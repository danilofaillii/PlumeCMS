<?php
// Router per php -S: fa quello che fa il .htaccess della radice (cms/.htaccess), così le prove di attacco valgono anche
// senza Apache: cartelle e file vietati 403, file veri serviti, il resto a index.php. Più temi/.htaccess (temi, fase 6).
// Con CMS_CARTELLA=/test nell'ambiente il CMS sta in <radice>/test e il sito si apre su /test (come duhtron.it/test).
$cartella = rtrim((string)getenv('CMS_CARTELLA'), '/');
$u = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($cartella !== '' && $u !== $cartella && !str_starts_with($u, $cartella . '/')) { http_response_code(404); exit; }
$r = ltrim(substr($u, strlen($cartella)), '/');
$f = $_SERVER['DOCUMENT_ROOT'] . $u;
$vieta = [
    '~^(includes|logs|aggiornamenti|privato|data|sql|docs|tools|test|test-definizioni)(/|$)~',
    '~^moduli/[^/]+/(includes|sql|docs)(/|$)~',
    '~^moduli/.*\.(php|phtml|phar)$~',
    '~^addon/[^/]+/(includes|sql|docs)(/|$)~', // 2.0.0: gli addon come i moduli
    '~^addon/.*\.(php|phtml|phar|php[0-9]|phps)$~i',
    '~^moduli/addon-attivi\.json$~',
    '~^uploads/riservati-[^/]+(/|$)~', // 1.11.0: i file riservati (su Apache il loro .htaccess)
    '~^temi/.*\.(php|phtml|phar|php[0-9]|phps)$~i',
    '~^(VERSIONE|CHANGELOG\.md|README\.md|INSTALLA\.md|composer\.(json|lock)|package(-lock)?\.json)$~',
    '~(^|/)\.(git|env|htpasswd|installato|gitkeep)~',
    '~^cron-[a-z-]+\.php$~',
    '~^(uploads|aggiornamenti|logs|assets)/.*\.(php|phtml|php[0-9]|phps|phar|pl|py|cgi|sh)$~',
    '~^temi/.*\.(php|phtml|php[0-9]|phps|phar|pht|pl|py|cgi|sh|shtml|json|htaccess|ini)$~i',
];
// 1.4.0: con logs/controllo-aperto il router fa come un server che non legge il .htaccess (prova-controlla-server.js).
$aperto = is_file($_SERVER['DOCUMENT_ROOT'] . $cartella . '/logs/controllo-aperto');
if (!$aperto) foreach ($vieta as $re) if (preg_match($re, $r)) { http_response_code(403); exit; }
if ($u !== '/' && $u !== $cartella . '/' && (is_file($f) || (is_dir($f) && is_file(rtrim($f, '/') . '/index.php')))) return false;
$_SERVER['SCRIPT_NAME'] = $cartella . '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . $cartella . '/index.php';
