<?php
/**
 * Una sfida per il captcha dei moduli (vedi includes/moduli.php). La chiede moduli.js quando
 * si spunta "Sono una persona". Nessun dato personale: solo numeri casuali e una firma.
 */
require_once __DIR__ . '/../includes/moduli.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
try {
    echo json_encode(moduli_captcha_sfida());
} catch (Throwable $e) {
    error_log('captcha: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['errore' => 'non disponibile']);
}
