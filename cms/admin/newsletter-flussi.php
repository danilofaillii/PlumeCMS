<?php
/**
 * Automazioni della newsletter (fino alla 2.14.0 "Flussi automatici"). Elenco con i modelli; per ogni automazione la tela
 * (barra con nome, tag e stato; pannello Inneschi, Azioni, Regole; blocchi collegati; numeri per passo) e la scheda
 * Attività e registro (chi è dentro, togli, aggiungi a mano, la storia). Motore in includes/newsletter-flussi.php e
 * includes/newsletter-automazioni.php. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/newsletter.php';
require_once dirname(__DIR__) . '/includes/newsletter-automazioni.php';
require_once dirname(__DIR__) . '/includes/form.php';
require_admin(); // sicurezza (ruoli): solo amministratore
// Il battito della tela: ogni cinque minuti la pagina chiama se stessa così, e la sessione resta viva
// anche se il disegno di un'automazione dura un'ora. Senza, al Salva si tornava al login e il lavoro si perdeva.
if (isset($_GET['ping'])) { http_response_code(204); exit; }
newsletter_schema();
newsletter_flussi_schema();
$adminTitle = 'Automazioni';
$id = (int)($_GET['id'] ?? 0);
$nuovo = isset($_GET['nuovo']);
$scheda = ($_GET['scheda'] ?? '') === 'attivita' ? 'attivita' : 'tela';
$modelli = nl_aut_modelli();
$modello = $nuovo && isset($modelli[(string)($_GET['modello'] ?? '')]) ? (string)$_GET['modello'] : '';
$errore = ''; $postato = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $azione = (string)($_POST['azione'] ?? '');
    try {
        if ($azione === 'elimina') { newsletter_flusso_elimina($id); flash('success', 'Automazione eliminata.'); header('Location: newsletter-flussi.php'); exit; }
        if ($azione === 'togli' || $azione === 'aggiungi') {
            $f = newsletter_flusso($id);
            if (!$f) { http_response_code(404); exit('Automazione non trovata.'); }
            if ($azione === 'togli') { $ok = nl_aut_togli($id, (int)($_POST['corso'] ?? 0)); flash($ok ? 'success' : 'error', $ok ? 'Contatto tolto dall\'automazione: non riceve più niente da qui.' : 'Questo contatto non era più dentro l\'automazione: niente da togliere.'); }
            else { [$ok, $msg] = nl_aut_aggiungi($f, is_scalar($_POST['email'] ?? null) ? (string)$_POST['email'] : ''); flash($ok ? 'success' : 'error', $msg); }
            header('Location: newsletter-flussi.php?id=' . $id . '&scheda=attivita');
            exit;
        }
        $id = newsletter_flusso_salva($id, $_POST);
        $nodo = (int)($_POST['nodo'] ?? 0);
        if ($azione === 'design' && $nodo > 0) { header('Location: newsletter-design.php?flusso=' . $id . '&nodo=' . $nodo); exit; }
        if ($azione === 'stacca') {
            // 2.18.0: "Stacca dal modello" (il passo copia il contenuto di adesso e non dipende più dal modello).
            $sn = newsletter_flusso_nodo(newsletter_flusso($id)['grafo'], (int)($_POST['stacca'] ?? 0));
            if ($sn && $sn['tipo'] === 'email' && $sn['dati']['editor'] !== 'modello') flash('success', 'L\'email #' . $sn['id'] . ' ora ha una copia sua del modello: se cambi il modello, questa email non cambia più. Automazione salvata.');
            else flash('error', 'Questa email non richiama un modello: non c\'era niente da staccare. Automazione salvata.');
            header('Location: newsletter-flussi.php?id=' . $id);
            exit;
        }
        $st = newsletter_flusso($id)['stato'];
        flash('success', ['attiva' => 'Automazione salvata e attiva: entra chi arriva da adesso in poi.', 'in_pausa' => 'Automazione salvata, in pausa: non entra nessuno di nuovo, chi è dentro prosegue.', 'inattiva' => 'Automazione salvata, inattiva: non entra nessuno e chi era dentro è stato tolto.'][$st]);
        header('Location: newsletter-flussi.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        // Niente redirect: la pagina si ridisegna con quello che è arrivato dal form, così il disegno non si perde.
        $errore = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'Salvataggio non riuscito: ' . $e->getMessage();
        flash('error', $errore);
        $nuovo = !$id; $scheda = 'tela';
        try { $grafoPostato = newsletter_flusso_grafo_valido(newsletter_flusso_grafo_dal_form($_POST['grafo'] ?? [])); } catch (Throwable $x) { $grafoPostato = null; }
        $s = fn($k) => is_scalar($_POST[$k] ?? null) ? (string)$_POST[$k] : '';
        $postato = ['id' => $id, 'nome' => mb_substr(trim($s('nome')), 0, 100), 'stato' => isset(NL_AUT_STATI[$s('stato')]) ? $s('stato') : 'in_pausa', 'tag' => mb_substr($s('tag'), 0, 255),
            'inneschi' => nl_inneschi_validi(json_decode($s('inneschi'), true, 4) ?: []), 'mittente_id' => (int)$s('mittente_id'), 'rientro' => $s('rientro') !== '' ? 1 : 0, 'rientro_attesa' => (int)$s('rientro_attesa'),
            'uscita' => ['disiscritto' => $s('uscita_disiscritto') !== '', 'segmento' => (int)$s('uscita_segmento')], 'grafo' => $grafoPostato];
    }
}
$f = $id ? newsletter_flusso($id) : null;
if ($id && !$f) { http_response_code(404); exit('Automazione non trovata.'); }
if ($postato) {
    // I dati appena mandati prendono il posto di quelli del database. Se il grafo non si legge, resta quello salvato o quello di partenza.
    if ($postato['grafo'] === null) unset($postato['grafo']);
    if ($f) $f = array_merge($f, $postato);
    elseif (isset($postato['grafo'])) $f = $postato;
}
$appenaSalvato = !empty(array_filter($_SESSION['flash'] ?? [], fn($x) => ($x['type'] ?? '') === 'success'));
$liste = newsletter_liste();
$forms = form_tutti();
$campagne = db()->query('SELECT id, oggetto FROM ' . tbl('newsletter_campagne') . " WHERE stato IN ('inviata','in_invio','programmata') ORDER BY id DESC LIMIT 100")->fetchAll();
$nomiListe = array_map(fn($x) => newsletter_4byte_fuori((string)$x), array_column($liste, 'nome', 'id'));
$date = [];
foreach (newsletter_attributi() as $n => $a) if ($a['tipo'] === 'date' || (NL_SEG_COLONNE[$a['colonna'] ?? ''] ?? '') === 'date') $date[$n] = newsletter_attributo_etichetta($a);
$inneschiCosa = ['lista' => $nomiListe, 'lista_tolto' => $nomiListe, 'form' => array_column($forms, 'nome', 'id'), 'apertura' => array_column($campagne, 'oggetto', 'id'), 'clic' => array_column($campagne, 'oggetto', 'id'), 'anniversario' => $date];
// 2.22.0: le scelte degli inneschi degli addon (Test e questionari: i test, e in '_esiti' i risultati di ogni test per il filtro).
try { $flCosa = cms_filtro('automazioni_inneschi_cosa', []); } catch (Throwable $e) { error_log('automazioni_inneschi_cosa: ' . $e->getMessage()); $flCosa = []; }
$inneschiEsiti = is_array($flCosa['_esiti'] ?? null) ? $flCosa['_esiti'] : [];
foreach (is_array($flCosa) ? $flCosa : [] as $k => $v) if ($k !== '_esiti' && is_array($v) && !isset($inneschiCosa[$k])) $inneschiCosa[$k] = $v;
unset($flCosa);
$eventiNomi = ['entrato' => 'È entrato', 'email' => 'Email', 'attesa' => 'Attesa', 'bivio' => 'Ramo scelto', 'evento' => 'Attendi un evento', 'azione' => 'Azione', 'finito' => 'Ha finito', 'tolto' => 'Tolto', 'uscito' => 'È uscito', 'fermato' => 'Fermato'];
$tipiNomi = ['innesco' => 'Inneschi', 'email' => 'Email', 'attesa' => 'Attesa', 'bivio' => 'Sì o No', 'fine' => 'Fine', 'evento' => 'Attendi un evento', 'dividi' => 'Divisione condizionale', 'percento' => 'Divisione in percentuale', 'lista_aggiungi' => 'Aggiungi a una lista', 'lista_togli' => 'Togli da una lista', 'attributo' => 'Aggiorna un attributo', 'webhook' => 'Webhook'];
$descriviInneschi = function (array $inn) use ($inneschiCosa): string {
    return implode('; ', array_map(fn($x) => (nl_inneschi_nomi()[$x['tipo']] ?? $x['tipo']) . ($x['tipo'] === 'inattivo' ? ' (' . $x['id'] . ' giorni)' : ($x['tipo'] === 'anniversario' ? ': ' . ($inneschiCosa['anniversario'][$x['attr']] ?? $x['attr']) : ($x['id'] && isset($inneschiCosa[$x['tipo']][$x['id']]) ? ': ' . $inneschiCosa[$x['tipo']][$x['id']] : ''))), $inn));
};
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/quill/quill.snow.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/css/flussi.css') ?>">
<?php if (!$f && !$nuovo): $tutti = newsletter_flussi(); ?>
<div class="ct-testa"><p class="fl-intro">Un'automazione fa entrare le persone quando succede qualcosa (si iscrivono, entrano in una lista, compilano un form, compiono gli anni...) e le porta lungo i passi che disegni: email, attese, divisioni, azioni sulle liste e sugli attributi. Le email le manda il cron della newsletter, dentro il limite orario.</p><a href="#fl-crea" class="btn btn-primary">+ Crea un'automazione</a></div>
<div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr><th>Automazione</th><th>Parte quando</th><th>Entrati</th><th>In corso</th><th>Finiti</th><th>Usciti</th><th>Email inviate</th><th>Stato</th></tr></thead>
    <tbody>
    <?php if (!$tutti): ?><tr><td colspan="8"><div class="empty-state"><p>Ancora nessuna automazione: creane una qui sotto, da zero o da un modello.</p></div></td></tr><?php endif; ?>
    <?php foreach ($tutti as $x): ?>
      <tr class="ct-riga" data-flusso="<?= (int)$x['id'] ?>">
        <td class="ct-titolo"><a class="ct-nome" href="newsletter-flussi.php?id=<?= $x['id'] ?>"><?= h($x['nome']) ?></a><?= $x['tag'] !== '' ? '<div class="fl-tag-elenco">' . h($x['tag']) . '</div>' : '' ?><div class="ct-azioni"><a href="newsletter-flussi.php?id=<?= $x['id'] ?>">Modifica</a> <a href="newsletter-flussi.php?id=<?= $x['id'] ?>&amp;scheda=attivita">Attività</a></div></td>
        <td class="ct-muto"><?= h($descriviInneschi($x['inneschi'])) ?></td>
        <td class="ct-muto"><?= (int)$x['entrati'] ?></td>
        <td class="ct-muto"><?= (int)$x['in_corso'] ?></td>
        <td class="ct-muto"><?= (int)$x['finiti'] ?></td>
        <td class="ct-muto"><?= (int)$x['usciti'] ?></td>
        <td class="ct-muto"><?= (int)$x['inviate'] ?></td>
        <td><span class="badge <?= ['attiva' => 'badge-published', 'in_pausa' => 'badge-scheduled', 'inattiva' => 'badge-draft'][$x['stato']] ?>"><?= h(NL_AUT_STATI[$x['stato']]) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="form-card" id="fl-crea">
  <h3>Crea un'automazione</h3>
  <p class="hint">Parti da zero o da un modello: si apre la tela già disegnata, e non si salva niente finché non premi Salva.</p>
  <div class="fl-modelli">
    <div class="fl-modello"><b>Da zero</b><p>Un innesco e un'email di partenza: il resto lo disegni tu.</p><a class="btn btn-secondary btn-sm" href="newsletter-flussi.php?nuovo=1">Crea da zero</a></div>
    <?php foreach ($modelli as $k => [$mn, $md]): ?><div class="fl-modello" data-modello="<?= h($k) ?>"><b><?= h($mn) ?></b><p><?= h($md) ?></p><a class="btn btn-secondary btn-sm" href="newsletter-flussi.php?nuovo=1&amp;modello=<?= h($k) ?>">Usa il modello <?= h($mn) ?></a></div><?php endforeach; ?>
  </div>
</div>
<?php elseif ($scheda === 'attivita' && $f):
    $passo = isset($_GET['passo']) && ctype_digit((string)$_GET['passo']) ? (int)$_GET['passo'] : -1;
    $pag = isset($_GET['pag']) && ctype_digit((string)$_GET['pag']) ? max(1, (int)$_GET['pag']) : 1;
    [$dentro, $totDentro] = nl_aut_dentro($id, $passo, $pag);
    $cerca = is_scalar($_GET['email'] ?? null) ? mb_substr(trim((string)$_GET['email']), 0, 190) : '';
    $registro = nl_aut_registro($id, $cerca);
    $nomeNodo = function (int $n) use ($f, $tipiNomi): string {
        $x = newsletter_flusso_nodo($f['grafo'], $n);
        if (!$x) return 'passo #' . $n . ' (non c\'è più)';
        return $n === 0 ? 'Inneschi' : '#' . $n . ' ' . $tipiNomi[$x['tipo']] . ($x['tipo'] === 'email' ? ': ' . ($x['dati']['oggetto'] !== '' || $x['dati']['editor'] !== 'modello' ? newsletter_4byte_fuori($x['dati']['oggetto']) : 'dal modello #' . $x['dati']['modello']) : '');
    };
    $haMano = in_array('mano', array_column($f['inneschi'], 'tipo'), true);
?>
<nav class="imp-schede" aria-label="Automazione">
  <a href="newsletter-flussi.php?id=<?= $id ?>">Tela</a>
  <a href="newsletter-flussi.php?id=<?= $id ?>&amp;scheda=attivita" class="attiva" aria-current="page">Attività e registro</a>
</nav>
<h2 class="fl-titolo-att"><?= h($f['nome']) ?> <span class="badge <?= ['attiva' => 'badge-published', 'in_pausa' => 'badge-scheduled', 'inattiva' => 'badge-draft'][$f['stato']] ?>"><?= h(NL_AUT_STATI[$f['stato']]) ?></span></h2>
<div class="form-card" id="fl-dentro">
  <h3>Chi è dentro adesso (<?= $totDentro ?>)</h3>
  <form method="get" class="fl-filtro"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="scheda" value="attivita">
    <div class="form-group"><label for="fl-passo">Al passo</label><select id="fl-passo" name="passo" data-invia-al-cambio><option value="">Tutti i passi</option><?php foreach ($f['grafo']['nodi'] as $n): if ($n['id'] === 0) continue; ?><option value="<?= $n['id'] ?>" <?= $passo === $n['id'] ? 'selected' : '' ?>><?= h($nomeNodo($n['id'])) ?></option><?php endforeach; ?></select></div>
  </form>
  <table class="table">
    <thead><tr><th>Contatto</th><th>Passo</th><th>Entrato</th><th>Prossimo controllo</th><th><span class="sr-solo">Azioni</span></th></tr></thead>
    <tbody>
    <?php if (!$dentro): ?><tr><td colspan="5"><?= $passo >= 0 ? 'Nessuno a questo passo adesso: scegli "Tutti i passi".' : 'Nessuno dentro adesso. Entra chi fa una delle cose degli inneschi mentre l\'automazione è Attiva.' ?></td></tr><?php endif; ?>
    <?php foreach ($dentro as $c): ?>
      <tr data-corso="<?= (int)$c['id'] ?>">
        <td><a href="newsletter-contatto.php?id=<?= (int)$c['iscritto_id'] ?>"><?= h((string)$c['email']) ?></a><?= (int)$c['giro'] > 1 ? ' <small>(giro ' . (int)$c['giro'] . ')</small>' : '' ?></td>
        <td><?= h($nomeNodo((int)$c['passo'])) ?><?= $c['ascolto'] ? ' <small>(' . h(['email' => 'aspetta una reazione', 'evento' => 'aspetta un evento', 'attesa' => 'in attesa', 'orario' => 'aspetta l\'orario dell\'email', 'modello' => 'fermo: il modello dell\'email non si può mandare, vedi il registro'][$c['ascolto']] ?? '') . ')</small>' : '' ?></td>
        <td><?= h(date('d/m/Y H:i', strtotime((string)$c['creato']))) ?></td>
        <td><?= $c['prossimo'] ? h(date('d/m/Y H:i', strtotime((string)$c['prossimo']))) : '' ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="azione" value="togli"><input type="hidden" name="corso" value="<?= (int)$c['id'] ?>"><button type="submit" class="btn btn-danger btn-sm" data-conferma="Togliere <?= h((string)$c['email']) ?> dall'automazione? Non riceve più niente da qui." data-conferma-ok="Togli il contatto" data-conferma-pericolo>Togli <span class="sr-solo"><?= h((string)$c['email']) ?></span></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($totDentro > 50): ?><p class="nl-riga"><?php if ($pag > 1): ?><a class="btn btn-secondary btn-sm" href="?id=<?= $id ?>&amp;scheda=attivita&amp;passo=<?= $passo >= 0 ? $passo : '' ?>&amp;pag=<?= $pag - 1 ?>">Pagina prima</a><?php endif; ?> <span class="hint">Pagina <?= $pag ?> di <?= (int)ceil($totDentro / 50) ?></span> <?php if ($pag * 50 < $totDentro): ?><a class="btn btn-secondary btn-sm" href="?id=<?= $id ?>&amp;scheda=attivita&amp;passo=<?= $passo >= 0 ? $passo : '' ?>&amp;pag=<?= $pag + 1 ?>">Pagina dopo</a><?php endif; ?></p><?php endif; ?>
</div>
<div class="form-card" id="fl-aggiungi">
  <h3>Aggiungi un contatto a mano</h3>
  <?php if ($haMano): ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="azione" value="aggiungi">
    <div class="form-group"><label for="fl-email-mano">Email del contatto</label><input type="email" id="fl-email-mano" name="email" required maxlength="190" placeholder="per esempio: anna@esempio.it"></div>
    <button type="submit" class="btn btn-primary">Aggiungi all'automazione</button>
  </form>
  <?php else: ?><p class="hint">Per aggiungere qualcuno a mano, nella tela apri la scheda Inneschi, aggiungi "Aggiunto a mano dal pannello" e salva.</p><?php endif; ?>
</div>
<div class="form-card" id="fl-registro">
  <h3>Registro</h3>
  <form method="get" class="fl-filtro"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="scheda" value="attivita">
    <div class="form-group"><label for="fl-cerca">Solo il contatto con l'email</label><input type="email" id="fl-cerca" name="email" value="<?= h($cerca) ?>" maxlength="190" placeholder="per esempio: anna@esempio.it"></div>
    <button type="submit" class="btn btn-secondary btn-sm">Cerca nel registro</button>
  </form>
  <p class="hint">Gli ultimi 200 fatti, il più recente in cima. Il registro tiene 90 giorni.</p>
  <table class="table">
    <thead><tr><th>Quando</th><th>Contatto</th><th>Passo</th><th>Cosa</th><th>Dettaglio</th></tr></thead>
    <tbody>
    <?php if (!$registro): ?><tr><td colspan="5"><?= $cerca !== '' ? 'Niente nel registro per ' . h($cerca) . ': controlla l\'email, o togli il filtro.' : 'Il registro è vuoto: si riempie quando qualcuno entra nell\'automazione.' ?></td></tr><?php endif; ?>
    <?php foreach ($registro as $x): ?>
      <tr data-evento="<?= h($x['evento']) ?>"><td><?= h(date('d/m/Y H:i', strtotime((string)$x['creato']))) ?></td><td><?= h((string)($x['email'] ?? '')) ?></td><td><?= h($nomeNodo((int)$x['nodo'])) ?></td><td><?= h($eventiNomi[$x['evento']] ?? $x['evento']) ?></td><td><?= h(newsletter_4byte_fuori((string)$x['dettaglio'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else:
if (!$f && $modello !== '') {
    [$mn, , $minn, $mg] = $modelli[$modello];
    $f = ['id' => 0, 'nome' => $mn, 'stato' => 'in_pausa', 'tag' => '', 'inneschi' => $minn, 'grafo' => newsletter_flusso_grafo_valido($mg), 'rientro' => 0, 'rientro_attesa' => 0, 'uscita' => ['disiscritto' => false, 'segmento' => 0], 'mittente_id' => 0];
}
$prossimo = (int)db()->query('SELECT COALESCE(MAX(id), 0) + 1 FROM ' . tbl('newsletter_flussi'))->fetchColumn();
$v = $f ?: ['id' => 0, 'nome' => 'Automazione n. ' . $prossimo, 'stato' => 'in_pausa', 'tag' => '', 'inneschi' => [['tipo' => 'iscrizione', 'id' => 0]], 'rientro' => 0, 'rientro_attesa' => 0, 'uscita' => ['disiscritto' => false, 'segmento' => 0], 'mittente_id' => 0, 'grafo' => ['nodi' => [
    ['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 60, 'dati' => []],
    ['id' => 1, 'tipo' => 'email', 'x' => 340, 'y' => 60, 'dati' => ['oggetto' => 'Benvenuto, {nome}', 'anteprima' => '', 'corpo' => '<p>Ciao {nome},</p><p>grazie per esserti iscritto.</p>', 'pulsante_testo' => '', 'pulsante_link' => '', 'ascolto' => 0]],
], 'archi' => [['da' => 0, 'uscita' => 'poi', 'a' => 1]]]];
$v['grafo'] = newsletter_flusso_grafo_valido($v['grafo']);
foreach ($v['grafo']['nodi'] as &$nn) {
    if ($nn['tipo'] === 'email') { $nn['dati']['corpo'] = newsletter_4byte_fuori($nn['dati']['corpo']); $nn['dati']['design'] = $nn['dati']['design'] !== '' ? 'si' : ''; $nn['dati']['html'] = $nn['dati']['html'] !== '' ? 'si' : ''; } // design e codice HTML restano sul server
    if ($nn['tipo'] === 'dividi') foreach ($nn['dati']['rami'] as &$rr) $rr['nome'] = newsletter_4byte_fuori($rr['nome']);
    unset($rr);
    if ($nn['tipo'] === 'attributo') $nn['dati']['valore'] = newsletter_4byte_fuori($nn['dati']['valore']);
}
unset($nn);
$numeri = $v['id'] ? nl_aut_numeri($v) : ['nodi' => [], 'tot' => ['entrati' => 0, 'attivo' => 0, 'finito' => 0, 'tolto' => 0, 'fermato' => 0]];
$attributi = [];
foreach (newsletter_attributi() as $n => $a) {
    if (in_array($a['categoria'], ['calculated', 'global'], true) || ((int)$a['sistema'] && !in_array($a['colonna'], ['nome', 'cognome'], true))) continue;
    $attributi[$n] = ['e' => newsletter_attributo_etichetta($a), 't' => $a['tipo'], 'o' => array_map(fn($x) => newsletter_4byte_fuori((string)$x), (array)$a['opzioni'])];
}
// 2.17.1 (Danilo: "devo poter scegliere l'email da un modello"): tutti i modelli, a blocchi e in codice HTML, anche quelli non attivi
// (segnati), tranne quelli dell'email di conferma. 2.18.0: il passo richiama il modello; qui anche anteprima (a) e dove aprirlo (u).
$modelliEmail = [];
if (function_exists('nd_modelli')) foreach (nd_modelli(false) as $_m) {
    if ($_m['tag'] === 'optin') continue;
    $_mh = $_m['editor'] === 'html';
    $modelliEmail[(string)$_m['id']] = ['n' => newsletter_4byte_fuori((string)$_m['nome']) . ((int)$_m['attivo'] ? '' : ' (non attivo)'), 't' => $_mh ? 'html' : 'blocchi', 'o' => newsletter_4byte_fuori((string)$_m['oggetto']), 'a' => newsletter_4byte_fuori((string)($_m['anteprima'] ?? '')),
        'u' => ($_mh ? 'newsletter-modelli.php?codice=' : 'newsletter-design.php?modello=') . (int)$_m['id'] . ($v['id'] ? '&flusso=' . (int)$v['id'] : '')];
}
unset($_m, $_mh);
$segmenti = function_exists('newsletter_segmenti') ? array_map(fn($x) => newsletter_4byte_fuori((string)$x), array_column(newsletter_segmenti(), 'nome', 'id')) : [];
$def = newsletter_regole_definizione();
$def['campi']['flusso_email'] = ['g' => 'Questa automazione', 'e' => 'Email di questa automazione', 't' => 'flusso_email'];
$flMitt = consegna_mittenti_scelta(); [$flPre] = posta_mittente();
$tot = $numeri['tot'];
$j = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
if ($v['id']): ?>
<nav class="imp-schede" aria-label="Automazione">
  <a href="newsletter-flussi.php?id=<?= (int)$v['id'] ?>" class="attiva" aria-current="page">Tela</a>
  <a href="newsletter-flussi.php?id=<?= (int)$v['id'] ?>&amp;scheda=attivita">Attività e registro</a>
</nav>
<?php endif; ?>
<form method="post" id="fl-form" class="fl-form">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="grafo" id="fl-grafo"><input type="hidden" name="inneschi" id="fl-inneschi-campo"><input type="hidden" name="nodo" id="fl-nodo-design" value="0"><input type="hidden" name="stacca" id="fl-stacca" value="-1">
  <div class="fl-testa">
    <label class="sr-solo" for="fl-nome">Nome dell'automazione</label>
    <input type="text" name="nome" id="fl-nome" class="fl-nome" value="<?= h($v['nome']) ?>" placeholder="per esempio: Benvenuto ai nuovi iscritti" maxlength="100" required>
    <span class="fl-campo"><label class="fl-et" for="fl-tag">Tag</label>
    <input type="text" name="tag" id="fl-tag" class="fl-tag" value="<?= h((string)$v['tag']) ?>" maxlength="255" placeholder="per esempio: clienti"></span>
    <span class="fl-campo"><label class="fl-et" for="fl-stato">Stato</label>
    <select name="stato" id="fl-stato" class="fl-stato" aria-describedby="fl-stato-aiuto"><?php foreach (NL_AUT_STATI as $k => $n): ?><option value="<?= $k ?>" <?= $v['stato'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></span>
    <span class="fl-spazio"></span>
    <button type="button" class="btn btn-secondary btn-sm" data-al-clic="FL.ordina()">Riordina i blocchi</button>
    <a class="btn btn-secondary btn-sm" href="newsletter-flussi.php">Elenco</a>
    <button type="submit" class="btn btn-primary btn-sm" name="azione" value="salva">Salva</button>
  </div>
  <p class="fl-aiuto-stato hint" id="fl-stato-aiuto">Attiva: entra chi fa una delle cose degli inneschi. In pausa: non entra nessuno di nuovo, chi è dentro prosegue. Inattiva: non entra nessuno e chi è dentro viene tolto.</p>
  <?php if ($v['id']): ?><p class="fl-numeri" id="fl-numeri">Entrati <b><?= $tot['entrati'] ?></b> · In corso <b><?= $tot['attivo'] ?></b> · Finiti <b><?= $tot['finito'] ?></b> · Usciti o tolti <b><?= $tot['tolto'] + $tot['fermato'] ?></b></p><?php endif; ?>
  <div class="fl-corpo">
    <aside class="fl-tavolozza" aria-label="Pannello dell'automazione">
      <div class="fl-schede" role="tablist" aria-label="Pannello dell'automazione">
        <button type="button" role="tab" id="fl-scheda-inneschi" aria-controls="fl-p-inneschi" aria-selected="true" tabindex="0">Inneschi</button>
        <button type="button" role="tab" id="fl-scheda-azioni" aria-controls="fl-p-azioni" aria-selected="false" tabindex="-1">Azioni</button>
        <button type="button" role="tab" id="fl-scheda-regole" aria-controls="fl-p-regole" aria-selected="false" tabindex="-1">Regole</button>
      </div>
      <section class="fl-vista" id="fl-p-inneschi" role="tabpanel" aria-labelledby="fl-scheda-inneschi">
        <p class="hint">Una persona entra quando fa almeno una di queste cose (fino a <?= NL_AUT_MAX_INNESCHI ?>).</p>
        <div id="fl-inneschi"></div>
        <button type="button" class="btn btn-secondary btn-sm" id="fl-innesco-piu">+ Aggiungi un innesco</button>
        <details class="fl-impostazioni" id="fl-impostazioni"<?= $v['rientro'] || $v['uscita']['disiscritto'] || $v['uscita']['segmento'] ? ' open' : '' ?>>
          <summary>Impostazioni</summary>
          <label class="fl-spunta"><input type="checkbox" name="rientro" value="1" id="fl-rientro" <?= $v['rientro'] ? 'checked' : '' ?>> Chi ha finito può rientrare</label>
          <div class="form-group"><label for="fl-rientro-ore">Dopo quante ore dalla fine</label><input type="number" id="fl-rientro-ore" name="rientro_attesa" min="1" max="8760" value="<?= max(1, (int)$v['rientro_attesa']) ?>" aria-describedby="fl-rientro-aiuto"></div>
          <p class="hint" id="fl-rientro-aiuto">Senza la spunta ognuno passa una volta sola. L'anniversario rientra comunque una volta l'anno.</p>
          <p class="fl-sottotitolo">Esce prima della fine quando</p>
          <label class="fl-spunta"><input type="checkbox" name="uscita_disiscritto" value="1" <?= $v['uscita']['disiscritto'] ? 'checked' : '' ?>> si disiscrive o va in lista nera</label>
          <div class="form-group"><label for="fl-uscita-seg">entra nel segmento</label><select id="fl-uscita-seg" name="uscita_segmento"><option value="0">Nessun segmento</option><?php foreach ($segmenti as $sid => $sn): ?><option value="<?= (int)$sid ?>" <?= (int)$v['uscita']['segmento'] === (int)$sid ? 'selected' : '' ?>><?= h($sn) ?></option><?php endforeach; ?></select></div>
          <p class="hint">Le condizioni si controllano a ogni giro del cron, anche durante un'attesa.</p>
          <div class="form-group"><label for="fl-mittente">Mittente delle email</label><select name="mittente_id" id="fl-mittente"><option value="0">Il predefinito<?= $flPre !== '' ? ' (' . h($flPre) . ')' : '' ?></option><?php foreach ($flMitt as $mid => $mn): ?><option value="<?= (int)$mid ?>" <?= (int)($v['mittente_id'] ?? 0) === (int)$mid ? 'selected' : '' ?>><?= h($mn) ?></option><?php endforeach; ?></select></div>
        </details>
        <?php if ($v['id']): ?><div class="fl-elimina-box"><button type="submit" class="btn btn-danger btn-sm" name="azione" value="elimina" formnovalidate data-conferma="Eliminare l'automazione? Chi è a metà non riceve più niente, e il registro si cancella." data-conferma-ok="Elimina l'automazione" data-conferma-pericolo>Elimina l'automazione</button></div><?php endif; ?>
      </section>
      <section class="fl-vista" id="fl-p-azioni" role="tabpanel" aria-labelledby="fl-scheda-azioni" hidden>
        <p class="hint">Fai clic su un'azione (o Invio): entra dopo il blocco scelto sulla tela, collegata. Oppure trascinala sulla tela.</p>
        <div class="fl-pezzo" data-tipo="email" role="button" tabindex="0"><b>&#9993;&#65039; Email</b><small>Manda un'email: mittente, oggetto, contenuto, orario</small></div>
        <div class="fl-pezzo" data-tipo="lista_aggiungi" role="button" tabindex="0"><b>&#10133; Aggiungi a una lista</b><small>Mette il contatto in una lista</small></div>
        <div class="fl-pezzo" data-tipo="lista_togli" role="button" tabindex="0"><b>&#10134; Togli da una lista</b><small>Toglie il contatto da una lista</small></div>
        <div class="fl-pezzo" data-tipo="attributo" role="button" tabindex="0"><b>&#9998; Aggiorna un attributo</b><small>Scrive un valore nei dati del contatto</small></div>
        <div class="fl-pezzo" data-tipo="webhook" role="button" tabindex="0"><b>&#8599; Webhook</b><small>Manda i dati del contatto a un altro servizio (https)</small></div>
      </section>
      <section class="fl-vista" id="fl-p-regole" role="tabpanel" aria-labelledby="fl-scheda-regole" hidden>
        <p class="hint">Fai clic su una regola (o Invio): entra dopo il blocco scelto sulla tela, collegata. Oppure trascinala sulla tela.</p>
        <div class="fl-pezzo" data-tipo="attesa" role="button" tabindex="0"><b>&#9203; Attesa</b><small>Aspetta minuti, ore o giorni (almeno 1 minuto)</small></div>
        <div class="fl-pezzo" data-tipo="evento" role="button" tabindex="0"><b>&#128065; Attendi un evento</b><small>Aspetta che apra o clicchi, al massimo per un tempo</small></div>
        <div class="fl-pezzo" data-tipo="bivio" role="button" tabindex="0"><b>&#128256; Sì o No</b><small>Due strade secondo una condizione</small></div>
        <div class="fl-pezzo" data-tipo="dividi" role="button" tabindex="0"><b>&#128256; Divisione condizionale</b><small>Più rami, ognuno con le sue condizioni, più "altrimenti"</small></div>
        <div class="fl-pezzo" data-tipo="percento" role="button" tabindex="0"><b>&#9878; Divisione in percentuale</b><small>Divide le persone in parti (per esempio 50 e 50)</small></div>
        <div class="fl-pezzo" data-tipo="fine" role="button" tabindex="0"><b>&#9209; Fine</b><small>Il cammino si chiude qui</small></div>
      </section>
    </aside>
    <div class="fl-tela-box" id="fl-tela-box">
      <div class="fl-tela" id="fl-tela">
        <svg class="fl-archi" id="fl-archi" aria-hidden="true"></svg>
      </div>
    </div>
    <aside class="fl-pannello" id="fl-pannello" aria-label="Il blocco scelto"><div class="fl-pannello-vuoto">Clicca un blocco per modificarlo.<br><br>Per cancellare un collegamento, clicca la linea e premi Canc.</div></aside>
  </div>
</form>
<script type="application/json" id="nls-def"><?= json_encode($def, $j) ?></script>
<script src="<?= pannello_file('assets/quill/quill.js') ?>"></script>
<script src="<?= pannello_file('assets/segmenti.js') ?>"></script>
<script<?= nonce_attr() ?>>
var FL_DATI = {
  grafo: <?= json_encode($v['grafo'], $j) ?>,
  inneschi: <?= json_encode($v['inneschi'], $j) ?>,
  nomiInneschi: <?= json_encode(nl_inneschi_nomi(), $j) ?>,
  cosa: <?= json_encode($inneschiCosa, $j) ?>,
  esiti: <?= json_encode($inneschiEsiti ?: new stdClass(), $j) ?>,
  condizioni: <?= json_encode(NL_CONDIZIONI, $j) ?>,
  uscite: <?= json_encode(NL_USCITE, $j) ?>,
  liste: <?= json_encode(array_map('strval', $nomiListe), $j) ?>,
  segmenti: <?= json_encode($segmenti ?: new stdClass(), $j) ?>,
  attributi: <?= json_encode($attributi ?: new stdClass(), $j) ?>,
  modelli: <?= json_encode($modelliEmail ?: new stdClass(), $j) ?>,
  mittenti: <?= json_encode(['0' => 'Quello dell\'automazione'] + array_map('strval', $flMitt), $j) ?>,
  numeri: <?= json_encode($numeri['nodi'] ?: new stdClass(), $j) ?>,
  max: {nodi: <?= NL_AUT_MAX_NODI ?>, inneschi: <?= NL_AUT_MAX_INNESCHI ?>, rami: <?= NL_AUT_MAX_RAMI ?>},
  nome: <?= json_encode((string)$v['nome'], $j) ?>,
  errore: <?= $errore !== '' ? 'true' : 'false' ?>,
  appenaSalvato: <?= $appenaSalvato ? 'true' : 'false' ?>
};
</script>
<script src="<?= pannello_file('assets/flussi.js') ?>"></script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
