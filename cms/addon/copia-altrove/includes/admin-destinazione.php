<?php
/**
 * Pannello, Impostazioni > Copia altrove (addon copia-altrove): la destinazione, le credenziali (la password non si
 * rivede mai), "Prova la connessione", "Manda una copia adesso", il registro degli invii. Solo amministratore.
 */
require_once __DIR__ . '/copia-altrove.php';
copia_altrove_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $admin = (string)($_SESSION['admin_username'] ?? '');
    try {
        if ($azione === 'salva') {
            copia_altrove_salva($_POST);
            sicurezza_log('impostazioni', $admin, (int)($_SESSION['admin_user_id'] ?? 0), 'copia altrove: destinazione salvata (' . copia_altrove_descrivi(copia_altrove_cfg()) . ')');
            flash('success', 'Destinazione salvata. Adesso premi "Prova la connessione".');
        } elseif ($azione === 'prova') {
            flash('success', copia_altrove_prova());
        } elseif ($azione === 'manda') {
            flash('success', copia_altrove_invia('manuale'));
        } else {
            throw new CopiaAltroveErrore('Azione sconosciuta.');
        }
    } catch (Throwable $e) {
        flash('error', ($azione === 'salva' ? 'Non salvato: ' : 'Non riuscito: ') . $e->getMessage());
    }
    header('Location: ' . copia_altrove_admin_url());
    exit;
}

$c = copia_altrove_cfg();
$disp = copia_altrove_disponibili();
$registro = copia_altrove_registro(30);
$adminTitle = 'Copia del sito altrove';
$js = cms_modulo_url('copia-altrove') . '/assets/copia-altrove.js?v=' . (int)@filemtime(dirname(__DIR__) . '/assets/copia-altrove.js');
require CMS_ADMIN . '/includes/admin-header.php';
?>
<style>.ca-griglia{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.25rem}.ca-griglia>*{min-width:0}#ca-form select,#ca-form input:not([type=checkbox]){width:100%;max-width:100%;box-sizing:border-box}@media(max-width:1100px){.ca-griglia{grid-template-columns:1fr}}.ca-riga{display:grid;grid-template-columns:minmax(0,1fr) 7rem;gap:.6rem}.ca-tab{overflow-x:auto}.ca-ok{color:#15803d;font-weight:600}.ca-ko{color:#b91c1c;font-weight:600}.ca-spunta{display:flex;gap:.5rem;align-items:center;font-weight:400;margin-top:.6rem}[hidden]{display:none!important}</style>
<p style="max-width:62rem">Ogni notte, con il cron, una copia di tutto il sito (database, immagini, file, temi: la stessa di <a href="copia-sito.php">Copia del sito</a>) parte verso un altro spazio.
Se il server del sito si rompe o viene attaccato, la copia è al sicuro altrove. Sulla destinazione restano le ultime copie, quante ne scegli; le altre si cancellano da sole.</p>
<div class="ca-griglia">
  <div class="sc-scatola"><h3>Destinazione</h3><div class="sc-corpo">
    <form method="post" id="ca-form" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="azione" value="salva">
      <label for="ca-tipo">Tipo</label>
      <select id="ca-tipo" name="tipo" data-ca-tipo>
        <?php foreach (COPIA_ALTROVE_TIPI as $k => $et): ?><option value="<?= h($k) ?>"<?= $c['tipo'] === $k ? ' selected' : '' ?>><?= h($et) ?><?= empty($disp[$k]) ? ' (non disponibile su questo server)' : '' ?></option><?php endforeach; ?>
      </select>
      <?php if (empty($disp['sftp'])): ?><p class="hint" id="ca-sftp-manca">SFTP non si può usare: su questo server manca l'estensione <b>ssh2</b> di PHP. Usa FTPS o WebDAV, oppure chiedi al tuo hosting di attivare ssh2.</p><?php endif; ?>
      <div data-ca-solo="ftp ftps sftp">
        <div class="ca-riga"><span><label for="ca-host">Server</label><input type="text" id="ca-host" name="host" value="<?= h($c['tipo'] !== 'webdav' ? $c['host'] : '') ?>" maxlength="253" placeholder="ftp.esempio.it"></span>
          <span><label for="ca-porta">Porta</label><input type="number" id="ca-porta" name="porta" value="<?= $c['tipo'] !== 'webdav' ? (int)$c['porta'] : '' ?>" min="1" max="65535" placeholder="21"></span></div>
        <p class="hint">Il nome del server senza <code>ftp://</code> davanti. Porta: di solito 21 per FTP e FTPS, 22 per SFTP.</p>
      </div>
      <div data-ca-solo="webdav">
        <label for="ca-url">Indirizzo WebDAV</label><input type="url" id="ca-url" name="url" value="<?= h($c['url']) ?>" maxlength="500" placeholder="https://cloud.esempio.it/remote.php/dav/files/mario/">
        <p class="hint">In Nextcloud lo trovi in File, Impostazioni (in basso a sinistra), WebDAV. Serve https.</p>
      </div>
      <label for="ca-utente">Nome utente</label><input type="text" id="ca-utente" name="utente" value="<?= h($c['utente']) ?>" maxlength="190" autocomplete="off">
      <label for="ca-password">Password</label><input type="password" id="ca-password" name="password" value="" maxlength="500" autocomplete="new-password" placeholder="<?= $c['password'] !== '' ? 'Salvata: lasciala vuota per non cambiarla' : '' ?>">
      <p class="hint" id="ca-password-stato"><?= $c['password'] !== '' ? 'La password è salvata cifrata e non si mostra più. Per cambiarla scrivi quella nuova.' : 'Nessuna password salvata.' ?></p>
      <?php if ($c['password'] !== ''): ?><label class="ca-spunta"><input type="checkbox" name="togli_password" value="1"> Togli la password salvata</label><?php endif; ?>
      <label for="ca-cartella">Cartella sulla destinazione</label><input type="text" id="ca-cartella" name="cartella" value="<?= h($c['cartella']) ?>" maxlength="200" placeholder="copie-del-sito">
      <p class="hint">Se non c'è, si crea. Usa una cartella solo per le copie: la pulizia tocca solo i file delle copie, mai gli altri.</p>
      <label for="ca-tenere">Quante copie tenere sulla destinazione</label><input type="number" id="ca-tenere" name="tenere" value="<?= (int)$c['tenere'] ?>" min="1" max="30">
      <label class="ca-spunta"><input type="checkbox" name="attivo" value="1" id="ca-attivo"<?= $c['attivo'] ? ' checked' : '' ?>> Manda una copia ogni notte (con il cron del sito)</label>
      <label class="ca-spunta"><input type="checkbox" name="tieni_locale" value="1"<?= $c['tieni_locale'] ? ' checked' : '' ?>> Tieni la copia anche su questo server (in Copia del sito)</label>
      <button class="btn btn-primary" type="submit" style="margin-top:1rem">Salva la destinazione</button>
    </form>
  </div></div>
  <div>
    <div class="sc-scatola"><h3>Adesso</h3><div class="sc-corpo">
      <p>Destinazione: <b id="ca-descrizione"><?= h(copia_altrove_descrivi($c)) ?></b><?= $c['attivo'] ? ', ogni notte' : ', solo a mano (l\'invio di ogni notte è spento)' ?>.</p>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="azione" value="prova"><button class="btn btn-secondary" type="submit" id="ca-prova"<?= $c['host'] === '' ? ' disabled' : '' ?>>Prova la connessione</button></form>
      <form method="post" style="display:inline" data-conferma="Creare una copia del sito e mandarla adesso? Su un sito grande ci vuole qualche minuto: non chiudere la pagina."><?= csrf_field() ?><input type="hidden" name="azione" value="manda"><button class="btn btn-primary" type="submit" id="ca-manda"<?= $c['host'] === '' ? ' disabled' : '' ?>>Manda una copia adesso</button></form>
      <p class="hint" style="margin-top:.8rem">"Prova la connessione" entra, apre la cartella, scrive un file piccolo e lo cancella: non manda la copia. Se la notte l'invio non riesce, arriva un'email all'indirizzo degli avvisi (Configurazione email).</p>
    </div></div>
    <div class="sc-scatola"><h3>Registro degli invii</h3><div class="sc-corpo ca-tab" style="padding:0">
      <?php if (!$registro): ?><p style="padding:1rem;margin:0;color:var(--text-muted)">Ancora nessun invio.</p><?php else: ?>
      <table class="admin-table" id="ca-registro" style="width:100%"><thead><tr><th>Quando</th><th>Cosa</th><th>Esito</th><th>Dettagli</th></tr></thead><tbody>
      <?php foreach ($registro as $r): ?>
        <tr><td style="white-space:nowrap"><?= h(date('d/m/Y H:i', strtotime($r['quando']))) ?></td>
          <td><?= h(['notte' => 'Copia della notte', 'manuale' => 'Copia mandata a mano', 'prova' => 'Prova della connessione'][$r['origine']] ?? $r['origine']) ?></td>
          <td class="<?= $r['esito'] === 'ok' ? 'ca-ok' : 'ca-ko' ?>"><?= $r['esito'] === 'ok' ? 'Riuscito' : 'Non riuscito' ?></td>
          <td><small><?= h((string)$r['messaggio']) ?><?= $r['file'] !== '' ? '<br>' . h($r['file']) : '' ?><?= (int)$r['secondi'] ? ' &middot; ' . (int)$r['secondi'] . ' s' : '' ?></small></td></tr>
      <?php endforeach; ?></tbody></table><?php endif; ?>
    </div></div>
  </div>
</div>
<script src="<?= h($js) ?>"></script>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
