<?php
/**
 * Widget (gruppo Editor): tutti i widget registrati nell'editor, per gruppo, con la provenienza (nucleo o modulo)
 * e un interruttore attivo/spento. Un widget spento sparisce dalla tavolozza dell'editor; le pagine che lo
 * contengono continuano a mostrarlo e a disegnarlo, e si può ancora modificare da lì.
 * L'elenco degli spenti sta nell'opzione editor_widget_disattivi (includes/editor-opzioni.php).
 *
 *   POST azione=salva attivi[]        salva lo stato di tutti i widget
 *   POST azione=tutti                 riaccende tutti
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/editor.php';
require_admin();
$adminTitle = 'Widget';
$autore = (string)(current_user()['username'] ?? '');

$definizioni = editor_definizioni();
$widget = [];
foreach ($definizioni as $tipo => $d) {
    if (in_array($tipo, ['sezione', 'colonna'], true)) continue;
    $gruppo = (string)($d['gruppo'] ?? '');
    if ($gruppo === '') $gruppo = 'Altri';
    $widget[$gruppo][$tipo] = $d;
}
ksort($widget);
$tipiNoti = [];
foreach ($widget as $g => $lista) foreach ($lista as $tipo => $d) $tipiNoti[] = $tipo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (($_POST['azione'] ?? '') === 'tutti') {
            editor_opzioni_salva(['widget_disattivi' => []], $autore, 'tutti i widget riaccesi');
            flash('success', 'Tutti i widget sono di nuovo nella tavolozza.');
        } else {
            $attivi = array_map('strval', (array)($_POST['attivi'] ?? []));
            $spenti = array_values(array_diff($tipiNoti, $attivi));
            // Gli spenti che oggi non sono registrati (un modulo disattivato) restano spenti: si riaccendono quando tornano.
            foreach (editor_widget_disattivi() as $t) if (!in_array($t, $tipiNoti, true)) $spenti[] = $t;
            editor_opzioni_salva(['widget_disattivi' => $spenti], $autore, count($spenti) . ' widget spenti');
            flash('success', count($spenti) === 0 ? 'Tutti i widget sono attivi.' : count($spenti) . ($spenti && count($spenti) === 1 ? ' widget spento' : ' widget spenti') . ': non compaiono più nella tavolozza dell\'editor. Le pagine che li usano restano come sono.');
        }
    } catch (Throwable $e) {
        flash('error', 'Salvataggio non riuscito: ' . $e->getMessage());
    }
    header('Location: editor-widget.php');
    exit;
}

$spenti = editor_widget_disattivi();
// Quante pagine, articoli e modelli usano ogni widget (strutture pubblicate e bozze).
$usi = [];
try {
    foreach (['pages', 'articles', 'modelli'] as $tab) {
        foreach (db()->query('SELECT struttura, struttura_bozza FROM ' . tbl($tab) . " WHERE (struttura IS NOT NULL AND struttura <> '') OR (struttura_bozza IS NOT NULL AND struttura_bozza <> '')") as $r) {
            $visti = [];
            foreach ([$r['struttura'], $r['struttura_bozza']] as $j) {
                if (!$j || !preg_match_all('/"tipo"\s*:\s*"([a-z][a-z0-9_]*)"/', (string)$j, $m)) continue;
                foreach (array_unique($m[1]) as $t) $visti[$t] = true;
            }
            foreach (array_keys($visti) as $t) $usi[$t] = ($usi[$t] ?? 0) + 1;
        }
    }
} catch (Throwable $e) { /* senza una tabella niente conteggi */ }
$origine = function (array $d): string {
    $o = (string)($d['origine'] ?? 'nucleo');
    if (str_starts_with($o, 'modulo:')) { $m = substr($o, 7); $mod = cms_modulo($m); return 'modulo ' . ($mod['etichetta'] ?? $m); }
    if (str_starts_with($o, 'addon:')) { $m = substr($o, 6); $mod = cms_modulo($m); return 'addon ' . ($mod['etichetta'] ?? $m); }
    return 'nucleo';
};
$totale = count($tipiNoti);
$csrf = csrf_token();
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.wg-testa{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}
.wg-testa p{margin:0;font-size:.88rem;color:var(--text-muted);flex:1 1 100%}
.wg-barra{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:.8rem;position:sticky;top:0;background:var(--bg);padding:.4rem 0;z-index:2}
.wg-barra input{padding:.4rem .6rem;border:1px solid var(--border);border-radius:8px;font:inherit;font-size:.85rem;background:var(--surface);min-width:220px}
.wg-barra .wg-conta{font-size:.84rem;color:var(--text-muted);margin-left:auto}
.wg-gruppo{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:1rem;overflow:hidden}
.wg-gruppo h3{margin:0;padding:.6rem .9rem;font-size:.86rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.wg-gruppo h3 button{background:none;border:0;font:inherit;font-size:.76rem;text-transform:none;letter-spacing:0;color:var(--link);cursor:pointer}
.wg-lista{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr))}
.wg-voce{display:flex;gap:.7rem;align-items:center;padding:.6rem .9rem;border-bottom:1px solid var(--border);border-right:1px solid var(--border)}
.wg-voce i{font-style:normal;font-size:1.25rem;width:1.6rem;text-align:center;color:var(--accento-testo);flex:none}
.wg-voce b{display:block;font-size:.9rem}
.wg-voce small{display:block;font-size:.74rem;color:var(--text-muted)}
.wg-voce.spento b{color:var(--text-muted);text-decoration:line-through}
.wg-voce .wg-tipo{font-family:Consolas,Monaco,monospace;font-size:.72rem}
.wg-voce.wg-nascosto{display:none}
.wg-int{margin-left:auto;flex:none;position:relative;width:40px;height:22px}
.wg-int input{opacity:0;width:0;height:0;position:absolute}
.wg-int span{position:absolute;inset:0;background:var(--bordo-forte);border-radius:22px;transition:.15s;cursor:pointer}
.wg-int span::before{content:"";position:absolute;width:16px;height:16px;left:3px;top:3px;background:var(--surface);border-radius:50%;transition:.15s}
.wg-int input:checked+span{background:var(--sage)}
.wg-int input:checked+span::before{transform:translateX(18px)}
.wg-int input:focus-visible+span{outline:2px solid var(--coral);outline-offset:2px}
</style>

<div class="wg-testa">
  <p><?= $totale ?> widget in <?= count($widget) ?> gruppi. Spegni quelli che non vuoi vedere nella tavolozza dell'editor: le pagine che li usano già continuano a mostrarli e puoi sempre modificarli da lì. Il numero sotto ogni widget dice in quanti contenuti compare oggi.</p>
</div>

<form method="post" id="wg-form">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="salva">
  <div class="wg-barra">
    <input type="search" id="wg-cerca" placeholder="Cerca un widget (nome o tipo)" aria-label="Cerca un widget">
    <button class="btn btn-primary btn-sm">Salva</button>
    <button type="button" class="btn btn-secondary btn-sm" id="wg-tutti">Accendi tutti</button>
    <button type="button" class="btn btn-secondary btn-sm" id="wg-nessuno">Spegni tutti</button>
    <span class="wg-conta" id="wg-conta"></span>
  </div>
  <?php foreach ($widget as $g => $lista): ?>
  <div class="wg-gruppo" data-gruppo="<?= h($g) ?>">
    <h3><span><?= h($g) ?> (<?= count($lista) ?>)</span><button type="button" class="wg-gruppo-tutti">accendi o spegni il gruppo</button></h3>
    <div class="wg-lista">
      <?php foreach ($lista as $tipo => $d): $on = !in_array($tipo, $spenti, true); ?>
      <label class="wg-voce <?= $on ? '' : 'spento' ?>" data-nome="<?= h(mb_strtolower((string)$d['etichetta'])) ?>" data-tipo="<?= h($tipo) ?>">
        <i><?= $d['icona'] ?? '*' ?></i>
        <div><b><?= h((string)$d['etichetta']) ?></b><small><span class="wg-tipo"><?= h($tipo) ?></span> · <?= h($origine($d)) ?><?= isset($usi[$tipo]) ? ' · in ' . (int)$usi[$tipo] . ($usi[$tipo] === 1 ? ' contenuto' : ' contenuti') : '' ?></small></div>
        <span class="wg-int"><input type="checkbox" name="attivi[]" value="<?= h($tipo) ?>" <?= $on ? 'checked' : '' ?>><span></span></span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</form>
<form method="post" style="margin-top:.4rem"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="tutti"><button class="btn btn-secondary btn-sm">Riaccendi tutti e salva</button></form>
<script<?= nonce_attr() ?>>
(function () {
  var form = document.getElementById('wg-form'), conta = document.getElementById('wg-conta');
  function aggiorna() {
    var tutti = form.querySelectorAll('input[name="attivi[]"]'), on = form.querySelectorAll('input[name="attivi[]"]:checked').length;
    conta.textContent = on + ' attivi su ' + tutti.length + (tutti.length - on ? ' (' + (tutti.length - on) + ' spenti)' : '');
    tutti.forEach(function (c) { c.closest('.wg-voce').classList.toggle('spento', !c.checked); });
  }
  form.addEventListener('change', aggiorna); aggiorna();
  document.getElementById('wg-tutti').addEventListener('click', function () { form.querySelectorAll('input[name="attivi[]"]').forEach(function (c) { c.checked = true; }); aggiorna(); });
  document.getElementById('wg-nessuno').addEventListener('click', function () { form.querySelectorAll('input[name="attivi[]"]').forEach(function (c) { c.checked = false; }); aggiorna(); });
  form.querySelectorAll('.wg-gruppo-tutti').forEach(function (b) {
    b.addEventListener('click', function () {
      var caselle = b.closest('.wg-gruppo').querySelectorAll('input[name="attivi[]"]'), tutteOn = Array.prototype.every.call(caselle, function (c) { return c.checked; });
      caselle.forEach(function (c) { c.checked = !tutteOn; }); aggiorna();
    });
  });
  document.getElementById('wg-cerca').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    form.querySelectorAll('.wg-voce').forEach(function (v) { v.classList.toggle('wg-nascosto', q !== '' && v.dataset.nome.indexOf(q) < 0 && v.dataset.tipo.indexOf(q) < 0); });
  });
})();
</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
