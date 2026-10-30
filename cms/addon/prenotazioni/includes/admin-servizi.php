<?php
/**
 * Prenotazioni > Servizi: quello che si può prenotare (nome, durata, pausa dopo, prezzo facoltativo, chi lo fa).
 * ?id=N apre la scheda di un servizio, ?id=0&nuovo=1 un servizio nuovo.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Servizi da prenotare';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['azione'] ?? '') === 'elimina') {
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('pren_prenotazioni') . " WHERE servizio_id = ? AND stato IN ('confermata','da_confermare') AND inizio_ts > ?");
        $st->execute([$id, time()]);
        if ((int)$st->fetchColumn() > 0) { flash('error', 'Questo servizio ha prenotazioni ancora da fare: spegnilo (togli la spunta "Si può prenotare") invece di eliminarlo.'); pren_admin_vai('servizi', ['id' => $id]); }
        db()->prepare('DELETE FROM ' . tbl('pren_servizi') . ' WHERE id = ?')->execute([$id]);
        flash('success', 'Servizio eliminato. Le prenotazioni passate restano nell\'elenco.');
        pren_admin_vai('servizi');
    }
    $nome = pren_testo($_POST['nome'] ?? '', 150);
    $durata = max(5, min(600, (int)($_POST['durata'] ?? 30)));
    $pausa = max(0, min(240, (int)($_POST['pausa'] ?? 0)));
    $risorse = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['risorse'] ?? [])))));
    $tutte = array_map('intval', array_column(pren_risorse(), 'id'));
    $risorse = array_values(array_intersect($risorse, $tutte));
    if ($nome === '') { flash('error', 'Scrivi il nome del servizio.'); pren_admin_vai('servizi', ['id' => $id, 'nuovo' => $id ? null : 1]); }
    $val = [$nome, pren_testo($_POST['descrizione'] ?? '', 500), $durata, $pausa, pren_testo($_POST['prezzo'] ?? '', 40), count($risorse) === count($tutte) ? '' : implode(',', $risorse), !empty($_POST['attivo']) ? 1 : 0, (int)($_POST['ordine'] ?? 0)];
    if ($id) {
        db()->prepare('UPDATE ' . tbl('pren_servizi') . ' SET nome = ?, descrizione = ?, durata = ?, pausa = ?, prezzo = ?, risorse = ?, attivo = ?, ordine = ? WHERE id = ?')->execute([...$val, $id]);
    } else {
        db()->prepare('INSERT INTO ' . tbl('pren_servizi') . ' (nome, descrizione, durata, pausa, prezzo, risorse, attivo, ordine, creato) VALUES (?,?,?,?,?,?,?,?,?)')->execute([...$val, date('Y-m-d H:i:s')]);
        $id = (int)db()->lastInsertId();
    }
    flash('success', 'Servizio «' . $nome . '» salvato.');
    pren_admin_vai('servizi');
}

$servizi = pren_servizi();
$risorse = pren_risorse();
$id = (int)($_GET['id'] ?? 0);
$s = $id ? pren_servizio($id) : (isset($_GET['nuovo']) ? ['id' => 0, 'nome' => '', 'descrizione' => '', 'durata' => 30, 'pausa' => 0, 'prezzo' => '', 'risorse' => '', 'attivo' => 1, 'ordine' => count($servizi) + 1] : null);
require CMS_ADMIN . '/includes/admin-header.php';
pren_admin_schede('servizi');
if ($s):
    $scelte = array_filter(array_map('intval', explode(',', (string)$s['risorse'])));
?>
<p><a href="<?= h(pren_admin_url('servizi')) ?>">&lsaquo; Tutti i servizi</a></p>
<form method="post" class="pa-modulo" id="pa-servizio"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
  <h3><?= $s['id'] ? 'Modifica il servizio' : 'Nuovo servizio' ?></h3>
  <div class="pa-campi">
    <label class="pa-largo">Nome<input name="nome" required maxlength="150" value="<?= h((string)$s['nome']) ?>" placeholder="Taglio e piega"></label>
    <label>Durata (minuti)<input type="number" name="durata" min="5" max="600" step="5" value="<?= (int)$s['durata'] ?>"></label>
    <label>Pausa dopo (minuti)<input type="number" name="pausa" min="0" max="240" step="5" value="<?= (int)$s['pausa'] ?>"><span class="pa-aiuto">Il tempo per riordinare prima del cliente dopo. Il cliente non lo vede.</span></label>
    <label>Prezzo (facoltativo)<input name="prezzo" maxlength="40" value="<?= h((string)$s['prezzo']) ?>" placeholder="35 €"></label>
    <label>Ordine nell'elenco<input type="number" name="ordine" value="<?= (int)$s['ordine'] ?>"></label>
    <label class="pa-largo">Descrizione (facoltativa)<textarea name="descrizione" rows="2" maxlength="500"><?= h((string)$s['descrizione']) ?></textarea></label>
    <fieldset class="pa-largo pa-campo" style="border:0;padding:0;margin:0"><legend>Chi lo fa</legend>
      <?php if (!$risorse): ?><span class="pa-aiuto">Prima aggiungi le persone in <a href="<?= h(pren_admin_url('persone')) ?>">Persone e orari</a>.</span><?php endif; ?>
      <?php foreach ($risorse as $r): ?><label class="pa-spunta"><input type="checkbox" name="risorse[]" value="<?= (int)$r['id'] ?>"<?= !$scelte || in_array((int)$r['id'], $scelte, true) ? ' checked' : '' ?>> <?= h((string)$r['nome']) ?></label><?php endforeach; ?>
    </fieldset>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="attivo" value="1"<?= $s['attivo'] ? ' checked' : '' ?>> Si può prenotare dal sito</label>
  </div>
  <p class="pa-azioni" style="margin-top:1rem"><button class="btn btn-primary">Salva</button></p>
</form>
<?php if ($s['id']): ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-danger btn-sm" name="azione" value="elimina" data-conferma="Eliminare il servizio?">Elimina il servizio</button></form>
<?php endif; ?>
<?php else: ?>
<div class="pa-barra"><h2>Servizi</h2><a class="btn btn-primary" href="<?= h(pren_admin_url('servizi', ['nuovo' => 1])) ?>">Aggiungi un servizio</a></div>
<div class="admin-table-wrap"><table class="pa-tabella">
  <thead><tr><th>Servizio</th><th>Durata</th><th>Prezzo</th><th>Chi lo fa</th><th></th></tr></thead>
  <tbody>
  <?php if (!$servizi): ?><tr><td colspan="5"><div class="empty-state"><p>Nessun servizio. Per esempio: «Taglio donna», 45 minuti, 35 €.</p></div></td></tr><?php endif; ?>
  <?php foreach ($servizi as $x): $ids = array_filter(array_map('intval', explode(',', (string)$x['risorse']))); ?>
  <tr>
    <td><a href="<?= h(pren_admin_url('servizi', ['id' => $x['id']])) ?>"><b><?= h((string)$x['nome']) ?></b></a></td>
    <td><?= (int)$x['durata'] ?> min<?= $x['pausa'] ? ' + ' . (int)$x['pausa'] . ' di pausa' : '' ?></td>
    <td><?= h((string)$x['prezzo']) ?></td>
    <td class="pa-muto"><?= $ids ? h(implode(', ', array_map(fn($r) => $r['nome'], array_filter($risorse, fn($r) => in_array((int)$r['id'], $ids, true))))) : 'Tutti' ?></td>
    <td><?= $x['attivo'] ? '<span class="badge badge-published">Si prenota</span>' : '<span class="badge badge-draft">Spento</span>' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
