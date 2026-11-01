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

    // 4. UX 2026-11 (punto 2 deciso da Danilo): le bozze vuote lasciate da "Aggiungi articolo" o "Aggiungi pagina" aperti e
    // chiusi senza scrivere niente vanno nel cestino dopo un giorno (dal cestino si ripristinano). Mai una bozza con dentro qualcosa.
    try {
        $n = cron_bozze_vuote();
        $righe[] = $n ? "Bozze vuote mai salvate messe nel cestino: $n" : 'Nessuna bozza vuota da mettere nel cestino';
    } catch (Throwable $e) {
        $righe[] = 'ERRORE bozze vuote: ' . $e->getMessage();
    }

    $log = date('Y-m-d H:i:s') . ' | Cron avviato' . ($da === 'pannello' ? ' dal pannello (Esegui adesso)' : '') . "\n";
    foreach ($righe as $r) $log .= "  $r\n";
    $log .= "  Completato.\n\n";
    @file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
    cron_segna('giornaliero', $da, $inizio, $righe);
    return $log;
}

/**
 * UX 2026-11: le bozze vuote mai salvate, piu vecchie di $ore, nel cestino. Ritorna quante.
 * "Vuota e mai salvata" vuol dire tutte queste cose insieme (basta che una manchi e la bozza resta dov'e):
 *  - bozza (status draft), non gia nel cestino, nata da piu di $ore ore;
 *  - titolo ancora quello che le dà il pannello ("Nuovo articolo", "Nuova pagina") e indirizzo nuovo-articolo, nuovo-articolo-2...;
 *  - nessun testo nel campo del contenuto e nessuna struttura pubblicata;
 *  - nella scheda nessun salvataggio dopo la nascita (updated_at entro cinque minuti da created_at);
 *  - nell'editor nessuna versione oltre a quella "creato dall'editor".
 * Solo articoli e pagine del nucleo. Le funzioni che usa ci sono dalla 2.1 (contenuti.php, editor.php): niente di nuovo da altri file.
 */
function cron_bozze_vuote(int $ore = 24): int {
    require_once __DIR__ . '/contenuti.php';
    $tot = 0;
    foreach (['articoli', 'pagine'] as $tipo) {
        $tipi = contenuti_tipi();
        if (!isset($tipi[$tipo])) continue;
        $def = $tipi[$tipo];
        $nome = (string)($def['nuovo'] ?? (((($def['genere'] ?? (str_ends_with((string)$def['uno'], 'a') ? 'f' : 'm')) === 'f') ? 'Nuova ' : 'Nuovo ') . $def['uno']));
        $base = contenuti_slug($nome);
        $campo = (string)($def['campo_contenuto'] ?? 'content');
        if (!preg_match('/^[a-z_]+$/', $campo)) continue;
        $tab = tbl($def['tabella']);
        $st = db()->prepare("SELECT id, slug, `$campo` AS testo, struttura FROM $tab WHERE status = 'draft' AND cestino IS NULL AND title = ?
            AND created_at < (NOW() - INTERVAL " . max(1, $ore) . " HOUR) AND (updated_at IS NULL OR updated_at <= created_at + INTERVAL 5 MINUTE)");
        $st->execute([$nome]);
        $ambito = 'contenuto:' . $tipo;
        $versioni = db()->prepare('SELECT COUNT(*) FROM ' . tbl('editor_versioni') . " WHERE ambito = ? AND oggetto_id = ? AND nota <> 'creato dall''editor'");
        $ids = [];
        foreach ($st->fetchAll() as $r) {
            if (!preg_match('/^' . preg_quote($base, '/') . '(-\d+)?$/', (string)$r['slug'])) continue;
            if (trim(strip_tags((string)$r['testo'])) !== '' || trim((string)$r['struttura']) !== '') continue;
            try { $versioni->execute([$ambito, (int)$r['id']]); if ((int)$versioni->fetchColumn() > 0) continue; } catch (Throwable $e) { continue; }
            $ids[] = (int)$r['id'];
        }
        if ($ids) $tot += contenuti_cestina($tipo, $ids);
    }
    return $tot;
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
        // 2.0.0: una volta al giorno, la pulizia (registro della posta oltre 60 giorni, iscritti mai confermati).
        try { $righe = array_merge($righe, newsletter_pulizia()); } catch (Throwable $e) { $righe[] = 'ERRORE pulizia: ' . $e->getMessage(); }
        // 2.9.0: importazioni ed esportazioni dei contatti a giri (con la copia di riserva per opcache: il file si carica qui).
        try {
            if (!function_exists('newsletter_lavori_giro') && is_file(__DIR__ . '/newsletter-importa.php')) require_once __DIR__ . '/newsletter-importa.php';
            if (function_exists('newsletter_lavori_giro')) foreach (newsletter_lavori_giro(15) as $x) $righe[] = 'Contatti: ' . $x;
        } catch (Throwable $e) { $righe[] = 'ERRORE importazioni: ' . $e->getMessage(); }
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
