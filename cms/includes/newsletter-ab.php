<?php
/**
 * 2.13.0, fase 6 del piano Brevo (docs-sviluppo/brevo-confronto.md, capitoli 1.14 e 3.6): il test A/B delle campagne.
 *
 * Una campagna di tipo 'ab' prova due versioni (l'oggetto, oppure il contenuto) su due gruppi di prova uguali, scelti a
 * caso fra i destinatari (ognuno fino al 50%); dopo la durata (ore, al massimo 23, o giorni, al massimo 7) il cron sceglie
 * la versione con il tasso di apertura o di clic più alto e la manda al resto. Pari: parte la versione A.
 *
 * Dati: newsletter_ab (una riga per campagna: le impostazioni, la versione B, la fase, il vincitore) e la colonna
 * newsletter_coda.versione: 'a' e 'b' i due gruppi di prova, 'r' il resto (stato 'riserva' finché il vincitore non c'è,
 * poi 'attesa' e parte con la versione vincente). La chiave unica (campagna, contatto) della coda resta: una persona riceve
 * una versione sola. Il resto parte una volta sola: la fase passa da 'prova' a 'resto' con un UPDATE condizionato, sotto il
 * blocco della newsletter (newsletter_invia_giro) e un blocco suo.
 *
 * Le campagne normali non passano di qui: stesso HTML al byte. Chi usa queste funzioni da un altro file controlla
 * function_exists (opcache).
 */

require_once __DIR__ . '/newsletter.php';

const NL_AB_COSA = ['oggetto' => "L'oggetto", 'design' => 'Il contenuto'];
const NL_AB_CRITERI = ['aperture' => 'Il tasso di apertura', 'clic' => 'Il tasso di clic'];
const NL_AB_UNITA = ['ore' => [23, 3600], 'giorni' => [7, 86400]];
const NL_AB_PERC_MAX = 50;
/** Le colonne del design che la versione B ha sue (nella tabella con _b in fondo). */
const NL_AB_DESIGN = ['editor', 'corpo', 'pulsante_testo', 'pulsante_link', 'html', 'design', 'testo', 'testo_modificato'];

function nl_ab_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $sql = 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_ab') . " (
        `campagna_id` INT(11) NOT NULL,
        `cosa` VARCHAR(8) NOT NULL DEFAULT 'oggetto',
        `oggetto_b` VARCHAR(255) NOT NULL DEFAULT '',
        `anteprima_b` VARCHAR(255) NOT NULL DEFAULT '',
        `editor_b` VARCHAR(8) NOT NULL DEFAULT '',
        `corpo_b` MEDIUMTEXT NULL,
        `pulsante_testo_b` VARCHAR(100) NOT NULL DEFAULT '',
        `pulsante_link_b` VARCHAR(500) NOT NULL DEFAULT '',
        `html_b` MEDIUMTEXT NULL,
        `design_b` MEDIUMTEXT NULL,
        `testo_b` MEDIUMTEXT NULL,
        `testo_modificato_b` TINYINT(1) NOT NULL DEFAULT 0,
        `percentuale` TINYINT NOT NULL DEFAULT 20,
        `criterio` VARCHAR(8) NOT NULL DEFAULT 'aperture',
        `durata` INT(11) NOT NULL DEFAULT 4,
        `unita` VARCHAR(6) NOT NULL DEFAULT 'ore',
        `fase` VARCHAR(8) NOT NULL DEFAULT '',
        `gruppo` INT(11) NOT NULL DEFAULT 0,
        `resto` INT(11) NOT NULL DEFAULT 0,
        `avviato_at` DATETIME DEFAULT NULL,
        `vincitore` VARCHAR(4) NOT NULL DEFAULT '',
        `scelto_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`campagna_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $impronta = md5($sql . DB_NAME . 'coda-versione');
    if (get_setting('newsletter_ab_impronta', '') === $impronta) return;
    newsletter_schema();
    db()->exec($sql);
    $q = tbl('newsletter_coda');
    $col = db()->query('SHOW COLUMNS FROM ' . $q)->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('versione', $col, true)) db()->exec("ALTER TABLE $q ADD COLUMN `versione` CHAR(1) NOT NULL DEFAULT 'a'");
    save_setting('newsletter_ab_impronta', $impronta);
}

/** La riga del test A/B di una campagna (con i valori di partenza se manca). */
function nl_ab(int $id): array {
    nl_ab_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_ab') . ' WHERE campagna_id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if ($r) return $r;
    db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_ab') . ' (campagna_id) VALUES (?)')->execute([$id]);
    $st->execute([$id]);
    return $st->fetch() ?: ['campagna_id' => $id, 'cosa' => 'oggetto', 'percentuale' => 20, 'criterio' => 'aperture', 'durata' => 4, 'unita' => 'ore', 'fase' => '', 'vincitore' => '', 'editor_b' => ''];
}

function nl_ab_e(array $c): bool { return ($c['tipo'] ?? '') === 'ab'; }

/** La campagna come la vede chi riceve la versione $v ('a', 'b', o 'r' il resto: la vincente). Le normali restano come sono. */
function nl_ab_versione(array $c, string $v): array {
    if (!nl_ab_e($c)) return $c;
    $ab = nl_ab((int)$c['id']);
    if ($v === 'r') $v = $ab['vincitore'] === 'b' ? 'b' : 'a';
    if ($v !== 'b') return $c;
    if ($ab['cosa'] === 'oggetto') { $c['oggetto'] = $ab['oggetto_b']; $c['anteprima'] = $ab['anteprima_b']; return $c; }
    if ($ab['editor_b'] === '') return $c;
    foreach (NL_AB_DESIGN as $k) $c[$k] = $ab[$k . '_b'];
    $c['modello_id'] = 0;
    return $c;
}

/** La versione che un contatto ha ricevuto ('a', 'b' o 'r'), per la versione web. */
function nl_ab_versione_di(int $campagna, int $contatto): string {
    nl_ab_schema();
    $st = db()->prepare('SELECT versione FROM ' . tbl('newsletter_coda') . ' WHERE campagna_id = ? AND iscritto_id = ?');
    $st->execute([$campagna, $contatto]);
    return (string)($st->fetchColumn() ?: 'a');
}

/** Scrive i campi del design della versione B ($set con i nomi delle colonne della campagna). */
function nl_ab_scrivi_b(int $id, array $set): void {
    $sql = []; $val = [];
    foreach ($set as $k => $v) if (in_array($k, NL_AB_DESIGN, true)) { $sql[] = "`{$k}_b` = ?"; $val[] = $v; }
    if (!$sql) return;
    nl_ab($id);
    $val[] = $id;
    db()->prepare('UPDATE ' . tbl('newsletter_ab') . ' SET ' . implode(', ', $sql) . ' WHERE campagna_id = ?')->execute($val);
    db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET aggiornato = NOW() WHERE id = ?')->execute([$id]);
}

/** Il design di un modello (o della galleria: $design) va nella versione B. */
function nl_ab_da_modello(int $id, ?array $m, ?array $design = null): string {
    $c = newsletter_campagna($id);
    if (!$c || !newsletter_campagna_modificabile($c)) return 'Questa campagna non si può più modificare: è già partita.';
    if ($m && $m['editor'] === 'html') { nl_ab_scrivi_b($id, ['editor' => 'html', 'html' => (string)$m['html']]); return ''; }
    $d = $design ?? ($m ? nd_design((string)$m['design']) : null);
    if (!$d) return 'Il modello non ha un design da usare.';
    nl_ab_scrivi_b($id, ['editor' => 'blocchi', 'design' => nd_json($d)]);
    return '';
}

/** La durata in secondi. */
function nl_ab_secondi(array $ab): int {
    return max(1, (int)$ab['durata']) * (NL_AB_UNITA[$ab['unita']][1] ?? 3600);
}

/** Quanti contatti per gruppo di prova, con $n destinatari: la percentuale, almeno 1 se ce ne sono due. */
function nl_ab_gruppo(int $n, int $perc): int {
    $g = intdiv($n * max(1, min(NL_AB_PERC_MAX, $perc)), 100);
    return $n >= 2 ? max(1, $g) : 0;
}

/** Salva la sezione Test A/B. Ritorna '' oppure l'errore (e niente si salva). */
function nl_ab_salva(array $c, array $p): string {
    $ab = nl_ab((int)$c['id']);
    $cosa = (string)($p['cosa'] ?? '');
    if (!isset(NL_AB_COSA[$cosa])) return "Scegli cosa provare: l'oggetto oppure il contenuto.";
    $perc = filter_var($p['percentuale'] ?? '', FILTER_VALIDATE_INT);
    if ($perc === false || $perc < 1 || $perc > NL_AB_PERC_MAX) return 'Ogni gruppo di prova va da 1% a ' . NL_AB_PERC_MAX . '% dei destinatari.';
    $crit = (string)($p['criterio'] ?? '');
    if (!isset(NL_AB_CRITERI[$crit])) return 'Scegli come si sceglie la versione vincente: aperture o clic.';
    $unita = (string)($p['unita'] ?? '');
    if (!isset(NL_AB_UNITA[$unita])) return 'Scegli se la durata è in ore o in giorni.';
    $dur = filter_var($p['durata'] ?? '', FILTER_VALIDATE_INT);
    $max = NL_AB_UNITA[$unita][0];
    if ($dur === false || $dur < 1 || $dur > $max) return $unita === 'ore' ? 'La durata in ore va da 1 a 23: per un giorno o più scegli «giorni».' : 'La durata in giorni va da 1 a 7.';
    $riga = fn($k) => newsletter_4byte_dentro(mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($p[$k] ?? ''))), 0, 200));
    $set = ['cosa' => $cosa, 'percentuale' => $perc, 'criterio' => $crit, 'unita' => $unita, 'durata' => $dur];
    if (array_key_exists('oggetto_b', $p)) { $set['oggetto_b'] = $riga('oggetto_b'); $set['anteprima_b'] = $riga('anteprima_b'); }
    // Il contenuto B nasce come copia della A, la prima volta che si sceglie di provare il contenuto.
    if ($cosa === 'design' && $ab['editor_b'] === '') foreach (NL_AB_DESIGN as $k) $set[$k . '_b'] = $c[$k] ?? null;
    $sql = []; $val = [];
    foreach ($set as $k => $v) { $sql[] = "`$k` = ?"; $val[] = $v; }
    $val[] = (int)$c['id'];
    db()->prepare('UPDATE ' . tbl('newsletter_ab') . ' SET ' . implode(', ', $sql) . ' WHERE campagna_id = ?')->execute($val);
    return '';
}

/** Lo stato della sezione Test A/B del riepilogo: ['ok', 'manca']. $n: i destinatari contati adesso. */
function nl_ab_sezione(array $c, int $n): array {
    $ab = nl_ab((int)$c['id']);
    $m = '';
    if ($ab['cosa'] === 'oggetto') {
        $b = trim((string)$ab['oggetto_b']);
        if ($b === '') $m = "Scrivi l'oggetto della versione B nella sezione Test A/B.";
        elseif (trim((string)$c['oggetto']) !== '' && $b === trim((string)$c['oggetto'])) $m = "L'oggetto della versione B è uguale a quello della A: cambialo, sennò il test non prova niente.";
    } elseif ($ab['editor_b'] === '' || !nl_camp_design_stato(nl_ab_versione($c, 'b'))[0]) {
        $m = 'Fai il design della versione B: nella sezione Design scegli «Versione B».';
    }
    if ($m === '' && $n > 0 && $n < 2) $m = 'Per un test A/B servono almeno 2 contatti fra i destinatari, uno per gruppo.';
    return ['ok' => $m === '', 'manca' => $m];
}

/** Dopo che la coda della campagna è pronta: due gruppi di prova a caso, il resto in riserva. Ritorna la riga di log. */
function nl_ab_avvia(int $id): string {
    $ab = nl_ab($id);
    $ids = array_map('intval', db()->query('SELECT id FROM ' . tbl('newsletter_coda') . " WHERE campagna_id = $id AND stato = 'attesa' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
    $n = count($ids);
    $g = nl_ab_gruppo($n, (int)$ab['percentuale']);
    for ($i = $n - 1; $i > 0; $i--) { $j = random_int(0, $i); [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; }
    $q = tbl('newsletter_coda');
    db()->prepare("UPDATE $q SET versione = 'r', stato = 'riserva' WHERE campagna_id = ? AND stato = 'attesa'")->execute([$id]);
    foreach (['a' => array_slice($ids, 0, $g), 'b' => array_slice($ids, $g, $g)] as $v => $parte) {
        foreach (array_chunk($parte, 500) as $pezzo) db()->exec("UPDATE $q SET versione = '$v', stato = 'attesa' WHERE campagna_id = $id AND id IN (" . implode(',', $pezzo) . ')');
    }
    $resto = $n - 2 * $g;
    db()->prepare('UPDATE ' . tbl('newsletter_ab') . " SET fase = 'prova', gruppo = ?, resto = ?, avviato_at = ?, vincitore = '', scelto_at = NULL WHERE campagna_id = ?")
        ->execute([$g, $resto, date('Y-m-d H:i:s'), $id]);
    return "campagna $id, test A/B: $g contatti per versione, $resto aspettano la vincente";
}

/** I numeri delle due versioni: [v => [inviate, aperture, clic, tasso_aperture, tasso_clic]], più 'r' (il resto, solo inviate). */
function nl_ab_risultati(int $id): array {
    nl_ab_schema();
    $out = [];
    foreach (['a', 'b', 'r'] as $v) $out[$v] = ['inviate' => 0, 'apertura' => 0, 'clic' => 0];
    foreach (db()->query('SELECT versione, COUNT(*) AS n FROM ' . tbl('newsletter_coda') . " WHERE campagna_id = $id AND stato = 'inviata' GROUP BY versione") as $r) if (isset($out[$r['versione']])) $out[$r['versione']]['inviate'] = (int)$r['n'];
    foreach (db()->query('SELECT q.versione, e.tipo, COUNT(DISTINCT e.iscritto_id) AS n FROM ' . tbl('newsletter_eventi') . ' e JOIN ' . tbl('newsletter_coda') . " q ON q.campagna_id = e.campagna_id AND q.iscritto_id = e.iscritto_id
        WHERE e.campagna_id = $id AND e.tipo IN ('apertura', 'clic') GROUP BY q.versione, e.tipo") as $r) if (isset($out[$r['versione']])) $out[$r['versione']][$r['tipo']] = (int)$r['n'];
    foreach ($out as $v => $x) {
        $out[$v]['tasso_aperture'] = $x['inviate'] ? round($x['apertura'] * 100 / $x['inviate'], 1) : 0;
        $out[$v]['tasso_clic'] = $x['inviate'] ? round($x['clic'] * 100 / $x['inviate'], 1) : 0;
    }
    return $out;
}

/** Il vincitore secondo il criterio: 'a', 'b' o 'pari' (tassi uguali, anche zero: parte la A). */
function nl_ab_vincitore(array $ris, string $criterio): string {
    $k = $criterio === 'clic' ? 'clic' : 'apertura';
    // Confronto senza arrotondamenti: na/ia contro nb/ib.
    $sa = $ris['a'][$k] * $ris['b']['inviate']; $sb = $ris['b'][$k] * $ris['a']['inviate'];
    if (!$ris['a']['inviate'] || !$ris['b']['inviate']) { $sa = $ris['a']['inviate'] ? $ris['a'][$k] : 0; $sb = $ris['b']['inviate'] ? $ris['b'][$k] : 0; }
    return $sa === $sb ? 'pari' : ($sa > $sb ? 'a' : 'b');
}

/** Sceglie il vincitore e libera il resto, una volta sola. Ritorna la riga di log ('' se l'ha già fatto un altro giro). */
function nl_ab_scegli(int $id): string {
    $ab = nl_ab($id);
    if ($ab['fase'] !== 'prova') return '';
    $ris = nl_ab_risultati($id);
    $v = nl_ab_vincitore($ris, (string)$ab['criterio']);
    $st = db()->prepare('UPDATE ' . tbl('newsletter_ab') . " SET fase = ?, vincitore = ?, scelto_at = ? WHERE campagna_id = ? AND fase = 'prova'");
    $st->execute([(int)$ab['resto'] > 0 ? 'resto' : 'finita', $v, date('Y-m-d H:i:s'), $id]);
    if ($st->rowCount() !== 1) return '';
    $libera = db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET stato = 'attesa' WHERE campagna_id = ? AND stato = 'riserva'");
    $libera->execute([$id]);
    $k = $ab['criterio'] === 'clic' ? 'tasso_clic' : 'tasso_aperture';
    return "campagna $id, test A/B: " . ($v === 'pari' ? 'pari, parte la versione A' : 'vince la versione ' . strtoupper($v)) . " ({$ris['a'][$k]}% contro {$ris['b'][$k]}%), " . $libera->rowCount() . ' email al resto';
}

/** Il passo del cron (dentro newsletter_invia_giro): i test arrivati alla fine della durata scelgono il vincitore. */
function newsletter_ab_giro(): array {
    nl_ab_schema();
    // Il nome del blocco vale per tutto il server MySQL: con il nome del database due siti sullo stesso server non si fermano a vicenda.
    $blocco = db()->quote('nl_ab_' . md5(DB_NAME . '|' . DB_PREFIX));
    if ((int)db()->query("SELECT GET_LOCK($blocco, 0)")->fetchColumn() !== 1) return [];
    try {
        $log = [];
        $righe = db()->query('SELECT a.* FROM ' . tbl('newsletter_ab') . ' a JOIN ' . tbl('newsletter_campagne') . " c ON c.id = a.campagna_id
            WHERE a.fase = 'prova' AND c.tipo = 'ab' AND c.stato IN ('in_invio', 'inviata') AND a.avviato_at IS NOT NULL")->fetchAll();
        foreach ($righe as $ab) {
            if (strtotime((string)$ab['avviato_at']) + nl_ab_secondi($ab) > time()) continue;
            $r = nl_ab_scegli((int)$ab['campagna_id']);
            if ($r !== '') $log[] = $r;
        }
        return $log;
    } finally {
        db()->query("SELECT RELEASE_LOCK($blocco)");
    }
}

/** Quando il test sceglie (timestamp), 0 se non è partito. */
function nl_ab_scade(array $ab): int {
    return $ab['avviato_at'] ? strtotime((string)$ab['avviato_at']) + nl_ab_secondi($ab) : 0;
}

/** Copia le impostazioni del test di $da in $a (Duplica): la fase e il vincitore ripartono da zero. */
function nl_ab_copia(int $da, int $a): void {
    $r = nl_ab($da);
    nl_ab($a);
    $col = ['cosa', 'oggetto_b', 'anteprima_b', 'percentuale', 'criterio', 'durata', 'unita'];
    foreach (NL_AB_DESIGN as $k) $col[] = $k . '_b';
    $val = array_map(fn($k) => $r[$k], $col);
    $val[] = $a;
    db()->prepare('UPDATE ' . tbl('newsletter_ab') . ' SET ' . implode(', ', array_map(fn($k) => "`$k` = ?", $col)) . ' WHERE campagna_id = ?')->execute($val);
}
