<?php
/**
 * Consegna delle email (2.1.0, tappa 2 dell'email marketing): far arrivare le email nella posta in arrivo, non nello spam.
 *
 *   - controllo del dominio: SPF, DKIM, DMARC e MX letti dal DNS con dns_get_record(), detti in parole semplici, con il
 *     testo esatto da chiedere all'hosting o da incollare nel DNS (consegna_controlla_dominio);
 *   - spedizione tramite un servizio esterno con le sue API HTTP: Brevo, Amazon SES (firma SigV4 scritta qui, niente
 *     librerie), Mailgun (consegna_invia_api, chiamata da posta_invia). Le chiavi si salvano cifrate (AES-256-GCM, chiave
 *     derivata da moduli_segreto()) e il pannello non le mostra più;
 *   - rimbalzi, segnalazioni di spam e uscite che i servizi mandano a api/posta-avvisi.php (webhook con un segreto
 *     nell'indirizzo, più la firma di Mailgun o quella di Amazon SNS): consegna_segnala;
 *   - più mittenti, ognuno confermato con un link mandato al suo indirizzo, uno predefinito (tabella posta_mittenti);
 *   - riscaldamento: il limite al giorno della newsletter parte basso e sale da solo fino al limite scritto.
 *
 * Indirizzi delle API per le prove: CMS_POSTA_API_PROVA nel config.local.php (per esempio 'http://127.0.0.1:8305')
 * prende il posto di schema e host di tutti e tre i servizi. CMS_DNS_PROVA è un file JSON con i record finti del DNS,
 * CMS_SNS_CERT_PROVA il certificato con cui le prove firmano gli avvisi di Amazon. Mai sui siti veri.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const CONSEGNA_SERVIZI = [
    'smtp' => 'Il server di posta (SMTP) o il server del sito',
    'brevo' => 'Brevo',
    'ses' => 'Amazon SES',
    'mailgun' => 'Mailgun',
];
/** I selettori DKIM più usati: cPanel (default), Google Workspace, Microsoft 365, Brevo, Mailgun, Mailchimp, altri. */
const CONSEGNA_SELETTORI = ['default', 'mail', 'cpanel', 'k1', 'k2', 'google', 'selector1', 'selector2', 'brevo1', 'brevo2', 'mailo', 'smtp', 'krs', 's1', 's2', 'dkim', 'mx'];
/** Domini di posta gratuita: come mittente di una newsletter non vanno (Gmail, Yahoo e Outlook pubblicano DMARC p=reject o quarantine). */
const CONSEGNA_GRATUITI = ['gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.it', 'ymail.com', 'outlook.com', 'outlook.it', 'hotmail.com', 'hotmail.it', 'live.com', 'live.it', 'msn.com', 'icloud.com', 'me.com', 'libero.it', 'virgilio.it', 'tiscali.it', 'alice.it', 'tim.it', 'email.it', 'aol.com', 'gmx.com', 'gmx.it', 'protonmail.com', 'proton.me'];

// ------------------------------------------------------------
// Tabelle
// ------------------------------------------------------------

function consegna_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $tabelle = [
        'posta_mittenti' => 'CREATE TABLE IF NOT EXISTS ' . tbl('posta_mittenti') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(190) NOT NULL,
            `nome` VARCHAR(100) NOT NULL DEFAULT '',
            `rispondi_a` VARCHAR(190) NOT NULL DEFAULT '',
            `predefinito` TINYINT(1) NOT NULL DEFAULT 0,
            `verificato_at` DATETIME DEFAULT NULL,
            `token` CHAR(64) DEFAULT NULL,
            `token_scade` DATETIME DEFAULT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `email` (`email`), KEY `token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // Gli avvisi arrivati dai servizi (rimbalzi, spam, uscite): uno per evento, la chiave impedisce di contarlo due volte.
        'posta_avvisi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('posta_avvisi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `quando` DATETIME NOT NULL,
            `servizio` VARCHAR(12) NOT NULL DEFAULT '',
            `evento` VARCHAR(12) NOT NULL DEFAULT '',
            `email` VARCHAR(190) NOT NULL DEFAULT '',
            `campagna_id` INT(11) NOT NULL DEFAULT 0,
            `esito` VARCHAR(160) NOT NULL DEFAULT '',
            `chiave` CHAR(64) NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `chiave` (`chiave`), KEY `quando` (`quando`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME);
    if (get_setting('consegna_schema_impronta', '') !== $impronta) {
        foreach ($tabelle as $sql) db()->exec($sql);
        save_setting('consegna_schema_impronta', $impronta);
    }
}

// ------------------------------------------------------------
// Chiavi cifrate
// ------------------------------------------------------------

/** La chiave che cifra le chiavi dei servizi: derivata dal segreto del sito, mai salvata. */
function consegna_chiave(): string {
    require_once __DIR__ . '/moduli.php';
    return hash_hmac('sha256', 'plumecms|posta|chiavi-dei-servizi', moduli_segreto(), true);
}

function consegna_cifra(string $s): string {
    if ($s === '') return '';
    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(12); $tag = '';
        $ct = openssl_encrypt($s, 'aes-256-gcm', consegna_chiave(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct !== false) return 'v1:' . base64_encode($iv . $tag . $ct);
    }
    $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'v2:' . base64_encode($n . sodium_crypto_secretbox($s, $n, consegna_chiave()));
}

/** '' se il testo non si decifra (segreto del sito cambiato, dato rovinato). */
function consegna_decifra(string $s): string {
    $d = base64_decode(substr($s, 3), true);
    if ($d === false) return '';
    if (str_starts_with($s, 'v1:') && strlen($d) > 28 && function_exists('openssl_decrypt')) {
        $r = openssl_decrypt(substr($d, 28), 'aes-256-gcm', consegna_chiave(), OPENSSL_RAW_DATA, substr($d, 0, 12), substr($d, 12, 16));
        return $r === false ? '' : $r;
    }
    if (str_starts_with($s, 'v2:') && function_exists('sodium_crypto_secretbox_open') && strlen($d) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        $r = sodium_crypto_secretbox_open(substr($d, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($d, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), consegna_chiave());
        return $r === false ? '' : $r;
    }
    return '';
}

// ------------------------------------------------------------
// Il servizio di spedizione
// ------------------------------------------------------------

/** La configurazione salvata (impostazione posta_servizio). Le chiavi restano cifrate: le apre consegna_segreto(). */
function consegna_config(): array {
    $c = json_decode((string)get_setting('posta_servizio', ''), true);
    $c = is_array($c) ? $c : [];
    $c += ['servizio' => 'smtp', 'brevo' => [], 'ses' => [], 'mailgun' => []];
    $c['brevo'] = (is_array($c['brevo']) ? $c['brevo'] : []) + ['chiave' => '', 'salvata' => ''];
    $c['ses'] = (is_array($c['ses']) ? $c['ses'] : []) + ['regione' => 'eu-west-1', 'id_chiave' => '', 'segreto' => '', 'set' => '', 'argomento' => '', 'salvata' => ''];
    $c['mailgun'] = (is_array($c['mailgun']) ? $c['mailgun'] : []) + ['dominio' => '', 'regione' => 'eu', 'chiave' => '', 'firma' => '', 'salvata' => ''];
    if (!isset(CONSEGNA_SERVIZI[$c['servizio']])) $c['servizio'] = 'smtp';
    return $c;
}

function consegna_config_salva(array $c): void {
    save_setting('posta_servizio', json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Una chiave del servizio, in chiaro (solo per spedire o controllare una firma: mai nell'HTML). */
function consegna_segreto(array $c, string $servizio, string $campo): string {
    $v = (string)($c[$servizio][$campo] ?? '');
    return $v === '' ? '' : consegna_decifra($v);
}

/** Il servizio con le API in uso: 'brevo', 'ses', 'mailgun', oppure '' (SMTP o mail() di PHP, come prima). */
function consegna_servizio_attivo(): string {
    try {
        $c = consegna_config();
    } catch (Throwable $e) {
        return '';
    }
    $s = $c['servizio'];
    if ($s === 'brevo' && $c['brevo']['chiave'] !== '') return 'brevo';
    if ($s === 'ses' && $c['ses']['segreto'] !== '' && $c['ses']['id_chiave'] !== '') return 'ses';
    if ($s === 'mailgun' && $c['mailgun']['chiave'] !== '' && $c['mailgun']['dominio'] !== '') return 'mailgun';
    return '';
}

/** Il servizio scelto dalla tendina, anche se mancano le chiavi (per i testi del pannello). Lo SMTP di Brevo conta come Brevo. */
function consegna_servizio_scelto(): string {
    $c = consegna_config();
    if ($c['servizio'] !== 'smtp') return $c['servizio'];
    $host = strtolower((string)((json_decode((string)get_setting('posta_smtp', ''), true) ?: [])['host'] ?? ''));
    if (str_contains($host, 'brevo.com') || str_contains($host, 'sendinblue.com')) return 'brevo';
    if (str_contains($host, 'amazonaws.com')) return 'ses';
    if (str_contains($host, 'mailgun.org')) return 'mailgun';
    return 'smtp';
}

/** L'indirizzo vero di un'API, o quello del server finto delle prove (CMS_POSTA_API_PROVA: schema e host). */
function consegna_url_api(string $url): string {
    if (defined('CMS_POSTA_API_PROVA') && preg_match('~^https?://[A-Za-z0-9.-]+(:\d+)?$~', (string)CMS_POSTA_API_PROVA)) {
        return (string)preg_replace('~^https://[^/]+~', rtrim((string)CMS_POSTA_API_PROVA, '/'), $url);
    }
    return $url;
}

/** Una chiamata HTTP: [codice, corpo]. Con curl se c'è, altrimenti con gli stream di PHP. */
function consegna_http(string $metodo, string $url, array $testa, string $corpo, int $attesa = 25): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $testa, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $attesa, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP]);
        if ($metodo !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
        $r = curl_exec($ch);
        $cod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        if ($r === false || $cod === 0) throw new RuntimeException('Il servizio non risponde (' . ($err ?: 'nessuna risposta') . ').');
        return [$cod, (string)$r];
    }
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $testa), 'content' => $corpo, 'timeout' => $attesa, 'ignore_errors' => true, 'follow_location' => 0],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $r = @file_get_contents($url, false, $ctx);
    $h = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
    if ($r === false && !$h) throw new RuntimeException('Il servizio non risponde (' . (error_get_last()['message'] ?? 'nessuna risposta') . ').');
    $cod = preg_match('~^HTTP/\S+\s+(\d{3})~', (string)($h[0] ?? ''), $m) ? (int)$m[1] : 0;
    return [$cod, (string)$r];
}

/**
 * La firma AWS Signature Version 4 di una richiesta. Ritorna le intestazioni da aggiungere (X-Amz-Date, Authorization).
 * $testa: le intestazioni da firmare, nome => valore (host lo aggiunge lei). $ora per le prove con i vettori di AWS.
 */
function consegna_sigv4(string $metodo, string $url, string $corpo, string $regione, string $servizio, string $idChiave, string $segreto, array $testa = [], ?int $ora = null): array {
    $u = parse_url($url);
    $host = strtolower((string)$u['host']) . (isset($u['port']) ? ':' . $u['port'] : '');
    $data = gmdate('Ymd\THis\Z', $ora ?? time());
    $giorno = substr($data, 0, 8);
    $percorso = (string)($u['path'] ?? '') ?: '/';
    $percorso = implode('/', array_map(fn($s) => rawurlencode(rawurldecode($s)), explode('/', $percorso)));
    $q = [];
    if (!empty($u['query'])) {
        foreach (explode('&', $u['query']) as $coppia) {
            [$k, $v] = array_pad(explode('=', $coppia, 2), 2, '');
            $q[] = rawurlencode(rawurldecode($k)) . '=' . rawurlencode(rawurldecode($v));
        }
        sort($q, SORT_STRING);
    }
    $firmare = ['host' => $host, 'x-amz-date' => $data];
    foreach ($testa as $k => $v) $firmare[strtolower($k)] = trim(preg_replace('/\s+/', ' ', (string)$v));
    ksort($firmare, SORT_STRING);
    $canon = '';
    foreach ($firmare as $k => $v) $canon .= "$k:$v\n";
    $nomi = implode(';', array_keys($firmare));
    $richiesta = "$metodo\n$percorso\n" . implode('&', $q) . "\n$canon\n$nomi\n" . hash('sha256', $corpo);
    $ambito = "$giorno/$regione/$servizio/aws4_request";
    $daFirmare = "AWS4-HMAC-SHA256\n$data\n$ambito\n" . hash('sha256', $richiesta);
    $k = hash_hmac('sha256', $giorno, 'AWS4' . $segreto, true);
    $k = hash_hmac('sha256', $regione, $k, true);
    $k = hash_hmac('sha256', $servizio, $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);
    $firma = hash_hmac('sha256', $daFirmare, $k);
    return ['X-Amz-Date' => $data, 'Authorization' => "AWS4-HMAC-SHA256 Credential=$idChiave/$ambito, SignedHeaders=$nomi, Signature=$firma"];
}

/** Le intestazioni di chi chiama, divise: [Reply-To, altre da tenere (List-Unsubscribe, X-...), From]. */
function consegna_intestazioni(string $intestazioni): array {
    $rispondi = ''; $altre = []; $from = '';
    foreach (preg_split('/\r?\n/', trim($intestazioni)) as $h) {
        if (!preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $h, $m)) continue;
        $n = strtolower($m[1]);
        if (in_array($n, ['mime-version', 'content-type', 'content-transfer-encoding', 'to', 'subject', 'date', 'message-id'], true)) continue;
        if ($n === 'reply-to') { $rispondi = trim($m[2]); continue; }
        if ($n === 'from') { $from = trim($m[2]); continue; }
        $altre[$m[1]] = trim($m[2]);
    }
    return [$rispondi, $altre, $from];
}

/**
 * Spedisce con le API del servizio. Lancia RuntimeException con un messaggio per una persona; PostaRifiutata
 * (codice 550) se il servizio rifiuta l'indirizzo del destinatario per sempre. Ritorna l'identificativo del messaggio.
 */
function consegna_invia_api(string $servizio, string $a, string $oggetto, string $corpo, string $intestazioni, array $allegati, array $mittente): string {
    $c = consegna_config();
    [$email, $nome] = $mittente;
    if ($email === '') throw new RuntimeException('Manca l\'indirizzo del mittente: scrivilo in Consegna delle email, Mittenti.');
    [$rispondi, $altre] = consegna_intestazioni($intestazioni);
    $rif = (string)($altre['X-Plume-Rif'] ?? '');
    if ($servizio === 'brevo') {
        $html = stripos($intestazioni, 'text/html') !== false;
        $dati = ['sender' => ['email' => $email] + ($nome !== '' ? ['name' => $nome] : []), 'to' => [['email' => $a]], 'subject' => $oggetto];
        if ($html) { $dati['htmlContent'] = $corpo; $dati['textContent'] = posta_testo_da_html($corpo); }
        else $dati['textContent'] = $corpo;
        if ($rispondi !== '' && preg_match('/<?([^<>\s]+@[^<>\s]+)>?\s*$/', $rispondi, $m)) $dati['replyTo'] = ['email' => $m[1]];
        if ($rif !== '') $altre['X-Mailin-custom'] = $rif;
        if ($altre) $dati['headers'] = $altre;
        if ($rif !== '') $dati['tags'] = ['plumecms'];
        foreach (array_slice($allegati, 0, 10) as $f) {
            $dati['attachment'][] = ['name' => preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)($f['nome'] ?? 'file')) ?: 'file', 'content' => base64_encode((string)($f['dati'] ?? ''))];
        }
        $chiave = consegna_segreto($c, 'brevo', 'chiave');
        if ($chiave === '') throw new RuntimeException('La chiave API di Brevo non si legge più: scrivila di nuovo in Consegna delle email.');
        [$cod, $r] = consegna_http('POST', consegna_url_api('https://api.brevo.com/v3/smtp/email'),
            ['Accept: application/json', 'Content-Type: application/json', 'api-key: ' . $chiave], json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $j = json_decode($r, true) ?: [];
        if ($cod >= 200 && $cod < 300) return (string)($j['messageId'] ?? '');
        consegna_errore_api('Brevo', $cod, (string)($j['message'] ?? $r));
    }
    // SES e Mailgun ricevono il messaggio MIME intero: lo stesso che partirebbe con SMTP.
    $raw = consegna_mime_intero($a, $oggetto, $corpo, $intestazioni, $allegati, $email, $nome);
    if ($servizio === 'ses') {
        $regione = (string)$c['ses']['regione'];
        if (!preg_match('/^[a-z]{2}(-[a-z]+)+-\d$/', $regione)) throw new RuntimeException('La regione di Amazon SES non è valida (per esempio eu-west-1).');
        $dati = ['FromEmailAddress' => $email, 'Destination' => ['ToAddresses' => [$a]], 'Content' => ['Raw' => ['Data' => base64_encode($raw)]]];
        if ($rif !== '') $dati['EmailTags'] = [['Name' => 'plume', 'Value' => $rif]];
        if ((string)$c['ses']['set'] !== '') $dati['ConfigurationSetName'] = (string)$c['ses']['set'];
        $json = json_encode($dati, JSON_UNESCAPED_SLASHES);
        $url = consegna_url_api("https://email.$regione.amazonaws.com/v2/email/outbound-emails");
        $firma = consegna_sigv4('POST', $url, $json, $regione, 'ses', (string)$c['ses']['id_chiave'], consegna_segreto($c, 'ses', 'segreto'), ['content-type' => 'application/json']);
        $testa = ['Content-Type: application/json'];
        foreach ($firma as $k => $v) $testa[] = "$k: $v";
        [$cod, $r] = consegna_http('POST', $url, $testa, $json);
        $j = json_decode($r, true) ?: [];
        if ($cod >= 200 && $cod < 300) return (string)($j['MessageId'] ?? '');
        consegna_errore_api('Amazon SES', $cod, (string)($j['message'] ?? $j['Message'] ?? $r));
    }
    if ($servizio === 'mailgun') {
        $dominio = strtolower((string)$c['mailgun']['dominio']);
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $dominio)) throw new RuntimeException('Il dominio di Mailgun non è valido (per esempio mg.esempio.it).');
        $base = $c['mailgun']['regione'] === 'us' ? 'https://api.mailgun.net' : 'https://api.eu.mailgun.net';
        $confine = 'plume-f-' . bin2hex(random_bytes(10));
        $campi = ['to' => $a] + ($rif !== '' ? ['v:plume' => $rif] : []);
        $body = '';
        foreach ($campi as $k => $v) $body .= "--$confine\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
        $body .= "--$confine\r\nContent-Disposition: form-data; name=\"message\"; filename=\"messaggio.eml\"\r\nContent-Type: message/rfc822\r\n\r\n$raw\r\n--$confine--\r\n";
        [$cod, $r] = consegna_http('POST', consegna_url_api("$base/v3/$dominio/messages.mime"),
            ['Authorization: Basic ' . base64_encode('api:' . consegna_segreto($c, 'mailgun', 'chiave')), "Content-Type: multipart/form-data; boundary=$confine"], $body);
        $j = json_decode($r, true) ?: [];
        if ($cod >= 200 && $cod < 300) return (string)($j['id'] ?? '');
        consegna_errore_api('Mailgun', $cod, (string)($j['message'] ?? $r));
    }
    throw new RuntimeException('Servizio di spedizione sconosciuto.');
}

/** Un errore dell'API in parole semplici. */
function consegna_errore_api(string $nome, int $cod, string $dettaglio): never {
    $dettaglio = mb_substr(trim(strip_tags($dettaglio)), 0, 200);
    if ($cod === 401 || $cod === 403) throw new RuntimeException("$nome non accetta la chiave (codice $cod): controlla di averla copiata intera e che sia attiva. ($dettaglio)");
    if ($cod === 429) throw new RuntimeException("$nome dice che sono partite troppe email in poco tempo (codice 429): le altre partono al prossimo giro. ($dettaglio)");
    if ($cod >= 500) throw new RuntimeException("$nome ha un problema in questo momento (codice $cod): riprova fra qualche minuto. ($dettaglio)");
    throw new RuntimeException("$nome ha rifiutato l'email (codice $cod): $dettaglio");
}

/** Il messaggio MIME completo (From, To, Subject, Date, Message-ID, poi quello che compone posta_mime). */
function consegna_mime_intero(string $a, string $oggetto, string $corpo, string $intestazioni, array $allegati, string $email, string $nome): string {
    [$tenute, $contenuto, $testo] = posta_mime($corpo, $intestazioni, $allegati);
    $tenute = array_values(array_filter($tenute, fn($h) => !preg_match('/^(From|To|Subject|Date|Message-ID):/i', $h)));
    $testa = array_merge([
        'Date: ' . date('r'),
        'From: ' . ($nome !== '' ? posta_oggetto($nome) . ' ' : '') . "<$email>",
        "To: <$a>",
        'Subject: ' . posta_oggetto($oggetto),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr((string)strrchr($email, '@'), 1) . '>',
    ], $tenute, $contenuto);
    return implode("\r\n", $testa) . "\r\n\r\n" . $testo;
}

// ------------------------------------------------------------
// Riferimento dell'email: chi l'ha ricevuta e da quale campagna o flusso
// ------------------------------------------------------------

/** "c12-f0-p0-i34-<firma>": viaggia nell'intestazione X-Plume-Rif e nei metadati dei servizi, torna con i loro avvisi. */
function consegna_rif(int $c, int $f, int $p, int $i): string {
    require_once __DIR__ . '/moduli.php';
    return "c$c-f$f-p$p-i$i-" . substr(hash_hmac('sha256', "rif|$c|$f|$p|$i", moduli_segreto()), 0, 16);
}

/** Il riferimento letto, se la firma regge: ['c' =>, 'f' =>, 'p' =>, 'i' =>], altrimenti null. */
function consegna_rif_leggi(string $rif): ?array {
    if (!preg_match('/^c(\d{1,9})-f(\d{1,9})-p(\d{1,9})-i(\d{1,9})-([a-f0-9]{16})$/', trim($rif), $m)) return null;
    if (!hash_equals(consegna_rif((int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4]), trim($rif))) return null;
    return ['c' => (int)$m[1], 'f' => (int)$m[2], 'p' => (int)$m[3], 'i' => (int)$m[4]];
}

// ------------------------------------------------------------
// Mittenti
// ------------------------------------------------------------

function consegna_mittenti(): array {
    consegna_schema();
    return db()->query('SELECT * FROM ' . tbl('posta_mittenti') . ' ORDER BY predefinito DESC, id')->fetchAll();
}

function consegna_mittente(int $id): ?array {
    if ($id <= 0) return null;
    consegna_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('posta_mittenti') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Il mittente predefinito confermato, o null (allora vale quello di Configurazione email). */
function consegna_mittente_predefinito(): ?array {
    try {
        consegna_schema();
        $r = db()->query('SELECT * FROM ' . tbl('posta_mittenti') . ' WHERE predefinito = 1 AND verificato_at IS NOT NULL LIMIT 1')->fetch();
        return $r ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** I mittenti confermati, per le tendine di campagne e flussi: id => "Nome <email>". */
function consegna_mittenti_scelta(): array {
    $out = [];
    foreach (consegna_mittenti() as $m) if ($m['verificato_at']) $out[(int)$m['id']] = ($m['nome'] !== '' ? $m['nome'] . ' ' : '') . '<' . $m['email'] . '>';
    return $out;
}

/** L'indirizzo di Configurazione email (mittente, o utente SMTP). */
function consegna_mittente_config(): string {
    $c = json_decode((string)get_setting('posta_smtp', ''), true);
    $c = is_array($c) ? $c : [];
    $e = trim((string)($c['mittente'] ?? '')) ?: trim((string)($c['utente'] ?? ''));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? mb_strtolower($e) : '';
}

/** L'indirizzo può comparire nel From: quello di Configurazione email o un mittente confermato. */
function consegna_mittente_permesso(string $email): bool {
    $email = mb_strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    if ($email === consegna_mittente_config()) return true;
    try {
        consegna_schema();
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('posta_mittenti') . ' WHERE email = ? AND verificato_at IS NOT NULL');
        $st->execute([$email]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Il mittente di un'email: il From di chi chiama se è permesso, altrimenti il predefinito, altrimenti $base ([email, nome]).
 */
function consegna_mittente_per(string $intestazioni, array $base): array {
    [, , $from] = consegna_intestazioni($intestazioni);
    if ($from !== '' && preg_match('/^(.*?)\s*<([^<>\s]+)>\s*$/', $from, $m)) {
        $nome = trim(str_replace('"', '', $m[1]));
        if (str_starts_with($nome, '=?UTF-8?B?')) $nome = (string)base64_decode(substr($nome, 10, -2));
        if (consegna_mittente_permesso($m[2])) return [mb_strtolower($m[2]), $nome !== '' ? $nome : $base[1]];
    } elseif ($from !== '' && consegna_mittente_permesso($from)) {
        return [mb_strtolower($from), $base[1]];
    }
    if ($p = consegna_mittente_predefinito()) return [(string)$p['email'], (string)$p['nome'] !== '' ? (string)$p['nome'] : $base[1]];
    return $base;
}

/** Le intestazioni From e Reply-To di un mittente scelto (campagna o flusso), '' se non è confermato. */
function consegna_intestazioni_mittente(int $id): string {
    $m = consegna_mittente($id);
    if (!$m || !$m['verificato_at']) return '';
    $nome = str_replace(['"', "\r", "\n"], '', (string)$m['nome']);
    return 'From: ' . ($nome !== '' ? posta_oggetto($nome) . ' ' : '') . '<' . $m['email'] . ">\r\n"
        . ($m['rispondi_a'] !== '' ? 'Reply-To: ' . $m['rispondi_a'] . "\r\n" : '');
}

/** Aggiunge un mittente e gli manda il link di conferma. Ritorna '' o il messaggio d'errore. */
function consegna_mittente_aggiungi(string $email, string $nome, string $rispondi): string {
    consegna_schema();
    $email = mb_strtolower(trim($email));
    $rispondi = mb_strtolower(trim($rispondi));
    $nome = mb_substr(trim(preg_replace('/[\x{10000}-\x{10FFFF}\r\n"<>]/u', '', $nome)), 0, 100);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) return 'L\'indirizzo del mittente non è valido.';
    if ($rispondi !== '' && !filter_var($rispondi, FILTER_VALIDATE_EMAIL)) return 'L\'indirizzo per le risposte non è valido.';
    $st = db()->prepare('SELECT id FROM ' . tbl('posta_mittenti') . ' WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetchColumn()) return 'Questo mittente c\'è già: se non hai ricevuto la conferma, premi "Rimanda la conferma".';
    db()->prepare('INSERT INTO ' . tbl('posta_mittenti') . ' (email, nome, rispondi_a, creato) VALUES (?, ?, ?, NOW())')->execute([$email, $nome, $rispondi]);
    return consegna_mittente_manda_conferma((int)db()->lastInsertId());
}

/** Manda (o rimanda) il link che conferma il mittente. Vale 2 giorni. */
function consegna_mittente_manda_conferma(int $id): string {
    $m = consegna_mittente($id);
    if (!$m) return 'Mittente non trovato.';
    require_once __DIR__ . '/moduli.php';
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE ' . tbl('posta_mittenti') . ' SET token = ?, token_scade = ? WHERE id = ?')->execute([hash('sha256', $token), date('Y-m-d H:i:s', time() + 2 * 86400), $id]);
    $link = consegna_url_api_sito('/api/mittente.php') . '?t=' . $token;
    $html = moduli_email_html('Conferma il mittente',
        '<p>Il pannello di ' . h(moduli_host()) . ' vuole spedire email con questo indirizzo come mittente: <b>' . h((string)$m['email']) . '</b>.</p>'
        . '<p>Se sei tu ad averlo chiesto, premi il pulsante. Il link vale 2 giorni.</p>'
        . '<p style="font-size:12px;color:#888">Se non sai di cosa si tratta, ignora questa email: senza conferma l&#039;indirizzo non si usa.</p>',
        'Confermo il mittente', $link);
    return posta_invia((string)$m['email'], 'Conferma il mittente delle email di ' . moduli_host(), $html, moduli_intestazioni(), 'prova')
        ? '' : 'Il link di conferma non è partito: ' . posta_errore();
}

/** Il link di conferma: vero se il mittente è confermato adesso. */
function consegna_mittente_conferma(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    consegna_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('posta_mittenti') . ' WHERE token = ? AND token_scade > NOW() LIMIT 1');
    $st->execute([hash('sha256', $token)]);
    $m = $st->fetch();
    if (!$m) return null;
    db()->prepare('UPDATE ' . tbl('posta_mittenti') . ' SET verificato_at = NOW(), token = NULL, token_scade = NULL WHERE id = ?')->execute([(int)$m['id']]);
    // Il primo confermato diventa il predefinito, se non ce n'è un altro.
    if (!db()->query('SELECT COUNT(*) FROM ' . tbl('posta_mittenti') . ' WHERE predefinito = 1')->fetchColumn()) consegna_mittente_predefinisci((int)$m['id']);
    return consegna_mittente((int)$m['id']);
}

function consegna_mittente_predefinisci(int $id): bool {
    $m = consegna_mittente($id);
    if ($id !== 0 && (!$m || !$m['verificato_at'])) return false;
    db()->exec('UPDATE ' . tbl('posta_mittenti') . ' SET predefinito = 0');
    if ($id) db()->prepare('UPDATE ' . tbl('posta_mittenti') . ' SET predefinito = 1 WHERE id = ?')->execute([$id]);
    return true;
}

/** Toglie un mittente. Campagne e flussi che lo usavano tornano al predefinito. */
function consegna_mittente_togli(int $id): void {
    consegna_schema();
    db()->prepare('DELETE FROM ' . tbl('posta_mittenti') . ' WHERE id = ?')->execute([$id]);
    foreach (['newsletter_campagne', 'newsletter_flussi'] as $t) {
        try { db()->prepare('UPDATE ' . tbl($t) . ' SET mittente_id = 0 WHERE mittente_id = ?')->execute([$id]); } catch (Throwable $e) {}
    }
}

// ------------------------------------------------------------
// Riscaldamento
// ------------------------------------------------------------

/** acceso, inizio (Y-m-d), partenza (email il primo giorno), arrivo (il limite da raggiungere), fattore (quanto sale ogni giorno). */
function consegna_riscaldamento(): array {
    $r = json_decode((string)get_setting('posta_riscaldamento', ''), true);
    $r = (is_array($r) ? $r : []) + ['acceso' => 0, 'inizio' => '', 'partenza' => 50, 'arrivo' => 0, 'fattore' => 1.5];
    $r['partenza'] = max(10, min(5000, (int)$r['partenza']));
    $r['fattore'] = max(1.1, min(3.0, (float)$r['fattore']));
    return $r;
}

/**
 * A che punto è: ['acceso', 'giorno' (1 il primo), 'oggi' (email al massimo nelle 24 ore), 'arrivo', 'finito',
 * 'giorni_mancanti', 'domani']. Il limite di oggi è partenza x fattore^(giorni passati), fino ad arrivo.
 */
function consegna_riscaldamento_stato(?int $ora = null, ?string $inizioScelto = null): array {
    $r = consegna_riscaldamento();
    if ($inizioScelto !== null) $r['inizio'] = $inizioScelto;
    $ora ??= time();
    $arrivo = (int)$r['arrivo'];
    if ($arrivo <= 0) {
        require_once __DIR__ . '/newsletter.php';
        $arrivo = (int)newsletter_config()['per_giorno'] ?: 2000;
    }
    $arrivo = max($r['partenza'], $arrivo);
    $inizio = strtotime((string)$r['inizio'] . ' 00:00:00') ?: $ora;
    $passati = max(0, (int)floor((strtotime(date('Y-m-d 00:00:00', $ora)) - strtotime(date('Y-m-d 00:00:00', $inizio))) / 86400 + 0.5));
    $giorno = fn(int $g) => (int)min($arrivo, round($r['partenza'] * pow($r['fattore'], $g)));
    $oggi = $giorno($passati);
    $mancano = 0;
    while ($giorno($passati + $mancano) < $arrivo && $mancano < 400) $mancano++;
    return ['acceso' => (bool)$r['acceso'], 'giorno' => $passati + 1, 'oggi' => $oggi, 'domani' => $giorno($passati + 1), 'arrivo' => $arrivo,
        'finito' => $oggi >= $arrivo, 'giorni_mancanti' => $mancano, 'partenza' => $r['partenza'], 'fattore' => $r['fattore'], 'inizio' => (string)$r['inizio']];
}

/** Il limite delle 24 ore che il riscaldamento impone adesso (0 = nessuno: spento). */
function consegna_limite_riscaldamento(): int {
    try {
        $s = consegna_riscaldamento_stato();
        return $s['acceso'] ? $s['oggi'] : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

// ------------------------------------------------------------
// Controllo del dominio
// ------------------------------------------------------------

/**
 * I record del DNS: lista di stringhe (TXT: il testo intero; MX: "priorità host"; A: l'IP), oppure null se il DNS non
 * risponde. Con CMS_DNS_PROVA (file JSON {"nome": {"TXT": [...], "MX": [...]}}) legge i record finti delle prove.
 */
function consegna_dns(string $nome, string $tipo): ?array {
    $nome = strtolower(rtrim($nome, '.'));
    if (defined('CMS_DNS_PROVA')) {
        $j = json_decode((string)@file_get_contents((string)CMS_DNS_PROVA), true);
        if (!is_array($j) || !empty($j['_spento'])) return null;
        return array_values((array)($j[$nome][$tipo] ?? []));
    }
    $costante = ['TXT' => DNS_TXT, 'MX' => DNS_MX, 'A' => DNS_A, 'NS' => DNS_NS, 'SOA' => DNS_SOA, 'CNAME' => DNS_CNAME][$tipo] ?? DNS_TXT;
    $r = @dns_get_record($nome, $costante);
    if ($r === false) return null;
    $out = [];
    foreach ($r as $x) {
        if ($tipo === 'TXT') $out[] = isset($x['entries']) ? implode('', $x['entries']) : (string)($x['txt'] ?? '');
        elseif ($tipo === 'MX') $out[] = (int)($x['pri'] ?? 0) . ' ' . (string)($x['target'] ?? '');
        elseif ($tipo === 'A') $out[] = (string)($x['ip'] ?? '');
        elseif ($tipo === 'NS') $out[] = (string)($x['target'] ?? '');
        elseif ($tipo === 'CNAME') $out[] = (string)($x['target'] ?? '');
        else $out[] = (string)($x['mname'] ?? 'soa');
    }
    return $out;
}

/** Il DNS risponde? Un dominio che esiste ha i suoi NS; se mancano anche quelli di un dominio noto, il DNS è muto. */
function consegna_dns_risponde(string $dominio): bool {
    if (defined('CMS_DNS_PROVA')) return consegna_dns($dominio, 'NS') !== null;
    $ns = consegna_dns($dominio, 'NS');
    if ($ns) return true;
    $noto = consegna_dns('iana.org', 'NS');
    return (bool)$noto;
}

/** I domini usati come mittenti (Configurazione email, mittenti aggiunti), il primo è quello del predefinito. */
function consegna_domini(): array {
    $d = [];
    $pre = consegna_mittente_predefinito();
    if ($pre) $d[] = substr((string)strrchr((string)$pre['email'], '@'), 1);
    if ($e = consegna_mittente_config()) $d[] = substr((string)strrchr($e, '@'), 1);
    foreach (consegna_mittenti() as $m) $d[] = substr((string)strrchr((string)$m['email'], '@'), 1);
    if (!$d) $d[] = preg_replace('/^www\./i', '', (string)(parse_url(cms_url_sito(), PHP_URL_HOST) ?: ''));
    return array_values(array_unique(array_filter(array_map('strtolower', $d))));
}

/** L'include dell'SPF del servizio scelto, e come si chiama nelle frasi. */
function consegna_spf_servizio(string $servizio): array {
    return [
        'brevo' => ['include:spf.brevo.com', 'Brevo'],
        'ses' => ['include:amazonses.com', 'Amazon SES'],
        'mailgun' => ['include:mailgun.org', 'Mailgun'],
    ][$servizio] ?? ['', 'il server di posta'];
}

/**
 * Controlla il dominio del mittente. Ritorna:
 *   ['dominio', 'dns' => il DNS risponde, 'servizio', 'voci' => [chiave => ['titolo', 'stato' (ok|attenzione|manca|sconosciuto),
 *    'frase', 'trovato' => [...], 'record' => ['tipo', 'nome', 'valore'] o null, 'dove' => dove si fa]], 'messaggio' (l'email
 *    da mandare all'hosting, '' se non serve niente), 'da_fare' => quante voci hanno bisogno di un intervento]
 */
function consegna_controlla_dominio(string $dominio, string $selettore = '', string $servizio = ''): array {
    $dominio = strtolower(trim(preg_replace('/^.*@/', '', $dominio)));
    $servizio = $servizio !== '' ? $servizio : consegna_servizio_scelto();
    $out = ['dominio' => $dominio, 'dns' => true, 'servizio' => $servizio, 'voci' => [], 'messaggio' => '', 'da_fare' => 0, 'valido' => true];
    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $dominio)) {
        $out['valido'] = false;
        return $out;
    }
    [$include, $nomeServ] = consegna_spf_servizio($servizio);
    if (in_array($dominio, CONSEGNA_GRATUITI, true)) {
        $out['gratuito'] = true;
    }
    if (!consegna_dns_risponde($dominio)) {
        $out['dns'] = false;
        return $out;
    }

    // SPF: un solo record v=spf1, che autorizza chi spedisce davvero.
    $txt = consegna_dns($dominio, 'TXT') ?? [];
    $spf = array_values(array_filter($txt, fn($t) => preg_match('/^v=spf1(\s|$)/i', trim($t))));
    $ip = '';
    if ($servizio === 'smtp') {
        $host = (string)((json_decode((string)get_setting('posta_smtp', ''), true) ?: [])['host'] ?? '');
        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP)) $ip = $host;
        elseif ($host !== '' && ($a = consegna_dns($host, 'A'))) $ip = (string)$a[0];
        if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) $ip = '';
    }
    $proposto = 'v=spf1 +a +mx' . ($ip !== '' ? " +ip4:$ip" : '') . ($include !== '' ? " $include" : '') . ' ~all';
    $v = ['titolo' => 'SPF: chi può spedire per il dominio', 'trovato' => $spf, 'record' => null, 'dove' => ''];
    if (count($spf) > 1) {
        $v['stato'] = 'attenzione';
        $v['frase'] = 'Ci sono ' . count($spf) . ' record SPF: deve essercene uno solo, altrimenti i server di posta li scartano tutti. Vanno uniti in uno.';
        $v['record'] = ['tipo' => 'TXT', 'nome' => $dominio, 'valore' => consegna_spf_unisci($spf, $include)];
    } elseif (!$spf) {
        $v['stato'] = 'manca';
        $v['frase'] = 'Manca. Senza SPF molti server mettono le tue email nello spam, o le rifiutano.';
        $v['record'] = ['tipo' => 'TXT', 'nome' => $dominio, 'valore' => $proposto];
    } else {
        $s = trim($spf[0]);
        $meccanismi = preg_match_all('/(^|\s)[+~?-]?(include:|a(\s|:|\/|$)|mx(\s|:|\/|$)|ptr|exists:|redirect=)/i', $s);
        if (preg_match('/(^|\s)\+?all(\s|$)/i', $s)) {
            $v['stato'] = 'attenzione';
            $v['frase'] = 'C\'è, ma finisce con "+all": vuol dire che chiunque può spedire a nome tuo. Va cambiato in "~all".';
            $v['record'] = ['tipo' => 'TXT', 'nome' => $dominio, 'valore' => preg_replace('/(^|\s)\+?all(\s|$)/i', '$1~all$2', consegna_spf_unisci([$s], $include))];
        } elseif ($include !== '' && stripos($s, $include) === false && !($servizio === 'brevo' && stripos($s, 'sendinblue.com') !== false)) {
            $v['stato'] = 'attenzione';
            $v['frase'] = "C'è, ma non autorizza $nomeServ, che spedisce le tue email. Va aggiunto \"$include\".";
            $v['record'] = ['tipo' => 'TXT', 'nome' => $dominio, 'valore' => consegna_spf_unisci([$s], $include)];
        } elseif ($meccanismi > 9) {
            $v['stato'] = 'attenzione';
            $v['frase'] = "C'è, ma chiede troppe ricerche al DNS ($meccanismi; il massimo è 10): alcuni server lo considerano sbagliato. Togli i servizi che non usi più.";
        } else {
            $v['stato'] = 'ok';
            $v['frase'] = 'A posto: il dominio dice chi può spedire per lui.' . ($servizio === 'ses' ? ' Con Amazon SES conta soprattutto la firma DKIM qui sotto.' : '');
        }
    }
    $v['dove'] = 'Nel DNS del dominio, record di tipo TXT. Su cPanel: Email, Autenticazione email (o "Email Deliverability"), SPF.';
    $out['voci']['spf'] = $v;

    // DKIM: la firma. Si cercano i selettori più comuni e quello scritto.
    $selettori = CONSEGNA_SELETTORI;
    $sel = strtolower(trim($selettore));
    if (preg_match('/^[a-z0-9]([a-z0-9._-]{0,62})$/', $sel)) array_unshift($selettori, $sel);
    $trovati = [];
    foreach (array_unique($selettori) as $s) {
        $r = consegna_dns("$s._domainkey.$dominio", 'TXT') ?? [];
        foreach ($r as $t) if (preg_match('/(^|;)\s*(v=DKIM1|k=rsa|k=ed25519|p=)/i', $t)) { $trovati[$s] = $t; break; }
    }
    $v = ['titolo' => 'DKIM: la firma delle email', 'trovato' => array_map(fn($s, $t) => "$s: " . mb_substr($t, 0, 80) . (mb_strlen($t) > 80 ? '...' : ''), array_keys($trovati), $trovati), 'record' => null];
    $deboli = [];
    $vuote = [];
    foreach ($trovati as $s => $t) {
        $p = preg_match('/(^|;)\s*p=([A-Za-z0-9+\/=\s]*)/', $t, $m) ? preg_replace('/\s+/', '', $m[2]) : '';
        if ($p === '') $vuote[] = $s;
        elseif (!preg_match('/k=ed25519/i', $t) && strlen($p) < 300) $deboli[] = $s;
    }
    $dove = [
        'smtp' => 'Lo attiva l\'hosting. Su cPanel: Email, Autenticazione email (o "Email Deliverability"), DKIM: "Installa il record suggerito".',
        'brevo' => 'In Brevo: Mittenti, domini e IP dedicati, Domini, "Autentica il dominio": Brevo ti dà i record da copiare nel DNS.',
        'ses' => 'In Amazon SES: Identità verificate, il tuo dominio, Autenticazione, DKIM: tre record CNAME da copiare nel DNS. Scrivi qui sopra uno dei selettori (la parte prima di "._domainkey").',
        'mailgun' => 'In Mailgun: Send, Domains, il tuo dominio, DNS records: il record TXT del DKIM da copiare nel DNS. Scrivi qui sopra il selettore (per esempio "mx" o "krs").',
    ][$servizio] ?? '';
    if (!$trovati) {
        $v['stato'] = 'manca';
        $v['frase'] = 'Non trovata con i selettori più comuni. Senza firma Gmail e Outlook si fidano poco delle tue email. Se il servizio ti ha dato un selettore, scrivilo nella casella e rifai il controllo.';
    } elseif (count($vuote) === count($trovati)) {
        $v['stato'] = 'attenzione';
        $v['frase'] = 'C\'è un record, ma la chiave è vuota (' . implode(', ', $vuote) . '): la firma è stata ritirata. Va rifatta.';
    } elseif ($deboli && count($deboli) === count($trovati)) {
        $v['stato'] = 'attenzione';
        $v['frase'] = 'C\'è, ma la chiave è corta (1024 bit o meno): oggi si usa una chiave da 2048 bit. Chiedi di rifarla più lunga.';
    } else {
        $v['stato'] = 'ok';
        $v['frase'] = 'A posto: le email sono firmate (selettore ' . implode(', ', array_keys($trovati)) . ').';
    }
    $v['dove'] = $dove;
    $out['voci']['dkim'] = $v;

    // DMARC: cosa fare con le email che non passano SPF o DKIM. Gmail e Yahoo lo chiedono da febbraio 2024 a chi manda newsletter.
    $dm = array_values(array_filter(consegna_dns("_dmarc.$dominio", 'TXT') ?? [], fn($t) => preg_match('/^v=DMARC1/i', trim($t))));
    $notifica = '';
    if (function_exists('posta_notifica_email')) $notifica = mb_strtolower(posta_notifica_email());
    $rua = $notifica !== '' && str_ends_with($notifica, '@' . $dominio) ? "; rua=mailto:$notifica" : '';
    $v = ['titolo' => 'DMARC: la regola per le email false', 'trovato' => $dm, 'record' => null];
    if (!$dm) {
        $v['stato'] = 'manca';
        $v['frase'] = 'Manca. Da febbraio 2024 Gmail e Yahoo lo chiedono a chi manda newsletter: senza, le email finiscono nello spam o vengono rifiutate.';
        $v['record'] = ['tipo' => 'TXT', 'nome' => "_dmarc.$dominio", 'valore' => "v=DMARC1; p=none$rua; adkim=r; aspf=r"];
    } elseif (count($dm) > 1) {
        $v['stato'] = 'attenzione';
        $v['frase'] = 'Ci sono ' . count($dm) . ' record DMARC: deve essercene uno solo. Tieni il primo e cancella gli altri.';
    } else {
        $pol = preg_match('/;\s*p=(none|quarantine|reject)/i', $dm[0], $m) ? strtolower($m[1]) : '';
        if ($pol === '') {
            $v['stato'] = 'attenzione';
            $v['frase'] = 'C\'è, ma senza la regola "p=": i server lo ignorano.';
            $v['record'] = ['tipo' => 'TXT', 'nome' => "_dmarc.$dominio", 'valore' => "v=DMARC1; p=none$rua; adkim=r; aspf=r"];
        } elseif ($pol === 'none') {
            $v['stato'] = 'ok';
            $v['frase'] = 'A posto (regola "p=none": i server guardano e non bloccano). Quando SPF e DKIM sono a posto da qualche settimana, puoi passare a "p=quarantine".';
        } else {
            $v['stato'] = 'ok';
            $v['frase'] = 'A posto (regola "p=' . $pol . '": le email false si fermano). Attenzione: con questa regola un SPF o un DKIM sbagliato blocca anche le tue email vere.';
        }
    }
    $v['dove'] = 'Nel DNS del dominio, record di tipo TXT con nome "_dmarc". Su cPanel: Dominio, Zone Editor (o "Editor zona DNS"), Aggiungi record TXT.';
    $out['voci']['dmarc'] = $v;

    // MX: dove arrivano le risposte e i rimbalzi.
    $mx = consegna_dns($dominio, 'MX') ?? [];
    $v = ['titolo' => 'MX: dove arrivano le risposte', 'trovato' => $mx, 'record' => null];
    if (!$mx) {
        $v['stato'] = 'attenzione';
        $v['frase'] = 'Nessun record MX: chi risponde alle tue email non riesce a scriverti, e alcuni server rifiutano un mittente senza MX. Chiedi all\'hosting di attivare la posta del dominio.';
    } else {
        $v['stato'] = 'ok';
        $v['frase'] = 'A posto: le risposte hanno dove arrivare.';
    }
    $v['dove'] = 'Lo imposta l\'hosting quando attivi le caselle email del dominio.';
    $out['voci']['mx'] = $v;

    // Il messaggio da mandare all'hosting, con i record da aggiungere o cambiare.
    $righe = [];
    foreach ($out['voci'] as $k => $x) {
        if ($x['stato'] === 'ok') continue;
        $out['da_fare']++;
        if ($x['record']) $righe[] = '- ' . strtoupper($k) . ': un record ' . $x['record']['tipo'] . ' con nome "' . $x['record']['nome'] . '" e valore:' . "\n  " . $x['record']['valore']
            . ($k === 'spf' && $spf ? "\n  (al posto di quello che c'è adesso: uno solo)" : '');
        elseif ($k === 'dkim') $righe[] = '- DKIM: attivare la firma DKIM per il dominio, con una chiave da 2048 bit, e pubblicare il record nel DNS.';
        elseif ($k === 'mx') $righe[] = '- MX: attivare la posta del dominio (record MX).';
        else $righe[] = '- ' . strtoupper($k) . ': ' . $x['frase'];
    }
    if ($righe) {
        $out['messaggio'] = "Buongiorno,\n\nper il dominio $dominio vi chiedo di sistemare il DNS della posta, così le email del sito non finiscono nello spam:\n\n"
            . implode("\n", $righe) . "\n\nGrazie.";
    }
    return $out;
}

/** Un solo SPF dai record che ci sono, con l'include del servizio: i meccanismi di tutti, una volta sola, e ~all in fondo. */
function consegna_spf_unisci(array $spf, string $include): string {
    $pezzi = [];
    $fine = '~all';
    foreach ($spf as $s) {
        foreach (preg_split('/\s+/', trim($s)) as $p) {
            if ($p === '' || strcasecmp($p, 'v=spf1') === 0) continue;
            if (preg_match('/^[+~?-]?all$/i', $p)) { if ($p[0] === '-') $fine = '-all'; continue; }
            if (!in_array(strtolower($p), array_map('strtolower', $pezzi), true)) $pezzi[] = $p;
        }
    }
    if ($include !== '' && !in_array(strtolower($include), array_map('strtolower', $pezzi), true)) $pezzi[] = $include;
    return trim('v=spf1 ' . implode(' ', $pezzi) . ' ' . $fine);
}

// ------------------------------------------------------------
// Avvisi dai servizi (webhook): rimbalzi, spam, uscite
// ------------------------------------------------------------

/** Il segreto nell'indirizzo del webhook di un servizio. Cambia con "Cambia gli indirizzi" (impostazione posta_avvisi_sale). */
function consegna_webhook_segreto(string $servizio): string {
    require_once __DIR__ . '/moduli.php';
    $sale = (string)get_setting('posta_avvisi_sale', '');
    if (strlen($sale) < 16) { $sale = bin2hex(random_bytes(16)); save_setting('posta_avvisi_sale', $sale); }
    return substr(hash_hmac('sha256', "avvisi|$servizio|$sale", moduli_segreto()), 0, 40);
}

/** L'indirizzo di un file del CMS dal sito pubblico: schema e host del sito, cartella del CMS. */
function consegna_url_api_sito(string $file): string {
    $u = parse_url(cms_url_sito());
    $origine = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? 'localhost') . (isset($u['port']) ? ':' . $u['port'] : '');
    return $origine . cms_base() . $file;
}

function consegna_webhook_url(string $servizio): string {
    return consegna_url_api_sito('/api/posta-avvisi.php') . '?s=' . $servizio . '&k=' . consegna_webhook_segreto($servizio);
}

/** L'ultimo avviso arrivato da un servizio ('' se nessuno): per dire nel pannello che il collegamento funziona. */
function consegna_ultimo_avviso(string $servizio): string {
    consegna_schema();
    $st = db()->prepare('SELECT quando FROM ' . tbl('posta_avvisi') . ' WHERE servizio = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$servizio]);
    return (string)$st->fetchColumn();
}

function consegna_avvisi_recenti(int $quanti = 30): array {
    consegna_schema();
    return db()->query('SELECT * FROM ' . tbl('posta_avvisi') . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $quanti)))->fetchAll();
}

/**
 * Un evento arrivato da un servizio. $evento: 'rimbalzo' (indirizzo che non esiste), 'temporaneo' (casella piena, per
 * ora), 'spam' (la persona ha premuto "è spam"), 'uscita' (si è tolta dal link del servizio), 'consegnata' (solo registro).
 * $chiave: l'identificativo dell'evento dato dal servizio (lo stesso evento due volte conta una volta).
 * Ritorna l'esito in parole ("iscritto segnato rimbalzato", "già ricevuto"...).
 */
function consegna_segnala(string $servizio, string $evento, string $email, string $rif, string $chiave, string $dettaglio = ''): string {
    consegna_schema();
    require_once __DIR__ . '/newsletter.php';
    newsletter_schema();
    newsletter_flussi_schema();
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $r = consegna_rif_leggi($rif);
    $ins = db()->prepare('INSERT IGNORE INTO ' . tbl('posta_avvisi') . ' (quando, servizio, evento, email, campagna_id, esito, chiave) VALUES (NOW(), ?, ?, ?, ?, ?, ?)');
    $ins->execute([$servizio, $evento, mb_substr($email, 0, 190), $r ? $r['c'] : 0, 'ricevuto', hash('sha256', "$servizio|$chiave|$evento|$email")]);
    if ($ins->rowCount() !== 1) return 'già ricevuto';
    $idAvviso = (int)db()->lastInsertId();
    // Chi è: dal riferimento firmato (e l'email deve essere la sua), altrimenti dall'email.
    $iscritto = null;
    if ($r && $r['i']) {
        $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
        $st->execute([$r['i']]);
        $iscritto = $st->fetch() ?: null;
        if ($iscritto && $email !== '' && mb_strtolower((string)$iscritto['email']) !== $email) { $iscritto = null; $r = null; }
    }
    if (!$iscritto && $email !== '') {
        $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE email = ?');
        $st->execute([$email]);
        $iscritto = $st->fetch() ?: null;
    }
    $esito = 'nessun iscritto con questo indirizzo';
    if ($iscritto) {
        $id = (int)$iscritto['id'];
        $motivo = mb_substr(trim(($dettaglio !== '' ? $dettaglio : $evento) . ' (avviso di ' . (CONSEGNA_SERVIZI[$servizio] ?? $servizio) . ')'), 0, 255);
        if ($evento === 'rimbalzo') {
            newsletter_rimbalzato($id, $motivo);
            $esito = $iscritto['stato'] === 'attivo' ? 'iscritto segnato rimbalzato' : 'rimbalzo registrato (non era attivo)';
        } elseif ($evento === 'temporaneo') {
            db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET errori_di_fila = errori_di_fila + 1, ultimo_errore = ? WHERE id = ?')->execute([$motivo, $id]);
            $esito = 'errore temporaneo contato';
        } elseif ($evento === 'spam') {
            newsletter_segnala_spam($id);
            $esito = 'ha segnalato spam: fuori e in soppressione';
        } elseif ($evento === 'uscita') {
            if ($iscritto['stato'] === 'attivo') newsletter_esci($id);
            $esito = 'uscito dal link del servizio';
        } else {
            $esito = 'registrato';
        }
        if ($r && in_array($evento, ['rimbalzo', 'spam', 'uscita'], true)) newsletter_evento_interno($evento, $r['c'], $r['f'], $r['p'], $id);
    }
    db()->prepare('UPDATE ' . tbl('posta_avvisi') . ' SET esito = ? WHERE id = ?')->execute([mb_substr($esito, 0, 160), $idAvviso]);
    return $esito;
}

/** Gli avvisi di Brevo (webhook "transazionale"): un evento per richiesta, JSON. */
function consegna_avvisi_brevo(array $j): array {
    $mappa = ['hard_bounce' => 'rimbalzo', 'invalid_email' => 'rimbalzo', 'blocked' => 'rimbalzo', 'soft_bounce' => 'temporaneo', 'spam' => 'spam', 'complaint' => 'spam', 'unsubscribed' => 'uscita'];
    $out = [];
    $eventi = isset($j[0]) && is_array($j[0]) ? $j : [$j];
    foreach (array_slice($eventi, 0, 50) as $e) {
        $tipo = strtolower((string)($e['event'] ?? ''));
        if (!isset($mappa[$tipo])) { $out[] = "evento $tipo ignorato"; continue; }
        $chiave = (string)($e['id'] ?? '') . '|' . (string)($e['message-id'] ?? '') . '|' . (string)($e['ts_event'] ?? $e['date'] ?? '');
        $out[] = consegna_segnala('brevo', $mappa[$tipo], (string)($e['email'] ?? ''), (string)($e['X-Mailin-custom'] ?? $e['x-mailin-custom'] ?? ''), $chiave, (string)($e['reason'] ?? $tipo));
    }
    return $out;
}

/** Gli avvisi di Mailgun (webhook firmato): ritorna null se la firma non regge. */
function consegna_avvisi_mailgun(array $j): ?array {
    $c = consegna_config();
    $chiave = consegna_segreto($c, 'mailgun', 'firma');
    $s = is_array($j['signature'] ?? null) ? $j['signature'] : [];
    $ts = (string)($s['timestamp'] ?? ''); $tok = (string)($s['token'] ?? ''); $fir = (string)($s['signature'] ?? '');
    if ($chiave === '' || !ctype_digit($ts) || $tok === '' || !hash_equals(hash_hmac('sha256', $ts . $tok, $chiave), $fir)) return null;
    if (abs(time() - (int)$ts) > 900) return null;
    $e = is_array($j['event-data'] ?? null) ? $j['event-data'] : [];
    $tipo = strtolower((string)($e['event'] ?? ''));
    $sev = strtolower((string)($e['severity'] ?? ''));
    $evento = match (true) {
        $tipo === 'failed' && $sev === 'permanent' => 'rimbalzo',
        $tipo === 'failed' => 'temporaneo',
        $tipo === 'complained' => 'spam',
        $tipo === 'unsubscribed' => 'uscita',
        default => '',
    };
    if ($evento === '') return ["evento $tipo ignorato"];
    $var = is_array($e['user-variables'] ?? null) ? $e['user-variables'] : [];
    $motivo = (string)($e['delivery-status']['description'] ?? $e['delivery-status']['message'] ?? $e['reason'] ?? $tipo);
    return [consegna_segnala('mailgun', $evento, (string)($e['recipient'] ?? ''), (string)($var['plume'] ?? ''), (string)($e['id'] ?? $tok), $motivo)];
}

/** La stringa che Amazon SNS firma, campo per campo. */
function consegna_sns_da_firmare(array $m): string {
    $campi = ($m['Type'] ?? '') === 'Notification' ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'] : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
    $s = '';
    foreach ($campi as $k) if (isset($m[$k]) && is_string($m[$k])) $s .= "$k\n{$m[$k]}\n";
    return $s;
}

/** La firma di Amazon SNS regge? Certificato solo da sns.<regione>.amazonaws.com (o quello delle prove). */
function consegna_sns_firma_valida(array $m): bool {
    $url = (string)($m['SigningCertURL'] ?? $m['SigningCertUrl'] ?? '');
    $firma = base64_decode((string)($m['Signature'] ?? ''), true);
    if (!$firma) return false;
    if (defined('CMS_SNS_CERT_PROVA')) {
        $pem = (string)@file_get_contents((string)CMS_SNS_CERT_PROVA);
    } else {
        if (!preg_match('~^https://sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?/[A-Za-z0-9._/-]+\.pem$~', $url)) return false;
        $chiave = 'posta_sns_cert_' . md5($url);
        $pem = (string)get_setting($chiave, '');
        if ($pem === '') {
            try { [$cod, $pem] = consegna_http('GET', $url, [], '', 10); } catch (Throwable $e) { return false; }
            if ($cod !== 200 || !str_contains($pem, 'BEGIN CERTIFICATE')) return false;
            save_setting($chiave, $pem);
        }
    }
    $pub = @openssl_pkey_get_public($pem);
    if (!$pub) return false;
    $algo = (string)($m['SignatureVersion'] ?? '1') === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
    return openssl_verify(consegna_sns_da_firmare($m), $firma, $pub, $algo) === 1;
}

/** Gli avvisi di Amazon SES che arrivano da SNS. Ritorna null se la firma non regge o l'argomento non è quello. */
function consegna_avvisi_ses(array $m): ?array {
    if (!consegna_sns_firma_valida($m)) return null;
    $c = consegna_config();
    $arn = (string)($m['TopicArn'] ?? '');
    if ($c['ses']['argomento'] !== '' && $arn !== $c['ses']['argomento']) return null;
    $tipo = (string)($m['Type'] ?? '');
    if ($tipo === 'SubscriptionConfirmation') {
        // Il primo messaggio: si conferma l'iscrizione all'argomento e si ricorda l'argomento.
        $url = (string)($m['SubscribeURL'] ?? '');
        $ok = false;
        if (preg_match('~^https://sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?/~', $url) && !defined('CMS_SNS_CERT_PROVA')) {
            try { [$cod] = consegna_http('GET', $url, [], '', 10); $ok = $cod === 200; } catch (Throwable $e) { $ok = false; }
        } elseif (defined('CMS_SNS_CERT_PROVA')) {
            $ok = true;
        }
        if ($ok && $c['ses']['argomento'] === '' && preg_match('/^arn:aws[a-z-]*:sns:[a-z0-9-]+:\d{12}:[A-Za-z0-9_-]{1,256}$/', $arn)) {
            $c['ses']['argomento'] = $arn;
            consegna_config_salva($c);
        }
        return [$ok ? 'iscrizione ad Amazon SNS confermata' : 'iscrizione ad Amazon SNS non confermata'];
    }
    if ($tipo !== 'Notification') return ['messaggio ' . $tipo . ' ignorato'];
    $n = json_decode((string)($m['Message'] ?? ''), true);
    if (!is_array($n)) return ['messaggio senza dati'];
    $quale = (string)($n['notificationType'] ?? $n['eventType'] ?? '');
    $rif = (string)(($n['mail']['tags']['plume'][0] ?? '') ?: '');
    if ($rif === '' && is_array($n['mail']['headers'] ?? null)) {
        foreach ($n['mail']['headers'] as $h) if (strcasecmp((string)($h['name'] ?? ''), 'X-Plume-Rif') === 0) $rif = (string)($h['value'] ?? '');
    }
    $chiave = (string)($m['MessageId'] ?? '');
    $out = [];
    if ($quale === 'Bounce') {
        $perm = (string)($n['bounce']['bounceType'] ?? '') === 'Permanent';
        foreach (array_slice((array)($n['bounce']['bouncedRecipients'] ?? []), 0, 50) as $d) {
            $out[] = consegna_segnala('ses', $perm ? 'rimbalzo' : 'temporaneo', (string)($d['emailAddress'] ?? ''), $rif, $chiave, (string)($d['diagnosticCode'] ?? ($n['bounce']['bounceSubType'] ?? 'Bounce')));
        }
    } elseif ($quale === 'Complaint') {
        foreach (array_slice((array)($n['complaint']['complainedRecipients'] ?? []), 0, 50) as $d) {
            $out[] = consegna_segnala('ses', 'spam', (string)($d['emailAddress'] ?? ''), $rif, $chiave, (string)($n['complaint']['complaintFeedbackType'] ?? 'abuse'));
        }
    } else {
        $out[] = "evento $quale ignorato";
    }
    return $out;
}
