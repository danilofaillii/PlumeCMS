<?php
/**
 * 2.2: la ricerca della finestra "Scegli dal sito" (admin/assets/scegli-link.js). Solo POST, con il token della sessione,
 * risposta JSON, come editor-api.php. La può chiamare anche il redattore (AUTH_REDATTORE_PAGINE): vede pagine, articoli,
 * categorie e tag come nel pannello, bozze comprese; dei contenuti degli addon solo quelli già pubblici.
 *   q      la ricerca nel titolo (vuota: i primi 60, in ordine di titolo)
 *   cosa   '' oppure pagina, articolo, categoria, tag, o il tipo di un addon (evento, prodotto, corso...)
 * ("cosa" e non "tipo": require_login legge ?tipo= per i modelli.) Motore in includes/collegamenti.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once dirname(__DIR__) . '/includes/collegamenti.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function link_api_esci(array $dati, int $codice = 200): never {
    http_response_code($codice);
    echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok((string)($_POST['_csrf'] ?? ''))) { // sicurezza: token e Origin/Referer
    link_api_esci(['errore' => 'Sessione scaduta: ricarica la pagina.'], 403);
}
$q = is_string($_POST['q'] ?? null) ? $_POST['q'] : '';
$tipo = is_string($_POST['cosa'] ?? null) ? (string)preg_replace('/[^a-z0-9-]/', '', strtolower(substr($_POST['cosa'], 0, 40))) : '';
try {
    link_api_esci(collegamenti_cerca($q, $tipo, is_admin()));
} catch (Throwable $e) {
    error_log('link-api: ' . $e->getMessage());
    link_api_esci(['errore' => 'Ricerca non riuscita. Riprova tra un momento.'], 500);
}
