<?php
// I Riquadri con icona e con immagine e il Pulsante fisso (1.4.0): la pagina /riquadri/ con i tre widget ai valori di
// partenza, piu un Riquadro con icona salvato come lo salvava la 1.3.1 con "posizione" = "sinistra" (scritto direttamente
// nel database, senza passare dalla validazione: deve uscire a sinistra grazie al nome di prima).
// Uso: php pagina-riquadri.php <cartella CMS>. Stampa l'id della pagina.
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$sez = fn(array $w) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => $w]]];
$icona = editor_widget('riquadro_icona', ['titolo' => 'Riquadro con icona', 'testo' => 'Il testo del riquadro con icona, su due righe se serve.']);
$immagine = editor_widget('riquadro_immagine', ['titolo' => 'Riquadro con immagine', 'testo' => 'Il testo del riquadro con immagine.', 'immagine' => cms_base() . '/sito/assets/favicon.svg']);
$fisso = editor_widget('pulsante_fisso', ['mostra_dopo' => 0]);
$vecchio = editor_widget('riquadro_icona', ['titolo' => 'Riquadro salvato dalla 1.3.1', 'testo' => 'Posizione scritta con il nome di prima.']);
$albero = editor_valida(['elementi' => [$sez([$icona]), $sez([$immagine]), $sez([$fisso])]]);
// Il quarto, con il nome di prima, aggiunto dopo la validazione: com'era scritto nel database prima della 1.4.0.
$vecchio['impostazioni']['posizione'] = 'sinistra';
$albero['versione'] = 1; // dalla 1.4.1 il nome di prima vale solo negli alberi salvati prima (versione 1)
$albero['elementi'][] = $sez([$vecchio]);
$pdo = db();
$st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . " WHERE slug = 'riquadri'"); $st->execute();
$id = (int)$st->fetchColumn();
if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status, publish_at) VALUES ('Riquadri', 'riquadri', 'published', NOW())")->execute(); $id = (int)$pdo->lastInsertId(); }
$pdo->prepare('UPDATE ' . tbl('pages') . ' SET struttura = ?, struttura_bozza = NULL WHERE id = ?')->execute([editor_json($albero), $id]);
echo $id, "\n";
