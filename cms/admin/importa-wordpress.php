<?php
/**
 * Impostazioni > Importa da WordPress (1.4.0). Collegamento, analisi con il report, scelta, import a giri con l'avanzamento,
 * riepilogo in prova con l'anteprima, conferma o annullamento. Il motore sta in includes/importa-wp*.php.
 * Solo per l'amministratore; ogni azione in POST con il token CSRF (le chiamate dei giri con l'intestazione X-CSRF).
 */
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/importa-wp-lavoro.php';
require_admin();
importa_wp_installa();
importa_wp_scadenza();

$adminTitle = 'Importa da WordPress';
$s = importa_wp_stato();
$fase = (string)($s['fase'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = (string)($_POST['azione'] ?? '');
    // Un giro di lavoro: risposta JSON, per lo script della pagina.
    if ($azione === 'giro') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (!csrf_ok()) { http_response_code(403); echo json_encode(['errore' => 'Token scaduto: ricarica la pagina.']); exit; }
        importa_wp_chiave();          // legge la chiave dalla sessione, se serve, prima di chiuderla
        session_write_close();        // gli altri pannelli aperti non aspettano questo giro
        @ignore_user_abort(true);     // se il browser si chiude, il giro finisce e salva dov'era
        @set_time_limit(IMPORTA_WP_GIRO + 90);
        $blocco = 'importa_wp_' . md5(DB_NAME . DB_PREFIX);
        if ((int)db()->query('SELECT GET_LOCK(' . db()->quote($blocco) . ', 0)')->fetchColumn() !== 1) { echo json_encode(['occupato' => true]); exit; }
        $risposta = [];
        try {
            $s = importa_wp_stato();
            $fino = microtime(true) + importa_wp_giro();
            $finito = false;
            unset($s['errore']);
            if (($s['fase'] ?? '') === 'analisi') {
                while (!$finito && microtime(true) < $fino) $finito = importa_wp_analisi_passo($s);
                [$fatti, $tot, $testo] = $finito ? [1, 1, 'Report pronto'] : importa_wp_analisi_avanzamento($s);
            } elseif (($s['fase'] ?? '') === 'importa') {
                $finito = importa_wp_import_giro($s, $fino);
                [$fatti, $tot, $testo] = $finito ? [1, 1, 'Fatto'] : importa_wp_avanzamento($s);
            } else {
                [$fatti, $tot, $testo, $finito] = [1, 1, '', true];
            }
            importa_wp_stato_salva($s);
            $risposta = ['fase' => $s['fase'] ?? '', 'fatti' => $fatti, 'totale' => $tot, 'testo' => $testo, 'finito' => $finito];
        } catch (ImportaWpErrore $e) {
            $s['errore'] = ['testo' => $e->getMessage(), 'tipo' => $e->tipo];
            importa_wp_stato_salva($s);
            $risposta = ['errore' => $e->getMessage(), 'tipo' => $e->tipo];
        } catch (Throwable $e) {
            error_log('importa-wordpress: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
            $s['errore'] = ['testo' => 'Errore imprevisto: ' . $e->getMessage(), 'tipo' => 'interno'];
            importa_wp_stato_salva($s);
            $risposta = ['errore' => 'Errore imprevisto: ' . $e->getMessage() . '. Riprova: il lavoro riprende da dove era.', 'tipo' => 'interno'];
        }
        db()->query('SELECT RELEASE_LOCK(' . db()->quote($blocco) . ')');
        echo json_encode($risposta, JSON_UNESCAPED_UNICODE);
        exit;
    }
    verify_csrf();
    try {
        if ($azione === 'collega') {
            if (in_array($fase, ['importa', 'prova'], true)) throw new ImportaWpErrore('C\'è già un import in corso: confermalo o annullalo prima di cominciarne un altro.');
            $_SESSION['importa_wp_form'] = ['indirizzo' => mb_substr((string)($_POST['indirizzo'] ?? ''), 0, 300), 'utente' => mb_substr((string)($_POST['utente'] ?? ''), 0, 100)];
            $nuovo = importa_wp_collega((string)($_POST['indirizzo'] ?? ''), (string)($_POST['utente'] ?? ''), (string)($_POST['password'] ?? ''));
            // Le voci di un lavoro vecchio (confermato o annullato) non servono più.
            if (!empty($s['lavoro'])) db()->prepare('DELETE FROM ' . tbl('importa_wp_voci') . ' WHERE lavoro = ?')->execute([(string)$s['lavoro']]);
            importa_wp_stato_salva($nuovo);
            unset($_SESSION['importa_wp_form']);
            sicurezza_log('importa_wp', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'collegato a ' . $nuovo['base']);
            header('Location: importa-wordpress.php?avvia=1'); exit;
        } elseif ($azione === 'password') {
            if (!in_array($fase, ['analisi', 'importa'], true)) throw new ImportaWpErrore('Non c\'è un lavoro da riprendere.');
            if (trim((string)($_POST['password'] ?? '')) === '') throw new ImportaWpErrore('Scrivi la password per le applicazioni.');
            $s['segreto'] = importa_wp_cifra((string)$_POST['password']);
            unset($s['errore']);
            importa_wp_stato_salva($s);
            header('Location: importa-wordpress.php?avvia=1'); exit;
        } elseif ($azione === 'scelta') {
            if ($fase !== 'pronto') throw new ImportaWpErrore('Il report non è pronto.');
            importa_wp_avvia($s, importa_wp_scelta_da($_POST));
            importa_wp_stato_salva($s);
            header('Location: importa-wordpress.php?avvia=1'); exit;
        } elseif ($azione === 'conferma') {
            if ($fase !== 'prova') throw new ImportaWpErrore('Non c\'è un import in prova da confermare.');
            importa_wp_conferma($s, !empty($_POST['menu_principale']) ? (int)($_POST['menu_id'] ?? 0) : 0);
            importa_wp_stato_salva($s);
            flash('success', 'Import confermato: i contenuti sono sul sito, come erano su WordPress (pubblicati, bozze e programmati), e i redirect sono attivi.');
            header('Location: importa-wordpress.php'); exit;
        } elseif ($azione === 'annulla') {
            if (!in_array($fase, ['importa', 'prova'], true)) throw new ImportaWpErrore('Non c\'è un import da annullare.');
            $n = importa_wp_annulla($s);
            importa_wp_stato_salva($s);
            flash('success', "Import annullato: tolte $n cose (contenuti, file, categorie, tag, menu e redirect). Il sito è tornato com'era prima.");
            header('Location: importa-wordpress.php'); exit;
        } elseif ($azione === 'abbandona') {
            if (in_array($fase, ['importa', 'prova'], true)) throw new ImportaWpErrore('L\'import è già cominciato: usa Annulla l\'import.');
            save_setting('importa_wp_stato', '');
            header('Location: importa-wordpress.php'); exit;
        }
    } catch (ImportaWpErrore $e) {
        flash('error', $e->getMessage());
        header('Location: importa-wordpress.php'); exit;
    }
}

$form = $_SESSION['importa_wp_form'] ?? ['indirizzo' => '', 'utente' => ''];
$report = $s['report'] ?? null;
$sito = rtrim(cms_url_sito(), '/');
$h = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.iw{max-width:1100px}
.iw-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.4rem 1.6rem;margin-bottom:1.2rem}
.iw-card h2{font-size:1.1rem;margin:0 0 .6rem}
.iw-card h3{font-size:.95rem;margin:1.1rem 0 .5rem}
.iw-nota{font-size:.86rem;color:var(--text-muted);line-height:1.55;margin:.4rem 0 0}
.iw-passi{margin:.6rem 0 0;padding-left:1.3rem;line-height:1.6;font-size:.92rem}
.iw-passi li{margin-bottom:.25rem}
.iw-griglia{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem}
@media(max-width:900px){.iw-griglia{grid-template-columns:1fr}}
.iw-barra{height:14px;background:var(--bg);border:1px solid var(--border);border-radius:999px;overflow:hidden;margin:.8rem 0 .5rem}
.iw-barra span{display:block;height:100%;width:0;background:var(--sage);transition:width .4s}
.iw-voci{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem;margin-top:.8rem}
.iw-voce{border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;background:var(--bg)}
.iw-voce label.iw-testa{display:flex;gap:.6rem;align-items:center;font-weight:700;font-size:1rem;cursor:pointer}
.iw-voce .iw-numero{margin-left:auto;font-size:1.4rem;font-weight:700}
.iw-voce p{margin:.35rem 0 0;font-size:.85rem;line-height:1.5}
.iw-voce .iw-diventa{color:var(--text-muted)}
.iw-stati{display:flex;flex-wrap:wrap;gap:.4rem 1rem;margin-top:.5rem;font-size:.85rem}
.iw-scorciatoie{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0 0}
.iw-tab{width:100%;border-collapse:collapse;font-size:.86rem;margin-top:.4rem}
.iw-tab th{text-align:left;font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);padding:.45rem .6rem;border-bottom:1px solid var(--border)}
.iw-tab td{padding:.5rem .6rem;border-bottom:1px solid var(--border);vertical-align:top}
.iw-elenco{margin:.4rem 0 0;padding-left:1.2rem;font-size:.88rem;line-height:1.55}
.iw-elenco li{margin-bottom:.3rem}
.iw-avviso{border-left:4px solid var(--mustard);background:var(--mustard-light);border-radius:var(--radius);padding:.9rem 1.2rem;margin-bottom:1.2rem;font-size:.92rem;line-height:1.55}
.iw-ok{border-left:4px solid var(--sage);background:var(--sage-light);border-radius:var(--radius);padding:.9rem 1.2rem;margin-bottom:1.2rem;font-size:.92rem;line-height:1.55}
.iw-errore{border-left:4px solid var(--coral);background:var(--coral-light);border-radius:var(--radius);padding:.9rem 1.2rem;margin:.8rem 0;font-size:.92rem;line-height:1.55}
.iw-errore[hidden],.iw-ok[hidden],[hidden]{display:none}
.iw-azioni{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-top:1rem}
.iw-spunta{display:flex;gap:.5rem;align-items:flex-start;font-size:.9rem;margin:.6rem 0}
.iw-spunta input{margin-top:.25rem}
.iw-pwd{font-family:monospace;letter-spacing:.05em}
</style>
<div class="iw">

<?php if ($fase === '' || $fase === 'confermato' || $fase === 'annullato'): ?>
  <?php if ($fase === 'confermato'): ?>
  <div class="iw-ok"><b>Ultimo import confermato</b> (<?= $h(date('d/m/Y H:i', (int)($s['esito']['confermato'] ?? time()))) ?>) da <?= $h($s['base'] ?? '') ?>. I contenuti sono in Articoli e Pagine. <a href="<?= $h($sito) ?>/" target="_blank" rel="noopener">Apri il sito</a>.</div>
  <?php elseif ($fase === 'annullato'): ?>
  <div class="iw-avviso"><b>Ultimo import annullato</b> (<?= $h(date('d/m/Y H:i', (int)($s['esito']['annullato'] ?? time()))) ?>): tolto tutto quello che era entrato.</div>
  <?php endif; ?>
  <div class="iw-griglia">
    <div class="iw-card">
      <h2>Porta qui il tuo sito WordPress</h2>
      <p class="iw-nota" style="margin-top:0">plumeCMS legge il sito WordPress (articoli, pagine, categorie, tag, immagini, menu, SEO), ti mostra cosa si può portare e cosa no, e lo importa in prova: lo guardi nell'anteprima e solo quando confermi diventa visibile. Il sito WordPress non viene toccato: si legge e basta.</p>
      <h3>Prima: crea una password per le applicazioni</h3>
      <ol class="iw-passi">
        <li>Entra nel pannello di WordPress e apri <b>Utenti &gt; Profilo</b>.</li>
        <li>In fondo, in <b>Password per le applicazioni</b>, scrivi un nome (per esempio <i>plumeCMS</i>) e premi <b>Aggiungi una nuova password per le applicazioni</b>.</li>
        <li>Copia la password che compare (sei gruppi di quattro lettere e cifre, come <span class="iw-pwd">abcd efgh ijkl mnop qrst uvwx</span>) e incollala qui accanto, spazi compresi. Non è la password con cui entri in WordPress.</li>
      </ol>
      <p class="iw-nota">Serve WordPress 5.6 o più nuovo, con il sito in HTTPS. Alla fine dell'import puoi cancellare la password per le applicazioni dallo stesso punto di WordPress.</p>
    </div>
    <div class="iw-card">
      <h2>Collegati al sito</h2>
      <form method="post" autocomplete="off" id="iw-collega">
        <?= csrf_field() ?><input type="hidden" name="azione" value="collega">
        <div class="form-group"><label for="iw-indirizzo">Indirizzo del sito WordPress</label><input id="iw-indirizzo" name="indirizzo" type="text" inputmode="url" placeholder="https://www.miosito.it" value="<?= $h($form['indirizzo']) ?>" required></div>
        <div class="form-group"><label for="iw-utente">Nome utente di WordPress</label><input id="iw-utente" name="utente" type="text" placeholder="il nome con cui entri in WordPress" value="<?= $h($form['utente']) ?>" required autocapitalize="off" spellcheck="false"></div>
        <div class="form-group"><label for="iw-password">Password per le applicazioni</label><input id="iw-password" name="password" type="password" class="iw-pwd" placeholder="abcd efgh ijkl mnop qrst uvwx" required autocomplete="new-password"><p class="hint">Si tiene cifrata solo per la durata dell'import, poi si cancella. Non compare mai nelle pagine né nei registri.</p></div>
        <div class="iw-azioni"><button class="btn btn-primary" type="submit">Collegati e analizza il sito</button></div>
      </form>
    </div>
  </div>

<?php elseif ($fase === 'analisi' || $fase === 'importa'): ?>
  <?php [$fatti, $tot, $testo] = $fase === 'analisi' ? importa_wp_analisi_avanzamento($s) : importa_wp_avanzamento($s); $err = $s['errore'] ?? null; ?>
  <div class="iw-card" id="iw-lavoro" data-avvia="<?= isset($_GET['avvia']) || $fase === 'analisi' ? '1' : '0' ?>" data-csrf="<?= $h(csrf_token()) ?>">
    <h2><?= $fase === 'analisi' ? 'Leggo il sito ' . $h($s['sito']['nome'] ?? '') : 'Importo da ' . $h($s['sito']['nome'] ?? '') ?></h2>
    <p class="iw-nota" style="margin-top:0"><?= $fase === 'analisi' ? 'Leggo tutto il sito per scriverti il report: niente entra ancora in plumeCMS.' : 'Tutto quello che entra resta in prova: i visitatori non vedono niente finché non confermi.' ?> Il lavoro va a giri brevi: se chiudi la pagina, riaprila e premi Riprendi, continua da dove era.</p>
    <div class="iw-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)round($fatti * 100 / max(1, $tot)) ?>" id="iw-barra"><span style="width:<?= (int)round($fatti * 100 / max(1, $tot)) ?>%"></span></div>
    <p id="iw-testo" style="margin:0;font-weight:600"><?= $h($testo) ?></p>
    <p class="iw-nota" id="iw-percento"><?= (int)round($fatti * 100 / max(1, $tot)) ?>%</p>
    <div class="iw-errore" id="iw-errore"<?= $err ? '' : ' hidden' ?>><?= $h($err['testo'] ?? '') ?></div>
    <?php if (($err['tipo'] ?? '') === 'password_persa' || !isset($s['segreto'])): ?>
    <form method="post" autocomplete="off" class="iw-spunta" style="flex-wrap:wrap">
      <?= csrf_field() ?><input type="hidden" name="azione" value="password">
      <span style="width:100%">Per continuare riscrivi la password per le applicazioni di <b><?= $h($s['utente'] ?? '') ?></b> (per sicurezza non si tiene oltre un giorno).</span>
      <input name="password" type="password" class="iw-pwd" placeholder="abcd efgh ijkl mnop qrst uvwx" required autocomplete="new-password" style="padding:.5rem .7rem;border:1px solid var(--border);border-radius:8px;min-width:280px">
      <button class="btn btn-primary" type="submit">Continua</button>
    </form>
    <?php endif; ?>
    <div class="iw-azioni">
      <button class="btn btn-primary" type="button" id="iw-riprendi" hidden>Riprendi</button>
      <?php if ($fase === 'analisi'): ?>
      <form method="post" data-conferma="Lascio perdere questa analisi?"><?= csrf_field() ?><input type="hidden" name="azione" value="abbandona"><button class="btn btn-secondary" type="submit">Lascia perdere</button></form>
      <?php else: ?>
      <form method="post" data-conferma="Annullo l'import? Tolgo tutto quello che è entrato finora."><?= csrf_field() ?><input type="hidden" name="azione" value="annulla"><button class="btn btn-danger" type="submit">Annulla l'import</button></form>
      <?php endif; ?>
    </div>
  </div>
  <script<?= nonce_attr() ?>>
  (function () {
    var box = document.getElementById('iw-lavoro'), barra = document.querySelector('#iw-barra span'), testo = document.getElementById('iw-testo'),
        perc = document.getElementById('iw-percento'), err = document.getElementById('iw-errore'), rip = document.getElementById('iw-riprendi'), fermo = false;
    function mostra(f, t, s) { var p = Math.round(f * 100 / Math.max(1, t)); barra.style.width = p + '%'; perc.textContent = p + '%'; box.querySelector('[role=progressbar]').setAttribute('aria-valuenow', p); if (s) testo.textContent = s; }
    function giro() {
      rip.hidden = true; err.hidden = true;
      var d = new FormData(); d.append('azione', 'giro'); d.append('_csrf', box.dataset.csrf);
      fetch('importa-wordpress.php', { method: 'POST', body: d, headers: { 'X-CSRF': box.dataset.csrf, 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.occupato) { testo.textContent = 'Sto finendo un giro iniziato prima...'; return setTimeout(giro, 2500); }
          if (j.errore) { err.textContent = j.errore; err.hidden = false; rip.textContent = 'Riprova'; rip.hidden = false; if (j.tipo === 'password_persa') location.reload(); return; }
          mostra(j.fatti, j.totale, j.testo);
          if (j.finito) { testo.textContent = 'Fatto: carico la pagina...'; location.href = 'importa-wordpress.php'; return; }
          if (!fermo) setTimeout(giro, 150);
        })
        .catch(function () { err.textContent = 'La connessione con il pannello si è interrotta. Premi Riprendi: il lavoro continua da dove era.'; err.hidden = false; rip.hidden = false; });
    }
    rip.addEventListener('click', giro);
    if (box.dataset.avvia === '1' && err.hidden) giro(); else { rip.hidden = false; if (err.hidden) testo.textContent = 'L\'import si è fermato qui: ' + testo.textContent + '. Premi Riprendi per continuare.'; }
  })();
  </script>

<?php elseif ($fase === 'pronto' && $report): $v = $report['voci']; ?>
  <div class="iw-card">
    <h2>Il report di <?= $h($report['sito']) ?></h2>
    <p class="iw-nota" style="margin-top:0"><a href="<?= $h($report['home']) ?>" target="_blank" rel="noopener noreferrer"><?= $h($report['home']) ?></a>. Autori: <?= $h(implode(', ', $report['autori']) ?: 'nessuno') ?>. <?= $report['seo_plugin'] ? 'Plugin SEO: ' . $h($report['seo_plugin']) . '.' : '' ?></p>
    <div class="stats-grid" style="margin:1rem 0 0">
      <div class="stat-card stat-card-accent"><div class="stat-card-value"><?= (int)$v['articoli']['numero'] ?></div><div class="stat-card-label">Articoli</div></div>
      <div class="stat-card stat-card-sage"><div class="stat-card-value"><?= (int)$v['pagine']['numero'] ?></div><div class="stat-card-label">Pagine</div></div>
      <div class="stat-card stat-card-mustard"><div class="stat-card-value"><?= (int)$v['media']['numero'] ?></div><div class="stat-card-label">Media</div></div>
      <div class="stat-card"><div class="stat-card-value"><?= (int)$v['categorie']['numero'] ?> / <?= (int)$v['tag']['numero'] ?></div><div class="stat-card-label">Categorie / tag</div></div>
      <div class="stat-card"><div class="stat-card-value"><?= (int)$v['menu']['numero'] ?></div><div class="stat-card-label">Menu</div></div>
    </div>
  </div>
  <form method="post" class="iw-card" id="iw-scelta">
    <?= csrf_field() ?><input type="hidden" name="azione" value="scelta">
    <h2>Scegli cosa importare</h2>
    <p class="iw-nota" style="margin-top:0">Per ogni voce: quanti sono, cosa diventano in plumeCMS. Togli la spunta a quello che non vuoi.</p>
    <div class="iw-scorciatoie">
      <button class="btn btn-secondary btn-sm" type="button" data-iw-tutto="1">Tutto</button>
      <button class="btn btn-secondary btn-sm" type="button" data-iw-solo="articoli,categorie,tag,seo,redirect">Solo gli articoli</button>
      <button class="btn btn-secondary btn-sm" type="button" data-iw-solo="pagine,seo,redirect">Solo le pagine</button>
      <button class="btn btn-secondary btn-sm" type="button" data-iw-tutto="0">Niente</button>
    </div>
    <div class="iw-voci">
      <?php foreach ($v as $k => $x): ?>
      <div class="iw-voce">
        <label class="iw-testa"><input type="checkbox" name="voce[<?= $h($k) ?>]" value="1" data-iw-voce="<?= $h($k) ?>" <?= ($x['numero'] ?? 0) > 0 ? 'checked' : 'disabled' ?>> <?= $h($x['nome']) ?> <span class="iw-numero"><?= (int)$x['numero'] ?></span></label>
        <p><?= $h($x['dettaglio']) ?></p>
        <p class="iw-diventa"><b>Diventa:</b> <?= $h($x['diventa']) ?></p>
        <?php if ($k === 'articoli' && $x['numero']): ?>
        <div class="iw-stati">
          <label><input type="checkbox" name="stati[]" value="publish" checked> pubblicati (<?= (int)$x['stati']['publish'] ?>)</label>
          <label><input type="checkbox" name="stati[]" value="draft" checked> bozze (<?= (int)$x['stati']['draft'] ?>)</label>
          <label><input type="checkbox" name="stati[]" value="future" checked> programmati (<?= (int)$x['stati']['future'] ?>)</label>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($report['conflitti']): ?>
    <h3>Contenuti che non entrano perché l'indirizzo è già usato (<?= count($report['conflitti']) ?>)</h3>
    <p class="iw-nota" style="margin-top:0">Per sicurezza niente di quello che c'è già in plumeCMS viene toccato: questi si saltano. I link e le voci di menu che puntavano a questi porteranno a quello che c'è già in plumeCMS con lo stesso indirizzo. Se ti servono, cambia l'indirizzo in WordPress (o in plumeCMS) e rifai l'import.</p>
    <table class="iw-tab"><tr><th>Contenuto</th><th>Indirizzo</th><th>Perché</th></tr>
      <?php foreach ($report['conflitti'] as $c): ?><tr><td><?= $h(ucfirst($c['tipo'])) ?>: <?= $h($c['titolo']) ?></td><td>/<?= $h($c['slug']) ?>/</td><td><?= $h($c['motivo']) ?></td></tr><?php endforeach; ?>
    </table>
    <?php endif; ?>

    <h3>Cosa non si può importare</h3>
    <ul class="iw-elenco"><?php foreach ($report['non_importabili'] as $n): ?><li><?= $h($n) ?></li><?php endforeach; ?></ul>

    <div class="iw-avviso" style="margin:1.2rem 0 0">Prima di cominciare faccio una <b>copia del sito</b> (la trovi in Impostazioni &gt; Copia del sito). Poi tutto entra <b>in prova</b>: i visitatori non vedono niente, tu lo guardi nell'anteprima e alla fine scegli Conferma o Annulla.</div>
    <div class="iw-azioni">
      <button class="btn btn-primary" type="submit">Importa le voci scelte</button>
    </div>
  </form>
  <form method="post" data-conferma="Ricomincio da capo? Il report si perde (niente è stato importato)."><?= csrf_field() ?><input type="hidden" name="azione" value="abbandona"><button class="btn btn-secondary btn-sm" type="submit">Ricomincia con un altro sito</button></form>
  <script<?= nonce_attr() ?>>
  (function () {
    var voci = document.querySelectorAll('[data-iw-voce]:not([disabled])');
    document.querySelectorAll('[data-iw-tutto]').forEach(function (b) { b.addEventListener('click', function () { voci.forEach(function (c) { c.checked = b.dataset.iwTutto === '1'; }); }); });
    document.querySelectorAll('[data-iw-solo]').forEach(function (b) { b.addEventListener('click', function () { var ok = b.dataset.iwSolo.split(','); voci.forEach(function (c) { c.checked = ok.indexOf(c.dataset.iwVoce) >= 0; }); }); });
  })();
  </script>

<?php elseif ($fase === 'prova'): $conti = importa_wp_conteggi((string)$s['lavoro']); $e = $s['esito']; $contenuti = array_merge(importa_wp_voci((string)$s['lavoro'], 'pagina'), importa_wp_voci((string)$s['lavoro'], 'articolo')); $menuImp = importa_wp_voci((string)$s['lavoro'], 'menu'); ?>
  <div class="iw-avviso"><b>L'import è in prova.</b> Tutto quello che è entrato è invisibile ai visitatori: lo vedi solo tu, nell'anteprima. Controlla, poi premi <b>Conferma l'import</b> (diventa tutto definitivo, come era su WordPress) oppure <b>Annulla l'import</b> (si toglie tutto e il sito torna com'era).</div>
  <div class="iw-card">
    <h2>Riepilogo: cosa è entrato da <?= $h($s['sito']['nome'] ?? '') ?></h2>
    <div class="stats-grid" style="margin:.8rem 0 0">
      <div class="stat-card stat-card-accent"><div class="stat-card-value"><?= (int)($conti['articolo']['tot'] ?? 0) ?></div><div class="stat-card-label">Articoli</div></div>
      <div class="stat-card stat-card-sage"><div class="stat-card-value"><?= (int)($conti['pagina']['tot'] ?? 0) ?></div><div class="stat-card-label">Pagine</div></div>
      <div class="stat-card stat-card-mustard"><div class="stat-card-value"><?= (int)($conti['media']['tot'] ?? 0) ?></div><div class="stat-card-label">File nei media</div></div>
      <div class="stat-card"><div class="stat-card-value"><?= (int)($conti['categoria']['tot'] ?? 0) ?> / <?= (int)($conti['tag']['tot'] ?? 0) ?></div><div class="stat-card-label">Categorie / tag nuovi</div></div>
      <div class="stat-card"><div class="stat-card-value"><?= (int)($conti['menu']['tot'] ?? 0) ?></div><div class="stat-card-label">Menu</div></div>
      <div class="stat-card"><div class="stat-card-value"><?= (int)($conti['redirect']['tot'] ?? 0) ?></div><div class="stat-card-label">Redirect</div></div>
    </div>
    <p class="iw-nota">Saltati: <b><?= count($e['saltati']) ?></b>. Copia del sito fatta prima di cominciare: <?= $h($s['copia'] ?? 'nessuna') ?>. Tempo: <?= (int)(($e['fine'] ?? time()) - ($e['inizio'] ?? time())) ?> secondi.</p>
    <div class="iw-azioni"><a class="btn btn-primary" href="<?= $h($sito) ?>/?preview=1" target="_blank" rel="noopener" id="iw-anteprima">Apri l'anteprima del sito</a><a class="btn btn-secondary" href="contenuti.php?tipo=articoli&amp;stato=bozza">Vedi gli articoli nel pannello</a></div>
  </div>

  <div class="iw-card">
    <h2>Contenuti entrati (<?= count($contenuti) ?>)</h2>
    <p class="iw-nota" style="margin-top:0">Ognuno si apre nell'anteprima. Ora sono tutti bozze; con la conferma tornano allo stato che avevano su WordPress.</p>
    <table class="iw-tab"><tr><th>Contenuto</th><th>Indirizzo nuovo</th><th>Con la conferma</th><th></th></tr>
      <?php foreach ($contenuti as $c): ?>
      <tr><td><?= $c['genere'] === 'pagina' ? 'Pagina' : 'Articolo' ?>: <?= $h(importa_wp_titolo_indice($s, $c['genere'] === 'pagina' ? 'pagine' : 'articoli', (int)$c['wp_id'])) ?></td>
        <td>/<?= $h($c['plume_rif']) ?>/<?= $c['wp_rif'] !== '/' . $c['plume_rif'] . '/' && !str_starts_with($c['wp_rif'], '/?') ? '<br><span class="iw-nota">prima: ' . $h(rawurldecode($c['wp_rif'])) . '</span>' : '' ?></td>
        <td><span class="badge <?= ['pubblicato' => 'badge-published', 'programmato' => 'badge-scheduled', 'bozza' => 'badge-draft'][$c['stato_finale']] ?? 'badge-draft' ?>"><?= $h($c['stato_finale']) ?></span></td>
        <td><a href="<?= $h($sito) ?>/<?= $h($c['plume_rif']) ?>/?preview=1" target="_blank" rel="noopener">Anteprima</a></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($e['saltati']): ?>
  <div class="iw-card">
    <h2>Cosa non è entrato (<?= count($e['saltati']) ?>)</h2>
    <table class="iw-tab"><tr><th>Cosa</th><th>Nome</th><th>Perché</th></tr>
      <?php foreach ($e['saltati'] as $x): ?><tr><td><?= $h($x['cosa']) ?></td><td><?= $h($x['titolo']) ?></td><td><?= $h($x['perche']) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <?php if (!empty($e['avvisi']['contenuti'])): ?>
  <div class="iw-card">
    <h2>Da guardare (<?= count($e['avvisi']['contenuti']) ?> contenuti)</h2>
    <p class="iw-nota" style="margin-top:0">Sono entrati, ma qualcosa del testo originale non si è potuto portare.</p>
    <table class="iw-tab"><tr><th>Contenuto</th><th>Cosa manca</th></tr>
      <?php foreach ($e['avvisi']['contenuti'] as $x): ?><tr><td><a href="<?= $h($sito) ?>/<?= $h($x['slug']) ?>/?preview=1" target="_blank" rel="noopener"><?= $h($x['titolo']) ?></a></td><td><?= $h(implode('; ', $x['note'])) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <div class="iw-griglia">
    <form method="post" class="iw-card" data-conferma="Confermo l'import? I contenuti diventano visibili come erano su WordPress e i redirect si accendono.">
      <?= csrf_field() ?><input type="hidden" name="azione" value="conferma">
      <h2>Conferma l'import</h2>
      <p class="iw-nota" style="margin-top:0">Pubblicati pubblicati, bozze bozze, programmati programmati; redirect dai vecchi indirizzi attivi; categorie e tag visibili.</p>
      <?php if ($menuImp): $pos = array_filter($menuImp, fn($m) => $m['wp_rif'] !== ''); ?>
      <label class="iw-spunta"><input type="checkbox" name="menu_principale" value="1" <?= $pos ? 'checked' : '' ?>> <span>Metti nel menu principale del sito (quello dell'header) le voci del menu
        <select name="menu_id"><?php foreach ($menuImp as $m): ?><option value="<?= (int)$m['plume_id'] ?>" <?= $m['wp_rif'] !== '' ? 'selected' : '' ?>><?= $h($m['plume_rif']) ?></option><?php endforeach; ?></select>
        al posto di quelle di adesso. Il menu di prima resta in Editor &gt; Versioni.</span></label>
      <?php endif; ?>
      <div class="iw-azioni"><button class="btn btn-primary" type="submit">Conferma l'import</button></div>
    </form>
    <form method="post" class="iw-card" data-conferma="Annullo l'import? Tolgo tutto quello che è entrato: contenuti, file, categorie, tag, menu e redirect.">
      <?= csrf_field() ?><input type="hidden" name="azione" value="annulla">
      <h2>Annulla l'import</h2>
      <p class="iw-nota" style="margin-top:0">Si toglie tutto quello che è entrato (contenuti, file, categorie e tag nuovi, menu, redirect). Quello che c'era prima resta com'era.</p>
      <div class="iw-azioni"><button class="btn btn-danger" type="submit">Annulla l'import</button></div>
    </form>
  </div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
