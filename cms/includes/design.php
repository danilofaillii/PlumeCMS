<?php
/**
 * Stile del sito: le impostazioni globali (le "Site Settings" di Elementor).
 *
 * Colori e caratteri globali, tipografia per elemento (corpo, paragrafi, link, H1..H6), pulsanti, immagini,
 * campi dei moduli, layout (larghezza, spazi, punti di rottura), sfondo del sito, identità del sito, CSS personalizzato.
 *
 * Tabella newdf_design: una riga per sezione (chiave = nome della sezione, valore = JSON), più la riga `_precedente`
 * con l'insieme salvato prima dell'ultimo salvataggio (per tornare indietro). La crea design_installa() alla prima lettura.
 *
 * Regola di fondo: con i valori di partenza il sito è identico a oggi. design_css() scrive le variabili --g-* su :root,
 * fa da ponte con le variabili di sito.css (--viola, --ciano, --testo, --bg, --f-titolo...) e scrive una regola per
 * elemento solo quando Danilo ha impostato un valore. Un valore vuoto vuol dire "come il sito".
 *
 * Contratto per gli altri file: $S/audit3/DESIGN-CONTRATTO.md (riassunto nei commenti qui sotto).
 *   design_valori()                 tutti i valori (salvati fusi sui predefiniti), cache per richiesta
 *   design_css() / design_head()    il CSS e quello che va nel <head> dopo sito.css (solo stile: i tag di analisi sono di seo.php)
 *   design_css_valore($v, $tipo)    'globale:primario' -> 'var(--g-colore-primario)'; 'Lora' (font) -> '"Lora",serif'
 *   design_globali()                colori e font globali per il pannello dell'editor
 *   design_sito()                   nome, sottotitolo, descrizione, logo, favicon
 *   design_breakpoint('tablet')     le soglie in px
 */

require_once __DIR__ . '/db.php';

const DESIGN_VERSIONE = 1;
const DESIGN_SEZIONI = ['colori', 'font', 'tipografia', 'pulsanti', 'immagini', 'campi', 'layout', 'sfondo', 'sito', 'css'];
const DESIGN_SISTEMA_COLORI = ['primario' => 'Primario', 'secondario' => 'Secondario', 'testo' => 'Testo', 'accento' => 'Accento', 'sfondo' => 'Sfondo del sito'];
const DESIGN_SISTEMA_FONT = ['primario' => 'Primario', 'secondario' => 'Secondario', 'testo' => 'Testo', 'accento' => 'Accento'];
const DESIGN_PESI = ['' => 'Come il sito', '100' => '100 (sottile)', '200' => '200', '300' => '300 (leggero)', '400' => '400 (normale)', '500' => '500 (medio)', '600' => '600 (semigrassetto)', '700' => '700 (grassetto)', '800' => '800', '900' => '900 (nero)'];
const DESIGN_STILI = ['' => 'Come il sito', 'normal' => 'Normale', 'italic' => 'Corsivo'];
const DESIGN_TRASFORMAZIONI = ['' => 'Come il sito', 'none' => 'Nessuna', 'uppercase' => 'MAIUSCOLO', 'lowercase' => 'minuscolo', 'capitalize' => 'Iniziali Maiuscole'];
const DESIGN_DECORAZIONI = ['' => 'Come il sito', 'none' => 'Nessuna', 'underline' => 'Sottolineato', 'line-through' => 'Barrato', 'overline' => 'Linea sopra'];
const DESIGN_BORDI = ['' => 'Come il sito', 'none' => 'Nessuno', 'solid' => 'Continuo', 'dashed' => 'Tratteggiato', 'dotted' => 'Puntinato', 'double' => 'Doppio'];
const DESIGN_SFONDO_POSIZIONI = ['center center' => 'Centro', 'center top' => 'Centro in alto', 'center bottom' => 'Centro in basso', 'left top' => 'Sinistra in alto', 'left center' => 'Sinistra al centro', 'left bottom' => 'Sinistra in basso', 'right top' => 'Destra in alto', 'right center' => 'Destra al centro', 'right bottom' => 'Destra in basso'];
const DESIGN_SFONDO_RIPETIZIONI = ['no-repeat' => 'Nessuna', 'repeat' => 'Ripeti', 'repeat-x' => 'Ripeti in orizzontale', 'repeat-y' => 'Ripeti in verticale'];
const DESIGN_SFONDO_DIMENSIONI = ['cover' => 'Copri tutto', 'contain' => 'Contieni', 'auto' => 'Dimensione reale'];
const DESIGN_SFONDO_ALLEGATI = ['scroll' => 'Scorre con la pagina', 'fixed' => 'Fissa'];

// ------------------------------------------------------------
// Famiglie di carattere
// ------------------------------------------------------------

/**
 * Le famiglie che si possono scegliere. "In casa" sono servite dal sito (sito/assets/font); "Sistema" sono quelle
 * del computer di chi legge; "Google Fonts" si caricano da Google solo se davvero scelte da qualche parte.
 * Ogni voce: css (la famiglia completa con il ripiego), gruppo, google (il parametro per il <link>, solo per Google).
 */
function design_font_elenco(): array {
    static $e = null;
    if ($e !== null) return $e;
    $e = [
        'Inter' => ['css' => '"Inter",system-ui,sans-serif', 'gruppo' => 'In casa'],
        'Space Grotesk' => ['css' => '"Space Grotesk",system-ui,sans-serif', 'gruppo' => 'In casa'],
        'Carattere del sistema' => ['css' => 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'gruppo' => 'Sistema'],
        'Arial' => ['css' => 'Arial,Helvetica,sans-serif', 'gruppo' => 'Sistema'],
        'Helvetica' => ['css' => 'Helvetica,Arial,sans-serif', 'gruppo' => 'Sistema'],
        'Verdana' => ['css' => 'Verdana,Geneva,sans-serif', 'gruppo' => 'Sistema'],
        'Tahoma' => ['css' => 'Tahoma,Geneva,sans-serif', 'gruppo' => 'Sistema'],
        'Trebuchet MS' => ['css' => '"Trebuchet MS",Helvetica,sans-serif', 'gruppo' => 'Sistema'],
        'Georgia' => ['css' => 'Georgia,"Times New Roman",serif', 'gruppo' => 'Sistema'],
        'Times New Roman' => ['css' => '"Times New Roman",Times,serif', 'gruppo' => 'Sistema'],
        'Garamond' => ['css' => 'Garamond,Georgia,serif', 'gruppo' => 'Sistema'],
        'Palatino' => ['css' => '"Palatino Linotype",Palatino,Georgia,serif', 'gruppo' => 'Sistema'],
        'Courier New' => ['css' => '"Courier New",Courier,monospace', 'gruppo' => 'Sistema'],
        'Monospazio del sistema' => ['css' => 'ui-monospace,Menlo,Consolas,monospace', 'gruppo' => 'Sistema'],
    ];
    $google = [
        'sans-serif' => ['Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Raleway', 'Nunito', 'Nunito Sans', 'Work Sans', 'DM Sans', 'Manrope', 'Rubik', 'Outfit', 'Plus Jakarta Sans', 'Source Sans 3', 'Oswald', 'Fira Sans', 'IBM Plex Sans', 'Josefin Sans', 'Quicksand', 'Mulish', 'Karla', 'Barlow', 'Archivo', 'Figtree', 'Lexend', 'Urbanist', 'Sora', 'Be Vietnam Pro'],
        'serif' => ['Playfair Display', 'Merriweather', 'Lora', 'PT Serif', 'Libre Baskerville', 'EB Garamond', 'Crimson Text', 'Cormorant Garamond', 'Fraunces', 'Bitter', 'Roboto Slab', 'IBM Plex Serif', 'Source Serif 4', 'Spectral', 'Literata', 'Noto Serif', 'DM Serif Display', 'Libre Caslon Text'],
        'monospace' => ['Fira Code', 'JetBrains Mono', 'Roboto Mono', 'Source Code Pro', 'IBM Plex Mono', 'Space Mono'],
    ];
    foreach ($google as $generico => $nomi) {
        foreach ($nomi as $n) {
            $e[$n] = ['css' => '"' . $n . '",' . $generico, 'gruppo' => 'Google Fonts', 'google' => str_replace(' ', '+', $n) . ':wght@400;500;600;700'];
        }
    }
    return $e;
}

/** La famiglia CSS completa di un nome dell'elenco; '' se non c'è. */
function design_font_css_famiglia(string $nome): string {
    return design_font_elenco()[$nome]['css'] ?? '';
}

// ------------------------------------------------------------
// Valori di partenza: l'aspetto di oggi (sito/assets/sito.css)
// ------------------------------------------------------------

/** Un gruppo tipografico vuoto: ogni voce vuota eredita dal sito. */
function design_tipografia_vuota(array $con = []): array {
    return array_replace([
        'famiglia' => '', 'dimensione' => ['d' => '', 't' => '', 'm' => ''], 'peso' => '', 'stile' => '', 'trasformazione' => '', 'decorazione' => '',
        'interlinea' => ['d' => '', 't' => '', 'm' => ''], 'spaziatura_lettere' => '', 'spaziatura_parole' => '',
    ], $con);
}

function design_predefiniti(): array {
    static $p = null;
    if ($p !== null) return $p;
    $tip = fn(array $c = []) => design_tipografia_vuota($c);
    $elemento = fn() => ['colore' => '', 'tipografia' => $tip()];
    // I valori generici del CMS (il tema di partenza, sito.css); il modulo del sito li riscrive con i suoi (filtro design_predefiniti).
    $p = [
        'colori' => [
            'sistema' => ['primario' => '#a855f7', 'secondario' => '#22d3ee', 'testo' => '#e6ebf5', 'accento' => '#4ade80', 'sfondo' => '#060a17'],
            'personalizzati' => [
                ['id' => 'sfondo-2', 'nome' => 'Sfondo secondario', 'valore' => '#0a1024'],
                ['id' => 'testo-2', 'nome' => 'Testo attenuato', 'valore' => '#a7b1c6'],
                ['id' => 'testo-3', 'nome' => 'Testo discreto', 'valore' => '#6f7a92'],
                ['id' => 'bordo', 'nome' => 'Bordi', 'valore' => 'rgba(140,190,255,.16)'],
            ],
        ],
        'font' => [
            'sistema' => [
                'primario' => $tip(['famiglia' => 'Space Grotesk']), 'secondario' => $tip(['famiglia' => 'Space Grotesk']),
                'testo' => $tip(['famiglia' => 'Inter']), 'accento' => $tip(['famiglia' => 'Inter']),
            ],
            'personalizzati' => [],
        ],
        'tipografia' => [
            'corpo' => ['colore' => '', 'tipografia' => $tip(), 'spazio_paragrafi' => ''],
            'paragrafo' => $elemento(),
            'link' => $elemento(), 'link_hover' => $elemento(),
            'h1' => $elemento(), 'h2' => $elemento(), 'h3' => $elemento(), 'h4' => $elemento(), 'h5' => $elemento(), 'h6' => $elemento(),
        ],
        'pulsanti' => [
            'tipografia' => $tip(), 'ombra_testo' => '',
            'normale' => ['colore' => '', 'sfondo' => '', 'bordo_colore' => '', 'ombra' => ''],
            'hover' => ['colore' => '', 'sfondo' => '', 'bordo_colore' => '', 'ombra' => ''],
            'bordo_tipo' => '', 'bordo_larghezza' => '', 'raggio' => '', 'padding' => '',
        ],
        'immagini' => [
            'raggio' => '', 'bordo_tipo' => '', 'bordo_larghezza' => '', 'bordo_colore' => '', 'opacita' => '', 'ombra' => '',
            'filtri' => ['sfocatura' => '', 'luminosita' => '', 'contrasto' => '', 'saturazione' => '', 'tonalita' => ''],
            'hover' => ['opacita' => '', 'bordo_colore' => '', 'ombra' => '', 'filtri' => ['sfocatura' => '', 'luminosita' => '', 'contrasto' => '', 'saturazione' => '', 'tonalita' => '']],
            'transizione' => '',
        ],
        'campi' => [
            'etichetta' => ['colore' => '', 'tipografia' => $tip()],
            'campo' => ['tipografia' => $tip(), 'colore' => '', 'sfondo' => '', 'bordo_colore' => '', 'bordo_larghezza' => '', 'raggio' => '', 'padding' => ''],
            'fuoco' => ['colore' => '', 'sfondo' => '', 'bordo_colore' => '', 'contorno' => ''],
        ],
        'layout' => ['larghezza' => 1180, 'spazio_widget' => 24, 'spazio_colonne' => 32, 'tablet' => 1024, 'telefono' => 767],
        'sfondo' => ['colore' => '', 'immagine' => '', 'posizione' => 'center center', 'ripetizione' => 'no-repeat', 'dimensione' => 'cover', 'allegato' => 'scroll', 'velo' => '', 'sfumature_sito' => true],
        'sito' => [
            'nome' => (string)cms_impostazione('sito_nome', CMS_NOME), 'sottotitolo' => (string)cms_impostazione('sito_sottotitolo', ''),
            'descrizione' => (string)cms_impostazione('sito_frase', cms_impostazione('sito_descrizione', '')),
            'logo' => '', 'favicon' => (string)cms_impostazione('sito_favicon', ''),
        ],
        'css' => ['sito' => ''],  // i tag di analisi e il codice nell'head sono della voce SEO (includes/seo.php), non qui
    ];
    $p = cms_filtro('design_predefiniti', $p);
    return $p;
}

// ------------------------------------------------------------
// Tabella, lettura, salvataggio
// ------------------------------------------------------------

function design_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('design') . " (
        `chiave` VARCHAR(40) NOT NULL,
        `valore` MEDIUMTEXT NOT NULL,
        `versione` INT(11) NOT NULL DEFAULT 1,
        `aggiornato` DATETIME NOT NULL,
        `autore` VARCHAR(100) NOT NULL DEFAULT '',
        PRIMARY KEY (`chiave`)
    )");
}

/** Le righe salvate: [chiave => array]. Senza database o senza tabella: niente (restano i predefiniti). */
function design_righe_salvate(): array {
    try {
        $leggi = fn() => db()->query('SELECT chiave, valore FROM ' . tbl('design'))->fetchAll();
        try {
            $righe = $leggi();
        } catch (PDOException $e) {
            design_installa();
            $righe = $leggi();
        }
        $out = [];
        foreach ($righe as $r) {
            $v = json_decode((string)$r['valore'], true);
            if (is_array($v)) $out[$r['chiave']] = $v;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Fonde i valori salvati sui predefiniti: le chiavi mancanti prendono il valore di partenza,
 * gli elenchi (colori e font personalizzati) si prendono così come sono stati salvati.
 */
function design_fondi(array $base, array $sopra): array {
    foreach ($sopra as $k => $v) {
        if (!array_key_exists($k, $base)) continue;
        if ($k === 'personalizzati') { $base[$k] = is_array($v) ? array_values($v) : []; continue; }
        if (is_array($base[$k]) && is_array($v)) $base[$k] = design_fondi($base[$k], $v);
        elseif (!is_array($base[$k])) $base[$k] = is_array($v) ? $base[$k] : $v;
    }
    return $base;
}

/** Tutti i valori dello stile, con cache per richiesta. */
function design_valori(bool $ricarica = false): array {
    static $cache = null;
    if ($cache !== null && !$ricarica) return $cache;
    $v = design_predefiniti();
    foreach (design_righe_salvate() as $k => $r) {
        if (in_array($k, DESIGN_SEZIONI, true)) $v[$k] = design_fondi($v[$k], $r);
    }
    $cache = design_pulisci($v);
    return $cache;
}

/** Vero se qualcosa è stato salvato (anche uguale ai predefiniti). */
function design_ha_salvataggi(): bool {
    return (bool)array_intersect(array_keys(design_righe_salvate()), DESIGN_SEZIONI);
}

/** C'è una versione precedente da richiamare? */
function design_ha_precedente(): bool {
    return isset(design_righe_salvate()['_precedente']);
}

/** Salva tutte le sezioni, dopo la pulizia. La versione di prima resta in `_precedente`. */
function design_salva(array $valori, string $autore = '', string $nota = ''): void {
    design_installa();
    $pulito = design_pulisci(design_fondi(design_predefiniti(), $valori));
    $prima = array_intersect_key(design_righe_salvate(), array_flip(DESIGN_SEZIONI));
    $st = db()->prepare('INSERT INTO ' . tbl('design') . ' (chiave, valore, versione, aggiornato, autore) VALUES (?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore), versione = VALUES(versione), aggiornato = NOW(), autore = VALUES(autore)');
    $autore = mb_substr($autore, 0, 100);
    db()->beginTransaction();
    try {
        foreach (DESIGN_SEZIONI as $k) $st->execute([$k, design_json($pulito[$k]), DESIGN_VERSIONE, $autore]);
        $st->execute(['_precedente', design_json($prima), DESIGN_VERSIONE, $autore]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    design_valori(true);
    // versioni: una copia dello stile a ogni salvataggio che cambia qualcosa.
    design_versione($pulito, $autore, $nota);
}

/** versioni: registra lo stile in {prefisso}editor_versioni (ambito "stile"). Mai un errore da qui. */
function design_versione(array $valori, string $autore = '', string $nota = ''): void {
    try {
        require_once __DIR__ . '/versioni.php';
        editor_versione_salva('stile', 0, 'Stile del sito', $valori, $nota, ['autore' => $autore]);
    } catch (Throwable $e) {
        error_log('versioni (stile): ' . $e->getMessage());
    }
}

/** Torna all'insieme salvato prima dell'ultimo salvataggio. */
function design_torna_indietro(string $autore = ''): bool {
    $righe = design_righe_salvate();
    if (!isset($righe['_precedente'])) return false;
    $prec = $righe['_precedente'];
    if (!$prec) { design_ripristina(); return true; }
    design_salva(design_fondi(design_predefiniti(), $prec), $autore, 'versione precedente richiamata');
    return true;
}

/** Cancella ogni impostazione: il sito torna com'è oggi. */
function design_ripristina(): void {
    design_installa();
    db()->exec('DELETE FROM ' . tbl('design'));
    design_valori(true);
    design_versione(design_pulisci(design_predefiniti()), '', 'ripristinati i predefiniti'); // versioni
}

/** JSON sicuro per la colonna a 3 byte: i caratteri fuori dal piano base restano scritti come \uXXXX. */
function design_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_SLASHES);
}

// ------------------------------------------------------------
// Pulizia e validazione
// ------------------------------------------------------------

function design_slug(string $s): string {
    $s = strtolower(trim($s));
    if (function_exists('iconv')) $s = (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim(substr($s, 0, 40), '-');
}

/** Gli id dei colori globali esistenti (sistema e personalizzati). */
function design_colori_id(?array $valori = null): array {
    $v = $valori ?? design_valori();
    $ids = array_keys(DESIGN_SISTEMA_COLORI);
    foreach ($v['colori']['personalizzati'] as $c) $ids[] = $c['id'];
    return $ids;
}

function design_font_id(?array $valori = null): array {
    $v = $valori ?? design_valori();
    $ids = array_keys(DESIGN_SISTEMA_FONT);
    foreach ($v['font']['personalizzati'] as $c) $ids[] = $c['id'];
    return $ids;
}

/** Un colore: hex 3/4/6/8, rgb(), rgba(), hsl(), hsla(), transparent, oppure globale:<id>. Vuoto = come il sito. */
function design_colore_valido(string $v, bool $globali = true): bool {
    $v = trim($v);
    if ($v === '') return true;
    if (preg_match('/^globale:[a-z0-9-]{1,40}$/', $v)) return $globali;
    return (bool)preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+%?\s*)?\)|hsla?\(\s*[\d.]+\s*,\s*[\d.]+%\s*,\s*[\d.]+%\s*(,\s*[\d.]+%?\s*)?\)|transparent)$/', $v);
}

/** Una famiglia: vuota, globale:font-<id> / globale:<id>, oppure un nome dell'elenco. */
function design_font_valido(string $v): bool {
    $v = trim($v);
    if ($v === '' || preg_match('/^globale:(font-)?[a-z0-9-]{1,40}$/', $v)) return true;
    return isset(design_font_elenco()[$v]);
}

/** Un numero con unità ("18px", "1.2em", "90%"): vuoto se non valido. $senza: anche il numero puro. */
function design_misura(string $v, bool $senza = false, bool $negativo = false): string {
    $v = str_replace(',', '.', trim($v));
    if ($v === '') return '';
    if (preg_match('/^(-?)(\d+(?:\.\d+)?)(px|em|rem|%|vw|vh|ch)?$/', $v, $m)) {
        if ($m[1] === '-' && !$negativo) return '';
        if (($m[3] ?? '') === '') return $senza ? $m[2] : $m[1] . $m[2] . 'px';
        return $m[1] . $m[2] . $m[3];
    }
    return '';
}

/** Fino a quattro misure separate da spazio (padding, raggio, larghezza del bordo). */
function design_misure(string $v, int $max = 4): string {
    $out = [];
    foreach (preg_split('/\s+/', trim($v)) as $x) {
        if ($x === '') continue;
        $m = design_misura($x);
        if ($m === '' && $x !== '0') return '';
        $out[] = $m === '' ? '0' : $m;
        if (count($out) > $max) return '';
    }
    return implode(' ', $out);
}

/** Un'ombra (box-shadow o text-shadow): solo caratteri innocui, lunghezza limitata. */
function design_ombra(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    return preg_match('/^[\w\s#.,()%-]{1,200}$/', $v) && substr_count($v, '(') === substr_count($v, ')') ? $v : '';
}

function design_url(string $v): string {
    $v = trim($v);
    return preg_match('~^(https?://|/)[^"\'<>\s()]*$~', $v) ? mb_substr($v, 0, 500) : '';
}

function design_testo(string $v, int $max = 200): string {
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $v)), 0, $max);
}

function design_scelta($v, array $opzioni, string $predefinito = ''): string {
    $v = (string)$v;
    return array_key_exists($v, $opzioni) ? $v : $predefinito;
}

function design_pulisci_tipografia(array $t): array {
    $t = design_fondi(design_tipografia_vuota(), $t);
    $out = design_tipografia_vuota();
    $out['famiglia'] = design_font_valido((string)$t['famiglia']) ? trim((string)$t['famiglia']) : '';
    foreach (['d', 't', 'm'] as $m) {
        $out['dimensione'][$m] = design_misura((string)($t['dimensione'][$m] ?? ''));
        $out['interlinea'][$m] = design_misura((string)($t['interlinea'][$m] ?? ''), true);
    }
    $out['peso'] = design_scelta($t['peso'], DESIGN_PESI);
    $out['stile'] = design_scelta($t['stile'], DESIGN_STILI);
    $out['trasformazione'] = design_scelta($t['trasformazione'], DESIGN_TRASFORMAZIONI);
    $out['decorazione'] = design_scelta($t['decorazione'], DESIGN_DECORAZIONI);
    $out['spaziatura_lettere'] = design_misura((string)$t['spaziatura_lettere'], false, true);
    $out['spaziatura_parole'] = design_misura((string)$t['spaziatura_parole'], false, true);
    return $out;
}

/** Un elenco di colori o font personalizzati: nome, id stabile (slug del nome la prima volta), valore. */
function design_pulisci_personalizzati(array $lista, string $tipo, array $riservati): array {
    $out = [];
    $usati = $riservati;
    foreach (array_slice(array_values($lista), 0, 40) as $c) {
        if (!is_array($c)) continue;
        $nome = design_testo((string)($c['nome'] ?? ''), 60);
        if ($nome === '') continue;
        $id = preg_match('/^[a-z0-9-]{1,40}$/', (string)($c['id'] ?? '')) ? $c['id'] : design_slug($nome);
        if ($id === '' || preg_match('/^font-/', $id)) $id = 'c' . substr(md5($nome), 0, 6);
        $base = $id; $n = 2;
        while (in_array($id, $usati, true)) $id = substr($base, 0, 36) . '-' . $n++;
        $usati[] = $id;
        if ($tipo === 'colore') {
            $val = trim((string)($c['valore'] ?? ''));
            if (!design_colore_valido($val, false) || $val === '') continue;
            $out[] = ['id' => $id, 'nome' => $nome, 'valore' => $val];
        } else {
            $out[] = ['id' => $id, 'nome' => $nome] + design_pulisci_tipografia(is_array($c) ? $c : []);
        }
    }
    return $out;
}

/** Ripulisce un insieme completo di valori (già fuso sui predefiniti). Tutto quello che non è valido torna vuoto. */
function design_pulisci(array $v): array {
    $p = design_predefiniti();
    $v = design_fondi($p, $v);
    $col = fn($x) => design_colore_valido((string)$x) ? trim((string)$x) : '';
    $colSenzaGlobali = fn($x) => design_colore_valido((string)$x, false) ? trim((string)$x) : '';

    // Colori
    foreach (DESIGN_SISTEMA_COLORI as $k => $n) {
        $x = $colSenzaGlobali($v['colori']['sistema'][$k]);
        $v['colori']['sistema'][$k] = $x === '' ? $p['colori']['sistema'][$k] : $x;
    }
    $v['colori']['personalizzati'] = design_pulisci_personalizzati($v['colori']['personalizzati'], 'colore', array_keys(DESIGN_SISTEMA_COLORI));
    $idColori = design_colori_id($v);
    // Un riferimento a un globale che non esiste più torna vuoto.
    $col = function ($x) use ($idColori) {
        $x = trim((string)$x);
        if (!design_colore_valido($x)) return '';
        if (str_starts_with($x, 'globale:') && !in_array(substr($x, 8), $idColori, true)) return '';
        return $x;
    };

    // Font
    foreach (DESIGN_SISTEMA_FONT as $k => $n) {
        $t = design_pulisci_tipografia($v['font']['sistema'][$k]);
        if ($t['famiglia'] === '' || str_starts_with($t['famiglia'], 'globale:')) $t['famiglia'] = $p['font']['sistema'][$k]['famiglia'];
        $v['font']['sistema'][$k] = $t;
    }
    $v['font']['personalizzati'] = design_pulisci_personalizzati($v['font']['personalizzati'], 'font', array_keys(DESIGN_SISTEMA_FONT));
    foreach ($v['font']['personalizzati'] as &$f) if ($f['famiglia'] === '' || str_starts_with($f['famiglia'], 'globale:')) $f['famiglia'] = 'Inter';
    unset($f);

    // Tipografia per elemento
    foreach ($v['tipografia'] as $k => &$el) {
        $el['colore'] = $col($el['colore']);
        $el['tipografia'] = design_pulisci_tipografia($el['tipografia']);
        if ($k === 'corpo') $el['spazio_paragrafi'] = design_misura((string)($el['spazio_paragrafi'] ?? ''));
    }
    unset($el);

    // Pulsanti
    $b = &$v['pulsanti'];
    $b['tipografia'] = design_pulisci_tipografia($b['tipografia']);
    $b['ombra_testo'] = design_ombra((string)$b['ombra_testo']);
    foreach (['normale', 'hover'] as $s) {
        $b[$s]['colore'] = $col($b[$s]['colore']);
        $b[$s]['sfondo'] = $col($b[$s]['sfondo']);
        $b[$s]['bordo_colore'] = $col($b[$s]['bordo_colore']);
        $b[$s]['ombra'] = design_ombra((string)$b[$s]['ombra']);
    }
    $b['bordo_tipo'] = design_scelta($b['bordo_tipo'], DESIGN_BORDI);
    $b['bordo_larghezza'] = design_misure((string)$b['bordo_larghezza']);
    $b['raggio'] = design_misure((string)$b['raggio']);
    $b['padding'] = design_misure((string)$b['padding']);
    unset($b);

    // Immagini
    $i = &$v['immagini'];
    $i['raggio'] = design_misure((string)$i['raggio']);
    $i['bordo_tipo'] = design_scelta($i['bordo_tipo'], DESIGN_BORDI);
    $i['bordo_larghezza'] = design_misure((string)$i['bordo_larghezza']);
    $i['bordo_colore'] = $col($i['bordo_colore']);
    $i['opacita'] = design_opacita((string)$i['opacita']);
    $i['ombra'] = design_ombra((string)$i['ombra']);
    $i['filtri'] = design_pulisci_filtri($i['filtri']);
    $i['hover']['opacita'] = design_opacita((string)$i['hover']['opacita']);
    $i['hover']['bordo_colore'] = $col($i['hover']['bordo_colore']);
    $i['hover']['ombra'] = design_ombra((string)$i['hover']['ombra']);
    $i['hover']['filtri'] = design_pulisci_filtri($i['hover']['filtri']);
    $i['transizione'] = design_misura((string)$i['transizione'], true) !== '' ? (string)min(5000, max(0, (float)$i['transizione'])) : '';
    unset($i);

    // Campi dei moduli
    $c = &$v['campi'];
    $c['etichetta']['colore'] = $col($c['etichetta']['colore']);
    $c['etichetta']['tipografia'] = design_pulisci_tipografia($c['etichetta']['tipografia']);
    $c['campo']['tipografia'] = design_pulisci_tipografia($c['campo']['tipografia']);
    foreach (['colore', 'sfondo', 'bordo_colore'] as $k) $c['campo'][$k] = $col($c['campo'][$k]);
    $c['campo']['bordo_larghezza'] = design_misure((string)$c['campo']['bordo_larghezza']);
    $c['campo']['raggio'] = design_misure((string)$c['campo']['raggio']);
    $c['campo']['padding'] = design_misure((string)$c['campo']['padding']);
    foreach (['colore', 'sfondo', 'bordo_colore', 'contorno'] as $k) $c['fuoco'][$k] = $col($c['fuoco'][$k]);
    unset($c);

    // Layout: interi dentro limiti sensati
    $l = &$v['layout'];
    $int = fn($x, int $min, int $max, int $pre) => is_numeric($x) ? (int)max($min, min($max, (int)$x)) : $pre;
    $l['larghezza'] = $int($l['larghezza'], 600, 2400, 1180);
    $l['spazio_widget'] = $int($l['spazio_widget'], 0, 200, 24);
    $l['spazio_colonne'] = $int($l['spazio_colonne'], 0, 200, 32);
    $l['tablet'] = $int($l['tablet'], 600, 1600, 1024);
    $l['telefono'] = $int($l['telefono'], 320, 1000, 767);
    if ($l['telefono'] >= $l['tablet']) $l['telefono'] = $l['tablet'] - 1;
    unset($l);

    // Sfondo
    $s = &$v['sfondo'];
    $s['colore'] = $col($s['colore']);
    $s['immagine'] = design_url((string)$s['immagine']);
    $s['posizione'] = design_scelta($s['posizione'], DESIGN_SFONDO_POSIZIONI, 'center center');
    $s['ripetizione'] = design_scelta($s['ripetizione'], DESIGN_SFONDO_RIPETIZIONI, 'no-repeat');
    $s['dimensione'] = design_scelta($s['dimensione'], DESIGN_SFONDO_DIMENSIONI, 'cover');
    $s['allegato'] = design_scelta($s['allegato'], DESIGN_SFONDO_ALLEGATI, 'scroll');
    $s['velo'] = $col($s['velo']);
    $s['sfumature_sito'] = !empty($s['sfumature_sito']) && $s['sfumature_sito'] !== '0';
    unset($s);

    // Identità
    $v['sito']['nome'] = design_testo((string)$v['sito']['nome'], 80) ?: $p['sito']['nome'];
    $v['sito']['sottotitolo'] = design_testo((string)$v['sito']['sottotitolo'], 120);
    $v['sito']['descrizione'] = design_testo((string)$v['sito']['descrizione'], 300);
    $v['sito']['logo'] = design_url((string)$v['sito']['logo']);
    $v['sito']['favicon'] = design_url((string)$v['sito']['favicon']) ?: $p['sito']['favicon'];

    // CSS personalizzato: niente chiusura del tag, lunghezza limitata
    // sicurezza: solo amministratore, ma senza import, expression, behavior e url() verso altri siti.
    require_once __DIR__ . '/sicurezza-html.php';
    $v['css']['sito'] = mb_substr(sicurezza_css_pulisci(str_ireplace('</style', '<\/style', str_replace("\r", '', (string)$v['css']['sito']))), 0, 60000);
    return $v;
}

function design_opacita(string $v): string {
    $v = str_replace(',', '.', trim($v));
    if ($v === '' || !is_numeric($v)) return '';
    $n = (float)$v;
    if ($n > 1) $n = $n / 100; // "80" vuol dire 80%
    return (string)round(max(0, min(1, $n)), 2);
}

function design_pulisci_filtri(array $f): array {
    $out = ['sfocatura' => '', 'luminosita' => '', 'contrasto' => '', 'saturazione' => '', 'tonalita' => ''];
    $lim = ['sfocatura' => [0, 50], 'luminosita' => [0, 200], 'contrasto' => [0, 200], 'saturazione' => [0, 200], 'tonalita' => [0, 360]];
    foreach ($out as $k => $x) {
        $n = str_replace(',', '.', trim((string)($f[$k] ?? '')));
        if ($n === '' || !is_numeric($n)) continue;
        $out[$k] = (string)round(max($lim[$k][0], min($lim[$k][1], (float)$n)), 1);
    }
    return $out;
}

// ------------------------------------------------------------
// Traduzione dei valori in CSS
// ------------------------------------------------------------

/**
 * Da un valore salvato al CSS. Colori: 'globale:primario' -> 'var(--g-colore-primario)', altrimenti il valore.
 * Font: 'globale:font-testo' o 'globale:testo' -> 'var(--g-font-testo)'; un nome dell'elenco -> la famiglia completa.
 */
function design_css_valore(string $v, string $tipo = 'colore'): string {
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('/^globale:(font-)?([a-z0-9-]{1,40})$/', $v, $m)) {
        return $tipo === 'font' ? 'var(--g-font-' . $m[2] . ')' : 'var(--g-colore-' . $m[2] . ')';
    }
    if ($tipo === 'font') return design_font_css_famiglia($v) ?: '';
    return design_colore_valido($v, false) ? $v : '';
}

/** Alias chiesto dall'agente modelli. */
function design_colore(string $v): string {
    return design_css_valore($v, 'colore');
}

/** Le proprietà CSS di un gruppo tipografico, per misura: ['d' => [prop => val], 't' => ..., 'm' => ...]. */
function design_css_tipografia(array $t): array {
    $out = ['d' => [], 't' => [], 'm' => []];
    $fam = design_css_valore((string)$t['famiglia'], 'font');
    if ($fam !== '') $out['d']['font-family'] = $fam;
    foreach (['d', 't', 'm'] as $m) {
        if (($t['dimensione'][$m] ?? '') !== '') $out[$m]['font-size'] = $t['dimensione'][$m];
        if (($t['interlinea'][$m] ?? '') !== '') $out[$m]['line-height'] = $t['interlinea'][$m];
    }
    if ($t['peso'] !== '') $out['d']['font-weight'] = $t['peso'];
    if ($t['stile'] !== '') $out['d']['font-style'] = $t['stile'];
    if ($t['trasformazione'] !== '') $out['d']['text-transform'] = $t['trasformazione'];
    if ($t['decorazione'] !== '') $out['d']['text-decoration'] = $t['decorazione'];
    if ($t['spaziatura_lettere'] !== '') $out['d']['letter-spacing'] = $t['spaziatura_lettere'];
    if ($t['spaziatura_parole'] !== '') $out['d']['word-spacing'] = $t['spaziatura_parole'];
    return $out;
}

/** La stringa filter() dai cinque filtri, '' se nessuno. */
function design_css_filtri(array $f): string {
    $p = [];
    if ($f['sfocatura'] !== '') $p[] = 'blur(' . $f['sfocatura'] . 'px)';
    if ($f['luminosita'] !== '') $p[] = 'brightness(' . $f['luminosita'] . '%)';
    if ($f['contrasto'] !== '') $p[] = 'contrast(' . $f['contrasto'] . '%)';
    if ($f['saturazione'] !== '') $p[] = 'saturate(' . $f['saturazione'] . '%)';
    if ($f['tonalita'] !== '') $p[] = 'hue-rotate(' . $f['tonalita'] . 'deg)';
    return implode(' ', $p);
}

/** I selettori del sito su cui vale ogni elemento della scheda Tipografia (anche quelli che sito.css specializza). */
function design_selettori(): array {
    return [
        'corpo' => 'body',
        'paragrafo' => 'p',
        'link' => 'main a:not(.btn):not(.card):not(.servizio):not(.marchio):not(.lettere a)',
        'link_hover' => 'main a:not(.btn):not(.card):not(.servizio):not(.marchio):not(.lettere a):hover',
        'h1' => 'h1,.hero h1,.art-testa h1,h1.ed-titolo,.cortesia h1',
        'h2' => 'h2,.corpo h2,.modulo h2',
        'h3' => 'h3,.cta-box h3,.card-titolo',
        'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6',
        'pulsante' => '.btn',
        'immagine' => 'main img',
        'etichetta' => '.modulo-campi label,.test-profilo label,.test-report label',
        'campo' => '.modulo-campi input,.modulo-campi textarea,.modulo-campi select,.test-profilo select,.test-report input,.cerca',
    ];
}

function design_breakpoint(string $quale): int {
    $l = design_valori()['layout'];
    return $quale === 'telefono' ? (int)$l['telefono'] : (int)$l['tablet'];
}

/** Aggiunge proprietà a un selettore in [misura => [sel => [prop => val]]]. */
function design_css_metti(array &$css, string $sel, array $perMisura): void {
    foreach ($perMisura as $m => $props) {
        foreach ($props as $p => $x) if ($x !== '' && $x !== null) $css[$m][$sel][$p] = $x;
    }
}

/**
 * Il CSS completo dello stile del sito. Con i predefiniti: solo le variabili e il ponte con sito.css
 * (stessi valori, stesso aspetto). Le regole per elemento escono solo per i valori impostati.
 */
function design_css(?array $v = null): string {
    $v = $v ?? design_valori();
    $root = [];
    foreach ($v['colori']['sistema'] as $k => $x) $root[] = "--g-colore-$k:$x";
    foreach ($v['colori']['personalizzati'] as $c) $root[] = '--g-colore-' . $c['id'] . ':' . $c['valore'];
    $fontVar = function (string $id, array $t) use (&$root) {
        $root[] = "--g-font-$id:" . (design_font_css_famiglia($t['famiglia']) ?: 'inherit');
        if ($t['peso'] !== '') $root[] = "--g-font-$id-peso:" . $t['peso'];
        if (($t['dimensione']['d'] ?? '') !== '') $root[] = "--g-font-$id-dimensione:" . $t['dimensione']['d'];
    };
    foreach ($v['font']['sistema'] as $k => $t) $fontVar($k, $t);
    foreach ($v['font']['personalizzati'] as $f) $fontVar($f['id'], $f);
    $l = $v['layout'];
    $root[] = '--g-larghezza:' . $l['larghezza'] . 'px';
    $root[] = '--g-spazio-widget:' . $l['spazio_widget'] . 'px';
    $root[] = '--g-spazio-colonne:' . $l['spazio_colonne'] . 'px';
    // Il ponte con sito.css: le sue variabili seguono i globali. I personalizzati di partenza, se ancora esistono.
    $root[] = '--viola:var(--g-colore-primario)';
    $root[] = '--ciano:var(--g-colore-secondario)';
    $root[] = '--testo:var(--g-colore-testo)';
    $root[] = '--bg:var(--g-colore-sfondo)';
    $ids = design_colori_id($v);
    foreach (['sfondo-2' => '--bg-2', 'testo-2' => '--testo-2', 'testo-3' => '--testo-3', 'bordo' => '--bordo'] as $id => $var) {
        if (in_array($id, $ids, true)) $root[] = "$var:var(--g-colore-$id)";
    }
    $root[] = '--f-titolo:var(--g-font-primario)';
    $root[] = '--f-testo:var(--g-font-testo)';
    $root[] = '--max:var(--g-larghezza)';
    $out = ':root{' . implode(';', $root) . '}';

    $css = ['d' => [], 't' => [], 'm' => []];
    $sel = design_selettori();

    // Tipografia per elemento
    foreach ($v['tipografia'] as $k => $el) {
        $s = $sel[$k];
        design_css_metti($css, $s, design_css_tipografia($el['tipografia']));
        if ($el['colore'] !== '') $css['d'][$s]['color'] = design_css_valore($el['colore']);
        if ($k === 'corpo' && $el['spazio_paragrafi'] !== '') $css['d']['p,.corpo p']['margin-bottom'] = $el['spazio_paragrafi'];
    }

    // Pulsanti
    $b = $v['pulsanti'];
    design_css_metti($css, '.btn', design_css_tipografia($b['tipografia']));
    if ($b['ombra_testo'] !== '') $css['d']['.btn']['text-shadow'] = $b['ombra_testo'];
    if ($b['normale']['colore'] !== '') $css['d']['.btn']['color'] = design_css_valore($b['normale']['colore']);
    if ($b['normale']['sfondo'] !== '') $css['d']['.btn']['background'] = design_css_valore($b['normale']['sfondo']);
    if ($b['normale']['bordo_colore'] !== '') $css['d']['.btn']['border-color'] = design_css_valore($b['normale']['bordo_colore']);
    if ($b['normale']['ombra'] !== '') $css['d']['.btn']['box-shadow'] = $b['normale']['ombra'];
    if ($b['bordo_tipo'] !== '') $css['d']['.btn']['border-style'] = $b['bordo_tipo'];
    if ($b['bordo_larghezza'] !== '') $css['d']['.btn']['border-width'] = $b['bordo_larghezza'];
    if ($b['raggio'] !== '') $css['d']['.btn']['border-radius'] = $b['raggio'];
    if ($b['padding'] !== '') $css['d']['.btn']['padding'] = $b['padding'];
    if ($b['hover']['colore'] !== '') $css['d']['.btn:hover']['color'] = design_css_valore($b['hover']['colore']);
    if ($b['hover']['sfondo'] !== '') $css['d']['.btn:hover']['background'] = design_css_valore($b['hover']['sfondo']);
    if ($b['hover']['bordo_colore'] !== '') $css['d']['.btn:hover']['border-color'] = design_css_valore($b['hover']['bordo_colore']);
    if ($b['hover']['ombra'] !== '') $css['d']['.btn:hover']['box-shadow'] = $b['hover']['ombra'];

    // Immagini
    $i = $v['immagini'];
    $si = $sel['immagine'];
    if ($i['raggio'] !== '') $css['d'][$si]['border-radius'] = $i['raggio'];
    if ($i['bordo_tipo'] !== '') $css['d'][$si]['border-style'] = $i['bordo_tipo'];
    if ($i['bordo_larghezza'] !== '') $css['d'][$si]['border-width'] = $i['bordo_larghezza'];
    if ($i['bordo_colore'] !== '') $css['d'][$si]['border-color'] = design_css_valore($i['bordo_colore']);
    if ($i['opacita'] !== '') $css['d'][$si]['opacity'] = $i['opacita'];
    if ($i['ombra'] !== '') $css['d'][$si]['box-shadow'] = $i['ombra'];
    if (($f = design_css_filtri($i['filtri'])) !== '') $css['d'][$si]['filter'] = $f;
    if ($i['transizione'] !== '') $css['d'][$si]['transition'] = 'all ' . $i['transizione'] . 'ms';
    if ($i['hover']['opacita'] !== '') $css['d']["$si:hover"]['opacity'] = $i['hover']['opacita'];
    if ($i['hover']['bordo_colore'] !== '') $css['d']["$si:hover"]['border-color'] = design_css_valore($i['hover']['bordo_colore']);
    if ($i['hover']['ombra'] !== '') $css['d']["$si:hover"]['box-shadow'] = $i['hover']['ombra'];
    if (($f = design_css_filtri($i['hover']['filtri'])) !== '') $css['d']["$si:hover"]['filter'] = $f;

    // Campi dei moduli
    $c = $v['campi'];
    design_css_metti($css, $sel['etichetta'], design_css_tipografia($c['etichetta']['tipografia']));
    if ($c['etichetta']['colore'] !== '') $css['d'][$sel['etichetta']]['color'] = design_css_valore($c['etichetta']['colore']);
    $sc = $sel['campo'];
    design_css_metti($css, $sc, design_css_tipografia($c['campo']['tipografia']));
    if ($c['campo']['colore'] !== '') $css['d'][$sc]['color'] = design_css_valore($c['campo']['colore']);
    if ($c['campo']['sfondo'] !== '') $css['d'][$sc]['background'] = design_css_valore($c['campo']['sfondo']);
    if ($c['campo']['bordo_colore'] !== '') $css['d'][$sc]['border-color'] = design_css_valore($c['campo']['bordo_colore']);
    if ($c['campo']['bordo_larghezza'] !== '') $css['d'][$sc]['border-width'] = $c['campo']['bordo_larghezza'];
    if ($c['campo']['raggio'] !== '') $css['d'][$sc]['border-radius'] = $c['campo']['raggio'];
    if ($c['campo']['padding'] !== '') $css['d'][$sc]['padding'] = $c['campo']['padding'];
    $sf = implode(',', array_map(fn($x) => $x . ':focus', explode(',', $sc)));
    if ($c['fuoco']['colore'] !== '') $css['d'][$sf]['color'] = design_css_valore($c['fuoco']['colore']);
    if ($c['fuoco']['sfondo'] !== '') $css['d'][$sf]['background'] = design_css_valore($c['fuoco']['sfondo']);
    if ($c['fuoco']['bordo_colore'] !== '') $css['d'][$sf]['border-color'] = design_css_valore($c['fuoco']['bordo_colore']);
    if ($c['fuoco']['contorno'] !== '') $css['d'][$sf]['outline-color'] = design_css_valore($c['fuoco']['contorno']);

    // Layout: solo quando cambia dai valori di oggi
    if ($l['spazio_widget'] !== 24) $css['d']['.ed-widget']['margin-bottom'] = 'var(--g-spazio-widget)';
    if ($l['spazio_colonne'] !== 32) $css['d']['.ed-riga']['gap'] = 'var(--g-spazio-colonne)';

    // Sfondo del sito
    $s = $v['sfondo'];
    if ($s['colore'] !== '') $css['d']['body']['background-color'] = design_css_valore($s['colore']);
    if ($s['immagine'] !== '') {
        $img = "url('" . str_replace(["'", '"', ')', '\\'], '', $s['immagine']) . "')";
        if ($s['velo'] !== '') { $velo = design_css_valore($s['velo']); $img = "linear-gradient($velo,$velo)," . $img; }
        $css['d']['body']['background-image'] = $img;
        $css['d']['body']['background-position'] = $s['posizione'];
        $css['d']['body']['background-repeat'] = $s['ripetizione'];
        $css['d']['body']['background-size'] = $s['dimensione'];
        $css['d']['body']['background-attachment'] = $s['allegato'];
        if (!$s['sfumature_sito']) $css['d']['body::before']['display'] = 'none';
    } elseif (!$s['sfumature_sito']) {
        $css['d']['body::before']['display'] = 'none';
    }

    $out .= design_css_testo($css, (int)$l['tablet'], (int)$l['telefono']);

    // Punti di rottura diversi da oggi: le classi "nascondi per misura" dell'editor seguono le soglie nuove.
    if ((int)$l['tablet'] !== 1024 || (int)$l['telefono'] !== 767) {
        $t = (int)$l['tablet']; $m = (int)$l['telefono'];
        $out .= '@media (min-width:' . ($t + 1) . 'px){.ed-no-d{display:none!important}.ed-no-t,.ed-no-m{display:revert!important}}'
            . '@media (min-width:' . ($m + 1) . 'px) and (max-width:' . $t . 'px){.ed-no-t{display:none!important}.ed-no-d,.ed-no-m{display:revert!important}}'
            . '@media (max-width:' . $m . 'px){.ed-no-m{display:none!important}.ed-no-d,.ed-no-t{display:revert!important}}';
    }

    if (trim($v['css']['sito']) !== '') $out .= "\n" . $v['css']['sito'];
    return $out;
}

/** Da [misura => [sel => [prop => val]]] al testo CSS, con i media query di tablet e telefono. */
function design_css_testo(array $css, int $tablet, int $telefono): string {
    $blocchi = ['d' => '', 't' => '@media (max-width:' . $tablet . 'px){', 'm' => '@media (max-width:' . $telefono . 'px){'];
    $out = '';
    foreach ($blocchi as $m => $apri) {
        if (empty($css[$m])) continue;
        $regole = '';
        foreach ($css[$m] as $sel => $props) {
            if (!$props) continue;
            $regole .= $sel . '{' . implode(';', array_map(fn($p, $x) => "$p:$x", array_keys($props), $props)) . '}';
        }
        if ($regole === '') continue;
        $out .= $apri === '' ? $regole : $apri . $regole . '}';
    }
    return $out;
}

/** Le famiglie di Google Fonts davvero scelte da qualche parte: solo quelle si caricano. */
function design_google_fonts(?array $v = null): array {
    $v = $v ?? design_valori();
    $nomi = [];
    $raccogli = function (array $t) use (&$nomi) { if (($t['famiglia'] ?? '') !== '' && !str_starts_with($t['famiglia'], 'globale:')) $nomi[$t['famiglia']] = true; };
    foreach ($v['font']['sistema'] as $t) $raccogli($t);
    foreach ($v['font']['personalizzati'] as $t) $raccogli($t);
    foreach ($v['tipografia'] as $el) $raccogli($el['tipografia']);
    $raccogli($v['pulsanti']['tipografia']);
    $raccogli($v['campi']['etichetta']['tipografia']);
    $raccogli($v['campi']['campo']['tipografia']);
    $out = [];
    foreach (array_keys($nomi) as $n) {
        $f = design_font_elenco()[$n] ?? null;
        if ($f && isset($f['google'])) $out[] = $f['google'];
    }
    return $out;
}

/** Il <link> di Google Fonts per le famiglie scelte, '' se nessuna. */
function design_google_link(?array $v = null): string {
    $fam = design_google_fonts($v);
    if (!$fam) return '';
    return 'https://fonts.googleapis.com/css2?' . implode('&', array_map(fn($f) => 'family=' . $f, $fam)) . '&display=swap';
}

/** Quello che va nel <head> del sito dopo sito.css: i font scelti e il CSS dello stile. */
function design_head(): string {
    try {
        $v = design_valori();
        $link = design_google_link($v);
        $out = '';
        if ($link !== '') $out .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link id="g-fonts" rel="stylesheet" href="' . htmlspecialchars($link, ENT_QUOTES) . '">';
        $out .= '<style id="g-css">' . str_replace('</', '<\/', design_css($v)) . '</style>';
        return $out;
    } catch (Throwable $e) {
        return '';
    }
}

/** Il colore della barra del browser (meta theme-color): il colore di sfondo del sito, se è un esadecimale. */
function design_theme_color(): string {
    $v = design_valori();
    $c = $v['sfondo']['colore'] !== '' ? design_css_valore($v['sfondo']['colore']) : $v['colori']['sistema']['sfondo'];
    if (str_starts_with($c, 'var(--g-colore-')) $c = design_colore_risolto(substr($c, 15, -1), $v);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#060a17';
}

/** Il valore vero di un colore globale dato l'id. */
function design_colore_risolto(string $id, ?array $v = null): string {
    $v = $v ?? design_valori();
    if (isset($v['colori']['sistema'][$id])) return $v['colori']['sistema'][$id];
    foreach ($v['colori']['personalizzati'] as $c) if ($c['id'] === $id) return $c['valore'];
    return '';
}

// ------------------------------------------------------------
// Per il pannello dell'editor e per gli altri moduli
// ------------------------------------------------------------

/** Nome, sottotitolo, descrizione, logo e favicon del sito. */
function design_sito(): array {
    return design_valori()['sito'];
}

/**
 * Colori e font globali per i controlli dell'editor (CONTROLLI-CONTRATTO punto 5):
 * colori con chiave, nome, valore; font con chiave, nome, famiglia; l'elenco delle famiglie.
 */
function design_globali(): array {
    $v = design_valori();
    $colori = [];
    foreach ($v['colori']['sistema'] as $k => $x) $colori[] = ['chiave' => $k, 'id' => $k, 'nome' => DESIGN_SISTEMA_COLORI[$k], 'valore' => $x, 'var' => "--g-colore-$k", 'sistema' => true];
    foreach ($v['colori']['personalizzati'] as $c) $colori[] = ['chiave' => $c['id'], 'id' => $c['id'], 'nome' => $c['nome'], 'valore' => $c['valore'], 'var' => '--g-colore-' . $c['id'], 'sistema' => false];
    $font = [];
    foreach ($v['font']['sistema'] as $k => $t) $font[] = ['chiave' => $k, 'id' => $k, 'nome' => DESIGN_SISTEMA_FONT[$k], 'famiglia' => $t['famiglia'], 'valore' => design_font_css_famiglia($t['famiglia']), 'var' => "--g-font-$k", 'sistema' => true];
    foreach ($v['font']['personalizzati'] as $f) $font[] = ['chiave' => $f['id'], 'id' => $f['id'], 'nome' => $f['nome'], 'famiglia' => $f['famiglia'], 'valore' => design_font_css_famiglia($f['famiglia']), 'var' => '--g-font-' . $f['id'], 'sistema' => false];
    $famiglie = design_font_elenco();
    return [
        'colori' => $colori, 'font' => $font,
        'famiglie' => array_keys($famiglie),
        'famiglie_css' => array_map(fn($f) => $f['css'], $famiglie),
        'famiglie_gruppo' => array_map(fn($f) => $f['gruppo'], $famiglie),
        'tablet' => (int)$v['layout']['tablet'], 'telefono' => (int)$v['layout']['telefono'],
    ];
}

/** Alias con il nome del brief. */
function design_globali_per_editor(): array {
    return design_globali();
}
