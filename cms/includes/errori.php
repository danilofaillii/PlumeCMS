<?php
/**
 * Errori non gestiti (2.25.1). Il 4 novembre 2026 un sito vero ha mostrato a chiunque aprisse il pannello
 * "Fatal error: Uncaught PDOException ... in /web/htdocs/www.<sito>/home/includes/sicurezza.php:121" con la traccia e i
 * percorsi del server: il pannello teneva display_errors acceso (admin/includes/auth.php) e nel nucleo non c'era nessun
 * gestore delle eccezioni. Ora:
 *   - un gestore delle eccezioni (set_exception_handler) e uno di chiusura (register_shutdown_function con error_get_last,
 *     per gli errori fatali che non sono eccezioni) scrivono tutto in logs/php-errori.log e rispondono con una pagina sobria;
 *   - nel pannello la pagina dice la classe dell'errore, il codice (SQLSTATE), il messaggio e file:riga RELATIVI alla cartella
 *     del CMS, mai un percorso assoluto, ma SOLO a chi ha una sessione del pannello (errori_dettaglio_visibile()): a chi non è
 *     entrato (il login, una pagina aperta da fuori) esce il testo generico con l'ora della riga da cercare nel log; le pagine
 *     -api.php ricevono lo stesso in JSON;
 *   - nel sito pubblico (e nelle API, nei cron) esce il 503 pulito "Torno subito", senza dettagli;
 *   - con define('CMS_DEBUG', true) nel config.local.php tutto resta come prima (errori a video nel pannello, nessun gestore).
 * Gli avvisi (warning, deprecazioni) vanno nel log come prima (set_error_handler di config.php e auth.php).
 */

/** Accende i gestori per il contesto dato: 'pannello', oppure 'sito' (sito pubblico, API, cron). */
function errori_attiva(string $contesto): void {
    // Nelle tracce (nel log, e in quelle che PHP scrive da sé) mai gli argomenti delle funzioni: un'eccezione dentro
    // login_esteso('utente', 'password') metterebbe la password in logs/php-errori.log. Vale anche con CMS_DEBUG.
    @ini_set('zend.exception_ignore_args', '1');
    if (defined('CMS_DEBUG') && CMS_DEBUG) return;
    $GLOBALS['errori_contesto'] = $contesto;
    set_exception_handler('errori_eccezione');
    register_shutdown_function('errori_chiusura');
}

/** La radice del CMS (per togliere i percorsi assoluti), anche quando cms.php non è ancora caricato. */
function errori_radice(): string {
    return function_exists('cms_radice') ? cms_radice() : dirname(__DIR__);
}

/** Un percorso senza la parte assoluta del server: "includes/sicurezza.php". */
function errori_relativo(string $percorso): string {
    $r = str_replace('\\', '/', errori_radice());
    $p = str_replace('\\', '/', $percorso);
    if ($r !== '' && str_starts_with($p, $r . '/')) return substr($p, strlen($r) + 1);
    $doc = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    if ($doc !== '' && str_starts_with($p, $doc . '/')) return substr($p, strlen($doc) + 1);
    return basename($p);
}

/** Un testo senza i percorsi assoluti del server (la radice del CMS e la radice del sito web). */
function errori_senza_percorsi(string $testo): string {
    $radici = array_filter(array_unique([str_replace('\\', '/', errori_radice()), rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/')]));
    foreach ($radici as $r) if ($r !== '' && $r !== '/') $testo = str_replace([$r . '/', $r], ['', '<cms>'], $testo);
    return $testo;
}

/** Il gestore delle eccezioni non catturate. */
function errori_eccezione(Throwable $e): void {
    try {
        error_log('Eccezione non gestita (' . ($GLOBALS['errori_contesto'] ?? 'sito') . '): ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        $codice = $e instanceof PDOException ? (string)$e->getCode() : (string)$e->getCode();
        errori_rispondi(get_class($e), $codice, $e->getMessage(), errori_relativo($e->getFile()) . ':' . $e->getLine(), $e instanceof PDOException);
    } catch (Throwable $e2) {
        error_log('errori_eccezione: ' . $e2->getMessage());
    }
}

/** Il gestore di chiusura: un errore fatale di PHP (non un'eccezione) diventa la stessa pagina sobria. PHP lo ha già scritto nel log. */
function errori_chiusura(): void {
    $u = error_get_last();
    if (!$u || !in_array((int)$u['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) return;
    try {
        $msg = (string)$u['message'];
        // "Uncaught PDOException: SQLSTATE[42S02]: ... in /percorso:riga Stack trace: ..." diventa la sola prima riga.
        $msg = preg_replace('/\s+Stack trace:.*$/s', '', $msg);
        $msg = preg_replace('/\s+in \S+:\d+$/', '', $msg);
        $classe = preg_match('/^Uncaught ([A-Za-z0-9_\\\\]+):/', $msg, $m) ? $m[1] : 'Errore fatale di PHP';
        $msg = preg_replace('/^Uncaught [A-Za-z0-9_\\\\]+:\s*/', '', $msg);
        errori_rispondi($classe, '', $msg, errori_relativo((string)$u['file']) . ':' . (int)$u['line'], str_contains($msg, 'SQLSTATE'));
    } catch (Throwable $e2) {
        error_log('errori_chiusura: ' . $e2->getMessage());
    }
}

/** Il dettaglio dell'errore (messaggio, file:riga) si mostra solo a chi è entrato nel pannello: a un visitatore dice troppo (tabelle, database). */
function errori_dettaglio_visibile(): bool {
    return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin_user_id']);
}

/** La risposta: JSON per le API del pannello, il 503 pulito per il sito, la pagina sobria per il pannello. */
function errori_rispondi(string $classe, string $codice, string $messaggio, string $dove, bool $database): void {
    $contesto = (string)($GLOBALS['errori_contesto'] ?? 'sito');
    $messaggio = errori_senza_percorsi($messaggio);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Errore: ' . $classe . ($codice !== '' && $codice !== '0' ? ' [' . $codice . ']' : '') . ': ' . $messaggio . ' (' . $dove . ")\n");
        return;
    }
    // Via quello che la pagina aveva già scritto a metà; il buffer del pannello (sicurezza_pannello_fine) resta per le intestazioni.
    while (ob_get_level() > 1) @ob_end_clean();
    if (ob_get_level()) @ob_clean();
    $inviate = headers_sent();
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $json = str_ends_with($script, '-api.php') || str_contains($script, '/api/') || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json');
    $stato = $database ? 503 : 500;
    if (!$inviate) { http_response_code($stato); header('Cache-Control: no-store'); }
    if ($contesto === 'pannello') {
        $visibile = errori_dettaglio_visibile();
        $dettaglio = $visibile ? $classe . ($codice !== '' && $codice !== '0' ? ' [' . $codice . ']' : '') . ': ' . $messaggio . ' (' . $dove . ')'
            : 'Errore delle ' . date('H:i:s') . ' del ' . date('d/m/Y') . ': il dettaglio è nel log, a quell\'ora (si mostra qui solo a chi è entrato nel pannello).';
        if ($json) {
            if (!$inviate) header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['errore' => 'Il pannello ha incontrato un errore e si è fermato. Il dettaglio è nel log degli errori del sito (logs/php-errori.log).', 'dettaglio' => $dettaglio], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if (!$inviate) header('Content-Type: text/html; charset=UTF-8');
        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $css = '';
        if (function_exists('cms_base')) $css = '<link rel="stylesheet" href="' . $h(cms_base() . '/admin/assets/css/aspetti.css') . '">';
        echo '<!DOCTYPE html><html lang="it" data-pannello="aria"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Qualcosa non ha funzionato</title>' . $css
            . '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:var(--font,system-ui,sans-serif);background:var(--bg,#f4f4f2);color:var(--text,#1d1d1b);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.2rem;padding:1.5rem 1rem}'
            . '.marchio{font-size:1.05rem;font-weight:700}.marchio strong{color:var(--coral,#e8553e)}.box{background:var(--surface,#fff);border:1px solid var(--border,#ddd);border-radius:16px;padding:2rem 2.2rem;width:100%;max-width:640px;box-shadow:var(--ombra-grande,0 10px 30px rgba(0,0,0,.08))}'
            . 'h1{font-size:1.3rem;margin-bottom:.6rem}p{font-size:.95rem;line-height:1.5;margin-bottom:.8rem}.dett{font-family:monospace;font-size:.82rem;background:var(--codice-fondo,#1f2430);color:var(--codice-testo,#e6ebf5);padding:.8rem 1rem;border-radius:8px;white-space:pre-wrap;word-break:break-word}'
            . 'a{color:var(--link,#1f4fbf)}.sotto{font-size:.85rem;color:var(--text-muted,#666);margin-top:1rem}</style></head><body>'
            . '<div class="marchio" aria-hidden="true"><strong>plume</strong>CMS</div><div class="box" role="alert"><h1>Qualcosa non ha funzionato</h1>'
            . '<p>Il pannello ha incontrato un errore e si è fermato, prima di fare danni. Riprova fra un momento; se succede ancora, manda a chi segue il sito il dettaglio qui sotto (lo stesso è nel log degli errori del sito, <code>logs/php-errori.log</code>).</p>'
            . '<div class="dett">' . $h($dettaglio) . '</div>'
            . '<p class="sotto"><a href="index.php">Torna alla dashboard</a></p></div></body></html>';
        return;
    }
    // Sito pubblico, API, cron via web: il 503 pulito (il dettaglio è solo nel log).
    if ($json) {
        if (!$inviate) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['errore' => 'Il sito si è fermato un momento. Riprova tra qualche minuto.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (!$inviate) { header('Content-Type: text/html; charset=UTF-8'); if ($database) header('Retry-After: 600'); }
    echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><title>Torno subito</title><body style="font-family:system-ui;background:#060a17;color:#e6ebf5;padding:15vh 24px;text-align:center"><h1>Torno subito.</h1><p>Il sito si è fermato un momento. Riprova tra qualche minuto.</p></body></html>';
}
