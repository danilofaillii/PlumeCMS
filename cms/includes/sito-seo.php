<?php
/**
 * Metadati e JSON-LD di ogni pagina del sito.
 * Titoli, robots e canonical come li dà oggi Rank Math (collaudati sul contratto degli indirizzi).
 * JSON-LD: un solo @graph per pagina, con @id stabili per persona, servizio e sito.
 */

require_once __DIR__ . '/sito-mappa.php';

// Il nome del sito nei titoli: impostazione sito_titolo (o sito_nome), poi il modulo del sito, poi il nome del CMS.
define('SITO_NOME', (string)(cms_impostazione('sito_titolo') ?: (cms_impostazione('sito_nome') ?: CMS_NOME)));

// Il pannello SEO e AEO (includes/seo.php): titoli, robots, descrizione predefinita, organizzazione e persona del JSON-LD.
// Con i valori di partenza l'uscita e identica a prima.
require_once __DIR__ . '/seo.php';

function sito_assoluto(string $p): string {
    return str_starts_with($p, 'http') ? $p : SITO_URL . $p;
}

function sito_robots_meta(string $r): string {
    return seo_robots_meta($r);
}

/** Il tipo di un contenuto per il pannello SEO: articolo, aforisma, pagina. */
function sito_tipo_seo(array $c): string {
    return match ($c['fonte'] ?? '') { 'articles' => 'articolo', 'aforismi' => 'aforisma', 'pages' => 'pagina', default => 'altro' };
}

/** Titolo, descrizione, robots, canonical e Open Graph di un indirizzo. */
function sito_meta(string $percorso, array $v): array {
    $url = SITO_URL . $percorso;
    if (in_array($v['genere'], ['home', 'contenuto', 'test'], true)) {
        $c = $v['c'];
        $tipoSeo = $v['genere'] === 'home' ? 'home' : sito_tipo_seo($c);
        $titolo = $c['seo']['titolo'] ?: seo_titolo($v['genere'] === 'home' ? (string)cms_impostazione('home_titolo', cms_impostazione('sito_nome', CMS_NOME)) : $c['titolo'], $tipoSeo);
        $immagine = $c['seo']['immagine_social'] ?: ($c['immagine']['url'] ?? seo_og_immagine_predefinita());
        $robots = $c['seo']['robots'];
        if ($v['genere'] === 'contenuto') $robots = seo_robots_tipo(['articolo' => 'articoli', 'aforisma' => 'aforismi', 'pagina' => 'pagine'][$tipoSeo] ?? 'pagine', $robots);
        return [
            'titolo' => $titolo,
            'descrizione' => $c['seo']['descrizione'] ?: (mb_substr($c['estratto'], 0, 160) ?: seo_descrizione_predefinita()),
            'robots' => $robots,
            'canonical' => $c['seo']['canonical'] ?: $url,
            'og' => array_filter([
                'og:type' => $c['tipo'] === 'post' ? 'article' : 'website', 'og:url' => $url, 'og:title' => $titolo,
                'og:locale' => 'it_IT', 'og:site_name' => SITO_NOME, 'og:description' => $c['seo']['descrizione'],
                'og:image' => $immagine,
                'article:published_time' => $c['tipo'] === 'post' ? $c['data'] : '',
                'article:modified_time' => $c['tipo'] === 'post' ? $c['modificato'] : '',
            ]),
        ];
    }
    if ($v['genere'] === 'blog') {
        $c = $v['c'];
        // Come oggi: le pagine 2, 3... del blog puntano alla prima.
        return ['titolo' => $c['seo']['titolo'] ?: seo_titolo($c['titolo'], 'pagina'), 'descrizione' => $c['seo']['descrizione'],
            'robots' => $v['pagina'] > 1 ? seo_robots_tipo('paginazione', $c['seo']['robots']) : $c['seo']['robots'], 'canonical' => SITO_URL . SITO_PAGINA_BLOG, 'og' => []];
    }
    if ($v['genere'] === 'glossario') {
        return ['titolo' => seo_titolo('Glossario', 'altro'), 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []];
    }
    if ($v['genere'] === 'archivio') {
        $robots = seo_robots_tipo(['category' => 'categorie', 'tag' => 'tag', 'data' => 'data'][$v['tassonomia']] ?? 'categorie', $v['robots']);
        if ($v['pagina'] > 1) $robots = seo_robots_tipo('paginazione', $robots);
        return ['titolo' => seo_titolo($v['nome'], 'archivio'), 'descrizione' => '', 'robots' => $robots,
            'canonical' => $v['tassonomia'] === 'tag' ? '' : $url, 'og' => []];
    }
    return ['titolo' => $v['titolo'] ?? SITO_NOME, 'descrizione' => '', 'robots' => $v['robots'] ?? 'noindex', 'canonical' => '', 'og' => []];
}

// ------------------------------------------------------------
// JSON-LD
// ------------------------------------------------------------

function sito_jsonld_fissi(): array {
    // Organizzazione, persona e sito li scrive il pannello SEO e AEO (scheda Schema e AEO). I predefiniti sono quelli di prima.
    return seo_jsonld_fissi();
}

function sito_jsonld_pagina(string $percorso, string $titolo, string $descrizione, array $extra = []): array {
    $u = sito_assoluto($percorso);
    return array_filter(['@type' => 'WebPage', '@id' => "$u#pagina", 'url' => $u, 'name' => $titolo, 'description' => $descrizione ?: null,
        'isPartOf' => ['@id' => SITO_URL . '/#sito'], 'inLanguage' => 'it-IT'] + $extra, fn($x) => $x !== null && $x !== '');
}

function sito_jsonld_briciole(array $voci): array {
    $i = 0;
    return ['@type' => 'BreadcrumbList', 'itemListElement' => array_map(function ($v) use (&$i) {
        return ['@type' => 'ListItem', 'position' => ++$i, 'name' => $v[0], 'item' => sito_assoluto($v[1])];
    }, $voci)];
}

function sito_jsonld_articolo(array &$c): array {
    $u = sito_assoluto($c['percorso']);
    $html = sito_html_contenuto($c);
    $nodi = [
        sito_jsonld_pagina($c['percorso'], $c['titolo'], $c['seo']['descrizione'], $c['immagine'] ? ['primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $c['immagine']['url']]] : []),
        array_filter([
            '@type' => 'BlogPosting', '@id' => "$u#articolo", 'mainEntityOfPage' => ['@id' => "$u#pagina"],
            'headline' => mb_substr($c['titolo'], 0, 110), 'description' => $c['seo']['descrizione'] ?: ($c['estratto'] ?: null),
            'abstract' => $c['tldr'] ?: null, // il TL;DR: la risposta diretta per i motori di risposta
            'datePublished' => $c['data'], 'dateModified' => $c['modificato'],
            'author' => ['@id' => seo_jsonld_id('persona')], 'publisher' => ['@id' => seo_jsonld_id('servizio')],
            'image' => $c['immagine']['url'] ?? null, 'keywords' => $c['seo']['keyword'] ?: null,
            'articleSection' => $c['categorie'][0] ?? null, 'inLanguage' => 'it-IT',
            'wordCount' => count(preg_split('/\s+/u', trim(strip_tags($html)), -1, PREG_SPLIT_NO_EMPTY)),
        ], fn($x) => $x !== null),
    ];
    if ($c['faq']) $nodi[] = sito_jsonld_faq($c);
    return $nodi;
}

function sito_jsonld_faq(array $c): array {
    $u = sito_assoluto($c['percorso']);
    return ['@type' => 'FAQPage', '@id' => "$u#faq", 'isPartOf' => ['@id' => "$u#pagina"],
        'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['domanda'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['risposta']]], $c['faq'])];
}

/** Pagine servizio: "percorso" diventa Course, gli altri Service. Prezzo solo se compilato. */
function sito_jsonld_servizio(array $c): array {
    $s = $c['servizio'];
    $u = sito_assoluto($c['percorso']);
    $offerta = isset($s['prezzo']) && $s['prezzo'] !== '' ? ['@type' => 'Offer', 'price' => $s['prezzo'], 'priceCurrency' => 'EUR', 'url' => $s['cta'] ?? $u, 'availability' => 'https://schema.org/InStock'] : null;
    if (($s['tipo'] ?? '') === 'percorso') {
        $modo = ['in presenza' => 'Onsite', 'entrambe' => 'Blended'][$s['modalita'] ?? ''] ?? 'Online';
        return array_filter(['@type' => 'Course', '@id' => "$u#corso", 'name' => $c['titolo'], 'description' => $c['tldr'] ?: $c['seo']['descrizione'], 'url' => $u,
            'provider' => ['@id' => seo_jsonld_id('servizio')], 'instructor' => ['@id' => seo_jsonld_id('persona')], 'inLanguage' => 'it-IT', 'offers' => $offerta,
            'hasCourseInstance' => array_filter(['@type' => 'CourseInstance', 'courseMode' => $modo, 'courseWorkload' => $s['durata'] ?? null, 'instructor' => ['@id' => seo_jsonld_id('persona')]])]);
    }
    return array_filter(['@type' => 'Service', '@id' => "$u#servizio", 'name' => $c['titolo'], 'description' => $c['tldr'] ?: $c['seo']['descrizione'], 'url' => $u,
        'provider' => ['@id' => seo_jsonld_id('servizio')], 'serviceType' => 'Coaching', 'areaServed' => ['@type' => 'Country', 'name' => 'Italia'], 'offers' => $offerta]);
}

function sito_jsonld_termine(array $c): array {
    $u = sito_assoluto($c['percorso']);
    return array_filter(['@type' => 'DefinedTerm', '@id' => "$u#termine", 'name' => $c['titolo'], 'description' => $c['tldr'] ?: null, 'url' => $u,
        'inDefinedTermSet' => ['@id' => SITO_URL . '/glossario/#insieme']]);
}

function sito_jsonld_glossario(array $termini): array {
    return ['@type' => 'DefinedTermSet', '@id' => SITO_URL . '/glossario/#insieme', 'name' => 'Glossario PNL', 'url' => SITO_URL . '/glossario/',
        'hasDefinedTerm' => array_map(fn($t) => ['@id' => sito_assoluto($t['percorso']) . '#termine'], $termini)];
}

/** Lo script JSON-LD. JSON_HEX_TAG: nessun testo può chiudere il tag <script>. */
function sito_jsonld_script(array $nodi): string {
    $g = ['@context' => 'https://schema.org', '@graph' => array_merge(sito_jsonld_fissi(), seo_jsonld_filtra($nodi))];
    return '<script type="application/ld+json">' . json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
}

/** Valori di partenza delle pagine servizio, finché i campi non sono compilati nel pannello: li dicono i moduli. */
function sito_servizi_predefiniti(): array {
    static $s = null;
    return $s ??= (array)cms_filtro('servizi_predefiniti', []);
}

/** Il nodo "about" della home nel JSON-LD (la persona o l'organizzazione): lo dice il modulo del sito. */
function sito_jsonld_about(): array {
    return (array)cms_filtro('jsonld_about', []);
}
