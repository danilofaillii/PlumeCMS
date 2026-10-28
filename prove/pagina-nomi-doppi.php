<?php
// I widget con un controllo che aveva lo stesso nome di uno delle Avanzate o di un altro controllo dello stesso widget
// (corretti nella 1.3.0, albero versione 2). La pagina /nomi-doppi/ ha:
//   0-3 quattro Titoli animati salvati con la versione 1: "animazione" vuota, zoom, dal_basso e senza "animazione"
//       (le parole si dissolvevano sempre, tranne con Zoom, che accendeva anche l'ingresso);
//   4   un Titolo animato senza scelte, per la prova nell'editor;
//   5-7 Condividi, Avviso e Flip box salvati con "padding" a 30 px: valeva sia per lo Stile sia per le Avanzate;
//   8   Icone social salvate con il "padding" delle Avanzate a 5 px;
//   9-10 Condividi e Icone social senza scelte, per la prova nell'editor;
//   11-12 due Caroselli di immagini, uno con "spazio" a 30 px e uno senza (12 px, lo Spazio fra le immagini).
// L'albero e scritto direttamente nel database con versione 1, com'era salvato fino alla 1.3.0 senza questa correzione:
// dopo l'aggiornamento il sito deve restare identico al pixel. Si lancia con il codice di prima per le foto "prima".
// Uso: php pagina-nomi-doppi.php <cartella CMS>. Stampa l'id della pagina.
$_SERVER['HTTP_HOST'] = '127.0.0.1:8099'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$n = 0;
$id = function () use (&$n) { return 'nd' . str_pad((string)++$n, 5, '0', STR_PAD_LEFT); };
$sez = fn(string $tipo, array $imp) => ['id' => $id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => $id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [['id' => $id(), 'tipo' => $tipo, 'impostazioni' => $imp]]]]];
$lati = fn(int $x) => ['d' => ['u' => 'px', 'legati' => true, 'sopra' => $x, 'destra' => $x, 'sotto' => $x, 'sinistra' => $x]];
$parole = ['modo' => 'rotante', 'prima' => 'Un sito', 'rotanti' => "veloce\nchiaro\ntuo", 'dopo' => 'da subito'];
$foto = array_map(fn($t) => ['immagine' => cms_base() . "/temi/$t/anteprima.png", 'alt' => $t], ['cosmo', 'aria', 'bottega', 'cosmo']);
$albero = ['versione' => 1, 'elementi' => [
    $sez('titolo_animato', $parole + ['animazione' => '']),
    $sez('titolo_animato', $parole + ['animazione' => 'zoom']),
    $sez('titolo_animato', $parole + ['animazione' => 'dal_basso']),
    $sez('titolo_animato', $parole),
    $sez('titolo_animato', $parole),
    $sez('condividi', ['padding' => $lati(30)]),
    $sez('avviso', ['padding' => $lati(30)]),
    $sez('flip_box', ['padding' => $lati(30)]),
    $sez('social', ['padding' => $lati(5)]),
    $sez('condividi', []),
    $sez('social', []),
    $sez('carosello', ['immagini' => $foto, 'spazio' => ['d' => ['v' => 30, 'u' => 'px']]]),
    $sez('carosello', ['immagini' => $foto]),
]];
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . " WHERE slug = 'nomi-doppi'"); $st->execute();
$pid = (int)$st->fetchColumn();
if (!$pid) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Nomi doppi', 'nomi-doppi', 'published')")->execute(); $pid = (int)$pdo->lastInsertId(); }
$pdo->prepare('UPDATE ' . tbl('pages') . ' SET struttura = ?, struttura_bozza = NULL WHERE id = ?')->execute([editor_json($albero), $pid]);
echo $pid, "\n";
