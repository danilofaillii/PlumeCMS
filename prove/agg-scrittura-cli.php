<?php
/**
 * 2.40.2: prova di agg_scrivi_file() (includes/aggiornamenti.php), la scrittura dei file dei pacchetti (mai nel prodotto).
 * Vuole root (chattr) e una cartella scrivibile. Stampa OK/KO e "N su M OK".
 * uso: php prove/agg-scrittura-cli.php <cartella CMS installato> <cartella di prova>
 */
[$dir, $prova] = array_pad(array_slice($argv, 1, 2), 2, '');
if ($dir === '' || $prova === '') { fwrite(STDERR, "uso: agg-scrittura-cli.php <cartella CMS> <cartella di prova>\n"); exit(2); }
define('CMS_AGG_PAUSE', [1, 1, 1]);
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/aggiornamenti.php';
$n = 0; $ko = 0;
function ok(string $t, bool $c, $x = ''): void { global $n, $ko; $n++; if (!$c) $ko++; echo ($c ? 'OK  ' : 'KO  ') . $t . ($x !== '' ? ' ' . json_encode($x, JSON_UNESCAPED_UNICODE) : '') . "\n"; }
@mkdir($prova, 0755, true);
$f = $prova . '/prova.php';
@exec('chattr -i ' . escapeshellarg($f) . ' 2>/dev/null');
@unlink($f);
ok('file nuovo: scritto', agg_scrivi_file($f, "<?php // uno\n") === 'scritto' && file_get_contents($f) === "<?php // uno\n");
ok('file nuovo: permessi 0644', (fileperms($f) & 0777) === 0644, sprintf('%o', fileperms($f) & 0777));
chmod($f, 0640);
ok('stesso contenuto: uguale, non toccato', agg_scrivi_file($f, "<?php // uno\n") === 'uguale');
ok('contenuto diverso: scritto', agg_scrivi_file($f, "<?php // due\n") === 'scritto' && file_get_contents($f) === "<?php // due\n");
ok('i permessi del file di prima restano', (fileperms($f) & 0777) === 0640, sprintf('%o', fileperms($f) & 0777));
ok('nessun file temporaneo rimasto', glob($prova . '/.*plume-*') === []);
// file bloccato per un momento: si sblocca dopo 2 secondi
exec('chattr +i ' . escapeshellarg($f), $o, $rc);
if ($rc === 0) {
    ok('bloccato: PHP non riesce a scriverlo', @file_put_contents($f, 'x') === false);
    exec('(sleep 2; chattr -i ' . escapeshellarg($f) . ') >/dev/null 2>&1 &');
    $t = microtime(true);
    $e = agg_scrivi_file($f, "<?php // tre\n");
    ok('bloccato per 2 secondi: riprova e scrive', $e === 'scritto' && file_get_contents($f) === "<?php // tre\n", [$e, round(microtime(true) - $t, 1)]);
    // file bloccato per sempre: il motivo in parole
    exec('chattr +i ' . escapeshellarg($f));
    $e = agg_scrivi_file($f, "<?php // quattro\n");
    exec('chattr -i ' . escapeshellarg($f));
    ok('bloccato per sempre: errore con il motivo e i tentativi', str_contains($e, 'riprovato 4 volte') && $e !== 'scritto' && preg_match('/not permitted|denied/i', $e), $e);
    ok('bloccato per sempre: il file resta com\'era', file_get_contents($f) === "<?php // tre\n");
    ok('bloccato per sempre: nessun file temporaneo rimasto', glob($prova . '/.*plume-*') === []);
} else ok('chattr disponibile (serve root su ext4 o overlay)', false);
// cartella non scrivibile da un utente qualsiasi: la prova con root non si può fare, si controlla la parola
ok('file grande: scritto intero', agg_scrivi_file($prova . '/grande.bin', str_repeat('a', 3 << 20)) === 'scritto' && filesize($prova . '/grande.bin') === 3 << 20);
echo ($n - $ko) . " su $n OK\n";
exit($ko ? 1 : 0);
