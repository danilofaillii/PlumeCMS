<?php
/**
 * Articoli > Aggiungi sempre alla fine (1.6.0): il riquadro ombreggiato che esce in fondo a ogni articolo.
 * È un modello dell'editor di tipo "fine" (includes/modelli.php): si compone con l'editor visuale, ha bozza,
 * versioni e anteprima come header e footer; esce sul sito quando è pubblicato (vista_fine_articolo in sito/viste.php).
 * Qui: crearlo, aprirlo nell'editor, accenderlo e spegnerlo, vederlo sotto l'ultimo articolo.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/contenuti.php';
require_once dirname(__DIR__) . '/includes/modelli.php';
require_once dirname(__DIR__) . '/includes/editor.php'; // editor_puo_pubblicare (Chi pubblica)
require_admin(); // come header e footer: un pezzo di tutto il sito, dell'amministratore
modelli_installa();
$adminTitle = 'Aggiungi sempre alla fine';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        switch ($_POST['azione'] ?? '') {
            case 'crea':
                header('Location: editor.php?tipo=modelli&id=' . modelli_fine_crea());
                exit;
            case 'accendi':
                $m = modelli_fine();
                if (!$m) throw new RuntimeException('Prima crea il riquadro.');
                if (!editor_puo_pubblicare()) throw new RuntimeException('Solo chi pubblica dall\'editor (Impostazioni dell\'editor, "Chi pubblica") accende il riquadro.');
                if (empty($m['pubblicata'])) throw new RuntimeException('Il riquadro non è ancora stato pubblicato: aprilo nell\'editor e premi Pubblica.');
                modelli_stato((int)$m['id'], true);
                flash('success', 'Acceso: il riquadro esce in fondo a ogni articolo.');
                break;
            case 'spegni':
                $m = modelli_fine();
                if ($m) modelli_stato((int)$m['id'], false);
                flash('success', 'Spento: il riquadro non esce più sotto gli articoli. Quello che hai composto resta qui.');
                break;
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: fine-articolo.php');
    exit;
}

$m = modelli_fine();
$acceso = modelli_fine_acceso($m);
$dove = modelli_fine_dove_vederlo();
$conteggi = contenuti_conteggi('articoli');
$csrf = csrf_field();

require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<style>
.fa-scatola{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.25rem;margin-bottom:1rem;max-width:980px}
.fa-scatola h2{font-size:1.05rem;margin:0 0 .5rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap}
.fa-scatola p{color:var(--text-muted);font-size:.9rem;margin:.3rem 0 .8rem;max-width:760px}
.fa-azioni{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
.fa-azioni form{display:inline}
.fa-anteprima{width:100%;height:560px;border:1px solid var(--border);border-radius:10px;background:var(--fondo-2)}
.fa-nota{font-size:.82rem;color:var(--text-muted);margin:.5rem 0 0}
</style>

<div class="ct-testa">
  <div class="ct-schede">
    <?php foreach (['' => 'Tutti', 'pubblicato' => 'Pubblicati', 'programmato' => 'Programmati', 'bozza' => 'Bozze', 'cestino' => 'Cestino'] as $k => $nome):
      $n = $conteggi[$k === '' ? 'tutti' : $k]; if ($k !== '' && $n === 0) continue; ?>
      <a href="contenuti.php?tipo=articoli<?= $k !== '' ? '&amp;stato=' . $k : '' ?>"><?= $nome ?> <span>(<?= $n ?>)</span></a>
    <?php endforeach; ?>
    <a href="fine-articolo.php" class="attivo" id="fa-scheda">Aggiungi sempre alla fine</a>
  </div>
</div>

<div class="fa-scatola" id="fa-stato">
  <h2>Il riquadro in fondo agli articoli
    <?php if (!$m): ?><span class="badge badge-draft">Non ancora creato</span>
    <?php elseif ($acceso): ?><span class="badge badge-published">Acceso</span>
    <?php else: ?><span class="badge badge-draft">Spento</span><?php endif; ?>
  </h2>
  <p>Quello che componi qui esce sotto ogni articolo, in un riquadro ombreggiato: un invito alla newsletter, un contatto, un
    libro da consigliare. Si costruisce con l'editor visuale, come una pagina, e vale per tutti gli articoli, anche quelli già pubblicati.</p>
  <div class="fa-azioni">
    <?php if (!$m): ?>
      <form method="post"><?= $csrf ?><input type="hidden" name="azione" value="crea"><button type="submit" class="btn btn-primary" id="fa-crea">Crea il riquadro</button></form>
    <?php else: ?>
      <a class="btn btn-primary" id="fa-modifica" href="editor.php?tipo=modelli&amp;id=<?= (int)$m['id'] ?>">Modifica nell'editor</a>
      <?php if ($acceso): ?>
        <form method="post"><?= $csrf ?><input type="hidden" name="azione" value="spegni"><button type="submit" class="btn btn-secondary" id="fa-spegni">Spegni</button></form>
      <?php else: ?>
        <form method="post"><?= $csrf ?><input type="hidden" name="azione" value="accendi"><button type="submit" class="btn btn-secondary" id="fa-accendi">Accendi</button></form>
      <?php endif; ?>
      <?php if ($dove !== ''): ?><a class="btn btn-secondary" target="_blank" href="<?= h(cms_sito_base() . $dove . '?preview=1&fine=' . (int)$m['id'] . '#fine-articolo') ?>">Vedi sotto l'ultimo articolo</a><?php endif; ?>
      <a class="btn btn-secondary" href="versioni.php?ambito=modello&amp;oggetto=<?= (int)$m['id'] ?>">Versioni</a>
    <?php endif; ?>
  </div>
  <?php if ($m && !$acceso): ?>
    <p class="fa-nota"><?= empty($m['pubblicata']) ? 'Per farlo uscire sul sito: Modifica nell\'editor, poi Pubblica.' : 'Premi Accendi per farlo uscire di nuovo sotto gli articoli.' ?></p>
  <?php endif; ?>
</div>

<?php if ($m && $dove !== ''): ?>
<div class="fa-scatola">
  <h2>Anteprima</h2>
  <p>L'ultimo articolo pubblicato, con il riquadro in fondo com'è adesso nell'editor (anche le modifiche non ancora pubblicate).</p>
  <iframe class="fa-anteprima" id="fa-anteprima" title="Anteprima del riquadro sotto un articolo" loading="lazy" src="<?= h(cms_sito_base() . $dove . '?preview=1&fine=' . (int)$m['id'] . '#fine-articolo') ?>"></iframe>
</div>
<?php elseif ($m): ?>
<div class="fa-scatola"><p>Quando pubblichi il primo articolo, qui vedi l'anteprima del riquadro sotto di lui.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
