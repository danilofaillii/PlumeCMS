<?php
require_once __DIR__ . '/includes/auth.php';
// sicurezza (attacco 2): pagina per anonimi, nessun dettaglio tecnico a video; tutto nel log.
ini_set('display_errors', '0');

// Dove andare dopo l'accesso: la dashboard, oppure Copia del sito (?dopo=copia-sito), oppure (2.32.0) il consenso di
// un'AI che chiede di collegarsi (?dopo=ai-collegate&r=<richiesta>, addon AI collegate). Il modulo ripete la query nel POST.
$dopoLogin = (($_GET['dopo'] ?? '') === 'copia-sito') ? 'copia-sito.php?ultimo=1' : 'index.php';
if (($_GET['dopo'] ?? '') === 'ai-collegate' && preg_match('/^[a-f0-9]{32}$/', (string)($_GET['r'] ?? ''))) $dopoLogin = 'addon-pagina.php?a=ai-collegate&p=consenso&r=' . $_GET['r'];
if (is_logged_in()) { header('Location: ' . $dopoLogin); exit; }

$error = '';
$avviso = '';
if (isset($_GET['annulla'])) { unset($_SESSION['sic_pre']); header('Location: login.php'); exit; }
$passo2 = !empty($_SESSION['sic_pre']) && time() - (int)$_SESSION['sic_pre']['quando'] <= 300;
$motivi = ['scaduta' => 'La sessione è scaduta: entra di nuovo.', 'inattiva' => 'Sei rimasto fermo troppo a lungo: entra di nuovo.', 'revocata' => 'Questa sessione è stata chiusa (cambio password o "Esci da tutti i dispositivi"): entra di nuovo.',
    'ip' => 'Il tuo indirizzo IP non è fra quelli consentiti per il pannello.', 'reimpostata' => 'Password reimpostata: entra con quella nuova.',
    // 2.25.1: dopo un ripristino da una copia con altri utenti si rientra con quelli, e si torna in Copia del sito con il registro.
    'ripristino' => 'Il sito è stato ripristinato da una copia che ha altri utenti: entra con il nome e la password di quella copia. Dopo, il registro del ripristino ti aspetta in Copia del sito.'];
if (isset($_GET['motivo']) && isset($motivi[$_GET['motivo']])) $avviso = $motivi[$_GET['motivo']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!sicurezza_origine_ok()) {
            $error = 'Richiesta rifiutata: apri il pannello dal suo indirizzo e riprova.';
        } elseif ($passo2 && isset($_POST['codice'])) {
            // sicurezza: secondo passo, il codice dell'app di autenticazione (o un codice di recupero).
            if (login_2fa((string)$_POST['codice'])) { header('Location: ' . $dopoLogin); exit; }
            $passo2 = !empty($_SESSION['sic_pre']);
            $error = $passo2 ? 'Codice non valido. Riprova, oppure usa un codice di recupero.' : 'Tempo scaduto: ricomincia con nome e password.';
        } else {
            unset($_SESSION['sic_pre']);
            $user = trim($_POST['username'] ?? '');
            $pass = $_POST['password'] ?? '';
            $secondi = 0;
            $esito = login_esteso($user, $pass, $secondi);
            if ($esito === 'ok') { header('Location: ' . $dopoLogin); exit; }
            if ($esito === '2fa') { $passo2 = true; }
            elseif ($esito === 'bloccato') $error = 'Troppi tentativi: riprova fra ' . max(1, (int)ceil($secondi / 60)) . ' minuti. Se hai dimenticato la password, usa il link qui sotto.';
            else $error = 'Nome utente o password non corretti. Controlla le maiuscole e riprova.';
        }
    } catch (Throwable $e) {
        error_log('login: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $error = 'Errore temporaneo del pannello: riprova fra poco. Se continua, il dettaglio è nel log degli errori.';
    }
}
?>
<!DOCTYPE html>
<html lang="it" data-pannello="aria"><?php /* 1.4.0: il login e sempre Aria: non si sa ancora chi entra */ ?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accesso al pannello</title>
  <link rel="stylesheet" href="<?= pannello_file('assets/css/aspetti.css') ?>">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.2rem; padding: 1.5rem 1rem; }
    .marchio { font-size: 1.05rem; font-weight: 700; color: var(--text); letter-spacing: -.01em; }
    .marchio strong { color: var(--coral); }
    .box { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 400px; box-shadow: var(--ombra-grande); }
    .box h1 { font-size: 1.3rem; margin-bottom: 0.5rem; }
    .box p { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--text); margin-bottom: 0.3rem; }
    .form-group input { width: 100%; padding: 0.65rem 0.85rem; border: 1px solid var(--bordo-campo, var(--bordo-forte)); border-radius: 8px; font-size: 0.95rem; font-family: inherit; background: var(--campo); color: var(--text); }
    .form-group input:focus { outline: 2px solid var(--coral); outline-offset: 1px; border-color: var(--coral); }
    .btn { display: block; width: 100%; padding: 0.75rem; background: var(--coral); color: var(--su-accento); border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; font-family: inherit; }
    .btn:hover { background: var(--coral-hover); }
    .btn:focus-visible { outline: 2px solid var(--coral); outline-offset: 2px; }
    .error { color: var(--errore-testo); font-size: 0.85rem; margin-bottom: 1rem; padding: 0.75rem; background: var(--errore-chiaro); border: 1px solid var(--errore-bordo); border-radius: 8px; }
    .info { color: var(--info-testo); font-size: 0.85rem; margin-bottom: 1rem; padding: 0.75rem; background: var(--info-chiaro); border: 1px solid var(--info-bordo); border-radius: 8px; }
    .sotto { margin-top: 1.2rem; font-size: 0.85rem; text-align: center; }
    .sotto a { color: var(--link); }
  </style>
</head>
<body>
<div class="marchio" aria-hidden="true"><strong>plume</strong>CMS</div>
<div class="box">
  <h1><?= htmlspecialchars((string)cms_impostazione('sito_nome', CMS_NOME)) ?></h1>
  <p><?= $passo2 ? 'Secondo passo: il codice della tua app di autenticazione.' : 'Pannello di amministrazione' ?></p>
  <?php if ($avviso): ?><div class="info"><?= htmlspecialchars($avviso) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
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
