<?php
/**
 * Il sito pubblico. Ogni indirizzo che non è un file passa da qui
 * (lo manda il .htaccess della radice, vedi sito-nuovo/radice/.htaccess).
 *
 * Ordine, lo stesso del sito di prima:
 *  1. indirizzi speciali con query string: /?p=ID, /?redirect_to=random, /?s=parola
 *  2. vecchi indirizzi del publisher /articoli/slug/
 *  3. redirect della tabella <prefisso>redirect (anche quelli importati da un sito di prima)
 *  4. file calcolati: robots.txt, sitemap.xml, llms.txt, feed
 *  5. la mappa degli indirizzi: articoli, pagine, archivi, glossario, blog
 *  6. indirizzo senza slash finale che esiste con lo slash: 301
 *  7. paginazione oltre l'ultima pagina: 301 alla prima; tutto il resto 404
 */

require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once __DIR__ . '/viste.php';
require_once __DIR__ . '/extra.php';
require_once dirname(__DIR__) . '/includes/moduli.php';
require_once dirname(__DIR__) . '/includes/newsletter.php';

// Anteprima dal pannello: con ?preview=1 e la sessione dell'admin, bozze e programmati si vedono come usciti.
define('SITO_ANTEPRIMA', !empty($_GET['preview']) && is_admin_preview());
if (SITO_ANTEPRIMA) header('Cache-Control: no-store');
// temi: il tema attivo (o quello in anteprima) e il suo PHP. Solo qui, mai nel pannello.
require_once dirname(__DIR__) . '/includes/temi.php';
temi_avvia_sito();

function sito_rispondi(int $codice, string $tipo, string $corpo, array $intestazioni = []): never {
    http_response_code($codice);
    header("Content-Type: $tipo; charset=UTF-8");
    // sicurezza: sulle pagine HTML anche la Content-Security-Policy (a nonce, modalita scelta nella pagina Sicurezza del pannello).
    if ($tipo === 'text/html') sicurezza_intestazioni('sito'); else sicurezza_intestazioni_base();
    foreach ($intestazioni as $h) header($h);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $corpo;
    exit;
}

function sito_vai(string $dove, int $codice = 301): never {
    if (!preg_match('~^https?://~', $dove)) $dove = SITO_URL . (str_starts_with($dove, '/') ? '' : '/') . $dove;
    http_response_code($codice);
    header('Location: ' . $dove);
    if ($codice === 307) header('Cache-Control: no-store');
    exit;
}

function sito_html(int $codice, array $meta, string $corpo, string $jsonld = '', string $attivo = ''): never {
    // Il pannello SEO e AEO aggiunge i suoi tag (verifica, statistiche, codice libero) al documento finito.
    // {versione:<serie>} nei testi (titolo della scheda compreso) diventa l'ultima versione caricata in Download (1.2.5).
    $html = seo_inietta_html(vista_pagina($meta, $corpo, $jsonld, $attivo));
    if (str_contains($html, '{versione:')) { require_once dirname(__DIR__) . '/includes/scaricabili.php'; $html = scaricabili_versioni_nel_testo($html); }
    $html = cms_con_cartella($html);
    // 1.4.0: le foto con la loro WebP accanto si servono in WebP a chi la accetta; la pagina dipende allora da Accept.
    require_once dirname(__DIR__) . '/includes/foto.php';
    $vary = [];
    if (foto_impostazioni()['attivo']) { $vary = ['Vary: Accept']; if (foto_browser_webp()) $html = foto_webp_nel_html($html); }
    sito_rispondi($codice, 'text/html', $html, $vary);
}

function sito_non_trovato(): never {
    seo_registra_404($GLOBALS['sito_percorso_404'] ?? '');
    sito_html(404, ['titolo' => seo_titolo('Pagina non trovata', 'altro'), 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []], vista_404());
}

/** Il redirect che vale per questo indirizzo, con le stesse regole di confronto di Rank Math. */
function sito_redirect_per(string $percorso, string $query): ?array {
    $qui = $percorso . ($query !== '' ? "?$query" : '');
    $senza = fn(string $s) => trim($s, '/');
    foreach (sito_redirect() as $r) {
        $da = $r['da'];
        $ok = match ($r['confronto']) {
            // Rank Math accetta l'indirizzo con e senza slash finale.
            'exact' => $qui === $da || $senza($percorso) . ($query !== '' ? "?$query" : '') === $senza($da),
            'contains' => str_contains($qui, $da),
            'start' => str_starts_with($senza($qui), $senza($da)),
            'end' => str_ends_with($senza($qui), $senza($da)),
            'regex' => @preg_match('~' . str_replace('~', '\~', $da) . '~', $senza($qui)) === 1,
            default => false,
        };
        if ($ok) return $r;
    }
    return null;
}

/**
 * /favicon.ico (1.4.0): se nella cartella c'è un favicon.ico vero lo serve il server web e qui non si arriva. Altrimenti
 * l'icona dello Stile del sito (302, perché dipende dalle impostazioni), o quella di partenza di plumeCMS.
 */
function sito_favicon(): never {
    $f = trim((string)design_sito()['favicon']);
    if ($f !== '') {
        header('Cache-Control: no-store');
        sito_vai(preg_match('~^https?://~', $f) ? $f : cms_origine_o_sito() . $f, 302);
    }
    $file = __DIR__ . '/assets/favicon.ico';
    // Non con sito_rispondi: un'immagine non ha il charset.
    header('Content-Type: image/x-icon');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($file));
    sicurezza_intestazioni_base();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($file);
    exit;
}

/** Lo schema e l'host del sito, per un indirizzo che comincia con "/". */
function cms_origine_o_sito(): string {
    return (string)preg_replace('~^(https?://[^/]+).*$~', '$1', SITO_URL);
}

// ------------------------------------------------------------

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$percorso = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
// Sito in una cartella (https://esempio.it/test): da qui in poi i percorsi sono quelli dalla radice del sito ("/blog/").
if (($cartella = cms_sito_base()) !== '' && ($percorso === $cartella || str_starts_with($percorso, $cartella . '/'))) {
    $percorso = substr($percorso, strlen($cartella)) ?: '/';
}
$query = (string)(parse_url($uri, PHP_URL_QUERY) ?? '');
parse_str($query, $q);
$GLOBALS['sito_percorso_404'] = $percorso;

try {
    // I moduli rispondono prima di tutto a una query loro (per esempio /?a_caso=1 per un contenuto a caso).
    if ($query !== '') cms_evento('sito_query', $q, $percorso);
    // 1. Query string sulla home
    if ($percorso === '/' && $query !== '') {
        // /?p=1658 e /?page_id=12: gli indirizzi corti di prima portano all'indirizzo vero con un 301.
        $id = $q['p'] ?? $q['page_id'] ?? null;
        if (is_string($id) && ctype_digit($id)) {
            foreach (sito_contenuti() as $c) {
                if ($c['id'] !== (int)$id) continue;
                $r = $c['rediretto'] ? sito_redirect_per($c['percorso'], '') : null;
                sito_vai($r['a'] ?? $c['percorso']);
            }
        }
        // Ricerca: /?s=parola resta 200.
        if (isset($q['s'])) {
            sito_html(200, ['titolo' => seo_titolo('Cerca', 'altro'), 'descrizione' => '', 'robots' => seo_robots_tipo('cerca', 'noindex'), 'canonical' => '', 'og' => []], vista_cerca(is_string($q['s']) ? $q['s'] : ''));
        }
    }

    // 3. Redirect
    if ($r = sito_redirect_per($percorso, $query)) sito_vai($r['a'], $r['codice'] ?: 301);
    if ($percorso === '/wp-sitemap.xml') sito_vai('/sitemap.xml');

    // 3b. Gli indirizzi dei moduli del sito (conferme, download, pagine di cortesia): il modulo risponde e chiude.
    cms_evento('sito_percorso', $percorso, $q);
    // Le pagine di cortesia della newsletter.
    $cortesia = ['titolo' => '', 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []];
    if ($percorso === NEWSLETTER_GRAZIE) sito_html(200, ['titolo' => 'Conferma la tua email - ' . SITO_NOME] + $cortesia, vista_newsletter_grazie());
    if ($percorso === NEWSLETTER_CONFERMA) {
        sito_html(200, ['titolo' => 'Iscrizione confermata - ' . SITO_NOME] + $cortesia, vista_newsletter_confermata(newsletter_conferma((string)($q['t'] ?? ''))));
    }
    if ($percorso === NEWSLETTER_ESCI) {
        $i = (string)($q['i'] ?? ''); $t = (string)($q['t'] ?? '');
        $iscritto = newsletter_iscritto_da_uscita($i, $t);
        // POST: il pulsante della pagina, oppure l'uscita in un clic dei programmi di posta (List-Unsubscribe-Post).
        $fatto = $iscritto && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        if ($fatto) newsletter_esci((int)$iscritto['id']);
        sito_html(200, ['titolo' => 'Uscita dalla newsletter - ' . SITO_NOME] + $cortesia, vista_newsletter_esci($iscritto, $fatto, $i, $t));
    }

    // Pagina di ringraziamento dei form costruiti dal pannello.
    if ($percorso === '/grazie/' && !empty($_GET['f'])) {
        require_once dirname(__DIR__) . '/includes/form.php';
        $f = form_leggi((int)$_GET['f']);
        if ($f) sito_html(200, ['titolo' => 'Ricevuto - ' . SITO_NOME] + $cortesia, form_grazie_html($f));
    }

    // File da scaricare (1.2.3): /scarica/<nome>/ manda uploads/scaricabili/<nome>.zip o .pdf; se non c'è, 404 come le altre pagine.
    if (preg_match('~^/scarica/([a-z0-9][a-z0-9._-]{0,120})/?$~', $percorso, $sc) && ($file = sito_scaricabile($sc[1]))) {
        sito_manda_scaricabile($file);
        exit;
    }

    // 4. File calcolati. robots.txt, sitemap e llms.txt passano dal pannello SEO e AEO (con i predefiniti sono quelli di sempre).
    switch ($percorso) {
        case '/robots.txt': sito_rispondi(200, 'text/plain', seo_robots_txt());
        case '/sitemap.xml': sito_rispondi(200, 'application/xml', seo_sitemap());
        case '/llms.txt': sito_rispondi(200, 'text/plain', seo_llms());
        case '/favicon.ico': sito_favicon();
    }
    // File di verifica dei motori, chiave IndexNow, llms-full.txt, sitemap divise.
    if ($speciale = seo_file_speciale($percorso)) sito_rispondi(200, $speciale[0], $speciale[1]);
    if (str_ends_with($percorso, '/feed/')) {
        $feed = extra_feed($percorso);
        if ($feed !== null) sito_rispondi(200, 'application/rss+xml', $feed);
        sito_non_trovato();
    }

    // 5. La mappa
    $mappa = sito_mappa();
    $v = $mappa[$percorso] ?? null;
    if ($v) {
        $meta = sito_meta($percorso, $v);
        // Per i popup: blog, glossario e archivi sono "altro"; home, articoli e pagine lo precisano sotto.
        $GLOBALS['sito_percorso'] = $percorso; $GLOBALS['sito_genere'] = 'altro';
        switch ($v['genere']) {
            case 'home':
                $GLOBALS['sito_percorso'] = '/'; $GLOBALS['sito_genere'] = 'home';
                $nodi = [sito_jsonld_pagina('/', $v['c']['titolo'], (string)cms_impostazione('home_descrizione', ''), sito_jsonld_about())];
                if (!empty($v['c']['editor'])) {
                    $c = $v['c'];
                    [$corpo, $testa] = vista_pagina_editor($c);
                    if ($c['editor_disegno'][2]) $nodi[] = sito_jsonld_faq(['faq' => $c['editor_disegno'][2]] + $c);
                    sito_html(200, $meta, $corpo, sito_jsonld_script($nodi) . $testa);
                }
                sito_html(200, $meta, vista_home(), sito_jsonld_script($nodi));
            case 'contenuto':
                $c = $v['c'];
                $GLOBALS['sito_percorso'] = $c['percorso']; $GLOBALS['sito_genere'] = $c['tipo'] === 'post' ? 'post' : ($c['tipo'] === 'page' ? 'page' : 'altro');
                if ($c['tipo'] === 'post') {
                    $cat = $c['categorie'][0] ?? null;
                    $briciole = [['Home', '/'], ['Blog', SITO_PAGINA_BLOG]];
                    if ($cat) $briciole[] = [sito_categorie()[$cat]['name'] ?? $cat, "/category/$cat/"];
                    $briciole[] = [$c['titolo'], $c['percorso']];
                    $testa = '';
                    if (!empty($c['editor'])) {
                        [$corpo, $testa] = vista_articolo_editor($c);
                        $c['faq'] = array_merge($c['faq'], $c['editor_disegno'][2]);
                    } else {
                        $corpo = vista_articolo($c);
                    }
                    // 1.6.0: il riquadro "Aggiungi sempre alla fine", se è acceso.
                    [$corpo, $cssFine] = vista_fine_articolo($corpo);
                    $testa .= $cssFine;
                    $nodi = array_merge(sito_jsonld_articolo($c), [sito_jsonld_briciole($briciole)]);
                    sito_html(200, $meta, $corpo, sito_jsonld_script($nodi) . $testa, SITO_PAGINA_BLOG);
                }
                if ($c['tipo'] === 'glossario') {
                    $nodi = [sito_jsonld_pagina($c['percorso'], $c['titolo'], $c['tldr']), sito_jsonld_termine($c),
                        sito_jsonld_briciole([['Home', '/'], [(string)cms_impostazione('glossario_titolo', 'Glossario'), '/glossario/'], [$c['titolo'], $c['percorso']]])];
                    sito_html(200, $meta, vista_termine($c), sito_jsonld_script($nodi), '/glossario/');
                }
                // Quello che arriva dal pannello vince sui valori di partenza.
                $c['servizio'] += sito_servizi_predefiniti()[$c['percorso']] ?? [];
                $nodi = [sito_jsonld_pagina($c['percorso'], $c['titolo'], $c['seo']['descrizione'])];
                if (!empty($c['servizio']['tipo'])) $nodi[] = sito_jsonld_servizio($c);
                if (!empty($c['editor'])) {
                    [$corpo, $testa] = vista_pagina_editor($c);
                    $faq = array_merge($c['faq'], $c['editor_disegno'][2]);
                    if ($faq) $nodi[] = sito_jsonld_faq(['faq' => $faq] + $c);
                    sito_html(200, $meta, $corpo, sito_jsonld_script($nodi) . $testa, $c['percorso']);
                }
                if ($c['faq']) $nodi[] = sito_jsonld_faq($c);
                sito_html(200, $meta, vista_pagina_generica($c), sito_jsonld_script($nodi), $c['percorso']);
            case 'blog':
                sito_html(200, $meta, vista_blog($v), sito_jsonld_script([sito_jsonld_pagina(SITO_PAGINA_BLOG, (string)cms_impostazione('blog_nome', 'Blog'), '')]), SITO_PAGINA_BLOG);
            case 'glossario':
                sito_html(200, $meta, vista_glossario($v['termini']),
                    sito_jsonld_script([sito_jsonld_pagina('/glossario/', (string)cms_impostazione('glossario_titolo', 'Glossario'), ''), sito_jsonld_glossario($v['termini'])]), '/glossario/');
            case 'archivio':
                sito_html(200, $meta, vista_archivio($v));
            default:
                // Un genere di un modulo (per esempio "test"): il modulo risponde e chiude.
                cms_evento('sito_genere', $v, $meta);
        }
    }

    // 6. Slash finale
    if (!str_ends_with($percorso, '/') && isset($mappa[$percorso . '/'])) sito_vai($percorso . '/' . ($query !== '' ? "?$query" : ''));

    // 7. Paginazione oltre l'ultima pagina (archivi che contavano articoli assorbiti dai pillar)
    if (preg_match('~^(/(?:category|tag)/[^/]+/|' . preg_quote(SITO_PAGINA_BLOG, '~') . ')page/\d+/$~', $percorso, $m) && isset($mappa[$m[1]])) sito_vai($m[1]);

    sito_non_trovato();
} catch (PDOException $e) {
    // Database fermo: una pagina onesta, e i motori di ricerca riprovano più tardi.
    error_log('sito: ' . $e->getMessage());
    sito_rispondi(503, 'text/html', '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><title>Torno subito</title><body style="font-family:system-ui;background:#060a17;color:#e6ebf5;padding:15vh 24px;text-align:center"><h1>Torno subito.</h1><p>Il sito si è fermato un momento. Riprova tra qualche minuto.</p></body></html>', ['Retry-After: 600']);
}
