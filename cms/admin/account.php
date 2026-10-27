<?php
/**
 * Il mio accesso: secondo fattore (app di autenticazione, codici di recupero), i dispositivi collegati,
 * "esci da tutti i dispositivi". Nome utente e password restano in password.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/sicurezza-qr.php';
require_login();

$adminTitle = 'Il mio accesso';
$io = current_user();
$obbligatorio = sicurezza_2fa_da_configurare($io);
$codiciNuovi = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    try {
        if ($azione === 'totp_inizia') {
            // Un segreto nuovo, ancora non attivo: si attiva solo quando l'app risponde con un codice giusto.
            $_SESSION['totp_bozza'] = sicurezza_totp_segreto();
        } elseif ($azione === 'totp_conferma') {
            $segreto = (string)($_SESSION['totp_bozza'] ?? '');
            $passo = sicurezza_totp_verifica($segreto, (string)($_POST['codice'] ?? ''));
            if ($segreto === '' || $passo === 0) throw new RuntimeException('Il codice non corrisponde: controlla l\'ora del telefono e riprova.');
            [$chiaro, $hash] = sicurezza_recupero_genera();
            db()->prepare('UPDATE ' . tbl('users') . ' SET totp_segreto = ?, totp_attivo = 1, totp_recupero = ?, totp_ultimo = ? WHERE id = ?')->execute([$segreto, json_encode($hash), $passo, (int)$io['id']]);
            unset($_SESSION['totp_bozza']);
            $_SESSION['recupero_mostra'] = $chiaro;
            sicurezza_log('2fa_attivato', (string)$io['username'], (int)$io['id']);
            flash('success', 'Secondo fattore attivo. Salva adesso i codici di recupero: non si rivedono piu.');
        } elseif ($azione === 'totp_spegni') {
            if ($obbligatorio || (sicurezza_cfg()['totp_admin'] && $io['role'] === 'admin')) throw new RuntimeException('Il secondo fattore e obbligatorio per gli amministratori: lo decide la pagina Sicurezza.');
            if (!sicurezza_password_verifica($io, (string)($_POST['password'] ?? ''))) throw new RuntimeException('Password sbagliata.');
            db()->prepare('UPDATE ' . tbl('users') . ' SET totp_segreto = \'\', totp_attivo = 0, totp_recupero = NULL WHERE id = ?')->execute([(int)$io['id']]);
            sicurezza_log('2fa_disattivato', (string)$io['username'], (int)$io['id']);
            flash('success', 'Secondo fattore spento.');
        } elseif ($azione === 'recupero_nuovi') {
            if (!sicurezza_password_verifica($io, (string)($_POST['password'] ?? ''))) throw new RuntimeException('Password sbagliata.');
            [$chiaro, $hash] = sicurezza_recupero_genera();
            db()->prepare('UPDATE ' . tbl('users') . ' SET totp_recupero = ? WHERE id = ?')->execute([json_encode($hash), (int)$io['id']]);
            $_SESSION['recupero_mostra'] = $chiaro;
            flash('success', 'Codici di recupero nuovi: quelli vecchi non valgono piu.');
        } elseif ($azione === 'esci_tutti') {
            $n = sicurezza_sessioni_chiudi((int)$io['id'], true);
            flash('success', "Chiuse $n altre sessioni. Questa resta aperta.");
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: account.php');
    exit;
}
if (!empty($_SESSION['recupero_mostra'])) { $codiciNuovi = $_SESSION['recupero_mostra']; unset($_SESSION['recupero_mostra']); }
$bozza = (string)($_SESSION['totp_bozza'] ?? '');
$sessioni = sicurezza_sessioni_di((int)$io['id']);
$mia = hash('sha256', session_id());
$recuperoRimasti = count(json_decode((string)($io['totp_recupero'] ?? ''), true) ?: []);

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.acc-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.2rem; align-items:start; max-width:1100px }
.acc-qr { display:flex; gap:1.2rem; align-items:flex-start; flex-wrap:wrap; margin:1rem 0 }
.acc-qr svg { width:180px; height:180px; border:1px solid var(--border, #e8ddd0); border-radius:8px; background:#fff }
.acc-codici { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:.4rem .9rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:1rem; background:#fff8e6; border:1px solid #f1d58a; border-radius:8px; padding:1rem; margin:.8rem 0 }
.acc-ok { color:#15803d; font-weight:600 } .acc-no { color:#b45309; font-weight:600 }
.acc-segreto { font-family: ui-monospace, monospace; letter-spacing:.08em; word-break:break-all; background:#f4f1ec; padding:.4rem .6rem; border-radius:6px; display:inline-block }
</style>
<?php if ($obbligatorio): ?>
<div class="flash flash-error">Il secondo fattore e obbligatorio per gli amministratori di questo sito. Finche non lo attivi qui sotto, il resto del pannello resta chiuso.</div>
<?php endif; ?>
<?php if ($codiciNuovi): ?>
<div class="form-card" style="max-width:720px;border:2px solid #f1d58a">
  <h3>Codici di recupero: salvali adesso</h3>
  <p style="font-size:.9rem">Ognuno vale una volta sola e ti fa entrare se perdi il telefono. Stampali o mettili nel gestore di password. <strong>Questa e l'unica volta che li vedi.</strong></p>
  <div class="acc-codici"><?php foreach ($codiciNuovi as $c): ?><span><?= h($c) ?></span><?php endforeach; ?></div>
</div>
<?php endif; ?>
<div class="acc-grid">
  <div class="form-card">
    <h3>Secondo fattore (app di autenticazione)</h3>
    <?php if (!empty($io['totp_attivo'])): ?>
      <p class="acc-ok">Attivo.</p>
      <p style="font-size:.88rem;color:var(--text-muted)">Al login, dopo la password, il pannello chiede il codice a sei cifre dell'app. Codici di recupero rimasti: <strong><?= $recuperoRimasti ?></strong>.</p>
      <form method="post" style="margin-top:1rem"><?= csrf_field() ?><input type="hidden" name="azione" value="recupero_nuovi">
        <div class="form-group"><label>La tua password (per generare codici di recupero nuovi)</label><input type="password" name="password" autocomplete="current-password" required></div>
        <button class="btn btn-secondary btn-sm">Genera codici di recupero nuovi</button></form>
      <?php if (!(sicurezza_cfg()['totp_admin'] && $io['role'] === 'admin')): ?>
      <form method="post" style="margin-top:1rem" data-conferma="Spegnere il secondo fattore? Il pannello tornera a chiedere solo la password."><?= csrf_field() ?><input type="hidden" name="azione" value="totp_spegni">
        <div class="form-group"><label>La tua password (per spegnere)</label><input type="password" name="password" autocomplete="current-password" required></div>
        <button class="btn btn-danger btn-sm">Spegni il secondo fattore</button></form>
      <?php else: ?><p style="font-size:.85rem;color:var(--text-muted);margin-top:1rem">Obbligatorio per gli amministratori: non si puo spegnere da qui.</p><?php endif; ?>
    <?php elseif ($bozza !== ''): ?>
      <p style="font-size:.9rem"><strong>Passo 1.</strong> Apri sul telefono un'app di autenticazione (Google Authenticator, Microsoft Authenticator, Aegis, 1Password, Bitwarden...) e scegli "aggiungi account" leggendo questo codice QR.</p>
      <div class="acc-qr">
        <?= sicurezza_qr_svg(sicurezza_totp_uri($bozza, (string)$io['username']), 4) ?>
        <div style="font-size:.85rem;max-width:320px">Se non puoi leggere il QR, scrivi la chiave a mano nell'app:<br><span class="acc-segreto"><?= h(trim(chunk_split($bozza, 4, ' '))) ?></span><br><small>Tipo: basato sul tempo (TOTP), 6 cifre, 30 secondi.</small></div>
      </div>
      <p style="font-size:.9rem"><strong>Passo 2.</strong> Scrivi qui il codice a sei cifre che l'app mostra adesso.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="totp_conferma">
        <div class="form-group"><input type="text" name="codice" inputmode="numeric" pattern="[0-9 ]{6,7}" required autofocus autocomplete="one-time-code" style="font-size:1.3rem;letter-spacing:.2em;max-width:200px"></div>
        <button class="btn btn-primary">Attiva il secondo fattore</button></form>
      <form method="post" style="margin-top:.6rem"><?= csrf_field() ?><input type="hidden" name="azione" value="totp_inizia"><button class="btn btn-secondary btn-sm">Genera una chiave nuova</button></form>
    <?php else: ?>
      <p class="acc-no">Spento.</p>
      <p style="font-size:.88rem;color:var(--text-muted)">Con il secondo fattore, chi ruba la tua password non entra: serve anche il codice che cambia ogni 30 secondi sul tuo telefono. Ti servono: un telefono con un'app di autenticazione gratuita, due minuti, e un posto dove salvare otto codici di recupero.</p>
      <form method="post" style="margin-top:1rem"><?= csrf_field() ?><input type="hidden" name="azione" value="totp_inizia"><button class="btn btn-primary">Attiva il secondo fattore</button></form>
    <?php endif; ?>
  </div>
  <div class="form-card">
    <h3>Dispositivi collegati</h3>
    <p style="font-size:.88rem;color:var(--text-muted)">Le sessioni aperte con il tuo accesso. Se ne vedi una che non riconosci, chiudile tutte e cambia la password.</p>
    <div class="admin-table-wrap"><table>
      <thead><tr><th>Dove</th><th>Browser</th><th>Aperta</th><th>Ultima attivita</th></tr></thead>
      <tbody>
      <?php foreach ($sessioni as $s): ?>
        <tr><td><?= h($s['ip']) ?><?= $s['sid'] === $mia ? ' <span class="badge badge-published">questa</span>' : '' ?></td><td style="font-size:.8rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($s['user_agent']) ?>"><?= h(mb_substr($s['user_agent'], 0, 60)) ?></td><td><?= h(date('d/m H:i', strtotime($s['creata']))) ?></td><td><?= h(date('d/m H:i', strtotime($s['ultimo']))) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <form method="post" style="margin-top:1rem" data-conferma="Chiudere tutte le altre sessioni?"><?= csrf_field() ?><input type="hidden" name="azione" value="esci_tutti"><button class="btn btn-secondary">Esci da tutti gli altri dispositivi</button></form>
    <p style="font-size:.85rem;margin-top:1.2rem"><a href="password.php">Cambia nome utente, email o password</a></p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
