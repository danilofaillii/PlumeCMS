<?php
/**
 * 2.12.0, fase 5 del piano di docs-sviluppo/brevo-confronto.md (capitoli 1.12, 1.13, 3.6): l'editor delle email a blocchi.
 *
 *   - il design: JSON con lo stile globale e le sezioni a colonne, ognuna con i suoi blocchi (titolo, testo, immagine,
 *     pulsante, divisore, spaziatore, social, video, piede). Ogni valore passa da un elenco chiuso (nd_pulisci()): tipi,
 *     proprietà, colori #rrggbb, caratteri sicuri per la posta, link https, mailto, tel o i quattro link speciali;
 *   - da JSON a HTML per la posta (nd_html()): solo tabelle, stili in linea, larghezza 600 (dallo stile), immagini con alt
 *     e misure, pulsanti a tabella con lo sfondo (Outlook), colonne una sotto l'altra al telefono (media query); il piede
 *     con il link per uscire e l'indirizzo dell'azienda si rimette da solo se manca (nd_piede_obbligatorio());
 *   - modelli (newsletter_modelli: attivo, rinomina, duplica, galleria in includes/newsletter-modelli/*.json, modello di
 *     doppio opt-in con il tag optin) e sezioni salvate (newsletter_sezioni_salvate), sincronizzate con le bozze.
 *
 * Il JSON si salva con i caratteri fuori dall'ASCII scritti \uXXXX (le emoji non rompono le colonne utf8 a 3 byte, e nei
 * campi di testo non entra mai un'entità scritta due volte).
 */

require_once __DIR__ . '/newsletter-campagne.php';
require_once __DIR__ . '/sicurezza-html.php';

const ND_FONT = ['arial' => ['Arial', 'Arial,Helvetica,sans-serif'], 'helvetica' => ['Helvetica', 'Helvetica,Arial,sans-serif'],
    'verdana' => ['Verdana', 'Verdana,Geneva,sans-serif'], 'tahoma' => ['Tahoma', 'Tahoma,Verdana,sans-serif'],
    'trebuchet' => ['Trebuchet MS', '\'Trebuchet MS\',Helvetica,sans-serif'], 'georgia' => ['Georgia', 'Georgia,\'Times New Roman\',serif'],
    'times' => ['Times New Roman', '\'Times New Roman\',Times,serif'], 'courier' => ['Courier New', '\'Courier New\',Courier,monospace']];
const ND_ALLINEA = ['left' => 'A sinistra', 'center' => 'Al centro', 'right' => 'A destra'];
const ND_LAYOUT = ['100' => ['Una colonna', [100]], '50-50' => ['Due colonne uguali', [50, 50]], '33-67' => ['Un terzo e due terzi', [33, 67]],
    '67-33' => ['Due terzi e un terzo', [67, 33]], '25-75' => ['Un quarto e tre quarti', [25, 75]], '75-25' => ['Tre quarti e un quarto', [75, 25]],
    '33-33-33' => ['Tre colonne', [33, 34, 33]]];
const ND_RETI = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
    'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'pinterest' => 'Pinterest', 'sito' => 'Sito', 'email' => 'Email'];
const ND_SPECIALI = ['unsubscribe', 'update_profile', 'mirror', 'doubleoptin'];
const ND_MAX_JSON = 400000;
const ND_MAX_SEZIONI = 60;
const ND_MAX_BLOCCHI = 300;

/** Le proprietà: [tipo, etichetta, predefinito, altro]. Tipi: t testo (altro: massimo), h testo con formattazione, c colore
 *  (vuoto: dello stile), s scelta (altro: opzioni), n numero (altro: [min, max]), m misura facoltativa (vuota o [min, max]),
 *  l link, i immagine, b sì/no, r reti social. */
function nd_spec_stile(): array {
    return [
        'fondo' => ['c', 'Sfondo intorno all\'email', '#f4f4f6'], 'corpo' => ['c', 'Sfondo del corpo', '#ffffff'],
        'larghezza' => ['n', 'Larghezza del corpo (px)', 600, [480, 700]], 'raggio' => ['n', 'Angoli del corpo (px)', 12, [0, 30]],
        'font' => ['s', 'Carattere del testo', 'arial', array_map(fn($f) => $f[0], ND_FONT)],
        'font_titoli' => ['s', 'Carattere dei titoli', 'arial', array_map(fn($f) => $f[0], ND_FONT)],
        'dim' => ['n', 'Dimensione del testo (px)', 16, [12, 22]], 'testo' => ['c', 'Colore del testo', '#1f2330'],
        'titoli' => ['c', 'Colore dei titoli', '#0b1020'], 'link' => ['c', 'Colore dei link', '#6d28d9'],
        'btn_fondo' => ['c', 'Sfondo dei pulsanti', '#6d28d9'], 'btn_testo' => ['c', 'Testo dei pulsanti', '#ffffff'],
        'btn_raggio' => ['n', 'Angoli dei pulsanti (px)', 8, [0, 30]],
    ];
}

function nd_spec_sezione(): array {
    return [
        'layout' => ['s', 'Colonne', '100', array_map(fn($l) => $l[0], ND_LAYOUT)], 'fondo' => ['c', 'Sfondo della sezione', ''],
        'spazio' => ['n', 'Spazio sopra e sotto (px)', 24, [0, 80]], 'spazio_x' => ['n', 'Spazio ai lati (px)', 30, [0, 60]],
        'tel' => ['s', 'Sul telefono', 'impila', ['impila' => 'Le colonne una sotto l\'altra', 'affianca' => 'Le colonne restano affiancate']],
        'nascosta' => ['b', 'Nascondi la sezione (non esce nell\'email)', false],
    ];
}

function nd_spec_blocchi(): array {
    $all = ['s', 'Allineamento', 'left', ND_ALLINEA];
    $cen = ['s', 'Allineamento', 'center', ND_ALLINEA];
    $col = ['c', 'Colore del testo (vuoto: quello dello stile)', ''];
    $spa = ['n', 'Spazio sotto (px)', 16, [0, 80]];
    return [
        'titolo' => ['Titolo', 'T', ['testo' => ['t', 'Testo', 'Il titolo della tua email', 300], 'livello' => ['s', 'Grandezza', 'h1', ['h1' => 'Grande', 'h2' => 'Media', 'h3' => 'Piccola']],
            'allinea' => $all, 'colore' => $col, 'spazio' => ['n', 'Spazio sotto (px)', 12, [0, 80]]]],
        'testo' => ['Testo', '¶', ['html' => ['h', 'Testo', '<p>Scrivi qui il tuo messaggio. Per mettere il nome di chi legge scrivi {{ contact.NOME }}.</p>', 20000],
            'allinea' => $all, 'colore' => $col, 'dim' => ['m', 'Dimensione (px, vuoto: quella dello stile)', '', [10, 30]], 'spazio' => $spa]],
        'immagine' => ['Immagine', '▣', ['src' => ['i', 'Immagine', ''], 'alt' => ['t', 'Testo alternativo (lo legge chi non vede le immagini)', '', 200],
            'larghezza' => ['n', 'Larghezza (% della colonna)', 100, [10, 100]], 'allinea' => $cen, 'link' => ['l', 'Link (facoltativo)', ''],
            'raggio' => ['n', 'Angoli (px)', 0, [0, 40]], 'spazio' => $spa, 'w' => ['n', '', 0, [0, 6000]], 'h' => ['n', '', 0, [0, 6000]]]],
        'pulsante' => ['Pulsante', '▭', ['testo' => ['t', 'Testo del pulsante', 'Scopri di più', 100], 'link' => ['l', 'Link', ''], 'allinea' => $cen,
            'fondo' => ['c', 'Sfondo (vuoto: quello dello stile)', ''], 'colore' => ['c', 'Testo (vuoto: quello dello stile)', ''],
            'raggio' => ['m', 'Angoli (px, vuoto: quelli dello stile)', '', [0, 30]], 'pieno' => ['b', 'Largo quanto la colonna', false], 'spazio' => $spa]],
        'divisore' => ['Divisore', '―', ['colore' => ['c', 'Colore', '#e5e7eb'], 'spessore' => ['n', 'Spessore (px)', 1, [1, 8]],
            'stile' => ['s', 'Linea', 'solid', ['solid' => 'Continua', 'dashed' => 'A trattini', 'dotted' => 'A puntini']],
            'larghezza' => ['n', 'Larghezza (%)', 100, [10, 100]], 'spazio' => $spa]],
        'spaziatore' => ['Spaziatore', '↕', ['altezza' => ['n', 'Altezza (px)', 24, [4, 160]]]],
        'social' => ['Social', '◎', ['reti' => ['r', 'Le reti', [['r' => 'facebook', 'url' => ''], ['r' => 'instagram', 'url' => '']]],
            'stile' => ['s', 'Icone', 'colori', ['colori' => 'A colori', 'scuro' => 'Scure', 'chiaro' => 'Chiare (per gli sfondi scuri)']],
            'dim' => ['n', 'Grandezza delle icone (px)', 32, [20, 48]], 'allinea' => $cen, 'spazio' => $spa]],
        'video' => ['Video', '▶', ['url' => ['l', 'Link del video (YouTube, Vimeo o il tuo sito)', ''], 'src' => ['i', 'Miniatura (vuota: quella di YouTube)', ''],
            'alt' => ['t', 'Testo alternativo della miniatura', 'Guarda il video', 200], 'testo' => ['t', 'Testo del link sotto', 'Guarda il video', 100],
            'larghezza' => ['n', 'Larghezza (% della colonna)', 100, [10, 100]], 'allinea' => $cen, 'spazio' => $spa, 'w' => ['n', '', 0, [0, 6000]], 'h' => ['n', '', 0, [0, 6000]]]],
        'piede' => ['Piede', '⌄', ['html' => ['h', 'Testo del piede', '<p>Ricevi questa email perché sei iscritto alla nostra newsletter.</p><p><a href="{{ unsubscribe }}">Esci dalla newsletter</a> · <a href="{{ update_profile }}">Aggiorna le tue preferenze</a></p>', 5000],
            'indirizzo' => ['b', 'Mostra l\'indirizzo dell\'azienda (da Impostazioni della newsletter)', true], 'allinea' => $cen,
            'colore' => ['c', 'Colore del testo', '#6b7280'], 'dim' => ['n', 'Dimensione (px)', 12, [10, 16]], 'spazio' => ['n', 'Spazio sotto (px)', 0, [0, 80]]]],
    ];
}

// ------------------------------------------------------------
// Dati
// ------------------------------------------------------------

function nd_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    newsletter_campagne_schema();
    $modelli = 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_modelli') . " (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(150) NOT NULL DEFAULT '',
        `editor` VARCHAR(8) NOT NULL DEFAULT 'blocchi',
        `design` MEDIUMTEXT NULL,
        `html` MEDIUMTEXT NULL,
        `oggetto` VARCHAR(200) NOT NULL DEFAULT '',
        `attivo` TINYINT(1) NOT NULL DEFAULT 0,
        `tag` VARCHAR(50) NOT NULL DEFAULT '',
        `origine` VARCHAR(40) NOT NULL DEFAULT '',
        `creato` DATETIME NOT NULL,
        `aggiornato` DATETIME NOT NULL,
        PRIMARY KEY (`id`), KEY `attivo` (`attivo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $sezioni = 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_sezioni_salvate') . " (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(150) NOT NULL DEFAULT '',
        `json` MEDIUMTEXT NULL,
        `sincronizzata` TINYINT(1) NOT NULL DEFAULT 0,
        `creato` DATETIME NOT NULL,
        `aggiornato` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $impronta = md5($modelli . $sezioni . DB_NAME . 'design modello_id');
    if (get_setting('newsletter_design_impronta', '') === $impronta) return;
    db()->exec($modelli);
    db()->exec($sezioni);
    $t = tbl('newsletter_campagne');
    $col = db()->query('SHOW COLUMNS FROM ' . $t)->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('design', $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `design` MEDIUMTEXT NULL");
    if (!in_array('modello_id', $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `modello_id` INT(11) NOT NULL DEFAULT 0");
    save_setting('newsletter_design_impronta', $impronta);
}

/** Il JSON da salvare: ASCII puro (gli altri caratteri come \uXXXX). */
function nd_json(array $d): string {
    return (string)json_encode($d, JSON_UNESCAPED_SLASHES);
}

/** Da JSON (o array) al design pulito; null se non è un design. */
function nd_design(mixed $j): ?array {
    if (is_string($j)) {
        if ($j === '' || strlen($j) > ND_MAX_JSON) return null;
        $j = json_decode($j, true, 16);
    }
    return is_array($j) ? nd_pulisci($j) : null;
}

function nd_id(mixed $v): string {
    return is_string($v) && preg_match('/^[a-z0-9]{1,16}$/', $v) ? $v : substr(bin2hex(random_bytes(6)), 0, 10);
}

/** Un colore #rrggbb (anche #rgb), altrimenti ''. Niente altro passa: nessun CSS libero. */
function nd_colore(mixed $v): string {
    if (!is_string($v)) return '';
    $v = strtolower(trim($v));
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $v, $m)) return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    return preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : '';
}

/** L'origine del sito (schema, host, porta), per gli indirizzi interi nelle email. */
function nd_origine(): string {
    $u = parse_url(cms_url_sito());
    return ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '') . (!empty($u['port']) ? ':' . $u['port'] : '');
}

/** Un link per la posta: https, mailto, tel, i quattro link speciali, un percorso del sito (diventa intero). Il resto: ''. */
function nd_link(mixed $v): string {
    if (!is_string($v)) return '';
    $v = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $v) ?? '');
    if ($v === '' || strlen($v) > 1000) return '';
    if (preg_match('/^\{\{\s*(' . implode('|', ND_SPECIALI) . ')\s*\}\}$/i', $v, $m)) return '{{ ' . strtolower($m[1]) . ' }}';
    if (preg_match('~^https?://[^\s"\'<>\\\\`]+$~i', $v)) return $v;
    if (preg_match('~^mailto:[^\s"\'<>\\\\:`/]+@[^\s"\'<>\\\\`]+$~i', $v)) return $v;
    if (preg_match('~^tel:\+?[0-9 ().-]{3,30}$~i', $v)) return $v;
    if (preg_match('~^/(?!/)[^\s"\'<>\\\\`]*$~', $v)) return nd_origine() . cms_con_cartella_percorso($v);
    if (preg_match('~^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(/[^\s"\'<>\\\\`]*)?$~i', $v)) return 'https://' . $v;
    return '';
}

/** Un percorso del sito con la cartella del CMS davanti (se non ce l'ha già). */
function cms_con_cartella_percorso(string $p): string {
    $b = cms_sito_base();
    return $b !== '' && !str_starts_with($p, $b . '/') && $p !== $b ? $b . $p : $p;
}

/** L'indirizzo di un'immagine: solo https (o http) e percorsi del sito. Niente data:, niente schemi strani. */
function nd_img(mixed $v): string {
    if (!is_string($v)) return '';
    $v = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $v) ?? '');
    if ($v === '' || strlen($v) > 1000) return '';
    if (preg_match('~^https?://[^\s"\'<>\\\\`()]+$~i', $v)) return $v;
    if (preg_match('~^/(?!/)[^\s"\'<>\\\\`()]*$~', $v)) return nd_origine() . cms_con_cartella_percorso($v);
    return '';
}

/** Un testo su una riga, senza caratteri di controllo. */
function nd_testo(mixed $v, int $max): string {
    if (!is_scalar($v)) return '';
    return mb_substr(trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$v)), 0, $max);
}

/**
 * Il testo con la formattazione: solo p, br, strong, b, em, i, u, s, a (href da nd_link), ul, ol, li, senza attributi.
 * Le liste puntate dell'editor (ol con li data-list="bullet") diventano ul.
 */
function nd_html_pulito(mixed $v, int $max): string {
    if (!is_string($v) || trim($v) === '') return '';
    $v = mb_substr($v, 0, $max * 2);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prima = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><html><body><div id="nd">' . $v . '</div></body></html>', LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prima);
    $radice = $doc->getElementById('nd');
    if (!$radice) return '';
    $ok = array_flip(['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li']);
    $via = array_flip(['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'input', 'button', 'textarea', 'select', 'head', 'title', 'meta', 'link', 'img', 'video', 'audio']);
    $giro = function (DOMNode $n) use (&$giro, $ok, $via, $doc) {
        foreach (iterator_to_array($n->childNodes) as $f) {
            if ($f instanceof DOMText) continue;
            if (!($f instanceof DOMElement)) { $n->removeChild($f); continue; }
            $tag = strtolower($f->tagName);
            if (isset($via[$tag])) { $n->removeChild($f); continue; }
            if (!isset($ok[$tag])) {
                $giro($f);
                while ($f->firstChild) $n->insertBefore($f->firstChild, $f);
                $n->removeChild($f);
                continue;
            }
            $href = $tag === 'a' ? nd_link($f->getAttribute('href')) : '';
            $bullet = $tag === 'ol' && $f->firstElementChild && $f->firstElementChild->getAttribute('data-list') === 'bullet';
            foreach (iterator_to_array($f->attributes) as $a) $f->removeAttribute($a->name);
            if ($tag === 'a' && $href !== '') $f->setAttribute('href', $href);
            if ($bullet) {
                $ul = $doc->createElement('ul');
                while ($f->firstChild) $ul->appendChild($f->firstChild);
                $n->replaceChild($ul, $f);
                $f = $ul;
            }
            $giro($f);
        }
    };
    $giro($radice);
    $out = '';
    foreach ($radice->childNodes as $c) $out .= $doc->saveHTML($c);
    // libxml scrive gli href con le parentesi e gli spazi codificati: i link speciali tornano come si scrivono.
    $out = preg_replace('~href="%7B%7B%20(' . implode('|', ND_SPECIALI) . ')%20%7D%7D"~', 'href="{{ $1 }}"', $out) ?? '';
    $out = trim(preg_replace('~<p>(\s|<br>)*</p>~', '', $out) ?? '');
    return strlen($out) > $max * 4 ? '' : $out;
}

/** Un valore pulito secondo la sua specifica. */
function nd_valore(array $s, mixed $v): mixed {
    [$tipo, , $pre] = $s;
    $altro = $s[3] ?? null;
    switch ($tipo) {
        case 't': return $v === null ? $pre : nd_testo($v, (int)$altro);
        case 'h': return $v === null ? $pre : nd_html_pulito($v, (int)$altro);
        case 'c': return $v === null ? $pre : (nd_colore($v) ?: $pre);
        case 's': return is_string($v) && isset($altro[$v]) ? $v : $pre;
        case 'n': return is_numeric($v) ? max($altro[0], min($altro[1], (int)round((float)$v))) : $pre;
        case 'm': return is_numeric($v) && $v !== '' ? max($altro[0], min($altro[1], (int)round((float)$v))) : '';
        case 'b': return $v === null ? $pre : (bool)$v;
        case 'l': return $v === null ? $pre : nd_link($v);
        case 'i': return $v === null ? $pre : nd_img($v);
        case 'r':
            if (!is_array($v)) return $pre;
            $out = [];
            foreach (array_slice($v, 0, 12) as $x) {
                if (!is_array($x) || !is_string($x['r'] ?? null) || !isset(ND_RETI[$x['r']])) continue;
                $out[] = ['r' => $x['r'], 'url' => nd_link($x['url'] ?? '')];
            }
            return $out;
    }
    return $pre;
}

/** Le proprietà di un oggetto (stile, sezione, blocco) pulite: solo quelle della specifica, tutte presenti. */
function nd_props(array $spec, mixed $in): array {
    $in = is_array($in) ? $in : [];
    $out = [];
    foreach ($spec as $k => $s) $out[$k] = nd_valore($s, array_key_exists($k, $in) ? $in[$k] : null);
    return $out;
}

/** Un blocco pulito, o null se il tipo non esiste. */
function nd_blocco(mixed $b): ?array {
    $spec = nd_spec_blocchi();
    if (!is_array($b) || !is_string($b['t'] ?? null) || !isset($spec[$b['t']])) return null;
    return ['id' => nd_id($b['id'] ?? null), 't' => $b['t']] + nd_props($spec[$b['t']][2], $b);
}

/** Una sezione pulita: le colonne sono quelle del suo layout, i blocchi in più spariscono. $conta: blocchi già presi. */
function nd_sezione(mixed $s, int &$conta): ?array {
    if (!is_array($s)) return null;
    $p = nd_props(nd_spec_sezione(), $s);
    $n = count(ND_LAYOUT[$p['layout']][1]);
    $col = [];
    $in = is_array($s['colonne'] ?? null) ? array_values($s['colonne']) : [];
    for ($i = 0; $i < $n; $i++) {
        $col[$i] = [];
        foreach (is_array($in[$i] ?? null) ? array_slice($in[$i], 0, 60) : [] as $b) {
            if ($conta >= ND_MAX_BLOCCHI) break;
            if ($b = nd_blocco($b)) { $col[$i][] = $b; $conta++; }
        }
    }
    return ['id' => nd_id($s['id'] ?? null)] + $p + ['salvata' => max(0, (int)(is_numeric($s['salvata'] ?? null) ? $s['salvata'] : 0)), 'colonne' => $col];
}

/** Il design intero, pulito con gli elenchi chiusi. */
function nd_pulisci(array $d): array {
    $conta = 0;
    $sez = [];
    foreach (is_array($d['sezioni'] ?? null) ? array_slice(array_values($d['sezioni']), 0, ND_MAX_SEZIONI) : [] as $s) if ($s = nd_sezione($s, $conta)) $sez[] = $s;
    return ['v' => 1, 'stile' => nd_props(nd_spec_stile(), $d['stile'] ?? []), 'sezioni' => $sez];
}

/** Un blocco nuovo con i valori di partenza (più quelli dati). */
function nd_nuovo(string $t, array $p = []): array {
    return nd_blocco(['t' => $t] + $p) ?? [];
}

/** Una sezione nuova. */
function nd_nuova_sezione(array $colonne, array $p = []): array {
    $conta = 0;
    $layout = $p['layout'] ?? ['1' => '100', '2' => '50-50', '3' => '33-33-33'][(string)count($colonne)] ?? '100';
    return nd_sezione(['layout' => $layout, 'colonne' => $colonne] + $p, $conta);
}

/** Il design di partenza di un'email nuova: un titolo, un testo, un pulsante, il piede. */
function nd_design_vuoto(): array {
    return nd_pulisci(['sezioni' => [
        nd_nuova_sezione([[nd_nuovo('titolo'), nd_nuovo('testo'), nd_nuovo('pulsante', ['link' => cms_url_sito()])]], ['spazio' => 32]),
        nd_nuova_sezione([[nd_nuovo('piede')]], ['fondo' => '#f9fafb', 'spazio' => 20]),
    ]]);
}

/** C'è qualcosa da leggere (un blocco visibile che non sia piede, spaziatore o divisore)? */
function nd_ha_contenuto(?array $d): bool {
    foreach ($d['sezioni'] ?? [] as $s) {
        if ($s['nascosta']) continue;
        foreach ($s['colonne'] as $c) foreach ($c as $b) if (!in_array($b['t'], ['piede', 'spaziatore', 'divisore'], true)) return true;
    }
    return false;
}

// ------------------------------------------------------------
// Da JSON a HTML per la posta
// ------------------------------------------------------------

function nd_font(string $k): string {
    return h((ND_FONT[$k] ?? ND_FONT['arial'])[1]);
}

/** L'indirizzo intero di un file degli asset del sito (le icone dei social). */
function nd_asset(string $f): string {
    return nd_origine() . cms_base() . '/sito/assets/' . $f;
}

/** La miniatura di un video di YouTube: [indirizzo, larghezza, altezza], o null. */
function nd_youtube(string $u): ?array {
    if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $u, $m)) return ['https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg', 480, 360];
    return null;
}

/** Il testo con la formattazione, con gli stili scritti su ogni elemento (molti programmi di posta ignorano i fogli). */
function nd_html_stili(string $html, string $font, int $dim, string $colore, string $link, string $allinea): string {
    $base = 'font-family:' . $font . ';font-size:' . $dim . 'px;line-height:1.6;color:' . $colore . ';text-align:' . $allinea;
    return strtr($html, [
        '<p>' => '<p style="margin:0 0 12px;' . $base . '">',
        '<ul>' => '<ul style="margin:0 0 12px;padding-left:24px;' . $base . '">',
        '<ol>' => '<ol style="margin:0 0 12px;padding-left:24px;' . $base . '">',
        '<li>' => '<li style="margin:0 0 6px">',
        '<a href=' => '<a target="_blank" style="color:' . $link . ';text-decoration:underline" href=',
    ]);
}

/** L'ultimo paragrafo senza il margine sotto: lo spazio fra i blocchi lo decide il blocco. */
function nd_ultimo_p(string $h): string {
    return (string)preg_replace('~ style="margin:0 0 12px;([^"]*)">((?:(?!<p ).)*)</p>$~s', ' style="margin:0;$1">$2</p>', $h);
}

/** Il contenitore di ogni blocco: una riga di tabella con il suo spazio sotto e l'allineamento. */
function nd_cella(string $dentro, int $spazio, string $allinea = 'left'): string {
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="' . $allinea . '" style="padding:0 0 ' . $spazio . 'px;text-align:' . $allinea . '">' . $dentro . '</td></tr></table>';
}

/** Un'immagine con le misure: larghezza in px dalla colonna, altezza dalle proporzioni (se note). */
function nd_immagine(string $src, string $alt, int $larg, int $w, int $h, int $raggio, string $allinea): string {
    $alto = $w > 0 && $h > 0 ? (int)round($larg * $h / $w) : 0;
    $margine = $allinea === 'center' ? '0 auto' : ($allinea === 'right' ? '0 0 0 auto' : '0');
    return '<img class="nd-img" src="' . h($src) . '" alt="' . h($alt) . '" width="' . $larg . '"' . ($alto ? ' height="' . $alto . '"' : '')
        . ' style="display:block;width:100%;max-width:' . $larg . 'px;height:auto;border:0;outline:none;text-decoration:none;margin:' . $margine . ($raggio ? ';border-radius:' . $raggio . 'px' : '') . '">';
}

/** Un blocco in HTML per la posta. $larg: la larghezza utile della colonna in px. */
function nd_blocco_html(array $b, array $st, int $larg, array $ctx): string {
    $font = nd_font($st['font']);
    $col = fn($k) => $b[$k] !== '' ? $b[$k] : $st['testo'];
    switch ($b['t']) {
        case 'titolo':
            if ($b['testo'] === '') return '';
            $px = ['h1' => 30, 'h2' => 24, 'h3' => 19][$b['livello']];
            return nd_cella('<' . $b['livello'] . ' style="margin:0;font-family:' . nd_font($st['font_titoli']) . ';font-size:' . $px . 'px;line-height:1.25;font-weight:700;color:' . ($b['colore'] ?: $st['titoli']) . ';text-align:' . $b['allinea'] . '">'
                . h($b['testo']) . '</' . $b['livello'] . '>', $b['spazio'], $b['allinea']);
        case 'testo':
            if ($b['html'] === '') return '';
            return nd_cella(nd_ultimo_p(nd_html_stili($b['html'], $font, $b['dim'] !== '' ? (int)$b['dim'] : $st['dim'], $col('colore'), $st['link'], $b['allinea'])), $b['spazio'], $b['allinea']);
        case 'immagine':
            if ($b['src'] === '') return '';
            $img = nd_immagine($b['src'], $b['alt'], (int)round($larg * $b['larghezza'] / 100), $b['w'], $b['h'], $b['raggio'], $b['allinea']);
            if ($b['link'] !== '') $img = '<a href="' . h($b['link']) . '" target="_blank" style="text-decoration:none">' . $img . '</a>';
            return nd_cella($img, $b['spazio'], $b['allinea']);
        case 'pulsante':
            if ($b['testo'] === '' || $b['link'] === '') return '';
            $fondo = $b['fondo'] ?: $st['btn_fondo'];
            $testo = $b['colore'] ?: $st['btn_testo'];
            $r = $b['raggio'] !== '' ? (int)$b['raggio'] : $st['btn_raggio'];
            $a = '<a href="' . h($b['link']) . '" target="_blank" style="display:' . ($b['pieno'] ? 'block' : 'inline-block') . ';padding:14px 28px;font-family:' . $font . ';font-size:' . $st['dim'] . 'px;line-height:1.2;font-weight:700;color:' . $testo . ';text-decoration:none;border-radius:' . $r . 'px;text-align:center">' . h($b['testo']) . '</a>';
            $t = '<table role="presentation"' . ($b['pieno'] ? ' width="100%"' : '') . ' align="' . $b['allinea'] . '" cellpadding="0" cellspacing="0" border="0" style="margin:' . ($b['allinea'] === 'center' ? '0 auto' : ($b['allinea'] === 'right' ? '0 0 0 auto' : '0')) . '"><tr>'
                . '<td align="center" bgcolor="' . $fondo . '" style="background:' . $fondo . ';border-radius:' . $r . 'px;mso-padding-alt:14px 28px">' . $a . '</td></tr></table>';
            return nd_cella($t, $b['spazio'], $b['allinea']);
        case 'divisore':
            $t = '<table role="presentation" width="' . $b['larghezza'] . '%" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto"><tr><td style="border-top:' . $b['spessore'] . 'px ' . $b['stile'] . ' ' . $b['colore'] . ';font-size:1px;line-height:1px;height:1px">&nbsp;</td></tr></table>';
            return nd_cella($t, $b['spazio'], 'center');
        case 'spaziatore':
            return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="' . $b['altezza'] . '" style="height:' . $b['altezza'] . 'px;font-size:1px;line-height:1px">&nbsp;</td></tr></table>';
        case 'social':
            $td = '';
            foreach ($b['reti'] as $r) {
                if ($r['url'] === '') continue;
                $td .= '<td style="padding:0 5px"><a href="' . h($r['url']) . '" target="_blank" style="text-decoration:none"><img src="' . h(nd_asset('email/' . $r['r'] . '-' . $b['stile'] . '.png')) . '" alt="' . h(ND_RETI[$r['r']]) . '" width="' . $b['dim'] . '" height="' . $b['dim'] . '" style="display:block;width:' . $b['dim'] . 'px;height:' . $b['dim'] . 'px;border:0"></a></td>';
            }
            if ($td === '') return '';
            return nd_cella('<table role="presentation" align="' . $b['allinea'] . '" cellpadding="0" cellspacing="0" border="0" style="margin:' . ($b['allinea'] === 'center' ? '0 auto' : ($b['allinea'] === 'right' ? '0 0 0 auto' : '0')) . '"><tr>' . $td . '</tr></table>', $b['spazio'], $b['allinea']);
        case 'video':
            if ($b['url'] === '') return '';
            [$src, $w, $h] = $b['src'] !== '' ? [$b['src'], $b['w'], $b['h']] : (nd_youtube($b['url']) ?? ['', 0, 0]);
            $out = $src !== '' ? '<a href="' . h($b['url']) . '" target="_blank" style="text-decoration:none">' . nd_immagine($src, $b['alt'], (int)round($larg * $b['larghezza'] / 100), $w, $h, 0, $b['allinea']) . '</a>' : '';
            if ($b['testo'] !== '') $out .= '<p style="margin:' . ($out !== '' ? '10px' : '0') . ' 0 0;font-family:' . $font . ';font-size:' . $st['dim'] . 'px;text-align:' . $b['allinea'] . '"><a href="' . h($b['url']) . '" target="_blank" style="color:' . $st['link'] . ';font-weight:700;text-decoration:none">&#9654; ' . h($b['testo']) . '</a></p>';
            return nd_cella($out, $b['spazio'], $b['allinea']);
        case 'piede':
            $h = nd_html_stili($b['html'], $font, $b['dim'], $b['colore'], $b['colore'], $b['allinea']);
            if ($b['indirizzo'] && ($ctx['indirizzo'] ?? '') !== '') $h .= '<p style="margin:0 0 12px;font-family:' . $font . ';font-size:' . $b['dim'] . 'px;line-height:1.6;color:' . $b['colore'] . ';text-align:' . $b['allinea'] . '">' . str_replace("\n", '<br>', h($ctx['indirizzo'])) . '</p>';
            return $h === '' ? '' : nd_cella(nd_ultimo_p($h), $b['spazio'], $b['allinea']);
    }
    return '';
}

/**
 * Il design in HTML per la posta. $ctx: oggetto, anteprima (il testo di anteprima già personalizzato), web (link della
 * versione web), indirizzo (dell'azienda). I link speciali ({{ unsubscribe }}...) e i segnaposti restano da sostituire;
 * dove andrebbe il piede obbligatorio c'è il segno <!--nd-piede-->.
 */
function nd_html(array $d, array $ctx = []): string {
    $st = $d['stile'];
    $W = $st['larghezza'];
    $font = nd_font($st['font']);
    $righe = '';
    foreach ($d['sezioni'] as $s) {
        if ($s['nascosta']) continue;
        $quote = ND_LAYOUT[$s['layout']][1];
        $n = count($quote);
        $dentro = $W - 2 * $s['spazio_x'];
        $td = '';
        foreach ($s['colonne'] as $i => $blocchi) {
            $cw = (int)round($dentro * $quote[$i] / 100);
            $pl = $i > 0 ? 8 : 0; $pr = $i < $n - 1 ? 8 : 0;
            $html = '';
            foreach ($blocchi as $b) $html .= nd_blocco_html($b, $st, $cw - $pl - $pr, $ctx);
            $td .= '<td class="nd-col" width="' . $cw . '" valign="top" style="width:' . $cw . 'px;vertical-align:top;padding:0 ' . $pr . 'px 0 ' . $pl . 'px">' . ($html !== '' ? $html : '&nbsp;') . '</td>';
        }
        $fondo = $s['fondo'] !== '' ? $s['fondo'] : '';
        $righe .= '<tr><td class="nd-pad"' . ($fondo ? ' bgcolor="' . $fondo . '"' : '') . ' style="padding:' . $s['spazio'] . 'px ' . $s['spazio_x'] . 'px' . ($fondo ? ';background:' . $fondo : '') . '">'
            . '<table role="presentation" class="' . ($s['tel'] === 'impila' && $n > 1 ? 'nd-impila' : 'nd-affianca') . '" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $td . '</tr></table></td></tr>';
    }
    $anteprima = (string)($ctx['anteprima'] ?? '') !== '' ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">' . h($ctx['anteprima']) . str_repeat('&#8204;&nbsp;', 40) . '</div>' : '';
    $web = (string)($ctx['web'] ?? '') !== '' ? '<p style="margin:0 0 10px;font-family:' . $font . ';font-size:12px;color:#6b7280;text-align:center"><a href="' . h($ctx['web']) . '" style="color:#6b7280">Vedi questa email nel browser</a></p>' : '';
    $r = $st['raggio'];
    return '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><title>' . h((string)($ctx['oggetto'] ?? '')) . '</title>'
        . '<style>@media only screen and (max-width:' . ($W + 20) . 'px){.nd-corpo{width:100%!important;max-width:100%!important}.nd-pad{padding-left:16px!important;padding-right:16px!important}'
        . '.nd-impila .nd-col{display:block!important;width:100%!important;max-width:100%!important;padding-left:0!important;padding-right:0!important}}</style></head>'
        . '<body style="margin:0;padding:0;background:' . $st['fondo'] . ';font-family:' . $font . ';color:' . $st['testo'] . '">' . $anteprima
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $st['fondo'] . '" style="background:' . $st['fondo'] . '"><tr><td align="center" style="padding:24px 12px">' . $web
        . '<table role="presentation" class="nd-corpo" width="' . $W . '" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $st['corpo'] . '" style="width:' . $W . 'px;max-width:' . $W . 'px;background:' . $st['corpo'] . ($r ? ';border-radius:' . $r . 'px;overflow:hidden' : '') . '">'
        . $righe . '<!--nd-piede--></table></td></tr></table></body></html>';
}

/**
 * Il piede che non si può togliere: se nell'email non c'è il link per uscire (o l'indirizzo dell'azienda, quando è scritto),
 * una riga in fondo li rimette. Poi il segno sparisce.
 */
function nd_piede_obbligatorio(string $html, string $uscita, string $indirizzo, array $st): string {
    $manca = [];
    if ($uscita !== '' && !str_contains($html, 'href="' . h($uscita) . '"')) $manca[] = '<a href="' . h($uscita) . '" style="color:#6b7280;text-decoration:underline">Esci dalla newsletter</a>, basta un clic.';
    $prima = trim(strtok($indirizzo, "\n") ?: '');
    if ($prima !== '' && !str_contains($html, h($prima))) $manca[] = str_replace("\n", '<br>', h(trim($indirizzo)));
    $riga = $manca ? '<tr><td class="nd-pad" style="padding:18px 30px 22px;font-family:' . nd_font($st['font']) . ';font-size:12px;line-height:1.6;color:#6b7280;text-align:center">' . implode('<br>', $manca) . '</td></tr>' : '';
    return str_replace('<!--nd-piede-->', $riga, $html);
}

/** L'email di una campagna (o di un modello in anteprima) fatta con l'editor a blocchi. Chiamata da newsletter_email(). */
function nd_email_campagna(array $c, array $iscritto, string $uscita, array $x, string $oggetto): string {
    $d = nd_design((string)($c['design'] ?? '')) ?? nd_design_vuoto();
    $ant = (string)($c['anteprima'] ?? '') !== '' ? newsletter_4byte_fuori(newsletter_personalizza((string)$c['anteprima'], $iscritto, false)) : '';
    $h = nd_html($d, ['oggetto' => $oggetto, 'anteprima' => $ant, 'web' => $x['web'] ?? '', 'indirizzo' => $x['indirizzo'] ?? '']);
    $h = str_replace('{{ doubleoptin }}', h(MODULI_URL . NEWSLETTER_PAGINA), $h);
    $h = nl_camp_link_speciali($h, $uscita, $x + ['web' => '', 'pref_sempre' => ''], true);
    $h = newsletter_personalizza($h, $iscritto, true);
    return nd_piede_obbligatorio($h, $uscita, (string)($x['indirizzo'] ?? ''), $d['stile']);
}

// ------------------------------------------------------------
// Modelli
// ------------------------------------------------------------

function nd_modelli(bool $soloAttivi = false): array {
    nd_schema();
    return db()->query('SELECT * FROM ' . tbl('newsletter_modelli') . ($soloAttivi ? ' WHERE attivo = 1' : '') . ' ORDER BY aggiornato DESC, id DESC')->fetchAll();
}

function nd_modello(int $id): ?array {
    nd_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_modelli') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function nd_nome(mixed $n): string {
    return newsletter_4byte_dentro(nd_testo($n, 150));
}

/** Crea un modello. Ritorna l'id, o il messaggio d'errore. */
function nd_modello_crea(string $nome, ?array $design, array $altro = []) {
    nd_schema();
    $nome = nd_nome($nome);
    if ($nome === '') return 'Scrivi il nome del modello: serve a te per ritrovarlo.';
    $editor = ($altro['editor'] ?? 'blocchi') === 'html' ? 'html' : 'blocchi';
    $tag = preg_match('/^[a-z0-9_-]{0,50}$/', (string)($altro['tag'] ?? '')) ? (string)($altro['tag'] ?? '') : '';
    $ora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO ' . tbl('newsletter_modelli') . ' (nome, editor, design, html, oggetto, attivo, tag, origine, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$nome, $editor, nd_json($design ?? nd_design_vuoto()), $editor === 'html' ? newsletter_4byte_dentro(mb_substr((string)($altro['html'] ?? ''), 0, 1500000)) : null,
            nd_nome($altro['oggetto'] ?? ''), empty($altro['attivo']) ? 0 : 1, $tag, preg_replace('/[^a-z0-9-]/', '', (string)($altro['origine'] ?? '')), $ora, $ora]);
    return (int)db()->lastInsertId();
}

/** Cambia una o più colonne di un modello (nome, design, attivo, tag, oggetto). */
function nd_modello_cambia(int $id, array $set): bool {
    $ok = ['nome', 'design', 'attivo', 'tag', 'oggetto'];
    $sql = []; $val = [];
    foreach ($set as $k => $v) if (in_array($k, $ok, true)) { $sql[] = "`$k` = ?"; $val[] = $v; }
    if (!$sql) return false;
    $val[] = $id;
    $st = db()->prepare('UPDATE ' . tbl('newsletter_modelli') . ' SET ' . implode(', ', $sql) . ', aggiornato = NOW() WHERE id = ?');
    $st->execute($val);
    return $st->rowCount() > 0;
}

function nd_modello_duplica(int $id): int {
    $m = nd_modello($id);
    if (!$m) return 0;
    $r = nd_modello_crea('Copia di ' . newsletter_4byte_fuori((string)$m['nome']), nd_design((string)$m['design']), ['editor' => $m['editor'], 'html' => newsletter_4byte_fuori((string)$m['html']), 'tag' => $m['tag'], 'oggetto' => newsletter_4byte_fuori((string)$m['oggetto'])]);
    return is_int($r) ? $r : 0;
}

function nd_modello_elimina(int $id): bool {
    nd_schema();
    $st = db()->prepare('DELETE FROM ' . tbl('newsletter_modelli') . ' WHERE id = ?');
    $st->execute([$id]);
    if ((int)get_setting('newsletter_doi_modello', '0') === $id) save_setting('newsletter_doi_modello', '0');
    return $st->rowCount() > 0;
}

/** I modelli pronti della galleria (includes/newsletter-modelli/*.json): slug => [nome, descrizione, oggetto, tag, design]. */
function nd_galleria(): array {
    static $g = null;
    if ($g !== null) return $g;
    $g = [];
    foreach (glob(__DIR__ . '/newsletter-modelli/*.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (!is_array($j) || !($d = nd_design($j['design'] ?? null))) continue;
        $g[basename($f, '.json')] = ['nome' => nd_testo($j['nome'] ?? '', 150), 'descrizione' => nd_testo($j['descrizione'] ?? '', 300),
            'oggetto' => nd_testo($j['oggetto'] ?? '', 200), 'tag' => ($j['tag'] ?? '') === 'optin' ? 'optin' : '', 'design' => $d];
    }
    return $g;
}

/** Porta un modello della galleria fra i tuoi modelli (attivo). Ritorna l'id o 0. */
function nd_galleria_copia(string $slug): int {
    $g = nd_galleria()[$slug] ?? null;
    if (!$g) return 0;
    $r = nd_modello_crea($g['nome'], $g['design'], ['attivo' => 1, 'tag' => $g['tag'], 'oggetto' => $g['oggetto'], 'origine' => $slug]);
    return is_int($r) ? $r : 0;
}

/** Il design (e l'editor) di un modello va nella campagna. Solo nelle campagne che si possono ancora cambiare. */
function nd_campagna_da_modello(int $campagna, ?array $m, ?array $design = null): string {
    nd_schema();
    $c = newsletter_campagna($campagna);
    if (!$c || !newsletter_campagna_modificabile($c)) return 'Questa campagna non si può più modificare: è già partita.';
    if ($m && $m['editor'] === 'html') {
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET editor = 'html', html = ?, modello_id = ?, aggiornato = NOW() WHERE id = ?")->execute([(string)$m['html'], (int)$m['id'], $campagna]);
        return '';
    }
    $d = $design ?? ($m ? nd_design((string)$m['design']) : null);
    if (!$d) return 'Il modello non ha un design da usare.';
    db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET editor = 'blocchi', design = ?, modello_id = ?, aggiornato = NOW() WHERE id = ?")->execute([nd_json($d), (int)($m['id'] ?? 0), $campagna]);
    return '';
}

// ------------------------------------------------------------
// Sezioni salvate
// ------------------------------------------------------------

function nd_sezioni(): array {
    nd_schema();
    $out = [];
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_sezioni_salvate') . ' ORDER BY nome, id')->fetchAll() as $r) {
        $conta = 0;
        $s = nd_sezione(json_decode((string)$r['json'], true, 16), $conta);
        if ($s) $out[] = ['id' => (int)$r['id'], 'nome' => newsletter_4byte_fuori((string)$r['nome']), 'sincronizzata' => (bool)$r['sincronizzata'], 'sezione' => $s];
    }
    return $out;
}

/** Salva una sezione per riusarla. Ritorna l'id, o il messaggio d'errore. */
function nd_sezione_crea(string $nome, mixed $sez, bool $sync) {
    nd_schema();
    $nome = nd_nome($nome);
    if ($nome === '') return 'Scrivi un nome per la sezione: lo ritrovi in Salvati.';
    $conta = 0;
    $s = nd_sezione($sez, $conta);
    if (!$s) return 'La sezione non è valida.';
    $s['salvata'] = 0;
    $ora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO ' . tbl('newsletter_sezioni_salvate') . ' (nome, json, sincronizzata, creato, aggiornato) VALUES (?,?,?,?,?)')->execute([$nome, nd_json($s), $sync ? 1 : 0, $ora, $ora]);
    return (int)db()->lastInsertId();
}

/**
 * "Modifica in tutte le email": la sezione salvata (sincronizzata) prende il contenuto nuovo, e così ogni campagna in bozza e
 * ogni modello che la usa. Le campagne programmate, partite o inviate restano come sono. Ritorna [campagne, modelli] cambiati,
 * o il messaggio d'errore.
 */
function nd_sezione_aggiorna(int $id, mixed $sez) {
    nd_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_sezioni_salvate') . ' WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) return 'Questa sezione salvata non c\'è più.';
    if (!(int)$r['sincronizzata']) return 'Questa sezione non è sincronizzata: le modifiche restano in questa email.';
    $conta = 0;
    $s = nd_sezione($sez, $conta);
    if (!$s) return 'La sezione non è valida.';
    $s['salvata'] = 0;
    db()->prepare('UPDATE ' . tbl('newsletter_sezioni_salvate') . ' SET json = ?, aggiornato = NOW() WHERE id = ?')->execute([nd_json($s), $id]);
    $cerca = '%"salvata":' . $id . ',%';
    $n = [0, 0];
    foreach ([['newsletter_campagne', " AND editor = 'blocchi' AND stato = 'bozza'"], ['newsletter_modelli', " AND editor = 'blocchi'"]] as $k => [$tab, $dove]) {
        $q = db()->prepare('SELECT id, design FROM ' . tbl($tab) . " WHERE design LIKE ?$dove");
        $q->execute([$cerca]);
        foreach ($q->fetchAll() as $riga) {
            $d = nd_design((string)$riga['design']);
            if (!$d) continue;
            $cambiata = false;
            foreach ($d['sezioni'] as $i => $x) if ($x['salvata'] === $id) { $d['sezioni'][$i] = ['id' => $x['id'], 'salvata' => $id] + $s; $cambiata = true; }
            if (!$cambiata) continue;
            db()->prepare('UPDATE ' . tbl($tab) . ' SET design = ?, aggiornato = NOW() WHERE id = ?')->execute([nd_json($d), (int)$riga['id']]);
            $n[$k]++;
        }
    }
    return $n;
}

function nd_sezione_elimina(int $id): bool {
    nd_schema();
    $st = db()->prepare('DELETE FROM ' . tbl('newsletter_sezioni_salvate') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

// ------------------------------------------------------------
// Il modello dell'email di conferma (doppio opt-in)
// ------------------------------------------------------------

/** I modelli attivi con il tag optin, che si possono scegliere per l'email di conferma. */
function nd_modelli_optin(): array {
    return array_values(array_filter(nd_modelli(true), fn($m) => $m['tag'] === 'optin' && $m['editor'] === 'blocchi'));
}

/**
 * L'email di conferma fatta con un modello (Modelli > Email di conferma dell'iscrizione). null quando si usa quella
 * predefinita (le parti di Newsletter > Impostazioni): è la scelta di partenza, e chi non cambia niente riceve l'email di sempre.
 * Il pulsante con il link {{ doubleoptin }} diventa il link di conferma; se il modello non lo ha, si aggiunge in fondo.
 */
function nd_doi_email(string $link, string $nome, string $email): ?array {
    $id = (int)get_setting('newsletter_doi_modello', '0');
    if ($id <= 0) return null;
    $m = nd_modello($id);
    if (!$m || !(int)$m['attivo'] || $m['tag'] !== 'optin' || !($d = nd_design((string)$m['design']))) return null;
    $chi = ['id' => 0, 'nome' => $nome, 'email' => $email];
    $oggetto = trim(newsletter_4byte_fuori(newsletter_personalizza((string)$m['oggetto'], $chi, false))) ?: 'Conferma l\'iscrizione';
    $h = nd_html($d, ['oggetto' => $oggetto]);
    if (!str_contains($h, '{{ doubleoptin }}')) {
        $h = str_replace('<!--nd-piede-->', '<tr><td class="nd-pad" style="padding:0 30px 28px">' . nd_blocco_html(nd_nuovo('pulsante', ['testo' => 'Confermo l\'iscrizione', 'link' => '{{ doubleoptin }}']), $d['stile'], 540, []) . '</td></tr><!--nd-piede-->', $h);
    }
    $h = str_replace('{{ doubleoptin }}', h($link), $h);
    $h = nl_camp_link_speciali($h, MODULI_URL . NEWSLETTER_PAGINA, ['web' => '', 'pref_sempre' => MODULI_URL . NEWSLETTER_PAGINA], true);
    $h = newsletter_personalizza($h, $chi, true);
    return [str_replace(["\r", "\n"], ' ', $oggetto), str_replace('<!--nd-piede-->', '', $h)];
}
