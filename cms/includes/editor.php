<?php
/**
 * Editor visuale delle pagine, degli articoli e degli aforismi.
 *
 * Una pagina è un albero: sezioni, dentro colonne, dentro widget. Ogni elemento ha un id e le sue impostazioni.
 *   {"versione":1,"elementi":[{"id":"a1b2c3d","tipo":"sezione","impostazioni":{...},"figli":[
 *       {"id":"...","tipo":"colonna","impostazioni":{...},"figli":[{"id":"...","tipo":"titolo","impostazioni":{...}}]}]}]}
 * Le impostazioni responsive valgono per desktop, tablet e telefono: {"d":..,"t":..,"m":..}; tablet e telefono,
 * se mancano, prendono il valore della misura più grande. Il CSS di ogni elemento si genera a parte (.ed-ID),
 * con le soglie classiche: tablet fino a 1024 px, telefono fino a 767 px.
 *
 * Nel database: la colonna struttura (pubblicata), struttura_bozza (in lavorazione); le versioni in {prefisso}editor_versioni (includes/versioni.php).
 * Una pagina senza struttura continua a mostrare il testo della scheda.
 */

require_once __DIR__ . '/sito-mappa.php';
require_once __DIR__ . '/editor-widget.php';

const EDITOR_VERSIONE = 1;
const EDITOR_REVISIONI = 30;
const EDITOR_TABLET = 1024;
const EDITOR_TELEFONO = 767;

/** Colonne e tabella dell'editor: le crea lo schema del sito (sito_schema_aggiorna), con la sua impronta. */
function editor_schema(): void {
    sito_schema_aggiorna();
}

function editor_id(): string {
    return substr(bin2hex(random_bytes(4)), 0, 7);
}

// ------------------------------------------------------------
// Controllo dei dati: nell'albero entra solo quello che l'editor conosce
// ------------------------------------------------------------

function editor_pulisci_valore(array $ctl, $val) {
    // Stati normale / al passaggio del mouse: {"normale": X, "hover": X}; un valore secco vale per il normale.
    if (!empty($ctl['stati'])) {
        $senza = ['stati' => false] + $ctl;
        if (is_array($val) && (array_key_exists('normale', $val) || array_key_exists('hover', $val))) {
            $out = ['normale' => editor_pulisci_valore($senza, $val['normale'] ?? null)];
            if (array_key_exists('hover', $val) && $val['hover'] !== null && $val['hover'] !== '') $out['hover'] = editor_pulisci_valore($senza, $val['hover']);
            return $out;
        }
        return ['normale' => editor_pulisci_valore($senza, $val)];
    }
    if (!empty($ctl['responsive']) && is_array($val) && !isset($val[0]) && !isset($val['url']) && !isset($val['v']) && !isset($val['sopra'])) {
        $out = [];
        foreach (['d', 't', 'm'] as $k) if (array_key_exists($k, $val) && $val[$k] !== null && $val[$k] !== '') $out[$k] = editor_pulisci_valore(['responsive' => false] + $ctl, $val[$k]);
        return $out;
    }
    if (!empty($ctl['responsive']) && (is_numeric($val) || is_string($val) || (is_array($val) && (isset($val['v']) || isset($val['sopra']))))) {
        // Un valore secco su un controllo responsive vale per il desktop.
        return ['d' => editor_pulisci_valore(['responsive' => false] + $ctl, $val)];
    }
    if (editor_e_gruppo($ctl['tipo'])) return editor_pulisci_gruppo($ctl, $val);
    switch ($ctl['tipo']) {
        case 'numero':
            $n = is_numeric($val) ? (float)$val : (float)(is_array($ctl['predefinito']) ? ($ctl['predefinito']['d'] ?? 0) : $ctl['predefinito']);
            if (isset($ctl['min'])) $n = max($ctl['min'], $n);
            if (isset($ctl['max'])) $n = min($ctl['max'], $n);
            return $n == (int)$n ? (int)$n : round($n, 2);
        case 'interruttore':
            return (bool)$val;
        case 'colore':
            return editor_pulisci_colore($val);
        case 'scelta':
            $val = (string)$val;
            return array_key_exists($val, $ctl['opzioni']) ? $val : (string)(is_array($ctl['predefinito']) ? ($ctl['predefinito']['d'] ?? '') : $ctl['predefinito']);
        case 'immagine':
            $val = trim((string)$val);
            return preg_match('~^(https?://|/)[^"\'<>\s]*$~', $val) ? mb_substr($val, 0, 500) : '';
        case 'link':
            return ['url' => editor_link_sicuro((string)(is_array($val) ? ($val['url'] ?? '') : $val)), 'nuova' => is_array($val) && !empty($val['nuova']), 'nofollow' => is_array($val) && !empty($val['nofollow'])];
        case 'ripetitore':
            $out = [];
            foreach (array_slice(is_array($val) ? array_values($val) : [], 0, 40) as $voce) {
                if (!is_array($voce)) continue;
                $r = [];
                foreach ($ctl['campi'] as $c) $r[$c['nome']] = editor_pulisci_valore($c, $voce[$c['nome']] ?? $c['predefinito']);
                $out[] = $r;
            }
            return $out;
        case 'ricco':
            return editor_ricco_pulito(mb_substr((string)$val, 0, 60000));
        case 'codice':
            return mb_substr((string)$val, 0, 400000);
        case 'testo':
            return mb_substr(str_replace(["\r", "\n"], ' ', (string)$val), 0, 500);
        default: // testolungo
            return mb_substr(str_replace("\r", '', (string)$val), 0, 5000);
    }
}

function editor_pulisci_impostazioni(string $tipo, $imp): array {
    $out = [];
    $imp = is_array($imp) ? $imp : [];
    foreach (editor_definizioni()[$tipo]['controlli'] as $ctl) {
        if ($ctl['tipo'] === 'intestazione' || !array_key_exists($ctl['nome'], $imp)) continue;
        $x = editor_pulisci_valore($ctl, $imp[$ctl['nome']]);
        if ($x !== null) $out[$ctl['nome']] = $x;
    }
    editor_pulisci_avanzate($out);
    return $out;
}

/**
 * sicurezza: i widget che emettono codice grezzo (html) sono dell'amministratore. Per chi non lo e, ogni widget html dell'albero
 * nuovo deve esistere identico nell'albero gia salvato ($precedente): se e nuovo o cambiato, il suo codice passa dal sanificatore
 * e diventa testo. Cosi un redattore non inietta script, e non perde i blocchi HTML che l'amministratore aveva messo.
 */
function editor_blinda_codice(array $albero, ?array $precedente): array {
    $noti = [];
    $raccogli = function (array $el) use (&$raccogli, &$noti) {
        foreach ($el as $x) {
            if (($x['tipo'] ?? '') === 'html') $noti[(string)($x['id'] ?? '')] = (string)($x['impostazioni']['codice'] ?? '');
            if (!empty($x['figli'])) $raccogli($x['figli']);
        }
    };
    if ($precedente) $raccogli($precedente['elementi'] ?? []);
    require_once __DIR__ . '/sicurezza-html.php';
    $blinda = function (array $el) use (&$blinda, $noti) {
        foreach ($el as &$x) {
            if (($x['tipo'] ?? '') === 'html') {
                $codice = (string)($x['impostazioni']['codice'] ?? '');
                $id = (string)($x['id'] ?? '');
                if (!isset($noti[$id]) || $noti[$id] !== $codice) $x['impostazioni']['codice'] = sicurezza_html_pulito($codice, 'articolo');
            }
            if (!empty($x['figli'])) $x['figli'] = $blinda($x['figli']);
        }
        unset($x);
        return $el;
    };
    $albero['elementi'] = $blinda($albero['elementi'] ?? []);
    return $albero;
}

/** Chi puo usare il widget HTML e il codice grezzo: l'amministratore (impostazione html_widget della pagina Sicurezza), oppure nessuno. */
function editor_puo_codice(): bool {
    if (!function_exists('is_logged_in') || !is_logged_in()) return true; // fuori dal pannello (cron, importazioni): codice gia fidato
    $chi = 'admin';
    if (function_exists('sicurezza_cfg')) { try { $chi = (string)sicurezza_cfg()['html_widget']; } catch (Throwable $e) {} }
    return $chi === 'admin' && function_exists('is_admin') && is_admin();
}

/** L'albero ripulito. Ritorna null se non è un albero dell'editor. */
function editor_valida($albero): ?array {
    if (!is_array($albero) || !isset($albero['elementi']) || !is_array($albero['elementi'])) return null;
    $def = editor_definizioni();
    $visti = [];
    $id = function ($x) use (&$visti) {
        $i = is_string($x['id'] ?? null) && preg_match('/^[a-z0-9]{4,12}$/', $x['id']) && !isset($visti[$x['id']]) ? $x['id'] : editor_id();
        $visti[$i] = true;
        return $i;
    };
    $sezioni = [];
    foreach (array_slice($albero['elementi'], 0, 200) as $s) {
        if (($s['tipo'] ?? '') !== 'sezione') continue;
        $colonne = [];
        foreach (array_slice((array)($s['figli'] ?? []), 0, 6) as $c) {
            if (($c['tipo'] ?? '') !== 'colonna') continue;
            $widget = [];
            foreach (array_slice((array)($c['figli'] ?? []), 0, 100) as $w) {
                $t = (string)($w['tipo'] ?? '');
                if (!isset($def[$t]) || in_array($t, ['sezione', 'colonna'], true)) continue;
                $widget[] = ['id' => $id($w), 'tipo' => $t, 'impostazioni' => editor_pulisci_impostazioni($t, $w['impostazioni'] ?? [])];
            }
            $colonne[] = ['id' => $id($c), 'tipo' => 'colonna', 'impostazioni' => editor_pulisci_impostazioni('colonna', $c['impostazioni'] ?? []), 'figli' => $widget];
        }
        if (!$colonne) $colonne[] = ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => []];
        $sezioni[] = ['id' => $id($s), 'tipo' => 'sezione', 'impostazioni' => editor_pulisci_impostazioni('sezione', $s['impostazioni'] ?? []), 'figli' => $colonne];
    }
    return ['versione' => EDITOR_VERSIONE, 'elementi' => $sezioni];
}

// ------------------------------------------------------------
// Valori
// ------------------------------------------------------------

/**
 * Impostazioni con i valori di partenza dove mancano. I responsive restano {"d","t","m"}; i controlli di gruppo
 * (tipografia, sfondo, bordo...) tornano con la struttura completa (editor_normalizza_gruppo); con "stati" tornano
 * {"normale": ..., "hover": ...} (hover vuoto se non impostato).
 */
function editor_valori(string $tipo, array $imp): array {
    $v = [];
    foreach (editor_definizioni()[$tipo]['controlli'] as $ctl) {
        if ($ctl['tipo'] === 'intestazione') continue;
        $x = $imp[$ctl['nome']] ?? null;
        if (!empty($ctl['stati'])) {
            $senza = ['stati' => false] + $ctl;
            $n = is_array($x) && (array_key_exists('normale', $x) || array_key_exists('hover', $x)) ? ($x['normale'] ?? null) : $x;
            $hv = is_array($x) ? ($x['hover'] ?? null) : null;
            $p = $ctl['predefinito'];
            $pStati = is_array($p) && (array_key_exists('normale', $p) || array_key_exists('hover', $p));
            $vuotoHover = editor_e_gruppo($ctl['tipo']) ? [] : '';
            $v[$ctl['nome']] = ['normale' => editor_valore_normalizzato($senza, $n, $pStati ? ($p['normale'] ?? $vuotoHover) : $p), 'hover' => editor_valore_normalizzato($senza, $hv, $pStati ? ($p['hover'] ?? $vuotoHover) : $vuotoHover)];
            continue;
        }
        $v[$ctl['nome']] = editor_valore_normalizzato($ctl, $x, $ctl['predefinito']);
    }
    return $v;
}

/** Un valore (responsive o no) con il predefinito dove manca e la struttura completa per i gruppi. */
function editor_valore_normalizzato(array $ctl, $x, $p) {
    $gruppo = editor_e_gruppo($ctl['tipo']) && is_array(editor_gruppo_vuoto($ctl['tipo']));
    if (!empty($ctl['responsive'])) {
        $p = is_array($p) && (isset($p['d']) || isset($p['t']) || isset($p['m'])) ? $p : ['d' => $p];
        if ($gruppo && is_array($x) && (isset($x['v']) || isset($x['sopra']))) $x = ['d' => $x];
        $r = is_array($x) ? $x + $p : $p;
        if ($gruppo) foreach ($r as $k => $y) $r[$k] = editor_normalizza_gruppo($ctl['tipo'], $p['d'] ?? null, $y);
        return $r;
    }
    if ($gruppo) return editor_normalizza_gruppo($ctl['tipo'], $p, $x);
    return $x ?? $p;
}

/** Il valore per una misura: tablet e telefono ereditano da quella più grande. */
function editor_per(array $r, string $m) {
    $catena = ['d' => ['d'], 't' => ['t', 'd'], 'm' => ['m', 't', 'd']][$m];
    foreach ($catena as $k) if (array_key_exists($k, $r) && $r[$k] !== '' && $r[$k] !== null) return $r[$k];
    return null;
}

// ------------------------------------------------------------
// Disegno della pagina
// ------------------------------------------------------------

/** CSS raccolto durante il disegno: [misura => [selettore => [proprietà => valore]]]. */
function editor_css_aggiungi(array &$css, string $sel, string $prop, $r, ?callable $fmt = null, ?string $base = null): void {
    $r = is_array($r) ? $r : ['d' => $r];
    $prima = $base; // il valore che il foglio di stile dà già: non si ripete
    foreach (['d', 't', 'm'] as $m) {
        $x = editor_per($r, $m);
        if ($x === null || $x === '') continue;
        $val = $fmt ? $fmt($x) : $x;
        if ($val === null || $val === '' || $val === $prima) continue;
        $css[$m][$sel][$prop] = $val;
        $prima = $val;
    }
}

function editor_css_testo(array $css): string {
    $blocchi = ['d' => '', 't' => '@media (max-width:' . editor_soglia('tablet') . 'px){', 'm' => '@media (max-width:' . editor_soglia('telefono') . 'px){'];
    $out = !empty($css['anima']) ? editor_css_animazioni() : '';
    foreach ($css['statico'] ?? [] as $x) $out .= $x;
    foreach ($blocchi as $m => $apri) {
        if (empty($css[$m])) continue;
        $regole = '';
        foreach ($css[$m] as $sel => $props) {
            $props = array_filter($props, fn($x) => $x !== null && $x !== '');
            if (!$props) continue;
            $regole .= $sel . '{' . implode(';', array_map(fn($p, $v) => "$p:$v", array_keys($props), $props)) . '}';
        }
        $out .= $apri === '' ? $regole : $apri . $regole . '}';
    }
    foreach ($css['raw'] ?? [] as $r) $out .= "\n" . $r;
    return $out;
}

/** Il CSS automatico di tutti i controlli con 'selettore' di un elemento, più quello della scheda Avanzate. */
function editor_css_elemento(array &$css, string $sel, string $tipo, array $v, string $per): void {
    foreach (editor_definizioni()[$tipo]['controlli'] as $ctl) {
        if (isset($ctl['selettore']) && array_key_exists($ctl['nome'], $v)) editor_css_controllo($css, $sel, $ctl, $v[$ctl['nome']]);
    }
    editor_css_avanzate($css, $sel, $v, $per);
}

function editor_classi_nascondi(array $v): string {
    return ($v['nascondi_d'] ?? false ? ' ed-no-d' : '') . ($v['nascondi_t'] ?? false ? ' ed-no-t' : '') . ($v['nascondi_m'] ?? false ? ' ed-no-m' : '');
}

/**
 * La pagina in HTML, con il suo CSS. $anteprima: attributi per l'editor (clic, trascinamento).
 * Ritorna [html, css].
 */
function editor_disegna(array $albero, string $percorso = '/', bool $anteprima = false): array {
    $GLOBALS['editor_percorso'] = $percorso;
    $css = [];
    $px = fn($x) => (is_numeric($x) ? $x . 'px' : null);
    $html = '';
    foreach ($albero['elementi'] ?? [] as $s) {
        $sv = editor_valori('sezione', $s['impostazioni'] ?? []);
        $sel = '.ed-' . $s['id'];
        editor_css_aggiungi($css, $sel, 'padding-top', $sv['spazio_sopra'], $px);
        editor_css_aggiungi($css, $sel, 'padding-bottom', $sv['spazio_sotto'], $px);
        editor_css_aggiungi($css, $sel, 'min-height', $sv['altezza_minima'], fn($x) => $x > 0 ? $x . 'vh' : 'auto', 'auto');
        editor_css_aggiungi($css, "$sel>.ed-contenitore>.ed-riga", 'gap', $sv['spazio_colonne'], $px, '32px');
        if ($sv['allineamento_verticale'] !== 'stretch') $css['d']["$sel>.ed-contenitore>.ed-riga"]['align-items'] = $sv['allineamento_verticale'];
        switch ($sv['sfondo']) {
            case 'colore': if ($sv['colore_sfondo'] !== '') $css['d'][$sel]['background'] = $sv['colore_sfondo']; break;
            case 'immagine':
                if ($sv['immagine_sfondo'] !== '') {
                    $velo = max(0, min(95, (int)$sv['velo'])) / 100;
                    $css['d'][$sel]['background'] = "linear-gradient(rgba(6,10,23,$velo),rgba(6,10,23,$velo)),url('" . str_replace(["'", '"', ')'], '', $sv['immagine_sfondo']) . "') center/cover no-repeat";
                }
                break;
        }
        $gap = $sv['spazio_colonne'];
        $colonne = '';
        foreach ($s['figli'] as $c) {
            $cv = editor_valori('colonna', $c['impostazioni'] ?? []);
            $csel = '.ed-' . $c['id'];
            $prima = '0 0 100%';
            foreach (['d', 't', 'm'] as $m) {
                $w = editor_per($cv['larghezza'], $m);
                $g = editor_per(is_array($gap) ? $gap : ['d' => $gap], $m) ?? 0;
                if ($w === null) continue;
                $flex = $w >= 100 ? '0 0 100%' : '0 0 calc(' . $w . '% - ' . round($g * (100 - $w) / 100, 2) . 'px)';
                if ($flex !== $prima) $css[$m][$csel]['flex'] = $flex;
                $prima = $flex;
            }
            if ($cv['allineamento_verticale'] !== 'flex-start') $css['d'][$csel]['justify-content'] = $cv['allineamento_verticale'];
            editor_css_aggiungi($css, $csel, 'padding', $cv['spazio_interno'], fn($x) => $x > 0 ? $x . 'px' : null);
            editor_css_aggiungi($css, $csel, 'text-align', $cv['allineamento'], fn($x) => $x ?: null);
            $widget = '';
            foreach ($c['figli'] as $w) $widget .= editor_disegna_elemento_widget($w, $css, $anteprima);
            if ($anteprima && $widget === '') $widget = '<div class="ed-vuoto ed-colonna-vuota">Trascina qui un widget</div>';
            editor_css_elemento($css, $csel, 'colonna', $cv, 'colonna');
            $cav = editor_attributi_avanzate($cv);
            $ctag = editor_tag_html($cv, 'div');
            $colonne .= '<' . $ctag . ' class="ed-colonna ed-' . $c['id'] . ($cv['riquadro'] ? ' ed-riquadro' : '') . editor_classi_nascondi($cv) . $cav['classi'] . ($cv['classe'] !== '' ? ' ' . h($cv['classe']) : '') . '"'
                . ($cv['ancora'] !== '' ? ' id="' . h($cv['ancora']) . '"' : '') . $cav['attr']
                . ($anteprima ? ' data-ed-id="' . $c['id'] . '" data-ed-tipo="colonna"' : '') . '>' . $widget . '</' . $ctag . '>';
        }
        $contenitore = ['contenuta' => 'wrap', 'stretta' => 'wrap ed-stretta', 'piena' => 'ed-piena', 'articolo' => 'ed-articolo', 'personalizzata' => 'wrap'][$sv['larghezza']] ?? 'wrap';
        if ($sv['larghezza'] === 'personalizzata') editor_css_resp($css, "$sel>.ed-contenitore", $sv['larghezza_px'], fn($x) => ['max-width' => editor_misura_css($x)]);
        if (($sv['overflow'] ?? '') === 'hidden') $css['d'][$sel]['overflow'] = 'hidden';
        editor_css_elemento($css, $sel, 'sezione', $sv, 'sezione');
        $sav = editor_attributi_avanzate($sv);
        $tag = editor_tag_html($sv, 'section');
        $html .= '<' . $tag . ' class="ed-sezione ed-' . $s['id'] . ' ed-sfondo-' . h($sv['sfondo']) . editor_classi_nascondi($sv) . $sav['classi'] . ($sv['classe'] !== '' ? ' ' . h($sv['classe']) : '') . '"'
            . ($sv['ancora'] !== '' ? ' id="' . h($sv['ancora']) . '"' : '') . $sav['attr']
            . ($anteprima ? ' data-ed-id="' . $s['id'] . '" data-ed-tipo="sezione"' : '') . '>'
            . '<div class="ed-contenitore ' . $contenitore . '"><div class="ed-riga">' . $colonne . '</div></div></' . $tag . '>';
    }
    return [$html, editor_css_testo($css)];
}

function editor_disegna_elemento_widget(array $w, array &$css, bool $anteprima): string {
    $v = editor_valori($w['tipo'], $w['impostazioni'] ?? []);
    $sel = '.ed-' . $w['id'];
    $px = fn($x) => (is_numeric($x) ? $x . 'px' : null);
    editor_css_aggiungi($css, $sel, 'margin-bottom', $v['margine_sotto'], $px, '24px');
    editor_css_elemento($css, $sel, $w['tipo'], $v, 'widget');
    $statico = editor_css_statico_widget($w['tipo']);
    if ($statico !== '') $css['statico'][$w['tipo']] = $statico;
    $GLOBALS['editor_ctx'] = ['id' => $w['id'], 'sel' => $sel, 'anteprima' => $anteprima, 'percorso' => $GLOBALS['editor_percorso'] ?? '/'];
    switch ($w['tipo']) {
        case 'titolo':
            editor_css_aggiungi($css, "$sel .ed-titolo", 'font-size', $v['dimensione'], fn($x) => $x > 0 ? $x . 'px' : null);
            editor_css_aggiungi($css, $sel, 'text-align', $v['allineamento'], fn($x) => $x ?: null);
            break;
        case 'testo':
            editor_css_aggiungi($css, "$sel .ed-testo", 'font-size', $v['dimensione'], fn($x) => $x > 0 ? $x . 'px' : null);
            if ($v['larghezza_max'] > 0) $css['d']["$sel .ed-testo"]['max-width'] = $v['larghezza_max'] . 'px';
            editor_css_aggiungi($css, $sel, 'text-align', $v['allineamento'], fn($x) => $x ?: null);
            break;
        case 'immagine':
            editor_css_aggiungi($css, "$sel img", 'width', $v['larghezza'], fn($x) => $x . '%');
            if (!$v['ritratto']) $css['d']["$sel img"]['border-radius'] = (int)$v['arrotondamento'] . 'px';
            editor_css_aggiungi($css, $sel, 'text-align', $v['allineamento'], fn($x) => $x ?: null);
            break;
        case 'pulsanti':
            editor_css_aggiungi($css, "$sel .ed-pulsanti", 'justify-content', $v['allineamento'], fn($x) => ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$x] ?? null);
            break;
        case 'spaziatore':
            editor_css_aggiungi($css, "$sel .ed-spazio", 'height', $v['altezza'], $px);
            break;
        case 'divisore':
            $css['d']["$sel .ed-divisore"]['width'] = (int)$v['larghezza'] . '%';
            $css['d']["$sel .ed-divisore"]['height'] = (int)$v['spessore'] . 'px';
            break;
        case 'passi': case 'schede': case 'numeri':
            editor_css_aggiungi($css, "$sel .ed-griglia", 'grid-template-columns', $v['colonne'], fn($x) => 'repeat(' . max(1, min(4, (int)$x)) . ',minmax(0,1fr))');
            break;
    }
    $interno = editor_disegna_registrato($w['tipo'], $v, $GLOBALS['editor_ctx'] + ['css' => &$css]);
    if ($interno === null) $interno = editor_disegna_widget($w['tipo'], $v);
    $av = editor_attributi_avanzate($v);
    return '<div class="ed-widget ed-w-' . $w['tipo'] . ' ed-' . $w['id'] . editor_classi_nascondi($v) . $av['classi'] . ($v['classe'] !== '' ? ' ' . h($v['classe']) : '') . '"'
        . ($v['ancora'] !== '' && $w['tipo'] !== 'titolo' ? ' id="' . h($v['ancora']) . '"' : '') . $av['attr']
        . ($anteprima ? ' data-ed-id="' . $w['id'] . '" data-ed-tipo="' . $w['tipo'] . '"' : '') . '>'
        . $interno . '</div>';
}

/** Le domande dei widget FAQ: per il JSON-LD FAQPage della pagina. */
function editor_faq(array $albero): array {
    $out = [];
    foreach ($albero['elementi'] ?? [] as $s) foreach ($s['figli'] as $c) foreach ($c['figli'] as $w) {
        if ($w['tipo'] !== 'faq') continue;
        foreach (editor_valori('faq', $w['impostazioni'])['voci'] as $x) {
            if (trim($x['domanda'] ?? '') !== '' && trim($x['risposta'] ?? '') !== '') $out[] = ['domanda' => $x['domanda'], 'risposta' => $x['risposta']];
        }
    }
    return $out;
}

/** Ha almeno un H1? (titolo con livello h1 oppure hero). Serve all'avviso nell'editor e alla vista pubblica. */
function editor_ha_h1(array $albero): bool {
    foreach ($albero['elementi'] ?? [] as $s) foreach ($s['figli'] as $c) foreach ($c['figli'] as $w) {
        if ($w['tipo'] === 'hero' || ($w['tipo'] === 'titolo' && ($w['impostazioni']['livello'] ?? 'h2') === 'h1')) return true;
    }
    return false;
}

// ------------------------------------------------------------
// Lettura e salvataggio
// ------------------------------------------------------------

/** La tabella (senza prefisso) di un tipo: quelle dei contenuti le dice contenuti_tipi(), "modelli" è dell'editor. */
function editor_tabella(string $tipo): string {
    if ($tipo === 'modelli') return 'modelli';
    require_once __DIR__ . '/contenuti.php';
    return contenuti_tipi()[$tipo]['tabella'] ?? 'pages';
}

/** I tipi di contenuto che l'editor apre: quelli di contenuti_tipi() (anche dei moduli) e i modelli di header e footer. */
function editor_tipo_valido(?string $tipo): string {
    if ($tipo === 'modelli' && is_file(__DIR__ . '/modelli.php')) return 'modelli';
    require_once __DIR__ . '/contenuti.php';
    if ($tipo !== null && $tipo !== 'pagine' && isset(contenuti_tipi()[$tipo])) return $tipo;
    return 'pagine';
}

/** Un tipo "di lettura" (articoli, aforismi) nasce con una sezione "Articolo" a colonna stretta; una pagina no. */
function editor_tipo_lettura(string $tipo): bool {
    if ($tipo === 'pagine' || $tipo === 'modelli') return false;
    require_once __DIR__ . '/contenuti.php';
    return (bool)(contenuti_tipi()[$tipo]['lettura'] ?? true);
}

function editor_pagina(int $id, string $tipo = 'pagine'): ?array {
    editor_schema();
    if ($tipo === 'modelli') {
        // modelli: la riga la dà includes/modelli.php con le chiavi che l'editor si aspetta (title, slug, status, struttura...).
        require_once __DIR__ . '/modelli.php';
        if (!function_exists('modelli_riga_editor')) return null;
        $r = modelli_riga_editor($id);
        if ($r) $r['_tipo'] = 'modelli';
        return $r ?: null;
    }
    $st = db()->prepare('SELECT * FROM ' . tbl(editor_tabella($tipo)) . ' WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch() ?: null;
    if ($r) $r['_tipo'] = $tipo;
    return $r;
}

/**
 * L'albero in JSON. Emoji e caratteri a 4 byte restano scritti come \uD83D\uDE00: la colonna è utf8 a 3 byte
 * e fuori dalla modalità stretta taglierebbe il testo in quel punto. Decodificato, il carattere torna uguale.
 */
function editor_json(array $albero): string {
    $json = json_encode($albero, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', function ($m) {
        $c = mb_ord($m[0]) - 0x10000;
        return sprintf('\\u%04x\\u%04x', 0xD800 + ($c >> 10), 0xDC00 + ($c & 0x3FF));
    }, $json);
}

function editor_decodifica(?string $json): ?array {
    if ($json === null || trim($json) === '') return null;
    return editor_valida(json_decode($json, true));
}

/** Salva la bozza; con $pubblica anche la versione pubblica. In entrambi i casi resta una versione (includes/versioni.php). */
function editor_salva(int $id, array $albero, bool $pubblica, string $autore = '', string $nota = '', string $tipo = 'pagine'): void {
    editor_schema();
    $json = editor_json($albero);
    if ($tipo === 'modelli' && function_exists('modelli_salva_struttura')) { modelli_salva_struttura($id, $json, $pubblica, $autore); return; }
    $tab = tbl(editor_tabella($tipo));
    if ($pubblica) {
        db()->prepare('UPDATE ' . $tab . ' SET struttura = ?, struttura_bozza = NULL, struttura_aggiornata = NOW(), updated_at = NOW() WHERE id = ?')->execute([$json, $id]);
    } else {
        db()->prepare('UPDATE ' . $tab . ' SET struttura_bozza = ?, struttura_aggiornata = NOW() WHERE id = ?')->execute([$json, $id]);
    }
    // versioni: ogni salvataggio che cambia qualcosa resta in {prefisso}editor_versioni (al posto di pagine_revisioni, importata una volta).
    editor_versione_dopo_salva($id, $tipo, $pubblica, $autore, $nota);
}

/** versioni: la versione di un contenuto o di un modello appena salvato dall'editor. Mai un errore da qui. */
function editor_versione_dopo_salva(int $id, string $tipo, bool $pubblica, string $autore, string $nota): void {
    try {
        require_once __DIR__ . '/versioni.php';
        $riga = editor_pagina($id, $tipo);
        if (!$riga) return;
        $nota = trim($nota) !== '' && $nota !== 'dall\'editor' ? $nota : ($pubblica ? 'pubblicato' : 'bozza');
        if ($tipo === 'modelli') {
            require_once __DIR__ . '/modelli.php';
            $m = modelli_leggi($id);
            if ($m) editor_versione_salva('modello', $id, (string)$m['nome'], versioni_dati_modello($m), $nota, ['pubblicata' => $pubblica, 'autore' => $autore]);
            return;
        }
        editor_versione_salva('contenuto:' . $tipo, $id, (string)$riga['title'], versioni_dati_contenuto($riga), $nota, ['pubblicata' => $pubblica, 'autore' => $autore]);
    } catch (Throwable $e) {
        error_log('versioni: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Punti di partenza
// ------------------------------------------------------------

function editor_sezione(array $widget, array $imp = []): array {
    return ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => $imp, 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => $widget]]];
}

function editor_widget(string $tipo, array $imp = []): array {
    return ['id' => editor_id(), 'tipo' => $tipo, 'impostazioni' => $imp];
}

/**
 * Il contenuto di prima dentro l'editor: niente si perde, si rifà con calma.
 * L'HTML resta intero in widget HTML; i moduli Brevo diventano widget Modulo, come li mostra oggi il sito
 * (vista_moduli_in_pagina). Stessa larghezza e stessi spazi della pagina di prima.
 */
function editor_albero_da_html(string $titolo, string $html, string $percorso = '/', string $tldr = ''): array {
    $w = [editor_widget('titolo', ['testo' => $titolo, 'livello' => 'h1', 'margine_sotto' => ['d' => 28]])];
    if (trim($tldr) !== '' && function_exists('vista_in_breve')) $w[] = editor_widget('html', ['codice' => vista_in_breve($tldr), 'margine_sotto' => ['d' => 0]]);
    $ebook = defined('MODULI_PAGINA_EBOOK') && $percorso === MODULI_PAGINA_EBOOK;
    if ($ebook) $html = preg_replace('~href="(?:' . preg_quote(defined('SITO_URL') ? SITO_URL : cms_url_sito(), '~') . ')?/fuori-dagli-schemi/"~', 'href="#modulo"', $html);
    $pezzi = preg_split('~(<!--\s*Begin Brevo Form\s*-->[\s\S]*?<!--\s*End Brevo Form\s*-->)~i', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $moduli = 0;
    foreach ($pezzi as $p) {
        if (preg_match('~^<!--\s*Begin Brevo Form~i', $p)) {
            $w[] = editor_widget('modulo', ['tipo' => str_contains($p, 'name="SMS"') ? 'laser' : 'newsletter']);
            $moduli++;
        } elseif (trim($p) !== '') {
            $w[] = editor_widget('html', ['codice' => $p, 'classe' => 'corpo']);
        }
    }
    if ($ebook) $w[] = editor_widget('modulo', ['tipo' => 'ebook']);
    elseif (!$moduli && $percorso === '/laser-coaching/') $w[] = editor_widget('modulo', ['tipo' => 'laser']);
    return editor_valida(['elementi' => [editor_sezione($w, ['larghezza' => 'stretta', 'spazio_sopra' => ['d' => 56], 'spazio_sotto' => ['d' => 80]])]]);
}

/**
 * Un articolo nell'editor. Il modulo del sito può convertirlo in blocchi (danilofailli.it: editor-articoli.php, un widget
 * per capitolo, testo, immagine, tabella...); altrimenti il testo entra in una sezione di lettura con un widget HTML.
 */
function editor_albero_da_articolo(array $a): array {
    $albero = cms_filtro('editor_albero_articolo', null, $a);
    if (is_array($albero)) return $albero;
    $html = (string)($a['content'] ?? $a['html'] ?? '');
    if (($a['content_format'] ?? 'html') === 'markdown' && function_exists('markdown_to_html')) $html = markdown_to_html($html);
    return editor_valida(['elementi' => [editor_sezione([editor_widget('html', ['codice' => $html, 'margine_sotto' => ['d' => 0]])], ['larghezza' => 'articolo', 'spazio_sopra' => ['d' => 0], 'spazio_sotto' => ['d' => 0]])]]);
}

/**
 * La home di oggi in sezioni e widget (la carta "La grafica di oggi" dell'editor): la dà il modulo del sito;
 * senza, un'apertura con il nome del sito e gli ultimi articoli.
 */
function editor_albero_home(): array {
    $albero = cms_filtro('editor_albero_home', null);
    if (is_array($albero)) return $albero;
    return editor_valida(['elementi' => [
        editor_sezione([editor_widget('hero', ['occhiello' => (string)cms_impostazione('sito_sottotitolo', ''), 'titolo' => (string)cms_impostazione('sito_nome', CMS_NOME), 'testo' => (string)cms_impostazione('sito_descrizione', ''), 'pulsanti' => [], 'immagine' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]])], ['spazio_sopra' => ['d' => 64], 'spazio_sotto' => ['d' => 40]]),
        editor_sezione([editor_widget('titolo', ['occhiello' => (string)cms_impostazione('blog_nome', 'Blog'), 'testo' => 'Gli ultimi articoli', 'margine_sotto' => ['d' => 28]]), editor_widget('articoli', ['numero' => 3, 'solo_pillar' => false, 'margine_sotto' => ['d' => 0]])]),
    ]]);
}

/** La pagina è la home? (impostazione pagina_home) */
function editor_e_home(array $pagina): bool {
    return ($pagina['_tipo'] ?? 'pagine') === 'pagine' && $pagina['slug'] === (string)get_setting('pagina_home', '');
}

function editor_percorso_pagina(array $pagina): string {
    return editor_e_home($pagina) ? '/' : '/' . $pagina['slug'] . '/';
}
