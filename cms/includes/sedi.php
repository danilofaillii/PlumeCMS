<?php
/**
 * 2.27.0: le sedi dell'attività e la SEO geografica (richiesta di Danilo: più sedi fin dall'inizio).
 *
 * Le sedi stanno nel gruppo "sedi" della configurazione SEO (impostazione seo_config, includes/seo.php), come elenco
 * JSON: niente tabelle nuove. Ogni sede ha un id (slug stabile), nome, tipo (includes/sedi-tipi.php), indirizzo a
 * campi, coordinate, telefono, email, fascia di prezzo, zona servita, orari nello stesso formato del widget Orari di
 * apertura (lun..dom, chiusure), link alla scheda Google e alla recensione, foto, descrizione, principale, pagina.
 *
 * Da qui escono: i nodi LocalBusiness del JSON-LD (location dell'Organizzazione, mainEntity della pagina della sede,
 * areaServed dei servizi), la città per %citta% nei titoli, i valori per i widget Mappa, Orari e Barra contatti che
 * leggono "dalla sede", i widget Sedi e Dati dell'attività (includes/editor-widget-sedi.php), la pagina della sede
 * (una pagina vera dell'editor) e la ricerca delle coordinate con Nominatim di OpenStreetMap (dal server, mai dal
 * browser). Senza sedi configurate il sito esce identico a prima, al byte.
 */

require_once __DIR__ . '/sedi-tipi.php';
require_once __DIR__ . '/seo.php';

const SEDI_MAX = 50;
const SEDI_ZONA_MAX = 30;
const SEDI_CHIUSURE_MAX = 20;
const SEDI_GIORNI = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
const SEDI_PREZZI = ['', '€', '€€', '€€€', '€€€€'];
/** Gli host ammessi per la scheda Google e il link della recensione (solo https). */
const SEDI_HOST_GOOGLE = ['google.com', 'g.page', 'maps.app.goo.gl'];
/** Il servizio che trasforma un indirizzo in coordinate; CMS_NOMINATIM_URL nel config lo sostituisce (serve alle prove). */
const SEDI_NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

// ------------------------------------------------------------
// Lettura e scrittura
// ------------------------------------------------------------

/** Una sede con tutti i campi al valore di partenza. */
function sedi_vuota(): array {
    return ['id' => '', 'nome' => '', 'tipo' => 'negozio', 'tipo_testo' => '', 'via' => '', 'cap' => '', 'citta' => '', 'provincia' => '', 'paese' => 'Italia',
        'lat' => '', 'lng' => '', 'telefono' => '', 'email' => '', 'prezzo' => '', 'zona' => [], 'orari' => array_fill_keys(SEDI_GIORNI, ''), 'chiusure' => [],
        'google_scheda' => '', 'google_recensione' => '', 'foto' => '', 'descrizione' => '', 'principale' => false, 'pagina' => 0];
}

/** Un testo di una riga, senza tag, al massimo $max caratteri. */
function sedi_testo($x, int $max): string {
    $s = is_scalar($x) ? (string)$x : '';
    $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? '');
    return mb_substr($s, 0, $max);
}

/** Una coordinata: numero vero fra -$max e +$max (anche con la virgola), come testo con sei decimali; altrimenti ''. */
function sedi_coordinata($x, float $max): string {
    if (!is_scalar($x)) return '';
    $s = str_replace(',', '.', trim((string)$x));
    if ($s === '' || !preg_match('/^-?\d{1,3}(\.\d{1,12})?$/', $s)) return '';
    $n = (float)$s;
    if (!is_finite($n) || $n < -$max || $n > $max) return '';
    return rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
}

/** Un link alla scheda Google o alla recensione: solo https verso google.com (e sottodomini), g.page, maps.app.goo.gl. */
function sedi_link_google_ok(string $u): bool {
    $u = trim($u);
    if ($u === '' || strlen($u) > 500 || preg_match('/[\s"\'<>\\\\]/', $u)) return false;
    $p = parse_url($u);
    if (!is_array($p) || strtolower((string)($p['scheme'] ?? '')) !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return false;
    $host = strtolower($p['host']);
    foreach (SEDI_HOST_GOOGLE as $h) if ($host === $h || str_ends_with($host, '.' . $h)) return true;
    return false;
}

/** Le righe della zona servita: pulite, senza doppioni, al massimo SEDI_ZONA_MAX, ognuna al massimo 80 caratteri. */
function sedi_zona_righe($x): array {
    $righe = is_array($x) ? $x : preg_split('/\r\n|\r|\n/', (string)$x);
    $out = [];
    foreach ($righe as $r) {
        $r = sedi_testo($r, 80);
        if ($r === '' || in_array($r, $out, true)) continue;
        $out[] = $r;
        if (count($out) >= SEDI_ZONA_MAX) break;
    }
    return $out;
}

/** Le chiusure da un testo a righe "AAAA-MM-GG", "AAAA-MM-GG - AAAA-MM-GG: motivo" (al massimo SEDI_CHIUSURE_MAX). */
function sedi_chiusure_da_testo(string $t): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $t) as $r) {
        $r = trim($r);
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s*(?:-|al|a)\s*(\d{4}-\d{2}-\d{2}))?\s*:?\s*(.*)$/u', $r, $m)) continue;
        $out[] = ['dal' => $m[1], 'al' => $m[2] !== '' && $m[2] >= $m[1] ? $m[2] : $m[1], 'motivo' => sedi_testo($m[3], 60)];
        if (count($out) >= SEDI_CHIUSURE_MAX) break;
    }
    return $out;
}

/** Le chiusure come testo a righe, per la casella del pannello. */
function sedi_chiusure_testo(array $chiusure): string {
    $righe = [];
    foreach ($chiusure as $c) $righe[] = $c['dal'] . ($c['al'] !== $c['dal'] ? ' - ' . $c['al'] : '') . ($c['motivo'] !== '' ? ': ' . $c['motivo'] : '');
    return implode("\n", $righe);
}

/** Una sede letta dal JSON (o da un modulo), con ogni campo pulito e al suo posto. */
function sedi_normalizza(array $s): array {
    $v = sedi_vuota();
    $v['id'] = preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', (string)($s['id'] ?? '')) ? (string)$s['id'] : '';
    $v['nome'] = sedi_testo($s['nome'] ?? '', 80);
    $v['tipo'] = sedi_tipo_valido((string)($s['tipo'] ?? 'negozio'));
    $v['tipo_testo'] = $v['tipo'] === SEDI_TIPO_ALTRO ? sedi_tipo_testo_pulito((string)($s['tipo_testo'] ?? '')) : '';
    foreach (['via' => 120, 'cap' => 10, 'citta' => 80, 'provincia' => 40, 'paese' => 60, 'telefono' => 40, 'email' => 120] as $k => $max) $v[$k] = sedi_testo($s[$k] ?? ($k === 'paese' ? 'Italia' : ''), $max);
    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $v['email'] = '';
    $v['telefono'] = preg_replace('/[^\d\s+().\/-]/', '', $v['telefono']) ?? '';
    $v['lat'] = sedi_coordinata($s['lat'] ?? '', 90);
    $v['lng'] = sedi_coordinata($s['lng'] ?? '', 180);
    if ($v['lat'] === '' || $v['lng'] === '') $v['lat'] = $v['lng'] = '';
    $v['prezzo'] = in_array((string)($s['prezzo'] ?? ''), SEDI_PREZZI, true) ? (string)$s['prezzo'] : '';
    $v['zona'] = sedi_zona_righe($s['zona'] ?? []);
    $orari = is_array($s['orari'] ?? null) ? $s['orari'] : [];
    foreach (SEDI_GIORNI as $g) $v['orari'][$g] = sedi_testo($orari[$g] ?? '', 80);
    $v['chiusure'] = [];
    foreach (array_slice(is_array($s['chiusure'] ?? null) ? $s['chiusure'] : [], 0, SEDI_CHIUSURE_MAX) as $c) {
        if (!is_array($c) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($c['dal'] ?? ''))) continue;
        $al = (string)($c['al'] ?? '');
        $v['chiusure'][] = ['dal' => (string)$c['dal'], 'al' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $al) && $al >= $c['dal'] ? $al : (string)$c['dal'], 'motivo' => sedi_testo($c['motivo'] ?? '', 60)];
    }
    foreach (['google_scheda', 'google_recensione'] as $k) $v[$k] = sedi_link_google_ok((string)($s[$k] ?? '')) ? trim((string)$s[$k]) : '';
    $foto = trim((string)($s['foto'] ?? ''));
    $v['foto'] = preg_match('~^(https?://|/)[^"\'<>\s]*$~', $foto) ? mb_substr($foto, 0, 500) : '';
    $v['descrizione'] = mb_substr(trim(strip_tags(str_replace("\r", '', (string)($s['descrizione'] ?? '')))), 0, 600);
    $v['principale'] = !empty($s['principale']);
    $v['pagina'] = max(0, (int)($s['pagina'] ?? 0));
    return $v;
}

/** Tutte le sedi, pulite, nell'ordine salvato. Una lettura per richiesta. */
function sedi_tutte(bool $azzera = false): array {
    static $cache = null;
    if ($cache !== null && !$azzera) return $cache;
    $elenco = seo_cfg($azzera)['sedi']['elenco'] ?? [];
    $out = [];
    foreach (is_array($elenco) ? $elenco : [] as $s) {
        if (!is_array($s)) continue;
        $n = sedi_normalizza($s);
        if ($n['id'] === '' || $n['nome'] === '' || isset($out[$n['id']])) continue;
        $out[$n['id']] = $n;
        if (count($out) >= SEDI_MAX) break;
    }
    // Una sola principale: la prima segnata, oppure la prima dell'elenco.
    $trovata = false;
    foreach ($out as $id => $s) { if ($s['principale'] && !$trovata) $trovata = true; elseif ($s['principale']) $out[$id]['principale'] = false; }
    if (!$trovata && $out) $out[array_key_first($out)]['principale'] = true;
    return $cache = $out;
}

/** La sede principale (null senza sedi). */
function sedi_principale(): ?array {
    foreach (sedi_tutte() as $s) if ($s['principale']) return $s;
    return null;
}

function sedi_leggi(string $id): ?array {
    return sedi_tutte()[$id] ?? null;
}

/** Scrive l'elenco nel gruppo "sedi" della configurazione SEO. */
function sedi_salva_elenco(array $elenco): void {
    $elenco = array_values(array_map('sedi_normalizza', $elenco));
    // Le emoji (4 byte) diventano entità: la tabella delle impostazioni è utf8 a 3 byte (seo_cfg() le rimette a posto).
    array_walk_recursive($elenco, function (&$x) { if (is_string($x)) $x = sito_senza_4byte($x); });
    seo_salva('sedi', ['elenco' => $elenco]);
    sedi_tutte(true);
}

/** Un id stabile dal nome ("Sede di Bologna" -> sede-di-bologna), diverso da quelli che ci sono. */
function sedi_id_libero(string $nome, array $elenco, string $corrente = ''): string {
    $base = contenuti_slug($nome) ?: 'sede';
    $base = mb_substr($base, 0, 50);
    $id = $base; $n = 2;
    while (isset($elenco[$id]) && $id !== $corrente) $id = $base . '-' . $n++;
    return $id;
}

/**
 * Controlla i campi arrivati dal pannello. Ritorna ['sede' => la sede pulita, 'errori' => [campo => messaggio]].
 * $idCorrente: l'id della sede che si sta modificando ('' per una nuova).
 */
function sedi_valida(array $in, string $idCorrente = ''): array {
    $errori = [];
    $s = $in;
    $s['zona'] = sedi_zona_righe($in['zona'] ?? '');
    $s['chiusure'] = sedi_chiusure_da_testo((string)($in['chiusure'] ?? ''));
    $orari = [];
    foreach (SEDI_GIORNI as $g) $orari[$g] = (string)($in['orari'][$g] ?? ($in['orario_' . $g] ?? ''));
    $s['orari'] = $orari;
    $s['id'] = $idCorrente;
    $tipo = (string)($in['tipo'] ?? '');
    if (!isset(SEDI_TIPI[$tipo])) $errori['tipo'] = 'Scegli il tipo di attività dall\'elenco (in fondo c\'è "Altro, lo scrivo io").';
    if ($tipo === SEDI_TIPO_ALTRO && sedi_tipo_testo_pulito((string)($in['tipo_testo'] ?? '')) === '') $errori['tipo_testo'] = 'Hai scelto "Altro": scrivi che attività è, per esempio "Centro di meditazione".';
    $sede = sedi_normalizza($s);
    if (mb_strlen(sedi_testo($in['nome'] ?? '', 200)) < 2) $errori['nome'] = 'Scrivi il nome della sede, per esempio "Sede di Bologna".';
    if ($sede['citta'] === '') $errori['citta'] = 'Scrivi la città: serve a Google e ai titoli.';
    if (trim((string)($in['email'] ?? '')) !== '' && $sede['email'] === '') $errori['email'] = 'L\'email non sembra giusta: controlla che abbia la @ e il punto, per esempio info@esempio.it.';
    $lat = trim((string)($in['lat'] ?? '')); $lng = trim((string)($in['lng'] ?? ''));
    if (($lat !== '' || $lng !== '') && $sede['lat'] === '') {
        $errori['lat'] = 'Le coordinate devono essere due numeri: latitudine fra -90 e 90, longitudine fra -180 e 180 (per esempio 44.4949 e 11.3426). Premi "Cerca sulla mappa" per trovarle dall\'indirizzo.';
    }
    foreach (['google_scheda' => 'Il link alla scheda Google', 'google_recensione' => 'Il link per lasciare una recensione'] as $k => $cosa) {
        if (trim((string)($in[$k] ?? '')) !== '' && $sede[$k] === '') $errori[$k] = $cosa . ' deve cominciare con https:// ed essere di Google: google.com, g.page oppure maps.app.goo.gl. Altri indirizzi non si accettano.';
    }
    if (count(sedi_zona_righe($in['zona'] ?? '')) === SEDI_ZONA_MAX && count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($in['zona'] ?? ''))))) > SEDI_ZONA_MAX) $errori['zona'] = 'La zona servita tiene al massimo ' . SEDI_ZONA_MAX . ' righe: le altre non si salvano.';
    return ['sede' => $sede, 'errori' => $errori];
}

/** Salva una sede (nuova o cambiata): le dà un id se manca, tiene una sola principale. Ritorna la sede salvata. */
function sedi_salva(array $sede): array {
    $tutte = sedi_tutte();
    $sede = sedi_normalizza($sede);
    if ($sede['nome'] === '') throw new RuntimeException('La sede ha bisogno di un nome.');
    if ($sede['id'] === '' || !isset($tutte[$sede['id']])) {
        if (count($tutte) >= SEDI_MAX) throw new RuntimeException('Si possono tenere al massimo ' . SEDI_MAX . ' sedi.');
        if ($sede['id'] === '') $sede['id'] = sedi_id_libero($sede['nome'], $tutte);
        if (!$tutte) $sede['principale'] = true;
        $tutte[$sede['id']] = $sede;
    } else {
        $tutte[$sede['id']] = $sede;
    }
    if ($sede['principale']) foreach ($tutte as $id => $x) if ($id !== $sede['id']) $tutte[$id]['principale'] = false;
    if (!array_filter($tutte, fn($x) => $x['principale'])) $tutte[array_key_first($tutte)]['principale'] = true;
    sedi_salva_elenco($tutte);
    return sedi_leggi($sede['id']) ?? $sede;
}

/** Toglie una sede. La sua pagina resta (lo dice il pannello). */
function sedi_elimina(string $id): bool {
    $tutte = sedi_tutte();
    if (!isset($tutte[$id])) return false;
    $eraPrincipale = $tutte[$id]['principale'];
    unset($tutte[$id]);
    if ($eraPrincipale && $tutte) $tutte[array_key_first($tutte)]['principale'] = true;
    sedi_salva_elenco($tutte);
    return true;
}

function sedi_imposta_principale(string $id): bool {
    $tutte = sedi_tutte();
    if (!isset($tutte[$id])) return false;
    foreach ($tutte as $k => $s) $tutte[$k]['principale'] = $k === $id;
    sedi_salva_elenco($tutte);
    return true;
}

// ------------------------------------------------------------
// Indirizzo, coordinate, link
// ------------------------------------------------------------

function sedi_ha_coordinate(array $s): bool {
    return $s['lat'] !== '' && $s['lng'] !== '';
}

/** "Via Rizzoli 1, 40125 Bologna BO, Italia" (il paese solo se non è l'Italia o se $conPaese). */
function sedi_indirizzo_testo(array $s, bool $conPaese = false): string {
    $citta = trim($s['cap'] . ' ' . $s['citta'] . ($s['provincia'] !== '' ? ' ' . $s['provincia'] : ''));
    $parti = array_values(array_filter([$s['via'], $citta]));
    if ($s['paese'] !== '' && ($conPaese || mb_strtolower($s['paese']) !== 'italia')) $parti[] = $s['paese'];
    return implode(', ', $parti);
}

/** "Via Rizzoli 1, Bologna". */
function sedi_indirizzo_breve(array $s): string {
    return implode(', ', array_values(array_filter([$s['via'], $s['citta']])));
}

/** Il punto per le mappe: "lat,lng" oppure l'indirizzo scritto. */
function sedi_destinazione(array $s): string {
    return sedi_ha_coordinate($s) ? $s['lat'] . ',' . $s['lng'] : sedi_indirizzo_testo($s, true);
}

/** Il link alla mappa (hasMap): dalle coordinate o dall'indirizzo. */
function sedi_link_mappa(array $s): string {
    $d = sedi_destinazione($s);
    return $d === '' ? '' : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($d);
}

/** Il link delle indicazioni stradali ("Come arrivare"). */
function sedi_link_indicazioni(array $s): string {
    $d = sedi_destinazione($s);
    return $d === '' ? '' : 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($d);
}

/** L'indirizzo assoluto di un file del sito (la foto): un percorso dalla radice prende l'origine del sito. */
function sedi_url_assoluto(string $p): string {
    if ($p === '' || preg_match('~^https?://~', $p)) return $p;
    $origine = preg_replace('~^(https?://[^/]+).*$~', '$1', SITO_URL) ?: SITO_URL;
    return str_starts_with($p, '/') ? $origine . $p : SITO_URL . '/' . $p;
}

// ------------------------------------------------------------
// Orari: lo stesso formato e le stesse funzioni del widget Orari di apertura (includes/editor-widget-extra-d.php)
// ------------------------------------------------------------

/** Carica le funzioni del widget Orari (ewx_orari_*), se la pagina non ha ancora caricato i widget. */
function sedi_orari_motore(): bool {
    if (function_exists('ewx_orari_stato')) return true;
    require_once __DIR__ . '/editor-controlli.php';
    require_once __DIR__ . '/editor.php';
    editor_carica_widget_esterni();
    return function_exists('ewx_orari_stato');
}

/** I valori della sede nella forma che il widget Orari si aspetta (lun..dom, chiusure, testi). */
function sedi_orari_valori(array $s, string $aperto = 'Aperto ora', string $chiuso = 'Chiuso ora'): array {
    return $s['orari'] + ['chiusure' => $s['chiusure'], 'testo_aperto' => $aperto, 'testo_chiuso' => $chiuso];
}

/** Gli orari come OpeningHoursSpecification di schema.org (vuoto se nessun giorno ha un orario). */
function sedi_orari_schema(array $s): array {
    if (!sedi_orari_motore()) return [];
    $out = [];
    foreach (EWX_GIORNI as $k => $nome) {
        foreach (ewx_orari_intervalli((string)($s['orari'][$k] ?? '')) as [$da, $a]) {
            $out[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/' . EWX_GIORNI_SCHEMA[$k],
                'opens' => sprintf('%02d:%02d', intdiv($da, 60), $da % 60), 'closes' => $a === 1440 ? '23:59' : sprintf('%02d:%02d', intdiv($a % 1440, 60), $a % 60)];
        }
    }
    return $out;
}

/** La sede ha almeno un giorno con un orario? */
function sedi_ha_orari(array $s): bool {
    foreach (SEDI_GIORNI as $g) if (trim($s['orari'][$g]) !== '') return true;
    return false;
}

/** [aperto, frase] adesso, nel fuso del sito (come il widget Orari). */
function sedi_stato_adesso(array $s): array {
    if (!sedi_ha_orari($s) || !sedi_orari_motore()) return [false, ''];
    return ewx_orari_stato(sedi_orari_valori($s), new DateTimeImmutable('now'));
}

/** Gli orari di oggi in breve: "9:00 - 13:00, 15:00 - 19:00", "24 ore su 24" o "Chiuso". */
function sedi_orari_oggi(array $s): string {
    if (!sedi_orari_motore()) return '';
    $k = SEDI_GIORNI[(int)(new DateTimeImmutable('now'))->format('N') - 1];
    $int = ewx_orari_intervalli((string)($s['orari'][$k] ?? ''));
    return $int === [[0, 1440]] ? '24 ore su 24' : ($int ? implode(', ', array_map(fn($x) => ewx_ora($x[0]) . ' - ' . ewx_ora($x[1]), $int)) : 'Chiuso');
}

// ------------------------------------------------------------
// La pagina della sede
// ------------------------------------------------------------

/** La riga della pagina della sede, se c'è ancora (anche in bozza o nel cestino). */
function sedi_pagina_riga(array $s): ?array {
    if ($s['pagina'] <= 0) return null;
    return contenuti_leggi('pagine', $s['pagina']);
}

/** L'indirizzo assoluto della pagina della sede, solo se la pagina c'è ed è pubblicata. */
function sedi_url_pagina(array $s): string {
    $r = sedi_pagina_riga($s);
    if (!$r || contenuti_stato('pagine', $r) !== 'pubblicato') return '';
    return SITO_URL . contenuti_percorso('pagine', $r);
}

/** La sede di cui questa pagina (id della tabella pages) è la pagina, oppure null. */
function sedi_di_pagina(int $pageId): ?array {
    if ($pageId <= 0) return null;
    foreach (sedi_tutte() as $s) if ($s['pagina'] === $pageId) return $s;
    return null;
}

/**
 * Crea la pagina della sede: una pagina vera (tabella pages, modificabile con l'editor), pubblicata, con l'apertura
 * (nome e città), Dati dell'attività, Mappa, Orari, Testo con la descrizione e Barra contatti, tutti "dalla sede".
 * Ritorna l'id della pagina e lo scrive nella sede.
 */
function sedi_crea_pagina(array $s, string $autore = ''): int {
    $s = sedi_leggi($s['id']) ?? $s;
    if ($s['id'] === '') throw new RuntimeException('Prima salva la sede, poi crea la sua pagina.');
    $riga = sedi_pagina_riga($s);
    if ($riga && empty($riga['cestino'])) return (int)$riga['id'];
    require_once __DIR__ . '/editor-controlli.php';
    require_once __DIR__ . '/editor.php';
    editor_carica_widget_esterni();
    $base = contenuti_slug($s['nome']) ?: 'sede';
    $slug = $base; $n = 2;
    while (!contenuti_slug_libero('pagine', $slug, 0)) $slug = $base . '-' . $n++;
    $id = contenuti_salva('pagine', 0, ['title' => $s['nome'], 'slug' => $slug, 'stato' => 'pubblicato', 'data' => date('Y-m-d'), 'contenuto' => '', 'robots' => 'index',
        'seo_description' => mb_substr($s['descrizione'] !== '' ? $s['descrizione'] : $s['nome'] . ': dove siamo, come raggiungerci e quando siamo aperti.', 0, 160)], $autore);
    $dove = trim($s['citta'] . ($s['provincia'] !== '' ? ' (' . $s['provincia'] . ')' : ''));
    $albero = [
        editor_sezione([editor_widget('hero', ['occhiello' => $dove, 'titolo' => $s['nome'], 'testo' => $s['descrizione'] !== '' ? $s['descrizione'] : 'Dove siamo, come raggiungerci e quando siamo aperti.',
            'pulsanti' => [], 'immagine' => $s['foto'], 'barre' => [], 'margine_sotto' => ['d' => 0]])], ['spazio_sopra' => ['d' => 48], 'spazio_sotto' => ['d' => 24]]),
        ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => ['spazio_colonne' => ['d' => 48]], 'figli' => [
            ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => ['larghezza' => ['d' => 45, 'm' => 100]], 'figli' => [
                editor_widget('dati_attivita', ['sede' => $s['id'], 'titolo' => 'Dove siamo']),
                editor_widget('testo', ['contenuto' => '<p>' . htmlspecialchars($s['descrizione'] !== '' ? $s['descrizione'] : 'Scrivi qui due righe su questa sede: come si arriva, dove si parcheggia, cosa si trova.', ENT_QUOTES, 'UTF-8') . '</p>']),
            ]],
            ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => ['larghezza' => ['d' => 55, 'm' => 100]], 'figli' => [
                editor_widget('mappa', ['sede' => $s['id'], 'al_clic' => true, 'come_arrivare' => 'sotto', 'margine_sotto' => ['d' => 0]]),
            ]],
        ]],
        editor_sezione([editor_widget('orari', ['sede' => $s['id'], 'titolo' => 'Orari di apertura', 'margine_sotto' => ['d' => 0]])], ['larghezza' => 'stretta']),
        editor_sezione([editor_widget('barra_contatti', ['sede' => $s['id'], 'margine_sotto' => ['d' => 0]])], ['spazio_sopra' => ['d' => 0], 'spazio_sotto' => ['d' => 0]]),
    ];
    editor_salva($id, editor_valida(['elementi' => $albero]), true, $autore, 'Pagina della sede', 'pagine');
    $s['pagina'] = $id;
    sedi_salva($s);
    return $id;
}

// ------------------------------------------------------------
// JSON-LD
// ------------------------------------------------------------

function sedi_jsonld_id(array $s): string {
    return SITO_URL . '/#sede-' . $s['id'];
}

/** La zona servita come nodi City o AdministrativeArea (province, regioni, sigle). */
function sedi_area_servita_di(array $s): array {
    $out = [];
    foreach ($s['zona'] as $z) {
        $amm = (bool)preg_match('/^(provincia|prov\.|regione|area metropolitana|citt\x{e0} metropolitana|comuni di)\b/iu', $z) || (bool)preg_match('/^[A-Z]{2}$/', $z);
        $out[] = ['@type' => $amm ? 'AdministrativeArea' : 'City', 'name' => $z];
    }
    return $out;
}

/** L'unione delle zone servite di tutte le sedi (per i servizi), senza doppioni. */
function sedi_area_servita(): array {
    $out = []; $visti = [];
    foreach (sedi_tutte() as $s) foreach (sedi_area_servita_di($s) as $a) {
        $k = mb_strtolower($a['name']);
        if (isset($visti[$k])) continue;
        $visti[$k] = true; $out[] = $a;
    }
    return $out;
}

/** Il nodo LocalBusiness (o sottotipo) di una sede. Tutto testo: niente HTML dentro, l'escape lo fa json_encode con JSON_HEX_TAG. */
function sedi_jsonld(array $s): array {
    $n = ['@type' => sedi_tipo_schema($s['tipo']), '@id' => sedi_jsonld_id($s), 'name' => $s['nome']];
    $tipoNome = sedi_tipo_nome($s['tipo'], $s['tipo_testo']);
    $descr = $s['descrizione'] !== '' ? $s['descrizione'] : ($s['tipo'] === SEDI_TIPO_ALTRO ? $tipoNome : '');
    if ($descr !== '') $n['description'] = $descr;
    $url = sedi_url_pagina($s);
    if ($url !== '') $n['url'] = $url;
    if ($s['foto'] !== '') $n['image'] = sedi_url_assoluto($s['foto']);
    if ($s['telefono'] !== '') $n['telephone'] = $s['telefono'];
    if ($s['email'] !== '') $n['email'] = $s['email'];
    $paese = $s['paese'] === '' || mb_strtolower($s['paese']) === 'italia' ? 'IT' : $s['paese'];
    $n['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $s['via'], 'postalCode' => $s['cap'], 'addressLocality' => $s['citta'], 'addressRegion' => $s['provincia'], 'addressCountry' => $paese], fn($x) => $x !== '');
    if (sedi_ha_coordinate($s)) $n['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float)$s['lat'], 'longitude' => (float)$s['lng']];
    $mappa = sedi_link_mappa($s);
    if ($mappa !== '') $n['hasMap'] = $mappa;
    $orari = sedi_orari_schema($s);
    if ($orari) $n['openingHoursSpecification'] = $orari;
    if ($s['prezzo'] !== '') $n['priceRange'] = $s['prezzo'];
    $area = sedi_area_servita_di($s);
    if ($area) $n['areaServed'] = $area;
    $same = [];
    if ($s['google_scheda'] !== '') $same[] = $s['google_scheda'];
    foreach (seo_righe((string)(seo_cfg()['org']['sameas'] ?? '')) as $u) if (!in_array($u, $same, true)) $same[] = $u;
    if ($same) $n['sameAs'] = $same;
    $n['parentOrganization'] = ['@id' => seo_jsonld_id('servizio')];
    return $n;
}

/** I nodi di tutte le sedi (location dell'Organizzazione). Vuoto senza sedi: il JSON-LD resta quello di prima. */
function sedi_jsonld_elenco(): array {
    return array_values(array_map('sedi_jsonld', sedi_tutte()));
}

/** Il nodo in più della WebPage di una pagina della sede: la sua LocalBusiness come mainEntity. Vuoto per le altre pagine. */
function sedi_jsonld_pagina_extra(int $pageId): array {
    $s = sedi_di_pagina($pageId);
    return $s ? ['mainEntity' => ['@id' => sedi_jsonld_id($s)]] : [];
}

/** La città della sede principale, per %citta% nei titoli ('' senza sedi). */
function sedi_citta_titolo(): string {
    $p = sedi_principale();
    return $p ? $p['citta'] : '';
}

// ------------------------------------------------------------
// Per i widget
// ------------------------------------------------------------

/** Le voci della tendina "Dati dalla sede": '' (i campi del widget), 'principale', poi ogni sede. */
function sedi_opzioni_widget(bool $conNessuna = true): array {
    $out = $conNessuna ? ['' => 'Nessuna: uso i campi qui sotto'] : [];
    $out['principale'] = 'La sede principale';
    foreach (sedi_tutte() as $id => $s) $out[$id] = $s['nome'];
    return $out;
}

/** La sede scelta in un widget: null con '' o con un id che non c'è (il widget usa i suoi campi). */
function sedi_per_widget($scelta): ?array {
    $scelta = is_scalar($scelta) ? (string)$scelta : '';
    if ($scelta === '') return null;
    if ($scelta === 'principale') return sedi_principale();
    return sedi_leggi($scelta);
}

// ------------------------------------------------------------
// Coordinate dall'indirizzo (Nominatim di OpenStreetMap, dal server)
// ------------------------------------------------------------

/**
 * Cerca le coordinate di un indirizzo. Ritorna ['ok' => bool, 'lat', 'lng', 'nome' (l'indirizzo trovato), 'errore'].
 * L'indirizzo del servizio: SEDI_NOMINATIM_URL, oppure CMS_NOMINATIM_URL nel config. Un servizio su un indirizzo
 * interno o privato (127.0.0.1, 10.x, localhost...) si rifiuta, come per i webhook; solo con CMS_NOMINATIM_PROVA true
 * nel config (le prove, con prove/nominatim-finto.php) passa. $url e $prova servono alle prove da riga di comando.
 */
function sedi_geocodifica(string $indirizzo, ?string $url = null, ?bool $prova = null): array {
    $no = fn(string $e) => ['ok' => false, 'lat' => '', 'lng' => '', 'nome' => '', 'errore' => $e];
    $indirizzo = sedi_testo($indirizzo, 300);
    if (mb_strlen($indirizzo) < 4) return $no('Scrivi prima l\'indirizzo: via, numero e città.');
    $url = $url ?? (defined('CMS_NOMINATIM_URL') ? (string)CMS_NOMINATIM_URL : SEDI_NOMINATIM_URL);
    $prova = $prova ?? (defined('CMS_NOMINATIM_PROVA') && CMS_NOMINATIM_PROVA === true);
    $p = parse_url($url);
    if (!is_array($p) || empty($p['host']) || !in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) return $no('L\'indirizzo del servizio delle mappe non è valido.');
    $host = strtolower($p['host']);
    $completo = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['q' => $indirizzo, 'format' => 'jsonv2', 'limit' => 1, 'addressdetails' => 0, 'accept-language' => 'it']);
    $agente = 'plumeCMS/' . cms_versione() . ' (' . SITO_URL . ')';
    if (!$prova) {
        // Come i webhook: niente indirizzi interni o privati, host risolto e fissato, solo http e https.
        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) return $no('Il servizio delle mappe non può stare su un indirizzo interno del server (' . $host . ').');
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) && sedi_ip_interno(trim($host, '[]'))) return $no('Il servizio delle mappe non può stare su un indirizzo interno o privato (' . $host . ').');
        if (strtolower((string)$p['scheme']) !== 'https') return $no('Il servizio delle mappe deve rispondere in https.');
        $r = seo_http($completo, 'GET', null, ['Accept: application/json'], 10, $agente, [$host]);
        if ($r['errore'] !== '' && str_contains($r['errore'], 'privato')) return $no('Il servizio delle mappe non può stare su un indirizzo interno o privato (' . $host . ').');
    } else {
        $r = sedi_http_prova($completo, $agente);
    }
    if ($r['errore'] !== '') return $no('Le coordinate non si trovano adesso: ' . $r['errore'] . ' Puoi scriverle a mano.');
    if ($r['codice'] !== 200) return $no('Il servizio delle mappe ha risposto con il codice ' . $r['codice'] . '. Riprova più tardi o scrivi le coordinate a mano.');
    $j = json_decode($r['corpo'], true);
    if (!is_array($j)) return $no('Il servizio delle mappe ha risposto in un formato che non si legge. Scrivi le coordinate a mano.');
    $primo = isset($j[0]) && is_array($j[0]) ? $j[0] : (isset($j['lat']) ? $j : null);
    if (!$primo) return $no('Nessun posto trovato con questo indirizzo. Prova con via, numero civico e città, oppure scrivi le coordinate a mano.');
    $lat = sedi_coordinata($primo['lat'] ?? '', 90); $lng = sedi_coordinata($primo['lon'] ?? ($primo['lng'] ?? ''), 180);
    if ($lat === '' || $lng === '') return $no('Il servizio delle mappe ha risposto senza coordinate leggibili. Scrivi le coordinate a mano.');
    return ['ok' => true, 'lat' => $lat, 'lng' => $lng, 'nome' => sedi_testo($primo['display_name'] ?? '', 200), 'errore' => ''];
}

/** Un IP interno, privato, locale o riservato (come nl_aut_ip_interno() dei webhook): anche 100.64/10, 0/8, 192.0.0/24, 198.18/15 e gli IPv6 che li avvolgono. */
function sedi_ip_interno(string $ip): bool {
    require_once __DIR__ . '/sicurezza-html.php';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $b = (string)inet_pton($ip);
        if (strlen($b) === 16 && (str_starts_with($b, str_repeat("\0", 10) . "\xff\xff") || str_starts_with($b, str_repeat("\0", 12)))) return true;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP) || sicurezza_ip_privato($ip)) return true;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['0.0.0.0', 8], ['192.0.0.0', 24], ['198.18.0.0', 15]] as [$rete, $bit]) {
            if (($n & (-1 << (32 - $bit))) === (ip2long($rete) & (-1 << (32 - $bit)))) return true;
        }
    }
    return false;
}

/** La chiamata al servizio finto delle prove (CMS_NOMINATIM_PROVA): senza il controllo degli indirizzi interni. */
function sedi_http_prova(string $url, string $agente): array {
    $out = ['codice' => 0, 'corpo' => '', 'errore' => ''];
    if (!function_exists('curl_init')) { $out['errore'] = 'manca curl.'; return $out; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => $agente, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_MAXFILESIZE => 1000000, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $r = curl_exec($ch);
    if ($r === false) $out['errore'] = seo_errore_rete(curl_error($ch));
    else { $out['codice'] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $out['corpo'] = mb_substr((string)$r, 0, 200000); }
    curl_close($ch);
    return $out;
}

// ------------------------------------------------------------
// Il sito su misura
// ------------------------------------------------------------

/**
 * La città del sito su misura diventa la sede principale ("Sede di <città>", tipo dall'attività, coordinate vuote).
 * Si chiama ogni volta che il kit su misura si compone: la sede nata da qui si aggiorna, le altre non si toccano.
 */
function sedi_da_su_misura(array $r): ?array {
    $citta = sedi_testo($r['citta'] ?? '', 80);
    if ($citta === '') return null;
    $tutte = sedi_tutte();
    $id = 'su-misura';
    $s = $tutte[$id] ?? sedi_vuota();
    $s['id'] = $id;
    $s['nome'] = 'Sede di ' . $citta;
    $s['citta'] = $citta;
    $s['tipo'] = sedi_tipo_da_su_misura((string)($r['attivita'] ?? ''));
    if ($s['tipo'] === SEDI_TIPO_ALTRO && $s['tipo_testo'] === '') $s['tipo_testo'] = 'Attività';
    if (!$tutte) $s['principale'] = true;
    return sedi_salva($s);
}
