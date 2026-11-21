<?php
/**
 * Statistiche della newsletter. 2.14.0 (fase 7 del piano Brevo, includes/newsletter-report.php): due schede.
 *   Campagne: il periodo scelto (ultimi 7, 30, 90 giorni, 12 mesi, da sempre, o dal giorno al giorno), le campagne partite
 *             nel periodo con i loro numeri, i totali e i tassi sulle consegnate, l'andamento (grafico SVG fatto dal server,
 *             con la tabella dei numeri), il confronto fra fino a quattro campagne, le soglie, i flussi e i motivi delle uscite.
 *   Liste:    la crescita di una lista (o di tutti i contatti) nel periodo: nuovi, usciti, iscritti alla fine di ogni passo,
 *             e i numeri delle campagne mandate a quella lista.
 * ?campagna=N (gli indirizzi di prima della 2.14.0) porta al report della campagna. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/newsletter.php';
require_once dirname(__DIR__) . '/includes/newsletter-campagne.php'; // 2.11.0: i motivi della disiscrizione
require_once dirname(__DIR__) . '/includes/newsletter-report.php'; // 2.14.0
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_campagne_schema();
nl_rep_schema();
if (isset($_GET['campagna'])) { header('Cache-Control: no-store'); header('Location: newsletter-report.php?id=' . (is_string($_GET['campagna']) && ctype_digit($_GET['campagna']) ? (int)$_GET['campagna'] : 0), true, 302); exit; }

$scheda = ($_GET['scheda'] ?? '') === 'liste' ? 'liste' : 'campagne';
[$da, $a, $periodo, $errPeriodo] = nl_rep_periodo($_GET);
$liste = newsletter_liste();
$listaId = is_string($_GET['lista'] ?? null) && ctype_digit($_GET['lista']) ? (int)$_GET['lista'] : (isset($_GET['lista']) ? -1 : 0);
if ($listaId !== 0 && !in_array($listaId, array_map('intval', array_column($liste, 'id')), true)) { $listaId = 0; $errPeriodo .= ($errPeriodo ? ' ' : '') . 'La lista scelta non esiste: ti mostro tutti i contatti.'; }
$campagne = nl_rep_campagne_periodo($da, $a, $scheda === 'liste' ? $listaId : 0);
$tot = nl_rep_somma($campagne);
$adminTitle = 'Statistiche della newsletter';
$qsPeriodo = 'periodo=' . rawurlencode($periodo) . ($periodo === 'scelto' ? '&da=' . $da . '&a=' . $a : '');
$numeri = newsletter_numeri();
require_once __DIR__ . '/includes/admin-header.php';
$data = fn(string $d) => date('d/m/Y', strtotime($d));
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/report.css') ?>">
<nav class="imp-schede" aria-label="Statistiche">
  <a href="newsletter-statistiche.php?<?= h($qsPeriodo) ?>"<?= $scheda === 'campagne' ? ' class="attiva" aria-current="page"' : '' ?>>Campagne</a>
  <a href="newsletter-statistiche.php?scheda=liste&amp;<?= h($qsPeriodo) ?>"<?= $scheda === 'liste' ? ' class="attiva" aria-current="page"' : '' ?>>Liste e contatti</a>
</nav>

<form method="get" class="form-card" id="st-periodo-form">
  <h3>Periodo</h3>
  <?php if ($scheda === 'liste'): ?><input type="hidden" name="scheda" value="liste"><?php endif; ?>
  <?php if ($errPeriodo): ?><p class="nlr-soglie" role="alert"><?= h($errPeriodo) ?></p><?php endif; ?>
  <div class="st-periodo">
    <div class="form-group"><label for="st-periodo">Quale periodo</label>
      <select id="st-periodo" name="periodo"><?php foreach (NL_REP_PERIODI as $k => [$et]): ?><option value="<?= h((string)$k) ?>"<?= (string)$k === $periodo ? " selected" : "" ?>><?= h($et) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="st-da">Dal giorno</label><input type="date" id="st-da" name="da" value="<?= h($da) ?>" aria-describedby="st-date-aiuto"></div>
    <div class="form-group"><label for="st-a">Al giorno</label><input type="date" id="st-a" name="a" value="<?= h($a) ?>" aria-describedby="st-date-aiuto"></div>
    <?php if ($scheda === 'liste'): ?><div class="form-group"><label for="st-lista-scelta">Lista</label>
      <select id="st-lista-scelta" name="lista"><option value="0">Tutti i contatti</option><?php foreach ($liste as $l): ?><option value="<?= (int)$l['id'] ?>"<?= (int)$l['id'] === $listaId ? ' selected' : '' ?>><?= h(newsletter_4byte_fuori($l['nome'])) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <button class="btn btn-primary" type="submit">Mostra il periodo</button>
  </div>
  <p class="hint" id="st-date-aiuto">Le date contano con «Dal giorno al giorno». Adesso: dal <?= h($data($da)) ?> al <?= h($data($a)) ?> (ora di <?= h(date_default_timezone_get()) ?>).</p>
</form>

<?php if ($scheda === 'campagne'):
  $and = nl_rep_andamento($campagne, $da, $a); [, , $passo] = nl_rep_passo($da, $a);
  $confr = array_values(array_intersect(array_map('intval', array_filter(is_array($_GET['confronta'] ?? null) ? $_GET['confronta'] : [], fn($v) => is_string($v) && ctype_digit($v))), array_map('intval', array_column($campagne, 'id'))));
  $confr = array_slice($confr, 0, 4); ?>
<div class="form-card" id="st-totali">
  <h3>Le campagne del periodo: <?= count($campagne) ?></h3>
  <?= nl_rep_soglie_html($tot, 'st-soglie') ?>
  <?= nl_rep_tessere($tot) ?>
  <p class="hint">Somme delle campagne partite nel periodo: chi apre due campagne conta due volte. I tassi sono sulle consegnate (i rimbalzi sulle email provate). Iscritti attivi oggi: <?= (int)($numeri['attivo'] ?? 0) ?>.
    <?php if (consegna_servizio_scelto() !== 'smtp'): ?>Le segnalazioni di spam arrivano solo se il servizio di spedizione è collegato (<a href="consegna.php?scheda=avvisi">Rimbalzi e spam</a>).<?php endif; ?></p>
</div>

<div class="form-card" id="st-andamento">
  <h3>Andamento, per <?= h($passo) ?></h3>
  <?php if (!$campagne): ?><div class="empty-state"><p>Nessuna campagna partita in questo periodo. Scegli un periodo più lungo, oppure manda la prima da <a href="newsletter.php">Campagne email</a>.</p></div><?php else: ?>
  <div class="st-legenda" aria-hidden="true"><span class="st-s1">Tasso di apertura</span><span class="st-s2">Tasso di clic</span></div>
  <?= nl_rep_svg(array_column($and, 'etichetta'), [['Tasso di apertura', 'st-s1', array_map(fn($x) => $x['n']['t_apertura'], $and)], ['Tasso di clic', 'st-s2', array_map(fn($x) => $x['n']['t_clic'], $and)]], 'Tasso di apertura e di clic per ' . $passo . ', sulle consegnate', '%') ?>
  <details class="pn-aiuto"><summary>I numeri del grafico</summary>
    <table class="table" id="st-andamento-tabella"><thead><tr><th><?= h(ucfirst($passo)) ?></th><th>Consegnate</th><th>Aperture uniche</th><th>Tasso di apertura</th><th>Clic unici</th><th>Tasso di clic</th></tr></thead>
      <tbody><?php foreach ($and as $k => $x): if (!$x['n']['inviate'] && !$x['n']['destinatari']) continue; ?><tr data-passo="<?= h($k) ?>"><td><?= h($x['etichetta']) ?></td><td><?= $x['n']['consegnate'] ?></td><td><?= $x['n']['apertura'] ?></td><td><?= h(nl_rep_pct_testo($x['n']['t_apertura'])) ?></td><td><?= $x['n']['clic'] ?></td><td><?= h(nl_rep_pct_testo($x['n']['t_clic'])) ?></td></tr><?php endforeach; ?></tbody></table>
  </details>
  <?php endif; ?>
</div>

<form method="get" class="form-card st-confronta" id="st-campagne">
  <h3>Campagne partite nel periodo</h3>
  <input type="hidden" name="periodo" value="<?= h($periodo) ?>"><?php if ($periodo === 'scelto'): ?><input type="hidden" name="da" value="<?= h($da) ?>"><input type="hidden" name="a" value="<?= h($a) ?>"><?php endif; ?>
  <?php if (!$campagne): ?><div class="empty-state"><p>Nessuna campagna partita fra il <?= h($data($da)) ?> e il <?= h($data($a)) ?>.</p></div><?php else: ?>
  <div class="nlr-scorre"><table class="table" id="st-tabella">
    <thead><tr><th>Campagna</th><th>Partita il</th><th>Consegnate</th><th>Aperture</th><th>Clic</th><th>Rimbalzi</th><th>Uscite</th><th>Spam</th><th>Confronta</th></tr></thead>
    <tbody>
    <?php foreach ($campagne as $c): $x = $c['n']; $cid = (int)$c['id']; $nome = newsletter_4byte_fuori((string)($c['nome'] !== '' ? $c['nome'] : $c['oggetto'])); /* audit 2026-11: l'emoji, non &#x... */
      $bad = fn(string $k) => isset($x['soglie'][$k]) ? ' class="kw-bad"><span aria-hidden="true">! </span><span class="sr-solo">oltre la soglia: </span' : ''; ?>
      <tr data-campagna="<?= $cid ?>">
        <td><a href="newsletter-report.php?id=<?= $cid ?>"><b><?= h($nome) ?></b></a><?= $c['tipo'] === 'ab' ? ' <span class="nlx-sotto">Test A/B</span>' : '' ?></td>
        <td><?= h(date('d/m/Y H:i', strtotime((string)$c['invio_at']))) ?></td>
        <td><?= $x['consegnate'] ?><?= $x['errori'] ? ' <span class="nlx-sotto">' . (int)$x['errori'] . ' non partite</span>' : '' ?></td>
        <td><b><?= $x['apertura'] ?></b> · <?= h(nl_rep_pct_testo($x['t_apertura'])) ?></td>
        <td><b><?= $x['clic'] ?></b> · <?= h(nl_rep_pct_testo($x['t_clic'])) ?></td>
        <td class="st-rimbalzi"><b><?= $x['rimbalzo'] ?></b> · <span<?= $bad('rimbalzi') ?>><?= h(nl_rep_pct_testo($x['t_rimbalzo'])) ?></span></td>
        <td class="st-uscite"><b><?= $x['uscita'] ?></b> · <span<?= $bad('uscite') ?>><?= h(nl_rep_pct_testo($x['t_uscita'])) ?></span></td>
        <td class="st-spam"><b><?= $x['spam'] ?></b> · <span<?= $bad('spam') ?>><?= h(nl_rep_pct_testo($x['t_spam'])) ?></span></td>
        <td><label><input type="checkbox" name="confronta[]" value="<?= $cid ?>"<?= in_array($cid, $confr, true) ? ' checked' : '' ?>><span class="sr-solo">Confronta «<?= h($nome) ?>»</span></label></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="hint">Il segno «!» dice una soglia superata: rimbalzi definitivi oltre il 2%, disiscrizioni oltre l'1%, spam oltre lo 0,2%. Spunta da due a quattro campagne per vederle una accanto all'altra.</p>
  <button class="btn btn-secondary" type="submit">Confronta le campagne spuntate</button>
  <?php endif; ?>
</form>

<?php if (count($confr) >= 2): $nomi = array_column($campagne, null, 'id'); ?>
<div class="form-card" id="st-confronto">
  <h3>Confronto</h3>
  <div class="nlr-scorre"><table class="table st-confronto" data-no-schede>
    <thead><tr><th>Numero</th><?php foreach ($confr as $cid): ?><th><?= h(newsletter_4byte_fuori((string)($nomi[$cid]['nome'] ?: $nomi[$cid]['oggetto']))) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach (nl_rep_voci() as $k => [$et, $t]): ?><tr data-voce="<?= h($k) ?>"><th scope="row"><?= h($et) ?></th><?php foreach ($confr as $cid): $x = $nomi[$cid]['n']; ?><td><?= $k === 'clic_su_aperture' ? h(nl_rep_pct_testo($x['t_ctor'])) : $x[$k] . ($t !== '' ? ' · ' . h(nl_rep_pct_testo($x[$t])) : '') ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
  </table></div>
</div>
<?php elseif ($confr): ?><p class="nlr-soglie" role="status">Per il confronto spunta almeno due campagne.</p><?php endif; ?>

<?php $flussi = newsletter_flussi(); $motivi = newsletter_uscite_motivi_conta(0); ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:1.2rem">
  <div class="form-card"><h3>Automazioni</h3>
    <?php if (!$flussi): ?><p>Ancora nessuna automazione. <a href="newsletter-flussi.php#fl-crea">Creane una</a>.</p><?php else: ?>
    <table class="table">
      <thead><tr><th>Automazione</th><th>In corso</th><th>Finiti</th><th>Email mandate</th></tr></thead>
      <tbody>
      <?php foreach ($flussi as $f): ?><tr><td><a href="newsletter-flussi.php?id=<?= (int)$f['id'] ?>"><?= h($f['nome']) ?></a><?= ($f['stato'] ?? ($f['attivo'] ? 'attiva' : 'inattiva')) === 'attiva' ? '' : ' <span class="badge badge-draft">' . h(defined('NL_AUT_STATI') ? NL_AUT_STATI[$f['stato']] : 'spenta') . '</span>' ?></td><td><?= (int)$f['in_corso'] ?></td><td><?= (int)$f['finiti'] ?></td><td><?= (int)$f['inviate'] ?></td></tr><?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <div class="form-card" id="st-motivi"><h3>Perché sono usciti (tutte le campagne)</h3>
    <?php if (!$motivi['totale']): ?><p>Ancora nessuna risposta al questionario della pagina di disiscrizione. Le domande si cambiano in <a href="newsletter.php?scheda=impostazioni#nl-pagine">Impostazioni</a>.</p><?php else: ?>
    <table class="table">
      <thead><tr><th>Motivo</th><th>Risposte</th><th>Sul totale</th></tr></thead>
      <tbody>
      <?php foreach ($motivi['n'] as $k => $v): ?><tr><td><?= h(NL_USCITA_MOTIVI[$k]) ?></td><td><b><?= (int)$v ?></b></td><td><?= round($v * 100 / $motivi['totale']) ?>%</td></tr><?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <?php if ($motivi['altri']): ?><p style="font-size:.85rem;margin:.6rem 0 .2rem"><b>Le ultime risposte «Altro»</b></p><ul style="font-size:.85rem;margin:0;padding-left:1.2rem"><?php foreach ($motivi['altri'] as $x): ?><li><?= h(newsletter_4byte_fuori((string)$x['altro'])) ?> <span class="nlx-sotto" style="display:inline">(<?= h(substr((string)$x['creato'], 0, 10)) ?>)</span></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($scheda === 'liste'): $cr = nl_rep_lista($listaId, $da, $a); [, , $passo] = nl_rep_passo($da, $a);
  $ultimo = end($cr) ?: ['iscritti' => 0];
  $nuovi = array_sum(array_column($cr, 'nuovi')); $usciti = array_sum(array_column($cr, 'usciti'));
  $nomeL = $listaId ? newsletter_4byte_fuori((string)(array_column($liste, 'nome', 'id')[$listaId] ?? '')) : 'Tutti i contatti'; ?>
<div class="form-card" id="st-lista">
  <h3><?= h($nomeL) ?>: la crescita</h3>
  <div class="nlr-numeri">
    <?= nl_rep_tessera('iscritti', 'Iscritti alla fine del periodo', (int)$ultimo['iscritti']) ?>
    <?= nl_rep_tessera('nuovi', $listaId ? 'Entrati nella lista' : 'Contatti nuovi', $nuovi) ?>
    <?= nl_rep_tessera('usciti', 'Usciti', $usciti) ?>
    <?= nl_rep_tessera('netto', 'Crescita netta', ($nuovi - $usciti > 0 ? '+' : '') . ($nuovi - $usciti)) ?>
  </div>
  <div class="st-legenda" aria-hidden="true"><span class="st-s1">Nuovi</span><span class="st-s2">Usciti</span></div>
  <?= nl_rep_svg(array_column($cr, 'etichetta'), [['Nuovi', 'st-s1', array_column($cr, 'nuovi')], ['Usciti', 'st-s2', array_column($cr, 'usciti')]], 'Nuovi e usciti per ' . $passo . ': ' . $nomeL) ?>
  <details class="pn-aiuto"><summary>I numeri del grafico</summary>
    <table class="table" id="st-lista-tabella"><thead><tr><th><?= h(ucfirst($passo)) ?></th><th>Nuovi</th><th>Usciti</th><th>Iscritti alla fine</th></tr></thead>
      <tbody><?php foreach ($cr as $k => $x): ?><tr data-passo="<?= h($k) ?>"><td><?= h($x['etichetta']) ?></td><td><?= $x['nuovi'] ?></td><td><?= $x['usciti'] ?></td><td><?= $x['iscritti'] ?></td></tr><?php endforeach; ?></tbody></table>
  </details>
  <p class="hint">Usciti: chi è andato in lista nera (disiscritto, rimbalzato, spam) o, per una lista, chi ha tolto solo questa lista dalle sue preferenze. Chi aspetta ancora la conferma non conta fra gli iscritti. Lo stato è quello di oggi: chi è uscito e poi rientrato conta come iscritto.</p>
</div>
<div class="form-card" id="st-lista-campagne">
  <h3>Campagne mandate <?= $listaId ? 'a questa lista' : 'nel periodo' ?>: <?= count($campagne) ?></h3>
  <?php if (!$campagne): ?><div class="empty-state"><p>Nessuna campagna mandata <?= $listaId ? 'a questa lista ' : '' ?>nel periodo.</p></div><?php else: ?>
  <?= nl_rep_soglie_html($tot, 'st-lista-soglie') ?><?= nl_rep_tessere($tot) ?>
  <ul><?php foreach ($campagne as $c): ?><li><a href="newsletter-report.php?id=<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori((string)($c['nome'] ?: $c['oggetto']))) ?></a> (<?= h(date('d/m/Y', strtotime((string)$c['invio_at']))) ?>)</li><?php endforeach; ?></ul>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
