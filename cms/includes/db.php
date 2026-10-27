<?php
require_once __DIR__ . '/config.php';

// Prefisso centralizzato per ogni tabella/view
// Il prefisso delle tabelle: CMS_PREFISSO dal config.local.php (l'installatore lo scrive; su danilofailli.it resta newdf_).
if (!defined('DB_PREFIX')) define('DB_PREFIX', CMS_PREFISSO);

function tbl(string $name): string {
    return '`' . DB_PREFIX . $name . '`';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // MySQL alla stessa ora di PHP (scarto calcolato adesso, ora legale compresa): NOW() e time()
        // devono dire la stessa cosa, se no un articolo programmato alle 06:00 esce alle 07:00 o alle 08:00.
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        // Stessa modalità SQL ovunque, quella predefinita di MySQL 8: in locale e sul server le query rispondono uguali.
        // Senza, un hosting con ANSI_QUOTES leggerebbe "published" come nome di colonna, e uno senza STRICT_TRANS_TABLES
        // taglierebbe i testi troppo lunghi in silenzio.
        $pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }
    return $pdo;
}

function get_published_articles(int $limit = 100, int $offset = 0): array {
    $stmt = db()->prepare(
        'SELECT * FROM ' . tbl('v_published_articles') . '
         ORDER BY publish_date DESC, id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_published_articles(): int {
    return (int) db()->query('SELECT COUNT(*) FROM ' . tbl('v_published_articles'))->fetchColumn();
}

function is_admin_preview(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/sicurezza.php';
        sicurezza_sessione_avvia(); // sicurezza: cookie HttpOnly, SameSite, Secure anche per l'anteprima
    }
    return !empty($_SESSION['admin_user_id']) && !empty($_GET['preview']);
}

function get_article_by_slug(string $slug): ?array {
    $table = is_admin_preview() ? tbl('articles') : tbl('v_published_articles');
    $stmt = db()->prepare(
        'SELECT * FROM ' . $table . ' WHERE slug = :slug'
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_articles_by_category(string $category, int $limit = 50): array {
    $table = is_admin_preview() ? tbl('articles') : tbl('v_published_articles');
    $stmt = db()->prepare(
        'SELECT * FROM ' . $table . '
         WHERE category = :cat
         ORDER BY type DESC, publish_date DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':cat', $category);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_articles_by_tag(string $tag, int $limit = 50): array {
    $table = is_admin_preview() ? tbl('articles') : tbl('v_published_articles');
    $stmt = db()->prepare(
        'SELECT * FROM ' . $table . '
         WHERE FIND_IN_SET(:tag, tags)
         ORDER BY publish_date DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':tag', $tag);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_recent_articles(int $limit = 3): array {
    $stmt = db()->prepare(
        'SELECT * FROM ' . tbl('v_published_articles') . '
         ORDER BY publish_date DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_setting(string $key, $default = null) {
    try {
        $stmt = db()->prepare('SELECT setting_value FROM ' . tbl('settings') . ' WHERE setting_key = :k');
        $stmt->execute([':k' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (\Exception $e) {
        return $default;
    }
}

function save_setting(string $key, string $value): void {
    $sql = 'INSERT INTO ' . tbl('settings') . ' (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2';
    $stmt = db()->prepare($sql);
    $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

function get_homepage_data(): array {
    $json = get_setting('homepage_content');
    if ($json) {
        $data = json_decode($json, true);
        if (is_array($data)) return $data;
    }
    return [];
}

function get_page_by_slug(string $slug): ?array {
    $stmt = db()->prepare('SELECT * FROM ' . tbl('pages') . ' WHERE slug = :slug AND status = "published" LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_all_pages(): array {
    return db()->query('SELECT * FROM ' . tbl('pages') . ' ORDER BY title ASC')->fetchAll();
}

function save_form_submission(string $formType, array $data): int {
    $stmt = db()->prepare('INSERT INTO ' . tbl('form_submissions') . '
        (form_type, page_slug, name, email, phone, message, extra_data, ip_address)
        VALUES (:type, :page, :name, :email, :phone, :msg, :extra, :ip)');
    $stmt->execute([
        ':type'  => $formType,
        ':page'  => $data['page_slug'] ?? '',
        ':name'  => $data['name'] ?? '',
        ':email' => $data['email'] ?? '',
        ':phone' => $data['phone'] ?? '',
        ':msg'   => $data['message'] ?? '',
        ':extra' => !empty($data['extra']) ? json_encode($data['extra'], JSON_UNESCAPED_UNICODE) : '',
        ':ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    return (int)db()->lastInsertId();
}

function count_downloads(string $slug): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM ' . tbl('form_submissions') . ' WHERE form_type = "ebook" AND page_slug = :s');
    $stmt->execute([':s' => $slug]);
    return (int)$stmt->fetchColumn();
}

function get_random_aforismi(int $count = 3, string $category = ''): array {
    $sql = 'SELECT * FROM ' . tbl('aforismi') . ' WHERE status = "published"';
    $params = [];
    if ($category) {
        $sql .= ' AND category = :cat';
        $params[':cat'] = $category;
    }
    $sql .= ' ORDER BY RAND() LIMIT ' . (int)$count;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
