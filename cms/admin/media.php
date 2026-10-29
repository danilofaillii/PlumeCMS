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
    if (($_POST['azione'] ?? '') === 'foto_impostazioni') {
        // 1.4.0: foto più leggere, acceso o spento e lato massimo. Solo l'amministratore.
        if (!is_admin()) { http_response_code(403); exit('Solo l\'amministratore cambia questa impostazione.'); }
        $lato = (int)($_POST['foto_lato_max'] ?? 0);
        if ($lato < 800 || $lato > 8000) flash('error', 'Il lato massimo va da 800 a 8000 pixel.');
        else {
            save_setting('foto_leggere', !empty($_POST['foto_leggere']) ? '1' : '0');
            save_setting('foto_lato_max', (string)$lato);
            flash('success', !empty($_POST['foto_leggere']) ? "Foto più leggere: acceso, lato massimo $lato px." : 'Foto più leggere: spento. Le foto nuove restano come arrivano.');
        }
    } elseif (($_POST['azione'] ?? '') === 'sostituisci') {
        // 1.7.0: una foto vera al posto di un'immagine finta del sito su misura, in tutte le pagine. Solo l'amministratore:
        // cambia anche header, footer e modelli.
        if (!is_admin()) { http_response_code(403); exit('Solo l\'amministratore sostituisce le immagini finte.'); }
        require_once dirname(__DIR__) . '/includes/sito-su-misura.php';
        $r = su_misura_sostituisci((string)($_POST['segnaposto'] ?? ''), $_FILES['file'] ?? null);
        if (isset($r['url'])) flash('success', 'Fatto: la foto nuova ha preso il posto di quella finta' . ($r['righe'] ? ' (' . $r['righe'] . ($r['righe'] === 1 ? ' punto del sito)' : ' punti del sito)') : ', che nessuna pagina mostrava più') . '.');
        else flash('error', $r['errore']);
    } elseif (isset($_POST['elimina'])) {
        $ok = media_elimina((string)$_POST['elimina']);
        flash($ok ? 'success' : 'error', ($ok ? 'Cancellata: ' : 'Non si è potuta cancellare: ') . (string)$_POST['elimina']);
    } else {
        $r = media_carica($_FILES['file'] ?? null, 'pagine');
        if (isset($r['url'])) flash('success', 'Caricata: ' . $r['url'] . (!empty($r['webp']) ? ' (con la versione WebP accanto)' : ''));
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

<?php if (is_admin()): $fotoCfg = foto_impostazioni(); $motore = foto_motore(); ?>
<details class="form-card md-foto" id="foto-leggere"<?= isset($_GET['foto']) ? ' open' : '' ?>>
  <summary><b>Foto più leggere</b> <span class="badge <?= $fotoCfg['attivo'] && $motore ? 'badge-published' : 'badge-draft' ?>"><?= $fotoCfg['attivo'] && $motore ? 'acceso' : 'spento' ?></span></summary>
  <p class="hint" style="margin:.6rem 0 1rem">Quando carichi una foto (qui, nell'editor o nella newsletter) il CMS la raddrizza se il telefono l'ha scattata di lato, la rimpicciolisce se il lato lungo supera il limite, toglie i dati nascosti (anche la posizione GPS) e prepara accanto una versione WebP, più leggera, che il sito manda ai browser che la accettano. le foto già caricate non cambiano.</p>
  <?php if ($motore === ''): ?><div class="flash flash-info">Su questo server mancano GD e Imagick: le foto restano come arrivano. Chiedi al fornitore dello spazio di attivare l'estensione GD di PHP.</div><?php endif; ?>
  <form method="post" class="md-foto-campi"><?= csrf_field() ?><input type="hidden" name="azione" value="foto_impostazioni">
    <label class="md-spunta"><input type="checkbox" name="foto_leggere" value="1"<?= $fotoCfg['attivo'] ? ' checked' : '' ?>> Alleggerisci le foto nuove</label>
    <label>Lato massimo <input type="number" name="foto_lato_max" min="800" max="8000" step="10" value="<?= (int)$fotoCfg['lato'] ?>" style="width:7rem"> pixel</label>
    <button class="btn btn-primary btn-sm">Salva</button>
  </form>
  <p class="hint" style="margin-top:.7rem">Lavora con: <b><?= $motore === 'gd' ? 'GD' : ($motore === 'imagick' ? 'Imagick' : 'nessuno') ?></b><?= $motore !== '' ? (foto_webp_possibile() ? ', WebP disponibile' : ', WebP non disponibile su questo server (le foto si rimpiccioliscono lo stesso)') : '' ?>. 2560 pixel bastano per uno schermo grande.</p>
</details>
<style>.md-foto{margin-bottom:1rem;padding:1rem 1.25rem}.md-foto summary{cursor:pointer;display:flex;gap:.6rem;align-items:center}.md-foto-campi{display:flex;flex-wrap:wrap;gap:1rem 1.5rem;align-items:center;font-size:.9rem}.md-foto-campi label{display:flex;gap:.5rem;align-items:center}.md-foto-campi input[type=number]{padding:.4rem .6rem;border:1px solid var(--border);border-radius:8px;font:inherit}</style>
<?php endif; ?>
<?php if (!empty($conta['segnaposto'])): ?>
<div class="flash flash-info" id="md-da-sostituire"><b><?= $conta['segnaposto'] === 1 ? 'Una foto finta da sostituire.' : $conta['segnaposto'] . ' foto finte da sostituire.' ?></b> Le ha messe il sito su misura per mostrare dove va una foto e cosa ritrarre.
  <?= is_admin() ? 'Apri <a href="' . h($link(['cartella' => 'segnaposto'])) . '">Da sostituire</a> e premi Sostituisci sotto ognuna: la foto nuova prende il suo posto in tutte le pagine.' : 'Le sostituisce l\'amministratore.' ?></div>
<style>.md-sostituisci{margin-top:.4rem}.md-sostituisci label{position:relative;overflow:hidden;font-size:.72rem;padding:.25rem .6rem;cursor:pointer}.md-sostituisci input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}.md-sostituisci label:focus-within{outline:2px solid var(--coral);outline-offset:2px}</style>
<?php endif; ?>
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
      <?php if ($img['cartella'] === 'segnaposto' && is_admin()): ?>
      <form method="post" enctype="multipart/form-data" class="md-sostituisci">
        <?= csrf_field() ?><input type="hidden" name="azione" value="sostituisci"><input type="hidden" name="segnaposto" value="<?= h($img['url']) ?>">
        <label class="btn btn-primary btn-sm">Sostituisci<input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,image/svg+xml" required data-invia-al-cambio></label>
      </form>
      <?php endif; ?>
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
