<?php
/**
 * Aiuti da riga di comando per prova-segmenti.js (2.10.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/segmenti-cli.php <cartella CMS> <comando> [argomenti]
 *   semina [n]       n contatti finti (5000) @seg-prova.it con attributi dei sei tipi, stati e motivi della lista nera
 *                    (anche righe di prima della 2.8.0, senza motivo), liste, tre campagne con coda, aperture e clic,
 *                    email dei flussi, invii di due form. Sempre gli stessi (mt_srand). Stampa id utili in JSON.
 *   confronta        ogni condizione del motore contro una query scritta a mano: [{nome, motore, mano, uguali, ms}]
 *   flussi           i bivi dei flussi: le nove condizioni della 2.9.0 (codice di prima, copiato qui) contro il motore,
 *                    su 600 contatti per condizione: [{condizione, prove, diverse}]
 *   bivi             l'esito dei bivi con il codice installato (JSON): si salva sulla 2.9.0 e si confronta dopo l'aggiornamento
 *   conta <b64|json> quanti contatti per delle regole: {n, ms} oppure {errore}
 *   sql "<query>"    righe in JSON ({p} diventa il prefisso)
 *   pulisci          toglie i contatti @seg-prova.it, le liste, le campagne e i form "SG ", i segmenti e le viste
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: segmenti-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
require_once $dir . '/includes/form.php';
newsletter_schema();
newsletter_flussi_schema();
form_schema();
if (function_exists('newsletter_importa_schema')) newsletter_importa_schema();
if (function_exists('newsletter_segmenti_schema')) newsletter_segmenti_schema();
$t = fn($n) => tbl($n);
$P = DB_PREFIX;
$ids = function (string $sql, array $par = []) use ($P): array {
    $st = db()->prepare(str_replace('{p}', $P, $sql));
    $st->execute($par);
    $r = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    sort($r);
    return $r;
};
$attrId = fn($n) => (int)db()->query('SELECT id FROM ' . $t('newsletter_attributi') . ' WHERE nome = ' . db()->quote($n))->fetchColumn();
$listaId = fn($n) => (int)db()->query('SELECT id FROM ' . $t('newsletter_liste') . ' WHERE nome = ' . db()->quote($n))->fetchColumn();
$campId = fn($n) => (int)db()->query('SELECT id FROM ' . $t('newsletter_campagne') . ' WHERE oggetto = ' . db()->quote($n))->fetchColumn();
$formId = fn($n) => (int)db()->query('SELECT id FROM ' . $t('moduli') . ' WHERE nome = ' . db()->quote($n))->fetchColumn();
$out = null;

switch ($cmd) {
case 'semina':
    $N = max(10, (int)($arg[0] ?? 5000));
    mt_srand(42);
    // Attributi
    $attr = [
        ['CITTA', 'text', 'Città', ''], ['PUNTI', 'float', 'Punti', ''], ['NASCITA', 'date', 'Data di nascita', ''], ['ACQUISTO', 'date', 'Ultimo acquisto', ''],
        ['SCADENZA', 'date', 'Scadenza', ''], ['VIP', 'boolean', 'Cliente VIP', ''], ['LIVELLO', 'category', 'Livello', "Base\nArgento\nOro"], ['INTERESSI', 'multiple-choice', 'Interessi', "Cucina\nViaggi\nSport\nMusica"],
    ];
    foreach ($attr as [$n, $tipo, $et, $op]) if (!$attrId($n)) { $e = newsletter_attributo_crea(['nome' => $n, 'tipo' => $tipo, 'etichetta' => $et, 'opzioni' => $op]); if ($e !== '') { fwrite(STDERR, "$n: $e\n"); exit(1); } }
    newsletter_attributi(true);
    $A = []; foreach ($attr as [$n]) $A[$n] = $attrId($n);
    $cart = (int)array_key_first(newsletter_cartelle());
    $L = [];
    foreach (['SG Clienti', 'SG Eventi', 'SG Fornitori', 'SG Vuota', 'SG Roma'] as $l) { if (!$listaId($l)) newsletter_lista_crea($l, $cart); $L[$l] = $listaId($l); }
    // Due form
    $F = [];
    foreach (['SG Contatti', 'SG Preventivo'] as $i => $f) {
        if (!$formId($f)) db()->prepare('INSERT INTO ' . $t('moduli') . " (nome, slug, campi, creato, aggiornato) VALUES (?, ?, '[]', NOW(), NOW())")->execute([$f, 'sg-form-' . $i . '-' . bin2hex(random_bytes(3))]);
        $F[$f] = $formId($f);
    }
    $citta = ['Roma', 'Milano', 'Napoli', 'Torino', 'Palermo', 'Genova', 'Bologna', 'Firenze', 'Bari', 'Catania', 'Romagnano', 'Venezia', ''];
    $nomi = ['Anna', 'Bruno', 'Carla', 'Dario', 'Elena', 'Fabio', 'Gina', 'Ivo', 'Luca', 'Marta', 'Nadia', 'Otto', 'Paola', 'Rita', 'Sara', 'Aldo', 'Zeno'];
    $ora = time();
    $righe = [];
    for ($i = 1; $i <= $N; $i++) {
        $r = mt_rand(1, 100);
        [$stato, $mot] = match (true) {
            $r <= 76 => ['attivo', ''], $r <= 81 => ['in_attesa', ''], $r <= 87 => ['uscito', 'unsubscribedViaEmail'], $r <= 89 => ['uscito', ''],
            $r <= 91 => ['rimbalzato', 'hardBounce'], $r <= 92 => ['rimbalzato', ''], $r <= 93 => ['spam', 'contactFlaggedAsSpam'], $r <= 96 => ['bloccato', 'adminBlocked'],
            $r <= 97 => ['bloccato', 'softBounce'], default => ['bloccato', 'import'],
        };
        $creato = date('Y-m-d H:i:s', $ora - mt_rand(0, 400) * 86400 - mt_rand(0, 86399));
        $cons = $stato === 'in_attesa' ? null : (mt_rand(0, 4) ? date('Y-m-d H:i:s', strtotime($creato) + mt_rand(0, 3600)) : null);
        $righe[] = [sprintf('c%05d@seg-prova.it', $i), $nomi[mt_rand(0, count($nomi) - 1)], mt_rand(0, 3) ? 'Cognome' . mt_rand(1, 50) : '', $stato, $mot,
            in_array($stato, ['uscito', 'rimbalzato', 'spam', 'bloccato'], true) && $mot !== '' ? $creato : null, mt_rand(0, 1), mt_rand(0, 2) ? $cons : null,
            mt_rand(0, 9) ? null : 'EXT' . $i, $cons, $creato, date('Y-m-d H:i:s', strtotime($creato) + mt_rand(0, 30) * 86400)];
    }
    foreach (array_chunk($righe, 500) as $pezzo) {
        $sql = 'INSERT INTO ' . $t('newsletter_iscritti') . ' (email, nome, cognome, stato, blocco_motivo, blocco_at, opt_in, doi_at, ext_id, consenso_at, creato, aggiornato, fonte) VALUES ' . implode(',', array_fill(0, count($pezzo), "(?,?,?,?,?,?,?,?,?,?,?,?,'import')"));
        db()->prepare($sql)->execute(array_merge(...$pezzo));
    }
    $C = $ids("SELECT id FROM {p}newsletter_iscritti WHERE email LIKE '%@seg-prova.it'");
    $attivi = $ids("SELECT id FROM {p}newsletter_iscritti WHERE email LIKE '%@seg-prova.it' AND stato = 'attivo'");
    // Valori degli attributi
    $val = [];
    $data = fn($giorni) => date('Y-m-d', $ora + $giorni * 86400);
    foreach ($C as $c) {
        if (mt_rand(0, 9)) { $cc = $citta[mt_rand(0, count($citta) - 1)]; if ($cc !== '') $val[] = [$c, $A['CITTA'], 0, $cc, null, null]; }
        if (mt_rand(0, 4)) $val[] = [$c, $A['PUNTI'], 0, '', mt_rand(0, 1000) / 10, null];
        if (mt_rand(0, 3)) $val[] = [$c, $A['NASCITA'], 0, '', null, sprintf('%04d-%02d-%02d', mt_rand(1950, 2005), mt_rand(1, 12), mt_rand(1, 28))];
        if (mt_rand(0, 2)) $val[] = [$c, $A['ACQUISTO'], 0, '', null, $data(-mt_rand(0, 500))];
        if (mt_rand(0, 2)) $val[] = [$c, $A['SCADENZA'], 0, '', null, $data(mt_rand(-100, 200))];
        if (mt_rand(0, 2)) $val[] = [$c, $A['VIP'], 0, '', mt_rand(0, 3) ? 0 : 1, null];
        if (mt_rand(0, 3)) $val[] = [$c, $A['LIVELLO'], 0, '', mt_rand(1, 3), null];
        foreach ([1, 2, 3, 4] as $o) if (!mt_rand(0, 2)) $val[] = [$c, $A['INTERESSI'], $o, '', null, null];
    }
    foreach (array_chunk($val, 800) as $pezzo) {
        db()->prepare('INSERT IGNORE INTO ' . $t('newsletter_valori') . ' (contatto_id, attributo_id, opzione, v_testo, v_num, v_data) VALUES ' . implode(',', array_fill(0, count($pezzo), '(?,?,?,?,?,?)')))->execute(array_merge(...$pezzo));
    }
    // Liste
    $app = [];
    foreach ($C as $c) {
        if (mt_rand(0, 9) < 6) $app[] = [$c, $L['SG Clienti']];
        if (mt_rand(0, 9) < 3) $app[] = [$c, $L['SG Eventi']];
        if (mt_rand(0, 9) < 1) $app[] = [$c, $L['SG Fornitori']];
    }
    foreach (array_chunk($app, 800) as $pezzo) db()->prepare('INSERT IGNORE INTO ' . $t('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) VALUES ' . implode(',', array_fill(0, count($pezzo), '(?,?,NOW())')))->execute(array_merge(...$pezzo));
    // Tre campagne inviate (100, 40 e 5 giorni fa): coda, aperture, clic con tre indirizzi
    $url = ['https://esempio.it/offerta', 'https://esempio.it/corso', 'https://esempio.it/blog?a=1&b=2'];
    $camp = [];
    foreach ([['SG Campagna uno', 100], ['SG Campagna due', 40], ['SG Campagna tre', 5]] as [$ogg, $gg]) {
        $quando = date('Y-m-d H:i:s', $ora - $gg * 86400);
        db()->prepare('INSERT INTO ' . $t('newsletter_campagne') . " (oggetto, corpo, stato, invio_at, liste, creato, aggiornato) VALUES (?, '<p>x</p>', 'inviata', ?, ?, ?, ?)")->execute([$ogg, $quando, (string)$L['SG Clienti'], $quando, $quando]);
        $cid = (int)db()->lastInsertId(); $camp[$ogg] = $cid;
        $coda = []; $ev = [];
        foreach ($attivi as $c) {
            if (mt_rand(0, 9) >= 7) continue;
            $coda[] = [$cid, $c, mt_rand(0, 30) ? 'inviata' : 'errore', $quando];
            if (mt_rand(0, 9) < 3) {
                $ap = date('Y-m-d H:i:s', strtotime($quando) + mt_rand(60, 6 * 86400));
                $ev[] = [$cid, 0, 0, $c, 'apertura', '', $ap];
                if (mt_rand(0, 9) < 4) $ev[] = [$cid, 0, 0, $c, 'clic', $url[mt_rand(0, 2)], $ap];
            }
        }
        foreach (array_chunk($coda, 800) as $pezzo) db()->prepare('INSERT INTO ' . $t('newsletter_coda') . ' (campagna_id, iscritto_id, stato, inviata_at) VALUES ' . implode(',', array_fill(0, count($pezzo), '(?,?,?,?)')))->execute(array_merge(...$pezzo));
        foreach (array_chunk($ev, 800) as $pezzo) db()->prepare('INSERT INTO ' . $t('newsletter_eventi') . ' (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) VALUES ' . implode(',', array_fill(0, count($pezzo), '(?,?,?,?,?,?,?)')))->execute(array_merge(...$pezzo));
    }
    // Rimbalzi, spam e uscite come eventi di una campagna (per chi è in lista nera con quei motivi)
    foreach ($ids("SELECT id FROM {p}newsletter_iscritti WHERE email LIKE '%@seg-prova.it' AND stato IN ('rimbalzato', 'spam', 'uscito') LIMIT 200") as $c) {
        db()->prepare('INSERT INTO ' . $t('newsletter_eventi') . " (campagna_id, iscritto_id, tipo, creato) VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))")->execute([$camp['SG Campagna due'], $c, ['rimbalzo', 'spam', 'uscita'][mt_rand(0, 2)], mt_rand(1, 39)]);
    }
    // Un flusso finto: email (storia) e aperture o clic sui passi 2 e 5
    $grafo = ['nodi' => [['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 40, 'dati' => []], ['id' => 2, 'tipo' => 'email', 'x' => 40, 'y' => 200, 'dati' => ['oggetto' => 'SG uno', 'anteprima' => '', 'corpo' => '<p>x</p>', 'pulsante_testo' => '', 'pulsante_link' => '', 'ascolto' => 0]],
        ['id' => 5, 'tipo' => 'email', 'x' => 40, 'y' => 360, 'dati' => ['oggetto' => 'SG due', 'anteprima' => '', 'corpo' => '<p>x</p>', 'pulsante_testo' => '', 'pulsante_link' => '', 'ascolto' => 0]]],
        'archi' => [['da' => 0, 'uscita' => 'poi', 'a' => 2], ['da' => 2, 'uscita' => 'poi', 'a' => 5]]];
    db()->prepare('INSERT INTO ' . $t('newsletter_flussi') . " (nome, attivo, innesco, innesco_id, passi, creato, aggiornato) VALUES ('SG Flusso', 0, 'iscrizione', 0, ?, NOW(), NOW())")->execute([json_encode($grafo)]);
    $flusso = (int)db()->lastInsertId();
    foreach ($attivi as $c) {
        if (mt_rand(0, 9) >= 3) continue;
        $gg = mt_rand(1, 60);
        db()->prepare('INSERT INTO ' . $t('newsletter_storia') . " (contatto_id, tipo, rif_id, dettaglio, creato) VALUES (?, 'flusso_email', ?, 'SG Flusso: SG uno', DATE_SUB(NOW(), INTERVAL ? DAY))")->execute([$c, $flusso, $gg]);
        if (mt_rand(0, 1)) db()->prepare('INSERT INTO ' . $t('newsletter_eventi') . " (campagna_id, flusso_id, passo, iscritto_id, tipo, creato) VALUES (0, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))")->execute([$flusso, mt_rand(0, 1) ? 2 : 5, $c, mt_rand(0, 2) ? 'apertura' : 'clic', $gg]);
    }
    // Invii dei form
    $inv = [];
    $em = db()->query('SELECT id, email FROM ' . $t('newsletter_iscritti') . " WHERE email LIKE '%@seg-prova.it'")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($C as $c) {
        if (mt_rand(0, 9)) continue;
        $email = $em[$c];
        $inv[] = [mt_rand(0, 1) ? $F['SG Contatti'] : $F['SG Preventivo'], $email, date('Y-m-d H:i:s', $ora - mt_rand(0, 90) * 86400)];
    }
    foreach (array_chunk($inv, 500) as $pezzo) db()->prepare('INSERT INTO ' . $t('moduli_invii') . " (modulo_id, email, nome, dati, pagina, ip, creato, aggiornato) VALUES " . implode(',', array_fill(0, count($pezzo), "(?,?,'','{}','','',?,NOW())")))->execute(array_merge(...$pezzo));
    // Statistiche delle tabelle aggiornate dopo tanti inserimenti in un colpo (come succede da sole col tempo): senza,
    // MySQL può scegliere per qualche minuto un piano lento e i tempi misurati non direbbero quelli veri.
    foreach (['newsletter_iscritti', 'newsletter_valori', 'newsletter_eventi', 'newsletter_coda', 'newsletter_storia', 'newsletter_iscritti_liste', 'moduli_invii'] as $tab) db()->query('ANALYZE TABLE ' . $t($tab))->fetchAll();
    $out = ['contatti' => count($C), 'attivi' => count($attivi), 'liste' => $L, 'campagne' => $camp, 'form' => $F, 'flusso' => $flusso, 'attributi' => $A,
        'eventi' => (int)db()->query('SELECT COUNT(*) FROM ' . $t('newsletter_eventi'))->fetchColumn(), 'invii_form' => count($inv)];
    break;

case 'confronta':
    $A = fn($n) => $attrId($n);
    $L1 = $listaId('SG Clienti'); $L2 = $listaId('SG Eventi');
    $C1 = $campId('SG Campagna uno'); $C2 = $campId('SG Campagna due'); $C3 = $campId('SG Campagna tre');
    $F1 = $formId('SG Contatti');
    $tutti = "SELECT id FROM {p}newsletter_iscritti";
    $val = fn($nome, $w) => "SELECT contatto_id FROM {p}newsletter_valori WHERE attributo_id = " . $A($nome) . " AND $w";
    $non = fn($sql) => "$tutti WHERE id NOT IN ($sql)";
    $nera = "stato IN ('uscito','rimbalzato','spam','bloccato')";
    $ult = (int)db()->query("SELECT id FROM {$P}newsletter_campagne WHERE stato IN ('in_invio','sospesa','inviata') ORDER BY COALESCE(invio_at, aggiornato) DESC, id DESC LIMIT 1")->fetchColumn();
    $c1 = fn($campo, $op, $x = []) => ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [['campo' => $campo, 'op' => $op] + $x]]]];
    // Un segmento salvato da usare dentro un altro.
    db()->exec("DELETE FROM {$P}newsletter_segmenti WHERE nome LIKE 'SG %'");
    $sid = newsletter_segmento_salva(0, 'SG Romani', $c1('attr:CITTA', 'uguale', ['valore' => 'Roma']));
    $casi = [
        ['testo uguale', $c1('attr:CITTA', 'uguale', ['valore' => 'Roma']), $val('CITTA', "v_testo = 'Roma'")],
        ['testo diverso (anche chi non ce l\'ha)', $c1('attr:CITTA', 'diverso', ['valore' => 'Roma']), $non($val('CITTA', "v_testo = 'Roma'"))],
        ['testo contiene', $c1('attr:CITTA', 'contiene', ['valore' => 'om']), $val('CITTA', "v_testo LIKE '%om%'")],
        ['testo non contiene', $c1('attr:CITTA', 'non_contiene', ['valore' => 'om']), $non($val('CITTA', "v_testo LIKE '%om%'"))],
        ['testo comincia', $c1('attr:CITTA', 'comincia', ['valore' => 'Ro']), $val('CITTA', "v_testo LIKE 'Ro%'")],
        ['testo finisce', $c1('attr:CITTA', 'finisce', ['valore' => 'no']), $val('CITTA', "v_testo LIKE '%no'")],
        ['testo vuoto', $c1('attr:CITTA', 'vuoto'), $non($val('CITTA', '1'))],
        ['testo pieno', $c1('attr:CITTA', 'pieno'), $val('CITTA', '1')],
        ['testo con il segno %', $c1('attr:CITTA', 'contiene', ['valore' => '%']), $val('CITTA', "v_testo LIKE '%\\\\%%'")],
        ['numero maggiore', $c1('attr:PUNTI', 'maggiore', ['valore' => '50']), $val('PUNTI', 'v_num > 50')],
        ['numero minore (virgola)', $c1('attr:PUNTI', 'minore', ['valore' => '20,5']), $val('PUNTI', 'v_num < 20.5')],
        ['numero fra (al contrario)', $c1('attr:PUNTI', 'fra', ['valore' => '30', 'valore2' => '10']), $val('PUNTI', 'v_num BETWEEN 10 AND 30')],
        ['numero uguale', $c1('attr:PUNTI', 'uguale', ['valore' => '42']), $val('PUNTI', 'v_num = 42')],
        ['numero diverso', $c1('attr:PUNTI', 'diverso', ['valore' => '42']), $non($val('PUNTI', 'v_num = 42'))],
        ['numero vuoto', $c1('attr:PUNTI', 'vuoto'), $non($val('PUNTI', '1'))],
        ['data prima', $c1('attr:NASCITA', 'prima', ['valore' => '1980-01-01']), $val('NASCITA', "v_data < '1980-01-01'")],
        ['data dopo (scritta all\'italiana)', $c1('attr:NASCITA', 'dopo', ['valore' => '31/12/1999']), $val('NASCITA', "v_data > '1999-12-31'")],
        ['data fra', $c1('attr:NASCITA', 'fra', ['valore' => '1970-01-01', 'valore2' => '1975-12-31']), $val('NASCITA', "v_data BETWEEN '1970-01-01' AND '1975-12-31'")],
        ['data uguale', $c1('attr:NASCITA', 'uguale', ['valore' => '1980-05-05']), $val('NASCITA', "v_data = '1980-05-05'")],
        ['data negli ultimi 30 giorni', $c1('attr:ACQUISTO', 'ultimi', ['n' => 30, 'unita' => 'giorni']), $val('ACQUISTO', 'v_data >= CURDATE() - INTERVAL 30 DAY AND v_data <= CURDATE()')],
        ['data negli ultimi 2 mesi', $c1('attr:ACQUISTO', 'ultimi', ['n' => 2, 'unita' => 'mesi']), $val('ACQUISTO', 'v_data >= CURDATE() - INTERVAL 2 MONTH AND v_data <= CURDATE()')],
        ['data più di 6 settimane fa', $c1('attr:ACQUISTO', 'piu_di', ['n' => 6, 'unita' => 'settimane']), $val('ACQUISTO', 'v_data < CURDATE() - INTERVAL 6 WEEK')],
        ['data nei prossimi 60 giorni', $c1('attr:SCADENZA', 'prossimi', ['n' => 60, 'unita' => 'giorni']), $val('SCADENZA', 'v_data >= CURDATE() AND v_data <= CURDATE() + INTERVAL 60 DAY')],
        ['data in questo mese', $c1('attr:ACQUISTO', 'mese_corrente'), $val('ACQUISTO', "DATE_FORMAT(v_data, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")],
        ['sì/no: sì', $c1('attr:VIP', 'si'), $val('VIP', 'v_num = 1')],
        ['sì/no: no o non impostato', $c1('attr:VIP', 'no'), $non($val('VIP', 'v_num = 1'))],
        ['categoria uno di', $c1('attr:LIVELLO', 'uno_di', ['valore' => [1, 3]]), $val('LIVELLO', 'v_num IN (1, 3)')],
        ['categoria nessuno di', $c1('attr:LIVELLO', 'nessuno_di', ['valore' => [2]]), $non($val('LIVELLO', 'v_num = 2'))],
        ['categoria vuoto', $c1('attr:LIVELLO', 'vuoto'), $non($val('LIVELLO', '1'))],
        ['scelta multipla almeno una', $c1('attr:INTERESSI', 'almeno_una', ['valore' => [1, 2]]), $val('INTERESSI', 'opzione IN (1, 2)')],
        ['scelta multipla tutte', $c1('attr:INTERESSI', 'tutte', ['valore' => [1, 2]]), "SELECT contatto_id FROM {p}newsletter_valori WHERE attributo_id = " . $A('INTERESSI') . " AND opzione IN (1, 2) GROUP BY contatto_id HAVING COUNT(DISTINCT opzione) = 2"],
        ['scelta multipla nessuna di', $c1('attr:INTERESSI', 'nessuna_di', ['valore' => [3]]), $non($val('INTERESSI', 'opzione = 3'))],
        ['EMAIL contiene', $c1('attr:EMAIL', 'contiene', ['valore' => 'c001']), "$tutti WHERE email LIKE '%c001%'"],
        ['NOME comincia', $c1('attr:NOME', 'comincia', ['valore' => 'A']), "$tutti WHERE nome LIKE 'A%'"],
        ['COGNOME vuoto', $c1('attr:COGNOME', 'vuoto'), "$tutti WHERE cognome = ''"],
        ['EXT_ID pieno', $c1('attr:EXT_ID', 'pieno'), "$tutti WHERE ext_id IS NOT NULL AND ext_id <> ''"],
        ['OPT_IN sì', $c1('attr:OPT_IN', 'si'), "$tutti WHERE opt_in = 1"],
        ['DOUBLE_OPT-IN sì', $c1('attr:DOUBLE_OPT-IN', 'si'), "$tutti WHERE doi_at IS NOT NULL"],
        ['DOUBLE_OPT-IN no', $c1('attr:DOUBLE_OPT-IN', 'no'), "$tutti WHERE doi_at IS NULL"],
        ['data di creazione negli ultimi 7 giorni', $c1('attr:CREATION_DATE', 'ultimi', ['n' => 7, 'unita' => 'giorni']), "$tutti WHERE DATE(creato) >= CURDATE() - INTERVAL 7 DAY AND DATE(creato) <= CURDATE()"],
        ['data di creazione in questo mese', $c1('attr:CREATION_DATE', 'mese_corrente'), "$tutti WHERE DATE_FORMAT(creato, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"],
        ['ultima modifica prima di una data', $c1('attr:LAST_CHANGED', 'prima', ['valore' => date('Y-m-d', time() - 200 * 86400)]), "$tutti WHERE DATE(aggiornato) < '" . date('Y-m-d', time() - 200 * 86400) . "'"],
        ['in una lista', $c1('lista', 'in', ['valore' => [$L1]]), "SELECT iscritto_id FROM {p}newsletter_iscritti_liste WHERE lista_id = $L1"],
        ['in almeno una di due liste', $c1('lista', 'in', ['valore' => [$L1, $L2]]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_iscritti_liste WHERE lista_id IN ($L1, $L2)"],
        ['non in una lista', $c1('lista', 'non_in', ['valore' => [$L1]]), $non("SELECT iscritto_id FROM {p}newsletter_iscritti_liste WHERE lista_id = $L1")],
        ['in nessuna lista', $c1('nessuna_lista', 'si'), $non('SELECT iscritto_id FROM {p}newsletter_iscritti_liste')],
        ['in almeno una lista', $c1('nessuna_lista', 'no'), 'SELECT DISTINCT iscritto_id FROM {p}newsletter_iscritti_liste'],
        ['fa parte di un segmento', $c1('segmento', 'in', ['valore' => $sid]), $val('CITTA', "v_testo = 'Roma'")],
        ['non fa parte di un segmento', $c1('segmento', 'non_in', ['valore' => $sid]), $non($val('CITTA', "v_testo = 'Roma'"))],
        ['ha aperto una campagna (sempre)', $c1('email', 'aperta', ['campagna' => 0]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'apertura' AND campagna_id > 0"],
        ['ha aperto negli ultimi 30 giorni', $c1('email', 'aperta', ['campagna' => 0, 'n' => 30, 'unita' => 'giorni']), "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'apertura' AND campagna_id > 0 AND creato >= NOW() - INTERVAL 30 DAY"],
        ['non ha aperto negli ultimi 90 giorni', $c1('email', 'non_aperta', ['campagna' => 0, 'n' => 90, 'unita' => 'giorni']), $non("SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'apertura' AND campagna_id > 0 AND creato >= NOW() - INTERVAL 90 DAY")],
        ['ha aperto una campagna precisa', $c1('email', 'aperta', ['campagna' => $C1]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'apertura' AND campagna_id = $C1"],
        ['non ha aperto una campagna precisa (ricevuta)', $c1('email', 'non_aperta', ['campagna' => $C2]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_coda WHERE campagna_id = $C2 AND stato = 'inviata' AND iscritto_id NOT IN (SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'apertura' AND campagna_id = $C2)"],
        ['ha cliccato l\'ultima campagna', $c1('email', 'cliccata', ['campagna' => -1]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'clic' AND campagna_id = $ult"],
        ['non ha cliccato l\'ultima campagna', $c1('email', 'non_cliccata', ['campagna' => -1]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_coda WHERE campagna_id = $ult AND stato = 'inviata' AND iscritto_id NOT IN (SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'clic' AND campagna_id = $ult)"],
        ['ha ricevuto una campagna precisa', $c1('email', 'ricevuta', ['campagna' => $C1]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_coda WHERE campagna_id = $C1 AND stato = 'inviata'"],
        ['non ha ricevuto nessuna campagna', $c1('email', 'non_ricevuta', ['campagna' => 0]), $non("SELECT iscritto_id FROM {p}newsletter_coda WHERE campagna_id > 0 AND stato = 'inviata'")],
        ['ha aperto qualsiasi email (anche automazioni)', $c1('email', 'aperta', ['campagna' => -2]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'apertura'"],
        ['ha ricevuto qualsiasi email negli ultimi 10 giorni', $c1('email', 'ricevuta', ['campagna' => -2, 'n' => 10, 'unita' => 'giorni']), "SELECT iscritto_id FROM {p}newsletter_coda WHERE stato = 'inviata' AND inviata_at >= NOW() - INTERVAL 10 DAY UNION SELECT contatto_id FROM {p}newsletter_storia WHERE tipo = 'flusso_email' AND creato >= NOW() - INTERVAL 10 DAY"],
        ['link preciso cliccato', $c1('link', 'cliccato', ['valore' => 'https://esempio.it/blog?a=1&b=2', 'campagna' => 0]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'clic' AND url = 'https://esempio.it/blog?a=1&b=2' AND campagna_id > 0"],
        ['link preciso cliccato in una campagna', $c1('link', 'cliccato', ['valore' => 'https://esempio.it/offerta', 'campagna' => $C2]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'clic' AND url = 'https://esempio.it/offerta' AND campagna_id = $C2"],
        ['link preciso non cliccato', $c1('link', 'non_cliccato', ['valore' => 'https://esempio.it/corso', 'campagna' => 0]), $non("SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'clic' AND url = 'https://esempio.it/corso' AND campagna_id > 0")],
        ['ha inviato un form', $c1('form', 'inviato', ['valore' => 0]), "SELECT DISTINCT i.id FROM {p}newsletter_iscritti i JOIN {p}moduli_invii m ON m.email = i.email"],
        ['ha inviato un form preciso negli ultimi 30 giorni', $c1('form', 'inviato', ['valore' => $F1, 'n' => 30, 'unita' => 'giorni']), "SELECT DISTINCT i.id FROM {p}newsletter_iscritti i JOIN {p}moduli_invii m ON m.email = i.email WHERE m.modulo_id = $F1 AND m.creato >= NOW() - INTERVAL 30 DAY"],
        ['non ha inviato un form', $c1('form', 'non_inviato', ['valore' => 0]), $non("SELECT i.id FROM {p}newsletter_iscritti i JOIN {p}moduli_invii m ON m.email = i.email")],
        ['in lista nera', $c1('lista_nera', 'si'), "$tutti WHERE $nera"],
        ['non in lista nera', $c1('lista_nera', 'no'), "$tutti WHERE NOT $nera"],
        ['in lista nera per hard bounce o a mano (anche righe senza motivo)', $c1('lista_nera', 'motivo', ['valore' => ['hardBounce', 'adminBlocked']]), "$tutti WHERE $nera AND (blocco_motivo IN ('hardBounce','adminBlocked') OR (blocco_motivo = '' AND stato IN ('rimbalzato','bloccato')))"],
        ['in lista nera per disiscrizione', $c1('lista_nera', 'motivo', ['valore' => ['unsubscribedViaEmail']]), "$tutti WHERE $nera AND (blocco_motivo = 'unsubscribedViaEmail' OR (blocco_motivo = '' AND stato = 'uscito'))"],
        ['iscritto', $c1('stato', 'iscritto'), "$tutti WHERE stato = 'attivo'"],
        ['non iscritto', $c1('stato', 'non_iscritto'), "$tutti WHERE stato <> 'attivo'"],
        ['in attesa di conferma', $c1('stato', 'in_attesa'), "$tutti WHERE stato = 'in_attesa'"],
        ['iscritto da almeno 100 giorni', $c1('iscritto_da', 'almeno', ['n' => 100]), "$tutti WHERE COALESCE(consenso_at, creato) <= NOW() - INTERVAL 100 DAY"],
        ['iscritto da meno di 100 giorni', $c1('iscritto_da', 'meno', ['n' => 100]), "$tutti WHERE COALESCE(consenso_at, creato) > NOW() - INTERVAL 100 DAY"],
        ['aperto o cliccato negli ultimi 30 giorni', $c1('attivita', 'si', ['n' => 30]), "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo IN ('apertura','clic') AND creato > NOW() - INTERVAL 30 DAY"],
        ['niente negli ultimi 30 giorni', $c1('attivita', 'no', ['n' => 30]), $non("SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo IN ('apertura','clic') AND creato > NOW() - INTERVAL 30 DAY")],
        ['una condizione con NON davanti', ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [['campo' => 'lista', 'op' => 'in', 'valore' => [$L1], 'non' => true]]]]], $non("SELECT iscritto_id FROM {p}newsletter_iscritti_liste WHERE lista_id = $L1")],
        ['gruppo E', ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [['campo' => 'stato', 'op' => 'iscritto'], ['campo' => 'attr:VIP', 'op' => 'si'], ['campo' => 'lista', 'op' => 'in', 'valore' => [$L1]]]]]],
            "SELECT i.id FROM {p}newsletter_iscritti i WHERE i.stato = 'attivo' AND i.id IN (" . $val('VIP', 'v_num = 1') . ") AND i.id IN (SELECT iscritto_id FROM {p}newsletter_iscritti_liste WHERE lista_id = $L1)"],
        ['gruppo O', ['op' => 'e', 'gruppi' => [['op' => 'o', 'condizioni' => [['campo' => 'attr:CITTA', 'op' => 'uguale', 'valore' => 'Milano'], ['campo' => 'attr:LIVELLO', 'op' => 'uno_di', 'valore' => [3]]]]]],
            "SELECT id FROM {p}newsletter_iscritti WHERE id IN (" . $val('CITTA', "v_testo = 'Milano'") . ") OR id IN (" . $val('LIVELLO', 'v_num = 3') . ")"],
        ['due gruppi in O, ognuno in E, con una negazione', ['op' => 'o', 'gruppi' => [
                ['op' => 'e', 'condizioni' => [['campo' => 'stato', 'op' => 'iscritto'], ['campo' => 'attr:PUNTI', 'op' => 'maggiore', 'valore' => 90]]],
                ['op' => 'e', 'condizioni' => [['campo' => 'lista_nera', 'op' => 'si'], ['campo' => 'email', 'op' => 'aperta', 'campagna' => 0, 'non' => true]]]]],
            "SELECT id FROM {p}newsletter_iscritti WHERE (stato = 'attivo' AND id IN (" . $val('PUNTI', 'v_num > 90') . ")) OR ($nera AND id NOT IN (SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'apertura' AND campagna_id > 0))"],
        ['due gruppi in E, il secondo in O', ['op' => 'e', 'gruppi' => [
                ['op' => 'e', 'condizioni' => [['campo' => 'stato', 'op' => 'iscritto']]],
                ['op' => 'o', 'condizioni' => [['campo' => 'email', 'op' => 'cliccata', 'campagna' => $C3], ['campo' => 'form', 'op' => 'inviato', 'valore' => 0, 'n' => 30, 'unita' => 'giorni']]]]],
            "SELECT id FROM {p}newsletter_iscritti WHERE stato = 'attivo' AND (id IN (SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'clic' AND campagna_id = $C3) OR id IN (SELECT i.id FROM {p}newsletter_iscritti i JOIN {p}moduli_invii m ON m.email = i.email WHERE m.creato >= NOW() - INTERVAL 30 DAY))"],
    ];
    // I modelli pronti, contro le loro query a mano.
    $mod = newsletter_segmenti_modelli();
    $nonAp = fn($tipo, $g) => "SELECT iscritto_id FROM {p}newsletter_eventi WHERE tipo = '$tipo' AND campagna_id > 0 AND creato >= NOW() - INTERVAL $g DAY";
    $casi[] = ['modello: contatti non coinvolti', $mod['non_coinvolti'][2], "$tutti WHERE stato = 'attivo' AND id NOT IN (" . $nonAp('apertura', 30) . ") AND id NOT IN (" . $nonAp('clic', 30) . ")"];
    $casi[] = ['modello: chi non apre da 90 giorni', $mod['non_aprono_90'][2], "$tutti WHERE stato = 'attivo' AND id NOT IN (" . $nonAp('apertura', 90) . ")"];
    $casi[] = ['modello: coinvolti', $mod['coinvolti'][2], "$tutti WHERE stato = 'attivo' AND (id IN (" . $nonAp('apertura', 30) . ") OR id IN (" . $nonAp('clic', 30) . "))"];
    $casi[] = ['modello: nuovi iscritti di questo mese', $mod['nuovi_mese'][2], "$tutti WHERE stato = 'attivo' AND DATE_FORMAT(creato, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"];
    $casi[] = ['modello: chi ha cliccato l\'ultima campagna', $mod['cliccato_ultima'][2], "SELECT DISTINCT iscritto_id FROM {p}newsletter_eventi WHERE tipo = 'clic' AND campagna_id = $ult"];
    $casi[] = ['modello: hanno compilato un form da poco', $mod['form_30'][2], "SELECT DISTINCT i.id FROM {p}newsletter_iscritti i JOIN {p}moduli_invii m ON m.email = i.email WHERE m.creato >= NOW() - INTERVAL 30 DAY"];
    $casi[] = ['modello: in nessuna lista', $mod['nessuna_lista'][2], $non('SELECT iscritto_id FROM {p}newsletter_iscritti_liste')];
    $out = [];
    foreach ($casi as [$nome, $regole, $mano]) {
        $v = newsletter_regole_valida($regole);
        if (isset($v['errore'])) { $out[] = ['nome' => $nome, 'errore' => $v['errore']]; continue; }
        $par = [];
        $w = newsletter_regole_where($v['regole'], $par);
        $t0 = microtime(true);
        $st = db()->prepare('SELECT i.id FROM ' . $t('newsletter_iscritti') . " i WHERE $w");
        $st->execute($par);
        $m = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $ms = (int)round((microtime(true) - $t0) * 1000);
        sort($m);
        $h = $ids($mano);
        $h = array_values(array_unique($h));
        $conta = newsletter_regole_conta($v['regole']);
        $out[] = ['nome' => $nome, 'motore' => count($m), 'mano' => count($h), 'uguali' => $m === $h, 'ms' => $ms, 'conta_ms' => $conta['ms'], 'conta' => $conta['n']];
    }
    // Cento condizioni (il massimo): tempo del conteggio.
    $cento = ['op' => 'o', 'gruppi' => []];
    for ($g = 0; $g < 10; $g++) {
        $cc = [];
        for ($k = 0; $k < 10; $k++) $cc[] = [['campo' => 'attr:PUNTI', 'op' => 'maggiore', 'valore' => $g * 10 + $k], ['campo' => 'email', 'op' => 'aperta', 'campagna' => 0, 'n' => $k + 1, 'unita' => 'giorni'], ['campo' => 'lista', 'op' => 'in', 'valore' => [$L1]], ['campo' => 'attr:CITTA', 'op' => 'contiene', 'valore' => 'a'], ['campo' => 'form', 'op' => 'non_inviato', 'valore' => 0]][$k % 5];
        $cento['gruppi'][] = ['op' => $g % 2 ? 'o' : 'e', 'condizioni' => $cc];
    }
    $v = newsletter_regole_valida($cento);
    $c = isset($v['regole']) ? newsletter_regole_conta($v['regole']) : ['n' => -1, 'ms' => -1];
    $out[] = ['nome' => 'cento condizioni in dieci gruppi: tempo', 'motore' => $c['n'], 'mano' => $c['n'], 'uguali' => isset($v['regole']), 'ms' => $c['ms'], 'conta_ms' => $c['ms'], 'conta' => $c['n']];
    db()->exec("DELETE FROM {$P}newsletter_segmenti WHERE nome LIKE 'SG %'");
    break;

case 'flussi':
    // Il codice dei bivi della 2.9.0 (newsletter_flusso_condizione), copiato com'era.
    $vecchia = function (int $f, string $c, int $v, int $iscritto): bool {
        switch ($c) {
            case 'in_lista': case 'non_in_lista':
                $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ? AND lista_id = ?');
                $st->execute([$iscritto, $v]); $si = (int)$st->fetchColumn() > 0;
                return $c === 'in_lista' ? $si : !$si;
            case 'aperta': case 'non_aperta': case 'cliccata': case 'non_cliccata':
                $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_eventi') . ' WHERE flusso_id = ? AND passo = ? AND iscritto_id = ? AND tipo = ?');
                $st->execute([$f, $v, $iscritto, str_contains($c, 'clicc') ? 'clic' : 'apertura']); $si = (int)$st->fetchColumn() > 0;
                return str_starts_with($c, 'non_') ? !$si : $si;
            case 'inattivo': case 'attivo':
                $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_eventi') . ' WHERE iscritto_id = ? AND creato > DATE_SUB(NOW(), INTERVAL ? DAY)');
                $st->execute([$iscritto, max(1, $v)]); $si = (int)$st->fetchColumn() > 0;
                return $c === 'attivo' ? $si : !$si;
            case 'iscritto_da':
                $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ? AND COALESCE(consenso_at, creato) <= DATE_SUB(NOW(), INTERVAL ? DAY)');
                $st->execute([$iscritto, max(0, $v)]);
                return (int)$st->fetchColumn() > 0;
        }
        return true;
    };
    $fl = (int)db()->query("SELECT id FROM {$P}newsletter_flussi WHERE nome = 'SG Flusso'")->fetchColumn();
    $f = newsletter_flusso($fl);
    $L1 = $listaId('SG Clienti');
    $persone = $ids("SELECT id FROM {p}newsletter_iscritti WHERE email LIKE '%@seg-prova.it' ORDER BY id LIMIT 600");
    $casi = [['in_lista', $L1], ['non_in_lista', $L1], ['in_lista', 0], ['non_in_lista', 0], ['in_lista', 999999], ['aperta', 2], ['non_aperta', 2], ['cliccata', 5], ['non_cliccata', 5], ['aperta', 0], ['non_aperta', 0],
        ['inattivo', 30], ['attivo', 30], ['attivo', 0], ['inattivo', 45], ['iscritto_da', 100], ['iscritto_da', 0], ['iscritto_da', 365], ['sconosciuta', 3]];
    $out = [];
    foreach ($casi as [$c, $v]) {
        $diverse = 0; $si = 0;
        foreach ($persone as $p) {
            $nodo = ['dati' => ['condizione' => $c, 'valore' => $v]];
            $nuova = newsletter_flusso_condizione($f, $nodo, $p);
            if ($nuova !== $vecchia($fl, $c, $v, $p)) $diverse++;
            if ($nuova) $si++;
        }
        $out[] = ['condizione' => "$c $v", 'prove' => count($persone), 'diverse' => $diverse, 'si' => $si];
    }
    // La condizione nuova "fa parte di un segmento", dentro un flusso.
    db()->exec("DELETE FROM {$P}newsletter_segmenti WHERE nome LIKE 'SG %'");
    $sid = newsletter_segmento_salva(0, 'SG Flusso VIP', ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [['campo' => 'attr:VIP', 'op' => 'si']]]]]);
    $vip = $ids('SELECT contatto_id FROM {p}newsletter_valori WHERE attributo_id = ' . $attrId('VIP') . ' AND v_num = 1');
    $diverse = 0;
    foreach ($persone as $p) if (newsletter_flusso_condizione($f, ['dati' => ['condizione' => 'in_segmento', 'valore' => $sid]], $p) !== in_array($p, $vip, true)) $diverse++;
    $out[] = ['condizione' => 'in_segmento (nuova)', 'prove' => count($persone), 'diverse' => $diverse, 'si' => count(array_intersect($persone, $vip))];
    db()->exec("DELETE FROM {$P}newsletter_segmenti WHERE nome LIKE 'SG %'");
    break;

case 'bivi':
    // L'esito dei bivi con il codice installato (2.9.0 prima dell'aggiornamento, 2.10.0 dopo): per confrontarli.
    $fl = (int)db()->query("SELECT id FROM {$P}newsletter_flussi WHERE nome = 'SG Flusso'")->fetchColumn();
    $f = newsletter_flusso($fl);
    $L1 = $listaId('SG Clienti');
    $esiti = [];
    foreach ([['in_lista', $L1], ['non_in_lista', $L1], ['in_lista', 0], ['non_in_lista', 0], ['aperta', 2], ['non_aperta', 2], ['cliccata', 5], ['non_cliccata', 5],
        ['inattivo', 30], ['attivo', 30], ['iscritto_da', 100], ['iscritto_da', 365]] as [$c, $v]) {
        $si = [];
        foreach ($ids("SELECT id FROM {p}newsletter_iscritti WHERE email LIKE '%@seg-prova.it' ORDER BY id LIMIT 600") as $p) if (newsletter_flusso_condizione($f, ['dati' => ['condizione' => $c, 'valore' => $v]], $p)) $si[] = $p;
        $esiti["$c $v"] = $si;
    }
    $out = $esiti;
    break;

case 'conta':
    $v = newsletter_regole_valida((string)($arg[0] ?? ''));
    $out = isset($v['errore']) ? ['errore' => $v['errore']] : newsletter_regole_conta($v['regole']);
    break;

case 'sql':
    $st = db()->query(str_replace('{p}', $P, (string)($arg[0] ?? '')));
    $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
    break;

case 'pulisci':
    $c = $ids("SELECT id FROM {p}newsletter_iscritti WHERE email LIKE '%@seg-prova.it'");
    foreach (array_chunk($c, 1000) as $pezzo) {
        $in = implode(',', $pezzo);
        foreach (['newsletter_valori' => 'contatto_id', 'newsletter_iscritti_liste' => 'iscritto_id', 'newsletter_coda' => 'iscritto_id', 'newsletter_eventi' => 'iscritto_id', 'newsletter_storia' => 'contatto_id', 'newsletter_consensi' => 'contatto_id', 'newsletter_iscritti' => 'id'] as $tab => $col) db()->exec('DELETE FROM ' . $t($tab) . " WHERE $col IN ($in)");
    }
    db()->exec('DELETE FROM ' . $t('moduli_invii') . " WHERE email LIKE '%@seg-prova.it'");
    db()->exec('DELETE FROM ' . $t('newsletter_campagne') . " WHERE oggetto LIKE 'SG %'");
    db()->exec('DELETE FROM ' . $t('newsletter_flussi') . " WHERE nome LIKE 'SG %'");
    db()->exec('DELETE FROM ' . $t('moduli') . " WHERE nome LIKE 'SG %'");
    foreach ($ids("SELECT id FROM {p}newsletter_liste WHERE nome LIKE 'SG %'") as $l) newsletter_lista_elimina($l);
    if (function_exists('newsletter_segmenti_schema')) { db()->exec('DELETE FROM ' . $t('newsletter_segmenti')); db()->exec('DELETE FROM ' . $t('newsletter_segmenti_cartelle')); db()->exec('DELETE FROM ' . $t('newsletter_viste')); }
    foreach (['CITTA', 'PUNTI', 'NASCITA', 'ACQUISTO', 'SCADENZA', 'VIP', 'LIVELLO', 'INTERESSI'] as $n) if ($a = $attrId($n)) newsletter_attributo_elimina($a);
    $out = count($c);
    break;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
