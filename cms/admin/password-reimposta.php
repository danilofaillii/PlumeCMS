<?php
/**
 * Nuova password dal link ricevuto via email (password-dimenticata.php). Il token e monouso e scade dopo 30 minuti;
 * la password nuova chiude tutte le sessioni aperte di quell'utente.
 */
require_once __DIR__ . '/includes/auth.php';
ini_set('display_errors', '0'); // pagina per anonimi: niente dettagli tecnici a video
if (is_logged_in()) { header('Location: index.php'); exit; }

$t = (string)($_REQUEST['t'] ?? '');
$uid = sicurezza_token_leggi('password', $t);
$error = ''; $fatto = false;
if ($uid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT * FROM ' . tbl('users') . ' WHERE id = ?'); $st->execute([$uid]);
    $u = $st->fetch();
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    if (!sicurezza_origine_ok()) $error = 'Richiesta rifiutata: apri il link dal browser e riprova.';
    elseif (!$u) $error = 'Utente non trovato.';
    elseif (($e = sicurezza_password_errore($p1, (string)$u['username'], (string)$u['email'])) !== '') $error = $e;
    elseif ($p1 !== $p2) $error = 'Le due password sono diverse.';
    else {
        sicurezza_token_leggi('password', $t, true);
        sicurezza_password_imposta($uid, $p1, false);
        sicurezza_login_riuscito((string)$u['username']);
        sicurezza_log('password_reset', (string)$u['username'], $uid);
        sicurezza_avvisa('Password reimpostata via email', 'L\'utente ' . $u['username'] . ' ha reimpostato la password con il link ricevuto via email. Tutte le sue sessioni precedenti sono state chiuse.');
        header('Location: login.php?motivo=reimpostata');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nuova password — Admin</title>
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-box">
  <h1>Scegli la password nuova</h1>
  <?php if (!$uid): ?>
    <p>Questo link non e valido o e scaduto (vale 30 minuti e si usa una volta sola).</p>
    <p><a href="password-dimenticata.php">Chiedi un link nuovo</a></p>
  <?php else: ?>
    <p>Almeno 12 caratteri, diversa dal nome utente e dalle password piu comuni. Dopo il salvataggio tutte le sessioni aperte si chiudono.</p>
    <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="t" value="<?= h($t) ?>">
      <div class="form-group"><label for="password">Password nuova</label><input type="password" id="password" name="password" required minlength="12" autocomplete="new-password" autofocus></div>
      <div class="form-group"><label for="password2">Ripeti la password</label><input type="password" id="password2" name="password2" required minlength="12" autocomplete="new-password"></div>
      <button type="submit" class="btn btn-primary" style="width:100%">Salva e vai al login</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
