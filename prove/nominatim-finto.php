<?php
// Il servizio delle coordinate finto, per prova-sedi.js e per il gruppo 42 di tools/sicurezza-prova.js (2.27.0).
// Risponde come Nominatim di OpenStreetMap (/search?q=...&format=jsonv2): un posto per ogni indirizzo che contiene una
// città conosciuta, niente per gli altri; alcune parole chiedono una risposta cattiva.
//   php -S 127.0.0.1:<porta> prove/nominatim-finto.php
// Nel config.local.php del sito di prova: define('CMS_NOMINATIM_URL', 'http://127.0.0.1:<porta>/search'); define('CMS_NOMINATIM_PROVA', true);
//   q con "cattivo"   -> HTML con uno script, codice 200 (il pannello non deve mostrarlo né eseguirlo)
//   q con "numeri"    -> coordinate che non sono numeri buoni (1e99, NaN, testo)
//   q con "fuori"     -> coordinate fuori dai limiti (lat 95, lon 200)
//   q con "vuoto"     -> [] (nessun posto)
//   q con "errore"    -> codice 503
//   q con "lento"     -> aspetta 15 secondi (oltre il timeout del CMS)
$q = (string)($_GET['q'] ?? '');
$agente = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
// Il registro delle richieste: la prova controlla lo User-Agent (plumeCMS/<versione> (<sito>)).
@file_put_contents(sys_get_temp_dir() . '/nominatim-finto.log', date('c') . "\t" . $agente . "\t" . $q . "\n", FILE_APPEND);
$l = mb_strtolower($q);
if (str_contains($l, 'cattivo')) { header('Content-Type: text/html; charset=utf-8'); echo '<html><body><script>window.__cattivo=1;alert(42)</script><img src=x onerror=alert(42)>cattivo</body></html>'; exit; }
if (str_contains($l, 'errore')) { http_response_code(503); header('Content-Type: text/plain'); echo 'servizio fermo'; exit; }
if (str_contains($l, 'lento')) { sleep(15); }
header('Content-Type: application/json; charset=utf-8');
if (str_contains($l, 'numeri')) { echo json_encode([['lat' => '1e99', 'lon' => 'NaN', 'display_name' => 'numeri']]); exit; }
if (str_contains($l, 'fuori')) { echo json_encode([['lat' => '95', 'lon' => '200', 'display_name' => 'fuori']]); exit; }
if (str_contains($l, 'vuoto')) { echo '[]'; exit; }
$citta = [
    'bologna' => ['44.4949', '11.3426'], 'firenze' => ['43.7696', '11.2558'], 'milano' => ['45.4642', '9.1900'], 'roma' => ['41.9028', '12.4964'],
    'casalecchio' => ['44.4767', '11.2785'], 'imola' => ['44.3533', '11.7138'], 'torino' => ['45.0703', '7.6869'],
];
foreach ($citta as $nome => [$lat, $lon]) {
    if (str_contains($l, $nome)) {
        // Un numero civico sposta un po' il punto, così due indirizzi nella stessa città non hanno le stesse coordinate.
        $n = preg_match('/\b(\d{1,4})\b/', $q, $m) ? (int)$m[1] : 0;
        $nomePosto = trim($q) . (preg_match('/italia/i', $q) ? '' : ', Italia');
        echo json_encode([['place_id' => 1000 + $n, 'lat' => (string)round((float)$lat + $n * 0.0001, 6), 'lon' => (string)round((float)$lon + $n * 0.0001, 6), 'display_name' => $nomePosto]]);
        exit;
    }
}
echo '[]';
