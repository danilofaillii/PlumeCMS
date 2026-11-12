<?php
// Crea (o rifà) la pagina "prova-foto" per prove/prova-foto-leggere.js (1.4.0): un widget Immagine con la foto data e una
// sezione con la stessa foto come sfondo (per l'url() nel foglio della pagina). Uso: php pagina-foto.php <cartella CMS> <indirizzo della foto>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$img = editor_widget('immagine');
$img['impostazioni'] = ['immagine' => $argv[2], 'alt' => 'Foto di prova'] + $img['impostazioni'];
$sez = fn(array $w, array $imp = []) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => $imp, 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
$tit = editor_widget('titolo'); $tit['impostazioni'] = ['testo' => 'Sezione con sfondo', 'livello' => 'h2'] + $tit['impostazioni'];
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['prova-foto']);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Prova foto', 'prova-foto', 'published')")->execute(); $id = (int)$pdo->lastInsertId(); }
editor_salva($id, editor_valida(['elementi' => [$sez($img), $sez($tit, ['sfondo' => 'immagine', 'immagine_sfondo' => $argv[2], 'velo' => 20])]]), true, 'prova', 'pagina di prova');
echo $id . "\n";
