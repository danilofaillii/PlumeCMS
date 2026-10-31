<?php
/** Widget extra, parte B: galleria, carosello, carosello di testimonianze, fisarmonica, schede, social, condividi. */

/** Le immagini di una galleria (dal controllo `galleria` o dal ripetitore di ripiego). */
function ewx_immagini($val): array {
    $out = [];
    foreach ((array)$val as $x) {
        if (!is_array($x) || trim((string)($x['immagine'] ?? '')) === '') continue;
        $out[] = ['immagine' => $x['immagine'], 'alt' => (string)($x['alt'] ?? ''), 'didascalia' => (string)($x['didascalia'] ?? ''), 'link' => (string)($x['link'] ?? '')];
    }
    return $out;
}

function ewx_controlli_galleria_stile(): array {
    return [
        ec('clic', 'scelta', 'Al clic', 'lightbox', 'contenuto', ['opzioni' => ['lightbox' => 'Apri a tutto schermo (lightbox)', 'link' => 'Vai al link della voce', 'niente' => 'Niente'], 'sezione' => 'Comportamento']),
        ec('didascalia', 'scelta', 'Didascalia', 'nessuna', 'contenuto', ['opzioni' => ['nessuna' => 'Nessuna', 'sotto' => 'Sotto l\'immagine', 'hover' => 'Al passaggio del mouse'], 'sezione' => 'Comportamento']),
        ec('proporzioni', 'scelta', 'Proporzioni', '1/1', 'stile', ['opzioni' => ['1/1' => 'Quadrate', '4/3' => '4:3', '3/2' => '3:2', '16/9' => '16:9', '3/4' => '3:4 (verticali)', 'auto' => 'Originali'], 'selettore' => '', 'proprieta' => '--ew-ratio', 'sezione' => 'Immagini']),
        ewx_var('spazio', 'Spazio fra le immagini', '--ew-gap', 'Immagini', ['d' => ['v' => 12, 'u' => 'px']]),
        ex('immagine_bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' .ew-gal-voce', 'sezione' => 'Immagini']),
        ec('effetto_hover', 'scelta', 'Effetto al passaggio', 'zoom', 'stile', ['opzioni' => ['' => 'Nessuno', 'zoom' => 'Ingrandisce', 'opacita' => 'Si schiarisce', 'grigio' => 'Da grigio a colori'], 'sezione' => 'Immagini']),
        ewx_col('velo_colore', 'Velo al passaggio', '--ew-velo', 'Immagini'),
        ...ewx_testo_stile('didascalia', 'Didascalia', ' figcaption', '--ew-dc'),
        ewx_col('didascalia_sfondo', 'Sfondo della didascalia', '--ew-dbg', 'Didascalia'),
    ];
}

function ewx_gal_voce(array $x, array $v, bool $anteprima, string $extra = ''): string {
    $img = ewx_img($x['immagine'], $x['alt'] ?: $x['didascalia']);
    $did = $x['didascalia'] !== '' && $v['didascalia'] !== 'nessuna' ? '<figcaption>' . h($x['didascalia']) . '</figcaption>' : '';
    $url = $v['clic'] === 'link' ? editor_link_sicuro($x['link']) : ($v['clic'] === 'lightbox' ? $x['immagine'] : '');
    $lb = $v['clic'] === 'lightbox' ? ' data-ew-lb data-did="' . h($x['didascalia']) . '"' : '';
    $corpo = $url !== '' ? '<a class="ew-gal-link" href="' . h($url) . '"' . $lb . '>' . $img . '</a>' : $img;
    return '<figure class="ew-gal-voce"' . $extra . '>' . $corpo . $did . '</figure>';
}

// ---------------- Galleria (Basic Gallery + Gallery Pro ridotta)
ewx_registra('galleria', 'Galleria', 'griglia', 'Gallerie', [
    ex('immagini', 'galleria', 'Immagini', [], 'contenuto', ['sezione' => 'Immagini']),
    ec('disposizione', 'scelta', 'Disposizione', 'griglia', 'contenuto', ['opzioni' => ['griglia' => 'Griglia', 'mosaico' => 'Mosaico (la prima grande)', 'giustificata' => 'Giustificata (righe piene)'], 'sezione' => 'Disposizione']),
    ewx_colonne(4),
    ewx_var('altezza_riga', 'Altezza delle righe (giustificata)', '--ew-rowh', 'Disposizione', ['d' => ['v' => 220, 'u' => 'px']], ['condizione' => ['disposizione' => 'giustificata']]),
    ...ewx_controlli_galleria_stile(),
], function (array $v, array $ctx): string {
    $imm = ewx_immagini($v['immagini']);
    if (!$imm) return ewx_vuoto('Aggiungi le immagini della galleria');
    $disp = in_array($v['disposizione'], ['mosaico', 'giustificata'], true) ? $v['disposizione'] : 'griglia';
    ewx_css_colonne($ctx, ' .ew-galleria', $v['colonne']);
    $out = '<div class="ew-galleria ew-gal-' . $disp . ' ew-did-' . h($v['didascalia']) . ($v['effetto_hover'] !== '' ? ' ew-gal-hov-' . h($v['effetto_hover']) : '') . '"' . ($v['clic'] === 'lightbox' ? ' data-ew-galleria' : '') . '>';
    foreach ($imm as $x) $out .= ewx_gal_voce($x, $v, $ctx['anteprima']);
    return $out . '</div>';
});

// ---------------- Carosello (Image Carousel)
function ewx_controlli_carosello(string $sezione = 'Scorrimento'): array {
    return [
        ec('per_vista', 'numero', 'Elementi visibili', ['d' => 3, 't' => 2, 'm' => 1], 'contenuto', ['min' => 1, 'max' => 6, 'responsive' => true, 'sezione' => $sezione]),
        ec('scorri', 'scelta', 'Scorri di', 'uno', 'contenuto', ['opzioni' => ['uno' => 'Un elemento', 'vista' => 'Tutti quelli visibili'], 'sezione' => $sezione]),
        ec('frecce', 'interruttore', 'Frecce', true, 'contenuto', ['sezione' => $sezione]),
        ec('pallini', 'interruttore', 'Pallini', true, 'contenuto', ['sezione' => $sezione]),
        ec('autoplay', 'interruttore', 'Scorre da solo', false, 'contenuto', ['sezione' => $sezione]),
        ec('intervallo', 'numero', 'Ogni quanti millisecondi', 5000, 'contenuto', ['min' => 1000, 'max' => 30000, 'condizione' => ['autoplay' => true], 'sezione' => $sezione]),
        ec('pausa_hover', 'interruttore', 'Si ferma al passaggio del mouse', true, 'contenuto', ['condizione' => ['autoplay' => true], 'sezione' => $sezione]),
        ec('ciclo', 'interruttore', 'Ricomincia dall\'inizio', true, 'contenuto', ['sezione' => $sezione]),
        ewx_var('spazio', 'Spazio fra gli elementi', '--ew-gap', $sezione, ['d' => ['v' => 16, 'u' => 'px']]),
        ec('frecce_posizione', 'scelta', 'Posizione delle frecce', 'dentro', 'stile', ['opzioni' => ['dentro' => 'Dentro', 'fuori' => 'Fuori'], 'sezione' => 'Frecce']),
        ewx_var('frecce_dimensione', 'Dimensione', '--ew-adim', 'Frecce', ['d' => ['v' => 40, 'u' => 'px']]),
        ewx_col('frecce_colore', 'Colore', '--ew-ac', 'Frecce', true),
        ewx_col('frecce_sfondo', 'Sfondo', '--ew-abg', 'Frecce', true),
        ewx_var('pallini_dimensione', 'Dimensione', '--ew-pdim', 'Pallini', ['d' => ['v' => 8, 'u' => 'px']]),
        ewx_col('pallini_colore', 'Colore', '--ew-pc', 'Pallini'),
        ewx_col('pallini_attivo', 'Colore del pallino attivo', '--ew-pca', 'Pallini'),
    ];
}
function ewx_carosello_html(array $v, array $ctx, array $voci, string $classe = ''): string {
    ewx_css_var($ctx, '--ew-n', $v['per_vista']);
    $n = count($voci);
    $attr = ' data-ew-car data-scorri="' . ($v['scorri'] === 'vista' ? 'vista' : 'uno') . '" data-ciclo="' . ($v['ciclo'] ? 1 : 0) . '"' . ($v['autoplay'] ? ' data-auto="' . max(1000, (int)$v['intervallo']) . '" data-pausa="' . ($v['pausa_hover'] ? 1 : 0) . '"' : '');
    $out = '<div class="ew-carosello ew-frecce-' . h($v['frecce_posizione']) . ($classe !== '' ? ' ' . $classe : '') . '"' . $attr . ' role="region" aria-roledescription="carosello" aria-label="Carosello">';
    $out .= '<div class="ew-car-vista"><ul class="ew-car-traccia">';
    foreach ($voci as $i => $x) $out .= '<li class="ew-car-voce" role="group" aria-roledescription="elemento" aria-label="' . ($i + 1) . ' di ' . $n . '">' . $x . '</li>';
    $out .= '</ul></div>';
    if ($v['frecce']) $out .= '<button type="button" class="ew-car-prev" aria-label="Precedente">' . icona_svg('chevron-sinistra') . '</button><button type="button" class="ew-car-next" aria-label="Successivo">' . icona_svg('chevron-destra') . '</button>';
    if ($v['pallini']) $out .= '<div class="ew-car-pallini" role="tablist" aria-label="Scegli l\'elemento"></div>';
    return $out . '</div>';
}
ewx_registra('carosello', 'Carosello di immagini', 'frecce-scambia', 'Gallerie', [
    ex('immagini', 'galleria', 'Immagini', [], 'contenuto', ['sezione' => 'Immagini']),
    ...ewx_controlli_carosello(),
    ...ewx_controlli_galleria_stile(),
], function (array $v, array $ctx): string {
    $imm = ewx_immagini($v['immagini']);
    if (!$imm) return ewx_vuoto('Aggiungi le immagini del carosello');
    $voci = array_map(fn($x) => ewx_gal_voce($x, $v, $ctx['anteprima']), $imm);
    return ewx_carosello_html($v, $ctx, $voci, 'ew-car-immagini ew-did-' . h($v['didascalia']) . ($v['effetto_hover'] !== '' ? ' ew-gal-hov-' . h($v['effetto_hover']) : '') . ($v['clic'] === 'lightbox' ? ' ew-car-lb' : ''));
});

// ---------------- Carosello di testimonianze
ewx_registra('carosello_testimonianze', 'Carosello di testimonianze', 'messaggi', 'Gallerie', array_merge([
    ec('voci', 'ripetitore', 'Testimonianze', [
        ['testo' => 'Puntuali, chiari, gentili. Lo consiglio a chiunque.', 'nome' => 'Nome Cognome', 'ruolo' => 'Cliente', 'immagine' => '', 'stelle' => 0],
        ['testo' => 'Hanno capito subito cosa ci serviva, e l\'hanno fatto meglio.', 'nome' => 'Nome Cognome', 'ruolo' => 'Cliente', 'immagine' => '', 'stelle' => 0],
    ], 'contenuto', ['campi' => [
        ec('testo', 'testolungo', 'Testo', 'Il testo della testimonianza.'), ec('nome', 'testo', 'Nome', 'Nome Cognome'), ec('ruolo', 'testo', 'Ruolo', ''), ec('immagine', 'immagine', 'Foto', ''), ec('stelle', 'numero', 'Stelle (0 = nessuna)', 0, 'contenuto', ['min' => 0, 'max' => 5]),
    ], 'titolo_voce' => 'nome', 'sezione' => 'Testimonianze']),
    ec('aspetto', 'scelta', 'Aspetto', 'riquadro', 'contenuto', ['opzioni' => ['riquadro' => 'Riquadro', 'bolla' => 'Fumetto'], 'sezione' => 'Testimonianze']),
], ewx_controlli_carosello(), ewx_controlli_testimonianza(false)), function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['testo'] ?? '')) !== '');
    if (!$voci) return ewx_vuoto('Aggiungi le testimonianze');
    $pos = in_array($v['posizione_foto'], ['sotto', 'sinistra', 'destra'], true) ? $v['posizione_foto'] : 'sopra';
    return ewx_carosello_html($v, $ctx, array_map(fn($x) => ewx_testimonianza_html($x, $pos, $v['aspetto'] === 'bolla'), array_values($voci)), 'ew-car-testim');
});

// ---------------- Fisarmonica (Accordion, con il Toggle dentro: "una sola aperta" spento)
function ewx_fisa_controlli(bool $unaSola): array {
    return [
        ec('voci', 'ripetitore', 'Voci', [['titolo' => 'Prima voce', 'contenuto' => '<p>Il contenuto della prima voce.</p>'], ['titolo' => 'Seconda voce', 'contenuto' => '<p>Il contenuto della seconda voce.</p>']], 'contenuto', ['campi' => [
            ec('titolo', 'testo', 'Titolo', 'Nuova voce'), ec('contenuto', 'ricco', 'Contenuto', '<p>Il contenuto della voce.</p>'),
        ], 'titolo_voce' => 'titolo', 'sezione' => 'Voci']),
        ewx_livello('h3', 'Livello dei titoli'),
        ec('prima_aperta', 'interruttore', 'Prima voce aperta', true, 'contenuto', ['sezione' => 'Comportamento']),
        ec('una_sola', 'interruttore', 'Una sola voce aperta per volta', $unaSola, 'contenuto', ['sezione' => 'Comportamento']),
        ec('schema_faq', 'interruttore', 'Dati per Google (FAQPage)', false, 'contenuto', ['sezione' => 'Comportamento', 'aiuto' => 'Accendilo solo se le voci sono domande e risposte vere.']),
        ex('icona', 'icona', 'Icona da chiusa', 'piu', 'contenuto', ['sezione' => 'Icona']),
        ex('icona_aperta', 'icona', 'Icona da aperta', 'meno', 'contenuto', ['sezione' => 'Icona']),
        ec('icona_posizione', 'scelta', 'Posizione dell\'icona', 'destra', 'contenuto', ['opzioni' => ['destra' => 'A destra', 'sinistra' => 'A sinistra'], 'sezione' => 'Icona']),
        ex('voce_bordo', 'bordo', 'Bordo e angoli delle voci', [], 'stile', ['selettore' => ' .ew-fisa-voce', 'sezione' => 'Voci']),
        ewx_var('voce_spazio', 'Spazio fra le voci', '--ew-gap', 'Voci', ['d' => ['v' => 10, 'u' => 'px']]),
        ewx_col('titolo_sfondo', 'Sfondo', '--ew-tbg', 'Titolo', true),
        ewx_col('titolo_colore', 'Colore', '--ew-tc', 'Titolo', true),
        ewx_col('titolo_colore_attivo', 'Colore da aperta', '--ew-tca', 'Titolo'),
        ewx_col('titolo_sfondo_attivo', 'Sfondo da aperta', '--ew-tbga', 'Titolo'),
        ewx_tip('titolo_tipografia', 'Tipografia', ' .ew-fisa-titolo', 'Titolo'),
        ex('titolo_padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-fisa-titolo', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Titolo']),
        ewx_col('icona_colore', 'Colore', '--ew-c1', 'Icona'),
        ewx_col('icona_colore_attivo', 'Colore da aperta', '--ew-c1a', 'Icona'),
        ewx_var('icona_dimensione', 'Dimensione', '--ew-dim', 'Icona', ['d' => ['v' => 20, 'u' => 'px']]),
        ewx_col('contenuto_sfondo', 'Sfondo', '--ew-cbg', 'Contenuto'),
        ewx_col('contenuto_colore', 'Colore', '--ew-cc', 'Contenuto'),
        ewx_tip('contenuto_tipografia', 'Tipografia', ' .ew-fisa-corpo', 'Contenuto'),
        ex('contenuto_padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-fisa-corpo', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Contenuto']),
    ];
}
function ewx_fisa_html(array $v, array $ctx): string {
    $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['titolo'] ?? '')) !== '');
    if (!$voci) return ewx_vuoto('Aggiungi le voci');
    $tag = ewx_tag($v);
    $ic = ewx_ic((string)$v['icona']) ?: icona_svg('piu');
    $ica = ewx_ic((string)$v['icona_aperta']) ?: icona_svg('meno');
    $out = '<div class="ew-fisa ew-icona-' . ($v['icona_posizione'] === 'sinistra' ? 'sinistra' : 'destra') . '" data-ew-fisa' . ($v['una_sola'] ? ' data-una="1"' : '') . '>';
    $i = 0;
    foreach ($voci as $x) {
        $out .= '<details class="ew-fisa-voce"' . ($i++ === 0 && $v['prima_aperta'] ? ' open' : '') . '><summary class="ew-fisa-titolo"><span class="ew-fisa-ic" aria-hidden="true"><span class="ew-fisa-ic-chiusa">' . $ic . '</span><span class="ew-fisa-ic-aperta">' . $ica . '</span></span>'
            . "<$tag class=\"ew-fisa-h\">" . h($x['titolo']) . "</$tag></summary><div class=\"ew-fisa-corpo\">" . editor_ricco_pulito((string)($x['contenuto'] ?? '')) . '</div></details>';
    }
    return $out . '</div>';
}
ewx_registra('fisarmonica', 'Fisarmonica', 'chevron-doppio-giu', 'Interazione', ewx_fisa_controlli(true), 'ewx_fisa_html');
ewx_registra('alterna', 'Interruttore (toggle)', 'elenco', 'Interazione', ewx_fisa_controlli(false), 'ewx_fisa_html');

// ---------------- Schede (Tabs)
ewx_registra('schede_tab', 'Schede (tab)', 'cartella', 'Interazione', [
    ec('voci', 'ripetitore', 'Schede', [['titolo' => 'Prima scheda', 'icona' => '', 'contenuto' => '<p>Il contenuto della prima scheda.</p>'], ['titolo' => 'Seconda scheda', 'icona' => '', 'contenuto' => '<p>Il contenuto della seconda scheda.</p>']], 'contenuto', ['campi' => [
        ec('titolo', 'testo', 'Titolo', 'Nuova scheda'), ex('icona', 'icona', 'Icona (facoltativa)', ''), ec('contenuto', 'ricco', 'Contenuto', '<p>Il contenuto della scheda.</p>'),
    ], 'titolo_voce' => 'titolo', 'sezione' => 'Schede']),
    ec('direzione', 'scelta', 'Direzione', 'orizzontale', 'contenuto', ['opzioni' => ['orizzontale' => 'Linguette sopra', 'verticale' => 'Linguette a sinistra'], 'sezione' => 'Disposizione']),
    ec('allineamento_linguette', 'scelta', 'Allineamento delle linguette', 'inizio', 'contenuto', ['opzioni' => ['inizio' => 'Inizio', 'centro' => 'Centro', 'fine' => 'Fine', 'giustificato' => 'Tutta la larghezza'], 'sezione' => 'Disposizione']),
    ec('su_telefono', 'scelta', 'Su telefono', 'schede', 'contenuto', ['opzioni' => ['schede' => 'Restano schede (scorrono)', 'fisarmonica' => 'Diventano una fisarmonica'], 'sezione' => 'Disposizione']),
    ec('attiva', 'numero', 'Scheda aperta all\'inizio', 1, 'contenuto', ['min' => 1, 'max' => 40, 'sezione' => 'Disposizione']),
    ewx_col('linguetta_colore', 'Colore', '--ew-tc', 'Linguette', true),
    ewx_col('linguetta_sfondo', 'Sfondo', '--ew-tbg', 'Linguette', true),
    ewx_col('linguetta_colore_attiva', 'Colore della scheda attiva', '--ew-tca', 'Linguette'),
    ewx_col('linguetta_sfondo_attiva', 'Sfondo della scheda attiva', '--ew-tbga', 'Linguette'),
    ewx_col('linguetta_bordo', 'Colore del bordo', '--ew-bc', 'Linguette'),
    ewx_var('linguetta_bordo_spessore', 'Spessore del bordo', '--ew-bw', 'Linguette', ['d' => ['v' => 1, 'u' => 'px']]),
    ewx_tip('linguetta_tipografia', 'Tipografia', ' .ew-tab-pulsante', 'Linguette'),
    ex('linguetta_padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-tab-pulsante', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Linguette']),
    ewx_var('linguetta_spazio', 'Spazio fra le linguette', '--ew-gap', 'Linguette', ['d' => ['v' => 4, 'u' => 'px']]),
    ewx_col('contenuto_colore', 'Colore', '--ew-cc', 'Contenuto'),
    ewx_col('contenuto_sfondo', 'Sfondo', '--ew-cbg', 'Contenuto'),
    ewx_tip('contenuto_tipografia', 'Tipografia', ' .ew-tab-pannello', 'Contenuto'),
    ex('contenuto_padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-tab-pannello', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Contenuto']),
], function (array $v, array $ctx): string {
    $voci = array_values(array_filter((array)$v['voci'], fn($x) => trim((string)($x['titolo'] ?? '')) !== ''));
    if (!$voci) return ewx_vuoto('Aggiungi le schede');
    $att = max(0, min(count($voci) - 1, (int)$v['attiva'] - 1));
    $id = 'ewt-' . $ctx['id'];
    $out = '<div class="ew-tab ew-tab-' . ($v['direzione'] === 'verticale' ? 'verticale' : 'orizzontale') . ' ew-tab-al-' . h($v['allineamento_linguette']) . ($v['su_telefono'] === 'fisarmonica' ? ' ew-tab-m-fisa' : '') . '" data-ew-tab>';
    $lista = '<div class="ew-tab-lista" role="tablist"' . ($v['direzione'] === 'verticale' ? ' aria-orientation="vertical"' : '') . '>';
    $pannelli = '<div class="ew-tab-pannelli">';
    foreach ($voci as $i => $x) {
        $on = $i === $att;
        $ic = ewx_ic((string)($x['icona'] ?? ''));
        $lista .= '<button type="button" role="tab" class="ew-tab-pulsante' . ($on ? ' attivo' : '') . '" id="' . $id . '-t' . $i . '" aria-selected="' . ($on ? 'true' : 'false') . '" aria-controls="' . $id . '-p' . $i . '" tabindex="' . ($on ? '0' : '-1') . '">' . $ic . '<span>' . h($x['titolo']) . '</span></button>';
        $pannelli .= '<div role="tabpanel" class="ew-tab-pannello' . ($on ? ' attivo' : '') . '" id="' . $id . '-p' . $i . '" aria-labelledby="' . $id . '-t' . $i . '" tabindex="0"' . ($on ? '' : ' hidden') . '>'
            . '<button type="button" class="ew-tab-mobile" aria-expanded="' . ($on ? 'true' : 'false') . '">' . $ic . '<span>' . h($x['titolo']) . '</span>' . icona_svg('chevron-giu') . '</button>'
            . '<div class="ew-tab-corpo">' . editor_ricco_pulito((string)($x['contenuto'] ?? '')) . '</div></div>';
    }
    return $out . $lista . '</div>' . $pannelli . '</div></div>';
});

// ---------------- Icone social (Social Icons)
const EWX_SOCIAL_LINK = ['email' => 'mailto:', 'telefono' => 'tel:'];
ewx_registra('social', 'Icone social', 'condividi', 'Marketing', [
    ec('voci', 'ripetitore', 'Profili', [['rete' => 'linkedin', 'link' => 'https://www.linkedin.com/', 'etichetta' => ''], ['rete' => 'instagram', 'link' => 'https://www.instagram.com/', 'etichetta' => ''], ['rete' => 'facebook', 'link' => 'https://www.facebook.com/', 'etichetta' => '']], 'contenuto', ['campi' => [
        ec('rete', 'scelta', 'Rete', 'linkedin', 'contenuto', ['opzioni' => EWX_SOCIAL]), ec('link', 'testo', 'Link (per email e telefono basta l\'indirizzo o il numero)', ''), ec('etichetta', 'testo', 'Etichetta per chi non vede (vuota: il nome della rete)', ''),
    ], 'titolo_voce' => 'rete', 'sezione' => 'Profili']),
    ec('forma', 'scelta', 'Forma', 'tonda', 'contenuto', ['opzioni' => ['tonda' => 'Tonda', 'arrotondata' => 'Arrotondata', 'quadrata' => 'Quadrata'], 'sezione' => 'Aspetto']),
    ec('colori', 'scelta', 'Colori', 'ufficiali', 'contenuto', ['opzioni' => ['ufficiali' => 'Ufficiali di ogni rete', 'personalizzati' => 'Personalizzati (dalla scheda Stile)'], 'sezione' => 'Aspetto']),
    ewx_colonne(0),
    ewx_allinea('allineamento', 'Aspetto', ''),
    ewx_var('dimensione', 'Dimensione dell\'icona', '--ew-dim', 'Icone', ['d' => ['v' => 20, 'u' => 'px']]),
    ewx_var('padding', 'Spazio intorno all\'icona', '--ew-pad', 'Icone', ['d' => ['v' => 10, 'u' => 'px']]),
    ewx_var('spazio', 'Spazio fra le icone', '--ew-gap', 'Icone', ['d' => ['v' => 10, 'u' => 'px']]),
    ewx_col('colore_primario', 'Colore principale (sfondo)', '--ew-c1', 'Colori personalizzati', true, ['condizione' => ['colori' => 'personalizzati']]),
    ewx_col('colore_secondario', 'Colore secondario (icona)', '--ew-c2', 'Colori personalizzati', true, ['condizione' => ['colori' => 'personalizzati']]),
    ex('bordo', 'bordo', 'Bordo', [], 'stile', ['selettore' => ' .ew-social-voce', 'sezione' => 'Icone', 'stati' => true]),
    ewx_hover(),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $voci = array_filter((array)$v['voci'], fn($x) => isset(EWX_SOCIAL[$x['rete'] ?? '']));
    if (!$voci) return ewx_vuoto('Aggiungi i profili social');
    $out = '<div class="ew-social ew-forma-' . h($v['forma']) . ' ew-col-' . ($v['colori'] === 'personalizzati' ? 'propri' : 'ufficiali') . (ewx_num($v['colonne']) > 0 ? ' ew-social-griglia' : '') . '">';
    if (ewx_num($v['colonne']) > 0) ewx_css_colonne($ctx, ' .ew-social', $v['colonne']);
    foreach ($voci as $x) {
        $rete = $x['rete'];
        $url = trim((string)($x['link'] ?? ''));
        if (isset(EWX_SOCIAL_LINK[$rete]) && $url !== '' && !str_contains($url, ':')) $url = EWX_SOCIAL_LINK[$rete] . $url;
        $url = editor_link_sicuro($url) ?: '#';
        $eti = trim((string)($x['etichetta'] ?? '')) ?: EWX_SOCIAL[$rete];
        $out .= '<a class="ew-social-voce ew-social-' . h($rete) . ($v['animazione_hover'] !== '' ? ' ew-hov-' . h($v['animazione_hover']) : '') . '" href="' . h($url) . '"' . (str_starts_with($url, 'http') ? ' target="_blank" rel="noopener"' : '') . ' aria-label="' . h($eti) . '" title="' . h($eti) . '">' . icona_svg($rete) . '</a>';
    }
    return $out . '</div>';
});

// ---------------- Condividi (Share Buttons)
const EWX_CONDIVIDI = ['facebook' => 'Facebook', 'x-social' => 'X', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'email' => 'Email', 'copia' => 'Copia il link', 'stampa' => 'Stampa'];
ewx_registra('condividi', 'Condividi', 'invia', 'Marketing', [
    ec('voci', 'ripetitore', 'Reti', [['rete' => 'linkedin', 'etichetta' => ''], ['rete' => 'whatsapp', 'etichetta' => ''], ['rete' => 'x-social', 'etichetta' => ''], ['rete' => 'email', 'etichetta' => ''], ['rete' => 'copia', 'etichetta' => '']], 'contenuto', ['campi' => [
        ec('rete', 'scelta', 'Rete', 'linkedin', 'contenuto', ['opzioni' => EWX_CONDIVIDI]), ec('etichetta', 'testo', 'Etichetta (vuota: il nome della rete)', ''),
    ], 'titolo_voce' => 'rete', 'sezione' => 'Reti']),
    ec('vista', 'scelta', 'Vista', 'icona_testo', 'contenuto', ['opzioni' => ['icona_testo' => 'Icona e testo', 'icona' => 'Solo icona', 'testo' => 'Solo testo'], 'sezione' => 'Aspetto']),
    ec('aspetto', 'scelta', 'Aspetto', 'piatto', 'contenuto', ['opzioni' => ['piatto' => 'Piatto', 'minimale' => 'Minimale', 'cornice' => 'Con cornice', 'riquadro' => 'Riquadro'], 'sezione' => 'Aspetto']),
    ec('forma', 'scelta', 'Forma', 'arrotondata', 'contenuto', ['opzioni' => ['tonda' => 'Tonda', 'arrotondata' => 'Arrotondata', 'quadrata' => 'Quadrata'], 'sezione' => 'Aspetto']),
    ec('colori', 'scelta', 'Colori', 'ufficiali', 'contenuto', ['opzioni' => ['ufficiali' => 'Ufficiali di ogni rete', 'personalizzati' => 'Personalizzati (dalla scheda Stile)'], 'sezione' => 'Aspetto']),
    ewx_colonne(0),
    ewx_allinea('allineamento', 'Aspetto', ''),
    ec('url', 'testo', 'Indirizzo da condividere (vuoto: questa pagina)', '', 'contenuto', ['sezione' => 'Aspetto']),
    ec('titolo_condivisione', 'testo', 'Testo che accompagna il link (vuoto: il titolo della pagina)', '', 'contenuto', ['sezione' => 'Aspetto']),
    ewx_var('dimensione', 'Dimensione dell\'icona', '--ew-dim', 'Pulsanti', ['d' => ['v' => 18, 'u' => 'px']]),
    ewx_var('spazio', 'Spazio fra i pulsanti', '--ew-gap', 'Pulsanti', ['d' => ['v' => 10, 'u' => 'px']]),
    ex('padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-cond-voce', 'proprieta' => 'padding', 'responsive' => true, 'sezione' => 'Pulsanti']),
    ewx_col('colore_primario', 'Colore principale', '--ew-c1', 'Colori personalizzati', true, ['condizione' => ['colori' => 'personalizzati']]),
    ewx_col('colore_secondario', 'Colore secondario', '--ew-c2', 'Colori personalizzati', true, ['condizione' => ['colori' => 'personalizzati']]),
    ewx_tip('tipografia', 'Tipografia', ' .ew-cond-voce', 'Pulsanti'),
    ewx_hover(),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $voci = array_filter((array)$v['voci'], fn($x) => isset(EWX_CONDIVIDI[$x['rete'] ?? '']));
    if (!$voci) return ewx_vuoto('Scegli le reti su cui condividere');
    $url = editor_link_sicuro(trim((string)$v['url'])) ?: ewx_url_pagina($ctx);
    $titolo = trim((string)$v['titolo_condivisione']) ?: (function_exists('editor_contesto') ? (string)editor_contesto('titolo', '') : '');
    $eu = rawurlencode($url); $et = rawurlencode($titolo);
    $href = ['facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $eu, 'x-social' => 'https://twitter.com/intent/tweet?url=' . $eu . ($et ? '&text=' . $et : ''), 'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $eu,
        'whatsapp' => 'https://api.whatsapp.com/send?text=' . ($et ? $et . '%20' : '') . $eu, 'telegram' => 'https://t.me/share/url?url=' . $eu . ($et ? '&text=' . $et : ''), 'email' => 'mailto:?subject=' . $et . '&body=' . $eu];
    $icone = ['copia' => 'link', 'stampa' => 'stampante'];
    $out = '<div class="ew-condividi ew-vista-' . h($v['vista']) . ' ew-asp-' . h($v['aspetto']) . ' ew-forma-' . h($v['forma']) . ' ew-col-' . ($v['colori'] === 'personalizzati' ? 'propri' : 'ufficiali') . (ewx_num($v['colonne']) > 0 ? ' ew-cond-griglia' : '') . '" data-ew-condividi data-url="' . h($url) . '">';
    if (ewx_num($v['colonne']) > 0) ewx_css_colonne($ctx, ' .ew-condividi', $v['colonne']);
    foreach ($voci as $x) {
        $r = $x['rete'];
        $eti = trim((string)($x['etichetta'] ?? '')) ?: EWX_CONDIVIDI[$r];
        $cl = 'ew-cond-voce ew-social-' . h($r) . ($v['animazione_hover'] !== '' ? ' ew-hov-' . h($v['animazione_hover']) : '');
        $corpo = icona_svg($icone[$r] ?? $r) . '<span class="ew-cond-testo">' . h($eti) . '</span>';
        if ($r === 'copia' || $r === 'stampa') $out .= '<button type="button" class="' . $cl . '" data-ew-' . $r . ' aria-label="' . h($eti) . '">' . $corpo . '</button>';
        else $out .= '<a class="' . $cl . '" href="' . h($href[$r]) . '" target="_blank" rel="noopener nofollow" aria-label="' . h($eti) . '">' . $corpo . '</a>';
    }
    return $out . '</div>';
});
