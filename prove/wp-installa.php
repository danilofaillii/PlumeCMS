<?php
/**
 * Installa un WordPress di prova da riga di comando, per le prove dell'import (prova-importa-wp.js).
 *   DBPASS=... php prove/wp-installa.php <cartella di WordPress> <indirizzo> <database> [utente del database]
 * La cartella e una copia di WordPress (mai l'originale scaricato). Il database si svuota: tutto e ripetibile.
 * Scrive wp-config.php (WP_ENVIRONMENT_TYPE local: le password per le applicazioni funzionano anche senza HTTPS),
 * installa, mette i permalink con la data e copia il plugin di prova (prove/wp-plugin-prova.php) fra i mu-plugins.
 * Solo per le prove in locale: niente di questo va su un server vero.
 */
if (PHP_SAPI !== 'cli') exit;
[$_, $dir, $url, $db] = $argv + [null, null, null, null];
$utenteDb = $argv[4] ?? 'danilo';
$pass = (string)getenv('DBPASS');
if (!$dir || !$url || !$db || $pass === '') { fwrite(STDERR, "uso: DBPASS=... php wp-installa.php <cartella> <indirizzo> <database> [utente]\n"); exit(1); }
$dir = rtrim(realpath($dir) ?: $dir, '/');
if (!is_file("$dir/wp-load.php")) { fwrite(STDERR, "$dir non e una cartella di WordPress\n"); exit(1); }

// Database vuoto.
$pdo = new PDO('mysql:host=127.0.0.1;port=3308;dbname=' . $db . ';charset=utf8mb4', $utenteDb, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec("DROP TABLE `$t`");

$q = fn($s) => var_export((string)$s, true);
$sale = '';
foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $k) $sale .= "define('$k', " . $q(bin2hex(random_bytes(24))) . ");\n";
file_put_contents("$dir/wp-config.php", "<?php\n// WordPress di prova per l'import di plumeCMS (prove/wp-installa.php). Mai su un server vero.\n"
    . "define('DB_NAME', {$q($db)});\ndefine('DB_USER', {$q($utenteDb)});\ndefine('DB_PASSWORD', getenv('DBPASS') ?: " . $q($pass) . ");\n"
    . "define('DB_HOST', '127.0.0.1:3308');\ndefine('DB_CHARSET', 'utf8mb4');\ndefine('DB_COLLATE', '');\n$sale"
    . "\$table_prefix = 'wp_';\ndefine('WP_DEBUG', false);\ndefine('WP_ENVIRONMENT_TYPE', 'local');\ndefine('WP_HOME', {$q($url)});\ndefine('WP_SITEURL', {$q($url)});\n"
    . "define('AUTOMATIC_UPDATER_DISABLED', true);\ndefine('WP_HTTP_BLOCK_EXTERNAL', true);\ndefine('DISABLE_WP_CRON', true);\n"
    . "if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');\nrequire_once ABSPATH . 'wp-settings.php';\n");
@chmod("$dir/wp-config.php", 0600);

define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = parse_url($url, PHP_URL_HOST) . (parse_url($url, PHP_URL_PORT) ? ':' . parse_url($url, PHP_URL_PORT) : '');
$_SERVER['REQUEST_URI'] = '/';
require "$dir/wp-load.php";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
// L'amministratore: la password di accesso e casuale e non serve (l'import usa quella per le applicazioni).
$r = wp_install('Bottega di prova', 'admin', 'admin@example.org', true, '', wp_generate_password(20));
update_option('blogdescription', 'Un WordPress di prova per l\'import');
update_option('permalink_structure', '/%year%/%monthnum%/%postname%/');
update_option('timezone_string', 'Europe/Rome');
update_option('WPLANG', '');
if (!is_dir("$dir/wp-content/mu-plugins")) mkdir("$dir/wp-content/mu-plugins", 0755, true);
copy(__DIR__ . '/wp-plugin-prova.php', "$dir/wp-content/mu-plugins/plume-prova.php");
echo "WordPress installato: $url (utente admin)\n";
