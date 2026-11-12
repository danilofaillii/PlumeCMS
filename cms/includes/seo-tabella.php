<?php
/**
 * 2.29.0: la Tabella SEO di tutto il sito (admin/seo-tabella.php): una riga per ogni contenuto (articoli, pagine e i tipi dei
 * moduli, bozze comprese) con titolo, titolo per Google, descrizione, lunghezze, robots, un semaforo per il titolo e uno per la
 * descrizione, le immagini senza alt. Titolo per Google e descrizione si correggono nella riga e si salvano senza ricaricare
 * (seo-api.php, azione tabella_salva -> seo_tabella_salva(), che passa da contenuti_salva_rapido: revisione, redirect,
 * IndexNow come dalla scheda; l'albero dell'editor non si tocca).
 *
 * Semaforo del titolo (sul titolo che esce davvero in <title>: il titolo per Google se c'è, altrimenti il titolo con il formato
 * di Globale SEO): verde da 30 a 60 caratteri; giallo fuori ma presente; rosso se manca, oltre 70 o uguale a quello di un
 * altro contenuto. Semaforo della descrizione (quella che il sito usa: seo_description, altrimenti meta_description): verde da
 * 70 a 160; giallo fuori; rosso se manca o doppia.
 */

require_once __DIR__ . '/media-alt.php';

const SEO_TABELLA_TITOLO_MIN = 30;
const SEO_TABELLA_TITOLO_MAX = 60;
const SEO_TABELLA_TITOLO_TROPPO = 70;
const SEO_TABELLA_DESCR_MIN = 70;
const SEO_TABELLA_DESCR_MAX = 160;

/** Il tipo SEO (il formato del titolo in Globale SEO) di un contenuto: home, articolo, pagina o il nome del tipo del modulo. */
function seo_tabella_tipo_seo(string $tipo, array $r): string {
    if ($tipo === 'pagine') return $r['slug'] === (string)get_setting('pagina_home', '') ? 'home' : 'pagina';
    if ($tipo === 'articoli') return 'articolo';
    return (string)(contenuti_tipi()[$tipo]['uno'] ?? 'altro');
}

/** Il titolo che esce davvero in <title>: il titolo per Google se c'è, altrimenti il titolo con il formato del suo tipo (seo_titolo). */
function seo_tabella_titolo_effettivo(string $tipo, array $r): string {
    $suo = trim((string)($r['seo_title'] ?? ''));
    if ($suo !== '') return $suo;
    $tipoSeo = seo_tabella_tipo_seo($tipo, $r);
    $base = $tipoSeo === 'home' ? (string)cms_impostazione('home_titolo', cms_impostazione('sito_nome', CMS_NOME)) : (string)$r['title'];
    return seo_titolo($base, $tipoSeo);
}

/** La descrizione che il sito usa (sito-dati.php: seo_description, altrimenti meta_description) e in quale colonna sta. */
function seo_tabella_descrizione(array $r): array {
    $sd = trim((string)($r['seo_description'] ?? '')); $md = trim((string)($r['meta_description'] ?? ''));
    if ($sd !== '') return ['testo' => $sd, 'colonna' => 'seo_description'];
    if ($md !== '') return ['testo' => $md, 'colonna' => 'meta_description'];
    // Tutte e due vuote: si scriverà dove la legge lo strumento con cui il contenuto è fatto (l'editor legge seo_description, la scheda meta_description).
    return ['testo' => '', 'colonna' => trim((string)($r['struttura'] ?? '')) !== '' ? 'seo_description' : 'meta_description'];
}

/** La regola di indicizzazione che vale sul sito per questo contenuto: index o noindex. */
function seo_tabella_robots(string $tipo, array $r): string {
    if ($tipo === 'articoli') return seo_robots_tipo('articoli', function_exists('sito_robots_articolo') ? sito_robots_articolo($r) : (($r['robots'] ?? '') === 'noindex' ? 'noindex' : 'index'));
    return seo_robots_tipo($tipo === 'pagine' ? 'pagine' : $tipo, ($r['robots'] ?? '') === 'noindex' ? 'noindex' : 'index');
}

/** Il semaforo del titolo: colore (verde, giallo, rosso), segno e testo in parole. */
function seo_tabella_semaforo_titolo(string $t, string $doppioDi = ''): array {
    $n = mb_strlen($t);
    if ($n === 0) return ['colore' => 'rosso', 'testo' => 'manca il titolo'];
    if ($doppioDi !== '') return ['colore' => 'rosso', 'testo' => "$n caratteri, uguale a quello di «" . mb_substr($doppioDi, 0, 40) . '»'];
    if ($n > SEO_TABELLA_TITOLO_TROPPO) return ['colore' => 'rosso', 'testo' => "$n caratteri: troppo lungo, Google lo taglia (massimo " . SEO_TABELLA_TITOLO_TROPPO . ')'];
    if ($n < SEO_TABELLA_TITOLO_MIN) return ['colore' => 'giallo', 'testo' => "$n caratteri: corto (da " . SEO_TABELLA_TITOLO_MIN . ' a ' . SEO_TABELLA_TITOLO_MAX . ')'];
    if ($n > SEO_TABELLA_TITOLO_MAX) return ['colore' => 'giallo', 'testo' => "$n caratteri: un po' lungo (da " . SEO_TABELLA_TITOLO_MIN . ' a ' . SEO_TABELLA_TITOLO_MAX . ')'];
    return ['colore' => 'verde', 'testo' => "$n caratteri"];
}

/** Il semaforo della descrizione. */
function seo_tabella_semaforo_descrizione(string $d, string $doppiaDi = ''): array {
    $n = mb_strlen($d);
    if ($n === 0) return ['colore' => 'rosso', 'testo' => 'manca: lo snippet lo decide Google'];
    if ($doppiaDi !== '') return ['colore' => 'rosso', 'testo' => "$n caratteri, uguale a quella di «" . mb_substr($doppiaDi, 0, 40) . '»'];
    if ($n < SEO_TABELLA_DESCR_MIN) return ['colore' => 'giallo', 'testo' => "$n caratteri: corta (da " . SEO_TABELLA_DESCR_MIN . ' a ' . SEO_TABELLA_DESCR_MAX . ')'];
    if ($n > SEO_TABELLA_DESCR_MAX) return ['colore' => 'giallo', 'testo' => "$n caratteri: lunga, Google taglia a " . SEO_TABELLA_DESCR_MAX];
    return ['colore' => 'verde', 'testo' => "$n caratteri"];
}

/** Una chiave per confrontare titoli e descrizioni (minuscole, spazi uniti). */
function seo_tabella_norm(string $s): string {
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
}

/**
 * Tutte le righe della tabella, ordinate con i problemi in cima (rosso, giallo, verde) e poi per titolo. Ogni riga: tipo,
 * tipo_nome, id, titolo, stato, percorso, url_sito, seo_title, titolo_auto, titolo_effettivo, descrizione, descrizione_colonna,
 * robots, lunghezze, sem_titolo, sem_descrizione, problemi (0-2), alt_mancanti, scheda_url, editor_url.
 */
function seo_tabella_righe(): array {
    $righe = [];
    foreach (contenuti_tipi() as $tipo => $t) {
        try { $tutte = db()->query('SELECT * FROM ' . tbl($t['tabella']) . ' WHERE cestino IS NULL')->fetchAll(); } catch (Throwable $e) { error_log('seo_tabella_righe: ' . $e->getMessage()); continue; }
        foreach ($tutte as $r) {
            $d = seo_tabella_descrizione($r);
            $righe[] = [
                'tipo' => $tipo, 'tipo_nome' => (string)$t['Uno'], 'id' => (int)$r['id'], 'titolo' => (string)$r['title'], 'stato' => contenuti_stato($tipo, $r),
                'percorso' => contenuti_percorso($tipo, $r), 'url_sito' => cms_sito_base() . contenuti_percorso($tipo, $r),
                'seo_title' => (string)($r['seo_title'] ?? ''), 'titolo_auto' => seo_tabella_titolo_effettivo($tipo, ['seo_title' => ''] + $r), 'titolo_effettivo' => seo_tabella_titolo_effettivo($tipo, $r),
                'descrizione' => $d['testo'], 'descrizione_colonna' => $d['colonna'], 'robots' => seo_tabella_robots($tipo, $r),
                'alt_mancanti' => count(media_alt_contenuto($tipo, $r)),
                'scheda_url' => 'contenuto.php?tipo=' . $tipo . '&id=' . (int)$r['id'], 'editor_url' => 'editor.php?tipo=' . $tipo . '&id=' . (int)$r['id'],
            ];
        }
    }
    // I doppioni: lo stesso titolo (o la stessa descrizione) in due contenuti. Il primo che si trova dà il nome all'altro.
    $titoli = []; $descr = [];
    foreach ($righe as $i => $r) {
        $kt = seo_tabella_norm($r['titolo_effettivo']); if ($kt !== '') $titoli[$kt][] = $i;
        $kd = seo_tabella_norm($r['descrizione']); if ($kd !== '') $descr[$kd][] = $i;
    }
    foreach ($righe as $i => &$r) {
        $kt = seo_tabella_norm($r['titolo_effettivo']); $kd = seo_tabella_norm($r['descrizione']);
        $altroT = ''; $altroD = '';
        if ($kt !== '' && count($titoli[$kt]) > 1) foreach ($titoli[$kt] as $j) if ($j !== $i) { $altroT = $righe[$j]['titolo']; break; }
        if ($kd !== '' && count($descr[$kd]) > 1) foreach ($descr[$kd] as $j) if ($j !== $i) { $altroD = $righe[$j]['titolo']; break; }
        $r['sem_titolo'] = seo_tabella_semaforo_titolo($r['titolo_effettivo'], $altroT);
        $r['sem_descrizione'] = seo_tabella_semaforo_descrizione($r['descrizione'], $altroD);
        $r['lunghezza_titolo'] = mb_strlen($r['titolo_effettivo']); $r['lunghezza_descrizione'] = mb_strlen($r['descrizione']);
        $r['problemi'] = ($r['sem_titolo']['colore'] !== 'verde' ? 1 : 0) + ($r['sem_descrizione']['colore'] !== 'verde' ? 1 : 0);
        $r['peso'] = ['rosso' => 0, 'giallo' => 1, 'verde' => 2][$r['sem_titolo']['colore']] + ['rosso' => 0, 'giallo' => 1, 'verde' => 2][$r['sem_descrizione']['colore']];
    }
    unset($r);
    usort($righe, fn($a, $b) => $a['peso'] <=> $b['peso'] ?: strcmp(mb_strtolower($a['titolo']), mb_strtolower($b['titolo'])));
    return $righe;
}

/**
 * Salva dalla riga titolo per Google e descrizione di un contenuto, con la stessa strada della scheda (contenuti_salva_rapido:
 * revisione, redirect, IndexNow; l'albero dell'editor non si tocca). Ritorna le righe ricalcolate (i doppioni cambiano anche
 * le altre). Lancia RuntimeException con un messaggio da mostrare.
 */
function seo_tabella_salva(string $tipo, int $id, array $d, string $autore = ''): array {
    if (!isset(contenuti_tipi()[$tipo])) throw new RuntimeException('Tipo di contenuto sconosciuto.');
    $r = contenuti_leggi($tipo, $id);
    if (!$r || !empty($r['cestino'])) throw new RuntimeException('Contenuto non trovato (forse è nel cestino): ricarica la pagina.');
    $campi = ['autore' => $autore];
    if (array_key_exists('seo_title', $d)) $campi['seo_title'] = mb_substr(trim(str_replace(["\r", "\n"], ' ', strip_tags((string)$d['seo_title']))), 0, 255);
    if (array_key_exists('descrizione', $d)) {
        $testo = mb_substr(trim(str_replace(["\r", "\n"], ' ', strip_tags((string)$d['descrizione']))), 0, 500);
        $col = seo_tabella_descrizione($r)['colonna'];
        $campi[$col] = $testo;
        // Se la descrizione effettiva stava nell'altra colonna e ora si svuota, la colonna di riserva non deve tornare fuori a sorpresa.
        if ($testo === '' && $col === 'seo_description' && trim((string)($r['meta_description'] ?? '')) !== '') $campi['meta_description'] = '';
    }
    contenuti_salva_rapido($tipo, $id, $campi);
    return seo_tabella_righe();
}
