<?php
/**
 * Test > Statistiche: iniziati e completati, abbandoni per domanda, distribuzione dei risultati, media per area, risposte
 * per domanda, esportazione CSV aggregata (celle protette dalle formule con sicurezza_csv_cella). Tutto anonimo: le
 * tabelle dell'addon non hanno né persone né IP. Solo amministratore.
 */
require_once __DIR__ . '/admin-comune.php';
require_once dirname(__DIR__, 3) . '/includes/sicurezza-html.php';
$adminTitle = 'Statistiche dei test';

$tutti = test_tutti();
$id = (int)($_GET['id'] ?? 0);
$t = $id ? test_leggi($id) : ($tutti ? test_leggi((int)$tutti[0]['id']) : null);
$giorni = in_array((int)($_GET['giorni'] ?? 0), [7, 30, 90, 365, 0], true) ? (int)($_GET['giorni'] ?? 0) : 0;
$s = $t ? test_statistiche($t, $giorni) : null;
$g = $t && $t['dati']['persona']['domande'] ? test_statistiche_gruppi($t) : null; // 2.23.0: per gruppi, sempre su tutti i risultati

/** 2.23.0: il nome di una fascia o di un profilo di un test (le colonne della distribuzione nei gruppi). */
function test_stat_colonne(array $t): array {
    return array_column($t['tipo'] === 'profili' ? $t['dati']['profili'] : $t['dati']['fasce'], 'nome', 'k');
}

/** 2.23.0: un numero di un gruppo come si legge: "meno di 5" o "non mostrato" per i gruppi nascosti. */
function test_stat_n(array $riga): string {
    if (!$riga['nascosto']) return (string)(int)$riga['n'];
    return $riga['perche'] === 'pochi' ? 'meno di ' . TEST_GRUPPO_MINIMO : 'non mostrato';
}

/** 2.23.0: le righe del CSV dei gruppi (sezione, voce, numero): quanti, la distribuzione e le medie, con la stessa soglia. */
function test_stat_righe_gruppi(array $t, array $g): array {
    $r = [];
    $col = test_stat_colonne($t);
    foreach ($g['domande'] as $q) foreach ($q['righe'] as $x) {
        $sez = 'Per gruppi: ' . $q['nome'];
        $voce = $x['t'];
        $r[] = [$sez, $voce . ' - persone', test_stat_n($x)];
        if ($x['nascosto'] || !$x['n']) continue;
        foreach ($col as $k => $n) $r[] = [$sez, $voce . ' - ' . ($t['tipo'] === 'profili' ? 'profilo ' : 'fascia ') . $n . ' (%)', $x['dist'][$k] ?? 0];
        if ($x['media'] !== null) $r[] = [$sez, $voce . ' - media del totale (su 100)', $x['media']];
        foreach ($t['dati']['aree'] as $a) if (isset($x['aree'][$a['k']])) $r[] = [$sez, $voce . ' - media dell\'area ' . $a['nome'] . ' (su 100)', $x['aree'][$a['k']]];
    }
    return $r;
}

/** Le righe aggregate (sezione, voce, numero) che vanno nel CSV e nelle tabelle. */
function test_stat_righe(array $t, array $s): array {
    $d = $t['dati'];
    $r = [['Riepilogo', 'Iniziati', $s['iniziati']], ['Riepilogo', 'Completati', $s['completati']],
        ['Riepilogo', 'Completati su iniziati (%)', $s['iniziati'] ? round($s['completati'] / $s['iniziati'] * 100) : 0]];
    if ($s['media_totale'] !== null) $r[] = ['Riepilogo', 'Media del totale (su 100)', $s['media_totale']];
    foreach ($d['domande'] as $i => $q) $r[] = ['Abbandoni', 'Domanda ' . ($i + 1) . ': ' . $q['testo'], $s['abbandoni'][$q['k']] ?? 0];
    if (isset($s['abbandoni'][''])) $r[] = ['Abbandoni', 'Prima della prima domanda', $s['abbandoni']['']];
    foreach ($d['fasce'] as $f) $r[] = ['Risultati', 'Totale: ' . $f['nome'], $s['distribuzione']['fascia'][$f['k']] ?? 0];
    foreach ($d['profili'] as $p) $r[] = ['Risultati', 'Profilo: ' . $p['nome'], $s['distribuzione']['profilo'][$p['k']] ?? 0];
    foreach ($d['regole'] as $g) $r[] = ['Risultati', 'Regola: ' . $g['nome'], $s['distribuzione']['regola'][$g['k']] ?? 0];
    foreach ($d['aree'] as $a) $r[] = ['Media per area (su 100)', $a['nome'], $s['medie'][$a['k']] ?? ''];
    foreach ($d['profili'] as $p) $r[] = ['Media dei punti per profilo', $p['nome'], $s['medie'][$p['k']] ?? ''];
    foreach ($d['domande'] as $i => $q) foreach (test_opzioni($q, $d) as $o) {
        $n = $s['risposte'][$q['k']][$o['k']] ?? 0;
        if ($q['tipo'] === 'cursore' && !$n) continue;
        $r[] = ['Risposte', 'Domanda ' . ($i + 1) . ': ' . $q['testo'] . ' - ' . $o['t'], $n];
    }
    return $r;
}

if ($t && isset($_GET['csv'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="test-' . $t['slug'] . '-statistiche.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Sezione', 'Voce', 'Numero'], ';', '"', '');
    foreach (test_stat_righe($t, $s) as $riga) fputcsv($out, sicurezza_csv_riga($riga), ';', '"', '');
    if ($g) foreach (test_stat_righe_gruppi($t, $g) as $riga) fputcsv($out, sicurezza_csv_riga($riga), ';', '"', ''); // 2.23.0
    fclose($out);
    exit;
}

require CMS_ADMIN . '/includes/admin-header.php';
test_admin_schede('statistiche');
if (!$t): ?>
<div class="empty-state"><p>Ancora nessun test: le statistiche compaiono quando qualcuno fa un test pubblicato.</p><a class="btn btn-primary" href="<?= h(test_admin_url('test', ['nuovo' => 1])) ?>">+ Nuovo test</a></div>
<?php else:
    $d = $t['dati'];
    $tasso = $s['iniziati'] ? round($s['completati'] / $s['iniziati'] * 100) : 0; ?>
<form method="get" class="ts-filtri" action="addon-pagina.php"><input type="hidden" name="a" value="test"><input type="hidden" name="p" value="statistiche">
  <div class="form-group"><label for="ts-quale">Test</label><select id="ts-quale" name="id" data-invia-al-cambio><?php foreach ($tutti as $x): ?><option value="<?= (int)$x['id'] ?>"<?= (int)$x['id'] === $t['id'] ? ' selected' : '' ?>><?= h($x['nome']) ?><?= $x['stato'] !== 'pubblicato' ? ' (bozza)' : '' ?></option><?php endforeach; ?></select></div>
  <div class="form-group"><label for="ts-giorni">Periodo</label><select id="ts-giorni" name="giorni" data-invia-al-cambio><?php foreach ([0 => 'Da sempre', 7 => 'Ultimi 7 giorni', 30 => 'Ultimi 30 giorni', 90 => 'Ultimi 90 giorni', 365 => 'Ultimo anno'] as $k => $n): ?><option value="<?= $k ?>"<?= $giorni === $k ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></div>
  <noscript><button class="btn btn-secondary btn-sm">Mostra</button></noscript>
  <a class="btn btn-secondary" href="<?= h(test_admin_url('statistiche', ['id' => $t['id'], 'giorni' => $giorni, 'csv' => 1])) ?>">Scarica il CSV</a>
</form>
<p class="hint">Tutto anonimo: numeri aggregati, senza nomi né indirizzi IP. «Iniziati» conta chi ha premuto «Inizia il test» (o ha mandato il test senza JavaScript). Le risposte per domanda contano da sempre.</p>
<div class="ts-numeri">
  <div class="ts-numero"><b><?= (int)$s['iniziati'] ?></b><span>iniziati</span></div>
  <div class="ts-numero"><b><?= (int)$s['completati'] ?></b><span>completati</span></div>
  <div class="ts-numero"><b><?= $tasso ?>%</b><span>arrivati alla fine</span></div>
  <?php if ($s['media_totale'] !== null): ?><div class="ts-numero"><b><?= h(str_replace('.', ',', (string)$s['media_totale'])) ?></b><span>media del totale, su 100</span></div><?php endif; ?>
</div>
<?php if (!$s['iniziati']): ?><div class="empty-state"><p>Ancora nessuno ha fatto questo test<?= $giorni ? ' in questo periodo' : '' ?>. <?= $t['stato'] !== 'pubblicato' ? 'Il test è in bozza: pubblicalo dal passo 5.' : 'Mettilo in una pagina con il widget Riquadro in evidenza, oppure manda il link: ' . h(cms_url_sito() . test_url($t)) ?></p></div><?php endif; ?>
<div class="sc-griglia ts-griglia">
<div class="sc-scatola ts-scatola"><h3>Risultati</h3><div class="sc-corpo">
  <?php $dist = []; foreach ($d['fasce'] as $f) $dist[] = [$f['nome'], $s['distribuzione']['fascia'][$f['k']] ?? 0]; foreach ($d['profili'] as $p) $dist[] = [$p['nome'], $s['distribuzione']['profilo'][$p['k']] ?? 0];
  $max = max(1, ...array_map(fn($x) => $x[1], $dist ?: [['', 0]])); ?>
  <ul class="ts-barre"><?php foreach ($dist as [$n, $v]): ?><li><span class="ts-barre-nome"><?= h($n) ?></span><span class="ts-barre-barra" aria-hidden="true"><span style="width:<?= round($v / $max * 100) ?>%"></span></span><b><?= (int)$v ?></b></li><?php endforeach; ?></ul>
  <?php if ($d['regole']): ?><h4 class="fo-sotto">Regole (vincono sulle fasce)</h4><ul class="ts-barre"><?php foreach ($d['regole'] as $g): $v = $s['distribuzione']['regola'][$g['k']] ?? 0; ?><li><span class="ts-barre-nome"><?= h($g['nome']) ?></span><span class="ts-barre-barra" aria-hidden="true"><span style="width:<?= round($v / max(1, $s['completati']) * 100) ?>%"></span></span><b><?= (int)$v ?></b></li><?php endforeach; ?></ul><?php endif; ?>
</div></div>
<?php if ($d['aree'] || $d['profili']): ?>
<div class="sc-scatola ts-scatola"><h3><?= $d['aree'] ? 'Media per area' : 'Media dei punti per profilo' ?></h3><div class="sc-corpo">
  <ul class="ts-barre"><?php foreach ($d['aree'] ?: $d['profili'] as $a): $v = $s['medie'][$a['k']] ?? null; $mx = $d['aree'] ? 100 : max(1, ...array_values($s['medie'] ?: [1])); ?><li><span class="ts-barre-nome"><?= h($a['nome']) ?></span><span class="ts-barre-barra" aria-hidden="true"><span style="width:<?= $v === null ? 0 : round($v / $mx * 100) ?>%"></span></span><b><?= $v === null ? '-' : h(str_replace('.', ',', (string)$v)) ?></b></li><?php endforeach; ?></ul>
</div></div>
<?php endif; ?>
</div>
<div class="sc-scatola ts-scatola"><h3>Domanda per domanda</h3><div class="sc-corpo">
  <p class="hint" style="margin-top:0">«Si sono fermati qui»: chi ha visto questa domanda come ultima e non ha finito il test.</p>
  <div class="admin-table-wrap"><table class="ts-tabella-dom">
    <thead><tr><th scope="col">Domanda</th><th scope="col">Si sono fermati qui</th><th scope="col">Risposte date</th></tr></thead>
    <tbody>
    <?php foreach ($d['domande'] as $i => $q): $rs = $s['risposte'][$q['k']] ?? []; ?>
      <tr><td><b><?= $i + 1 ?>.</b> <?= h($q['testo']) ?></td><td><?= (int)($s['abbandoni'][$q['k']] ?? 0) ?></td>
        <td><?php if (!$rs): ?><span class="ct-muto">nessuna</span><?php else: $nomi = array_column(test_opzioni($q, $d), 't', 'k'); arsort($rs); echo h(implode(', ', array_map(fn($k, $n) => ($nomi[$k] ?? $k) . ' ' . $n, array_keys($rs), $rs))); endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div></div>
<?php if ($g): $col = test_stat_colonne($t); /* 2.23.0 */ ?>
<div class="sc-scatola ts-scatola" id="ts-gruppi"><h3>Per gruppi</h3><div class="sc-corpo">
  <p class="hint" style="margin-top:0">Le risposte alle domande sulla persona, su tutti i <?= (int)$g['totale'] ?> risultati del test (non solo sul periodo scelto). Un gruppo con meno di <?= TEST_GRUPPO_MINIMO ?> persone non mostra i suoi numeri, per non riconoscere nessuno; «non mostrato» vuol dire che si nasconde anche quel gruppo, altrimenti per differenza dal totale si saprebbero i numeri dei gruppi piccoli. Le percentuali sono sul numero di persone del gruppo.</p>
  <?php foreach ($g['domande'] as $q): ?>
  <h4 class="fo-sotto"><?= h($q['nome']) ?> <span class="ct-muto">(<?= h($q['testo']) ?>)</span></h4>
  <div class="admin-table-wrap"><table class="ts-tabella-gruppi" data-no-schede>
    <thead><tr><th scope="col">Risposta</th><th scope="col">Persone</th><?php foreach ($col as $cn): ?><th scope="col"><?= h($cn) ?></th><?php endforeach; ?><?php if ($t['tipo'] !== 'profili'): ?><th scope="col">Media del totale</th><?php endif; ?><?php foreach ($t['dati']['aree'] as $a): ?><th scope="col">Media <?= h($a['nome']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php $zero = []; foreach ($q['righe'] as $x): if (!$x['nascosto'] && !$x['n']) { $zero[] = $x['t']; continue; } $vuota = $x['nascosto']; ?>
      <tr<?= $x['nascosto'] ? ' class="ts-nascosto"' : '' ?>><td><?= h($x['t']) ?></td><td><?= h($x['nascosto'] ? test_stat_n($x) : (string)(int)$x['n']) ?></td>
        <?php foreach ($col as $ck => $cn): ?><td><?= $vuota ? '<span class="ct-muto">-</span>' : (int)($x['dist'][$ck] ?? 0) . '%' ?></td><?php endforeach; ?>
        <?php if ($t['tipo'] !== 'profili'): ?><td><?= $vuota || $x['media'] === null ? '<span class="ct-muto">-</span>' : h(str_replace('.', ',', (string)$x['media'])) ?></td><?php endif; ?>
        <?php foreach ($t['dati']['aree'] as $a): ?><td><?= $vuota || !isset($x['aree'][$a['k']]) ? '<span class="ct-muto">-</span>' : h(str_replace('.', ',', (string)$x['aree'][$a['k']])) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    <?php if (count($zero) === count($q['righe'])): ?><tr><td colspan="<?= 2 + count($col) + ($t['tipo'] !== 'profili' ? 1 : 0) + count($t['dati']['aree']) ?>">Ancora nessuna risposta a questa domanda.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php if ($zero && count($zero) < count($q['righe'])): ?><p class="hint">Nessuno ha risposto: <?= h(implode(', ', $zero)) ?>.</p><?php endif; ?>
  <?php endforeach; ?>
</div></div>
<?php endif; ?>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
