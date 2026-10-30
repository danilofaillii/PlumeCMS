<?php
/**
 * Password dimenticata: si scrive l'email, se corrisponde a un utente arriva un link firmato monouso (30 minuti).
 * La risposta e sempre la stessa, che l'utente esista o no: da qui nessuno scopre chi ha un accesso.
 */
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php'; // h(): auth.php non la porta, senza questa riga un errore dava pagina bianca (500)
ini_set('display_errors', '0'); // pagina per anonimi: niente dettagli tecnici a video
if (is_logged_in()) { header('Location: index.php'); exit; }

$fatto = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $secondi = sicurezza_reset_attesa($email); // sicurezza: conteggio a parte, il login dell'utente non si blocca
    if (!sicurezza_origine_ok()) {
        $error = 'Richiesta rifiutata: apri il pannello dal suo indirizzo e riprova.';
    } elseif ($secondi > 0) {
        $error = 'Troppe richieste: riprova fra ' . max(1, (int)ceil($secondi / 60)) . ' minuti.';
    } else {
        $fatto = true;
        // Ogni richiesta conta (per IP e per email, a parte dal login): nessuno intasa una casella, nessuno chiude fuori il proprietario.
        sicurezza_reset_conta($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $st = db()->prepare('SELECT * FROM ' . tbl('users') . ' WHERE email = ?'); $st->execute([$email]);
            $u = $st->fetch();
            if ($u) {
                $t = sicurezza_token_crea('password', (int)$u['id'], 30);
                $link = cms_url_pannello() . 'password-reimposta.php?t=' . $t;
                require_once dirname(__DIR__) . '/includes/posta.php';
                posta_invia((string)$u['email'], 'Nuova password per il pannello di ' . cms_impostazione('sito_nome', CMS_NOME),
                    "Ciao {$u['username']},\n\nqualcuno (speriamo tu) ha chiesto di reimpostare la password del pannello.\n\nApri questo link entro 30 minuti e scegli la password nuova:\n$link\n\nSe non sei stato tu, ignora questa email: la password resta quella di adesso.\n\nRichiesta arrivata da IP " . sicurezza_ip() . ' il ' . date('d/m/Y \a\l\l\e H:i') . '.');
                sicurezza_log('password_reset_richiesto', (string)$u['username'], (int)$u['id']);
            } else {
                sicurezza_log('password_reset_richiesto', $email, 0, 'email sconosciuta');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it" data-pannello="aria">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password dimenticata &middot; Pannello</title>
  <link rel="stylesheet" href="<?= pannello_file('assets/css/aspetti.css') ?>"><link rel="stylesheet" href="<?= pannello_file('assets/css/admin.css') ?>">
</head>
<body class="login-page">
<div class="login-box">
  <h1>Password dimenticata</h1>
  <?php if ($fatto): ?>
    <p>Se l'indirizzo corrisponde a un utente del pannello, fra poco arriva un'email con il link per scegliere la password nuova. Il link vale 30 minuti e funziona una volta sola. Controlla anche nello spam.</p>
    <p><a href="login.php">Torna al login</a></p>
  <?php else: ?>
    <p>Scrivi l'email del tuo accesso: ti mandiamo un link per scegliere una password nuova.</p>
    <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" required autofocus></div>
      <button type="submit" class="btn btn-primary" style="width:100%">Mandami il link</button>
    </form>
    <p style="margin-top:1rem;font-size:.85rem"><a href="login.php">Torna al login</a></p>
  <?php endif; ?>
</div>
</body>
</html>
