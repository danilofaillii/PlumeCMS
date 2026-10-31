<?php
/**
 * Il motore dell'addon Prenotazioni: tabelle, impostazioni, posti liberi, prenota, sposta, annulla, email, file .ics, cron.
 *
 * Le ore: tutto nel fuso del sito (date_default_timezone_get(), CMS_FUSO). Ogni prenotazione ha l'ora scritta (inizio, fine)
 * e i secondi veri (inizio_ts, fine_ts, blocco_ts = fine più la pausa): le sovrapposizioni si controllano sui secondi, così
 * il cambio dell'ora legale non inganna. Un orario che quel giorno non esiste (dalle 2 alle 3 dell'ultima domenica di marzo)
 * non si offre.
 * Due richieste insieme: la prenotazione si scrive in una transazione che blocca le righe delle persone (SELECT ... FOR UPDATE)
 * e ricalcola i posti dopo il blocco; in più la colonna slot_attivo è UNIQUE con la persona (stesso inizio due volte: no).
 * Gli orari settimanali si scrivono come nel widget Orari di apertura ("9:00-13:00, 15:00-19:00") e si leggono con la sua
 * funzione, ewx_orari_intervalli().
 */
require_once dirname(__DIR__, 3) . '/includes/moduli.php';

const PREN_STATI_ATTIVI = ['confermata', 'da_confermare'];
const PREN_STATI = ['da_confermare' => 'Da confermare', 'confermata' => 'Confermata', 'svolta' => 'Svolta', 'non_presentato' => 'Non presentato', 'annullata' => 'Annullata'];
const PREN_LIMITE_ORA = 6;         // prenotazioni nuove per indirizzo IP, in un'ora
const PREN_GIORNI_LINK = 30;       // il link personale vale fino a 30 giorni dopo l'appuntamento
const PREN_GIORNI = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
const PREN_GIORNI_NOMI = ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
const PREN_MESI = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
const PREN_PREDEFINITE = [
    'anticipo_ore' => 2, 'anticipo_giorni' => 60, 'passo' => 30, 'limite_ore' => 24, 'da_confermare' => false,
    'scegli_persona' => true, 'avviso_a' => '', 'promemoria' => true, 'chiusure' => [], 'indirizzo' => '', 'nota_email' => '',
    'privacy' => '/privacy-policy/',
];

// ------------------------------------------------------------
// Tabelle
// ------------------------------------------------------------

function pren_tabelle(): array {
    return [
        'pren_servizi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('pren_servizi') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(150) NOT NULL,
            `descrizione` VARCHAR(500) NOT NULL DEFAULT '',
            `durata` SMALLINT NOT NULL DEFAULT 30,
            `pausa` SMALLINT NOT NULL DEFAULT 0,
            `prezzo` VARCHAR(40) NOT NULL DEFAULT '',
            `risorse` VARCHAR(255) NOT NULL DEFAULT '',
            `attivo` TINYINT(1) NOT NULL DEFAULT 1,
            `ordine` INT NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'pren_risorse' => 'CREATE TABLE IF NOT EXISTS ' . tbl('pren_risorse') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `descrizione` VARCHAR(300) NOT NULL DEFAULT '',
            `orari` TEXT NOT NULL,
            `ferie` TEXT NOT NULL,
            `attivo` TINYINT(1) NOT NULL DEFAULT 1,
            `ordine` INT NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'pren_prenotazioni' => 'CREATE TABLE IF NOT EXISTS ' . tbl('pren_prenotazioni') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `codice` CHAR(32) NOT NULL,
            `servizio_id` INT NOT NULL,
            `risorsa_id` INT NOT NULL,
            `servizio` VARCHAR(150) NOT NULL DEFAULT '',
            `risorsa` VARCHAR(100) NOT NULL DEFAULT '',
            `prezzo` VARCHAR(40) NOT NULL DEFAULT '',
            `inizio` DATETIME NOT NULL,
            `fine` DATETIME NOT NULL,
            `inizio_ts` BIGINT NOT NULL,
            `fine_ts` BIGINT NOT NULL,
            `blocco_ts` BIGINT NOT NULL,
            `nome` VARCHAR(150) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `telefono` VARCHAR(40) NOT NULL DEFAULT '',
            `note` VARCHAR(1000) NOT NULL DEFAULT '',
            `stato` VARCHAR(20) NOT NULL DEFAULT 'confermata',
            `slot_attivo` BIGINT GENERATED ALWAYS AS (IF(`stato` IN ('confermata','da_confermare'), `inizio_ts`, NULL)) STORED,
            `messaggio` VARCHAR(1000) NOT NULL DEFAULT '',
            `origine` VARCHAR(10) NOT NULL DEFAULT 'sito',
            `sequenza` INT NOT NULL DEFAULT 0,
            `promemoria_at` DATETIME NULL DEFAULT NULL,
            `consenso_at` DATETIME NOT NULL,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `posto` (`risorsa_id`, `slot_attivo`),
            KEY `inizio` (`inizio_ts`),
            KEY `stato` (`stato`),
            KEY `ip` (`ip`, `creato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
}

/** Crea le tabelle (accendendo l'addon, e la prima volta che servono dopo un aggiornamento). Ritorna una riga di log. */
function pren_installa(): string {
    foreach (pren_tabelle() as $sql) db()->exec($sql);
    pren_colonne_nuove();
    save_setting('pren_schema_impronta', md5(serialize(pren_tabelle()) . DB_NAME));
    return 'tabelle a posto';
}

/**
 * 2.1: le colonne nate dopo la 2.0.0 sulle tabelle che c'erano già (CREATE TABLE IF NOT EXISTS non le aggiunge).
 * origine: 'sito' (dal widget) o 'pannello' (scritta dall'attività: telefonata, di persona). sequenza: il numero di
 * revisione del file per il calendario (RFC 5545 SEQUENCE), cresce a ogni spostamento e all'annullamento.
 */
function pren_colonne_nuove(): void {
    $tab = tbl('pren_prenotazioni');
    $ci = [];
    foreach (db()->query('SHOW COLUMNS FROM ' . $tab)->fetchAll() as $c) $ci[$c['Field']] = true;
    if (!isset($ci['origine'])) db()->exec('ALTER TABLE ' . $tab . " ADD COLUMN `origine` VARCHAR(10) NOT NULL DEFAULT 'sito' AFTER `messaggio`");
    if (!isset($ci['sequenza'])) db()->exec('ALTER TABLE ' . $tab . ' ADD COLUMN `sequenza` INT NOT NULL DEFAULT 0 AFTER `origine`');
}

function pren_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    if (get_setting('pren_schema_impronta', '') !== md5(serialize(pren_tabelle()) . DB_NAME)) pren_installa();
    $fatto = true;
}

// ------------------------------------------------------------
// Impostazioni, testi, firme
// ------------------------------------------------------------

function pren_impostazioni(): array {
    $s = json_decode((string)get_setting('pren_impostazioni', ''), true);
    return (is_array($s) ? $s : []) + PREN_PREDEFINITE;
}

/** Un testo che arriva dal web o dal pannello: UTF-8 valido, senza caratteri a 4 byte né di controllo, tagliato. */
function pren_testo(mixed $s, int $max, bool $righe = false): string {
    $s = is_scalar($s) ? mb_scrub((string)$s, 'UTF-8') : '';
    $s = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
    $s = (string)preg_replace($righe ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $righe ? '' : ' ', $s);
    return mb_substr(trim($s), 0, $max);
}

/** La firma del link personale di una prenotazione: senza il codice segreto della riga (e quello del sito) non si rifà. */
function pren_firma(int $id, string $codice): string {
    return substr(hash_hmac('sha256', "prenotazione|$id|$codice", moduli_segreto()), 0, 40);
}

/** La prenotazione di un link personale (id e firma), oppure null se la firma non torna o il link è scaduto. */
function pren_da_link(mixed $id, mixed $firma): ?array {
    if (!is_string($id) || !ctype_digit($id) || !is_string($firma) || !preg_match('/^[a-f0-9]{40}$/', $firma)) return null;
    $p = pren_leggi((int)$id);
    if (!$p || !hash_equals(pren_firma((int)$p['id'], (string)$p['codice']), $firma)) return null;
    if ((int)$p['fine_ts'] + PREN_GIORNI_LINK * 86400 < time()) return null;
    return $p;
}

function pren_url(string $cosa, array $p): string {
    return cms_url_sito() . '/prenotazione/' . $cosa . '/?' . http_build_query(['p' => (int)$p['id'], 't' => pren_firma((int)$p['id'], (string)$p['codice'])]);
}

/** "lunedì 2 novembre 2026" e "9:30", nel fuso del sito. */
function pren_giorno_esteso(int $ts, bool $anno = true): string {
    $d = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    return PREN_GIORNI_NOMI[(int)$d->format('N') - 1] . ' ' . (int)$d->format('j') . ' ' . PREN_MESI[(int)$d->format('n') - 1] . ($anno ? ' ' . $d->format('Y') : '');
}

function pren_ora(int $ts): string {
    return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('G:i');
}

function pren_quando(array $p): string {
    return pren_giorno_esteso((int)$p['inizio_ts']) . ' alle ' . pren_ora((int)$p['inizio_ts']);
}

// ------------------------------------------------------------
// Servizi e persone
// ------------------------------------------------------------

function pren_servizi(bool $soloAttivi = false): array {
    pren_schema();
    return db()->query('SELECT * FROM ' . tbl('pren_servizi') . ($soloAttivi ? ' WHERE attivo = 1' : '') . ' ORDER BY ordine, nome, id')->fetchAll();
}

function pren_servizio(int $id): ?array {
    pren_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('pren_servizi') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function pren_risorse(bool $soloAttive = false): array {
    pren_schema();
    $r = db()->query('SELECT * FROM ' . tbl('pren_risorse') . ($soloAttive ? ' WHERE attivo = 1' : '') . ' ORDER BY ordine, nome, id')->fetchAll();
    foreach ($r as &$x) { $x['orari'] = json_decode((string)$x['orari'], true) ?: []; $x['ferie'] = json_decode((string)$x['ferie'], true) ?: []; }
    return $r;
}

/** Le persone (attive) che fanno un servizio: quelle scelte nel servizio, oppure tutte se non ne ha scelte. */
function pren_risorse_servizio(array $s): array {
    $ids = array_filter(array_map('intval', explode(',', (string)$s['risorse'])));
    return array_values(array_filter(pren_risorse(true), fn($r) => !$ids || in_array((int)$r['id'], $ids, true)));
}

/** Le chiusure valide [dal, al, motivo] di un elenco [{dal, al, motivo}] (le stesse regole del widget Orari di apertura). */
function pren_chiusure(array $l): array {
    $out = [];
    foreach ($l as $c) {
        $da = trim((string)($c['dal'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $da)) continue;
        $a = trim((string)($c['al'] ?? ''));
        $out[] = [$da, preg_match('/^\d{4}-\d{2}-\d{2}$/', $a) && $a >= $da ? $a : $da, trim((string)($c['motivo'] ?? ''))];
    }
    return $out;
}

function pren_chiuso(array $l, string $giorno): bool {
    foreach (pren_chiusure($l) as [$da, $a]) if ($giorno >= $da && $giorno <= $a) return true;
    return false;
}

/** Gli intervalli di un giorno scritti come nel widget Orari di apertura, in minuti: la funzione è quella del widget. */
function pren_intervalli(string $testo): array {
    if (!function_exists('ewx_orari_intervalli')) {
        require_once cms_radice() . '/includes/editor-controlli.php';
        require_once cms_radice() . '/includes/editor.php';
        editor_carica_widget_esterni();
    }
    return ewx_orari_intervalli($testo);
}

// ------------------------------------------------------------
// Posti liberi
// ------------------------------------------------------------

/** Le prenotazioni attive di alcune persone fra due istanti: risorsa_id => [[inizio_ts, blocco_ts], ...]. */
function pren_occupati(array $risorse, int $da, int $a, int $escludi = 0): array {
    if (!$risorse) return [];
    $in = implode(',', array_map('intval', $risorse));
    $st = db()->prepare('SELECT id, risorsa_id, inizio_ts, blocco_ts FROM ' . tbl('pren_prenotazioni') . " WHERE risorsa_id IN ($in) AND stato IN ('confermata','da_confermare') AND blocco_ts > ? AND inizio_ts < ? AND id <> ?");
    $st->execute([$da, $a, $escludi]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['risorsa_id']][] = [(int)$r['inizio_ts'], (int)$r['blocco_ts']];
    return $out;
}

/**
 * I posti liberi di un servizio in un giorno (AAAA-MM-GG, nel fuso del sito): 'H:MM' => [id delle persone libere].
 * $risorsa 0 = qualsiasi persona. $adesso: l'istante da cui contano gli anticipi (le prove lo fissano). $escludi: una
 * prenotazione che non conta (quella che si sta spostando).
 */
function pren_posti(array $servizio, int $risorsa, string $giorno, ?int $adesso = null, int $escludi = 0, bool $pannello = false): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $giorno) || !checkdate((int)substr($giorno, 5, 2), (int)substr($giorno, 8, 2), (int)substr($giorno, 0, 4))) return [];
    if (empty($servizio['attivo'])) return [];
    $imp = pren_impostazioni();
    $adesso ??= time();
    if (pren_chiuso((array)$imp['chiusure'], $giorno)) return [];
    $tz = new DateTimeZone(date_default_timezone_get());
    $mezzanotte = new DateTimeImmutable($giorno . ' 00:00:00', $tz);
    // Dal pannello (2.1) l'attività prenota anche fra un'ora o fra sei mesi: gli anticipi valgono per chi prenota dal sito.
    $minimo = $pannello ? $adesso : $adesso + max(0, (int)$imp['anticipo_ore']) * 3600;
    $massimo = $pannello ? $adesso + 366 * 86400 : $adesso + max(1, (int)$imp['anticipo_giorni']) * 86400;
    if ($mezzanotte->getTimestamp() > $massimo) return [];
    $durata = max(5, (int)$servizio['durata']) * 60;
    $pausa = max(0, (int)$servizio['pausa']) * 60;
    $passo = max(5, min(240, (int)$imp['passo']));
    $risorse = array_values(array_filter(pren_risorse_servizio($servizio), fn($r) => $risorsa === 0 || (int)$r['id'] === $risorsa));
    $occ = pren_occupati(array_column($risorse, 'id'), $mezzanotte->getTimestamp() - 86400, $mezzanotte->getTimestamp() + 2 * 86400, $escludi);
    $chiave = PREN_GIORNI[(int)$mezzanotte->format('N') - 1];
    $out = [];
    foreach ($risorse as $r) {
        if (pren_chiuso((array)$r['ferie'], $giorno)) continue;
        foreach (pren_intervalli((string)($r['orari'][$chiave] ?? '')) as [$da, $a]) {
            $a = min($a, 1440); // un turno oltre la mezzanotte si prenota fino a mezzanotte
            $chiude = $a >= 1440 ? $mezzanotte->modify('+1 day')->getTimestamp() : $mezzanotte->setTime(intdiv($a, 60), $a % 60)->getTimestamp();
            for ($t = $da; $t < $a; $t += $passo) {
                $ora = sprintf('%d:%02d', intdiv($t, 60), $t % 60);
                $dt = $mezzanotte->setTime(intdiv($t, 60), $t % 60);
                if ($dt->format('G:i') !== $ora) continue; // quel giorno quest'ora non esiste (cambio dell'ora)
                $s = $dt->getTimestamp();
                if ($s < $minimo || $s > $massimo || $s + $durata > $chiude) continue;
                $b = $s + $durata + $pausa;
                foreach ($occ[(int)$r['id']] ?? [] as [$oi, $ob]) if ($s < $ob && $oi < $b) continue 2;
                $out[$ora][] = (int)$r['id'];
            }
        }
    }
    uksort($out, fn($x, $y) => strcmp(sprintf('%05s', $x), sprintf('%05s', $y)));
    return $out;
}

/** I giorni da $da per $n giorni, con quanti posti liberi ha ognuno: [['d' => 'AAAA-MM-GG', 'n' => 3], ...]. */
function pren_giorni(array $servizio, int $risorsa, string $da, int $n = 14, ?int $adesso = null): array {
    $tz = new DateTimeZone(date_default_timezone_get());
    $g = DateTimeImmutable::createFromFormat('!Y-m-d', $da, $tz) ?: new DateTimeImmutable('today', $tz);
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $d = $g->modify("+$i day")->format('Y-m-d');
        $out[] = ['d' => $d, 'n' => count(pren_posti($servizio, $risorsa, $d, $adesso))];
    }
    return $out;
}

// ------------------------------------------------------------
// Prenotare, spostare, annullare
// ------------------------------------------------------------

function pren_leggi(int $id): ?array {
    pren_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('pren_prenotazioni') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Troppe prenotazioni nuove dallo stesso indirizzo IP nell'ultima ora? */
function pren_troppi(): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('pren_prenotazioni') . ' WHERE ip = ? AND creato > ?');
    $st->execute([moduli_ip(), date('Y-m-d H:i:s', time() - 3600)]);
    return (int)$st->fetchColumn() >= PREN_LIMITE_ORA;
}

/**
 * Blocca le righe delle persone (in ordine di id, così due richieste non si incrociano) e ricalcola i posti: chi arriva
 * secondo aspetta il primo e poi vede il posto già preso. Va chiamata dentro una transazione.
 */
function pren_blocca(array $servizio): void {
    $ids = array_map('intval', array_column(pren_risorse_servizio($servizio), 'id'));
    if (!$ids) return;
    sort($ids);
    db()->query('SELECT id FROM ' . tbl('pren_risorse') . ' WHERE id IN (' . implode(',', $ids) . ') ORDER BY id FOR UPDATE')->fetchAll();
}

function pren_transazione(callable $f) {
    $pdo = db();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();
    try {
        $r = $f($pdo);
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Lo stesso posto preso nello stesso istante: lo ferma l'indice UNIQUE.
        if ($e instanceof PDOException && ($e->errorInfo[1] ?? 0) === 1062) return 'posto';
        throw $e;
    }
}

/** Il nome di una persona (attiva) dal suo id, oppure ''. */
function pren_nome_risorsa(int $id): string {
    foreach (pren_risorse(true) as $x) if ((int)$x['id'] === $id) return (string)$x['nome'];
    return '';
}

/** L'istante di un giorno e di un'ora nel fuso del sito, oppure null se la data è sbagliata o quell'ora quel giorno non esiste. */
function pren_istante(string $giorno, string $ora): ?DateTimeImmutable {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $giorno) || !checkdate((int)substr($giorno, 5, 2), (int)substr($giorno, 8, 2), (int)substr($giorno, 0, 4))) return null;
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $ora, $m) || (int)$m[1] > 23 || (int)$m[2] > 59) return null;
    $ini = (new DateTimeImmutable($giorno . ' 00:00:00', new DateTimeZone(date_default_timezone_get())))->setTime((int)$m[1], (int)$m[2]);
    return $ini->format('G:i') === sprintf('%d:%02d', (int)$m[1], (int)$m[2]) ? $ini : null;
}

/** Scrive la riga di una prenotazione nuova (dentro la transazione, dopo i controlli). Ritorna l'id. */
function pren_inserisci(PDO $pdo, array $s, int $rid, DateTimeImmutable $ini, array $d, string $stato, string $origine): int {
    $iTs = $ini->getTimestamp();
    $fTs = $iTs + (int)$s['durata'] * 60;
    $ora0 = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO ' . tbl('pren_prenotazioni') . ' (codice, servizio_id, risorsa_id, servizio, risorsa, prezzo, inizio, fine, inizio_ts, fine_ts, blocco_ts, nome, email, telefono, note, stato, origine, consenso_at, ip, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([bin2hex(random_bytes(16)), (int)$s['id'], $rid, $s['nome'], pren_nome_risorsa($rid), $s['prezzo'], $ini->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $fTs), $iTs, $fTs, $fTs + (int)$s['pausa'] * 60,
            $d['nome'], $d['email'] ?? '', $d['telefono'] ?? '', $d['note'] ?? '', $stato, $origine, $ora0, $origine === 'pannello' ? '' : moduli_ip(), $ora0, $ora0]);
    return (int)$pdo->lastInsertId();
}

/**
 * Una prenotazione nuova. $d: servizio, risorsa (0 = qualsiasi), giorno, ora, nome, email, telefono, note.
 * Ritorna ['id' => ...] oppure il codice d'errore ('servizio', 'posto', ...).
 */
function pren_prenota(array $d, ?int $adesso = null): array|string {
    pren_schema();
    $s = pren_servizio((int)($d['servizio'] ?? 0));
    if (!$s || !$s['attivo']) return 'servizio';
    $risorsa = (int)($d['risorsa'] ?? 0);
    if ($risorsa && !in_array($risorsa, array_map('intval', array_column(pren_risorse_servizio($s), 'id')), true)) return 'servizio';
    $giorno = (string)($d['giorno'] ?? '');
    $ora = (string)($d['ora'] ?? '');
    if (!preg_match('/^\d{1,2}:\d{2}$/', $ora)) return 'posto';
    $imp = pren_impostazioni();
    $r = pren_transazione(function (PDO $pdo) use ($s, $risorsa, $giorno, $ora, $d, $adesso, $imp) {
        pren_blocca($s);
        $posti = pren_posti($s, $risorsa, $giorno, $adesso);
        if (empty($posti[$ora])) return 'posto';
        $ini = pren_istante($giorno, $ora);
        if (!$ini) return 'posto';
        return ['id' => pren_inserisci($pdo, $s, $posti[$ora][0], $ini, $d, $imp['da_confermare'] ? 'da_confermare' : 'confermata', 'sito')];
    });
    if (is_array($r)) {
        $p = pren_leggi($r['id']);
        cms_evento('prenotazione_nuova', $p);
        pren_email_cliente($p, $p['stato'] === 'da_confermare' ? 'ricevuta' : 'confermata');
        pren_email_attivita($p, 'nuova');
    }
    return $r;
}

/**
 * 2.1: una prenotazione scritta dall'attività nel pannello (una telefonata, un cliente al banco). $d: servizio, risorsa
 * (0 = la prima persona libera), giorno, ora, nome, telefono, email (facoltativa), note.
 * Senza $forza l'ora deve essere fra i posti liberi (gli stessi del sito, senza gli anticipi minimo e massimo). Con $forza
 * va bene anche fuori dagli orari di lavoro, in un giorno chiuso o di ferie, ma con una persona scelta e mai sopra un
 * altro appuntamento della stessa persona: le stesse regole contro le doppie prenotazioni (blocco delle righe, poi
 * controllo, più l'indice UNIQUE). Nasce confermata. L'email di conferma parte solo se c'è l'indirizzo e $email è vero;
 * l'avviso all'attività non parte (l'ha scritta lei). Ritorna ['id' => ..., 'fuori' => bool, 'email' => bool] oppure un
 * codice d'errore: 'servizio', 'persona', 'quando' (data o ora sbagliate), 'posto' (non è un posto libero), 'occupato'
 * (la persona ha già un appuntamento che si sovrappone).
 */
function pren_prenota_pannello(array $d, bool $forza = false, bool $email = true, ?int $adesso = null): array|string {
    pren_schema();
    $s = pren_servizio((int)($d['servizio'] ?? 0));
    if (!$s || !$s['attivo']) return 'servizio';
    $adesso ??= time();
    $risorsa = (int)($d['risorsa'] ?? 0);
    $persone = array_map('intval', array_column(pren_risorse_servizio($s), 'id'));
    if ($risorsa && !in_array($risorsa, $persone, true)) return 'persona';
    if ($forza && !$risorsa) return 'persona';
    $giorno = (string)($d['giorno'] ?? '');
    $ini = pren_istante($giorno, (string)($d['ora'] ?? ''));
    if (!$ini) return 'quando';
    $ora = $ini->format('G:i');
    $r = pren_transazione(function (PDO $pdo) use ($s, $risorsa, $giorno, $ora, $ini, $d, $forza, $adesso) {
        pren_blocca($s);
        $posti = pren_posti($s, $risorsa, $giorno, $adesso, 0, true);
        if (!empty($posti[$ora])) return ['id' => pren_inserisci($pdo, $s, $posti[$ora][0], $ini, $d, 'confermata', 'pannello'), 'fuori' => false];
        if (!$forza) return 'posto';
        // Fuori dagli orari liberi, ma mai sopra un altro appuntamento della stessa persona.
        $iTs = $ini->getTimestamp();
        $bTs = $iTs + ((int)$s['durata'] + (int)$s['pausa']) * 60;
        foreach (pren_occupati([$risorsa], $iTs - 86400, $bTs + 86400)[$risorsa] ?? [] as [$oi, $ob]) if ($iTs < $ob && $oi < $bTs) return 'occupato';
        return ['id' => pren_inserisci($pdo, $s, $risorsa, $ini, $d, 'confermata', 'pannello'), 'fuori' => true];
    });
    if (is_array($r)) {
        $p = pren_leggi($r['id']);
        cms_evento('prenotazione_nuova', $p);
        $r['email'] = $email && filter_var($p['email'], FILTER_VALIDATE_EMAIL) ? pren_email_cliente($p, 'confermata') : false;
    }
    return $r;
}

/** Si può ancora annullare o spostare dal link? (fino a limite_ore prima dell'inizio) */
function pren_modificabile(array $p, ?int $adesso = null): bool {
    return in_array($p['stato'], PREN_STATI_ATTIVI, true) && (int)$p['inizio_ts'] - ($adesso ?? time()) >= (int)pren_impostazioni()['limite_ore'] * 3600;
}

/** Sposta una prenotazione a un altro giorno e ora, con la stessa persona se c'era una scelta, altrimenti la prima libera. */
function pren_sposta(array $p, string $giorno, string $ora, bool $dalCliente = true, ?int $adesso = null): string {
    if ($dalCliente && !pren_modificabile($p, $adesso)) return 'tardi';
    if (!in_array($p['stato'], PREN_STATI_ATTIVI, true)) return 'tardi';
    $s = pren_servizio((int)$p['servizio_id']);
    if (!$s || !preg_match('/^\d{1,2}:\d{2}$/', $ora)) return 'posto';
    $r = pren_transazione(function (PDO $pdo) use ($p, $s, $giorno, $ora, $adesso) {
        pren_blocca($s);
        $posti = pren_posti($s, 0, $giorno, $adesso, (int)$p['id']);
        $liberi = $posti[$ora] ?? [];
        if (!$liberi) return 'posto';
        $rid = in_array((int)$p['risorsa_id'], $liberi, true) ? (int)$p['risorsa_id'] : $liberi[0];
        $nome = $p['risorsa'];
        foreach (pren_risorse(true) as $x) if ((int)$x['id'] === $rid) $nome = $x['nome'];
        [$h, $m] = array_map('intval', explode(':', $ora));
        $ini = (new DateTimeImmutable($giorno . ' 00:00:00', new DateTimeZone(date_default_timezone_get())))->setTime($h, $m);
        $iTs = $ini->getTimestamp();
        $fTs = $iTs + (int)$s['durata'] * 60;
        $pdo->prepare('UPDATE ' . tbl('pren_prenotazioni') . ' SET risorsa_id = ?, risorsa = ?, inizio = ?, fine = ?, inizio_ts = ?, fine_ts = ?, blocco_ts = ?, promemoria_at = NULL, sequenza = sequenza + 1, aggiornato = ? WHERE id = ?')
            ->execute([$rid, $nome, $ini->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $fTs), $iTs, $fTs, $fTs + (int)$s['pausa'] * 60, date('Y-m-d H:i:s'), (int)$p['id']]);
        return '';
    });
    if ($r === '') {
        $nuova = pren_leggi((int)$p['id']);
        pren_email_cliente($nuova, 'spostata');
        if ($dalCliente) pren_email_attivita($nuova, 'spostata', pren_quando($p));
    }
    return (string)$r;
}

/** Annulla. $da: 'cliente' (dal link, entro il limite) oppure 'attivita' (dal pannello, con un messaggio facoltativo). */
function pren_annulla(array $p, string $da, string $messaggio = '', ?int $adesso = null): string {
    if ($da === 'cliente' && !pren_modificabile($p, $adesso)) return 'tardi';
    $st = db()->prepare('UPDATE ' . tbl('pren_prenotazioni') . " SET stato = 'annullata', messaggio = ?, sequenza = sequenza + 1, aggiornato = ? WHERE id = ? AND stato IN ('confermata','da_confermare')");
    $st->execute([pren_testo($messaggio, 1000, true), date('Y-m-d H:i:s'), (int)$p['id']]);
    if ($st->rowCount() !== 1) return 'tardi';
    $p = pren_leggi((int)$p['id']);
    cms_evento('prenotazione_annullata', $p);
    pren_email_cliente($p, 'annullata', $da === 'attivita' ? $messaggio : '');
    if ($da === 'cliente') pren_email_attivita($p, 'annullata');
    return '';
}

/** Dal pannello: conferma (da confermare -> confermata) o segna "non presentato". */
function pren_cambia_stato(array $p, string $stato): bool {
    if ($stato === 'confermata') {
        $st = db()->prepare('UPDATE ' . tbl('pren_prenotazioni') . " SET stato = 'confermata', aggiornato = ? WHERE id = ? AND stato = 'da_confermare'");
        $st->execute([date('Y-m-d H:i:s'), (int)$p['id']]);
        if ($st->rowCount() === 1) { pren_email_cliente(pren_leggi((int)$p['id']), 'confermata'); return true; }
        return false;
    }
    if ($stato === 'non_presentato') {
        $st = db()->prepare('UPDATE ' . tbl('pren_prenotazioni') . " SET stato = 'non_presentato', aggiornato = ? WHERE id = ? AND stato IN ('confermata','svolta') AND inizio_ts <= ?");
        $st->execute([date('Y-m-d H:i:s'), (int)$p['id'], time()]);
        if ($st->rowCount() === 1) { cms_evento('prenotazione_non_presentata', pren_leggi((int)$p['id'])); return true; }
        return false;
    }
    if ($stato === 'svolta') { // un "non presentato" segnato per sbaglio torna svolto
        $st = db()->prepare('UPDATE ' . tbl('pren_prenotazioni') . " SET stato = 'svolta', aggiornato = ? WHERE id = ? AND stato = 'non_presentato'");
        $st->execute([date('Y-m-d H:i:s'), (int)$p['id']]);
        return $st->rowCount() === 1;
    }
    return false;
}

// ------------------------------------------------------------
// Email e file per il calendario
// ------------------------------------------------------------

/** Un testo per l'oggetto o per un'intestazione: niente a capo (nessuna intestazione nascosta). */
function pren_riga(string $s): string {
    return trim((string)preg_replace('/[\r\n\t\x00-\x1F\x7F]+/', ' ', $s));
}

function pren_riepilogo_html(array $p): string {
    $righe = [['Servizio', $p['servizio'] . ($p['prezzo'] !== '' ? ' (' . $p['prezzo'] . ')' : '')], ['Quando', pren_quando($p)]];
    if ($p['risorsa'] !== '' && count(pren_risorse(true)) > 1) $righe[] = ['Con', $p['risorsa']];
    $ind = trim((string)pren_impostazioni()['indirizzo']);
    if ($ind !== '') $righe[] = ['Dove', $ind];
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;font-size:15px">' . implode('', array_map(fn($r) => '<tr><td style="padding:4px 14px 4px 0;color:#666">' . h($r[0]) . '</td><td style="padding:4px 0"><b>' . h($r[1]) . '</b></td></tr>', $righe)) . '</table>';
}

function pren_email_cliente(?array $p, string $tipo, string $messaggio = ''): bool {
    if (!$p || !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) return false;
    $imp = pren_impostazioni();
    $sito = (string)cms_impostazione('sito_nome', CMS_NOME);
    $ciao = '<p>Ciao ' . h(strtok((string)$p['nome'], ' ') ?: (string)$p['nome']) . ',</p>';
    $gestisci = '<p style="font-size:14px;color:#555">Se non puoi più venire, puoi annullare o spostare l\'appuntamento da solo fino a ' . (int)$imp['limite_ore'] . ' ore prima, dal link qui sotto.</p>';
    $cal = '<p style="font-size:14px"><a href="' . h(pren_url('calendario', $p)) . '">Aggiungi al calendario</a> (file .ics, per il calendario del telefono o del computer)</p>';
    $nota = trim((string)$imp['nota_email']) !== '' ? '<p>' . nl2br(h((string)$imp['nota_email'])) . '</p>' : '';
    switch ($tipo) {
        case 'ricevuta':
            [$ogg, $tit, $corpo] = ['Richiesta di prenotazione ricevuta - ' . $sito, 'Abbiamo ricevuto la tua richiesta', $ciao . '<p>abbiamo ricevuto la tua richiesta di prenotazione. Ti scriviamo appena la confermiamo.</p>' . pren_riepilogo_html($p) . $gestisci]; break;
        case 'confermata':
            [$ogg, $tit, $corpo] = ['Prenotazione confermata: ' . pren_quando($p) . ' - ' . $sito, 'La tua prenotazione è confermata', $ciao . '<p>ti aspettiamo:</p>' . pren_riepilogo_html($p) . $nota . $cal . $gestisci]; break;
        case 'spostata':
            [$ogg, $tit, $corpo] = ['Prenotazione spostata: ' . pren_quando($p) . ' - ' . $sito, 'Il tuo appuntamento è stato spostato', $ciao . '<p>il tuo appuntamento adesso è:</p>' . pren_riepilogo_html($p) . $nota . $cal . $gestisci]; break;
        case 'promemoria':
            [$ogg, $tit, $corpo] = ['Promemoria: ' . pren_quando($p) . ' - ' . $sito, 'Ti ricordiamo l\'appuntamento', $ciao . '<p>ti ricordiamo il tuo appuntamento:</p>' . pren_riepilogo_html($p) . $nota . $cal . $gestisci]; break;
        case 'annullata':
            [$ogg, $tit, $corpo] = ['Prenotazione annullata - ' . $sito, 'La prenotazione è annullata', $ciao . '<p>la prenotazione di ' . h(pren_quando($p)) . ' (' . h((string)$p['servizio']) . ') è annullata.</p>'
                . (trim($messaggio) !== '' ? '<p style="border-left:3px solid #7c3aed;padding-left:12px">' . nl2br(h(trim($messaggio))) . '</p>' : '') . '<p>Se vuoi, puoi prenotare un altro giorno dal sito.</p>']; break;
        default: return false;
    }
    $btn = in_array($tipo, ['ricevuta', 'confermata', 'spostata', 'promemoria'], true) ? ['Annulla o sposta', pren_url('gestisci', $p)] : ['Vai al sito', cms_url_sito() . '/'];
    // 2.1: il file per il calendario arriva anche come allegato vero (oltre al link): REQUEST per conferma e spostamento,
    // CANCEL per l'annullamento, con lo stesso UID e la SEQUENCE che cresce, così il calendario aggiorna l'appuntamento.
    $allegati = [];
    if (in_array($tipo, ['confermata', 'spostata', 'annullata'], true)) {
        $metodo = $tipo === 'annullata' ? 'CANCEL' : 'REQUEST';
        $allegati[] = ['nome' => $tipo === 'annullata' ? 'annullamento.ics' : 'appuntamento.ics', 'tipo' => 'text/calendar; charset=UTF-8; method=' . $metodo, 'dati' => pren_ics($p, $metodo)];
    }
    $ok = posta_invia((string)$p['email'], pren_riga($ogg), moduli_email_html($tit, $corpo, $btn[0], $btn[1]), moduli_intestazioni(pren_rispondi_a()), 'prenotazione', $allegati);
    if (!$ok) error_log('prenotazioni: email ' . $tipo . ' non partita: ' . posta_errore());
    return $ok;
}

/** L'indirizzo dell'attività (avvisi, risposta delle email): quello delle impostazioni, altrimenti quello degli avvisi del sito. */
function pren_indirizzo_attivita(): string {
    $a = trim((string)pren_impostazioni()['avviso_a']);
    if (!filter_var($a, FILTER_VALIDATE_EMAIL)) $a = posta_notifica_email();
    return filter_var($a, FILTER_VALIDATE_EMAIL) ? $a : '';
}

function pren_rispondi_a(): string {
    return pren_indirizzo_attivita();
}

function pren_email_attivita(array $p, string $tipo, string $prima = ''): bool {
    $a = pren_indirizzo_attivita();
    if ($a === '') return false;
    $tit = ['nuova' => $p['stato'] === 'da_confermare' ? 'Nuova prenotazione da confermare' : 'Nuova prenotazione', 'annullata' => 'Prenotazione annullata dal cliente', 'spostata' => 'Prenotazione spostata dal cliente'][$tipo] ?? 'Prenotazione';
    $corpo = '<p><b>' . h((string)$p['nome']) . '</b> &middot; <a href="mailto:' . h((string)$p['email']) . '">' . h((string)$p['email']) . '</a>' . ($p['telefono'] !== '' ? ' &middot; ' . h((string)$p['telefono']) : '') . '</p>'
        . pren_riepilogo_html($p) . ($prima !== '' ? '<p style="color:#666">Prima era: ' . h($prima) . '</p>' : '')
        . ($p['note'] !== '' ? '<p><b>Note:</b> ' . nl2br(h((string)$p['note'])) . '</p>' : '');
    $ok = posta_invia($a, pren_riga($tit . ': ' . $p['nome'] . ', ' . pren_quando($p)), moduli_email_html($tit, $corpo, 'Apri nel pannello', cms_url_pannello() . 'addon-pagina.php?a=prenotazioni&p=elenco&id=' . (int)$p['id']), moduli_intestazioni((string)$p['email']), 'prenotazione');
    if (!$ok) error_log('prenotazioni: avviso non partito: ' . posta_errore());
    return $ok;
}

/** Un testo per il file .ics (RFC 5545): barre, punti e virgola, virgole e a capo protetti. */
function pren_ics_testo(string $s): string {
    return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', ''], $s);
}

/** Le righe lunghe del file .ics si spezzano a 75 byte, senza tagliare una lettera accentata. */
function pren_ics_piega(string $riga): string {
    $out = ''; $pezzo = '';
    foreach (mb_str_split($riga) as $c) {
        if (strlen($pezzo . $c) > ($out === '' ? 75 : 74)) { $out .= ($out === '' ? '' : "\r\n ") . $pezzo; $pezzo = ''; }
        $pezzo .= $c;
    }
    return $out . ($out === '' ? '' : "\r\n ") . $pezzo;
}

/**
 * Il file .ics di una prenotazione. $metodo: PUBLISH (il file scaricato dal link), REQUEST (allegato alla conferma e allo
 * spostamento) o CANCEL (allegato all'annullamento). Lo stesso UID per sempre e SEQUENCE dalla colonna sequenza: il
 * calendario del cliente riconosce l'appuntamento e lo aggiorna (o lo toglie) invece di aggiungerne un altro.
 * REQUEST e CANCEL portano ORGANIZER (l'attività) e ATTENDEE (il cliente), come vuole RFC 5546.
 */
function pren_ics(array $p, string $metodo = 'PUBLISH'): string {
    $metodo = in_array($metodo, ['PUBLISH', 'REQUEST', 'CANCEL'], true) ? $metodo : 'PUBLISH';
    $host = (string)(parse_url(cms_url_sito(), PHP_URL_HOST) ?: 'localhost');
    $z = fn(int $ts) => gmdate('Ymd\THis\Z', $ts);
    $sito = (string)cms_impostazione('sito_nome', CMS_NOME);
    $annullata = $metodo === 'CANCEL' || !in_array($p['stato'], PREN_STATI_ATTIVI, true);
    $righe = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//plumeCMS//Prenotazioni//IT', 'CALSCALE:GREGORIAN', 'METHOD:' . $metodo, 'BEGIN:VEVENT',
        'UID:prenotazione-' . substr((string)$p['codice'], 0, 16) . '@' . $host, 'SEQUENCE:' . max(0, (int)($p['sequenza'] ?? 0)), 'DTSTAMP:' . $z(time()), 'DTSTART:' . $z((int)$p['inizio_ts']), 'DTEND:' . $z((int)$p['fine_ts']),
        'SUMMARY:' . pren_ics_testo($p['servizio'] . ' - ' . $sito),
        'DESCRIPTION:' . pren_ics_testo($annullata ? 'Appuntamento annullato.' : 'Per annullare o spostare: ' . pren_url('gestisci', $p)),
        'STATUS:' . ($annullata ? 'CANCELLED' : 'CONFIRMED')];
    if ($metodo !== 'PUBLISH') {
        $org = pren_indirizzo_attivita();
        if ($org === '') $org = (string)(posta_mittente()[0] ?? '');
        if (filter_var($org, FILTER_VALIDATE_EMAIL)) $righe[] = 'ORGANIZER;CN="' . str_replace(['"', '\\', ';', ':', ','], '', pren_riga($sito)) . '":mailto:' . $org;
        if (filter_var($p['email'], FILTER_VALIDATE_EMAIL)) $righe[] = 'ATTENDEE;CN="' . str_replace(['"', '\\', ';', ':', ','], '', pren_riga((string)$p['nome'])) . '";ROLE=REQ-PARTICIPANT;PARTSTAT=' . ($annullata ? 'DECLINED' : 'ACCEPTED') . ';RSVP=FALSE:mailto:' . $p['email'];
    }
    $ind = trim((string)pren_impostazioni()['indirizzo']);
    if ($ind !== '') $righe[] = 'LOCATION:' . pren_ics_testo($ind);
    $righe[] = 'END:VEVENT';
    $righe[] = 'END:VCALENDAR';
    return implode("\r\n", array_map('pren_ics_piega', $righe)) . "\r\n";
}

// ------------------------------------------------------------
// Cron frequente: promemoria e appuntamenti svolti
// ------------------------------------------------------------

/**
 * Il giro frequente (cron_spesso). Promemoria: alle prenotazioni confermate che cominciano entro 24 ore, fatte più di 24
 * ore prima, una volta sola (la riga si prende con UPDATE ... WHERE promemoria_at IS NULL, prima di spedire). Svolte: le
 * confermate finite diventano "svolta" e partono l'evento prenotazione_svolta (lo ascolta l'addon Recensioni, se acceso).
 */
function pren_cron(?int $adesso = null): string {
    pren_schema();
    $adesso ??= time();
    $imp = pren_impostazioni();
    $mandati = 0; $svolte = 0; $falliti = 0;
    if ($imp['promemoria']) {
        $st = db()->prepare('SELECT * FROM ' . tbl('pren_prenotazioni') . " WHERE stato = 'confermata' AND promemoria_at IS NULL AND inizio_ts > ? AND inizio_ts <= ? ORDER BY inizio_ts LIMIT 50");
        $st->execute([$adesso, $adesso + 86400]);
        foreach ($st->fetchAll() as $p) {
            if ((int)$p['inizio_ts'] - strtotime((string)$p['creato']) <= 86400) continue; // prenotata da poco: l'email di conferma basta
            $presa = db()->prepare('UPDATE ' . tbl('pren_prenotazioni') . ' SET promemoria_at = ? WHERE id = ? AND promemoria_at IS NULL');
            $presa->execute([date('Y-m-d H:i:s', $adesso), (int)$p['id']]);
            if ($presa->rowCount() !== 1) continue;
            if (pren_email_cliente($p, 'promemoria')) { $mandati++; continue; }
            // Non è partito (server di posta giù): la riga torna libera e si riprova al giro dopo.
            db()->prepare('UPDATE ' . tbl('pren_prenotazioni') . ' SET promemoria_at = NULL WHERE id = ?')->execute([(int)$p['id']]);
            $falliti++;
        }
    }
    $st = db()->prepare('SELECT * FROM ' . tbl('pren_prenotazioni') . " WHERE stato = 'confermata' AND fine_ts <= ? ORDER BY fine_ts LIMIT 200");
    $st->execute([$adesso]);
    foreach ($st->fetchAll() as $p) {
        $u = db()->prepare('UPDATE ' . tbl('pren_prenotazioni') . " SET stato = 'svolta', aggiornato = ? WHERE id = ? AND stato = 'confermata'");
        $u->execute([date('Y-m-d H:i:s', $adesso), (int)$p['id']]);
        if ($u->rowCount() !== 1) continue;
        $svolte++;
        $p['stato'] = 'svolta';
        try { cms_evento('prenotazione_svolta', $p); } catch (Throwable $e) { error_log('prenotazioni: evento svolta: ' . $e->getMessage()); }
    }
    return ($mandati || $svolte || $falliti) ? "$mandati promemoria, $svolte appuntamenti svolti" . ($falliti ? ", $falliti promemoria non partiti (si riprova)" : '') : '';
}
