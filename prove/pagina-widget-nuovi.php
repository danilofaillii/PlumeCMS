<?php
// La pagina /widget-nuovi/ con i sette widget della 1.10.0, compilati come li compilerebbe un'attività (per prova-widget-nuovi.js).
// uso: php prove/pagina-widget-nuovi.php <cartella CMS>   (le foto: i disegni del kit di partenza, uploads/kit/)
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
// Le foto: i disegni del kit di partenza o, su un sito su misura, le sue foto finte.
$foto = array_map(fn($f) => cms_uploads_url() . '/' . basename(dirname($f)) . '/' . basename($f), array_merge(glob(cms_uploads_dir() . '/kit/*.svg') ?: [], glob(cms_uploads_dir() . '/segnaposto/*.svg') ?: []));
$f = fn(int $i) => $foto[$i % max(1, count($foto))] ?? '';
$oggi = new DateTimeImmutable('now');
$w = fn(string $t, array $imp) => ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [array_replace_recursive(editor_widget($t), ['impostazioni' => $imp])]];
$sez = fn(array $col) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => $col];
$sezioni = [
    $sez([$w('slider', ['voci' => [
        ['immagine' => $f(0), 'occhiello' => 'Novità', 'titolo' => 'Prima slide di prova', 'testo' => 'Una frase.', 'pulsante' => 'Scrivici', 'link' => '/contatti/'],
        ['immagine' => $f(1), 'occhiello' => '', 'titolo' => 'Seconda slide di prova', 'testo' => 'Un\'altra frase.', 'pulsante' => '', 'link' => ''],
        ['immagine' => '', 'occhiello' => '', 'titolo' => 'Terza slide senza foto', 'testo' => '', 'pulsante' => '', 'link' => '']], 'autoplay' => false])]),
    $sez([$w('orari', ['lun' => '9:00-13:00, 15:00-19:00', 'dom' => 'Chiuso', 'sab' => '9-13',
        'chiusure' => [['dal' => $oggi->modify('+20 days')->format('Y-m-d'), 'al' => $oggi->modify('+27 days')->format('Y-m-d'), 'motivo' => 'Ferie estive']]]),
        $w('recensioni', ['media' => '4,6', 'totale' => '57 recensioni', 'fonte' => 'Google', 'pulsante_link' => ['url' => 'https://g.page/r/esempio/review', 'nuova' => true]])]),
    $sez([$w('prima_dopo', ['immagine_prima' => $f(2), 'immagine_dopo' => $f(3)])]),
    $sez([$w('squadra', ['voci' => [
        ['immagine' => $f(0), 'nome' => 'Anna Rossi', 'ruolo' => 'Titolare', 'testo' => 'Si occupa dei clienti.', 'email' => 'anna@esempio.it', 'telefono' => '+39 051 123456', 'linkedin' => 'https://www.linkedin.com/in/esempio', 'instagram' => 'javascript:alert(1)', 'facebook' => '', 'sito' => ''],
        ['immagine' => '', 'nome' => 'Luca Bianchi', 'ruolo' => 'Tecnico', 'testo' => '', 'email' => 'non-una-email', 'telefono' => '', 'linkedin' => '', 'instagram' => '', 'facebook' => '', 'sito' => '']]])]),
    $sez([$w('eventi', ['voci' => [
        ['data' => $oggi->modify('+40 days')->format('Y-m-d'), 'ora' => '10:00', 'titolo' => 'Evento dopo', 'luogo' => 'In negozio', 'testo' => 'Secondo in ordine.', 'prezzo' => '', 'immagine' => '', 'link' => '/contatti/'],
        ['data' => $oggi->modify('-3 days')->format('Y-m-d'), 'ora' => '21:00', 'titolo' => 'Evento passato', 'luogo' => '', 'testo' => '', 'prezzo' => '', 'immagine' => '', 'link' => ''],
        ['data' => $oggi->modify('+5 days')->format('Y-m-d'), 'ora' => '21:00', 'titolo' => 'Evento prima', 'luogo' => 'Piazza Maggiore, Bologna', 'testo' => 'Primo in ordine.', 'prezzo' => 'Ingresso libero', 'immagine' => $f(1), 'link' => '']]])]),
    $sez([$w('barra_contatti', ['telefono' => '+39 051 123456', 'whatsapp' => '39333123456', 'indirizzo' => 'Via Rizzoli 1, Bologna', 'email' => 'info@esempio.it'])]),
];
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['widget-nuovi']);
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Widget nuovi', 'widget-nuovi', 'published')")->execute(); $id = (int)$pdo->lastInsertId(); }
editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'pagina di prova');
echo "pagina $id, foto: " . count($foto) . "\n";
