<?php
/**
 * L'ultima versione di una serie del download manager (1.3.0), in JSON, per chi vuole sapere se c'è qualcosa di nuovo:
 *   GET /api/versione.php?serie=plumecms-ultima-aggiornamento
 *   {"serie":..., "versione":"1.3.1", "nome":"plumecms-1.3.1-aggiornamento.zip", "sha256":..., "dimensione":123456,
 *    "url":"https://sito/scarica/plumecms-1.3.1-aggiornamento/", "data":"2026-10-27T12:00:00+01:00"}
 * Generico: vale per ogni serie (includes/scaricabili.php: un file con la versione nel nome). Così il sito da cui si
 * scarica plumeCMS (che e un plumeCMS) dice da se qual e l'ultima versione, e i pannelli degli altri siti la chiedono
 * (includes/versione-nuova.php). Solo dati già pubblici: gli stessi file che /scarica/ offre a tutti.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/scaricabili.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
$serie = (string)($_GET['serie'] ?? '');
$rispondi = function (int $codice, array $dati): never {
    http_response_code($codice);
    header('Cache-Control: ' . ($codice === 200 ? 'public, max-age=300' : 'no-store'));
    echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') $rispondi(405, ['errore' => 'solo GET']);
if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,120}$/', $serie) || !str_contains($serie, 'ultima')) $rispondi(400, ['errore' => 'serie non valida']);
try {
    $x = scaricabili_serie()[$serie] ?? null;
    if (!$x) $rispondi(404, ['errore' => 'serie non trovata']);
    $rispondi(200, [
        'serie' => $serie, 'versione' => $x['versione_nome'], 'nome' => $x['file'], 'titolo' => $x['titolo'],
        'sha256' => scaricabili_sha256($x['file']), 'dimensione' => $x['peso'],
        'url' => cms_url_sito() . '/scarica/' . $x['nome'] . '/', 'data' => date('c', $x['data']),
    ]);
} catch (Throwable $e) {
    error_log('api/versione: ' . $e->getMessage());
    $rispondi(500, ['errore' => 'non disponibile']);
}
