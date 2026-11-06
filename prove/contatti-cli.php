<?php
/**
 * Aiuti da riga di comando per prova-contatti-brevo.js (2.8.0), su un CMS installato. Mai nel prodotto.
 * Funziona anche su un CMS 2.7.0 (per preparare il sito da aggiornare).
 * uso: php prove/contatti-cli.php <cartella CMS> <comando> [argomenti]
 *   semina                 contatti in tutti e cinque gli stati di prima, tre liste, una campagna inviata con aperture e
 *                          clic, una campagna programmata, un flusso con un cammino in corso, un invio di un form
 *   conta                  i conteggi che non devono cambiare con l'aggiornamento (JSON)
 *   sql "<query>"          righe in JSON ({p} diventa il prefisso)
 *   giro                   un giro della newsletter
 *   email <campagna> <id>  l'email della campagna per quel contatto: [oggetto, html] in JSON
 *   pulisci                toglie i dati della prova (contatti con @prova-contatti.it, liste e campagne "PC ")
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: contatti-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
newsletter_schema();
newsletter_flussi_schema();
$t = fn($n) => tbl($n);
$out = null;
switch ($cmd) {
    case 'semina':
        $ora = date('Y-m-d H:i:s');
        $ieri = date('Y-m-d H:i:s', time() - 86400);
        $liste = [];
        foreach (['PC Clienti', 'PC Eventi', 'PC Vuota'] as $l) {
            db()->prepare('INSERT IGNORE INTO ' . $t('newsletter_liste') . ' (nome, descrizione, creato) VALUES (?, ?, NOW())')->execute([$l, 'lista di prova']);
            $st = db()->prepare('SELECT id FROM ' . $t('newsletter_liste') . ' WHERE nome = ?'); $st->execute([$l]); $liste[$l] = (int)$st->fetchColumn();
        }
        $persone = [
            ['attivo1', 'attivo', 'Anna', 'Rossi', 'modulo'], ['attivo2', 'attivo', 'Bruno', 'Verdi', 'import'], ['attivo3', 'attivo', 'Carla', 'Bianchi', 'form'],
            ['attesa1', 'in_attesa', 'Dario', '', 'modulo'], ['uscito1', 'uscito', 'Elena', 'Neri', 'modulo'], ['rimbalzato1', 'rimbalzato', 'Fabio', '', 'import'],
            ['spam1', 'spam', 'Gina', '', 'form'], ['errori1', 'attivo', 'Ivo', '', 'modulo'],
        ];
        $ids = [];
        foreach ($persone as [$e, $stato, $n, $c, $fonte]) {
            $email = "$e@prova-contatti.it";
            db()->prepare('INSERT IGNORE INTO ' . $t('newsletter_iscritti') . ' (email, nome, cognome, stato, fonte, consenso_versione, consenso_at, uscito_at, rimbalzato_at, ip, errori_di_fila, creato, aggiornato)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$email, $n, $c, $stato, $fonte, $fonte === 'import' ? 'import' : 'modulo-1', $stato === 'in_attesa' ? null : $ieri,
                in_array($stato, ['uscito', 'spam'], true) ? $ieri : null, $stato === 'rimbalzato' ? $ieri : null, '10.0.0.1', $e === 'errori1' ? 3 : 0, $ieri, $ieri]);
            $ids[$e] = newsletter_id_da_email($email);
        }
        foreach (['attivo1', 'attivo2', 'uscito1', 'rimbalzato1', 'errori1'] as $e) newsletter_in_lista($ids[$e], $liste['PC Clienti'], false);
        foreach (['attivo1', 'attivo3', 'spam1'] as $e) newsletter_in_lista($ids[$e], $liste['PC Eventi'], false);
        // Una campagna inviata con coda ed eventi, una programmata.
        db()->prepare('INSERT INTO ' . $t('newsletter_campagne') . " (oggetto, anteprima, corpo, stato, destinatari, inviate, liste, creato, aggiornato) VALUES ('PC Campagna inviata', '', '<p>Ciao {nome}</p>', 'inviata', 3, 3, ?, ?, ?)")
            ->execute([(string)$liste['PC Clienti'], $ieri, $ieri]);
        $c1 = (int)db()->lastInsertId();
        foreach (['attivo1', 'attivo2', 'errori1'] as $e) db()->prepare('INSERT INTO ' . $t('newsletter_coda') . " (campagna_id, iscritto_id, stato, inviata_at) VALUES (?, ?, 'inviata', ?)")->execute([$c1, $ids[$e], $ieri]);
        db()->prepare('INSERT INTO ' . $t('newsletter_eventi') . " (campagna_id, iscritto_id, tipo, url, creato) VALUES (?, ?, 'apertura', '', ?), (?, ?, 'clic', 'https://esempio.it/offerta', ?)")
            ->execute([$c1, $ids['attivo1'], $ieri, $c1, $ids['attivo1'], $ieri]);
        db()->prepare('INSERT INTO ' . $t('newsletter_campagne') . " (oggetto, anteprima, corpo, stato, invio_at, liste, creato, aggiornato) VALUES ('PC Campagna programmata', '', '<p>Ciao {nome}</p>', 'programmata', DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, ?)")
            ->execute([(string)$liste['PC Eventi'], $ora, $ora]);
        // Un flusso con un cammino in corso (attesa lunga).
        $grafo = ['nodi' => [['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 40, 'dati' => []], ['id' => 1, 'tipo' => 'attesa', 'x' => 40, 'y' => 200, 'dati' => ['minuti' => 60 * 24 * 30]]], 'archi' => [['da' => 0, 'uscita' => 'poi', 'a' => 1]]];
        db()->prepare('INSERT INTO ' . $t('newsletter_flussi') . " (nome, attivo, innesco, innesco_id, passi, creato, aggiornato) VALUES ('PC Flusso', 1, 'lista', ?, ?, NOW(), NOW())")->execute([$liste['PC Clienti'], json_encode($grafo)]);
        $f = (int)db()->lastInsertId();
        db()->prepare('INSERT INTO ' . $t('newsletter_corsi') . " (flusso_id, iscritto_id, passo, prossimo, stato, creato, aggiornato) VALUES (?, ?, 1, DATE_ADD(NOW(), INTERVAL 30 DAY), 'attivo', NOW(), NOW())")->execute([$f, $ids['attivo1']]);
        $out = ['liste' => $liste, 'contatti' => $ids, 'campagna' => $c1, 'flusso' => $f];
        break;
    case 'conta':
        $q = fn($sql) => db()->query(str_replace('{p}', DB_PREFIX, $sql))->fetchAll(PDO::FETCH_KEY_PAIR);
        $uno = fn($sql) => (int)db()->query(str_replace('{p}', DB_PREFIX, $sql))->fetchColumn();
        $out = [
            'stati' => $q("SELECT stato, COUNT(*) FROM {p}newsletter_iscritti WHERE email LIKE '%@prova-contatti.it' GROUP BY stato ORDER BY stato"),
            'liste' => $q("SELECT l.nome, COUNT(il.iscritto_id) FROM {p}newsletter_liste l LEFT JOIN {p}newsletter_iscritti_liste il ON il.lista_id = l.id GROUP BY l.id ORDER BY l.nome"),
            'iscritti' => $uno('SELECT COUNT(*) FROM {p}newsletter_iscritti'),
            'appartenenze' => $uno('SELECT COUNT(*) FROM {p}newsletter_iscritti_liste'),
            'campagne' => $q('SELECT oggetto, stato FROM {p}newsletter_campagne ORDER BY id'),
            'coda' => $uno('SELECT COUNT(*) FROM {p}newsletter_coda'),
            'eventi' => $uno('SELECT COUNT(*) FROM {p}newsletter_eventi'),
            'flussi' => $q('SELECT nome, attivo FROM {p}newsletter_flussi ORDER BY id'),
            'corsi' => $q("SELECT CONCAT(flusso_id, '-', iscritto_id), stato FROM {p}newsletter_corsi ORDER BY id"),
            'form' => $uno('SELECT COUNT(*) FROM {p}moduli'),
        ];
        break;
    case 'sql':
        $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
        $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
        break;
    case 'giro':
        $out = newsletter_invia_giro();
        break;
    case 'email':
        $c = newsletter_campagna((int)$arg[0]);
        $st = db()->prepare('SELECT * FROM ' . $t('newsletter_iscritti') . ' WHERE id = ?'); $st->execute([(int)$arg[1]]);
        $i = $st->fetch();
        [$o, $h] = newsletter_email($c, ['id' => (int)$i['id'], 'nome' => $i['nome'], 'email' => $i['email']]);
        $out = [$o, $h];
        break;
    case 'soft':
        // un soft bounce (errore temporaneo) dal servizio di invio, come arriva dal webhook: consegna_segnala()
        require_once $dir . '/includes/consegna.php';
        $out = consegna_segnala('brevo', 'temporaneo', (string)$arg[0], '', 'prova-' . bin2hex(random_bytes(6)), 'casella piena');
        break;
    case 'valori':
        $out = newsletter_valori_di([(int)$arg[0]])[(int)$arg[0]] ?? null;
        break;
    case 'pulisci':
        $ids = db()->query('SELECT id FROM ' . $t('newsletter_iscritti') . " WHERE email LIKE '%@prova-contatti.it'")->fetchAll(PDO::FETCH_COLUMN);
        newsletter_togli_iscritti($ids);
        db()->exec('DELETE FROM ' . $t('newsletter_campagne') . " WHERE oggetto LIKE 'PC %'");
        db()->exec('DELETE FROM ' . $t('newsletter_flussi') . " WHERE nome LIKE 'PC %'");
        foreach (db()->query('SELECT id FROM ' . $t('newsletter_liste') . " WHERE nome LIKE 'PC %'")->fetchAll(PDO::FETCH_COLUMN) as $l) {
            db()->exec('DELETE FROM ' . $t('newsletter_iscritti_liste') . ' WHERE lista_id = ' . (int)$l);
            db()->exec('DELETE FROM ' . $t('newsletter_liste') . ' WHERE id = ' . (int)$l);
        }
        $out = count($ids);
        break;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
