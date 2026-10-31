<?php
// Crea (o rifà) la pagina "prova-download" con due widget Download: i file scelti (con un titolo proprio) e gli ultimi
// caricati a schede, con il conteggio. Per prove/prova-download.js. Uso: php pagina-download.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$sez = fn(array $w) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
$scelti = editor_widget('download');
$scelti['impostazioni'] = ['origine' => 'scelti', 'voci' => [['file' => 'listino-2026', 'titolo' => '', 'descrizione' => ''], ['file' => 'manuale-tecnico', 'titolo' => 'Il manuale, titolo dal widget', 'descrizione' => '']]] + $scelti['impostazioni'];
$ultimi = editor_widget('download');
$ultimi['impostazioni'] = ['origine' => 'ultimi', 'quanti' => 3, 'disposizione' => 'schede', 'mostra_conteggio' => true, 'mostra_data' => true] + $ultimi['impostazioni'];
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['prova-download']);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Prova download', 'prova-download', 'published')")->execute(); $id = (int)$pdo->lastInsertId(); }
// 1.2.5: una serie (sempre l'ultima guida) e il numero di versione scritto in un titolo.
$serie = editor_widget('download');
$serie['impostazioni'] = ['origine' => 'scelti', 'voci' => [['file' => 'guida-ultima', 'titolo' => 'Guida {versione}', 'descrizione' => '']]] + $serie['impostazioni'];
$titolo = editor_widget('titolo');
$titolo['impostazioni'] = ['testo' => 'La guida è alla versione {versione:guida-ultima}', 'livello' => 'h2'] + $titolo['impostazioni'];
editor_salva($id, editor_valida(['elementi' => [$sez($scelti), $sez($ultimi), $sez($titolo), $sez($serie)]]), true, 'prova', 'pagina di prova');
echo $id . "\n";
