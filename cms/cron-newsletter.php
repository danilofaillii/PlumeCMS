<?php
/**
 * Invio della newsletter, a piccoli giri. Va messo nel cron di cPanel ogni 5 minuti:
 *   /usr/local/bin/php /home/UTENTE/public_html/cron-newsletter.php   (la cartella del CMS)
 * Ogni giro avvia le campagne arrivate alla loro ora e manda fino a 40 email, senza superare
 * il limite orario scelto nel pannello (Newsletter, Impostazioni), poi i flussi automatici. Senza niente da fare, finisce subito.
 * Il lavoro sta in includes/cron.php (cron_newsletter), lo stesso del pulsante "Esegui adesso" della pagina Log cron.
 */
if (PHP_SAPI !== 'cli') {
    // Come cron-daily.php: dal web solo con CMS_CRON_TOKEN (almeno 32 caratteri) nel config.local.php.
    if (is_file(__DIR__ . '/includes/config.local.php')) require_once __DIR__ . '/includes/config.local.php';
    $tokenCron = defined('CMS_CRON_TOKEN') ? (string)CMS_CRON_TOKEN : '';
    $dato = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? (is_string($_GET['token'] ?? null) ? $_GET['token'] : ''));
    if (strlen($tokenCron) < 32 || !hash_equals($tokenCron, $dato)) { http_response_code(403); exit; }
}
// Il cron di cPanel usa il PHP di sistema, che può essere più vecchio di quello del sito: con uno prima dell'8.1
// il codice non si legge nemmeno. Meglio una riga chiara nel log che un errore di sintassi.
if (PHP_VERSION_ID < 80100) { fwrite(STDERR, "Questo cron richiede PHP 8.1 o successivo (qui " . PHP_VERSION . "). In cPanel, Processi cron, usa il binario della versione del sito, per esempio /opt/cpanel/ea-php84/root/usr/bin/php\n"); exit(1); }
require_once __DIR__ . '/includes/cron.php';

try {
    $righe = cron_newsletter('cron');
    if ($righe) echo date('Y-m-d H:i:s') . ' | ' . implode('; ', $righe) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
