<?php
/**
 * I widget dell'editor delle pagine: per ognuno, i controlli (letti dall'editor per costruire il pannello)
 * e la funzione che lo disegna in HTML con le classi del sito.
 *
 * Ogni controllo sta in una scheda (contenuto, stile, avanzate), può essere "responsive"
 * (un valore per desktop, tablet e telefono: {"d":..,"t":..,"m":..}) e ha un valore di partenza.
 * Tipi di controllo: testo, testolungo, ricco, numero, colore, scelta, interruttore, immagine, link, codice, ripetitore.
 */

require_once __DIR__ . '/editor-controlli.php';

/**
 * Un controllo. $extra: opzioni, min, max, passo, unita, intervalli, responsive, stati, selettore, proprieta, valore_css, mappa,
 * hover_su, condizione, sezione, campi (ripetitore), aiuto. Tipi e strutture: editor-controlli.php (e docs-sviluppo/editor-pagine.md nel repository).
 */
function ec(string $nome, string $tipo, string $etichetta, $predefinito = '', string $scheda = 'contenuto', array $extra = []): array {
    return ['nome' => $nome, 'tipo' => $tipo, 'etichetta' => $etichetta, 'predefinito' => $predefinito, 'scheda' => $scheda] + $extra;
}

// Scorciatoie per la scheda Stile: controllo con selettore (il CSS esce da solo) e sezione a tendina.
function es_tip(string $nome, string $et, string $sel, string $sez, array $pred = []): array { return ec($nome, 'tipografia', $et, $pred, 'stile', ['selettore' => $sel, 'sezione' => $sez]); }
function es_col(string $nome, string $et, string $sel, string $sez, string $prop = 'color', string $pred = '', array $extra = []): array { return ec($nome, 'colore', $et, $pred, 'stile', ['selettore' => $sel, 'proprieta' => $prop, 'sezione' => $sez] + $extra); }
function es_colh(string $nome, string $et, string $sel, string $sez, string $prop = 'color', $pred = ''): array { return ec($nome, 'colore', $et, $pred, 'stile', ['selettore' => $sel, 'proprieta' => $prop, 'sezione' => $sez, 'stati' => true, 'hover_su' => 'elemento']); }
function es_sfo(string $nome, string $et, string $sel, string $sez, $pred = [], bool $stati = true, string $hover = 'elemento'): array { return ec($nome, 'sfondo', $et, $pred, 'stile', ['selettore' => $sel, 'sezione' => $sez, 'stati' => $stati, 'hover_su' => $hover]); }
function es_bor(string $nome, string $et, string $sel, string $sez, $pred = [], bool $stati = true, string $hover = 'elemento'): array { return ec($nome, 'bordo', $et, $pred, 'stile', ['selettore' => $sel, 'sezione' => $sez, 'stati' => $stati, 'hover_su' => $hover]); }
function es_omb(string $nome, string $et, string $sel, string $sez, $pred = [], bool $stati = true, string $hover = 'elemento'): array { return ec($nome, 'ombra', $et, $pred, 'stile', ['selettore' => $sel, 'sezione' => $sez, 'stati' => $stati, 'hover_su' => $hover]); }
function es_pad(string $nome, string $et, string $sel, string $sez, $pred = [], string $prop = 'padding'): array { return ec($nome, 'dimensioni', $et, $pred, 'stile', ['selettore' => $sel, 'proprieta' => $prop, 'sezione' => $sez, 'responsive' => true, 'unita' => ['px', 'em', 'rem', '%'], 'intervalli' => $prop === 'padding' ? ['px' => [0, 300, 1], 'em' => [0, 10, 0.05], 'rem' => [0, 10, 0.05], '%' => [0, 50, 1]] : []]); }
function es_mis(string $nome, string $et, string $sel, string $sez, string $prop, $pred = [], array $unita = ['px', 'em', 'rem', '%'], array $extra = []): array { return ec($nome, 'misura', $et, $pred, 'stile', ['selettore' => $sel, 'proprieta' => $prop, 'sezione' => $sez, 'responsive' => true, 'unita' => $unita] + $extra); }
function es_all(string $nome, string $et, string $sel, string $sez, string $prop = 'text-align', $pred = ['d' => ''], array $extra = []): array { return ec($nome, 'allineamento', $et, $pred, 'stile', ['selettore' => $sel, 'proprieta' => $prop, 'sezione' => $sez, 'responsive' => true] + $extra); }
/** Un valore di misura {v,u} per i predefiniti. */
function em_(float|int|string $v, string $u = 'px'): array { return ['d' => ['v' => $v, 'u' => $u]]; }
/** Quattro lati uguali (o sopra/destra/sotto/sinistra) per i predefiniti di dimensioni. */
function el_(float|int|string $a, $b = null, $c = null, $d = null, string $u = 'px'): array { return ['d' => ['sopra' => $a, 'destra' => $b ?? $a, 'sotto' => $c ?? $a, 'sinistra' => $d ?? $b ?? $a, 'u' => $u, 'legati' => $b === null]]; }
/** Il blocco "colore del testo + tipografia" per un pezzo del widget. */
function es_testo(string $prefisso, string $et, string $sel, string $sez, string $predColore = '', array $predTip = []): array {
    return [es_col($prefisso . '_colore', 'Colore' . ($et !== '' ? ' ' . $et : ''), $sel, $sez, 'color', $predColore), es_tip($prefisso . '_tipografia', 'Tipografia' . ($et !== '' ? ' ' . $et : ''), $sel, $sez, $predTip)];
}

/**
 * La sezione "Foto" della scheda Stile per i widget con la foto velata (Apertura e Presentazione, 1.2.2): trattamento
 * (velo colorato, bianco e nero, naturale), colori e intensità del velo, effetto animato con i suoi colori e la velocità.
 * $sel: il contenitore della foto (' .finestra' o ' .ritratto'); $coloriVelo: le etichette dei colori del velo, in ordine.
 * Il CSS dei colori esce come variabili (--velo-N, --velo-o, --fx-1, --fx-2, --fx-v) lette da sito.css; trattamento ed
 * effetto diventano classi nel disegno del widget (editor_foto_classi). Con i valori di partenza la foto è quella di prima.
 */
function es_foto(string $sel, array $coloriVelo): array {
    $velo = ['condizione' => ['foto_trattamento' => 'velo']];
    $fx = ['condizione' => ['!foto_effetto' => '']];
    $c = [ec('foto_trattamento', 'scelta', 'Trattamento della foto', 'velo', 'stile', ['sezione' => 'Foto', 'opzioni' => EDITOR_FOTO_TRATTAMENTI])];
    foreach (array_values($coloriVelo) as $i => $et) $c[] = es_col('velo_colore_' . ($i + 1), $et, $sel, 'Foto', '--velo-' . ($i + 1), '', $velo);
    $c[] = ec('velo_intensita', 'numero', 'Intensità del velo (%)', 100, 'stile', ['sezione' => 'Foto', 'min' => 0, 'max' => 100, 'unita' => '%', 'selettore' => $sel, 'proprieta' => '--velo-o'] + $velo);
    $c[] = ec('foto_effetto', 'scelta', 'Effetto animato', '', 'stile', ['sezione' => 'Foto', 'opzioni' => EDITOR_FOTO_EFFETTI]);
    $c[] = es_col('effetto_colore_1', "Primo colore dell'effetto", $sel, 'Foto', '--fx-1', '', $fx);
    $c[] = es_col('effetto_colore_2', "Secondo colore dell'effetto", $sel, 'Foto', '--fx-2', '', $fx);
    $c[] = ec('effetto_velocita', 'scelta', "Velocità dell'effetto", '', 'stile', ['sezione' => 'Foto', 'opzioni' => ['lenta' => 'Lenta', '' => 'Normale', 'veloce' => 'Veloce'],
        'selettore' => $sel, 'proprieta' => '--fx-v', 'mappa' => ['lenta' => '1.6', 'veloce' => '.55']] + $fx);
    return $c;
}

/** Le classi della foto velata secondo trattamento ed effetto; vuote con i valori di partenza. */
function editor_foto_classi(array $v): string {
    $t = (string)($v['foto_trattamento'] ?? 'velo');
    $e = (string)($v['foto_effetto'] ?? '');
    return (isset(EDITOR_FOTO_TRATTAMENTI[$t]) && $t !== 'velo' ? ' ed-foto-' . $t : '') . ($e !== '' && isset(EDITOR_FOTO_EFFETTI[$e]) ? ' ed-fx ed-fx-' . $e : '');
}

const EDITOR_FOTO_TRATTAMENTI = ['velo' => 'Velo colorato sul bianco e nero', 'bn' => 'Solo bianco e nero', 'naturale' => 'Foto naturale, a colori'];
const EDITOR_FOTO_EFFETTI = ['' => 'Nessuno', 'zoom' => 'Zoom lento', 'bagliore' => 'Bagliore che attraversa', 'cornice' => 'Cornice luminosa che gira',
    'alone' => 'Alone che pulsa', 'scintille' => 'Scintille che salgono'];

// Le dieci disposizioni dell'Apertura in più della classica (1.2.6).
require_once __DIR__ . '/editor-hero.php';

const EDITOR_ALLINEAMENTI = ['' => 'Come la sezione', 'left' => 'Sinistra', 'center' => 'Centro', 'right' => 'Destra'];
const EDITOR_STILI_PULSANTE = ['viola' => 'Pieno viola', 'ciano' => 'Pieno ciano', 'filo' => 'Contorno'];

/** I moduli che il widget Modulo può mostrare: la newsletter, i form del pannello e quelli dei moduli del sito (filtro moduli_disponibili). */
function editor_moduli_disponibili(): array {
    $o = ['newsletter' => 'Newsletter'];
    try {
        require_once __DIR__ . '/form.php';
        foreach (form_tutti() as $f) $o['form:' . $f['id']] = 'Form: ' . $f['nome'] . ($f['attivo'] ? '' : ' (spento)');
    } catch (Throwable $e) { /* senza database (prove a secco) resta la newsletter */ }
    return (array)cms_filtro('moduli_disponibili', $o);
}

/** Controlli che hanno tutti i widget, nella scheda Avanzate (come Elementor: layout, movimento, trasformazione, sfondo, bordo, posizione, responsive, attributi, CSS). */
function editor_controlli_comuni(): array {
    return editor_controlli_avanzate('widget');
}

/** Le definizioni: sezione, colonna e widget. Usate dal pannello (in JSON) e dal disegno. */
function editor_definizioni(): array {
    static $d = null;
    if ($d !== null) return $d;
    $allinea = fn($nome = 'allineamento') => ec($nome, 'scelta', 'Allineamento', ['d' => ''], 'stile', ['opzioni' => EDITOR_ALLINEAMENTI, 'responsive' => true]);
    $colonne = fn($pred) => ec('colonne', 'numero', 'Colonne', ['d' => $pred, 't' => min($pred, 2), 'm' => 1], 'stile', ['min' => 1, 'max' => 4, 'responsive' => true]);
    $d = [
        'sezione' => ['etichetta' => 'Sezione', 'icona' => '▭', 'controlli' => array_merge([
            ec('larghezza', 'scelta', 'Larghezza del contenuto', 'contenuta', 'contenuto', ['sezione' => 'Layout', 'opzioni' => ['contenuta' => 'Contenuta (come il sito)', 'stretta' => 'Stretta (lettura)', 'piena' => 'Piena', 'personalizzata' => 'Personalizzata (in pixel)', 'articolo' => 'Articolo (colonna di lettura con sommario)']]),
            ec('larghezza_px', 'misura', 'Larghezza massima del contenuto', em_(1180), 'contenuto', ['sezione' => 'Layout', 'responsive' => true, 'unita' => ['px', '%', 'vw'], 'intervalli' => ['px' => [300, 2400, 10]], 'condizione' => ['larghezza' => 'personalizzata']]),
            ec('altezza_minima', 'numero', 'Altezza minima (% dello schermo)', ['d' => 0], 'contenuto', ['sezione' => 'Layout', 'min' => 0, 'max' => 100, 'responsive' => true]),
            ec('allineamento_verticale', 'scelta', 'Colonne allineate', 'stretch', 'contenuto', ['sezione' => 'Layout', 'opzioni' => ['stretch' => 'Stessa altezza', 'flex-start' => 'In alto', 'center' => 'Al centro', 'flex-end' => 'In basso']]),
            ec('spazio_colonne', 'numero', 'Spazio tra le colonne (px)', ['d' => 32], 'contenuto', ['sezione' => 'Layout', 'min' => 0, 'max' => 120, 'responsive' => true]),
            ec('overflow', 'scelta', 'Contenuto che sborda', '', 'contenuto', ['sezione' => 'Layout', 'opzioni' => ['' => 'Visibile', 'hidden' => 'Tagliato (nascosto)']]),
            // Stile
            ec('sfondo', 'scelta', 'Sfondo', 'nessuno', 'stile', ['sezione' => 'Sfondo', 'opzioni' => ['nessuno' => 'Nessuno (sfondo del sito)', 'vetro' => 'Vetro', 'gradiente' => 'Gradiente viola e ciano', 'colore' => 'Colore', 'immagine' => 'Immagine', 'avanzato' => 'Avanzato (colore, immagine, sfumatura, al passaggio)']]),
            ec('colore_sfondo', 'colore', 'Colore di sfondo', '#0a1024', 'stile', ['sezione' => 'Sfondo', 'condizione' => ['sfondo' => 'colore']]),
            ec('immagine_sfondo', 'immagine', 'Immagine di sfondo', '', 'stile', ['sezione' => 'Sfondo', 'condizione' => ['sfondo' => 'immagine']]),
            ec('velo', 'numero', "Velo scuro sull'immagine (%)", 50, 'stile', ['sezione' => 'Sfondo', 'min' => 0, 'max' => 95, 'condizione' => ['sfondo' => 'immagine']]),
            es_sfo('sfondo_g', 'Sfondo', '', 'Sfondo', [], true, 'contenitore') + ['condizione' => ['sfondo' => 'avanzato']],
            es_bor('bordo_g', 'Bordo', '', 'Bordo', [], true, 'contenitore'),
            es_omb('ombra_g', 'Ombra', '', 'Bordo', [], true, 'contenitore'),
            es_col('colore_testo', 'Colore del testo', '', 'Tipografia'),
            es_col('colore_titoli', 'Colore dei titoli', ' h1, h2, h3, h4', 'Tipografia'),
            es_colh('colore_link', 'Colore dei link', ' a', 'Tipografia'),
            es_tip('tipografia_g', 'Tipografia del testo', '', 'Tipografia'),
            es_all('allineamento_testo', 'Allineamento del testo', '', 'Tipografia'),
            ec('spazio_sopra', 'numero', 'Spazio sopra (px)', ['d' => 72, 'm' => 48], 'stile', ['sezione' => 'Spazi', 'min' => 0, 'max' => 300, 'responsive' => true]),
            ec('spazio_sotto', 'numero', 'Spazio sotto (px)', ['d' => 72, 'm' => 48], 'stile', ['sezione' => 'Spazi', 'min' => 0, 'max' => 300, 'responsive' => true]),
        ], editor_controlli_avanzate('sezione'))],
        'colonna' => ['etichetta' => 'Colonna', 'icona' => '▯', 'controlli' => array_merge([
            ec('larghezza', 'numero', 'Larghezza (%)', ['d' => 100, 'm' => 100], 'contenuto', ['sezione' => 'Layout', 'min' => 10, 'max' => 100, 'responsive' => true]),
            ec('allineamento_verticale', 'scelta', 'Contenuto', 'flex-start', 'contenuto', ['sezione' => 'Layout', 'opzioni' => ['flex-start' => 'In alto', 'center' => 'Al centro', 'flex-end' => 'In basso', 'space-between' => 'Distribuito']]),
            ec('allineamento_orizzontale', 'allineamento', 'Widget allineati', ['d' => ''], 'contenuto', ['sezione' => 'Layout', 'responsive' => true, 'flex' => true, 'stretch' => true, 'selettore' => '', 'proprieta' => 'align-items']),
            es_mis('spazio_widget', 'Spazio fra i widget', '', 'Layout', 'gap', [], ['px', 'em', 'rem'], ['intervalli' => ['px' => [0, 120, 1]], 'aiuto' => 'Si somma allo spazio sotto di ogni widget.']) + ['scheda' => 'contenuto'],
            ec('riquadro', 'interruttore', 'Riquadro di vetro', false, 'stile', ['sezione' => 'Sfondo']),
            es_sfo('sfondo_g', 'Sfondo', '', 'Sfondo', [], true, 'contenitore'),
            es_bor('bordo_g', 'Bordo', '', 'Bordo', [], true, 'contenitore'),
            es_omb('ombra_g', 'Ombra', '', 'Bordo', [], true, 'contenitore'),
            ec('spazio_interno', 'numero', 'Spazio interno (px, tutti i lati)', ['d' => 0], 'stile', ['sezione' => 'Spazi', 'min' => 0, 'max' => 120, 'responsive' => true]),
            es_col('colore_testo', 'Colore del testo', '', 'Tipografia'),
            es_col('colore_titoli', 'Colore dei titoli', ' h1, h2, h3, h4', 'Tipografia'),
            es_colh('colore_link', 'Colore dei link', ' a', 'Tipografia'),
            es_tip('tipografia_g', 'Tipografia del testo', '', 'Tipografia'),
            ec('allineamento', 'scelta', 'Allineamento del testo', ['d' => ''], 'stile', ['sezione' => 'Tipografia', 'opzioni' => EDITOR_ALLINEAMENTI, 'responsive' => true]),
        ], editor_controlli_avanzate('colonna'))],
        // ---------------- widget
        'titolo' => ['etichetta' => 'Titolo', 'icona' => 'H', 'gruppo' => 'Base', 'controlli' => [
            ec('occhiello', 'testo', 'Occhiello (riga piccola sopra)', ''),
            ec('testo', 'testo', 'Titolo', 'Scrivi il titolo', 'contenuto', ['aiuto' => 'Metti tra *asterischi* le parole da evidenziare con il gradiente.']),
            ec('livello', 'scelta', 'Livello', 'h2', 'contenuto', ['opzioni' => ['h1' => 'H1 (uno per pagina)', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4']]),
            ec('sottotitolo', 'testolungo', 'Testo sotto il titolo', ''),
            ec('dimensione', 'numero', 'Dimensione (px, 0 = automatica)', ['d' => 0], 'stile', ['sezione' => 'Titolo', 'min' => 0, 'max' => 120, 'responsive' => true]),
            ec('maiuscolo', 'interruttore', 'Tutto maiuscolo', false, 'stile', ['sezione' => 'Titolo']),
            es_col('colore', 'Colore', ' .ed-titolo', 'Titolo'),
            es_col('colore_evidenza', 'Colore delle parole tra asterischi (vuoto = gradiente del sito)', ' .ed-titolo em', 'Titolo', 'background'),
            es_tip('tipografia', 'Tipografia', ' .ed-titolo', 'Titolo'),
            ec('ombra_testo', 'ombra_testo', 'Ombra del testo', [], 'stile', ['sezione' => 'Titolo', 'selettore' => ' .ed-titolo']),
            $allinea() + ['sezione' => 'Titolo'],
            ...es_testo('occhiello', "dell'occhiello", ' .occhiello', 'Occhiello'),
            ...es_testo('sottotitolo', 'del testo sotto', ' .ed-sottotitolo', 'Testo sotto il titolo'),
        ]],
        'testo' => ['etichetta' => 'Testo', 'icona' => '¶', 'gruppo' => 'Base', 'controlli' => [
            ec('contenuto', 'ricco', 'Testo', '<p>Scrivi qui il testo.</p>'),
            ec('dimensione', 'numero', 'Dimensione (px, 0 = come il sito)', ['d' => 0], 'stile', ['sezione' => 'Testo', 'min' => 0, 'max' => 40, 'responsive' => true]),
            es_col('colore', 'Colore', ' .ed-testo', 'Testo'),
            es_tip('tipografia', 'Tipografia', ' .ed-testo', 'Testo'),
            ec('larghezza_max', 'numero', 'Larghezza massima (px, 0 = tutta)', 0, 'stile', ['sezione' => 'Testo', 'min' => 0, 'max' => 1400]),
            $allinea() + ['sezione' => 'Testo'],
            ec('colonne_testo', 'numero', 'Colonne di testo', ['d' => 1], 'stile', ['sezione' => 'Testo', 'min' => 1, 'max' => 4, 'responsive' => true, 'selettore' => ' .ed-testo', 'proprieta' => 'column-count']),
            es_colh('colore_link', 'Colore dei link', ' .ed-testo a', 'Link'),
            ...es_testo('titoli', 'dei titoli nel testo', ' .ed-testo h2, .ed-testo h3, .ed-testo h4', 'Titoli nel testo'),
        ]],
        'immagine' => ['etichetta' => 'Immagine', 'icona' => '▣', 'gruppo' => 'Base', 'controlli' => [
            ec('immagine', 'immagine', 'Immagine', ''),
            ec('alt', 'testo', 'Testo alternativo (per chi non vede e per Google)', ''),
            ec('didascalia', 'testo', 'Didascalia', ''),
            ec('link', 'link', 'Link', ['url' => '', 'nuova' => false]),
            ec('larghezza', 'numero', 'Larghezza (%)', ['d' => 100], 'stile', ['sezione' => 'Immagine', 'min' => 5, 'max' => 100, 'responsive' => true]),
            es_mis('larghezza_max', 'Larghezza massima', ' img', 'Immagine', 'max-width', [], ['px', '%', 'vw'], ['intervalli' => ['px' => [0, 2000, 1]]]),
            es_mis('altezza', 'Altezza', ' img', 'Immagine', 'height', [], ['px', 'vh'], ['intervalli' => ['px' => [0, 1200, 1]]]),
            ec('adatta', 'scelta', "Come riempie l'altezza", '', 'stile', ['sezione' => 'Immagine', 'opzioni' => ['' => 'Predefinito', 'cover' => 'Riempi (taglia)', 'contain' => 'Contieni', 'fill' => 'Stira'], 'selettore' => ' img', 'proprieta' => 'object-fit', 'condizione' => ['!altezza' => '']]),
            ec('arrotondamento', 'numero', 'Angoli arrotondati (px)', 18, 'stile', ['sezione' => 'Immagine', 'min' => 0, 'max' => 400]),
            ec('ritratto', 'interruttore', 'Ritaglio tondo (ritratto)', false, 'stile', ['sezione' => 'Immagine']),
            $allinea() + ['sezione' => 'Immagine'],
            ec('opacita', 'numero', 'Opacità (%)', '', 'stile', ['sezione' => 'Immagine', 'min' => 0, 'max' => 100, 'stati' => true, 'hover_su' => 'elemento', 'selettore' => ' img', 'proprieta' => 'opacity', 'valore_css' => 'calc({{v}} / 100)']),
            es_bor('bordo', 'Bordo', ' img', 'Bordo'),
            es_omb('ombra', 'Ombra', ' img', 'Bordo'),
            ec('img_trasformazione', 'trasformazione', 'Trasformazione (anche al passaggio)', [], 'stile', ['sezione' => 'Bordo', 'stati' => true, 'hover_su' => 'elemento', 'selettore' => ' img']),
            ...es_testo('didascalia', 'della didascalia', ' figcaption', 'Didascalia'),
            es_all('didascalia_allineamento', 'Allineamento della didascalia', ' figcaption', 'Didascalia'),
        ]],
        'pulsanti' => ['etichetta' => 'Pulsanti', 'icona' => '⬭', 'gruppo' => 'Base', 'controlli' => [
            ec('voci', 'ripetitore', 'Pulsanti', [['testo' => 'Scopri di più', 'link' => '#', 'stile' => 'viola', 'nuova' => false]], 'contenuto', ['campi' => [
                ec('testo', 'testo', 'Testo', 'Pulsante'), ec('link', 'testo', 'Link', '/'), ec('stile', 'scelta', 'Stile', 'viola', 'contenuto', ['opzioni' => EDITOR_STILI_PULSANTE]),
                ec('nuova', 'interruttore', 'Apri in una nuova scheda', false),
            ], 'titolo_voce' => 'testo']),
            $allinea() + ['sezione' => 'Pulsanti'],
            ec('pieni', 'interruttore', 'Larghi quanto la colonna', false, 'stile', ['sezione' => 'Pulsanti']),
            es_mis('spazio', 'Spazio fra i pulsanti', ' .ed-pulsanti', 'Pulsanti', 'gap', [], ['px', 'em'], ['intervalli' => ['px' => [0, 80, 1]]]),
            es_tip('tipografia', 'Tipografia', ' .btn', 'Pulsanti'),
            es_pad('pulsante_padding', 'Spazio interno', ' .btn', 'Pulsanti'),
            es_colh('colore_testo', 'Colore del testo', ' .btn', 'Colori'),
            es_sfo('sfondo', 'Sfondo (vince sullo stile scelto)', ' .btn', 'Colori'),
            es_bor('bordo', 'Bordo', ' .btn', 'Colori'),
            es_omb('ombra', 'Ombra', ' .btn', 'Colori'),
        ]],
        'spaziatore' => ['etichetta' => 'Spazio', 'icona' => '↕', 'gruppo' => 'Base', 'controlli' => [
            ec('altezza', 'numero', 'Altezza (px)', ['d' => 48, 'm' => 32], 'contenuto', ['min' => 0, 'max' => 400, 'responsive' => true]),
        ]],
        'divisore' => ['etichetta' => 'Linea', 'icona' => '—', 'gruppo' => 'Base', 'controlli' => [
            ec('stile', 'scelta', 'Stile', 'gradiente', 'stile', ['sezione' => 'Linea', 'opzioni' => ['gradiente' => 'Gradiente', 'linea' => 'Linea sottile']]),
            es_col('colore', 'Colore (vuoto = come lo stile)', ' .ed-divisore', 'Linea', 'background'),
            ec('larghezza', 'numero', 'Larghezza (%)', 100, 'stile', ['sezione' => 'Linea', 'min' => 5, 'max' => 100]),
            ec('spessore', 'numero', 'Spessore (px)', 2, 'stile', ['sezione' => 'Linea', 'min' => 1, 'max' => 12]),
            ec('allineamento', 'allineamento', 'Allineamento', 'center', 'stile', ['sezione' => 'Linea', 'selettore' => ' .ed-divisore', 'proprieta' => 'margin', 'opzioni' => ['left' => 'Sinistra', 'center' => 'Centro', 'right' => 'Destra'], 'mappa' => ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto']]),
        ]],
        'elenco' => ['etichetta' => 'Elenco', 'icona' => '☰', 'gruppo' => 'Base', 'controlli' => [
            ec('voci', 'ripetitore', 'Voci', [['testo' => 'Prima voce'], ['testo' => 'Seconda voce']], 'contenuto', ['campi' => [ec('testo', 'testolungo', 'Testo', '')], 'titolo_voce' => 'testo']),
            ec('segno', 'scelta', 'Segno', 'spunta', 'stile', ['sezione' => 'Elenco', 'opzioni' => ['spunta' => 'Spunta', 'freccia' => 'Freccia', 'punto' => 'Punto', 'numero' => 'Numero']]),
            es_col('colore_segno', 'Colore del segno', ' li::before', 'Elenco'),
            es_mis('spazio_voci', 'Spazio fra le voci', ' li', 'Elenco', 'padding-bottom', [], ['px', 'em'], ['intervalli' => ['px' => [0, 60, 1]]]),
            ...es_testo('testo', 'del testo', ' li', 'Elenco'),
        ]],
        'frase' => ['etichetta' => 'Frase in evidenza', 'icona' => '❝', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('testo', 'testolungo', 'Frase', 'Una frase che resta in mente, *con la parte che conta in evidenza.*', 'contenuto', ['aiuto' => 'Tra *asterischi* la parte con il gradiente.']),
            ec('autore', 'testo', 'Firma (facoltativa)', ''),
            es_col('colore', 'Colore della frase', ' .frase', 'Frase'),
            es_col('colore_evidenza', 'Colore della parte tra asterischi (vuoto = gradiente)', ' .frase em', 'Frase', 'background'),
            es_tip('tipografia', 'Tipografia', ' .frase', 'Frase'),
            es_all('allineamento', 'Allineamento', ' .frase', 'Frase'),
            ...es_testo('autore', 'della firma', ' .ed-autore', 'Firma'),
        ]],
        'passi' => ['etichetta' => 'Passi numerati', 'icona' => '①', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('voci', 'ripetitore', 'Passi', [['titolo' => 'Primo passo', 'testo' => 'Descrizione.'], ['titolo' => 'Secondo passo', 'testo' => 'Descrizione.'], ['titolo' => 'Terzo passo', 'testo' => 'Descrizione.']], 'contenuto',
                ['campi' => [ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', '')], 'titolo_voce' => 'titolo']),
            $colonne(3) + ['sezione' => 'Griglia'],
            es_mis('spazio', 'Spazio fra i riquadri', ' .ed-griglia', 'Griglia', 'gap', [], ['px', 'em'], ['intervalli' => ['px' => [0, 80, 1]]]),
            es_sfo('sfondo_card', 'Sfondo del riquadro', ' .passo', 'Riquadro'),
            es_bor('bordo_card', 'Bordo del riquadro', ' .passo', 'Riquadro'),
            es_omb('ombra_card', 'Ombra del riquadro', ' .passo', 'Riquadro'),
            es_pad('padding_card', 'Spazio interno del riquadro', ' .passo', 'Riquadro'),
            es_col('colore_numero', 'Colore del numero', ' .passo::before', 'Numero'),
            ...es_testo('titolo', 'del titolo', ' .passo h3', 'Titolo'),
            ...es_testo('testo', 'del testo', ' .passo p', 'Testo'),
        ]],
        'schede' => ['etichetta' => 'Schede con link', 'icona' => '▤', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('voci', 'ripetitore', 'Schede', [['titolo' => 'Il tuo servizio', 'testo' => 'Descrizione.', 'link' => '#', 'invito' => 'Scopri →', 'evidenza' => true]], 'contenuto',
                ['campi' => [ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', ''), ec('link', 'testo', 'Link', ''), ec('invito', 'testo', 'Invito (in fondo)', 'Scopri →'), ec('evidenza', 'interruttore', 'In evidenza', false)], 'titolo_voce' => 'titolo']),
            $colonne(3) + ['sezione' => 'Griglia'],
            es_mis('spazio', 'Spazio fra le schede', ' .ed-griglia', 'Griglia', 'gap', [], ['px', 'em'], ['intervalli' => ['px' => [0, 80, 1]]]),
            es_sfo('sfondo_card', 'Sfondo della scheda', ' .servizio', 'Scheda'),
            es_bor('bordo_card', 'Bordo della scheda', ' .servizio', 'Scheda'),
            es_omb('ombra_card', 'Ombra della scheda', ' .servizio', 'Scheda'),
            es_pad('padding_card', 'Spazio interno della scheda', ' .servizio', 'Scheda'),
            ...es_testo('titolo', 'del titolo', ' .servizio h3', 'Titolo'),
            ...es_testo('testo', 'del testo', ' .servizio p', 'Testo'),
            es_colh('invito_colore', "Colore dell'invito", ' .servizio .vai', 'Invito'),
            es_tip('invito_tipografia', "Tipografia dell'invito", ' .servizio .vai', 'Invito'),
        ]],
        'presentazione' => ['etichetta' => 'Presentazione', 'icona' => '☺', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('immagine', 'immagine', 'Ritratto', (string)cms_impostazione('w_ritratto', '')),
            ec('occhiello', 'testo', 'Occhiello', 'Chi sono'),
            ec('titolo', 'testo', 'Titolo', (string)cms_impostazione('w_chi_sono_titolo', 'Chi sono')),
            ec('testo', 'testolungo', 'Testo', ''),
            ec('voci', 'ripetitore', 'Voci', [['etichetta' => 'Focus', 'valore' => '']], 'contenuto', ['campi' => [ec('etichetta', 'testo', 'Etichetta', ''), ec('valore', 'testo', 'Valore', '')], 'titolo_voce' => 'etichetta']),
            es_mis('ritratto_misura', 'Misura del ritratto', ' .ritratto', 'Ritratto', 'width', [], ['px'], ['intervalli' => ['px' => [60, 400, 2]], 'valore_css' => '{{v}}']),
            es_mis('ritratto_altezza', 'Altezza del ritratto (uguale alla misura)', ' .ritratto', 'Ritratto', 'height', [], ['px'], ['intervalli' => ['px' => [60, 400, 2]]]),
            es_omb('ritratto_ombra', 'Ombra del ritratto', ' .ritratto', 'Ritratto', [], false),
            ...es_testo('occhiello', "dell'occhiello", ' .occhiello', 'Occhiello'),
            ...es_testo('titolo', 'del titolo', ' .sez-titolo', 'Titolo'),
            ...es_testo('testo', 'del testo', ' .sez-intro', 'Testo'),
            ...es_foto(' .ritratto', ['Colore del velo in alto', 'Colore del velo in basso']),
            ...es_testo('etichette', 'delle etichette', ' dt', 'Voci'),
            ...es_testo('valori', 'dei valori', ' dd', 'Voci'),
        ]],
        'hero' => ['etichetta' => 'Apertura (hero)', 'icona' => '★', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('disposizione', 'scelta', 'Disposizione', 'classica', 'contenuto', ['opzioni' => EDITOR_HERO_DISPOSIZIONI, 'aiuto' => 'Undici modi di mettere insieme le stesse parole e la stessa foto. Cambiando disposizione i testi restano.']),
            ec('occhiello', 'testo', 'Occhiello', (string)cms_impostazione('w_hero_occhiello', cms_impostazione('sito_sottotitolo', ''))),
            ec('titolo', 'testo', 'Titolo (H1)', 'Il titolo della pagina, *con le parole che contano.*', 'contenuto', ['aiuto' => 'Tra *asterischi* la parte con il gradiente.']),
            ec('testo', 'testolungo', 'Testo', ''),
            ec('pulsanti', 'ripetitore', 'Pulsanti', [['testo' => 'Scopri di più', 'link' => '#', 'stile' => 'viola']], 'contenuto', ['campi' => [
                ec('testo', 'testo', 'Testo', ''), ec('link', 'testo', 'Link', ''), ec('stile', 'scelta', 'Stile', 'viola', 'contenuto', ['opzioni' => EDITOR_STILI_PULSANTE])], 'titolo_voce' => 'testo']),
            ec('immagine', 'immagine', 'Foto', (string)cms_impostazione('w_hero_foto', '')),
            ec('onda_mostra', 'interruttore', "Mostra l'onda sopra la foto", true, 'contenuto', ['aiuto' => "Il riquadro con le barre che ondeggiano. Spento, sulla foto restano solo velo ed effetto."]),
            ec('etichetta_onda', 'testo', "Etichetta dell'onda", (string)cms_impostazione('w_onda_etichetta', ''), 'contenuto', ['condizione' => ['onda_mostra' => true]]),
            ec('barre', 'ripetitore', 'Barre', [['etichetta' => 'Avanzamento', 'valore' => 78]], 'contenuto', ['campi' => [ec('etichetta', 'testo', 'Etichetta', ''), ec('valore', 'numero', 'Valore (%)', 50, 'contenuto', ['min' => 0, 'max' => 100])], 'titolo_voce' => 'etichetta']),
            ...es_testo('occhiello', "dell'occhiello", ' .occhiello', 'Occhiello'),
            ...es_testo('titolo', 'del titolo', ' h1', 'Titolo'),
            es_col('colore_evidenza', 'Colore della parte tra asterischi (vuoto = gradiente)', ' h1 em', 'Titolo', 'background'),
            ...es_testo('testo', 'del testo', ' .lead', 'Testo'),
            ...es_foto(' .finestra', ['Colore del velo in alto a sinistra', 'Colore del velo al centro', 'Colore del velo in basso a destra']),
            es_mis('spazio_pulsanti', 'Spazio fra i pulsanti', ' .azioni', 'Pulsanti', 'gap', [], ['px'], ['intervalli' => ['px' => [0, 60, 1]]]),
            es_tip('pulsanti_tipografia', 'Tipografia dei pulsanti', ' .btn', 'Pulsanti'),
            es_bor('pulsanti_bordo', 'Bordo dei pulsanti', ' .btn', 'Pulsanti'),
        ]],
        'numeri' => ['etichetta' => 'Numeri', 'icona' => '#', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('voci', 'ripetitore', 'Numeri', [['valore' => '3', 'etichetta' => 'passi per cominciare']], 'contenuto', ['campi' => [ec('valore', 'testo', 'Numero', ''), ec('etichetta', 'testo', 'Etichetta', '')], 'titolo_voce' => 'valore']),
            $colonne(3) + ['sezione' => 'Griglia'],
            es_mis('spazio', 'Spazio fra i riquadri', ' .ed-griglia', 'Griglia', 'gap', [], ['px', 'em'], ['intervalli' => ['px' => [0, 80, 1]]]),
            es_sfo('sfondo_card', 'Sfondo del riquadro', ' .ed-numero', 'Riquadro'),
            es_bor('bordo_card', 'Bordo del riquadro', ' .ed-numero', 'Riquadro'),
            es_omb('ombra_card', 'Ombra del riquadro', ' .ed-numero', 'Riquadro'),
            es_pad('padding_card', 'Spazio interno del riquadro', ' .ed-numero', 'Riquadro'),
            es_col('numero_colore', 'Colore del numero (vuoto = gradiente)', ' .ed-numero b', 'Numero', 'background'),
            es_tip('numero_tipografia', 'Tipografia del numero', ' .ed-numero b', 'Numero'),
            ...es_testo('etichetta', "dell'etichetta", ' .ed-numero span', 'Etichetta'),
        ]],
        'faq' => ['etichetta' => 'Domande frequenti', 'icona' => '?', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('voci', 'ripetitore', 'Domande', [['domanda' => 'Una domanda?', 'risposta' => 'La risposta.']], 'contenuto', ['campi' => [ec('domanda', 'testo', 'Domanda', ''), ec('risposta', 'testolungo', 'Risposta', '')], 'titolo_voce' => 'domanda',
                'aiuto' => 'Le domande entrano anche nei dati per Google (FAQPage).']),
            ec('prima_aperta', 'interruttore', 'Prima domanda aperta', true),
            es_sfo('sfondo_voce', 'Sfondo della domanda', ' details', 'Domanda'),
            es_bor('bordo_voce', 'Bordo della domanda', ' details', 'Domanda'),
            es_mis('spazio_voci', 'Spazio fra le domande', ' details', 'Domanda', 'margin-bottom', [], ['px', 'em'], ['intervalli' => ['px' => [0, 60, 1]]]),
            es_pad('padding_domanda', 'Spazio interno della domanda', ' summary', 'Domanda'),
            es_colh('domanda_colore', 'Colore della domanda', ' summary', 'Domanda'),
            es_tip('domanda_tipografia', 'Tipografia della domanda', ' summary', 'Domanda'),
            es_col('icona_colore', "Colore del segno + e -", ' summary::after', 'Domanda'),
            ...es_testo('risposta', 'della risposta', ' details p', 'Risposta'),
            es_pad('padding_risposta', 'Spazio interno della risposta', ' details p', 'Risposta'),
        ]],
        'video' => ['etichetta' => 'Video', 'icona' => '▶', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('url', 'testo', 'Indirizzo YouTube o Vimeo', ''),
            ec('titolo', 'testo', 'Titolo del video', ''),
            ec('copertina', 'immagine', 'Copertina (facoltativa)', '', 'contenuto', ['aiuto' => 'Il video si carica solo quando la persona preme play: prima nessun contatto con YouTube.']),
            ec('proporzioni', 'scelta', 'Proporzioni', '', 'stile', ['sezione' => 'Video', 'opzioni' => ['' => '16:9', '4/3' => '4:3', '1/1' => 'Quadrato', '9/16' => 'Verticale 9:16', '21/9' => 'Cinema 21:9'], 'selettore' => ' .ed-video', 'proprieta' => 'aspect-ratio']),
            es_bor('bordo', 'Bordo', ' .ed-video', 'Video', [], false),
            es_omb('ombra', 'Ombra', ' .ed-video', 'Video', [], false),
            es_col('pulsante_colore', 'Colore del pulsante play (vuoto = gradiente)', ' .ed-video button span', 'Pulsante', 'background'),
            es_mis('pulsante_misura', 'Misura del pulsante play', ' .ed-video button span', 'Pulsante', 'width', [], ['px'], ['intervalli' => ['px' => [40, 160, 2]]]),
            es_mis('pulsante_altezza', 'Altezza del pulsante play (uguale alla misura)', ' .ed-video button span', 'Pulsante', 'height', [], ['px'], ['intervalli' => ['px' => [40, 160, 2]]]),
            ...es_testo('testo', 'del titolo sul video', ' .ed-video button', 'Pulsante'),
        ]],
        'articoli' => ['etichetta' => 'Ultimi articoli', 'icona' => '▦', 'gruppo' => 'Dal sito', 'controlli' => [
            ec('numero', 'numero', 'Quanti', 3, 'contenuto', ['min' => 1, 'max' => 12]),
            ec('solo_pillar', 'interruttore', 'Solo i pillar', true),
            ec('categoria', 'testo', 'Solo una categoria (slug, facoltativo)', ''),
            ec('colonne', 'numero', 'Colonne', ['d' => 3, 't' => 2, 'm' => 1], 'stile', ['sezione' => 'Griglia', 'min' => 1, 'max' => 4, 'responsive' => true, 'selettore' => ' .griglia', 'proprieta' => 'grid-template-columns', 'valore_css' => 'repeat({{v}},minmax(0,1fr))']),
            es_mis('spazio', 'Spazio fra le schede', ' .griglia', 'Griglia', 'gap', [], ['px', 'em'], ['intervalli' => ['px' => [0, 80, 1]]]),
            es_sfo('sfondo_card', 'Sfondo della scheda', ' .card', 'Scheda'),
            es_bor('bordo_card', 'Bordo della scheda', ' .card', 'Scheda'),
            es_omb('ombra_card', 'Ombra della scheda', ' .card', 'Scheda'),
            es_pad('padding_card', 'Spazio interno della scheda', ' .card', 'Scheda'),
            ...es_testo('titolo', 'del titolo', ' .card-titolo', 'Titolo'),
            ...es_testo('meta', 'di categoria e data', ' .card-meta', 'Meta'),
        ]],
        'modulo' => ['etichetta' => 'Modulo', 'icona' => '✉', 'gruppo' => 'Dal sito', 'controlli' => [
            ec('tipo', 'scelta', 'Quale modulo', 'newsletter', 'contenuto', ['opzioni' => editor_moduli_disponibili(), 'accetta' => '/^[a-z][a-z0-9_:-]{0,40}$/', 'aiuto' => 'I form li crei dal pannello, voce Form. Lì decidi anche a chi arriva l\'email di avviso a ogni compilazione (Dopo l\'invio, Manda l\'avviso a).']),
            es_sfo('sfondo', 'Sfondo del riquadro', ' .modulo', 'Riquadro', [], false),
            es_bor('bordo', 'Bordo del riquadro', ' .modulo', 'Riquadro', [], false),
            es_pad('riquadro_padding', 'Spazio interno del riquadro', ' .modulo', 'Riquadro'),
            ...es_testo('titolo', 'del titolo', ' .modulo h2', 'Titolo'),
            ...es_testo('testo', 'del testo', ' .modulo>p', 'Testo'),
            es_col('etichette_colore', 'Colore delle etichette', ' .modulo-campi label', 'Campi'),
            es_col('campi_colore', 'Colore del testo nei campi', ' .modulo-campi input, .modulo-campi textarea, .modulo select', 'Campi'),
            es_col('campi_sfondo', 'Sfondo dei campi', ' .modulo-campi input, .modulo-campi textarea, .modulo select', 'Campi', 'background-color'),
            es_bor('campi_bordo', 'Bordo dei campi', ' .modulo-campi input, .modulo-campi textarea, .modulo select', 'Campi', [], false),
            es_colh('pulsante_colore', 'Colore del testo del pulsante', ' .btn', 'Pulsante'),
            es_sfo('pulsante_sfondo', 'Sfondo del pulsante', ' .btn', 'Pulsante'),
            es_bor('pulsante_bordo', 'Bordo del pulsante', ' .btn', 'Pulsante'),
            es_tip('pulsante_tipografia', 'Tipografia del pulsante', ' .btn', 'Pulsante'),
        ]],
        'infografica' => ['etichetta' => 'Infografica', 'icona' => '▥', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('tipo', 'scelta', 'Tipo', 'steps', 'contenuto', ['opzioni' => ['steps' => 'Passaggi numerati', 'checklist' => 'Lista con spunte', 'compare' => 'Confronto a due colonne', 'stats' => 'Numeri e percentuali', 'quote' => 'Citazione', 'flow' => 'Percorso (timeline)']]),
            ec('titolo', 'testo', 'Titolo', ''),
            ec('voci', 'ripetitore', 'Voci', [['etichetta' => 'Primo passo', 'testo' => 'Descrizione.', 'valore' => '', 'didascalia' => '', 'segno' => 'check', 'sinistra' => '', 'destra' => '']], 'contenuto', ['campi' => [
                ec('etichetta', 'testo', 'Titolo della voce (passaggi, percorso, numeri: etichetta)', ''),
                ec('testo', 'testolungo', 'Testo (passaggi, lista, percorso; citazione: la frase)', ''),
                ec('valore', 'testo', 'Numero (solo per Numeri, es. 70%)', ''),
                ec('didascalia', 'testo', 'Didascalia (Numeri); autore (Citazione)', ''),
                ec('segno', 'scelta', 'Segno (solo lista)', 'check', 'contenuto', ['opzioni' => ['check' => '✓ positivo', 'x' => '✕ negativo']]),
                ec('sinistra', 'testo', 'Colonna sinistra (solo confronto)', ''),
                ec('destra', 'testo', 'Colonna destra (solo confronto)', ''),
            ], 'titolo_voce' => 'etichetta', 'aiuto' => 'Ogni tipo usa solo i campi che gli servono: gli altri si ignorano.']),
            ec('etichetta_sinistra', 'testo', 'Intestazione sinistra (confronto)', 'Invece di'),
            ec('etichetta_destra', 'testo', 'Intestazione destra (confronto)', 'Fai'),
            es_sfo('sfondo', 'Sfondo del riquadro', ' .ig', 'Riquadro', [], false),
            es_bor('bordo', 'Bordo del riquadro', ' .ig', 'Riquadro', [], false),
            es_pad('riquadro_padding', 'Spazio interno', ' .ig', 'Riquadro'),
            ...es_testo('titolo', 'del titolo', ' .ig-title', 'Titolo'),
            es_col('testo_colore', 'Colore del testo', ' .ig', 'Testo'),
            es_col('accento_colore', 'Colore dei numeri e dei segni', ' .ig-step-num, .ig-flow-dot, .ig-check-icon', 'Testo'),
        ]],
        'tabella' => ['etichetta' => 'Tabella', 'icona' => '▦', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('intestazione', 'interruttore', 'La prima riga è l\'intestazione', true),
            ec('righe', 'ripetitore', 'Righe', [['celle' => 'Colonna 1 | Colonna 2'], ['celle' => 'Valore | Valore']], 'contenuto', ['campi' => [ec('celle', 'testolungo', 'Celle, separate da una barra verticale |', '')], 'titolo_voce' => 'celle']),
            es_tip('tipografia', 'Tipografia della tabella', ' table', 'Tabella'),
            es_pad('padding_celle', 'Spazio interno delle celle', ' th, td', 'Tabella'),
            es_col('righe_colore', 'Colore delle righe di separazione', ' th, td', 'Tabella', 'border-color'),
            es_col('alterne_sfondo', 'Sfondo delle righe pari', ' tr:nth-child(even) td', 'Tabella', 'background-color'),
            es_col('intestazione_colore', "Colore dell'intestazione", ' th', 'Intestazione'),
            es_col('intestazione_sfondo', "Sfondo dell'intestazione", ' th', 'Intestazione', 'background-color'),
            es_tip('intestazione_tipografia', "Tipografia dell'intestazione", ' th', 'Intestazione'),
            es_col('celle_colore', 'Colore del testo delle celle', ' td', 'Celle'),
            es_all('celle_allineamento', 'Allineamento nelle celle', ' th, td', 'Celle'),
        ]],
        'scheda' => ['etichetta' => 'Scheda riassuntiva', 'icona' => '▤', 'gruppo' => 'Contenuto', 'controlli' => [
            ec('occhiello', 'testo', 'Occhiello (riga piccola sopra il titolo)', ''),
            ec('titolo', 'testo', 'Titolo', 'Titolo della scheda'),
            ec('sottotitolo', 'testolungo', 'Riga sotto il titolo', ''),
            ec('stile', 'scelta', 'Colore della testata', 'scuro', 'stile', ['opzioni' => ['scuro' => 'Scuro', 'viola' => 'Viola', 'verde' => 'Verde', 'rosso' => 'Rosso', 'oro' => 'Oro', 'ciano' => 'Ciano']]),
            ec('disposizione', 'scelta', 'Disposizione delle voci', 'elenco', 'stile', ['opzioni' => ['elenco' => 'Una sotto l\'altra', 'griglia2' => 'Griglia a due colonne', 'griglia3' => 'Griglia a tre colonne']]),
            ec('voci', 'ripetitore', 'Voci', [['segno' => '1', 'titolo' => 'Prima voce', 'testo' => 'Descrizione.']], 'contenuto', ['campi' => [
                ec('segno', 'testo', 'Segno (numero, ✓, ✗, vuoto)', ''), ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', ''),
            ], 'titolo_voce' => 'titolo']),
            ec('nota', 'testolungo', 'Nota in fondo', ''),
            es_sfo('testata_sfondo', 'Sfondo della testata (vince sul colore scelto)', ' .scheda-testa', 'Testata', [], false),
            es_col('testata_colore', 'Colore del titolo e del testo della testata', ' .scheda-testa, .scheda-testa h3', 'Testata'),
            es_tip('testata_tipografia', 'Tipografia del titolo', ' .scheda-testa h3', 'Testata'),
            es_sfo('sfondo', 'Sfondo della scheda', ' .scheda', 'Scheda', [], false),
            es_bor('bordo', 'Bordo della scheda', ' .scheda', 'Scheda', [], false),
            es_omb('ombra', 'Ombra della scheda', ' .scheda', 'Scheda', [], false),
            es_pad('padding_corpo', 'Spazio interno del corpo', ' .scheda-corpo', 'Scheda'),
            es_sfo('voce_sfondo', 'Sfondo delle voci', ' .scheda-voce', 'Voci', [], false),
            es_bor('voce_bordo', 'Bordo delle voci', ' .scheda-voce', 'Voci', [], false),
            es_col('voce_titolo_colore', 'Colore del titolo delle voci', ' .scheda-voce b', 'Voci'),
            es_col('voce_testo_colore', 'Colore del testo delle voci', ' .scheda-voce p', 'Voci'),
            es_col('segno_colore', 'Colore del segno', ' .scheda-segno', 'Voci'),
            ...es_testo('nota', 'della nota', ' .scheda-nota', 'Nota'),
        ]],
        // I tre riquadri "dal sito": i valori di partenza dei controlli sono l'aspetto di oggi (il CSS esce dai controlli, niente stili inline).
        'cta' => ['etichetta' => 'Invito', 'icona' => '★', 'gruppo' => 'Dal sito', 'controlli' => array_merge([
            ec('titolo', 'testo', 'Titolo', 'Hai un progetto in mente?'),
            ec('testo', 'testolungo', 'Testo', 'Scrivici due righe: ti rispondiamo entro un giorno con una proposta concreta.'),
            ec('pulsante_testo', 'testo', 'Testo del pulsante', 'Scrivici'),
            ec('pulsante_link', 'testo', 'Link del pulsante', '/contatti/'),
        ], editor_controlli_riquadro_cta('.ed-cta', [
            'sfondo' => ['tipo' => 'sfumatura', 'sfumatura' => ['colore1' => 'ig:ciano-2', 'posizione1' => 0, 'colore2' => 'ig:ciano-profondo', 'posizione2' => 100, 'tipo' => 'linear', 'angolo' => 135]],
            'raggio' => 16, 'padding' => el_(2, null, null, null, 'rem'), 'margine' => el_(2.5, 0, 1, 0, 'rem'),
            'titolo_colore' => '#ffffff', 'titolo_tip' => ['dimensione' => em_(1.25, 'rem'), 'peso' => '800'],
            'testo_colore' => 'rgba(255,255,255,.85)', 'testo_tip' => ['dimensione' => em_(0.95, 'rem')], 'testo_larghezza' => em_(520),
            'pulsante_sfondo' => 'ig:rosso', 'pulsante_colore' => '#ffffff', 'pulsante_tip' => ['dimensione' => em_(1, 'rem'), 'peso' => '700'], 'pulsante_padding' => el_(0.85, 2, 0.85, 2, 'rem'), 'pulsante_raggio' => 10,
        ]))],
        'riquadro_test' => ['etichetta' => 'Riquadro in evidenza', 'icona' => '✓', 'gruppo' => 'Dal sito', 'controlli' => array_merge([
            ec('titolo', 'testo', 'Titolo', 'Mettiti alla prova in tre minuti'),
            ec('testo', 'testolungo', 'Testo', 'Poche domande, risultato subito: scopri a che punto sei.'),
            ec('pulsante_testo', 'testo', 'Testo del pulsante', 'Comincia'),
            ec('pulsante_link', 'testo', 'Link del pulsante', '#'),
        ], editor_controlli_riquadro_cta('.ed-test', [
            'sfondo' => ['tipo' => 'classico', 'colore' => 'ig:sfondo'], 'bordo' => ['tipo' => 'solid', 'larghezza' => el_(2), 'colore' => 'ig:oro'],
            'raggio' => 16, 'padding' => el_(1.8, 2, 1.8, 2, 'rem'), 'margine' => el_(2.5, 0, 0.5, 0, 'rem'),
            'titolo_colore' => 'ig:titolo', 'titolo_tip' => ['dimensione' => em_(1.15, 'rem'), 'peso' => '800'],
            'testo_colore' => 'ig:testo', 'testo_tip' => ['dimensione' => em_(0.92, 'rem')], 'testo_larghezza' => em_(480),
            'pulsante_sfondo' => 'ig:oro', 'pulsante_colore' => 'ig:scuro', 'pulsante_tip' => ['dimensione' => em_(0.95, 'rem'), 'peso' => '700'], 'pulsante_padding' => el_(0.75, 1.8, 0.75, 1.8, 'rem'), 'pulsante_raggio' => 10,
        ]))],
        // Leggi anche: colori di partenza leggibili sul fondo scuro del sito (prima il titolo era marrone su blu scuro), tutti modificabili.
        'leggi_anche' => ['etichetta' => 'Leggi anche', 'icona' => '→', 'gruppo' => 'Dal sito', 'controlli' => [
            ec('titolo', 'testo', 'Titolo', 'Leggi anche'),
            ec('voci', 'ripetitore', 'Articoli', [['titolo' => 'Titolo dell\'articolo', 'link' => '/']], 'contenuto', ['campi' => [ec('titolo', 'testo', 'Titolo', ''), ec('link', 'testo', 'Link', '')], 'titolo_voce' => 'titolo']),
            ec('mostra_linea', 'interruttore', 'Riga di separazione sopra', true, 'stile', ['sezione' => 'Riquadro']),
            es_col('linea_colore', 'Colore della riga', ' .ed-leggi-linea', 'Riquadro', 'border-color', 'globale:bordo'),
            es_pad('riquadro_margine', 'Spazio fuori dal riquadro', ' .ed-leggi', 'Riquadro', el_(2.5, 0, 0.5, 0, 'rem'), 'margin'),
            ...es_testo('titolo', 'del titolo', ' .ed-leggi-titolo', 'Titolo', 'globale:testo', ['dimensione' => em_(1.15, 'rem'), 'peso' => '800']),
            es_all('titolo_allineamento', 'Allineamento del titolo', ' .ed-leggi-titolo', 'Titolo'),
            es_mis('colonna_min', 'Larghezza minima di una scheda', ' .ed-leggi-griglia', 'Schede', 'grid-template-columns', em_(220), ['px'], ['intervalli' => ['px' => [120, 600, 10]], 'valore_css' => 'repeat(auto-fit,minmax({{v}},1fr))']),
            es_mis('spazio', 'Spazio fra le schede', ' .ed-leggi-griglia', 'Schede', 'gap', em_(0.8, 'rem'), ['px', 'rem'], ['intervalli' => ['px' => [0, 60, 1]]]),
            es_sfo('voce_sfondo', 'Sfondo della scheda', ' .ed-leggi-voce', 'Schede', ['normale' => ['tipo' => 'classico', 'colore' => 'rgba(255,255,255,.05)'], 'hover' => ['tipo' => 'classico', 'colore' => 'rgba(255,255,255,.1)']]),
            es_bor('voce_bordo', 'Bordo della scheda', ' .ed-leggi-voce', 'Schede', ['normale' => ['tipo' => 'solid', 'larghezza' => el_(1), 'colore' => 'globale:bordo', 'raggio' => el_(12)], 'hover' => ['colore' => 'globale:primario']]),
            es_omb('voce_ombra', 'Ombra della scheda', ' .ed-leggi-voce', 'Schede'),
            es_pad('voce_padding', 'Spazio interno della scheda', ' .ed-leggi-voce', 'Schede', el_(1, 1.2, 1, 1.2, 'rem')),
            es_colh('voce_colore', 'Colore del testo della scheda', ' .ed-leggi-voce', 'Schede', 'color', ['normale' => 'globale:testo', 'hover' => 'globale:primario']),
            es_tip('voce_tipografia', 'Tipografia della scheda', ' .ed-leggi-voce', 'Schede', ['dimensione' => em_(0.92, 'rem'), 'peso' => '600', 'interlinea' => ['d' => ['v' => 1.4, 'u' => '']]]),
        ]],
        'html' => ['etichetta' => 'HTML', 'icona' => '<>', 'gruppo' => 'Avanzati', 'controlli' => [
            ec('codice', 'codice', 'Codice HTML', '', 'contenuto', ['aiuto' => 'Per incollare un pezzo già pronto. Qui finisce anche il contenuto di prima delle pagine.']),
        ]],
    ];
    $d += igx_definizioni();
    // Widget scritti in altri file (editor-widget-tema.php, editor-widget-extra.php): registrati con editor_registra_widget().
    foreach (editor_widget_registrati() as $tipo => $w) if (!isset($d[$tipo])) $d[$tipo] = $w['definizione'];
    foreach ($d as $tipo => &$def) {
        if (!in_array($tipo, ['sezione', 'colonna'], true)) $def['controlli'] = array_merge($def['controlli'], editor_controlli_comuni());
    }
    unset($def);
    return $d;
}

/**
 * I controlli di stile dei riquadri "Invito" e "Riquadro del test": riquadro (sfondo, bordo, raggio, spazi, allineamento), titolo, testo, pulsante.
 * $p: i valori di partenza, cioè l'aspetto di oggi. $sel: la classe del riquadro (" .ed-cta").
 */
function editor_controlli_riquadro_cta(string $sel, array $p): array {
    $sel = ' ' . ltrim($sel);
    return [
        es_sfo('sfondo', 'Sfondo del riquadro', $sel, 'Riquadro', ['normale' => $p['sfondo'] ?? []]),
        es_bor('bordo', 'Bordo del riquadro', $sel, 'Riquadro', ['normale' => ($p['bordo'] ?? []) + ['raggio' => el_($p['raggio'] ?? 0)]]),
        es_omb('ombra', 'Ombra del riquadro', $sel, 'Riquadro'),
        es_pad('riquadro_padding', 'Spazio interno', $sel, 'Riquadro', $p['padding'] ?? []),
        es_pad('riquadro_margine', 'Spazio fuori dal riquadro', $sel, 'Riquadro', $p['margine'] ?? [], 'margin'),
        es_all('allineamento', 'Allineamento', $sel, 'Riquadro', 'text-align', ['d' => 'center']),
        ...es_testo('titolo', 'del titolo', $sel . '-titolo', 'Titolo', $p['titolo_colore'] ?? '', $p['titolo_tip'] ?? []),
        ...es_testo('testo', 'del testo', $sel . '-testo', 'Testo', $p['testo_colore'] ?? '', $p['testo_tip'] ?? []),
        es_mis('testo_larghezza', 'Larghezza massima del testo', $sel . '-testo', 'Testo', 'max-width', $p['testo_larghezza'] ?? [], ['px', '%'], ['intervalli' => ['px' => [200, 1200, 10]]]),
        es_sfo('pulsante_sfondo', 'Sfondo del pulsante', $sel . '-pulsante', 'Pulsante', ['normale' => ['tipo' => 'classico', 'colore' => $p['pulsante_sfondo'] ?? '']]),
        es_colh('pulsante_colore', 'Colore del testo del pulsante', $sel . '-pulsante', 'Pulsante', 'color', ['normale' => $p['pulsante_colore'] ?? '']),
        es_tip('pulsante_tipografia', 'Tipografia del pulsante', $sel . '-pulsante', 'Pulsante', $p['pulsante_tip'] ?? []),
        es_pad('pulsante_padding', 'Spazio interno del pulsante', $sel . '-pulsante', 'Pulsante', $p['pulsante_padding'] ?? []),
        es_bor('pulsante_bordo', 'Bordo del pulsante', $sel . '-pulsante', 'Pulsante', ['normale' => ['raggio' => el_($p['pulsante_raggio'] ?? 0)]]),
        es_omb('pulsante_ombra', 'Ombra del pulsante', $sel . '-pulsante', 'Pulsante'),
    ];
}

/** Il CSS fisso (struttura, non colori) dei widget che prima lo avevano inline: entra una volta nel CSS della pagina se il widget c'è. */
function editor_css_statico_widget(string $tipo): string {
    return match ($tipo) {
        'cta' => '.ed-cta p{margin:0}.ed-cta-titolo{margin:0 0 .5rem}.ed-cta-testo{margin:0 auto 1.3rem}.ed-cta-pulsante{display:inline-block;text-decoration:none}',
        'riquadro_test' => '.ed-test p{margin:0}.ed-test-titolo{margin:0 0 .4rem}.ed-test-testo{margin:0 auto 1.1rem}.ed-test-pulsante{display:inline-block;text-decoration:none}',
        'leggi_anche' => '.ed-leggi-linea{border:0;border-top:1px solid;margin:0 0 1.3rem}.ed-leggi-titolo{margin:0 0 1rem}.ed-leggi-griglia{display:grid}.ed-leggi-voce{display:block;text-decoration:none}',
        default => '',
    };
}

// ------------------------------------------------------------
// Disegno dei widget
// ------------------------------------------------------------

/** *parola* diventa <em>parola</em> (il gradiente del sito); il resto si protegge. */
function editor_evidenza(string $s): string {
    return preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', h($s));
}

function editor_paragrafi(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    return '<p>' . implode('</p><p>', array_map(fn($p) => nl2br(h(trim($p)), false), preg_split('/\n\s*\n/', $s))) . '</p>';
}

/** target e rel di un valore 'link' ({url, nuova, nofollow}). */
function editor_attr_link($link): string {
    if (!is_array($link)) return '';
    $rel = array_filter([!empty($link['nuova']) ? 'noopener' : '', !empty($link['nofollow']) ? 'nofollow' : '']);
    return (!empty($link['nuova']) ? ' target="_blank"' : '') . ($rel ? ' rel="' . implode(' ', $rel) . '"' : '');
}

function editor_link_sicuro(string $url): string {
    // sicurezza: via i caratteri di controllo (java\nscript:), poi solo http, https, percorso interno, ancora, mailto, tel.
    $url = trim(preg_replace('/[\x00-\x20\x7F]+/', '', $url) ?? '');
    if ($url === '') return '';
    return preg_match('~^(https?://|/|#|mailto:|tel:)~i', $url) ? $url : '';
}

/** Testo ricco dall'editor: si tengono solo i tag di formattazione, senza script né attributi strani. */
function editor_ricco_pulito(string $html): string {
    // Gli spazi insecabili (da Word, da copia e incolla) impediscono al testo di andare a capo: diventano spazi normali.
    $html = str_replace(["\u{00A0}", '&nbsp;', '&#160;', '&#xA0;'], ' ', $html);
    // Quill 2 scrive tutte le liste come <ol> con data-list: qui tornano liste vere, prima della pulizia.
    $html = preg_replace_callback('~<ol>((?:(?!</ol>).)*?data-list="bullet"(?:(?!</ol>).)*)</ol>~s', fn($m) => '<ul>' . $m[1] . '</ul>', $html);
    $html = preg_replace('~<span class="ql-ui"[^>]*></span>~', '', $html);
    // sicurezza: il parser DOM tiene solo i tag di formattazione, gli href con schemi ammessi, le classi ql-align-*; via eventi, stili, script.
    require_once __DIR__ . '/sicurezza-html.php';
    return sicurezza_html_pulito($html, 'ricco');
}

/** Il widget in HTML. $v: impostazioni con i valori di partenza già messi. */
function editor_disegna_widget(string $tipo, array $v): string {
    switch ($tipo) {
        case 'titolo':
            $l = in_array($v['livello'], ['h1', 'h2', 'h3', 'h4'], true) ? $v['livello'] : 'h2';
            return ($v['occhiello'] !== '' ? '<span class="occhiello">' . h($v['occhiello']) . '</span>' : '')
                . "<$l class=\"sez-titolo ed-titolo" . ($v['maiuscolo'] ? ' ed-maiuscolo' : '') . '"' . ($v['ancora'] !== '' ? ' id="' . h($v['ancora']) . '"' : '') . '>' . editor_evidenza($v['testo']) . "</$l>"
                . ($v['sottotitolo'] !== '' ? '<div class="sez-intro ed-sottotitolo">' . editor_paragrafi($v['sottotitolo']) . '</div>' : '');
        case 'testo':
            return '<div class="ed-testo corpo">' . editor_ricco_pulito((string)$v['contenuto']) . '</div>';
        case 'immagine':
            if ($v['immagine'] === '') return '<div class="ed-vuoto">Scegli un\'immagine</div>';
            $img = '<img src="' . h($v['immagine']) . '" alt="' . h($v['alt']) . '" loading="lazy">';
            $u = editor_link_sicuro((string)($v['link']['url'] ?? ''));
            if ($u !== '') $img = '<a href="' . h($u) . '"' . editor_attr_link($v['link']) . '>' . $img . '</a>';
            return '<figure class="ed-immagine' . ($v['ritratto'] ? ' ed-ritratto' : '') . '">' . $img . ($v['didascalia'] !== '' ? '<figcaption>' . h($v['didascalia']) . '</figcaption>' : '') . '</figure>';
        case 'pulsanti':
            $out = '';
            foreach ((array)$v['voci'] as $p) {
                $u = editor_link_sicuro((string)($p['link'] ?? '')) ?: '#';
                $st = array_key_exists($p['stile'] ?? '', EDITOR_STILI_PULSANTE) ? $p['stile'] : 'viola';
                $out .= '<a class="btn btn-' . $st . '" href="' . h($u) . '"' . (!empty($p['nuova']) ? ' target="_blank" rel="noopener"' : '') . '>' . h($p['testo'] ?? '') . '</a>';
            }
            return '<div class="azioni ed-pulsanti' . ($v['pieni'] ? ' ed-pieni' : '') . '">' . $out . '</div>';
        case 'spaziatore':
            return '<div class="ed-spazio" aria-hidden="true"></div>';
        case 'divisore':
            return '<hr class="ed-divisore ed-divisore-' . ($v['stile'] === 'linea' ? 'linea' : 'gradiente') . '">';
        case 'elenco':
            $segno = in_array($v['segno'], ['spunta', 'freccia', 'punto', 'numero'], true) ? $v['segno'] : 'spunta';
            $tag = $segno === 'numero' ? 'ol' : 'ul';
            return "<$tag class=\"ed-elenco ed-segno-$segno\">" . implode('', array_map(fn($x) => '<li>' . nl2br(h($x['testo'] ?? ''), false) . '</li>', (array)$v['voci'])) . "</$tag>";
        case 'frase':
            return '<p class="frase">' . editor_evidenza($v['testo']) . '</p>' . ($v['autore'] !== '' ? '<p class="ed-autore">' . h($v['autore']) . '</p>' : '');
        case 'passi':
            return '<div class="passi ed-griglia">' . implode('', array_map(fn($x) => '<div class="passo"><h3>' . h($x['titolo'] ?? '') . '</h3><p>' . nl2br(h($x['testo'] ?? ''), false) . '</p></div>', (array)$v['voci'])) . '</div>';
        case 'schede':
            return '<div class="servizi ed-griglia">' . implode('', array_map(function ($x) {
                $u = editor_link_sicuro((string)($x['link'] ?? ''));
                $tag = $u !== '' ? 'a' : 'div';
                return "<$tag class=\"servizio" . (!empty($x['evidenza']) ? ' servizio-primo' : '') . '"' . ($u !== '' ? ' href="' . h($u) . '"' : '') . '><h3>' . h($x['titolo'] ?? '') . '</h3><p>' . nl2br(h($x['testo'] ?? ''), false) . '</p>'
                    . (($x['invito'] ?? '') !== '' ? '<span class="vai">' . h($x['invito']) . '</span>' : '') . "</$tag>";
            }, (array)$v['voci'])) . '</div>';
        case 'presentazione':
            $dl = implode('', array_map(fn($x) => '<dt>' . h($x['etichetta'] ?? '') . '</dt><dd>' . h($x['valore'] ?? '') . '</dd>', (array)$v['voci']));
            return '<div class="chi">' . ($v['immagine'] !== '' ? '<div class="ritratto' . editor_foto_classi($v) . '"><img src="' . h($v['immagine']) . '" alt="' . h($v['titolo']) . '" width="300" height="300" loading="lazy">' . (($v['foto_effetto'] ?? '') !== '' ? '<span class="ed-fx-luce"></span>' : '') . '</div>' : '')
                . '<div>' . ($v['occhiello'] !== '' ? '<span class="occhiello">' . h($v['occhiello']) . '</span>' : '') . '<h2 class="sez-titolo">' . editor_evidenza($v['titolo']) . '</h2>'
                . ($v['testo'] !== '' ? '<div class="sez-intro" style="margin:0">' . editor_paragrafi($v['testo']) . '</div>' : '') . ($dl !== '' ? "<dl>$dl</dl>" : '') . '</div></div>';
        case 'hero':
            $disp = (string)($v['disposizione'] ?? 'classica');
            if ($disp !== 'classica' && isset(EDITOR_HERO_DISPOSIZIONI[$disp]) && function_exists('editor_hero_' . $disp)) return ('editor_hero_' . $disp)($v);
            $pulsanti = implode('', array_map(fn($p) => '<a class="btn btn-' . (array_key_exists($p['stile'] ?? '', EDITOR_STILI_PULSANTE) ? $p['stile'] : 'viola') . '" href="' . h(editor_link_sicuro((string)($p['link'] ?? '')) ?: '#') . '">' . h($p['testo'] ?? '') . '</a>', (array)$v['pulsanti']));
            $onda = implode('', array_map(fn($x) => '<span style="animation-delay:' . $x . 's"></span>', [0, .2, .4, .1, .5, .3, .6, .15, .45, .25]));
            $barre = implode('', array_map(fn($b) => '<div class="barra">' . h($b['etichetta'] ?? '') . '<div style="--w:' . max(0, min(100, (int)($b['valore'] ?? 0))) . '%"></div></div>', (array)$v['barre']));
            return '<div class="hero ed-hero"><div class="ed-hero-griglia"><div>'
                . ($v['occhiello'] !== '' ? '<span class="occhiello">' . h($v['occhiello']) . '</span>' : '')
                . '<h1>' . editor_evidenza($v['titolo']) . '</h1>' . ($v['testo'] !== '' ? '<p class="lead">' . h($v['testo']) . '</p>' : '')
                . ($pulsanti !== '' ? '<div class="azioni">' . $pulsanti . '</div>' : '') . '</div>'
                . ($v['immagine'] !== '' ? '<div class="scena" aria-hidden="true"><div class="finestra' . editor_foto_classi($v) . '"><img src="' . h($v['immagine']) . '" alt="" width="768" height="768" fetchpriority="high">' . (($v['foto_effetto'] ?? '') !== '' ? '<span class="ed-fx-luce"></span>' : '') . '</div>'
                    . (($v['onda_mostra'] ?? true) && $v['etichetta_onda'] !== '' ? '<div class="chip chip-onda">' . h($v['etichetta_onda']) . '<div class="onda">' . $onda . '</div></div>' : '')
                    . ($barre !== '' ? '<div class="chip chip-barre">' . $barre . '</div>' : '') . '</div>' : '')
                . '</div></div>';
        case 'numeri':
            return '<div class="ed-numeri ed-griglia">' . implode('', array_map(fn($x) => '<div class="ed-numero"><b>' . h($x['valore'] ?? '') . '</b><span>' . h($x['etichetta'] ?? '') . '</span></div>', (array)$v['voci'])) . '</div>';
        case 'faq':
            $i = 0;
            return '<div class="ed-faq">' . implode('', array_map(function ($x) use (&$i, $v) {
                return '<details' . ($i++ === 0 && $v['prima_aperta'] ? ' open' : '') . '><summary>' . h($x['domanda'] ?? '') . '</summary>' . editor_paragrafi((string)($x['risposta'] ?? '')) . '</details>';
            }, (array)$v['voci'])) . '</div>';
        case 'video':
            $emb = editor_video_incorporato((string)$v['url']);
            if (!$emb) return '<div class="ed-vuoto">Incolla l\'indirizzo di un video YouTube o Vimeo</div>';
            // Facciata: prima del clic nessuna richiesta a YouTube o Vimeo (niente cookie di terzi).
            return '<div class="ed-video" data-src="' . h($emb) . '"' . ($v['copertina'] !== '' ? ' style="background-image:url(\'' . h($v['copertina']) . '\')"' : '') . '>'
                . '<button type="button" aria-label="Guarda il video ' . h($v['titolo']) . '"><span>▶</span>' . ($v['titolo'] !== '' ? '<b>' . h($v['titolo']) . '</b>' : '') . '<small>Il video si apre da ' . (str_contains($emb, 'vimeo') ? 'Vimeo' : 'YouTube') . '</small></button></div>';
        case 'articoli':
            $cat = trim((string)$v['categoria']);
            $art = sito_ultimi(max(1, min(12, (int)$v['numero'])), fn($a) => (!$v['solo_pillar'] || $a['pillar']) && ($cat === '' || in_array($cat, $a['categorie'], true)));
            return $art ? '<div class="griglia">' . implode('', array_map('vista_scheda', $art)) . '</div>' : '<div class="ed-vuoto">Ancora nessun articolo da mostrare con questi filtri.</div>';
        case 'modulo':
            // Un tipo che non è newsletter né un form lo disegna il modulo del sito che lo conosce (vista_modulo).
            $t = preg_match('/^[a-z][a-z0-9_:-]{0,40}$/', (string)$v['tipo']) ? $v['tipo'] : 'newsletter';
            $html = vista_modulo($t, $GLOBALS['editor_percorso'] ?? '/');
            // Nell'editor un modulo che nessuno conosce più (era di un modulo del sito tolto) si vede e si spiega; sul sito non esce.
            if ($html === '' && !empty($GLOBALS['editor_ctx']['anteprima'])) $html = '<div class="ed-vuoto">' . (str_starts_with($t, 'form:') ? 'Questo form è spento o è stato eliminato' : 'Il modulo «' . h($t) . '» non c&#039;è più') . ': scegli la newsletter o un form nella scheda Contenuto.</div>';
            return $html;
        case 'infografica':
            if (!function_exists('render_infographic')) require_once __DIR__ . '/infographic.php';
            $voci = (array)$v['voci'];
            $dati = match ($v['tipo']) {
                'checklist' => ['items' => array_map(fn($x) => ['text' => $x['testo'] ?: $x['etichetta'], 'icon' => ($x['segno'] ?? 'check') === 'x' ? 'x' : 'check'], $voci)],
                'compare' => ['left_label' => $v['etichetta_sinistra'], 'right_label' => $v['etichetta_destra'], 'rows' => array_map(fn($x) => ['left' => $x['sinistra'], 'right' => $x['destra']], $voci)],
                'stats' => ['items' => array_map(fn($x) => ['value' => $x['valore'], 'label' => $x['etichetta'], 'caption' => $x['didascalia']], $voci)],
                'quote' => ['text' => $voci[0]['testo'] ?? '', 'author' => $voci[0]['didascalia'] ?? ''],
                default => ['items' => array_map(fn($x) => ['label' => $x['etichetta'], 'text' => $x['testo']], $voci)],
            };
            return render_infographic(['infographic_type' => in_array($v['tipo'], ['steps', 'checklist', 'compare', 'stats', 'quote', 'flow'], true) ? $v['tipo'] : 'steps', 'infographic_title' => $v['titolo'], 'infographic_data' => json_encode($dati, JSON_UNESCAPED_UNICODE)]) ?: '<div class="ed-vuoto">Compila le voci dell\'infografica</div>';
        case 'tabella':
            $righe = array_values(array_filter(array_map(fn($r) => array_map('trim', explode('|', (string)($r['celle'] ?? ''))), (array)$v['righe']), fn($r) => implode('', $r) !== ''));
            if (!$righe) return '<div class="ed-vuoto">Aggiungi le righe della tabella</div>';
            $out = '<table>';
            foreach ($righe as $i => $r) {
                $t = $i === 0 && $v['intestazione'] ? 'th' : 'td';
                $out .= '<tr>' . implode('', array_map(fn($c) => "<$t>" . h($c) . "</$t>", $r)) . '</tr>';
            }
            return $out . '</table>';
        case 'scheda':
            $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['titolo'] ?? '')) !== '' || trim((string)($x['testo'] ?? '')) !== '');
            $out = '<div class="scheda scheda-' . h($v['stile']) . '">';
            if ($v['titolo'] !== '' || $v['occhiello'] !== '') $out .= '<div class="scheda-testa">' . ($v['occhiello'] !== '' ? '<span>' . h($v['occhiello']) . '</span>' : '') . ($v['titolo'] !== '' ? '<h3>' . h($v['titolo']) . '</h3>' : '') . (trim((string)$v['sottotitolo']) !== '' ? '<p>' . nl2br(h($v['sottotitolo']), false) . '</p>' : '') . '</div>';
            $out .= '<div class="scheda-corpo' . ($v['disposizione'] === 'griglia2' ? ' scheda-griglia-2' : ($v['disposizione'] === 'griglia3' ? ' scheda-griglia-3' : '')) . '">';
            foreach ($voci as $x) {
                $segno = trim((string)($x['segno'] ?? ''));
                $out .= '<div class="scheda-voce' . ($segno === '✗' || $segno === '✕' ? ' scheda-no' : ($segno === '✓' ? ' scheda-si' : '')) . '">' . ($segno !== '' ? '<span class="scheda-segno">' . h($segno) . '</span>' : '')
                    . '<div>' . (trim((string)($x['titolo'] ?? '')) !== '' ? '<b>' . h($x['titolo']) . '</b>' : '') . (trim((string)($x['testo'] ?? '')) !== '' ? '<p>' . nl2br(h($x['testo']), false) . '</p>' : '') . '</div></div>';
            }
            $out .= '</div>';
            if (trim((string)$v['nota']) !== '') $out .= '<p class="scheda-nota">' . nl2br(h($v['nota']), false) . '</p>';
            return $out . '</div>';
        case 'cta':
            return '<div class="article-cta-box ed-cta"><p class="ed-cta-titolo">' . h($v['titolo']) . '</p><p class="ed-cta-testo">' . h($v['testo']) . '</p>'
                . '<p><a class="ed-cta-pulsante" href="' . h(editor_link_sicuro((string)$v['pulsante_link']) ?: '#') . '">' . h($v['pulsante_testo']) . '</a></p></div>';
        case 'riquadro_test':
            return '<div class="ed-riquadro-box ed-test"><p class="ed-test-titolo">' . h($v['titolo']) . '</p><p class="ed-test-testo">' . h($v['testo']) . '</p>'
                . '<p><a class="ed-test-pulsante" href="' . h(editor_link_sicuro((string)$v['pulsante_link']) ?: '#') . '">' . h($v['pulsante_testo']) . '</a></p></div>';
        case 'leggi_anche':
            $voci = array_filter((array)$v['voci'], fn($x) => trim((string)($x['titolo'] ?? '')) !== '' && editor_link_sicuro((string)($x['link'] ?? '')) !== '');
            if (!$voci) return '<div class="ed-vuoto">Aggiungi gli articoli da consigliare</div>';
            return '<div class="ed-leggi-anche-box ed-leggi">' . ($v['mostra_linea'] ? '<hr class="ed-leggi-linea">' : '')
                . '<p class="ed-leggi-titolo">' . h($v['titolo']) . '</p><div class="ed-leggi-griglia">'
                . implode('', array_map(fn($x) => '<a class="ed-leggi-voce" href="' . h(editor_link_sicuro((string)$x['link'])) . '">' . h(rtrim(trim((string)$x['titolo']), ' →')) . ' →</a>', $voci))
                . '</div></div>';
        case 'html':
            // sicurezza: il codice e dell'amministratore (il salvataggio lo garantisce); gli <script> prendono il nonce della CSP.
            return function_exists('sicurezza_nonce_script') ? sicurezza_nonce_script((string)$v['codice']) : (string)$v['codice'];
    }
    if (str_starts_with($tipo, 'ig_')) return igx_disegna($tipo, $v);
    return '';
}

// ------------------------------------------------------------
// Infografiche degli articoli (widget ig_*): card chiara con testata colorata,
// una per ogni forma trovata negli articoli (tabelle, flussi, fasi, checklist, barre, mappe...).
// Contratto dei campi: audit2/WIDGET-CONTRATTO.md. CSS: sito.css, classi igx-*.
// ------------------------------------------------------------

/** I colori delle voci: nome → classe CSS (la classe fissa --c, --c2 e --ct). */
function igx_palette(): array {
    return ['rosso' => '#e8553e', 'rosso_scuro' => '#c0392b', 'arancio' => '#e8853e', 'oro' => '#e8a830', 'oro_scuro' => '#b8860b', 'giallo' => '#e8c830',
        'verde' => '#6b8f71', 'verde_scuro' => '#3a7d44', 'ciano' => '#4a7c82', 'ciano_scuro' => '#2d5a5f', 'viola' => '#7b5ea7', 'scuro' => '#2d1b0e', 'marrone' => '#6b4c35', 'bianco' => '#ffffff'];
}

const IGX_SEQUENZA = ['rosso', 'oro', 'verde', 'ciano', 'viola', 'scuro'];

function igx_opzioni_colore(bool $conSequenza = false, bool $conVuoto = false): array {
    $o = [];
    if ($conVuoto) $o[''] = 'Come il widget';
    if ($conSequenza) $o['sequenza'] = 'A rotazione (rosso, oro, verde, ciano, viola, scuro)';
    foreach (['rosso' => 'Rosso', 'rosso_scuro' => 'Rosso scuro', 'arancio' => 'Arancio', 'oro' => 'Oro', 'oro_scuro' => 'Oro scuro', 'giallo' => 'Giallo', 'verde' => 'Verde', 'verde_scuro' => 'Verde scuro',
        'ciano' => 'Ciano', 'ciano_scuro' => 'Ciano scuro', 'viola' => 'Viola', 'scuro' => 'Scuro', 'marrone' => 'Marrone', 'bianco' => 'Bianco'] as $k => $e) $o[$k] = $e;
    return $o;
}

const IGX_TESTATE = ['scuro' => 'Scuro', 'scuro_sfumato' => 'Scuro sfumato', 'verde' => 'Verde', 'ciano' => 'Ciano', 'rosso' => 'Rosso', 'mattone' => 'Mattone', 'viola' => 'Viola', 'oro' => 'Oro', 'oro_scuro' => 'Oro scuro',
    'ciano_viola' => 'Ciano e viola', 'rosso_viola' => 'Rosso e viola', 'viola_rosso' => 'Viola e rosso', 'rosso_scuro' => 'Rosso e scuro'];

/** Le opzioni del campo "tema": i dieci preimpostati più quelli salvati (senza database, solo i preimpostati). */
function igx_opzioni_tema(): array {
    require_once __DIR__ . '/editor-temi.php';
    return editor_temi_opzioni();
}

/** Controlli della cornice (testata, intro, chiusura, firma) che hanno tutti i widget ig_*. */
function igx_controlli_testa(string $occhiello = '', string $titolo = 'Titolo dell\'infografica', string $testata = 'scuro'): array {
    return array_merge([
        ec('tema', 'scelta', 'Tema', 'carta', 'stile', ['opzioni' => igx_opzioni_tema(), 'aiuto' => 'Scegli un tema: i campi di stile qui sotto prendono i suoi valori e restano modificabili. Con "Salva come tema" tieni le tue modifiche con un nome.']),
        ec('occhiello', 'testo', 'Occhiello (riga piccola sopra il titolo)', $occhiello),
        ec('titolo', 'testo', 'Titolo', $titolo),
        ec('sottotitolo', 'testolungo', 'Riga sotto il titolo', ''),
    ], igx_controlli_stile_testata($testata));
}

/** I controlli di stile della testata e della card (quelli che un tema può impostare), senza il campo tema. */
function igx_controlli_stile_testata(string $testata = 'scuro'): array {
    return [
        ec('testata', 'scelta', 'Colore della testata', $testata, 'stile', ['opzioni' => IGX_TESTATE]),
        ec('occhiello_colore', 'scelta', 'Colore dell\'occhiello', 'auto', 'stile', ['opzioni' => ['auto' => 'In armonia con la testata', 'oro' => 'Oro', 'rosso' => 'Rosso', 'verde' => 'Verde', 'ciano' => 'Ciano chiaro', 'viola' => 'Viola chiaro', 'bianco' => 'Bianco']]),
        ec('mostra_occhiello', 'interruttore', 'Mostra l\'occhiello', true, 'stile'),
        ec('testata_colore', 'colore', 'Testata: colore libero (vince sulla scelta sopra)', '', 'stile'),
        ec('testata_colore2', 'colore', 'Testata: secondo colore della sfumatura (facoltativo)', '', 'stile'),
        ec('testata_testo', 'colore', 'Testata: colore del testo (vuoto = bianco)', '', 'stile'),
        ec('testata_allineamento', 'scelta', 'Testata: allineamento', 'sinistra', 'stile', ['opzioni' => ['sinistra' => 'A sinistra', 'centro' => 'Centrato']]),
        ec('corpo', 'scelta', 'Sfondo del corpo', 'crema', 'stile', ['opzioni' => ['crema' => 'Crema (originale)', 'bianco' => 'Bianco', 'scuro' => 'Scuro come il sito', 'libero' => 'Colore libero']]),
        ec('corpo_colore', 'colore', 'Corpo: colore libero dello sfondo', '', 'stile'),
        ec('bordo_colore', 'colore', 'Bordo della card (vuoto = originale #e8ddd0)', '', 'stile'),
        ec('bordo_spessore', 'numero', 'Spessore del bordo (px)', 1, 'stile', ['min' => 0, 'max' => 4]),
        ec('angoli', 'scelta', 'Forma degli angoli', 'arrotondati', 'stile', ['opzioni' => ['squadrati' => 'Squadrati', 'arrotondati' => 'Arrotondati (originale)', 'molto' => 'Molto arrotondati']]),
        ec('ombra', 'interruttore', 'Ombra sotto la card', false, 'stile'),
        ec('spaziatura', 'scelta', 'Spaziatura interna', 'normale', 'stile', ['opzioni' => ['stretta' => 'Stretta', 'normale' => 'Normale (originale)', 'larga' => 'Larga']]),
        ec('testo_dimensione', 'scelta', 'Dimensione del testo', 'normale', 'stile', ['opzioni' => ['piccolo' => 'Piccolo', 'normale' => 'Normale (originale)', 'grande' => 'Grande']]),
        ec('carattere', 'scelta', 'Carattere', 'sito', 'stile', ['opzioni' => ['sito' => 'Quello del sito (Inter)', 'titoli' => 'Quello dei titoli del sito (Space Grotesk)', 'sistema' => 'Di sistema (come l\'originale)', 'serif' => 'Con le grazie (Georgia)', 'mono' => 'A spaziatura fissa']]),
        ec('colore_libero', 'colore', 'Colore libero di tutte le voci (vince sulla palette)', '', 'stile'),
        ec('intro', 'testolungo', 'Paragrafo introduttivo nel corpo (facoltativo)', ''),
    ];
}

function igx_controlli_coda(): array {
    return [
        ec('chiusura_titolo', 'testo', 'Chiusura: prima riga in grassetto (facoltativa)', ''),
        ec('chiusura', 'testolungo', 'Chiusura: testo del riquadro finale (vuoto = nessun riquadro)', ''),
        ec('chiusura_stile', 'scelta', 'Chiusura: stile', 'scuro', 'stile', ['opzioni' => ['scuro' => 'Fascia scura, testo oro', 'tinta' => 'Sfondo colorato leggero', 'bianco' => 'Box bianco', 'pill' => 'Pillola centrata', 'bordo' => 'Box con bordo a sinistra']]),
        ec('chiusura_colore', 'scelta', 'Chiusura: colore', 'rosso', 'stile', ['opzioni' => igx_opzioni_colore()]),
        ec('chiusura_colore_libero', 'colore', 'Chiusura: colore libero', '', 'stile'),
        ec('firma', 'testo', 'Firma in fondo', (string)cms_impostazione('w_firma', cms_impostazione('sito_nome', CMS_NOME))),
        ec('firma_stile', 'scelta', 'Firma: stile', 'riga', 'stile', ['opzioni' => ['riga' => 'Sopra una riga sottile', 'scura' => 'Fascia scura', 'nessuna' => 'Senza firma']]),
    ];
}

/** Il controllo "colore" a livello di widget. */
function igx_ctl_colore(string $predefinito = 'sequenza'): array {
    return ec('colore', 'scelta', 'Colore delle voci', $predefinito, 'stile', ['opzioni' => igx_opzioni_colore(true)]);
}
/** I controlli "colore" di una voce: palette e colore libero. */
function igx_ctl_colori_voce(string $etichetta = 'Colore (vuoto: come il widget)'): array {
    return [ec('colore', 'scelta', $etichetta, '', 'contenuto', ['opzioni' => igx_opzioni_colore(false, true)]), ec('colore_libero', 'colore', 'Colore libero (vince sulla palette)', '')];
}

/** Le definizioni dei widget ig_* (gruppo Infografiche). */
function igx_definizioni(): array {
    $t = fn(...$a) => igx_controlli_testa(...$a);
    $c = igx_controlli_coda();
    $d = [];
    $d['ig_passi'] = ['etichetta' => 'Passi numerati', 'icona' => '①', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('INFOGRAFICA', 'I passi da fare', 'scuro_sfumato'), [
        ec('stile', 'scelta', 'Disposizione', 'elenco', 'stile', ['opzioni' => ['elenco' => 'Pallino e testo', 'separatori' => 'Pallino e testo con righe fra le voci', 'box' => 'Pallino e card bianca', 'card' => 'Card bianca con bordo a sinistra', 'badge' => 'Card bianca con numero in quadratino', 'linea' => 'Pallino con linea verticale e card', 'pieno' => 'Blocchi pieni colorati', 'righe' => 'Righe attaccate con colonna del numero']]),
        ec('forma', 'scelta', 'Forma del numero', 'cerchio', 'stile', ['opzioni' => ['cerchio' => 'Cerchio', 'quadrato' => 'Quadrato', 'nessuno' => 'Senza sfondo']]),
        igx_ctl_colore(),
        ec('numero_grande', 'interruttore', 'Nei blocchi pieni: numero a due cifre semitrasparente', false, 'stile'),
        ec('voci', 'ripetitore', 'Passi', [['titolo' => 'Primo passo', 'testo' => 'Cosa fare.', 'nota' => '', 'colore' => ''], ['titolo' => 'Secondo passo', 'testo' => 'Cosa fare dopo.', 'nota' => '', 'colore' => '']], 'contenuto', ['campi' => [
            ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', ''), ec('nota', 'testolungo', 'Nota in riquadro sotto il testo (facoltativa)', ''), ec('segno', 'testo', 'Al posto del numero: simbolo o emoji (facoltativo)', ''), ...igx_ctl_colori_voce(),
        ], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_timeline'] = ['etichetta' => 'Linea del tempo', 'icona' => '⋮', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('PERCORSO', 'Le fasi del percorso', 'ciano'), [
        ec('numerata', 'interruttore', 'Pallini con il numero', false, 'stile'),
        ec('stile', 'scelta', 'Disposizione', 'semplice', 'stile', ['opzioni' => ['semplice' => 'Titolo e testo accanto alla linea', 'card' => 'Card con bordo colorato accanto alla linea']]),
        igx_ctl_colore('ciano'),
        ec('voci', 'ripetitore', 'Tappe', [['etichetta' => '', 'titolo' => 'Prima tappa', 'testo' => 'Cosa succede.', 'colore' => '']], 'contenuto', ['campi' => [
            ec('etichetta', 'testo', 'Riga piccola sopra il titolo (facoltativa)', ''), ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', ''), ...igx_ctl_colori_voce(),
        ], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_checklist'] = ['etichetta' => 'Lista di controllo', 'icona' => '☑', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('CHECKLIST', 'La checklist', 'verde'), [
        ec('segno', 'scelta', 'Segno', 'spunta', 'stile', ['opzioni' => ['spunta' => '✓ spunta', 'croce' => '✗ croce', 'casella' => '☐ casella da spuntare', 'vuota' => 'Quadratino vuoto con bordo colorato']]),
        ec('forma', 'scelta', 'Come si disegna il segno', 'contorno', 'stile', ['opzioni' => ['contorno' => 'Quadrato con bordo', 'pieno' => 'Quadrato pieno colorato', 'cerchio' => 'Cerchio pieno colorato', 'semplice' => 'Solo il simbolo colorato']]),
        ec('cornice', 'scelta', 'Cornice delle voci', 'nessuna', 'stile', ['opzioni' => ['nessuna' => 'Nessuna', 'box' => 'Card bianca', 'separatori' => 'Riga fra le voci', 'bordo' => 'Card bianca con bordo a sinistra']]),
        ec('disposizione', 'scelta', 'Disposizione', 'elenco', 'stile', ['opzioni' => ['elenco' => 'Una sotto l\'altra', 'griglia2' => 'Griglia a due colonne']]),
        igx_ctl_colore('verde'),
        ec('in_linea', 'interruttore', 'Titolo e testo sulla stessa riga', false, 'stile'),
        ec('voci', 'ripetitore', 'Voci', [['titolo' => 'Prima voce', 'testo' => 'Spiegazione.', 'colore' => '']], 'contenuto', ['campi' => [
            ec('titolo', 'testo', 'Titolo in grassetto (facoltativo)', ''), ec('testo', 'testolungo', 'Testo', ''), ec('segno', 'testo', 'Segno di questa voce, se diverso (simbolo o emoji)', ''), ...igx_ctl_colori_voce(),
        ], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_tabella'] = ['etichetta' => 'Tabella comparativa', 'icona' => '▦', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('CONFRONTO', 'Prima e dopo', 'scuro'), [
        ec('intestazione_criterio', 'testo', 'Intestazione della colonna dei criteri (vuota se non c\'è)', ''),
        ec('intestazione_a', 'testo', 'Intestazione colonna A', '✗ PRIMA'),
        ec('intestazione_b', 'testo', 'Intestazione colonna B', '✓ DOPO'),
        ec('colore_a', 'scelta', 'Colore colonna A', 'rosso_scuro', 'stile', ['opzioni' => igx_opzioni_colore()]),
        ec('colore_b', 'scelta', 'Colore colonna B', 'verde', 'stile', ['opzioni' => igx_opzioni_colore()]),
        ec('colore_a_libero', 'colore', 'Colonna A: colore libero', '', 'stile'),
        ec('colore_b_libero', 'colore', 'Colonna B: colore libero', '', 'stile'),
        ec('intestazioni_stile', 'scelta', 'Stile delle intestazioni', 'pieno', 'stile', ['opzioni' => ['pieno' => 'Sfondo pieno, testo bianco', 'tinta' => 'Sfondo chiaro, testo colorato']]),
        ec('criterio_stile', 'scelta', 'Colonna dei criteri', 'chiaro', 'stile', ['opzioni' => ['chiaro' => 'Cella grigia, testo in grassetto', 'scuro' => 'Cella scura, testo oro', 'in_cella' => 'Etichetta piccola sopra ogni coppia di celle']]),
        ec('testo_colorato', 'interruttore', 'Testo delle celle del colore della colonna', false, 'stile'),
        ec('b_grassetto', 'interruttore', 'Colonna B in grassetto', false, 'stile'),
        ec('righe', 'ripetitore', 'Righe', [['criterio' => '', 'a' => 'Come si faceva prima', 'b' => 'Come si fa dopo']], 'contenuto', ['campi' => [
            ec('criterio', 'testo', 'Criterio (prima colonna, facoltativo)', ''), ec('a', 'testolungo', 'Colonna A', ''), ec('b', 'testolungo', 'Colonna B', ''),
        ], 'titolo_voce' => 'a']),
    ], $c)];
    $d['ig_liste'] = ['etichetta' => 'Liste affiancate', 'icona' => '⫼', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('', 'Cosa fare e cosa evitare', 'scuro'), [
        ec('stile', 'scelta', 'Etichetta delle colonne', 'pill', 'stile', ['opzioni' => ['pill' => 'Pillola colorata chiara', 'etichetta' => 'Maiuscolo colorato senza sfondo', 'testata' => 'Card bianca con testata piena', 'card' => 'Card con bordo colorato']]),
        ec('voci_stile', 'scelta', 'Voci', 'bordo', 'stile', ['opzioni' => ['bordo' => 'Riga con bordo a sinistra', 'segno' => 'Simbolo della colonna davanti a ogni voce', 'box' => 'Card bianca con bordo a sinistra', 'punti' => 'Elenco puntato']]),
        ec('colonne', 'ripetitore', 'Colonne', [
            ['titolo' => '✓ COSA FARE', 'colore' => 'verde_scuro', 'segno' => '', 'voci' => [['titolo' => '', 'testo' => 'Prima cosa da fare']]],
            ['titolo' => '✗ COSA EVITARE', 'colore' => 'rosso_scuro', 'segno' => '', 'voci' => [['titolo' => '', 'testo' => 'Prima cosa da evitare']]],
        ], 'contenuto', ['campi' => [
            ec('titolo', 'testo', 'Etichetta della colonna', ''), ec('colore', 'scelta', 'Colore', 'verde_scuro', 'contenuto', ['opzioni' => igx_opzioni_colore()]), ec('colore_libero', 'colore', 'Colore libero', ''), ec('segno', 'testo', 'Simbolo davanti a ogni voce (solo con "Simbolo della colonna")', ''),
            ec('voci', 'ripetitore', 'Voci della colonna', [], 'contenuto', ['campi' => [ec('titolo', 'testo', 'Titolo in grassetto (facoltativo)', ''), ec('testo', 'testolungo', 'Testo', '')], 'titolo_voce' => 'testo']),
        ], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_numeri'] = ['etichetta' => 'Numeri grandi', 'icona' => '№', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('I NUMERI', 'I numeri che contano', 'scuro'), [
        ec('colonne', 'scelta', 'Colonne', '2', 'stile', ['opzioni' => ['2' => 'Due', '3' => 'Tre']]),
        ec('stile', 'scelta', 'Stile delle card', 'box', 'stile', ['opzioni' => ['box' => 'Card bianca', 'bordo_sopra' => 'Card con bordo superiore colorato', 'bordo' => 'Card con bordo colorato']]),
        igx_ctl_colore('rosso'),
        ec('ultimo_largo', 'interruttore', 'Ultima card a tutta larghezza se le voci sono dispari', false, 'stile'),
        ec('voci', 'ripetitore', 'Numeri', [['valore' => '55+', 'etichetta' => '', 'testo' => 'ore a settimana', 'colore' => '']], 'contenuto', ['campi' => [
            ec('valore', 'testo', 'Numero grande', ''), ec('etichetta', 'testo', 'Riga in grassetto sotto il numero (facoltativa)', ''), ec('testo', 'testolungo', 'Descrizione', ''), ...igx_ctl_colori_voce(),
        ], 'titolo_voce' => 'valore']),
    ], $c)];
    $d['ig_card'] = ['etichetta' => 'Griglia di schede', 'icona' => '▣', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('IN SINTESI', 'I punti chiave', 'scuro'), [
        ec('colonne', 'scelta', 'Colonne', '2', 'stile', ['opzioni' => ['1' => 'Una', '2' => 'Due', '3' => 'Tre']]),
        ec('stile', 'scelta', 'Stile delle schede', 'bianco', 'stile', ['opzioni' => ['bianco' => 'Card bianca', 'pieno' => 'Card piena colorata', 'bordo' => 'Card con bordo colorato', 'bordo_sx' => 'Card con bordo a sinistra', 'bordo_sopra' => 'Card con bordo superiore colorato', 'barra' => 'Barra verticale sottile, senza card']]),
        igx_ctl_colore(),
        ec('numerate', 'interruttore', 'Pallino con il numero', false, 'stile'),
        ec('centrate', 'interruttore', 'Testo centrato', false, 'stile'),
        ec('titolo_colorato', 'interruttore', 'Titolo del colore della scheda', false, 'stile'),
        ec('etichetta_posizione', 'scelta', 'Posizione dell\'etichetta piccola', 'sopra', 'stile', ['opzioni' => ['sopra' => 'Sopra il titolo', 'sotto' => 'Fra titolo e testo']]),
        ec('ultimo_largo', 'interruttore', 'Ultima scheda a tutta larghezza se le voci sono dispari', false, 'stile'),
        ec('voci', 'ripetitore', 'Schede', [['gruppo' => '', 'emoji' => '', 'etichetta' => '', 'titolo' => 'Prima scheda', 'testo' => 'Testo.', 'nota' => '', 'colore' => '']], 'contenuto', ['campi' => [
            ec('gruppo', 'testo', 'Gruppo (riga maiuscola sopra la griglia quando cambia)', ''), ec('emoji', 'testo', 'Emoji o simbolo', ''), ec('etichetta', 'testo', 'Etichetta piccola maiuscola', ''),
            ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', ''), ec('nota', 'testo', 'Riga corsiva in fondo (facoltativa)', ''), ...igx_ctl_colori_voce(),
        ], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_mappa'] = ['etichetta' => 'Mappa mentale', 'icona' => '✣', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('MAPPA CONCETTUALE', 'La mappa', 'scuro'), [
        ec('stile', 'scelta', 'Aspetto', 'chiaro', 'stile', ['opzioni' => ['chiaro' => 'Card chiara', 'scuro' => 'Tutta la card scura, testata centrata']]),
        ec('centro', 'testolungo', 'Nodo centrale', 'Il tema al centro'),
        ec('centro_forma', 'scelta', 'Forma del nodo centrale', 'pill', 'stile', ['opzioni' => ['pill' => 'Pillola scura', 'cerchio' => 'Cerchio viola']]),
        ec('rami_stile', 'scelta', 'Stile dei rami', 'tinta', 'stile', ['opzioni' => ['tinta' => 'Sfondo colorato leggero con bordo a sinistra', 'bordo_sx' => 'Card bianca con bordo a sinistra', 'bordo' => 'Card bianca con bordo colorato e pallino', 'pieno' => 'Card piena colorata']]),
        igx_ctl_colore(),
        ec('colonne', 'scelta', 'Rami per riga', 'auto', 'stile', ['opzioni' => ['auto' => 'Automatico (due per riga)', '2' => 'Due', '3' => 'Tre', '4' => 'Quattro']]),
        ec('rami', 'ripetitore', 'Rami', [['titolo' => 'Primo ramo', 'testo' => 'Prima voce' . "\n" . 'Seconda voce', 'colore' => 'rosso_scuro'], ['titolo' => 'Secondo ramo', 'testo' => 'Prima voce', 'colore' => 'verde_scuro']], 'contenuto', ['campi' => [
            ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo (una riga per voce dell\'elenco)', ''), ...igx_ctl_colori_voce(),
        ], 'titolo_voce' => 'titolo']),
        ec('chiave', 'testo', 'Pillola conclusiva dopo i rami (facoltativa)', ''),
        ec('schede', 'ripetitore', 'Mini schede finali (facoltative)', [], 'contenuto', ['campi' => [ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', '')], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_flusso'] = ['etichetta' => 'Diagramma di flusso', 'icona' => '⇩', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('FLOWCHART', 'Il percorso', 'rosso'), [
        ec('numerati', 'interruttore', 'Pallino con il numero nei nodi non affiancati', false, 'stile'),
        ec('frecce_colore', 'scelta', 'Colore delle frecce', 'precedente', 'stile', ['opzioni' => ['precedente' => 'Del nodo che precede', 'seguente' => 'Del nodo che segue', 'grigio' => 'Grigio chiaro']]),
        ec('larghezza_nodi', 'scelta', 'Larghezza dei nodi', 'piena', 'stile', ['opzioni' => ['piena' => 'Tutta la colonna', 'stretta' => 'Stretti e centrati (circa 480 px)']]),
        ec('nodi', 'ripetitore', 'Nodi, dall\'alto in basso', [
            ['etichetta' => '', 'titolo' => 'Punto di partenza', 'testo' => '', 'nota' => '', 'colore' => 'rosso', 'stile' => 'contorno', 'affianca' => false, 'freccia' => 'nessuna', 'freccia_testo' => ''],
            ['etichetta' => 'SÌ →', 'titolo' => '', 'testo' => 'Cosa succede se sì', 'nota' => '', 'colore' => 'verde', 'stile' => 'contorno', 'affianca' => false, 'freccia' => 'giu', 'freccia_testo' => ''],
            ['etichetta' => 'NO →', 'titolo' => '', 'testo' => 'Cosa succede se no', 'nota' => '', 'colore' => 'oro', 'stile' => 'contorno', 'affianca' => true, 'freccia' => 'nessuna', 'freccia_testo' => ''],
        ], 'contenuto', ['campi' => [
            ec('etichetta', 'testo', 'Riga maiuscola piccola sopra il titolo', ''), ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', ''), ec('nota', 'testolungo', 'Riga finale del nodo (facoltativa)', ''),
            ec('colore', 'scelta', 'Colore', 'rosso', 'contenuto', ['opzioni' => igx_opzioni_colore()]), ec('colore_libero', 'colore', 'Colore libero', ''),
            ec('bordo', 'scelta', 'Bordo di colore diverso (facoltativo)', '', 'contenuto', ['opzioni' => igx_opzioni_colore(false, true)]), ec('bordo_libero', 'colore', 'Bordo: colore libero', ''),
            ec('stile', 'scelta', 'Stile del nodo', 'contorno', 'contenuto', ['opzioni' => ['contorno' => 'Bianco con bordo colorato', 'tinta' => 'Sfondo chiaro colorato con bordo', 'pieno' => 'Fondo colorato, testo bianco', 'scuro' => 'Fondo scuro, titolo oro', 'bianco' => 'Box bianco con bordo grigio']]),
            ec('affianca', 'interruttore', 'Sulla stessa riga del nodo precedente', false),
            ec('freccia', 'scelta', 'Freccia prima di questo nodo', 'giu', 'contenuto', ['opzioni' => ['giu' => '↓', 'nessuna' => 'Nessuna', 'diagonali' => '↙ ↘ sopra due opzioni', 'triangolo' => 'Asta con triangolo']]),
            ec('freccia_testo', 'testo', 'Etichetta accanto alla freccia', ''),
        ], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_fasi'] = ['etichetta' => 'Fasi', 'icona' => '≣', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('LE FASI', 'Le fasi del percorso', 'ciano'), [
        ec('disposizione', 'scelta', 'Disposizione', 'blocchi', 'stile', ['opzioni' => ['blocchi' => 'Blocchi con sfondo tinto', 'imbuto' => 'Imbuto: blocchi tinti sempre più stretti', 'imbuto_pieno' => 'Imbuto: blocchi pieni centrati sempre più stretti', 'righe' => 'Righe con colonna colorata (etichetta e durata)', 'schede' => 'Schede con testata, durata e colonne fare / non fare', 'piene' => 'Blocchi pieni con etichetta, titolo, testo e riquadro Segnale']]),
        ec('bordo_sx', 'interruttore', 'Nei blocchi: bordo colorato a sinistra', false, 'stile'),
        ec('tinta', 'scelta', 'Blocchi e imbuto: andamento della tinta di sfondo', 'calante', 'stile', ['opzioni' => ['calante' => 'Sempre più chiara (originale di 014, 019, 033)', 'crescente' => 'Sempre più intensa (originale di 006)', 'uguale' => 'Uguale per tutte']]),
        igx_ctl_colore(),
        ec('etichetta_fare', 'testo', 'Schede: intestazione colonna sinistra', '✓ Cosa fare'),
        ec('etichetta_non_fare', 'testo', 'Schede: intestazione colonna destra', '✗ Non fare'),
        ec('etichetta_segnale', 'testo', 'Blocchi pieni: titolo del riquadro a destra', 'Segnale'),
        ec('voci', 'ripetitore', 'Fasi', [['etichetta' => 'Fase 1', 'durata' => '', 'titolo' => 'Prima fase', 'testo' => 'Cosa succede.', 'fare' => '', 'non_fare' => '', 'segnale' => '', 'colore' => '']], 'contenuto', ['campi' => [
            ec('etichetta', 'testo', 'Etichetta (Fase 1, Step 1)', ''), ec('durata', 'testo', 'Durata (2–4 settimane)', ''), ec('titolo', 'testo', 'Titolo', ''), ec('testo', 'testolungo', 'Testo', ''),
            ec('fare', 'testolungo', 'Schede: cosa fare', ''), ec('non_fare', 'testolungo', 'Schede: non fare', ''), ec('segnale', 'testo', 'Blocchi pieni: segnale', ''), ...igx_ctl_colori_voce(),
        ], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_barre'] = ['etichetta' => 'Barre di avanzamento', 'icona' => '▭', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('STEP BY STEP', 'I passi, uno alla volta', 'viola'), [
        ec('disposizione', 'scelta', 'Disposizione', 'etichetta_barra', 'stile', ['opzioni' => ['etichetta_barra' => 'Etichetta a sinistra della barra, poi titolo e testo', 'titolo_percentuale' => 'Titolo a sinistra e percentuale a destra, poi barra e testo']]),
        igx_ctl_colore('viola'),
        ec('spessore', 'scelta', 'Spessore delle barre', 'auto', 'stile', ['opzioni' => ['auto' => 'Come l\'originale (sottile con etichetta, spessa con percentuale)', 'sottile' => 'Sottile', 'spessa' => 'Spessa']]),
        ec('voci', 'ripetitore', 'Barre', [['etichetta' => 'Step 1', 'titolo' => 'Primo passo', 'percentuale' => 25, 'mostra_percentuale' => false, 'testo' => 'Cosa fare.', 'colore' => '']], 'contenuto', ['campi' => [
            ec('etichetta', 'testo', 'Etichetta (Step 1)', ''), ec('titolo', 'testo', 'Titolo', ''), ec('percentuale', 'numero', 'Percentuale', 50, 'contenuto', ['min' => 0, 'max' => 100]),
            ec('mostra_percentuale', 'interruttore', 'Scrivi la percentuale accanto', false), ec('testo', 'testolungo', 'Testo', ''), ...igx_ctl_colori_voce(),
        ], 'titolo_voce' => 'titolo']),
    ], $c)];
    $d['ig_prima_dopo'] = ['etichetta' => 'Scenari prima / dopo', 'icona' => '⇄', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('PRIMA / DOPO', 'Prima e dopo', 'scuro'), [
        ec('disposizione', 'scelta', 'Disposizione', 'colonne', 'stile', ['opzioni' => ['colonne' => 'Testata della situazione e due colonne affiancate', 'righe' => 'Due righe una sotto l\'altra', 'testata' => 'Il "prima" è la testata colorata, il "dopo" è il corpo', 'badge' => 'Testata della situazione e righe con etichetta in badge', 'freccia' => 'Prima ➜ dopo su una riga']]),
        ec('etichetta_prima', 'testo', 'Etichetta del "prima"', '✗ PRIMA'),
        ec('etichetta_dopo', 'testo', 'Etichetta del "dopo"', '✓ DOPO'),
        ec('colore_prima', 'scelta', 'Colore del "prima"', 'rosso', 'stile', ['opzioni' => igx_opzioni_colore()]),
        ec('colore_dopo', 'scelta', 'Colore del "dopo"', 'verde', 'stile', ['opzioni' => igx_opzioni_colore()]),
        ec('colore_prima_libero', 'colore', 'Colore libero del "prima"', '', 'stile'),
        ec('colore_dopo_libero', 'colore', 'Colore libero del "dopo"', '', 'stile'),
        ec('testata_stile', 'scelta', 'Testata della situazione', 'chiara', 'stile', ['opzioni' => ['chiara' => 'Grigio chiaro', 'scura' => 'Scura con testo oro', 'colorata' => 'Del colore della voce']]),
        ec('celle_tinte', 'interruttore', 'Colonne: celle con sfondo colorato leggero', false, 'stile'),
        ec('prima_corsivo', 'interruttore', 'Testo "prima" in corsivo', false, 'stile'),
        ec('dopo_corsivo', 'interruttore', 'Testo "dopo" in corsivo', false, 'stile'),
        ec('voci', 'ripetitore', 'Scenari', [['situazione' => 'Scenario 1 · La situazione', 'prima_etichetta' => '', 'prima_titolo' => '', 'prima' => 'Cosa succedeva prima.', 'dopo_etichetta' => '', 'dopo_titolo' => '', 'dopo' => 'Cosa succede dopo.', 'colore' => '']], 'contenuto', ['campi' => [
            ec('situazione', 'testo', 'Situazione (testata della card, facoltativa)', ''), ec('prima_etichetta', 'testo', 'Etichetta "prima" di questa voce (se diversa)', ''), ec('prima_titolo', 'testo', 'Titolo del "prima" (facoltativo)', ''), ec('prima', 'testolungo', 'Testo del "prima"', ''),
            ec('dopo_etichetta', 'testo', 'Etichetta "dopo" di questa voce (se diversa)', ''), ec('dopo_titolo', 'testo', 'Titolo del "dopo" (facoltativo)', ''), ec('dopo', 'testolungo', 'Testo del "dopo"', ''), ...igx_ctl_colori_voce('Colore della testata (disposizione con badge)'),
        ], 'titolo_voce' => 'situazione']),
    ], $c)];
    $d['ig_zone'] = ['etichetta' => 'Zone colorate', 'icona' => '▤', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('3 ZONE', 'In quale zona sei?', 'scuro'), [
        ec('stile', 'scelta', 'Aspetto delle zone', 'testata', 'stile', ['opzioni' => ['riquadro' => 'Riquadro tinto con nome in pillola', 'testata' => 'Riquadro con testata piena e corpo bianco', 'testata_tinta' => 'Testata piena e corpo tinto']]),
        igx_ctl_colore(),
        ec('zone', 'ripetitore', 'Zone', [['nome' => 'ZONA VERDE', 'sottotitolo' => '', 'colore' => 'verde', 'testo' => 'Cosa succede qui.', 'etichetta_voci' => '', 'voci_stile' => 'box', 'griglia' => false, 'voci' => [], 'azione' => '']], 'contenuto', ['campi' => [
            ec('nome', 'testo', 'Nome della zona', ''), ec('sottotitolo', 'testo', 'Riga corsiva accanto al nome (facoltativa)', ''), ec('colore', 'scelta', 'Colore', 'verde', 'contenuto', ['opzioni' => igx_opzioni_colore()]), ec('colore_libero', 'colore', 'Colore libero', ''),
            ec('testo', 'testolungo', 'Paragrafo della zona (facoltativo)', ''), ec('etichetta_voci', 'testo', 'Riga maiuscola sopra le voci (facoltativa)', ''),
            ec('voci_stile', 'scelta', 'Stile delle voci', 'box', 'contenuto', ['opzioni' => ['box' => 'Card bianche', 'punti' => 'Elenco puntato']]), ec('griglia', 'interruttore', 'Voci su due colonne', false),
            ec('voci', 'ripetitore', 'Voci', [], 'contenuto', ['campi' => [ec('titolo', 'testo', 'Titolo (facoltativo)', ''), ec('testo', 'testolungo', 'Testo', '')], 'titolo_voce' => 'titolo']),
            ec('azione', 'testolungo', 'Riga finale (→ Cosa fare: …)', ''),
        ], 'titolo_voce' => 'nome']),
    ], $c)];
    $d['ig_domande'] = ['etichetta' => 'Domande con due risposte', 'icona' => '?', 'gruppo' => 'Infografiche', 'controlli' => array_merge($t('AUTO-VALUTAZIONE', 'Le domande da farti', 'rosso'), [
        ec('voci', 'ripetitore', 'Domande', [['domanda' => 'Domanda 1: la prima domanda', 'a_titolo' => 'SÌ', 'a_testo' => 'Allora…', 'a_colore' => 'verde', 'b_titolo' => 'NO', 'b_testo' => 'Allora…', 'b_colore' => 'oro']], 'contenuto', ['campi' => [
            ec('domanda', 'testo', 'Domanda', ''), ec('a_titolo', 'testo', 'Prima risposta: titolo', ''), ec('a_testo', 'testolungo', 'Prima risposta: testo', ''), ec('a_colore', 'scelta', 'Prima risposta: colore', 'verde', 'contenuto', ['opzioni' => igx_opzioni_colore()]), ec('a_colore_libero', 'colore', 'Prima risposta: colore libero', ''),
            ec('b_titolo', 'testo', 'Seconda risposta: titolo', ''), ec('b_testo', 'testolungo', 'Seconda risposta: testo', ''), ec('b_colore', 'scelta', 'Seconda risposta: colore', 'oro', 'contenuto', ['opzioni' => igx_opzioni_colore()]), ec('b_colore_libero', 'colore', 'Seconda risposta: colore libero', ''),
        ], 'titolo_voce' => 'domanda']),
    ], $c)];
    return $d;
}

// ---- disegno ----

/** Testo di un campo: entità (emoji a 4 byte) decodificate, HTML protetto, **grassetto**, *corsivo*, a capo → <br>. */
function igx_t($s): string {
    $s = trim(html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($s === '') return '';
    $s = h($s);
    $s = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![*\w])\*(?!\s)([^*\n]+?)(?<!\s)\*(?![*\w])/u', '<em>$1</em>', $s);
    return nl2br($s, false);
}
/** Testo lungo: due a capo fanno paragrafi, uno fa <br>. */
function igx_p($s, string $classe = ''): string {
    $s = trim(str_replace("\r", '', (string)$s));
    if ($s === '') return '';
    $cl = $classe !== '' ? ' class="' . $classe . '"' : '';
    return implode('', array_map(fn($p) => "<p$cl>" . igx_t($p) . '</p>', preg_split('/\n\s*\n/', $s)));
}
/** Righe non vuote di un testo lungo. */
function igx_righe($s): array {
    return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string)$s))), fn($r) => $r !== ''));
}
/** Il nome del colore di una voce: il suo, oppure quello del widget (a rotazione se "sequenza"). */
function igx_colore(array $v, $voce, int $i, string $predefinito = 'sequenza'): string {
    $c = (string)(is_array($voce) ? ($voce['colore'] ?? '') : $voce);
    if ($c === '' || !isset(igx_palette()[$c])) $c = (string)($v['colore'] ?? $predefinito);
    if ($c === 'sequenza' || !isset(igx_palette()[$c])) $c = IGX_SEQUENZA[$i % count(IGX_SEQUENZA)];
    return $c;
}
function igx_cl(string $colore): string {
    return ' igx-c-' . (isset(igx_palette()[$colore]) ? $colore : 'scuro');
}
function igx_esa($c): string {
    $c = trim((string)$c);
    return preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) ? $c : '';
}
/** Classe e variabili CSS di una voce colorata: colore libero della voce, poi del widget, poi la palette. */
function igx_col(array $v, $voce, int $i, string $predefinito = 'sequenza'): array {
    $lib = is_array($voce) ? igx_esa($voce['colore_libero'] ?? '') : '';
    if ($lib === '' && (!is_array($voce) || trim((string)($voce['colore'] ?? '')) === '')) $lib = igx_esa($v['colore_libero'] ?? '');
    if ($lib !== '') return [' igx-c-libero', '--c:' . $lib . ';--c2:' . $lib];
    return [igx_cl(igx_colore($v, $voce, $i, $predefinito)), ''];
}
/** Classe e attributo style per un colore fisso (colonne, prima/dopo, chiusura) con eventuale colore libero. */
function igx_col_fisso(string $nome, $libero): array {
    $libero = igx_esa($libero);
    if ($libero !== '') return [' igx-c-libero', ' style="--c:' . $libero . ';--c2:' . $libero . '"'];
    return [igx_cl($nome), ''];
}
/** Apre un elemento colorato: <div class="classi igx-c-x" style="--c:..."> */
function igx_apri(string $classi, array $v, $voce, int $i, string $predefinito = 'sequenza', string $vars = '', string $tag = 'div'): string {
    [$cl, $cv] = igx_col($v, $voce, $i, $predefinito);
    $st = trim($vars . ($vars !== '' && $cv !== '' ? ';' : '') . $cv);
    return "<$tag class=\"" . $classi . $cl . '"' . ($st !== '' ? ' style="' . $st . '"' : '') . '>';
}
/** Il numero della voce (1, 2…) o a due cifre. */
function igx_num(int $i, bool $dueCifre = false): string {
    return $dueCifre ? str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) : (string)($i + 1);
}

/** La cornice: testata, intro, corpo, chiusura, firma. */
function igx_cornice(string $tipo, array $v, string $corpo, string $classi = ''): string {
    $scura = $tipo === 'ig_mappa' && ($v['stile'] ?? '') === 'scuro';
    $vars = [];
    if (igx_esa($v['testata_colore']) !== '') { $vars[] = '--t1:' . igx_esa($v['testata_colore']); $vars[] = '--t2:' . (igx_esa($v['testata_colore2']) ?: igx_esa($v['testata_colore'])); }
    if (igx_esa($v['testata_testo']) !== '') $vars[] = '--tt:' . igx_esa($v['testata_testo']);
    if ($v['corpo'] === 'libero' && igx_esa($v['corpo_colore']) !== '') $vars[] = '--sfondo:' . igx_esa($v['corpo_colore']);
    if (igx_esa($v['bordo_colore']) !== '') $vars[] = '--bordo-c:' . igx_esa($v['bordo_colore']);
    if ((int)$v['bordo_spessore'] !== 1) $vars[] = '--bordo-sp:' . max(0, min(4, (int)$v['bordo_spessore'])) . 'px';
    $out = '<div class="igx igx-' . substr($tipo, 3) . ' igx-testata-' . h($v['testata']) . ' igx-occhiello-' . h($v['occhiello_colore']) . ' igx-corpo-' . h($v['corpo']) . ($scura ? ' igx-scura' : '')
        . (igx_esa($v['testata_colore']) !== '' ? ' igx-testata-libera' : '') . ' igx-angoli-' . h($v['angoli']) . ($v['ombra'] ? ' igx-ombra' : '') . ' igx-spazio-' . h($v['spaziatura']) . ' igx-testo-' . h($v['testo_dimensione']) . ' igx-font-' . h($v['carattere']) . ($v['testata_allineamento'] === 'centro' ? ' igx-testata-centro' : '')
        . ($classi !== '' ? ' ' . $classi : '') . '"' . ($vars ? ' style="' . implode(';', $vars) . '"' : '') . '>';
    $testa = ($v['occhiello'] !== '' && $v['mostra_occhiello'] ? '<p class="igx-occhiello">' . igx_t($v['occhiello']) . '</p>' : '')
        . ($v['titolo'] !== '' ? '<h3 class="igx-titolo">' . igx_t($v['titolo']) . '</h3>' : '')
        . (trim((string)$v['sottotitolo']) !== '' ? '<p class="igx-sottotitolo">' . igx_t($v['sottotitolo']) . '</p>' : '');
    if ($scura) {
        $out .= '<div class="igx-testa">' . $testa . '</div><div class="igx-corpo">' . igx_p($v['intro'], 'igx-intro') . $corpo . igx_chiusura($v) . '</div>' . igx_firma($v);
    } else {
        if ($testa !== '') $out .= '<div class="igx-testa">' . $testa . '</div>';
        $out .= '<div class="igx-corpo">' . igx_p($v['intro'], 'igx-intro') . $corpo . igx_chiusura($v) . ($v['firma_stile'] === 'riga' ? igx_firma($v) : '') . '</div>';
        if ($v['firma_stile'] === 'scura') $out .= igx_firma($v);
    }
    return $out . '</div>';
}
function igx_firma(array $v): string {
    if ($v['firma_stile'] === 'nessuna' || trim((string)$v['firma']) === '') return '';
    return '<p class="igx-firma igx-firma-' . h($v['firma_stile']) . '">' . igx_t($v['firma']) . '</p>';
}
function igx_chiusura(array $v): string {
    if (trim((string)$v['chiusura']) === '' && trim((string)$v['chiusura_titolo']) === '') return '';
    [$cl, $st] = igx_col_fisso($v['chiusura_colore'], $v['chiusura_colore_libero']);
    return '<div class="igx-chiusura igx-chiusura-' . h($v['chiusura_stile']) . $cl . '"' . $st . '>'
        . ($v['chiusura_titolo'] !== '' ? '<p class="igx-chiusura-titolo">' . igx_t($v['chiusura_titolo']) . '</p>' : '') . igx_p($v['chiusura']) . '</div>';
}

/** Il widget ig_* in HTML. */
function igx_disegna(string $tipo, array $v): string {
    switch ($tipo) {
        case 'ig_passi':
            $dueCifre = in_array($v['stile'], ['badge', 'card'], true) || ($v['stile'] === 'pieno' && $v['numero_grande']);
            $out = '<div class="igx-passi igx-passi-' . h($v['stile']) . ' igx-forma-' . h($v['forma']) . ($v['numero_grande'] ? ' igx-numero-grande' : '') . '">';
            foreach (array_values((array)$v['voci']) as $i => $x) {
                $out .= igx_apri('igx-passo', $v, $x, $i) . '<span class="igx-num">' . (trim((string)($x['segno'] ?? '')) !== '' ? igx_t($x['segno']) : igx_num($i, $dueCifre)) . '</span><div class="igx-passo-corpo">'
                    . (trim((string)($x['titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['titolo']) . '</p>' : '') . igx_p($x['testo'] ?? '', 'igx-voce-testo')
                    . (trim((string)($x['nota'] ?? '')) !== '' ? '<div class="igx-nota">' . igx_p($x['nota']) . '</div>' : '') . '</div></div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_timeline':
            $out = '<div class="igx-tl igx-tl-' . h($v['stile']) . ($v['numerata'] ? ' igx-tl-numerata' : '') . '">';
            $voci = array_values((array)$v['voci']);
            foreach ($voci as $i => $x) {
                $out .= igx_apri('igx-tappa' . ($i === count($voci) - 1 ? ' igx-ultima' : ''), $v, $x, $i, 'ciano') . '<div class="igx-tl-asse"><span class="igx-tl-punto">' . ($v['numerata'] ? igx_num($i) : '') . '</span><span class="igx-tl-linea"></span></div><div class="igx-tappa-corpo">'
                    . (trim((string)($x['etichetta'] ?? '')) !== '' ? '<p class="igx-etichetta">' . igx_t($x['etichetta']) . '</p>' : '')
                    . (trim((string)($x['titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['titolo']) . '</p>' : '') . igx_p($x['testo'] ?? '', 'igx-voce-testo') . '</div></div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_checklist':
            $simbolo = ['spunta' => '✓', 'croce' => '✗', 'casella' => '☐', 'vuota' => ''][$v['segno']] ?? '✓';
            $out = '<div class="igx-check igx-check-' . h($v['segno']) . ' igx-forma-' . h($v['forma']) . ' igx-cornice-' . h($v['cornice']) . ' igx-disp-' . h($v['disposizione']) . ($v['in_linea'] ? ' igx-in-linea' : '') . '">';
            foreach (array_values((array)$v['voci']) as $i => $x) {
                $out .= igx_apri('igx-check-voce', $v, $x, $i, 'verde') . '<span class="igx-segno">' . (trim((string)($x['segno'] ?? '')) !== '' ? igx_t($x['segno']) : $simbolo) . '</span><div class="igx-check-corpo">'
                    . (trim((string)($x['titolo'] ?? '')) !== '' ? '<span class="igx-voce-titolo">' . igx_t($x['titolo']) . '</span>' : '')
                    . (trim((string)($x['testo'] ?? '')) !== '' ? ($v['in_linea'] ? '<span class="igx-voce-testo">' . igx_t($x['testo']) . '</span>' : igx_p($x['testo'], 'igx-voce-testo')) : '') . '</div></div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_tabella':
            $righe = array_values((array)$v['righe']);
            $conCriterio = trim((string)$v['intestazione_criterio']) !== '' || array_filter($righe, fn($r) => trim((string)($r['criterio'] ?? '')) !== '');
            $inCella = $v['criterio_stile'] === 'in_cella';
            $tre = $conCriterio && !$inCella;
            [$ca, $sa] = igx_col_fisso($v['colore_a'], $v['colore_a_libero']);
            [$cb, $sb] = igx_col_fisso($v['colore_b'], $v['colore_b_libero']);
            $out = '<div class="igx-tab' . ($tre ? ' igx-tab-3' : ' igx-tab-2') . ' igx-int-' . h($v['intestazioni_stile']) . ' igx-crit-' . h($v['criterio_stile']) . ($v['testo_colorato'] ? ' igx-testo-colorato' : '') . ($v['b_grassetto'] ? ' igx-b-grassetto' : '') . '">';
            $out .= '<div class="igx-tab-riga igx-tab-int">' . ($tre ? '<div class="igx-cella igx-cella-crit">' . igx_t($v['intestazione_criterio']) . '</div>' : '')
                . '<div class="igx-cella igx-cella-a' . $ca . '"' . $sa . '>' . igx_t($v['intestazione_a']) . '</div><div class="igx-cella igx-cella-b' . $cb . '"' . $sb . '>' . igx_t($v['intestazione_b']) . '</div></div>';
            foreach ($righe as $i => $r) {
                $alt = $i % 2 ? ' igx-alt' : '';
                if ($inCella && trim((string)($r['criterio'] ?? '')) !== '') $out .= '<div class="igx-tab-riga igx-tab-crit' . $alt . '"><div class="igx-cella igx-cella-a' . $ca . '"' . $sa . '>' . igx_t($r['criterio']) . '</div><div class="igx-cella igx-cella-b' . $cb . '"' . $sb . '>' . igx_t($r['criterio']) . '</div></div>';
                $out .= '<div class="igx-tab-riga' . $alt . '">' . ($tre ? '<div class="igx-cella igx-cella-crit">' . igx_t($r['criterio'] ?? '') . '</div>' : '')
                    . '<div class="igx-cella igx-cella-a' . $ca . '"' . $sa . '>' . igx_t($r['a'] ?? '') . '</div><div class="igx-cella igx-cella-b' . $cb . '"' . $sb . '>' . igx_t($r['b'] ?? '') . '</div></div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_liste':
            $colonne = array_values((array)$v['colonne']);
            $out = '<div class="igx-liste igx-liste-' . h($v['stile']) . ' igx-voci-' . h($v['voci_stile']) . ' igx-col-' . max(1, min(3, count($colonne))) . '">';
            foreach ($colonne as $i => $col) {
                $out .= igx_apri('igx-lista', $v, $col, $i) . (trim((string)($col['titolo'] ?? '')) !== '' ? '<p class="igx-lista-titolo">' . igx_t($col['titolo']) . '</p>' : '') . '<div class="igx-lista-voci">';
                foreach ((array)($col['voci'] ?? []) as $x) {
                    $out .= '<div class="igx-lista-voce">' . ($v['voci_stile'] === 'segno' && trim((string)($col['segno'] ?? '')) !== '' ? '<span class="igx-segno">' . igx_t($col['segno']) . '</span>' : '') . '<div>'
                        . (trim((string)($x['titolo'] ?? '')) !== '' ? '<span class="igx-voce-titolo">' . igx_t($x['titolo']) . '</span>' : '') . (trim((string)($x['testo'] ?? '')) !== '' ? '<span class="igx-voce-testo">' . igx_t($x['testo']) . '</span>' : '') . '</div></div>';
                }
                $out .= '</div></div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_numeri':
            $voci = array_values((array)$v['voci']);
            $out = '<div class="igx-numeri igx-numeri-' . h($v['stile']) . ' igx-col-' . h($v['colonne']) . '">';
            foreach ($voci as $i => $x) {
                $out .= igx_apri('igx-numero' . ($v['ultimo_largo'] && $i === count($voci) - 1 && count($voci) % (int)$v['colonne'] ? ' igx-largo' : ''), $v, $x, $i, 'rosso') . '<p class="igx-valore">' . igx_t($x['valore'] ?? '') . '</p>'
                    . (trim((string)($x['etichetta'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['etichetta']) . '</p>' : '') . igx_p($x['testo'] ?? '', 'igx-voce-testo') . '</div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_card':
            $voci = array_values((array)$v['voci']);
            $cl = 'igx-card igx-card-' . h($v['stile']) . ' igx-col-' . h($v['colonne']) . ($v['centrate'] ? ' igx-centrate' : '') . ($v['titolo_colorato'] ? ' igx-titolo-colorato' : '') . ' igx-etichetta-' . h($v['etichetta_posizione']);
            $out = ''; $gruppo = null; $aperta = false;
            foreach ($voci as $i => $x) {
                $g = trim((string)($x['gruppo'] ?? ''));
                if ($g !== '' && $g !== $gruppo) {
                    if ($aperta) $out .= '</div>';
                    $out .= igx_apri('igx-gruppo', $v, $x, $i, 'sequenza', '', 'p') . igx_t($g) . '</p><div class="' . $cl . '">'; $aperta = true; $gruppo = $g;
                } elseif (!$aperta) { $out .= '<div class="' . $cl . '">'; $aperta = true; }
                $etichetta = trim((string)($x['etichetta'] ?? '')) !== '' ? '<p class="igx-etichetta">' . igx_t($x['etichetta']) . '</p>' : '';
                $out .= igx_apri('igx-scheda' . ($v['ultimo_largo'] && $i === count($voci) - 1 && count($voci) % (int)$v['colonne'] ? ' igx-largo' : ''), $v, $x, $i)
                    . (trim((string)($x['emoji'] ?? '')) !== '' ? '<span class="igx-emoji">' . igx_t($x['emoji']) . '</span>' : '')
                    . ($v['numerate'] || ($v['etichetta_posizione'] === 'sopra' && $etichetta !== '') ? '<div class="igx-scheda-testa">' . ($v['numerate'] ? '<span class="igx-num">' . igx_num($i) . '</span>' : '') . ($v['etichetta_posizione'] === 'sopra' ? $etichetta : '') . '</div>' : '')
                    . (trim((string)($x['titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['titolo']) . '</p>' : '') . ($v['etichetta_posizione'] === 'sotto' ? $etichetta : '')
                    . igx_p($x['testo'] ?? '', 'igx-voce-testo') . (trim((string)($x['nota'] ?? '')) !== '' ? '<p class="igx-voce-nota">' . igx_t($x['nota']) . '</p>' : '') . '</div>';
            }
            if ($aperta) $out .= '</div>';
            return igx_cornice($tipo, $v, $out);
        case 'ig_mappa':
            $out = (trim((string)$v['centro']) !== '' ? '<div class="igx-centro-riga"><span class="igx-centro igx-centro-' . h($v['centro_forma']) . '">' . igx_t($v['centro']) . '</span></div>' : '');
            $out .= '<div class="igx-rami igx-rami-' . h($v['rami_stile']) . (in_array($v['colonne'], ['2', '3', '4'], true) ? ' igx-rami-col-' . $v['colonne'] : '') . '">';
            foreach (array_values((array)$v['rami']) as $i => $r) {
                $righe = igx_righe($r['testo'] ?? '');
                $out .= igx_apri('igx-ramo', $v, $r, $i) . '<span class="igx-ramo-punto"></span>' . (trim((string)($r['titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($r['titolo']) . '</p>' : '')
                    . implode('', array_map(fn($x) => '<p class="igx-voce-testo">' . igx_t($x) . '</p>', $righe)) . '</div>';
            }
            $out .= '</div>' . (trim((string)$v['chiave']) !== '' ? '<div class="igx-centro-riga"><span class="igx-chiave">' . igx_t($v['chiave']) . '</span></div>' : '');
            $schede = array_values((array)$v['schede']);
            if ($schede) $out .= '<div class="igx-mini igx-col-' . max(1, min(3, count($schede))) . '">' . implode('', array_map(fn($s) => '<div class="igx-mini-scheda">' . (trim((string)($s['titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($s['titolo']) . '</p>' : '') . igx_p($s['testo'] ?? '', 'igx-voce-testo') . '</div>', $schede)) . '</div>';
            return igx_cornice($tipo, $v, $out);
        case 'ig_flusso':
            $nodi = array_values((array)$v['nodi']);
            $out = '<div class="igx-flusso' . ($v['numerati'] ? ' igx-flusso-numerato' : '') . ($v['larghezza_nodi'] === 'stretta' ? ' igx-flusso-stretta' : '') . '">';
            $riga = []; $n = 0; $prec = 'rosso';
            $chiudi = function () use (&$riga, &$out) { if ($riga) { $out .= '<div class="igx-flusso-riga igx-col-' . min(3, count($riga)) . '">' . implode('', $riga) . '</div>'; $riga = []; } };
            foreach ($nodi as $i => $x) {
                $colore = igx_colore($v, $x, $i, 'rosso');
                if (!($x['affianca'] ?? false) || !$riga) {
                    $chiudi();
                    $f = (string)($x['freccia'] ?? 'giu');
                    $cf = $v['frecce_colore'] === 'seguente' ? $colore : $prec;
                    if ($i > 0 && $f !== 'nessuna') $out .= '<div class="igx-freccia igx-freccia-' . h($f) . ($v['frecce_colore'] === 'grigio' ? ' igx-freccia-grigia' : igx_cl($cf)) . '">' . ($f === 'triangolo' ? '<span class="igx-asta"></span><span class="igx-punta"></span>' : ($f === 'diagonali' ? '<span>↙</span><span>↘</span>' : '<span>↓</span>')) . (trim((string)($x['freccia_testo'] ?? '')) !== '' ? '<em class="igx-freccia-testo">' . igx_t($x['freccia_testo']) . '</em>' : '') . '</div>';
                }
                $numero = $v['numerati'] && !($x['affianca'] ?? false) ? '<span class="igx-num">' . igx_num($n++) . '</span>' : '';
                $bordo = igx_esa($x['bordo_libero'] ?? '') ?: (igx_palette()[(string)($x['bordo'] ?? '')] ?? '');
                $riga[] = igx_apri('igx-nodo igx-nodo-' . h($x['stile'] ?? 'contorno') . ($bordo !== '' ? ' igx-nodo-bordato' : ''), $v, $x, $i, 'rosso', $bordo !== '' ? '--b:' . $bordo : '') . $numero . '<div class="igx-nodo-corpo">'
                    . (trim((string)($x['etichetta'] ?? '')) !== '' ? '<p class="igx-etichetta">' . igx_t($x['etichetta']) . '</p>' : '')
                    . (trim((string)($x['titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['titolo']) . '</p>' : '') . igx_p($x['testo'] ?? '', 'igx-voce-testo')
                    . (trim((string)($x['nota'] ?? '')) !== '' ? '<div class="igx-nota">' . igx_p($x['nota']) . '</div>' : '') . '</div></div>';
                $prec = $colore;
            }
            $chiudi();
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_fasi':
            $voci = array_values((array)$v['voci']);
            $disp = $v['disposizione'];
            $out = '<div class="igx-fasi igx-fasi-' . h($disp) . ($v['bordo_sx'] ? ' igx-bordo-sx' : '') . ' igx-tinta-' . h($v['tinta']) . '">';
            foreach ($voci as $i => $x) {
                [$fcl, $fv] = igx_col($v, $x, $i);
                $stile = ' style="--i:' . min($i, 7) . ($fv !== '' ? ';' . $fv : '') . '"';
                $et = trim((string)($x['etichetta'] ?? '')) !== '' ? '<p class="igx-etichetta">' . igx_t($x['etichetta']) . '</p>' : '';
                $durata = trim((string)($x['durata'] ?? '')) !== '' ? '<span class="igx-durata">' . igx_t($x['durata']) . '</span>' : '';
                $titolo = trim((string)($x['titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['titolo']) . '</p>' : '';
                $testo = igx_p($x['testo'] ?? '', 'igx-voce-testo');
                $cl = 'igx-fase' . $fcl;
                if ($disp === 'righe') {
                    $out .= '<div class="' . $cl . '"' . $stile . '><div class="igx-fase-colonna">' . $et . $durata . '</div><div class="igx-fase-corpo">' . $titolo . $testo . '</div></div>';
                } elseif ($disp === 'schede') {
                    if ($i > 0) $out .= '<div class="igx-freccia igx-freccia-giu' . igx_cl(igx_colore($v, $x, $i)) . '"><span>↓</span></div>';
                    $out .= '<div class="' . $cl . '"' . $stile . '><div class="igx-fase-testa">' . $et . $durata . '</div><div class="igx-fase-corpo">' . $titolo . $testo
                        . ((trim((string)($x['fare'] ?? '')) !== '' || trim((string)($x['non_fare'] ?? '')) !== '') ? '<div class="igx-fare-non"><div><p class="igx-etichetta">' . igx_t($v['etichetta_fare']) . '</p>' . igx_p($x['fare'] ?? '', 'igx-voce-testo') . '</div><div><p class="igx-etichetta igx-etichetta-grigia">' . igx_t($v['etichetta_non_fare']) . '</p>' . igx_p($x['non_fare'] ?? '', 'igx-voce-testo') . '</div></div>' : '') . '</div></div>';
                } elseif ($disp === 'piene') {
                    $out .= '<div class="' . $cl . '"' . $stile . '><div class="igx-fase-corpo">' . $et . $titolo . $testo . '</div>' . (trim((string)($x['segnale'] ?? '')) !== '' ? '<div class="igx-segnale"><p class="igx-etichetta">' . igx_t($v['etichetta_segnale']) . '</p><p>' . igx_t($x['segnale']) . '</p></div>' : '') . '</div>';
                } else {
                    $out .= '<div class="' . $cl . '"' . $stile . '>' . $et . $durata . $titolo . $testo . '</div>';
                }
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_barre':
            $out = '<div class="igx-barre igx-barre-' . h($v['disposizione']) . ' igx-barre-' . h($v['spessore'] === 'auto' ? ($v['disposizione'] === 'etichetta_barra' ? 'sottile' : 'spessa') : $v['spessore']) . '">';
            foreach (array_values((array)$v['voci']) as $i => $x) {
                $pct = max(0, min(100, (int)($x['percentuale'] ?? 0)));
                $barra = '<div class="igx-barra"><div class="igx-barra-pieno" style="--w:' . $pct . '%"></div></div>';
                $pctTxt = !empty($x['mostra_percentuale']) ? '<span class="igx-pct">' . $pct . '%</span>' : '';
                $titolo = trim((string)($x['titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['titolo']) . '</p>' : '';
                $out .= igx_apri('igx-barra-voce', $v, $x, $i, 'viola');
                if ($v['disposizione'] === 'titolo_percentuale') $out .= '<div class="igx-barra-riga">' . $titolo . $pctTxt . '</div>' . $barra;
                else $out .= '<div class="igx-barra-riga">' . (trim((string)($x['etichetta'] ?? '')) !== '' ? '<span class="igx-barra-etichetta">' . igx_t($x['etichetta']) . '</span>' : '') . $barra . $pctTxt . '</div>' . $titolo;
                $out .= igx_p($x['testo'] ?? '', 'igx-voce-testo') . '</div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_prima_dopo':
            $disp = $v['disposizione'];
            $out = '<div class="igx-pd igx-pd-' . h($disp) . ' igx-testata-sit-' . h($v['testata_stile']) . ($v['celle_tinte'] ? ' igx-celle-tinte' : '') . ($v['prima_corsivo'] ? ' igx-prima-corsivo' : '') . ($v['dopo_corsivo'] ? ' igx-dopo-corsivo' : '') . '">';
            [$cp, $sp] = igx_col_fisso($v['colore_prima'], $v['colore_prima_libero']);
            [$cd, $sd] = igx_col_fisso($v['colore_dopo'], $v['colore_dopo_libero']);
            foreach (array_values((array)$v['voci']) as $i => $x) {
                $ep = trim((string)($x['prima_etichetta'] ?? '')) !== '' ? $x['prima_etichetta'] : $v['etichetta_prima'];
                $ed = trim((string)($x['dopo_etichetta'] ?? '')) !== '' ? $x['dopo_etichetta'] : $v['etichetta_dopo'];
                $pt = trim((string)($x['prima_titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['prima_titolo']) . '</p>' : '';
                $dt = trim((string)($x['dopo_titolo'] ?? '')) !== '' ? '<p class="igx-voce-titolo">' . igx_t($x['dopo_titolo']) . '</p>' : '';
                $sit = trim((string)($x['situazione'] ?? '')) !== '' ? igx_apri('igx-pd-sit', $v, $x, $i) . igx_t($x['situazione']) . '</div>' : '';
                $out .= igx_apri('igx-pd-voce', $v, $x, $i);
                if ($disp === 'testata' || $disp === 'badge') {
                    $out .= ($disp === 'badge' ? $sit : '') . '<div class="igx-pd-prima' . $cp . '"' . $sp . '>' . (trim((string)$ep) !== '' ? '<span class="igx-pd-etichetta">' . igx_t($ep) . '</span> ' : '') . $pt . '<span class="igx-pd-testo">' . igx_t($x['prima'] ?? '') . '</span></div>'
                        . '<div class="igx-pd-dopo' . $cd . '"' . $sd . '>' . (trim((string)$ed) !== '' ? '<span class="igx-pd-etichetta">' . igx_t($ed) . '</span> ' : '') . $dt . '<span class="igx-pd-testo">' . igx_t($x['dopo'] ?? '') . '</span></div>';
                } elseif ($disp === 'freccia') {
                    $out .= '<div class="igx-pd-prima' . $cp . '"><p class="igx-pd-etichetta">' . igx_t($ep) . '</p>' . $pt . igx_p($x['prima'] ?? '', 'igx-pd-testo') . '</div><div class="igx-pd-freccia"><span>➜</span></div>'
                        . '<div class="igx-pd-dopo' . $cd . '"><p class="igx-pd-etichetta">' . igx_t($ed) . '</p>' . $dt . igx_p($x['dopo'] ?? '', 'igx-pd-testo') . '</div>';
                } else {
                    $out .= $sit . '<div class="igx-pd-coppia"><div class="igx-pd-prima' . $cp . '"><p class="igx-pd-etichetta">' . igx_t($ep) . '</p>' . $pt . igx_p($x['prima'] ?? '', 'igx-pd-testo') . '</div>'
                        . '<div class="igx-pd-dopo' . $cd . '"><p class="igx-pd-etichetta">' . igx_t($ed) . '</p>' . $dt . igx_p($x['dopo'] ?? '', 'igx-pd-testo') . '</div></div>';
                }
                $out .= '</div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_zone':
            $out = '<div class="igx-zone igx-zone-' . h($v['stile']) . '">';
            foreach (array_values((array)$v['zone']) as $i => $z) {
                $voci = array_values((array)($z['voci'] ?? []));
                $out .= igx_apri('igx-zona', $v, $z, $i) . '<div class="igx-zona-testa"><span class="igx-zona-nome">' . igx_t($z['nome'] ?? '') . '</span>' . (trim((string)($z['sottotitolo'] ?? '')) !== '' ? '<em class="igx-zona-sotto">' . igx_t($z['sottotitolo']) . '</em>' : '') . '</div><div class="igx-zona-corpo">'
                    . igx_p($z['testo'] ?? '', 'igx-voce-testo') . (trim((string)($z['etichetta_voci'] ?? '')) !== '' ? '<p class="igx-etichetta">' . igx_t($z['etichetta_voci']) . '</p>' : '');
                if ($voci) {
                    $out .= (($z['voci_stile'] ?? 'box') === 'punti' ? '<ul class="igx-zona-punti">' : '<div class="igx-zona-voci' . (!empty($z['griglia']) ? ' igx-col-2' : '') . '">');
                    foreach ($voci as $x) {
                        $dentro = (trim((string)($x['titolo'] ?? '')) !== '' ? '<span class="igx-voce-titolo">' . igx_t($x['titolo']) . '</span>' . (preg_match('/^[,;:.]/u', trim((string)($x['testo'] ?? ''))) ? '' : ' ') : '') . (trim((string)($x['testo'] ?? '')) !== '' ? '<span class="igx-voce-testo">' . igx_t($x['testo']) . '</span>' : '');
                        $out .= ($z['voci_stile'] ?? 'box') === 'punti' ? '<li>' . $dentro . '</li>' : '<div class="igx-zona-voce">' . $dentro . '</div>';
                    }
                    $out .= ($z['voci_stile'] ?? 'box') === 'punti' ? '</ul>' : '</div>';
                }
                $out .= (trim((string)($z['azione'] ?? '')) !== '' ? '<p class="igx-zona-azione">' . igx_t($z['azione']) . '</p>' : '') . '</div></div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
        case 'ig_domande':
            $out = '<div class="igx-domande">';
            foreach (array_values((array)$v['voci']) as $x) {
                $out .= '<div class="igx-domanda"><p class="igx-domanda-testo">' . igx_t($x['domanda'] ?? '') . '</p><div class="igx-risposte">'
                    . igx_apri('igx-risposta', $v, ['colore' => (string)($x['a_colore'] ?? 'verde'), 'colore_libero' => (string)($x['a_colore_libero'] ?? '')], 0, 'verde') . '<p class="igx-voce-titolo">' . igx_t($x['a_titolo'] ?? '') . '</p>' . igx_p($x['a_testo'] ?? '', 'igx-voce-testo') . '</div>'
                    . igx_apri('igx-risposta', $v, ['colore' => (string)($x['b_colore'] ?? 'oro'), 'colore_libero' => (string)($x['b_colore_libero'] ?? '')], 0, 'oro') . '<p class="igx-voce-titolo">' . igx_t($x['b_titolo'] ?? '') . '</p>' . igx_p($x['b_testo'] ?? '', 'igx-voce-testo') . '</div></div></div>';
            }
            return igx_cornice($tipo, $v, $out . '</div>');
    }
    return '';
}

function editor_video_incorporato(string $url): ?string {
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0';
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) return 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1';
    return null;
}
