<?php
// Le sezioni pronte (1.3.0): /sezioni-pronte/ con tutte le varianti una sotto l'altra, e /sezioni-<categoria>/ per
// ognuna delle categorie. Uso: php pagina-sezioni.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
require_once $argv[1] . '/includes/sezioni-pronte.php';
editor_carica_widget_esterni();
$pdo = db();
$salva = function (string $slug, string $titolo, array $sezioni) use ($pdo) {
    $st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
    $id = (int)$st->fetchColumn();
    if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute([$titolo, $slug]); $id = (int)$pdo->lastInsertId(); }
    editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'pagina di prova');
    return $id;
};
$tutte = []; $perCat = [];
foreach (sezioni_pronte() as $id => $v) {
    $el = sezioni_pronte_elementi($id);
    if (!$el) { fwrite(STDERR, "variante vuota: $id\n"); continue; }
    $tutte = array_merge($tutte, $el);
    $perCat[$v['categoria']] = array_merge($perCat[$v['categoria']] ?? [], $el);
}
$salva('sezioni-pronte', 'Sezioni pronte', $tutte);
foreach ($perCat as $c => $el) $salva('sezioni-' . $c, 'Sezioni ' . $c, $el);
echo count(sezioni_pronte()) . ' varianti, ' . count($tutte) . " sezioni\n";
