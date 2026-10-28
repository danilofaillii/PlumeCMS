<?php
/**
 * Copia del sito (1.3.0): scarica tutto il sito in uno zip e ripristinalo da uno zip (includes/copia-sito.php).
 * Solo per l'amministratore, ogni azione in POST con il token CSRF; il ripristino chiede anche la conferma esplicita.
 */
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/copia-sito.php';
require_admin();

$adminTitle = 'Copia del sito';
$esito = null;
$limite = copia_limite_caricamento();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Un file oltre post_max_size arriva senza nessun campo (nemmeno il token): si spiega invece di dire "token scaduto".
    if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'Il file supera il limite di caricamento del server (' . agg_fmt_bytes($limite) . '). Caricalo con il gestore dei file del tuo hosting (o via FTP) nella cartella aggiornamenti/copie/ del CMS, con un nome come copia-20261027-120000-mia.zip: comparirà qui sotto, fra le copie sul server, e la ripristini da lì.');
        header('Location: copia-sito.php'); exit;
    }
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    try {
        if ($azione === 'crea') {
            session_write_close();
            ob_start();
            $nome = copia_crea('manuale');
            ob_end_clean();
            @session_start();
            flash('success', 'Copia pronta: ' . $nome . ' (' . agg_fmt_bytes((int)filesize(copia_percorso($nome))) . '). Premi Scarica per salvarla sul tuo computer.');
            header('Location: copia-sito.php?pronta=' . rawurlencode($nome)); exit;
        } elseif ($azione === 'scarica') {
            $f = copia_percorso((string)($_POST['file'] ?? ''));
            if (function_exists('sicurezza_log')) sicurezza_log('copia', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'copia scaricata: ' . basename($f));
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($f) . '"');
            header('Content-Length: ' . filesize($f));
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            readfile($f);
            exit;
        } elseif ($azione === 'elimina') {
            copia_elimina((string)($_POST['file'] ?? ''));
            flash('success', 'Copia eliminata dal server.');
            header('Location: copia-sito.php'); exit;
        } elseif ($azione === 'carica' || $azione === 'ripristina') {
            if (empty($_POST['conferma'])) throw new RuntimeException('Per ripristinare spunta la conferma: il database e i file del sito vengono sostituiti da quelli della copia.');
            if ($azione === 'carica') {
                $f = $_FILES['copia'] ?? null;
                if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                    $err = (int)($f['error'] ?? -1);
                    throw new RuntimeException(in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                        ? 'Il file supera il limite di caricamento del server (' . agg_fmt_bytes($limite) . '). Caricalo con il gestore dei file del tuo hosting nella cartella aggiornamenti/copie/ e ripristinalo dall\'elenco qui sotto.'
                        : 'Caricamento fallito (codice ' . $err . ').');
                }
                $file = copia_registra_caricata($f['tmp_name']);
            } else {
                $file = basename((string)($_POST['file'] ?? ''));
            }
            session_write_close();
            ob_start();
            $r = copia_ripristina($file);
            $rumore = trim((string)ob_get_clean());
            @session_start();
            if ($rumore !== '') $r['log'][] = 'Avvisi di PHP durante il lavoro, non bloccanti: ' . mb_substr($rumore, 0, 1500);
            $esito = ['ok' => $r['ok'], 'titolo' => ($r['ok'] ? 'Sito ripristinato dalla copia ' : 'Ripristino con errori dalla copia ') . $file, 'log' => $r['log']];
            // Il database nuovo può avere altri utenti: se questo non c'è più, si rientra con quelli della copia.
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('users') . " WHERE id = ? AND username = ? AND role = 'admin'");
            $st->execute([(int)($_SESSION['admin_user_id'] ?? 0), (string)($_SESSION['admin_username'] ?? '')]);
            if (!(int)$st->fetchColumn()) logout(); // al login si entra con gli utenti della copia
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        if ($azione !== 'carica' && $azione !== 'ripristina') { header('Location: copia-sito.php'); exit; }
    }
}

$copie = copia_elenco();
$pronta = basename((string)($_GET['pronta'] ?? ''));
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.cs-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}
@media(max-width:900px){.cs-grid{grid-template-columns:1fr}}
.cs-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:1.2rem 1.4rem}
.cs-card h2{font-size:1.05rem;margin:0 0 .6rem}
.cs-note{font-size:.84rem;color:var(--text-muted);margin:.5rem 0 0;line-height:1.5}
.cs-avviso{display:flex;gap:.8rem;align-items:flex-start;border:1px solid var(--attenzione-bordo);background:var(--mustard-light);color:var(--attenzione-testo);border-radius:12px;padding:.9rem 1.2rem;margin-bottom:1.2rem;font-size:.9rem;line-height:1.5}
.cs-avviso b{display:block;margin-bottom:.15rem}
.cs-btn{display:inline-block;padding:.5rem 1rem;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-family:var(--font);font-size:.84rem;font-weight:600;cursor:pointer;color:var(--text)}
.cs-btn.primary{background:var(--coral);color:var(--su-accento);border-color:var(--coral)}
.cs-btn.danger{color:var(--errore-testo);border-color:var(--errore-bordo)}
.cs-btn:disabled{opacity:.5;cursor:default}
.cs-spunta{display:flex;gap:.5rem;align-items:flex-start;font-size:.86rem;margin:.7rem 0}
.cs-spunta input{margin-top:.2rem}
.cs-table{width:100%;border-collapse:collapse;font-size:.86rem}
.cs-table th{text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);padding:.5rem .6rem;border-bottom:1px solid var(--border)}
.cs-table td{padding:.6rem;border-bottom:1px solid var(--border);vertical-align:top}
.cs-table tr.cs-nuova td{background:var(--sage-light)}
.cs-tag{display:inline-block;padding:.1rem .5rem;border-radius:999px;font-size:.72rem;font-weight:700}
.cs-tag.si{background:var(--sage-light);color:var(--successo-testo)}.cs-tag.no{background:var(--mustard-light);color:var(--attenzione-testo)}
.cs-azioni{display:flex;gap:.4rem;flex-wrap:wrap}
.cs-azioni details{width:100%}
.cs-azioni summary{cursor:pointer;color:var(--errore-testo);font-weight:600;font-size:.84rem;list-style:none}
.cs-esito{border-radius:12px;padding:1rem 1.4rem;margin-bottom:1.2rem;border:1px solid}
.cs-esito.ok{background:var(--sage-light);border-color:var(--successo-bordo)}.cs-esito.ko{background:var(--errore-chiaro);border-color:var(--errore-bordo)}
.cs-log{background:var(--codice-fondo);color:var(--codice-testo);padding:1rem;border-radius:8px;font-family:monospace;font-size:.8rem;white-space:pre-wrap;word-break:break-word;max-height:360px;overflow:auto;margin:.6rem 0 0}
.cs-lavoro{position:fixed;inset:0;background:var(--velo);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.cs-lavoro[hidden]{display:none}
.cs-lavoro div{background:var(--surface);border-radius:14px;padding:1.6rem 1.8rem;max-width:480px}
</style>

<?php if ($esito): ?>
<div class="cs-esito <?= $esito['ok'] ? 'ok' : 'ko' ?>"><strong><?= h($esito['titolo']) ?></strong><pre class="cs-log"><?= h(implode("\n", $esito['log'])) ?></pre></div>
<?php endif; ?>

<div class="cs-avviso" role="note"><span aria-hidden="true">&#9888;</span><div><b>Una copia contiene dati sensibili.</b>Dentro ci sono il database intero (utenti con le password cifrate, iscritti alla newsletter, messaggi dei form, impostazioni) e tutti i file caricati. Conservala in un posto sicuro e non mandarla per email. La password del database (il file <code>includes/config.local.php</code>) non è nella copia.</div></div>

<div class="cs-grid">
  <div class="cs-card">
    <h2>Scarica una copia del sito</h2>
    <p class="cs-note" style="margin-top:0">Database, immagini e file caricati, temi e moduli in un solo zip. Si prepara qui sul server e poi la scarichi: su un sito grande ci vuole qualche minuto.</p>
    <form method="post" data-lavoro="Preparo la copia del sito" style="margin-top:.8rem">
      <?= csrf_field() ?><input type="hidden" name="azione" value="crea">
      <button class="cs-btn primary" type="submit">Prepara la copia</button>
    </form>
    <p class="cs-note">Le copie restano sul server, nella cartella <code>aggiornamenti/copie/</code>, che da internet non si apre: si scaricano solo da questa pagina. Se ne tengono le ultime <?= COPIA_DA_TENERE ?>.</p>
  </div>
  <div class="cs-card">
    <h2>Ripristina da una copia</h2>
    <form method="post" enctype="multipart/form-data" data-lavoro="Ripristino il sito dalla copia" id="cs-carica">
      <?= csrf_field() ?><input type="hidden" name="azione" value="carica">
      <input type="file" name="copia" accept=".zip,application/zip" required style="font-size:.85rem;display:block" data-limite="<?= (int)$limite ?>">
      <p class="cs-note" id="cs-limite">Il server accetta file fino a <b><?= h(agg_fmt_bytes($limite)) ?></b>. Una copia più grande si mette con il gestore dei file del tuo hosting nella cartella <code>aggiornamenti/copie/</code>, e si ripristina dall'elenco qui sotto.</p>
      <label class="cs-spunta"><input type="checkbox" name="conferma" value="1" required> <span>Ho capito: il database e i file del sito vengono <b>sostituiti</b> da quelli della copia. Prima viene fatta da sola una copia del sito di adesso.</span></label>
      <button class="cs-btn danger" type="submit">Carica e ripristina</button>
    </form>
  </div>
</div>

<div class="cs-card">
  <h2>Copie sul server</h2>
  <?php if (!$copie): ?>
    <p class="cs-note">Ancora nessuna copia.</p>
  <?php else: ?>
  <table class="cs-table">
    <tr><th>Copia</th><th>Contenuto</th><th>Origine</th><th></th></tr>
    <?php foreach ($copie as $c): $m = $c['manifest']; ?>
    <tr<?= $c['file'] === $pronta ? ' class="cs-nuova"' : '' ?>>
      <td><strong><?= h(date('d/m/Y H:i', $c['data'])) ?></strong><br><span class="cs-note"><?= h($c['file']) ?>, <?= h(agg_fmt_bytes($c['peso'])) ?></span></td>
      <td><?php if ($m): ?><?= (int)$m['tabelle'] ?> tabelle, <?= number_format((int)$m['righe'], 0, ',', '.') ?> righe, <?= (int)$m['file'] ?> file<br><span class="cs-note"><?= h(CMS_NOME . ' ' . ($m['cms_versione'] ?? '?')) ?></span><?php else: ?><span class="cs-note">Non si legge: non è una copia valida.</span><?php endif; ?></td>
      <td><?php if ($m): ?><?= $c['firmata'] ? '<span class="cs-tag si">questo sito</span>' : '<span class="cs-tag no">altro sito</span>' ?><br><span class="cs-note"><?= h((string)($m['sito'] ?? '')) ?></span><?php endif; ?></td>
      <td>
        <div class="cs-azioni">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="scarica"><input type="hidden" name="file" value="<?= h($c['file']) ?>"><button class="cs-btn primary" type="submit">Scarica</button></form>
          <form method="post" data-conferma="Elimino questa copia dal server?"><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="file" value="<?= h($c['file']) ?>"><button class="cs-btn" type="submit">Elimina</button></form>
          <?php if ($m): ?>
          <details><summary>Ripristina questa copia</summary>
            <form method="post" data-lavoro="Ripristino il sito dalla copia"><?= csrf_field() ?><input type="hidden" name="azione" value="ripristina"><input type="hidden" name="file" value="<?= h($c['file']) ?>">
              <label class="cs-spunta"><input type="checkbox" name="conferma" value="1" required> <span>Sostituisci database e file del sito con quelli di questa copia.</span></label>
              <?php if (!$c['firmata']): ?><p class="cs-note">Viene da un altro sito: i file con codice PHP (temi e moduli) non entrano.</p><?php endif; ?>
              <button class="cs-btn danger" type="submit">Ripristina adesso</button>
            </form>
          </details>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="cs-lavoro" id="cs-lavoro" hidden><div><h3 id="cs-lavoro-titolo" style="margin:0 0 .4rem">Sto lavorando</h3><p class="cs-note">Non chiudere questa pagina: su un sito grande ci vuole qualche minuto.</p></div></div>
<script<?= nonce_attr() ?>>
(function () {
  var lav = document.getElementById('cs-lavoro');
  document.querySelectorAll('form[data-lavoro]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var file = f.querySelector('input[type=file]');
      var lim = file ? +file.dataset.limite : 0;
      // Prima di mandarlo: un file oltre il limite del server non arriverebbe mai.
      if (file && file.files[0] && lim && file.files[0].size > lim) {
        e.preventDefault();
        var p = document.getElementById('cs-limite'); p.style.color = 'var(--errore-testo)';
        p.innerHTML = '<b>Questo file pesa ' + (file.files[0].size / 1048576).toFixed(1) + ' MB, il server ne accetta al massimo ' + (lim / 1048576).toFixed(1) + '.</b> Mettilo con il gestore dei file del tuo hosting nella cartella <code>aggiornamenti/copie/</code> e ripristinalo dall\'elenco qui sotto.';
        return;
      }
      if (f.querySelector('input[name=azione]').value !== 'crea' && !confirm('Ripristino il sito dalla copia? Database e file vengono sostituiti. Prima faccio una copia del sito di adesso.')) { e.preventDefault(); return; }
      document.getElementById('cs-lavoro-titolo').textContent = f.dataset.lavoro;
      lav.hidden = false;
    });
  });
})();
</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
