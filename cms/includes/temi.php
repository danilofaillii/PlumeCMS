<?php
/**
 * I temi del sito (27 ottobre 2026). Un tema cambia l'aspetto intero: colori, caratteri, pulsanti, header, footer, CSS,
 * e se vuole le pagine (viste in PHP, come i temi di WordPress). Menu e contenuti non si toccano.
 *
 * Un tema è una cartella temi/<nome>/ (nome: lettere minuscole, cifre e trattini):
 *   tema.json      obbligatorio: nome, versione, autore, descrizione, anteprima, css, stile, modelli (i file sono facoltativi)
 *   stile.json     lo Stile del sito del tema (stesso formato di design_valori(), solo le sezioni dell'aspetto)
 *   modelli.json   {"header": albero dell'editor, "footer": albero}; un menu si indica con "posizione:principale"
 *   tema.css       CSS in più, caricato dopo sito.css e prima dello Stile del sito
 *   infografiche   (dentro tema.json) i colori delle infografiche: superfici e i sei colori principali (TEMI_IG_*)
 *   funzioni.php   facoltativo: ritorna ['filtri' => [...], 'eventi' => [...]] come un modulo
 *   viste/*.php    facoltativo: ridisegna una pagina (vedi TEMI_VISTE); ritorna l'HTML come stringa
 *
 * Sicurezza: il pannello legge solo i JSON e non esegue mai il PHP di un tema, così un tema rotto non chiude fuori
 * nessuno. Il PHP gira solo sul sito pubblico (temi_avvia_sito): se lancia un errore il tema si segna come guasto
 * (impostazione tema_guasto) e da quel momento il sito torna all'aspetto di base, finché non lo si riattiva.
 */

require_once __DIR__ . '/design.php';

/** Le sezioni dello Stile del sito che un tema imposta. Identità del sito (nome, logo, favicon) e CSS personalizzato restano di chi li ha scritti. */
const TEMI_SEZIONI_STILE = ['colori', 'font', 'tipografia', 'pulsanti', 'immagini', 'campi', 'layout', 'sfondo'];
/** Infografiche: le superfici che un tema può dare (chiave in tema.json -> variabile di sito.css). */
const TEMI_IG_SUPERFICI = ['sfondo' => 'sfondo', 'bianco' => 'bianco', 'bordo' => 'bordo', 'riga' => 'riga', 'titolo' => 'titolo', 'testo' => 'testo',
    'muto' => 'muto', 'situazione' => 'situazione', 'su_testata' => 'su-testata', 'su_scuro' => 'su-scuro'];
/** Infografiche: i sei colori principali. Gli altri della tavolozza (arancio, giallo, versioni scure, tinte chiare) si ricavano da questi. */
const TEMI_IG_VOCI = ['rosso', 'oro', 'verde', 'ciano', 'viola', 'scuro'];

/** Le pagine che un tema può ridisegnare con viste/<nome>.php, e le variabili che riceve. */
const TEMI_VISTE = [
    'documento' => 'la pagina intera: $testa (dentro <head>), $testata, $corpo, $piede, $popup, $meta',
    'home' => 'la home scritta nel codice (quando la pagina iniziale non è fatta con l\'editor): nessuna variabile',
    'articolo' => 'un articolo senza editor: $c (il contenuto), $html (il testo pronto)',
    'pagina' => 'una pagina senza editor: $c, $html',
    'blog' => 'la pagina del blog: $v (articoli, pagina, totale)',
    'archivio' => 'categoria o tag: $v (tassonomia, nome, descrizione, articoli, base, pagina, totale)',
    'cerca' => 'la ricerca: $q (la parola), $trovati (elenco di [c, e])',
    '404' => 'pagina non trovata: nessuna variabile',
];

function temi_dir(): string {
    return cms_radice() . '/temi';
}

function temi_url(string $slug): string {
    return cms_base() . '/temi/' . $slug;
}

function temi_slug_valido(string $s): bool {
    return (bool)preg_match('/^[a-z][a-z0-9-]{1,39}$/', $s);
}

/** Un percorso relativo dentro la cartella del tema: niente risalite, solo i tipi ammessi. */
function temi_file_valido(string $f, array $estensioni): bool {
    return (bool)preg_match('~^[a-z0-9_-]+(/[a-z0-9_-]+)*\.(' . implode('|', $estensioni) . ')$~i', $f) && !str_contains($f, '..');
}

function temi_json(string $file): ?array {
    if (!is_file($file) || filesize($file) > 2 * 1024 * 1024) return null;
    $d = json_decode((string)file_get_contents($file), true);
    return is_array($d) ? $d : null;
}

/**
 * Un tema letto dai suoi JSON (nessun PHP eseguito). Null se la cartella o tema.json non vanno.
 * Ritorna: slug, nome, versione, autore, descrizione, cartella, anteprima (indirizzo o ''), css (percorso relativo o ''),
 * stile (array), modelli (header/footer), php (true se ha funzioni.php o viste), viste (nomi presenti), errori (testi).
 */
function temi_leggi(string $slug): ?array {
    if (!temi_slug_valido($slug)) return null;
    $dir = temi_dir() . '/' . $slug;
    $j = temi_json($dir . '/tema.json');
    if ($j === null) return null;
    $t = ['slug' => $slug, 'cartella' => $dir, 'errori' => []];
    $testo = fn($k, $max, $pred = '') => mb_substr(trim((string)($j[$k] ?? $pred)), 0, $max);
    $t['nome'] = $testo('nome', 60, ucfirst($slug));
    $t['versione'] = $testo('versione', 20, '1.0');
    $t['autore'] = $testo('autore', 80);
    $t['descrizione'] = $testo('descrizione', 300);
    $t['anteprima'] = '';
    $a = (string)($j['anteprima'] ?? '');
    if ($a !== '' && temi_file_valido($a, ['png', 'jpg', 'jpeg', 'webp']) && is_file("$dir/$a")) $t['anteprima'] = temi_url($slug) . '/' . $a . '?v=' . filemtime("$dir/$a");
    $t['css'] = '';
    $c = (string)($j['css'] ?? '');
    if ($c !== '') {
        if (temi_file_valido($c, ['css']) && is_file("$dir/$c")) $t['css'] = $c; else $t['errori'][] = "Il CSS $c non c'è.";
    }
    $t['stile'] = [];
    $s = (string)($j['stile'] ?? '');
    if ($s !== '') {
        $v = temi_file_valido($s, ['json']) ? temi_json("$dir/$s") : null;
        if ($v === null) $t['errori'][] = "Lo stile $s non si legge.";
        else $t['stile'] = array_intersect_key($v, array_flip(TEMI_SEZIONI_STILE));
    }
    $t['modelli'] = [];
    $m = (string)($j['modelli'] ?? '');
    if ($m !== '') {
        $v = temi_file_valido($m, ['json']) ? temi_json("$dir/$m") : null;
        if ($v === null) $t['errori'][] = "Header e footer ($m) non si leggono.";
        else foreach (['header', 'footer'] as $tipo) if (is_array($v[$tipo] ?? null)) $t['modelli'][$tipo] = $v[$tipo];
    }
    $t['infografiche'] = temi_ig_leggi((array)($j['infografiche'] ?? []));
    $t['viste'] = [];
    // (string): la chiave '404' in PHP diventa il numero 404, e in_array(..., true) non la riconoscerebbe più.
    foreach (array_keys(TEMI_VISTE) as $n) if (is_file("$dir/viste/$n.php")) $t['viste'][] = (string)$n;
    $t['php'] = is_file("$dir/funzioni.php") || $t['viste'] !== [];
    return $t;
}

/** Tutti i temi presenti, per nome. */
function temi_elenco(): array {
    $out = [];
    foreach (glob(temi_dir() . '/*/tema.json') ?: [] as $f) {
        $t = temi_leggi(basename(dirname($f)));
        if ($t) $out[$t['slug']] = $t;
    }
    uasort($out, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));
    return $out;
}

/** Il tema scelto nel pannello ('' = nessuno, l'aspetto di base). Letto ogni volta dal database, senza cache. */
function temi_scelto(): string {
    $s = function_exists('get_setting') ? (string)get_setting('tema_attivo', '') : '';
    return temi_slug_valido($s) && is_file(temi_dir() . "/$s/tema.json") ? $s : '';
}

/** Il tema guasto, se c'è: ['slug', 'errore', 'quando']. */
function temi_guasto_info(): ?array {
    $g = function_exists('get_setting') ? json_decode((string)get_setting('tema_guasto', ''), true) : null;
    return is_array($g) && !empty($g['slug']) ? $g : null;
}

/** Il tema attivo sul sito: quello scelto, se non è guasto. */
function temi_attivo(): string {
    $s = temi_scelto();
    if ($s === '') return '';
    $g = temi_guasto_info();
    return $g && $g['slug'] === $s ? '' : $s;
}

/** Il tema di questa richiesta del sito (quello attivo, o quello in anteprima per l'amministratore). Null se nessuno. */
function temi_sito(): ?array {
    return $GLOBALS['tema_sito'] ?? null;
}

// ------------------------------------------------------------
// Sito pubblico: avvio, PHP del tema, viste
// ------------------------------------------------------------

/**
 * Da chiamare una volta all'inizio del sito pubblico (sito/index.php). Sceglie il tema (?tema=<nome> con ?preview=1 e
 * la sessione dell'amministratore: anteprima senza attivarlo), carica funzioni.php e arma il controllo degli errori fatali.
 */
function temi_avvia_sito(): void {
    $GLOBALS['tema_sito'] = null;
    $GLOBALS['tema_ganci'] = [];
    try {
        $slug = temi_attivo();
        $anteprima = false;
        $chiesto = isset($_GET['tema']) && is_string($_GET['tema']) ? $_GET['tema'] : '';
        if ($chiesto !== '' && function_exists('is_admin_preview') && is_admin_preview()) {
            if ($chiesto === 'base') { $slug = ''; $anteprima = true; }
            elseif (temi_slug_valido($chiesto) && is_file(temi_dir() . "/$chiesto/tema.json")) { $slug = $chiesto; $anteprima = true; }
        }
        if ($anteprima) {
            header('Cache-Control: no-store');
            // Lo stile e gli header del tema in anteprima, senza toccare quelli salvati (design_valori, modelli_pezzo).
            $GLOBALS['tema_anteprima'] = $slug === '' ? ['slug' => '', 'stile' => [], 'modelli' => []] : null;
        }
        if ($slug === '') return;
        $t = temi_leggi($slug);
        if (!$t) return;
        $t['anteprima_sito'] = $anteprima;
        $GLOBALS['tema_sito'] = $t;
        if ($anteprima) $GLOBALS['tema_anteprima'] = $t;
        register_shutdown_function('temi_controllo_fatale');
        if (is_file($t['cartella'] . '/funzioni.php')) {
            $g = temi_esegui($t['cartella'] . '/funzioni.php');
            if ($GLOBALS['tema_sito'] !== null && is_array($g)) {
                $GLOBALS['tema_ganci'] = ['filtri' => array_filter((array)($g['filtri'] ?? []), 'is_callable'), 'eventi' => array_filter((array)($g['eventi'] ?? []), 'is_callable')];
            }
        }
    } catch (Throwable $e) {
        error_log('temi: avvio: ' . $e->getMessage());
        $GLOBALS['tema_sito'] = null;
    }
}

/** Esegue un file PHP del tema con le variabili date. Un errore segna il tema come guasto e ritorna null. */
function temi_esegui(string $file, array $variabili = []) {
    $t = temi_sito();
    if ($t === null) return null;
    try {
        return (static function (string $__file, array $__v) {
            extract($__v, EXTR_SKIP);
            return include $__file;
        })($file, $variabili);
    } catch (Throwable $e) {
        temi_guasto($t, $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
        return null;
    }
}

/** Una vista del tema, se c'è: l'HTML, oppure null (si usa quella del CMS). */
function temi_vista(string $nome, array $variabili = []): ?string {
    $t = temi_sito();
    if ($t === null || !isset(TEMI_VISTE[$nome]) || !in_array($nome, $t['viste'], true)) return null;
    $html = temi_esegui($t['cartella'] . "/viste/$nome.php", $variabili);
    return is_string($html) ? $html : null;
}

/** I filtri e gli eventi del tema (cms_filtro e cms_evento li chiamano dopo quelli dei moduli). */
function temi_gancio(string $tipo, string $nome): ?callable {
    $f = $GLOBALS['tema_ganci'][$tipo][$nome] ?? null;
    return $f && temi_sito() !== null ? $f : null;
}

/**
 * Il tema ha sbagliato: da questa richiesta in poi si usa l'aspetto di base. Se è il tema attivo (non un'anteprima)
 * resta segnato come guasto finché qualcuno non lo riattiva dal pannello.
 */
function temi_guasto(array $t, string $errore): void {
    error_log('temi: ' . $t['slug'] . ': ' . $errore);
    $GLOBALS['tema_sito'] = null;
    $GLOBALS['tema_ganci'] = [];
    if (!empty($t['anteprima_sito'])) return;
    try {
        save_setting('tema_guasto', json_encode(['slug' => $t['slug'], 'errore' => mb_substr($errore, 0, 500), 'quando' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } catch (Throwable $e) {
        error_log('temi: guasto non registrato: ' . $e->getMessage());
    }
}

/** Alla fine della richiesta: un errore fatale nato in un file del tema (che try/catch non prende) segna il tema come guasto. */
function temi_controllo_fatale(): void {
    $e = error_get_last();
    $t = $GLOBALS['tema_sito'] ?? null;
    if (!$e || !$t || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    if (!str_starts_with(str_replace('\\', '/', (string)$e['file']), str_replace('\\', '/', $t['cartella']) . '/')) return;
    temi_guasto($t, $e['message'] . ' in ' . basename((string)$e['file']) . ':' . $e['line']);
}

/** Il <link> del CSS del tema, da mettere dopo sito.css e tema.css e prima dello Stile del sito. */
function temi_css_link(): string {
    // Nel pannello (tela dell'editor, anteprime) temi_avvia_sito non gira: il CSS del tema attivo si legge da tema.json, senza PHP.
    $t = array_key_exists('tema_sito', $GLOBALS) ? temi_sito() : (($s = temi_attivo()) !== '' ? temi_leggi($s) : null);
    if ($t === null) return '';
    $out = '';
    $ig = temi_ig_css($t['infografiche']);
    if ($ig !== '') $out .= '<style id="tema-ig">' . $ig . '</style>';
    if ($t['css'] !== '') {
        $f = $t['cartella'] . '/' . $t['css'];
        $out .= '<link rel="stylesheet" id="tema-css" href="' . h(temi_url($t['slug']) . '/' . $t['css'] . '?v=' . (is_file($f) ? filemtime($f) : 0)) . '">';
    }
    return $out;
}

// ------------------------------------------------------------
// Infografiche: i colori dal tema (fase 4)
// ------------------------------------------------------------

/**
 * Il blocco "infografiche" di tema.json, ripulito: superfici (TEMI_IG_SUPERFICI), voci (TEMI_IG_VOCI) e "variabili"
 * (qualsiasi --ig-* di sito.css scritta a mano, senza il prefisso: {"mattone": "#b5523a"}). Solo colori validi.
 */
function temi_ig_leggi(array $j): array {
    $col = fn($x) => is_string($x) && trim($x) !== '' && design_colore_valido(trim($x), false) ? trim($x) : null;
    $out = ['superfici' => [], 'voci' => [], 'variabili' => []];
    foreach (TEMI_IG_SUPERFICI as $k => $var) if (($c = $col($j[$k] ?? null)) !== null) $out['superfici'][$var] = $c;
    foreach (TEMI_IG_VOCI as $k) if (($c = $col($j['voci'][$k] ?? null)) !== null) $out['voci'][$k] = $c;
    foreach ((array)($j['variabili'] ?? []) as $k => $v) {
        if (is_string($k) && preg_match('/^[a-z][a-z0-9-]{0,30}$/', $k) && ($c = $col($v)) !== null) $out['variabili'][$k] = $c;
    }
    return $out;
}

/**
 * Le variabili --ig-* del tema: le superfici e le voci date, le tinte ricavate dalle voci (seconda tinta più scura,
 * tinte chiare per gli occhielli, arancio, giallo, versioni scure, mattone, lilla, marrone), poi quelle scritte a mano.
 * Stanno in :root, che vince sui valori di Carta di sito.css (:where(:root)). Vuoto se il tema non dà niente.
 */
function temi_ig_css(array $ig): string {
    $v = [];
    foreach ($ig['superfici'] ?? [] as $k => $c) $v[$k] = $c;
    $voci = $ig['voci'] ?? [];
    $mix = fn(string $a, string $b, int $p) => "color-mix(in srgb,var(--ig-$a)," . $b . " $p%)";
    foreach ($voci as $k => $c) {
        $v[$k] = $c;
        // La seconda tinta: più scura; per lo scuro (testate e riquadri) un po' più chiara, come in Carta.
        $v["$k-2"] = $k === 'scuro' ? $mix('scuro', '#fff', 14) : $mix($k, '#000', 22);
        if ($k !== 'scuro') $v["$k-chiaro"] = $mix($k, '#fff', 72);
    }
    if (isset($voci['rosso'])) {
        $v['rosso-scuro'] = $mix('rosso', '#000', 18); $v['rosso-scuro-2'] = $mix('rosso', '#000', 30);
        $v['mattone'] = $mix('rosso', '#000', 12); $v['mattone-2'] = $mix('rosso', '#000', 32); $v['mattone-chiaro'] = $mix('rosso', '#fff', 66);
    }
    if (isset($voci['rosso'], $voci['oro'])) { $v['arancio'] = 'color-mix(in srgb,var(--ig-rosso),var(--ig-oro))'; $v['arancio-2'] = $mix('arancio', '#000', 20); }
    if (isset($voci['oro'])) {
        $v['oro-scuro'] = $mix('oro', '#000', 25); $v['oro-scuro-2'] = $mix('oro', '#000', 42); $v['oro-scuro-chiaro'] = $mix('oro', '#fff', 35);
        $v['giallo'] = $mix('oro', '#ff0', 25); $v['giallo-2'] = $mix('giallo', '#000', 15);
    }
    if (isset($voci['verde'])) { $v['verde-scuro'] = $mix('verde', '#000', 30); $v['verde-scuro-2'] = $mix('verde', '#000', 45); }
    if (isset($voci['ciano'])) { $v['ciano-scuro'] = $mix('ciano', '#000', 30); $v['ciano-scuro-2'] = $mix('ciano', '#000', 45); $v['ciano-profondo'] = $mix('ciano', '#000', 58); }
    if (isset($voci['viola'])) $v['lilla'] = $mix('viola', '#fff', 62);
    if (isset($voci['scuro'])) {
        $v['marrone'] = $mix('scuro', '#fff', 25); $v['marrone-2'] = 'var(--ig-scuro-2)';
        if (!isset($v['su-scuro'])) $v['su-scuro'] = $mix('scuro', '#fff', 70);
    }
    foreach ($ig['variabili'] ?? [] as $k => $c) $v[$k] = $c;
    if (!$v) return '';
    $righe = [];
    foreach ($v as $k => $c) $righe[] = "--ig-$k:$c";
    return ':root{' . implode(';', $righe) . '}';
}

/** Lo stile del tema in anteprima, sopra quello salvato (design_valori lo usa solo durante un'anteprima). */
function temi_stile_anteprima(array $valori): array {
    $a = $GLOBALS['tema_anteprima'] ?? null;
    if (!is_array($a)) return $valori;
    $pred = design_predefiniti();
    foreach (TEMI_SEZIONI_STILE as $k) $valori[$k] = design_fondi($pred[$k], (array)($a['stile'][$k] ?? []));
    return $valori;
}

// ------------------------------------------------------------
// Pannello: attivare, tornare all'aspetto di base
// ------------------------------------------------------------

/**
 * Un albero di header o footer del tema adattato a questo sito: nei widget, "menu": "posizione:<nome>" diventa l'id del
 * menu con quella posizione (il numero del menu cambia da un sito all'altro). Va chiamato prima di editor_valida.
 */
function temi_albero_locale(array $albero): array {
    require_once __DIR__ . '/menu-nav.php';
    $giro = function (array $nodi) use (&$giro): array {
        foreach ($nodi as &$n) {
            if (!is_array($n)) continue;
            $m = $n['impostazioni']['menu'] ?? null;
            if (is_string($m) && str_starts_with($m, 'posizione:')) {
                $menu = menu_per_posizione(substr($m, 10));
                $n['impostazioni']['menu'] = $menu ? (string)$menu['id'] : '';
            }
            if (isset($n['figli']) && is_array($n['figli'])) $n['figli'] = $giro($n['figli']);
        }
        return $nodi;
    };
    if (isset($albero['elementi']) && is_array($albero['elementi'])) $albero['elementi'] = $giro($albero['elementi']);
    return $albero;
}

/** I modelli creati da ogni tema: {slug: {header: id, footer: id}}. Servono per non duplicarli a ogni attivazione. */
function temi_modelli_creati(): array {
    $m = json_decode((string)get_setting('tema_modelli', ''), true);
    return is_array($m) ? $m : [];
}

/**
 * Attiva un tema: lo stile del tema prende il posto delle sezioni dell'aspetto (quelle di prima restano nelle Versioni
 * e in "Versione precedente"), l'header e il footer del tema diventano i predefiniti (quelli di prima restano).
 * Non esegue PHP del tema. Ritorna le righe del resoconto.
 */
function temi_attiva(string $slug, string $autore = ''): array {
    $t = temi_leggi($slug);
    if (!$t) throw new RuntimeException('Tema non trovato.');
    temi_proteggi(); // il PHP del tema non deve mai aprirsi dal browser
    $log = [];
    // 1. Stile: ogni sezione dell'aspetto riparte dai predefiniti e prende i valori del tema.
    $v = design_valori(true);
    $pred = design_predefiniti();
    foreach (TEMI_SEZIONI_STILE as $k) $v[$k] = design_fondi($pred[$k], (array)($t['stile'][$k] ?? []));
    design_salva($v, $autore, 'tema ' . $t['nome'] . ' attivato');
    $log[] = 'Stile del sito: colori, caratteri, pulsanti, campi, layout e sfondo del tema ' . $t['nome'] . '.';
    // 2. Header e footer: quelli del tema diventano i predefiniti.
    if ($t['modelli']) {
        require_once __DIR__ . '/modelli.php';
        require_once __DIR__ . '/editor-controlli.php';
        require_once __DIR__ . '/editor.php';
        $creati = temi_modelli_creati();
        foreach ($t['modelli'] as $tipo => $albero) {
            $id = (int)($creati[$slug][$tipo] ?? 0);
            $esiste = $id ? modelli_leggi($id) : null;
            if (!$esiste || !empty($esiste['cestino'])) {
                $valido = editor_valida(temi_albero_locale($albero));
                if ($valido === null) { $log[] = ucfirst($tipo) . ' del tema non valido: lasciato quello di prima.'; continue; }
                $id = modelli_crea($tipo, ucfirst($tipo) . ' ' . $t['nome'], $valido);
                db()->prepare('UPDATE ' . tbl('modelli') . ' SET struttura = struttura_bozza, struttura_bozza = NULL WHERE id = ?')->execute([$id]);
                $creati[$slug][$tipo] = $id;
                $log[] = ucfirst($tipo) . ' "' . ucfirst($tipo) . ' ' . $t['nome'] . '" creato.';
            }
            modelli_predefinito($id);
            $log[] = ucfirst($tipo) . ' del tema impostato come predefinito (gli altri restano in Header e footer).';
        }
        save_setting('tema_modelli', json_encode($creati));
    }
    save_setting('tema_attivo', $slug);
    // Un tema scelto di nuovo riparte pulito; il guasto di un altro tema non conta più, perché quel tema non è scelto.
    save_setting('tema_guasto', '');
    $log[] = 'Tema attivo: ' . $t['nome'] . '.';
    return $log;
}

/** Nessun tema: il sito usa sito.css e lo Stile del sito così com'è (non si cambia niente dello stile). */
function temi_disattiva(): void {
    save_setting('tema_attivo', '');
    save_setting('tema_guasto', '');
}

// ------------------------------------------------------------
// Caricare, scaricare, eliminare (fase 6)
// ------------------------------------------------------------

/**
 * temi/.htaccess: dal browser nella cartella dei temi non si esegue niente (il PHP di un tema lo carica solo il CMS) e i
 * JSON non si leggono. Lo scrive il CMS stesso quando manca (pagina Temi, attivazione, caricamento, installatore): il
 * pannello Aggiornamenti non accetta .htaccess in sottocartelle, quindi non può arrivare con un pacchetto.
 */
const TEMI_HTACCESS = <<<'HT'
# Cartella dei temi (temi/): dal web si servono CSS, immagini e caratteri; il PHP dei temi lo carica solo il CMS.
# Lo scrive plumeCMS da solo quando manca (includes/temi.php, temi_proteggi). Su Nginx vale la regola in install/esempi/nginx.txt.
Options -Indexes -ExecCGI
<FilesMatch "(?i)\.(php|phtml|php[0-9]|phps|phar|pht|pl|py|cgi|sh|shtml|json|htaccess|ini)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>
<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
</IfModule>
HT;

/** Scrive temi/.htaccess se manca o è diverso. Ritorna true se c'è (su Nginx non serve, ma non fa danni). */
function temi_proteggi(): bool {
    $dir = temi_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $f = $dir . '/.htaccess';
    if (is_file($f) && (string)file_get_contents($f) === TEMI_HTACCESS) return true;
    if (@file_put_contents($f, TEMI_HTACCESS) === false) return false;
    @chmod($f, 0644);
    return true;
}

/** I tipi di file che un tema caricato può contenere. Niente SVG (possono portare script), niente file nascosti. */
const TEMI_ZIP_ESTENSIONI = ['json', 'css', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'woff', 'woff2', 'ttf', 'otf', 'txt', 'md', 'php'];
const TEMI_ZIP_MAX_FILE = 400;
const TEMI_ZIP_MAX_UNO = 10 * 1048576;
const TEMI_ZIP_MAX_TOTALE = 40 * 1048576;

/** Dove finiscono le versioni sostituite o eliminate: dentro aggiornamenti/, che dal web non si legge. */
function temi_cartella_copie(): string {
    return cms_radice() . '/aggiornamenti/temi-precedenti';
}

/**
 * Legge e controlla uno zip di tema senza scrivere niente. Ritorna slug, nome, voci [percorso nel tema => indice nello
 * zip], php (se contiene PHP), firmato. tema.json può stare nella radice dello zip o in un'unica cartella; manifest.json
 * nella radice porta la firma (tools/pacchetto.py --rifirma) e non si installa; le cartelle __MACOSX e i .DS_Store si saltano.
 */
function temi_zip_leggi(string $percorso): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Sul server manca la libreria PHP per gli zip (ZipArchive): si accende da cPanel, voce Select PHP Version.');
    $zip = new ZipArchive();
    if ($zip->open($percorso) !== true) throw new RuntimeException('Il file non è uno zip leggibile.');
    try {
        $nomi = [];
        $totale = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $n = str_replace('\\', '/', (string)($st['name'] ?? ''));
            if ($n === '' || str_starts_with($n, '/') || str_contains($n, "\0") || preg_match('~(^|/)\.\.(/|$)~', $n)) throw new RuntimeException("Nome non ammesso nello zip: $n");
            if (str_ends_with($n, '/') || str_starts_with($n, '__MACOSX/') || basename($n) === '.DS_Store') continue;
            $dim = (int)($st['size'] ?? 0);
            $comp = max(1, (int)($st['comp_size'] ?? 0));
            if ($dim > TEMI_ZIP_MAX_UNO) throw new RuntimeException("File troppo grande nel tema: $n (oltre 10 MB).");
            if ($dim > 1048576 && $dim / $comp > 100) throw new RuntimeException("File compresso in modo sospetto: $n.");
            $totale += $dim;
            $nomi[$n] = $i;
        }
        if (count($nomi) > TEMI_ZIP_MAX_FILE) throw new RuntimeException('Troppi file nel tema (più di ' . TEMI_ZIP_MAX_FILE . ').');
        if ($totale > TEMI_ZIP_MAX_TOTALE) throw new RuntimeException('Il tema è troppo grande una volta estratto (oltre 40 MB).');
        // Dove sta tema.json: nella radice, oppure in un'unica cartella che contiene tutto.
        $base = '';
        if (!isset($nomi['tema.json'])) {
            $cand = array_values(array_filter(array_keys($nomi), fn($n) => preg_match('~^[^/]+/tema\.json$~', $n)));
            if (count($cand) !== 1) throw new RuntimeException('Nello zip non trovo tema.json (nella radice o in una sola cartella): non sembra un tema di plumeCMS.');
            $base = dirname($cand[0]) . '/';
        }
        $firma = '';
        $voci = [];
        foreach ($nomi as $n => $i) {
            if ($n === 'manifest.json') {
                $m = json_decode((string)$zip->getFromIndex($i), true);
                $firma = is_array($m) ? trim((string)($m['firma'] ?? '')) : '';
                continue;
            }
            if ($base !== '' && !str_starts_with($n, $base)) throw new RuntimeException("Il file $n sta fuori dalla cartella del tema.");
            $rel = substr($n, strlen($base));
            foreach (explode('/', $rel) as $seg) {
                if (!preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,99}$/', $seg)) throw new RuntimeException("Nome di file non ammesso nel tema: $rel (niente file nascosti, spazi o caratteri speciali).");
            }
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            if (!in_array($ext, TEMI_ZIP_ESTENSIONI, true)) throw new RuntimeException("Tipo di file non ammesso nel tema: $rel. Ammessi: " . implode(', ', TEMI_ZIP_ESTENSIONI) . '.');
            $voci[$rel] = $i;
        }
        $j = json_decode((string)$zip->getFromIndex($voci['tema.json']), true);
        if (!is_array($j)) throw new RuntimeException('tema.json non è un JSON valido.');
        // Il nome della cartella: quello della cartella nello zip, altrimenti "slug" di tema.json, altrimenti dal nome.
        $slug = strtolower(rtrim($base, '/'));
        if (!temi_slug_valido($slug)) $slug = strtolower((string)($j['slug'] ?? ''));
        if (!temi_slug_valido($slug)) $slug = design_slug((string)($j['nome'] ?? ''));
        if (!temi_slug_valido($slug)) throw new RuntimeException('Non riesco a dare un nome alla cartella del tema: in tema.json scrivi "slug" con lettere minuscole, cifre e trattini.');
        require_once __DIR__ . '/sicurezza.php';
        $firmato = $firma !== '' && sicurezza_pacchetto_firma_ok($zip, $firma);
        if ($firma !== '' && !$firmato) throw new RuntimeException('La firma del tema non corrisponde: lo zip è stato modificato dopo la firma, oppure è firmato con una chiave diversa da quella di questo sito.');
        $php = (bool)array_filter(array_keys($voci), fn($r) => strtolower(pathinfo($r, PATHINFO_EXTENSION)) === 'php');
        return ['slug' => $slug, 'nome' => mb_substr(trim((string)($j['nome'] ?? $slug)), 0, 60), 'voci' => $voci, 'php' => $php, 'firmato' => $firmato];
    } finally {
        $zip->close();
    }
}

/**
 * Installa uno zip di tema in temi/<nome>. Con PHP serve la firma (o la finestra di un'ora aperta in Sicurezza); se
 * Sicurezza chiede pacchetti sempre firmati, la firma serve comunque. Con $sostituisci un tema con lo stesso nome finisce
 * fra le copie (aggiornamenti/temi-precedenti). Non attiva il tema. Ritorna [slug, nome, righe del resoconto].
 */
function temi_installa_zip(string $percorso, bool $sostituisci, string $autore = ''): array {
    temi_proteggi();
    $z = temi_zip_leggi($percorso);
    require_once __DIR__ . '/sicurezza.php';
    $cfg = [];
    try { $cfg = sicurezza_cfg(); } catch (Throwable $e) {}
    if (!$z['firmato'] && !empty($cfg['firma_pacchetti'])) throw new RuntimeException('Tema senza firma: la pagina Sicurezza richiede pacchetti firmati. Firmalo con tools/pacchetto.py --rifirma.');
    if ($z['php'] && !$z['firmato'] && (int)($cfg['root_php_fino'] ?? 0) <= time()) {
        throw new RuntimeException('Il tema "' . $z['nome'] . '" contiene codice PHP, che gira sul tuo server. Per caricarlo serve uno zip firmato (tools/pacchetto.py --rifirma), oppure apri per un\'ora la finestra in Impostazioni, Sicurezza (serve la tua password) e ricaricalo. Fallo solo se il tema viene da una fonte di cui ti fidi.');
    }
    $dest = temi_dir() . '/' . $z['slug'];
    $esiste = is_dir($dest);
    if ($esiste && !$sostituisci) throw new RuntimeException('Esiste già un tema nella cartella temi/' . $z['slug'] . '. Se vuoi sostituirlo, spunta "Sostituisci il tema con lo stesso nome" e ricarica lo zip.');
    // Si scrive in una cartella provvisoria dentro aggiornamenti/ (non raggiungibile dal web), un file alla volta, poi si sposta.
    $tmp = cms_radice() . '/aggiornamenti/temi-nuovi/' . $z['slug'] . '-' . bin2hex(random_bytes(4));
    if (!@mkdir($tmp, 0755, true)) throw new RuntimeException('Non riesco a creare la cartella provvisoria in aggiornamenti/: controlla i permessi.');
    $zip = new ZipArchive();
    $zip->open($percorso);
    try {
        foreach ($z['voci'] as $rel => $i) {
            $f = $tmp . '/' . $rel;
            if (!is_dir(dirname($f)) && !@mkdir(dirname($f), 0755, true)) throw new RuntimeException("Non riesco a creare la cartella di $rel.");
            $dati = $zip->getFromIndex($i);
            if ($dati === false || @file_put_contents($f, $dati) === false) throw new RuntimeException("Non riesco a scrivere $rel.");
            @chmod($f, 0644);
        }
    } catch (Throwable $e) {
        $zip->close();
        temi_cancella_cartella($tmp);
        throw $e;
    }
    $zip->close();
    $copia = '';
    if ($esiste) {
        $copia = temi_cartella_copie() . '/' . $z['slug'] . '-' . date('Ymd-His');
        @mkdir(dirname($copia), 0755, true);
        if (!@rename($dest, $copia)) { temi_cancella_cartella($tmp); throw new RuntimeException('Non riesco a mettere da parte il tema di prima: controlla i permessi di temi/.'); }
    }
    if (!@rename($tmp, $dest)) {
        if ($copia !== '') @rename($copia, $dest);
        temi_cancella_cartella($tmp);
        throw new RuntimeException('Non riesco a spostare il tema in temi/: controlla i permessi.');
    }
    $t = temi_leggi($z['slug']);
    if (!$t) {
        temi_cancella_cartella($dest);
        if ($copia !== '') @rename($copia, $dest);
        throw new RuntimeException('Il tema caricato non si legge (tema.json): tolto, niente è cambiato.');
    }
    $righe = ['Tema "' . $t['nome'] . '" ' . ($esiste ? 'sostituito' : 'caricato') . ' in temi/' . $z['slug'] . ' (' . count($z['voci']) . ' file' . ($z['php'] ? ', con PHP' : '') . ($z['firmato'] ? ', firmato' : '') . ').'];
    if ($copia !== '') $righe[] = 'La versione di prima è in aggiornamenti/temi-precedenti/' . basename($copia) . '.';
    if ($esiste && temi_scelto() === $z['slug']) $righe[] = 'È il tema attivo: premi Attiva per applicare il suo stile e i suoi header e footer nuovi.';
    if ($t['errori']) $righe[] = 'Attenzione: ' . implode(' ', $t['errori']);
    try { sicurezza_log('impostazioni', $autore, 0, 'tema ' . $z['slug'] . ($esiste ? ' sostituito' : ' caricato') . ($z['php'] ? ' (con PHP' . ($z['firmato'] ? ', firmato)' : ', finestra aperta)') : '')); } catch (Throwable $e) {}
    return ['slug' => $z['slug'], 'nome' => $t['nome'], 'righe' => $righe];
}

/** Toglie un tema non in uso: la cartella finisce fra le copie (aggiornamenti/temi-precedenti), da dove si può riprendere. */
function temi_elimina(string $slug, string $autore = ''): string {
    $t = temi_leggi($slug);
    if (!$t) throw new RuntimeException('Tema non trovato.');
    if (temi_scelto() === $slug) throw new RuntimeException('Il tema "' . $t['nome'] . '" è quello scelto per il sito: prima attiva un altro tema o l\'aspetto di base.');
    $copia = temi_cartella_copie() . '/' . $slug . '-' . date('Ymd-His');
    @mkdir(dirname($copia), 0755, true);
    if (!@rename($t['cartella'], $copia)) throw new RuntimeException('Non riesco a spostare la cartella del tema: controlla i permessi di temi/.');
    try { require_once __DIR__ . '/sicurezza.php'; sicurezza_log('impostazioni', $autore, 0, "tema $slug eliminato (copia in aggiornamenti/temi-precedenti)"); } catch (Throwable $e) {}
    return basename($copia);
}

function temi_cancella_cartella(string $dir): void {
    if (!is_dir($dir) || is_link($dir)) { if (is_file($dir) || is_link($dir)) @unlink($dir); return; }
    foreach (scandir($dir) ?: [] as $f) if ($f !== '.' && $f !== '..') temi_cancella_cartella("$dir/$f");
    @rmdir($dir);
}

/** Un file zip provvisorio (da cancellare dopo l'invio). */
function temi_zip_provvisorio(): string {
    $dir = cms_radice() . '/aggiornamenti';
    $f = (is_dir($dir) && is_writable($dir) ? $dir : sys_get_temp_dir()) . '/tema-' . bin2hex(random_bytes(6)) . '.zip';
    return $f;
}

/** Lo zip di un tema presente: tutti i suoi file dentro una cartella con il suo nome (senza i file nascosti). */
function temi_zip_crea(string $slug): string {
    $t = temi_leggi($slug);
    if (!$t) throw new RuntimeException('Tema non trovato.');
    $out = temi_zip_provvisorio();
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Non riesco a creare lo zip.');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($t['cartella'], FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || $f->isLink()) continue;
        $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($t['cartella']) + 1);
        if (preg_match('~(^|/)\.~', $rel)) continue;
        $zip->addFile($f->getPathname(), $slug . '/' . $rel);
    }
    $zip->close();
    return $out;
}

/**
 * L'aspetto di adesso come tema da scaricare: lo Stile del sito (sezioni dell'aspetto), l'header e il footer predefiniti
 * (con il menu indicato per posizione), il CSS personalizzato e il CSS del tema attivo in tema.css, i colori delle
 * infografiche del tema attivo. Il PHP del tema attivo non entra. Il nome della cartella: "il-mio-aspetto".
 */
function temi_zip_aspetto_attuale(): string {
    require_once __DIR__ . '/modelli.php';
    require_once __DIR__ . '/menu-nav.php';
    $slug = 'il-mio-aspetto';
    $v = design_valori(true);
    $stile = array_intersect_key($v, array_flip(TEMI_SEZIONI_STILE));
    // Header e footer predefiniti e pubblicati; un menu che ha una posizione si scrive come "posizione:<nome>".
    $pos = [];
    foreach (menu_tutti() as $m) if ((string)$m['posizione'] !== '') $pos[(string)$m['id']] = 'posizione:' . $m['posizione'];
    $giro = function (array $nodi) use (&$giro, $pos): array {
        foreach ($nodi as &$n) {
            if (!is_array($n)) continue;
            $m = (string)($n['impostazioni']['menu'] ?? '');
            if ($m !== '' && isset($pos[$m])) $n['impostazioni']['menu'] = $pos[$m];
            if (isset($n['figli']) && is_array($n['figli'])) $n['figli'] = $giro($n['figli']);
        }
        return $nodi;
    };
    $modelli = [];
    foreach (modelli_tutti(true) as $m) {
        if (empty($m['predefinito']) || ($m['status'] ?? '') !== 'published' || !empty($m['cestino'])) continue;
        $a = modelli_albero($m);
        if (!empty($a['elementi'])) { $a['elementi'] = $giro($a['elementi']); $modelli[(string)$m['tipo']] = $a; }
    }
    $attivo = temi_attivo() !== '' ? temi_leggi(temi_attivo()) : null;
    $css = '';
    if ($attivo && $attivo['css'] !== '') $css .= '/* Dal tema ' . $attivo['nome'] . " */\n" . (string)file_get_contents($attivo['cartella'] . '/' . $attivo['css']) . "\n";
    if (trim((string)($v['css']['sito'] ?? '')) !== '') $css .= "/* Il CSS personalizzato dello Stile del sito */\n" . $v['css']['sito'] . "\n";
    $nomeSito = (string)(design_sito()['nome'] ?? '');
    $j = ['nome' => 'Il mio aspetto', 'versione' => date('Y.m.d'), 'autore' => $nomeSito, 'slug' => $slug,
        'descrizione' => 'L\'aspetto di ' . ($nomeSito !== '' ? $nomeSito : 'questo sito') . ' salvato il ' . date('d/m/Y') . ($attivo ? ', partito dal tema ' . $attivo['nome'] : '') . '.',
        'stile' => 'stile.json'];
    if ($modelli) $j['modelli'] = 'modelli.json';
    if ($css !== '') $j['css'] = 'tema.css';
    if ($attivo) {
        $raw = temi_json($attivo['cartella'] . '/tema.json');
        if (is_array($raw['infografiche'] ?? null)) $j['infografiche'] = $raw['infografiche'];
    }
    $flag = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $out = temi_zip_provvisorio();
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Non riesco a creare lo zip.');
    $zip->addFromString("$slug/tema.json", json_encode($j, $flag) . "\n");
    $zip->addFromString("$slug/stile.json", json_encode($stile, $flag) . "\n");
    if ($modelli) $zip->addFromString("$slug/modelli.json", json_encode($modelli, $flag) . "\n");
    if ($css !== '') $zip->addFromString("$slug/tema.css", $css);
    $zip->close();
    return $out;
}
