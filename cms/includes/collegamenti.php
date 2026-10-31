<?php
/**
 * 2.2: "Scegli dal sito". Le pagine, gli articoli, le categorie, i tag e i contenuti degli addon che hanno una pagina
 * pubblica, cercati per titolo, per la finestra del pannello che scrive un link senza doverlo scrivere a mano
 * (admin/assets/scegli-link.js, chiamata admin/link-api.php).
 *
 * Il valore che si salva è il percorso dalla radice del sito, senza la cartella: "/contatti/". È lo stesso formato dei link
 * scritti a mano che editor_link_sicuro() accetta, e resta giusto se il sito cambia cartella: cms_con_cartella() aggiunge
 * la cartella quando la pagina esce (sito/index.php, sito_html). Per le email serve l'indirizzo intero: "assoluto".
 *
 * Gli addon aggiungono le loro voci con il filtro del nucleo 'collegamenti_sito':
 *     'collegamenti_sito' => function (array $voci, string $q, bool $admin): array { ... return $voci; }
 * Ogni voce: tipo (chiave breve, per esempio "evento"), tipo_nome ("Evento"), titolo, percorso ("/evento/x/"),
 * stato ("pubblicato" oppure "bozza"). Il nucleo filtra per la ricerca, toglie le bozze a chi non è amministratore,
 * controlla il percorso e aggiunge indirizzo e assoluto.
 */

require_once __DIR__ . '/contenuti.php';

const COLLEGAMENTI_MAX = 60;          // voci per risposta
const COLLEGAMENTI_RICERCA_MAX = 100; // caratteri della ricerca: il resto si taglia

/** I tipi del nucleo, nell'ordine in cui escono nella finestra. */
const COLLEGAMENTI_TIPI = ['pagina' => 'Pagina', 'articolo' => 'Articolo', 'categoria' => 'Categoria', 'tag' => 'Tag'];

/** La ricerca come la scrive una persona: senza caratteri di controllo e a 4 byte, spazi in fila ridotti, al massimo 100 caratteri. */
function collegamenti_ricerca_pulita(string $q): string {
    $q = (string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $q);
    if (!mb_check_encoding($q, 'UTF-8')) $q = mb_convert_encoding($q, 'UTF-8', 'UTF-8');
    // I caratteri a 4 byte (emoji) non stanno nelle colonne utf8 del sito: nessun titolo li contiene, e in un LIKE fanno
    // fallire la query delle tabelle a 3 byte (collazioni diverse). Si tolgono prima.
    $q = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $q);
    $q = trim((string)preg_replace('/\s+/u', ' ', $q));
    return mb_substr($q, 0, COLLEGAMENTI_RICERCA_MAX);
}

/** Un percorso che un addon dà: dalla radice, una barra sola davanti, solo i caratteri di un indirizzo. Altrimenti ''. */
function collegamenti_percorso_ok(string $p): string {
    return preg_match('~^/(?!/)[A-Za-z0-9._\~!$&\'()*+,;=:@%/?#-]*$~', $p) ? $p : '';
}

/** Le condizioni LIKE su più colonne, con % e _ scritti dall'utente che valgono come caratteri. */
function collegamenti_like(array $colonne, string $q, array &$par): string {
    if ($q === '') return '1=1';
    $parti = [];
    foreach ($colonne as $i => $c) {
        $parti[] = "$c LIKE :q$i ESCAPE '\\\\'";
        $par[":q$i"] = '%' . addcslashes($q, '%_\\') . '%';
    }
    return '(' . implode(' OR ', $parti) . ')';
}

/**
 * La ricerca. $tipo: '' (tutti) oppure la chiave di un tipo. $admin: falso per il redattore (vede pagine, articoli,
 * categorie e tag come nel pannello, bozze comprese; degli addon solo quello che è già pubblico).
 * Ritorna ['voci' => [...], 'tipi' => [chiave => nome], 'altre' => bool (ce ne sono di più: scrivi qualche lettera)].
 */
function collegamenti_cerca(string $q, string $tipo = '', bool $admin = true): array {
    $q = collegamenti_ricerca_pulita($q);
    $voci = [];
    $vuole = fn(string $t) => $tipo === '' || $tipo === $t;
    $home = (string)get_setting('pagina_home', '');

    foreach ([['pagina', 'pagine'], ['articolo', 'articoli']] as [$t, $chiave]) {
        if (!$vuole($t)) continue;
        $tab = contenuti_tipo($chiave)['tabella'];
        $par = [];
        $quando = $chiave === 'pagine' ? 'publish_at' : 'publish_date, publish_time';
        $st = db()->prepare('SELECT id, title, slug, status, cestino, ' . $quando . ' FROM ' . tbl($tab)
            . ' WHERE cestino IS NULL AND ' . collegamenti_like(['title', 'slug'], $q, $par)
            . ' ORDER BY ' . ($q !== '' ? '(title = :esatto) DESC, ' : '') . 'title LIMIT ' . (COLLEGAMENTI_MAX + 1));
        if ($q !== '') $par[':esatto'] = $q;
        $st->execute($par);
        foreach ($st->fetchAll() as $r) {
            $stato = contenuti_stato($chiave, $r);
            $voci[] = ['tipo' => $t, 'tipo_nome' => COLLEGAMENTI_TIPI[$t], 'titolo' => (string)$r['title'],
                'percorso' => $chiave === 'pagine' && $r['slug'] === $home ? '/' : '/' . $r['slug'] . '/',
                'stato' => $stato === 'pubblicato' ? 'pubblicato' : ($stato === 'programmato' ? 'programmato' : 'bozza')];
        }
    }
    foreach ([['categoria', 'categories', 'category'], ['tag', 'tags', 'tag']] as [$t, $tab, $base]) {
        if (!$vuole($t)) continue;
        $par = [];
        try {
            $st = db()->prepare('SELECT name, slug FROM ' . tbl($tab) . ' WHERE ' . collegamenti_like(['name', 'slug'], $q, $par) . ' ORDER BY name LIMIT ' . (COLLEGAMENTI_MAX + 1));
            $st->execute($par);
            foreach ($st->fetchAll() as $r) $voci[] = ['tipo' => $t, 'tipo_nome' => COLLEGAMENTI_TIPI[$t], 'titolo' => (string)$r['name'], 'percorso' => "/$base/{$r['slug']}/", 'stato' => 'pubblicato'];
        } catch (PDOException $e) {
            error_log('collegamenti ' . $tab . ': ' . $e->getMessage()); // una tabella che manca (sito vecchio) non ferma la finestra
        }
    }

    // Gli addon. Ognuno è dentro un try: un addon guasto toglie le sue voci, non la finestra.
    $tipi = COLLEGAMENTI_TIPI;
    $extra = [];
    try { $extra = (array)cms_filtro('collegamenti_sito', [], $q, $admin); } catch (Throwable $e) { error_log('collegamenti_sito: ' . $e->getMessage()); }
    foreach ($extra as $v) {
        if (!is_array($v)) continue;
        $t = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($v['tipo'] ?? '')));
        $p = collegamenti_percorso_ok((string)($v['percorso'] ?? ''));
        $titolo = trim((string)($v['titolo'] ?? ''));
        if ($t === '' || $p === '' || $titolo === '' || isset(COLLEGAMENTI_TIPI[$t])) continue;
        $stato = ($v['stato'] ?? 'pubblicato') === 'pubblicato' ? 'pubblicato' : 'bozza';
        if ($stato !== 'pubblicato' && !$admin) continue;
        $tipi[$t] ??= mb_substr(trim((string)($v['tipo_nome'] ?? '')) ?: ucfirst($t), 0, 40);
        if (!$vuole($t)) continue;
        if ($q !== '' && mb_stripos($titolo, $q) === false && stripos($p, $q) === false) continue;
        $voci[] = ['tipo' => $t, 'tipo_nome' => $tipi[$t], 'titolo' => $titolo, 'percorso' => $p, 'stato' => $stato];
    }

    // Prima chi ha il titolo uguale alla ricerca, poi chi comincia così, poi gli altri; a parità, nell'ordine dei tipi.
    $ordine = array_flip(array_keys($tipi));
    $peso = function (array $v) use ($q): int {
        if ($q === '') return 2;
        $t = mb_strtolower($v['titolo']); $c = mb_strtolower($q);
        return $t === $c ? 0 : (str_starts_with($t, $c) ? 1 : 2);
    };
    usort($voci, fn($a, $b) => [$peso($a), $ordine[$a['tipo']] ?? 99, mb_strtolower($a['titolo'])] <=> [$peso($b), $ordine[$b['tipo']] ?? 99, mb_strtolower($b['titolo'])]);
    $altre = count($voci) > COLLEGAMENTI_MAX;
    $voci = array_slice($voci, 0, COLLEGAMENTI_MAX);

    $sito = rtrim(cms_url_sito(), '/');
    $cartella = cms_sito_base();
    foreach ($voci as &$v) {
        $v['titolo'] = mb_substr($v['titolo'], 0, 200);
        $v['indirizzo'] = $cartella . $v['percorso'];   // come si vede nel browser (con la cartella del sito)
        $v['assoluto'] = $sito . $v['percorso'];        // per le email
    }
    unset($v);
    return ['voci' => $voci, 'tipi' => $tipi, 'altre' => $altre];
}
