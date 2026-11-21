<?php
/**
 * Recensioni > Inviti: le email "Com'è andata?" in coda, mandate, usate. Si aggiungono a mano (un elenco di indirizzi di
 * clienti, con la spunta che dice che si può scrivere loro) oppure da sole dopo un appuntamento svolto, se l'addon
 * Prenotazioni è acceso. "Manda adesso" spedisce quelli arrivati alla loro ora senza aspettare il cron.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Inviti a lasciare una recensione';

/** Gli indirizzi di un elenco scritto a mano: "giulia@x.it", "Giulia Rossi <giulia@x.it>", "Giulia Rossi; giulia@x.it". */
function rec_admin_leggi_elenco(string $testo): array {
    $out = [];
    foreach (preg_split('/\R/', $testo) ?: [] as $riga) {
        $riga = rec_testo($riga, 400);
        if ($riga === '') continue;
        if (preg_match('/^(.*?)[\s;,]*<([^<>\s]+@[^<>\s]+)>\s*$/u', $riga, $m)) { $out[] = [trim($m[2]), trim($m[1], " \"';,")]; continue; }
        if (preg_match('/^(.*?)[\s;,]+([^\s;,]+@[^\s;,]+)$/u', $riga, $m)) { $out[] = [trim($m[2]), trim($m[1], " \"';,")]; continue; }
        foreach (preg_split('/[\s;,]+/', $riga) ?: [] as $x) if (str_contains($x, '@')) $out[] = [$x, ''];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    switch ($_POST['azione'] ?? '') {
        case 'aggiungi':
            if (empty($_POST['consenso'])) { flash('error', 'Serve la spunta: puoi scrivere solo a clienti che ti hanno dato l\'indirizzo.'); break; }
            $elenco = rec_admin_leggi_elenco((string)($_POST['elenco'] ?? ''));
            if (count($elenco) > REC_MAX_A_MANO) { flash('error', 'Al massimo ' . REC_MAX_A_MANO . ' indirizzi alla volta.'); break; }
            $quando = ($_POST['quando'] ?? '') === 'subito' ? time() : time() + max(0, (int)rec_impostazioni()['giorni_dopo']) * 86400;
            $servizio = rec_testo($_POST['servizio'] ?? '', 150);
            $si = 0; $no = [];
            foreach ($elenco as [$email, $nome]) {
                if (rec_invito_crea($email, $nome, 'manuale', null, $servizio, $quando)) $si++; else $no[] = $email;
            }
            flash($si ? 'success' : 'error', ($si ? ($si === 1 ? '1 invito in coda' : "$si inviti in coda") . ($_POST['quando'] === 'subito' ? ': partono al prossimo giro del cron, oppure premi Manda adesso.' : ', partono fra ' . (int)rec_impostazioni()['giorni_dopo'] . ' giorni.') : 'Nessun invito aggiunto.')
                . ($no ? ' Saltati ' . count($no) . ' (indirizzo sbagliato, già invitati, o hanno chiesto di non ricevere queste email): ' . implode(', ', array_slice($no, 0, 10)) . '.' : ''));
            break;
        case 'manda':
            $r = rec_cron();
            flash('success', $r !== '' ? 'Fatto: ' . $r . '.' : 'Nessun invito da mandare adesso.');
            break;
        case 'annulla':
            db()->prepare('UPDATE ' . tbl('rec_inviti') . ' SET annullato = 1 WHERE id = ? AND inviato_at IS NULL')->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Invito annullato.');
            break;
        case 'elimina':
            db()->prepare('DELETE FROM ' . tbl('rec_inviti') . ' WHERE id = ? AND usato_at IS NULL')->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Invito eliminato.');
            break;
    }
    rec_admin_vai('inviti');
}
$inviti = db()->query('SELECT * FROM ' . tbl('rec_inviti') . ' ORDER BY id DESC LIMIT 300')->fetchAll();
$imp = rec_impostazioni();
require CMS_ADMIN . '/includes/admin-header.php';
rec_admin_schede('inviti');
?>
<form method="post" class="pa-modulo" id="rec-aggiungi"><?= csrf_field() ?>
  <h3>Chiedi una recensione a dei clienti</h3>
  <div class="pa-campi">
    <label class="pa-largo">Indirizzi email, uno per riga<textarea name="elenco" rows="5" required placeholder="giulia.rossi@esempio.it&#10;Marco Bianchi &lt;marco@esempio.it&gt;"></textarea><span class="pa-aiuto">Anche con il nome davanti: l'email comincia con "Ciao Marco". Al massimo <?= REC_MAX_A_MANO ?> alla volta.</span></label>
    <label>Servizio (facoltativo)<input name="servizio" maxlength="150" placeholder="Taglio e piega"></label>
    <label>Quando parte<select name="quando"><option value="subito">Subito</option><option value="dopo">Fra <?= (int)$imp['giorni_dopo'] ?> giorni</option></select></label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="consenso" value="1" required> Sono miei clienti e mi hanno dato il loro indirizzo: posso scrivere loro per chiedere com'è andata.</label>
  </div>
  <p><button class="btn btn-primary" name="azione" value="aggiungi">Metti in coda</button></p>
</form>
<form method="post" class="pa-barra"><?= csrf_field() ?><h2>Inviti</h2><button class="btn btn-secondary btn-sm" name="azione" value="manda" id="rec-manda">Manda adesso quelli arrivati alla loro ora</button></form>
<?php if (!$imp['da_prenotazioni']): ?><p class="pa-aiuto">Gli inviti dopo gli appuntamenti sono spenti (Impostazioni).</p><?php endif; ?>
<div class="admin-table-wrap"><table class="pa-tabella">
  <thead><tr><th>Chi</th><th>Da</th><th>Stato</th><th></th></tr></thead>
  <tbody>
  <?php if (!$inviti): ?><tr><td colspan="4"><div class="empty-state"><p>Nessun invito ancora.</p></div></td></tr><?php endif; ?>
  <?php foreach ($inviti as $i):
      if ($i['usato_at']) $stato = 'Recensione lasciata il ' . date('d/m/Y', strtotime((string)$i['usato_at']));
      elseif ((int)$i['annullato']) $stato = 'Annullato';
      elseif ($i['inviato_at']) $stato = 'Mandato il ' . date('d/m/Y', strtotime((string)$i['inviato_at'])) . (strtotime((string)$i['scade_at']) < time() ? ', scaduto' : ', il link vale fino al ' . date('d/m', strtotime((string)$i['scade_at'])));
      else $stato = 'In coda: parte il ' . date('d/m/Y H:i', strtotime((string)$i['invia_dopo']));
  ?>
  <tr data-invito="<?= (int)$i['id'] ?>">
    <td><b><?= h((string)$i['nome'] ?: '-') ?></b><br><span class="pa-muto"><?= h((string)$i['email']) ?></span></td>
    <td class="pa-muto"><?= ['prenotazione' => 'Appuntamento svolto', 'evento' => 'Dopo un evento'][$i['origine']] ?? 'A mano' ?><?= $i['servizio'] !== '' ? '<br>' . h((string)$i['servizio']) : '' ?></td>
    <td><?= h($stato) ?></td>
    <td class="pa-azioni">
      <?php if (!$i['inviato_at'] && !(int)$i['annullato']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-secondary btn-sm" name="azione" value="annulla">Annulla</button></form><?php endif; ?>
      <?php if (!$i['usato_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-danger btn-sm" name="azione" value="elimina" data-conferma="Eliminare l'invito e l'indirizzo?">Elimina</button></form><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
