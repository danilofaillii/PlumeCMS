<?php
// Un "sito ufficiale" che si comporta male, per prova-versione.js (1.3.0). Router per php -S:
//   FINTO_ZIP=<pacchetto.zip> FINTO_CONTA=<file> php -S 127.0.0.1:8084 prove/finto-ufficiale.php
// Il primo pezzo del percorso e il modo: /<modo>/api/versione.php e /<modo>/scarica/<nome>/.
//   buono       risposta giusta (stesso zip, sha256 e dimensione veri)
//   redirect    la risposta JSON e un rimando 302 verso un altro sito
//   enorme      JSON di 100 KB
//   sha         sha256 sbagliato
//   host        lo zip sta su un altro host
//   corto       dimensione annunciata piu piccola del vero
//   grande      dimensione annunciata oltre il limite (60 MB)
//   lento       risponde dopo 8 secondi
//   zipredirect il JSON e giusto ma lo zip e un rimando 302
//   http        lo zip annunciato in http mentre il sito e in https (qui: schema diverso dal sito)
$u = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$modo = explode('/', ltrim($u, '/'))[0] ?? '';
$zip = (string)getenv('FINTO_ZIP');
// La versione che annuncia e quella nel nome del pacchetto (prova-versione.js la fa dal sito cliente, piu uno).
$v = preg_match('~plumecms-([\d.]+)-aggiornamento~', basename($zip), $m) ? $m[1] : '1.3.1';
$base = 'http://127.0.0.1:' . $_SERVER['SERVER_PORT'] . '/' . $modo;
if (str_ends_with($u, '/api/versione.php')) {
    if (getenv('FINTO_CONTA')) file_put_contents((string)getenv('FINTO_CONTA'), '.', FILE_APPEND); // quante richieste (prova della cache)
    if ($modo === 'redirect') { header('Location: http://127.0.0.1:8083/api/versione.php?serie=plumecms-ultima-aggiornamento', true, 302); exit; }
    if ($modo === 'lento') sleep(8);
    if ($modo === 'enorme') { header('Content-Type: application/json'); echo '{"versione":"' . $v . '","x":"' . str_repeat('a', 100000) . '"}'; exit; }
    $d = ['serie' => 'plumecms-ultima-aggiornamento', 'versione' => $v, 'nome' => 'plumecms-' . $v . '-aggiornamento.zip', 'sha256' => hash_file('sha256', $zip), 'dimensione' => filesize($zip), 'url' => $base . '/scarica/plumecms-' . $v . '-aggiornamento/'];
    if ($modo === 'sha') $d['sha256'] = str_repeat('0', 64);
    if ($modo === 'host') $d['url'] = 'http://127.0.0.2:' . $_SERVER['SERVER_PORT'] . '/host/scarica/plumecms-' . $v . '-aggiornamento/';
    if ($modo === 'corto') $d['dimensione'] = 100;
    if ($modo === 'grande') $d['dimensione'] = 60 * 1048576;
    if ($modo === 'http') $d['url'] = 'https://127.0.0.1:' . $_SERVER['SERVER_PORT'] . '/http/scarica/plumecms-' . $v . '-aggiornamento/';
    header('Content-Type: application/json');
    echo json_encode($d, JSON_UNESCAPED_SLASHES);
    exit;
}
if (str_contains($u, '/scarica/')) {
    if ($modo === 'zipredirect') { header('Location: http://127.0.0.2:8084/buono/scarica/plumecms-' . $v . '-aggiornamento/', true, 302); exit; }
    header('Content-Type: application/zip');
    readfile($zip);
    exit;
}
http_response_code(404);
