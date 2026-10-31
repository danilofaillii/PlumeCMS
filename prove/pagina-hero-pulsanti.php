<?php
// 2.0.2: per la prova dei pulsanti dell'Apertura. Una pagina /hp-<disposizione>-<effetto>/ per ogni disposizione e ogni
// effetto animato della foto, con due pulsanti che portano a /servizi/ e /contatti/, la foto, l'onda e le barre.
// Uso: php prove/pagina-hero-pulsanti.php <cartella CMS> <url della foto>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$foto = $argv[2] ?? '';
$pdo = db();
$n = 0;
foreach (array_keys(EDITOR_HERO_DISPOSIZIONI) as $d) foreach (array_keys(EDITOR_FOTO_EFFETTI) as $e) {
    $w = editor_widget('hero');
    $w['impostazioni'] = ['disposizione' => $d, 'occhiello' => 'Prova dei pulsanti', 'titolo' => 'Il titolo *della prova*',
        'testo' => 'Due pulsanti qui sotto: si devono poter cliccare.',
        'pulsanti' => [['testo' => 'Primo pulsante', 'link' => '/servizi/', 'stile' => 'viola'], ['testo' => 'Secondo pulsante', 'link' => '/contatti/', 'stile' => 'filo']],
        'immagine' => $foto, 'onda_mostra' => true, 'etichetta_onda' => 'Onda', 'barre' => [['etichetta' => 'Uno', 'valore' => 70]],
        'foto_trattamento' => 'velo', 'foto_effetto' => $e] + $w['impostazioni'];
    $sez = ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
    $slug = 'hp-' . $d . '-' . ($e === '' ? 'nessuno' : $e);
    $st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
    $id = (int)$st->fetchColumn();
    if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute(['Pulsanti ' . $slug, $slug]); $id = (int)$pdo->lastInsertId(); }
    editor_salva($id, editor_valida(['elementi' => [$sez]]), true, 'prova', 'prova dei pulsanti');
    echo $slug, "\n"; $n++;
}
// I link scritti come li scrive una persona (senza la barra davanti): prima del 2.0.2 il pulsante puntava a "#".
$w = editor_widget('hero');
$w['impostazioni'] = ['disposizione' => 'classica', 'titolo' => 'Link scritti a mano', 'testo' => 'servizi e contatti/ senza la barra.',
    'pulsanti' => [['testo' => 'Primo pulsante', 'link' => 'servizi', 'stile' => 'viola'], ['testo' => 'Secondo pulsante', 'link' => 'contatti/', 'stile' => 'filo']],
    'immagine' => $foto] + $w['impostazioni'];
$sez = ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['hp-link-a-mano']);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES (?, ?, 'published')")->execute(['Pulsanti link a mano', 'hp-link-a-mano']); $id = (int)$pdo->lastInsertId(); }
editor_salva($id, editor_valida(['elementi' => [$sez]]), true, 'prova', 'prova dei pulsanti');
echo "hp-link-a-mano\n"; $n++;
fwrite(STDERR, "pagine: $n\n");
