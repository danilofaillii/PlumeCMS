<?php
/**
 * Aiuti da riga di comando per prove/prova-popup-editor.js (mai nel prodotto).
 * uso: php prove/popup-cli.php <cartella CMS> uno "<query con {p} al posto del prefisso>"   la prima colonna della prima riga
 *      php prove/popup-cli.php <cartella CMS> esegui "<query>"                             una query che cambia i dati
 *      php prove/popup-cli.php <cartella CMS> albero <id modello> <file json>              scrive la bozza del modello
 */
[$dir, $azione, $a1, $a2] = array_pad(array_slice($argv, 1, 4), 4, '');
if ($dir === '' || !in_array($azione, ['uno', 'esegui', 'albero'], true)) { fwrite(STDERR, "uso: popup-cli.php <cartella CMS> uno|esegui|albero ...\n"); exit(2); }
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
$q = str_replace('{p}', DB_PREFIX, $a1);
if ($azione === 'uno') { $r = db()->query($q)->fetchColumn(); echo $r === false ? '' : $r, "\n"; exit; }
if ($azione === 'esegui') { echo db()->exec($q), "\n"; exit; }
db()->prepare('UPDATE ' . DB_PREFIX . 'modelli SET struttura_bozza = ? WHERE id = ?')->execute([file_get_contents($a2), (int)$a1]);
echo "ok\n";
