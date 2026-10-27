<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

function admin_get_all_articles(string $status = '', string $category = '', string $search = ''): array {
    $sql = 'SELECT * FROM ' . tbl('articles') . ' WHERE 1=1';
    $params = [];
    if ($status) { $sql .= ' AND status = :status'; $params[':status'] = $status; }
    if ($category) { $sql .= ' AND category = :cat'; $params[':cat'] = $category; }
    if ($search) { $q = '%' . addcslashes($search, '%_\\') . '%'; $sql .= " AND (title LIKE :q1 ESCAPE '\\\\' OR slug LIKE :q2 ESCAPE '\\\\' OR keyword_target LIKE :q3 ESCAPE '\\\\')"; $params[':q1'] = $q; $params[':q2'] = $q; $params[':q3'] = $q; }
    $sql .= ' ORDER BY publish_date DESC, id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function admin_get_article(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM ' . tbl('articles') . ' WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function admin_save_article(array $data, ?int $id = null): int {
    $fields = [
        'title', 'slug', 'keyword_target', 'keywords_secondarie',
        'meta_description', 'excerpt', 'content', 'featured_image',
        'featured_image_alt', 'type', 'category', 'tags', 'cta',
        'pillar_ref', 'pillar_title', 'publish_date', 'status',
        'seo_title', 'seo_description', 'noindex', 'author',
        'infographic_type', 'infographic_title', 'infographic_data',
        'publish_time', 'tldr', 'faq', 'robots',
    ];

    if ($id) {
        $sets = [];
        $params = [':id' => $id];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "`$f` = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        $sql = 'UPDATE ' . tbl('articles') . ' SET ' . implode(', ', $sets) . ' WHERE id = :id';
        db()->prepare($sql)->execute($params);
        return $id;
    } else {
        $cols = []; $placeholders = []; $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $cols[] = "`$f`";
                $placeholders[] = ":$f";
                $params[":$f"] = $data[$f];
            }
        }
        $sql = 'INSERT INTO ' . tbl('articles') . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
        db()->prepare($sql)->execute($params);
        return (int) db()->lastInsertId();
    }
}

/** FAQ dal testo dell'editor: blocchi separati da una riga vuota, prima riga la domanda. Ritorna JSON. */
function admin_faq_da_testo(string $testo): string {
    $faq = [];
    foreach (preg_split('/\R\s*\R/', trim($testo)) as $blocco) {
        $righe = preg_split('/\R/', trim($blocco));
        $domanda = trim(array_shift($righe) ?? '');
        $risposta = trim(implode(' ', array_map('trim', $righe)));
        if ($domanda !== '' && $risposta !== '') $faq[] = ['domanda' => $domanda, 'risposta' => $risposta];
    }
    return $faq ? json_encode($faq, JSON_UNESCAPED_UNICODE) : '';
}

function admin_faq_a_testo(?string $json): string {
    $faq = json_decode((string)$json, true);
    if (!is_array($faq)) return '';
    return implode("\n\n", array_map(fn($f) => ($f['domanda'] ?? '') . "\n" . ($f['risposta'] ?? ''), $faq));
}

function admin_delete_article(int $id): void {
    db()->prepare('DELETE FROM ' . tbl('articles') . ' WHERE id = :id')->execute([':id' => $id]);
}

function admin_count_by_status(): array {
    $rows = db()->query(
        'SELECT status, COUNT(*) as cnt FROM ' . tbl('articles') . ' GROUP BY status'
    )->fetchAll();
    $counts = ['draft' => 0, 'published' => 0, 'scheduled' => 0, 'total' => 0];
    foreach ($rows as $r) {
        $counts[$r['status']] = (int)$r['cnt'];
        $counts['total'] += (int)$r['cnt'];
    }
    // Published but future = scheduled
    $future = (int) db()->query(
        'SELECT COUNT(*) FROM ' . tbl('articles') . ' WHERE status="published" AND publish_date > CURDATE()'
    )->fetchColumn();
    $counts['live'] = $counts['published'] - $future;
    $counts['future'] = $future;
    return $counts;
}

function admin_get_categories_list(): array {
    return [
        'stress-da-lavoro', 'burnout', 'colleghi-tossici', 'capo-tossico',
        'mobbing', 'conflitti-e-comunicazione', 'ansia-e-confini',
        'coaching-e-pnl', 'autosabotaggio', 'crescita-e-cambiamento',
    ];
}

// sicurezza: handle_upload() (si fidava del tipo dichiarato dal browser) e stata tolta: i caricamenti passano da media_carica() in includes/media.php.
