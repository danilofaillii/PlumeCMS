<?php
/**
 * Sicurezza (solo amministratore): lista di controllo dello stato, impostazioni (2FA obbligatorio, tentativi e blocco,
 * scadenza della sessione, IP consentiti, CSP, chi usa il codice grezzo, avvisi), registro degli accessi con filtri,
 * sessioni aperte e "esci da tutti i dispositivi" per ogni utente, rapporti della CSP.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_admin();
sicurezza_schema();

$adminTitle = 'Sicurezza';
$io = current_user();
$scheda = (string)($_GET['scheda'] ?? 'stato');
if (!in_array($scheda, ['stato', 'impostazioni', 'registro', 'sessioni', 'csp'], true)) $scheda = 'stato';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    try {
        switch ($azione) {
            case 'salva':
                $v = $_POST['cfg'] ?? [];
                if (!is_array($v)) $v = [];
                $cfgPrima = sicurezza_cfg();
                // La lista IP si accende solo se contiene l'IP di chi la accende: nessuno si chiude fuori da solo.
                if (!empty($v['ip_attivo']) && !sicurezza_ip_consentito(sicurezza_ip(), (string)($v['ip_elenco'] ?? ''))) {
                    throw new RuntimeException('Il tuo indirizzo attuale (' . sicurezza_ip() . ') non e nell\'elenco: aggiungilo prima di accendere la lista, altrimenti resti fuori.');
                }
                if (!empty($v['totp_admin']) && empty($io['totp_attivo'])) {
                    throw new RuntimeException('Prima attiva il secondo fattore sul tuo accesso (Il mio accesso), poi rendilo obbligatorio per tutti gli amministratori.');
                }
                foreach (['totp_admin', 'ip_attivo', 'hsts', 'avvisi_email', 'firma_pacchetti'] as $k) $v[$k] = empty($v[$k]) ? 0 : 1;
                sicurezza_salva_cfg($v);
                $dopo = sicurezza_cfg();
                $cambi = [];
                foreach ($dopo as $k => $x) if (($cfgPrima[$k] ?? null) !== $x) $cambi[] = $k;
                sicurezza_log('impostazioni', (string)$io['username'], (int)$io['id'], $cambi ? 'cambiate: ' . implode(', ', $cambi) : 'nessun cambiamento');
                flash('success', 'Impostazioni di sicurezza salvate.');
                $scheda = 'impostazioni';
                break;
            case 'sblocca':
                sicurezza_sblocca((string)($_POST['chiave'] ?? ''));
                flash('success', 'Blocco tolto.');
                $scheda = 'registro';
                break;
            case 'esci_tutti':
                $uid = (int)($_POST['utente_id'] ?? 0);
                $n = sicurezza_sessioni_chiudi($uid, $uid === (int)$io['id']);
                flash('success', "Chiuse $n sessioni.");
                $scheda = 'sessioni';
                break;
            case 'root_php_apri':
                // Una finestra di un'ora in cui un pacchetto non firmato puo mettere file .php nella radice del sito (trasloco).
                // Serve la password: chi ha rubato solo la sessione non la apre.
                if (!sicurezza_password_verifica($io, (string)($_POST['password'] ?? ''))) throw new RuntimeException('Password sbagliata: la finestra resta chiusa.');
                sicurezza_salva_cfg(['root_php_fino' => time() + 3600]);
                sicurezza_log('impostazioni', (string)$io['username'], (int)$io['id'], 'finestra di un\'ora per file .php nella radice dai pacchetti non firmati');
                flash('success', 'Per un\'ora il pannello Aggiornamenti accetta pacchetti non firmati con file .php nella radice del sito. Poi si richiude da sola.');
                $scheda = 'impostazioni';
                break;
            case 'root_php_chiudi':
                sicurezza_salva_cfg(['root_php_fino' => 0]);
                flash('success', 'Finestra chiusa.');
                $scheda = 'impostazioni';
                break;
            case 'csp_svuota':
                db()->exec('DELETE FROM ' . tbl('sicurezza_csp'));
                flash('success', 'Rapporti CSP cancellati: i prossimi arrivano puliti.');
                $scheda = 'csp';
                break;
            case 'rimuovi_install':
                $dir = cms_radice() . '/install';
                if (!is_dir($dir)) throw new RuntimeException('La cartella install non c\'e gia piu.');
                if (!is_file($dir . '/.installato')) throw new RuntimeException('Questa installazione non risulta fatta dall\'installatore: togli la cartella a mano, dal file manager.');
                require_once dirname(__DIR__) . '/includes/aggiornamenti.php';
                agg_rimuovi_cartella($dir);
                if (is_dir($dir)) throw new RuntimeException('Il server non lascia cancellare la cartella: toglila dal file manager dell\'hosting.');
                sicurezza_log('impostazioni', (string)$io['username'], (int)$io['id'], 'cartella install rimossa');
                flash('success', 'Cartella install rimossa.');
                break;
            case 'scrivi_htaccess_uploads':
                $dir = cms_uploads_dir();
                if (!is_dir($dir)) throw new RuntimeException("La cartella delle immagini ($dir) non esiste.");
                // Il modello sta con l'installatore. Dove install/ non c'e (danilofailli.it), il file arriva con un pacchetto.
                $modello = cms_radice() . '/install/esempi/htaccess-uploads.txt';
                if (!is_file($modello)) throw new RuntimeException('Su questa installazione manca il modello (install/esempi/htaccess-uploads.txt): il file uploads/.htaccess si consegna con un pacchetto del pannello Aggiornamenti.');
                $testo = (string)file_get_contents($modello);
                if ($testo === '' || file_put_contents($dir . '/.htaccess', $testo) === false) throw new RuntimeException('Non riesco a scrivere il file: permessi della cartella.');
                flash('success', 'Scritto ' . basename($dir) . '/.htaccess: PHP spento nella cartella delle immagini (vale su Apache).');
                break;
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: sicurezza.php?scheda=' . $scheda);
    exit;
}

$cfg = sicurezza_cfg();
require_once __DIR__ . '/includes/admin-header.php';
$schede = ['stato' => 'Stato', 'impostazioni' => 'Impostazioni', 'registro' => 'Registro', 'sessioni' => 'Sessioni e utenti', 'csp' => 'Rapporti CSP'];
?>
<style>
.sic-schede { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1.2rem }
.sic-schede a { padding:.45rem .9rem; border:1px solid var(--border); border-radius:999px; text-decoration:none; color:inherit; font-size:.88rem; background:#fff }
.sic-schede a.attiva { background:#2d1b0e; color:#fff; border-color:#2d1b0e }
.sic-lista { display:grid; gap:.5rem; max-width:900px }
.sic-voce { display:grid; grid-template-columns: 28px minmax(0,1fr); gap:.8rem; align-items:start; background:#fff; border:1px solid var(--border); border-radius:10px; padding:.7rem .9rem }
.sic-voce b { display:block; font-size:.92rem } .sic-voce span { font-size:.84rem; color:var(--text-muted) }
.sic-pallino { width:18px; height:18px; border-radius:50%; margin-top:2px } .sic-ok { background:#22c55e } .sic-attenzione { background:#f59e0b } .sic-no { background:#dc2626 } .sic-info { background:#94a3b8 }
.sic-form { max-width:820px } .sic-form h4 { margin:1.4rem 0 .5rem; font-size:1rem } .sic-form .hint { font-size:.82rem; color:var(--text-muted); margin-top:.25rem }
.sic-form .form-group { margin-bottom:.9rem } .sic-form input[type=number] { max-width:120px } .sic-form textarea { width:100%; min-height:90px; font-family:ui-monospace,monospace; font-size:.85rem }
.sic-riquadro { border:1px solid #f1d58a; background:#fff8e6; border-radius:10px; padding:.9rem 1rem; font-size:.88rem; margin:.8rem 0 }
.sic-filtri { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; margin-bottom:1rem } .sic-filtri label { font-size:.78rem; display:block; color:var(--text-muted) }
.sic-filtri input, .sic-filtri select { padding:.4rem .5rem; border:1px solid var(--border); border-radius:6px }
.sic-tab td, .sic-tab th { font-size:.84rem; vertical-align:top } .sic-mono { font-family:ui-monospace,monospace; font-size:.8rem }
</style>
<div class="sic-schede"><?php foreach ($schede as $k => $n): ?><a href="sicurezza.php?scheda=<?= $k ?>" class="<?= $scheda === $k ? 'attiva' : '' ?>"><?= h($n) ?></a><?php endforeach; ?></div>

<?php if ($scheda === 'stato'): ?>
  <p style="font-size:.9rem;color:var(--text-muted);max-width:900px;margin-bottom:1rem">Un colpo d'occhio su come e protetto il sito. Verde: a posto. Giallo: funziona ma si puo stringere. Rosso: da sistemare. Grigio: informazione.</p>
  <div class="sic-lista">
  <?php foreach (sicurezza_checklist() as [$k, $esito, $titolo, $testo]): ?>
    <div class="sic-voce"><div class="sic-pallino sic-<?= $esito ?>"></div><div><b><?= h($titolo) ?></b><span><?= h($testo) ?></span>
      <?php if ($k === 'install' && is_dir(cms_radice() . '/install') && is_file(cms_radice() . '/install/.installato')): ?>
        <form method="post" style="margin-top:.5rem" data-conferma="Rimuovere la cartella install? Non serve piu dopo l'installazione."><?= csrf_field() ?><input type="hidden" name="azione" value="rimuovi_install"><button class="btn btn-secondary btn-sm">Rimuovi la cartella install</button></form>
      <?php elseif ($k === 'uploads' && $esito !== 'ok'): ?>
        <form method="post" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="azione" value="scrivi_htaccess_uploads"><button class="btn btn-secondary btn-sm">Scrivi uploads/.htaccess</button></form>
      <?php endif; ?>
    </div></div>
  <?php endforeach; ?>
  </div>

<?php elseif ($scheda === 'impostazioni'): ?>
  <form method="post" class="form-card sic-form"><?= csrf_field() ?><input type="hidden" name="azione" value="salva">
    <h3>Come si entra nel pannello</h3>
    <div class="form-group"><label>Tentativi sbagliati prima del blocco (per indirizzo IP)</label><input type="number" name="cfg[tentativi_max]" min="3" max="20" value="<?= (int)$cfg['tentativi_max'] ?>">
      <div class="hint">Contati per indirizzo IP. Al blocco parte un'attesa di <strong><?= (int)$cfg['blocco_minuti'] ?> minuti</strong>, che raddoppia a ogni tentativo in piu (fino a un giorno). Un accesso riuscito azzera il conteggio.
      Per nome utente vale una regola fissa: <strong>20 tentativi sbagliati in 15 minuti</strong>, da qualunque indirizzo, bloccano quel nome per 15 minuti (poi raddoppia) e ti arriva un'email; durante quel blocco entri lo stesso, con la password giusta, dai computer da cui hai gia fatto accesso.</div></div>
    <div class="form-group"><label>Durata del primo blocco (minuti)</label><input type="number" name="cfg[blocco_minuti]" min="1" max="1440" value="<?= (int)$cfg['blocco_minuti'] ?>"></div>
    <div class="form-group"><label>La sessione scade comunque dopo (ore)</label><input type="number" name="cfg[sessione_ore]" min="1" max="720" value="<?= (int)$cfg['sessione_ore'] ?>"><div class="hint">Anche se continui a lavorare: alla scadenza il pannello chiede di rientrare.</div></div>
    <div class="form-group"><label>La sessione scade se resti fermo per (minuti)</label><input type="number" name="cfg[inattivita_minuti]" min="5" max="1440" value="<?= (int)$cfg['inattivita_minuti'] ?>"></div>
    <div class="form-group"><label><input type="checkbox" name="cfg[avvisi_email]" value="1" <?= $cfg['avvisi_email'] ? 'checked' : '' ?>> Avvisami per email quando scatta un blocco o un amministratore entra da un IP mai visto</label><div class="hint">All'indirizzo degli avvisi (Impostazioni, Configurazione email).</div></div>

    <h4>Secondo fattore (2FA)</h4>
    <div class="sic-riquadro"><strong>Prima di renderlo obbligatorio</strong>: attiva il secondo fattore sul tuo accesso da <a href="account.php">Il mio accesso</a> e salva gli otto codici di recupero. Se perdi il telefono e i codici, un altro amministratore puo azzerare il tuo 2FA dalla voce Utenti; se sei l'unico, la riga di emergenza da mettere in <code>includes/config.local.php</code> e <code>define('CMS_SICUREZZA_SPEGNI', true);</code> (poi la togli).</div>
    <div class="form-group"><label><input type="checkbox" name="cfg[totp_admin]" value="1" <?= $cfg['totp_admin'] ? 'checked' : '' ?> <?= empty($io['totp_attivo']) ? 'disabled title="Prima attiva il tuo secondo fattore"' : '' ?>> Secondo fattore obbligatorio per tutti gli amministratori</label>
      <div class="hint">Un amministratore senza 2FA, al prossimo accesso, deve configurarlo prima di fare altro. I redattori restano liberi di attivarlo.</div></div>

    <h4>Indirizzi IP consentiti</h4>
    <div class="sic-riquadro"><strong>Attenzione</strong>: con la lista accesa il pannello risponde solo da questi indirizzi. Il tuo IP di adesso e <code><?= h(sicurezza_ip()) ?></code>: se non e in elenco il salvataggio si rifiuta. Se la connessione di casa cambia IP (succede), ti chiudi fuori: in quel caso la riga di emergenza in <code>config.local.php</code> e <code>define('CMS_SICUREZZA_SPEGNI', true);</code>. Conviene usarla solo con un IP fisso o una VPN.</div>
    <div class="form-group"><label><input type="checkbox" name="cfg[ip_attivo]" value="1" <?= $cfg['ip_attivo'] ? 'checked' : '' ?>> Lista IP attiva</label></div>
    <div class="form-group"><label>Indirizzi o reti ammessi (uno per riga, anche in forma 192.168.1.0/24 o 2001:db8::/32)</label><textarea name="cfg[ip_elenco]" placeholder="<?= h(sicurezza_ip()) ?>"><?= h($cfg['ip_elenco']) ?></textarea></div>

    <h4>Intestazioni di sicurezza e Content-Security-Policy</h4>
    <div class="hint" style="margin-bottom:.6rem">La CSP dice al browser da dove possono arrivare script, stili, immagini e frame. In modalita <em>solo rapporto</em> il browser segnala le violazioni (scheda Rapporti CSP) senza bloccare niente: lasciala cosi qualche giorno, guarda i rapporti, poi passa ad <em>applica</em>. Gli script dei tag di analisi (GA4, Tag Manager, Pixel, Clarity) entrano nella CSP solo se sono accesi nella voce SEO.</div>
    <?php foreach (['csp_sito' => 'CSP del sito pubblico', 'csp_pannello' => 'CSP del pannello'] as $k => $et): ?>
    <div class="form-group"><label><?= $et ?></label><select name="cfg[<?= $k ?>]">
      <?php foreach (['spenta' => 'Spenta', 'rapporto' => 'Solo rapporto (il browser segnala, non blocca)', 'applica' => 'Applica (il browser blocca)'] as $v => $n): ?><option value="<?= $v ?>" <?= $cfg[$k] === $v ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
    </select></div>
    <?php endforeach; ?>
    <div class="form-group"><label>Sorgenti in piu (una per riga: direttiva e indirizzo, per esempio <code>frame-src https://www.google.com</code>)</label><textarea name="cfg[csp_extra]" placeholder="script-src https://esempio.it"><?= h($cfg['csp_extra']) ?></textarea><div class="hint">Serve se un widget HTML carica uno script o un frame da un sito che la CSP non conosce (lo vedi nei rapporti).</div></div>
    <div class="form-group"><label><input type="checkbox" name="cfg[hsts]" value="1" <?= $cfg['hsts'] ? 'checked' : '' ?>> Strict-Transport-Security (il browser usa sempre https per sei mesi)</label><div class="hint">Solo quando il certificato funziona bene: se poi https si rompe, i browser non aprono il sito in http.</div></div>

    <h4>Chi puo scrivere codice grezzo</h4>
    <div class="form-group"><label>Widget HTML dell'editor e scheda Testo in HTML</label><select name="cfg[html_widget]"><option value="admin" <?= $cfg['html_widget'] === 'admin' ? 'selected' : '' ?>>Solo gli amministratori (i redattori vedono il blocco ma, se lo cambiano, diventa testo pulito)</option><option value="nessuno" <?= $cfg['html_widget'] === 'nessuno' ? 'selected' : '' ?>>Nessuno: anche l'amministratore salva solo testo pulito</option></select></div>
    <div class="form-group"><label>Codice libero head/body del pannello SEO</label><select name="cfg[codice_libero]"><option value="admin" <?= $cfg['codice_libero'] === 'admin' ? 'selected' : '' ?>>Attivo (lo scrive l'amministratore, esce su ogni pagina)</option><option value="nessuno" <?= $cfg['codice_libero'] === 'nessuno' ? 'selected' : '' ?>>Spento: il codice resta salvato ma non esce sul sito</option></select>
      <div class="hint">E HTML grezzo che finisce in ogni pagina del sito: incolla solo codice di cui conosci la provenienza.</div></div>

    <h4>Pacchetti e chiamate in uscita</h4>
    <div class="form-group"><label><input type="checkbox" name="cfg[firma_pacchetti]" value="1" <?= $cfg['firma_pacchetti'] ? 'checked' : '' ?>> I pacchetti del pannello Aggiornamenti devono essere firmati</label><div class="hint">La firma la mette chi costruisce il pacchetto (<code>tools/pacchetto.py --firma</code>) con la chiave <code>CMS_FIRMA_PACCHETTI</code> del config. Senza spunta, una firma presente viene comunque verificata.</div></div>
    <?php $finestraRoot = (int)$cfg['root_php_fino'] > time(); ?>
    <div class="sic-riquadro"><strong>File .php nella radice del sito.</strong> Un pacchetto che mette un file eseguibile (.php) nella radice (destinazione <code>root/</code>, per esempio il pacchetto del trasloco) passa solo se e firmato, oppure durante una finestra di un'ora che apri qui sotto con la tua password.
      <?= $finestraRoot ? '<br><strong>Finestra aperta fino alle ' . h(date('H:i', (int)$cfg['root_php_fino'])) . '.</strong>' : '' ?></div>
    <div class="form-group"><label>Altri host verso cui il CMS puo fare chiamate (uno per riga)</label><textarea name="cfg[uscita_host]" placeholder="api.esempio.it"><?= h($cfg['uscita_host']) ?></textarea><div class="hint">Di base: IndexNow, Bing, Google (Search Console). Mai verso indirizzi privati del server.</div></div>
    <div style="margin-top:1.2rem"><button class="btn btn-primary">Salva le impostazioni</button></div>
  </form>
  <form method="post" class="form-card sic-form" style="margin-top:1rem"><?= csrf_field() ?>
    <h3>Finestra per i pacchetti del trasloco</h3>
    <?php if ((int)$cfg['root_php_fino'] > time()): ?>
      <p style="font-size:.88rem">Aperta fino alle <strong><?= h(date('H:i', (int)$cfg['root_php_fino'])) ?></strong>.</p>
      <input type="hidden" name="azione" value="root_php_chiudi"><button class="btn btn-secondary btn-sm">Chiudi adesso</button>
    <?php else: ?>
      <p style="font-size:.88rem;color:var(--text-muted)">Serve solo per un pacchetto non firmato che scrive file .php nella radice (il pannello Aggiornamenti te lo dice). Dopo un'ora si chiude da sola.</p>
      <input type="hidden" name="azione" value="root_php_apri">
      <div class="form-group"><label>La tua password</label><input type="password" name="password" autocomplete="current-password" required></div>
      <button class="btn btn-secondary btn-sm">Apri la finestra per un'ora</button>
    <?php endif; ?>
  </form>

<?php elseif ($scheda === 'registro'): ?>
  <?php $blocchi = sicurezza_blocchi_attivi(); if ($blocchi): ?>
  <div class="form-card" style="max-width:900px"><h3>Blocchi attivi</h3>
    <table class="sic-tab"><thead><tr><th>Chi</th><th>Tentativi</th><th>Fino alle</th><th></th></tr></thead><tbody>
    <?php foreach ($blocchi as $b): ?><tr><td class="sic-mono"><?= h($b['chiave']) ?></td><td><?= (int)$b['falliti'] ?></td><td><?= h(date('d/m H:i', strtotime($b['bloccato_fino']))) ?></td>
      <td><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="sblocca"><input type="hidden" name="chiave" value="<?= h($b['chiave']) ?>"><button class="btn btn-secondary btn-sm">Sblocca</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <?php $f = ['evento' => (string)($_GET['evento'] ?? ''), 'utente' => (string)($_GET['utente'] ?? ''), 'ip' => (string)($_GET['ip'] ?? ''), 'da' => (string)($_GET['da'] ?? ''), 'a' => (string)($_GET['a'] ?? '')];
        $pag = max(1, (int)($_GET['p'] ?? 1)); [$righe, $tot] = sicurezza_log_elenco($f, $pag, 50); ?>
  <form method="get" class="sic-filtri"><input type="hidden" name="scheda" value="registro">
    <div><label>Evento</label><select name="evento"><option value="">tutti</option><?php foreach (SICUREZZA_EVENTI as $k => $n): ?><option value="<?= $k ?>" <?= $f['evento'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></div>
    <div><label>Utente</label><input type="text" name="utente" value="<?= h($f['utente']) ?>"></div>
    <div><label>IP</label><input type="text" name="ip" value="<?= h($f['ip']) ?>"></div>
    <div><label>Dal</label><input type="date" name="da" value="<?= h($f['da']) ?>"></div>
    <div><label>Al</label><input type="date" name="a" value="<?= h($f['a']) ?>"></div>
    <div><button class="btn btn-secondary btn-sm">Filtra</button> <a class="btn btn-secondary btn-sm" href="sicurezza.php?scheda=registro">Tutto</a></div>
  </form>
  <p style="font-size:.85rem;color:var(--text-muted)"><?= $tot ?> righe. Il registro tiene sei mesi.</p>
  <div class="admin-table-wrap"><table class="sic-tab"><thead><tr><th>Quando</th><th>Evento</th><th>Utente</th><th>IP</th><th>Dettagli</th><th>Browser</th></tr></thead><tbody>
  <?php foreach ($righe as $r): ?>
    <tr><td><?= h(date('d/m/Y H:i:s', strtotime($r['creato']))) ?></td><td><?= h(SICUREZZA_EVENTI[$r['evento']] ?? $r['evento']) ?></td><td><?= h($r['utente']) ?></td><td class="sic-mono"><?= h($r['ip']) ?></td><td><?= h((string)$r['dettagli']) ?></td><td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($r['user_agent']) ?>"><?= h(mb_substr($r['user_agent'], 0, 50)) ?></td></tr>
  <?php endforeach; if (!$righe): ?><tr><td colspan="6" style="color:var(--text-muted)">Nessuna riga con questi filtri.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($tot > 50): ?><p style="margin-top:.8rem"><?php for ($i = 1; $i <= min(40, ceil($tot / 50)); $i++): ?><a class="btn btn-secondary btn-sm" style="<?= $i === $pag ? 'font-weight:700' : '' ?>" href="sicurezza.php?<?= h(http_build_query(['scheda' => 'registro', 'p' => $i] + $f)) ?>"><?= $i ?></a> <?php endfor; ?></p><?php endif; ?>

<?php elseif ($scheda === 'sessioni'): ?>
  <p style="font-size:.9rem;color:var(--text-muted);max-width:900px">Le sessioni aperte di ogni utente. "Esci da tutti i dispositivi" chiude ogni sessione di quell'utente (se sei tu, questa resta). Il secondo fattore di un utente si azzera dalla voce Utenti.</p>
  <?php $utenti = db()->query('SELECT id, username, role, last_login, totp_attivo FROM ' . tbl('users') . ' ORDER BY username')->fetchAll(); ?>
  <div class="admin-table-wrap"><table class="sic-tab"><thead><tr><th>Utente</th><th>Ruolo</th><th>2FA</th><th>Ultimo accesso</th><th>Sessioni aperte</th><th></th></tr></thead><tbody>
  <?php foreach ($utenti as $u): $ss = sicurezza_sessioni_di((int)$u['id']); ?>
    <tr><td><strong><?= h($u['username']) ?></strong><?= (int)$u['id'] === (int)$io['id'] ? ' (tu)' : '' ?></td><td><?= $u['role'] === 'admin' ? 'Amministratore' : 'Redattore' ?></td><td><?= $u['totp_attivo'] ? '<span class="badge badge-published">attivo</span>' : 'no' ?></td><td><?= $u['last_login'] ? h(substr($u['last_login'], 0, 16)) : 'mai' ?></td>
      <td><?php foreach ($ss as $s): ?><div class="sic-mono"><?= h($s['ip']) ?> &middot; <?= h(date('d/m H:i', strtotime($s['ultimo']))) ?> &middot; <?= h(mb_substr($s['user_agent'], 0, 40)) ?></div><?php endforeach; if (!$ss): ?><span style="color:var(--text-muted)">nessuna</span><?php endif; ?></td>
      <td><form method="post" data-conferma="Chiudere tutte le sessioni di questo utente?"><?= csrf_field() ?><input type="hidden" name="azione" value="esci_tutti"><input type="hidden" name="utente_id" value="<?= (int)$u['id'] ?>"><button class="btn btn-secondary btn-sm">Esci da tutti i dispositivi</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>

<?php else: ?>
  <?php $rap = db()->query('SELECT * FROM ' . tbl('sicurezza_csp') . ' ORDER BY ultimo DESC LIMIT 300')->fetchAll(); ?>
  <p style="font-size:.9rem;color:var(--text-muted);max-width:900px">Quello che i browser dei visitatori hanno segnalato come fuori dalla Content-Security-Policy. Con la CSP in "solo rapporto" niente di questo e stato bloccato: e l'elenco di cosa si romperebbe passando ad "applica". Uno script tuo che compare qui si aggiunge nelle Sorgenti in piu; uno sconosciuto e un'estensione del browser di qualcuno (frequente: <code>chrome-extension</code>, <code>moz-extension</code>) oppure un tentativo di iniezione.</p>
  <form method="post" style="margin-bottom:1rem"><?= csrf_field() ?><input type="hidden" name="azione" value="csp_svuota"><button class="btn btn-secondary btn-sm">Svuota i rapporti</button></form>
  <div class="admin-table-wrap"><table class="sic-tab"><thead><tr><th>Dove</th><th>Direttiva</th><th>Bloccato</th><th>Pagina</th><th>Volte</th><th>Ultimo</th></tr></thead><tbody>
  <?php foreach ($rap as $r): ?><tr><td><?= h($r['contesto']) ?></td><td class="sic-mono"><?= h($r['direttiva']) ?></td><td class="sic-mono" style="word-break:break-all"><?= h($r['bloccato']) ?></td><td class="sic-mono" style="word-break:break-all"><?= h($r['documento']) ?></td><td><?= (int)$r['conteggio'] ?></td><td><?= h(date('d/m H:i', strtotime($r['ultimo']))) ?></td></tr><?php endforeach; ?>
  <?php if (!$rap): ?><tr><td colspan="6" style="color:var(--text-muted)">Nessun rapporto finora.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
