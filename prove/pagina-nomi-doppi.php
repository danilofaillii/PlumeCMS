<?php
// I widget con un controllo che aveva lo stesso nome di uno delle Avanzate (corretti nella 1.4.1): Titolo animato
// ("animazione"), Condividi, Avviso, Flip box e Icone social ("padding"). La pagina /nomi-doppi/ ha:
//   0-1 due Titoli animati salvati dalla 1.4.0: "animazione" vuota (le parole si dissolvevano, qualunque cosa si
//       scegliesse) e "animazione" = zoom (parole in zoom e anche l'animazione all'ingresso);
//   2   un Titolo animato nuovo, per la prova nell'editor;
//   3-5 Condividi, Avviso e Flip box salvati dalla 1.4.0 con "padding" a 30 px: valeva sia per lo Stile sia per le Avanzate;
//   6   Icone social salvate dalla 1.4.0 con il "padding" delle Avanzate a 5 px;
//   7-8 Condividi e Icone social nuovi, per la prova nell'editor.
// Gli alberi sono scritti direttamente nel database, com'erano salvati dalla 1.4.0 (versione 1 dell'albero, nomi di prima):
// dopo l'aggiornamento il sito deve restare identico al pixel.
// Uso: php pagina-nomi-doppi.php <cartella CMS>. Stampa l'id della pagina.
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$n = 0;
$id = function () use (&$n) { return 'nd' . str_pad((string)++$n, 5, '0', STR_PAD_LEFT); };
$sez = fn(string $tipo, array $imp) => ['id' => $id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => $id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [['id' => $id(), 'tipo' => $tipo, 'impostazioni' => $imp]]]]];
$trenta = ['d' => ['u' => 'px', 'legati' => true, 'sopra' => 30, 'destra' => 30, 'sotto' => 30, 'sinistra' => 30]];
$parole = ['modo' => 'rotante', 'prima' => 'Un sito', 'rotanti' => "veloce\nchiaro\ntuo", 'dopo' => 'da subito'];
$albero = ['versione' => 1, 'elementi' => [
    $sez('titolo_animato', $parole + ['animazione' => '']),
    $sez('titolo_animato', $parole + ['animazione' => 'zoom']),
    $sez('titolo_animato', $parole),
    $sez('condividi', ['padding' => $trenta]),
    $sez('avviso', ['padding' => $trenta]),
    $sez('flip_box', ['padding' => $trenta]),
    $sez('social', ['padding' => ['d' => ['u' => 'px', 'legati' => true, 'sopra' => 5, 'destra' => 5, 'sotto' => 5, 'sinistra' => 5]]]),
    $sez('condividi', []),
    $sez('social', []),
]];
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . " WHERE slug = 'nomi-doppi'"); $st->execute();
$pid = (int)$st->fetchColumn();
if (!$pid) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status, publish_at) VALUES ('Nomi doppi', 'nomi-doppi', 'published', NOW())")->execute(); $pid = (int)$pdo->lastInsertId(); }
$pdo->prepare('UPDATE ' . tbl('pages') . ' SET struttura = ?, struttura_bozza = NULL WHERE id = ?')->execute([editor_json($albero), $pid]);
echo $pid, "\n";
