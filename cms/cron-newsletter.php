<?php
/**
 * Invio della newsletter, a piccoli giri. Va messo nel cron di cPanel ogni 5 minuti:
 *   /usr/local/bin/php /home/UTENTE/public_html/new/cron-newsletter.php
 * Ogni giro avvia le campagne arrivate alla loro ora e manda fino a 40 email, senza superare
 * il limite orario scelto nel pannello (Newsletter, Impostazioni). Senza niente da fare, finisce subito.
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
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/newsletter.php';

// Ogni giro svuota anche la coda di IndexNow (pannello SEO e AEO), se c'e qualcosa e la rete risponde.
try {
    require_once __DIR__ . '/includes/seo.php';
    if (seo_coda_conta() > 0) { $r = seo_coda_invia(10000, 15); @file_put_contents(__DIR__ . '/logs/seo.log', date('Y-m-d H:i:s') . ' | ' . $r . "\n", FILE_APPEND); }
} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/logs/seo.log', date('Y-m-d H:i:s') . ' | ERRORE ' . $e->getMessage() . "\n", FILE_APPEND);
}

try {
    $log = newsletter_invia_giro();
    if ($log) {
        $riga = date('Y-m-d H:i:s') . ' | ' . implode('; ', $log) . "\n";
        @file_put_contents(__DIR__ . '/logs/newsletter.log', $riga, FILE_APPEND);
        echo $riga;
    }
} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/logs/newsletter.log', date('Y-m-d H:i:s') . ' | ERRORE ' . $e->getMessage() . "\n", FILE_APPEND);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
