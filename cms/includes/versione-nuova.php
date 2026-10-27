<?php
/**
 * "C'e una versione nuova" (1.3.0, 27 ottobre 2026): il pannello chiede al sito ufficiale di plumeCMS qual e l'ultima
 * versione e, se e piu nuova di questa, l'amministratore la scarica e la mette nella coda di Aggiornamenti con un clic.
 * Da li si applica come uno zip caricato a mano: snapshot, file, ripristino da solo se qualcosa va storto.
 *
 * Da dove: CMS_AGGIORNAMENTI_URL nel config.local.php (l'indirizzo di un plumeCMS, senza barra finale), altrimenti
 * VERSIONE_SITO_UFFICIALE. Il sito ufficiale risponde con api/versione.php per la serie VERSIONE_SERIE del suo
 * download manager: versione, nome, sha256 e dimensione del file.
 *
 * Quando: al massimo una volta al giorno (la risposta si ricorda nell'impostazione versione_controllo_cache), mai
 * durante il caricamento di una pagina: la dashboard e la pagina Aggiornamenti mostrano quello che sanno e, se e
 * vecchio, il browser chiede il controllo dopo (aggiornamenti.php, azione versione_controlla). Solo l'amministratore.
 * Si spegne da Aggiornamenti (impostazione versione_controllo = 0).
 *
 * Sicurezza: solo HTTPS (con il certificato verificato), timeout brevi, nessun redirect seguito, risposta JSON di al
 * massimo 16 KB, zip di al massimo VERSIONE_MAX_ZIP, lunghezza e sha256 controllati prima di metterlo in coda; in coda
 * passa da agg_leggi_manifest come ogni pacchetto (firma obbligatoria se la pagina Sicurezza la chiede). Se la rete non
 * c'e, il controllo dice "non raggiungibile" e il pannello funziona come prima.
 *
 * Solo per le prove: con CMS_AGGIORNAMENTI_HTTP_PROVA definita a true nel config.local.php si accetta anche http://
 * (due installazioni locali, prove/prova-versione.js). Su un sito vero non va mai definita.
 */

require_once __DIR__ . '/aggiornamenti.php';

const VERSIONE_SITO_UFFICIALE = 'https://duhtron.it/test';
const VERSIONE_SERIE = 'plumecms-ultima-aggiornamento';
const VERSIONE_OGNI = 86400;            // un controllo al giorno
const VERSIONE_MAX_ZIP = 52428800;      // 50 MB
const VERSIONE_TIMEOUT = 5;             // secondi per la risposta JSON
const VERSIONE_TIMEOUT_ZIP = 60;        // secondi per lo zip

/** L'indirizzo del sito ufficiale (senza barra finale). */
function versione_sito(): string {
    $u = defined('CMS_AGGIORNAMENTI_URL') ? (string)CMS_AGGIORNAMENTI_URL : VERSIONE_SITO_UFFICIALE;
    return rtrim(trim($u), '/');
}

/** Il controllo e acceso? (Aggiornamenti, "Controlla da solo") */
function versione_attivo(): bool {
    return (string)get_setting('versione_controllo', '1') !== '0';
}

/** Un indirizzo che si puo chiamare: https (o http solo con la costante delle prove), con un host. */
function versione_url_ok(string $u): bool {
    $p = parse_url($u);
    if (!is_array($p) || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return false;
    $schema = strtolower((string)($p['scheme'] ?? ''));
    return $schema === 'https' || ($schema === 'http' && defined('CMS_AGGIORNAMENTI_HTTP_PROVA') && CMS_AGGIORNAMENTI_HTTP_PROVA === true);
}

/** Lo stesso host (e schema e porta) del sito ufficiale: lo zip si scarica solo da li. */
function versione_stesso_host(string $u): bool {
    $a = parse_url(versione_sito()); $b = parse_url($u);
    if (!is_array($a) || !is_array($b)) return false;
    return strtolower((string)($a['scheme'] ?? '')) === strtolower((string)($b['scheme'] ?? '')) && strtolower((string)($a['host'] ?? '')) === strtolower((string)($b['host'] ?? ''))
        && (int)($a['port'] ?? 0) === (int)($b['port'] ?? 0);
}

/**
 * Una GET verso il sito ufficiale, senza seguire redirect. $verso: un file dove scrivere (lo zip) oppure null (la
 * risposta torna come testo). Al massimo $max byte. Ritorna il testo (o '' se scritto su file); eccezione se va male.
 */
function versione_http(string $url, int $max, int $timeout, ?string $verso = null): string {
    if (!versione_url_ok($url)) throw new RuntimeException('Indirizzo non ammesso (serve https): ' . $url);
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => $timeout, 'follow_location' => 0, 'max_redirects' => 0, 'ignore_errors' => true,
            'header' => "User-Agent: " . CMS_NOME . '/' . cms_versione() . "\r\nAccept: application/json, application/zip\r\nConnection: close\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
    ]);
    $vecchio = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', (string)$timeout);
    $h = @fopen($url, 'rb', false, $ctx);
    ini_set('default_socket_timeout', (string)$vecchio);
    if (!$h) throw new RuntimeException('Il sito ' . parse_url($url, PHP_URL_HOST) . ' non risponde (rete assente o sito spento).');
    try {
        $meta = stream_get_meta_data($h);
        $righe = (array)($meta['wrapper_data'] ?? []);
        $codice = 0;
        foreach ($righe as $r) if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string)$r, $m)) $codice = (int)$m[1]; // l'ultima riga di stato
        if ($codice >= 300 && $codice < 400) throw new RuntimeException('Il sito ufficiale ha risposto con un rimando (' . $codice . '): per sicurezza non si segue.');
        if ($codice !== 200) throw new RuntimeException('Il sito ufficiale ha risposto ' . ($codice ?: 'in modo illeggibile') . '.');
        $out = $verso !== null ? @fopen($verso, 'wb') : null;
        if ($verso !== null && !$out) throw new RuntimeException('Non riesco a scrivere in ' . dirname($verso));
        $testo = ''; $letti = 0;
        while (!feof($h)) {
            $pezzo = fread($h, 65536);
            if ($pezzo === false) break;
            if (stream_get_meta_data($h)['timed_out'] ?? false) throw new RuntimeException('Il sito ufficiale e troppo lento: riprova piu tardi.');
            $letti += strlen($pezzo);
            if ($letti > $max) throw new RuntimeException('La risposta e piu grande del previsto: interrotta.');
            if ($out) fwrite($out, $pezzo); else $testo .= $pezzo;
        }
        if ($out) fclose($out);
        return $testo;
    } finally {
        fclose($h);
    }
}

/** Chiede al sito ufficiale l'ultima versione. Ritorna i dati controllati: versione, nome, sha256, dimensione, url. */
function versione_chiedi(): array {
    $u = versione_sito() . '/api/versione.php?serie=' . rawurlencode(VERSIONE_SERIE);
    $j = json_decode(versione_http($u, 16384, VERSIONE_TIMEOUT), true);
    if (!is_array($j)) throw new RuntimeException('Il sito ufficiale non ha dato una risposta leggibile.');
    $v = (string)($j['versione'] ?? ''); $nome = (string)($j['nome'] ?? ''); $sha = strtolower((string)($j['sha256'] ?? ''));
    $dim = (int)($j['dimensione'] ?? 0); $url = (string)($j['url'] ?? '');
    if (!preg_match('/^\d+(\.\d+){1,3}$/', $v)) throw new RuntimeException('Versione non valida nella risposta.');
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,120}\.zip$/', $nome)) throw new RuntimeException('Nome del file non valido nella risposta.');
    if (!preg_match('/^[a-f0-9]{64}$/', $sha)) throw new RuntimeException('Impronta sha256 non valida nella risposta.');
    if ($dim <= 0 || $dim > VERSIONE_MAX_ZIP) throw new RuntimeException('Dimensione del file non ammessa nella risposta.');
    if (!versione_url_ok($url) || !versione_stesso_host($url)) throw new RuntimeException('Il file sta su un altro sito: per sicurezza non si scarica.');
    return ['versione' => $v, 'nome' => $nome, 'sha256' => $sha, 'dimensione' => $dim, 'url' => $url];
}

/** Quello che si sa: ['quando' => time, 'dati' => [...] | null, 'errore' => '' ] oppure null se non si e mai controllato. */
function versione_cache(): ?array {
    $c = json_decode((string)get_setting('versione_controllo_cache', ''), true);
    return is_array($c) && isset($c['quando']) ? $c : null;
}

/** Il controllo e da rifare? (spento: mai) */
function versione_da_controllare(): bool {
    if (!versione_attivo()) return false;
    $c = versione_cache();
    return !$c || (int)$c['quando'] < time() - VERSIONE_OGNI || (string)($c['sito'] ?? '') !== versione_sito();
}

/** Controlla adesso (o usa quello che sa, se ha controllato da meno di un giorno e non si forza). Non lancia mai eccezioni. */
function versione_controlla(bool $forza = false): array {
    if (!$forza && !versione_da_controllare() && ($c = versione_cache())) return $c;
    try { $c = ['quando' => time(), 'sito' => versione_sito(), 'dati' => versione_chiedi(), 'errore' => '']; }
    catch (Throwable $e) { $c = ['quando' => time(), 'sito' => versione_sito(), 'dati' => null, 'errore' => $e->getMessage()]; }
    save_setting('versione_controllo_cache', json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $c;
}

/** La versione nuova, se quella che si sa e piu nuova di questa: i dati, altrimenti null. */
function versione_nuova(): ?array {
    if (!versione_attivo()) return null;
    $c = versione_cache();
    $d = $c['dati'] ?? null;
    return is_array($d) && version_compare((string)$d['versione'], cms_versione(), '>') ? $d : null;
}

/** Il pacchetto di questa versione e gia nella coda? (cosi "Scarica e prepara" non lo mette due volte) */
function versione_in_coda(array $d): ?array {
    foreach (agg_lista_pacchetti() as $p) if (($p['file_zip'] ?? '') === $d['nome'] && ($p['stato'] ?? '') !== 'applicato') return $p;
    return null;
}

/**
 * "Scarica e prepara": scarica lo zip della versione nuova, controlla dimensione e sha256, lo mette nella coda degli
 * aggiornamenti come uno zip caricato a mano. Ritorna l'id del pacchetto. Lo applica l'amministratore con "Applica ora".
 */
function versione_scarica_e_prepara(): string {
    $c = versione_controlla(true); // si riparte sempre dai dati di adesso: il file potrebbe essere cambiato
    $d = $c['dati'] ?? null;
    if (!$d) throw new RuntimeException('Non riesco a sapere qual e l\'ultima versione: ' . ($c['errore'] ?: 'nessuna risposta') . '.');
    if (!version_compare($d['versione'], cms_versione(), '>')) throw new RuntimeException('Hai gia l\'ultima versione (' . cms_versione() . ').');
    if ($p = versione_in_coda($d)) return $p['id'];
    @set_time_limit(VERSIONE_TIMEOUT_ZIP + 60);
    $tmp = agg_dir('pacchetti') . '/.scarico-' . bin2hex(random_bytes(6)) . '.zip';
    try {
        versione_http($d['url'], $d['dimensione'], VERSIONE_TIMEOUT_ZIP, $tmp);
        if ((int)filesize($tmp) !== $d['dimensione']) throw new RuntimeException('Lo zip scaricato non e intero (' . agg_fmt_bytes((int)filesize($tmp)) . ' invece di ' . agg_fmt_bytes($d['dimensione']) . ').');
        if (!hash_equals($d['sha256'], (string)hash_file('sha256', $tmp))) throw new RuntimeException('Lo zip scaricato non corrisponde all\'impronta sha256 del sito ufficiale: non lo uso.');
        $id = agg_registra_pacchetto($tmp, $d['nome']); // sposta $tmp nella coda, dopo aver letto il manifest
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
    if (function_exists('sicurezza_log')) sicurezza_log('pacchetto', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'scaricato dal sito ufficiale: ' . $d['nome'] . ' (' . $d['versione'] . ')');
    return $id;
}
