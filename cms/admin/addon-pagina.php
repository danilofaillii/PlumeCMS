<?php
/**
 * Le pagine del pannello degli addon accesi (2.0.0): addon-pagina.php?a=<addon>&p=<pagina>.
 * La pagina è quella dichiarata nel manifesto dell'addon, chiave 'pannello'. Solo amministratore.
 * Un addon spento, o una pagina che non c'è, danno 404.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_admin();
$a = (string)($_GET['a'] ?? '');
$p = (string)($_GET['p'] ?? '');
$m = preg_match('/^[a-z][a-z0-9_-]{0,40}$/', $a) && preg_match('/^[a-z][a-z0-9_-]{0,40}$/', $p) ? cms_modulo($a) : null;
if (!$m || empty($m['addon']) || empty($m['pannello'][$p])) { http_response_code(404); exit('Pagina dell\'addon non trovata (l\'addon è spento?).'); }
cms_pagina_modulo($a, $p);
