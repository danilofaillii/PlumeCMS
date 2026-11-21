<?php
// La pagina /vetrina-widget/ con i due widget della Vetrina (addon 2.0.0): Carrello e Prodotti in evidenza.
// Per prova-addon-vetrina.js, con l'addon già acceso. uso: php prove/pagina-vetrina.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
// Con "togli": la pagina di prova si cancella (restano solo le sue versioni nella tabella delle versioni).
if (($argv[2] ?? '') === 'togli') {
    $st = db()->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['vetrina-widget']);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) db()->prepare('DELETE FROM ' . tbl('pages') . ' WHERE id = ?')->execute([$id]);
    echo "pagina tolta\n"; exit;
}
$w = fn(string $t, array $imp) => ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [array_replace_recursive(editor_widget($t), ['impostazioni' => $imp])]];
$sez = fn(array $col) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => $col];
$sezioni = [
    $sez([$w('vetrina_carrello', ['testo' => 'Il mio carrello', 'allinea' => 'right'])]),
    $sez([$w('vetrina_prodotti', ['quali' => 'evidenza', 'quanti' => 4, 'colonne' => 3])]),
];
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['vetrina-widget']);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Vetrina widget', 'vetrina-widget', 'published')")->execute(); $id = (int)$pdo->lastInsertId(); }
editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'pagina di prova');
echo "pagina $id\n";
