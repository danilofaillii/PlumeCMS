<?php
/**
 * Flussi automatici della newsletter: un innesco e una tela dove si trascinano i blocchi (email, attesa, bivio, fine)
 * e si collegano le uscite. Il motore sta in includes/newsletter-flussi.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/newsletter.php';
require_once dirname(__DIR__) . '/includes/form.php';
require_admin(); // sicurezza (ruoli): solo amministratore
// Il battito della tela: ogni cinque minuti la pagina chiama se stessa così, e la sessione resta viva
// anche se il disegno di un flusso dura un'ora. Senza, al Salva si tornava al login e il lavoro si perdeva.
if (isset($_GET['ping'])) { http_response_code(204); exit; }
newsletter_schema();
$adminTitle = 'Flussi automatici';
$id = (int)($_GET['id'] ?? 0);
$nuovo = isset($_GET['nuovo']);
$errore = ''; $postato = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['azione'] ?? '') === 'elimina') { newsletter_flusso_elimina($id); flash('success', 'Flusso eliminato.'); header('Location: newsletter-flussi.php'); exit; }
        $id = newsletter_flusso_salva($id, $_POST);
        flash('success', !empty($_POST['attivo']) ? 'Flusso salvato e acceso: parte per chi arriva da adesso in poi.' : 'Flusso salvato, spento.');
        header('Location: newsletter-flussi.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        // Niente redirect: la pagina si ridisegna con quello che è arrivato dal form, così il disegno non si perde.
        $errore = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'Salvataggio non riuscito: ' . $e->getMessage();
        flash('error', $errore);
        $nuovo = !$id;
        try { $grafoPostato = newsletter_flusso_grafo_valido(newsletter_flusso_grafo_dal_form($_POST['grafo'] ?? [])); } catch (Throwable $x) { $grafoPostato = null; }
        $postato = ['id' => $id, 'nome' => mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 100), 'attivo' => !empty($_POST['attivo']) ? 1 : 0,
            'innesco' => isset(NL_INNESCHI[(string)($_POST['innesco'] ?? '')]) ? (string)$_POST['innesco'] : 'iscrizione', 'innesco_id' => (int)($_POST['innesco_id'] ?? 0), 'grafo' => $grafoPostato];
    }
}
$f = $id ? newsletter_flusso($id) : null;
if ($id && !$f) { http_response_code(404); exit('Flusso non trovato.'); }
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
$inneschiCosa = ['iscrizione' => [], 'lista' => array_column($liste, 'nome', 'id'), 'form' => array_column($forms, 'nome', 'id'), 'apertura' => array_column($campagne, 'oggetto', 'id'), 'clic' => array_column($campagne, 'oggetto', 'id')];
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/quill/quill.snow.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/css/flussi.css') ?>">
<?php if (!$f && !$nuovo): $tutti = newsletter_flussi(); ?>
<div class="ct-testa"><p style="color:var(--text-muted);font-size:.88rem;margin:0">Un flusso parte da un innesco (iscrizione, ingresso in una lista, form compilato, apertura o clic di una campagna, nessuna interazione da tanti giorni) e si disegna su una tela: blocchi Email, Attesa, Bivio e Fine collegati fra loro. Ogni email ha più uscite: subito dopo, se apre, se clicca, se non fa niente entro un tempo. Le email le manda il cron della newsletter, dentro il limite orario.</p><a href="newsletter-flussi.php?nuovo=1" class="btn btn-primary">+ Nuovo flusso</a></div>
<div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr><th>Flusso</th><th>Parte quando</th><th>Email</th><th>In corso</th><th>Finiti</th><th>Inviate</th><th>Stato</th></tr></thead>
    <tbody>
    <?php if (!$tutti): ?><tr><td colspan="7"><div class="empty-state"><p>Nessun flusso ancora.</p><a href="newsletter-flussi.php?nuovo=1" class="btn btn-primary">+ Nuovo flusso</a></div></td></tr><?php endif; ?>
    <?php foreach ($tutti as $x): ?>
      <tr class="ct-riga">
        <td class="ct-titolo"><a class="ct-nome" href="newsletter-flussi.php?id=<?= $x['id'] ?>"><?= h($x['nome']) ?></a><div class="ct-azioni"><a href="newsletter-flussi.php?id=<?= $x['id'] ?>">Modifica</a></div></td>
        <td class="ct-muto"><?= h(NL_INNESCHI[$x['innesco']] ?? $x['innesco']) ?><?= $x['innesco_id'] && isset($inneschiCosa[$x['innesco']][$x['innesco_id']]) ? ': ' . h($inneschiCosa[$x['innesco']][$x['innesco_id']]) : '' ?></td>
        <td class="ct-muto"><?= count(array_filter($x['grafo']['nodi'], fn($n) => $n['tipo'] === 'email')) ?></td>
        <td class="ct-muto"><?= (int)$x['in_corso'] ?></td>
        <td class="ct-muto"><?= (int)$x['finiti'] ?></td>
        <td class="ct-muto"><?= (int)$x['inviate'] ?></td>
        <td><span class="badge <?= $x['attivo'] ? 'badge-published' : 'badge-draft' ?>"><?= $x['attivo'] ? 'Acceso' : 'Spento' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else:
$v = $f ?: ['id' => 0, 'nome' => '', 'attivo' => 0, 'innesco' => 'iscrizione', 'innesco_id' => 0, 'grafo' => ['nodi' => [
    ['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 60, 'dati' => []],
    ['id' => 1, 'tipo' => 'email', 'x' => 340, 'y' => 60, 'dati' => ['oggetto' => 'Benvenuto, {nome}', 'anteprima' => '', 'corpo' => '<p>Ciao {nome},</p><p>grazie per esserti iscritto.</p>', 'pulsante_testo' => '', 'pulsante_link' => '', 'ascolto' => 0]],
], 'archi' => [['da' => 0, 'uscita' => 'poi', 'a' => 1]]]];
foreach ($v['grafo']['nodi'] as &$nn) if ($nn['tipo'] === 'email') $nn['dati']['corpo'] = newsletter_4byte_fuori($nn['dati']['corpo']); unset($nn);
$inCorso = $v['id'] ? db()->query('SELECT passo, COUNT(*) n FROM ' . tbl('newsletter_corsi') . " WHERE flusso_id = " . (int)$v['id'] . " AND stato = 'attivo' GROUP BY passo")->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$inviateNodo = $v['id'] ? db()->query('SELECT passo, COUNT(DISTINCT iscritto_id) n FROM ' . tbl('newsletter_eventi') . " WHERE flusso_id = " . (int)$v['id'] . " AND tipo = 'apertura' GROUP BY passo")->fetchAll(PDO::FETCH_KEY_PAIR) : [];
?>
<form method="post" id="fl-form" class="fl-form">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="grafo" id="fl-grafo">
  <div class="fl-testa">
    <input type="text" name="nome" class="fl-nome" value="<?= h($v['nome']) ?>" placeholder="Nome del flusso" required>
    <label class="fl-acceso"><input type="checkbox" name="attivo" value="1" <?= $v['attivo'] ? 'checked' : '' ?>> Acceso</label>
    <span class="fl-spazio"></span>
    <button type="button" class="btn btn-secondary btn-sm" data-al-clic="FL.ordina()">Riordina i blocchi</button>
    <a class="btn btn-secondary btn-sm" href="newsletter-flussi.php">Elenco</a>
    <button type="submit" class="btn btn-primary btn-sm">Salva</button>
  </div>
  <div class="fl-corpo">
    <aside class="fl-tavolozza">
      <h4>Blocchi</h4>
      <p class="hint">Trascinali sulla tela, o clicca per aggiungerli. Poi tira dal pallino di un'uscita fino a un altro blocco per collegarli.</p>
      <div class="fl-pezzo" data-tipo="email" draggable="false"><b>✉️ Email</b><small>Manda un'email. Uscite: subito dopo, se apre, se clicca, se non fa niente</small></div>
      <div class="fl-pezzo" data-tipo="attesa"><b>⏳ Attesa</b><small>Aspetta minuti, ore o giorni</small></div>
      <div class="fl-pezzo" data-tipo="bivio"><b>🔀 Bivio</b><small>Sì o No secondo una condizione</small></div>
      <div class="fl-pezzo" data-tipo="fine"><b>⏹ Fine</b><small>Il cammino si chiude qui</small></div>
      <h4 style="margin-top:1rem">Parte quando</h4>
      <select name="innesco" id="fl-innesco"><?php foreach (NL_INNESCHI as $k => $n): ?><option value="<?= $k ?>" <?= $v['innesco'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
      <div id="fl-cosa-box"><label id="fl-cosa-label" class="fl-mini">Quale</label><select name="innesco_id" id="fl-cosa"></select><input type="number" id="fl-cosa-n" min="1" max="3650" class="fl-n" placeholder="giorni" style="display:none"></div>
      <?php if ($v['id']): ?><div style="margin-top:1.2rem;text-align:right"><button type="submit" class="sc-cestina" name="azione" value="elimina" formnovalidate data-conferma="Eliminare il flusso? Chi è a metà non riceve più niente.">Elimina il flusso</button></div><?php endif; ?>
    </aside>
    <div class="fl-tela-box" id="fl-tela-box">
      <div class="fl-tela" id="fl-tela">
        <svg class="fl-archi" id="fl-archi"></svg>
      </div>
    </div>
    <aside class="fl-pannello" id="fl-pannello"><div class="fl-pannello-vuoto">Clicca un blocco per modificarlo.<br><br>Per cancellare un collegamento, clicca la linea e premi Canc.</div></aside>
  </div>
</form>
<script src="<?= pannello_file('assets/quill/quill.js') ?>"></script>
<script<?= nonce_attr() ?>>
var FL_DATI = {
  grafo: <?= json_encode($v['grafo'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  innescoId: <?= (int)$v['innesco_id'] ?>,
  cosa: <?= json_encode($inneschiCosa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  condizioni: <?= json_encode(NL_CONDIZIONI, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  uscite: <?= json_encode(NL_USCITE, JSON_UNESCAPED_UNICODE) ?>,
  liste: <?= json_encode(array_map('strval', array_column($liste, 'nome', 'id')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  inCorso: <?= json_encode(array_map('intval', $inCorso)) ?>,
  aperture: <?= json_encode(array_map('intval', $inviateNodo)) ?>,
  nome: <?= json_encode((string)$v['nome'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  attivo: <?= (int)$v['attivo'] ?>,
  innesco: <?= json_encode((string)$v['innesco']) ?>,
  errore: <?= $errore !== '' ? 'true' : 'false' ?>,
  appenaSalvato: <?= $appenaSalvato ? 'true' : 'false' ?>
};
</script>
<script src="<?= pannello_file('assets/flussi.js') ?>"></script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
