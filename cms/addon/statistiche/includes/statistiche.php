<?php
/**
 * Il motore delle Statistiche delle visite (addon, 2.0.0): conta le pagine del sito pubblico lato server, senza cookie
 * e senza salvare l'indirizzo IP.
 *
 * Come si conta (st_conta, chiamata dall'evento sito_pagina_servita del nucleo, sito/index.php):
 *  - solo pagine HTML con risposta 200, in GET, fuori dall'anteprima del pannello;
 *  - non i robot (user agent noti, e richieste senza i segni di un browser: Mozilla/, Accept text/html, Accept-Language),
 *    non le pagine caricate in anticipo (prefetch), non chi è entrato nel pannello (si guarda la sessione solo se il
 *    browser ha già il cookie della sessione: ai visitatori non si apre e non si manda niente);
 *  - con "Rispetta Do Not Track" acceso, non chi manda DNT: 1 o Sec-GPC: 1.
 * Il visitatore unico del giorno: impronta = sha256(sale del giorno | IP | user agent | host), 16 byte. Il sale cambia
 * ogni giorno e quello vecchio si cancella (st_sale), così le impronte di ieri non si possono più ricalcolare né
 * collegare a quelle di oggi; le impronte si cancellano al primo passaggio del giorno nuovo. L'IP non si scrive mai.
 * Si scrive una riga per giorno + pagina + provenienza + campagna + dispositivo (INSERT ... ON DUPLICATE KEY UPDATE):
 * visite +1, visitatori +1 solo alla prima pagina del giorno di quel visitatore (così la provenienza è quella d'ingresso).
 * La scrittura avviene dopo la risposta (register_shutdown_function, e fastcgi_finish_request dove c'è).
 */
require_once dirname(__DIR__, 3) . '/includes/cms.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';

const ST_DISPOSITIVI = ['computer' => 'Computer', 'telefono' => 'Telefono', 'tablet' => 'Tablet'];
const ST_VISTE_MAX = 1000; // pagine al giorno di uno stesso visitatore: oltre, è un programma e non si conta più
const ST_ROBOT = '~bot|crawl|spider|slurp|fetch|preview|headless|curl|wget|python|httpclient|monitor|scan|lighthouse|pingdom|uptime|facebookexternalhit|whatsapp|telegram|slack|discord|embedly|bingpreview|google-inspectiontool|gptbot|claude|perplexity|ahrefs|semrush|mj12|yandex|baidu|bytespider|petal|applebot|java/|go-http|okhttp|axios|node-fetch|libwww|phantom|selenium|puppeteer|playwright|httrack|archive~i';

function st_tabelle(): array {
    return [
        'stat_visite' => 'CREATE TABLE IF NOT EXISTS ' . tbl('stat_visite') . ' (
            `giorno` DATE NOT NULL,
            `chiave` BINARY(16) NOT NULL,
            `pagina` VARCHAR(191) NOT NULL,
            `fonte` VARCHAR(100) NOT NULL DEFAULT \'\',
            `utm_source` VARCHAR(80) NOT NULL DEFAULT \'\',
            `utm_medium` VARCHAR(80) NOT NULL DEFAULT \'\',
            `utm_campaign` VARCHAR(80) NOT NULL DEFAULT \'\',
            `dispositivo` VARCHAR(10) NOT NULL DEFAULT \'computer\',
            `visite` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `visitatori` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`giorno`, `chiave`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
        'stat_impronte' => 'CREATE TABLE IF NOT EXISTS ' . tbl('stat_impronte') . ' (
            `giorno` DATE NOT NULL,
            `impronta` BINARY(16) NOT NULL,
            `viste` INT(11) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`giorno`, `impronta`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    ];
}

function st_installa(): string {
    foreach (st_tabelle() as $sql) db()->exec($sql);
    return 'tabelle delle statistiche a posto';
}

function st_impostazioni_predefinite(): array {
    return ['dnt' => 0, 'giorni' => 395];
}

/** Le impostazioni e il sale del giorno in una query sola (la conta deve pesare poco). */
function st_letture(): array {
    static $r = null;
    if ($r !== null) return $r;
    $st = db()->prepare('SELECT setting_key, setting_value FROM ' . tbl('settings') . ' WHERE setting_key IN (?, ?)');
    $st->execute(['statistiche_impostazioni', 'statistiche_sale']);
    $v = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $imp = json_decode((string)($v['statistiche_impostazioni'] ?? ''), true);
    $sale = json_decode((string)($v['statistiche_sale'] ?? ''), true);
    return $r = ['imp' => (is_array($imp) ? $imp : []) + st_impostazioni_predefinite(), 'sale' => is_array($sale) ? $sale : []];
}

function st_impostazioni(): array {
    return st_letture()['imp'];
}

function st_impostazioni_salva(array $n): void {
    $imp = array_intersect_key($n, st_impostazioni_predefinite()) + st_impostazioni();
    save_setting('statistiche_impostazioni', json_encode($imp));
}

/**
 * Il sale di oggi. Se quello salvato è di un altro giorno se ne fa uno nuovo (quello vecchio si sovrascrive, quindi
 * sparisce) e si cancellano le impronte dei giorni prima.
 */
function st_sale(string $giorno): string {
    $s = st_letture()['sale'];
    if (($s['giorno'] ?? '') === $giorno && is_string($s['sale'] ?? null) && strlen($s['sale']) === 64) return $s['sale'];
    $nuovo = bin2hex(random_bytes(32));
    // Due richieste insieme a mezzanotte: vince la prima, l'altra rilegge (si sovrascrive solo il sale di un altro giorno).
    $json = json_encode(['giorno' => $giorno, 'sale' => $nuovo]);
    db()->prepare('INSERT INTO ' . tbl('settings') . ' (setting_key, setting_value) VALUES (\'statistiche_sale\', ?)
        ON DUPLICATE KEY UPDATE setting_value = IF(setting_value LIKE ?, setting_value, ?)')
        ->execute([$json, '{"giorno":"' . $giorno . '"%', $json]);
    $st = db()->prepare('SELECT setting_value FROM ' . tbl('settings') . ' WHERE setting_key = \'statistiche_sale\'');
    $st->execute();
    $s = json_decode((string)$st->fetchColumn(), true) ?: [];
    db()->prepare('DELETE FROM ' . tbl('stat_impronte') . ' WHERE giorno < ?')->execute([$giorno]);
    return (string)($s['sale'] ?? $nuovo);
}

// ------------------------------------------------------------
// Chi si conta
// ------------------------------------------------------------

/** È un robot (o comunque non un browser che apre una pagina)? Si guardano solo le intestazioni della richiesta. */
function st_e_robot(array $srv): bool {
    $ua = (string)($srv['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || strlen($ua) > 600 || !str_starts_with($ua, 'Mozilla/') || preg_match(ST_ROBOT, $ua)) return true;
    if (!str_contains(strtolower((string)($srv['HTTP_ACCEPT'] ?? '')), 'text/html')) return true;
    // Un browser vero dice sempre in che lingue legge; "*" lo mandano i programmi (per esempio fetch di Node).
    if (in_array(trim((string)($srv['HTTP_ACCEPT_LANGUAGE'] ?? '')), ['', '*'], true)) return true;
    // Pagine chieste in anticipo dal browser (prefetch, prerender): non sono visite.
    if (preg_match('/prefetch|prerender/i', (string)($srv['HTTP_SEC_PURPOSE'] ?? '') . (string)($srv['HTTP_PURPOSE'] ?? '') . (string)($srv['HTTP_X_MOZ'] ?? ''))) return true;
    $dest = (string)($srv['HTTP_SEC_FETCH_DEST'] ?? '');
    if ($dest !== '' && $dest !== 'document') return true;
    return false;
}

/** Telefono, tablet o computer, dallo user agent. */
function st_dispositivo(string $ua): string {
    if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|Nexus (7|9|10)|SM-T\d|Android(?!.*Mobile)/i', $ua)) return 'tablet';
    if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|Opera Mini|IEMobile|BlackBerry/i', $ua)) return 'telefono';
    return 'computer';
}

/** C'è dentro una persona del pannello? Solo se il browser ha già il cookie della sessione (ai visitatori niente sessione). */
function st_e_del_pannello(): bool {
    if (session_status() === PHP_SESSION_ACTIVE) return !empty($_SESSION['admin_user_id']);
    $nome = session_name();
    $id = (string)($_COOKIE[$nome] ?? '');
    if ($id === '' || !preg_match('/^[A-Za-z0-9,-]{20,128}$/', $id) || headers_sent()) return false;
    // In sola lettura e senza intestazioni: niente cookie nuovi, niente Cache-Control della sessione, niente file creati.
    $ok = @session_start(['read_and_close' => true, 'use_strict_mode' => 0, 'cache_limiter' => '', 'use_cookies' => 1, 'use_only_cookies' => 1]);
    $admin = $ok && !empty($_SESSION['admin_user_id']);
    $_SESSION = [];
    return $admin;
}

/** Il dominio della provenienza, senza "www." e senza percorso; '' se è il sito stesso, se manca o se non è un dominio. */
function st_fonte(string $referer, string $host): string {
    if ($referer === '' || strlen($referer) > 2000) return '';
    $h = strtolower((string)parse_url($referer, PHP_URL_HOST));
    $h = (string)preg_replace('/^www\./', '', $h);
    if ($h === '' || strlen($h) > 100 || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $h)) return '';
    $mio = (string)preg_replace('/^www\./', '', strtolower((string)preg_replace('/:\d+$/', '', $host)));
    return $h === $mio ? '' : $h;
}

/** Un valore utm pulito: lettere, cifre e pochi segni (niente HTML), al massimo 80 caratteri. */
function st_utm($v): string {
    if (!is_string($v)) return '';
    $v = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', mb_check_encoding($v, 'UTF-8') ? $v : '');
    $v = (string)preg_replace('/[^\p{L}\p{N} ._+\/:@-]/u', '', $v);
    return mb_substr(trim((string)preg_replace('/\s+/u', ' ', $v)), 0, 80);
}

/**
 * La visita di questa richiesta, se si conta. L'evento arriva prima che la pagina parta; la scrittura si fa dopo.
 * Mai un errore verso il visitatore.
 */
function st_conta(int $codice, string $percorso): void {
    try {
        if ($codice !== 200 || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        if ((defined('SITO_ANTEPRIMA') && SITO_ANTEPRIMA) || isset($_GET['preview'])) return;
        if (st_e_robot($_SERVER)) return;
        if (st_impostazioni()['dnt'] && (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1')) return;
        if (st_e_del_pannello()) return;
        if (!function_exists('sicurezza_ip')) require_once dirname(__DIR__, 3) . '/includes/sicurezza.php';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $dati = [
            'pagina' => mb_substr((string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', mb_check_encoding($percorso, 'UTF-8') ? $percorso : '/'), 0, 191) ?: '/',
            'fonte' => st_fonte((string)($_SERVER['HTTP_REFERER'] ?? ''), $host),
            'utm_source' => st_utm($_GET['utm_source'] ?? ''), 'utm_medium' => st_utm($_GET['utm_medium'] ?? ''), 'utm_campaign' => st_utm($_GET['utm_campaign'] ?? ''),
            'dispositivo' => st_dispositivo((string)$_SERVER['HTTP_USER_AGENT']),
        ];
        // L'IP e lo user agent servono solo qui, per l'impronta: non escono da questa funzione e non si scrivono.
        $grezzo = sicurezza_ip() . '|' . (string)$_SERVER['HTTP_USER_AGENT'] . '|' . strtolower($host);
        register_shutdown_function(function () use ($dati, $grezzo) {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            try { st_registra($dati, $grezzo); } catch (Throwable $e) { error_log('statistiche: ' . $e->getMessage()); }
        });
    } catch (Throwable $e) {
        error_log('statistiche: ' . $e->getMessage());
    }
}

/** Scrive la visita: l'impronta del giorno (visitatore nuovo?) e la riga aggregata. Tabelle mancanti: si creano e si riprova. */
function st_registra(array $d, string $grezzo, ?string $giorno = null): void {
    $giorno ??= date('Y-m-d');
    for ($giro = 0; $giro < 2; $giro++) {
        try {
            $impronta = substr(hash('sha256', st_sale($giorno) . '|' . $grezzo, true), 0, 16);
            $st = db()->prepare('INSERT INTO ' . tbl('stat_impronte') . ' (giorno, impronta, viste) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE viste = LAST_INSERT_ID(viste + 1)');
            $st->execute([$giorno, $impronta]);
            $nuovo = $st->rowCount() === 1;
            if (!$nuovo && (int)db()->lastInsertId() > ST_VISTE_MAX) return;
            $chiave = md5(implode("\x1f", [$d['pagina'], $d['fonte'], $d['utm_source'], $d['utm_medium'], $d['utm_campaign'], $d['dispositivo']]), true);
            db()->prepare('INSERT INTO ' . tbl('stat_visite') . ' (giorno, chiave, pagina, fonte, utm_source, utm_medium, utm_campaign, dispositivo, visite, visitatori) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE visite = visite + 1, visitatori = visitatori + ?')
                ->execute([$giorno, $chiave, $d['pagina'], $d['fonte'], $d['utm_source'], $d['utm_medium'], $d['utm_campaign'], $d['dispositivo'], $nuovo ? 1 : 0, $nuovo ? 1 : 0]);
            return;
        } catch (PDOException $e) {
            if ($giro === 0 && in_array((string)$e->getCode(), ['42S02'], true)) { st_installa(); continue; }
            throw $e;
        }
    }
}

/** La pulizia del cron: righe oltre il periodo scelto, impronte dei giorni passati. */
function st_pulizia(): string {
    st_installa();
    $g = max(30, min(3650, (int)st_impostazioni()['giorni']));
    $a = db()->prepare('DELETE FROM ' . tbl('stat_visite') . ' WHERE giorno < ?');
    $a->execute([date('Y-m-d', strtotime("-$g days"))]);
    $b = db()->prepare('DELETE FROM ' . tbl('stat_impronte') . ' WHERE giorno < ?');
    $b->execute([date('Y-m-d')]);
    return $a->rowCount() ? $a->rowCount() . " righe oltre $g giorni tolte" : '';
}

// ------------------------------------------------------------
// Letture per il pannello
// ------------------------------------------------------------

/** Il periodo: [dal, al] (date AAAA-MM-GG) da 7, 30, 90 giorni o personalizzato. Al massimo due anni. */
function st_periodo(array $q): array {
    $oggi = date('Y-m-d');
    $p = (string)($q['periodo'] ?? '30');
    if ($p === 'personalizzato') {
        $dal = (string)($q['dal'] ?? ''); $al = (string)($q['al'] ?? '');
        $ok = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
        if ($ok($dal) && $ok($al)) {
            if ($dal > $al) [$dal, $al] = [$al, $dal];
            if ($al > $oggi) $al = $oggi;
            if ((strtotime($al) - strtotime($dal)) / 86400 > 730) $dal = date('Y-m-d', strtotime($al . ' -730 days'));
            return ['personalizzato', $dal, $al];
        }
        $p = '30';
    }
    $n = in_array($p, ['7', '30', '90'], true) ? (int)$p : 30;
    return [(string)$n, date('Y-m-d', strtotime('-' . ($n - 1) . ' days')), $oggi];
}

/** Il periodo prima, della stessa lunghezza. */
function st_periodo_prima(string $dal, string $al): array {
    $giorni = (int)round((strtotime($al) - strtotime($dal)) / 86400) + 1;
    return [date('Y-m-d', strtotime($dal . ' -' . $giorni . ' days')), date('Y-m-d', strtotime($dal . ' -1 day'))];
}

function st_totali(string $dal, string $al): array {
    st_installa_una_volta();
    $st = db()->prepare('SELECT COALESCE(SUM(visite), 0) v, COALESCE(SUM(visitatori), 0) u FROM ' . tbl('stat_visite') . ' WHERE giorno BETWEEN ? AND ?');
    $st->execute([$dal, $al]);
    $r = $st->fetch();
    return ['visite' => (int)$r['v'], 'visitatori' => (int)$r['u']];
}

/** Visite e visitatori per ogni giorno del periodo (anche i giorni a zero). */
function st_per_giorno(string $dal, string $al): array {
    st_installa_una_volta();
    $st = db()->prepare('SELECT giorno, SUM(visite) v, SUM(visitatori) u FROM ' . tbl('stat_visite') . ' WHERE giorno BETWEEN ? AND ? GROUP BY giorno');
    $st->execute([$dal, $al]);
    $c = [];
    foreach ($st->fetchAll() as $r) $c[$r['giorno']] = ['visite' => (int)$r['v'], 'visitatori' => (int)$r['u']];
    $out = [];
    // Un giorno alla volta con le date, non con 86400 secondi: il giorno del cambio dell'ora ne ha 23 o 25.
    for ($g = new DateTimeImmutable($dal); $g->format('Y-m-d') <= $al; $g = $g->modify('+1 day')) $out[$g->format('Y-m-d')] = $c[$g->format('Y-m-d')] ?? ['visite' => 0, 'visitatori' => 0];
    return $out;
}

/** Una classifica: per 'pagina', 'fonte', 'dispositivo' o 'campagna' (utm_source, utm_medium, utm_campaign insieme). */
function st_classifica(string $per, string $dal, string $al, int $limite = 20): array {
    st_installa_una_volta();
    $col = ['pagina' => 'pagina', 'fonte' => 'fonte', 'dispositivo' => 'dispositivo', 'campagna' => 'utm_source, utm_medium, utm_campaign'][$per] ?? 'pagina';
    $dove = $per === 'campagna' ? " AND (utm_source <> '' OR utm_medium <> '' OR utm_campaign <> '')" : '';
    $ordina = in_array($per, ['fonte', 'campagna', 'dispositivo'], true) ? 'u DESC, v DESC' : 'v DESC, u DESC';
    $st = db()->prepare("SELECT $col, SUM(visite) v, SUM(visitatori) u FROM " . tbl('stat_visite') . " WHERE giorno BETWEEN ? AND ?$dove GROUP BY $col ORDER BY $ordina LIMIT " . max(1, min(500, $limite)));
    $st->execute([$dal, $al]);
    return array_map(fn($r) => $r + ['visite' => (int)$r['v'], 'visitatori' => (int)$r['u']], $st->fetchAll());
}

/** Tutte le righe del periodo, per il CSV. */
function st_righe(string $dal, string $al): array {
    st_installa_una_volta();
    $st = db()->prepare('SELECT giorno, pagina, fonte, utm_source, utm_medium, utm_campaign, dispositivo, visite, visitatori FROM ' . tbl('stat_visite') . ' WHERE giorno BETWEEN ? AND ? ORDER BY giorno, visite DESC LIMIT 200000');
    $st->execute([$dal, $al]);
    return $st->fetchAll();
}

function st_installa_una_volta(): void {
    static $f = false;
    if (!$f) { st_installa(); $f = true; }
}

/** Il cambio rispetto a prima, in parole: "+12%" / "-5%" / "nuovo" / "uguale". */
function st_cambio(int $ora, int $prima): string {
    if ($prima === 0) return $ora > 0 ? 'prima non c\'erano' : 'uguale';
    $p = (int)round(($ora - $prima) / $prima * 100);
    return $p === 0 ? 'uguale' : ($p > 0 ? '+' . $p . '%' : $p . '%');
}

/** Il testo pronto per l'informativa privacy (da copiare nella pagina Privacy del sito). */
function st_testo_privacy(): string {
    $dnt = st_impostazioni()['dnt'];
    return "Statistiche delle visite\n\n"
        . "Per sapere quante persone visitano il sito e quali pagine leggono, il sito conta le visite da solo, sul proprio server, senza servizi esterni (niente Google Analytics o simili).\n\n"
        . "Non usiamo cookie né altri strumenti che salvano informazioni sul tuo dispositivo. Non salviamo il tuo indirizzo IP. Per non contarti due volte nello stesso giorno, il server calcola un codice anonimo a partire dall'indirizzo IP e dal tipo di browser, mescolati con un valore casuale che cambia ogni giorno e poi viene cancellato: il codice non permette di risalire a te, non si può collegare alle visite dei giorni dopo e viene cancellato entro il giorno successivo.\n\n"
        . "Conserviamo solo numeri complessivi: per ogni giorno e per ogni pagina quante visite, da quale sito si arrivava (solo il nome del sito, per esempio google.com), le campagne indicate nei link (utm_source, utm_medium, utm_campaign) e il tipo di dispositivo (computer, telefono o tablet). Non registriamo il paese, la città né altri dati su di te.\n\n"
        . ($dnt ? "Se il tuo browser chiede di non essere tracciato (Do Not Track o Global Privacy Control), la tua visita non viene contata.\n\n" : '')
        . 'I numeri si conservano per ' . (int)st_impostazioni()['giorni'] . " giorni e poi si cancellano. La base giuridica è il legittimo interesse a capire come viene usato il sito; trattandosi di dati anonimi e aggregati, non serve il consenso.";
}
