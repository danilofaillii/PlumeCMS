<?php
/**
 * Widget Download (1.2.4, 27 ottobre 2026): i file del download manager (includes/scaricabili.php, pannello Download)
 * in una pagina. Ogni file con icona del tipo, titolo, descrizione, tipo, peso, versione, data, quante volte e stato
 * scaricato e il pulsante. Si scelgono i file uno per uno (con titolo e descrizione propri, se servono) oppure si
 * mostrano gli ultimi caricati. Disposizione a elenco o a schede. CSS in sito/assets/download.css.
 */
require_once __DIR__ . '/scaricabili.php';

/** I file per la tendina del widget: nome => "Titolo (tipo, peso)". */
function editor_download_opzioni(): array {
    $o = ['' => 'Scegli un file'];
    try {
        foreach (scaricabili_elenco() as $x) $o[$x['nome']] = $x['titolo'] . ' (' . $x['tipo'] . ', ' . scaricabili_peso($x['peso']) . ')';
    } catch (Throwable $e) { /* senza database o senza cartella: solo la voce vuota */ }
    return $o;
}

editor_registra_widget('download', ['etichetta' => 'Download', 'icona' => '&#11015;', 'gruppo' => 'Dal sito', 'controlli' => [
    ec('origine', 'scelta', 'Quali file', 'scelti', 'contenuto', ['sezione' => 'File', 'opzioni' => ['scelti' => 'Quelli che scelgo qui sotto', 'ultimi' => 'Gli ultimi caricati'],
        'aiuto' => 'I file si caricano dal pannello, voce Download.']),
    ec('voci', 'ripetitore', 'File', [['file' => '', 'titolo' => '', 'descrizione' => '']], 'contenuto', ['sezione' => 'File', 'condizione' => ['origine' => 'scelti'],
        // 'accetta': un nome di file valido resta anche se il file non c'e ancora (un pacchetto porta la pagina prima dei file).
        'campi' => [ec('file', 'scelta', 'File', '', 'contenuto', ['opzioni' => editor_download_opzioni(), 'accetta' => '/^[a-z0-9][a-z0-9._-]{0,120}$/']),
                    ec('titolo', 'testo', 'Titolo (vuoto = quello del pannello)', ''),
                    ec('descrizione', 'testolungo', 'Descrizione (vuota = quella del pannello)', '')], 'titolo_voce' => 'titolo']),
    ec('quanti', 'numero', 'Quanti file', 6, 'contenuto', ['sezione' => 'File', 'min' => 1, 'max' => 50, 'condizione' => ['origine' => 'ultimi']]),
    ec('pulsante_testo', 'testo', 'Testo del pulsante', 'Scarica', 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('pulsante_stile', 'scelta', 'Stile del pulsante', 'viola', 'contenuto', ['sezione' => 'Cosa mostrare', 'opzioni' => EDITOR_STILI_PULSANTE]),
    ec('mostra_icona', 'interruttore', 'Icona del tipo di file', true, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('mostra_descrizione', 'interruttore', 'Descrizione', true, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('mostra_tipo', 'interruttore', 'Tipo e peso (PDF, 900 KB)', true, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('mostra_versione', 'interruttore', 'Versione', true, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('mostra_data', 'interruttore', 'Data del file', false, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('mostra_conteggio', 'interruttore', 'Quante volte è stato scaricato', false, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('testo_conteggio', 'testo', 'Testo del conteggio ({n} = il numero)', '{n} download', 'contenuto', ['sezione' => 'Cosa mostrare', 'condizione' => ['mostra_conteggio' => true]]),
    // Stile
    ec('disposizione', 'scelta', 'Disposizione', 'elenco', 'stile', ['sezione' => 'Disposizione', 'opzioni' => ['elenco' => 'Elenco (una riga per file)', 'schede' => 'Schede affiancate']]),
    ec('colonne', 'numero', 'Schede per riga', ['d' => 3, 't' => 2, 'm' => 1], 'stile', ['sezione' => 'Disposizione', 'min' => 1, 'max' => 4, 'responsive' => true,
        'selettore' => ' .ed-dl-schede', 'proprieta' => 'grid-template-columns', 'valore_css' => 'repeat({{v}},minmax(0,1fr))', 'condizione' => ['disposizione' => 'schede']]),
    es_mis('spazio', 'Spazio fra i file', ' .ed-dl-griglia', 'Disposizione', 'gap', [], ['px', 'rem'], ['intervalli' => ['px' => [0, 60, 1]]]),
    es_sfo('voce_sfondo', 'Sfondo del file', ' .ed-dl-voce', 'Riquadro'),
    es_bor('voce_bordo', 'Bordo', ' .ed-dl-voce', 'Riquadro'),
    es_omb('voce_ombra', 'Ombra', ' .ed-dl-voce', 'Riquadro'),
    es_pad('voce_padding', 'Spazio dentro', ' .ed-dl-voce', 'Riquadro'),
    es_col('icona_colore', "Colore dell'icona", ' .ed-dl-icona', 'Icona', 'color'),
    es_col('icona_sfondo', "Sfondo dell'icona", ' .ed-dl-icona', 'Icona', 'background-color'),
    ...es_testo('titolo', 'del titolo', ' .ed-dl-titolo', 'Titolo'),
    ...es_testo('descrizione', 'della descrizione', ' .ed-dl-desc', 'Descrizione'),
    ...es_testo('meta', 'dei dettagli', ' .ed-dl-meta', 'Dettagli'),
]], function (array $v, array $ctx): string {
    try { $tutti = scaricabili_elenco(); } catch (Throwable $e) { $tutti = []; }
    $voci = [];
    if (($v['origine'] ?? 'scelti') === 'ultimi') {
        foreach (array_slice($tutti, 0, max(1, (int)$v['quanti'])) as $x) $voci[] = $x;
    } else {
        foreach ((array)$v['voci'] as $s) {
            $x = $tutti[(string)($s['file'] ?? '')] ?? null;
            if (!$x) {
                if ($ctx['anteprima'] && ($s['file'] ?? '') !== '') $voci[] = ['mancante' => (string)$s['file']];
                continue;
            }
            if (trim((string)($s['titolo'] ?? '')) !== '') $x['titolo'] = trim((string)$s['titolo']);
            if (trim((string)($s['descrizione'] ?? '')) !== '') $x['descrizione'] = trim((string)$s['descrizione']);
            $voci[] = $x;
        }
    }
    if (!$voci) return $ctx['anteprima'] ? '<div class="ed-vuoto">Scegli i file da mostrare (si caricano dal pannello, voce Download)</div>' : '';
    $stile = array_key_exists($v['pulsante_stile'] ?? '', EDITOR_STILI_PULSANTE) ? $v['pulsante_stile'] : 'viola';
    $html = '';
    foreach ($voci as $x) {
        if (isset($x['mancante'])) { $html .= '<div class="ed-dl-voce ed-dl-mancante">File non trovato: ' . h($x['mancante']) . '. Forse è stato eliminato dal pannello.</div>'; continue; }
        $meta = [];
        if ($v['mostra_tipo']) $meta[] = h($x['tipo']) . ' &middot; ' . h(scaricabili_peso($x['peso']));
        if ($v['mostra_versione'] && $x['versione'] !== '') $meta[] = 'versione ' . h($x['versione']);
        if ($v['mostra_data']) $meta[] = h(date('d/m/Y', $x['data']));
        if ($v['mostra_conteggio']) $meta[] = h(str_replace('{n}', number_format($x['conteggio'], 0, ',', '.'), (string)$v['testo_conteggio']));
        $html .= '<div class="ed-dl-voce">'
            . ($v['mostra_icona'] ? '<span class="ed-dl-icona" aria-hidden="true">' . editor_icona($x['icona']) . '<b>' . h($x['ext']) . '</b></span>' : '')
            . '<div class="ed-dl-testi"><h3 class="ed-dl-titolo">' . h($x['titolo']) . '</h3>'
            . ($v['mostra_descrizione'] && $x['descrizione'] !== '' ? '<p class="ed-dl-desc">' . nl2br(h($x['descrizione'])) . '</p>' : '')
            . ($meta ? '<p class="ed-dl-meta"><span>' . implode('</span><span>', $meta) . '</span></p>' : '') . '</div>'
            . '<a class="btn btn-' . $stile . ' ed-dl-btn" href="' . h($x['url']) . '" download>' . h((string)$v['pulsante_testo'] ?: 'Scarica') . '</a></div>';
    }
    $disp = ($v['disposizione'] ?? 'elenco') === 'schede' ? 'schede' : 'elenco';
    return '<div class="ed-dl"><div class="ed-dl-griglia ed-dl-' . $disp . '">' . $html . '</div></div>';
});

editor_registra_asset('download', ['css' => 'download.css']);
