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

/** Il tipo di un contenuto per il pannello SEO: articolo, pagina, o il nome di un tipo di un modulo ("uno", per esempio ricetta). */
function sito_tipo_seo(array $c): string {
    $fonte = (string)($c['fonte'] ?? '');
    if ($fonte === 'articles') return 'articolo';
    if ($fonte === 'pages') return 'pagina';
    foreach (seo_tipi_moduli() as $t) if (($t['tabella'] ?? '') === $fonte) return (string)$t['uno'];
    return 'altro';
}

/** La regola di indicizzazione di un tipo SEO: articoli, pagine, o la chiave del tipo del modulo. */
function sito_robots_chiave(string $tipoSeo): string {
    if ($tipoSeo === 'articolo') return 'articoli';
    foreach (seo_tipi_moduli() as $k => $t) if (($t['uno'] ?? '') === $tipoSeo) return (string)$k;
    return 'pagine';
}

/** Titolo, descrizione, robots, canonical e Open Graph di un indirizzo. */
function sito_meta(string $percorso, array $v): array {
    $url = SITO_URL . $percorso;
    if (in_array($v['genere'], ['home', 'contenuto', 'test'], true)) {
        $c = $v['c'];
        $tipoSeo = $v['genere'] === 'home' ? 'home' : sito_tipo_seo($c);
        $titolo = $c['seo']['titolo'] ?: ($v['genere'] === 'home' && function_exists('seo_titolo_home') ? seo_titolo_home((string)cms_impostazione('home_titolo', cms_impostazione('sito_nome', CMS_NOME)))
            : seo_titolo($v['genere'] === 'home' ? (string)cms_impostazione('home_titolo', cms_impostazione('sito_nome', CMS_NOME)) : $c['titolo'], $tipoSeo));
        $immagine = $c['seo']['immagine_social'] ?: ($c['immagine']['url'] ?? seo_og_immagine_predefinita());
        $robots = $c['seo']['robots'];
        if ($v['genere'] === 'contenuto') $robots = seo_robots_tipo(sito_robots_chiave($tipoSeo), $robots);
        return [
            'titolo' => $titolo,
            'descrizione' => $c['seo']['descrizione'] ?: (mb_substr($c['estratto'], 0, 160) ?: seo_descrizione_predefinita()),
            'robots' => $robots,
            'canonical' => $c['seo']['canonical'] ?: $url,
            'og' => array_filter([
                // 2.39.2: og:url uguale al canonical (prima era sempre l'indirizzo della pagina, anche con un canonical scelto nella scheda).
                'og:type' => $c['tipo'] === 'post' ? 'article' : 'website', 'og:url' => $c['seo']['canonical'] ?: $url, 'og:title' => $titolo,
                'og:locale' => 'it_IT', 'og:site_name' => SITO_NOME, 'og:description' => $c['seo']['descrizione'],
                'og:image' => $immagine,
                'article:published_time' => $c['tipo'] === 'post' ? $c['data'] : '',
                'article:modified_time' => $c['tipo'] === 'post' ? $c['modificato'] : '',
            ]),
        ];
    }
    if ($v['genere'] === 'blog') {
        $c = $v['c'];
        // 2.39.2: ogni pagina dell'elenco ha il suo canonical (fino alla 2.39.1 le pagine 2, 3... puntavano alla prima, con
        // titolo e descrizione uguali alla prima: segnali in contrasto per Google e doppioni in Search Console).
        $meta = ['titolo' => $c['seo']['titolo'] ?: seo_titolo($c['titolo'], 'pagina'), 'descrizione' => $c['seo']['descrizione'],
            'robots' => $v['pagina'] > 1 ? seo_robots_tipo('paginazione', $c['seo']['robots']) : $c['seo']['robots'], 'canonical' => $url, 'og' => []];
        // 2.40.0: Open Graph anche per il blog (fino alla 2.39.2 nessuno: un link condiviso usciva senza anteprima curata).
        // L'immagine è quella della pagina del blog se nella scheda ce n'è una, altrimenti quella predefinita di SEO e AEO.
        $meta['og'] = sito_og_elenco($meta, $c['seo']['immagine_social'] ?: (string)($c['immagine']['url'] ?? ''));
        return sito_meta_paginata($meta, (int)$v['pagina'], (int)$v['totale'], $c['titolo'], 'pagina', $c['seo']['titolo'] !== '');
    }
    if ($v['genere'] === 'glossario') {
        return ['titolo' => seo_titolo('Glossario', 'altro'), 'descrizione' => '', 'robots' => 'noindex', 'canonical' => '', 'og' => []];
    }
    if ($v['genere'] === 'archivio') {
        $robots = seo_robots_tipo(['category' => 'categorie', 'tag' => 'tag', 'data' => 'data'][$v['tassonomia']] ?? 'categorie', $v['robots']);
        if ($v['pagina'] > 1) $robots = seo_robots_tipo('paginazione', $robots);
        // 2.39.2: il tag ha il canonical quando è indicizzato (come le categorie); da noindex resta senza, come prima.
        // 2.40.0: la descrizione della categoria scritta in Articoli > Categorie diventa la meta description (pulita dall'HTML e
        // tagliata intorno a 160 caratteri); senza, resta vuota come prima. I tag e gli archivi per data non hanno descrizione.
        $meta = ['titolo' => seo_titolo($v['nome'], 'archivio'), 'descrizione' => $v['tassonomia'] === 'category' ? sito_descrizione_breve((string)($v['descrizione'] ?? '')) : '', 'robots' => $robots,
            'canonical' => $v['tassonomia'] === 'tag' && $robots !== 'index' ? '' : $url, 'og' => []];
        // 2.40.0: Open Graph per categorie e tag (og:url solo se c'è il canonical: un tag noindex resta senza).
        if (in_array($v['tassonomia'], ['category', 'tag'], true)) $meta['og'] = sito_og_elenco($meta, '');
        return sito_meta_paginata($meta, (int)$v['pagina'], (int)$v['totale'], (string)$v['nome'], 'archivio', false);
    }
    return ['titolo' => $v['titolo'] ?? SITO_NOME, 'descrizione' => '', 'robots' => $v['robots'] ?? 'noindex', 'canonical' => '', 'og' => []];
}

/**
 * 2.40.0: l'Open Graph di una pagina d'elenco (blog, categoria, tag), come lo scrivono articoli e pagine: tipo website, og:url
 * uguale al canonical (assente se il canonical manca), titolo, descrizione se c'è, immagine ($immagine, altrimenti la predefinita
 * di SEO e AEO), nome del sito e lingua. Con og:title la pagina scrive anche twitter:card e twitter:image (vista_pagina).
 */
function sito_og_elenco(array $meta, string $immagine): array {
    return array_filter([
        'og:type' => 'website', 'og:url' => (string)$meta['canonical'], 'og:title' => (string)$meta['titolo'],
        'og:locale' => 'it_IT', 'og:site_name' => SITO_NOME, 'og:description' => (string)$meta['descrizione'],
        'og:image' => $immagine !== '' ? $immagine : seo_og_immagine_predefinita(),
    ], fn($x) => $x !== '');
}

/**
 * 2.40.0: un testo scritto nel pannello (per esempio la descrizione di una categoria) pronto per la meta description: senza HTML
 * (e senza il contenuto di script e style), entità decodificate, spazi uniti, tagliato all'ultima parola intera prima di $max
 * caratteri con i puntini. Un testo vuoto resta vuoto.
 */
function sito_descrizione_breve(string $testo, int $max = 160): string {
    $t = (string)preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', ' ', $testo);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = trim((string)preg_replace('/\s+/u', ' ', $t));
    if (mb_strlen($t) <= $max) return $t;
    $corto = mb_substr($t, 0, $max - 1);
    $spazio = mb_strrpos($corto, ' ');
    if ($spazio !== false && $spazio > $max / 2) $corto = mb_substr($corto, 0, $spazio);
    return rtrim($corto, " ,;:.-") . "\u{2026}";
}

/**
 * 2.39.2: titolo e descrizione della pagina N (N >= 2) di un elenco (blog, categorie, tag, archivi per data). Il titolo prende
 * "Pagina N" con il separatore di SEO e AEO, prima del nome del sito ("Blog %sep% Pagina 2 %sep% Nome del sito"); un titolo
 * scritto a mano nella scheda lo prende prima della coda " %sep% %sito%" se finisce così, altrimenti in fondo. La descrizione,
 * se c'è, finisce con "Pagina N di M." (una vuota resta vuota). La pagina 1 esce com'era.
 */
function sito_meta_paginata(array $meta, int $n, int $totale, string $nome, string $tipo, bool $titoloScritto): array {
    if ($n < 2) return $meta;
    $t = seo_cfg()['titolo'];
    $sep = trim((string)$t['sep']);
    $pagina = 'Pagina ' . $n;
    if (!$titoloScritto) {
        $meta['titolo'] = seo_titolo($nome . ' ' . $sep . ' ' . $pagina, $tipo);
    } else {
        $coda = ' ' . $sep . ' ' . trim((string)$t['sito']);
        $meta['titolo'] = str_ends_with($meta['titolo'], $coda) && trim((string)$t['sito']) !== ''
            ? substr($meta['titolo'], 0, -strlen($coda)) . ' ' . $sep . ' ' . $pagina . $coda
            : $meta['titolo'] . ' ' . $sep . ' ' . $pagina;
    }
    $d = rtrim((string)$meta['descrizione']);
    if ($d !== '') $meta['descrizione'] = $d . (preg_match('/[.!?\x{2026}]$/u', $d) ? '' : '.') . ' ' . $pagina . ' di ' . max($n, $totale) . '.';
    foreach (['og:title' => $meta['titolo'], 'og:description' => $meta['descrizione'], 'og:url' => $meta['canonical']] as $k => $x) {
        if (isset($meta['og'][$k])) $meta['og'][$k] = $x;
    }
    return $meta;
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
    // 2.27.0: con le sedi, l'area servita è l'unione delle loro zone (City e AdministrativeArea); senza, l'impostazione di sempre.
    $areaSedi = function_exists('sedi_area_servita') ? sedi_area_servita() : [];
    return array_filter(['@type' => 'Service', '@id' => "$u#servizio", 'name' => $c['titolo'], 'description' => $c['tldr'] ?: $c['seo']['descrizione'], 'url' => $u,
        'provider' => ['@id' => seo_jsonld_id('servizio')], 'serviceType' => (string)cms_impostazione('servizio_tipo', '') ?: null,
        'areaServed' => $areaSedi ?: (($zona = (string)cms_impostazione('servizio_zona', '')) !== '' ? ['@type' => 'Country', 'name' => $zona] : null), 'offers' => $offerta]);
}

/** 2.27.0: i nodi in più della WebPage di una pagina della sede (mainEntity = la sua LocalBusiness). Vuoto per tutte le altre pagine. */
function sito_jsonld_extra_sede(array $c): array {
    if (($c['fonte'] ?? '') !== 'pages' || !function_exists('sedi_jsonld_pagina_extra')) return [];
    return sedi_jsonld_pagina_extra((int)($c['riga']['id'] ?? 0));
}

/** 2.28.0: il nodo in più della WebPage di una pagina per zona (mainEntity = il suo Service). Vuoto per tutte le altre pagine. */
function sito_jsonld_extra_zona(array $c): array {
    if (($c['fonte'] ?? '') !== 'pages' || !function_exists('zone_di_pagina')) return [];
    $z = zone_di_pagina((int)($c['riga']['id'] ?? 0));
    return $z ? zone_jsonld_pagina_extra($z, $c) : [];
}

/** 2.28.0: i nodi di una pagina per zona: il Service (name, areaServed, provider) e le briciole Home > servizio > zona. Vuoto per le altre. */
function sito_jsonld_zona(array $c): array {
    if (($c['fonte'] ?? '') !== 'pages' || !function_exists('zone_di_pagina')) return [];
    $z = zone_di_pagina((int)($c['riga']['id'] ?? 0));
    if (!$z) return [];
    return [zone_jsonld_servizio($z, $c), sito_jsonld_briciole(zone_briciole_voci($z, $c))];
}

function sito_jsonld_termine(array $c): array {
    $u = sito_assoluto($c['percorso']);
    return array_filter(['@type' => 'DefinedTerm', '@id' => "$u#termine", 'name' => $c['titolo'], 'description' => $c['tldr'] ?: null, 'url' => $u,
        'inDefinedTermSet' => ['@id' => SITO_URL . '/glossario/#insieme']]);
}

function sito_jsonld_glossario(array $termini): array {
    return ['@type' => 'DefinedTermSet', '@id' => SITO_URL . '/glossario/#insieme', 'name' => (string)cms_impostazione('glossario_titolo', 'Glossario'), 'url' => SITO_URL . '/glossario/',
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
