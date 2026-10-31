<?php
// Cambia i colori globali dello Stile del sito (o li riporta ai predefiniti). uso: php colori.php <cartella CMS> chiaro|ripristina
$_SERVER['HTTP_HOST'] = '127.0.0.1:8099'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/design.php';
if ($argv[2] === 'ripristina') { design_ripristina(); echo "predefiniti\n"; exit; }
$v = design_valori();
$v['colori']['sistema'] = ['primario' => '#e11d48', 'secondario' => '#0d9488', 'testo' => '#1f2937', 'accento' => '#16a34a', 'sfondo' => '#ffffff'];
foreach ($v['colori']['personalizzati'] as &$c) $c['valore'] = ['sfondo-2' => '#f3f4f6', 'testo-2' => '#4b5563', 'testo-3' => '#6b7280', 'bordo' => 'rgba(0,0,0,.12)'][$c['id']] ?? $c['valore'];
design_salva($v, 'prova', 'prova colori chiari');
echo "chiaro\n";
