<?php
// Il widget "Scorri di lato" (2.25.0) con valori cattivi in ogni campo (per tools/sicurezza-prova.js, gruppo 26b): testi con
// script, link javascript: e data:, immagini esterne o finte, colori con codice, caratteri a 4 byte, migliaia di pagine,
// disposizione e velocità inventate. Passa da editor_valida come dall'editor vero, si salva nella pagina /scorri-cattivi/
// (pubblicata) e stampa l'HTML del widget disegnato, con il CSS in testa (<style>).
// uso: php prove/scorri-cattivi.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$x = '"><script>alert(1)</script><img src=x onerror=alert(2)>';
$j = 'javascript:alert(3)';
$quattro = "Faccina \u{1F600} e razzo \u{1F680}";
$pagina = ['occhiello' => $x, 'titolo' => $x . ' *' . $x . '*', 'testo' => $x . "\n\n" . $quattro, 'data' => $x, 'immagine' => $j, 'alt' => $x,
    'sfondo' => 'red;}</style><script>alert(5)</script>', 'pulsante' => $x, 'pulsante_link' => $j, 'pulsante2' => $quattro, 'pulsante2_link' => 'data:text/html,<script>alert(4)</script>'];
$immagini = [$j, 'https://cattivo.example/x.png', '//cattivo.example/x.png', 'data:image/svg+xml,<svg onload=alert(6)>', '/uploads/../config.local.php', '/x.png" onerror="alert(7)', 'JaVaScRiPt:alert(8)', "java\nscript:alert(9)"];
$pagine = [];
for ($i = 0; $i < 5000; $i++) $pagine[] = ['immagine' => $immagini[$i % count($immagini)]] + $pagina;
$widget = [];
foreach (['pellicola', 'carte', 'fermo', 'tempo', 'profondita', 'inventata', '<script>'] as $d) {
    $widget[] = ['id' => editor_id(), 'tipo' => 'scorri_lato', 'impostazioni' => ['disposizione' => $d, 'occhiello' => $x, 'titolo' => $x, 'pagine' => $pagine,
        'velocita' => 'x"><script>', 'foto' => '"><script>', 'lineette' => $x, 'tutto_schermo' => $x, 'titolo_colore' => 'red;}</style><script>alert(10)</script>']];
}
$albero = ['elementi' => array_map(fn($w) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]], $widget)];
$v = editor_valida($albero);
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['scorri-cattivi']);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Scorri cattivi', 'scorri-cattivi', 'published')")->execute(); $id = (int)$pdo->lastInsertId(); }
editor_salva($id, $v, true, 'prova', 'attacchi');
[$html, $css] = editor_disegna($v, '/scorri-cattivi/');
echo '<style>' . $css . '</style>' . $html;
