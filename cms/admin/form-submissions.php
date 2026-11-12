<?php
/**
 * Invii dei form: le compilazioni dei form costruiti dal pannello (e quelle vecchie del modulo di contatto).
 * Filtro per form, segna come letto, CSV, elimina.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/form.php';
require_admin(); // sicurezza (ruoli): solo amministratore
form_schema();
$adminTitle = 'Invii dei form';
$modulo = (int)($_GET['modulo'] ?? 0);
$forms = form_tutti();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    if (!empty($_POST['uno'])) $ids = [(int)$_POST['uno']];
    $in = implode(',', $ids ?: [0]);
    switch ($_POST['azione'] ?? '') {
        case 'letto': db()->exec('UPDATE ' . tbl('moduli_invii') . " SET letto = 1 WHERE id IN ($in)"); flash('success', 'Segnate come lette.'); break;
        case 'nonletto': db()->exec('UPDATE ' . tbl('moduli_invii') . " SET letto = 0 WHERE id IN ($in)"); flash('success', 'Segnate come da leggere.'); break;
        case 'elimina': form_allegati_cancella($ids); $n = db()->exec('DELETE FROM ' . tbl('moduli_invii') . " WHERE id IN ($in)"); form_file_orfani(); flash('success', "$n eliminate."); break;
        case 'elimina_vecchio': db()->prepare('DELETE FROM ' . tbl('form_submissions') . ' WHERE id = ?')->execute([(int)($_POST['uno'] ?? 0)]); flash('success', 'Eliminata.'); break;
    }
    header('Location: form-submissions.php' . ($modulo ? '?modulo=' . $modulo : ''));
    exit;
}
// 2.1: un file arrivato con un form, solo da qui (pagina dell'amministratore). Si scarica sempre come allegato, con il nome
// com'era e il tipo controllato all'arrivo: il browser non lo apre come pagina.
if (isset($_GET['file'])) {
    $st = db()->prepare('SELECT allegati FROM ' . tbl('moduli_invii') . ' WHERE id = ?');
    $st->execute([(int)$_GET['file']]);
    $al = form_allegati(['allegati' => (string)$st->fetchColumn()]);
    $x = $al[(int)($_GET['n'] ?? 0)] ?? null;
    $f = $x ? form_file_dir() . '/' . $x['file'] : '';
    if (!$x || !is_file($f)) { http_response_code(404); exit('File non trovato: forse è stato cancellato.'); }
    $nome = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$x['nome']) ?: 'file';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $nome . '"; filename*=UTF-8\'\'' . rawurlencode((string)$x['nome']));
    header('Content-Length: ' . filesize($f));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    while (ob_get_level()) ob_end_clean();
    readfile($f);
    exit;
}
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="invii-form-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Data', 'Form', 'Nome', 'Email', 'Dati', 'Pagina'], ';', '"', '\\');
    $st = db()->prepare('SELECT i.*, m.nome AS modulo FROM ' . tbl('moduli_invii') . ' i JOIN ' . tbl('moduli') . ' m ON m.id = i.modulo_id' . ($modulo ? ' WHERE i.modulo_id = ?' : '') . ' ORDER BY i.id DESC');
    $st->execute($modulo ? [$modulo] : []);
    foreach ($st->fetchAll() as $r) {
        $d = json_decode((string)$r['dati'], true) ?: [];
        // sicurezza: le celle che Excel leggerebbe come formula (= + - @) prendono un apice davanti; i campi dei form vengono da anonimi.
        fputcsv($out, sicurezza_csv_riga([$r['creato'], $r['modulo'], $r['nome'], $r['email'], implode(' | ', array_map(fn($k, $v) => "$k: $v", array_keys($d), $d)), $r['pagina']]), ';', '"', '\\');
    }
    exit;
}

$st = db()->prepare('SELECT i.*, m.nome AS modulo FROM ' . tbl('moduli_invii') . ' i JOIN ' . tbl('moduli') . ' m ON m.id = i.modulo_id' . ($modulo ? ' WHERE i.modulo_id = ?' : '') . ' ORDER BY i.id DESC LIMIT 300');
$st->execute($modulo ? [$modulo] : []);
$invii = $st->fetchAll();
// La tabella del vecchio modulo di contatto può mancare su un server nuovo: senza, la pagina vive lo stesso.
try { $vecchi = db()->query('SELECT * FROM ' . tbl('form_submissions') . ' ORDER BY id DESC LIMIT 100')->fetchAll(); } catch (PDOException $e) { $vecchi = []; }
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<div class="ct-testa">
  <div class="ct-schede">
    <a href="form-submissions.php" class="<?= !$modulo ? 'attivo' : '' ?>">Tutti</a>
    <?php foreach ($forms as $f): ?><a href="form-submissions.php?modulo=<?= $f['id'] ?>" class="<?= $modulo === (int)$f['id'] ? 'attivo' : '' ?>"><?= h($f['nome']) ?> <span>(<?= (int)$f['invii'] ?><?= $f['nuovi'] ? ', ' . (int)$f['nuovi'] . ' nuove' : '' ?>)</span></a><?php endforeach; ?>
  </div>
  <a class="btn btn-secondary btn-sm" href="form-submissions.php?csv=1<?= $modulo ? '&amp;modulo=' . $modulo : '' ?>">Scarica CSV</a>
</div>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="uno" value="">
  <div class="ct-barra"><div class="ct-sinistra">
    <select name="azione" id="ct-azione"><option value="">Azioni di gruppo</option><option value="letto">Segna come lette</option><option value="nonletto">Segna da leggere</option><option value="elimina">Elimina</option></select>
    <button type="submit" class="btn btn-secondary btn-sm" data-pn-gruppo="#ct-azione" data-pn-gruppo-elimina="Eliminare le compilazioni spuntate?">Applica</button>
  </div></div>
  <div class="admin-table-wrap ct-tabella">
    <table>
      <thead><tr><th class="ct-col-sel"><input type="checkbox" data-seleziona-tutti=".ct-sel"></th><th>Quando</th><th>Form</th><th>Chi</th><th>Dati</th><th>Pagina</th></tr></thead>
      <tbody>
      <?php if (!$invii): ?><tr><td colspan="6"><div class="empty-state"><p>Nessuna compilazione<?= $modulo ? ' per questo form' : '' ?>.</p></div></td></tr><?php endif; ?>
      <?php foreach ($invii as $i): $d = json_decode((string)$i['dati'], true) ?: []; ?>
        <tr class="ct-riga" style="<?= $i['letto'] ? '' : 'font-weight:600' ?>">
          <td class="ct-col-sel"><input type="checkbox" class="ct-sel" name="ids[]" value="<?= $i['id'] ?>"></td>
          <td class="ct-muto" style="white-space:nowrap"><?= h(substr($i['creato'], 0, 16)) ?><?= $i['letto'] ? '' : ' <span class="badge badge-scheduled">nuova</span>' ?>
            <div class="ct-azioni"><button type="submit" name="azione" value="<?= $i['letto'] ? 'nonletto' : 'letto' ?>" data-uno="<?= $i['id'] ?>"><?= $i['letto'] ? 'Da leggere' : 'Letta' ?></button><button type="submit" name="azione" value="elimina" class="ct-rosso" data-uno="<?= $i['id'] ?>" data-conferma="Eliminare?">Elimina</button></div></td>
          <td class="ct-muto"><?= h($i['modulo']) ?></td>
          <td><?= h($i['nome']) ?><?= $i['email'] ? '<br><a href="mailto:' . h($i['email']) . '" style="font-weight:400;font-size:.82rem">' . h($i['email']) . '</a>' : '' ?></td>
          <td style="font-weight:400;font-size:.85rem"><?php foreach ($d as $k => $v): ?><div><span class="ct-muto"><?= h((string)$k) ?>:</span> <?= nl2br(h(mb_substr((string)$v, 0, 400))) ?></div><?php endforeach; ?>
            <?php foreach (form_allegati($i) as $n => $al): /* 2.1: i file del form */ ?><div><a class="fs-file" href="form-submissions.php?file=<?= (int)$i['id'] ?>&amp;n=<?= $n ?>" download>Scarica <?= h($al['nome']) ?></a> <span class="ct-muto">(<?= max(1, (int)round($al['peso'] / 1024)) ?> KB)</span></div><?php endforeach; ?></td>
          <td class="ct-muto"><?= h((string)$i['pagina']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($vecchi && !$modulo): ?>
  <h3 style="margin:1.5rem 0 .5rem;font-size:.95rem">Invii del vecchio modulo di contatto</h3>
  <div class="admin-table-wrap ct-tabella"><table>
    <thead><tr><th>Quando</th><th>Tipo</th><th>Chi</th><th>Messaggio</th><th></th></tr></thead>
    <tbody><?php foreach ($vecchi as $s): ?><tr><td class="ct-muto"><?= h(substr((string)$s['created_at'], 0, 16)) ?></td><td class="ct-muto"><?= h((string)$s['form_type']) ?></td><td><?= h((string)$s['name']) ?><br><span class="ct-muto"><?= h((string)$s['email']) ?> <?= h((string)$s['phone']) ?></span></td><td style="font-size:.85rem"><?= nl2br(h(mb_substr((string)$s['message'], 0, 400))) ?></td><td><button type="submit" name="azione" value="elimina_vecchio" class="ct-rosso" style="background:none;border:0;cursor:pointer;font-size:.8rem" data-uno="<?= $s['id'] ?>" data-conferma="Eliminare?">Elimina</button></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</form>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
