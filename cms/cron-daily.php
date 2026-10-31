<?php
/**
 * Cron giornaliero: applica i pacchetti di aggiornamento programmati, fa i compiti dei moduli del sito, manda a
 * IndexNow gli indirizzi in coda. Il lavoro sta in includes/cron.php (cron_giornaliero), lo stesso del pulsante
 * "Esegui adesso" della pagina Log cron del pannello.
 *
 * cPanel, Processi cron: una riga al giorno (per esempio alle 06:00) che lancia cron-daily.php nella cartella del CMS.
 * La riga con il percorso giusto la scrive la pagina Impostazioni, Log cron.
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
require_once __DIR__ . '/includes/cron.php';

$log = cron_giornaliero('cron');
if (PHP_SAPI === 'cli') echo $log;
