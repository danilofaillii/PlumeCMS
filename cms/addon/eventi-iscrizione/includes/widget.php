<?php
/**
 * I widget dell'addon Eventi con iscrizione:
 *  - "Iscrizione all'evento": il riquadro di un evento con il modulo d'iscrizione;
 *  - "Eventi con iscrizione": l'elenco dei prossimi eventi con i posti rimasti. Lo disegna il widget Eventi del nucleo
 *    (stesso aspetto, stessi dati per Google con JSON_HEX_TAG): qui si preparano solo le sue voci.
 * Gli asset stanno in addon/eventi-iscrizione/assets/ (relativi a sito/assets/, da qui il "../../").
 */
require_once __DIR__ . '/vista.php';

$eviScelte = ['' => 'Il prossimo evento'];
try {
    foreach (evi_eventi() as $e) $eviScelte[(string)$e['id']] = $e['titolo'] . ' (' . date('d/m/Y', (int)$e['inizio_ts']) . ($e['stato'] === 'bozza' ? ', bozza' : '') . ')';
} catch (Throwable $e) {
    error_log('eventi: widget senza eventi: ' . $e->getMessage());
}

editor_registra_widget('evento_iscrizione', [
    'etichetta' => 'Iscrizione all\'evento', 'icona' => function_exists('icona_svg') ? (icona_svg('calendario-giorno') ?: '*') : '*', 'gruppo' => 'Dal sito',
    'controlli' => [
        ec('evento', 'scelta', 'Evento', '', 'contenuto', ['opzioni' => $eviScelte, 'accetta' => '/^\d{1,9}$/', 'aiuto' => 'Gli eventi si scrivono nel pannello, in Eventi.']),
        ec('titolo', 'testo', 'Titolo (vuoto: il nome dell\'evento)', ''),
        ec('mostra_scheda', 'interruttore', 'Mostra data, luogo e posti', true),
        ec('link_pagina', 'interruttore', 'Link alla pagina dell\'evento', true),
    ],
], function (array $v, array $ctx): string {
    $id = (int)$v['evento'];
    $e = $id ? evi_evento($id) : null;
    if (!$id) foreach (evi_eventi(true, true) as $x) if ($x['stato'] === 'pubblicato') { $e = evi_evento((int)$x['id']); break; }
    if (!$e || ($e['stato'] === 'bozza' && empty($ctx['anteprima']))) {
        return !empty($ctx['anteprima']) ? '<div class="ed-vuoto">Scegli un evento (si scrivono nel pannello, in Eventi), oppure pubblicane uno.</div>' : '<p class="evi-chiuse">Al momento non ci sono eventi in programma.</p>';
    }
    $codice = (string)($_GET['modulo'] ?? '');
    $html = evi_modulo_html($e, ['id' => 'evi-' . preg_replace('/[^a-z0-9]/', '', strtolower((string)($ctx['id'] ?? ''))), 'percorso' => (string)($ctx['percorso'] ?? '/'),
        'errore' => str_starts_with($codice, 'evi-') ? substr($codice, 4) : '', 'titolo' => trim((string)$v['titolo']) ?: (string)$e['titolo'], 'scheda' => (bool)$v['mostra_scheda']]);
    if ($v['link_pagina']) $html .= '<p class="evi-link-pagina"><a href="' . h(evi_url_evento($e)) . '">Tutti i dettagli dell\'evento</a></p>';
    // I dati per Google stanno nella pagina dell'evento: qui no, per non scriverli due volte sullo stesso evento.
    return $html;
});
editor_registra_asset('evento_iscrizione', ['css' => '../../addon/eventi-iscrizione/assets/eventi.css']);

editor_registra_widget('eventi_iscrizione', [
    'etichetta' => 'Eventi con iscrizione', 'icona' => function_exists('icona_svg') ? (icona_svg('calendario') ?: '*') : '*', 'gruppo' => 'Dal sito',
    'controlli' => [
        ec('quanti', 'numero', 'Quanti al massimo (0: tutti)', 0, 'contenuto', ['min' => 0, 'max' => 40]),
        ec('vuoto', 'testo', 'Testo senza eventi in programma', 'Al momento non ci sono eventi in programma. Torna a trovarci presto.'),
        ec('link_testo', 'testo', 'Testo del link', 'Iscriviti'),
        ec('dati_google', 'interruttore', 'Dati per Google (eventi)', true),
    ],
], function (array $v, array $ctx): string {
    $voci = [];
    foreach (evi_eventi(true, true) as $e) {
        $d = (new DateTimeImmutable('@' . (int)$e['inizio_ts']))->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $testo = trim((string)preg_replace('/\s+/', ' ', (string)$e['descrizione']));
        $voci[] = ['data' => $d->format('Y-m-d'), 'ora' => $d->format('H:i'), 'titolo' => (string)$e['titolo'], 'luogo' => trim($e['luogo'] . ($e['indirizzo'] !== '' ? ', ' . $e['indirizzo'] : ''), ', '),
            'testo' => mb_strlen($testo) > 180 ? mb_substr($testo, 0, 177) . '...' : $testo, 'prezzo' => trim(($e['prezzo'] !== '' ? $e['prezzo'] . ' · ' : '') . evi_posti_testo($e)),
            'immagine' => (string)$e['foto'], 'link' => evi_url_evento($e)];
    }
    // Il widget Eventi del nucleo disegna l'elenco (aspetto in widget.css, dati per Google con JSON_HEX_TAG).
    $nucleo = editor_widget_registrati()['eventi'] ?? null;
    if (!$nucleo) return '';
    return (string)($nucleo['disegna'])(['voci' => $voci, 'nascondi_passati' => true, 'quanti' => (int)$v['quanti'], 'vuoto' => (string)$v['vuoto'], 'link_testo' => (string)$v['link_testo'], 'dati_google' => (bool)$v['dati_google'],
        'data_sfondo' => '', 'data_colore' => '', 'titolo_colore' => '', 'testo_colore' => ''], $ctx);
});
editor_registra_asset('eventi_iscrizione', ['css' => ['widget.css', '../../addon/eventi-iscrizione/assets/eventi.css']]);
