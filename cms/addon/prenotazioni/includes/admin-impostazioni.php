<?php
/**
 * Prenotazioni > Impostazioni: anticipo minimo e massimo, passo degli orari, limite per annullare, conferma a mano,
 * scelta della persona, avvisi, promemoria, indirizzo, chiusure di tutta l'attività. Tutto in pren_impostazioni (JSON).
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Impostazioni delle prenotazioni';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $avviso = pren_testo($_POST['avviso_a'] ?? '', 190);
    if ($avviso !== '' && !filter_var($avviso, FILTER_VALIDATE_EMAIL)) { flash('error', '«' . $avviso . '» non è un indirizzo email valido.'); pren_admin_vai('impostazioni'); }
    $privacy = pren_testo($_POST['privacy'] ?? '', 190);
    if ($privacy !== '' && !preg_match('~^(/[^\s"<>]*|https?://[^\s"<>]+)$~', $privacy)) $privacy = PREN_PREDEFINITE['privacy'];
    $imp = [
        'anticipo_ore' => max(0, min(720, (int)($_POST['anticipo_ore'] ?? 2))),
        'anticipo_giorni' => max(1, min(365, (int)($_POST['anticipo_giorni'] ?? 60))),
        'passo' => in_array((int)($_POST['passo'] ?? 30), [10, 15, 20, 30, 45, 60, 90, 120], true) ? (int)$_POST['passo'] : 30,
        'limite_ore' => max(0, min(720, (int)($_POST['limite_ore'] ?? 24))),
        'da_confermare' => !empty($_POST['da_confermare']),
        'scegli_persona' => !empty($_POST['scegli_persona']),
        'promemoria' => !empty($_POST['promemoria']),
        'avviso_a' => $avviso,
        'indirizzo' => pren_testo($_POST['indirizzo'] ?? '', 200),
        'nota_email' => pren_testo($_POST['nota_email'] ?? '', 600, true),
        'privacy' => $privacy ?: PREN_PREDEFINITE['privacy'],
        'chiusure' => pren_admin_chiusure_leggi($_POST['chiusure'] ?? []),
    ];
    save_setting('pren_impostazioni', json_encode($imp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    flash('success', 'Impostazioni salvate.');
    pren_admin_vai('impostazioni');
}
$imp = pren_impostazioni();
require CMS_ADMIN . '/includes/admin-header.php';
pren_admin_schede('impostazioni');
?>
<form method="post" class="pa-modulo" id="pa-impostazioni"><?= csrf_field() ?>
  <h3>Quando si può prenotare</h3>
  <div class="pa-campi">
    <label>Anticipo minimo (ore)<input type="number" name="anticipo_ore" min="0" max="720" value="<?= (int)$imp['anticipo_ore'] ?>"><span class="pa-aiuto">Con 2, alle 10 il primo orario prenotabile è a mezzogiorno.</span></label>
    <label>Fino a quanti giorni avanti<input type="number" name="anticipo_giorni" min="1" max="365" value="<?= (int)$imp['anticipo_giorni'] ?>"></label>
    <label>Un orario ogni (minuti)<select name="passo"><?php foreach ([10, 15, 20, 30, 45, 60, 90, 120] as $m): ?><option value="<?= $m ?>"<?= (int)$imp['passo'] === $m ? ' selected' : '' ?>><?= $m ?></option><?php endforeach; ?></select><span class="pa-aiuto">Gli orari che il sito propone: 9:00, 9:30, 10:00...</span></label>
    <label>Annullare o spostare dal sito fino a (ore prima)<input type="number" name="limite_ore" min="0" max="720" value="<?= (int)$imp['limite_ore'] ?>"></label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="da_confermare" value="1"<?= $imp['da_confermare'] ? ' checked' : '' ?>> Confermo io ogni prenotazione a mano (il cliente riceve prima "richiesta ricevuta", poi la conferma)</label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="scegli_persona" value="1"<?= $imp['scegli_persona'] ? ' checked' : '' ?>> Il cliente può scegliere con chi (se le persone sono più di una)</label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="promemoria" value="1"<?= $imp['promemoria'] ? ' checked' : '' ?>> Manda il promemoria il giorno prima</label>
  </div>
  <h3 style="margin-top:1.2rem">Email</h3>
  <div class="pa-campi">
    <label>Avvisi delle prenotazioni a<input type="email" name="avviso_a" maxlength="190" value="<?= h((string)$imp['avviso_a']) ?>" placeholder="<?= h(posta_notifica_email()) ?>"><span class="pa-aiuto">Vuoto: all'indirizzo degli avvisi del sito (Impostazioni &gt; Configurazione email).</span></label>
    <label>Indirizzo dell'attività<input name="indirizzo" maxlength="200" value="<?= h((string)$imp['indirizzo']) ?>" placeholder="Via dell'Indipendenza 10, Bologna"><span class="pa-aiuto">Esce nelle email e nel calendario del cliente.</span></label>
    <label class="pa-largo">Una frase in più nelle email di conferma (facoltativa)<textarea name="nota_email" rows="2" maxlength="600" placeholder="Se arrivi in ritardo di più di 15 minuti potremmo non riuscire a fare il servizio."><?= h((string)$imp['nota_email']) ?></textarea></label>
    <label>Pagina dell'informativa privacy<input name="privacy" maxlength="190" value="<?= h((string)$imp['privacy']) ?>"></label>
  </div>
  <h3 style="margin-top:1.2rem">Chiusure di tutta l'attività</h3>
  <p class="pa-aiuto">Feste, ferie, inventario: in questi giorni nessuno si può prenotare.</p>
  <?= pren_admin_chiusure_campi('chiusure', (array)$imp['chiusure']) ?>
  <p class="pa-azioni" style="margin-top:1rem"><button class="btn btn-primary">Salva</button></p>
</form>
<p class="pa-aiuto" style="max-width:52rem">Il promemoria e il passaggio degli appuntamenti a "svolto" li fa il cron frequente (quello della newsletter, ogni 5 minuti): controlla in Impostazioni &gt; Log cron che giri.</p>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
