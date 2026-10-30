<?php
/**
 * Vetrina > Prodotti (addon, 2.0.0): l'elenco dei prodotti con foto, prezzo, scorte e stato, il pulsante per un
 * prodotto nuovo e quello per eliminare. La scheda di un prodotto è la pagina "prodotto" (admin-prodotto.php).
 */
require_once __DIR__ . '/admin-comune.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['azione'] ?? '') === 'elimina' && ($p = vt_prodotto((int)($_POST['id'] ?? 0), false))) {
        vt_prodotto_elimina($p['id']);
        flash('success', '«' . $p['nome'] . '» eliminato. Gli ordini già arrivati restano come erano.');
    }
    vt_admin_vai('prodotti');
}
$cerca = vt_testo($_GET['q'] ?? '', 100);
$prodotti = vt_prodotti(['tutti' => true, 'cerca' => $cerca]);
$adminTitle = 'Prodotti';
require CMS_ADMIN . '/includes/admin-header.php';
echo vt_admin_stile();
?>
<p style="max-width:62rem;color:var(--text-muted)">I prodotti del negozio. Sul sito li trovi in <a href="<?= h(rtrim(cms_url_sito(), '/') . vt_base()) ?>" target="_blank"><?= h(vt_base()) ?></a>; nelle pagine li mostri con il widget <b>Prodotti</b> dell'editor (gruppo Negozio).</p>
<div class="admin-toolbar" style="margin-bottom:1rem;display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
  <a class="btn btn-primary" href="<?= h(vt_admin_url('prodotto')) ?>" id="vt-nuovo">+ Nuovo prodotto</a>
  <form method="get" style="display:flex;gap:.4rem"><input type="hidden" name="a" value="vetrina"><input type="hidden" name="p" value="prodotti">
    <input type="search" name="q" class="search-input" value="<?= h($cerca) ?>" placeholder="Cerca un prodotto"><button type="submit" class="btn btn-secondary btn-sm">Cerca</button></form>
</div>
<?php if (!$prodotti): ?>
  <div class="empty-state"><p><?= $cerca !== '' ? 'Nessun prodotto con questo nome.' : 'Ancora nessun prodotto. Comincia dal primo: bastano nome, prezzo e una foto.' ?></p></div>
<?php else: ?>
<div class="admin-table-wrap vt-a-tabella-wrap"><table class="admin-table" id="vt-prodotti" style="width:100%">
  <thead><tr><th></th><th>Prodotto</th><th>Prezzo</th><th>Scorte</th><th>Stato</th><th></th></tr></thead><tbody>
  <?php foreach ($prodotti as $p):
      $scorte = $p['varianti'] ? implode(', ', array_map(fn($v) => vt_variante_nome($v) . ': ' . ($v['scorta'] === null ? 'non contate' : $v['scorta']), $p['varianti'])) : ($p['scorta'] === null ? 'non contate' : (string)$p['scorta']); ?>
    <tr data-slug="<?= h($p['slug']) ?>">
      <td><?= $p['foto'] ? '<img class="vt-a-foto" src="' . h($p['foto'][0]) . '" alt="">' : '<span class="vt-a-foto"></span>' ?></td>
      <td><a href="<?= h(vt_admin_url('prodotto', ['id' => $p['id']])) ?>"><b><?= h($p['nome']) ?></b></a><?= $p['evidenza'] ? ' <span class="badge badge-published">In evidenza</span>' : '' ?><br><small><?= h(vt_base() . $p['slug'] . '/') ?></small></td>
      <td><?= vt_prezzo_pieno($p) ? '<s>' . h(vt_euro($p['prezzo_cent'])) . '</s> ' : '' ?><?= h(vt_euro(vt_prezzo($p))) ?><?= $p['varianti'] ? '<br><small>' . count($p['varianti']) . ' varianti</small>' : '' ?></td>
      <td><small><?= h($scorte) ?></small></td>
      <td><?= $p['pubblicato'] ? '<span class="badge badge-published">Sul sito</span>' : '<span class="badge badge-draft">Nascosto</span>' ?><?= !vt_disponibile($p) ? ' <span class="badge badge-draft">Esaurito</span>' : '' ?></td>
      <td style="white-space:nowrap"><a class="btn btn-secondary btn-sm" href="<?= h(vt_admin_url('prodotto', ['id' => $p['id']])) ?>">Modifica</a>
        <?php if ($p['pubblicato']): ?><a class="btn btn-secondary btn-sm" href="<?= h(rtrim(cms_url_sito(), '/') . vt_base() . $p['slug'] . '/') ?>" target="_blank">Vedi</a><?php endif; ?>
        <form method="post" style="display:inline" data-conferma="Eliminare «<?= h($p['nome']) ?>»? Non si torna indietro (gli ordini già arrivati restano)."><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">Elimina</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
