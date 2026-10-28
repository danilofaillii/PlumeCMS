<?php
// Crea (o rifà) l'articolo "articolo-largo", fatto con sezioni dell'editor a tutta larghezza (non "Articolo"), pubblicato,
// con in fondo una Griglia di articoli: le sue schede sono <article>, come quelle che un tema o un widget mettono in pagina.
// Serve a provare il ramo "fine-articolo-fuori" del riquadro Aggiungi sempre alla fine (1.6.0).
// Uso: php articolo-largo.php <cartella CMS>   (stampa il percorso)
$_SERVER['HTTP_HOST'] = '127.0.0.1:8099'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
editor_schema();
$sezioni = [
    editor_sezione([editor_widget('titolo', ['testo' => 'Un articolo largo', 'livello' => 'h2']),
        editor_widget('testo', ['testo' => '<p>Questo articolo usa sezioni a tutta larghezza: niente colonna di lettura.</p>'])], ['larghezza' => 'piena']),
    editor_sezione([editor_widget('griglia_contenuti', ['quanti' => 2])], ['larghezza' => 'contenuta']),
];
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('articles') . ' WHERE slug = ?'); $st->execute(['articolo-largo']);
$id = (int)$st->fetchColumn();
if (!$id) {
    $pdo->prepare('INSERT INTO ' . tbl('articles') . " (title, slug, content, status, publish_date, publish_time, content_format) VALUES ('Articolo largo', 'articolo-largo', '', 'published', CURDATE() - INTERVAL 1 DAY, '06:00:00', 'html')")->execute();
    $id = (int)$pdo->lastInsertId();
}
editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'articolo di prova', 'articoli');
echo "/articolo-largo/\n";
