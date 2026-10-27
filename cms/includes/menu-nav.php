<?php
/**
 * Menu di navigazione, come Aspetto > Menu di WordPress.
 *
 * Un menu è una riga di newdf_menu: nome, posizione (un'etichetta libera: principale, piede...) e le voci
 * in un solo JSON ad albero, così il trascinamento annidato del pannello salva tutto in un colpo:
 *   [{"id":"a1b2c3d","etichetta":"Blog","tipo":"pagina|articolo|categoria|link","rif":"slug o indirizzo",
 *     "url":"/blog-fuori-del-coso/","nuova":false,"classi":"","figli":[...]}]
 * Le voci che puntano a una pagina, a un articolo o a una categoria ricordano lo slug (rif): se l'indirizzo
 * cambia, menu_voci_risolte() lo ricalcola dalla mappa del sito.
 *
 * Il widget menu_nav (editor-widget-tema.php) sceglie un menu e lo disegna; il primo avvio crea il menu
 * "Principale" con le voci della testata di prima e i tre menu del piede.
 */

require_once __DIR__ . '/sito.php';

const MENU_VERSIONE_SCHEMA = '1';
const MENU_TIPI_VOCE = ['link' => 'Link personalizzato', 'pagina' => 'Pagina', 'articolo' => 'Articolo', 'categoria' => 'Categoria'];
const MENU_POSIZIONI = ['' => 'Nessuna', 'principale' => 'Principale (testata)', 'piede-1' => 'Piede, prima colonna', 'piede-2' => 'Piede, seconda colonna', 'piede-3' => 'Piede, terza colonna', 'telefono' => 'Menu del telefono'];

/** Crea la tabella se manca, e il menu Principale la prima volta. Una query di controllo per richiesta. */
function menu_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    if (get_setting('menu_schema', '') === MENU_VERSIONE_SCHEMA) return;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('menu') . ' (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(100) NOT NULL,
        `posizione` VARCHAR(30) NOT NULL DEFAULT \'\',
        `voci` LONGTEXT,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`), KEY `posizione` (`posizione`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
    if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('menu'))->fetchColumn() === 0) menu_crea_di_partenza();
    save_setting('menu_schema', MENU_VERSIONE_SCHEMA);
}

/** I menu di partenza: il modulo del sito dà i suoi (danilofailli.it: testata e tre colonne del piede); altrimenti Home e blog. */
function menu_crea_di_partenza(): void {
    $v = fn(string $etichetta, string $url, string $tipo = 'link', string $rif = '') => ['id' => menu_id_voce(), 'etichetta' => $etichetta, 'tipo' => $tipo, 'rif' => $rif, 'url' => $url, 'nuova' => false, 'classi' => '', 'figli' => []];
    $voci = [$v('Home', '/')];
    if (SITO_PAGINA_BLOG !== '') $voci[] = $v('Blog', SITO_PAGINA_BLOG);
    $menu = cms_filtro('menu_partenza', [['nome' => 'Principale', 'posizione' => 'principale', 'voci' => $voci]]);
    foreach ($menu as $m) menu_salva(0, (string)$m['nome'], (string)$m['posizione'], (array)$m['voci']);
}

function menu_id_voce(): string {
    return substr(bin2hex(random_bytes(4)), 0, 7);
}

// ------------------------------------------------------------
// Lettura
// ------------------------------------------------------------

function menu_tutti(): array {
    static $m = null;
    if ($m !== null) return $m;
    menu_installa();
    $m = [];
    foreach (db()->query('SELECT * FROM ' . tbl('menu') . ' ORDER BY id')->fetchAll() as $r) {
        $voci = json_decode((string)$r['voci'], true);
        $r['voci'] = menu_pulisci_voci(is_array($voci) ? $voci : []);
        $m[(int)$r['id']] = $r;
    }
    return $m;
}

function menu_leggi(int $id): ?array {
    return menu_tutti()[$id] ?? null;
}

/** Il menu con una posizione (il primo che la porta). */
function menu_per_posizione(string $posizione): ?array {
    foreach (menu_tutti() as $m) if ($m['posizione'] === $posizione) return $m;
    return null;
}

/** Per le tendine del pannello e dell'editor: id => nome. */
function menu_opzioni(): array {
    $o = [];
    foreach (menu_tutti() as $m) $o[(string)$m['id']] = $m['nome'];
    return $o;
}

/** Le voci con l'indirizzo ricalcolato dalla mappa del sito (una pagina rinominata resta nel menu). */
function menu_voci_risolte(array $menu): array {
    $risolvi = function (array $voci) use (&$risolvi) {
        $out = [];
        foreach ($voci as $x) {
            $x['url'] = menu_url_voce($x);
            $x['figli'] = $risolvi($x['figli']);
            $out[] = $x;
        }
        return $out;
    };
    return $risolvi($menu['voci']);
}

function menu_url_voce(array $x): string {
    if ($x['tipo'] === 'categoria' && $x['rif'] !== '') return '/category/' . $x['rif'] . '/';
    if (in_array($x['tipo'], ['pagina', 'articolo'], true) && $x['rif'] !== '') {
        $home = (string)get_setting('pagina_home', '');
        if ($x['tipo'] === 'pagina' && $x['rif'] === $home) return '/';
        return '/' . $x['rif'] . '/';
    }
    return $x['url'] !== '' ? $x['url'] : '#';
}

// ------------------------------------------------------------
// Scrittura
// ------------------------------------------------------------

/** Solo i campi che il menu conosce, al massimo tre livelli e 200 voci. */
function menu_pulisci_voci($voci, int $livello = 0, int &$conta = 0): array {
    if (!is_array($voci) || $livello > 2) return [];
    $out = [];
    foreach ($voci as $x) {
        if (!is_array($x) || ++$conta > 200) continue;
        $tipo = array_key_exists($x['tipo'] ?? '', MENU_TIPI_VOCE) ? $x['tipo'] : 'link';
        $url = trim((string)($x['url'] ?? ''));
        if ($url !== '' && !preg_match('~^(https?://|/|#|mailto:|tel:)~i', $url)) $url = '';
        $out[] = [
            'id' => preg_match('/^[a-z0-9]{4,12}$/', (string)($x['id'] ?? '')) ? $x['id'] : menu_id_voce(),
            'etichetta' => mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($x['etichetta'] ?? ''))), 0, 100),
            'tipo' => $tipo,
            'rif' => mb_substr(preg_replace('/[^a-z0-9\-]/', '', (string)($x['rif'] ?? '')), 0, 255),
            'url' => mb_substr($url, 0, 500),
            'nuova' => !empty($x['nuova']),
            'classi' => mb_substr(preg_replace('/[^A-Za-z0-9 _\-]/', '', (string)($x['classi'] ?? '')), 0, 100),
            'figli' => menu_pulisci_voci($x['figli'] ?? [], $livello + 1, $conta),
        ];
    }
    return $out;
}

function menu_salva(int $id, string $nome, string $posizione, array $voci): int {
    menu_installa();
    $nome = mb_substr(trim($nome), 0, 100);
    if ($nome === '') throw new RuntimeException('Il menu ha bisogno di un nome.');
    if (!array_key_exists($posizione, MENU_POSIZIONI)) $posizione = '';
    $json = json_encode(menu_pulisci_voci($voci), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Le emoji restano come 😀: la colonna è utf8 a 3 byte.
    $json = preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', function ($m) {
        $c = mb_ord($m[0]) - 0x10000;
        return sprintf('\\u%04x\\u%04x', 0xD800 + ($c >> 10), 0xDC00 + ($c & 0x3FF));
    }, $json);
    if ($id) {
        db()->prepare('UPDATE ' . tbl('menu') . ' SET nome = ?, posizione = ?, voci = ?, updated_at = NOW() WHERE id = ?')->execute([$nome, $posizione, $json, $id]);
    } else {
        db()->prepare('INSERT INTO ' . tbl('menu') . ' (nome, posizione, voci, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')->execute([$nome, $posizione, $json]);
        $id = (int)db()->lastInsertId();
    }
    menu_versione($id, ['nome' => $nome, 'posizione' => $posizione, 'voci' => menu_pulisci_voci($voci)], ''); // versioni
    return $id;
}

function menu_elimina(int $id): void {
    $m = menu_leggi($id);
    if ($m) menu_versione($id, ['nome' => $m['nome'], 'posizione' => $m['posizione'], 'voci' => $m['voci']], 'eliminato (un ripristino lo ricrea)'); // versioni
    db()->prepare('DELETE FROM ' . tbl('menu') . ' WHERE id = ?')->execute([$id]);
}

/** versioni: una copia del menu in {prefisso}editor_versioni (ambito "menu"). Mai un errore da qui. */
function menu_versione(int $id, array $dati, string $nota): void {
    try {
        require_once __DIR__ . '/versioni.php';
        editor_versione_salva('menu', $id, (string)$dati['nome'], $dati, $nota);
    } catch (Throwable $e) {
        error_log('versioni (menu): ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Disegno (usato dal widget menu_nav)
// ------------------------------------------------------------

/**
 * L'elenco delle voci in HTML: <ul><li><a>...</a><ul>...</ul></li></ul>.
 * $attivo: l'indirizzo "aperto" (quello che vista_pagina riceve), per aria-current.
 */
function menu_html_voci(array $voci, string $attivo = '', int $livello = 0): string {
    if (!$voci) return '';
    $out = '<ul class="' . ($livello === 0 ? 'mn-lista' : 'mn-sotto') . '">';
    foreach ($voci as $x) {
        $url = $x['url'] !== '' ? $x['url'] : '#';
        $qui = $attivo !== '' && $url === $attivo;
        $figli = $x['figli'] ?? [];
        $out .= '<li class="mn-voce' . ($qui ? ' mn-attiva' : '') . ($figli ? ' mn-con-figli' : '') . ($x['classi'] !== '' ? ' ' . h($x['classi']) : '') . '">'
            . '<a href="' . h($url) . '"' . ($qui ? ' aria-current="page"' : '') . ($x['nuova'] ? ' target="_blank" rel="noopener"' : '') . '>' . h($x['etichetta'])
            . ($figli ? '<span class="mn-freccia" aria-hidden="true"></span>' : '') . '</a>'
            . menu_html_voci($figli, $attivo, $livello + 1) . '</li>';
    }
    return $out . '</ul>';
}

/** Le voci in piano (per il menu a scomparsa del telefono): un link per riga, le sottovoci rientrate. */
function menu_html_piatto(array $voci, int $livello = 0): string {
    $out = '';
    foreach ($voci as $x) {
        $url = $x['url'] !== '' ? $x['url'] : '#';
        $out .= '<a href="' . h($url) . '"' . ($livello ? ' class="mn-h-sotto"' : '') . ($x['nuova'] ? ' target="_blank" rel="noopener"' : '') . '>' . h($x['etichetta']) . '</a>' . menu_html_piatto($x['figli'] ?? [], $livello + 1);
    }
    return $out;
}

/** Pagine, articoli e categorie da cui scegliere nel pannello Menu. */
function menu_scelte(): array {
    $home = (string)get_setting('pagina_home', '');
    $pagine = db()->query('SELECT slug, title FROM ' . tbl('pages') . " WHERE cestino IS NULL AND status = 'published' ORDER BY title")->fetchAll();
    $articoli = db()->query('SELECT slug, title FROM ' . tbl('articles') . " WHERE cestino IS NULL AND status = 'published' AND publish_date <= CURDATE() ORDER BY publish_date DESC, title LIMIT 400")->fetchAll();
    $categorie = db()->query('SELECT slug, name AS title FROM ' . tbl('categories') . ' ORDER BY name')->fetchAll();
    $spec = cms_filtro('menu_scelte_speciali', [['slug' => '', 'title' => 'Home', 'url' => '/']]);
    return [
        'pagine' => array_map(fn($p) => ['slug' => $p['slug'], 'title' => $p['title'], 'url' => $p['slug'] === $home ? '/' : '/' . $p['slug'] . '/'], $pagine),
        'articoli' => array_map(fn($a) => ['slug' => $a['slug'], 'title' => $a['title'], 'url' => '/' . $a['slug'] . '/'], $articoli),
        'categorie' => array_map(fn($c) => ['slug' => $c['slug'], 'title' => $c['title'], 'url' => '/category/' . $c['slug'] . '/'], $categorie),
        'speciali' => $spec,
    ];
}
