<?php
// 2.1.0: copia del sito e snapshot degli aggiornamenti con le colonne generate (email_attiva di Eventi, slot_attivo di
// Prenotazioni). Prima del 2.1.0 il ripristino falliva con l'errore MySQL 3105.
// Prima: un sito con gli addon Eventi con iscrizione e Prenotazioni accesi (le tabelle ci sono).
// uso: php prove/prova-colonne-generate.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/aggiornamenti.php';
require_once $argv[1] . '/includes/copia-sito.php';
$n = 0; $ko = 0;
$ok = function (string $t, bool $c, $x = '') use (&$n, &$ko) { $n++; if (!$c) $ko++; echo ($c ? 'OK  ' : 'KO  ') . $t . ($x !== '' ? ' ' . substr(json_encode($x, JSON_UNESCAPED_UNICODE), 0, 300) : '') . "\n"; };
$pdo = db(); $i = tbl('evi_iscrizioni'); $p = tbl('pren_prenotazioni');
$pdo->exec("DELETE FROM $i WHERE codice IN ('gen-prova-1','gen-prova-2')"); $pdo->exec("DELETE FROM $p WHERE codice = 'gen-prova-1'");
$pdo->exec("INSERT INTO $i (evento_id,codice,nome,email,stato,consenso_at,creato,aggiornato) VALUES (1,'gen-prova-1','Anna','anna@esempio.it','confermata',NOW(),NOW(),NOW()),(1,'gen-prova-2','Bea','bea@esempio.it','cancellata',NOW(),NOW(),NOW())");
$pdo->exec("INSERT INTO $p (codice,servizio_id,risorsa_id,inizio,fine,inizio_ts,fine_ts,blocco_ts,nome,email,consenso_at,creato,aggiornato) VALUES ('gen-prova-1',1,1,NOW(),NOW(),1,2,3,'Carlo','carlo@esempio.it',NOW(),NOW(),NOW())");
$nome = fn($t) => trim($t, '`');
$ok('le colonne generate si riconoscono', count(agg_colonne_generate($pdo, $nome($i))) === 1 && count(agg_colonne_generate($pdo, $nome($p))) >= 1);
$stato = fn() => [$pdo->query("SELECT codice, email_attiva FROM $i WHERE codice LIKE 'gen-prova-%' ORDER BY codice")->fetchAll(PDO::FETCH_KEY_PAIR), (int)$pdo->query("SELECT COUNT(*) FROM $p WHERE codice = 'gen-prova-1'")->fetchColumn()];
$prima = $stato();
$f = copia_crea('manuale');
$r = copia_ripristina($f, true);
$log = implode(' ', $r['log'] ?? []);
$ok('copia del sito: il ripristino finisce senza errori', !empty($r['ok']) && !preg_match('/errore|3105/i', $log), $r['log'] ?? $r);
$ok('copia del sito: iscrizioni e prenotazioni tornano, con le colonne generate ricalcolate', $stato() === $prima && $prima[0]['gen-prova-2'] === null && $prima[1] === 1, $stato());
copia_elimina($f); if (!empty($r['prima'])) copia_elimina($r['prima']);
$tmp = tempnam(sys_get_temp_dir(), 'gen') . '.sql.gz';
agg_dump_db($tmp);
$r2 = agg_restore_db($tmp); @unlink($tmp);
$ok('snapshot degli aggiornamenti: il ripristino finisce senza errori', empty($r2['errori']), $r2['errori'] ?? $r2);
$ok('snapshot: iscrizioni e prenotazioni tornano', $stato() === $prima);
$pdo->exec("DELETE FROM $i WHERE codice IN ('gen-prova-1','gen-prova-2')"); $pdo->exec("DELETE FROM $p WHERE codice = 'gen-prova-1'");
echo "\n" . ($n - $ko) . " su $n OK\n";
exit($ko ? 1 : 0);
