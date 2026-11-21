<?php
// La pagina /prova-sedi/ con i widget delle sedi (2.27.0), per prova-sedi.js: Sedi in griglia con la mappa, Sedi in elenco con
// una sede scelta, Dati dell'attività in riga, Mappa, Orari e Barra contatti "dalla sede". Vuole almeno una sede salvata.
// uso: php prove/pagina-sedi.php <cartella CMS> [id della sede da scegliere nell'elenco]
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$scelta = (string)($argv[2] ?? 'principale');
$w = fn(string $t, array $imp) => ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [array_replace_recursive(editor_widget($t), ['impostazioni' => $imp])]];
$sez = fn(array $col) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => $col];
$sezioni = [
    $sez([$w('titolo', ['testo' => 'Le nostre sedi', 'livello' => 'h1'])]),
    $sez([$w('sedi', ['quali' => 'tutte', 'disposizione' => 'griglia', 'mostra_mappa' => true, 'mappa_al_clic' => true])]),
    $sez([$w('sedi', ['quali' => 'scelte', 'scelte' => [['sede' => $scelta]], 'disposizione' => 'elenco', 'mostra_mappa' => true, 'mappa_al_clic' => false, 'classe' => 'prova-elenco'])]),
    $sez([$w('dati_attivita', ['sede' => 'principale', 'titolo' => 'Dove siamo', 'disposizione' => 'orizzontale'])]),
    $sez([$w('mappa', ['sede' => 'principale', 'al_clic' => false, 'come_arrivare' => 'sotto'])]),
    $sez([$w('orari', ['sede' => 'principale', 'titolo' => 'Orari della sede principale'])]),
    $sez([$w('barra_contatti', ['sede' => 'principale', 'whatsapp' => '39333123456'])]),
];
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['prova-sedi']);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Prova sedi', 'prova-sedi', 'published')")->execute(); $id = (int)$pdo->lastInsertId(); }
editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'pagina di prova delle sedi');
echo "pagina $id\n";
