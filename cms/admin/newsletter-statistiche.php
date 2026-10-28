<?php
/**
 * Statistiche della newsletter: aperture e clic per campagna, link più cliccati, crescita degli iscritti, flussi.
 * I numeri sono per persona e per difetto: chi blocca le immagini nelle email non risulta tra le aperture.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/newsletter.php';
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();
newsletter_flussi_schema();
$adminTitle = 'Statistiche della newsletter';
$campagne = newsletter_statistiche_campagne();
$mesi = newsletter_iscritti_per_mese();
$flussi = newsletter_flussi();
$numeri = newsletter_numeri();
$dettaglio = isset($_GET['campagna']) ? (int)$_GET['campagna'] : 0;
$tot = ['inviate' => array_sum(array_column($campagne, 'inviate')), 'aperture' => array_sum(array_column($campagne, 'aperture')), 'clic' => array_sum(array_column($campagne, 'clic'))];
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<div class="stats-grid">
  <div class="stat-card stat-card-accent"><div class="stat-card-value"><?= (int)($numeri['attivo'] ?? 0) ?></div><div class="stat-card-label">Iscritti attivi</div></div>
  <div class="stat-card"><div class="stat-card-value"><?= $tot['inviate'] ?></div><div class="stat-card-label">Email inviate (campagne)</div></div>
  <div class="stat-card stat-card-sage"><div class="stat-card-value"><?= $tot['inviate'] ? round($tot['aperture'] * 100 / $tot['inviate'], 1) : 0 ?>%</div><div class="stat-card-label">Aperture, media</div></div>
  <div class="stat-card stat-card-mustard"><div class="stat-card-value"><?= $tot['inviate'] ? round($tot['clic'] * 100 / $tot['inviate'], 1) : 0 ?>%</div><div class="stat-card-label">Clic, media</div></div>
</div>
<p class="hint" style="font-size:.8rem;color:var(--text-muted);margin:0 0 1rem">Aperture e clic contano una volta per persona. Chi legge con le immagini bloccate non compare: i numeri veri sono un po' più alti.</p>

<div class="admin-table-wrap ct-tabella" style="margin-bottom:1.2rem">
  <table>
    <thead><tr><th>Campagna</th><th>Inviata il</th><th>Inviate</th><th>Aperte</th><th>Clic</th></tr></thead>
    <tbody>
    <?php if (!$campagne): ?><tr><td colspan="5"><div class="empty-state"><p>Nessuna campagna inviata ancora.</p></div></td></tr><?php endif; ?>
    <?php foreach ($campagne as $c): ?>
      <tr class="ct-riga">
        <td class="ct-titolo"><a class="ct-nome" href="newsletter-statistiche.php?campagna=<?= (int)$c['id'] ?>"><?= h($c['oggetto']) ?></a><div class="ct-azioni"><a href="newsletter-statistiche.php?campagna=<?= (int)$c['id'] ?>">Link cliccati</a><a href="newsletter-campagna.php?id=<?= (int)$c['id'] ?>">Apri la campagna</a></div></td>
        <td class="ct-muto"><?= h(substr((string)$c['invio_at'], 0, 16)) ?></td>
        <td class="ct-muto"><?= (int)$c['inviate'] ?><?= $c['errori'] ? ' <span class="kw-bad">(' . (int)$c['errori'] . ' errori)</span>' : '' ?></td>
        <td class="ct-muto"><b><?= (int)$c['aperture'] ?></b> · <?= $c['tasso_aperture'] ?>%</td>
        <td class="ct-muto"><b><?= (int)$c['clic'] ?></b> · <?= $c['tasso_clic'] ?>%</td>
      </tr>
      <?php if ($dettaglio === (int)$c['id']): $link = newsletter_clic_per_link($dettaglio); ?>
      <tr><td colspan="5" style="background:var(--fondo-2)">
        <b style="font-size:.85rem">Link più cliccati</b>
        <?php if (!$link): ?><p class="ct-muto">Nessun clic ancora.</p><?php else: ?>
        <ul class="db-lista" style="font-size:.85rem;margin:.4rem 0 0;padding:0;list-style:none"><?php foreach ($link as $l): ?><li style="display:flex;justify-content:space-between;gap:1rem;padding:.2rem 0"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($l['url']) ?></span><b><?= (int)$l['n'] ?></b></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.2rem">
  <div class="admin-table-wrap ct-tabella">
    <table>
      <thead><tr><th>Mese</th><th>Nuovi iscritti</th><th>di cui attivi</th><th>usciti</th></tr></thead>
      <tbody>
      <?php if (!$mesi): ?><tr><td colspan="4" class="ct-muto">Nessun iscritto negli ultimi 12 mesi.</td></tr><?php endif; ?>
      <?php foreach (array_reverse($mesi) as $m): ?><tr><td><?= h($m['mese']) ?></td><td class="ct-muto"><?= (int)$m['n'] ?></td><td class="ct-muto"><?= (int)$m['attivi'] ?></td><td class="ct-muto"><?= (int)$m['usciti'] ?></td></tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="admin-table-wrap ct-tabella">
    <table>
      <thead><tr><th>Flusso</th><th>In corso</th><th>Finiti</th><th>Email mandate</th></tr></thead>
      <tbody>
      <?php if (!$flussi): ?><tr><td colspan="4" class="ct-muto">Nessun flusso. <a href="newsletter-flussi.php?nuovo=1">Creane uno</a>.</td></tr><?php endif; ?>
      <?php foreach ($flussi as $f): ?><tr><td><a href="newsletter-flussi.php?id=<?= (int)$f['id'] ?>" style="color:inherit"><?= h($f['nome']) ?></a><?= $f['attivo'] ? '' : ' <span class="badge badge-draft">spento</span>' ?></td><td class="ct-muto"><?= (int)$f['in_corso'] ?></td><td class="ct-muto"><?= (int)$f['finiti'] ?></td><td class="ct-muto"><?= (int)$f['inviate'] ?></td></tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
