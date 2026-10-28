<?php
/**
 * I controlli di gruppo dell'editor (come i Group Controls di Elementor) e gli agganci per i widget scritti in altri file.
 *
 * Tipi di gruppo: tipografia, sfondo, bordo, ombra, ombra_testo, dimensioni, misura (alias cursore), allineamento,
 * scelta_icone, icona, galleria, data_ora, trasformazione, intestazione. Ogni tipo ha tre pezzi, qui sotto in ordine:
 * la pulizia del valore che arriva dal pannello (editor_pulisci_gruppo), la struttura completa con i valori di partenza
 * (editor_normalizza_gruppo) e il CSS che ne esce (editor_css_gruppo). Il contratto per gli altri file:
 * audit3/CONTROLLI-CONTRATTO.md (copia in docs/editor-pagine.md, sezione "Controlli di gruppo").
 *
 * Un controllo con 'selettore' scrive il CSS da solo (editor_css_controllo): il widget non deve leggere i suoi valori di stile.
 * Un valore vuoto non scrive niente: il foglio di stile del sito resta quello che si vede.
 */

const EDITOR_UNITA_MISURA = ['px', '%', 'em', 'rem', 'vw', 'vh'];
const EDITOR_INTERVALLI = ['px' => [0, 500, 1], '%' => [0, 100, 1], 'em' => [0, 20, 0.1], 'rem' => [0, 20, 0.1], 'vw' => [0, 100, 1], 'vh' => [0, 100, 1], '' => [0, 5, 0.1], 'ms' => [0, 5000, 10], 'deg' => [-360, 360, 1]];
const EDITOR_INTERVALLI_DIMENSIONI = ['px' => [-500, 500, 1], '%' => [-100, 100, 1], 'em' => [-20, 20, 0.1], 'rem' => [-20, 20, 0.1], 'vw' => [-100, 100, 1], 'vh' => [-100, 100, 1]];
const EDITOR_TIPI_GRUPPO = ['carattere', 'tipografia', 'sfondo', 'bordo', 'ombra', 'ombra_testo', 'dimensioni', 'misura', 'cursore', 'allineamento', 'scelta_icone', 'icona', 'galleria', 'data_ora', 'trasformazione', 'intestazione'];
const EDITOR_PESI = ['', '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'];
require_once __DIR__ . '/editor-opzioni.php'; // menu: opzioni dell'editor (widget attivi, animazioni, colonne, chi pubblica)

const EDITOR_POSIZIONI_SFONDO = ['center center', 'center left', 'center right', 'top center', 'top left', 'top right', 'bottom center', 'bottom left', 'bottom right'];
const EDITOR_ANIMAZIONI = ['' => 'Nessuna', 'dissolvenza' => 'Dissolvenza', 'dal_basso' => 'Dal basso', 'dall_alto' => "Dall'alto", 'da_sinistra' => 'Da sinistra', 'da_destra' => 'Da destra', 'zoom' => 'Zoom'];

// ------------------------------------------------------------
// Agganci: widget registrati da altri file, asset, contesto
// ------------------------------------------------------------

/** I widget registrati con editor_registra_widget(): tipo → ['definizione' => [...], 'disegna' => callable]. */
function &editor_registro(): array {
    static $r = ['widget' => [], 'asset' => [], 'caricato' => false, 'file_corrente' => ''];
    return $r;
}

/**
 * Registra un widget scritto in un altro file. $definizione: etichetta, icona, gruppo, controlli (i comuni della scheda
 * Avanzate si aggiungono da soli). $disegna: function (array $v, array $ctx): string con l'HTML interno del widget.
 */
function editor_registra_widget(string $tipo, array $definizione, callable $disegna): void {
    $r = &editor_registro();
    if (!preg_match('/^[a-z][a-z0-9_]{1,40}$/', $tipo) || str_starts_with($tipo, 'ig_') || in_array($tipo, ['sezione', 'colonna'], true)) {
        throw new InvalidArgumentException("Nome del widget non valido: $tipo");
    }
    // menu: da dove viene il widget (pagina Widget del pannello): 'nucleo' oppure 'modulo:<nome>' se il file sta in moduli/<nome>/.
    $origine = 'nucleo';
    if ($r['file_corrente'] !== '' && preg_match('~/moduli/([a-z][a-z0-9_-]*)/~', str_replace('\\', '/', $r['file_corrente']), $m)) $origine = 'modulo:' . $m[1];
    $definizione += ['etichetta' => ucfirst(str_replace('_', ' ', $tipo)), 'icona' => '*', 'gruppo' => 'Altri', 'controlli' => [], 'origine' => $origine];
    $r['widget'][$tipo] = ['definizione' => $definizione, 'disegna' => $disegna];
}

/** I file di sito/assets/ che un widget vuole sul sito: ['css' => 'widget.css', 'js' => 'widget.js']. */
function editor_registra_asset(string $tipo, array $asset): void {
    $r = &editor_registro();
    $r['asset'][$tipo] = $asset;
}

/** Carica una volta i file dei widget esterni (tema, extra), se esistono. */
function editor_carica_widget_esterni(): void {
    $r = &editor_registro();
    if ($r['caricato']) return;
    $r['caricato'] = true;
    $file = [];
    foreach (['editor-widget-tema.php', 'editor-widget-extra.php', 'editor-widget-download.php'] as $f) $file[] = __DIR__ . '/' . $f;
    foreach (cms_moduli_elenco('widget') as $x) $file[] = (string)$x['valore']; // i widget dichiarati dai moduli
    foreach ($file as $f) {
        if (!is_file($f)) continue;
        // Un file dei widget rotto o a metà non deve portare giù il sito né sporcare le risposte JSON del pannello:
        // gli avvisi finiscono nel log e si va avanti senza quei widget.
        set_error_handler(function (int $n, string $msg, string $file = '', int $riga = 0) use ($f) { error_log("editor: avviso in $f: $msg ($file:$riga)"); return true; });
        $r['file_corrente'] = $f; // menu: per l'origine dei widget registrati
        try {
            require_once $f;
        } catch (Throwable $e) {
            error_log('editor: ' . $f . ' non caricato: ' . $e->getMessage());
        } finally {
            restore_error_handler();
            $r['file_corrente'] = '';
        }
    }
    // I fogli delle disposizioni dell'Apertura (1.2.6): solo nelle pagine che hanno un'Apertura.
    if (!isset($r['asset']['hero']) && defined('EDITOR_HERO_CSS')) $r['asset']['hero'] = ['css' => EDITOR_HERO_CSS];
    // Compatibilità con la forma a tre funzioni dell'agente modelli: editor_tema_definizioni(), editor_tema_disegna($tipo, $v), editor_tema_css($tipo, $v, $sel, &$css).
    if (function_exists('editor_tema_definizioni')) {
        foreach ((array)editor_tema_definizioni() as $tipo => $def) {
            if (isset($r['widget'][$tipo])) continue;
            editor_registra_widget($tipo, $def, function (array $v, array $ctx) use ($tipo) {
                if (function_exists('editor_tema_css')) { $css = &$ctx['css']; editor_tema_css($tipo, $v, $ctx['sel'], $css); }
                return function_exists('editor_tema_disegna') ? (string)editor_tema_disegna($tipo, $v) : '';
            });
        }
    }
}

/** Le definizioni dei widget registrati (dopo il caricamento). */
function editor_widget_registrati(): array {
    editor_carica_widget_esterni();
    return editor_registro()['widget'];
}

/** Il disegno di un widget registrato, oppure null se il tipo non è registrato. */
function editor_disegna_registrato(string $tipo, array $v, array $ctx): ?string {
    $w = editor_widget_registrati()[$tipo] ?? null;
    if (!$w) return null;
    return (string)($w['disegna'])($v, $ctx);
}

/** Gli asset (css, js) da caricare per una pagina, guardando quali widget contiene l'HTML. */
function editor_asset_pagina(string $html): array {
    editor_carica_widget_esterni();
    $out = ['css' => [], 'js' => []];
    foreach (editor_registro()['asset'] as $tipo => $a) {
        if (!str_contains($html, 'ed-w-' . $tipo)) continue;
        // Un widget puo avere piu file (1.2.6: le disposizioni dell'Apertura): 'css' => ['a.css', 'b.css'].
        // Le viste mettono ogni <link> davanti al precedente: i fogli dello stesso widget si danno al contrario, cosi
        // nella pagina escono nell'ordine scritto (hero-base.css prima di hero-a.css e hero-b.css).
        foreach (['css', 'js'] as $k) {
            $file = (array)($a[$k] ?? []);
            foreach ($k === 'css' ? array_reverse($file) : $file as $f) if ($f !== '' && !in_array($f, $out[$k], true)) $out[$k][] = $f;
        }
    }
    return $out;
}

/** Serve lo script editor-pagina.js? Video, animazioni all'ingresso o elementi sticky. */
function editor_serve_script(string $html): bool {
    return str_contains($html, 'class="ed-video"') || str_contains($html, 'ed-anima-') || str_contains($html, 'ed-sticky-');
}

/** Il contenuto corrente (titolo, percorso, tipo...), per i widget che lo mostrano. */
function editor_contesto(string $chiave, $predefinito = null) {
    return $GLOBALS['editor_contesto'][$chiave] ?? $predefinito;
}

// ------------------------------------------------------------
// Globali (stile del sito), icone, conversioni
// ------------------------------------------------------------

/**
 * Colori e caratteri globali per il pannello: da design_globali_per_editor() (DESIGN-CONTRATTO.md) se design.php c'è,
 * altrimenti quelli del sito di oggi. Forma: colori [{id, nome, valore, var}], font [{id, nome, valore, var}], famiglie {nome: pila}, tablet, telefono.
 */
function editor_globali(): array {
    static $g = null;
    if ($g !== null) return $g;
    $g = null;
    if (is_file(__DIR__ . '/design.php')) {
        require_once __DIR__ . '/design.php';
        if (function_exists('design_globali_per_editor')) {
            try { $g = design_globali_per_editor(); } catch (Throwable $e) { $g = null; }
        }
    }
    if (!is_array($g)) $g = [];
    $g += [
        'colori' => [
            ['id' => 'primario', 'nome' => 'Primario', 'valore' => '#22d3ee', 'var' => '--g-colore-primario'], ['id' => 'secondario', 'nome' => 'Secondario', 'valore' => '#a855f7', 'var' => '--g-colore-secondario'],
            ['id' => 'testo', 'nome' => 'Testo', 'valore' => '#e6ebf5', 'var' => '--g-colore-testo'], ['id' => 'accento', 'nome' => 'Accento', 'valore' => '#4ade80', 'var' => '--g-colore-accento'],
            ['id' => 'sfondo', 'nome' => 'Sfondo', 'valore' => '#060a17', 'var' => '--g-colore-sfondo'],
            ['id' => 'sfondo-2', 'nome' => 'Sfondo 2', 'valore' => '#0a1024', 'var' => '--g-colore-sfondo-2'], ['id' => 'testo-2', 'nome' => 'Testo 2', 'valore' => '#a7b1c6', 'var' => '--g-colore-testo-2'],
            ['id' => 'testo-3', 'nome' => 'Testo 3', 'valore' => '#6f7a92', 'var' => '--g-colore-testo-3'], ['id' => 'bordo', 'nome' => 'Bordo', 'valore' => 'rgba(140,190,255,.16)', 'var' => '--g-colore-bordo'],
        ],
        'font' => [
            ['id' => 'primario', 'nome' => 'Primario (titoli)', 'valore' => '"Space Grotesk",system-ui,sans-serif', 'var' => '--g-font-primario'], ['id' => 'secondario', 'nome' => 'Secondario', 'valore' => '"Inter",system-ui,sans-serif', 'var' => '--g-font-secondario'],
            ['id' => 'testo', 'nome' => 'Testo', 'valore' => '"Inter",system-ui,sans-serif', 'var' => '--g-font-testo'], ['id' => 'accento', 'nome' => 'Accento', 'valore' => '"Space Grotesk",system-ui,sans-serif', 'var' => '--g-font-accento'],
        ],
        'famiglie' => ['Inter' => '"Inter",system-ui,sans-serif', 'Space Grotesk' => '"Space Grotesk",system-ui,sans-serif', 'Georgia' => 'Georgia,serif', 'Times New Roman' => '"Times New Roman",serif', 'Arial' => 'Arial,sans-serif',
            'Helvetica' => 'Helvetica,Arial,sans-serif', 'Verdana' => 'Verdana,sans-serif', 'Trebuchet MS' => '"Trebuchet MS",sans-serif', 'Courier New' => '"Courier New",monospace', 'system-ui' => 'system-ui,sans-serif'],
        'tablet' => 1024, 'telefono' => 767,
    ];
    return $g;
}

/** Le soglie di tablet e telefono: dallo Stile del sito se c'è, altrimenti 1024 e 767. */
function editor_soglia(string $quale): int {
    if (is_file(__DIR__ . '/design.php')) {
        require_once __DIR__ . '/design.php';
        if (function_exists('design_breakpoint')) { try { return (int)design_breakpoint($quale); } catch (Throwable $e) { /* soglie fisse */ } }
    }
    return $quale === 'tablet' ? 1024 : 767;
}

/** Il set di icone (includes/icone.php dell'agente widget): nome → svg. Senza il file, elenco vuoto. */
function editor_icone_set(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = [];
    if (is_file(__DIR__ . '/icone.php')) {
        require_once __DIR__ . '/icone.php';
        if (function_exists('icone_set')) {
            try { $s = (array)icone_set(); } catch (Throwable $e) { $s = []; }
        }
    }
    return $s;
}

/** L'SVG di un'icona del set, o '' se non c'è. */
function editor_icona(string $nome, string $classe = ''): string {
    $set = editor_icone_set();
    if ($nome === '' || !isset($set[$nome])) return '';
    if (function_exists('icone_svg')) return icone_svg($nome, $classe);
    $svg = (string)$set[$nome];
    return $classe !== '' ? preg_replace('/<svg\b/', '<svg class="' . h($classe) . '"', $svg, 1) : $svg;
}

/** design.php (stile globale), se c'è: le sue funzioni traducono i riferimenti e danno l'elenco dei font. */
function editor_design_c(): bool {
    static $c = null;
    if ($c !== null) return $c;
    if (is_file(__DIR__ . '/design.php')) require_once __DIR__ . '/design.php';
    return $c = function_exists('design_css_valore');
}

/** Un colore salvato → CSS. '' → null. globale:x → var(--g-colore-x). ig:x → var(--ig-x), un colore della tavolozza delle infografiche (temi). */
function editor_colore_css($c): ?string {
    $c = trim((string)$c);
    if ($c === '') return null;
    if (preg_match('/^globale:([a-z0-9-]+)$/', $c, $m)) {
        // Con la variabile anche il valore di ripiego: se la tabella dello Stile del sito è vuota, il colore resta quello.
        $rip = editor_globali_ripiego()['colori'][$m[1]] ?? null;
        return 'var(--g-colore-' . $m[1] . ($rip ? ',' . $rip : '') . ')';
    }
    // temi: i colori di partenza di alcuni widget (CTA, riquadro del test) sono quelli di Carta: con ig: li cambia il tema.
    if (preg_match('/^ig:([a-z0-9-]+)$/', $c, $m)) return 'var(--ig-' . $m[1] . ')';
    return $c;
}

/** I valori di ripiego dei globali (id → valore), dai colori e font che il pannello conosce. */
function editor_globali_ripiego(): array {
    static $r = null;
    if ($r !== null) return $r;
    $r = ['colori' => [], 'font' => []];
    foreach (editor_globali()['colori'] as $c) if (isset($c['id'], $c['valore'])) $r['colori'][$c['id']] = $c['valore'];
    foreach (editor_globali()['font'] as $f) if (isset($f['id'], $f['valore'])) $r['font'][$f['id']] = $f['valore'];
    return $r;
}

/** Una famiglia salvata → CSS. '' → null. globale:x (o globale:font-x) → var(--g-font-x). Un nome → la pila di design.php, o una sensata. */
function editor_font_css($f): ?string {
    $f = trim((string)$f);
    if ($f === '') return null;
    if (preg_match('/^globale:(?:font-)?([a-z0-9-]+)$/', $f, $m)) {
        $rip = editor_globali_ripiego()['font'][$m[1]] ?? null;
        return 'var(--g-font-' . $m[1] . ($rip ? ',' . $rip : '') . ')';
    }
    if (editor_design_c()) {
        try { $x = design_css_valore($f, 'font'); if (is_string($x) && $x !== '') return $x; } catch (Throwable $e) { /* si va avanti con la pila di ripiego */ }
    }
    if (in_array(strtolower($f), ['system-ui', 'serif', 'sans-serif', 'monospace', 'inherit'], true)) return $f;
    if (str_contains($f, ',')) return $f; // pila già scritta
    $generica = in_array($f, ['Georgia', 'Times New Roman', 'Garamond', 'Palatino'], true) ? 'serif' : (in_array($f, ['Courier New', 'Menlo', 'Consolas'], true) ? 'monospace' : 'sans-serif');
    return '"' . str_replace('"', '', $f) . '",' . ($generica === 'sans-serif' ? 'system-ui,' : '') . $generica;
}

/** {v,u} → "12px"; v vuoto → null. Unità '' = numero puro. */
function editor_misura_css($m): ?string {
    if (is_numeric($m)) return $m . 'px';
    if (!is_array($m) || !isset($m['v']) || $m['v'] === '' || $m['v'] === null || !is_numeric($m['v'])) return null;
    $v = (float)$m['v'];
    return ($v == (int)$v ? (string)(int)$v : rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.')) . (string)($m['u'] ?? 'px');
}

/** Pulisce un colore: esadecimale, rgba, transparent, globale:chiave. */
function editor_pulisci_colore($val): string {
    $val = trim((string)$val);
    if ($val === 'transparent' || preg_match('/^(globale|ig):[a-z0-9-]{1,40}$/', $val)) return $val;
    return preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(,\s*[\d.]+\s*)?\)|hsla?\(\s*[\d.]+\s*,\s*[\d.]+%\s*,\s*[\d.]+%\s*(,\s*[\d.]+\s*)?\))$/', $val) ? $val : '';
}

// ------------------------------------------------------------
// Pulizia dei valori di gruppo (quello che arriva dal pannello)
// ------------------------------------------------------------

function editor_intervallo(array $ctl, string $u, bool $dimensioni = false): array {
    $base = $dimensioni ? EDITOR_INTERVALLI_DIMENSIONI : EDITOR_INTERVALLI;
    $i = $ctl['intervalli'][$u] ?? $base[$u] ?? [0, 500, 1];
    if (!isset($ctl['intervalli'][$u])) {
        if (isset($ctl['min'])) $i[0] = $ctl['min'];
        if (isset($ctl['max'])) $i[1] = $ctl['max'];
        if (isset($ctl['passo'])) $i[2] = $ctl['passo'];
    }
    return $i;
}

function editor_unita(array $ctl, array $predefinite = EDITOR_UNITA_MISURA): array {
    $u = $ctl['unita'] ?? $predefinite;
    if (is_string($u)) $u = [$u];
    return array_values(array_filter($u, fn($x) => is_string($x))) ?: $predefinite;
}

function editor_numero_o_vuoto($x, array $i): string|int|float {
    if (!is_numeric($x)) return '';
    $n = max($i[0], min($i[1], (float)$x));
    return $n == (int)$n ? (int)$n : round($n, 3);
}

/** Pulisce {v,u} (misura). Accetta anche un numero secco. */
function editor_pulisci_misura(array $ctl, $val, array $unitaPred = EDITOR_UNITA_MISURA): array {
    $unita = editor_unita($ctl, $unitaPred);
    if (is_numeric($val)) $val = ['v' => $val, 'u' => $unita[0]];
    if (!is_array($val)) return ['v' => '', 'u' => $unita[0]];
    $u = in_array($val['u'] ?? '', $unita, true) ? (string)$val['u'] : $unita[0];
    return ['v' => editor_numero_o_vuoto($val['v'] ?? '', editor_intervallo($ctl, $u)), 'u' => $u];
}

/** Pulisce i quattro lati (dimensioni). */
function editor_pulisci_dimensioni(array $ctl, $val, array $unitaPred = ['px', '%', 'em', 'rem']): array {
    $unita = editor_unita($ctl, $unitaPred);
    if (!is_array($val)) $val = [];
    $u = in_array($val['u'] ?? '', $unita, true) ? (string)$val['u'] : $unita[0];
    $i = editor_intervallo($ctl, $u, true);
    $out = ['u' => $u, 'legati' => !empty($val['legati'])];
    foreach (['sopra', 'destra', 'sotto', 'sinistra'] as $l) $out[$l] = editor_numero_o_vuoto($val[$l] ?? '', $i);
    return $out;
}

/** Un sotto-campo responsive dentro un gruppo ({"d":..,"t":..,"m":..}): ogni misura passa da $pulisci. */
function editor_pulisci_resp($val, callable $pulisci): array {
    $out = [];
    if (!is_array($val)) return $out;
    if (array_key_exists('v', $val) || array_key_exists('sopra', $val)) $val = ['d' => $val]; // valore secco → desktop
    foreach (['d', 't', 'm'] as $k) if (isset($val[$k]) && is_array($val[$k])) {
        $p = $pulisci($val[$k]);
        $vuoto = !array_filter($p, fn($x, $kk) => $kk !== 'u' && $kk !== 'legati' && $x !== '', ARRAY_FILTER_USE_BOTH);
        if (!$vuoto || $k === 'd') $out[$k] = $p;
    }
    return $out;
}

function editor_pulisci_in(array $ammessi, $val, string $pred = ''): string {
    $val = (string)$val;
    return in_array($val, $ammessi, true) ? $val : $pred;
}

/** La pulizia dei tipi di gruppo. Chiamata da editor_pulisci_valore per i tipi che non conosce. Ritorna null per "non salvare". */
function editor_pulisci_gruppo(array $ctl, $val) {
    switch ($ctl['tipo']) {
        case 'intestazione':
            return null;
        case 'carattere':
            $fam = trim((string)(is_array($val) ? '' : $val));
            return preg_match('/^(globale:(?:font-)?[a-z0-9-]{1,40}|[A-Za-z0-9 ,\'"\-]{0,100})$/', $fam) ? $fam : '';
        case 'misura': case 'cursore':
            return editor_pulisci_misura($ctl, $val);
        case 'dimensioni':
            return editor_pulisci_dimensioni($ctl, $val);
        case 'allineamento':
            return editor_pulisci_in(array_keys(editor_opzioni_allineamento($ctl)), $val, '');
        case 'scelta_icone':
            return editor_pulisci_in(array_keys((array)($ctl['opzioni'] ?? [])), $val, (string)(is_array($ctl['predefinito']) ? ($ctl['predefinito']['d'] ?? '') : $ctl['predefinito']));
        case 'icona':
            $val = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string)$val)));
            $set = editor_icone_set();
            return $set && !isset($set[$val]) ? '' : mb_substr($val, 0, 60);
        case 'data_ora':
            $val = trim((string)$val);
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/', $val, $m)) return $m[1] . ' ' . $m[2];
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) ? $val . ' 00:00' : '';
        case 'galleria':
            $out = [];
            foreach (array_slice(is_array($val) ? array_values($val) : [], 0, 60) as $x) {
                if (!is_array($x)) continue;
                $img = trim((string)($x['immagine'] ?? ''));
                if (!preg_match('~^(https?://|/)[^"\'<>\s]*$~', $img)) continue;
                $out[] = ['immagine' => mb_substr($img, 0, 500), 'alt' => mb_substr(trim((string)($x['alt'] ?? '')), 0, 300), 'didascalia' => mb_substr(trim((string)($x['didascalia'] ?? '')), 0, 500), 'link' => editor_link_sicuro((string)($x['link'] ?? ''))];
            }
            return $out;
        case 'tipografia':
            if (!is_array($val)) $val = [];
            $fam = trim((string)($val['famiglia'] ?? ''));
            if (!preg_match('/^(globale:(?:font-)?[a-z0-9-]{1,40}|[A-Za-z0-9 ,\'"\-]{0,100})$/', $fam)) $fam = '';
            $mis = fn(array $unita) => fn($x) => editor_pulisci_misura(['intervalli' => ['px' => [0, 300, 1], 'em' => [0, 20, 0.01], 'rem' => [0, 20, 0.01], 'vw' => [0, 30, 0.1], '' => [0, 5, 0.01]], 'unita' => $unita], $x, $unita);
            $sp = fn(array $unita) => fn($x) => editor_pulisci_misura(['intervalli' => ['px' => [-20, 50, 0.1], 'em' => [-2, 5, 0.01]], 'unita' => $unita], $x, $unita);
            return [
                'famiglia' => $fam,
                'dimensione' => editor_pulisci_resp($val['dimensione'] ?? null, $mis(['px', 'em', 'rem', 'vw'])),
                'peso' => editor_pulisci_in(EDITOR_PESI, $val['peso'] ?? ''),
                'stile' => editor_pulisci_in(['', 'normal', 'italic'], $val['stile'] ?? ''),
                'trasformazione' => editor_pulisci_in(['', 'none', 'uppercase', 'lowercase', 'capitalize'], $val['trasformazione'] ?? ''),
                'decorazione' => editor_pulisci_in(['', 'none', 'underline', 'line-through'], $val['decorazione'] ?? ''),
                'interlinea' => editor_pulisci_resp($val['interlinea'] ?? null, $mis(['', 'px', 'em'])),
                'spaziatura_lettere' => editor_pulisci_resp($val['spaziatura_lettere'] ?? null, $sp(['px', 'em'])),
                'spaziatura_parole' => editor_pulisci_resp($val['spaziatura_parole'] ?? null, $sp(['px', 'em'])),
            ];
        case 'sfondo':
            if (!is_array($val)) $val = [];
            $sf = is_array($val['sfumatura'] ?? null) ? $val['sfumatura'] : [];
            $img = trim((string)($val['immagine'] ?? ''));
            return [
                'tipo' => editor_pulisci_in(['', 'classico', 'sfumatura'], $val['tipo'] ?? ''),
                'colore' => editor_pulisci_colore($val['colore'] ?? ''),
                'immagine' => preg_match('~^(https?://|/)[^"\'<>\s()]*$~', $img) ? mb_substr($img, 0, 500) : '',
                'posizione' => editor_pulisci_in(EDITOR_POSIZIONI_SFONDO, $val['posizione'] ?? '', 'center center'),
                'ripetizione' => editor_pulisci_in(['no-repeat', 'repeat', 'repeat-x', 'repeat-y'], $val['ripetizione'] ?? '', 'no-repeat'),
                'dimensione' => editor_pulisci_in(['cover', 'contain', 'auto'], $val['dimensione'] ?? '', 'cover'),
                'allegato' => editor_pulisci_in(['scroll', 'fixed'], $val['allegato'] ?? '', 'scroll'),
                'sfumatura' => [
                    'colore1' => editor_pulisci_colore($sf['colore1'] ?? ''), 'posizione1' => (int)editor_numero_o_vuoto($sf['posizione1'] ?? 0, [0, 100, 1]),
                    'colore2' => editor_pulisci_colore($sf['colore2'] ?? ''), 'posizione2' => editor_numero_o_vuoto($sf['posizione2'] ?? 100, [0, 100, 1]) === '' ? 100 : (int)editor_numero_o_vuoto($sf['posizione2'] ?? 100, [0, 100, 1]),
                    'tipo' => editor_pulisci_in(['linear', 'radial'], $sf['tipo'] ?? '', 'linear'), 'angolo' => (int)editor_numero_o_vuoto($sf['angolo'] ?? 180, [0, 360, 1]),
                ],
                'velo' => editor_pulisci_colore($val['velo'] ?? ''),
            ];
        case 'bordo':
            if (!is_array($val)) $val = [];
            return [
                'tipo' => editor_pulisci_in(['', 'none', 'solid', 'dashed', 'dotted', 'double'], $val['tipo'] ?? ''),
                'larghezza' => editor_pulisci_resp($val['larghezza'] ?? null, fn($x) => editor_pulisci_dimensioni(['intervalli' => ['px' => [0, 50, 1]]], $x, ['px'])),
                'colore' => editor_pulisci_colore($val['colore'] ?? ''),
                'raggio' => editor_pulisci_resp($val['raggio'] ?? null, fn($x) => editor_pulisci_dimensioni(['intervalli' => ['px' => [0, 400, 1], '%' => [0, 100, 1]]], $x, ['px', '%'])),
            ];
        case 'ombra':
            if (!is_array($val)) $val = [];
            return ['colore' => editor_pulisci_colore($val['colore'] ?? ''), 'x' => (int)editor_numero_o_vuoto($val['x'] ?? 0, [-100, 100, 1]), 'y' => (int)editor_numero_o_vuoto($val['y'] ?? 0, [-100, 100, 1]),
                'sfocatura' => (int)editor_numero_o_vuoto($val['sfocatura'] ?? 10, [0, 200, 1]), 'diffusione' => (int)editor_numero_o_vuoto($val['diffusione'] ?? 0, [-100, 100, 1]), 'posizione' => editor_pulisci_in(['', 'inset'], $val['posizione'] ?? '')];
        case 'ombra_testo':
            if (!is_array($val)) $val = [];
            return ['colore' => editor_pulisci_colore($val['colore'] ?? ''), 'x' => (int)editor_numero_o_vuoto($val['x'] ?? 0, [-100, 100, 1]), 'y' => (int)editor_numero_o_vuoto($val['y'] ?? 0, [-100, 100, 1]), 'sfocatura' => (int)editor_numero_o_vuoto($val['sfocatura'] ?? 10, [0, 200, 1])];
        case 'trasformazione':
            if (!is_array($val)) $val = [];
            $u = editor_pulisci_in(['px', '%'], $val['u'] ?? '', 'px');
            $sc = editor_numero_o_vuoto($val['scala'] ?? '', [0, 5, 0.01]);
            return ['rotazione' => (int)editor_numero_o_vuoto($val['rotazione'] ?? 0, [-360, 360, 1]), 'scala' => $sc === '' ? 1 : $sc, 'x' => editor_numero_o_vuoto($val['x'] ?? '', [-1000, 1000, 1]), 'y' => editor_numero_o_vuoto($val['y'] ?? '', [-1000, 1000, 1]), 'u' => $u];
    }
    return null;
}

/** Le opzioni di un controllo allineamento: left/center/right, più justify o le versioni flex. */
function editor_opzioni_allineamento(array $ctl): array {
    if (!empty($ctl['opzioni'])) return (array)$ctl['opzioni'];
    if (!empty($ctl['flex'])) return ['' => 'Predefinito', 'flex-start' => 'Inizio', 'center' => 'Centro', 'flex-end' => 'Fine'] + (!empty($ctl['stretch']) ? ['stretch' => 'Allarga'] : []);
    return ['' => 'Predefinito', 'left' => 'Sinistra', 'center' => 'Centro', 'right' => 'Destra'] + (!empty($ctl['giustifica']) ? ['justify' => 'Giustificato'] : []);
}

// ------------------------------------------------------------
// Strutture complete con i valori di partenza (per il disegno)
// ------------------------------------------------------------

/** La struttura vuota di un tipo di gruppo. */
function editor_gruppo_vuoto(string $tipo): array|string {
    switch ($tipo) {
        case 'misura': case 'cursore': return ['v' => '', 'u' => 'px'];
        case 'dimensioni': return ['sopra' => '', 'destra' => '', 'sotto' => '', 'sinistra' => '', 'u' => 'px', 'legati' => false];
        case 'tipografia': return ['famiglia' => '', 'dimensione' => ['d' => ['v' => '', 'u' => 'px']], 'peso' => '', 'stile' => '', 'trasformazione' => '', 'decorazione' => '',
            'interlinea' => ['d' => ['v' => '', 'u' => '']], 'spaziatura_lettere' => ['d' => ['v' => '', 'u' => 'px']], 'spaziatura_parole' => ['d' => ['v' => '', 'u' => 'px']]];
        case 'sfondo': return ['tipo' => '', 'colore' => '', 'immagine' => '', 'posizione' => 'center center', 'ripetizione' => 'no-repeat', 'dimensione' => 'cover', 'allegato' => 'scroll',
            'sfumatura' => ['colore1' => '', 'posizione1' => 0, 'colore2' => '', 'posizione2' => 100, 'tipo' => 'linear', 'angolo' => 180], 'velo' => ''];
        case 'bordo': return ['tipo' => '', 'larghezza' => ['d' => ['sopra' => '', 'destra' => '', 'sotto' => '', 'sinistra' => '', 'u' => 'px', 'legati' => true]], 'colore' => '',
            'raggio' => ['d' => ['sopra' => '', 'destra' => '', 'sotto' => '', 'sinistra' => '', 'u' => 'px', 'legati' => true]]];
        case 'ombra': return ['colore' => '', 'x' => 0, 'y' => 0, 'sfocatura' => 10, 'diffusione' => 0, 'posizione' => ''];
        case 'ombra_testo': return ['colore' => '', 'x' => 0, 'y' => 0, 'sfocatura' => 10];
        case 'trasformazione': return ['rotazione' => 0, 'scala' => 1, 'x' => '', 'y' => '', 'u' => 'px'];
        case 'galleria': return [];
    }
    return '';
}

/** Fonde ricorsivamente il valore salvato sopra la struttura di partenza (gli array a lista si prendono interi). */
function editor_fondi_struttura($pred, $val) {
    if (!is_array($pred)) return $val === null ? $pred : $val;
    if (!is_array($val) || $val === []) return $pred;
    if (array_is_list($pred) || array_is_list($val)) return $val;
    $out = $pred;
    foreach ($val as $k => $x) $out[$k] = isset($pred[$k]) && is_array($pred[$k]) ? editor_fondi_struttura($pred[$k], $x) : $x;
    // Nei sotto-campi responsive le misure t e m prendono la stessa struttura di d.
    foreach (['t', 'm'] as $k) if (isset($out[$k]) && is_array($out[$k]) && isset($pred['d']) && is_array($pred['d'])) $out[$k] = editor_fondi_struttura($pred['d'], $out[$k]);
    return $out;
}

/** Il valore normalizzato di un controllo di gruppo: struttura completa, con il predefinito dichiarato sopra quella vuota. */
function editor_normalizza_gruppo(string $tipo, $predefinito, $val) {
    $vuoto = editor_gruppo_vuoto($tipo);
    if (!is_array($vuoto)) return $val ?? $predefinito;
    $base = editor_fondi_struttura($vuoto, is_array($predefinito) ? $predefinito : null);
    return editor_fondi_struttura($base, is_array($val) ? $val : null);
}

function editor_e_gruppo(string $tipo): bool {
    return in_array($tipo, EDITOR_TIPI_GRUPPO, true);
}

// ------------------------------------------------------------
// CSS
// ------------------------------------------------------------

/** Scrive, per le tre misure, le proprietà che $propsDi ritorna per il valore di quella misura; non ripete i valori ereditati. */
function editor_css_resp(array &$css, string $sel, $r, callable $propsDi): void {
    $r = is_array($r) && (isset($r['d']) || isset($r['t']) || isset($r['m'])) ? $r : ['d' => $r];
    $prima = [];
    foreach (['d', 't', 'm'] as $m) {
        $x = editor_per($r, $m);
        if ($x === null || $x === '') continue;
        foreach ((array)$propsDi($x) as $p => $v) {
            if ($v === null || $v === '') continue;
            if (($prima[$p] ?? null) === $v) continue;
            $css[$m][$sel][$p] = $v;
            $prima[$p] = $v;
        }
    }
}

/** I quattro lati → [proprietà => valore]: la scorciatoia se ci sono tutti, altrimenti un lato per volta. */
function editor_css_lati(array $d, string $prop): array {
    $lati = ['sopra', 'destra', 'sotto', 'sinistra'];
    $u = (string)($d['u'] ?? 'px');
    $v = [];
    foreach ($lati as $l) $v[$l] = isset($d[$l]) && $d[$l] !== '' && is_numeric($d[$l]) ? ((float)$d[$l] == 0 ? '0' : $d[$l] . $u) : null;
    $set = array_filter($v, fn($x) => $x !== null);
    if (!$set) return [];
    if (count($set) === 4) return [$prop => implode(' ', $v)];
    $out = [];
    $nomi = $prop === 'border-radius' ? ['sopra' => 'border-top-left-radius', 'destra' => 'border-top-right-radius', 'sotto' => 'border-bottom-right-radius', 'sinistra' => 'border-bottom-left-radius']
        : ($prop === 'border-width' ? ['sopra' => 'border-top-width', 'destra' => 'border-right-width', 'sotto' => 'border-bottom-width', 'sinistra' => 'border-left-width']
        : ['sopra' => "$prop-top", 'destra' => "$prop-right", 'sotto' => "$prop-bottom", 'sinistra' => "$prop-left"]);
    foreach ($set as $l => $x) $out[$nomi[$l]] = $x;
    return $out;
}

function editor_css_modello(array $ctl, ?string $v): ?string {
    if ($v === null) return null;
    return !empty($ctl['valore_css']) ? str_replace('{{v}}', $v, $ctl['valore_css']) : $v;
}

/**
 * Il CSS di un controllo di gruppo (o di un controllo semplice con 'proprieta') su un selettore già finito.
 * $tipo: il nome del tipo oppure l'array del controllo (serve per proprieta, mappa, unita...). $stato: normale | hover.
 */
function editor_css_gruppo(array &$css, string $sel, $tipo, $valore, string $stato = 'normale'): void {
    $ctl = is_array($tipo) ? $tipo : ['tipo' => $tipo];
    $tipo = $ctl['tipo'];
    $prop = $ctl['proprieta'] ?? null;
    switch ($tipo) {
        case 'tipografia':
            if (!is_array($valore)) return;
            $v = editor_normalizza_gruppo('tipografia', null, $valore);
            $f = editor_font_css($v['famiglia']);
            if ($f) $css['d'][$sel]['font-family'] = $f;
            editor_css_resp($css, $sel, $v['dimensione'], fn($x) => ['font-size' => editor_misura_css($x)]);
            if ($v['peso'] !== '') $css['d'][$sel]['font-weight'] = $v['peso'];
            if ($v['stile'] !== '') $css['d'][$sel]['font-style'] = $v['stile'];
            if ($v['trasformazione'] !== '') $css['d'][$sel]['text-transform'] = $v['trasformazione'];
            if ($v['decorazione'] !== '') $css['d'][$sel]['text-decoration'] = $v['decorazione'];
            editor_css_resp($css, $sel, $v['interlinea'], fn($x) => ['line-height' => editor_misura_css($x)]);
            editor_css_resp($css, $sel, $v['spaziatura_lettere'], fn($x) => ['letter-spacing' => editor_misura_css($x)]);
            editor_css_resp($css, $sel, $v['spaziatura_parole'], fn($x) => ['word-spacing' => editor_misura_css($x)]);
            return;
        case 'sfondo':
            if (!is_array($valore)) return;
            $v = editor_normalizza_gruppo('sfondo', null, $valore);
            if ($v['tipo'] === 'classico') {
                $c = editor_colore_css($v['colore']);
                if ($c) $css['d'][$sel]['background-color'] = $c;
                if ($v['immagine'] !== '') {
                    $velo = editor_colore_css($v['velo']);
                    $css['d'][$sel]['background-image'] = ($velo ? "linear-gradient($velo,$velo)," : '') . "url('" . str_replace(["'", '"', ')', ' '], ['', '', '', '%20'], $v['immagine']) . "')";
                    $css['d'][$sel]['background-position'] = $v['posizione'];
                    $css['d'][$sel]['background-repeat'] = $v['ripetizione'];
                    $css['d'][$sel]['background-size'] = $v['dimensione'];
                    if ($v['allegato'] === 'fixed') $css['d'][$sel]['background-attachment'] = 'fixed';
                } elseif ($c) {
                    $css['d'][$sel]['background-image'] = 'none'; // il foglio di stile del sito usa spesso sfumature: il colore deve vincere
                }
            } elseif ($v['tipo'] === 'sfumatura') {
                $s = $v['sfumatura'];
                $c1 = editor_colore_css($s['colore1']) ?? 'transparent';
                $c2 = editor_colore_css($s['colore2']) ?? 'transparent';
                $css['d'][$sel]['background-image'] = $s['tipo'] === 'radial' ? "radial-gradient(circle at center,$c1 {$s['posizione1']}%,$c2 {$s['posizione2']}%)" : "linear-gradient({$s['angolo']}deg,$c1 {$s['posizione1']}%,$c2 {$s['posizione2']}%)";
                $css['d'][$sel]['background-color'] = 'transparent';
            }
            return;
        case 'bordo':
            if (!is_array($valore)) return;
            $v = editor_normalizza_gruppo('bordo', null, $valore);
            if ($v['tipo'] !== '') {
                $css['d'][$sel]['border-style'] = $v['tipo'];
                if ($v['tipo'] !== 'none') {
                    editor_css_resp($css, $sel, $v['larghezza'], fn($x) => editor_css_lati($x, 'border-width'));
                    $c = editor_colore_css($v['colore']);
                    if ($c) $css['d'][$sel]['border-color'] = $c;
                }
            } elseif ($stato === 'hover') {
                $c = editor_colore_css($v['colore']);
                if ($c) $css['d'][$sel]['border-color'] = $c;
            }
            editor_css_resp($css, $sel, $v['raggio'], fn($x) => editor_css_lati($x, 'border-radius'));
            return;
        case 'ombra':
            if (!is_array($valore)) return;
            $v = editor_normalizza_gruppo('ombra', null, $valore);
            $c = editor_colore_css($v['colore']);
            if ($c) $css['d'][$sel]['box-shadow'] = ($v['posizione'] === 'inset' ? 'inset ' : '') . "{$v['x']}px {$v['y']}px {$v['sfocatura']}px {$v['diffusione']}px $c";
            return;
        case 'ombra_testo':
            if (!is_array($valore)) return;
            $v = editor_normalizza_gruppo('ombra_testo', null, $valore);
            $c = editor_colore_css($v['colore']);
            if ($c) $css['d'][$sel]['text-shadow'] = "{$v['x']}px {$v['y']}px {$v['sfocatura']}px $c";
            return;
        case 'trasformazione':
            if (!is_array($valore)) return;
            $v = editor_normalizza_gruppo('trasformazione', null, $valore);
            $t = [];
            if ((float)$v['rotazione'] != 0) $t[] = "rotate({$v['rotazione']}deg)";
            if ((float)$v['scala'] != 1) $t[] = "scale({$v['scala']})";
            if (($v['x'] !== '' && (float)$v['x'] != 0) || ($v['y'] !== '' && (float)$v['y'] != 0)) $t[] = 'translate(' . ($v['x'] !== '' ? $v['x'] . $v['u'] : '0') . ',' . ($v['y'] !== '' ? $v['y'] . $v['u'] : '0') . ')';
            if ($t) $css['d'][$sel]['transform'] = implode(' ', $t);
            return;
        case 'dimensioni':
            if (!$prop) return;
            editor_css_resp($css, $sel, $valore, fn($x) => is_array($x) ? editor_css_lati($x, $prop) : []);
            return;
        case 'misura': case 'cursore':
            if (!$prop) return;
            editor_css_resp($css, $sel, $valore, fn($x) => [$prop => editor_css_modello($ctl, editor_misura_css($x))]);
            return;
        case 'allineamento': case 'scelta': case 'scelta_icone':
            if (!$prop) return;
            $mappa = $ctl['mappa'] ?? null;
            editor_css_resp($css, $sel, $valore, fn($x) => [$prop => ($x === '' || $x === null) ? null : editor_css_modello($ctl, (string)($mappa ? ($mappa[$x] ?? null) : $x))]);
            return;
        case 'colore':
            if (!$prop) return;
            editor_css_resp($css, $sel, $valore, fn($x) => [$prop => editor_colore_css($x)]);
            return;
        case 'carattere':
            $f = editor_font_css(is_array($valore) ? '' : $valore);
            if ($f) $css['d'][$sel][$prop ?: 'font-family'] = $f;
            return;
        case 'numero':
            if (!$prop) return;
            $u = is_string($ctl['unita'] ?? null) ? $ctl['unita'] : '';
            editor_css_resp($css, $sel, $valore, fn($x) => [$prop => is_numeric($x) ? editor_css_modello($ctl, $x . $u) : null]);
            return;
        case 'interruttore':
            if (!$prop) return;
            $on = $ctl['acceso'] ?? null;
            $off = $ctl['spento'] ?? null;
            if ($valore && $on !== null) $css['d'][$sel][$prop] = $on;
            elseif (!$valore && $off !== null) $css['d'][$sel][$prop] = $off;
            return;
        case 'testo':
            if (!$prop) return;
            $x = trim((string)(is_array($valore) ? '' : $valore));
            if ($x !== '' && !preg_match('/[;{}<>]/', $x)) $css['d'][$sel][$prop] = editor_css_modello($ctl, $x);
            return;
    }
}

/** Il selettore finito di un controllo: .ed-ID + selettore, per ogni pezzo separato da virgola; con lo stato hover dove va. */
function editor_selettore_controllo(string $sel, array $ctl, string $stato = 'normale'): string {
    $pezzi = array_map('rtrim', explode(',', (string)($ctl['selettore'] ?? '')));
    $out = [];
    foreach ($pezzi as $i => $p) {
        if ($i > 0) $p = ' ' . ltrim($p);
        if ($i > 0 && trim($p) === '') $p = '';
        if ($stato === 'hover') $out[] = ($ctl['hover_su'] ?? 'contenitore') === 'elemento' && trim($p) !== '' ? $sel . $p . ':hover' : $sel . ':hover' . $p;
        else $out[] = $sel . $p;
    }
    return implode(',', $out);
}

/** Il CSS automatico di un controllo con 'selettore' (anche con stati), dato il selettore base del widget. */
function editor_css_controllo(array &$css, string $sel, array $ctl, $valore): void {
    if (!isset($ctl['selettore'])) return;
    $tipo = $ctl['tipo'];
    if (!editor_e_gruppo($tipo) && empty($ctl['proprieta'])) return;
    if (!empty($ctl['stati']) && is_array($valore) && (array_key_exists('normale', $valore) || array_key_exists('hover', $valore))) {
        $selN = editor_selettore_controllo($sel, $ctl, 'normale');
        $selH = editor_selettore_controllo($sel, $ctl, 'hover');
        editor_css_gruppo($css, $selN, $ctl, $valore['normale'] ?? null, 'normale');
        $prima = count($css['d'][$selH] ?? []) + count($css['t'][$selH] ?? []) + count($css['m'][$selH] ?? []);
        editor_css_gruppo($css, $selH, $ctl, $valore['hover'] ?? null, 'hover');
        $dopo = count($css['d'][$selH] ?? []) + count($css['t'][$selH] ?? []) + count($css['m'][$selH] ?? []);
        if ($dopo > $prima && !isset($css['d'][$selN]['transition'])) $css['d'][$selN]['transition'] = 'all .25s';
        return;
    }
    editor_css_gruppo($css, editor_selettore_controllo($sel, $ctl), $ctl, $valore);
}

// ------------------------------------------------------------
// Scheda Avanzate, comune a sezioni, colonne e widget (come Elementor)
// ------------------------------------------------------------

/** I controlli della scheda Avanzate. $per: widget | colonna | sezione. */
function editor_controlli_avanzate(string $per = 'widget'): array {
    $A = 'avanzate';
    $c = [];
    // Layout
    $c[] = ec('margine', 'dimensioni', 'Margine', [], $A, ['sezione' => 'Layout', 'responsive' => true, 'selettore' => '', 'proprieta' => 'margin', 'unita' => ['px', '%', 'em', 'rem']]);
    $c[] = ec('padding', 'dimensioni', 'Spazio interno (padding)', [], $A, ['sezione' => 'Layout', 'responsive' => true, 'selettore' => '', 'proprieta' => 'padding', 'unita' => ['px', '%', 'em', 'rem'], 'intervalli' => ['px' => [0, 500, 1], '%' => [0, 100, 1], 'em' => [0, 20, 0.1], 'rem' => [0, 20, 0.1]]]);
    if ($per === 'widget') {
        $c[] = ec('margine_sotto', 'numero', 'Spazio sotto (px)', ['d' => 24], $A, ['sezione' => 'Layout', 'min' => 0, 'max' => 200, 'responsive' => true]);
        $c[] = ec('larghezza_widget', 'scelta_icone', 'Larghezza', ['d' => ''], $A, ['sezione' => 'Layout', 'responsive' => true, 'opzioni' => ['' => 'Piena', 'inline' => 'In linea (auto)', 'custom' => 'Personalizzata']]);
        $c[] = ec('larghezza_custom', 'misura', 'Larghezza personalizzata', ['d' => ['v' => 50, 'u' => '%']], $A, ['sezione' => 'Layout', 'responsive' => true, 'unita' => ['%', 'px', 'vw'], 'intervalli' => ['px' => [0, 1600, 1]], 'condizione' => ['larghezza_widget' => 'custom']]);
        $c[] = ec('allineamento_widget', 'allineamento', 'Allineamento del widget', ['d' => ''], $A, ['sezione' => 'Layout', 'responsive' => true, 'flex' => true, 'condizione' => ['larghezza_widget' => ['inline', 'custom']]]);
    }
    if ($per !== 'widget') $c[] = ec('tag_html', 'scelta', 'Tag HTML', '', $A, ['sezione' => 'Layout', 'opzioni' => ['' => $per === 'sezione' ? 'section' : 'div', 'div' => 'div', 'header' => 'header', 'footer' => 'footer', 'main' => 'main', 'article' => 'article', 'aside' => 'aside', 'nav' => 'nav'] + ($per === 'colonna' ? [] : ['section' => 'section'])]);
    $c[] = ec('z_index', 'numero', 'Z-index (chi sta sopra)', '', $A, ['sezione' => 'Layout', 'min' => -10, 'max' => 1000]);
    $c[] = ec('ancora', 'testo', 'ID CSS, usato anche come ancora (#nome)', '', $A, ['sezione' => 'Layout', 'aiuto' => 'Solo lettere minuscole, numeri e trattini. Serve ai link interni e al CSS personalizzato.']);
    $c[] = ec('classe', 'testo', 'Classi CSS', '', $A, ['sezione' => 'Layout', 'aiuto' => 'Separate da uno spazio.']);
    // Effetti di movimento
    $c[] = ec('animazione', 'scelta', "Animazione all'ingresso", '', $A, ['sezione' => 'Effetti di movimento', 'opzioni' => EDITOR_ANIMAZIONI]);
    $c[] = ec('animazione_durata', 'scelta', 'Durata', '', $A, ['sezione' => 'Effetti di movimento', 'opzioni' => ['lenta' => 'Lenta', '' => 'Normale', 'veloce' => 'Veloce'], 'condizione' => ['!animazione' => '']]);
    $c[] = ec('animazione_ritardo', 'numero', 'Ritardo (ms)', 0, $A, ['sezione' => 'Effetti di movimento', 'min' => 0, 'max' => 5000, 'passo' => 50, 'condizione' => ['!animazione' => '']]);
    $c[] = ec('sticky', 'scelta', 'Resta fermo scorrendo (sticky)', '', $A, ['sezione' => 'Effetti di movimento', 'opzioni' => ['' => 'No', 'alto' => 'In alto', 'basso' => 'In basso'], 'aiuto' => "L'elemento resta visibile mentre si scorre, finché dura il suo contenitore."]);
    $c[] = ec('sticky_offset', 'numero', 'Distanza dal bordo (px)', 0, $A, ['sezione' => 'Effetti di movimento', 'min' => 0, 'max' => 400, 'condizione' => ['!sticky' => '']]);
    $c[] = ec('sticky_effetti', 'numero', 'Classe "ed-sticky-attivo" dopo uno scorrimento di (px, 0 = mai)', 0, $A, ['sezione' => 'Effetti di movimento', 'min' => 0, 'max' => 2000, 'passo' => 10, 'condizione' => ['!sticky' => ''], 'aiuto' => 'Serve al CSS personalizzato: per esempio un header che cambia sfondo quando si scorre.']);
    if ($per === 'widget') {
        // Trasformazione, sfondo, bordo (sezioni e colonne li hanno nello Stile)
        $c[] = ec('trasformazione', 'trasformazione', 'Trasformazione', [], $A, ['sezione' => 'Trasformazione', 'stati' => true, 'selettore' => '']);
        $c[] = ec('sfondo_av', 'sfondo', 'Sfondo', [], $A, ['sezione' => 'Sfondo', 'stati' => true, 'selettore' => '']);
        $c[] = ec('bordo_av', 'bordo', 'Bordo', [], $A, ['sezione' => 'Bordo', 'stati' => true, 'selettore' => '']);
        $c[] = ec('ombra_av', 'ombra', 'Ombra', [], $A, ['sezione' => 'Bordo', 'stati' => true, 'selettore' => '']);
        $c[] = ec('transizione', 'numero', 'Durata della transizione al passaggio (ms)', '', $A, ['sezione' => 'Bordo', 'min' => 0, 'max' => 3000, 'passo' => 50]);
        // Posizionamento
        // 1.3.0: si chiamava "posizione", come la Posizione dell'icona e dell'immagine dei riquadri e l'Angolo del pulsante fisso,
        // e la pulizia di questa cancellava quelle. I salvataggi vecchi passano da editor_posizione_css_vecchia().
        $c[] = ec('posizione_css', 'scelta', 'Posizione', '', $A, ['sezione' => 'Posizionamento', 'opzioni' => ['' => 'Normale', 'absolute' => 'Assoluta (dentro la sezione)', 'fixed' => 'Fissa (rispetto alla finestra)']]);
        $c[] = ec('posizione_x_da', 'scelta_icone', 'Orizzontale da', 'sinistra', $A, ['sezione' => 'Posizionamento', 'opzioni' => ['sinistra' => 'Sinistra', 'destra' => 'Destra'], 'condizione' => ['!posizione_css' => '']]);
        $c[] = ec('posizione_x', 'misura', 'Spostamento orizzontale', ['d' => ['v' => 0, 'u' => 'px']], $A, ['sezione' => 'Posizionamento', 'responsive' => true, 'unita' => ['px', '%', 'vw'], 'intervalli' => ['px' => [-1000, 1000, 1], '%' => [-100, 100, 1], 'vw' => [-100, 100, 1]], 'condizione' => ['!posizione_css' => '']]);
        $c[] = ec('posizione_y_da', 'scelta_icone', 'Verticale da', 'alto', $A, ['sezione' => 'Posizionamento', 'opzioni' => ['alto' => 'Alto', 'basso' => 'Basso'], 'condizione' => ['!posizione_css' => '']]);
        $c[] = ec('posizione_y', 'misura', 'Spostamento verticale', ['d' => ['v' => 0, 'u' => 'px']], $A, ['sezione' => 'Posizionamento', 'responsive' => true, 'unita' => ['px', '%', 'vh'], 'intervalli' => ['px' => [-1000, 1000, 1], '%' => [-100, 100, 1], 'vh' => [-100, 100, 1]], 'condizione' => ['!posizione_css' => '']]);
    }
    // Responsive
    $c[] = ec('nascondi_d', 'interruttore', 'Nascondi su desktop', false, $A, ['sezione' => 'Responsive']);
    $c[] = ec('nascondi_t', 'interruttore', 'Nascondi su tablet', false, $A, ['sezione' => 'Responsive']);
    $c[] = ec('nascondi_m', 'interruttore', 'Nascondi su telefono', false, $A, ['sezione' => 'Responsive']);
    if ($per === 'sezione') {
        $c[] = ec('inverti_colonne_t', 'interruttore', "Inverti l'ordine delle colonne su tablet", false, $A, ['sezione' => 'Responsive']);
        $c[] = ec('inverti_colonne_m', 'interruttore', "Inverti l'ordine delle colonne su telefono", false, $A, ['sezione' => 'Responsive']);
    }
    // Attributi e CSS
    $c[] = ec('attributi', 'testolungo', 'Attributi (chiave=valore, uno per riga)', '', $A, ['sezione' => 'Attributi', 'aiuto' => 'Ammessi: data-*, aria-*, title, lang, role, tabindex, rel, itemprop.']);
    $c[] = ec('css_personalizzato', 'codice', 'CSS personalizzato', '', $A, ['sezione' => 'CSS personalizzato', 'aiuto' => 'Scrivi "selector" per indicare questo elemento: selector .titolo { color: red }. Ogni regola vale solo dentro questo elemento.']);
    return $c;
}

/**
 * Fino alla 1.3.0 la Posizione delle Avanzate si salvava in "posizione", lo stesso nome della Posizione dell'icona o
 * dell'immagine (riquadri) e dell'Angolo (pulsante fisso). Un "absolute" o "fixed" senza "posizione_css" viene da li:
 * passa in "posizione_css" e "posizione" torna libera (il widget riprende il suo valore di partenza, come prima).
 */
function editor_posizione_css_vecchia(array $imp): array {
    if (!array_key_exists('posizione_css', $imp) && in_array($imp['posizione'] ?? null, ['absolute', 'fixed'], true)) {
        $imp['posizione_css'] = $imp['posizione'];
        unset($imp['posizione']);
    }
    return $imp;
}

/** Pulizia dei campi speciali della scheda Avanzate (dopo editor_pulisci_valore). */
function editor_pulisci_avanzate(array &$out): void {
    if (isset($out['ancora'])) $out['ancora'] = preg_replace('/[^a-z0-9-]/', '', strtolower($out['ancora']));
    if (isset($out['classe'])) $out['classe'] = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 _-]/', '', $out['classe'])));
    if (isset($out['attributi'])) {
        $righe = [];
        foreach (preg_split('/\r?\n/', (string)$out['attributi']) as $r) {
            if (!preg_match('/^\s*(data-[a-z0-9-]+|aria-[a-z-]+|title|lang|role|tabindex|rel|itemprop|itemscope|itemtype)\s*=\s*(.{0,300})$/i', $r, $m)) continue;
            $righe[] = strtolower($m[1]) . '=' . trim($m[2]);
        }
        $out['attributi'] = implode("\n", array_slice($righe, 0, 20));
    }
    if (isset($out['css_personalizzato'])) {
        // sicurezza: via import, expression, behavior, binding, url() esterni; il confinamento a .ed-ID avviene alla resa (editor_css_avanzate).
        require_once __DIR__ . '/sicurezza-html.php';
        $out['css_personalizzato'] = sicurezza_css_pulisci(mb_substr((string)$out['css_personalizzato'], 0, 20000));
    }
}

/** Gli attributi dell'elemento (classi, attributi liberi) decisi dalla scheda Avanzate. Ritorna ['classi' => ' a b', 'attr' => ' data-x="y"']. */
function editor_attributi_avanzate(array $v): array {
    $classi = '';
    $attr = '';
    // menu: con le animazioni spente in Impostazioni dell'editor le classi non escono e il sito resta fermo.
    if (($v['animazione'] ?? '') !== '' && isset(EDITOR_ANIMAZIONI[$v['animazione']]) && editor_animazioni_attive()) {
        $classi .= ' ed-anima ed-anima-' . $v['animazione'] . (($v['animazione_durata'] ?? '') !== '' ? ' ed-anima-' . $v['animazione_durata'] : '');
    }
    if (($v['sticky'] ?? '') !== '') $classi .= ' ed-sticky ed-sticky-' . $v['sticky'];
    if (($v['sticky'] ?? '') !== '' && (int)($v['sticky_effetti'] ?? 0) > 0) $attr .= ' data-ed-sticky-dopo="' . (int)$v['sticky_effetti'] . '"';
    if (!empty($v['inverti_colonne_m'])) $classi .= ' ed-inverti-m';
    if (!empty($v['inverti_colonne_t'])) $classi .= ' ed-inverti-t';
    foreach (preg_split('/\r?\n/', (string)($v['attributi'] ?? '')) as $r) {
        if ($r === '' || !str_contains($r, '=')) continue;
        [$k, $x] = explode('=', $r, 2);
        $k = strtolower(trim($k));
        if (!preg_match('/^(data-[a-z0-9-]+|aria-[a-z-]+|title|lang|role|tabindex|rel|itemprop|itemscope|itemtype)$/', $k) || in_array($k, ['class', 'id', 'style'], true)) continue;
        $attr .= ' ' . $k . '="' . h(trim($x)) . '"';
    }
    return ['classi' => $classi, 'attr' => $attr];
}

/** Il CSS della scheda Avanzate che il selettore automatico non copre: larghezza, z-index, posizione, animazione, sticky, CSS libero. */
function editor_css_avanzate(array &$css, string $sel, array $v, string $per = 'widget'): void {
    if ($per === 'widget') {
        $largh = $v['larghezza_widget'] ?? ['d' => ''];
        $custom = $v['larghezza_custom'] ?? ['d' => ['v' => '', 'u' => '%']];
        editor_css_resp($css, $sel, $largh, function ($x) { return $x === 'inline' ? ['width' => 'auto', 'max-width' => '100%'] : ($x === 'custom' ? ['max-width' => '100%'] : ($x === '' ? ['width' => '100%'] : [])); });
        // Il valore '' (piena) scrive width:100% solo se un'altra misura ha cambiato la larghezza: via la riga inutile sul desktop.
        if (($css['d'][$sel]['width'] ?? '') === '100%' && editor_per($largh, 'd') === '') unset($css['d'][$sel]['width']);
        foreach (['d', 't', 'm'] as $m) {
            if (editor_per($largh, $m) === 'custom') {
                $w = editor_misura_css(editor_per($custom, $m));
                if ($w) $css[$m][$sel]['width'] = $w;
            }
        }
        editor_css_resp($css, $sel, $v['allineamento_widget'] ?? ['d' => ''], fn($x) => ['align-self' => $x === '' ? null : $x]);
        $pos = $v['posizione_css'] ?? '';
        if ($pos === '' && in_array($v['posizione'] ?? '', ['absolute', 'fixed'], true)) $pos = $v['posizione']; // valori di prima della 1.3.0
        if ($pos === 'absolute' || $pos === 'fixed') {
            $css['d'][$sel]['position'] = $pos;
            $px = ($v['posizione_x_da'] ?? 'sinistra') === 'destra' ? 'right' : 'left';
            $py = ($v['posizione_y_da'] ?? 'alto') === 'basso' ? 'bottom' : 'top';
            editor_css_resp($css, $sel, $v['posizione_x'] ?? ['d' => ['v' => 0, 'u' => 'px']], fn($x) => [$px => editor_misura_css($x) ?? '0']);
            editor_css_resp($css, $sel, $v['posizione_y'] ?? ['d' => ['v' => 0, 'u' => 'px']], fn($x) => [$py => editor_misura_css($x) ?? '0']);
            if (!isset($css['d'][$sel]['width']) && !isset($css['d'][$sel]['max-width'])) $css['d'][$sel]['width'] = 'auto';
            $css['d'][$sel]['margin-bottom'] = '0';
        }
        $tr = $v['transizione'] ?? '';
        if ($tr !== '' && is_numeric($tr) && (int)$tr >= 0) $css['d'][$sel]['transition'] = 'all ' . (int)$tr . 'ms';
    }
    $z = $v['z_index'] ?? '';
    if ($z !== '' && is_numeric($z)) {
        $css['d'][$sel]['z-index'] = (int)$z;
        if (!isset($css['d'][$sel]['position'])) $css['d'][$sel]['position'] = 'relative';
    }
    if (($v['animazione'] ?? '') !== '') {
        $css['anima'] = true;
        $r = (int)($v['animazione_ritardo'] ?? 0);
        if ($r > 0) $css['d'][$sel]['animation-delay'] = $r . 'ms';
    }
    if (($v['sticky'] ?? '') !== '') {
        $css['d'][$sel]['position'] = 'sticky';
        $css['d'][$sel][$v['sticky'] === 'basso' ? 'bottom' : 'top'] = (int)($v['sticky_offset'] ?? 0) . 'px';
        if (!isset($css['d'][$sel]['z-index'])) $css['d'][$sel]['z-index'] = 20;
    }
    if (!empty($v['inverti_colonne_m']) && $per === 'sezione') $css['m']["$sel>.ed-contenitore>.ed-riga"]['flex-direction'] = 'column-reverse';
    if (!empty($v['inverti_colonne_t']) && $per === 'sezione') $css['t']["$sel>.ed-contenitore>.ed-riga"]['flex-direction'] = 'row-reverse';
    $libero = trim((string)($v['css_personalizzato'] ?? ''));
    // sicurezza: ogni regola resta dentro l'elemento (.ed-ID), anche "body{...}" diventa ".ed-ID body{...}" e non tocca la pagina.
    if ($libero !== '') { require_once __DIR__ . '/sicurezza-html.php'; $c = sicurezza_css_confina($libero, $sel); if ($c !== '') $css['raw'][] = $c; }
}

/** Le regole fisse delle animazioni all'ingresso: entrano nel CSS della pagina solo se qualche elemento le usa. */
function editor_css_animazioni(): string {
    return '.ed-anima-attesa{opacity:0}'
        . '.ed-anima-via{animation-duration:.8s;animation-fill-mode:both;animation-timing-function:cubic-bezier(.2,.7,.2,1)}'
        . '.ed-anima-via.ed-anima-lenta{animation-duration:1.4s}.ed-anima-via.ed-anima-veloce{animation-duration:.4s}'
        . '.ed-anima-via.ed-anima-dissolvenza{animation-name:edDissolvenza}.ed-anima-via.ed-anima-dal_basso{animation-name:edDalBasso}.ed-anima-via.ed-anima-dall_alto{animation-name:edDallAlto}'
        . '.ed-anima-via.ed-anima-da_sinistra{animation-name:edDaSinistra}.ed-anima-via.ed-anima-da_destra{animation-name:edDaDestra}.ed-anima-via.ed-anima-zoom{animation-name:edZoom}'
        . '@keyframes edDissolvenza{from{opacity:0}to{opacity:1}}@keyframes edDalBasso{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:none}}'
        . '@keyframes edDallAlto{from{opacity:0;transform:translateY(-40px)}to{opacity:1;transform:none}}@keyframes edDaSinistra{from{opacity:0;transform:translateX(-40px)}to{opacity:1;transform:none}}'
        . '@keyframes edDaDestra{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none}}@keyframes edZoom{from{opacity:0;transform:scale(.85)}to{opacity:1;transform:none}}'
        . '@media (prefers-reduced-motion:reduce){.ed-anima-attesa{opacity:1}.ed-anima-via{animation:none}}';
}

/** Il tag HTML di una sezione o colonna (scheda Avanzate, Layout). */
function editor_tag_html(array $v, string $pred): string {
    $t = (string)($v['tag_html'] ?? '');
    return in_array($t, ['div', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'section'], true) ? $t : $pred;
}
