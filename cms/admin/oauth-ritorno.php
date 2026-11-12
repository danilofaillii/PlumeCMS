<?php
/**
 * 2.1: il ritorno di un collegamento OAuth 2 di un addon (per esempio Copia del sito altrove con Google Drive o Dropbox).
 *
 * È il "redirect URI" che l'amministratore registra presso il servizio: un indirizzo fisso, senza query, perché i servizi
 * lo confrontano carattere per carattere (https://<sito>/admin/oauth-ritorno.php). Il servizio aggiunge ?code=...&state=...
 * (oppure ?error=...). Lo state comincia con il nome dell'addon e un punto ("copia-altrove.<...>"): questa pagina lo passa
 * alla funzione 'oauth_ritorno' del manifesto di quell'addon, che controlla tutto lo state (firma, sessione, scadenza, una
 * volta sola), scambia il codice e finisce con il suo redirect, sempre dentro il pannello. Qui non si segue nessun
 * indirizzo che arriva dalla richiesta.
 *
 * Solo amministratore (la sessione del pannello: il cookie SameSite=Lax arriva anche tornando dal servizio). Un addon
 * spento, o uno state senza un addon che lo aspetta, danno 400.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_admin();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer'); // il codice nell'indirizzo non va a nessun altro sito
$state = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
$a = preg_match('/^([a-z][a-z0-9_-]{0,40})\./', $state, $m) ? $m[1] : '';
$mod = $a !== '' ? cms_modulo($a) : null;
if (!$mod || empty($mod['addon']) || !is_callable($mod['oauth_ritorno'] ?? null)) {
    http_response_code(400);
    exit('Collegamento non valido: questa risposta non appartiene a nessun addon acceso. Torna al pannello e ripeti il collegamento.');
}
($mod['oauth_ritorno'])($_GET);
http_response_code(400); // chi risponde chiude da sé; se torna qui, la risposta non era valida
exit('Collegamento non valido.');
