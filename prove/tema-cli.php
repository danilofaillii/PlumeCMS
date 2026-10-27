<?php
// uso: php tema-cli.php <cartella CMS> elenco | attiva <nome> | disattiva | stato | ripristina-stile
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/temi.php';
switch ($argv[2]) {
    case 'elenco':
        foreach (temi_elenco() as $t) echo $t['slug'], ' | ', $t['nome'], ' | php=', $t['php'] ? 'si' : 'no', ' | viste=', implode(',', $t['viste']), ' | css=', $t['css'], ' | errori=', implode(' ', $t['errori']), "\n";
        break;
    case 'attiva': foreach (temi_attiva($argv[3], 'collaudo') as $r) echo "  $r\n"; break;
    case 'disattiva': temi_disattiva(); echo "nessun tema\n"; break;
    case 'ripristina-stile': design_ripristina(); echo "stile predefinito\n"; break;
    case 'stato':
        echo 'scelto=', temi_scelto(), ' attivo=', temi_attivo(), ' guasto=', json_encode(temi_guasto_info(), JSON_UNESCAPED_UNICODE), "\n";
        echo 'modelli creati=', json_encode(temi_modelli_creati()), "\n";
        echo 'nome del sito=', design_sito()['nome'], ' | primario=', design_valori(true)['colori']['sistema']['primario'], "\n";
        $n = db()->query('SELECT COUNT(*) FROM ' . tbl('modelli'))->fetchColumn(); echo "modelli nel database=$n\n";
        break;
}
