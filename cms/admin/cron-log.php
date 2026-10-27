<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_admin(); // sicurezza (ruoli): solo amministratore

$adminTitle = 'Log Cron';

$logFile = dirname(__DIR__) . '/logs/cron-daily.log';
$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
$logSize = file_exists($logFile) ? filesize($logFile) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear']) && csrf_ok()) { // sicurezza: azione in POST, confronto hash_equals
    @unlink($logFile);
    flash('success', 'Log cancellato.');
    header('Location: cron-log.php');
    exit;
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="max-width:900px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <p style="color:var(--text-muted);font-size:0.85rem">
      <?php if ($logContent): ?>
        <?= round($logSize/1024, 1) ?> KB · Ultimo aggiornamento: <?= date('d/m/Y H:i:s', filemtime($logFile)) ?>
      <?php else: ?>
        Nessun log ancora. Il cron non è stato eseguito.
      <?php endif; ?>
    </p>
    <div style="display:flex;gap:0.5rem">
      <?php if ($logContent): ?>
      <form method="post" data-conferma="Cancellare il log?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="clear" value="1"><button type="submit" class="btn btn-secondary btn-sm" style="color:#b91c1c">Svuota log</button></form>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-card">
    <?php if ($logContent): ?>
    <pre style="background:#1e1e1e;color:#d4d4d4;padding:1rem;border-radius:8px;font-size:0.82rem;max-height:500px;overflow:auto;white-space:pre-wrap"><?= htmlspecialchars($logContent) ?></pre>
    <?php else: ?>
    <div style="text-align:center;padding:3rem;color:var(--text-muted)">
      <p style="font-size:1.2rem;margin-bottom:0.5rem">Nessun log</p>
      <p>Il cron non è stato ancora eseguito, oppure il file di log non è stato creato.</p>
      <p style="margin-top:1rem">Per testare manualmente:</p>
      <ol style="text-align:left;display:inline-block;margin-top:0.5rem;font-size:0.85rem">
        <li>Clicca <strong>"Test connessione WP"</strong> per verificare le credenziali</li>
        <li>Clicca <strong>"Dry run"</strong> per simulare senza pubblicare</li>
        <li>Clicca <strong>"Pubblica ora"</strong> per pubblicare gli articoli</li>
      </ol>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
