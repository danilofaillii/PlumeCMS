<?php
/** Recensioni > Impostazioni: dopo quanti giorni, per quanto vale il link, il testo dell'email, Google, gli avvisi. */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Impostazioni delle recensioni';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $google = rec_testo($_POST['google_link'] ?? '', 500);
    if ($google !== '' && !preg_match('~^https://[^\s"<>]+$~', $google)) { flash('error', 'Il link di Google deve cominciare con https://'); rec_admin_vai('impostazioni'); }
    $avviso = rec_testo($_POST['avviso_a'] ?? '', 190);
    if ($avviso !== '' && !filter_var($avviso, FILTER_VALIDATE_EMAIL)) { flash('error', '«' . $avviso . '» non è un indirizzo email valido.'); rec_admin_vai('impostazioni'); }
    save_setting('rec_impostazioni', json_encode([
        'giorni_dopo' => max(0, min(60, (int)($_POST['giorni_dopo'] ?? 2))), 'validita' => max(1, min(365, (int)($_POST['validita'] ?? 30))),
        'da_prenotazioni' => !empty($_POST['da_prenotazioni']), 'google_link' => $google, 'google_da' => max(1, min(5, (int)($_POST['google_da'] ?? 4))),
        'avviso_a' => $avviso, 'avvisa' => !empty($_POST['avvisa']),
        'oggetto' => rec_riga(rec_testo($_POST['oggetto'] ?? '', 120)) ?: REC_PREDEFINITE['oggetto'], 'testo' => rec_testo($_POST['testo'] ?? '', 1000, true) ?: REC_PREDEFINITE['testo'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    flash('success', 'Impostazioni salvate.');
    rec_admin_vai('impostazioni');
}
$imp = rec_impostazioni();
$prenotazioni = cms_modulo('prenotazioni') !== null;
require CMS_ADMIN . '/includes/admin-header.php';
rec_admin_schede('impostazioni');
?>
<form method="post" class="pa-modulo" id="rec-impostazioni"><?= csrf_field() ?>
  <h3>Quando chiedere</h3>
  <div class="pa-campi">
    <label class="pa-spunta pa-largo"><input type="checkbox" name="da_prenotazioni" value="1"<?= $imp['da_prenotazioni'] ? ' checked' : '' ?>> Chiedi da solo dopo ogni appuntamento svolto <?= $prenotazioni ? '' : '<span class="pa-aiuto">(serve l\'addon Prenotazioni acceso: adesso è spento)</span>' ?></label>
    <label>Dopo quanti giorni<input type="number" name="giorni_dopo" min="0" max="60" value="<?= (int)$imp['giorni_dopo'] ?>"><span class="pa-aiuto">Dall'appuntamento (o da quando li aggiungi a mano, se scegli "fra qualche giorno").</span></label>
    <label>Il link vale (giorni)<input type="number" name="validita" min="1" max="365" value="<?= (int)$imp['validita'] ?>"></label>
  </div>
  <h3 style="margin-top:1.2rem">L'email</h3>
  <div class="pa-campi">
    <label class="pa-largo">Oggetto<input name="oggetto" maxlength="120" value="<?= h((string)$imp['oggetto']) ?>"></label>
    <label class="pa-largo">Testo<textarea name="testo" rows="3" maxlength="1000"><?= h((string)$imp['testo']) ?></textarea></label>
  </div>
  <h3 style="margin-top:1.2rem">Google</h3>
  <div class="pa-campi">
    <label class="pa-largo">Il link per lasciare una recensione su Google (facoltativo)<input name="google_link" maxlength="500" value="<?= h((string)$imp['google_link']) ?>" placeholder="https://g.page/r/.../review"><span class="pa-aiuto">Lo trovi nel Profilo dell'attività su Google, "Chiedi recensioni". Dopo una recensione buona il sito propone di lasciarla anche lì.</span></label>
    <label>Proponilo a chi dà almeno<select name="google_da"><?php foreach ([5, 4, 3] as $n): ?><option value="<?= $n ?>"<?= (int)$imp['google_da'] === $n ? ' selected' : '' ?>><?= $n ?> stelle</option><?php endforeach; ?></select></label>
  </div>
  <h3 style="margin-top:1.2rem">Avvisi</h3>
  <div class="pa-campi">
    <label class="pa-spunta pa-largo"><input type="checkbox" name="avvisa" value="1"<?= $imp['avvisa'] ? ' checked' : '' ?>> Mandami un'email a ogni recensione nuova</label>
    <label>A questo indirizzo<input type="email" name="avviso_a" maxlength="190" value="<?= h((string)$imp['avviso_a']) ?>" placeholder="<?= h(posta_notifica_email()) ?>"></label>
  </div>
  <p class="pa-azioni" style="margin-top:1rem"><button class="btn btn-primary">Salva</button></p>
</form>
<p class="pa-aiuto" style="max-width:52rem">Sul sito le recensioni approvate escono con il widget <b>Recensioni dal sito</b> (gruppo Marketing dell'editor). Le stelle non vanno nei dati per Google: Google non le mostra per le recensioni raccolte sul proprio sito.</p>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
