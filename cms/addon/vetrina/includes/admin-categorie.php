<?php
/**
 * Vetrina > Categorie (addon, 2.0.0): le categorie dei prodotti (i filtri in cima al negozio). Crea, rinomina, ordina,
 * elimina (i prodotti restano, senza quella categoria).
 */
require_once __DIR__ . '/admin-comune.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $az = (string)($_POST['azione'] ?? '');
    if ($az === 'salva') {
        $r = vt_categoria_salva((int)($_POST['id'] ?? 0), (string)($_POST['nome'] ?? ''), (string)($_POST['descrizione'] ?? ''), (int)($_POST['ordine'] ?? 0));
        flash(is_int($r) ? 'success' : 'error', is_int($r) ? 'Categoria salvata.' : $r);
    } elseif ($az === 'elimina') {
        vt_categoria_elimina((int)($_POST['id'] ?? 0));
        flash('success', 'Categoria eliminata. I suoi prodotti restano nel negozio.');
    }
    vt_admin_vai('categorie');
}
$categorie = vt_categorie();
$conta = [];
foreach (db()->query('SELECT categoria_id, COUNT(*) n FROM ' . tbl('vetrina_prodotti_categorie') . ' GROUP BY categoria_id')->fetchAll() as $r) $conta[(int)$r['categoria_id']] = (int)$r['n'];
$adminTitle = 'Categorie dei prodotti';
require CMS_ADMIN . '/includes/admin-header.php';
echo vt_admin_stile();
?>
<p style="max-width:62rem;color:var(--text-muted)">Le categorie diventano i filtri in cima al negozio (per esempio Tazze, Piatti, Regali). Un prodotto può stare in più categorie: le scegli nella sua scheda.</p>
<div class="vt-a-griglia">
  <div>
  <?php if (!$categorie): ?><div class="empty-state"><p>Ancora nessuna categoria.</p></div><?php else: ?>
    <div class="admin-table-wrap vt-a-tabella-wrap"><table class="admin-table" id="vt-categorie" style="width:100%"><thead><tr><th>Nome</th><th>Prodotti</th><th>Posizione</th><th></th></tr></thead><tbody>
    <?php foreach ($categorie as $c): ?>
      <tr><td><form method="post" id="vt-cat-<?= (int)$c['id'] ?>" style="display:flex;flex-direction:column;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input type="text" name="nome" value="<?= h($c['nome']) ?>" maxlength="120" required aria-label="Nome" style="padding:.4rem .5rem;border:1px solid var(--border);border-radius:6px;background:var(--campo,transparent);color:var(--text)">
          <input type="text" name="descrizione" value="<?= h((string)$c['descrizione']) ?>" maxlength="1000" placeholder="Una frase sotto il titolo (facoltativa)" aria-label="Descrizione" style="padding:.4rem .5rem;border:1px solid var(--border);border-radius:6px;background:var(--campo,transparent);color:var(--text);font-size:.85rem"></form></td>
        <td><?= $conta[(int)$c['id']] ?? 0 ?></td>
        <td><input form="vt-cat-<?= (int)$c['id'] ?>" type="number" name="ordine" value="<?= (int)$c['ordine'] ?>" min="-999" max="999" style="width:5rem" aria-label="Posizione"></td>
        <td style="white-space:nowrap"><button form="vt-cat-<?= (int)$c['id'] ?>" type="submit" class="btn btn-secondary btn-sm">Salva</button>
          <form method="post" style="display:inline" data-conferma="Eliminare la categoria «<?= h($c['nome']) ?>»? I prodotti restano."><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">Elimina</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  </div>
  <aside><div class="form-card"><h3>Nuova categoria</h3>
    <form method="post" id="vt-cat-nuova"><?= csrf_field() ?><input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="0">
      <div class="form-group"><label for="vt-cat-nome">Nome</label><input type="text" id="vt-cat-nome" name="nome" maxlength="120" required placeholder="Tazze"></div>
      <div class="form-group"><label for="vt-cat-desc">Frase sotto il titolo (facoltativa)</label><textarea id="vt-cat-desc" name="descrizione" class="vt-a-corto" maxlength="1000"></textarea></div>
      <div class="form-group"><label for="vt-cat-ordine">Posizione</label><input type="number" id="vt-cat-ordine" name="ordine" value="0" min="-999" max="999"></div>
      <button type="submit" class="btn btn-primary">Aggiungi la categoria</button>
    </form></div></aside>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
