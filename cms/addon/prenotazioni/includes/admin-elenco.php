<?php
/**
 * Prenotazioni > Prenotazioni: l'elenco (prossime, da confermare, passate, annullate) e la scheda di una prenotazione
 * (?id=) con le azioni: conferma, annulla con un messaggio per il cliente, sposta, non presentato, elimina i dati.
 * Ogni azione in POST con il token CSRF.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Prenotazioni';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $p = pren_leggi((int)($_POST['id'] ?? 0));
    $az = (string)($_POST['azione'] ?? '');
    if (!$p) { flash('error', 'Prenotazione non trovata.'); pren_admin_vai('elenco'); }
    switch ($az) {
        case 'conferma':
            flash(pren_cambia_stato($p, 'confermata') ? 'success' : 'error', pren_leggi((int)$p['id'])['stato'] === 'confermata' ? 'Confermata: al cliente è partita l\'email di conferma.' : 'Non si può confermare.');
            break;
        case 'annulla':
            $e = pren_annulla($p, 'attivita', pren_testo($_POST['messaggio'] ?? '', 1000, true));
            flash($e === '' ? 'success' : 'error', $e === '' ? 'Annullata: al cliente è partita l\'email' . (trim((string)($_POST['messaggio'] ?? '')) !== '' ? ' con il tuo messaggio.' : '.') : 'Questa prenotazione non è più attiva.');
            break;
        case 'non_presentato':
            flash(pren_cambia_stato($p, 'non_presentato') ? 'success' : 'error', pren_leggi((int)$p['id'])['stato'] === 'non_presentato' ? 'Segnata: il cliente non si è presentato.' : 'Si può segnare solo un appuntamento già cominciato.');
            break;
        case 'svolta':
            flash(pren_cambia_stato($p, 'svolta') ? 'success' : 'error', 'Segnata come svolta.');
            break;
        case 'sposta':
            $e = pren_sposta($p, (string)($_POST['giorno'] ?? ''), (string)($_POST['ora'] ?? ''), false);
            flash($e === '' ? 'success' : 'error', $e === '' ? 'Spostata: al cliente è partita l\'email con il nuovo orario.' : 'Quell\'orario non è libero (o è fuori dagli orari di lavoro): scegline un altro.');
            break;
        case 'elimina':
            if (in_array($p['stato'], PREN_STATI_ATTIVI, true) && (int)$p['inizio_ts'] > time()) { flash('error', 'Prima annulla la prenotazione: è ancora da fare.'); break; }
            db()->prepare('DELETE FROM ' . tbl('pren_prenotazioni') . ' WHERE id = ?')->execute([(int)$p['id']]);
            cms_evento('prenotazione_eliminata', $p);
            flash('success', 'Prenotazione e dati del cliente eliminati.');
            pren_admin_vai('elenco');
    }
    pren_admin_vai('elenco', ['id' => (int)$p['id']]);
}

$id = (int)($_GET['id'] ?? 0);
$p = $id ? pren_leggi($id) : null;
$filtri = ['prossime' => 'Prossime', 'da_confermare' => 'Da confermare', 'passate' => 'Passate', 'annullate' => 'Annullate', 'tutte' => 'Tutte'];
$filtro = array_key_exists($_GET['filtro'] ?? '', $filtri) ? $_GET['filtro'] : 'prossime';
$cerca = pren_testo($_GET['q'] ?? '', 100);
$ora = time();
$dove = ['prossime' => "stato IN ('confermata','da_confermare') AND inizio_ts >= $ora", 'da_confermare' => "stato = 'da_confermare'", 'passate' => "stato IN ('svolta','non_presentato','confermata') AND inizio_ts < $ora", 'annullate' => "stato = 'annullata'", 'tutte' => '1=1'][$filtro];
$arg = [];
if ($cerca !== '') { $dove .= ' AND (nome LIKE ? OR email LIKE ? OR telefono LIKE ?)'; $l = '%' . addcslashes($cerca, '%_\\') . '%'; $arg = [$l, $l, $l]; }
$st = db()->prepare('SELECT * FROM ' . tbl('pren_prenotazioni') . " WHERE $dove ORDER BY inizio_ts " . (in_array($filtro, ['passate', 'annullate', 'tutte'], true) ? 'DESC' : 'ASC') . ' LIMIT 300');
$st->execute($arg);
$elenco = $st->fetchAll();
require CMS_ADMIN . '/includes/admin-header.php';
pren_admin_schede('elenco');

if ($p):
    $attiva = in_array($p['stato'], PREN_STATI_ATTIVI, true);
    $cominciata = (int)$p['inizio_ts'] <= time();
?>
<p><a href="<?= h(pren_admin_url('elenco')) ?>">&lsaquo; Tutte le prenotazioni</a></p>
<div class="pa-modulo" id="pa-scheda" data-stato="<?= h((string)$p['stato']) ?>">
  <h3><?= h((string)$p['nome']) ?> <?= pren_stato_badge((string)$p['stato']) ?></h3>
  <dl class="pa-dettaglio">
    <dt>Servizio</dt><dd><?= h((string)$p['servizio']) ?><?= $p['prezzo'] !== '' ? ' (' . h((string)$p['prezzo']) . ')' : '' ?></dd>
    <dt>Quando</dt><dd><?= h(pren_quando($p)) ?>, fino alle <?= h(pren_ora((int)$p['fine_ts'])) ?></dd>
    <dt>Con</dt><dd><?= h((string)$p['risorsa']) ?></dd>
    <dt>Email</dt><dd><a href="mailto:<?= h((string)$p['email']) ?>"><?= h((string)$p['email']) ?></a></dd>
    <dt>Telefono</dt><dd><a href="tel:<?= h(preg_replace('/[^\d+]/', '', (string)$p['telefono'])) ?>"><?= h((string)$p['telefono']) ?></a></dd>
    <?php if ($p['note'] !== ''): ?><dt>Note</dt><dd><?= nl2br(h((string)$p['note'])) ?></dd><?php endif; ?>
    <?php if ($p['messaggio'] !== ''): ?><dt>Messaggio mandato</dt><dd><?= nl2br(h((string)$p['messaggio'])) ?></dd><?php endif; ?>
    <dt>Prenotata il</dt><dd><?= h(date('d/m/Y H:i', strtotime((string)$p['creato']))) ?><?= $p['promemoria_at'] ? ' &middot; promemoria mandato il ' . h(date('d/m H:i', strtotime((string)$p['promemoria_at']))) : '' ?></dd>
  </dl>
  <div class="pa-azioni">
    <?php if ($p['stato'] === 'da_confermare'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-primary" name="azione" value="conferma">Conferma</button></form>
    <?php endif; ?>
    <?php if ($cominciata && in_array($p['stato'], ['confermata', 'svolta'], true)): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-secondary" name="azione" value="non_presentato" data-conferma="Segnare che il cliente non si è presentato?">Non si è presentato</button></form>
    <?php endif; ?>
    <?php if ($p['stato'] === 'non_presentato'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-secondary" name="azione" value="svolta">Si è presentato (era un errore)</button></form>
    <?php endif; ?>
    <?php if (!$attiva || $cominciata): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-danger" name="azione" value="elimina" data-conferma="Eliminare la prenotazione e i dati del cliente? Non si torna indietro.">Elimina i dati</button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ($attiva): ?>
<form method="post" class="pa-modulo" id="pa-sposta"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <h3>Sposta</h3>
  <div class="pa-campi">
    <label>Nuovo giorno<input type="date" name="giorno" required value="<?= h(date('Y-m-d', (int)$p['inizio_ts'])) ?>"></label>
    <label>Nuova ora<input name="ora" required placeholder="9:30" pattern="\d{1,2}:\d{2}" value="<?= h(pren_ora((int)$p['inizio_ts'])) ?>"><span class="pa-aiuto">Deve essere un orario libero, dentro gli orari di lavoro.</span></label>
  </div>
  <p><button class="btn btn-secondary" name="azione" value="sposta">Sposta e avvisa il cliente</button></p>
</form>
<form method="post" class="pa-modulo" id="pa-annulla"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <h3>Annulla</h3>
  <label class="pa-campo">Messaggio per il cliente (facoltativo)<textarea name="messaggio" rows="3" maxlength="1000" placeholder="Ci scusiamo: quel giorno siamo chiusi per un imprevisto. Chiamaci per fissare un altro giorno."></textarea></label>
  <p><button class="btn btn-danger" name="azione" value="annulla" data-conferma="Annullare la prenotazione? Al cliente parte un'email.">Annulla e avvisa il cliente</button></p>
</form>
<?php endif; ?>
<?php else: ?>
<div class="pa-barra">
  <nav class="pa-schede" style="border:0;margin:0" aria-label="Filtro">
    <?php foreach ($filtri as $k => $n): ?><a href="<?= h(pren_admin_url('elenco', ['filtro' => $k])) ?>"<?= $k === $filtro ? ' class="attivo"' : '' ?>><?= h($n) ?></a><?php endforeach; ?>
  </nav>
  <form method="get" class="pa-azioni"><input type="hidden" name="a" value="prenotazioni"><input type="hidden" name="p" value="elenco"><input type="hidden" name="filtro" value="<?= h($filtro) ?>">
    <input type="search" name="q" value="<?= h($cerca) ?>" placeholder="Cerca nome, email, telefono" aria-label="Cerca" style="padding:.45rem .6rem;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text)"><button class="btn btn-secondary btn-sm">Cerca</button></form>
</div>
<div class="admin-table-wrap"><table class="pa-tabella">
  <thead><tr><th>Quando</th><th>Cliente</th><th>Servizio</th><th>Con</th><th>Stato</th></tr></thead>
  <tbody>
  <?php if (!$elenco): ?><tr><td colspan="5"><div class="empty-state"><p>Nessuna prenotazione qui.</p></div></td></tr><?php endif; ?>
  <?php foreach ($elenco as $x): ?>
    <tr data-pren-id="<?= (int)$x['id'] ?>">
      <td style="white-space:nowrap"><a href="<?= h(pren_admin_url('elenco', ['id' => $x['id']])) ?>"><?= h(date('d/m/Y', (int)$x['inizio_ts'])) ?> <?= h(pren_ora((int)$x['inizio_ts'])) ?></a></td>
      <td><a href="<?= h(pren_admin_url('elenco', ['id' => $x['id']])) ?>"><b><?= h((string)$x['nome']) ?></b></a><br><span class="pa-muto"><?= h((string)$x['telefono']) ?></span></td>
      <td><?= h((string)$x['servizio']) ?></td>
      <td><?= h((string)$x['risorsa']) ?></td>
      <td><?= pren_stato_badge((string)$x['stato']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
