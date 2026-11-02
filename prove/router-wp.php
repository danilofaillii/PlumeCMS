<?php
// Router per php -S davanti a un WordPress di prova (prove/wp-installa.php): i file veri si servono, il resto va a
// index.php di WordPress, come fa il suo .htaccess con i permalink belli. Solo per le prove, mai su un server vero.
// Con WP_TOGLI_AUTH=1 il router fa come certi hosting (Aruba, 2.19.0): toglie le credenziali prima di WordPress.
if (getenv('WP_TOGLI_AUTH') === '1') unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
$u = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$f = $_SERVER['DOCUMENT_ROOT'] . $u;
if ($u !== '/' && (is_file($f) || (is_dir($f) && is_file(rtrim($f, '/') . '/index.php')))) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
