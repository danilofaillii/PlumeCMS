<?php
/**
 * Utenti del pannello: chi entra, con quale ruolo. Admin fa tutto; redattore scrive e pubblica
 * contenuti ma non tocca utenti, impostazioni, aggiornamenti e SQL.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_admin();
$adminTitle = 'Utenti';
$io = current_user();
$sonoAdmin = ($io['role'] ?? 'admin') === 'admin';
const UTENTI_RUOLI = ['admin' => 'Amministratore', 'editor' => 'Redattore'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (!$sonoAdmin) throw new RuntimeException('Solo un amministratore gestisce gli utenti.');
        if (($_POST['azione'] ?? '') === 'esci_tutti') {
            // sicurezza: chiude ogni sessione di quell'utente (anche la mia, se sono io).
            $n = sicurezza_sessioni_chiudi($id, $id === (int)$io['id']);
            flash('success', "Chiuse $n sessioni dell'utente.");
        } elseif (($_POST['azione'] ?? '') === 'azzera_2fa') {
            if ($id === (int)$io['id']) throw new RuntimeException('Il tuo secondo fattore lo gestisci da "Il mio accesso".');
            db()->prepare('UPDATE ' . tbl('users') . ' SET totp_segreto = \'\', totp_attivo = 0, totp_recupero = NULL WHERE id = ?')->execute([$id]);
            sicurezza_log('2fa_disattivato', (string)$io['username'], (int)$io['id'], "azzerato dall'amministratore per l'utente $id");
            flash('success', 'Secondo fattore azzerato: al prossimo accesso basta la password (e, se obbligatorio, dovra riattivarlo).');
        } elseif (($_POST['azione'] ?? '') === 'elimina') {
            if ($id === (int)$io['id']) throw new RuntimeException('Non puoi eliminare il tuo stesso accesso.');
            $admin = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('users') . " WHERE role = 'admin' AND id <> " . $id)->fetchColumn();
            if ($admin === 0) throw new RuntimeException('Deve restare almeno un amministratore.');
            db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE id = ?')->execute([$id]);
            sicurezza_sessioni_chiudi($id);
            sicurezza_log('utente', (string)$io['username'], (int)$io['id'], "eliminato l'utente $id");
            flash('success', 'Utente eliminato.');
        } else {
            $nome = trim((string)($_POST['username'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $mostrato = trim((string)($_POST['display_name'] ?? '')) ?: $nome;
            $ruolo = isset(UTENTI_RUOLI[$_POST['role'] ?? '']) ? $_POST['role'] : 'editor';
            $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $nome)) throw new RuntimeException('Nome utente: da 3 a 50 caratteri, solo lettere, numeri, punto, trattino.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Email non valida.');
            if ($p1 !== '' && ($errPw = sicurezza_password_errore($p1, $nome, $email)) !== '') throw new RuntimeException($errPw); // sicurezza
            if ($p1 !== $p2) throw new RuntimeException('Le due password sono diverse.');
            if (!$id && $p1 === '') throw new RuntimeException('Un utente nuovo ha bisogno di una password.');
            $c = db()->prepare('SELECT COUNT(*) FROM ' . tbl('users') . ' WHERE (username = ? OR email = ?) AND id <> ?');
            $c->execute([$nome, $email, $id]);
            if ($c->fetchColumn()) throw new RuntimeException('Nome utente o email già usati da un altro utente.');
            if ($id === (int)$io['id'] && $ruolo !== 'admin') throw new RuntimeException('Non puoi toglierti da solo il ruolo di amministratore.');
            if ($id) {
                db()->prepare('UPDATE ' . tbl('users') . ' SET username = ?, email = ?, display_name = ?, role = ?' . ($p1 !== '' ? ', password_hash = ?' : '') . ' WHERE id = ?')
                    ->execute(array_merge([$nome, $email, $mostrato, $ruolo], $p1 !== '' ? [sicurezza_password_hash($p1)] : [], [$id]));
                if ($p1 !== '') sicurezza_password_imposta($id, $p1, $id === (int)$io['id']); // sicurezza: password nuova, sessioni chiuse
                sicurezza_log('utente', (string)$io['username'], (int)$io['id'], "modificato $nome ($ruolo)" . ($p1 !== '' ? ', password nuova' : ''));
                flash('success', 'Utente aggiornato.');
            } else {
                db()->prepare('INSERT INTO ' . tbl('users') . ' (username, email, display_name, role, password_hash) VALUES (?,?,?,?,?)')->execute([$nome, $email, $mostrato, $ruolo, sicurezza_password_hash($p1)]);
                sicurezza_log('utente', (string)$io['username'], (int)$io['id'], "creato $nome ($ruolo)");
                flash('success', 'Utente creato: può entrare da ' . cms_base() . '/admin/login.php.');
            }
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: utenti.php');
    exit;
}

sicurezza_schema();
$utenti = db()->query('SELECT id, username, email, display_name, role, last_login, created_at, totp_attivo FROM ' . tbl('users') . ' ORDER BY username')->fetchAll();
$sessioniPer = [];
foreach (db()->query('SELECT utente_id, COUNT(*) n FROM ' . tbl('sicurezza_sessioni') . ' GROUP BY utente_id')->fetchAll() as $r) $sessioniPer[(int)$r['utente_id']] = (int)$r['n'];
$modifica = isset($_GET['modifica']) ? array_values(array_filter($utenti, fn($u) => (int)$u['id'] === (int)$_GET['modifica']))[0] ?? null : null;
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<div style="display:grid;grid-template-columns:320px minmax(0,1fr);gap:1.2rem;align-items:start">
  <div class="sc-scatola">
    <h3><?= $modifica ? 'Modifica utente' : 'Aggiungi utente' ?></h3>
    <form method="post" class="sc-corpo" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $modifica['id'] ?? 0 ?>">
      <label>Nome utente</label><input type="text" name="username" value="<?= h($modifica['username'] ?? '') ?>" required autocomplete="off">
      <label>Email</label><input type="text" name="email" value="<?= h($modifica['email'] ?? '') ?>" required>
      <label>Nome mostrato</label><input type="text" name="display_name" value="<?= h($modifica['display_name'] ?? '') ?>">
      <label>Ruolo</label>
      <select name="role"><?php foreach (UTENTI_RUOLI as $k => $n): ?><option value="<?= $k ?>" <?= ($modifica['role'] ?? 'editor') === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
      <div class="hint">Il redattore scrive e pubblica; non vede utenti, impostazioni, aggiornamenti e SQL.</div>
      <label><?= $modifica ? 'Nuova password (vuota = resta quella)' : 'Password (almeno 12 caratteri)' ?></label><input type="password" name="password" autocomplete="new-password" <?= $modifica ? '' : 'required' ?>>
      <label>Ripeti la password</label><input type="password" name="password2" autocomplete="new-password">
      <div style="display:flex;gap:.5rem;margin-top:.8rem"><button class="btn btn-primary btn-sm" <?= $sonoAdmin ? '' : 'disabled' ?>><?= $modifica ? 'Salva' : 'Aggiungi' ?></button><?php if ($modifica): ?><a class="btn btn-secondary btn-sm" href="utenti.php">Annulla</a><?php endif; ?></div>
    </form>
  </div>
  <div class="admin-table-wrap ct-tabella">
    <table>
      <thead><tr><th>Utente</th><th>Email</th><th>Ruolo</th><th>Ultimo accesso</th><th>2FA</th><th>Sessioni</th></tr></thead>
      <tbody>
      <?php foreach ($utenti as $u): ?>
        <tr class="ct-riga">
          <td class="ct-titolo"><a class="ct-nome" href="utenti.php?modifica=<?= $u['id'] ?>"><?= h($u['display_name'] ?: $u['username']) ?></a> <span class="ct-muto">(<?= h($u['username']) ?>)</span><?= (int)$u['id'] === (int)$io['id'] ? ' <span class="badge ct-badge-home">sei tu</span>' : '' ?>
            <div class="ct-azioni"><a href="utenti.php?modifica=<?= $u['id'] ?>">Modifica</a>
              <?php if ($sonoAdmin && (int)$u['id'] !== (int)$io['id']): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button type="submit" class="ct-rosso" data-conferma="Eliminare l'accesso di <?= h(($u['username'])) ?>?">Elimina</button></form><?php endif; ?>
            </div></td>
          <td class="ct-muto"><?= h($u['email']) ?></td>
          <td class="ct-muto"><?= UTENTI_RUOLI[$u['role']] ?? h($u['role']) ?></td>
          <td class="ct-muto"><?= $u['last_login'] ? h(substr($u['last_login'], 0, 16)) : 'mai' ?></td>
          <td class="ct-muto"><?= !empty($u['totp_attivo']) ? '<span class="badge badge-published">attivo</span>' : 'no' ?>
            <?php if ($sonoAdmin && !empty($u['totp_attivo']) && (int)$u['id'] !== (int)$io['id']): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="azione" value="azzera_2fa"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button type="submit" class="ct-rosso" data-conferma="Azzerare il secondo fattore di questo utente? Serve se ha perso il telefono.">azzera</button></form><?php endif; ?></td>
          <td class="ct-muto"><?= (int)($sessioniPer[(int)$u['id']] ?? 0) ?>
            <?php if ($sonoAdmin): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="azione" value="esci_tutti"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button type="submit" class="ct-rosso" data-conferma="Chiudere tutte le sessioni di questo utente?">esci da tutti</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
