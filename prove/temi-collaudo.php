<?php
// Crea i quattro temi di collaudo per prove/prova-temi.js nella cartella temi/ di un CMS installato (mai nel prodotto).
//   prova    completo: stile chiaro, header e footer suoi (con una scritta), CSS, un filtro PHP, la sua 404
//   rotto    una vista che lancia un errore
//   fatale   errore di sintassi in funzioni.php
//   mortale  ridefinisce una funzione del CMS: errore fatale che try/catch non prende
// uso: php temi-collaudo.php <cartella CMS installato>
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
$cms = rtrim($argv[1] ?? '', '/');
require $cms . '/includes/config.php';
require_once $cms . '/includes/modelli.php';
require_once $cms . '/includes/editor.php';
$t = $cms . '/temi';
$scrivi = function (string $f, string $testo) use ($t) {
    @mkdir(dirname("$t/$f"), 0755, true);
    file_put_contents("$t/$f", $testo);
};
$json = fn($x) => json_encode($x, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$h = modelli_albero_header_partenza();
$h['elementi'][] = editor_sezione([editor_widget('testo', ['contenuto' => '<p>Questo header viene dal tema Prova</p>'])]);
$scrivi('prova/modelli.json', $json(['header' => $h, 'footer' => modelli_albero_footer_partenza()]));
$scrivi('prova/tema.json', $json(['nome' => 'Prova', 'versione' => '0.1', 'autore' => 'Collaudo', 'descrizione' => 'Tema di collaudo: chiaro, rosso, header suo, 404 suo.',
    'css' => 'tema.css', 'stile' => 'stile.json', 'modelli' => 'modelli.json']));
$scrivi('prova/stile.json', $json([
    'colori' => ['sistema' => ['primario' => '#e11d48', 'secondario' => '#0d9488', 'testo' => '#1f2937', 'accento' => '#16a34a', 'sfondo' => '#ffffff'],
        'personalizzati' => [['id' => 'sfondo-2', 'nome' => 'Sfondo secondario', 'valore' => '#f3f4f6'], ['id' => 'testo-2', 'nome' => 'Testo attenuato', 'valore' => '#4b5563'],
            ['id' => 'testo-3', 'nome' => 'Testo discreto', 'valore' => '#6b7280'], ['id' => 'bordo', 'nome' => 'Bordi', 'valore' => 'rgba(0,0,0,.12)']]],
    'sfondo' => ['sfumature_sito' => false],
    'sito' => ['nome' => 'NON DEVE ENTRARE'],   // il tema non cambia l'identità del sito: deve essere ignorato
]));
$scrivi('prova/tema.css', ":root{--primario-chiaro:#ff8a00;--primario-scuro:#c2410c}\n.sez-titolo{letter-spacing:-.02em}\n");
$scrivi('prova/funzioni.php', "<?php\nreturn ['filtri' => ['llms_intestazione' => fn(\$righe) => is_array(\$righe) ? array_merge(\$righe, ['> Riga aggiunta dal tema Prova']) : \$righe]];\n");
$scrivi('prova/viste/404.php', "<?php\nreturn '<div class=\"wrap\" style=\"padding:96px 0\"><h1 class=\"sez-titolo\">404 del tema Prova</h1></div>';\n");

$scrivi('rotto/tema.json', $json(['nome' => 'Rotto', 'descrizione' => 'Una vista che lancia un errore.']));
$scrivi('rotto/viste/404.php', "<?php\nthrow new RuntimeException('errore voluto nella vista 404');\n");
$scrivi('fatale/tema.json', $json(['nome' => 'Fatale', 'descrizione' => 'Errore di sintassi in funzioni.php.']));
$scrivi('fatale/funzioni.php', "<?php\nreturn [ \"filtri\" => \n");
$scrivi('mortale/tema.json', $json(['nome' => 'Mortale', 'descrizione' => 'Ridefinisce una funzione del CMS: errore fatale che try/catch non prende.']));
$scrivi('mortale/funzioni.php', "<?php\nfunction h(\$s) { return \$s; }\nreturn [];\n");
echo "temi di collaudo scritti in $t\n";
