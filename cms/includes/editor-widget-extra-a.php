<?php
/** Widget extra, parte A: icona, riquadri, pulsante, elenco con icone, valutazione, contatore, barra, testimonianza. */

// ---------------- Icona
ewx_registra('icona', 'Icona', 'stella', 'Elementi', [
    ex('icona', 'icona', 'Icona', 'stella', 'contenuto', ['sezione' => 'Icona']),
    ...ewx_cornice_icona(),
    ewx_link(),
    ewx_allinea('allineamento', 'Icona', 'center'),
    ...ewx_stile_icona('Icona', 50),
    ewx_hover(),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $ic = ewx_icona_html($v);
    if ($ic === '') return ewx_vuoto('Scegli un\'icona');
    [$a, $z] = ewx_a($v['link'], 'ew-icona-link' . ($v['animazione_hover'] !== '' ? ' ew-hov-' . h($v['animazione_hover']) : ''), 'span');
    return '<div class="ew-icona">' . $a . $ic . $z . '</div>';
});

// ---------------- Riquadro con icona (Icon Box)
ewx_registra('riquadro_icona', 'Riquadro con icona', 'lampadina', 'Elementi', [
    ex('icona', 'icona', 'Icona', 'lampadina', 'contenuto', ['sezione' => 'Icona']),
    ...ewx_cornice_icona(),
    ec('titolo', 'testo', 'Titolo', 'Titolo del riquadro', 'contenuto', ['sezione' => 'Testi']),
    ec('testo', 'testolungo', 'Testo', 'Due righe che spiegano il punto. Brevi, concrete.', 'contenuto', ['sezione' => 'Testi']),
    ewx_livello('h3'),
    ewx_link(),
    ec('posizione', 'scelta', 'Posizione dell\'icona', 'sopra', 'contenuto', ['opzioni' => ['sopra' => 'Sopra', 'sinistra' => 'A sinistra', 'destra' => 'A destra'], 'sezione' => 'Disposizione']),
    ec('icona_in_alto', 'scelta', 'Icona allineata', 'inizio', 'contenuto', ['opzioni' => ['inizio' => 'In alto', 'center' => 'Al centro'], 'condizione' => ['!posizione' => 'sopra'], 'sezione' => 'Disposizione']),
    ewx_allinea('allineamento', 'Disposizione', 'center'),
    ...ewx_stile_icona('Icona', 44),
    ewx_var('icona_spazio', 'Spazio fra icona e testo', '--ew-gap', 'Icona', ['d' => ['v' => 16, 'u' => 'px']]),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-ribox-titolo', '--ew-tc', true),
    ewx_var('titolo_spazio', 'Spazio sotto il titolo', '--ew-tgap', 'Titolo', ['d' => ['v' => 8, 'u' => 'px']]),
    ...ewx_testo_stile('testo', 'Testo', ' .ew-ribox-testo', '--ew-dc'),
    ...ewx_riquadro_stile(),
    ewx_hover(),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $tag = ewx_tag($v);
    $pos = in_array($v['posizione'], ['sinistra', 'destra'], true) ? $v['posizione'] : 'sopra';
    [$a, $z] = ewx_a($v['link'], 'ew-ribox-link', 'span');
    $ic = ewx_icona_html($v);
    $out = '<div class="ew-ribox ew-pos-' . $pos . ($v['icona_in_alto'] === 'center' ? ' ew-ic-centro' : '') . ($v['animazione_hover'] !== '' ? ' ew-hov-' . h($v['animazione_hover']) : '') . '">';
    if ($ic !== '') $out .= '<div class="ew-ribox-icona">' . $a . $ic . $z . '</div>';
    $out .= '<div class="ew-ribox-corpo">';
    if (trim($v['titolo']) !== '') $out .= "<$tag class=\"ew-ribox-titolo\">" . $a . h($v['titolo']) . $z . "</$tag>";
    if (trim($v['testo']) !== '') $out .= '<div class="ew-ribox-testo">' . editor_paragrafi($v['testo']) . '</div>';
    return $out . '</div></div>';
});

// ---------------- Riquadro con immagine (Image Box)
ewx_registra('riquadro_immagine', 'Riquadro con immagine', 'immagine', 'Elementi', [
    ec('immagine', 'immagine', 'Immagine', '', 'contenuto', ['sezione' => 'Immagine']),
    ec('alt', 'testo', 'Testo alternativo', '', 'contenuto', ['sezione' => 'Immagine']),
    ec('titolo', 'testo', 'Titolo', 'Titolo del riquadro', 'contenuto', ['sezione' => 'Testi']),
    ec('testo', 'testolungo', 'Testo', 'Due righe che spiegano il punto.', 'contenuto', ['sezione' => 'Testi']),
    ewx_livello('h3'),
    ewx_link(),
    ec('posizione', 'scelta', 'Posizione dell\'immagine', 'sopra', 'contenuto', ['opzioni' => ['sopra' => 'Sopra', 'sinistra' => 'A sinistra', 'destra' => 'A destra'], 'sezione' => 'Disposizione']),
    ec('verticale', 'scelta', 'Allineamento verticale', 'inizio', 'contenuto', ['opzioni' => ['inizio' => 'In alto', 'center' => 'Al centro', 'fine' => 'In basso'], 'condizione' => ['!posizione' => 'sopra'], 'sezione' => 'Disposizione']),
    ewx_allinea('allineamento', 'Disposizione', 'center'),
    ewx_var('immagine_larghezza', 'Larghezza dell\'immagine', '--ew-imgw', 'Immagine', ['d' => ['v' => 100, 'u' => '%']], ['unita' => ['%', 'px'], 'intervalli' => ['%' => [5, 100, 1], 'px' => [20, 800, 1]]]),
    ewx_var('immagine_spazio', 'Spazio fra immagine e testo', '--ew-gap', 'Immagine', ['d' => ['v' => 16, 'u' => 'px']]),
    ex('immagine_bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' img', 'sezione' => 'Immagine']),
    ec('immagine_opacita_hover', 'numero', 'Opacita al passaggio (%)', '', 'stile', ['min' => 0, 'max' => 100, 'unita' => '%', 'selettore' => '', 'proprieta' => '--ew-imgo', 'sezione' => 'Immagine']),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-ribox-titolo', '--ew-tc', true),
    ewx_var('titolo_spazio', 'Spazio sotto il titolo', '--ew-tgap', 'Titolo', ['d' => ['v' => 8, 'u' => 'px']]),
    ...ewx_testo_stile('testo', 'Testo', ' .ew-ribox-testo', '--ew-dc'),
    ...ewx_riquadro_stile(),
    ewx_hover(),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $tag = ewx_tag($v);
    $pos = in_array($v['posizione'], ['sinistra', 'destra'], true) ? $v['posizione'] : 'sopra';
    [$a, $z] = ewx_a($v['link'], 'ew-ribox-link', 'span');
    $out = '<div class="ew-ribox ew-imgbox ew-pos-' . $pos . ' ew-v-' . h($v['verticale']) . ($v['animazione_hover'] !== '' ? ' ew-hov-' . h($v['animazione_hover']) : '') . '">';
    if ($v['immagine'] !== '') $out .= '<figure class="ew-ribox-immagine">' . $a . ewx_img($v['immagine'], $v['alt'] ?: $v['titolo']) . $z . '</figure>';
    else $out .= '<div class="ed-vuoto">Scegli un\'immagine</div>';
    $out .= '<div class="ew-ribox-corpo">';
    if (trim($v['titolo']) !== '') $out .= "<$tag class=\"ew-ribox-titolo\">" . $a . h($v['titolo']) . $z . "</$tag>";
    if (trim($v['testo']) !== '') $out .= '<div class="ew-ribox-testo">' . editor_paragrafi($v['testo']) . '</div>';
    return $out . '</div></div>';
});

// ---------------- Pulsante singolo (Button)
ewx_registra('pulsante', 'Pulsante', 'freccia-tonda-destra', 'Elementi', [
    ec('testo', 'testo', 'Testo', 'Clicca qui', 'contenuto', ['sezione' => 'Pulsante']),
    ewx_link(),
    ec('stile', 'scelta', 'Stile', 'viola', 'contenuto', ['opzioni' => EDITOR_STILI_PULSANTE + ['neutro' => 'Neutro (dai colori sotto)'], 'sezione' => 'Pulsante']),
    ec('dimensione', 'scelta', 'Dimensione', 'md', 'contenuto', ['opzioni' => ['xs' => 'Molto piccolo', 'sm' => 'Piccolo', 'md' => 'Medio', 'lg' => 'Grande', 'xl' => 'Molto grande'], 'sezione' => 'Pulsante']),
    ex('icona', 'icona', 'Icona', '', 'contenuto', ['sezione' => 'Icona']),
    ec('icona_posizione', 'scelta', 'Posizione dell\'icona', 'dopo', 'contenuto', ['opzioni' => ['prima' => 'Prima del testo', 'dopo' => 'Dopo il testo'], 'condizione' => ['!icona' => ''], 'sezione' => 'Icona']),
    ewx_var('icona_spazio', 'Spazio fra icona e testo', '--ew-gap', 'Icona', '', ['condizione' => ['!icona' => '']]),
    ewx_allinea('allineamento', 'Disposizione', ''),
    ec('pieno', 'interruttore', 'Largo quanto la colonna', false, 'contenuto', ['sezione' => 'Disposizione']),
    ec('id_pulsante', 'testo', 'ID del pulsante (per i moduli e le statistiche)', '', 'avanzate'),
    ewx_tip('pulsante_tipografia', 'Tipografia', ' .ew-btn', 'Pulsante'),
    ewx_col('pulsante_colore', 'Colore del testo', '--ew-bc', 'Pulsante', true),
    ex('pulsante_sfondo', 'sfondo', 'Sfondo', [], 'stile', ['selettore' => ' .ew-btn', 'sezione' => 'Pulsante', 'stati' => true]),
    ex('pulsante_bordo', 'bordo', 'Bordo e angoli', [], 'stile', ['selettore' => ' .ew-btn', 'sezione' => 'Pulsante', 'stati' => true]),
    ex('pulsante_ombra', 'ombra', 'Ombra', [], 'stile', ['selettore' => ' .ew-btn', 'sezione' => 'Pulsante', 'stati' => true]),
    ex('pulsante_padding', 'dimensioni', 'Spazio interno', [], 'stile', ['selettore' => ' .ew-btn', 'sezione' => 'Pulsante', 'proprieta' => 'padding', 'responsive' => true]),
    ewx_hover(),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $st = array_key_exists($v['stile'], EDITOR_STILI_PULSANTE) ? $v['stile'] : ($v['stile'] === 'neutro' ? 'neutro' : 'viola');
    $dim = in_array($v['dimensione'], ['xs', 'sm', 'md', 'lg', 'xl'], true) ? $v['dimensione'] : 'md';
    $ic = ewx_ic((string)$v['icona']);
    $cl = 'btn btn-' . $st . ' ew-btn ew-btn-' . $dim . ($v['animazione_hover'] !== '' ? ' ew-hov-' . h($v['animazione_hover']) : '');
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$v['id_pulsante']);
    $link = $v['link'];
    if (editor_link_sicuro((string)($link['url'] ?? '')) === '') $link = ['url' => '#', 'nuova' => false];
    [$a, $z] = ewx_a($link, $cl, 'a', $id !== '' ? ' id="' . $id . '"' : '');
    $testo = '<span class="ew-btn-testo">' . h($v['testo']) . '</span>';
    $corpo = $ic !== '' ? ($v['icona_posizione'] === 'prima' ? $ic . $testo : $testo . $ic) : $testo;
    return '<div class="ew-pulsante' . ($v['pieno'] ? ' ew-pieno' : '') . '">' . $a . $corpo . $z . '</div>';
});

// ---------------- Elenco con icone (Icon List)
ewx_registra('elenco_icone', 'Elenco con icone', 'lista-spunte', 'Elementi', [
    ex('voci', 'ripetitore', 'Voci', [['testo' => 'Prima voce', 'icona' => 'spunta', 'link' => ''], ['testo' => 'Seconda voce', 'icona' => 'spunta', 'link' => ''], ['testo' => 'Terza voce', 'icona' => 'spunta', 'link' => '']], 'contenuto', ['campi' => [
        ec('testo', 'testo', 'Testo', 'Nuova voce'), ex('icona', 'icona', 'Icona', 'spunta'), ec('link', 'testo', 'Link (facoltativo)', ''), ec('nuova', 'interruttore', 'Apri in una nuova scheda', false),
    ], 'titolo_voce' => 'testo', 'sezione' => 'Elenco']),
    ec('disposizione', 'scelta', 'Disposizione', 'verticale', 'contenuto', ['opzioni' => ['verticale' => 'Una sotto l\'altra', 'inlinea' => 'In riga'], 'sezione' => 'Elenco']),
    ec('divisore', 'interruttore', 'Divisore fra le voci', false, 'contenuto', ['sezione' => 'Elenco']),
    ewx_var('spazio', 'Spazio fra le voci', '--ew-gap', 'Elenco', ['d' => ['v' => 10, 'u' => 'px']]),
    ewx_allinea('allineamento', 'Elenco', ''),
    ec('divisore_stile', 'scelta', 'Stile del divisore', 'solid', 'stile', ['opzioni' => ['solid' => 'Linea', 'dotted' => 'Puntini', 'dashed' => 'Tratteggio', 'double' => 'Doppia'], 'selettore' => '', 'proprieta' => '--ew-ds', 'condizione' => ['divisore' => true], 'sezione' => 'Elenco']),
    ewx_col('divisore_colore', 'Colore del divisore', '--ew-dcol', 'Elenco', false, ['condizione' => ['divisore' => true]]),
    ewx_col('icona_colore', 'Colore', '--ew-c1', 'Icona', true),
    ewx_var('icona_dimensione', 'Dimensione', '--ew-dim', 'Icona', ['d' => ['v' => 18, 'u' => 'px']]),
    ewx_var('icona_spazio', 'Spazio fra icona e testo', '--ew-igap', 'Icona', ['d' => ['v' => 10, 'u' => 'px']]),
    ec('icona_allineamento', 'scelta', 'Allineamento verticale dell\'icona', 'center', 'stile', ['opzioni' => ['flex-start' => 'In alto', 'center' => 'Al centro'], 'selettore' => '', 'proprieta' => '--ew-ialign', 'sezione' => 'Icona']),
    ewx_col('testo_colore', 'Colore', '--ew-tc', 'Testo', true),
    ewx_tip('testo_tipografia', 'Tipografia', ' .ew-ei-testo', 'Testo'),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['testo'] ?? '')) !== '');
    if (!$voci) return ewx_vuoto('Aggiungi le voci dell\'elenco');
    $out = '<ul class="ew-elenco-icone' . ($v['disposizione'] === 'inlinea' ? ' ew-inlinea' : '') . ($v['divisore'] ? ' ew-con-divisore' : '') . '">';
    foreach ($voci as $x) {
        [$a, $z] = ewx_a(['url' => $x['link'] ?? '', 'nuova' => !empty($x['nuova'])], 'ew-ei-voce', 'span');
        $out .= '<li>' . $a . (ewx_ic((string)($x['icona'] ?? '')) ?: '<span class="ew-ei-vuota"></span>') . '<span class="ew-ei-testo">' . h($x['testo']) . '</span>' . $z . '</li>';
    }
    return $out . '</ul>';
});

// ---------------- Valutazione (Star Rating)
ewx_registra('valutazione', 'Valutazione a stelle', 'stella-piena', 'Elementi', [
    ec('voto', 'numero', 'Voto', 5, 'contenuto', ['min' => 0, 'max' => 10, 'sezione' => 'Valutazione', 'aiuto' => 'Anche con la virgola: 4.5']),
    ec('scala', 'scelta', 'Scala', '5', 'contenuto', ['opzioni' => ['5' => 'Da 0 a 5', '10' => 'Da 0 a 10'], 'sezione' => 'Valutazione']),
    ex('icona', 'icona', 'Simbolo', 'stella-piena', 'contenuto', ['sezione' => 'Valutazione']),
    ec('titolo', 'testo', 'Titolo', '', 'contenuto', ['sezione' => 'Valutazione']),
    ec('titolo_posizione', 'scelta', 'Posizione del titolo', 'prima', 'contenuto', ['opzioni' => ['prima' => 'Prima delle stelle', 'dopo' => 'Dopo le stelle'], 'condizione' => ['!titolo' => ''], 'sezione' => 'Valutazione']),
    ewx_allinea('allineamento', 'Valutazione', ''),
    ewx_var('dimensione', 'Dimensione delle stelle', '--ew-dim', 'Stelle', ['d' => ['v' => 22, 'u' => 'px']]),
    ewx_var('spazio', 'Spazio fra le stelle', '--ew-gap', 'Stelle', ['d' => ['v' => 2, 'u' => 'px']]),
    ewx_col('colore_piene', 'Colore delle stelle piene', '--ew-c1', 'Stelle'),
    ewx_col('colore_vuote', 'Colore delle stelle vuote', '--ew-c2', 'Stelle'),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-voti-titolo', '--ew-tc'),
    ewx_var('titolo_spazio', 'Spazio fra titolo e stelle', '--ew-tgap', 'Titolo', ['d' => ['v' => 10, 'u' => 'px']]),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $scala = (int)$v['scala'] === 10 ? 10 : 5;
    $voto = max(0, min($scala, (float)$v['voto']));
    $icona = ewx_ic((string)$v['icona']) ?: icona_svg('stella-piena');
    $stelle = '';
    for ($i = 1; $i <= $scala; $i++) {
        $q = max(0, min(1, $voto - ($i - 1)));
        $stelle .= '<i class="ew-stella" style="--ew-q:' . round($q * 100) . '%"><span class="ew-stella-vuota">' . $icona . '</span><span class="ew-stella-piena">' . $icona . '</span></i>';
    }
    $eti = str_replace('.', ',', rtrim(rtrim(number_format($voto, 1, '.', ''), '0'), '.')) . ' su ' . $scala;
    $t = trim($v['titolo']) !== '' ? '<span class="ew-voti-titolo">' . h($v['titolo']) . '</span>' : '';
    $s = '<span class="ew-voti-stelle" role="img" aria-label="' . h($eti) . '">' . $stelle . '</span>';
    return '<div class="ew-voti">' . ($v['titolo_posizione'] === 'dopo' ? $s . $t : $t . $s) . '</div>';
});

// ---------------- Contatore (Counter)
ewx_registra('contatore', 'Contatore animato', 'calcolatrice', 'Numeri e tempo', [
    ec('da', 'numero', 'Parte da', 0, 'contenuto', ['min' => -1000000000, 'max' => 1000000000, 'sezione' => 'Numero']),
    ec('a', 'numero', 'Arriva a', 100, 'contenuto', ['min' => -1000000000, 'max' => 1000000000, 'sezione' => 'Numero']),
    ec('durata', 'numero', 'Durata (millisecondi)', 2000, 'contenuto', ['min' => 100, 'max' => 20000, 'sezione' => 'Numero']),
    ec('prefisso', 'testo', 'Prima del numero', '', 'contenuto', ['sezione' => 'Numero']),
    ec('suffisso', 'testo', 'Dopo il numero', '', 'contenuto', ['sezione' => 'Numero']),
    ec('migliaia', 'scelta', 'Separatore delle migliaia', '.', 'contenuto', ['opzioni' => ['' => 'Nessuno', '.' => 'Punto (1.000)', ' ' => 'Spazio (1 000)', ',' => 'Virgola (1,000)'], 'sezione' => 'Numero']),
    ec('titolo', 'testo', 'Titolo', 'Persone seguite', 'contenuto', ['sezione' => 'Numero']),
    ewx_allinea('allineamento', 'Numero', 'center'),
    ...ewx_testo_stile('numero', 'Numero', ' .ew-contatore-numero', '--ew-nc'),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-contatore-titolo', '--ew-tc'),
    ewx_var('titolo_spazio', 'Spazio fra numero e titolo', '--ew-tgap', 'Titolo', ['d' => ['v' => 6, 'u' => 'px']]),
], function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $a = (float)$v['a'];
    $fmt = fn($n) => number_format($n, 0, ',', (string)$v['migliaia']);
    return '<div class="ew-contatore"><div class="ew-contatore-numero">' . ($v['prefisso'] !== '' ? '<span class="ew-contatore-pre">' . h($v['prefisso']) . '</span>' : '')
        . '<span class="ew-num" data-ew-num data-da="' . (float)$v['da'] . '" data-a="' . $a . '" data-durata="' . (int)$v['durata'] . '" data-sep="' . h((string)$v['migliaia']) . '">' . $fmt($a) . '</span>'
        . ($v['suffisso'] !== '' ? '<span class="ew-contatore-suf">' . h($v['suffisso']) . '</span>' : '') . '</div>'
        . (trim($v['titolo']) !== '' ? '<div class="ew-contatore-titolo">' . h($v['titolo']) . '</div>' : '') . '</div>';
});

// ---------------- Barra di avanzamento (Progress Bar)
ewx_registra('barra_avanzamento', 'Barra di avanzamento', 'grafico-barre', 'Numeri e tempo', [
    ec('titolo', 'testo', 'Titolo', 'Il mio avanzamento', 'contenuto', ['sezione' => 'Barra']),
    ec('percentuale', 'numero', 'Percentuale', 60, 'contenuto', ['min' => 0, 'max' => 100, 'sezione' => 'Barra']),
    ec('mostra_percentuale', 'interruttore', 'Mostra la percentuale', true, 'contenuto', ['sezione' => 'Barra']),
    ec('testo_interno', 'testo', 'Testo dentro la barra', '', 'contenuto', ['sezione' => 'Barra']),
    ewx_col('colore_barra', 'Colore della barra', '--ew-c1', 'Barra'),
    ewx_col('colore_sfondo', 'Colore dello sfondo', '--ew-c2', 'Barra'),
    ewx_var('altezza', 'Altezza', '--ew-h', 'Barra', ['d' => ['v' => 28, 'u' => 'px']]),
    ewx_var('raggio', 'Angoli arrotondati', '--ew-r', 'Barra', ['d' => ['v' => 8, 'u' => 'px']]),
    ...ewx_testo_stile('interno', 'Testo dentro la barra', ' .ew-barra-interno, .ew-barra-pct', '--ew-ic'),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-barra-titolo', '--ew-tc'),
], function (array $v, array $ctx): string {
    $p = max(0, min(100, (int)$v['percentuale']));
    return '<div class="ew-barra">' . (trim($v['titolo']) !== '' ? '<span class="ew-barra-titolo">' . h($v['titolo']) . '</span>' : '')
        . '<div class="ew-barra-traccia" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $p . '"' . (trim($v['titolo']) !== '' ? ' aria-label="' . h($v['titolo']) . '"' : '') . '>'
        . '<div class="ew-barra-riempi" data-ew-barra style="--ew-p:' . $p . '%"><span class="ew-barra-interno">' . h($v['testo_interno']) . '</span>' . ($v['mostra_percentuale'] ? '<span class="ew-barra-pct">' . $p . '%</span>' : '') . '</div></div></div>';
});

// ---------------- Testimonianza (Testimonial)
function ewx_controlli_testimonianza(bool $singola = true): array {
    $c = [];
    if ($singola) {
        $c[] = ec('testo', 'testolungo', 'Testo', 'Ci hanno seguito dal primo giorno, con pazienza e idee chiare. Il risultato è andato oltre quello che speravamo.', 'contenuto', ['sezione' => 'Testimonianza']);
        $c[] = ec('immagine', 'immagine', 'Foto', '', 'contenuto', ['sezione' => 'Testimonianza']);
        $c[] = ec('nome', 'testo', 'Nome', 'Nome Cognome', 'contenuto', ['sezione' => 'Testimonianza']);
        $c[] = ec('ruolo', 'testo', 'Ruolo', 'Cliente', 'contenuto', ['sezione' => 'Testimonianza']);
        $c[] = ec('stelle', 'numero', 'Stelle (0 = nessuna)', 0, 'contenuto', ['min' => 0, 'max' => 5, 'sezione' => 'Testimonianza']);
    }
    return array_merge($c, [
        ec('posizione_foto', 'scelta', 'Posizione della foto', 'sopra', 'contenuto', ['opzioni' => ['sopra' => 'Sopra il testo', 'sotto' => 'Sotto il testo', 'sinistra' => 'A sinistra della firma', 'destra' => 'A destra della firma'], 'sezione' => 'Disposizione']),
        ewx_allinea('allineamento', 'Disposizione', 'center'),
        ...ewx_testo_stile('testo', 'Testo', ' .ew-testim-testo', '--ew-dc'),
        ewx_var('foto_dimensione', 'Dimensione della foto', '--ew-imgs', 'Foto', ['d' => ['v' => 64, 'u' => 'px']]),
        ex('foto_bordo', 'bordo', 'Bordo e angoli della foto', [], 'stile', ['selettore' => ' .ew-testim-foto img', 'sezione' => 'Foto']),
        ewx_var('foto_spazio', 'Spazio intorno alla foto', '--ew-gap', 'Foto', ['d' => ['v' => 14, 'u' => 'px']]),
        ...ewx_testo_stile('nome', 'Nome', ' .ew-testim-nome', '--ew-nc'),
        ...ewx_testo_stile('ruolo', 'Ruolo', ' .ew-testim-ruolo', '--ew-rc'),
        ewx_col('stelle_colore', 'Colore delle stelle', '--ew-c1', 'Stelle'),
        ...ewx_riquadro_stile(' .ew-testim'),
    ]);
}
function ewx_testimonianza_html(array $x, string $pos, bool $bolla = false): string {
    $foto = ($x['immagine'] ?? '') !== '' ? '<span class="ew-testim-foto">' . ewx_img($x['immagine'], (string)($x['nome'] ?? '')) . '</span>' : '';
    $stelle = '';
    $n = (int)($x['stelle'] ?? 0);
    if ($n > 0) { $s = icona_svg('stella-piena'); $stelle = '<span class="ew-testim-stelle" role="img" aria-label="' . $n . ' su 5">' . str_repeat($s, $n) . '</span>'; }
    $chi = '<span class="ew-testim-chi">' . (($x['nome'] ?? '') !== '' ? '<b class="ew-testim-nome">' . h($x['nome']) . '</b>' : '') . (($x['ruolo'] ?? '') !== '' ? '<span class="ew-testim-ruolo">' . h($x['ruolo']) . '</span>' : '') . '</span>';
    $firma = '<figcaption class="ew-testim-firma">' . ($pos === 'sinistra' || $pos === 'destra' ? $foto : '') . $chi . '</figcaption>';
    $testo = '<blockquote class="ew-testim-testo">' . $stelle . editor_paragrafi((string)($x['testo'] ?? '')) . '</blockquote>';
    $out = '<figure class="ew-testim ew-foto-' . $pos . ($bolla ? ' ew-testim-bolla' : '') . '">';
    if ($pos === 'sopra') $out .= $foto . $testo . $firma;
    elseif ($pos === 'sotto') $out .= $testo . $foto . $firma;
    else $out .= $testo . $firma;
    return $out . '</figure>';
}
ewx_registra('testimonianza', 'Testimonianza', 'citazione', 'Elementi', ewx_controlli_testimonianza(true), function (array $v, array $ctx): string {
    if (isset($v['allineamento'])) ewx_css_allinea($ctx, $v['allineamento']);
    $pos = in_array($v['posizione_foto'], ['sotto', 'sinistra', 'destra'], true) ? $v['posizione_foto'] : 'sopra';
    return ewx_testimonianza_html($v, $pos);
});
