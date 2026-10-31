<?php
/**
 * Recensioni > Recensioni: quelle arrivate, da approvare, approvate, rifiutate. Per ognuna: Approva, Rifiuta, Rispondi
 * (la risposta esce sul sito sotto la recensione), Elimina (per esempio se chi l'ha scritta chiede di toglierla).
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Recensioni';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $r = rec_recensione((int)($_POST['id'] ?? 0));
    $filtro = (string)($_POST['filtro'] ?? '');
    if ($r) {
        $ora = date('Y-m-d H:i:s');
        switch ($_POST['azione'] ?? '') {
            case 'approva': db()->prepare('UPDATE ' . tbl('rec_recensioni') . " SET stato = 'approvata', aggiornato = ? WHERE id = ?")->execute([$ora, (int)$r['id']]); flash('success', 'Approvata: adesso esce nel widget Recensioni dal sito.'); break;
            case 'rifiuta': db()->prepare('UPDATE ' . tbl('rec_recensioni') . " SET stato = 'rifiutata', aggiornato = ? WHERE id = ?")->execute([$ora, (int)$r['id']]); flash('success', 'Rifiutata: sul sito non esce.'); break;
            case 'rispondi':
                $t = rec_testo($_POST['risposta'] ?? '', 2000, true);
                db()->prepare('UPDATE ' . tbl('rec_recensioni') . ' SET risposta = ?, risposta_at = ?, aggiornato = ? WHERE id = ?')->execute([$t, $t !== '' ? $ora : null, $ora, (int)$r['id']]);
                flash('success', $t !== '' ? 'Risposta salvata' . ($r['stato'] === 'approvata' ? ': si vede sul sito sotto la recensione.' : ': si vedrà quando approvi la recensione.') : 'Risposta tolta.');
                break;
            case 'elimina': db()->prepare('DELETE FROM ' . tbl('rec_recensioni') . ' WHERE id = ?')->execute([(int)$r['id']]); flash('success', 'Recensione eliminata.'); break;
        }
    }
    rec_admin_vai('recensioni', $filtro !== '' ? ['filtro' => $filtro] : []);
}
$filtri = ['da_approvare' => 'Da approvare', 'approvata' => 'Approvate', 'rifiutata' => 'Rifiutate', 'tutte' => 'Tutte'];
$filtro = array_key_exists($_GET['filtro'] ?? '', $filtri) ? $_GET['filtro'] : 'da_approvare';
$st = db()->prepare('SELECT r.*, i.email FROM ' . tbl('rec_recensioni') . ' r LEFT JOIN ' . tbl('rec_inviti') . ' i ON i.id = r.invito_id' . ($filtro !== 'tutte' ? ' WHERE r.stato = ?' : '') . ' ORDER BY r.creato DESC, r.id DESC LIMIT 300');
$st->execute($filtro !== 'tutte' ? [$filtro] : []);
$elenco = $st->fetchAll();
[, $media, $quante] = rec_approvate(1);
require CMS_ADMIN . '/includes/admin-header.php';
rec_admin_schede('recensioni');
?>
<div class="pa-barra">
  <nav class="pa-schede" style="border:0;margin:0" aria-label="Filtro"><?php foreach ($filtri as $k => $n): ?><a href="<?= h(rec_admin_url('recensioni', ['filtro' => $k])) ?>"<?= $k === $filtro ? ' class="attivo"' : '' ?>><?= h($n) ?></a><?php endforeach; ?></nav>
  <span class="pa-muto"><?= $quante ? 'Sul sito: ' . $quante . ' approvate, media ' . h(str_replace('.', ',', (string)round($media, 1))) . ' su 5' : 'Nessuna recensione approvata ancora' ?></span>
</div>
<?php if (!$elenco): ?><div class="pa-modulo"><p>Niente qui. Le recensioni arrivano dalle email "Com'è andata?": vedi <a href="<?= h(rec_admin_url('inviti')) ?>">Inviti</a>.</p></div><?php endif; ?>
<?php foreach ($elenco as $r): ?>
<article class="pa-modulo" data-recensione="<?= (int)$r['id'] ?>" data-stato="<?= h((string)$r['stato']) ?>">
  <h3><?= rec_stelle_testo((int)$r['stelle']) ?> <?= h((string)$r['nome']) ?> <span class="badge <?= ['da_approvare' => 'badge-scheduled', 'approvata' => 'badge-published', 'rifiutata' => 'badge-cluster'][$r['stato']] ?? '' ?>"><?= h(REC_STATI[$r['stato']] ?? '') ?></span></h3>
  <p class="pa-muto"><?= h(date('d/m/Y H:i', strtotime((string)$r['creato']))) ?><?= $r['servizio'] !== '' ? ' &middot; ' . h((string)$r['servizio']) : '' ?><?= $r['email'] ? ' &middot; ' . h((string)$r['email']) : '' ?></p>
  <div class="rec-testo-pannello"><?= rec_paragrafi((string)$r['testo']) ?></div>
  <div class="pa-azioni" style="margin:.6rem 0">
    <?php foreach (['approva' => ['Approva', 'btn-primary', $r['stato'] !== 'approvata'], 'rifiuta' => ['Rifiuta', 'btn-secondary', $r['stato'] !== 'rifiutata'], 'elimina' => ['Elimina', 'btn-danger', true]] as $az => [$et, $cl, $si]): if (!$si) continue; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filtro" value="<?= h($filtro) ?>"><button class="btn <?= $cl ?> btn-sm" name="azione" value="<?= $az ?>"<?= $az === 'elimina' ? ' data-conferma="Eliminare la recensione? Non si torna indietro."' : '' ?>><?= $et ?></button></form>
    <?php endforeach; ?>
  </div>
  <form method="post" class="rec-rispondi"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filtro" value="<?= h($filtro) ?>">
    <label class="pa-campo">La tua risposta (esce sul sito sotto la recensione)<textarea name="risposta" rows="2" maxlength="2000" placeholder="Grazie Giulia, ti aspettiamo!"><?= h((string)$r['risposta']) ?></textarea></label>
    <p><button class="btn btn-secondary btn-sm" name="azione" value="rispondi">Salva la risposta</button></p>
  </form>
</article>
<?php endforeach; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
