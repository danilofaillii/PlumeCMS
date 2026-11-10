<?php
/**
 * 2.38.0: i dati delle immagini della libreria dei Media. Oltre all'alt (2.29.0, includes/media-alt.php) ogni immagine può
 * avere titolo, didascalia, descrizione, autore (persona o organizzazione), licenza, pagina della licenza e indirizzo della
 * fonte. Stanno nella stessa tabella <prefisso>media_alt (una riga per percorso, colonne aggiunte al primo uso da
 * media_alt_schema()): la libreria è fatta di file, non di righe con un id, e la chiave di un'immagine è il suo percorso
 * (media_alt_chiave()). La tabella <prefisso>media dello schema di base non la scrive nessuno: resta com'è.
 *
 * Dove servono:
 *  - la scheda dell'immagine in Media (admin/includes/media-scheda.php) e Media > Dati delle immagini
 *    (admin/includes/media-alt-pagina.php), anche per il redattore;
 *  - sul sito: la didascalia della libreria nei widget Immagine e Galleria con l'interruttore acceso, e il nodo ImageObject
 *    del JSON-LD per l'immagine principale di pagine e articoli (media_dati_jsonld()). Senza dati (impostazione media_dati_n a 0)
 *    nessuna query e niente cambia;
 *  - l'addon AI collegate: list_media e propose_media_data, import_photo che scrive i crediti.
 * Testo semplice ovunque (niente HTML), indirizzi solo http o https.
 */
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/media-alt.php';

/** I campi di testo e la loro lunghezza massima. */
const MEDIA_DATI_TESTI = ['titolo' => 200, 'didascalia' => 500, 'descrizione' => 2000, 'autore' => 200, 'licenza_nome' => 100];
const MEDIA_DATI_URL = ['licenza_url', 'fonte_url'];
const MEDIA_DATI_PESANTE = 512000; // oltre 500 KB: "pesante" in Dati delle immagini
const MEDIA_DATI_ALT_CONSIGLIATO = [5, 125];

/** Le licenze della tendina: codice => [etichetta, pagina della licenza di partenza ('' = nessuna)]. */
function media_dati_licenze(): array {
    return [
        '' => ['Non indicata', ''],
        'riservati' => ['Tutti i diritti riservati', ''],
        'cc0' => ['CC0: nessun obbligo (pubblico dominio)', 'https://creativecommons.org/publicdomain/zero/1.0/'],
        'pd' => ['Pubblico dominio', 'https://creativecommons.org/publicdomain/mark/1.0/'],
        'cc-by' => ['CC BY: si cita l\'autore', 'https://creativecommons.org/licenses/by/4.0/'],
        'cc-by-sa' => ['CC BY-SA: si cita l\'autore, stessa licenza', 'https://creativecommons.org/licenses/by-sa/4.0/'],
        'archivio' => ['Licenza dell\'archivio da cui viene', ''],
        'altro' => ['Altra licenza', ''],
    ];
}

/** Un testo pulito: senza tag né caratteri di controllo né caratteri a 4 byte, una riga sola (la descrizione tiene gli a capo). */
function media_dati_testo($v, int $max, bool $righe = false): string {
    $s = is_scalar($v) ? mb_scrub((string)$v, 'UTF-8') : '';
    $via = fn(string $x) => (string)preg_replace('~<(script|style)\b[\s\S]*?(</\1\s*>|$)~i', ' ', $x);
    $s = strip_tags($via(html_entity_decode(strip_tags($via($s)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $s = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
    $s = (string)preg_replace($righe ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]+/', $righe ? '' : ' ', $s);
    $s = $righe ? (string)preg_replace("/\r\n?/", "\n", $s) : (string)preg_replace('/\s+/u', ' ', $s);
    if ($righe) $s = (string)preg_replace("/\n{3,}/", "\n\n", $s);
    return mb_substr(trim($s), 0, $max);
}

/** Un indirizzo: '' se vuoto, null se non valido (solo http:// o https:// con un host, niente spazi né virgolette). */
function media_dati_url($v): ?string {
    $u = is_scalar($v) ? trim((string)$v) : '';
    if ($u === '') return '';
    if (strlen($u) > 500 || preg_match('/[\s"\'<>\\\\\x00-\x1F]/', $u) || !preg_match('~^https?://[^/?#]+~i', $u)) return null;
    $h = parse_url($u, PHP_URL_HOST);
    if (!is_string($h) || $h === '' || parse_url($u, PHP_URL_USER) !== null) return null;
    return $u;
}

/** I dati vuoti di un'immagine. */
function media_dati_vuoti(): array {
    return ['alt' => '', 'titolo' => '', 'didascalia' => '', 'descrizione' => '', 'autore' => '', 'autore_tipo' => '', 'licenza' => '', 'licenza_nome' => '', 'licenza_url' => '', 'fonte_url' => '', 'origine' => '', 'aggiornato' => null];
}

/** Tutti i dati salvati (oltre all'alt): chiave => riga. Una volta per richiesta; nessuna query se media_dati_n è 0. */
function media_dati_tutti(bool $forza = false): array {
    static $tutti = null;
    if ($tutti !== null && !$forza) return $tutti;
    $tutti = [];
    if (!$forza && (int)get_setting('media_dati_n', '0') <= 0) return $tutti;
    try {
        media_alt_schema();
        $vuote = [];
        foreach (array_keys(MEDIA_DATI_TESTI) as $c) $vuote[] = "`$c` <> ''";
        foreach (array_merge(MEDIA_DATI_URL, ['licenza', 'origine']) as $c) $vuote[] = "`$c` <> ''";
        foreach (db()->query('SELECT * FROM ' . tbl('media_alt') . ' WHERE ' . implode(' OR ', $vuote))->fetchAll() as $r) $tutti[(string)$r['percorso']] = array_intersect_key($r, media_dati_vuoti()) + media_dati_vuoti();
    } catch (Throwable $e) { error_log('media_dati_tutti: ' . $e->getMessage()); }
    return $tutti;
}

/** I dati di un'immagine (alt compreso) letti dal database: sempre tutte le chiavi. */
function media_dati_di(string $url, bool $dalDatabase = false): array {
    $k = media_alt_chiave($url);
    if ($k === '') return media_dati_vuoti();
    if (!$dalDatabase) {
        $d = media_dati_tutti()[$k] ?? media_dati_vuoti();
        if ($d['alt'] === '') $d['alt'] = media_alt_di($url);
        return $d;
    }
    try {
        media_alt_schema();
        $st = db()->prepare('SELECT * FROM ' . tbl('media_alt') . ' WHERE percorso = ?');
        $st->execute([$k]);
        $r = $st->fetch();
    } catch (Throwable $e) { error_log('media_dati_di: ' . $e->getMessage()); $r = null; }
    return $r ? array_intersect_key($r, media_dati_vuoti()) + media_dati_vuoti() : media_dati_vuoti();
}

/** L'immagine della libreria con quella chiave (voce di media_elenco()), oppure null. */
function media_dati_voce(string $url): ?array {
    $k = media_alt_chiave($url);
    if ($k === '') return null;
    foreach (media_elenco() as $m) if (media_alt_chiave($m['url']) === $k) return $m + ['chiave' => $k];
    return null;
}

/** Il file sul disco di un'immagine della libreria ('' se non è un file del sito). */
function media_dati_file(string $url): string {
    $k = media_alt_chiave($url);
    if ($k === '' || str_contains($k, '..') || preg_match('~^https?://~', $k)) return '';
    $up = media_alt_chiave(cms_uploads_url() . '/x');
    $up = substr($up, 0, -2);
    if ($up !== '' && str_starts_with($k, $up . '/')) { $p = cms_uploads_dir() . substr($k, strlen($up)); return is_file($p) ? $p : ''; }
    if (str_starts_with($k, '/assets/img/')) { $p = cms_radice() . $k; return is_file($p) ? $p : ''; }
    return '';
}

/** Dimensioni, peso, formato e data del file. */
function media_dati_file_info(string $url): array {
    $p = media_dati_file($url);
    $out = ['larghezza' => 0, 'altezza' => 0, 'peso' => 0, 'formato' => strtoupper((string)pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)), 'data' => 0];
    if ($p === '') return $out;
    $out['peso'] = (int)filesize($p);
    $out['data'] = (int)filemtime($p);
    $i = @getimagesize($p);
    if ($i) { $out['larghezza'] = (int)$i[0]; $out['altezza'] = (int)$i[1]; }
    elseif (strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'svg') {
        $t = (string)@file_get_contents($p, false, null, 0, 4000);
        if (preg_match('/viewBox\s*=\s*["\'][\d.\s-]+?\s([\d.]+)\s+([\d.]+)["\']/i', $t, $m)) { $out['larghezza'] = (int)$m[1]; $out['altezza'] = (int)$m[2]; }
    }
    if ($out['formato'] === 'JPEG') $out['formato'] = 'JPG';
    return $out;
}

/**
 * Pulisce e controlla i dati arrivati da un modulo o da una proposta. Ritorna ['dati' => [...], 'errori' => [campo => messaggio]].
 * $soloQuesti: i campi da toccare (gli altri restano quelli di $prima).
 */
function media_dati_pulisci(array $in, array $prima, ?array $soloQuesti = null): array {
    $d = $prima; $errori = [];
    $tocca = fn(string $c) => $soloQuesti === null ? array_key_exists($c, $in) : in_array($c, $soloQuesti, true);
    if ($tocca('alt')) $d['alt'] = media_alt_pulito($in['alt'] ?? '');
    foreach (MEDIA_DATI_TESTI as $c => $max) if ($tocca($c)) $d[$c] = media_dati_testo($in[$c] ?? '', $max, $c === 'descrizione');
    foreach (MEDIA_DATI_URL as $c) if ($tocca($c)) {
        $u = media_dati_url($in[$c] ?? '');
        if ($u === null) { $errori[$c] = 'Scrivi un indirizzo intero che comincia con https:// (per esempio https://www.archivio.it/foto/123), oppure lascia vuoto.'; $d[$c] = $prima[$c]; }
        else $d[$c] = $u;
    }
    if ($tocca('licenza')) {
        $l = is_string($in['licenza'] ?? null) ? $in['licenza'] : '';
        if (!array_key_exists($l, media_dati_licenze())) { $errori['licenza'] = 'Scegli una licenza dall\'elenco.'; $l = $prima['licenza']; }
        $d['licenza'] = $l;
    }
    if ($tocca('autore_tipo')) $d['autore_tipo'] = in_array($in['autore_tipo'] ?? '', ['persona', 'organizzazione'], true) ? (string)$in['autore_tipo'] : '';
    if (!in_array($d['licenza'], ['archivio', 'altro'], true)) $d['licenza_nome'] = '';
    return ['dati' => $d, 'errori' => $errori];
}

/**
 * Salva i dati di un'immagine della libreria. $in: i campi da cambiare (quelli assenti restano come sono, salvo $soloQuesti).
 * Ritorna ['ok' => true, 'dati' => ..., 'percorso' => chiave] oppure ['errore' => ..., 'errori' => [campo => messaggio]].
 */
function media_dati_salva(string $url, array $in, ?array $soloQuesti = null, string $origine = ''): array {
    $voce = media_dati_voce($url);
    if (!$voce && ($f = media_dati_file($url)) !== '' && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), MEDIA_ESTENSIONI, true) && !foto_e_webp_compagna(basename($f)) && !media_e_miniatura(basename($f))) {
        // Un file appena caricato in questa stessa richiesta (media_elenco() è già stato letto): c'è sul disco, nella cartella delle immagini.
        $voce = ['chiave' => media_alt_chiave($url), 'url' => $url];
    }
    if (!$voce) return ['errore' => 'Questa immagine non sta nella libreria dei Media.', 'errori' => []];
    $k = $voce['chiave'];
    $prima = media_dati_di($url, true);
    $p = media_dati_pulisci($in, $prima, $soloQuesti);
    if ($p['errori']) return ['errore' => count($p['errori']) === 1 ? 'Un campo da sistemare: lo trovi segnato in rosso.' : count($p['errori']) . ' campi da sistemare: li trovi segnati in rosso.', 'errori' => $p['errori']];
    $d = $p['dati'];
    if ($origine !== '') $d['origine'] = preg_replace('/[^a-z0-9-]/', '', strtolower($origine)) ?: '';
    media_alt_schema();
    $colonne = array_merge(['alt'], array_keys(MEDIA_ALT_COLONNE_DATI));
    $valori = array_map(fn($c) => mb_substr((string)($d[$c] ?? ''), 0, $c === 'descrizione' ? 2000 : 500), $colonne);
    db()->prepare('INSERT INTO ' . tbl('media_alt') . ' (percorso, ' . implode(', ', array_map(fn($c) => "`$c`", $colonne)) . ', aggiornato) VALUES (?' . str_repeat(', ?', count($colonne)) . ', NOW()) ON DUPLICATE KEY UPDATE '
        . implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", $colonne)) . ', aggiornato = NOW()')->execute(array_merge([$k], $valori));
    media_alt_togli_se_vuota($k);
    media_dati_conta();
    return ['ok' => true, 'dati' => media_dati_di($url, true), 'percorso' => $k];
}

/** Ricalcola i due contatori che tengono il sito senza query quando non ci sono dati (media_alt_n, media_dati_n). */
function media_dati_conta(): void {
    media_alt_schema();
    save_setting('media_alt_n', (string)(int)db()->query('SELECT COUNT(*) FROM ' . tbl('media_alt') . " WHERE alt <> ''")->fetchColumn());
    $vuote = [];
    foreach (array_keys(MEDIA_DATI_TESTI) as $c) $vuote[] = "`$c` <> ''";
    foreach (array_merge(MEDIA_DATI_URL, ['licenza', 'origine']) as $c) $vuote[] = "`$c` <> ''";
    save_setting('media_dati_n', (string)(int)db()->query('SELECT COUNT(*) FROM ' . tbl('media_alt') . ' WHERE ' . implode(' OR ', $vuote))->fetchColumn());
    media_alt_tutti(true);
    media_dati_tutti(true);
}

/** Ha crediti? (autore o licenza scritti) */
function media_dati_ha_crediti(array $d): bool {
    return trim($d['autore']) !== '' || $d['licenza'] !== '';
}

/** L'immagine viene da un archivio esterno (importata dall'AI collegata)? */
function media_dati_importata(array $d): bool {
    return $d['origine'] !== '';
}

// ------------------------------------------------------------
// Dove è usata
// ------------------------------------------------------------

/**
 * Dove compare un'immagine: pagine, articoli e altri contenuti, modelli (header, footer, riquadri), impostazioni, newsletter,
 * form e popup. Si cerca il nome del file (unico: ha un suffisso casuale) nelle colonne di testo; "certo" quando compare il
 * percorso nella versione pubblicata, altrimenti "forse" (solo nella bozza, solo il nome del file, o una tabella dove non si
 * sa in che modo). Ogni voce: dove (testo), titolo, link (nel pannello, '' se non c'è), certo (bool), nota.
 */
function media_dati_usata(string $url): array {
    require_once __DIR__ . '/contenuti.php';
    $k = media_alt_chiave($url);
    $nome = basename((string)parse_url($k, PHP_URL_PATH));
    if ($k === '' || $nome === '' || strlen($nome) < 5) return [];
    $simile = '%' . strtr($nome, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    $contiene = function (string $testo) use ($k, $url, $nome): int {
        if ($testo === '') return 0;
        $t = str_replace('\\/', '/', $testo);
        if (str_contains($t, $k) || ($url !== $k && str_contains($t, $url))) return 2;
        return str_contains($t, $nome) ? 1 : 0;
    };
    $out = [];
    // I contenuti
    foreach (contenuti_tipi() as $tipo => $t) {
        try {
            $st = db()->prepare('SELECT * FROM ' . tbl($t['tabella']) . ' WHERE CONCAT_WS(" ", ' . media_dati_colonne_testo($t['tabella']) . ') LIKE ?');
            $st->execute([$simile]);
            $righe = $st->fetchAll();
        } catch (Throwable $e) { error_log('media_dati_usata ' . $tipo . ': ' . $e->getMessage()); continue; }
        foreach ($righe as $r) {
            $pubblicato = 0;
            foreach (['struttura', $t['campo_contenuto'], 'immagine_url', 'featured_image', 'og_image'] as $c) if (isset($r[$c])) $pubblicato = max($pubblicato, $contiene((string)$r[$c]));
            $bozza = $contiene((string)($r['struttura_bozza'] ?? ''));
            $stato = function_exists('contenuti_stato') ? contenuti_stato($tipo, $r) : '';
            $nota = $pubblicato === 2 ? '' : ($pubblicato === 1 ? 'compare il nome del file' : ($bozza ? 'solo nella bozza dell\'editor' : 'compare in un altro campo'));
            if (!empty($r['cestino'])) $nota = trim($nota . ($nota !== '' ? ', ' : '') . 'nel cestino');
            $out[] = ['dove' => (string)$t['Uno'] . ($stato !== '' && $stato !== 'pubblicato' ? ' (' . (CONTENUTI_STATI[$stato] ?? $stato) . ')' : ''), 'titolo' => (string)($r['title'] ?? ''),
                'link' => trim((string)($r['struttura'] ?? '')) !== '' || trim((string)($r['struttura_bozza'] ?? '')) !== '' ? 'editor.php?tipo=' . $tipo . '&id=' . (int)$r['id'] : 'contenuto.php?tipo=' . $tipo . '&id=' . (int)$r['id'],
                'certo' => $pubblicato === 2 && empty($r['cestino']), 'nota' => $nota];
        }
    }
    // Le altre tabelle dove una foto può stare: modelli, impostazioni, newsletter, form, popup.
    $altre = ['modelli' => 'Modello', 'settings' => 'Impostazioni del sito', 'seo_config' => 'Impostazioni SEO', 'newsletter_campagne' => 'Newsletter (campagna)', 'newsletter_modelli' => 'Newsletter (modello)',
        'newsletter_sezioni' => 'Newsletter (sezione salvata)', 'newsletter_flussi' => 'Automazione', 'moduli' => 'Form', 'popup' => 'Popup', 'menu_nav' => 'Menu'];
    foreach ($altre as $tab => $et) {
        $cols = media_dati_colonne_testo($tab);
        if ($cols === '') continue;
        try {
            $st = db()->prepare('SELECT * FROM ' . tbl($tab) . ' WHERE CONCAT_WS(" ", ' . $cols . ') LIKE ? LIMIT 50');
            $st->execute([$simile]);
            foreach ($st->fetchAll() as $r) {
                $testo = implode(' ', array_map('strval', array_filter($r, 'is_scalar')));
                $c = $contiene($testo);
                $titolo = (string)($r['nome'] ?? $r['name'] ?? $r['titolo'] ?? $r['oggetto'] ?? $r['setting_key'] ?? $r['chiave'] ?? $r['key'] ?? '');
                $link = match ($tab) { 'modelli' => 'editor.php?modello=' . (int)($r['id'] ?? 0), 'moduli' => 'form.php', 'popup' => 'popup.php', 'menu_nav' => 'menu.php', default => '' };
                $out[] = ['dove' => $tab === 'modelli' ? 'Modello (' . (string)($r['tipo'] ?? '') . ')' : $et, 'titolo' => $titolo, 'link' => $link,
                    'certo' => $c === 2 && $tab === 'modelli' && ($r['status'] ?? '') === 'published', 'nota' => $c === 2 ? ($tab === 'modelli' && ($r['status'] ?? '') !== 'published' ? 'modello non pubblicato' : '') : 'compare il nome del file'];
            }
        } catch (Throwable $e) { error_log('media_dati_usata ' . $tab . ': ' . $e->getMessage()); }
    }
    return $out;
}

/** Le colonne di testo di una tabella, per CONCAT_WS ('' se la tabella non c'è). */
function media_dati_colonne_testo(string $tab): string {
    static $cache = [];
    if (isset($cache[$tab])) return $cache[$tab];
    try {
        $st = db()->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE IN ('char','varchar','text','mediumtext','longtext','json')");
        $st->execute([trim(tbl($tab), '`')]);
        $c = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { $c = []; }
    return $cache[$tab] = implode(', ', array_map(fn($x) => '`' . str_replace('`', '', (string)$x) . '`', $c));
}

// ------------------------------------------------------------
// Nome del file leggibile e rinomina
// ------------------------------------------------------------

/** Un nome di file da macchina fotografica o telefono (IMG_20261108_123456, DSC0001, PXL_..., 20261108_123456, WhatsApp Image...). */
function media_dati_nome_da_fotocamera(string $nome): bool {
    $b = pathinfo($nome, PATHINFO_FILENAME);
    return (bool)preg_match('/^(img|dsc|dscn|dscf|dji|gopr|pxl|mvimg|p|sam|photo|foto|image|immagine|screenshot|schermata|whatsapp[ _-]image)?[ _-]?[\d _.-]{4,}(?:[ _-]?\w{0,8})?$/i', $b)
        || (bool)preg_match('/^(img|dsc|dscn|dscf|pxl|mvimg|whatsapp image|screenshot)[ _-]/i', $b);
}

/** Lo slug di un titolo per il nome di un file (minuscole, trattini, al massimo 60 caratteri). */
function media_dati_slug(string $t): string {
    $t = function_exists('cms_ascii') ? cms_ascii($t) : (function_exists('iconv') ? (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) : $t);
    return trim(substr((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), 0, 60), '-');
}

/**
 * Rinomina il file di un'immagine che nessuno usa (nemmeno "forse"): il nome nuovo viene dal titolo, con il suffisso casuale.
 * Solo nelle cartelle caricate dal pannello (non nei segnaposto né nelle copertine del pannello). Sposta anche la WebP accanto
 * e i dati della libreria. Ritorna ['ok' => true, 'url' => nuovo] oppure ['errore' => ...].
 */
function media_dati_rinomina(string $url, string $titolo): array {
    $voce = media_dati_voce($url);
    if (!$voce) return ['errore' => 'Questa immagine non sta nella libreria dei Media.'];
    if (!in_array($voce['cartella'], ['uploads', 'pagine', 'contenuti'], true)) return ['errore' => 'Le immagini di questa cartella non si rinominano (le usano le email già spedite o il sito su misura).'];
    if (media_dati_usata($voce['url'])) return ['errore' => 'La foto è usata (o forse usata) da qualche parte: rinominarla romperebbe quei collegamenti. Si rinomina solo una foto che nessuno usa.'];
    $p = media_dati_file($voce['url']);
    if ($p === '') return ['errore' => 'Il file non si trova.'];
    $slug = media_dati_slug($titolo);
    if ($slug === '') return ['errore' => 'Scrivi prima il titolo della foto: il nome nuovo del file viene da lì.'];
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    $dir = dirname($p);
    do { $nuovo = $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext; } while (file_exists("$dir/$nuovo"));
    if (!@rename($p, "$dir/$nuovo")) return ['errore' => 'Il file non si è rinominato (permessi della cartella).'];
    if (is_file($p . '.webp')) @rename($p . '.webp', "$dir/$nuovo.webp");
    $urlNuovo = substr($voce['url'], 0, -strlen(basename($p))) . $nuovo;
    $kNuova = media_alt_chiave($urlNuovo);
    try {
        media_alt_schema();
        db()->prepare('UPDATE ' . tbl('media_alt') . ' SET percorso = ? WHERE percorso = ?')->execute([$kNuova, $voce['chiave']]);
    } catch (Throwable $e) { error_log('media_dati_rinomina: ' . $e->getMessage()); }
    media_dati_conta();
    return ['ok' => true, 'url' => $urlNuovo];
}

// ------------------------------------------------------------
// Elenchi per Media > Dati delle immagini e per l'AI
// ------------------------------------------------------------

/** I filtri di Dati delle immagini: codice => etichetta. */
function media_dati_filtri(): array {
    return ['alt' => 'Senza alt', 'descrizione' => 'Senza descrizione', 'crediti' => 'Importate senza crediti', 'pesanti' => 'Pesanti (oltre 500 KB)', 'tutte' => 'Tutte'];
}

/** Le immagini della libreria (senza miniature) con i loro dati e cosa manca, filtrate. */
function media_dati_elenco(string $filtro = 'tutte'): array {
    $alt = media_alt_tutti(true);
    $dati = media_dati_tutti(true);
    $out = [];
    foreach (media_elenco() as $m) {
        if ($m['miniatura']) continue;
        $k = media_alt_chiave($m['url']);
        if ($k === '') continue;
        $d = $dati[$k] ?? media_dati_vuoti();
        if ($d['alt'] === '') $d['alt'] = $alt[$k] ?? '';
        $manca = [];
        if ($d['alt'] === '') $manca[] = 'alt';
        if ($d['descrizione'] === '') $manca[] = 'descrizione';
        if (media_dati_importata($d) && !media_dati_ha_crediti($d)) $manca[] = 'crediti';
        if ($m['peso'] > MEDIA_DATI_PESANTE && !$m['svg']) $manca[] = 'pesanti';
        if ($filtro !== 'tutte' && !in_array($filtro, $manca, true)) continue;
        $out[] = $m + ['chiave' => $k, 'dati' => $d, 'manca' => $manca];
    }
    return $out;
}

// ------------------------------------------------------------
// Sul sito
// ------------------------------------------------------------

/** La didascalia della libreria di un'immagine ('' se non c'è). */
function media_dati_didascalia(string $url): string {
    return (string)(media_dati_tutti()[media_alt_chiave($url)]['didascalia'] ?? '');
}

/** L'immagine principale di un contenuto: quella in evidenza, altrimenti la foto della prima Apertura dell'editor. */
function media_dati_immagine_principale(array $c): string {
    if (!empty($c['immagine']['url'])) return (string)$c['immagine']['url'];
    if (empty($c['editor'])) return '';
    require_once __DIR__ . '/editor.php';
    $json = (string)($c['riga']['struttura'] ?? '');
    if ($json === '' && function_exists('sito_campo') && !empty($c['fonte']) && !empty($c['riga']['id'])) $json = (string)sito_campo((string)$c['fonte'], 'struttura', (int)$c['riga']['id']);
    $albero = $json !== '' ? editor_decodifica($json) : null;
    if (!is_array($albero)) return '';
    foreach ($albero['elementi'] ?? [] as $s) foreach ($s['figli'] ?? [] as $col) foreach ($col['figli'] ?? [] as $w) {
        if (($w['tipo'] ?? '') !== 'hero') continue;
        $v = function_exists('editor_valori') ? editor_valori('hero', (array)($w['impostazioni'] ?? [])) : (array)($w['impostazioni'] ?? []);
        return trim((string)($v['immagine'] ?? ''));
    }
    return '';
}

/** Il nodo ImageObject di un'immagine, solo con i valori veri. Vuoto se l'immagine non ha dati oltre all'alt. */
function media_dati_image_object(string $url): array {
    $k = media_alt_chiave($url);
    $d = $k !== '' ? (media_dati_tutti()[$k] ?? null) : null;
    if (!$d) return [];
    $pieni = array_filter([$d['titolo'], $d['didascalia'], $d['descrizione'], $d['autore'], $d['licenza'], $d['licenza_url'], $d['fonte_url']], fn($x) => $x !== '');
    if (!$pieni) return []; // solo l'archivio di provenienza, niente da dire a Google
    $ass = preg_match('~^https?://~', $url) ? $url : (defined('SITO_URL') ? SITO_URL : cms_url_sito()) . (str_starts_with($url, '/') ? '' : '/') . $url;
    $info = media_dati_file_info($url);
    $lic = media_dati_licenze()[$d['licenza']] ?? ['', ''];
    $licenza = $d['licenza_url'] !== '' ? $d['licenza_url'] : $lic[1];
    $autore = trim($d['autore']);
    $n = ['@type' => 'ImageObject', 'url' => $ass, 'contentUrl' => $ass];
    if ($info['larghezza'] > 0) { $n['width'] = $info['larghezza']; $n['height'] = $info['altezza']; }
    if ($d['titolo'] !== '') $n['name'] = $d['titolo'];
    if ($d['didascalia'] !== '') $n['caption'] = $d['didascalia'];
    if ($d['descrizione'] !== '') $n['description'] = $d['descrizione'];
    if ($autore !== '') {
        $n['creditText'] = $autore;
        $n['creator'] = ['@type' => $d['autore_tipo'] === 'organizzazione' ? 'Organization' : 'Person', 'name' => $autore];
        if (!in_array($d['licenza'], ['cc0', 'pd'], true)) $n['copyrightNotice'] = $autore;
    }
    if ($licenza !== '') $n['license'] = $licenza;
    if ($d['fonte_url'] !== '') $n['acquireLicensePage'] = $d['fonte_url'];
    return $n;
}

/**
 * Il JSON-LD di una pagina con l'immagine principale descritta: se l'immagine ha dati nella libreria, il nodo WebPage riceve
 * (o arricchisce) primaryImageOfPage con un ImageObject completo. Senza dati i nodi tornano identici.
 */
function media_dati_jsonld(array $nodi, array $c): array {
    if ((int)get_setting('media_dati_n', '0') <= 0) return $nodi;
    $img = media_dati_immagine_principale($c);
    if ($img === '') return $nodi;
    $obj = media_dati_image_object($img);
    if (!$obj) return $nodi;
    foreach ($nodi as $i => $n) {
        if (($n['@type'] ?? '') !== 'WebPage') continue;
        $nodi[$i]['primaryImageOfPage'] = $obj;
        break;
    }
    return $nodi;
}
