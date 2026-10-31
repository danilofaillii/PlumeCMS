<?php
/**
 * Copia del sito altrove (addon copia-altrove, 2.1): Google Drive e Dropbox, con OAuth 2.
 *
 * Il collegamento, per chi non è tecnico:
 *   1. l'amministratore crea la sua app (Google Cloud Console o Dropbox App Console) seguendo i passi del pannello e
 *      registra come "redirect URI" l'indirizzo fisso del pannello admin/oauth-ritorno.php (copia_altrove_redirect_uri);
 *   2. incolla ID e segreto dell'app e preme "Salva e collega": copia_altrove_oauth_inizia() prepara lo state e la sfida
 *      PKCE (S256), li tiene nella sessione del pannello e manda il browser alla pagina del servizio;
 *   3. il servizio torna su admin/oauth-ritorno.php?code=...&state=..., che passa a copia_altrove_oauth_ritorno():
 *      state firmato (HMAC con moduli_segreto), legato a questa sessione e a questo amministratore, scaduto dopo 15 minuti,
 *      buono una volta sola; poi il codice diventa un refresh token (cifrato con la chiave di copia_altrove_chiave()).
 *   4. a ogni invio l'accesso (access token, un'ora) si rinnova da solo con il refresh token; un 401 lo rinnova e riprova.
 *
 * Il caricamento è a pezzi: per Google Drive l'upload "resumable" (sessione, pezzi multipli di 256 KB, Content-Range,
 * 308 con Range, ripresa dopo un errore chiedendo a che punto è), per Dropbox l'upload session (start, append_v2, finish,
 * con incorrect_offset per riprendere). Pezzi da 8 MB.
 *
 * Permessi chiesti: Google "drive.file" (solo i file che crea l'app: non vede il resto del Drive, e Google non chiede la
 * verifica dell'app); Dropbox un'app "App folder" (vede solo la sua cartella, Applicazioni/<nome dell'app>).
 *
 * Indirizzi dei servizi: fissi (COPIA_ALTROVE_CLOUD_HOST). Solo per le prove, nel config.local.php:
 *     define('CMS_COPIA_ALTROVE_CLOUD_PROVA', 'gdrive=http://127.0.0.1:8321,dropbox=http://127.0.0.1:8322');
 *     define('CMS_COPIA_ALTROVE_PEZZO', 262144);   // pezzi da 256 KB, per provare l'upload a pezzi con una copia piccola
 * e https://<host>/<percorso> diventa <base>/<host>/<percorso> (prove/server-cloud-finti.py). Dal pannello non si cambia.
 *
 * Il token non esce mai: né nella pagina, né nel registro, né nei messaggi di errore, né in un indirizzo (sempre nella
 * testata Authorization). Il segreto dell'app non si rivede, come la password delle altre destinazioni.
 */

require_once __DIR__ . '/copia-altrove.php';

const COPIA_ALTROVE_CLOUD = ['gdrive' => 'Google Drive', 'dropbox' => 'Dropbox'];
/** Gli host veri dei due servizi: gli unici a cui il CMS parla (le prove li riscrivono, vedi sopra). */
const COPIA_ALTROVE_CLOUD_HOST = [
    'gdrive' => ['accounts.google.com', 'oauth2.googleapis.com', 'www.googleapis.com'],
    'dropbox' => ['www.dropbox.com', 'api.dropboxapi.com', 'content.dropboxapi.com'],
];
const COPIA_ALTROVE_GDRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file';
const COPIA_ALTROVE_OAUTH_MINUTI = 15;

function copia_altrove_e_cloud(string $tipo): bool {
    return isset(COPIA_ALTROVE_CLOUD[$tipo]);
}

/** L'indirizzo a cui i servizi rimandano il browser: fisso, senza query (si registra così nell'app). */
function copia_altrove_redirect_uri(): string {
    return cms_url_pannello() . 'oauth-ritorno.php';
}

/** Le basi delle prove: ['gdrive' => 'http://127.0.0.1:8321', ...] (solo da CMS_COPIA_ALTROVE_CLOUD_PROVA). */
function copia_altrove_cloud_prova(): array {
    if (!defined('CMS_COPIA_ALTROVE_CLOUD_PROVA')) return [];
    $out = [];
    foreach (explode(',', (string)CMS_COPIA_ALTROVE_CLOUD_PROVA) as $x) {
        [$k, $v] = array_pad(explode('=', trim($x), 2), 2, '');
        if (copia_altrove_e_cloud($k) && preg_match('~^https?://[a-z0-9.:\[\]-]+$~i', $v)) $out[$k] = rtrim($v, '/');
    }
    return $out;
}

/** L'indirizzo vero di un servizio (https://host/percorso), oppure quello del server finto delle prove. */
function copia_altrove_cloud_url(string $servizio, string $url): string {
    $prova = copia_altrove_cloud_prova()[$servizio] ?? '';
    if ($prova === '') return $url;
    return preg_replace('~^https://~', $prova . '/', $url);
}

/** Il segreto per i pezzi: 8 MB; per le prove CMS_COPIA_ALTROVE_PEZZO (multiplo di 256 KB). */
function copia_altrove_pezzo(): int {
    $p = defined('CMS_COPIA_ALTROVE_PEZZO') ? (int)CMS_COPIA_ALTROVE_PEZZO : 8388608;
    return max(262144, intdiv($p, 262144) * 262144);
}

// ------------------------------------------------------------
// Impostazioni del collegamento
// ------------------------------------------------------------

/** I dati del collegamento di un servizio, sempre con tutte le chiavi. Il segreto e i token sono cifrati (v1:...). */
function copia_altrove_oauth(array $c, string $servizio): array {
    $o = (array)($c['oauth'][$servizio] ?? []);
    return [
        'client_id' => (string)($o['client_id'] ?? ''),
        'segreto' => (string)($o['segreto'] ?? ''),
        'refresh' => (string)($o['refresh'] ?? ''),
        'accesso' => (string)($o['accesso'] ?? ''),
        'scade' => (int)($o['scade'] ?? 0),
        'account' => (string)($o['account'] ?? ''),
        'collegato' => (string)($o['collegato'] ?? ''),
        'cartella_id' => (string)($o['cartella_id'] ?? ''),
        'cartella_per' => (string)($o['cartella_per'] ?? ''),
    ];
}

/** Riscrive i dati del collegamento di un servizio (rilegge le impostazioni: niente sovrascritture di dati vecchi). */
function copia_altrove_oauth_scrivi(string $servizio, array $nuovi): void {
    $c = copia_altrove_cfg();
    $c['oauth'][$servizio] = array_merge(copia_altrove_oauth($c, $servizio), $nuovi);
    copia_altrove_cfg_scrivi($c);
}

function copia_altrove_collegato(array $c, string $servizio): bool {
    return copia_altrove_oauth($c, $servizio)['refresh'] !== '';
}

/** Il collegamento si toglie (token e account), l'app resta (ID e segreto): per ricollegare basta "Collega". */
function copia_altrove_oauth_dimentica(string $servizio): void {
    copia_altrove_oauth_scrivi($servizio, ['refresh' => '', 'accesso' => '', 'scade' => 0, 'account' => '', 'collegato' => '', 'cartella_id' => '', 'cartella_per' => '']);
}

/** Dal modulo del pannello: ID e segreto dell'app. Il segreto vuoto lascia quello di prima. Un'app diversa toglie il collegamento. */
function copia_altrove_cloud_salva(array &$nuovo, array $p, string $servizio): void {
    $o = copia_altrove_oauth($nuovo, $servizio);
    $id = trim((string)($p[$servizio . '_client_id'] ?? ''));
    $seg = trim((string)($p[$servizio . '_segreto'] ?? ''));
    if ($servizio === 'gdrive' && $id !== '' && !preg_match('/^[A-Za-z0-9._-]{4,200}\.apps\.googleusercontent\.com$/', $id)) {
        throw new CopiaAltroveErrore('L\'ID client di Google finisce con .apps.googleusercontent.com: copialo intero dalla pagina Client della Google Cloud Console.');
    }
    if ($servizio === 'dropbox' && $id !== '' && !preg_match('/^[A-Za-z0-9]{8,40}$/', $id)) {
        throw new CopiaAltroveErrore('La App key di Dropbox è fatta di lettere e numeri (circa 15): copiala dalla scheda Settings della tua app.');
    }
    if ($seg !== '' && !preg_match('#^[A-Za-z0-9._~+/=-]{8,200}$#', $seg)) throw new CopiaAltroveErrore('Il segreto dell\'app non sembra giusto: copialo di nuovo, senza spazi.');
    if ($id !== $o['client_id']) {
        // Un'app diversa: il collegamento di prima non vale più.
        $o = ['client_id' => $id, 'segreto' => '', 'refresh' => '', 'accesso' => '', 'scade' => 0, 'account' => '', 'collegato' => '', 'cartella_id' => '', 'cartella_per' => ''] + $o;
    }
    if ($seg !== '') $o['segreto'] = copia_altrove_cifra($seg);
    $nuovo['oauth'][$servizio] = $o;
}

// ------------------------------------------------------------
// HTTP verso i servizi
// ------------------------------------------------------------

/**
 * Una chiamata a un servizio: [codice, testate (nomi minuscoli), corpo]. Solo gli host di COPIA_ALTROVE_CLOUD_HOST (o le
 * basi delle prove), solo https (http solo per le prove), certificati verificati, nessun rimando seguito.
 * $corpo: una stringa, oppure ['file' => percorso, 'da' => byte, 'quanti' => byte] per un pezzo di un file.
 */
function copia_altrove_cloud_http(string $servizio, string $metodo, string $url, array $testate = [], $corpo = null, int $tempo = 120): array {
    $prova = copia_altrove_cloud_prova()[$servizio] ?? '';
    $o = parse_url($url);
    $ammesso = $prova !== '' ? str_starts_with($url, $prova . '/') : (($o['scheme'] ?? '') === 'https' && in_array(strtolower((string)($o['host'] ?? '')), COPIA_ALTROVE_CLOUD_HOST[$servizio] ?? [], true));
    if (!$ammesso || !function_exists('curl_init')) throw new CopiaAltroveErrore(function_exists('curl_init') ? 'Indirizzo del servizio non ammesso.' : 'Su questo server manca l\'estensione curl di PHP, che serve per ' . COPIA_ALTROVE_CLOUD[$servizio] . '.');
    $h = curl_init($url);
    $ricevute = [];
    $opz = [
        CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => $prova !== '' ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => COPIA_ALTROVE_TIMEOUT, CURLOPT_TIMEOUT => $tempo,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'plumeCMS copia altrove',
        CURLOPT_HTTPHEADER => array_merge($testate, ['Expect:']),
        CURLOPT_HEADERFUNCTION => function ($ch, $riga) use (&$ricevute) {
            $p = strpos($riga, ':');
            if ($p !== false) $ricevute[strtolower(trim(substr($riga, 0, $p)))] = trim(substr($riga, $p + 1));
            elseif (preg_match('~^HTTP/~', $riga)) $ricevute = []; // un 100 Continue prima della risposta vera
            return strlen($riga);
        },
    ];
    $fh = null;
    if (is_array($corpo)) {
        $fh = fopen($corpo['file'], 'rb');
        if (!$fh) throw new CopiaAltroveErrore('La copia sul server non si legge.');
        fseek($fh, (int)$corpo['da']);
        $resta = (int)$corpo['quanti'];
        $opz[CURLOPT_UPLOAD] = true;
        $opz[CURLOPT_INFILESIZE] = $resta;
        $opz[CURLOPT_READFUNCTION] = function ($ch, $f, $n) use ($fh, &$resta) {
            if ($resta <= 0) return '';
            $d = (string)fread($fh, min($n, $resta));
            $resta -= strlen($d);
            return $d;
        };
        $opz[CURLOPT_CUSTOMREQUEST] = $metodo; // UPLOAD vuole PUT: il metodo vero resta questo (POST per Dropbox)
    } elseif ($corpo !== null) {
        $opz[CURLOPT_POSTFIELDS] = (string)$corpo;
    } elseif (in_array($metodo, ['POST', 'PUT'], true)) {
        $opz[CURLOPT_POSTFIELDS] = '';
    }
    curl_setopt_array($h, $opz);
    $r = curl_exec($h);
    $codice = (int)curl_getinfo($h, CURLINFO_RESPONSE_CODE);
    $errore = curl_error($h);
    curl_close($h);
    if ($fh) fclose($fh);
    if ($r === false) throw new CopiaAltroveErrore(COPIA_ALTROVE_CLOUD[$servizio] . ' non risponde: ' . $errore, 1);
    return [$codice, $ricevute, (string)$r];
}

/** Il motivo di un errore di un servizio, in breve e senza mai un token. */
function copia_altrove_cloud_motivo(string $corpo): string {
    $j = json_decode($corpo, true);
    $m = '';
    if (is_array($j)) {
        $e = $j['error'] ?? null;
        $m = is_array($e) ? (string)($e['message'] ?? ($e['.tag'] ?? '')) : (string)$e;
        if (!empty($j['error_description'])) $m .= ': ' . $j['error_description'];
        if ($m === '' && !empty($j['error_summary'])) $m = (string)$j['error_summary'];
    }
    $m = preg_replace('/[A-Za-z0-9._~+\/=-]{40,}/', '...', $m); // niente che somigli a un token
    return mb_substr(trim((string)$m), 0, 200);
}

/** Una chiamata al token endpoint (codice o refresh): il JSON della risposta. Lancia l'errore spiegato. */
function copia_altrove_token_chiama(string $servizio, array $campi): array {
    $c = copia_altrove_cfg();
    $o = copia_altrove_oauth($c, $servizio);
    $seg = $o['segreto'] !== '' ? copia_altrove_decifra($o['segreto']) : null;
    if ($o['client_id'] === '' || $seg === null) throw new CopiaAltroveErrore('Manca l\'app di ' . COPIA_ALTROVE_CLOUD[$servizio] . ' (ID e segreto): scrivili e premi "Salva e collega".');
    $url = $servizio === 'gdrive' ? 'https://oauth2.googleapis.com/token' : 'https://api.dropboxapi.com/oauth2/token';
    $r = copia_altrove_cloud_http($servizio, 'POST', copia_altrove_cloud_url($servizio, $url), ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        http_build_query($campi + ['client_id' => $o['client_id'], 'client_secret' => $seg], '', '&', PHP_QUERY_RFC3986), 60);
    $j = json_decode($r[2], true);
    if ($r[0] !== 200 || !is_array($j) || empty($j['access_token'])) {
        $motivo = copia_altrove_cloud_motivo($r[2]);
        if (str_contains($motivo, 'invalid_client') || str_contains($motivo, 'unauthorized_client')) throw new CopiaAltroveErrore(COPIA_ALTROVE_CLOUD[$servizio] . ' non riconosce l\'app: controlla ID e segreto (copiali di nuovo) e premi "Salva e collega".');
        if (($campi['grant_type'] ?? '') === 'refresh_token' && str_contains($motivo, 'invalid_grant')) {
            copia_altrove_oauth_dimentica($servizio);
            throw new CopiaAltroveErrore('Il collegamento con ' . COPIA_ALTROVE_CLOUD[$servizio] . ' non vale più (il permesso è stato tolto, oppure è scaduto): premi "Salva e collega" e dai di nuovo il permesso.');
        }
        throw new CopiaAltroveErrore(COPIA_ALTROVE_CLOUD[$servizio] . ' ha rifiutato (codice ' . $r[0] . ($motivo !== '' ? ', ' . $motivo : '') . ').');
    }
    return $j;
}

/** L'accesso (access token) valido, rinnovato da solo con il refresh token quando sta per scadere o se $forza. */
function copia_altrove_accesso(string $servizio, bool $forza = false): string {
    $o = copia_altrove_oauth(copia_altrove_cfg(), $servizio);
    if ($o['refresh'] === '') throw new CopiaAltroveErrore(COPIA_ALTROVE_CLOUD[$servizio] . ' non è collegato: premi "Salva e collega".');
    if (!$forza && $o['accesso'] !== '' && $o['scade'] > time() + 60) {
        $a = copia_altrove_decifra($o['accesso']);
        if ($a !== null && $a !== '') return $a;
    }
    $refresh = copia_altrove_decifra($o['refresh']);
    if ($refresh === null) throw new CopiaAltroveErrore('Il collegamento salvato non si legge più (il sito è stato spostato o ripristinato?): premi "Salva e collega".');
    $j = copia_altrove_token_chiama($servizio, ['grant_type' => 'refresh_token', 'refresh_token' => $refresh]);
    $nuovi = ['accesso' => copia_altrove_cifra((string)$j['access_token']), 'scade' => time() + max(60, (int)($j['expires_in'] ?? 3600))];
    if (!empty($j['refresh_token'])) $nuovi['refresh'] = copia_altrove_cifra((string)$j['refresh_token']); // se il servizio lo cambia
    copia_altrove_oauth_scrivi($servizio, $nuovi);
    return (string)$j['access_token'];
}

// ------------------------------------------------------------
// OAuth: inizio e ritorno
// ------------------------------------------------------------

function copia_altrove_b64url(string $b): string {
    return rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
}

function copia_altrove_state_firma(string $servizio, string $nonce, int $scade, int $utente): string {
    return copia_altrove_b64url(hash_hmac('sha256', "copia-altrove|oauth|$servizio|$nonce|$scade|$utente", moduli_segreto(), true));
}

/**
 * Prepara il collegamento: state firmato e sfida PKCE nella sessione del pannello. Ritorna l'indirizzo della pagina del
 * servizio dove l'amministratore dà il permesso. Una sola richiesta aperta per sessione (la nuova sostituisce la vecchia).
 */
function copia_altrove_oauth_inizia(string $servizio): string {
    if (!copia_altrove_e_cloud($servizio)) throw new CopiaAltroveErrore('Servizio sconosciuto.');
    $o = copia_altrove_oauth(copia_altrove_cfg(), $servizio);
    if ($o['client_id'] === '' || $o['segreto'] === '') throw new CopiaAltroveErrore('Prima scrivi ID e segreto dell\'app di ' . COPIA_ALTROVE_CLOUD[$servizio] . '.');
    $utente = (int)($_SESSION['admin_user_id'] ?? 0);
    if ($utente <= 0) throw new CopiaAltroveErrore('Sessione del pannello non valida: rientra.');
    $nonce = copia_altrove_b64url(random_bytes(18));
    $verifier = copia_altrove_b64url(random_bytes(48));
    $scade = time() + COPIA_ALTROVE_OAUTH_MINUTI * 60;
    $state = 'copia-altrove.' . $servizio . '.' . $nonce . '.' . $scade . '.' . copia_altrove_state_firma($servizio, $nonce, $scade, $utente);
    $q = [
        'client_id' => $o['client_id'], 'redirect_uri' => copia_altrove_redirect_uri(), 'response_type' => 'code', 'state' => $state,
        'code_challenge' => copia_altrove_b64url(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256',
    ];
    if ($servizio === 'gdrive') {
        $q += ['scope' => COPIA_ALTROVE_GDRIVE_SCOPE, 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'false'];
        $url = 'https://accounts.google.com/o/oauth2/v2/auth';
    } else {
        $q += ['token_access_type' => 'offline'];
        $url = 'https://www.dropbox.com/oauth2/authorize';
    }
    $vai = copia_altrove_cloud_url($servizio, $url) . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    $_SESSION['copia_altrove_oauth'] = ['servizio' => $servizio, 'nonce' => $nonce, 'verifier' => $verifier, 'scade' => $scade, 'utente' => $utente, 'vai' => $vai];
    return $vai;
}

/** L'indirizzo della pagina del servizio per la richiesta aperta in questa sessione, oppure ''. */
function copia_altrove_oauth_in_corso(): string {
    $s = $_SESSION['copia_altrove_oauth'] ?? null;
    return is_array($s) && (int)$s['scade'] > time() ? (string)$s['vai'] : '';
}

/**
 * Il ritorno dal servizio (admin/oauth-ritorno.php, solo amministratore). Controlla lo state, scambia il codice, legge
 * l'account e salva il collegamento. Finisce sempre con un redirect alla pagina dell'addon, mai altrove.
 */
function copia_altrove_oauth_ritorno(array $q): never {
    $vai = function (string $tipo, string $msg): never {
        flash($tipo, $msg);
        header('Location: ' . copia_altrove_admin_url(), true, 303);
        exit;
    };
    $state = is_string($q['state'] ?? null) ? $q['state'] : '';
    $s = $_SESSION['copia_altrove_oauth'] ?? null;
    $p = explode('.', $state);
    $ok = count($p) === 5 && $p[0] === 'copia-altrove' && copia_altrove_e_cloud($p[1]) && preg_match('/^[A-Za-z0-9_-]{24}$/', $p[2]) && ctype_digit($p[3])
        && is_array($s) && hash_equals((string)$s['nonce'], $p[2]) && $s['servizio'] === $p[1] && (int)$s['scade'] === (int)$p[3]
        && (int)$s['utente'] === (int)($_SESSION['admin_user_id'] ?? 0)
        && hash_equals(copia_altrove_state_firma($p[1], $p[2], (int)$p[3], (int)$s['utente']), $p[4]);
    if (!$ok) {
        sicurezza_log('impostazioni', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'copia altrove: ritorno OAuth respinto (state non valido)');
        $vai('error', 'Collegamento non riuscito: questa risposta non è partita da qui, oppure è già stata usata. Premi di nuovo "Salva e collega".');
    }
    unset($_SESSION['copia_altrove_oauth']); // una volta sola, anche se dopo qualcosa non va
    $servizio = $p[1];
    $nome = COPIA_ALTROVE_CLOUD[$servizio];
    if ((int)$p[3] < time()) $vai('error', 'Collegamento non riuscito: sono passati più di ' . COPIA_ALTROVE_OAUTH_MINUTI . ' minuti. Premi di nuovo "Salva e collega".');
    if (isset($q['error'])) {
        $vai('error', $q['error'] === 'access_denied' ? 'Non hai dato il permesso a ' . $nome . ': il collegamento non è fatto. Se hai sbagliato, premi di nuovo "Salva e collega" e scegli Consenti.' : 'Collegamento non riuscito: ' . $nome . ' ha risposto "' . mb_substr(preg_replace('/[^a-z_]/', '', (string)$q['error']), 0, 40) . '".');
    }
    $code = is_string($q['code'] ?? null) ? $q['code'] : '';
    if ($code === '' || strlen($code) > 2048) $vai('error', 'Collegamento non riuscito: ' . $nome . ' non ha mandato il codice. Premi di nuovo "Salva e collega".');
    try {
        $j = copia_altrove_token_chiama($servizio, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => copia_altrove_redirect_uri(), 'code_verifier' => (string)$s['verifier']]);
        if (empty($j['refresh_token'])) throw new CopiaAltroveErrore($nome . ' non ha dato il permesso permanente (refresh token). ' . ($servizio === 'gdrive' ? 'Togli l\'app da myaccount.google.com/permissions e collega di nuovo.' : 'Collega di nuovo.'));
        if ($servizio === 'gdrive' && !in_array(COPIA_ALTROVE_GDRIVE_SCOPE, explode(' ', (string)($j['scope'] ?? '')), true)) {
            throw new CopiaAltroveErrore('Nella pagina di Google non è stata spuntata la casella per i file di Drive: collega di nuovo e lasciala spuntata.');
        }
        copia_altrove_oauth_scrivi($servizio, [
            'refresh' => copia_altrove_cifra((string)$j['refresh_token']), 'accesso' => copia_altrove_cifra((string)$j['access_token']),
            'scade' => time() + max(60, (int)($j['expires_in'] ?? 3600)), 'collegato' => date('Y-m-d H:i:s'), 'cartella_id' => '', 'cartella_per' => '',
        ]);
        $account = copia_altrove_account($servizio);
        copia_altrove_oauth_scrivi($servizio, ['account' => $account]);
        sicurezza_log('impostazioni', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'copia altrove: collegato ' . $nome . ($account !== '' ? ' (' . $account . ')' : ''));
    } catch (Throwable $e) {
        $vai('error', 'Collegamento non riuscito: ' . $e->getMessage());
    }
    $vai('success', 'Collegato a ' . $nome . ($account !== '' ? ' come ' . $account : '') . '. Adesso premi "Prova la connessione".');
}

/** L'account collegato, in parole: "Mario Rossi (mario@gmail.com)". */
function copia_altrove_account(string $servizio): string {
    if ($servizio === 'gdrive') {
        $r = copia_altrove_cloud_api($servizio, 'GET', 'https://www.googleapis.com/drive/v3/about?fields=' . rawurlencode('user(displayName,emailAddress)'));
        $u = (array)(json_decode($r[2], true)['user'] ?? []);
        $nome = (string)($u['displayName'] ?? ''); $email = (string)($u['emailAddress'] ?? '');
    } else {
        $r = copia_altrove_cloud_api($servizio, 'POST', 'https://api.dropboxapi.com/2/users/get_current_account', ['Content-Type: application/json'], 'null');
        $j = (array)json_decode($r[2], true);
        $nome = (string)($j['name']['display_name'] ?? ''); $email = (string)($j['email'] ?? '');
    }
    if ($r[0] !== 200) throw new CopiaAltroveErrore($r[0] === 401 || $r[0] === 403 ? COPIA_ALTROVE_CLOUD[$servizio] . ' non dà l\'accesso: ' . ($servizio === 'dropbox' ? 'nella scheda Permissions dell\'app spunta account_info.read, files.metadata.read, files.content.write e files.content.read, premi Submit e collega di nuovo.' : 'controlla di aver abilitato la Google Drive API nel progetto.') : COPIA_ALTROVE_CLOUD[$servizio] . ' non risponde come dovrebbe (codice ' . $r[0] . ').');
    $nome = copia_altrove_testo($nome); $email = copia_altrove_testo($email);
    $t = trim($nome . ($email !== '' ? ($nome !== '' ? ' (' . $email . ')' : $email) : ''));
    return mb_substr($t, 0, 190);
}

/** Un testo che arriva da un servizio: senza a capo, senza caratteri a 4 byte (colonne utf8 e JSON delle impostazioni). */
function copia_altrove_testo(string $s): string {
    return trim(preg_replace('/[\x{10000}-\x{10FFFF}\r\n\t\0]/u', '', $s) ?? '');
}

/** Una chiamata alle API con l'accesso: un 401 rinnova l'accesso e riprova una volta. */
function copia_altrove_cloud_api(string $servizio, string $metodo, string $url, array $testate = [], $corpo = null, int $tempo = 120): array {
    $r = copia_altrove_cloud_http($servizio, $metodo, copia_altrove_cloud_url($servizio, $url), array_merge($testate, ['Authorization: Bearer ' . copia_altrove_accesso($servizio)]), $corpo, $tempo);
    if ($r[0] === 401) $r = copia_altrove_cloud_http($servizio, $metodo, copia_altrove_cloud_url($servizio, $url), array_merge($testate, ['Authorization: Bearer ' . copia_altrove_accesso($servizio, true)]), $corpo, $tempo);
    return $r;
}

/** "Scollega": il servizio toglie il permesso (se risponde), il sito dimentica i token. ID e segreto dell'app restano. */
function copia_altrove_scollega(string $servizio): string {
    if (!copia_altrove_e_cloud($servizio)) throw new CopiaAltroveErrore('Servizio sconosciuto.');
    $o = copia_altrove_oauth(copia_altrove_cfg(), $servizio);
    $revocato = false;
    if ($o['refresh'] !== '') {
        try {
            if ($servizio === 'gdrive') {
                $t = copia_altrove_decifra($o['refresh']);
                if ($t) $revocato = copia_altrove_cloud_http($servizio, 'POST', copia_altrove_cloud_url($servizio, 'https://oauth2.googleapis.com/revoke'), ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['token' => $t]), 30)[0] === 200;
            } else {
                $revocato = copia_altrove_cloud_api($servizio, 'POST', 'https://api.dropboxapi.com/2/auth/token/revoke', [], null, 30)[0] === 200;
            }
        } catch (Throwable $e) { $revocato = false; }
    }
    copia_altrove_oauth_dimentica($servizio);
    $c = copia_altrove_cfg();
    if ($c['tipo'] === $servizio && $c['attivo']) { $c['attivo'] = false; copia_altrove_cfg_scrivi($c); }
    return 'Scollegato da ' . COPIA_ALTROVE_CLOUD[$servizio] . '.' . ($revocato ? ' Anche ' . COPIA_ALTROVE_CLOUD[$servizio] . ' ha tolto il permesso.' : ' Il permesso dato all\'app si può togliere anche dal tuo account ' . COPIA_ALTROVE_CLOUD[$servizio] . '.')
        . ' L\'invio di ogni notte è spento; le copie già mandate restano dove sono.';
}

// ------------------------------------------------------------
// Google Drive
// ------------------------------------------------------------

final class CopiaAltroveGdrive {
    private string $cartella = '';
    /** nome => [id, ...] dall'ultimo elenco */
    private array $ids = [];

    public function __construct(private array $cfg) {
        // La cartella: quella già trovata (se esiste ancora e non è nel cestino), altrimenti si cerca o si crea, pezzo per pezzo.
        $o = copia_altrove_oauth($cfg, 'gdrive');
        if ($o['cartella_id'] !== '' && $o['cartella_per'] === $cfg['cartella']) {
            $r = $this->api('GET', 'drive/v3/files/' . rawurlencode($o['cartella_id']) . '?fields=id,trashed,mimeType');
            $j = json_decode($r[2], true);
            if ($r[0] === 200 && is_array($j) && empty($j['trashed'])) { $this->cartella = (string)$j['id']; return; }
            if ($r[0] !== 404 && $r[0] !== 200) $this->errore($r, 'aprire la cartella');
        }
        $padre = 'root';
        foreach (array_filter(explode('/', $cfg['cartella'] !== '' ? $cfg['cartella'] : 'copie-del-sito'), 'strlen') as $pezzo) {
            $q = "name = '" . self::q($pezzo) . "' and mimeType = 'application/vnd.google-apps.folder' and '" . self::q($padre) . "' in parents and trashed = false";
            $r = $this->api('GET', 'drive/v3/files?' . http_build_query(['q' => $q, 'fields' => 'files(id,name)', 'pageSize' => 10, 'spaces' => 'drive'], '', '&', PHP_QUERY_RFC3986));
            if ($r[0] !== 200) $this->errore($r, 'cercare la cartella');
            $f = json_decode($r[2], true)['files'][0]['id'] ?? '';
            if ($f === '') {
                $r = $this->api('POST', 'drive/v3/files?fields=id', ['Content-Type: application/json; charset=UTF-8'], json_encode(['name' => $pezzo, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$padre]]));
                if ($r[0] !== 200) $this->errore($r, 'creare la cartella «' . $cfg['cartella'] . '»');
                $f = (string)(json_decode($r[2], true)['id'] ?? '');
                if ($f === '') $this->errore($r, 'creare la cartella');
            }
            $padre = (string)$f;
        }
        $this->cartella = $padre;
        copia_altrove_oauth_scrivi('gdrive', ['cartella_id' => $padre, 'cartella_per' => $cfg['cartella']]);
    }

    /** Un valore dentro una query di Drive (fra apici): \ e ' con la barra davanti. */
    private static function q(string $s): string { return str_replace(['\\', "'"], ['\\\\', "\\'"], $s); }

    private function api(string $metodo, string $percorso, array $testate = [], $corpo = null, int $tempo = 120): array {
        return copia_altrove_cloud_api('gdrive', $metodo, 'https://www.googleapis.com/' . $percorso, $testate, $corpo, $tempo);
    }

    private function errore(array $r, string $cosa): never {
        $m = copia_altrove_cloud_motivo($r[2]);
        if ($r[0] === 403 && str_contains(strtolower($m), 'quota')) throw new CopiaAltroveErrore('Google Drive è pieno: libera spazio o togli copie vecchie.');
        if ($r[0] === 403 && (str_contains($m, 'has not been used') || str_contains($m, 'disabled'))) throw new CopiaAltroveErrore('La Google Drive API non è abilitata nel tuo progetto: Google Cloud Console, API e servizi, Libreria, Google Drive API, Abilita.');
        throw new CopiaAltroveErrore('Google Drive non ha lasciato ' . $cosa . ' (codice ' . $r[0] . ($m !== '' ? ', ' . $m : '') . ').');
    }

    /** Il caricamento "resumable": una sessione, poi pezzi da copia_altrove_pezzo() con Content-Range; riprende dopo un errore. */
    public function carica(string $locale, string $nome): void {
        $peso = (int)filesize($locale);
        $r = $this->api('POST', 'upload/drive/v3/files?uploadType=resumable&fields=id,name,size',
            ['Content-Type: application/json; charset=UTF-8', 'X-Upload-Content-Type: application/zip', 'X-Upload-Content-Length: ' . $peso],
            json_encode(['name' => $nome, 'parents' => [$this->cartella], 'mimeType' => 'application/zip']));
        $sessione = (string)($r[1]['location'] ?? '');
        // La sessione deve stare sullo stesso servizio: il token non va mai a un altro indirizzo.
        if ($r[0] !== 200 || !str_starts_with($sessione, copia_altrove_cloud_url('gdrive', 'https://www.googleapis.com/upload/drive/v3/files'))) $this->errore($r, 'cominciare il caricamento');
        $pezzo = copia_altrove_pezzo();
        $da = 0; $tentativi = 0; $finito = null;
        while ($finito === null) {
            $quanti = min($pezzo, $peso - $da);
            try {
                $r = copia_altrove_cloud_http('gdrive', 'PUT', $sessione, ['Content-Type: application/zip', 'Content-Range: bytes ' . ($peso ? $da . '-' . ($da + $quanti - 1) : '*') . '/' . $peso], ['file' => $locale, 'da' => $da, 'quanti' => $quanti], 900);
            } catch (CopiaAltroveErrore $e) {
                $r = [0, [], ''];
            }
            if ($r[0] === 200 || $r[0] === 201) { $finito = json_decode($r[2], true); break; }
            if ($r[0] === 308) { $da = self::fino($r[1]); $tentativi = 0; continue; }
            if ($r[0] === 404 || $r[0] === 410) throw new CopiaAltroveErrore('Google Drive ha chiuso il caricamento a metà: riprova.');
            if (($r[0] === 0 || $r[0] >= 500 || $r[0] === 429) && ++$tentativi <= 4) {
                // Un pezzo non arrivato: si chiede a Google a che punto è, e si riparte da lì.
                usleep(min(8, 2 ** $tentativi) * 250000);
                try {
                    $s = copia_altrove_cloud_http('gdrive', 'PUT', $sessione, ['Content-Range: bytes */' . $peso], '', 60);
                    if ($s[0] === 200 || $s[0] === 201) { $finito = json_decode($s[2], true); break; }
                    if ($s[0] === 308) $da = self::fino($s[1]);
                } catch (CopiaAltroveErrore $e) {}
                continue;
            }
            $this->errore($r, 'caricare la copia');
        }
        if (!is_array($finito) || (string)($finito['size'] ?? '') !== (string)$peso) throw new CopiaAltroveErrore('La copia è arrivata a Google Drive a metà (' . (int)($finito['size'] ?? 0) . ' byte su ' . $peso . '). Riprova.');
    }

    /** Il byte da cui ripartire, dalla testata Range di un 308 ("bytes=0-1048575"); niente Range = da capo. */
    private static function fino(array $testate): int {
        return preg_match('/bytes=0-(\d+)/', (string)($testate['range'] ?? ''), $m) ? (int)$m[1] + 1 : 0;
    }

    public function scrivi(string $nome, string $testo): void {
        $tmp = copia_dir() . '/.prova-' . bin2hex(random_bytes(6));
        file_put_contents($tmp, $testo);
        try { $this->carica($tmp, $nome); } finally { @unlink($tmp); }
    }

    public function elenco(): array {
        $this->ids = [];
        $token = '';
        for ($giro = 0; $giro < 50; $giro++) {
            $q = ['q' => "'" . self::q($this->cartella) . "' in parents and trashed = false", 'fields' => 'nextPageToken,files(id,name)', 'pageSize' => 1000, 'spaces' => 'drive'];
            if ($token !== '') $q['pageToken'] = $token;
            $r = $this->api('GET', 'drive/v3/files?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986));
            if ($r[0] !== 200) $this->errore($r, 'elencare i file');
            $j = json_decode($r[2], true);
            foreach ((array)($j['files'] ?? []) as $f) if (isset($f['name'], $f['id'])) $this->ids[(string)$f['name']][] = (string)$f['id'];
            $token = (string)($j['nextPageToken'] ?? '');
            if ($token === '') break;
        }
        return array_keys($this->ids);
    }

    public function elimina(string $nome): bool {
        if (!isset($this->ids[$nome])) $this->elenco();
        $ok = false;
        foreach ($this->ids[$nome] ?? [] as $id) {
            $r = $this->api('DELETE', 'drive/v3/files/' . rawurlencode($id));
            $ok = in_array($r[0], [200, 204, 404], true) || $ok;
        }
        unset($this->ids[$nome]);
        return $ok;
    }

    public function chiudi(): void {}
}

// ------------------------------------------------------------
// Dropbox
// ------------------------------------------------------------

final class CopiaAltroveDropbox {
    private string $base;

    public function __construct(private array $cfg) {
        // La cartella dentro la cartella dell'app ("" = la cartella dell'app). Dropbox crea da solo le cartelle che mancano.
        $this->base = $cfg['cartella'] !== '' ? '/' . trim($cfg['cartella'], '/') : '';
        $r = $this->rpc('users/get_current_account', null);
        if ($r[0] !== 200) $this->errore($r, 'entrare');
    }

    private function rpc(string $metodo, ?array $arg, int $tempo = 120): array {
        return copia_altrove_cloud_api('dropbox', 'POST', 'https://api.dropboxapi.com/2/' . $metodo, ['Content-Type: application/json'], $arg === null ? 'null' : json_encode($arg), $tempo);
    }

    /** Una chiamata "content" (i byte nel corpo, gli argomenti in Dropbox-API-Arg, JSON con i caratteri non ASCII come \uXXXX). */
    private function contenuto(string $metodo, array $arg, $corpo): array {
        return copia_altrove_cloud_api('dropbox', 'POST', 'https://content.dropboxapi.com/2/' . $metodo,
            ['Content-Type: application/octet-stream', 'Dropbox-API-Arg: ' . json_encode($arg)], $corpo, 900);
    }

    private function errore(array $r, string $cosa): never {
        $m = copia_altrove_cloud_motivo($r[2]);
        if (str_contains($m, 'insufficient_space')) throw new CopiaAltroveErrore('Dropbox è pieno: libera spazio o togli copie vecchie.');
        if (str_contains($m, 'missing_scope')) throw new CopiaAltroveErrore('All\'app di Dropbox manca un permesso: nella scheda Permissions spunta files.metadata.read, files.content.write e files.content.read, premi Submit, poi "Salva e collega" di nuovo.');
        throw new CopiaAltroveErrore('Dropbox non ha lasciato ' . $cosa . ' (codice ' . $r[0] . ($m !== '' ? ', ' . $m : '') . ').');
    }

    private function percorso(string $nome): string { return $this->base . '/' . $nome; }

    private function commit(string $nome): array {
        return ['path' => $this->percorso($nome), 'mode' => 'overwrite', 'autorename' => false, 'mute' => true, 'strict_conflict' => false];
    }

    /** Fino a un pezzo: un caricamento solo. Più grande: upload session (start, append_v2, finish), con la ripresa. */
    public function carica(string $locale, string $nome): void {
        $peso = (int)filesize($locale);
        $pezzo = copia_altrove_pezzo();
        if ($peso <= $pezzo) {
            $r = $this->riprova(fn() => $this->contenuto('files/upload', $this->commit($nome), ['file' => $locale, 'da' => 0, 'quanti' => $peso]));
            if ($r[0] !== 200) $this->errore($r, 'caricare la copia');
            $fatto = json_decode($r[2], true);
        } else {
            $r = $this->riprova(fn() => $this->contenuto('files/upload_session/start', ['close' => false], ['file' => $locale, 'da' => 0, 'quanti' => $pezzo]));
            $sessione = (string)(json_decode($r[2], true)['session_id'] ?? '');
            if ($r[0] !== 200 || $sessione === '') $this->errore($r, 'cominciare il caricamento');
            $da = $pezzo;
            $giri = 0;
            while ($da < $peso) {
                if (++$giri > 100000) throw new CopiaAltroveErrore('Caricamento su Dropbox fermo: riprova.');
                $quanti = min($pezzo, $peso - $da);
                $r = $this->riprova(fn() => $this->contenuto('files/upload_session/append_v2', ['cursor' => ['session_id' => $sessione, 'offset' => $da], 'close' => false], ['file' => $locale, 'da' => $da, 'quanti' => $quanti]));
                if ($r[0] === 200) { $da += $quanti; continue; }
                // Dropbox dice a che punto è (per esempio un pezzo arrivato due volte): si riparte da lì.
                $j = json_decode($r[2], true);
                if ($r[0] === 409 && ($j['error']['.tag'] ?? '') === 'incorrect_offset' && isset($j['error']['correct_offset'])) { $da = (int)$j['error']['correct_offset']; continue; }
                $this->errore($r, 'caricare la copia');
            }
            $r = $this->riprova(fn() => $this->contenuto('files/upload_session/finish', ['cursor' => ['session_id' => $sessione, 'offset' => $peso], 'commit' => $this->commit($nome)], ''));
            if ($r[0] !== 200) $this->errore($r, 'chiudere il caricamento');
            $fatto = json_decode($r[2], true);
        }
        if (!is_array($fatto) || (int)($fatto['size'] ?? -1) !== $peso) throw new CopiaAltroveErrore('La copia è arrivata a Dropbox a metà. Riprova.');
    }

    /** Fino a quattro tentativi quando Dropbox è occupato (429, 5xx) o la rete cade. */
    private function riprova(callable $f): array {
        for ($i = 1; ; $i++) {
            try { $r = $f(); } catch (CopiaAltroveErrore $e) { if ($e->getCode() !== 1 || $i >= 4) throw $e; $r = [0, [], '']; }
            if ($r[0] !== 0 && $r[0] !== 429 && $r[0] < 500) return $r;
            if ($i >= 4) return $r;
            $dopo = (int)($r[1]['retry-after'] ?? 0);
            usleep(min(10, max($dopo, 2 ** ($i - 1))) * 250000);
        }
    }

    public function scrivi(string $nome, string $testo): void {
        $r = $this->contenuto('files/upload', $this->commit($nome), $testo);
        if ($r[0] !== 200) $this->errore($r, 'scrivere nella cartella');
    }

    public function elenco(): array {
        $out = [];
        $r = $this->rpc('files/list_folder', ['path' => $this->base, 'recursive' => false, 'limit' => 2000]);
        for ($giro = 0; $giro < 50; $giro++) {
            if ($r[0] === 409 && str_contains($r[2], 'not_found')) return $out; // la cartella non c'è ancora
            if ($r[0] !== 200) $this->errore($r, 'elencare i file');
            $j = json_decode($r[2], true);
            foreach ((array)($j['entries'] ?? []) as $e) if (($e['.tag'] ?? '') === 'file' && isset($e['name'])) $out[] = (string)$e['name'];
            if (empty($j['has_more']) || empty($j['cursor'])) break;
            $r = $this->rpc('files/list_folder/continue', ['cursor' => (string)$j['cursor']]);
        }
        return $out;
    }

    public function elimina(string $nome): bool {
        $r = $this->rpc('files/delete_v2', ['path' => $this->percorso($nome)]);
        return $r[0] === 200 || ($r[0] === 409 && str_contains($r[2], 'not_found'));
    }

    public function chiudi(): void {}
}
