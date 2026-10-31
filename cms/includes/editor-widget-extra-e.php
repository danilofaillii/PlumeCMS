<?php
/**
 * Widget extra, parte E (2.1, scelti da Danilo: "tutti"): menu del ristorante, richiesta di prenotazione, galleria con
 * filtri, loghi e testo scorrevoli, contenuto esterno con consenso. Stesso contratto delle parti A-D: HTML con classi
 * ew-*, aspetto in sito/assets/widget.css, comportamento in sito/assets/widget.js.
 * Niente variabili in cima al file: tutto sta in funzioni o dentro ewx_registra().
 */

// ---------------- Menu del ristorante

/** I 14 allergeni del regolamento UE 1169/2011 (allegato II), nel loro ordine: numero => [parola breve, nome per la legenda]. */
const EWE_ALLERGENI = [
    1 => ['glutine', 'Cereali con glutine'], 2 => ['crostacei', 'Crostacei'], 3 => ['uova', 'Uova'], 4 => ['pesce', 'Pesce'],
    5 => ['arachidi', 'Arachidi'], 6 => ['soia', 'Soia'], 7 => ['latte', 'Latte e lattosio'], 8 => ['frutta a guscio', 'Frutta a guscio'],
    9 => ['sedano', 'Sedano'], 10 => ['senape', 'Senape'], 11 => ['sesamo', 'Semi di sesamo'], 12 => ['solfiti', 'Anidride solforosa e solfiti'],
    13 => ['lupini', 'Lupini'], 14 => ['molluschi', 'Molluschi'],
];
/** Le etichette dei piatti: campo => [testo, icona, dieta per Google]. */
const EWE_ETICHETTE = [
    'vegano' => ['Vegano', 'foglia', 'https://schema.org/VeganDiet'],
    'vegetariano' => ['Vegetariano', 'germoglio', 'https://schema.org/VegetarianDiet'],
    'senza_glutine' => ['Senza glutine', 'spunta-cerchio', 'https://schema.org/GlutenFreeDiet'],
    'piccante' => ['Piccante', 'fiamma', ''],
    'novita' => ['Novità', 'scintilla', ''],
];
const EWE_GIORNI = ['lun' => 'lunedì', 'mar' => 'martedì', 'mer' => 'mercoledì', 'gio' => 'giovedì', 'ven' => 'venerdì', 'sab' => 'sabato', 'dom' => 'domenica'];

/** Gli allergeni scritti in un campo ("1, 7" oppure "glutine, latte"): i numeri, senza doppioni, in ordine. */
function ewe_allergeni(string $testo): array {
    $out = [];
    foreach (preg_split('/[,;\/+]+|\s+e\s+/u', mb_strtolower($testo)) as $t) {
        $t = trim($t);
        if ($t === '') continue;
        if (ctype_digit($t) && isset(EWE_ALLERGENI[(int)$t])) { $out[(int)$t] = true; continue; }
        foreach (EWE_ALLERGENI as $n => [$breve, $nome]) {
            if ($t === $breve || (mb_strlen($t) >= 4 && (str_contains(mb_strtolower($nome), $t) || str_starts_with($t, $breve)))) { $out[$n] = true; break; }
        }
    }
    ksort($out);
    return array_keys($out);
}

/** I giorni di una sezione ("lun, mer-ven"): le chiavi di EWE_GIORNI; vuoto = tutti i giorni. */
function ewe_giorni(string $testo): array {
    $k = array_keys(EWE_GIORNI);
    $out = [];
    foreach (preg_split('/[,;\s]+/u', mb_strtolower(trim($testo)), -1, PREG_SPLIT_NO_EMPTY) as $t) {
        $p = explode('-', $t);
        $a = array_search(mb_substr($p[0], 0, 3), $k, true);
        $b = isset($p[1]) ? array_search(mb_substr($p[1], 0, 3), $k, true) : $a;
        if ($a === false || $b === false) continue;
        for ($i = $a; ; $i = ($i + 1) % 7) { $out[$k[$i]] = true; if ($i === $b) break; }
    }
    return array_values(array_intersect($k, array_keys($out)));
}

/** Un prezzo: [testo da mostrare, numero per Google o null]. "14" diventa "14 €", "9,5" diventa "9,50 €". */
function ewe_prezzo(string $p, string $valuta = '€'): array {
    $p = trim($p);
    if (preg_match('/^(\d{1,5})(?:[.,](\d{1,2}))?\s*(?:€|euro|eur)?$/iu', $p, $m)) {
        $n = (float)($m[1] . '.' . ($m[2] ?? '0'));
        $testo = (isset($m[2]) && (int)$m[2] > 0 ? number_format($n, 2, ',', '.') : number_format($n, 0, ',', '.')) . ' ' . $valuta;
        return [trim($testo), number_format($n, 2, '.', '')];
    }
    return [$p, null];
}

function ewx_controlli_menu(): array {
    $piatto = [];
    foreach (EWE_ETICHETTE as $k => [$t]) $piatto[] = ec($k, 'interruttore', $t, false);
    return [
        ec('titolo', 'testo', 'Titolo', 'Il nostro menu', 'contenuto', ['sezione' => 'Menu']),
        ec('piatti', 'ripetitore', 'Piatti', [
            ['sezione' => 'Antipasti', 'nome' => 'Tagliere della casa', 'descrizione' => 'Salumi del territorio e formaggi stagionati', 'prezzo' => '14', 'immagine' => '', 'allergeni' => '7', 'vegano' => false, 'vegetariano' => false, 'senza_glutine' => false, 'piccante' => false, 'novita' => false],
            ['sezione' => 'Primi', 'nome' => 'Tagliatelle al ragù', 'descrizione' => 'Pasta fresca tirata a mano', 'prezzo' => '13', 'immagine' => '', 'allergeni' => '1, 3, 9', 'vegano' => false, 'vegetariano' => false, 'senza_glutine' => false, 'piccante' => false, 'novita' => true],
            ['sezione' => 'Primi', 'nome' => 'Risotto alle verdure', 'descrizione' => 'Con le verdure dell\'orto', 'prezzo' => '12', 'immagine' => '', 'allergeni' => '9', 'vegano' => true, 'vegetariano' => true, 'senza_glutine' => true, 'piccante' => false, 'novita' => false],
            ['sezione' => 'Dolci', 'nome' => 'Tiramisù', 'descrizione' => '', 'prezzo' => '6', 'immagine' => '', 'allergeni' => '1, 3, 7', 'vegano' => false, 'vegetariano' => true, 'senza_glutine' => false, 'piccante' => false, 'novita' => false],
        ], 'contenuto', ['campi' => array_merge([
            ec('sezione', 'testo', 'Sezione (Antipasti, Primi...)', 'Primi'), ec('nome', 'testo', 'Nome del piatto', 'Nuovo piatto'), ec('descrizione', 'testolungo', 'Descrizione', ''),
            ec('prezzo', 'testo', 'Prezzo (per esempio 12 oppure 9,50)', ''), ec('immagine', 'immagine', 'Foto (facoltativa)', ''),
            ec('allergeni', 'testo', 'Allergeni (numeri o nomi: 1, 7 oppure glutine, latte)', '', 'contenuto', ['aiuto' => '1 glutine, 2 crostacei, 3 uova, 4 pesce, 5 arachidi, 6 soia, 7 latte, 8 frutta a guscio, 9 sedano, 10 senape, 11 sesamo, 12 solfiti, 13 lupini, 14 molluschi.']),
        ], $piatto), 'titolo_voce' => 'nome', 'sezione' => 'Piatti', 'aiuto' => 'I piatti con la stessa sezione stanno insieme, nell\'ordine in cui le sezioni compaiono la prima volta.']),
        ec('sezioni', 'ripetitore', 'Sezioni con una nota o solo in certi giorni', [], 'contenuto', ['campi' => [
            ec('nome', 'testo', 'Sezione (lo stesso nome scritto nei piatti)', ''), ec('nota', 'testo', 'Nota sotto il titolo (facoltativa)', ''),
            ec('giorni', 'testo', 'Solo in questi giorni (vuoto: sempre)', '', 'contenuto', ['aiuto' => 'Per esempio: lun-ven per il menu del giorno, oppure dom. Gli altri giorni la sezione non si vede.']),
        ], 'titolo_voce' => 'nome', 'sezione' => 'Sezioni', 'aiuto' => 'Facoltativo: serve per la nota sotto il titolo di una sezione e per il menu del giorno.']),
        ec('valuta', 'testo', 'Valuta', '€', 'contenuto', ['sezione' => 'Aspetto']),
        ec('colonne', 'numero', 'Colonne', ['d' => 2, 't' => 2, 'm' => 1], 'contenuto', ['min' => 1, 'max' => 3, 'responsive' => true, 'sezione' => 'Aspetto']),
        ec('mostra_foto', 'interruttore', 'Mostra le foto dei piatti', true, 'contenuto', ['sezione' => 'Aspetto']),
        ec('mostra_allergeni', 'interruttore', 'Mostra gli allergeni (numeri e legenda)', true, 'contenuto', ['sezione' => 'Allergeni']),
        ec('legenda', 'scelta', 'Legenda', 'usati', 'contenuto', ['opzioni' => ['usati' => 'Solo gli allergeni presenti nel menu', 'tutti' => 'Tutti i 14 allergeni'], 'condizione' => ['mostra_allergeni' => true], 'sezione' => 'Allergeni']),
        ec('nota_allergeni', 'testolungo', 'Nota sugli allergeni', 'Per allergie e intolleranze chiedi al personale: ti diciamo ingredienti e possibili tracce di ogni piatto (Reg. UE 1169/2011).', 'contenuto', ['condizione' => ['mostra_allergeni' => true], 'sezione' => 'Allergeni']),
        ec('dati_google', 'interruttore', 'Dati per Google (menu)', true, 'contenuto', ['sezione' => 'Aspetto', 'aiuto' => 'Il menu entra nei dati strutturati della pagina (Menu e MenuItem).']),
        ...ewx_testo_stile('titolo', 'Titolo', ' .ew-menu-titolo', '--ew-tc'),
        ...ewx_testo_stile('sezione', 'Titoli delle sezioni', ' .ew-menu-sez-titolo', '--ew-ac'),
        ...ewx_testo_stile('nome', 'Nome del piatto', ' .ew-menu-nome', '--ew-nc'),
        ...ewx_testo_stile('descrizione', 'Descrizione', ' .ew-menu-desc', '--ew-dc'),
        ...ewx_testo_stile('prezzo', 'Prezzo', ' .ew-menu-prezzo', '--ew-pc'),
        ewx_col('puntini', 'Colore dei puntini', '--ew-bc', 'Prezzo'),
        ewx_col('etichette_colore', 'Colore delle etichette', '--ew-ec', 'Etichette e allergeni'),
        ewx_col('allergeni_sfondo', 'Sfondo dei numeri degli allergeni', '--ew-abg', 'Etichette e allergeni'),
        ...ewx_riquadro_stile(' .ew-menu', 'Riquadro'),
    ];
}

ewx_registra('menu_ristorante', 'Menu del ristorante', 'libro-aperto', 'Elementi', ewx_controlli_menu(), function (array $v, array $ctx): string {
    ewx_css_colonne($ctx, ' .ew-menu-piatti', $v['colonne'], 3);
    $oggi = array_keys(EWE_GIORNI)[(int)date('N') - 1];
    $infoSez = [];
    foreach ((array)$v['sezioni'] as $s) {
        $nome = trim((string)($s['nome'] ?? ''));
        if ($nome !== '') $infoSez[mb_strtolower($nome)] = ['nota' => trim((string)($s['nota'] ?? '')), 'giorni' => ewe_giorni((string)($s['giorni'] ?? ''))];
    }
    $sezioni = [];
    foreach ((array)$v['piatti'] as $p) {
        if (trim((string)($p['nome'] ?? '')) === '') continue;
        $s = trim((string)($p['sezione'] ?? '')) ?: 'Menu';
        $sezioni[mb_strtolower($s)]['nome'] ??= $s;
        $sezioni[mb_strtolower($s)]['piatti'][] = $p;
    }
    if (!$sezioni) return ewx_vuoto('Aggiungi i piatti del menu');
    $usati = [];
    $ld = [];
    $html = '';
    foreach ($sezioni as $chiave => $s) {
        $info = $infoSez[$chiave] ?? ['nota' => '', 'giorni' => []];
        $oggiSi = !$info['giorni'] || in_array($oggi, $info['giorni'], true);
        if (!$oggiSi && !$ctx['anteprima']) continue; // il menu del giorno, gli altri giorni, non c'è
        $quando = $info['giorni'] && count($info['giorni']) < 7 ? implode(', ', array_map(fn($g) => EWE_GIORNI[$g], $info['giorni'])) : '';
        $piatti = '';
        $ldPiatti = [];
        foreach ($s['piatti'] as $p) {
            [$prezzo, $num] = ewe_prezzo((string)($p['prezzo'] ?? ''), trim((string)$v['valuta']) ?: '€');
            $all = $v['mostra_allergeni'] ? ewe_allergeni((string)($p['allergeni'] ?? '')) : [];
            foreach ($all as $n) $usati[$n] = true;
            $eti = '';
            $diete = [];
            foreach (EWE_ETICHETTE as $k => [$t, $ic, $dieta]) {
                if (empty($p[$k])) continue;
                $eti .= '<span class="ew-menu-eti ew-menu-eti-' . $k . '">' . icona_svg($ic) . h($t) . '</span>';
                if ($dieta !== '') $diete[] = $dieta;
            }
            $img = $v['mostra_foto'] && ($p['immagine'] ?? '') !== '' ? '<span class="ew-menu-foto">' . ewx_img((string)$p['immagine'], (string)$p['nome']) . '</span>' : '';
            $piatti .= '<li class="ew-menu-piatto' . ($img !== '' ? ' ew-menu-con-foto' : '') . '">' . $img . '<div class="ew-menu-corpo">'
                . '<div class="ew-menu-riga"><h4 class="ew-menu-nome">' . h($p['nome']) . '</h4><span class="ew-menu-puntini" aria-hidden="true"></span>'
                . ($prezzo !== '' ? '<span class="ew-menu-prezzo">' . h($prezzo) . '</span>' : '') . '</div>'
                . (trim((string)($p['descrizione'] ?? '')) !== '' ? '<p class="ew-menu-desc">' . nl2br(h($p['descrizione']), false) . '</p>' : '')
                . ($eti !== '' || $all ? '<p class="ew-menu-info">' . $eti . ($all ? '<span class="ew-menu-all"><span class="ew-nascosto-vista">Allergeni: ' . h(implode(', ', array_map(fn($n) => mb_strtolower(EWE_ALLERGENI[$n][1]), $all))) . '.</span>'
                    . implode('', array_map(fn($n) => '<span class="ew-all" aria-hidden="true" title="' . h(EWE_ALLERGENI[$n][1]) . '">' . $n . '</span>', $all)) . '</span>' : '') . '</p>' : '')
                . '</div></li>';
            $voce = ['@type' => 'MenuItem', 'name' => (string)$p['nome']];
            if (trim((string)($p['descrizione'] ?? '')) !== '') $voce['description'] = (string)$p['descrizione'];
            if ($num !== null) $voce['offers'] = ['@type' => 'Offer', 'price' => $num, 'priceCurrency' => 'EUR'];
            if ($diete) $voce['suitableForDiet'] = $diete;
            if (($p['immagine'] ?? '') !== '') $voce['image'] = (str_starts_with((string)$p['immagine'], '/') && defined('SITO_URL') ? SITO_URL : '') . $p['immagine'];
            $ldPiatti[] = $voce;
        }
        $html .= '<section class="ew-menu-sezione' . ($quando !== '' ? ' ew-menu-del-giorno' : '') . '"><div class="ew-menu-sez-testa"><h3 class="ew-menu-sez-titolo">' . h($s['nome']) . '</h3>'
            . ($quando !== '' ? '<span class="ew-menu-quando">' . ($oggiSi ? icona_svg('calendario-spunta') . 'Oggi' : 'Solo ' . h($quando) . ' (sul sito si vede solo quei giorni)') . '</span>' : '')
            . '</div>' . ($info['nota'] !== '' ? '<p class="ew-menu-nota">' . h($info['nota']) . '</p>' : '')
            . '<ul class="ew-menu-piatti">' . $piatti . '</ul></section>';
        $ld[] = ['@type' => 'MenuSection', 'name' => (string)$s['nome'], 'hasMenuItem' => $ldPiatti];
    }
    $legenda = '';
    if ($v['mostra_allergeni'] && ($usati || $v['legenda'] === 'tutti')) {
        $elenco = $v['legenda'] === 'tutti' ? array_keys(EWE_ALLERGENI) : array_keys($usati);
        sort($elenco);
        $legenda = '<aside class="ew-menu-legenda" aria-label="Legenda degli allergeni"><h3 class="ew-menu-sez-titolo">Allergeni</h3><ul>'
            . implode('', array_map(fn($n) => '<li><span class="ew-all" aria-hidden="true">' . $n . '</span>' . h(EWE_ALLERGENI[$n][1]) . '</li>', $elenco)) . '</ul>'
            . (trim((string)$v['nota_allergeni']) !== '' ? '<p>' . h($v['nota_allergeni']) . '</p>' : '') . '</aside>';
    }
    $out = '<div class="ew-menu">' . (trim($v['titolo']) !== '' ? '<h2 class="ew-menu-titolo">' . h($v['titolo']) . '</h2>' : '') . $html . $legenda . '</div>';
    if ($v['dati_google'] && $ld && !$ctx['anteprima']) {
        $menu = ['@context' => 'https://schema.org', '@type' => 'Menu', 'name' => trim($v['titolo']) ?: 'Menu', 'inLanguage' => 'it', 'hasMenuSection' => $ld];
        $out .= '<script type="application/ld+json">' . json_encode($menu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
    return $out;
});

// ---------------- Richiesta di prenotazione (il modulo: non è l'addon Prenotazioni)

function ewx_controlli_richiesta(): array {
    $c = [];
    // Con l'addon Prenotazioni acceso, il pannello lo suggerisce: lui gestisce posti, orari e conferme.
    if (function_exists('cms_addon_attivi') && in_array('prenotazioni', cms_addon_attivi(), true)) {
        $c[] = ec('nota_addon', 'intestazione', 'Hai l\'addon Prenotazioni acceso', '', 'contenuto', ['sezione' => 'Richiesta',
            'aiuto' => 'Questo widget manda solo una richiesta per email, che confermi tu. Se vuoi orari, posti disponibili e conferme automatiche, usa il widget dell\'addon Prenotazioni.']);
    }
    return array_merge($c, [
        ec('modalita', 'scelta', 'Cosa si prenota', 'tavolo', 'contenuto', ['opzioni' => ['tavolo' => 'Un tavolo (data, ora, persone)', 'alloggio' => 'Un alloggio (arrivo, partenza, ospiti)'], 'sezione' => 'Richiesta']),
        ec('titolo', 'testo', 'Titolo', 'Prenota un tavolo', 'contenuto', ['sezione' => 'Richiesta']),
        ec('intro', 'testolungo', 'Testo sopra il modulo', 'Mandaci la richiesta: ti rispondiamo per confermare il prima possibile.', 'contenuto', ['sezione' => 'Richiesta']),
        ec('email_a', 'testo', 'A chi arriva la richiesta (email)', '', 'contenuto', ['sezione' => 'Richiesta', 'aiuto' => 'Fino a ' . 3 . ' indirizzi separati da una virgola. Vuoto: l\'indirizzo degli avvisi del sito (Impostazioni, Posta in uscita). Le richieste restano anche nel pannello, in Invii dei form.']),
        ec('max_persone', 'numero', 'Quante persone al massimo', 12, 'contenuto', ['min' => 1, 'max' => 200, 'sezione' => 'Campi']),
        ec('tel_obbligatorio', 'interruttore', 'Telefono obbligatorio', true, 'contenuto', ['sezione' => 'Campi']),
        ec('mostra_note', 'interruttore', 'Campo per le note (allergie, seggiolone, orario di arrivo...)', true, 'contenuto', ['sezione' => 'Campi']),
        ec('pulsante', 'testo', 'Testo del pulsante', 'Invia la richiesta', 'contenuto', ['sezione' => 'Campi']),
        ec('consenso', 'testo', 'Testo della spunta privacy (vuoto: quello standard)', '', 'contenuto', ['sezione' => 'Campi']),
        ec('grazie', 'testolungo', 'Messaggio dopo l\'invio', 'Grazie, abbiamo ricevuto la tua richiesta. Non è ancora una prenotazione: ti scriviamo noi per confermarla.', 'contenuto', ['sezione' => 'Dopo l\'invio']),
        ewx_tip('titolo_tipografia', 'Tipografia del titolo', ' .modulo h2', 'Titolo'),
        ewx_col('etichette_colore', 'Colore delle etichette', '--ew-ec', 'Campi'),
        ...ewx_riquadro_stile(' .modulo', 'Riquadro'),
    ]);
}

ewx_registra('richiesta_prenotazione', 'Richiesta di prenotazione', 'calendario-spunta', 'Dal sito', ewx_controlli_richiesta(), function (array $v, array $ctx): string {
    require_once __DIR__ . '/richiesta-prenotazione.php';
    $id = preg_match('/^[a-z0-9]{1,12}$/', (string)$ctx['id']) ? (string)$ctx['id'] : 'x';
    $alloggio = $v['modalita'] === 'alloggio';
    $max = max(1, min(200, (int)$v['max_persone']));
    $percorso = (string)($ctx['percorso'] ?? '/');
    if (!preg_match('~^/[a-z0-9/-]*$~', $percorso)) $percorso = '/';
    $titolo = trim((string)$v['titolo']);
    $esito = !$ctx['anteprima'] && ($_GET['w'] ?? '') === $id ? (string)($_GET['prenota'] ?? '') : '';
    $out = '<section class="modulo ew-rp" id="prenota-' . $id . '"' . ($titolo !== '' ? ' aria-labelledby="prenota-titolo-' . $id . '"' : ' aria-label="Richiesta di prenotazione"') . '>'
        . ($titolo !== '' ? '<h2 id="prenota-titolo-' . $id . '">' . h($titolo) . '</h2>' : '');
    if ($esito === 'ok') {
        return $out . '<div class="ew-rp-ok" role="status">' . icona_svg('spunta-cerchio') . '<p>' . nl2br(h((string)$v['grazie']), false) . '</p></div>'
            . '<p><a class="ew-rp-altra" href="' . h($percorso) . '#prenota-' . $id . '">Fai un\'altra richiesta</a></p></section>';
    }
    $errore = $esito !== '' ? rp_errore($esito) : '';
    [$cfg, $firma] = rp_firma(['modalita' => $alloggio ? 'alloggio' : 'tavolo', 'a' => rp_indirizzi((string)$v['email_a']), 'max' => $max, 'tel_obbligatorio' => (bool)$v['tel_obbligatorio'], 'titolo' => $titolo]);
    $oggi = date('Y-m-d');
    $fino = date('Y-m-d', strtotime('+2 years'));
    $f = fn(string $nome, string $etichetta, string $campo, bool $largo = false) => '<label' . ($largo ? ' class="form-largo"' : '') . '><span>' . $etichetta . '</span>' . $campo . '</label>';
    $campi = $alloggio
        ? $f('data', 'Arrivo', '<input type="date" name="data" min="' . $oggi . '" max="' . $fino . '" required data-ew-rp-arrivo>')
            . $f('partenza', 'Partenza', '<input type="date" name="partenza" min="' . $oggi . '" max="' . $fino . '" required data-ew-rp-partenza>')
            . $f('persone', 'Ospiti', '<input type="number" name="persone" min="1" max="' . $max . '" value="2" inputmode="numeric" required>')
        : $f('data', 'Data', '<input type="date" name="data" min="' . $oggi . '" max="' . $fino . '" required>')
            . $f('ora', 'Ora', '<input type="time" name="ora" step="900" required>')
            . $f('persone', 'Persone', '<input type="number" name="persone" min="1" max="' . $max . '" value="2" inputmode="numeric" required>');
    $campi .= $f('nome', 'Nome e cognome', '<input type="text" name="nome" maxlength="100" autocomplete="name" required>')
        . $f('email', 'Email', '<input type="email" name="email" maxlength="190" autocomplete="email" required>')
        . $f('telefono', 'Telefono' . ($v['tel_obbligatorio'] ? '' : ' <small>(facoltativo)</small>'), '<input type="tel" name="telefono" maxlength="30" autocomplete="tel"' . ($v['tel_obbligatorio'] ? ' required' : '') . '>')
        . ($v['mostra_note'] ? $f('note', 'Note <small>(facoltativo)</small>', '<textarea name="note" rows="3" maxlength="2000"></textarea>', true) : '');
    $consenso = trim((string)$v['consenso']) !== '' ? h($v['consenso']) : 'Ho letto l&#039;<a href="/privacy-policy/" target="_blank">informativa privacy</a> e accetto il trattamento dei miei dati per questa richiesta.';
    $out .= (trim((string)$v['intro']) !== '' ? '<p>' . nl2br(h($v['intro']), false) . '</p>' : '')
        . ($errore !== '' ? '<p class="modulo-errore" role="alert">' . h($errore) . '</p>' : '')
        . '<form method="post" action="' . cms_base() . '/api/modulo.php" data-captcha' . ($ctx['anteprima'] ? ' data-ew-anteprima' : '') . '>'
        . '<input type="hidden" name="modulo" value="prenota"><input type="hidden" name="pagina" value="' . h($percorso) . '"><input type="hidden" name="rp_w" value="' . $id . '">'
        . '<input type="hidden" name="rp_cfg" value="' . h($cfg) . '"><input type="hidden" name="rp_firma" value="' . h($firma) . '"><input type="hidden" name="captcha" value="">'
        . '<div class="modulo-campi">' . $campi . '</div>'
        . '<div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<label class="spunta"><input type="checkbox" name="privacy" value="1" required> <span>' . $consenso . '</span></label>'
        . '<label class="captcha"><input type="checkbox"> <span>Sono una persona</span> <span class="captcha-stato" aria-live="polite"></span></label>'
        . '<button class="btn btn-viola" type="submit">' . h(trim((string)$v['pulsante']) ?: 'Invia la richiesta') . '</button>'
        . '<p class="ew-rp-nota">' . ($alloggio ? 'È una richiesta: la camera è tua quando ti confermiamo la disponibilità.' : 'È una richiesta: il tavolo è tuo quando ti confermiamo.') . '</p>'
        . '</form><script src="' . (function_exists('sito_versione_asset') ? sito_versione_asset('moduli.js') : cms_base() . '/sito/assets/moduli.js') . '" defer></script></section>';
    return $out;
});

// ---------------- Galleria con filtri

function ewe_slug(string $t): string {
    $a = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($a !== false ? $a : $t)), '-') ?: 'altro';
}

ewx_registra('galleria_filtri', 'Galleria con filtri', 'filtro', 'Gallerie', array_merge([
    ec('immagini', 'ripetitore', 'Immagini', [], 'contenuto', ['campi' => [
        ec('immagine', 'immagine', 'Immagine', ''), ec('alt', 'testo', 'Testo alternativo (cosa si vede)', ''), ec('didascalia', 'testo', 'Didascalia', ''),
        ec('categorie', 'testo', 'Categorie (separate da una virgola)', '', 'contenuto', ['aiuto' => 'Per esempio: Matrimoni oppure Matrimoni, Eventi. Ogni categoria diventa un pulsante sopra la galleria.']),
    ], 'titolo_voce' => 'alt', 'sezione' => 'Immagini']),
    ec('mostra_tutti', 'interruttore', 'Pulsante per vedere tutte le foto', true, 'contenuto', ['sezione' => 'Filtri']),
    ec('tutti', 'testo', 'Testo del pulsante', 'Tutti', 'contenuto', ['condizione' => ['mostra_tutti' => true], 'sezione' => 'Filtri']),
    ec('filtri_allinea', 'scelta', 'Posizione dei pulsanti', 'centro', 'contenuto', ['opzioni' => ['sinistra' => 'A sinistra', 'centro' => 'Al centro', 'destra' => 'A destra'], 'sezione' => 'Filtri']),
    ewx_colonne(3),
    ewx_col('filtro_colore', 'Colore del testo dei pulsanti', '--ew-fc', 'Pulsanti dei filtri'),
    ewx_col('filtro_sfondo', 'Sfondo dei pulsanti', '--ew-fbg', 'Pulsanti dei filtri'),
    ewx_col('filtro_attivo', 'Sfondo del pulsante scelto', '--ew-fac', 'Pulsanti dei filtri'),
    ewx_col('filtro_attivo_testo', 'Testo del pulsante scelto', '--ew-fat', 'Pulsanti dei filtri'),
], ewx_controlli_galleria_stile()), function (array $v, array $ctx): string {
    $voci = [];
    $cat = [];
    foreach ((array)$v['immagini'] as $x) {
        if (!is_array($x) || trim((string)($x['immagine'] ?? '')) === '') continue;
        $mie = [];
        foreach (preg_split('/\s*,\s*/u', trim((string)($x['categorie'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $c) {
            $c = mb_substr(trim($c), 0, 60);
            $s = ewe_slug($c);
            $cat[$s] ??= $c;
            $mie[$s] = true;
        }
        $voci[] = [['immagine' => (string)$x['immagine'], 'alt' => (string)($x['alt'] ?? ''), 'didascalia' => (string)($x['didascalia'] ?? ''), 'link' => ''], array_keys($mie)];
    }
    if (!$voci) return ewx_vuoto('Aggiungi le immagini e scrivi le loro categorie');
    ewx_css_colonne($ctx, ' .ew-galleria', $v['colonne']);
    $all = in_array($v['filtri_allinea'], ['sinistra', 'destra'], true) ? $v['filtri_allinea'] : 'centro';
    $filtri = '';
    if ($cat) {
        $filtri = '<div class="ew-gf-filtri ew-gf-' . $all . '" role="group" aria-label="Filtra le foto">';
        if ($v['mostra_tutti']) $filtri .= '<button type="button" class="ew-gf-filtro" data-filtro="*" aria-pressed="true">' . h(trim((string)$v['tutti']) ?: 'Tutti') . '</button>';
        $primo = !$v['mostra_tutti'];
        foreach ($cat as $s => $nome) { $filtri .= '<button type="button" class="ew-gf-filtro" data-filtro="' . h($s) . '" aria-pressed="' . ($primo ? 'true' : 'false') . '">' . h($nome) . '</button>'; $primo = false; }
        $filtri .= '</div>';
    }
    $iniziale = !$v['mostra_tutti'] && $cat ? array_key_first($cat) : '*';
    $g = '<div class="ew-galleria ew-gal-griglia ew-did-' . h($v['didascalia']) . ($v['effetto_hover'] !== '' ? ' ew-gal-hov-' . h($v['effetto_hover']) : '') . '"' . ($v['clic'] === 'lightbox' ? ' data-ew-galleria' : '') . '>';
    foreach ($voci as [$x, $mie]) {
        $vis = $iniziale === '*' || in_array($iniziale, $mie, true);
        $g .= ewx_gal_voce($x, $v, $ctx['anteprima'], ' data-cat="' . h(implode(' ', $mie)) . '"' . ($vis ? '' : ' hidden'));
    }
    $g .= '</div>';
    return '<div class="ew-gf" data-ew-gf>' . $filtri . '<p class="ew-nascosto-vista" aria-live="polite" data-ew-gf-conta></p>' . $g . '</div>';
});

// ---------------- Loghi e testo scorrevoli

ewx_registra('scorrevole', 'Loghi e testo scorrevoli', 'infinito', 'Elementi', [
    ec('tipo', 'scelta', 'Cosa scorre', 'loghi', 'contenuto', ['opzioni' => ['loghi' => 'Una fila di loghi', 'testo' => 'Una frase'], 'sezione' => 'Contenuto']),
    ec('loghi', 'ripetitore', 'Loghi', [], 'contenuto', ['campi' => [
        ec('immagine', 'immagine', 'Logo', ''), ec('alt', 'testo', 'Nome (per chi non vede)', ''), ec('link', 'testo', 'Link (facoltativo)', ''),
    ], 'titolo_voce' => 'alt', 'condizione' => ['tipo' => 'loghi'], 'sezione' => 'Contenuto', 'aiuto' => 'Loghi della stessa altezza, su sfondo trasparente (PNG o SVG), vengono meglio.']),
    ec('frase', 'testo', 'Frase', 'Spedizione gratuita sopra i 50 € · Ritiro in negozio · Pagamento sicuro', 'contenuto', ['condizione' => ['tipo' => 'testo'], 'sezione' => 'Contenuto', 'aiuto' => 'La frase si ripete senza fine. Il punto in mezzo (·) separa bene le parti.']),
    ec('velocita', 'numero', 'Durata di un giro (secondi: più alto, più lento)', 30, 'contenuto', ['min' => 5, 'max' => 180, 'sezione' => 'Movimento']),
    ec('direzione', 'scelta', 'Direzione', 'sinistra', 'contenuto', ['opzioni' => ['sinistra' => 'Verso sinistra', 'destra' => 'Verso destra'], 'sezione' => 'Movimento']),
    ec('pausa', 'interruttore', 'Si ferma al passaggio del mouse', true, 'contenuto', ['sezione' => 'Movimento', 'aiuto' => 'Il pulsante per fermarlo c\'è sempre; per chi ha chiesto al telefono o al computer meno movimento, sta fermo da solo.']),
    ec('sfuma', 'interruttore', 'Bordi sfumati', true, 'contenuto', ['sezione' => 'Movimento']),
    ec('grigio', 'interruttore', 'Loghi in grigio che si colorano al passaggio', false, 'contenuto', ['condizione' => ['tipo' => 'loghi'], 'sezione' => 'Aspetto']),
    ewx_var('altezza', 'Altezza dei loghi', '--ew-h', 'Aspetto', ['d' => ['v' => 48, 'u' => 'px'], 'm' => ['v' => 36, 'u' => 'px']]),
    ewx_var('spazio', 'Spazio fra gli elementi', '--ew-gap', 'Aspetto', ['d' => ['v' => 56, 'u' => 'px'], 'm' => ['v' => 32, 'u' => 'px']]),
    ...ewx_testo_stile('frase', 'Frase', ' .ew-scr-frase', '--ew-tc'),
    ewx_col('separatore_colore', 'Colore del punto fra le frasi', '--ew-ac', 'Frase'),
    ...ewx_riquadro_stile(' .ew-scr', 'Striscia'),
], function (array $v, array $ctx): string {
    $voci = [];
    if ($v['tipo'] === 'testo') {
        $f = trim((string)$v['frase']);
        if ($f === '') return ewx_vuoto('Scrivi la frase che scorre');
        $voci[] = '<span class="ew-scr-frase">' . h($f) . '</span>';
        $sep = '<span class="ew-scr-sep" aria-hidden="true">&#10022;</span>';
        $voci = [$voci[0] . $sep];
    } else {
        foreach ((array)$v['loghi'] as $x) {
            if (!is_array($x) || trim((string)($x['immagine'] ?? '')) === '') continue;
            // Senza loading="lazy": un logo fuori dalla striscia deve essere già pronto quando arriva.
            $img = str_replace(' loading="lazy"', '', ewx_img((string)$x['immagine'], (string)($x['alt'] ?? ''), 'ew-scr-logo'));
            $u = editor_link_sicuro((string)($x['link'] ?? ''));
            $voci[] = $u !== '' ? '<a href="' . h($u) . '"' . (preg_match('~^https?://~i', $u) ? ' target="_blank" rel="noopener"' : '') . '>' . $img . '</a>' : $img;
        }
        if (!$voci) return ewx_vuoto('Aggiungi i loghi');
    }
    // Ogni gruppo deve essere più largo della striscia: i pochi elementi si ripetono (le copie, nascoste a chi non vuole movimento).
    $min = $v['tipo'] === 'testo' ? 4 : 8;
    $gruppo = '';
    for ($i = 0, $n = count($voci); $i < max($min, $n); $i++) {
        $gruppo .= '<li' . ($i >= $n ? ' class="ew-scr-copia" aria-hidden="true"' : '') . '>' . ($i >= $n ? str_replace('<a ', '<a tabindex="-1" ', $voci[$i % $n]) : $voci[$i]) . '</li>';
    }
    $copia = str_replace(['<li>', '<a '], ['<li class="ew-scr-copia">', '<a tabindex="-1" '], $gruppo);
    $s = max(5, min(180, (int)$v['velocita']));
    $cl = 'ew-scr ew-scr-' . ($v['tipo'] === 'testo' ? 'testo' : 'loghi') . ($v['direzione'] === 'destra' ? ' ew-scr-destra' : '') . ($v['pausa'] ? ' ew-scr-pausa' : '')
        . ($v['sfuma'] ? ' ew-scr-sfuma' : '') . ($v['grigio'] && $v['tipo'] !== 'testo' ? ' ew-scr-grigio' : '');
    return '<div class="' . $cl . '" style="--ew-durata:' . $s . 's" data-ew-scr role="region" aria-label="' . ($v['tipo'] === 'testo' ? 'Frase che scorre' : 'Loghi che scorrono') . '">'
        . '<div class="ew-scr-vista"><div class="ew-scr-traccia"><ul class="ew-scr-gruppo">' . $gruppo . '</ul><ul class="ew-scr-gruppo" aria-hidden="true">' . $copia . '</ul></div></div>'
        . '<button type="button" class="ew-scr-ferma" aria-pressed="false"><span class="ew-scr-ico" aria-hidden="true"></span><span class="ew-nascosto-vista">Ferma lo scorrimento</span></button></div>';
});

// ---------------- Contenuto esterno con consenso

/** I servizi ammessi: nome, origini da aprire nella CSP (frame-src), icona. Booking.com non si lascia incorporare: solo il link. */
const EWE_FORNITORI = [
    'calendly' => ['Calendly', ['https://calendly.com'], 'calendario'],
    'spotify' => ['Spotify', ['https://open.spotify.com'], 'spotify'],
    'gcal' => ['Google Calendar', ['https://calendar.google.com'], 'calendario-giorno'],
    'typeform' => ['Typeform', ['https://form.typeform.com'], 'blocco-note'],
    'booking' => ['Booking.com', [], 'casa'],
];

/**
 * L'indirizzo incollato, letto per il suo servizio: [servizio, indirizzo da incorporare ('' per Booking), altezza, link].
 * Solo i servizi di EWE_FORNITORI e solo le loro forme di indirizzo; tutto il resto è null (niente HTML libero).
 */
function ewe_esterno(string $url, string $solo = ''): ?array {
    $url = trim($url);
    // Un calendario Google scritto come indirizzo del calendario (esempio@group.calendar.google.com).
    if (preg_match('/^[A-Za-z0-9._%+-]{1,120}@(group\.calendar\.google\.com|gmail\.com)$/', $url)) $url = 'https://calendar.google.com/calendar/embed?src=' . rawurlencode($url);
    if (!preg_match('~^https://[^\s"\'<>]{4,600}$~', $url)) return null;
    $p = parse_url($url);
    $host = strtolower((string)($p['host'] ?? ''));
    $path = (string)($p['path'] ?? '');
    if (isset($p['user']) || isset($p['pass']) || isset($p['port'])) return null;
    parse_str((string)($p['query'] ?? ''), $q);
    $r = null;
    if (in_array($host, ['calendly.com', 'www.calendly.com'], true) && preg_match('~^/[A-Za-z0-9_-]{1,80}(/[A-Za-z0-9_-]{1,80})?/?$~', $path)) {
        $r = ['calendly', 'https://calendly.com' . rtrim($path, '/') . '?hide_gdpr_banner=1&embed_type=Inline', 700, 'https://calendly.com' . rtrim($path, '/')];
    } elseif ($host === 'open.spotify.com' && preg_match('~^(?:/intl-[a-z]{2}(?:-[a-zA-Z]{2})?)?/(?:embed/)?(track|album|playlist|episode|show|artist)/([A-Za-z0-9]{22})~', $path, $m)) {
        $r = ['spotify', 'https://open.spotify.com/embed/' . $m[1] . '/' . $m[2], in_array($m[1], ['track', 'episode'], true) ? 152 : 352, 'https://open.spotify.com/' . $m[1] . '/' . $m[2]];
    } elseif ($host === 'calendar.google.com' && preg_match('~^/calendar(/u/\d)?/embed/?$~', $path) && !empty($q['src'])) {
        $src = array_values(array_filter((array)$q['src'], fn($x) => is_string($x) && preg_match('/^[A-Za-z0-9._%+#-]{1,200}@[A-Za-z0-9.-]{3,100}$|^[A-Za-z0-9._%+=-]{8,200}$/', $x)));
        if ($src) {
            $qq = implode('&', array_map(fn($x) => 'src=' . rawurlencode($x), $src)) . '&ctz=' . rawurlencode(preg_match('~^[A-Za-z_]+/[A-Za-z_]+$~', (string)($q['ctz'] ?? '')) ? $q['ctz'] : date_default_timezone_get())
                . (in_array($q['mode'] ?? '', ['WEEK', 'MONTH', 'AGENDA'], true) ? '&mode=' . $q['mode'] : '') . '&hl=it';
            $r = ['gcal', 'https://calendar.google.com/calendar/embed?' . $qq, 600, 'https://calendar.google.com/calendar/embed?' . $qq];
        }
    } elseif (preg_match('~^([a-z0-9-]{1,60}\.)?typeform\.com$~', $host) && preg_match('~^/to/([A-Za-z0-9]{4,20})/?$~', $path, $m)) {
        $r = ['typeform', 'https://form.typeform.com/to/' . $m[1] . '?typeform-embed=embed-widget', 560, 'https://form.typeform.com/to/' . $m[1]];
    } elseif (in_array($host, ['booking.com', 'www.booking.com'], true) && preg_match('~^/hotel/[a-z]{2}/[A-Za-z0-9._-]{1,120}\.html$~', $path)) {
        $r = ['booking', '', 0, 'https://www.booking.com' . $path];
    }
    if ($r && $solo !== '' && $r[0] !== $solo) return null;
    return $r;
}

ewx_registra('contenuto_esterno', 'Contenuto esterno (con consenso)', 'scudo-spunta', 'Interazione', [
    ec('indirizzo', 'testo', 'Indirizzo da incollare', '', 'contenuto', ['sezione' => 'Contenuto', 'aiuto' => 'L\'indirizzo della pagina di Calendly, di una playlist o di un brano di Spotify, di un calendario Google (Impostazioni del calendario, Integra il calendario), di un modulo Typeform o della tua struttura su Booking.com. Altri siti e codice HTML non si possono incollare qui.']),
    ec('servizio', 'scelta', 'Servizio', '', 'contenuto', ['opzioni' => ['' => 'Lo capisce dall\'indirizzo'] + array_map(fn($x) => $x[0], EWE_FORNITORI), 'sezione' => 'Contenuto']),
    ec('titolo', 'testo', 'Titolo del riquadro (facoltativo)', '', 'contenuto', ['sezione' => 'Contenuto', 'aiuto' => 'Vuoto: "Contenuto di" e il nome del servizio.']),
    ec('testo_pulsante', 'testo', 'Testo del pulsante (vuoto: Mostra il contenuto di ...)', '', 'contenuto', ['sezione' => 'Consenso']),
    ec('testo_privacy', 'testolungo', 'Frase sulla privacy (vuoto: quella standard)', '', 'contenuto', ['sezione' => 'Consenso', 'aiuto' => 'Quella standard: "Il contenuto arriva da ... Se lo mostri, ... riceve il tuo indirizzo IP e può usare i suoi cookie."']),
    ec('link_privacy', 'testo', 'Link dell\'informativa privacy', '/privacy-policy/', 'contenuto', ['sezione' => 'Consenso']),
    ewx_var('altezza', 'Altezza (vuoto: quella del servizio)', '--ew-h', 'Riquadro', '', ['intervalli' => ['px' => [120, 1400, 1]]]),
    ...ewx_testo_stile('testo', 'Testo del riquadro', ' .ew-est-privacy', '--ew-dc'),
    ...ewx_riquadro_stile(' .ew-est', 'Riquadro'),
], function (array $v, array $ctx): string {
    $solo = isset(EWE_FORNITORI[$v['servizio']]) ? $v['servizio'] : '';
    $e = ewe_esterno((string)$v['indirizzo'], $solo);
    if (!$e) return ewx_vuoto(trim((string)$v['indirizzo']) === '' ? 'Incolla l\'indirizzo di Calendly, Spotify, Google Calendar, Typeform o Booking.com' : 'Questo indirizzo non è di un servizio ammesso (Calendly, Spotify, Google Calendar, Typeform, Booking.com) o non si può incorporare');
    [$serv, $src, $alt, $link] = $e;
    [$nome, $origini, $ic] = EWE_FORNITORI[$serv];
    $titolo = trim((string)$v['titolo']) ?: 'Contenuto di ' . $nome;
    $priv = trim((string)$v['testo_privacy']) ?: 'Il contenuto arriva da ' . $nome . '. Se lo mostri, ' . $nome . ' riceve il tuo indirizzo IP e può usare i suoi cookie.';
    $lp = editor_link_sicuro((string)$v['link_privacy']);
    $privHtml = '<p class="ew-est-privacy">' . h($priv) . ($lp !== '' ? ' <a href="' . h($lp) . '">Informativa privacy</a>.' : '') . '</p>';
    $fuori = '<a class="ew-est-fuori" href="' . h($link) . '" target="_blank" rel="noopener">' . ($serv === 'booking' ? 'Vedi la struttura su Booking.com' : 'Apri su ' . h($nome)) . icona_svg('link-esterno') . '<span class="ew-nascosto-vista"> (si apre in una nuova scheda)</span></a>';
    if ($serv === 'booking') {
        // Booking.com non lascia mettere le sue pagine dentro un altro sito: un riquadro con il link, niente di caricato.
        return '<div class="ew-est ew-est-booking"><div class="ew-est-consenso">' . icona_svg($ic) . '<p class="ew-est-titolo">' . h($titolo) . '</p>'
            . '<p class="ew-est-privacy">Le disponibilità e i prezzi sono su Booking.com: la pagina si apre lì, in una nuova scheda.</p>'
            . '<a class="btn btn-viola" href="' . h($link) . '" target="_blank" rel="noopener">' . h(trim((string)$v['testo_pulsante']) ?: 'Vedi la disponibilità su Booking.com') . '<span class="ew-nascosto-vista"> (si apre in una nuova scheda)</span></a></div></div>';
    }
    $h = $alt . 'px'; // l'altezza del servizio; quella scelta nel widget (--ew-h) vince, nel foglio di stile
    $pulsante = h(trim((string)$v['testo_pulsante']) ?: 'Mostra il contenuto di ' . $nome);
    if ($ctx['anteprima']) {
        return '<div class="ew-est ew-est-' . $serv . '" style="--ew-hp:' . h($h) . '"><div class="ew-est-consenso">' . icona_svg($ic) . '<p class="ew-est-titolo">' . h($titolo) . '</p>' . $privHtml
            . '<span class="btn btn-viola ew-est-finto">' . $pulsante . '</span><p class="ew-est-nota">Sul sito il contenuto si carica solo dopo il clic della persona.</p></div></div>';
    }
    // La CSP di questa pagina, e solo di questa, si apre al dominio del servizio (sicurezza_csp_pagina).
    if (function_exists('sicurezza_csp_pagina')) foreach ($origini as $o) sicurezza_csp_pagina('frame-src', $o);
    return '<div class="ew-est ew-est-' . $serv . '" style="--ew-hp:' . h($h) . '" data-ew-est data-src="' . h($src) . '" data-titolo="' . h($titolo) . '" data-servizio="' . $serv . '">'
        . '<div class="ew-est-consenso">' . icona_svg($ic) . '<p class="ew-est-titolo">' . h($titolo) . '</p>' . $privHtml
        . '<button type="button" class="btn btn-viola" data-ew-est-mostra>' . $pulsante . '</button>' . $fuori . '</div></div>';
});
