<?php
// I sette widget della 1.10.0 disegnati con valori cattivi in ogni campo di testo (per tools/sicurezza-prova.js, gruppo 26).
// Passano da editor_valida come dall'editor vero. Stampa l'HTML del sito.  uso: php prove/widget-cattivi.php <cartella CMS>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
editor_carica_widget_esterni();
$x = '"><script>alert(1)</script><img src=x onerror=alert(2)>';
$j = 'javascript:alert(3)';
$cattivi = [
    'orari' => ['lun' => $x, 'mar' => '9-13' . $x, 'titolo' => $x, 'testo_aperto' => $x, 'testo_chiuso' => $x, 'chiusure' => [['dal' => date('Y-m-d'), 'al' => '', 'motivo' => $x]]],
    'barra_contatti' => ['telefono' => $x, 'etichetta_telefono' => $x, 'whatsapp' => '39' . $x, 'messaggio' => $x, 'indirizzo' => $x, 'etichetta_indirizzo' => $x, 'email' => 'a@b.it' . $x, 'etichetta_email' => $x],
    'recensioni' => ['media' => $x, 'totale' => $x, 'fonte' => $x, 'voci' => [['testo' => $x, 'nome' => $x, 'stelle' => '9' . $x, 'quando' => $x]], 'pulsante_testo' => $x, 'pulsante_link' => ['url' => $j, 'nuova' => true]],
    'prima_dopo' => ['immagine_prima' => $j, 'immagine_dopo' => '/x.png' . $x, 'etichetta_prima' => $x, 'etichetta_dopo' => $x, 'alt' => $x, 'partenza' => '50' . $x],
    'squadra' => ['voci' => [['immagine' => $j, 'nome' => $x, 'ruolo' => $x, 'testo' => $x, 'email' => $x, 'telefono' => $x, 'linkedin' => $j, 'instagram' => 'https://x.it/' . $x, 'facebook' => $x, 'sito' => 'data:text/html,<script>alert(4)</script>']]],
    'slider' => ['voci' => [['immagine' => $j, 'occhiello' => $x, 'titolo' => $x, 'testo' => $x, 'pulsante' => $x, 'link' => $j]]],
    'eventi' => ['voci' => [['data' => date('Y-m-d', strtotime('+3 days')), 'ora' => $x, 'titolo' => $x, 'luogo' => $x, 'testo' => $x, 'prezzo' => $x, 'immagine' => $j, 'link' => $j], ['data' => $x, 'titolo' => 'data sbagliata']]],
];
// Una sezione per widget: una sezione ha al massimo sei colonne.
$albero = ['elementi' => array_map(fn($t) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => $t, 'impostazioni' => $cattivi[$t]]]]]], array_keys($cattivi))];
$v = editor_valida($albero);
$r = editor_disegna($v, '/prova/'); echo is_array($r) ? ($r['html'] ?? ($r[0] ?? '')) : $r;
