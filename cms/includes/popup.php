<?php
/**
 * Popup del sito: una finestra che compare dopo qualche secondo, a metà pagina o quando il mouse esce,
 * con un titolo, un testo, un pulsante e, se si vuole, un modulo (la newsletter, un form o un modulo del sito).
 * Si decide su quali pagine, e ogni quanti giorni la stessa persona la rivede (ricordato nel suo browser).
 *
 * 2.39.0: "Cosa mostra" ha due modi. "Campi semplici" (contenuto = campi) è il popup di sempre. "Costruito con l'editor
 * visuale" (contenuto = editor) mostra un modello dell'editor di tipo "popup" (includes/modelli.php, colonna modello_id):
 * sezioni, colonne e tutti i widget, come una pagina, con bozza, Pubblica e versioni. Esce solo quando il modello è
 * pubblicato. Larghezza, posizione e fondo della finestra valgono per tutti e due i modi; con i valori di partenza
 * (media, centro, con il fondo) un popup a campi esce identico al byte a prima.
 */

require_once __DIR__ . '/form.php';
require_once __DIR__ . '/editor-widget.php';

const POPUP_DOVE = ['tutte' => 'Tutte le pagine', 'home' => 'Solo la home', 'articoli' => 'Solo gli articoli (e gli altri contenuti da leggere)', 'pagine' => 'Solo le pagine fisse', 'percorsi' => 'Solo gli indirizzi elencati'];
const POPUP_INNESCHI = ['secondi' => 'Dopo un certo numero di secondi', 'scroll' => 'Quando si scorre fino a una percentuale della pagina', 'uscita' => 'Quando il mouse esce dalla pagina (solo desktop)'];
// 2.39.0: il primo valore di ogni elenco è quello di partenza (il popup di prima).
const POPUP_CONTENUTI = ['campi' => 'Campi semplici', 'editor' => 'Costruito con l\'editor visuale'];
const POPUP_LARGHEZZE = ['media' => 'Media (560 px, come prima)', 'stretta' => 'Stretta (420 px)', 'larga' => 'Larga (900 px)', 'piena' => 'Tutta la larghezza dello schermo'];
const POPUP_POSIZIONI = ['centro' => 'Al centro, sopra la pagina velata', 'basso-destra' => 'In basso a destra, la pagina resta usabile', 'barra-basso' => 'Barra in fondo allo schermo', 'barra-alto' => 'Barra in cima allo schermo'];

function popup_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $sql = 'CREATE TABLE IF NOT EXISTS ' . tbl('popup') . " (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(100) NOT NULL,
        `attivo` TINYINT(1) NOT NULL DEFAULT 0,
        `occhiello` VARCHAR(100) NOT NULL DEFAULT '',
        `titolo` VARCHAR(255) NOT NULL DEFAULT '',
        `testo` TEXT,
        `immagine` VARCHAR(500) NOT NULL DEFAULT '',
        `pulsante_testo` VARCHAR(100) NOT NULL DEFAULT '',
        `pulsante_link` VARCHAR(500) NOT NULL DEFAULT '',
        `modulo` VARCHAR(30) NOT NULL DEFAULT '',
        `dove` VARCHAR(20) NOT NULL DEFAULT 'tutte',
        `percorsi` TEXT,
        `innesco` VARCHAR(20) NOT NULL DEFAULT 'secondi',
        `valore` INT(11) NOT NULL DEFAULT 8,
        `frequenza_giorni` INT(11) NOT NULL DEFAULT 7,
        `contenuto` VARCHAR(10) NOT NULL DEFAULT 'campi',
        `modello_id` INT(11) NOT NULL DEFAULT 0,
        `larghezza` VARCHAR(10) NOT NULL DEFAULT 'media',
        `posizione` VARCHAR(20) NOT NULL DEFAULT 'centro',
        `sfondo_finestra` TINYINT(1) NOT NULL DEFAULT 1,
        `creato` DATETIME NOT NULL,
        `aggiornato` DATETIME NOT NULL,
        PRIMARY KEY (`id`), KEY `attivo` (`attivo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $impronta = md5($sql . DB_NAME);
    if (get_setting('popup_schema_impronta', '') !== $impronta) {
        db()->exec($sql);
        // 2.39.0: le tabelle nate prima non hanno le colonne nuove; i valori di partenza sono il popup di prima.
        $ci = db()->query('SHOW COLUMNS FROM ' . tbl('popup'))->fetchAll(PDO::FETCH_COLUMN);
        foreach (POPUP_COLONNE_NUOVE as $c => $def) if (!in_array($c, $ci, true)) db()->exec('ALTER TABLE ' . tbl('popup') . " ADD COLUMN `$c` $def");
        save_setting('popup_schema_impronta', $impronta);
    }
}

/** 2.39.0: le colonne aggiunte alle tabelle di prima (stesse definizioni del CREATE). */
const POPUP_COLONNE_NUOVE = [
    'contenuto' => "VARCHAR(10) NOT NULL DEFAULT 'campi'", 'modello_id' => 'INT(11) NOT NULL DEFAULT 0',
    'larghezza' => "VARCHAR(10) NOT NULL DEFAULT 'media'", 'posizione' => "VARCHAR(20) NOT NULL DEFAULT 'centro'",
    'sfondo_finestra' => 'TINYINT(1) NOT NULL DEFAULT 1',
];

/** I testi scritti da chi usa il pannello: con le emoji (4 byte) la tabella a 3 byte rifiutava tutto il popup (errore 3988). */
const POPUP_TESTI = ['nome', 'occhiello', 'titolo', 'testo', 'pulsante_testo'];

/** Le colonne di testo che possono essere NULL diventano stringhe: chi le usa confronta con ''. */
function popup_riga(array $p): array {
    foreach (['testo', 'percorsi', 'occhiello', 'titolo', 'immagine', 'pulsante_testo', 'pulsante_link', 'modulo'] as $k) $p[$k] = (string)($p[$k] ?? '');
    // Audit 2026-11: le emoji si salvano come entità (la tabella è a 3 byte) e qui tornano emoji, nel pannello e sul sito.
    foreach (POPUP_TESTI as $k) $p[$k] = newsletter_4byte_fuori((string)($p[$k] ?? ''));
    // 2.39.0: un valore fuori elenco (o una riga letta prima che la colonna esistesse) torna quello di partenza.
    $p['contenuto'] = isset(POPUP_CONTENUTI[$p['contenuto'] ?? '']) ? $p['contenuto'] : 'campi';
    $p['larghezza'] = isset(POPUP_LARGHEZZE[$p['larghezza'] ?? '']) ? $p['larghezza'] : 'media';
    $p['posizione'] = isset(POPUP_POSIZIONI[$p['posizione'] ?? '']) ? $p['posizione'] : 'centro';
    $p['modello_id'] = (int)($p['modello_id'] ?? 0);
    $p['sfondo_finestra'] = (int)($p['sfondo_finestra'] ?? 1) ? 1 : 0;
    return $p;
}

function popup_tutti(): array {
    popup_schema();
    return array_map('popup_riga', db()->query('SELECT * FROM ' . tbl('popup') . ' ORDER BY attivo DESC, nome')->fetchAll());
}

function popup_leggi(int $id): ?array {
    popup_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('popup') . ' WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    return $p ? popup_riga($p) : null;
}

function popup_salva(int $id, array $d): int {
    popup_schema();
    $nome = mb_substr(trim((string)($d['nome'] ?? '')), 0, 100);
    if ($nome === '') throw new RuntimeException('Il popup ha bisogno di un nome.');
    $v = [
        'nome' => $nome, 'attivo' => !empty($d['attivo']) ? 1 : 0,
        'occhiello' => mb_substr(trim((string)($d['occhiello'] ?? '')), 0, 100), 'titolo' => mb_substr(trim((string)($d['titolo'] ?? '')), 0, 255),
        'testo' => newsletter_4byte_dentro(trim((string)($d['testo'] ?? ''))), 'immagine' => mb_substr(trim((string)($d['immagine'] ?? '')), 0, 500),
        'pulsante_testo' => mb_substr(trim((string)($d['pulsante_testo'] ?? '')), 0, 100), 'pulsante_link' => mb_substr(trim((string)($d['pulsante_link'] ?? '')), 0, 500),
        'modulo' => preg_match('/^[a-z][a-z0-9_:-]{0,40}$/', (string)($d['modulo'] ?? '')) ? $d['modulo'] : '',
        'dove' => is_string($d['dove'] ?? null) && isset(POPUP_DOVE[$d['dove']]) ? $d['dove'] : 'tutte', 'percorsi' => trim((string)($d['percorsi'] ?? '')),
        'innesco' => is_string($d['innesco'] ?? null) && isset(POPUP_INNESCHI[$d['innesco']]) ? $d['innesco'] : 'secondi', 'valore' => max(0, min(600, (int)($d['valore'] ?? 8))),
        'frequenza_giorni' => max(0, min(365, (int)($d['frequenza_giorni'] ?? 7))),
    ];
    // 2.39.0: modo, larghezza, posizione e fondo. Chi non li manda (il pop-up dei form, fb_popup_salva) lascia quelli che ci sono.
    $prima = $id ? popup_leggi($id) : null;
    foreach (['contenuto' => POPUP_CONTENUTI, 'larghezza' => POPUP_LARGHEZZE, 'posizione' => POPUP_POSIZIONI] as $k => $elenco) {
        if (array_key_exists($k, $d)) $v[$k] = is_string($d[$k]) && isset($elenco[$d[$k]]) ? $d[$k] : array_key_first($elenco);
        elseif ($prima) $v[$k] = $prima[$k];
    }
    // La scheda manda sempre sfondo_finestra (un campo nascosto a 0 prima della casella): senza la voce resta com'era.
    if (array_key_exists('sfondo_finestra', $d)) $v['sfondo_finestra'] = is_scalar($d['sfondo_finestra']) && (string)$d['sfondo_finestra'] === '1' ? 1 : 0;
    $contenuto = $v['contenuto'] ?? ($prima['contenuto'] ?? 'campi');
    // Il modello si collega solo da qui (popup_modello_crea): un modello_id mandato a mano non conta.
    if ($contenuto === 'campi' && $v['titolo'] === '' && $v['testo'] === '' && $v['modulo'] === '') throw new RuntimeException('Un popup vuoto: metti almeno un titolo, un testo o un modulo.');
    foreach (POPUP_TESTI as $k) $v[$k] = newsletter_4byte_dentro(newsletter_4byte_fuori((string)$v[$k])); // audit 2026-11
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($v)));
        $p = []; foreach ($v as $k => $x) $p[":$k"] = $x; $p[':id'] = $id;
        db()->prepare('UPDATE ' . tbl('popup') . " SET $set, aggiornato = NOW() WHERE id = :id")->execute($p);
        return $id;
    }
    $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($v)));
    $segna = implode(', ', array_map(fn($k) => ":$k", array_keys($v)));
    $p = []; foreach ($v as $k => $x) $p[":$k"] = $x;
    db()->prepare('INSERT INTO ' . tbl('popup') . " ($cols, creato, aggiornato) VALUES ($segna, NOW(), NOW())")->execute($p);
    return (int)db()->lastInsertId();
}

/** I popup che valgono per questo indirizzo. $genere: home, post, page, altro. */
function popup_per(string $percorso, string $genere): array {
    popup_schema();
    // 2.39.0: l'anteprima dal pannello (?preview=1&popup_prova=ID, solo l'amministratore): quel popup e basta, anche spento,
    // con la bozza del disegno, aperto subito e senza la memoria dei giorni.
    if (($prova = popup_prova_id()) > 0) {
        $p = popup_leggi($prova);
        return $p ? [$p + ['_prova' => true]] : [];
    }
    $out = [];
    foreach (db()->query('SELECT * FROM ' . tbl('popup') . ' WHERE attivo = 1 ORDER BY id')->fetchAll() as $p) {
        $p = popup_riga($p);
        $ok = match ($p['dove']) {
            'home' => $genere === 'home',
            'articoli' => $genere === 'post',
            'pagine' => $genere === 'page',
            'percorsi' => in_array(popup_percorso_pulito($percorso), array_map('popup_percorso_pulito', preg_split('/\R|,/', (string)$p['percorsi'])), true),
            default => true,
        };
        if ($ok) $out[] = $p;
    }
    return $out;
}

/** Un indirizzo scritto a mano, con o senza slash finale o dominio, nella forma del sito: /contatti/ */
function popup_percorso_pulito(string $p): string {
    $p = trim($p);
    if ($p === '') return '';
    $p = preg_replace('~^https?://[^/]+~i', '', $p);
    $p = parse_url($p, PHP_URL_PATH) ?: '/';
    return '/' . trim($p, '/') . (trim($p, '/') === '' ? '' : '/');
}

/** 2.39.0: l'id del popup da provare (?preview=1&popup_prova=ID), solo per l'amministratore in anteprima; 0 altrimenti. */
function popup_prova_id(): int {
    if (!isset($_GET['popup_prova']) || !is_string($_GET['popup_prova']) || !ctype_digit($_GET['popup_prova'])) return 0;
    if (!is_file(__DIR__ . '/modelli.php')) return 0;
    require_once __DIR__ . '/modelli.php';
    return function_exists('modelli_anteprima_admin') && modelli_anteprima_admin() ? (int)$_GET['popup_prova'] : 0;
}

// ------------------------------------------------------------
// 2.39.0: il popup costruito con l'editor visuale (un modello di tipo "popup")
// ------------------------------------------------------------

/** Il modello dell'editor del popup: solo se esiste, è di tipo "popup" e non è nel cestino. */
function popup_modello(array $p): ?array {
    $mid = (int)($p['modello_id'] ?? 0);
    if ($mid <= 0 || !is_file(__DIR__ . '/modelli.php')) return null;
    require_once __DIR__ . '/modelli.php';
    $m = modelli_leggi($mid);
    return $m && $m['tipo'] === 'popup' && empty($m['cestino']) ? $m : null;
}

/** Il disegno è pubblicato? Stato pubblicato e una struttura pubblicata (come il riquadro di fine articolo). */
function popup_modello_pubblicato(?array $m): bool {
    return $m !== null && $m['status'] === 'published' && empty($m['cestino']) && trim((string)($m['struttura'] ?? '')) !== '';
}

/** Ci sono modifiche salvate nell'editor ma non ancora pubblicate? */
function popup_modello_modifiche(?array $m): bool {
    return $m !== null && trim((string)($m['struttura_bozza'] ?? '')) !== '' && (string)$m['struttura_bozza'] !== (string)($m['struttura'] ?? '');
}

/** Il popup a cui appartiene un modello (per l'editor: "Torna al popup", la larghezza della tela), o null. */
function popup_per_modello(int $mid): ?array {
    if ($mid <= 0) return null;
    popup_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('popup') . ' WHERE modello_id = ? ORDER BY id LIMIT 1');
    $st->execute([$mid]);
    $p = $st->fetch();
    return $p ? popup_riga($p) : null;
}

/**
 * Il modello del popup, creato al primo "Apri nell'editor" con una sezione di partenza: titolo, testo e pulsante,
 * presi dal popup se li ha già (occhiello, titolo, testo, pulsante), altrimenti un esempio da cambiare. Ritorna l'id.
 */
function popup_modello_crea(int $id): int {
    $p = popup_leggi($id);
    if (!$p) throw new RuntimeException('Popup non trovato.');
    if ($m = popup_modello($p)) return (int)$m['id'];
    require_once __DIR__ . '/modelli.php';
    require_once __DIR__ . '/editor.php';
    // Il pulsante va sempre: con il link del popup se l'ha, altrimenti "#chiudi" (chiude il popup, come la X).
    $link = $p['pulsante_testo'] !== '' ? editor_link_sicuro($p['pulsante_link']) : '';
    $w = [
        editor_widget('titolo', ['occhiello' => $p['occhiello'], 'testo' => $p['titolo'] !== '' ? $p['titolo'] : 'Un momento, prima di andare', 'livello' => 'h2']),
        editor_widget('testo', ['contenuto' => $p['testo'] !== '' ? editor_ricco_pulito($p['testo'])
            : '<p>Scrivi qui il messaggio del popup. Puoi aggiungere foto, pulsanti, un form e tutti i widget, come in una pagina.</p>']),
        editor_widget('pulsanti', ['voci' => [$link !== '' ? ['testo' => $p['pulsante_testo'], 'link' => $link, 'stile' => 'viola', 'nuova' => false] : ['testo' => 'Ho capito', 'link' => '#chiudi', 'stile' => 'viola', 'nuova' => false]]]),
    ];
    $albero = editor_valida(['elementi' => [editor_sezione($w, ['larghezza' => 'contenuta', 'spazio_sopra' => ['d' => 40, 'm' => 32], 'spazio_sotto' => ['d' => 40, 'm' => 32]])]]);
    $mid = modelli_crea('popup', 'Popup: ' . $p['nome'], $albero);
    db()->prepare('UPDATE ' . tbl('popup') . ' SET modello_id = ?, aggiornato = NOW() WHERE id = ?')->execute([$mid, $id]);
    return $mid;
}

/** Elimina il popup e, se ne ha uno, il suo disegno dell'editor (con le sue versioni nella tabella delle revisioni). */
function popup_elimina(int $id): void {
    $p = popup_leggi($id);
    if (!$p) return;
    if ($m = popup_modello($p)) modelli_elimina((int)$m['id']);
    db()->prepare('DELETE FROM ' . tbl('popup') . ' WHERE id = ?')->execute([$id]);
}

/** [html, css] del disegno del popup per questa pagina, o null se non deve uscire (modello mancante, non pubblicato, vuoto). */
function popup_disegno(array $p, string $percorso, bool $bozza = false): ?array {
    try {
        $m = popup_modello($p);
        if ($m === null || (!$bozza && !popup_modello_pubblicato($m))) return null;
        require_once __DIR__ . '/editor.php';
        [$html, $css] = editor_disegna(modelli_albero($m, $bozza), $percorso);
        if (trim(strip_tags($html, '<img><iframe><video><svg><form><input><button><a>')) === '') return null;
        return [$html, $css];
    } catch (Throwable $e) {
        // Un disegno rotto non deve spegnere la pagina: il popup non esce e resta traccia nel log.
        error_log('popup ' . (int)$p['id'] . ': ' . $e->getMessage());
        return null;
    }
}

/** Le classi in più della finestra: nessuna con i valori di partenza (il popup di prima, identico al byte). */
function popup_classi(array $p, bool $editor): string {
    $c = [];
    if ($editor) $c[] = 'popup-editor';
    if (($p['larghezza'] ?? 'media') !== 'media') $c[] = 'popup-l-' . $p['larghezza'];
    if (($p['posizione'] ?? 'centro') !== 'centro') $c[] = 'popup-p-' . $p['posizione'];
    if (empty($p['sfondo_finestra'])) $c[] = 'popup-senza-fondo';
    return $c ? ' ' . implode(' ', $c) : '';
}

/** Il modulo dentro il popup dice da quale popup arriva (con un errore la pagina riapre il popup) e prende id suoi. */
function popup_moduli_dentro(string $html, int $id, bool $tutti): string {
    $html = str_replace('<input type="hidden" name="captcha" value="">', '<input type="hidden" name="captcha" value=""><input type="hidden" name="popup" value="' . $id . '">', $html);
    // Lo stesso modulo può stare anche in pagina: dentro il popup gli id cambiano, così non si ripetono.
    // Con l'editor (2.39.0) anche i rimandi agli aiuti dei campi file (form-file-...).
    return $tutti
        ? (string)preg_replace('/\b(id|aria-labelledby|aria-describedby|for)="(modulo["-]|form-file-)/', '$1="popup-' . $id . '-$2', $html)
        : (string)preg_replace('/\b(id|aria-labelledby)="modulo(["-])/', '$1="popup-' . $id . '-modulo$2', $html);
}

/**
 * L'HTML dei popup per la pagina, più lo script. Vuoto se non ce ne sono.
 * $pagina (2.39.0): l'HTML della pagina già pronto, per non caricare due volte i fogli e gli script dei widget del disegno.
 */
function popup_html(array $popup, string $percorso, string $pagina = ''): string {
    if (!$popup) return '';
    $h = '';
    $riapri = is_string($_GET['popup'] ?? null) ? (int)$_GET['popup'] : 0;
    foreach ($popup as $p) {
        $pid = (int)$p['id'];
        $prova = !empty($p['_prova']);
        if (($p['contenuto'] ?? 'campi') === 'editor') {
            $d = popup_disegno($p, $percorso, $prova);
            if ($d === null) continue;
            $h .= popup_html_editor($p, $d[0], $d[1], $percorso, $riapri, $prova, $pagina . $h);
            continue;
        }
        $modulo = $p['modulo'] !== '' ? vista_modulo($p['modulo'], $percorso) : '';
        // Il modulo dice da quale popup arriva: con un errore la pagina riapre il popup sulla risposta.
        if ($modulo !== '') $modulo = popup_moduli_dentro($modulo, $pid, false);
        $subito = ($riapri === $pid && $modulo !== '' && isset($_GET['modulo'])) || $prova;
        $h .= '<div class="popup' . popup_classi($p, false) . '" id="popup-' . $pid . '" data-innesco="' . h($p['innesco']) . '" data-valore="' . (int)$p['valore'] . '" data-giorni="' . (int)$p['frequenza_giorni'] . '"' . ($subito ? ' data-subito="1"' : '') . ($prova ? ' data-prova="1"' : '') . ' hidden>'
            . '<div class="popup-finestra" role="dialog" aria-modal="' . (($p['posizione'] ?? 'centro') === 'centro' ? 'true' : 'false') . '" aria-labelledby="popup-titolo-' . $pid . '">'
            . '<button type="button" class="popup-chiudi" aria-label="Chiudi">×</button>'
            . ($p['immagine'] !== '' ? '<img class="popup-immagine" src="' . h($p['immagine']) . '" alt="" loading="lazy">' : '')
            . ($p['occhiello'] !== '' ? '<span class="occhiello">' . h($p['occhiello']) . '</span>' : '')
            . ($p['titolo'] !== '' ? '<h2 id="popup-titolo-' . $pid . '">' . h($p['titolo']) . '</h2>' : '')
            . ($p['testo'] !== '' ? '<div class="popup-testo">' . editor_ricco_pulito((string)$p['testo']) . '</div>' : '')
            // 2.2: il link passa da editor_link_sicuro() come quelli dei widget (javascript: e simili restano fuori, "contatti" diventa /contatti/).
            . ($p['pulsante_testo'] !== '' && editor_link_sicuro($p['pulsante_link']) !== '' ? '<p class="azioni"><a class="btn btn-viola" href="' . h(editor_link_sicuro($p['pulsante_link'])) . '">' . h($p['pulsante_testo']) . '</a></p>' : '')
            . $modulo . '</div></div>';
    }
    if ($h === '') return '';
    return $h . '<script src="' . sito_versione_asset('popup.js') . '" defer></script>';
}

/**
 * 2.39.0: un popup costruito con l'editor. Il disegno pubblicato con il suo CSS (come il riquadro di fine articolo, senza
 * <article>), i fogli e gli script dei widget che usa (gli stessi delle pagine, editor_asset_pagina), il pulsante Chiudi
 * sempre fuori dal disegno, il nome della finestra dal primo titolo del disegno (o dal nome del popup).
 */
function popup_html_editor(array $p, string $html, string $css, string $percorso, int $riapri, bool $prova, string $gia): string {
    $pid = (int)$p['id'];
    $html = popup_moduli_dentro($html, $pid, true);
    $conModulo = str_contains($html, 'name="popup" value="' . $pid . '"');
    $subito = ($riapri === $pid && $conModulo && isset($_GET['modulo'])) || $prova;
    // Il primo titolo del disegno dà il nome alla finestra: con il suo id, o con uno nuovo.
    $etichetta = ' aria-label="' . h($p['nome']) . '"';
    if (preg_match('~<h([1-6])\b([^>]*)>~i', $html, $t, PREG_OFFSET_CAPTURE)) {
        if (preg_match('~\sid="([^"]+)"~', $t[2][0], $idm)) $tid = $idm[1];
        else {
            $tid = 'popup-titolo-' . $pid;
            $html = substr_replace($html, '<h' . $t[1][0] . ' id="' . $tid . '"' . $t[2][0] . '>', $t[0][1], strlen($t[0][0]));
        }
        $etichetta = ' aria-labelledby="' . h($tid) . '"';
    }
    // I fogli e gli script dei widget usati, se la pagina (o un popup prima) non li ha già.
    $testa = '';
    $coda = '';
    if (function_exists('editor_asset_pagina')) {
        $asset = editor_asset_pagina($html);
        foreach (array_reverse($asset['css'] ?? []) as $f) { $u = sito_versione_asset($f); if (!str_contains($gia, $u)) $testa .= '<link rel="stylesheet" href="' . $u . '">'; }
        foreach ($asset['js'] ?? [] as $f) { $u = sito_versione_asset($f); if (!str_contains($gia, $u)) $coda .= '<script src="' . $u . '" defer></script>'; }
    }
    if ((function_exists('editor_serve_script') ? editor_serve_script($html) : false) && !str_contains($gia . $coda, 'editor-pagina.js')) $coda .= '<script src="' . sito_versione_asset('editor-pagina.js') . '" defer></script>';
    $stile = $css !== '' ? '<style id="ed-css-popup-' . $pid . '">' . str_replace('</', '<\\/', $css) . '</style>' : '';
    return $testa . $stile
        . '<div class="popup' . popup_classi($p, true) . '" id="popup-' . $pid . '" data-innesco="' . h($p['innesco']) . '" data-valore="' . (int)$p['valore'] . '" data-giorni="' . (int)$p['frequenza_giorni'] . '"' . ($subito ? ' data-subito="1"' : '') . ($prova ? ' data-prova="1"' : '') . ' hidden>'
        . '<div class="popup-finestra" role="dialog" aria-modal="' . (($p['posizione'] ?? 'centro') === 'centro' ? 'true' : 'false') . '"' . $etichetta . '>'
        . '<button type="button" class="popup-chiudi" aria-label="Chiudi">×</button>'
        . '<div class="popup-disegno ed-modello" data-modello="' . (int)$p['modello_id'] . '">' . $html . '</div>'
        . '</div></div>' . $coda;
}
