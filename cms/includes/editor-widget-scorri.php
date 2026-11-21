<?php
/**
 * 2.25.0: il widget "Scorri di lato" (richiesta di Danilo: "come sul sito Apple: quando chi scorre arriva sul widget, il sito
 * scorre di lato fino all'ultima pagina, poi ricomincia a scorrere in verticale"). Cinque disposizioni, scelte tutte e cinque
 * da Danilo fra le proposte del prototipo: pellicola, carte, titolo fermo, linea del tempo, profondità.
 *
 * Un solo HTML per tutte le disposizioni: la radice .sdl con le pagine una sotto l'altra, leggibili anche senza JavaScript e
 * per chi chiede meno movimento. Lo script sito/assets/scorri.js aggiunge .sdl-vivo: solo allora la scena si ferma
 * (position:sticky) e le pagine scorrono di lato (sito/assets/scorri.css, regole sotto .sdl.sdl-vivo e .sdl-d-<id>.sdl-vivo).
 * Nella tela dell'editor la radice ha .sdl-tela: le pagine stanno in una fila che si scorre a mano, l'effetto si vede sul sito.
 *
 * Le pagine sono campi (non sezioni dell'editor dentro un widget: l'editor ha tre livelli fissi, sezione, colonna, widget, e
 * nessun modo pulito di mettere un albero dentro un widget). Classi pubbliche sdl-*: .scena, .finestra, .barra e .chip sono
 * di sito.css (Apertura) e non si toccano.
 */

const SDL_DISPOSIZIONI = [
    'pellicola' => 'Pellicola: ogni pagina a tutto schermo',
    'carte' => 'Carte: una fila di carte',
    'fermo' => 'Titolo fermo: il testo resta, le immagini scorrono',
    'tempo' => 'Linea del tempo: tappe con la data',
    'profondita' => 'Profondità: sfondo lento, testo che entra',
];
const SDL_MAX_PAGINE = 12;
const SDL_VELOCITA = ['lenta' => 'Lenta (più rotella per ogni pagina)', 'normale' => 'Normale', 'veloce' => 'Veloce'];

/** L'indirizzo di un'immagine del sito, oppure '' (niente immagini esterne: solo percorsi del sito o la cartella delle immagini). */
function sdl_immagine(string $u): string {
    $u = trim($u);
    if ($u === '' || strlen($u) > 500 || str_contains($u, '..') || preg_match('~[\s"\'<>()\\\\]~', $u)) return '';
    if (str_starts_with($u, '/') && !str_starts_with($u, '//')) return $u;
    foreach ([function_exists('cms_uploads_url') ? cms_uploads_url() : '', defined('SITO_URL') ? (string)SITO_URL : ''] as $base) {
        if (preg_match('~^https?://[^/]+~', $base) && str_starts_with($u, rtrim($base, '/') . '/')) return $u;
    }
    return '';
}

/** Un testo corto della pagina: stringa, senza a capo, al massimo $max caratteri. */
function sdl_testo($x, int $max = 200): string {
    return mb_substr(trim(str_replace(["\r", "\n"], ' ', is_scalar($x) ? (string)$x : '')), 0, $max);
}

/** Le pagine buone (al massimo SDL_MAX_PAGINE), con i campi tutti presenti e ripuliti. */
function sdl_pagine($pagine): array {
    $out = [];
    foreach (array_slice(is_array($pagine) ? array_values($pagine) : [], 0, SDL_MAX_PAGINE) as $p) {
        if (!is_array($p)) continue;
        $out[] = [
            'occhiello' => sdl_testo($p['occhiello'] ?? '', 120), 'titolo' => sdl_testo($p['titolo'] ?? '', 200),
            'testo' => mb_substr(trim(str_replace("\r", '', is_scalar($p['testo'] ?? '') ? (string)($p['testo'] ?? '') : '')), 0, 1200),
            'data' => sdl_testo($p['data'] ?? '', 60), 'immagine' => sdl_immagine(is_scalar($p['immagine'] ?? '') ? (string)($p['immagine'] ?? '') : ''),
            'alt' => sdl_testo($p['alt'] ?? '', 200), 'sfondo' => is_scalar($p['sfondo'] ?? '') ? (string)($p['sfondo'] ?? '') : '',
            'pulsante' => sdl_testo($p['pulsante'] ?? '', 60), 'pulsante_link' => editor_link_sicuro(is_scalar($p['pulsante_link'] ?? '') ? (string)($p['pulsante_link'] ?? '') : ''),
            'pulsante2' => sdl_testo($p['pulsante2'] ?? '', 60), 'pulsante2_link' => editor_link_sicuro(is_scalar($p['pulsante2_link'] ?? '') ? (string)($p['pulsante2_link'] ?? '') : ''),
        ];
    }
    return $out;
}

/** I pulsanti di una pagina: il primo viola pieno, il secondo a filo. Un pulsante senza testo non esce; senza link punta a "#". */
function sdl_pulsanti(array $p): string {
    $out = '';
    foreach ([['pulsante', 'viola'], ['pulsante2', 'filo']] as [$k, $stile]) {
        if ($p[$k] === '') continue;
        $out .= '<a class="btn btn-' . $stile . ' sdl-btn" href="' . h($p[$k . '_link'] ?: '#') . '">' . h($p[$k]) . '</a>';
    }
    return $out !== '' ? '<div class="azioni sdl-azioni">' . $out . '</div>' : '';
}

$sdlPred = [
    ['occhiello' => '01 · Scrivi', 'titolo' => 'Il tuo sito, *leggero* come una piuma.', 'testo' => 'Apri il pannello e scrivi. Il resto lo fa plumeCMS.', 'data' => '25 ottobre',
        'immagine' => '', 'alt' => '', 'sfondo' => '', 'pulsante' => 'Scarica plumeCMS', 'pulsante_link' => '/scarica/', 'pulsante2' => '', 'pulsante2_link' => ''],
    ['occhiello' => '02 · Trascina', 'titolo' => 'Sezioni, colonne, *widget*.', 'testo' => 'Prendi un blocco e lo lasci dove vuoi. Lo vedi subito come lo vedrà chi visita.', 'data' => '26 ottobre',
        'immagine' => '', 'alt' => '', 'sfondo' => '', 'pulsante' => '', 'pulsante_link' => '', 'pulsante2' => '', 'pulsante2_link' => ''],
    ['occhiello' => '03 · Vesti', 'titolo' => 'Undici temi, *un clic*.', 'testo' => 'Cambi vestito al sito e i tuoi testi restano dove sono.', 'data' => '30 ottobre',
        'immagine' => '', 'alt' => '', 'sfondo' => '', 'pulsante' => '', 'pulsante_link' => '', 'pulsante2' => '', 'pulsante2_link' => ''],
    ['occhiello' => '04 · Pubblica', 'titolo' => 'Premi Pubblica. *Fatto.*', 'testo' => 'Se sbagli, torni indietro: ogni modifica resta nelle versioni.', 'data' => '3 novembre',
        'immagine' => '', 'alt' => '', 'sfondo' => '', 'pulsante' => '', 'pulsante_link' => '', 'pulsante2' => 'Guarda cosa sa fare', 'pulsante2_link' => '/funzionalita/'],
];

editor_registra_widget('scorri_lato', [
    'etichetta' => 'Scorri di lato',
    'icona' => function_exists('icona_svg') ? (icona_svg('freccia-destra') ?: '⇆') : '⇆',
    'gruppo' => 'Contenuto',
    'controlli' => [
        ec('disposizione', 'scelta', 'Disposizione', 'pellicola', 'contenuto', ['opzioni' => SDL_DISPOSIZIONI, 'accetta' => '/^[a-z]{3,20}$/',
            'aiuto' => 'Cinque modi di far scorrere le stesse pagine. Cambiando disposizione i testi restano. Nella tela le pagine stanno in fila; l\'effetto si vede sul sito.']),
        ec('occhiello', 'testo', 'Occhiello della sezione', 'Cosa c\'è dentro', 'contenuto', ['condizione' => ['disposizione' => ['carte', 'tempo']],
            'aiuto' => 'Resta fermo in alto mentre le pagine scorrono.']),
        ec('titolo', 'testo', 'Titolo della sezione', 'Tutto quello che serve, *niente di più*.', 'contenuto', ['condizione' => ['disposizione' => ['carte', 'tempo']],
            'aiuto' => 'Tra *asterischi* la parte con il gradiente. Vuoto: niente titolo fermo.']),
        ec('pagine', 'ripetitore', 'Pagine', $sdlPred, 'contenuto', ['max_voci' => SDL_MAX_PAGINE, 'titolo_voce' => 'titolo',
            'aiuto' => 'Da 2 a ' . SDL_MAX_PAGINE . ' pagine. Fai clic su una pagina per aprirla; per cambiare l\'ordine trascina ⋮⋮ oppure usa le frecce ↑ ↓.',
            'campi' => [
                ec('occhiello', 'testo', 'Occhiello', ''),
                ec('titolo', 'testo', 'Titolo', '', 'contenuto', ['aiuto' => 'Tra *asterischi* la parte con il gradiente.']),
                ec('testo', 'testolungo', 'Testo', ''),
                ec('data', 'testo', 'Data della tappa', '', 'contenuto', ['condizione' => ['disposizione' => 'tempo'], 'aiuto' => 'Per esempio "Marzo 2024" o "1998".']),
                ec('immagine', 'immagine', 'Immagine', ''),
                ec('alt', 'testo', 'Testo alternativo dell\'immagine', '', 'contenuto', ['aiuto' => 'Cosa si vede nella foto, per chi non vede e per Google.']),
                ec('sfondo', 'colore', 'Colore di sfondo della pagina', '', 'contenuto', ['condizione' => ['disposizione' => ['pellicola', 'carte', 'profondita']],
                    'aiuto' => 'Facoltativo. Vuoto: il fondo del tema.']),
                ec('pulsante', 'testo', 'Primo pulsante (viola)', '', 'contenuto', ['aiuto' => 'Vuoto: niente pulsante.']),
                ec('pulsante_link', 'testo', 'Link del primo pulsante', ''),
                ec('pulsante2', 'testo', 'Secondo pulsante (a filo)', ''),
                ec('pulsante2_link', 'testo', 'Link del secondo pulsante', ''),
            ]]),
        ec('velocita', 'scelta', 'Velocità dello scorrimento', 'normale', 'contenuto', ['opzioni' => SDL_VELOCITA, 'sezione' => 'Scorrimento',
            'aiuto' => 'Quanto bisogna scorrere per passare da una pagina all\'altra.']),
        ec('lineette', 'interruttore', 'Mostra le lineette di avanzamento', true, 'contenuto', ['sezione' => 'Scorrimento']),
        ec('tutto_schermo', 'interruttore', 'A tutta larghezza dello schermo', true, 'contenuto', ['sezione' => 'Scorrimento',
            'aiuto' => 'Acceso, la scena prende tutta la larghezza della finestra anche se la sezione è più stretta. Spento, resta larga come la colonna.']),
        ec('foto', 'scelta', 'Trattamento delle immagini', 'velo', 'stile', ['sezione' => 'Immagini', 'opzioni' => ['velo' => 'Bianco e nero con il velo ciano e viola', 'bn' => 'Solo bianco e nero', 'naturale' => 'Naturale']]),
        ...es_testo('occhiello', "dell'occhiello", ' .occhiello', 'Occhiello'),
        ...es_testo('titolo', 'dei titoli delle pagine', ' .sdl-titolo', 'Titoli'),
        es_col('colore_evidenza', 'Colore della parte tra asterischi (vuoto = gradiente)', ' .sdl-titolo em', 'Titoli', 'background'),
        ...es_testo('titolo_sezione', 'del titolo della sezione', ' .sdl-titolo-sez', 'Titolo della sezione'),
        ...es_testo('testo', 'del testo', ' .sdl-lead', 'Testo'),
    ],
], function (array $v, array $ctx): string {
    $d = (string)($v['disposizione'] ?? 'pellicola');
    if (!isset(SDL_DISPOSIZIONI[$d])) $d = 'pellicola'; // una disposizione che questo sito non conosce: la prima
    $pagine = sdl_pagine($v['pagine'] ?? []);
    $tela = !empty($ctx['anteprima']);
    if (!$pagine) return $tela ? '<div class="ed-vuoto">Aggiungi la prima pagina: scheda Contenuto, Pagine, + Aggiungi.</div>' : '';
    $n = count($pagine);
    $foto = in_array($v['foto'] ?? '', ['velo', 'bn', 'naturale'], true) ? $v['foto'] : 'velo';
    $vel = ['lenta' => '1.6', 'normale' => '1', 'veloce' => '0.6'][(string)($v['velocita'] ?? 'normale')] ?? '1';
    $testa = '';
    if (in_array($d, ['carte', 'tempo'], true) && (trim((string)($v['occhiello'] ?? '')) !== '' || trim((string)($v['titolo'] ?? '')) !== '')) {
        $testa = '<div class="sdl-testa">' . (trim((string)$v['occhiello']) !== '' ? '<span class="occhiello">' . h(sdl_testo($v['occhiello'], 120)) . '</span>' : '')
            . (trim((string)$v['titolo']) !== '' ? '<h2 class="sdl-titolo-sez">' . editor_evidenza(sdl_testo($v['titolo'], 200)) . '</h2>' : '') . '</div>';
    }
    $hp = $testa !== '' && str_contains($testa, '<h2') ? 'h3' : 'h2';
    $css = &$ctx['css'];
    $out = '';
    foreach ($pagine as $i => $p) {
        $col = $p['sfondo'] !== '' && function_exists('editor_colore_css') ? editor_colore_css(editor_pulisci_colore($p['sfondo'])) : null;
        if ($col && is_array($css)) $css['d'][$ctx['sel'] . ' .sdl-p' . ($i + 1)]['--sdl-sfondo'] = $col;
        $img = $p['immagine'] !== ''
            ? '<figure class="sdl-finestra"><img src="' . h($p['immagine']) . '" alt="' . h($p['alt']) . '" loading="lazy" decoding="async"></figure>'
            : '<div class="sdl-finestra sdl-senza" aria-hidden="true"></div>';
        $out .= '<div class="sdl-pagina sdl-p' . ($i + 1) . ($p['immagine'] !== '' ? ' sdl-con-foto' : '') . ($i === 0 ? ' sdl-on' : '') . '" style="--i:' . $i . '" role="group" aria-roledescription="pagina" aria-label="' . ($i + 1) . ' di ' . $n . '">'
            . ($d === 'tempo' ? '<span class="sdl-data">' . h($p['data']) . '</span><span class="sdl-punto" aria-hidden="true"></span>' : '')
            . '<div class="sdl-testo">'
            . ($p['occhiello'] !== '' ? '<span class="occhiello">' . h($p['occhiello']) . '</span>' : '')
            . ($p['titolo'] !== '' ? "<$hp class=\"sdl-titolo\">" . editor_evidenza($p['titolo']) . "</$hp>" : '')
            . ($p['testo'] !== '' ? '<div class="sdl-lead">' . editor_paragrafi($p['testo']) . '</div>' : '')
            . sdl_pulsanti($p) . '</div>' . $img . '</div>';
    }
    $lineette = !empty($v['lineette']) && $n > 1 ? '<div class="sdl-lineette" aria-hidden="true">' . str_repeat('<i><b></b></i>', $n) . '</div>' : '';
    $contatore = $d === 'fermo' && $n > 1 ? '<span class="sdl-contatore" aria-hidden="true"><b>1</b> di ' . $n . '</span>' : '';
    $linea = $d === 'tempo' ? '<div class="sdl-linea" aria-hidden="true"><b></b></div>' : '';
    return ($tela ? '<p class="sdl-nota-tela">Scorri di lato, ' . h(mb_strtolower(explode(':', SDL_DISPOSIZIONI[$d])[0])) . ': nella tela le pagine stanno in fila (scorrile con la barra qui sotto). L\'effetto si vede sul sito e nell\'anteprima.</p>' : '')
        . '<div class="sdl sdl-d-' . $d . ' sdl-foto-' . $foto . ($tela ? ' sdl-tela' : '') . '" data-sdl="' . $d . '" data-sdl-vel="' . $vel . '"' . (!empty($v['tutto_schermo']) ? ' data-sdl-pieno="1"' : '') . ' style="--n:' . $n . '">'
        . '<div class="sdl-scena">' . $testa . '<div class="sdl-binario">' . $linea . $out . '</div>' . $contatore . $lineette . '</div></div>';
});
editor_registra_asset('scorri_lato', ['css' => 'scorri.css', 'js' => 'scorri.js']);
