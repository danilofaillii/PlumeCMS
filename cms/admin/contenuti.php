<?php
/**
 * L'elenco di articoli, pagine e tipi dei moduli: schede per stato, cerca, filtri per
 * data e categoria, azioni di massa, modifica rapida nella riga, cestino, duplica, anteprima.
 *   contenuti.php?tipo=articoli    contenuti.php?tipo=pagine
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/contenuti.php';
require_login();
sito_schema_aggiorna();

$tipo = isset(contenuti_tipi()[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : 'articoli';
$T = contenuti_tipo($tipo);
$adminTitle = $T['Tanti'];
$autore = (string)(current_user()['username'] ?? '');
$filtri = ['stato' => (string)($_GET['stato'] ?? ''), 'q' => trim((string)($_GET['q'] ?? '')), 'categoria' => (string)($_GET['categoria'] ?? ''),
    'tipo_articolo' => (string)($_GET['tipo_articolo'] ?? ''), 'mese' => (string)($_GET['mese'] ?? ''), 'ordina' => (string)($_GET['ordina'] ?? 'data'),
    'dir' => (string)($_GET['dir'] ?? 'desc'), 'pagina' => (int)($_GET['pagina'] ?? 1)];
$qs = fn(array $extra = []) => 'contenuti.php?' . http_build_query(array_filter(['tipo' => $tipo] + $extra + $filtri, fn($v) => $v !== '' && $v !== null && $v !== 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    if (!empty($_POST['uno'])) $ids = [(int)$_POST['uno']];
    try {
        switch ($_POST['azione'] ?? '') {
            case 'rapido':
                $d = ['title' => (string)($_POST['title'] ?? ''), 'slug' => (string)($_POST['slug'] ?? ''), 'stato' => (string)($_POST['stato'] ?? 'bozza'), 'data' => (string)($_POST['data'] ?? ''), 'ora' => (string)($_POST['ora'] ?? ''), 'autore' => $autore];
                if ($T['tassonomie']) { $d['categorie'] = (array)($_POST['categorie'] ?? []); $d['tags'] = (string)($_POST['tags'] ?? ''); }
                contenuti_salva_rapido($tipo, $ids[0], $d);
                flash('success', $T['Uno'] . ' aggiornato.');
                break;
            case 'pubblica': $n = contenuti_stato_massa($tipo, $ids, 'pubblicato'); flash('success', "$n " . ($n === 1 ? $T['uno'] : $T['tanti']) . ' pubblicati.'); break;
            case 'bozza': $n = contenuti_stato_massa($tipo, $ids, 'bozza'); flash('success', "$n in bozza."); break;
            case 'cestina': $n = contenuti_cestina($tipo, $ids); flash('success', "$n nel cestino." . ($n < count($ids) ? ' La home resta fuori dal cestino.' : '')); break;
            case 'ripristina': $n = contenuti_ripristina($tipo, $ids); flash('success', "$n ripristinati."); break;
            case 'elimina': $n = contenuti_elimina($tipo, $ids); flash('success', "$n eliminati per sempre."); break;
            case 'svuota':
                $tutti = db()->query('SELECT id FROM ' . tbl($T['tabella']) . ' WHERE cestino IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
                $n = contenuti_elimina($tipo, array_map('intval', $tutti)); flash('success', "Cestino svuotato: $n eliminati."); break;
            case 'duplica':
                $nuovo = contenuti_duplica($tipo, $ids[0]);
                flash('success', 'Copia creata in bozza.');
                header('Location: contenuto.php?tipo=' . $tipo . '&id=' . $nuovo);
                exit;
            default: flash('error', 'Scegli un\'azione.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . sicurezza_ritorno_pannello((string)($_POST['torna'] ?? ''), $qs())); // sicurezza: solo dentro il pannello
    exit;
}

[$righe, $totale, $pagina, $per] = contenuti_elenco($tipo, $filtri);
$conteggi = contenuti_conteggi($tipo);
$pagine = max(1, (int)ceil($totale / $per));
$categorie = $T['tassonomie'] ? contenuti_categorie() : [];
$mesi = contenuti_mesi($tipo);
$home = $tipo === 'pagine' ? (string)get_setting('pagina_home', '') : '';
$ordinaLink = function (string $col, string $nome) use ($filtri, $qs) {
    $dir = $filtri['ordina'] === $col && $filtri['dir'] === 'desc' ? 'asc' : 'desc';
    $freccia = $filtri['ordina'] === $col ? ($filtri['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . h($qs(['ordina' => $col, 'dir' => $dir, 'pagina' => 1])) . '">' . $nome . $freccia . '</a>';
};
setlocale(LC_TIME, 'it_IT.UTF-8');
$meseNome = fn(string $m) => SITO_MESI[(int)substr($m, 5, 2) - 1] . ' ' . substr($m, 0, 4);
$colonne = 5 + ($T['tassonomie'] ? 2 : 0) + ($T['kw'] ? 1 : 0);

require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">

<div class="ct-testa">
  <div class="ct-schede">
    <?php foreach (['' => 'Tutti', 'pubblicato' => 'Pubblicati', 'programmato' => 'Programmati', 'bozza' => 'Bozze', 'cestino' => 'Cestino'] as $k => $nome):
      $n = $conteggi[$k === '' ? 'tutti' : $k]; if ($k !== '' && $n === 0) continue; ?>
      <a href="<?= h($qs(['stato' => $k, 'pagina' => 1, 'q' => '', 'categoria' => '', 'mese' => '', 'tipo_articolo' => ''])) ?>" class="<?= $filtri['stato'] === $k ? 'attivo' : '' ?>"><?= $nome ?> <span>(<?= $n ?>)</span></a>
    <?php endforeach; ?>
  </div>
  <a href="editor.php?nuova=1&amp;tipo=<?= $tipo ?>" class="btn btn-primary">+ Aggiungi <?= $T['uno'] ?></a>
</div>

<form method="post" id="ct-form">
  <?= csrf_field() ?>
  <input type="hidden" name="torna" value="<?= h($qs()) ?>">
  <div class="ct-barra">
    <div class="ct-sinistra">
      <select name="azione" id="ct-azione">
        <option value="">Azioni di gruppo</option>
        <?php if ($filtri['stato'] === 'cestino'): ?>
        <option value="ripristina">Ripristina</option>
        <option value="elimina">Elimina definitivamente</option>
        <?php else: ?>
        <option value="pubblica">Pubblica</option>
        <option value="bozza">Metti in bozza</option>
        <option value="cestina">Sposta nel cestino</option>
        <?php endif; ?>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm" data-pn-gruppo="#ct-azione" data-pn-gruppo-righe=".ct-sel" data-pn-gruppo-elimina="Eliminare per sempre? Non si torna indietro.">Applica</button>
      <?php if ($filtri['stato'] === 'cestino' && $conteggi['cestino'] > 0): ?>
      <button type="submit" class="btn btn-danger btn-sm" data-campo-id="ct-azione" data-valore="svuota" data-conferma="Svuotare il cestino? Si eliminano per sempre.">Svuota il cestino</button>
      <?php endif; ?>
    </div>
    <div class="ct-filtri">
      <select data-vai-al-valore>
        <option value="<?= h($qs(['mese' => '', 'pagina' => 1])) ?>">Tutte le date</option>
        <?php foreach ($mesi as $m): if (!$m) continue; ?><option value="<?= h($qs(['mese' => $m, 'pagina' => 1])) ?>" <?= $filtri['mese'] === $m ? 'selected' : '' ?>><?= h($meseNome($m)) ?></option><?php endforeach; ?>
      </select>
      <?php if ($T['tassonomie']): ?>
      <select data-vai-al-valore>
        <option value="<?= h($qs(['categoria' => '', 'pagina' => 1])) ?>">Tutte le categorie</option>
        <?php foreach ($categorie as $slug => $c): ?><option value="<?= h($qs(['categoria' => $slug, 'pagina' => 1])) ?>" <?= $filtri['categoria'] === $slug ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
      </select>
      <?php endif; ?>
      <?php if ($T['attributi']): ?>
      <select data-vai-al-valore>
        <option value="<?= h($qs(['tipo_articolo' => '', 'pagina' => 1])) ?>">Pillar e cluster</option>
        <option value="<?= h($qs(['tipo_articolo' => 'pillar', 'pagina' => 1])) ?>" <?= $filtri['tipo_articolo'] === 'pillar' ? 'selected' : '' ?>>Solo pillar</option>
        <option value="<?= h($qs(['tipo_articolo' => 'cluster', 'pagina' => 1])) ?>" <?= $filtri['tipo_articolo'] === 'cluster' ? 'selected' : '' ?>>Solo cluster</option>
      </select>
      <?php endif; ?>
    </div>
    <div class="ct-cerca">
      <input type="search" name="ct-q" id="ct-q" value="<?= h($filtri['q']) ?>" placeholder="Cerca <?= $T['tanti'] ?>…">
      <button type="button" class="btn btn-secondary btn-sm" data-al-clic="ctCerca(<?= h(json_encode($qs(['q' => '__Q__', 'pagina' => 1]))) ?>)">Cerca</button>
    </div>
  </div>

  <div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr>
      <th class="ct-col-sel"><input type="checkbox" data-seleziona-tutti=".ct-sel" title="Seleziona tutto"></th>
      <th><?= $ordinaLink('titolo', 'Titolo') ?></th>
      <th><?= $ordinaLink('autore', 'Autore') ?></th>
      <?php if ($T['tassonomie']): ?><th>Categorie</th><th>Tag</th><?php endif; ?>
      <?php if ($T['kw']): ?><th title="Parole e densità della keyword principale e delle correlate">Parole · KW</th><?php endif; ?>
      <th><?= $ordinaLink('data', 'Data') ?></th>
    </tr></thead>
    <tbody>
    <?php if (!$righe): ?>
      <tr><td colspan="<?= $colonne ?>"><div class="empty-state"><p><?= $filtri['stato'] === 'cestino' ? 'Il cestino è vuoto.' : 'Nessun ' . $T['uno'] . ' qui.' ?></p></div></td></tr>
    <?php endif; ?>
    <?php foreach ($righe as $r):
      $stato = contenuti_stato($tipo, $r);
      $quando = contenuti_quando($tipo, $r);
      $percorso = contenuti_percorso($tipo, $r);
      $eHome = $tipo === 'pagine' && $r['slug'] === $home;
      $modifica = 'contenuto.php?tipo=' . $tipo . '&id=' . $r['id'];
      $conEditor = trim((string)($r['struttura_aggiornata'] ?? '')) !== '';
      $cats = $T['tassonomie'] ? contenuti_categorie_di($r) : [];
      $tagNomi = $T['tassonomie'] ? contenuti_tag_di($r) : [];
      $kw = $T['kw'] ? contenuti_kw_density($r['content'] ?? '', $r['keyword_target'] ?? '') : null;
      $rk = $T['kw'] ? contenuti_kw_correlate($r['content'] ?? '', $r['keywords_secondarie'] ?? '') : null;
    ?>
      <tr class="ct-riga" id="ct-riga-<?= $r['id'] ?>" data-id="<?= $r['id'] ?>" data-title="<?= h($r['title']) ?>" data-slug="<?= h($r['slug']) ?>" data-stato="<?= $stato === 'bozza' ? 'bozza' : 'pubblicato' ?>"
          data-data="<?= h(substr($quando, 0, 10)) ?>" data-ora="<?= h(substr($quando, 11, 5) ?: '06:00') ?>" data-categorie="<?= h(implode(',', $cats)) ?>" data-tags="<?= h(implode(', ', $tagNomi)) ?>">
        <td class="ct-col-sel"><input type="checkbox" class="ct-sel" name="ids[]" value="<?= $r['id'] ?>"></td>
        <td class="ct-titolo">
          <a class="ct-nome" href="<?= h($modifica) ?>"><?= h($r['title']) ?></a>
          <?php if ($stato === 'bozza'): ?> <span class="badge badge-draft">Bozza</span><?php elseif ($stato === 'programmato'): ?> <span class="badge badge-scheduled">Programmato</span><?php endif; ?>
          <?php if ($eHome): ?> <span class="badge ct-badge-home">Home</span><?php endif; ?>
          <?php if ($T['attributi'] && $r['type'] === 'pillar'): ?> <span class="badge badge-pillar">Pillar</span><?php endif; ?>
          <?php if ($conEditor): ?> <span class="badge ct-badge-editor" title="Composto con l'editor visuale">Editor</span><?php endif; ?>
          <div class="ct-indirizzo"><?= h($percorso) ?></div>
          <div class="ct-azioni">
            <?php if ($stato === 'cestino'): ?>
              <button type="submit" name="azione" value="ripristina" data-uno="<?= $r['id'] ?>">Ripristina</button>
              <button type="submit" name="azione" value="elimina" class="ct-rosso" data-uno="<?= $r['id'] ?>" data-conferma="Eliminare per sempre «<?= h(($r['title'])) ?>»?">Elimina definitivamente</button>
            <?php else: ?>
              <a href="<?= h($modifica) ?>">Modifica</a>
              <button type="button" data-al-clic="ctRapido(<?= $r['id'] ?>)">Modifica rapida</button>
              <a href="editor.php?tipo=<?= $tipo ?>&amp;id=<?= $r['id'] ?>">Editor visuale</a>
              <button type="submit" name="azione" value="duplica" data-uno="<?= $r['id'] ?>">Duplica</button>
              <?php if (!$eHome): ?><button type="submit" name="azione" value="cestina" class="ct-rosso" data-uno="<?= $r['id'] ?>">Cestina</button><?php endif; ?>
              <a href="<?= h(cms_sito_base() . $percorso) ?><?= $stato === 'pubblicato' ? '' : '?preview=1' ?>" target="_blank"><?= $stato === 'pubblicato' ? 'Visualizza' : 'Anteprima' ?></a>
            <?php endif; ?>
          </div>
        </td>
        <td class="ct-muto"><?= h($r['author'] ?? (string)cms_impostazione('autore_predefinito', '')) ?></td>
        <?php if ($T['tassonomie']): ?>
        <td class="ct-muto"><?= $cats ? implode(', ', array_map(fn($c) => '<a href="' . h($qs(['categoria' => $c, 'pagina' => 1])) . '">' . h($categorie[$c]['name'] ?? $c) . '</a>', $cats)) : '—' ?></td>
        <td class="ct-muto ct-tag"><?= $tagNomi ? h(implode(', ', $tagNomi)) : '—' ?></td>
        <?php endif; ?>
        <?php if ($T['kw']): ?>
        <td class="ct-kw" title="Keyword: <?= h($r['keyword_target'] ?? '') ?> · esatta <?= $kw['exact_density'] ?>% (<?= $kw['exact_count'] ?>x), varianti <?= $kw['variant_density'] ?>% · correlate: <?= h(implode(' | ', $rk['details'])) ?>">
          <?= $kw['words'] ?> parole<br>
          <span class="<?= $kw['density'] >= 1.8 ? 'kw-good' : ($kw['density'] >= 1 ? 'kw-warn' : 'kw-bad') ?>"><?= $kw['density'] ?>%</span> ·
          <span class="<?= $rk['density'] >= 1.5 ? 'kw-good' : ($rk['density'] >= .8 ? 'kw-warn' : 'kw-bad') ?>"><?= $rk['density'] ?>%</span>
        </td>
        <?php endif; ?>
        <td class="ct-muto ct-data">
          <?php if ($stato === 'cestino'): ?>Nel cestino<br><?= h(substr((string)$r['cestino'], 0, 16)) ?>
          <?php elseif ($stato === 'bozza'): ?>Ultima modifica<br><?= h(substr((string)$r['updated_at'], 0, 16)) ?>
          <?php elseif ($stato === 'programmato'): ?>Programmato<br><?= h(substr($quando, 0, 16)) ?>
          <?php else: ?>Pubblicato<br><?= h(substr($quando, 0, 16)) ?><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <input type="hidden" name="uno" value="">
</form>

<?php if ($pagine > 1): ?>
<div class="ct-pagine">
  <span><?= $totale ?> <?= $T['tanti'] ?></span>
  <?php for ($i = 1; $i <= $pagine; $i++): if ($pagine > 12 && abs($i - $pagina) > 3 && $i !== 1 && $i !== $pagine) { if (abs($i - $pagina) === 4) echo '<span>…</span>'; continue; } ?>
  <a href="<?= h($qs(['pagina' => $i])) ?>" class="btn btn-sm <?= $i === $pagina ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Modifica rapida: il modulo si sposta sotto la riga scelta -->
<form method="post" id="ct-rapido" class="ct-rapido" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="azione" value="rapido"><input type="hidden" name="uno" value=""><input type="hidden" name="torna" value="<?= h($qs()) ?>">
  <div class="ct-rapido-griglia">
    <label>Titolo<input name="title" required></label>
    <label>Indirizzo<span class="ct-slug"><i>/</i><input name="slug" pattern="[a-z0-9\-]+"><i>/</i></span></label>
    <label>Stato<select name="stato"><option value="pubblicato">Pubblicato</option><option value="bozza">Bozza</option></select></label>
    <label>Data<input type="date" name="data"></label>
    <label>Ora<input type="time" name="ora"></label>
    <?php if ($T['tassonomie']): ?>
    <div class="ct-rapido-cat"><span>Categorie</span><div class="ct-cat-lista">
      <?php foreach ($categorie as $slug => $c): ?><label><input type="checkbox" name="categorie[]" value="<?= h($slug) ?>"> <?= h($c['name']) ?></label><?php endforeach; ?>
    </div></div>
    <label class="ct-rapido-tag">Tag (separati da virgola)<input name="tags" list="ct-tag-lista"></label>
    <?php endif; ?>
  </div>
  <p class="ct-aiuto">Una data futura con lo stato Pubblicato vuol dire Programmato: esce da solo a quell'ora. Se cambi l'indirizzo di un contenuto già uscito, dal vecchio parte un redirect 301.</p>
  <div class="ct-rapido-azioni"><button type="submit" class="btn btn-primary btn-sm">Aggiorna</button><button type="button" class="btn btn-secondary btn-sm" data-al-clic="ctChiudiRapido()">Annulla</button></div>
</form>
<?php if ($T['tassonomie']): ?>
<datalist id="ct-tag-lista"><?php foreach (contenuti_tag_tutti() as $t): ?><option value="<?= h($t) ?>"><?php endforeach; ?></datalist>
<?php endif; ?>

<script<?= nonce_attr() ?>>
function ctCerca(modello) { location.href = modello.replace('__Q__', encodeURIComponent(document.getElementById('ct-q').value)); }
function ctRapido(id) {
  ctChiudiRapido();
  var riga = document.getElementById('ct-riga-' + id), f = document.getElementById('ct-rapido');
  f.uno.value = id; f.title.value = riga.dataset.title; f.slug.value = riga.dataset.slug; f.stato.value = riga.dataset.stato;
  f.data.value = riga.dataset.data; f.ora.value = riga.dataset.ora;
  if (f.tags) { f.tags.value = riga.dataset.tags; var cats = riga.dataset.categorie.split(','); f.querySelectorAll('[name="categorie[]"]').forEach(function (c) { c.checked = cats.indexOf(c.value) >= 0; }); }
  var tr = document.createElement('tr'); tr.id = 'ct-rapido-riga';
  var td = document.createElement('td'); td.colSpan = <?= $colonne ?>; tr.appendChild(td);
  riga.parentNode.insertBefore(tr, riga.nextSibling);
  td.appendChild(f); f.hidden = false; riga.hidden = true; f.title.focus();
}
function ctChiudiRapido() {
  var tr = document.getElementById('ct-rapido-riga'), f = document.getElementById('ct-rapido');
  if (!tr) return;
  document.body.appendChild(f); f.hidden = true;
  var riga = document.getElementById('ct-riga-' + f.uno.value); if (riga) riga.hidden = false;
  tr.remove();
}
document.getElementById('ct-q').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); this.nextElementSibling.click(); } });
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
