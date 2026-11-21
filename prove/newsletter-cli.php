<?php
/**
 * Aiuti da riga di comando per prova-newsletter-difetti.js (2.0.0), su un CMS installato. Mai nel prodotto.
 * Funziona anche su un CMS di prima della 2.0.0 (per vedere i difetti): dove una funzione manca, risponde null.
 * uso: php prove/newsletter-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"                       righe in JSON ({p} diventa il prefisso delle tabelle)
 *   giro                                un giro della newsletter (campagne e flussi), righe di log in JSON
 *   pulizia                             la pulizia del cron, subito
 *   flusso <nome> <innesco> <id> <oggetto>   un flusso attivo: innesco, poi un'email; stampa l'id
 *   campagna <liste> <oggetto>          una campagna programmata adesso per le liste ("1,2"); stampa l'id
 *   invia <quante>                      quante email di prova (tipo "prova") a prova@esempio.it
 *   senza_smtp <a>                      un'email HTML con mail() di PHP (SMTP tolto per la durata della chiamata)
 *   conferma <token>                    newsletter_conferma_esito(): l'esito in JSON
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: newsletter-cli.php <cartella CMS> <comando> [argomenti]\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
newsletter_schema();
newsletter_flussi_schema();
$out = null;
switch ($cmd) {
    case 'sql':
        $q = str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? ''));
        $st = db()->query($q);
        $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
        break;
    case 'giro':
        $out = newsletter_invia_giro();
        break;
    case 'pulizia':
        $out = function_exists('newsletter_pulizia') ? newsletter_pulizia(true) : null;
        break;
    case 'flusso':
        [$nome, $innesco, $id, $oggetto] = array_pad($arg, 4, '');
        $grafo = ['nodi' => [['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 40, 'dati' => []],
            ['id' => 1, 'tipo' => 'email', 'x' => 40, 'y' => 200, 'dati' => ['oggetto' => $oggetto, 'anteprima' => '', 'corpo' => '<p>Ciao {nome}, questa è l\'email del flusso.</p><p><a href="https://esempio.it/pagina">Leggi</a></p>', 'pulsante_testo' => '', 'pulsante_link' => '', 'ascolto' => 0]]],
            'archi' => [['da' => 0, 'uscita' => 'poi', 'a' => 1]]];
        $out = newsletter_flusso_salva(0, ['nome' => $nome, 'attivo' => 1, 'innesco' => $innesco, 'innesco_id' => (int)$id, 'grafo' => json_encode($grafo)]);
        break;
    case 'campagna':
        [$liste, $oggetto] = array_pad($arg, 2, '');
        $ora = date('Y-m-d H:i:s');
        db()->prepare('INSERT INTO ' . tbl('newsletter_campagne') . " (oggetto, anteprima, corpo, liste, stato, invio_at, creato, aggiornato) VALUES (?, '', ?, ?, 'programmata', ?, ?, ?)")
            ->execute([$oggetto, '<p>Ciao {nome}, ecco le novità.</p>', $liste, $ora, $ora, $ora]);
        $out = (int)db()->lastInsertId();
        break;
    case 'invia':
        $ok = 0;
        for ($i = 0; $i < (int)($arg[0] ?? 0); $i++) $ok += posta_invia('prova@esempio.it', 'Prova ' . ($i + 1), "Email di prova.\n", '', 'prova') ? 1 : 0;
        $out = $ok;
        break;
    case 'senza_smtp':
        $vecchia = (string)get_setting('posta_smtp', '');
        $c = json_decode($vecchia, true) ?: [];
        save_setting('posta_smtp', json_encode(['host' => ''] + $c));
        try {
            [$oggetto, $html, $intest] = newsletter_email(['id' => 0, 'oggetto' => 'Prova senza SMTP', 'anteprima' => '', 'corpo' => '<p>Testo della prova con <a href="https://esempio.it/x">un link</a>.</p>', 'pulsante_testo' => '', 'pulsante_link' => ''], ['id' => 0, 'nome' => 'Ada']);
            $out = posta_invia((string)($arg[0] ?? 'prova@esempio.it'), $oggetto, $html, $intest);
        } finally {
            save_setting('posta_smtp', $vecchia);
        }
        break;
    case 'conferma':
        $out = function_exists('newsletter_conferma_esito') ? newsletter_conferma_esito((string)($arg[0] ?? '')) : null;
        break;
    default:
        fwrite(STDERR, "comando sconosciuto: $cmd\n");
        exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
