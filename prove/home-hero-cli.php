<?php
/**
 * La hero della home e il pacchetto del sito di plumeCMS (2.18.0, regola di Danilo: "ogni volta che aggiorniamo la versione
 * lascia la hero dell'home page così com'è"). Usato da prove/prova-hero-home.sh.
 *   php prove/home-hero-cli.php <cartella CMS> cambia "<titolo>"   cambia il titolo dell'Apertura della home, come dal pannello
 *   php prove/home-hero-cli.php <cartella CMS> leggi              stampa {titolo, sezioni, seconda}: titolo dell'Apertura, numero
 *                                                                 di sezioni e titolo della seconda sezione
 */
if (PHP_SAPI !== 'cli') exit;
[, $dir, $cmd] = $argv + [null, null, null];
require $dir . '/includes/config.php';
require_once $dir . '/includes/editor-controlli.php';
require_once $dir . '/includes/editor.php';
editor_carica_widget_esterni();
$id = (int)db()->query('SELECT id FROM ' . tbl('pages') . " WHERE slug = 'home'")->fetchColumn();
$r = editor_pagina($id);
$albero = json_decode((string)($r['struttura'] ?? ''), true);
$trova = function (array $el, string $tipo) use (&$trova) {
    if (($el['tipo'] ?? '') === $tipo) return $el;
    foreach ((array)($el['figli'] ?? []) as $f) if (is_array($f) && ($x = $trova($f, $tipo))) return $x;
    return null;
};
$cambia = function (array &$el, string $titolo) use (&$cambia): bool {
    if (($el['tipo'] ?? '') === 'hero') { $el['impostazioni']['titolo'] = $titolo; return true; }
    foreach ($el['figli'] ?? [] as $k => $f) if (is_array($f) && $cambia($el['figli'][$k], $titolo)) return true;
    return false;
};
if ($cmd === 'cambia') {
    $cambia($albero['elementi'][0], (string)($argv[3] ?? ''));
    editor_salva($id, $albero, true, 'prova', 'titolo della hero cambiato a mano');
    echo json_encode(['ok' => true]), "\n";
} else {
    $h = $trova($albero['elementi'][0] ?? [], 'hero');
    $t2 = $trova($albero['elementi'][1] ?? [], 'titolo');
    echo json_encode(['titolo' => $h['impostazioni']['titolo'] ?? null, 'sezioni' => count($albero['elementi'] ?? []), 'seconda' => $t2['impostazioni']['titolo'] ?? null], JSON_UNESCAPED_UNICODE), "\n";
}
