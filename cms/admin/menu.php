<?php
/**
 * Menu di navigazione, come Aspetto > Menu di WordPress: a sinistra le voci da aggiungere (pagine, articoli,
 * categorie, link personalizzato), a destra la struttura del menu da ordinare e annidare trascinando (SortableJS).
 *   menu.php            il primo menu     menu.php?id=N     un menu     menu.php?nuovo=1
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/menu-nav.php';
require_admin(); // sicurezza (ruoli): solo amministratore
menu_installa();
$adminTitle = 'Menu di navigazione';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['azione'] ?? '') === 'elimina') {
            menu_elimina($id);
            flash('success', 'Menu eliminato. I widget che lo mostravano restano vuoti finché non scegli un altro menu.');
            header('Location: menu.php');
            exit;
        }
        $voci = json_decode((string)($_POST['voci'] ?? '[]'), true);
        $id = menu_salva($id, (string)($_POST['nome'] ?? ''), (string)($_POST['posizione'] ?? ''), is_array($voci) ? $voci : []);
        flash('success', 'Menu salvato.');
        header('Location: menu.php?id=' . $id);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        header('Location: menu.php?' . ($id ? 'id=' . $id : 'nuovo=1'));
    }
    exit;
}

$tutti = menu_tutti();
$nuovo = isset($_GET['nuovo']) || !$tutti;
$id = (int)($_GET['id'] ?? 0);
$menu = !$nuovo ? ($id ? menu_leggi($id) : reset($tutti)) : null;
if (!$nuovo && !$menu) { http_response_code(404); exit('Menu non trovato. <a href="menu.php">Torna ai menu</a>'); }
$m = $menu ?: ['id' => 0, 'nome' => '', 'posizione' => '', 'voci' => []];
$scelte = menu_scelte();
$csrf = csrf_token();
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="assets/css/contenuti.css">
<style>
.mn-testa{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}
.mn-griglia{display:grid;grid-template-columns:300px minmax(0,1fr);gap:1.2rem;align-items:start}
@media (max-width:900px){.mn-griglia{grid-template-columns:1fr}}
.mn-scatola{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius)}
.mn-scatola h3{margin:0;padding:.6rem .9rem;font-size:.9rem;border-bottom:1px solid var(--border)}
.mn-scatola details{border-bottom:1px solid var(--border)}
.mn-scatola summary{padding:.55rem .9rem;cursor:pointer;font-weight:600;font-size:.86rem}
.mn-lista-scelte{max-height:230px;overflow:auto;padding:.3rem .9rem .6rem;font-size:.84rem}
.mn-lista-scelte label{display:block;padding:.15rem 0}
.mn-scelte-azioni{padding:.4rem .9rem .7rem;display:flex;gap:.5rem;align-items:center}
.mn-cerca{width:100%;margin:.4rem 0}
.mn-corpo{padding:.9rem}
.mn-albero,.mn-albero ul{list-style:none;margin:0;padding:0;min-height:12px}
.mn-albero ul{margin-left:2rem;margin-top:.3rem}
.mn-voce-ed{margin:0 0 .35rem}
.mn-barra{display:flex;align-items:center;gap:.5rem;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:.45rem .7rem;cursor:grab}
.mn-barra b{flex:1;font-weight:600;font-size:.9rem}
.mn-barra small{color:var(--text-muted);font-size:.74rem}
.mn-barra button{background:none;border:0;cursor:pointer;color:var(--text-muted);font-size:.8rem}
.mn-dettagli{display:none;padding:.6rem .8rem .8rem;border:1px solid var(--border);border-top:0;border-radius:0 0 8px 8px;background:#fff;font-size:.84rem}
.mn-voce-ed.aperta>.mn-dettagli{display:block}
.mn-voce-ed.aperta>.mn-barra{border-radius:8px 8px 0 0}
.mn-dettagli label{display:block;margin:.3rem 0 .1rem;color:var(--text-muted)}
.mn-dettagli input[type=text]{width:100%}
.mn-dettagli .mn-azioni-voce{margin-top:.6rem;display:flex;gap:.8rem;font-size:.8rem}
.mn-dettagli .mn-azioni-voce a{color:#b91c1c;cursor:pointer}
.sortable-ghost{opacity:.4}
.mn-vuoto{border:1px dashed var(--border);border-radius:8px;padding:1.2rem;text-align:center;color:var(--text-muted);font-size:.86rem}
</style>

<div class="mn-testa">
  <label>Menu da modificare
    <select data-vai-al-valore>
      <?php foreach ($tutti as $x): ?><option value="menu.php?id=<?= $x['id'] ?>" <?= !$nuovo && (int)$m['id'] === (int)$x['id'] ? 'selected' : '' ?>><?= h($x['nome']) ?><?= $x['posizione'] ? ' (' . h(MENU_POSIZIONI[$x['posizione']] ?? $x['posizione']) . ')' : '' ?></option><?php endforeach; ?>
      <option value="menu.php?nuovo=1" <?= $nuovo ? 'selected' : '' ?>>+ Crea un nuovo menu</option>
    </select>
  </label>
  <span class="hint" style="font-size:.84rem;color:var(--text-muted)">Un menu esce sul sito dentro il widget <b>Menu di navigazione</b> dell'editor (nell'header, nel footer o in una pagina).</span>
  <a href="versioni.php?ambito=menu<?= $m['id'] ? '&amp;oggetto=' . (int)$m['id'] : '' ?>" class="btn btn-secondary btn-sm" style="margin-left:auto" title="Le versioni di questo menu: confronta e ripristina">Cronologia</a><!-- versioni -->
</div>

<form method="post" id="mn-form">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="voci" id="mn-voci-json">
  <div class="mn-griglia">
    <aside class="mn-scatola">
      <h3>Aggiungi voci al menu</h3>
      <?php foreach ([['pagine', 'Pagine', 'pagina'], ['articoli', 'Articoli', 'articolo'], ['categorie', 'Categorie', 'categoria']] as [$k, $nome, $tipo]): ?>
      <details <?= $k === 'pagine' ? 'open' : '' ?>>
        <summary><?= $nome ?></summary>
        <div class="mn-lista-scelte" data-gruppo="<?= $k ?>">
          <input type="search" class="mn-cerca" placeholder="Cerca…" data-al-input="mnFiltra(this)">
          <?php if ($k === 'pagine'): foreach ($scelte['speciali'] as $s): ?>
          <label><input type="checkbox" data-tipo="link" data-rif="" data-url="<?= h($s['url']) ?>" data-nome="<?= h($s['title']) ?>"> <?= h($s['title']) ?></label>
          <?php endforeach; endif; ?>
          <?php foreach ($scelte[$k] as $s): ?>
          <label><input type="checkbox" data-tipo="<?= $tipo ?>" data-rif="<?= h($s['slug']) ?>" data-url="<?= h($s['url']) ?>" data-nome="<?= h($s['title']) ?>"> <?= h($s['title']) ?></label>
          <?php endforeach; ?>
        </div>
        <div class="mn-scelte-azioni"><button type="button" class="btn btn-secondary btn-sm" data-al-clic="mnAggiungiSpuntate(this)">Aggiungi al menu</button></div>
      </details>
      <?php endforeach; ?>
      <details>
        <summary>Link personalizzato</summary>
        <div class="mn-lista-scelte">
          <label style="display:block">Indirizzo<input type="text" id="mn-link-url" placeholder="https://… oppure /pagina/" style="width:100%"></label>
          <label style="display:block">Testo della voce<input type="text" id="mn-link-testo" placeholder="Es. Prenota" style="width:100%"></label>
        </div>
        <div class="mn-scelte-azioni"><button type="button" class="btn btn-secondary btn-sm" data-al-clic="mnAggiungiLink()">Aggiungi al menu</button></div>
      </details>
    </aside>

    <section class="mn-scatola">
      <h3>Struttura del menu</h3>
      <div class="mn-corpo">
        <div class="sc-riga" style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:.9rem">
          <div><label>Nome del menu</label><input type="text" name="nome" value="<?= h($m['nome']) ?>" required placeholder="Es. Principale"></div>
          <div><label>Posizione (etichetta, facoltativa)</label>
            <select name="posizione"><?php foreach (MENU_POSIZIONI as $k => $n): ?><option value="<?= h($k) ?>" <?= $m['posizione'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <p class="hint" style="font-size:.82rem;color:var(--text-muted);margin:0 0 .6rem">Trascina le voci per ordinarle. Trascina una voce un po' più a destra, sotto un'altra, per farla diventare una sottovoce. Fai clic sulla freccia per cambiare testo e link.</p>
        <ul class="mn-albero" id="mn-albero"></ul>
        <div class="mn-vuoto" id="mn-vuoto" hidden>Il menu è vuoto: aggiungi le voci dalla colonna a sinistra.</div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;gap:.5rem;flex-wrap:wrap">
          <?php if ($m['id']): ?><button type="submit" class="ct-link ct-rosso" name="azione" value="elimina" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:.84rem" data-conferma="Eliminare il menu <?= h(($m['nome'])) ?>?">Elimina il menu</button><?php else: ?><span></span><?php endif; ?>
          <button type="submit" class="btn btn-primary"><?= $m['id'] ? 'Salva il menu' : 'Crea il menu' ?></button>
        </div>
      </div>
    </section>
  </div>
</form>

<template id="mn-tpl">
  <li class="mn-voce-ed">
    <div class="mn-barra"><b class="mn-et"></b><small class="mn-tipo"></small><button type="button" class="mn-apri" title="Modifica">&#9662;</button></div>
    <div class="mn-dettagli">
      <label>Testo della voce</label><input type="text" data-c="etichetta">
      <label>Indirizzo</label><input type="text" data-c="url">
      <label>Classi CSS (facoltative)</label><input type="text" data-c="classi">
      <label><input type="checkbox" data-c="nuova"> Apri in una nuova scheda</label>
      <div class="mn-azioni-voce"><a class="mn-elimina">Elimina la voce</a></div>
    </div>
    <ul class="mn-figli"></ul>
  </li>
</template>

<script src="assets/editor/Sortable.min.js"></script>
<script<?= nonce_attr() ?>>
(function () {
  var VOCI = <?= json_encode($m['voci'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var TIPI = <?= json_encode(MENU_TIPI_VOCE, JSON_UNESCAPED_UNICODE) ?>;
  var albero = document.getElementById('mn-albero'), vuoto = document.getElementById('mn-vuoto');
  function nuovoId() { var s = '', a = 'abcdefghijklmnopqrstuvwxyz0123456789'; for (var i = 0; i < 7; i++) s += a[Math.floor(Math.random() * a.length)]; return s; }
  function nodo(v) {
    var li = document.getElementById('mn-tpl').content.firstElementChild.cloneNode(true);
    li.dataset.id = v.id || nuovoId(); li.dataset.tipo = v.tipo || 'link'; li.dataset.rif = v.rif || '';
    li.querySelector('[data-c=etichetta]').value = v.etichetta || '';
    li.querySelector('[data-c=url]').value = v.url || '';
    li.querySelector('[data-c=classi]').value = v.classi || '';
    li.querySelector('[data-c=nuova]').checked = !!v.nuova;
    aggiornaBarra(li);
    li.querySelector('.mn-apri').addEventListener('click', function () { li.classList.toggle('aperta'); });
    li.querySelector('.mn-elimina').addEventListener('click', function () { if (confirm('Togliere questa voce (e le sue sottovoci)?')) { li.remove(); controllaVuoto(); } });
    li.querySelectorAll('input').forEach(function (i) { i.addEventListener('input', function () { aggiornaBarra(li); }); });
    var figli = li.querySelector('.mn-figli');
    (v.figli || []).forEach(function (f) { figli.appendChild(nodo(f)); });
    ordinabile(figli);
    return li;
  }
  function aggiornaBarra(li) {
    li.querySelector('.mn-et').textContent = li.querySelector('[data-c=etichetta]').value || '(senza testo)';
    li.querySelector('.mn-tipo').textContent = TIPI[li.dataset.tipo] || 'Link';
  }
  function ordinabile(ul) {
    new Sortable(ul, { group: 'menu', animation: 150, fallbackOnBody: true, swapThreshold: 0.65, handle: '.mn-barra', ghostClass: 'sortable-ghost', onEnd: controllaVuoto });
  }
  function leggi(ul, livello) {
    var out = [];
    Array.prototype.forEach.call(ul.children, function (li) {
      if (!li.classList.contains('mn-voce-ed')) return;
      out.push({ id: li.dataset.id, tipo: li.dataset.tipo, rif: li.dataset.rif, etichetta: li.querySelector('[data-c=etichetta]').value, url: li.querySelector('[data-c=url]').value,
        classi: li.querySelector('[data-c=classi]').value, nuova: li.querySelector('[data-c=nuova]').checked, figli: livello < 2 ? leggi(li.querySelector('.mn-figli'), livello + 1) : [] });
    });
    return out;
  }
  function controllaVuoto() { vuoto.hidden = albero.children.length > 0; }
  function aggiungi(v) { albero.appendChild(nodo(v)); controllaVuoto(); }
  window.mnAggiungiSpuntate = function (b) {
    var box = b.closest('details').querySelector('.mn-lista-scelte');
    box.querySelectorAll('input[type=checkbox]:checked').forEach(function (c) { aggiungi({ tipo: c.dataset.tipo, rif: c.dataset.rif, url: c.dataset.url, etichetta: c.dataset.nome }); c.checked = false; });
  };
  window.mnAggiungiLink = function () {
    var u = document.getElementById('mn-link-url'), t = document.getElementById('mn-link-testo');
    if (!u.value.trim()) { u.focus(); return; }
    aggiungi({ tipo: 'link', rif: '', url: u.value.trim(), etichetta: t.value.trim() || u.value.trim() });
    u.value = ''; t.value = '';
  };
  window.mnFiltra = function (inp) {
    var q = inp.value.trim().toLowerCase();
    inp.parentNode.querySelectorAll('label').forEach(function (l) { l.hidden = q !== '' && l.textContent.toLowerCase().indexOf(q) < 0; });
  };
  VOCI.forEach(aggiungi);
  controllaVuoto();
  ordinabile(albero);
  document.getElementById('mn-form').addEventListener('submit', function (e) {
    if (e.submitter && e.submitter.value === 'elimina') return;
    document.getElementById('mn-voci-json').value = JSON.stringify(leggi(albero, 0));
  });
})();
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
