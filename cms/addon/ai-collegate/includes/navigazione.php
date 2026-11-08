<?php
/**
 * Addon AI collegate (2.33.0): la navigazione. Legge tutti i menu, header e footer del Theme Builder e i link delle pagine
 * dei menu, risolve ogni voce verso il suo contenuto e dice cosa non va (voci rotte, doppioni, etichette lunghe o
 * incoerenti, troppi livelli, troppe voci, pagine orfane o solo nel piede, briciole, Contatti, blog, inviti finali).
 * Poi le due proposte della navigazione: un menu intero (tipo "menu", approvata = menu_salva(), con il controllo che il menu
 * non sia cambiato nel frattempo) e i blocchi in fondo a una pagina (Leggi anche e un invito verso Contatti).
 * Una query per tabella, mai una per voce. Manuale tecnico, 47.11-47.13.
 */
require_once __DIR__ . '/ai.php';
require_once cms_radice() . '/includes/contenuti.php';
require_once cms_radice() . '/includes/menu-nav.php';

/** Oltre questi caratteri un'etichetta di menu è lunga (al telefono va a capo). */
const AIC_NAV_ETICHETTA_MAX = 25;
/** Oltre queste voci al primo livello un menu è affollato. */
const AIC_NAV_PRIMO_LIVELLO_MAX = 7;
/** Oltre questa profondità (livelli contati da 1) una voce è difficile da raggiungere, soprattutto al telefono. */
const AIC_NAV_PROFONDITA_MAX = 2;
/** I widget che chiudono una pagina con un invito all'azione. */
const AIC_NAV_WIDGET_INVITO = ['cta', 'invito_libero', 'riquadro_test', 'pulsante', 'pulsanti', 'modulo', 'richiesta_prenotazione', 'tabella_prezzi', 'barra_contatti'];
/** Le chiavi delle impostazioni dei widget che contengono un link. */
const AIC_NAV_CHIAVI_LINK = '~(^|_)(link|url|href)$~';

// ------------------------------------------------------------
// L'indice del sito: una query per tabella, una volta per richiesta
// ------------------------------------------------------------

/**
 * Pagine e articoli (anche in bozza e nel cestino), categorie, home e blog. Ritorna:
 *   contenuti  "pagine:3" => [tipo, id, titolo, slug, percorso, stato (pubblicato|bozza|programmato|cestino), riga]
 *   slug       "pagine" => [slug => chiave], "articoli" => [...]
 *   percorsi   "/chi-siamo/" => chiave (solo pubblicati e bozze, per sapere dove porta un link scritto a mano)
 *   categorie  slug => nome
 */
function aic_nav_indice(bool $forza = false): array {
    static $ix = null;
    if ($ix !== null && !$forza) return $ix;
    $ix = ['contenuti' => [], 'slug' => ['pagine' => [], 'articoli' => []], 'percorsi' => [], 'categorie' => []];
    foreach (['pagine', 'articoli'] as $tipo) {
        foreach (db()->query('SELECT * FROM ' . tbl(contenuti_tipo($tipo)['tabella']))->fetchAll() as $r) {
            $stato = !empty($r['cestino']) ? 'cestino' : contenuti_stato($tipo, $r);
            $k = $tipo . ':' . (int)$r['id'];
            $p = contenuti_percorso($tipo, $r);
            $ix['contenuti'][$k] = ['tipo' => $tipo, 'id' => (int)$r['id'], 'titolo' => (string)$r['title'], 'slug' => (string)$r['slug'], 'percorso' => $p, 'stato' => $stato, 'riga' => $r];
            // Un contenuto vivo vince su uno nel cestino con lo stesso slug.
            if (!isset($ix['slug'][$tipo][(string)$r['slug']]) || $stato !== 'cestino') $ix['slug'][$tipo][(string)$r['slug']] = $k;
            if ($stato !== 'cestino' && (!isset($ix['percorsi'][$p]) || $stato === 'pubblicato')) $ix['percorsi'][$p] = $k;
        }
    }
    foreach (db()->query('SELECT slug, name FROM ' . tbl('categories'))->fetchAll() as $c) $ix['categorie'][(string)$c['slug']] = (string)$c['name'];
    return $ix;
}

/** Il percorso interno di un indirizzo del sito ("/chi-siamo/"), oppure null se è esterno o non è un percorso. */
function aic_nav_percorso_interno(string $url): ?string {
    $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
    if ($url === '') return null;
    if (preg_match('~^https?://~i', $url)) {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host !== strtolower((string)parse_url(cms_url_sito(), PHP_URL_HOST))) return null;
        $p = (string)parse_url($url, PHP_URL_PATH);
    } elseif ($url[0] === '/' && !str_starts_with($url, '//')) {
        $p = (string)parse_url($url, PHP_URL_PATH);
    } else return null;
    $base = cms_sito_base();
    if ($base !== '' && ($p === $base || str_starts_with($p, $base . '/'))) $p = substr($p, strlen($base)) ?: '/';
    $p = rawurldecode($p === '' ? '/' : $p);
    if (!preg_match('~\.[a-z0-9]{2,5}$~i', $p)) $p = rtrim($p, '/') . '/';
    return $p;
}

/**
 * Dove porta una voce di menu o un link. Ritorna [esito, chiave, url, destinazione]:
 *   esito  ok | bozza | programmato | cestino | inesistente | esterno | ancora | contatto | sistema
 *   chiave "pagine:3", "articoli:9", "categoria:news", "esterno:<url>", "percorso:/x/" (per i doppioni e i confronti)
 */
function aic_nav_risolvi(array $x): array {
    $ix = aic_nav_indice();
    $tipo = (string)($x['tipo'] ?? 'link');
    $rif = (string)($x['rif'] ?? '');
    if ($tipo === 'categoria' && $rif !== '') {
        $ok = isset($ix['categorie'][$rif]);
        return ['esito' => $ok ? 'ok' : 'inesistente', 'chiave' => 'categoria:' . $rif, 'url' => '/category/' . $rif . '/', 'destinazione' => ['tipo' => 'categoria', 'slug' => $rif, 'titolo' => $ix['categorie'][$rif] ?? '']];
    }
    if (in_array($tipo, ['pagina', 'articolo'], true) && $rif !== '') {
        $t = $tipo === 'pagina' ? 'pagine' : 'articoli';
        $k = $ix['slug'][$t][$rif] ?? null;
        if (!$k) return ['esito' => 'inesistente', 'chiave' => $t . ':' . $rif, 'url' => menu_url_voce($x + ['url' => '']), 'destinazione' => null];
        $c = $ix['contenuti'][$k];
        return ['esito' => $c['stato'] === 'pubblicato' ? 'ok' : $c['stato'], 'chiave' => $k, 'url' => $c['percorso'], 'destinazione' => aic_nav_dest($c)];
    }
    $url = trim((string)($x['url'] ?? ''));
    return aic_nav_risolvi_url($url);
}

function aic_nav_dest(array $c): array {
    return ['tipo' => $c['tipo'], 'id' => $c['id'], 'titolo' => $c['titolo'], 'percorso' => $c['percorso'], 'stato' => $c['stato']];
}

function aic_nav_risolvi_url(string $url): array {
    $ix = aic_nav_indice();
    if ($url === '' || $url === '#') return ['esito' => 'inesistente', 'chiave' => 'vuoto', 'url' => $url, 'destinazione' => null];
    if ($url[0] === '#') return ['esito' => 'ancora', 'chiave' => 'ancora:' . $url, 'url' => $url, 'destinazione' => null];
    if (preg_match('~^(mailto|tel):~i', $url)) return ['esito' => 'contatto', 'chiave' => 'contatto:' . strtolower($url), 'url' => $url, 'destinazione' => null];
    $p = aic_nav_percorso_interno($url);
    if ($p === null) return ['esito' => preg_match('~^https?://~i', $url) ? 'esterno' : 'inesistente', 'chiave' => 'esterno:' . $url, 'url' => $url, 'destinazione' => null];
    if (isset($ix['percorsi'][$p])) {
        $c = $ix['contenuti'][$ix['percorsi'][$p]];
        return ['esito' => $c['stato'] === 'pubblicato' ? 'ok' : $c['stato'], 'chiave' => $ix['percorsi'][$p], 'url' => $url, 'destinazione' => aic_nav_dest($c)];
    }
    if (preg_match('~^/category/([a-z0-9-]+)/$~', $p, $m)) return ['esito' => isset($ix['categorie'][$m[1]]) ? 'ok' : 'inesistente', 'chiave' => 'categoria:' . $m[1], 'url' => $url, 'destinazione' => ['tipo' => 'categoria', 'slug' => $m[1], 'titolo' => $ix['categorie'][$m[1]] ?? '']];
    // Le pagine del sito che non sono contenuti (blog, archivi, pagine dei moduli e degli addon), i redirect e i file.
    $mappa = function_exists('sito_mappa') ? sito_mappa() : [];
    $redirect = function_exists('sito_redirect') ? array_column(sito_redirect(), 'da') : [];
    $sistema = isset($mappa[$p]) || isset($mappa[rtrim($p, '/')]) || in_array($p, $redirect, true) || in_array(rtrim($p, '/'), $redirect, true) || preg_match('~^/\d{4}/~', $p);
    if (!$sistema && defined('AIC_PERCORSI_SPECIALI')) foreach (AIC_PERCORSI_SPECIALI as $pre) if (str_starts_with($p, $pre)) { $sistema = true; break; }
    if (!$sistema && defined('SITO_PAGINA_BLOG') && $p === SITO_PAGINA_BLOG) $sistema = true;
    if (!$sistema && preg_match('~\.[a-z0-9]{2,5}$~i', $p) && !str_contains($p, '..') && (is_file(cms_radice() . $p) || is_file(dirname(cms_uploads_dir()) . $p))) $sistema = true;
    return ['esito' => $sistema ? 'sistema' : 'inesistente', 'chiave' => 'percorso:' . $p, 'url' => $url, 'destinazione' => null];
}

/** Le frasi italiane degli esiti che non vanno bene. */
function aic_nav_esito_testo(string $e): string {
    return ['bozza' => 'porta a un contenuto ancora in bozza (chi clicca trova "pagina non trovata")', 'programmato' => 'porta a un contenuto programmato, non ancora uscito',
        'cestino' => 'porta a un contenuto nel cestino', 'inesistente' => 'non porta a nessuna pagina del sito (404)'][$e] ?? $e;
}

// ------------------------------------------------------------
// Link dentro un contenuto o un modello (albero dell'editor o testo della scheda)
// ------------------------------------------------------------

/** Tutti i link di un albero decodificato o di un HTML: elenco di stringhe (indirizzi come scritti). */
function aic_nav_link_da_valore($v, string $chiave = '', array &$out = []): array {
    if (is_array($v)) { foreach ($v as $k => $x) aic_nav_link_da_valore($x, is_string($k) ? $k : $chiave, $out); return $out; }
    if (!is_string($v) || $v === '') return $out;
    if (preg_match(AIC_NAV_CHIAVI_LINK, $chiave) && preg_match('~^(/|https?://|#|mailto:|tel:)~i', trim($v))) $out[] = trim($v);
    if (str_contains($v, 'href') && preg_match_all('~href\s*=\s*["\']([^"\']+)["\']~i', $v, $m)) foreach ($m[1] as $u) $out[] = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
    return $out;
}

/** I link (pubblicati) di una riga di contenuto: dall'albero pubblicato se c'è, altrimenti dal testo della scheda. */
function aic_nav_link_contenuto(string $tipo, array $r): array {
    $json = trim((string)($r['struttura'] ?? ''));
    if ($json !== '') { $a = json_decode($json, true); return is_array($a) ? aic_nav_link_da_valore($a) : []; }
    return aic_nav_link_da_valore((string)($r[contenuti_tipo($tipo)['campo_contenuto']] ?? ''), 'html');
}

/** I widget di un albero decodificato, in ordine: [sezione => n, tipo, id, impostazioni]. */
function aic_nav_widget_albero(array $a): array {
    $out = [];
    foreach ((array)($a['elementi'] ?? []) as $i => $s) foreach ((array)($s['figli'] ?? []) as $c) foreach ((array)($c['figli'] ?? []) as $w) if (is_array($w))
        $out[] = ['sezione' => $i, 'tipo' => (string)($w['tipo'] ?? ''), 'id' => (string)($w['id'] ?? ''), 'impostazioni' => is_array($w['impostazioni'] ?? null) ? $w['impostazioni'] : []];
    return $out;
}

/** La pagina finisce con un invito all'azione? (l'ultima sezione ha un widget di invito, o un link verso Contatti). */
function aic_nav_ha_invito(string $tipo, array $r): bool {
    $json = trim((string)($r['struttura'] ?? ''));
    if ($json !== '') {
        $a = json_decode($json, true);
        $ws = is_array($a) ? aic_nav_widget_albero($a) : [];
        if (!$ws) return false;
        $ultima = max(array_column($ws, 'sezione'));
        foreach ($ws as $w) {
            if ($w['sezione'] !== $ultima) continue;
            if (in_array($w['tipo'], AIC_NAV_WIDGET_INVITO, true)) return true;
            foreach (aic_nav_link_da_valore($w['impostazioni']) as $u) if (preg_match('~contatt|contact|prenot|mailto:|tel:~i', $u)) return true;
        }
        return false;
    }
    $h = (string)($r[contenuti_tipo($tipo)['campo_contenuto']] ?? '');
    $coda = mb_substr($h, max(0, mb_strlen($h) - 600));
    return (bool)preg_match('~href\s*=\s*["\'][^"\']*(contatt|contact|prenot|mailto:|tel:)~i', $coda);
}

// ------------------------------------------------------------
// Etichette
// ------------------------------------------------------------

/** Lo stile delle maiuscole di un'etichetta: MAIUSCOLO, minuscolo, Ogni Parola, Frase, oppure '' (una parola sola: vale tutto). */
function aic_nav_stile_etichetta(string $e): string {
    $lettere = preg_replace('/[^\p{L}]/u', '', $e);
    if ($lettere === '') return '';
    if (mb_strlen($lettere) > 1 && mb_strtoupper($lettere) === $lettere) return 'MAIUSCOLO';
    if (mb_strtolower($lettere) === $lettere) return 'minuscolo';
    $piccole = ['di', 'e', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'del', 'della', 'dei', 'delle', 'al', 'alla', 'ai', 'chi', 'come', 'cosa', 'the', 'of', 'and'];
    // Le parole dopo la prima, senza articoli e preposizioni: tutte maiuscole = Ogni Parola, una minuscola = Frase.
    $tutte = array_values(array_filter(preg_split('/[\s\-\/]+/u', trim($e)), fn($p) => preg_match('/\p{L}/u', $p)));
    $dopo = array_values(array_filter(array_slice($tutte, 1), fn($p) => !in_array(mb_strtolower($p), $piccole, true)));
    if (!$dopo) return '';
    foreach ($dopo as $p) { $prima = mb_substr(preg_replace('/^[^\p{L}]+/u', '', $p), 0, 1); if ($prima !== '' && mb_strtolower($prima) === $prima) return 'Frase'; }
    return 'Ogni Parola';
}

// ------------------------------------------------------------
// L'analisi
// ------------------------------------------------------------

/** Dove esce un menu: header, footer, telefono, oppure da nessuna parte. $usati: id => [dove...] dai widget dei modelli. */
function aic_nav_dove_menu(array $m, array $usati): array {
    $dove = $usati[(int)$m['id']] ?? [];
    $pos = (string)$m['posizione'];
    if ($pos === 'principale') $dove[] = 'header';
    elseif ($pos === 'telefono') $dove[] = 'telefono';
    elseif (str_starts_with($pos, 'piede')) $dove[] = 'footer';
    return array_values(array_unique($dove));
}

/**
 * analyze_navigation. Ritorna l'analisi completa (vedi la descrizione dello strumento). Le pagine dei menu si leggono
 * dall'indice (già in memoria), i link dalla colonna struttura: nessuna query per voce.
 */
function aic_nav_analizza(): array {
    $ix = aic_nav_indice(true);
    require_once cms_radice() . '/includes/modelli.php';
    // 1. Header e footer pubblicati: quali menu mostrano, quali link hanno, se hanno le briciole di pane.
    $modelli = [];
    $usati = [];
    $briciole = [];
    $linkModelli = ['header' => [], 'footer' => []];
    foreach (db()->query('SELECT id, tipo, nome, status, cestino, predefinito, struttura FROM ' . tbl('modelli'))->fetchAll() as $md) {
        if ($md['status'] !== 'published' || !empty($md['cestino']) || trim((string)$md['struttura']) === '') continue;
        $a = json_decode((string)$md['struttura'], true);
        if (!is_array($a)) continue;
        $ws = aic_nav_widget_albero($a);
        $menuQui = []; $linkQui = [];
        foreach ($ws as $w) {
            if ($w['tipo'] === 'menu_nav' && (int)($w['impostazioni']['menu'] ?? 0) > 0) $menuQui[] = (int)$w['impostazioni']['menu'];
            if ($w['tipo'] === 'breadcrumb') $briciole[] = MODELLI_TIPI[$md['tipo']] ?? $md['tipo'];
            foreach (aic_nav_link_da_valore($w['impostazioni']) as $u) $linkQui[] = $u;
        }
        $dove = in_array($md['tipo'], ['header', 'footer'], true) ? $md['tipo'] : '';
        foreach ($menuQui as $mid) if ($dove !== '') $usati[$mid][] = $dove;
        $linkRisolti = [];
        foreach (array_unique($linkQui) as $u) { $r = aic_nav_risolvi_url($u); $linkRisolti[] = ['url' => $u, 'esito' => $r['esito'], 'destinazione' => $r['destinazione']]; if ($dove !== '') $linkModelli[$dove][] = $r; }
        $modelli[] = ['id' => (int)$md['id'], 'tipo' => (string)$md['tipo'], 'nome' => (string)$md['nome'], 'predefinito' => (bool)$md['predefinito'], 'menu' => array_values(array_unique($menuQui)), 'link' => $linkRisolti];
    }
    // 2. I menu, voce per voce.
    $menu = []; $problemi = ['voci_rotte' => [], 'doppioni' => [], 'etichette_lunghe' => [], 'maiuscole_miste' => [], 'nomi_diversi' => [], 'troppo_profonde' => [], 'troppe_voci_primo_livello' => []];
    $raggiunte = ['header' => [], 'footer' => [], 'telefono' => [], 'altro' => []]; // chiave del contenuto => true
    $etichettePerChiave = [];
    $contatti = null; $blogVoce = null;
    $paginaBlog = defined('SITO_PAGINA_BLOG') ? SITO_PAGINA_BLOG : '/blog/';
    $menuInUso = [];
    foreach (menu_tutti() as $m) {
        $dove = aic_nav_dove_menu($m, $usati);
        $inUso = (bool)$dove;
        if ($inUso) $menuInUso[(int)$m['id']] = true;
        $stili = []; $visti = []; $nVoci = 0; $prof = 0;
        $giro = function (array $voci, int $livello) use (&$giro, $m, $dove, &$problemi, &$raggiunte, &$etichettePerChiave, &$contatti, &$blogVoce, &$stili, &$visti, &$nVoci, &$prof, $paginaBlog, $ix): array {
            $out = [];
            foreach ($voci as $x) {
                $nVoci++;
                $prof = max($prof, $livello + 1);
                $r = aic_nav_risolvi($x);
                $et = (string)$x['etichetta'];
                $voce = ['etichetta' => $et, 'tipo' => (string)$x['tipo'], 'url' => $r['url'], 'esito' => $r['esito'], 'destinazione' => $r['destinazione'], 'livello' => $livello + 1, 'figli' => []];
                $dove1 = ['menu' => (string)$m['nome'], 'menu_id' => (int)$m['id'], 'etichetta' => $et, 'url' => $r['url']];
                if (in_array($r['esito'], ['bozza', 'programmato', 'cestino', 'inesistente'], true)) $problemi['voci_rotte'][] = $dove1 + ['esito' => $r['esito'], 'motivo' => aic_nav_esito_testo($r['esito'])];
                if (isset($visti[$r['chiave']]) && !in_array($r['esito'], ['ancora'], true)) $problemi['doppioni'][] = $dove1 + ['prima_volta' => $visti[$r['chiave']]];
                else $visti[$r['chiave']] = $et;
                if (mb_strlen($et) > AIC_NAV_ETICHETTA_MAX) $problemi['etichette_lunghe'][] = $dove1 + ['caratteri' => mb_strlen($et)];
                if ($livello + 1 > AIC_NAV_PROFONDITA_MAX) $problemi['troppo_profonde'][] = $dove1 + ['livello' => $livello + 1];
                $st = aic_nav_stile_etichetta($et);
                if ($st !== '') $stili[$st][] = $et;
                if ($dove && !in_array($r['esito'], ['esterno', 'ancora', 'contatto', 'inesistente'], true)) {
                    foreach ($dove as $d) $raggiunte[$d][$r['chiave']] = true;
                    $etichettePerChiave[$r['chiave']][] = ['menu' => (string)$m['nome'], 'dove' => implode(', ', $dove), 'etichetta' => $et];
                }
                $dest = $r['destinazione'];
                if (!$contatti && (preg_match('~contatt|contact|scrivi|dove siamo~i', $et) || preg_match('~contatt|contact~i', (string)($dest['percorso'] ?? $r['url'])) || $r['esito'] === 'contatto')) $contatti = $dove1;
                if (!$blogVoce && (rtrim((string)($dest['percorso'] ?? $r['url']), '/') . '/' === $paginaBlog || ($dest['tipo'] ?? '') === 'categoria' || preg_match('~\b(blog|articoli|notizie|news|novit)~i', $et))) $blogVoce = $dove1;
                $voce['figli'] = $giro((array)($x['figli'] ?? []), $livello + 1);
                $out[] = $voce;
            }
            return $out;
        };
        $albero = $giro(menu_voci_risolte($m), 0);
        $primo = count($m['voci']);
        if ($primo > AIC_NAV_PRIMO_LIVELLO_MAX) $problemi['troppe_voci_primo_livello'][] = ['menu' => (string)$m['nome'], 'menu_id' => (int)$m['id'], 'voci' => $primo, 'massimo' => AIC_NAV_PRIMO_LIVELLO_MAX];
        if (count($stili) > 1) $problemi['maiuscole_miste'][] = ['menu' => (string)$m['nome'], 'menu_id' => (int)$m['id'], 'stili' => array_map(fn($l) => array_slice($l, 0, 4), $stili)];
        $menu[] = ['id' => (int)$m['id'], 'nome' => (string)$m['nome'], 'posizione' => (string)$m['posizione'], 'dove' => $dove ?: ['non usato'],
            'voci_totali' => $nVoci, 'voci_primo_livello' => $primo, 'profondita' => $prof, 'albero' => $albero];
    }
    // Lo stesso contenuto con nomi diversi fra un menu e l'altro (header e footer).
    foreach ($etichettePerChiave as $k => $l) {
        $nomi = array_unique(array_map(fn($x) => mb_strtolower(trim($x['etichetta'])), $l));
        if (count($nomi) > 1) $problemi['nomi_diversi'][] = ['destinazione' => isset($ix['contenuti'][$k]) ? aic_nav_dest($ix['contenuti'][$k]) : ['chiave' => $k], 'etichette' => $l];
    }
    // Link fissi di header e footer (pulsanti, loghi) contano come voci.
    foreach ($linkModelli as $d => $l) foreach ($l as $r) if (!in_array($r['esito'], ['esterno', 'ancora', 'contatto', 'inesistente'], true)) $raggiunte[$d][$r['chiave']] = true;
    $daHeader = $raggiunte['header'] + $raggiunte['telefono'];
    // 3. Le pagine dei menu: invito finale e link verso le altre pagine dei menu (dalla colonna struttura, già letta).
    $chiaviMenu = array_keys($daHeader + $raggiunte['footer']);
    $pagineMenu = [];
    $daLinkHeader = []; $daLinkFooter = [];
    foreach ($chiaviMenu as $k) {
        if (!isset($ix['contenuti'][$k])) continue;
        $c = $ix['contenuti'][$k];
        $collegate = [];
        foreach (aic_nav_link_contenuto($c['tipo'], $c['riga']) as $u) {
            $r = aic_nav_risolvi_url($u);
            if ($r['esito'] !== 'ok' || $r['chiave'] === $k) continue;
            if (isset($daHeader[$k])) $daLinkHeader[$r['chiave']] = true; else $daLinkFooter[$r['chiave']] = true;
            if (in_array($r['chiave'], $chiaviMenu, true)) $collegate[$r['chiave']] = $ix['contenuti'][$r['chiave']]['titolo'] ?? $r['chiave'];
        }
        if (count($pagineMenu) >= 150) continue;
        $pagineMenu[] = ['tipo' => $c['tipo'], 'id' => $c['id'], 'titolo' => $c['titolo'], 'slug' => $c['slug'], 'percorso' => $c['percorso'], 'stato' => $c['stato'],
            'fatta_con_editor' => trim((string)($c['riga']['struttura'] ?? '')) !== '', 'invito_finale' => $c['percorso'] === $paginaBlog ? null : aic_nav_ha_invito($c['tipo'], $c['riga']),
            'link_verso_pagine_dei_menu' => count($collegate), 'collegate' => array_values(array_slice($collegate, 0, 10))];
    }
    // 4. Pagine orfane e pagine solo nel piede (le pagine pubblicate; gli articoli si trovano dal blog).
    $home = (string)get_setting('pagina_home', '');
    $orfane = []; $soloPiede = [];
    foreach ($ix['contenuti'] as $k => $c) {
        if ($c['tipo'] !== 'pagine' || $c['stato'] !== 'pubblicato' || $c['percorso'] === '/' || $c['slug'] === $home) continue;
        $inHeader = isset($daHeader[$k]) || isset($daLinkHeader[$k]);
        $inPiede = isset($raggiunte['footer'][$k]) || isset($daLinkFooter[$k]);
        if (!$inHeader && !$inPiede) $orfane[] = ['id' => $c['id'], 'titolo' => $c['titolo'], 'percorso' => $c['percorso']];
        elseif (!$inHeader) $soloPiede[] = ['id' => $c['id'], 'titolo' => $c['titolo'], 'percorso' => $c['percorso']];
    }
    $nArticoli = 0;
    foreach ($ix['contenuti'] as $c) if ($c['tipo'] === 'articoli' && $c['stato'] === 'pubblicato') $nArticoli++;
    // 5. Punteggio e suggerimenti.
    $sug = []; $pt = 100;
    $togli = function (int $n, int $peso, int $max) use (&$pt) { $pt -= min($max, $n * $peso); };
    $nr = count($problemi['voci_rotte']);
    if ($nr) { $togli($nr, 12, 40); $sug[] = $nr . ($nr === 1 ? ' voce di menu porta' : ' voci di menu portano') . ' a una pagina che non c\'è o non è pubblicata: correggile o toglile (propose_menu).'; }
    if ($n = count($problemi['doppioni'])) { $togli($n, 5, 15); $sug[] = 'Ci sono voci doppie nello stesso menu: tienine una sola.'; }
    if ($n = count($problemi['troppe_voci_primo_livello'])) { $togli($n, 10, 10); $sug[] = 'Un menu ha più di ' . AIC_NAV_PRIMO_LIVELLO_MAX . ' voci al primo livello: raggruppa le pagine simili sotto una voce (per esempio "Servizi").'; }
    if ($n = count($problemi['troppo_profonde'])) { $togli($n, 4, 12); $sug[] = 'Alcune voci stanno al terzo livello: al telefono si raggiungono a fatica. Tieni al massimo due livelli.'; }
    if ($n = count($problemi['etichette_lunghe'])) { $togli($n, 2, 8); $sug[] = 'Alcune etichette superano ' . AIC_NAV_ETICHETTA_MAX . ' caratteri: usa una o due parole (per esempio "Chi siamo", "Servizi").'; }
    if ($n = count($problemi['maiuscole_miste'])) { $togli($n, 4, 8); $sug[] = 'Le etichette di un menu usano maiuscole diverse ("Chi Siamo" e "Dove siamo"): scegli uno stile, meglio solo la prima lettera maiuscola.'; }
    if ($n = count($problemi['nomi_diversi'])) { $togli($n, 3, 9); $sug[] = 'La stessa pagina ha nomi diversi in menu diversi: chi la cerca non la riconosce. Usa lo stesso nome.'; }
    if ($n = count($orfane)) { $togli($n, 4, 20); $sug[] = $n . ($n === 1 ? ' pagina pubblicata non si raggiunge' : ' pagine pubblicate non si raggiungono') . ' da nessun menu né dalle pagine dei menu: aggiungile a un menu o collegale da una pagina vicina (propose_navigation_fix con "Leggi anche").'; }
    if ($n = count($soloPiede)) { $togli($n, 1, 5); $sug[] = 'Alcune pagine si trovano solo nel piede del sito: se sono importanti, mettile anche nel menu principale.'; }
    if (!$contatti) { $pt -= 10; $sug[] = 'Nessun menu ha una voce verso Contatti: aggiungila in fondo al menu principale.'; }
    if ($nArticoli > 0 && !$blogVoce) { $pt -= 6; $sug[] = 'Il sito ha ' . $nArticoli . ' articoli pubblicati ma nessun menu porta al blog: aggiungi la voce "Blog" (' . $paginaBlog . ').'; }
    if (!$briciole) { $pt -= 4; $sug[] = 'Il sito non mostra le briciole di pane (il percorso Home > pagina). Non c\'è un\'impostazione da accendere: si aggiungono con il widget "Briciole di pane" nell\'header, dal pannello (Editor > Header e footer). Lo fa l\'amministratore: dillo a lui, non si può proporre da qui.'; }
    $senzaInvito = array_values(array_filter($pagineMenu, fn($p) => $p['invito_finale'] === false && $p['percorso'] !== '/'));
    if ($n = count($senzaInvito)) { $togli($n, 2, 10); $sug[] = $n . ($n === 1 ? ' pagina dei menu finisce' : ' pagine dei menu finiscono') . ' senza un invito all\'azione: proponi in fondo un invito verso Contatti (propose_navigation_fix).'; }
    $senzaLink = array_values(array_filter($pagineMenu, fn($p) => $p['link_verso_pagine_dei_menu'] === 0 && $p['percorso'] !== $paginaBlog));
    if ($n = count($senzaLink)) { $togli($n, 1, 8); $sug[] = $n . ($n === 1 ? ' pagina dei menu non collega' : ' pagine dei menu non collegano') . ' nessun\'altra pagina del sito: un blocco "Leggi anche" in fondo aiuta chi legge a continuare.'; }
    $nonUsati = array_values(array_filter($menu, fn($x) => $x['dove'] === ['non usato']));
    if ($nonUsati) $sug[] = 'I menu ' . implode(', ', array_map(fn($x) => '«' . $x['nome'] . '»', $nonUsati)) . ' non escono da nessuna parte (nessuna posizione e nessun widget Menu nell\'header o nel footer).';
    if (!$sug) $sug[] = 'La navigazione è in ordine: menu brevi, voci tutte raggiungibili, Contatti presente.';
    $taglia = fn(array $l) => array_slice($l, 0, 100);
    return [
        'punteggio' => max(0, min(100, $pt)),
        'menu' => $menu,
        'header_e_footer' => $modelli,
        'problemi' => array_map($taglia, $problemi),
        'pagine_orfane' => $taglia($orfane),
        'pagine_solo_nel_piede' => $taglia($soloPiede),
        'briciole_di_pane' => ['presenti' => (bool)$briciole, 'dove' => array_values(array_unique($briciole)), 'impostazione' => false,
            'nota' => $briciole ? 'Il widget Briciole di pane è nel ' . implode(' e nel ', array_unique($briciole)) . '.' : 'Nessuna impostazione da accendere: si aggiungono con il widget Briciole di pane del Theme Builder.'],
        'contatti' => ['presente' => (bool)$contatti, 'voce' => $contatti],
        'blog' => ['articoli_pubblicati' => $nArticoli, 'voce_presente' => (bool)$blogVoce, 'voce' => $blogVoce, 'indirizzo' => $paginaBlog],
        'pagine_dei_menu' => $pagineMenu,
        'suggerimenti' => $sug,
        'limiti' => ['etichetta_max' => AIC_NAV_ETICHETTA_MAX, 'primo_livello_max' => AIC_NAV_PRIMO_LIVELLO_MAX, 'profondita_max' => AIC_NAV_PROFONDITA_MAX, 'voci_max_per_menu' => 200, 'livelli_max_per_menu' => 3],
        '_registro' => 'punteggio ' . max(0, min(100, $pt)) . ', ' . count($menu) . ' menu, ' . $nr . ' voci rotte, ' . count($orfane) . ' orfane',
    ];
}

// ------------------------------------------------------------
// propose_menu: le voci che arrivano dall'AI
// ------------------------------------------------------------

/** Un indirizzo di una voce "link" scritto da un'AI: relativo al sito, https, #ancora, mailto: o tel:. Lancia AicErrore se no. */
function aic_nav_url_sicuro($u, string $dove): string {
    if (!is_string($u)) throw new AicErrore($dove . ': "url" deve essere un testo.');
    $u = trim($u);
    if ($u === '') throw new AicErrore($dove . ': manca "url" (per una pagina usa tipo "pagina" e "rif" con lo slug).');
    if (mb_strlen($u) > 500 || preg_match('~[\x00-\x20\x7F"\'<>\\\\`]~', $u)) throw new AicErrore($dove . ': l\'indirizzo contiene spazi, virgolette o caratteri non ammessi.');
    if (preg_match('~^#[A-Za-z][A-Za-z0-9_-]{0,60}$~', $u)) return $u;
    if (preg_match('~^mailto:[^@\s]+@[^@\s]+\.[a-z]{2,}$~i', $u) && filter_var(substr($u, 7), FILTER_VALIDATE_EMAIL)) return $u;
    if (preg_match('~^tel:\+?[0-9][0-9 .-]{3,24}$~', $u)) return $u;
    if (str_starts_with($u, '/') && !str_starts_with($u, '//') && preg_match('@^/[A-Za-z0-9/_.~%?=&#+-]*$@', $u) && !str_contains($u, '..')) return $u;
    if (preg_match('~^https://~i', $u) && filter_var($u, FILTER_VALIDATE_URL)) {
        $p = parse_url($u);
        if (!empty($p['host']) && empty($p['user']) && empty($p['pass']) && preg_match('~^[a-z0-9.-]+$~i', $p['host'])) return $u;
    }
    throw new AicErrore($dove . ': indirizzo non ammesso ("' . mb_substr(aic_testo($u, 60), 0, 60) . '"). Usa un percorso del sito (/chi-siamo/), un indirizzo https://, #ancora, mailto: o tel:.');
}

/** La chiave di una voce già pulita (per tenere gli id delle voci che c'erano e per il confronto). */
function aic_nav_chiave_voce(array $x): string {
    // Per indirizzo: "Servizi" come pagina e "Servizi" come link a /servizi/ sono la stessa voce.
    if (in_array($x['tipo'], ['pagina', 'articolo', 'categoria'], true) && $x['rif'] !== '') {
        $r = aic_nav_risolvi($x);
        return 'u:' . (aic_nav_percorso_interno((string)$r['url']) ?? $x['tipo'] . ':' . $x['rif']);
    }
    $p = aic_nav_percorso_interno((string)$x['url']);
    return 'u:' . ($p ?? strtolower((string)$x['url']));
}

/**
 * Le voci di propose_menu, controllate una per una prima di menu_pulisci_voci() (che toglierebbe in silenzio il troppo):
 * al massimo 200 voci e tre livelli, etichette di testo, destinazioni che esistono e sono pubblicate. Ritorna le voci nel
 * formato del menu. $ids: chiave => id delle voci di adesso, per riusarli.
 */
function aic_nav_voci_da_ai($voci, array &$ids, int $livello = 0, int &$conta = 0, string $percorso = ''): array {
    if (!is_array($voci) || ($voci && !array_is_list($voci))) throw new AicErrore(($percorso ?: '"voci"') . ' deve essere un elenco di voci.');
    $ix = aic_nav_indice();
    $out = [];
    foreach ($voci as $i => $x) {
        $dove = 'Voce ' . ($percorso !== '' ? $percorso . '.' : '') . ($i + 1);
        if (!is_array($x)) throw new AicErrore($dove . ': ogni voce è un oggetto {etichetta, tipo, rif o url, figli}.');
        if (++$conta > 200) throw new AicErrore('Troppe voci: un menu ne ha al massimo 200.');
        if ($livello > 2) throw new AicErrore($dove . ': un menu ha al massimo tre livelli (consigliati due).');
        $et = aic_testo($x['etichetta'] ?? '', 100);
        if ($et === '') throw new AicErrore($dove . ': manca l\'etichetta (testo semplice, senza HTML).');
        $tipo = (string)($x['tipo'] ?? 'link');
        if (!array_key_exists($tipo, MENU_TIPI_VOCE)) throw new AicErrore($dove . ': tipo "' . mb_substr(aic_testo($tipo, 20), 0, 20) . '" sconosciuto. Ammessi: ' . implode(', ', array_keys(MENU_TIPI_VOCE)) . '.');
        $v = ['etichetta' => $et, 'tipo' => $tipo, 'rif' => '', 'url' => '', 'nuova' => !empty($x['nuova']), 'classi' => '', 'figli' => []];
        if ($tipo === 'pagina' || $tipo === 'articolo') {
            $t = $tipo === 'pagina' ? 'pagine' : 'articoli';
            $k = null;
            if (isset($x['id']) && is_numeric($x['id'])) $k = isset($ix['contenuti'][$t . ':' . (int)$x['id']]) ? $t . ':' . (int)$x['id'] : null;
            elseif (is_string($x['rif'] ?? null) && preg_match('/^[a-z0-9-]{1,190}$/', $x['rif'])) $k = $ix['slug'][$t][$x['rif']] ?? null;
            if (!$k) throw new AicErrore($dove . ' («' . $et . '»): ' . ($tipo === 'pagina' ? 'pagina non trovata' : 'articolo non trovato') . '. Passa "id" (da list_content) oppure "rif" con lo slug.');
            $c = $ix['contenuti'][$k];
            if ($c['stato'] !== 'pubblicato') throw new AicErrore($dove . ' («' . $et . '»): «' . $c['titolo'] . '» è ' . ($c['stato'] === 'cestino' ? 'nel cestino' : 'in ' . $c['stato']) . ': un menu porta solo a contenuti pubblicati.');
            $v['rif'] = $c['slug'];
        } elseif ($tipo === 'categoria') {
            $rif = is_string($x['rif'] ?? null) ? $x['rif'] : '';
            if (!isset($ix['categorie'][$rif])) throw new AicErrore($dove . ' («' . $et . '»): categoria "' . mb_substr(aic_testo($rif, 40), 0, 40) . '" sconosciuta.');
            $v['rif'] = $rif;
        } else {
            $v['url'] = aic_nav_url_sicuro($x['url'] ?? '', $dove . ' («' . $et . '»)');
            $r = aic_nav_risolvi_url($v['url']);
            if (in_array($r['esito'], ['bozza', 'programmato', 'cestino', 'inesistente'], true)) throw new AicErrore($dove . ' («' . $et . '»): ' . $v['url'] . ' ' . aic_nav_esito_testo($r['esito']) . '.');
        }
        $k = aic_nav_chiave_voce($v);
        if (!empty($ids[$k])) $v['id'] = array_shift($ids[$k]);
        if (isset($x['figli']) && $x['figli'] !== [] && $x['figli'] !== null) $v['figli'] = aic_nav_voci_da_ai($x['figli'], $ids, $livello + 1, $conta, ($percorso !== '' ? $percorso . '.' : '') . ($i + 1));
        $out[] = $v;
    }
    return $out;
}

/** Gli id delle voci di adesso per chiave (una pagina che resta nel menu tiene il suo id). */
function aic_nav_ids_per_chiave(array $voci, array &$out = []): array {
    foreach ($voci as $x) { $out[aic_nav_chiave_voce($x)][] = $x['id']; aic_nav_ids_per_chiave($x['figli'], $out); }
    return $out;
}

/** Le voci senza gli id (per sapere se due menu sono uguali). */
function aic_nav_canonico(array $voci): string {
    $f = function (array $l) use (&$f) { return array_map(fn($x) => [$x['etichetta'], $x['tipo'], $x['rif'], $x['url'], (bool)$x['nuova'], $x['classi'], $f($x['figli'])], $l); };
    return json_encode($f(menu_pulisci_voci($voci)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Il menu letto adesso dal database (non dalla memoria della richiesta). */
function aic_nav_menu_fresco(int $id): ?array {
    menu_installa();
    $st = db()->prepare('SELECT * FROM ' . tbl('menu') . ' WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) return null;
    $v = json_decode((string)$m['voci'], true);
    $m['voci'] = menu_pulisci_voci(is_array($v) ? $v : []);
    return $m;
}

/** propose_menu: una proposta di tipo "menu" (prima: le voci di adesso; dopo: quelle nuove). Una sola aperta per menu. */
function aic_nav_proponi_menu(array $conn, array $a): array {
    aic_schema();
    $id = is_numeric($a['menu_id'] ?? null) ? (int)$a['menu_id'] : 0;
    $m = $id > 0 ? aic_nav_menu_fresco($id) : null;
    if (!$m) throw new AicErrore('Menu non trovato: passa in "menu_id" l\'id di un menu (da list_menus o analyze_navigation).', 404);
    $motivo = aic_testo($a['motivo'] ?? '', 1000, true);
    if (mb_strlen($motivo) < 5) throw new AicErrore('Scrivi il motivo della proposta (lo legge l\'amministratore per decidere).');
    if (!array_key_exists('voci', $a)) throw new AicErrore('Manca "voci": la struttura nuova completa del menu.');
    if ($a['voci'] === [] ) throw new AicErrore('Un menu senza voci non serve: passa almeno una voce.');
    $ids = aic_nav_ids_per_chiave($m['voci']);
    $voci = menu_pulisci_voci(aic_nav_voci_da_ai($a['voci'], $ids));
    if (aic_nav_canonico($voci) === aic_nav_canonico($m['voci'])) throw new AicErrore('Le voci proposte sono uguali a quelle di adesso.');
    $n = db()->prepare('SELECT COUNT(*) FROM ' . tbl('ai_proposte') . " WHERE connessione_id = ? AND stato = 'attesa'");
    $n->execute([(int)$conn['id']]);
    if ((int)$n->fetchColumn() >= AIC_PROPOSTE_MAX) throw new AicErrore('Ci sono già ' . AIC_PROPOSTE_MAX . ' proposte in attesa da questo collegamento: aspetta che l\'amministratore decida.', 429);
    $json = fn(array $v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Una proposta per menu aperta alla volta: la vecchia ancora aperta va in Scartata, con la nota.
    $vecchie = db()->prepare('UPDATE ' . tbl('ai_proposte') . " SET stato = 'scartata', deciso = NOW(), deciso_da = 'AI', nota = 'sostituita da una proposta di menu più recente' WHERE stato = 'attesa' AND tipo = 'menu' AND contenuto_id = ?");
    $vecchie->execute([$id]);
    db()->prepare('INSERT INTO ' . tbl('ai_proposte') . ' (connessione_id, client, tipo, contenuto_id, media_url, campo, widget_id, prima, dopo, motivo, stato, creato) VALUES (?, ?, \'menu\', ?, \'\', \'voci\', \'\', ?, ?, ?, \'attesa\', NOW())')
        ->execute([(int)$conn['id'], mb_substr((string)$conn['nome'], 0, 200), $id, $json($m['voci']), $json($voci), $motivo]);
    $pid = (int)db()->lastInsertId();
    $diff = aic_nav_confronto($m['voci'], $voci);
    return ['proposta' => $pid, 'stato' => 'attesa', 'menu_id' => $id, 'menu' => (string)$m['nome'], 'sostituite' => $vecchie->rowCount(),
        'riepilogo' => $diff['riepilogo'], 'voci' => $voci,
        'nota' => 'Proposta registrata: l\'amministratore vede i due menu affiancati in AI collegate > Proposte e decide. Se nel frattempo qualcuno cambia il menu, l\'approvazione si ferma e serve una proposta nuova.',
        '_registro' => 'proposta ' . $pid . ' sul menu ' . (string)$m['nome']];
}

/** Applica una proposta di menu: solo se il menu è ancora quello di "prima", e con le destinazioni ancora pubblicate. */
function aic_nav_applica_menu(array $p): void {
    $m = aic_nav_menu_fresco((int)$p['contenuto_id']);
    if (!$m) throw new RuntimeException('Il menu non c\'è più: la proposta non si può applicare. Scartala.');
    $prima = json_decode((string)$p['prima'], true);
    if (!is_array($prima) || aic_nav_canonico($prima) !== aic_nav_canonico($m['voci'])) throw new RuntimeException('Il menu «' . $m['nome'] . '» è cambiato dopo la proposta: per non cancellare le modifiche fatte nel frattempo non la applico. Scartala e chiedi all\'AI una proposta nuova.');
    $dopo = json_decode((string)$p['dopo'], true);
    if (!is_array($dopo) || !$dopo) throw new RuntimeException('La proposta è rovinata: scartala.');
    aic_nav_indice(true);
    try { $ids = []; aic_nav_voci_da_ai(array_map('aic_nav_voce_per_verifica', $dopo), $ids); }
    catch (AicErrore $e) { throw new RuntimeException('Non applico la proposta: ' . $e->getMessage()); }
    menu_salva((int)$m['id'], (string)$m['nome'], (string)$m['posizione'], $dopo);
}

/** Una voce salvata, nella forma che aic_nav_voci_da_ai() rilegge (per ricontrollare le destinazioni prima di applicare). */
function aic_nav_voce_per_verifica(array $x): array {
    return ['etichetta' => $x['etichetta'], 'tipo' => $x['tipo'], 'rif' => $x['rif'], 'url' => $x['url'], 'nuova' => $x['nuova'], 'figli' => array_map('aic_nav_voce_per_verifica', (array)$x['figli'])];
}

// ------------------------------------------------------------
// Il confronto di due menu (per il pannello): aggiunte, tolte, spostate, rinominate
// ------------------------------------------------------------

/** La sottosequenza comune più lunga di due elenchi di chiavi (piccoli: le voci di un livello). */
function aic_nav_lcs(array $a, array $b): array {
    $n = count($a); $m = count($b);
    if (!$n || !$m) return [];
    $t = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--) $t[$i][$j] = $a[$i] === $b[$j] ? $t[$i + 1][$j + 1] + 1 : max($t[$i + 1][$j], $t[$i][$j + 1]);
    $out = []; $i = 0; $j = 0;
    while ($i < $n && $j < $m) { if ($a[$i] === $b[$j]) { $out[$a[$i]] = true; $i++; $j++; } elseif ($t[$i + 1][$j] >= $t[$i][$j + 1]) $i++; else $j++; }
    return $out;
}

/** Le voci in piano: chiave unica (con il numero della ripetizione) => [genitore, etichetta]; e i figli per genitore. */
function aic_nav_piano(array $voci, string $genitore, array &$piano, array &$figli, array &$volte): void {
    foreach ($voci as $x) {
        $k = aic_nav_chiave_voce($x);
        $volte[$k] = ($volte[$k] ?? 0) + 1;
        $ku = $k . '#' . $volte[$k];
        $piano[$ku] = ['genitore' => $genitore, 'etichetta' => (string)$x['etichetta']];
        $figli[$genitore][] = $ku;
        aic_nav_piano($x['figli'], $ku, $piano, $figli, $volte);
    }
}

/**
 * Il confronto: due alberi annotati (prima con "tolta", dopo con "nuova", "spostata", "nome nuovo") e un riepilogo.
 * Una voce è "spostata" se cambia genitore o se cambia l'ordine rispetto alle voci che restano.
 */
function aic_nav_confronto(array $prima, array $dopo): array {
    $pa = []; $fa = []; $va = []; aic_nav_piano($prima, '', $pa, $fa, $va);
    $pb = []; $fb = []; $vb = []; aic_nav_piano($dopo, '', $pb, $fb, $vb);
    $spostate = [];
    foreach ($pb as $k => $x) if (isset($pa[$k]) && $pa[$k]['genitore'] !== $x['genitore']) $spostate[$k] = true;
    foreach ($fb as $g => $lista) {
        $comuniB = array_values(array_filter($lista, fn($k) => isset($pa[$k]) && $pa[$k]['genitore'] === $g));
        $comuniA = array_values(array_filter($fa[$g] ?? [], fn($k) => isset($pb[$k]) && $pb[$k]['genitore'] === $g));
        $fermi = aic_nav_lcs($comuniA, $comuniB);
        foreach ($comuniB as $k) if (!isset($fermi[$k])) $spostate[$k] = true;
    }
    $annota = function (array $voci, array $altro, array $mio, bool $dopoLato, array &$volte) use (&$annota, $spostate, $pa) {
        $out = [];
        foreach ($voci as $x) {
            $k = aic_nav_chiave_voce($x);
            $volte[$k] = ($volte[$k] ?? 0) + 1;
            $ku = $k . '#' . $volte[$k];
            $segni = [];
            if (!isset($altro[$ku])) $segni[] = $dopoLato ? 'nuova' : 'tolta';
            else {
                if ($dopoLato && isset($spostate[$ku])) $segni[] = 'spostata';
                if ($dopoLato && $altro[$ku]['etichetta'] !== (string)$x['etichetta']) $segni[] = 'nome nuovo';
            }
            $r = aic_nav_risolvi($x);
            $out[] = ['etichetta' => (string)$x['etichetta'], 'url' => $r['url'], 'esito' => $r['esito'], 'segni' => $segni,
                'era' => $dopoLato && isset($altro[$ku]) && $altro[$ku]['etichetta'] !== (string)$x['etichetta'] ? $altro[$ku]['etichetta'] : '',
                'figli' => $annota($x['figli'], $altro, $mio, $dopoLato, $volte)];
        }
        return $out;
    };
    $v1 = []; $v2 = [];
    $alberoPrima = $annota($prima, $pb, $pa, false, $v1);
    $alberoDopo = $annota($dopo, $pa, $pb, true, $v2);
    $nuove = count(array_diff_key($pb, $pa)); $tolte = count(array_diff_key($pa, $pb));
    $rinominate = 0;
    foreach ($pb as $k => $x) if (isset($pa[$k]) && $pa[$k]['etichetta'] !== $x['etichetta']) $rinominate++;
    return ['prima' => $alberoPrima, 'dopo' => $alberoDopo, 'riepilogo' => ['nuove' => $nuove, 'tolte' => $tolte, 'spostate' => count($spostate), 'rinominate' => $rinominate, 'voci_prima' => count($pa), 'voci_dopo' => count($pb)]];
}

/** Un albero annotato in HTML (pannello): ogni segno ha la sua parola, non solo il colore. */
function aic_nav_albero_html(array $voci, int $livello = 0): string {
    if (!$voci) return $livello ? '' : '<p class="hint">Nessuna voce.</p>';
    $out = '<ul class="aic-menu-albero' . ($livello ? ' aic-menu-sotto' : '') . '">';
    foreach ($voci as $x) {
        $cl = [];
        foreach ($x['segni'] as $s) $cl[] = 'aic-v-' . str_replace(' ', '-', $s);
        $segni = '';
        foreach ($x['segni'] as $s) $segni .= ' <span class="aic-segno aic-segno-' . h(str_replace(' ', '-', $s)) . '">' . h($s) . '</span>';
        $rotta = in_array($x['esito'], ['bozza', 'programmato', 'cestino', 'inesistente'], true) ? ' <span class="aic-segno aic-segno-tolta">' . h($x['esito'] === 'inesistente' ? 'non porta a niente' : $x['esito']) . '</span>' : '';
        $out .= '<li class="' . h(implode(' ', $cl)) . '"><span class="aic-voce"><b>' . h($x['etichetta']) . '</b>' . $segni . $rotta
            . ($x['era'] !== '' ? ' <span class="aic-era">era «' . h($x['era']) . '»</span>' : '')
            . ' <span class="aic-voce-url">' . h($x['url']) . '</span></span>' . aic_nav_albero_html($x['figli'], $livello + 1) . '</li>';
    }
    return $out . '</ul>';
}

// ------------------------------------------------------------
// propose_navigation_fix: Leggi anche e invito verso Contatti in fondo a una pagina
// ------------------------------------------------------------

/** La pagina Contatti del sito (la prima pagina pubblicata con "contatt" nello slug o nel titolo), oppure null. */
function aic_nav_pagina_contatti(): ?array {
    foreach (aic_nav_indice()['contenuti'] as $c) if ($c['tipo'] === 'pagine' && $c['stato'] === 'pubblicato' && preg_match('~contatt|contact~i', $c['slug'] . ' ' . $c['titolo'])) return $c;
    return null;
}

/**
 * I blocchi da aggiungere in fondo: [widget per l'editor, HTML per una scheda classica, testo leggibile, avvisi].
 * Lancia AicErrore se un pezzo non va.
 */
function aic_nav_blocchi(array $c, array $a): array {
    $ix = aic_nav_indice();
    $widget = []; $html = ''; $leggibile = []; $avvisi = [];
    $struttura = trim((string)($c['riga']['struttura'] ?? '')) !== '' ? json_decode((string)$c['riga']['struttura'], true) : null;
    $giaW = is_array($struttura) ? aic_nav_widget_albero($struttura) : [];
    if (isset($a['leggi_anche'])) {
        $la = $a['leggi_anche'];
        if (!is_array($la) || !is_array($la['voci'] ?? null) || !$la['voci']) throw new AicErrore('"leggi_anche" vuole {titolo, voci:[{tipo, id}]}: da 2 a 6 contenuti pubblicati del sito.');
        if (count($la['voci']) > 6) throw new AicErrore('"leggi_anche": al massimo 6 voci.');
        if (in_array('leggi_anche', array_column($giaW, 'tipo'), true)) throw new AicErrore('La pagina ha già un blocco Leggi anche: per cambiarlo usa propose_change sul suo testo, o chiedi all\'amministratore.');
        $voci = []; $visti = [];
        foreach (array_values($la['voci']) as $i => $v) {
            $t = is_array($v) && in_array($v['tipo'] ?? '', ['pagine', 'articoli'], true) ? $v['tipo'] : '';
            $k = $t !== '' && is_numeric($v['id'] ?? null) ? $t . ':' . (int)$v['id'] : '';
            $d = $ix['contenuti'][$k] ?? null;
            if (!$d) throw new AicErrore('"leggi_anche", voce ' . ($i + 1) . ': contenuto non trovato (serve {tipo: pagine|articoli, id}).', 404);
            if ($d['stato'] !== 'pubblicato') throw new AicErrore('"leggi_anche", voce ' . ($i + 1) . ': «' . $d['titolo'] . '» non è pubblicato.');
            if ($k === $c['tipo'] . ':' . $c['id']) throw new AicErrore('"leggi_anche", voce ' . ($i + 1) . ': una pagina non rimanda a sé stessa.');
            if (isset($visti[$k])) continue;
            $visti[$k] = true;
            $voci[] = ['titolo' => $d['titolo'], 'link' => $d['percorso']];
        }
        $titolo = aic_testo($la['titolo'] ?? '', 80) ?: 'Leggi anche';
        $widget[] = ['tipo' => 'leggi_anche', 'impostazioni' => ['titolo' => $titolo, 'voci' => $voci]];
        $html .= '<h2>' . h($titolo) . '</h2><ul>' . implode('', array_map(fn($v) => '<li><a href="' . h($v['link']) . '">' . h($v['titolo']) . '</a></li>', $voci)) . '</ul>';
        $leggibile[] = 'Blocco «' . $titolo . '» con: ' . implode(', ', array_map(fn($v) => $v['titolo'] . ' (' . $v['link'] . ')', $voci)) . '.';
    }
    if (isset($a['invito'])) {
        $in = $a['invito'];
        if (!is_array($in)) throw new AicErrore('"invito" vuole {titolo, testo, pulsante, link}.');
        if (aic_nav_ha_invito($c['tipo'], $c['riga'])) throw new AicErrore('La pagina finisce già con un invito all\'azione: non ne aggiungo un secondo.');
        $link = '';
        if (isset($in['link']) && $in['link'] !== '') {
            $link = aic_nav_url_sicuro($in['link'], '"invito.link"');
            $r = aic_nav_risolvi_url($link);
            if (in_array($r['esito'], ['bozza', 'programmato', 'cestino', 'inesistente'], true)) throw new AicErrore('"invito.link": ' . $link . ' ' . aic_nav_esito_testo($r['esito']) . '.');
        } else {
            $ct = aic_nav_pagina_contatti();
            if (!$ct) throw new AicErrore('Il sito non ha una pagina Contatti pubblicata: passa "invito.link" (per esempio mailto: o la pagina giusta).');
            $link = $ct['percorso'];
        }
        $tit = aic_testo($in['titolo'] ?? '', 120) ?: 'Hai domande?';
        $txt = aic_testo($in['testo'] ?? '', 300) ?: 'Scrivici: ti rispondiamo presto.';
        $pul = aic_testo($in['pulsante'] ?? '', 40) ?: 'Contattaci';
        $widget[] = ['tipo' => 'cta', 'impostazioni' => ['titolo' => $tit, 'testo' => $txt, 'pulsante_testo' => $pul, 'pulsante_link' => $link]];
        $html .= '<h2>' . h($tit) . '</h2><p>' . h($txt) . '</p><p><a href="' . h($link) . '">' . h($pul) . '</a></p>';
        $leggibile[] = 'Invito «' . $tit . '»: ' . $txt . ' Pulsante «' . $pul . '» verso ' . $link . '.';
    }
    if (!empty($a['briciole'])) $avvisi[] = 'Briciole di pane: il sito non ha un\'impostazione per accenderle; si aggiungono con il widget Briciole di pane nell\'header (Editor > Header e footer). Lo fa l\'amministratore: non l\'ho proposto.';
    if (!$widget) throw new AicErrore('Niente da aggiungere: passa "leggi_anche", "invito" o tutti e due.' . ($avvisi ? ' ' . $avvisi[0] : ''));
    return [$widget, $html, $leggibile, $avvisi];
}

/** Quello con cui finisce una pagina oggi, in parole (per il "prima" della proposta). */
function aic_nav_fine_leggibile(array $c): string {
    $json = trim((string)($c['riga']['struttura'] ?? ''));
    if ($json === '') return 'La pagina (testo della scheda) finisce così: «' . mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)($c['riga'][contenuti_tipo($c['tipo'])['campo_contenuto']] ?? '')))), -160) . '»';
    $a = json_decode($json, true);
    $ws = is_array($a) ? aic_nav_widget_albero($a) : [];
    if (!$ws) return 'La pagina è vuota.';
    aic_carica_editor_nav();
    $def = editor_definizioni();
    $ultima = max(array_column($ws, 'sezione'));
    $nomi = [];
    foreach ($ws as $w) if ($w['sezione'] === $ultima) $nomi[] = ($def[$w['tipo']]['etichetta'] ?? $w['tipo']) . (is_string($w['impostazioni']['titolo'] ?? null) && $w['impostazioni']['titolo'] !== '' ? ' «' . aic_testo($w['impostazioni']['titolo'], 60) . '»' : (is_string($w['impostazioni']['testo'] ?? null) ? ' «' . mb_substr(aic_testo($w['impostazioni']['testo'], 60), 0, 60) . '»' : ''));
    return 'Oggi l\'ultima sezione della pagina ha: ' . implode(', ', $nomi) . '.';
}

function aic_carica_editor_nav(): void {
    if (function_exists('aic_carica_editor')) { aic_carica_editor(); return; }
    require_once cms_radice() . '/includes/editor-controlli.php';
    require_once cms_radice() . '/includes/editor.php';
    editor_carica_widget_esterni();
}

/** La sezione nuova (validata dall'editor) con i widget da aggiungere. */
function aic_nav_sezione(array $widget): array {
    aic_carica_editor_nav();
    $ws = array_map(fn($w) => editor_widget($w['tipo'], $w['impostazioni']), $widget);
    $a = editor_valida(['elementi' => [editor_sezione($ws)]]);
    if (!$a || !$a['elementi']) throw new AicErrore('Non riesco a comporre i blocchi da aggiungere.');
    return $a['elementi'][0];
}

/**
 * propose_navigation_fix. Pubblicato: una proposta (campo "navigazione" per una pagina dell'editor, "testo" per una scheda
 * classica). Bozza: i blocchi vanno subito nella bozza (serve anche il permesso "bozze").
 */
function aic_nav_proponi_correzione(array $conn, array $a): array {
    aic_schema();
    $tipo = in_array($a['tipo'] ?? '', ['pagine', 'articoli'], true) ? $a['tipo'] : '';
    if ($tipo === '') throw new AicErrore('Il tipo deve essere pagine o articoli.');
    $ix = aic_nav_indice(true);
    $c = is_numeric($a['id'] ?? null) ? ($ix['contenuti'][$tipo . ':' . (int)$a['id']] ?? null) : null;
    if (!$c || $c['stato'] === 'cestino') throw new AicErrore('Contenuto non trovato: controlla tipo e id con list_content.', 404);
    $motivo = aic_testo($a['motivo'] ?? '', 1000, true);
    if (mb_strlen($motivo) < 5) throw new AicErrore('Scrivi il motivo della proposta (lo legge l\'amministratore per decidere).');
    [$widget, $html, $leggibile, $avvisi] = aic_nav_blocchi($c, $a);
    $editor = trim((string)($c['riga']['struttura'] ?? '') . (string)($c['riga']['struttura_bozza'] ?? '')) !== '';
    $autore = function_exists('aic_autore') ? aic_autore($conn) : 'AI';
    if ($c['stato'] === 'bozza') {
        if (!in_array('bozze', $conn['permessi'], true)) throw new AicErrore('Questo contenuto è una bozza: si modifica direttamente, ma serve il permesso "bozze" che questo collegamento non ha.', 403);
        if ($editor) {
            aic_carica_editor_nav();
            $alb = editor_decodifica((string)($c['riga']['struttura_bozza'] ?: $c['riga']['struttura'])) ?: ['elementi' => []];
            $alb['elementi'][] = aic_nav_sezione($widget);
            editor_salva($c['id'], editor_valida($alb), false, $autore, 'navigazione aggiunta dall\'AI', $tipo);
        } else {
            $base = array_replace(aic_base_da_riga($tipo, $c['riga']), ['contenuto' => (string)$c['riga'][contenuti_tipo($tipo)['campo_contenuto']] . $html]);
            $base['stato'] = 'bozza';
            contenuti_salva($tipo, $c['id'], $base, $autore);
        }
        return ['tipo' => $tipo, 'id' => $c['id'], 'stato' => 'bozza', 'applicato_alla_bozza' => true, 'aggiunto' => $leggibile, 'avvisi' => $avvisi, '_registro' => 'navigazione aggiunta alla bozza ' . $c['titolo']];
    }
    if (!$editor) {
        // Una scheda classica: una proposta normale sul testo intero, con i blocchi in coda.
        require_once __DIR__ . '/proposte.php';
        $p = aic_proposta_crea($conn, ['tipo' => $tipo, 'id' => $c['id'], 'campo' => 'testo', 'valore' => (string)$c['riga'][contenuti_tipo($tipo)['campo_contenuto']] . $html, 'motivo' => $motivo]);
        return ['proposta' => $p['id'], 'stato' => 'attesa', 'tipo' => $tipo, 'id' => $c['id'], 'campo' => 'testo', 'aggiunto' => $leggibile, 'avvisi' => $avvisi, '_registro' => 'proposta ' . $p['id'] . ' di navigazione (testo)'];
    }
    $n = db()->prepare('SELECT COUNT(*) FROM ' . tbl('ai_proposte') . " WHERE connessione_id = ? AND stato = 'attesa'");
    $n->execute([(int)$conn['id']]);
    if ((int)$n->fetchColumn() >= AIC_PROPOSTE_MAX) throw new AicErrore('Ci sono già ' . AIC_PROPOSTE_MAX . ' proposte in attesa da questo collegamento: aspetta che l\'amministratore decida.', 429);
    $sezione = aic_nav_sezione($widget);
    db()->prepare('UPDATE ' . tbl('ai_proposte') . " SET stato = 'superata', deciso = NOW(), nota = 'sostituita da una proposta più recente' WHERE stato = 'attesa' AND tipo = ? AND contenuto_id = ? AND campo = 'navigazione'")->execute([$tipo, $c['id']]);
    $dopo = json_encode(['sezione' => $sezione, 'leggibile' => implode("\n", $leggibile)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    db()->prepare('INSERT INTO ' . tbl('ai_proposte') . ' (connessione_id, client, tipo, contenuto_id, media_url, campo, widget_id, prima, dopo, motivo, stato, creato) VALUES (?, ?, ?, ?, \'\', \'navigazione\', \'\', ?, ?, ?, \'attesa\', NOW())')
        ->execute([(int)$conn['id'], mb_substr((string)$conn['nome'], 0, 200), $tipo, $c['id'], aic_nav_fine_leggibile($c), $dopo, $motivo]);
    $pid = (int)db()->lastInsertId();
    return ['proposta' => $pid, 'stato' => 'attesa', 'tipo' => $tipo, 'id' => $c['id'], 'campo' => 'navigazione', 'aggiunto' => $leggibile, 'avvisi' => $avvisi,
        'nota' => 'Proposta registrata: l\'amministratore la vede in AI collegate > Proposte. Approvata, la sezione va in fondo alla pagina pubblicata.', '_registro' => 'proposta ' . $pid . ' di navigazione'];
}

/** Applica una proposta "navigazione": la sezione in fondo all'albero pubblicato (e alla bozza dell'editor, se c'è). */
function aic_nav_applica_correzione(array $p, string $autore): void {
    $tipo = (string)$p['tipo'];
    $r = contenuti_leggi($tipo, (int)$p['contenuto_id']);
    if (!$r || !empty($r['cestino'])) throw new RuntimeException('Il contenuto non c\'è più (o è nel cestino).');
    $d = json_decode((string)$p['dopo'], true);
    if (!is_array($d['sezione'] ?? null)) throw new RuntimeException('La proposta è rovinata: scartala.');
    aic_carica_editor_nav();
    // I link del blocco si ricontrollano: una pagina tolta nel frattempo non entra.
    aic_nav_indice(true);
    foreach (aic_nav_link_da_valore($d['sezione']) as $u) {
        $x = aic_nav_risolvi_url($u);
        if (in_array($x['esito'], ['bozza', 'programmato', 'cestino', 'inesistente'], true)) throw new RuntimeException('Il link ' . $u . ' ' . aic_nav_esito_testo($x['esito']) . ': la proposta non si applica più. Scartala.');
    }
    $alb = editor_decodifica((string)$r['struttura']);
    if (!$alb) throw new RuntimeException('La pagina pubblicata non è fatta con l\'editor: la proposta non si applica. Scartala.');
    $alb['elementi'][] = $d['sezione'];
    $alb = editor_valida($alb);
    $alb = editor_blinda_codice($alb, null);
    editor_salva((int)$r['id'], $alb, true, $autore, 'navigazione proposta dall\'AI, approvata', $tipo);
    if (trim((string)$r['struttura_bozza']) !== '') {
        $b = editor_decodifica((string)$r['struttura_bozza']);
        if ($b) { $b['elementi'][] = $d['sezione']; editor_salva((int)$r['id'], editor_blinda_codice(editor_valida($b), null), false, $autore, 'bozza di prima, con la navigazione proposta dall\'AI', $tipo); }
    }
}
