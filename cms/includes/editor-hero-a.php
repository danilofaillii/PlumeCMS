<?php
/**
 * Disposizioni dell'Apertura, gruppo A (1.2.6): centrata, copertina, divisa, rivista, scheda.
 * Ognuna costruisce l'HTML con le funzioni comuni di editor-hero.php; lo stile sta in sito/assets/hero-a.css,
 * con ogni regola dentro .ed-hero-<id>.
 */

/** Centrata: parole al centro, sotto la foto larga e bassa; onda e barre a cavallo del bordo basso della foto. */
function editor_hero_centrata(array $v): string {
    $chip = editor_hero_onda($v) . editor_hero_barre($v);
    $foto = editor_hero_foto($v, 'ed-hc-foto');
    return editor_hero_apri('centrata')
        . '<div class="ed-hc-parole">' . editor_hero_parole($v) . '</div>'
        . ($foto !== '' || $chip !== '' ? '<div class="ed-hc-scena' . ($foto === '' ? ' ed-hc-senza-foto' : '') . '">' . $foto
            . ($chip !== '' ? '<div class="ed-hc-chip">' . $chip . '</div>' : '') . '</div>' : '')
        . '</div>';
}

/** Copertina: la foto riempie lo sfondo, un velo scuro sfumato la copre e le parole stanno sopra in bianco. */
function editor_hero_copertina(array $v): string {
    $chip = editor_hero_onda($v) . editor_hero_barre($v);
    return editor_hero_apri('copertina')
        . editor_hero_foto($v, 'ed-hcop-foto')
        . '<div class="ed-hcop-velo" aria-hidden="true"></div>'
        . '<div class="ed-hcop-parole">' . editor_hero_parole($v) . '</div>'
        . ($chip !== '' ? '<div class="ed-hcop-chip">' . $chip . '</div>' : '')
        . '</div>';
}

/** Divisa a meta: a sinistra le parole su un fondo di colore, a destra la foto fino al bordo del riquadro. */
function editor_hero_divisa(array $v): string {
    $chip = editor_hero_onda($v) . editor_hero_barre($v);
    $foto = editor_hero_foto($v, 'ed-hd-foto');
    return editor_hero_apri('divisa')
        . '<div class="ed-hd-parole">' . editor_hero_parole($v) . '</div>'
        . ($foto !== '' ? '<div class="ed-hd-lato">' . $foto . ($chip !== '' ? '<div class="ed-hd-chip">' . $chip . '</div>' : '') . '</div>'
            : ($chip !== '' ? '<div class="ed-hd-lato ed-hd-senza-foto"><div class="ed-hd-chip">' . $chip . '</div></div>' : ''))
        . '</div>';
}

/** Rivista: occhiello e titolo enorme a tutta larghezza, la foto a nastro, sotto testo e pulsanti a sinistra e i numeri a destra. */
function editor_hero_rivista(array $v): string {
    $occhiello = (string)($v['occhiello'] ?? '') !== '' ? '<span class="occhiello">' . h($v['occhiello']) . '</span>' : '';
    $lato = editor_hero_numeri($v) . editor_hero_onda($v);
    return editor_hero_apri('rivista')
        . '<div class="ed-hr-testa">' . $occhiello . '<h1>' . editor_evidenza((string)($v['titolo'] ?? '')) . '</h1></div>'
        . editor_hero_foto($v, 'ed-hr-nastro')
        . '<div class="ed-hr-sotto"><div class="ed-hr-parole">'
            . ((string)($v['testo'] ?? '') !== '' ? '<p class="lead">' . h($v['testo']) . '</p>' : '')
            . editor_hero_pulsanti($v) . '</div>'
            . ($lato !== '' ? '<div class="ed-hr-lato">' . $lato . '</div>' : '') . '</div>'
        . '</div>';
}

/** Scheda: tutto in un riquadro staccato dal fondo, il ritratto rotondo con l'anello a sinistra e le parole a destra. */
function editor_hero_scheda(array $v): string {
    $chip = editor_hero_onda($v) . editor_hero_barre($v);
    $foto = editor_hero_foto($v, 'ed-hs-foto');
    return editor_hero_apri('scheda')
        . '<div class="ed-hs-riquadro' . ($foto === '' ? ' ed-hs-senza-foto' : '') . '">'
        . ($foto !== '' ? '<div class="ed-hs-anello">' . $foto . '</div>' : '')
        . '<div class="ed-hs-parole">' . editor_hero_parole($v) . ($chip !== '' ? '<div class="ed-hs-chip">' . $chip . '</div>' : '') . '</div>'
        . '</div></div>';
}
