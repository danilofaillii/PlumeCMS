<?php
/**
 * I pezzi comuni delle pagine del pannello dell'addon Eventi con iscrizione (addon-pagina.php?a=eventi-iscrizione&p=...).
 * addon-pagina.php ha già controllato l'accesso (solo amministratore) e caricato auth e CSRF.
 */
require_once __DIR__ . '/eventi.php';
evi_schema();

function evi_admin_url(string $p, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'eventi-iscrizione', 'p' => $p] + $q);
}

function evi_admin_vai(string $p, array $q = []): never {
    header('Location: ' . evi_admin_url($p, $q));
    exit;
}

function evi_admin_schede(string $qui): void {
    $f = dirname(__DIR__) . '/assets/admin.css';
    echo '<link rel="stylesheet" href="' . h(cms_base() . '/addon/eventi-iscrizione/assets/admin.css?v=' . (is_file($f) ? filemtime($f) : 0)) . '">';
    echo '<nav class="pa-schede" aria-label="Eventi">';
    foreach (['eventi' => 'Eventi', 'iscritti' => 'Iscritti', 'impostazioni' => 'Impostazioni'] as $k => $n) echo '<a href="' . h(evi_admin_url($k)) . '"' . ($k === $qui ? ' class="attivo" aria-current="page"' : '') . '>' . h($n) . '</a>';
    echo '</nav>';
}

/** Un AAAA-MM-GGTHH:MM di <input type="datetime-local"> nel fuso del sito: i secondi veri, oppure 0. */
function evi_admin_ts(mixed $v): int {
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v)) return 0;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $v, new DateTimeZone(date_default_timezone_get()));
    return $d ? $d->getTimestamp() : 0;
}

function evi_admin_locale(int $ts): string {
    return $ts > 0 ? (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d\TH:i') : '';
}

function evi_stato_badge(string $s): string {
    $cl = ['confermata' => 'badge-published', 'attesa' => 'badge-scheduled', 'cancellata' => 'badge-cluster', 'pubblicato' => 'badge-published', 'bozza' => 'badge-draft', 'annullato' => 'badge-pillar'][$s] ?? 'badge-draft';
    $n = EVI_STATI[$s] ?? ['pubblicato' => 'Pubblicato', 'bozza' => 'Bozza', 'annullato' => 'Annullato'][$s] ?? $s;
    return '<span class="badge ' . $cl . '">' . h($n) . '</span>';
}
