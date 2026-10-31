<?php
/**
 * Disposizioni dell'Apertura, gruppo B (1.2.6): minimale, mosaico, inclinata, banner, diagonale.
 * Ognuna e costruita con le funzioni comuni di editor-hero.php; il CSS sta in sito/assets/hero-b.css,
 * con tutte le regole dentro .ed-hero-<id>.
 */

/** Minimale: niente foto, solo parole grandi e ariose; sotto i pulsanti le barre come numeri, dopo una linea sottile. */
function editor_hero_minimale(array $v): string {
    return editor_hero_apri('minimale')
        . '<div class="ed-hmi-corpo">' . editor_hero_parole($v) . editor_hero_numeri($v) . '</div>'
        . '</div>';
}

/** Mosaico: parole a sinistra; a destra una griglia di riquadri: la foto grande, un riquadro di colore, l'onda e le barre. */
function editor_hero_mosaico(array $v): string {
    $riquadri = editor_hero_foto($v, 'ed-hmo-foto')
        . '<div class="ed-hmo-colore" aria-hidden="true"><span></span></div>'
        . editor_hero_onda($v, 'ed-hmo-onda')
        . editor_hero_barre($v, 'ed-hmo-barre');
    return editor_hero_apri('mosaico')
        . '<div class="ed-hmo-griglia"><div class="ed-hmo-parole">' . editor_hero_parole($v) . '</div>'
        . '<div class="ed-hmo-mosaico' . ((string)($v['immagine'] ?? '') === '' ? ' ed-hmo-senza-foto' : '') . '">' . $riquadri . '</div></div>'
        . '</div>';
}

/** Inclinata: parole a sinistra; a destra la foto ruotata di qualche grado, appoggiata su forme di colore trasparenti. */
function editor_hero_inclinata(array $v): string {
    $foto = editor_hero_foto($v, 'ed-hin-foto');
    $scena = $foto !== '' ? '<div class="ed-hin-scena"><span class="ed-hin-forma ed-hin-f1" aria-hidden="true"></span>'
        . '<span class="ed-hin-forma ed-hin-f2" aria-hidden="true"></span><span class="ed-hin-forma ed-hin-f3" aria-hidden="true"></span>'
        . $foto . '</div>' : '';
    return editor_hero_apri('inclinata')
        . '<div class="ed-hin-griglia' . ($scena === '' ? ' ed-hin-solo' : '') . '"><div class="ed-hin-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/** Banner: fascia bassa per le pagine interne; foto piccola e tonda accanto al titolo, pulsanti a destra sulla stessa riga. */
function editor_hero_banner(array $v): string {
    return editor_hero_apri('banner')
        . '<div class="ed-hba-riga">' . editor_hero_foto($v, 'ed-hba-foto')
        . '<div class="ed-hba-parole">' . editor_hero_parole($v, false) . '</div>'
        . editor_hero_pulsanti($v) . '</div>'
        . '</div>';
}

/** Diagonale: fondo pieno con il gradiente dei colori del sito, tagliato in obliquo in basso; foto in cornice chiara a destra. */
function editor_hero_diagonale(array $v): string {
    $foto = editor_hero_foto($v, 'ed-hdi-foto');
    return editor_hero_apri('diagonale')
        . '<div class="ed-hdi-fondo' . ($foto === '' ? ' ed-hdi-solo' : '') . '"><div class="ed-hdi-parole">' . editor_hero_parole($v) . '</div></div>'
        . ($foto !== '' ? '<div class="ed-hdi-cornice">' . $foto . '</div>' : '')
        . '</div>';
}
