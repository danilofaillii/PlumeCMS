<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/aggiornamenti.php';
require_once dirname(__DIR__) . '/includes/versione-nuova.php'; // 1.3.0: c'è una versione nuova?
require_admin();

// 1.3.0: il controllo della versione chiesto dal browser (dashboard e questa pagina), dopo il caricamento: risponde in JSON.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'versione_controlla' && !empty($_SERVER['HTTP_X_CSRF'])) {
    verify_csrf();
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $c = versione_controlla(!empty($_POST['forza']));
    $nuova = versione_nuova();
    echo json_encode(['nuova' => (bool)$nuova, 'versione' => $nuova['versione'] ?? '', 'installata' => cms_versione(), 'errore' => (string)($c['errore'] ?? '')], JSON_UNESCAPED_UNICODE);
    exit;
}

// Avanzamento di un'operazione lunga. Il pannello lo chiede ogni secondo
// mentre applica o ripristina, così si vede a che punto e invece di
// guardare una pagina ferma. session_write_close() serve: senza, questa
// richiesta resterebbe in coda dietro a quella che sta lavorando.
if (isset($_GET['progresso'])) {
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        agg_progresso_leggi((string)$_GET['progresso'])
            ?: ['fatti' => 0, 'totale' => 1, 'testo' => 'Preparo il lavoro', 'finito' => false, 'ok' => true],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$adminTitle = 'Aggiornamenti';
$esito = null;      // ['ok' => bool, 'titolo' => ..., 'log' => []]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'carica') {
            $f = $_FILES['pacchetto'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                $err = $f['error'] ?? -1;
                $msg = in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE]) ? 'Il file supera il limite di caricamento del server.' : 'Caricamento fallito (codice ' . $err . ').';
                throw new RuntimeException($msg);
            }
            $id = agg_registra_pacchetto($f['tmp_name'], $f['name']);
            $p = agg_leggi_stato($id);
            if ($p['applica_dal'] !== '' && $p['applica_dal'] > date('Y-m-d')) {
                flash('success', 'Pacchetto "' . $p['nome'] . '" caricato. Verra applicato da solo il ' . date('d/m/Y', strtotime($p['applica_dal'])) . ' alle 06:00. Se vuoi applicarlo prima, usa "Applica ora".');
            } else {
                flash('success', 'Pacchetto "' . $p['nome'] . '" caricato. Controlla il riepilogo e premi "Applica ora".');
            }
        } elseif ($action === 'applica') {
            $id = $_POST['id'] ?? '';
            $p = agg_leggi_stato($id);
            if (!$p) throw new RuntimeException('Pacchetto non trovato.');
            // Il lavoro gira con la sessione chiusa (serve alla barra) e con
            // l'output in un buffer: un avviso di PHP che arrivasse al browser
            // manderebbe le intestazioni e romperebbe la sessione qui sotto.
            session_write_close();
            ob_start();
            $res = agg_applica($id);
            $rumore = trim((string)ob_get_clean());
            if (session_status() === PHP_SESSION_NONE) @session_start();
            if ($rumore !== '') $res['log'][] = 'Avvisi di PHP durante il lavoro, non bloccanti: ' . mb_substr($rumore, 0, 1500);
            $esito = ['ok' => $res['ok'], 'titolo' => ($res['ok'] ? 'Aggiornamento applicato: ' : 'Aggiornamento fallito: ') . $p['nome'], 'log' => $res['log']];
            if ($res['ok']) flash('success', 'Aggiornamento "' . $p['nome'] . '" applicato. ' . $res['riassunto'] . '.');
            else flash('error', 'Aggiornamento "' . $p['nome'] . '" fallito: ' . $res['riassunto'] . '. Il sito è stato riportato allo stato precedente.');
        } elseif ($action === 'elimina') {
            agg_elimina_pacchetto($_POST['id'] ?? '');
            flash('success', 'Pacchetto eliminato.');
        } elseif ($action === 'rimetti') {
            agg_rimetti_in_attesa($_POST['id'] ?? '');
            flash('success', 'Pacchetto rimesso in attesa: il cron lo applica alla sua data, oppure usa "Applica ora".');
        } elseif ($action === 'snapshot') {
            $id = agg_crea_snapshot('manuale');
            $s = agg_leggi_snapshot($id);
            flash('success', 'Snapshot creato: ' . $id . ' (' . $s['db']['tabelle'] . ' tabelle, ' . agg_fmt_bytes($s['db']['dimensione']) . ').');
        } elseif ($action === 'ripristina') {
            $id = $_POST['id'] ?? '';
            session_write_close();
            ob_start();
            $log = agg_ripristina($id);
            $rumore = trim((string)ob_get_clean());
            if (session_status() === PHP_SESSION_NONE) @session_start();
            if ($rumore !== '') $log[] = 'Avvisi di PHP durante il ripristino, non bloccanti: ' . mb_substr($rumore, 0, 1500);
            $esito = ['ok' => true, 'titolo' => 'Ripristino dallo snapshot ' . $id, 'log' => $log];
            flash('success', 'Snapshot ' . $id . ' ripristinato. Controlla il sito.');
        } elseif ($action === 'elimina_snapshot') {
            agg_elimina_snapshot($_POST['id'] ?? '');
            flash('success', 'Snapshot eliminato.');
        } elseif ($action === 'versione_controlla') {
            $c = versione_controlla(true);
            if ($c['errore'] !== '') flash('error', 'Controllo della versione non riuscito: ' . $c['errore']);
            elseif ($nv = versione_nuova()) flash('success', CMS_NOME . ' ' . $nv['versione'] . ' è disponibile (hai la ' . cms_versione() . '). Premi "Scarica e prepara".');
            else flash('success', 'Hai già l\'ultima versione di ' . CMS_NOME . ' (' . cms_versione() . ').');
        } elseif ($action === 'versione_prepara') {
            $id = versione_scarica_e_prepara();
            $p = agg_leggi_stato($id);
            flash('success', 'Aggiornamento "' . ($p['nome'] ?? $id) . '" scaricato e controllato (dimensione e sha256): è nella lista qui sotto. Leggi il riepilogo e premi "Applica ora".');
        } elseif ($action === 'versione_sito') {
            $perche = versione_sito_salva((string)($_POST['sito'] ?? ''));
            if ($perche !== '') flash('error', $perche);
            else flash('success', 'Da adesso il pannello chiede le versioni nuove a ' . versione_sito() . '. Premi "Controlla adesso" per provarlo.');
        } elseif ($action === 'versione_interruttore') {
            save_setting('versione_controllo', !empty($_POST['attivo']) ? '1' : '0');
            flash('success', !empty($_POST['attivo']) ? 'Il pannello controllerà da solo, una volta al giorno, se c\'è una versione nuova.' : 'Controllo automatico della versione spento.');
        } elseif ($action === 'applica_scaduti') {
            $righe = agg_applica_in_attesa();
            $esito = ['ok' => true, 'titolo' => 'Pacchetti in scadenza', 'log' => $righe ?: ['Nessun pacchetto da applicare oggi.']];
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$controlli  = agg_controlli();
$versioneCache = versione_cache();
$versioneNuova = versione_nuova();
$versioneInCoda = $versioneNuova ? versione_in_coda($versioneNuova) : null;
$pacchetti  = agg_lista_pacchetti();
$snapshots  = agg_lista_snapshot();
$daApplicare = agg_pacchetti_da_applicare();

require_once __DIR__ . '/includes/admin-header.php';
?>

<style>
.agg-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}
@media(max-width:900px){.agg-grid{grid-template-columns:1fr}}
.agg-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:1.2rem 1.4rem}
.agg-card h2{font-size:1.05rem;margin:0 0 0.8rem}
.agg-check{list-style:none;padding:0;margin:0;font-size:0.88rem}
.agg-check li{padding:0.25rem 0;display:flex;gap:0.5rem;align-items:flex-start}
.agg-ok{color:var(--successo-testo);font-weight:700}.agg-ko{color:var(--errore-testo);font-weight:700}
.agg-table{width:100%;border-collapse:collapse;font-size:0.86rem}
.agg-table th{text-align:left;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);padding:0.5rem 0.6rem;border-bottom:1px solid var(--border)}
.agg-table td{padding:0.6rem;border-bottom:1px solid var(--border);vertical-align:top}
.agg-stato{display:inline-block;padding:0.15rem 0.55rem;border-radius:999px;font-size:0.75rem;font-weight:700}
.agg-stato.in_attesa{background:var(--mustard-light);color:var(--attenzione-testo)}.agg-stato.applicato{background:var(--sage-light);color:var(--successo-testo)}.agg-stato.fallito{background:var(--errore-chiaro);color:var(--errore-testo)}
.agg-btn{display:inline-block;padding:0.45rem 0.9rem;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-family:var(--font);font-size:0.82rem;font-weight:600;cursor:pointer;color:var(--text)}
.agg-btn.primary{background:var(--coral);color:var(--su-accento);border-color:var(--coral)}
.agg-btn.danger{color:var(--errore-testo);border-color:var(--errore-bordo)}
.agg-log{background:var(--codice-fondo);color:var(--codice-testo);padding:1rem;border-radius:8px;font-family:monospace;font-size:0.8rem;white-space:pre-wrap;word-break:break-word;max-height:420px;overflow:auto;margin:0.6rem 0 0}
.agg-esito{border-radius:12px;padding:1rem 1.4rem;margin-bottom:1.2rem;border:1px solid}
.agg-esito.ok{background:var(--sage-light);border-color:var(--successo-bordo)}.agg-esito.ko{background:var(--errore-chiaro);border-color:var(--errore-bordo)}
.agg-note{font-size:0.82rem;color:var(--text-muted);margin:0.4rem 0 0}
details summary{cursor:pointer;font-size:0.82rem;color:var(--text-muted)}
.agg-manifest{font-size:0.82rem;margin:0.4rem 0 0;padding-left:1rem}
.agg-lavoro{position:fixed;inset:0;background:var(--velo);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.agg-lavoro[hidden]{display:none}
.agg-lavoro-box{background:var(--surface);border-radius:14px;padding:1.6rem 1.8rem;max-width:520px;width:100%;box-shadow:var(--ombra-grande)}
.agg-lavoro-box h3{margin:0 0 0.3rem;font-size:1.05rem}
.agg-barra{height:12px;border-radius:999px;background:var(--border);overflow:hidden;margin:1rem 0 0.6rem}
.agg-barra i{display:block;height:100%;width:0;background:var(--coral);border-radius:999px;transition:width 0.4s ease}
.agg-barra.indeterminata i{width:35%;animation:agg-scorre 1.4s ease-in-out infinite}
@keyframes agg-scorre{0%{margin-left:-35%}100%{margin-left:100%}}
.agg-passo{font-size:0.9rem;margin:0;min-height:2.4em}
.agg-tempo{font-size:0.78rem;color:var(--text-muted);margin:0.5rem 0 0}
.agg-lavoro-box.ko .agg-barra i{background:var(--errore)}
.agg-versione.nuova{border-color:var(--coral);box-shadow:0 0 0 3px var(--fuoco)}
</style>



<?php if ($esito): ?>
<div class="agg-esito <?= $esito['ok'] ? 'ok' : 'ko' ?>">
  <strong><?= h($esito['titolo']) ?></strong>
  <pre class="agg-log"><?= h(implode("\n", $esito['log'])) ?></pre>
</div>
<?php endif; ?>

<div class="agg-card agg-versione<?= $versioneNuova ? ' nuova' : '' ?>" style="margin-bottom:1.2rem" id="agg-versione">
  <h2><?= $versioneNuova ? h(CMS_NOME . ' ' . $versioneNuova['versione'] . ' è disponibile') : 'Versione' ?></h2>
  <p class="agg-note" style="margin-top:0">Hai <?= h(CMS_NOME) ?> <b><?= h(cms_versione()) ?></b>.
    <?php if (!versione_attivo()): ?>Il controllo automatico è spento.
    <?php elseif (!$versioneCache): ?>Ancora nessun controllo: lo faccio adesso.
    <?php elseif ($versioneCache['errore'] !== ''): ?>Ultimo controllo il <?= h(date('d/m/Y H:i', (int)$versioneCache['quando'])) ?>: <?= h($versioneCache['errore']) ?>
    <?php elseif ($versioneNuova): ?>La versione nuova pesa <?= h(agg_fmt_bytes((int)$versioneNuova['dimensione'])) ?> (controllato il <?= h(date('d/m/Y H:i', (int)$versioneCache['quando'])) ?>).
    <?php else: ?>È l'ultima (controllato il <?= h(date('d/m/Y H:i', (int)$versioneCache['quando'])) ?>).<?php endif; ?></p>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-top:.7rem">
    <?php if ($versioneNuova && !$versioneInCoda): ?>
    <form method="post" data-conferma="Scarico <?= h(CMS_NOME . ' ' . $versioneNuova['versione']) ?> dal sito ufficiale e lo metto nella lista dei pacchetti? Il sito non cambia finché non premi Applica ora.">
      <?= csrf_field() ?><input type="hidden" name="action" value="versione_prepara">
      <button class="agg-btn primary" type="submit">Scarica e prepara</button>
    </form>
    <?php elseif ($versioneInCoda): ?><span class="agg-stato in_attesa">già scaricato: è nella lista qui sotto, premi Applica ora</span><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="versione_controlla"><button class="agg-btn" type="submit">Controlla adesso</button></form>
    <form method="post" style="margin-left:auto"><?= csrf_field() ?><input type="hidden" name="action" value="versione_interruttore">
      <label class="agg-note" style="display:flex;gap:.4rem;align-items:center;margin:0"><input type="checkbox" name="attivo" value="1"<?= versione_attivo() ? ' checked' : '' ?> data-invia-subito> Controlla da solo, una volta al giorno</label>
      <noscript><button class="agg-btn" type="submit">Salva</button></noscript>
    </form>
  </div>
  <p class="agg-note">Il pannello chiede a <code><?= h(versione_sito()) ?></code> qual è l'ultima versione. Lo zip si scarica solo da lì, in HTTPS, e si usa solo se dimensione e impronta sha256 sono quelle annunciate.</p>
  <?php if (defined('CMS_AGGIORNAMENTI_URL')): ?><p class="agg-note">L'indirizzo è fissato nel file <code>config.local.php</code> (<code>CMS_AGGIORNAMENTI_URL</code>): si cambia lì.</p>
  <?php else: ?><form method="post" class="agg-riga" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;margin-top:1rem"><?= csrf_field() ?><input type="hidden" name="action" value="versione_sito">
    <label class="agg-note" style="flex:1;min-width:16rem;margin:0">Indirizzo del sito da cui arrivano gli aggiornamenti<br>
      <input type="url" name="sito" value="<?= h((string)get_setting('versione_sito', '')) ?>" placeholder="<?= h(VERSIONE_SITO_UFFICIALE) ?>" maxlength="300" style="width:100%;margin-top:.35rem;padding:.55rem .7rem;border:1px solid var(--border);border-radius:8px;font:inherit;color:inherit;background:var(--campo)"></label>
    <button class="agg-btn" type="submit">Salva l'indirizzo</button>
  </form><p class="agg-note">Vuoto vuol dire quello di partenza (<code><?= h(VERSIONE_SITO_UFFICIALE) ?></code>). Deve essere un sito plumeCMS 1.3.0 o più nuovo, con lo zip di aggiornamento nella pagina Download.</p><?php endif; ?>
</div>

<?php if ($daApplicare): ?>
<div class="agg-esito ko">
  <strong><?= count($daApplicare) ?> pacchett<?= count($daApplicare) === 1 ? 'o' : 'i' ?> in scadenza non ancora applicat<?= count($daApplicare) === 1 ? 'o' : 'i' ?>.</strong>
  Il cron delle 06:00 li applica da solo. Se vuoi farlo adesso:
  <form method="post" style="display:inline">
    <?= csrf_field() ?><input type="hidden" name="action" value="applica_scaduti">
    <button class="agg-btn primary" type="submit">Applica adesso quelli in scadenza</button>
  </form>
</div>
<?php endif; ?>

<div class="agg-grid">
  <div class="agg-card">
    <h2>Carica un pacchetto</h2>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="carica">
      <input type="file" name="pacchetto" accept=".zip" required style="font-size:0.85rem;margin-bottom:0.6rem;display:block">
      <button class="agg-btn primary" type="submit">Carica</button>
    </form>
    <p class="agg-note">Uno zip con dentro <code>manifest.json</code>. Se il pacchetto ha una data, resta in attesa e viene applicato quel giorno alle 06:00. Prima di ogni aggiornamento viene creato uno snapshot; se qualcosa fallisce, il sito torna com'era da solo.</p>
  </div>
  <div class="agg-card">
    <h2>Controlli</h2>
    <ul class="agg-check">
      <?php foreach ($controlli as $c): ?>
      <li><span class="<?= $c['ok'] ? 'agg-ok' : 'agg-ko' ?>"><?= $c['ok'] ? 'OK' : 'NO' ?></span> <span><?= h($c['testo']) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <form method="post" style="margin-top:0.8rem">
      <?= csrf_field() ?><input type="hidden" name="action" value="snapshot">
      <button class="agg-btn" type="submit">Crea uno snapshot adesso</button>
    </form>
  </div>
</div>

<div class="agg-card" style="margin-bottom:1.2rem">
  <h2>Pacchetti</h2>
  <?php if (!$pacchetti): ?>
    <p class="agg-note">Nessun pacchetto caricato.</p>
  <?php else: ?>
  <table class="agg-table">
    <tr><th>Pacchetto</th><th>Stato</th><th>Date</th><th>Contenuto</th><th></th></tr>
    <?php foreach ($pacchetti as $p): $m = $p['manifest']; ?>
    <tr>
      <td><strong><?= h($p['nome']) ?></strong><br><span class="agg-note"><?= h($m['descrizione']) ?></span>
        <?php if ($m['note']): ?><p class="agg-note"><?= nl2br(h($m['note'])) ?></p><?php endif; ?>
        <?php if ($p['log']): ?><details><summary>Log</summary><pre class="agg-log"><?= h(implode("\n", $p['log'])) ?></pre></details><?php endif; ?>
      </td>
      <td><span class="agg-stato <?= h($p['stato']) ?>"><?= ['in_attesa' => 'in attesa', 'applicato' => 'applicato', 'fallito' => 'fallito'][$p['stato']] ?? h($p['stato']) ?></span></td>
      <td style="white-space:nowrap">caricato <?= h(substr($p['caricato'], 0, 16)) ?><br>
        <?php if ($p['applica_dal']): ?>previsto <?= h(date('d/m/Y', strtotime($p['applica_dal']))) ?><br><?php endif; ?>
        <?php if ($p['applicato']): ?>applicato <?= h(substr($p['applicato'], 0, 16)) ?><br><?php endif; ?>
        <?php if ($p['snapshot']): ?>snapshot <?= h($p['snapshot']) ?><?php endif; ?>
      </td>
      <td>
        <ul class="agg-manifest">
          <li><?= count($m['file']) ?> file</li>
          <li><?= count($m['sql']) ?> file SQL</li>
          <li><?= count($m['redirect']) ?> redirect</li>
        </ul>
      </td>
      <td style="white-space:nowrap">
        <?php if ($p['stato'] !== 'applicato'): ?>
        <form method="post" style="display:inline" data-conferma="Applico il pacchetto &quot;<?= h(($p['nome'])) ?>&quot; adesso? Prima viene creato uno snapshot.">
          <?= csrf_field() ?><input type="hidden" name="action" value="applica"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
          <button class="agg-btn primary" type="submit">Applica ora</button>
        </form>
        <?php endif; ?>
        <?php if ($p['stato'] === 'fallito'): ?>
        <form method="post" style="display:inline">
          <?= csrf_field() ?><input type="hidden" name="action" value="rimetti"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
          <button class="agg-btn" type="submit">Rimetti in attesa</button>
        </form>
        <?php endif; ?>
        <form method="post" style="display:inline" data-conferma="Elimino il pacchetto dall'elenco? Il sito non cambia.">
          <?= csrf_field() ?><input type="hidden" name="action" value="elimina"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
          <button class="agg-btn danger" type="submit">Elimina</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="agg-card">
  <h2>Snapshot</h2>
  <p class="agg-note">Si tengono gli ultimi <?= AGG_SNAPSHOT_DA_TENERE ?>. Ogni snapshot contiene il database intero più una copia dei soli file che il pacchetto stava per sovrascrivere: uno snapshot manuale ha quindi zero file, ed e normale. Ripristinare rimette database e file: tutto quello che è cambiato dopo, compresi gli articoli pubblicati nel frattempo, torna indietro.</p>
  <?php if (!$snapshots): ?>
    <p class="agg-note">Nessuno snapshot.</p>
  <?php else: ?>
  <table class="agg-table">
    <tr><th>Snapshot</th><th>Creato</th><th>Database</th><th>File copiati</th><th></th></tr>
    <?php foreach ($snapshots as $s): ?>
    <tr>
      <td><strong><?= h($s['etichetta']) ?></strong><br><span class="agg-note"><?= h($s['id']) ?></span></td>
      <td style="white-space:nowrap"><?= h(substr($s['creato'], 0, 16)) ?></td>
      <td><?= (int)$s['db']['tabelle'] ?> tabelle, <?= number_format((int)$s['db']['righe'], 0, ',', '.') ?> righe, <?= agg_fmt_bytes((int)$s['db']['dimensione']) ?></td>
      <td><?= count($s['files']) ?></td>
      <td style="white-space:nowrap">
        <form method="post" style="display:inline" data-conferma="Ripristino il sito allo stato di questo snapshot? Le modifiche fatte dopo andranno perse.">
          <?= csrf_field() ?><input type="hidden" name="action" value="ripristina"><input type="hidden" name="id" value="<?= h($s['id']) ?>">
          <button class="agg-btn danger" type="submit">Ripristina</button>
        </form>
        <form method="post" style="display:inline" data-conferma="Elimino questo snapshot?">
          <?= csrf_field() ?><input type="hidden" name="action" value="elimina_snapshot"><input type="hidden" name="id" value="<?= h($s['id']) ?>">
          <button class="agg-btn" type="submit">Elimina</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="agg-lavoro" id="agg-lavoro" hidden>
  <div class="agg-lavoro-box" id="agg-lavoro-box">
    <h3 id="agg-lavoro-titolo">Sto lavorando</h3>
    <p class="agg-note" id="agg-lavoro-sotto">Non chiudere questa pagina. Se qualcosa va storto il sito torna come prima da solo.</p>
    <div class="agg-barra indeterminata" id="agg-barra"><i></i></div>
    <p class="agg-passo" id="agg-passo">Apro il pacchetto</p>
    <p class="agg-tempo" id="agg-tempo">0 secondi</p>
  </div>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && versione_da_controllare()): /* 1.3.0: il controllo del giorno, dopo il caricamento della pagina */ ?>
<script<?= nonce_attr() ?>>
fetch('aggiornamenti.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': <?= json_encode(csrf_token()) ?> }, body: new URLSearchParams({ action: 'versione_controlla', _csrf: <?= json_encode(csrf_token()) ?> }) })
  .then(function (r) { return r.json(); }).then(function () { location.reload(); }).catch(function () {});
</script>
<?php endif; ?>
<script<?= nonce_attr() ?>>
document.querySelectorAll('[data-invia-subito]').forEach(function (c) { c.addEventListener('change', function () { c.form.submit(); }); });
(function () {
  var AZIONI = {
    applica:         'Applico il pacchetto',
    ripristina:      'Ripristino lo snapshot',
    applica_scaduti: 'Applico i pacchetti in scadenza',
    carica:          'Carico il pacchetto',
    versione_prepara: 'Scarico la versione nuova dal sito ufficiale',
    snapshot:        'Creo lo snapshot'
  };
  var overlay = document.getElementById('agg-lavoro');
  var box     = document.getElementById('agg-lavoro-box');
  var barra   = document.getElementById('agg-barra');
  var riempi  = barra.querySelector('i');
  var passo   = document.getElementById('agg-passo');
  var tempo   = document.getElementById('agg-tempo');
  var titolo  = document.getElementById('agg-lavoro-titolo');

  function secondi(n) { return n === 1 ? '1 secondo' : n + ' secondi'; }

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var campoAzione = form.querySelector('input[name="action"]');
      if (!campoAzione || !AZIONI[campoAzione.value]) return;
      var azione = campoAzione.value;
      var campoId = form.querySelector('input[name="id"]');
      var id = campoId ? campoId.value : '';

      titolo.textContent = AZIONI[azione];
      passo.textContent = azione === 'carica' ? 'Mando il file al server' : 'Preparo il lavoro';
      overlay.hidden = false;
      form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

      var partenza = Date.now();
      setInterval(function () {
        tempo.textContent = secondi(Math.round((Date.now() - partenza) / 1000));
      }, 1000);

      if (azione !== 'applica' || !id) return;   // per gli altri resta la barra che scorre

      (function chiedi() {
        fetch('?progresso=' + encodeURIComponent(id), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.testo) passo.textContent = d.testo;
            if (d.totale > 1 || d.fatti > 0) {
              barra.classList.remove('indeterminata');
              riempi.style.width = Math.min(100, Math.round((d.fatti / d.totale) * 100)) + '%';
            }
            if (d.ok === false) box.classList.add('ko');
            if (!d.finito) setTimeout(chiedi, 1000);
          })
          .catch(function () { setTimeout(chiedi, 2000); });
      })();
    });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
