<?php
/**
 * Addon AI collegate (2.32.0): il motore comune. Tabelle, permessi, token (sempre e solo come impronta), registro, limiti di
 * velocità, testi puliti. Lo usano il server MCP (mcp.php), l'autorizzazione OAuth (oauth.php), gli strumenti
 * (strumenti.php), le foto (foto.php), le proposte (proposte.php) e le pagine del pannello. Manuale tecnico, capitolo 47.
 *
 * Regola di Danilo: un'AI collegata crea solo bozze; non esiste nessun modo per farle pubblicare. Quello che tocca un
 * contenuto pubblicato o la libreria dei Media diventa una proposta che l'amministratore approva nel pannello.
 */

/** I permessi che un'AI può chiedere. Mai: utenti, password, aggiornamenti, copie, codice, temi, sicurezza, pubblicare. */
const AIC_PERMESSI = [
    'leggi' => 'Leggere contenuti, menu, impostazioni SEO, sedi, zone e il catalogo dei widget',
    'bozze' => 'Creare e modificare bozze (mai pubblicarle)',
    'proposte' => 'Proporre modifiche a contenuti pubblicati e alla libreria dei Media (le approvi tu)',
    'foto' => 'Cercare foto con licenza libera e importarle nei Media',
    'statistiche' => 'Leggere le statistiche delle visite (solo con l\'addon Statistiche acceso)',
];
/** Quelli proposti di partenza nella pagina del consenso e nelle chiavi personali. */
const AIC_PERMESSI_PARTENZA = ['leggi', 'bozze', 'proposte', 'foto'];
/** Le versioni del protocollo MCP che il server parla (la prima è la più recente). */
const AIC_MCP_VERSIONI = ['2025-11-25', '2025-06-18', '2025-03-26'];
/** Durate: codice monouso, token di accesso, token di rinnovo, richiesta di autorizzazione in attesa. */
const AIC_CODICE_SECONDI = 600;
const AIC_ACCESSO_SECONDI = 3600;
const AIC_RINNOVO_GIORNI = 30;
const AIC_RICHIESTA_SECONDI = 900;
/** Limiti: chiamate agli strumenti per connessione e per IP al minuto, registrazioni per IP al giorno, token per IP all'ora. */
const AIC_LIMITE_STRUMENTI = 60;
const AIC_LIMITE_STRUMENTI_IP = 240;
const AIC_LIMITE_REGISTRAZIONI = 20;
const AIC_LIMITE_TOKEN = 120;
/** Corpo massimo di una richiesta al server MCP (byte) e degli alberi dell'editor che arrivano da un'AI. */
const AIC_CORPO_MAX = 1048576;
const AIC_ALBERO_MAX_WIDGET = 200;
const AIC_ALBERO_MAX_SEZIONI = 60;
const AIC_ALBERO_MAX_BYTE = 400000;
/** Le proposte in attesa che una connessione può lasciare al massimo (oltre, si aspetta che l'amministratore decida). */
const AIC_PROPOSTE_MAX = 200;
/** 2.33.0: le proposte di una scheda dell'articolo in una chiamata sola (propose_article_changes). */
const AIC_SCHEDA_MAX = 20;
/** I widget che un'AI non può usare: codice grezzo e contenuti di siti esterni. */
const AIC_WIDGET_VIETATI = ['html', 'contenuto_esterno'];

/** Un errore dello strumento, con il messaggio da dare all'AI (in italiano, con cosa fare). */
class AicErrore extends RuntimeException {
    public int $http;
    public function __construct(string $msg, int $http = 400) { parent::__construct($msg); $this->http = $http; }
}

// ------------------------------------------------------------
// Tabelle (nate al primo uso, provate in modalità SQL stretta)
// ------------------------------------------------------------

function aic_tabelle(): array {
    $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8';
    return [
        'ai_client' => 'CREATE TABLE IF NOT EXISTS ' . tbl('ai_client') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `client_id` VARCHAR(64) NOT NULL,
            `segreto_hash` CHAR(64) NOT NULL DEFAULT '',
            `nome` VARCHAR(200) NOT NULL DEFAULT '',
            `sito` VARCHAR(300) NOT NULL DEFAULT '',
            `redirect_uris` TEXT NOT NULL,
            `ip_hash` CHAR(64) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `client` (`client_id`)
        ) $e",
        'ai_richieste' => 'CREATE TABLE IF NOT EXISTS ' . tbl('ai_richieste') . " (
            `id` CHAR(32) NOT NULL,
            `dati` TEXT NOT NULL,
            `scade` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `scade` (`scade`)
        ) $e",
        'ai_codici' => 'CREATE TABLE IF NOT EXISTS ' . tbl('ai_codici') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `hash` CHAR(64) NOT NULL,
            `client_id` VARCHAR(64) NOT NULL,
            `utente_id` INT(11) NOT NULL,
            `redirect_uri` TEXT NOT NULL,
            `scope` VARCHAR(200) NOT NULL DEFAULT '',
            `challenge` VARCHAR(128) NOT NULL,
            `resource` VARCHAR(500) NOT NULL DEFAULT '',
            `connessione_id` INT(11) NOT NULL DEFAULT 0,
            `scade` DATETIME NOT NULL,
            `usato` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `hash` (`hash`), KEY `scade` (`scade`)
        ) $e",
        'ai_connessioni' => 'CREATE TABLE IF NOT EXISTS ' . tbl('ai_connessioni') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tipo` VARCHAR(10) NOT NULL DEFAULT 'oauth',
            `client_id` VARCHAR(64) NOT NULL DEFAULT '',
            `nome` VARCHAR(200) NOT NULL DEFAULT '',
            `utente_id` INT(11) NOT NULL DEFAULT 0,
            `scope` VARCHAR(200) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `ultimo` DATETIME DEFAULT NULL,
            `revocato` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`), KEY `client` (`client_id`)
        ) $e",
        'ai_token' => 'CREATE TABLE IF NOT EXISTS ' . tbl('ai_token') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `connessione_id` INT(11) NOT NULL,
            `tipo` VARCHAR(10) NOT NULL,
            `hash` CHAR(64) NOT NULL,
            `audience` VARCHAR(500) NOT NULL DEFAULT '',
            `scade` DATETIME DEFAULT NULL,
            `usato` DATETIME DEFAULT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `hash` (`hash`), KEY `connessione` (`connessione_id`)
        ) $e",
        'ai_registro' => 'CREATE TABLE IF NOT EXISTS ' . tbl('ai_registro') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `connessione_id` INT(11) NOT NULL DEFAULT 0,
            `client` VARCHAR(200) NOT NULL DEFAULT '',
            `strumento` VARCHAR(60) NOT NULL DEFAULT '',
            `tipo` VARCHAR(20) NOT NULL DEFAULT '',
            `contenuto_id` INT(11) NOT NULL DEFAULT 0,
            `esito` VARCHAR(10) NOT NULL DEFAULT 'ok',
            `dettagli` VARCHAR(500) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `creato` (`creato`), KEY `connessione` (`connessione_id`)
        ) $e",
        'ai_proposte' => 'CREATE TABLE IF NOT EXISTS ' . tbl('ai_proposte') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `connessione_id` INT(11) NOT NULL DEFAULT 0,
            `client` VARCHAR(200) NOT NULL DEFAULT '',
            `tipo` VARCHAR(20) NOT NULL,
            `contenuto_id` INT(11) NOT NULL DEFAULT 0,
            `media_url` VARCHAR(500) NOT NULL DEFAULT '',
            `campo` VARCHAR(40) NOT NULL,
            `widget_id` VARCHAR(20) NOT NULL DEFAULT '',
            `prima` LONGTEXT,
            `dopo` LONGTEXT,
            `motivo` TEXT,
            `stato` VARCHAR(12) NOT NULL DEFAULT 'attesa',
            `nota` VARCHAR(500) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `deciso` DATETIME DEFAULT NULL,
            `deciso_da` VARCHAR(100) NOT NULL DEFAULT '',
            `gruppo` VARCHAR(60) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`), KEY `stato` (`stato`, `creato`), KEY `gruppo` (`gruppo`)
        ) $e",
        'ai_foto' => 'CREATE TABLE IF NOT EXISTS ' . tbl('ai_foto') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `url` VARCHAR(500) NOT NULL,
            `archivio` VARCHAR(20) NOT NULL,
            `foto_id` VARCHAR(100) NOT NULL DEFAULT '',
            `titolo` VARCHAR(300) NOT NULL DEFAULT '',
            `autore` VARCHAR(200) NOT NULL DEFAULT '',
            `autore_url` VARCHAR(500) NOT NULL DEFAULT '',
            `licenza` VARCHAR(60) NOT NULL DEFAULT '',
            `licenza_url` VARCHAR(500) NOT NULL DEFAULT '',
            `fonte_url` VARCHAR(500) NOT NULL DEFAULT '',
            `connessione_id` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `url` (`url`(191))
        ) $e",
    ];
}

function aic_installa(): string {
    foreach (aic_tabelle() as $sql) db()->exec($sql);
    // 2.33.0: la colonna "gruppo" delle proposte (le schede degli articoli: più proposte legate da un codice) sulle tabelle nate prima.
    $cols = array_column(db()->query('SHOW COLUMNS FROM ' . tbl('ai_proposte'))->fetchAll(), 'Field');
    if (!in_array('gruppo', $cols, true)) db()->exec('ALTER TABLE ' . tbl('ai_proposte') . " ADD COLUMN `gruppo` VARCHAR(60) NOT NULL DEFAULT '', ADD KEY `gruppo` (`gruppo`)");
    save_setting('aic_schema_impronta', md5(serialize(aic_tabelle()) . DB_NAME));
    return 'tabelle a posto';
}

function aic_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    if (get_setting('aic_schema_impronta', '') !== md5(serialize(aic_tabelle()) . DB_NAME)) aic_installa();
    $fatto = true;
}

// ------------------------------------------------------------
// Indirizzi
// ------------------------------------------------------------

/** L'emittente OAuth e la base di ogni indirizzo: l'indirizzo del sito, con la cartella se c'è. */
function aic_issuer(): string {
    return cms_url_sito();
}

/** L'indirizzo del server MCP: quello da incollare nella tua AI. È anche la "risorsa" (RFC 8707) a cui sono legati i token. */
function aic_url_mcp(): string {
    return aic_issuer() . '/api/mcp';
}

/** Lo stesso indirizzo scritto in modo equivalente (barra finale): vale come la risorsa. */
function aic_risorsa_valida(string $r): bool {
    return rtrim($r, '/') === aic_url_mcp();
}

function aic_url_metadati_risorsa(): string {
    return aic_issuer() . '/.well-known/oauth-protected-resource';
}

// ------------------------------------------------------------
// Impostazioni (le chiavi degli archivi di foto non escono mai: solo "salvata" sì o no)
// ------------------------------------------------------------

function aic_impostazioni(): array {
    $s = json_decode((string)get_setting('aic_impostazioni', ''), true);
    return (is_array($s) ? $s : []) + ['pexels' => '', 'unsplash' => ''];
}

function aic_impostazioni_salva(array $v): void {
    $s = array_intersect_key($v + aic_impostazioni(), ['pexels' => 1, 'unsplash' => 1]);
    foreach ($s as $k => $x) $s[$k] = mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$x), 0, 200);
    save_setting('aic_impostazioni', json_encode($s));
}

// ------------------------------------------------------------
// Testi che arrivano da un'AI: sono dati, mai istruzioni, mai codice
// ------------------------------------------------------------

/** Testo semplice: niente tag, niente caratteri di controllo, niente caratteri a 4 byte, una riga se $righe è falso. */
function aic_testo($s, int $max = 500, bool $righe = false): string {
    $s = is_scalar($s) ? mb_scrub((string)$s, 'UTF-8') : '';
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = strip_tags($s); // un tag scritto come entità (&lt;script&gt;) torna tag dopo la decodifica: via anche lui
    $s = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
    $s = (string)preg_replace($righe ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]+/', $righe ? '' : ' ', $s);
    if (!$righe) $s = (string)preg_replace('/\s+/u', ' ', $s);
    return mb_substr(trim($s), 0, $max);
}

/** HTML di un testo lungo (classico, o il testo di un widget Testo): dagli stessi sanificatori del redattore, con le emoji in entità. */
function aic_html($s, int $max = 60000): string {
    $s = is_scalar($s) ? mb_scrub((string)$s, 'UTF-8') : '';
    require_once cms_radice() . '/includes/sicurezza-html.php';
    $s = sicurezza_html_pulito(mb_substr($s, 0, $max), 'articolo');
    return function_exists('sito_senza_4byte') ? sito_senza_4byte($s) : (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
}

function aic_slug($s): string {
    return mb_substr(contenuti_slug(aic_testo($s, 200)), 0, 190);
}

// ------------------------------------------------------------
// Token: casuali, mostrati una volta, salvati solo come impronta legata al sito
// ------------------------------------------------------------

function aic_token_nuovo(string $prefisso): string {
    return $prefisso . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

/** L'impronta di un token: HMAC con il segreto del sito. Lo stesso token su un altro sito ha un'altra impronta. */
function aic_hash(string $token): string {
    if (!function_exists('moduli_segreto')) require_once cms_radice() . '/includes/moduli.php';
    return hash_hmac('sha256', $token, 'aic|' . moduli_segreto());
}

function aic_scope_pulito($s, array $ammessi = []): array {
    $ammessi = $ammessi ?: array_keys(AIC_PERMESSI);
    $out = [];
    foreach (preg_split('/[\s,]+/', is_array($s) ? implode(' ', $s) : (string)$s) as $x) if (in_array($x, $ammessi, true) && !in_array($x, $out, true)) $out[] = $x;
    return $out;
}

/** Crea una connessione (oauth o chiave) e ritorna l'id. */
function aic_connessione_crea(string $tipo, string $clientId, string $nome, int $utente, array $scope): int {
    aic_schema();
    db()->prepare('INSERT INTO ' . tbl('ai_connessioni') . ' (tipo, client_id, nome, utente_id, scope, creato) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$tipo, $clientId, mb_substr($nome, 0, 200), $utente, implode(' ', $scope)]);
    return (int)db()->lastInsertId();
}

/** Un token per una connessione. Ritorna il token in chiaro (l'unica volta che esiste in chiaro). */
function aic_token_crea(int $connessione, string $tipo, ?int $secondi): string {
    $pref = ['accesso' => 'pca_', 'rinnovo' => 'pcr_', 'chiave' => 'pck_'][$tipo];
    $t = aic_token_nuovo($pref);
    db()->prepare('INSERT INTO ' . tbl('ai_token') . ' (connessione_id, tipo, hash, audience, scade, creato) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$connessione, $tipo, aic_hash($t), aic_url_mcp(), $secondi === null ? null : date('Y-m-d H:i:s', time() + $secondi)]);
    return $t;
}

/** Il token che arriva nell'intestazione Authorization (anche quando Apache la passa come REDIRECT_HTTP_AUTHORIZATION). */
function aic_bearer(): string {
    $h = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($h === '' && function_exists('getallheaders')) foreach ((array)getallheaders() as $k => $v) if (strtolower((string)$k) === 'authorization') $h = (string)$v;
    return preg_match('/^Bearer\s+([A-Za-z0-9._~+\/=-]{20,200})$/i', trim($h), $m) ? $m[1] : '';
}

/**
 * La connessione del token di accesso o della chiave personale: null se manca, è scaduto, revocato o di un'altra risorsa.
 * Ritorna la riga della connessione con 'permessi' (array) e 'nome_client'.
 */
function aic_autentica(string $token): ?array {
    if ($token === '' || !preg_match('/^pc[ak]_/', $token)) return null;
    aic_schema();
    $st = db()->prepare('SELECT t.id AS token_id, t.tipo AS token_tipo, t.audience, t.scade, c.* FROM ' . tbl('ai_token') . ' t JOIN ' . tbl('ai_connessioni') . ' c ON c.id = t.connessione_id
        WHERE t.hash = ? AND t.tipo IN (\'accesso\', \'chiave\') AND c.revocato IS NULL');
    $st->execute([aic_hash($token)]);
    $r = $st->fetch();
    if (!$r) return null;
    if ($r['scade'] !== null && strtotime((string)$r['scade']) < time()) return null;
    if (!aic_risorsa_valida((string)$r['audience'])) return null; // un token dato per un altro indirizzo (un altro sito, un'altra cartella)
    $r['permessi'] = aic_scope_pulito((string)$r['scope']);
    if (empty($r['ultimo']) || strtotime((string)$r['ultimo']) < time() - 60) db()->prepare('UPDATE ' . tbl('ai_connessioni') . ' SET ultimo = NOW() WHERE id = ?')->execute([(int)$r['id']]);
    return $r;
}

function aic_connessione_revoca(int $id): bool {
    aic_schema();
    $st = db()->prepare('UPDATE ' . tbl('ai_connessioni') . ' SET revocato = NOW() WHERE id = ? AND revocato IS NULL');
    $st->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('ai_token') . ' WHERE connessione_id = ?')->execute([$id]);
    return $st->rowCount() > 0;
}

/** Le connessioni (oauth e chiavi), le più recenti prima, con il nome dell'utente che ha dato il permesso. */
function aic_connessioni(bool $tutte = false): array {
    aic_schema();
    return db()->query('SELECT c.*, u.username FROM ' . tbl('ai_connessioni') . ' c LEFT JOIN ' . tbl('users') . ' u ON u.id = c.utente_id'
        . ($tutte ? '' : ' WHERE c.revocato IS NULL') . ' ORDER BY c.id DESC LIMIT 300')->fetchAll();
}

/** Pulizia: richieste e codici scaduti, token scaduti da più di un giorno, registro più vecchio di sei mesi, client mai usati. */
function aic_pulisci(): void {
    aic_schema();
    db()->exec('DELETE FROM ' . tbl('ai_richieste') . ' WHERE scade < NOW()');
    db()->exec('DELETE FROM ' . tbl('ai_codici') . ' WHERE scade < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    db()->exec('DELETE FROM ' . tbl('ai_token') . ' WHERE scade IS NOT NULL AND scade < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    db()->exec('DELETE FROM ' . tbl('ai_registro') . ' WHERE creato < DATE_SUB(NOW(), INTERVAL 180 DAY)');
    // I client registrati da più di sette giorni che non hanno mai avuto un collegamento né un codice: registrazioni lasciate a metà.
    db()->exec('DELETE c FROM ' . tbl('ai_client') . ' c LEFT JOIN ' . tbl('ai_connessioni') . ' n ON n.client_id = c.client_id LEFT JOIN ' . tbl('ai_codici') . ' k ON k.client_id = c.client_id
        WHERE c.creato < DATE_SUB(NOW(), INTERVAL 7 DAY) AND n.id IS NULL AND k.id IS NULL');
}

// ------------------------------------------------------------
// Registro e limiti
// ------------------------------------------------------------

function aic_registra(?array $conn, string $strumento, string $esito, string $dettagli = '', string $tipo = '', int $id = 0): void {
    try {
        aic_schema();
        db()->prepare('INSERT INTO ' . tbl('ai_registro') . ' (connessione_id, client, strumento, tipo, contenuto_id, esito, dettagli, creato) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([(int)($conn['id'] ?? 0), mb_substr((string)($conn['nome'] ?? ''), 0, 200), mb_substr($strumento, 0, 60), mb_substr($tipo, 0, 20), $id, $esito, mb_substr(aic_testo($dettagli, 500), 0, 500)]);
    } catch (Throwable $e) {
        error_log('aic_registra: ' . $e->getMessage());
    }
}

/** Dentro il limite? (sicurezza_limite del nucleo: una riga per chiave nella tabella dei tentativi). */
function aic_limite(string $chiave, int $max, int $secondi): bool {
    return sicurezza_limite('aic:' . $chiave, $max, $secondi);
}

function aic_ip_hash(): string {
    return hash('sha256', 'aic-ip|' . sicurezza_ip() . '|' . (function_exists('moduli_segreto') ? moduli_segreto() : ''));
}

/** Le proposte in attesa (il numero nel menu del pannello). Mai un errore da qui. */
function aic_proposte_in_attesa(): int {
    try {
        aic_schema();
        return (int)db()->query('SELECT COUNT(*) FROM ' . tbl('ai_proposte') . " WHERE stato = 'attesa'")->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
