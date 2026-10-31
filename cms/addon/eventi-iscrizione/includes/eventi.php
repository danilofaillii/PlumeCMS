<?php
/**
 * Il motore dell'addon Eventi con iscrizione: tabelle, eventi, iscrizioni, lista d'attesa, email, file .ics, cron.
 *
 * Posti: un'iscrizione occupa N posti (fino al massimo per persona dell'evento). Con l'evento pieno l'iscrizione entra
 * in lista d'attesa (se l'evento la vuole). Quando qualcuno si cancella, o l'attività aumenta i posti, entrano i primi
 * in attesa in ordine di arrivo finché ci stanno (senza saltare chi chiede più posti: si va in ordine), e ricevono l'email.
 * Due richieste insieme: l'iscrizione si scrive in una transazione che blocca la riga dell'evento (SELECT ... FOR UPDATE)
 * e conta i posti dopo il blocco. La stessa email non si iscrive due volte allo stesso evento (indice UNIQUE).
 */
require_once dirname(__DIR__, 3) . '/includes/moduli.php';

const EVI_STATI = ['confermata' => 'Iscritto', 'attesa' => 'In lista d\'attesa', 'cancellata' => 'Cancellato'];
const EVI_LIMITE_ORA = 6;
const EVI_MESI = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
const EVI_GIORNI = ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
const EVI_PREDEFINITE = ['avviso_a' => '', 'avvisa_iscrizioni' => true, 'nota_email' => '', 'privacy' => '/privacy-policy/', 'promemoria' => true];

function evi_tabelle(): array {
    return [
        'evi_eventi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('evi_eventi') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(120) NOT NULL,
            `titolo` VARCHAR(200) NOT NULL,
            `inizio` DATETIME NOT NULL,
            `inizio_ts` BIGINT NOT NULL,
            `fine_ts` BIGINT NOT NULL DEFAULT 0,
            `luogo` VARCHAR(200) NOT NULL DEFAULT '',
            `indirizzo` VARCHAR(255) NOT NULL DEFAULT '',
            `descrizione` TEXT NOT NULL,
            `foto` VARCHAR(500) NOT NULL DEFAULT '',
            `prezzo` VARCHAR(60) NOT NULL DEFAULT '',
            `posti` INT NOT NULL DEFAULT 0,
            `max_persona` INT NOT NULL DEFAULT 4,
            `chiusura_ts` BIGINT NOT NULL DEFAULT 0,
            `attesa` TINYINT(1) NOT NULL DEFAULT 1,
            `stato` VARCHAR(20) NOT NULL DEFAULT 'bozza',
            `svolto_at` DATETIME NULL DEFAULT NULL,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`),
            KEY `inizio` (`inizio_ts`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'evi_iscrizioni' => 'CREATE TABLE IF NOT EXISTS ' . tbl('evi_iscrizioni') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `evento_id` INT NOT NULL,
            `codice` CHAR(32) NOT NULL,
            `nome` VARCHAR(150) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `telefono` VARCHAR(40) NOT NULL DEFAULT '',
            `posti` INT NOT NULL DEFAULT 1,
            `stato` VARCHAR(20) NOT NULL DEFAULT 'confermata',
            `email_attiva` VARCHAR(190) GENERATED ALWAYS AS (IF(`stato` <> 'cancellata', `email`, NULL)) STORED,
            `presente` TINYINT(1) NULL DEFAULT NULL,
            `promemoria_at` DATETIME NULL DEFAULT NULL,
            `entrato_at` DATETIME NULL DEFAULT NULL,
            `consenso_at` DATETIME NOT NULL,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `una_volta` (`evento_id`, `email_attiva`),
            KEY `evento` (`evento_id`, `stato`),
            KEY `ip` (`ip`, `creato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
}

function evi_installa(): string {
    foreach (evi_tabelle() as $sql) db()->exec($sql);
    evi_colonne_nuove();
    save_setting('evi_schema_impronta', md5(serialize(evi_tabelle()) . DB_NAME));
    return 'tabelle a posto';
}

/**
 * 2.1: la colonna svolto_at (l'evento è finito e l'evento evento_svolto è partito) sulle tabelle della 2.0.0. Gli eventi
 * già finiti quando la colonna nasce si segnano svolti subito: niente inviti "Com'è andata?" per eventi di mesi fa.
 */
function evi_colonne_nuove(): void {
    $tab = tbl('evi_eventi');
    $ci = [];
    foreach (db()->query('SHOW COLUMNS FROM ' . $tab)->fetchAll() as $c) $ci[$c['Field']] = true;
    if (isset($ci['svolto_at'])) return;
    db()->exec('ALTER TABLE ' . $tab . ' ADD COLUMN `svolto_at` DATETIME NULL DEFAULT NULL AFTER `stato`');
    db()->prepare('UPDATE ' . $tab . ' SET svolto_at = ? WHERE GREATEST(inizio_ts, fine_ts) < ?')->execute([date('Y-m-d H:i:s'), time()]);
}

/** Quando finisce un evento: la fine scritta, oppure due ore dopo l'inizio (come nel file .ics). */
function evi_fine(array $e): int {
    return (int)$e['fine_ts'] > (int)$e['inizio_ts'] ? (int)$e['fine_ts'] : (int)$e['inizio_ts'] + 7200;
}

function evi_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    if (get_setting('evi_schema_impronta', '') !== md5(serialize(evi_tabelle()) . DB_NAME)) evi_installa();
    $fatto = true;
}

function evi_impostazioni(): array {
    $s = json_decode((string)get_setting('evi_impostazioni', ''), true);
    return (is_array($s) ? $s : []) + EVI_PREDEFINITE;
}

/** Un testo che arriva dal web o dal pannello: UTF-8 valido, senza caratteri a 4 byte né di controllo, tagliato. */
function evi_testo(mixed $s, int $max, bool $righe = false): string {
    $s = is_scalar($s) ? mb_scrub((string)$s, 'UTF-8') : '';
    $s = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
    $s = (string)preg_replace($righe ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $righe ? '' : ' ', $s);
    return mb_substr(trim($s), 0, $max);
}

function evi_riga(string $s): string {
    return trim((string)preg_replace('/[\r\n\t\x00-\x1F\x7F]+/', ' ', $s));
}

function evi_slug(string $s): string {
    $s = mb_strtolower($s);
    $s = strtr($s, ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '&' => ' e ']);
    $s = trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return substr($s, 0, 100) ?: 'evento';
}

/** "venerdì 20 novembre 2026 alle 21:00" nel fuso del sito. */
function evi_quando(array $e, bool $ora = true): string {
    $d = (new DateTimeImmutable('@' . (int)$e['inizio_ts']))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    return EVI_GIORNI[(int)$d->format('N') - 1] . ' ' . (int)$d->format('j') . ' ' . EVI_MESI[(int)$d->format('n') - 1] . ' ' . $d->format('Y') . ($ora ? ' alle ' . $d->format('G:i') : '');
}

// ------------------------------------------------------------
// Eventi
// ------------------------------------------------------------

function evi_eventi(bool $pubblici = false, bool $futuri = false): array {
    evi_schema();
    $dove = [];
    if ($pubblici) $dove[] = "stato IN ('pubblicato','annullato')";
    if ($futuri) $dove[] = 'GREATEST(inizio_ts, fine_ts) >= ' . time();
    return db()->query('SELECT e.*, (SELECT COALESCE(SUM(posti),0) FROM ' . tbl('evi_iscrizioni') . " i WHERE i.evento_id = e.id AND i.stato = 'confermata') AS occupati, (SELECT COALESCE(SUM(posti),0) FROM " . tbl('evi_iscrizioni') . " i WHERE i.evento_id = e.id AND i.stato = 'attesa') AS in_attesa FROM " . tbl('evi_eventi') . ' e' . ($dove ? ' WHERE ' . implode(' AND ', $dove) : '') . ' ORDER BY inizio_ts')->fetchAll();
}

function evi_evento(int $id): ?array {
    evi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('evi_eventi') . ' WHERE id = ?');
    $st->execute([$id]);
    $e = $st->fetch();
    return $e ? $e + ['occupati' => evi_occupati($id)] : null;
}

function evi_evento_slug(string $slug): ?array {
    evi_schema();
    $st = db()->prepare('SELECT id FROM ' . tbl('evi_eventi') . ' WHERE slug = ?');
    $st->execute([$slug]);
    $id = (int)$st->fetchColumn();
    return $id ? evi_evento($id) : null;
}

function evi_occupati(int $evento): int {
    $st = db()->prepare('SELECT COALESCE(SUM(posti),0) FROM ' . tbl('evi_iscrizioni') . " WHERE evento_id = ? AND stato = 'confermata'");
    $st->execute([$evento]);
    return (int)$st->fetchColumn();
}

/** I posti liberi (PHP_INT_MAX senza limite). */
function evi_liberi(array $e): int {
    return (int)$e['posti'] <= 0 ? PHP_INT_MAX : max(0, (int)$e['posti'] - (int)($e['occupati'] ?? evi_occupati((int)$e['id'])));
}

/** Le iscrizioni sono aperte? Evento pubblicato, prima della chiusura (o dell'inizio). */
function evi_aperte(array $e, ?int $adesso = null): bool {
    $chiude = (int)$e['chiusura_ts'] > 0 ? (int)$e['chiusura_ts'] : (int)$e['inizio_ts'];
    return $e['stato'] === 'pubblicato' && ($adesso ?? time()) < $chiude;
}

function evi_url_evento(array $e): string {
    return '/evento/' . $e['slug'] . '/';
}

/** Lo stato dei posti in parole, per la pagina e per il widget dell'elenco. */
function evi_posti_testo(array $e): string {
    if ($e['stato'] === 'annullato') return 'Evento annullato';
    if (!evi_aperte($e)) return (int)$e['inizio_ts'] < time() ? 'Evento passato' : 'Iscrizioni chiuse';
    $l = evi_liberi($e);
    if ($l === PHP_INT_MAX) return 'Iscrizioni aperte';
    if ($l === 0) return $e['attesa'] ? 'Al completo: puoi entrare in lista d\'attesa' : 'Al completo';
    return $l === 1 ? 'Ultimo posto libero' : $l . ' posti liberi';
}

// ------------------------------------------------------------
// Iscrizioni
// ------------------------------------------------------------

function evi_iscrizione(int $id): ?array {
    evi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('evi_iscrizioni') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function evi_firma(int $id, string $codice): string {
    return substr(hash_hmac('sha256', "evento-iscrizione|$id|$codice", moduli_segreto()), 0, 40);
}

function evi_url(string $cosa, array $i): string {
    return cms_url_sito() . '/evento-iscrizione/' . $cosa . '/?' . http_build_query(['i' => (int)$i['id'], 't' => evi_firma((int)$i['id'], (string)$i['codice'])]);
}

/** L'iscrizione di un link personale, o null se la firma non torna o l'evento è finito da più di 30 giorni. */
function evi_da_link(mixed $id, mixed $firma): ?array {
    if (!is_string($id) || !ctype_digit($id) || !is_string($firma) || !preg_match('/^[a-f0-9]{40}$/', $firma)) return null;
    $i = evi_iscrizione((int)$id);
    if (!$i || !hash_equals(evi_firma((int)$i['id'], (string)$i['codice']), $firma)) return null;
    $e = evi_evento((int)$i['evento_id']);
    if (!$e || max((int)$e['inizio_ts'], (int)$e['fine_ts']) + 30 * 86400 < time()) return null;
    return $i;
}

function evi_troppi(): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('evi_iscrizioni') . ' WHERE ip = ? AND creato > ?');
    $st->execute([moduli_ip(), date('Y-m-d H:i:s', time() - 3600)]);
    return (int)$st->fetchColumn() >= EVI_LIMITE_ORA;
}

function evi_transazione(callable $f) {
    $pdo = db();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();
    try {
        $r = $f($pdo);
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && ($e->errorInfo[1] ?? 0) === 1062) return 'gia';
        throw $e;
    }
}

/**
 * Un'iscrizione nuova. $d: nome, email, telefono, posti. Ritorna ['id', 'stato'] oppure un codice d'errore:
 * 'chiuse', 'posti' (numero sbagliato), 'pochi:N' (restano N posti), 'pieno', 'gia' (già iscritto con questa email).
 */
function evi_iscrivi(int $eventoId, array $d, ?int $adesso = null): array|string {
    evi_schema();
    $r = evi_transazione(function (PDO $pdo) use ($eventoId, $d, $adesso) {
        $st = $pdo->prepare('SELECT * FROM ' . tbl('evi_eventi') . ' WHERE id = ? FOR UPDATE');
        $st->execute([$eventoId]);
        $e = $st->fetch();
        if (!$e || !evi_aperte($e, $adesso)) return 'chiuse';
        $n = (int)$d['posti'];
        if ($n < 1 || $n > max(1, (int)$e['max_persona'])) return 'posti';
        $g = $pdo->prepare('SELECT COUNT(*) FROM ' . tbl('evi_iscrizioni') . " WHERE evento_id = ? AND email = ? AND stato <> 'cancellata'");
        $g->execute([$eventoId, $d['email']]);
        if ((int)$g->fetchColumn() > 0) return 'gia';
        $e['occupati'] = evi_occupati($eventoId);
        $liberi = evi_liberi($e);
        // Chi è già in attesa passa prima: con una lista d'attesa non vuota, i nuovi si mettono in fila.
        $fila = $pdo->prepare('SELECT COUNT(*) FROM ' . tbl('evi_iscrizioni') . " WHERE evento_id = ? AND stato = 'attesa'");
        $fila->execute([$eventoId]);
        $coda = (int)$fila->fetchColumn() > 0;
        if ($n <= $liberi && !$coda) $stato = 'confermata';
        elseif ($liberi > 0 && $liberi !== PHP_INT_MAX && !$coda) return 'pochi:' . $liberi;
        elseif ($e['attesa']) $stato = 'attesa';
        else return 'pieno';
        $ora = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO ' . tbl('evi_iscrizioni') . ' (evento_id, codice, nome, email, telefono, posti, stato, consenso_at, ip, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$eventoId, bin2hex(random_bytes(16)), $d['nome'], $d['email'], $d['telefono'] ?? '', $n, $stato, $ora, moduli_ip(), $ora, $ora]);
        return ['id' => (int)$pdo->lastInsertId(), 'stato' => $stato];
    });
    if (is_array($r)) {
        $i = evi_iscrizione($r['id']);
        $e = evi_evento($eventoId);
        evi_email($e, $i, $r['stato'] === 'confermata' ? 'confermata' : 'attesa');
        if (evi_impostazioni()['avvisa_iscrizioni']) evi_email_attivita($e, $i, $r['stato'] === 'confermata' ? 'nuova' : 'attesa');
    }
    return $r;
}

/** La posizione in lista d'attesa (1 = la prima). */
function evi_posizione(array $i): int {
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('evi_iscrizioni') . " WHERE evento_id = ? AND stato = 'attesa' AND id <= ?");
    $st->execute([(int)$i['evento_id'], (int)$i['id']]);
    return (int)$st->fetchColumn();
}

/** Cancella un'iscrizione (dalla persona o dall'attività) e fa entrare chi aspetta. */
function evi_cancella(array $i, string $da = 'persona'): bool {
    $st = db()->prepare('UPDATE ' . tbl('evi_iscrizioni') . " SET stato = 'cancellata', aggiornato = ? WHERE id = ? AND stato <> 'cancellata'");
    $st->execute([date('Y-m-d H:i:s'), (int)$i['id']]);
    if ($st->rowCount() !== 1) return false;
    $e = evi_evento((int)$i['evento_id']);
    $i = evi_iscrizione((int)$i['id']);
    // 2.1: per gli altri addon (Raccolta recensioni annulla l'invito "Com'è andata?" non ancora partito).
    try { cms_evento('iscrizione_cancellata', $i, $e); } catch (Throwable $x) { error_log('eventi: evento iscrizione_cancellata: ' . $x->getMessage()); }
    if ($e) {
        evi_email($e, $i, 'cancellata');
        if ($da === 'persona' && evi_impostazioni()['avvisa_iscrizioni']) evi_email_attivita($e, $i, 'cancellata');
        evi_promuovi((int)$e['id']);
    }
    return true;
}

/** Chi è in attesa entra, in ordine di arrivo, finché ci sta. Ritorna quanti sono entrati. */
function evi_promuovi(int $eventoId): int {
    $entrati = evi_transazione(function (PDO $pdo) use ($eventoId) {
        $st = $pdo->prepare('SELECT * FROM ' . tbl('evi_eventi') . ' WHERE id = ? FOR UPDATE');
        $st->execute([$eventoId]);
        $e = $st->fetch();
        if (!$e || $e['stato'] !== 'pubblicato' || (int)$e['inizio_ts'] < time()) return [];
        $occ = evi_occupati($eventoId);
        $att = $pdo->prepare('SELECT * FROM ' . tbl('evi_iscrizioni') . " WHERE evento_id = ? AND stato = 'attesa' ORDER BY id");
        $att->execute([$eventoId]);
        $out = [];
        foreach ($att->fetchAll() as $i) {
            if ((int)$e['posti'] > 0 && $occ + (int)$i['posti'] > (int)$e['posti']) break;
            $pdo->prepare('UPDATE ' . tbl('evi_iscrizioni') . " SET stato = 'confermata', entrato_at = ?, aggiornato = ? WHERE id = ? AND stato = 'attesa'")->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), (int)$i['id']]);
            $occ += (int)$i['posti'];
            $out[] = (int)$i['id'];
        }
        return $out;
    });
    if (!is_array($entrati)) return 0;
    $e = evi_evento($eventoId);
    foreach ($entrati as $id) {
        $i = evi_iscrizione($id);
        evi_email($e, $i, 'entrata');
        if (evi_impostazioni()['avvisa_iscrizioni']) evi_email_attivita($e, $i, 'entrata');
    }
    return count($entrati);
}

// ------------------------------------------------------------
// Email e calendario
// ------------------------------------------------------------

function evi_indirizzo_attivita(): string {
    $a = trim((string)evi_impostazioni()['avviso_a']);
    if (!filter_var($a, FILTER_VALIDATE_EMAIL)) $a = posta_notifica_email();
    return filter_var($a, FILTER_VALIDATE_EMAIL) ? $a : '';
}

function evi_riepilogo_html(array $e, ?array $i = null): string {
    $righe = [['Evento', $e['titolo']], ['Quando', evi_quando($e)]];
    if (trim($e['luogo'] . ' ' . $e['indirizzo']) !== '') $righe[] = ['Dove', trim($e['luogo'] . ($e['indirizzo'] !== '' ? ', ' . $e['indirizzo'] : ''), ', ')];
    if ($i) $righe[] = ['Posti', (string)(int)$i['posti']];
    if ($e['prezzo'] !== '') $righe[] = ['Prezzo', $e['prezzo']];
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;font-size:15px">' . implode('', array_map(fn($r) => '<tr><td style="padding:4px 14px 4px 0;color:#666">' . h($r[0]) . '</td><td style="padding:4px 0"><b>' . h($r[1]) . '</b></td></tr>', $righe)) . '</table>';
}

/** Le email alla persona: confermata, attesa, entrata (dalla lista d'attesa), cancellata, promemoria, messaggio (dal pannello). */
function evi_email(?array $e, ?array $i, string $tipo, string $oggetto = '', string $messaggio = ''): bool {
    if (!$e || !$i || !filter_var($i['email'], FILTER_VALIDATE_EMAIL)) return false;
    $imp = evi_impostazioni();
    $sito = (string)cms_impostazione('sito_nome', CMS_NOME);
    $ciao = '<p>Ciao ' . h(strtok((string)$i['nome'], ' ') ?: (string)$i['nome']) . ',</p>';
    $cal = '<p style="font-size:14px"><a href="' . h(evi_url('calendario', $i)) . '">Aggiungi al calendario</a> (file .ics, per il calendario del telefono o del computer)</p>';
    $nota = trim((string)$imp['nota_email']) !== '' ? '<p>' . nl2br(h((string)$imp['nota_email'])) . '</p>' : '';
    $esci = '<p style="font-size:14px;color:#555">Se non puoi più venire, cancellati dal link qui sotto: il tuo posto andrà a chi aspetta.</p>';
    $btn = ['Vedi o cancella l\'iscrizione', evi_url('gestisci', $i)];
    switch ($tipo) {
        case 'confermata':
            [$ogg, $tit, $corpo] = ['Iscrizione confermata: ' . $e['titolo'], 'Sei iscritto', $ciao . '<p>la tua iscrizione è confermata:</p>' . evi_riepilogo_html($e, $i) . $nota . $cal . $esci]; break;
        case 'attesa':
            [$ogg, $tit, $corpo] = ['Sei in lista d\'attesa: ' . $e['titolo'], 'Sei in lista d\'attesa', $ciao . '<p>l\'evento è al completo: sei in lista d\'attesa, al posto numero <b>' . evi_posizione($i) . '</b>. Se si libera un posto entri da solo e ti scriviamo subito.</p>' . evi_riepilogo_html($e, $i)]; break;
        case 'entrata':
            [$ogg, $tit, $corpo] = ['Si è liberato un posto: sei iscritto a ' . $e['titolo'], 'Si è liberato un posto: sei dentro', $ciao . '<p>si è liberato un posto e sei passato dalla lista d\'attesa agli iscritti:</p>' . evi_riepilogo_html($e, $i) . $nota . $cal . $esci]; break;
        case 'promemoria':
            [$ogg, $tit, $corpo] = ['Promemoria: ' . $e['titolo'] . ', ' . evi_quando($e), 'Ci vediamo presto', $ciao . '<p>ti ricordiamo l\'evento a cui sei iscritto:</p>' . evi_riepilogo_html($e, $i) . $nota . $cal . $esci]; break;
        case 'cancellata':
            [$ogg, $tit, $corpo] = ['Iscrizione cancellata: ' . $e['titolo'], 'Iscrizione cancellata', $ciao . '<p>la tua iscrizione a <b>' . h((string)$e['titolo']) . '</b> (' . h(evi_quando($e)) . ') è cancellata.</p>']; $btn = ['Vai al sito', cms_url_sito() . '/']; break;
        case 'messaggio':
            [$ogg, $tit, $corpo] = [$oggetto, $oggetto, $ciao . '<p>' . nl2br(h($messaggio)) . '</p>' . evi_riepilogo_html($e, $i)]; break;
        default: return false;
    }
    $ok = posta_invia((string)$i['email'], evi_riga($ogg . ' - ' . $sito), moduli_email_html($tit, $corpo, $btn[0], $btn[1]), moduli_intestazioni(evi_indirizzo_attivita()), 'evento');
    if (!$ok) error_log('eventi: email ' . $tipo . ' non partita: ' . posta_errore());
    return $ok;
}

function evi_email_attivita(array $e, array $i, string $tipo): bool {
    $a = evi_indirizzo_attivita();
    if ($a === '') return false;
    $tit = ['nuova' => 'Nuova iscrizione', 'attesa' => 'Nuova iscrizione in lista d\'attesa', 'cancellata' => 'Iscrizione cancellata', 'entrata' => 'Entrato dalla lista d\'attesa'][$tipo] ?? 'Iscrizione';
    $e['occupati'] = evi_occupati((int)$e['id']);
    $corpo = '<p><b>' . h((string)$i['nome']) . '</b> &middot; ' . h((string)$i['email']) . ($i['telefono'] !== '' ? ' &middot; ' . h((string)$i['telefono']) : '') . '</p>' . evi_riepilogo_html($e, $i)
        . '<p>Iscritti adesso: <b>' . (int)$e['occupati'] . ((int)$e['posti'] > 0 ? ' su ' . (int)$e['posti'] : '') . '</b> posti.</p>';
    return posta_invia($a, evi_riga($tit . ': ' . $i['nome'] . ', ' . $e['titolo']), moduli_email_html($tit, $corpo, 'Apri gli iscritti', cms_url_pannello() . 'addon-pagina.php?a=eventi-iscrizione&p=iscritti&e=' . (int)$e['id']), moduli_intestazioni((string)$i['email']), 'evento');
}

/** Scrive a tutti gli iscritti di un evento (anche a chi aspetta, se scelto). Ritorna [mandate, non partite]. */
function evi_scrivi_a_tutti(array $e, string $oggetto, string $messaggio, bool $ancheAttesa): array {
    $st = db()->prepare('SELECT * FROM ' . tbl('evi_iscrizioni') . ' WHERE evento_id = ? AND stato IN (' . ($ancheAttesa ? "'confermata','attesa'" : "'confermata'") . ') ORDER BY id');
    $st->execute([(int)$e['id']]);
    $ok = 0; $ko = 0;
    foreach ($st->fetchAll() as $i) evi_email($e, $i, 'messaggio', evi_riga($oggetto), $messaggio) ? $ok++ : $ko++;
    return [$ok, $ko];
}

function evi_ics_testo(string $s): string {
    return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', ''], $s);
}

function evi_ics_piega(string $riga): string {
    $out = ''; $pezzo = '';
    foreach (mb_str_split($riga) as $c) {
        if (strlen($pezzo . $c) > ($out === '' ? 75 : 74)) { $out .= ($out === '' ? '' : "\r\n ") . $pezzo; $pezzo = ''; }
        $pezzo .= $c;
    }
    return $out . ($out === '' ? '' : "\r\n ") . $pezzo;
}

function evi_ics(array $e, array $i): string {
    $host = (string)(parse_url(cms_url_sito(), PHP_URL_HOST) ?: 'localhost');
    $z = fn(int $ts) => gmdate('Ymd\THis\Z', $ts);
    $fine = evi_fine($e);
    $righe = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//plumeCMS//Eventi//IT', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
        'UID:evento-' . (int)$e['id'] . '-' . substr((string)$i['codice'], 0, 12) . '@' . $host, 'DTSTAMP:' . $z(time()), 'DTSTART:' . $z((int)$e['inizio_ts']), 'DTEND:' . $z($fine),
        'SUMMARY:' . evi_ics_testo((string)$e['titolo']), 'URL:' . cms_url_sito() . evi_url_evento($e),
        'DESCRIPTION:' . evi_ics_testo('Per cancellarti: ' . evi_url('gestisci', $i)),
        'STATUS:' . ($e['stato'] === 'annullato' || $i['stato'] === 'cancellata' ? 'CANCELLED' : 'CONFIRMED')];
    $dove = trim($e['luogo'] . ($e['indirizzo'] !== '' ? ', ' . $e['indirizzo'] : ''), ', ');
    if ($dove !== '') $righe[] = 'LOCATION:' . evi_ics_testo($dove);
    $righe[] = 'END:VEVENT';
    $righe[] = 'END:VCALENDAR';
    return implode("\r\n", array_map('evi_ics_piega', $righe)) . "\r\n";
}

/** I dati per Google di un evento (schema.org Event), con JSON_HEX_TAG come il widget Eventi del nucleo. */
function evi_jsonld(array $e): string {
    $tz = new DateTimeZone(date_default_timezone_get());
    $iso = fn(int $ts) => (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('c');
    $ld = ['@context' => 'https://schema.org', '@type' => 'Event', 'name' => (string)$e['titolo'], 'startDate' => $iso((int)$e['inizio_ts']),
        'eventStatus' => 'https://schema.org/' . ($e['stato'] === 'annullato' ? 'EventCancelled' : 'EventScheduled'), 'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => ['@type' => 'Place', 'name' => trim((string)$e['luogo']) ?: (string)cms_impostazione('sito_nome', CMS_NOME), 'address' => trim((string)$e['indirizzo']) ?: (trim((string)$e['luogo']) ?: (string)cms_impostazione('sito_nome', CMS_NOME))],
        'organizer' => ['@type' => 'Organization', 'name' => (string)cms_impostazione('sito_nome', CMS_NOME), 'url' => cms_url_sito() . '/'],
        'url' => cms_url_sito() . evi_url_evento($e)];
    if ((int)$e['fine_ts'] > (int)$e['inizio_ts']) $ld['endDate'] = $iso((int)$e['fine_ts']);
    if (trim((string)$e['descrizione']) !== '') $ld['description'] = mb_substr(trim((string)$e['descrizione']), 0, 500);
    if ($e['foto'] !== '') $ld['image'] = str_starts_with((string)$e['foto'], '/') ? preg_replace('~^(https?://[^/]+).*$~', '$1', cms_url_sito()) . $e['foto'] : (string)$e['foto'];
    if ((int)$e['posti'] > 0) $ld['maximumAttendeeCapacity'] = (int)$e['posti'];
    return '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
}

// ------------------------------------------------------------
// Cron frequente: promemoria
// ------------------------------------------------------------

/**
 * Il giro frequente: promemoria agli iscritti degli eventi che cominciano entro 24 ore (iscritti da più di 24 ore), una
 * volta sola; poi (2.1) gli eventi finiti: svolto_at si prende con UPDATE ... WHERE svolto_at IS NULL e parte l'evento
 * evento_svolto (l'evento e i suoi iscritti confermati, con la presenza) per gli altri addon: Raccolta recensioni, se è
 * accesa, prepara gli inviti "Com'è andata?". Solo eventi pubblicati e finiti da meno di 30 giorni.
 */
function evi_cron(?int $adesso = null): string {
    evi_schema();
    $adesso ??= time();
    $mandati = 0; $falliti = 0; $svolti = 0;
    if (evi_impostazioni()['promemoria']) {
        $st = db()->prepare('SELECT i.*, e.inizio_ts FROM ' . tbl('evi_iscrizioni') . ' i JOIN ' . tbl('evi_eventi') . " e ON e.id = i.evento_id WHERE i.stato = 'confermata' AND i.promemoria_at IS NULL AND e.stato = 'pubblicato' AND e.inizio_ts > ? AND e.inizio_ts <= ? ORDER BY e.inizio_ts LIMIT 100");
        $st->execute([$adesso, $adesso + 86400]);
        foreach ($st->fetchAll() as $i) {
            if ((int)$i['inizio_ts'] - strtotime((string)($i['entrato_at'] ?: $i['creato'])) <= 86400) continue;
            $presa = db()->prepare('UPDATE ' . tbl('evi_iscrizioni') . ' SET promemoria_at = ? WHERE id = ? AND promemoria_at IS NULL');
            $presa->execute([date('Y-m-d H:i:s', $adesso), (int)$i['id']]);
            if ($presa->rowCount() !== 1) continue;
            if (evi_email(evi_evento((int)$i['evento_id']), $i, 'promemoria')) { $mandati++; continue; }
            db()->prepare('UPDATE ' . tbl('evi_iscrizioni') . ' SET promemoria_at = NULL WHERE id = ?')->execute([(int)$i['id']]);
            $falliti++;
        }
    }
    $st = db()->prepare('SELECT * FROM ' . tbl('evi_eventi') . " WHERE stato = 'pubblicato' AND svolto_at IS NULL AND IF(fine_ts > inizio_ts, fine_ts, inizio_ts + 7200) <= ? AND IF(fine_ts > inizio_ts, fine_ts, inizio_ts + 7200) > ? ORDER BY inizio_ts LIMIT 20");
    $st->execute([$adesso, $adesso - 30 * 86400]);
    foreach ($st->fetchAll() as $e) {
        $presa = db()->prepare('UPDATE ' . tbl('evi_eventi') . ' SET svolto_at = ? WHERE id = ? AND svolto_at IS NULL');
        $presa->execute([date('Y-m-d H:i:s', $adesso), (int)$e['id']]);
        if ($presa->rowCount() !== 1) continue;
        $svolti++;
        $e['svolto_at'] = date('Y-m-d H:i:s', $adesso);
        $i = db()->prepare('SELECT * FROM ' . tbl('evi_iscrizioni') . " WHERE evento_id = ? AND stato = 'confermata' ORDER BY id");
        $i->execute([(int)$e['id']]);
        try { cms_evento('evento_svolto', $e, $i->fetchAll()); } catch (Throwable $x) { error_log('eventi: evento evento_svolto: ' . $x->getMessage()); }
    }
    $out = [];
    if ($mandati || $falliti) $out[] = "$mandati promemoria" . ($falliti ? ", $falliti non partiti (si riprova)" : '');
    if ($svolti) $out[] = $svolti === 1 ? '1 evento concluso' : "$svolti eventi conclusi";
    return implode(', ', $out);
}
