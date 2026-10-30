<?php
/**
 * SEO e AEO: il motore dietro la pagina "SEO e AEO" del pannello (admin/seo.php).
 *
 *  - impostazioni: una sola voce JSON (seo_config) con i valori di partenza uguali a come il sito esce oggi;
 *    con i predefiniti il contratto dei 1.005 indirizzi resta a zero differenze
 *  - verifica della proprieta: meta tag o file in radice per Google, Bing, Yandex, Pinterest
 *  - IndexNow: chiave, file /<chiave>.txt, coda (<prefisso>seo_coda) e log (<prefisso>seo_log)
 *  - Google: service account, token con JWT firmato da openssl, Search Console (ispezione, prestazioni,
 *    sitemap) e Indexing API (spenta per scelta)
 *  - copertura: verdetti di Search Console per indirizzo (<prefisso>seo_url)
 *  - analisi e tag: GA4, Tag Manager, Meta Pixel, Clarity, codice libero, con consenso
 *  - robots.txt, llms.txt, llms-full.txt, sitemap, 404 (<prefisso>seo_404)
 *  - seo_analisi(): il punteggio di un contenuto, come Rank Math
 *
 * Credenziali e chiavi stanno solo nel database. Niente in questo file.
 * Ogni chiamata esterna ha un timeout e torna con un errore leggibile, mai con un fatal.
 */

require_once __DIR__ . '/sito-dati.php';
require_once __DIR__ . '/contenuti.php';

const SEO_INDEXNOW_API = 'https://api.indexnow.org/indexnow';
const SEO_GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';
const SEO_GSC_API = 'https://www.googleapis.com/webmasters/v3/sites/';
const SEO_GSC_ISPEZIONE = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
const SEO_GOOGLE_INDEXING = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
const SEO_QUOTA_ISPEZIONI = 2000;
// Il nome del sito nei titoli di oggi (lo stesso di SITO_NOME in sito-seo.php, che però non e sempre caricato nel pannello).
define('SEO_NOME_SITO', (string)cms_impostazione('sito_titolo', cms_impostazione('sito_nome', CMS_NOME)));

/** Gli @id dei nodi fissi del JSON-LD (persona, servizio/organizzazione, sito): il modulo del sito può darne di suoi. */
function seo_jsonld_id(string $quale): string {
    return SITO_URL . ['persona' => (string)cms_impostazione('jsonld_id_persona', '/#persona'), 'servizio' => (string)cms_impostazione('jsonld_id_organizzazione', '/#organizzazione'), 'sito' => '/#sito'][$quale];
}

/** I crawler delle intelligenze artificiali che si possono fermare da robots.txt, con la spiegazione per il pannello. */
const SEO_CRAWLER_AI = [
    'GPTBot' => ['OpenAI (ChatGPT)', 'Raccoglie pagine per addestrare i modelli di OpenAI. Bloccarlo non toglie il sito dalle risposte di ChatGPT con ricerca, che usa OAI-SearchBot.'],
    'OAI-SearchBot' => ['OpenAI, ricerca', 'Serve a ChatGPT per cercare e citare pagine in tempo reale. Lasciarlo passare aiuta a essere citati.'],
    'ChatGPT-User' => ['OpenAI, su richiesta', 'Apre una pagina quando un utente di ChatGPT la chiede. Senza, ChatGPT non può leggere il sito su richiesta.'],
    'ClaudeBot' => ['Anthropic (Claude)', 'Raccoglie pagine per addestrare Claude. Claude-User e Claude-SearchBot restano separati.'],
    'Google-Extended' => ['Google, Gemini', 'Non e un crawler: e un segnale che dice a Google di non usare le pagine per addestrare Gemini. Googlebot e la ricerca non cambiano.'],
    'PerplexityBot' => ['Perplexity', 'Indicizza per il motore di risposta Perplexity. Lasciarlo passare aiuta a comparire fra le fonti citate.'],
    'CCBot' => ['Common Crawl', 'Archivio pubblico del web, usato da molti per addestrare modelli. Bloccarlo toglie il sito da molti insiemi di addestramento.'],
    'Bytespider' => ['ByteDance (TikTok)', 'Crawler aggressivo di ByteDance. Nessun beneficio di visibilita: molti lo bloccano.'],
    'Applebot-Extended' => ['Apple Intelligence', 'Come Google-Extended: dice ad Apple di non usare le pagine per addestrare i suoi modelli. Applebot e Siri non cambiano.'],
    'meta-externalagent' => ['Meta (Llama)', 'Raccoglie pagine per addestrare i modelli di Meta.'],
];

// ------------------------------------------------------------
// Schema
// ------------------------------------------------------------

function seo_schema_tabelle(): array {
    return [
        'seo_coda' => "CREATE TABLE IF NOT EXISTS " . tbl('seo_coda') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `url` VARCHAR(500) NOT NULL,
            `motivo` VARCHAR(60) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `tentativi` SMALLINT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), UNIQUE KEY `url` (`url`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'seo_log' => "CREATE TABLE IF NOT EXISTS " . tbl('seo_log') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `quando` DATETIME NOT NULL,
            `servizio` VARCHAR(30) NOT NULL,
            `azione` VARCHAR(60) NOT NULL DEFAULT '',
            `esito` VARCHAR(10) NOT NULL DEFAULT '',
            `codice` SMALLINT NOT NULL DEFAULT 0,
            `dettaglio` TEXT,
            `quanti` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), KEY `quando` (`quando`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'seo_url' => "CREATE TABLE IF NOT EXISTS " . tbl('seo_url') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `url` VARCHAR(500) NOT NULL,
            `verdetto` VARCHAR(30) NOT NULL DEFAULT '',
            `copertura` VARCHAR(120) NOT NULL DEFAULT '',
            `stato_indice` VARCHAR(40) NOT NULL DEFAULT '',
            `ultima_scansione` DATETIME DEFAULT NULL,
            `canonical_google` VARCHAR(500) NOT NULL DEFAULT '',
            `canonical_utente` VARCHAR(500) NOT NULL DEFAULT '',
            `robots_txt` VARCHAR(30) NOT NULL DEFAULT '',
            `pagina_recuperata` VARCHAR(40) NOT NULL DEFAULT '',
            `controllato` DATETIME DEFAULT NULL,
            `grezzo` MEDIUMTEXT,
            PRIMARY KEY (`id`), UNIQUE KEY `url` (`url`(191)), KEY `verdetto` (`verdetto`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'seo_404' => "CREATE TABLE IF NOT EXISTS " . tbl('seo_404') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `percorso` VARCHAR(500) NOT NULL,
            `conteggio` INT NOT NULL DEFAULT 1,
            `referer` VARCHAR(500) NOT NULL DEFAULT '',
            `primo` DATETIME NOT NULL,
            `ultimo` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `percorso` (`percorso`(191)), KEY `ultimo` (`ultimo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
}

/** Crea le tabelle che mancano. Una query di controllo per volta, poi niente (impronta in <prefisso>settings). */
function seo_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $tabelle = seo_schema_tabelle();
    $impronta = md5(serialize([$tabelle, DB_NAME]));
    if (get_setting('seo_schema_impronta', '') === $impronta) return;
    foreach ($tabelle as $nome => $sql) {
        if (!db()->query('SHOW TABLES LIKE ' . db()->quote(DB_PREFIX . $nome))->fetchColumn()) db()->exec($sql);
    }
    save_setting('seo_schema_impronta', $impronta);
}

// ------------------------------------------------------------
// Impostazioni
// ------------------------------------------------------------

/** I valori di partenza: identici a come il sito esce oggi. */
function seo_predefiniti(): array {
    $p = [
        // Verifica della proprieta: valore del meta tag, oppure nome del file HTML (google...html) che il sito serve in radice.
        'verifica' => ['google' => '', 'bing' => '', 'bing_file' => '', 'yandex' => '', 'pinterest' => ''],
        // IndexNow: attivo appena c'è una chiave. Invio immediato al salvataggio, il resto dal cron.
        'indexnow' => ['attivo' => 1, 'subito' => 1],
        // Google: proprieta di Search Console e Indexing API (spenta: Google la ammette solo per JobPosting e BroadcastEvent).
        'google' => ['proprieta' => SITO_URL . '/', 'indexing_attivo' => 0],
        // Analisi e tag. consenso: 'banner' carica i tag solo dopo il consenso; 'subito' li carica sempre.
        'analisi' => ['ga4' => '', 'gtm' => '', 'pixel' => '', 'clarity' => '', 'consenso' => 'banner',
            'banner_testo' => 'Questo sito usa strumenti di statistica solo se li accetti. Nessun dato prima del consenso.',
            'codice_head' => '', 'codice_body_inizio' => '', 'codice_body_fine' => ''],
        // Titoli: %titolo% %sep% %sito%. Il nome del sito e quello dei <title> di oggi.
        'titolo' => ['sep' => '-', 'sito' => SEO_NOME_SITO, 'home' => '%titolo% %sep% %sito%', 'articolo' => '%titolo% %sep% %sito%',
            'pagina' => '%titolo% %sep% %sito%', 'archivio' => '%titolo% %sep% %sito%', 'altro' => '%titolo% %sep% %sito%'],
        'meta' => ['descrizione' => '', 'og_immagine' => '', 'twitter_account' => '', 'robots_avanzati' => 1],
        // Robots per tipo: 'auto' = la regola di oggi (quella del contratto). Si cambia solo con cognizione di causa.
        'robots' => ['articoli' => 'auto', 'pagine' => 'auto', 'categorie' => 'auto', 'tag' => 'auto', 'autore' => 'auto', 'cerca' => 'auto', 'paginazione' => 'auto', 'data' => 'auto'],
        'robots_txt' => ['personale' => '', 'ai' => []],
        'llms' => ['personale' => '', 'full' => 1],
        'sitemap' => ['pagine' => 1, 'categorie' => 1, 'immagini' => 0, 'divisa' => 0],
        'schema' => ['article' => 1, 'faq' => 1, 'breadcrumb' => 1, 'website' => 1, 'searchaction' => 0, 'speakable' => 0, 'organization' => 1, 'person' => 1],
        'aeo' => ['parole_primo_paragrafo' => 60, 'tldr_min' => 40, 'tldr_max' => 60],
        // Organizzazione e persona: valori generici dal nome del sito; il modulo del sito li completa (filtro seo_predefiniti).
        'org' => [
            'tipo' => 'Organization', 'nome' => (string)cms_impostazione('sito_nome', CMS_NOME), 'nome_sito' => (string)cms_impostazione('sito_titolo', cms_impostazione('sito_nome', CMS_NOME)),
            'logo' => '', 'immagine' => '',
            'descrizione' => '', 'lingua' => (string)cms_impostazione('sito_lingua', 'it'), 'area' => 'Italia', 'servizio' => '',
            'telefono' => '', 'email' => '', 'indirizzo' => '', 'sameas' => '',
        ],
        'persona' => [
            'nome' => (string)cms_impostazione('autore_predefinito', ''), 'ruolo' => (string)cms_impostazione('sito_sottotitolo', ''),
            'immagine' => '', 'descrizione' => '', 'conosce' => '', 'credenziale' => '', 'credenziale_ente' => '', 'sameas' => '',
        ],
    ];
    return cms_filtro('seo_predefiniti', $p);
}

/** La configurazione completa: quello che è salvato sopra i predefiniti. Una query per richiesta; $azzera rilegge dopo un salvataggio. */
function seo_cfg(bool $azzera = false): array {
    static $c = null;
    if ($c === null || $azzera) {
        $salvata = json_decode((string)get_setting('seo_config', ''), true);
        $c = seo_predefiniti();
        if (is_array($salvata)) {
            foreach ($salvata as $gruppo => $valori) {
                if (is_array($valori) && isset($c[$gruppo]) && is_array($c[$gruppo])) $c[$gruppo] = array_replace($c[$gruppo], $valori);
            }
        }
    }
    return $c;
}

/** Salva un gruppo di impostazioni (solo le chiavi conosciute). */
function seo_salva(string $gruppo, array $valori): void {
    $pred = seo_predefiniti();
    if (!isset($pred[$gruppo])) throw new InvalidArgumentException("Gruppo sconosciuto: $gruppo");
    $tutto = json_decode((string)get_setting('seo_config', ''), true) ?: [];
    $puliti = [];
    foreach ($pred[$gruppo] as $k => $p) {
        if (!array_key_exists($k, $valori)) continue;
        $v = $valori[$k];
        $puliti[$k] = is_array($p) ? (is_array($v) ? $v : []) : (is_int($p) ? (int)$v : sito_senza_4byte(trim(str_replace("\r\n", "\n", (string)$v))));
    }
    $tutto[$gruppo] = $puliti + ($tutto[$gruppo] ?? []);
    save_setting('seo_config', json_encode($tutto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    seo_cfg(true);
}

/** Le righe di un campo "una per riga" come elenco pulito. */
function seo_righe(string $s): array {
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $s)), fn($x) => $x !== ''));
}

// ------------------------------------------------------------
// Log
// ------------------------------------------------------------

function seo_log(string $servizio, string $azione, string $esito, int $codice = 0, string $dettaglio = '', int $quanti = 0): void {
    try {
        seo_installa();
        db()->prepare('INSERT INTO ' . tbl('seo_log') . ' (quando, servizio, azione, esito, codice, dettaglio, quanti) VALUES (NOW(), ?, ?, ?, ?, ?, ?)')
            ->execute([$servizio, mb_substr($azione, 0, 60), $esito, $codice, mb_substr(sito_senza_4byte($dettaglio), 0, 4000), $quanti]);
        // Il log non cresce all'infinito.
        if (random_int(1, 50) === 1) db()->exec('DELETE FROM ' . tbl('seo_log') . ' WHERE quando < DATE_SUB(NOW(), INTERVAL 90 DAY)');
    } catch (Throwable $e) {
        error_log('seo_log: ' . $e->getMessage());
    }
}

function seo_log_elenco(int $n = 100, string $servizio = ''): array {
    seo_installa();
    $sql = 'SELECT * FROM ' . tbl('seo_log') . ($servizio !== '' ? ' WHERE servizio = ' . db()->quote($servizio) : '') . ' ORDER BY id DESC LIMIT ' . (int)$n;
    return db()->query($sql)->fetchAll();
}

// ------------------------------------------------------------
// HTTP: una sola porta verso l'esterno, con timeout e senza eccezioni
// ------------------------------------------------------------

/**
 * Chiamata HTTP. Ritorna ['codice' => int, 'corpo' => string, 'errore' => string].
 * codice 0 = la rete non ha risposto (DNS, timeout, rete chiusa): l'errore lo dice in italiano.
 */
function seo_http(string $url, string $metodo = 'GET', ?string $corpo = null, array $intestazioni = [], int $timeout = 12): array {
    $out = ['codice' => 0, 'corpo' => '', 'errore' => ''];
    // sicurezza: solo host consentiti, mai verso indirizzi privati, nessun redirect seguito, solo http e https.
    require_once __DIR__ . '/sicurezza-html.php';
    $ctrl = sicurezza_uscita_controlla($url);
    if (!$ctrl['ok']) { $out['errore'] = $ctrl['errore']; return $out; }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $intestazioni,
            CURLOPT_USERAGENT => CMS_NOME . ' (SEO)', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_MAXFILESIZE => 5000000,
            // L'IP appena controllato e quello che si usa davvero (niente cambio di DNS fra il controllo e la chiamata).
            CURLOPT_RESOLVE => [$ctrl['host'] . ':' . $ctrl['porta'] . ':' . $ctrl['ip']],
        ]);
        if ($corpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
        $r = curl_exec($ch);
        if ($r === false) {
            $out['errore'] = seo_errore_rete(curl_error($ch));
        } else {
            $out['codice'] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $out['corpo'] = (string)$r;
        }
        curl_close($ch);
        return $out;
    }
    // sicurezza (attacco 6): senza curl la chiamata non può fissare l'IP appena controllato (il DNS si risolverebbe di nuovo,
    // finestra di DNS rebinding). Meglio rifiutare con un messaggio chiaro: curl c'è su ogni hosting cPanel.
    $out['errore'] = "Manca l'estensione PHP curl: senza, le chiamate esterne (IndexNow, Search Console) restano spente. Si accende da cPanel, Select PHP Version.";
    return $out;
}

function seo_errore_rete(string $grezzo): string {
    $g = strtolower($grezzo);
    if (str_contains($g, 'resolve') || str_contains($g, 'getaddrinfo') || str_contains($g, 'name or service')) return 'Il server non raggiunge Internet (il nome del servizio non si risolve). Riprova più tardi o controlla la rete dell\'hosting.';
    if (str_contains($g, 'timed out') || str_contains($g, 'timeout')) return 'Il servizio non ha risposto in tempo.';
    if (str_contains($g, 'ssl') || str_contains($g, 'certificate')) return 'Problema di certificato nel collegamento sicuro: ' . $grezzo;
    return 'Collegamento non riuscito: ' . ($grezzo !== '' ? $grezzo : 'nessuna risposta');
}

// ------------------------------------------------------------
// Verifica della proprieta e file speciali in radice
// ------------------------------------------------------------

/** I meta tag di verifica per il <head>. Vuoto se non c'è niente da verificare. */
function seo_meta_verifica(): string {
    $v = seo_cfg()['verifica'];
    $righe = [];
    if ($v['google'] !== '' && !str_ends_with($v['google'], '.html')) $righe[] = '<meta name="google-site-verification" content="' . h($v['google']) . '">';
    if ($v['bing'] !== '') $righe[] = '<meta name="msvalidate.01" content="' . h($v['bing']) . '">';
    if ($v['yandex'] !== '') $righe[] = '<meta name="yandex-verification" content="' . h($v['yandex']) . '">';
    if ($v['pinterest'] !== '') $righe[] = '<meta name="p:domain_verify" content="' . h($v['pinterest']) . '">';
    return implode("\n", $righe);
}

/**
 * Un file che il sito serve in radice per i motori: la verifica di Google (google....html), BingSiteAuth.xml,
 * la chiave di IndexNow (/<chiave>.txt), llms-full.txt, le sitemap divise. Null se il percorso non e uno di questi.
 */
function seo_file_speciale(string $percorso): ?array {
    $v = seo_cfg()['verifica'];
    if ($v['google'] !== '' && str_ends_with($v['google'], '.html') && $percorso === '/' . $v['google']) {
        return ['text/html', 'google-site-verification: ' . $v['google']];
    }
    if ($percorso === '/BingSiteAuth.xml' && $v['bing_file'] !== '') return ['application/xml', $v['bing_file']];
    $chiave = seo_indexnow_chiave();
    if ($chiave !== '' && $percorso === "/$chiave.txt") return ['text/plain', $chiave];
    if ($percorso === '/llms-full.txt' && !empty(seo_cfg()['llms']['full'])) return ['text/plain', seo_llms_full()];
    if (!empty(seo_cfg()['sitemap']['divisa']) && preg_match('~^/sitemap-(articoli|pagine|categorie)\.xml$~', $percorso, $m)) {
        return ['application/xml', seo_sitemap_tipo($m[1])];
    }
    return null;
}

// ------------------------------------------------------------
// IndexNow
// ------------------------------------------------------------

function seo_indexnow_chiave(): string {
    static $k = null;
    if ($k === null) $k = preg_match('/^[a-f0-9]{32}$/', (string)get_setting('seo_indexnow_chiave', '')) ? (string)get_setting('seo_indexnow_chiave', '') : '';
    return $k;
}

/** Genera (o rigenera) la chiave. La chiave e pubblica per natura: sta nel file /<chiave>.txt. */
function seo_indexnow_genera(): string {
    $k = bin2hex(random_bytes(16));
    save_setting('seo_indexnow_chiave', $k);
    seo_log('indexnow', 'chiave generata', 'ok');
    return $k;
}

function seo_indexnow_attivo(): bool {
    return seo_indexnow_chiave() !== '' && !empty(seo_cfg()['indexnow']['attivo']);
}

/** Mette un indirizzo in coda. Un indirizzo già in coda si aggiorna (tentativi azzerati). */
function seo_coda_aggiungi(string $url, string $motivo = ''): void {
    seo_installa();
    $url = sito_assoluto_seo($url);
    db()->prepare('INSERT INTO ' . tbl('seo_coda') . ' (url, motivo, creato, tentativi) VALUES (?, ?, NOW(), 0) ON DUPLICATE KEY UPDATE motivo = VALUES(motivo), creato = NOW(), tentativi = 0')
        ->execute([mb_substr($url, 0, 500), mb_substr($motivo, 0, 60)]);
}

function sito_assoluto_seo(string $p): string {
    return preg_match('~^https?://~', $p) ? $p : SITO_URL . (str_starts_with($p, '/') ? '' : '/') . $p;
}

function seo_coda_conta(): int {
    seo_installa();
    return (int)db()->query('SELECT COUNT(*) FROM ' . tbl('seo_coda'))->fetchColumn();
}

function seo_coda_elenco(int $n = 50): array {
    seo_installa();
    return db()->query('SELECT * FROM ' . tbl('seo_coda') . ' ORDER BY id DESC LIMIT ' . (int)$n)->fetchAll();
}

/**
 * Manda a IndexNow gli indirizzi in coda (fino a 10.000 per chiamata, il massimo del protocollo).
 * Ritorna una riga di esito leggibile. Bing, Yandex, Seznam, Naver e Yep condividono la notifica.
 */
function seo_coda_invia(int $limite = 1000, int $timeout = 10): string {
    seo_installa();
    if (!seo_indexnow_attivo()) return 'IndexNow non attivo: niente inviato.';
    $righe = db()->query('SELECT id, url FROM ' . tbl('seo_coda') . ' WHERE tentativi < 5 ORDER BY id LIMIT ' . (int)min(10000, max(1, $limite)))->fetchAll();
    if (!$righe) return 'Coda vuota.';
    $urls = array_values(array_unique(array_column($righe, 'url')));
    $r = seo_indexnow_invia($urls, $timeout);
    $ids = implode(',', array_map('intval', array_column($righe, 'id')));
    if ($r['ok']) {
        db()->exec('DELETE FROM ' . tbl('seo_coda') . " WHERE id IN ($ids)");
        return count($urls) . ' indirizzi inviati a IndexNow (codice ' . $r['codice'] . ').';
    }
    db()->exec('UPDATE ' . tbl('seo_coda') . " SET tentativi = tentativi + 1 WHERE id IN ($ids)");
    return 'IndexNow non ha accettato l\'invio: ' . $r['messaggio'];
}

/** L'invio vero. Ritorna ['ok' => bool, 'codice' => int, 'messaggio' => string]. */
function seo_indexnow_invia(array $urls, int $timeout = 10): array {
    $chiave = seo_indexnow_chiave();
    if ($chiave === '') return ['ok' => false, 'codice' => 0, 'messaggio' => 'Manca la chiave IndexNow.'];
    $host = (string)parse_url(SITO_URL, PHP_URL_HOST);
    $corpo = json_encode(['host' => $host, 'key' => $chiave, 'keyLocation' => SITO_URL . "/$chiave.txt", 'urlList' => array_values($urls)], JSON_UNESCAPED_SLASHES);
    $r = seo_http(SEO_INDEXNOW_API, 'POST', $corpo, ['Content-Type: application/json; charset=utf-8'], $timeout);
    $spiega = [200 => 'ricevuto', 202 => 'ricevuto, chiave da verificare', 400 => 'richiesta non valida', 403 => 'chiave non valida o file della chiave non raggiungibile',
        422 => 'indirizzi non del dominio o chiave non corrispondente', 429 => 'troppe richieste: riprova più tardi'];
    $ok = in_array($r['codice'], [200, 202], true);
    $msg = $r['errore'] !== '' ? $r['errore'] : ('codice ' . $r['codice'] . ($spiega[$r['codice']] ?? false ? ', ' . $spiega[$r['codice']] : '') . ($r['corpo'] !== '' && !$ok ? ': ' . mb_substr(strip_tags($r['corpo']), 0, 200) : ''));
    seo_log('indexnow', 'invio', $ok ? 'ok' : 'errore', $r['codice'], $msg . "\n" . implode("\n", array_slice($urls, 0, 50)), count($urls));
    return ['ok' => $ok, 'codice' => $r['codice'], 'messaggio' => $msg];
}

/** Tutti gli indirizzi della sitemap in coda, poi l'invio. */
function seo_indexnow_tutta_sitemap(): string {
    foreach (seo_sitemap_voci() as $v) seo_coda_aggiungi($v['loc'], 'sitemap');
    return seo_coda_invia(10000, 20);
}

/**
 * Aggancio per il salvataggio di un contenuto (includes/contenuti.php): se e pubblicato va in coda,
 * e se l'invio immediato e acceso si prova subito, con un timeout corto e senza mai bloccare il salvataggio.
 */
function seo_notifica_contenuto(string $tipo, int $id): void {
    try {
        if (!seo_indexnow_attivo() && empty(seo_cfg()['google']['indexing_attivo'])) return;
        $r = contenuti_leggi($tipo, $id);
        if (!$r || contenuti_stato($tipo, $r) !== 'pubblicato') return;
        if (sito_robots_articolo($r) !== 'index' && $tipo !== 'pagine') return;
        if ($tipo === 'pagine' && ($r['robots'] ?? '') === 'noindex') return;
        $url = SITO_URL . contenuti_percorso($tipo, $r);
        if (seo_indexnow_attivo()) {
            seo_coda_aggiungi($url, 'salvataggio');
            if (!empty(seo_cfg()['indexnow']['subito'])) seo_coda_invia(100, 4);
        }
        if (!empty(seo_cfg()['google']['indexing_attivo'])) seo_google_indexing($url);
    } catch (Throwable $e) {
        error_log('seo_notifica_contenuto: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Google: service account, token, Search Console, Indexing API
// ------------------------------------------------------------

/** Il service account salvato (array) oppure null. La chiave privata non esce mai da qui verso il pannello. */
function seo_google_sa(): ?array {
    $j = json_decode((string)get_setting('seo_google_sa', ''), true);
    return is_array($j) && !empty($j['client_email']) && !empty($j['private_key']) ? $j : null;
}

/** Salva il JSON del service account dopo un controllo di forma. Ritorna l'email del service account. */
function seo_google_sa_salva(string $json): string {
    $j = json_decode(trim($json), true);
    if (!is_array($j) || ($j['type'] ?? '') !== 'service_account' || empty($j['client_email']) || empty($j['private_key'])) {
        throw new RuntimeException('Il file non sembra la chiave JSON di un service account Google (servono type = service_account, client_email e private_key).');
    }
    if (!function_exists('openssl_sign')) throw new RuntimeException('Su questo server PHP non ha OpenSSL: la firma del token non è possibile.');
    $k = openssl_pkey_get_private($j['private_key']);
    if ($k === false) throw new RuntimeException('La chiave privata nel JSON non si legge.');
    save_setting('seo_google_sa', json_encode($j, JSON_UNESCAPED_SLASHES));
    save_setting('seo_google_token', '');
    seo_log('google', 'service account salvato', 'ok', 0, $j['client_email']);
    return (string)$j['client_email'];
}

function seo_google_sa_togli(): void {
    save_setting('seo_google_sa', '');
    save_setting('seo_google_token', '');
}

function seo_b64url(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/**
 * Un token di accesso per gli ambiti richiesti: JWT RS256 firmato con openssl, scambiato a oauth2.googleapis.com.
 * In cache nelle impostazioni per 50 minuti. Ritorna ['token' => string] oppure ['errore' => string].
 */
function seo_google_token(array $ambiti): array {
    $sa = seo_google_sa();
    if (!$sa) return ['errore' => 'Nessun service account Google salvato: scheda Motori di ricerca, riquadro Google.'];
    sort($ambiti);
    $chiaveCache = md5(implode(' ', $ambiti) . $sa['client_email']);
    $cache = json_decode((string)get_setting('seo_google_token', ''), true);
    if (is_array($cache) && ($cache['chiave'] ?? '') === $chiaveCache && ($cache['scade'] ?? 0) > time() + 60) return ['token' => $cache['token']];
    if (!function_exists('openssl_sign')) return ['errore' => 'PHP senza OpenSSL: impossibile firmare il token.'];
    $ora = time();
    $testata = seo_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $corpo = seo_b64url(json_encode(['iss' => $sa['client_email'], 'scope' => implode(' ', $ambiti), 'aud' => SEO_GOOGLE_TOKEN, 'exp' => $ora + 3600, 'iat' => $ora]));
    $firma = '';
    $pk = openssl_pkey_get_private($sa['private_key']);
    if ($pk === false || !openssl_sign("$testata.$corpo", $firma, $pk, OPENSSL_ALGO_SHA256)) return ['errore' => 'Firma del token non riuscita: la chiave privata del service account non si legge.'];
    $jwt = "$testata.$corpo." . seo_b64url($firma);
    $r = seo_http(SEO_GOOGLE_TOKEN, 'POST', http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]), ['Content-Type: application/x-www-form-urlencoded'], 12);
    if ($r['errore'] !== '') { seo_log('google', 'token', 'errore', 0, $r['errore']); return ['errore' => $r['errore']]; }
    $j = json_decode($r['corpo'], true);
    if ($r['codice'] !== 200 || empty($j['access_token'])) {
        $msg = 'Google non ha dato il token (codice ' . $r['codice'] . '): ' . (string)($j['error_description'] ?? $j['error'] ?? mb_substr($r['corpo'], 0, 200));
        seo_log('google', 'token', 'errore', $r['codice'], $msg);
        return ['errore' => $msg];
    }
    save_setting('seo_google_token', json_encode(['chiave' => $chiaveCache, 'token' => $j['access_token'], 'scade' => $ora + (int)($j['expires_in'] ?? 3600) - 300]));
    return ['token' => $j['access_token']];
}

/** Chiamata autenticata alle API di Google. Ritorna ['dati' => array] oppure ['errore' => string, 'codice' => int]. */
function seo_google_chiama(string $url, array $ambiti, string $metodo = 'GET', ?array $corpo = null, int $timeout = 20): array {
    $t = seo_google_token($ambiti);
    if (isset($t['errore'])) return ['errore' => $t['errore'], 'codice' => 0];
    $r = seo_http($url, $metodo, $corpo !== null ? json_encode($corpo, JSON_UNESCAPED_SLASHES) : null,
        ['Authorization: Bearer ' . $t['token'], 'Content-Type: application/json', 'Accept: application/json'], $timeout);
    if ($r['errore'] !== '') return ['errore' => $r['errore'], 'codice' => 0];
    $j = json_decode($r['corpo'], true);
    if ($r['codice'] >= 400 || !is_array($j)) {
        $m = is_array($j) ? (string)($j['error']['message'] ?? $j['error'] ?? '') : '';
        $spiega = match ($r['codice']) {
            403 => ' Di solito: il service account non è stato aggiunto come utente della proprieta in Search Console, oppure l\'API non e abilitata nel progetto Google Cloud.',
            404 => ' La proprieta indicata non esiste con questo nome esatto: controlla "Proprieta di Search Console" (con https:// e la barra finale, oppure sc-domain:dominio).',
            429 => ' Quota giornaliera esaurita: si riprende domani.',
            default => '',
        };
        return ['errore' => 'Google ha risposto ' . $r['codice'] . ($m !== '' ? ': ' . $m : '') . $spiega, 'codice' => $r['codice']];
    }
    return ['dati' => $j, 'codice' => $r['codice']];
}

const SEO_AMBITO_GSC = 'https://www.googleapis.com/auth/webmasters.readonly';
const SEO_AMBITO_INDEXING = 'https://www.googleapis.com/auth/indexing';

function seo_gsc_proprieta(): string {
    return trim((string)seo_cfg()['google']['proprieta']) ?: SITO_URL . '/';
}

/** Le sitemap note a Search Console per la proprieta. */
function seo_gsc_sitemaps(): array {
    return seo_google_chiama(SEO_GSC_API . rawurlencode(seo_gsc_proprieta()) . '/sitemaps', [SEO_AMBITO_GSC]);
}

/** Prova di collegamento: elenco delle proprieta a cui il service account ha accesso. */
function seo_gsc_prova(): array {
    return seo_google_chiama(SEO_GSC_API, [SEO_AMBITO_GSC]);
}

/** Ispeziona un indirizzo e salva il verdetto in <prefisso>seo_url. Ritorna la riga salvata oppure ['errore' => ...]. */
function seo_gsc_ispeziona(string $url): array {
    seo_installa();
    $url = sito_assoluto_seo($url);
    $r = seo_google_chiama(SEO_GSC_ISPEZIONE, [SEO_AMBITO_GSC], 'POST', ['inspectionUrl' => $url, 'siteUrl' => seo_gsc_proprieta(), 'languageCode' => 'it'], 30);
    if (isset($r['errore'])) { seo_log('google', 'ispezione', 'errore', $r['codice'], $url . ' ' . $r['errore']); return $r; }
    $i = $r['dati']['inspectionResult']['indexStatusResult'] ?? [];
    $riga = [
        'url' => $url,
        'verdetto' => (string)($i['verdict'] ?? ''),
        'copertura' => mb_substr((string)($i['coverageState'] ?? ''), 0, 120),
        'stato_indice' => (string)($i['indexingState'] ?? ''),
        'ultima_scansione' => !empty($i['lastCrawlTime']) ? date('Y-m-d H:i:s', strtotime($i['lastCrawlTime'])) : null,
        'canonical_google' => mb_substr((string)($i['googleCanonical'] ?? ''), 0, 500),
        'canonical_utente' => mb_substr((string)($i['userCanonical'] ?? ''), 0, 500),
        'robots_txt' => (string)($i['robotsTxtState'] ?? ''),
        'pagina_recuperata' => (string)($i['pageFetchState'] ?? ''),
        'grezzo' => json_encode($r['dati'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
    db()->prepare('INSERT INTO ' . tbl('seo_url') . ' (url, verdetto, copertura, stato_indice, ultima_scansione, canonical_google, canonical_utente, robots_txt, pagina_recuperata, controllato, grezzo)
        VALUES (:url, :verdetto, :copertura, :stato_indice, :ultima_scansione, :canonical_google, :canonical_utente, :robots_txt, :pagina_recuperata, NOW(), :grezzo)
        ON DUPLICATE KEY UPDATE verdetto = VALUES(verdetto), copertura = VALUES(copertura), stato_indice = VALUES(stato_indice), ultima_scansione = VALUES(ultima_scansione),
        canonical_google = VALUES(canonical_google), canonical_utente = VALUES(canonical_utente), robots_txt = VALUES(robots_txt), pagina_recuperata = VALUES(pagina_recuperata), controllato = NOW(), grezzo = VALUES(grezzo)')
        ->execute($riga);
    seo_log('google', 'ispezione', 'ok', 200, $url . ' ' . $riga['copertura']);
    return $riga;
}

/** Quante ispezioni oggi (la quota di Google e 2000 al giorno per proprieta). */
function seo_gsc_ispezioni_oggi(): int {
    seo_installa();
    return (int)db()->query('SELECT COUNT(*) FROM ' . tbl('seo_log') . " WHERE servizio = 'google' AND azione = 'ispezione' AND quando >= CURDATE()")->fetchColumn();
}

/**
 * Ispeziona a lotti: i prossimi N indirizzi della sitemap mai controllati o controllati da più tempo.
 * Ritorna [quanti fatti, errore o ''].
 */
function seo_gsc_ispeziona_lotto(int $n = 20): array {
    seo_installa();
    $n = max(1, min(100, $n));
    $resto = SEO_QUOTA_ISPEZIONI - seo_gsc_ispezioni_oggi();
    if ($resto <= 0) return [0, 'Quota giornaliera di 2000 ispezioni esaurita: si riprende domani.'];
    $n = min($n, $resto);
    $controllati = db()->query('SELECT url, controllato FROM ' . tbl('seo_url'))->fetchAll(PDO::FETCH_KEY_PAIR);
    $voci = array_map(fn($v) => $v['loc'], seo_sitemap_voci());
    usort($voci, fn($a, $b) => strcmp((string)($controllati[$a] ?? ''), (string)($controllati[$b] ?? '')));
    $fatti = 0;
    foreach (array_slice($voci, 0, $n) as $u) {
        $r = seo_gsc_ispeziona($u);
        if (isset($r['errore'])) return [$fatti, $r['errore']];
        $fatti++;
    }
    return [$fatti, ''];
}

/** La copertura salvata, con un filtro: tutti | indicizzato | non_indicizzato | escluso. */
function seo_copertura(string $filtro = ''): array {
    seo_installa();
    $w = match ($filtro) {
        'indicizzato' => "WHERE verdetto = 'PASS'",
        'non_indicizzato' => "WHERE verdetto <> 'PASS' AND copertura LIKE '%not indexed%'",
        'escluso' => "WHERE verdetto <> 'PASS' AND copertura NOT LIKE '%not indexed%'",
        default => '',
    };
    return db()->query('SELECT * FROM ' . tbl('seo_url') . " $w ORDER BY controllato DESC, id DESC")->fetchAll();
}

function seo_copertura_numeri(): array {
    seo_installa();
    $n = ['tutti' => 0, 'indicizzato' => 0, 'non_indicizzato' => 0, 'escluso' => 0];
    foreach (db()->query('SELECT verdetto, copertura FROM ' . tbl('seo_url'))->fetchAll() as $r) {
        $n['tutti']++;
        if ($r['verdetto'] === 'PASS') $n['indicizzato']++;
        elseif (stripos($r['copertura'], 'not indexed') !== false) $n['non_indicizzato']++;
        else $n['escluso']++;
    }
    return $n;
}

/** Traduzione dei verdetti e degli stati di copertura più comuni. */
function seo_copertura_italiano(string $s): string {
    $t = [
        'PASS' => 'Indicizzato', 'NEUTRAL' => 'Escluso', 'FAIL' => 'Errore', 'VERDICT_UNSPECIFIED' => 'Sconosciuto',
        'Submitted and indexed' => 'Inviato e indicizzato', 'Indexed, not submitted in sitemap' => 'Indicizzato, non in sitemap',
        'Crawled - currently not indexed' => 'Scansionata, al momento non indicizzata', 'Discovered - currently not indexed' => 'Rilevata, al momento non indicizzata',
        'URL is unknown to Google' => 'Indirizzo sconosciuto a Google', 'Page with redirect' => 'Pagina con redirect',
        'Excluded by \'noindex\' tag' => 'Esclusa dal tag noindex', 'Duplicate without user-selected canonical' => 'Duplicato senza canonical scelto',
        'Duplicate, Google chose different canonical than user' => 'Duplicato: Google ha scelto un altro canonical', 'Alternate page with proper canonical tag' => 'Pagina alternativa con canonical corretto',
        'Not found (404)' => 'Non trovata (404)', 'Blocked by robots.txt' => 'Bloccata da robots.txt', 'Soft 404' => 'Soft 404',
    ];
    return $t[$s] ?? $s;
}

/**
 * Prestazioni degli ultimi N giorni per dimensione (date, page, query). I dati arrivano con 2-3 giorni di ritardo.
 * In cache un'ora nelle impostazioni, per non consumare la quota a ogni apertura della scheda.
 */
function seo_gsc_prestazioni(string $dimensione = 'date', int $giorni = 28, int $righe = 50, bool $forza = false): array {
    $dimensione = in_array($dimensione, ['date', 'page', 'query'], true) ? $dimensione : 'date';
    $chiave = "seo_gsc_$dimensione" . '_' . $giorni;
    $cache = json_decode((string)get_setting($chiave, ''), true);
    if (!$forza && is_array($cache) && ($cache['quando'] ?? 0) > time() - 3600) return $cache['dati'] + ['cache' => date('H:i', $cache['quando'])];
    $fine = date('Y-m-d', strtotime('-3 days'));
    $inizio = date('Y-m-d', strtotime("-$giorni days", strtotime($fine)));
    $r = seo_google_chiama(SEO_GSC_API . rawurlencode(seo_gsc_proprieta()) . '/searchAnalytics/query', [SEO_AMBITO_GSC], 'POST',
        ['startDate' => $inizio, 'endDate' => $fine, 'dimensions' => [$dimensione], 'rowLimit' => $righe, 'dataState' => 'all'], 30);
    if (isset($r['errore'])) return $r;
    $out = ['righe' => [], 'inizio' => $inizio, 'fine' => $fine];
    foreach ($r['dati']['rows'] ?? [] as $x) {
        $out['righe'][] = ['chiave' => (string)($x['keys'][0] ?? ''), 'clic' => (int)($x['clicks'] ?? 0), 'impressioni' => (int)($x['impressions'] ?? 0),
            'ctr' => round(((float)($x['ctr'] ?? 0)) * 100, 1), 'posizione' => round((float)($x['position'] ?? 0), 1)];
    }
    if ($dimensione === 'date') usort($out['righe'], fn($a, $b) => strcmp($a['chiave'], $b['chiave']));
    else usort($out['righe'], fn($a, $b) => $b['clic'] <=> $a['clic'] ?: $b['impressioni'] <=> $a['impressioni']);
    save_setting($chiave, json_encode(['quando' => time(), 'dati' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    seo_log('google', "prestazioni $dimensione", 'ok', 200, count($out['righe']) . ' righe');
    return $out;
}

/** Un grafico SVG semplice: clic e impressioni per giorno. Niente librerie. */
function seo_grafico_svg(array $righe): string {
    if (!$righe) return '<p class="hint">Nessun dato nel periodo.</p>';
    $w = 720; $hh = 220; $px = 36; $py = 16;
    $maxC = max(1, max(array_column($righe, 'clic')));
    $maxI = max(1, max(array_column($righe, 'impressioni')));
    $n = count($righe);
    $x = fn(int $i) => $px + ($n > 1 ? ($w - 2 * $px) * $i / ($n - 1) : ($w - 2 * $px) / 2);
    $yC = fn(int $v) => $py + ($hh - 2 * $py) * (1 - $v / $maxC);
    $yI = fn(int $v) => $py + ($hh - 2 * $py) * (1 - $v / $maxI);
    $lc = ''; $li = '';
    foreach ($righe as $i => $r) { $lc .= ($i ? ' L' : 'M') . round($x($i), 1) . ' ' . round($yC($r['clic']), 1); $li .= ($i ? ' L' : 'M') . round($x($i), 1) . ' ' . round($yI($r['impressioni']), 1); }
    $etichette = '';
    foreach ([0, intdiv($n - 1, 2), $n - 1] as $i) {
        if (!isset($righe[$i])) continue;
        $etichette .= '<text x="' . round($x($i), 1) . '" y="' . ($hh - 2) . '" font-size="10" text-anchor="middle" style="fill:var(--text-muted)">' . h(substr($righe[$i]['chiave'], 5)) . '</text>';
    }
    return '<svg viewBox="0 0 ' . $w . ' ' . $hh . '" width="100%" style="max-width:' . $w . 'px;display:block" role="img" aria-label="Clic e impressioni per giorno">'
        . '<line x1="' . $px . '" y1="' . ($hh - $py) . '" x2="' . ($w - $px) . '" y2="' . ($hh - $py) . '" style="stroke:var(--border)"/>'
        . '<path d="' . $li . '" fill="none" style="stroke:var(--sage)" stroke-width="2" opacity=".8"/>'
        . '<path d="' . $lc . '" fill="none" style="stroke:var(--coral)" stroke-width="2.5"/>'
        . '<text x="' . $px . '" y="12" font-size="10" style="fill:var(--accento-testo)">Clic (max ' . $maxC . ')</text>'
        . '<text x="' . ($w - $px) . '" y="12" font-size="10" text-anchor="end" style="fill:var(--successo-testo)">Impressioni (max ' . $maxI . ')</text>'
        . $etichette . '</svg>';
}

/** Google Indexing API: notifica di un indirizzo aggiornato. Google la ammette solo per JobPosting e BroadcastEvent. */
function seo_google_indexing(string $url, string $tipo = 'URL_UPDATED'): array {
    $r = seo_google_chiama(SEO_GOOGLE_INDEXING, [SEO_AMBITO_INDEXING], 'POST', ['url' => sito_assoluto_seo($url), 'type' => $tipo], 15);
    seo_log('google', 'indexing api', isset($r['errore']) ? 'errore' : 'ok', $r['codice'] ?? 0, $url . ' ' . ($r['errore'] ?? $tipo));
    return $r;
}

// ------------------------------------------------------------
// Analisi e tag nel sito
// ------------------------------------------------------------

/** Tutto quello che va nel <head> per statistiche e tag, più i meta di verifica e il codice libero. */
function seo_head_html(): string {
    $a = seo_cfg()['analisi'];
    $parti = [];
    $v = seo_meta_verifica();
    if ($v !== '') $parti[] = $v;
    $tw = seo_meta_twitter();
    if ($tw !== '') $parti[] = $tw;
    $tag = seo_tag_script($a);
    if ($tag !== '') $parti[] = $tag;
    // sicurezza: il codice libero e dell'amministratore (impostazione codice_libero della pagina Sicurezza); gli <script> prendono il nonce della CSP.
    if (seo_codice_libero_attivo() && trim($a['codice_head']) !== '') $parti[] = "<!-- codice personalizzato (pannello SEO) -->\n" . sicurezza_nonce_script($a['codice_head']);
    return implode("\n", $parti);
}

/** Gli script dei tag: caricati subito, oppure solo al consenso (banner fatto in casa, nessun servizio esterno). */
function seo_tag_script(array $a): string {
    $ga4 = preg_match('/^G-[A-Z0-9]{4,}$/', $a['ga4']) ? $a['ga4'] : '';
    $gtm = preg_match('/^GTM-[A-Z0-9]{4,}$/', $a['gtm']) ? $a['gtm'] : '';
    $pixel = preg_match('/^\d{6,20}$/', $a['pixel']) ? $a['pixel'] : '';
    $clarity = preg_match('/^[a-z0-9]{6,20}$/i', $a['clarity']) ? $a['clarity'] : '';
    if ($ga4 === '' && $gtm === '' && $pixel === '' && $clarity === '') return '';
    // Ogni tag e una funzione: si lancia subito oppure quando arriva il consenso.
    $carica = [];
    if ($ga4 !== '') $carica[] = "s('https://www.googletagmanager.com/gtag/js?id=$ga4');window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)};gtag('js',new Date());gtag('config','$ga4',{anonymize_ip:true});";
    if ($gtm !== '') $carica[] = "window.dataLayer=window.dataLayer||[];dataLayer.push({'gtm.start':new Date().getTime(),event:'gtm.js'});s('https://www.googletagmanager.com/gtm.js?id=$gtm');";
    if ($pixel !== '') $carica[] = "!function(f,b,e,v,n,t){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;b.head.appendChild(t)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','$pixel');fbq('track','PageView');";
    if ($clarity !== '') $carica[] = "(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','$clarity');";
    $js = "(function(){function s(u){var e=document.createElement('script');e.async=true;e.src=u;document.head.appendChild(e)}"
        . "function avvia(){if(window.seoTagAvviati)return;window.seoTagAvviati=true;" . implode('', $carica) . "}window.seoTagAvvia=avvia;";
    if (($a['consenso'] ?? 'banner') === 'subito') {
        $js .= 'avvia();';
    } else {
        // Il consenso vive in localStorage: 'si' carica i tag, 'no' li tiene fermi, niente = banner.
        $js .= "var c=null;try{c=localStorage.getItem('seo_consenso')}catch(e){}if(c==='si'){avvia()}else if(c!=='no'){window.seoConsensoChiedi=true}"
            . "window.seoConsenso=function(v){try{localStorage.setItem('seo_consenso',v?'si':'no')}catch(e){}var b=document.getElementById('seo-consenso');if(b)b.remove();if(v)avvia()};";
    }
    $js .= '})();';
    return '<script id="seo-tag"' . sicurezza_nonce_attr() . '>' . $js . '</script>';
}

/** Il banner del consenso (se serve) e il codice libero dopo <body>. */
function seo_body_inizio_html(): string {
    $a = seo_cfg()['analisi'];
    $out = [];
    if (seo_codice_libero_attivo() && trim($a['codice_body_inizio']) !== '') $out[] = sicurezza_nonce_script($a['codice_body_inizio']);
    if (($a['consenso'] ?? 'banner') === 'banner' && seo_tag_script($a) !== '') {
        // sicurezza: il banner si costruisce con il DOM e i pulsanti si agganciano con addEventListener (niente onclick in linea: la CSP a nonce li vieta).
        $out[] = '<script' . sicurezza_nonce_attr() . '>if(window.seoConsensoChiedi){(function(){var d=document.createElement("div");d.id="seo-consenso";d.setAttribute("role","dialog");d.setAttribute("aria-label","Consenso alle statistiche");d.style.cssText="position:fixed;left:16px;right:16px;bottom:16px;z-index:9998;max-width:560px;margin:0 auto;background:#0f1630;color:#e6ebf5;border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:14px 16px;font:15px/1.45 Inter,system-ui,sans-serif;box-shadow:0 12px 40px rgba(0,0,0,.4);display:flex;gap:12px;align-items:center;flex-wrap:wrap";'
            . 'd.innerHTML=\'<span style="flex:1 1 260px">' . addslashes(h($a['banner_testo'])) . ' <a href="/privacy-policy/" style="color:#9ad1ff">Privacy</a></span><span style="display:flex;gap:8px"><button type="button" data-consenso="no" style="background:transparent;color:#e6ebf5;border:1px solid rgba(255,255,255,.3);border-radius:999px;padding:8px 14px;cursor:pointer">Rifiuta</button><button type="button" data-consenso="si" style="background:#7c5cff;color:#fff;border:0;border-radius:999px;padding:8px 16px;cursor:pointer;font-weight:600">Accetta</button></span>\';'
            . 'd.querySelectorAll("[data-consenso]").forEach(function(b){b.addEventListener("click",function(){seoConsenso(b.getAttribute("data-consenso")==="si")})});'
            . 'var m=function(){document.body.appendChild(d)};if(document.body)m();else document.addEventListener("DOMContentLoaded",m)})()}</script>';
    }
    return implode("\n", $out);
}

function seo_body_fine_html(): string {
    return seo_codice_libero_attivo() ? sicurezza_nonce_script(trim(seo_cfg()['analisi']['codice_body_fine'])) : '';
}

/** sicurezza: il codice libero head/body esce sul sito solo se la pagina Sicurezza lo ammette (codice_libero = admin). */
function seo_codice_libero_attivo(): bool {
    try { return (string)sicurezza_cfg()['codice_libero'] === 'admin'; } catch (Throwable $e) { return true; }
}

/** Inserisce nel documento finito quello che il pannello SEO aggiunge. Chiamata dal front controller (sito/index.php). */
function seo_inietta_html(string $html): string {
    try {
        $testa = seo_head_html();
        if ($testa !== '') $html = preg_replace('~</head>~', $testa . "\n</head>", $html, 1);
        $inizio = seo_body_inizio_html();
        if ($inizio !== '') $html = preg_replace('~<body([^>]*)>~', '<body$1>' . "\n" . $inizio, $html, 1);
        $fine = seo_body_fine_html();
        if ($fine !== '') $html = preg_replace('~</body>~', $fine . "\n</body>", $html, 1);
    } catch (Throwable $e) {
        error_log('seo_inietta_html: ' . $e->getMessage());
    }
    return $html;
}

// ------------------------------------------------------------
// Titoli, meta, robots
// ------------------------------------------------------------

/** Il <title> secondo il formato del tipo: %titolo% %sep% %sito%. Con i predefiniti: "Titolo - Nome del sito". */
/**
 * I tipi di contenuto dei moduli del sito, oltre ad articoli e pagine (contenuti_tipi): ognuno ha il suo formato del
 * titolo (chiave "uno", per esempio "ricetta") e la sua regola di indicizzazione (chiave del tipo, "ricette").
 */
function seo_tipi_moduli(): array {
    require_once __DIR__ . '/contenuti.php';
    return array_diff_key(contenuti_tipi(), ['articoli' => 1, 'pagine' => 1]);
}

function seo_titolo(string $titolo, string $tipo = 'altro'): string {
    $t = seo_cfg()['titolo'];
    $formato = $t[$tipo] ?? $t['altro'];
    $out = str_replace(['%titolo%', '%sep%', '%sito%'], [$titolo, $t['sep'], $t['sito']], $formato);
    return trim(preg_replace('/\s+/u', ' ', $out));
}

/** Il contenuto del meta robots: index con le direttive avanzate (come oggi) oppure senza. */
function seo_robots_meta(string $r): string {
    if ($r !== 'index') return 'noindex, follow';
    return !empty(seo_cfg()['meta']['robots_avanzati']) ? 'index, follow, max-video-preview:-1, max-image-preview:large, max-snippet:-1' : 'index, follow';
}

/**
 * Il robots scelto nel pannello per un tipo di indirizzo, oppure quello automatico (la regola di oggi).
 * $auto e il valore che il sito calcola da solo.
 */
function seo_robots_tipo(string $tipo, string $auto): string {
    $v = seo_cfg()['robots'][$tipo] ?? 'auto';
    return in_array($v, ['index', 'noindex'], true) ? $v : $auto;
}

/** La descrizione predefinita del pannello, se la pagina non ne ha una. */
function seo_descrizione_predefinita(): string {
    return (string)seo_cfg()['meta']['descrizione'];
}

function seo_og_immagine_predefinita(): string {
    return (string)seo_cfg()['meta']['og_immagine'];
}

/** Il meta twitter:site, se nel pannello c'è l'account (la card summary_large_image la mette già la pagina). */
function seo_meta_twitter(): string {
    $a = seo_cfg()['meta']['twitter_account'];
    return $a !== '' ? '<meta name="twitter:site" content="@' . h(ltrim($a, '@')) . '">' : '';
}

// ------------------------------------------------------------
// robots.txt, llms.txt, sitemap
// ------------------------------------------------------------

/** Il robots.txt predefinito più le righe dei crawler AI bloccati. */
function seo_robots_txt_predefinito(): string {
    $base = function_exists('extra_robots') ? extra_robots() : "User-agent: *\nAllow: /\n\nSitemap: " . SITO_URL . "/sitemap.xml\n";
    $ai = seo_cfg()['robots_txt']['ai'] ?? [];
    $blocchi = '';
    foreach (SEO_CRAWLER_AI as $bot => $x) {
        if (!empty($ai[$bot])) $blocchi .= "User-agent: $bot\nDisallow: /\n\n";
    }
    if ($blocchi === '') return $base;
    // I blocchi vanno prima della regola generale: per i crawler nominati vale il loro gruppo.
    return "# Crawler delle intelligenze artificiali fermati dal pannello SEO\n" . $blocchi . $base;
}

/** Il robots.txt che il sito serve: quello scritto nel pannello, altrimenti il predefinito. */
function seo_robots_txt(): string {
    $p = seo_cfg()['robots_txt']['personale'];
    return trim($p) !== '' ? str_replace("\r\n", "\n", rtrim($p)) . "\n" : seo_robots_txt_predefinito();
}

/** llms.txt: quello scritto nel pannello, altrimenti quello calcolato dagli articoli. */
function seo_llms(): string {
    $p = seo_cfg()['llms']['personale'];
    if (trim($p) !== '') return str_replace("\r\n", "\n", rtrim($p)) . "\n";
    return function_exists('extra_llms') ? extra_llms() : '';
}

/** llms-full.txt: l'indice più il testo intero dei pillar e degli articoli indicizzati, in testo semplice. */
function seo_llms_full(): string {
    $out = [seo_llms(), '', '---', '', '# Testi completi', ''];
    $articoli = array_values(array_filter(sito_contenuti(), fn($c) => $c['tipo'] === 'post' && !$c['rediretto'] && $c['seo']['robots'] === 'index'));
    usort($articoli, fn($a, $b) => ($b['pillar'] <=> $a['pillar']) ?: strcmp($b['data'], $a['data']));
    foreach ($articoli as $c) {
        $html = sito_html_contenuto($c);
        $html = preg_replace('~<(script|style|table)[\s\S]*?</\1>~i', ' ', $html);
        $html = preg_replace('~</(p|h[1-6]|li|div|blockquote)>~i', "\n", $html);
        $html = preg_replace_callback('~<h([2-4])[^>]*>([\s\S]*?)$~i', fn($m) => str_repeat('#', (int)$m[1]) . ' ' . $m[2], $html);
        $testo = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $testo = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $testo)));
        $out[] = '## ' . $c['titolo'];
        $out[] = 'Indirizzo: ' . SITO_URL . $c['percorso'] . ' | Pubblicato: ' . substr($c['data'], 0, 10) . ' | Aggiornato: ' . substr($c['modificato'], 0, 10);
        if ($c['tldr'] !== '') $out[] = 'In breve: ' . trim(preg_replace('/\s+/u', ' ', $c['tldr']));
        $out[] = '';
        $out[] = $testo;
        if ($c['faq']) {
            $out[] = '';
            $out[] = '### Domande frequenti';
            foreach ($c['faq'] as $f) { $out[] = '- ' . $f['domanda']; $out[] = '  ' . trim(preg_replace('/\s+/u', ' ', strip_tags($f['risposta']))); }
        }
        $out[] = '';
    }
    return implode("\n", $out);
}

/**
 * Le voci della sitemap secondo le scelte del pannello: [['loc', 'lastmod', 'tipo', 'immagine']].
 * Con i predefiniti sono le stesse di extra_sitemap(): home, contenuti indicizzabili, categorie indicizzabili con articoli.
 */
function seo_sitemap_voci(): array {
    $s = seo_cfg()['sitemap'];
    $attivi = array_values(array_filter(sito_contenuti(), fn($c) => !$c['rediretto']));
    $articoli = array_values(array_filter($attivi, fn($c) => $c['tipo'] === 'post'));
    $redirectDa = array_flip(array_column(sito_redirect(), 'da'));
    $voci = [['loc' => SITO_URL . '/', 'lastmod' => $articoli[0]['modificato'] ?? '', 'tipo' => 'pagine', 'immagine' => '']];
    foreach ($attivi as $c) {
        if ($c['seo']['robots'] !== 'index' || $c['percorso'] === '/') continue;
        if ($c['tipo'] === 'page' && empty($s['pagine'])) continue;
        $voci[] = ['loc' => SITO_URL . $c['percorso'], 'lastmod' => $c['modificato'], 'tipo' => $c['tipo'] === 'post' ? 'articoli' : 'pagine',
            'immagine' => !empty($s['immagini']) && $c['immagine'] ? $c['immagine']['url'] : ''];
    }
    if (!empty($s['categorie'])) {
        foreach (sito_categorie() as $c) {
            if ($c['robots'] !== 'index' || isset($redirectDa["/category/{$c['slug']}/"])) continue;
            foreach ($articoli as $a) if (in_array($c['slug'], $a['categorie'], true)) { $voci[] = ['loc' => SITO_URL . "/category/{$c['slug']}/", 'lastmod' => $a['modificato'], 'tipo' => 'categorie', 'immagine' => '']; break; }
        }
    }
    // 2.0.0: gli indirizzi dei moduli e degli addon (per esempio le schede dei prodotti della Vetrina), nello stesso formato.
    return (array)cms_filtro('sitemap_voci', $voci);
}

function seo_sitemap_xml(array $voci): string {
    $conImmagini = (bool)array_filter($voci, fn($v) => $v['immagine'] !== '');
    $righe = array_map(function ($v) {
        $x = '<url><loc>' . htmlspecialchars($v['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . ($v['lastmod'] ? '<lastmod>' . substr($v['lastmod'], 0, 10) . '</lastmod>' : '');
        if ($v['immagine'] !== '') $x .= '<image:image><image:loc>' . htmlspecialchars($v['immagine'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</image:loc></image:image>';
        return $x . '</url>';
    }, $voci);
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . ($conImmagini ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '') . '>' . "\n" . implode("\n", $righe) . "\n</urlset>\n";
}

/** /sitemap.xml: unica (come oggi) oppure indice delle sitemap per tipo. */
function seo_sitemap(): string {
    $s = seo_cfg()['sitemap'];
    if (empty($s['divisa'])) return seo_sitemap_xml(seo_sitemap_voci());
    $voci = seo_sitemap_voci();
    $tipi = [];
    foreach ($voci as $v) $tipi[$v['tipo']] = max($tipi[$v['tipo']] ?? '', substr($v['lastmod'], 0, 10));
    $righe = '';
    foreach ($tipi as $t => $ultimo) $righe .= '<sitemap><loc>' . SITO_URL . "/sitemap-$t.xml</loc>" . ($ultimo ? "<lastmod>$ultimo</lastmod>" : '') . "</sitemap>\n";
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $righe . "</sitemapindex>\n";
}

function seo_sitemap_tipo(string $tipo): string {
    return seo_sitemap_xml(array_values(array_filter(seo_sitemap_voci(), fn($v) => $v['tipo'] === $tipo)));
}

// ------------------------------------------------------------
// 404
// ------------------------------------------------------------

/** Registra un 404 del sito (chiamata dal front controller). Mai un errore verso il visitatore. */
function seo_registra_404(string $percorso): void {
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || mb_strlen($percorso) > 500) return;
        // Niente file di sistema e sonde: solo indirizzi che potrebbero essere pagine.
        if (preg_match('~\.(php|env|git|sql|zip|bak|asp|aspx|jsp|cgi|xml|json|txt|ico|png|jpg|jpeg|gif|svg|webp|css|js|map|woff2?|ttf)$~i', $percorso)) return;
        if (preg_match('~^/(wp-admin|wp-login|wp-includes|xmlrpc|\.well-known|cgi-bin|vendor|new/)~', $percorso)) return;
        seo_installa();
        $ref = mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
        db()->prepare('INSERT INTO ' . tbl('seo_404') . ' (percorso, conteggio, referer, primo, ultimo) VALUES (?, 1, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE conteggio = conteggio + 1, ultimo = NOW(), referer = IF(VALUES(referer) <> \'\', VALUES(referer), referer)')
            ->execute([sito_senza_4byte($percorso), sito_senza_4byte($ref)]);
        if (random_int(1, 100) === 1) db()->exec('DELETE FROM ' . tbl('seo_404') . ' WHERE id NOT IN (SELECT id FROM (SELECT id FROM ' . tbl('seo_404') . ' ORDER BY ultimo DESC LIMIT 3000) x)');
    } catch (Throwable $e) {
        error_log('seo_registra_404: ' . $e->getMessage());
    }
}

function seo_404_elenco(int $n = 200): array {
    seo_installa();
    return db()->query('SELECT * FROM ' . tbl('seo_404') . ' ORDER BY conteggio DESC, ultimo DESC LIMIT ' . (int)$n)->fetchAll();
}

function seo_404_togli(int $id): void {
    seo_installa();
    db()->prepare('DELETE FROM ' . tbl('seo_404') . ' WHERE id = ?')->execute([$id]);
}

/** I redirect della tabella del sito (la stessa dei pacchetti e del cambio di slug), per la scheda. */
function seo_redirect_elenco(): array {
    sito_schema_aggiorna();
    return db()->query('SELECT * FROM ' . tbl('redirect') . ' ORDER BY id DESC')->fetchAll();
}

// ------------------------------------------------------------
// JSON-LD: organizzazione e persona dal pannello
// ------------------------------------------------------------

/** I nodi fissi di ogni pagina (WebSite, organizzazione, persona). Con i predefiniti e identico a prima. */
function seo_jsonld_fissi(): array {
    $c = seo_cfg();
    $o = $c['org']; $p = $c['persona']; $s = $c['schema'];
    $id = ['persona' => seo_jsonld_id('persona'), 'servizio' => seo_jsonld_id('servizio'), 'sito' => seo_jsonld_id('sito')];
    $nodi = [];
    if (!empty($s['website'])) {
        $sito = ['@type' => 'WebSite', '@id' => $id['sito'], 'url' => SITO_URL . '/', 'name' => $o['nome_sito'], 'inLanguage' => 'it-IT', 'publisher' => ['@id' => $id['servizio']]];
        if (!empty($s['searchaction'])) $sito['potentialAction'] = ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => SITO_URL . '/?s={search_term_string}'], 'query-input' => 'required name=search_term_string'];
        $nodi[] = $sito;
    }
    if (!empty($s['organization'])) {
        $org = ['@type' => preg_match('/^[A-Za-z]+$/', $o['tipo']) ? $o['tipo'] : 'ProfessionalService', '@id' => $id['servizio'], 'name' => $o['nome'], 'url' => SITO_URL . '/',
            'logo' => ['@type' => 'ImageObject', '@id' => SITO_URL . '/#logo', 'url' => $o['logo']],
            'image' => $o['immagine'], 'founder' => ['@id' => $id['persona']],
            'areaServed' => ['@type' => 'Country', 'name' => $o['area']], 'availableLanguage' => $o['lingua'], 'serviceType' => $o['servizio']];
        if ($o['descrizione'] !== '') $org['description'] = $o['descrizione'];
        if ($o['telefono'] !== '') $org['telephone'] = $o['telefono'];
        if ($o['email'] !== '') $org['email'] = $o['email'];
        if ($o['indirizzo'] !== '') $org['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $o['indirizzo'], 'addressCountry' => 'IT'];
        if (seo_righe($o['sameas'])) $org['sameAs'] = seo_righe($o['sameas']);
        $nodi[] = $org;
    }
    if (!empty($s['person'])) {
        // Segnali E-E-A-T. sameAs: solo profili personali verificati.
        $per = ['@type' => 'Person', '@id' => $id['persona'], 'name' => $p['nome'], 'url' => SITO_URL . '/',
            'image' => $p['immagine'], 'jobTitle' => $p['ruolo'], 'description' => $p['descrizione'],
            'knowsAbout' => seo_righe($p['conosce'])];
        if ($p['credenziale'] !== '') {
            $per['hasCredential'] = ['@type' => 'EducationalOccupationalCredential', 'credentialCategory' => 'certification', 'name' => $p['credenziale']];
            if ($p['credenziale_ente'] !== '') $per['hasCredential']['recognizedBy'] = ['@type' => 'Organization', 'name' => $p['credenziale_ente']];
        }
        $per['worksFor'] = ['@id' => $id['servizio']];
        $per['sameAs'] = seo_righe($p['sameas']);
        $nodi[] = $per;
    }
    return $nodi;
}

/** Filtra i nodi di una pagina secondo gli interruttori (Article, FAQ, Breadcrumb) e aggiunge Speakable se acceso. */
function seo_jsonld_filtra(array $nodi): array {
    $s = seo_cfg()['schema'];
    $out = [];
    foreach ($nodi as $n) {
        $t = $n['@type'] ?? '';
        if ($t === 'BlogPosting' && empty($s['article'])) continue;
        if ($t === 'FAQPage' && empty($s['faq'])) continue;
        if ($t === 'BreadcrumbList' && empty($s['breadcrumb'])) continue;
        if ($t === 'WebPage' && !empty($s['speakable'])) $n['speakable'] = ['@type' => 'SpeakableSpecification', 'cssSelector' => ['.tldr', 'h1']];
        $out[] = $n;
    }
    return $out;
}

/** Il JSON-LD completo di un indirizzo del sito, calcolato come lo calcola la pagina. Per l'anteprima del pannello. */
function seo_jsonld_di(string $percorso): array {
    $percorso = '/' . trim(preg_replace('~^https?://[^/]+~', '', trim($percorso)), '/');
    if ($percorso !== '/') $percorso .= '/';
    $mappa = sito_mappa();
    $v = $mappa[$percorso] ?? null;
    if (!$v) return ['errore' => "L'indirizzo $percorso non e una pagina del sito."];
    $nodi = [];
    switch ($v['genere']) {
        case 'home':
            $nodi = [sito_jsonld_pagina('/', $v['c']['titolo'], (string)cms_impostazione('home_descrizione', ''), sito_jsonld_about())];
            break;
        case 'contenuto':
            $c = $v['c'];
            if ($c['tipo'] === 'post') {
                $cat = $c['categorie'][0] ?? null;
                $briciole = [['Home', '/'], ['Blog', SITO_PAGINA_BLOG]];
                if ($cat) $briciole[] = [sito_categorie()[$cat]['name'] ?? $cat, "/category/$cat/"];
                $briciole[] = [$c['titolo'], $c['percorso']];
                $nodi = array_merge(sito_jsonld_articolo($c), [sito_jsonld_briciole($briciole)]);
            } elseif ($c['tipo'] === 'glossario') {
                $nodi = [sito_jsonld_pagina($c['percorso'], $c['titolo'], $c['tldr']), sito_jsonld_termine($c)];
            } else {
                $c['servizio'] += sito_servizi_predefiniti()[$c['percorso']] ?? [];
                $nodi = [sito_jsonld_pagina($c['percorso'], $c['titolo'], $c['seo']['descrizione'])];
                if (!empty($c['servizio']['tipo'])) $nodi[] = sito_jsonld_servizio($c);
                if ($c['faq']) $nodi[] = sito_jsonld_faq($c);
            }
            break;
        default:
            $nodi = [sito_jsonld_pagina($percorso, $v['nome'] ?? $v['titolo'] ?? '', '')];
    }
    return ['@context' => 'https://schema.org', '@graph' => array_merge(sito_jsonld_fissi(), seo_jsonld_filtra($nodi))];
}

// ------------------------------------------------------------
// Controllo per contenuto: seo_analisi()
// ------------------------------------------------------------

/** Testo semplice da HTML o Markdown, con le infografiche tolte. */
function seo_testo_semplice(string $contenuto, string $formato = 'html'): string {
    $c = preg_replace('/\[IG:[\s\S]*?\[\/IG\]/', ' ', $contenuto);
    if ($formato === 'markdown' && function_exists('markdown_to_html')) $c = markdown_to_html($c);
    $c = preg_replace('~<(script|style)[\s\S]*?</\1>~i', ' ', $c);
    $c = preg_replace('~</(p|h[1-6]|li|div|blockquote|tr)>~i', "\n", $c);
    $t = html_entity_decode(strip_tags($c), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace("/\n\s*\n/", "\n", preg_replace('/[ \t]+/', ' ', $t)));
}

function seo_parole(string $testo): array {
    return preg_split('/\s+/u', trim($testo), -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

/** Stima della larghezza in pixel di un testo nei risultati di Google (Arial 20px titolo, 14px descrizione). */
function seo_pixel(string $s, float $base = 10.0): int {
    $w = 0.0;
    $stretti = 'iljtfr.,;:!\'|I '; $larghi = 'mwMW@%';
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if (str_contains($stretti, $ch)) $w += $base * 0.45;
        elseif (str_contains($larghi, $ch)) $w += $base * 1.45;
        elseif (ctype_upper($ch)) $w += $base * 1.2;
        else $w += $base;
    }
    return (int)round($w);
}

function seo_norm(string $s): string {
    $s = mb_strtolower($s);
    $s = strtr($s, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'á' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}

function seo_contiene(string $dove, string $cosa): bool {
    $cosa = seo_norm($cosa);
    return $cosa !== '' && str_contains(seo_norm($dove), $cosa);
}

/**
 * Il controllo SEO di un contenuto, come Rank Math. Accetta la riga del database (o i campi del form):
 * title, slug, seo_title, meta_description, seo_description, keyword_target, keywords_secondarie, contenuto
 * (o content/html), content_format, immagine (o immagine_url/featured_image), immagine_alt, tldr, faq (JSON o array), type.
 * Ritorna punteggio 0-100, colore, voci [chiave, stato ok|attenzione|no, testo, peso], numeri.
 */
function seo_analisi(array $c): array {
    $cfg = seo_cfg()['aeo'];
    $titolo = trim((string)($c['title'] ?? ''));
    $seoTitolo = trim((string)($c['seo_title'] ?? '')) ?: $titolo;
    $slug = (string)($c['slug'] ?? '');
    $descrizione = trim((string)($c['meta_description'] ?? '')) ?: trim((string)($c['seo_description'] ?? ''));
    $kw = trim((string)($c['keyword_target'] ?? ''));
    $correlate = trim((string)($c['keywords_secondarie'] ?? ''));
    $contenuto = (string)($c['contenuto'] ?? ($c['content'] ?? ($c['html'] ?? '')));
    $formato = (string)($c['content_format'] ?? 'html');
    $html = $formato === 'markdown' && function_exists('markdown_to_html') ? markdown_to_html(preg_replace('/\[IG:[\s\S]*?\[\/IG\]/', ' ', $contenuto)) : $contenuto;
    $testo = seo_testo_semplice($contenuto, $formato);
    $parole = seo_parole($testo);
    $nParole = count($parole);
    $immagine = trim((string)($c['immagine'] ?? ($c['immagine_url'] ?? ($c['featured_image'] ?? ''))));
    $alt = trim((string)($c['immagine_alt'] ?? ($c['featured_image_alt'] ?? '')));
    $tldr = trim((string)($c['tldr'] ?? ''));
    $faq = $c['faq'] ?? '';
    $faq = is_array($faq) ? $faq : sito_faq_da_json((string)$faq);
    $pillar = ($c['type'] ?? '') === 'pillar';
    $paragrafi = array_values(array_filter(array_map('trim', explode("\n", $testo)), fn($p) => $p !== '' && count(seo_parole($p)) > 3));
    $primo = $paragrafi[0] ?? '';
    // Il primo paragrafo di prosa, saltando il titolo ripetuto o un occhiello corto.
    $voci = [];
    $v = function (string $chiave, string $stato, string $testo, int $peso) use (&$voci) { $voci[] = ['chiave' => $chiave, 'stato' => $stato, 'testo' => $testo, 'peso' => $peso]; };
    $ok = fn(bool $b) => $b ? 'ok' : 'no';

    // Keyword
    if ($kw === '') {
        $v('keyword', 'attenzione', 'Keyword principale non impostata: i controlli sulla keyword restano in sospeso.', 10);
    } else {
        $v('kw_titolo', $ok(seo_contiene($seoTitolo, $kw)), seo_contiene($seoTitolo, $kw) ? 'La keyword e nel titolo per Google.' : 'La keyword non compare nel titolo per Google.', 10);
        $v('kw_descrizione', $ok(seo_contiene($descrizione, $kw)), seo_contiene($descrizione, $kw) ? 'La keyword e nella descrizione.' : 'La keyword non compare nella descrizione.', 8);
        $slugNorm = str_replace('-', ' ', $slug);
        $v('kw_url', $ok(seo_contiene($slugNorm, $kw)), seo_contiene($slugNorm, $kw) ? "La keyword e nell'indirizzo." : "La keyword non compare nell'indirizzo (/$slug/).", 6);
        $inizio = implode(' ', array_slice($parole, 0, max(100, (int)($nParole * 0.1))));
        $v('kw_inizio', $ok(seo_contiene($inizio, $kw)), seo_contiene($inizio, $kw) ? "La keyword compare all'inizio del testo." : "La keyword non compare nel primo 10% del testo.", 8);
        preg_match_all('~<h[23][^>]*>([\s\S]*?)</h[23]>~i', $html, $hh);
        $titoli = implode("\n", array_map('strip_tags', $hh[1] ?? []));
        $v('kw_h2', count($hh[1] ?? []) ? $ok(seo_contiene($titoli, $kw)) : 'attenzione', !count($hh[1] ?? []) ? 'Il testo non ha sottotitoli (H2, H3).' : (seo_contiene($titoli, $kw) ? 'La keyword e in almeno un sottotitolo.' : 'La keyword non compare nei sottotitoli H2 e H3.'), 6);
        $d = contenuti_kw_density($testo, $kw);
        $stato = $d['density'] >= 1.8 ? 'ok' : ($d['density'] >= 1.0 ? 'attenzione' : 'no');
        $v('densita', $stato, 'Densita della keyword: ' . $d['density'] . '% (' . $d['exact_count'] . ' volte esatta). Obiettivo 1,8%.' . ($d['density'] > 3.5 ? ' Sopra il 3,5% è troppo.' : ''), 10);
        if ($d['density'] > 3.5) $voci[count($voci) - 1]['stato'] = 'attenzione';
        if ($correlate !== '') {
            $rk = contenuti_kw_correlate($testo, $correlate);
            $v('correlate', $rk['density'] >= 1.5 ? 'ok' : ($rk['density'] >= 0.8 ? 'attenzione' : 'no'), 'Densita delle keyword correlate: ' . $rk['density'] . '%. Obiettivo 1,5%.' . ($rk['details'] ? ' (' . implode(', ', $rk['details']) . ')' : ''), 6);
        } else {
            $v('correlate', 'attenzione', 'Nessuna keyword correlata impostata.', 6);
        }
    }

    // Titolo e descrizione
    $lt = mb_strlen($seoTitolo); $pxT = seo_pixel($seoTitolo, 11.0);
    $v('titolo_lunghezza', $lt === 0 ? 'no' : (($lt >= 30 && $lt <= 60 && $pxT <= 580) ? 'ok' : 'attenzione'),
        $lt === 0 ? 'Manca il titolo.' : "Titolo per Google: $lt caratteri, circa $pxT px" . ($pxT > 580 ? ' (Google taglia intorno ai 580 px).' : ($lt < 30 ? ' (corto: sotto i 30 caratteri).' : '.')), 6);
    $ld = mb_strlen($descrizione); $pxD = seo_pixel($descrizione, 7.0);
    $v('descrizione_lunghezza', $ld === 0 ? 'no' : (($ld >= 120 && $ld <= 160) ? 'ok' : 'attenzione'),
        $ld === 0 ? 'Manca la descrizione per Google: lo snippet lo decide Google.' : "Descrizione: $ld caratteri, circa $pxD px" . ($ld > 160 ? ' (sopra i 160 Google taglia).' : ($ld < 120 ? ' (sotto i 120: c\'è spazio per dire di più).' : '.')), 6);

    // Immagini
    $v('immagine', $immagine === '' ? 'no' : ($alt === '' ? 'attenzione' : 'ok'), $immagine === '' ? 'Manca l\'immagine in evidenza.' : ($alt === '' ? 'Immagine in evidenza senza testo alternativo (alt).' : 'Immagine in evidenza con testo alternativo.'), 6);
    preg_match_all('~<img\b[^>]*>~i', $html, $imgs);
    $senzaAlt = 0;
    foreach ($imgs[0] as $im) if (!preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $im)) $senzaAlt++;
    $v('img_alt', !count($imgs[0]) ? 'attenzione' : ($senzaAlt ? 'attenzione' : 'ok'), !count($imgs[0]) ? 'Nel testo non ci sono immagini.' : ($senzaAlt ? "$senzaAlt immagini nel testo senza alt su " . count($imgs[0]) . '.' : count($imgs[0]) . ' immagini nel testo, tutte con alt.'), 4);

    // Link
    preg_match_all('~<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']~i', $html, $links);
    $interni = 0; $esterni = 0;
    foreach ($links[1] as $u) {
        if (str_starts_with($u, '#') || str_starts_with($u, 'mailto:') || str_starts_with($u, 'tel:')) continue;
        if (!preg_match('~^https?://~', $u) || str_contains($u, (string)parse_url(SITO_URL, PHP_URL_HOST))) $interni++; else $esterni++;
    }
    $v('link_interni', $interni >= 2 ? 'ok' : ($interni === 1 ? 'attenzione' : 'no'), "$interni link interni (almeno 2).", 6);
    $v('link_esterni', $esterni >= 1 ? 'ok' : 'attenzione', $esterni ? "$esterni link esterni (le fonti)." : 'Nessun link esterno: le fonti dei numeri vanno linkate.', 4);

    // Lunghezza
    $soglia = $pillar ? 3500 : 1000;
    $v('lunghezza', $nParole >= $soglia ? 'ok' : ($nParole >= $soglia * 0.6 ? 'attenzione' : 'no'), "$nParole parole" . ($pillar ? ' (pillar: minimo 3500).' : ' (consigliate almeno 1000).'), 8);

    // Leggibilita: frasi lunghe e paragrafi lunghi
    $frasi = preg_split('/(?<=[.!?])\s+/u', $testo, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $mediaFrase = $frasi ? $nParole / count($frasi) : 0;
    $lunghe = count(array_filter($frasi, fn($f) => count(seo_parole($f)) > 30));
    $percLunghe = $frasi ? round($lunghe / count($frasi) * 100) : 0;
    $v('leggibilita', $mediaFrase <= 20 && $percLunghe <= 15 ? 'ok' : ($mediaFrase <= 25 && $percLunghe <= 25 ? 'attenzione' : 'no'), 'Frasi di ' . round($mediaFrase) . " parole in media, $percLunghe% sopra le 30 parole.", 6);
    $paragrafiLunghi = count(array_filter($paragrafi, fn($p) => count(seo_parole($p)) > 150));
    $v('paragrafi', $paragrafiLunghi === 0 ? 'ok' : ($paragrafiLunghi <= 2 ? 'attenzione' : 'no'), $paragrafiLunghi ? "$paragrafiLunghi paragrafi sopra le 150 parole." : 'Nessun paragrafo sopra le 150 parole.', 3);
    $trattini = preg_match_all('/\x{2014}|\x{2013}/u', $testo . ' ' . $titolo . ' ' . $descrizione);
    $v('trattini', $trattini ? 'no' : 'ok', $trattini ? "$trattini trattini lunghi: la regola del sito li vieta." : 'Nessun trattino lungo.', 3);

    // AEO: risposta diretta in cima, In breve, domande frequenti
    $nPrimo = count(seo_parole($primo));
    $max = (int)($cfg['parole_primo_paragrafo'] ?: 60);
    $v('primo_paragrafo', $nPrimo === 0 ? 'no' : ($nPrimo <= $max ? 'ok' : 'attenzione'), $nPrimo === 0 ? 'Testo vuoto.' : "Primo paragrafo di $nPrimo parole (massimo $max per una risposta diretta).", 4);
    $nTldr = count(seo_parole($tldr));
    $v('tldr', $nTldr === 0 ? 'no' : (($nTldr >= (int)$cfg['tldr_min'] && $nTldr <= (int)$cfg['tldr_max'] + 10) ? 'ok' : 'attenzione'), $nTldr === 0 ? 'Manca "In breve" (la risposta diretta per motori e IA).' : "\"In breve\" di $nTldr parole (da {$cfg['tldr_min']} a {$cfg['tldr_max']}).", 5);
    $v('faq', count($faq) >= 3 ? 'ok' : (count($faq) ? 'attenzione' : 'no'), count($faq) ? count($faq) . ' domande frequenti (FAQPage).' : 'Nessuna domanda frequente: senza, niente FAQPage nei dati strutturati.', 4);

    $totale = 0; $preso = 0.0;
    foreach ($voci as $x) { $totale += $x['peso']; $preso += $x['stato'] === 'ok' ? $x['peso'] : ($x['stato'] === 'attenzione' ? $x['peso'] / 2 : 0); }
    $punteggio = $totale ? (int)round($preso / $totale * 100) : 0;
    return ['punteggio' => $punteggio, 'colore' => $punteggio >= 80 ? 'verde' : ($punteggio >= 50 ? 'giallo' : 'rosso'), 'voci' => $voci,
        'parole' => $nParole, 'keyword' => $kw, 'titolo_px' => $pxT, 'descrizione_px' => $pxD, 'ok' => count(array_filter($voci, fn($x) => $x['stato'] === 'ok')), 'totale' => count($voci)];
}

/** L'HTML del riquadro del controllo (per la scheda del contenuto e la panoramica). */
function seo_analisi_html(array $a): string {
    // 1.4.0: i colori degli stati del pannello, che seguono l'aspetto scelto
    $colore = ['verde' => 'var(--successo-testo)', 'giallo' => 'var(--attenzione-testo)', 'rosso' => 'var(--errore-testo)'][$a['colore']];
    $segno = ['ok' => '&#10003;', 'attenzione' => '&#9679;', 'no' => '&#10007;'];
    $cl = ['ok' => 'var(--successo-testo)', 'attenzione' => 'var(--attenzione-testo)', 'no' => 'var(--errore-testo)'];
    $out = '<div class="seo-punteggio" style="display:flex;align-items:center;gap:1rem;margin-bottom:.8rem"><div style="width:58px;height:58px;border-radius:50%;border:4px solid ' . $colore . ';display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;color:' . $colore . '">' . $a['punteggio'] . '</div>'
        . '<div><b>' . $a['ok'] . ' controlli su ' . $a['totale'] . ' a posto</b><div class="hint">' . $a['parole'] . ' parole' . ($a['keyword'] !== '' ? ' &middot; keyword: ' . h($a['keyword']) : ' &middot; nessuna keyword') . '</div></div></div><ul style="list-style:none;padding:0;margin:0;font-size:.86rem">';
    foreach ($a['voci'] as $x) $out .= '<li style="padding:.28rem 0;border-bottom:1px solid var(--border);display:flex;gap:.5rem"><span style="color:' . $cl[$x['stato']] . ';font-weight:700;width:1em">' . $segno[$x['stato']] . '</span><span>' . h($x['testo']) . '</span></li>';
    return $out . '</ul>';
}

/** Tutti i contenuti pubblicati con il punteggio, per la Panoramica. */
function seo_panoramica(): array {
    $out = [];
    foreach (['articoli' => 'articles', 'pagine' => 'pages'] as $tipo => $tab) {
        $campo = $tipo === 'pagine' ? 'html' : 'content';
        $w = contenuti_sql_stato($tipo, 'pubblicato');
        foreach (db()->query("SELECT * FROM " . tbl($tab) . " WHERE $w")->fetchAll() as $r) {
            if ($tipo === 'pagine' && trim((string)$r['html']) === '' && trim((string)$r['struttura']) === '') continue;
            $riga = $r + ['contenuto' => (string)($r[$campo] ?? '')];
            // Una pagina o un articolo fatti con l'editor: il testo e quello disegnato.
            if (trim((string)($r['struttura'] ?? '')) !== '') {
                require_once __DIR__ . '/editor.php';
                $albero = editor_decodifica((string)$r['struttura']);
                if ($albero) { [$h] = editor_disegna($albero, contenuti_percorso($tipo, $r)); $riga['contenuto'] = $h; $riga['content_format'] = 'html'; }
            }
            $a = seo_analisi($riga);
            $out[] = ['tipo' => $tipo, 'id' => (int)$r['id'], 'titolo' => $r['title'], 'percorso' => contenuti_percorso($tipo, $r), 'punteggio' => $a['punteggio'], 'colore' => $a['colore'],
                'parole' => $a['parole'], 'keyword' => $a['keyword'], 'robots' => $tipo === 'pagine' ? ($r['robots'] === 'noindex' ? 'noindex' : 'index') : sito_robots_articolo($r),
                'tldr' => trim((string)$r['tldr']) !== '', 'faq' => (bool)sito_faq_da_json($r['faq']), 'descrizione' => trim((string)$r['meta_description']) !== '' || trim((string)$r['seo_description']) !== '',
                'immagine' => trim((string)($r['immagine_url'] ?: ($r['featured_image'] ?? ''))) !== '',
                'primo_paragrafo' => (array_values(array_filter($a['voci'], fn($x) => $x['chiave'] === 'primo_paragrafo'))[0]['stato'] ?? '') === 'ok',
                'voci' => $a['voci'], 'type' => $r['type'] ?? ''];
        }
    }
    usort($out, fn($a, $b) => $a['punteggio'] <=> $b['punteggio']);
    return $out;
}
