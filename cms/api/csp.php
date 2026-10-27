<?php
/**
 * Raccoglie i rapporti della Content-Security-Policy che i browser mandano quando la CSP e in modalita "solo rapporto"
 * (o blocca qualcosa in modalita "applica"). Li legge la pagina Sicurezza del pannello, scheda Rapporti CSP.
 * Nessun dato personale: direttiva violata, cosa e stato bloccato, su quale pagina. Massimo 500 voci distinte.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/sicurezza.php';

http_response_code(204);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
$corpo = (string)file_get_contents('php://input', false, null, 0, 65536);
if ($corpo === '') exit;
$contesto = ($_GET['c'] ?? '') === 'pannello' ? 'pannello' : 'sito';
try {
    $j = json_decode($corpo, true);
    if (!is_array($j)) exit;
    // Formato classico {"csp-report": {...}} oppure Reporting API [{"type":"csp-violation","body":{...}}]
    $rapporti = [];
    if (isset($j['csp-report']) && is_array($j['csp-report'])) $rapporti[] = $j['csp-report'];
    elseif (isset($j[0])) foreach (array_slice($j, 0, 10) as $r) if (is_array($r['body'] ?? null)) $rapporti[] = $r['body'];
    // Al massimo 60 rapporti al minuto per IP: un flusso piu alto si ignora.
    $chiave = 'csp:' . sicurezza_ip() . ':' . date('YmdHi');
    $f = sys_get_temp_dir() . '/cms-csp-' . md5($chiave);
    $n = is_file($f) ? (int)file_get_contents($f) : 0;
    if ($n > 60) exit;
    file_put_contents($f, (string)($n + count($rapporti)));
    foreach ($rapporti as $r) sicurezza_csp_rapporto($contesto, $r);
} catch (Throwable $e) {
    error_log('csp: ' . $e->getMessage());
}
