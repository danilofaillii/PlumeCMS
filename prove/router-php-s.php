<?php
// Router per php -S: emula il .htaccess della radice (file veri serviti, il resto a index.php).
$u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = $_SERVER['DOCUMENT_ROOT'] . $u;
if (preg_match('~^/(includes|logs|aggiornamenti|docs)(/|$)~', $u)) { http_response_code(403); exit; }
if ($u !== '/' && (is_file($f) || (is_dir($f) && is_file(rtrim($f, '/') . '/index.php')))) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
