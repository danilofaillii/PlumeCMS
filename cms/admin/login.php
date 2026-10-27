<?php
require_once __DIR__ . '/includes/auth.php';
// sicurezza (attacco 2): pagina per anonimi, nessun dettaglio tecnico a video; tutto nel log.
ini_set('display_errors', '0');

if (is_logged_in()) { header('Location: index.php'); exit; }

$error = '';
$avviso = '';
if (isset($_GET['annulla'])) { unset($_SESSION['sic_pre']); header('Location: login.php'); exit; }
$passo2 = !empty($_SESSION['sic_pre']) && time() - (int)$_SESSION['sic_pre']['quando'] <= 300;
$motivi = ['scaduta' => 'La sessione e scaduta: entra di nuovo.', 'inattiva' => 'Sei rimasto fermo troppo a lungo: entra di nuovo.', 'revocata' => 'Questa sessione e stata chiusa (cambio password o "Esci da tutti i dispositivi"): entra di nuovo.',
    'ip' => 'Il tuo indirizzo IP non e fra quelli consentiti per il pannello.', 'reimpostata' => 'Password reimpostata: entra con quella nuova.'];
if (isset($_GET['motivo']) && isset($motivi[$_GET['motivo']])) $avviso = $motivi[$_GET['motivo']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!sicurezza_origine_ok()) {
            $error = 'Richiesta rifiutata: apri il pannello dal suo indirizzo e riprova.';
        } elseif ($passo2 && isset($_POST['codice'])) {
            // sicurezza: secondo passo, il codice dell'app di autenticazione (o un codice di recupero).
            if (login_2fa((string)$_POST['codice'])) { header('Location: index.php'); exit; }
            $passo2 = !empty($_SESSION['sic_pre']);
            $error = $passo2 ? 'Codice non valido. Riprova, oppure usa un codice di recupero.' : 'Tempo scaduto: ricomincia con nome e password.';
        } else {
            unset($_SESSION['sic_pre']);
            $user = trim($_POST['username'] ?? '');
            $pass = $_POST['password'] ?? '';
            $secondi = 0;
            $esito = login_esteso($user, $pass, $secondi);
            if ($esito === 'ok') { header('Location: index.php'); exit; }
            if ($esito === '2fa') { $passo2 = true; }
            elseif ($esito === 'bloccato') $error = 'Troppi tentativi: riprova fra ' . max(1, (int)ceil($secondi / 60)) . ' minuti. Se hai dimenticato la password, usa il link qui sotto.';
            else $error = 'Credenziali non valide.';
        }
    } catch (Throwable $e) {
        error_log('login: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $error = 'Errore temporaneo del pannello: riprova fra poco. Se continua, il dettaglio è nel log degli errori.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: #f4f1ec; color: #2d1b0e; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .box { background: white; border: 1px solid #e8ddd0; border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 400px; box-shadow: 0 8px 32px rgba(45,27,14,0.08); }
    .box h1 { font-size: 1.3rem; margin-bottom: 0.5rem; }
    .box p { font-size: 0.88rem; color: #6b4c35; margin-bottom: 1.5rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #9c7b65; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.03em; }
    .form-group input { width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #e8ddd0; border-radius: 8px; font-size: 0.92rem; font-family: inherit; }
    .form-group input:focus { outline: none; border-color: #e8553e; box-shadow: 0 0 0 3px #fde8e3; }
    .btn { display: block; width: 100%; padding: 0.75rem; background: #e8553e; color: white; border: none; border-radius: 8px; font-size: 0.92rem; font-weight: 600; cursor: pointer; font-family: inherit; }
    .btn:hover { background: #d03f28; }
    .error { color: #b91c1c; font-size: 0.85rem; margin-bottom: 1rem; padding: 0.75rem; background: #fde8e3; border-radius: 8px; }
    .info { color: #4a3728; font-size: 0.85rem; margin-bottom: 1rem; padding: 0.75rem; background: #f4f1ec; border-radius: 8px; }
    .sotto { margin-top: 1.2rem; font-size: 0.82rem; text-align: center; }
    .sotto a { color: #9c7b65; }
  </style>
</head>
<body>
<div class="box">
  <h1><?= htmlspecialchars((string)cms_impostazione('sito_nome', CMS_NOME)) ?></h1>
  <p><?= $passo2 ? 'Secondo passo: il codice della tua app di autenticazione.' : 'Pannello di amministrazione' ?></p>
  <?php if ($avviso): ?><div class="info"><?= htmlspecialchars($avviso) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($passo2): ?>
  <form method="post">
    <div class="form-group">
      <label for="codice">Codice a sei cifre (o codice di recupero)</label>
      <input type="text" id="codice" name="codice" required autofocus autocomplete="one-time-code" inputmode="numeric" pattern="[0-9a-zA-Z-]{6,11}">
    </div>
    <button type="submit" class="btn">Conferma</button>
  </form>
  <div class="sotto"><a href="login.php?annulla=1">Torna a nome e password</a></div>
  <?php else: ?>
  <form method="post">
    <div class="form-group">
      <label for="username">Username o Email</label>
      <input type="text" id="username" name="username" required autofocus autocomplete="username">
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn">Accedi</button>
  </form>
  <div class="sotto"><a href="password-dimenticata.php">Password dimenticata?</a></div>
  <?php endif; ?>
</div>
</body>
</html>
