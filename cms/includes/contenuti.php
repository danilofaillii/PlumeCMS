<?php
/**
 * Articoli, pagine e tipi dei moduli gestiti allo stesso modo: stessi stati (bozza, programmato,
 * pubblicato, cestino), stesso elenco con filtri e azioni di massa, stessa scheda di modifica,
 * stesse revisioni, stesso editor visuale.
 *
 * Due tabelle restano due (<prefisso>articles, <prefisso>pages): qui si descrivono una volta sola le
 * differenze, e tutto il resto le tratta uguali.
 */

require_once __DIR__ . '/sito-mappa.php';
require_once __DIR__ . '/media.php';

const CONTENUTI_PER_PAGINA = 20;
const CONTENUTI_REVISIONI = 20;

/** I due tipi: tabella, nome, campi che cambiano nome, cosa hanno in più. */
function contenuti_tipi(): array {
    static $tipi = null;
    if ($tipi !== null) return $tipi;
    $tipi = [
        // tassonomie: categorie e tag; attributi: pillar, keyword, CTA; kw: colonne della densità; citazione: autore della frase.
        'articoli' => [
            'tabella' => 'articles', 'uno' => 'articolo', 'tanti' => 'articoli', 'Uno' => 'Articolo', 'Tanti' => 'Articoli',
            'campo_contenuto' => 'content', 'tassonomie' => true, 'attributi' => true, 'kw' => true, 'citazione' => false,
        ],
        'pagine' => [
            'tabella' => 'pages', 'uno' => 'pagina', 'tanti' => 'pagine', 'Uno' => 'Pagina', 'Tanti' => 'Pagine',
            'campo_contenuto' => 'html', 'tassonomie' => false, 'attributi' => false, 'kw' => false, 'citazione' => false,
        ],
    ];
    // I tipi dichiarati dai moduli (per esempio un catalogo), con le stesse chiavi.
    foreach (cms_moduli_elenco('tipi_contenuto') as $x) {
        if (is_array($x['valore']) && !isset($tipi[$x['chiave']])) $tipi[(string)$x['chiave']] = $x['valore'] + ['tassonomie' => false, 'attributi' => false, 'kw' => false, 'citazione' => false, 'campo_contenuto' => 'content'];
    }
    return $tipi;
}

function contenuti_tipo(string $tipo): array {
    $t = contenuti_tipi()[$tipo] ?? null;
    if (!$t) throw new InvalidArgumentException("Tipo di contenuto sconosciuto: $tipo");
    return $t + ['chiave' => $tipo];
}

/** Colonne in più, create dallo schema del sito (sito_schema_aggiorna) con la sua impronta. */
function contenuti_schema_colonne(): array {
    return [
        'articles' => [
            'cestino'              => 'DATETIME DEFAULT NULL',
            // modelli: header e footer scelti per questo contenuto (0 = predefinito, -1 = nessuno, > 0 = quel modello).
            'header_id' => 'INT(11) NOT NULL DEFAULT 0',
            'footer_id' => 'INT(11) NOT NULL DEFAULT 0',
            'struttura'            => 'LONGTEXT',
            'struttura_bozza'      => 'LONGTEXT',
            'struttura_aggiornata' => 'DATETIME DEFAULT NULL',
            // Da dove viene la struttura: "conversione N data" se l'ha scritta editor_converti_articoli(), vuoto se nasce dall'editor.
            'struttura_origine'    => "VARCHAR(80) NOT NULL DEFAULT ''",
        ],
        'pages' => [
            'cestino'      => 'DATETIME DEFAULT NULL',
            // modelli: header e footer scelti per questo contenuto (0 = predefinito, -1 = nessuno, > 0 = quel modello).
            'header_id' => 'INT(11) NOT NULL DEFAULT 0',
            'footer_id' => 'INT(11) NOT NULL DEFAULT 0',
            'excerpt'      => 'TEXT',
            'author'       => "VARCHAR(100) NOT NULL DEFAULT ''",
            'publish_time' => "TIME NOT NULL DEFAULT '06:00:00'",
        ],
    ];
}

function contenuti_schema_tabelle(): array {
    return [
        // Le ultime versioni salvate dalla scheda classica (titolo, testo, estratto e il resto), per tornare indietro.
        'revisioni' => "CREATE TABLE IF NOT EXISTS " . tbl('revisioni') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tipo` VARCHAR(20) NOT NULL,
            `contenuto_id` INT(11) NOT NULL,
            `dati` LONGTEXT NOT NULL,
            `nota` VARCHAR(255) NOT NULL DEFAULT '',
            `autore` VARCHAR(100) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `contenuto` (`tipo`, `contenuto_id`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
}

// ------------------------------------------------------------
// Lettura
// ------------------------------------------------------------

/** Data e ora di uscita in formato AAAA-MM-GG HH:MM:SS, oppure '' se mai fissata. */
function contenuti_quando(string $tipo, array $r): string {
    if ($tipo === 'pagine') return $r['publish_at'] ? substr((string)$r['publish_at'], 0, 19) : '';
    if (empty($r['publish_date'])) return '';
    return substr((string)$r['publish_date'], 0, 10) . ' ' . substr((string)($r['publish_time'] ?: SITO_ORA_PREDEFINITA), 0, 8);
}

/** Lo stato: bozza, programmato, pubblicato, cestino. */
function contenuti_stato(string $tipo, array $r): string {
    if (!empty($r['cestino'])) return 'cestino';
    if (($r['status'] ?? 'draft') === 'draft') return 'bozza';
    $q = contenuti_quando($tipo, $r);
    return ($q !== '' && $q > date('Y-m-d H:i:s')) ? 'programmato' : 'pubblicato';
}

const CONTENUTI_STATI = ['bozza' => 'Bozza', 'programmato' => 'Programmato', 'pubblicato' => 'Pubblicato', 'cestino' => 'Cestino'];

function contenuti_percorso(string $tipo, array $r): string {
    if ($tipo === 'pagine' && $r['slug'] === (string)get_setting('pagina_home', '')) return '/';
    return '/' . $r['slug'] . '/';
}

function contenuti_leggi(string $tipo, int $id): ?array {
    $t = contenuti_tipo($tipo);
    $st = db()->prepare('SELECT * FROM ' . tbl($t['tabella']) . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Condizione SQL per uno stato dell'elenco. */
function contenuti_sql_stato(string $tipo, string $stato): string {
    $quando = $tipo === 'pagine' ? 'publish_at' : 'TIMESTAMP(publish_date, publish_time)';
    return match ($stato) {
        'cestino' => 'cestino IS NOT NULL',
        'bozza' => "cestino IS NULL AND status = 'draft'",
        'programmato' => "cestino IS NULL AND status <> 'draft' AND $quando > NOW()",
        'pubblicato' => "cestino IS NULL AND status <> 'draft' AND ($quando IS NULL OR $quando <= NOW())",
        default => 'cestino IS NULL',
    };
}

/**
 * L'elenco con i filtri: stato, ricerca, categoria, tipo di articolo, mese,
 * ordinamento, pagina. Ritorna [righe, totale].
 */
function contenuti_elenco(string $tipo, array $f): array {
    $t = contenuti_tipo($tipo);
    $where = [contenuti_sql_stato($tipo, (string)($f['stato'] ?? ''))];
    $p = [];
    if (($f['q'] ?? '') !== '') {
        // sicurezza: % e _ scritti dall'utente valgono come caratteri, non come jolly.
        $esc = "ESCAPE '\\\\'";
        $where[] = "(title LIKE :q1 $esc OR slug LIKE :q2 $esc" . ($t['kw'] ? " OR keyword_target LIKE :q3 $esc" : '') . ')';
        $p[':q1'] = $p[':q2'] = '%' . addcslashes((string)$f['q'], '%_\\') . '%';
        if ($t['kw']) $p[':q3'] = '%' . addcslashes((string)$f['q'], '%_\\') . '%';
    }
    if ($t['tassonomie'] && ($f['categoria'] ?? '') !== '') {
        $where[] = "(category = :cat OR FIND_IN_SET(:cat2, REPLACE(categorie, ' ', '')))";
        $p[':cat'] = $p[':cat2'] = $f['categoria'];
    }
    if ($t['tassonomie'] && in_array($f['tipo_articolo'] ?? '', ['pillar', 'cluster'], true)) {
        $where[] = 'type = :tp';
        $p[':tp'] = $f['tipo_articolo'];
    }
    if (preg_match('/^\d{4}-\d{2}$/', (string)($f['mese'] ?? ''))) {
        $where[] = ($tipo === 'pagine' ? "DATE_FORMAT(publish_at, '%Y-%m')" : "DATE_FORMAT(publish_date, '%Y-%m')") . ' = :mese';
        $p[':mese'] = $f['mese'];
    }
    $ordini = ['data' => $tipo === 'pagine' ? 'publish_at' : 'publish_date, publish_time', 'titolo' => 'title', 'modificato' => 'updated_at', 'autore' => 'author'];
    $ordina = $ordini[$f['ordina'] ?? ''] ?? $ordini['data'];
    $dir = ($f['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $ordina = implode(', ', array_map(fn($c) => trim($c) . " $dir", explode(',', $ordina)));
    $sqlWhere = ' FROM ' . tbl($t['tabella']) . ' WHERE ' . implode(' AND ', $where);
    $c = db()->prepare('SELECT COUNT(*)' . $sqlWhere);
    $c->execute($p);
    $totale = (int)$c->fetchColumn();
    $per = max(1, (int)($f['per'] ?? CONTENUTI_PER_PAGINA));
    $pagina = max(1, min((int)($f['pagina'] ?? 1), max(1, (int)ceil($totale / $per))));
    $st = db()->prepare('SELECT ' . sito_colonne_leggere($t['tabella'], ['struttura', 'struttura_bozza']) . $sqlWhere . " ORDER BY $ordina, id DESC LIMIT " . (($pagina - 1) * $per) . ", $per");
    $st->execute($p);
    return [$st->fetchAll(), $totale, $pagina, $per];
}

function contenuti_conteggi(string $tipo): array {
    $out = [];
    foreach (['' => 'tutti', 'pubblicato' => 'pubblicato', 'programmato' => 'programmato', 'bozza' => 'bozza', 'cestino' => 'cestino'] as $s => $k) {
        $out[$k] = (int)db()->query('SELECT COUNT(*) FROM ' . tbl(contenuti_tipo($tipo)['tabella']) . ' WHERE ' . contenuti_sql_stato($tipo, $s))->fetchColumn();
    }
    return $out;
}

/** I mesi che hanno contenuti, per il filtro "Tutte le date". */
function contenuti_mesi(string $tipo): array {
    $col = $tipo === 'pagine' ? 'publish_at' : 'publish_date';
    return db()->query("SELECT DISTINCT DATE_FORMAT($col, '%Y-%m') AS m FROM " . tbl(contenuti_tipo($tipo)['tabella']) . " WHERE $col IS NOT NULL ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
}

// ------------------------------------------------------------
// Tassonomie
// ------------------------------------------------------------

function contenuti_categorie(): array {
    static $c = null;
    if ($c === null) $c = db()->query('SELECT slug, name, description, robots FROM ' . tbl('categories') . ' ORDER BY sort_order, name')->fetchAll(PDO::FETCH_UNIQUE);
    return $c;
}

function contenuti_slug(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($s))) ?: mb_strtolower(trim($s));
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}

/** Le categorie di un articolo: slug, dal campo nuovo oppure da quello vecchio. */
function contenuti_categorie_di(array $r): array {
    return array_values(sito_lista($r['categorie'] ?? '') ?: array_filter([(string)($r['category'] ?? '')]));
}

/** I tag di un articolo come nomi leggibili. */
function contenuti_tag_di(array $r): array {
    $slugs = sito_lista($r['tag_slugs'] ?? '');
    if (!$slugs) return sito_lista($r['tags'] ?? '');
    $nomi = db()->query('SELECT slug, name FROM ' . tbl('tags'))->fetchAll(PDO::FETCH_KEY_PAIR);
    return array_map(fn($s) => $nomi[$s] ?? $s, $slugs);
}

/** Crea una categoria se manca. Ritorna lo slug. */
function contenuti_categoria_crea(string $nome): string {
    $slug = contenuti_slug($nome);
    if ($slug === '') throw new RuntimeException('Nome della categoria vuoto.');
    db()->prepare('INSERT IGNORE INTO ' . tbl('categories') . ' (slug, name) VALUES (?, ?)')->execute([$slug, trim($nome)]);
    return $slug;
}

/** I tag dal campo libero: nomi separati da virgola. Crea quelli nuovi. Ritorna [slug[], nomi[]]. */
function contenuti_tag_sistema(string $csv): array {
    $slugs = []; $nomi = [];
    $esistenti = db()->query('SELECT slug, name FROM ' . tbl('tags'))->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (sito_lista($csv) as $n) {
        $slug = contenuti_slug($n);
        if ($slug === '' || in_array($slug, $slugs, true)) continue;
        if (!isset($esistenti[$slug])) {
            db()->prepare('INSERT IGNORE INTO ' . tbl('tags') . ' (slug, name) VALUES (?, ?)')->execute([$slug, $n]);
            $esistenti[$slug] = $n;
        }
        $slugs[] = $slug;
        $nomi[] = $esistenti[$slug];
    }
    return [$slugs, $nomi];
}

function contenuti_tag_tutti(): array {
    return db()->query('SELECT name FROM ' . tbl('tags') . ' ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
}

// ------------------------------------------------------------
// Scrittura
// ------------------------------------------------------------

/** Slug libero? Diverso da ogni altro contenuto dello stesso tipo. */
function contenuti_slug_libero(string $tipo, string $slug, int $id): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl(contenuti_tipo($tipo)['tabella']) . ' WHERE slug = ? AND id <> ?');
    $st->execute([$slug, $id]);
    return !$st->fetchColumn();
}

/** Un redirect 301 dal vecchio indirizzo al nuovo, quando cambia lo slug. */
function contenuti_redirect(string $da, string $a): void {
    if ($da === $a || $da === '/' || $a === '') return;
    db()->prepare('INSERT INTO ' . tbl('redirect') . " (da, a, codice, confronto, attivo, origine) VALUES (?, ?, 301, 'exact', 1, 'slug') ON DUPLICATE KEY UPDATE a = VALUES(a), attivo = 1")->execute([$da, $a]);
    // Chi puntava al vecchio indirizzo ora punta al nuovo, senza doppio salto.
    db()->prepare('UPDATE ' . tbl('redirect') . ' SET a = ? WHERE a = ?')->execute([$a, $da]);
    db()->prepare('DELETE FROM ' . tbl('redirect') . ' WHERE da = ?')->execute([$a]);
}

/**
 * Salva dalla scheda classica. $d: title, slug, contenuto, excerpt, stato (bozza|pubblicato), data (AAAA-MM-GG),
 * ora (HH:MM), author, seo_title, seo_description, meta_description, robots, tldr, faq (JSON), immagine, immagine_alt,
 * e per gli articoli: categorie[], categoria_nuova, tags, type, keyword_target, keywords_secondarie, cta, pillar_ref, pillar_title.
 * Ritorna l'id. Lancia RuntimeException con un messaggio da mostrare.
 */
function contenuti_salva(string $tipo, int $id, array $d, string $autore = ''): int {
    $t = contenuti_tipo($tipo);
    $prima = $id ? contenuti_leggi($tipo, $id) : null;
    if ($id && !$prima) throw new RuntimeException('Contenuto non trovato.');
    $titolo = trim((string)($d['title'] ?? ''));
    if ($titolo === '') throw new RuntimeException('Il titolo è obbligatorio.');
    $slug = contenuti_slug((string)($d['slug'] ?? '')) ?: contenuti_slug($titolo);
    if ($slug === '') throw new RuntimeException("L'indirizzo non può restare vuoto.");
    if (!contenuti_slug_libero($tipo, $slug, $id)) throw new RuntimeException("C'è già " . ($tipo === 'pagine' ? 'una pagina' : 'un articolo') . " con l'indirizzo /$slug/.");

    $stato = ($d['stato'] ?? 'bozza') === 'pubblicato' ? 'published' : 'draft';
    $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['data'] ?? '')) ? $d['data'] : date('Y-m-d');
    // Senza ora: un contenuto che c'è tiene la sua, uno nuovo prende quella di adesso (fino alla 1.2.7 le 06:00, nel futuro
    // per chi scriveva di notte: la pagina appena pubblicata dall'editor restava programmata fino al mattino).
    $ora = preg_match('/^\d{2}:\d{2}$/', (string)($d['ora'] ?? '')) ? $d['ora'] . ':00' : (($prima && ($q = contenuti_quando($tipo, $prima)) !== '') ? substr($q, 11, 8) : date('H:i:s'));
    // sicurezza: il testo grezzo (HTML) lo scrive solo l'amministratore. Un redattore che cambia il testo lo passa dal sanificatore
    // (restano titoli, tabelle, immagini, classi; via script, eventi, stili pericolosi); se non lo ha toccato, resta com'era.
    $contenuto = (string)($d['contenuto'] ?? '');
    $formato = (string)($d['content_format'] ?? ($prima['content_format'] ?? 'html'));
    if (function_exists('editor_puo_codice') ? !editor_puo_codice() : (function_exists('is_logged_in') && is_logged_in() && function_exists('is_admin') && !is_admin())) {
        if ($formato !== 'markdown' && $contenuto !== (string)($prima[$t['campo_contenuto']] ?? null)) {
            require_once __DIR__ . '/sicurezza-html.php';
            $contenuto = sicurezza_html_pulito($contenuto, 'articolo');
        }
    }
    $v = [
        'title' => mb_substr($titolo, 0, 500), 'slug' => $slug, 'status' => $stato,
        $t['campo_contenuto'] => sito_senza_4byte($contenuto),
        'excerpt' => sito_senza_4byte(trim((string)($d['excerpt'] ?? ''))),
        'author' => mb_substr(trim((string)($d['author'] ?? '')) ?: (string)cms_impostazione('autore_predefinito', ''), 0, 100),
        'seo_title' => mb_substr(trim((string)($d['seo_title'] ?? '')), 0, 255),
        'seo_description' => trim((string)($d['seo_description'] ?? '')),
        'meta_description' => mb_substr(trim((string)($d['meta_description'] ?? '')), 0, 500),
        'robots' => in_array($d['robots'] ?? '', ['index', 'noindex'], true) ? $d['robots'] : '',
        'tldr' => sito_senza_4byte(trim((string)($d['tldr'] ?? ''))),
        'faq' => (string)($d['faq'] ?? ''),
        'immagine_url' => mb_substr(trim((string)($d['immagine'] ?? '')), 0, 500),
    ];
    // modelli: le tendine Header e Footer. Se il modulo non le manda, il valore salvato resta.
    foreach (['header_id', 'footer_id'] as $k) {
        if (array_key_exists($k, $d)) {
            require_once __DIR__ . '/modelli.php';
            $v[$k] = modelli_valore_scelta($d[$k]);
        }
    }
    if ($tipo === 'pagine') {
        $v['publish_at'] = "$data $ora";
        $v['publish_time'] = $ora;
    } else {
        $v['publish_date'] = $data;
        $v['publish_time'] = $ora;
        $v['featured_image'] = $v['immagine_url'];
        if ($t['attributi']) {
            $v['featured_image_alt'] = mb_substr(trim((string)($d['immagine_alt'] ?? '')), 0, 500);
            $v['noindex'] = $v['robots'] === 'noindex' ? 1 : 0;
            $v['type'] = ($d['type'] ?? 'cluster') === 'pillar' ? 'pillar' : 'cluster';
            $v['keyword_target'] = mb_substr(trim((string)($d['keyword_target'] ?? '')), 0, 255);
            $v['keywords_secondarie'] = trim((string)($d['keywords_secondarie'] ?? ''));
            $v['cta'] = mb_substr(trim((string)($d['cta'] ?? '')), 0, 100);
            $v['pillar_ref'] = mb_substr(trim((string)($d['pillar_ref'] ?? '')), 0, 255);
            $v['pillar_title'] = mb_substr(trim((string)($d['pillar_title'] ?? '')), 0, 500);
        }
        if ($t['citazione']) $v['author_quote'] = mb_substr(trim((string)($d['author_quote'] ?? '')), 0, 255);
        if (isset($d['content_format']) && in_array($d['content_format'], ['html', 'markdown'], true)) $v['content_format'] = $d['content_format'];
        $cats = array_values(array_filter(array_map('contenuti_slug', (array)($d['categorie'] ?? []))));
        if (trim((string)($d['categoria_nuova'] ?? '')) !== '') $cats[] = contenuti_categoria_crea((string)$d['categoria_nuova']);
        $cats = array_values(array_unique(array_intersect($cats, array_keys(contenuti_categorie()))));
        $v['categorie'] = implode(',', $cats);
        $v['category'] = $cats[0] ?? '';
        [$slugs, $nomi] = contenuti_tag_sistema((string)($d['tags'] ?? ''));
        $v['tag_slugs'] = implode(',', $slugs);
        $v['tags'] = implode(', ', $nomi);
    }

    if ($prima) {
        contenuti_revisione($tipo, $id, $prima, $autore, 'prima della modifica');
        $set = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($v)));
        $p = [];
        foreach ($v as $k => $x) $p[":$k"] = $x;
        $p[':id'] = $id;
        db()->prepare('UPDATE ' . tbl($t['tabella']) . " SET $set WHERE id = :id")->execute($p);
        // Indirizzo cambiato su un contenuto già uscito: 301 dal vecchio, così nessun link si rompe.
        if ($prima['slug'] !== $slug && contenuti_stato($tipo, $prima) === 'pubblicato') {
            contenuti_redirect(contenuti_percorso($tipo, $prima), contenuti_percorso($tipo, $v + $prima));
        }
        contenuti_versione_modelli($tipo, $id, $prima, $v); // versioni: header o footer cambiati, nota automatica
        contenuti_avvisa_motori($tipo, $id); // seo: IndexNow e Google sanno che la pagina è cambiata
        return $id;
    }
    $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($v)));
    $segna = implode(', ', array_map(fn($k) => ":$k", array_keys($v)));
    $p = [];
    foreach ($v as $k => $x) $p[":$k"] = $x;
    db()->prepare('INSERT INTO ' . tbl($t['tabella']) . " ($cols) VALUES ($segna)")->execute($p);
    $id = (int)db()->lastInsertId();
    contenuti_avvisa_motori($tipo, $id); // seo
    return $id;
}

/** seo: dopo un salvataggio, il pannello SEO e AEO (includes/seo.php) mette l'indirizzo in coda per IndexNow. Mai un errore da qui. */
function contenuti_avvisa_motori(string $tipo, int $id): void {
    try {
        require_once __DIR__ . '/seo.php';
        seo_notifica_contenuto($tipo, $id);
    } catch (Throwable $e) {
        error_log('contenuti_avvisa_motori: ' . $e->getMessage());
    }
}

/** Modifica rapida dall'elenco: titolo, slug, stato, data, ora, e per gli articoli categorie e tag. */
function contenuti_salva_rapido(string $tipo, int $id, array $d): void {
    $t = contenuti_tipo($tipo);
    $r = contenuti_leggi($tipo, $id);
    if (!$r) throw new RuntimeException('Contenuto non trovato.');
    $base = ['title' => $d['title'] ?? $r['title'], 'slug' => $d['slug'] ?? $r['slug'], 'stato' => $d['stato'] ?? (contenuti_stato($tipo, $r) === 'bozza' ? 'bozza' : 'pubblicato'),
        'data' => $d['data'] ?? substr(contenuti_quando($tipo, $r), 0, 10), 'ora' => $d['ora'] ?? substr(contenuti_quando($tipo, $r), 11, 5),
        'contenuto' => $r[$t['campo_contenuto']], 'excerpt' => $r['excerpt'], 'author' => $r['author'] ?? (string)cms_impostazione('autore_predefinito', ''),
        'seo_title' => $r['seo_title'], 'seo_description' => $r['seo_description'], 'meta_description' => $r['meta_description'],
        'robots' => $r['robots'], 'tldr' => $r['tldr'], 'faq' => $r['faq'], 'immagine' => $r['immagine_url'] ?: ($r['featured_image'] ?? '')];
    if ($t['tassonomie']) $base += ['categorie' => $d['categorie'] ?? contenuti_categorie_di($r), 'tags' => $d['tags'] ?? implode(', ', contenuti_tag_di($r))];
    if ($t['attributi']) {
        $base += ['immagine_alt' => $r['featured_image_alt'], 'type' => $r['type'], 'keyword_target' => $r['keyword_target'], 'keywords_secondarie' => $r['keywords_secondarie'],
            'cta' => $r['cta'], 'pillar_ref' => $r['pillar_ref'], 'pillar_title' => $r['pillar_title']];
    }
    if ($t['citazione']) $base['author_quote'] = $r['author_quote'];
    foreach (['header_id', 'footer_id'] as $k) if (array_key_exists($k, $d)) $base[$k] = $d[$k]; // modelli
    contenuti_salva($tipo, $id, $base, (string)($d['autore'] ?? ''));
}

/** versioni: se l'header o il footer scelti per il contenuto sono cambiati, una versione con la nota "header: X al posto di Y". Mai un errore da qui. */
function contenuti_versione_modelli(string $tipo, int $id, array $prima, array $nuovi): void {
    try {
        $note = [];
        foreach (['header_id' => 'header', 'footer_id' => 'footer'] as $k => $nome) {
            if (!array_key_exists($k, $nuovi) || (int)$nuovi[$k] === (int)($prima[$k] ?? 0)) continue;
            require_once __DIR__ . '/versioni.php';
            $note[] = $nome . ': ' . versioni_formatta_chiave($k, (int)$nuovi[$k], '') . ' al posto di ' . versioni_formatta_chiave($k, (int)($prima[$k] ?? 0), '');
        }
        if (!$note) return;
        $riga = contenuti_leggi($tipo, $id);
        if ($riga) editor_versione_salva('contenuto:' . $tipo, $id, (string)$riga['title'], versioni_dati_contenuto($riga), implode('; ', $note), ['autore' => (string)($nuovi['author'] ?? '')]);
    } catch (Throwable $e) {
        error_log('versioni (modelli del contenuto): ' . $e->getMessage());
    }
}

/** Una copia della riga, per le revisioni della scheda classica. Tiene le ultime CONTENUTI_REVISIONI. */
function contenuti_revisione(string $tipo, int $id, array $riga, string $autore, string $nota = ''): void {
    // versioni: la stessa copia entra nella cronologia unica (ambito contenuto:<tipo>, chiave "scheda").
    try { require_once __DIR__ . '/versioni.php'; editor_versione_salva('contenuto:' . $tipo, $id, (string)($riga['title'] ?? ''), ['scheda' => versioni_scheda_pulita($riga)], 'scheda: ' . $nota, ['autore' => $autore]); } catch (Throwable $e) { error_log('versioni (scheda): ' . $e->getMessage()); }
    unset($riga['struttura'], $riga['struttura_bozza']);
    $json = json_encode($riga, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $json = preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', fn($m) => sprintf('&#x%X;', mb_ord($m[0])), $json);
    $ultima = db()->prepare('SELECT dati FROM ' . tbl('revisioni') . ' WHERE tipo = ? AND contenuto_id = ? ORDER BY id DESC LIMIT 1');
    $ultima->execute([$tipo, $id]);
    if ($ultima->fetchColumn() === $json) return; // niente di nuovo da ricordare
    db()->prepare('INSERT INTO ' . tbl('revisioni') . ' (tipo, contenuto_id, dati, nota, autore, creato) VALUES (?, ?, ?, ?, ?, NOW())')->execute([$tipo, $id, $json, mb_substr($nota, 0, 255), mb_substr($autore, 0, 100)]);
    $vecchie = db()->prepare('SELECT id FROM ' . tbl('revisioni') . ' WHERE tipo = ? AND contenuto_id = ? ORDER BY id DESC LIMIT 1000 OFFSET ' . CONTENUTI_REVISIONI);
    $vecchie->execute([$tipo, $id]);
    $ids = array_map('intval', $vecchie->fetchAll(PDO::FETCH_COLUMN));
    if ($ids) db()->exec('DELETE FROM ' . tbl('revisioni') . ' WHERE id IN (' . implode(',', $ids) . ')');
}

function contenuti_revisioni(string $tipo, int $id): array {
    $st = db()->prepare('SELECT id, nota, autore, creato, LENGTH(dati) AS peso FROM ' . tbl('revisioni') . ' WHERE tipo = ? AND contenuto_id = ? ORDER BY id DESC');
    $st->execute([$tipo, $id]);
    return $st->fetchAll();
}

/** Ripristina una revisione: testo, titolo, slug e campi tornano com'erano; stato, data e ora restano quelli di adesso (come in WordPress: un ripristino non toglie dal sito un articolo pubblicato). Quella di adesso finisce nelle revisioni. */
function contenuti_ripristina_revisione(string $tipo, int $id, int $rev, string $autore): void {
    $t = contenuti_tipo($tipo);
    $st = db()->prepare('SELECT dati FROM ' . tbl('revisioni') . ' WHERE tipo = ? AND contenuto_id = ? AND id = ?');
    $st->execute([$tipo, $id, $rev]);
    $dati = json_decode((string)$st->fetchColumn(), true);
    if (!is_array($dati)) throw new RuntimeException('Revisione non trovata.');
    $adesso = contenuti_leggi($tipo, $id);
    if (!$adesso) throw new RuntimeException('Contenuto non trovato.');
    contenuti_revisione($tipo, $id, $adesso, $autore, 'prima del ripristino');
    $colonne = db()->query('SHOW COLUMNS FROM ' . tbl($t['tabella']))->fetchAll(PDO::FETCH_COLUMN);
    $set = []; $p = [':id' => $id];
    foreach ($dati as $k => $x) {
        if ($k === 'id' || !in_array($k, $colonne, true) || in_array($k, ['struttura', 'struttura_bozza', 'cestino', 'wp_post_id', 'wp_hash', 'wp_id', 'created_at', 'updated_at', 'status', 'publish_date', 'publish_time'], true)) continue;
        if ($k === 'slug' && !contenuti_slug_libero($tipo, (string)$x, $id)) continue;
        $set[] = "`$k` = :$k";
        $p[":$k"] = $x;
    }
    if ($set) db()->prepare('UPDATE ' . tbl($t['tabella']) . ' SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($p);
    // La revisione riporta un indirizzo diverso su un contenuto già uscito: 301 da quello di adesso,
    // e il vecchio redirect che puntava qui si scioglie (altrimenti l'indirizzo nuovo rimanderebbe a un 404).
    if (isset($p[':slug']) && (string)$p[':slug'] !== $adesso['slug'] && contenuti_stato($tipo, $adesso) === 'pubblicato') {
        contenuti_redirect(contenuti_percorso($tipo, $adesso), contenuti_percorso($tipo, ['slug' => (string)$p[':slug']] + $adesso));
    }
}

// ------------------------------------------------------------
// Cestino, duplicazione, azioni di massa
// ------------------------------------------------------------

function contenuti_cestina(string $tipo, array $ids): int {
    if (!$ids) return 0;
    $t = contenuti_tipo($tipo);
    if ($tipo === 'pagine') {
        $home = (string)get_setting('pagina_home', '');
        $st = db()->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?');
        $st->execute([$home]);
        $ids = array_diff($ids, [(int)$st->fetchColumn()]); // la home non va nel cestino
    }
    if (!$ids) return 0;
    $in = implode(',', array_map('intval', $ids));
    return db()->exec('UPDATE ' . tbl($t['tabella']) . " SET cestino = NOW() WHERE id IN ($in) AND cestino IS NULL");
}

function contenuti_ripristina(string $tipo, array $ids): int {
    if (!$ids) return 0;
    $in = implode(',', array_map('intval', $ids));
    return db()->exec('UPDATE ' . tbl(contenuti_tipo($tipo)['tabella']) . " SET cestino = NULL WHERE id IN ($in)");
}

/** Elimina per sempre: solo quello che sta nel cestino. */
function contenuti_elimina(string $tipo, array $ids): int {
    if (!$ids) return 0;
    $t = contenuti_tipo($tipo);
    $in = implode(',', array_map('intval', $ids));
    $n = db()->exec('DELETE FROM ' . tbl($t['tabella']) . " WHERE id IN ($in) AND cestino IS NOT NULL");
    db()->exec('DELETE FROM ' . tbl('revisioni') . " WHERE tipo = " . db()->quote($tipo) . " AND contenuto_id IN ($in) AND contenuto_id NOT IN (SELECT id FROM " . tbl($t['tabella']) . ')');
    // Anche le revisioni dell'editor visuale, che hanno il loro tipo (pagine, articoli, tipi dei moduli).
    db()->exec('DELETE FROM ' . tbl('pagine_revisioni') . " WHERE tipo = " . db()->quote($tipo) . " AND pagina_id IN ($in) AND pagina_id NOT IN (SELECT id FROM " . tbl($t['tabella']) . ')');
    return $n;
}

function contenuti_stato_massa(string $tipo, array $ids, string $stato): int {
    if (!$ids || !in_array($stato, ['bozza', 'pubblicato'], true)) return 0;
    if ($tipo === 'pagine' && $stato === 'bozza') {
        // La home non va in bozza: il sito resterebbe senza pagina iniziale.
        $st = db()->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?');
        $st->execute([(string)get_setting('pagina_home', '')]);
        $ids = array_diff($ids, [(int)$st->fetchColumn()]);
        if (!$ids) return 0;
    }
    $in = implode(',', array_map('intval', $ids));
    $s = $stato === 'bozza' ? 'draft' : 'published';
    // Chi viene pubblicato senza una data ne riceve una adesso: senza, il sito non lo mostrerebbe mai.
    $data = $s === 'draft' ? '' : ($tipo === 'pagine' ? ', publish_at = COALESCE(publish_at, NOW())' : ', publish_date = COALESCE(publish_date, CURDATE())');
    return db()->exec('UPDATE ' . tbl(contenuti_tipo($tipo)['tabella']) . " SET status = '$s'$data WHERE id IN ($in) AND cestino IS NULL");
}

/** Una copia in bozza, con "-copia" nell'indirizzo. Ritorna l'id nuovo. */
function contenuti_duplica(string $tipo, int $id): int {
    $t = contenuti_tipo($tipo);
    $r = contenuti_leggi($tipo, $id);
    if (!$r) throw new RuntimeException('Contenuto non trovato.');
    unset($r['id'], $r['created_at'], $r['updated_at'], $r['wp_post_id'], $r['wp_hash'], $r['wp_id'], $r['cestino'], $r['struttura_bozza']);
    $r['status'] = 'draft';
    $r['title'] = $r['title'] . ' (copia)';
    $base = $r['slug'] . '-copia';
    $n = 1;
    do { $slug = $base . ($n > 1 ? "-$n" : ''); $n++; } while (!contenuti_slug_libero($tipo, $slug, 0));
    $r['slug'] = $slug;
    $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($r)));
    $segna = implode(', ', array_fill(0, count($r), '?'));
    db()->prepare('INSERT INTO ' . tbl($t['tabella']) . " ($cols) VALUES ($segna)")->execute(array_values($r));
    return (int)db()->lastInsertId();
}

/** Conta le parole del testo di un contenuto (senza i tag). */
function contenuti_parole(string $html): int {
    return str_word_count(strip_tags(preg_replace('/\[IG:[\s\S]*?\[\/IG\]/', ' ', $html)) ?: '');
}

// ------------------------------------------------------------
// Densità delle keyword (dall'elenco articoli di prima)
// ------------------------------------------------------------

function contenuti_kw_density($content, $keyword) {
    if (!$content || !$keyword) return ['count' => 0, 'density' => 0, 'words' => 0, 'exact_count' => 0, 'variant_count' => 0, 'exact_density' => 0, 'variant_density' => 0];
    $clean = preg_replace('/[#*\[\]()_\-\n\\\\]/', ' ', $content);
    $clean = preg_replace('/\s+/', ' ', trim($clean));
    $totalWords = count(explode(' ', $clean));
    $text = strtolower($clean);
    $kw = strtolower(trim($keyword));
    $kwParts = array_filter(explode(' ', $kw));
    $kwWordCount = count($kwParts);

    // 1. Conteggio ESATTO (keyword così com'è)
    $exactCount = 0; $pos = 0;
    while (($pos = strpos($text, $kw, $pos)) !== false) { $exactCount++; $pos++; }
    $exactDensity = $totalWords > 0 ? round(($exactCount * $kwWordCount / $totalWords) * 100, 1) : 0;

    // 2. Conteggio VARIANTI (singole parole della keyword, escluse stop words)
    $stopWords = ['di','da','del','della','delle','dei','degli','il','la','le','lo','li','i','gli','un','una','uno','e','è','a','al','alla','alle','in','nel','nella','con','per','come','che','non','si','se','o','ma','vs'];
    $significantParts = array_filter($kwParts, function($w) use ($stopWords) {
        return strlen($w) > 2 && !in_array($w, $stopWords);
    });

    $variantCount = 0;
    foreach ($significantParts as $part) {
        $partCount = 0; $pos = 0;
        while (($pos = strpos($text, $part, $pos)) !== false) { $partCount++; $pos++; }
        $variantCount += $partCount;
    }
    // Sottrai le occorrenze già contate nell'esatto per non duplicare
    $variantOnly = max(0, $variantCount - ($exactCount * count($significantParts)));
    $variantDensity = $totalWords > 0 ? round(($variantOnly / $totalWords) * 100, 1) : 0;

    // Totale combinato
    $combinedDensity = $exactDensity + $variantDensity;

    return [
        'count' => $exactCount,
        'density' => $combinedDensity,
        'words' => $totalWords,
        'exact_count' => $exactCount,
        'exact_density' => $exactDensity,
        'variant_count' => $variantOnly,
        'variant_density' => $variantDensity,
    ];
}

function contenuti_kw_correlate($content, $keywords_sec) {
    if (!$content || !$keywords_sec) return ['count' => 0, 'density' => 0, 'details' => []];
    $clean = preg_replace('/[#*\[\]()_\-\n\\\\]/', ' ', $content);
    $clean = preg_replace('/\s+/', ' ', trim($clean));
    $totalWords = count(explode(' ', $clean));
    $text = strtolower($clean);
    $kws = array_map('trim', explode(',', $keywords_sec));
    $totalCount = 0;
    $totalKwWords = 0;
    $details = [];
    foreach ($kws as $kw) {
        $kw = strtolower(trim($kw));
        if (!$kw) continue;
        // Cerca esatta
        $count = 0; $pos = 0;
        while (($pos = strpos($text, $kw, $pos)) !== false) { $count++; $pos++; }
        // Se esatta = 0, cerca le parole singole significative
        if ($count === 0) {
            $stopWords = ['di','da','del','della','delle','dei','degli','il','la','le','lo','li','i','gli','un','una','uno','e','è','a','al','alla','alle','in','nel','nella','con','per','come','che','non','si','se','o','ma'];
            $parts = array_filter(explode(' ', $kw), function($w) use ($stopWords) { return strlen($w) > 2 && !in_array($w, $stopWords); });
            foreach ($parts as $p) {
                $pc = 0; $pos = 0;
                while (($pos = strpos($text, $p, $pos)) !== false) { $pc++; $pos++; }
                $count += $pc;
            }
        }
        $kwWords = count(explode(' ', $kw));
        $totalCount += $count;
        $totalKwWords += $count * $kwWords;
        if ($count > 0) $details[] = "$kw: {$count}x";
    }
    $density = $totalWords > 0 ? round(($totalKwWords / $totalWords) * 100, 1) : 0;
    return ['count' => $totalCount, 'density' => $density, 'details' => $details];
}


