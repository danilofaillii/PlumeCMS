<?php
/**
 * Aperture e clic delle email della newsletter.
 *   a=a  apertura: segna e risponde con un'immagine di un pixel
 *   a=c  clic: segna e rimanda all'indirizzo vero (u), solo http o https
 * Ogni richiesta porta una firma (t): senza quella giusta non si segna niente, e il rimando vale solo verso
 * il sito stesso (così nessuno può usare questo indirizzo per mandare la gente altrove con un link firmato a caso).
 */
require_once __DIR__ . '/../includes/newsletter.php';

ini_set('display_errors', '0');
$s = fn(string $k) => isset($_GET[$k]) && is_scalar($_GET[$k]) ? (string)$_GET[$k] : '';
$a = $s('a');
$c = (int)$s('c'); $f = (int)$s('f'); $p = (int)$s('p'); $i = (int)$s('i');
$t = $s('t');
$u = trim($s('u'));
header('Cache-Control: no-store, private');

try {
    // Solo un indirizzo con un host pulito: niente barre rovesciate, chiocciole o spazi, che i browser leggono a modo loro.
    if ($a === 'c' && preg_match('~^https?://[a-z0-9.-]+(?::\d+)?(?:[/?#]|$)~i', $u) && !preg_match('/[\r\n\\\\]/', $u)) {
        // Il clic si conta con la firma dell'evento (t). Il rimando verso un altro sito vale solo se anche l'indirizzo
        // e firmato (k, sicurezza: attacco 1); i link spediti prima, senza k, portano solo dentro il sito.
        newsletter_evento('clic', $c, $f, $p, $i, $t, $u);
        $urlFirmato = newsletter_url_firmato($c, $f, $p, $i, $u, $s('k'));
        $host = strtolower((string)parse_url($u, PHP_URL_HOST));
        $nostro = strtolower((string)parse_url(MODULI_URL, PHP_URL_HOST));
        $stessoSito = $host === $nostro || $host === preg_replace('/^www\./', '', $nostro) || 'www.' . $host === $nostro;
        header('Location: ' . ($urlFirmato || $stessoSito ? $u : MODULI_URL . '/'), true, 302);
        exit;
    }
    if ($a === 'a') newsletter_evento('apertura', $c, $f, $p, $i, $t);
} catch (Throwable $e) {
    error_log('nl: ' . $e->getMessage());
}
// Un GIF trasparente di un pixel, qualunque cosa sia successa.
$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
header('Content-Type: image/gif');
header('Content-Length: ' . strlen($gif));
echo $gif;
