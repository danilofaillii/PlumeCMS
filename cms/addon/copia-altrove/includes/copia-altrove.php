<?php
/**
 * Copia del sito altrove (addon copia-altrove, 2.0.0): il motore.
 *
 * La copia è quella di includes/copia-sito.php (copia_crea): database, uploads, temi, moduli, firmata. Si crea in
 * aggiornamenti/copie/ con l'etichetta "altrove", parte verso la destinazione e, se l'invio va bene, la copia sul server
 * si toglie (a meno di "tienila anche qui"). Sulla destinazione restano le ultime N copie "altrove" (mai altri file).
 *
 * Destinazioni: FTP e FTPS (estensione ftp di PHP), SFTP (solo se c'è l'estensione ssh2, altrimenti si dice che manca),
 * WebDAV con curl (Nextcloud, ownCloud e gli spazi cloud che parlano WebDAV); dalla 2.1 Google Drive e Dropbox con OAuth 2
 * (includes/cloud.php: collegamento, rinnovo dell'accesso, caricamento a pezzi).
 *
 * Credenziali: nell'impostazione copia_altrove_cfg; la password cifrata con sodium (secretbox), chiave derivata da
 * moduli_segreto() e da CMS_SEGRETO del config.local.php (che non entra nella copia del sito). Non si mostra mai più.
 *
 * SSRF: l'host della destinazione si risolve e si rifiuta se porta a un indirizzo interno, privato o riservato; ci si
 * collega all'IP controllato (niente seconda risoluzione). Per le prove in locale, nel config.local.php:
 *     define('CMS_COPIA_ALTROVE_LOCALE', '127.0.0.1:8205,127.0.0.1:8206');
 * Solo quelle coppie host:porta, e solo da lì: dal pannello non si può aprire nessuna eccezione.
 *
 * Registro: tabella copia_altrove_registro (ogni prova e ogni invio, con esito, peso, durata e messaggio).
 */

require_once dirname(__DIR__, 3) . '/includes/copia-sito.php';
require_once dirname(__DIR__, 3) . '/includes/moduli.php';

const COPIA_ALTROVE_TIPI = ['gdrive' => 'Google Drive', 'dropbox' => 'Dropbox', 'ftp' => 'FTP', 'ftps' => 'FTPS (FTP cifrato)', 'sftp' => 'SFTP', 'webdav' => 'WebDAV (Nextcloud e molti spazi cloud)'];
const COPIA_ALTROVE_PORTE = ['gdrive' => 443, 'dropbox' => 443, 'ftp' => 21, 'ftps' => 21, 'sftp' => 22, 'webdav' => 443];
const COPIA_ALTROVE_ETICHETTA = 'altrove';
const COPIA_ALTROVE_TIMEOUT = 30;

class CopiaAltroveErrore extends RuntimeException {}

function copia_altrove_installa(): string {
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('copia_altrove_registro') . " (
        `id` INT NOT NULL AUTO_INCREMENT,
        `quando` DATETIME NOT NULL,
        `origine` VARCHAR(12) NOT NULL DEFAULT '',
        `esito` VARCHAR(10) NOT NULL DEFAULT '',
        `tipo` VARCHAR(10) NOT NULL DEFAULT '',
        `destinazione` VARCHAR(255) NOT NULL DEFAULT '',
        `file` VARCHAR(120) NOT NULL DEFAULT '',
        `peso` BIGINT NOT NULL DEFAULT 0,
        `secondi` INT NOT NULL DEFAULT 0,
        `messaggio` TEXT,
        PRIMARY KEY (`id`), KEY `quando` (`quando`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    save_setting('copia_altrove_schema', '1');
    return 'registro degli invii a posto';
}

function copia_altrove_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    if ((string)get_setting('copia_altrove_schema', '') === '') copia_altrove_installa();
}

function copia_altrove_admin_url(array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'copia-altrove', 'p' => 'destinazione'] + $q);
}

// ------------------------------------------------------------
// Impostazioni e password cifrata
// ------------------------------------------------------------

function copia_altrove_cfg(): array {
    $c = json_decode((string)get_setting('copia_altrove_cfg', ''), true);
    $c = is_array($c) ? $c : [];
    $tipo = isset(COPIA_ALTROVE_TIPI[$c['tipo'] ?? '']) ? $c['tipo'] : 'gdrive';
    return [
        'tipo' => $tipo,
        'host' => (string)($c['host'] ?? ''),
        'porta' => (int)($c['porta'] ?? 0) ?: COPIA_ALTROVE_PORTE[$tipo],
        'utente' => (string)($c['utente'] ?? ''),
        'password' => (string)($c['password'] ?? ''),          // cifrata (v1:...)
        'cartella' => (string)($c['cartella'] ?? ''),
        'url' => (string)($c['url'] ?? ''),
        'tenere' => max(1, min(30, (int)($c['tenere'] ?? 7))),
        'attivo' => !empty($c['attivo']),
        'tieni_locale' => !empty($c['tieni_locale']),
        'sftp_impronta' => (string)($c['sftp_impronta'] ?? ''),
        // 2.1: Google Drive e Dropbox (includes/cloud.php, copia_altrove_oauth()): app, token cifrati, account.
        'oauth' => is_array($c['oauth'] ?? null) ? $c['oauth'] : [],
    ];
}

/** La destinazione è pronta per un invio? (FTP e SFTP: il server; WebDAV: l'indirizzo; Drive e Dropbox: collegati) */
function copia_altrove_configurata(array $c): bool {
    if (copia_altrove_e_cloud($c['tipo'])) return copia_altrove_collegato($c, $c['tipo']);
    return $c['tipo'] === 'webdav' ? $c['url'] !== '' : $c['host'] !== '';
}

function copia_altrove_cfg_scrivi(array $c): void {
    save_setting('copia_altrove_cfg', json_encode($c, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/** La chiave della password: dal segreto del sito (database) e da CMS_SEGRETO (config.local.php, fuori dalla copia). */
function copia_altrove_chiave(): string {
    $extra = defined('CMS_SEGRETO') ? (string)CMS_SEGRETO : '';
    return hash_hmac('sha256', 'copia-altrove|password|' . $extra, moduli_segreto(), true);
}

function copia_altrove_cifra(string $testo): string {
    if (!function_exists('sodium_crypto_secretbox')) throw new CopiaAltroveErrore('Sul server manca la libreria sodium di PHP: serve per tenere cifrata la password.');
    $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'v1:' . base64_encode($n . sodium_crypto_secretbox($testo, $n, copia_altrove_chiave()));
}

/** La password in chiaro (solo in memoria, per la connessione), oppure null se non si decifra. */
function copia_altrove_decifra(string $c): ?string {
    if ($c === '' || !str_starts_with($c, 'v1:') || !function_exists('sodium_crypto_secretbox_open')) return null;
    $b = base64_decode(substr($c, 3), true);
    if ($b === false || strlen($b) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
    $p = @sodium_crypto_secretbox_open(substr($b, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($b, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), copia_altrove_chiave());
    return $p === false ? null : $p;
}

/**
 * Salva le impostazioni dal modulo del pannello. La password vuota lascia quella di prima; "togli la password" la cancella.
 * Lancia CopiaAltroveErrore con il motivo in parole semplici.
 */
function copia_altrove_salva(array $p): void {
    $c = copia_altrove_cfg();
    $tipo = (string)($p['tipo'] ?? '');
    if (!isset(COPIA_ALTROVE_TIPI[$tipo])) throw new CopiaAltroveErrore('Scegli il tipo di destinazione.');
    $nuovo = $c;
    $nuovo['tipo'] = $tipo;
    if (copia_altrove_e_cloud($tipo)) {
        // 2.1: Google Drive e Dropbox: niente server da scrivere, l'app (ID e segreto) del servizio.
        $nuovo['host'] = ''; $nuovo['porta'] = 443; $nuovo['url'] = '';
        copia_altrove_cloud_salva($nuovo, $p, $tipo);
    } elseif ($tipo === 'webdav') {
        $url = trim((string)($p['url'] ?? ''));
        $o = parse_url($url);
        if (!$o || !in_array(strtolower((string)($o['scheme'] ?? '')), ['http', 'https'], true) || empty($o['host'])) throw new CopiaAltroveErrore('Scrivi l\'indirizzo WebDAV completo, per esempio https://cloud.esempio.it/remote.php/dav/files/mario/');
        if (isset($o['user']) || isset($o['pass'])) throw new CopiaAltroveErrore('Nell\'indirizzo non vanno nome e password: scrivili nei campi qui sotto.');
        if (isset($o['query']) || isset($o['fragment'])) throw new CopiaAltroveErrore('L\'indirizzo WebDAV non può avere ? o #.');
        $nuovo['url'] = rtrim($url, '/') . '/';
        $nuovo['host'] = strtolower((string)$o['host']);
        $nuovo['porta'] = (int)($o['port'] ?? (strtolower($o['scheme']) === 'https' ? 443 : 80));
    } else {
        $host = strtolower(trim((string)($p['host'] ?? '')));
        if (!preg_match('/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/', $host) && !filter_var($host, FILTER_VALIDATE_IP)) throw new CopiaAltroveErrore('Scrivi il nome del server, per esempio ftp.esempio.it (senza ftp:// davanti).');
        $porta = (int)($p['porta'] ?? 0) ?: COPIA_ALTROVE_PORTE[$tipo];
        if ($porta < 1 || $porta > 65535) throw new CopiaAltroveErrore('La porta è un numero da 1 a 65535.');
        $nuovo['host'] = $host; $nuovo['porta'] = $porta; $nuovo['url'] = '';
    }
    $cart = trim((string)($p['cartella'] ?? ''), " \t");
    if ($cart !== '' && (!preg_match('~^[A-Za-z0-9._/ -]{1,200}$~', $cart) || preg_match('~(^|/)\.\.(/|$)~', $cart))) throw new CopiaAltroveErrore('La cartella può avere lettere, numeri, punti, trattini e barre (niente "..").');
    $nuovo['cartella'] = trim($cart, '/');
    if (copia_altrove_e_cloud($tipo) && $nuovo['cartella'] === '') $nuovo['cartella'] = 'copie-del-sito';
    $utente = trim((string)($p['utente'] ?? ''));
    if (strlen($utente) > 190 || preg_match('/[\r\n\0]/', $utente)) throw new CopiaAltroveErrore('Il nome utente non va bene.');
    $nuovo['utente'] = $utente;
    $pw = (string)($p['password'] ?? '');
    if (!empty($p['togli_password'])) $nuovo['password'] = '';
    elseif ($pw !== '') {
        if (strlen($pw) > 500 || preg_match('/[\r\n\0]/', $pw)) throw new CopiaAltroveErrore('La password non va bene.');
        $nuovo['password'] = copia_altrove_cifra($pw);
    }
    $nuovo['tenere'] = max(1, min(30, (int)($p['tenere'] ?? 7)));
    $nuovo['attivo'] = !empty($p['attivo']);
    $nuovo['tieni_locale'] = !empty($p['tieni_locale']);
    // Server diverso: l'impronta SFTP di prima non vale più.
    if ($nuovo['host'] !== $c['host'] || $nuovo['porta'] !== $c['porta'] || $nuovo['tipo'] !== $c['tipo']) $nuovo['sftp_impronta'] = '';
    copia_altrove_cfg_scrivi($nuovo);
}

/** La destinazione in parole: "FTPS ftp.esempio.it:21, cartella backup". Mai la password. */
function copia_altrove_descrivi(array $c): string {
    if (copia_altrove_e_cloud($c['tipo'])) {
        $o = copia_altrove_oauth($c, $c['tipo']);
        if ($o['refresh'] === '') return COPIA_ALTROVE_TIPI[$c['tipo']] . ', non ancora collegato';
        return COPIA_ALTROVE_TIPI[$c['tipo']] . ($o['account'] !== '' ? ' di ' . $o['account'] : '') . ', cartella ' . ($c['tipo'] === 'dropbox' ? 'Applicazioni/(la tua app)/' : '') . $c['cartella'];
    }
    if (!copia_altrove_configurata($c)) return 'nessuna destinazione';
    if ($c['tipo'] === 'webdav') return 'WebDAV ' . $c['url'];
    return COPIA_ALTROVE_TIPI[$c['tipo']] . ' ' . $c['host'] . ':' . $c['porta'] . ($c['cartella'] !== '' ? ', cartella ' . $c['cartella'] : '');
}

/** Le estensioni di PHP che servono, per tipo: ['ftp' => true, ...]. */
function copia_altrove_disponibili(): array {
    return ['gdrive' => function_exists('curl_init') && function_exists('sodium_crypto_secretbox'), 'dropbox' => function_exists('curl_init') && function_exists('sodium_crypto_secretbox'),
        'ftp' => function_exists('ftp_connect'), 'ftps' => function_exists('ftp_ssl_connect'), 'sftp' => function_exists('ssh2_connect'), 'webdav' => function_exists('curl_init')];
}

// ------------------------------------------------------------
// SSRF: l'host della destinazione
// ------------------------------------------------------------

function copia_altrove_locali(): array {
    if (!defined('CMS_COPIA_ALTROVE_LOCALE')) return [];
    return array_values(array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', (string)CMS_COPIA_ALTROVE_LOCALE))));
}

/** Un IP interno, privato, riservato, di loopback, link-local o della rete condivisa dei provider? */
function copia_altrove_ip_interno(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return true;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return true;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['0.0.0.0', 8], ['192.0.0.0', 24], ['198.18.0.0', 15], ['127.0.0.0', 8], ['169.254.0.0', 16], ['224.0.0.0', 4], ['240.0.0.0', 4]] as [$rete, $bit]) {
            if (($n & (-1 << (32 - $bit))) === (ip2long($rete) & (-1 << (32 - $bit)))) return true;
        }
    } else {
        $b = inet_pton($ip);
        // ::ffff:a.b.c.d (IPv4 dentro IPv6): si controlla l'IPv4.
        if ($b !== false && substr($b, 0, 12) === str_repeat("\0", 10) . "\xff\xff") return copia_altrove_ip_interno((string)inet_ntop(substr($b, 12)));
        if (preg_match('/^(fc|fd|fe8|fe9|fea|feb|ff)/i', $ip) || $ip === '::1' || $ip === '::') return true;
    }
    return false;
}

/** L'IP a cui collegarsi per host e porta, controllato. Lancia CopiaAltroveErrore se l'host è interno o non si trova. */
function copia_altrove_ip(string $host, int $porta): string {
    $host = strtolower(trim($host, '[]'));
    if ($host === '') throw new CopiaAltroveErrore('Manca il nome del server.');
    $locale = in_array($host . ':' . $porta, copia_altrove_locali(), true);
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
    else {
        if (!preg_match('/^[a-z0-9.-]+$/', $host)) throw new CopiaAltroveErrore('Il nome del server contiene caratteri non ammessi.');
        $ips = @gethostbynamel($host) ?: [];
        if (!$ips) throw new CopiaAltroveErrore('Il server ' . $host . ' non si trova: controlla di aver scritto bene il nome.');
    }
    if (!$locale) foreach ($ips as $ip) {
        if (copia_altrove_ip_interno($ip)) throw new CopiaAltroveErrore('Il server ' . $host . ' porta a una rete interna (' . $ip . '): per sicurezza la copia va solo verso server su internet.');
    }
    return $ips[0];
}

// ------------------------------------------------------------
// Le destinazioni: collega, cartella, carica, elenca, elimina
// ------------------------------------------------------------

/** Una connessione aperta. Ritorna un oggetto con i metodi carica(locale, nome), elenco(), elimina(nome), scrivi(nome, testo), chiudi(). */
function copia_altrove_apri(array $c): object {
    $disp = copia_altrove_disponibili();
    if (copia_altrove_e_cloud($c['tipo'])) {
        // 2.1: Google Drive e Dropbox (includes/cloud.php). Gli host sono fissi: niente controllo SSRF da fare.
        if (empty($disp[$c['tipo']])) throw new CopiaAltroveErrore('Su questo server manca l\'estensione curl (o sodium) di PHP, che serve per ' . COPIA_ALTROVE_TIPI[$c['tipo']] . '.');
        if (!copia_altrove_collegato($c, $c['tipo'])) throw new CopiaAltroveErrore(COPIA_ALTROVE_TIPI[$c['tipo']] . ' non è ancora collegato: scrivi ID e segreto dell\'app e premi "Salva e collega".');
        return $c['tipo'] === 'gdrive' ? new CopiaAltroveGdrive($c) : new CopiaAltroveDropbox($c);
    }
    if (!copia_altrove_configurata($c)) throw new CopiaAltroveErrore('Prima scrivi la destinazione e salva.');
    if (empty($disp[$c['tipo']])) {
        throw new CopiaAltroveErrore(match ($c['tipo']) {
            'sftp' => 'Su questo server manca l\'estensione ssh2 di PHP, che serve per SFTP: SFTP non si può usare. Scegli FTPS o WebDAV, oppure chiedi al tuo hosting di attivare ssh2.',
            'webdav' => 'Su questo server manca l\'estensione curl di PHP, che serve per WebDAV.',
            default => 'Su questo server manca l\'estensione ftp di PHP: chiedi al tuo hosting di attivarla, oppure usa WebDAV.',
        });
    }
    $pw = $c['password'] !== '' ? copia_altrove_decifra($c['password']) : '';
    if ($pw === null) throw new CopiaAltroveErrore('La password salvata non si legge più (il sito è stato spostato o ripristinato?): scrivila di nuovo e salva.');
    // WebDAV: nome e password viaggiano solo su https (http solo per le prove in locale, CMS_COPIA_ALTROVE_LOCALE).
    if ($c['tipo'] === 'webdav' && strtolower((string)parse_url($c['url'], PHP_URL_SCHEME)) !== 'https' && !in_array($c['host'] . ':' . $c['porta'], copia_altrove_locali(), true)) {
        throw new CopiaAltroveErrore('Per mandare nome e password serve un indirizzo https://.');
    }
    $ip = copia_altrove_ip($c['host'], (int)$c['porta']);
    return match ($c['tipo']) {
        'ftp', 'ftps' => new CopiaAltroveFtp($c, $ip, $pw),
        'sftp' => new CopiaAltroveSftp($c, $ip, $pw),
        'webdav' => new CopiaAltroveWebdav($c, $ip, $pw),
    };
}

final class CopiaAltroveFtp {
    private $c;
    public function __construct(array $cfg, string $ip, string $pw) {
        $this->c = $cfg['tipo'] === 'ftps' ? @ftp_ssl_connect($ip, (int)$cfg['porta'], COPIA_ALTROVE_TIMEOUT) : @ftp_connect($ip, (int)$cfg['porta'], COPIA_ALTROVE_TIMEOUT);
        if (!$this->c) throw new CopiaAltroveErrore('Il server ' . $cfg['host'] . ' non risponde sulla porta ' . $cfg['porta'] . '. Controlla nome e porta.');
        if (!@ftp_login($this->c, $cfg['utente'] !== '' ? $cfg['utente'] : 'anonymous', $pw)) {
            @ftp_close($this->c);
            throw new CopiaAltroveErrore($cfg['tipo'] === 'ftps' ? 'Accesso rifiutato: controlla nome utente e password (oppure il server non accetta FTPS: prova FTP).' : 'Accesso rifiutato: controlla nome utente e password.');
        }
        // Il server dice dove collegarsi per i dati: si usa sempre l'IP già controllato, mai quello che dice lui.
        @ftp_set_option($this->c, FTP_USEPASVADDRESS, false);
        ftp_pasv($this->c, true);
        foreach (array_filter(explode('/', $cfg['cartella'])) as $pezzo) {
            if (!@ftp_chdir($this->c, $pezzo)) {
                if (!@ftp_mkdir($this->c, $pezzo) || !@ftp_chdir($this->c, $pezzo)) throw new CopiaAltroveErrore('Non riesco a creare o aprire la cartella «' . $cfg['cartella'] . '» sul server.');
            }
        }
    }
    public function carica(string $locale, string $nome): void {
        $tmp = $nome . '.parte';
        if (!@ftp_put($this->c, $tmp, $locale, FTP_BINARY)) { @ftp_delete($this->c, $tmp); throw new CopiaAltroveErrore('Il server ha interrotto il caricamento: spazio finito, oppure niente permesso di scrivere nella cartella.'); }
        $peso = @ftp_size($this->c, $tmp);
        if ($peso >= 0 && $peso !== filesize($locale)) { @ftp_delete($this->c, $tmp); throw new CopiaAltroveErrore('La copia è arrivata a metà (' . $peso . ' byte su ' . filesize($locale) . '). Riprova.'); }
        @ftp_delete($this->c, $nome);
        if (!@ftp_rename($this->c, $tmp, $nome)) throw new CopiaAltroveErrore('La copia è arrivata ma non si riesce a darle il suo nome.');
    }
    public function scrivi(string $nome, string $testo): void {
        $h = fopen('php://temp', 'w+'); fwrite($h, $testo); rewind($h);
        $ok = @ftp_fput($this->c, $nome, $h, FTP_BINARY); fclose($h);
        if (!$ok) throw new CopiaAltroveErrore('Collegato, ma il server non lascia scrivere nella cartella.');
    }
    public function elenco(): array {
        $l = @ftp_nlist($this->c, '.');
        return array_map('basename', is_array($l) ? $l : []);
    }
    public function elimina(string $nome): bool { return @ftp_delete($this->c, $nome); }
    public function chiudi(): void { @ftp_close($this->c); }
}

/** SFTP con l'estensione ssh2. Al primo collegamento si ricorda l'impronta del server; se cambia, ci si ferma. */
final class CopiaAltroveSftp {
    private $s; private $sftp; private string $dir;
    public function __construct(array $cfg, string $ip, string $pw) {
        $this->s = @ssh2_connect($ip, (int)$cfg['porta']);
        if (!$this->s) throw new CopiaAltroveErrore('Il server ' . $cfg['host'] . ' non risponde sulla porta ' . $cfg['porta'] . '.');
        $impronta = (string)@ssh2_fingerprint($this->s, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX);
        if ($cfg['sftp_impronta'] !== '' && !hash_equals($cfg['sftp_impronta'], $impronta)) throw new CopiaAltroveErrore('L\'impronta del server SFTP è cambiata: potrebbe non essere più il tuo server. Se l\'hai cambiato tu, salva di nuovo la destinazione.');
        if (!@ssh2_auth_password($this->s, $cfg['utente'], $pw)) throw new CopiaAltroveErrore('Accesso rifiutato: controlla nome utente e password.');
        if ($cfg['sftp_impronta'] === '') { $c = copia_altrove_cfg(); $c['sftp_impronta'] = $impronta; copia_altrove_cfg_scrivi($c); }
        $this->sftp = @ssh2_sftp($this->s);
        if (!$this->sftp) throw new CopiaAltroveErrore('Collegato, ma il server non offre SFTP.');
        $this->dir = '';
        foreach (array_filter(explode('/', $cfg['cartella'])) as $pezzo) {
            $this->dir .= ($this->dir === '' && !str_starts_with($cfg['cartella'], '/') ? '' : '/') . $pezzo;
            if (!@is_dir($this->u($this->dir)) && !@ssh2_sftp_mkdir($this->sftp, $this->dir, 0755)) throw new CopiaAltroveErrore('Non riesco a creare la cartella «' . $cfg['cartella'] . '» sul server.');
        }
    }
    private function u(string $p): string { return 'ssh2.sftp://' . (int)$this->sftp . '/' . ltrim($p, '/'); }
    private function p(string $nome): string { return ($this->dir !== '' ? $this->dir . '/' : '') . $nome; }
    public function carica(string $locale, string $nome): void {
        $tmp = $this->p($nome . '.parte');
        if (!@copy($locale, $this->u($tmp))) throw new CopiaAltroveErrore('Il server ha interrotto il caricamento.');
        @ssh2_sftp_unlink($this->sftp, $this->p($nome));
        if (!@ssh2_sftp_rename($this->sftp, $tmp, $this->p($nome))) throw new CopiaAltroveErrore('La copia è arrivata ma non si riesce a darle il suo nome.');
    }
    public function scrivi(string $nome, string $testo): void {
        if (@file_put_contents($this->u($this->p($nome)), $testo) === false) throw new CopiaAltroveErrore('Collegato, ma il server non lascia scrivere nella cartella.');
    }
    public function elenco(): array {
        $h = @opendir($this->u($this->dir !== '' ? $this->dir : '.'));
        $out = [];
        if ($h) { while (($f = readdir($h)) !== false) $out[] = $f; closedir($h); }
        return $out;
    }
    public function elimina(string $nome): bool { return @ssh2_sftp_unlink($this->sftp, $this->p($nome)); }
    public function chiudi(): void { if (function_exists('ssh2_disconnect')) @ssh2_disconnect($this->s); }
}

/** WebDAV con curl: PUT, PROPFIND, DELETE, MKCOL. L'host è fissato all'IP controllato (CURLOPT_RESOLVE), niente rimandi. */
final class CopiaAltroveWebdav {
    private string $base; private array $cfg; private string $ip; private string $pw;
    public function __construct(array $cfg, string $ip, string $pw) {
        $this->cfg = $cfg; $this->ip = $ip; $this->pw = $pw;
        $this->base = $cfg['url'];
        $r = $this->chiama('PROPFIND', $this->base, null, ['Depth: 0']);
        if ($r[0] === 401 || $r[0] === 403) throw new CopiaAltroveErrore('Accesso rifiutato: controlla nome utente e password (in Nextcloud serve una "password per le applicazioni").');
        if ($r[0] === 404) throw new CopiaAltroveErrore('L\'indirizzo WebDAV non esiste (404): controllalo.');
        if ($r[0] !== 207) throw new CopiaAltroveErrore('Il server non risponde come un server WebDAV (codice ' . $r[0] . '): controlla l\'indirizzo.');
        foreach (array_filter(explode('/', $cfg['cartella'])) as $pezzo) {
            $this->base .= rawurlencode($pezzo) . '/';
            $r = $this->chiama('PROPFIND', $this->base, null, ['Depth: 0']);
            if ($r[0] === 404) { $r = $this->chiama('MKCOL', $this->base); if ($r[0] >= 300) throw new CopiaAltroveErrore('Non riesco a creare la cartella «' . $cfg['cartella'] . '» (codice ' . $r[0] . ').'); }
            elseif ($r[0] !== 207) throw new CopiaAltroveErrore('Non riesco ad aprire la cartella «' . $cfg['cartella'] . '» (codice ' . $r[0] . ').');
        }
    }
    /** Una chiamata: [codice, corpo]. $file: percorso da mandare (PUT), oppure null. */
    private function chiama(string $metodo, string $url, ?string $file = null, array $testate = [], ?string $testo = null): array {
        $o = parse_url($url);
        $porta = (int)($o['port'] ?? (strtolower($o['scheme']) === 'https' ? 443 : 80));
        $h = curl_init($url);
        $opz = [
            CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RESOLVE => [$o['host'] . ':' . $porta . ':' . $this->ip],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_CONNECTTIMEOUT => COPIA_ALTROVE_TIMEOUT, CURLOPT_TIMEOUT => $file ? 0 : 60,
            // Solo Basic, mandato subito: con la negoziazione curl dovrebbe rimandare il file da capo, e non può. Va solo su https.
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC, CURLOPT_USERPWD => $this->cfg['utente'] . ':' . $this->pw,
            CURLOPT_HTTPHEADER => $testate, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'plumeCMS copia altrove',
        ];
        if (defined('CURLOPT_UNRESTRICTED_AUTH')) $opz[CURLOPT_UNRESTRICTED_AUTH] = false;
        $fh = null;
        if ($file !== null) { $fh = fopen($file, 'rb'); $opz[CURLOPT_UPLOAD] = true; $opz[CURLOPT_INFILE] = $fh; $opz[CURLOPT_INFILESIZE] = filesize($file); unset($opz[CURLOPT_CUSTOMREQUEST]); $opz[CURLOPT_CUSTOMREQUEST] = 'PUT'; }
        elseif ($testo !== null) { $opz[CURLOPT_POSTFIELDS] = $testo; }
        curl_setopt_array($h, $opz);
        $corpo = curl_exec($h);
        $codice = (int)curl_getinfo($h, CURLINFO_RESPONSE_CODE);
        $errore = curl_error($h);
        curl_close($h);
        if ($fh) fclose($fh);
        if ($corpo === false) throw new CopiaAltroveErrore('Il server ' . $this->cfg['host'] . ' non risponde: ' . $errore);
        return [$codice, (string)$corpo];
    }
    public function carica(string $locale, string $nome): void {
        $r = $this->chiama('PUT', $this->base . rawurlencode($nome), $locale);
        if ($r[0] < 200 || $r[0] >= 300) throw new CopiaAltroveErrore('Il server ha rifiutato la copia (codice ' . $r[0] . '): spazio finito o niente permesso di scrivere?');
    }
    public function scrivi(string $nome, string $testo): void {
        $r = $this->chiama('PUT', $this->base . rawurlencode($nome), null, ['Content-Type: text/plain'], $testo);
        if ($r[0] < 200 || $r[0] >= 300) throw new CopiaAltroveErrore('Collegato, ma il server non lascia scrivere nella cartella (codice ' . $r[0] . ').');
    }
    public function elenco(): array {
        $r = $this->chiama('PROPFIND', $this->base, null, ['Depth: 1', 'Content-Type: application/xml'], '<d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/></d:prop></d:propfind>');
        if ($r[0] !== 207) return [];
        preg_match_all('~<(?:[a-z0-9]+:)?href>([^<]+)</(?:[a-z0-9]+:)?href>~i', $r[1], $m);
        return array_values(array_filter(array_map(fn($x) => rawurldecode(basename(rtrim(html_entity_decode($x), '/'))), $m[1])));
    }
    public function elimina(string $nome): bool { return $this->chiama('DELETE', $this->base . rawurlencode($nome))[0] < 300; }
    public function chiudi(): void {}
}

// ------------------------------------------------------------
// Prova, invio, registro
// ------------------------------------------------------------

function copia_altrove_registra(string $origine, string $esito, array $c, string $file, int $peso, int $secondi, string $msg): void {
    copia_altrove_schema();
    db()->prepare('INSERT INTO ' . tbl('copia_altrove_registro') . ' (quando, origine, esito, tipo, destinazione, file, peso, secondi, messaggio) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$origine, $esito, $c['tipo'], mb_substr(copia_altrove_descrivi($c), 0, 255), $file, $peso, $secondi, mb_substr($msg, 0, 2000)]);
    // Il registro tiene gli ultimi 500 invii.
    db()->exec('DELETE FROM ' . tbl('copia_altrove_registro') . ' WHERE id < (SELECT m FROM (SELECT MAX(id) - 500 AS m FROM ' . tbl('copia_altrove_registro') . ') x)');
}

function copia_altrove_registro(int $quanti = 30): array {
    copia_altrove_schema();
    return db()->query('SELECT * FROM ' . tbl('copia_altrove_registro') . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $quanti)))->fetchAll();
}

/** "Prova la connessione": si collega, apre la cartella, scrive e cancella un file piccolo. Ritorna il messaggio; lancia l'errore. */
function copia_altrove_prova(): string {
    $c = copia_altrove_cfg();
    $t = microtime(true);
    try {
        $x = copia_altrove_apri($c);
        $nome = 'plumecms-prova-' . bin2hex(random_bytes(4)) . '.txt';
        $x->scrivi($nome, "Prova di plumeCMS: questo file si cancella da solo.\n");
        $visto = in_array($nome, $x->elenco(), true);
        $x->elimina($nome);
        $x->chiudi();
        $msg = 'Collegamento riuscito: la cartella si apre e si può scrivere' . ($visto ? '' : ' (il server però non elenca i file: la pulizia delle copie vecchie potrebbe non funzionare)') . '.';
        copia_altrove_registra('prova', 'ok', $c, '', 0, (int)round(microtime(true) - $t), $msg);
        return $msg;
    } catch (Throwable $e) {
        copia_altrove_registra('prova', 'errore', $c, '', 0, (int)round(microtime(true) - $t), $e->getMessage());
        throw $e;
    }
}

/**
 * Crea la copia del sito e la manda alla destinazione; poi tiene sulla destinazione le ultime N copie "altrove".
 * $origine: 'notte' (cron) o 'manuale' (pannello). Ritorna il messaggio; lancia l'errore (già scritto nel registro).
 */
function copia_altrove_invia(string $origine): string {
    $c = copia_altrove_cfg();
    $t = microtime(true);
    $lock = @fopen(copia_dir() . '/.altrove.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new CopiaAltroveErrore('Un invio è già in corso: aspetta che finisca.');
    $nome = ''; $peso = 0;
    try {
        $x = copia_altrove_apri($c); // prima la destinazione: se non risponde, la copia non si fa nemmeno
        $nome = copia_crea(COPIA_ALTROVE_ETICHETTA);
        $locale = copia_percorso($nome);
        $peso = (int)filesize($locale);
        $x->carica($locale, $nome);
        $tolte = 0;
        $mie = array_values(array_filter($x->elenco(), fn($f) => (bool)preg_match('/^copia-\d{8}-\d{6}-' . COPIA_ALTROVE_ETICHETTA . '\.zip$/', $f)));
        rsort($mie, SORT_STRING);
        foreach (array_slice($mie, $c['tenere']) as $vecchia) if ($x->elimina($vecchia)) $tolte++;
        $x->chiudi();
        if (!$c['tieni_locale']) @unlink($locale);
        $msg = 'Copia mandata (' . copia_altrove_peso($peso) . ').' . ($tolte ? " Tolte $tolte copie vecchie dalla destinazione." : '') . ' Sulla destinazione ce ne sono ' . min(count($mie), $c['tenere']) . '.';
        copia_altrove_registra($origine, 'ok', $c, $nome, $peso, (int)round(microtime(true) - $t), $msg);
        return $msg;
    } catch (Throwable $e) {
        $msg = $e->getMessage() . ($nome !== '' ? ' La copia è rimasta sul server, in Impostazioni > Copia del sito.' : '');
        copia_altrove_registra($origine, 'errore', $c, $nome, $peso, (int)round(microtime(true) - $t), $msg);
        throw new CopiaAltroveErrore($msg, 0, $e);
    } finally {
        flock($lock, LOCK_UN); fclose($lock);
    }
}

function copia_altrove_peso(int $b): string {
    if ($b < 1048576) return max(1, (int)round($b / 1024)) . ' KB';
    return str_replace('.', ',', (string)round($b / 1048576, 1)) . ' MB';
}

/** Il passo del cron di ogni notte. Se non riesce, un'email all'indirizzo degli avvisi. */
function copia_altrove_notte(): string {
    $c = copia_altrove_cfg();
    if (!$c['attivo']) return 'spenta (si accende nella pagina Copia altrove)';
    if (!copia_altrove_configurata($c) && !copia_altrove_e_cloud($c['tipo'])) return 'nessuna destinazione';
    try {
        return copia_altrove_invia('notte');
    } catch (Throwable $e) {
        try {
            $a = posta_notifica_email();
            if ($a !== '') posta_invia($a, '[' . cms_impostazione('sito_nome', CMS_NOME) . '] La copia del sito non è partita', moduli_email_html('La copia del sito non è partita', '<p>Stanotte la copia del sito verso ' . h(copia_altrove_descrivi($c)) . ' non è riuscita:</p><p><b>' . h($e->getMessage()) . '</b></p><p>Nel pannello: Impostazioni, Copia altrove, "Prova la connessione".</p>', 'Apri il pannello', cms_url_pannello() . copia_altrove_admin_url()), moduli_intestazioni(), 'copia');
        } catch (Throwable $e2) { error_log('copia altrove: avviso non partito: ' . $e2->getMessage()); }
        throw $e;
    }
}

require_once __DIR__ . '/cloud.php'; // 2.1: Google Drive e Dropbox
