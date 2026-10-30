<?php
/**
 * Pannello, Membri > Impostazioni (addon membri): chi può iscriversi, il testo per chi non è entrato, gli avvisi.
 */
require_once __DIR__ . '/membri.php';
membri_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $modo = (string)($_POST['modo'] ?? '');
    if (!isset(MEMBRI_MODI[$modo])) { flash('error', 'Scegli chi può iscriversi.'); header('Location: ' . membri_admin_url('impostazioni')); exit; }
    membri_cfg_salva(['modo' => $modo, 'avviso' => (string)($_POST['avviso'] ?? ''), 'benvenuto' => (string)($_POST['benvenuto'] ?? ''), 'avvisa_admin' => !empty($_POST['avvisa_admin'])]);
    flash('success', 'Impostazioni dell\'area riservata salvate.');
    header('Location: ' . membri_admin_url('impostazioni'));
    exit;
}
$cfg = membri_cfg();
$base = rtrim(cms_url_sito(), '/');
$adminTitle = 'Area riservata: impostazioni';
require CMS_ADMIN . '/includes/admin-header.php';
?>
<style>.mi-modi label{display:flex;gap:.6rem;align-items:flex-start;font-weight:400;margin:.4rem 0}.mi-indirizzi td:first-child{white-space:nowrap}</style>
<div style="max-width:52rem">
<div class="sc-scatola"><h3>Impostazioni</h3><div class="sc-corpo">
<form method="post" id="mi-form"><?= csrf_field() ?>
  <fieldset class="mi-modi" style="border:0;padding:0;margin:0 0 1rem"><legend style="font-weight:600;margin-bottom:.3rem">Chi può iscriversi</legend>
    <label><input type="radio" name="modo" value="aperta"<?= $cfg['modo'] === 'aperta' ? ' checked' : '' ?>> <span><b>Aperta a tutti.</b> Chi si iscrive conferma l'email ed è subito dentro.</span></label>
    <label><input type="radio" name="modo" value="approvazione"<?= $cfg['modo'] === 'approvazione' ? ' checked' : '' ?>> <span><b>Aperta, ma approvo io.</b> Dopo la conferma dell'email l'account aspetta: lo attivi tu dall'elenco dei membri, pulsante Attiva.</span></label>
    <label><input type="radio" name="modo" value="invito"<?= $cfg['modo'] === 'invito' ? ' checked' : '' ?>> <span><b>Solo su invito.</b> La pagina Iscriviti dice che si entra solo su invito; gli account li crei tu dall'elenco dei membri, riquadro Invita.</span></label>
  </fieldset>
  <label for="mi-avviso">Il testo per chi non è entrato</label>
  <textarea id="mi-avviso" name="avviso" rows="3" maxlength="500"><?= h($cfg['avviso']) ?></textarea>
  <p class="hint">Lo legge chi apre una pagina o un articolo "solo per i membri" senza essere entrato. Sotto ci sono i pulsanti Accedi e Iscriviti.</p>
  <label for="mi-benvenuto">Il messaggio in cima all'area riservata (facoltativo)</label>
  <textarea id="mi-benvenuto" name="benvenuto" rows="3" maxlength="1000"><?= h($cfg['benvenuto']) ?></textarea>
  <label style="display:flex;gap:.5rem;align-items:center;font-weight:400;margin-top:.8rem"><input type="checkbox" name="avvisa_admin" value="1"<?= $cfg['avvisa_admin'] ? ' checked' : '' ?>> Mandami un'email per ogni nuova iscrizione (all'indirizzo degli avvisi, Impostazioni &gt; Configurazione email)</label>
  <button type="submit" class="btn btn-primary" style="margin-top:1rem">Salva</button>
</form>
</div></div>
<div class="sc-scatola"><h3>Gli indirizzi dell'area riservata</h3><div class="sc-corpo">
  <p class="hint">Mettili nel menu (Editor, Menu di navigazione) o in un pulsante. Il widget <b>Accesso membri</b> dell'editor mostra da solo "Accedi" a chi è fuori e "Area riservata" a chi è dentro.</p>
  <table class="admin-table mi-indirizzi">
    <tr><td><code><?= h($base) ?>/accedi/</code></td><td>Accesso con email e password</td></tr>
    <tr><td><code><?= h($base) ?>/iscriviti/</code></td><td>Iscrizione (non c'è con "Solo su invito")</td></tr>
    <tr><td><code><?= h($base) ?>/area-riservata/</code></td><td>La pagina del membro: file, contenuti, profilo</td></tr>
    <tr><td><code><?= h($base) ?>/password-dimenticata/</code></td><td>Per scegliere una password nuova</td></tr>
  </table>
  <p class="hint">Se una tua pagina ha lo stesso indirizzo (per esempio <code>/accedi/</code>), con l'addon acceso vince la pagina dell'area riservata.</p>
</div></div>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
