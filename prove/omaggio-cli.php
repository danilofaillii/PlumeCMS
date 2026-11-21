<?php
/**
 * Per prova-omaggio.js (mai nel prodotto): una chiave di un link personale scaduta ieri, firmata davvero dal sito.
 * uso: php prove/omaggio-cli.php <cartella CMS> <nome del file> <id iscritto> [valida|cartella]
 *   valida: una chiave buona (come quella dell'email); cartella: il nome della cartella dei file riservati.
 */
[$dir, $nome, $id, $modo] = array_pad(array_slice($argv, 1, 4), 4, '');
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/moduli.php';
require_once $dir . '/includes/scaricabili.php';
if ($modo === 'cartella') { echo basename(scaricabili_dir_riservati()), "\n"; exit; }
$scade = $modo === 'valida' ? time() + 86400 : time() - 86400;
echo (int)$id . '.' . $scade . '.' . scaricabili_firma($nome, (int)$id, $scade), "\n";
