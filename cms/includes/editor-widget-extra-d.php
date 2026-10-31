<?php
/**
 * Widget extra, parte D (1.10.0, scelti da Danilo fra le proposte dell'analisi dei widget): orari di apertura, barra dei
 * contatti per il telefono, recensioni, prima e dopo, squadra, slider, eventi. Stesso contratto delle parti A, B e C:
 * HTML con classi ew-*, aspetto in sito/assets/widget.css, comportamento in sito/assets/widget.js.
 * Niente servizi esterni: le recensioni si scrivono a mano, gli orari e gli eventi stanno nel widget.
 */

// ---------------- Orari di apertura
const EWX_GIORNI = ['lun' => 'Lunedì', 'mar' => 'Martedì', 'mer' => 'Mercoledì', 'gio' => 'Giovedì', 'ven' => 'Venerdì', 'sab' => 'Sabato', 'dom' => 'Domenica'];
const EWX_GIORNI_SCHEMA = ['lun' => 'Monday', 'mar' => 'Tuesday', 'mer' => 'Wednesday', 'gio' => 'Thursday', 'ven' => 'Friday', 'sab' => 'Saturday', 'dom' => 'Sunday'];

/**
 * Gli intervalli di un giorno, in minuti dalla mezzanotte: "9:00-13:00, 15:00-19:00", "9-13", "9.30 - 12.30", "24 ore".
 * "Chiuso", vuoto o un testo che non si legge: nessun intervallo (giorno chiuso).
 */
function ewx_orari_intervalli(string $testo): array {
    $t = mb_strtolower(trim($testo));
    if ($t === '' || str_contains($t, 'chius')) return [];
    if (preg_match('/24\s*(ore|h)|sempre aperto/u', $t)) return [[0, 1440]];
    $out = [];
    preg_match_all('/(\d{1,2})(?:[:.](\d{2}))?\s*(?:-|–|alle|a)\s*(\d{1,2})(?:[:.](\d{2}))?/u', $t, $m, PREG_SET_ORDER);
    foreach ($m as $x) {
        $da = min(24, (int)$x[1]) * 60 + min(59, (int)($x[2] ?? 0));
        $a = min(24, (int)$x[3]) * 60 + min(59, (int)($x[4] ?? 0));
        if ($a <= $da) $a += 1440; // oltre la mezzanotte (19:00-02:00)
        $out[] = [$da, min($a, 2880)];
    }
    return $out;
}

function ewx_ora(int $min): string {
    $min %= 1440;
    return sprintf('%d:%02d', intdiv($min, 60), $min % 60);
}

/** Le chiusure valide del widget: [da, a, motivo] con le date AAAA-MM-GG; "a" vuota vale un giorno. */
function ewx_orari_chiusure(array $v): array {
    $out = [];
    foreach ((array)($v['chiusure'] ?? []) as $c) {
        $da = trim((string)($c['dal'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $da)) continue;
        $a = trim((string)($c['al'] ?? ''));
        $out[] = [$da, preg_match('/^\d{4}-\d{2}-\d{2}$/', $a) && $a >= $da ? $a : $da, trim((string)($c['motivo'] ?? ''))];
    }
    return $out;
}

/** Aperto adesso? Ritorna [aperto, frase]. $ora è un DateTimeImmutable nel fuso del sito. */
function ewx_orari_stato(array $v, DateTimeImmutable $ora): array {
    $chiavi = array_keys(EWX_GIORNI);
    $oggi = $ora->format('Y-m-d');
    foreach (ewx_orari_chiusure($v) as [$da, $a, $motivo]) {
        if ($oggi >= $da && $oggi <= $a) return [false, trim(($v['testo_chiuso'] ?: 'Chiuso') . ($motivo !== '' ? ': ' . $motivo : '') . ($a > $oggi ? ', fino al ' . DateTimeImmutable::createFromFormat('Y-m-d', $a)->format('d/m') : ''))];
    }
    $g = (int)$ora->format('N') - 1; // 0 = lunedì
    $min = (int)$ora->format('G') * 60 + (int)$ora->format('i');
    // Ieri dopo mezzanotte (19:00-02:00) conta ancora.
    foreach (ewx_orari_intervalli((string)($v[$chiavi[($g + 6) % 7]] ?? '')) as [$da, $a]) {
        if ($a > 1440 && $min < $a - 1440) return [true, ($v['testo_aperto'] ?: 'Aperto ora') . ', chiude alle ' . ewx_ora($a)];
    }
    foreach (ewx_orari_intervalli((string)($v[$chiavi[$g]] ?? '')) as [$da, $a]) {
        if ($da === 0 && $a === 1440) return [true, ($v['testo_aperto'] ?: 'Aperto ora') . ', 24 ore su 24'];
        if ($min >= $da && $min < $a) return [true, ($v['testo_aperto'] ?: 'Aperto ora') . ', chiude alle ' . ewx_ora($a)];
    }
    // Chiuso: quando riapre (oggi più tardi o nei prossimi sette giorni).
    for ($k = 0; $k < 8; $k++) {
        $giorno = $ora->modify('+' . $k . ' day');
        $chiuso = false;
        foreach (ewx_orari_chiusure($v) as [$da, $a]) if ($giorno->format('Y-m-d') >= $da && $giorno->format('Y-m-d') <= $a) $chiuso = true;
        if ($chiuso) continue;
        foreach (ewx_orari_intervalli((string)($v[$chiavi[((int)$giorno->format('N') - 1)]] ?? '')) as [$da]) {
            if ($k === 0 && $da <= $min) continue;
            $quando = $k === 0 ? 'alle ' : ($k === 1 ? 'domani alle ' : mb_strtolower(EWX_GIORNI[$chiavi[(int)$giorno->format('N') - 1]]) . ' alle ');
            return [false, ($v['testo_chiuso'] ?: 'Chiuso ora') . ', apre ' . $quando . ewx_ora($da)];
        }
    }
    return [false, $v['testo_chiuso'] ?: 'Chiuso ora'];
}

$ewxOrariPred = ['lun' => '9:00-13:00, 15:00-19:00', 'mar' => '9:00-13:00, 15:00-19:00', 'mer' => '9:00-13:00, 15:00-19:00', 'gio' => '9:00-13:00, 15:00-19:00', 'ven' => '9:00-13:00, 15:00-19:00', 'sab' => '9:00-13:00', 'dom' => 'Chiuso'];
$ewxOrariGiorni = [];
foreach (EWX_GIORNI as $k => $nome) $ewxOrariGiorni[] = ec($k, 'testo', $nome, $ewxOrariPred[$k], 'contenuto', ['sezione' => 'Orari', 'aiuto' => $k === 'lun' ? 'Per esempio 9:00-13:00, 15:00-19:00. Chiuso, oppure vuoto, per un giorno di chiusura; 24 ore per sempre aperto.' : '']);
ewx_registra('orari', 'Orari di apertura', 'orologio', 'Elementi', array_merge($ewxOrariGiorni, [
    ec('chiusure', 'ripetitore', 'Ferie e chiusure', [], 'contenuto', ['campi' => [
        ec('dal', 'testo', 'Dal (AAAA-MM-GG)', ''), ec('al', 'testo', 'Al (AAAA-MM-GG, vuoto: un giorno)', ''), ec('motivo', 'testo', 'Motivo (facoltativo)', 'Ferie'),
    ], 'titolo_voce' => 'dal', 'sezione' => 'Ferie e chiusure', 'aiuto' => 'Nei giorni di chiusura il widget dice Chiuso e il motivo. Quelle passate si possono lasciare: non contano più.']),
    ec('titolo', 'testo', 'Titolo', 'Orari di apertura', 'contenuto', ['sezione' => 'Aspetto']),
    ec('mostra_stato', 'interruttore', 'Mostra "Aperto ora" o "Chiuso"', true, 'contenuto', ['sezione' => 'Aspetto']),
    ec('testo_aperto', 'testo', 'Testo quando è aperto', 'Aperto ora', 'contenuto', ['condizione' => ['mostra_stato' => true], 'sezione' => 'Aspetto']),
    ec('testo_chiuso', 'testo', 'Testo quando è chiuso', 'Chiuso ora', 'contenuto', ['condizione' => ['mostra_stato' => true], 'sezione' => 'Aspetto']),
    ec('evidenzia_oggi', 'interruttore', 'Evidenzia il giorno di oggi', true, 'contenuto', ['sezione' => 'Aspetto']),
    ec('dati_google', 'interruttore', 'Dati per Google (orari dell\'attività)', true, 'contenuto', ['sezione' => 'Aspetto', 'aiuto' => 'Gli orari entrano nei dati strutturati della pagina, che Google può leggere.']),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-orari-titolo', '--ew-tc'),
    ...ewx_testo_stile('giorno', 'Giorni e orari', ' .ew-orari-riga', '--ew-dc'),
    ewx_col('oggi_sfondo', 'Sfondo del giorno di oggi', '--ew-obg', 'Giorni e orari'),
    ewx_col('aperto_colore', 'Colore di "Aperto"', '--ew-ok', 'Stato'),
    ewx_col('chiuso_colore', 'Colore di "Chiuso"', '--ew-no', 'Stato'),
    ...ewx_riquadro_stile(' .ew-orari'),
]), function (array $v, array $ctx): string {
    $ora = new DateTimeImmutable('now');
    $oggi = array_keys(EWX_GIORNI)[(int)$ora->format('N') - 1];
    [$aperto, $frase] = ewx_orari_stato($v, $ora);
    $righe = '';
    $schema = [];
    foreach (EWX_GIORNI as $k => $nome) {
        $int = ewx_orari_intervalli((string)($v[$k] ?? ''));
        $testo = $int === [[0, 1440]] ? '24 ore su 24' : ($int ? implode(', ', array_map(fn($x) => ewx_ora($x[0]) . ' - ' . ewx_ora($x[1]), $int)) : 'Chiuso');
        $righe .= '<li class="ew-orari-riga' . ($v['evidenzia_oggi'] && $k === $oggi ? ' ew-oggi' : '') . ($int ? '' : ' ew-chiuso') . '"' . ($k === $oggi ? ' aria-current="date"' : '') . '><span>' . h($nome) . '</span><span>' . h($testo) . '</span></li>';
        foreach ($int as [$da, $a]) $schema[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/' . EWX_GIORNI_SCHEMA[$k], 'opens' => sprintf('%02d:%02d', intdiv($da, 60), $da % 60), 'closes' => $a === 1440 ? '23:59' : sprintf('%02d:%02d', intdiv($a % 1440, 60), $a % 60)];
    }
    $prossime = array_filter(ewx_orari_chiusure($v), fn($c) => $c[1] >= $ora->format('Y-m-d'));
    $ferie = '';
    foreach (array_slice($prossime, 0, 3) as [$da, $a, $motivo]) {
        $d = fn($x) => DateTimeImmutable::createFromFormat('Y-m-d', $x)->format('d/m');
        $ferie .= '<li>' . h(($motivo !== '' ? $motivo . ': ' : 'Chiuso: ') . ($da === $a ? 'il ' . $d($da) : 'dal ' . $d($da) . ' al ' . $d($a))) . '</li>';
    }
    // Lo stato si ricalcola anche nel browser (pagina aperta a lungo): gli stessi dati, in JSON, nel fuso del sito.
    $dati = ['fuso' => date_default_timezone_get(), 'giorni' => array_map(fn($k) => ewx_orari_intervalli((string)($v[$k] ?? '')), array_keys(EWX_GIORNI)),
        'chiusure' => array_values(array_map(fn($c) => [$c[0], $c[1], $c[2]], ewx_orari_chiusure($v))), 'aperto' => $v['testo_aperto'] ?: 'Aperto ora', 'chiuso' => $v['testo_chiuso'] ?: 'Chiuso ora'];
    $out = '<div class="ew-orari"' . ($v['mostra_stato'] ? ' data-ew-orari="' . h(json_encode($dati, JSON_UNESCAPED_UNICODE)) . '"' : '') . '>'
        . (trim($v['titolo']) !== '' ? '<h3 class="ew-orari-titolo">' . h($v['titolo']) . '</h3>' : '')
        . ($v['mostra_stato'] ? '<p class="ew-orari-stato ' . ($aperto ? 'ew-aperto' : 'ew-chiuso-ora') . '" aria-live="polite"><span class="ew-orari-punto" aria-hidden="true"></span><span class="ew-orari-frase">' . h($frase) . '</span></p>' : '')
        . '<ul class="ew-orari-giorni">' . $righe . '</ul>' . ($ferie !== '' ? '<ul class="ew-orari-ferie">' . $ferie . '</ul>' : '') . '</div>';
    if ($v['dati_google'] && $schema && !$ctx['anteprima']) {
        $ld = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => (string)cms_impostazione('sito_nome', CMS_NOME), 'url' => defined('SITO_URL') ? SITO_URL . '/' : '', 'openingHoursSpecification' => $schema];
        $out .= '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
    return $out;
});

// ---------------- Barra dei contatti per il telefono
ewx_registra('barra_contatti', 'Barra contatti (telefono)', 'telefono', 'Elementi', [
    ec('telefono', 'testo', 'Numero da chiamare', '', 'contenuto', ['sezione' => 'Pulsanti', 'aiuto' => 'Con il prefisso se serve, per esempio +39 051 123456. Vuoto: niente pulsante Chiama.']),
    ec('etichetta_telefono', 'testo', 'Etichetta', 'Chiama', 'contenuto', ['sezione' => 'Pulsanti']),
    ec('whatsapp', 'testo', 'Numero WhatsApp', '', 'contenuto', ['sezione' => 'Pulsanti', 'aiuto' => 'Con il prefisso del paese, per esempio 39333...']),
    ec('etichetta_whatsapp', 'testo', 'Etichetta', 'WhatsApp', 'contenuto', ['sezione' => 'Pulsanti']),
    ec('messaggio', 'testo', 'Messaggio WhatsApp già scritto', (string)cms_impostazione('whatsapp_messaggio', 'Ciao, '), 'contenuto', ['sezione' => 'Pulsanti']),
    ec('indirizzo', 'testo', 'Indirizzo per le indicazioni', '', 'contenuto', ['sezione' => 'Pulsanti', 'aiuto' => 'Via, numero e città: il pulsante apre le indicazioni stradali sul telefono.']),
    ec('etichetta_indirizzo', 'testo', 'Etichetta', 'Indicazioni', 'contenuto', ['sezione' => 'Pulsanti']),
    ec('email', 'testo', 'Email', '', 'contenuto', ['sezione' => 'Pulsanti']),
    ec('etichetta_email', 'testo', 'Etichetta', 'Scrivici', 'contenuto', ['sezione' => 'Pulsanti']),
    ec('dove', 'scelta', 'Dove si vede', 'telefono', 'contenuto', ['opzioni' => ['telefono' => 'Solo sul telefono', 'sempre' => 'Sempre'], 'sezione' => 'Comportamento']),
    ewx_col('sfondo', 'Sfondo della barra', '--ew-bg', 'Aspetto'),
    ewx_col('colore', 'Colore di icone e testi', '--ew-c1', 'Aspetto'),
    ewx_col('separatore', 'Colore delle righe fra i pulsanti', '--ew-bc', 'Aspetto'),
    ewx_var('altezza', 'Altezza', '--ew-h', 'Aspetto', ['d' => ['v' => 62, 'u' => 'px']]),
], function (array $v, array $ctx): string {
    $voci = [];
    $tel = preg_replace('/[^\d+]/', '', (string)$v['telefono']);
    if ($tel !== '') $voci[] = ['tel:' . $tel, 'telefono', $v['etichetta_telefono'] ?: 'Chiama', false];
    $wa = preg_replace('/\D/', '', (string)$v['whatsapp']);
    if ($wa !== '') $voci[] = ['https://wa.me/' . $wa . (trim($v['messaggio']) !== '' ? '?text=' . rawurlencode($v['messaggio']) : ''), 'whatsapp', $v['etichetta_whatsapp'] ?: 'WhatsApp', true];
    if (trim($v['indirizzo']) !== '') $voci[] = ['https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(trim($v['indirizzo'])), 'navigazione', $v['etichetta_indirizzo'] ?: 'Indicazioni', true];
    $em = trim((string)$v['email']);
    if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $voci[] = ['mailto:' . $em, 'email', $v['etichetta_email'] ?: 'Scrivici', false];
    if (!$voci) return ewx_vuoto('Scrivi almeno un numero, un indirizzo o un\'email');
    $out = '<nav class="ew-barra-contatti ew-bc-' . ($v['dove'] === 'sempre' ? 'sempre' : 'telefono') . ($ctx['anteprima'] ? ' ew-bc-anteprima' : '') . '" aria-label="Contatti rapidi">';
    foreach ($voci as [$href, $ic, $eti, $fuori]) {
        $out .= '<a class="ew-bc-voce ew-bc-' . h($ic) . '" href="' . h($href) . '"' . ($fuori ? ' target="_blank" rel="noopener"' : '') . '>' . icona_svg($ic) . '<span>' . h($eti) . '</span></a>';
    }
    $out .= '</nav>';
    return $out . ($ctx['anteprima'] ? '<div class="ew-fisso-segnaposto">Barra dei contatti: sul sito sta fissa in fondo allo schermo' . ($v['dove'] === 'sempre' ? '' : ', solo sul telefono') . '.</div>' : '');
});

// ---------------- Recensioni
/** Le stelle con la parte piena giusta (4,6 su 5): due file di stelle sovrapposte, quella piena tagliata. */
function ewx_stelle_media(float $media): string {
    $media = max(0, min(5, $media));
    $vuote = str_repeat(icona_svg('stella'), 5);
    $piene = str_repeat(icona_svg('stella-piena'), 5);
    return '<span class="ew-rec-stelle" role="img" aria-label="' . h(str_replace('.', ',', (string)round($media, 1))) . ' su 5"><span class="ew-rec-vuote">' . $vuote . '</span><span class="ew-rec-piene" style="width:' . round($media / 5 * 100, 1) . '%">' . $piene . '</span></span>';
}
ewx_registra('recensioni', 'Recensioni', 'stella-piena', 'Marketing', [
    ec('media', 'testo', 'Voto medio (per esempio 4,8)', '4,8', 'contenuto', ['sezione' => 'Riepilogo']),
    ec('totale', 'testo', 'Quante recensioni', '127 recensioni', 'contenuto', ['sezione' => 'Riepilogo']),
    ec('fonte', 'testo', 'Dove sono (per esempio Google)', 'Google', 'contenuto', ['sezione' => 'Riepilogo']),
    ec('mostra_riepilogo', 'interruttore', 'Mostra il riepilogo', true, 'contenuto', ['sezione' => 'Riepilogo']),
    ec('voci', 'ripetitore', 'Recensioni', [
        ['testo' => 'Gentili e precisi: ci torneremo di sicuro.', 'nome' => 'Giulia R.', 'stelle' => 5, 'quando' => 'un mese fa'],
        ['testo' => 'Tutto come promesso, e anche qualcosa in più.', 'nome' => 'Marco B.', 'stelle' => 5, 'quando' => 'due mesi fa'],
        ['testo' => 'Ottimo rapporto fra qualità e prezzo.', 'nome' => 'Sara L.', 'stelle' => 4, 'quando' => 'tre mesi fa'],
    ], 'contenuto', ['campi' => [
        ec('testo', 'testolungo', 'Testo', ''), ec('nome', 'testo', 'Nome', ''), ec('stelle', 'numero', 'Stelle', 5, 'contenuto', ['min' => 1, 'max' => 5]), ec('quando', 'testo', 'Quando (facoltativo)', ''),
    ], 'titolo_voce' => 'nome', 'sezione' => 'Recensioni', 'aiuto' => 'Copiale da Google o da dove le hai ricevute, con il permesso di chi le ha scritte.']),
    ewx_colonne(3),
    ec('pulsante_testo', 'testo', 'Testo del pulsante', 'Lasciaci una recensione', 'contenuto', ['sezione' => 'Pulsante']),
    ewx_link('pulsante_link', 'Link del pulsante (la pagina per lasciare una recensione)'),
    ewx_col('stelle_colore', 'Colore delle stelle', '--ew-c1', 'Stelle'),
    ewx_var('stelle_dimensione', 'Dimensione delle stelle', '--ew-dim', 'Stelle', ['d' => ['v' => 18, 'u' => 'px']]),
    ...ewx_testo_stile('media', 'Voto medio', ' .ew-rec-media', '--ew-tc'),
    ...ewx_testo_stile('testo', 'Testo delle recensioni', ' .ew-rec-testo', '--ew-dc'),
    ...ewx_testo_stile('nome', 'Nome', ' .ew-rec-nome', '--ew-ac'),
    ...ewx_riquadro_stile(' .ew-rec-voce', 'Schede'),
], function (array $v, array $ctx): string {
    ewx_css_colonne($ctx, ' .ew-rec-griglia', $v['colonne'], 4);
    $media = (float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', (string)$v['media']));
    $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['testo'] ?? '')) !== '');
    $out = '<div class="ew-rec">';
    if ($v['mostra_riepilogo'] && $media > 0) {
        $out .= '<div class="ew-rec-riepilogo"><b class="ew-rec-media">' . h(str_replace('.', ',', (string)round($media, 1))) . '</b><div>' . ewx_stelle_media($media)
            . '<span class="ew-rec-totale">' . h(trim($v['totale'] . (trim($v['fonte']) !== '' ? ' su ' . $v['fonte'] : ''))) . '</span></div></div>';
    }
    if ($voci) {
        $out .= '<div class="ew-rec-griglia">';
        foreach ($voci as $x) {
            $n = max(1, min(5, (int)($x['stelle'] ?? 5)));
            $out .= '<figure class="ew-rec-voce"><span class="ew-rec-stelle-voce" role="img" aria-label="' . $n . ' su 5">' . str_repeat(icona_svg('stella-piena'), $n) . '</span>'
                . '<blockquote class="ew-rec-testo">' . editor_paragrafi((string)$x['testo']) . '</blockquote><figcaption><b class="ew-rec-nome">' . h((string)($x['nome'] ?? '')) . '</b>'
                . (trim((string)($x['quando'] ?? '')) !== '' ? '<span class="ew-rec-quando">' . h($x['quando']) . '</span>' : '') . '</figcaption></figure>';
        }
        $out .= '</div>';
    }
    $url = editor_link_sicuro((string)($v['pulsante_link']['url'] ?? ''));
    if ($url !== '' && trim($v['pulsante_testo']) !== '') $out .= '<p class="ew-rec-azione"><a class="btn btn-viola" href="' . h($url) . '" target="_blank" rel="noopener">' . icona_svg('stella-piena') . ' ' . h($v['pulsante_testo']) . '</a></p>';
    if ($out === '<div class="ew-rec">') return ewx_vuoto('Scrivi il voto medio o aggiungi le recensioni');
    return $out . '</div>';
});

// ---------------- Prima e dopo
ewx_registra('prima_dopo', 'Prima e dopo', 'frecce-scambia', 'Gallerie', [
    ec('immagine_prima', 'immagine', 'Foto di prima', '', 'contenuto', ['sezione' => 'Foto']),
    ec('immagine_dopo', 'immagine', 'Foto di dopo', '', 'contenuto', ['sezione' => 'Foto', 'aiuto' => 'Due foto della stessa misura e inquadratura vengono meglio.']),
    ec('etichetta_prima', 'testo', 'Etichetta di prima', 'Prima', 'contenuto', ['sezione' => 'Foto']),
    ec('etichetta_dopo', 'testo', 'Etichetta di dopo', 'Dopo', 'contenuto', ['sezione' => 'Foto']),
    ec('alt', 'testo', 'Cosa mostrano (per chi non vede)', 'La stessa stanza prima e dopo il lavoro', 'contenuto', ['sezione' => 'Foto']),
    ec('partenza', 'numero', 'Dove parte il cursore (%)', 50, 'contenuto', ['min' => 5, 'max' => 95, 'sezione' => 'Cursore']),
    ec('proporzioni', 'scelta', 'Proporzioni', '3/2', 'stile', ['opzioni' => ['1/1' => 'Quadrate', '4/3' => '4:3', '3/2' => '3:2', '16/9' => '16:9', '3/4' => '3:4 (verticali)'], 'selettore' => '', 'proprieta' => '--ew-ratio', 'sezione' => 'Foto']),
    ewx_var('raggio', 'Angoli arrotondati', '--ew-r', 'Foto', ['d' => ['v' => 14, 'u' => 'px']]),
    ewx_col('linea_colore', 'Colore della linea e della maniglia', '--ew-c1', 'Cursore'),
    ewx_col('etichette_sfondo', 'Sfondo delle etichette', '--ew-ebg', 'Etichette'),
    ewx_col('etichette_colore', 'Colore delle etichette', '--ew-ec', 'Etichette'),
], function (array $v, array $ctx): string {
    $a = (string)$v['immagine_prima']; $b = (string)$v['immagine_dopo'];
    if ($a === '' || $b === '') return ewx_vuoto('Scegli la foto di prima e quella di dopo');
    $p = max(5, min(95, (int)$v['partenza']));
    return '<div class="ew-pd" data-ew-pd style="--ew-pos:' . $p . '%">'
        . '<img class="ew-pd-dopo" src="' . h($b) . '" alt="' . h(trim($v['alt'] . ' (' . $v['etichetta_dopo'] . ')')) . '" loading="lazy">'
        . '<div class="ew-pd-prima"><img src="' . h($a) . '" alt="' . h(trim($v['alt'] . ' (' . $v['etichetta_prima'] . ')')) . '" loading="lazy"></div>'
        . (trim($v['etichetta_prima']) !== '' ? '<span class="ew-pd-eti ew-pd-eti-prima" aria-hidden="true">' . h($v['etichetta_prima']) . '</span>' : '')
        . (trim($v['etichetta_dopo']) !== '' ? '<span class="ew-pd-eti ew-pd-eti-dopo" aria-hidden="true">' . h($v['etichetta_dopo']) . '</span>' : '')
        . '<span class="ew-pd-linea" aria-hidden="true"><span class="ew-pd-maniglia">' . icona_svg('frecce-scambia') . '</span></span>'
        . '<input class="ew-pd-cursore" type="range" min="0" max="100" value="' . $p . '" aria-label="Sposta per confrontare prima e dopo">'
        . '</div>';
});

// ---------------- Squadra
ewx_registra('squadra', 'Squadra', 'utenti', 'Elementi', [
    ec('voci', 'ripetitore', 'Persone', [
        ['immagine' => '', 'nome' => 'Nome Cognome', 'ruolo' => 'Fondatrice', 'testo' => 'Due righe su cosa fa e perché le piace.', 'email' => '', 'telefono' => '', 'linkedin' => '', 'instagram' => '', 'facebook' => '', 'sito' => ''],
        ['immagine' => '', 'nome' => 'Nome Cognome', 'ruolo' => 'Collaboratore', 'testo' => 'Due righe su cosa fa e perché gli piace.', 'email' => '', 'telefono' => '', 'linkedin' => '', 'instagram' => '', 'facebook' => '', 'sito' => ''],
        ['immagine' => '', 'nome' => 'Nome Cognome', 'ruolo' => 'Collaboratrice', 'testo' => 'Due righe su cosa fa e perché le piace.', 'email' => '', 'telefono' => '', 'linkedin' => '', 'instagram' => '', 'facebook' => '', 'sito' => ''],
    ], 'contenuto', ['campi' => [
        ec('immagine', 'immagine', 'Foto', ''), ec('nome', 'testo', 'Nome', 'Nome Cognome'), ec('ruolo', 'testo', 'Ruolo', ''), ec('testo', 'testolungo', 'Due righe', ''),
        ec('email', 'testo', 'Email', ''), ec('telefono', 'testo', 'Telefono', ''), ec('linkedin', 'testo', 'LinkedIn (indirizzo)', ''), ec('instagram', 'testo', 'Instagram (indirizzo)', ''), ec('facebook', 'testo', 'Facebook (indirizzo)', ''), ec('sito', 'testo', 'Sito (indirizzo)', ''),
    ], 'titolo_voce' => 'nome', 'sezione' => 'Persone']),
    ewx_colonne(3),
    ec('forma', 'scelta', 'Forma della foto', 'tonda', 'contenuto', ['opzioni' => ['tonda' => 'Tonda', 'quadrata' => 'Quadrata', 'verticale' => 'Verticale (3:4)'], 'sezione' => 'Disposizione']),
    ewx_allinea('allineamento', 'Disposizione', 'center'),
    ewx_var('foto_dimensione', 'Dimensione della foto tonda', '--ew-imgs', 'Foto', ['d' => ['v' => 140, 'u' => 'px']]),
    ...ewx_testo_stile('nome', 'Nome', ' .ew-sq-nome', '--ew-tc'),
    ...ewx_testo_stile('ruolo', 'Ruolo', ' .ew-sq-ruolo', '--ew-ac'),
    ...ewx_testo_stile('testo', 'Testo', ' .ew-sq-testo', '--ew-dc'),
    ewx_col('social_colore', 'Colore delle icone', '--ew-c1', 'Contatti', true),
    ...ewx_riquadro_stile(' .ew-sq-voce', 'Schede'),
], function (array $v, array $ctx): string {
    ewx_css_colonne($ctx, ' .ew-squadra', $v['colonne'], 6);
    ewx_css_allinea($ctx, $v['allineamento']);
    $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['nome'] ?? '')) !== '');
    if (!$voci) return ewx_vuoto('Aggiungi le persone');
    $forma = in_array($v['forma'], ['quadrata', 'verticale'], true) ? $v['forma'] : 'tonda';
    $out = '<div class="ew-squadra ew-sq-' . $forma . '">';
    foreach ($voci as $x) {
        $soc = '';
        $em = trim((string)($x['email'] ?? ''));
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $soc .= '<a href="mailto:' . h($em) . '" aria-label="Email di ' . h($x['nome']) . '">' . icona_svg('email') . '</a>';
        $tel = preg_replace('/[^\d+]/', '', (string)($x['telefono'] ?? ''));
        if ($tel !== '') $soc .= '<a href="tel:' . h($tel) . '" aria-label="Telefono di ' . h($x['nome']) . '">' . icona_svg('telefono') . '</a>';
        foreach (['linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'sito' => 'Sito'] as $k => $nome) {
            $u = editor_link_sicuro((string)($x[$k] ?? ''));
            if ($u !== '' && preg_match('~^https?://~i', $u)) $soc .= '<a href="' . h($u) . '" target="_blank" rel="noopener" aria-label="' . h($nome . ' di ' . $x['nome']) . '">' . icona_svg($k === 'sito' ? 'globo' : $k) . '</a>';
        }
        $foto = ($x['immagine'] ?? '') !== '' ? ewx_img($x['immagine'], (string)$x['nome']) : '<span class="ew-sq-iniziali" aria-hidden="true">' . h(mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim((string)$x['nome'])), 0, 2))))) . '</span>';
        $out .= '<figure class="ew-sq-voce"><span class="ew-sq-foto">' . $foto . '</span><figcaption><b class="ew-sq-nome">' . h($x['nome']) . '</b>'
            . (trim((string)($x['ruolo'] ?? '')) !== '' ? '<span class="ew-sq-ruolo">' . h($x['ruolo']) . '</span>' : '')
            . (trim((string)($x['testo'] ?? '')) !== '' ? '<span class="ew-sq-testo">' . nl2br(h($x['testo']), false) . '</span>' : '')
            . ($soc !== '' ? '<span class="ew-sq-social">' . $soc . '</span>' : '') . '</figcaption></figure>';
    }
    return $out . '</div>';
});

// ---------------- Slider (più aperture che scorrono: le Slides di Elementor)
$ewxSliderScorri = array_map(function ($c) {
    if (($c['nome'] ?? '') === 'per_vista') return null; // una slide alla volta, sempre
    if (($c['nome'] ?? '') === 'autoplay') $c['predefinito'] = true;
    if (($c['nome'] ?? '') === 'spazio') $c['predefinito'] = ['d' => ['v' => 0, 'u' => 'px']];
    return $c;
}, ewx_controlli_carosello());
ewx_registra('slider', 'Slider', 'immagine', 'Gallerie', array_merge([
    ec('voci', 'ripetitore', 'Slide', [
        ['immagine' => '', 'occhiello' => 'Novità', 'titolo' => 'Il titolo della prima slide', 'testo' => 'Una frase che dice perché continuare a leggere.', 'pulsante' => 'Scopri di più', 'link' => '/contatti/'],
        ['immagine' => '', 'occhiello' => '', 'titolo' => 'Il titolo della seconda slide', 'testo' => 'Un\'altra cosa importante, detta in breve.', 'pulsante' => 'Scrivici', 'link' => '/contatti/'],
    ], 'contenuto', ['campi' => [
        ec('immagine', 'immagine', 'Foto di sfondo', ''), ec('occhiello', 'testo', 'Occhiello', ''), ec('titolo', 'testo', 'Titolo', 'Titolo della slide'), ec('testo', 'testolungo', 'Testo', ''),
        ec('pulsante', 'testo', 'Testo del pulsante', ''), ec('link', 'testo', 'Link del pulsante', ''),
    ], 'titolo_voce' => 'titolo', 'sezione' => 'Slide']),
    ec('allineamento_testo', 'scelta', 'Posizione del testo', 'sinistra', 'contenuto', ['opzioni' => ['sinistra' => 'A sinistra', 'centro' => 'Al centro', 'destra' => 'A destra'], 'sezione' => 'Slide']),
    ewx_var('altezza', 'Altezza', '--ew-h', 'Slide', ['d' => ['v' => 460, 'u' => 'px'], 'm' => ['v' => 380, 'u' => 'px']]),
    ewx_col('velo', 'Velo sopra la foto', '--ew-velo', 'Slide'),
    ewx_var('raggio', 'Angoli arrotondati', '--ew-r', 'Slide', ['d' => ['v' => 18, 'u' => 'px']]),
    ...ewx_testo_stile('occhiello', 'Occhiello', ' .ew-sl-occhiello', '--ew-ac'),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-sl-titolo', '--ew-tc'),
    ...ewx_testo_stile('testo', 'Testo', ' .ew-sl-testo', '--ew-dc'),
], array_values(array_filter($ewxSliderScorri))), function (array $v, array $ctx): string {
    $voci = array_values(array_filter((array)$v['voci'], fn($x) => trim((string)($x['titolo'] ?? '')) !== '' || ($x['immagine'] ?? '') !== ''));
    if (!$voci) return ewx_vuoto('Aggiungi le slide');
    $v['per_vista'] = 1;
    $pos = in_array($v['allineamento_testo'], ['centro', 'destra'], true) ? $v['allineamento_testo'] : 'sinistra';
    $html = [];
    foreach ($voci as $i => $x) {
        $url = editor_link_sicuro((string)($x['link'] ?? ''));
        $tag = $i === 0 ? 'h2' : 'h3';
        $html[] = '<div class="ew-sl ew-sl-' . $pos . (($x['immagine'] ?? '') !== '' ? ' ew-sl-foto' : '') . '">'
            . (($x['immagine'] ?? '') !== '' ? '<img class="ew-sl-sfondo" src="' . h($x['immagine']) . '" alt=""' . ($i === 0 ? ' fetchpriority="high"' : ' loading="lazy"') . '>' : '')
            . '<div class="ew-sl-testi">' . (trim((string)($x['occhiello'] ?? '')) !== '' ? '<span class="ew-sl-occhiello">' . h($x['occhiello']) . '</span>' : '')
            . (trim((string)($x['titolo'] ?? '')) !== '' ? "<$tag class=\"ew-sl-titolo\">" . h($x['titolo']) . "</$tag>" : '')
            . (trim((string)($x['testo'] ?? '')) !== '' ? '<p class="ew-sl-testo">' . nl2br(h($x['testo']), false) . '</p>' : '')
            . ($url !== '' && trim((string)($x['pulsante'] ?? '')) !== '' ? '<a class="btn btn-viola ew-sl-pulsante" href="' . h($url) . '">' . h($x['pulsante']) . '</a>' : '')
            . '</div></div>';
    }
    return ewx_carosello_html($v, $ctx, $html, 'ew-slider');
});

// ---------------- Eventi
const EWX_MESI = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
ewx_registra('eventi', 'Eventi', 'calendario', 'Dal sito', [
    ec('voci', 'ripetitore', 'Eventi', [
        ['data' => date('Y-m-d', strtotime('+10 days')), 'ora' => '21:00', 'titolo' => 'Serata di presentazione', 'luogo' => 'In sede', 'testo' => 'Due righe su cosa succede e per chi è.', 'prezzo' => 'Ingresso libero', 'immagine' => '', 'link' => ''],
        ['data' => date('Y-m-d', strtotime('+30 days')), 'ora' => '10:00', 'titolo' => 'Laboratorio del sabato', 'luogo' => '', 'testo' => 'Posti limitati: meglio prenotare.', 'prezzo' => '15 €', 'immagine' => '', 'link' => ''],
    ], 'contenuto', ['campi' => [
        ec('data', 'testo', 'Data (AAAA-MM-GG)', ''), ec('ora', 'testo', 'Ora (facoltativa, per esempio 21:00)', ''), ec('titolo', 'testo', 'Titolo', 'Nuovo evento'), ec('luogo', 'testo', 'Luogo', ''),
        ec('testo', 'testolungo', 'Descrizione', ''), ec('prezzo', 'testo', 'Prezzo (facoltativo)', ''), ec('immagine', 'immagine', 'Immagine', ''), ec('link', 'testo', 'Link (prenotazione o dettagli)', ''),
    ], 'titolo_voce' => 'titolo', 'sezione' => 'Eventi']),
    ec('nascondi_passati', 'interruttore', 'Togli da solo gli eventi passati', true, 'contenuto', ['sezione' => 'Elenco']),
    ec('quanti', 'numero', 'Quanti al massimo (0: tutti)', 0, 'contenuto', ['min' => 0, 'max' => 40, 'sezione' => 'Elenco']),
    ec('vuoto', 'testo', 'Testo senza eventi in programma', 'Al momento non ci sono eventi in programma. Torna a trovarci presto.', 'contenuto', ['sezione' => 'Elenco']),
    ec('link_testo', 'testo', 'Testo del link', 'Prenota', 'contenuto', ['sezione' => 'Elenco']),
    ec('dati_google', 'interruttore', 'Dati per Google (eventi)', true, 'contenuto', ['sezione' => 'Elenco']),
    ewx_col('data_sfondo', 'Sfondo della data', '--ew-c2', 'Data'),
    ewx_col('data_colore', 'Colore della data', '--ew-c1', 'Data'),
    ...ewx_testo_stile('titolo', 'Titolo', ' .ew-ev-titolo', '--ew-tc'),
    ...ewx_testo_stile('testo', 'Descrizione', ' .ew-ev-testo', '--ew-dc'),
    ...ewx_riquadro_stile(' .ew-ev-voce', 'Schede'),
], function (array $v, array $ctx): string {
    $oggi = date('Y-m-d');
    $voci = [];
    foreach ((array)$v['voci'] as $x) {
        $d = trim((string)($x['data'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || trim((string)($x['titolo'] ?? '')) === '') continue;
        $x['passato'] = $d < $oggi;
        if ($x['passato'] && $v['nascondi_passati'] && !$ctx['anteprima']) continue;
        $voci[] = $x;
    }
    usort($voci, fn($a, $b) => strcmp($a['data'] . ' ' . $a['ora'], $b['data'] . ' ' . $b['ora']));
    if ((int)$v['quanti'] > 0) $voci = array_slice($voci, 0, (int)$v['quanti']);
    if (!$voci) return '<p class="ew-ev-vuoto">' . h($v['vuoto']) . '</p>';
    $out = '<ol class="ew-eventi">';
    $ld = [];
    foreach ($voci as $x) {
        [$y, $m, $g] = array_map('intval', explode('-', $x['data']));
        $ora = preg_match('/^(\d{1,2})[:.](\d{2})$/', trim((string)$x['ora']), $mo) ? sprintf('%02d:%02d', (int)$mo[1], (int)$mo[2]) : '';
        $url = editor_link_sicuro((string)($x['link'] ?? ''));
        $out .= '<li class="ew-ev-voce' . ($x['passato'] ? ' ew-ev-passato' : '') . '"><time class="ew-ev-data" datetime="' . h($x['data'] . ($ora !== '' ? 'T' . $ora : '')) . '"><b>' . $g . '</b><span>' . EWX_MESI[max(0, min(11, $m - 1))] . '</span><small>' . $y . '</small></time>'
            . (($x['immagine'] ?? '') !== '' ? '<span class="ew-ev-img">' . ewx_img($x['immagine'], (string)$x['titolo']) . '</span>' : '')
            . '<div class="ew-ev-corpo"><h3 class="ew-ev-titolo">' . h($x['titolo']) . '</h3>'
            . '<p class="ew-ev-meta">' . h(implode(' · ', array_filter([$ora !== '' ? 'ore ' . $ora : '', trim((string)($x['luogo'] ?? '')), trim((string)($x['prezzo'] ?? ''))]))) . ($x['passato'] ? ' <em>(passato: sul sito non si vede)</em>' : '') . '</p>'
            . (trim((string)($x['testo'] ?? '')) !== '' ? '<p class="ew-ev-testo">' . nl2br(h($x['testo']), false) . '</p>' : '')
            . ($url !== '' ? '<a class="ew-ev-link" href="' . h($url) . '">' . h($v['link_testo'] ?: 'Dettagli') . ' ' . icona_svg('freccia-destra') . '</a>' : '') . '</div></li>';
        if (!$x['passato']) {
            $e = ['@type' => 'Event', 'name' => (string)$x['titolo'], 'startDate' => $x['data'] . ($ora !== '' ? 'T' . $ora : ''), 'eventStatus' => 'https://schema.org/EventScheduled',
                'location' => ['@type' => 'Place', 'name' => trim((string)($x['luogo'] ?? '')) ?: (string)cms_impostazione('sito_nome', CMS_NOME), 'address' => trim((string)($x['luogo'] ?? '')) ?: (string)cms_impostazione('sito_nome', CMS_NOME)]];
            if (trim((string)($x['testo'] ?? '')) !== '') $e['description'] = (string)$x['testo'];
            if (($x['immagine'] ?? '') !== '') $e['image'] = (str_starts_with($x['immagine'], '/') && defined('SITO_URL') ? SITO_URL : '') . $x['immagine'];
            $ld[] = $e;
        }
    }
    $out .= '</ol>';
    if ($v['dati_google'] && $ld && !$ctx['anteprima']) {
        $out .= '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $ld], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
    return $out;
});
