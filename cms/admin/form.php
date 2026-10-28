<?php
/**
 * Form: elenco e scheda. I campi si costruiscono qui (tipo, etichetta, obbligatorio, opzioni) e il form si mette
 * in pagina con il widget Modulo dell'editor visuale, oppure dentro un popup.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/form.php';
require_admin(); // sicurezza (ruoli): solo amministratore
$adminTitle = 'Form';
$id = (int)($_GET['id'] ?? 0);
$nuovo = isset($_GET['nuovo']);

$daPost = null; // con un errore di salvataggio la scheda si ridisegna con quello che era stato scritto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['azione'] ?? '') === 'elimina') { form_elimina($id); flash('success', 'Form eliminato, con le sue compilazioni.'); header('Location: form.php'); exit; }
        $id = form_salva($id, $_POST);
        flash('success', 'Form salvato. Lo metti in pagina con il widget Modulo dell\'editor visuale, scegliendo «Form: ' . h((string)($_POST['nome'] ?? '')) . '».');
        header('Location: form.php?id=' . $id);
        exit;
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        $campi = json_decode((string)($_POST['campi'] ?? ''), true);
        $daPost = ['id' => $id] + array_map(fn($x) => is_string($x) ? $x : '', $_POST) + ['nome' => '', 'slug' => '', 'titolo' => '', 'intro' => '', 'pulsante' => 'Invia', 'consenso' => '', 'grazie_titolo' => '', 'grazie_testo' => '', 'grazie_link' => ''];
        $daPost['campi'] = is_array($campi) ? array_values(array_filter($campi, 'is_array')) : [];
        foreach (['lista_id' => 0, 'lista_conferma' => 0, 'avviso' => 0, 'attivo' => 0] as $k => $z) $daPost[$k] = (int)($_POST[$k] ?? $z);
        $nuovo = !$id;
    }
}

$liste = newsletter_liste();
$f = $daPost ?: ($id ? form_leggi($id) : null);
if ($id && !$f) { http_response_code(404); exit('Form non trovato.'); }
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<?php if (!$f && !$nuovo): $tutti = form_tutti(); ?>
<div class="ct-testa"><p style="color:var(--text-muted);font-size:.88rem;margin:0">Un form raccoglie i campi che decidi tu. Le compilazioni finiscono in <a href="form-submissions.php">Invii dei form</a>, e se vuoi la persona entra in una lista della newsletter.</p><a href="form.php?nuovo=1" class="btn btn-primary">+ Nuovo form</a></div>
<div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr><th>Form</th><th>Campi</th><th>Dopo l'invio</th><th>Compilazioni</th><th>Stato</th></tr></thead>
    <tbody>
    <?php if (!$tutti): ?><tr><td colspan="5"><div class="empty-state"><p>Nessun form ancora.</p><a href="form.php?nuovo=1" class="btn btn-primary">+ Nuovo form</a></div></td></tr><?php endif; ?>
    <?php foreach ($tutti as $m): $campi = json_decode((string)$m['campi'], true) ?: []; ?>
      <tr class="ct-riga">
        <td class="ct-titolo"><a class="ct-nome" href="form.php?id=<?= $m['id'] ?>"><?= h($m['nome']) ?></a><div class="ct-indirizzo">widget Modulo → «Form: <?= h($m['nome']) ?>»</div>
          <div class="ct-azioni"><a href="form.php?id=<?= $m['id'] ?>">Modifica</a><a href="form-submissions.php?modulo=<?= $m['id'] ?>">Compilazioni</a></div></td>
        <td class="ct-muto"><?= h(implode(', ', array_map(fn($c) => $c['etichetta'] ?? '', $campi))) ?></td>
        <td class="ct-muto"><?= $m['lista_id'] ? 'entra nella lista «' . h(array_values(array_filter($liste, fn($l) => (int)$l['id'] === (int)$m['lista_id']))[0]['nome'] ?? '?') . '»' : 'nessuna lista' ?><?= $m['avviso'] ? ', avviso via email' : '' ?></td>
        <td class="ct-muto"><?= (int)$m['invii'] ?><?= $m['nuovi'] ? ' <span class="badge badge-scheduled">' . (int)$m['nuovi'] . ' nuove</span>' : '' ?></td>
        <td><span class="badge <?= $m['attivo'] ? 'badge-published' : 'badge-draft' ?>"><?= $m['attivo'] ? 'Attivo' : 'Spento' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: $v = $f ?: ['id' => 0, 'nome' => '', 'slug' => '', 'titolo' => '', 'intro' => '', 'campi' => [['nome' => 'nome', 'etichetta' => 'Nome', 'tipo' => 'testo', 'obbligatorio' => true, 'segnaposto' => '', 'opzioni' => [], 'meta' => 'nome'], ['nome' => 'email', 'etichetta' => 'Email', 'tipo' => 'email', 'obbligatorio' => true, 'segnaposto' => '', 'opzioni' => [], 'meta' => 'email'], ['nome' => 'messaggio', 'etichetta' => 'Messaggio', 'tipo' => 'testolungo', 'obbligatorio' => false, 'segnaposto' => '', 'opzioni' => [], 'meta' => '']], 'pulsante' => 'Invia', 'consenso' => '', 'lista_id' => 0, 'lista_conferma' => 1, 'avviso' => 1, 'grazie_titolo' => 'Grazie, ricevuto.', 'grazie_testo' => 'Ti rispondo appena posso.', 'grazie_link' => '', 'attivo' => 1]; ?>
<form method="post" id="fm-form">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="campi" id="fm-campi-json">
  <div class="sc-griglia">
    <div>
      <div class="sc-scatola"><h3>Il form</h3><div class="sc-corpo">
        <div class="sc-riga"><div><label>Nome (nel pannello)</label><input type="text" name="nome" value="<?= h($v['nome']) ?>" required></div><div><label>Nome breve</label><input type="text" name="slug" value="<?= h($v['slug']) ?>" placeholder="dal nome"></div></div>
        <label>Titolo mostrato sopra il form</label><input type="text" name="titolo" value="<?= h($v['titolo']) ?>">
        <label>Testo di introduzione</label><textarea name="intro" rows="2"><?= h($v['intro']) ?></textarea>
        <label>Testo del pulsante</label><input type="text" name="pulsante" value="<?= h($v['pulsante']) ?>">
      </div></div>
      <div class="sc-scatola"><h3>Campi</h3><div class="sc-corpo">
        <div id="fm-campi"></div>
        <button type="button" class="btn btn-secondary btn-sm" data-al-clic="fmAggiungi()">+ Aggiungi un campo</button>
        <div class="hint">Trascina ⋮⋮ per cambiare l'ordine. Un campo di tipo Email serve per iscrivere alla newsletter e per risponderti. Nelle scelte, scrivi le opzioni una per riga.</div>
      </div></div>
      <div class="sc-scatola"><h3>Consenso</h3><div class="sc-corpo">
        <label>Testo della spunta privacy (vuoto = quello standard)</label><textarea name="consenso" rows="2"><?= h($v['consenso']) ?></textarea>
      </div></div>
    </div>
    <aside>
      <div class="sc-scatola"><h3>Stato</h3><div class="sc-corpo">
        <label class="ed-spunta" style="display:flex;gap:.5rem;align-items:center;font-weight:400"><input type="checkbox" name="attivo" value="1" <?= $v['attivo'] ? 'checked' : '' ?>> Attivo (riceve compilazioni)</label>
        <div style="display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap"><button type="submit" class="btn btn-primary">Salva</button><a class="btn btn-secondary" href="form.php">Elenco</a></div>
        <?php if ($v['id']): ?><div style="margin-top:.6rem;text-align:right"><button type="submit" class="sc-cestina" name="azione" value="elimina" data-conferma="Eliminare il form e tutte le sue compilazioni?">Elimina il form</button></div><?php endif; ?>
      </div></div>
      <div class="sc-scatola"><h3>Dopo l'invio</h3><div class="sc-corpo">
        <label>Iscrivi alla lista della newsletter</label>
        <select name="lista_id"><option value="0">Nessuna</option><?php foreach ($liste as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$v['lista_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= h($l['nome']) ?></option><?php endforeach; ?></select>
        <label class="ed-spunta" style="display:flex;gap:.5rem;align-items:center;font-weight:400;margin-top:.5rem"><input type="checkbox" name="lista_conferma" value="1" <?= $v['lista_conferma'] ? 'checked' : '' ?>> Con email di conferma (doppio consenso)</label>
        <label class="ed-spunta" style="display:flex;gap:.5rem;align-items:center;font-weight:400"><input type="checkbox" name="avviso" value="1" <?= $v['avviso'] ? 'checked' : '' ?>> Avvisami via email a ogni compilazione</label>
        <label>Titolo della pagina di ringraziamento</label><input type="text" name="grazie_titolo" value="<?= h($v['grazie_titolo']) ?>">
        <label>Testo</label><textarea name="grazie_testo" rows="3"><?= h($v['grazie_testo']) ?></textarea>
        <label>Link del pulsante "Continua" (facoltativo)</label><input type="text" name="grazie_link" value="<?= h($v['grazie_link']) ?>" placeholder="/grazie/">
      </div></div>
    </aside>
  </div>
</form>
<script src="<?= pannello_file('assets/editor/Sortable.min.js') ?>"></script>
<script<?= nonce_attr() ?>>
var fmTipi = <?= json_encode(FORM_TIPI_CAMPO, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var fmCampi = <?= json_encode($v['campi'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
// Le opzioni di una scelta possono arrivare come elenco (dal database) o come testo (appena scritto): sempre testo, una per riga.
function fmOpzioni(c) {
  if (Array.isArray(c.opzioni)) return c.opzioni.join('\n');
  return String(c.opzioni == null ? '' : c.opzioni);
}
function fmScelta(tipo) { return tipo === 'scelta' || tipo === 'multiscelta'; }
function fmDisegna() {
  var c = document.getElementById('fm-campi'); c.innerHTML = '';
  fmCampi.forEach(function (campo, i) {
    if (!campo || typeof campo !== 'object') return;
    var d = document.createElement('div'); d.className = 'fm-campo';
    d.innerHTML = '<div class="fm-riga"><span class="fm-maniglia" title="Trascina">⋮⋮</span>'
      + '<input type="text" class="fm-etichetta" placeholder="Etichetta" value="' + fmE(campo.etichetta) + '" data-al-input="fmImposta(' + i + ', \'etichetta\', this.value)">'
      + '<select class="fm-tipo" data-al-cambio="fmImposta(' + i + ', \'tipo\', this.value, true)">' + Object.keys(fmTipi).map(function (k) { return '<option value="' + k + '"' + (campo.tipo === k ? ' selected' : '') + '>' + fmTipi[k] + '</option>'; }).join('') + '</select>'
      + '<label class="fm-obb"><input type="checkbox"' + (campo.obbligatorio ? ' checked' : '') + ' data-al-cambio="fmImposta(' + i + ', \'obbligatorio\', this.checked)"> obbligatorio</label>'
      + '<button type="button" class="fm-via" title="Togli" data-al-clic="fmTogli(' + i + ')">×</button></div>'
      + '<div class="fm-riga fm-riga2">'
      + (fmScelta(campo.tipo) ? '<textarea class="fm-opzioni" rows="3" placeholder="Opzioni tra cui scegliere: una per riga" data-al-input="fmImposta(' + i + ', \'opzioni\', this.value)">' + fmE(fmOpzioni(campo)) + '</textarea>'
        : (campo.tipo === 'spunta' || campo.tipo === 'data' ? '' : '<input type="text" class="fm-segnaposto" placeholder="Suggerimento dentro il campo (facoltativo)" value="' + fmE(campo.segnaposto) + '" data-al-input="fmImposta(' + i + ', \'segnaposto\', this.value)">'))
      + (['testo', 'email', 'telefono'].indexOf(campo.tipo) >= 0 ? '<select class="fm-meta" data-al-cambio="fmImposta(' + i + ', \'meta\', this.value)" title="A cosa serve questo campo"><option value="">(dato generico)</option><option value="nome"' + (campo.meta === 'nome' ? ' selected' : '') + '>è il nome</option><option value="cognome"' + (campo.meta === 'cognome' ? ' selected' : '') + '>è il cognome</option><option value="email"' + (campo.meta === 'email' ? ' selected' : '') + '>è l\'email</option><option value="cellulare"' + (campo.meta === 'cellulare' ? ' selected' : '') + '>è il cellulare</option></select>' : '')
      + '</div>';
    c.appendChild(d);
  });
}
function fmE(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
// I campi del costruttore senza gestori in linea (CSP): pannello.js chiama queste due funzioni dagli attributi data-al-*.
function fmImposta(i, k, v, ridisegna) { if (!fmCampi[i]) return; fmCampi[i][k] = v; if (ridisegna) fmDisegna(); }
function fmTogli(i) { fmCampi.splice(i, 1); fmDisegna(); }
function fmAggiungi() { fmCampi.push({ nome: '', etichetta: 'Nuovo campo', tipo: 'testo', obbligatorio: false, segnaposto: '', opzioni: [], meta: '' }); fmDisegna(); var u = document.querySelectorAll('.fm-etichetta'); if (u.length) { u[u.length - 1].focus(); u[u.length - 1].select(); } }
fmDisegna();
new Sortable(document.getElementById('fm-campi'), { handle: '.fm-maniglia', animation: 120, onEnd: function (e) { if (e.oldIndex === e.newIndex) return; var v = fmCampi.splice(e.oldIndex, 1)[0]; fmCampi.splice(e.newIndex, 0, v); fmDisegna(); } });
document.getElementById('fm-form').addEventListener('submit', function () {
  document.getElementById('fm-campi-json').value = JSON.stringify(fmCampi.map(function (c) { return Object.assign({}, c, { opzioni: fmOpzioni(c) }); }));
});
</script>
<style>
.fm-campo{border:1px solid var(--border);border-radius:8px;padding:.5rem .6rem;margin-bottom:.5rem;background:var(--fondo-2)}
.fm-riga{display:flex;gap:.5rem;align-items:center}.fm-riga2{margin-top:.4rem;padding-left:1.4rem}
.fm-riga input[type=text]{flex:1;padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font:inherit;font-size:.85rem}
.fm-riga select{padding:.4rem .5rem;border:1px solid var(--border);border-radius:6px;font:inherit;font-size:.82rem}
.fm-riga textarea.fm-opzioni{flex:1;min-height:0;padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font:inherit;font-size:.85rem;resize:vertical}
.fm-obb{font-size:.78rem;color:var(--text-muted);display:flex;gap:.3rem;align-items:center;white-space:nowrap;margin:0}
.fm-maniglia{cursor:grab;color:var(--text-muted)}.fm-via{border:0;background:none;color:var(--errore-testo);font-size:1.1rem;cursor:pointer}
</style>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
