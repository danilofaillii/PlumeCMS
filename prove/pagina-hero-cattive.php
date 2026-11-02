<?php
// 2.17.0, attacchi (gruppo 39): le quindici disposizioni nuove dell'Apertura con valori cattivi nei campi nuovi e in quelli
// di sempre, salvati con editor_valida come dall'editor vero. Una pagina /hero-cattiva-<id>/ per disposizione.
// Stampa una riga JSON con i valori che editor_valida ha tenuto (video, forma, posizioni, disposizione inventata).
// Uso: php prove/pagina-hero-cattive.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$x = '<script>alert(39)</script><img src=x onerror=alert(391)>"\'><svg onload=alert(392)>';
$video = ['javascript:alert(1)//.mp4', 'https://cattivo.example/v.mp4', '//cattivo.example/v.mp4', '/uploads/../../config.local.php', '/uploads/video/x.mp4" onerror="alert(1)', 'data:video/mp4;base64,AAAA', '/uploads/video/buono.mp4'];
$pdo = db();
$tenuti = ['video' => [], 'forma' => '', 'x' => [], 'lente' => [], 'disposizione' => '', 'righe' => 0];
$ids = array_slice(array_keys(EDITOR_HERO_DISPOSIZIONI), 11);
foreach ($ids as $i => $d) {
    $w = editor_widget('hero');
    $w['impostazioni'] = array_merge($w['impostazioni'], [
        'disposizione' => $d, 'occhiello' => $x, 'titolo' => $x . ' *' . $x . '*', 'testo' => $x, 'etichetta_onda' => $x,
        'pulsanti' => [['testo' => $x, 'link' => 'javascript:alert(393)', 'stile' => 'viola" onclick="alert(1)'], ['testo' => 'Due', 'link' => 'data:text/html,<script>alert(1)</script>', 'stile' => 'filo']],
        'immagine' => '/uploads/pagine/editor.webp" onerror="alert(394)', 'barre' => [['etichetta' => $x, 'valore' => 99999], ['etichetta' => $x, 'valore' => -50]],
        'terminale_righe' => implode("\n", array_fill(0, 9, '$ ' . $x)), 'parola' => $x . 'PAROLALUNGHISSIMADAVVERO',
        'nastro_foto' => array_fill(0, 30, ['immagine' => 'javascript:alert(395)']),
        'video' => $video[$i % count($video)],
        'etichette' => array_fill(0, 12, ['testo' => $x, 'x' => 99999, 'y' => -99999]),
        'lente_x' => 1e9, 'lente_y' => -1e9, 'forma' => '../../etc/passwd<svg onload=alert(396)>', 'immagine_prima' => 'javascript:alert(397)',
        'foto_trattamento' => 'velo;}</style><script>alert(398)</script>',
    ]);
    $albero = editor_valida(['elementi' => [['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]]]]);
    $v = $albero['elementi'][0]['figli'][0]['figli'][0]['impostazioni'] ?? [];
    $tenuti['video'][] = $v['video'] ?? null;
    if ($d === 'costellazione') $tenuti['forma'] = $v['forma'] ?? null;
    if ($d === 'note') $tenuti['x'] = array_map(fn($e) => [$e['x'] ?? null, $e['y'] ?? null], (array)($v['etichette'] ?? []));
    if ($d === 'lente') $tenuti['lente'] = [$v['lente_x'] ?? null, $v['lente_y'] ?? null];
    $slug = 'hero-cattiva-' . $d;
    $st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
    $id = (int)$st->fetchColumn();
    if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute(['Hero cattiva ' . $d, $slug]); $id = (int)$pdo->lastInsertId(); }
    editor_salva($id, $albero, true, 'prova', 'attacchi 39');
}
// Una disposizione inventata (che nessuna versione conosce) con caratteri cattivi: non entra; una inventata pulita resta, e si vede la classica.
$w = editor_widget('hero'); $w['impostazioni']['disposizione'] = 'finta"><script>alert(399)</script>';
$a = editor_valida(['elementi' => [['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]]]]);
$tenuti['disposizione'] = $a['elementi'][0]['figli'][0]['figli'][0]['impostazioni']['disposizione'] ?? null;
$w['impostazioni']['disposizione'] = 'futura';
$a = editor_valida(['elementi' => [['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]]]]);
$tenuti['futura'] = $a['elementi'][0]['figli'][0]['figli'][0]['impostazioni']['disposizione'] ?? null;
$tenuti['futura_classica'] = str_contains((string)(editor_disegna($a)[0] ?? ''), 'ed-hero-griglia');
$tenuti['ids'] = $ids;
echo json_encode($tenuti, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
