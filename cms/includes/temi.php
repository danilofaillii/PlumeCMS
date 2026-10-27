<?php
/**
 * I temi del sito (27 ottobre 2026). Un tema cambia l'aspetto intero: colori, caratteri, pulsanti, header, footer, CSS,
 * e se vuole le pagine (viste in PHP, come i temi di WordPress). Menu e contenuti non si toccano.
 *
 * Un tema è una cartella temi/<nome>/ (nome: lettere minuscole, cifre e trattini):
 *   tema.json      obbligatorio: nome, versione, autore, descrizione, anteprima, css, stile, modelli (i file sono facoltativi)
 *   stile.json     lo Stile del sito del tema (stesso formato di design_valori(), solo le sezioni dell'aspetto)
 *   modelli.json   {"header": albero dell'editor, "footer": albero}
 *   tema.css       CSS in più, caricato dopo sito.css e prima dello Stile del sito
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
    if ($a !== '' && temi_file_valido($a, ['png', 'jpg', 'jpeg', 'webp', 'svg']) && is_file("$dir/$a")) $t['anteprima'] = temi_url($slug) . '/' . $a . '?v=' . filemtime("$dir/$a");
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
    if ($t === null || $t['css'] === '') return '';
    $f = $t['cartella'] . '/' . $t['css'];
    return '<link rel="stylesheet" id="tema-css" href="' . h(temi_url($t['slug']) . '/' . $t['css'] . '?v=' . (is_file($f) ? filemtime($f) : 0)) . '">';
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
                $valido = editor_valida($albero);
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
