<?php
/**
 * Il sito: tutto quello che serve al publisher per esserne il motore.
 *
 *  - schema: colonne e tabelle nuove, create da sole al primo uso (idempotente, MySQL 5.6)
 *  - render: l'HTML finale di un articolo
 *  - importazione: articoli e contenuti dai pacchetti zip
 *
 * Usato da: includes/aggiornamenti.php, includes/sito-dati.php,
 * admin/article-edit.php, admin/impostazioni.php, cron-daily.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/infographic.php';

const SITO_ORA_PREDEFINITA = '06:00:00';
// L'indirizzo del sito: impostazione sito_url, poi il modulo del sito, poi la richiesta (includes/cms.php).
define('SITO_URL', cms_url_sito());
/** Categorie i cui contenuti e archivi sono noindex: le dicono i moduli del sito. */
function sito_categorie_noindex(): array {
    static $c = null;
    return $c ??= array_values(array_unique((array)cms_filtro('categorie_noindex', [])));
}

// ------------------------------------------------------------
// Schema
// ------------------------------------------------------------

function sito_colonna_esiste(string $tabella, string $colonna): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
    $st->execute([':t' => DB_PREFIX . $tabella, ':c' => $colonna]);
    return (int)$st->fetchColumn() > 0;
}

/** Aggiunge colonne e tabelle che mancano. Si può chiamare ogni volta: se è tutto a posto non fa niente. */
function sito_schema_aggiorna(): array {
    static $fatto = null;
    if ($fatto !== null) return $fatto;
    $log = [];
    $colonne = [
        'articles' => [
            'publish_time'   => "TIME NOT NULL DEFAULT '06:00:00'",
            'content_format' => "VARCHAR(10) NOT NULL DEFAULT 'markdown'",
            'tldr'           => 'TEXT',
            'faq'            => 'TEXT',
            'robots'         => "VARCHAR(10) NOT NULL DEFAULT ''",
            'categorie'      => "VARCHAR(500) NOT NULL DEFAULT ''",
            'tag_slugs'      => 'TEXT',
            'immagine_url'   => "VARCHAR(500) NOT NULL DEFAULT ''",
            'wp_id'          => 'INT(11) DEFAULT NULL',
        ],
        'pages' => [
            'html'             => 'LONGTEXT',
            'seo_title'        => "VARCHAR(255) NOT NULL DEFAULT ''",
            'seo_description'  => 'TEXT',
            'robots'           => "VARCHAR(10) NOT NULL DEFAULT ''",
            'tldr'             => 'TEXT',
            'faq'              => 'TEXT',
            'servizio'         => 'TEXT',
            'immagine_url'     => "VARCHAR(500) NOT NULL DEFAULT ''",
            'publish_at'       => 'DATETIME DEFAULT NULL',
            'wp_id'            => 'INT(11) DEFAULT NULL',
            // Editor delle pagine (includes/editor.php): versione pubblica, bozza, ultima modifica.
            'struttura'            => 'LONGTEXT',
            'struttura_bozza'      => 'LONGTEXT',
            'struttura_aggiornata' => 'DATETIME DEFAULT NULL',
        ],
        'categories' => [
            'robots' => "VARCHAR(10) NOT NULL DEFAULT ''",
        ],
    ];
    // Le colonne dei tipi di contenuto dei moduli (per esempio la tabella di un catalogo).
    $colonne = cms_filtro('schema_colonne', $colonne);
    // Articoli, pagine e tipi dei moduli gestiti allo stesso modo (cestino, revisioni, editor visuale).
    require_once __DIR__ . '/contenuti.php';
    foreach (contenuti_schema_colonne() as $tab => $cols) $colonne[$tab] = ($colonne[$tab] ?? []) + $cols;
    $colonne['pagine_revisioni'] = ['tipo' => "VARCHAR(20) NOT NULL DEFAULT 'pagine'"];
    $tabelle = sito_schema_tabelle() + contenuti_schema_tabelle();
    // Il sito pubblico chiama questa funzione a ogni pagina: se lo schema è già quello giusto, nessuna query.
    // Il nome del database fa parte dell'impronta: su un server nuovo il controllo riparte anche se le impostazioni arrivano copiate.
    $impronta = md5(serialize([$colonne, $tabelle, DB_NAME]));
    if (get_setting('sito_schema_impronta', '') === $impronta) return $fatto = [];
    foreach ($tabelle as $nome => $sql) {
        $esiste = db()->query('SHOW TABLES LIKE ' . db()->quote(DB_PREFIX . $nome))->fetchColumn();
        if (!$esiste) { db()->exec($sql); $log[] = "tabella creata: $nome"; }
    }
    foreach ($colonne as $tab => $cols) {
        foreach ($cols as $col => $def) {
            if (!sito_colonna_esiste($tab, $col)) {
                db()->exec('ALTER TABLE ' . tbl($tab) . " ADD COLUMN `$col` $def");
                $log[] = "colonna aggiunta: $tab.$col";
            }
        }
    }
    sito_viste();
    save_setting('sito_schema_impronta', $impronta);
    return $fatto = $log;
}

/**
 * Le due viste del publisher (articoli usciti e programmati). Si ricreano invece di copiarle:
 * in un'esportazione portano l'utente del vecchio server (DEFINER), e su un hosting cPanel
 * l'importazione di quelle righe si ferma per mancanza di permessi.
 */
/** Le viste mancano (database importato senza): si ricreano. Una query. */
function sito_viste_se_mancano(): void {
    $n = (int)db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ("
        . db()->quote(DB_PREFIX . 'v_published_articles') . ', ' . db()->quote(DB_PREFIX . 'v_scheduled_articles') . ')')->fetchColumn();
    if ($n < 2) sito_viste();
}

function sito_viste(): void {
    db()->exec('CREATE OR REPLACE VIEW ' . tbl('v_published_articles') . ' AS SELECT * FROM ' . tbl('articles')
        . " WHERE `status` = 'published' AND `cestino` IS NULL AND `publish_date` IS NOT NULL AND `publish_date` <= CURDATE()");
    db()->exec('CREATE OR REPLACE VIEW ' . tbl('v_scheduled_articles') . ' AS SELECT * FROM ' . tbl('articles')
        . " WHERE `status` IN ('published','scheduled') AND `cestino` IS NULL AND `publish_date` > CURDATE()");
}

function sito_schema_tabelle(): array {
    return [
        'glossario' => "CREATE TABLE IF NOT EXISTS " . tbl('glossario') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(255) NOT NULL,
            `title` VARCHAR(500) NOT NULL,
            `definizione` TEXT,
            `uso_in_ufficio` TEXT,
            `html` LONGTEXT,
            `status` ENUM('draft','published') NOT NULL DEFAULT 'published',
            `publish_at` DATETIME DEFAULT NULL,
            `wp_id` INT(11) DEFAULT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`), UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'redirect' => "CREATE TABLE IF NOT EXISTS " . tbl('redirect') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `da` VARCHAR(500) NOT NULL,
            `a` VARCHAR(500) NOT NULL,
            `codice` SMALLINT NOT NULL DEFAULT 301,
            `confronto` VARCHAR(10) NOT NULL DEFAULT 'exact',
            `attivo` TINYINT(1) NOT NULL DEFAULT 1,
            `origine` VARCHAR(30) NOT NULL DEFAULT '',
            `creato` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`), UNIQUE KEY `da` (`da`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // Le ultime versioni pubblicate di ogni pagina fatta con l'editor.
        'pagine_revisioni' => "CREATE TABLE IF NOT EXISTS " . tbl('pagine_revisioni') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `pagina_id` INT(11) NOT NULL,
            `struttura` LONGTEXT NOT NULL,
            `nota` VARCHAR(255) NOT NULL DEFAULT '',
            `autore` VARCHAR(100) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `tipo` VARCHAR(20) NOT NULL DEFAULT 'pagine',
            PRIMARY KEY (`id`), KEY `pagina` (`pagina_id`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
}

// ------------------------------------------------------------
// Testo
// ------------------------------------------------------------

/**
 * I caratteri sopra U+FFFF (emoji) diventano entità HTML: la colonna utf8 a 3 byte
 * taglierebbe il testo in quel punto senza errore. L'entità è testo semplice e il browser la mostra uguale.
 */
function sito_senza_4byte(string $s): string {
    return (string)preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', fn($m) => sprintf('&#x%X;', mb_ord($m[0], 'UTF-8')), $s);
}

/** HTML finale di un articolo: in HTML passa com'è (con le infografiche del configuratore), in Markdown si costruisce. */
function sito_html_articolo(array $a): string {
    if (($a['content_format'] ?? 'markdown') === 'html') {
        $html = (string)$a['content'];
        // Le infografiche del configuratore ([IG:tipo:titolo] ... [/IG]) si disegnano anche nel testo HTML.
        if (str_contains($html, '[IG:')) {
            if (!function_exists('process_infographic_shortcodes')) require_once __DIR__ . '/infographic.php';
            $html = process_infographic_shortcodes($html);
        }
        return $html;
    }
    require_once __DIR__ . '/corpo-articolo.php';
    return corpo_articolo_markdown($a);
}

function sito_lista(string $csv): array {
    return array_values(array_filter(array_map('trim', explode(',', $csv)), 'strlen'));
}

function sito_robots_articolo(array $a): string {
    if (in_array($a['robots'] ?? '', ['index', 'noindex'], true)) return $a['robots'];
    if (!empty($a['noindex'])) return 'noindex';
    $cats = sito_lista($a['categorie'] ?? '') ?: [($a['category'] ?? '')];
    return array_intersect($cats, sito_categorie_noindex()) ? 'noindex' : 'index';
}

// ------------------------------------------------------------
// Importazione dai pacchetti
// ------------------------------------------------------------

/**
 * Una pagina fatta con l'editor, da un pacchetto di aggiornamento (1.2.3, voce "pagine" del manifest).
 * JSON: {slug, titolo, struttura (albero dell'editor), seo_titolo, seo_descrizione, robots, estratto}.
 * La pagina con lo stesso slug si aggiorna (se era nel cestino ne esce), altrimenti nasce; esce pubblicata.
 * L'albero passa da editor_valida come quello dell'editor, e il salvataggio lascia una versione: quella di prima
 * resta in Editor > Versioni.
 */
function sito_importa_pagina(array $j): string {
    require_once __DIR__ . '/editor-controlli.php';
    require_once __DIR__ . '/editor.php';
    editor_carica_widget_esterni();
    $slug = trim((string)($j['slug'] ?? ''));
    if (!preg_match('/^[a-z0-9-]{1,100}$/', $slug)) throw new RuntimeException("Pagina: slug non valido: $slug");
    $titolo = sito_senza_4byte(trim((string)($j['titolo'] ?? '')));
    if ($titolo === '') throw new RuntimeException("Pagina $slug senza titolo");
    $albero = editor_valida(is_array($j['struttura'] ?? null) ? $j['struttura'] : null);
    if ($albero === null || !$albero['elementi']) throw new RuntimeException("Pagina $slug: struttura dell'editor mancante o non valida");
    $campi = [
        'title' => $titolo,
        'status' => 'published',
        'seo_title' => mb_substr(sito_senza_4byte((string)($j['seo_titolo'] ?? '')), 0, 255),
        'seo_description' => sito_senza_4byte((string)($j['seo_descrizione'] ?? '')),
        'meta_description' => mb_substr(sito_senza_4byte((string)($j['seo_descrizione'] ?? '')), 0, 500),
        'robots' => in_array($j['robots'] ?? '', ['index', 'noindex'], true) ? $j['robots'] : '',
        'excerpt' => sito_senza_4byte((string)($j['estratto'] ?? '')),
    ];
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?');
    $st->execute([$slug]);
    $id = (int)$st->fetchColumn();
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($campi)));
        $pdo->prepare('UPDATE ' . tbl('pages') . " SET $set, cestino = NULL WHERE id = ?")->execute([...array_values($campi), $id]);
        $esito = 'aggiornata';
    } else {
        $campi['slug'] = $slug;
        $campi['publish_at'] = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO ' . tbl('pages') . ' (`' . implode('`, `', array_keys($campi)) . '`) VALUES (' . implode(', ', array_fill(0, count($campi), '?')) . ')')->execute(array_values($campi));
        $id = (int)$pdo->lastInsertId();
        $esito = 'creata';
    }
    editor_salva($id, $albero, true, 'pacchetto', 'dal pacchetto di aggiornamento');
    return "Pagina /$slug/ $esito (" . count($albero['elementi']) . ' sezioni).';
}

/**
 * I file da scaricare dal sito (1.2.3; download manager dalla 1.2.4, includes/scaricabili.php): stanno in
 * uploads/scaricabili/ e si aprono da /scarica/<nome>/, senza estensione nell'indirizzo (il .htaccess del CMS vieta gli
 * .zip, e cosi l'indirizzo non dipende dal server). Ritorna il percorso del file, oppure null.
 */
function sito_scaricabile(string $nome): ?string {
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,120}$/', $nome) || str_contains($nome, '..')) return null;
    require_once __DIR__ . '/scaricabili.php';
    $x = scaricabili_leggi($nome);
    return $x ? scaricabili_dir() . '/' . $x['file'] : null;
}

/** Manda un file scaricabile: tipo giusto, come allegato, una cache di un'ora; e lo conta (non per i robot). */
function sito_manda_scaricabile(string $file): void {
    require_once __DIR__ . '/scaricabili.php';
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . SCARICABILI_TIPI[$ext][0]);
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=3600');
    header('X-Robots-Tag: noindex');
    scaricabili_conta($file);
    readfile($file);
}

/**
 * Inserisce o aggiorna un articolo da un pacchetto.
 * Campi del JSON: slug, titolo, corpo_html o corpo_markdown, pubblicazione (AAAA-MM-GGTHH:MM),
 * stato (published|draft), categoria, categorie[], tag[], tipo (pillar|cluster),
 * seo_titolo, seo_descrizione, keyword, correlate[], tldr, faq[], robots, immagine, cta, estratto.
 */
function sito_importa_articolo(array $j): string {
    sito_schema_aggiorna();
    $slug = trim((string)($j['slug'] ?? ''));
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) throw new RuntimeException("Slug non valido: $slug");
    $titolo = trim((string)($j['titolo'] ?? ''));
    if ($titolo === '') throw new RuntimeException("Articolo $slug senza titolo");
    $pub = trim((string)($j['pubblicazione'] ?? ''));
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?$/', $pub, $m)) {
        throw new RuntimeException("Articolo $slug: pubblicazione deve essere AAAA-MM-GGTHH:MM, trovato '$pub'");
    }
    $html = array_key_exists('corpo_html', $j);
    $corpo = sito_senza_4byte((string)($html ? $j['corpo_html'] : ($j['corpo_markdown'] ?? '')));
    if (trim($corpo) === '') throw new RuntimeException("Articolo $slug senza corpo");
    $categorie = array_values(array_filter((array)($j['categorie'] ?? [])));
    $categoria = (string)($j['categoria'] ?? ($categorie[0] ?? ''));
    $tag = array_values(array_filter((array)($j['tag'] ?? [])));
    $faq = array_values(array_filter((array)($j['faq'] ?? []), fn($f) => !empty($f['domanda']) && !empty($f['risposta'])));
    $stato = in_array($j['stato'] ?? 'published', ['published', 'draft'], true) ? ($j['stato'] ?? 'published') : 'published';

    $riga = [
        'slug' => $slug,
        'title' => sito_senza_4byte($titolo),
        'content' => $corpo,
        'content_format' => $html ? 'html' : 'markdown',
        'excerpt' => sito_senza_4byte((string)($j['estratto'] ?? '')),
        'type' => ($j['tipo'] ?? '') === 'pillar' ? 'pillar' : 'cluster',
        'category' => $categoria,
        'categorie' => implode(',', $categorie ?: [$categoria]),
        'tags' => implode(', ', $tag),
        'tag_slugs' => implode(',', array_map('sito_slug', $tag)),
        'publish_date' => $m[1],
        'publish_time' => $m[2] . ':00',
        'status' => $stato,
        'seo_title' => sito_senza_4byte((string)($j['seo_titolo'] ?? '')),
        'seo_description' => sito_senza_4byte((string)($j['seo_descrizione'] ?? '')),
        'meta_description' => sito_senza_4byte((string)($j['seo_descrizione'] ?? '')),
        'keyword_target' => (string)($j['keyword'] ?? ''),
        'keywords_secondarie' => implode(', ', (array)($j['correlate'] ?? [])),
        'tldr' => sito_senza_4byte((string)($j['tldr'] ?? '')),
        'faq' => $faq ? json_encode($faq, JSON_UNESCAPED_UNICODE) : '',
        'robots' => in_array($j['robots'] ?? '', ['index', 'noindex'], true) ? $j['robots'] : '',
        'immagine_url' => (string)($j['immagine'] ?? ''),
        'cta' => (string)($j['cta'] ?? ''),
    ];
    // Un modulo può prendersi il contenuto (per esempio "tipo": "ricetta" va nella tabella delle ricette).
    $esito = cms_filtro('importa_contenuto', null, $j, $riga, $categoria);
    if (is_string($esito)) return $esito;
    $cols = array_keys($riga);
    $sql = 'INSERT INTO ' . tbl('articles') . ' (`' . implode('`,`', $cols) . '`) VALUES (:' . implode(',:', $cols) . ')
            ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", array_diff($cols, ['slug'])));
    $st = db()->prepare($sql);
    foreach ($riga as $k => $v) $st->bindValue(":$k", $v);
    $st->execute();
    foreach ($tag as $t) sito_upsert_tag(sito_slug($t), $t);
    $quando = $m[1] . ' ' . $m[2];
    return "articolo $slug: " . ($st->rowCount() === 1 ? 'inserito' : 'aggiornato') . ", esce il $quando" . ($stato === 'draft' ? ' (in bozza)' : '');
}


function sito_slug(string $nome): string {
    $s = strtolower(trim($nome));
    $s = strtr($s, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', "'" => '']);
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}

function sito_upsert_tag(string $slug, string $nome): void {
    if ($slug === '') return;
    db()->prepare('INSERT INTO ' . tbl('tags') . ' (slug, name) VALUES (:s, :n) ON DUPLICATE KEY UPDATE name = name')
        ->execute([':s' => $slug, ':n' => sito_senza_4byte($nome)]);
}

function sito_salva_redirect(string $da, string $a, int $codice = 301, string $confronto = 'exact', string $origine = 'pacchetto'): string {
    sito_schema_aggiorna();
    $da = trim($da);
    if ($confronto === 'exact' && !str_starts_with($da, '/?') && !str_contains($da, '?')) $da = '/' . trim(preg_replace('~^https?://[^/]+~', '', $da), '/') . '/';
    $host = preg_quote(preg_replace('~^www\.~', '', (string)parse_url(SITO_URL, PHP_URL_HOST)), '~');
    $a = preg_replace('~^https?://(www\.)?' . $host . '~', '', trim($a)) ?: '/';
    db()->prepare('INSERT INTO ' . tbl('redirect') . ' (da, a, codice, confronto, origine) VALUES (:d, :a, :c, :k, :o)
                   ON DUPLICATE KEY UPDATE a = VALUES(a), codice = VALUES(codice), confronto = VALUES(confronto), attivo = 1')
        ->execute([':d' => $da, ':a' => $a, ':c' => $codice, ':k' => $confronto, ':o' => $origine]);
    return "redirect $da -> $a";
}
