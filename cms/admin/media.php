<?php
/**
 * Libreria Media: tutte le immagini del sito, da ogni cartella (archivio per anno, pillar, editor,
 * newsletter, copertine). Motore in includes/media.php, lo stesso della finestra "Scegli" dell'editor.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/media.php';
require_login();

$adminTitle = 'Libreria Media';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['elimina'])) {
        $ok = media_elimina((string)$_POST['elimina']);
        flash($ok ? 'success' : 'error', ($ok ? 'Cancellata: ' : 'Non si è potuta cancellare: ') . (string)$_POST['elimina']);
    } else {
        $r = media_carica($_FILES['file'] ?? null, 'pagine');
        if (isset($r['url'])) flash('success', 'Caricata: ' . $r['url']);
        else flash('error', $r['errore']);
    }
    header('Location: media.php?' . http_build_query(array_intersect_key($_GET, array_flip(['cartella', 'q', 'miniature']))));
    exit;
}

$cartella = (string)($_GET['cartella'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$miniature = !empty($_GET['miniature']);
$perPagina = 60;
$pagina = max(1, (int)($_GET['p'] ?? 1));
$tutti = media_filtra($cartella, $q, $miniature);
$totale = count($tutti);
$pagine = max(1, (int)ceil($totale / $perPagina));
$pagina = min($pagina, $pagine);
$voci = array_slice($tutti, ($pagina - 1) * $perPagina, $perPagina);
$conta = [];
foreach (media_elenco() as $m) if ($miniature || !$m['miniatura']) $conta[$m['cartella']] = ($conta[$m['cartella']] ?? 0) + 1;
$link = fn(array $extra) => 'media.php?' . http_build_query(array_filter($extra + ['cartella' => $cartella, 'q' => $q, 'miniature' => $miniature ? 1 : '', 'p' => 1], fn($v) => $v !== '' && $v !== null));

require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
  <p style="color:var(--text-muted);font-size:0.9rem;margin:0"><?= $totale ?> immagini<?= $q !== '' ? ' per «' . h($q) . '»' : '' ?><?= $miniature ? '' : ', senza le miniature' ?></p>
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:0.5rem;align-items:center">
    <?= csrf_field() ?>
    <input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,image/svg+xml" required style="font-size:0.85rem">
    <button type="submit" class="btn btn-primary btn-sm">Carica in uploads/pagine</button>
  </form>
</div>

<div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.8rem">
  <a href="<?= h($link(['cartella' => ''])) ?>" class="btn btn-sm <?= $cartella === '' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.78rem">Tutte (<?= array_sum($conta) ?>)</a>
  <?php foreach (media_cartelle() as $k => $nome): if (empty($conta[$k])) continue; ?>
  <a href="<?= h($link(['cartella' => $k])) ?>" class="btn btn-sm <?= $cartella === $k ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.78rem"><?= h($nome) ?> (<?= $conta[$k] ?>)</a>
  <?php endforeach; ?>
</div>

<form method="get" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1.2rem">
  <?php if ($cartella !== ''): ?><input type="hidden" name="cartella" value="<?= h($cartella) ?>"><?php endif; ?>
  <input type="text" name="q" class="search-input" placeholder="Cerca nel nome o nel percorso…" value="<?= h($q) ?>" style="min-width:260px">
  <label style="font-size:0.82rem;color:var(--text-muted);display:flex;gap:0.3rem;align-items:center"><input type="checkbox" name="miniature" value="1" <?= $miniature ? 'checked' : '' ?>> mostra anche le miniature (-300x300 e simili)</label>
  <button type="submit" class="btn btn-secondary btn-sm">Cerca</button>
</form>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem">
  <?php foreach ($voci as $img): ?>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
    <a href="<?= h($img['url']) ?>" target="_blank" style="aspect-ratio:16/9;overflow:hidden;background:var(--bg);display:flex;align-items:center;justify-content:center">
      <img src="<?= h($img['url']) ?>" alt="" loading="lazy" style="max-width:100%;max-height:100%;object-fit:contain">
    </a>
    <div style="padding:0.6rem 0.75rem">
      <div style="font-size:0.78rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($img['nome']) ?>"><?= h($img['nome']) ?></div>
      <div style="font-size:0.72rem;color:var(--text-muted);display:flex;justify-content:space-between;margin-top:0.2rem">
        <span><?= h(media_cartelle()[$img['cartella']] ?? $img['cartella']) ?><?= $img['sotto'] !== '' ? ' · ' . h($img['sotto']) : '' ?></span>
        <span><?= round($img['peso'] / 1024) ?> KB</span>
      </div>
      <input type="text" value="<?= h($img['url']) ?>" readonly title="Clic per copiare l'indirizzo"
             style="width:100%;font-size:0.7rem;padding:0.25rem 0.4rem;margin-top:0.4rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text-muted)"
             data-copia-se-stesso>
      <?php if (in_array($img['cartella'], ['pagine', 'pannello', 'newsletter'], true)): ?>
      <form method="post" style="margin-top:0.4rem" data-conferma="Cancellare <?= h($img['nome']) ?>? Se una pagina la usa, resterà un buco.">
        <?= csrf_field() ?><input type="hidden" name="elimina" value="<?= h($img['url']) ?>">
        <button type="submit" class="btn btn-secondary btn-sm" style="font-size:0.7rem;padding:0.2rem 0.5rem">Cancella</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (!$voci): ?>
<div class="empty-state"><p>Nessuna immagine qui.</p></div>
<?php endif; ?>

<?php if ($pagine > 1): ?>
<div style="display:flex;gap:0.4rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pagine; $i++): if ($pagine > 15 && abs($i - $pagina) > 4 && $i !== 1 && $i !== $pagine) { if (abs($i - $pagina) === 5) echo '<span style="color:var(--text-muted)">…</span>'; continue; } ?>
  <a href="<?= h($link(['p' => $i])) ?>" class="btn btn-sm <?= $i === $pagina ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.78rem"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
