<?php
// Errori e deprecazioni: nel log del publisher (logs/php-errori.log), mai a video sul sito pubblico, nelle API e nei cron.
// Sul server il php.ini può avere display_errors acceso: una riga "Deprecated" stampata prima di header() rompe un redirect,
// un JSON o il pixel delle aperture. Il pannello (admin/includes/auth.php) definisce PUBLISHER_ERRORI prima e tiene gli avvisi veri a video.
if (!defined('PUBLISHER_ERRORI')) {
    define('PUBLISHER_ERRORI', 'log');
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    @mkdir(dirname(__DIR__) . '/logs', 0755, true);
    ini_set('error_log', dirname(__DIR__) . '/logs/php-errori.log');
    set_error_handler(function (int $n, string $msg, string $file, int $riga): bool {
        if ($n & (E_DEPRECATED | E_USER_DEPRECATED)) { error_log("Deprecato: $msg in $file:$riga"); return true; }
        return false;
    });
}

// Carica credenziali locali PRIMA (se esistono)
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}
// Il CMS: nome, versione, prefisso delle tabelle, base URL, impostazioni, moduli (includes/cms.php).
require_once __DIR__ . '/cms.php';

// Senza config.local.php non c'è un'installazione: dal browser si apre l'installatore (install/), da riga di comando ci si ferma.
if (!cms_installato() && !defined('CMS_INSTALLER')) {
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Manca includes/config.local.php: apri install/ dal browser per installare il CMS.\n"); exit(1); }
    header('Location: ' . cms_base() . '/install/', true, 302);
    exit;
}

// Ora italiana per tutto il publisher: le date e ore di uscita degli articoli sono italiane.
date_default_timezone_set(defined('CMS_FUSO') && in_array(CMS_FUSO, timezone_identifiers_list(), true) ? CMS_FUSO : 'Europe/Rome');

// Defaults — solo se non già definiti da config.local.php
if (!defined('DB_HOST'))    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_NAME'))    define('DB_NAME', getenv('DB_NAME') ?: '');
if (!defined('DB_USER'))    define('DB_USER', getenv('DB_USER') ?: '');
if (!defined('DB_PASS'))    define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8');

// sicurezza: intestazioni di base su ogni risposta del CMS (X-Powered-By tolto, nosniff, niente frame esterni). La CSP la mettono sito e pannello.
require_once __DIR__ . '/sicurezza.php';
if (PHP_SAPI !== 'cli' && !headers_sent()) sicurezza_intestazioni_base();

// Le costanti storiche del publisher: dal modulo del sito (o dalla richiesta), non più scritte qui.
if (!defined('SITE_URL'))  define('SITE_URL', getenv('SITE_URL') ?: ((string)cms_predefinito('sito_url') ?: cms_origine()) . cms_base());
if (!defined('SITE_NAME')) define('SITE_NAME', (string)(cms_predefinito('sito_nome') ?? CMS_NOME) . ((string)cms_predefinito('sito_sottotitolo') !== '' ? ' — ' . cms_predefinito('sito_sottotitolo') : ''));
if (!defined('SITE_DESC')) define('SITE_DESC', (string)(cms_predefinito('sito_descrizione') ?? ''));
