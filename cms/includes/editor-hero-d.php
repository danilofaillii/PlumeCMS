<?php
/**
 * Disposizioni dell'Apertura, gruppo D (2.17.0): orizzonte, video, note, lente, costellazione, primadopo, trittico.
 * Stessa lingua delle undici; il CSS sta in sito/assets/hero-d.css (ogni regola dentro .ed-hero-<id>), lo script di
 * video e prima e dopo in sito/assets/hero-c.js, caricato solo dove c'è l'attributo data-ed-hero-js.
 */

/** Orizzonte: un sole sfumato dietro e un pavimento a griglia luminosa che corre verso l'orizzonte; la finestra galleggia. */
function editor_hero_orizzonte(array $v): string {
    $foto = editor_hero_foto($v, 'ed-hoz-foto');
    return editor_hero_apri('orizzonte')
        . '<span class="ed-hoz-sole" aria-hidden="true"></span><span class="ed-hoz-suolo" aria-hidden="true"></span>'
        . '<div class="ed-hoz-griglia' . ($foto === '' ? ' ed-hoz-solo' : '') . '"><div class="ed-hoz-parole">' . editor_hero_parole($v) . '</div>'
        . ($foto !== '' ? '<div class="ed-hoz-scena">' . $foto . '</div>' : '') . '</div>'
        . '</div>';
}

/**
 * Video: la finestra con la foto e un grande pulsante play; al clic hero-c.js mette al suo posto il video caricato nei
 * Media (un <video> del sito, niente servizi esterni). Senza video la foto resta senza play.
 */
function editor_hero_video(array $v): string {
    $video = function_exists('editor_video_locale') ? editor_video_locale((string)($v['video'] ?? '')) : '';
    $foto = editor_hero_foto($v, 'ed-hvi-foto');
    if ($foto === '' && $video !== '') $foto = '<div class="finestra ed-hero-foto ed-hvi-foto ed-hvi-vuota" aria-hidden="true"></div>';
    $scena = $foto !== '' ? '<div class="ed-hvi-scena"' . ($video !== '' ? ' data-ed-hero-js="video" data-video="' . h($video) . '"' : '') . '>' . $foto
        . ($video !== '' ? '<button type="button" class="ed-hvi-play" aria-label="Guarda il video"><span aria-hidden="true"></span></button>' : '') . '</div>' : '';
    return editor_hero_apri('video')
        . '<div class="ed-hvi-griglia' . ($scena === '' ? ' ed-hvi-solo' : '') . '"><div class="ed-hvi-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/** Note sulla foto: fino a tre etichette con il pallino ciano appuntate sulla finestra (posizione in %), e un cursore che si muove. */
function editor_hero_note(array $v): string {
    $foto = editor_hero_foto($v, 'ed-hno-foto');
    $note = '';
    foreach (array_slice((array)($v['etichette'] ?? []), 0, 3) as $e) {
        $t = trim((string)($e['testo'] ?? ''));
        if ($t === '') continue;
        $x = max(0, min(100, (int)($e['x'] ?? 50))); $y = max(0, min(100, (int)($e['y'] ?? 50)));
        $note .= '<li style="--x:' . $x . '%;--y:' . $y . '%"><i aria-hidden="true"></i>' . h(mb_substr($t, 0, 40)) . '</li>';
    }
    $note = $note !== '' ? '<ul class="ed-hno-note">' . $note . '</ul>' : '';
    $scena = $foto !== '' ? '<div class="ed-hno-scena">' . $foto . $note . '<span class="ed-hno-cursore" aria-hidden="true"></span></div>'
        : ($note !== '' ? '<div class="ed-hno-scena ed-hno-senza-foto">' . $note . '</div>' : '');
    return editor_hero_apri('note')
        . '<div class="ed-hno-griglia' . ($scena === '' ? ' ed-hno-solo' : '') . '"><div class="ed-hno-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/**
 * Lente: una lente con il bordo sfumato ingrandisce a colori il punto scelto della foto (lente_x, lente_y in %), sopra il
 * resto con il suo velo. Il pezzo ingrandito è la stessa foto, scalata intorno al punto e ritagliata a cerchio.
 */
function editor_hero_lente(array $v): string {
    $img = (string)($v['immagine'] ?? '');
    // Il punto si tiene dentro la foto: vicino ai bordi la lente uscirebbe dalla finestra.
    $x = max(16, min(84, (int)($v['lente_x'] ?? 50))); $y = max(20, min(80, (int)($v['lente_y'] ?? 55)));
    $zoom = $img !== '' ? '<span class="ed-hle-zoom"><img src="' . h($img) . '" alt="" width="768" height="768"></span>' : '';
    $foto = editor_hero_foto($v, 'ed-hle-foto', null, $zoom);
    $scena = $foto !== '' ? '<div class="ed-hle-scena" style="--x:' . $x . '%;--y:' . $y . '%">' . $foto . '<span class="ed-hle-anello" aria-hidden="true"><span class="ed-hle-manico"></span></span></div>' : '';
    return editor_hero_apri('lente')
        . '<div class="ed-hle-griglia' . ($scena === '' ? ' ed-hle-solo' : '') . '"><div class="ed-hle-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/** Le forme della Costellazione: i punti (x, y su 100), le linee fra i punti e il punto viola che si accende. */
function editor_hero_forma(string $forma): array {
    $giro = function (int $n, float $cx, float $cy, array $raggi, float $da): array {
        $p = [];
        for ($i = 0; $i < $n; $i++) { $a = deg2rad($da + 360 / $n * $i); $r = $raggi[$i % count($raggi)]; $p[] = [round($cx + $r * cos($a), 1), round($cy + $r * sin($a), 1)]; }
        return $p;
    };
    if ($forma === 'cerchio') {
        $p = $giro(10, 50, 50, [38], -90);
        $l = []; for ($i = 0; $i < 10; $i++) $l[] = [$i, ($i + 1) % 10];
        return ['punti' => $p, 'linee' => array_merge($l, [[0, 5], [2, 7]]), 'accento' => 3];
    }
    if ($forma === 'stella') {
        $p = $giro(10, 50, 52, [40, 16], -90);
        $l = []; for ($i = 0; $i < 10; $i++) $l[] = [$i, ($i + 1) % 10];
        return ['punti' => $p, 'linee' => $l, 'accento' => 0];
    }
    // piuma: la punta in alto a destra, il calamo in basso a sinistra, due bordi di barbe e il rachide in mezzo.
    $p = [[74, 8], [62, 34], [52, 58], [44, 78], [30, 96], [84, 20], [86, 38], [78, 58], [62, 74], [65, 17], [50, 34], [40, 54], [38, 70]];
    $l = [[0, 1], [1, 2], [2, 3], [3, 4], [0, 5], [5, 6], [6, 7], [7, 8], [8, 3], [0, 9], [9, 10], [10, 11], [11, 12], [12, 3], [1, 5], [2, 6], [3, 7], [1, 10], [2, 11]];
    return ['punti' => $p, 'linee' => $l, 'accento' => 7];
}

/** Costellazione: niente foto; stelle collegate da linee sottili disegnano una forma (piuma, cerchio, stella) e un punto viola si accende. */
function editor_hero_costellazione(array $v): string {
    $forma = isset(EDITOR_HERO_FORME[(string)($v['forma'] ?? '')]) ? (string)$v['forma'] : 'piuma';
    $f = editor_hero_forma($forma);
    $lontane = '';
    foreach ([[6, 10], [28, 4], [96, 6], [12, 44], [98, 64], [4, 82], [94, 94], [58, 98], [42, 22], [70, 40], [20, 64], [86, 78]] as [$x, $y]) $lontane .= '<circle cx="' . $x . '" cy="' . $y . '" r=".45"/>';
    $linee = '';
    foreach ($f['linee'] as $i => [$a, $b]) $linee .= '<path pathLength="1" style="--i:' . $i . '" d="M' . $f['punti'][$a][0] . ' ' . $f['punti'][$a][1] . 'L' . $f['punti'][$b][0] . ' ' . $f['punti'][$b][1] . '"/>';
    $stelle = '';
    foreach ($f['punti'] as $i => [$x, $y]) if ($i !== $f['accento']) $stelle .= '<circle style="--i:' . $i . '" cx="' . $x . '" cy="' . $y . '" r="' . ($i % 3 === 0 ? '1.3' : '.95') . '"/>';
    [$ax, $ay] = $f['punti'][$f['accento']];
    $svg = '<svg class="ed-hco-cielo ed-hco-' . $forma . '" viewBox="0 0 100 100" aria-hidden="true" focusable="false">'
        . '<g class="ed-hco-lontane">' . $lontane . '</g><g class="ed-hco-linee">' . $linee . '</g><g class="ed-hco-stelle">' . $stelle . '</g>'
        . '<circle class="ed-hco-alone" cx="' . $ax . '" cy="' . $ay . '" r="4"/><circle class="ed-hco-accento" cx="' . $ax . '" cy="' . $ay . '" r="1.9"/></svg>';
    $barre = editor_hero_barre($v, 'ed-hco-barre');
    return editor_hero_apri('costellazione')
        . '<div class="ed-hco-griglia"><div class="ed-hco-parole">' . editor_hero_parole($v) . '</div>'
        . '<div class="ed-hco-scena">' . $svg . $barre . '</div></div>'
        . '</div>';
}

/**
 * Prima e dopo: la finestra divisa da una maniglia. A destra la foto "dopo" con il velo, a sinistra la foto "prima" (o la
 * stessa, spenta). La maniglia è un cursore vero (input range: mouse, dito, frecce della tastiera); hero-c.js sposta la
 * divisione. Senza script resta a metà.
 */
function editor_hero_primadopo(array $v): string {
    $img = (string)($v['immagine'] ?? '');
    $prima = (string)($v['immagine_prima'] ?? '');
    $strato = $img !== '' ? '<span class="ed-hpd-prima' . ($prima === '' ? ' ed-hpd-spenta' : '') . '"><img src="' . h($prima !== '' ? $prima : $img) . '" alt="" width="768" height="768"></span>' : '';
    $foto = editor_hero_foto($v, 'ed-hpd-foto', null, $strato);
    $scena = $foto !== '' ? '<div class="ed-hpd-scena" data-ed-hero-js="primadopo" style="--pos:50%">' . $foto
        . '<input class="ed-hpd-cursore" type="range" min="0" max="100" step="1" value="50" aria-label="Prima e dopo: sposta la divisione con le frecce" aria-valuetext="metà prima, metà dopo">'
        . '<span class="ed-hpd-linea" aria-hidden="true"><span class="ed-hpd-maniglia"></span></span>'
        . '<span class="ed-hpd-eti ed-hpd-eti-prima" aria-hidden="true">Prima</span><span class="ed-hpd-eti ed-hpd-eti-dopo" aria-hidden="true">Dopo</span></div>' : '';
    return editor_hero_apri('primadopo')
        . '<div class="ed-hpd-griglia' . ($scena === '' ? ' ed-hpd-solo' : '') . '"><div class="ed-hpd-parole">' . editor_hero_parole($v) . '</div>' . $scena . '</div>'
        . '</div>';
}

/** Trittico: tre colonne, le parole, la foto in un tablet verticale e tre riquadri di vetro (onda, un numero grande, barre). */
function editor_hero_trittico(array $v): string {
    $barre = array_values((array)($v['barre'] ?? []));
    $numero = '';
    // Il numero grande: quello scritto nel campo "Numero grande" (es. "5 min"); vuoto, la prima delle Barre in percentuale.
    if (trim((string)($v['numero'] ?? '')) !== '') {
        $numero = '<div class="chip ed-htt-numero">' . h(mb_substr((string)($v['numero_etichetta'] ?? ''), 0, 40)) . '<b>' . h(mb_substr(trim((string)$v['numero']), 0, 12)) . '</b></div>';
    } elseif ($barre) {
        $b = array_shift($barre);
        $numero = '<div class="chip ed-htt-numero">' . h($b['etichetta'] ?? '') . '<b>' . max(0, min(100, (int)($b['valore'] ?? 0))) . '%</b></div>';
    }
    $col = editor_hero_onda($v) . $numero . editor_hero_barre(['barre' => $barre]);
    $foto = editor_hero_foto($v, 'ed-htt-foto');
    return editor_hero_apri('trittico')
        . '<div class="ed-htt-griglia' . ($foto === '' ? ' ed-htt-senza-foto' : '') . ($col === '' ? ' ed-htt-senza-col' : '') . '"><div class="ed-htt-parole">' . editor_hero_parole($v) . '</div>'
        . ($foto !== '' ? '<div class="ed-htt-tablet">' . $foto . '</div>' : '')
        . ($col !== '' ? '<div class="ed-htt-col">' . $col . '</div>' : '') . '</div>'
        . '</div>';
}
