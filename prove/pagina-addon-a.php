<?php
// Crea (o rifà) una pagina pubblicata con i widget degli addon accesi (Accesso membri, Elenco corsi), uno per sezione.
// Per prove/prova-addon-membri.js e prove/prova-addon-corsi.js: l'addon va acceso prima dal pannello.
// Uso: php pagina-addon-a.php <cartella CMS> <slug> <titolo> <widget,widget...>   Stampa l'id della pagina.
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
[$_, $dir, $slug, $titolo, $widget] = $argv + [null, '', '', '', ''];
if (!preg_match('/^[a-z0-9-]+$/', (string)$slug)) { fwrite(STDERR, "slug non valido\n"); exit(1); }
require $dir . '/includes/config.php';
require_once $dir . '/includes/editor-controlli.php';
require_once $dir . '/includes/editor.php';
editor_carica_widget_esterni();
$sez = fn(array $w) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
$elementi = [];
$t = editor_widget('titolo');
$t['impostazioni'] = ['testo' => $titolo, 'livello' => 'h1'] + $t['impostazioni'];
$elementi[] = $sez($t);
foreach (array_filter(explode(',', (string)$widget)) as $w) $elementi[] = $sez(editor_widget($w));
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute([$titolo, $slug]); $id = (int)$pdo->lastInsertId(); }
editor_salva($id, editor_valida(['elementi' => $elementi]), true, 'prova', 'pagina di prova degli addon');
echo $id . "\n";
