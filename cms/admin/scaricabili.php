<?php
/**
 * Download (1.2.4): i file che il sito offre da scaricare. Carica, sostituisci con una versione nuova, scrivi titolo,
 * descrizione e versione, guarda quante volte è stato scaricato, elimina. Motore in includes/scaricabili.php; i file si
 * mostrano sul sito con il widget Download dell'editor e si aprono da /scarica/<nome>/.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/sito.php';
require_once dirname(__DIR__) . '/includes/scaricabili.php';
require_admin();

$adminTitle = 'Download';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $info = ['titolo' => (string)($_POST['titolo'] ?? ''), 'descrizione' => (string)($_POST['descrizione'] ?? ''), 'versione' => (string)($_POST['versione'] ?? '')];
    if ($azione === 'carica') {
        $ris = !empty($_POST['riservato']);
        $r = scaricabili_carica($_FILES['file'] ?? null, $info, !empty($_POST['sostituisci']), $ris);
        flash(isset($r['file']) ? 'success' : 'error', isset($r['file']) ? 'Caricato: ' . $r['file'] . ($ris ? '. È riservato: si scarica solo con il link personale che arriva dopo la conferma di un form.' : '. Si scarica da /scarica/' . $r['nome'] . '/') : $r['errore']);
    } elseif ($azione === 'salva') {
        $ok = scaricabili_salva_info((string)($_POST['nome'] ?? ''), $info);
        flash($ok ? 'success' : 'error', $ok ? 'Salvato.' : 'File non trovato.');
    } elseif ($azione === 'riservato' || $azione === 'pubblico') {
        // 1.11.0: sposta il file fra i pubblici e i riservati.
        $ok = scaricabili_rendi_riservato((string)($_POST['nome'] ?? ''), $azione === 'riservato');
        flash($ok ? 'success' : 'error', $ok ? ($azione === 'riservato' ? 'Ora il file è riservato: l\'indirizzo pubblico non funziona più, si scarica solo con il link personale.' : 'Ora il file è pubblico: si scarica da /scarica/' . (string)$_POST['nome'] . '/.') : 'Non si è potuto spostare il file (ce n\'è già uno con lo stesso nome dall\'altra parte?).');
    } elseif ($azione === 'giorni') {
        $g = (int)($_POST['giorni'] ?? 30);
        if ($g < 1 || $g > 3650) flash('error', 'I giorni vanno da 1 a 3650.');
        else { save_setting('scaricabili_giorni', (string)$g); flash('success', "I link personali dei file riservati ora valgono $g giorni (quelli già mandati restano come erano)."); }
    } elseif ($azione === 'elimina') {
        $ok = scaricabili_elimina((string)($_POST['nome'] ?? ''));
        flash($ok ? 'success' : 'error', $ok ? 'File eliminato.' : 'Non si è potuto eliminare il file.');
    }
    header('Location: scaricabili.php');
    exit;
}

$file = scaricabili_elenco();
$serie = scaricabili_serie($file);
$totale = array_sum(array_column($file, 'conteggio'));
$limite = ini_get('upload_max_filesize');
$base = rtrim(cms_url_sito(), '/');

require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<style>
.dl-tabella td{vertical-align:top}
.dl-tabella input[readonly]{border:1px solid var(--border);border-radius:6px;padding:.25rem .4rem;background:var(--campo);color:var(--text);cursor:copy}
.dl-modifica{display:inline-block;vertical-align:top}
.dl-modifica[open]{display:block;margin-bottom:.4rem}
.dl-modifica>summary{list-style:none}
.dl-modifica>summary::-webkit-details-marker{display:none}
</style>
<p style="color:var(--text-muted);max-width:820px;margin:0 0 1.2rem">
  I file che il sito offre da scaricare: manuali, ebook, listini, programmi. Nelle pagine li mostri con il widget
  <b>Download</b> dell'editor (gruppo "Dal sito"). Ogni file ha il suo indirizzo, <code>/scarica/nome-del-file/</code>,
  e qui vedi quante volte è stato scaricato (i robot dei motori di ricerca non contano).
</p>

<div class="sc-griglia" style="grid-template-columns:minmax(0,1fr) 340px">
  <div>
    <div class="sc-scatola"><h3><?= count($file) ?> file<?= $file ? ', ' . number_format($totale, 0, ',', '.') . ' download in tutto' : '' ?></h3><div class="sc-corpo" style="padding:0">
    <?php if (!$file): ?>
      <p style="padding:1rem;margin:0;color:var(--text-muted)">Ancora nessun file. Caricane uno con il riquadro qui accanto.</p>
    <?php else: ?>
      <table class="admin-table dl-tabella" id="dl-elenco" style="width:100%">
        <thead><tr><th>File</th><th>Tipo e peso</th><th>Scaricato</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($file as $x): ?>
          <tr id="dl-<?= h($x['nome']) ?>">
            <td style="max-width:360px">
              <b><?= h($x['titolo']) ?></b><?= $x['versione'] !== '' ? ' <span class="badge badge-published">' . h($x['versione']) . '</span>' : '' ?><?= $x['riservato'] ? ' <span class="badge badge-scheduled dl-riservato">Riservato</span>' : '' ?><br>
              <small style="color:var(--text-muted)"><?= h($x['file']) ?> &middot; caricato il <?= h(date('d/m/Y', $x['data'])) ?></small>
              <?php if ($x['descrizione'] !== ''): ?><br><small><?= h(mb_strimwidth($x['descrizione'], 0, 140, '…')) ?></small><?php endif; ?>
              <?php if ($x['riservato']): $cons = scaricabili_consegne($x['file']); ?>
                <small style="display:block;margin-top:.4rem;color:var(--text-muted)">Nessun indirizzo pubblico: lo ricevono, con un link personale valido <?= scaricabili_giorni() ?> giorni, le persone che confermano l'email di un form che lo dà (Form, Dopo l'invio).</small>
                <details class="dl-consegne" style="margin-top:.3rem"><summary style="cursor:pointer;font-size:.82rem">Chi l'ha ricevuto (<?= count($cons) ?>)</summary>
                  <?php if (!$cons): ?><small style="color:var(--text-muted)">Ancora nessuno.</small><?php else: ?>
                  <table class="admin-table" style="font-size:.8rem;margin-top:.3rem"><tr><th>Email</th><th>Ricevuto</th><th>Scaricato</th></tr>
                    <?php foreach ($cons as $c): ?><tr><td><?= h((string)($c['email'] ?? '(cancellato)')) ?></td><td><?= h(date('d/m/Y', strtotime($c['creato']))) ?></td><td><?= (int)$c['scaricati'] ?> volte<?= $c['ultimo'] ? ', ultima il ' . h(date('d/m/Y', strtotime($c['ultimo']))) : '' ?></td></tr><?php endforeach; ?>
                  </table><?php endif; ?>
                </details>
              <?php else: ?>
              <input type="text" readonly value="<?= h($base . $x['url']) ?>" data-copia-se-stesso title="Clic per copiare l'indirizzo" style="margin-top:.4rem;width:100%;font-size:.78rem">
              <?php endif; ?>
              <?php if ($x['serie'] !== '' && ($serie[$x['serie']]['nome'] ?? '') === $x['nome']): ?>
                <small class="dl-serie" style="display:block;margin-top:.4rem;color:var(--text-muted)">Sempre l'ultima versione (cambia da sola quando carichi la prossima):</small>
                <input type="text" readonly value="<?= h($base . '/scarica/' . $x['serie'] . '/') ?>" data-copia-se-stesso title="Clic per copiare l'indirizzo" style="width:100%;font-size:.78rem">
              <?php endif; ?>
            </td>
            <td><?= h($x['tipo']) ?><br><small style="color:var(--text-muted)"><?= h(scaricabili_peso($x['peso'])) ?></small></td>
            <td><b class="dl-conteggio"><?= number_format($x['conteggio'], 0, ',', '.') ?></b> volte<?= $x['ultimo'] !== '' ? '<br><small style="color:var(--text-muted)">ultima il ' . h(date('d/m/Y H:i', strtotime($x['ultimo']))) . '</small>' : '' ?></td>
            <td style="white-space:nowrap">
              <details class="dl-modifica"><summary class="btn btn-secondary btn-sm">Modifica</summary>
                <form method="post" class="sc-corpo" style="margin-top:.6rem;min-width:280px;padding:0">
                  <?= csrf_field() ?><input type="hidden" name="azione" value="salva"><input type="hidden" name="nome" value="<?= h($x['nome']) ?>">
                  <label>Titolo</label><input type="text" name="titolo" value="<?= h($x['titolo_scritto']) ?>" placeholder="<?= h($x['titolo']) ?>" maxlength="200">
                  <label>Descrizione</label><textarea name="descrizione" rows="3" maxlength="2000"><?= h($x['descrizione']) ?></textarea>
                  <label>Versione</label><input type="text" name="versione" value="<?= h($x['versione']) ?>" maxlength="40" placeholder="1.0">
                  <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.5rem">Salva</button>
                </form>
              </details>
              <?php if (!$x['riservato']): ?><a class="btn btn-secondary btn-sm" href="<?= h($base . $x['url']) ?>">Scarica</a><?php endif; ?>
              <form method="post" style="display:inline" data-conferma="<?= h($x['riservato'] ? 'Rendere pubblico ' . $x['file'] . '? Chiunque potrà scaricarlo dal suo indirizzo, senza iscriversi.' : 'Rendere riservato ' . $x['file'] . '? Il suo indirizzo pubblico smette di funzionare: si scaricherà solo con il link personale dopo la conferma di un form.') ?>">
                <?= csrf_field() ?><input type="hidden" name="azione" value="<?= $x['riservato'] ? 'pubblico' : 'riservato' ?>"><input type="hidden" name="nome" value="<?= h($x['nome']) ?>">
                <button type="submit" class="btn btn-secondary btn-sm dl-sposta"><?= $x['riservato'] ? 'Rendi pubblico' : 'Rendi riservato' ?></button>
              </form>
              <form method="post" style="display:inline" data-conferma="Eliminare <?= h($x['file']) ?>? Chi ha il link non potrà più scaricarlo e il conteggio si perde.">
                <?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="nome" value="<?= h($x['nome']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    </div></div>
  </div>
  <aside>
    <div class="sc-scatola"><h3>Carica un file</h3><div class="sc-corpo">
      <form method="post" enctype="multipart/form-data" id="dl-carica">
        <?= csrf_field() ?><input type="hidden" name="azione" value="carica">
        <input type="file" name="file" required accept="<?= h(implode(',', array_map(fn($e) => '.' . $e, array_keys(SCARICABILI_TIPI)))) ?>">
        <label>Titolo</label><input type="text" name="titolo" maxlength="200" placeholder="Se vuoto, dal nome del file">
        <label>Descrizione</label><textarea name="descrizione" rows="3" maxlength="2000" placeholder="Una o due frasi: cosa trova chi lo scarica"></textarea>
        <label>Versione</label><input type="text" name="versione" maxlength="40" placeholder="1.0">
        <label class="ed-spunta" style="display:flex;gap:.5rem;align-items:center;font-weight:400;margin-top:.6rem"><input type="checkbox" name="sostituisci" value="1"> Sostituisci il file con lo stesso nome (versione nuova, stesso indirizzo)</label>
        <label class="ed-spunta" style="display:flex;gap:.5rem;align-items:center;font-weight:400;margin-top:.4rem"><input type="checkbox" name="riservato" value="1" id="dl-riservato"> Riservato: si scarica solo dopo l'iscrizione e la conferma dell'email</label>
        <button type="submit" class="btn btn-primary" style="margin-top:.8rem">Carica</button>
      </form>
      <p class="hint" style="margin-top:.8rem">Tipi ammessi: <?= h(implode(', ', array_keys(SCARICABILI_TIPI))) ?>.
        Il nome del file diventa l'indirizzo: <code>Listino 2026.pdf</code> si scarica da <code>/scarica/listino-2026/</code>.</p>
      <p class="hint">Con la versione nel nome (<code>guida-1.2.zip</code>) il file entra in una serie: <code>/scarica/guida-ultima/</code>
        porta sempre alla versione più alta, e nei testi delle pagine <code>{versione:guida-ultima}</code> diventa il suo numero.
        Per una versione nuova carica il file nuovo (<code>guida-1.3.zip</code>): link e pagine la seguono da soli.</p>
      <p class="hint"><b>File riservati.</b> Non hanno un indirizzo pubblico. Li dai con un form: in <b>Newsletter e form &gt; Form</b>,
        riquadro Dopo l'invio, scegli il file in "Dopo la conferma, dai questo file". Chi conferma l'email riceve un link personale.</p>
      <form method="post" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin:.2rem 0 .8rem" id="dl-giorni"><?= csrf_field() ?><input type="hidden" name="azione" value="giorni">
        <label style="font-size:.85rem;margin:0">Il link personale vale</label><input type="number" name="giorni" min="1" max="3650" value="<?= scaricabili_giorni() ?>" style="width:5.5rem"><span style="font-size:.85rem">giorni</span>
        <button type="submit" class="btn btn-secondary btn-sm">Salva</button></form>
      <p class="hint">Il tuo server accetta file fino a <b><?= h((string)$limite) ?></b>. Un file più grande lo metti nella cartella
        <code>uploads/scaricabili/</code> con il gestore dei file dell'hosting: compare qui da solo.</p>
    </div></div>
  </aside>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
