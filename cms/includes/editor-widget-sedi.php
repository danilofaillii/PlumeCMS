<?php
/**
 * 2.27.0: i widget delle sedi (includes/sedi.php). Caricato in fondo a includes/editor-widget-extra.php, dopo Scorri di lato,
 * con lo stesso contratto delle parti A-E (ewx_registra(), classi ew-*). Aspetto in sito/assets/sedi.css, che si carica
 * solo nelle pagine che hanno uno dei due widget (dopo widget.css, che dà le classi degli orari e della mappa).
 * Niente JavaScript suo: "Aperto ora" si ricalcola con la funzione orari di sito/assets/widget.js (data-ew-orari), la mappa
 * al clic con la stessa di Mappa (data-ew-mappa).
 *
 *   sedi            le sedi in schede (tutte o un elenco scelto), griglia o elenco, con la mappa in ogni scheda a scelta
 *   dati_attivita   nome, indirizzo, telefono, email, orari brevi e i pulsanti di una sede (la principale di partenza):
 *                   per il footer e la pagina Contatti
 *
 * Niente variabili in cima al file: ogni file dei widget si carica dentro una funzione (editor_carica_widget_esterni).
 */

/** I controlli "cosa mostrare" comuni ai due widget. */
function ews_controlli_mostra(bool $conNome): array {
    $c = [];
    if ($conNome) $c[] = ec('mostra_nome', 'interruttore', 'Nome della sede', true, 'contenuto', ['sezione' => 'Cosa mostrare']);
    $c[] = ec('mostra_tipo', 'interruttore', 'Tipo di attività', true, 'contenuto', ['sezione' => 'Cosa mostrare']);
    $c[] = ec('mostra_foto', 'interruttore', 'Foto della sede', true, 'contenuto', ['sezione' => 'Cosa mostrare']);
    $c[] = ec('mostra_indirizzo', 'interruttore', 'Indirizzo', true, 'contenuto', ['sezione' => 'Cosa mostrare']);
    $c[] = ec('mostra_telefono', 'interruttore', 'Telefono (si può chiamare)', true, 'contenuto', ['sezione' => 'Cosa mostrare']);
    $c[] = ec('mostra_email', 'interruttore', 'Email', true, 'contenuto', ['sezione' => 'Cosa mostrare']);
    $c[] = ec('mostra_orari', 'interruttore', 'Orari in breve, con "Aperto ora" o "Chiuso"', true, 'contenuto', ['sezione' => 'Cosa mostrare']);
    $c[] = ec('mostra_arrivare', 'interruttore', 'Pulsante "Come arrivare"', true, 'contenuto', ['sezione' => 'Pulsanti']);
    $c[] = ec('testo_arrivare', 'testo', 'Testo del pulsante', 'Come arrivare', 'contenuto', ['sezione' => 'Pulsanti', 'condizione' => ['mostra_arrivare' => true]]);
    $c[] = ec('mostra_recensione', 'interruttore', 'Pulsante "Lascia una recensione" (solo se la sede ha il link)', true, 'contenuto', ['sezione' => 'Pulsanti']);
    $c[] = ec('testo_recensione', 'testo', 'Testo del pulsante', 'Lascia una recensione su Google', 'contenuto', ['sezione' => 'Pulsanti', 'condizione' => ['mostra_recensione' => true]]);
    return $c;
}

/** I controlli di stile comuni. */
function ews_controlli_stile(string $radice): array {
    return [
        ...ewx_testo_stile('nome', 'Nome della sede', $radice . ' .ew-sede-nome', '--ew-tc'),
        ...ewx_testo_stile('testo', 'Indirizzo e contatti', $radice . ' .ew-sede-corpo', '--ew-dc'),
        ewx_col('aperto_colore', 'Colore di "Aperto"', '--ew-ok', 'Stato'),
        ewx_col('chiuso_colore', 'Colore di "Chiuso"', '--ew-no', 'Stato'),
        ...ewx_riquadro_stile($radice . ' .ew-sede', 'Scheda'),
    ];
}

/** La mappa di una sede: OpenStreetMap con il segnaposto dalle coordinate, altrimenti la mappa dall'indirizzo; al clic o subito. */
function ews_mappa(array $s, array $v, array $ctx): string {
    $z = max(1, min(20, (int)($v['zoom'] ?? 15)));
    if (sedi_ha_coordinate($s)) {
        $lat = (float)$s['lat']; $lon = (float)$s['lng'];
        $d = 180 / (2 ** $z) * 1.4;
        $src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . ($lon - $d) . ',' . ($lat - $d / 2) . ',' . ($lon + $d) . ',' . ($lat + $d / 2) . '&layer=mapnik&marker=' . $lat . ',' . $lon;
        $nome = 'OpenStreetMap';
    } else {
        $ind = sedi_indirizzo_testo($s, true);
        if ($ind === '') return '';
        $src = 'https://www.google.com/maps?q=' . rawurlencode($ind) . '&z=' . $z . '&hl=it&output=embed';
        $nome = 'Google Maps';
    }
    $titolo = 'Mappa: ' . sedi_indirizzo_breve($s);
    $alClic = !empty($v['mappa_al_clic']);
    $out = '<div class="ew-mappa ew-sede-mappa"' . ($alClic ? ' data-src="' . h($src) . '" data-titolo="' . h($titolo) . '"' : '') . '>';
    if ($alClic && empty($ctx['anteprima'])) $out .= '<button type="button" class="ew-mappa-avvia" data-ew-mappa>' . icona_svg('pin') . '<b>' . h((string)($v['testo_mappa'] ?? 'Mostra la mappa')) . '</b><small>La mappa arriva da ' . $nome . '</small></button>';
    elseif ($alClic) $out .= '<div class="ew-mappa-avvia ew-mappa-finta">' . icona_svg('pin') . '<b>' . h((string)($v['testo_mappa'] ?? 'Mostra la mappa')) . '</b><small>Sul sito la mappa si carica al clic, da ' . $nome . '</small></div>';
    else $out .= '<iframe src="' . h($src) . '" title="' . h($titolo) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
    return $out . '</div>';
}

/** La scheda di una sede: le parti scelte, nell'ordine nome, tipo, foto, indirizzo, contatti, orari, pulsanti, mappa. */
function ews_scheda(array $s, array $v, array $ctx, string $tag = 'h3', bool $conMappa = false): string {
    $mostra = fn(string $k) => !empty($v['mostra_' . $k]);
    $corpo = '';
    if ($mostra('nome')) $corpo .= '<' . $tag . ' class="ew-sede-nome">' . h($s['nome']) . '</' . $tag . '>';
    $tipo = sedi_tipo_nome($s['tipo'], $s['tipo_testo']);
    if ($mostra('tipo') && $tipo !== '') $corpo .= '<p class="ew-sede-tipo">' . h($tipo) . '</p>';
    $ind = sedi_indirizzo_testo($s);
    if ($mostra('indirizzo') && $ind !== '') $corpo .= '<address class="ew-sede-indirizzo">' . icona_svg('pin-mappa') . '<span>' . h($ind) . '</span></address>';
    $contatti = '';
    $tel = preg_replace('/[^\d+]/', '', $s['telefono']);
    if ($mostra('telefono') && $tel !== '') $contatti .= '<a class="ew-sede-tel" href="tel:' . h($tel) . '">' . icona_svg('telefono') . '<span>' . h($s['telefono']) . '</span></a>';
    if ($mostra('email') && $s['email'] !== '') $contatti .= '<a class="ew-sede-email" href="mailto:' . h($s['email']) . '">' . icona_svg('email') . '<span>' . h($s['email']) . '</span></a>';
    if ($contatti !== '') $corpo .= '<p class="ew-sede-contatti">' . $contatti . '</p>';
    if ($mostra('orari') && sedi_ha_orari($s)) {
        $aperto = trim((string)($v['testo_aperto'] ?? '')) ?: 'Aperto ora'; $chiuso = trim((string)($v['testo_chiuso'] ?? '')) ?: 'Chiuso ora';
        $ov = sedi_orari_valori($s, $aperto, $chiuso);
        [$stato, $frase] = ewx_orari_stato($ov, new DateTimeImmutable('now'));
        $dati = ['fuso' => date_default_timezone_get(), 'giorni' => array_map(fn($k) => ewx_orari_intervalli((string)($ov[$k] ?? '')), array_keys(EWX_GIORNI)),
            'chiusure' => array_values(array_map(fn($c) => [$c[0], $c[1], $c[2]], ewx_orari_chiusure($ov))), 'aperto' => $aperto, 'chiuso' => $chiuso];
        $corpo .= '<div class="ew-orari ew-sede-orari" data-ew-orari="' . h(json_encode($dati, JSON_UNESCAPED_UNICODE)) . '">'
            . '<p class="ew-orari-stato ' . ($stato ? 'ew-aperto' : 'ew-chiuso-ora') . '" aria-live="polite"><span class="ew-orari-punto" aria-hidden="true"></span><span class="ew-orari-frase">' . h($frase) . '</span></p>'
            . '<p class="ew-sede-oggi">' . icona_svg('orologio') . '<span>Oggi: ' . h(sedi_orari_oggi($s)) . '</span></p></div>';
    }
    $azioni = '';
    $nuova = '<span class="ew-nascosto-vista"> (si apre in una nuova scheda)</span>';
    if ($mostra('arrivare') && ($u = sedi_link_indicazioni($s)) !== '') $azioni .= '<a class="btn btn-viola ew-sede-arrivare" href="' . h($u) . '" target="_blank" rel="noopener">' . icona_svg('navigazione') . '<span>' . h(trim((string)($v['testo_arrivare'] ?? '')) ?: 'Come arrivare') . '</span>' . $nuova . '</a>';
    if ($mostra('recensione') && $s['google_recensione'] !== '') $azioni .= '<a class="btn btn-filo ew-sede-recensione" href="' . h($s['google_recensione']) . '" target="_blank" rel="noopener">' . icona_svg('stella') . '<span>' . h(trim((string)($v['testo_recensione'] ?? '')) ?: 'Lascia una recensione su Google') . '</span>' . $nuova . '</a>';
    if (!empty($v['mostra_pagina']) && ($u = sedi_url_pagina($s)) !== '') $azioni .= '<a class="btn btn-filo ew-sede-pagina" href="' . h($u) . '">' . icona_svg('freccia-destra') . '<span>' . h(trim((string)($v['testo_pagina'] ?? '')) ?: 'La sede') . '</span></a>';
    if ($azioni !== '') $corpo .= '<div class="ew-sede-azioni">' . $azioni . '</div>';
    $foto = $mostra('foto') && $s['foto'] !== '' ? '<figure class="ew-sede-foto"><img src="' . h($s['foto']) . '" alt="' . h($s['nome']) . '" loading="lazy" decoding="async"></figure>' : '';
    $mappa = $conMappa ? ews_mappa($s, $v, $ctx) : '';
    return '<article class="ew-sede' . ($foto !== '' ? ' ew-sede-con-foto' : '') . ($mappa !== '' ? ' ew-sede-con-mappa' : '') . '">' . $foto . '<div class="ew-sede-corpo">' . $corpo . '</div>' . $mappa . '</article>';
}

/** Le opzioni della tendina di una sede dentro l'elenco del widget Sedi (senza "nessuna"). */
function ews_opzioni_sede(): array {
    return sedi_opzioni_widget(false);
}

// ---------------- Sedi: le sedi in schede
ewx_registra('sedi', 'Sedi', 'pin-mappa', 'Dal sito', [
    ec('quali', 'scelta', 'Quali sedi', 'tutte', 'contenuto', ['opzioni' => ['tutte' => 'Tutte le sedi', 'scelte' => 'Solo quelle che scelgo'], 'sezione' => 'Sedi',
        'aiuto' => 'Le sedi si scrivono in SEO e AEO, Sedi. Con "Tutte" il widget segue da solo le sedi aggiunte dopo.']),
    ec('scelte', 'ripetitore', 'Le sedi da mostrare', [], 'contenuto', ['campi' => [
        ec('sede', 'scelta', 'Sede', 'principale', 'contenuto', ['opzioni' => ews_opzioni_sede(), 'accetta' => '/^[a-z0-9][a-z0-9-]{0,59}$/']),
    ], 'titolo_voce' => 'sede', 'max_voci' => SEDI_MAX, 'condizione' => ['quali' => 'scelte'], 'sezione' => 'Sedi']),
    ec('disposizione', 'scelta', 'Disposizione', 'griglia', 'contenuto', ['opzioni' => ['griglia' => 'Griglia (schede affiancate)', 'elenco' => 'Elenco (una sotto l\'altra)'], 'sezione' => 'Disposizione']),
    ec('colonne', 'numero', 'Colonne', ['d' => 2, 't' => 2, 'm' => 1], 'contenuto', ['min' => 1, 'max' => 3, 'responsive' => true, 'sezione' => 'Disposizione', 'condizione' => ['disposizione' => 'griglia'], 'aiuto' => 'Al telefono sempre una colonna.']),
    ec('livello', 'scelta', 'Livello del nome della sede', 'h3', 'contenuto', ['opzioni' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'], 'sezione' => 'Disposizione']),
    ...ews_controlli_mostra(true),
    ec('mostra_pagina', 'interruttore', 'Pulsante verso la pagina della sede (solo se c\'è)', true, 'contenuto', ['sezione' => 'Pulsanti']),
    ec('testo_pagina', 'testo', 'Testo del pulsante', 'La sede', 'contenuto', ['sezione' => 'Pulsanti', 'condizione' => ['mostra_pagina' => true]]),
    ec('mostra_mappa', 'interruttore', 'Una mappa in ogni scheda', false, 'contenuto', ['sezione' => 'Mappa', 'aiuto' => 'Con le coordinate la mappa arriva da OpenStreetMap, con il segnaposto e senza cookie; senza coordinate, dall\'indirizzo.']),
    ec('mappa_al_clic', 'interruttore', 'Carica la mappa solo al clic (niente contatti con il servizio prima)', true, 'contenuto', ['sezione' => 'Mappa', 'condizione' => ['mostra_mappa' => true]]),
    ec('testo_mappa', 'testo', 'Testo del pulsante', 'Mostra la mappa', 'contenuto', ['sezione' => 'Mappa', 'condizione' => ['mostra_mappa' => true, 'mappa_al_clic' => true]]),
    ec('zoom', 'numero', 'Zoom', 15, 'contenuto', ['min' => 1, 'max' => 20, 'sezione' => 'Mappa', 'condizione' => ['mostra_mappa' => true]]),
    ewx_var('mappa_altezza', 'Altezza della mappa', '--ew-mh', 'Mappa', ['d' => ['v' => 220, 'u' => 'px']], ['intervalli' => ['px' => [120, 800, 1]], 'condizione' => ['mostra_mappa' => true]]),
    ec('testo_aperto', 'testo', 'Testo quando è aperto', 'Aperto ora', 'contenuto', ['sezione' => 'Orari', 'condizione' => ['mostra_orari' => true]]),
    ec('testo_chiuso', 'testo', 'Testo quando è chiuso', 'Chiuso ora', 'contenuto', ['sezione' => 'Orari', 'condizione' => ['mostra_orari' => true]]),
    ...ews_controlli_stile(' .ew-sedi'),
], function (array $v, array $ctx): string {
    $tutte = sedi_tutte();
    if (!$tutte) return !empty($ctx['anteprima']) ? ewx_vuoto('Nessuna sede ancora: aggiungile in SEO e AEO, Sedi') : '';
    $sedi = [];
    if (($v['quali'] ?? 'tutte') === 'scelte') {
        foreach ((array)($v['scelte'] ?? []) as $r) { $s = sedi_per_widget($r['sede'] ?? ''); if ($s && !isset($sedi[$s['id']])) $sedi[$s['id']] = $s; }
        if (!$sedi) return !empty($ctx['anteprima']) ? ewx_vuoto('Scegli almeno una sede nell\'elenco, oppure "Tutte le sedi"') : '';
    } else {
        $sedi = $tutte;
    }
    $disp = ($v['disposizione'] ?? 'griglia') === 'elenco' ? 'elenco' : 'griglia';
    if ($disp === 'griglia') ewx_css_colonne($ctx, ' .ew-sedi-griglia', $v['colonne'] ?? 2, 3);
    $tag = in_array($v['livello'] ?? '', ['h2', 'h3', 'h4'], true) ? $v['livello'] : 'h3';
    $out = '';
    foreach ($sedi as $s) $out .= ews_scheda($s, $v, $ctx, $tag, !empty($v['mostra_mappa']));
    return '<div class="ew-sedi ew-sedi-' . $disp . '">' . $out . '</div>';
});
editor_registra_asset('sedi', ['css' => ['widget.css', 'sedi.css'], 'js' => 'widget.js']);

// ---------------- Dati dell'attività: una sede sola, per il footer e la pagina Contatti
ewx_registra('dati_attivita', 'Dati dell\'attività (sede)', 'edificio', 'Dal sito', [
    ec('sede', 'scelta', 'Quale sede', 'principale', 'contenuto', ['opzioni' => sedi_opzioni_widget(false), 'accetta' => '/^[a-z0-9][a-z0-9-]{0,59}$/', 'sezione' => 'Sede',
        'aiuto' => 'Le sedi si scrivono in SEO e AEO, Sedi. "La sede principale" segue quella segnata come principale.']),
    ec('titolo', 'testo', 'Titolo sopra i dati', '', 'contenuto', ['sezione' => 'Sede', 'aiuto' => 'Per esempio "Dove siamo". Vuoto: niente titolo.']),
    ec('disposizione', 'scelta', 'Disposizione', 'verticale', 'contenuto', ['opzioni' => ['verticale' => 'In colonna', 'orizzontale' => 'In riga (per il footer)'], 'sezione' => 'Sede']),
    ...ews_controlli_mostra(true),
    ec('testo_aperto', 'testo', 'Testo quando è aperto', 'Aperto ora', 'contenuto', ['sezione' => 'Orari', 'condizione' => ['mostra_orari' => true]]),
    ec('testo_chiuso', 'testo', 'Testo quando è chiuso', 'Chiuso ora', 'contenuto', ['sezione' => 'Orari', 'condizione' => ['mostra_orari' => true]]),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-dati-titolo', '--ew-ttc'),
    ...ews_controlli_stile(' .ew-dati-attivita'),
], function (array $v, array $ctx): string {
    $s = sedi_per_widget($v['sede'] ?? 'principale') ?? sedi_principale();
    if (!$s) return !empty($ctx['anteprima']) ? ewx_vuoto('Nessuna sede ancora: aggiungila in SEO e AEO, Sedi') : '';
    $titolo = trim((string)($v['titolo'] ?? ''));
    $disp = ($v['disposizione'] ?? 'verticale') === 'orizzontale' ? 'orizzontale' : 'verticale';
    return '<div class="ew-dati-attivita ew-dati-' . $disp . '">' . ($titolo !== '' ? '<h3 class="ew-dati-titolo">' . h($titolo) . '</h3>' : '') . ews_scheda($s, $v, $ctx, $titolo !== '' ? 'h4' : 'h3') . '</div>';
});
editor_registra_asset('dati_attivita', ['css' => ['widget.css', 'sedi.css'], 'js' => 'widget.js']);
