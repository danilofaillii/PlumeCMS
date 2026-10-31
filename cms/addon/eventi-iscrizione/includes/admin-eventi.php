<?php
/**
 * Eventi > Eventi: l'elenco degli eventi e la scheda di un evento (?id=N, ?nuovo=1): titolo, quando, dove, descrizione,
 * foto, posti, posti per persona, chiusura delle iscrizioni, lista d'attesa, stato. Aumentando i posti entrano da soli
 * i primi in lista d'attesa.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Eventi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['azione'] ?? '') === 'elimina') {
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('evi_iscrizioni') . " WHERE evento_id = ? AND stato <> 'cancellata'");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) { flash('error', 'Questo evento ha degli iscritti: mettilo "Annullato" (e scrivi agli iscritti da Iscritti) invece di eliminarlo.'); evi_admin_vai('eventi', ['id' => $id]); }
        $vecchio = evi_evento($id);
        db()->prepare('DELETE FROM ' . tbl('evi_iscrizioni') . ' WHERE evento_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM ' . tbl('evi_eventi') . ' WHERE id = ?')->execute([$id]);
        if ($vecchio) { try { cms_evento('evento_eliminato', $vecchio); } catch (Throwable $x) { error_log('eventi: evento evento_eliminato: ' . $x->getMessage()); } }
        flash('success', 'Evento eliminato.');
        evi_admin_vai('eventi');
    }
    $titolo = evi_testo($_POST['titolo'] ?? '', 200);
    $inizio = evi_admin_ts($_POST['inizio'] ?? '');
    $torna = fn(string $m) => [flash('error', $m), evi_admin_vai('eventi', $id ? ['id' => $id] : ['nuovo' => 1])];
    if ($titolo === '') $torna('Scrivi il titolo dell\'evento.');
    if (!$inizio) $torna('Scrivi giorno e ora dell\'evento.');
    $fine = evi_admin_ts($_POST['fine'] ?? '');
    if ($fine && $fine <= $inizio) $fine = 0;
    $chiusura = evi_admin_ts($_POST['chiusura'] ?? '');
    $foto = evi_testo($_POST['foto'] ?? '', 500);
    if ($foto !== '' && !preg_match('~^(/[^\s"<>]*|https?://[^\s"<>]+)$~', $foto)) $torna('L\'indirizzo della foto deve cominciare con / (una foto di Media) oppure con https://.');
    $slug = evi_slug(evi_testo($_POST['slug'] ?? '', 120) ?: $titolo);
    // Un indirizzo già usato da un altro evento prende un numero in fondo.
    $base = $slug;
    for ($n = 2; ; $n++) {
        $st = db()->prepare('SELECT id FROM ' . tbl('evi_eventi') . ' WHERE slug = ? AND id <> ?');
        $st->execute([$slug, $id]);
        if (!$st->fetchColumn()) break;
        $slug = $base . '-' . $n;
    }
    $stato = in_array($_POST['stato'] ?? '', ['bozza', 'pubblicato', 'annullato'], true) ? $_POST['stato'] : 'bozza';
    $val = [$slug, $titolo, date('Y-m-d H:i:s', $inizio), $inizio, $fine, evi_testo($_POST['luogo'] ?? '', 200), evi_testo($_POST['indirizzo'] ?? '', 255), evi_testo($_POST['descrizione'] ?? '', 6000, true),
        $foto, evi_testo($_POST['prezzo'] ?? '', 60), max(0, min(100000, (int)($_POST['posti'] ?? 0))), max(1, min(50, (int)($_POST['max_persona'] ?? 4))), $chiusura, !empty($_POST['attesa']) ? 1 : 0, $stato, date('Y-m-d H:i:s')];
    $prima = $id ? evi_evento($id) : null;
    if ($id) {
        db()->prepare('UPDATE ' . tbl('evi_eventi') . ' SET slug = ?, titolo = ?, inizio = ?, inizio_ts = ?, fine_ts = ?, luogo = ?, indirizzo = ?, descrizione = ?, foto = ?, prezzo = ?, posti = ?, max_persona = ?, chiusura_ts = ?, attesa = ?, stato = ?, aggiornato = ? WHERE id = ?')->execute([...$val, $id]);
    } else {
        db()->prepare('INSERT INTO ' . tbl('evi_eventi') . ' (slug, titolo, inizio, inizio_ts, fine_ts, luogo, indirizzo, descrizione, foto, prezzo, posti, max_persona, chiusura_ts, attesa, stato, aggiornato, creato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$val, date('Y-m-d H:i:s')]);
        $id = (int)db()->lastInsertId();
    }
    // 2.1: un evento rinviato (la fine torna nel futuro) o annullato non è più "svolto": gli inviti "Com'è andata?" non
    // ancora partiti si annullano (Raccolta recensioni ascolta evento_cambiato) e, se si svolge, ripartono alla fine nuova.
    $dopo = evi_evento($id);
    if ($prima && $dopo && ($stato === 'annullato' || evi_fine($dopo) !== evi_fine($prima))) {
        if ($prima['svolto_at'] !== null && ($stato === 'annullato' || evi_fine($dopo) > time())) db()->prepare('UPDATE ' . tbl('evi_eventi') . ' SET svolto_at = NULL WHERE id = ?')->execute([$id]);
        try { cms_evento('evento_cambiato', evi_evento($id)); } catch (Throwable $x) { error_log('eventi: evento evento_cambiato: ' . $x->getMessage()); }
    }
    $entrati = evi_promuovi($id);
    flash('success', 'Evento «' . $titolo . '» salvato' . ($stato === 'pubblicato' ? ': la sua pagina è ' . cms_url_sito() . '/evento/' . $slug . '/' : ' (' . ($stato === 'bozza' ? 'bozza: sul sito non si vede ancora' : 'annullato') . ')') . '.' . ($entrati ? " $entrati dalla lista d'attesa sono entrati e hanno ricevuto l'email." : ''));
    evi_admin_vai('eventi', ['id' => $id]);
}

$id = (int)($_GET['id'] ?? 0);
$e = $id ? evi_evento($id) : (isset($_GET['nuovo']) ? ['id' => 0, 'slug' => '', 'titolo' => '', 'inizio_ts' => 0, 'fine_ts' => 0, 'luogo' => '', 'indirizzo' => '', 'descrizione' => '', 'foto' => '', 'prezzo' => '', 'posti' => 20, 'max_persona' => 4, 'chiusura_ts' => 0, 'attesa' => 1, 'stato' => 'bozza'] : null);
$eventi = evi_eventi();
require CMS_ADMIN . '/includes/admin-header.php';
evi_admin_schede('eventi');
if ($e): ?>
<p><a href="<?= h(evi_admin_url('eventi')) ?>">&lsaquo; Tutti gli eventi</a></p>
<form method="post" class="pa-modulo" id="evi-evento"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
  <h3><?= $e['id'] ? 'Modifica l\'evento' : 'Nuovo evento' ?> <?= $e['id'] ? evi_stato_badge((string)$e['stato']) : '' ?></h3>
  <div class="pa-campi">
    <label class="pa-largo">Titolo<input name="titolo" required maxlength="200" value="<?= h((string)$e['titolo']) ?>" placeholder="Serata di degustazione"></label>
    <label>Inizio<input type="datetime-local" name="inizio" required value="<?= h(evi_admin_locale((int)$e['inizio_ts'])) ?>"></label>
    <label>Fine (facoltativa)<input type="datetime-local" name="fine" value="<?= h(evi_admin_locale((int)$e['fine_ts'])) ?>"></label>
    <label>Luogo<input name="luogo" maxlength="200" value="<?= h((string)$e['luogo']) ?>" placeholder="In negozio"></label>
    <label>Indirizzo<input name="indirizzo" maxlength="255" value="<?= h((string)$e['indirizzo']) ?>" placeholder="Via Rizzoli 1, Bologna"></label>
    <label>Prezzo (facoltativo)<input name="prezzo" maxlength="60" value="<?= h((string)$e['prezzo']) ?>" placeholder="15 € a persona"></label>
    <label>Foto (indirizzo)<input name="foto" maxlength="500" value="<?= h((string)$e['foto']) ?>" placeholder="/uploads/2026/11/serata.jpg"><span class="pa-aiuto">In Media, apri la foto e copia il suo indirizzo.</span></label>
    <label class="pa-largo">Descrizione<textarea name="descrizione" rows="6" maxlength="6000" placeholder="Cosa succede, per chi è, cosa portare."><?= h((string)$e['descrizione']) ?></textarea><span class="pa-aiuto">Testo semplice: una riga vuota fa un paragrafo nuovo.</span></label>
    <label>Posti in tutto<input type="number" name="posti" min="0" max="100000" value="<?= (int)$e['posti'] ?>"><span class="pa-aiuto">0 = senza limite.</span></label>
    <label>Posti al massimo per persona<input type="number" name="max_persona" min="1" max="50" value="<?= (int)$e['max_persona'] ?>"><span class="pa-aiuto">Chi si iscrive può portare qualcuno: per esempio 4.</span></label>
    <label>Iscrizioni chiuse dal (facoltativo)<input type="datetime-local" name="chiusura" value="<?= h(evi_admin_locale((int)$e['chiusura_ts'])) ?>"><span class="pa-aiuto">Vuoto: si chiudono all'inizio dell'evento.</span></label>
    <label>Indirizzo della pagina<input name="slug" maxlength="120" value="<?= h((string)$e['slug']) ?>" placeholder="si fa da solo dal titolo"></label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="attesa" value="1"<?= $e['attesa'] ? ' checked' : '' ?>> Lista d'attesa quando è pieno (chi si cancella lascia il posto al primo in attesa)</label>
    <label>Stato<select name="stato"><?php foreach (['bozza' => 'Bozza (non si vede sul sito)', 'pubblicato' => 'Pubblicato', 'annullato' => 'Annullato'] as $k => $n): ?><option value="<?= $k ?>"<?= $e['stato'] === $k ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></label>
  </div>
  <p class="pa-azioni" style="margin-top:1rem"><button class="btn btn-primary">Salva</button>
  <?php if ($e['id'] && $e['stato'] !== 'bozza'): ?><a class="btn btn-secondary" href="<?= h(cms_url_sito() . evi_url_evento($e)) ?>" target="_blank">Vedi la pagina</a><?php endif; ?>
  <?php if ($e['id']): ?><a class="btn btn-secondary" href="<?= h(evi_admin_url('iscritti', ['e' => $e['id']])) ?>">Iscritti</a><?php endif; ?></p>
</form>
<?php if ($e['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn btn-danger btn-sm" name="azione" value="elimina" data-conferma="Eliminare l'evento?">Elimina l'evento</button></form><?php endif; ?>
<?php else: ?>
<div class="pa-barra"><h2>Eventi</h2><a class="btn btn-primary" href="<?= h(evi_admin_url('eventi', ['nuovo' => 1])) ?>">Aggiungi un evento</a></div>
<p class="pa-aiuto" style="max-width:52rem">Ogni evento pubblicato ha la sua pagina con il modulo d'iscrizione. Per mostrarli in una pagina del sito usa i widget <b>Eventi con iscrizione</b> (l'elenco) e <b>Iscrizione all'evento</b> (un evento solo).</p>
<div class="admin-table-wrap"><table class="pa-tabella">
  <thead><tr><th>Quando</th><th>Evento</th><th>Iscritti</th><th>Stato</th></tr></thead>
  <tbody>
  <?php if (!$eventi): ?><tr><td colspan="4"><div class="empty-state"><p>Nessun evento ancora.</p></div></td></tr><?php endif; ?>
  <?php foreach (array_reverse($eventi) as $x): ?>
  <tr data-evento="<?= (int)$x['id'] ?>">
    <td style="white-space:nowrap"><?= h(date('d/m/Y H:i', (int)$x['inizio_ts'])) ?></td>
    <td><a href="<?= h(evi_admin_url('eventi', ['id' => $x['id']])) ?>"><b><?= h((string)$x['titolo']) ?></b></a><br><span class="pa-muto"><?= h((string)$x['luogo']) ?></span></td>
    <td><a href="<?= h(evi_admin_url('iscritti', ['e' => $x['id']])) ?>"><?= (int)$x['occupati'] ?><?= (int)$x['posti'] > 0 ? ' su ' . (int)$x['posti'] : '' ?> posti</a><?= (int)$x['in_attesa'] ? '<br><span class="pa-muto">' . (int)$x['in_attesa'] . ' in attesa</span>' : '' ?></td>
    <td><?= evi_stato_badge((string)$x['stato']) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
