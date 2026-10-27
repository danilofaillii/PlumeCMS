<?php
// uso: php tema-cli.php <cartella CMS> elenco | attiva <nome> | disattiva | stato | ripristina-stile | redattore <password>
//      | finestra apri|chiudi | chiave | file
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/temi.php';
switch ($argv[2]) {
    case 'elenco':
        foreach (temi_elenco() as $t) echo $t['slug'], ' | ', $t['nome'], ' | php=', $t['php'] ? 'si' : 'no', ' | viste=', implode(',', $t['viste']), ' | css=', $t['css'], ' | errori=', implode(' ', $t['errori']), "\n";
        break;
    case 'attiva': foreach (temi_attiva($argv[3], 'collaudo') as $r) echo "  $r\n"; break;
    case 'disattiva': temi_disattiva(); echo "nessun tema\n"; break;
    case 'redattore':
        // Un utente con il ruolo di redattore per le prove dei permessi (redattore-prova), con la password data.
        require_once $argv[1] . '/includes/sicurezza.php';
        db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute(['redattore-prova']);
        db()->prepare('INSERT INTO ' . tbl('users') . ' (username, email, display_name, role, password_hash) VALUES (?,?,?,?,?)')
            ->execute(['redattore-prova', 'redattore@esempio.it', 'Redattore di prova', 'editor', sicurezza_password_hash($argv[3])]);
        echo "redattore-prova creato\n";
        break;
    case 'finestra':
        // La finestra di un'ora di Sicurezza per il PHP non firmato: apri | chiudi.
        require_once $argv[1] . '/includes/sicurezza.php';
        sicurezza_salva_cfg(['root_php_fino' => $argv[3] === 'apri' ? time() + 3600 : 0]);
        echo "finestra {$argv[3]}\n";
        break;
    case 'chiave':
        // La chiave di firma dei pacchetti del sito di prova, per tools/pacchetto.py --rifirma (la legge lo script di prova, non si stampa altrove).
        require_once $argv[1] . '/includes/sicurezza.php';
        echo sicurezza_chiave_pacchetti();
        break;
    case 'file':
        // I file di temi/ (anche nascosti) e le copie in aggiornamenti/temi-precedenti, uno per riga.
        foreach ([temi_dir(), temi_cartella_copie()] as $d) foreach (glob($d . '/{,.}*', GLOB_BRACE) ?: [] as $x) if (!in_array(basename($x), ['.', '..'], true)) echo substr($x, strlen($argv[1]) + 1), "\n";
        break;
    case 'ripristina-stile': design_ripristina(); echo "stile predefinito\n"; break;
    case 'stato':
        echo 'scelto=', temi_scelto(), ' attivo=', temi_attivo(), ' guasto=', json_encode(temi_guasto_info(), JSON_UNESCAPED_UNICODE), "\n";
        echo 'modelli creati=', json_encode(temi_modelli_creati()), "\n";
        echo 'nome del sito=', design_sito()['nome'], ' | primario=', design_valori(true)['colori']['sistema']['primario'], "\n";
        $n = db()->query('SELECT COUNT(*) FROM ' . tbl('modelli'))->fetchColumn(); echo "modelli nel database=$n\n";
        break;
}
