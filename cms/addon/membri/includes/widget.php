<?php
/**
 * Widget "Accesso membri" (addon membri): un pulsante che chi è fuori vede come "Accedi" (e, se si vuole, "Iscriviti")
 * e chi è dentro come "Area riservata", con il suo nome. Si disegna a ogni richiesta, quindi segue chi guarda.
 */
require_once __DIR__ . '/membri.php';

editor_registra_widget('membri_accesso', ['etichetta' => 'Accesso membri', 'icona' => function_exists('icona_svg') ? (icona_svg('utente') ?: '&#128100;') : '&#128100;', 'gruppo' => 'Dal sito', 'controlli' => [
    ec('testo_accedi', 'testo', 'Testo per chi è fuori', 'Accedi', 'contenuto', ['sezione' => 'Pulsante']),
    ec('mostra_iscriviti', 'interruttore', 'Anche il pulsante "Iscriviti"', true, 'contenuto', ['sezione' => 'Pulsante', 'aiuto' => 'Non compare se le iscrizioni sono solo su invito.']),
    ec('testo_iscriviti', 'testo', 'Testo di "Iscriviti"', 'Iscriviti', 'contenuto', ['sezione' => 'Pulsante', 'condizione' => ['mostra_iscriviti' => true]]),
    ec('testo_area', 'testo', 'Testo per chi è dentro', 'Area riservata', 'contenuto', ['sezione' => 'Pulsante']),
    ec('mostra_nome', 'interruttore', 'Saluta per nome chi è dentro', true, 'contenuto', ['sezione' => 'Pulsante']),
    ec('stile', 'scelta', 'Stile del pulsante', 'viola', 'contenuto', ['sezione' => 'Pulsante', 'opzioni' => EDITOR_STILI_PULSANTE]),
    ec('allinea', 'scelta', 'Allineamento', 'inizio', 'contenuto', ['sezione' => 'Pulsante', 'opzioni' => ['inizio' => 'A sinistra', 'centro' => 'Al centro', 'fine' => 'A destra']]),
]], function (array $v, array $ctx): string {
    $stile = array_key_exists($v['stile'] ?? '', EDITOR_STILI_PULSANTE) ? $v['stile'] : 'viola';
    $allinea = ['inizio' => 'flex-start', 'centro' => 'center', 'fine' => 'flex-end'][$v['allinea'] ?? 'inizio'] ?? 'flex-start';
    $m = empty($ctx['anteprima']) ? membri_corrente() : null;
    $html = '<div class="membri-accesso" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:' . $allinea . '">';
    if ($m) {
        if (!empty($v['mostra_nome'])) $html .= '<span class="membri-accesso-ciao">Ciao, ' . h((string)$m['nome']) . '</span>';
        $html .= '<a class="btn btn-' . $stile . '" href="/area-riservata/">' . h((string)($v['testo_area'] ?: 'Area riservata')) . '</a>';
    } else {
        $html .= '<a class="btn btn-' . $stile . '" href="/accedi/">' . h((string)($v['testo_accedi'] ?: 'Accedi')) . '</a>';
        $invito = false;
        try { $invito = membri_cfg()['modo'] === 'invito'; } catch (Throwable $e) {}
        if (!empty($v['mostra_iscriviti']) && !$invito) $html .= '<a class="btn btn-filo" href="/iscriviti/">' . h((string)($v['testo_iscriviti'] ?: 'Iscriviti')) . '</a>';
    }
    return $html . '</div>';
});
