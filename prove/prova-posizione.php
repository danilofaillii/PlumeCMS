<?php
// La Posizione delle Avanzate (posizione_css) non cancella piu la Posizione dei riquadri e l'Angolo del pulsante fisso,
// e i salvataggi di prima della 1.3.0 (posizione: absolute o fixed) restano come erano. Uso: php prova-posizione.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1:8099'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$n = 0; $ko = 0;
function ok(string $t, bool $c, $extra = ''): void { global $n, $ko; $n++; if (!$c) $ko++; echo ($c ? 'OK  ' : 'KO  ') . $t . ($extra !== '' ? ' ' . substr(json_encode($extra), 0, 200) : '') . "\n"; }
function imp(string $tipo, array $i): array { return editor_pulisci_impostazioni($tipo, $i); }
function disegna(string $tipo, array $i): array {
    $w = editor_widget($tipo, $i); $w['id'] = 'provaw';
    $a = editor_valida(['elementi' => [['id' => 'provas', 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => 'provac', 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]]]]);
    [$h, $css] = editor_disegna($a, '/');
    $w = $a['elementi'][0]['figli'][0]['figli'][0];
    return [$h, $css, (bool)preg_match('/\.ed-' . $w['id'] . '\{[^}]*position:(absolute|fixed)/', $css, $m) ? $m[1] : ''];
}

// La scelta del widget resta
foreach ([['riquadro_icona', 'sinistra'], ['riquadro_icona', 'destra'], ['riquadro_immagine', 'sinistra'], ['riquadro_immagine', 'destra'], ['pulsante_fisso', 'sinistra']] as [$t, $p]) {
    $x = imp($t, ['posizione' => $p]);
    ok("$t: posizione $p resta dopo la pulizia", ($x['posizione'] ?? null) === $p, $x['posizione'] ?? null);
}
[$h, , $pos] = disegna('riquadro_icona', ['posizione' => 'sinistra']);
ok('riquadro_icona a sinistra: classe ew-pos-sinistra, nessuna posizione CSS', str_contains($h, 'ew-pos-sinistra') && $pos === '');
[$h] = disegna('pulsante_fisso', ['posizione' => 'sinistra']);
ok('pulsante_fisso: classe ew-fisso-sinistra', str_contains($h, 'ew-fisso-sinistra'));

// Le due posizioni insieme
$x = imp('riquadro_icona', ['posizione' => 'destra', 'posizione_css' => 'absolute']);
ok('riquadro_icona: posizione destra e posizione_css absolute insieme', ($x['posizione'] ?? '') === 'destra' && ($x['posizione_css'] ?? '') === 'absolute', $x);
[$h, , $pos] = disegna('riquadro_icona', ['posizione' => 'destra', 'posizione_css' => 'absolute']);
ok('riquadro_icona: ew-pos-destra e position:absolute', str_contains($h, 'ew-pos-destra') && $pos === 'absolute');
$x = imp('riquadro_icona', ['posizione_css' => 'sinistra']);
ok('posizione_css fuori elenco torna Normale', ($x['posizione_css'] ?? null) === '', $x);

// Salvataggi di prima della 1.3.0
foreach ([['riquadro_icona', 'absolute'], ['riquadro_immagine', 'fixed'], ['pulsante_fisso', 'absolute'], ['testo', 'absolute'], ['titolo', 'fixed']] as [$t, $p]) {
    $x = imp($t, ['posizione' => $p]);
    ok("$t vecchio con posizione $p: passa in posizione_css, posizione torna libera", ($x['posizione_css'] ?? null) === $p && !isset($x['posizione']), $x);
    [, , $pos] = disegna($t, ['posizione' => $p]);
    ok("$t vecchio con posizione $p: il CSS ha ancora position:$p", $pos === $p);
}
[$h] = disegna('riquadro_icona', ['posizione' => 'absolute']);
ok('riquadro_icona vecchio absolute: icona sopra come prima', str_contains($h, 'ew-pos-sopra'));
$x = imp('testo', ['posizione' => 'absolute', 'posizione_css' => '']);
ok('posizione_css gia scelta (Normale) vince sul vecchio absolute', ($x['posizione_css'] ?? null) === '' && !isset($x['posizione']), $x);
[, , $pos] = disegna('testo', ['posizione' => 'absolute', 'posizione_css' => '']);
ok('... e nel CSS non c\'e position', $pos === '');
$x = imp('sezione', ['posizione' => 'absolute']);
ok('sezione: nessuna posizione_css (le Avanzate di sezione e colonna non ce l\'hanno)', !isset($x['posizione_css']), $x);

// I controlli nel pannello: le condizioni di Spostamento orizzontale e verticale guardano posizione_css
$def = editor_definizioni()['riquadro_icona']['controlli'];
$nomi = array_count_values(array_column($def, 'nome'));
ok('riquadro_icona: nessun nome di controllo ripetuto', !array_filter($nomi, fn($c) => $c > 1), array_keys(array_filter($nomi, fn($c) => $c > 1)));
$cond = array_values(array_filter($def, fn($c) => $c['nome'] === 'posizione_x'))[0]['condizione'] ?? [];
ok('posizione_x compare con posizione_css diversa da Normale', $cond === ['!posizione_css' => '']);
$doppi = [];
foreach (editor_definizioni() as $t => $d) if (count(array_keys(array_column($d['controlli'], 'nome'), 'posizione')) > 1) $doppi[] = $t;
ok('nessun widget ha due controlli "posizione"', !$doppi, $doppi);

echo "\n" . ($n - $ko) . " su $n OK\n";
exit($ko ? 1 : 0);
