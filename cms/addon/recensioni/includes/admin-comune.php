<?php
/**
 * I pezzi comuni delle pagine del pannello dell'addon Raccolta recensioni (addon-pagina.php?a=recensioni&p=...).
 * addon-pagina.php ha già controllato l'accesso (solo amministratore) e caricato auth e CSRF.
 */
require_once __DIR__ . '/recensioni.php';
rec_schema();

function rec_admin_url(string $p, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'recensioni', 'p' => $p] + $q);
}

function rec_admin_vai(string $p, array $q = []): never {
    header('Location: ' . rec_admin_url($p, $q));
    exit;
}

function rec_admin_schede(string $qui): void {
    $f = dirname(__DIR__) . '/assets/admin.css';
    echo '<link rel="stylesheet" href="' . h(cms_base() . '/addon/recensioni/assets/admin.css?v=' . (is_file($f) ? filemtime($f) : 0)) . '">';
    $n = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('rec_recensioni') . " WHERE stato = 'da_approvare'")->fetchColumn();
    echo '<nav class="pa-schede" aria-label="Recensioni">';
    foreach (['recensioni' => 'Recensioni' . ($n ? " ($n da approvare)" : ''), 'inviti' => 'Inviti', 'impostazioni' => 'Impostazioni'] as $k => $t) echo '<a href="' . h(rec_admin_url($k)) . '"' . ($k === $qui ? ' class="attivo" aria-current="page"' : '') . '>' . h($t) . '</a>';
    echo '</nav>';
}

/** Paragrafi da un testo semplice (righe vuote = paragrafo nuovo), con l'escape. */
function rec_paragrafi(string $s): string {
    return implode('', array_map(fn($p) => '<p>' . nl2br(h(trim($p)), false) . '</p>', preg_split('/\n\s*\n/', trim($s)) ?: []));
}

function rec_stelle_testo(int $n): string {
    return '<span class="pa-stelle" role="img" aria-label="' . $n . ' su 5">' . str_repeat('&#9733;', $n) . '<span style="opacity:.25">' . str_repeat('&#9733;', 5 - $n) . '</span></span>';
}
