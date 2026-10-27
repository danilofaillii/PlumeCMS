<?php
/**
 * Popup: elenco e scheda. Dove compare, quando, ogni quanto, cosa mostra (titolo, testo, pulsante, modulo o form).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/popup.php';
require_admin(); // sicurezza (ruoli): solo amministratore
$adminTitle = 'Popup';
$id = (int)($_GET['id'] ?? 0);
$nuovo = isset($_GET['nuovo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['azione'] ?? '') === 'elimina') { db()->prepare('DELETE FROM ' . tbl('popup') . ' WHERE id = ?')->execute([$id]); flash('success', 'Popup eliminato.'); header('Location: popup.php'); exit; }
        $id = popup_salva($id, $_POST);
        flash('success', !empty($_POST['attivo']) ? 'Popup salvato e acceso: compare sul sito secondo le regole scelte.' : 'Popup salvato, spento.');
        header('Location: popup.php?id=' . $id);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        header('Location: popup.php?' . ($id ? 'id=' . $id : 'nuovo=1'));
    }
    exit;
}
$p = $id ? popup_leggi($id) : null;
if ($id && !$p) { http_response_code(404); exit('Popup non trovato.'); }
$moduli = editor_moduli_disponibili();
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="assets/css/contenuti.css">
<?php if (!$p && !$nuovo): $tutti = popup_tutti(); ?>
<div class="ct-testa"><p style="color:var(--text-muted);font-size:.88rem;margin:0">Una finestra che compare sopra la pagina: dopo qualche secondo, a una certa profondità, o quando il mouse esce. Chi la chiude non la rivede per i giorni che scegli.</p><a href="popup.php?nuovo=1" class="btn btn-primary">+ Nuovo popup</a></div>
<div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr><th>Popup</th><th>Dove</th><th>Quando</th><th>Mostra</th><th>Stato</th></tr></thead>
    <tbody>
    <?php if (!$tutti): ?><tr><td colspan="5"><div class="empty-state"><p>Nessun popup ancora.</p><a href="popup.php?nuovo=1" class="btn btn-primary">+ Nuovo popup</a></div></td></tr><?php endif; ?>
    <?php foreach ($tutti as $x): ?>
      <tr class="ct-riga">
        <td class="ct-titolo"><a class="ct-nome" href="popup.php?id=<?= $x['id'] ?>"><?= h($x['nome']) ?></a><div class="ct-azioni"><a href="popup.php?id=<?= $x['id'] ?>">Modifica</a></div></td>
        <td class="ct-muto"><?= h(POPUP_DOVE[$x['dove']] ?? $x['dove']) ?></td>
        <td class="ct-muto"><?= $x['innesco'] === 'secondi' ? 'dopo ' . (int)$x['valore'] . ' secondi' : ($x['innesco'] === 'scroll' ? 'al ' . (int)$x['valore'] . '% della pagina' : 'quando il mouse esce') ?> · ogni <?= (int)$x['frequenza_giorni'] ?: 'volta' ?><?= $x['frequenza_giorni'] ? ' giorni' : '' ?></td>
        <td class="ct-muto"><?= h(mb_substr($x['titolo'], 0, 50)) ?><?= $x['modulo'] ? ' + ' . h($moduli[$x['modulo']] ?? $x['modulo']) : '' ?></td>
        <td><span class="badge <?= $x['attivo'] ? 'badge-published' : 'badge-draft' ?>"><?= $x['attivo'] ? 'Acceso' : 'Spento' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: $v = $p ?: ['id' => 0, 'nome' => '', 'attivo' => 0, 'occhiello' => '', 'titolo' => '', 'testo' => '', 'immagine' => '', 'pulsante_testo' => '', 'pulsante_link' => '', 'modulo' => '', 'dove' => 'tutte', 'percorsi' => '', 'innesco' => 'secondi', 'valore' => 8, 'frequenza_giorni' => 7]; ?>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
  <div class="sc-griglia">
    <div>
      <div class="sc-scatola"><h3>Cosa mostra</h3><div class="sc-corpo">
        <label>Nome (nel pannello)</label><input type="text" name="nome" value="<?= h($v['nome']) ?>" required>
        <label>Occhiello</label><input type="text" name="occhiello" value="<?= h($v['occhiello']) ?>">
        <label>Titolo</label><input type="text" name="titolo" value="<?= h($v['titolo']) ?>">
        <label>Testo (HTML semplice: paragrafi, grassetto, link)</label><textarea name="testo" rows="5"><?= h($v['testo']) ?></textarea>
        <label>Immagine (indirizzo, dalla libreria Media)</label><input type="text" name="immagine" value="<?= h($v['immagine']) ?>" placeholder="/wp-content/uploads/…">
        <div class="sc-riga"><div><label>Testo del pulsante</label><input type="text" name="pulsante_testo" value="<?= h($v['pulsante_testo']) ?>"></div><div><label>Link del pulsante</label><input type="text" name="pulsante_link" value="<?= h($v['pulsante_link']) ?>" placeholder="/laser-coaching/"></div></div>
        <label>Modulo dentro il popup</label>
        <select name="modulo"><option value="">Nessuno</option><?php foreach ($moduli as $k => $n): ?><option value="<?= h($k) ?>" <?= $v['modulo'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
      </div></div>
    </div>
    <aside>
      <div class="sc-scatola"><h3>Stato</h3><div class="sc-corpo">
        <label style="display:flex;gap:.5rem;align-items:center;font-weight:400"><input type="checkbox" name="attivo" value="1" <?= $v['attivo'] ? 'checked' : '' ?>> Acceso</label>
        <div style="display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap"><button type="submit" class="btn btn-primary">Salva</button><a class="btn btn-secondary" href="popup.php">Elenco</a></div>
        <?php if ($v['id']): ?><div style="margin-top:.6rem;text-align:right"><button type="submit" class="sc-cestina" name="azione" value="elimina" data-conferma="Eliminare il popup?">Elimina</button></div><?php endif; ?>
      </div></div>
      <div class="sc-scatola"><h3>Dove e quando</h3><div class="sc-corpo">
        <label>Su quali pagine</label>
        <select name="dove"><?php foreach (POPUP_DOVE as $k => $n): ?><option value="<?= $k ?>" <?= $v['dove'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
        <label>Indirizzi (uno per riga, solo per "indirizzi elencati")</label><textarea name="percorsi" rows="3" placeholder="/laser-coaching/&#10;/percorso-doc/"><?= h($v['percorsi']) ?></textarea>
        <label>Quando compare</label>
        <select name="innesco"><?php foreach (POPUP_INNESCHI as $k => $n): ?><option value="<?= $k ?>" <?= $v['innesco'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
        <label>Secondi, oppure percentuale della pagina</label><input type="number" name="valore" value="<?= (int)$v['valore'] ?>" min="0" max="600">
        <label>Ogni quanti giorni la stessa persona lo rivede (0 = sempre)</label><input type="number" name="frequenza_giorni" value="<?= (int)$v['frequenza_giorni'] ?>" min="0" max="365">
      </div></div>
    </aside>
  </div>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
