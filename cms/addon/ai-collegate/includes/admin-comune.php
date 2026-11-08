<?php
/**
 * Addon AI collegate: i pezzi comuni delle pagine del pannello (addon-pagina.php?a=ai-collegate&p=...).
 * addon-pagina.php ha già controllato l'accesso (solo amministratore) e caricato auth e CSRF.
 */
require_once __DIR__ . '/ai.php';
aic_schema();

function aic_admin_url(string $p, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'ai-collegate', 'p' => $p] + $q);
}

function aic_admin_vai(string $p, array $q = [], string $ancora = ''): never {
    header('Location: ' . aic_admin_url($p, $q) . ($ancora !== '' ? '#' . $ancora : ''));
    exit;
}

/** Il foglio dell'addon e la fila delle schede. */
function aic_admin_schede(string $qui): void {
    $f = dirname(__DIR__) . '/assets/admin.css';
    echo '<link rel="stylesheet" href="' . h(cms_base() . '/addon/ai-collegate/assets/admin.css?v=' . (is_file($f) ? filemtime($f) : 0)) . '">';
    $n = aic_proposte_in_attesa();
    echo '<nav class="imp-schede" aria-label="AI collegate">';
    foreach (['collegate' => 'Collegamenti', 'proposte' => 'Proposte' . ($n ? " ($n da decidere)" : ''), 'registro' => 'Registro', 'impostazioni' => 'Impostazioni'] as $k => $t)
        echo '<a href="' . h(aic_admin_url($k)) . '"' . ($k === $qui ? ' class="attiva" aria-current="page"' : '') . '>' . h($t) . '</a>';
    echo '</nav>';
}

/** I permessi come elenco leggibile. */
function aic_permessi_testo(string $scope): string {
    $p = aic_scope_pulito($scope);
    return $p ? implode(', ', $p) : 'nessuno';
}

function aic_data(?string $d): string {
    return $d ? date('d/m/Y H:i', strtotime($d)) : 'mai';
}

/** Le caselle dei permessi; $scelti spuntati, $soli (se non vuoto) gli unici mostrati. */
function aic_caselle_permessi(string $nome, array $scelti, array $soli = []): string {
    $stat = (bool)cms_modulo('statistiche');
    $out = '<fieldset class="aic-permessi"><legend>Permessi</legend>';
    foreach (AIC_PERMESSI as $k => $t) {
        if ($soli && !in_array($k, $soli, true)) continue;
        $spento = $k === 'statistiche' && !$stat;
        $out .= '<label class="aic-spunta"><input type="checkbox" name="' . h($nome) . '[]" value="' . h($k) . '"' . (in_array($k, $scelti, true) && !$spento ? ' checked' : '') . ($spento ? ' disabled' : '') . '> <span><b>' . h($k) . '</b>: ' . h($t) . ($spento ? ' (accendi prima l\'addon Statistiche)' : '') . '</span></label>';
    }
    return $out . '</fieldset>';
}
