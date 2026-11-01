<?php
/**
 * Le quindici infografiche disegnate: imbuto, piramide, matrice, ciclo, ciambella, cerchi che si toccano, ragnatela,
 * lancetta, iceberg, omini, viaggio, bilancia, mappa di calore, cerchi concentrici, barre al cento per cento.
 * Widget del nucleo nel gruppo Infografiche (prefisso ig_), con la stessa cornice delle altre quattordici (igx_cornice,
 * igx_controlli_testa, igx_controlli_coda in editor-widget.php).
 *
 * Il disegno è un SVG fatto qui, con i numeri già controllati: testo vero dentro l'SVG, role="img" e aria-label con tutti i
 * dati. Ogni infografica esce due volte: larga (viewBox di IGS_LARGO) e stretta (IGS_STRETTO), e il foglio
 * sito/assets/infografiche-svg.css mostra quella giusta secondo la larghezza del riquadro (container query): al telefono le
 * scritte restano grandi quanto al computer. I colori sono le classi igx-c-* (variabili --c, --c2), quindi seguono il tema
 * del sito; il testo sopra un colore pieno (classe igs-su) si sceglie bianco o nero dal foglio, con il contrasto migliore.
 *
 * Solo funzioni e costanti: niente variabili in cima al file.
 */

const IGS_TIPI = ['ig_imbuto', 'ig_piramide', 'ig_matrice', 'ig_ciclo', 'ig_ciambella', 'ig_venn', 'ig_radar', 'ig_lancetta', 'ig_iceberg', 'ig_omini', 'ig_viaggio', 'ig_bilancia', 'ig_calore', 'ig_cerchi', 'ig_barre100'];
const IGS_LARGO = 560;
const IGS_STRETTO = 320;
const IGS_CSS = 'infografiche-svg.css';

// ------------------------------------------------------------
// Controlli
// ------------------------------------------------------------

/** I colori di una voce: la palette delle infografiche più il grigio (per "Altro", "Elimina"). */
function igs_opzioni_colore(bool $conVuoto = false): array {
    $o = igx_opzioni_colore(false, $conVuoto);
    $o['grigio'] = 'Grigio';
    return $o;
}

/** I due controlli del colore di una voce, con il valore di partenza. */
function igs_ctl_colori(string $predefinito = ''): array {
    return [ec('colore', 'scelta', 'Colore', $predefinito, 'contenuto', ['opzioni' => igs_opzioni_colore(true)]), ec('colore_libero', 'colore', 'Colore libero (vince sulla palette)', '')];
}

/** Il colore delle voci a livello di widget: usato dalle voci con il colore "Come il widget". */
function igs_ctl_colore(string $predefinito = 'sequenza'): array {
    return ec('colore', 'scelta', 'Colore delle voci senza un colore loro', $predefinito, 'stile', ['opzioni' => ['sequenza' => 'A rotazione (rosso, oro, verde, ciano, viola, scuro)'] + igs_opzioni_colore()]);
}

/** La cornice delle ig_* con l'occhiello bianco (si legge su tutte le testate) e la testata scelta. */
function igs_testa(string $occhiello, string $titolo, string $testata): array {
    $c = igx_controlli_testa($occhiello, $titolo, $testata);
    foreach ($c as &$x) if ($x['nome'] === 'occhiello_colore') $x['predefinito'] = 'bianco';
    unset($x);
    return $c;
}

/** Una voce di partenza di un ripetitore con i colori. */
function igs_voce(array $campi, string $colore = ''): array {
    return $campi + ['colore' => $colore, 'colore_libero' => ''];
}

/** Le definizioni dei quindici widget. */
function igs_definizioni(): array {
    $c = igx_controlli_coda();
    $d = [];
    $def = fn(string $et, string $icona, array $testa, array $controlli) => ['etichetta' => $et, 'icona' => $icona, 'gruppo' => 'Infografiche', 'controlli' => array_merge($testa, $controlli, $c)];

    $d['ig_imbuto'] = $def('Imbuto', '▽', igs_testa('DAL VISITATORE AL CLIENTE', 'Quanti arrivano in fondo', 'ciano'), [
        ec('gradini', 'ripetitore', 'Gradini, dall\'alto (da 3 a 6)', [
            igs_voce(['nome' => 'Visite al sito', 'valore' => 1200], 'ciano_scuro'), igs_voce(['nome' => 'Contatti', 'valore' => 340], 'ciano'),
            igs_voce(['nome' => 'Appuntamenti', 'valore' => 85], 'verde'), igs_voce(['nome' => 'Clienti', 'valore' => 32], 'oro'),
        ], 'contenuto', ['campi' => [ec('nome', 'testo', 'Nome del gradino', ''), ec('valore', 'numero', 'Quanti', 0, 'contenuto', ['min' => 0, 'max' => 1000000000]), ...igs_ctl_colori()], 'titolo_voce' => 'nome', 'aiuto' => 'Da 3 a 6 gradini. La resa fra un gradino e il precedente si calcola da sola.']),
        ec('mostra_resa', 'interruttore', 'Scrivi la resa fra un gradino e l\'altro', true),
        ec('etichetta_resa', 'testo', 'Titolo della colonna della resa', 'resa'),
        igs_ctl_colore(),
    ]);
    $d['ig_piramide'] = $def('Piramide a livelli', '△', igs_testa('I LIVELLI DEL CAMBIAMENTO', 'Dalla base alla punta', 'rosso'), [
        ec('livelli', 'ripetitore', 'Livelli, dalla punta alla base (da 3 a 6)', [
            igs_voce(['nome' => 'Identità', 'testo' => 'chi sono'], 'rosso'), igs_voce(['nome' => 'Convinzioni', 'testo' => 'perché lo faccio'], 'arancio'),
            igs_voce(['nome' => 'Capacità', 'testo' => 'come lo faccio'], 'oro'), igs_voce(['nome' => 'Comportamenti', 'testo' => 'cosa faccio'], 'verde'),
            igs_voce(['nome' => 'Ambiente', 'testo' => 'dove e con chi'], 'ciano'),
        ], 'contenuto', ['campi' => [ec('nome', 'testo', 'Nome del livello', ''), ec('testo', 'testo', 'Riga di spiegazione accanto', ''), ...igs_ctl_colori()], 'titolo_voce' => 'nome', 'aiuto' => 'Da 3 a 6 livelli. Il primo è la punta.']),
        igs_ctl_colore(),
    ]);
    $d['ig_matrice'] = $def('Matrice a quattro quadranti', '⊞', igs_testa('DOVE METTERE IL TEMPO', 'Urgente o importante?', 'scuro'), [
        ec('asse_x_a', 'testo', 'Asse orizzontale: colonna di sinistra', 'URGENTE'),
        ec('asse_x_b', 'testo', 'Asse orizzontale: colonna di destra', 'NON URGENTE'),
        ec('asse_y_a', 'testo', 'Asse verticale: riga in alto', 'IMPORTANTE'),
        ec('asse_y_b', 'testo', 'Asse verticale: riga in basso', 'NON IMPORTANTE'),
        ec('quadranti', 'ripetitore', 'Quadranti: in alto a sinistra, in alto a destra, in basso a sinistra, in basso a destra', [
            igs_voce(['titolo' => 'Fai subito', 'testo' => 'Il cliente che aspetta'], 'rosso'), igs_voce(['titolo' => 'Pianifica', 'testo' => 'Il corso di aggiornamento'], 'oro'),
            igs_voce(['titolo' => 'Delega', 'testo' => 'Le email di routine'], 'ciano'), igs_voce(['titolo' => 'Elimina', 'testo' => 'Le riunioni senza scopo'], 'grigio'),
        ], 'contenuto', ['campi' => [ec('titolo', 'testo', 'Titolo del quadrante', ''), ec('testo', 'testolungo', 'Esempi (uno per riga)', ''), ...igs_ctl_colori()], 'titolo_voce' => 'titolo', 'aiuto' => 'Quattro quadranti: dal quinto in poi non si vedono.']),
    ]);
    $d['ig_ciclo'] = $def('Ciclo', '↻', igs_testa('IL METODO CHE SI RIPETE', 'Un giro dopo l\'altro', 'verde'), [
        ec('tappe', 'ripetitore', 'Tappe, in senso orario dall\'alto (da 3 a 6)', [
            igs_voce(['nome' => 'Pianifica'], 'ciano_scuro'), igs_voce(['nome' => 'Fai'], 'verde'), igs_voce(['nome' => 'Verifica'], 'oro'), igs_voce(['nome' => 'Correggi'], 'rosso'),
        ], 'contenuto', ['campi' => [ec('nome', 'testo', 'Nome della tappa', ''), ...igs_ctl_colori()], 'titolo_voce' => 'nome', 'aiuto' => 'Da 3 a 6 tappe. Nomi corti: stanno dentro il cerchio.']),
        ec('centro_sopra', 'testo', 'Al centro: riga piccola', 'Un giro'),
        ec('centro', 'testo', 'Al centro: riga in grassetto', 'al mese'),
        igs_ctl_colore(),
    ]);
    $d['ig_ciambella'] = $def('Ciambella', '◔', igs_testa('DA DOVE ARRIVANO I CLIENTI', 'Le parti del tutto', 'ciano'), [
        ec('voci', 'ripetitore', 'Voci (da 2 a 6)', [
            igs_voce(['nome' => 'Passaparola', 'percentuale' => 45], 'ciano_scuro'), igs_voce(['nome' => 'Google', 'percentuale' => 30], 'oro'),
            igs_voce(['nome' => 'Social', 'percentuale' => 15], 'rosso'), igs_voce(['nome' => 'Altro', 'percentuale' => 10], 'grigio'),
        ], 'contenuto', ['campi' => [ec('nome', 'testo', 'Nome', ''), ec('percentuale', 'numero', 'Percentuale', 0, 'contenuto', ['min' => 0, 'max' => 100, 'passo' => 0.1]), ...igs_ctl_colori()], 'titolo_voce' => 'nome', 'aiuto' => 'Da 2 a 6 voci. Le percentuali devono fare 100: se no, l\'editor te lo dice.']),
        ec('centro', 'scelta', 'Al centro', 'piu_grande', 'contenuto', ['opzioni' => ['piu_grande' => 'Il valore più grande', 'nessuno' => 'Niente']]),
        ec('centro_testo', 'testo', 'Riga sotto il numero al centro (vuota: il nome della voce più grande)', ''),
        igs_ctl_colore(),
    ]);
    $d['ig_venn'] = $def('Cerchi che si toccano', '⚭', igs_testa('IL LAVORO GIUSTO PER TE', 'Dove si incontrano', 'oro_scuro'), [
        ec('cerchi', 'ripetitore', 'Cerchi (2 o 3)', [
            igs_voce(['sopra' => 'Ciò che', 'nome' => 'ami'], 'rosso'), igs_voce(['sopra' => 'Ciò in cui', 'nome' => 'sei bravo'], 'ciano'), igs_voce(['sopra' => 'Ciò per cui', 'nome' => 'ti pagano'], 'oro'),
        ], 'contenuto', ['campi' => [ec('sopra', 'testo', 'Riga piccola (facoltativa)', ''), ec('nome', 'testo', 'Nome in grassetto', ''), ...igs_ctl_colori()], 'titolo_voce' => 'nome', 'aiuto' => 'Due o tre cerchi: dal quarto in poi non si vedono.']),
        ec('centro_sopra', 'testo', 'Incrocio: riga piccola', 'Il tuo'),
        ec('centro', 'testo', 'Incrocio: riga in grassetto', 'lavoro'),
        igs_ctl_colore(),
    ]);
    $d['ig_radar'] = $def('Ragnatela', '✳', igs_testa('COME STAI, PRIMA E DOPO', 'Il profilo, prima e dopo', 'verde'), [
        ec('aspetti', 'ripetitore', 'Aspetti, in senso orario dall\'alto (da 4 a 8)', [
            ['nome' => 'Energia', 'prima' => 4, 'dopo' => 8.8], ['nome' => 'Chiarezza', 'prima' => 5, 'dopo' => 7.5], ['nome' => 'Relazioni', 'prima' => 3, 'dopo' => 7],
            ['nome' => 'Sonno', 'prima' => 6, 'dopo' => 9], ['nome' => 'Lavoro', 'prima' => 3.5, 'dopo' => 8], ['nome' => 'Fiducia', 'prima' => 5, 'dopo' => 7.8],
        ], 'contenuto', ['campi' => [ec('nome', 'testo', 'Aspetto', ''), ec('prima', 'numero', 'Prima (da 0 a 10)', 5, 'contenuto', ['min' => 0, 'max' => 10, 'passo' => 0.1]), ec('dopo', 'numero', 'Dopo (da 0 a 10)', 5, 'contenuto', ['min' => 0, 'max' => 10, 'passo' => 0.1])], 'titolo_voce' => 'nome', 'aiuto' => 'Da 4 a 8 aspetti, con il valore di prima e di dopo da 0 a 10.']),
        ec('etichetta_prima', 'testo', 'Legenda: prima', 'Prima'),
        ec('etichetta_dopo', 'testo', 'Legenda: dopo', 'Dopo 3 mesi'),
        ec('mostra_prima', 'interruttore', 'Disegna anche il "prima"', true),
        ec('colore_dopo', 'scelta', 'Colore del "dopo"', 'verde', 'stile', ['opzioni' => igs_opzioni_colore()]),
        ec('colore_dopo_libero', 'colore', 'Colore libero del "dopo"', '', 'stile'),
    ]);
    $d['ig_lancetta'] = $def('Lancetta', '◠', igs_testa('IL VOTO DEI CLIENTI', 'Quanto siamo piaciuti', 'scuro'), [
        ec('valore', 'numero', 'Valore', 8.6, 'contenuto', ['min' => -1000000, 'max' => 1000000, 'passo' => 0.1]),
        ec('minimo', 'numero', 'Minimo della scala', 0, 'contenuto', ['min' => -1000000, 'max' => 1000000, 'passo' => 0.1]),
        ec('massimo', 'numero', 'Massimo della scala', 10, 'contenuto', ['min' => -1000000, 'max' => 1000000, 'passo' => 0.1]),
        ec('soglia_1', 'numero', 'Dove finisce la prima zona', 5, 'contenuto', ['min' => -1000000, 'max' => 1000000, 'passo' => 0.1]),
        ec('soglia_2', 'numero', 'Dove finisce la seconda zona', 7.5, 'contenuto', ['min' => -1000000, 'max' => 1000000, 'passo' => 0.1]),
        ec('verso', 'scelta', 'Le zone', 'alto', 'contenuto', ['opzioni' => ['alto' => 'Più alto è meglio: rossa, gialla, verde', 'basso' => 'Più basso è meglio: verde, gialla, rossa']]),
        ec('decimali', 'scelta', 'Cifre dopo la virgola', '1', 'contenuto', ['opzioni' => ['0' => 'Nessuna', '1' => 'Una', '2' => 'Due']]),
        ec('suffisso', 'testo', 'Dopo il numero (facoltativo: %, €, /10)', ''),
        ec('testo', 'testo', 'Riga sotto', 'voto medio dei clienti, 214 risposte'),
    ]);
    $d['ig_iceberg'] = $def('Iceberg', '⛰', igs_testa('QUELLO CHE NON SI VEDE', 'Sotto la superficie', 'ciano'), [
        ec('sopra_titolo', 'testo', 'Sopra l\'acqua: frase in grassetto', 'Il risultato'),
        ec('sopra_testo', 'testo', 'Sopra l\'acqua: seconda riga', 'che tutti vedono'),
        ec('voci', 'ripetitore', 'Sotto l\'acqua (da 2 a 6)', [['testo' => 'Anni di pratica'], ['testo' => 'Errori e tentativi'], ['testo' => 'Costanza ogni giorno'], ['testo' => 'Le rinunce']],
            'contenuto', ['campi' => [ec('testo', 'testo', 'Voce', '')], 'titolo_voce' => 'testo', 'aiuto' => 'Da 2 a 6 voci, corte.']),
        ec('etichetta_sopra', 'testo', 'Scritta sopra la linea dell\'acqua', 'SOPRA'),
        ec('etichetta_sotto', 'testo', 'Scritta sotto la linea dell\'acqua', 'SOTTO'),
        ec('colore', 'scelta', 'Colore dell\'iceberg', 'ciano_scuro', 'stile', ['opzioni' => igs_opzioni_colore()]),
    ]);
    $d['ig_omini'] = $def('Omini', '♙', igs_testa('IL PASSAPAROLA CONTA', 'Sette su dieci', 'rosso'), [
        ec('quanti', 'numero', 'Quanti', 7, 'contenuto', ['min' => 0, 'max' => 20]),
        ec('totale', 'numero', 'Su quanti (fino a 20)', 10, 'contenuto', ['min' => 1, 'max' => 20]),
        ec('frase', 'testo', 'Frase', 'clienti arrivano dal passaparola'),
        ec('simbolo', 'scelta', 'Simbolo', 'persona', 'contenuto', ['opzioni' => ['persona' => 'Persone', 'casa' => 'Case', 'cuore' => 'Cuori', 'stella' => 'Stelle']]),
        ec('colore', 'scelta', 'Colore', 'rosso', 'stile', ['opzioni' => igs_opzioni_colore()]),
    ]);
    $d['ig_viaggio'] = $def('Il viaggio', '⚑', igs_testa('IL PERCORSO DEL CLIENTE', 'Le tappe del viaggio', 'viola'), [
        ec('tappe', 'ripetitore', 'Tappe (da 3 a 7)', [
            igs_voce(['nome' => 'La chiamata'], 'ciano_scuro'), igs_voce(['nome' => 'Il dubbio'], 'oro'), igs_voce(['nome' => 'Il mentore'], 'verde'),
            igs_voce(['nome' => 'La prova'], 'rosso'), igs_voce(['nome' => 'Il ritorno'], 'rosso'),
        ], 'contenuto', ['campi' => [ec('nome', 'testo', 'Nome della tappa', ''), ...igs_ctl_colori()], 'titolo_voce' => 'nome', 'aiuto' => 'Da 3 a 7 tappe. L\'ultima è la meta.']),
        ec('bandiera', 'interruttore', 'Bandiera sull\'ultima tappa', true),
        igs_ctl_colore(),
    ]);
    $d['ig_bilancia'] = $def('Bilancia', '⚖', igs_testa('CONVIENE?', 'Pro e contro', 'scuro_sfumato'), [
        ec('etichetta_pro', 'testo', 'Titolo del piatto di sinistra', 'PRO'),
        ec('pro', 'ripetitore', 'Voci a sinistra (fino a 8)', [['testo' => 'Più clienti'], ['testo' => 'Meno attesa'], ['testo' => 'Prezzo chiaro']], 'contenuto', ['campi' => [ec('testo', 'testo', 'Voce', '')], 'titolo_voce' => 'testo']),
        ec('etichetta_contro', 'testo', 'Titolo del piatto di destra', 'CONTRO'),
        ec('contro', 'ripetitore', 'Voci a destra (fino a 8)', [['testo' => 'Costo iniziale'], ['testo' => 'Un mese per partire']], 'contenuto', ['campi' => [ec('testo', 'testo', 'Voce', '')], 'titolo_voce' => 'testo']),
        ec('inclinazione', 'scelta', 'Da che parte pende', 'auto', 'contenuto', ['opzioni' => ['auto' => 'Da sola: dalla parte con più voci', 'pro' => 'A sinistra', 'contro' => 'A destra', 'pari' => 'In equilibrio']]),
        ec('colore_pro', 'scelta', 'Colore del piatto di sinistra', 'verde', 'stile', ['opzioni' => igs_opzioni_colore()]),
        ec('colore_contro', 'scelta', 'Colore del piatto di destra', 'rosso', 'stile', ['opzioni' => igs_opzioni_colore()]),
    ]);
    $d['ig_calore'] = $def('Mappa di calore', '▩', igs_testa('QUANDO TI CERCANO', 'I momenti più richiesti', 'ciano'), [
        ec('colonne', 'testo', 'Nomi delle colonne, separati dalla virgola (fino a 12)', 'Lun, Mar, Mer, Gio, Ven, Sab, Dom'),
        ec('righe', 'ripetitore', 'Righe (fino a 12)', [
            ['nome' => '8-10', 'valori' => '1 1 1 1 1 3 0'], ['nome' => '10-12', 'valori' => '2 2 2 2 3 4 1'], ['nome' => '12-14', 'valori' => '1 1 1 1 1 2 0'],
            ['nome' => '14-16', 'valori' => '1 2 1 2 2 1 0'], ['nome' => '16-18', 'valori' => '2 2 3 2 3 1 0'], ['nome' => '18-20', 'valori' => '3 4 3 4 2 0 0'],
        ], 'contenuto', ['campi' => [ec('nome', 'testo', 'Nome della riga', ''), ec('valori', 'testo', 'Valori da 0 a 4, uno per colonna (es. 1 2 4 0)', '')], 'titolo_voce' => 'nome', 'aiuto' => 'Per ogni riga un valore da 0 (niente) a 4 (moltissimo) per ogni colonna, separati da spazi.']),
        ec('etichetta_poco', 'testo', 'Legenda: valore più basso', 'Poco'),
        ec('etichetta_tanto', 'testo', 'Legenda: valore più alto', 'Tanto'),
        ec('colore', 'scelta', 'Colore', 'ciano_scuro', 'stile', ['opzioni' => igs_opzioni_colore()]),
    ]);
    $d['ig_cerchi'] = $def('Cerchi concentrici', '◎', igs_testa('PARTI DAL PERCHÉ', 'Dal centro all\'esterno', 'ciano'), [
        ec('cerchi', 'ripetitore', 'Cerchi, dall\'esterno al centro (da 2 a 4)', [['nome' => 'Cosa', 'testo' => 'i servizi che vendi'], ['nome' => 'Come', 'testo' => 'il tuo metodo, diverso'], ['nome' => 'Perché', 'testo' => 'la ragione per cui esisti']],
            'contenuto', ['campi' => [ec('nome', 'testo', 'Nome', ''), ec('testo', 'testo', 'Riga di spiegazione accanto', '')], 'titolo_voce' => 'nome', 'aiuto' => 'Da 2 a 4 cerchi. Il primo è il più grande, l\'ultimo il centro.']),
        ec('colore', 'scelta', 'Colore', 'ciano', 'stile', ['opzioni' => igs_opzioni_colore()]),
    ]);
    $d['ig_barre100'] = $def('Barre al cento per cento', '▬', igs_testa('COME PASSI LA SETTIMANA', 'Come si divide il tempo', 'oro_scuro'), [
        ec('voci', 'ripetitore', 'Voci, con il loro colore (da 2 a 6)', [
            igs_voce(['nome' => 'Lavoro'], 'ciano_scuro'), igs_voce(['nome' => 'Urgenze'], 'rosso'), igs_voce(['nome' => 'Te stesso'], 'oro'), igs_voce(['nome' => 'Famiglia'], 'verde'),
        ], 'contenuto', ['campi' => [ec('nome', 'testo', 'Nome', ''), ...igs_ctl_colori()], 'titolo_voce' => 'nome', 'aiuto' => 'Da 2 a 6 voci: le parti di ogni barra, nell\'ordine.']),
        ec('barre', 'ripetitore', 'Barre (da 1 a 4)', [['nome' => 'Prima', 'valori' => '55 25 5 15'], ['nome' => 'Dopo 3 mesi', 'valori' => '45 10 20 25']],
            'contenuto', ['campi' => [ec('nome', 'testo', 'Nome della barra', ''), ec('valori', 'testo', 'Percentuali delle voci, nell\'ordine (es. 55 25 5 15)', '')], 'titolo_voce' => 'nome', 'aiuto' => 'Ogni barra fa 100: se le percentuali non fanno 100, l\'editor te lo dice.']),
        ec('mostra_percentuali', 'interruttore', 'Scrivi le percentuali dentro le barre', true),
        igs_ctl_colore(),
    ]);
    return $d;
}

// ------------------------------------------------------------
// Testo e numeri
// ------------------------------------------------------------

/** Un testo semplice di un campo: entità decodificate, niente caratteri di controllo né a capo, spazi singoli. */
function igs_pulito($s, int $max = 300): string {
    $s = html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = (string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
    $s = trim((string)preg_replace('/\s+/u', ' ', $s));
    return mb_substr($s, 0, $max);
}

/** Larghezza stimata di un testo in unità dell'SVG (carattere senza grazie). */
function igs_largh(string $s, float $fs, bool $grassetto = false): float {
    $w = 0.0;
    foreach (mb_str_split($s) as $ch) {
        if ($ch === ' ') $w += .28;
        elseif (strlen($ch) === 1 && strpos("iljtfr.,;:'!|()[]", $ch) !== false) $w += .32;
        elseif (strlen($ch) === 1 && strpos('mwMW', $ch) !== false) $w += .86;
        elseif (preg_match('/\p{Lu}/u', $ch)) $w += .69;
        elseif (preg_match('/\d/', $ch)) $w += .59;
        else $w += .56;
    }
    // 1,1: misurato nel browser con Inter, la stima sta fra 0,88 e 1,1 della larghezza vera: si tiene il margine.
    return $w * $fs * ($grassetto ? 1.08 : 1) * 1.1;
}

/** Taglia una riga con i puntini perché stia in $max. */
function igs_tronca(string $s, float $fs, float $max, bool $g = false): string {
    if (igs_largh($s, $fs, $g) <= $max) return $s;
    while ($s !== '' && igs_largh($s . '…', $fs, $g) > $max) $s = rtrim(mb_substr($s, 0, -1));
    return $s === '' ? '' : $s . '…';
}

/** Manda a capo un testo in righe larghe al massimo $max, non più di $righe (l'ultima con i puntini, se serve). */
function igs_a_capo($s, float $fs, float $max, int $righe = 2, bool $g = false): array {
    $s = igs_pulito($s);
    if ($s === '' || $max < $fs) return [];
    $out = []; $cur = '';
    foreach (explode(' ', $s) as $p) {
        $prova = $cur === '' ? $p : $cur . ' ' . $p;
        if (igs_largh($prova, $fs, $g) <= $max) { $cur = $prova; continue; }
        if ($cur !== '') $out[] = $cur;
        $cur = $p;
        while (igs_largh($cur, $fs, $g) > $max && mb_strlen($cur) > 1) {
            $k = mb_strlen($cur) - 1;
            while ($k > 1 && igs_largh(mb_substr($cur, 0, $k), $fs, $g) > $max) $k--;
            $out[] = mb_substr($cur, 0, $k);
            $cur = mb_substr($cur, $k);
        }
        if (count($out) > $righe) break;
    }
    if ($cur !== '') $out[] = $cur;
    if (count($out) > $righe) {
        $out = array_slice($out, 0, $righe);
        $out[$righe - 1] = igs_tronca($out[$righe - 1] . ' …', $fs, $max, $g);
        if (!str_ends_with($out[$righe - 1], '…')) $out[$righe - 1] .= '…';
    }
    return $out;
}

/** Un numero con un decimale al massimo: 2 cifre se servono. */
function igs_n(float $x): string {
    $s = rtrim(rtrim(number_format($x, 1, '.', ''), '0'), '.');
    return $s === '-0' ? '0' : $s;
}

/** Un numero all'italiana: 1.200, 8,6. $dec < 0: decimali solo se servono (al massimo 1). */
function igs_num(float $n, int $dec = -1): string {
    if ($dec < 0) $dec = abs($n - round($n)) < 0.05 ? 0 : 1;
    return number_format($n, $dec, ',', '.');
}

/**
 * Un <text> con una o più righe. $y è la linea di base della prima riga. $peso 400-700, $classe: igs-t (titoli), igs-tm
 * (testo), igs-su (sopra un colore pieno), igs-inv (sopra il colore dei titoli).
 */
function igs_testo(float $x, float $y, array $righe, string $classe, float $fs, string $anchor = 'middle', int $peso = 400, float $lh = 1.25, string $extra = ''): string {
    if (!$righe) return '';
    $o = '<text x="' . igs_n($x) . '" y="' . igs_n($y) . '" class="' . $classe . '" font-size="' . igs_n($fs) . '"' . ($peso !== 400 ? ' font-weight="' . $peso . '"' : '') . ($anchor !== 'start' ? ' text-anchor="' . $anchor . '"' : '') . $extra . '>';
    if (count($righe) === 1) return $o . h((string)$righe[0]) . '</text>';
    foreach (array_values($righe) as $i => $r) $o .= '<tspan x="' . igs_n($x) . '"' . ($i ? ' dy="' . igs_n($fs * $lh) . '"' : '') . '>' . h((string)$r) . '</tspan>';
    return $o . '</text>';
}

/** La linea di base della prima riga di un blocco di $n righe centrato in verticale su $cy. */
function igs_y_centro(float $cy, int $n, float $fs, float $lh = 1.25): float {
    return $cy - ($n - 1) * $fs * $lh / 2 + $fs * .35;
}

/** Valori numerici da un testo ("55 25 5 15", "1,5; 2"): al massimo $max, ognuno fra $min e $tetto. */
function igs_valori($s, int $max, float $min, float $tetto): array {
    preg_match_all('/-?\d+(?:[.,]\d+)?/', mb_substr((string)$s, 0, 400), $m);
    return array_map(fn($x) => max($min, min($tetto, (float)str_replace(',', '.', $x))), array_slice($m[0], 0, $max));
}

// ------------------------------------------------------------
// Colori
// ------------------------------------------------------------

/**
 * L'attributo del colore di una voce per un <g>: la classe della palette (igx-c-*, igs-c-grigio) o le variabili del colore
 * libero. Ordine: colore libero della voce; colore della voce; colore libero di tutte le voci (se la voce non ha un colore
 * suo); colore del widget ($v['colore'], "sequenza" a rotazione); $pred.
 */
function igs_c($voce, array $v, int $i, string $pred = 'sequenza'): string {
    $voce = is_array($voce) ? $voce : ['colore' => (string)$voce];
    $lib = igx_esa($voce['colore_libero'] ?? '');
    $nome = trim((string)($voce['colore'] ?? ''));
    if ($lib === '' && $nome === '') $lib = igx_esa($v['colore_libero'] ?? '');
    if ($lib !== '') return ' style="--c:' . $lib . ';--c2:' . $lib . '"';
    $ok = fn($n) => $n === 'grigio' || isset(igx_palette()[$n]);
    if (!$ok($nome)) $nome = (string)($v['colore'] ?? $pred);
    if (!$ok($nome) && $nome !== 'sequenza') $nome = $pred;
    if ($nome === 'sequenza' || !$ok($nome)) $nome = IGX_SEQUENZA[$i % count(IGX_SEQUENZA)];
    return ' class="' . ($nome === 'grigio' ? 'igs-c-grigio' : 'igx-c-' . $nome) . '"';
}

/** Il colore fisso di una parte (iceberg, omini, bilancia...): un nome della palette o il grigio, con il colore libero. */
function igs_cf(string $nome, $libero = ''): string {
    return igs_c(['colore' => $nome, 'colore_libero' => (string)$libero], [], 0, $nome);
}

// ------------------------------------------------------------
// Disegno: cornice comune
// ------------------------------------------------------------

/** Le voci non vuote di un ripetitore: quelle con almeno un campo di $chiavi scritto. */
function igs_voci($lista, array $chiavi, int $max): array {
    $out = [];
    foreach (array_values(is_array($lista) ? $lista : []) as $x) {
        if (!is_array($x)) continue;
        foreach ($chiavi as $k) if (igs_pulito($x[$k] ?? '') !== '' || (isset($x[$k]) && is_numeric($x[$k]))) { $out[] = $x; break; }
    }
    return array_slice($out, 0, $max);
}

/** Quante voci arrivano e quante se ne usano: la nota per l'editor se sono troppe. */
function igs_nota_troppe($lista, int $max, string $cosa): string {
    $n = is_array($lista) ? count($lista) : 0;
    return $n > $max ? 'Si vedono solo ' . $max . ' ' . $cosa . ' su ' . $n . ': le altre restano salvate ma non compaiono.' : '';
}

/**
 * Il widget ig_* disegnato: le note per l'editor, le due versioni dell'SVG, la cornice.
 * Ogni igs_<nome>($v, $W) restituisce ['svg' => contenuto, 'h' => altezza, 'aria' => testo per chi non vede,
 * 'note' => [frasi per l'editor], 'vuoto' => frase se non c'è niente da disegnare].
 */
function igs_disegna(string $tipo, array $v): ?string {
    $f = 'igs_' . substr($tipo, 3);
    if (!in_array($tipo, IGS_TIPI, true) || !function_exists($f)) return null;
    $largo = $f($v, IGS_LARGO);
    $note = implode('', array_map(fn($x) => '<p class="ed-vuoto igs-nota">' . h($x) . '</p>', array_filter((array)($largo['note'] ?? []))));
    if (!empty($largo['vuoto'])) return igx_cornice($tipo, $v, $note . '<div class="ed-vuoto">' . h($largo['vuoto']) . '</div>', igs_classi_cornice($v));
    $stretto = $f($v, IGS_STRETTO);
    $svg = fn(array $r, int $W, string $cl) => '<svg class="igs-svg ' . $cl . '" viewBox="0 0 ' . $W . ' ' . (int)ceil($r['h']) . '" width="' . $W . '" height="' . (int)ceil($r['h']) . '" role="img" aria-label="' . h(igs_pulito($r['aria'], 2000)) . '" focusable="false">' . $r['svg'] . '</svg>';
    $fig = '<figure class="igs igs-' . substr($tipo, 3) . '">' . $svg($largo, IGS_LARGO, 'igs-largo') . $svg($stretto, IGS_STRETTO, 'igs-stretto') . '</figure>';
    return igx_cornice($tipo, $v, $note . $fig, igs_classi_cornice($v));
}

/**
 * Le classi in più della cornice delle quindici: titolo e occhiello della testata bianchi o neri secondo il colore della
 * testata (il contrasto più alto, anche nei temi), a meno che chi scrive non abbia scelto il colore del testo o
 * dell'occhiello; la firma con il colore del testo, che si legge sul corpo.
 */
function igs_classi_cornice(array $v): string {
    // Testata di un colore solo (scelta dalla palette, senza secondo colore): piena come nel disegno approvato, e il testo
    // bianco o nero vale per tutta la testata. Le testate a due colori restano sfumate.
    $piatta = in_array($v['testata'] ?? '', ['scuro', 'verde', 'ciano', 'rosso', 'mattone', 'viola', 'oro', 'oro_scuro'], true) && igx_esa($v['testata_colore2'] ?? '') === '';
    return 'igx-disegnata' . ($piatta ? ' igs-testa-piatta' : '') . (igx_esa($v['testata_testo'] ?? '') === '' ? ' igs-testa-auto' : '') . (in_array($v['occhiello_colore'] ?? '', ['auto', 'bianco'], true) ? ' igs-occhiello-auto' : '');
}

/** Un poligono da una lista di punti [x, y]. */
function igs_punti(array $p): string {
    return implode(' ', array_map(fn($q) => igs_n($q[0]) . ',' . igs_n($q[1]), $p));
}

// ------------------------------------------------------------
// 1. Imbuto
// ------------------------------------------------------------
function igs_imbuto(array $v, int $W): array {
    $voci = igs_voci($v['gradini'] ?? [], ['nome', 'valore'], 6);
    $note = [igs_nota_troppe($v['gradini'] ?? [], 6, 'gradini')];
    if (!$voci) return ['vuoto' => 'Aggiungi i gradini dell\'imbuto: nome e quanti.', 'note' => $note];
    if (count($voci) < 3) $note[] = 'L\'imbuto si legge meglio con almeno 3 gradini.';
    $L = $W >= 500;
    $resa = !empty($v['mostra_resa']) && count($voci) > 1;
    $colR = $resa ? ($L ? 74 : 50) : 0;
    $cx = ($W - $colR) / 2;
    $hw0 = ($W - $colR) / 2 - 4; $hwb = $hw0 * .27;
    $n = count($voci); $h = $L ? 56 : 52; $gap = 5; $y = $resa ? 24 : 4;
    $fsN = $L ? 13.5 : 12.5; $fsV = $L ? 19 : 16.5;
    $o = ''; $aria = [];
    if ($resa) $o .= igs_testo($W - 2, 14, [mb_strtoupper(igs_pulito($v['etichetta_resa'] ?? '', 30))], 'igs-tm', 11, 'end', 700, 1.25, ' letter-spacing=".08em"');
    $prec = null;
    foreach ($voci as $k => $x) {
        $a = $hw0 - ($hw0 - $hwb) * $k / $n; $b = $hw0 - ($hw0 - $hwb) * ($k + 1) / $n;
        $val = max(0.0, (float)($x['valore'] ?? 0));
        $nome = igs_pulito($x['nome'] ?? '', 80);
        $wTesto = 2 * $b - 14;
        $o .= '<g' . igs_c($x, $v, $k) . '><polygon class="igs-p" points="' . igs_punti([[$cx - $a, $y], [$cx + $a, $y], [$cx + $b, $y + $h], [$cx - $b, $y + $h]]) . '"/>'
            . igs_testo($cx, $y + $h * .42, [igs_tronca($nome, $fsN, $wTesto)], 'igs-su', $fsN, 'middle', 500)
            . igs_testo($cx, $y + $h * .42 + $fsV + 1, [igs_tronca(igs_num($val), $fsV, $wTesto, true)], 'igs-su igs-num', $fsV, 'middle', 700) . '</g>';
        $r = '';
        if ($resa && $prec !== null) {
            $r = $prec > 0 ? igs_num($val / $prec * 100, 0) . '%' : '';
            if ($r !== '') $o .= igs_testo($W - 2, $y + $h / 2 + 4.5, [$r], 'igs-tm igs-num', $L ? 13.5 : 12.5, 'end', 700);
        }
        $aria[] = $nome . ' ' . igs_num($val) . ($r !== '' ? ' (resa ' . $r . ')' : '');
        $prec = $val; $y += $h + $gap;
    }
    return ['svg' => $o, 'h' => $y - $gap + 4, 'aria' => 'Imbuto: ' . implode('; ', $aria) . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 2. Piramide a livelli
// ------------------------------------------------------------
function igs_piramide(array $v, int $W): array {
    $voci = igs_voci($v['livelli'] ?? [], ['nome', 'testo'], 6);
    $note = [igs_nota_troppe($v['livelli'] ?? [], 6, 'livelli')];
    if (!$voci) return ['vuoto' => 'Aggiungi i livelli della piramide.', 'note' => $note];
    $L = $W >= 500;
    $base = $L ? 300 : 150; $ax = $base / 2; $tx = $L ? 334 : 166; $tw = $W - $tx - 2;
    $n = count($voci); $lh = $L ? 56 : 58; $top = 6; $Hp = $n * $lh;
    $base = min($base, $Hp * 1.07); $ax = ($L ? 300 : 150) / 2;
    $hw = fn($y) => ($base / 2) * ($y - $top) / $Hp;
    $o = ''; $aria = [];
    foreach ($voci as $k => $x) {
        $a = $top + $k * $lh; $b = $a + $lh; $m = ($a + $b) / 2;
        $o .= '<g' . igs_c($x, $v, $k) . '><polygon class="igs-p" points="' . igs_punti([[$ax - $hw($a + 1), $a + 1], [$ax + $hw($a + 1), $a + 1], [$ax + $hw($b - 1), $b - 1], [$ax - $hw($b - 1), $b - 1]]) . '"/></g>';
        $nome = igs_tronca(igs_pulito($x['nome'] ?? '', 80), 14.5, $tw, true);
        $righe = igs_a_capo($x['testo'] ?? '', 12.5, $tw, 2);
        $tot = 14.5 * 1.2 + count($righe) * 12.5 * 1.25;
        $y0 = $m - $tot / 2 + 14.5 * .85;
        $bordo = $ax + $hw($m) + 6;
        if ($bordo < $tx - 10) $o .= '<line class="igs-filo" x1="' . igs_n($bordo) . '" y1="' . igs_n($m) . '" x2="' . igs_n($tx - 6) . '" y2="' . igs_n($m) . '"/>';
        $o .= igs_testo($tx, $y0, [$nome], 'igs-t', 14.5, 'start', 700) . igs_testo($tx, $y0 + 14.5 * .35 + 12.5 * 1.15, $righe, 'igs-tm', 12.5, 'start');
        $aria[] = igs_pulito($x['nome'] ?? '') . (igs_pulito($x['testo'] ?? '') !== '' ? ': ' . igs_pulito($x['testo']) : '');
    }
    return ['svg' => $o, 'h' => $top + $Hp + 4, 'aria' => 'Piramide, dalla punta alla base: ' . implode('; ', $aria) . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 3. Matrice a quattro quadranti
// ------------------------------------------------------------
function igs_matrice(array $v, int $W): array {
    $q = array_slice(array_values(array_filter((array)($v['quadranti'] ?? []), 'is_array')), 0, 4);
    $note = [igs_nota_troppe($v['quadranti'] ?? [], 4, 'quadranti')];
    if (!$q) return ['vuoto' => 'Aggiungi i quattro quadranti.', 'note' => $note];
    while (count($q) < 4) $q[] = ['titolo' => '', 'testo' => '', 'colore' => 'grigio'];
    $L = $W >= 500;
    $ax = 28; $ay = 26; $gap = 10;
    $qw = ($W - $ax - $gap) / 2;
    $fsT = $L ? 16 : 14.5; $fsE = $L ? 13 : 12.5; $tw = $qw - 30;
    $dati = [];
    foreach ($q as $k => $x) {
        $tit = igs_a_capo($x['titolo'] ?? '', $fsT, $tw, 2, true);
        $es = [];
        foreach (array_slice(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string)($x['testo'] ?? '')))), fn($r) => $r !== ''), 0, 6) as $r) $es = array_merge($es, igs_a_capo($r, $fsE, $tw, 2));
        $es = array_slice($es, 0, $L ? 5 : 6);
        $dati[$k] = [$tit, $es, 22 + count($tit) * $fsT * 1.2 + ($es ? 6 + count($es) * $fsE * 1.3 : 0) + 14];
    }
    $assi = [igs_pulito($v['asse_y_a'] ?? '', 60), igs_pulito($v['asse_y_b'] ?? '', 60)];
    // La scritta ruotata dell'asse sta tutta nella riga: con la spaziatura delle lettere (0,1 em) fino a 200 di altezza.
    $alt = fn(string $a) => min(200, igs_largh(mb_strtoupper($a), 11, true) + mb_strlen($a) * 1.1 + 18);
    $rh = [max($L ? 112 : 104, $dati[0][2], $dati[1][2], $alt($assi[0])), max($L ? 112 : 104, $dati[2][2], $dati[3][2], $alt($assi[1]))];
    $sp = ' letter-spacing=".1em"';
    $o = igs_testo($ax + $qw / 2, 15, [igs_tronca(mb_strtoupper(igs_pulito($v['asse_x_a'] ?? '', 60)), 11, $qw - 8, true)], 'igs-tm', 11, 'middle', 700, 1.25, $sp)
        . igs_testo($ax + $gap + $qw * 1.5, 15, [igs_tronca(mb_strtoupper(igs_pulito($v['asse_x_b'] ?? '', 60)), 11, $qw - 8, true)], 'igs-tm', 11, 'middle', 700, 1.25, $sp);
    $aria = [];
    foreach ([0, 1] as $r) {
        $y = $ay + ($r ? $rh[0] + $gap : 0);
        $o .= '<text class="igs-tm" font-size="11" font-weight="700" text-anchor="middle"' . $sp . ' transform="translate(13 ' . igs_n($y + $rh[$r] / 2) . ') rotate(-90)" y="4">' . h(igs_tronca(mb_strtoupper($assi[$r]), 11, $rh[$r] - 12 - mb_strlen($assi[$r]) * 1.1, true)) . '</text>';
        foreach ([0, 1] as $c) {
            $k = $r * 2 + $c; $x = $ax + $c * ($qw + $gap);
            [$tit, $es] = $dati[$k];
            $o .= '<g' . igs_c($q[$k], $v, $k, 'grigio') . '><rect class="igs-tinta igs-bordo" x="' . igs_n($x) . '" y="' . igs_n($y) . '" width="' . igs_n($qw) . '" height="' . igs_n($rh[$r]) . '" rx="7"/>'
                . '<rect class="igs-p" x="' . igs_n($x) . '" y="' . igs_n($y) . '" width="6" height="' . igs_n($rh[$r]) . '" rx="3"/></g>';
            $yy = $y + 20 + $fsT * .8;
            $o .= igs_testo($x + 18, $yy, $tit, 'igs-t', $fsT, 'start', 700, 1.2);
            $o .= igs_testo($x + 18, $yy + count($tit) * $fsT * 1.2 + 6, $es, 'igs-tm', $fsE, 'start', 400, 1.3);
            $aria[] = ($r ? $assi[1] : $assi[0]) . ' e ' . igs_pulito($c ? ($v['asse_x_b'] ?? '') : ($v['asse_x_a'] ?? '')) . ': ' . igs_pulito($q[$k]['titolo'] ?? '') . (igs_pulito($q[$k]['testo'] ?? '') !== '' ? ' (' . igs_pulito($q[$k]['testo']) . ')' : '');
        }
    }
    return ['svg' => $o, 'h' => $ay + $rh[0] + $gap + $rh[1] + 2, 'aria' => 'Matrice a quattro quadranti. ' . implode('; ', $aria) . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 4. Ciclo
// ------------------------------------------------------------
/** La punta di una freccia: tip in $p, direzione ($dx, $dy) normalizzata. */
function igs_punta(float $px, float $py, float $dx, float $dy, float $lung = 10, float $larg = 5.5): string {
    $bx = $px - $dx * $lung; $by = $py - $dy * $lung;
    return '<polygon class="igs-pieno" points="' . igs_punti([[$px, $py], [$bx - $dy * $larg, $by + $dx * $larg], [$bx + $dy * $larg, $by - $dx * $larg]]) . '"/>';
}

function igs_ciclo(array $v, int $W): array {
    $voci = igs_voci($v['tappe'] ?? [], ['nome'], 6);
    $note = [igs_nota_troppe($v['tappe'] ?? [], 6, 'tappe')];
    if (count($voci) < 2) return ['vuoto' => 'Aggiungi almeno due tappe al ciclo (meglio da 3 a 6).', 'note' => $note];
    $L = $W >= 500;
    $R = $L ? 114 : 102; $r = $L ? 42 : 38;
    $cx = $W / 2; $cy = $R + $r + 6; $n = count($voci);
    $pos = fn($ang) => [$cx + $R * cos($ang), $cy + $R * sin($ang)];
    $d = 2 * asin(min(.99, ($r + 9) / (2 * $R)));
    $o = ''; $aria = [];
    for ($i = 0; $i < $n; $i++) {
        $a1 = -M_PI / 2 + 2 * M_PI * $i / $n + $d; $a2 = -M_PI / 2 + 2 * M_PI * ($i + 1) / $n - $d;
        if ($a2 - $a1 < .05) continue;
        [$x1, $y1] = $pos($a1); [$x2, $y2] = $pos($a2 - 6 / $R);
        $o .= '<path class="igs-linea" fill="none" stroke-width="2.5" d="M' . igs_n($x1) . ' ' . igs_n($y1) . ' A' . $R . ' ' . $R . ' 0 ' . ($a2 - $a1 > M_PI ? 1 : 0) . ' 1 ' . igs_n($x2) . ' ' . igs_n($y2) . '"/>';
        [$px, $py] = $pos($a2);
        $o .= igs_punta($px, $py, -sin($a2), cos($a2));
    }
    foreach ($voci as $i => $x) {
        [$nx, $ny] = $pos(-M_PI / 2 + 2 * M_PI * $i / $n);
        $righe = igs_a_capo($x['nome'] ?? '', 12.5, 2 * $r - 12, 2, true);
        $o .= '<g' . igs_c($x, $v, $i) . '><circle class="igs-p" cx="' . igs_n($nx) . '" cy="' . igs_n($ny) . '" r="' . $r . '"/>' . igs_testo($nx, igs_y_centro($ny, count($righe), 12.5, 1.15), $righe, 'igs-su', 12.5, 'middle', 700, 1.15) . '</g>';
        $aria[] = igs_pulito($x['nome'] ?? '');
    }
    $libero = 2 * ($R - $r) - 22;
    $s1 = igs_a_capo($v['centro_sopra'] ?? '', 12.5, $libero, 2);
    $s2 = igs_a_capo($v['centro'] ?? '', 16, $libero, 2, true);
    $tot = count($s1) * 12.5 * 1.25 + count($s2) * 16 * 1.2;
    $y0 = $cy - $tot / 2 + 12.5 * .85;
    $o .= igs_testo($cx, $y0, $s1, 'igs-tm', 12.5) . igs_testo($cx, $y0 + count($s1) * 12.5 * 1.25 + ($s1 ? 3 : 0) + ($s1 ? 0 : 3), $s2, 'igs-t', 16, 'middle', 700, 1.2);
    $centro = trim(igs_pulito($v['centro_sopra'] ?? '') . ' ' . igs_pulito($v['centro'] ?? ''));
    return ['svg' => $o, 'h' => 2 * ($R + $r) + 12, 'aria' => 'Ciclo' . ($centro !== '' ? ' (' . $centro . ')' : '') . ': ' . implode(', poi ', $aria) . ', e si ricomincia.', 'note' => $note];
}

// ------------------------------------------------------------
// 5. Ciambella
// ------------------------------------------------------------
function igs_ciambella(array $v, int $W): array {
    $voci = igs_voci($v['voci'] ?? [], ['nome'], 6);
    $note = [igs_nota_troppe($v['voci'] ?? [], 6, 'voci')];
    if (!$voci) return ['vuoto' => 'Aggiungi le voci della ciambella: nome e percentuale.', 'note' => $note];
    $val = array_map(fn($x) => max(0.0, min(100.0, (float)($x['percentuale'] ?? 0))), $voci);
    $tot = array_sum($val);
    if (abs($tot - 100) > .05) $note[] = 'Le percentuali fanno ' . igs_num($tot) . '%: per riempire il cerchio devono fare 100.';
    $base = max(100.0, $tot);
    $L = $W >= 500;
    $n = count($voci);
    if ($L) { $cx = 140; $cy = 136; $r = 96; $sw = 40; $lx = 296; $rowH = 44; }
    else { $cx = $W / 2; $cy = 112; $r = 80; $sw = 34; $lx = 6; $rowH = 40; }
    $C = 2 * M_PI * $r;
    $o = '<circle class="igs-anello" cx="' . igs_n($cx) . '" cy="' . igs_n($cy) . '" r="' . $r . '" fill="none" stroke-width="' . $sw . '"/>';
    $off = 0.0; $aria = [];
    foreach ($voci as $i => $x) {
        $Ls = $C * $val[$i] / $base;
        if ($Ls > .2) $o .= '<g' . igs_c($x, $v, $i) . '><circle class="igs-tratto" cx="' . igs_n($cx) . '" cy="' . igs_n($cy) . '" r="' . $r . '" fill="none" stroke-width="' . $sw . '" stroke-dasharray="' . igs_n(max(.5, $Ls - ($n > 1 ? 2.5 : 0))) . ' ' . igs_n($C) . '" stroke-dashoffset="' . igs_n(-$off) . '" transform="rotate(-90 ' . igs_n($cx) . ' ' . igs_n($cy) . ')"/></g>';
        $off += $Ls;
        $aria[] = igs_pulito($x['nome'] ?? '') . ' ' . igs_num($val[$i]) . '%';
    }
    if (($v['centro'] ?? 'piu_grande') === 'piu_grande') {
        $k = array_keys($val, max($val))[0];
        $sotto = igs_pulito($v['centro_testo'] ?? '') !== '' ? $v['centro_testo'] : ($voci[$k]['nome'] ?? '');
        $dentro = 2 * ($r - $sw / 2) - 14;
        $fs = $L ? 32 : 28;
        $o .= igs_testo($cx, $cy + 4, [igs_tronca(igs_num($val[$k]) . '%', $fs, $dentro, true)], 'igs-t igs-num', $fs, 'middle', 700)
            . igs_testo($cx, $cy + 24, [igs_tronca(igs_pulito($sotto, 80), 12.5, $dentro)], 'igs-tm', 12.5);
    }
    $ly = $L ? $cy - $n * $rowH / 2 + $rowH / 2 : $cy + $r + $sw / 2 + 30;
    $lw = $W - $lx - 2;
    foreach ($voci as $i => $x) {
        $y = $ly + $i * $rowH;
        $o .= '<g' . igs_c($x, $v, $i) . '><rect class="igs-p" x="' . igs_n($lx) . '" y="' . igs_n($y - 12) . '" width="15" height="15" rx="3.5"/></g>'
            . igs_testo($lx + 25, $y, [igs_tronca(igs_pulito($x['nome'] ?? '', 80), 14, $lw - 25 - 64, true)], 'igs-t', 14, 'start', 600)
            . igs_testo($W - 2, $y, [igs_num($val[$i]) . '%'], 'igs-t igs-num', 14.5, 'end', 700)
            . ($i < $n - 1 ? '<line class="igs-filo" x1="' . igs_n($lx) . '" y1="' . igs_n($y + $rowH / 2 - 4) . '" x2="' . igs_n($W - 2) . '" y2="' . igs_n($y + $rowH / 2 - 4) . '"/>' : '');
    }
    $h = $L ? max($cy + $r + $sw / 2 + 6, $ly + ($n - 1) * $rowH + 16) : $ly + ($n - 1) * $rowH + 12;
    return ['svg' => $o, 'h' => $h, 'aria' => 'Ciambella: ' . implode('; ', $aria) . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 6. Cerchi che si toccano
// ------------------------------------------------------------
function igs_venn(array $v, int $W): array {
    $voci = igs_voci($v['cerchi'] ?? [], ['nome', 'sopra'], 3);
    $note = [igs_nota_troppe($v['cerchi'] ?? [], 3, 'cerchi')];
    if (count($voci) < 2) return ['vuoto' => 'Aggiungi due o tre cerchi.', 'note' => $note];
    $L = $W >= 500; $tre = count($voci) === 3;
    if ($tre) {
        $r = $L ? 100 : 88; $cx = $W / 2; $cy = 1.33 * $r + 6;
        $cc = [[$cx - .59 * $r, $cy - .33 * $r], [$cx + .59 * $r, $cy - .33 * $r], [$cx, $cy + .6 * $r]];
        $lab = [[$cc[0][0] - .4 * $r, $cc[0][1] - .2 * $r], [$cc[1][0] + .4 * $r, $cc[1][1] - .2 * $r], [$cc[2][0], $cc[2][1] + .6 * $r]];
        $mid = [$cx, $cy + .03 * $r]; $h = $cy + 1.6 * $r + 6; $lw = .92 * $r;
    } else {
        $r = $L ? 118 : 92; $cx = $W / 2; $cy = $r + 6;
        $cc = [[$cx - .56 * $r, $cy], [$cx + .56 * $r, $cy]];
        $lab = [[$cc[0][0] - .42 * $r, $cy], [$cc[1][0] + .42 * $r, $cy]];
        $mid = [$cx, $cy]; $h = 2 * $r + 12; $lw = .82 * $r;
    }
    $o = ''; $t = ''; $aria = [];
    foreach ($voci as $i => $x) {
        $o .= '<g' . igs_c($x, $v, $i) . '><circle class="igs-tinta igs-bordo-2" cx="' . igs_n($cc[$i][0]) . '" cy="' . igs_n($cc[$i][1]) . '" r="' . $r . '"/></g>';
        $s1 = igs_a_capo($x['sopra'] ?? '', 12.5, $lw, 2); $s2 = igs_a_capo($x['nome'] ?? '', 14.5, $lw, 2, true);
        $tot = count($s1) * 12.5 * 1.2 + count($s2) * 14.5 * 1.2;
        $y0 = $lab[$i][1] - $tot / 2 + 12.5 * .85;
        $t .= igs_testo($lab[$i][0], $y0, $s1, 'igs-tm', 12.5, 'middle', 400, 1.2) . igs_testo($lab[$i][0], $y0 + count($s1) * 12.5 * 1.2 + ($s1 ? 2 : 1), $s2, 'igs-t', 14.5, 'middle', 700, 1.2);
        $aria[] = trim(igs_pulito($x['sopra'] ?? '') . ' ' . igs_pulito($x['nome'] ?? ''));
    }
    $c1 = igs_pulito($v['centro_sopra'] ?? ''); $c2 = igs_pulito($v['centro'] ?? '');
    if ($c1 !== '' || $c2 !== '') {
        $rr = $L ? 32 : 30; $dw = 2 * $rr - 8;
        $a = $c1 !== '' ? [igs_tronca($c1, 10.5, $dw)] : []; $b = $c2 !== '' ? [igs_tronca($c2, 12, $dw, true)] : [];
        $y0 = $mid[1] - (count($a) + count($b) - 1) * 6.5 + 4;
        $t .= '<circle class="igs-pieno" cx="' . igs_n($mid[0]) . '" cy="' . igs_n($mid[1]) . '" r="' . $rr . '"/>' . igs_testo($mid[0], $y0, $a, 'igs-inv', 10.5) . igs_testo($mid[0], $y0 + ($a ? 13 : 0), $b, 'igs-inv', 12, 'middle', 700);
    }
    return ['svg' => $o . $t, 'h' => $h, 'aria' => 'Cerchi che si toccano: ' . implode('; ', $aria) . ($c1 . $c2 !== '' ? '. Dove si incontrano: ' . trim($c1 . ' ' . $c2) : '') . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 7. Ragnatela
// ------------------------------------------------------------
function igs_radar(array $v, int $W): array {
    $voci = igs_voci($v['aspetti'] ?? [], ['nome'], 8);
    $note = [igs_nota_troppe($v['aspetti'] ?? [], 8, 'aspetti')];
    if (count($voci) < 3) return ['vuoto' => 'Aggiungi almeno 3 aspetti alla ragnatela (meglio da 4 a 8).', 'note' => $note];
    $L = $W >= 500; $n = count($voci);
    $R = $L ? 118 : 84; $cx = $W / 2; $cy = $R + ($L ? 46 : 48);
    $pt = fn($k, $f) => [$cx + $R * $f * cos(-M_PI / 2 + 2 * M_PI * $k / $n), $cy + $R * $f * sin(-M_PI / 2 + 2 * M_PI * $k / $n)];
    $o = '';
    foreach ([1, 2 / 3, 1 / 3] as $g) $o .= '<polygon class="igs-filo" fill="none" points="' . igs_punti(array_map(fn($k) => $pt($k, $g), range(0, $n - 1))) . '"/>';
    for ($k = 0; $k < $n; $k++) { [$x, $y] = $pt($k, 1); $o .= '<line class="igs-filo" x1="' . igs_n($cx) . '" y1="' . igs_n($cy) . '" x2="' . igs_n($x) . '" y2="' . igs_n($y) . '"/>'; }
    $vp = array_map(fn($x) => max(0.0, min(10.0, (float)($x['prima'] ?? 0))), $voci);
    $vd = array_map(fn($x) => max(0.0, min(10.0, (float)($x['dopo'] ?? 0))), $voci);
    $prima = !empty($v['mostra_prima']);
    if ($prima) $o .= '<polygon class="igs-prima" points="' . igs_punti(array_map(fn($k) => $pt($k, $vp[$k] / 10), range(0, $n - 1))) . '"/>';
    $col = igs_cf((string)($v['colore_dopo'] ?? 'verde'), $v['colore_dopo_libero'] ?? '');
    $o .= '<g' . $col . '><polygon class="igs-dopo" points="' . igs_punti(array_map(fn($k) => $pt($k, $vd[$k] / 10), range(0, $n - 1))) . '"/>';
    for ($k = 0; $k < $n; $k++) { [$x, $y] = $pt($k, $vd[$k] / 10); $o .= '<circle class="igs-p2" cx="' . igs_n($x) . '" cy="' . igs_n($y) . '" r="3.8"/>'; }
    $o .= '</g>';
    $fs = $L ? 13 : 12; $aria = []; $fondo = $cy + $R;
    for ($k = 0; $k < $n; $k++) {
        $a = -M_PI / 2 + 2 * M_PI * $k / $n; $co = cos($a); $si = sin($a);
        [$x, $y] = [$cx + ($R + ($L ? 14 : 9)) * $co, $cy + ($R + ($L ? 14 : 9)) * $si];
        $anc = abs($co) < .2 ? 'middle' : ($co > 0 ? 'start' : 'end');
        $max = $anc === 'middle' ? ($L ? 180 : 150) : ($co > 0 ? $W - $x - 2 : $x - 2);
        $righe = igs_a_capo($voci[$k]['nome'] ?? '', $fs, $max, 2, true);
        $nr = count($righe);
        $y0 = $si < -.8 ? $y - ($nr - 1) * $fs * 1.15 - 3 : ($si > .8 ? $y + $fs + 2 : igs_y_centro($y, $nr, $fs, 1.15));
        $o .= igs_testo($x, $y0, $righe, 'igs-t', $fs, $anc, 600, 1.15);
        if (!$L || $co < -.2) $fondo = max($fondo, $y0 + ($nr - 1) * $fs * 1.15 + 4);
        $aria[] = igs_pulito($voci[$k]['nome'] ?? '') . ($prima ? ': prima ' . igs_num($vp[$k]) . ', ' : ': ') . igs_pulito($v['etichetta_dopo'] ?? '') . ' ' . igs_num($vd[$k]);
    }
    $ly = max($cy + $R + ($L ? 44 : 30), $fondo + ($L ? 22 : 26));
    $leg = function (float $x, float $y, string $cl, string $testo, string $attr = '') { return '<g' . $attr . '><rect class="' . $cl . '" x="' . igs_n($x) . '" y="' . igs_n($y - 6) . '" width="16" height="4" rx="1"/></g>' . igs_testo($x + 22, $y, [$testo], 'igs-tm', 12.5, 'start'); };
    $tp = igs_tronca(igs_pulito($v['etichetta_prima'] ?? '', 60), 12.5, $W / 2 - 30); $td = igs_tronca(igs_pulito($v['etichetta_dopo'] ?? '', 60), 12.5, $W / 2 - 30);
    if ($L) { $o .= ($prima ? $leg(4, $ly - 22, 'igs-prima-l', $tp) : '') . $leg(4, $ly, 'igs-p2', $td, $col); $h = $ly + 8; }
    else { $o .= ($prima ? $leg(6, $ly, 'igs-prima-l', $tp) : '') . $leg($prima ? $W / 2 + 4 : 6, $ly, 'igs-p2', $td, $col); $h = $ly + 8; }
    return ['svg' => $o, 'h' => $h, 'aria' => 'Ragnatela, valori da 0 a 10. ' . implode('; ', $aria) . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 8. Lancetta
// ------------------------------------------------------------
function igs_lancetta(array $v, int $W): array {
    $min = (float)($v['minimo'] ?? 0); $max = (float)($v['massimo'] ?? 10);
    $note = [];
    if ($max <= $min) { $note[] = 'Il massimo deve essere più grande del minimo: la scala va da ' . igs_num($min) . ' a ' . igs_num($min + 10) . '.'; $max = $min + 10; }
    $s = [max($min, min($max, (float)($v['soglia_1'] ?? $min))), max($min, min($max, (float)($v['soglia_2'] ?? $max)))];
    sort($s);
    $val = (float)($v['valore'] ?? 0);
    $dec = (int)($v['decimali'] ?? 1); $dec = max(0, min(2, $dec));
    if ($val < $min || $val > $max) $note[] = 'Il valore è fuori dalla scala: la lancetta si ferma al bordo.';
    $L = $W >= 500;
    if ($L) { $cx = $W / 2; $r = 148; $sw = 30; $cy = $r + $sw / 2 + 6; $fsV = 46; }
    else { $cx = $W / 2; $r = 120; $sw = 26; $cy = $r + $sw / 2 + 6; $fsV = 38; }
    $ang = fn($x) => M_PI - M_PI * (max($min, min($max, $x)) - $min) / ($max - $min);
    $p = fn($a, $rr) => [$cx + $rr * cos($a), $cy - $rr * sin($a)];
    $zone = ($v['verso'] ?? 'alto') === 'basso' ? ['verde', 'oro', 'rosso'] : ['rosso', 'oro', 'verde'];
    $lim = [$min, $s[0], $s[1], $max];
    $o = ''; $nomiZone = ['rosso' => 'rossa', 'oro' => 'gialla', 'verde' => 'verde']; $zonaVal = '';
    for ($z = 0; $z < 3; $z++) {
        if ($lim[$z + 1] - $lim[$z] <= 0) continue;
        if ($val >= $lim[$z] && $val <= $lim[$z + 1] && $zonaVal === '') $zonaVal = $nomiZone[$zone[$z]];
        $a1 = $ang($lim[$z]); $a2 = $ang($lim[$z + 1]);
        $pezzi = $a1 - $a2 > M_PI * .99 ? [[$a1, ($a1 + $a2) / 2], [($a1 + $a2) / 2, $a2]] : [[$a1, $a2]];
        $o .= '<g' . igs_cf($zone[$z]) . '>';
        foreach ($pezzi as [$b1, $b2]) { [$x1, $y1] = $p($b1, $r); [$x2, $y2] = $p($b2, $r); $o .= '<path class="igs-tratto" fill="none" stroke-width="' . $sw . '" d="M' . igs_n($x1) . ' ' . igs_n($y1) . ' A' . $r . ' ' . $r . ' 0 0 1 ' . igs_n($x2) . ' ' . igs_n($y2) . '"/>'; }
        $o .= '</g>';
    }
    for ($t = 0; $t <= 10; $t++) { $a = M_PI - M_PI * $t / 10; [$x1, $y1] = $p($a, $r - $sw / 2 - 6); [$x2, $y2] = $p($a, $r - $sw / 2 - 13); $o .= '<line class="igs-linea" stroke-width="1.5" x1="' . igs_n($x1) . '" y1="' . igs_n($y1) . '" x2="' . igs_n($x2) . '" y2="' . igs_n($y2) . '"/>'; }
    [$nx, $ny] = $p($ang($val), $r - $sw / 2 - 20);
    $o .= '<line class="igs-linea" stroke-width="5" stroke-linecap="round" x1="' . igs_n($cx) . '" y1="' . igs_n($cy) . '" x2="' . igs_n($nx) . '" y2="' . igs_n($ny) . '"/><circle class="igs-pieno" cx="' . igs_n($cx) . '" cy="' . igs_n($cy) . '" r="9"/>';
    $suf = igs_pulito($v['suffisso'] ?? '', 12);
    $testoVal = igs_num($val, $dec) . ($suf !== '' ? (preg_match('/^[%\/]/', $suf) ? '' : ' ') . $suf : '');
    $o .= igs_testo($cx, $cy - $r * .36, [igs_tronca($testoVal, $fsV, 2 * ($r - $sw) - 30, true)], 'igs-t igs-num igs-alone', $fsV, 'middle', 700);
    $o .= igs_testo($cx - $r, $cy + 24, [igs_num($min, $dec > 0 && abs($min - round($min)) > .001 ? $dec : 0)], 'igs-tm igs-num', 13)
        . igs_testo($cx + $r, $cy + 24, [igs_num($max, $dec > 0 && abs($max - round($max)) > .001 ? $dec : 0)], 'igs-tm igs-num', 13);
    $righe = igs_a_capo($v['testo'] ?? '', 13, $W - 2 * ($L ? 90 : 34), 2);
    $o .= igs_testo($cx, $cy + 50, $righe, 'igs-tm', 13);
    $h = $cy + ($righe ? 54 + (count($righe) - 1) * 16.3 : 30) + 6;
    $aria = 'Lancetta: ' . $testoVal . ' su una scala da ' . igs_num($min) . ' a ' . igs_num($max) . ($zonaVal !== '' ? ', nella zona ' . $zonaVal : '') . '. ' . igs_pulito($v['testo'] ?? '');
    return ['svg' => $o, 'h' => $h, 'aria' => $aria, 'note' => $note];
}

// ------------------------------------------------------------
// 9. Iceberg
// ------------------------------------------------------------
function igs_iceberg(array $v, int $W): array {
    $voci = igs_voci($v['voci'] ?? [], ['testo'], 6);
    $note = [igs_nota_troppe($v['voci'] ?? [], 6, 'voci')];
    $L = $W >= 500; $n = count($voci);
    $k = $L ? 1.5 : 1.25; $wl = 60 * $k + 18;
    $bx = $L ? 250 : 128; $bw = $L ? 340 : 236;
    $lh = $L ? 27 : 25; $bh = max($L ? 168 : 150, $n * $lh + 70);
    $col = igs_cf((string)($v['colore'] ?? 'ciano_scuro'), $v['colore_libero'] ?? '');
    $o = '<g class="igx-c-ciano"><rect class="igs-acqua" x="0" y="' . igs_n($wl) . '" width="' . $W . '" height="' . igs_n($bh + 18) . '"/><line class="igs-tratto" stroke-width="2" x1="0" y1="' . igs_n($wl) . '" x2="' . $W . '" y2="' . igs_n($wl) . '"/></g>';
    $punta = [[-38, 0], [-18, -40], [-2, -30], [14, -58], [38, 0]];
    $sotto = [[-.36, 0], [.33, 0], [.5, .37], [.41, .77], [.07, 1], [-.31, .9], [-.5, .44]];
    $o .= '<g' . $col . '><polygon class="igs-ghiaccio" points="' . igs_punti(array_map(fn($q) => [$bx + $q[0] * $k, $wl + $q[1] * $k], $punta)) . '"/>'
        . '<polygon class="igs-p" points="' . igs_punti(array_map(fn($q) => [$bx + $q[0] * $bw, $wl + 2 + $q[1] * $bh], $sotto)) . '"/>';
    $y0 = $wl + 2 + $bh * .46 - ($n - 1) * $lh / 2 + 5;
    $fsV = $L ? 14.5 : 13;
    foreach ($voci as $i => $x) $o .= igs_testo($bx, $y0 + $i * $lh, [igs_tronca(igs_pulito($x['testo'] ?? '', 80), $fsV, $bw * ($L ? .6 : .7), $i === 0)], 'igs-su', $fsV, 'middle', $i === 0 ? 700 : 500);
    $o .= '</g>';
    $lx = $bx + 38 * $k + 34; $tw = $W - $lx - 2;
    $t1 = igs_a_capo($v['sopra_titolo'] ?? '', 14.5, $tw, 2, true); $t2 = igs_a_capo($v['sopra_testo'] ?? '', 12.5, $tw, 2);
    $ly = $wl - 58 * $k * .62;
    if ($t1 || $t2) $o .= '<line class="igs-filo" x1="' . igs_n($bx + 24 * $k) . '" y1="' . igs_n($ly) . '" x2="' . igs_n($lx - 6) . '" y2="' . igs_n($ly) . '"/>'
        . igs_testo($lx, $ly - 3 - (count($t1) - 1) * 17, $t1, 'igs-t', 14.5, 'start', 700, 1.15) . igs_testo($lx, $ly + 13, $t2, 'igs-tm', 12.5, 'start', 400, 1.2);
    $sp = ' letter-spacing=".1em"';
    $o .= igs_testo(10, $wl - 9, [igs_tronca(mb_strtoupper(igs_pulito($v['etichetta_sopra'] ?? '', 30)), 11, $bx - 38 * $k - 20, true)], 'igs-tm', 11, 'start', 700, 1.25, $sp)
        . ($L ? igs_testo(10, $wl + 20, [igs_tronca(mb_strtoupper(igs_pulito($v['etichetta_sotto'] ?? '', 30)), 11, $bx - $bw * .5 - 16, true)], 'igs-tm', 11, 'start', 700, 1.25, $sp)
            : igs_testo($W - 6, $wl + 20, [igs_tronca(mb_strtoupper(igs_pulito($v['etichetta_sotto'] ?? '', 30)), 11, $W - $bx - $bw * .36 - 22, true)], 'igs-tm', 11, 'end', 700, 1.25, $sp));
    $aria = 'Iceberg. Sopra l\'acqua: ' . trim(igs_pulito($v['sopra_titolo'] ?? '') . ' ' . igs_pulito($v['sopra_testo'] ?? '')) . '. Sotto: ' . implode('; ', array_map(fn($x) => igs_pulito($x['testo'] ?? ''), $voci)) . '.';
    return ['svg' => $o, 'h' => $wl + $bh + 12, 'aria' => $aria, 'note' => $note];
}

// ------------------------------------------------------------
// 10. Omini
// ------------------------------------------------------------
/** Un simbolo largo $s con l'angolo in alto a sinistra in ($x, $y). Persona: alta 2,07 volte la larghezza. */
function igs_simbolo(string $tipo, float $x, float $y, float $s, string $classe): string {
    $c = $x + $s / 2; $n = 'igs_n';
    switch ($tipo) {
        case 'casa':
            $d = 'M' . $n($c) . ' ' . $n($y) . 'L' . $n($x + $s) . ' ' . $n($y + .46 * $s) . 'L' . $n($x + .88 * $s) . ' ' . $n($y + .46 * $s) . 'L' . $n($x + .88 * $s) . ' ' . $n($y + $s) . 'L' . $n($x + .12 * $s) . ' ' . $n($y + $s) . 'L' . $n($x + .12 * $s) . ' ' . $n($y + .46 * $s) . 'L' . $n($x) . ' ' . $n($y + .46 * $s) . 'Z'
                . 'M' . $n($c - .11 * $s) . ' ' . $n($y + $s) . 'L' . $n($c - .11 * $s) . ' ' . $n($y + .7 * $s) . 'L' . $n($c + .11 * $s) . ' ' . $n($y + .7 * $s) . 'L' . $n($c + .11 * $s) . ' ' . $n($y + $s) . 'Z';
            return '<path class="' . $classe . '" fill-rule="evenodd" d="' . $d . '"/>';
        case 'cuore':
            $d = 'M' . $n($c) . ' ' . $n($y + .28 * $s) . 'C' . $n($c) . ' ' . $n($y + .04 * $s) . ' ' . $n($x) . ' ' . $n($y - .02 * $s) . ' ' . $n($x) . ' ' . $n($y + .32 * $s)
                . 'C' . $n($x) . ' ' . $n($y + .6 * $s) . ' ' . $n($c - .12 * $s) . ' ' . $n($y + .74 * $s) . ' ' . $n($c) . ' ' . $n($y + .95 * $s)
                . 'C' . $n($c + .12 * $s) . ' ' . $n($y + .74 * $s) . ' ' . $n($x + $s) . ' ' . $n($y + .6 * $s) . ' ' . $n($x + $s) . ' ' . $n($y + .32 * $s)
                . 'C' . $n($x + $s) . ' ' . $n($y - .02 * $s) . ' ' . $n($c) . ' ' . $n($y + .04 * $s) . ' ' . $n($c) . ' ' . $n($y + .28 * $s) . 'Z';
            return '<path class="' . $classe . '" d="' . $d . '"/>';
        case 'stella':
            $p = [];
            for ($i = 0; $i < 10; $i++) { $a = -M_PI / 2 + M_PI * $i / 5; $rr = $i % 2 ? .21 * $s : .52 * $s; $p[] = [$c + $rr * cos($a), $y + .54 * $s + $rr * sin($a)]; }
            return '<polygon class="' . $classe . '" points="' . igs_punti($p) . '"/>';
        default:
            return '<circle class="' . $classe . '" cx="' . $n($c) . '" cy="' . $n($y + .33 * $s) . '" r="' . $n(.33 * $s) . '"/><path class="' . $classe . '" d="M' . $n($x) . ' ' . $n($y + 2.07 * $s) . 'Q' . $n($x) . ' ' . $n($y + .87 * $s) . ' ' . $n($c) . ' ' . $n($y + .87 * $s) . 'Q' . $n($x + $s) . ' ' . $n($y + .87 * $s) . ' ' . $n($x + $s) . ' ' . $n($y + 2.07 * $s) . 'Z"/>';
    }
}

function igs_omini(array $v, int $W): array {
    $tot = max(1, min(20, (int)round((float)($v['totale'] ?? 10))));
    $q = max(0, min(20, (int)round((float)($v['quanti'] ?? 0))));
    $note = [];
    if ($q > $tot) { $note[] = 'Quanti (' . $q . ') è più di Su quanti (' . $tot . '): si colorano tutti.'; $q = $tot; }
    $L = $W >= 500;
    $sim = in_array($v['simbolo'] ?? '', ['persona', 'casa', 'cuore', 'stella'], true) ? $v['simbolo'] : 'persona';
    $alto = $sim === 'persona' ? 2.07 : 1.0;
    $col = igs_cf((string)($v['colore'] ?? 'rosso'), $v['colore_libero'] ?? '');
    $frase = igs_pulito($v['frase'] ?? '', 200);
    if ($L) {
        $bw = 170; $area = $W - $bw - 16; $ax = $bw + 16;
        $rows = $tot <= 5 ? 1 : ($tot <= 12 ? 2 : ($tot <= 15 ? 3 : 4));
        $cols = (int)ceil($tot / $rows);
        $cell = min(74, $area / $cols); $s = $cell * ($sim === 'persona' ? .62 : .74); $rowH = $s * $alto + $cell * .32;
        $gh = $rows * $rowH;
        $fr = igs_a_capo($frase, 13, $bw - 8, 3);
        $bh = 116 + 26 + count($fr) * 16.5;
        $H = max($gh, $bh) + 8; $gy = ($H - $gh) / 2 + $cell * .16; $by = ($H - $bh) / 2;
        $o = igs_testo($bw / 2, $by + 82, [(string)$q], 'igs-t igs-num', 84, 'middle', 700)
            . igs_testo($bw / 2, $by + 116, ['su ' . $tot], 'igs-t', 17, 'middle', 600)
            . igs_testo($bw / 2, $by + 142, $fr, 'igs-tm', 13, 'middle', 400, 1.27);
        $ox = $ax + ($area - $cols * $cell) / 2;
    } else {
        $fr = igs_a_capo($frase, 13, $W - 120, 3);
        $o = igs_testo(52, 64, [(string)$q], 'igs-t igs-num', 64, 'middle', 700) . igs_testo(118, 34, ['su ' . $tot], 'igs-t', 16, 'start', 600) . igs_testo(118, 56, $fr, 'igs-tm', 13, 'start', 400, 1.27);
        $top = max(86, 56 + count($fr) * 16.5 + 8);
        $cols = min(5, $tot); $rows = (int)ceil($tot / $cols);
        $cell = min(64, ($W - 8) / $cols); $s = $cell * ($sim === 'persona' ? .6 : .72); $rowH = $s * $alto + $cell * .3;
        $gy = $top + 8; $ox = ($W - $cols * $cell) / 2;
        $H = $gy + $rows * $rowH;
    }
    $acc = ''; $spe = '';
    for ($i = 0; $i < $tot; $i++) {
        $x = $ox + ($i % $cols) * $cell + ($cell - $s) / 2; $y = $gy + intdiv($i, $cols) * $rowH;
        if ($i < $q) $acc .= igs_simbolo($sim, $x, $y, $s, 'igs-p'); else $spe .= igs_simbolo($sim, $x, $y, $s, 'igs-spento');
    }
    $o .= '<g' . $col . '>' . $acc . '</g>' . $spe;
    return ['svg' => $o, 'h' => $H + 4, 'aria' => $q . ' su ' . $tot . ($frase !== '' ? ': ' . $frase : '') . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 11. Il viaggio
// ------------------------------------------------------------
function igs_viaggio(array $v, int $W): array {
    $voci = igs_voci($v['tappe'] ?? [], ['nome'], 7);
    $note = [igs_nota_troppe($v['tappe'] ?? [], 7, 'tappe')];
    if (count($voci) < 2) return ['vuoto' => 'Aggiungi le tappe del viaggio (da 3 a 7).', 'note' => $note];
    $L = $W >= 500; $n = count($voci); $flag = !empty($v['bandiera']);
    $pt = [];
    if ($L) {
        $H = 274; $yb = 206; $yt = 92; $x0 = 34; $x1 = $W - 44;
        $amp = .55 * ($yb - $yt) / ($n - 1);
        for ($i = 0; $i < $n; $i++) {
            $y = $yb - ($yb - $yt) * $i / ($n - 1);
            if ($i > 0 && $i < $n - 1) $y += $i % 2 ? -$amp : $amp;
            $pt[] = [$x0 + ($x1 - $x0) * $i / ($n - 1), max(58, min($yb, $y))];
        }
        $d = 'M' . igs_n($pt[0][0]) . ' ' . igs_n($pt[0][1]);
        for ($i = 0; $i < $n - 1; $i++) { $dx = ($pt[$i + 1][0] - $pt[$i][0]) / 2; $d .= ' C' . igs_n($pt[$i][0] + $dx) . ' ' . igs_n($pt[$i][1]) . ',' . igs_n($pt[$i + 1][0] - $dx) . ' ' . igs_n($pt[$i + 1][1]) . ',' . igs_n($pt[$i + 1][0]) . ' ' . igs_n($pt[$i + 1][1]); }
    } else {
        $step = 62; $H = 56 + ($n - 1) * $step + 34;
        for ($i = 0; $i < $n; $i++) $pt[] = [$i % 2 ? 96 : 40, $H - 30 - $step * $i];
        $d = 'M' . igs_n($pt[0][0]) . ' ' . igs_n($pt[0][1]);
        for ($i = 0; $i < $n - 1; $i++) { $dy = ($pt[$i][1] - $pt[$i + 1][1]) / 2; $d .= ' C' . igs_n($pt[$i][0]) . ' ' . igs_n($pt[$i][1] - $dy) . ',' . igs_n($pt[$i + 1][0]) . ' ' . igs_n($pt[$i + 1][1] + $dy) . ',' . igs_n($pt[$i + 1][0]) . ' ' . igs_n($pt[$i + 1][1]); }
    }
    $o = '<path class="igs-strada" fill="none" stroke-width="15" stroke-linecap="round" d="' . $d . '"/><path class="igs-mezzeria" fill="none" stroke-width="1.5" stroke-dasharray="6 6" d="' . $d . '"/>';
    $fs = $L ? 13 : 13.5; $sx = $L ? ($pt[1][0] - $pt[0][0]) : 0; $aria = [];
    foreach ($voci as $i => $x) {
        [$px, $py] = $pt[$i];
        $ultima = $i === $n - 1 && $flag;
        if ($ultima) $o .= '<g' . igs_c($x, $v, $i) . '><line class="igs-linea" stroke-width="2.2" x1="' . igs_n($px) . '" y1="' . igs_n($py) . '" x2="' . igs_n($px) . '" y2="' . igs_n($py - 40) . '"/><polygon class="igs-p" points="' . igs_punti([[$px, $py - 40], [$px + 26, $py - 32], [$px, $py - 24]]) . '"/><circle class="igs-pieno" cx="' . igs_n($px) . '" cy="' . igs_n($py) . '" r="4.5"/></g>';
        else $o .= '<g' . igs_c($x, $v, $i) . '><circle class="igs-p igs-anello-carta" cx="' . igs_n($px) . '" cy="' . igs_n($py) . '" r="13.5" stroke-width="3"/>' . igs_testo($px, $py + 4.5, [(string)($i + 1)], 'igs-su igs-num', 12.5, 'middle', 700) . '</g>';
        $nome = igs_pulito($x['nome'] ?? '', 80);
        if ($L) {
            // Sopra e sotto il sentiero, a turno: due etichette dalla stessa parte stanno a due tappe di distanza.
            // La meta va dalla parte opposta della penultima (sopra la bandiera, se la penultima sta sotto).
            if ($i === 0) { $r = igs_a_capo($nome, $fs, min(170, $sx + 4), 2, true); $o .= igs_testo($px - 14, $py + 32, $r, 'igs-t', $fs, 'start', 700, 1.15); }
            elseif ($i === $n - 1) {
                $sopra = ($n - 2) % 2 === 0;
                $r = igs_a_capo($nome, $fs, min(170, $sx + ($ultima ? 14 : 0)), 2, true);
                $ys = $ultima ? $py - 50 : $py - 22;
                $o .= $sopra ? igs_testo($px + ($ultima ? 26 : 12), $ys - (count($r) - 1) * $fs * 1.15, $r, 'igs-t', $fs, 'end', 700, 1.15) : igs_testo($px + 12, $py + 32, $r, 'igs-t', $fs, 'end', 700, 1.15);
            }
            elseif ($i % 2) { $r = igs_a_capo($nome, $fs, min(170, 2 * $sx - 16), 2, true); $o .= igs_testo($px, $py - 22 - (count($r) - 1) * $fs * 1.15, $r, 'igs-t', $fs, 'middle', 700, 1.15); }
            else { $r = igs_a_capo($nome, $fs, min(170, 2 * $sx - 16), 2, true); $o .= igs_testo($px, $py + 32, $r, 'igs-t', $fs, 'middle', 700, 1.15); }
        } else {
            $r = igs_a_capo($nome, $fs, $W - 132 - 4, 2, true);
            $o .= igs_testo(132, igs_y_centro($py - ($ultima ? 12 : 0), count($r), $fs, 1.15), $r, 'igs-t', $fs, 'start', 700, 1.15);
        }
        $aria[] = ($i + 1) . '. ' . $nome;
    }
    return ['svg' => $o, 'h' => $H, 'aria' => 'Il viaggio, tappa per tappa: ' . implode('; ', $aria) . ($flag ? ' (la meta)' : '') . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 12. Bilancia
// ------------------------------------------------------------
function igs_bilancia(array $v, int $W): array {
    $pro = igs_voci($v['pro'] ?? [], ['testo'], 8); $con = igs_voci($v['contro'] ?? [], ['testo'], 8);
    $note = [igs_nota_troppe($v['pro'] ?? [], 8, 'voci a sinistra'), igs_nota_troppe($v['contro'] ?? [], 8, 'voci a destra')];
    if (!$pro && !$con) return ['vuoto' => 'Aggiungi le voci dei due piatti della bilancia.', 'note' => $note];
    $L = $W >= 500;
    $inc = (string)($v['inclinazione'] ?? 'auto');
    $diff = count($pro) - count($con);
    $gradi = match ($inc) { 'pro' => 7.0, 'contro' => -7.0, 'pari' => 0.0, default => $diff === 0 ? 0.0 : ($diff > 0 ? 1 : -1) * min(10, 5 + 2 * abs($diff)) };
    $a = deg2rad($gradi);
    $cx = $W / 2; $py = $L ? 66 : 60; $hb = $L ? 176 : 112;
    $e1 = [$cx - $hb * cos($a), $py + $hb * sin($a)]; $e2 = [$cx + $hb * cos($a), $py - $hb * sin($a)];
    $pw = $L ? 46 : 38;
    $colW = $L ? 210 : 140;
    $lc = [max($colW / 2 + 2, $e1[0]), min($W - $colW / 2 - 2, $e2[0])];
    $o = '';
    $lati = [[$e1, $pro, 'etichetta_pro', igs_cf((string)($v['colore_pro'] ?? 'verde')), $lc[0]], [$e2, $con, 'etichetta_contro', igs_cf((string)($v['colore_contro'] ?? 'rosso')), $lc[1]]];
    $fondo = 0; $testi = '';
    foreach ($lati as [$e, $voci, $kEt, $col, $tx]) {
        [$ex, $ey] = $e;
        $o .= '<line class="igs-filo-scuro" x1="' . igs_n($ex) . '" y1="' . igs_n($ey) . '" x2="' . igs_n($ex - $pw * .62) . '" y2="' . igs_n($ey + 40) . '"/><line class="igs-filo-scuro" x1="' . igs_n($ex) . '" y1="' . igs_n($ey) . '" x2="' . igs_n($ex + $pw * .62) . '" y2="' . igs_n($ey + 40) . '"/>'
            . '<g' . $col . '><path class="igs-p" d="M' . igs_n($ex - $pw) . ' ' . igs_n($ey + 40) . ' Q' . igs_n($ex) . ' ' . igs_n($ey + 70) . ' ' . igs_n($ex + $pw) . ' ' . igs_n($ey + 40) . ' Z"/></g>';
        $y = $ey + 92;
        $testi .= igs_testo($tx, $y, [igs_tronca(mb_strtoupper(igs_pulito($v[$kEt] ?? '', 40)), 12, $colW, true)], 'igs-t', 12, 'middle', 700, 1.25, ' letter-spacing=".1em"');
        $y += 21;
        foreach ($voci as $x) { $r = igs_a_capo($x['testo'] ?? '', 13.5, $colW, 2); $testi .= igs_testo($tx, $y, $r, 'igs-tm', 13.5, 'middle', 400, 1.2); $y += count($r) * 16.2 + 4; }
        $fondo = max($fondo, $y);
    }
    $H = $fondo + 18;
    $o = '<rect class="igs-pieno" x="' . igs_n($cx - 4) . '" y="' . igs_n($py) . '" width="8" height="' . igs_n($H - 12 - $py) . '" rx="3"/><rect class="igs-pieno" x="' . igs_n($cx - 48) . '" y="' . igs_n($H - 12) . '" width="96" height="10" rx="4"/>'
        . '<line class="igs-linea" stroke-width="5" stroke-linecap="round" x1="' . igs_n($e1[0]) . '" y1="' . igs_n($e1[1]) . '" x2="' . igs_n($e2[0]) . '" y2="' . igs_n($e2[1]) . '"/><circle class="igs-pieno" cx="' . igs_n($cx) . '" cy="' . igs_n($py) . '" r="8"/>' . $o . $testi;
    $pende = $gradi > 0 ? 'pende a sinistra' : ($gradi < 0 ? 'pende a destra' : 'è in equilibrio');
    $aria = 'Bilancia, ' . $pende . '. ' . igs_pulito($v['etichetta_pro'] ?? '') . ': ' . implode('; ', array_map(fn($x) => igs_pulito($x['testo'] ?? ''), $pro)) . '. ' . igs_pulito($v['etichetta_contro'] ?? '') . ': ' . implode('; ', array_map(fn($x) => igs_pulito($x['testo'] ?? ''), $con)) . '.';
    return ['svg' => $o, 'h' => $H, 'aria' => $aria, 'note' => $note];
}

// ------------------------------------------------------------
// 13. Mappa di calore
// ------------------------------------------------------------
function igs_calore(array $v, int $W): array {
    $colonne = array_slice(array_values(array_filter(array_map(fn($x) => igs_pulito($x, 40), explode(',', (string)($v['colonne'] ?? ''))), fn($x) => $x !== '')), 0, 12);
    $righe = igs_voci($v['righe'] ?? [], ['nome', 'valori'], 12);
    $note = [igs_nota_troppe($v['righe'] ?? [], 12, 'righe')];
    if (count(array_filter(explode(',', (string)($v['colonne'] ?? '')), fn($x) => trim($x) !== '')) > 12) $note[] = 'Si vedono solo 12 colonne.';
    if (!$righe) return ['vuoto' => 'Aggiungi le righe della mappa di calore, con un valore da 0 a 4 per colonna.', 'note' => $note];
    $nc = count($colonne);
    if (!$nc) { foreach ($righe as $r) $nc = max($nc, count(igs_valori($r['valori'] ?? '', 12, 0, 4))); $colonne = array_fill(0, max(1, $nc), ''); $nc = max(1, $nc); }
    $L = $W >= 500;
    $lw = $L ? 70 : 48; $gap = 3; $ch = $L ? 34 : 28; $hh = 24;
    $cw = min($L ? 58 : 40, ($W - $lw) / $nc - $gap);
    $gw = $lw + $nc * ($cw + $gap); $ox = $L ? ($W - $gw) / 2 : 0;
    $op = [.07, .26, .48, .72, 1];
    $fs = $L ? 12 : 11;
    $o = ''; $aria = [];
    foreach ($colonne as $j => $c) $o .= igs_testo($ox + $lw + $j * ($cw + $gap) + $cw / 2, 15, [igs_tronca($c, $fs, $cw - 1, true)], 'igs-tm', $fs, 'middle', 600);
    $o .= '<g' . igs_cf((string)($v['colore'] ?? 'ciano_scuro'), $v['colore_libero'] ?? '') . '>';
    $celle = ''; $nomi = '';
    foreach ($righe as $i => $r) {
        $y = $hh + $i * ($ch + $gap);
        $nome = igs_pulito($r['nome'] ?? '', 40);
        $nomi .= igs_testo($ox + $lw - 8, $y + $ch / 2 + 4, [igs_tronca($nome, $fs, $lw - 10)], 'igs-tm igs-num', $fs, 'end');
        $vals = array_map(fn($x) => (int)round($x), igs_valori($r['valori'] ?? '', $nc, 0, 4));
        $riga = [];
        foreach ($colonne as $j => $c) {
            $x = $vals[$j] ?? 0;
            $celle .= '<rect class="igs-p" fill-opacity="' . $op[$x] . '" x="' . igs_n($ox + $lw + $j * ($cw + $gap)) . '" y="' . igs_n($y) . '" width="' . igs_n($cw) . '" height="' . $ch . '" rx="4"><title>' . h(trim($c . ' ' . $nome) . ': ' . $x) . '</title></rect>';
            $riga[] = ($c !== '' ? $c . ' ' : '') . $x;
        }
        $aria[] = $nome . ': ' . implode(', ', $riga);
    }
    $o .= $celle;
    $ly = $hh + count($righe) * ($ch + $gap) + 16;
    $poco = igs_tronca(igs_pulito($v['etichetta_poco'] ?? '', 30), 12, 90); $tanto = igs_tronca(igs_pulito($v['etichetta_tanto'] ?? '', 30), 12, 90);
    $bx = max($ox + $gw - 5 * 22 - igs_largh($tanto, 12) - 8, igs_largh($poco, 12) + 12);
    for ($k = 0; $k < 5; $k++) $o .= '<rect class="igs-p" fill-opacity="' . $op[$k] . '" x="' . igs_n($bx + $k * 22) . '" y="' . igs_n($ly - 1) . '" width="19" height="14" rx="3"/>';
    $o .= '</g>' . $nomi;
    for ($k = 0; $k < 5; $k++) $o .= igs_testo($bx + $k * 22 + 9.5, $ly + 27, [(string)$k], 'igs-tm igs-num', 10.5);
    $o .= igs_testo($bx - 8, $ly + 10.5, [$poco], 'igs-tm', 12, 'end') . igs_testo($bx + 5 * 22 + 4, $ly + 10.5, [$tanto], 'igs-tm', 12, 'start');
    return ['svg' => $o, 'h' => $ly + 32, 'aria' => 'Mappa di calore, valori da 0 (' . igs_pulito($v['etichetta_poco'] ?? '') . ') a 4 (' . igs_pulito($v['etichetta_tanto'] ?? '') . '). ' . implode('. ', $aria) . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 14. Cerchi concentrici
// ------------------------------------------------------------
function igs_cerchi(array $v, int $W): array {
    $voci = igs_voci($v['cerchi'] ?? [], ['nome', 'testo'], 4);
    $note = [igs_nota_troppe($v['cerchi'] ?? [], 4, 'cerchi')];
    if (!$voci) return ['vuoto' => 'Aggiungi i cerchi, dall\'esterno al centro.', 'note' => $note];
    $L = $W >= 500; $n = count($voci);
    $op = [1 => [], 2 => [.2], 3 => [.16, .38], 4 => [.14, .28, .44]][$n];
    $R = $L ? 150 : 132; $cx = $L ? 160 : $W / 2; $cy = $R + 4;
    $col = igs_cf((string)($v['colore'] ?? 'ciano'), $v['colore_libero'] ?? '');
    $rad = array_map(fn($k) => $R * ($n - $k) / $n, range(0, $n - 1));
    $o = '<g' . $col . '>';
    foreach ($voci as $k => $x) $o .= '<circle class="' . ($k === $n - 1 ? 'igs-p2' : 'igs-p') . '"' . ($k < $n - 1 ? ' fill-opacity="' . $op[$k] . '"' : '') . ' cx="' . igs_n($cx) . '" cy="' . igs_n($cy) . '" r="' . igs_n($rad[$k]) . '"/>';
    $fs = $L ? 14.5 : 14;
    foreach ($voci as $k => $x) {
        $nome = igs_pulito($x['nome'] ?? '', 60);
        if ($k === $n - 1) $o .= igs_testo($cx, igs_y_centro($cy, 1, $fs + 1), [igs_tronca($nome, $fs + 1, 2 * $rad[$k] - 12, true)], 'igs-su2', $fs + 1, 'middle', 700);
        else $o .= igs_testo($cx, $cy - ($rad[$k] + $rad[$k + 1]) / 2 + $fs * .35, [igs_tronca($nome, $fs, 2 * sqrt(max(0, $rad[$k + 1] ** 2 - 0)) * .9, true)], 'igs-t', $fs, 'middle', 700);
    }
    $o .= '</g>';
    $aria = [];
    if ($L) {
        $lx = $cx + $R + 40; $tw = $W - $lx - 2;
        foreach ($voci as $k => $x) {
            $yk = $cy - $R + 2 * $R * ($k + .5) / $n;
            $rm = $k === $n - 1 ? $rad[$k] * .55 : ($rad[$k] + $rad[$k + 1]) / 2;
            $si = max(-.92, min(.92, ($yk - $cy) / $rm)); $px = $cx + $rm * sqrt(1 - $si * $si); $pyy = $cy + $rm * $si;
            $o .= '<polyline class="igs-filo-scuro" fill="none" points="' . igs_punti([[$px, $pyy], [$lx - 26, $yk], [$lx - 8, $yk]]) . '"/><circle class="igs-pieno" cx="' . igs_n($px) . '" cy="' . igs_n($pyy) . '" r="3"/>';
            $r2 = igs_a_capo($x['testo'] ?? '', 12.5, $tw, 2);
            $tot = 14.5 * 1.2 + count($r2) * 12.5 * 1.25;
            $y0 = $yk - $tot / 2 + 14.5 * .85;
            $o .= igs_testo($lx, $y0, [igs_tronca(igs_pulito($x['nome'] ?? '', 60), 14.5, $tw, true)], 'igs-t', 14.5, 'start', 700) . igs_testo($lx, $y0 + 14.5 * .35 + 12.5 * 1.15, $r2, 'igs-tm', 12.5, 'start');
        }
        $h = 2 * $R + 10;
    } else {
        $y = 2 * $R + 34;
        foreach ($voci as $k => $x) {
            $o .= '<g' . $col . '><circle class="' . ($k === $n - 1 ? 'igs-p2' : 'igs-p igs-bordo') . '"' . ($k < $n - 1 ? ' fill-opacity="' . max(.3, $op[$k] + .1) . '"' : '') . ' cx="14" cy="' . igs_n($y - 5) . '" r="9"/></g>';
            $r2 = igs_a_capo($x['testo'] ?? '', 12.5, $W - 34, 2);
            $o .= igs_testo(32, $y, [igs_tronca(igs_pulito($x['nome'] ?? '', 60), 14, $W - 34, true)], 'igs-t', 14, 'start', 700) . igs_testo(32, $y + 18, $r2, 'igs-tm', 12.5, 'start');
            $y += 26 + count($r2) * 15.6 + 6;
        }
        $h = $y - 14;
    }
    foreach ($voci as $x) $aria[] = igs_pulito($x['nome'] ?? '') . (igs_pulito($x['testo'] ?? '') !== '' ? ': ' . igs_pulito($x['testo']) : '');
    return ['svg' => $o, 'h' => $h, 'aria' => 'Cerchi concentrici, dall\'esterno al centro: ' . implode('; ', $aria) . '.', 'note' => $note];
}

// ------------------------------------------------------------
// 15. Barre al cento per cento
// ------------------------------------------------------------
function igs_barre100(array $v, int $W): array {
    $cat = igs_voci($v['voci'] ?? [], ['nome'], 6);
    $barre = igs_voci($v['barre'] ?? [], ['nome', 'valori'], 4);
    $note = [igs_nota_troppe($v['voci'] ?? [], 6, 'voci'), igs_nota_troppe($v['barre'] ?? [], 4, 'barre')];
    if (!$cat || !$barre) return ['vuoto' => 'Aggiungi le voci (con il loro colore) e almeno una barra con le percentuali.', 'note' => $note];
    $L = $W >= 500; $bh = $L ? 40 : 36; $o = ''; $y = 18; $aria = [];
    foreach ($barre as $b) {
        $vals = igs_valori($b['valori'] ?? '', count($cat), 0, 100);
        $sum = array_sum($vals);
        $nome = igs_pulito($b['nome'] ?? '', 80);
        if (abs($sum - 100) > .05) $note[] = 'La barra "' . $nome . '" fa ' . igs_num($sum) . '%: ogni barra deve fare 100 (le parti si allargano o si stringono per riempirla).';
        $o .= igs_testo(0, $y, [igs_tronca($nome, 14, $W, true)], 'igs-t', 14, 'start', 700);
        $x = 0.0; $parti = [];
        foreach ($vals as $k => $p) {
            if ($sum <= 0) break;
            $w = $W * $p / $sum;
            if ($w > .5) {
                $t = igs_num($p) . '%';
                $o .= '<g' . igs_c($cat[$k], $v, $k) . '><rect class="igs-p" x="' . igs_n($x) . '" y="' . igs_n($y + 8) . '" width="' . igs_n(max(.5, $w - 2)) . '" height="' . $bh . '" rx="3.5"/>'
                    . (!empty($v['mostra_percentuali']) && igs_largh($t, 13, true) + 10 <= $w - 2 ? igs_testo($x + ($w - 2) / 2, $y + 8 + $bh / 2 + 4.5, [$t], 'igs-su igs-num', 13, 'middle', 700) : '') . '</g>';
            }
            $x += $w;
            $parti[] = igs_pulito($cat[$k]['nome'] ?? '') . ' ' . igs_num($p) . '%';
        }
        if ($sum <= 0) $o .= '<rect class="igs-anello-r" x="0" y="' . igs_n($y + 8) . '" width="' . $W . '" height="' . $bh . '" rx="3.5"/>';
        $aria[] = $nome . ': ' . implode(', ', $parti);
        $y += $bh + ($L ? 44 : 42);
    }
    $y += 2; $x = 0.0;
    foreach ($cat as $k => $c) {
        $t = igs_tronca(igs_pulito($c['nome'] ?? '', 60), 12.5, $W - 24);
        $w = 20 + igs_largh($t, 12.5) + 18;
        if ($x > 0 && $x + $w > $W) { $x = 0; $y += 22; }
        $o .= '<g' . igs_c($c, $v, $k) . '><rect class="igs-p" x="' . igs_n($x) . '" y="' . igs_n($y - 11) . '" width="13" height="13" rx="3"/></g>' . igs_testo($x + 19, $y, [$t], 'igs-tm', 12.5, 'start');
        $x += $w;
    }
    return ['svg' => $o, 'h' => $y + 8, 'aria' => 'Barre al cento per cento. ' . implode('. ', $aria) . '.', 'note' => $note];
}
