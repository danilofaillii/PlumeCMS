<?php
/**
 * Pannello, Corsi (addon corsi): l'elenco dei corsi, con quanti iscritti e lezioni; Nuovo corso; Elimina.
 */
require_once __DIR__ . '/corsi.php';
corsi_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $azione = (string)($_POST['azione'] ?? '');
        if ($azione === 'nuovo') {
            $id = corsi_crea((string)($_POST['titolo'] ?? ''));
            flash('success', 'Corso creato. Adesso scrivi la descrizione e aggiungi le lezioni.');
            header('Location: ' . corsi_admin_url('corso', ['id' => $id]));
            exit;
        }
        if ($azione === 'elimina') {
            $c = corsi_leggi((int)($_POST['id'] ?? 0));
            if (!$c) throw new RuntimeException('Corso non trovato.');
            corsi_elimina((int)$c['id']);
            flash('success', 'Il corso «' . $c['titolo'] . '» è stato eliminato, con le sue lezioni, l\'avanzamento e i certificati.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . corsi_admin_url('corsi'));
    exit;
}

$corsi = corsi_tutti();
$adminTitle = 'Corsi';
require CMS_ADMIN . '/includes/admin-header.php';
?>
<style>.co-griglia{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:1.25rem}@media(max-width:1000px){.co-griglia{grid-template-columns:1fr}}.co-tab{overflow-x:auto}</style>
<p style="max-width:62rem">I corsi online del sito. Li seguono i <b>membri</b> dell'area riservata: ognuno vede quanto gli manca, riprende da dove era e alla fine riceve un certificato.
Sul sito l'elenco è a <code>/corsi/</code>; in una pagina lo mostri con il widget <b>Elenco corsi</b> dell'editor.</p>
<div class="co-griglia">
  <div class="sc-scatola"><h3><?= count($corsi) ?> corsi</h3><div class="sc-corpo co-tab" style="padding:0">
  <?php if (!$corsi): ?><p style="padding:1rem;margin:0;color:var(--text-muted)">Ancora nessun corso. Crea il primo con il riquadro qui accanto.</p>
  <?php else: ?>
    <table class="admin-table" id="co-elenco" style="width:100%">
      <thead><tr><th>Corso</th><th>Stato</th><th>Chi lo vede</th><th>Lezioni</th><th>Iscritti</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($corsi as $c): $nl = count(corsi_lezioni((int)$c['id'])); $ni = count(corsi_iscritti((int)$c['id'])); ?>
        <tr data-corso="<?= h($c['slug']) ?>">
          <td><a href="<?= h(corsi_admin_url('corso', ['id' => $c['id']])) ?>"><b><?= h($c['titolo']) ?></b></a><br><small style="color:var(--text-muted)">/corsi/<?= h($c['slug']) ?>/</small></td>
          <td><span class="badge <?= $c['stato'] === 'pubblicato' ? 'badge-published' : 'badge-draft' ?>"><?= h(CORSI_STATI[$c['stato']] ?? $c['stato']) ?></span></td>
          <td><?= h(CORSI_ACCESSI[$c['accesso']] ?? $c['accesso']) ?></td>
          <td><?= $nl ?></td><td><?= $ni ?></td>
          <td style="white-space:nowrap"><a class="btn btn-secondary btn-sm" href="<?= h(corsi_admin_url('corso', ['id' => $c['id']])) ?>">Modifica</a>
            <form method="post" style="display:inline" data-conferma="Eliminare «<?= h($c['titolo']) ?>»? Spariscono le lezioni, l'avanzamento di chi lo segue e i certificati. Non si torna indietro."><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div></div>
  <aside><div class="sc-scatola"><h3>Nuovo corso</h3><div class="sc-corpo">
    <form method="post" id="co-nuovo"><?= csrf_field() ?><input type="hidden" name="azione" value="nuovo">
      <label for="co-titolo">Titolo del corso</label><input type="text" id="co-titolo" name="titolo" maxlength="200" required placeholder="Per esempio: Fotografia per cominciare">
      <button class="btn btn-primary" type="submit" style="margin-top:.8rem">Crea il corso</button>
    </form>
    <p class="hint">Il corso nasce in bozza, con un primo modulo vuoto: lo pubblichi quando è pronto.</p>
  </div></div></aside>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
