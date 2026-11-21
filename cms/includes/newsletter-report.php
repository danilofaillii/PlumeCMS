<?php
/**
 * 2.14.0, fase 7 del piano Brevo (docs-sviluppo/brevo-confronto.md, capitoli 1.6, 1.15, 1.16): il report delle campagne,
 * le statistiche di un periodo e delle liste, le soglie, l'orario migliore.
 *
 *   - numeri di una campagna (nl_rep_numeri): inviate, consegnate (inviate meno chi è rimbalzato dopo la partenza),
 *     aperture e clic unici (per persona, come prima) e totali (colonna `volte` di newsletter_eventi, dalla 2.14.0),
 *     disiscrizioni, rimbalzi definitivi e temporanei, segnalazioni di spam; i tassi sulle consegnate, i rimbalzi sulle
 *     email provate (inviate più quelle non partite);
 *   - link con i clic e mappa dei clic sull'anteprima; elenchi dei contatti per ogni numero, in CSV, e in una lista o un
 *     segmento;
 *   - statistiche di un periodo (campagne, totali, andamento, confronto) e di una lista (crescita, entrati, usciti);
 *   - soglie (NL_SOGLIE): rimbalzi definitivi oltre il 2%, disiscrizioni oltre l'1%, spam oltre lo 0,2%;
 *   - orario migliore: l'ora in cui ogni contatto apre di solito; la coda parte a quell'ora nel giorno scelto (`dopo`).
 *
 * Chi usa queste funzioni da un altro file le chiama dietro function_exists o fa require_once di questo file (opcache).
 */

require_once __DIR__ . '/newsletter.php';

const NL_SOGLIE = ['rimbalzi' => [2.0, 'Rimbalzi definitivi oltre il 2% delle email provate: togli gli indirizzi vecchi o comprati, i servizi di posta guardano questo numero.'],
    'uscite' => [1.0, 'Disiscrizioni oltre l\'1% delle consegnate: forse mandi troppe email, o a chi non le aspetta.'],
    'spam' => [0.2, 'Segnalazioni di spam oltre lo 0,2% delle consegnate: oltre questa soglia le tue email cominciano a finire nello spam.']];
/** Gli elenchi dei contatti del report: chiave => [titolo, scheda, si salva come segmento]. Elenco chiuso. */
const NL_REP_ELENCHI = [
    'aperto' => ['Hanno aperto', 'aperture', true], 'non_aperto' => ['Non hanno aperto', 'aperture', false],
    'cliccato' => ['Hanno cliccato', 'clic', true],
    'uscito' => ['Si sono disiscritti', 'uscite', false], 'spam' => ['Hanno segnalato spam', 'uscite', false],
    'rimbalzo' => ['Rimbalzi definitivi', 'rimbalzi', false], 'soft' => ['Rimbalzi temporanei', 'rimbalzi', false],
];
const NL_REP_SCHEDE = ['panoramica' => 'Panoramica', 'aperture' => 'Aperture', 'clic' => 'Clic', 'uscite' => 'Disiscrizioni', 'rimbalzi' => 'Rimbalzi'];
const NL_REP_PER_PAGINA = 50;
/** Orario migliore: aperture minime negli ultimi NL_ORARIO_GIORNI giorni perché un contatto abbia un orario suo. */
const NL_ORARIO_MIN = 2;
const NL_ORARIO_GIORNI = 365;
/** Gli stati di una campagna che ha un report. */
const NL_REP_STATI = ['in_invio', 'sospesa', 'inviata', 'annullata'];

function nl_rep_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    newsletter_schema();
    newsletter_flussi_schema(); // volte, ultimo e l'indice per contatto in newsletter_eventi
    $impronta = md5(DB_NAME . 'orario_migliore orario_ora');
    if (get_setting('newsletter_report_impronta', '') === $impronta) return;
    $t = tbl('newsletter_campagne');
    $col = db()->query('SHOW COLUMNS FROM ' . $t)->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('orario_migliore', $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `orario_migliore` TINYINT(1) NOT NULL DEFAULT 0");
    if (!in_array('orario_ora', $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `orario_ora` TINYINT NOT NULL DEFAULT 10");
    $ind = db()->query('SHOW INDEX FROM ' . $t)->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('invio_at', $ind, true)) db()->exec("ALTER TABLE $t ADD KEY `invio_at` (`invio_at`)");
    save_setting('newsletter_report_impronta', $impronta);
}

/** Una percentuale con un decimale (due per lo spam), 0 se la base è 0. */
function nl_rep_pct(int $n, int $base, int $dec = 1): float {
    return $base > 0 ? round($n * 100 / $base, $dec) : 0.0;
}

/** Come si scrive una percentuale per chi legge: 12,5%. */
function nl_rep_pct_testo(float $v): string {
    return str_replace('.', ',', (string)$v) . '%';
}

/**
 * I numeri di più campagne in poche query: [id => numeri]. Unici = persone (COUNT DISTINCT, come prima della 2.14.0),
 * totali = somma di `volte`. I rimbalzi temporanei non contano chi ha anche un rimbalzo definitivo.
 */
function nl_rep_numeri_molte(array $ids): array {
    nl_rep_schema();
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($x) => $x > 0)));
    if (!$ids) return [];
    $ev = tbl('newsletter_eventi'); $co = tbl('newsletter_coda');
    $out = [];
    foreach (array_chunk($ids, 500) as $pezzo) {
        $in = implode(',', $pezzo);
        foreach (db()->query('SELECT id, destinatari, inviate, errori FROM ' . tbl('newsletter_campagne') . " WHERE id IN ($in)") as $c) {
            $out[(int)$c['id']] = ['destinatari' => (int)$c['destinatari'], 'inviate' => (int)$c['inviate'], 'errori' => (int)$c['errori'],
                'apertura' => 0, 'apertura_tot' => 0, 'clic' => 0, 'clic_tot' => 0, 'uscita' => 0, 'spam' => 0, 'rimbalzo' => 0, 'soft' => 0, 'rimbalzati_dopo' => 0];
        }
        foreach (db()->query("SELECT campagna_id, tipo, COUNT(DISTINCT iscritto_id) AS u, SUM(volte) AS t FROM $ev WHERE campagna_id IN ($in) AND tipo IN ('apertura', 'clic', 'uscita', 'spam', 'rimbalzo') GROUP BY campagna_id, tipo") as $r) {
            if (!isset($out[(int)$r['campagna_id']])) continue;
            $out[(int)$r['campagna_id']][$r['tipo']] = (int)$r['u'];
            if (in_array($r['tipo'], ['apertura', 'clic'], true)) $out[(int)$r['campagna_id']][$r['tipo'] . '_tot'] = (int)$r['t'];
        }
        foreach (db()->query("SELECT s.campagna_id, COUNT(DISTINCT s.iscritto_id) AS n FROM $ev s WHERE s.campagna_id IN ($in) AND s.tipo = 'soft'
            AND NOT EXISTS (SELECT 1 FROM $ev h WHERE h.campagna_id = s.campagna_id AND h.iscritto_id = s.iscritto_id AND h.tipo = 'rimbalzo') GROUP BY s.campagna_id") as $r) {
            if (isset($out[(int)$r['campagna_id']])) $out[(int)$r['campagna_id']]['soft'] = (int)$r['n'];
        }
        // Chi è rimbalzato dopo che l'email era partita (avviso del servizio): non è consegnata.
        foreach (db()->query("SELECT e.campagna_id, COUNT(DISTINCT e.iscritto_id) AS n FROM $ev e JOIN $co q ON q.campagna_id = e.campagna_id AND q.iscritto_id = e.iscritto_id AND q.stato = 'inviata'
            WHERE e.campagna_id IN ($in) AND e.tipo IN ('rimbalzo', 'soft') GROUP BY e.campagna_id") as $r) {
            if (isset($out[(int)$r['campagna_id']])) $out[(int)$r['campagna_id']]['rimbalzati_dopo'] = (int)$r['n'];
        }
    }
    foreach ($out as $id => $n) $out[$id] = nl_rep_tassi($n);
    return $out;
}

/** Consegnate, provate e tassi di un insieme di numeri (una campagna o la somma di un periodo). */
function nl_rep_tassi(array $n): array {
    $n['consegnate'] = max(0, $n['inviate'] - $n['rimbalzati_dopo']);
    $n['provate'] = $n['inviate'] + $n['errori'];
    $n['t_apertura'] = nl_rep_pct($n['apertura'], $n['consegnate']);
    $n['t_clic'] = nl_rep_pct($n['clic'], $n['consegnate']);
    $n['t_ctor'] = nl_rep_pct($n['clic'], $n['apertura']);
    $n['clic_su_aperture'] = $n['clic']; // chi ha cliccato, sul numero di chi ha aperto (il tasso è t_ctor)
    $n['t_uscita'] = nl_rep_pct($n['uscita'], $n['consegnate']);
    $n['t_spam'] = nl_rep_pct($n['spam'], $n['consegnate'], 2);
    $n['t_rimbalzo'] = nl_rep_pct($n['rimbalzo'], $n['provate']);
    $n['t_soft'] = nl_rep_pct($n['soft'], $n['provate']);
    $n['soglie'] = [];
    if ($n['t_rimbalzo'] > NL_SOGLIE['rimbalzi'][0]) $n['soglie']['rimbalzi'] = NL_SOGLIE['rimbalzi'][1];
    if ($n['t_uscita'] > NL_SOGLIE['uscite'][0]) $n['soglie']['uscite'] = NL_SOGLIE['uscite'][1];
    if ($n['t_spam'] > NL_SOGLIE['spam'][0]) $n['soglie']['spam'] = NL_SOGLIE['spam'][1];
    return $n;
}

function nl_rep_numeri(int $id): array {
    return nl_rep_numeri_molte([$id])[$id] ?? nl_rep_tassi(['destinatari' => 0, 'inviate' => 0, 'errori' => 0, 'apertura' => 0, 'apertura_tot' => 0, 'clic' => 0, 'clic_tot' => 0, 'uscita' => 0, 'spam' => 0, 'rimbalzo' => 0, 'soft' => 0, 'rimbalzati_dopo' => 0]);
}

/** I link cliccati: [url, unici, totali, quota dei clic in percentuale], dal più cliccato. */
function nl_rep_link(int $id): array {
    nl_rep_schema();
    $st = db()->prepare('SELECT url, COUNT(DISTINCT iscritto_id) AS unici, SUM(volte) AS totali FROM ' . tbl('newsletter_eventi') . " WHERE campagna_id = ? AND tipo = 'clic' AND url <> '' GROUP BY url ORDER BY totali DESC, url LIMIT 200");
    $st->execute([$id]);
    $r = $st->fetchAll();
    $tot = array_sum(array_map(fn($x) => (int)$x['totali'], $r));
    foreach ($r as &$x) { $x['unici'] = (int)$x['unici']; $x['totali'] = (int)$x['totali']; $x['quota'] = nl_rep_pct($x['totali'], $tot); }
    return $r;
}

/** L'HTML dell'anteprima con, dopo ogni link, quanti clic ha avuto e la sua quota (la mappa dei clic). */
function nl_rep_mappa(array $c): string {
    $link = [];
    foreach (nl_rep_link((int)$c['id']) as $l) $link[$l['url']] = $l;
    [, $html] = newsletter_email($c, ['id' => 0, 'nome' => 'Giulia', 'email' => ''], []);
    // Stili in linea: l'anteprima è un iframe in sandbox. Colori fissi: sono dentro l'email, come il resto del suo HTML.
    return preg_replace_callback('~(<a\b[^>]*\bhref="([^"]+)"[^>]*>)(.*?)(</a>)~is', function ($m) use ($link) {
        $u = html_entity_decode($m[2]);
        if (!preg_match('~^https?://~i', $u) || preg_match('~/newsletter-(esci|preferenze|web)/~', $u)) return $m[0]; // i link che non passano dal conteggio
        $l = $link[$u] ?? null;
        $t = $l ? $l['totali'] . ' clic, ' . $l['unici'] . ' persone, ' . nl_rep_pct_testo($l['quota']) : 'nessun clic';
        return '<span style="outline:2px solid ' . ($l ? '#b91c1c' : '#6b7280') . ';outline-offset:2px;border-radius:3px">' . $m[1] . $m[3] . $m[4] . '</span>'
            . '<span data-mappa="' . h($u) . '" style="display:inline-block;margin:2px 4px;padding:1px 6px;border-radius:6px;font:700 12px/1.5 Arial,sans-serif;background:' . ($l ? '#b91c1c' : '#4b5563') . ';color:#fff">' . h($t) . '</span>';
    }, $html) ?? $html;
}

/** La parte FROM ... WHERE dell'elenco $tipo, sui contatti `i`, con quando, volte e dettaglio. $url solo per i clic. */
function nl_rep_elenco_sql(int $id, string $tipo, string $url, array &$par): ?array {
    $ev = tbl('newsletter_eventi'); $co = tbl('newsletter_coda'); $is = tbl('newsletter_iscritti');
    $par = [$id];
    switch ($tipo) {
        case 'aperto': case 'uscito': case 'spam': case 'rimbalzo':
            $t = ['aperto' => 'apertura', 'uscito' => 'uscita', 'spam' => 'spam', 'rimbalzo' => 'rimbalzo'][$tipo];
            $det = $tipo === 'uscito' ? '(SELECT m.motivo FROM ' . tbl('newsletter_uscite_motivi') . ' m WHERE m.contatto_id = i.id AND m.campagna_id = e.campagna_id ORDER BY m.id DESC LIMIT 1)'
                : ($tipo === 'rimbalzo' ? "(SELECT q.errore FROM $co q WHERE q.campagna_id = e.campagna_id AND q.iscritto_id = i.id LIMIT 1)" : "''");
            return ["e.creato AS quando, e.volte, $det AS dettaglio", "FROM $ev e JOIN $is i ON i.id = e.iscritto_id WHERE e.campagna_id = ? AND e.tipo = '$t' AND e.url = ''", 'e.creato DESC, i.id'];
        case 'soft':
            return ["e.creato AS quando, e.volte, (SELECT q.errore FROM $co q WHERE q.campagna_id = e.campagna_id AND q.iscritto_id = i.id LIMIT 1) AS dettaglio",
                "FROM $ev e JOIN $is i ON i.id = e.iscritto_id WHERE e.campagna_id = ? AND e.tipo = 'soft' AND NOT EXISTS (SELECT 1 FROM $ev h WHERE h.campagna_id = e.campagna_id AND h.iscritto_id = e.iscritto_id AND h.tipo = 'rimbalzo')", 'e.creato DESC, i.id'];
        case 'cliccato':
            $w = '';
            if ($url !== '') { $w = ' AND e.url = ?'; $par[] = $url; }
            // Una riga per persona: il primo clic, i clic di tutti i link (o di quello scelto), quanti link diversi.
            return ["MIN(e.creato) AS quando, SUM(e.volte) AS volte, COUNT(*) AS dettaglio", "FROM $ev e JOIN $is i ON i.id = e.iscritto_id WHERE e.campagna_id = ? AND e.tipo = 'clic'$w", 'quando DESC, i.id', 'GROUP BY i.id'];
        case 'non_aperto':
            return ["q.inviata_at AS quando, 0 AS volte, '' AS dettaglio", "FROM $co q JOIN $is i ON i.id = q.iscritto_id WHERE q.campagna_id = ? AND q.stato = 'inviata'
                AND NOT EXISTS (SELECT 1 FROM $ev e WHERE e.campagna_id = q.campagna_id AND e.iscritto_id = q.iscritto_id AND e.tipo IN ('apertura', 'rimbalzo', 'soft'))", 'q.inviata_at DESC, i.id'];
    }
    return null;
}

/** Un elenco del report: ['n' => quanti, 'righe' => [...]]. $per = 0: tutte le righe (CSV, a pezzi da chi chiama). */
function nl_rep_elenco(int $id, string $tipo, string $url = '', int $pag = 1, int $per = NL_REP_PER_PAGINA): array {
    nl_rep_schema();
    $par = [];
    $q = nl_rep_elenco_sql($id, $tipo, $url, $par);
    if (!$q) return ['n' => 0, 'righe' => []];
    $gruppo = $q[3] ?? '';
    $st = db()->prepare($gruppo ? "SELECT COUNT(DISTINCT i.id) {$q[1]}" : "SELECT COUNT(*) {$q[1]}");
    $st->execute($par);
    $n = (int)$st->fetchColumn();
    $pag = max(1, min(1000000, $pag));
    $st = db()->prepare("SELECT i.id, i.email, i.nome, i.cognome, i.stato, {$q[0]} {$q[1]} $gruppo ORDER BY {$q[2]} LIMIT " . (int)$per . ' OFFSET ' . (($pag - 1) * $per));
    $st->execute($par);
    return ['n' => $n, 'righe' => $st->fetchAll()];
}

/** Il CSV di un elenco ('report' = i numeri e i link), scritto in $f (celle con le formule neutralizzate). */
function nl_rep_csv($f, int $id, string $tipo, string $url = ''): void {
    require_once __DIR__ . '/sicurezza-html.php';
    $riga = fn(array $r) => fputcsv($f, sicurezza_csv_riga($r), ';', '"', '');
    fwrite($f, "\xEF\xBB\xBF");
    if ($tipo === 'report') {
        $n = nl_rep_numeri($id);
        $c = newsletter_campagna($id);
        $riga(['VOCE', 'VALORE', 'TASSO']);
        $riga(['Campagna', newsletter_4byte_fuori((string)($c['nome'] ?: $c['oggetto'])), '']);
        $riga(['Oggetto', newsletter_4byte_fuori((string)$c['oggetto']), '']);
        $riga(['Inviata il', (string)$c['invio_at'], '']);
        foreach (nl_rep_voci() as $k => [$et, $tasso]) $riga([$et, $n[$k], $tasso ? nl_rep_pct_testo($n[$tasso]) : '']);
        $riga([]);
        $riga(['LINK', 'CLIC UNICI', 'CLIC TOTALI', 'QUOTA DEI CLIC']);
        foreach (nl_rep_link($id) as $l) $riga([$l['url'], $l['unici'], $l['totali'], nl_rep_pct_testo($l['quota'])]);
        return;
    }
    $riga(['EMAIL', 'NOME', 'COGNOME', 'QUANDO', 'VOLTE', 'DETTAGLIO']);
    for ($pag = 1; ; $pag++) {
        $e = nl_rep_elenco($id, $tipo, $url, $pag, 1000);
        foreach ($e['righe'] as $r) $riga([$r['email'], newsletter_4byte_fuori((string)$r['nome']), newsletter_4byte_fuori((string)$r['cognome']), (string)$r['quando'], (int)$r['volte'], nl_rep_dettaglio($tipo, $r)]);
        if (count($e['righe']) < 1000) break;
    }
}

/** Il dettaglio di una riga di un elenco, in parole. */
function nl_rep_dettaglio(string $tipo, array $r): string {
    $d = (string)($r['dettaglio'] ?? '');
    if ($tipo === 'uscito') return $d !== '' ? (NL_USCITA_MOTIVI[$d] ?? $d) : 'senza risposta al questionario';
    if ($tipo === 'cliccato') return (int)$d === 1 ? '1 link' : (int)$d . ' link';
    return newsletter_4byte_fuori($d);
}

/** Le voci dei numeri: chiave => [etichetta, chiave del tasso o '']. */
function nl_rep_voci(): array {
    return ['destinatari' => ['Destinatari', ''], 'inviate' => ['Inviate', ''], 'consegnate' => ['Consegnate', ''],
        'apertura' => ['Aperture uniche', 't_apertura'], 'apertura_tot' => ['Aperture totali', ''], 'clic' => ['Clic unici', 't_clic'],
        'clic_tot' => ['Clic totali', ''], 'clic_su_aperture' => ['Clic sulle aperture', 't_ctor'], 'uscita' => ['Disiscrizioni', 't_uscita'],
        'rimbalzo' => ['Rimbalzi definitivi', 't_rimbalzo'], 'soft' => ['Rimbalzi temporanei', 't_soft'], 'spam' => ['Segnalazioni di spam', 't_spam']];
}

/** Mette i contatti di un elenco in una lista nuova (le automazioni della lista non partono). Ritorna [id, quanti] o l'errore. */
function nl_rep_in_lista(int $id, string $tipo, string $url, string $nome) {
    $par = [];
    $q = nl_rep_elenco_sql($id, $tipo, $url, $par);
    if (!$q) return 'Elenco sconosciuto.';
    $nome = trim(strip_tags($nome)); // un nome, non dell'HTML
    $l = newsletter_lista_crea($nome, 0, 'Dal report della campagna n. ' . $id);
    if (is_string($l)) return $l;
    array_unshift($par, $l);
    $st = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti_liste') . " (iscritto_id, lista_id, aggiunto) SELECT DISTINCT i.id, ?, NOW() {$q[1]}");
    $st->execute($par);
    return [$l, $st->rowCount()];
}

/** Un segmento che segue l'elenco (chi ha aperto o cliccato questa campagna, o un suo link). Ritorna l'id o l'errore. */
function nl_rep_segmento(int $id, string $tipo, string $url, string $nome) {
    if (!function_exists('newsletter_segmento_salva') || empty(NL_REP_ELENCHI[$tipo][2])) return 'Questo elenco non si salva come segmento: salvalo come lista.';
    $nome = trim(strip_tags($nome));
    $cond = $tipo === 'aperto' ? ['campo' => 'email', 'op' => 'aperta', 'campagna' => $id]
        : ($url !== '' ? ['campo' => 'link', 'op' => 'cliccato', 'valore' => $url, 'campagna' => $id] : ['campo' => 'email', 'op' => 'cliccata', 'campagna' => $id]);
    return newsletter_segmento_salva(0, $nome, ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [$cond]]]], 0, '', (int)($_SESSION['admin_user_id'] ?? 0));
}

// ------------------------------------------------------------
// Periodi
// ------------------------------------------------------------

/** Le scelte del periodo: chiave => [etichetta, giorni]. */
const NL_REP_PERIODI = ['7' => ['Ultimi 7 giorni', 7], '30' => ['Ultimi 30 giorni', 30], '90' => ['Ultimi 90 giorni', 90], '365' => ['Ultimi 12 mesi', 365], 'tutto' => ['Da sempre', 0], 'scelto' => ['Dal giorno al giorno', 0]];

/** Il periodo dai parametri: [da Y-m-d, a Y-m-d, chiave, errore]. Un valore storto torna agli ultimi 30 giorni, con l'errore. */
function nl_rep_periodo(array $q): array {
    $k = is_string($q['periodo'] ?? null) && isset(NL_REP_PERIODI[$q['periodo']]) ? $q['periodo'] : '30';
    $err = isset($q['periodo']) && $k !== $q['periodo'] ? 'Il periodo scelto non esiste: ti mostro gli ultimi 30 giorni.' : '';
    $oggi = date('Y-m-d');
    if ($k === 'scelto') {
        $d = fn($v) => is_string($v) && ($x = DateTime::createFromFormat('!Y-m-d', $v)) && $x->format('Y-m-d') === $v ? $v : null;
        $da = $d($q['da'] ?? null); $a = $d($q['a'] ?? null);
        if (!$da || !$a || $da > $a || $da < '2000-01-01' || $a > '2100-12-31') return [date('Y-m-d', strtotime('-29 days')), $oggi, '30', 'Le date del periodo non vanno bene: scrivi il primo giorno e l\'ultimo, il primo prima dell\'ultimo. Ti mostro gli ultimi 30 giorni.'];
        return [$da, $a, 'scelto', ''];
    }
    if ($k === 'tutto') {
        $p = (string)db()->query('SELECT MIN(invio_at) FROM ' . tbl('newsletter_campagne') . " WHERE invio_at IS NOT NULL")->fetchColumn();
        $q2 = (string)db()->query('SELECT MIN(creato) FROM ' . tbl('newsletter_iscritti'))->fetchColumn();
        $da = min(array_filter([substr($p, 0, 10), substr($q2, 0, 10), $oggi]));
        return [$da, $oggi, 'tutto', ''];
    }
    return [date('Y-m-d', strtotime('-' . (NL_REP_PERIODI[$k][1] - 1) . ' days')), $oggi, $k, $err];
}

/** Il passo dell'andamento per un periodo: [formato MySQL, formato PHP, etichetta]. */
function nl_rep_passo(string $da, string $a): array {
    $g = (strtotime($a) - strtotime($da)) / 86400;
    if ($g <= 45) return ['%Y-%m-%d', 'Y-m-d', 'giorno'];
    if ($g <= 200) return ['%x-%v', 'o-W', 'settimana'];
    return ['%Y-%m', 'Y-m', 'mese'];
}

/** Le chiavi dell'andamento, dalla prima all'ultima, con la loro etichetta: [chiave => etichetta]. */
function nl_rep_chiavi(string $da, string $a): array {
    [, $fmt, $passo] = nl_rep_passo($da, $a);
    $out = [];
    $mesi = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
    for ($t = strtotime($da); $t <= strtotime($a) && count($out) < 400; $t = strtotime('+1 day', $t)) {
        $k = date($fmt, $t);
        if (isset($out[$k])) continue;
        $out[$k] = $passo === 'giorno' ? date('d/m', $t) : ($passo === 'settimana' ? 'dal ' . date('d/m', $t) : $mesi[(int)date('n', $t) - 1] . ' ' . date('Y', $t));
    }
    return $out;
}

/** Le campagne partite nel periodo, con i loro numeri, dalla più recente (al massimo 300). $lista: solo quelle mandate a lei. */
function nl_rep_campagne_periodo(string $da, string $a, int $lista = 0): array {
    nl_rep_schema();
    $par = [$da . ' 00:00:00', $a . ' 23:59:59'];
    $w = '';
    if ($lista > 0) { $w = ' AND FIND_IN_SET(?, liste)'; $par[] = (string)$lista; }
    $st = db()->prepare('SELECT id, nome, oggetto, tipo, stato, invio_at FROM ' . tbl('newsletter_campagne') . " WHERE stato IN ('in_invio', 'sospesa', 'inviata', 'annullata') AND invio_at BETWEEN ? AND ?$w ORDER BY invio_at DESC, id DESC LIMIT 300");
    $st->execute($par);
    $c = $st->fetchAll();
    $n = nl_rep_numeri_molte(array_column($c, 'id'));
    foreach ($c as &$x) $x['n'] = $n[(int)$x['id']];
    return $c;
}

/** La somma dei numeri di più campagne, con i tassi sulla somma. */
function nl_rep_somma(array $campagne): array {
    $s = ['destinatari' => 0, 'inviate' => 0, 'errori' => 0, 'apertura' => 0, 'apertura_tot' => 0, 'clic' => 0, 'clic_tot' => 0, 'uscita' => 0, 'spam' => 0, 'rimbalzo' => 0, 'soft' => 0, 'rimbalzati_dopo' => 0];
    foreach ($campagne as $c) foreach ($s as $k => $v) $s[$k] += (int)$c['n'][$k];
    return nl_rep_tassi($s);
}

/** L'andamento delle campagne: [chiave => somma dei numeri delle campagne partite in quel giorno, settimana o mese]. */
function nl_rep_andamento(array $campagne, string $da, string $a): array {
    [, $fmt] = nl_rep_passo($da, $a);
    $g = [];
    foreach ($campagne as $c) $g[date($fmt, strtotime((string)$c['invio_at']))][] = $c;
    $out = [];
    foreach (nl_rep_chiavi($da, $a) as $k => $et) $out[$k] = ['etichetta' => $et, 'n' => nl_rep_somma($g[$k] ?? [])];
    return $out;
}

/**
 * La crescita di una lista (0 = tutti i contatti) nel periodo: per ogni passo i nuovi (entrati nella lista, o creati), gli
 * usciti (in lista nera, o usciti solo da questa lista) e gli iscritti alla fine del passo. Lo stato è quello di oggi: chi
 * è uscito e poi rientrato conta come iscritto.
 */
function nl_rep_lista(int $lista, string $da, string $a): array {
    nl_rep_schema();
    [$fm] = nl_rep_passo($da, $a);
    $is = tbl('newsletter_iscritti'); $il = tbl('newsletter_iscritti_liste'); $lu = tbl('newsletter_liste_uscite');
    $bl = "'" . implode("','", NL_BLOCCATI) . "'";
    $p = [$da . ' 00:00:00', $a . ' 23:59:59'];
    $conta = function (string $sql, array $par) use ($fm) { $st = db()->prepare(str_replace('{fmt}', $fm, $sql)); $st->execute($par); return array_column($st->fetchAll(), 'n', 'k'); };
    if ($lista > 0) {
        $nuovi = $conta("SELECT DATE_FORMAT(l.aggiunto, '{fmt}') AS k, COUNT(*) AS n FROM $il l WHERE l.lista_id = ? AND l.aggiunto BETWEEN ? AND ? GROUP BY k", array_merge([$lista], $p));
        $usciti = $conta("SELECT DATE_FORMAT(x.q, '{fmt}') AS k, COUNT(*) AS n FROM (SELECT i.id, LEAST(COALESCE(i.blocco_at, '9999-12-31'), COALESCE(u.quando, '9999-12-31')) AS q FROM $il l JOIN $is i ON i.id = l.iscritto_id
            LEFT JOIN $lu u ON u.contatto_id = i.id AND u.lista_id = l.lista_id WHERE l.lista_id = ? AND ((i.stato IN ($bl) AND i.blocco_at IS NOT NULL) OR u.quando IS NOT NULL)) x WHERE x.q BETWEEN ? AND ? GROUP BY k", array_merge([$lista], $p));
        // Iscritto alla fine del passo (?): entrato prima, non in attesa, non in lista nera da prima, non uscito dalla lista da prima.
        $cond = "l.aggiunto <= ? AND i.stato <> 'in_attesa' AND NOT (i.stato IN ($bl) AND COALESCE(i.blocco_at, '1970-01-01') <= ?) AND NOT (u.quando IS NOT NULL AND u.quando <= ?)";
        $base = "FROM $il l JOIN $is i ON i.id = l.iscritto_id LEFT JOIN $lu u ON u.contatto_id = i.id AND u.lista_id = l.lista_id WHERE l.lista_id = " . (int)$lista;
        $quanti = 3;
    } else {
        $nuovi = $conta("SELECT DATE_FORMAT(creato, '{fmt}') AS k, COUNT(*) AS n FROM $is WHERE creato BETWEEN ? AND ? GROUP BY k", $p);
        $usciti = $conta("SELECT DATE_FORMAT(blocco_at, '{fmt}') AS k, COUNT(*) AS n FROM $is WHERE stato IN ($bl) AND blocco_at BETWEEN ? AND ? GROUP BY k", $p);
        $cond = "i.creato <= ? AND i.stato <> 'in_attesa' AND NOT (i.stato IN ($bl) AND COALESCE(i.blocco_at, '1970-01-01') <= ?)";
        $base = "FROM $is i";
        $quanti = 2;
    }
    $chiavi = nl_rep_chiavi($da, $a);
    // La fine di ogni passo: l'ultimo giorno del periodo che ha quella chiave. Tutti i passi in una query sola (una lettura).
    [, $fmt] = nl_rep_passo($da, $a);
    $fine = [];
    for ($t = strtotime($da); $t <= strtotime($a); $t = strtotime('+1 day', $t)) $fine[date($fmt, $t)] = date('Y-m-d 23:59:59', $t);
    $sel = []; $par = [];
    foreach (array_keys($chiavi) as $j => $k) {
        $sel[] = "SUM($cond) AS p$j";
        for ($x = 0; $x < $quanti; $x++) $par[] = $fine[$k] ?? $a . ' 23:59:59';
    }
    $st = db()->prepare('SELECT ' . implode(', ', $sel) . " $base");
    $st->execute($par);
    $tot = $st->fetch(PDO::FETCH_NUM) ?: [];
    $out = [];
    foreach (array_keys($chiavi) as $j => $k) $out[$k] = ['etichetta' => $chiavi[$k], 'nuovi' => (int)($nuovi[$k] ?? 0), 'usciti' => (int)($usciti[$k] ?? 0), 'iscritti' => (int)($tot[$j] ?? 0)];
    return $out;
}

/**
 * Un grafico a linee in SVG, fatto dal server (nessuna libreria). $serie: [[nome, classe, [valori]], ...] con la stessa
 * unità (un asse solo). Ogni punto ha il suo titolo (al passaggio del mouse); i numeri stanno anche nella tabella sotto.
 */
function nl_rep_svg(array $etichette, array $serie, string $titolo, string $unita = ''): string {
    $w = 720; $h = 240; $sx = 46; $dx = 12; $su = 14; $giu = 34;
    $n = count($etichette);
    $max = 0;
    foreach ($serie as $s) foreach ($s[2] as $v) $max = max($max, (float)$v);
    $passi = [1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000, 10000, 20000, 50000, 100000, 1000000];
    $tacca = 1; foreach ($passi as $p) { $tacca = $p; if ($max / $p <= 4) break; }
    $cima = max($tacca, ceil($max / $tacca) * $tacca);
    $x = fn($i) => $n > 1 ? $sx + ($w - $sx - $dx) * $i / ($n - 1) : $sx + ($w - $sx - $dx) / 2;
    $y = fn($v) => $su + ($h - $su - $giu) * (1 - $v / $cima);
    $et = array_values($etichette);
    $o = '<svg class="st-grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . h($titolo) . '" preserveAspectRatio="xMidYMid meet"><title>' . h($titolo) . '</title>';
    for ($v = 0; $v <= $cima + 1e-9; $v += $tacca) {
        $o .= '<line class="st-griglia" x1="' . $sx . '" x2="' . ($w - $dx) . '" y1="' . round($y($v), 1) . '" y2="' . round($y($v), 1) . '"/>'
            . '<text class="st-asse" x="' . ($sx - 6) . '" y="' . round($y($v) + 4, 1) . '" text-anchor="end">' . h(str_replace('.', ',', (string)$v) . $unita) . '</text>';
    }
    $ogni = max(1, (int)ceil($n / 8));
    foreach ($et as $i => $e) if ($i % $ogni === 0 || $i === $n - 1) $o .= '<text class="st-asse" x="' . round($x($i), 1) . '" y="' . ($h - 12) . '" text-anchor="middle">' . h($e) . '</text>';
    foreach ($serie as $k => [$nome, $classe, $valori]) {
        $valori = array_values($valori);
        $pt = [];
        foreach ($valori as $i => $v) $pt[] = round($x($i), 1) . ',' . round($y((float)$v), 1);
        if ($n > 1) $o .= '<polyline class="st-linea ' . h($classe) . '" points="' . implode(' ', $pt) . '"/>';
        foreach ($valori as $i => $v) {
            $cx = round($x($i), 1); $cy = round($y((float)$v), 1);
            $tt = '<title>' . h($nome . ', ' . $et[$i] . ': ' . str_replace('.', ',', (string)$v) . $unita) . '</title>';
            // Il segno cambia con la serie (cerchio, quadrato): il colore non è l'unico modo di distinguerle.
            $o .= $k % 2 === 0 ? '<circle class="st-punto ' . h($classe) . '" cx="' . $cx . '" cy="' . $cy . '" r="4">' . $tt . '</circle>'
                : '<rect class="st-punto ' . h($classe) . '" x="' . ($cx - 4) . '" y="' . ($cy - 4) . '" width="8" height="8">' . $tt . '</rect>';
        }
    }
    return $o . '</svg>';
}

// ------------------------------------------------------------
// Orario migliore
// ------------------------------------------------------------

/**
 * L'ora in cui ogni contatto apre di solito: [id => ora 0-23], solo per chi ha almeno NL_ORARIO_MIN aperture negli ultimi
 * NL_ORARIO_GIORNI giorni (campagne e automazioni; la prima apertura di ogni email, nel fuso del sito). L'ora con più
 * aperture; a parità, quella dell'apertura più recente.
 */
function nl_orario_ore(array $ids): array {
    nl_rep_schema();
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($x) => $x > 0)));
    $out = [];
    $da = date('Y-m-d H:i:s', time() - NL_ORARIO_GIORNI * 86400);
    foreach (array_chunk($ids, 1000) as $pezzo) {
        $st = db()->prepare('SELECT iscritto_id, HOUR(creato) AS ora, COUNT(*) AS n, MAX(creato) AS ultima FROM ' . tbl('newsletter_eventi') . " WHERE tipo = 'apertura' AND creato >= ? AND iscritto_id IN (" . implode(',', $pezzo) . ') GROUP BY iscritto_id, ora');
        $st->execute([$da]);
        $per = [];
        foreach ($st->fetchAll() as $r) $per[(int)$r['iscritto_id']][] = $r;
        foreach ($per as $id => $ore) {
            if (array_sum(array_column($ore, 'n')) < NL_ORARIO_MIN) continue;
            usort($ore, fn($a, $b) => [(int)$b['n'], (string)$b['ultima']] <=> [(int)$a['n'], (string)$a['ultima']]);
            $out[$id] = (int)$ore[0]['ora'];
        }
    }
    return $out;
}

/** All'avvio di una campagna all'orario migliore: ogni riga della coda parte all'ora del suo contatto nel giorno scelto. */
function nl_orario_applica(int $id, array $c): string {
    $giorno = date('Y-m-d', strtotime((string)$c['invio_at']) ?: time());
    $pred = max(0, min(23, (int)($c['orario_ora'] ?? 10)));
    $righe = db()->query('SELECT iscritto_id FROM ' . tbl('newsletter_coda') . " WHERE campagna_id = $id AND stato = 'attesa'")->fetchAll(PDO::FETCH_COLUMN);
    $ore = nl_orario_ore($righe);
    $per = [];
    foreach ($righe as $i) $per[$ore[(int)$i] ?? $pred][] = (int)$i;
    foreach ($per as $h => $ids) {
        $quando = sprintf('%s %02d:00:00', $giorno, $h);
        $dopo = strtotime($quando) > time() ? $quando : null; // un'ora già passata oggi: parte subito
        foreach (array_chunk($ids, 1000) as $pezzo) db()->prepare('UPDATE ' . tbl('newsletter_coda') . ' SET lotto = 1, dopo = ? WHERE campagna_id = ? AND iscritto_id IN (' . implode(',', $pezzo) . ')')->execute([$dopo, $id]);
    }
    return 'all\'orario migliore: ' . count(array_intersect_key($ore, array_flip(array_map('intval', $righe)))) . ' con il loro orario, gli altri alle ' . sprintf('%02d:00', $pred);
}

/** Per la sezione Programma: [quanti destinatari, quanti con un orario loro, [ora => quanti]]. */
function nl_orario_riassunto(array $c): array {
    if (!function_exists('newsletter_campagna_where')) return [0, 0, []];
    $par = [];
    $w = newsletter_campagna_where($c, $par);
    if ($w === '') return [0, 0, []];
    $st = db()->prepare('SELECT i.id FROM ' . tbl('newsletter_iscritti') . " i WHERE i.stato = 'attivo' AND $w");
    $st->execute($par);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    $ore = nl_orario_ore($ids);
    $per = array_count_values($ore);
    ksort($per);
    return [count($ids), count($ore), $per];
}

// ------------------------------------------------------------
// Pezzi di pagina comuni al riepilogo della campagna e al report
// ------------------------------------------------------------

/** Il riquadro dei risultati del test A/B (prima stava solo nel riepilogo della campagna). */
function nl_rep_ab_html(array $c, array $ab): string {
    $id = (int)$c['id'];
    $abR = nl_ab_risultati($id); $abV = (string)$ab['vincitore'];
    $crit = mb_strtolower(NL_AB_CRITERI[$ab['criterio']] ?? '');
    $quando = fn($t) => h(date('d/m/Y H:i', strtotime((string)$t)));
    if ($abV === '') $esito = 'Non ancora: la versione vincente si sceglie dopo il <b>' . h(date('d/m/Y H:i', nl_ab_scade($ab))) . '</b>, secondo ' . h($crit) . '.';
    elseif ($abV === 'pari') $esito = '<b>Pari</b>: le due versioni hanno lo stesso ' . ($ab['criterio'] === 'clic' ? 'tasso di clic' : 'tasso di apertura') . ', e al resto è partita la versione A (scelta il ' . $quando($ab['scelto_at']) . ').';
    else $esito = '<b>Ha vinto la versione ' . strtoupper($abV) . '</b>, per ' . h($crit) . ': scelta il ' . $quando($ab['scelto_at']) . ((int)$ab['resto'] ? ' e mandata agli altri ' . (int)$ab['resto'] . ' contatti' : '') . '.';
    $o = '<div class="form-card" id="nlr-ab-risultati"><h3>Risultati del test A/B</h3><p role="status" class="nlr-ab-esito">' . $esito . '</p>'
        . '<table class="table nlr-ab-tabella"><thead><tr><th>Versione</th><th>' . ($ab['cosa'] === 'oggetto' ? 'Oggetto' : 'Contenuto') . '</th><th>Inviate</th><th>Hanno aperto</th><th>Hanno cliccato</th></tr></thead><tbody>';
    foreach (['a', 'b'] as $v) {
        $x = $abR[$v];
        $segno = $abV === $v ? ' <span class="nlr-stato nlr-ok"><span aria-hidden="true">&#10003;</span> Vincente</span>' : ($abV === 'pari' && $v === 'a' ? ' <span class="nlr-stato nlr-ok">Partita al resto</span>' : '');
        $o .= '<tr data-versione="' . $v . '"><td><b>' . strtoupper($v) . '</b>' . $segno . '</td><td>' . h($ab['cosa'] === 'oggetto' ? newsletter_4byte_fuori((string)nl_ab_versione($c, $v)['oggetto']) : 'Design ' . strtoupper($v)) . '</td>'
            . '<td>' . (int)$x['inviate'] . '</td><td>' . (int)$x['apertura'] . ' (' . $x['tasso_aperture'] . '%)</td><td>' . (int)$x['clic'] . ' (' . $x['tasso_clic'] . '%)</td></tr>';
    }
    if ((int)$ab['resto']) $o .= '<tr data-versione="r"><td>Il resto</td><td>' . ($abV === '' ? 'aspetta la vincente' : 'versione ' . ($abV === 'b' ? 'B' : 'A')) . '</td><td>' . (int)$abR['r']['inviate'] . ' di ' . (int)$ab['resto'] . '</td><td>' . (int)$abR['r']['apertura'] . ' (' . $abR['r']['tasso_aperture'] . '%)</td><td>' . (int)$abR['r']['clic'] . ' (' . $abR['r']['tasso_clic'] . '%)</td></tr>';
    return $o . '</tbody></table><p class="hint">Ogni gruppo di prova: ' . (int)$ab['percentuale'] . '% dei destinatari, scelti a caso; ognuno riceve una versione sola. I tassi del test sono sulle email inviate al gruppo.</p></div>';
}

/** Gli avvisi delle soglie superate: un riquadro con il segno e le frasi (vuoto se nessuna). */
function nl_rep_soglie_html(array $n, string $id = 'nlr-soglie'): string {
    if (!$n['soglie']) return '';
    $o = '<div class="nlr-soglie" id="' . h($id) . '" role="status"><b><span aria-hidden="true">! </span>Da guardare</b><ul>';
    foreach ($n['soglie'] as $k => $t) $o .= '<li data-soglia="' . h($k) . '">' . h($t) . '</li>';
    return $o . '</ul></div>';
}

/** Un numero del report: valore grande, etichetta, tasso; con il segno "!" se supera la sua soglia. */
function nl_rep_tessera(string $k, string $et, $v, string $tasso = '', bool $oltre = false): string {
    return '<div class="nlr-tessera' . ($oltre ? ' nlr-oltre' : '') . '" data-numero="' . h($k) . '"><b>' . h((string)$v) . '</b><span>' . h($et) . '</span>'
        . ($tasso !== '' ? '<span class="nlr-tasso">' . ($oltre ? '<span aria-hidden="true">! </span><span class="sr-solo">oltre la soglia: </span>' : '') . h($tasso) . '</span>' : '') . '</div>';
}

/** Le tessere dei numeri di una campagna o di un periodo. */
function nl_rep_tessere(array $n): string {
    $o = '<div class="nlr-numeri">';
    $soglia = ['t_rimbalzo' => 'rimbalzi', 't_uscita' => 'uscite', 't_spam' => 'spam'];
    foreach (nl_rep_voci() as $k => [$et, $t]) {
        $tasso = $t === '' ? '' : nl_rep_pct_testo($n[$t]) . ($t === 't_ctor' ? ' di chi ha aperto' : ($t === 't_rimbalzo' || $t === 't_soft' ? ' delle provate' : ' delle consegnate'));
        $o .= nl_rep_tessera($k, $et, $n[$k], $tasso, isset($soglia[$t]) && isset($n['soglie'][$soglia[$t]]));
    }
    return $o . '</div>';
}
