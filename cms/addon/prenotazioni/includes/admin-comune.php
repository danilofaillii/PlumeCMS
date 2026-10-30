<?php
/**
 * I pezzi comuni delle pagine del pannello dell'addon Prenotazioni (addon-pagina.php?a=prenotazioni&p=...).
 * addon-pagina.php ha già controllato l'accesso (solo amministratore) e caricato auth e CSRF.
 */
require_once __DIR__ . '/prenotazioni.php';
pren_schema();

function pren_admin_url(string $p, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'prenotazioni', 'p' => $p] + $q);
}

/** Le schede in cima a ogni pagina, e il foglio di stile del pannello dell'addon. */
function pren_admin_schede(string $qui): void {
    $schede = ['agenda' => 'Agenda', 'elenco' => 'Prenotazioni', 'servizi' => 'Servizi', 'persone' => 'Persone e orari', 'impostazioni' => 'Impostazioni'];
    $f = dirname(__DIR__) . '/assets/admin.css';
    echo '<link rel="stylesheet" href="' . h(cms_base() . '/addon/prenotazioni/assets/admin.css?v=' . (is_file($f) ? filemtime($f) : 0)) . '">';
    echo '<nav class="pa-schede" aria-label="Prenotazioni">';
    foreach ($schede as $k => $n) echo '<a href="' . h(pren_admin_url($k)) . '"' . ($k === $qui ? ' class="attivo" aria-current="page"' : '') . '>' . h($n) . '</a>';
    echo '</nav>';
    if (!pren_servizi() || !pren_risorse()) {
        echo '<div class="pa-avviso"><b>Per cominciare:</b> ' . (!pren_risorse() ? 'aggiungi chi lavora e i suoi orari in <a href="' . h(pren_admin_url('persone')) . '">Persone e orari</a>' : '') . (!pren_risorse() && !pren_servizi() ? ', poi ' : '')
            . (!pren_servizi() ? 'scrivi i servizi che si possono prenotare in <a href="' . h(pren_admin_url('servizi')) . '">Servizi</a>' : '') . '. Poi metti il widget <b>Prenota</b> in una pagina con l\'editor.</div>';
    }
}

function pren_stato_badge(string $s): string {
    $cl = ['da_confermare' => 'badge-scheduled', 'confermata' => 'badge-published', 'svolta' => 'badge-draft', 'non_presentato' => 'badge-pillar', 'annullata' => 'badge-cluster'][$s] ?? 'badge-draft';
    return '<span class="badge ' . $cl . '">' . h(PREN_STATI[$s] ?? $s) . '</span>';
}

/** Le righe delle chiusure (dal, al, motivo) di un modulo del pannello: quelle salvate più tre vuote. */
function pren_admin_chiusure_campi(string $nome, array $chiusure): string {
    $righe = array_values(array_map(fn($c) => ['dal' => $c[0], 'al' => $c[1], 'motivo' => $c[2]], pren_chiusure($chiusure)));
    for ($i = 0; $i < 3; $i++) $righe[] = ['dal' => '', 'al' => '', 'motivo' => ''];
    $out = '<div class="pa-chiusure"><div class="pa-chiusura pa-chiusura-testa" aria-hidden="true"><span>Dal</span><span>Al (vuoto: un giorno)</span><span>Motivo</span></div>';
    foreach ($righe as $i => $r) {
        $out .= '<div class="pa-chiusura"><input type="date" name="' . $nome . '[' . $i . '][dal]" value="' . h($r['dal']) . '" aria-label="Dal"><input type="date" name="' . $nome . '[' . $i . '][al]" value="' . h($r['al'] !== $r['dal'] ? $r['al'] : '') . '" aria-label="Al">'
            . '<input name="' . $nome . '[' . $i . '][motivo]" value="' . h($r['motivo']) . '" maxlength="80" placeholder="Ferie" aria-label="Motivo"></div>';
    }
    return $out . '</div>';
}

/** Le chiusure arrivate da un modulo: solo date vere, al massimo 60. */
function pren_admin_chiusure_leggi(mixed $l): array {
    $out = [];
    foreach (is_array($l) ? $l : [] as $c) {
        if (!is_array($c)) continue;
        $da = (string)($c['dal'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $da)) continue;
        $a = (string)($c['al'] ?? '');
        $out[] = ['dal' => $da, 'al' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $a) && $a >= $da ? $a : $da, 'motivo' => pren_testo($c['motivo'] ?? '', 80)];
        if (count($out) >= 60) break;
    }
    return $out;
}

function pren_admin_vai(string $p, array $q = []): never {
    header('Location: ' . pren_admin_url($p, $q));
    exit;
}
