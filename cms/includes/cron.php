<?php
/**
 * Il cron di plumeCMS (1.4.0: le due passate in funzioni, così il pannello le lancia con "Esegui adesso").
 *
 * Due righe nel cron dell'hosting (cPanel, Processi cron), con il PHP da riga di comando:
 *   cron-daily.php       una volta al giorno (per esempio alle 06:00): applica i pacchetti di aggiornamento programmati
 *                        (pannello Aggiornamenti), fa i compiti dei moduli (chiave cron del manifest), manda le email del
 *                        mattino (Social, includes/social.php: "Oggi sul sito" e i testi del calendario), manda a IndexNow
 *                        gli indirizzi in coda (pannello SEO e AEO). Scrive logs/cron-daily.log.
 *   cron-newsletter.php  ogni 5 minuti: avvia le campagne arrivate alla loro ora, manda un giro di email (fino a 40, nel
 *                        limite orario della newsletter), fa avanzare i flussi automatici, svuota la coda di IndexNow;
 *                        una volta al giorno pulisce il registro della posta (oltre 60 giorni) e gli iscritti mai confermati.
 *                        Scrive logs/newsletter.log solo quando ha fatto qualcosa.
 *
 * Ogni passata, da qualunque parte arrivi, lascia una riga in logs/cron-stato.json: quando, da dove ("cron" se dalla riga
 * di comando o dalla chiamata con CMS_CRON_TOKEN, "pannello" se dal pulsante), quanto e durata e cosa ha fatto. La pagina
 * Log cron e i Primi passi della dashboard leggono da li; "cron attivo" vuol dire una passata arrivata dal cron vero.
 */

require_once __DIR__ . '/config.php';

const CRON_GIORNO_ORE = 36;      // il giro del mattino e "fermo" dopo 36 ore senza passate
const CRON_NEWSLETTER_MIN = 60;  // quello della newsletter dopo un'ora

function cron_file_stato(): string {
    return cms_radice() . '/logs/cron-stato.json';
}

/** Le ultime passate: ['giornaliero' => [...], 'newsletter' => [...], 'giornaliero_cron' => [...], 'newsletter_cron' => [...]]. */
function cron_stato(): array {
    $s = json_decode((string)@file_get_contents(cron_file_stato()), true);
    return is_array($s) ? $s : [];
}

/** Ricorda una passata. La riga "_cron" tiene l'ultima arrivata dal cron vero, anche se poi si preme il pulsante. */
function cron_segna(string $quale, string $da, float $inizio, array $righe): void {
    @mkdir(cms_radice() . '/logs', 0755, true);
    $s = cron_stato();
    $voce = ['quando' => time(), 'da' => $da, 'secondi' => round(microtime(true) - $inizio, 2), 'righe' => array_slice(array_values($righe), 0, 40)];
    $s[$quale] = $voce;
    if ($da === 'cron') $s[$quale . '_cron'] = $voce;
    @file_put_contents(cron_file_stato(), json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
}

/** Il giro del mattino. Ritorna il testo che finisce nel log (e che la riga di comando stampa). */
function cron_giornaliero(string $da = 'cron'): string {
    $inizio = microtime(true);
    $logFile = cms_radice() . '/logs/cron-daily.log';
    @mkdir(dirname($logFile), 0755, true);
    $righe = [];

    // 1. I pacchetti di aggiornamento programmati (pannello Aggiornamenti, "Applica alle...").
    try {
        require_once __DIR__ . '/aggiornamenti.php';
        $agg = agg_applica_in_attesa();
        if ($agg) { foreach ($agg as $r) $righe[] = "Aggiornamento: $r"; }
        else $righe[] = 'Nessun pacchetto di aggiornamento in scadenza';
    } catch (Throwable $e) {
        $righe[] = 'ERRORE aggiornamenti: ' . $e->getMessage();
    }

    // 2. I compiti dei moduli del sito (per esempio un'email del mattino con gli articoli che escono oggi).
    require_once __DIR__ . '/sito.php';
    foreach (cms_moduli_elenco('cron') as $x) {
        try {
            $esito = ($x['valore'])();
            $righe[] = ucfirst((string)$x['chiave']) . ': ' . (is_string($esito) && $esito !== '' ? $esito : 'fatto');
        } catch (Throwable $e) {
            $righe[] = 'ERRORE ' . $x['chiave'] . ' (' . $x['modulo'] . '): ' . $e->getMessage();
        }
    }

    // 1.5.0: le email del mattino del CMS (Social): "Oggi sul sito" con i contenuti che escono oggi, e i testi social
    // del calendario con la data di oggi. Prima le mandava il modulo di danilofailli.it.
    try {
        require_once __DIR__ . '/social.php';
        foreach (social_cron() as $r) $righe[] = $r;
    } catch (Throwable $e) {
        $righe[] = 'ERRORE social: ' . $e->getMessage();
    }

    // 3. IndexNow: la coda degli indirizzi pubblicati o modificati (pannello SEO e AEO).
    try {
        require_once __DIR__ . '/seo.php';
        $righe[] = 'IndexNow: ' . seo_coda_invia(10000, 20);
    } catch (Throwable $e) {
        $righe[] = 'ERRORE IndexNow: ' . $e->getMessage();
    }

    $log = date('Y-m-d H:i:s') . ' | Cron avviato' . ($da === 'pannello' ? ' dal pannello (Esegui adesso)' : '') . "\n";
    foreach ($righe as $r) $log .= "  $r\n";
    $log .= "  Completato.\n\n";
    @file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
    cron_segna('giornaliero', $da, $inizio, $righe);
    return $log;
}

/** Il giro della newsletter. Ritorna le righe di quello che ha fatto (vuoto: niente da fare). Eccezione se la newsletter si ferma. */
function cron_newsletter(string $da = 'cron'): array {
    $inizio = microtime(true);
    require_once __DIR__ . '/newsletter.php';
    $righe = [];
    // Ogni giro svuota anche la coda di IndexNow (pannello SEO e AEO), se c'è qualcosa e la rete risponde.
    try {
        require_once __DIR__ . '/seo.php';
        if (seo_coda_conta() > 0) { $r = seo_coda_invia(10000, 15); @file_put_contents(cms_radice() . '/logs/seo.log', date('Y-m-d H:i:s') . ' | ' . $r . "\n", FILE_APPEND); $righe[] = 'IndexNow: ' . $r; }
    } catch (Throwable $e) {
        @file_put_contents(cms_radice() . '/logs/seo.log', date('Y-m-d H:i:s') . ' | ERRORE ' . $e->getMessage() . "\n", FILE_APPEND);
    }
    // 2.0.0: i passi frequenti dei moduli e degli addon (promemoria delle prenotazioni, email dopo un servizio...).
    foreach (cms_moduli_elenco('cron_spesso') as $x) {
        try {
            $esito = ($x['valore'])();
            if (is_string($esito) && $esito !== '') $righe[] = ucfirst((string)$x['chiave']) . ': ' . $esito;
        } catch (Throwable $e) {
            $righe[] = 'ERRORE ' . $x['chiave'] . ' (' . $x['modulo'] . '): ' . $e->getMessage();
        }
    }
    try {
        // 1.11.0: una volta al giorno, la pulizia (registro della posta oltre 60 giorni, iscritti mai confermati).
        try { $righe = array_merge($righe, newsletter_pulizia()); } catch (Throwable $e) { $righe[] = 'ERRORE pulizia: ' . $e->getMessage(); }
        $log = newsletter_invia_giro();
        if ($log) @file_put_contents(cms_radice() . '/logs/newsletter.log', date('Y-m-d H:i:s') . ' | ' . implode('; ', $log) . "\n", FILE_APPEND);
        $righe = array_merge($righe, $log);
    } catch (Throwable $e) {
        @file_put_contents(cms_radice() . '/logs/newsletter.log', date('Y-m-d H:i:s') . ' | ERRORE ' . $e->getMessage() . "\n", FILE_APPEND);
        cron_segna('newsletter', $da, $inizio, array_merge($righe, ['ERRORE ' . $e->getMessage()]));
        throw $e;
    }
    cron_segna('newsletter', $da, $inizio, $righe ?: ['niente da mandare']);
    return $righe;
}

/** Il cron vero gira? Per ognuno dei due: la passata dal cron (non dal pulsante) e abbastanza recente. */
function cron_attivo(): array {
    $s = cron_stato();
    $g = (int)($s['giornaliero_cron']['quando'] ?? 0);
    $n = (int)($s['newsletter_cron']['quando'] ?? 0);
    return ['giornaliero' => $g > time() - CRON_GIORNO_ORE * 3600, 'newsletter' => $n > time() - CRON_NEWSLETTER_MIN * 60, 'giornaliero_quando' => $g, 'newsletter_quando' => $n];
}

/**
 * Il PHP da riga di comando da scrivere nel cron. Il pannello gira spesso con php-fpm o il modulo di Apache: si cerca
 * il "php" accanto (cPanel: /opt/cpanel/ea-php84/root/usr/bin/php), poi i posti di sempre. Se non si trova, "php".
 */
function cron_php(): string {
    $b = (string)PHP_BINARY;
    $candidati = [];
    if ($b !== '' && preg_match('~/php(\d[\d.]*)?$~', $b)) $candidati[] = $b;
    if ($b !== '') { $candidati[] = dirname($b) . '/php'; $candidati[] = dirname($b, 2) . '/bin/php'; }
    $candidati[] = '/usr/local/bin/php';
    $candidati[] = '/usr/bin/php';
    foreach ($candidati as $c) if (@is_file($c) && @is_executable($c)) return $c;
    return 'php';
}

/** Le due righe del cron, con il percorso vero di questa installazione: [[quando, comando, spiegazione], ...]. */
function cron_righe(): array {
    $php = cron_php();
    $r = cms_radice();
    return [
        ['0 6 * * *', $php . ' ' . $r . '/cron-daily.php > /dev/null 2>&1', 'una volta al giorno, alle 6 del mattino'],
        ['*/5 * * * *', $php . ' ' . $r . '/cron-newsletter.php > /dev/null 2>&1', 'ogni 5 minuti'],
    ];
}
