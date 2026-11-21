<?php
/**
 * Addon AI collegate (2.34.0): lo stile del sito. analyze_style misura gli articoli pubblicati (al massimo gli ultimi 50) con
 * regole semplici e dichiarate: niente modello di linguaggio sul server, solo conti. save_style_profile conserva il profilo
 * scritto dall'AI collegata (testo semplice, al massimo 8000 caratteri) con le misure del momento, calcolate di nuovo qui;
 * get_style_profile lo rilegge. Uno solo per sito, con le ultime dieci versioni recuperabili (tabella ai_stile: l'ultima riga
 * è quella in uso). aic_stile_coerenza() confronta una bozza con il profilo (create_draft e update_draft): solo avvisi.
 * Si carica da strumenti.php e dalle pagine del pannello. Manuale tecnico, 47.17-47.18.
 */
require_once __DIR__ . '/ai.php';

/** Gli articoli misurati al massimo in una chiamata, la lunghezza del profilo, le versioni tenute oltre quella in uso. */
const AIC_STILE_MAX_ARTICOLI = 50;
const AIC_STILE_PROFILO_MAX = 8000;
const AIC_STILE_VERSIONI = 10;
/** Un estratto rappresentativo al massimo (caratteri). */
const AIC_STILE_ESTRATTO_MAX = 600;

/** Le parole vuote dell'italiano: non contano fra le parole ricorrenti e non aprono né chiudono un'espressione. */
const AIC_STILE_VUOTE = ['a', 'ad', 'al', 'allo', 'alla', 'ai', 'agli', 'alle', 'anche', 'ancora', 'avere', 'aveva', 'avevano', 'c', 'che', 'chi', 'ci', 'coi', 'col', 'come',
    'con', 'contro', 'cosa', 'cui', 'da', 'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle', 'degli', 'dei', 'del', 'dell', 'della', 'delle', 'dello', 'di', 'dove', 'dopo',
    'e', 'ecco', 'ed', 'era', 'erano', 'essere', 'fa', 'fare', 'fino', 'fra', 'gli', 'ha', 'hanno', 'ho', 'i', 'il', 'in', 'invece', 'io', 'l', 'la', 'le', 'lei', 'li', 'lo',
    'loro', 'lui', 'ma', 'mai', 'me', 'meno', 'mi', 'mio', 'mia', 'molto', 'molta', 'molti', 'molte', 'ne', 'nei', 'nel', 'nell', 'nella', 'nelle', 'nello', 'negli', 'nessun',
    'nessuno', 'no', 'noi', 'non', 'nostro', 'nostra', 'nostri', 'nostre', 'o', 'od', 'ogni', 'oppure', 'per', 'perche', 'perch', 'poi', 'po', 'piu', 'proprio', 'qua', 'quale',
    'quali', 'quando', 'quanto', 'quella', 'quelle', 'quelli', 'quello', 'questa', 'queste', 'questi', 'questo', 'qui', 'se', 'sei', 'senza', 'si', 'sia', 'siamo', 'sono',
    'sopra', 'sotto', 'sta', 'stato', 'stati', 'su', 'sua', 'sue', 'sui', 'sul', 'sull', 'sulla', 'sulle', 'suo', 'suoi', 'tra', 'tu', 'ti', 'te', 'tuo', 'tua', 'tuoi', 'tue',
    'tutto', 'tutta', 'tutti', 'tutte', 'un', 'una', 'uno', 'vi', 'voi', 'vostro', 'vostra', 'gia', 'cosi', 'puo', 'puoi', 'essere', 'stesso', 'stessa', 'altro', 'altra', 'altri',
    'altre', 'com', 'dov', 'quell', 'quest', 'nient', 'd', 's', 'm', 't', 'v', 'n', 'ora', 'quindi', 'pero', 'mentre', 'oltre', 'entro', 'verso', 'tanto', 'tanta', 'tanti', 'tante', 'solo', 'sempre', 'bene', 'dire', 'detto', 'nostro', 'via'];
/** Le parole che dicono la persona del testo (euristica dichiarata in come_leggerlo). */
const AIC_STILE_TU = ['tu', 'ti', 'te', 'tuo', 'tua', 'tuoi', 'tue', 'puoi', 'devi', 'vuoi', 'sai', 'hai', 'sei', 'fai', 'scegli', 'prova', 'pensa', 'immagina', 'scopri', 'ricorda'];
const AIC_STILE_VOI = ['voi', 'vi', 'vostro', 'vostra', 'vostri', 'vostre', 'potete', 'dovete', 'volete', 'avete', 'siete', 'fate'];
const AIC_STILE_NOI = ['noi', 'nostro', 'nostra', 'nostri', 'nostre', 'possiamo', 'dobbiamo', 'vogliamo', 'abbiamo', 'siamo', 'facciamo', 'lavoriamo', 'offriamo'];
/** Sopra queste parole ogni mille un articolo ha quella persona. */
const AIC_STILE_SOGLIA_PERSONA = 6;

// ------------------------------------------------------------
// Testo di un articolo: dall'albero dell'editor o dalla scheda, senza disegnare la pagina
// ------------------------------------------------------------

/**
 * Le parti misurabili di un contenuto: HTML del testo (testi e titoli dei widget, o il testo della scheda), sequenza dei
 * widget, immagini e link interni che non stanno nell'HTML. $bozza: l'albero della bozza se c'è (per la coerenza).
 */
function aic_stile_estrai(string $tipo, array $r, bool $bozza = false): array {
    $json = $bozza && trim((string)($r['struttura_bozza'] ?? '')) !== '' ? (string)$r['struttura_bozza'] : (string)($r['struttura'] ?? '');
    $out = ['html' => '', 'widget' => [], 'immagini_widget' => 0, 'link_widget' => 0, 'editor' => false];
    $a = trim($json) !== '' ? json_decode($json, true) : null;
    if (is_array($a) && !empty($a['elementi'])) {
        $out['editor'] = true;
        $html = [];
        foreach ((array)$a['elementi'] as $s) foreach ((array)($s['figli'] ?? []) as $c) foreach ((array)($c['figli'] ?? []) as $w) {
            if (!is_array($w)) continue;
            $t = (string)($w['tipo'] ?? '');
            $imp = is_array($w['impostazioni'] ?? null) ? $w['impostazioni'] : [];
            if ($t !== '' && count($out['widget']) < 200) $out['widget'][] = $t;
            if ($t === 'testo') { $html[] = (string)($imp['contenuto'] ?? ''); continue; }
            if ($t === 'titolo') { $l = in_array($imp['livello'] ?? 'h2', ['h1', 'h2', 'h3', 'h4'], true) ? $imp['livello'] : 'h2'; $html[] = '<' . $l . '>' . h((string)($imp['testo'] ?? '')) . '</' . $l . '>'; continue; }
            if (in_array($t, ['immagine', 'galleria', 'carosello_immagini', 'prima_dopo'], true)) $out['immagini_widget']++;
            // Gli altri widget: i testi dei campi diventano paragrafi (riquadri, elenchi, inviti); i campi link contano come link.
            $testi = [];
            array_walk_recursive($imp, function ($v, $k) use (&$testi, &$out) {
                if (!is_string($v) || $v === '') return;
                if (preg_match('~(^|_)(link|url|href)$~', (string)$k)) { if (preg_match('~^/(?!/)~', $v)) $out['link_widget']++; return; }
                if (preg_match('~^(#|/|https?:|\{|rgba?\(|var\()~', $v) || in_array((string)$k, ['immagine', 'icona', 'livello', 'tipo', 'disposizione', 'id', 'stile', 'colore', 'allineamento'], true) || mb_strlen($v) < 3) return;
                $testi[] = $v;
            });
            foreach ($testi as $x) $html[] = str_contains($x, '<') ? $x : '<p>' . h($x) . '</p>';
        }
        $out['html'] = implode("\n", $html);
        return $out;
    }
    $h = (string)($r[contenuti_tipo($tipo)['campo_contenuto']] ?? '');
    if (($r['content_format'] ?? 'html') === 'markdown' && function_exists('markdown_to_html')) $h = markdown_to_html($h);
    $out['html'] = $h;
    return $out;
}

/** Testo semplice da un pezzo di HTML (entità decodificate, spazi compattati). */
function aic_stile_piano(string $html): string {
    return trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)preg_replace('~<[^>]+>~', ' $0 ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/** Le parole (lettere e numeri, minuscole, senza accenti per i conti). */
function aic_stile_parole(string $testo): array {
    preg_match_all('/[\p{L}\p{N}]+/u', $testo, $m);
    return $m[0];
}

/** Le parole con un segno vuoto ('') dove finisce una frase o un pezzo (punteggiatura): le espressioni non scavalcano i confini. */
function aic_stile_token(string $testo): array {
    preg_match_all('/[\p{L}\p{N}]+|[.!?;:,()\x{2026}"\x{201C}\x{201D}\x{AB}\x{BB}]/u', $testo, $m);
    return array_map(fn($x) => preg_match('/^[\p{L}\p{N}]/u', $x) ? $x : '', $m[0]);
}

/** Le frasi: si spezza dopo . ! ? e i puntini, quando segue uno spazio e una maiuscola, un numero o una virgoletta. */
function aic_stile_frasi(string $testo): array {
    $f = preg_split('/(?<=[.!?\x{2026}])\s+(?=[\p{Lu}\p{N}"\x{201C}\x{AB}(])/u', trim($testo), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_filter(array_map('trim', $f), fn($x) => count(aic_stile_parole($x)) >= 2));
}

function aic_stile_norm(string $s): string {
    return function_exists('seo_norm') ? seo_norm($s) : trim((string)preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)));
}

/** Gli emoji: caratteri a 4 byte, entità numeriche sopra U+1F000 e i simboli più usati (U+2600-U+27BF). */
function aic_stile_emoji(string $html): int {
    $n = preg_match_all('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
    if (preg_match_all('/&#(x[0-9a-f]+|\d+);/i', $html, $m)) foreach ($m[1] as $x) {
        $c = $x[0] === 'x' || $x[0] === 'X' ? hexdec(substr($x, 1)) : (int)$x;
        if ($c >= 0x1F000 || ($c >= 0x2600 && $c <= 0x27BF)) $n++;
    }
    return (int)$n;
}

/** Link interni nell'HTML: verso un percorso del sito o l'indirizzo del sito, non verso altri domini. */
function aic_stile_link_interni(string $html): array {
    preg_match_all('~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1~is', $html, $m);
    $base = rtrim(cms_url_sito(), '/');
    $out = [];
    foreach ($m[2] as $u) {
        $u = html_entity_decode(trim($u), ENT_QUOTES, 'UTF-8');
        if (str_starts_with($u, $base . '/')) $u = substr($u, strlen($base));
        if (preg_match('~^/(?!/)~', $u)) $out[] = $u;
    }
    return $out;
}

/** La persona di un testo: tu, voi, noi o impersonale, con le parole ogni mille. */
function aic_stile_persona(array $parole): array {
    $n = max(1, count($parole));
    $conta = ['tu' => 0, 'voi' => 0, 'noi' => 0];
    foreach ($parole as $p) {
        $p = mb_strtolower($p);
        if (in_array($p, AIC_STILE_TU, true)) $conta['tu']++;
        elseif (in_array($p, AIC_STILE_VOI, true)) $conta['voi']++;
        elseif (in_array($p, AIC_STILE_NOI, true)) $conta['noi']++;
    }
    $mille = array_map(fn($x) => round($x * 1000 / $n, 1), $conta);
    arsort($mille);
    $prima = (string)array_key_first($mille);
    return ['persona' => $mille[$prima] >= AIC_STILE_SOGLIA_PERSONA ? $prima : 'impersonale', 'ogni_mille' => ['tu' => $mille['tu'], 'voi' => $mille['voi'], 'noi' => $mille['noi']]];
}

/** Le misure di un solo contenuto (per l'analisi e per la coerenza di una bozza). */
function aic_stile_misura(string $tipo, array $r, bool $bozza = false): array {
    $e = aic_stile_estrai($tipo, $r, $bozza);
    $html = $e['html'];
    preg_match_all('~<p\b[^>]*>([\s\S]*?)</p>~i', $html, $mp);
    $paragrafi = [];
    foreach ($mp[1] as $p) { $t = aic_stile_piano($p); if (count(aic_stile_parole($t)) >= 3) $paragrafi[] = $t; }
    $piano = aic_stile_piano((string)preg_replace('~</(p|h[1-6]|li|blockquote|div|td)>~i', '$0 ', $html));
    $parole = aic_stile_parole($piano);
    $frasi = aic_stile_frasi($piano);
    if (!$paragrafi && $piano !== '') $paragrafi = [$piano];
    $parFrasi = $paragrafi ? aic_stile_frasi($paragrafi[0]) : [];
    $ultFrasi = $paragrafi ? aic_stile_frasi($paragrafi[count($paragrafi) - 1]) : [];
    $attacco = implode(' ', array_slice($parFrasi ?: ($paragrafi ? [$paragrafi[0]] : []), 0, 2));
    $chiusura = implode(' ', array_slice($ultFrasi ?: ($paragrafi ? [$paragrafi[count($paragrafi) - 1]] : []), -2));
    $titoliDomanda = 0;
    if (preg_match_all('~<h[23]\b[^>]*>([\s\S]*?)</h[23]>~i', $html, $mh)) foreach ($mh[1] as $x) if (str_ends_with(aic_stile_piano($x), '?')) $titoliDomanda++;
    $faq = function_exists('sito_faq_da_json') ? sito_faq_da_json((string)($r['faq'] ?? '')) : (json_decode((string)($r['faq'] ?? ''), true) ?: []);
    $link = aic_stile_link_interni($html);
    $token = aic_stile_token(aic_stile_piano((string)preg_replace('~</(p|h[1-6]|li|blockquote|div|td)>~i', ' . $0', $html)));
    return [
        'parole' => count($parole),
        'frasi' => count($frasi),
        'parole_per_frase' => $frasi ? round(count($parole) / count($frasi), 1) : 0,
        'paragrafi' => count($paragrafi),
        'parole_per_paragrafo' => $paragrafi ? round(array_sum(array_map(fn($p) => count(aic_stile_parole($p)), $paragrafi)) / count($paragrafi), 1) : 0,
        'h2' => (int)preg_match_all('~<h2\b~i', $html), 'h3' => (int)preg_match_all('~<h3\b~i', $html),
        'elenchi' => (int)preg_match_all('~<(ul|ol)\b~i', $html), 'voci_elenco' => (int)preg_match_all('~<li\b~i', $html),
        'domande' => count(array_filter($frasi, fn($f) => str_ends_with(rtrim($f, " \"\x{201D}\x{BB})"), '?'))),
        'attacco_con_domanda' => str_contains($attacco, '?'),
        'grassetti' => (int)preg_match_all('~<(strong|b)\b~i', $html),
        'emoji' => aic_stile_emoji($html),
        'link_interni' => count($link) + $e['link_widget'],
        'immagini' => (int)preg_match_all('~<img\b~i', $html) + $e['immagini_widget'] + (trim((string)($r['immagine_url'] ?? $r['featured_image'] ?? '')) !== '' ? 1 : 0),
        'domande_frequenti' => count($faq) + $titoliDomanda,
        'persona' => aic_stile_persona($parole),
        'editor' => $e['editor'], 'widget' => $e['widget'],
        '_attacco' => $attacco, '_chiusura' => $chiusura, '_paragrafi' => $paragrafi, '_parole' => $parole, '_token' => $token,
    ];
}

// ------------------------------------------------------------
// analyze_style
// ------------------------------------------------------------

function aic_stile_media(array $v): float { return $v ? round(array_sum($v) / count($v), 1) : 0.0; }
function aic_stile_mediana(array $v): float {
    if (!$v) return 0.0;
    sort($v); $n = count($v); $m = intdiv($n, 2);
    return round($n % 2 ? (float)$v[$m] : ($v[$m - 1] + $v[$m]) / 2, 1);
}

/** Gli articoli pubblicati più recenti, con le colonne che servono (una query sola). */
function aic_stile_articoli(int $quanti, int $salta = 0): array {
    $t = tbl('articles');
    $cols = 'id, title, slug, content, content_format, struttura, faq, keyword_target, keywords_secondarie, seo_title, seo_description, meta_description, categorie, category, tag_slugs, tags, featured_image, immagine_url, publish_date, publish_time, updated_at';
    $st = db()->query('SELECT ' . $cols . ' FROM ' . $t . ' WHERE ' . contenuti_sql_stato('articoli', 'pubblicato') . ' ORDER BY publish_date DESC, publish_time DESC, id DESC LIMIT ' . max(0, $salta) . ', ' . max(1, $quanti));
    return $st->fetchAll();
}

function aic_stile_totale_pubblicati(): int {
    return (int)db()->query('SELECT COUNT(*) FROM ' . tbl('articles') . ' WHERE ' . contenuti_sql_stato('articoli', 'pubblicato'))->fetchColumn();
}

/** Le parole e le espressioni ricorrenti (in almeno due articoli), escluse le parole vuote. */
function aic_stile_ricorrenti(array $perArticolo): array {
    $parole = []; $arti = []; $gr = [2 => [], 3 => []]; $grArt = [2 => [], 3 => []];
    foreach ($perArticolo as $i => $ps) {
        $norm = array_map(fn($p) => aic_stile_norm($p), $ps);
        $visti = []; $vistiG = [2 => [], 3 => []];
        $n = count($norm);
        foreach ($norm as $k => $p) {
            if ($p === '' || mb_strlen($p) < 3 || ctype_digit($p) || in_array($p, AIC_STILE_VUOTE, true)) continue;
            $parole[$p] = ($parole[$p] ?? 0) + 1;
            $visti[$p] = true;
            foreach ([2, 3] as $g) {
                if ($k + $g > $n) continue;
                $pezzo = array_slice($norm, $k, $g);
                $ultima = $pezzo[$g - 1];
                if ($ultima === '' || mb_strlen($ultima) < 3 || in_array($ultima, AIC_STILE_VUOTE, true) || in_array('', $pezzo, true)) continue;
                $x = implode(' ', $pezzo);
                $gr[$g][$x] = ($gr[$g][$x] ?? 0) + 1;
                $vistiG[$g][$x] = true;
            }
        }
        foreach ($visti as $p => $_) $arti[$p] = ($arti[$p] ?? 0) + 1;
        foreach ([2, 3] as $g) foreach ($vistiG[$g] as $x => $_) $grArt[$g][$x] = ($grArt[$g][$x] ?? 0) + 1;
    }
    $minimo = count($perArticolo) >= 2 ? 2 : 1;
    $scegli = function (array $conti, array $inArt, int $quante) use ($minimo) {
        $c = array_filter($conti, fn($v, $k) => ($inArt[$k] ?? 0) >= $minimo && $v >= 2, ARRAY_FILTER_USE_BOTH);
        uksort($c, fn($a, $b) => [$c[$b], $inArt[$b] ?? 0, $a] <=> [$c[$a], $inArt[$a] ?? 0, $b]);
        $out = [];
        foreach (array_slice($c, 0, $quante, true) as $k => $v) $out[] = ['testo' => (string)$k, 'volte' => $v, 'articoli' => $inArt[$k] ?? 0];
        return $out;
    };
    return ['parole' => $scegli($parole, $arti, 25), 'espressioni_di_2' => $scegli($gr[2], $grArt[2], 15), 'espressioni_di_3' => $scegli($gr[3], $grArt[3], 10)];
}

/** Le misure di tutti gli articoli scelti, aggregate. Ritorna il JSON di analyze_style. */
function aic_stile_analizza(array $a = []): array {
    $quanti = max(1, min(AIC_STILE_MAX_ARTICOLI, (int)($a['quanti'] ?? AIC_STILE_MAX_ARTICOLI)));
    $salta = max(0, min(100000, (int)($a['salta'] ?? 0)));
    $righe = aic_stile_articoli($quanti, $salta);
    $totale = aic_stile_totale_pubblicati();
    $base = ['articoli_misurati' => count($righe), 'articoli_pubblicati' => $totale, 'salta' => $salta, 'calcolato' => date('Y-m-d H:i:s')];
    if (!$righe) return $base + ['nota' => $totale ? 'Nessun articolo in questa parte dell\'elenco: riduci "salta".' : 'Il sito non ha ancora articoli pubblicati: non c\'è uno stile da misurare. Scrivi le prime bozze seguendo le indicazioni del proprietario.', 'come_leggerlo' => aic_stile_come_leggerlo()];
    $tagNomi = db()->query('SELECT slug, name FROM ' . tbl('tags'))->fetchAll(PDO::FETCH_KEY_PAIR);
    $catNomi = array_map(fn($c) => (string)$c['name'], contenuti_categorie());
    $m = []; $perArt = []; $persone = ['tu' => 0, 'voi' => 0, 'noi' => 0, 'impersonale' => 0];
    $cat = []; $tag = []; $titoli = []; $titoliSeo = []; $descr = []; $seq = []; $primo = []; $tipiW = []; $editor = 0;
    foreach ($righe as $r) {
        $x = aic_stile_misura('articoli', $r);
        $x['_r'] = $r;
        $m[] = $x;
        $perArt[] = $x['_token'];
        $persone[$x['persona']['persona']]++;
        foreach (contenuti_categorie_di($r) as $c) if ($c !== '') $cat[$c] = ($cat[$c] ?? 0) + 1;
        $ts = sito_lista((string)($r['tag_slugs'] ?? '')) ?: array_map('contenuti_slug', sito_lista((string)($r['tags'] ?? '')));
        foreach ($ts as $s) if ($s !== '') $tag[$s] = ($tag[$s] ?? 0) + 1;
        $titoli[] = mb_strlen((string)$r['title']);
        $titoliSeo[] = mb_strlen(trim((string)$r['seo_title']) !== '' ? (string)$r['seo_title'] : (string)$r['title']);
        $d = trim((string)(($r['meta_description'] ?? '') ?: ($r['seo_description'] ?? '')));
        if ($d !== '') $descr[] = mb_strlen($d);
        if ($x['editor']) {
            $editor++;
            $w = $x['widget'];
            $comp = [];
            foreach ($w as $t) if (!$comp || end($comp) !== $t) $comp[] = $t; // i widget uguali di fila contano una volta
            $k = implode(' > ', array_slice($comp, 0, 12));
            if ($k !== '') $seq[$k] = ($seq[$k] ?? 0) + 1;
            if ($w) $primo[$w[0]] = ($primo[$w[0]] ?? 0) + 1;
            foreach (array_unique($w) as $t) $tipiW[$t] = ($tipiW[$t] ?? 0) + 1;
        }
    }
    $n = count($m);
    $col = fn(string $k) => array_column($m, $k);
    $quota = fn(callable $f) => round(100 * count(array_filter($m, $f)) / $n);
    arsort($cat); arsort($tag); arsort($seq); arsort($primo); arsort($tipiW);
    $parFrasi = []; foreach ($m as $x) foreach ($x['_paragrafi'] as $p) $parFrasi[] = count(aic_stile_parole($p));
    $parMed = aic_stile_media($parFrasi);
    $dominante = $persone; arsort($dominante); $dom = (string)array_key_first($dominante);
    // Esempi: i cinque più recenti (l'ordine della query) con un attacco e una chiusura veri.
    $attacchi = []; $chiusure = [];
    foreach ($m as $x) {
        if (count($attacchi) < 5 && $x['_attacco'] !== '') $attacchi[] = ['articolo' => (string)$x['_r']['title'], 'id' => (int)$x['_r']['id'], 'testo' => mb_substr($x['_attacco'], 0, 400)];
        if (count($chiusure) < 5 && $x['_chiusura'] !== '') $chiusure[] = ['articolo' => (string)$x['_r']['title'], 'id' => (int)$x['_r']['id'], 'testo' => mb_substr($x['_chiusura'], 0, 400)];
    }
    // Estratti rappresentativi: il paragrafo più vicino al paragrafo medio del sito, uno per articolo, con la persona dominante
    // se c'è, dai più recenti; al massimo cinque.
    $estratti = []; $giaVisti = [];
    foreach ($m as $x) {
        if (count($estratti) >= 5) break;
        $migliore = null; $dist = PHP_INT_MAX;
        foreach ($x['_paragrafi'] as $p) {
            $np = count(aic_stile_parole($p));
            if ($np < 15) continue;
            if ($dom !== 'impersonale' && aic_stile_persona(aic_stile_parole($p))['persona'] !== $dom) continue;
            $dd = abs($np - $parMed);
            if ($dd < $dist && !isset($giaVisti[aic_stile_norm($p)])) { $dist = $dd; $migliore = $p; }
        }
        if ($migliore !== null) $giaVisti[aic_stile_norm($migliore)] = true; // due paragrafi uguali in due articoli valgono un estratto
        if ($migliore !== null) $estratti[] = ['articolo' => (string)$x['_r']['title'], 'id' => (int)$x['_r']['id'], 'testo' => mb_strlen($migliore) > AIC_STILE_ESTRATTO_MAX ? mb_substr($migliore, 0, AIC_STILE_ESTRATTO_MAX - 3) . '...' : $migliore];
    }
    $tagOut = []; foreach (array_slice($tag, 0, 15, true) as $s => $v) $tagOut[] = ['tag' => (string)($tagNomi[$s] ?? $s), 'articoli' => $v];
    $catOut = []; foreach (array_slice($cat, 0, 15, true) as $s => $v) $catOut[] = ['categoria' => (string)($catNomi[$s] ?? $s), 'slug' => (string)$s, 'articoli' => $v];
    $seqOut = []; foreach (array_slice($seq, 0, 5, true) as $s => $v) $seqOut[] = ['sequenza' => (string)$s, 'articoli' => $v];
    $primoOut = []; foreach (array_slice($primo, 0, 5, true) as $s => $v) $primoOut[] = ['widget' => (string)$s, 'articoli' => $v];
    $tipiOut = []; foreach (array_slice($tipiW, 0, 15, true) as $s => $v) $tipiOut[] = ['widget' => (string)$s, 'articoli' => $v];
    return $base + [
        'lunghezza' => ['parole_media' => aic_stile_media($col('parole')), 'parole_mediana' => aic_stile_mediana($col('parole')), 'parole_minimo' => min($col('parole')), 'parole_massimo' => max($col('parole'))],
        'frasi' => ['parole_per_frase_media' => aic_stile_media(array_filter($col('parole_per_frase'))), 'frasi_per_articolo' => aic_stile_media($col('frasi'))],
        'paragrafi' => ['parole_per_paragrafo_media' => $parMed, 'paragrafi_per_articolo' => aic_stile_media($col('paragrafi'))],
        'sottotitoli' => ['h2_per_articolo' => aic_stile_media($col('h2')), 'h3_per_articolo' => aic_stile_media($col('h3')), 'articoli_senza_h2_percento' => $quota(fn($x) => $x['h2'] === 0)],
        'elenchi' => ['articoli_con_elenchi_percento' => $quota(fn($x) => $x['elenchi'] > 0), 'elenchi_per_articolo' => aic_stile_media($col('elenchi')), 'voci_per_articolo' => aic_stile_media($col('voci_elenco'))],
        'domande_ai_lettori' => ['per_articolo' => aic_stile_media($col('domande')), 'articoli_con_domande_percento' => $quota(fn($x) => $x['domande'] > 0), 'attacchi_con_domanda_percento' => $quota(fn($x) => $x['attacco_con_domanda'])],
        'persona' => ['dominante' => $dom, 'articoli' => $persone,
            'ogni_mille_parole' => ['tu' => aic_stile_media(array_map(fn($x) => $x['persona']['ogni_mille']['tu'], $m)), 'voi' => aic_stile_media(array_map(fn($x) => $x['persona']['ogni_mille']['voi'], $m)), 'noi' => aic_stile_media(array_map(fn($x) => $x['persona']['ogni_mille']['noi'], $m))]],
        'ricorrenti' => aic_stile_ricorrenti($perArt),
        'attacchi_tipici' => $attacchi,
        'chiusure_tipiche' => $chiusure,
        'grassetti' => ['per_articolo' => aic_stile_media($col('grassetti')), 'articoli_con_grassetti_percento' => $quota(fn($x) => $x['grassetti'] > 0)],
        'emoji' => ['per_articolo' => aic_stile_media($col('emoji')), 'articoli_con_emoji_percento' => $quota(fn($x) => $x['emoji'] > 0), 'nota' => 'Se ne usi, scrivili come entità HTML (per esempio &#127793;): il database non conserva i caratteri a 4 byte.'],
        'link_interni' => ['per_articolo' => aic_stile_media($col('link_interni')), 'articoli_senza_link_percento' => $quota(fn($x) => $x['link_interni'] === 0)],
        'immagini' => ['per_articolo' => aic_stile_media($col('immagini')), 'articoli_senza_immagini_percento' => $quota(fn($x) => $x['immagini'] === 0)],
        'domande_frequenti' => ['articoli_con_domande_frequenti_percento' => $quota(fn($x) => $x['domande_frequenti'] > 0), 'per_articolo' => aic_stile_media($col('domande_frequenti'))],
        'categorie' => $catOut, 'tag' => $tagOut,
        'titoli' => ['caratteri_media' => aic_stile_media($titoli), 'caratteri_mediana' => aic_stile_mediana($titoli), 'titolo_google_media' => aic_stile_media($titoliSeo),
            'descrizione_google_media' => aic_stile_media($descr), 'articoli_con_descrizione_percento' => (int)round(100 * count($descr) / $n)],
        'widget_editor' => ['articoli_con_editor' => $editor, 'articoli_classici' => $n - $editor, 'sequenze_piu_comuni' => $seqOut, 'primo_widget' => $primoOut, 'widget_piu_usati' => $tipiOut],
        'estratti' => $estratti,
        'criterio_estratti' => 'Per ogni articolo, dai più recenti, il paragrafo (almeno 15 parole) con la lunghezza più vicina al paragrafo medio del sito' . ($dom !== 'impersonale' ? ' che usa la persona dominante (' . $dom . ')' : '') . '; al massimo uno per articolo e cinque in tutto, tagliati a ' . AIC_STILE_ESTRATTO_MAX . ' caratteri.',
        'come_leggerlo' => aic_stile_come_leggerlo(),
    ];
}

function aic_stile_come_leggerlo(): array {
    return [
        'Tutte le misure sono contate dal sito sugli articoli pubblicati più recenti (al massimo ' . AIC_STILE_MAX_ARTICOLI . '; con "salta" leggi i precedenti). Nessuna è inventata o stimata da un modello.',
        'Il testo di un articolo fatto con l\'editor visuale è quello dei widget Testo e Titolo più i testi degli altri widget (riquadri, elenchi, inviti); per gli altri è il testo della scheda.',
        'Parole: sequenze di lettere e numeri. Frasi: si spezza dopo punto, punto esclamativo, punto interrogativo e puntini, quando segue una maiuscola. Paragrafi: i blocchi <p> con almeno tre parole.',
        'Persona: si contano tu/ti/te/tuo/tua/tuoi/tue e verbi come puoi, devi, sei, hai (seconda persona), voi/vi/vostro e potete, avete (voi), noi/nostro e possiamo, abbiamo, siamo (prima plurale). Un articolo ha quella persona se le sue parole superano ' . AIC_STILE_SOGLIA_PERSONA . ' ogni mille; altrimenti è impersonale. "Ci" non conta (è ambiguo).',
        'Domande ai lettori: le frasi che finiscono con il punto interrogativo. Attacco: le prime due frasi del primo paragrafo; chiusura: le ultime due dell\'ultimo.',
        'Parole ricorrenti: le più frequenti escluse le parole vuote dell\'italiano, presenti in almeno due articoli; espressioni: sequenze di due o tre parole che non finiscono con una parola vuota.',
        'Link interni: i link verso un percorso del sito (/...) o il suo indirizzo, più i campi link dei widget. Immagini: <img> nel testo, i widget di immagini e l\'immagine in evidenza.',
        'Domande frequenti: quelle della scheda più i sottotitoli H2 e H3 che finiscono con il punto interrogativo.',
        'Widget dell\'editor: la sequenza dei tipi di widget di ogni articolo (uguali di fila contati una volta); le più comuni dicono la struttura tipica.',
        'Usa queste misure per scrivere il profilo con save_style_profile: tono, persona, lessico da usare ed evitare, struttura tipo, regole ed esempi.',
    ];
}

/** Le misure da conservare con il profilo: tutto tranne gli esempi lunghi (attacchi, chiusure ed estratti si rileggono con analyze_style). */
function aic_stile_misure_breve(array $an): array {
    $keep = ['articoli_misurati', 'articoli_pubblicati', 'calcolato', 'lunghezza', 'frasi', 'paragrafi', 'sottotitoli', 'elenchi', 'domande_ai_lettori', 'persona', 'grassetti', 'emoji', 'link_interni', 'immagini', 'domande_frequenti', 'titoli', 'categorie', 'tag'];
    $out = array_intersect_key($an, array_flip($keep));
    if (isset($an['ricorrenti'])) $out['ricorrenti'] = ['parole' => array_slice($an['ricorrenti']['parole'], 0, 15), 'espressioni_di_2' => array_slice($an['ricorrenti']['espressioni_di_2'], 0, 10)];
    if (isset($an['widget_editor'])) $out['widget_editor'] = ['articoli_con_editor' => $an['widget_editor']['articoli_con_editor'], 'sequenze_piu_comuni' => array_slice($an['widget_editor']['sequenze_piu_comuni'], 0, 3)];
    unset($out['emoji']['nota']);
    return $out;
}

// ------------------------------------------------------------
// Il profilo (tabella ai_stile: una riga per versione, l'ultima è in uso)
// ------------------------------------------------------------

/** Il profilo in uso, o null. Con 'misure' decodificate. */
function aic_stile_profilo(?int $id = null): ?array {
    aic_schema();
    if ($id !== null) { $st = db()->prepare('SELECT * FROM ' . tbl('ai_stile') . ' WHERE id = ?'); $st->execute([$id]); $r = $st->fetch(); }
    else $r = db()->query('SELECT * FROM ' . tbl('ai_stile') . ' ORDER BY id DESC LIMIT 1')->fetch();
    if (!$r) return null;
    $r['misure'] = json_decode((string)$r['misure'], true) ?: [];
    return $r;
}

/** Le versioni precedenti (senza quella in uso), le più recenti prima. */
function aic_stile_versioni(): array {
    aic_schema();
    $righe = db()->query('SELECT id, autore, origine, creato, CHAR_LENGTH(testo) AS caratteri, LEFT(testo, 200) AS inizio FROM ' . tbl('ai_stile') . ' ORDER BY id DESC LIMIT ' . (AIC_STILE_VERSIONI + 1))->fetchAll();
    return array_slice($righe, 1);
}

/** Il testo del profilo pulito: niente HTML, niente caratteri a 4 byte, al massimo 8000 caratteri. Lancia se vuoto. */
function aic_stile_testo_pulito($s): string {
    if (!is_scalar($s)) throw new AicErrore('Il profilo deve essere un testo.');
    if (mb_strlen((string)$s) > AIC_STILE_PROFILO_MAX * 2) throw new AicErrore('Il profilo è troppo lungo: al massimo ' . AIC_STILE_PROFILO_MAX . ' caratteri. Tieni le regole essenziali e gli esempi più utili.');
    $t = aic_testo($s, AIC_STILE_PROFILO_MAX, true);
    $t = trim((string)preg_replace("/\n{3,}/", "\n\n", $t));
    if (mb_strlen($t) < 40) throw new AicErrore('Il profilo è vuoto o troppo corto (almeno 40 caratteri di testo semplice, senza HTML): descrivi tono, persona, lessico, struttura e regole.');
    return $t;
}

/** Salva una versione nuova del profilo e tiene le ultime dieci precedenti. Ritorna l'id. */
function aic_stile_salva(string $testo, array $misure, string $autore, int $connessione, string $origine): int {
    aic_schema();
    db()->prepare('INSERT INTO ' . tbl('ai_stile') . ' (testo, misure, autore, connessione_id, origine, creato) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$testo, json_encode($misure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), mb_substr($autore, 0, 200), $connessione, $origine]);
    $id = (int)db()->lastInsertId();
    $soglia = (int)db()->query('SELECT id FROM ' . tbl('ai_stile') . ' ORDER BY id DESC LIMIT ' . AIC_STILE_VERSIONI . ', 1')->fetchColumn();
    if ($soglia > 0) db()->prepare('DELETE FROM ' . tbl('ai_stile') . ' WHERE id < ?')->execute([$soglia]);
    return $id;
}

/** Ripristina una versione precedente: diventa una versione nuova, con lo stesso testo e le stesse misure. */
function aic_stile_ripristina(int $id, string $chi): int {
    $p = aic_stile_profilo($id);
    if (!$p) throw new RuntimeException('Versione non trovata: forse è già uscita dalle ultime dieci.');
    return aic_stile_salva((string)$p['testo'], (array)$p['misure'], $chi, 0, 'ripristino');
}

/** Il profilo come lo restituisce get_style_profile. */
function aic_stile_profilo_per_ai(?array $p): array {
    if (!$p) return ['profilo' => null, 'nota' => 'Questo sito non ha ancora un profilo di stile. Chiama analyze_style, leggi le misure e gli estratti, poi scrivi il profilo con save_style_profile.'];
    return ['profilo' => (string)$p['testo'], 'salvato' => (string)$p['creato'], 'salvato_da' => (string)$p['autore'], 'origine' => (string)$p['origine'], 'versione' => (int)$p['id'],
        'misure' => $p['misure'], 'versioni_precedenti' => count(aic_stile_versioni()),
        'nota' => 'Scrivi gli articoli nuovi seguendo questo profilo. create_draft e update_draft ti dicono dove la bozza si allontana dalle misure.'];
}

// ------------------------------------------------------------
// Coerenza di una bozza con il profilo (solo avvisi)
// ------------------------------------------------------------

/** Il controllo di coerenza di una bozza (articoli) con il profilo salvato. Null se non c'è un profilo o non è un articolo. */
function aic_stile_coerenza(string $tipo, int $id): ?array {
    if ($tipo !== 'articoli') return null;
    $p = aic_stile_profilo();
    if (!$p || empty($p['misure']['lunghezza'])) return null;
    $r = contenuti_leggi($tipo, $id);
    if (!$r) return null;
    $x = aic_stile_misura($tipo, $r, true);
    $pm = $p['misure'];
    $sc = [];
    $med = (float)($pm['lunghezza']['parole_mediana'] ?? 0);
    if ($med > 0 && $x['parole'] > 0) {
        $d = ($x['parole'] - $med) / $med;
        if (abs($d) > 0.4) $sc[] = ['misura' => 'lunghezza', 'bozza' => $x['parole'], 'sito' => $med, 'avviso' => 'La bozza ha ' . $x['parole'] . ' parole; gli articoli del sito ne hanno ' . (int)$med . ' (mediana): ' . ($d > 0 ? 'è più lunga' : 'è più corta') . ' del ' . (int)round(abs($d) * 100) . '%.'];
    }
    $dom = (string)($pm['persona']['dominante'] ?? '');
    if ($dom !== '' && $x['parole'] >= 60 && $x['persona']['persona'] !== $dom) $sc[] = ['misura' => 'persona', 'bozza' => $x['persona']['persona'], 'sito' => $dom, 'avviso' => 'Gli articoli del sito sono scritti ' . aic_stile_persona_testo($dom) . '; la bozza è scritta ' . aic_stile_persona_testo($x['persona']['persona']) . '.'];
    $fm = (float)($pm['frasi']['parole_per_frase_media'] ?? 0);
    if ($fm > 0 && $x['frasi'] >= 3) {
        $d = ($x['parole_per_frase'] - $fm) / $fm;
        if (abs($d) > 0.35) $sc[] = ['misura' => 'frase media', 'bozza' => $x['parole_per_frase'], 'sito' => $fm, 'avviso' => 'Le frasi della bozza hanno in media ' . $x['parole_per_frase'] . ' parole; quelle del sito ' . $fm . ': ' . ($d > 0 ? 'accorciale' : 'sono più brevi del solito') . '.'];
    }
    $h2 = (float)($pm['sottotitoli']['h2_per_articolo'] ?? 0);
    if ($h2 >= 1.5 && $x['h2'] === 0) $sc[] = ['misura' => 'sottotitoli', 'bozza' => 0, 'sito' => $h2, 'avviso' => 'La bozza non ha sottotitoli H2; gli articoli del sito ne hanno in media ' . $h2 . '.'];
    elseif ($h2 > 0 && $x['h2'] > max(4, $h2 * 2.5)) $sc[] = ['misura' => 'sottotitoli', 'bozza' => $x['h2'], 'sito' => $h2, 'avviso' => 'La bozza ha ' . $x['h2'] . ' sottotitoli H2; gli articoli del sito ne hanno in media ' . $h2 . '.'];
    $li = (float)($pm['link_interni']['per_articolo'] ?? 0);
    if ($li >= 0.5 && $x['link_interni'] === 0) $sc[] = ['misura' => 'link interni', 'bozza' => 0, 'sito' => $li, 'avviso' => 'La bozza non ha link interni; gli articoli del sito ne hanno in media ' . $li . ' (vedi link_interni_consigliati di suggest_topics o review_article).'];
    $faq = (int)($pm['domande_frequenti']['articoli_con_domande_frequenti_percento'] ?? 0);
    if ($faq >= 50 && $x['domande_frequenti'] === 0) $sc[] = ['misura' => 'domande frequenti', 'bozza' => 0, 'sito' => $faq . '%', 'avviso' => 'Il ' . $faq . '% degli articoli del sito ha domande frequenti; la bozza no (campo seo.domande_frequenti).'];
    $dq = (int)($pm['domande_ai_lettori']['articoli_con_domande_percento'] ?? 0);
    if ($dq >= 60 && $x['domande'] === 0 && $x['parole'] >= 60) $sc[] = ['misura' => 'domande ai lettori', 'bozza' => 0, 'sito' => $dq . '%', 'avviso' => 'Il ' . $dq . '% degli articoli del sito fa domande a chi legge; la bozza nessuna.'];
    return [
        'profilo_del' => (string)$p['creato'],
        'bozza' => ['parole' => $x['parole'], 'parole_per_frase' => $x['parole_per_frase'], 'persona' => $x['persona']['persona'], 'h2' => $x['h2'], 'h3' => $x['h3'], 'link_interni' => $x['link_interni'], 'domande_frequenti' => $x['domande_frequenti'], 'domande' => $x['domande']],
        'scostamenti' => $sc,
        'nota' => $sc ? 'Sono avvisi, non errori: la bozza è salvata. Correggila con update_draft se lo scostamento non è voluto.' : 'La bozza è in linea con le misure del profilo di stile.',
    ];
}

function aic_stile_persona_testo(string $p): string {
    return ['tu' => 'dando del tu', 'voi' => 'dando del voi', 'noi' => 'con il noi', 'impersonale' => 'in modo impersonale'][$p] ?? $p;
}
