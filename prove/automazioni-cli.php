<?php
/**
 * Aiuti da riga di comando per prova-automazioni.js e prova-automazioni-vecchie.js (2.15.0), su un CMS installato. Mai nel prodotto.
 * I comandi "vecchi_*" e "impronte" funzionano anche su una 2.14.0 (i flussi di prima, da aggiornare).
 * uso: php prove/automazioni-cli.php <cartella CMS> <comando> [argomenti]   (AU_LOCALE=host:porta definisce CMS_WEBHOOK_LOCALE)
 *   sql "<query>"                 righe in JSON ({p} diventa il prefisso delle tabelle)
 *   giro                          un giro della newsletter (campagne e automazioni), righe di log
 *   smtp <porta>                  la posta verso il server finto su quella porta, limite orario largo
 *   pulisci                       toglie i dati della prova (contatti @au-prova.it, liste, automazioni e segmenti "AU ")
 *   semina <n> [lista]            n contatti au0001..@au-prova.it, attivi, nella lista (creata se manca); stampa {lista, ids}
 *   salva <json>                  newsletter_flusso_salva(0, json): stampa l'id o {errore}
 *   innesco <tipo> <id> <contatto>   newsletter_flussi_innesco()
 *   evento <apertura|clic> <flusso> <nodo> <contatto>   un'apertura o un clic firmati, come dal pixel o dal link
 *   esci <contatto>               il contatto si disiscrive (newsletter_esci)
 *   togli_lista <contatto> <lista>   il contatto viene tolto dalla lista
 *   avanza <minuti> [flusso]      il tempo che passa: cammini, registro ed eventi delle automazioni AU indietro di N minuti
 *   attributo <contatto> <NOME> <valore>   scrive un attributo (crea AU_DATA, tipo data, se serve)
 *   webhook <url> [contatto]      controllo e chiamata di un webhook come dal passo: {controllo, esito}
 *   numeri <flusso>               nl_aut_numeri()
 *   vecchi_semina                 (2.14.0) tre flussi "AU Vecchio" con sei contatti a metà; stampa gli id
 *   impronte                      sha256 di oggetto e HTML di ogni email dei flussi "AU Vecchio" per ogni loro contatto
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: automazioni-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
if (getenv('AU_LOCALE')) define('CMS_WEBHOOK_LOCALE', getenv('AU_LOCALE'));
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
newsletter_schema();
newsletter_flussi_schema();
$t = fn($n) => tbl($n);
$listaId = function (string $n) use ($t): int {
    $id = (int)db()->query('SELECT id FROM ' . $t('newsletter_liste') . ' WHERE nome = ' . db()->quote($n))->fetchColumn();
    if ($id) return $id;
    db()->prepare('INSERT INTO ' . $t('newsletter_liste') . ' (nome, descrizione, creato) VALUES (?, \'\', NOW())')->execute([$n]);
    return (int)db()->lastInsertId();
};
$contatto = function (string $email, string $nome, int $lista) use ($t): int {
    $id = newsletter_id_da_email($email);
    if (!$id) {
        db()->prepare('INSERT INTO ' . $t('newsletter_iscritti') . " (email, nome, stato, fonte, consenso_at, creato, aggiornato) VALUES (?, ?, 'attivo', 'prova', NOW(), NOW(), NOW())")->execute([$email, $nome]);
        $id = (int)db()->lastInsertId();
    }
    if ($lista) newsletter_in_lista($id, $lista);
    return $id;
};
$au = fn() => db()->query('SELECT id FROM ' . $t('newsletter_flussi') . " WHERE nome LIKE 'AU %'")->fetchAll(PDO::FETCH_COLUMN);
$out = null;

switch ($cmd) {
case 'sql':
    $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
    $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
    break;
case 'giro':
    $out = newsletter_invia_giro();
    break;
case 'smtp':
    save_setting('posta_smtp', json_encode(['host' => '127.0.0.1', 'porta' => (int)$arg[0], 'sicurezza' => 'nessuna', 'utente' => '', 'password' => '', 'mittente' => 'sito@esempio.it', 'nome' => 'Studio Prova']));
    save_setting('newsletter_config', json_encode(['per_ora' => 50000, 'per_giorno' => 0, 'rispondi_a' => '']));
    $out = true;
    break;
case 'pulisci':
    $ids = db()->query('SELECT id FROM ' . $t('newsletter_iscritti') . " WHERE email LIKE '%@au-prova.it'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_chunk($ids, 1000) as $pezzo) newsletter_togli_iscritti($pezzo);
    foreach ($au() as $f) newsletter_flusso_elimina((int)$f);
    db()->exec('DELETE FROM ' . $t('newsletter_liste') . " WHERE nome LIKE 'AU %'");
    if (function_exists('newsletter_segmenti')) db()->exec('DELETE FROM ' . $t('newsletter_segmenti') . " WHERE nome LIKE 'AU %'");
    // Le righe della posta che le prove lasciano: il limite orario dei giri dopo resta pieno (trappola della 2.14.0).
    try { db()->exec('UPDATE ' . $t('posta_registro') . " SET quando = quando - INTERVAL 2 DAY WHERE tipo = 'flusso' AND quando > NOW() - INTERVAL 2 DAY"); } catch (Throwable $e) {}
    $out = count($ids);
    break;
case 'semina':
    $n = max(1, (int)($arg[0] ?? 10));
    $l = $listaId((string)($arg[1] ?? 'AU Base'));
    $ids = [];
    $ins = db()->prepare('INSERT IGNORE INTO ' . $t('newsletter_iscritti') . " (email, nome, stato, fonte, consenso_at, creato, aggiornato) VALUES (?, ?, 'attivo', 'prova', NOW(), NOW(), NOW())");
    $lis = db()->prepare('INSERT IGNORE INTO ' . $t('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) VALUES (?, ?, NOW())');
    for ($i = 1; $i <= $n; $i++) {
        $e = sprintf('au%04d@au-prova.it', $i);
        $ins->execute([$e, 'Persona ' . $i]);
        $id = newsletter_id_da_email($e);
        $lis->execute([$id, $l]);
        $ids[] = $id;
    }
    $out = ['lista' => $l, 'ids' => $ids];
    break;
case 'lista':
    $out = $listaId((string)$arg[0]);
    break;
case 'molti':
    // molti <prefisso> <n> [lista]: contatti <prefisso>01..@au-prova.it, attivi, messi in una lista senza far partire niente.
    [$pre, $n] = [(string)$arg[0], (int)$arg[1]];
    $l = isset($arg[2]) ? (int)$arg[2] : 0;
    $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $e = sprintf('%s%03d@au-prova.it', $pre, $i);
        $id = newsletter_id_da_email($e) ?: $contatto($e, ucfirst($pre) . $i, 0);
        if ($l) db()->prepare('INSERT IGNORE INTO ' . $t('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) VALUES (?, ?, NOW())')->execute([$id, $l]);
        $out[] = $id;
    }
    break;
case 'in_lista':
    // in_lista <lista> <id,id,...>: newsletter_in_lista(), che fa partire le automazioni della lista.
    foreach (array_filter(array_map('intval', explode(',', (string)$arg[1]))) as $id) newsletter_in_lista($id, (int)$arg[0]);
    $out = true;
    break;
case 'attr_testo':
    if (!isset(newsletter_attributi()[$arg[0]])) newsletter_attributo_crea(['nome' => $arg[0], 'tipo' => 'text', 'etichetta' => 'Nota di prova']);
    $out = isset(newsletter_attributi(true)[$arg[0]]);
    break;
case 'segmento':
    // segmento <nome> <lista>: un segmento "sta nella lista".
    $out = newsletter_segmento_salva(0, (string)$arg[0], ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [['campo' => 'lista', 'op' => 'in', 'valore' => [(int)$arg[1]]]]]]]);
    break;
case 'modello':
    // Un modello a blocchi attivo, copiato dalla galleria: stampa l'id.
    $slug = array_key_first(nd_galleria());
    $out = nd_galleria_copia((string)$slug);
    break;
case 'contatto':
    $out = $contatto((string)$arg[0], (string)($arg[1] ?? 'Prova'), (int)($arg[2] ?? 0));
    break;
case 'salva':
    try { $out = newsletter_flusso_salva(0, json_decode((string)$arg[0], true)); } catch (Throwable $e) { $out = ['errore' => $e->getMessage()]; }
    break;
case 'innesco':
    newsletter_flussi_innesco((string)$arg[0], (int)$arg[1], (int)$arg[2]);
    $out = true;
    break;
case 'evento':
    [$tipo, $f, $p, $i] = $arg;
    $out = newsletter_evento($tipo, 0, (int)$f, (int)$p, (int)$i, nl_firma(0, (int)$f, (int)$p, (int)$i), $tipo === 'clic' ? 'https://esempio.it/pagina' : '');
    break;
case 'esci':
    newsletter_esci((int)$arg[0]);
    $out = true;
    break;
case 'togli_lista':
    $out = newsletter_togli_da_liste([(int)$arg[0]], [(int)$arg[1]]);
    break;
case 'avanza':
    $m = (int)$arg[0];
    $fl = isset($arg[1]) ? [(int)$arg[1]] : array_map('intval', $au());
    if ($fl) {
        $in = implode(',', $fl);
        db()->exec('UPDATE ' . $t('newsletter_corsi') . " SET prossimo = prossimo - INTERVAL $m MINUTE, creato = creato - INTERVAL $m MINUTE, aggiornato = aggiornato - INTERVAL $m MINUTE WHERE flusso_id IN ($in)");
        db()->exec('UPDATE ' . $t('newsletter_eventi') . " SET creato = creato - INTERVAL $m MINUTE WHERE flusso_id IN ($in)");
        try { db()->exec('UPDATE ' . $t('newsletter_flussi_registro') . " SET creato = creato - INTERVAL $m MINUTE WHERE flusso_id IN ($in)"); } catch (Throwable $e) {}
    }
    $out = count($fl);
    break;
case 'attributo':
    if (!isset(newsletter_attributi()[$arg[1]])) { newsletter_attributo_crea(['nome' => $arg[1], 'tipo' => 'date', 'etichetta' => 'Data di prova']); newsletter_attributi(true); }
    $out = newsletter_valori_salva((int)$arg[0], [$arg[1] => $arg[2]]);
    break;
case 'webhook':
    $c = nl_aut_webhook_controlla((string)$arg[0], true);
    $r = ['id' => 0, 'iscritto_id' => (int)($arg[1] ?? 0), 'email' => 'prova@au-prova.it', 'nome' => 'Prova', 'cognome' => ''];
    $out = ['controllo' => $c['errore'], 'esito' => nl_aut_webhook(['id' => 0, 'nome' => 'AU Prova'], ['id' => 1, 'tipo' => 'webhook', 'dati' => ['url' => (string)$arg[0]]], $r)];
    break;
case 'numeri':
    $out = nl_aut_numeri(newsletter_flusso((int)$arg[0]));
    break;
case 'vecchi_semina':
    // Su una 2.14.0: tre flussi di prima e sei contatti, mandati avanti fino a metà con il motore di prima.
    $l = $listaId('AU Vecchia');
    $e = fn($id, $ogg, $asc = 0) => ['id' => $id, 'tipo' => 'email', 'x' => 40, 'y' => 40 + 150 * $id, 'dati' => ['oggetto' => $ogg, 'anteprima' => 'Anteprima ' . $ogg, 'corpo' => '<p>Ciao {nome}, questa è «' . $ogg . '».</p><p><a href="https://esempio.it/pagina">Leggi</a></p>', 'pulsante_testo' => 'Vai', 'pulsante_link' => 'https://esempio.it/vai', 'ascolto' => $asc]];
    $nodo = fn($id, $tipo, $dati) => ['id' => $id, 'tipo' => $tipo, 'x' => 40, 'y' => 40 + 150 * $id, 'dati' => $dati];
    $inn = ['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 40, 'dati' => []];
    $salva = fn($nome, $g) => newsletter_flusso_salva(0, ['nome' => $nome, 'attivo' => 1, 'innesco' => 'lista', 'innesco_id' => $l, 'grafo' => json_encode($g)]);
    $A = $salva('AU Vecchio A', ['nodi' => [$inn, $e(1, 'AU A1'), $nodo(2, 'attesa', ['minuti' => 1440]), $nodo(3, 'bivio', ['condizione' => 'cliccata', 'valore' => 1]), $e(4, 'AU A2 cliccato'), $e(5, 'AU A2 no')],
        'archi' => [['da' => 0, 'uscita' => 'poi', 'a' => 1], ['da' => 1, 'uscita' => 'poi', 'a' => 2], ['da' => 2, 'uscita' => 'poi', 'a' => 3], ['da' => 3, 'uscita' => 'si', 'a' => 4], ['da' => 3, 'uscita' => 'no', 'a' => 5]]]);
    $B = $salva('AU Vecchio B', ['nodi' => [$inn, $e(1, 'AU B1', 2880), $e(2, 'AU B apre'), $e(3, 'AU B niente')],
        'archi' => [['da' => 0, 'uscita' => 'poi', 'a' => 1], ['da' => 1, 'uscita' => 'aperta', 'a' => 2], ['da' => 1, 'uscita' => 'nessuna', 'a' => 3]]]);
    $C = $salva('AU Vecchio C', ['nodi' => [$inn, $nodo(1, 'attesa', ['minuti' => 1440]), $e(2, 'AU C dopo')], 'archi' => [['da' => 0, 'uscita' => 'poi', 'a' => 1], ['da' => 1, 'uscita' => 'poi', 'a' => 2]]]);
    $ids = [];
    foreach (['au01' => 'Anna', 'au02' => 'Bruno', 'au03' => 'Carla', 'au04' => 'Dario', 'au05' => 'Elena', 'au06' => 'Fabio'] as $k => $nome) $ids[$k] = $contatto("$k@au-prova.it", $nome, $l);
    newsletter_invia_giro();
    // Clic in A1 (au01, au02: al bivio Sì), aperture in B1 prima dell'aggiornamento (au01, au03); C si spegne con i cammini in attesa.
    foreach (['au01', 'au02'] as $k) newsletter_evento('clic', 0, $A, 1, $ids[$k], nl_firma(0, $A, 1, $ids[$k]), 'https://esempio.it/pagina');
    foreach (['au01', 'au03'] as $k) newsletter_evento('apertura', 0, $B, 1, $ids[$k], nl_firma(0, $B, 1, $ids[$k]));
    db()->prepare('UPDATE ' . $t('newsletter_flussi') . ' SET attivo = 0 WHERE id = ?')->execute([$C]);
    $out = ['A' => $A, 'B' => $B, 'C' => $C, 'lista' => $l, 'contatti' => $ids];
    break;
case 'impronte':
    $out = [];
    foreach (db()->query('SELECT * FROM ' . $t('newsletter_flussi') . " WHERE nome LIKE 'AU Vecchio%' ORDER BY id")->fetchAll() as $r) {
        $f = newsletter_flusso((int)$r['id']);
        foreach (db()->query('SELECT i.* FROM ' . $t('newsletter_iscritti') . " i WHERE i.email LIKE 'au0_@au-prova.it' ORDER BY i.email")->fetchAll() as $i) {
            foreach ($f['grafo']['nodi'] as $n) {
                if ($n['tipo'] !== 'email') continue;
                $d = $n['dati'];
                $c = function_exists('nl_email_campagna') ? nl_email_campagna($f, $d) : ['id' => 0, 'oggetto' => $d['oggetto'], 'anteprima' => $d['anteprima'], 'corpo' => $d['corpo'], 'pulsante_testo' => $d['pulsante_testo'], 'pulsante_link' => $d['pulsante_link'], 'mittente_id' => (int)($f['mittente_id'] ?? 0)];
                [$ogg, $html] = newsletter_email($c, ['id' => (int)$i['id'], 'nome' => $i['nome'], 'email' => $i['email']], ['f' => (int)$f['id'], 'p' => $n['id']]);
                $out[$r['nome'] . '|' . $n['id'] . '|' . $i['email']] = hash('sha256', $ogg . "\n" . $html);
            }
        }
    }
    ksort($out);
    break;
default:
    fwrite(STDERR, "comando sconosciuto: $cmd\n");
    exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
