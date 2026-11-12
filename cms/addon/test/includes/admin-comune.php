<?php
/**
 * I pezzi comuni delle pagine del pannello dell'addon Test e questionari (addon-pagina.php?a=test&p=...).
 * addon-pagina.php ha già controllato l'accesso (solo amministratore) e caricato auth e CSRF.
 */
require_once __DIR__ . '/test.php';
test_schema();

const TEST_PASSI = [1 => 'Presentazione', 2 => 'Domande e aree', 3 => 'Punteggi e fasce', 4 => 'Risultati', 5 => 'Dopo il test'];

function test_admin_url(string $p, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'test', 'p' => $p] + $q);
}

function test_admin_vai(string $p, array $q = [], string $ancora = ''): never {
    header('Location: ' . test_admin_url($p, $q) . ($ancora !== '' ? '#' . $ancora : ''));
    exit;
}

function test_admin_asset(string $f): string {
    $p = dirname(__DIR__) . '/assets/' . $f;
    return cms_base() . '/addon/test/assets/' . $f . '?v=' . (is_file($p) ? filemtime($p) : 0);
}

function test_admin_schede(string $qui): void {
    echo '<link rel="stylesheet" href="' . h(test_admin_asset('admin.css')) . '">';
    echo '<nav class="ct-schede ts-schede" aria-label="Test e questionari">';
    foreach (['test' => 'Test', 'statistiche' => 'Statistiche'] as $k => $n) echo '<a href="' . h(test_admin_url($k)) . '"' . ($k === $qui ? ' class="attivo" aria-current="page"' : '') . '>' . h($n) . '</a>';
    echo '</nav>';
}

function test_stato_badge(string $s): string {
    return $s === 'pubblicato' ? '<span class="badge badge-published">Pubblicato</span>' : '<span class="badge badge-draft">Bozza</span>';
}
