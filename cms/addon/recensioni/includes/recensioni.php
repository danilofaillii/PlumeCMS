<?php
/**
 * Il motore dell'addon Raccolta recensioni: tabelle, impostazioni, inviti, link firmati, recensioni, email, cron.
 *
 * Un invito è una riga di rec_inviti: chi (email, nome), da dove (una prenotazione svolta, 'pren:<id>', oppure a mano),
 * quando parte (invia_dopo), quando è partito, quando scade il link, quando è stato usato. Una recensione per invito:
 * l'invito si "consuma" con UPDATE ... WHERE usato_at IS NULL (una sola richiesta vince) e rec_recensioni.invito_id è UNIQUE.
 * Chi chiede di non ricevere più queste email finisce in rec_esclusi (solo l'impronta sha256 dell'indirizzo).
 * Niente dati per Google sulle stelle: Google non mostra le stelle di chi si recensisce da sé (la stessa scelta del widget Recensioni).
 */
require_once dirname(__DIR__, 3) . '/includes/moduli.php';

const REC_STATI = ['da_approvare' => 'Da approvare', 'approvata' => 'Approvata', 'rifiutata' => 'Rifiutata'];
const REC_PREDEFINITE = ['giorni_dopo' => 2, 'validita' => 30, 'da_prenotazioni' => true, 'google_link' => '', 'google_da' => 4, 'avviso_a' => '', 'avvisa' => true,
    'oggetto' => 'Com\'è andata?', 'testo' => 'Grazie di essere passato da noi. Ci diresti com\'è andata? Bastano un clic sulle stelle e due righe: ci aiuta a migliorare, e a chi ci cerca a sceglierci.'];
const REC_MAX_A_MANO = 200;

function rec_tabelle(): array {
    return [
        'rec_inviti' => 'CREATE TABLE IF NOT EXISTS ' . tbl('rec_inviti') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `codice` CHAR(32) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `nome` VARCHAR(150) NOT NULL DEFAULT '',
            `origine` VARCHAR(20) NOT NULL DEFAULT 'manuale',
            `rif` VARCHAR(40) NULL DEFAULT NULL,
            `servizio` VARCHAR(150) NOT NULL DEFAULT '',
            `invia_dopo` DATETIME NOT NULL,
            `inviato_at` DATETIME NULL DEFAULT NULL,
            `scade_at` DATETIME NULL DEFAULT NULL,
            `usato_at` DATETIME NULL DEFAULT NULL,
            `annullato` TINYINT(1) NOT NULL DEFAULT 0,
            `tentativi` INT NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `rif` (`rif`),
            KEY `coda` (`inviato_at`, `annullato`, `invia_dopo`),
            KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'rec_recensioni' => 'CREATE TABLE IF NOT EXISTS ' . tbl('rec_recensioni') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `invito_id` INT NOT NULL,
            `nome` VARCHAR(100) NOT NULL,
            `stelle` TINYINT NOT NULL,
            `testo` VARCHAR(3000) NOT NULL DEFAULT '',
            `servizio` VARCHAR(150) NOT NULL DEFAULT '',
            `stato` VARCHAR(20) NOT NULL DEFAULT 'da_approvare',
            `risposta` VARCHAR(2000) NOT NULL DEFAULT '',
            `risposta_at` DATETIME NULL DEFAULT NULL,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `invito` (`invito_id`),
            KEY `stato` (`stato`, `creato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'rec_esclusi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('rec_esclusi') . " (
            `impronta` CHAR(64) NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`impronta`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
}

function rec_installa(): string {
    foreach (rec_tabelle() as $sql) db()->exec($sql);
    save_setting('rec_schema_impronta', md5(serialize(rec_tabelle()) . DB_NAME));
    return 'tabelle a posto';
}

function rec_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    if (get_setting('rec_schema_impronta', '') !== md5(serialize(rec_tabelle()) . DB_NAME)) rec_installa();
    $fatto = true;
}

function rec_impostazioni(): array {
    $s = json_decode((string)get_setting('rec_impostazioni', ''), true);
    return (is_array($s) ? $s : []) + REC_PREDEFINITE;
}

function rec_testo(mixed $s, int $max, bool $righe = false): string {
    $s = is_scalar($s) ? mb_scrub((string)$s, 'UTF-8') : '';
    $s = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
    $s = (string)preg_replace($righe ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $righe ? '' : ' ', $s);
    return mb_substr(trim($s), 0, $max);
}

function rec_riga(string $s): string {
    return trim((string)preg_replace('/[\r\n\t\x00-\x1F\x7F]+/', ' ', $s));
}

function rec_impronta(string $email): string {
    return hash('sha256', mb_strtolower(trim($email)));
}

function rec_escluso(string $email): bool {
    $st = db()->prepare('SELECT 1 FROM ' . tbl('rec_esclusi') . ' WHERE impronta = ?');
    $st->execute([rec_impronta($email)]);
    return (bool)$st->fetchColumn();
}

/** "Giulia Rossi" -> "Giulia R.": il nome che si propone per la recensione pubblica. */
function rec_nome_breve(string $nome): string {
    $p = preg_split('/\s+/', trim($nome)) ?: [];
    if (!$p || $p[0] === '') return '';
    return $p[0] . (count($p) > 1 ? ' ' . mb_strtoupper(mb_substr(end($p), 0, 1)) . '.' : '');
}

// ------------------------------------------------------------
// Inviti
// ------------------------------------------------------------

/**
 * Un invito nuovo. Ritorna l'id, oppure 0 se non si fa: indirizzo non valido, persona che ha chiesto di non ricevere più
 * queste email, la stessa prenotazione già invitata (rif UNIQUE), un invito ancora aperto per la stessa email.
 */
function rec_invito_crea(string $email, string $nome, string $origine, ?string $rif, string $servizio, int $quando): int {
    rec_schema();
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || rec_escluso($email)) return 0;
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('rec_inviti') . ' WHERE email = ? AND annullato = 0 AND usato_at IS NULL AND (inviato_at IS NULL OR scade_at > ?)');
    $st->execute([$email, date('Y-m-d H:i:s')]);
    if ((int)$st->fetchColumn() > 0) return 0;
    try {
        db()->prepare('INSERT INTO ' . tbl('rec_inviti') . ' (codice, email, nome, origine, rif, servizio, invia_dopo, creato) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([bin2hex(random_bytes(16)), $email, rec_testo($nome, 150), $origine, $rif, rec_testo($servizio, 150), date('Y-m-d H:i:s', $quando), date('Y-m-d H:i:s')]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) return 0;
        throw $e;
    }
    return (int)db()->lastInsertId();
}

/** L'evento prenotazione_svolta dell'addon Prenotazioni: l'invito parte N giorni dopo la fine dell'appuntamento. */
function rec_da_prenotazione(array $p): void {
    $imp = rec_impostazioni();
    if (!$imp['da_prenotazioni']) return;
    rec_invito_crea((string)$p['email'], (string)$p['nome'], 'prenotazione', 'pren:' . (int)$p['id'], (string)$p['servizio'], (int)$p['fine_ts'] + max(0, (int)$imp['giorni_dopo']) * 86400);
}

/** Un invito non ancora partito che non deve più partire (appuntamento annullato, non presentato); con $elimina si toglie la riga. */
function rec_annulla_rif(string $rif, bool $elimina = false): void {
    rec_schema();
    if ($elimina) db()->prepare('DELETE FROM ' . tbl('rec_inviti') . ' WHERE rif = ? AND usato_at IS NULL')->execute([$rif]);
    else db()->prepare('UPDATE ' . tbl('rec_inviti') . ' SET annullato = 1 WHERE rif = ? AND inviato_at IS NULL')->execute([$rif]);
}

function rec_invito(int $id): ?array {
    rec_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('rec_inviti') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function rec_firma(int $id, string $codice, string $scopo = 'recensione'): string {
    return substr(hash_hmac('sha256', "$scopo|$id|$codice", moduli_segreto()), 0, 40);
}

function rec_url(array $i, string $scopo = 'recensione', array $q = []): string {
    return cms_url_sito() . ($scopo === 'esci' ? '/recensione/esci/' : '/recensione/') . '?' . http_build_query(['i' => (int)$i['id'], 't' => rec_firma((int)$i['id'], (string)$i['codice'], $scopo)] + $q);
}

/**
 * L'invito di un link personale: ['invito' => riga, 'stato' => 'ok' | 'usato' | 'scaduto'] oppure null se la firma non torna
 * (o l'invito non è mai partito, o è annullato).
 */
function rec_da_link(mixed $id, mixed $firma, string $scopo = 'recensione'): ?array {
    if (!is_string($id) || !ctype_digit($id) || !is_string($firma) || !preg_match('/^[a-f0-9]{40}$/', $firma)) return null;
    $i = rec_invito((int)$id);
    if (!$i || !hash_equals(rec_firma((int)$i['id'], (string)$i['codice'], $scopo), $firma) || $i['inviato_at'] === null) return null;
    if ($scopo === 'esci') return ['invito' => $i, 'stato' => 'ok'];
    if ((int)$i['annullato']) return null;
    if ($i['usato_at'] !== null) return ['invito' => $i, 'stato' => 'usato'];
    if (strtotime((string)$i['scade_at']) < time()) return ['invito' => $i, 'stato' => 'scaduto'];
    return ['invito' => $i, 'stato' => 'ok'];
}

/**
 * Salva la recensione di un invito, una volta sola. Ritorna l'id, oppure 'usato' (già lasciata o richiesta doppia),
 * 'scaduto'. L'invito si prende con un UPDATE condizionato prima di scrivere: due richieste insieme, una sola passa.
 */
function rec_salva(array $i, int $stelle, string $testo, string $nome): int|string {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('UPDATE ' . tbl('rec_inviti') . ' SET usato_at = ? WHERE id = ? AND usato_at IS NULL AND annullato = 0 AND scade_at > ?');
        $st->execute([date('Y-m-d H:i:s'), (int)$i['id'], date('Y-m-d H:i:s')]);
        if ($st->rowCount() !== 1) { $pdo->rollBack(); return strtotime((string)$i['scade_at']) < time() ? 'scaduto' : 'usato'; }
        $ora = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO ' . tbl('rec_recensioni') . ' (invito_id, nome, stelle, testo, servizio, stato, ip, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([(int)$i['id'], $nome, max(1, min(5, $stelle)), $testo, (string)$i['servizio'], 'da_approvare', moduli_ip(), $ora, $ora]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && ($e->errorInfo[1] ?? 0) === 1062) return 'usato';
        throw $e;
    }
    rec_avvisa_attivita($id);
    return $id;
}

/** "Non scrivermi più": l'impronta dell'indirizzo entra negli esclusi e gli inviti in coda per quell'indirizzo si annullano. */
function rec_escludi(string $email): void {
    rec_schema();
    db()->prepare('INSERT IGNORE INTO ' . tbl('rec_esclusi') . ' (impronta, creato) VALUES (?, ?)')->execute([rec_impronta($email), date('Y-m-d H:i:s')]);
    db()->prepare('UPDATE ' . tbl('rec_inviti') . ' SET annullato = 1 WHERE email = ? AND inviato_at IS NULL')->execute([mb_strtolower(trim($email))]);
}

// ------------------------------------------------------------
// Recensioni
// ------------------------------------------------------------

function rec_recensione(int $id): ?array {
    rec_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('rec_recensioni') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Le recensioni approvate (le più recenti prima), e il riepilogo di tutte le approvate: [elenco, media, quante]. */
function rec_approvate(int $quante = 6, int $minimo = 1): array {
    rec_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('rec_recensioni') . " WHERE stato = 'approvata' AND stelle >= ? ORDER BY creato DESC, id DESC LIMIT " . max(1, min(60, $quante)));
    $st->execute([max(1, min(5, $minimo))]);
    $tot = db()->query('SELECT COUNT(*) AS n, AVG(stelle) AS m FROM ' . tbl('rec_recensioni') . " WHERE stato = 'approvata'")->fetch();
    return [$st->fetchAll(), (float)($tot['m'] ?? 0), (int)($tot['n'] ?? 0)];
}

// ------------------------------------------------------------
// Email
// ------------------------------------------------------------

function rec_indirizzo_attivita(): string {
    $a = trim((string)rec_impostazioni()['avviso_a']);
    if (!filter_var($a, FILTER_VALIDATE_EMAIL)) $a = posta_notifica_email();
    return filter_var($a, FILTER_VALIDATE_EMAIL) ? $a : '';
}

/** L'email "Com'è andata?": cinque stelle cliccabili (ognuna apre il modulo con quel voto), il pulsante, il link per non riceverne più. */
function rec_email_invito(array $i): bool {
    $imp = rec_impostazioni();
    $sito = (string)cms_impostazione('sito_nome', CMS_NOME);
    $stelle = '';
    for ($n = 1; $n <= 5; $n++) $stelle .= '<a href="' . h(rec_url($i, 'recensione', ['s' => $n])) . '" style="font-size:34px;line-height:1;color:#f5b301;text-decoration:none;padding:0 3px" title="' . $n . ' su 5">&#9733;</a>';
    $corpo = '<p>Ciao ' . h(strtok((string)$i['nome'], ' ') ?: 'e grazie') . ',</p><p>' . nl2br(h((string)$imp['testo'])) . '</p>'
        . ($i['servizio'] !== '' ? '<p style="color:#555">Il servizio: <b>' . h((string)$i['servizio']) . '</b></p>' : '')
        . '<p style="margin:22px 0 6px">Quante stelle ci dai?</p><p style="margin:0 0 6px">' . $stelle . '</p>'
        . '<p style="font-size:13px;color:#888">Il link vale ' . (int)$imp['validita'] . ' giorni ed è solo tuo: con questo si lascia una recensione sola.</p>';
    $fondo = '<p style="font-size:12px;color:#888;margin-top:26px">Non vuoi ricevere altre email come questa? <a href="' . h(rec_url($i, 'esci')) . '" style="color:#888">Non scrivermi più</a>.</p>';
    $html = str_replace('</td></tr></table></td></tr></table></body>', $fondo . '</td></tr></table></td></tr></table></body>', moduli_email_html((string)$imp['oggetto'], $corpo, 'Lascia la tua recensione', rec_url($i)));
    $ok = posta_invia((string)$i['email'], rec_riga((string)$imp['oggetto'] . ' - ' . $sito), $html, moduli_intestazioni(rec_indirizzo_attivita()) . 'List-Unsubscribe: <' . rec_url($i, 'esci') . ">\r\n", 'recensione');
    if (!$ok) error_log('recensioni: invito non partito: ' . posta_errore());
    return $ok;
}

function rec_avvisa_attivita(int $id): void {
    $imp = rec_impostazioni();
    $a = rec_indirizzo_attivita();
    $r = rec_recensione($id);
    if (!$imp['avvisa'] || $a === '' || !$r) return;
    $corpo = '<p style="font-size:24px;color:#f5b301;margin:0 0 10px">' . str_repeat('&#9733;', (int)$r['stelle']) . '<span style="color:#ccc">' . str_repeat('&#9733;', 5 - (int)$r['stelle']) . '</span></p>'
        . '<p><b>' . h((string)$r['nome']) . '</b>' . ($r['servizio'] !== '' ? ' &middot; ' . h((string)$r['servizio']) : '') . '</p>'
        . ($r['testo'] !== '' ? '<p style="border-left:3px solid #7c3aed;padding-left:12px">' . nl2br(h((string)$r['testo'])) . '</p>' : '')
        . '<p>Sul sito esce solo dopo che la approvi.</p>';
    posta_invia($a, rec_riga('Nuova recensione: ' . (int)$r['stelle'] . ' stelle da ' . $r['nome']), moduli_email_html('Nuova recensione da approvare', $corpo, 'Apri nel pannello', cms_url_pannello() . 'addon-pagina.php?a=recensioni&p=recensioni'), moduli_intestazioni(), 'recensione');
}

// ------------------------------------------------------------
// Cron frequente: gli inviti arrivati alla loro ora
// ------------------------------------------------------------

/** Manda gli inviti arrivati alla loro ora (al massimo 30 a giro). Ogni invito si prende prima di spedire: mai due volte. */
function rec_cron(?int $adesso = null): string {
    rec_schema();
    $adesso ??= time();
    $imp = rec_impostazioni();
    $st = db()->prepare('SELECT * FROM ' . tbl('rec_inviti') . ' WHERE inviato_at IS NULL AND annullato = 0 AND invia_dopo <= ? ORDER BY invia_dopo, id LIMIT 30');
    $st->execute([date('Y-m-d H:i:s', $adesso)]);
    $ok = 0; $ko = 0;
    foreach ($st->fetchAll() as $i) {
        if (rec_escluso((string)$i['email'])) { db()->prepare('UPDATE ' . tbl('rec_inviti') . ' SET annullato = 1 WHERE id = ?')->execute([(int)$i['id']]); continue; }
        $scade = date('Y-m-d H:i:s', $adesso + max(1, (int)$imp['validita']) * 86400);
        $presa = db()->prepare('UPDATE ' . tbl('rec_inviti') . ' SET inviato_at = ?, scade_at = ? WHERE id = ? AND inviato_at IS NULL');
        $presa->execute([date('Y-m-d H:i:s', $adesso), $scade, (int)$i['id']]);
        if ($presa->rowCount() !== 1) continue;
        $i['inviato_at'] = date('Y-m-d H:i:s', $adesso);
        if (rec_email_invito($i)) { $ok++; continue; }
        // Non partito: torna in coda; dopo cinque tentativi si lascia stare.
        db()->prepare('UPDATE ' . tbl('rec_inviti') . ' SET inviato_at = NULL, scade_at = NULL, tentativi = tentativi + 1, annullato = IF(tentativi >= 4, 1, 0) WHERE id = ?')->execute([(int)$i['id']]);
        $ko++;
    }
    return ($ok || $ko) ? ($ok === 1 ? '1 invito mandato' : "$ok inviti mandati") . ($ko ? ", $ko non partiti (si riprova)" : '') : '';
}
