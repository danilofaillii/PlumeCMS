<?php
/**
 * Pannello, Membri > Contenuti e file riservati (addon membri): quali pagine e articoli sono "solo per i membri", e quali
 * file riservati di Download i membri trovano nella loro area. Solo amministratore (admin/addon-pagina.php).
 */
require_once __DIR__ . '/membri.php';
require_once cms_radice() . '/includes/scaricabili.php';
membri_schema();

$contenuti = [];
foreach (['articles' => 'Articolo', 'pages' => 'Pagina'] as $fonte => $tipo) {
    try {
        foreach (db()->query('SELECT id, title, slug, status FROM ' . tbl($fonte) . ' WHERE cestino IS NULL ORDER BY title')->fetchAll() as $r) {
            $contenuti[] = ['fonte' => $fonte, 'tipo' => $tipo, 'id' => (int)$r['id'], 'titolo' => (string)$r['title'], 'slug' => (string)$r['slug'], 'stato' => (string)$r['status']];
        }
    } catch (Throwable $e) { error_log('membri: ' . $fonte . ': ' . $e->getMessage()); }
}
$home = (string)get_setting('pagina_home', '');
$riservatiFile = array_filter(scaricabili_elenco(), fn($x) => $x['riservato']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['azione'] ?? '') === 'contenuti') {
        $scelti = array_flip(array_filter((array)($_POST['c'] ?? []), 'is_string'));
        $n = 0;
        foreach ($contenuti as $c) {
            if ($c['fonte'] === 'pages' && $c['slug'] === $home) continue; // la home resta di tutti
            $si = isset($scelti[$c['fonte'] . ':' . $c['id']]);
            membri_riservato_imposta($c['fonte'], $c['id'], $si);
            if ($si) $n++;
        }
        flash('success', $n ? "Fatto: $n contenuti sono solo per i membri." : 'Fatto: nessun contenuto è riservato, il sito è tutto pubblico.');
    } elseif (($_POST['azione'] ?? '') === 'file') {
        $scelti = array_values(array_filter((array)($_POST['f'] ?? []), fn($f) => is_string($f) && isset($riservatiFile[$f])));
        membri_cfg_salva(['file' => $scelti]);
        flash('success', count($scelti) ? 'Fatto: i membri trovano ' . count($scelti) . ' file nella loro area.' : 'Fatto: nessun file per i membri.');
    }
    header('Location: ' . membri_admin_url('riservati'));
    exit;
}

$riservati = membri_riservati();
$fileScelti = array_flip(membri_cfg()['file']);
$adminTitle = 'Contenuti e file riservati';
require CMS_ADMIN . '/includes/admin-header.php';
?>
<style>.mr-griglia{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.25rem}@media(max-width:1000px){.mr-griglia{grid-template-columns:1fr}}.mr-voce{display:flex;gap:.6rem;align-items:flex-start;padding:.45rem 0;border-bottom:1px solid var(--border);font-weight:400}.mr-voce small{color:var(--text-muted)}.mr-elenco{max-height:60vh;overflow:auto;margin-bottom:.8rem}</style>
<p style="max-width:62rem">Un contenuto <b>solo per i membri</b> lo vede intero solo chi è entrato nell'area riservata. Gli altri vedono il titolo e l'invito ad accedere o a iscriversi;
nei motori di ricerca la pagina esce senza il testo. <b>Attenzione:</b> se spegni l'addon Area riservata, questi contenuti tornano visibili a tutti.</p>
<div class="mr-griglia">
  <div class="sc-scatola"><h3>Pagine e articoli</h3><div class="sc-corpo">
    <?php if (!$contenuti): ?><p>Nessuna pagina e nessun articolo.</p><?php else: ?>
    <form method="post" id="mr-contenuti"><?= csrf_field() ?><input type="hidden" name="azione" value="contenuti">
      <div class="mr-elenco">
      <?php foreach ($contenuti as $c): $k = $c['fonte'] . ':' . $c['id']; $eHome = $c['fonte'] === 'pages' && $c['slug'] === $home; ?>
        <label class="mr-voce"><input type="checkbox" name="c[]" value="<?= h($k) ?>"<?= isset($riservati[$k]) ? ' checked' : '' ?><?= $eHome ? ' disabled' : '' ?>>
          <span><b><?= h($c['titolo']) ?></b><br><small><?= h($c['tipo']) ?> &middot; /<?= h($c['slug']) ?>/<?= $c['stato'] !== 'published' ? ' &middot; non pubblicato' : '' ?><?= $eHome ? ' &middot; la home resta di tutti' : '' ?></small></span></label>
      <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary">Salva i contenuti riservati</button>
    </form>
    <?php endif; ?>
  </div></div>
  <div class="sc-scatola"><h3>File per i membri</h3><div class="sc-corpo">
    <p class="hint">Sono i file <b>riservati</b> della pagina Download (lì si caricano, con la spunta "Riservato"). Quelli scelti qui i membri li scaricano dalla loro area, senza altri passaggi.</p>
    <?php if (!$riservatiFile): ?><p>Nessun file riservato. Caricane uno da <a href="scaricabili.php">Download</a>, con la spunta "Riservato".</p><?php else: ?>
    <form method="post" id="mr-file"><?= csrf_field() ?><input type="hidden" name="azione" value="file">
      <div class="mr-elenco">
      <?php foreach ($riservatiFile as $x): ?>
        <label class="mr-voce"><input type="checkbox" name="f[]" value="<?= h($x['nome']) ?>"<?= isset($fileScelti[$x['nome']]) ? ' checked' : '' ?>>
          <span><b><?= h($x['titolo']) ?></b><br><small><?= h($x['file']) ?> &middot; <?= h($x['tipo']) ?>, <?= h(scaricabili_peso($x['peso'])) ?></small></span></label>
      <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary">Salva i file per i membri</button>
    </form>
    <?php endif; ?>
  </div></div>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
