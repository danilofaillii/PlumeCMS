<?php
/**
 * Nome utente e password del pannello: si entra da /new/admin/login.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_login();

$adminTitle = 'Nome utente e password';
$utente = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nome = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password2'] ?? '';
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $nome)) {
        flash('error', 'Nome utente: da 3 a 50 caratteri, solo lettere, numeri, punto, trattino.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Email non valida.');
    } elseif (($errPw = sicurezza_password_errore($p1, $nome, $email)) !== '') { // sicurezza: lunghezza, non uguale al nome, non fra le piu comuni
        flash('error', $errPw);
    } elseif ($p1 !== $p2) {
        flash('error', 'Le due password sono diverse.');
    } else {
        $hash = sicurezza_password_hash($p1);
        if ($utente) {
            db()->prepare('UPDATE ' . tbl('users') . ' SET username = :u, email = :e WHERE id = :id')
                ->execute([':u' => $nome, ':e' => $email, ':id' => $utente['id']]);
            // sicurezza: la password nuova chiude tutte le altre sessioni di questo utente; questa resta aperta.
            sicurezza_password_imposta((int)$utente['id'], $p1, true);
            sicurezza_log('password_cambiata', $nome, (int)$utente['id']);
        } else {
            db()->prepare('INSERT INTO ' . tbl('users') . ' (id, username, email, password_hash, display_name, role) VALUES (:id, :u, :e, :h, :d, "admin")')
                ->execute([':id' => (int)$_SESSION['admin_user_id'], ':u' => $nome, ':e' => $email, ':h' => $hash, ':d' => $nome]);
        }
        flash('success', 'Credenziali salvate. Da adesso entri anche da ' . cms_base() . '/admin/login.php.');
        header('Location: password.php');
        exit;
    }
}

require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="form-card" style="max-width:560px">
  <h3>Come entri nel pannello</h3>
  <p style="font-size:0.9rem;color:var(--text-muted)">Indirizzo: <strong><?= h(cms_url_pannello()) ?>login.php</strong>. Scegli una password lunga e salvala nel tuo gestore di password. Cambiandola, le altre sessioni aperte si chiudono. Secondo fattore e dispositivi collegati: <a href="account.php">Il mio accesso</a>.</p>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-group"><label for="username">Nome utente</label>
      <input type="text" id="username" name="username" value="<?= h($utente['username'] ?? '') ?>" required autocomplete="username"></div>
    <div class="form-group"><label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= h($utente['email'] ?? '') ?>" required></div>
    <div class="form-group"><label for="password">Nuova password (almeno 12 caratteri)</label>
      <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password"></div>
    <div class="form-group"><label for="password2">Ripeti la password</label>
      <input type="password" id="password2" name="password2" required minlength="12" autocomplete="new-password"></div>
    <button type="submit" class="btn btn-primary">Salva</button>
  </form>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
