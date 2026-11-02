<?php
/**
 * Aiuti da riga di comando per prova-report.js (2.14.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/report-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"              righe in JSON ({p} diventa il prefisso delle tabelle)
 *   pulisci                    toglie quello che ha seminato la prova (contatti @rp-prova.it e @rp-grande.it, liste, campagne e segmenti "RP ")
 *   smtp <porta>               la posta verso il server finto su quella porta, limite orario largo
 *   semina                     liste "RP Report" (r01-r10, rimbalza1, rinvia1) e "RP Due" (r01-r03), campagne "RP Report" (programmata
 *                              adesso, due link) e "RP Due" (programmata adesso); stampa gli id
 *   giro                       un giro della newsletter (come il cron)
 *   interno <tipo> <c> <i>     come un avviso del servizio di spedizione: rimbalzo, spam (con la lista nera), soft
 *   numeri <c>                 i numeri del report (nl_rep_numeri), oppure null su un CMS senza il report
 *   conta_vecchie              (anche sulla 2.13.0) per ogni campagna partita: inviate, errori e persone per tipo di evento, in SQL
 *   eventi_vecchi              (anche sulla 2.13.0) dopo semina e tre giri: gli eventi della prova su "RP Report" con newsletter_evento
 *   semina_orario              contatti o1-o5 nella lista "RP Orario" con le aperture passate a ore scelte; stampa gli id
 *   orario_ore <ids,...>       le ore calcolate (nl_orario_ore)
 *   avvia <c>                  fa partire la campagna adesso (come il cron all'ora della partenza), qualunque sia invio_at
 *   semina_ab                  una campagna "RP AB" inviata con il test A/B finito (vince la A): stampa l'id
 *   semina_grande <n>          n contatti @rp-grande.it e una campagna "RP Grande" inviata a tutti, con aperture, clic, uscite, rimbalzi
 *   tempo_orario <c>           i millisecondi di nl_orario_riassunto per la campagna
 *   pulisci_grande             toglie i contatti @rp-grande.it e la campagna "RP Grande" (alla fine della prova)
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: report-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
newsletter_schema();
newsletter_flussi_schema();
if (function_exists('newsletter_segmenti_schema')) newsletter_segmenti_schema();
if (function_exists('newsletter_campagne_schema')) newsletter_campagne_schema();
$t = fn($n) => tbl($n);
$listaId = fn($n) => (int)db()->query('SELECT id FROM ' . $t('newsletter_liste') . ' WHERE nome = ' . db()->quote($n))->fetchColumn();
$nuovaLista = function (string $n) use ($listaId) {
    if (!$listaId($n)) newsletter_lista_crea($n, (int)array_key_first(newsletter_cartelle()));
    return $listaId($n);
};
$campagna = function (string $nome, string $corpo, int $lista) use ($t) {
    $ora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO ' . $t('newsletter_campagne') . " (nome, oggetto, corpo, liste, stato, invio_at, creato, aggiornato) VALUES (?, ?, ?, ?, 'programmata', ?, ?, ?)")
        ->execute([$nome, $nome . ' oggetto', $corpo, (string)$lista, $ora, $ora, $ora]);
    return (int)db()->lastInsertId();
};
$out = null;

switch ($cmd) {
case 'sql':
    $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
    $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
    break;
case 'pulisci':
    $ids = db()->query('SELECT id FROM ' . $t('newsletter_iscritti') . " WHERE email LIKE '%@rp-prova.it' OR email LIKE '%@rp-grande.it'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_chunk($ids, 2000) as $p) newsletter_togli_iscritti($p);
    db()->exec('DELETE FROM ' . $t('newsletter_liste') . " WHERE nome LIKE 'RP %'");
    $camp = db()->query('SELECT id FROM ' . $t('newsletter_campagne') . " WHERE nome LIKE 'RP %'")->fetchAll(PDO::FETCH_COLUMN);
    if ($camp) { $in = implode(',', array_map('intval', $camp)); foreach (['newsletter_coda', 'newsletter_eventi', 'newsletter_uscite_motivi'] as $x) db()->exec('DELETE FROM ' . $t($x) . " WHERE campagna_id IN ($in)"); db()->exec('DELETE FROM ' . $t('newsletter_campagne') . " WHERE id IN ($in)"); try { db()->exec('DELETE FROM ' . $t('newsletter_ab') . " WHERE campagna_id IN ($in)"); } catch (Throwable $e) {} }
    if (function_exists('newsletter_segmenti')) db()->exec('DELETE FROM ' . $t('newsletter_segmenti') . " WHERE nome LIKE 'RP %'");
    $out = count($ids);
    break;
case 'smtp':
    save_setting('posta_smtp', json_encode(['host' => '127.0.0.1', 'porta' => (int)$arg[0], 'sicurezza' => 'nessuna', 'utente' => '', 'password' => '', 'mittente' => 'sito@esempio.it', 'nome' => 'Studio Prova']));
    save_setting('newsletter_config', json_encode(['per_ora' => 50000, 'per_giorno' => 0, 'rispondi_a' => '']));
    $out = true;
    break;
case 'semina':
    $l1 = $nuovaLista('RP Report'); $l2 = $nuovaLista('RP Due');
    $id = [];
    foreach (['r01' => 'Anna', 'r02' => 'Bruno', 'r03' => '=HYPERLINK("http://x.it")', 'r04' => 'Dario', 'r05' => 'Elena', 'r06' => 'Fabio', 'r07' => 'Gina', 'r08' => 'Ivo', 'r09' => 'Luca', 'r10' => 'Marta', 'rimbalza1' => 'Nora', 'rinvia1' => 'Olga'] as $k => $nome) {
        $r = newsletter_contatto_crea(['EMAIL' => "$k@rp-prova.it", 'NOME' => $nome], in_array($k, ['r01', 'r02', 'r03'], true) ? [$l1, $l2] : [$l1]);
        $id[$k] = (int)($r['id'] ?? $r['esiste'] ?? 0);
    }
    $c1 = $campagna('RP Report', '<p>Ciao {nome}, ecco le novità.</p><p><a href="' . MODULI_URL . '/contatti/">I contatti</a> e <a href="https://esempio.it/fuori?a=1">un sito fuori</a></p>', $l1);
    $c2 = $campagna('RP Due', '<p>Seconda.</p><p><a href="https://esempio.it/due">Vai</a></p>', $l2);
    $out = ['liste' => ['report' => $l1, 'due' => $l2], 'campagne' => ['c1' => $c1, 'c2' => $c2], 'contatti' => $id];
    break;
case 'giro':
    $out = newsletter_invia_giro();
    break;
case 'interno':
    [$tipo, $c, $i] = [(string)$arg[0], (int)$arg[1], (int)$arg[2]];
    if ($tipo === 'rimbalzo') newsletter_rimbalzato($i, 'avviso finto');
    if ($tipo === 'spam') newsletter_segnala_spam($i);
    newsletter_evento_interno($tipo, $c, 0, 0, $i);
    $out = true;
    break;
case 'numeri':
    $out = function_exists('nl_rep_numeri') ? nl_rep_numeri((int)$arg[0]) : null;
    break;
case 'conta_vecchie':
    $out = [];
    foreach (db()->query('SELECT id, destinatari, inviate, errori FROM ' . $t('newsletter_campagne') . " WHERE stato IN ('in_invio', 'sospesa', 'inviata', 'annullata') ORDER BY id")->fetchAll() as $c) {
        $x = ['destinatari' => (int)$c['destinatari'], 'inviate' => (int)$c['inviate'], 'errori' => (int)$c['errori']];
        foreach (['apertura', 'clic', 'uscita', 'spam', 'rimbalzo'] as $tipo) $x[$tipo] = (int)db()->query('SELECT COUNT(DISTINCT iscritto_id) FROM ' . $t('newsletter_eventi') . ' WHERE campagna_id = ' . (int)$c['id'] . " AND tipo = '$tipo'")->fetchColumn();
        $x['link'] = db()->query('SELECT url, COUNT(DISTINCT iscritto_id) AS n FROM ' . $t('newsletter_eventi') . ' WHERE campagna_id = ' . (int)$c['id'] . " AND tipo = 'clic' GROUP BY url ORDER BY url")->fetchAll(PDO::FETCH_KEY_PAIR);
        $out[$c['id']] = $x;
    }
    break;
case 'eventi_vecchi':
    // Dopo semina e tre giri: gli eventi della prova sulla campagna "RP Report" con newsletter_evento (anche sulla 2.13.0,
    // che conta una volta sola le aperture e i clic ripetuti), più uscita, spam e un rimbalzo dopo l'invio.
    $c = (int)db()->query('SELECT id FROM ' . $t('newsletter_campagne') . " WHERE nome = 'RP Report'")->fetchColumn();
    $id = fn($k) => (int)db()->query('SELECT id FROM ' . $t('newsletter_iscritti') . " WHERE email = '$k@rp-prova.it'")->fetchColumn();
    $ev = function (string $k, string $tipo, int $volte, string $url = '') use ($c, $id) { $i = $id($k); for ($v = 0; $v < $volte; $v++) newsletter_evento($tipo, $c, 0, 0, $i, nl_firma($c, 0, 0, $i), $url); };
    $l1 = MODULI_URL . '/contatti/'; $l2 = 'https://esempio.it/fuori?a=1';
    $ev('r01', 'apertura', 3); $ev('r01', 'clic', 2, $l1); $ev('r01', 'clic', 1, $l2);
    $ev('r02', 'apertura', 1); $ev('r02', 'clic', 1, $l1);
    $ev('r03', 'apertura', 2); $ev('r04', 'clic', 1, $l2); $ev('r05', 'apertura', 1); $ev('r06', 'apertura', 1);
    newsletter_esci($id('r05')); newsletter_evento_interno('uscita', $c, 0, 0, $id('r05'));
    newsletter_segnala_spam($id('r06')); newsletter_evento_interno('spam', $c, 0, 0, $id('r06'));
    newsletter_rimbalzato($id('r10'), 'avviso finto'); newsletter_evento_interno('rimbalzo', $c, 0, 0, $id('r10'));
    $out = $c;
    break;
case 'semina_orario':
    $l = $nuovaLista('RP Orario');
    $id = [];
    foreach (['o1', 'o2', 'o3', 'o4', 'o5'] as $k) { $r = newsletter_contatto_crea(['EMAIL' => "$k@rp-prova.it", 'NOME' => strtoupper($k)], [$l]); $id[$k] = (int)($r['id'] ?? $r['esiste'] ?? 0); }
    // Le aperture passate (campagna 0, flusso 999999: non toccano nessun report). o1: 18, 18, 9 -> 18. o2: 7 (3 giorni fa) e
    // 21 (5 giorni fa), una a testa -> la più recente, 7. o3: una sola apertura -> nessun orario. o4: due alle 6 ma 400 giorni fa.
    $ev = [['o1', 18, 2], ['o1', 18, 9], ['o1', 9, 4], ['o2', 7, 3], ['o2', 21, 5], ['o3', 15, 2], ['o4', 6, 400], ['o4', 6, 401]];
    $ins = db()->prepare('INSERT INTO ' . $t('newsletter_eventi') . " (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) VALUES (0, 999999, ?, ?, 'apertura', '', ?)");
    foreach ($ev as $n => [$k, $ora, $giorni]) $ins->execute([$n + 1, $id[$k], date('Y-m-d', strtotime("-$giorni days")) . sprintf(' %02d:%02d:00', $ora, 10 + $n)]);
    $out = ['lista' => $l, 'contatti' => $id];
    break;
case 'orario_ore':
    $out = nl_orario_ore(array_map('intval', explode(',', (string)$arg[0])));
    break;
case 'avvia':
    $c = newsletter_campagna((int)$arg[0]);
    $out = newsletter_campagna_avvia($c);
    break;
case 'semina_ab':
    $l = $nuovaLista('RP Report');
    $c = newsletter_campagna_crea('RP AB', 'ab');
    $ids = db()->query('SELECT i.id FROM ' . $t('newsletter_iscritti') . ' i WHERE i.email IN (\'r01@rp-prova.it\', \'r02@rp-prova.it\', \'r03@rp-prova.it\', \'r04@rp-prova.it\') ORDER BY i.email')->fetchAll(PDO::FETCH_COLUMN);
    db()->prepare('UPDATE ' . $t('newsletter_campagne') . " SET oggetto = 'RP AB oggetto A', corpo = '<p>x</p>', liste = ?, stato = 'inviata', invio_at = NOW(), destinatari = 4, inviate = 4 WHERE id = ?")->execute([(string)$l, $c]);
    nl_ab($c);
    db()->prepare('UPDATE ' . $t('newsletter_ab') . " SET cosa = 'oggetto', oggetto_b = 'RP AB oggetto B', fase = 'finita', vincitore = 'a', criterio = 'aperture', gruppo = 2, resto = 0, percentuale = 50, avviato_at = NOW(), scelto_at = NOW() WHERE campagna_id = ?")->execute([$c]);
    foreach ($ids as $n => $i) db()->prepare('INSERT INTO ' . $t('newsletter_coda') . " (campagna_id, iscritto_id, stato, inviata_at, versione) VALUES (?, ?, 'inviata', NOW(), ?)")->execute([$c, $i, $n < 2 ? 'a' : 'b']);
    newsletter_evento('apertura', $c, 0, 0, (int)$ids[0], nl_firma($c, 0, 0, (int)$ids[0]));
    $out = $c;
    break;
case 'semina_grande':
    $n = (int)$arg[0];
    $l = $nuovaLista('RP Grande');
    $ora = date('Y-m-d H:i:s');
    $is = $t('newsletter_iscritti');
    for ($k = 1; $k <= $n; $k += 1000) {
        $v = [];
        for ($j = $k; $j < min($n + 1, $k + 1000); $j++) $v[] = sprintf("('g%05d@rp-grande.it', 'G%d', 'attivo', 'manuale', '%s', '%s')", $j, $j, $ora, $ora);
        db()->exec("INSERT IGNORE INTO $is (email, nome, stato, fonte, creato, aggiornato) VALUES " . implode(',', $v));
    }
    db()->exec('INSERT IGNORE INTO ' . $t('newsletter_iscritti_liste') . " (iscritto_id, lista_id, aggiunto) SELECT id, $l, NOW() FROM $is WHERE email LIKE '%@rp-grande.it'");
    $c = $campagna('RP Grande', '<p>Grande.</p>', $l);
    db()->exec("UPDATE {$t('newsletter_campagne')} SET stato = 'inviata', destinatari = $n, inviate = $n WHERE id = $c");
    db()->exec('INSERT IGNORE INTO ' . $t('newsletter_coda') . " (campagna_id, iscritto_id, stato, inviata_at) SELECT $c, id, 'inviata', DATE_SUB(NOW(), INTERVAL 2 HOUR) FROM $is WHERE email LIKE '%@rp-grande.it'"); // fuori dall'ultima ora: il limite orario delle prove dopo resta libero
    $ev = $t('newsletter_eventi');
    // 40% aperture (volte da 1 a 3), 15% clic su cinque link, 1% uscite, 0,5% rimbalzi, una segnalazione su 1000 di spam.
    db()->exec("INSERT INTO $ev (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato, volte) SELECT $c, 0, 0, id, 'apertura', '', DATE_SUB(NOW(), INTERVAL id % 24 HOUR), 1 + id % 3 FROM $is WHERE email LIKE '%@rp-grande.it' AND id % 5 < 2");
    db()->exec("INSERT INTO $ev (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato, volte) SELECT $c, 0, 0, id, 'clic', CONCAT('https://esempio.it/link', id % 5), NOW(), 1 + id % 2 FROM $is WHERE email LIKE '%@rp-grande.it' AND id % 20 < 3");
    db()->exec("INSERT INTO $ev (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) SELECT $c, 0, 0, id, 'uscita', '', NOW() FROM $is WHERE email LIKE '%@rp-grande.it' AND id % 100 = 7");
    db()->exec("INSERT INTO $ev (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) SELECT $c, 0, 0, id, 'rimbalzo', '', NOW() FROM $is WHERE email LIKE '%@rp-grande.it' AND id % 200 = 11");
    db()->exec("INSERT INTO $ev (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) SELECT $c, 0, 0, id, 'spam', '', NOW() FROM $is WHERE email LIKE '%@rp-grande.it' AND id % 1000 = 13");
    $out = ['campagna' => $c, 'lista' => $l];
    break;
case 'pulisci_grande':
    // Solo i 20000 contatti e la campagna "RP Grande": le prove dopo (Contatti a pagine, limite orario) non li devono vedere.
    $ids = db()->query('SELECT id FROM ' . $t('newsletter_iscritti') . " WHERE email LIKE '%@rp-grande.it'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_chunk($ids, 2000) as $p) newsletter_togli_iscritti($p);
    $c = (int)db()->query('SELECT id FROM ' . $t('newsletter_campagne') . " WHERE nome = 'RP Grande'")->fetchColumn();
    if ($c) { db()->exec('DELETE FROM ' . $t('newsletter_eventi') . " WHERE campagna_id = $c"); db()->exec('DELETE FROM ' . $t('newsletter_coda') . " WHERE campagna_id = $c"); db()->exec('DELETE FROM ' . $t('newsletter_campagne') . " WHERE id = $c"); }
    db()->exec('DELETE FROM ' . $t('newsletter_liste') . " WHERE nome = 'RP Grande'");
    $out = count($ids);
    break;
case 'tempo_orario':
    $t0 = microtime(true);
    $r = nl_orario_riassunto(newsletter_campagna((int)$arg[0]));
    $out = ['ms' => round((microtime(true) - $t0) * 1000), 'n' => $r[0], 'con' => $r[1]];
    break;
default:
    fwrite(STDERR, "comando sconosciuto\n"); exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
