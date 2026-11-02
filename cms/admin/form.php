<?php
/**
 * Form: elenco e costruttore. 2.16.0 (fase 9 del piano Brevo, includes/form-brevo.php): il costruttore a cinque passi con
 * "Avanti", come Brevo: 1 Impostazione, 2 Aspetto e campi, 3 Messaggi, 4 Conferma, 5 Condividi. Ogni passo salva solo i
 * suoi campi (il resto del form resta com'era). I tipi: Iscrizione, Pop-up, Aggiornamento del profilo, Disiscrizione, Altri
 * form (contatti, richieste: i form di prima).
 *
 * Il form si mette in pagina con il widget Modulo dell'editor visuale, dentro un popup, oppure da Condividi: pagina
 * ospitata, codice iframe, HTML, HTML semplice, QR.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/form.php';
require_once dirname(__DIR__) . '/includes/popup.php';
require_once __DIR__ . '/includes/email-conferma-campi.php'; // 2.7.0: il riquadro Email di conferma
require_admin(); // sicurezza (ruoli): solo amministratore
$adminTitle = 'Form';
$id = (int)($_GET['id'] ?? 0);
$nuovo = isset($_GET['nuovo']);
$passo = max(1, min(5, (int)($_GET['passo'] ?? 1)));
$tipoNuovo = is_string($_GET['tipo'] ?? null) && isset(FORM_TIPI[$_GET['tipo']]) ? $_GET['tipo'] : '';

/** I dati di un form salvato nella forma che vuole form_salva() (per salvare un passo senza toccare gli altri). */
function fm_dati(array $f): array {
    $d = [];
    foreach (['nome', 'slug', 'titolo', 'intro', 'pulsante', 'consenso', 'lista_id', 'lista_conferma', 'avviso', 'avviso_a', 'omaggio', 'grazie_titolo', 'grazie_testo', 'grazie_link', 'attivo',
        'tipo', 'conferma', 'liste', 'doi_modello_id', 'doi_url', 'aggiorna_esistenti', 'gdpr', 'gdpr_testo', 'dopo_invio', 'dopo_url'] as $k) $d[$k] = $f[$k] ?? '';
    $d['campi'] = $f['campi'];
    $d['messaggi'] = fb_messaggi($f);
    return $d;
}

/** I campi di partenza di un form nuovo, per tipo. */
function fm_campi_partenza(string $tipo): array {
    $c = fn($nome, $et, $tipoC, $obb, $attr = '', $meta = '') => ['nome' => $nome, 'etichetta' => $et, 'tipo' => $tipoC, 'obbligatorio' => $obb, 'segnaposto' => '', 'opzioni' => [], 'meta' => $meta, 'attributo' => $attr];
    return match ($tipo) {
        'iscrizione', 'popup' => [$c('email', 'Email', 'email', true, 'EMAIL', 'email'), $c('nome', 'Nome', 'testo', false, 'NOME', 'nome')],
        'profilo' => [$c('email', 'Email', 'email', true, 'EMAIL', 'email'), $c('nome', 'Nome', 'testo', false, 'NOME', 'nome'), $c('cognome', 'Cognome', 'testo', false, 'COGNOME', 'cognome')],
        'disiscrizione' => [$c('email', 'Email', 'email', true, 'EMAIL', 'email')],
        default => [$c('nome', 'Nome', 'testo', true, 'NOME', 'nome'), $c('email', 'Email', 'email', true, 'EMAIL', 'email'), $c('messaggio', 'Messaggio', 'testolungo', false)],
    };
}

// Il QR della pagina ospitata, da scaricare (Condividi).
if ($id && isset($_GET['qr']) && ($fq = form_leggi($id))) {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="qr-' . $fq['slug'] . '.svg"');
    header('Cache-Control: no-store');
    echo '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' . "\n" . fb_qr_svg($fq, 10);
    exit;
}

$daPost = null; // con un errore di salvataggio il passo si ridisegna con quello che era stato scritto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $passo = max(1, min(5, (int)($_POST['passo'] ?? 1)));
    $azione = is_string($_POST['azione'] ?? null) ? $_POST['azione'] : '';
    $fmS = fn($x) => is_scalar($x) ? (string)$x : ''; // un campo arrivato come elenco (POST manipolato) vale vuoto
    try {
        if ($azione === 'elimina') { form_elimina($id); flash('success', 'Form eliminato, con le sue compilazioni.'); header('Location: form.php'); exit; }
        $f = $id ? form_leggi($id) : null;
        if ($id && !$f) throw new RuntimeException('Il form non c\'è più.');
        if (!$f) {
            // Passo 1 di un form nuovo: Crea il form, con i campi di partenza del suo tipo.
            $tipo = is_string($_POST['tipo'] ?? null) && isset(FORM_TIPI[$_POST['tipo']]) ? $_POST['tipo'] : 'generico';
            $d = ['nome' => $fmS($_POST['nome'] ?? ''), 'slug' => $fmS($_POST['slug'] ?? ''), 'attivo' => !empty($_POST['attivo']), 'tipo' => $tipo, 'campi' => fm_campi_partenza($tipo),
                'pulsante' => in_array($tipo, ['iscrizione', 'popup'], true) ? 'Iscriviti' : ($tipo === 'profilo' ? 'Salva i miei dati' : ($tipo === 'disiscrizione' ? 'Mandami il link per uscire' : 'Invia')),
                'gdpr' => !empty($_POST['gdpr']) && !in_array($tipo, ['profilo', 'disiscrizione'], true), 'gdpr_testo' => '',
                'lista_id' => in_array($tipo, ['iscrizione', 'popup'], true) ? newsletter_lista_principale() : 0, 'conferma' => in_array($tipo, ['iscrizione', 'popup'], true) ? 'doppia' : 'nessuna',
                'avviso' => $tipo === 'generico', 'dopo_invio' => in_array($tipo, ['generico'], true) ? 'pagina' : 'messaggio',
                'grazie_titolo' => 'Grazie, ricevuto.', 'grazie_testo' => 'Ti rispondo appena posso.',
                'messaggi' => ['riuscita' => match ($tipo) { 'iscrizione', 'popup' => 'Ci sei quasi: ti abbiamo mandato un\'email. Apri il link per confermare l\'iscrizione.', 'profilo' => 'Fatto: i tuoi dati sono aggiornati.', 'disiscrizione' => 'Se l\'indirizzo è nella newsletter, ti abbiamo mandato un\'email con il link per uscire.', default => fb_messaggi_partenza()['riuscita'] }]];
            $id = form_salva(0, $d);
            flash('success', 'Form creato. Adesso scegli i campi e come appare.');
            header('Location: form.php?id=' . $id . '&passo=2');
            exit;
        }
        $d = fm_dati($f);
        $p = $_POST;
        switch ($passo) {
            case 1:
                foreach (['nome', 'slug'] as $k) $d[$k] = $fmS($p[$k] ?? '');
                if (is_string($p['tipo'] ?? null) && isset(FORM_TIPI[$p['tipo']])) $d['tipo'] = $p['tipo'];
                $d['attivo'] = !empty($p['attivo']);
                $d['gdpr'] = !empty($p['gdpr']);
                break;
            case 2:
                foreach (['titolo', 'intro', 'pulsante', 'consenso', 'gdpr_testo'] as $k) if (array_key_exists($k, $p)) $d[$k] = $fmS($p[$k]);
                $c = json_decode($fmS($p['campi'] ?? ''), true);
                if (is_array($c)) $d['campi'] = $c;
                break;
            case 3:
                foreach (['dopo_invio', 'dopo_url', 'grazie_titolo', 'grazie_testo', 'grazie_link', 'avviso_a'] as $k) if (array_key_exists($k, $p)) $d[$k] = $fmS($p[$k]);
                $d['avviso'] = !empty($p['avviso']);
                $d['messaggi'] = array_merge($d['messaggi'], array_intersect_key(is_array($p['messaggi'] ?? null) ? $p['messaggi'] : [], array_flip(['riuscita', 'errore', 'obbligatorio', 'email'])));
                break;
            case 4:
                foreach (['lista_id', 'conferma', 'doi_modello_id', 'doi_url', 'omaggio'] as $k) if (array_key_exists($k, $p)) $d[$k] = $fmS($p[$k]);
                $d['liste'] = is_array($p['liste'] ?? null) ? $p['liste'] : [];
                $d['aggiorna_esistenti'] = !empty($p['aggiorna_esistenti']);
                $d['messaggi'] = array_merge($d['messaggi'], array_intersect_key(is_array($p['messaggi'] ?? null) ? $p['messaggi'] : [], array_flip(['benvenuto_oggetto', 'benvenuto_testo'])));
                if (array_key_exists('ce', $p)) $d['ce'] = $p['ce']; // 2.7.0: le parti dell'email di conferma
                break;
            case 5:
                if ($f['tipo'] === 'popup' && ($azione === 'popup')) fb_popup_salva($f, is_array($p['pp'] ?? null) ? $p['pp'] : []);
                if ($f['tipo'] === 'profilo' && $azione === 'preferenze') save_setting('newsletter_preferenze_form', !empty($p['centro']) ? (string)$id : ((int)get_setting('newsletter_preferenze_form', '0') === $id ? '0' : (string)get_setting('newsletter_preferenze_form', '0')));
                flash('success', $azione === 'popup' ? 'Pop-up salvato.' : 'Salvato.');
                header('Location: form.php?id=' . $id . '&passo=5');
                exit;
        }
        form_salva($id, $d);
        $avanti = ($p['vai'] ?? '') === 'avanti' && $passo < 5;
        flash('success', 'Form salvato.' . ($avanti ? '' : ' Lo metti in pagina con il widget Modulo dell\'editor visuale, scegliendo «Form: ' . h((string)$d['nome']) . '», oppure da Condividi.'));
        header('Location: form.php?id=' . $id . '&passo=' . ($avanti ? $passo + 1 : $passo));
        exit;
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        $f0 = $id ? form_leggi($id) : null;
        $fmTipoPost = is_string($_POST['tipo'] ?? null) && isset(FORM_TIPI[$_POST['tipo']]) ? $_POST['tipo'] : 'generico';
        $daPost = ($f0 ?: ['id' => 0, 'campi' => fm_campi_partenza($fmTipoPost), 'tipo' => $fmTipoPost] + form_nuove_colonne([]));
        foreach ($_POST as $k => $x) if (is_string($x) && !in_array($k, ['_csrf', 'id', 'passo', 'campi', 'vai', 'azione'], true)) $daPost[$k] = $x;
        $campi = json_decode($fmS($_POST['campi'] ?? ''), true);
        if (is_array($campi)) $daPost['campi'] = array_values(array_filter($campi, 'is_array'));
        if ($passo === 4) { $daPost['liste'] = array_map('intval', is_array($_POST['liste'] ?? null) ? $_POST['liste'] : []); $daPost['aggiorna_esistenti'] = (int)!empty($_POST['aggiorna_esistenti']); }
        if ($passo === 3) $daPost['avviso'] = (int)!empty($_POST['avviso']);
        if ($passo === 1) { $daPost['attivo'] = (int)!empty($_POST['attivo']); $daPost['gdpr'] = (int)!empty($_POST['gdpr']); }
        if (is_array($_POST['messaggi'] ?? null)) $daPost['messaggi_post'] = $_POST['messaggi'];
        // 2.7.0: le parti dell'email di conferma tornano nei campi come erano state scritte.
        if (is_array($_POST['ce'] ?? null)) $daPost['conferma_email'] = array_map(fn($x) => is_scalar($x) ? (string)$x : '', array_intersect_key($_POST['ce'], NL_EMAIL_PARTI));
        $nuovo = !$id;
        if ($nuovo) $tipoNuovo = is_string($_POST['tipo'] ?? null) && isset(FORM_TIPI[$_POST['tipo']]) ? $_POST['tipo'] : 'generico';
    }
}

$liste = newsletter_liste();
$f = $daPost ?: ($id ? form_leggi($id) : null);
if ($id && !$f) { http_response_code(404); exit('Form non trovato.'); }
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<?php if (!$f && !$nuovo):
  $fmTutti = form_tutti();
  $fmFiltro = is_string($_GET['vedi'] ?? null) && isset(FORM_TIPI[$_GET['vedi']]) ? $_GET['vedi'] : '';
  $fmConta = array_count_values(array_map(fn($m) => form_nuove_colonne($m)['tipo'], $fmTutti)); ?>
<div class="ct-testa"><p style="color:var(--text-muted);font-size:.88rem;margin:0">Un form raccoglie i campi che decidi tu. Le compilazioni finiscono in <a href="form-submissions.php">Invii dei form</a>; i form della newsletter iscrivono, aggiornano o tolgono i contatti.</p><a href="form.php?nuovo=1" class="btn btn-primary">+ Nuovo form</a></div>
<nav class="ct-schede fo-viste" aria-label="Tipi di form">
  <a href="form.php" class="<?= $fmFiltro === '' ? 'attivo' : '' ?>"<?= $fmFiltro === '' ? ' aria-current="page"' : '' ?>>Tutti <span>(<?= count($fmTutti) ?>)</span></a>
  <?php foreach (FORM_TIPI as $k => $et): ?><a href="form.php?vedi=<?= $k ?>" class="<?= $fmFiltro === $k ? 'attivo' : '' ?>"<?= $fmFiltro === $k ? ' aria-current="page"' : '' ?>><?= h($et) ?> <span>(<?= (int)($fmConta[$k] ?? 0) ?>)</span></a><?php endforeach; ?>
</nav>
<div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr><th>Form</th><th>Tipo</th><th>Campi</th><th>Dopo l'invio</th><th>Compilazioni</th><th>Stato</th></tr></thead>
    <tbody>
    <?php $fmMostrati = array_filter($fmTutti, fn($m) => $fmFiltro === '' || form_nuove_colonne($m)['tipo'] === $fmFiltro); ?>
    <?php if (!$fmMostrati): ?><tr><td colspan="6"><div class="empty-state"><p><?= $fmFiltro === '' ? 'Ancora nessun form: crea il primo con «+ Nuovo form».' : 'Nessun form di questo tipo: crealo con «+ Nuovo form», oppure guarda tutti i form.' ?></p><a href="form.php?nuovo=1<?= $fmFiltro !== '' ? '&tipo=' . $fmFiltro : '' ?>" class="btn btn-primary">+ Nuovo form</a></div></td></tr><?php endif; ?>
    <?php foreach ($fmMostrati as $m): $m = form_nuove_colonne($m); $campi = json_decode((string)$m['campi'], true) ?: []; ?>
      <tr class="ct-riga">
        <td class="ct-titolo"><a class="ct-nome" href="form.php?id=<?= $m['id'] ?>"><?= h($m['nome']) ?></a><div class="ct-indirizzo">widget Modulo → «Form: <?= h($m['nome']) ?>»</div>
          <div class="ct-azioni"><a href="form.php?id=<?= $m['id'] ?>">Modifica</a><a href="form.php?id=<?= $m['id'] ?>&amp;passo=5">Condividi</a><a href="form-submissions.php?modulo=<?= $m['id'] ?>">Compilazioni</a></div></td>
        <td class="ct-muto"><?= h(FORM_TIPI[$m['tipo']]) ?></td>
        <td class="ct-muto"><?= h(implode(', ', array_map(fn($c) => $c['etichetta'] ?? '', $campi))) ?></td>
        <td class="ct-muto"><?= $m['lista_id'] ? 'entra nella lista «' . h(array_values(array_filter($liste, fn($l) => (int)$l['id'] === (int)$m['lista_id']))[0]['nome'] ?? '?') . '»' . ($m['conferma'] === 'doppia' ? ' dopo la conferma' : '') : ($m['tipo'] === 'profilo' ? 'aggiorna il contatto' : ($m['tipo'] === 'disiscrizione' ? 'manda il link per uscire' : 'nessuna lista')) ?><?= $m['avviso'] ? ', avviso a ' . h(implode(', ', form_avviso_destinatari($m)) ?: 'nessuno (manca l\'indirizzo)') : '' ?></td>
        <td class="ct-muto"><?= (int)$m['invii'] ?><?= $m['nuovi'] ? ' <span class="badge badge-scheduled">' . (int)$m['nuovi'] . ' nuove</span>' : '' ?></td>
        <td><?php if ($m['slug'] === 'richieste-prenotazione'): /* 2.1: le richieste del widget Richiesta di prenotazione */ ?><span class="badge badge-scheduled">Dal widget</span><?php else: ?><span class="badge <?= $m['attivo'] ? 'badge-published' : 'badge-draft' ?>"><?= $m['attivo'] ? 'Attivo' : 'Spento' ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php elseif (!$f && $nuovo && $tipoNuovo === ''): ?>
<div class="sc-scatola fo-tipi-scatola"><h3>Che form vuoi creare?</h3><div class="sc-corpo">
  <p class="hint" style="margin-top:0">Scegli il tipo: il costruttore ti prepara i campi giusti. Poi lo crei in cinque passi, e ogni passo si salva con «Avanti».</p>
  <ul class="fo-tipi">
    <?php foreach (FORM_TIPI as $k => $et): ?><li><a class="fo-tipo" href="form.php?nuovo=1&amp;tipo=<?= $k ?>"><b><?= h($et) ?></b><span><?= h(FORM_TIPI_SPIEGA[$k]) ?></span></a></li><?php endforeach; ?>
  </ul>
  <p><a class="btn btn-secondary" href="form.php">Torna all'elenco</a></p>
</div></div>
<?php else:
  $v = $f ?: (['id' => 0, 'nome' => '', 'slug' => '', 'titolo' => '', 'intro' => '', 'campi' => fm_campi_partenza($tipoNuovo), 'pulsante' => 'Invia', 'consenso' => '', 'lista_id' => 0, 'lista_conferma' => 1, 'avviso' => 1, 'avviso_a' => '', 'omaggio' => '', 'grazie_titolo' => 'Grazie, ricevuto.', 'grazie_testo' => 'Ti rispondo appena posso.', 'grazie_link' => '', 'attivo' => 1, 'gdpr' => 1] + form_nuove_colonne(['tipo' => $tipoNuovo]));
  $v = form_nuove_colonne($v);
  if (!$v['id']) $passo = 1;
  $fmMsg = array_merge(fb_messaggi($v), is_array($v['messaggi_post'] ?? null) ? array_map('strval', $v['messaggi_post']) : []);
  $fmIscrive = in_array($v['tipo'], ['iscrizione', 'popup', 'generico'], true);
  $fmProssimo = FORM_PASSI[$passo + 1] ?? ''; ?>
<div class="fo-testa">
  <p class="fo-sopra"><a href="form.php">Form</a> › <?= $v['id'] ? h($v['nome']) : 'Nuovo form' ?> <span class="badge badge-scheduled"><?= h(FORM_TIPI[$v['tipo']]) ?></span><?php if ($v['id'] && !$v['attivo']): ?> <span class="badge badge-draft">Spento</span><?php endif; ?></p>
  <nav class="fo-passi" aria-label="I passi del form"><ol>
    <?php foreach (FORM_PASSI as $n => $et): $fmQui = $n === $passo; ?>
      <li class="<?= $fmQui ? 'qui' : ($n < $passo ? 'fatto' : '') ?>"><?php if ($v['id'] && !$fmQui): ?><a href="form.php?id=<?= (int)$v['id'] ?>&amp;passo=<?= $n ?>"><span class="fo-num"><?= $n ?></span> <?= h($et) ?></a><?php else: ?><span<?= $fmQui ? ' aria-current="step"' : '' ?>><span class="fo-num"><?= $n ?></span> <?= h($et) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ol></nav>
</div>
<form method="post" id="fm-form" action="form.php<?= $v['id'] ? '?id=' . (int)$v['id'] . '&amp;passo=' . $passo : '?nuovo=1&amp;tipo=' . h($v['tipo']) ?>">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="passo" value="<?= $passo ?>">
<?php if ($passo === 1): ?>
  <div class="sc-scatola"><h3>1. Impostazione</h3><div class="sc-corpo">
    <div class="sc-riga"><div><label for="fm-nome">Nome (nel pannello)</label><input type="text" id="fm-nome" name="nome" value="<?= h($v['nome']) ?>" required maxlength="100" aria-describedby="fm-nome-aiuto"></div>
      <div><label for="fm-slug">Nome breve</label><input type="text" id="fm-slug" name="slug" value="<?= h($v['slug']) ?>" maxlength="100" aria-describedby="fm-slug-aiuto"></div></div>
    <div class="hint" id="fm-nome-aiuto">Lo vedi solo tu, nel pannello e nel widget Modulo. Per esempio: Iscrizione dalla home.</div>
    <div class="hint" id="fm-slug-aiuto">Va nell'indirizzo della pagina del form (<?= h(MODULI_URL) ?>/modulo/<b>nome-breve</b>/). Vuoto: dal nome.</div>
    <?php if ($v['id']): ?>
      <label for="fm-tipo">Tipo di form</label>
      <select id="fm-tipo" name="tipo" aria-describedby="fm-tipo-aiuto"><?php foreach (FORM_TIPI as $k => $et): ?><option value="<?= $k ?>"<?= $v['tipo'] === $k ? ' selected' : '' ?>><?= h($et) ?></option><?php endforeach; ?></select>
      <div class="hint" id="fm-tipo-aiuto"><?= h(FORM_TIPI_SPIEGA[$v['tipo']]) ?></div>
    <?php else: ?><input type="hidden" name="tipo" value="<?= h($v['tipo']) ?>"><p class="hint"><b><?= h(FORM_TIPI[$v['tipo']]) ?></b>: <?= h(FORM_TIPI_SPIEGA[$v['tipo']]) ?> <a href="form.php?nuovo=1">Cambia tipo</a></p><?php endif; ?>
    <?php if (!in_array($v['tipo'], ['profilo', 'disiscrizione'], true)): ?>
      <label class="nl-casella fo-casella"><input type="checkbox" name="gdpr" value="1" <?= $v['gdpr'] ? 'checked' : '' ?> aria-describedby="fm-gdpr-aiuto"> Attiva i campi GDPR</label>
      <div class="hint" id="fm-gdpr-aiuto">Sopra la casella del consenso il form mostra la dichiarazione (la scrivi nel passo 2). La casella parte vuota: la spunta chi compila. Data, indirizzo IP e testo finiscono nel registro del consenso del contatto.</div>
    <?php endif; ?>
    <label class="nl-casella fo-casella"><input type="checkbox" name="attivo" value="1" <?= $v['attivo'] ? 'checked' : '' ?>> Attivo (riceve compilazioni)</label>
  </div></div>
<?php elseif ($passo === 2): ?>
  <input type="hidden" name="campi" id="fm-campi-json">
  <div class="sc-griglia">
    <div>
      <div class="sc-scatola"><h3>2. Aspetto</h3><div class="sc-corpo">
        <label for="fm-titolo">Titolo mostrato sopra il form</label><input type="text" id="fm-titolo" name="titolo" value="<?= h($v['titolo']) ?>" maxlength="255">
        <label for="fm-intro">Testo di introduzione</label><textarea id="fm-intro" name="intro" rows="2"><?= h($v['intro']) ?></textarea>
        <label for="fm-pulsante">Testo del pulsante</label><input type="text" id="fm-pulsante" name="pulsante" value="<?= h($v['pulsante']) ?>" maxlength="100">
      </div></div>
      <div class="sc-scatola"><h3>Campi</h3><div class="sc-corpo">
        <p class="hint" style="margin-top:0">Trascina ⋮⋮ per cambiare l'ordine. <?= $v['tipo'] !== 'generico' ? 'Lega ogni campo a un attributo del contatto: il valore scritto finisce nella sua scheda. La casella da spuntare va con un attributo Sì/No.' : 'Un campo di tipo Email serve per iscrivere alla newsletter e per risponderti.' ?> Nelle scelte, scrivi le opzioni una per riga.</p>
        <div id="fm-campi"></div>
        <button type="button" class="btn btn-secondary btn-sm" data-al-clic="fmAggiungi()">+ Aggiungi un campo</button>
      </div></div>
    </div>
    <aside>
      <div class="sc-scatola"><h3>Consenso</h3><div class="sc-corpo">
        <?php if (in_array($v['tipo'], ['profilo', 'disiscrizione'], true)): ?>
          <p class="hint" style="margin-top:0">Questo tipo di form non chiede il consenso: <?= $v['tipo'] === 'profilo' ? 'si apre dal link personale delle email, e la persona cambia solo i suoi dati.' : 'serve a uscire dalla newsletter.' ?></p>
        <?php else: ?>
          <?php if ($v['gdpr']): ?>
            <label for="fm-gdpr-testo">Dichiarazione GDPR</label>
            <textarea id="fm-gdpr-testo" name="gdpr_testo" rows="4" maxlength="2000" aria-describedby="fm-gdpr-testo-aiuto"><?= h($v['gdpr_testo']) ?></textarea>
            <div class="hint" id="fm-gdpr-testo-aiuto">Sta sopra la casella. Vuoto: «<?= h(FORM_GDPR_PARTENZA) ?>»</div>
          <?php else: ?><p class="hint" style="margin-top:0">I campi GDPR sono spenti: li accendi nel <a href="form.php?id=<?= (int)$v['id'] ?>&amp;passo=1">passo 1</a>.</p><?php endif; ?>
          <label for="fm-consenso">Testo della casella del consenso</label><textarea id="fm-consenso" name="consenso" rows="2" maxlength="500" aria-describedby="fm-consenso-aiuto"><?= h($v['consenso']) ?></textarea>
          <div class="hint" id="fm-consenso-aiuto">Vuoto: «Ho letto l'informativa privacy e accetto il trattamento dei miei dati per questa richiesta.», con il link alla pagina della privacy.</div>
        <?php endif; ?>
      </div></div>
      <div class="sc-scatola"><h3>Come appare</h3><div class="sc-corpo">
        <p class="hint" style="margin-top:0">Salva, poi apri la pagina del form per vederlo come lo vede chi lo compila.</p>
        <?php if ($v['attivo']): ?><a class="btn btn-secondary btn-sm" href="<?= h(fb_url_ospitata($v)) ?>" target="_blank" rel="noopener">Apri la pagina del form</a><?php else: ?><p class="hint">Il form è spento: la sua pagina si apre quando lo accendi (passo 1).</p><?php endif; ?>
      </div></div>
    </aside>
  </div>
<?php elseif ($passo === 3): ?>
  <div class="sc-griglia">
    <div>
      <div class="sc-scatola"><h3>3. Dopo l'invio</h3><div class="sc-corpo">
        <fieldset class="fo-scelte"><legend>Cosa vede chi ha compilato il form</legend>
          <?php foreach (FORM_DOPO as $k => $et): ?><label class="nl-casella fo-casella"><input type="radio" name="dopo_invio" value="<?= $k ?>"<?= $v['dopo_invio'] === $k ? ' checked' : '' ?>> <?= h($et) ?></label><?php endforeach; ?>
        </fieldset>
        <label for="fm-riuscita">Messaggio di riuscita</label><textarea id="fm-riuscita" name="messaggi[riuscita]" rows="2" maxlength="300" aria-describedby="fm-riuscita-aiuto"><?= h($fmMsg['riuscita']) ?></textarea>
        <div class="hint" id="fm-riuscita-aiuto">Con «Un messaggio al posto del form»: il form sparisce e al suo posto esce questo testo.</div>
        <label for="fm-grazie-titolo">Titolo della pagina di ringraziamento</label><input type="text" id="fm-grazie-titolo" name="grazie_titolo" value="<?= h($v['grazie_titolo']) ?>" maxlength="255">
        <label for="fm-grazie-testo">Testo della pagina di ringraziamento</label><textarea id="fm-grazie-testo" name="grazie_testo" rows="3"><?= h($v['grazie_testo']) ?></textarea>
        <label for="fm-grazie-link">Link del pulsante «Continua» (facoltativo)</label><input type="text" name="grazie_link" id="fm-grazie-link" value="<?= h($v['grazie_link']) ?>" placeholder="per esempio /blog/" data-link-sito>
        <label for="fm-dopo-url">Un altro indirizzo: dove va chi ha compilato</label><input type="text" name="dopo_url" id="fm-dopo-url" value="<?= h($v['dopo_url']) ?>" placeholder="per esempio /grazie-davvero/" data-link-sito aria-describedby="fm-dopo-url-aiuto">
        <div class="hint" id="fm-dopo-url-aiuto">Solo con «Un altro indirizzo». Una pagina del sito (comincia con /) oppure un indirizzo intero con https://.</div>
      </div></div>
      <div class="sc-scatola"><h3>Messaggi d'errore</h3><div class="sc-corpo">
        <p class="hint" style="margin-top:0">Vuoto: i messaggi di sempre (li vedi scritti sotto).</p>
        <label for="fm-m-obb">Un campo obbligatorio vuoto o sbagliato</label><input type="text" id="fm-m-obb" name="messaggi[obbligatorio]" value="<?= h($fmMsg['obbligatorio']) ?>" maxlength="300" aria-describedby="fm-m-obb-aiuto">
        <div class="hint" id="fm-m-obb-aiuto"><code>{campo}</code> diventa il nome del campo. Vuoto: «Controlla il campo «Nome».»</div>
        <label for="fm-m-email">Un'email scritta male</label><input type="text" id="fm-m-email" name="messaggi[email]" value="<?= h($fmMsg['email']) ?>" maxlength="300" aria-describedby="fm-m-email-aiuto">
        <div class="hint" id="fm-m-email-aiuto">Vuoto: «<?= h(MODULI_ERRORI['email']) ?>»</div>
        <label for="fm-m-err">Qualcosa non è andato</label><input type="text" id="fm-m-err" name="messaggi[errore]" value="<?= h($fmMsg['errore']) ?>" maxlength="300" aria-describedby="fm-m-err-aiuto">
        <div class="hint" id="fm-m-err-aiuto">Per esempio quando l'email non parte. Vuoto: «<?= h(MODULI_ERRORI['posta']) ?>»</div>
      </div></div>
    </div>
    <aside>
      <div class="sc-scatola"><h3>Avviso a te</h3><div class="sc-corpo">
        <label class="ed-spunta fo-casella"><input type="checkbox" name="avviso" value="1" <?= $v['avviso'] ? 'checked' : '' ?>> Avvisami via email a ogni compilazione</label>
        <?php $avvisoSito = posta_notifica_email(); /* 1.9.0: a chi va l'avviso di questo form */ ?>
        <label for="fm-avviso-a">Manda l'avviso a</label>
        <textarea name="avviso_a" id="fm-avviso-a" rows="2" placeholder="<?= h($avvisoSito !== '' ? $avvisoSito : 'nome@esempio.it') ?>" autocomplete="off" spellcheck="false" aria-describedby="fm-avviso-aiuto"><?= h((string)($v['avviso_a'] ?? '')) ?></textarea>
        <small class="fm-aiuto" id="fm-avviso-aiuto">Uno o più indirizzi, separati da una virgola o uno per riga (al massimo <?= FORM_AVVISO_MAX ?>). <?= $avvisoSito !== '' ? 'Vuoto: va a ' . h($avvisoSito) . ', l\'indirizzo degli avvisi del sito.' : '<b>Vuoto: l\'avviso non parte</b>, perché il sito non ha un indirizzo per gli avvisi (Impostazioni, Posta in uscita).' ?></small>
      </div></div>
    </aside>
  </div>
<?php elseif ($passo === 4): ?>
  <?php if (in_array($v['tipo'], ['profilo', 'disiscrizione'], true)): ?>
  <div class="sc-scatola"><h3>4. Conferma</h3><div class="sc-corpo">
    <p class="hint" style="margin-top:0"><?= $v['tipo'] === 'profilo' ? 'Un form di aggiornamento del profilo non iscrive: cambia i dati, le liste e i consensi del contatto che lo apre dal suo link. Non c\'è niente da confermare.' : 'Un form di disiscrizione non iscrive: chi scrive la sua email riceve il link per uscire. Non c\'è niente da confermare.' ?></p>
  </div></div>
  <?php else:
    $fmOptin = function_exists('nd_modelli_optin') ? nd_modelli_optin() : [];
    require_once dirname(__DIR__) . '/includes/scaricabili.php'; $riservati = array_filter(scaricabili_elenco(), fn($x) => $x['riservato']); /* 2.0.0 */ ?>
  <div class="sc-griglia">
    <div>
      <div class="sc-scatola"><h3>4. Conferma dell'iscrizione</h3><div class="sc-corpo">
        <fieldset class="fo-scelte"><legend>Come entra nella lista</legend>
          <?php foreach (FORM_CONFERME as $k => [$et, $sp]): ?><label class="nl-casella fo-casella fo-scelta"><input type="radio" name="conferma" value="<?= $k ?>"<?= $v['conferma'] === $k ? ' checked' : '' ?>> <span><b><?= h($et) ?></b><br><small><?= h($sp) ?></small></span></label><?php endforeach; ?>
        </fieldset>
        <label for="fm-lista">Lista principale</label>
        <select name="lista_id" id="fm-lista" aria-describedby="fm-lista-aiuto"><option value="0"><?= $v['tipo'] === 'generico' ? 'Nessuna (non iscrive)' : 'Scegli la lista' ?></option><?php foreach ($liste as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$v['lista_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= h(newsletter_4byte_fuori((string)$l['nome'])) ?></option><?php endforeach; ?></select>
        <div class="hint" id="fm-lista-aiuto">Chi compila il form entra qui. <?= $v['tipo'] === 'generico' ? 'Con «Nessuna» il form non iscrive alla newsletter.' : '' ?></div>
        <?php if (count($liste) > 1): ?>
        <fieldset class="fo-scelte"><legend>Entra anche in queste liste (facoltativo)</legend>
          <?php foreach ($liste as $l): ?><label class="nl-casella fo-casella"><input type="checkbox" name="liste[]" value="<?= (int)$l['id'] ?>"<?= in_array((int)$l['id'], $v['liste'], true) ? ' checked' : '' ?>> <?= h(newsletter_4byte_fuori((string)$l['nome'])) ?></label><?php endforeach; ?>
          <div class="hint">Per far scegliere le liste a chi compila, aggiungi il blocco «Iscrizione a più liste» nei campi (passo 2).</div>
        </fieldset>
        <?php endif; ?>
        <label class="nl-casella fo-casella"><input type="checkbox" name="aggiorna_esistenti" value="1"<?= $v['aggiorna_esistenti'] ? ' checked' : '' ?> aria-describedby="fm-agg-aiuto"> Aggiorna i contatti esistenti</label>
        <div class="hint" id="fm-agg-aiuto">Se chi compila è già nei contatti, quello che scrive nel form prende il posto dei suoi dati. Spenta: i dati che c'erano restano.</div>
      </div></div>
      <?php /* 2.7.0: l'email di conferma di questo form, parte per parte (includes/newsletter-email.php) */
        $ecValori = is_array($v['conferma_email'] ?? null) ? $v['conferma_email'] : [];
        $ecComuni = nl_email_comuni();
        $ecEredita = nl_email_ereditati(false, true);
        $ecOrigine = array_map(fn($k) => isset($ecComuni[$k]) ? 'comune' : 'partenza', array_combine(array_keys(NL_EMAIL_PARTI), array_keys(NL_EMAIL_PARTI)));
        $ecCambiate = count($ecValori); ?>
      <div class="sc-scatola"><h3>Email di doppio opt-in</h3><div class="sc-corpo">
        <label for="fm-doi">Quale email riceve per confermare</label>
        <select id="fm-doi" name="doi_modello_id" aria-describedby="fm-doi-aiuto"><option value="0">L'email di conferma di questo form (qui sotto)</option><?php foreach ($fmOptin as $mo): ?><option value="<?= (int)$mo['id'] ?>"<?= (int)$v['doi_modello_id'] === (int)$mo['id'] ? ' selected' : '' ?>>Modello: <?= h(newsletter_4byte_fuori((string)$mo['nome'])) ?></option><?php endforeach; ?></select>
        <div class="hint" id="fm-doi-aiuto"><?= $fmOptin ? 'I modelli con l\'etichetta di doppio opt-in, fatti con l\'editor a blocchi in Modelli.' : 'Per usare un modello fatto con l\'editor a blocchi, crealo in <a href="newsletter-modelli.php">Modelli</a> con l\'etichetta di doppio opt-in.' ?></div>
        <label for="fm-doi-url">Dopo il clic di conferma, vai a (facoltativo)</label><input type="text" id="fm-doi-url" name="doi_url" value="<?= h($v['doi_url']) ?>" placeholder="per esempio /benvenuto/" data-link-sito aria-describedby="fm-doi-url-aiuto">
        <div class="hint" id="fm-doi-url-aiuto">Vuoto: la pagina «Email confermata» del sito. Con un file da dare dopo la conferma resta quella, con il pulsante per scaricarlo.</div>
      </div></div>
      <?php /* per l'anteprima dell'email (assets/email-conferma.js): il titolo e il nome del form dicono per cosa ci si iscrive; questo passo non li salva */ ?>
      <input type="hidden" name="titolo" value="<?= h($v['titolo']) ?>"><input type="hidden" name="nome" value="<?= h($v['nome']) ?>">
      <div class="sc-scatola" id="fm-conferma"><h3>Email di conferma</h3><div class="sc-corpo" data-email-conferma="email-conferma-api.php" data-contesto="form" data-csrf="<?= h(csrf_token()) ?>">
        <p class="hint" style="margin-top:0">L'email che arriva a chi compila il form con la doppia conferma, se qui sopra non scegli un modello. Lascia vuoto un campo per usare il testo comune (<a href="newsletter.php?scheda=impostazioni#nl-conferma">Newsletter, Impostazioni</a>) o quello di partenza. <?= $ecCambiate ? '<b>Parti cambiate per questo form: ' . $ecCambiate . '.</b>' : 'Per ora questo form usa i testi in uso per tutti.' ?></p>
        <?php if (!(int)$v['lista_id'] || $v['conferma'] !== 'doppia'): ?><p class="hint" id="fm-conferma-spenta"><b>Adesso questo form non manda l'email di conferma:</b> scegli una lista e «Doppia conferma», poi salva.</p><?php endif; ?>
        <?= ec_segnaposti_html('form') ?>
        <details class="ec-dettagli" id="fm-conferma-campi"<?= $ecCambiate ? ' open' : '' ?>><summary>Cambia i testi dell'email</summary>
          <?= ec_campi_html($ecValori, $ecEredita, 'form', $ecOrigine) ?>
        </details>
        <?= ec_anteprima_html() ?>
      </div></div>
    </div>
    <aside>
      <div class="sc-scatola"><h3>Conferma semplice</h3><div class="sc-corpo">
        <p class="hint" style="margin-top:0">L'email che riceve chi entra con la conferma semplice. <code>{nome}</code> diventa il suo nome.</p>
        <label for="fm-bo">Oggetto</label><input type="text" id="fm-bo" name="messaggi[benvenuto_oggetto]" value="<?= h($fmMsg['benvenuto_oggetto']) ?>" maxlength="300">
        <label for="fm-bt">Testo</label><textarea id="fm-bt" name="messaggi[benvenuto_testo]" rows="5" maxlength="2000"><?= h($fmMsg['benvenuto_testo']) ?></textarea>
      </div></div>
      <div class="sc-scatola"><h3>Un file in regalo</h3><div class="sc-corpo">
        <label for="fm-omaggio">Dopo la conferma, dai questo file</label>
        <select name="omaggio" id="fm-omaggio" aria-describedby="fm-omaggio-aiuto"><option value="">Nessun file</option><?php foreach ($riservati as $x): ?><option value="<?= h($x['nome']) ?>" <?= (string)($v['omaggio'] ?? '') === $x['nome'] ? 'selected' : '' ?>><?= h($x['titolo']) ?> (<?= h($x['file']) ?>)</option><?php endforeach; ?></select>
        <small class="fm-aiuto" id="fm-omaggio-aiuto"><?= $riservati ? 'Chi compila riceve per email un link personale al file, dopo aver confermato l\'indirizzo (con la doppia conferma) oppure subito. Serve una lista qui accanto.' : 'Qui compaiono i file <b>riservati</b>: caricane uno in Download con la spunta «Riservato».' ?></small>
      </div></div>
    </aside>
  </div>
  <?php endif; ?>
<?php elseif ($passo === 5): $fmUrl = fb_url_ospitata($v); ?>
</form>
  <?php if (!$v['attivo']): ?><p class="nl-avviso" role="status">Il form è spento: la sua pagina e i codici funzionano quando lo accendi nel <a href="form.php?id=<?= (int)$v['id'] ?>&amp;passo=1">passo 1</a>.</p><?php endif; ?>
  <div class="sc-griglia">
    <div>
      <div class="sc-scatola"><h3>5. Condividi</h3><div class="sc-corpo">
        <label for="fo-link">Link alla pagina del form</label>
        <input type="text" id="fo-link" value="<?= h($fmUrl) ?>" readonly data-copia-se-stesso aria-describedby="fo-link-aiuto">
        <div class="hint" id="fo-link-aiuto">Una pagina con solo il form, da mandare a chi vuoi o da mettere nei social. Fai clic nel campo per copiarlo. <?= $v['tipo'] === 'profilo' ? '<b>Questo form si apre solo dal link personale</b> che il contatto trova in fondo alle email («Aggiorna le tue preferenze»).' : '' ?></div>
        <p><a class="btn btn-secondary btn-sm" href="<?= h($fmUrl) ?>" target="_blank" rel="noopener">Apri la pagina del form</a></p>
        <h4 class="fo-sotto">Nel sito</h4>
        <p class="hint" style="margin-top:0">Nell'editor visuale aggiungi il widget Modulo e scegli «Form: <?= h($v['nome']) ?>».</p>
        <h4 class="fo-sotto">In un altro sito</h4>
        <label for="fo-iframe">Codice iframe</label>
        <textarea id="fo-iframe" rows="3" readonly data-copia-se-stesso aria-describedby="fo-iframe-aiuto"><?= h(fb_codice_iframe($v)) ?></textarea>
        <div class="hint" id="fo-iframe-aiuto">Il modo più semplice: incolla questo codice dove vuoi il form. Si vede la pagina del form, dentro una cornice.</div>
        <label for="fo-html">Codice HTML</label>
        <textarea id="fo-html" rows="6" readonly data-copia-se-stesso aria-describedby="fo-html-aiuto"><?= h(fb_codice_html($v)) ?></textarea>
        <div class="hint" id="fo-html-aiuto">Il form vero e proprio, con il suo stile e la verifica «Sono una persona» (serve JavaScript). Dopo l'invio, chi compila arriva alla pagina del form su questo sito.</div>
        <label for="fo-semplice">Codice HTML semplice</label>
        <textarea id="fo-semplice" rows="6" readonly data-copia-se-stesso aria-describedby="fo-semplice-aiuto"><?= h(fb_codice_html($v, true)) ?></textarea>
        <div class="hint" id="fo-semplice-aiuto">Per i siti che non accettano JavaScript: funziona senza. Dopo l'invio, una domanda di controllo («Quanto fa tre più due?») prende il posto della casella «Sono una persona».</div>
      </div></div>
    </div>
    <aside>
      <div class="sc-scatola"><h3>Codice QR</h3><div class="sc-corpo">
        <div class="fo-qr"><?= fb_qr_svg($v, 4) ?></div>
        <p class="hint">Inquadralo con il telefono: apre la pagina del form. Per volantini e locandine.</p>
        <a class="btn btn-secondary btn-sm" href="form.php?id=<?= (int)$v['id'] ?>&amp;qr=1">Scarica il codice QR</a>
      </div></div>
      <?php if ($v['tipo'] === 'popup'): $pp = $v['popup_id'] ? popup_leggi((int)$v['popup_id']) : null; $pp = $pp ?: ['attivo' => 0, 'dove' => 'tutte', 'percorsi' => '', 'innesco' => 'secondi', 'valore' => 8, 'frequenza_giorni' => 7]; ?>
      <form method="post" class="sc-scatola" id="fo-popup" action="form.php?id=<?= (int)$v['id'] ?>&amp;passo=5"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="passo" value="5"><input type="hidden" name="azione" value="popup">
        <h3>Il pop-up</h3><div class="sc-corpo">
        <label class="nl-casella fo-casella"><input type="checkbox" name="pp[attivo]" value="1"<?= (int)$pp['attivo'] ? ' checked' : '' ?>> Mostra il pop-up sul sito</label>
        <label for="pp-dove">Su quali pagine</label><select id="pp-dove" name="pp[dove]"><?php foreach (POPUP_DOVE as $k => $et): ?><option value="<?= $k ?>"<?= $pp['dove'] === $k ? ' selected' : '' ?>><?= h($et) ?></option><?php endforeach; ?></select>
        <label for="pp-percorsi">Indirizzi (con «Solo gli indirizzi elencati»)</label><textarea id="pp-percorsi" name="pp[percorsi]" rows="2" placeholder="per esempio /blog/"><?= h((string)$pp['percorsi']) ?></textarea>
        <label for="pp-innesco">Quando compare</label><select id="pp-innesco" name="pp[innesco]"><?php foreach (POPUP_INNESCHI as $k => $et): ?><option value="<?= $k ?>"<?= $pp['innesco'] === $k ? ' selected' : '' ?>><?= h($et) ?></option><?php endforeach; ?></select>
        <label for="pp-valore">Secondi, o percentuale della pagina</label><input type="number" id="pp-valore" name="pp[valore]" min="0" max="600" value="<?= (int)$pp['valore'] ?>">
        <label for="pp-freq">Ogni quanti giorni la stessa persona lo rivede</label><input type="number" id="pp-freq" name="pp[frequenza_giorni]" min="0" max="365" value="<?= (int)$pp['frequenza_giorni'] ?>">
        <button class="btn btn-primary" type="submit">Salva il pop-up</button>
        <p class="hint">Lo trovi anche in Popup, con il nome «Form: <?= h($v['nome']) ?>».</p>
      </div></form>
      <?php endif; ?>
      <?php if ($v['tipo'] === 'profilo'): $fmCentro = (int)get_setting('newsletter_preferenze_form', '0') === (int)$v['id']; ?>
      <form method="post" class="sc-scatola" id="fo-centro" action="form.php?id=<?= (int)$v['id'] ?>&amp;passo=5"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="passo" value="5"><input type="hidden" name="azione" value="preferenze">
        <h3>Centro preferenze</h3><div class="sc-corpo">
        <label class="nl-casella fo-casella"><input type="checkbox" name="centro" value="1"<?= $fmCentro ? ' checked' : '' ?> aria-describedby="fo-centro-aiuto"> Usa questo form come centro preferenze</label>
        <div class="hint" id="fo-centro-aiuto">Il link «Aggiorna le tue preferenze» delle email apre questo form, già compilato con i dati del contatto. Spenta: si apre la pagina delle preferenze di sempre.</div>
        <button class="btn btn-primary" type="submit">Salva il centro preferenze</button>
      </div></form>
      <?php endif; ?>
    </aside>
  </div>
<?php endif; ?>
<?php if ($passo !== 5): ?>
  <div class="fo-azioni">
    <?php if ($v['id']): ?>
      <a class="btn btn-secondary" href="form.php">Elenco</a>
      <button type="submit" class="btn btn-secondary" name="vai" value="resta">Salva</button>
      <button type="submit" class="btn btn-primary" name="vai" value="avanti">Avanti: <?= h(mb_strtolower($fmProssimo)) ?></button>
    <?php else: ?>
      <a class="btn btn-secondary" href="form.php">Annulla</a>
      <button type="submit" class="btn btn-primary">Crea il form</button>
    <?php endif; ?>
  </div>
</form>
<?php else: ?>
  <div class="fo-azioni"><a class="btn btn-secondary" href="form.php?id=<?= (int)$v['id'] ?>&amp;passo=4">Indietro</a><a class="btn btn-primary" href="form.php">Fine: torna all'elenco</a></div>
<?php endif; ?>
<?php if ($passo === 1 && $v['id']): ?>
  <form method="post" class="fo-elimina" data-conferma="Eliminare il form «<?= h($v['nome']) ?>» e tutte le sue compilazioni? Non si torna indietro." data-conferma-ok="Elimina il form" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="azione" value="elimina"><button type="submit" class="sc-cestina">Elimina il form</button></form>
<?php endif; ?>
<?php if ($passo === 4): ?><?= ec_stile_html() ?><script src="<?= pannello_file('assets/email-conferma.js') ?>"></script><?php endif; ?>
<?php if ($passo === 2):
  $fmAttr = [];
  foreach (fb_attributi_campi() as $n => $a) $fmAttr[$n] = ['etichetta' => newsletter_4byte_fuori(newsletter_attributo_etichetta($a)), 'tipo' => $a['tipo'], 'opzioni' => fb_opzioni_attributo($a)];
  $fmGruppi = []; foreach (nl_gruppi() as $gid => $g) $fmGruppi[] = ['id' => $gid, 'nome' => $g['nome']]; ?>
<script src="<?= pannello_file('assets/editor/Sortable.min.js') ?>"></script>
<script<?= nonce_attr() ?>>
var fmTipi = <?= json_encode(FORM_TIPI_CAMPO, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var fmCampi = <?= json_encode($v['campi'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
// 2.1: i tipi di file che un campo File può accettare e il peso che il server lascia passare (MB).
var fmFile = <?= json_encode(array_map(fn($x) => $x[0], FORM_FILE_TIPI), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var fmPesoServer = <?= json_encode(round(min(form_file_limite_server(), FORM_FILE_PESO_MAX << 20) / 1048576, 1)) ?>;
// 2.16.0: gli attributi dei contatti, i tipi di campo che vanno con ognuno, le liste e i gruppi da offrire.
var fmAttr = <?= json_encode((object)$fmAttr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var fmCompat = <?= json_encode(FORM_ATTR_CAMPI, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var fmListe = <?= json_encode(array_map(fn($l) => ['id' => (int)$l['id'], 'nome' => newsletter_4byte_fuori((string)$l['nome'])], $liste), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var fmGruppi = <?= json_encode($fmGruppi, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var fmGruppiAttivi = <?= nl_gruppi_attivi() ? 'true' : 'false' ?>;
var fmGenerico = <?= $v['tipo'] === 'generico' ? 'true' : 'false' ?>;
</script>
<script src="<?= pannello_file('assets/form-campi.js') ?>"></script>
<?php endif; ?>
<?php endif; ?>
<style>
.fo-testa{margin-bottom:1rem}
.fo-sopra{margin:0 0 .6rem;color:var(--text-muted);font-size:.88rem}
.fo-sopra a{color:var(--accento-testo)}
.fo-passi ol{display:flex;flex-wrap:wrap;gap:.4rem;list-style:none;margin:0;padding:0;counter-reset:passo}
.fo-passi li>a,.fo-passi li>span{display:flex;align-items:center;gap:.45rem;min-height:44px;padding:.35rem .8rem;border:1px solid var(--bordo-campo);border-radius:999px;color:var(--text);text-decoration:none;font-size:.88rem;background:var(--surface)}
.fo-passi li.qui>span{background:var(--coral);border-color:var(--coral);color:var(--su-accento);font-weight:700}
.fo-passi li.fatto>a{border-color:var(--successo-bordo)}
.fo-passi li.fatto .fo-num::after{content:" ✓"}
.fo-num{font-weight:700}
.fo-azioni{display:flex;gap:.6rem;flex-wrap:wrap;justify-content:flex-end;margin:1rem 0}
.fo-elimina{text-align:right;margin:0 0 1rem}
.fo-casella{display:flex;gap:.5rem;align-items:flex-start;font-weight:400;margin:.6rem 0 .2rem;min-height:24px}
.fo-scelta span small{color:var(--text-muted)}
.sc-corpo .fo-scelte{border:0;margin:.4rem 0 .6rem;padding:0;min-width:0}
.sc-corpo .fo-scelte legend{font-weight:600;font-size:.82rem;padding:0;margin-bottom:.3rem;color:var(--text)}
.fo-casella{font-size:.88rem}
.fo-sotto{margin:1.2rem 0 .4rem;font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);border-top:1px solid var(--border);padding-top:.8rem}
.fo-qr svg{width:100%;max-width:220px;height:auto;display:block;margin:0 auto}
.fo-tipi{list-style:none;margin:0 0 1rem;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,240px),1fr));gap:.7rem}
.fo-tipo{display:flex;flex-direction:column;gap:.3rem;padding:1rem;border:1px solid var(--bordo-campo);border-radius:10px;text-decoration:none;color:var(--text);background:var(--surface);min-height:44px}
.fo-tipo:hover,.fo-tipo:focus-visible{border-color:var(--coral)}
.fo-tipo span{color:var(--text-muted);font-size:.86rem}
.fo-viste{margin-bottom:.8rem}
.fm-campo{border:1px solid var(--border);border-radius:8px;padding:.5rem .6rem;margin-bottom:.5rem;background:var(--fondo-2)}
.fm-riga{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}.fm-riga2{margin-top:.4rem;padding-left:1.4rem}
.fm-riga input[type=text]{flex:1;padding:.4rem .6rem;border:1px solid var(--bordo-campo);border-radius:6px;font:inherit;font-size:.85rem}
.fm-riga select{padding:.4rem .5rem;border:1px solid var(--bordo-campo);border-radius:6px;font:inherit;font-size:.82rem}
.fm-riga textarea.fm-opzioni{flex:1;min-height:0;padding:.4rem .6rem;border:1px solid var(--bordo-campo);border-radius:6px;font:inherit;font-size:.85rem;resize:vertical}
/* 1.4.2: dentro .sc-corpo le tendine erano larghe 100% e schiacciavano la casella dell'etichetta a pochi pixel */
.sc-corpo .fm-riga select{width:auto;flex:0 0 auto;max-width:100%}
.sc-corpo .fm-riga input[type=text]{width:auto;flex:1 1 8rem;min-width:8rem}
.fm-obb{font-size:.78rem;color:var(--text-muted);display:flex;gap:.3rem;align-items:center;white-space:nowrap;margin:0}
.fm-maniglia{cursor:grab;color:var(--text-muted)}
.fm-nota{font-size:.8rem;color:var(--text-muted);flex:1 1 100%}
.fm-attr{display:flex;gap:.4rem;align-items:center;font-size:.8rem;color:var(--text-muted);margin:0;font-weight:400}
/* 2.1: le regole del campo File */
.fm-file{flex:1;display:flex;flex-direction:column;gap:.4rem;min-width:0}
.sc-corpo .fm-file-tipi,.sc-corpo .fm-scelta-liste{border:0;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.3rem .9rem;min-width:0}
.sc-corpo .fm-file-tipi legend,.sc-corpo .fm-scelta-liste legend{font-size:.78rem;color:var(--text-muted);padding:0;margin-bottom:.2rem}
.sc-corpo .fm-file-tipi label,.sc-corpo .fm-scelta-liste label{display:flex;gap:.3rem;align-items:center;font-weight:400;font-size:.85rem;margin:0}
.sc-corpo .fm-file-peso{display:flex;gap:.5rem;align-items:center;font-weight:400;font-size:.85rem;margin:0}
.sc-corpo .fm-file-peso select{width:auto}
.fm-file-nota{font-size:.78rem;color:var(--text-muted)}.fm-via{border:0;background:none;color:var(--errore-testo);font-size:1.1rem;cursor:pointer;min-width:32px;min-height:32px}
</style>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
