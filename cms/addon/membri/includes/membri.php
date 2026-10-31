<?php
/**
 * Area riservata (addon membri, 2.0.0): il motore. Account dei visitatori del sito, separati dagli utenti del pannello.
 *
 * Tabelle (prefisso del sito):
 *   membri            id, email, nome, password_hash, stato (da_confermare, in_attesa, invitato, attivo, sospeso), date, epoca
 *   membri_sessioni   le sessioni aperte: hash del token del cookie, membro, scadenza (il token vero sta solo nel cookie)
 *   membri_token      link monouso per email: conferma, password, invito, email (hash del token, scadenza, dato)
 *   membri_riservati  i contenuti "solo per i membri": fonte (articles, pages) e id della riga
 *
 * La sessione dei membri non è quella del pannello: un cookie suo (membri_<impronta>), HttpOnly, SameSite=Lax, Secure su
 * https, con un token a caso di 256 bit di cui il database tiene solo l'hash. Niente $_SESSION: chi è entrato nel pannello
 * non è un membro, e un membro non entra nel pannello.
 * CSRF: ogni POST pubblico porta _mt, l'HMAC (segreto del sito) di un cookie a caso del browser (membri_csrf_<impronta>),
 * più il controllo di Origin e Referer. Tentativi: nella tabella sicurezza_tentativi del nucleo, con chiavi "membri-...".
 */

require_once dirname(__DIR__, 3) . '/includes/cms.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';
require_once dirname(__DIR__, 3) . '/includes/sicurezza.php';
require_once dirname(__DIR__, 3) . '/includes/moduli.php';

const MEMBRI_STATI = ['da_confermare' => 'Da confermare', 'in_attesa' => 'In attesa di approvazione', 'invitato' => 'Invitato', 'attivo' => 'Attivo', 'sospeso' => 'Sospeso'];
const MEMBRI_MODI = ['aperta' => 'Aperta a tutti', 'approvazione' => 'Aperta, ma approvo io ogni iscrizione', 'invito' => 'Solo su invito'];
const MEMBRI_ORE_CONFERMA = 48;       // validità del link di conferma dell'iscrizione
const MEMBRI_MINUTI_PASSWORD = 60;    // validità del link per una password nuova
const MEMBRI_GIORNI_INVITO = 7;       // validità del link di un invito
const MEMBRI_GIORNI_RICORDA = 30;     // "Resta collegato"
const MEMBRI_ORE_SESSIONE = 12;       // senza "Resta collegato"
const MEMBRI_TENTATIVI_IP = 10;       // accessi sbagliati da un indirizzo prima del blocco
const MEMBRI_TENTATIVI_EMAIL = 10;    // accessi sbagliati su un'email (da qualunque indirizzo) prima del blocco
const MEMBRI_BLOCCO_MINUTI = 15;      // il primo blocco; raddoppia a ogni tentativo in più, fino a un giorno

// ------------------------------------------------------------
// Tabelle
// ------------------------------------------------------------

function membri_installa(): string {
    $t = [
        'CREATE TABLE IF NOT EXISTS ' . tbl('membri') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(190) NOT NULL,
            `nome` VARCHAR(120) NOT NULL DEFAULT '',
            `password_hash` VARCHAR(255) NOT NULL DEFAULT '',
            `stato` VARCHAR(20) NOT NULL DEFAULT 'da_confermare',
            `creato` DATETIME NOT NULL,
            `confermato` DATETIME DEFAULT NULL,
            `ultimo_accesso` DATETIME DEFAULT NULL,
            `epoca` INT NOT NULL DEFAULT 0,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`), UNIQUE KEY `email` (`email`), KEY `stato` (`stato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('membri_sessioni') . " (
            `token` CHAR(64) NOT NULL,
            `membro_id` INT NOT NULL,
            `creata` DATETIME NOT NULL,
            `ultimo` DATETIME NOT NULL,
            `scade` DATETIME NOT NULL,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`token`), KEY `membro` (`membro_id`), KEY `scade` (`scade`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('membri_token') . " (
            `token` CHAR(64) NOT NULL,
            `scopo` VARCHAR(20) NOT NULL,
            `membro_id` INT NOT NULL,
            `dato` VARCHAR(190) NOT NULL DEFAULT '',
            `scade` DATETIME NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`token`), KEY `membro` (`membro_id`), KEY `scade` (`scade`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('membri_riservati') . " (
            `fonte` VARCHAR(20) NOT NULL,
            `contenuto_id` INT NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`fonte`, `contenuto_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($t as $sql) db()->exec($sql);
    save_setting('membri_schema', md5(implode('', $t)));
    return 'tabelle dei membri a posto';
}

/** Le tabelle ci sono? Una volta per richiesta (l'addon acceso le ha già create; dopo un ripristino si rifanno). */
function membri_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    if ((string)get_setting('membri_schema', '') === '') membri_installa();
}

// ------------------------------------------------------------
// Impostazioni
// ------------------------------------------------------------

function membri_cfg(): array {
    $c = json_decode((string)get_setting('membri_cfg', ''), true);
    $c = is_array($c) ? $c : [];
    return [
        'modo' => isset(MEMBRI_MODI[$c['modo'] ?? '']) ? $c['modo'] : 'aperta',
        'avviso' => trim((string)($c['avviso'] ?? '')) ?: 'Questo contenuto è riservato ai membri del sito. Accedi, oppure iscriviti: è gratis.',
        'avvisa_admin' => !empty($c['avvisa_admin'] ?? true),
        'file' => array_values(array_filter((array)($c['file'] ?? []), fn($f) => is_string($f) && preg_match('/^[a-z0-9][a-z0-9._-]{0,120}$/', $f))),
        'benvenuto' => trim((string)($c['benvenuto'] ?? '')),
    ];
}

function membri_cfg_salva(array $nuovo): void {
    $c = array_merge(membri_cfg(), $nuovo);
    $c['avviso'] = membri_testo((string)$c['avviso'], 500);
    $c['benvenuto'] = membri_testo((string)$c['benvenuto'], 1000);
    save_setting('membri_cfg', json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function membri_testo_avviso(): string {
    return membri_cfg()['avviso'];
}

/** Un testo scritto da una persona: senza a capo strani, senza caratteri a 4 byte (le emoji), lungo al massimo $max. */
function membri_testo(string $s, int $max = 120): string {
    $s = str_replace(["\0", "\r"], '', $s);
    $s = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
    return mb_substr(trim($s), 0, $max);
}

// ------------------------------------------------------------
// Membri
// ------------------------------------------------------------

function membri_per_email(string $email): ?array {
    membri_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('membri') . ' WHERE email = ?');
    $st->execute([mb_strtolower(trim($email))]);
    return $st->fetch() ?: null;
}

function membri_leggi(int $id): ?array {
    membri_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('membri') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function membri_conta(string $stato = ''): int {
    membri_schema();
    if ($stato === '') return (int)db()->query('SELECT COUNT(*) FROM ' . tbl('membri'))->fetchColumn();
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('membri') . ' WHERE stato = ?');
    $st->execute([$stato]);
    return (int)$st->fetchColumn();
}

/** L'elenco per il pannello: filtro per stato e ricerca su nome ed email. */
function membri_elenco(string $stato = '', string $cerca = '', int $limite = 500): array {
    membri_schema();
    $w = []; $p = [];
    if (isset(MEMBRI_STATI[$stato])) { $w[] = 'stato = ?'; $p[] = $stato; }
    if ($cerca !== '') { $w[] = '(email LIKE ? OR nome LIKE ?)'; $p[] = '%' . sicurezza_like($cerca) . '%'; $p[] = '%' . sicurezza_like($cerca) . '%'; }
    $st = db()->prepare('SELECT * FROM ' . tbl('membri') . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY id DESC LIMIT ' . max(1, min(5000, $limite)));
    $st->execute($p);
    return $st->fetchAll();
}

function membri_email_valida(string $email): bool {
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && !preg_match('/[\r\n]/', $email);
}

/** Crea un membro. Ritorna l'id. L'email deve essere nuova (il chiamante lo controlla). */
function membri_crea(string $email, string $nome, string $password, string $stato): int {
    membri_schema();
    $hash = $password !== '' ? sicurezza_password_hash($password) : '';
    db()->prepare('INSERT INTO ' . tbl('membri') . ' (email, nome, password_hash, stato, creato, ip) VALUES (?, ?, ?, ?, NOW(), ?)')
        ->execute([mb_strtolower(trim($email)), membri_testo($nome), $hash, $stato, substr(sicurezza_ip(), 0, 45)]);
    return (int)db()->lastInsertId();
}

function membri_imposta_stato(int $id, string $stato): void {
    if (!isset(MEMBRI_STATI[$stato])) throw new InvalidArgumentException('Stato non valido.');
    db()->prepare('UPDATE ' . tbl('membri') . ' SET stato = ?, confermato = IF(? = \'attivo\' AND confermato IS NULL, NOW(), confermato) WHERE id = ?')->execute([$stato, $stato, $id]);
    // Un membro sospeso esce subito da ogni dispositivo.
    if ($stato !== 'attivo') membri_sessioni_chiudi($id);
}

/** Cancella un membro e tutto quello che è suo (sessioni, link). Gli altri addon (corsi) ascoltano membro_eliminato. */
function membri_elimina(int $id): void {
    membri_schema();
    foreach (['membri_sessioni', 'membri_token'] as $t) db()->prepare('DELETE FROM ' . tbl($t) . ' WHERE membro_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('membri') . ' WHERE id = ?')->execute([$id]);
    try { cms_evento('membro_eliminato', $id); } catch (Throwable $e) { error_log('membri: membro_eliminato: ' . $e->getMessage()); }
}

function membri_password_imposta(int $id, string $pw): void {
    db()->prepare('UPDATE ' . tbl('membri') . ' SET password_hash = ?, epoca = epoca + 1 WHERE id = ?')->execute([sicurezza_password_hash($pw), $id]);
}

/** La password va bene? Le stesse regole del pannello (12 caratteri, niente password comuni, niente email dentro). */
function membri_password_errore(string $pw, string $pw2, string $email): string {
    if ($pw !== $pw2) return 'Le due password non sono uguali.';
    return sicurezza_password_errore($pw, '', $email);
}

// ------------------------------------------------------------
// Link monouso (conferma, password, invito, cambio email)
// ------------------------------------------------------------

function membri_token_crea(string $scopo, int $membro, int $minuti, string $dato = ''): string {
    membri_schema();
    $t = bin2hex(random_bytes(32));
    db()->exec('DELETE FROM ' . tbl('membri_token') . ' WHERE scade < NOW()');
    db()->prepare('DELETE FROM ' . tbl('membri_token') . ' WHERE scopo = ? AND membro_id = ?')->execute([$scopo, $membro]);
    db()->prepare('INSERT INTO ' . tbl('membri_token') . ' (token, scopo, membro_id, dato, scade, creato) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())')
        ->execute([hash('sha256', $t), $scopo, $membro, $dato, $minuti]);
    return $t;
}

/** Il token: ['membro_id', 'dato'] se valido e non scaduto, altrimenti null. Con $consuma muore. */
function membri_token_leggi(string $scopo, string $token, bool $consuma = false): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    membri_schema();
    $h = hash('sha256', $token);
    $st = db()->prepare('SELECT membro_id, dato FROM ' . tbl('membri_token') . ' WHERE token = ? AND scopo = ? AND scade > NOW()');
    $st->execute([$h, $scopo]);
    $r = $st->fetch();
    if (!$r) return null;
    if ($consuma) db()->prepare('DELETE FROM ' . tbl('membri_token') . ' WHERE token = ?')->execute([$h]);
    return ['membro_id' => (int)$r['membro_id'], 'dato' => (string)$r['dato']];
}

// ------------------------------------------------------------
// Cookie, sessione, CSRF
// ------------------------------------------------------------

/** Un pezzo del nome dei cookie, diverso per ogni installazione sullo stesso dominio (prefisso e cartella). */
function membri_impronta(): string {
    return substr(hash('sha256', DB_PREFIX . '|' . cms_sito_base()), 0, 8);
}

function membri_cookie_nome(string $quale = 'sessione'): string {
    return ($quale === 'csrf' ? 'membri_csrf_' : 'membri_') . membri_impronta();
}

function membri_cookie(string $nome, string $valore, int $scade): void {
    if (headers_sent() || PHP_SAPI === 'cli') return;
    setcookie($nome, $valore, ['expires' => $scade, 'path' => cms_sito_base() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => sicurezza_https()]);
}

/** Il token CSRF per i form pubblici: l'HMAC di un cookie a caso del browser (lo crea se manca). */
function membri_csrf(): string {
    $n = membri_cookie_nome('csrf');
    $v = (string)($_COOKIE[$n] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $v)) {
        $v = bin2hex(random_bytes(16));
        membri_cookie($n, $v, 0);
        $_COOKIE[$n] = $v;
    }
    return substr(hash_hmac('sha256', 'membri-csrf|' . $v, moduli_segreto()), 0, 40);
}

function membri_csrf_campo(): string {
    return '<input type="hidden" name="_mt" value="' . membri_csrf() . '">';
}

/** Il POST viene da un form di questo sito, aperto in questo browser? */
function membri_csrf_ok(): bool {
    $v = (string)($_COOKIE[membri_cookie_nome('csrf')] ?? '');
    $t = (string)($_POST['_mt'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $v) || !is_string($_POST['_mt'] ?? null) || strlen($t) !== 40) return false;
    return hash_equals(substr(hash_hmac('sha256', 'membri-csrf|' . $v, moduli_segreto()), 0, 40), $t) && sicurezza_origine_ok();
}

/** Il membro entrato in questo browser, oppure null. Solo membri attivi con una sessione non scaduta. */
function membri_corrente(): ?array {
    static $m = false;
    if ($m !== false) return $m;
    $m = null;
    $t = (string)($_COOKIE[membri_cookie_nome()] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $t)) return null;
    try {
        membri_schema();
        $st = db()->prepare('SELECT m.*, s.ultimo AS s_ultimo, s.token AS s_token FROM ' . tbl('membri_sessioni') . ' s JOIN ' . tbl('membri') . ' m ON m.id = s.membro_id
            WHERE s.token = ? AND s.scade > NOW() AND m.stato = \'attivo\'');
        $st->execute([hash('sha256', $t)]);
        $r = $st->fetch();
    } catch (Throwable $e) { error_log('membri: sessione: ' . $e->getMessage()); return null; }
    if (!$r) return null;
    if (strtotime((string)$r['s_ultimo']) < time() - 60) db()->prepare('UPDATE ' . tbl('membri_sessioni') . ' SET ultimo = NOW() WHERE token = ?')->execute([$r['s_token']]);
    unset($r['password_hash']);
    return $m = $r;
}

/** Apre la sessione di un membro (dopo la password giusta): token nuovo, cookie, riga nel database. */
function membri_entra(array $membro, bool $ricorda): void {
    $t = bin2hex(random_bytes(32));
    $secondi = $ricorda ? MEMBRI_GIORNI_RICORDA * 86400 : MEMBRI_ORE_SESSIONE * 3600;
    db()->prepare('INSERT INTO ' . tbl('membri_sessioni') . ' (token, membro_id, creata, ultimo, scade, ip, user_agent) VALUES (?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?)')
        ->execute([hash('sha256', $t), (int)$membro['id'], $secondi, substr(sicurezza_ip(), 0, 45), sicurezza_user_agent()]);
    db()->prepare('UPDATE ' . tbl('membri') . ' SET ultimo_accesso = NOW() WHERE id = ?')->execute([(int)$membro['id']]);
    membri_cookie(membri_cookie_nome(), $t, $ricorda ? time() + $secondi : 0);
    $_COOKIE[membri_cookie_nome()] = $t;
    // Un cookie CSRF nuovo dopo l'accesso: quello di prima (che poteva conoscere chi ha preparato la pagina) non vale più.
    membri_cookie(membri_cookie_nome('csrf'), '', time() - 3600);
    unset($_COOKIE[membri_cookie_nome('csrf')]);
}

/** Esce da questo browser. */
function membri_esci(): void {
    $t = (string)($_COOKIE[membri_cookie_nome()] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $t)) {
        try { db()->prepare('DELETE FROM ' . tbl('membri_sessioni') . ' WHERE token = ?')->execute([hash('sha256', $t)]); } catch (Throwable $e) {}
    }
    membri_cookie(membri_cookie_nome(), '', time() - 3600);
    unset($_COOKIE[membri_cookie_nome()]);
}

/** Chiude le sessioni di un membro, tranne (a scelta) quella di questo browser. */
function membri_sessioni_chiudi(int $id, bool $tieniQuesta = false): void {
    membri_schema();
    $t = (string)($_COOKIE[membri_cookie_nome()] ?? '');
    if ($tieniQuesta && preg_match('/^[a-f0-9]{64}$/', $t)) {
        db()->prepare('DELETE FROM ' . tbl('membri_sessioni') . ' WHERE membro_id = ? AND token <> ?')->execute([$id, hash('sha256', $t)]);
    } else {
        db()->prepare('DELETE FROM ' . tbl('membri_sessioni') . ' WHERE membro_id = ?')->execute([$id]);
    }
}

// ------------------------------------------------------------
// Limite ai tentativi (tabella sicurezza_tentativi del nucleo, chiavi a parte da quelle del pannello)
// ------------------------------------------------------------

function membri_chiavi_tentativi(string $email): array {
    return ['membri-ip:' . sicurezza_ip() => MEMBRI_TENTATIVI_IP, 'membri-email:' . hash('sha256', mb_strtolower(trim($email))) => MEMBRI_TENTATIVI_EMAIL];
}

/** Secondi che mancano alla fine del blocco (per indirizzo o per email), 0 se si può provare. */
function membri_bloccato(string $email): int {
    sicurezza_schema();
    $st = db()->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), bloccato_fino) FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ? AND bloccato_fino > NOW()');
    $max = 0;
    foreach (array_keys(membri_chiavi_tentativi($email)) as $k) { $st->execute([$k]); $max = max($max, (int)$st->fetchColumn()); }
    return $max;
}

function membri_tentativo_fallito(string $email): void {
    sicurezza_schema();
    foreach (membri_chiavi_tentativi($email) as $k => $soglia) {
        // Passati 15 minuti senza tentativi (e senza blocco in corso) il conteggio riparte da uno.
        db()->prepare('INSERT INTO ' . tbl('sicurezza_tentativi') . ' (chiave, falliti, ultimo) VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE falliti = IF(ultimo < DATE_SUB(NOW(), INTERVAL ? MINUTE) AND (bloccato_fino IS NULL OR bloccato_fino < NOW()), 1, falliti + 1), ultimo = NOW()')
            ->execute([$k, MEMBRI_BLOCCO_MINUTI]);
        $st = db()->prepare('SELECT falliti FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ?'); $st->execute([$k]);
        $n = (int)$st->fetchColumn();
        if ($n >= $soglia) {
            $minuti = min(1440, MEMBRI_BLOCCO_MINUTI * (2 ** min(7, $n - $soglia)));
            db()->prepare('UPDATE ' . tbl('sicurezza_tentativi') . ' SET bloccato_fino = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE chiave = ?')->execute([$minuti, $k]);
            if ($n === $soglia) sicurezza_log('blocco', 'membro', 0, "$k bloccato per $minuti minuti dopo $n accessi sbagliati all'area riservata");
        }
    }
}

function membri_tentativo_riuscito(string $email): void {
    $st = db()->prepare('DELETE FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ?');
    foreach (array_keys(membri_chiavi_tentativi($email)) as $k) $st->execute([$k]);
}

/**
 * Controlla email e password. Ritorna ['membro' => riga] se va bene, altrimenti ['errore' => testo].
 * Lo stesso messaggio per email che non c'è e password sbagliata, e lo stesso tempo (hash finto): niente enumerazione.
 */
function membri_verifica_accesso(string $email, string $pw): array {
    $email = mb_strtolower(trim($email));
    if (($att = membri_bloccato($email)) > 0) return ['errore' => 'Troppi tentativi sbagliati. Riprova fra ' . max(1, (int)ceil($att / 60)) . ' minuti, oppure usa "Password dimenticata".'];
    $m = membri_email_valida($email) ? membri_per_email($email) : null;
    $ok = password_verify($pw, $m && $m['password_hash'] !== '' ? (string)$m['password_hash'] : sicurezza_hash_finto());
    if (!$m || !$ok || $m['password_hash'] === '') {
        membri_tentativo_fallito($email);
        return ['errore' => 'Email o password non corrette.'];
    }
    membri_tentativo_riuscito($email);
    // Chi ha la password giusta può sapere a che punto è il suo account.
    return match ($m['stato']) {
        'attivo' => ['membro' => $m],
        'da_confermare' => ['errore' => 'Prima conferma la tua email: apri il messaggio che ti abbiamo mandato e premi il pulsante. Se non lo trovi, iscriviti di nuovo con la stessa email: te lo rimandiamo.'],
        'in_attesa' => ['errore' => 'La tua iscrizione è in attesa di approvazione. Riceverai un\'email quando sarà attiva.'],
        default => ['errore' => 'Questo account è sospeso. Per informazioni scrivi al sito.'],
    };
}

// ------------------------------------------------------------
// Email
// ------------------------------------------------------------

/** Manda un'email del sito a un membro (posta_invia del nucleo, impaginata come le altre email del sito). */
function membri_manda(string $a, string $oggetto, string $titolo, string $corpoHtml, string $pulsante = '', string $link = ''): bool {
    try {
        return posta_invia($a, $oggetto, moduli_email_html($titolo, $corpoHtml, $pulsante, $link), moduli_intestazioni(), 'membri');
    } catch (Throwable $e) {
        error_log('membri: email non partita: ' . $e->getMessage());
        return false;
    }
}

function membri_url(string $percorso): string {
    return rtrim(cms_url_sito(), '/') . $percorso;
}

/** Una pagina del pannello dell'addon: addon-pagina.php?a=membri&p=<pagina>. */
function membri_admin_url(string $p, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'membri', 'p' => $p] + $q);
}

function membri_nome_sito(): string {
    return (string)cms_impostazione('sito_nome', CMS_NOME);
}

// ------------------------------------------------------------
// Contenuti e file riservati
// ------------------------------------------------------------

/** I contenuti segnati "solo per i membri": ['articles:12' => true, ...]. */
function membri_riservati(): array {
    static $r = null;
    if ($r !== null) return $r;
    $r = [];
    try {
        membri_schema();
        foreach (db()->query('SELECT fonte, contenuto_id FROM ' . tbl('membri_riservati'))->fetchAll() as $x) $r[$x['fonte'] . ':' . (int)$x['contenuto_id']] = true;
    } catch (Throwable $e) { error_log('membri: riservati: ' . $e->getMessage()); }
    return $r;
}

/** Il contenuto del sito ($c di sito_contenuti) è solo per i membri? */
function membri_contenuto_riservato(array $c): bool {
    $fonte = (string)($c['fonte'] ?? '');
    $id = (int)($c['riga']['id'] ?? 0);
    return in_array($fonte, ['articles', 'pages'], true) && $id > 0 && isset(membri_riservati()[$fonte . ':' . $id]);
}

/** Solo per i membri e chi guarda non è un membro entrato (né l'amministratore in anteprima)? */
function membri_contenuto_chiuso(array $c): bool {
    if (!membri_contenuto_riservato($c)) return false;
    if (defined('SITO_ANTEPRIMA') && SITO_ANTEPRIMA) return false;
    return membri_corrente() === null;
}

function membri_riservato_imposta(string $fonte, int $id, bool $si): void {
    if (!in_array($fonte, ['articles', 'pages'], true) || $id <= 0) return;
    membri_schema();
    if ($si) db()->prepare('INSERT IGNORE INTO ' . tbl('membri_riservati') . ' (fonte, contenuto_id, creato) VALUES (?, ?, NOW())')->execute([$fonte, $id]);
    else db()->prepare('DELETE FROM ' . tbl('membri_riservati') . ' WHERE fonte = ? AND contenuto_id = ?')->execute([$fonte, $id]);
}

/**
 * 2.1, filtro contenuti_elenco del nucleo (sito_contenuti_elenco(), includes/sito-dati.php): i contenuti "solo per i membri"
 * non escono negli elenchi pubblici, con titolo e riassunto: sitemap (e IndexNow), feed, llms.txt e llms-full.txt sempre;
 * la ricerca del sito ($dove 'cerca') li mostra solo a un membro collegato, e allora la pagina dei risultati non va in cache.
 */
function membri_elenco_filtra(array $tutti, string $dove): array {
    if (!membri_riservati()) return $tutti;
    if ($dove === 'cerca' && membri_corrente() !== null) {
        if (!headers_sent()) header('Cache-Control: private, no-store');
        return $tutti;
    }
    return array_values(array_filter($tutti, fn($c) => !membri_contenuto_riservato($c)));
}

/** La fonte (tabella) dei tipi della scheda del pannello: articoli e pagine. Null per gli altri tipi. */
function membri_fonte_tipo(string $tipo): ?string {
    return ['articoli' => 'articles', 'pagine' => 'pages'][$tipo] ?? null;
}

/** La pagina è la home? (resta sempre di tutti) */
function membri_e_home(string $tipo, ?array $r): bool {
    return $tipo === 'pagine' && $r && (string)($r['slug'] ?? '') === (string)get_setting('pagina_home', '');
}

/**
 * 2.1, filtro scheda_riquadri del nucleo (admin/contenuto.php): il riquadro "Area riservata" nella scheda di un articolo o
 * di una pagina, solo per l'amministratore (le pagine Membri sono sue). Il campo nascosto membri_scheda dice all'evento
 * scheda_salvata che il riquadro c'era: senza, la spunta tolta non si legge come "pubblico".
 */
function membri_scheda_riquadro(string $tipo, int $id, ?array $r): string {
    $fonte = membri_fonte_tipo($tipo);
    if (!$fonte || $id <= 0 || !function_exists('is_admin') || !is_admin()) return '';
    $home = membri_e_home($tipo, $r);
    $si = !$home && isset(membri_riservati()[$fonte . ':' . $id]);
    $uno = $tipo === 'pagine' ? 'la pagina' : 'l\'articolo';
    return '<div class="sc-scatola" id="membri-scheda"><h3>Area riservata</h3><div class="sc-corpo">'
        . '<input type="hidden" name="membri_scheda" value="1">'
        . '<label style="display:flex;gap:.5rem;align-items:flex-start;font-size:.9rem;font-weight:600;color:var(--text);margin:0">'
        . '<input type="checkbox" name="membri_riservato" value="1" id="membri-riservato"' . ($si ? ' checked' : '') . ($home ? ' disabled' : '') . ' style="margin-top:.2rem"> <span>Solo per i membri</span></label>'
        . '<div class="hint">' . ($home ? 'La home resta di tutti.' : 'Con la spunta, ' . $uno . ' lo vede intero solo chi è entrato nell\'area riservata: gli altri trovano l\'invito ad accedere. Non esce nella ricerca del sito (tranne che ai membri), nella sitemap, nel feed e in llms.txt.')
        . ' Tutti i contenuti riservati: <a href="' . h(membri_admin_url('riservati')) . '">Membri, Contenuti e file riservati</a>.</div>'
        . '</div></div>';
}

/** 2.1, evento scheda_salvata del nucleo: la spunta "Solo per i membri" della scheda. Solo l'amministratore, mai la home. */
function membri_scheda_salva(string $tipo, int $id, array $d): void {
    $fonte = membri_fonte_tipo($tipo);
    if (!$fonte || $id <= 0 || ($d['membri_scheda'] ?? '') !== '1' || !function_exists('is_admin') || !is_admin()) return;
    $r = db()->prepare('SELECT slug FROM ' . tbl($fonte) . ' WHERE id = ?');
    $r->execute([$id]);
    $slug = $r->fetchColumn();
    if ($slug === false) return;
    $si = !empty($d['membri_riservato']) && !membri_e_home($tipo, ['slug' => (string)$slug]);
    membri_riservato_imposta($fonte, $id, $si);
}

/** I file riservati di Download che i membri vedono nell'area riservata (scelti in Contenuti e file riservati). */
function membri_file(): array {
    require_once dirname(__DIR__, 3) . '/includes/scaricabili.php';
    $out = [];
    $tutti = scaricabili_elenco();
    foreach (membri_cfg()['file'] as $nome) {
        $x = $tutti[$nome] ?? null;
        if ($x && $x['riservato']) $out[$nome] = $x;
    }
    return $out;
}

// ------------------------------------------------------------
// Cron
// ------------------------------------------------------------

/** Ogni notte: via le sessioni e i link scaduti, e le iscrizioni mai confermate dopo sette giorni. */
function membri_pulizia(): string {
    membri_schema();
    $s = db()->exec('DELETE FROM ' . tbl('membri_sessioni') . ' WHERE scade < NOW()');
    $t = db()->exec('DELETE FROM ' . tbl('membri_token') . ' WHERE scade < NOW()');
    $vecchi = db()->query('SELECT id FROM ' . tbl('membri') . " WHERE stato = 'da_confermare' AND creato < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($vecchi as $id) membri_elimina((int)$id);
    return "$s sessioni scadute, $t link scaduti, " . count($vecchi) . ' iscrizioni mai confermate tolte';
}
