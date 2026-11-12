<?php
/**
 * 2.28.0: il widget Altre zone (includes/zone.php). Caricato in fondo a includes/editor-widget-extra.php, dopo Sedi, con lo
 * stesso contratto delle parti A-E (ewx_registra(), classi ew-*). Aspetto in sito/assets/zone.css, che si carica solo nelle
 * pagine che hanno il widget. Niente JavaScript.
 *
 *   zone   le altre pagine per zona dello stesso servizio, pubblicate, come link in riga o in elenco, con un titolo a scelta
 *          ("Lavoriamo anche a..."). Fuori da una pagina per zona, o senza altre zone pubblicate, non mostra niente (nella
 *          tela dell'editor dice perché).
 *
 * Niente variabili in cima al file: ogni file dei widget si carica dentro una funzione (editor_carica_widget_esterni).
 */

require_once __DIR__ . '/zone.php';

// ---------------- Altre zone: le altre pagine per zona dello stesso servizio
ewx_registra('zone', 'Altre zone', 'mappa', 'Dal sito', [
    ec('titolo', 'testo', 'Titolo', 'Lavoriamo anche a...', 'contenuto', ['sezione' => 'Altre zone', 'aiuto' => 'Vuoto: niente titolo. Il widget elenca le altre pagine per zona dello stesso servizio (SEO e AEO, Zone), solo quelle pubblicate.']),
    ec('livello', 'scelta', 'Livello del titolo', 'h2', 'contenuto', ['opzioni' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'Testo semplice'], 'sezione' => 'Altre zone']),
    ec('disposizione', 'scelta', 'Disposizione', 'riga', 'contenuto', ['opzioni' => ['riga' => 'In riga (etichette una accanto all\'altra)', 'elenco' => 'In elenco (una sotto l\'altra)'], 'sezione' => 'Altre zone']),
    ec('con_servizio', 'interruttore', 'Nel link anche il servizio ("Idraulico a Bologna" invece di "Bologna")', false, 'contenuto', ['sezione' => 'Altre zone']),
    // 2.36.0: con centinaia di zone un elenco di tutte è troppo lungo: quante mostrarne (0 = tutte, come prima) e quali.
    ec('quante', 'numero', 'Quante mostrarne', 0, 'contenuto', ['min' => 0, 'max' => 200, 'passo' => 1, 'sezione' => 'Altre zone', 'aiuto' => '0 = tutte. Con più di 30 zone pubblicate meglio fra 12 e 20, con l\'ordine "Le più vicine": ogni pagina mostra zone diverse e tutte ricevono link.']),
    ec('ordine', 'scelta', 'Quali mostrare', 'alfabetico', 'contenuto', ['opzioni' => ['alfabetico' => 'Le prime in ordine alfabetico', 'vicine' => 'Le più vicine a questa zona nell\'elenco alfabetico'], 'sezione' => 'Altre zone', 'aiuto' => 'Conta solo se "Quante mostrarne" non è 0. L\'elenco esce sempre in ordine alfabetico.']),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-zone-titolo', '--ew-ttc'),
    ...ewx_testo_stile('link', 'Link', ' .ew-zone-link', '--ew-lc', true),
    ewx_col('link_sfondo', 'Sfondo delle etichette', '--ew-lb', 'Link', true),
    ...ewx_riquadro_stile(' .ew-zone', 'Riquadro'),
], function (array $v, array $ctx): string {
    $z = zone_di_percorso((string)($ctx['percorso'] ?? '/'));
    if (!$z) return !empty($ctx['anteprima']) ? ewx_vuoto('Altre zone: esce solo in una pagina per zona (SEO e AEO, Zone), con i link alle altre zone dello stesso servizio') : '';
    $quante = max(0, min(200, (int)($v['quante'] ?? 0)));
    $altre = zone_altre_pubblicate($z, $quante, ($v['ordine'] ?? '') === 'vicine' ? 'vicine' : 'alfabetico');
    if (!$altre) return !empty($ctx['anteprima']) ? ewx_vuoto('Altre zone: nessun\'altra pagina per zona pubblicata per "' . $z['servizio'] . '" (le bozze non si elencano)') : '';
    $disp = ($v['disposizione'] ?? 'riga') === 'elenco' ? 'elenco' : 'riga';
    $titolo = trim((string)($v['titolo'] ?? ''));
    $tag = in_array($v['livello'] ?? '', ['h2', 'h3', 'h4', 'p'], true) ? $v['livello'] : 'h2';
    $conServizio = !empty($v['con_servizio']);
    $out = '<nav class="ew-zone ew-zone-' . $disp . '" aria-label="' . h($titolo !== '' ? $titolo : 'Altre zone') . '">';
    if ($titolo !== '') $out .= '<' . $tag . ' class="ew-zone-titolo">' . h($titolo) . '</' . $tag . '>';
    $out .= '<ul class="ew-zone-elenco">';
    foreach ($altre as $a) $out .= '<li><a class="ew-zone-link" href="' . h($a['url']) . '">' . h($conServizio ? $a['titolo'] : $a['zona']) . '</a></li>';
    return $out . '</ul></nav>';
});
editor_registra_asset('zone', ['css' => 'zone.css']);
