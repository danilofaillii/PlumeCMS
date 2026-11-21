<?php
/**
 * Disposizioni dell'Apertura, gruppo C (2.17.0): prospettiva, dispositivi, pila, orbita, terminale, nastro, traforata, faro.
 * Stessa lingua delle undici (finestra di vetro con il velo, riquadri dell'onda e delle barre, occhiello, titolo con
 * l'evidenza sfumata); il CSS sta in sito/assets/hero-c.css, ogni regola dentro .ed-hero-<id>.
 */

/** Prospettiva: parole a sinistra, a destra la finestra ruotata in 3D che entra dal bordo; al passaggio del mouse si raddrizza. */
function editor_hero_prospettiva(array $v): string {
    $foto = editor_hero_foto($v, 'ed-hpr-foto');
    $onda = editor_hero_onda($v);
    return editor_hero_apri('prospettiva')
        . '<div class="ed-hpr-griglia' . ($foto === '' ? ' ed-hpr-solo' : '') . '"><div class="ed-hpr-parole">' . editor_hero_parole($v) . '</div>'
        . ($foto !== '' ? '<div class="ed-hpr-scena"><div class="ed-hpr-giro">' . $foto . '</div>' . ($onda !== '' ? '<div class="ed-hpr-chip">' . $onda . '</div>' : '') . '</div>' : '')
        . '</div></div>';
}

/** Computer e telefono: la stessa foto grande nello schermo del computer e piccola nel telefono davanti. */
function editor_hero_dispositivi(array $v): string {
    $pc = editor_hero_foto($v, 'ed-hdv-schermo');
    $tel = editor_hero_foto($v, 'ed-hdv-schermo-tel');
    $scena = $pc !== '' ? '<div class="ed-hdv-scena"><div class="ed-hdv-pc">' . $pc . '<span class="ed-hdv-base" aria-hidden="true"></span></div>'
        . '<div class="ed-hdv-tel">' . $tel . '</div></div>' : '';
    return editor_hero_apri('dispositivi')
        . '<div class="ed-hdv-griglia' . ($scena === '' ? ' ed-hdv-solo' : '') . '"><div class="ed-hdv-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/** Pila di finestre: tre finestre sfalsate, tre tagli diversi della stessa foto; il riquadro delle barre in alto a destra. */
function editor_hero_pila(array $v): string {
    $f = [editor_hero_foto($v, 'ed-hpi-f1'), editor_hero_foto($v, 'ed-hpi-f2'), editor_hero_foto($v, 'ed-hpi-f3')];
    $barre = editor_hero_barre($v, 'ed-hpi-barre');
    $scena = $f[0] !== '' ? '<div class="ed-hpi-scena">' . implode('', $f) . $barre . '</div>' : ($barre !== '' ? '<div class="ed-hpi-scena ed-hpi-senza-foto">' . $barre . '</div>' : '');
    return editor_hero_apri('pila')
        . '<div class="ed-hpi-griglia' . ($scena === '' ? ' ed-hpi-solo' : '') . '"><div class="ed-hpi-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/** Orbita: la foto in un cerchio, due orbite intorno, il riquadro dell'onda che ci gira sopra piano e un pianeta ciano. */
function editor_hero_orbita(array $v): string {
    $foto = editor_hero_foto($v, 'ed-hor-foto');
    $onda = editor_hero_onda($v);
    $scena = '<div class="ed-hor-scena' . ($foto === '' ? ' ed-hor-senza-foto' : '') . '"><span class="ed-hor-anello ed-hor-a2" aria-hidden="true"></span><span class="ed-hor-anello ed-hor-a1" aria-hidden="true"></span>'
        . $foto . ($onda !== '' ? '<div class="ed-hor-giro"><div class="ed-hor-chip">' . $onda . '</div></div>' : '')
        . '<span class="ed-hor-pianeta" aria-hidden="true"></span></div>';
    return editor_hero_apri('orbita')
        . '<div class="ed-hor-griglia"><div class="ed-hor-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/**
 * Le righe del terminale: fino a 5, testo semplice. "$ ..." è un comando, "✓ ..." o "+ ..." una cosa fatta.
 * Ogni riga si scrive dopo la precedente (solo CSS: --n lettere, --t quando comincia); con meno movimento sono tutte già scritte.
 */
function editor_hero_terminale_righe(array $v): string {
    $righe = array_slice(array_values(array_filter(array_map(fn($r) => trim(mb_substr($r, 0, 80)), explode("\n", str_replace("\r", '', (string)($v['terminale_righe'] ?? '')))), fn($r) => $r !== '')), 0, 5);
    if (!$righe) return '';
    $out = ''; $t = 0.3;
    foreach ($righe as $r) {
        $segno = ''; $resto = $r;
        if (str_starts_with($r, '$')) { $segno = '<b>$</b>'; $resto = ltrim(mb_substr($r, 1)); }
        elseif (str_starts_with($r, "\u{2713}") || str_starts_with($r, '+')) { $segno = '<i>&#10003;</i>'; $resto = ltrim(mb_substr($r, 1)); }
        $n = mb_strlen($resto) + ($segno !== '' ? 2 : 0);
        $out .= '<span class="ed-hte-riga" style="--n:' . $n . ';--t:' . number_format($t, 2, '.', '') . 's">' . ($segno !== '' ? $segno . ' ' : '') . h($resto) . '</span>';
        $t += $n * 0.045 + 0.35;
    }
    return '<div class="ed-hte-cmd" style="--fine:' . number_format($t, 2, '.', '') . 's">' . $out . '<span class="ed-hte-cursore" aria-hidden="true"></span></div>';
}

/** Terminale: parole a sinistra; a destra la finestra con la foto e davanti una riga di comando che scrive le righe una alla volta. */
function editor_hero_terminale(array $v): string {
    $foto = editor_hero_foto($v, 'ed-hte-foto');
    $cmd = editor_hero_terminale_righe($v);
    $scena = $foto !== '' || $cmd !== '' ? '<div class="ed-hte-scena' . ($foto === '' ? ' ed-hte-senza-foto' : '') . '">' . $foto . $cmd . '</div>' : '';
    return editor_hero_apri('terminale')
        . '<div class="ed-hte-griglia' . ($scena === '' ? ' ed-hte-solo' : '') . '"><div class="ed-hte-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/**
 * Nastro di schermate: parole al centro, sotto una fila di finestre che scorre piano e sfuma ai lati.
 * Le foto sono la principale più le "Altre foto del nastro" (fino a 7); con una foto sola ogni finestra ne mostra un taglio
 * diverso. La fila è scritta due volte, così lo scorrimento torna all'inizio senza salto.
 */
function editor_hero_nastro(array $v): string {
    $foto = array_values(array_filter(array_merge([(string)($v['immagine'] ?? '')], array_map(fn($x) => (string)($x['immagine'] ?? ''), array_slice((array)($v['nastro_foto'] ?? []), 0, 7))), fn($u) => $u !== ''));
    $fila = '';
    if ($foto) {
        $voci = [];
        for ($i = 0; $i < max(6, count($foto)); $i++) $voci[] = editor_hero_foto($v, 'ed-hna-foto ed-hna-t' . ($i % 4), $foto[$i % count($foto)]);
        $fila = '<div class="ed-hna-nastro" aria-hidden="true"><div class="ed-hna-fila">' . implode('', $voci) . implode('', $voci) . '</div></div>';
    }
    return editor_hero_apri('nastro')
        . '<div class="ed-hna-parole">' . editor_hero_parole($v) . '</div>' . $fila
        . '</div>';
}

/** La parola grande del Titolo traforato: quella scelta, oppure la prima parola del titolo (senza asterischi e punteggiatura). */
function editor_hero_parola(array $v): string {
    $p = trim((string)($v['parola'] ?? ''));
    if ($p === '') {
        $t = trim(preg_replace('/[*_]/', '', (string)($v['titolo'] ?? '')));
        $p = (string)(preg_split('/\s+/u', $t)[0] ?? '');
    }
    $p = preg_replace('/[^\p{L}\p{N}\'&-]/u', '', $p) ?? '';
    return mb_substr($p, 0, 14);
}

/**
 * Titolo traforato: una parola sola, gigante, con la foto e il velo dentro le lettere. È dentro l'h1 ma nascosta ai
 * lettori di schermo; il titolo intero resta nell'h1, sotto e più piccolo: per Google e per chi ascolta la pagina è
 * lo stesso titolo di sempre.
 */
/**
 * L'indirizzo di una foto scritto dentro uno stile (url() del CSS): cms_con_cartella() guarda solo gli attributi src e href,
 * quindi in un sito in una cartella (/test) la cartella si mette qui, come fa per le <img>. Trovato dalle prove della 2.17.0.
 */
function editor_hero_con_cartella(string $u): string {
    $b = function_exists('cms_sito_base') ? cms_sito_base() : '';
    if ($b === '' || !str_starts_with($u, '/') || str_starts_with($u, '//') || $u === $b || str_starts_with($u, $b . '/')) return $u;
    return $b . $u;
}

function editor_hero_traforata(array $v): string {
    $parola = editor_hero_parola($v);
    $img = (string)($v['immagine'] ?? '');
    $t = (string)($v['foto_trattamento'] ?? 'velo');
    $classi = 'finestra ed-htr-velo' . ($img === '' ? ' ed-htr-senza-foto' : '') . (in_array($t, ['bn', 'naturale'], true) ? ' ed-foto-' . $t : '');
    $giga = $parola !== '' ? '<span class="' . $classi . '" aria-hidden="true"><span class="ed-htr-parola" style="--n:' . max(4, mb_strlen($parola))
        . ($img !== '' ? ';--foto:url(&quot;' . h(str_replace(['\\', '"'], '', editor_hero_con_cartella($img))) . '&quot;)' : '') . '">' . h($parola) . '</span></span>' : '';
    $occhiello = (string)($v['occhiello'] ?? '') !== '' ? '<span class="occhiello">' . h($v['occhiello']) . '</span>' : '';
    return editor_hero_apri('traforata')
        . '<div class="ed-htr-corpo">' . $occhiello
        . '<h1>' . $giga . '<span class="ed-htr-titolo">' . editor_evidenza((string)($v['titolo'] ?? '')) . '</span></h1>'
        . ((string)($v['testo'] ?? '') !== '' ? '<p class="lead">' . h($v['testo']) . '</p>' : '')
        . editor_hero_pulsanti($v) . '</div>'
        . '</div>';
}

/** Faro: un cono di luce viola e ciano scende dall'alto sulle parole, al centro; la finestra sale dal fondo, illuminata. */
function editor_hero_faro(array $v): string {
    $foto = editor_hero_foto($v, 'ed-hfa-foto');
    return editor_hero_apri('faro')
        . '<span class="ed-hfa-cono" aria-hidden="true"></span>'
        . '<div class="ed-hfa-parole">' . editor_hero_parole($v) . '</div>'
        . ($foto !== '' ? '<div class="ed-hfa-scena"><span class="ed-hfa-pavimento" aria-hidden="true"></span>' . $foto . '</div>' : '')
        . '</div>';
}
