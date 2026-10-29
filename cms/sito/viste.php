<?php
/**
 * Le pagine del sito: ogni funzione restituisce l'HTML del suo pezzo.
 * Stessa grafica e stesse classi del CSS in assets/sito.css.
 */

define('SITO_ASSET', cms_base() . '/sito/assets/');   // gli asset del sito, dalla cartella del CMS
define('SITO_U', cms_uploads_url());                   // le immagini caricate
/** "newsletter Novità", oppure solo "newsletter": il nome con cui il sito chiama la sua newsletter. */
function sito_newsletter_nome(): string {
    $n = trim((string)cms_impostazione('newsletter_nome', ''));
    return $n !== '' ? "newsletter $n" : 'newsletter';
}

/** Le voci della testata fissa (quando non c'è un modello di header): dal modulo del sito, altrimenti Home e blog. */
function sito_menu_fisso(): array {
    return (array)cms_filtro('sito_menu', array_filter([['Home', '/'], SITO_PAGINA_BLOG !== '' ? ['Blog', SITO_PAGINA_BLOG] : null]));
}
/** Le categorie offerte come filtri in testa al blog: dal modulo del sito, altrimenti nessuna. */
function sito_filtri_blog(): array {
    return (array)cms_filtro('blog_filtri', []);
}
// design: lo Stile del sito (variabili globali, tipografia, pulsanti...) entra nel <head> dopo sito.css.
require_once dirname(__DIR__) . '/includes/design.php';
// temi: il tema attivo (CSS, viste). Il suo PHP gira solo se il sito pubblico lo avvia (temi_avvia_sito in sito/index.php).
require_once dirname(__DIR__) . '/includes/temi.php';

function sito_versione_asset(string $f): string {
    $p = __DIR__ . '/assets/' . $f;
    return SITO_ASSET . $f . (is_file($p) ? '?v=' . filemtime($p) : '');
}

/** I popup attivi per questa pagina (genere e percorso li mette il front controller). */
function vista_popup(): string {
    if (empty($GLOBALS['sito_percorso']) || !empty($GLOBALS['sito_senza_popup'])) return '';
    require_once dirname(__DIR__) . '/includes/popup.php';
    return popup_html(popup_per($GLOBALS['sito_percorso'], (string)($GLOBALS['sito_genere'] ?? 'altro')), $GLOBALS['sito_percorso']);
}

/** La pagina intera: testa con i metadati, intestazione, contenuto, piede. */
function vista_pagina(array $meta, string $corpo, string $jsonld = '', string $attivo = ''): string {
    $righe = ['<title>' . h($meta['titolo']) . '</title>'];
    if ($meta['descrizione'] !== '') $righe[] = '<meta name="description" content="' . h($meta['descrizione']) . '">';
    $righe[] = '<meta name="robots" content="' . sito_robots_meta($meta['robots']) . '">';
    if ($meta['canonical'] !== '') $righe[] = '<link rel="canonical" href="' . h($meta['canonical']) . '">';
    foreach ($meta['og'] as $k => $v) {
        $righe[] = '<meta property="' . $k . '" content="' . h((string)$v) . '">';
    }
    if (!empty($meta['og']['og:title'])) {
        $righe[] = '<meta name="twitter:card" content="summary_large_image">';
    }
    $menu = '';
    foreach (sito_menu_fisso() as [$nome, $href]) {
        $menu .= '<a href="' . $href . '"' . ($attivo === $href ? ' aria-current="page"' : '') . '>' . h($nome) . '</a>';
    }
    $menuMobile = '';
    foreach (sito_menu_fisso() as [$nome, $href]) $menuMobile .= '<a href="' . $href . '">' . h($nome) . '</a>';
    // modelli: header e footer dal Theme Builder (includes/modelli.php). Senza modelli, la testata e il piede scritti qui.
    $GLOBALS['sito_attivo'] = $attivo;
    [$testata, $piede, $cssModelli] = vista_header_footer($menu, $menuMobile);
    // 1.8.0: la firma sotto il piede ("Un nuovo sito plumeCMS"), che si cambia o si spegne in Impostazioni.
    require_once dirname(__DIR__) . '/includes/firma.php';
    $piede .= firma_html();

    $testa = '<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="cms-base" content="' . h(cms_base()) . '">
<meta name="theme-color" content="' . design_theme_color() . '">
' . implode("\n", $righe) . '
<link rel="preload" href="' . SITO_ASSET . 'font/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="' . sito_versione_asset('sito.css') . '">
<link rel="stylesheet" href="' . sito_versione_asset('tema.css') . '">
' . (function_exists('temi_css_link') && ($tl = temi_css_link()) !== '' ? $tl . "\n" : '') . design_head() . $cssModelli . '
' . vista_favicon() . '
<link rel="alternate" type="application/rss+xml" title="' . h(SITO_NOME) . '" href="' . SITO_URL . '/feed/">
' . $jsonld;
    $popup = vista_popup();
    // temi: un tema può ridisegnare la pagina intera (viste/documento.php) con i pezzi già pronti.
    $doc = vista_tema('documento', ['testa' => $testa, 'testata' => $testata, 'corpo' => $corpo, 'piede' => $piede, 'popup' => $popup, 'meta' => $meta]);
    if ($doc !== null) return $doc;

    return '<!DOCTYPE html>
<html lang="it">
<head>
' . $testa . '
</head>
<body>
<a class="salta" href="#contenuto">Vai al contenuto</a>
' . $testata . '
<main id="contenuto">' . $corpo . '</main>' . $popup . '
' . $piede . '
</body>
</html>';
}

/** temi: la vista del tema attivo, se ne ha una con questo nome (includes/temi.php), altrimenti null. */
function vista_tema(string $nome, array $variabili = []): ?string {
    return function_exists('temi_vista') ? temi_vista($nome, $variabili) : null;
}

/**
 * modelli: la testata e il piede della pagina. Dal Theme Builder (includes/modelli.php) se c'è un modello,
 * altrimenti quelli scritti qui, identici a prima. Ritorna [testata, piede, <style> con il CSS dei modelli].
 */
function vista_header_footer(string $menu, string $menuMobile): array {
    // La testata e il piede fissi: dal modulo del sito, altrimenti una versione essenziale con il nome del sito.
    $fissi = cms_filtro('testata_fissa', null, $menu, $menuMobile);
    if (is_array($fissi) && count($fissi) === 2) {
        [$testata, $piede] = $fissi;
    } else {
        $nome = h((string)cms_impostazione('sito_nome', CMS_NOME)); $sotto = h((string)cms_impostazione('sito_sottotitolo', ''));
        $testata = '<header class="top"><div class="wrap"><a class="marchio" href="/"><b>' . mb_strtoupper($nome) . '</b>' . ($sotto !== '' ? '<small>' . mb_strtoupper($sotto) . '</small>' : '') . '</a>'
            . '<nav class="nav" aria-label="Principale">' . $menu . '</nav><details class="menu"><summary>Menu</summary><nav aria-label="Principale">' . $menuMobile . '</nav></details></div></header>';
        $piede = '<footer class="piede"><div class="wrap"><div><a class="marchio" href="/"><b>' . mb_strtoupper($nome) . '</b></a><p>' . h((string)cms_impostazione('sito_frase', '')) . '</p></div></div></footer>';
    }
    $css = '';
    if (is_file(dirname(__DIR__) . '/includes/modelli.php')) {
        require_once dirname(__DIR__) . '/includes/modelli.php';
        $h = modelli_header_per();
        $f = modelli_footer_per();
        if ($h !== null) { $testata = $h[0]; $css .= $h[1]; }
        if ($f !== null) { $piede = $f[0]; $css .= $f[1]; }
    }
    return [$testata, $piede, $css !== '' ? '<style id="ed-css-tema">' . str_replace('</', '<\\/', $css) . '</style>' : ''];
}

// ------------------------------------------------------------
// Schede e paginazione
// ------------------------------------------------------------

function vista_scheda(array $a): string {
    $cat = $a['categorie'][0] ?? '';
    $nome = $cat !== '' ? (sito_categorie()[$cat]['name'] ?? '') : '';
    $meta = ($a['pillar'] ? '<b>Pillar</b> · ' : '') . h($nome) . ($nome !== '' ? ' · ' : '') . sito_data_estesa($a['data']);
    return '<a class="card' . ($a['pillar'] ? ' card-pillar' : '') . '" href="' . h($a['percorso']) . '"><span class="card-meta">' . $meta
        . '</span><span class="card-titolo">' . h($a['titolo']) . '</span></a>';
}

function vista_griglia(array $articoli): string {
    if (!$articoli) return '<p class="vuoto">Qui non ci sono ancora articoli.</p>';
    return '<div class="griglia">' . implode('', array_map('vista_scheda', $articoli)) . '</div>';
}

/** Paginazione: /base/, /base/page/2/, ... */
function vista_paginazione(string $base, int $pagina, int $totale): string {
    if ($totale < 2) return '';
    $out = '';
    for ($n = 1; $n <= $totale; $n++) {
        $out .= $n === $pagina ? '<span aria-current="page">' . $n . '</span>' : '<a href="' . h(sito_paginato($base, $n)) . '">' . $n . '</a>';
    }
    return '<nav class="paginazione" aria-label="Pagine">' . $out . '</nav>';
}

function vista_testa_sezione(string $occhiello, string $titolo, string $intro = ''): string {
    return '<span class="occhiello">' . $occhiello . '</span><h1 class="sez-titolo" style="font-size:clamp(34px,4.6vw,56px)">' . $titolo . '</h1>'
        . ($intro !== '' ? '<p class="sez-intro">' . $intro . '</p>' : '');
}

// ------------------------------------------------------------
// Contenuti
// ------------------------------------------------------------

/** Sommario dalle H2 con id, come lo scrive il publisher. */
function vista_voci_sommario(string $html): array {
    preg_match_all('~<h2[^>]*id="([^"]+)"[^>]*>([\s\S]*?)</h2>~', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) {
        $t = trim(strip_tags($x[2]));
        if (!preg_match('/^sommario$/i', $t)) $out[] = ['id' => $x[1], 'titolo' => $t];
    }
    return $out;
}

function vista_in_breve(string $testo): string {
    return $testo === '' ? '' : '<aside class="in-breve" id="in-breve" aria-label="In breve"><b>In breve</b><p>' . h($testo) . '</p></aside>';
}

/** La testata dell'articolo: occhiello con la categoria, titolo, autore, data, minuti di lettura. */
function vista_articolo_testa(array $c, string $html): string {
    $cat = isset($c['categorie'][0]) ? (sito_categorie()[$c['categorie'][0]] ?? null) : null;
    $occhiello = ($c['pillar'] ? 'Pillar' : 'Articolo')
        . ($cat ? ' · <a href="/category/' . h($cat['slug']) . '/" style="text-decoration:none">' . h($cat['name']) . '</a>' : '');
    return '<div class="wrap"><header class="art-testa"><span class="occhiello">' . $occhiello . '</span><h1>' . h($c['titolo']) . '</h1>'
        . '<div class="art-meta">' . (cms_impostazione('autore_predefinito', '') !== '' ? '<span>' . h((string)cms_impostazione('autore_predefinito')) . '</span>' : '') . '<time datetime="' . h($c['data']) . '">' . sito_data_estesa($c['data']) . '</time>'
        . ($html !== '' ? '<span>' . (($m = sito_minuti_lettura($html)) === 1 ? '1 minuto' : $m . ' minuti') . ' di lettura</span>' : '') . '</div></header></div>';
}

/**
 * Articolo composto con l'editor. Se tutte le sezioni sono "Articolo" (quelle della conversione a blocchi),
 * la pagina ha la stessa cornice dell'articolo classico: testata, sommario laterale, colonna di lettura, FAQ.
 * Altrimenti le sezioni escono a tutta pagina sotto la testata. Ritorna [corpo, righe per la testa].
 */
function vista_articolo_editor(array &$c): array {
    [$html, $css] = sito_pagina_editor($c);
    $testa = $css !== '' ? '<style id="ed-css">' . str_replace('</', '<\\/', $css) . '</style>' : '';
    // controlli: editor-pagina.js serve per i video, le animazioni all'ingresso e gli elementi sticky (editor_serve_script).
    if (function_exists('editor_serve_script') ? editor_serve_script($html) : str_contains($html, 'class="ed-video"')) $html .= '<script src="' . sito_versione_asset('editor-pagina.js') . '" defer></script>';
    // widget-extra: foglio di stile e script dei widget registrati (icone, caroselli, schede...), solo se la pagina ne contiene uno.
    if (function_exists('editor_asset_pagina')) { $asset = editor_asset_pagina($html); foreach ($asset['css'] ?? [] as $f) $testa = '<link rel="stylesheet" href="' . sito_versione_asset($f) . '">' . $testa; foreach ($asset['js'] ?? [] as $f) $html .= '<script src="' . sito_versione_asset($f) . '" defer></script>'; }
    // L'autore della frase di un contenuto con la scheda Citazione si firma anche sotto le sezioni dell'editor.
    $autore = trim((string)($c['riga']['author_quote'] ?? ''));
    $firma = $autore !== '' ? '<p class="citazione-autore">' . h($autore) . '</p>' : '';
    $soloArticolo = $html !== '' && !preg_match('~<div class="ed-contenitore (?!ed-articolo)~', $html);
    if ($soloArticolo) {
        $voci = vista_voci_sommario($html);
        $toc = count($voci) >= 3 ? '<nav class="toc" aria-label="Sommario"><b>Sommario</b><ol>'
            . implode('', array_map(fn($v) => '<li><a href="#' . h($v['id']) . '">' . h($v['titolo']) . '</a></li>', $voci)) . '</ol></nav>' : '';
        $faq = '';
        if ($c['faq'] && !preg_match('~<h2[^>]*>[^<]*domande frequenti~i', $html) && !str_contains($html, 'ed-w-faq')) {
            $faq = '<section id="domande-frequenti" aria-label="Domande frequenti"><h2>Domande frequenti</h2>'
                . implode('', array_map(fn($f) => '<div><h3>' . h($f['domanda']) . '</h3><p>' . h($f['risposta']) . '</p></div>', $c['faq'])) . '</section>';
        }
        return [substr(vista_articolo_testa($c, $html), 0, -6)
            . '<div class="art"' . ($toc === '' ? ' style="grid-template-columns:minmax(0,1fr)"' : '') . '>' . $toc
            . '<article class="corpo"><div class="ed-pagina ed-articolo-pagina">' . vista_in_breve($c['tldr']) . $html . '</div>' . $faq . ($firma !== '' ? '<div>' . $firma . '</div>' : '') . '</article></div></div>', $testa];
    }
    $firma = $firma !== '' ? '<div class="wrap ed-stretta">' . $firma . '</div>' : '';
    return [vista_articolo_testa($c, $html) . '<div class="ed-pagina">' . ($c['tldr'] !== '' ? '<div class="wrap ed-stretta">' . vista_in_breve($c['tldr']) . '</div>' : '') . $html . $firma . '</div>', $testa];
}

function vista_articolo(array &$c): string {
    $html = sito_html_contenuto($c);
    if (($t = vista_tema('articolo', ['c' => $c, 'html' => $html])) !== null) return $t;
    $voci = vista_voci_sommario($html);
    $toc = '';
    if (count($voci) >= 3) {
        // Il sommario dentro il testo diventa la colonna laterale: qui si toglie per non mostrarlo due volte.
        $html = preg_replace('~<h2[^>]*>\s*Sommario\s*</h2>\s*<(ul|ol)[\s\S]*?</\1>~i', '', $html, 1);
        $toc = '<nav class="toc" aria-label="Sommario"><b>Sommario</b><ol>'
            . implode('', array_map(fn($v) => '<li><a href="#' . h($v['id']) . '">' . h($v['titolo']) . '</a></li>', $voci)) . '</ol></nav>';
    }
    // Le FAQ marcate nel JSON-LD devono essere visibili: se il testo non le contiene già, si mostrano qui.
    $faq = '';
    if ($c['faq'] && !preg_match('~<h2[^>]*>[^<]*domande frequenti~i', $html)) {
        $faq = '<section id="domande-frequenti" aria-label="Domande frequenti"><h2>Domande frequenti</h2>'
            . implode('', array_map(fn($f) => '<div><h3>' . h($f['domanda']) . '</h3><p>' . h($f['risposta']) . '</p></div>', $c['faq'])) . '</section>';
    }
    return substr(vista_articolo_testa($c, $html), 0, -6)
        . '<div class="art"' . ($toc === '' ? ' style="grid-template-columns:minmax(0,1fr)"' : '') . '>' . $toc
        . '<article class="corpo">' . vista_in_breve($c['tldr']) . '<div>' . $html . '</div>' . $faq . '</article></div></div>';
}

/**
 * 1.6.0: il riquadro "Aggiungi sempre alla fine" (includes/modelli.php, modelli_fine_articolo) in fondo all'articolo.
 * Dentro la colonna di lettura, prima della chiusura di <article>; un articolo a tutta pagina (sezioni dell'editor non
 * "Articolo", o la vista di un tema senza <article>) lo riceve in fondo, largo quanto la lettura. Ritorna [corpo, css].
 */
function vista_fine_articolo(string $corpo): array {
    if (!is_file(dirname(__DIR__) . '/includes/modelli.php')) return [$corpo, ''];
    require_once dirname(__DIR__) . '/includes/modelli.php';
    $f = modelli_fine_articolo();
    if ($f === null) return [$corpo, ''];
    [$html, $css] = $f;
    $css = $css !== '' ? '<style id="ed-css-fine">' . str_replace('</', '<\\/', $css) . '</style>' : '';
    // Si cerca la chiusura dell'<article class="corpo"> (contando gli <article> dentro, come le schede di una Griglia di
    // articoli); senza la colonna di lettura, un articolo dell'editor a tutta pagina lo riceve fuori, mai dentro l'ultima
    // scheda di un widget. La vista di un tema (niente "corpo", niente ed-pagina) lo riceve prima dell'ultimo </article>.
    $a = strpos($corpo, '<article class="corpo"');
    if ($a !== false) $i = vista_chiusura_article($corpo, $a);
    elseif (!str_contains($corpo, 'class="ed-pagina')) $i = strrpos($corpo, '</article>');
    else $i = false;
    if ($i !== false) return [substr($corpo, 0, $i) . $html . substr($corpo, $i), $css];
    // Fuori dall'articolo resta dentro un div.corpo: titoli e testi escono come nella colonna di lettura (e nella tela dell'editor).
    return [$corpo . '<div class="wrap ed-stretta fine-articolo-fuori"><div class="corpo">' . $html . '</div></div>', $css];
}

/** La posizione del </article> che chiude l'<article> che comincia in $da, contando quelli annidati; false se manca. */
function vista_chiusura_article(string $html, int $da): int|false {
    $livello = 0;
    $pos = $da;
    while (preg_match('~<(/?)article\b~i', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $livello += $m[1][0] === '/' ? -1 : 1;
        if ($livello === 0) return $m[0][1];
        $pos = $m[0][1] + strlen($m[0][0]);
    }
    return false;
}

/** Pagine fisse senza struttura dell'editor: il testo della scheda passa così com'è, dentro la grafica del sito. */
function vista_pagina_generica(array &$c): string {
    if (($t = vista_tema('pagina', ['c' => $c, 'html' => vista_moduli_in_pagina($c['percorso'], sito_html_contenuto($c))])) !== null) return $t;
    return '<div class="wrap"><article class="pagina-generica"><h1>' . h($c['titolo']) . '</h1>' . vista_in_breve($c['tldr'])
        . '<div class="corpo">' . vista_moduli_in_pagina($c['percorso'], sito_html_contenuto($c)) . '</div></article></div>';
}

/**
 * Pagina fatta con l'editor: le sezioni a tutta larghezza, il loro CSS nella testa della pagina.
 * Ritorna [corpo, righe per la testa].
 */
function vista_pagina_editor(array &$c): array {
    [$html, $css] = sito_pagina_editor($c);
    $testa = $css !== '' ? '<style id="ed-css">' . str_replace('</', '<\\/', $css) . '</style>' : '';
    // controlli: editor-pagina.js serve per i video, le animazioni all'ingresso e gli elementi sticky (editor_serve_script).
    if (function_exists('editor_serve_script') ? editor_serve_script($html) : str_contains($html, 'class="ed-video"')) $html .= '<script src="' . sito_versione_asset('editor-pagina.js') . '" defer></script>';
    // widget-extra: foglio di stile e script dei widget registrati (icone, caroselli, schede...), solo se la pagina ne contiene uno.
    if (function_exists('editor_asset_pagina')) { $asset = editor_asset_pagina($html); foreach ($asset['css'] ?? [] as $f) $testa = '<link rel="stylesheet" href="' . sito_versione_asset($f) . '">' . $testa; foreach ($asset['js'] ?? [] as $f) $html .= '<script src="' . sito_versione_asset($f) . '" defer></script>'; }
    return ['<div class="ed-pagina">' . $html . '</div>', $testa];
}

// ------------------------------------------------------------
// Moduli nelle pagine: newsletter e form del costruttore (motore in includes/moduli.php e includes/form.php)
// ------------------------------------------------------------

/** Il testo di una pagina scritta nella scheda classica, con i moduli che un modulo del sito vuole aggiungervi (filtro pagina_moduli). */
function vista_moduli_in_pagina(string $percorso, string $html): string {
    return (string)cms_filtro('pagina_moduli', $html, $percorso);
}

/**
 * Un modulo da compilare: "newsletter" o "form:<id>" (i form del costruttore). Un altro tipo, rimasto in una pagina o in
 * un popup, lo disegna il modulo del sito che lo conosce (filtro modulo_html); se nessuno lo conosce non esce niente.
 */
function vista_modulo(string $tipo, string $percorso): string {
    $codice = (string)($_GET['modulo'] ?? '');
    if (preg_match('/^form:(\d+)$/', $tipo, $m)) {
        require_once dirname(__DIR__) . '/includes/form.php';
        $f = form_leggi((int)$m[1]);
        if (!$f || !$f['attivo']) return '';
        // L'errore vale solo per il form che l'ha prodotto (?f=ID), così in pagina non si segnano tutti.
        [$errore, $campo] = isset($_GET['f']) && (int)$_GET['f'] === (int)$f['id'] ? form_errore($codice) : ['', ''];
        return form_html($f, $percorso, $errore, $campo);
    }
    if ($tipo !== 'newsletter') {
        $html = (string)cms_filtro('modulo_html', '', $tipo, $percorso);
        // Fino alla 1.2.7 la pagina Contatti dell'installatore usciva con il modulo "laser" al posto del suo form:
        // se nessun modulo del sito lo conosce, al suo posto va il form Contatti, quello che la pagina doveva avere.
        if ($html === '' && $tipo === 'laser') {
            require_once dirname(__DIR__) . '/includes/form.php';
            foreach (form_tutti() as $f) if ($f['attivo'] && mb_strtolower($f['nome']) === 'contatti') return vista_modulo('form:' . (int)$f['id'], $percorso);
        }
        return $html;
    }
    $errore = isset($_GET['f']) ? '' : (MODULI_ERRORI[$codice] ?? '');
    $campo = fn($nome, $etichetta, $tipoInput, $auto, $extra = '') => '<label><span>' . $etichetta . '</span><input name="' . $nome . '" type="' . $tipoInput
        . '" autocomplete="' . $auto . '" required ' . $extra . '></label>';
    return '<section class="modulo" id="modulo" aria-labelledby="modulo-titolo"><h2 id="modulo-titolo">Iscriviti alla ' . h(sito_newsletter_nome()) . '</h2>'
        . '<p>' . h((string)cms_impostazione('newsletter_descrizione', 'Ti scrivo quando esce qualcosa di nuovo.')) . ' Prima ti mando un&#039;email per confermare l&#039;indirizzo.</p>'
        . ($errore !== '' ? '<p class="modulo-errore" role="alert">' . h($errore) . '</p>' : '')
        . '<form method="post" action="' . cms_base() . '/api/modulo.php" data-captcha>'
        . '<input type="hidden" name="modulo" value="newsletter"><input type="hidden" name="pagina" value="' . h($percorso) . '"><input type="hidden" name="captcha" value="">'
        . '<div class="modulo-campi">'
        . $campo('nome', 'Nome', 'text', 'given-name', 'maxlength="100"')
        . $campo('cognome', 'Cognome', 'text', 'family-name', 'maxlength="100"')
        . $campo('email', 'Email', 'email', 'email', 'maxlength="190"')
        . '</div>'
        // Campo esca: invisibile alle persone, i programmi automatici lo riempiono.
        . '<div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<label class="spunta"><input type="checkbox" name="privacy" value="1" required> <span>Ho letto l&#039;<a href="/privacy-policy/" target="_blank">informativa privacy</a> e accetto il trattamento dei miei dati per ricevere la newsletter.</span></label>'
        . '<label class="captcha"><input type="checkbox"> <span>Sono una persona</span> <span class="captcha-stato" aria-live="polite"></span></label>'
        . '<button class="btn btn-viola" type="submit">Iscrivimi</button>'
        . '</form><script src="' . sito_versione_asset('moduli.js') . '" defer></script></section>';
}

function vista_cortesia(string $occhiello, string $titolo, string $testo, string $azione = ''): string {
    return '<div class="wrap"><article class="pagina-generica cortesia"><span class="occhiello">' . $occhiello . '</span><h1>' . $titolo . '</h1>'
        . '<div class="corpo">' . $testo . '</div>' . $azione . '</article></div>';
}

function vista_newsletter_grazie(): string {
    return vista_cortesia('Newsletter', 'Ci sei quasi.',
        '<p>Ti ho appena scritto all&#039;indirizzo che hai indicato. Apri l&#039;email e premi <b>«Confermo l&#039;iscrizione»</b>: da lì sei dentro.</p>'
        . '<p>Se entro qualche minuto la casella è vuota, guarda nella cartella spam o in quella delle promozioni.</p>');
}

function vista_newsletter_confermata(bool $ok): string {
    return $ok
        ? vista_cortesia('Newsletter', 'Sei dentro.',
            '<p>Da adesso ricevi la ' . h(sito_newsletter_nome()) . '. In fondo a ogni email c&#039;è il link per uscire, in un clic.</p>',
            '<p><a class="btn btn-viola" href="' . h(SITO_PAGINA_BLOG) . '">Intanto, leggi il blog</a></p>')
        : vista_cortesia('Newsletter', 'Questo link è scaduto.',
            '<p>Il link è scaduto oppure è già stato usato. Iscriviti di nuovo: ti arriva un link nuovo in un minuto.</p>',
            '<p><a class="btn btn-viola" href="' . NEWSLETTER_PAGINA . '#modulo">Iscriviti</a></p>');
}

function vista_newsletter_esci(?array $iscritto, bool $fatto, string $i, string $t): string {
    if (!$iscritto) {
        return vista_cortesia('Newsletter', 'Link non valido.', '<p>Per uscire dalla newsletter usa il link in fondo a una delle email che hai ricevuto.</p>');
    }
    if ($fatto || $iscritto['stato'] === 'uscito') {
        return vista_cortesia('Newsletter', 'Fatto. Sei fuori.',
            '<p>L&#039;indirizzo ' . h($iscritto['email']) . ' non riceverà più la newsletter.</p><p>Se cambi idea, ti iscrivi di nuovo dalla pagina della newsletter.</p>',
            '<p><a class="btn btn-filo" href="' . NEWSLETTER_PAGINA . '">Pagina della newsletter</a></p>');
    }
    // Un pulsante, e non l'uscita diretta: i filtri antispam aprono i link delle email e farebbero uscire chi non lo ha chiesto.
    return vista_cortesia('Newsletter', 'Vuoi uscire dalla newsletter?',
        '<p>L&#039;indirizzo ' . h($iscritto['email']) . ' smette di ricevere la ' . h(sito_newsletter_nome()) . '.</p>',
        '<form method="post" action="' . NEWSLETTER_ESCI . '?i=' . h($i) . '&amp;t=' . h($t) . '"><button class="btn btn-viola" type="submit">Sì, toglimi dall&#039;elenco</button></form>');
}

function vista_termine(array &$c): string {
    // Il primo paragrafo è la definizione: si mostra già nel riquadro, si toglie dal corpo.
    $corpo = preg_replace('~<h2[^>]*>[^<]*</h2>\s*<p>[\s\S]*?</p>~', '', sito_html_contenuto($c), 1);
    $t = mb_strtolower($c['titolo']);
    $collegato = sito_ultimi(1, fn($a) => str_contains(mb_strtolower($a['titolo']), $t))[0] ?? null;
    return '<div class="wrap"><article class="termine"><a href="/glossario/" class="occhiello" style="text-decoration:none">← ' . h((string)cms_impostazione('glossario_titolo', 'Glossario')) . '</a>'
        . '<h1>' . h($c['titolo']) . '</h1>' . ($c['tldr'] !== '' ? '<p class="definizione">' . h($c['tldr']) . '</p>' : '')
        . '<div class="corpo">' . $corpo . '</div>'
        . ($collegato ? '<h2>Da leggere</h2><div class="collegati">' . vista_scheda($collegato) . '</div>' : '')
        . '</article></div>';
}

// ------------------------------------------------------------
// Elenchi
// ------------------------------------------------------------

function vista_blog(array $v): string {
    if (($t = vista_tema('blog', ['v' => $v])) !== null) return $t;
    $filtri = '<a href="' . SITO_PAGINA_BLOG . '">Tutti</a>';
    $cat = sito_categorie();
    foreach (sito_filtri_blog() as $s) if (isset($cat[$s])) $filtri .= '<a href="/category/' . $s . '/">' . h($cat[$s]['name']) . '</a>';
    return '<div class="wrap" style="padding-top:56px;padding-bottom:40px">'
        . vista_testa_sezione((string)cms_impostazione('blog_occhiello', 'Il blog'), (string)cms_impostazione('blog_titolo', 'Gli articoli'), (string)cms_impostazione('blog_intro', ''))
        . '<nav class="filtri" aria-label="Categorie">' . $filtri . '</nav>'
        . vista_griglia($v['articoli']) . vista_paginazione(SITO_PAGINA_BLOG, $v['pagina'], $v['totale']) . '</div>';
}

function vista_archivio(array $v): string {
    if (($t = vista_tema('archivio', ['v' => $v])) !== null) return $t;
    $etichetta = ['category' => 'Categoria', 'tag' => 'Tag'][$v['tassonomia']] ?? 'Archivio';
    return '<div class="wrap" style="padding-top:56px;padding-bottom:40px">'
        . vista_testa_sezione($etichetta, h($v['nome']), h($v['descrizione']))
        . vista_griglia($v['articoli']) . vista_paginazione($v['base'], $v['pagina'], $v['totale']) . '</div>';
}

function vista_glossario(array $termini): string {
    $gruppi = [];
    foreach ($termini as $t) $gruppi[mb_strtoupper(mb_substr($t['titolo'], 0, 1))][] = $t;
    $lettere = '';
    $sezioni = '';
    foreach ($gruppi as $l => $voci) {
        $lettere .= '<a href="#g-' . h($l) . '">' . h($l) . '</a>';
        $sezioni .= '<section class="g-gruppo" id="g-' . h($l) . '"><h3>' . h($l) . '</h3><ul>'
            . implode('', array_map(fn($t) => '<li><a href="' . h($t['percorso']) . '">' . h($t['titolo']) . '</a></li>', $voci)) . '</ul></section>';
    }
    return '<div class="wrap" style="padding-top:56px;padding-bottom:40px">'
        . vista_testa_sezione((string)cms_impostazione('glossario_titolo', 'Glossario'), (string)cms_impostazione('glossario_sottotitolo', 'Le parole del sito, spiegate semplici.'),
            count($termini) . ' termini, ognuno con la sua definizione.')
        . '<input class="cerca" type="search" placeholder="Cerca un termine…" aria-label="Cerca nel glossario" id="cerca-glossario">'
        . '<nav class="lettere" aria-label="Lettere">' . $lettere . '</nav><div>' . $sezioni . '</div>'
        . '<script' . sicurezza_nonce_attr() . '>(function(){var c=document.getElementById("cerca-glossario");c&&c.addEventListener("input",function(){var q=c.value.trim().toLowerCase();document.querySelectorAll(".g-gruppo").forEach(function(g){var n=0;g.querySelectorAll("li").forEach(function(li){var ok=li.textContent.toLowerCase().indexOf(q)>-1;li.hidden=!ok;if(ok)n++});g.hidden=n===0})})})();</script>'
        . '</div>';
}

/** Ricerca /?s=parola: si cerca nel database, nessun indice da scaricare. */
function vista_cerca(string $q): string {
    $q = trim($q);
    $trovati = [];
    if (mb_strlen($q) >= 2) {
        $t = mb_strtolower($q);
        foreach (sito_contenuti() as $c) {
            if ($c['rediretto'] || ($c['tipo'] === 'page' && $c['seo']['robots'] !== 'index')) continue;
            $testo = $c['tldr'] ?: ($c['seo']['descrizione'] ?: $c['estratto']);
            if (str_contains(mb_strtolower($c['titolo'] . ' ' . $testo), $t)) $trovati[] = ['c' => $c, 'e' => mb_substr($testo, 0, 180)];
            if (count($trovati) >= 30) break;
        }
    }
    $elenco = '';
    foreach ($trovati as $x) {
        $elenco .= '<a class="card" href="' . h($x['c']['percorso']) . '"><span class="card-titolo">' . h($x['c']['titolo']) . '</span><span class="card-meta">' . h($x['e']) . '</span></a>';
    }
    if (($t = vista_tema('cerca', ['q' => $q, 'trovati' => $trovati])) !== null) return $t;
    if ($elenco === '' && mb_strlen($q) >= 2) $elenco = '<p class="vuoto">Nessun risultato per questa parola.</p>';
    return '<div class="wrap" style="padding-top:56px;padding-bottom:40px"><span class="occhiello">Cerca</span><h1 class="sez-titolo">Cosa stai cercando?</h1>'
        . '<form action="/" method="get" role="search"><input name="s" class="cerca" type="search" value="' . h($q) . '" placeholder="Scrivi una parola…" aria-label="Cerca nel sito"></form>'
        . '<div class="griglia" style="margin-top:24px">' . $elenco . '</div></div>';
}

function vista_404(): string {
    if (($t = vista_tema('404')) !== null) return $t;
    return '<div class="wrap" style="padding:96px 0"><span class="occhiello">Errore 404</span><h1 class="sez-titolo">Questa pagina non c&#039;è.</h1>'
        . '<p class="sez-intro">Può darsi che l&#039;indirizzo sia scritto male. Da qui riparti in un clic.</p>'
        . '<p><a class="btn btn-viola" href="' . h(SITO_PAGINA_BLOG) . '">Vai al blog</a></p></div>';
}

// ------------------------------------------------------------
// Home
// ------------------------------------------------------------

function vista_home(): string {
    // La home scritta nel codice, quando la pagina iniziale non ha una struttura dell'editor: dal modulo del sito,
    // altrimenti un'apertura essenziale con il nome, la frase e gli ultimi articoli.
    $html = cms_filtro('vista_home', null);
    if (is_string($html)) return $html;
    if (($t = vista_tema('home')) !== null) return $t;
    $ultimi = sito_ultimi(3);
    return '<section class="hero"><div class="wrap"><div><span class="occhiello">' . h((string)cms_impostazione('sito_sottotitolo', '')) . '</span><h1>' . h((string)cms_impostazione('sito_nome', CMS_NOME)) . '</h1>'
        . '<p class="lead">' . h((string)cms_impostazione('sito_descrizione', '')) . '</p></div></div></section>'
        . ($ultimi ? '<section class="sez"><div class="wrap"><span class="occhiello">' . h((string)cms_impostazione('blog_nome', 'Blog')) . '</span><h2 class="sez-titolo">Gli ultimi articoli</h2><div class="griglia" style="margin-top:28px">' . implode('', array_map('vista_scheda', $ultimi)) . '</div></div></section>' : '');
}

/**
 * L'icona del sito nella scheda del browser (1.4.0). Quella dello Stile del sito, se c'è; altrimenti l'icona di
 * partenza di plumeCMS (sito/assets/favicon.svg, e favicon.ico per i browser che non leggono l'SVG), servita anche a
 * /favicon.ico dal sito (sito/index.php). Con il CMS in una cartella gli indirizzi la portano (SITO_ASSET, cms_base).
 */
function vista_favicon(): string {
    $f = trim((string)design_sito()['favicon']);
    if ($f !== '') return '<link rel="icon" href="' . h($f) . '">';
    return '<link rel="icon" href="' . h(SITO_ASSET) . 'favicon.ico" sizes="48x48">' . "\n" . '<link rel="icon" href="' . h(SITO_ASSET) . 'favicon.svg" type="image/svg+xml">';
}

