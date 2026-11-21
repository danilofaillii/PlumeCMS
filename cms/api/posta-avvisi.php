<?php
/**
 * Gli avvisi dei servizi di spedizione (2.1.0, includes/consegna.php): rimbalzi, segnalazioni di spam, uscite.
 * Un indirizzo per servizio: ?s=brevo|ses|mailgun&k=<segreto>. Il segreto lo dà il pannello (Consegna delle email,
 * Rimbalzi e spam); senza quello giusto la richiesta si rifiuta con 403 e non si legge niente.
 * In più: Mailgun firma ogni avviso con la sua chiave (HMAC), Amazon SNS con il suo certificato. Brevo non firma:
 * per lui vale il segreto nell'indirizzo.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/consegna.php';
require_once dirname(__DIR__) . '/includes/posta.php';

ini_set('display_errors', '0');
header('Cache-Control: no-store, private');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$rispondi = function (int $codice, array $dati): never {
    http_response_code($codice);
    echo json_encode($dati, JSON_UNESCAPED_UNICODE);
    exit;
};

$s = isset($_GET['s']) && is_string($_GET['s']) ? $_GET['s'] : '';
$k = isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '';
if (!in_array($s, ['brevo', 'ses', 'mailgun'], true)) $rispondi(404, ['errore' => 'servizio sconosciuto']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $rispondi(405, ['errore' => 'solo POST']);
try {
    if ($k === '' || !hash_equals(consegna_webhook_segreto($s), $k)) $rispondi(403, ['errore' => 'segreto sbagliato']);
    $corpo = (string)file_get_contents('php://input', false, null, 0, 262144);
    $j = json_decode($corpo, true);
    // Mailgun può mandare anche un modulo (vecchio formato): si accetta solo il JSON firmato.
    if (!is_array($j)) $rispondi(400, ['errore' => 'serve un JSON']);
    if ($s === 'brevo') $esiti = consegna_avvisi_brevo($j);
    elseif ($s === 'mailgun') $esiti = consegna_avvisi_mailgun($j);
    else $esiti = consegna_avvisi_ses($j);
    if ($esiti === null) $rispondi(403, ['errore' => 'firma non valida']);
    $rispondi(200, ['ok' => true, 'esiti' => $esiti]);
} catch (Throwable $e) {
    error_log('posta-avvisi: ' . $e->getMessage());
    $rispondi(500, ['errore' => 'errore interno']);
}
