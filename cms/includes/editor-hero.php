<?php
/**
 * Le disposizioni dell'Apertura (hero), 1.2.6, 27 ottobre 2026: oltre alla classica (testo a sinistra, foto a destra,
 * quella di sempre) dieci disposizioni diverse, scelte con la tendina "Disposizione" del widget. Stessi campi per tutte:
 * occhiello, titolo (con *evidenza*), testo, pulsanti, foto con la sezione Foto (velo, effetti), onda e barre.
 *
 * Ogni disposizione e una funzione editor_hero_<id>(array $v): string, che ritorna tutto l'HTML del widget dentro
 * <div class="hero ed-hero ed-hero-v ed-hero-<id>">. Stanno in editor-hero-a.php (centrata, copertina, divisa, rivista,
 * scheda) e editor-hero-b.php (minimale, mosaico, inclinata, banner, diagonale); il CSS in sito/assets/hero-base.css,
 * hero-a.css, hero-b.css, caricati solo nelle pagine che hanno un'Apertura. La classica resta in editor-widget.php,
 * identica al pixel a prima.
 */

const EDITOR_HERO_DISPOSIZIONI = [
    'classica'  => 'Classica: testo a sinistra, foto a destra',
    'centrata'  => 'Centrata: testo al centro, foto larga sotto',
    'copertina' => 'Copertina: foto a tutto sfondo, testo sopra',
    'divisa'    => 'Divisa a metà: testo su colore, foto fino al bordo',
    'rivista'   => 'Rivista: titolo enorme, testo a lato, foto a nastro',
    'scheda'    => 'Scheda: tutto in un riquadro, ritratto rotondo',
    'minimale'  => 'Minimale: solo parole, grandi, e i numeri sotto',
    'mosaico'   => 'Mosaico: foto e riquadri a griglia',
    'inclinata' => 'Inclinata: foto ruotata su forme di colore',
    'banner'    => 'Banner: fascia bassa per le pagine interne',
    'diagonale' => 'Diagonale: colore pieno tagliato in obliquo',
];

/** Il file CSS di ogni gruppo di disposizioni, caricati solo dove c'è un'Apertura. */
const EDITOR_HERO_CSS = ['hero-base.css', 'hero-a.css', 'hero-b.css'];

/** I pulsanti dell'Apertura, come nella classica. Vuoto se non ce ne sono. */
function editor_hero_pulsanti(array $v): string {
    $p = implode('', array_map(fn($p) => '<a class="btn btn-' . (array_key_exists($p['stile'] ?? '', EDITOR_STILI_PULSANTE) ? $p['stile'] : 'viola') . '" href="'
        . h(editor_link_sicuro((string)($p['link'] ?? '')) ?: '#') . '">' . h($p['testo'] ?? '') . '</a>', (array)($v['pulsanti'] ?? [])));
    return $p !== '' ? '<div class="azioni">' . $p . '</div>' : '';
}

/** Occhiello, titolo (h1, con *evidenza*), testo e pulsanti: le parole di ogni disposizione, con le classi della classica. */
function editor_hero_parole(array $v, bool $pulsanti = true): string {
    return ((string)($v['occhiello'] ?? '') !== '' ? '<span class="occhiello">' . h($v['occhiello']) . '</span>' : '')
        . '<h1>' . editor_evidenza((string)($v['titolo'] ?? '')) . '</h1>'
        . ((string)($v['testo'] ?? '') !== '' ? '<p class="lead">' . h($v['testo']) . '</p>' : '')
        . ($pulsanti ? editor_hero_pulsanti($v) : '');
}

/**
 * La foto, con il trattamento e l'effetto della sezione Foto (velo, bianco e nero, naturale; zoom, bagliore...).
 * Ha la classe "finestra" perché i colori del velo della scheda Stile si scrivono su .finestra; hero-base.css la
 * toglie dalla posizione assoluta della classica. $classe: classi in più della disposizione. Vuoto senza foto.
 */
function editor_hero_foto(array $v, string $classe = ''): string {
    if ((string)($v['immagine'] ?? '') === '') return '';
    return '<div class="finestra ed-hero-foto' . ($classe !== '' ? ' ' . h($classe) : '') . editor_foto_classi($v) . '" aria-hidden="true">'
        . '<img src="' . h($v['immagine']) . '" alt="" width="768" height="768" fetchpriority="high">'
        . (($v['foto_effetto'] ?? '') !== '' ? '<span class="ed-fx-luce"></span>' : '') . '</div>';
}

/** Il riquadro dell'onda (se acceso e con l'etichetta), come nella classica. */
function editor_hero_onda(array $v, string $classe = ''): string {
    if (!($v['onda_mostra'] ?? true) || (string)($v['etichetta_onda'] ?? '') === '') return '';
    $onda = implode('', array_map(fn($x) => '<span style="animation-delay:' . $x . 's"></span>', [0, .2, .4, .1, .5, .3, .6, .15, .45, .25]));
    return '<div class="chip chip-onda' . ($classe !== '' ? ' ' . h($classe) : '') . '">' . h($v['etichetta_onda']) . '<div class="onda">' . $onda . '</div></div>';
}

/** Il riquadro delle barre (se ce ne sono), come nella classica. */
function editor_hero_barre(array $v, string $classe = ''): string {
    $b = implode('', array_map(fn($b) => '<div class="barra">' . h($b['etichetta'] ?? '') . '<div style="--w:' . max(0, min(100, (int)($b['valore'] ?? 0))) . '%"></div></div>', (array)($v['barre'] ?? [])));
    return $b !== '' ? '<div class="chip chip-barre' . ($classe !== '' ? ' ' . h($classe) : '') . '">' . $b . '</div>' : '';
}

/** Le barre come numeri grandi (etichetta e valore), per le disposizioni che le mostrano come statistiche. */
function editor_hero_numeri(array $v): string {
    $n = implode('', array_map(fn($b) => '<div class="ed-hero-numero"><b>' . max(0, min(100, (int)($b['valore'] ?? 0))) . '%</b><span>' . h($b['etichetta'] ?? '') . '</span></div>', (array)($v['barre'] ?? [])));
    return $n !== '' ? '<div class="ed-hero-numeri">' . $n . '</div>' : '';
}

/** Apre il contenitore comune: ogni disposizione comincia così e chiude con </div>. */
function editor_hero_apri(string $id): string {
    return '<div class="hero ed-hero ed-hero-v ed-hero-' . $id . '">';
}

foreach (['editor-hero-a.php', 'editor-hero-b.php'] as $f) if (is_file(__DIR__ . '/' . $f)) require_once __DIR__ . '/' . $f;
