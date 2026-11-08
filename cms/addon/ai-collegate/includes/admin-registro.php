<?php
/**
 * AI collegate > Registro: ogni chiamata delle AI (data, AI, strumento, contenuto, esito) con il filtro, e le foto importate
 * con autore e licenza.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Registro delle AI';
$esiti = ['' => 'Tutti gli esiti', 'ok' => 'Riuscite', 'errore' => 'Con errore', 'negato' => 'Negate'];
$fEsito = array_key_exists($_GET['esito'] ?? '', $esiti) ? (string)($_GET['esito'] ?? '') : '';
$fClient = aic_testo($_GET['client'] ?? '', 200);
$fStrumento = preg_replace('/[^a-z_]/', '', (string)($_GET['strumento'] ?? ''));
$dove = []; $par = [];
if ($fEsito !== '') { $dove[] = 'esito = ?'; $par[] = $fEsito; }
if ($fClient !== '') { $dove[] = 'client = ?'; $par[] = $fClient; }
if ($fStrumento !== '') { $dove[] = 'strumento = ?'; $par[] = $fStrumento; }
$st = db()->prepare('SELECT * FROM ' . tbl('ai_registro') . ($dove ? ' WHERE ' . implode(' AND ', $dove) : '') . ' ORDER BY id DESC LIMIT 200');
$st->execute($par);
$righe = $st->fetchAll();
$clienti = db()->query('SELECT DISTINCT client FROM ' . tbl('ai_registro') . " WHERE client <> '' ORDER BY client LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
$strumenti = db()->query('SELECT DISTINCT strumento FROM ' . tbl('ai_registro') . ' ORDER BY strumento LIMIT 100')->fetchAll(PDO::FETCH_COLUMN);
$foto = db()->query('SELECT * FROM ' . tbl('ai_foto') . ' ORDER BY id DESC LIMIT 100')->fetchAll();
$nomiEsito = ['ok' => 'riuscita', 'errore' => 'errore', 'negato' => 'negata'];
require CMS_ADMIN . '/includes/admin-header.php';
aic_admin_schede('registro');
?>
<div class="form-card" id="registro">
  <h3>Le azioni delle AI</h3>
  <form method="get" class="aic-filtro">
    <input type="hidden" name="a" value="ai-collegate"><input type="hidden" name="p" value="registro">
    <div class="form-group"><label for="aic-f-client">AI</label><select name="client" id="aic-f-client"><option value="">Tutte</option><?php foreach ($clienti as $c): ?><option value="<?= h((string)$c) ?>"<?= $c === $fClient ? ' selected' : '' ?>><?= h((string)$c) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="aic-f-strumento">Strumento</label><select name="strumento" id="aic-f-strumento"><option value="">Tutti</option><?php foreach ($strumenti as $c): ?><option value="<?= h((string)$c) ?>"<?= $c === $fStrumento ? ' selected' : '' ?>><?= h((string)$c) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="aic-f-esito">Esito</label><select name="esito" id="aic-f-esito"><?php foreach ($esiti as $k => $n): ?><option value="<?= h($k) ?>"<?= $k === $fEsito ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-secondary btn-sm" type="submit">Filtra</button>
  </form>
  <?php if (!$righe): ?>
  <div class="empty-state"><?= $dove ? 'Nessuna azione con questi filtri: togli un filtro.' : 'Ancora nessuna azione: qui compare ogni chiamata di un\'AI collegata, con l\'esito.' ?></div>
  <?php else: ?>
  <table class="table" id="aic-registro">
    <thead><tr><th>Quando</th><th>AI</th><th>Strumento</th><th>Contenuto</th><th>Esito</th><th>Dettagli</th></tr></thead>
    <tbody>
    <?php foreach ($righe as $r): ?>
    <tr>
      <td><?= h(aic_data($r['creato'])) ?></td>
      <td><?= h((string)$r['client']) ?></td>
      <td><code><?= h((string)$r['strumento']) ?></code></td>
      <td><?php if ((int)$r['contenuto_id'] > 0 && in_array($r['tipo'], ['articoli', 'pagine'], true)): ?><a href="contenuto.php?tipo=<?= h((string)$r['tipo']) ?>&amp;id=<?= (int)$r['contenuto_id'] ?>"><?= h((string)$r['tipo']) ?> <?= (int)$r['contenuto_id'] ?></a><?php else: ?>&ndash;<?php endif; ?></td>
      <td><span class="aic-esito aic-esito-<?= h((string)$r['esito']) ?>"><?= ['ok' => '&#10003; ', 'errore' => '! ', 'negato' => '&#10007; '][$r['esito']] ?? '' ?><?= h($nomiEsito[$r['esito']] ?? (string)$r['esito']) ?></span></td>
      <td><?= h((string)$r['dettagli']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint">Le ultime 200 righe; il registro tiene sei mesi.</p>
  <?php endif; ?>
</div>
<div class="form-card" id="foto">
  <h3>Foto importate dalle AI</h3>
  <?php if (!$foto): ?>
  <div class="empty-state">Ancora nessuna foto importata. Quando un'AI importa una foto da un archivio, qui trovi autore e licenza da citare.</div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Foto</th><th>Archivio</th><th>Autore</th><th>Licenza</th><th>Importata il</th></tr></thead>
    <tbody>
    <?php foreach ($foto as $f): ?>
    <tr>
      <td><a href="<?= h(cms_url_sito() . (string)$f['url']) ?>" target="_blank" rel="noopener"><?= h(basename((string)$f['url'])) ?></a><?= $f['titolo'] !== '' ? '<br><span class="hint">' . h((string)$f['titolo']) . '</span>' : '' ?></td>
      <td><?= h((string)$f['archivio']) ?><?= $f['fonte_url'] !== '' ? ' (<a href="' . h((string)$f['fonte_url']) . '" target="_blank" rel="noopener nofollow">originale</a>)' : '' ?></td>
      <td><?= $f['autore_url'] !== '' ? '<a href="' . h((string)$f['autore_url']) . '" target="_blank" rel="noopener nofollow">' . h((string)$f['autore']) . '</a>' : h((string)$f['autore']) ?></td>
      <td><?= $f['licenza_url'] !== '' ? '<a href="' . h((string)$f['licenza_url']) . '" target="_blank" rel="noopener nofollow">' . h((string)$f['licenza']) . '</a>' : h((string)$f['licenza']) ?></td>
      <td><?= h(aic_data($f['creato'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint">Con la licenza CC BY va citato l'autore vicino alla foto o nei crediti del sito.</p>
  <?php endif; ?>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
