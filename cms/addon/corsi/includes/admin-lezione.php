<?php
/**
 * Pannello, Corsi > una lezione (addon corsi): titolo, modulo, testo (HTML, ripulito come gli articoli), video di YouTube o
 * Vimeo oppure un file .mp4/.webm caricato, materiali da scaricare (i file riservati di Download), durata.
 */
require_once __DIR__ . '/corsi.php';
corsi_schema();
$l = corsi_lezione((int)($_GET['id'] ?? 0));
if (!$l) { http_response_code(404); exit('Lezione non trovata.'); }
$corso = corsi_leggi((int)$l['corso_id']);
$qui = corsi_admin_url('lezione', ['id' => $l['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (($_POST['azione'] ?? '') === 'togli_video') {
            corsi_video_togli((int)$l['id']);
            flash('success', 'Video caricato tolto.');
        } else {
            corsi_lezione_salva((int)$l['id'], ['titolo' => (string)($_POST['titolo'] ?? ''), 'slug' => (string)($_POST['slug'] ?? ''), 'modulo_id' => (int)($_POST['modulo_id'] ?? 0),
                'testo' => (string)($_POST['testo'] ?? ''), 'video_url' => (string)($_POST['video_url'] ?? ''), 'durata' => (string)($_POST['durata'] ?? ''),
                'materiali' => array_values(array_filter((array)($_POST['materiali'] ?? []), 'is_string'))]);
            $e = corsi_video_carica($_FILES['video'] ?? null, (int)$l['id']);
            if ($e !== '') flash('error', 'Lezione salvata, ma il video no: ' . $e);
            else flash('success', 'Lezione salvata.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . $qui);
    exit;
}

$moduli = corsi_moduli((int)$corso['id']);
$riservati = array_filter(scaricabili_elenco(), fn($x) => $x['riservato']);
$scelti = array_flip(corsi_materiali($l));
$url = rtrim(cms_url_sito(), '/') . '/corsi/' . $corso['slug'] . '/' . $l['slug'] . '/';
$adminTitle = 'Lezione: ' . $l['titolo'];
require CMS_ADMIN . '/includes/admin-header.php';
?>
<style>.le-griglia{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:1.25rem}@media(max-width:1100px){.le-griglia{grid-template-columns:1fr}}.le-mat{display:flex;gap:.5rem;align-items:flex-start;font-weight:400;padding:.3rem 0}#le-testo{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.9rem;min-height:18rem}</style>
<p><a href="<?= h(corsi_admin_url('corso', ['id' => $corso['id']])) ?>#programma">&larr; <?= h($corso['titolo']) ?></a> &middot; <a href="<?= h($url . '?preview=1') ?>" target="_blank">Vedi sul sito</a></p>
<form method="post" enctype="multipart/form-data" id="le-form"><?= csrf_field() ?><input type="hidden" name="azione" value="salva">
<div class="le-griglia">
  <div class="sc-scatola"><h3>La lezione</h3><div class="sc-corpo">
    <label for="le-titolo">Titolo</label><input type="text" id="le-titolo" name="titolo" value="<?= h($l['titolo']) ?>" maxlength="200" required>
    <label for="le-testo">Testo della lezione</label>
    <textarea id="le-testo" name="testo" rows="16"><?= h((string)$l['testo']) ?></textarea>
    <p class="hint">Si scrive in HTML semplice: <code>&lt;p&gt;</code> per i paragrafi, <code>&lt;h2&gt;</code> per i titoli, <code>&lt;ul&gt;&lt;li&gt;</code> per gli elenchi, <code>&lt;b&gt;</code> per il grassetto, <code>&lt;a href="..."&gt;</code> per i link.
      Si può anche incollare il testo di un articolo. Script e codice pericoloso si tolgono da soli al salvataggio.</p>
  </div></div>
  <aside>
    <div class="sc-scatola"><h3>Dove sta</h3><div class="sc-corpo">
      <label for="le-modulo">Modulo</label><select id="le-modulo" name="modulo_id"><?php foreach ($moduli as $m): ?><option value="<?= (int)$m['id'] ?>"<?= (int)$m['id'] === (int)$l['modulo_id'] ? ' selected' : '' ?>><?= h($m['titolo']) ?></option><?php endforeach; ?></select>
      <label for="le-slug">Indirizzo</label><input type="text" id="le-slug" name="slug" value="<?= h($l['slug']) ?>" maxlength="100" pattern="[a-z0-9\-]+">
      <label for="le-durata">Durata (facoltativa)</label><input type="text" id="le-durata" name="durata" value="<?= h($l['durata']) ?>" maxlength="40" placeholder="12 minuti">
    </div></div>
    <div class="sc-scatola"><h3>Video</h3><div class="sc-corpo">
      <label for="le-video">Indirizzo di YouTube o Vimeo</label><input type="url" id="le-video" name="video_url" value="<?= h($l['video_url']) ?>" maxlength="500" placeholder="https://www.youtube.com/watch?v=...">
      <p class="hint">Copia l'indirizzo dalla barra del browser mentre guardi il video. YouTube si vede nella versione che non usa i cookie.</p>
      <label for="le-file">Oppure carica un video (.mp4 o .webm)</label><input type="file" id="le-file" name="video" accept="video/mp4,video/webm,.mp4,.webm">
      <p class="hint">Limite del server: <?= h((string)ini_get('upload_max_filesize')) ?>. Il video caricato lo vede solo chi segue il corso; se c'è, vince sull'indirizzo.</p>
      <?php if ($l['video_file'] !== ''): ?><p id="le-video-caricato">Video caricato: <code><?= h($l['video_file']) ?></code> <button class="btn btn-secondary btn-sm" type="submit" name="azione" value="togli_video" data-conferma="Togliere il video caricato?">Togli</button></p><?php endif; ?>
    </div></div>
    <div class="sc-scatola"><h3>Materiali da scaricare</h3><div class="sc-corpo">
      <?php if (!$riservati): ?><p class="hint">Nessun file riservato. Caricane uno da <a href="scaricabili.php">Download</a> con la spunta "Riservato": poi lo scegli qui.</p>
      <?php else: foreach ($riservati as $x): ?>
        <label class="le-mat"><input type="checkbox" name="materiali[]" value="<?= h($x['nome']) ?>"<?= isset($scelti[$x['nome']]) ? ' checked' : '' ?>> <span><?= h($x['titolo']) ?> <small style="color:var(--text-muted)">(<?= h($x['file']) ?>)</small></span></label>
      <?php endforeach; ?><p class="hint">Sono i file riservati di Download: li scarica solo chi può vedere la lezione.</p><?php endif; ?>
    </div></div>
    <button class="btn btn-primary" type="submit" style="width:100%">Salva la lezione</button>
  </aside>
</div>
</form>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
