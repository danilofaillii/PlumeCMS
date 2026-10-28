<?php
/**
 * Log cron (1.4.0, riscritta): cosa fa il cron di plumeCMS, le due righe da mettere nel cron dell'hosting con il percorso
 * vero di questa installazione, com'e andato l'ultimo giro di ognuno, il registro del giro del mattino e il pulsante
 * "Esegui adesso" per chi il cron non l'ha ancora messo. Solo amministratore; le azioni in POST con il token CSRF.
 * Il lavoro sta in includes/cron.php, lo stesso di cron-daily.php e cron-newsletter.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_admin(); // sicurezza (ruoli): solo amministratore
require_once dirname(__DIR__) . '/includes/cron.php';

$adminTitle = 'Log cron';
$logFile = dirname(__DIR__) . '/logs/cron-daily.log';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    if ($azione === 'svuota') {
        @unlink($logFile);
        flash('success', 'Registro svuotato.');
    } elseif ($azione === 'esegui') {
        // I due giri, uno dopo l'altro, come li farebbe il cron. Niente stampa a schermo: l'esito va nel messaggio.
        session_write_close();
        ob_start();
        $esito = []; $errore = '';
        try { cron_giornaliero('pannello'); $esito[] = 'giro del mattino fatto'; } catch (Throwable $e) { $errore .= 'giro del mattino: ' . $e->getMessage() . '. '; }
        try { $r = cron_newsletter('pannello'); $esito[] = 'giro della newsletter fatto (' . ($r ? implode('; ', $r) : 'niente da mandare') . ')'; } catch (Throwable $e) { $errore .= 'newsletter: ' . $e->getMessage() . '. '; }
        ob_end_clean();
        @session_start();
        if ($esito) flash('success', 'Eseguito adesso: ' . implode(', ', $esito) . '. Il dettaglio è qui sotto.');
        if ($errore !== '') flash('error', 'Qualcosa non è andato: ' . $errore);
    }
    header('Location: cron-log.php');
    exit;
}

$stato = cron_stato();
$attivo = cron_attivo();
$righe = cron_righe();
$logContent = is_file($logFile) ? (string)file_get_contents($logFile) : '';
// Del registro si mostrano gli ultimi 60 giri, il piu recente in alto.
$giri = array_slice(array_reverse(array_values(array_filter(preg_split('/\n\n+/', trim($logContent)), fn($x) => trim($x) !== ''))), 0, 60);
$quando = fn(int $t) => $t ? date('d/m/Y \a\l\l\e H:i', $t) : 'mai';

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.cr-griglia{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;margin-bottom:1rem}
.cr-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.25rem}
.cr-card h2{font-size:1rem;margin:0 0 .5rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
.cr-card p,.cr-card li{font-size:.88rem;line-height:1.5}
.cr-card ul,.cr-card ol{margin:.3rem 0 .5rem 1.2rem}
.cr-card small{color:var(--text-muted)}
.cr-riga{display:block;width:100%;font:.82rem/1.4 ui-monospace,Menlo,Consolas,monospace;padding:.5rem .6rem;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);margin:.25rem 0 .6rem}
.cr-esito{font-size:.85rem;margin:.4rem 0 0;padding:.5rem .7rem;border-radius:8px;background:var(--bg)}
.cr-esito li{font-size:.82rem}
.cr-registro{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:.8rem 1rem;font:.8rem/1.5 ui-monospace,Menlo,Consolas,monospace;max-height:460px;overflow:auto;white-space:pre-wrap;color:var(--text);margin:0}
.cr-azioni{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center}
</style>

<div class="cr-griglia">
  <div class="cr-card">
    <h2>Cosa fa il cron</h2>
    <p>Il cron è il server che lancia da solo due file del CMS a orari fissi. Senza, il sito funziona, ma queste cose non succedono da sole:</p>
    <p><b>Il giro del mattino</b> (<code>cron-daily.php</code>, una volta al giorno):</p>
    <ul>
      <li>applica i pacchetti di aggiornamento programmati in Aggiornamenti;</li>
      <li>avvisa i motori di ricerca (IndexNow) delle pagine nuove o cambiate;</li>
      <li>fa i compiti dei moduli installati, se ne hanno.</li>
    </ul>
    <p><b>Il giro della newsletter</b> (<code>cron-newsletter.php</code>, ogni 5 minuti):</p>
    <ul>
      <li>fa partire le campagne programmate quando arriva la loro ora;</li>
      <li>manda le email a piccoli giri, senza superare il limite orario della newsletter;</li>
      <li>fa avanzare i flussi automatici (per esempio il benvenuto ai nuovi iscritti).</li>
    </ul>
  </div>

  <div class="cr-card">
    <h2>Come si va adesso</h2>
<?php foreach (['giornaliero' => 'Giro del mattino', 'newsletter' => 'Giro della newsletter'] as $k => $nome): $u = $stato[$k] ?? null; $ok = $attivo[$k]; ?>
    <p style="margin-top:.6rem"><b><?= h($nome) ?></b>
      <span class="badge <?= $ok ? 'badge-published' : 'badge-draft' ?>" data-cron="<?= h($k) ?>"><?= $ok ? 'il cron gira' : 'il cron non gira' ?></span></p>
    <p><small>Ultimo giro dal cron dell'hosting: <?= h($quando((int)$attivo[$k . '_quando'])) ?>.
      <?php if ($u && ($u['da'] ?? '') === 'pannello'): ?>Ultimo giro in assoluto: <?= h($quando((int)$u['quando'])) ?>, dal pulsante Esegui adesso.<?php endif; ?></small></p>
<?php if ($u): ?>
    <ul class="cr-esito"><?php foreach ((array)$u['righe'] as $r): ?><li><?= h((string)$r) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php endforeach; ?>
    <div class="cr-azioni" style="margin-top:1rem">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="esegui"><button type="submit" class="btn btn-primary btn-sm" id="cron-esegui">Esegui adesso</button></form>
      <small>Fa subito i due giri, come li farebbe il cron. Serve finché il cron non è impostato: non lo sostituisce.</small>
    </div>
  </div>
</div>

<div class="cr-card" style="margin-bottom:1rem">
  <h2>Le due righe da mettere nel cron dell'hosting</h2>
  <p>Con il percorso di questa installazione (<code><?= h(cms_radice()) ?></code>). In <b>cPanel</b>:</p>
  <ol>
    <li>apri <b>Processi cron</b> (Cron Jobs), nella sezione Avanzate;</li>
    <li>in "Aggiungi nuovo processo cron" scegli l'intervallo scritto sotto ogni riga;</li>
    <li>nel campo <b>Comando</b> incolla il comando (un clic sul riquadro lo copia) e premi Aggiungi.</li>
  </ol>
<?php foreach ($righe as [$intervallo, $comando, $spiega]): ?>
  <p style="margin:.6rem 0 0"><b><?= h(ucfirst($spiega)) ?></b> <small>(intervallo <code><?= h($intervallo) ?></code>)</small></p>
  <input type="text" class="cr-riga" readonly value="<?= h($comando) ?>" data-copia-se-stesso aria-label="Comando del cron, <?= h($spiega) ?>">
<?php endforeach; ?>
  <p><small>Con un altro pannello (Plesk, DirectAdmin) o con il crontab a mano la riga completa è l'intervallo seguito dal comando, per esempio <code><?= h($righe[0][0] . ' ' . $righe[0][1]) ?></code>.
    Il comando usa <code><?= h(cron_php()) ?></code>: se il cron risponde con un errore di versione, usa il PHP della versione del sito (in cPanel di solito <code>/opt/cpanel/ea-php84/root/usr/bin/php</code>).
    Se l'hosting sa chiamare solo un indirizzo web, metti <code>define('CMS_CRON_TOKEN', '...')</code> (almeno 32 caratteri a caso) in <code>includes/config.local.php</code> e fai chiamare <code><?= h(rtrim(preg_replace('~/admin/?$~', '', cms_url_pannello()), '/')) ?>/cron-daily.php?token=...</code>; in quel caso togli dal <code>.htaccess</code> la riga che vieta i file cron dal web.</small></p>
</div>

<div class="cr-card">
  <h2>Registro del giro del mattino <small><?= $logContent !== '' ? round(strlen($logContent) / 1024, 1) . ' KB, il giro più recente in alto' : '' ?></small></h2>
<?php if ($giri): ?>
  <pre class="cr-registro"><?= h(implode("\n\n", $giri)) ?></pre>
  <form method="post" data-conferma="Svuotare il registro del cron?" style="margin-top:.7rem"><?= csrf_field() ?><input type="hidden" name="azione" value="svuota"><button type="submit" class="btn btn-secondary btn-sm">Svuota il registro</button></form>
<?php else: ?>
  <p>Ancora vuoto: il giro del mattino non è mai partito. Metti le due righe qui sopra nel cron dell'hosting, oppure premi <b>Esegui adesso</b> per provarlo subito.</p>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
