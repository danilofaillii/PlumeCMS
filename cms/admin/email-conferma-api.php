<?php
/**
 * 2.7.0: anteprima e prova dell'email di conferma dell'iscrizione (admin/assets/email-conferma.js). Solo POST, con il
 * token della sessione, risposta JSON, solo amministratore (un redattore riceve 403 da require_login, perché il file
 * finisce in -api.php e non è fra le sue pagine; qui lo si controlla di nuovo).
 *   azione     anteprima | prova
 *   contesto   form (una scheda di Form: titolo, nome e lista_id dicono per cosa ci si iscrive) | comuni (Newsletter > Impostazioni)
 *   ce[...]    le parti scritte adesso nei campi, anche non salvate
 * La prova va all'email dell'utente che la chiede, con un link finto che non conferma niente (nl_email_link_finto()).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once dirname(__DIR__) . '/includes/newsletter.php';
require_once dirname(__DIR__) . '/includes/newsletter-email.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function ec_api_esci(array $dati, int $codice = 200): never {
    http_response_code($codice);
    echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!is_admin()) ec_api_esci(['errore' => 'Riservato all\'amministratore.'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok((string)($_POST['_csrf'] ?? ''))) ec_api_esci(['errore' => 'Sessione scaduta: ricarica la pagina.'], 403);

$azione = (string)($_POST['azione'] ?? '');
$contesto = ($_POST['contesto'] ?? '') === 'form' ? 'form' : 'comuni';
$parti = nl_email_da_json(nl_email_pulisci($_POST['ce'] ?? []));
try {
    if ($contesto === 'form') {
        $lista = (int)($_POST['lista_id'] ?? 0);
        $cosa = mb_substr(trim((string)($_POST['titolo'] ?? '')), 0, 255) ?: mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 100) ?: (newsletter_nome_lista($lista) ?: 'questa lista');
        $c = ['principale' => false, 'cosa' => $cosa, 'parti' => $parti, 'comuni' => true];
    } else {
        $c = ['principale' => true, 'cosa' => lcfirst(newsletter_nome()), 'parti' => $parti, 'comuni' => false];
    }
    $io = current_user() ?: [];
    $c += ['nome' => 'Maria', 'link' => nl_email_link_finto()];
    [$oggetto, $html] = nl_email_conferma($c);
    if ($azione === 'anteprima') ec_api_esci(['oggetto' => $oggetto, 'html' => $html]);
    if ($azione === 'prova') {
        $a = (string)($io['email'] ?? '');
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) ec_api_esci(['errore' => 'Il tuo utente non ha un\'email valida: scrivila in Il mio account e riprova.'], 400);
        if (!posta_invia($a, '[PROVA] ' . $oggetto, $html, moduli_intestazioni(), 'prova')) {
            ec_api_esci(['errore' => 'La prova non è partita. Controlla la posta in uscita in Impostazioni, Configurazione email (' . posta_errore() . ').'], 502);
        }
        ec_api_esci(['oggetto' => $oggetto, 'html' => $html, 'messaggio' => 'Prova mandata a ' . $a . '. Guarda anche nello spam. Il link della prova non conferma niente.']);
    }
    ec_api_esci(['errore' => 'Azione sconosciuta.'], 400);
} catch (Throwable $e) {
    error_log('email-conferma-api: ' . $e->getMessage());
    ec_api_esci(['errore' => 'Anteprima non riuscita. Riprova tra un momento.'], 500);
}
