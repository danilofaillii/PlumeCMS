<?php
/**
 * Aiuti da riga di comando per prova-campagna-riepilogo.js (2.11.0), su un CMS installato. Mai nel prodotto.
 * Funziona anche su una 2.10.0 (per le campagne di prima: semina_vecchie, html): dove una funzione manca, risponde null.
 * uso: php prove/campagne-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"            righe in JSON ({p} diventa il prefisso delle tabelle)
 *   semina                   contatti, liste, attributo CITTA, segmenti e lista dei test della prova (@cr-prova.it); stampa gli id
 *   pulisci                  toglie tutto quello che ha seminato la prova (contatti @cr-prova.it, liste e campagne "CR ")
 *   giro                     un giro della newsletter (come il cron), righe di log
 *   smtp <porta>             la posta verso il server finto su quella porta, limite orario largo
 *   html <campagna> <contatto>  l'email della campagna per quel contatto, come parte (con il tracciamento): {oggetto, html, intest}
 *   conta <campagna>         quanti contatti riceverebbero la campagna adesso
 *   uscita <contatto> [campagna]  il link di uscita (firmato con la campagna)
 *   preferenze <contatto>    il link del centro preferenze
 *   semina_vecchie           (su un sito 2.10.0) tre campagne come le fa il pannello di prima, con contatti in una lista "CR Vecchia"
 *   impronte                 lo sha256 dell'HTML di ogni campagna "CR Vecchia" per ogni suo contatto: {campagna: {contatto: sha}}
 *   semina_ab <n>            (2.13.0) n contatti ab01..@cr-prova.it nella lista "CR AB"; stampa l'id della lista
 *   ab <campagna>            la riga del test A/B e la coda per versione e stato: {ab, coda: {"a|inviata": n, ...}}
 *   ab_indietro <campagna> <secondi>  sposta indietro la partenza del test (il tempo che passa, per le prove)
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: campagne-cli.php <cartella CMS> <comando>\n"); exit(2); }
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
$out = null;

switch ($cmd) {
case 'sql':
    $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
    $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
    break;
case 'pulisci':
    $ids = db()->query('SELECT id FROM ' . $t('newsletter_iscritti') . " WHERE email LIKE '%@cr-prova.it'")->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) newsletter_togli_iscritti($ids);
    db()->exec('DELETE FROM ' . $t('newsletter_liste') . " WHERE nome LIKE 'CR %'");
    $camp = db()->query('SELECT id FROM ' . $t('newsletter_campagne') . " WHERE nome LIKE 'CR %' OR oggetto LIKE 'CR %' OR nome LIKE 'Copia di CR %' OR nome LIKE '%: CR %'")->fetchAll(PDO::FETCH_COLUMN);
    if ($camp) { $in = implode(',', array_map('intval', $camp)); db()->exec('DELETE FROM ' . $t('newsletter_coda') . " WHERE campagna_id IN ($in)"); db()->exec('DELETE FROM ' . $t('newsletter_eventi') . " WHERE campagna_id IN ($in)"); db()->exec('DELETE FROM ' . $t('newsletter_campagne') . " WHERE id IN ($in)"); }
    if (function_exists('newsletter_segmenti')) db()->exec('DELETE FROM ' . $t('newsletter_segmenti') . " WHERE nome LIKE 'CR %'");
    try { db()->exec('DELETE FROM ' . $t('newsletter_uscite_motivi') . ' WHERE contatto_id NOT IN (SELECT id FROM ' . $t('newsletter_iscritti') . ')'); } catch (Throwable $e) {}
    try { db()->exec('DELETE FROM ' . $t('newsletter_ab') . ' WHERE campagna_id NOT IN (SELECT id FROM ' . $t('newsletter_campagne') . ')'); } catch (Throwable $e) {}
    $out = count($ids);
    break;
case 'semina':
    if (!isset(newsletter_attributi()['CITTA'])) newsletter_attributo_crea(['nome' => 'CITTA', 'tipo' => 'text', 'etichetta' => 'Città']);
    newsletter_attributi(true);
    $cart = (int)array_key_first(newsletter_cartelle());
    $L = [];
    foreach (['CR Alfa', 'CR Beta', 'CR Escl', 'CR Pubblica', 'CR Privata'] as $l) { if (!$listaId($l)) newsletter_lista_crea($l, $cart); $L[$l] = $listaId($l); }
    // a1-a3 in Alfa (a3 anche in Beta: doppione), a4 in Beta (escluso dal segmento), a5 in Alfa e in Escl (esclusa dalla
    // lista), a6 in Alfa ma uscito, a7 in nessuna lista ma di Roma (segmento), a2 di Roma e in Alfa (doppione lista e
    // segmento), a8 in Alfa ma uscito dalla sola lista Alfa.
    $C = [
        'a1' => ['Anna', 'Rossi', 'Milano', ['CR Alfa']], 'a2' => ['Bruno', 'Bianchi', 'Roma', ['CR Alfa']], 'a3' => ['Carla', 'Verdi', 'Napoli', ['CR Alfa', 'CR Beta']],
        'a4' => ['Dario', 'Neri', 'Torino', ['CR Beta']], 'a5' => ['Elena', 'Galli', 'Bari', ['CR Alfa', 'CR Escl']], 'a6' => ['Fabio', 'Conti', 'Pisa', ['CR Alfa']],
        'a7' => ['', '', 'Roma', []], 'a8' => ['Gina', 'Ferri', 'Lecce', ['CR Alfa']], 't1' => ['Tina', 'Testa', 'Milano', []],
    ];
    $id = [];
    foreach ($C as $k => [$nome, $cog, $citta, $liste]) {
        $r = newsletter_contatto_crea(['EMAIL' => "$k@cr-prova.it", 'NOME' => $nome, 'COGNOME' => $cog, 'CITTA' => $citta], array_map(fn($x) => $L[$x], $liste));
        $id[$k] = (int)($r['id'] ?? $r['esiste'] ?? 0);
    }
    newsletter_esci($id['a6']);
    db()->prepare('INSERT IGNORE INTO ' . $t('newsletter_liste_uscite') . ' (contatto_id, lista_id, quando) VALUES (?, ?, NOW())')->execute([$id['a8'], $L['CR Alfa']]);
    $S = [];
    $S['roma'] = newsletter_segmento_salva(0, 'CR Roma', ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [['campo' => 'attr:CITTA', 'op' => 'uguale', 'valore' => 'Roma']]]]]);
    $S['a4'] = newsletter_segmento_salva(0, 'CR Solo a4', ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [['campo' => 'attr:EMAIL', 'op' => 'comincia', 'valore' => 'a4@']]]]]);
    newsletter_lista_test_salva('t1@cr-prova.it');
    $out = ['liste' => $L, 'contatti' => $id, 'segmenti' => $S];
    break;
case 'giro':
    $out = newsletter_invia_giro();
    break;
case 'smtp':
    // La posta verso il server finto (porta), mittente sito@esempio.it, limite orario largo.
    save_setting('posta_smtp', json_encode(['host' => '127.0.0.1', 'porta' => (int)$arg[0], 'sicurezza' => 'nessuna', 'utente' => '', 'password' => '', 'mittente' => 'sito@esempio.it', 'nome' => 'Studio Prova']));
    save_setting('newsletter_config', json_encode(['per_ora' => 2000, 'per_giorno' => 0, 'rispondi_a' => '']));
    $out = true;
    break;
case 'html':
    $c = newsletter_campagna((int)$arg[0]);
    $st = db()->prepare('SELECT * FROM ' . $t('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([(int)$arg[1]]);
    $i = $st->fetch();
    [$o, $h, $in] = newsletter_email($c, ['id' => (int)$i['id'], 'nome' => $i['nome'], 'email' => $i['email']]);
    $out = ['oggetto' => $o, 'html' => $h, 'intest' => $in];
    break;
case 'conta':
    $out = function_exists('newsletter_campagna_conta') ? newsletter_campagna_conta(newsletter_campagna((int)$arg[0])) : null;
    break;
case 'uscita':
    $out = newsletter_link_uscita((int)$arg[0], isset($arg[1]) ? ['c' => (int)$arg[1]] : null);
    break;
case 'preferenze':
    $out = function_exists('newsletter_link_preferenze') ? newsletter_link_preferenze((int)$arg[0]) : null;
    break;
case 'semina_vecchie':
    // Come il pannello della 2.10.0: "Nuova campagna" (liste = principale), poi oggetto, testo, pulsante, liste, mittente.
    $cart = function_exists('newsletter_cartelle') ? (int)array_key_first(newsletter_cartelle()) : 0;
    if (!$listaId('CR Vecchia')) newsletter_lista_crea('CR Vecchia', $cart);
    $lv = $listaId('CR Vecchia');
    foreach ([['v1', 'Viola', 'Valli'], ['v2', '', ''], ['v3', 'Zoe', '']] as [$k, $n, $cg]) newsletter_contatto_crea(['EMAIL' => "$k@cr-prova.it", 'NOME' => $n, 'COGNOME' => $cg], [$lv]);
    $ora = date('Y-m-d H:i:s');
    $camp = [
        ['CR Vecchia uno {nome}', 'Anteprima di {nome}', '<p>Ciao {nome}, le novità.</p><h2>Titolo</h2><ol><li data-list="bullet">uno</li><li data-list="bullet">due</li></ol><p class="ql-align-center"><a href="' . MODULI_URL . '/contatti/">Contatti</a> e <a href="https://esempio.it/x?a=1&amp;b=2">fuori</a></p>', 'Leggi', MODULI_URL . '/blog/'],
        ['CR Vecchia due {{ contact.NOME | default:"amico" }}', '', '<p>Gentile {{ contact.NOME | default:"amico" }} {{ contact.COGNOME }}, emoji &#x1F600; e <b>grassetto</b>.</p><blockquote>citazione</blockquote>', '', ''],
        ['CR Vecchia tre', 'Solo testo', '<p>Una riga sola.</p>', 'Pulsante senza link', ''],
    ];
    $ids = [];
    foreach ($camp as [$o, $a, $c, $pt, $pl]) {
        db()->prepare('INSERT INTO ' . $t('newsletter_campagne') . ' (oggetto, anteprima, corpo, pulsante_testo, pulsante_link, liste, creato, aggiornato) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([newsletter_4byte_dentro($o), $a, $c, $pt, $pl, (string)$lv, $ora, $ora]);
        $ids[] = (int)db()->lastInsertId();
    }
    $out = ['campagne' => $ids, 'lista' => $lv];
    break;
case 'impronte':
    $out = [];
    $ids = db()->query('SELECT i.id FROM ' . $t('newsletter_iscritti') . ' i JOIN ' . $t('newsletter_iscritti_liste') . ' l ON l.iscritto_id = i.id WHERE l.lista_id = ' . $listaId('CR Vecchia') . ' ORDER BY i.id')->fetchAll(PDO::FETCH_COLUMN);
    foreach (db()->query('SELECT * FROM ' . $t('newsletter_campagne') . " WHERE oggetto LIKE 'CR Vecchia%' ORDER BY id")->fetchAll() as $c) {
        foreach ($ids as $i) {
            $st = db()->prepare('SELECT * FROM ' . $t('newsletter_iscritti') . ' WHERE id = ?');
            $st->execute([(int)$i]);
            $r = $st->fetch();
            [$o, $h] = newsletter_email($c, ['id' => (int)$r['id'], 'nome' => $r['nome'], 'email' => $r['email']]);
            $out[$c['id']][$i] = hash('sha256', $h);
            $out[$c['id']]['o' . $i] = $o;
        }
    }
    break;
case 'semina_ab':
    $cart = (int)array_key_first(newsletter_cartelle());
    if (!$listaId('CR AB')) newsletter_lista_crea('CR AB', $cart);
    $la = $listaId('CR AB');
    for ($k = 1; $k <= (int)$arg[0]; $k++) newsletter_contatto_crea(['EMAIL' => sprintf('ab%02d@cr-prova.it', $k), 'NOME' => 'Nome' . $k], [$la]);
    $out = $la;
    break;
case 'ab':
    $c = (int)$arg[0];
    $out = ['ab' => nl_ab($c), 'coda' => []];
    foreach (db()->query('SELECT versione, stato, COUNT(*) AS n FROM ' . $t('newsletter_coda') . " WHERE campagna_id = $c GROUP BY versione, stato") as $r) $out['coda'][$r['versione'] . '|' . $r['stato']] = (int)$r['n'];
    break;
case 'ab_indietro':
    db()->prepare('UPDATE ' . $t('newsletter_ab') . ' SET avviato_at = DATE_SUB(avviato_at, INTERVAL ? SECOND) WHERE campagna_id = ?')->execute([(int)$arg[1], (int)$arg[0]]);
    $out = nl_ab((int)$arg[0])['avviato_at'];
    break;
default:
    fwrite(STDERR, "comando sconosciuto\n"); exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
