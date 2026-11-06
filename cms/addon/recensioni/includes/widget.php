<?php
/**
 * Il widget "Recensioni dal sito" dell'addon Raccolta recensioni: le recensioni approvate nel pannello, con lo stesso
 * aspetto del widget Recensioni del nucleo (classi ew-rec-*, widget.css, le stelle di ewx_stelle_media()) e la risposta
 * dell'attività sotto. Come il widget del nucleo, niente dati per Google sulle stelle.
 */
require_once __DIR__ . '/recensioni.php';

editor_registra_widget('recensioni_sito', [
    'etichetta' => 'Recensioni dal sito', 'icona' => function_exists('icona_svg') ? (icona_svg('stella-piena') ?: '*') : '*', 'gruppo' => 'Marketing',
    'controlli' => [
        ec('quante', 'numero', 'Quante al massimo', 6, 'contenuto', ['min' => 1, 'max' => 60]),
        ec('minimo', 'scelta', 'Solo quelle da', '1', 'contenuto', ['opzioni' => ['1' => 'Tutte', '3' => '3 stelle in su', '4' => '4 stelle in su', '5' => '5 stelle']]),
        ec('mostra_riepilogo', 'interruttore', 'Mostra il voto medio', true),
        ec('mostra_risposte', 'interruttore', 'Mostra le risposte dell\'attività', true),
        ewx_colonne(3),
        ec('pulsante_testo', 'testo', 'Testo del pulsante', 'Lasciaci una recensione su Google'),
        ec('pulsante_google', 'interruttore', 'Pulsante per Google (il link delle impostazioni delle recensioni)', false),
        ec('vuoto', 'testo', 'Testo senza recensioni', ''),
    ],
], function (array $v, array $ctx): string {
    if (!empty($ctx['css'])) ewx_css_colonne($ctx, ' .ew-rec-griglia', $v['colonne'], 4);
    [$voci, $media, $quante] = rec_approvate((int)$v['quante'], (int)$v['minimo']);
    $out = '<div class="ew-rec rec-sito">';
    if ($v['mostra_riepilogo'] && $quante > 0) {
        $out .= '<div class="ew-rec-riepilogo"><b class="ew-rec-media">' . h(str_replace('.', ',', (string)round($media, 1))) . '</b><div>' . ewx_stelle_media($media)
            . '<span class="ew-rec-totale">' . $quante . ($quante === 1 ? ' recensione' : ' recensioni') . ' dei nostri clienti</span></div></div>';
    }
    $sito = (string)cms_impostazione('sito_nome', CMS_NOME);
    $mesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    if ($voci) {
        $out .= '<div class="ew-rec-griglia">';
        foreach ($voci as $r) {
            $n = max(1, min(5, (int)$r['stelle']));
            $t = strtotime((string)$r['creato']);
            $out .= '<figure class="ew-rec-voce"><span class="ew-rec-stelle-voce" role="img" aria-label="' . $n . ' su 5">' . str_repeat(icona_svg('stella-piena'), $n) . '</span>'
                . '<blockquote class="ew-rec-testo">' . editor_paragrafi((string)$r['testo']) . '</blockquote><figcaption><b class="ew-rec-nome">' . h((string)$r['nome']) . '</b>'
                . '<span class="ew-rec-quando">' . h($mesi[(int)date('n', $t) - 1] . ' ' . date('Y', $t)) . '</span></figcaption>'
                . ($v['mostra_risposte'] && trim((string)$r['risposta']) !== '' ? '<div class="rec-risposta"><b>Risposta di ' . h($sito) . '</b>' . editor_paragrafi((string)$r['risposta']) . '</div>' : '')
                . '</figure>';
        }
        $out .= '</div>';
    } elseif (trim((string)$v['vuoto']) !== '') {
        $out .= '<p class="rec-vuoto">' . h((string)$v['vuoto']) . '</p>';
    } elseif (!empty($ctx['anteprima'])) {
        $out .= '<div class="ed-vuoto">Ancora nessuna recensione approvata: le approvi nel pannello, in Recensioni.</div>';
    }
    $google = trim((string)rec_impostazioni()['google_link']);
    if ($v['pulsante_google'] && preg_match('~^https://~', $google) && trim((string)$v['pulsante_testo']) !== '') {
        $out .= '<p class="ew-rec-azione"><a class="btn btn-viola" href="' . h($google) . '" target="_blank" rel="noopener">' . icona_svg('stella-piena') . ' ' . h((string)$v['pulsante_testo']) . '</a></p>';
    }
    return $out === '<div class="ew-rec rec-sito">' ? '' : $out . '</div>';
});
editor_registra_asset('recensioni_sito', ['css' => ['widget.css', '../../addon/recensioni/assets/recensioni.css']]);
