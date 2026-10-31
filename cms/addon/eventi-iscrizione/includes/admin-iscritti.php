<?php
/**
 * Eventi > Iscritti (?e=<evento>): chi si è iscritto, chi aspetta, le presenze (Presente / Assente), la cancellazione
 * (fa entrare chi aspetta), il CSV (?csv=1) e "Scrivi a tutti gli iscritti". Ogni azione in POST con il token CSRF.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Iscritti agli eventi';
$eventi = evi_eventi();
$eid = (int)($_GET['e'] ?? $_POST['e'] ?? 0);
if (!$eid) {
    // Senza scelta: il prossimo evento, altrimenti l'ultimo.
    foreach ($eventi as $x) if (max((int)$x['inizio_ts'], (int)$x['fine_ts']) >= time()) { $eid = (int)$x['id']; break; }
    if (!$eid && $eventi) $eid = (int)end($eventi)['id'];
}
$e = $eid ? evi_evento($eid) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $e) {
    verify_csrf();
    $i = evi_iscrizione((int)($_POST['iscrizione'] ?? 0));
    if ($i && (int)$i['evento_id'] !== (int)$e['id']) $i = null;
    switch ($_POST['azione'] ?? '') {
        case 'presente': case 'assente':
            if ($i) {
                db()->prepare('UPDATE ' . tbl('evi_iscrizioni') . ' SET presente = ?, aggiornato = ? WHERE id = ?')->execute([$_POST['azione'] === 'presente' ? 1 : 0, date('Y-m-d H:i:s'), (int)$i['id']]);
                // 2.1: per gli altri addon (Raccolta recensioni invita chi era presente, e non chi era assente).
                try { cms_evento('iscrizione_presenza', evi_iscrizione((int)$i['id']), evi_evento((int)$e['id'])); } catch (Throwable $x) { error_log('eventi: evento iscrizione_presenza: ' . $x->getMessage()); }
            }
            break;
        case 'cancella':
            if ($i && evi_cancella($i, 'attivita')) flash('success', 'Iscrizione di ' . $i['nome'] . ' cancellata: le è partita l\'email. Se qualcuno aspettava, è entrato.');
            break;
        case 'scrivi':
            $ogg = evi_riga(evi_testo($_POST['oggetto'] ?? '', 150));
            $msg = evi_testo($_POST['messaggio'] ?? '', 5000, true);
            if ($ogg === '' || $msg === '') { flash('error', 'Scrivi l\'oggetto e il messaggio.'); break; }
            [$ok, $ko] = evi_scrivi_a_tutti($e, $ogg, $msg, !empty($_POST['anche_attesa']));
            flash($ko ? 'error' : 'success', "Email mandate: $ok." . ($ko ? " Non partite: $ko (controlla Impostazioni > Configurazione email)." : ''));
            break;
    }
    evi_admin_vai('iscritti', ['e' => $e['id']]);
}

$iscritti = [];
if ($e) {
    $st = db()->prepare('SELECT * FROM ' . tbl('evi_iscrizioni') . " WHERE evento_id = ? ORDER BY FIELD(stato, 'confermata', 'attesa', 'cancellata'), id");
    $st->execute([(int)$e['id']]);
    $iscritti = $st->fetchAll();
}
if ($e && isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="iscritti-' . $e['slug'] . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nome', 'Email', 'Telefono', 'Posti', 'Stato', 'Presente', 'Iscritto il'], ';', '"', '\\');
    // Le celle che Excel leggerebbe come formula (= + - @) prendono un apice davanti: i nomi arrivano da chiunque.
    foreach ($iscritti as $i) fputcsv($out, sicurezza_csv_riga([$i['nome'], $i['email'], $i['telefono'], (int)$i['posti'], EVI_STATI[$i['stato']] ?? $i['stato'], $i['presente'] === null ? '' : ((int)$i['presente'] ? 'sì' : 'no'), $i['creato']]), ';', '"', '\\');
    exit;
}
require CMS_ADMIN . '/includes/admin-header.php';
evi_admin_schede('iscritti');
if (!$e): ?>
<p>Nessun evento ancora. <a href="<?= h(evi_admin_url('eventi', ['nuovo' => 1])) ?>">Aggiungi il primo</a>.</p>
<?php else:
    $conf = array_filter($iscritti, fn($x) => $x['stato'] === 'confermata');
    $att = array_filter($iscritti, fn($x) => $x['stato'] === 'attesa');
    $posti = array_sum(array_map(fn($x) => (int)$x['posti'], $conf));
?>
<form method="get" class="pa-barra"><input type="hidden" name="a" value="eventi-iscrizione"><input type="hidden" name="p" value="iscritti">
  <label class="pa-campo" style="flex-direction:row;align-items:center;gap:.5rem">Evento <select name="e" data-invia-al-cambio style="padding:.45rem .6rem;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text)">
    <?php foreach (array_reverse($eventi) as $x): ?><option value="<?= (int)$x['id'] ?>"<?= (int)$x['id'] === (int)$e['id'] ? ' selected' : '' ?>><?= h($x['titolo'] . ' (' . date('d/m/Y', (int)$x['inizio_ts']) . ')') ?></option><?php endforeach; ?>
  </select></label>
  <noscript><button class="btn btn-secondary btn-sm">Apri</button></noscript>
  <a class="btn btn-secondary btn-sm" href="<?= h(evi_admin_url('iscritti', ['e' => $e['id'], 'csv' => 1])) ?>">Scarica CSV</a>
</form>
<p id="evi-totali"><b><?= h((string)$e['titolo']) ?></b>, <?= h(evi_quando($e)) ?>: <b><?= $posti ?><?= (int)$e['posti'] > 0 ? ' su ' . (int)$e['posti'] : '' ?></b> posti presi da <?= count($conf) ?> iscritti<?= $att ? ', <b>' . count($att) . '</b> in lista d\'attesa' : '' ?>.</p>
<div class="admin-table-wrap"><table class="pa-tabella">
  <thead><tr><th>Chi</th><th>Posti</th><th>Stato</th><th>Presenza</th><th></th></tr></thead>
  <tbody>
  <?php if (!$iscritti): ?><tr><td colspan="5"><div class="empty-state"><p>Nessun iscritto ancora.</p></div></td></tr><?php endif; ?>
  <?php foreach ($iscritti as $i): ?>
  <tr data-iscrizione="<?= (int)$i['id'] ?>" data-stato="<?= h((string)$i['stato']) ?>">
    <td><b><?= h((string)$i['nome']) ?></b><br><span class="pa-muto"><?= h((string)$i['email']) ?> <?= h((string)$i['telefono']) ?></span></td>
    <td><?= (int)$i['posti'] ?></td>
    <td><?= evi_stato_badge((string)$i['stato']) ?><?= $i['stato'] === 'attesa' ? ' <span class="pa-muto">posto ' . evi_posizione($i) . '</span>' : '' ?></td>
    <td><?php if ($i['stato'] === 'confermata'): ?><form method="post" class="pa-azioni"><?= csrf_field() ?><input type="hidden" name="e" value="<?= (int)$e['id'] ?>"><input type="hidden" name="iscrizione" value="<?= (int)$i['id'] ?>">
      <button class="btn btn-<?= $i['presente'] === 1 || $i['presente'] === '1' ? 'primary' : 'secondary' ?> btn-sm" name="azione" value="presente">Presente</button><button class="btn btn-<?= $i['presente'] === 0 || $i['presente'] === '0' ? 'primary' : 'secondary' ?> btn-sm" name="azione" value="assente">Assente</button></form><?php endif; ?></td>
    <td><?php if ($i['stato'] !== 'cancellata'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="e" value="<?= (int)$e['id'] ?>"><input type="hidden" name="iscrizione" value="<?= (int)$i['id'] ?>"><button class="btn btn-danger btn-sm" name="azione" value="cancella" data-conferma="Cancellare l'iscrizione di <?= h((string)$i['nome']) ?>? Le arriva un'email.">Cancella</button></form><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<form method="post" class="pa-modulo" id="evi-scrivi" style="margin-top:1.4rem"><?= csrf_field() ?><input type="hidden" name="e" value="<?= (int)$e['id'] ?>">
  <h3>Scrivi a tutti gli iscritti</h3>
  <div class="pa-campi">
    <label class="pa-largo">Oggetto<input name="oggetto" required maxlength="150" placeholder="Cambio di orario"></label>
    <label class="pa-largo">Messaggio<textarea name="messaggio" rows="5" required maxlength="5000" placeholder="Ciao, la serata comincia alle 21:30 invece che alle 21."></textarea><span class="pa-aiuto">Arriva a ognuno con il suo nome e il riepilogo dell'evento. Testo semplice.</span></label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="anche_attesa" value="1"> Anche a chi è in lista d'attesa</label>
  </div>
  <p><button class="btn btn-primary" name="azione" value="scrivi" data-conferma="Mandare l'email a tutti gli iscritti?">Manda</button></p>
</form>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
