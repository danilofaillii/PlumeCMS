<?php
/**
 * Impostazioni: la posta in uscita (server SMTP, mittente, indirizzo degli avvisi).
 * Il sito pubblico legge sempre da questo pannello: non c'è più un motore da scegliere.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/sito.php';
require_once __DIR__ . '/../includes/posta.php';
require_once __DIR__ . '/../includes/firma.php';
require_admin();

$adminTitle = 'Impostazioni';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'save_posta') {
        $vecchia = json_decode((string)get_setting('posta_smtp', ''), true) ?: [];
        $pw = (string)($_POST['smtp_password'] ?? '');
        $posta = [
            'host' => trim($_POST['smtp_host'] ?? ''),
            'porta' => (int)($_POST['smtp_porta'] ?? 465) ?: 465,
            'sicurezza' => in_array($_POST['smtp_sicurezza'] ?? '', ['ssl', 'tls', 'nessuna'], true) ? $_POST['smtp_sicurezza'] : 'ssl',
            'utente' => trim($_POST['smtp_utente'] ?? ''),
            // Il campo mostra asterischi: se non lo tocchi, resta la password di prima.
            'password' => ($pw === '' || $pw === '********') ? ($vecchia['password'] ?? '') : $pw,
            'mittente' => trim($_POST['smtp_mittente'] ?? ''),
            'nome' => trim($_POST['smtp_nome'] ?? '') ?: (string)cms_impostazione('sito_nome', CMS_NOME),
        ];
        save_setting('posta_smtp', json_encode($posta, JSON_UNESCAPED_UNICODE));
        save_setting('notifica_email', trim($_POST['notification_email'] ?? ''));
        flash('success', $posta['host'] ? 'Posta salvata: si spedisce da ' . $posta['host'] . '.' : 'Posta salvata: senza server SMTP si usa mail() di PHP.');
    } elseif (($_POST['action'] ?? '') === 'save_firma') {
        // 1.8.0: la frase in fondo al sito (includes/firma.php).
        $f = firma_salva(['mostra' => !empty($_POST['firma_mostra']), 'testo' => $_POST['firma_testo'] ?? '', 'url' => $_POST['firma_url'] ?? '']);
        flash('success', $f['mostra'] && $f['testo'] !== '' ? 'Frase in fondo al sito salvata: «' . $f['testo'] . '».' : 'Frase in fondo al sito spenta: sul sito non compare più.');
    } elseif (($_POST['action'] ?? '') === 'prova_posta') {
        $a = trim($_POST['prova_a'] ?? '');
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Indirizzo di prova non valido.');
        } elseif (posta_invia($a, 'Prova di spedizione dal pannello', "Se leggi questa email, il pannello di " . (parse_url(cms_url_sito(), PHP_URL_HOST) ?: CMS_NOME) . " spedisce correttamente.\n\nInviata il " . date('d/m/Y \a\l\l\e H:i') . '.')) {
            flash('success', "Email di prova spedita a $a. Controlla anche nello spam.");
        } else {
            flash('error', 'Spedizione non riuscita: ' . posta_errore());
        }
    }
    header('Location: impostazioni.php');
    exit;
}

$smtp = (json_decode((string)get_setting('posta_smtp', ''), true) ?: []) + ['host' => '', 'porta' => 465, 'sicurezza' => 'ssl', 'utente' => '', 'password' => '', 'mittente' => '', 'nome' => (string)cms_impostazione('sito_nome', CMS_NOME)];
$notifica = posta_notifica_email();
$firma = firma_valori();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="max-width:720px">
  <div class="form-card">
    <h3>Posta in uscita</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem">
      Da qui partono l'email del mattino, gli avvisi dei form, la newsletter e le conferme di iscrizione.
      I dati sono quelli della casella email che spedisce, li trovi nel pannello dell'hosting (cPanel, Account email, "Connetti dispositivi").
      Se lasci vuoto il server, il pannello prova con mail() di PHP.
    </p>
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem" id="posta-brevo-smtp">
      Con <b>Brevo</b> via SMTP: server <code>smtp-relay.brevo.com</code>, porta <code>587</code>, sicurezza STARTTLS, utente il Login della pagina SMTP e API di Brevo, password una chiave SMTP.
      Per Brevo, Amazon SES o Mailgun con le API, per il controllo del dominio e per i rimbalzi: <a href="consegna.php">Consegna delle email</a>.
      <?php $servAttivo = consegna_servizio_attivo(); if ($servAttivo !== ''): ?><br><b>Attenzione:</b> adesso le email partono da <?= h(CONSEGNA_SERVIZI[$servAttivo]) ?> (Consegna delle email, Come partono): i dati SMTP qui sotto non si usano.<?php endif; ?>
    </p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_posta">
      <div class="form-group"><label for="smtp_host">Server SMTP</label><input type="text" id="smtp_host" name="smtp_host" value="<?= h($smtp['host']) ?>" placeholder="mail.<?= h((string)preg_replace('/^www\./', '', (string)parse_url(cms_url_sito(), PHP_URL_HOST))) ?>" autocomplete="off"></div>
      <div class="form-group"><label for="smtp_porta">Porta</label><input type="number" id="smtp_porta" name="smtp_porta" value="<?= (int)$smtp['porta'] ?>"></div>
      <div class="form-group"><label for="smtp_sicurezza">Sicurezza</label>
        <select id="smtp_sicurezza" name="smtp_sicurezza">
          <option value="ssl" <?= $smtp['sicurezza'] === 'ssl' ? 'selected' : '' ?>>SSL (di solito porta 465)</option>
          <option value="tls" <?= $smtp['sicurezza'] === 'tls' ? 'selected' : '' ?>>STARTTLS (di solito porta 587)</option>
          <option value="nessuna" <?= $smtp['sicurezza'] === 'nessuna' ? 'selected' : '' ?>>Nessuna</option>
        </select></div>
      <div class="form-group"><label for="smtp_utente">Utente (l'indirizzo email completo)</label><input type="text" id="smtp_utente" name="smtp_utente" value="<?= h($smtp['utente']) ?>" placeholder="posta@<?= h((string)preg_replace('/^www\./', '', (string)parse_url(cms_url_sito(), PHP_URL_HOST))) ?>" autocomplete="off"></div>
      <div class="form-group"><label for="smtp_password">Password della casella</label><input type="password" id="smtp_password" name="smtp_password" value="<?= $smtp['password'] !== '' ? '********' : '' ?>" autocomplete="new-password">
        <div class="hint">Resta salvata nel database del pannello. Gli asterischi vogliono dire che c'è già: se non la cambi, resta quella.</div></div>
      <div class="form-group"><label for="smtp_mittente">Indirizzo del mittente</label><input type="text" id="smtp_mittente" name="smtp_mittente" value="<?= h($smtp['mittente']) ?>" placeholder="uguale all'utente"></div>
      <div class="form-group"><label for="smtp_nome">Nome del mittente</label><input type="text" id="smtp_nome" name="smtp_nome" value="<?= h($smtp['nome']) ?>"></div>
      <div class="form-group"><label for="notification_email">Dove arrivano l'email del mattino e gli avvisi dei form</label><input type="text" id="notification_email" name="notification_email" value="<?= h($notifica) ?>" placeholder="tuaemail@esempio.it"></div>
      <div style="margin-top:1rem"><button type="submit" class="btn btn-primary">Salva</button></div>
    </form>
    <form method="post" style="margin-top:1.5rem;display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="prova_posta">
      <div class="form-group" style="margin:0;flex:1;min-width:220px"><label for="prova_a">Manda una email di prova a</label><input type="email" id="prova_a" name="prova_a" value="<?= h($notifica) ?>" required></div>
      <button type="submit" class="btn btn-secondary">Manda la prova</button>
    </form>
  </div>

  <div class="form-card" style="margin-top:1rem">
    <h3>Sito pubblico</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);margin:0">
      Il sito legge da questo pannello a ogni visita: una modifica si vede subito, un articolo programmato compare da solo alla sua data e ora.
      Grafica e contenuti si cambiano da <a href="contenuti.php?tipo=pagine">Pagine</a> e <a href="contenuti.php?tipo=articoli">Articoli</a>, con l'editor visuale.
    </p>
  </div>

  <form method="post" class="form-card" id="firma-sito" style="margin-top:1rem">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_firma">
    <h3>La frase in fondo al sito</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);margin:0 0 1rem">Una riga piccola sotto il piede di ogni pagina, con un link. Si può cambiare il testo, l'indirizzo, oppure spegnerla.</p>
    <div class="form-group" style="display:flex;gap:.5rem;align-items:center">
      <input type="checkbox" id="firma_mostra" name="firma_mostra" value="1"<?= $firma['mostra'] ? ' checked' : '' ?> style="width:auto">
      <label for="firma_mostra" style="margin:0;text-transform:none;letter-spacing:0">Mostra la frase sul sito</label>
    </div>
    <div class="form-group">
      <label for="firma_testo">Testo</label>
      <input type="text" id="firma_testo" name="firma_testo" maxlength="120" value="<?= h($firma['testo']) ?>" placeholder="<?= h(FIRMA_TESTO) ?>">
    </div>
    <div class="form-group">
      <label for="firma_url">Indirizzo del link</label>
      <input type="url" id="firma_url" name="firma_url" value="<?= h($firma['url']) ?>" placeholder="<?= h(FIRMA_URL) ?>">
      <small style="display:block;color:var(--text-muted);font-size:.8rem;margin-top:.3rem">Vuoto: la frase esce senza link. Di partenza: il sito ufficiale di plumeCMS.</small>
    </div>
    <button type="submit" class="btn btn-primary" id="firma-salva">Salva la frase</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
