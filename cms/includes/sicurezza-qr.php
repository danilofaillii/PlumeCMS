<?php
/**
 * Codice QR fatto in casa, in SVG: serve alla pagina del secondo fattore (TOTP) per mostrare la chiave
 * all'app di autenticazione senza passare da un servizio esterno (la chiave non deve uscire dal server).
 *
 * Modalita byte, correzione di errori M, versioni da 1 a 10 (fino a 213 byte): un indirizzo otpauth:// ci sta.
 * Nessuna libreria. L'algoritmo e quello della norma ISO/IEC 18004: dati, codici di Reed-Solomon, disposizione
 * a zig-zag, maschera scelta con le penalita 1 e 2, informazioni di formato e di versione.
 */

/** Il codice QR di un testo come SVG (quadrati neri su bianco). $scala: pixel per modulo. */
function sicurezza_qr_svg(string $testo, int $scala = 5, int $margine = 4): string {
    $m = sicurezza_qr_matrice($testo);
    if ($m === null) return '';
    $n = count($m);
    $lato = ($n + 2 * $margine) * $scala;
    $out = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $lato . ' ' . $lato . '" width="' . $lato . '" height="' . $lato . '" shape-rendering="crispEdges" role="img" aria-label="Codice QR">'
        . '<rect width="' . $lato . '" height="' . $lato . '" fill="#fff"/><path fill="#000" d="';
    for ($r = 0; $r < $n; $r++) {
        $c = 0;
        while ($c < $n) {
            if (!$m[$r][$c]) { $c++; continue; }
            $da = $c;
            while ($c < $n && $m[$r][$c]) $c++;
            $out .= 'M' . (($da + $margine) * $scala) . ' ' . (($r + $margine) * $scala) . 'h' . (($c - $da) * $scala) . 'v' . $scala . 'h-' . (($c - $da) * $scala) . 'z';
        }
    }
    return $out . '"/></svg>';
}

/** La matrice dei moduli (array di righe di 0/1), oppure null se il testo e troppo lungo. */
function sicurezza_qr_matrice(string $testo): ?array {
    // Versione, totale codeword, codeword di correzione per blocco, blocchi [n, dati] del gruppo 1 e 2 (livello M).
    $tab = [
        1 => [26, 10, [1, 16], [0, 0]], 2 => [44, 16, [1, 28], [0, 0]], 3 => [70, 26, [1, 44], [0, 0]], 4 => [100, 18, [2, 32], [0, 0]],
        5 => [134, 24, [2, 43], [0, 0]], 6 => [172, 16, [4, 27], [0, 0]], 7 => [196, 18, [4, 31], [0, 0]], 8 => [242, 22, [2, 38], [2, 39]],
        9 => [292, 22, [3, 36], [2, 37]], 10 => [346, 26, [4, 43], [1, 44]],
    ];
    $len = strlen($testo);
    $v = 0;
    foreach ($tab as $ver => $t) {
        $dati = $t[2][0] * $t[2][1] + $t[3][0] * $t[3][1];
        $intest = 4 + ($ver < 10 ? 8 : 16);
        if ($len * 8 + $intest <= $dati * 8) { $v = $ver; break; }
    }
    if ($v === 0) return null;
    [$totale, $ecPer, $g1, $g2] = $tab[$v];
    $dataCw = $g1[0] * $g1[1] + $g2[0] * $g2[1];

    // 1. Flusso di bit: modo byte, lunghezza, dati, terminatore, riempimento.
    $bits = '';
    $bits .= '0100';
    $bits .= str_pad(decbin($len), $v < 10 ? 8 : 16, '0', STR_PAD_LEFT);
    for ($i = 0; $i < $len; $i++) $bits .= str_pad(decbin(ord($testo[$i])), 8, '0', STR_PAD_LEFT);
    $bits .= substr('0000', 0, max(0, min(4, $dataCw * 8 - strlen($bits))));
    while (strlen($bits) % 8) $bits .= '0';
    $pad = ['11101100', '00010001'];
    for ($i = 0; strlen($bits) < $dataCw * 8; $i++) $bits .= $pad[$i % 2];
    $cw = [];
    for ($i = 0; $i < $dataCw; $i++) $cw[] = bindec(substr($bits, $i * 8, 8));

    // 2. Blocchi e codici di correzione.
    $blocchi = [];
    $pos = 0;
    foreach ([$g1, $g2] as $g) {
        for ($b = 0; $b < $g[0]; $b++) {
            $d = array_slice($cw, $pos, $g[1]);
            $pos += $g[1];
            $blocchi[] = [$d, sicurezza_qr_rs($d, $ecPer)];
        }
    }
    $finale = [];
    $maxD = max(array_map(fn($b) => count($b[0]), $blocchi));
    for ($i = 0; $i < $maxD; $i++) foreach ($blocchi as $b) if (isset($b[0][$i])) $finale[] = $b[0][$i];
    for ($i = 0; $i < $ecPer; $i++) foreach ($blocchi as $b) $finale[] = $b[1][$i];
    $flusso = '';
    foreach ($finale as $c) $flusso .= str_pad(decbin($c), 8, '0', STR_PAD_LEFT);
    $resto = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0][$v];
    $flusso .= str_repeat('0', $resto);

    // 3. Matrice con i disegni fissi; $fissa segna i moduli che la maschera non deve toccare.
    $n = 4 * $v + 17;
    $m = array_fill(0, $n, array_fill(0, $n, 0));
    $fissa = array_fill(0, $n, array_fill(0, $n, false));
    $metti = function (int $r, int $c, int $val) use (&$m, &$fissa, $n) {
        if ($r < 0 || $c < 0 || $r >= $n || $c >= $n) return;
        $m[$r][$c] = $val; $fissa[$r][$c] = true;
    };
    // Finder con separatore
    foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$r0, $c0]) {
        for ($r = -1; $r <= 7; $r++) for ($c = -1; $c <= 7; $c++) {
            $dentro = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
            $nero = $dentro && (($r === 0 || $r === 6 || $c === 0 || $c === 6) || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
            $metti($r0 + $r, $c0 + $c, $nero ? 1 : 0);
        }
    }
    // Timing
    for ($i = 8; $i < $n - 8; $i++) { $metti(6, $i, $i % 2 === 0 ? 1 : 0); $metti($i, 6, $i % 2 === 0 ? 1 : 0); }
    // Allineamento
    $centri = [2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]][$v] ?? [];
    foreach ($centri as $cr) foreach ($centri as $cc) {
        if (($cr < 9 && $cc < 9) || ($cr < 9 && $cc > $n - 10) || ($cr > $n - 10 && $cc < 9)) continue; // sopra un finder
        for ($r = -2; $r <= 2; $r++) for ($c = -2; $c <= 2; $c++) {
            $nero = max(abs($r), abs($c)) !== 1;
            $metti($cr + $r, $cc + $c, $nero ? 1 : 0);
        }
    }
    // Modulo scuro
    $metti(4 * $v + 9, 8, 1);
    // Aree del formato (riservate) e della versione
    for ($i = 0; $i < 9; $i++) { if (!$fissa[8][$i]) $metti(8, $i, 0); if (!$fissa[$i][8]) $metti($i, 8, 0); }
    for ($i = 0; $i < 8; $i++) $metti(8, $n - 1 - $i, 0);
    for ($i = 0; $i < 7; $i++) $metti($n - 1 - $i, 8, 0);
    if ($v >= 7) {
        $vinfo = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3][$v];
        for ($i = 0; $i < 18; $i++) {
            $bit = ($vinfo >> $i) & 1;
            $metti(intdiv($i, 3), $n - 11 + $i % 3, $bit);
            $metti($n - 11 + $i % 3, intdiv($i, 3), $bit);
        }
    }

    // 4. Dati a zig-zag, dal basso a destra, due colonne alla volta, saltando la colonna 6.
    $idx = 0;
    $lunghezza = strlen($flusso);
    $su = true;
    for ($col = $n - 1; $col > 0; $col -= 2) {
        if ($col === 6) $col = 5;
        for ($k = 0; $k < $n; $k++) {
            $r = $su ? $n - 1 - $k : $k;
            foreach ([0, 1] as $dc) {
                $c = $col - $dc;
                if ($fissa[$r][$c]) continue;
                $m[$r][$c] = $idx < $lunghezza ? (int)$flusso[$idx] : 0;
                $idx++;
            }
        }
        $su = !$su;
    }

    // 5. Maschera: si prova ognuna delle otto e si tiene quella con la penalita piu bassa (regole 1 e 2).
    $formato = [0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0]; // livello M, maschere 0-7
    $migliore = null; $minPen = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $mm = $m;
        for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) {
            if ($fissa[$r][$c]) continue;
            $x = match ($mask) {
                0 => ($r + $c) % 2 === 0, 1 => $r % 2 === 0, 2 => $c % 3 === 0, 3 => ($r + $c) % 3 === 0,
                4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0, 5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
                6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0, default => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
            };
            if ($x) $mm[$r][$c] ^= 1;
        }
        // Informazioni di formato
        $f = $formato[$mask];
        for ($i = 0; $i < 15; $i++) {
            $bit = ($f >> $i) & 1;
            // Copia 1: colonna 8 (righe 0-5, 7, 8) poi riga 8 (colonne 7..0 esclusa la 6)
            if ($i < 6) $mm[$i][8] = $bit;
            elseif ($i < 8) $mm[$i + 1][8] = $bit;
            elseif ($i === 8) $mm[8][7] = $bit;
            else $mm[8][14 - $i] = $bit;
            // Copia 2: riga 8 a destra (bit 0-7), colonna 8 in basso (bit 8-14)
            if ($i < 8) $mm[8][$n - 1 - $i] = $bit;
            else $mm[$n - 15 + $i][8] = $bit;
        }
        $pen = sicurezza_qr_penalita($mm, $n);
        if ($pen < $minPen) { $minPen = $pen; $migliore = $mm; }
    }
    return $migliore;
}

/** Penalita delle regole 1 (file di 5 o piu moduli uguali) e 2 (blocchi 2x2 uguali). */
function sicurezza_qr_penalita(array $m, int $n): int {
    $p = 0;
    for ($r = 0; $r < $n; $r++) {
        $cr = 1; $cc = 1;
        for ($c = 1; $c < $n; $c++) {
            if ($m[$r][$c] === $m[$r][$c - 1]) { $cr++; if ($cr === 5) $p += 3; elseif ($cr > 5) $p++; } else $cr = 1;
            if ($m[$c][$r] === $m[$c - 1][$r]) { $cc++; if ($cc === 5) $p += 3; elseif ($cc > 5) $p++; } else $cc = 1;
        }
    }
    for ($r = 0; $r < $n - 1; $r++) for ($c = 0; $c < $n - 1; $c++) {
        $x = $m[$r][$c];
        if ($x === $m[$r][$c + 1] && $x === $m[$r + 1][$c] && $x === $m[$r + 1][$c + 1]) $p += 3;
    }
    return $p;
}

/** Codici di Reed-Solomon ($quanti) per un blocco di dati, nel campo GF(256) con il polinomio 0x11D. */
function sicurezza_qr_rs(array $dati, int $quanti): array {
    static $exp = null, $log = null;
    if ($exp === null) {
        $exp = array_fill(0, 512, 0); $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) { $exp[$i] = $x; $log[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11D; }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    }
    $mul = fn(int $a, int $b) => ($a === 0 || $b === 0) ? 0 : $exp[$log[$a] + $log[$b]];
    // Polinomio generatore
    $gen = [1];
    for ($i = 0; $i < $quanti; $i++) {
        $nuovo = array_fill(0, count($gen) + 1, 0);
        foreach ($gen as $k => $g) { $nuovo[$k] ^= $g; $nuovo[$k + 1] ^= $mul($g, $exp[$i]); }
        $gen = $nuovo;
    }
    // Divisione
    $resto = array_merge($dati, array_fill(0, $quanti, 0));
    $nd = count($dati);
    for ($i = 0; $i < $nd; $i++) {
        $coef = $resto[$i];
        if ($coef === 0) continue;
        for ($j = 1; $j < count($gen); $j++) $resto[$i + $j] ^= $mul($gen[$j], $coef);
    }
    return array_slice($resto, $nd);
}
