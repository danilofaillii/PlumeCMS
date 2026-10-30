<?php
/**
 * Aiuti da riga di comando per prova-password-dimenticata.js (1.11.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/password-cli.php <cartella CMS> <comando> [argomenti]
 *   token <utente>    un link di reimpostazione valido per quell'utente (come quello dell'email): stampa il token
 *   svuota            toglie i conteggi delle richieste di reimpostazione (reset-ip, reset-email)
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: password-cli.php <cartella CMS> <comando> [argomenti]\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/sicurezza.php';
sicurezza_schema();
switch ($cmd) {
    case 'token':
        $st = db()->prepare('SELECT id FROM ' . tbl('users') . ' WHERE username = ?'); $st->execute([(string)($arg[0] ?? '')]);
        $id = (int)$st->fetchColumn();
        if (!$id) { fwrite(STDERR, "utente non trovato\n"); exit(1); }
        echo sicurezza_token_crea('password', $id, 30), "\n";
        break;
    case 'svuota':
        echo db()->exec('DELETE FROM ' . tbl('sicurezza_tentativi') . " WHERE chiave LIKE 'reset-%'"), "\n";
        break;
    default:
        fwrite(STDERR, "comando sconosciuto: $cmd\n"); exit(2);
}
