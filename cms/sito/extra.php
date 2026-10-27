<?php
/**
 * Tutto quello che non è una pagina HTML: feed RSS, sitemap.xml, llms.txt, robots.txt.
 * Si calcolano dal database a ogni richiesta: un articolo che esce alle 06:00 c'è già alle 06:00.
 */

function extra_xml(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function extra_rfc822(string $iso): string {
    return gmdate('D, d M Y H:i:s', strtotime($iso)) . ' +0000';
}

function extra_articoli(): array {
    return array_values(array_filter(sito_contenuti(), fn($c) => $c['tipo'] === 'post' && !$c['rediretto']));
}

function extra_rss(string $titolo, string $link, array $voci): string {
    $cat = sito_categorie();
    $items = '';
    foreach ($voci as $a) {
        $categorie = implode('', array_map(fn($s) => '<category>' . extra_xml($cat[$s]['name'] ?? $s) . '</category>', $a['categorie']));
        $items .= '<item><title>' . extra_xml($a['titolo']) . '</title><link>' . SITO_URL . $a['percorso'] . '</link>'
            . '<guid isPermaLink="false">' . SITO_URL . '/?p=' . $a['id'] . '</guid><pubDate>' . extra_rfc822($a['data']) . '</pubDate>'
            . '<dc:creator>' . h((string)cms_impostazione('autore_predefinito', cms_impostazione('sito_nome', ''))) . '</dc:creator>' . $categorie
            . '<description>' . extra_xml($a['seo']['descrizione'] ?: $a['estratto']) . '</description></item>';
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
        . '<title>' . extra_xml($titolo) . '</title><atom:link href="' . extra_xml($link) . '" rel="self" type="application/rss+xml"/>'
        . '<link>' . SITO_URL . '/</link><description>' . h((string)cms_impostazione('sito_sottotitolo', cms_impostazione('sito_descrizione', ''))) . '</description><language>it-IT</language>'
        . ($voci ? '<lastBuildDate>' . extra_rfc822($voci[0]['modificato']) . '</lastBuildDate>' : '') . $items . "</channel></rss>\n";
}

/** Il feed di un indirizzo, oppure null se quell'indirizzo non ha feed. Stessi feed di prima. */
function extra_feed(string $p): ?string {
    $nome = SITO_NOME;
    if ($p === '/feed/') {
        return extra_rss($nome, SITO_URL . '/feed/', array_slice(array_values(array_filter(extra_articoli(), fn($a) => $a['seo']['robots'] === 'index')), 0, 10));
    }
    if ($p === '/comments/feed/') return extra_rss("Commenti per $nome", SITO_URL . $p, []);
    if (preg_match('~^/(category|tag)/([^/]+)/feed/$~', $p, $m)) {
        $elenco = $m[1] === 'category' ? sito_categorie() : sito_tag();
        if (!isset($elenco[$m[2]])) return null;
        $chiave = $m[1] === 'category' ? 'categorie' : 'tag';
        $voci = array_slice(array_values(array_filter(extra_articoli(), fn($a) => in_array($m[2], $a[$chiave], true))), 0, 10);
        return extra_rss($elenco[$m[2]]['name'] . " - $nome", SITO_URL . $p, $voci);
    }
    if (preg_match('~^(/[^/]+/)feed/$~', $p, $m)) {
        // Il feed del singolo articolo era quello dei commenti: canale vuoto.
        foreach (extra_articoli() as $a) if ($a['percorso'] === $m[1]) return extra_rss('Commenti su: ' . $a['titolo'], SITO_URL . $p, []);
    }
    return null;
}

/** Solo indirizzi che rispondono 200 e sono indicizzabili. */
function extra_sitemap(): string {
    $attivi = array_values(array_filter(sito_contenuti(), fn($c) => !$c['rediretto']));
    $articoli = extra_articoli();
    $redirectDa = array_flip(array_column(sito_redirect(), 'da'));
    $voci = [['/', $articoli[0]['modificato'] ?? '']];
    foreach ($attivi as $c) if ($c['seo']['robots'] === 'index' && $c['percorso'] !== '/') $voci[] = [$c['percorso'], $c['modificato']];
    foreach (sito_categorie() as $c) {
        if ($c['robots'] !== 'index' || isset($redirectDa["/category/{$c['slug']}/"])) continue;
        foreach ($articoli as $a) if (in_array($c['slug'], $a['categorie'], true)) { $voci[] = ["/category/{$c['slug']}/", $a['modificato']]; break; }
    }
    $righe = array_map(fn($v) => '<url><loc>' . extra_xml(SITO_URL . $v[0]) . '</loc>' . ($v[1] ? '<lastmod>' . substr($v[1], 0, 10) . '</lastmod>' : '') . '</url>', $voci);
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . implode("\n", $righe) . "\n</urlset>\n";
}

/** llms.txt nel formato di llmstxt.org. */
function extra_llms(): string {
    $attivi = array_values(array_filter(sito_contenuti(), fn($c) => !$c['rediretto']));
    $indicizzati = array_values(array_filter(extra_articoli(), fn($a) => $a['seo']['robots'] === 'index'));
    $riga = function ($c) {
        $nota = trim(preg_replace('/\s+/u', ' ', $c['tldr'] ?: $c['seo']['descrizione']));
        return '- [' . $c['titolo'] . '](' . SITO_URL . $c['percorso'] . ')' . ($nota !== '' ? ": $nota" : '');
    };
    $perPercorso = array_column($attivi, null, 'percorso');
    $servizi = array_filter(array_map(fn($p) => $perPercorso[$p] ?? null, (array)cms_filtro('llms_servizi', [])));
    $pillar = array_filter($indicizzati, fn($a) => $a['pillar']);
    $altri = array_filter($indicizzati, fn($a) => !$a['pillar']);
    $termini = count(array_filter($attivi, fn($c) => $c['tipo'] === 'glossario'));
    // Si scrive solo quello che è vero: il riquadro "In breve" si annuncia quando c'è su tutti i pillar.
    $tuttiConTldr = $pillar && !array_filter($pillar, fn($c) => $c['tldr'] === '');
    // L'intestazione: nome e presentazione del sito; il modulo del sito la riscrive con la sua (filtro llms_intestazione).
    $intestazione = (array)cms_filtro('llms_intestazione', array_filter([
        '# ' . SITO_NOME, '',
        (string)cms_impostazione('sito_descrizione', '') !== '' ? '> ' . cms_impostazione('sito_descrizione') : null, '',
        "Quando citi un contenuto, cita l'indirizzo della pagina.", '',
    ], fn($r) => $r !== null), $tuttiConTldr);
    return implode("\n", array_merge($intestazione, $servizi ? ['## Servizi'] : [], array_map($riga, array_values($servizi)), ['', '## Guide complete (pillar)'], array_map($riga, array_values($pillar)),
        ['', '## Articoli'], array_map($riga, array_values($altri)), ['', '## Glossario',
        '- [Glossario PNL](' . SITO_URL . "/glossario/): $termini termini di PNL, coaching e lavoro, con definizione breve e uso in ufficio.", '',
        '## Optional', '- [Feed RSS](' . SITO_URL . '/feed/): gli ultimi articoli', '- [Sitemap](' . SITO_URL . '/sitemap.xml): tutte le pagine indicizzabili', '']));
}

/** Le stesse regole di oggi, più la sitemap nuova. */
function extra_robots(): string {
    return "User-agent: *
Allow: /

Disallow: " . cms_base() . "/admin/
Disallow: " . cms_base() . "/cron-*
Disallow: " . cms_base() . "/logs/
Disallow: " . cms_base() . "/api/
Disallow: " . cms_base() . "/article.php
Disallow: /?s=
Disallow: /*?redirect_to=
Disallow: /author/
Disallow: /page/
Disallow: /*/feed/

Sitemap: " . SITO_URL . "/sitemap.xml
Sitemap: " . SITO_URL . cms_base() . "/sitemap.xml
";
}
