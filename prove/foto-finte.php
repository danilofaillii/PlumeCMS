<?php
// Gli archivi di foto finti, per prova-ai-collegate.js e per il gruppo 46 di tools/sicurezza-prova.js (2.32.0).
// Rispondono come Openverse, Pexels e Unsplash (solo i campi che usa l'addon AI collegate) e servono i file delle foto.
//   php -S 127.0.0.1:<porta> prove/foto-finte.php
// Nel config.local.php del sito di prova: define('CMS_AI_FOTO_PROVA', 'http://127.0.0.1:<porta>');
// (lo scrive php prove/ai-cli.php <cartella> config-foto http://127.0.0.1:<porta>).
//   /openverse/?q=...           tre foto: cc0, by, by-nc (quest'ultima l'addon la deve scartare) e una cc0 ospitata altrove
//   /openverse/<id>/            una foto; id "nc1" ha licenza by-nc; id "altrove" ha il file su un host non in elenco
//   /openverse/<id>/thumb/      la miniatura (JPEG)
//   /pexels/search, /pexels/photos/<id>       solo con l'intestazione Authorization uguale a "pexels-chiave-di-prova-123"
//   /unsplash/search/photos, /unsplash/photos/<id>, /unsplash/photos/<id>/download   solo con "Client-ID unsplash-chiave-di-prova-123"
//   /file/<id>.jpg              una JPEG vera 640x420; id "codice" ha codice PHP in coda; "svg" è un SVG con uno script; "html" è una pagina
$u = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = 'http://' . $_SERVER['HTTP_HOST'];
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
@file_put_contents(sys_get_temp_dir() . '/foto-finte.log', date('c') . "\t" . $_SERVER['REQUEST_URI'] . "\t" . $auth . "\n", FILE_APPEND);
$json = function ($x, int $c = 200) { http_response_code($c); header('Content-Type: application/json'); echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; };
$ov = function (string $id) use ($base) {
    $lic = ['cc0a' => ['cc0', '1.0'], 'by1' => ['by', '4.0'], 'nc1' => ['by-nc', '4.0'], 'altrove' => ['cc0', '1.0']][$id] ?? ['cc0', '1.0'];
    return ['id' => $id, 'title' => 'Vaso di fiori ' . $id, 'creator' => 'Autrice ' . $id, 'creator_url' => 'https://autori.example/' . $id,
        'license' => $lic[0], 'license_version' => $lic[1], 'license_url' => 'https://creativecommons.org/licenses/' . $lic[0] . '/' . $lic[1] . '/',
        'url' => $id === 'altrove' ? 'https://foto.non-in-elenco.example/' . $id . '.jpg' : $base . '/file/' . $id . '.jpg', 'thumbnail' => $base . '/openverse/' . $id . '/thumb/',
        'foreign_landing_url' => 'https://archivio.example/foto/' . $id, 'width' => 640, 'height' => 420];
};
$jpeg = function (string $id) {
    if ($id === 'svg') { header('Content-Type: image/svg+xml'); echo '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'; exit; }
    if ($id === 'html') { header('Content-Type: text/html'); echo '<html><script>alert(1)</script></html>'; exit; }
    $im = imagecreatetruecolor(640, 420);
    imagefill($im, 0, 0, imagecolorallocate($im, 40 + strlen($id) * 10 % 200, 120, 180));
    imagestring($im, 5, 20, 20, 'foto ' . $id, imagecolorallocate($im, 255, 255, 255));
    ob_start(); imagejpeg($im, null, 80); $b = ob_get_clean();
    if ($id === 'codice') $b .= '<?php echo "attacco"; ?><script>alert(1)</script>';
    header('Content-Type: image/jpeg'); echo $b; exit;
};
if (preg_match('~^/file/([a-z0-9]+)\.jpg$~', $u, $m)) $jpeg($m[1]);
if (preg_match('~^/openverse/([a-z0-9]+)/thumb/?$~', $u, $m)) $jpeg('t' . $m[1]);
if ($u === '/openverse/' || $u === '/openverse') {
    $q = (string)($_GET['q'] ?? '');
    if (str_contains($q, 'errore')) $json(['detail' => 'errore'], 500);
    $json(['result_count' => 4, 'results' => [$ov('cc0a'), $ov('by1'), $ov('nc1'), $ov('altrove')], 'echo' => $_GET]);
}
if (preg_match('~^/openverse/([a-z0-9]+)/?$~', $u, $m)) $json($ov($m[1]));
$px = fn(string $id) => ['id' => (int)$id, 'alt' => 'Foto Pexels ' . $id, 'photographer' => 'Fotografo Pexels', 'photographer_url' => 'https://www.pexels.com/@prova', 'url' => 'https://www.pexels.com/photo/' . $id . '/',
    'width' => 640, 'height' => 420, 'src' => ['medium' => $base . '/file/px' . $id . '.jpg', 'large2x' => $base . '/file/px' . $id . '.jpg']];
if (str_starts_with($u, '/pexels/')) {
    if ($auth !== 'pexels-chiave-di-prova-123') $json(['error' => 'chiave'], 401);
    if ($u === '/pexels/search') $json(['photos' => [$px('101'), $px('102')]]);
    if (preg_match('~^/pexels/photos/(\d+)$~', $u, $m)) $json($px($m[1]));
}
$us = fn(string $id) => ['id' => $id, 'alt_description' => 'Foto Unsplash ' . $id, 'width' => 640, 'height' => 420, 'user' => ['name' => 'Fotografa Unsplash', 'links' => ['html' => 'https://unsplash.com/@prova']],
    'urls' => ['small' => $base . '/file/us' . $id . '.jpg', 'regular' => $base . '/file/us' . $id . '.jpg'], 'links' => ['html' => 'https://unsplash.com/photos/' . $id, 'download_location' => $base . '/unsplash/photos/' . $id . '/download']];
if (str_starts_with($u, '/unsplash/')) {
    if ($auth !== 'Client-ID unsplash-chiave-di-prova-123') $json(['errors' => ['chiave']], 401);
    if ($u === '/unsplash/search/photos') $json(['results' => [$us('aaa1')]]);
    if (preg_match('~^/unsplash/photos/([a-z0-9]+)/download$~', $u, $m)) $json(['url' => $base . '/file/us' . $m[1] . '.jpg']);
    if (preg_match('~^/unsplash/photos/([a-z0-9]+)$~', $u, $m)) $json($us($m[1]));
}
http_response_code(404); echo 'non trovato';
