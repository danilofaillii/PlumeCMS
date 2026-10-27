<?php
/**
 * I widget in piu dell'editor (quelli di Elementor free e Pro che mancavano): icona, riquadri, elenchi con icone,
 * valutazione, contatore, barra, testimonianze, galleria, caroselli, fisarmonica, schede, social, mappa, avviso,
 * conto alla rovescia, invito all'azione, tabelle e listini prezzi, flip box, titolo animato, condividi, sommario,
 * citazione, griglia di articoli, torna su.
 *
 * Contratto: audit3/CONTROLLI-CONTRATTO.md. Ogni widget si registra con editor_registra_widget(tipo, definizione, disegna).
 * HTML con classi ew-*, aspetto in sito/assets/widget.css, comportamento in sito/assets/widget.js (caricati solo
 * quando la pagina contiene uno di questi widget). I controlli di stile scrivono variabili CSS (--ew-*) sul
 * contenitore del widget tramite `selettore` + `proprieta`: il foglio di stile le legge con un valore di partenza,
 * cosi un controllo vuoto lascia l'aspetto di oggi.
 *
 * Finche il framework dei controlli di gruppo non c'e (function_exists('editor_css_gruppo') falso) il file traduce
 * i tipi nuovi in tipi semplici e genera da solo il CSS dei controlli semplici con `selettore`.
 */

require_once __DIR__ . '/icone.php';

// ------------------------------------------------------------
// Registro di ripiego (vale finche il framework non definisce le stesse funzioni)
// ------------------------------------------------------------

if (!function_exists('editor_registra_widget')) {
    function editor_registra_widget(string $tipo, array $definizione, callable $disegna): void {
        $GLOBALS['ewx_registro'][$tipo] = ['def' => $definizione, 'disegna' => $disegna];
    }
}
if (!function_exists('editor_registra_asset')) {
    function editor_registra_asset(string $tipo, array $asset): void {
        $GLOBALS['ewx_asset'][$tipo] = $asset;
    }
}
if (!function_exists('editor_asset_pagina')) {
    /** I file di sito/assets/ da caricare per questo HTML (cerca le classi ed-w-TIPO dei widget registrati). */
    function editor_asset_pagina(string $html): array {
        $out = [];
        foreach ($GLOBALS['ewx_asset'] ?? [] as $tipo => $a) {
            if (str_contains($html, 'ed-w-' . $tipo)) foreach ($a as $f) $out[$f] = $f;
        }
        return array_values($out);
    }
}
if (!function_exists('editor_icona')) {
    function editor_icona(string $nome, string $classe = ''): string { return icona_svg($nome, $classe); }
}

/** Il framework dei controlli di gruppo c'e? */
function ewx_fw(): bool {
    return function_exists('editor_css_gruppo');
}

function ewx_tipi(): array {
    return $GLOBALS['ewx_tipi'] ?? [];
}

/** Le definizioni dei widget extra, per editor_definizioni() (ripiego: con il framework il registro e il suo). */
function editor_extra_definizioni(): array {
    $out = [];
    foreach ($GLOBALS['ewx_registro'] ?? [] as $tipo => $r) $out[$tipo] = $r['def'];
    return $out;
}

function editor_extra_e(string $tipo): bool {
    return isset($GLOBALS['ewx_registro'][$tipo]);
}

/** Chiamata dal disegno (editor.php) prima dell'HTML: prepara il contesto e, senza framework, scrive il CSS dei controlli semplici. */
function editor_extra_css(string $tipo, array $v, string $sel, array &$css, bool $anteprima = false, string $id = ''): void {
    if (!editor_extra_e($tipo)) return;
    $GLOBALS['ewx_ctx'] = ['id' => $id ?: ltrim($sel, '.ed-'), 'sel' => $sel, 'anteprima' => $anteprima, 'percorso' => $GLOBALS['editor_percorso'] ?? '/'];
    $GLOBALS['ewx_ctx']['css'] = &$css;
    if (ewx_fw()) return;
    ewx_css_semplice($GLOBALS['ewx_registro'][$tipo]['def']['controlli'], $v, $sel, $css);
}

function editor_extra_disegna(string $tipo, array $v): string {
    if (!editor_extra_e($tipo)) return '';
    if (empty($GLOBALS['ewx_ctx'])) { $css = []; $GLOBALS['ewx_ctx'] = ['id' => 'x', 'sel' => '.ed-x', 'anteprima' => false, 'percorso' => '/']; $GLOBALS['ewx_ctx']['css'] = &$css; }
    return ($GLOBALS['ewx_registro'][$tipo]['disegna'])($v, $GLOBALS['ewx_ctx']);
}

/** CSS di ripiego per i controlli semplici con selettore (colore, numero, scelta, interruttore). */
function ewx_css_semplice(array $controlli, array $v, string $sel, array &$css): void {
    foreach ($controlli as $c) {
        if (!isset($c['selettore']) || empty($c['proprieta']) || !array_key_exists($c['nome'], $v)) continue;
        $val = $v[$c['nome']];
        if (is_array($val) && array_key_exists('normale', $val)) $val = $val['normale'];
        $prop = $c['proprieta'];
        $fmt = null;
        switch ($c['tipo']) {
            case 'colore': $fmt = fn($x) => ($x === '' || $x === null) ? null : ewx_colore($x); break;
            case 'numero':
                $u = is_string($c['unita'] ?? null) ? $c['unita'] : 'px';
                $fmt = fn($x) => ($x === '' || $x === null || !is_numeric($x)) ? null : (isset($c['valore_css']) ? str_replace('{{v}}', $x . $u, $c['valore_css']) : $x . $u);
                break;
            case 'scelta':
                $fmt = fn($x) => ($x === '' || $x === null) ? null : (isset($c['mappa']) ? ($c['mappa'][$x] ?? null) : $x);
                break;
            case 'interruttore':
                $fmt = fn($x) => $x ? ($c['acceso'] ?? null) : ($c['spento'] ?? null);
                break;
            default: continue 2;
        }
        foreach (explode(',', $c['selettore']) as $pezzo) {
            editor_css_aggiungi($css, $sel . $pezzo, $prop, $val, $fmt);
        }
    }
}

// ------------------------------------------------------------
// Dichiarazione dei controlli: ex() = ec() con la traduzione di ripiego
// ------------------------------------------------------------

function ewx_opzioni_icone(): array {
    static $o = null;
    if ($o !== null) return $o;
    $cat = icone_categorie();
    $o = ['' => 'Nessuna'];
    foreach (icone_elenco() as $id => $r) $o[$id] = $r['nome'] . ' (' . ($cat[$r['cat']] ?? $r['cat']) . ')';
    return $o;
}

/** Un controllo del contratto; senza framework diventa un tipo semplice oppure sparisce (null). */
function ex(string $nome, string $tipo, string $etichetta, $pred = '', string $scheda = 'contenuto', array $extra = []): ?array {
    if (ewx_fw()) return ec($nome, $tipo, $etichetta, $pred, $scheda, $extra);
    unset($extra['stati']);
    switch ($tipo) {
        case 'intestazione': case 'tipografia': case 'sfondo': case 'bordo': case 'ombra': case 'ombra_testo': case 'dimensioni':
            return null;
        case 'misura':
            $iv = $extra['intervalli']['px'] ?? [$extra['min'] ?? 0, $extra['max'] ?? 500, $extra['passo'] ?? 1];
            $num = function ($p) { return is_array($p) && array_key_exists('v', $p) ? $p['v'] : $p; };
            if (is_array($pred) && array_key_exists('d', $pred)) $pred = array_map($num, $pred); else $pred = $num($pred);
            return ec($nome, 'numero', $etichetta . ' (px)', $pred, $scheda, ['min' => $iv[0], 'max' => $iv[1], 'unita' => 'px'] + $extra);
        case 'allineamento':
            $o = ['' => 'Come il sito', 'left' => 'Sinistra', 'center' => 'Centro', 'right' => 'Destra'];
            if (!empty($extra['giustifica'])) $o['justify'] = 'Giustificato';
            if (!empty($extra['flex'])) $o = ['' => 'Come il sito', 'flex-start' => 'Inizio', 'center' => 'Centro', 'flex-end' => 'Fine'];
            return ec($nome, 'scelta', $etichetta, $pred, $scheda, ['opzioni' => $o] + $extra);
        case 'icona':
            return ec($nome, 'scelta', $etichetta, $pred, $scheda, ['opzioni' => ewx_opzioni_icone()] + $extra);
        case 'galleria':
            return ec($nome, 'ripetitore', $etichetta, $pred, $scheda, ['campi' => [
                ec('immagine', 'immagine', 'Immagine', ''), ec('alt', 'testo', 'Testo alternativo', ''), ec('didascalia', 'testo', 'Didascalia', ''), ec('link', 'testo', 'Link (facoltativo)', ''),
            ], 'titolo_voce' => 'alt'] + $extra);
        case 'data_ora':
            return ec($nome, 'testo', $etichetta, $pred, $scheda, ['aiuto' => 'Formato: AAAA-MM-GG HH:MM'] + $extra);
    }
    return ec($nome, $tipo, $etichetta, $pred, $scheda, $extra);
}

/** Registra un widget extra: definizione con i controlli filtrati, asset del sito. */
function ewx_registra(string $tipo, string $etichetta, string $icona, string $gruppo, array $controlli, callable $disegna): void {
    $GLOBALS['ewx_tipi'][] = $tipo;
    editor_registra_widget($tipo, ['etichetta' => $etichetta, 'icona' => icona_svg($icona) ?: $icona, 'gruppo' => $gruppo, 'controlli' => array_values(array_filter($controlli))], $disegna);
    editor_registra_asset($tipo, ['css' => 'widget.css', 'js' => 'widget.js']);
}

// ---- scorciatoie per i controlli ricorrenti della scheda Stile
function ewx_var(string $nome, string $etichetta, string $var, string $sezione, $pred = '', array $extra = []): ?array {
    return ex($nome, 'misura', $etichetta, $pred, 'stile', ['selettore' => '', 'proprieta' => $var, 'sezione' => $sezione, 'responsive' => true, 'unita' => ['px', 'em', 'rem', '%'], 'intervalli' => ['px' => [0, 300, 1], 'em' => [0, 10, 0.1], 'rem' => [0, 10, 0.1], '%' => [0, 100, 1]]] + $extra);
}
function ewx_col(string $nome, string $etichetta, string $var, string $sezione, bool $stati = false, array $extra = []): ?array {
    return ex($nome, 'colore', $etichetta, '', 'stile', ['selettore' => '', 'proprieta' => $var, 'sezione' => $sezione, 'stati' => $stati] + $extra);
}
function ewx_tip(string $nome, string $etichetta, string $sel, string $sezione): ?array {
    return ex($nome, 'tipografia', $etichetta, [], 'stile', ['selettore' => $sel, 'sezione' => $sezione]);
}
function ewx_testo_stile(string $prefisso, string $etichetta, string $sel, string $var, bool $hover = false): array {
    return [ewx_col($prefisso . '_colore', 'Colore', $var, $etichetta, $hover), ewx_tip($prefisso . '_tipografia', 'Tipografia', $sel, $etichetta)];
}
function ewx_riquadro_stile(string $sel = '', string $sezione = 'Riquadro'): array {
    return [
        ex('riquadro_sfondo', 'sfondo', 'Sfondo', [], 'stile', ['selettore' => $sel, 'sezione' => $sezione, 'stati' => true]),
        ex('riquadro_bordo', 'bordo', 'Bordo', [], 'stile', ['selettore' => $sel, 'sezione' => $sezione, 'stati' => true]),
        ex('riquadro_ombra', 'ombra', 'Ombra', [], 'stile', ['selettore' => $sel, 'sezione' => $sezione, 'stati' => true]),
        ex('riquadro_padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => $sel, 'sezione' => $sezione, 'proprieta' => 'padding', 'responsive' => true]),
    ];
}
function ewx_allinea(string $nome = 'allineamento', string $sezione = 'Disposizione', $pred = ''): ?array {
    return ex($nome, 'allineamento', 'Allineamento', ['d' => $pred], 'contenuto', ['selettore' => '', 'proprieta' => 'text-align', 'responsive' => true, 'sezione' => $sezione]);
}
function ewx_colonne($pred = 3): ?array {
    return ec('colonne', 'numero', 'Colonne', ['d' => $pred, 't' => min($pred, 2), 'm' => 1], 'contenuto', ['min' => 1, 'max' => 6, 'responsive' => true, 'sezione' => 'Disposizione']);
}
function ewx_link(string $nome = 'link', string $etichetta = 'Link'): array {
    return ec($nome, 'link', $etichetta, ['url' => '', 'nuova' => false]);
}
function ewx_livello(string $pred = 'h3', string $etichetta = 'Livello del titolo'): array {
    return ec('livello', 'scelta', $etichetta, $pred, 'contenuto', ['opzioni' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'Nessun livello (div)']]);
}
function ewx_hover(string $nome = 'animazione_hover'): array {
    return ec($nome, 'scelta', 'Animazione al passaggio', '', 'stile', ['opzioni' => ['' => 'Nessuna', 'cresce' => 'Cresce', 'riduce' => 'Si riduce', 'pulsa' => 'Pulsa', 'spinge' => 'Spinge', 'galleggia' => 'Galleggia', 'affonda' => 'Affonda', 'ruota' => 'Ruota', 'vibra' => 'Vibra'], 'sezione' => 'Effetti']);
}
const EWX_SOCIAL = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'youtube' => 'YouTube', 'x-social' => 'X', 'telegram' => 'Telegram', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest', 'spotify' => 'Spotify', 'github' => 'GitHub', 'email' => 'Email', 'telefono' => 'Telefono', 'rss' => 'Feed RSS', 'globo' => 'Sito web'];

// ------------------------------------------------------------
// Lettura dei valori nel disegno
// ------------------------------------------------------------

/** Da "#fff" o "globale:primario" al CSS. */
function ewx_colore($x): string {
    $x = (string)$x;
    if (function_exists('editor_colore_css')) return (string)(editor_colore_css($x) ?? '');
    if (str_starts_with($x, 'globale:')) return 'var(--g-colore-' . preg_replace('/[^a-z0-9-]/', '', substr($x, 8)) . ')';
    return $x;
}

/** Un valore "misura" ({v,u}, responsive, numero o stringa) in CSS: "12px" oppure null. */
function ewx_mis($x, string $unita = 'px'): ?string {
    if (is_array($x) && array_key_exists('d', $x)) $x = editor_per($x, 'd');
    if (is_array($x) && array_key_exists('normale', $x)) $x = $x['normale'];
    if (is_array($x) && array_key_exists('v', $x)) { if ($x['v'] === '' || $x['v'] === null) return null; return $x['v'] . ($x['u'] ?: $unita); }
    if ($x === '' || $x === null || is_array($x)) return null;
    return is_numeric($x) ? $x . $unita : (string)$x;
}

/** Il numero di una misura (senza unita), per i calcoli. */
function ewx_num($x, float $pred = 0): float {
    if (is_array($x) && array_key_exists('d', $x)) $x = editor_per($x, 'd');
    if (is_array($x) && array_key_exists('v', $x)) $x = $x['v'];
    return is_numeric($x) ? (float)$x : $pred;
}

function ewx_str($x): string {
    if (is_array($x)) $x = $x['normale'] ?? ($x['d'] ?? '');
    return trim((string)$x);
}

function ewx_ic(string $id, string $classe = ''): string {
    return $id !== '' ? icona_svg($id, $classe) : '';
}

/** Apre un <a> se il link c'e, altrimenti il tag di ripiego. Ritorna [apertura, chiusura]. */
function ewx_a($link, string $classe, string $ripiego = 'div', string $extra = ''): array {
    $url = is_array($link) ? editor_link_sicuro((string)($link['url'] ?? '')) : editor_link_sicuro((string)$link);
    if ($url === '') return ["<$ripiego class=\"$classe\"$extra>", "</$ripiego>"];
    $nuova = is_array($link) && !empty($link['nuova']) ? ' target="_blank" rel="noopener"' : '';
    return ['<a class="' . $classe . '" href="' . h($url) . '"' . $nuova . $extra . '>', '</a>'];
}

function ewx_tag(array $v, string $pred = 'h3'): string {
    $l = (string)($v['livello'] ?? $pred);
    return in_array($l, ['h2', 'h3', 'h4', 'h5', 'div', 'p', 'span'], true) ? $l : $pred;
}

function ewx_img(string $src, string $alt, string $classe = '', string $extra = ''): string {
    return $src !== '' ? '<img class="' . $classe . '" src="' . h($src) . '" alt="' . h($alt) . '" loading="lazy"' . $extra . '>' : '';
}

function ewx_vuoto(string $msg): string {
    return '<div class="ed-vuoto">' . h($msg) . '</div>';
}

/** La griglia a colonne di un widget: scrive grid-template-columns per misura. */
function ewx_css_colonne(array $ctx, string $sel, $colonne, int $max = 6): void {
    $css = &$ctx['css'];
    editor_css_aggiungi($css, $ctx['sel'] . $sel, 'grid-template-columns', $colonne, fn($x) => 'repeat(' . max(1, min($max, (int)$x)) . ',minmax(0,1fr))');
}

/** Scrive una variabile CSS responsive sul contenitore (per i valori che il framework non copre). */
function ewx_css_var(array $ctx, string $var, $valore, ?callable $fmt = null): void {
    $css = &$ctx['css'];
    editor_css_aggiungi($css, $ctx['sel'], $var, $valore, $fmt ?? fn($x) => (is_numeric($x) ? (string)$x : null));
}

/** L'allineamento (left/center/right, responsive) anche come --ew-just per i contenitori flex del widget. */
function ewx_css_allinea(array $ctx, $allineamento): void {
    ewx_css_var($ctx, '--ew-just', $allineamento, fn($x) => ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$x] ?? null);
}

function ewx_url_pagina(array $ctx): string {
    $base = defined('SITO_URL') ? SITO_URL : '';
    return $base . ($ctx['percorso'] ?? '/');
}

function ewx_cornice_icona(): array {
    return [
        ec('vista', 'scelta', 'Vista', 'predefinita', 'contenuto', ['opzioni' => ['predefinita' => 'Semplice', 'impilata' => 'Impilata (cerchio pieno)', 'incorniciata' => 'Incorniciata (bordo)'], 'sezione' => 'Icona']),
        ec('forma', 'scelta', 'Forma', 'cerchio', 'contenuto', ['opzioni' => ['cerchio' => 'Cerchio', 'quadrato' => 'Quadrato'], 'condizione' => ['!vista' => 'predefinita'], 'sezione' => 'Icona']),
    ];
}
function ewx_stile_icona(string $sezione = 'Icona', $dim = 50): array {
    return [
        ewx_col('icona_colore', 'Colore principale', '--ew-c1', $sezione, true),
        ewx_col('icona_colore2', 'Colore secondario (sfondo o cornice)', '--ew-c2', $sezione, true),
        ewx_var('icona_dimensione', 'Dimensione', '--ew-dim', $sezione, ['d' => ['v' => $dim, 'u' => 'px']]),
        ewx_var('icona_padding', 'Spazio intorno', '--ew-pad', $sezione, '', ['condizione' => ['!vista' => 'predefinita']]),
        ec('icona_rotazione', 'numero', 'Rotazione (gradi)', '', 'stile', ['min' => 0, 'max' => 360, 'unita' => 'deg', 'selettore' => '', 'proprieta' => '--ew-rot', 'sezione' => $sezione]),
        ewx_var('icona_bordo', 'Spessore della cornice', '--ew-bw', $sezione, '', ['condizione' => ['vista' => 'incorniciata']]),
        ewx_var('icona_raggio', 'Angoli arrotondati', '--ew-r', $sezione, '', ['condizione' => ['forma' => 'quadrato']]),
    ];
}
function ewx_icona_html(array $v, string $classe = ''): string {
    $vista = in_array($v['vista'] ?? '', ['impilata', 'incorniciata'], true) ? $v['vista'] : 'predefinita';
    $forma = ($v['forma'] ?? '') === 'quadrato' ? 'quadrato' : 'cerchio';
    $svg = ewx_ic((string)($v['icona'] ?? ''));
    if ($svg === '') return '';
    return '<span class="ew-ic ew-ic-' . $vista . ' ew-ic-' . $forma . ($classe !== '' ? ' ' . $classe : '') . '">' . $svg . '</span>';
}

// Le definizioni e i disegni, in tre file per argomento.
require_once __DIR__ . '/editor-widget-extra-a.php';
require_once __DIR__ . '/editor-widget-extra-b.php';
require_once __DIR__ . '/editor-widget-extra-c.php';
