<?php
// I controlli dei widget che si chiamavano come uno delle Avanzate (titolo_animato.animazione, padding di Icone social,
// Condividi, Avviso e Flip box) o come un altro controllo dello stesso widget (carosello.spazio) hanno un nome loro dalla
// 1.3.0 (albero versione 2). Si prova: nessun nome doppio in nessun elemento; i valori nuovi restano dopo editor_valida e
// finiscono nel CSS giusto; gli alberi con versione 1 si disegnano come prima (stesse classi, stesso CSS).
// Uso: php prova-nomi-doppi.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1:8099'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$n = 0; $ko = 0;
function ok(string $t, bool $c, $extra = ''): void { global $n, $ko; $n++; if (!$c) $ko++; echo ($c ? 'OK  ' : 'KO  ') . $t . ($extra !== '' ? ' ' . substr(json_encode($extra), 0, 240) : '') . "\n"; }
/** [impostazioni salvate, html del widget, css del widget] di un widget passato da editor_valida (versione: null = codice di oggi). */
function prova(string $tipo, array $imp, ?int $versione = null): array {
    $al = ['elementi' => [['id' => 'provas', 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => 'provac', 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [['id' => 'provaw', 'tipo' => $tipo, 'impostazioni' => $imp]]]]]]];
    if ($versione !== null) $al['versione'] = $versione;
    $a = editor_valida($al);
    [$h, $css] = editor_disegna($a, '/');
    preg_match_all('/\.ed-provaw[^{]*\{[^}]*\}/', $css, $m);
    return [$a['elementi'][0]['figli'][0]['figli'][0]['impostazioni'], $h, implode('', $m[0]), $a['versione']];
}
$lati = fn(int $x) => ['d' => ['u' => 'px', 'legati' => true, 'sopra' => $x, 'destra' => $x, 'sotto' => $x, 'sinistra' => $x]];
$parole = ['modo' => 'rotante', 'prima' => 'Un', 'rotanti' => "a\nb", 'dopo' => 'x'];

// Nessun nome doppio
$doppi = [];
foreach (editor_definizioni() as $t => $d) foreach (array_count_values(array_column($d['controlli'], 'nome')) as $nome => $k) if ($k > 1) $doppi[] = "$t.$nome";
ok('nessun elemento ha due controlli con lo stesso nome', !$doppi, $doppi);
ok('editor_valida scrive la versione 2', prova('testo', [])[3] === 2 && EDITOR_VERSIONE === 2);

// Titolo animato
foreach (['scrittura', 'scorri', 'dissolvi', 'zoom', 'gira'] as $an) {
    [$s, $h] = prova('titolo_animato', $parole + ['animazione_parole' => $an]);
    ok("titolo_animato: parole \"$an\" restano, senza animazione all'ingresso", ($s['animazione_parole'] ?? '') === $an && str_contains($h, "ew-anim-$an\"") && !str_contains($h, 'ed-anima'), $s);
}
[$s, $h] = prova('titolo_animato', $parole + ['animazione_parole' => 'gira', 'animazione' => 'dal_basso']);
ok('titolo_animato: parole Gira e ingresso Dal basso insieme', str_contains($h, 'ew-anim-gira"') && str_contains($h, 'ed-anima-dal_basso'), $s);
[$s, $h] = prova('titolo_animato', $parole);
ok('titolo_animato nuovo: parole Scrittura a macchina (il predefinito che il pannello mostra)', str_contains($h, 'ew-anim-scrittura"'));
[$s, $h] = prova('titolo_animato', $parole + ['animazione' => 'zoom']);
ok('titolo_animato nuovo con ingresso Zoom: le parole non lo copiano', str_contains($h, 'ew-anim-scrittura"') && str_contains($h, 'ed-anima-zoom') && !isset($s['animazione_parole']), $s);

// Titolo animato salvato con la versione 1: come prima
foreach (['' => 'dissolvi', 'zoom' => 'zoom', 'dal_basso' => 'dissolvi', 'scrittura' => 'dissolvi', null => 'dissolvi'] as $vecchia => $attesa) {
    $imp = $vecchia === null ? $parole : $parole + ['animazione' => (string)$vecchia];
    [$s, $h] = prova('titolo_animato', $imp, 1);
    $ingresso = in_array($vecchia, ['zoom', 'dal_basso'], true) ? 'ed-anima-' . $vecchia : '';
    ok('titolo_animato versione 1, animazione ' . json_encode($vecchia) . ": parole $attesa" . ($ingresso ? ", ingresso $vecchia" : ', senza ingresso'),
        ($s['animazione_parole'] ?? '') === $attesa && str_contains($h, "ew-anim-$attesa\"") && ($ingresso ? str_contains($h, $ingresso) : !str_contains($h, 'ed-anima')), $s);
}
[$s] = prova('titolo_animato', $parole + ['animazione' => 'zoom', 'animazione_parole' => 'gira'], 1);
ok('versione 1 con animazione_parole gia scritta: resta quella', $s['animazione_parole'] === 'gira', $s);

// Condividi, Avviso, Flip box
foreach (['condividi' => ['pulsanti_padding', '.ew-cond-voce'], 'avviso' => ['avviso_padding', '.ew-avviso'], 'flip_box' => ['contenuto_padding', '.ew-flip-contenuto']] as $t => [$nome, $dentro]) {
    [$s, , $css] = prova($t, [$nome => $lati(20)]);
    ok("$t: $nome a 20 px solo dentro ($dentro)", str_contains($css, ".ed-provaw $dentro{padding:20px 20px 20px 20px}") && !preg_match('/\.ed-provaw\{[^}]*padding:/', $css), $css);
    [$s, , $css] = prova($t, ['padding' => $lati(30)]);
    ok("$t: padding delle Avanzate a 30 px solo sul widget", preg_match('/\.ed-provaw\{[^}]*padding:30px/', $css) && !str_contains($css, " $dentro{padding"), $css);
    [$s, , $css] = prova($t, ['padding' => $lati(30)], 1);
    ok("$t versione 1 con padding 30: ancora sul widget e dentro, come prima", ($s[$nome] ?? null) == $lati(30) && preg_match('/\.ed-provaw\{[^}]*padding:30px/', $css) && str_contains($css, ".ed-provaw $dentro{padding:30px 30px 30px 30px}"), $css);
    [$s] = prova($t, ['padding' => $lati(30), $nome => $lati(8)], 1);
    ok("$t versione 1 con $nome gia scritto: resta quello", $s[$nome] == $lati(8), $s);
}

// Icone social
[$s, , $css] = prova('social', ['icona_padding' => ['d' => ['v' => 16, 'u' => 'px']]]);
ok('social: icona_padding 16 px diventa --ew-pad (prima si perdeva)', str_contains($css, '--ew-pad:16px') && !preg_match('/\.ed-provaw\{[^}]*padding:/', $css), $css);
[$s, , $css] = prova('social', ['padding' => $lati(5)], 1);
ok('social versione 1 con il padding delle Avanzate: sul widget, icone a 10 px come prima', preg_match('/\.ed-provaw\{[^}]*padding:5px/', $css) && str_contains($css, '--ew-pad:10px') && !isset($s['icona_padding']), $css);

// Carosello di immagini
$def = array_values(array_filter(editor_definizioni()['carosello']['controlli'], fn($c) => $c['nome'] === 'spazio'));
ok('carosello: un solo "spazio", Spazio fra le immagini a 12 px', count($def) === 1 && $def[0]['etichetta'] === 'Spazio fra le immagini' && $def[0]['predefinito']['d']['v'] === 12, $def);
$foto = [['immagine' => '/a.png', 'alt' => 'a']];
[, , $css] = prova('carosello', ['immagini' => $foto]);
ok('carosello senza spazio: --ew-gap 12px come prima', str_contains($css, '--ew-gap:12px') && substr_count($css, '--ew-gap') === 1, $css);
[, , $css] = prova('carosello', ['immagini' => $foto, 'spazio' => ['d' => ['v' => 30, 'u' => 'px']]], 1);
ok('carosello versione 1 con spazio 30: --ew-gap 30px come prima', str_contains($css, '--ew-gap:30px') && substr_count($css, '--ew-gap') === 1, $css);
$t = array_values(array_filter(editor_definizioni()['carosello_testimonianze']['controlli'], fn($c) => $c['nome'] === 'spazio'));
ok('carosello di testimonianze: Spazio fra gli elementi a 16 px resta', count($t) === 1 && $t[0]['predefinito']['d']['v'] === 16, $t);

echo "\n" . ($n - $ko) . " su $n OK\n";
exit($ko ? 1 : 0);
