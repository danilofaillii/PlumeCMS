<?php
/**
 * Pannello, Impostazioni > Copia altrove (addon copia-altrove): la destinazione, le credenziali (password e segreti non si
 * rivedono mai), il collegamento con Google Drive o Dropbox (2.1, OAuth 2: passi per creare l'app, redirect URI da copiare,
 * "Salva e collega", "Scollega"), "Prova la connessione", "Manda una copia adesso", il registro degli invii. Solo amministratore.
 */
require_once __DIR__ . '/copia-altrove.php';
copia_altrove_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $admin = (string)($_SESSION['admin_username'] ?? '');
    $dopo = copia_altrove_admin_url();
    try {
        if ($azione === 'salva' || $azione === 'collega') {
            copia_altrove_salva($_POST);
            $c = copia_altrove_cfg();
            sicurezza_log('impostazioni', $admin, (int)($_SESSION['admin_user_id'] ?? 0), 'copia altrove: destinazione salvata (' . copia_altrove_descrivi($c) . ')');
            if ($azione === 'collega' && copia_altrove_e_cloud($c['tipo'])) {
                // Il browser va alla pagina del servizio con una navigazione nuova (la pagina qui sotto), non con il redirect
                // di questo POST: la CSP del pannello (form-action 'self') fermerebbe un redirect verso un altro sito.
                copia_altrove_oauth_inizia($c['tipo']);
                $dopo = copia_altrove_admin_url(['autorizza' => '1']);
            } else {
                flash('success', copia_altrove_configurata($c) ? 'Destinazione salvata. Adesso premi "Prova la connessione".' : (copia_altrove_e_cloud($c['tipo']) ? 'Salvato. Adesso premi "Salva e collega" per dare il permesso a ' . COPIA_ALTROVE_TIPI[$c['tipo']] . '.' : 'Destinazione salvata.'));
            }
        } elseif ($azione === 'scollega') {
            $msg = copia_altrove_scollega((string)($_POST['servizio'] ?? ''));
            sicurezza_log('impostazioni', $admin, (int)($_SESSION['admin_user_id'] ?? 0), 'copia altrove: scollegato ' . (string)($_POST['servizio'] ?? ''));
            flash('success', $msg);
        } elseif ($azione === 'prova') {
            flash('success', copia_altrove_prova());
        } elseif ($azione === 'manda') {
            flash('success', copia_altrove_invia('manuale'));
        } else {
            throw new CopiaAltroveErrore('Azione sconosciuta.');
        }
    } catch (Throwable $e) {
        flash('error', (in_array($azione, ['salva', 'collega'], true) ? 'Non salvato: ' : 'Non riuscito: ') . $e->getMessage());
    }
    header('Location: ' . $dopo);
    exit;
}

$c = copia_altrove_cfg();
$disp = copia_altrove_disponibili();
$registro = copia_altrove_registro(30);
$pronta = copia_altrove_configurata($c);
$vaiServizio = !empty($_GET['autorizza']) ? copia_altrove_oauth_in_corso() : '';
$redirect = copia_altrove_redirect_uri();
$adminTitle = 'Copia del sito altrove';
$js = cms_modulo_url('copia-altrove') . '/assets/copia-altrove.js?v=' . (int)@filemtime(dirname(__DIR__) . '/assets/copia-altrove.js');
require CMS_ADMIN . '/includes/admin-header.php';
$passi = [
    'gdrive' => [
        'Apri <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> ed entra con l\'account Google dove vuoi le copie.',
        'In alto premi il nome del progetto (o "Seleziona un progetto"), poi <b>Nuovo progetto</b>. Nome: "Copie del sito". Premi <b>Crea</b> e scegli il progetto appena creato.',
        'Nel menu a sinistra: <b>API e servizi</b>, <b>Libreria</b>. Cerca "Google Drive API", aprila e premi <b>Abilita</b>.',
        'Nel menu: <b>Google Auth Platform</b> (prima si chiamava "Schermata consenso OAuth"). Premi <b>Inizia</b>: nome dell\'app (per esempio "Copie del sito"), la tua email, Pubblico <b>Esterno</b>, di nuovo la tua email, accetta e premi <b>Crea</b>.',
        'Sempre lì, in <b>Pubblico</b>, premi <b>Pubblica app</b> e conferma. Serve: un\'app lasciata "in test" perde il permesso dopo 7 giorni. Non serve la verifica di Google, perché l\'app chiede solo di vedere i file che crea lei.',
        'In <b>Client</b>, premi <b>Crea client</b>. Tipo di applicazione: <b>Applicazione web</b>. In <b>URI di reindirizzamento autorizzati</b> premi <b>Aggiungi URI</b> e incolla l\'indirizzo qui sotto. Premi <b>Crea</b>.',
        'Google mostra <b>ID client</b> e <b>Client secret</b>: copiali nei due campi qui sotto e premi <b>Salva e collega con Google Drive</b>. Scegli il tuo account e premi <b>Continua</b> (o Consenti): torni qui da solo.',
    ],
    'dropbox' => [
        'Apri <a href="https://www.dropbox.com/developers/apps" target="_blank" rel="noopener">dropbox.com/developers/apps</a> ed entra con il tuo account Dropbox.',
        'Premi <b>Create app</b>. Scegli <b>Scoped access</b>, poi <b>App folder</b> (l\'app vede solo la sua cartella, non il resto del tuo Dropbox). Dai un nome, per esempio "copie-sito-" e il nome del tuo sito, e premi <b>Create app</b>.',
        'Nella scheda <b>Permissions</b> spunta <b>files.metadata.read</b>, <b>files.content.write</b> e <b>files.content.read</b> (account_info.read c\'è già). Premi <b>Submit</b> in fondo.',
        'Nella scheda <b>Settings</b>, alla voce <b>Redirect URIs</b>, incolla l\'indirizzo qui sotto e premi <b>Add</b>.',
        'Sempre in Settings trovi <b>App key</b> e <b>App secret</b> (premi Show): copiali nei due campi qui sotto e premi <b>Salva e collega con Dropbox</b>. Premi <b>Consenti</b> (Allow): torni qui da solo.',
        'Le copie arrivano nel tuo Dropbox in <b>Applicazioni</b>, cartella con il nome dell\'app.',
    ],
];
$campi = ['gdrive' => ['ID client', 'Client secret', '1234567890-abc...apps.googleusercontent.com'], 'dropbox' => ['App key', 'App secret', 'per esempio a1b2c3d4e5f6g7h']];
?>
<style>.ca-griglia{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.25rem}.ca-griglia>*{min-width:0}#ca-form select,#ca-form input:not([type=checkbox]){width:100%;max-width:100%;box-sizing:border-box}@media(max-width:1100px){.ca-griglia{grid-template-columns:1fr}}.ca-riga{display:grid;grid-template-columns:minmax(0,1fr) 7rem;gap:.6rem}.ca-tab{overflow-x:auto}.ca-ok{color:#15803d;font-weight:600}.ca-ko{color:#b91c1c;font-weight:600}.ca-spunta{display:flex;gap:.5rem;align-items:center;font-weight:400;margin-top:.6rem}[hidden]{display:none!important}
.ca-cloud{border:1px solid var(--border);border-radius:10px;padding:.9rem 1rem;margin:.8rem 0;background:var(--bg)}.ca-cloud h4{margin:0 0 .4rem;font-size:.95rem}.ca-passi{margin:.4rem 0 .6rem;padding-left:1.3rem;font-size:.86rem;line-height:1.55}.ca-passi li{margin:.3rem 0}.ca-passi a{word-break:break-all}
.ca-stato{display:flex;gap:.5rem;align-items:flex-start;padding:.6rem .8rem;border-radius:8px;font-size:.88rem;margin:.2rem 0 .6rem;word-break:break-word}.ca-stato.si{background:var(--sage-light);color:var(--successo-testo)}.ca-stato.no{background:var(--mustard-light);color:var(--attenzione-testo)}
.ca-redirect{display:flex;gap:.4rem}.ca-redirect input{font-family:monospace;font-size:.8rem}.ca-vai{border:1px solid var(--coral);border-radius:12px;padding:1rem 1.2rem;margin:0 0 1.2rem;background:var(--surface)}.ca-azioni{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}</style>
<?php if ($vaiServizio !== ''): $nomeServizio = COPIA_ALTROVE_TIPI[$c['tipo']] ?? 'il servizio'; ?>
<div class="ca-vai" id="ca-vai" role="status"><b>Ti porto alla pagina di <?= h($nomeServizio) ?></b> per dare il permesso: scegli il tuo account e premi Consenti (o Continua). Poi torni qui da solo.
  <p style="margin:.6rem 0 0"><a class="btn btn-primary" id="ca-vai-link" href="<?= h($vaiServizio) ?>">Vai a <?= h($nomeServizio) ?></a> <span class="hint">Se la pagina non si apre da sola, premi il pulsante.</span></p></div>
<script<?= nonce_attr() ?>>setTimeout(function(){var a=document.getElementById('ca-vai-link');if(a)location.assign(a.href)},700);</script>
<?php endif; ?>
<p style="max-width:62rem">Ogni notte, con il cron, una copia di tutto il sito (database, immagini, file, temi: la stessa di <a href="copia-sito.php">Copia del sito</a>) parte verso un altro spazio.
Se il server del sito si rompe o viene attaccato, la copia è al sicuro altrove. Sulla destinazione restano le ultime copie, quante ne scegli; le altre si cancellano da sole.</p>
<div class="ca-griglia">
  <div class="sc-scatola"><h3>Destinazione</h3><div class="sc-corpo">
    <form method="post" id="ca-form" autocomplete="off"><?= csrf_field() ?>
      <label for="ca-tipo">Dove mandare le copie</label>
      <select id="ca-tipo" name="tipo" data-ca-tipo>
        <?php foreach (COPIA_ALTROVE_TIPI as $k => $et): ?><option value="<?= h($k) ?>"<?= $c['tipo'] === $k ? ' selected' : '' ?>><?= h($et) ?><?= empty($disp[$k]) ? ' (non disponibile su questo server)' : '' ?></option><?php endforeach; ?>
      </select>
      <?php if (empty($disp['sftp'])): ?><p class="hint" id="ca-sftp-manca">SFTP non si può usare: su questo server manca l'estensione <b>ssh2</b> di PHP. Usa FTPS o WebDAV, oppure chiedi al tuo hosting di attivare ssh2.</p><?php endif; ?>
      <?php foreach (COPIA_ALTROVE_CLOUD as $s => $nomeS): $o = copia_altrove_oauth($c, $s); $collegato = $o['refresh'] !== ''; ?>
      <div class="ca-cloud" data-ca-solo="<?= h($s) ?>" id="ca-<?= h($s) ?>">
        <h4><?= h($nomeS) ?></h4>
        <div class="ca-stato <?= $collegato ? 'si' : 'no' ?>" id="ca-<?= h($s) ?>-stato"><?= $collegato
            ? '&#10003; Collegato' . ($o['account'] !== '' ? ' come <b>' . h($o['account']) . '</b>' : '') . ($o['collegato'] !== '' ? ' dal ' . h(date('d/m/Y', strtotime($o['collegato']))) : '') . '. Il permesso si rinnova da solo.'
            : 'Non ancora collegato. Ci vogliono circa 10 minuti, una volta sola: segui i passi qui sotto.' ?></div>
        <details<?= $collegato ? '' : ' open' ?>><summary>Come si crea l'app di <?= h($nomeS) ?>, passo per passo</summary>
          <ol class="ca-passi"><?php foreach ($passi[$s] as $passo): ?><li><?= $passo ?></li><?php endforeach; ?></ol>
        </details>
        <label for="ca-<?= h($s) ?>-redirect">Indirizzo da incollare (<?= $s === 'gdrive' ? 'URI di reindirizzamento' : 'Redirect URI' ?>)</label>
        <div class="ca-redirect"><input type="text" id="ca-<?= h($s) ?>-redirect" value="<?= h($redirect) ?>" readonly data-copia-se-stesso title="Clic per copiare"></div>
        <p class="hint">Fai clic sull'indirizzo: si copia da solo. Va incollato identico, con <code>https://</code> davanti.</p>
        <label for="ca-<?= h($s) ?>-client_id"><?= h($campi[$s][0]) ?></label><input type="text" id="ca-<?= h($s) ?>-client_id" name="<?= h($s) ?>_client_id" value="<?= h($o['client_id']) ?>" maxlength="200" placeholder="<?= h($campi[$s][2]) ?>" spellcheck="false">
        <label for="ca-<?= h($s) ?>-segreto"><?= h($campi[$s][1]) ?></label><input type="password" id="ca-<?= h($s) ?>-segreto" name="<?= h($s) ?>_segreto" value="" maxlength="200" autocomplete="new-password" placeholder="<?= $o['segreto'] !== '' ? 'Salvato: lascialo vuoto per non cambiarlo' : '' ?>">
        <p class="hint"><?= $o['segreto'] !== '' ? 'Il segreto è salvato cifrato e non si mostra più.' : 'Il segreto si salva cifrato e non si mostra più.' ?></p>
        <button class="btn btn-primary" type="submit" name="azione" value="collega" id="ca-<?= h($s) ?>-collega" style="margin-top:.4rem"><?= $collegato ? 'Salva e collega di nuovo' : 'Salva e collega con ' . h($nomeS) ?></button>
      </div>
      <?php endforeach; ?>
      <div data-ca-solo="ftp ftps sftp">
        <div class="ca-riga"><span><label for="ca-host">Server</label><input type="text" id="ca-host" name="host" value="<?= h(in_array($c['tipo'], ['ftp', 'ftps', 'sftp'], true) ? $c['host'] : '') ?>" maxlength="253" placeholder="ftp.esempio.it"></span>
          <span><label for="ca-porta">Porta</label><input type="number" id="ca-porta" name="porta" value="<?= in_array($c['tipo'], ['ftp', 'ftps', 'sftp'], true) ? (int)$c['porta'] : '' ?>" min="1" max="65535" placeholder="21"></span></div>
        <p class="hint">Il nome del server senza <code>ftp://</code> davanti. Porta: di solito 21 per FTP e FTPS, 22 per SFTP.</p>
      </div>
      <div data-ca-solo="webdav">
        <label for="ca-url">Indirizzo WebDAV</label><input type="url" id="ca-url" name="url" value="<?= h($c['url']) ?>" maxlength="500" placeholder="https://cloud.esempio.it/remote.php/dav/files/mario/">
        <p class="hint">In Nextcloud lo trovi in File, Impostazioni (in basso a sinistra), WebDAV. Serve https.</p>
      </div>
      <div data-ca-solo="ftp ftps sftp webdav">
        <label for="ca-utente">Nome utente</label><input type="text" id="ca-utente" name="utente" value="<?= h($c['utente']) ?>" maxlength="190" autocomplete="off">
        <label for="ca-password">Password</label><input type="password" id="ca-password" name="password" value="" maxlength="500" autocomplete="new-password" placeholder="<?= $c['password'] !== '' ? 'Salvata: lasciala vuota per non cambiarla' : '' ?>">
        <p class="hint" id="ca-password-stato"><?= $c['password'] !== '' ? 'La password è salvata cifrata e non si mostra più. Per cambiarla scrivi quella nuova.' : 'Nessuna password salvata.' ?></p>
        <?php if ($c['password'] !== ''): ?><label class="ca-spunta"><input type="checkbox" name="togli_password" value="1"> Togli la password salvata</label><?php endif; ?>
      </div>
      <label for="ca-cartella">Cartella sulla destinazione</label><input type="text" id="ca-cartella" name="cartella" value="<?= h($c['cartella']) ?>" maxlength="200" placeholder="copie-del-sito">
      <p class="hint">Se non c'è, si crea. Usa una cartella solo per le copie: la pulizia tocca solo i file delle copie, mai gli altri.</p>
      <label for="ca-tenere">Quante copie tenere sulla destinazione</label><input type="number" id="ca-tenere" name="tenere" value="<?= (int)$c['tenere'] ?>" min="1" max="30">
      <label class="ca-spunta"><input type="checkbox" name="attivo" value="1" id="ca-attivo"<?= $c['attivo'] ? ' checked' : '' ?>> Manda una copia ogni notte (con il cron del sito)</label>
      <label class="ca-spunta"><input type="checkbox" name="tieni_locale" value="1"<?= $c['tieni_locale'] ? ' checked' : '' ?>> Tieni la copia anche su questo server (in Copia del sito)</label>
      <button class="btn btn-secondary" type="submit" name="azione" value="salva" id="ca-salva" style="margin-top:1rem">Salva la destinazione</button>
    </form>
  </div></div>
  <div>
    <div class="sc-scatola"><h3>Adesso</h3><div class="sc-corpo">
      <p>Destinazione: <b id="ca-descrizione"><?= h(copia_altrove_descrivi($c)) ?></b><?= $c['attivo'] ? ', ogni notte' : ', solo a mano (l\'invio di ogni notte è spento)' ?>.</p>
      <div class="ca-azioni">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="prova"><button class="btn btn-secondary" type="submit" id="ca-prova"<?= $pronta ? '' : ' disabled' ?>>Prova la connessione</button></form>
      <form method="post" data-conferma="Creare una copia del sito e mandarla adesso? Su un sito grande ci vuole qualche minuto: non chiudere la pagina."><?= csrf_field() ?><input type="hidden" name="azione" value="manda"><button class="btn btn-primary" type="submit" id="ca-manda"<?= $pronta ? '' : ' disabled' ?>>Manda una copia adesso</button></form>
      <?php if (copia_altrove_e_cloud($c['tipo']) && copia_altrove_collegato($c, $c['tipo'])): ?>
      <form method="post" data-conferma="Scollegare <?= h(COPIA_ALTROVE_TIPI[$c['tipo']]) ?>? Le copie di ogni notte si fermano; quelle già mandate restano dove sono."><?= csrf_field() ?><input type="hidden" name="azione" value="scollega"><input type="hidden" name="servizio" value="<?= h($c['tipo']) ?>"><button class="btn btn-secondary" type="submit" id="ca-scollega">Scollega <?= h(COPIA_ALTROVE_TIPI[$c['tipo']]) ?></button></form>
      <?php endif; ?>
      </div>
      <p class="hint" style="margin-top:.8rem">"Prova la connessione" entra, apre la cartella, scrive un file piccolo e lo cancella: non manda la copia. Se la notte l'invio non riesce, arriva un'email all'indirizzo degli avvisi (Configurazione email).</p>
    </div></div>
    <div class="sc-scatola"><h3>Registro degli invii</h3><div class="sc-corpo ca-tab" style="padding:0">
      <?php if (!$registro): ?><p style="padding:1rem;margin:0;color:var(--text-muted)">Ancora nessun invio.</p><?php else: ?>
      <div class="ad-tabella"><table class="admin-table" id="ca-registro" style="width:100%"><thead><tr><th>Quando</th><th>Cosa</th><th>Esito</th><th>Dettagli</th></tr></thead><tbody>
      <?php foreach ($registro as $r): ?>
        <tr><td style="white-space:nowrap"><?= h(date('d/m/Y H:i', strtotime($r['quando']))) ?></td>
          <td><?= h(['notte' => 'Copia della notte', 'manuale' => 'Copia mandata a mano', 'prova' => 'Prova della connessione'][$r['origine']] ?? $r['origine']) ?></td>
          <td class="<?= $r['esito'] === 'ok' ? 'ca-ok' : 'ca-ko' ?>"><?= $r['esito'] === 'ok' ? 'Riuscito' : 'Non riuscito' ?></td>
          <td><small><?= h((string)$r['messaggio']) ?><?= $r['file'] !== '' ? '<br>' . h($r['file']) : '' ?><?= (int)$r['secondi'] ? ' &middot; ' . (int)$r['secondi'] . ' s' : '' ?></small></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div>
  </div>
</div>
<script src="<?= h($js) ?>"></script>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
