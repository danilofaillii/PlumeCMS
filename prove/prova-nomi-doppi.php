<?php
// 2.2.0: i controlli dei widget che avevano lo stesso nome di uno delle Avanzate ("animazione" del Titolo animato, "padding" di
// Avviso, Flip box, Condividi e Icone social). Due modi:
//   php prove/prova-nomi-doppi.php <cartella CMS> pagina     crea /nomi-doppi/ con i cinque widget e i valori salvati come
//                                                           li salvava il CMS di prima (da lanciare sul CMS vecchio)
//   php prove/prova-nomi-doppi.php <cartella CMS> controlla  sul CMS nuovo: i valori vecchi passano ai nomi nuovi, e i due
//                                                           controlli ora tengono valori diversi
// Le foto della pagina prima e dopo (prove/foto-sito.js) le fa chi lancia la prova.
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$modo = $argv[2] ?? 'controlla';
$pad = fn(float $a) => ['d' => ['sopra' => $a, 'destra' => $a, 'sotto' => $a, 'sinistra' => $a, 'u' => 'px', 'legati' => true]];
$w = fn(string $t, array $imp) => ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [array_replace_recursive(editor_widget($t), ['impostazioni' => $imp])]];
$sez = fn(array $col) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => $col];
$pdo = db();
$idPagina = function () use ($pdo) { $st = $pdo->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute(['nomi-doppi']); return (int)$st->fetchColumn(); };
if ($modo === 'pagina') {
    $sezioni = [
        $sez([$w('titolo_animato', ['modo' => 'rotante', 'prima' => 'Un sito', 'rotanti' => "veloce\nchiaro", 'animazione' => 'gira'])]),
        $sez([$w('avviso', ['titolo' => 'Avviso di prova', 'testo' => 'Con lo spazio interno a 31 px.', 'padding' => $pad(31)])]),
        $sez([$w('flip_box', ['padding' => $pad(27)])]),
        $sez([$w('condividi', ['padding' => $pad(13)])]),
        $sez([$w('social', ['padding' => ['d' => ['v' => 17, 'u' => 'px']]])]),
    ];
    $id = $idPagina();
    if (!$id) { $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status) VALUES ('Nomi doppi', 'nomi-doppi', 'published')")->execute(); $id = (int)$pdo->lastInsertId(); }
    editor_salva($id, editor_valida(['elementi' => $sezioni]), true, 'prova', 'pagina di prova');
    echo "pagina $id\n";
    exit(0);
}
$n = 0; $ko = 0;
$ok = function (string $t, bool $c, $x = '') use (&$n, &$ko) { $n++; if (!$c) $ko++; echo ($c ? 'OK  ' : 'KO  ') . $t . ($x !== '' ? ' ' . substr(json_encode($x, JSON_UNESCAPED_UNICODE), 0, 300) : '') . "\n"; };
$st = $pdo->prepare('SELECT struttura FROM ' . tbl('pages') . ' WHERE id = ?'); $st->execute([$idPagina()]);
$albero = editor_decodifica((string)$st->fetchColumn());
$widget = [];
$giro = function (array $el) use (&$giro, &$widget) { foreach ($el as $x) { if (isset($x['figli'])) $giro($x['figli']); if (!in_array($x['tipo'] ?? '', ['sezione', 'colonna', 'contenitore'], true)) $widget[$x['tipo']] = $x['impostazioni'] ?? []; } };
$giro($albero['elementi'] ?? []);
$val = fn(string $t) => editor_valori($t, $widget[$t] ?? []);
// Il CMS di prima perdeva la scelta al salvataggio ("gira" diventava ""): il titolo usciva senza animazione delle parole.
// Ora vale quella di partenza, la scrittura a macchina; una scelta salvata da un CMS che la teneva passa al nome nuovo.
$ok('Titolo animato salvato dal CMS di prima: animazione delle parole di partenza (scrittura)', ($val('titolo_animato')['animazione_parole'] ?? '') === 'scrittura', $widget['titolo_animato'] ?? null);
$ok('Titolo animato: un "gira" salvato con il nome vecchio passa ad Animazione delle parole', (editor_valori('titolo_animato', ['modo' => 'rotante', 'animazione' => 'gira'])['animazione_parole'] ?? '') === 'gira');
$ok('Avviso: lo spazio interno di prima (31 px) passa al controllo suo', ($val('avviso')['avviso_padding']['d']['sopra'] ?? null) == 31, $val('avviso')['avviso_padding'] ?? null);
$ok('Flip box: lo spazio interno di prima (27 px) passa al controllo suo', ($val('flip_box')['flip_padding']['d']['sopra'] ?? null) == 27);
$ok('Condividi: lo spazio interno di prima (13 px) passa al controllo suo', ($val('condividi')['voce_padding']['d']['sopra'] ?? null) == 13);
// Ora i due controlli tengono valori diversi.
$imp = ['titolo' => 'x', 'avviso_padding' => $pad(9), 'padding' => $pad(41)];
$pul = editor_pulisci_impostazioni('avviso', $imp);
$ok('Avviso: spazio interno del riquadro e delle Avanzate restano diversi dopo il salvataggio', ($pul['avviso_padding']['d']['sopra'] ?? null) == 9 && ($pul['padding']['d']['sopra'] ?? null) == 41, $pul);
$imp = ['modo' => 'rotante', 'animazione_parole' => 'scorri', 'animazione' => 'dissolvenza'];
$pul = editor_pulisci_impostazioni('titolo_animato', $imp);
$ok('Titolo animato: Animazione delle parole e Animazione all\'ingresso restano diverse dopo il salvataggio', ($pul['animazione_parole'] ?? '') === 'scorri' && ($pul['animazione'] ?? '') === 'dissolvenza', $pul);
$alb = editor_valida(['elementi' => [$sez([$w('titolo_animato', ['modo' => 'rotante', 'rotanti' => "a\nb", 'animazione_parole' => 'scorri', 'animazione' => 'dissolvenza'])])]]);
$d = editor_disegna($alb, '/');
$html = is_array($d) ? implode(' ', array_map(fn($x) => is_string($x) ? $x : '', $d)) : (string)$d;
$ok('Titolo animato disegnato: parole con "scorri" e ingresso con "dissolvenza"', str_contains($html, 'ew-anim-scorri') && str_contains($html, 'ed-anima-dissolvenza'), substr($html, 0, 200));
$pul = editor_pulisci_impostazioni('social', ['icona_spazio' => ['d' => ['v' => 6, 'u' => 'px']], 'padding' => $pad(22)]);
$ok('Icone social: spazio intorno all\'icona e spazio interno delle Avanzate restano diversi', ($pul['icona_spazio']['d']['v'] ?? null) == 6 && ($pul['padding']['d']['sopra'] ?? null) == 22, $pul);
echo "\n" . ($n - $ko) . " su $n OK\n";
exit($ko ? 1 : 0);
