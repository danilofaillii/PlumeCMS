<?php
/**
 * Statistiche > Scarica il CSV (addon, 2.0.0): tutte le righe del periodo (giorno, pagina, provenienza, campagna,
 * dispositivo, visite, visitatori), separate da punto e virgola come le apre Excel in italiano, in UTF-8 con BOM.
 * Un valore che comincia con = + - @ prende un apostrofo davanti: un foglio di calcolo non lo esegue come formula.
 */
require_once __DIR__ . '/statistiche.php';

[, $dal, $al] = st_periodo($_GET);
$righe = st_righe($dal, $al);
$cella = function ($v): string {
    $v = (string)$v;
    if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) $v = "'" . $v;
    return '"' . str_replace('"', '""', $v) . '"';
};
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="statistiche-' . $dal . '-' . $al . '.csv"');
header('Cache-Control: no-store');
echo "\xEF\xBB\xBF" . implode(';', array_map($cella, ['giorno', 'pagina', 'provenienza', 'utm_source', 'utm_medium', 'utm_campaign', 'dispositivo', 'visite', 'visitatori'])) . "\r\n";
foreach ($righe as $r) echo implode(';', array_map($cella, [$r['giorno'], $r['pagina'], $r['fonte'], $r['utm_source'], $r['utm_medium'], $r['utm_campaign'], $r['dispositivo'], $r['visite'], $r['visitatori']])) . "\r\n";
exit;
