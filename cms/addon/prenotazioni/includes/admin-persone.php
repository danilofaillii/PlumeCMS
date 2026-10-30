<?php
/**
 * Prenotazioni > Persone e orari: chi lavora (o una risorsa: una cabina, un campo), i suoi orari della settimana scritti
 * come nel widget Orari di apertura ("9:00-13:00, 15:00-19:00", "Chiuso"), le sue ferie.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Persone e orari';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['azione'] ?? '') === 'elimina') {
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('pren_prenotazioni') . " WHERE risorsa_id = ? AND stato IN ('confermata','da_confermare') AND inizio_ts > ?");
        $st->execute([$id, time()]);
        if ((int)$st->fetchColumn() > 0) { flash('error', 'Questa persona ha appuntamenti ancora da fare: spostali o annullali prima, oppure togli la spunta "Prende prenotazioni".'); pren_admin_vai('persone', ['id' => $id]); }
        db()->prepare('DELETE FROM ' . tbl('pren_risorse') . ' WHERE id = ?')->execute([$id]);
        flash('success', 'Eliminata.');
        pren_admin_vai('persone');
    }
    $nome = pren_testo($_POST['nome'] ?? '', 100);
    if ($nome === '') { flash('error', 'Scrivi il nome.'); pren_admin_vai('persone', ['id' => $id, 'nuova' => $id ? null : 1]); }
    $orari = [];
    foreach (PREN_GIORNI as $g) $orari[$g] = pren_testo($_POST['orari'][$g] ?? '', 120);
    $val = [$nome, pren_testo($_POST['descrizione'] ?? '', 300), json_encode($orari, JSON_UNESCAPED_UNICODE), json_encode(pren_admin_chiusure_leggi($_POST['ferie'] ?? []), JSON_UNESCAPED_UNICODE), !empty($_POST['attivo']) ? 1 : 0, (int)($_POST['ordine'] ?? 0)];
    if ($id) db()->prepare('UPDATE ' . tbl('pren_risorse') . ' SET nome = ?, descrizione = ?, orari = ?, ferie = ?, attivo = ?, ordine = ? WHERE id = ?')->execute([...$val, $id]);
    else db()->prepare('INSERT INTO ' . tbl('pren_risorse') . ' (nome, descrizione, orari, ferie, attivo, ordine, creato) VALUES (?,?,?,?,?,?,?)')->execute([...$val, date('Y-m-d H:i:s')]);
    flash('success', '«' . $nome . '» salvata. Gli orari valgono subito per le prenotazioni dal sito.');
    pren_admin_vai('persone');
}

$risorse = pren_risorse();
$id = (int)($_GET['id'] ?? 0);
$r = null;
if ($id) foreach ($risorse as $x) if ((int)$x['id'] === $id) $r = $x;
if (!$r && isset($_GET['nuova'])) $r = ['id' => 0, 'nome' => '', 'descrizione' => '', 'orari' => ['lun' => '9:00-13:00, 15:00-19:00', 'mar' => '9:00-13:00, 15:00-19:00', 'mer' => '9:00-13:00, 15:00-19:00', 'gio' => '9:00-13:00, 15:00-19:00', 'ven' => '9:00-13:00, 15:00-19:00', 'sab' => '9:00-13:00', 'dom' => 'Chiuso'], 'ferie' => [], 'attivo' => 1, 'ordine' => count($risorse) + 1];
require CMS_ADMIN . '/includes/admin-header.php';
pren_admin_schede('persone');
if ($r): ?>
<p><a href="<?= h(pren_admin_url('persone')) ?>">&lsaquo; Tutte le persone</a></p>
<form method="post" class="pa-modulo" id="pa-persona"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
  <h3><?= $r['id'] ? 'Modifica' : 'Nuova persona' ?></h3>
  <div class="pa-campi">
    <label>Nome<input name="nome" required maxlength="100" value="<?= h((string)$r['nome']) ?>" placeholder="Anna"></label>
    <label>Ordine nell'elenco<input type="number" name="ordine" value="<?= (int)$r['ordine'] ?>"></label>
    <label class="pa-largo">Descrizione (facoltativa)<input name="descrizione" maxlength="300" value="<?= h((string)$r['descrizione']) ?>" placeholder="Colore e trattamenti"></label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="attivo" value="1"<?= $r['attivo'] ? ' checked' : '' ?>> Prende prenotazioni dal sito</label>
  </div>
  <h3 style="margin-top:1.2rem">Orari della settimana</h3>
  <p class="pa-aiuto">Come nel widget Orari di apertura: <b>9:00-13:00, 15:00-19:00</b> per due turni, <b>Chiuso</b> o vuoto per un giorno libero.</p>
  <div class="pa-orari">
    <?php foreach (PREN_GIORNI as $i => $g): ?><label for="pa-o-<?= $g ?>"><?= h(ucfirst(PREN_GIORNI_NOMI[$i])) ?></label><input id="pa-o-<?= $g ?>" name="orari[<?= $g ?>]" maxlength="120" value="<?= h((string)($r['orari'][$g] ?? '')) ?>"><?php endforeach; ?>
  </div>
  <h3 style="margin-top:1.2rem">Ferie e giorni liberi</h3>
  <p class="pa-aiuto">In questi giorni la persona non si prenota. Le chiusure di tutta l'attività (feste, ferie di agosto) si scrivono una volta sola in Impostazioni.</p>
  <?= pren_admin_chiusure_campi('ferie', (array)$r['ferie']) ?>
  <p class="pa-azioni" style="margin-top:1rem"><button class="btn btn-primary">Salva</button></p>
</form>
<?php if ($r['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm" name="azione" value="elimina" data-conferma="Eliminare questa persona?">Elimina</button></form><?php endif; ?>
<?php else: ?>
<div class="pa-barra"><h2>Persone e orari</h2><a class="btn btn-primary" href="<?= h(pren_admin_url('persone', ['nuova' => 1])) ?>">Aggiungi una persona</a></div>
<p class="pa-aiuto" style="max-width:52rem">Una persona per chi prende appuntamenti (per esempio due parrucchiere). Se lavori da solo, basta una persona: il sito non chiederà "con chi".</p>
<div class="admin-table-wrap"><table class="pa-tabella">
  <thead><tr><th>Nome</th><th>Orari</th><th>Ferie in arrivo</th><th></th></tr></thead>
  <tbody>
  <?php if (!$risorse): ?><tr><td colspan="4"><div class="empty-state"><p>Nessuna persona ancora.</p></div></td></tr><?php endif; ?>
  <?php foreach ($risorse as $x): $prossime = array_filter(pren_chiusure((array)$x['ferie']), fn($c) => $c[1] >= date('Y-m-d')); ?>
  <tr>
    <td><a href="<?= h(pren_admin_url('persone', ['id' => $x['id']])) ?>"><b><?= h((string)$x['nome']) ?></b></a></td>
    <td class="pa-muto"><?php foreach (PREN_GIORNI as $i => $g): ?><?= h(mb_substr(PREN_GIORNI_NOMI[$i], 0, 3)) ?> <?= h((string)($x['orari'][$g] ?? '') ?: 'chiuso') ?><br><?php endforeach; ?></td>
    <td class="pa-muto"><?= $prossime ? h(implode('; ', array_map(fn($c) => ($c[2] !== '' ? $c[2] . ': ' : '') . date('d/m', strtotime($c[0])) . ($c[1] !== $c[0] ? '-' . date('d/m', strtotime($c[1])) : ''), $prossime))) : 'nessuna' ?></td>
    <td><?= $x['attivo'] ? '<span class="badge badge-published">Prende prenotazioni</span>' : '<span class="badge badge-draft">Spenta</span>' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
