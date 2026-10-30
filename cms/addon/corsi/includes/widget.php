<?php
/**
 * Widget "Elenco corsi" (addon corsi): le schede dei corsi pubblicati, con l'avanzamento per il membro che guarda.
 * Si disegna a ogni richiesta, quindi segue chi guarda.
 */
require_once __DIR__ . '/pagine.php';

editor_registra_widget('corsi_elenco', ['etichetta' => 'Elenco corsi', 'icona' => function_exists('icona_svg') ? (icona_svg('laurea') ?: '&#127891;') : '&#127891;', 'gruppo' => 'Dal sito', 'controlli' => [
    ec('quali', 'scelta', 'Quali corsi', 'tutti', 'contenuto', ['sezione' => 'Corsi', 'opzioni' => ['tutti' => 'Tutti i corsi pubblicati', 'miei' => 'Solo quelli che chi guarda può seguire']]),
    ec('quanti', 'numero', 'Quanti al massimo', 6, 'contenuto', ['sezione' => 'Corsi', 'min' => 1, 'max' => 30]),
    ec('mostra_descrizione', 'interruttore', 'Descrizione', true, 'contenuto', ['sezione' => 'Corsi']),
    ec('pulsante', 'testo', 'Testo del pulsante (vuoto: Scopri, Inizia o Riprendi)', '', 'contenuto', ['sezione' => 'Corsi']),
    ec('vuoto', 'testo', 'Testo quando non c\'è nessun corso', 'Ancora nessun corso. Torna a trovarci.', 'contenuto', ['sezione' => 'Corsi']),
]], function (array $v, array $ctx): string {
    try {
        $m = empty($ctx['anteprima']) ? membri_corrente() : null;
        $corsi = corsi_tutti(true);
        if (($v['quali'] ?? 'tutti') === 'miei') $corsi = array_filter($corsi, fn($c) => corsi_puo($c, $m));
        $schede = '';
        foreach (array_slice(array_values($corsi), 0, max(1, min(30, (int)($v['quanti'] ?? 6)))) as $c) $schede .= corsi_scheda($c, $m, !empty($v['mostra_descrizione']), trim((string)($v['pulsante'] ?? '')));
    } catch (Throwable $e) {
        error_log('corsi: widget: ' . $e->getMessage());
        $schede = '';
    }
    if ($schede === '') return '<p class="corsi-vuoto">' . h((string)($v['vuoto'] ?? '')) . '</p>';
    return corsi_css() . '<div class="corsi-griglia corsi-widget">' . $schede . '</div>';
});
