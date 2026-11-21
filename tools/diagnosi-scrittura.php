<?php
/**
 * Diagnosi della scrittura dei file per il pannello Aggiornamenti di plumeCMS (21 novembre 2026).
 * Non fa parte del prodotto: si carica a mano nella cartella del sito (accanto a index.php), si apre nel browser
 * con ?k=<chiave> e poi si cancella (pulsante in fondo alla pagina, o dal File Manager).
 *
 * Cosa fa, senza cambiare il sito:
 *  1. dice con che utente gira PHP e chi è il proprietario dei file;
 *  2. prova ad aprire in scrittura (modo "c": non svuota il file) aggiornamenti.php, addon.php e altri file del CMS;
 *  3. rifà la sequenza del pannello: riscrive addon.php con il suo stesso contenuto e apre subito aggiornamenti.php;
 *  4. rifà la stessa sequenza su file di prova suoi (in includes/ e in aggiornamenti/), con .php e senza;
 *  5. se una scrittura viene rifiutata, riprova a 1, 2, 3, 5 e 10 secondi per misurare quanto dura il blocco;
 *  6. prova la strada nuova della 2.40.2: file temporaneo e rinomina.
 * I file di prova li cancella alla fine. Il contenuto dei file veri non cambia mai (si riscrivono gli stessi byte).
 * Dopo 3 ore dal caricamento non risponde più.
 */
const CHIAVE = 'METTI_QUI_LA_CHIAVE';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if (!preg_match('/^[0-9a-f]{24,}$/', CHIAVE) || !hash_equals(CHIAVE, (string)($_GET['k'] ?? ''))) { http_response_code(404); exit('Pagina non trovata.'); }
if (filemtime(__FILE__) < time() - 3 * 3600) { http_response_code(410); exit('Pagina scaduta: ricaricala dal File Manager se serve ancora, poi cancellala.'); }
@set_time_limit(180);
error_reporting(E_ALL);
ini_set('display_errors', '0');

if (($_POST['azione'] ?? '') === 'cancella' && hash_equals(CHIAVE, (string)($_POST['k'] ?? ''))) {
    $ok = @unlink(__FILE__);
    exit($ok ? 'File di diagnosi cancellato. Puoi chiudere questa pagina.' : 'Non sono riuscito a cancellarmi: cancella questo file dal File Manager.');
}

$base = __DIR__;
$inc = $base . '/includes';
$righe = [];
$problemi = [];
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function riga(string $gruppo, string $cosa, $esito, string $nota = ''): void { global $righe; $righe[] = [$gruppo, $cosa, $esito, $nota]; }
function errore(): string { $e = error_get_last(); error_clear_last(); return $e ? preg_replace('/^[a-z_]+\([^)]*\): /i', '', $e['message']) : ''; }
function utente($id): string {
    if (function_exists('posix_getpwuid')) { $p = @posix_getpwuid($id); if ($p) return $p['name'] . ' (' . $id . ')'; }
    return (string)$id;
}
function gruppo($id): string {
    if (function_exists('posix_getgrgid')) { $g = @posix_getgrgid($id); if ($g) return $g['name'] . ' (' . $id . ')'; }
    return (string)$id;
}
function permessi($f): string { return substr(sprintf('%o', fileperms($f)), -4); }
/** Apre in scrittura senza svuotare (modo c). true o il messaggio di errore. */
function apri_scrittura(string $f) { error_clear_last(); $h = @fopen($f, 'c'); if ($h) { fclose($h); return true; } return errore() ?: 'rifiutato senza messaggio'; }
/** Riscrive gli stessi byte senza svuotare il file prima: se qualcosa va storto il contenuto resta quello. */
function riscrivi_uguale(string $f) {
    $dati = @file_get_contents($f);
    if ($dati === false) return 'non si legge: ' . errore();
    error_clear_last();
    $h = @fopen($f, 'c');
    if (!$h) return errore() ?: 'rifiutato senza messaggio';
    $n = @fwrite($h, $dati);
    fclose($h);
    clearstatcache();
    if ($n !== strlen($dati)) return 'scritti ' . (int)$n . ' byte su ' . strlen($dati) . ' ' . errore();
    return true;
}
/** Se $prova() fallisce, riprova a 1, 2, 3, 5, 10 secondi dall'inizio: dice dopo quanto passa. */
function quanto_dura(callable $prova): string {
    $t0 = microtime(true);
    foreach ([1, 2, 3, 5, 10] as $s) {
        $aspetta = $s - (microtime(true) - $t0);
        if ($aspetta > 0) usleep((int)($aspetta * 1e6));
        clearstatcache();
        if ($prova() === true) return 'passa dopo ' . $s . ' secondi';
    }
    return 'ancora rifiutato dopo 10 secondi';
}

// 1. Chi sono
$euid = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();
$egid = function_exists('posix_getegid') ? posix_getegid() : getmygid();
riga('PHP', 'Versione e modo', PHP_VERSION . ' / ' . PHP_SAPI, (string)($_SERVER['SERVER_SOFTWARE'] ?? ''));
riga('PHP', 'Utente con cui gira PHP', utente($euid) . ', gruppo ' . gruppo($egid));
riga('PHP', 'Proprietario di questo file', utente(fileowner(__FILE__)) . ', gruppo ' . gruppo(filegroup(__FILE__)));
riga('PHP', 'umask', sprintf('%04o', umask()));
riga('PHP', 'open_basedir', ini_get('open_basedir') ?: '(nessuno)');
riga('PHP', 'Funzioni disattivate', ini_get('disable_functions') ?: '(nessuna)');
riga('PHP', 'opcache', function_exists('opcache_get_status') && @opcache_get_status(false) ? 'acceso' : 'spento o non leggibile');
$libero = @disk_free_space($base);
riga('Disco', 'Spazio libero visto da PHP', $libero === false ? 'non leggibile' : round($libero / 1048576) . ' MB', 'Il limite del piano di hosting può essere più basso: vale quello di cPanel.');
foreach (['/proc/self/attr/current' => 'Contesto di sicurezza'] as $f => $n) { $c = @file_get_contents($f); if ($c !== false && trim($c) !== '') riga('PHP', $n, trim($c)); }
foreach (['cagefs' => '/var/.cagefs', 'imunify' => '/var/imunify360', 'litespeed' => '/usr/local/lsws'] as $n => $f) if (@is_dir($f)) riga('Server', 'Trovato ' . $n, $f);

// 2. I file veri: proprietario, permessi, apertura in scrittura (senza svuotare)
$campione = ['includes/aggiornamenti.php', 'includes/addon.php', 'includes/consegna.php', 'includes/editor-widget-extra-c.php', 'includes/cms.php', 'includes/config.php', 'VERSIONE', 'index.php'];
foreach (glob($inc . '/*.php') ?: [] as $f) { $r = 'includes/' . basename($f); if (!in_array($r, $campione, true) && count($campione) < 30) $campione[] = $r; }
$diversi = [];
foreach ($campione as $r) {
    $f = $base . '/' . $r;
    if (!is_file($f)) { riga('File del sito', $r, 'non c\'è'); continue; }
    $p = apri_scrittura($f);
    $info = 'proprietario ' . utente(fileowner($f)) . ', permessi ' . permessi($f) . ', modificato ' . date('d/m H:i:s', filemtime($f));
    if (fileowner($f) !== $euid) $diversi[] = $r;
    riga('File del sito', $r, $p === true ? 'OK' : 'NO: ' . $p, $info);
    if ($p !== true) $problemi[] = "Il file $r non si apre in scrittura nemmeno da fermo: $p ($info).";
}
foreach (['includes', 'aggiornamenti', 'uploads', '.'] as $d) {
    $f = $base . '/' . $d;
    if (is_dir($f)) riga('Cartelle', $d, is_writable($f) ? 'scrivibile' : 'NON scrivibile', 'proprietario ' . utente(fileowner($f)) . ', permessi ' . permessi($f));
}
if ($diversi) $problemi[] = 'Questi file hanno un proprietario diverso dall\'utente di PHP: ' . implode(', ', $diversi) . '.';

// 3. La sequenza vera del pannello, sui file veri, con gli stessi byte
$seq = [];
$t = microtime(true);
$a = riscrivi_uguale($inc . '/addon.php');
$seq[] = ['riscrivo addon.php (stessi byte)', $a, round((microtime(true) - $t) * 1000) . ' ms'];
$b = apri_scrittura($inc . '/aggiornamenti.php');
$seq[] = ['subito dopo: apro aggiornamenti.php in scrittura', $b, round((microtime(true) - $t) * 1000) . ' ms'];
if ($b !== true) {
    $dura = quanto_dura(function () use ($inc) { return apri_scrittura($inc . '/aggiornamenti.php'); });
    $seq[] = ['aggiornamenti.php, di nuovo', $dura, ''];
    $problemi[] = "Riprodotto: dopo aver scritto addon.php il server rifiuta aggiornamenti.php ($b). Il blocco: $dura.";
}
$c = riscrivi_uguale($inc . '/consegna.php');
$seq[] = ['riscrivo consegna.php (stessi byte)', $c, ''];
$d = apri_scrittura($inc . '/editor-widget-extra-c.php');
$seq[] = ['subito dopo: apro editor-widget-extra-c.php in scrittura', $d, ''];
if ($d !== true) $problemi[] = "Riprodotto anche con consegna.php e editor-widget-extra-c.php ($d).";
foreach ($seq as $s) riga('Sequenza sui file veri', $s[0], $s[1] === true ? 'OK' : $s[1], $s[2]);

// 4. La stessa sequenza su file di prova, in includes/ e in aggiornamenti/, con .php e con .txt
$cartelle = [];
foreach (['includes', 'aggiornamenti'] as $dd) {
    $dir = $base . '/' . $dd . '/.diagnosi-' . bin2hex(random_bytes(3));
    if (!@mkdir($dir, 0755)) { riga('File di prova', $dd, 'non riesco a creare la cartella di prova: ' . errore()); continue; }
    $cartelle[] = $dir;
    foreach (['php', 'txt'] as $est) {
        $nomi = [];
        for ($i = 1; $i <= 4; $i++) { $nomi[$i] = "$dir/prova$i.$est"; @file_put_contents($nomi[$i], "<?php // prova $i\n"); }
        sleep(1);
        $esiti = [];
        foreach ($nomi as $i => $f) {
            error_clear_last();
            $ok = @file_put_contents($f, "<?php // prova $i, riscritto\n") !== false;
            $esiti[] = 'file ' . $i . ': ' . ($ok ? 'OK' : 'NO (' . errore() . ')');
            if (!$ok && $i > 1) $problemi[] = "Anche un file di prova .$est in $dd/ viene rifiutato quando è il secondo scritto di fila.";
        }
        riga('File di prova', "4 file .$est in $dd/, riscritti di fila", implode('; ', $esiti));
        // la strada della 2.40.2: temporaneo + rinomina
        $tmp = "$dir/.tmp-" . bin2hex(random_bytes(3));
        @file_put_contents($nomi[1], "<?php // uno\n");
        error_clear_last();
        $r = @file_put_contents($tmp, "<?php // con rinomina\n") !== false && @rename($tmp, $nomi[2]);
        riga('File di prova', "temporaneo + rinomina in $dd/ (.$est), subito dopo un'altra scrittura", $r ? 'OK' : 'NO (' . errore() . ')');
        @unlink($tmp);
    }
}
foreach ($cartelle as $dir) { foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) if (is_file($f)) @unlink($f); @rmdir($dir); }

// Verdetto
$verdetto = $problemi ? $problemi : ['Nessun blocco riprodotto: i file veri si aprono in scrittura e le sequenze passano. Il blocco del pannello allora dipende da qualcosa che succede solo durante l\'aggiornamento (per esempio lo snapshot che copia molti file subito prima): mandami comunque questa pagina.'];
?><!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Diagnosi scrittura file</title>
<style>
:root{--bg:#f6f7f9;--fg:#1d2330;--mu:#5d6675;--ok:#11703b;--no:#b3261e;--bd:#d9dde3;--card:#fff}
@media (prefers-color-scheme:dark){:root{--bg:#10141c;--fg:#e8ebf0;--mu:#9aa3b2;--ok:#5fd18c;--no:#ff8a80;--bd:#2a3140;--card:#171c26}}
body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.5 system-ui,sans-serif}
main{max-width:1100px;margin:0 auto;padding:24px 16px}
h1{font-size:22px;margin:0 0 4px}.mu{color:var(--mu)}
.box{background:var(--card);border:1px solid var(--bd);border-radius:10px;padding:16px;margin:16px 0}
.verdetto li{margin:6px 0}
table{width:100%;border-collapse:collapse;font-size:13px}td,th{border-top:1px solid var(--bd);padding:6px 8px;text-align:left;vertical-align:top;word-break:break-word}
th{color:var(--mu);font-weight:600}.ok{color:var(--ok);font-weight:600}.no{color:var(--no);font-weight:600}
button{font:inherit;padding:8px 14px;border-radius:8px;border:1px solid var(--no);background:transparent;color:var(--no);cursor:pointer}
</style></head><body><main>
<h1>Diagnosi della scrittura dei file</h1>
<p class="mu">Fatta il <?= h(date('d/m/Y H:i:s')) ?> su <?= h($base) ?>. Fai una schermata di tutta la pagina e mandala.</p>
<div class="box verdetto"><strong>Verdetto</strong><ul><?php foreach ($verdetto as $v) echo '<li>' . h($v) . '</li>'; ?></ul></div>
<div class="box"><table><tr><th>Gruppo</th><th>Cosa</th><th>Esito</th><th>Dettagli</th></tr>
<?php foreach ($righe as $r) { $e = is_string($r[2]) ? $r[2] : ($r[2] ? 'OK' : 'NO'); $cl = preg_match('/^(OK|scrivibile|passa)/', $e) ? 'ok' : (preg_match('/^(NO|NON|non |ancora|scritti)/', $e) ? 'no' : ''); echo '<tr><td>' . h($r[0]) . '</td><td>' . h($r[1]) . '</td><td class="' . $cl . '">' . h($e) . '</td><td>' . h($r[3]) . '</td></tr>'; } ?>
</table></div>
<div class="box"><form method="post"><input type="hidden" name="k" value="<?= h(CHIAVE) ?>"><input type="hidden" name="azione" value="cancella">
<p>Quando hai fatto la schermata, cancella questo file:</p><button type="submit">Cancella il file di diagnosi</button></form></div>
</main></body></html>
