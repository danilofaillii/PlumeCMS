<?php
/**
 * 2.14.0, fase 7 del piano Brevo: il report di una campagna, a schede (Panoramica, Aperture, Clic, Disiscrizioni,
 * Rimbalzi). Numeri e tassi sulle consegnate, aperture e clic unici e totali, mappa dei clic sull'anteprima, elenchi dei
 * contatti con il CSV e "Salva come lista" o "come segmento", soglie, risultati del test A/B.
 * Motore in includes/newsletter-report.php. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-campagne.php';
require_once __DIR__ . '/../includes/newsletter-segmenti.php';
require_once __DIR__ . '/../includes/newsletter-report.php';
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_campagne_schema();
nl_rep_schema();
if (function_exists('newsletter_segmenti_schema')) newsletter_segmenti_schema();

$idGrezzo = $_GET['id'] ?? $_POST['id'] ?? '';
$id = is_string($idGrezzo) && ctype_digit($idGrezzo) ? (int)$idGrezzo : 0; // un id storto (array, testo, negativo) non apre niente
$c = $id > 0 ? newsletter_campagna($id) : null;
if (!$c || !in_array($c['stato'], NL_REP_STATI, true)) {
    flash('error', $c ? 'Questa campagna non è ancora partita: il report c\'è dopo l\'invio.' : 'La campagna non esiste (forse è stata eliminata).');
    header('Location: ' . ($c ? 'newsletter-campagna.php?id=' . $id : 'newsletter.php'));
    exit;
}
$s = fn(string $k) => isset($_GET[$k]) && is_string($_GET[$k]) ? $_GET[$k] : '';
$scheda = isset(NL_REP_SCHEDE[$s('scheda')]) ? $s('scheda') : 'panoramica';
$link = nl_rep_link($id);
$urlValidi = array_column($link, 'url');
// Il link scelto per l'elenco dei clic: solo uno dei link della campagna.
$url = in_array($s('url'), $urlValidi, true) ? $s('url') : '';
$elenchiScheda = array_keys(array_filter(NL_REP_ELENCHI, fn($e) => $e[1] === $scheda));
$elenco = in_array($s('elenco'), $elenchiScheda, true) ? $s('elenco') : ($elenchiScheda[0] ?? '');

// Esportazione CSV: il report intero o un elenco (con il link scelto).
$csv = $s('csv');
if ($csv !== '') {
    if ($csv !== 'report' && !isset(NL_REP_ELENCHI[$csv])) { flash('error', 'Questo elenco non esiste.'); header('Location: newsletter-report.php?id=' . $id); exit; }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report-campagna-' . $id . '-' . ($csv === 'report' ? 'numeri' : $csv) . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $f = fopen('php://output', 'wb');
    nl_rep_csv($f, $id, $csv, $csv === 'cliccato' ? $url : '');
    fclose($f);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $tipo = is_string($_POST['elenco'] ?? null) ? $_POST['elenco'] : '';
    $u = is_string($_POST['url'] ?? null) && in_array($_POST['url'], $urlValidi, true) && $tipo === 'cliccato' ? $_POST['url'] : '';
    $nome = is_string($_POST['nome'] ?? null) ? trim(strip_tags($_POST['nome'])) : '';
    $torna = 'newsletter-report.php?id=' . $id . '&scheda=' . (NL_REP_ELENCHI[$tipo][1] ?? 'panoramica') . '&elenco=' . rawurlencode($tipo) . ($u !== '' ? '&url=' . rawurlencode($u) : '');
    if (!isset(NL_REP_ELENCHI[$tipo])) { flash('error', 'Questo elenco non esiste.'); header('Location: newsletter-report.php?id=' . $id); exit; }
    if (($_POST['azione'] ?? '') === 'lista') {
        $r = nl_rep_in_lista($id, $tipo, $u, $nome);
        if (is_string($r)) flash('error', $r);
        else flash('success', 'Lista «' . newsletter_testo_pulito($nome, 100) . '» creata con ' . ($r[1] === 1 ? '1 contatto' : $r[1] . ' contatti') . '. La trovi in Contatti, scheda Liste. Le automazioni della lista non partono per loro.');
    } elseif (($_POST['azione'] ?? '') === 'segmento') {
        $r = nl_rep_segmento($id, $tipo, $u, $nome);
        if (is_string($r)) flash('error', $r);
        else flash('success', 'Segmento «' . newsletter_testo_pulito($nome, 100) . '» creato: si aggiorna da solo. Lo trovi in Contatti, scheda Segmenti.');
    }
    header('Location: ' . $torna);
    exit;
}

$n = nl_rep_numeri($id);
$nomeC = newsletter_4byte_fuori((string)($c['nome'] !== '' ? $c['nome'] : $c['oggetto'])) ?: 'Campagna senza nome';
$isAb = ($c['tipo'] ?? '') === 'ab';
$ab = $isAb && function_exists('nl_ab') ? nl_ab($id) : null;
$adminTitle = 'Report: ' . $nomeC;
$base = 'newsletter-report.php?id=' . $id;
$pag = max(1, min(100000, (int)$s('pag')));
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/report.css') ?>">
<style>.nlr-stato{display:inline-flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:700;padding:.1rem .55rem;border-radius:999px;border:1px solid currentColor}.nlr-ok{color:var(--successo-testo);background:var(--successo-chiaro)}</style>
<p><a href="newsletter.php">&larr; Tutte le campagne</a> · <a href="newsletter-campagna.php?id=<?= $id ?>">Apri la campagna</a> · <a href="newsletter-statistiche.php">Statistiche di un periodo</a></p>

<div class="form-card" id="nlr-testata">
  <h3><?= h($nomeC) ?></h3>
  <dl class="nlr-dettagli">
    <div><dt>Oggetto</dt><dd><?= h(newsletter_4byte_fuori((string)$c['oggetto'])) ?></dd></div>
    <div><dt>Stato</dt><dd><?= h(NL_CAMP_STATI[$c['stato']] ?? $c['stato']) ?><?= $isAb ? ' · Test A/B' : '' ?><?= (int)($c['orario_migliore'] ?? 0) ? ' · all\'orario migliore' : '' ?></dd></div>
    <div><dt>Partita il</dt><dd><?= $c['invio_at'] ? h(date('d/m/Y H:i', strtotime((string)$c['invio_at']))) : '' ?></dd></div>
    <div><dt>Destinatari</dt><dd><?= (int)$c['destinatari'] ?></dd></div>
  </dl>
  <p class="nl-riga" style="margin:.4rem 0 0"><a class="btn btn-secondary btn-sm" href="<?= h($base) ?>&amp;csv=report">Esporta il report (CSV)</a></p>
</div>

<nav class="imp-schede" aria-label="Schede del report">
  <?php foreach (NL_REP_SCHEDE as $k => $v): ?><a href="<?= h($base) ?><?= $k !== 'panoramica' ? '&amp;scheda=' . $k : '' ?>"<?= $scheda === $k ? ' class="attiva" aria-current="page"' : '' ?>><?= h($v) ?></a><?php endforeach; ?>
</nav>

<?php if ($scheda === 'panoramica'): ?>
<div class="form-card" id="nlr-panoramica">
  <h3>I numeri della campagna</h3>
  <?= nl_rep_soglie_html($n) ?>
  <?= nl_rep_tessere($n) ?>
  <details class="pn-aiuto"><summary>Come si contano questi numeri</summary>
    <p><b>Consegnate</b>: le email partite meno quelle che il servizio di posta ha poi rifiutato (rimbalzi arrivati dopo l'invio). I tassi di apertura, di clic, di disiscrizione e di spam si calcolano sulle consegnate; i rimbalzi sulle email provate (partite più quelle rifiutate subito).</p>
    <p><b>Uniche</b>: una persona conta una volta, anche se apre o clicca più volte. <b>Totali</b>: ogni apertura e ogni clic. Un clic vale anche come apertura. <b>Clic sulle aperture</b>: quanti di quelli che hanno aperto hanno anche cliccato.</p>
    <p>Chi legge con le immagini bloccate non risulta fra le aperture, e alcuni programmi di posta aprono le email da soli: i numeri sono indicativi. Le prove non contano mai.</p>
    <p>Le soglie: rimbalzi definitivi oltre il 2%, disiscrizioni oltre l'1%, segnalazioni di spam oltre lo 0,2%. Sopra queste soglie il numero è segnato con «!» e il riquadro in alto dice cosa fare.</p>
  </details>
</div>
<?php if ($ab && $ab['fase'] !== '') echo nl_rep_ab_html($c, $ab); ?>
<?php endif; ?>

<?php if ($scheda === 'aperture'): ?>
<div class="form-card"><h3>Aperture</h3>
  <div class="nlr-numeri"><?= nl_rep_tessera('apertura', 'Aperture uniche', $n['apertura'], nl_rep_pct_testo($n['t_apertura']) . ' delle consegnate') . nl_rep_tessera('apertura_tot', 'Aperture totali', $n['apertura_tot']) . nl_rep_tessera('non_aperto', 'Non hanno aperto', nl_rep_elenco($id, 'non_aperto', '', 1, 1)['n']) ?></div>
</div>
<?php endif; ?>

<?php if ($scheda === 'clic'): ?>
<div class="form-card" id="nlr-link"><h3>Clic e link</h3>
  <div class="nlr-numeri"><?= nl_rep_tessera('clic', 'Clic unici', $n['clic'], nl_rep_pct_testo($n['t_clic']) . ' delle consegnate') . nl_rep_tessera('clic_tot', 'Clic totali', $n['clic_tot']) . nl_rep_tessera('clic_su_aperture', 'Clic sulle aperture', nl_rep_pct_testo($n['t_ctor'])) ?></div>
  <?php if (!$link): ?><div class="empty-state"><p>Ancora nessun clic. Quando qualcuno clicca un link dell'email, lo vedi qui con i suoi numeri.</p></div><?php else: ?>
  <div class="nlr-scorre"><table class="table" id="nlr-link-tabella">
    <thead><tr><th>Link</th><th>Clic unici</th><th>Clic totali</th><th>Quota dei clic</th><th>Chi l'ha cliccato</th></tr></thead>
    <tbody><?php foreach ($link as $l): ?><tr data-url="<?= h($l['url']) ?>"><td class="nlr-url"><?= h($l['url']) ?></td><td><?= $l['unici'] ?></td><td><?= $l['totali'] ?></td><td><?= h(nl_rep_pct_testo($l['quota'])) ?></td>
      <td><a href="<?= h($base) ?>&amp;scheda=clic&amp;elenco=cliccato&amp;url=<?= h(rawurlencode($l['url'])) ?>#nlr-elenco">Elenco<span class="sr-solo"> di chi ha cliccato <?= h($l['url']) ?></span></a></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<div class="form-card nlr-mappa" id="nlr-mappa"><h3>Mappa dei clic</h3>
  <p class="hint">L'email come la vede chi la riceve (con il nome «Giulia»): accanto a ogni link il numero dei clic, delle persone e la sua quota sul totale dei clic. I link con dei clic hanno il bordo rosso.<?= $isAb && $ab && $ab['cosa'] === 'design' ? ' Qui vedi la versione A.' : '' ?></p>
  <iframe title="Mappa dei clic dell'email" sandbox srcdoc="<?= h(nl_rep_mappa($c)) ?>"></iframe>
</div>
<?php endif; ?>

<?php if ($scheda === 'uscite'): $mot = newsletter_uscite_motivi_conta($id); ?>
<div class="form-card" id="nlr-motivi"><h3>Disiscrizioni e spam</h3>
  <div class="nlr-numeri"><?= nl_rep_tessera('uscita', 'Disiscrizioni', $n['uscita'], nl_rep_pct_testo($n['t_uscita']) . ' delle consegnate', isset($n['soglie']['uscite'])) . nl_rep_tessera('spam', 'Segnalazioni di spam', $n['spam'], nl_rep_pct_testo($n['t_spam']) . ' delle consegnate', isset($n['soglie']['spam'])) ?></div>
  <?= nl_rep_soglie_html($n) ?>
  <table class="table"><thead><tr><th>Perché sono usciti</th><th>Risposte</th><th>Sul totale</th></tr></thead><tbody>
  <?php if (!$mot['totale']): ?><tr><td colspan="3">Nessuna risposta al questionario per questa campagna. Chi esce dal link dell'email può dire perché nella pagina di uscita.</td></tr><?php endif; ?>
  <?php if ($mot['totale']) foreach ($mot['n'] as $k => $v): ?><tr data-motivo="<?= h($k) ?>"><td><?= h(NL_USCITA_MOTIVI[$k]) ?></td><td><b><?= (int)$v ?></b></td><td><?= round($v * 100 / $mot['totale']) ?>%</td></tr><?php endforeach; ?>
  </tbody></table>
  <?php if ($mot['altri']): ?><p style="margin:.6rem 0 .2rem"><b>Le risposte «Altro»</b></p><ul style="margin:0;padding-left:1.2rem"><?php foreach ($mot['altri'] as $a): ?><li><?= h(newsletter_4byte_fuori((string)$a['altro'])) ?></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($scheda === 'rimbalzi'): ?>
<div class="form-card"><h3>Rimbalzi</h3>
  <div class="nlr-numeri"><?= nl_rep_tessera('rimbalzo', 'Rimbalzi definitivi', $n['rimbalzo'], nl_rep_pct_testo($n['t_rimbalzo']) . ' delle provate', isset($n['soglie']['rimbalzi'])) . nl_rep_tessera('soft', 'Rimbalzi temporanei', $n['soft'], nl_rep_pct_testo($n['t_soft']) . ' delle provate') . nl_rep_tessera('provate', 'Email provate', $n['provate']) ?></div>
  <?= nl_rep_soglie_html($n) ?>
  <p class="hint">Definitivo: l'indirizzo non esiste o rifiuta per sempre, e il contatto va in lista nera. Temporaneo: casella piena o server occupato; dopo cinque di fila il contatto va in lista nera.</p>
</div>
<?php endif; ?>

<?php if ($elenco !== ''): $e = nl_rep_elenco($id, $elenco, $elenco === 'cliccato' ? $url : '', $pag); $pagine = max(1, (int)ceil($e['n'] / NL_REP_PER_PAGINA));
  $qsEl = '&amp;scheda=' . $scheda . '&amp;elenco=' . $elenco . ($elenco === 'cliccato' && $url !== '' ? '&amp;url=' . h(rawurlencode($url)) : ''); ?>
<div class="form-card" id="nlr-elenco">
  <h3>Elenco: <?= h(NL_REP_ELENCHI[$elenco][0]) ?></h3>
  <?php if (count($elenchiScheda) > 1): ?><nav class="nlr-elenchi" aria-label="Elenchi di questa scheda"><?php foreach ($elenchiScheda as $k): ?><a class="btn btn-secondary btn-sm" href="<?= h($base) ?>&amp;scheda=<?= $scheda ?>&amp;elenco=<?= $k ?>#nlr-elenco"<?= $k === $elenco ? ' aria-current="page"' : '' ?>><?= h(NL_REP_ELENCHI[$k][0]) ?></a><?php endforeach; ?></nav><?php endif; ?>
  <?php if ($elenco === 'cliccato' && $link): ?>
  <form method="get" class="nl-riga" style="gap:.5rem;align-items:flex-end;margin:0 0 .8rem"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="scheda" value="clic"><input type="hidden" name="elenco" value="cliccato">
    <div class="form-group" style="margin:0;flex:1;min-width:min(100%,16rem)"><label for="nlr-url">Solo chi ha cliccato questo link</label>
      <select id="nlr-url" name="url"><option value="">Qualsiasi link</option><?php foreach ($link as $l): ?><option value="<?= h($l['url']) ?>"<?= $l['url'] === $url ? ' selected' : '' ?>><?= h(mb_strimwidth($l['url'], 0, 90, '...')) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-secondary btn-sm" type="submit">Mostra l'elenco</button>
  </form>
  <?php endif; ?>
  <p data-elenco-n="<?= (int)$e['n'] ?>"><b><?= (int)$e['n'] ?></b> <?= (int)$e['n'] === 1 ? 'contatto' : 'contatti' ?><?= $elenco === 'cliccato' && $url !== '' ? ' per <span class="nlr-url">' . h($url) . '</span>' : '' ?>.</p>
  <?php if (!$e['n']): ?><div class="empty-state"><p>Nessun contatto in questo elenco, per ora. I numeri si aggiornano da soli quando arrivano aperture, clic, uscite o rimbalzi.</p></div><?php else: ?>
  <div class="nlr-scorre"><table class="table nlr-elenco-tabella">
    <thead><tr><th>Contatto</th><th>Nome</th><th>Quando</th><?= in_array($elenco, ['aperto', 'cliccato'], true) ? '<th>Volte</th>' : '' ?><?= in_array($elenco, ['uscito', 'cliccato', 'rimbalzo', 'soft'], true) ? '<th>' . ($elenco === 'uscito' ? 'Motivo' : ($elenco === 'cliccato' ? 'Link diversi' : 'Errore')) . '</th>' : '' ?></tr></thead>
    <tbody><?php foreach ($e['righe'] as $r): ?><tr>
      <td class="nlr-url"><a href="newsletter-contatto.php?id=<?= (int)$r['id'] ?>"><?= h($r['email']) ?></a></td>
      <td><?= h(trim(newsletter_4byte_fuori($r['nome'] . ' ' . $r['cognome']))) ?></td>
      <td><?= $r['quando'] ? h(date('d/m/Y H:i', strtotime((string)$r['quando']))) : '' ?></td>
      <?= in_array($elenco, ['aperto', 'cliccato'], true) ? '<td>' . (int)$r['volte'] . '</td>' : '' ?>
      <?= in_array($elenco, ['uscito', 'cliccato', 'rimbalzo', 'soft'], true) ? '<td>' . h(nl_rep_dettaglio($elenco, $r)) . '</td>' : '' ?>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php if ($pagine > 1): ?><nav class="nlr-pagine" aria-label="Pagine dell'elenco">
    <?php if ($pag > 1): ?><a class="btn btn-secondary btn-sm" href="<?= h($base) ?><?= $qsEl ?>&amp;pag=<?= $pag - 1 ?>#nlr-elenco">Pagina prima</a><?php endif; ?>
    <span>Pagina <?= $pag ?> di <?= $pagine ?></span>
    <?php if ($pag < $pagine): ?><a class="btn btn-secondary btn-sm" href="<?= h($base) ?><?= $qsEl ?>&amp;pag=<?= $pag + 1 ?>#nlr-elenco">Pagina dopo</a><?php endif; ?>
  </nav><?php endif; ?>
  <p class="nl-riga" style="margin:.8rem 0 0"><a class="btn btn-secondary btn-sm" href="<?= h($base) ?><?= $qsEl ?>&amp;csv=<?= $elenco ?>">Esporta questo elenco (CSV)</a></p>
  <form method="post" class="nlr-salva"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="elenco" value="<?= h($elenco) ?>"><input type="hidden" name="url" value="<?= h($elenco === 'cliccato' ? $url : '') ?>">
    <div class="form-group"><label for="nlr-salva-nome">Nome della lista<?= NL_REP_ELENCHI[$elenco][2] ? ' o del segmento' : '' ?></label>
      <input type="text" id="nlr-salva-nome" name="nome" maxlength="100" required placeholder="per esempio: <?= h(NL_REP_ELENCHI[$elenco][0]) ?> di novembre" aria-describedby="nlr-salva-aiuto"></div>
    <button class="btn btn-primary btn-sm" type="submit" name="azione" value="lista">Salva come lista</button>
    <?php if (NL_REP_ELENCHI[$elenco][2]): ?><button class="btn btn-secondary btn-sm" type="submit" name="azione" value="segmento">Salva come segmento</button><?php endif; ?>
  </form>
  <p class="hint" id="nlr-salva-aiuto">La lista è una fotografia di adesso: chi c'è oggi resta, anche se l'elenco cambia. <?= NL_REP_ELENCHI[$elenco][2] ? 'Il segmento invece si aggiorna da solo con chi apre o clicca dopo.' : '' ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
