<?php
/**
 * Sicurezza del CMS: sessione, accessi, limite ai tentativi, secondo fattore (TOTP), password dimenticata,
 * IP consentiti, intestazioni HTTP con Content-Security-Policy a nonce, registro degli accessi, firma dei pacchetti.
 *
 * Tutto fatto in casa, senza librerie. Le impostazioni stanno nella tabella settings (chiave sicurezza_cfg) e si
 * governano dalla pagina Sicurezza del pannello (admin/sicurezza.php). Il 2FA e la lista degli IP nascono spenti.
 *
 * Se ci si chiude fuori (2FA perso, IP cambiato): in includes/config.local.php si aggiunge la riga
 *     define('CMS_SICUREZZA_SPEGNI', true);
 * e il pannello torna a chiedere solo nome e password da qualunque indirizzo. Poi si toglie la riga.
 *
 * I sanificatori (HTML, CSS, SVG, URL, chiamate in uscita) stanno in includes/sicurezza-html.php.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sicurezza-html.php';
require_once __DIR__ . '/sicurezza-password.php';

// ------------------------------------------------------------
// Impostazioni e schema
// ------------------------------------------------------------

/** I valori di partenza: prudenti ma che non chiudono nessuno fuori. */
function sicurezza_predefiniti(): array {
    return [
        'tentativi_max' => 5,          // tentativi falliti prima del blocco (per IP e per utente)
        'blocco_minuti' => 10,         // durata del primo blocco; poi raddoppia a ogni tentativo in piu
        'sessione_ore' => 12,          // scadenza assoluta della sessione del pannello
        'inattivita_minuti' => 120,    // scadenza per inattivita
        'totp_admin' => 0,             // 2FA obbligatorio per gli amministratori
        'ip_attivo' => 0,              // lista IP consentiti attiva
        'ip_elenco' => '',             // un IP o una rete (CIDR) per riga
        'csp_sito' => 'applica',       // spenta | rapporto | applica (dalla 1.1.2 applicata: verificata a zero violazioni)
        'csp_pannello' => 'applica',   // spenta | rapporto | applica
        'csp_extra' => '',             // sorgenti in piu, una per riga: "script-src https://esempio.it"
        'hsts' => 0,                   // Strict-Transport-Security (solo su https)
        'html_widget' => 'admin',      // chi usa il widget HTML e il testo grezzo: admin | nessuno
        'codice_libero' => 'admin',    // chi usa il codice head/body del pannello SEO: admin | nessuno
        'avvisi_email' => 1,           // email all'amministratore su blocchi e accessi da IP nuovi
        'firma_pacchetti' => 0,        // i pacchetti del pannello Aggiornamenti devono essere firmati
        'uscita_host' => '',           // host in piu ammessi per le chiamate in uscita, uno per riga
        'root_php_fino' => 0,          // fino a quando (timestamp) un pacchetto non firmato puo mettere file .php nella radice
    ];
}

function sicurezza_cfg(bool $azzera = false): array {
    static $c = null;
    if ($c === null || $azzera) {
        $s = json_decode((string)get_setting('sicurezza_cfg', ''), true);
        // Un modulo puo cambiare i valori di partenza (per esempio la CSP di un sito che ha ancora script vecchi).
        $pred = sicurezza_predefiniti();
        if (function_exists('cms_filtro')) $pred = array_intersect_key((array)cms_filtro('sicurezza_predefiniti', $pred), $pred) + $pred;
        $c = (is_array($s) ? array_intersect_key($s, $pred) : []) + $pred;
    }
    return $c;
}

function sicurezza_salva_cfg(array $v): void {
    $p = sicurezza_predefiniti();
    $out = sicurezza_cfg();
    foreach ($p as $k => $pred) {
        if (!array_key_exists($k, $v)) continue;
        $x = $v[$k];
        if (is_int($pred)) $x = (int)$x;
        else $x = str_replace("\r", '', (string)$x);
        $out[$k] = $x;
    }
    $out['root_php_fino'] = max(0, min(time() + 3600, (int)$out['root_php_fino']));
    $out['tentativi_max'] = max(3, min(20, $out['tentativi_max']));
    $out['blocco_minuti'] = max(1, min(1440, $out['blocco_minuti']));
    $out['sessione_ore'] = max(1, min(720, $out['sessione_ore']));
    $out['inattivita_minuti'] = max(5, min(1440, $out['inattivita_minuti']));
    foreach (['csp_sito', 'csp_pannello'] as $k) if (!in_array($out[$k], ['spenta', 'rapporto', 'applica'], true)) $out[$k] = 'rapporto';
    foreach (['html_widget', 'codice_libero'] as $k) if (!in_array($out[$k], ['admin', 'nessuno'], true)) $out[$k] = 'admin';
    save_setting('sicurezza_cfg', json_encode($out, JSON_UNESCAPED_UNICODE));
    sicurezza_cfg(true);
}

/** Spegnimento di emergenza da config.local.php: niente 2FA e niente lista IP. */
function sicurezza_spento(): bool {
    return defined('CMS_SICUREZZA_SPEGNI') && CMS_SICUREZZA_SPEGNI;
}

/** Tabelle e colonne della sicurezza: si creano una volta, con un'impronta nelle impostazioni. */
function sicurezza_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $tabelle = [
        'sicurezza_log' => 'CREATE TABLE IF NOT EXISTS ' . tbl('sicurezza_log') . ' (
  `id` int NOT NULL AUTO_INCREMENT, `evento` varchar(40) NOT NULL, `utente` varchar(100) NOT NULL DEFAULT \'\', `utente_id` int NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL DEFAULT \'\', `user_agent` varchar(255) NOT NULL DEFAULT \'\', `dettagli` text, `creato` datetime NOT NULL,
  PRIMARY KEY (`id`), KEY `creato` (`creato`), KEY `evento` (`evento`), KEY `utente` (`utente`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'sicurezza_tentativi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('sicurezza_tentativi') . ' (
  `chiave` varchar(140) NOT NULL, `falliti` int NOT NULL DEFAULT 0, `ultimo` datetime NOT NULL, `bloccato_fino` datetime DEFAULT NULL, `avvisato` tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (`chiave`), KEY `ultimo` (`ultimo`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'sicurezza_sessioni' => 'CREATE TABLE IF NOT EXISTS ' . tbl('sicurezza_sessioni') . ' (
  `sid` char(64) NOT NULL, `utente_id` int NOT NULL, `ip` varchar(45) NOT NULL DEFAULT \'\', `user_agent` varchar(255) NOT NULL DEFAULT \'\',
  `creata` datetime NOT NULL, `ultimo` datetime NOT NULL, PRIMARY KEY (`sid`), KEY `utente_id` (`utente_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'sicurezza_token' => 'CREATE TABLE IF NOT EXISTS ' . tbl('sicurezza_token') . ' (
  `token` char(64) NOT NULL, `scopo` varchar(20) NOT NULL, `utente_id` int NOT NULL, `scade` datetime NOT NULL, `creato` datetime NOT NULL,
  PRIMARY KEY (`token`), KEY `scade` (`scade`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'sicurezza_csp' => 'CREATE TABLE IF NOT EXISTS ' . tbl('sicurezza_csp') . ' (
  `impronta` char(32) NOT NULL, `contesto` varchar(10) NOT NULL DEFAULT \'sito\', `direttiva` varchar(60) NOT NULL DEFAULT \'\', `bloccato` varchar(500) NOT NULL DEFAULT \'\',
  `documento` varchar(500) NOT NULL DEFAULT \'\', `conteggio` int NOT NULL DEFAULT 1, `primo` datetime NOT NULL, `ultimo` datetime NOT NULL,
  PRIMARY KEY (`impronta`), KEY `ultimo` (`ultimo`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    ];
    $colonne = ['users' => [
        'totp_segreto' => "varchar(64) NOT NULL DEFAULT ''", 'totp_attivo' => 'tinyint NOT NULL DEFAULT 0', 'totp_recupero' => 'text',
        'totp_ultimo' => 'int NOT NULL DEFAULT 0', 'sessione_epoca' => 'int NOT NULL DEFAULT 0', 'password_cambiata' => 'datetime DEFAULT NULL',
        // 1.4.0: l'aspetto del pannello scelto dall'utente (includes/pannello-aspetto.php). Chi c'era gia parte da Aria.
        'pannello_aspetto' => "varchar(12) NOT NULL DEFAULT 'aria'",
    ]];
    $impronta = md5(serialize([$tabelle, $colonne, DB_NAME]));
    if (get_setting('sicurezza_schema_impronta', '') === $impronta) return;
    foreach ($tabelle as $nome => $sql) {
        if (!db()->query('SHOW TABLES LIKE ' . db()->quote(DB_PREFIX . $nome))->fetchColumn()) db()->exec($sql);
    }
    foreach ($colonne as $tab => $cols) {
        $presenti = array_column(db()->query('SHOW COLUMNS FROM ' . tbl($tab))->fetchAll(), 'Field');
        foreach ($cols as $col => $def) if (!in_array($col, $presenti, true)) db()->exec('ALTER TABLE ' . tbl($tab) . " ADD COLUMN `$col` $def");
    }
    save_setting('sicurezza_schema_impronta', $impronta);
}

// ------------------------------------------------------------
// Richiesta: IP, https, origine
// ------------------------------------------------------------

/** L'IP di chi chiama. Un proxy davanti al server si dichiara con CMS_PROXY_FIDATO nel config: solo allora vale X-Forwarded-For. */
function sicurezza_ip(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (defined('CMS_PROXY_FIDATO') && CMS_PROXY_FIDATO && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $primo = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($primo, FILTER_VALIDATE_IP)) $ip = $primo;
    }
    return $ip;
}

function sicurezza_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function sicurezza_user_agent(): string {
    return mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/**
 * Origin e Referer della richiesta devono essere del sito stesso (difesa in profondita oltre al token CSRF).
 * Senza nessuna delle due intestazioni passa: alcuni browser non le mandano sulle richieste dello stesso sito.
 */
function sicurezza_origine_ok(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return true;
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $k) {
        $v = (string)($_SERVER[$k] ?? '');
        if ($v === '' || $v === 'null') { if ($k === 'HTTP_ORIGIN' && $v === 'null') return false; continue; }
        $h = strtolower((string)parse_url($v, PHP_URL_HOST));
        $p = parse_url($v, PHP_URL_PORT);
        if ($p) $h .= ':' . $p;
        $hostSenza = preg_replace('/:(80|443)$/', '', $host);
        if ($h !== $host && $h !== $hostSenza) return false;
        return true; // basta la prima intestazione presente (Origin vince sul Referer)
    }
    return true;
}

// ------------------------------------------------------------
// Registro degli accessi
// ------------------------------------------------------------

function sicurezza_log(string $evento, string $utente = '', int $utenteId = 0, string $dettagli = ''): void {
    try {
        sicurezza_schema();
        db()->prepare('INSERT INTO ' . tbl('sicurezza_log') . ' (evento, utente, utente_id, ip, user_agent, dettagli, creato) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([mb_substr($evento, 0, 40), mb_substr($utente, 0, 100), $utenteId, sicurezza_ip(), sicurezza_user_agent(), mb_substr($dettagli, 0, 2000)]);
        // Il registro non cresce all'infinito: ogni tanto si potano le righe piu vecchie di sei mesi.
        if (random_int(1, 200) === 1) db()->exec('DELETE FROM ' . tbl('sicurezza_log') . ' WHERE creato < DATE_SUB(NOW(), INTERVAL 180 DAY)');
    } catch (Throwable $e) {
        error_log('sicurezza_log: ' . $e->getMessage());
    }
}

/** Le righe del registro con i filtri (evento, utente, ip, da, a). Ritorna [righe, totale]. */
function sicurezza_log_elenco(array $f, int $pagina = 1, int $per = 50): array {
    sicurezza_schema();
    $w = ['1=1']; $p = [];
    if (($f['evento'] ?? '') !== '') { $w[] = 'evento = ?'; $p[] = $f['evento']; }
    if (($f['utente'] ?? '') !== '') { $w[] = 'utente LIKE ? ESCAPE \'\\\\\''; $p[] = '%' . sicurezza_like($f['utente']) . '%'; }
    if (($f['ip'] ?? '') !== '') { $w[] = 'ip LIKE ? ESCAPE \'\\\\\''; $p[] = sicurezza_like($f['ip']) . '%'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($f['da'] ?? ''))) { $w[] = 'creato >= ?'; $p[] = $f['da'] . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($f['a'] ?? ''))) { $w[] = 'creato <= ?'; $p[] = $f['a'] . ' 23:59:59'; }
    $dove = implode(' AND ', $w);
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('sicurezza_log') . " WHERE $dove"); $st->execute($p);
    $tot = (int)$st->fetchColumn();
    $st = db()->prepare('SELECT * FROM ' . tbl('sicurezza_log') . " WHERE $dove ORDER BY id DESC LIMIT " . (int)$per . ' OFFSET ' . (int)(max(1, $pagina) - 1) * $per);
    $st->execute($p);
    return [$st->fetchAll(), $tot];
}

/** I jolly di LIKE (% e _) sfuggiti: la ricerca trova quello che l'utente ha scritto, non di piu. */
function sicurezza_like(string $s): string {
    return addcslashes($s, '%_\\');
}

const SICUREZZA_EVENTI = [
    'login' => 'Accesso riuscito', 'login_durante_blocco' => 'Accesso durante un blocco (IP di casa)', 'login_fallito' => 'Accesso fallito', 'login_bloccato' => 'Tentativo durante un blocco', 'blocco' => 'Blocco attivato',
    'login_2fa_fallito' => 'Codice 2FA sbagliato', 'logout' => 'Uscita', 'sessione_scaduta' => 'Sessione scaduta', 'sessioni_chiuse' => 'Uscita da tutti i dispositivi',
    'password_cambiata' => 'Password cambiata', 'password_reset_richiesto' => 'Richiesta di nuova password', 'password_reset' => 'Password reimpostata via email',
    '2fa_attivato' => '2FA attivato', '2fa_disattivato' => '2FA disattivato', '2fa_recupero' => 'Codice di recupero usato', 'ip_rifiutato' => 'IP non consentito',
    'csrf_rifiutato' => 'Richiesta rifiutata (CSRF o origine)', 'pacchetto' => 'Pacchetto applicato', 'impostazioni' => 'Impostazioni di sicurezza cambiate',
    'utente' => 'Utente modificato', 'upload_rifiutato' => 'Caricamento rifiutato', 'nuovo_ip' => 'Accesso admin da un IP nuovo',
];

// ------------------------------------------------------------
// Email di avviso
// ------------------------------------------------------------

function sicurezza_avvisa(string $oggetto, string $testo): void {
    if (!sicurezza_cfg()['avvisi_email']) return;
    try {
        require_once __DIR__ . '/posta.php';
        $a = posta_notifica_email();
        if ($a === '') return;
        $sito = (string)cms_impostazione('sito_nome', CMS_NOME);
        posta_invia($a, "[$sito] $oggetto", $testo . "\n\nIP: " . sicurezza_ip() . "\nBrowser: " . sicurezza_user_agent() . "\nQuando: " . date('d/m/Y H:i') . "\n\nRegistro: " . cms_url_pannello() . 'sicurezza.php?scheda=registro');
    } catch (Throwable $e) {
        error_log('sicurezza_avvisa: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Limite ai tentativi di login
// ------------------------------------------------------------

function sicurezza_chiavi_tentativi(string $utente): array {
    return ['ip:' . sicurezza_ip(), 'utente:' . mb_strtolower(mb_substr(trim($utente), 0, 100))];
}

/**
 * Blocchi del login (1.1.3, decisi dopo il terzo giro di attacco):
 *  - per IP: tentativi_max sbagliati (di partenza 5) e parte un blocco che raddoppia a ogni tentativo in piu;
 *  - per nome utente: 20 sbagliati in 15 minuti, da qualunque IP, e parte un blocco di 15 minuti che raddoppia, con
 *    un'email all'amministratore. Durante questo blocco entra lo stesso, con la password giusta, chi arriva da un IP
 *    mai bloccato che ha gia fatto accessi riusciti con quell'utente (riga "Accesso durante un blocco" nel registro):
 *    cosi un anonimo che sbaglia apposta non chiude fuori il proprietario.
 */
const SICUREZZA_UTENTE_MAX = 20;
const SICUREZZA_UTENTE_FINESTRA = 15; // minuti: la finestra in cui si contano i 20 tentativi, e la durata del primo blocco

/** Secondi che mancano alla fine del blocco per IP e per nome utente: ['ip' => s, 'utente' => s], 0 se libero. */
function sicurezza_login_blocchi(string $utente): array {
    sicurezza_schema();
    [$kIp, $kUt] = sicurezza_chiavi_tentativi($utente);
    $st = db()->prepare('SELECT bloccato_fino FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ? AND bloccato_fino > NOW()');
    $out = [];
    foreach (['ip' => $kIp, 'utente' => $kUt] as $tipo => $k) {
        $st->execute([$k]);
        $fino = $st->fetchColumn();
        $out[$tipo] = $fino ? max(1, strtotime((string)$fino) - time()) : 0;
    }
    return $out;
}

/** Compatibilita: il blocco piu lungo fra IP e nome utente. */
function sicurezza_login_bloccato(string $utente): int {
    return max(sicurezza_login_blocchi($utente));
}

/**
 * Questo IP e "di casa" per l'utente? Ha gia fatto almeno un accesso riuscito con lui (registro degli accessi),
 * non e mai stato bloccato (nessuna riga "blocco" per ip:<ip>) e non e bloccato adesso.
 */
function sicurezza_ip_fidato(int $utenteId): bool {
    if ($utenteId <= 0) return false;
    sicurezza_schema();
    $ip = sicurezza_ip();
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('sicurezza_log') . " WHERE evento = 'login' AND utente_id = ? AND ip = ?");
    $st->execute([$utenteId, $ip]);
    if ((int)$st->fetchColumn() === 0) return false;
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('sicurezza_log') . " WHERE evento = 'blocco' AND dettagli LIKE ? ESCAPE '\\\\'");
    $st->execute([sicurezza_like('ip:' . $ip) . ' %']);
    if ((int)$st->fetchColumn() > 0) return false;
    return sicurezza_login_blocchi('')['ip'] === 0;
}

/** Un tentativo fallito: conta per IP (blocco a tentativi_max) e per nome utente (blocco a 20 in 15 minuti). */
function sicurezza_login_fallito(string $utente): void {
    sicurezza_schema();
    $cfg = sicurezza_cfg();
    $bloccato = false;
    [$kIp, $kUt] = sicurezza_chiavi_tentativi($utente);
    foreach ([$kIp, $kUt] as $k) {
        $perUtente = $k === $kUt;
        if ($perUtente) {
            // Il conteggio per nome utente vale 15 minuti: passati senza tentativi (e senza blocco in corso), riparte da uno.
            db()->prepare('INSERT INTO ' . tbl('sicurezza_tentativi') . ' (chiave, falliti, ultimo) VALUES (?, 1, NOW())
                ON DUPLICATE KEY UPDATE falliti = IF(ultimo < DATE_SUB(NOW(), INTERVAL ? MINUTE) AND (bloccato_fino IS NULL OR bloccato_fino < NOW()), 1, falliti + 1),
                avvisato = IF(ultimo < DATE_SUB(NOW(), INTERVAL ? MINUTE) AND (bloccato_fino IS NULL OR bloccato_fino < NOW()), 0, avvisato), ultimo = NOW()')
                ->execute([$k, SICUREZZA_UTENTE_FINESTRA, SICUREZZA_UTENTE_FINESTRA]);
        } else {
            db()->prepare('INSERT INTO ' . tbl('sicurezza_tentativi') . ' (chiave, falliti, ultimo) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE falliti = falliti + 1, ultimo = NOW()')->execute([$k]);
        }
        $st = db()->prepare('SELECT falliti, avvisato FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ?'); $st->execute([$k]);
        $r = $st->fetch();
        $n = (int)($r['falliti'] ?? 0);
        $soglia = $perUtente ? SICUREZZA_UTENTE_MAX : (int)$cfg['tentativi_max'];
        $base = $perUtente ? SICUREZZA_UTENTE_FINESTRA : (int)$cfg['blocco_minuti'];
        if ($n >= $soglia) {
            $minuti = min(1440, $base * (2 ** min(10, $n - $soglia)));
            db()->prepare('UPDATE ' . tbl('sicurezza_tentativi') . ' SET bloccato_fino = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE chiave = ?')->execute([$minuti, $k]);
            $bloccato = true;
            if (!$r['avvisato']) {
                db()->prepare('UPDATE ' . tbl('sicurezza_tentativi') . ' SET avvisato = 1 WHERE chiave = ?')->execute([$k]);
                sicurezza_log('blocco', $utente, 0, "$k bloccato per $minuti minuti dopo $n tentativi");
                sicurezza_avvisa($perUtente ? 'Nome utente del pannello bloccato' : 'Accessi falliti al pannello: blocco attivo', $perUtente
                    ? "Dopo $n tentativi sbagliati in " . SICUREZZA_UTENTE_FINESTRA . " minuti, da uno o piu indirizzi, il nome utente \"$utente\" e bloccato per $minuti minuti. Nessuno e entrato: la password non ha funzionato. Il proprietario entra lo stesso dai computer da cui ha gia fatto accesso. Dettagli nella pagina Sicurezza del pannello."
                    : "Dopo $n tentativi sbagliati l'accesso da questo indirizzo e bloccato per $minuti minuti ($k). Se non eri tu, nessuno e entrato: la password non ha funzionato. Puoi vedere i tentativi nella pagina Sicurezza del pannello.");
            }
        }
    }
    sicurezza_log($bloccato ? 'login_bloccato' : 'login_fallito', $utente);
    // Pulizia dei tentativi vecchi di un giorno (il conteggio ricomincia).
    if (random_int(1, 20) === 1) db()->exec('DELETE FROM ' . tbl('sicurezza_tentativi') . ' WHERE ultimo < DATE_SUB(NOW(), INTERVAL 1 DAY) AND (bloccato_fino IS NULL OR bloccato_fino < NOW())');
}

/** Accesso riuscito: azzera il conteggio dell'IP e del nome utente (tranne il blocco per nome utente in corso, se $tieniUtente). */
function sicurezza_login_riuscito(string $utente, bool $tieniUtente = false): void {
    sicurezza_schema();
    [$kIp, $kUt] = sicurezza_chiavi_tentativi($utente);
    $st = db()->prepare('DELETE FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ?');
    $st->execute([$kIp]);
    if (!$tieniUtente) $st->execute([$kUt]);
}

/**
 * Password dimenticata (sicurezza, attacco 8): un conteggio a parte, per IP e per email, che non tocca quello del login.
 * Cosi chi conosce l'email di un utente puo al massimo fermare le richieste di reset per quell'email, mai il suo accesso.
 * Limiti: 3 richieste l'ora per email, 10 l'ora per IP. Ritorna i secondi d'attesa (0 = si puo).
 */
function sicurezza_reset_attesa(string $email): int {
    sicurezza_schema();
    $limiti = ['reset-ip:' . sicurezza_ip() => 10, 'reset-email:' . hash('sha256', mb_strtolower(trim($email))) => 3];
    $st = db()->prepare('SELECT falliti, TIMESTAMPDIFF(SECOND, ultimo, NOW()) AS eta FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ?');
    $max = 0;
    foreach ($limiti as $k => $n) {
        $st->execute([$k]);
        $r = $st->fetch();
        if ($r && (int)$r['eta'] < 3600 && (int)$r['falliti'] >= $n) $max = max($max, 3600 - (int)$r['eta']);
    }
    return $max;
}

function sicurezza_reset_conta(string $email): void {
    sicurezza_schema();
    foreach (['reset-ip:' . sicurezza_ip(), 'reset-email:' . hash('sha256', mb_strtolower(trim($email)))] as $k) {
        // Passata un'ora dall'ultima richiesta il conteggio riparte da uno.
        db()->prepare('INSERT INTO ' . tbl('sicurezza_tentativi') . ' (chiave, falliti, ultimo) VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE falliti = IF(ultimo < DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, falliti + 1), ultimo = NOW()')->execute([$k]);
    }
}

/**
 * Un limite generico per le azioni pubbliche (invio di un report, una richiesta): al massimo $max volte in $secondi per
 * quella chiave. Conta la richiesta e ritorna vero se e ancora dentro il limite. Chiavi a parte dal login.
 */
function sicurezza_limite(string $chiave, int $max, int $secondi): bool {
    sicurezza_schema();
    $k = 'limite:' . mb_substr($chiave, 0, 120);
    db()->prepare('INSERT INTO ' . tbl('sicurezza_tentativi') . ' (chiave, falliti, ultimo) VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE falliti = IF(ultimo < DATE_SUB(NOW(), INTERVAL ? SECOND), 1, falliti + 1), ultimo = NOW()')->execute([$k, $secondi]);
    $st = db()->prepare('SELECT falliti FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ?'); $st->execute([$k]);
    return (int)$st->fetchColumn() <= $max;
}

/** I blocchi in corso (per la pagina Sicurezza). */
function sicurezza_blocchi_attivi(): array {
    sicurezza_schema();
    return db()->query('SELECT * FROM ' . tbl('sicurezza_tentativi') . ' WHERE bloccato_fino > NOW() ORDER BY bloccato_fino DESC')->fetchAll();
}

function sicurezza_sblocca(string $chiave): void {
    db()->prepare('DELETE FROM ' . tbl('sicurezza_tentativi') . ' WHERE chiave = ?')->execute([$chiave]);
}

// ------------------------------------------------------------
// Password: requisiti, hash, migrazione
// ------------------------------------------------------------

// I requisiti della password stanno in includes/sicurezza-password.php (senza database: li usa anche l'installatore).


/**
 * sicurezza (attacco 3): l'hash finto per il ramo "utente che non esiste" del login, con lo stesso algoritmo e gli stessi
 * parametri degli account veri (sicurezza_password_hash). Si calcola una volta e resta nelle impostazioni; se l'algoritmo
 * del server cambia (password_needs_rehash), si rifa. Cosi verificare un utente vero o uno inventato costa lo stesso tempo.
 */
function sicurezza_hash_finto(): string {
    static $h = null;
    if ($h !== null) return $h;
    $alg = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    $opz = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1] : ['cost' => 12];
    $h = (string)get_setting('sicurezza_hash_finto', '');
    if ($h === '' || password_needs_rehash($h, $alg, $opz)) {
        $h = sicurezza_password_hash(bin2hex(random_bytes(24)));
        try { save_setting('sicurezza_hash_finto', $h); } catch (Throwable $e) {}
    }
    return $h;
}

/** Verifica e, se l'hash e di un algoritmo vecchio, lo rifa con quello di adesso (migrazione trasparente al login). */
function sicurezza_password_verifica(array &$user, string $pw): bool {
    if (!password_verify($pw, (string)$user['password_hash'])) return false;
    $alg = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    $opz = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1] : ['cost' => 12];
    if (password_needs_rehash((string)$user['password_hash'], $alg, $opz)) {
        try {
            $nuovo = sicurezza_password_hash($pw);
            db()->prepare('UPDATE ' . tbl('users') . ' SET password_hash = ? WHERE id = ?')->execute([$nuovo, (int)$user['id']]);
            $user['password_hash'] = $nuovo;
        } catch (Throwable $e) { error_log('rehash: ' . $e->getMessage()); }
    }
    return true;
}

/** Cambia la password di un utente e chiude tutte le sue altre sessioni. */
function sicurezza_password_imposta(int $utenteId, string $pw, bool $tieniQuesta = true): void {
    sicurezza_schema();
    db()->prepare('UPDATE ' . tbl('users') . ' SET password_hash = ?, password_cambiata = NOW(), sessione_epoca = sessione_epoca + 1 WHERE id = ?')->execute([sicurezza_password_hash($pw), $utenteId]);
    sicurezza_sessioni_chiudi($utenteId, $tieniQuesta);
    if ($tieniQuesta && !empty($_SESSION['sic']) && (int)($_SESSION['admin_user_id'] ?? 0) === $utenteId) $_SESSION['sic']['epoca']++;
}

// ------------------------------------------------------------
// Sessione
// ------------------------------------------------------------

/** Apre la sessione con i cookie giusti: HttpOnly, SameSite=Lax, Secure su https, id non accettati se sconosciuti. */
function sicurezza_sessione_avvia(): void {
    if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') return;
    if (headers_sent()) { @session_start(); return; }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => sicurezza_https()]);
    session_start();
}

/** Dopo la verifica della password (e del secondo fattore): id nuovo, dati in sessione, riga nel registro delle sessioni. */
function sicurezza_login_completa(array $user): void {
    sicurezza_schema();
    $pre = $_SESSION['sic_pre'] ?? null;
    session_regenerate_id(true);
    $_SESSION = ['admin_user_id' => (int)$user['id'], 'admin_username' => $user['username'], 'admin_role' => $user['role']];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['sic'] = ['creata' => time(), 'ultimo' => time(), 'epoca' => (int)($user['sessione_epoca'] ?? 0), 'ip' => sicurezza_ip(), 'scritto' => time()];
    db()->prepare('INSERT INTO ' . tbl('sicurezza_sessioni') . ' (sid, utente_id, ip, user_agent, creata, ultimo) VALUES (?,?,?,?,NOW(),NOW())')
        ->execute([hash('sha256', session_id()), (int)$user['id'], sicurezza_ip(), sicurezza_user_agent()]);
    db()->prepare('UPDATE ' . tbl('users') . ' SET last_login = NOW() WHERE id = ?')->execute([(int)$user['id']]);
    sicurezza_login_riuscito((string)$user['username'], !empty($GLOBALS['sic_durante_blocco']));
    // Accesso di un amministratore da un IP mai visto negli ultimi accessi riusciti: una riga nel registro e un'email.
    if (($user['role'] ?? '') === 'admin') {
        try {
            $st = db()->prepare('SELECT ip FROM ' . tbl('sicurezza_log') . " WHERE evento = 'login' AND utente_id = ? ORDER BY id DESC LIMIT 60");
            $st->execute([(int)$user['id']]);
            $visti = array_unique(array_column($st->fetchAll(), 'ip'));
            if ($visti && !in_array(sicurezza_ip(), $visti, true)) {
                sicurezza_log('nuovo_ip', (string)$user['username'], (int)$user['id']);
                sicurezza_avvisa('Accesso amministratore da un indirizzo nuovo', 'L\'utente ' . $user['username'] . ' e entrato nel pannello da un IP mai usato prima. Se non eri tu, cambia subito la password dal pannello e usa "Esci da tutti i dispositivi" nella pagina Sicurezza.');
            }
        } catch (Throwable $e) { error_log('nuovo_ip: ' . $e->getMessage()); }
    }
    sicurezza_log('login', (string)$user['username'], (int)$user['id'], $pre ? 'con secondo fattore' : '');
}

/**
 * La sessione del pannello e ancora valida? Ritorna '' se si, altrimenti il motivo: scaduta, inattiva, revocata, ip.
 * Aggiorna l'ultimo accesso (nel database al massimo una volta al minuto).
 */
function sicurezza_sessione_controlla(?array $user): string {
    $cfg = sicurezza_cfg();
    $s = $_SESSION['sic'] ?? null;
    if (!$s) {
        // Sessione aperta prima di questa versione: si completa con i dati di adesso, senza buttare fuori nessuno.
        $_SESSION['sic'] = $s = ['creata' => time(), 'ultimo' => time(), 'epoca' => (int)($user['sessione_epoca'] ?? 0), 'ip' => sicurezza_ip(), 'scritto' => 0];
    }
    if (time() - (int)$s['creata'] > $cfg['sessione_ore'] * 3600) return 'scaduta';
    if (time() - (int)$s['ultimo'] > $cfg['inattivita_minuti'] * 60) return 'inattiva';
    if ($user && (int)$s['epoca'] !== (int)($user['sessione_epoca'] ?? 0)) return 'revocata';
    if (!sicurezza_spento() && $cfg['ip_attivo'] && !sicurezza_ip_consentito(sicurezza_ip(), (string)$cfg['ip_elenco'])) return 'ip';
    $_SESSION['sic']['ultimo'] = time();
    if (time() - (int)($s['scritto'] ?? 0) > 60) {
        $_SESSION['sic']['scritto'] = time();
        try {
            sicurezza_schema();
            $st = db()->prepare('UPDATE ' . tbl('sicurezza_sessioni') . ' SET ultimo = NOW() WHERE sid = ?');
            $st->execute([hash('sha256', session_id())]);
            if ($st->rowCount() === 0 && $user) {
                // La riga manca (sessione nata prima di questa versione, oppure chiusa da "Esci da tutti"): si verifica l'epoca e si riscrive.
                db()->prepare('INSERT IGNORE INTO ' . tbl('sicurezza_sessioni') . ' (sid, utente_id, ip, user_agent, creata, ultimo) VALUES (?,?,?,?,NOW(),NOW())')
                    ->execute([hash('sha256', session_id()), (int)$user['id'], sicurezza_ip(), sicurezza_user_agent()]);
            }
        } catch (Throwable $e) { error_log('sessione: ' . $e->getMessage()); }
    }
    return '';
}

/** Chiude la sessione corrente: cookie azzerato, riga tolta, id rigenerato. */
function sicurezza_sessione_chiudi(string $evento = 'logout'): void {
    $uid = (int)($_SESSION['admin_user_id'] ?? 0);
    $nome = (string)($_SESSION['admin_username'] ?? '');
    try {
        sicurezza_schema();
        db()->prepare('DELETE FROM ' . tbl('sicurezza_sessioni') . ' WHERE sid = ?')->execute([hash('sha256', session_id())]);
    } catch (Throwable $e) {}
    if ($uid) sicurezza_log($evento, $nome, $uid);
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite']]);
    }
    session_destroy();
}

/** "Esci da tutti i dispositivi": l'epoca dell'utente cambia e ogni sua sessione (tranne, a scelta, questa) muore. */
function sicurezza_sessioni_chiudi(int $utenteId, bool $tieniQuesta = false): int {
    sicurezza_schema();
    $mia = hash('sha256', session_id() ?: '');
    if ($tieniQuesta) {
        $st = db()->prepare('DELETE FROM ' . tbl('sicurezza_sessioni') . ' WHERE utente_id = ? AND sid <> ?'); $st->execute([$utenteId, $mia]);
    } else {
        $st = db()->prepare('DELETE FROM ' . tbl('sicurezza_sessioni') . ' WHERE utente_id = ?'); $st->execute([$utenteId]);
    }
    $n = $st->rowCount();
    db()->prepare('UPDATE ' . tbl('users') . ' SET sessione_epoca = sessione_epoca + 1 WHERE id = ?')->execute([$utenteId]);
    if ($tieniQuesta && (int)($_SESSION['admin_user_id'] ?? 0) === $utenteId && isset($_SESSION['sic'])) $_SESSION['sic']['epoca']++;
    sicurezza_log('sessioni_chiuse', (string)($_SESSION['admin_username'] ?? ''), $utenteId, "utente $utenteId, $n sessioni chiuse");
    return $n;
}

function sicurezza_sessioni_di(int $utenteId): array {
    sicurezza_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('sicurezza_sessioni') . ' WHERE utente_id = ? ORDER BY ultimo DESC'); $st->execute([$utenteId]);
    return $st->fetchAll();
}

// ------------------------------------------------------------
// IP consentiti
// ------------------------------------------------------------

/** L'IP sta nell'elenco (un indirizzo o una rete CIDR per riga, IPv4 e IPv6)? Un elenco vuoto non lascia passare nessuno. */
function sicurezza_ip_consentito(string $ip, string $elenco): bool {
    $bin = @inet_pton($ip);
    if ($bin === false) return false;
    foreach (preg_split('/[\s,;]+/', $elenco) ?: [] as $voce) {
        $voce = trim($voce);
        if ($voce === '' || str_starts_with($voce, '#')) continue;
        [$rete, $bits] = array_pad(explode('/', $voce, 2), 2, null);
        $rbin = @inet_pton($rete);
        if ($rbin === false || strlen($rbin) !== strlen($bin)) continue;
        $max = strlen($bin) * 8;
        $bits = $bits === null ? $max : (int)$bits;
        if ($bits < 0 || $bits > $max) continue;
        $byte = intdiv($bits, 8); $resto = $bits % 8;
        if ($byte > 0 && substr($bin, 0, $byte) !== substr($rbin, 0, $byte)) continue;
        if ($resto > 0) {
            $m = 0xFF << (8 - $resto) & 0xFF;
            if ((ord($bin[$byte]) & $m) !== (ord($rbin[$byte]) & $m)) continue;
        }
        return true;
    }
    return false;
}

// ------------------------------------------------------------
// Secondo fattore: TOTP (RFC 6238), codici di recupero
// ------------------------------------------------------------

function sicurezza_base32_encode(string $bin): string {
    $alf = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = ''; $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $p) $out .= $alf[bindec(str_pad($p, 5, '0'))];
    return $out;
}

function sicurezza_base32_decode(string $s): string {
    $alf = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s));
    $bits = '';
    for ($i = 0; $i < strlen($s); $i++) $bits .= str_pad(decbin(strpos($alf, $s[$i])), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
    return $out;
}

function sicurezza_totp_segreto(): string {
    return sicurezza_base32_encode(random_bytes(20));
}

/** Il codice a sei cifre per un passo di 30 secondi. */
function sicurezza_totp_codice(string $segreto, int $passo): string {
    $chiave = sicurezza_base32_decode($segreto);
    $msg = pack('J', $passo);
    $h = hash_hmac('sha1', $msg, $chiave, true);
    $off = ord($h[19]) & 0x0F;
    $n = ((ord($h[$off]) & 0x7F) << 24) | (ord($h[$off + 1]) << 16) | (ord($h[$off + 2]) << 8) | ord($h[$off + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Verifica un codice con una finestra di un passo prima e dopo. Ritorna il passo usato, o 0 se sbagliato. */
function sicurezza_totp_verifica(string $segreto, string $codice, int $ultimoUsato = 0): int {
    $codice = preg_replace('/\D/', '', $codice);
    if (strlen($codice) !== 6 || $segreto === '') return 0;
    $adesso = intdiv(time(), 30);
    for ($d = -1; $d <= 1; $d++) {
        $p = $adesso + $d;
        if ($p <= $ultimoUsato) continue; // un codice non si riusa (replay)
        if (hash_equals(sicurezza_totp_codice($segreto, $p), $codice)) return $p;
    }
    return 0;
}

function sicurezza_totp_uri(string $segreto, string $utente): string {
    $sito = preg_replace('/[^A-Za-z0-9 ._-]/', '', (string)cms_impostazione('sito_nome', CMS_NOME)) ?: CMS_NOME;
    $sito = mb_substr(trim($sito), 0, 24);
    $utente = preg_replace('/[^A-Za-z0-9._@-]/', '', $utente);
    return 'otpauth://totp/' . rawurlencode($sito) . ':' . rawurlencode($utente) . '?secret=' . $segreto . '&issuer=' . rawurlencode($sito) . '&algorithm=SHA1&digits=6&period=30';
}

/** Otto codici di recupero: si mostrano una volta sola, nel database restano solo gli hash. Ritorna [in chiaro, hash]. */
function sicurezza_recupero_genera(): array {
    $chiaro = []; $hash = [];
    for ($i = 0; $i < 8; $i++) {
        $c = strtolower(substr(sicurezza_base32_encode(random_bytes(10)), 0, 10));
        $c = substr($c, 0, 5) . '-' . substr($c, 5, 5);
        $chiaro[] = $c; $hash[] = hash('sha256', $c);
    }
    return [$chiaro, $hash];
}

/** Usa un codice di recupero: se e nell'elenco lo toglie e ritorna vero. */
function sicurezza_recupero_usa(array $user, string $codice): bool {
    $codice = strtolower(preg_replace('/[^a-z0-9-]/', '', strtolower($codice)));
    $hash = json_decode((string)($user['totp_recupero'] ?? ''), true) ?: [];
    $h = hash('sha256', $codice);
    $trovato = false; $resto = [];
    foreach ($hash as $x) { if (!$trovato && hash_equals($x, $h)) { $trovato = true; continue; } $resto[] = $x; }
    if (!$trovato) return false;
    db()->prepare('UPDATE ' . tbl('users') . ' SET totp_recupero = ? WHERE id = ?')->execute([json_encode($resto), (int)$user['id']]);
    sicurezza_log('2fa_recupero', (string)$user['username'], (int)$user['id'], count($resto) . ' codici rimasti');
    return true;
}

/** Il 2FA e richiesto a questo utente (attivo per lui, oppure obbligatorio per gli admin)? */
function sicurezza_2fa_richiesto(array $user): bool {
    if (sicurezza_spento()) return false;
    return !empty($user['totp_attivo']);
}

/** Un amministratore senza 2FA quando il 2FA e obbligatorio: deve configurarlo prima di fare altro. */
function sicurezza_2fa_da_configurare(array $user): bool {
    if (sicurezza_spento()) return false;
    return sicurezza_cfg()['totp_admin'] && ($user['role'] ?? '') === 'admin' && empty($user['totp_attivo']);
}

// ------------------------------------------------------------
// Token monouso (password dimenticata)
// ------------------------------------------------------------

function sicurezza_token_crea(string $scopo, int $utenteId, int $minuti = 30): string {
    sicurezza_schema();
    $t = bin2hex(random_bytes(32));
    db()->exec('DELETE FROM ' . tbl('sicurezza_token') . ' WHERE scade < NOW()');
    db()->prepare('DELETE FROM ' . tbl('sicurezza_token') . ' WHERE scopo = ? AND utente_id = ?')->execute([$scopo, $utenteId]);
    db()->prepare('INSERT INTO ' . tbl('sicurezza_token') . ' (token, scopo, utente_id, scade, creato) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE),NOW())')->execute([hash('sha256', $t), $scopo, $utenteId, $minuti]);
    return $t;
}

/** L'utente a cui appartiene il token, se valido e non scaduto; con $consuma il token muore. */
function sicurezza_token_leggi(string $scopo, string $token, bool $consuma = false): ?int {
    sicurezza_schema();
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $h = hash('sha256', $token);
    $st = db()->prepare('SELECT utente_id FROM ' . tbl('sicurezza_token') . ' WHERE token = ? AND scopo = ? AND scade > NOW()'); $st->execute([$h, $scopo]);
    $uid = $st->fetchColumn();
    if ($uid === false) return null;
    if ($consuma) db()->prepare('DELETE FROM ' . tbl('sicurezza_token') . ' WHERE token = ?')->execute([$h]);
    return (int)$uid;
}

// ------------------------------------------------------------
// Intestazioni HTTP e Content-Security-Policy
// ------------------------------------------------------------

/** Il nonce degli script in linea di questa richiesta. */
function sicurezza_nonce(): string {
    static $n = null;
    return $n ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
}

/** L'attributo da mettere su ogni <script> in linea: nonce="...". */
function sicurezza_nonce_attr(): string {
    return ' nonce="' . sicurezza_nonce() . '"';
}

/** Aggiunge il nonce agli <script> senza nonce di un pezzo di HTML scritto dall'amministratore (widget HTML, codice del pannello SEO). */
function sicurezza_nonce_script(string $html): string {
    if (stripos($html, '<script') === false) return $html;
    return (string)preg_replace_callback('/<script\b([^>]*)>/i', function ($m) {
        if (preg_match('/\snonce\s*=/i', $m[1])) return $m[0];
        return '<script' . $m[1] . sicurezza_nonce_attr() . '>';
    }, $html);
}

/** Le intestazioni che valgono per ogni risposta del CMS (anche API, feed, immagini): niente X-Powered-By, niente sniffing, niente frame esterni. */
function sicurezza_intestazioni_base(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    if (sicurezza_https() && function_exists('sicurezza_cfg')) {
        try { if (sicurezza_cfg()['hsts']) header('Strict-Transport-Security: max-age=15552000'); } catch (Throwable $e) {}
    }
}

/**
 * Le intestazioni di una pagina HTML del sito ('sito') o del pannello ('pannello'), CSP compresa.
 */
function sicurezza_intestazioni(string $contesto): void {
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    sicurezza_intestazioni_base();
    header('Cross-Origin-Opener-Policy: ' . ($contesto === 'pannello' ? 'same-origin' : 'same-origin-allow-popups'));
    try { $cfg = sicurezza_cfg(); } catch (Throwable $e) { return; }
    $modo = (string)$cfg[$contesto === 'pannello' ? 'csp_pannello' : 'csp_sito'];
    // frame-ancestors si applica sempre (il clickjacking non aspetta la fase di rapporto).
    header("Content-Security-Policy: frame-ancestors 'self'");
    if ($modo === 'spenta') return;
    $csp = sicurezza_csp($contesto);
    if ($modo === 'applica') header('Content-Security-Policy: ' . $csp . "; frame-ancestors 'self'", true);
    else header('Content-Security-Policy-Report-Only: ' . $csp . '; report-uri ' . cms_base() . '/api/csp.php?c=' . $contesto);
}

/** Il testo della CSP per il sito o per il pannello. Le sorgenti dei tag di analisi entrano solo se attive nel pannello SEO. */
function sicurezza_csp(string $contesto): string {
    $cfg = sicurezza_cfg();
    $nonce = "'nonce-" . sicurezza_nonce() . "'";
    $d = [
        'default-src' => ["'self'"], 'base-uri' => ["'self'"], 'object-src' => ["'none'"],
        'script-src' => ["'self'", $nonce], 'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
        'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'], 'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
        'media-src' => ["'self'", 'https:', 'blob:'], 'connect-src' => ["'self'"], 'form-action' => ["'self'"],
        'frame-src' => $contesto === 'pannello' ? ["'self'"] : ["'self'", 'https://www.youtube-nocookie.com', 'https://www.youtube.com', 'https://player.vimeo.com', 'https://www.google.com', 'https://www.openstreetmap.org'],
    ];
    if ($contesto === 'pannello') {
        // Le pagine del nucleo non hanno piu gestori in linea (onclick="..."): li aggancia admin/assets/pannello.js dagli attributi data-*.
        // Le pagine vecchie dei moduli che ne hanno ancora molti (o li generano in JavaScript) si dichiarano con il filtro
        // 'csp_pannello_inline' e solo su quelle si ammettono gli script in linea. Altrove, un gestore rimasto passa con il suo hash.
        // sicurezza (attacco 2): nessun hash ricavato dall'output (un gestore iniettato si autorizzerebbe da solo). Solo il nonce;
        // le pagine vecchie dei moduli che hanno ancora gestori in linea si dichiarano con il filtro e solo li vale 'unsafe-inline'.
        $legacy = (array)(function_exists('cms_filtro') ? cms_filtro('csp_pannello_inline', []) : []);
        $pagina = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (in_array($pagina, $legacy, true)) $d['script-src'] = ["'self'", "'unsafe-inline'"];
        // Sorgenti esterne dichiarate da un modulo per una sua pagina: ['pagina.php' => ['script-src' => ['https://...']]].
        $extra = (array)(function_exists('cms_filtro') ? cms_filtro('csp_pannello_sorgenti', []) : []);
        foreach ((array)($extra[$pagina] ?? []) as $dir => $fonti) {
            if (!isset($d[$dir])) continue;
            foreach ((array)$fonti as $f) if (preg_match('~^https://[a-z0-9.-]+(/[A-Za-z0-9._/@-]*)?$~', (string)$f)) $d[$dir][] = $f;
        }
    } else {
        $a = [];
        if (function_exists('seo_cfg')) { try { $a = seo_cfg()['analisi'] ?? []; } catch (Throwable $e) {} }
        if (preg_match('/^G-[A-Z0-9]{4,}$/', (string)($a['ga4'] ?? '')) || preg_match('/^GTM-[A-Z0-9]{4,}$/', (string)($a['gtm'] ?? ''))) {
            $d['script-src'][] = 'https://www.googletagmanager.com';
            array_push($d['connect-src'], 'https://www.googletagmanager.com', 'https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://stats.g.doubleclick.net');
            $d['frame-src'][] = 'https://www.googletagmanager.com';
        }
        if (preg_match('/^\d{6,20}$/', (string)($a['pixel'] ?? ''))) { $d['script-src'][] = 'https://connect.facebook.net'; $d['connect-src'][] = 'https://www.facebook.com'; $d['frame-src'][] = 'https://www.facebook.com'; }
        if (preg_match('/^[a-z0-9]{6,20}$/i', (string)($a['clarity'] ?? ''))) { array_push($d['script-src'], 'https://www.clarity.ms', 'https://*.clarity.ms'); $d['connect-src'][] = 'https://*.clarity.ms'; }
        if (sicurezza_https()) $d['upgrade-insecure-requests'] = [];
    }
    // Sorgenti in piu scritte nella pagina Sicurezza: "direttiva sorgente" per riga.
    foreach (preg_split('/\r?\n/', (string)$cfg['csp_extra']) ?: [] as $r) {
        if (!preg_match('/^\s*([a-z-]+-src)\s+(\S.*)$/', trim($r), $m) || !isset($d[$m[1]])) continue;
        foreach (preg_split('/\s+/', trim($m[2])) as $s) if (preg_match('~^(https?://[A-Za-z0-9*.:-]+|\'[a-z-]+\'|data:|blob:)$~', $s)) $d[$m[1]][] = $s;
    }
    $out = [];
    foreach ($d as $k => $v) $out[] = $v ? $k . ' ' . implode(' ', array_unique($v)) : $k;
    return implode('; ', $out);
}

/**
 * Per il pannello: a fine pagina si emettono le intestazioni (CSP a nonce). Si usa come callback di ob_start.
 * Nessun hash dei gestori in linea: il nucleo non ne ha, e uno iniettato non deve autorizzarsi da solo.
 */
function sicurezza_pannello_fine(string $html): string {
    if (headers_sent()) return $html;
    $tipo = '';
    foreach (headers_list() as $h) if (stripos($h, 'Content-Type:') === 0) $tipo = $h;
    if ($tipo === '' || stripos($tipo, 'text/html') !== false) sicurezza_intestazioni('pannello');
    else sicurezza_intestazioni_base();
    return $html;
}

/** Registra un rapporto CSP (api/csp.php). Tiene al massimo 500 voci distinte, con il conteggio. */
function sicurezza_csp_rapporto(string $contesto, array $r): void {
    sicurezza_schema();
    $direttiva = mb_substr((string)($r['effective-directive'] ?? $r['violated-directive'] ?? $r['effectiveDirective'] ?? ''), 0, 60);
    $bloccato = mb_substr((string)($r['blocked-uri'] ?? $r['blockedURL'] ?? ''), 0, 500);
    $doc = mb_substr((string)($r['document-uri'] ?? $r['documentURL'] ?? ''), 0, 500);
    if ($direttiva === '') return;
    // Via l'identificativo di sessione e le query lunghe dal documento: nel rapporto serve la pagina, non i dati.
    $doc = preg_replace('/\?.*$/', '', $doc) ?? $doc;
    $contesto = $contesto === 'pannello' ? 'pannello' : 'sito';
    $impronta = md5($contesto . '|' . $direttiva . '|' . $bloccato . '|' . $doc);
    $n = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('sicurezza_csp'))->fetchColumn();
    $st = db()->prepare('SELECT 1 FROM ' . tbl('sicurezza_csp') . ' WHERE impronta = ?'); $st->execute([$impronta]);
    if (!$st->fetchColumn() && $n >= 500) return;
    db()->prepare('INSERT INTO ' . tbl('sicurezza_csp') . ' (impronta, contesto, direttiva, bloccato, documento, conteggio, primo, ultimo) VALUES (?,?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE conteggio = conteggio + 1, ultimo = NOW()')
        ->execute([$impronta, $contesto, $direttiva, $bloccato, $doc]);
}

// ------------------------------------------------------------
// Firma dei pacchetti del pannello Aggiornamenti
// ------------------------------------------------------------

/** La chiave con cui si firmano i pacchetti: CMS_FIRMA_PACCHETTI nel config.local.php, altrimenti CMS_SEGRETO. */
function sicurezza_chiave_pacchetti(): string {
    if (defined('CMS_FIRMA_PACCHETTI') && CMS_FIRMA_PACCHETTI !== '') return (string)CMS_FIRMA_PACCHETTI;
    return defined('CMS_SEGRETO') ? (string)CMS_SEGRETO : '';
}

/**
 * L'impronta di uno zip: per ogni voce (tranne manifest.json), in ordine di nome, "nome sha256" su una riga.
 * La firma e l'HMAC-SHA256 di questo testo con la chiave. Lo stesso calcolo lo fa tools/pacchetto.py --firma.
 */
function sicurezza_pacchetto_impronta(ZipArchive $zip): string {
    $righe = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if ($n === false || $n === 'manifest.json' || str_ends_with($n, '/')) continue;
        $righe[] = $n . ' ' . hash('sha256', (string)$zip->getFromIndex($i));
    }
    sort($righe, SORT_STRING);
    return implode("\n", $righe);
}

function sicurezza_pacchetto_firma_ok(ZipArchive $zip, string $firma): bool {
    $chiave = sicurezza_chiave_pacchetti();
    if ($chiave === '' || !preg_match('/^[a-f0-9]{64}$/', $firma)) return false;
    return hash_equals(hash_hmac('sha256', sicurezza_pacchetto_impronta($zip), $chiave), $firma);
}

// ------------------------------------------------------------
// Stato per la pagina Sicurezza
// ------------------------------------------------------------

/** Chiede al sito stesso un indirizzo (timeout breve) e ritorna il codice HTTP, 0 se non raggiungibile. */
function sicurezza_autoverifica(string $percorso): int {
    $url = cms_url_sito();
    if ($url === '') return 0;
    $origine = preg_replace('~^(https?://[^/]+).*$~', '$1', $url) ?: $url;
    $u = $origine . cms_base() . $percorso;
    if (!function_exists('curl_init')) return 0;
    $ch = curl_init($u);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true]);
    curl_exec($ch);
    $c = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $c;
}

/** La lista di controllo: ogni voce con esito (ok | attenzione | no | info), titolo e spiegazione. */
function sicurezza_checklist(): array {
    $cfg = sicurezza_cfg();
    $v = [];
    $https = sicurezza_https();
    $v[] = ['https', $https ? 'ok' : 'no', 'Connessione https', $https ? 'Il pannello e aperto in https.' : 'Il pannello e aperto in http: la password viaggia in chiaro. Attiva il certificato nell\'hosting e la riga "forza https" nel .htaccess.'];
    $v[] = ['csp_sito', $cfg['csp_sito'] === 'applica' ? 'ok' : ($cfg['csp_sito'] === 'rapporto' ? 'attenzione' : 'no'), 'Content-Security-Policy del sito', ['spenta' => 'Spenta.', 'rapporto' => 'In modalita "solo rapporto": il browser segnala, non blocca. Guarda la scheda Rapporti CSP e poi passa ad "applica".', 'applica' => 'Applicata: gli script non previsti vengono bloccati.'][$cfg['csp_sito']]];
    $v[] = ['csp_pannello', $cfg['csp_pannello'] === 'applica' ? 'ok' : ($cfg['csp_pannello'] === 'rapporto' ? 'attenzione' : 'no'), 'Content-Security-Policy del pannello', ['spenta' => 'Spenta.', 'rapporto' => 'In modalita "solo rapporto".', 'applica' => 'Applicata.'][$cfg['csp_pannello']]];
    $v[] = ['hsts', $https ? ($cfg['hsts'] ? 'ok' : 'attenzione') : 'info', 'Strict-Transport-Security', $https ? ($cfg['hsts'] ? 'Attiva: il browser usa sempre https.' : 'Spenta: si accende nelle impostazioni qui sotto, solo quando il certificato funziona bene.') : 'Vale solo su https.'];
    $v[] = ['php', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'no', 'Versione di PHP: ' . PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>=') ? 'Versione supportata.' : 'Versione vecchia, senza correzioni di sicurezza: cambiala da cPanel (Select PHP Version).'];
    $v[] = ['display_errors', (ini_get('display_errors') === '1' || strtolower((string)ini_get('display_errors')) === 'on') && PUBLISHER_ERRORI !== 'pannello' ? 'attenzione' : 'ok', 'Errori a video', 'Fuori dal pannello gli errori vanno solo nel log (includes/config.php).'];
    $v[] = ['expose_php', ini_get('expose_php') ? 'attenzione' : 'ok', 'expose_php', ini_get('expose_php') ? 'PHP annuncia la sua versione: il CMS toglie l\'intestazione X-Powered-By, ma conviene spegnere expose_php nel php.ini.' : 'Spento.'];
    $cfgFile = cms_radice() . '/includes/config.local.php';
    $perm = is_file($cfgFile) ? fileperms($cfgFile) & 0777 : 0;
    $v[] = ['permessi', $perm && ($perm & 0007) ? 'attenzione' : 'ok', 'Permessi di config.local.php: ' . ($perm ? decoct($perm) : 'n.d.'), $perm & 0007 ? 'Il file e leggibile da tutti gli utenti del server: chmod 640 (o 600) dal file manager di cPanel.' : 'Il file non e leggibile da altri utenti del server.'];
    $cfgWeb = sicurezza_autoverifica('/includes/config.local.php');
    $v[] = ['config_web', $cfgWeb === 0 ? 'info' : (in_array($cfgWeb, [403, 404], true) ? 'ok' : 'no'), 'config.local.php dal web', $cfgWeb === 0 ? 'Non verificabile da qui (il server non raggiunge se stesso).' : ($cfgWeb === 200 ? 'RISPONDE 200: il web server lo serve. Controlla il .htaccess (voce includes negata) o la regola Nginx.' : "Negato ($cfgWeb).")];
    $logWeb = sicurezza_autoverifica('/logs/php-errori.log');
    $v[] = ['logs_web', $logWeb === 0 ? 'info' : (in_array($logWeb, [403, 404], true) ? 'ok' : 'no'), 'Cartella logs dal web', $logWeb === 0 ? 'Non verificabile da qui.' : ($logWeb === 200 ? 'Il log degli errori e leggibile da chiunque: nega la cartella logs nel .htaccess.' : "Negata ($logWeb).")];
    $install = is_dir(cms_radice() . '/install');
    $installWeb = $install ? sicurezza_autoverifica('/install/') : 0;
    $v[] = ['install', !$install ? 'ok' : (is_file(cms_radice() . '/install/.installato') ? ($installWeb === 200 ? 'attenzione' : 'attenzione') : 'no'), 'Installatore', !$install ? 'La cartella install e stata rimossa.' : (is_file(cms_radice() . '/install/.installato') ? 'La cartella install c\'e ancora: si auto-blocca, ma e meglio toglierla (pulsante qui sotto).' : 'La cartella install c\'e e non risulta bloccata.')];
    // temi: temi/.htaccess lo scrive il CMS da solo (includes/temi.php); qui si dice se c'è.
    $temiHt = is_file(cms_radice() . '/temi/.htaccess');
    $v[] = ['temi', $temiHt ? 'ok' : 'attenzione', 'PHP vietato dal web nella cartella dei temi', $temiHt ? 'C\'e un .htaccess in temi/.' : 'Manca temi/.htaccess (su Apache): si scrive da solo aprendo Editor, Temi. Su Nginx vale la regola in install/esempi/nginx.txt.'];
    $upl = cms_uploads_dir();
    $v[] = ['uploads', is_file($upl . '/.htaccess') ? 'ok' : 'attenzione', 'PHP spento nella cartella delle immagini', is_file($upl . '/.htaccess') ? 'C\'e un .htaccess in ' . basename($upl) . '/.' : 'Manca uploads/.htaccess (su Apache). Il pulsante qui sotto lo scrive; su Nginx vale la regola in INSTALLA.md.'];
    sicurezza_schema();
    $admin = db()->query('SELECT COUNT(*) FROM ' . tbl('users') . " WHERE role = 'admin'")->fetchColumn();
    $admin2fa = db()->query('SELECT COUNT(*) FROM ' . tbl('users') . " WHERE role = 'admin' AND totp_attivo = 1")->fetchColumn();
    $v[] = ['2fa', $admin2fa == $admin ? 'ok' : ($admin2fa > 0 ? 'attenzione' : 'no'), "Secondo fattore degli amministratori: $admin2fa su $admin", $cfg['totp_admin'] ? 'Obbligatorio per gli amministratori.' : 'Facoltativo. Ogni utente lo attiva da "Il mio accesso"; qui sotto puoi renderlo obbligatorio.'];
    $v[] = ['ip', $cfg['ip_attivo'] ? 'ok' : 'info', 'IP consentiti per il pannello', $cfg['ip_attivo'] ? 'Attiva: entra solo chi viene dagli indirizzi in elenco.' : 'Spenta: il pannello risponde da qualunque indirizzo (protetto da password, limite ai tentativi ed eventuale 2FA).'];
    if (sicurezza_spento()) $v[] = ['spegni', 'no', 'CMS_SICUREZZA_SPEGNI attivo', 'In config.local.php c\'e la riga di emergenza che spegne 2FA e lista IP. Toglila appena sei rientrato.'];
    $falliti = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('sicurezza_log') . " WHERE evento IN ('login_fallito','login_bloccato') AND creato > DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
    $v[] = ['falliti', $falliti > 20 ? 'attenzione' : 'info', "Accessi falliti nelle ultime 24 ore: $falliti", $falliti > 20 ? 'Molti tentativi: qualcuno prova le password. Il blocco crescente li rallenta; con la lista IP li fermi del tutto.' : 'Nella norma.'];
    $blocchi = count(sicurezza_blocchi_attivi());
    $v[] = ['blocchi', 'info', "Blocchi attivi: $blocchi", $blocchi ? 'Vedi la scheda Registro per sbloccare.' : 'Nessuno.'];
    $v[] = ['firma', $cfg['firma_pacchetti'] ? 'ok' : 'info', 'Firma dei pacchetti di aggiornamento', $cfg['firma_pacchetti'] ? 'Obbligatoria: un pacchetto senza firma valida viene rifiutato.' : 'Facoltativa: se un pacchetto porta la firma viene verificata, altrimenti passa.'];
    $v[] = ['versione', 'info', 'Versione del CMS: ' . cms_versione(), 'Dal pannello Aggiornamenti si applicano le versioni nuove.'];
    return $v;
}
