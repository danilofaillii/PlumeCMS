<?php
/**
 * Il widget "Prenota" dell'addon Prenotazioni: il calendario dei posti liberi e il modulo (includes/vista.php).
 * Si carica con gli altri widget (chiave 'widget' del manifesto), solo quando l'addon è acceso.
 * Gli asset stanno in addon/prenotazioni/assets/: editor_registra_asset() li vuole relativi a sito/assets/, da qui il "../../".
 */
require_once __DIR__ . '/vista.php';

$prenServizi = ['' => 'Il visitatore sceglie fra tutti'];
$prenPersone = ['' => 'Il visitatore sceglie (o chi è libero)'];
try {
    foreach (pren_servizi(true) as $s) $prenServizi[(string)$s['id']] = $s['nome'];
    foreach (pren_risorse(true) as $r) $prenPersone[(string)$r['id']] = 'Solo ' . $r['nome'];
} catch (Throwable $e) {
    error_log('prenotazioni: widget senza servizi: ' . $e->getMessage());
}

editor_registra_widget('prenota', [
    'etichetta' => 'Prenota', 'icona' => function_exists('icona_svg') ? (icona_svg('calendario-spunta') ?: '*') : '*', 'gruppo' => 'Dal sito',
    'controlli' => [
        ec('titolo', 'testo', 'Titolo', 'Prenota un appuntamento'),
        ec('intro', 'testolungo', 'Testo sopra il calendario', 'Scegli il servizio, il giorno e l\'ora: ti mandiamo subito l\'email di conferma.'),
        ec('servizio', 'scelta', 'Servizio', '', 'contenuto', ['opzioni' => $prenServizi, 'accetta' => '/^\d{1,9}$/', 'aiuto' => 'Un solo servizio, per una pagina dedicata (per esempio "Prenota il taglio"). I servizi si scrivono in Prenotazioni > Servizi.']),
        ec('persona', 'scelta', 'Con chi', '', 'contenuto', ['opzioni' => $prenPersone, 'accetta' => '/^\d{1,9}$/']),
        ec('pulsante', 'testo', 'Testo del pulsante', 'Prenota'),
    ],
], function (array $v, array $ctx): string {
    $codice = (string)($_GET['modulo'] ?? '');
    $id = 'pren-' . preg_replace('/[^a-z0-9]/', '', strtolower((string)($ctx['id'] ?? ''))) ?: 'pren';
    return pren_modulo_html(['servizio' => (int)$v['servizio'], 'risorsa' => (int)$v['persona'], 'titolo' => (string)$v['titolo'], 'intro' => (string)$v['intro'], 'pulsante' => (string)$v['pulsante'],
        'id' => $id, 'percorso' => (string)($ctx['percorso'] ?? '/'), 'errore' => str_starts_with($codice, 'pren-') ? substr($codice, 5) : '', 'anteprima' => !empty($ctx['anteprima'])]);
});
editor_registra_asset('prenota', ['css' => '../../addon/prenotazioni/assets/prenota.css', 'js' => '../../addon/prenotazioni/assets/prenota.js']);
