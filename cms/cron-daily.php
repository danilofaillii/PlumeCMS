<?php
/**
 * Cron giornaliero: applica i pacchetti in scadenza, manda l'email del mattino con gli articoli
 * che escono oggi (escono da soli alla loro ora) e manda i testi social.
 *
 * cPanel, Processi cron: una riga al giorno alle 06:00 ora italiana che lancia new/cron-daily.php
 */

// sicurezza (attacco 2): il cron gira da riga di comando (cPanel, Processi cron: /usr/local/bin/php .../cron-daily.php).
// Dal web risponde 403. Un hosting che sa chiamare il cron solo via HTTP mette nel config.local.php
// define('CMS_CRON_TOKEN', '<almeno 32 caratteri a caso>') e chiama cron-daily.php?token=<lo stesso valore>.
if (PHP_SAPI !== 'cli') {
    if (is_file(__DIR__ . '/includes/config.local.php')) require_once __DIR__ . '/includes/config.local.php';
    $tokenCron = defined('CMS_CRON_TOKEN') ? (string)CMS_CRON_TOKEN : '';
    $dato = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? (is_string($_GET['token'] ?? null) ? $_GET['token'] : ''));
    if (strlen($tokenCron) < 32 || !hash_equals($tokenCron, $dato)) { http_response_code(403); header('Content-Type: text/plain'); exit("Il cron si lancia da riga di comando.\n"); }
}
// Il cron di cPanel usa il PHP di sistema, che può essere più vecchio di quello del sito: con uno prima dell'8.1
// il codice non si legge nemmeno. Meglio una riga chiara nel log che un errore di sintassi.
if (PHP_VERSION_ID < 80100) { fwrite(STDERR, "Questo cron richiede PHP 8.1 o successivo (qui " . PHP_VERSION . "). In cPanel, Processi cron, usa il binario della versione del sito, per esempio /opt/cpanel/ea-php84/root/usr/bin/php\n"); exit(1); }
require_once __DIR__ . '/includes/config.php';

$logFile = __DIR__ . '/logs/cron-daily.log';
@mkdir(__DIR__ . '/logs', 0755, true);

$log = date('Y-m-d H:i:s') . " | Cron avviato\n";

// Step 0: applica i pacchetti di aggiornamento in scadenza (pannello Aggiornamenti)
$aggScript = __DIR__ . '/includes/aggiornamenti.php';
if (file_exists($aggScript)) {
    try {
        require_once $aggScript;
        $righe = agg_applica_in_attesa();
        if ($righe) {
            foreach ($righe as $r) $log .= "  Aggiornamento: $r\n";
        } else {
            $log .= "  Nessun pacchetto di aggiornamento in scadenza\n";
        }
    } catch (Throwable $e) {
        $log .= "  ERRORE aggiornamenti: " . $e->getMessage() . "\n";
    }
}

// Step 1: l'email del mattino con gli articoli che escono oggi. Gli articoli escono da soli alla loro ora.
require_once __DIR__ . '/includes/sito.php';
// I passi dei moduli (danilofailli.it: l'email del mattino con gli articoli che escono oggi e i testi social del pillar).
foreach (cms_moduli_elenco('cron') as $x) {
    try {
        $esito = ($x['valore'])();
        $log .= '  ' . ucfirst((string)$x['chiave']) . ': ' . (is_string($esito) && $esito !== '' ? $esito : 'fatto') . "\n";
    } catch (Throwable $e) {
        $log .= '  ERRORE ' . $x['chiave'] . ' (' . $x['modulo'] . '): ' . $e->getMessage() . "\n";
    }
}

// Step 3: IndexNow, la coda degli indirizzi pubblicati o modificati (pannello SEO e AEO)
$seoScript = __DIR__ . '/includes/seo.php';
if (file_exists($seoScript)) {
    try {
        require_once $seoScript;
        $log .= '  IndexNow: ' . seo_coda_invia(10000, 20) . "\n";
    } catch (Throwable $e) {
        $log .= "  ERRORE IndexNow: " . $e->getMessage() . "\n";
    }
}

$log .= "  Completato.\n\n";
@file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);

if (php_sapi_name() === 'cli') {
    echo $log;
}
