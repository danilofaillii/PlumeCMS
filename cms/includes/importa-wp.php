<?php
/**
 * Import da WordPress (1.4.0): la base. Chiamate in uscita verso il sito WordPress, credenziali cifrate, collegamento con
 * i messaggi d'errore in parole semplici, stato del lavoro, tabella delle voci importate.
 *
 * Il percorso intero (pagina Impostazioni > Importa da WordPress, admin/importa-wordpress.php):
 *   1. collegamento: indirizzo, utente, password per le applicazioni (importa_wp_collega);
 *   2. analisi: il CMS legge tutto il sito con la REST (wp-json/wp/v2), a giri brevi, e scrive il report (importa-wp-analisi.php);
 *   3. scelta delle voci; 4. import a giri, dopo una copia del sito (importa-wp-lavoro.php, importa-wp-converti.php);
 *   5. tutto entra "in prova": bozze invisibili ai visitatori, visibili nell'anteprima dell'amministratore;
 *   6. Conferma (pubblica come era su WordPress) oppure Annulla (toglie tutto quello che è entrato).
 *
 * Sicurezza:
 * - le chiamate vanno solo all'host dato (stesso host e porta; da http a https sullo stesso host si'), mai seguendo un rimando
 *   verso un altro host; solo le porte 80 e 443; l'indirizzo IP si risolve una volta, si controlla che non sia interno o privato
 *   e la connessione si fissa su quell'IP (CURLOPT_RESOLVE: niente DNS rebinding); certificato HTTPS verificato; tempi e pesi
 *   limitati; nessun proxy;
 * - le credenziali viaggiano solo in HTTPS (http solo per gli host della costante delle prove);
 * - la password per le applicazioni si tiene solo cifrata (sodium secretbox, chiave da CMS_SEGRETO) per la durata del
 *   lavoro, poi si cancella; non compare mai in pagine, log o messaggi.
 *
 * Solo per le prove in locale: CMS_IMPORTA_WP_LOCALE nel config.local.php, un elenco "host:porta" separato da virgole
 * (per esempio '127.0.0.1:8071'), toglie il controllo degli indirizzi interni e ammette http per quegli host e porte
 * esatti, nessun altro. Su un sito vero non va mai definita (la pagina Sicurezza lo direbbe con il registro).
 */

require_once __DIR__ . '/sicurezza-html.php';

const IMPORTA_WP_SCHEMA = '1';
const IMPORTA_WP_TIMEOUT = 20;              // secondi per una risposta JSON
const IMPORTA_WP_TIMEOUT_FILE = 60;         // secondi per un file
const IMPORTA_WP_MAX_JSON = 8388608;        // 8 MB per una risposta JSON
const IMPORTA_WP_MAX_FILE = 26214400;       // 25 MB per un file
const IMPORTA_WP_GIRO = 8;                  // secondi di lavoro per giro: le richieste restano brevi anche su un hosting condiviso
const IMPORTA_WP_SCADENZA = 86400;          // dopo un giorno senza lavoro la password si dimentica
const IMPORTA_WP_REDIRECT = 3;              // rimandi seguiti, solo sullo stesso host

/** I secondi di un giro: IMPORTA_WP_GIRO, oppure CMS_IMPORTA_WP_GIRO dal config.local.php (solo per le prove: giri corti per provare la ripresa). */
function importa_wp_giro(): float {
    return defined('CMS_IMPORTA_WP_GIRO') ? max(0.2, (float)CMS_IMPORTA_WP_GIRO) : (float)IMPORTA_WP_GIRO;
}

/** Un errore da mostrare com'è a chi usa il pannello. $tipo: dns, irraggiungibile, lento, certificato, interno, altro_host, rest_chiusa, ... */
class ImportaWpErrore extends RuntimeException {
    public string $tipo;
    public function __construct(string $messaggio, string $tipo = 'altro') { parent::__construct($messaggio); $this->tipo = $tipo; }
}

// ------------------------------------------------------------
// Tabella delle voci importate
// ------------------------------------------------------------

/** Una riga per ogni cosa entrata (contenuto, categoria, tag, file, menu, redirect): serve a Conferma e ad Annulla. */
function importa_wp_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    if (get_setting('importa_wp_schema', '') === IMPORTA_WP_SCHEMA) return;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('importa_wp_voci') . ' (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `lavoro` VARCHAR(32) NOT NULL,
        `genere` VARCHAR(20) NOT NULL,
        `wp_id` INT(11) NOT NULL DEFAULT 0,
        `wp_rif` VARCHAR(500) NOT NULL DEFAULT \'\',
        `plume_id` INT(11) NOT NULL DEFAULT 0,
        `plume_rif` VARCHAR(500) NOT NULL DEFAULT \'\',
        `stato_finale` VARCHAR(20) NOT NULL DEFAULT \'\',
        `creato` DATETIME NOT NULL,
        PRIMARY KEY (`id`), KEY `voce` (`lavoro`, `genere`, `wp_id`), KEY `rif` (`lavoro`, `wp_rif`(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    save_setting('importa_wp_schema', IMPORTA_WP_SCHEMA);
}

function importa_wp_voce_aggiungi(string $lavoro, string $genere, int $wpId, string $wpRif, int $plumeId, string $plumeRif, string $statoFinale = ''): void {
    db()->prepare('INSERT INTO ' . tbl('importa_wp_voci') . ' (lavoro, genere, wp_id, wp_rif, plume_id, plume_rif, stato_finale, creato) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$lavoro, $genere, $wpId, mb_substr($wpRif, 0, 500), $plumeId, mb_substr($plumeRif, 0, 500), $statoFinale]);
}

/** La voce già entrata per questo id di WordPress (o per questo indirizzo), oppure null. Rende ogni giro ripetibile senza doppioni. */
function importa_wp_voce(string $lavoro, string $genere, int $wpId = 0, string $wpRif = ''): ?array {
    if ($wpId) {
        $st = db()->prepare('SELECT * FROM ' . tbl('importa_wp_voci') . ' WHERE lavoro = ? AND genere = ? AND wp_id = ? LIMIT 1');
        $st->execute([$lavoro, $genere, $wpId]);
    } else {
        $st = db()->prepare('SELECT * FROM ' . tbl('importa_wp_voci') . ' WHERE lavoro = ? AND genere = ? AND wp_rif = ? LIMIT 1');
        $st->execute([$lavoro, $genere, $wpRif]);
    }
    return $st->fetch() ?: null;
}

function importa_wp_voci(string $lavoro, string $genere = ''): array {
    $st = db()->prepare('SELECT * FROM ' . tbl('importa_wp_voci') . ' WHERE lavoro = ?' . ($genere !== '' ? ' AND genere = ?' : '') . ' ORDER BY id');
    $st->execute($genere !== '' ? [$lavoro, $genere] : [$lavoro]);
    return $st->fetchAll();
}

// ------------------------------------------------------------
// Stato del lavoro
// ------------------------------------------------------------

/** Lo stato: un JSON nell'impostazione importa_wp_stato. Vuoto: nessun import in corso. */
function importa_wp_stato(): array {
    $s = json_decode((string)get_setting('importa_wp_stato', ''), true);
    return is_array($s) ? $s : [];
}

function importa_wp_stato_salva(array $s): void {
    $s['toccato'] = time();
    // Senza JSON_UNESCAPED_UNICODE: tutto in ASCII (\uXXXX), anche le emoji, qualunque sia il charset della tabella.
    save_setting('importa_wp_stato', (string)json_encode($s, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
}

/** Toglie la password dallo stato (a lavoro finito, annullato, o fermo da più di un giorno). */
function importa_wp_dimentica_password(array &$s): void {
    unset($s['segreto']);
}

/** Un lavoro fermo da più di IMPORTA_WP_SCADENZA: la password si dimentica (per riprendere si riscrive). */
function importa_wp_scadenza(): void {
    $s = importa_wp_stato();
    if (isset($s['segreto']) && (int)($s['toccato'] ?? 0) < time() - IMPORTA_WP_SCADENZA) {
        importa_wp_dimentica_password($s);
        importa_wp_stato_salva($s);
    }
}

// ------------------------------------------------------------
// Credenziali cifrate
// ------------------------------------------------------------

/** La chiave: da CMS_SEGRETO (scritto dall'installatore); senza, una chiave a caso nella sessione (si riprende solo nella stessa sessione). */
function importa_wp_chiave(): string {
    if (defined('CMS_SEGRETO') && (string)CMS_SEGRETO !== '') return hash('sha256', 'importa-wp|' . CMS_SEGRETO, true);
    if (empty($_SESSION['importa_wp_chiave'])) $_SESSION['importa_wp_chiave'] = base64_encode(random_bytes(32));
    return base64_decode($_SESSION['importa_wp_chiave']);
}

function importa_wp_cifra(string $testo): string {
    if (!function_exists('sodium_crypto_secretbox')) throw new ImportaWpErrore('Sul server manca la libreria sodium di PHP: serve per tenere cifrata la password durante l\'import.');
    $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'v1:' . base64_encode($n . sodium_crypto_secretbox($testo, $n, importa_wp_chiave()));
}

/** La password in chiaro, solo in memoria e solo per le chiamate; null se non si decifra (chiave cambiata, sessione nuova). */
function importa_wp_decifra(string $c): ?string {
    if (!str_starts_with($c, 'v1:') || !function_exists('sodium_crypto_secretbox_open')) return null;
    $b = base64_decode(substr($c, 3), true);
    if ($b === false || strlen($b) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
    $p = @sodium_crypto_secretbox_open(substr($b, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($b, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), importa_wp_chiave());
    return $p === false ? null : $p;
}

/** La password per le applicazioni come WordPress la mostra ("abcd efgh ijkl mnop qrst uvwx"): via gli spazi. */
function importa_wp_password_pulita(string $p): string {
    return preg_replace('/\s+/', '', trim($p)) ?? '';
}

/** Ha la forma di una password per le applicazioni? 24 lettere e cifre (WordPress le mostra in sei gruppi di quattro). */
function importa_wp_password_per_app(string $p): bool {
    return (bool)preg_match('/^[A-Za-z0-9]{24}$/', importa_wp_password_pulita($p));
}

// ------------------------------------------------------------
// Indirizzi e controllo SSRF
// ------------------------------------------------------------

/** Gli host:porta delle prove in locale (CMS_IMPORTA_WP_LOCALE), in minuscolo. */
function importa_wp_locali(): array {
    if (!defined('CMS_IMPORTA_WP_LOCALE')) return [];
    return array_values(array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', (string)CMS_IMPORTA_WP_LOCALE))));
}

/** Schema, host e porta di un indirizzo, con la porta predefinita dello schema. */
function importa_wp_origine(string $url): ?array {
    $p = parse_url($url);
    if (!is_array($p) || empty($p['host'])) return null;
    $schema = strtolower((string)($p['scheme'] ?? ''));
    if (!in_array($schema, ['http', 'https'], true)) return null;
    $host = strtolower(trim($p['host'], '[]'));
    return ['schema' => $schema, 'host' => $host, 'porta' => (int)($p['port'] ?? ($schema === 'https' ? 443 : 80)), 'utente' => isset($p['user']) || isset($p['pass'])];
}

/** L'host e una delle eccezioni delle prove in locale? */
function importa_wp_e_locale(array $o): bool {
    return in_array($o['host'] . ':' . $o['porta'], importa_wp_locali(), true);
}

/**
 * Sullo stesso sito del WordPress dato? Stesso host e stessa porta, oppure il passaggio da http a https sullo stesso host
 * con le porte predefinite. Mai da https a http.
 */
function importa_wp_stesso_sito(string $url, string $base): bool {
    $a = importa_wp_origine($base); $b = importa_wp_origine($url);
    if (!$a || !$b || $b['utente'] || $a['host'] !== $b['host']) return false;
    if ($a['schema'] === $b['schema']) return $a['porta'] === $b['porta'];
    return $a['schema'] === 'http' && $b['schema'] === 'https' && $a['porta'] === 80 && $b['porta'] === 443;
}

/** Un IP interno, privato, riservato o della rete condivisa dei provider (100.64.0.0/10)? */
function importa_wp_ip_interno(string $ip): bool {
    if (sicurezza_ip_privato($ip)) return true;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['0.0.0.0', 8], ['192.0.0.0', 24], ['198.18.0.0', 15]] as [$rete, $bit]) {
            if (($n & (-1 << (32 - $bit))) === (ip2long($rete) & (-1 << (32 - $bit)))) return true;
        }
    }
    return false;
}

/**
 * Controlla un indirizzo prima di chiamarlo e ritorna [origine, ip]. Lancia ImportaWpErrore con il motivo in parole semplici.
 */
function importa_wp_controlla(string $url): array {
    $o = importa_wp_origine($url);
    if (!$o) throw new ImportaWpErrore('L\'indirizzo non è valido: scrivilo completo, per esempio https://www.miosito.it', 'indirizzo');
    if ($o['utente']) throw new ImportaWpErrore('L\'indirizzo non può contenere nome e password: scrivi solo l\'indirizzo del sito.', 'indirizzo');
    $locale = importa_wp_e_locale($o);
    if (!$locale && !in_array($o['porta'], [80, 443], true)) throw new ImportaWpErrore('Si importa solo da siti sulle porte normali del web (80 e 443): togli il ":' . $o['porta'] . '" dall\'indirizzo.', 'porta');
    if (filter_var($o['host'], FILTER_VALIDATE_IP)) $ips = [$o['host']];
    else {
        if (!preg_match('/^[a-z0-9.-]+$/', $o['host'])) throw new ImportaWpErrore('Il nome del sito contiene caratteri non ammessi.', 'indirizzo');
        $ips = @gethostbynamel($o['host']) ?: [];
        if (!$ips) throw new ImportaWpErrore('Il sito ' . $o['host'] . ' non esiste o non si trova: controlla di aver scritto bene l\'indirizzo.', 'dns');
    }
    if (!$locale) foreach ($ips as $ip) {
        if (importa_wp_ip_interno($ip)) throw new ImportaWpErrore('L\'indirizzo porta a una rete interna (' . $ip . '): per sicurezza si importa solo da siti pubblici su internet.', 'interno');
    }
    return [$o, $ips[0]];
}

// ------------------------------------------------------------
// Chiamate
// ------------------------------------------------------------

/**
 * Una GET verso il WordPress. $s: lo stato (base e credenziali). $opz: auth (bool, predefinito vero), max (byte), timeout,
 * file (scrive il corpo li invece di tenerlo). Ritorna ['codice', 'header' (minuscole), 'corpo', 'json', 'url'].
 * Segue al massimo IMPORTA_WP_REDIRECT rimandi, solo sullo stesso sito. Lancia ImportaWpErrore.
 */
function importa_wp_get(array $s, string $url, array $opz = []): array {
    if (!function_exists('curl_init')) throw new ImportaWpErrore('Sul server manca l\'estensione curl di PHP: serve per leggere il sito WordPress.', 'server');
    $base = (string)$s['base'];
    $auth = $opz['auth'] ?? true;
    $max = (int)($opz['max'] ?? IMPORTA_WP_MAX_JSON);
    $timeout = (int)($opz['timeout'] ?? IMPORTA_WP_TIMEOUT);
    $password = null;
    if ($auth) {
        $password = isset($s['segreto']) ? importa_wp_decifra((string)$s['segreto']) : ($s['_password'] ?? null);
        if ($password === null) throw new ImportaWpErrore('La password per le applicazioni non c\'è più (per sicurezza si dimentica dopo un giorno, o alla chiusura del browser): riscrivila per continuare.', 'password_persa');
    }
    for ($salto = 0; $salto <= IMPORTA_WP_REDIRECT; $salto++) {
        if (!importa_wp_stesso_sito($url, $base)) throw new ImportaWpErrore('Il sito rimanda a un altro indirizzo (' . (preg_replace('~^(https?://[^/?#]+).*$~i', '$1', $url) ?: $url) . '): per sicurezza non lo seguo. Scrivi nel campo l\'indirizzo vero del sito WordPress.', 'altro_host');
        [$o, $ip] = importa_wp_controlla($url);
        $locale = importa_wp_e_locale($o);
        if ($auth && $o['schema'] !== 'https' && !$locale) throw new ImportaWpErrore('Il sito è in http, senza HTTPS: la password viaggerebbe in chiaro e WordPress stesso non accetta le password per le applicazioni senza HTTPS. Scrivi l\'indirizzo con https://', 'http');
        $header = []; $corpo = ''; $letti = 0; $troppo = false;
        $fh = isset($opz['file']) ? fopen($opz['file'], 'wb') : null;
        $c = curl_init();
        curl_setopt_array($c, [
            CURLOPT_URL => $url,
            CURLOPT_RESOLVE => [$o['host'] . ':' . $o['porta'] . ':' . (str_contains($ip, ':') ? "[$ip]" : $ip)],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => array_values(array_filter([
                'Accept: application/json, */*;q=0.5',
                'User-Agent: ' . CMS_NOME . '/' . cms_versione() . ' (importa da WordPress)',
                $auth ? 'Authorization: Basic ' . base64_encode((string)$s['utente'] . ':' . importa_wp_password_pulita((string)$password)) : null,
            ])),
            CURLOPT_HEADERFUNCTION => function ($c, $riga) use (&$header) {
                if (preg_match('~^HTTP/~', $riga)) $header = [];
                elseif (str_contains($riga, ':')) { [$k, $v] = explode(':', $riga, 2); $header[strtolower(trim($k))] = trim($v); }
                return strlen($riga);
            },
            CURLOPT_WRITEFUNCTION => function ($c, $pezzo) use (&$corpo, &$letti, &$troppo, $max, $fh) {
                $letti += strlen($pezzo);
                if ($letti > $max) { $troppo = true; return -1; }
                if ($fh) fwrite($fh, $pezzo); else $corpo .= $pezzo;
                return strlen($pezzo);
            },
        ]);
        $ok = curl_exec($c);
        $err = curl_errno($c);
        $codice = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        curl_close($c);
        if ($fh) fclose($fh);
        unset($password);
        if ($troppo) throw new ImportaWpErrore('La risposta del sito è più grande del limite (' . round($max / 1048576) . ' MB): saltata.', 'troppo_grande');
        if ($ok === false || $err) {
            throw match (true) {
                $err === CURLE_COULDNT_RESOLVE_HOST => new ImportaWpErrore('Il sito ' . $o['host'] . ' non si trova: controlla l\'indirizzo.', 'dns'),
                $err === CURLE_OPERATION_TIMEDOUT => new ImportaWpErrore('Il sito è troppo lento a rispondere: riprova fra qualche minuto.', 'lento'),
                in_array($err, [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91], true) => new ImportaWpErrore('Il certificato HTTPS del sito non è valido (scaduto, di un altro nome o fatto in casa): per sicurezza non mi collego. Sistemalo sul sito WordPress, poi riprova.', 'certificato'),
                default => new ImportaWpErrore('Il sito ' . $o['host'] . ' non risponde: è spento, l\'indirizzo è sbagliato o blocca le richieste dei server.', 'irraggiungibile'),
            };
        }
        if ($codice >= 300 && $codice < 400 && !empty($header['location'])) {
            $url = importa_wp_assoluto((string)$header['location'], $url);
            continue;
        }
        $json = null;
        if (!$fh && $corpo !== '' && str_contains((string)($header['content-type'] ?? ''), 'json')) $json = json_decode($corpo, true);
        elseif (!$fh && $corpo !== '' && ($corpo[0] === '{' || $corpo[0] === '[')) $json = json_decode($corpo, true);
        return ['codice' => $codice, 'header' => $header, 'corpo' => $corpo, 'json' => $json, 'url' => $url];
    }
    throw new ImportaWpErrore('Il sito rimanda troppe volte da un indirizzo all\'altro: fermato.', 'redirect');
}

/** Un indirizzo relativo (da un Location) reso completo rispetto a quello di partenza. */
function importa_wp_assoluto(string $u, string $da): string {
    if (preg_match('~^https?://~i', $u)) return $u;
    $p = parse_url($da);
    $orig = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($u, '//')) return $p['scheme'] . ':' . $u;
    if (str_starts_with($u, '/')) return $orig . $u;
    return $orig . rtrim(dirname($p['path'] ?? '/'), '/') . '/' . $u;
}

/** L'indirizzo di una rotta della REST: /wp-json/<rotta> oppure ?rest_route=/<rotta> (siti senza permalink belli). */
function importa_wp_api(array $s, string $rotta, array $q = []): string {
    $rotta = '/' . ltrim($rotta, '/');
    if (($s['api'] ?? 'json') === 'rest_route') return $s['base'] . '/?' . http_build_query(['rest_route' => $rotta] + $q);
    return $s['base'] . '/wp-json' . $rotta . ($q ? '?' . http_build_query($q) : '');
}

/** Una GET a una rotta che deve rispondere 200 in JSON; altrimenti l'errore, in parole semplici. */
function importa_wp_json(array $s, string $rotta, array $q = [], bool $auth = true): array {
    $r = importa_wp_get($s, importa_wp_api($s, $rotta, $q), ['auth' => $auth]);
    if ($r['codice'] === 200 && is_array($r['json'])) return $r;
    throw importa_wp_errore_risposta($r, $rotta);
}

function importa_wp_errore_risposta(array $r, string $rotta): ImportaWpErrore {
    $code = is_array($r['json']) ? (string)($r['json']['code'] ?? '') : '';
    if (in_array($code, ['rest_disabled', 'rest_api_disabled', 'rest_cannot_access', 'rest_login_required', 'rest_forbidden_context'], true) || ($r['codice'] === 403 && $code === '')) {
        return new ImportaWpErrore('La REST di WordPress è chiusa (risposta ' . $r['codice'] . ($code ? ', ' . $code : '') . '): di solito la spegne un plugin di sicurezza (per esempio "Disable REST API", Wordfence, iThemes, Perfmatters). Nel plugin, permetti la REST almeno agli utenti con le credenziali, poi riprova.', 'rest_chiusa');
    }
    if (in_array($r['codice'], [401, 403], true)) return new ImportaWpErrore('WordPress non permette all\'utente di leggere ' . $rotta . ' (risposta ' . $r['codice'] . '): serve un amministratore.', 'permessi');
    if ($r['codice'] === 404) return new ImportaWpErrore('Su questo sito manca ' . $rotta . ' (risposta 404).', 'manca');
    return new ImportaWpErrore('Il sito ha risposto in modo inatteso a ' . $rotta . ' (codice ' . $r['codice'] . ').', 'risposta');
}

/** Tutte le pagine di una rotta a elenco (per_page 100): ritorna gli elementi. Per le rotte piccole (categorie, menu). */
function importa_wp_tutti(array $s, string $rotta, array $q = [], int $limite = 50): array {
    $out = [];
    for ($p = 1; $p <= $limite; $p++) {
        $r = importa_wp_json($s, $rotta, $q + ['per_page' => 100, 'page' => $p]);
        foreach ($r['json'] as $x) if (is_array($x)) $out[] = $x;
        if ($p >= (int)($r['header']['x-wp-totalpages'] ?? 1)) break;
    }
    return $out;
}

// ------------------------------------------------------------
// Collegamento
// ------------------------------------------------------------

/** L'indirizzo scritto dall'utente, ripulito: https:// se manca, senza /wp-admin, /wp-login.php, /wp-json e barra finale. */
function importa_wp_base(string $u): string {
    $u = trim($u);
    if ($u !== '' && !preg_match('~^[a-z]+://~i', $u)) $u = 'https://' . $u;
    $u = preg_replace('~[?#].*$~', '', $u) ?? '';
    $u = preg_replace('~/(wp-admin|wp-login\.php|wp-json|index\.php)(/.*)?$~i', '', $u) ?? '';
    return rtrim($u, '/');
}

/**
 * Prova il collegamento: REST aperta, credenziali giuste, permessi. Ritorna lo stato nuovo (senza la password in chiaro).
 * Lancia ImportaWpErrore con il motivo in parole semplici.
 */
function importa_wp_collega(string $indirizzo, string $utente, string $password): array {
    $base = importa_wp_base($indirizzo);
    $utente = trim($utente);
    if ($base === '' || $utente === '' || trim($password) === '') throw new ImportaWpErrore('Scrivi indirizzo, utente e password per le applicazioni.', 'vuoto');
    importa_wp_controlla($base);
    $s = ['base' => $base, 'utente' => $utente, '_password' => $password, 'api' => 'json'];

    // 1. La REST risponde? Prima /wp-json/, poi ?rest_route=/ per i siti senza permalink belli.
    $indice = null; $chiusa = null;
    foreach (['json', 'rest_route'] as $modo) {
        $s['api'] = $modo;
        $r = importa_wp_get($s, importa_wp_api($s, '/'), ['auth' => false]);
        if ($r['codice'] === 200 && is_array($r['json']) && isset($r['json']['namespaces'])) { $indice = $r['json']; break; }
        if (in_array($r['codice'], [401, 403], true) && is_array($r['json'])) { $chiusa = $r; break; }
    }
    if ($chiusa) {
        // Alcuni plugin chiudono la REST solo a chi non ha le credenziali: si riprova con quelle.
        $r = importa_wp_get($s, importa_wp_api($s, '/'), ['auth' => true]);
        if ($r['codice'] === 200 && is_array($r['json']) && isset($r['json']['namespaces'])) $indice = $r['json'];
        else throw importa_wp_errore_risposta($r, 'la REST');
    }
    if (!$indice) throw new ImportaWpErrore('Non trovo la REST di WordPress su ' . $base . ': o non è un sito WordPress, o l\'indirizzo è sbagliato (scrivi quello della home), o un plugin ha spento la REST.', 'non_wordpress');
    if (!in_array('wp/v2', (array)($indice['namespaces'] ?? []), true)) throw new ImportaWpErrore('Il sito ha la REST ma senza i contenuti (manca wp/v2): un plugin la limita. Nel plugin di sicurezza permetti la REST agli utenti con le credenziali.', 'rest_chiusa');

    // 2. Le credenziali.
    $r = importa_wp_get($s, importa_wp_api($s, '/wp/v2/users/me', ['context' => 'edit']));
    if ($r['codice'] !== 200 || !is_array($r['json']) || empty($r['json']['id'])) {
        $code = is_array($r['json']) ? (string)($r['json']['code'] ?? '') : '';
        $appAttive = isset($indice['authentication']['application-passwords']);
        if (in_array($code, ['rest_disabled', 'rest_api_disabled', 'rest_cannot_access', 'rest_login_required'], true)) throw importa_wp_errore_risposta($r, 'la REST');
        if (!$appAttive) throw new ImportaWpErrore('Su questo WordPress le password per le applicazioni sono spente: succede se il sito non è in HTTPS o se un plugin di sicurezza le ha disattivate. Riattivale (o metti il sito in HTTPS), poi riprova.', 'app_spente');
        if (!importa_wp_password_per_app($password)) throw new ImportaWpErrore('WordPress non ha accettato la password. Questa sembra la password con cui entri in WordPress: qui serve la password per le applicazioni (24 lettere e cifre in sei gruppi di quattro), che si crea in Utenti > Profilo > Password per le applicazioni.', 'password_normale');
        if ($code === 'invalid_username') throw new ImportaWpErrore('WordPress non conosce l\'utente "' . $utente . '": scrivi il nome con cui entri nel pannello di WordPress (o la sua email).', 'utente');
        throw new ImportaWpErrore('WordPress non ha accettato utente e password per le applicazioni (risposta ' . $r['codice'] . '). Controlla il nome utente e la password, oppure crea una password nuova in Utenti > Profilo > Password per le applicazioni. Se sono giusti, il server del sito toglie le credenziali dalle richieste: serve una riga nel suo .htaccess (chiedi all\'hosting di passare l\'intestazione Authorization a PHP).', 'credenziali');
    }
    $io = $r['json'];
    $ruoli = (array)($io['roles'] ?? []);
    $cap = (array)($io['capabilities'] ?? []);
    if (!in_array('administrator', $ruoli, true) && empty($cap['edit_others_posts'])) {
        throw new ImportaWpErrore('L\'utente "' . $utente . '" non può leggere tutto il sito (ruolo: ' . (implode(', ', $ruoli) ?: 'sconosciuto') . '). Serve un amministratore di WordPress: bozze, menu e utenti li vede solo lui.', 'permessi');
    }
    unset($s['_password']);
    $s['segreto'] = importa_wp_cifra($password);
    $s['lavoro'] = bin2hex(random_bytes(8));
    $s['fase'] = 'analisi';
    $s['creato'] = time();
    $s['sito'] = [
        'nome' => html_entity_decode((string)($indice['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'descrizione' => html_entity_decode((string)($indice['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'home' => rtrim((string)($indice['home'] ?? $base), '/'), 'url' => rtrim((string)($indice['url'] ?? $base), '/'),
        'namespaces' => array_values((array)($indice['namespaces'] ?? [])), 'amministratore' => in_array('administrator', $ruoli, true),
        'utente_nome' => (string)($io['name'] ?? $utente),
    ];
    $s['analisi'] = ['passo' => 0, 'pagina' => 1];
    return $s;
}

/** Le pagine di WordPress e del CMS parlano in entita: un titolo "&amp;" torna "&". */
function importa_wp_testo(string $s): string {
    return trim(html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** Il percorso di un indirizzo del vecchio sito, rispetto alla home di WordPress (che può stare in una cartella). */
function importa_wp_percorso(array $s, string $url): string {
    $home = (string)($s['sito']['home'] ?? $s['base']);
    $p = (string)(parse_url($url, PHP_URL_PATH) ?? '/');
    $hp = rtrim((string)(parse_url($home, PHP_URL_PATH) ?? ''), '/');
    if ($hp !== '' && str_starts_with($p, $hp . '/')) $p = substr($p, strlen($hp));
    $q = (string)(parse_url($url, PHP_URL_QUERY) ?? '');
    return ($p === '' ? '/' : $p) . ($q !== '' ? '?' . $q : '');
}

/** Un indirizzo del vecchio sito? Stesso host (con o senza www) e stessa porta della home o dell'indirizzo dato (80 e 443 valgono uguale). */
function importa_wp_del_sito(array $s, string $url): bool {
    $chiave = function (string $u): string {
        $o = importa_wp_origine($u);
        if (!$o) return '';
        return preg_replace('/^www\./', '', $o['host']) . ':' . (in_array($o['porta'], [80, 443], true) ? 'web' : $o['porta']);
    };
    $k = $chiave($url);
    if ($k === '') return false;
    foreach ([$s['base'], (string)($s['sito']['home'] ?? ''), (string)($s['sito']['url'] ?? '')] as $b) if ($b !== '' && $chiave($b) === $k) return true;
    return false;
}
