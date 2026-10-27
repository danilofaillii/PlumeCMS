<?php
/**
 * La mappa degli indirizzi del sito: per ogni percorso, cosa va mostrato.
 * Stesse regole collaudate sui 1.005 indirizzi del contratto (sito-nuovo/contratto-url.tsv):
 * paginazioni, archivi per data, glossario, blog, categorie e tag.
 */

require_once __DIR__ . '/sito-dati.php';

const SITO_PER_PAGINA = 10;        // archivi: categorie, tag, date
const SITO_BLOG_PER_PAGINA = 12;   // pagina blog
define('SITO_PAGINA_BLOG', (string)cms_impostazione('pagina_blog', '/blog/')); // l'archivio del blog: impostazione pagina_blog
define('SITO_PAGINE_GLOSSARIO', (int)cms_impostazione('glossario_pagine', 0));  // /glossario/page/2/ ... rispondono
const SITO_MESI = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

function sito_paginato(string $base, int $n): string {
    return $n === 1 ? $base : $base . 'page/' . $n . '/';
}

function sito_pagine(array $elenco, int $per): array {
    $g = array_chunk($elenco, $per);
    return $g ?: [[]];
}

/** Tutta la mappa: percorso => voce. Una volta per richiesta. */
function sito_mappa(): array {
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    $tutti = sito_contenuti();
    $attivi = array_values(array_filter($tutti, fn($c) => !$c['rediretto']));
    $articoli = array_values(array_filter($attivi, fn($c) => $c['tipo'] === 'post'));

    foreach ($attivi as $i => $c) {
        if ($c['percorso'] === '/') { $m['/'] = ['genere' => 'home', 'c' => $c]; continue; }
        if ($c['percorso'] === SITO_PAGINA_BLOG) continue;
        $m[$c['percorso']] = ['genere' => 'contenuto', 'c' => $c];
    }
    // I moduli possono cambiare il genere di una voce o aggiungerne (per esempio le pagine con un questionario diventano "test").
    $m = cms_filtro('sito_mappa', $m, $attivi);

    // Blog: solo articoli indicizzati, 12 per pagina.
    $blog = array_values(array_filter($attivi, fn($c) => $c['percorso'] === SITO_PAGINA_BLOG))[0] ?? null;
    if ($blog) {
        $nel = array_values(array_filter($articoli, fn($a) => $a['seo']['robots'] === 'index'));
        $gruppi = sito_pagine($nel, SITO_BLOG_PER_PAGINA);
        foreach ($gruppi as $i => $g) $m[sito_paginato(SITO_PAGINA_BLOG, $i + 1)] = ['genere' => 'blog', 'pagina' => $i + 1, 'totale' => count($gruppi), 'articoli' => $g, 'c' => $blog];
    }

    // Categorie e tag: articoli attivi, 10 per pagina. Una categoria vuota risponde lo stesso.
    $redirectDa = array_flip(array_column(sito_redirect(), 'da'));
    foreach ([['category', sito_categorie(), 'categorie'], ['tag', sito_tag(), 'tag']] as [$tax, $elenco, $chiave]) {
        foreach ($elenco as $t) {
            $base = "/$tax/{$t['slug']}/";
            if (isset($redirectDa[$base])) continue;
            $suoi = array_values(array_filter($articoli, fn($a) => in_array($t['slug'], $a[$chiave], true)));
            $gruppi = sito_pagine($suoi, SITO_PER_PAGINA);
            foreach ($gruppi as $i => $g) {
                $m[sito_paginato($base, $i + 1)] = ['genere' => 'archivio', 'tassonomia' => $tax, 'nome' => $t['name'], 'slug' => $t['slug'],
                    'base' => $base, 'pagina' => $i + 1, 'totale' => count($gruppi), 'articoli' => $g, 'robots' => $t['robots'], 'descrizione' => $t['description'] ?? ''];
            }
        }
    }

    // Archivi per data: tutti gli articoli pubblicati, anche quelli assorbiti dai pillar . Sempre noindex.
    $perData = [];
    foreach ($tutti as $a) {
        if ($a['tipo'] !== 'post') continue;
        [$anno, $mese, $giorno] = explode('-', substr($a['data'], 0, 10));
        $nomeMese = SITO_MESI[(int)$mese - 1];
        foreach ([["/$anno/", $anno], ["/$anno/$mese/", "$nomeMese $anno"], ["/$anno/$mese/$giorno/", (int)$giorno . " $nomeMese $anno"]] as [$k, $nome]) {
            $perData[$k]['nome'] = $nome;
            $perData[$k]['elenco'][] = $a;
        }
    }
    foreach ($perData as $base => $x) {
        $gruppi = sito_pagine($x['elenco'], SITO_PER_PAGINA);
        foreach ($gruppi as $i => $g) {
            $m[sito_paginato($base, $i + 1)] = ['genere' => 'archivio', 'tassonomia' => 'data', 'nome' => $x['nome'], 'slug' => $base,
                'base' => $base, 'pagina' => $i + 1, 'totale' => count($gruppi), 'articoli' => $g, 'robots' => 'noindex', 'descrizione' => ''];
        }
    }

    // Glossario: archivio unico, ripetuto sulle pagine 2-15 come oggi.
    $termini = array_values(array_filter($attivi, fn($c) => $c['tipo'] === 'glossario'));
    usort($termini, fn($a, $b) => strcoll(mb_strtolower($a['titolo']), mb_strtolower($b['titolo'])));
    for ($n = 1; $n <= SITO_PAGINE_GLOSSARIO; $n++) $m[sito_paginato('/glossario/', $n)] = ['genere' => 'glossario', 'pagina' => $n, 'termini' => $termini];

    return $m;
}

function sito_data_estesa(string $iso): string {
    [$a, $mm, $g] = explode('-', substr($iso, 0, 10));
    return (int)$g . ' ' . SITO_MESI[(int)$mm - 1] . ' ' . $a;
}

function sito_minuti_lettura(string $html): int {
    $t = preg_replace('~<table[\s\S]*?</table>~i', ' ', $html);
    return max(1, (int)round(count(preg_split('/\s+/u', trim(strip_tags($t)), -1, PREG_SPLIT_NO_EMPTY)) / 220));
}

function sito_ultimi(int $n, ?callable $filtro = null): array {
    $out = [];
    foreach (sito_contenuti() as $c) {
        if ($c['tipo'] !== 'post' || $c['rediretto'] || $c['seo']['robots'] !== 'index') continue;
        if ($filtro && !$filtro($c)) continue;
        $out[] = $c;
        if (count($out) >= $n) break;
    }
    return $out;
}
