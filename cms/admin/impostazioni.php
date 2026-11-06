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
require_once __DIR__ . '/../includes/moduli.php';
require_admin();

// UX 2026-11 (punto 4 deciso da Danilo): due schede. "Configurazione email" (impostazioni.php) ha solo la posta;
// "Sito" (impostazioni.php?scheda=sito) ha sito pubblico, indirizzo del sito e frase in fondo. I vecchi indirizzi
// impostazioni.php#indirizzo-sito e #firma-sito portano alla scheda Sito (pannello.js, data-spostato; senza JavaScript
// un link nella pagina).
$scheda = ($_GET['scheda'] ?? '') === 'sito' ? 'sito' : 'email';
$adminTitle = $scheda === 'sito' ? 'Sito' : 'Configurazione email';

/**
 * 2.4.0: cambia l'indirizzo del sito (impostazione sito_url: link, sitemap, canonical, email, firma degli aggiornamenti).
 * Si scrive solo l'origine (https://nuovo-dominio.it): la cartella del sito resta quella di adesso (cms_base()).
 * Prova: un file con un codice a caso in uploads/, chiesto al nuovo indirizzo; se non torna, non si salva (salvo $forza).
 * Ritorna ['ok' => bool, 'messaggio' => testo per chi usa il pannello].
 */
function indirizzo_sito_salva(string $url, bool $forza): array {
    $url = rtrim(trim($url), '/');
    $p = parse_url($url);
    if (!is_array($p) || !in_array(strtolower((string)($p['scheme'] ?? '')), ['https', 'http'], true) || empty($p['host'])
        || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || (($p['path'] ?? '') !== '' && $p['path'] !== '/')
        || !preg_match('/^[a-z0-9.-]+$/i', (string)$p['host'])) {
        return ['ok' => false, 'messaggio' => 'Scrivi solo l\'indirizzo del dominio, con https:// davanti, per esempio https://www.miosito.it (senza pagine né cartelle dopo).'];
    }
    $nuovo = strtolower((string)$p['scheme']) . '://' . strtolower((string)$p['host']) . (isset($p['port']) ? ':' . (int)$p['port'] : '') . cms_base();
    if ($nuovo === cms_url_sito()) return ['ok' => true, 'messaggio' => 'L\'indirizzo del sito è già ' . $nuovo . '.'];
    if (!$forza) {
        require_once dirname(__DIR__) . '/includes/controllo-server.php';
        $codice = bin2hex(random_bytes(12));
        $nome = 'prova-indirizzo-' . $codice . '.txt';
        $file = cms_uploads_dir() . '/' . $nome;
        @file_put_contents($file, $codice);
        $percorsoUploads = (string)parse_url(cms_uploads_url(), PHP_URL_PATH); // /uploads oppure /cartella/uploads
        [$cod, $corpo, $err] = server_http(strtolower((string)$p['scheme']) . '://' . strtolower((string)$p['host']) . (isset($p['port']) ? ':' . (int)$p['port'] : '') . rtrim($percorsoUploads, '/') . '/' . $nome, 8, 1024);
        @unlink($file);
        if ($cod !== 200 || trim((string)$corpo) !== $codice) {
            return ['ok' => false, 'messaggio' => 'Il nuovo indirizzo ' . $nuovo . ' non apre ancora questo sito (' . ($cod ? 'risposta ' . $cod : $err) . '). Prima collega il dominio a questa cartella nel pannello dell\'hosting e attiva il certificato HTTPS, poi riprova. Se sei sicuro che sia già giusto, spunta "Salva lo stesso".'];
        }
    }
    $vecchio = cms_url_sito();
    save_setting('sito_url', $nuovo);
    if (function_exists('sicurezza_log')) sicurezza_log('impostazioni', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'indirizzo del sito: ' . $vecchio . ' -> ' . $nuovo);
    return ['ok' => true, 'messaggio' => 'Indirizzo del sito cambiato: ' . $nuovo . '. Da ora link, sitemap, canonical ed email usano questo indirizzo. Ricordati di aggiungerlo in Search Console.'];
}

/**
 * 2.31.0: l'avviso d'esempio (un form compilato da Maria Rossi) per l'anteprima e la prova di "Gli avvisi che ricevi".
 * Passa da moduli_email_avviso(), la stessa strada degli avvisi veri. Ritorna [oggetto, html].
 */
function avviso_esempio(bool $pulsante): array {
    $dati = ['Nome' => 'Maria Rossi', 'Email' => 'maria.rossi@esempio.it', 'Messaggio' => "Buongiorno, vorrei sapere se avete posto sabato sera per quattro persone.\nGrazie!"];
    $righe = implode('', array_map(fn($k, $v) => '<p style="margin:0 0 10px"><b>' . h($k) . ':</b> ' . nl2br(h($v)) . '</p>', array_keys($dati), $dati));
    $testo = $righe . '<p style="color:#888;font-size:13px">Dalla pagina /contatti/, il ' . date('d/m/Y H:i') . '.</p>';
    return ['Nuova compilazione: Contatti', moduli_email_avviso('Form «Contatti»', $testo, 'Apri nel pannello', cms_url_pannello() . 'form-submissions.php', $pulsante)];
}

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
    } elseif (($_POST['action'] ?? '') === 'save_avvisi') {
        // 2.31.0: il pulsante "Apri nel pannello" negli avvisi con il Reply-To del visitatore (moduli_email_avviso()).
        $acceso = !empty($_POST['avvisi_pulsante_pannello']);
        save_setting('avvisi_pulsante_pannello', $acceso ? '1' : '0');
        flash('success', $acceso ? 'Salvato: negli avvisi c\'è il pulsante "Apri nel pannello". Se rispondi a un cliente, lo riceve anche lui nel messaggio citato.' : 'Salvato: negli avvisi niente pulsante e niente indirizzo del pannello. Quando rispondi, il cliente non li vede.');
    } elseif (($_POST['action'] ?? '') === 'prova_avviso') {
        $a = posta_notifica_email();
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) $a = (string)(current_user()['email'] ?? '');
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Non so a chi mandare la prova: scrivi l\'indirizzo in "Dove arrivano l\'email del mattino e gli avvisi dei form", qui sopra, e salva.');
        } else {
            [$ogg, $html] = avviso_esempio(moduli_avviso_pannello());
            if (posta_invia($a, '[PROVA] ' . $ogg, $html, moduli_intestazioni(), 'prova')) flash('success', "Avviso di prova spedito a $a, " . (moduli_avviso_pannello() ? 'con' : 'senza') . ' il pulsante. Controlla anche nello spam.');
            else flash('error', 'La prova non è partita: ' . posta_errore());
        }
    } elseif (($_POST['action'] ?? '') === 'save_firma') {
        // 1.8.0: la frase in fondo al sito (includes/firma.php).
        $f = firma_salva(['mostra' => !empty($_POST['firma_mostra']), 'testo' => $_POST['firma_testo'] ?? '', 'url' => $_POST['firma_url'] ?? '']);
        flash('success', $f['mostra'] && $f['testo'] !== '' ? 'Frase in fondo al sito salvata: «' . $f['testo'] . '».' : 'Frase in fondo al sito spenta: sul sito non compare più.');
    } elseif (($_POST['action'] ?? '') === 'save_indirizzo') {
        // 2.4.0: l'indirizzo del sito (sito_url), per quando il sito passa a un altro dominio. Prima si prova che il nuovo
        // indirizzo apra proprio questo sito: un file con un codice a caso in uploads/, letto dal nuovo indirizzo.
        $r = indirizzo_sito_salva((string)($_POST['sito_url'] ?? ''), !empty($_POST['sito_url_forza']));
        flash($r['ok'] ? 'success' : 'error', $r['messaggio']);
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
    $azioneSito = in_array($_POST['action'] ?? '', ['save_firma', 'save_indirizzo'], true);
    header('Location: impostazioni.php' . ($azioneSito ? '?scheda=sito#' . (($_POST['action'] ?? '') === 'save_firma' ? 'firma-sito' : 'indirizzo-sito') : (in_array($_POST['action'] ?? '', ['save_avvisi', 'prova_avviso'], true) ? '#avvisi' : '')));
    exit;
}

$smtp = (json_decode((string)get_setting('posta_smtp', ''), true) ?: []) + ['host' => '', 'porta' => 465, 'sicurezza' => 'ssl', 'utente' => '', 'password' => '', 'mittente' => '', 'nome' => (string)cms_impostazione('sito_nome', CMS_NOME)];
$notifica = posta_notifica_email();
$firma = firma_valori();

require_once __DIR__ . '/includes/admin-header.php';
?>

<nav class="imp-schede" aria-label="Impostazioni">
  <a href="impostazioni.php"<?= $scheda === 'email' ? ' class="attiva" aria-current="page"' : '' ?>>Configurazione email</a>
  <a href="impostazioni.php?scheda=sito"<?= $scheda === 'sito' ? ' class="attiva" aria-current="page"' : '' ?>>Sito</a>
</nav>
<div style="max-width:720px">
<?php if ($scheda === 'email'): ?>
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
      <div class="form-group"><label for="smtp_host">Server SMTP</label><input type="text" id="smtp_host" name="smtp_host" value="<?= h($smtp['host']) ?>" placeholder="per esempio mail.<?= h((string)preg_replace('/^www\./', '', (string)parse_url(cms_url_sito(), PHP_URL_HOST))) ?>" autocomplete="off"></div>
      <div class="form-group"><label for="smtp_porta">Porta</label><input type="number" id="smtp_porta" name="smtp_porta" value="<?= (int)$smtp['porta'] ?>"></div>
      <div class="form-group"><label for="smtp_sicurezza">Sicurezza</label>
        <select id="smtp_sicurezza" name="smtp_sicurezza">
          <option value="ssl" <?= $smtp['sicurezza'] === 'ssl' ? 'selected' : '' ?>>SSL (di solito porta 465)</option>
          <option value="tls" <?= $smtp['sicurezza'] === 'tls' ? 'selected' : '' ?>>STARTTLS (di solito porta 587)</option>
          <option value="nessuna" <?= $smtp['sicurezza'] === 'nessuna' ? 'selected' : '' ?>>Nessuna</option>
        </select></div>
      <div class="form-group"><label for="smtp_utente">Utente (l'indirizzo email completo)</label><input type="text" id="smtp_utente" name="smtp_utente" value="<?= h($smtp['utente']) ?>" placeholder="per esempio posta@<?= h((string)preg_replace('/^www\./', '', (string)parse_url(cms_url_sito(), PHP_URL_HOST))) ?>" autocomplete="off"></div>
      <div class="form-group"><label for="smtp_password">Password della casella</label><input type="password" id="smtp_password" name="smtp_password" value="<?= $smtp['password'] !== '' ? '********' : '' ?>" autocomplete="new-password">
        <div class="hint">Resta salvata nel database del pannello. Gli asterischi vogliono dire che c'è già: se non la cambi, resta quella.</div></div>
      <div class="form-group"><label for="smtp_mittente">Indirizzo del mittente</label><input type="text" id="smtp_mittente" name="smtp_mittente" value="<?= h($smtp['mittente']) ?>" placeholder="uguale all'utente"></div>
      <div class="form-group"><label for="smtp_nome">Nome del mittente</label><input type="text" id="smtp_nome" name="smtp_nome" value="<?= h($smtp['nome']) ?>"></div>
      <div class="form-group"><label for="notification_email">Dove arrivano l'email del mattino e gli avvisi dei form</label><input type="text" id="notification_email" name="notification_email" value="<?= h($notifica) ?>" placeholder="per esempio tuaemail@esempio.it"></div>
      <div style="margin-top:1rem"><button type="submit" class="btn btn-primary">Salva</button></div>
    </form>
    <form method="post" style="margin-top:1.5rem;display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="prova_posta">
      <div class="form-group" style="margin:0;flex:1;min-width:220px"><label for="prova_a">Manda una email di prova a</label><input type="email" id="prova_a" name="prova_a" value="<?= h($notifica) ?>" required></div>
      <button type="submit" class="btn btn-secondary">Manda la prova</button>
    </form>
  </div>
  <?php /* 2.31.0: il pulsante del pannello negli avvisi con il Reply-To del visitatore. */
  $avvPulsante = moduli_avviso_pannello(); [$avvOgg, $avvHtml] = avviso_esempio($avvPulsante); ?>
  <div class="form-card" id="avvisi" style="margin-top:1rem">
    <h3>Gli avvisi che ricevi</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem">
      Quando qualcuno compila un form, chiede una prenotazione, fa un ordine o si iscrive a un evento ti arriva un avviso.
      Se premi Rispondi, la risposta va a lui, e il programma di posta di solito ci mette sotto l'avviso intero.
    </p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_avvisi">
      <div class="form-group" style="display:flex;gap:.5rem;align-items:flex-start">
        <input type="checkbox" id="avvisi_pulsante_pannello" name="avvisi_pulsante_pannello" value="1"<?= $avvPulsante ? ' checked' : '' ?> style="margin-top:.1rem" aria-describedby="avvisi-aiuto">
        <label for="avvisi_pulsante_pannello" style="margin:0;text-transform:none;letter-spacing:0">Negli avvisi che hanno come risposta il cliente, mostra il pulsante "Apri nel pannello"</label>
      </div>
      <div class="hint" id="avvisi-aiuto">Spenta (di partenza): niente pulsante e niente indirizzo del pannello, così chi riceve la tua risposta non li vede. Accesa: arrivi al pannello con un clic, ma rispondendo il cliente riceve anche il pulsante e l'indirizzo. Gli avvisi solo per te, come una recensione da approvare, hanno il pulsante sempre.</div>
      <div style="margin-top:1rem"><button type="submit" class="btn btn-primary">Salva gli avvisi</button></div>
    </form>
    <p class="hint" style="margin-top:1.2rem">Anteprima di un avviso, così come esce adesso (<?= $avvPulsante ? 'con il pulsante' : 'senza pulsante' ?>). Oggetto: <b><?= h($avvOgg) ?></b></p>
    <iframe class="av-cornice" title="Anteprima di un avviso" sandbox srcdoc="<?= h($avvHtml) ?>"></iframe>
    <form method="post" style="margin-top:.8rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="prova_avviso">
      <button type="submit" class="btn btn-secondary">Manda un avviso di prova</button>
      <span class="hint">Va <?= $notifica !== '' ? 'a <b>' . h($notifica) . '</b>' : 'all\'email del tuo utente' ?>, con i dati finti di Maria Rossi.</span>
    </form>
  </div>
  <p class="hint" style="margin-top:1rem">Indirizzo del sito e frase in fondo al sito stanno nella scheda
    <a href="impostazioni.php?scheda=sito#indirizzo-sito" id="indirizzo-sito" data-spostato="impostazioni.php?scheda=sito#indirizzo-sito">Sito</a><span id="firma-sito" data-spostato="impostazioni.php?scheda=sito#firma-sito"></span>.</p>
<?php else: ?>

  <div class="form-card">
    <h3>Sito pubblico</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);margin:0">
      Il sito legge da questo pannello a ogni visita: una modifica si vede subito, un articolo programmato compare da solo alla sua data e ora.
      Grafica e contenuti si cambiano da <a href="contenuti.php?tipo=pagine">Pagine</a> e <a href="contenuti.php?tipo=articoli">Articoli</a>, con l'editor visuale.
    </p>
  </div>

  <?php /* 2.4.0: l'indirizzo del sito, per il trasloco su un altro dominio. */ ?>
  <form method="post" class="form-card" id="indirizzo-sito" style="margin-top:1rem">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_indirizzo">
    <h3>Indirizzo del sito</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);margin:0 0 1rem">L'indirizzo che il sito usa per i link, la sitemap, il canonical, le email e Search Console. Cambialo solo quando sposti il sito su un altro dominio, <b>dopo</b> aver collegato il nuovo dominio a questa cartella e attivato HTTPS: il pannello prova che il nuovo indirizzo apra davvero questo sito prima di salvarlo.</p>
    <div class="form-group">
      <label for="sito_url">Indirizzo (solo il dominio)</label>
      <input type="url" id="sito_url" name="sito_url" value="<?= h(preg_replace('~' . preg_quote(cms_base(), '~') . '$~', '', cms_url_sito())) ?>" placeholder="per esempio https://www.miosito.it" required>
      <?php if (cms_base() !== ''): ?><div class="hint">Il sito sta nella cartella <code><?= h(cms_base()) ?></code>: resta la stessa anche con il dominio nuovo.</div><?php endif; ?>
    </div>
    <div class="form-group" style="display:flex;gap:.5rem;align-items:center">
      <input type="checkbox" id="sito_url_forza" name="sito_url_forza" value="1" style="width:auto">
      <label for="sito_url_forza" style="margin:0;text-transform:none;letter-spacing:0">Salva lo stesso, senza la prova (solo se sai che il nuovo indirizzo funziona)</label>
    </div>
    <button type="submit" class="btn btn-primary" id="indirizzo-salva" data-conferma="Cambiare l'indirizzo del sito? Link, sitemap, canonical ed email useranno il nuovo indirizzo.">Salva l'indirizzo</button>
  </form>

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
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
