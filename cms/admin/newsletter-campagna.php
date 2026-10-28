<?php
/**
 * Editor di una campagna della newsletter: oggetto, anteprima, testo (Quill), pulsante finale.
 * Prova a un indirizzo, programmazione, sospensione. Il testo si salva com'è nell'editor
 * e diventa HTML da email al momento dell'invio (newsletter_corpo_email()).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();

$id = (int)($_GET['id'] ?? 0);
$c = newsletter_campagna($id);
if (!$c) { header('Location: newsletter.php'); exit; }
$modificabile = in_array($c['stato'], ['bozza', 'programmata'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = $_POST['azione'] ?? 'salva';
    if ($modificabile) {
        $link = trim((string)($_POST['pulsante_link'] ?? ''));
        $scelte = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['liste'] ?? [])))));
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET oggetto = ?, anteprima = ?, corpo = ?, pulsante_testo = ?, pulsante_link = ?, liste = ?, aggiornato = NOW() WHERE id = ?')
            ->execute([
                newsletter_4byte_dentro(mb_substr(trim((string)($_POST['oggetto'] ?? '')), 0, 200)),
                newsletter_4byte_dentro(mb_substr(trim((string)($_POST['anteprima'] ?? '')), 0, 200)),
                newsletter_4byte_dentro((string)($_POST['corpo'] ?? '')),
                newsletter_4byte_dentro(mb_substr(trim((string)($_POST['pulsante_testo'] ?? '')), 0, 100)),
                preg_match('~^https?://~', $link) ? mb_substr($link, 0, 500) : '',
                implode(',', $scelte),
                $id,
            ]);
        $c = newsletter_campagna($id);
    }
    $vuota = trim(strip_tags((string)$c['corpo'], '<img>')) === '' || trim($c['oggetto']) === '';
    $destinatari = newsletter_destinatari(newsletter_liste_campagna($c));
    if (in_array($azione, ['programma', 'subito'], true) && !newsletter_liste_campagna($c)) { flash('error', 'Scegli almeno una lista.'); $azione = 'fermo'; }
    elseif (in_array($azione, ['programma', 'subito'], true) && $destinatari === 0) { flash('error', 'Nelle liste scelte non c\'è nessun iscritto attivo.'); $azione = 'fermo'; }
    switch ($azione) {
        case 'prova':
            $a = trim((string)($_POST['prova_a'] ?? ''));
            if (!filter_var($a, FILTER_VALIDATE_EMAIL)) { flash('error', 'Indirizzo per la prova non valido.'); break; }
            [$ogg, $html, $intest] = newsletter_email($c, ['id' => 0, 'nome' => trim((string)($_POST['prova_nome'] ?? '')) ?: 'Giulia', 'email' => $a]);
            posta_invia($a, '[PROVA] ' . $ogg, $html, $intest)
                ? flash('success', "Prova spedita a $a.") : flash('error', 'Prova non partita: ' . posta_errore());
            break;
        case 'programma':
            $quando = strtotime((string)($_POST['invio_at'] ?? ''));
            if ($vuota) { flash('error', 'Prima scrivi oggetto e testo.'); break; }
            if (!$quando) { flash('error', 'Scegli giorno e ora.'); break; }
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'programmata', invio_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:00', max($quando, time())), $id]);
            flash('success', 'Campagna programmata per il ' . date('d/m/Y \a\l\l\e H:i', max($quando, time())) . ", verso $destinatari persone (contate oggi). Parte con il primo giro del cron dopo quell'ora.");
            break;
        case 'subito':
            if ($vuota) { flash('error', 'Prima scrivi oggetto e testo.'); break; }
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'programmata', invio_at = NOW() WHERE id = ?")->execute([$id]);
            flash('success', "Campagna in partenza verso $destinatari persone: il cron la spedisce a giri, entro il limite orario.");
            break;
        case 'fermo':
            break;
        case 'annulla':
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'bozza', invio_at = NULL WHERE id = ? AND stato = 'programmata'")->execute([$id]);
            flash('success', 'Programmazione annullata: la campagna è di nuovo una bozza.');
            break;
        case 'sospendi':
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'sospesa' WHERE id = ? AND stato = 'in_invio'")->execute([$id]);
            flash('success', 'Invio sospeso. Le email già partite restano partite.');
            break;
        case 'riprendi':
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'in_invio' WHERE id = ? AND stato = 'sospesa'")->execute([$id]);
            flash('success', 'Invio ripreso.');
            break;
        default:
            flash('success', 'Campagna salvata.');
    }
    header('Location: newsletter-campagna.php?id=' . $id);
    exit;
}

[$oggProva, $htmlProva] = newsletter_email($c, ['id' => 0, 'nome' => 'Giulia', 'email' => '']);
$liste = newsletter_liste();
$scelte = newsletter_liste_campagna($c);
$destinatari = newsletter_destinatari($scelte);
$prova = posta_notifica_email(); // l'indirizzo degli avvisi (Impostazioni, Configurazione email)
$adminTitle = 'Newsletter: ' . (newsletter_4byte_fuori($c['oggetto']) ?: 'nuova campagna');
require_once __DIR__ . '/includes/admin-header.php';
?>
<link href="<?= pannello_file('assets/quill/quill.snow.css') ?>" rel="stylesheet">
<style>
.nlc{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.25rem;align-items:start}
@media(max-width:1100px){.nlc{grid-template-columns:1fr}}
#editor{min-height:420px;background:var(--campo);color:var(--text);font-size:16px}
.nlc iframe{width:100%;height:760px;border:1px solid var(--border);border-radius:10px;background:#f4f4f6 /* fisso: il fondo dell email */}
.nlc-azioni{display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;margin-top:1rem}
.nlc-azioni input{padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px}
.nlc-stato{padding:.6rem 1rem;border-radius:8px;background:var(--info-chiaro);color:var(--info-testo);margin-bottom:1rem}
</style>

<p><a href="newsletter.php">← Tutte le campagne</a></p>
<?php if ($c['stato'] !== 'bozza'): ?>
<div class="nlc-stato">
  <?php if ($c['stato'] === 'programmata'): ?>Programmata per il <b><?= h(date('d/m/Y H:i', strtotime($c['invio_at']))) ?></b>. Puoi ancora modificarla.
  <?php elseif ($c['stato'] === 'in_invio' || $c['stato'] === 'sospesa'): ?><?= $c['stato'] === 'sospesa' ? 'Invio sospeso' : 'In invio' ?>: <?= (int)$c['inviate'] ?> di <?= (int)$c['destinatari'] ?> partite<?= $c['errori'] ? ', ' . (int)$c['errori'] . ' non partite' : '' ?>.
  <?php else: ?>Inviata: <?= (int)$c['inviate'] ?> email partite<?= $c['errori'] ? ', ' . (int)$c['errori'] . ' non partite' : '' ?>. Per una nuova versione usa "Duplica" nell'elenco.<?php endif; ?>
</div>
<?php endif; ?>

<div class="nlc">
  <div class="form-card">
    <form method="post" id="nl-form">
      <?= csrf_field() ?>
      <input type="hidden" name="corpo" id="corpo">
      <div class="form-group"><label for="oggetto">Oggetto</label><input type="text" id="oggetto" name="oggetto" maxlength="200" value="<?= h(newsletter_4byte_fuori($c['oggetto'])) ?>" <?= $modificabile ? '' : 'readonly' ?> required></div>
      <div class="form-group"><label for="anteprima">Testo di anteprima</label><input type="text" id="anteprima" name="anteprima" maxlength="200" value="<?= h(newsletter_4byte_fuori($c['anteprima'])) ?>" <?= $modificabile ? '' : 'readonly' ?>>
        <div class="hint">La riga grigia che i programmi di posta mostrano accanto all'oggetto. Scrivi <code>{nome}</code> dove vuoi il nome della persona, anche nell'oggetto e nel testo.</div></div>
      <div class="form-group"><label>Testo</label><div id="editor"></div></div>
      <div class="form-group"><label for="pulsante_testo">Pulsante in fondo (facoltativo)</label>
        <input type="text" id="pulsante_testo" name="pulsante_testo" maxlength="100" placeholder="Leggi la guida" value="<?= h(newsletter_4byte_fuori($c['pulsante_testo'])) ?>" <?= $modificabile ? '' : 'readonly' ?>>
        <input type="url" name="pulsante_link" maxlength="500" placeholder="<?= h(cms_url_sito()) ?>/..." value="<?= h($c['pulsante_link']) ?>" style="margin-top:.4rem" <?= $modificabile ? '' : 'readonly' ?>></div>
      <div class="form-group"><label>A quali liste</label>
        <?php foreach ($liste as $l): ?><label style="display:block;font-weight:400;text-transform:none;letter-spacing:0"><input type="checkbox" name="liste[]" value="<?= (int)$l['id'] ?>" <?= in_array((int)$l['id'], $scelte, true) ? 'checked' : '' ?> <?= $modificabile ? '' : 'disabled' ?>> <?= h($l['nome']) ?> <small style="color:var(--text-muted)">(<?= (int)$l['attivi'] ?> attivi)</small></label><?php endforeach; ?>
        <div class="hint">Con le liste salvate la campagna arriva a <b><?= $destinatari ?></b> persone: chi sta in più liste la riceve una volta sola. Il numero si aggiorna quando salvi.</div></div>

      <?php if ($modificabile): ?>
      <div class="nlc-azioni"><button class="btn btn-primary" type="submit" name="azione" value="salva">Salva</button></div>
      <div class="nlc-azioni">
        <label>Prova a <input type="email" name="prova_a" value="<?= h($prova) ?>"></label>
        <button class="btn btn-secondary" type="submit" name="azione" value="prova">Salva e manda la prova</button>
      </div>
      <div class="nlc-azioni">
        <label>Giorno e ora <input type="datetime-local" name="invio_at" value="<?= h($c['invio_at'] ? date('Y-m-d\TH:i', strtotime($c['invio_at'])) : date('Y-m-d\TH:i', strtotime('+1 day 06:00'))) ?>"></label>
        <button class="btn btn-secondary" type="submit" name="azione" value="programma">Programma l'invio</button>
        <button class="btn btn-secondary" type="submit" name="azione" value="subito" data-conferma="Mandare adesso la campagna alle liste spuntate?">Invia adesso alle liste scelte</button>
        <?php if ($c['stato'] === 'programmata'): ?><button class="btn btn-secondary" type="submit" name="azione" value="annulla">Annulla la programmazione</button><?php endif; ?>
      </div>
      <?php elseif ($c['stato'] === 'in_invio'): ?>
      <div class="nlc-azioni"><button class="btn btn-secondary" type="submit" name="azione" value="sospendi">Sospendi l'invio</button></div>
      <?php elseif ($c['stato'] === 'sospesa'): ?>
      <div class="nlc-azioni"><button class="btn btn-primary" type="submit" name="azione" value="riprendi">Riprendi l'invio</button></div>
      <?php endif; ?>
    </form>
  </div>
  <div>
    <p style="font-size:.85rem;color:var(--text-muted);margin:0 0 .5rem">Anteprima dell'ultima versione salvata, con il nome "Giulia".</p>
    <iframe title="Anteprima" sandbox srcdoc="<?= h($htmlProva) ?>"></iframe>
  </div>
</div>

<script src="<?= pannello_file('assets/quill/quill.js') ?>"></script>
<script<?= nonce_attr() ?>>
(function () {
  var quill = new Quill('#editor', {
    theme: 'snow',
    readOnly: <?= $modificabile ? 'false' : 'true' ?>,
    placeholder: 'Scrivi qui. {nome} diventa il nome di chi legge.',
    modules: { toolbar: { container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], ['link', 'image'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote'], [{ align: [] }], ['clean']],
      handlers: { image: carica } } }
  });
  quill.clipboard.dangerouslyPasteHTML(<?= json_encode((string)$c['corpo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);

  // Le immagini vanno sul server, non dentro l'email: si caricano e si inserisce il loro indirizzo.
  function carica() {
    var input = document.createElement('input');
    input.type = 'file'; input.accept = 'image/png,image/jpeg,image/gif,image/webp';
    input.onchange = function () {
      var dati = new FormData();
      dati.append('immagine', input.files[0]);
      dati.append('_csrf', document.querySelector('#nl-form input[name=_csrf]').value);
      fetch('newsletter-immagine.php', { method: 'POST', body: dati, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.url) { alert(j.errore || 'Caricamento non riuscito.'); return; }
          var r = quill.getSelection(true);
          quill.insertEmbed(r.index, 'image', j.url, 'user');
        })
        .catch(function () { alert('Caricamento non riuscito.'); });
    };
    input.click();
  }

  document.getElementById('nl-form').addEventListener('submit', function () {
    document.getElementById('corpo').value = quill.getLength() > 1 ? quill.root.innerHTML : '';
  });
})();
</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
