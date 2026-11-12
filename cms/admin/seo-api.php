<?php
/**
 * Chiamate del pannello SEO e AEO. POST con il token della sessione, risposta JSON.
 *   analisi   i campi di un contenuto (title, slug, seo_title, meta_description, keyword_target, keywords_secondarie,
 *             contenuto, content_format, immagine, immagine_alt, tldr, faq, type) -> punteggio, voci, html del riquadro
 *   jsonld    url -> il JSON-LD che quella pagina emette, validato
 *   ispeziona url -> verdetto di Search Console (richiede il service account)
 *   sedi_cerca indirizzo -> lat, lng, nome dal servizio delle mappe (2.27.0, solo amministratore)
 *   tabella_salva tipo, id, seo_title, descrizione -> la Tabella SEO salva titolo per Google e descrizione di un contenuto (2.29.0,
 *             anche il redattore: passa da contenuti_salva_rapido come la scheda); risponde con le righe ricalcolate
 *   media_alt_salva url, alt -> l'alt di un'immagine della libreria (2.29.0, Media > Senza alt; anche il redattore)
 *   media_dato_salva url, campo, valore -> un campo dei dati di un'immagine (2.38.0, Media > Dati delle immagini; anche il redattore)
 * Usata dalla scheda del contenuto (assets/seo-controllo.js) e dall'editor visuale (Impostazioni della pagina).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/sito/viste.php';
require_once dirname(__DIR__) . '/sito/extra.php';
require_once dirname(__DIR__) . '/includes/moduli.php';
require_once dirname(__DIR__) . '/includes/seo.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function seo_api_esci(array $dati, int $codice = 200): never {
    http_response_code($codice);
    echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$azione = (string)($_POST['azione'] ?? $_GET['azione'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok((string)($_POST['_csrf'] ?? ''))) { // sicurezza: token e Origin/Referer
    seo_api_esci(['errore' => 'Sessione scaduta: ricarica la pagina.'], 403);
}

try {
    switch ($azione) {
        case 'analisi':
            $c = $_POST;
            if (isset($c['faq_testo']) && !isset($c['faq'])) { require_once __DIR__ . '/includes/admin-functions.php'; $c['faq'] = admin_faq_da_testo((string)$c['faq_testo']); }
            $a = seo_analisi($c);
            seo_api_esci(['punteggio' => $a['punteggio'], 'colore' => $a['colore'], 'voci' => $a['voci'], 'parole' => $a['parole'], 'html' => seo_analisi_html($a)]);

        case 'jsonld':
            $g = seo_jsonld_di((string)($_POST['url'] ?? '/'));
            if (isset($g['errore'])) seo_api_esci(['errore' => $g['errore']], 404);
            $testo = json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            // Controllo di forma: ogni nodo ha @type, gli @id citati esistono.
            $avvisi = [];
            $ids = array_filter(array_map(fn($n) => $n['@id'] ?? null, $g['@graph']));
            foreach ($g['@graph'] as $i => $n) {
                if (empty($n['@type'])) $avvisi[] = "Il nodo $i non ha @type.";
                array_walk_recursive($n, function ($v, $k) use ($ids, &$avvisi, $n) { if ($k === '@id' && !in_array($v, $ids, true) && !str_contains($v, '#pagina') && !str_contains($v, '#logo')) $avvisi[] = 'Riferimento ' . $v . ' senza nodo (in ' . ($n['@type'] ?? '?') . ').'; });
            }
            seo_api_esci(['testo' => $testo, 'nodi' => count($g['@graph']), 'tipi' => array_values(array_unique(array_map(fn($n) => $n['@type'] ?? '?', $g['@graph']))), 'avvisi' => array_values(array_unique($avvisi))]);

        case 'sedi_cerca':
            // 2.27.0: le coordinate di un indirizzo, dal server (includes/sedi.php, Nominatim di OpenStreetMap). Solo amministratore.
            if (!is_admin()) seo_api_esci(['errore' => 'Riservato all\'amministratore.'], 403);
            require_once dirname(__DIR__) . '/includes/sedi.php';
            $g = sedi_geocodifica((string)($_POST['indirizzo'] ?? ''));
            seo_api_esci($g['ok'] ? ['ok' => true, 'lat' => $g['lat'], 'lng' => $g['lng'], 'nome' => $g['nome']] : ['ok' => false, 'errore' => $g['errore']], $g['ok'] ? 200 : 422);

        case 'ispeziona':
            if (!is_admin()) seo_api_esci(['errore' => 'Riservato all\'amministratore.'], 403);
            $r = seo_gsc_ispeziona((string)($_POST['url'] ?? ''));
            if (isset($r['errore'])) seo_api_esci(['errore' => $r['errore']], 502);
            unset($r['grezzo']);
            $r['copertura_it'] = seo_copertura_italiano($r['copertura']);
            seo_api_esci($r);

        case 'tabella_salva':
            // 2.29.0: la Tabella SEO (admin/seo-tabella.php, includes/seo-tabella.php). Il redattore salva i contenuti come dalla scheda:
            // niente configurazione. Niente HTML nei campi (strip_tags in seo_tabella_salva), escape in uscita nel pannello.
            require_once dirname(__DIR__) . '/includes/seo-tabella.php';
            require_once dirname(__DIR__) . '/includes/editor-controlli.php';
            require_once dirname(__DIR__) . '/includes/editor.php';
            $tipo = (string)($_POST['tipo'] ?? ''); $id = (int)($_POST['id'] ?? 0);
            if (!isset(contenuti_tipi()[$tipo]) || $id <= 0) seo_api_esci(['errore' => 'Contenuto non trovato.'], 404);
            $d = [];
            if (isset($_POST['seo_title'])) $d['seo_title'] = (string)$_POST['seo_title'];
            if (isset($_POST['descrizione'])) $d['descrizione'] = (string)$_POST['descrizione'];
            try { $righe = seo_tabella_salva($tipo, $id, $d, (string)(current_user()['username'] ?? '')); } catch (RuntimeException $e) { seo_api_esci(['errore' => $e->getMessage()], 400); }
            $mia = null; $tutte = [];
            foreach ($righe as $r) {
                $x = ['tipo' => $r['tipo'], 'id' => $r['id'], 'sem_titolo' => $r['sem_titolo'], 'sem_descrizione' => $r['sem_descrizione'], 'problemi' => $r['problemi'], 'lunghezza_titolo' => $r['lunghezza_titolo'], 'lunghezza_descrizione' => $r['lunghezza_descrizione']];
                $tutte[] = $x;
                if ($r['tipo'] === $tipo && $r['id'] === $id) $mia = $x + ['seo_title' => $r['seo_title'], 'titolo_auto' => $r['titolo_auto'], 'titolo_effettivo' => $r['titolo_effettivo'], 'descrizione' => $r['descrizione']];
            }
            seo_api_esci(['ok' => true, 'riga' => $mia, 'righe' => $tutte, 'con_problemi' => count(array_filter($righe, fn($r) => $r['problemi'] > 0))]);

        case 'media_alt_salva':
            // 2.29.0: l'alt di un'immagine della libreria (includes/media-alt.php). Solo immagini che stanno in Media; testo semplice.
            require_once dirname(__DIR__) . '/includes/media.php';
            require_once dirname(__DIR__) . '/includes/media-alt.php';
            $r = media_alt_salva((string)($_POST['url'] ?? ''), (string)($_POST['alt'] ?? ''));
            seo_api_esci($r, isset($r['errore']) ? 400 : 200);

        case 'media_dato_salva':
            // 2.38.0: un campo dei dati di un'immagine della libreria (Media > Dati delle immagini; anche il redattore).
            require_once dirname(__DIR__) . '/includes/media-dati.php';
            $campo = (string)($_POST['campo'] ?? '');
            if (!in_array($campo, ['alt', 'titolo', 'didascalia', 'descrizione', 'autore'], true)) seo_api_esci(['errore' => 'Campo sconosciuto.'], 400);
            $r = media_dati_salva((string)($_POST['url'] ?? ''), [$campo => (string)($_POST['valore'] ?? '')], [$campo]);
            if (isset($r['errore'])) seo_api_esci(['errore' => $r['errore']], 400);
            seo_api_esci(['ok' => true, 'campo' => $campo, 'valore' => (string)$r['dati'][$campo], 'percorso' => $r['percorso']]);

        default:
            seo_api_esci(['errore' => 'Azione sconosciuta.'], 400);
    }
} catch (Throwable $e) {
    error_log('seo-api: ' . $e->getMessage());
    seo_api_esci(['errore' => 'Errore: ' . $e->getMessage()], 500);
}
