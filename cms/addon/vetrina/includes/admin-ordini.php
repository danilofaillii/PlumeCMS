<?php
/**
 * Vetrina > Ordini (addon, 2.0.0): l'elenco delle richieste d'ordine con i filtri per stato, e la scheda di un ordine
 * (?id=) con i dati del cliente, le righe, i totali e il cambio di stato (nuovo, pagato, spedito, consegnato, annullato).
 * A ogni cambio di stato, se la casella è spuntata, il cliente riceve un'email. Annullare rimette le scorte.
 */
require_once __DIR__ . '/admin-comune.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['azione'] ?? '') === 'stato') {
        $stato = (string)($_POST['stato'] ?? '');
        $err = vt_ordine_stato($id, $stato, !empty($_POST['avvisa']), (string)($_POST['messaggio'] ?? ''));
        if ($err !== '') flash('error', $err);
        else flash('success', 'Stato cambiato in «' . (VT_STATI[$stato] ?? $stato) . '».' . (!empty($_POST['avvisa']) ? ' Il cliente riceve un\'email.' : ' Nessuna email al cliente.'));
    } elseif (($_POST['azione'] ?? '') === 'email') {
        $o = vt_ordine($id);
        if ($o) { $ok = vt_email_ordine($o)['cliente']; flash($ok ? 'success' : 'error', $ok ? 'Riepilogo mandato di nuovo al cliente.' : 'L\'email non è partita: controlla Impostazioni > Configurazione email.'); }
    }
    vt_admin_vai('ordini', $id ? ['id' => $id] : []);
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $o = vt_ordine($id);
    if (!$o) { flash('error', 'Ordine non trovato.'); vt_admin_vai('ordini'); }
    $adminTitle = 'Ordine ' . $o['numero'];
    require CMS_ADMIN . '/includes/admin-header.php';
    echo vt_admin_stile();
    ?>
<p><a href="<?= h(vt_admin_url('ordini')) ?>">&lsaquo; Tutti gli ordini</a></p>
<div class="vt-a-griglia" id="vt-ordine">
  <div>
    <div class="form-card">
      <h3>Ordine <?= h($o['numero']) ?> <?= vt_admin_badge($o['stato']) ?></h3>
      <p style="color:var(--text-muted);font-size:.88rem">Arrivato il <?= h(date('d/m/Y \a\l\l\e H:i', strtotime($o['creato']))) ?></p>
      <div class="vt-a-tabella-wrap"><table class="admin-table" style="width:100%">
        <thead><tr><th>Prodotto</th><th>Quantità</th><th>Prezzo</th><th style="text-align:right">Totale</th></tr></thead><tbody>
        <?php foreach ($o['righe'] as $r): ?>
          <tr><td><?= h((string)$r['nome']) ?><?= $r['variante'] !== '' ? '<br><small>' . h((string)$r['variante']) . '</small>' : '' ?></td><td><?= (int)$r['qta'] ?></td><td><?= h(vt_euro((int)$r['prezzo_cent'])) ?></td><td style="text-align:right"><?= h(vt_euro((int)$r['totale_cent'])) ?></td></tr>
        <?php endforeach; ?>
          <tr><td colspan="3">Prodotti</td><td style="text-align:right"><?= h(vt_euro($o['subtotale_cent'])) ?></td></tr>
          <tr><td colspan="3"><?= $o['consegna'] === 'ritiro' ? 'Ritiro in negozio' : 'Spedizione' ?></td><td style="text-align:right"><?= $o['spedizione_cent'] ? h(vt_euro($o['spedizione_cent'])) : 'gratis' ?></td></tr>
          <tr><td colspan="3"><b>Totale</b> <small>(IVA inclusa: <?= h(vt_euro($o['iva_cent'])) ?>)</small></td><td style="text-align:right"><b class="vt-a-totale"><?= h(vt_euro($o['totale_cent'])) ?></b></td></tr>
        </tbody></table></div>
    </div>
    <div class="form-card" style="margin-top:1rem">
      <h3>Cliente</h3>
      <p class="vt-a-cliente"><b><?= h($o['nome']) ?></b><br><a href="mailto:<?= h($o['email']) ?>"><?= h($o['email']) ?></a><br><?= h($o['telefono']) ?></p>
      <p><b><?= $o['consegna'] === 'ritiro' ? 'Ritiro in negozio' : 'Spedizione a' ?></b><?= $o['consegna'] === 'ritiro' ? '' : '<br>' . h($o['indirizzo']) . '<br>' . h($o['cap'] . ' ' . $o['citta']) ?></p>
      <p><b>Pagamento:</b> <?= h(VT_METODI[$o['metodo']] ?? $o['metodo']) ?><?= $o['metodo'] === 'bonifico' ? ' (causale: Ordine ' . h($o['numero']) . ')' : '' ?></p>
      <?php if ($o['note'] !== ''): ?><p><b>Note del cliente:</b><br><span class="vt-a-note"><?= nl2br(h((string)$o['note'])) ?></span></p><?php endif; ?>
      <p style="font-size:.82rem;color:var(--text-muted)">Consenso a condizioni e privacy dato il <?= h(date('d/m/Y', strtotime((string)$o['consenso'] ?: $o['creato']))) ?>.</p>
    </div>
  </div>
  <aside>
    <div class="form-card">
      <h3>Cambia lo stato</h3>
      <form method="post" id="vt-stato"><?= csrf_field() ?><input type="hidden" name="azione" value="stato"><input type="hidden" name="id" value="<?= $o['id'] ?>">
        <div class="form-group"><label for="vt-stato-scelta">Stato</label><select name="stato" id="vt-stato-scelta">
          <?php foreach (VT_STATI as $k => $et): ?><option value="<?= h($k) ?>"<?= $o['stato'] === $k ? ' selected' : '' ?>><?= h($et) ?></option><?php endforeach; ?>
        </select></div>
        <label class="vt-a-spunta"><input type="checkbox" name="avvisa" value="1" checked> Manda un'email al cliente</label>
        <div class="form-group"><label for="vt-messaggio">Messaggio in più nell'email (facoltativo)</label><textarea name="messaggio" id="vt-messaggio" class="vt-a-corto" maxlength="1000" placeholder="Per esempio il numero di spedizione del corriere"></textarea></div>
        <button type="submit" class="btn btn-primary">Salva lo stato</button>
      </form>
      <p class="hint" style="font-size:.8rem;color:var(--text-muted);margin-top:.8rem">Annullare un ordine rimette in magazzino i pezzi. Il pagamento lo controlli tu (bonifico arrivato, PayPal o Stripe): poi scegli «Pagato».</p>
    </div>
    <div class="form-card" style="margin-top:1rem">
      <h3>Storia</h3>
      <ul class="vt-a-storia"><?php foreach (array_reverse($o['storia']) as $s): ?><li><?= h(date('d/m/Y H:i', strtotime((string)$s['quando']))) ?>: <?= h(VT_STATI[$s['stato']] ?? (string)$s['stato']) ?></li><?php endforeach; ?></ul>
      <form method="post" style="margin-top:.8rem"><?= csrf_field() ?><input type="hidden" name="azione" value="email"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button type="submit" class="btn btn-secondary btn-sm">Rimanda il riepilogo al cliente</button></form>
    </div>
  </aside>
</div>
<?php
    require CMS_ADMIN . '/includes/admin-footer.php';
    return;
}

$stato = (string)($_GET['stato'] ?? '');
if (!isset(VT_STATI[$stato])) $stato = '';
$cerca = vt_testo($_GET['q'] ?? '', 100);
$ordini = vt_ordini($stato, $cerca);
$adminTitle = 'Ordini';
require CMS_ADMIN . '/includes/admin-header.php';
echo vt_admin_stile();
$conta = [];
foreach (array_keys(VT_STATI) as $k) $conta[$k] = vt_ordini_conta($k);
?>
<p style="max-width:62rem;color:var(--text-muted)">Le richieste d'ordine arrivate dal negozio del sito (<a href="<?= h(rtrim(cms_url_sito(), '/') . vt_base()) ?>" target="_blank"><?= h(vt_base()) ?></a>). Ogni ordine arriva anche per email. Aprilo per cambiare lo stato: il cliente riceve un'email a ogni passo.</p>
<nav class="vt-a-schede" aria-label="Stati">
  <a href="<?= h(vt_admin_url('ordini')) ?>"<?= $stato === '' ? ' aria-current="page"' : '' ?>>Tutti</a>
  <?php foreach (VT_STATI as $k => $et): ?><a href="<?= h(vt_admin_url('ordini', ['stato' => $k])) ?>"<?= $stato === $k ? ' aria-current="page"' : '' ?>><?= h($et) ?> (<?= $conta[$k] ?>)</a><?php endforeach; ?>
</nav>
<form method="get" class="admin-toolbar" style="margin-bottom:1rem"><input type="hidden" name="a" value="vetrina"><input type="hidden" name="p" value="ordini"><?php if ($stato !== ''): ?><input type="hidden" name="stato" value="<?= h($stato) ?>"><?php endif; ?>
  <input type="search" name="q" class="search-input" value="<?= h($cerca) ?>" placeholder="Cerca per nome, email o numero"><button type="submit" class="btn btn-secondary btn-sm">Cerca</button></form>
<?php if (!$ordini): ?>
  <div class="empty-state"><p>Nessun ordine<?= $stato !== '' || $cerca !== '' ? ' con questi filtri' : ', per ora' ?>.</p></div>
<?php else: ?>
<div class="admin-table-wrap vt-a-tabella-wrap"><table class="admin-table" id="vt-ordini" style="width:100%">
  <thead><tr><th>Numero</th><th>Cliente</th><th>Totale</th><th>Pagamento</th><th>Stato</th><th>Arrivato</th></tr></thead><tbody>
  <?php foreach ($ordini as $o): ?>
    <tr data-numero="<?= h($o['numero']) ?>"><td><a href="<?= h(vt_admin_url('ordini', ['id' => $o['id']])) ?>"><b><?= h($o['numero']) ?></b></a></td><td><?= h($o['nome']) ?><br><small><?= h($o['email']) ?></small></td>
      <td><?= h(vt_euro($o['totale_cent'])) ?></td><td><?= h(VT_METODI[$o['metodo']] ?? $o['metodo']) ?></td><td><?= vt_admin_badge($o['stato']) ?></td><td><?= h(date('d/m/Y H:i', strtotime($o['creato']))) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
