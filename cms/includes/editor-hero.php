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
 * 2.17.0: altre quindici, in editor-hero-c.php (prospettiva, dispositivi, pila, orbita, terminale, nastro, traforata, faro)
 * e editor-hero-d.php (orizzonte, video, note, lente, costellazione, primadopo, trittico), con hero-c.css e hero-d.css.
 * Alcune hanno campi loro, che nel pannello compaiono solo quando la disposizione li usa ('condizione').
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
    // 2.17.0: quindici disposizioni nuove, scelte da Danilo (editor-hero-c.php, editor-hero-d.php).
    'prospettiva'   => 'Prospettiva: finestra ruotata in 3D dal bordo destro',
    'dispositivi'   => 'Computer e telefono: la stessa foto in tutti e due',
    'pila'          => 'Pila di finestre: tre tagli della foto sfalsati',
    'orbita'        => 'Orbita: foto in un cerchio con le orbite',
    'terminale'     => 'Terminale: righe di comando scritte una alla volta',
    'nastro'        => 'Nastro di schermate: titolo al centro, fila che scorre',
    'traforata'     => 'Titolo traforato: la foto dentro una parola gigante',
    'faro'          => 'Faro: cono di luce sulla foto che sale dal fondo',
    'orizzonte'     => 'Orizzonte: sole sfumato e griglia luminosa',
    'video'         => 'Video: la foto con il pulsante play',
    'note'          => 'Note sulla foto: tre etichette appuntate',
    'lente'         => 'Lente: un dettaglio della foto ingrandito',
    'costellazione' => 'Costellazione: stelle collegate, senza foto',
    'primadopo'     => 'Prima e dopo: la foto divisa da una maniglia',
    'trittico'      => 'Trittico: parole, tablet e tre riquadri',
];

/** 2.17.0: le forme della Costellazione, disegnate in casa (editor_hero_costellazione() in editor-hero-d.php). */
const EDITOR_HERO_FORME = ['piuma' => 'Piuma', 'cerchio' => 'Cerchio', 'stella' => 'Stella'];

/** Il file CSS di ogni gruppo di disposizioni, caricati solo dove c'è un'Apertura. */
const EDITOR_HERO_CSS = ['hero-base.css', 'hero-a.css', 'hero-b.css', 'hero-c.css', 'hero-d.css'];

/**
 * 2.17.0: lo script di terminale, video e prima e dopo (righe scritte una alla volta, play, maniglia). Si carica solo
 * nelle pagine dove un'Apertura ha l'attributo data-ed-hero-js (editor_asset_pagina() cerca quel segno, non la classe).
 */
const EDITOR_HERO_JS = 'hero-c.js';

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
 * 2.17.0: $src per un'altra foto (le altre foto del nastro, la foto "prima"), $dentro per l'HTML in più dentro la
 * finestra (la lente). Senza i due parametri l'HTML è quello di prima, al byte.
 */
function editor_hero_foto(array $v, string $classe = '', ?string $src = null, string $dentro = ''): string {
    $src = $src ?? (string)($v['immagine'] ?? '');
    if ($src === '') return '';
    return '<div class="finestra ed-hero-foto' . ($classe !== '' ? ' ' . h($classe) : '') . editor_foto_classi($v) . '" aria-hidden="true">'
        . '<img src="' . h($src) . '" alt="" width="768" height="768" fetchpriority="high">'
        . (($v['foto_effetto'] ?? '') !== '' ? '<span class="ed-fx-luce"></span>' : '') . $dentro . '</div>';
}

/**
 * 2.17.0: l'indirizzo di un video caricato nel sito (MP4 o WebM), oppure '' se non va bene. Solo un percorso del sito
 * (/uploads/video/...), oppure l'indirizzo intero della cartella delle immagini quando sta su un altro dominio
 * (CMS_UPLOADS_URL): niente indirizzi esterni, niente javascript:, niente "..".
 */
function editor_video_locale(string $u): string {
    $u = trim($u);
    if ($u === '' || strlen($u) > 500 || str_contains($u, '..') || !preg_match('~\.(mp4|webm)$~i', $u)) return '';
    if (preg_match('~^/[A-Za-z0-9/_.\-]+$~', $u) && !str_starts_with($u, '//')) return $u;
    $base = function_exists('cms_uploads_url') ? cms_uploads_url() : '';
    if (preg_match('~^https?://[^/]+~', $base) && str_starts_with($u, $base . '/') && preg_match('~^https?://[A-Za-z0-9.:\-]+/[A-Za-z0-9/_.\-]+$~', $u)) return $u;
    return '';
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

foreach (['editor-hero-a.php', 'editor-hero-b.php', 'editor-hero-c.php', 'editor-hero-d.php'] as $f) if (is_file(__DIR__ . '/' . $f)) require_once __DIR__ . '/' . $f;
