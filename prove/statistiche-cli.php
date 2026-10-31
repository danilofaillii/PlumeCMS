<?php
// Le tabelle dell'addon Statistiche (2.0.0) per prova-addon-statistiche.js.
// uso: php prove/statistiche-cli.php <cartella CMS> dump     -> JSON con tutte le righe (i campi binari in esadecimale)
//      php prove/statistiche-cli.php <cartella CMS> azzera   -> svuota le due tabelle
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/db.php';
$azione = $argv[2] ?? 'dump';
if ($azione === 'azzera') {
    foreach (['stat_visite', 'stat_impronte'] as $t) db()->exec('DELETE FROM ' . tbl($t));
    echo "azzerate\n"; exit;
}
$out = [];
foreach (['stat_visite', 'stat_impronte'] as $t) {
    $righe = db()->query('SELECT * FROM ' . tbl($t))->fetchAll();
    foreach ($righe as &$r) foreach (['chiave', 'impronta'] as $k) if (isset($r[$k])) $r[$k] = bin2hex($r[$k]);
    unset($r);
    $out[$t] = $righe;
    $out['colonne'][$t] = array_column(db()->query('SHOW COLUMNS FROM ' . tbl($t))->fetchAll(), 'Field');
}
$out['sale'] = json_decode((string)get_setting('statistiche_sale', ''), true);
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
