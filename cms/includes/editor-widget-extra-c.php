<?php
/** Widget extra, parte C: avviso, ancora, citazione, invito libero, conto alla rovescia, mappa, flip box, titolo animato, listino, tabella prezzi, sommario, griglia di contenuti, pulsante fisso. */

// ---------------- Avviso (Alert)
ewx_registra('avviso', 'Avviso', 'avviso', 'Elementi', [
    ec('tipo', 'scelta', 'Tipo', 'info', 'contenuto', ['opzioni' => ['info' => 'Informazione', 'successo' => 'Successo', 'attenzione' => 'Attenzione', 'pericolo' => 'Pericolo'], 'sezione' => 'Avviso']),
    ec('titolo', 'testo', 'Titolo', 'Questo e un avviso', 'contenuto', ['sezione' => 'Avviso']),
    ec('testo', 'testolungo', 'Testo', 'Una riga di spiegazione, breve.', 'contenuto', ['sezione' => 'Avviso']),
    ec('mostra_icona', 'interruttore', 'Mostra l\'icona', true, 'contenuto', ['sezione' => 'Avviso']),
    ex('icona', 'icona', 'Icona (vuota: quella del tipo)', '', 'contenuto', ['condizione' => ['mostra_icona' => true], 'sezione' => 'Avviso']),
    ec('chiudibile', 'interruttore', 'Si può chiudere', true, 'contenuto', ['sezione' => 'Avviso']),
    ewx_col('sfondo', 'Sfondo', '--ew-bg', 'Avviso'),
    ewx_col('bordo_colore', 'Colore del bordo', '--ew-bc', 'Avviso'),
    ewx_var('bordo_sinistro', 'Spessore del bordo a sinistra', '--ew-bw', 'Avviso', ['d' => ['v' => 4, 'u' => 'px']]),
    ewx_var('raggio', 'Angoli arrotondati', '--ew-r', 'Avviso', ['d' => ['v' => 12, 'u' => 'px']]),
    ex('padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-avviso', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Avviso']),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-avviso-titolo', '--ew-tc'),
    ...ewx_testo_stile('testo', 'Testo', ' .ew-avviso-testo', '--ew-dc'),
    ewx_col('icona_colore', 'Colore', '--ew-c1', 'Icona e chiusura'),
    ewx_var('icona_dimensione', 'Dimensione', '--ew-dim', 'Icona e chiusura', ['d' => ['v' => 22, 'u' => 'px']]),
], function (array $v, array $ctx): string {
    $tipo = in_array($v['tipo'], ['successo', 'attenzione', 'pericolo'], true) ? $v['tipo'] : 'info';
    $pred = ['info' => 'info', 'successo' => 'spunta-cerchio', 'attenzione' => 'avviso', 'pericolo' => 'croce-cerchio'][$tipo];
    $ic = $v['mostra_icona'] ? '<span class="ew-avviso-icona">' . (ewx_ic((string)$v['icona']) ?: icona_svg($pred)) . '</span>' : '';
    return '<div class="ew-avviso ew-avviso-' . $tipo . '" role="alert">' . $ic . '<div class="ew-avviso-corpo">'
        . (trim($v['titolo']) !== '' ? '<b class="ew-avviso-titolo">' . h($v['titolo']) . '</b>' : '') . (trim($v['testo']) !== '' ? '<div class="ew-avviso-testo">' . editor_paragrafi($v['testo']) . '</div>' : '') . '</div>'
        . ($v['chiudibile'] ? '<button type="button" class="ew-avviso-chiudi" data-ew-chiudi aria-label="Chiudi l\'avviso">' . icona_svg('croce') . '</button>' : '') . '</div>';
});

// ---------------- Ancora di menu (Menu Anchor)
ewx_registra('ancora_menu', 'Ancora di menu', 'pin', 'Elementi', [
    ec('nome', 'testo', 'Nome dell\'ancora (senza #)', 'sezione', 'contenuto', ['aiuto' => 'Poi nei link scrivi #nome. Solo lettere minuscole, numeri e trattini.']),
], function (array $v, array $ctx): string {
    $id = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string)$v['nome'])));
    return '<span class="ew-ancora" id="' . h($id ?: 'ancora-' . $ctx['id']) . '"' . ($ctx['anteprima'] ? ' data-nome="#' . h($id) . '"' : '') . '></span>';
});

// ---------------- Citazione (Blockquote)
ewx_registra('citazione', 'Citazione', 'citazione-piena', 'Elementi', [
    ec('aspetto', 'scelta', 'Aspetto', 'bordo', 'contenuto', ['opzioni' => ['bordo' => 'Bordo a sinistra', 'virgolette' => 'Con le virgolette', 'riquadro' => 'Riquadro', 'pulito' => 'Pulito'], 'sezione' => 'Citazione']),
    ec('testo', 'testolungo', 'Testo', 'Le cose semplici, fatte bene, sono quelle che restano.', 'contenuto', ['sezione' => 'Citazione']),
    ec('autore', 'testo', 'Autore', (string)cms_impostazione('citazione_autore', cms_impostazione('autore_predefinito', '')), 'contenuto', ['sezione' => 'Citazione']),
    ewx_allinea('allineamento', 'Citazione', ''),
    ec('condividi_su', 'scelta', 'Pulsante di condivisione', '', 'contenuto', ['opzioni' => ['' => 'Nessuno', 'x-social' => 'X', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'copia' => 'Copia il testo'], 'sezione' => 'Condivisione']),
    ec('condividi_vista', 'scelta', 'Vista del pulsante', 'icona_testo', 'contenuto', ['opzioni' => ['icona_testo' => 'Icona e testo', 'icona' => 'Solo icona', 'testo' => 'Solo testo'], 'condizione' => ['!condividi_su' => ''], 'sezione' => 'Condivisione']),
    ec('condividi_etichetta', 'testo', 'Etichetta', 'Condividi', 'contenuto', ['condizione' => ['!condividi_su' => ''], 'sezione' => 'Condivisione']),
    ...ewx_testo_stile('testo', 'Testo', ' .ew-cit-testo', '--ew-dc'),
    ewx_var('testo_spazio', 'Spazio sotto il testo', '--ew-tgap', 'Testo', ['d' => ['v' => 12, 'u' => 'px']]),
    ...ewx_testo_stile('autore', 'Autore', ' .ew-cit-autore', '--ew-ac'),
    ewx_col('bordo_colore', 'Colore del bordo o delle virgolette', '--ew-bc', 'Bordo e virgolette'),
    ewx_var('bordo_spessore', 'Spessore del bordo', '--ew-bw', 'Bordo e virgolette', ['d' => ['v' => 3, 'u' => 'px']]),
    ewx_var('virgolette_dimensione', 'Dimensione delle virgolette', '--ew-qdim', 'Bordo e virgolette', ['d' => ['v' => 42, 'u' => 'px']]),
    ...ewx_riquadro_stile(' .ew-cit'),
    ewx_col('pulsante_colore', 'Colore', '--ew-pc', 'Pulsante', true),
    ewx_col('pulsante_sfondo', 'Sfondo', '--ew-pbg', 'Pulsante', true),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $asp = in_array($v['aspetto'], ['virgolette', 'riquadro', 'pulito'], true) ? $v['aspetto'] : 'bordo';
    $out = '<blockquote class="ew-cit ew-cit-' . $asp . '">' . ($asp === 'virgolette' ? '<span class="ew-cit-quote" aria-hidden="true">' . icona_svg('citazione-piena') . '</span>' : '')
        . '<div class="ew-cit-testo">' . editor_paragrafi($v['testo']) . '</div>';
    $piede = '';
    if (trim($v['autore']) !== '') $piede .= '<cite class="ew-cit-autore">' . h($v['autore']) . '</cite>';
    if ($v['condividi_su'] !== '') {
        $t = rawurlencode(trim($v['testo']) . (trim($v['autore']) !== '' ? ' (' . trim($v['autore']) . ')' : ''));
        $u = rawurlencode(ewx_url_pagina($ctx));
        $href = ['x-social' => 'https://twitter.com/intent/tweet?text=' . $t . '&url=' . $u, 'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u, 'whatsapp' => 'https://api.whatsapp.com/send?text=' . $t . '%20' . $u][$v['condividi_su']] ?? '';
        $corpo = icona_svg($v['condividi_su'] === 'copia' ? 'copia' : $v['condividi_su']) . '<span>' . h($v['condividi_etichetta']) . '</span>';
        $cl = 'ew-cit-condividi ew-vista-' . h($v['condividi_vista']);
        $piede .= $href !== '' ? '<a class="' . $cl . '" href="' . h($href) . '" target="_blank" rel="noopener nofollow" aria-label="' . h($v['condividi_etichetta']) . '">' . $corpo . '</a>'
            : '<button type="button" class="' . $cl . '" data-ew-copia-testo="' . h(trim($v['testo'])) . '" aria-label="' . h($v['condividi_etichetta']) . '">' . $corpo . '</button>';
    }
    if ($piede !== '') $out .= '<footer class="ew-cit-piede">' . $piede . '</footer>';
    return $out . '</blockquote>';
});

// ---------------- Invito all'azione libero (Call to Action)
ewx_registra('invito_libero', 'Invito all\'azione', 'megafono', 'Marketing', [
    ec('aspetto', 'scelta', 'Aspetto', 'classico', 'contenuto', ['opzioni' => ['classico' => 'Classico (immagine sopra o di lato)', 'copertina' => 'Copertina (immagine di sfondo)'], 'sezione' => 'Immagine']),
    ec('immagine', 'immagine', 'Immagine', '', 'contenuto', ['sezione' => 'Immagine']),
    ec('immagine_posizione', 'scelta', 'Posizione dell\'immagine', 'sopra', 'contenuto', ['opzioni' => ['sopra' => 'Sopra', 'sinistra' => 'A sinistra', 'destra' => 'A destra'], 'condizione' => ['aspetto' => 'classico'], 'sezione' => 'Immagine']),
    ex('icona', 'icona', 'Icona (facoltativa, sopra il titolo)', '', 'contenuto', ['sezione' => 'Contenuto']),
    ec('titolo', 'testo', 'Titolo', 'Parliamone', 'contenuto', ['sezione' => 'Contenuto']),
    ewx_livello('h3'),
    ec('testo', 'testolungo', 'Testo', 'Raccontaci cosa ti serve: ti rispondiamo con una proposta concreta.', 'contenuto', ['sezione' => 'Contenuto']),
    ec('pulsante_testo', 'testo', 'Testo del pulsante', 'Scrivici', 'contenuto', ['sezione' => 'Pulsante']),
    ewx_link('link', 'Link'),
    ec('link_su', 'scelta', 'Il link vale su', 'pulsante', 'contenuto', ['opzioni' => ['pulsante' => 'Solo il pulsante', 'tutto' => 'Tutto il riquadro'], 'sezione' => 'Pulsante']),
    ec('nastro', 'testo', 'Nastro (facoltativo)', '', 'contenuto', ['sezione' => 'Nastro']),
    ec('nastro_posizione', 'scelta', 'Posizione del nastro', 'destra', 'contenuto', ['opzioni' => ['destra' => 'In alto a destra', 'sinistra' => 'In alto a sinistra'], 'condizione' => ['!nastro' => ''], 'sezione' => 'Nastro']),
    ewx_allinea('allineamento', 'Disposizione', 'center'),
    ec('verticale', 'scelta', 'Contenuto allineato', 'center', 'contenuto', ['opzioni' => ['flex-start' => 'In alto', 'center' => 'Al centro', 'flex-end' => 'In basso'], 'selettore' => '', 'proprieta' => '--ew-valign', 'sezione' => 'Disposizione']),
    ewx_var('altezza_minima', 'Altezza minima', '--ew-minh', 'Disposizione', '', ['unita' => ['px', 'vh'], 'intervalli' => ['px' => [0, 1000, 1], 'vh' => [0, 100, 1]]]),
    ec('effetto_hover', 'scelta', 'Effetto al passaggio sull\'immagine', 'zoom', 'stile', ['opzioni' => ['' => 'Nessuno', 'zoom' => 'Ingrandisce', 'opacita' => 'Si schiarisce', 'grigio' => 'Da grigio a colori'], 'sezione' => 'Immagine']),
    ewx_col('velo', 'Velo sull\'immagine', '--ew-velo', 'Immagine'),
    ewx_col('velo_hover', 'Velo al passaggio', '--ew-veloh', 'Immagine'),
    ewx_var('immagine_larghezza', 'Larghezza dell\'immagine (di lato)', '--ew-imgw', 'Immagine', ['d' => ['v' => 40, 'u' => '%']], ['unita' => ['%', 'px'], 'intervalli' => ['%' => [10, 90, 1], 'px' => [40, 800, 1]]]),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-cta-titolo', '--ew-tc'),
    ewx_var('titolo_spazio', 'Spazio sotto il titolo', '--ew-tgap', 'Titolo', ['d' => ['v' => 10, 'u' => 'px']]),
    ...ewx_testo_stile('testo', 'Testo', ' .ew-cta-testo', '--ew-dc'),
    ewx_var('testo_spazio', 'Spazio sotto il testo', '--ew-dgap', 'Testo', ['d' => ['v' => 22, 'u' => 'px']]),
    ewx_col('icona_colore', 'Colore', '--ew-c1', 'Icona'),
    ewx_var('icona_dimensione', 'Dimensione', '--ew-dim', 'Icona', ['d' => ['v' => 40, 'u' => 'px']]),
    ewx_tip('pulsante_tipografia', 'Tipografia', ' .ew-cta-pulsante', 'Pulsante'),
    ewx_col('pulsante_colore', 'Colore del testo', '--ew-pc', 'Pulsante', true),
    ewx_col('pulsante_sfondo', 'Sfondo', '--ew-pbg', 'Pulsante', true),
    ex('pulsante_bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' .ew-cta-pulsante', 'sezione' => 'Pulsante', 'stati' => true]),
    ex('pulsante_padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-cta-pulsante', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Pulsante']),
    ewx_col('nastro_colore', 'Colore', '--ew-nc', 'Nastro'),
    ewx_col('nastro_sfondo', 'Sfondo', '--ew-nbg', 'Nastro'),
    ...ewx_riquadro_stile(' .ew-cta'),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $tag = ewx_tag($v);
    $asp = $v['aspetto'] === 'copertina' ? 'copertina' : 'classico';
    $pos = in_array($v['immagine_posizione'], ['sinistra', 'destra'], true) ? $v['immagine_posizione'] : 'sopra';
    $url = editor_link_sicuro((string)($v['link']['url'] ?? ''));
    $nuova = !empty($v['link']['nuova']) ? ' target="_blank" rel="noopener"' : '';
    $tutto = $url !== '' && $v['link_su'] === 'tutto';
    $tagEst = $tutto ? 'a' : 'div';
    $out = "<$tagEst class=\"ew-cta ew-cta-" . $asp . ' ew-img-' . $pos . ($v['effetto_hover'] !== '' ? ' ew-cta-hov-' . h($v['effetto_hover']) : '') . '"' . ($tutto ? ' href="' . h($url) . '"' . $nuova : '') . '>';
    if ($v['immagine'] !== '') $out .= '<div class="ew-cta-sfondo" style="background-image:url(\'' . h(str_replace(["'", ')'], '', $v['immagine'])) . '\')" role="img" aria-label="' . h($v['titolo']) . '"><span class="ew-cta-velo"></span></div>';
    $out .= '<div class="ew-cta-contenuto">';
    $ic = ewx_ic((string)$v['icona']);
    if ($ic !== '') $out .= '<span class="ew-cta-icona">' . $ic . '</span>';
    if (trim($v['titolo']) !== '') $out .= "<$tag class=\"ew-cta-titolo\">" . h($v['titolo']) . "</$tag>";
    if (trim($v['testo']) !== '') $out .= '<div class="ew-cta-testo">' . editor_paragrafi($v['testo']) . '</div>';
    if (trim($v['pulsante_testo']) !== '') {
        $out .= $tutto || $url === '' ? '<span class="btn btn-viola ew-cta-pulsante">' . h($v['pulsante_testo']) . '</span>' : '<a class="btn btn-viola ew-cta-pulsante" href="' . h($url) . '"' . $nuova . '>' . h($v['pulsante_testo']) . '</a>';
    }
    $out .= '</div>';
    if (trim($v['nastro']) !== '') $out .= '<span class="ew-cta-nastro ew-nastro-' . ($v['nastro_posizione'] === 'sinistra' ? 'sinistra' : 'destra') . '"><span>' . h($v['nastro']) . '</span></span>';
    return $out . "</$tagEst>";
});

// ---------------- Conto alla rovescia (Countdown)
ewx_registra('conto_alla_rovescia', 'Conto alla rovescia', 'cronometro', 'Numeri e tempo', [
    ex('scadenza', 'data_ora', 'Scadenza', date('Y-m-d H:i', strtotime('+30 days')), 'contenuto', ['sezione' => 'Scadenza']),
    ec('mostra_giorni', 'interruttore', 'Giorni', true, 'contenuto', ['sezione' => 'Unita']),
    ec('mostra_ore', 'interruttore', 'Ore', true, 'contenuto', ['sezione' => 'Unita']),
    ec('mostra_minuti', 'interruttore', 'Minuti', true, 'contenuto', ['sezione' => 'Unita']),
    ec('mostra_secondi', 'interruttore', 'Secondi', true, 'contenuto', ['sezione' => 'Unita']),
    ec('mostra_etichette', 'interruttore', 'Mostra le etichette', true, 'contenuto', ['sezione' => 'Etichette']),
    ec('etichetta_giorni', 'testo', 'Giorni', 'Giorni', 'contenuto', ['sezione' => 'Etichette']),
    ec('etichetta_ore', 'testo', 'Ore', 'Ore', 'contenuto', ['sezione' => 'Etichette']),
    ec('etichetta_minuti', 'testo', 'Minuti', 'Minuti', 'contenuto', ['sezione' => 'Etichette']),
    ec('etichetta_secondi', 'testo', 'Secondi', 'Secondi', 'contenuto', ['sezione' => 'Etichette']),
    ec('alla_scadenza', 'scelta', 'Alla scadenza', 'niente', 'contenuto', ['opzioni' => ['niente' => 'Resta a zero', 'nascondi' => 'Sparisce', 'messaggio' => 'Mostra un messaggio', 'vai' => 'Va a un indirizzo'], 'sezione' => 'Alla scadenza']),
    ec('messaggio', 'testolungo', 'Messaggio', 'Il tempo è scaduto.', 'contenuto', ['condizione' => ['alla_scadenza' => 'messaggio'], 'sezione' => 'Alla scadenza']),
    ec('vai_a', 'testo', 'Indirizzo', '', 'contenuto', ['condizione' => ['alla_scadenza' => 'vai'], 'sezione' => 'Alla scadenza']),
    ewx_allinea('allineamento', 'Disposizione', 'center'),
    ewx_col('riquadro_sfondo_colore', 'Sfondo dei riquadri', '--ew-bg', 'Riquadri'),
    ex('riquadro_bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' .ew-conto-voce', 'sezione' => 'Riquadri']),
    ewx_var('riquadro_larghezza', 'Larghezza minima', '--ew-w', 'Riquadri', ['d' => ['v' => 90, 'u' => 'px']]),
    ewx_var('riquadro_spazio', 'Spazio fra i riquadri', '--ew-gap', 'Riquadri', ['d' => ['v' => 12, 'u' => 'px']]),
    ex('riquadro_padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-conto-voce', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Riquadri']),
    ...ewx_testo_stile('numero', 'Numeri', ' .ew-conto-num', '--ew-nc'),
    ...ewx_testo_stile('etichetta', 'Etichette', ' .ew-conto-eti', '--ew-ec'),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $s = trim(ewx_str($v['scadenza']));
    $ts = $s !== '' ? strtotime(str_replace('T', ' ', $s)) : false;
    if ($ts === false) return ewx_vuoto('Scrivi la data di scadenza (AAAA-MM-GG HH:MM)');
    $resto = max(0, $ts - time());
    $g = intdiv($resto, 86400); $o = intdiv($resto % 86400, 3600); $m = intdiv($resto % 3600, 60); $sec = $resto % 60;
    $unita = [['g', $v['mostra_giorni'], $g, $v['etichetta_giorni']], ['o', $v['mostra_ore'], $o, $v['etichetta_ore']], ['m', $v['mostra_minuti'], $m, $v['etichetta_minuti']], ['s', $v['mostra_secondi'], $sec, $v['etichetta_secondi']]];
    $out = '<div class="ew-conto" data-ew-conto data-fine="' . date('c', $ts) . '" data-azione="' . h($v['alla_scadenza']) . '"' . ($v['alla_scadenza'] === 'vai' ? ' data-vai="' . h(editor_link_sicuro($v['vai_a'])) . '"' : '') . ' role="timer" aria-live="off">';
    $out .= '<div class="ew-conto-riga">';
    foreach ($unita as [$k, $on, $n, $eti]) {
        if (!$on) continue;
        $out .= '<div class="ew-conto-voce"><b class="ew-conto-num" data-u="' . $k . '">' . ($k === 'g' ? $n : str_pad((string)$n, 2, '0', STR_PAD_LEFT)) . '</b>' . ($v['mostra_etichette'] ? '<span class="ew-conto-eti">' . h($eti) . '</span>' : '') . '</div>';
    }
    $out .= '</div>';
    if ($v['alla_scadenza'] === 'messaggio') $out .= '<div class="ew-conto-msg"' . ($resto > 0 ? ' hidden' : '') . '>' . editor_paragrafi($v['messaggio']) . '</div>';
    return $out . '</div>';
});

// ---------------- Mappa (Google Maps)
ewx_registra('mappa', 'Mappa', 'mappa', 'Elementi', [
    ec('fornitore', 'scelta', 'Servizio', 'google', 'contenuto', ['opzioni' => ['google' => 'Google Maps (indirizzo)', 'osm' => 'OpenStreetMap (coordinate)'], 'sezione' => 'Mappa']),
    ec('indirizzo', 'testo', 'Indirizzo o luogo', 'Firenze, Italia', 'contenuto', ['condizione' => ['fornitore' => 'google'], 'sezione' => 'Mappa']),
    ec('lat', 'testo', 'Latitudine', '43.7696', 'contenuto', ['condizione' => ['fornitore' => 'osm'], 'sezione' => 'Mappa']),
    ec('lon', 'testo', 'Longitudine', '11.2558', 'contenuto', ['condizione' => ['fornitore' => 'osm'], 'sezione' => 'Mappa']),
    ec('zoom', 'numero', 'Zoom', 14, 'contenuto', ['min' => 1, 'max' => 20, 'sezione' => 'Mappa']),
    ec('tipo', 'scelta', 'Tipo di mappa', 'strade', 'contenuto', ['opzioni' => ['strade' => 'Strade', 'satellite' => 'Satellite'], 'condizione' => ['fornitore' => 'google'], 'sezione' => 'Mappa']),
    ec('al_clic', 'interruttore', 'Carica la mappa solo al clic (niente contatti con il servizio prima)', true, 'contenuto', ['sezione' => 'Privacy']),
    ec('testo_pulsante', 'testo', 'Testo del pulsante', 'Mostra la mappa', 'contenuto', ['condizione' => ['al_clic' => true], 'sezione' => 'Privacy']),
    ewx_var('altezza', 'Altezza', '--ew-h', 'Mappa', ['d' => ['v' => 360, 'u' => 'px']], ['intervalli' => ['px' => [120, 1200, 1]]]),
    ex('bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' .ew-mappa', 'sezione' => 'Mappa']),
    ec('grigio', 'interruttore', 'In scala di grigi', false, 'stile', ['sezione' => 'Mappa']),
    ec('opacita_hover', 'numero', 'Opacita (%)', '', 'stile', ['min' => 10, 'max' => 100, 'unita' => '%', 'selettore' => '', 'proprieta' => '--ew-op', 'sezione' => 'Mappa']),
], function (array $v, array $ctx): string {
    $z = max(1, min(20, (int)$v['zoom']));
    if ($v['fornitore'] === 'osm') {
        $lat = (float)str_replace(',', '.', $v['lat']); $lon = (float)str_replace(',', '.', $v['lon']);
        $d = 180 / (2 ** $z) * 1.4;
        $src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . ($lon - $d) . ',' . ($lat - $d / 2) . ',' . ($lon + $d) . ',' . ($lat + $d / 2) . '&layer=mapnik&marker=' . $lat . ',' . $lon;
        $nome = 'OpenStreetMap';
    } else {
        if (trim($v['indirizzo']) === '') return ewx_vuoto('Scrivi un indirizzo');
        $src = 'https://www.google.com/maps?q=' . rawurlencode(trim($v['indirizzo'])) . '&z=' . $z . '&hl=it' . ($v['tipo'] === 'satellite' ? '&t=k' : '') . '&output=embed';
        $nome = 'Google Maps';
    }
    $titolo = $v['fornitore'] === 'osm' ? 'Mappa' : 'Mappa: ' . $v['indirizzo'];
    $out = '<div class="ew-mappa' . ($v['grigio'] ? ' ew-grigio' : '') . '"' . ($v['al_clic'] ? ' data-src="' . h($src) . '" data-titolo="' . h($titolo) . '"' : '') . '>';
    if ($v['al_clic'] && !$ctx['anteprima']) $out .= '<button type="button" class="ew-mappa-avvia" data-ew-mappa>' . icona_svg('pin') . '<b>' . h($v['testo_pulsante']) . '</b><small>La mappa arriva da ' . $nome . '</small></button>';
    elseif ($ctx['anteprima'] && $v['al_clic']) $out .= '<div class="ew-mappa-avvia ew-mappa-finta">' . icona_svg('pin') . '<b>' . h($v['testo_pulsante']) . '</b><small>Sul sito la mappa si carica al clic, da ' . $nome . '</small></div>';
    else $out .= '<iframe src="' . h($src) . '" title="' . h($titolo) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
    return $out . '</div>';
});

// ---------------- Flip box
ewx_registra('flip_box', 'Flip box', 'copia', 'Gallerie', [
    ec('f_grafica', 'scelta', 'Elemento grafico', 'icona', 'contenuto', ['opzioni' => ['nessuno' => 'Nessuno', 'icona' => 'Icona', 'immagine' => 'Immagine'], 'sezione' => 'Fronte']),
    ex('f_icona', 'icona', 'Icona', 'lampadina', 'contenuto', ['condizione' => ['f_grafica' => 'icona'], 'sezione' => 'Fronte']),
    ec('f_immagine', 'immagine', 'Immagine', '', 'contenuto', ['condizione' => ['f_grafica' => 'immagine'], 'sezione' => 'Fronte']),
    ec('f_titolo', 'testo', 'Titolo', 'Il fronte', 'contenuto', ['sezione' => 'Fronte']),
    ec('f_testo', 'testolungo', 'Testo', 'Passa il mouse per girare.', 'contenuto', ['sezione' => 'Fronte']),
    ex('f_sfondo', 'sfondo', 'Sfondo del fronte', [], 'stile', ['selettore' => ' .ew-flip-fronte', 'sezione' => 'Fronte']),
    ec('r_titolo', 'testo', 'Titolo', 'Il retro', 'contenuto', ['sezione' => 'Retro']),
    ec('r_testo', 'testolungo', 'Testo', 'Qui la spiegazione completa, con un pulsante.', 'contenuto', ['sezione' => 'Retro']),
    ec('r_pulsante', 'testo', 'Testo del pulsante', 'Scopri', 'contenuto', ['sezione' => 'Retro']),
    ewx_link('link', 'Link'),
    ec('link_su', 'scelta', 'Il link vale su', 'pulsante', 'contenuto', ['opzioni' => ['pulsante' => 'Solo il pulsante', 'tutto' => 'Tutto il retro'], 'sezione' => 'Retro']),
    ex('r_sfondo', 'sfondo', 'Sfondo del retro', [], 'stile', ['selettore' => ' .ew-flip-retro', 'sezione' => 'Retro']),
    ec('effetto', 'scelta', 'Effetto', 'gira', 'contenuto', ['opzioni' => ['gira' => 'Gira', 'scivola' => 'Scivola', 'spinge' => 'Spinge', 'zoom' => 'Zoom', 'dissolve' => 'Dissolve'], 'sezione' => 'Impostazioni']),
    ec('direzione', 'scelta', 'Direzione', 'destra', 'contenuto', ['opzioni' => ['destra' => 'Da destra', 'sinistra' => 'Da sinistra', 'su' => 'Dal basso', 'giu' => 'Dall\'alto'], 'condizione' => ['!effetto' => ['zoom', 'dissolve']], 'sezione' => 'Impostazioni']),
    ewx_var('altezza', 'Altezza', '--ew-h', 'Impostazioni', ['d' => ['v' => 280, 'u' => 'px']], ['intervalli' => ['px' => [100, 1000, 1]]]),
    ewx_allinea('allineamento', 'Impostazioni', 'center'),
    ec('verticale', 'scelta', 'Contenuto allineato', 'center', 'contenuto', ['opzioni' => ['flex-start' => 'In alto', 'center' => 'Al centro', 'flex-end' => 'In basso'], 'selettore' => '', 'proprieta' => '--ew-valign', 'sezione' => 'Impostazioni']),
    ex('padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-flip-contenuto', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Impostazioni']),
    ex('bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' .ew-flip-fronte, .ew-flip-retro', 'sezione' => 'Impostazioni']),
    ewx_col('icona_colore', 'Colore dell\'icona', '--ew-c1', 'Fronte: stile'),
    ewx_var('icona_dimensione', 'Dimensione dell\'icona', '--ew-dim', 'Fronte: stile', ['d' => ['v' => 48, 'u' => 'px']]),
    ewx_var('immagine_larghezza', 'Larghezza dell\'immagine', '--ew-imgw', 'Fronte: stile', ['d' => ['v' => 100, 'u' => 'px']]),
    ...ewx_testo_stile('f_titolo', 'Fronte: titolo', ' .ew-flip-fronte .ew-flip-titolo', '--ew-ftc'),
    ...ewx_testo_stile('f_testo', 'Fronte: testo', ' .ew-flip-fronte .ew-flip-testo', '--ew-fdc'),
    ...ewx_testo_stile('r_titolo', 'Retro: titolo', ' .ew-flip-retro .ew-flip-titolo', '--ew-rtc'),
    ...ewx_testo_stile('r_testo', 'Retro: testo', ' .ew-flip-retro .ew-flip-testo', '--ew-rdc'),
    ewx_col('pulsante_colore', 'Colore del testo', '--ew-pc', 'Retro: pulsante', true),
    ewx_col('pulsante_sfondo', 'Sfondo', '--ew-pbg', 'Retro: pulsante', true),
    ex('pulsante_bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' .ew-flip-pulsante', 'sezione' => 'Retro: pulsante', 'stati' => true]),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $eff = in_array($v['effetto'], ['scivola', 'spinge', 'zoom', 'dissolve'], true) ? $v['effetto'] : 'gira';
    $dir = in_array($v['direzione'], ['sinistra', 'su', 'giu'], true) ? $v['direzione'] : 'destra';
    $url = editor_link_sicuro((string)($v['link']['url'] ?? ''));
    $nuova = !empty($v['link']['nuova']) ? ' target="_blank" rel="noopener"' : '';
    $graf = '';
    if ($v['f_grafica'] === 'icona') $graf = ewx_ic((string)$v['f_icona']) !== '' ? '<span class="ew-flip-icona">' . ewx_ic((string)$v['f_icona']) . '</span>' : '';
    elseif ($v['f_grafica'] === 'immagine' && $v['f_immagine'] !== '') $graf = '<span class="ew-flip-immagine">' . ewx_img($v['f_immagine'], $v['f_titolo']) . '</span>';
    $fronte = '<div class="ew-flip-fronte"><div class="ew-flip-contenuto">' . $graf . (trim($v['f_titolo']) !== '' ? '<h3 class="ew-flip-titolo">' . h($v['f_titolo']) . '</h3>' : '') . (trim($v['f_testo']) !== '' ? '<div class="ew-flip-testo">' . editor_paragrafi($v['f_testo']) . '</div>' : '') . '</div></div>';
    $tutto = $url !== '' && $v['link_su'] === 'tutto';
    $puls = trim($v['r_pulsante']) !== '' ? ($tutto || $url === '' ? '<span class="btn btn-filo ew-flip-pulsante">' . h($v['r_pulsante']) . '</span>' : '<a class="btn btn-filo ew-flip-pulsante" href="' . h($url) . '"' . $nuova . '>' . h($v['r_pulsante']) . '</a>') : '';
    $retro = '<' . ($tutto ? 'a href="' . h($url) . '"' . $nuova : 'div') . ' class="ew-flip-retro"><div class="ew-flip-contenuto">' . (trim($v['r_titolo']) !== '' ? '<h3 class="ew-flip-titolo">' . h($v['r_titolo']) . '</h3>' : '') . (trim($v['r_testo']) !== '' ? '<div class="ew-flip-testo">' . editor_paragrafi($v['r_testo']) . '</div>' : '') . $puls . '</div></' . ($tutto ? 'a' : 'div') . '>';
    return '<div class="ew-flip ew-flip-' . $eff . ' ew-dir-' . $dir . '" tabindex="0"><div class="ew-flip-interno">' . $fronte . $retro . '</div></div>';
});

// ---------------- Titolo animato (Animated Headline)
ewx_registra('titolo_animato', 'Titolo animato', 'bacchetta', 'Marketing', [
    ec('modo', 'scelta', 'Modo', 'evidenziato', 'contenuto', ['opzioni' => ['evidenziato' => 'Parola evidenziata (disegno)', 'rotante' => 'Parole che si alternano'], 'sezione' => 'Titolo']),
    ec('prima', 'testo', 'Testo prima', 'Un sito che è', 'contenuto', ['sezione' => 'Titolo']),
    ec('evidenziato', 'testo', 'Parola evidenziata', 'tuo', 'contenuto', ['condizione' => ['modo' => 'evidenziato'], 'sezione' => 'Titolo']),
    ec('rotanti', 'testolungo', 'Parole che si alternano (una per riga)', "veloce\nchiaro\ntuo", 'contenuto', ['condizione' => ['modo' => 'rotante'], 'sezione' => 'Titolo']),
    ec('dopo', 'testo', 'Testo dopo', '', 'contenuto', ['sezione' => 'Titolo']),
    ewx_livello('h2'),
    ewx_link(),
    ec('forma', 'scelta', 'Forma del disegno', 'cerchio', 'contenuto', ['opzioni' => ['cerchio' => 'Cerchio', 'sottolinea' => 'Sottolineatura', 'doppia' => 'Doppia sottolineatura', 'zigzag' => 'Zigzag', 'barrato' => 'Barrato', 'evidenziatore' => 'Evidenziatore', 'x' => 'Una X'], 'condizione' => ['modo' => 'evidenziato'], 'sezione' => 'Animazione']),
    ec('animazione', 'scelta', 'Animazione delle parole', 'scrittura', 'contenuto', ['opzioni' => ['scrittura' => 'Scrittura a macchina', 'scorri' => 'Scorri dal basso', 'dissolvi' => 'Dissolvenza', 'zoom' => 'Zoom', 'gira' => 'Gira'], 'condizione' => ['modo' => 'rotante'], 'sezione' => 'Animazione']),
    ec('ciclo', 'interruttore', 'Ripeti all\'infinito', true, 'contenuto', ['sezione' => 'Animazione']),
    ec('durata', 'numero', 'Durata (millisecondi)', 2500, 'contenuto', ['min' => 500, 'max' => 10000, 'sezione' => 'Animazione']),
    ewx_allinea('allineamento', 'Titolo', ''),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-tanim', '--ew-tc'),
    ...ewx_testo_stile('parole', 'Parole evidenziate o rotanti', ' .ew-tanim-mark, .ew-tanim-rot', '--ew-wc'),
    ewx_col('forma_colore', 'Colore del disegno', '--ew-mc', 'Disegno'),
    ewx_var('forma_spessore', 'Spessore del tratto', '--ew-mw', 'Disegno', ['d' => ['v' => 6, 'u' => 'px']], ['intervalli' => ['px' => [1, 30, 1]]]),
    ec('forma_sopra', 'interruttore', 'Disegno sopra il testo', false, 'stile', ['sezione' => 'Disegno']),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $tag = ewx_tag($v, 'h2');
    [$a, $z] = ewx_a($v['link'], 'ew-tanim-link', 'span');
    $prima = trim($v['prima']) !== '' ? '<span class="ew-tanim-prima">' . h($v['prima']) . '</span> ' : '';
    $dopo = trim($v['dopo']) !== '' ? ' <span class="ew-tanim-dopo">' . h($v['dopo']) . '</span>' : '';
    if ($v['modo'] === 'rotante') {
        $parole = array_values(array_filter(array_map('trim', explode("\n", (string)$v['rotanti'])), fn($x) => $x !== ''));
        if (!$parole) return ewx_vuoto('Scrivi le parole che si alternano');
        $lunga = array_reduce($parole, fn($c, $p) => mb_strlen($p) > mb_strlen($c) ? $p : $c, '');
        $rot = '<span class="ew-tanim-rot ew-anim-' . h($v['animazione']) . '" data-ew-rot data-durata="' . (int)$v['durata'] . '" data-ciclo="' . ($v['ciclo'] ? 1 : 0) . '"><span class="ew-tanim-misura" aria-hidden="true">' . h($lunga) . '</span>';
        foreach ($parole as $i => $p) $rot .= '<span class="ew-tanim-parola' . ($i === 0 ? ' attiva' : '') . '"' . ($i === 0 ? '' : ' aria-hidden="true"') . '>' . h($p) . '</span>';
        $rot .= '</span>';
        return "<$tag class=\"ew-tanim ew-tanim-rotante\">" . $a . $prima . $rot . $dopo . $z . "</$tag>";
    }
    $forme = [
        'cerchio' => '<path d="M325,18C228,16 94,13 55,35 10,61 17,108 71,128 155,150 341,152 438,130 497,112 491,68 440,44 371,15 263,16 199,20"/>',
        'sottolinea' => '<path d="M7.7,145.6C109,125 299,116 494,120"/>',
        'doppia' => '<path d="M8,135c100,-12 290,-16 486,-10"/><path d="M20,150c110,-8 280,-10 464,-8"/>',
        'zigzag' => '<path d="M5,125l40,22 40,-22 40,22 40,-22 40,22 40,-22 40,22 40,-22 40,22 40,-22 40,22 40,-22"/>',
        'barrato' => '<path d="M5,75h490"/>',
        'evidenziatore' => '<path d="M10,75h480" class="ew-mark-spessa"/>',
        'x' => '<path d="M10,10 490,140"/><path d="M490,10 10,140"/>',
    ];
    $f = isset($forme[$v['forma']]) ? $v['forma'] : 'cerchio';
    $mark = '<span class="ew-tanim-mark ew-mark-' . $f . ($v['forma_sopra'] ? ' ew-mark-sopra' : '') . '" data-ew-mark data-ciclo="' . ($v['ciclo'] ? 1 : 0) . '" data-durata="' . (int)$v['durata'] . '">' . h($v['evidenziato'])
        . '<svg class="ew-mark-svg" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true">' . $forme[$f] . '</svg></span>';
    return "<$tag class=\"ew-tanim ew-tanim-evid\">" . $a . $prima . $mark . $dopo . $z . "</$tag>";
});

// ---------------- Listino (Price List)
ewx_registra('listino', 'Listino', 'euro', 'Marketing', [
    ec('voci', 'ripetitore', 'Voci', [['titolo' => 'Prima voce', 'prezzo' => '25 €', 'testo' => 'Cosa comprende, in una riga.', 'immagine' => '', 'link' => ''], ['titolo' => 'Seconda voce', 'prezzo' => 'Su misura', 'testo' => 'Una descrizione breve e concreta.', 'immagine' => '', 'link' => '']], 'contenuto', ['campi' => [
        ec('titolo', 'testo', 'Titolo', 'Nuova voce'), ec('prezzo', 'testo', 'Prezzo', ''), ec('testo', 'testolungo', 'Descrizione', ''), ec('immagine', 'immagine', 'Immagine', ''), ec('link', 'testo', 'Link (facoltativo)', ''),
    ], 'titolo_voce' => 'titolo', 'sezione' => 'Voci']),
    ec('separatore', 'scelta', 'Fra titolo e prezzo', 'puntini', 'contenuto', ['opzioni' => ['puntini' => 'Puntini', 'linea' => 'Linea', 'nessuno' => 'Niente'], 'sezione' => 'Aspetto']),
    ec('immagine_posizione', 'scelta', 'Posizione dell\'immagine', 'sinistra', 'contenuto', ['opzioni' => ['sinistra' => 'A sinistra', 'destra' => 'A destra'], 'sezione' => 'Aspetto']),
    ec('divisore', 'interruttore', 'Riga fra le voci', true, 'contenuto', ['sezione' => 'Aspetto']),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-list-titolo', '--ew-tc'),
    ...ewx_testo_stile('prezzo', 'Prezzo', ' .ew-list-prezzo', '--ew-pc'),
    ...ewx_testo_stile('testo', 'Descrizione', ' .ew-list-testo', '--ew-dc'),
    ewx_col('separatore_colore', 'Colore dei puntini e delle righe', '--ew-bc', 'Separatori'),
    ewx_var('spazio', 'Spazio fra le voci', '--ew-gap', 'Separatori', ['d' => ['v' => 18, 'u' => 'px']]),
    ewx_var('immagine_dimensione', 'Dimensione dell\'immagine', '--ew-imgs', 'Immagine', ['d' => ['v' => 80, 'u' => 'px']]),
    ex('immagine_bordo', 'bordo', 'Bordo e angoli dell\'immagine', [], 'stile', ['selettore' => ' .ew-list-img img', 'sezione' => 'Immagine']),
    ewx_var('immagine_spazio', 'Spazio fra immagine e testo', '--ew-igap', 'Immagine', ['d' => ['v' => 16, 'u' => 'px']]),
], function (array $v, array $ctx): string {
    $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['titolo'] ?? '')) !== '');
    if (!$voci) return ewx_vuoto('Aggiungi le voci del listino');
    $out = '<ul class="ew-listino ew-sep-' . h($v['separatore']) . ' ew-img-' . ($v['immagine_posizione'] === 'destra' ? 'destra' : 'sinistra') . ($v['divisore'] ? ' ew-con-divisore' : '') . '">';
    foreach ($voci as $x) {
        [$a, $z] = ewx_a($x['link'] ?? '', 'ew-list-voce', 'div');
        $out .= '<li>' . $a . (($x['immagine'] ?? '') !== '' ? '<span class="ew-list-img">' . ewx_img($x['immagine'], $x['titolo']) . '</span>' : '')
            . '<span class="ew-list-corpo"><span class="ew-list-testa"><span class="ew-list-titolo">' . h($x['titolo']) . '</span><span class="ew-list-sep" aria-hidden="true"></span><span class="ew-list-prezzo">' . h((string)($x['prezzo'] ?? '')) . '</span></span>'
            . (trim((string)($x['testo'] ?? '')) !== '' ? '<span class="ew-list-testo">' . nl2br(h($x['testo']), false) . '</span>' : '') . '</span>' . $z . '</li>';
    }
    return $out . '</ul>';
});

// ---------------- Tabella prezzi (Price Table)
ewx_registra('tabella_prezzi', 'Tabella prezzi', 'tabella', 'Marketing', [
    ec('titolo', 'testo', 'Titolo', (string)cms_impostazione('w_prezzi_titolo', 'I nostri piani'), 'contenuto', ['sezione' => 'Intestazione']),
    ec('sottotitolo', 'testo', 'Sottotitolo', 'Tutto quello che serve per cominciare', 'contenuto', ['sezione' => 'Intestazione']),
    ec('valuta', 'testo', 'Simbolo della valuta', 'EUR', 'contenuto', ['sezione' => 'Prezzo']),
    ec('valuta_posizione', 'scelta', 'Posizione della valuta', 'prima', 'contenuto', ['opzioni' => ['prima' => 'Prima del numero', 'dopo' => 'Dopo il numero'], 'sezione' => 'Prezzo']),
    ec('prezzo', 'testo', 'Prezzo', '49', 'contenuto', ['sezione' => 'Prezzo']),
    ec('decimali', 'testo', 'Decimali (facoltativi)', '', 'contenuto', ['sezione' => 'Prezzo']),
    ec('prezzo_vecchio', 'testo', 'Prezzo barrato (facoltativo)', '', 'contenuto', ['sezione' => 'Prezzo']),
    ec('periodo', 'testo', 'Periodo', '/ mese', 'contenuto', ['sezione' => 'Prezzo']),
    ec('voci', 'ripetitore', 'Cosa comprende', [['testo' => 'Prima cosa compresa', 'icona' => 'spunta'], ['testo' => 'Seconda cosa compresa', 'icona' => 'spunta'], ['testo' => 'Assistenza via email', 'icona' => 'spunta']], 'contenuto', ['campi' => [
        ec('testo', 'testo', 'Testo', 'Nuova voce'), ex('icona', 'icona', 'Icona', 'spunta'),
    ], 'titolo_voce' => 'testo', 'sezione' => 'Elenco']),
    ec('pulsante_testo', 'testo', 'Testo del pulsante', 'Scegli', 'contenuto', ['sezione' => 'Pulsante']),
    ewx_link(),
    ec('nota', 'testolungo', 'Nota in fondo', '', 'contenuto', ['sezione' => 'Pulsante']),
    ec('nastro', 'testo', 'Nastro (facoltativo)', '', 'contenuto', ['sezione' => 'Nastro']),
    ec('nastro_posizione', 'scelta', 'Posizione del nastro', 'destra', 'contenuto', ['opzioni' => ['destra' => 'In alto a destra', 'sinistra' => 'In alto a sinistra'], 'condizione' => ['!nastro' => ''], 'sezione' => 'Nastro']),
    ec('evidenza', 'interruttore', 'In evidenza (bordo colorato)', false, 'contenuto', ['sezione' => 'Nastro']),
    ewx_col('testa_sfondo', 'Sfondo', '--ew-hbg', 'Intestazione'),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-prezzo-titolo', '--ew-tc'),
    ...ewx_testo_stile('sottotitolo', 'Sottotitolo', ' .ew-prezzo-sotto', '--ew-sc'),
    ewx_col('prezzo_sfondo', 'Sfondo', '--ew-pbg', 'Prezzo'),
    ...ewx_testo_stile('prezzo', 'Numero', ' .ew-prezzo-num', '--ew-pc'),
    ...ewx_testo_stile('valuta', 'Valuta e decimali', ' .ew-prezzo-valuta, .ew-prezzo-dec', '--ew-vc'),
    ...ewx_testo_stile('periodo', 'Periodo', ' .ew-prezzo-periodo', '--ew-perc'),
    ewx_col('vecchio_colore', 'Colore del prezzo barrato', '--ew-oc', 'Prezzo'),
    ...ewx_testo_stile('voci', 'Voci', ' .ew-prezzo-voci li', '--ew-lc'),
    ewx_col('voci_icona_colore', 'Colore delle icone', '--ew-c1', 'Voci'),
    ewx_col('voci_divisore', 'Colore delle righe', '--ew-bc', 'Voci'),
    ewx_var('voci_spazio', 'Spazio fra le voci', '--ew-gap', 'Voci', ['d' => ['v' => 10, 'u' => 'px']]),
    ewx_tip('pulsante_tipografia', 'Tipografia', ' .ew-prezzo-pulsante', 'Pulsante'),
    ewx_col('pulsante_colore', 'Colore del testo', '--ew-btc', 'Pulsante', true),
    ewx_col('pulsante_sfondo', 'Sfondo', '--ew-bbg', 'Pulsante', true),
    ex('pulsante_bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' .ew-prezzo-pulsante', 'sezione' => 'Pulsante', 'stati' => true]),
    ...ewx_testo_stile('nota', 'Nota', ' .ew-prezzo-nota', '--ew-nc'),
    ewx_col('nastro_colore', 'Colore', '--ew-rc', 'Nastro'),
    ewx_col('nastro_sfondo', 'Sfondo', '--ew-rbg', 'Nastro'),
    ...ewx_riquadro_stile(' .ew-prezzo'),
], function (array $v, array $ctx): string {
    $val = '<span class="ew-prezzo-valuta">' . h($v['valuta']) . '</span>';
    $num = '<b class="ew-prezzo-num">' . h($v['prezzo']) . '</b>' . (trim($v['decimali']) !== '' ? '<sup class="ew-prezzo-dec">,' . h(ltrim($v['decimali'], ',.')) . '</sup>' : '');
    $out = '<div class="ew-prezzo' . ($v['evidenza'] ? ' ew-prezzo-evidenza' : '') . '">';
    if (trim($v['titolo']) !== '' || trim($v['sottotitolo']) !== '') $out .= '<div class="ew-prezzo-testa">' . (trim($v['titolo']) !== '' ? '<h3 class="ew-prezzo-titolo">' . h($v['titolo']) . '</h3>' : '') . (trim($v['sottotitolo']) !== '' ? '<p class="ew-prezzo-sotto">' . h($v['sottotitolo']) . '</p>' : '') . '</div>';
    $out .= '<div class="ew-prezzo-importo">' . (trim($v['prezzo_vecchio']) !== '' ? '<s class="ew-prezzo-vecchio">' . h($v['prezzo_vecchio']) . '</s>' : '') . '<span class="ew-prezzo-cifra">' . ($v['valuta_posizione'] === 'dopo' ? $num . $val : $val . $num) . '</span>' . (trim($v['periodo']) !== '' ? '<span class="ew-prezzo-periodo">' . h($v['periodo']) . '</span>' : '') . '</div>';
    $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['testo'] ?? '')) !== '');
    if ($voci) $out .= '<ul class="ew-prezzo-voci">' . implode('', array_map(fn($x) => '<li>' . ewx_ic((string)($x['icona'] ?? '')) . '<span>' . h($x['testo']) . '</span></li>', $voci)) . '</ul>';
    $url = editor_link_sicuro((string)($v['link']['url'] ?? ''));
    if (trim($v['pulsante_testo']) !== '') $out .= '<div class="ew-prezzo-azione"><a class="btn btn-viola ew-prezzo-pulsante" href="' . h($url ?: '#') . '"' . (!empty($v['link']['nuova']) ? ' target="_blank" rel="noopener"' : '') . '>' . h($v['pulsante_testo']) . '</a></div>';
    if (trim($v['nota']) !== '') $out .= '<p class="ew-prezzo-nota">' . nl2br(h($v['nota']), false) . '</p>';
    if (trim($v['nastro']) !== '') $out .= '<span class="ew-prezzo-nastro ew-nastro-' . ($v['nastro_posizione'] === 'sinistra' ? 'sinistra' : 'destra') . '"><span>' . h($v['nastro']) . '</span></span>';
    return $out . '</div>';
});

// ---------------- Sommario (Table of Contents)
ewx_registra('sommario', 'Sommario', 'elenco', 'Interazione', [
    ec('titolo', 'testo', 'Titolo', 'Sommario', 'contenuto', ['sezione' => 'Sommario']),
    ec('livelli', 'scelta', 'Titoli da includere', 'h2h3', 'contenuto', ['opzioni' => ['h2' => 'Solo H2', 'h2h3' => 'H2 e H3', 'h2h4' => 'H2, H3 e H4'], 'sezione' => 'Sommario']),
    ec('contenitore', 'testo', 'Cerca i titoli dentro (selettore CSS, vuoto: tutta la pagina)', '', 'contenuto', ['sezione' => 'Sommario']),
    ec('escludi', 'testo', 'Escludi (selettore CSS)', '', 'contenuto', ['sezione' => 'Sommario']),
    ec('segno', 'scelta', 'Segno davanti alle voci', 'numeri', 'contenuto', ['opzioni' => ['numeri' => 'Numeri', 'pallini' => 'Pallini', 'nessuno' => 'Niente'], 'sezione' => 'Sommario']),
    ec('gerarchico', 'interruttore', 'Rientra i sottotitoli', true, 'contenuto', ['sezione' => 'Sommario']),
    ec('messaggio_vuoto', 'testo', 'Se non trova titoli', 'Nessun titolo in questa pagina.', 'contenuto', ['sezione' => 'Sommario']),
    ec('richiudibile', 'interruttore', 'Si può chiudere', true, 'contenuto', ['sezione' => 'Comportamento']),
    ec('chiuso', 'scelta', 'Parte chiuso', 'telefono', 'contenuto', ['opzioni' => ['mai' => 'Mai', 'telefono' => 'Su telefono', 'sempre' => 'Sempre'], 'condizione' => ['richiudibile' => true], 'sezione' => 'Comportamento']),
    ec('fisso', 'interruttore', 'Resta fermo scorrendo (sticky)', false, 'contenuto', ['sezione' => 'Comportamento']),
    ec('fisso_offset', 'numero', 'Distanza dall\'alto (px)', 90, 'contenuto', ['min' => 0, 'max' => 400, 'unita' => 'px', 'selettore' => '', 'proprieta' => '--ew-top', 'condizione' => ['fisso' => true], 'sezione' => 'Comportamento']),
    ec('evidenzia', 'interruttore', 'Evidenzia la voce che stai leggendo', true, 'contenuto', ['sezione' => 'Comportamento']),
    ewx_col('testa_sfondo', 'Sfondo', '--ew-hbg', 'Testata'),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-toc-titolo', '--ew-tc'),
    ewx_col('icona_colore', 'Colore dell\'icona', '--ew-c1', 'Testata'),
    ewx_col('voce_colore', 'Colore', '--ew-lc', 'Voci', true),
    ewx_col('voce_colore_attiva', 'Colore della voce attiva', '--ew-lca', 'Voci'),
    ewx_tip('voce_tipografia', 'Tipografia', ' .ew-toc-lista a', 'Voci'),
    ewx_var('voce_spazio', 'Spazio fra le voci', '--ew-gap', 'Voci', ['d' => ['v' => 6, 'u' => 'px']]),
    ewx_var('voce_rientro', 'Rientro dei sottotitoli', '--ew-ind', 'Voci', ['d' => ['v' => 18, 'u' => 'px']]),
    ewx_var('altezza_massima', 'Altezza massima dell\'elenco (0: nessuna)', '--ew-maxh', 'Voci', '', ['intervalli' => ['px' => [0, 1200, 1]]]),
    ...ewx_riquadro_stile(' .ew-toc'),
], function (array $v, array $ctx): string {
    $liv = ['h2' => 'h2', 'h2h3' => 'h2,h3', 'h2h4' => 'h2,h3,h4'][$v['livelli']] ?? 'h2,h3';
    $chiuso = $v['richiudibile'] ? ($v['chiuso'] === 'sempre' ? 'sempre' : ($v['chiuso'] === 'mai' ? 'mai' : 'telefono')) : 'mai';
    return '<nav class="ew-toc ew-toc-' . h($v['segno']) . ($v['gerarchico'] ? ' ew-toc-gerarchico' : '') . ($v['fisso'] ? ' ew-sticky' : '') . '" data-ew-toc data-livelli="' . $liv . '" data-cont="' . h(trim($v['contenitore'])) . '" data-escludi="' . h(trim($v['escludi'])) . '" data-chiuso="' . $chiuso . '" data-evidenzia="' . ($v['evidenzia'] ? 1 : 0) . '" aria-label="' . h($v['titolo'] ?: 'Sommario') . '">'
        . '<div class="ew-toc-testa"><b class="ew-toc-titolo">' . h($v['titolo']) . '</b>' . ($v['richiudibile'] ? '<button type="button" class="ew-toc-toggle" aria-expanded="true" aria-label="Apri o chiudi il sommario">' . icona_svg('chevron-giu') . '</button>' : '') . '</div>'
        . '<div class="ew-toc-corpo"><ol class="ew-toc-lista"></ol><p class="ew-toc-vuoto" hidden>' . h($v['messaggio_vuoto']) . '</p></div></nav>';
});

// ---------------- Griglia di contenuti (Posts)
/** Cosa può mostrare la griglia: gli articoli, i tipi da leggere dei moduli del sito, tutti insieme. Chiave => [nome, tabella]. */
function ewx_fonti_griglia(): array {
    $f = ['articoli' => ['Articoli', 'articles']];
    if (function_exists('seo_tipi_moduli') || is_file(__DIR__ . '/seo.php')) {
        require_once __DIR__ . '/seo.php';
        foreach (seo_tipi_moduli() as $k => $t) $f[$k] = [(string)$t['Tanti'], (string)$t['tabella']];
    }
    if (count($f) > 1) $f['tutti'] = ['Tutti i contenuti da leggere', ''];
    return $f;
}
ewx_registra('griglia_contenuti', 'Griglia di articoli', 'giornale', 'Dal sito', [
    ec('fonte', 'scelta', 'Cosa mostrare', 'articoli', 'contenuto', ['opzioni' => array_map(fn($x) => $x[0], ewx_fonti_griglia()), 'sezione' => 'Interrogazione']),
    ec('categoria', 'testo', 'Solo una categoria (slug)', '', 'contenuto', ['sezione' => 'Interrogazione']),
    ec('tag', 'testo', 'Solo un tag (slug)', '', 'contenuto', ['sezione' => 'Interrogazione']),
    ec('solo_pillar', 'interruttore', 'Solo i pillar', false, 'contenuto', ['sezione' => 'Interrogazione']),
    ec('escludi_corrente', 'interruttore', 'Escludi la pagina in cui sta il widget', true, 'contenuto', ['sezione' => 'Interrogazione']),
    ec('ordine', 'scelta', 'Ordine', 'recenti', 'contenuto', ['opzioni' => ['recenti' => 'Dal più recente', 'vecchi' => 'Dal più vecchio', 'titolo' => 'Per titolo', 'casuale' => 'Casuale'], 'sezione' => 'Interrogazione']),
    ec('quanti', 'numero', 'Quanti', 6, 'contenuto', ['min' => 1, 'max' => 60, 'sezione' => 'Interrogazione']),
    ec('salta', 'numero', 'Salta i primi', 0, 'contenuto', ['min' => 0, 'max' => 100, 'sezione' => 'Interrogazione']),
    ec('aspetto', 'scelta', 'Aspetto delle schede', 'classico', 'contenuto', ['opzioni' => ['classico' => 'Classico (immagine sopra)', 'schede' => 'Schede del sito (come oggi)', 'compatto' => 'Compatto (immagine a sinistra)', 'copertina' => 'Immagine con il titolo sopra'], 'sezione' => 'Disposizione']),
    ewx_colonne(3),
    ec('paginazione', 'scelta', 'Paginazione', 'nessuna', 'contenuto', ['opzioni' => ['nessuna' => 'Nessuna', 'numeri' => 'Numeri di pagina', 'altri' => 'Pulsante "Carica altri"'], 'sezione' => 'Disposizione']),
    ec('per_pagina', 'numero', 'Per pagina', 6, 'contenuto', ['min' => 1, 'max' => 30, 'condizione' => ['!paginazione' => 'nessuna'], 'sezione' => 'Disposizione']),
    ec('mostra_immagine', 'interruttore', 'Immagine', true, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('proporzioni', 'scelta', 'Proporzioni dell\'immagine', '16/9', 'contenuto', ['opzioni' => ['16/9' => '16:9', '4/3' => '4:3', '1/1' => 'Quadrata', '3/4' => '3:4'], 'selettore' => '', 'proprieta' => '--ew-ratio', 'condizione' => ['mostra_immagine' => true], 'sezione' => 'Cosa mostrare']),
    ewx_livello('h3'),
    ec('mostra_estratto', 'interruttore', 'Riassunto', true, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('lunghezza_estratto', 'numero', 'Parole del riassunto', 25, 'contenuto', ['min' => 5, 'max' => 100, 'condizione' => ['mostra_estratto' => true], 'sezione' => 'Cosa mostrare']),
    ec('meta_data', 'interruttore', 'Data', true, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('meta_categoria', 'interruttore', 'Categoria', true, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('meta_autore', 'interruttore', 'Autore', false, 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('pulsante_testo', 'testo', 'Testo del link "Leggi" (vuoto: niente)', 'Leggi', 'contenuto', ['sezione' => 'Cosa mostrare']),
    ec('messaggio_vuoto', 'testo', 'Se non c\'è niente', 'Ancora nessun articolo con questi filtri.', 'contenuto', ['sezione' => 'Cosa mostrare']),
    ewx_var('spazio', 'Spazio fra le schede', '--ew-gap', 'Griglia', ['d' => ['v' => 18, 'u' => 'px']]),
    ...ewx_riquadro_stile(' .ew-post', 'Scheda'),
    ewx_var('immagine_spazio', 'Spazio sotto l\'immagine', '--ew-igap', 'Immagine', ['d' => ['v' => 14, 'u' => 'px']]),
    ewx_var('immagine_raggio', 'Angoli dell\'immagine', '--ew-ir', 'Immagine', ['d' => ['v' => 12, 'u' => 'px']]),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-post-titolo', '--ew-tc', true),
    ewx_var('titolo_spazio', 'Spazio sotto il titolo', '--ew-tgap', 'Titolo', ['d' => ['v' => 8, 'u' => 'px']]),
    ...ewx_testo_stile('meta', 'Meta', ' .ew-post-meta', '--ew-mc'),
    ...ewx_testo_stile('estratto', 'Riassunto', ' .ew-post-estratto', '--ew-dc'),
    ewx_col('pulsante_colore', 'Colore del link "Leggi"', '--ew-pc', 'Link Leggi', true),
    ewx_col('paginazione_colore', 'Colore', '--ew-pgc', 'Paginazione', true),
    ewx_col('paginazione_attiva', 'Colore della pagina attiva', '--ew-pgca', 'Paginazione'),
], function (array $v, array $ctx): string {
    if (!function_exists('sito_contenuti')) return ewx_vuoto('Gli articoli si vedono sul sito');
    $cat = trim((string)$v['categoria']); $tag = trim((string)$v['tag']);
    $percorso = $ctx['percorso'] ?? '/';
    $tutti = [];
    try { $contenuti = sito_contenuti_elenco('elenco'); } catch (Throwable $e) { return ewx_vuoto('Gli articoli si vedono quando il database risponde'); }
    $tabella = ewx_fonti_griglia()[$v['fonte']][1] ?? 'articles';
    foreach ($contenuti as $c) {
        if ($c['tipo'] !== 'post' || $c['rediretto'] || $c['seo']['robots'] !== 'index') continue;
        if ($tabella !== '' && $c['fonte'] !== $tabella) continue;
        if ($v['solo_pillar'] && !$c['pillar']) continue;
        if ($cat !== '' && !in_array($cat, $c['categorie'], true)) continue;
        if ($tag !== '' && !in_array($tag, $c['tag'], true)) continue;
        if ($v['escludi_corrente'] && $c['percorso'] === $percorso) continue;
        $tutti[] = $c;
    }
    switch ($v['ordine']) {
        case 'vecchi': usort($tutti, fn($a, $b) => strcmp($a['data'], $b['data'])); break;
        case 'titolo': usort($tutti, fn($a, $b) => strcoll(mb_strtolower($a['titolo']), mb_strtolower($b['titolo']))); break;
        case 'casuale': shuffle($tutti); break;
        default: usort($tutti, fn($a, $b) => strcmp($b['data'], $a['data']));
    }
    $lista = array_slice($tutti, max(0, (int)$v['salta']), max(1, min(60, (int)$v['quanti'])));
    if (!$lista) return '<p class="ew-posts-vuoto vuoto">' . h($v['messaggio_vuoto']) . '</p>';
    $asp = in_array($v['aspetto'], ['schede', 'compatto', 'copertina'], true) ? $v['aspetto'] : 'classico';
    ewx_css_colonne($ctx, ' .ew-posts-griglia', $v['colonne']);
    $tag = ewx_tag($v);
    $pag = $v['paginazione'] !== 'nessuna' && count($lista) > (int)$v['per_pagina'] ? ' data-ew-pagina="' . max(1, (int)$v['per_pagina']) . '" data-modo="' . ($v['paginazione'] === 'altri' ? 'altri' : 'numeri') . '"' : '';
    $out = '<div class="ew-posts ew-posts-' . $asp . '"' . $pag . '><div class="ew-posts-griglia' . ($asp === 'schede' ? ' griglia' : '') . '">';
    foreach ($lista as $i => $c) {
        if ($asp === 'schede') { $out .= str_replace('<a class="card', '<a class="ew-post card', vista_scheda($c)); continue; }
        $catSlug = $c['categorie'][0] ?? '';
        $nomeCat = $catSlug !== '' && function_exists('sito_categorie') ? (string)(sito_categorie()[$catSlug]['name'] ?? '') : '';
        $meta = [];
        if ($v['meta_categoria'] && $nomeCat !== '') $meta[] = '<span class="ew-post-cat">' . h($nomeCat) . '</span>';
        if ($v['meta_data']) $meta[] = '<time datetime="' . h($c['data']) . '">' . sito_data_estesa($c['data']) . '</time>';
        if ($v['meta_autore'] && cms_impostazione('autore_predefinito', '') !== '') $meta[] = '<span>' . h((string)cms_impostazione('autore_predefinito')) . '</span>';
        $img = '';
        if ($v['mostra_immagine'] && !empty($c['immagine']['url'])) $img = '<a class="ew-post-img" href="' . h($c['percorso']) . '" tabindex="-1" aria-hidden="true"><img src="' . h($c['immagine']['url']) . '" alt="" loading="lazy"></a>';
        $estr = '';
        if ($v['mostra_estratto']) {
            $t = trim($c['estratto'] ?: ($c['seo']['descrizione'] ?: $c['tldr']));
            if ($t !== '') { $p = preg_split('/\s+/u', $t); $estr = '<p class="ew-post-estratto">' . h(implode(' ', array_slice($p, 0, max(5, (int)$v['lunghezza_estratto'])))) . (count($p) > (int)$v['lunghezza_estratto'] ? '...' : '') . '</p>'; }
        }
        $out .= '<article class="ew-post' . ($c['pillar'] ? ' ew-post-pillar' : '') . '">' . $img . '<div class="ew-post-corpo">' . ($meta ? '<div class="ew-post-meta">' . implode('<i aria-hidden="true"></i>', $meta) . '</div>' : '')
            . "<$tag class=\"ew-post-titolo\"><a href=\"" . h($c['percorso']) . '">' . h($c['titolo']) . "</a></$tag>" . $estr
            . (trim($v['pulsante_testo']) !== '' ? '<a class="ew-post-leggi" href="' . h($c['percorso']) . '">' . h($v['pulsante_testo']) . ' ' . icona_svg('freccia-destra') . '</a>' : '') . '</div></article>';
    }
    $out .= '</div>';
    if ($pag !== '') $out .= '<nav class="ew-posts-pag" aria-label="Pagine"></nav>';
    return $out . '</div>';
});

// ---------------- Pulsante fisso (torna su, link, WhatsApp)
ewx_registra('pulsante_fisso', 'Pulsante fisso (torna su)', 'torna-su', 'Elementi', [
    ec('tipo', 'scelta', 'Tipo', 'su', 'contenuto', ['opzioni' => ['su' => 'Torna su', 'link' => 'Link', 'whatsapp' => 'WhatsApp'], 'sezione' => 'Pulsante']),
    ex('icona', 'icona', 'Icona', 'freccia-su', 'contenuto', ['sezione' => 'Pulsante']),
    ec('etichetta', 'testo', 'Etichetta (vuota: solo icona)', '', 'contenuto', ['sezione' => 'Pulsante']),
    ec('aria', 'testo', 'Descrizione per chi non vede', 'Torna in cima alla pagina', 'contenuto', ['sezione' => 'Pulsante']),
    ewx_link('link', 'Link'),
    ec('numero', 'testo', 'Numero WhatsApp (con prefisso, es. 39333...)', '', 'contenuto', ['condizione' => ['tipo' => 'whatsapp'], 'sezione' => 'Pulsante']),
    ec('messaggio', 'testo', 'Messaggio precompilato', (string)cms_impostazione('whatsapp_messaggio', 'Ciao, '), 'contenuto', ['condizione' => ['tipo' => 'whatsapp'], 'sezione' => 'Pulsante']),
    // 1.4.0: "posizione_angolo" (prima "posizione", come il Posizionamento delle Avanzate: "In basso a sinistra" si perdeva).
    ec('posizione_angolo', 'scelta', 'Angolo', 'destra', 'contenuto', ['opzioni' => ['destra' => 'In basso a destra', 'sinistra' => 'In basso a sinistra'], 'sezione' => 'Posizione', 'prima' => 'posizione']),
    ec('mostra_dopo', 'numero', 'Compare dopo (px di scorrimento, 0: sempre)', 400, 'contenuto', ['min' => 0, 'max' => 3000, 'sezione' => 'Posizione']),
    ewx_var('distanza_basso', 'Distanza dal basso', '--ew-b', 'Posizione', ['d' => ['v' => 24, 'u' => 'px']]),
    ewx_var('distanza_lato', 'Distanza dal lato', '--ew-l', 'Posizione', ['d' => ['v' => 24, 'u' => 'px']]),
    ewx_var('dimensione', 'Dimensione', '--ew-dim', 'Aspetto', ['d' => ['v' => 48, 'u' => 'px']]),
    ewx_col('colore', 'Colore dell\'icona', '--ew-c1', 'Aspetto', true),
    ewx_col('sfondo', 'Sfondo', '--ew-c2', 'Aspetto', true),
    ewx_var('raggio', 'Angoli arrotondati', '--ew-r', 'Aspetto', ['d' => ['v' => 50, 'u' => '%']], ['unita' => ['%', 'px']]),
    ex('ombra', 'ombra', 'Ombra', [], 'stile', ['selettore' => ' .ew-fisso', 'sezione' => 'Aspetto']),
], function (array $v, array $ctx): string {
    $tipo = in_array($v['tipo'], ['link', 'whatsapp'], true) ? $v['tipo'] : 'su';
    $href = '#';
    if ($tipo === 'link') $href = editor_link_sicuro((string)($v['link']['url'] ?? '')) ?: '#';
    if ($tipo === 'whatsapp') $href = 'https://wa.me/' . preg_replace('/\D/', '', $v['numero']) . (trim($v['messaggio']) !== '' ? '?text=' . rawurlencode($v['messaggio']) : '');
    $ic = ewx_ic((string)$v['icona']) ?: icona_svg($tipo === 'whatsapp' ? 'whatsapp' : 'freccia-su');
    $cl = 'ew-fisso ew-fisso-' . $tipo . ' ew-fisso-' . ($v['posizione_angolo'] === 'sinistra' ? 'sinistra' : 'destra') . (trim($v['etichetta']) !== '' ? ' ew-fisso-eti' : '');
    $extra = ($tipo !== 'su' && str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '') . ' aria-label="' . h($v['aria']) . '"' . ($ctx['anteprima'] ? ' data-anteprima="1"' : '');
    return '<a class="' . $cl . '" href="' . h($href) . '" data-ew-fisso="' . $tipo . '" data-dopo="' . max(0, (int)$v['mostra_dopo']) . '"' . $extra . '>' . $ic . (trim($v['etichetta']) !== '' ? '<span>' . h($v['etichetta']) . '</span>' : '') . '</a>'
        . ($ctx['anteprima'] ? '<div class="ew-fisso-segnaposto">Pulsante fisso: sul sito sta nell\'angolo in basso' . ($v['posizione_angolo'] === 'sinistra' ? ' a sinistra' : ' a destra') . '.</div>' : '');
});
