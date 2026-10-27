<?php
/**
 * Le chiamate dell'editor delle pagine. Tutte POST, con il token della sessione, risposta JSON.
 *   disegna      albero → html e css per la tela (con gli attributi per selezionare e trascinare)
 *   salva        albero, pubblica (0/1), nota → bozza oppure versione pubblica; in entrambi i casi una versione (includes/versioni.php)
 *   revisioni    elenco delle versioni del contenuto (bozze e pubblicate, dalla tabella unica delle versioni)
 *   revisione    una versione, da caricare nella tela
 *   confronta    versione, con (precedente | attuale | id) → il confronto leggibile in HTML
 *   nota         versione, testo → una nota scritta a mano (la versione non si pota più)
 *   impostazioni titolo, slug, seo, robots, stato
 *   togli        il contenuto torna al testo della scheda (le revisioni restano)
 *   carica       un'immagine in wp-content/uploads/pagine/AAAA/MM/
 *   media        le immagini già caricate
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/sito/viste.php';
require_once dirname(__DIR__) . '/includes/moduli.php';
require_once dirname(__DIR__) . '/includes/editor.php';
if (is_file(dirname(__DIR__) . '/includes/modelli.php')) require_once dirname(__DIR__) . '/includes/modelli.php'; // modelli
require_once dirname(__DIR__) . '/includes/editor-temi.php';
require_once dirname(__DIR__) . '/includes/versioni.php'; // versioni
require_once dirname(__DIR__) . '/includes/media.php';
if (is_file(dirname(__DIR__) . '/includes/seo.php')) require_once dirname(__DIR__) . '/includes/seo.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_esci(array $dati, int $codice = 200): never {
    http_response_code($codice);
    echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok((string)($_POST['_csrf'] ?? ''))) { // sicurezza: token e Origin/Referer
    api_esci(['errore' => 'Sessione scaduta: ricarica la pagina. Le modifiche non salvate restano nella tela finché non chiudi la scheda.'], 403);
}
editor_schema();
$azione = (string)($_POST['azione'] ?? '');
$tipo = editor_tipo_valido($_POST['tipo'] ?? '');
$tab = tbl(editor_tabella($tipo));
$id = (int)($_POST['id'] ?? 0);
$pagina = $id ? editor_pagina($id, $tipo) : null;
if (!in_array($azione, ['carica', 'media', 'markdown'], true) && !$pagina) api_esci(['errore' => 'Pagina non trovata.'], 404);
$percorso = $pagina ? editor_percorso_pagina($pagina) : '/';

function api_albero(): array {
    global $pagina;
    $a = editor_valida(json_decode((string)($_POST['albero'] ?? ''), true));
    if ($a === null) api_esci(['errore' => 'La struttura della pagina non è leggibile.'], 400);
    // sicurezza: il widget HTML e dell'amministratore. Per un redattore ogni blocco html nuovo o cambiato diventa testo pulito.
    if (!editor_puo_codice()) {
        $salvato = $pagina ? (editor_decodifica((string)($pagina['struttura_bozza'] ?: $pagina['struttura'])) ?? null) : null;
        $pubblicato = $pagina && !empty($pagina['struttura']) ? editor_decodifica((string)$pagina['struttura']) : null;
        if ($salvato && $pubblicato && $salvato !== $pubblicato) $salvato['elementi'] = array_merge($salvato['elementi'], $pubblicato['elementi']);
        $a = editor_blinda_codice($a, $salvato);
    }
    return $a;
}

switch ($azione) {
    case 'disegna':
        $a = api_albero();
        [$html, $css] = editor_disegna($a, $percorso, true);
        api_esci(['html' => $html, 'css' => $css, 'h1' => editor_ha_h1($a)]);

    case 'salva':
        $a = api_albero();
        $pubblica = !empty($_POST['pubblica']);
        if ($pubblica && !editor_puo_pubblicare()) api_esci(['errore' => 'Solo un amministratore può pubblicare dall\'editor (Editor > Impostazioni dell\'editor). La bozza resta salvata.'], 403); // menu
        editor_salva($id, $a, $pubblica, (string)(current_user()['username'] ?? ''), (string)($_POST['nota'] ?? ''), $tipo);
        if ($pubblica && $tipo === 'modelli') {
            // modelli: Pubblica nell'editor pubblica anche il modello (la tabella non ha publish_date).
            modelli_stato($id, true);
        } elseif ($pubblica && $pagina['status'] !== 'published') {
            if ($tipo === 'pagine') db()->prepare('UPDATE ' . $tab . " SET status = 'published', publish_at = COALESCE(publish_at, NOW()) WHERE id = ?")->execute([$id]);
            else db()->prepare('UPDATE ' . $tab . " SET status = 'published', publish_date = COALESCE(publish_date, CURDATE()) WHERE id = ?")->execute([$id]);
        }
        api_esci(['ok' => true, 'quando' => date('H:i'), 'pubblicata' => $pubblica, 'h1' => editor_ha_h1($a)]);

    case 'revisioni':
        // versioni: la stessa tabella e lo stesso confronto della pagina Versioni (includes/versioni.php).
        $ambito = $tipo === 'modelli' ? 'modello' : 'contenuto:' . $tipo;
        $righe = [];
        foreach (editor_versioni($ambito, $id, 60) as $r) {
            $righe[] = ['id' => (int)$r['id'], 'nota' => $r['nota'], 'autore' => $r['autore'], 'creato' => $r['creato'], 'peso' => (int)$r['dimensione'], 'pubblicata' => (bool)$r['pubblicata'], 'nota_mano' => (bool)$r['nota_mano']];
        }
        api_esci(['revisioni' => $righe, 'url' => 'versioni.php?ambito=' . rawurlencode($ambito) . '&oggetto=' . $id]);

    case 'revisione':
        $v = editor_versione((int)($_POST['revisione'] ?? 0));
        $ambito = $tipo === 'modelli' ? 'modello' : 'contenuto:' . $tipo;
        if (!$v || $v['ambito'] !== $ambito || (int)$v['oggetto_id'] !== $id) api_esci(['errore' => 'Versione non trovata.'], 404);
        $a = is_array($v['dati']['albero'] ?? null) ? editor_valida($v['dati']['albero']) : null;
        if (!$a) api_esci(['errore' => 'Questa versione non ha una struttura dell\'editor (è una copia della scheda classica).'], 400);
        api_esci(['albero' => $a]);

    case 'confronta':
        $v = editor_versione((int)($_POST['versione'] ?? 0));
        $ambito = $tipo === 'modelli' ? 'modello' : 'contenuto:' . $tipo;
        if (!$v || $v['ambito'] !== $ambito || (int)$v['oggetto_id'] !== $id) api_esci(['errore' => 'Versione non trovata.'], 404);
        $con = (string)($_POST['con'] ?? 'precedente');
        try {
            if ($con === 'attuale') {
                $diff = editor_versioni_confronta($v, ['ambito' => $ambito, 'oggetto_id' => $id, 'dati' => versioni_dati_attuali($ambito, $id) ?? []]);
                $html = versioni_confronto_html($diff, 'Versione ' . $v['id'], 'Adesso');
            } elseif ($con === 'tela') {
                $diff = editor_versioni_confronta($v, ['ambito' => $ambito, 'oggetto_id' => $id, 'dati' => ['albero' => api_albero()]]);
                $html = versioni_confronto_html($diff, 'Versione ' . $v['id'], 'La tela');
            } else {
                $prec = $con === 'precedente' ? versioni_precedente($v) : editor_versione((int)$con);
                if (!$prec) api_esci(['html' => '<p class="vs-uguali">È la prima versione: non c\'è niente di precedente con cui confrontarla.</p>', 'riassunto' => 'prima versione']);
                $diff = editor_versioni_confronta($prec, $v);
                $html = versioni_confronto_html($diff, 'Versione ' . $prec['id'], 'Versione ' . $v['id']);
            }
        } catch (Throwable $e) { api_esci(['errore' => 'Confronto non riuscito: ' . $e->getMessage()], 500); }
        api_esci(['html' => $html, 'riassunto' => $diff['riassunto']]);

    case 'nota':
        $v = editor_versione((int)($_POST['versione'] ?? 0));
        $ambito = $tipo === 'modelli' ? 'modello' : 'contenuto:' . $tipo;
        if (!$v || $v['ambito'] !== $ambito || (int)$v['oggetto_id'] !== $id) api_esci(['errore' => 'Versione non trovata.'], 404);
        versioni_nota((int)$v['id'], (string)($_POST['testo'] ?? ''));
        api_esci(['ok' => true]);

    case 'impostazioni':
        // Titolo, indirizzo, stato e dati per Google: la stessa strada della scheda classica (contenuti_salva_rapido).
        if ($tipo === 'modelli') {
            // modelli: solo il nome e lo stato.
            try { modelli_rinomina($id, (string)($_POST['titolo'] ?? '')); modelli_stato($id, ($_POST['stato'] ?? '') === 'published'); } catch (Throwable $e) { api_esci(['errore' => $e->getMessage()], 400); }
            api_esci(['ok' => true, 'slug' => '', 'percorso' => '/']);
        }
        require_once dirname(__DIR__) . '/includes/contenuti.php';
        $stato = ($_POST['stato'] ?? '') === 'published' ? 'pubblicato' : 'bozza';
        if ($stato === 'bozza' && editor_e_home($pagina)) api_esci(['errore' => 'La home non può andare in bozza: il sito resterebbe senza pagina iniziale.'], 400);
        try {
            $campi = ['title' => (string)($_POST['titolo'] ?? ''), 'stato' => $stato, 'autore' => (string)(current_user()['username'] ?? '')];
            if (isset($_POST['slug'])) $campi['slug'] = (string)$_POST['slug'];
            // modelli: header e footer scelti per questo contenuto (0 predefinito, -1 nessuno).
            if (isset($_POST['header_id'])) $campi['header_id'] = (int)$_POST['header_id'];
            if (isset($_POST['footer_id'])) $campi['footer_id'] = (int)$_POST['footer_id'];
            contenuti_salva_rapido($tipo, $id, $campi);
            db()->prepare('UPDATE ' . $tab . ' SET seo_title = ?, seo_description = ?, robots = ? WHERE id = ?')
                ->execute([mb_substr(trim((string)($_POST['seo_titolo'] ?? '')), 0, 255), mb_substr(trim((string)($_POST['seo_descrizione'] ?? '')), 0, 1000), ($_POST['robots'] ?? '') === 'noindex' ? 'noindex' : '', $id]);
        } catch (RuntimeException $e) {
            api_esci(['errore' => $e->getMessage()], 400);
        }
        $nuova = editor_pagina($id, $tipo);
        api_esci(['ok' => true, 'slug' => $nuova['slug'], 'percorso' => editor_percorso_pagina($nuova)]);

    case 'temi':
        // I temi delle infografiche salvati da Danilo: salva (nome + valori JSON), rinomina (tema, nome), elimina (tema). Risponde con l'elenco aggiornato.
        try {
            $cosa = (string)($_POST['cosa'] ?? '');
            $temaId = (int)preg_replace('/\D/', '', (string)($_POST['tema'] ?? ''));
            if ($cosa === 'salva') {
                $val = json_decode((string)($_POST['valori'] ?? ''), true);
                $nuovo = 't' . editor_tema_salva((string)($_POST['nome'] ?? ''), is_array($val) ? $val : []);
            } elseif ($cosa === 'rinomina') { editor_tema_rinomina($temaId, (string)($_POST['nome'] ?? '')); $nuovo = 't' . $temaId; }
            elseif ($cosa === 'elimina') { editor_tema_elimina($temaId); $nuovo = 'carta'; }
            else api_esci(['errore' => 'Richiesta sui temi non riconosciuta.'], 400);
        } catch (RuntimeException $e) {
            api_esci(['errore' => $e->getMessage()], 400);
        }
        api_esci(['ok' => true, 'temi' => editor_temi_infografica(), 'opzioni' => editor_temi_opzioni(), 'scelto' => $nuovo]);

    case 'seo':
        // Il controllo SEO del contenuto com'è nella tela (includes/seo.php), come Rank Math.
        if (!function_exists('seo_analisi') || $tipo === 'modelli') api_esci(['html' => '']);
        $a = api_albero();
        [$h] = editor_disegna($a, $percorso);
        $riga = $pagina + ['contenuto' => $h, 'content_format' => 'html'];
        $riga['title'] = (string)($_POST['titolo'] ?? $pagina['title']);
        $riga['seo_title'] = (string)($_POST['seo_titolo'] ?? $pagina['seo_title']);
        $riga['seo_description'] = (string)($_POST['seo_descrizione'] ?? $pagina['seo_description']);
        if (!empty($_POST['slug'])) $riga['slug'] = (string)$_POST['slug'];
        try { api_esci(['html' => seo_analisi_html(seo_analisi($riga))]); } catch (Throwable $e) { api_esci(['html' => '<p class="ed-aiuto">Controllo SEO non disponibile: ' . h($e->getMessage()) . '</p>']); }

    case 'togli':
        db()->prepare('UPDATE ' . $tab . ' SET struttura = NULL, struttura_bozza = NULL, struttura_aggiornata = NULL WHERE id = ?')->execute([$id]);
        api_esci(['ok' => true]);

    case 'markdown':
        // La scheda Visuale di un articolo scritto in Markdown: lo mostra convertito.
        api_esci(['html' => markdown_to_html((string)($_POST['testo'] ?? ''))]);

    case 'carica':
        $r = media_carica($_FILES['immagine'] ?? null, 'pagine');
        api_esci($r);

    case 'media':
        // La stessa libreria della voce Media: tutte le cartelle, a pagine di 60.
        $voci = media_filtra((string)($_POST['cartella'] ?? ''), (string)($_POST['q'] ?? ''), !empty($_POST['miniature']));
        $pagina = max(1, (int)($_POST['pagina'] ?? 1));
        $cartelle = media_cartelle();
        $conta = [];
        foreach (media_elenco() as $m) if (!$m['miniatura']) $conta[$m['cartella']] = ($conta[$m['cartella']] ?? 0) + 1;
        api_esci([
            'totale' => count($voci), 'pagina' => $pagina, 'pagine' => max(1, (int)ceil(count($voci) / 60)),
            'cartelle' => array_map(fn($k, $n) => ['chiave' => $k, 'nome' => $n, 'quante' => $conta[$k] ?? 0], array_keys($cartelle), $cartelle),
            'immagini' => array_map(fn($m) => ['url' => $m['url'], 'nome' => $m['nome'], 'cartella' => $cartelle[$m['cartella']] ?? $m['cartella'], 'sotto' => $m['sotto']], array_slice($voci, ($pagina - 1) * 60, 60)),
        ]);
}
api_esci(['errore' => 'Azione sconosciuta.'], 400);
