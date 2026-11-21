<?php
/**
 * Aiuti da riga di comando per prova-consegna-email.js e attacchi-consegna.js (2.1.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/consegna-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"                righe in JSON ({p} diventa il prefisso delle tabelle)
 *   invia <a> [tipo]             un'email HTML con un allegato, da posta_invia(); stampa [ok, esito, errore]
 *   sigv4                        i due vettori di prova di AWS (get-vanilla, get-vanilla-query-order-key-case)
 *   rif <c> <f> <p> <i>          il riferimento firmato di un'email
 *   uscita <i> <c>               il link di uscita firmato con la campagna
 *   webhook <servizio>           l'indirizzo del webhook
 *   riscaldamento <giorni fa>    lo stato del riscaldamento come se fosse partito tanti giorni fa
 *   giro                         un giro della newsletter
 *   impostazione <chiave>        il valore grezzo di un'impostazione (per vedere che le chiavi sono cifrate)
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: consegna-cli.php <cartella CMS> <comando> [argomenti]\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
newsletter_schema();
newsletter_flussi_schema();
consegna_schema();
$out = null;
switch ($cmd) {
    case 'sql':
        $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
        $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
        break;
    case 'invia':
        $ok = posta_invia((string)$arg[0], 'Prova dalla riga di comando', '<p>Ciao, <b>prova</b>.</p>', moduli_intestazioni(), (string)($arg[1] ?? 'prova'),
            [['nome' => 'evento.ics', 'tipo' => 'text/calendar; method=REQUEST', 'dati' => "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n"]]);
        $out = [$ok, posta_esito(), posta_errore()];
        break;
    case 'sigv4':
        $t = gmmktime(12, 36, 0, 8, 30, 2015);
        $k = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';
        $out = [consegna_sigv4('GET', 'https://example.amazonaws.com/', '', 'us-east-1', 'service', 'AKIDEXAMPLE', $k, [], $t)['Authorization'],
            consegna_sigv4('GET', 'https://example.amazonaws.com/?Param2=value2&Param1=value1', '', 'us-east-1', 'service', 'AKIDEXAMPLE', $k, [], $t)['Authorization']];
        break;
    case 'rif':
        $out = consegna_rif((int)$arg[0], (int)$arg[1], (int)$arg[2], (int)$arg[3]);
        break;
    case 'uscita':
        $out = newsletter_link_uscita((int)$arg[0], ['c' => (int)$arg[1]]);
        break;
    case 'webhook':
        $out = consegna_webhook_url((string)$arg[0]);
        break;
    case 'riscaldamento':
        $out = consegna_riscaldamento_stato(time(), date('Y-m-d', time() - (int)$arg[0] * 86400));
        break;
    case 'giro':
        $out = newsletter_invia_giro();
        break;
    case 'impostazione':
        $out = get_setting((string)$arg[0], null);
        break;
    default:
        fwrite(STDERR, "comando sconosciuto: $cmd\n");
        exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
