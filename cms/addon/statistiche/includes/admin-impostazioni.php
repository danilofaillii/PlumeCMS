<?php
/**
 * Statistiche > Impostazioni e privacy (addon, 2.0.0): per quanti giorni tenere i numeri, se rispettare Do Not Track e
 * Global Privacy Control, e il testo pronto per l'informativa privacy del sito.
 */
require_once __DIR__ . '/statistiche.php';

const ST_PERIODI = [90 => '3 mesi', 180 => '6 mesi', 395 => '13 mesi', 730 => '2 anni', 1825 => '5 anni'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $g = (int)($_POST['giorni'] ?? 395);
    if (!isset(ST_PERIODI[$g])) { flash('error', 'Scegli uno dei periodi dell\'elenco.'); }
    else {
        st_impostazioni_salva(['giorni' => $g, 'dnt' => empty($_POST['dnt']) ? 0 : 1]);
        flash('success', 'Impostazioni salvate. Se hai cambiato «Rispetta Do Not Track», aggiorna anche il testo dell\'informativa privacy qui sotto.');
    }
    header('Location: addon-pagina.php?a=statistiche&p=impostazioni');
    exit;
}
$imp = st_impostazioni();
$adminTitle = 'Statistiche: impostazioni e privacy';
require CMS_ADMIN . '/includes/admin-header.php';
?>
<div class="form-grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)">
  <div class="form-card">
    <h3>Impostazioni</h3>
    <form method="post" id="st-impostazioni"><?= csrf_field() ?>
      <div class="form-group"><label for="st-giorni">Tieni i numeri per</label><select id="st-giorni" name="giorni">
        <?php foreach (ST_PERIODI as $k => $et): ?><option value="<?= $k ?>"<?= (int)$imp['giorni'] === $k ? ' selected' : '' ?>><?= h($et) ?></option><?php endforeach; ?>
      </select><p class="hint">I giorni più vecchi si cancellano da soli ogni notte (cron del mattino).</p></div>
      <label style="display:flex;gap:.5rem;align-items:flex-start;margin:.4rem 0 1rem"><input type="checkbox" name="dnt" value="1"<?= $imp['dnt'] ? ' checked' : '' ?> style="margin-top:.25rem"> <span>Rispetta <b>Do Not Track</b> e <b>Global Privacy Control</b>: non contare chi ha chiesto al browser di non essere tracciato. Le statistiche non tracciano nessuno, quindi non è obbligatorio; acceso, i numeri escono un po' più bassi.</span></label>
      <button type="submit" class="btn btn-primary">Salva</button>
    </form>
    <h3 style="margin-top:1.6rem">Cosa si conta e cosa no</h3>
    <ul style="font-size:.9rem;line-height:1.6;padding-left:1.1rem">
      <li>Si conta ogni pagina del sito che si apre davvero in un browser.</li>
      <li>Non si contano: robot e motori di ricerca, anteprime dei link (WhatsApp, Facebook...), pagine non trovate, le anteprime del pannello, e tu (o chiunque sia entrato nel pannello con lo stesso browser).</li>
      <li>Nessun cookie, nessun servizio esterno, nessun indirizzo IP salvato. Per questo non serve il banner dei cookie.</li>
      <li>«Visitatori»: persone diverse in un giorno, riconosciute con un codice anonimo che cambia ogni giorno. Chi torna domani conta di nuovo.</li>
    </ul>
  </div>
  <div class="form-card">
    <h3>Testo per l'informativa privacy</h3>
    <p class="hint" style="margin-top:0">Copia questo paragrafo nella pagina Privacy del sito (Pagine &gt; Privacy policy, nell'editor o nella scheda classica). Se lo usi per un sito di un'attività, fallo rileggere a chi cura la vostra privacy.</p>
    <div class="form-group"><textarea id="st-privacy" readonly rows="18" style="min-height:22rem;font-family:inherit;font-size:.9rem" data-copia-se-stesso title="Clic per copiare"><?= h(st_testo_privacy()) ?></textarea></div>
  </div>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
