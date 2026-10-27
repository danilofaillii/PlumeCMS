<?php
/**
 * Header e footer (Theme Builder): l'elenco dei modelli per tipo, la stellina del predefinito, le condizioni,
 * duplica, rinomina, cestino. "+ Aggiungi" crea un modello in bozza e apre l'editor visuale (editor.php?tipo=modelli&id=N).
 *   modelli.php?tipo=header | footer
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/modelli.php';
require_once dirname(__DIR__) . '/includes/editor.php'; // editor_puo_pubblicare (Chi pubblica)
require_admin(); // sicurezza (ruoli): header e footer sono dell'amministratore
modelli_installa();

$tipo = ($_GET['tipo'] ?? 'header') === 'footer' ? 'footer' : 'header';
$adminTitle = 'Header e footer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $tipoPost = ($_POST['tipo'] ?? $tipo) === 'footer' ? 'footer' : 'header';
    try {
        switch ($_POST['azione'] ?? '') {
            case 'crea':
                $nuovo = modelli_crea($tipoPost, (string)($_POST['nome'] ?? ''));
                header('Location: editor.php?tipo=modelli&id=' . $nuovo);
                exit;
            case 'duplica':
                $nuovo = modelli_duplica($id);
                flash('success', 'Copia creata, in bozza: aprila con Modifica e pubblicala quando è pronta.');
                break;
            case 'rinomina':
                modelli_rinomina($id, (string)($_POST['nome'] ?? ''));
                flash('success', 'Nome cambiato.');
                break;
            case 'predefinito':
                if (!editor_puo_pubblicare()) throw new RuntimeException('Solo chi pubblica dall\'editor (Impostazioni dell\'editor, "Chi pubblica") cambia il modello predefinito.');
                modelli_predefinito($id);
                flash('success', 'Da adesso è il ' . MODELLI_TIPI[$tipoPost] . ' predefinito: esce su tutte le pagine che non hanno una scelta diversa.');
                break;
            case 'pubblica':
                if (!editor_puo_pubblicare()) throw new RuntimeException('Solo chi pubblica dall\'editor (Impostazioni dell\'editor, "Chi pubblica") pubblica un modello.');
                modelli_stato($id, true);
                flash('success', 'Modello pubblicato.');
                break;
            case 'bozza':
                modelli_stato($id, false);
                flash('success', 'Modello messo in bozza: non esce più sul sito.');
                break;
            case 'condizioni':
                $c = json_decode((string)($_POST['condizioni'] ?? '[]'), true);
                modelli_condizioni_salva($id, is_array($c) ? $c : [], (int)($_POST['priorita'] ?? 0));
                flash('success', 'Condizioni salvate.');
                break;
            case 'cestina':
                modelli_cestina($id);
                flash('success', 'Modello spostato nel cestino.');
                break;
            case 'ripristina':
                modelli_ripristina($id);
                flash('success', 'Modello ripristinato.');
                break;
            case 'elimina':
                modelli_elimina($id);
                flash('success', 'Modello eliminato per sempre.');
                break;
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: modelli.php?tipo=' . $tipoPost . (isset($_POST['cestino']) ? '&cestino=1' : ''));
    exit;
}

$cestino = !empty($_GET['cestino']);
$tutti = array_values(array_filter(modelli_tutti(), fn($m) => $m['tipo'] === $tipo && (bool)$m['cestino'] === $cestino));
$nelCestino = count(array_filter(modelli_tutti(), fn($m) => $m['tipo'] === $tipo && $m['cestino']));
$conteggi = ['header' => 0, 'footer' => 0];
foreach (modelli_tutti() as $m) if (!$m['cestino']) $conteggi[$m['tipo']]++;

// Quanti contenuti hanno scelto ogni modello (header_id / footer_id = id).
$inUso = [];
require_once dirname(__DIR__) . '/includes/contenuti.php';
foreach (array_combine(array_column(array_values(contenuti_tipi()), 'tabella'), array_keys(contenuti_tipi())) as $tab => $nome) {
    if (!sito_colonna_esiste($tab, $tipo . '_id')) continue;
    foreach (db()->query('SELECT `' . $tipo . '_id` AS m, COUNT(*) AS n FROM ' . tbl($tab) . ' WHERE cestino IS NULL AND `' . $tipo . '_id` > 0 GROUP BY `' . $tipo . '_id`')->fetchAll() as $r) {
        $inUso[(int)$r['m']][$nome] = (int)$r['n'];
    }
}
// Per il dialogo delle condizioni: categorie e contenuti.
$categorie = db()->query('SELECT slug, name FROM ' . tbl('categories') . ' ORDER BY name')->fetchAll();
$contenuti = [];
foreach (contenuti_tipi() as $k => $def) {
    foreach (db()->query('SELECT id, title FROM ' . tbl($def['tabella']) . " WHERE cestino IS NULL AND status = 'published' ORDER BY title LIMIT 600")->fetchAll() as $r) $contenuti[] = ['rif' => $k . ':' . $r['id'], 'nome' => $r['title'] . ' (' . $def['uno'] . ')'];
}
$csrf = csrf_token();
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="assets/css/contenuti.css">
<style>
.md-schede{display:flex;gap:.25rem;border-bottom:1px solid var(--border);margin-bottom:1rem}
.md-schede a{padding:.6rem 1rem;text-decoration:none;color:var(--text-muted);border-bottom:2px solid transparent;font-weight:600}
.md-schede a.attiva{color:var(--text);border-bottom-color:var(--coral)}
.md-stella{background:none;border:0;cursor:pointer;font-size:1.3rem;line-height:1;color:#cbd5e1;padding:0 .2rem}
.md-stella.on{color:#f59e0b}
.md-azioni form{display:inline}
.md-cond{font-size:.82rem;color:var(--text-muted)}
dialog.md-dialogo{border:1px solid var(--border);border-radius:12px;padding:1.25rem;max-width:720px;width:95vw}
dialog.md-dialogo::backdrop{background:rgba(0,0,0,.4)}
.md-regola{display:grid;grid-template-columns:110px 1fr 1fr 32px;gap:.4rem;align-items:center;margin-bottom:.4rem}
.md-regola select,.md-regola input{width:100%}
.md-regola button{border:0;background:#fde8e3;color:#b91c1c;border-radius:6px;height:30px;cursor:pointer}
.md-aiuto{font-size:.82rem;color:var(--text-muted)}
</style>

<div class="md-schede">
  <a href="modelli.php?tipo=header" class="<?= $tipo === 'header' ? 'attiva' : '' ?>">Header (<?= $conteggi['header'] ?>)</a>
  <a href="modelli.php?tipo=footer" class="<?= $tipo === 'footer' ? 'attiva' : '' ?>">Footer (<?= $conteggi['footer'] ?>)</a>
</div>

<div class="ct-testa">
  <p style="color:var(--text-muted);font-size:.88rem;margin:0;max-width:720px">
    Puoi avere più <?= $tipo ?>: quello con la stellina è il predefinito ed esce dappertutto. Con le <b>Condizioni</b> un modello esce solo dove dici tu
    (tutti gli articoli, una categoria, una pagina precisa). Ogni articolo o pagina può poi scegliere il suo nella propria scheda.
    <?php if ($nelCestino): ?> · <a href="modelli.php?tipo=<?= $tipo ?>&amp;cestino=1">Cestino (<?= $nelCestino ?>)</a><?php endif; ?>
    <?php if ($cestino): ?> · <a href="modelli.php?tipo=<?= $tipo ?>">Torna all'elenco</a><?php endif; ?>
  </p>
  <form method="post" style="display:flex;gap:.4rem;align-items:center">
    <?= csrf_field() ?><input type="hidden" name="azione" value="crea"><input type="hidden" name="tipo" value="<?= $tipo ?>">
    <a href="versioni.php?ambito=modello" class="btn btn-secondary" title="Tutte le versioni di header e footer: confronta, anteprima, ripristina">Cronologia</a><!-- versioni -->
    <input type="text" name="nome" placeholder="Nome del nuovo <?= $tipo ?>" style="min-width:200px">
    <button type="submit" class="btn btn-primary">+ Aggiungi <?= $tipo ?></button>
  </form>
</div>

<div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr><th style="width:40px"></th><th>Nome</th><th>Stato</th><th>Esce su</th><th>In uso su</th><th>Aggiornato</th></tr></thead>
    <tbody>
    <?php if (!$tutti): ?><tr><td colspan="6"><div class="empty-state"><p><?= $cestino ? 'Il cestino è vuoto.' : 'Nessun ' . $tipo . ' ancora.' ?></p></div></td></tr><?php endif; ?>
    <?php foreach ($tutti as $m): $uso = $inUso[(int)$m['id']] ?? []; ?>
      <tr class="ct-riga">
        <td>
          <?php if (!$cestino): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="predefinito"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tipo" value="<?= $tipo ?>">
            <button type="submit" class="md-stella <?= $m['predefinito'] ? 'on' : '' ?>" title="<?= $m['predefinito'] ? 'È il ' . $tipo . ' predefinito' : 'Imposta come ' . $tipo . ' predefinito' ?>" <?= $m['predefinito'] ? 'disabled' : '' ?>>&#9733;</button></form>
          <?php endif; ?>
        </td>
        <td class="ct-titolo">
          <a class="ct-nome" href="editor.php?tipo=modelli&amp;id=<?= $m['id'] ?>"><?= h($m['nome']) ?></a><?= $m['predefinito'] ? ' <span class="badge badge-pillar">predefinito</span>' : '' ?>
          <div class="ct-azioni md-azioni">
            <?php if (!$cestino): ?>
            <a href="editor.php?tipo=modelli&amp;id=<?= $m['id'] ?>">Modifica</a>
            <a href="../?preview=1&amp;<?= $tipo ?>=<?= $m['id'] ?>" target="_blank">Anteprima sul sito</a>
            <a href="#" data-al-clic="mdCondizioni(<?= $m['id'] ?>, <?= h(json_encode($m['condizioni'])) ?>, <?= (int)$m['priorita'] ?>, <?= h(json_encode($m['nome'])) ?>);return false">Condizioni</a>
            <a href="#" data-al-clic="mdRinomina(<?= $m['id'] ?>, <?= h(json_encode($m['nome'])) ?>);return false">Rinomina</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="duplica"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tipo" value="<?= $tipo ?>"><button type="submit" class="ct-link">Duplica</button></form>
            <?php if ($m['status'] === 'published'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="bozza"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tipo" value="<?= $tipo ?>"><button type="submit" class="ct-link" <?= $m['predefinito'] ? 'disabled title="Il predefinito resta pubblicato"' : '' ?>>Metti in bozza</button></form>
            <?php else: ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="pubblica"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tipo" value="<?= $tipo ?>"><button type="submit" class="ct-link">Pubblica</button></form>
            <?php endif; ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="cestina"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tipo" value="<?= $tipo ?>"><button type="submit" class="ct-link ct-rosso" <?= $m['predefinito'] ? 'disabled title="Prima scegli un altro predefinito"' : '' ?>>Cestino</button></form>
            <?php else: ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="ripristina"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tipo" value="<?= $tipo ?>"><input type="hidden" name="cestino" value="1"><button type="submit" class="ct-link">Ripristina</button></form>
            <form method="post" data-conferma="Eliminare per sempre? I contenuti che lo usavano tornano al predefinito."><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tipo" value="<?= $tipo ?>"><input type="hidden" name="cestino" value="1"><button type="submit" class="ct-link ct-rosso">Elimina per sempre</button></form>
            <?php endif; ?>
          </div>
        </td>
        <td><span class="badge <?= $m['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>"><?= $m['status'] === 'published' ? 'Pubblicato' : 'Bozza' ?></span><?= $m['con_bozza'] && $m['pubblicata'] ? ' <span class="badge badge-scheduled" title="Nell\'editor c\'è una bozza non ancora pubblicata">bozza in sospeso</span>' : '' ?><?= !$m['pubblicata'] && $m['status'] === 'published' ? ' <span class="badge badge-scheduled" title="Pubblica dall\'editor per farlo uscire">vuoto</span>' : '' ?></td>
        <td class="md-cond"><?= $m['condizioni'] ? h(modelli_condizioni_testo($m)) . ($m['priorita'] ? ' · priorità ' . (int)$m['priorita'] : '') : ($m['predefinito'] ? 'Tutto il sito (predefinito)' : 'Solo dove viene scelto') ?></td>
        <td class="ct-muto"><?= $uso ? h(implode(', ', array_map(fn($k, $n) => "$n $k", array_keys($uso), $uso))) : '–' ?></td>
        <td class="ct-muto"><?= h(substr((string)($m['struttura_aggiornata'] ?: $m['updated_at']), 0, 16)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<dialog id="md-dialogo" class="md-dialogo">
  <form method="post" id="md-form-cond">
    <?= csrf_field() ?><input type="hidden" name="azione" value="condizioni"><input type="hidden" name="id" id="md-cond-id"><input type="hidden" name="tipo" value="<?= $tipo ?>"><input type="hidden" name="condizioni" id="md-cond-json">
    <h2 style="margin:0 0 .3rem;font-size:1.1rem">Condizioni: <span id="md-cond-nome"></span></h2>
    <p class="md-aiuto">Dove esce questo <?= $tipo ?>. Vince il modello con la condizione più precisa (un contenuto batte una categoria, che batte "tutti gli articoli", che batte "tutto il sito"). A parità vince la priorità più alta. "Escludi" lo toglie da quelle pagine.</p>
    <div id="md-regole"></div>
    <button type="button" class="btn btn-secondary btn-sm" data-al-clic="mdAggiungiRegola()">+ Aggiungi una condizione</button>
    <p style="margin:1rem 0 .5rem"><label>Priorità (da -99 a 99, 0 normale) <input type="number" name="priorita" id="md-priorita" min="-99" max="99" value="0" style="width:90px"></label></p>
    <div style="display:flex;justify-content:flex-end;gap:.5rem"><button type="button" class="btn btn-secondary" data-chiudi-dialogo="#md-dialogo">Annulla</button><button type="submit" class="btn btn-primary">Salva le condizioni</button></div>
  </form>
</dialog>
<form method="post" id="md-form-rinomina" hidden><?= csrf_field() ?><input type="hidden" name="azione" value="rinomina"><input type="hidden" name="id"><input type="hidden" name="nome"><input type="hidden" name="tipo" value="<?= $tipo ?>"></form>

<script<?= nonce_attr() ?>>
var MD_DOVE = <?= json_encode(modelli_dove(), JSON_UNESCAPED_UNICODE) ?>;
var MD_CAT = <?= json_encode(array_map(fn($c) => ['slug' => $c['slug'], 'nome' => $c['name']], $categorie), JSON_UNESCAPED_UNICODE) ?>;
var MD_CONT = <?= json_encode($contenuti, JSON_UNESCAPED_UNICODE) ?>;
function mdRinomina(id, nome) {
  var n = prompt('Nuovo nome', nome);
  if (n === null || !n.trim()) return;
  var f = document.getElementById('md-form-rinomina'); f.id.value = id; f.nome.value = n.trim(); f.submit();
}
function mdRegola(r) {
  var d = document.createElement('div'); d.className = 'md-regola';
  var sel = '<select name="escludi"><option value="0"' + (r.escludi ? '' : ' selected') + '>Includi</option><option value="1"' + (r.escludi ? ' selected' : '') + '>Escludi</option></select>';
  var dove = '<select name="dove" data-al-cambio="mdValore(this)">'; Object.keys(MD_DOVE).forEach(function (k) { dove += '<option value="' + k + '"' + (r.dove === k ? ' selected' : '') + '>' + MD_DOVE[k] + '</option>'; }); dove += '</select>';
  d.innerHTML = sel + dove + '<span class="md-valore"></span><button type="button" title="Togli" data-rimuovi-genitore>×</button>';
  document.getElementById('md-regole').appendChild(d);
  mdValore(d.querySelector('[name=dove]'), r.valore || '');
}
function mdValore(selDove, valore) {
  var box = selDove.parentNode.querySelector('.md-valore'), dove = selDove.value, h = '';
  if (dove === 'categoria') { h = '<select name="valore">'; MD_CAT.forEach(function (c) { h += '<option value="' + c.slug + '"' + (valore === c.slug ? ' selected' : '') + '>' + c.nome + '</option>'; }); h += '</select>'; }
  else if (dove === 'contenuto') { h = '<input name="valore" list="md-lista-cont" placeholder="Scrivi il titolo e scegli" value="' + (valore || '').replace(/"/g, '') + '">'; }
  else h = '<span class="md-aiuto">–</span>';
  box.innerHTML = h;
}
function mdAggiungiRegola() { mdRegola({ dove: 'sito', valore: '', escludi: false }); }
function mdCondizioni(id, regole, priorita, nome) {
  document.getElementById('md-cond-id').value = id; document.getElementById('md-cond-nome').textContent = nome;
  document.getElementById('md-priorita').value = priorita; document.getElementById('md-regole').innerHTML = '';
  (regole || []).forEach(function (r) {
    if (r.dove === 'contenuto') { var c = MD_CONT.filter(function (x) { return x.rif === r.valore; })[0]; if (c) r = { dove: r.dove, escludi: r.escludi, valore: c.nome }; }
    mdRegola(r);
  });
  document.getElementById('md-dialogo').showModal();
}
document.getElementById('md-form-cond').addEventListener('submit', function () {
  var out = [];
  document.querySelectorAll('#md-regole .md-regola').forEach(function (d) {
    var dove = d.querySelector('[name=dove]').value, v = d.querySelector('[name=valore]'), valore = v ? v.value : '';
    if (dove === 'contenuto') { var c = MD_CONT.filter(function (x) { return x.nome === valore || x.rif === valore; })[0]; valore = c ? c.rif : ''; if (!valore) return; }
    out.push({ dove: dove, valore: valore, escludi: d.querySelector('[name=escludi]').value === '1' });
  });
  document.getElementById('md-cond-json').value = JSON.stringify(out);
});
</script>
<datalist id="md-lista-cont"><?php foreach ($contenuti as $c): ?><option value="<?= h($c['nome']) ?>"><?php endforeach; ?></datalist>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
