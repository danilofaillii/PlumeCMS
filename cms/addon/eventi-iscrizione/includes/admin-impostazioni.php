<?php
/** Eventi > Impostazioni: a chi arrivano gli avvisi, promemoria, frase in più nelle email, pagina della privacy. */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Impostazioni degli eventi';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $avviso = evi_testo($_POST['avviso_a'] ?? '', 190);
    if ($avviso !== '' && !filter_var($avviso, FILTER_VALIDATE_EMAIL)) { flash('error', '«' . $avviso . '» non è un indirizzo email valido.'); evi_admin_vai('impostazioni'); }
    $privacy = evi_testo($_POST['privacy'] ?? '', 190);
    if (!preg_match('~^(/[^\s"<>]*|https?://[^\s"<>]+)$~', $privacy)) $privacy = EVI_PREDEFINITE['privacy'];
    save_setting('evi_impostazioni', json_encode(['avviso_a' => $avviso, 'avvisa_iscrizioni' => !empty($_POST['avvisa_iscrizioni']), 'promemoria' => !empty($_POST['promemoria']),
        'nota_email' => evi_testo($_POST['nota_email'] ?? '', 600, true), 'privacy' => $privacy], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    flash('success', 'Impostazioni salvate.');
    evi_admin_vai('impostazioni');
}
$imp = evi_impostazioni();
require CMS_ADMIN . '/includes/admin-header.php';
evi_admin_schede('impostazioni');
?>
<form method="post" class="pa-modulo" id="evi-impostazioni"><?= csrf_field() ?>
  <div class="pa-campi">
    <label>Avvisi delle iscrizioni a<input type="email" name="avviso_a" maxlength="190" value="<?= h((string)$imp['avviso_a']) ?>" placeholder="<?= h(posta_notifica_email()) ?>"><span class="pa-aiuto">Vuoto: all'indirizzo degli avvisi del sito.</span></label>
    <label>Pagina dell'informativa privacy<input name="privacy" maxlength="190" value="<?= h((string)$imp['privacy']) ?>"></label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="avvisa_iscrizioni" value="1"<?= $imp['avvisa_iscrizioni'] ? ' checked' : '' ?>> Mandami un'email a ogni iscrizione e cancellazione</label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="promemoria" value="1"<?= $imp['promemoria'] ? ' checked' : '' ?>> Manda agli iscritti il promemoria il giorno prima</label>
    <label class="pa-largo">Una frase in più nelle email di conferma (facoltativa)<textarea name="nota_email" rows="2" maxlength="600" placeholder="Porta con te questa email: all'ingresso diciamo il tuo nome."><?= h((string)$imp['nota_email']) ?></textarea></label>
  </div>
  <p class="pa-azioni" style="margin-top:1rem"><button class="btn btn-primary">Salva</button></p>
</form>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
