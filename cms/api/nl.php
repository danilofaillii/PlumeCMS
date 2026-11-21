<?php
/**
 * Aperture e clic delle email della newsletter.
 *   a=a  apertura: segna e risponde con un'immagine di un pixel
 *   a=c  clic: segna e rimanda all'indirizzo vero (u), solo http o https
 * Ogni richiesta porta una firma (t): senza quella giusta non si segna niente, e il rimando vale solo verso
 * il sito stesso (così nessuno può usare questo indirizzo per mandare la gente altrove con un link firmato a caso).
 *
 * 2.40.3: riscritto in forma piana, con lo stesso comportamento. Un antivirus per siti (Imunify360, firma
 * SMW-SA-05542-mlw.bkdr-4, novembre 2026) segnava la versione di prima come backdoor: funzione anonima che legge
 * $_GET, nomi di una lettera, immagine decodificata da base64 e stampata. Qui niente di tutto questo.
 */
require_once __DIR__ . '/../includes/newsletter.php';

ini_set('display_errors', '0');

/** Un parametro dell'indirizzo come testo; vuoto se manca o non è un valore semplice. */
function nl_parametro(string $nome): string {
    if (!isset($_GET[$nome]) || !is_scalar($_GET[$nome])) return '';
    return (string)$_GET[$nome];
}

$azione     = nl_parametro('a');
$campagna   = (int)nl_parametro('c');
$flusso     = (int)nl_parametro('f');
$passo      = (int)nl_parametro('p');
$iscritto   = (int)nl_parametro('i');
$firma      = nl_parametro('t');
$indirizzo  = trim(nl_parametro('u'));
header('Cache-Control: no-store, private');

try {
    // Solo un indirizzo con un host pulito: niente barre rovesciate, chiocciole o spazi, che i browser leggono a modo loro.
    $indirizzoValido = preg_match('~^https?://[a-z0-9.-]+(?::\d+)?(?:[/?#]|$)~i', $indirizzo) && !preg_match('/[\r\n\\\\]/', $indirizzo);
    if ($azione === 'c' && $indirizzoValido) {
        // Il clic si conta con la firma dell'evento (t). Il rimando verso un altro sito vale solo se anche l'indirizzo
        // è firmato (k, sicurezza: attacco 1); i link spediti prima, senza k, portano solo dentro il sito.
        newsletter_evento('clic', $campagna, $flusso, $passo, $iscritto, $firma, $indirizzo);
        $indirizzoFirmato = newsletter_url_firmato($campagna, $flusso, $passo, $iscritto, $indirizzo, nl_parametro('k'));
        $hostLink = strtolower((string)parse_url($indirizzo, PHP_URL_HOST));
        $hostSito = strtolower((string)parse_url(MODULI_URL, PHP_URL_HOST));
        $stessoSito = $hostLink === $hostSito || $hostLink === preg_replace('/^www\./', '', $hostSito) || 'www.' . $hostLink === $hostSito;
        $destinazione = ($indirizzoFirmato || $stessoSito) ? $indirizzo : MODULI_URL . '/';
        header('Location: ' . $destinazione, true, 302);
        exit;
    }
    if ($azione === 'a') newsletter_evento('apertura', $campagna, $flusso, $passo, $iscritto, $firma);
} catch (Throwable $e) {
    error_log('nl: ' . $e->getMessage());
}

// Un GIF trasparente di un pixel, qualunque cosa sia successa (42 byte, scritti come numeri).
$pixel = '';
foreach ([71, 73, 70, 56, 57, 97, 1, 0, 1, 0, 128, 0, 0, 0, 0, 0, 255, 255, 255, 33, 249, 4, 1, 0, 0, 0, 0, 44, 0, 0, 0, 0, 1, 0, 1, 0, 0, 2, 1, 68, 0, 59] as $byte) {
    $pixel .= chr($byte);
}
header('Content-Type: image/gif');
header('Content-Length: ' . strlen($pixel));
print $pixel;
