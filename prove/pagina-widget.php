<?php
// Crea (o rifà) la pagina "tutti-i-widget" con un widget per ogni tipo, valori di partenza. Uso: php pagina-widget.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1:8099'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$tipi = array_values(array_diff(array_keys(editor_definizioni()), ["sezione", "colonna"]));
$sezioni = [];
foreach (array_chunk($tipi, 4) as $gruppo) {
    $col = [];
    foreach ($gruppo as $t) $col[] = ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [editor_widget($t)]];
    $sezioni[] = ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => $col];
}
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['tutti-i-widget']);
$id = (int)$st->fetchColumn();
if (!$id) {
    $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Tutti i widget', 'tutti-i-widget', 'published')")->execute();
    $id = (int)$pdo->lastInsertId();
}
editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'pagina di prova');
echo count($tipi) . " widget nella pagina $id: " . implode(' ', $tipi) . "\n";
