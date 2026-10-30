<?php
/**
 * Impostazioni > Addon (2.0.0): l'elenco degli addon che arrivano con plumeCMS, ognuno con Accendi o Spegni.
 * Solo amministratore, ogni azione in POST con il token CSRF. Spegnere non cancella dati (includes/addon.php).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/addon.php';
require_admin();

$adminTitle = 'Addon';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nome = (string)($_POST['addon'] ?? '');
    $acceso = ($_POST['azione'] ?? '') === 'accendi';
    try {
        $log = addon_attiva($nome, $acceso);
        $et = addon_disponibili()[$nome]['etichetta'] ?? $nome;
        if (function_exists('sicurezza_log')) sicurezza_log('addon', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), ($acceso ? 'acceso ' : 'spento ') . $nome);
        flash('success', ($acceso ? '«' . $et . '» è acceso: lo trovi nel menu a sinistra.' : '«' . $et . '» è spento. I suoi dati restano: se lo riaccendi, li ritrovi.') . ($log ? ' ' . implode(' ', $log) : ''));
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    header('Location: addon.php'); exit;
}
$tutti = addon_disponibili();
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>.ad-griglia{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1rem}.ad-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.5rem}.ad-card.acceso{border-color:var(--accent,#2563eb);box-shadow:inset 4px 0 0 var(--accent,#2563eb)}.ad-card h2{font-size:1.05rem;margin:0;display:flex;gap:.5rem;align-items:center;justify-content:space-between}.ad-card p{margin:0;font-size:.9rem;color:var(--text-muted)}.ad-card form{margin-top:auto;padding-top:.4rem}.ad-richiede{font-size:.82rem}</style>
<p style="max-width:62rem">Gli addon sono funzioni in più che arrivano con plumeCMS: accendi solo quelle che ti servono. Un addon acceso aggiunge le sue voci al menu e i suoi widget all'editor. Spento, sparisce, ma i suoi dati restano dove sono.</p>
<?php if (!$tutti): ?><p>Nessun addon in questa installazione.</p><?php else: ?>
<div class="ad-griglia">
<?php foreach ($tutti as $nome => $d): ?>
  <div class="ad-card<?= $d['acceso'] ? ' acceso' : '' ?>" data-addon="<?= h($nome) ?>">
    <h2><?= h((string)$d['etichetta']) ?> <span class="badge <?= $d['acceso'] ? 'badge-published' : 'badge-draft' ?>"><?= $d['acceso'] ? 'Acceso' : 'Spento' ?></span></h2>
    <p><?= h((string)$d['descrizione']) ?></p>
    <?php if ($d['richiede']): ?><p class="ad-richiede">Richiede: <?= h(implode(', ', array_map(fn($r) => (string)($tutti[$r]['etichetta'] ?? $r), (array)$d['richiede']))) ?> (si accende da solo).</p><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="addon" value="<?= h($nome) ?>">
      <?php if ($d['acceso']): ?><button type="submit" name="azione" value="spegni" class="btn btn-secondary" data-conferma="Spegnere «<?= h((string)$d['etichetta']) ?>»? I dati restano.">Spegni</button>
      <?php else: ?><button type="submit" name="azione" value="accendi" class="btn btn-primary">Accendi</button><?php endif; ?>
    </form>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php';
