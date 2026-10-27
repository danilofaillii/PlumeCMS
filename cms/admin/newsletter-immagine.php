<?php
/**
 * Caricamento delle immagini dell'editor della newsletter. Vanno in <cartella delle immagini>/newsletter/AAAA/MM/ (cms_uploads_dir(); può stare fuori dalla cartella del CMS),
 * con un indirizzo pubblico: le email le mostrano da lì.
 */
require_once __DIR__ . '/includes/auth.php';
require_admin(); // sicurezza (ruoli): solo amministratore
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok((string)($_POST['_csrf'] ?? ''))) { // sicurezza: token e Origin/Referer
    http_response_code(403);
    exit(json_encode(['errore' => 'Sessione scaduta: ricarica la pagina.']));
}
$f = $_FILES['immagine'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) exit(json_encode(['errore' => 'Caricamento non riuscito (limite del server: ' . ini_get('upload_max_filesize') . ').']));
if ($f['size'] > 5 * 1048576) exit(json_encode(['errore' => 'Immagine troppo pesante: massimo 5 MB. Per le email ne basta molto meno.']));
$tipi = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
$info = @getimagesize($f['tmp_name']);
if (!$info || !isset($tipi[$info[2]])) exit(json_encode(['errore' => 'Formati accettati: JPG, PNG, GIF, WebP.']));

$rel = 'newsletter/' . date('Y/m');
$radice = cms_uploads_dir();
$dir = "$radice/$rel";
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) exit(json_encode(['errore' => 'La cartella delle immagini non è scrivibile.']));
$base = preg_replace('/[^a-z0-9-]+/', '-', strtolower(pathinfo($f['name'], PATHINFO_FILENAME))) ?: 'immagine';
$nome = trim(substr($base, 0, 60), '-') . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $tipi[$info[2]];
if (!move_uploaded_file($f['tmp_name'], "$dir/$nome")) exit(json_encode(['errore' => 'Il file non si è salvato.']));
require_once dirname(__DIR__) . '/includes/sicurezza-html.php';
sicurezza_immagine_ricodifica("$dir/$nome", $tipi[$info[2]]); // sicurezza: via metadati e qualunque cosa nascosta dopo i pixel
@chmod("$dir/$nome", 0644);
echo json_encode(['url' => cms_url_sito() . cms_uploads_url() . '/' . $rel . '/' . $nome]);
