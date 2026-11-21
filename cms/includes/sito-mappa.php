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
    // 2.1: blog, categorie, tag e archivi per data elencano solo i contenuti che possono uscire in un elenco ('elenco':
    // l'Area riservata toglie i suoi a chi non è entrato). La pagina del contenuto resta raggiungibile e dice lei che è riservata.
    $inElenco = array_flip(array_map(fn($c) => $c['fonte'] . ':' . $c['id'], sito_contenuti_elenco('elenco')));
    $articoli = array_values(array_filter($attivi, fn($c) => $c['tipo'] === 'post' && isset($inElenco[$c['fonte'] . ':' . $c['id']])));

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
        // 2.40.0: la pagina del blog fatta con l'editor. Se il disegno ha un widget che elenca tutti gli articoli (Ultimi articoli
        // o Griglia di articoli senza filtri, sito_blog_widget_per), le pagine si contano con il suo "quanti per pagina" e quel
        // widget mostra la pagina N; senza, sotto il disegno esce l'elenco del blog, 12 per pagina come prima. La voce 'per_widget'
        // c'è solo per i disegni veri: il blog classico e la pagina Blog di partenza (solo il titolo) hanno la voce di sempre.
        $perWidget = !empty($blog['editor']) ? sito_blog_per_widget($blog) : null;
        $gruppi = sito_pagine($nel, $perWidget ?: SITO_BLOG_PER_PAGINA);
        foreach ($gruppi as $i => $g) $m[sito_paginato(SITO_PAGINA_BLOG, $i + 1)] = ['genere' => 'blog', 'pagina' => $i + 1, 'totale' => count($gruppi), 'articoli' => $g, 'c' => $blog]
            + ($perWidget !== null ? ['per_widget' => $perWidget] : []);
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
        if ($a['tipo'] !== 'post' || !isset($inElenco[$a['fonte'] . ':' . $a['id']])) continue;
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

/**
 * 2.40.0: quanti articoli per pagina mostra un widget della pagina del blog quando fa da elenco del blog, oppure 0 se non elenca
 * tutti gli articoli. Ultimi articoli: senza categoria e senza "Solo i pillar", per pagina il suo "Quanti" (1-12). Griglia di
 * articoli: articoli o tutti i contenuti da leggere, senza categoria, tag, "Solo i pillar" e "Salta i primi", dal più recente;
 * per pagina il suo "Per pagina" se ha la paginazione, altrimenti il suo "Quanti". $v: i valori del widget (editor_valori).
 */
function sito_blog_widget_per(string $tipo, array $v): int {
    if ($tipo === 'articoli') {
        if (trim((string)($v['categoria'] ?? '')) !== '' || !empty($v['solo_pillar'])) return 0;
        return max(1, min(12, (int)($v['numero'] ?? 3)));
    }
    if ($tipo === 'griglia_contenuti') {
        if (!in_array((string)($v['fonte'] ?? 'articoli'), ['articoli', 'tutti'], true) || trim((string)($v['categoria'] ?? '')) !== '' || trim((string)($v['tag'] ?? '')) !== ''
            || !empty($v['solo_pillar']) || (int)($v['salta'] ?? 0) > 0 || (string)($v['ordine'] ?? 'recenti') !== 'recenti') return 0;
        return ($v['paginazione'] ?? 'nessuna') !== 'nessuna' ? max(1, min(30, (int)($v['per_pagina'] ?? 6))) : max(1, min(60, (int)($v['quanti'] ?? 6)));
    }
    return 0;
}

/**
 * 2.40.0: nella pagina del blog fatta con l'editor, quanti per pagina mostra il primo widget che elenca tutti gli articoli
 * (sito_blog_widget_per), oppure 0 se il disegno non ne ha (l'elenco del blog esce sotto il disegno). null se il disegno è la
 * pagina Blog di partenza, quella che fanno l'installatore e i kit (un solo widget Titolo, oppure niente): il blog esce come
 * sempre, con la vista standard, identico alla 2.39.2. Lo stesso albero che esce sul sito (sito_pagina_editor): quello
 * pubblicato, oppure la bozza in anteprima se non è mai stata pubblicata.
 */
function sito_blog_per_widget(array $blog): ?int {
    try {
        // La mappa si fa a ogni richiesta e quasi ogni sito ha la pagina Blog di partenza fatta con l'editor: qui si legge il JSON
        // così com'è (sezioni, colonne, widget, come editor_valida) e l'editor si carica solo se c'è un widget degli articoli.
        $json = sito_campo($blog['fonte'], 'struttura', (int)$blog['riga']['id']);
        if (trim($json) === '' && sito_anteprima()) $json = sito_campo($blog['fonte'], 'struttura_bozza', (int)$blog['riga']['id']);
        $albero = json_decode($json, true);
        if (!is_array($albero) || !is_array($albero['elementi'] ?? null)) return null;
        $widget = [];
        foreach (array_slice($albero['elementi'], 0, 200) as $s) {
            if (!is_array($s) || ($s['tipo'] ?? '') !== 'sezione') continue;
            foreach (array_slice((array)($s['figli'] ?? []), 0, 6) as $c) {
                if (!is_array($c) || ($c['tipo'] ?? '') !== 'colonna') continue;
                foreach (array_slice((array)($c['figli'] ?? []), 0, 100) as $w) if (is_array($w) && is_string($w['tipo'] ?? null)) $widget[] = $w;
            }
        }
        if (count($widget) === 0 || (count($widget) === 1 && $widget[0]['tipo'] === 'titolo')) return null;
        foreach ($widget as $w) {
            if (!in_array($w['tipo'], ['articoli', 'griglia_contenuti'], true)) continue;
            require_once __DIR__ . '/editor.php';
            if (!isset(editor_definizioni()[$w['tipo']])) continue;
            $per = sito_blog_widget_per($w['tipo'], editor_valori($w['tipo'], editor_pulisci_impostazioni($w['tipo'], $w['impostazioni'] ?? [])));
            if ($per > 0) return $per;
        }
    } catch (Throwable $e) {
        error_log('sito_blog_per_widget: ' . $e->getMessage());
        return null;
    }
    return 0;
}

/**
 * 2.40.0: chiamata dai widget Ultimi articoli e Griglia di articoli mentre si disegna la pagina del blog fatta con l'editor
 * (sito/index.php mette la voce della mappa in $GLOBALS['sito_blog_elenco']). Il primo widget che elenca tutti gli articoli
 * prende la voce: gli articoli della pagina N, il numero della pagina e quante sono. Gli altri widget, la tela dell'editor e
 * ogni altra pagina ricevono null e si disegnano come sempre.
 */
function sito_blog_elenco_prendi(string $tipo, array $v): ?array {
    $b = $GLOBALS['sito_blog_elenco'] ?? null;
    if (!is_array($b) || !empty($b['preso']) || !empty($GLOBALS['editor_ctx']['anteprima'])) return null;
    if (($GLOBALS['editor_percorso'] ?? '') !== SITO_PAGINA_BLOG || sito_blog_widget_per($tipo, $v) <= 0) return null;
    $GLOBALS['sito_blog_elenco']['preso'] = true;
    return $b;
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
    foreach (sito_contenuti_elenco('elenco') as $c) {
        if ($c['tipo'] !== 'post' || $c['rediretto'] || $c['seo']['robots'] !== 'index') continue;
        if ($filtro && !$filtro($c)) continue;
        $out[] = $c;
        if (count($out) >= $n) break;
    }
    return $out;
}
