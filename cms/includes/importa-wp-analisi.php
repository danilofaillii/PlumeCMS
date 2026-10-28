<?php
/**
 * Import da WordPress (1.4.0): l'analisi. Legge tutto il sito con la REST, a passi brevi (un passo = una richiesta), e
 * scrive nello stato l'indice di ogni cosa (senza i testi, che si rileggono durante l'import) e il report.
 *
 * Passi: categorie, tag, utenti, pagine, articoli, media, menu, il resto (commenti, tipi personalizzati, impostazioni),
 * poi il report. Gli articoli e le pagine si leggono a 50 per richiesta con context=edit (vede bozze, programmati,
 * in attesa di revisione e privati; il testo grezzo serve a riconoscere shortcode e page builder).
 */

require_once __DIR__ . '/importa-wp.php';

const IMPORTA_WP_PASSI = ['categorie', 'tag', 'utenti', 'pagine', 'articoli', 'media', 'menu', 'altro'];
const IMPORTA_WP_STATI = 'publish,future,draft,pending,private';
/** Shortcode di WordPress stesso: diventano HTML da soli, non sono "di un plugin". */
const IMPORTA_WP_SHORTCODE_NUCLEO = ['caption', 'wp_caption', 'gallery', 'audio', 'video', 'playlist', 'embed'];
/** I tipi di file che entrano nella libreria (dal contenuto vero, non dal nome). */
const IMPORTA_WP_MIME_OK = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml', 'application/pdf', 'video/mp4', 'audio/mpeg'];

/**
 * Un passo dell'analisi. Ritorna vero quando l'analisi e finita (lo stato passa a "pronto" con il report).
 */
function importa_wp_analisi_passo(array &$s): bool {
    $a = &$s['analisi'];
    $passo = IMPORTA_WP_PASSI[(int)$a['passo']] ?? null;
    if ($passo === null) {
        $s['report'] = importa_wp_report($s);
        $s['fase'] = 'pronto';
        return true;
    }
    $pagina = (int)($a['pagina'] ?? 1);
    $avanti = function () use (&$a) { $a['passo']++; $a['pagina'] = 1; };
    switch ($passo) {
        case 'categorie':
        case 'tag':
            $s['indice'][$passo] = [];
            foreach (importa_wp_tutti($s, $passo === 'tag' ? '/wp/v2/tags' : '/wp/v2/categories', ['_fields' => 'id,name,slug,parent,count,link,description']) as $t) {
                $s['indice'][$passo][(string)$t['id']] = ['id' => (int)$t['id'], 'nome' => importa_wp_testo((string)$t['name']), 'slug' => (string)$t['slug'], 'padre' => (int)($t['parent'] ?? 0),
                    'conta' => (int)($t['count'] ?? 0), 'percorso' => importa_wp_percorso($s, (string)($t['link'] ?? '')), 'descrizione' => importa_wp_testo((string)($t['description'] ?? ''))];
            }
            $avanti();
            break;
        case 'utenti':
            $s['indice']['autori'] = [];
            try { $u = importa_wp_tutti($s, '/wp/v2/users', ['context' => 'edit', '_fields' => 'id,name']); }
            catch (ImportaWpErrore $e) { $u = importa_wp_tutti($s, '/wp/v2/users', ['_fields' => 'id,name']); }
            foreach ($u as $x) $s['indice']['autori'][(string)$x['id']] = importa_wp_testo((string)$x['name']);
            $avanti();
            break;
        case 'pagine':
        case 'articoli':
            if ($pagina === 1) $s['indice'][$passo] = [];
            $r = importa_wp_json($s, $passo === 'pagine' ? '/wp/v2/pages' : '/wp/v2/posts', ['context' => 'edit', 'status' => IMPORTA_WP_STATI, 'per_page' => 50, 'page' => $pagina,
                'orderby' => 'id', 'order' => 'asc', '_fields' => 'id,type,slug,generated_slug,title,status,date,date_gmt,link,parent,author,categories,tags,featured_media,content,excerpt,yoast_head_json,meta,acf,template']);
            foreach ($r['json'] as $x) if (is_array($x)) $s['indice'][$passo][] = importa_wp_voce_indice($s, $x, $passo);
            $a['totale_pagine'] = (int)($r['header']['x-wp-totalpages'] ?? 1);
            if ($pagina >= $a['totale_pagine']) $avanti(); else $a['pagina'] = $pagina + 1;
            break;
        case 'media':
            if ($pagina === 1) $s['indice']['media'] = [];
            $r = importa_wp_json($s, '/wp/v2/media', ['per_page' => 100, 'page' => $pagina, 'orderby' => 'id', 'order' => 'asc', '_fields' => 'id,source_url,mime_type,media_type,media_details,alt_text']);
            foreach ($r['json'] as $x) {
                if (!is_array($x)) continue;
                $s['indice']['media'][] = ['id' => (int)$x['id'], 'url' => (string)($x['source_url'] ?? ''), 'mime' => (string)($x['mime_type'] ?? ''), 'immagine' => ($x['media_type'] ?? '') === 'image',
                    'peso' => (int)($x['media_details']['filesize'] ?? 0), 'alt' => importa_wp_testo((string)($x['alt_text'] ?? ''))];
            }
            $a['totale_pagine'] = (int)($r['header']['x-wp-totalpages'] ?? 1);
            if ($pagina >= $a['totale_pagine']) $avanti(); else $a['pagina'] = $pagina + 1;
            break;
        case 'menu':
            $s['indice']['menu'] = importa_wp_leggi_menu($s);
            $avanti();
            break;
        case 'altro':
            $s['indice']['altro'] = importa_wp_leggi_altro($s);
            $avanti();
            break;
    }
    return false;
}

/** A che punto e l'analisi, per la barra: [fatti, totale, testo]. */
function importa_wp_analisi_avanzamento(array $s): array {
    $a = $s['analisi'] ?? ['passo' => 0];
    $n = count(IMPORTA_WP_PASSI);
    $i = min((int)$a['passo'], $n);
    $nomi = ['categorie' => 'Leggo le categorie', 'tag' => 'Leggo i tag', 'utenti' => 'Leggo gli autori', 'pagine' => 'Leggo le pagine', 'articoli' => 'Leggo gli articoli',
        'media' => 'Leggo la libreria dei media', 'menu' => 'Leggo i menu', 'altro' => 'Commenti, tipi personalizzati, impostazioni'];
    $testo = $nomi[IMPORTA_WP_PASSI[$i] ?? ''] ?? 'Scrivo il report';
    if (in_array(IMPORTA_WP_PASSI[$i] ?? '', ['pagine', 'articoli', 'media'], true) && (int)($a['pagina'] ?? 1) > 1) $testo .= ' (pagina ' . (int)$a['pagina'] . ' di ' . (int)($a['totale_pagine'] ?? 1) . ')';
    return [$i * 10 + (int)min(9, ((int)($a['pagina'] ?? 1) - 1) * 10 / max(1, (int)($a['totale_pagine'] ?? 1))), $n * 10 + 1, $testo];
}

/** Una voce dell'indice: quello che serve per il report, per la scelta e per i link nuovi (il testo no). */
function importa_wp_voce_indice(array $s, array $x, string $passo): array {
    $raw = (string)($x['content']['raw'] ?? '');
    $html = (string)($x['content']['rendered'] ?? '');
    $note = [];
    // Page builder: il testo e "a pezzi" fatti dal plugin; entra il testo, la grafica no.
    $builder = match (true) {
        str_contains($html, 'class="elementor') || str_contains($raw, 'elementor-') => 'Elementor',
        str_contains($raw, '[et_pb_') => 'Divi',
        str_contains($raw, '[vc_row') || str_contains($raw, '[vc_column') => 'WPBakery',
        str_contains($html, 'fl-builder-content') => 'Beaver Builder',
        str_contains($raw, '[fusion_') => 'Avada',
        str_contains($html, 'brz-') && str_contains($html, 'brz-root') => 'Brizy',
        str_contains($raw, '[ct_') || str_contains($html, 'ct-section') => 'Oxygen',
        str_contains($html, 'panel-layout') => 'SiteOrigin',
        default => '',
    };
    if ($builder !== '') $note['builder'] = $builder;
    if (preg_match_all('/\[([a-zA-Z][a-zA-Z0-9_-]*)[\s\]\/]/', $raw, $m)) {
        $sc = array_values(array_unique(array_filter($m[1], fn($n) => !in_array(strtolower($n), IMPORTA_WP_SHORTCODE_NUCLEO, true) && !preg_match('/^(et_pb|vc_|fusion_|ct_)/', $n))));
        if ($sc) $note['shortcode'] = array_slice($sc, 0, 10);
    }
    $embed = substr_count($html, 'wp-block-embed') ? preg_match_all('/<figure[^>]*wp-block-embed/', $html) : 0;
    if ($embed) $note['embed'] = $embed;
    if (preg_match_all('/<img\s[^>]*src="([^"]+)"/i', $html, $m)) {
        $note['immagini'] = count($m[1]);
        $fuori = count(array_filter($m[1], fn($u) => preg_match('~^https?://~', $u) && !importa_wp_stesso_sito($u, $s['base']) && !importa_wp_stesso_sito(str_replace('http://', 'https://', $u), $s['base'])));
        if ($fuori) $note['immagini_fuori'] = $fuori;
    }
    if (preg_match('/<(form|script|iframe)\b/i', $html, $m)) $note['codice'] = strtolower($m[1]);
    if (!empty($x['acf']) || (is_array($x['meta'] ?? null) && array_filter($x['meta'], fn($v, $k) => $k !== 'footnotes' && $v !== '' && $v !== [] && $v !== false, ARRAY_FILTER_USE_BOTH))) $note['campi'] = 1;
    $titolo = importa_wp_testo((string)($x['title']['raw'] ?? $x['title']['rendered'] ?? ''));
    [$seoT, $seoD] = importa_wp_seo($s, $x, $titolo);
    // Lo slug di WordPress puo avere caratteri codificati (%f0%9f... per un'emoji): si decodifica e i caratteri a 4 byte si tolgono.
    $slug = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', rawurldecode((string)($x['slug'] ?: ($x['generated_slug'] ?? ''))));
    $slug = trim($slug, '-');
    if ($slug === '') $slug = contenuti_slug($titolo);
    return [
        'id' => (int)$x['id'], 'tipo' => $passo === 'pagine' ? 'pagina' : 'articolo', 'slug' => $slug, 'titolo' => $titolo !== '' ? $titolo : '(senza titolo)',
        'stato' => (string)$x['status'], 'data' => (string)($x['date'] ?? ''), 'data_gmt' => (string)($x['date_gmt'] ?? ''),
        'percorso' => importa_wp_percorso($s, (string)($x['link'] ?? '')), 'padre' => (int)($x['parent'] ?? 0), 'autore' => (int)($x['author'] ?? 0),
        'cat' => array_map('intval', (array)($x['categories'] ?? [])), 'tag' => array_map('intval', (array)($x['tags'] ?? [])),
        'evidenza' => (int)($x['featured_media'] ?? 0), 'note' => $note,
        'seo' => $seoT !== '', 'seo_desc' => $seoD !== '',
    ];
}

/** I menu: quelli classici (wp/v2/menus, da WordPress 5.9) e quelli dei temi a blocchi (wp/v2/navigation). */
function importa_wp_leggi_menu(array $s): array {
    $out = ['menu' => [], 'nota' => ''];
    $posizioni = [];
    try {
        foreach ((importa_wp_json($s, '/wp/v2/menu-locations')['json']) as $k => $l) if (!empty($l['menu'])) $posizioni[(int)$l['menu']][] = (string)($l['description'] ?? $k);
    } catch (ImportaWpErrore $e) {}
    try {
        foreach (importa_wp_tutti($s, '/wp/v2/menus', ['_fields' => 'id,name,locations']) as $m) {
            $voci = [];
            foreach (importa_wp_tutti($s, '/wp/v2/menu-items', ['menus' => (int)$m['id'], '_fields' => 'id,title,url,type,object,object_id,parent,menu_order,target']) as $v) {
                $voci[] = ['id' => (int)$v['id'], 'titolo' => importa_wp_testo((string)($v['title']['rendered'] ?? $v['title'] ?? '')), 'url' => (string)($v['url'] ?? ''), 'tipo' => (string)($v['type'] ?? ''),
                    'oggetto' => (string)($v['object'] ?? ''), 'oggetto_id' => (int)($v['object_id'] ?? 0), 'padre' => (int)($v['parent'] ?? 0), 'ordine' => (int)($v['menu_order'] ?? 0), 'nuova' => ($v['target'] ?? '') === '_blank'];
            }
            usort($voci, fn($a, $b) => $a['ordine'] <=> $b['ordine']);
            $out['menu'][] = ['id' => (int)$m['id'], 'nome' => importa_wp_testo((string)$m['name']), 'posizioni' => $posizioni[(int)$m['id']] ?? array_values((array)($m['locations'] ?? [])), 'voci' => $voci, 'fonte' => 'menu'];
        }
    } catch (ImportaWpErrore $e) {
        $out['nota'] = $e->tipo === 'manca' ? 'WordPress più vecchio della 5.9: i menu classici non si leggono dalla REST.' : 'I menu classici non si leggono: ' . $e->getMessage();
    }
    // Temi a blocchi: i menu stanno nei blocchi "navigation".
    try {
        foreach (importa_wp_tutti($s, '/wp/v2/navigation', ['context' => 'edit', 'status' => 'publish', '_fields' => 'id,title,content']) as $n) {
            $voci = importa_wp_navigation_voci((string)($n['content']['raw'] ?? ''));
            if ($voci) $out['menu'][] = ['id' => (int)$n['id'], 'nome' => importa_wp_testo((string)($n['title']['raw'] ?? $n['title']['rendered'] ?? 'Navigazione')), 'posizioni' => [], 'voci' => $voci, 'fonte' => 'navigation'];
        }
    } catch (ImportaWpErrore $e) {}
    return $out;
}

/** Le voci di un blocco navigation: navigation-link e navigation-submenu, con il padre, nello stesso formato dei menu classici. */
function importa_wp_navigation_voci(string $raw): array {
    $voci = []; $pila = [0]; $n = 0;
    if (!preg_match_all('~<!--\s+(/?)wp:(navigation-link|navigation-submenu|page-list)(\s+(\{.*?\}))?\s*(/?)-->~s', $raw, $m, PREG_SET_ORDER)) return [];
    foreach ($m as $b) {
        if ($b[1] === '/') { if ($b[2] === 'navigation-submenu' && count($pila) > 1) array_pop($pila); continue; }
        if ($b[2] === 'page-list') continue;
        $j = json_decode($b[4] ?? '{}', true) ?: [];
        $id = 900000 + (++$n);
        $tipo = ($j['kind'] ?? '') === 'taxonomy' ? 'taxonomy' : (($j['kind'] ?? '') === 'post-type' ? 'post_type' : 'custom');
        $voci[] = ['id' => $id, 'titolo' => importa_wp_testo((string)($j['label'] ?? '')), 'url' => (string)($j['url'] ?? ''), 'tipo' => $tipo, 'oggetto' => (string)($j['type'] ?? 'custom'),
            'oggetto_id' => (int)($j['id'] ?? 0), 'padre' => end($pila), 'ordine' => $n, 'nuova' => !empty($j['opensInNewTab'])];
        if ($b[2] === 'navigation-submenu' && $b[5] !== '/') $pila[] = $id;
    }
    return $voci;
}

/** Commenti, tipi di contenuto personalizzati, impostazioni di lettura, plugin SEO. */
function importa_wp_leggi_altro(array $s): array {
    $out = ['commenti' => 0, 'tipi' => [], 'impostazioni' => [], 'seo' => ''];
    try { $r = importa_wp_json($s, '/wp/v2/comments', ['per_page' => 1, '_fields' => 'id']); $out['commenti'] = (int)($r['header']['x-wp-total'] ?? count($r['json'])); } catch (ImportaWpErrore $e) {}
    try {
        $r = importa_wp_json($s, '/wp/v2/types', ['context' => 'edit']);
        $noti = ['post', 'page', 'attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face'];
        $n = 0;
        foreach ($r['json'] as $k => $t) {
            if (in_array($k, $noti, true) || empty($t['rest_base']) || ++$n > 12) continue;
            $conta = 0;
            try { $c = importa_wp_json($s, '/' . ($t['rest_namespace'] ?? 'wp/v2') . '/' . $t['rest_base'], ['per_page' => 1, '_fields' => 'id']); $conta = (int)($c['header']['x-wp-total'] ?? 0); } catch (ImportaWpErrore $e) {}
            if ($conta) $out['tipi'][] = ['nome' => importa_wp_testo((string)($t['name'] ?? $k)), 'chiave' => (string)$k, 'quanti' => $conta];
        }
    } catch (ImportaWpErrore $e) {}
    try {
        $r = importa_wp_json($s, '/wp/v2/settings');
        foreach (['show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page'] as $k) $out['impostazioni'][$k] = $r['json'][$k] ?? null;
    } catch (ImportaWpErrore $e) {}
    $ns = (array)($s['sito']['namespaces'] ?? []);
    $out['seo'] = in_array('yoast/v1', $ns, true) ? 'Yoast SEO' : (in_array('rankmath/v1', $ns, true) ? 'Rank Math' : '');
    return $out;
}

/** La struttura dei permalink, ricavata dall'indirizzo di un articolo pubblicato. */
function importa_wp_permalink(array $s): string {
    foreach ($s['indice']['articoli'] ?? [] as $x) {
        if ($x['stato'] !== 'publish') continue;
        $p = $x['percorso'];
        if (str_starts_with($p, '/?')) return '/?p=123 (semplice)';
        $d = $x['data'];
        $p = str_replace('/' . $x['slug'] . '/', '/%postname%/', rtrim($p, '/') . '/');
        $p = preg_replace(['~^/' . substr($d, 0, 4) . '/~', '~/' . substr($d, 5, 2) . '/~', '~/' . substr($d, 8, 2) . '/~', '~/' . $x['id'] . '/~'], ['/%year%/', '/%monthnum%/', '/%day%/', '/%post_id%/'], $p, 1);
        foreach ($x['cat'] as $c) { $cs = $s['indice']['categorie'][(string)$c]['slug'] ?? ''; if ($cs !== '') $p = str_replace('/' . $cs . '/', '/%category%/', $p); }
        return $p;
    }
    return '';
}

/**
 * Titolo e descrizione SEO di un contenuto: dai meta di Yoast o Rank Math se la REST li mostra, altrimenti da
 * yoast_head_json (il campo che Yoast aggiunge alla REST). Il nome del sito in coda si toglie (plumeCMS lo aggiunge da se);
 * un titolo uguale a quello del contenuto non serve e resta vuoto. Ritorna [titolo, descrizione].
 */
function importa_wp_seo(array $s, array $x, string $titolo): array {
    $meta = is_array($x['meta'] ?? null) ? $x['meta'] : [];
    $y = is_array($x['yoast_head_json'] ?? null) ? $x['yoast_head_json'] : [];
    $t = (string)($meta['_yoast_wpseo_title'] ?? $meta['rank_math_title'] ?? '');
    $d = (string)($meta['_yoast_wpseo_metadesc'] ?? $meta['rank_math_description'] ?? '');
    if ($t === '') $t = (string)($y['title'] ?? '');
    if ($d === '') $d = (string)($y['description'] ?? '');
    $sito = (string)($s['sito']['nome'] ?? '');
    $t = str_replace(['%%title%%', '%%sitename%%', '%%sep%%', '%title%', '%sitename%', '%sep%'], [$titolo, $sito, '-', $titolo, $sito, '-'], $t);
    $t = importa_wp_testo(preg_replace('/%%?[a-z_]+%%?/i', '', $t) ?? '');
    if ($sito !== '') $t = (string)preg_replace('/\s*[-|:\x{2013}\x{2014}\x{00B7}\x{2022}]\s*' . preg_quote($sito, '/') . '\s*$/u', '', $t);
    $t = trim((string)preg_replace('/\s+/', ' ', $t));
    // WordPress scrive gli apostrofi "tipografici" nei titoli disegnati: per il confronto valgono come quelli dritti.
    $norm = fn($x) => strtr($x, ["\u{2019}" => "'", "\u{2018}" => "'", "\u{201C}" => '"', "\u{201D}" => '"', "\u{2013}" => '-', "\u{2014}" => '-']);
    if ($norm($t) === $norm($titolo)) $t = '';
    $d = importa_wp_testo((string)preg_replace('/%%?[a-z_]+%%?/i', '', $d));
    return [mb_substr($t, 0, 255), mb_substr($d, 0, 500)];
}

/** Lo slug e gia occupato in plumeCMS (un contenuto, anche in bozza o nel cestino, o un indirizzo del sito)? Ritorna il motivo o ''. */
function importa_wp_slug_occupato(string $slug): string {
    static $occupati = null;
    if ($occupati === null) {
        $occupati = [];
        foreach (['articles' => 'un articolo', 'pages' => 'una pagina'] as $t => $nome) foreach (db()->query('SELECT slug FROM ' . tbl($t))->fetchAll(PDO::FETCH_COLUMN) as $x) $occupati[(string)$x] = $nome;
        foreach (['blog', 'category', 'tag', 'feed', 'page', 'scarica', 'admin', 'api', 'assets', 'uploads', 'temi', 'moduli', 'install', 'includes', 'sito', 'logs', 'aggiornamenti', 'grazie', 'wp-admin', 'wp-content', 'wp-json'] as $r) $occupati[$r] ??= 'un indirizzo del sito';
        $blog = trim((string)(defined('SITO_PAGINA_BLOG') ? SITO_PAGINA_BLOG : ''), '/');
        if ($blog !== '') $occupati[$blog] = 'la pagina del blog';
    }
    if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) return '';
    return isset($occupati[$slug]) ? 'In plumeCMS c\'è già ' . $occupati[$slug] . ' con l\'indirizzo /' . $slug . '/' : '';
}

/**
 * Il report: per ogni voce, i numeri, cosa diventa in plumeCMS e cosa non si porta. Scritto per chi non e tecnico.
 */
function importa_wp_report(array $s): array {
    $i = $s['indice'];
    $conta = fn(array $l, string $st) => count(array_filter($l, fn($x) => $x['stato'] === $st));
    $art = $i['articoli'] ?? []; $pag = $i['pagine'] ?? []; $media = $i['media'] ?? [];
    $note = ['builder' => [], 'shortcode' => [], 'embed' => 0, 'campi' => 0, 'codice' => 0, 'immagini_fuori' => 0];
    $conflitti = [];
    $visti = [];
    foreach (array_merge($pag, $art) as $x) {
        foreach ($x['note'] as $k => $v) {
            if ($k === 'builder') $note['builder'][$v][] = $x['titolo'];
            elseif ($k === 'shortcode') foreach ($v as $sc) $note['shortcode'][$sc] = ($note['shortcode'][$sc] ?? 0) + 1;
            elseif (in_array($k, ['embed', 'immagini_fuori'], true)) $note[$k] += (int)$v;
            elseif (in_array($k, ['campi', 'codice'], true)) $note[$k]++;
        }
        $motivo = importa_wp_slug_occupato($x['slug']);
        if ($motivo === '' && isset($visti[$x['slug']])) $motivo = 'Su WordPress ci sono due contenuti con l\'indirizzo /' . $x['slug'] . '/: in plumeCMS articoli e pagine stanno insieme, entra solo il primo';
        if ($motivo !== '') $conflitti[] = ['tipo' => $x['tipo'], 'titolo' => $x['titolo'], 'slug' => $x['slug'], 'motivo' => $motivo];
        $visti[$x['slug']] = true;
    }
    $ammessi = array_filter($media, fn($m) => in_array($m['mime'], IMPORTA_WP_MIME_OK, true));
    $fuori = array_filter($media, fn($m) => !importa_wp_stesso_sito($m['url'], $s['base']) && !importa_wp_stesso_sito(preg_replace('~^http://~', 'https://', $m['url']), $s['base']));
    $nonAmmessi = [];
    foreach ($media as $m) if (!in_array($m['mime'], IMPORTA_WP_MIME_OK, true)) { $e = strtolower(pathinfo((string)parse_url($m['url'], PHP_URL_PATH), PATHINFO_EXTENSION)) ?: $m['mime']; $nonAmmessi[$e] = ($nonAmmessi[$e] ?? 0) + 1; }
    $peso = array_sum(array_column($media, 'peso'));
    $senzaPeso = count(array_filter($media, fn($m) => !$m['peso']));
    $cat = $i['categorie'] ?? []; $tag = $i['tag'] ?? [];
    $catEsistenti = db()->query('SELECT slug FROM ' . tbl('categories'))->fetchAll(PDO::FETCH_COLUMN);
    $tagEsistenti = db()->query('SELECT slug FROM ' . tbl('tags'))->fetchAll(PDO::FETCH_COLUMN);
    $annidate = count(array_filter($cat, fn($c) => $c['padre']));
    $menu = $i['menu']['menu'] ?? [];
    $vociMenu = array_sum(array_map(fn($m) => count($m['voci']), $menu));
    $altro = $i['altro'] ?? [];
    $redirect = 0;
    foreach (array_merge($art, $pag) as $x) if ($x['stato'] === 'publish') { $redirect += $x['tipo'] === 'pagina' ? 2 : 1; if (rtrim($x['percorso'], '/') !== '/' . $x['slug'] && !str_starts_with($x['percorso'], '/?')) $redirect++; }
    foreach ($cat as $c) if ($c['conta'] && $c['percorso'] !== '' && $c['percorso'] !== '/category/' . $c['slug'] . '/') $redirect++;
    foreach ($tag as $t) if ($t['conta'] && $t['percorso'] !== '' && $t['percorso'] !== '/tag/' . $t['slug'] . '/') $redirect++;
    $seoTitoli = count(array_filter(array_merge($art, $pag), fn($x) => $x['seo']));
    $seoDesc = count(array_filter(array_merge($art, $pag), fn($x) => $x['seo_desc']));
    $figlie = count(array_filter($pag, fn($p) => $p['padre']));
    $pl = fn(int $n, string $uno, string $tanti) => $n . ' ' . ($n === 1 ? $uno : $tanti);
    $fmt = fn(int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB';

    $voci = [];
    $voci['articoli'] = ['nome' => 'Articoli', 'numero' => count($art),
        'dettaglio' => $pl($conta($art, 'publish'), 'pubblicato', 'pubblicati') . ', ' . $pl($conta($art, 'draft') + $conta($art, 'pending') + $conta($art, 'private'), 'bozza', 'bozze') . ', ' . $pl($conta($art, 'future'), 'programmato', 'programmati')
            . ($conta($art, 'pending') ? sprintf(' (fra le bozze, %d in attesa di revisione)', $conta($art, 'pending')) : '') . ($conta($art, 'private') ? ' (' . $pl($conta($art, 'private'), 'privato', 'privati') . ': entrano come bozze)' : ''),
        'diventa' => 'Articoli di plumeCMS con titolo, indirizzo, data e ora, autore, estratto, categorie, tag e immagine in evidenza. Il testo diventa HTML semplice: i blocchi di WordPress (paragrafi, titoli, immagini, gallerie, liste, citazioni, tabelle) restano com\'erano, i video di YouTube e Vimeo restano video.',
        'stati' => ['publish' => $conta($art, 'publish'), 'draft' => $conta($art, 'draft') + $conta($art, 'pending') + $conta($art, 'private'), 'future' => $conta($art, 'future')]];
    $voci['pagine'] = ['nome' => 'Pagine', 'numero' => count($pag),
        'dettaglio' => $pl($conta($pag, 'publish'), 'pubblicata', 'pubblicate') . ', ' . $pl($conta($pag, 'draft') + $conta($pag, 'pending') + $conta($pag, 'private'), 'bozza', 'bozze') . ', ' . $pl($conta($pag, 'future'), 'programmata', 'programmate') . ($figlie ? sprintf('; %d dentro un\'altra pagina', $figlie) : ''),
        'diventa' => 'Pagine di plumeCMS con il loro testo, da rifinire poi nell\'editor visuale se vuoi.' . ($figlie ? ' In plumeCMS le pagine non stanno una dentro l\'altra: "/chi-siamo/il-team/" diventa "/il-team/", con un redirect dal vecchio indirizzo.' : '')];
    $voci['categorie'] = ['nome' => 'Categorie', 'numero' => count($cat),
        'dettaglio' => ($annidate ? "$annidate dentro un'altra categoria" : 'nessuna annidata') . (($g = count(array_filter($cat, fn($c) => in_array($c['slug'], $catEsistenti, true)))) ? "; $g già presenti in plumeCMS (si usano quelle)" : ''),
        'diventa' => 'Categorie di plumeCMS (indirizzo /category/nome/).' . ($annidate ? ' In plumeCMS le categorie non stanno una dentro l\'altra: quelle annidate diventano categorie normali, con un redirect dal vecchio indirizzo.' : '')];
    $voci['tag'] = ['nome' => 'Tag', 'numero' => count($tag),
        'dettaglio' => ($g = count(array_filter($tag, fn($c) => in_array($c['slug'], $tagEsistenti, true)))) ? "$g già presenti in plumeCMS (si usano quelli)" : 'tutti nuovi',
        'diventa' => 'Tag di plumeCMS (indirizzo /tag/nome/).'];
    $voci['media'] = ['nome' => 'Media', 'numero' => count($media),
        'dettaglio' => $pl(count(array_filter($media, fn($m) => $m['immagine'])), 'immagine', 'immagini') . ' e ' . $pl(count(array_filter($media, fn($m) => !$m['immagine'])), 'altro file', 'altri file') . ', ' . $fmt((int)$peso) . ' in tutto' . ($senzaPeso ? " ($senzaPeso senza peso dichiarato)" : ''),
        'diventa' => 'File nella libreria dei Media di plumeCMS (cartella uploads/anno/mese). Le immagini si riscrivono per togliere i dati nascosti. Anche senza questa voce, le immagini usate negli articoli e nelle pagine importate si scaricano lo stesso.'];
    $pos = [];
    foreach ($menu as $m) foreach ($m['posizioni'] as $p) $pos[$p] = true;
    $voci['menu'] = ['nome' => 'Menu', 'numero' => count($menu), 'dettaglio' => $menu ? sprintf('%d voci in tutto', $vociMenu) . ($pos ? '; in posizione: ' . implode(', ', array_keys($pos)) : '') : (($i['menu']['nota'] ?? '') ?: 'nessun menu'),
        'diventa' => 'Menu di navigazione di plumeCMS (Editor > Menu), con le voci che puntano ai nuovi indirizzi. Alla conferma scegli se usarlo come menu principale del sito.'];
    $voci['seo'] = ['nome' => 'SEO', 'numero' => $seoTitoli, 'dettaglio' => ($altro['seo'] ?? '') !== '' || $seoTitoli ? sprintf('%s: %d contenuti con titolo SEO, %d con la descrizione', ($altro['seo'] ?? '') ?: 'Yoast SEO', $seoTitoli, $seoDesc) : 'nessun plugin SEO trovato',
        'numero_desc' => $seoDesc, 'diventa' => 'Titolo e descrizione per Google nei campi SEO di ogni contenuto. Il nome del sito in coda al titolo si toglie: plumeCMS lo aggiunge da sé.'];
    $voci['redirect'] = ['nome' => 'Redirect', 'numero' => $redirect, 'dettaglio' => 'circa, per i contenuti pubblicati; struttura dei permalink: ' . (importa_wp_permalink($s) ?: 'non riconosciuta'),
        'diventa' => 'Redirect 301 dai vecchi indirizzi (con la data, /?p=123, le pagine e le categorie annidate) ai nuovi: i link sparsi per il web e su Google continuano a funzionare.'];

    $nonImport = [];
    if ($note['builder']) foreach ($note['builder'] as $b => $tit) $nonImport[] = sprintf('%s con %s (%s): entra il testo, non la grafica. Rifai l\'impaginazione con l\'editor visuale di plumeCMS.', $pl(count($tit), 'contenuto fatto', 'contenuti fatti'), $b, implode(', ', array_slice($tit, 0, 4)) . (count($tit) > 4 ? '...' : ''));
    if ($note['shortcode']) $nonImport[] = 'Shortcode di plugin (' . implode(', ', array_map(fn($k, $v) => "[$k] in $v", array_keys($note['shortcode']), $note['shortcode'])) . '): sono comandi di plugin che plumeCMS non ha. Si tolgono dal testo; moduli di contatto, gallerie o pulsanti fatti così vanno rifatti con i widget dell\'editor.';
    if ($note['codice']) $nonImport[] = $note['codice'] . ' contenuti hanno moduli, script o riquadri esterni nel testo: per sicurezza non entrano (restano il testo e i video di YouTube e Vimeo).';
    if ($note['campi']) $nonImport[] = $note['campi'] . ' contenuti hanno campi personalizzati (per esempio di ACF): plumeCMS non li ha, non entrano.';
    if ($note['immagini_fuori'] || count($fuori)) $nonImport[] = ($note['immagini_fuori'] + count($fuori)) . ' immagini o file stanno su un altro indirizzo (per esempio un CDN): per sicurezza non si scaricano e restano collegati all\'indirizzo di prima.';
    if ($nonAmmessi) $nonImport[] = 'File di tipo ' . implode(', ', array_map(fn($k, $v) => "$k ($v)", array_keys($nonAmmessi), $nonAmmessi)) . ': la libreria di plumeCMS accetta immagini, PDF, MP4 e MP3. Gli altri si caricano a mano in Download, se servono.';
    if (!empty($altro['commenti'])) $nonImport[] = $altro['commenti'] . ' commenti: plumeCMS non ha i commenti, non entrano. Se ti servono, esportali da WordPress (Strumenti > Esporta) prima di chiudere il vecchio sito.';
    foreach ($altro['tipi'] ?? [] as $t) $nonImport[] = sprintf('%d "%s" (tipo personalizzato %s, per esempio di WooCommerce o di un plugin): plumeCMS non ha quel tipo, non entrano.', $t['quanti'], $t['nome'], $t['chiave']);
    if (($altro['impostazioni']['show_on_front'] ?? '') === 'page' && ($fp = (int)($altro['impostazioni']['page_on_front'] ?? 0))) {
        foreach ($pag as $p) if ($p['id'] === $fp) $nonImport[] = 'La pagina iniziale di WordPress è "' . $p['titolo'] . '": entra come pagina normale. La home di plumeCMS resta quella di adesso.';
    }
    $nonImport[] = 'L\'aspetto (tema, widget delle barre laterali, colori, caratteri) e i plugin non si portano: plumeCMS ha i suoi temi e il suo editor.';
    $nonImport[] = 'Utenti e password di WordPress non entrano: gli autori restano come nome sotto ogni contenuto.';

    return ['sito' => $s['sito']['nome'], 'home' => $s['sito']['home'], 'voci' => $voci, 'conflitti' => $conflitti, 'non_importabili' => $nonImport,
        'autori' => array_values($i['autori'] ?? []), 'menu' => array_map(fn($m) => ['nome' => $m['nome'], 'voci' => count($m['voci']), 'posizioni' => $m['posizioni']], $menu),
        'seo_plugin' => $altro['seo'] ?? '', 'permalink' => importa_wp_permalink($s), 'creato' => time()];
}
