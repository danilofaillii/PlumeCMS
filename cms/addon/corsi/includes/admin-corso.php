<?php
/**
 * Pannello, Corsi > un corso (addon corsi): i dati del corso, il programma (moduli e lezioni, da riordinare con le frecce),
 * chi lo segue e a che punto è; per un corso "solo i membri a cui lo assegno", l'elenco delle assegnazioni.
 */
require_once __DIR__ . '/corsi.php';
corsi_schema();
$corso = corsi_leggi((int)($_GET['id'] ?? 0));
if (!$corso) { http_response_code(404); exit('Corso non trovato.'); }
$cid = (int)$corso['id'];
$qui = corsi_admin_url('corso', ['id' => $cid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $ancora = '';
    try {
        $mod = fn() => (($m = corsi_modulo((int)($_POST['modulo'] ?? 0))) && (int)$m['corso_id'] === $cid) ? $m : throw new RuntimeException('Modulo non trovato.');
        $lez = fn() => (($l = corsi_lezione((int)($_POST['lezione'] ?? 0))) && (int)$l['corso_id'] === $cid) ? $l : throw new RuntimeException('Lezione non trovata.');
        switch ($azione) {
            case 'salva':
                corsi_salva($cid, ['titolo' => (string)($_POST['titolo'] ?? ''), 'slug' => (string)($_POST['slug'] ?? ''), 'descrizione' => (string)($_POST['descrizione'] ?? ''),
                    'immagine' => (string)($_POST['immagine'] ?? ''), 'accesso' => (string)($_POST['accesso'] ?? ''), 'stato' => (string)($_POST['stato'] ?? '')]);
                flash('success', 'Corso salvato.');
                break;
            case 'modulo_nuovo': corsi_modulo_crea($cid, (string)($_POST['titolo'] ?? '')); flash('success', 'Modulo aggiunto.'); $ancora = '#programma'; break;
            case 'modulo_rinomina':
                $m = $mod(); $t = membri_testo((string)($_POST['titolo'] ?? ''), 200);
                if ($t === '') throw new RuntimeException('Il nome del modulo non può essere vuoto.');
                db()->prepare('UPDATE ' . tbl('corsi_moduli') . ' SET titolo = ? WHERE id = ?')->execute([$t, (int)$m['id']]);
                flash('success', 'Nome del modulo salvato.'); $ancora = '#programma'; break;
            case 'modulo_su': case 'modulo_giu': corsi_sposta('modulo', (int)$mod()['id'], $azione === 'modulo_su' ? -1 : 1); $ancora = '#programma'; break;
            case 'modulo_elimina': corsi_modulo_elimina((int)$mod()['id']); flash('success', 'Modulo eliminato.'); $ancora = '#programma'; break;
            case 'lezione_nuova':
                $id = corsi_lezione_crea($cid, (int)$mod()['id'], (string)($_POST['titolo'] ?? ''));
                flash('success', 'Lezione aggiunta: adesso scrivi il testo e aggiungi il video.');
                header('Location: ' . corsi_admin_url('lezione', ['id' => $id])); exit;
            case 'lezione_su': case 'lezione_giu': corsi_sposta('lezione', (int)$lez()['id'], $azione === 'lezione_su' ? -1 : 1); $ancora = '#programma'; break;
            case 'lezione_elimina': $l = $lez(); corsi_lezione_elimina((int)$l['id']); flash('success', 'Lezione «' . $l['titolo'] . '» eliminata.'); $ancora = '#programma'; break;
            case 'assegna':
                $m = membri_leggi((int)($_POST['membro'] ?? 0));
                if (!$m) throw new RuntimeException('Scegli un membro.');
                corsi_assegna($cid, (int)$m['id'], true);
                if (!empty($_POST['avvisa'])) membri_manda($m['email'], 'Un corso per te: ' . $corso['titolo'], 'Hai un corso nuovo', '<p>Ciao ' . h($m['nome']) . ', nella tua area riservata di ' . h(membri_nome_sito()) . ' trovi il corso <b>' . h($corso['titolo']) . '</b>.</p>', 'Apri il corso', membri_url('/corsi/' . $corso['slug'] . '/'));
                flash('success', '«' . $m['email'] . '» può seguire il corso.'); $ancora = '#iscritti'; break;
            case 'togli':
                $m = membri_leggi((int)($_POST['membro'] ?? 0));
                if (!$m) throw new RuntimeException('Membro non trovato.');
                corsi_assegna($cid, (int)$m['id'], false);
                flash('success', '«' . $m['email'] . '» non può più seguire il corso. Il suo avanzamento resta, se glielo riassegni.'); $ancora = '#iscritti'; break;
            default: throw new RuntimeException('Azione sconosciuta.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . $qui . $ancora);
    exit;
}

$moduli = corsi_moduli($cid);
$lezioniPer = [];
foreach (corsi_lezioni($cid) as $l) $lezioniPer[(int)$l['modulo_id']][] = $l;
$totale = array_sum(array_map('count', $lezioniPer));
$iscritti = corsi_iscritti($cid);
$assegnati = array_flip(array_map(fn($r) => (int)$r['membro_id'], array_filter($iscritti, fn($r) => (int)$r['assegnato'] === 1)));
$candidati = array_filter(membri_elenco('attivo', '', 2000), fn($r) => !isset($assegnati[(int)$r['id']]));
$sito = rtrim(cms_url_sito(), '/') . '/corsi/' . $corso['slug'] . '/';
$adminTitle = 'Corso: ' . $corso['titolo'];
require CMS_ADMIN . '/includes/admin-header.php';
?>
<style>.co-griglia{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.25rem}@media(max-width:1100px){.co-griglia{grid-template-columns:1fr}}
.co-modulo{border:1px solid var(--border);border-radius:var(--radius);padding:.8rem 1rem;margin-bottom:1rem}.co-modulo-testa{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
.co-modulo-testa input[type=text]{flex:1;min-width:10rem}.co-lez{list-style:none;margin:.6rem 0;padding:0}.co-lez li{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;padding:.4rem 0;border-top:1px solid var(--border)}
.co-lez li .co-nome{flex:1;min-width:10rem}.co-lez form,.co-modulo-testa form{display:inline}.co-tab{overflow-x:auto}.co-acc label{display:flex;gap:.5rem;font-weight:400;align-items:center}</style>
<p><a href="<?= h(corsi_admin_url('corsi')) ?>">&larr; Tutti i corsi</a> &middot; <a href="<?= h($sito . ($corso['stato'] !== 'pubblicato' ? '?preview=1' : '')) ?>" target="_blank" id="co-vedi">Vedi sul sito<?= $corso['stato'] !== 'pubblicato' ? ' (anteprima: è in bozza)' : '' ?></a></p>
<div class="co-griglia">
<div>
  <div class="sc-scatola"><h3>Il corso</h3><div class="sc-corpo">
    <form method="post" id="co-dati"><?= csrf_field() ?><input type="hidden" name="azione" value="salva">
      <label for="co-titolo">Titolo</label><input type="text" id="co-titolo" name="titolo" value="<?= h($corso['titolo']) ?>" maxlength="200" required>
      <label for="co-slug">Indirizzo</label><input type="text" id="co-slug" name="slug" value="<?= h($corso['slug']) ?>" maxlength="100" pattern="[a-z0-9\-]+">
      <p class="hint">Il corso si apre da <code>/corsi/<?= h($corso['slug']) ?>/</code>. Solo minuscole, numeri e trattini.</p>
      <label for="co-descrizione">Descrizione</label><textarea id="co-descrizione" name="descrizione" rows="4" maxlength="3000"><?= h((string)$corso['descrizione']) ?></textarea>
      <label for="co-immagine">Immagine (facoltativa)</label><input type="text" id="co-immagine" name="immagine" value="<?= h($corso['immagine']) ?>" maxlength="500" placeholder="/uploads/... (copiala da Media)">
      <fieldset class="co-acc" style="border:0;padding:0;margin:.8rem 0"><legend style="font-weight:600">Chi può seguirlo</legend>
        <?php foreach (CORSI_ACCESSI as $k => $et): ?><label><input type="radio" name="accesso" value="<?= h($k) ?>"<?= $corso['accesso'] === $k ? ' checked' : '' ?>> <?= h($et) ?></label><?php endforeach; ?>
      </fieldset>
      <label for="co-stato">Stato</label><select id="co-stato" name="stato"><?php foreach (CORSI_STATI as $k => $et): ?><option value="<?= h($k) ?>"<?= $corso['stato'] === $k ? ' selected' : '' ?>><?= h($et) ?></option><?php endforeach; ?></select>
      <p class="hint">In bozza il corso non si vede sul sito (tu lo vedi con "Vedi sul sito").</p>
      <button class="btn btn-primary" type="submit">Salva il corso</button>
    </form>
  </div></div>
  <div class="sc-scatola" id="iscritti"><h3>Chi segue il corso (<?= count($iscritti) ?>)</h3><div class="sc-corpo co-tab">
    <?php if ($corso['accesso'] === 'assegnati'): ?>
      <form method="post" id="co-assegna" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:.8rem"><?= csrf_field() ?><input type="hidden" name="azione" value="assegna">
        <span style="flex:1;min-width:14rem"><label for="co-membro">Assegna il corso a</label><select id="co-membro" name="membro" required><option value="">Scegli un membro attivo</option>
          <?php foreach ($candidati as $r): ?><option value="<?= (int)$r['id'] ?>"><?= h($r['nome'] . ' (' . $r['email'] . ')') ?></option><?php endforeach; ?></select></span>
        <label style="display:flex;gap:.4rem;align-items:center;font-weight:400"><input type="checkbox" name="avvisa" value="1" checked> mandagli un'email</label>
        <button class="btn btn-primary" type="submit">Assegna</button>
      </form>
    <?php else: ?><p class="hint">Il corso è aperto a tutti i membri: qui compare chi l'ha cominciato.</p><?php endif; ?>
    <?php if (!$iscritti): ?><p style="color:var(--text-muted)">Ancora nessuno.</p><?php else: ?>
    <div class="ad-tabella"><table class="admin-table" id="co-iscritti" style="width:100%"><thead><tr><th>Membro</th><th>Avanzamento</th><th>Ultima visita</th><th>Certificato</th><th></th></tr></thead><tbody>
    <?php foreach ($iscritti as $r): $pc = $totale ? (int)floor((int)$r['fatte'] * 100 / $totale) : 0; ?>
      <tr data-email="<?= h($r['email']) ?>"><td><?= h($r['nome']) ?><br><small style="color:var(--text-muted)"><?= h($r['email']) ?><?= (int)$r['assegnato'] ? ' &middot; assegnato' : '' ?></small></td>
        <td class="co-av"><?= (int)$r['fatte'] ?> su <?= $totale ?> (<?= $pc ?>%)</td>
        <td><?= $r['ultimo'] ? h(date('d/m/Y H:i', strtotime($r['ultimo']))) : '-' ?></td>
        <td><?= $r['certificato'] ? '<code>' . h($r['certificato']) . '</code>' : '-' ?></td>
        <td><?php if ((int)$r['assegnato'] && $corso['accesso'] === 'assegnati'): ?><form method="post" data-conferma="Togliere il corso a <?= h($r['email']) ?>?"><?= csrf_field() ?><input type="hidden" name="azione" value="togli"><input type="hidden" name="membro" value="<?= (int)$r['membro_id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Togli</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div></div>
</div>
<div class="sc-scatola" id="programma"><h3>Programma: <?= count($moduli) ?> moduli, <?= $totale ?> lezioni</h3><div class="sc-corpo">
  <?php foreach ($moduli as $mi => $m): $mid = (int)$m['id']; ?>
    <div class="co-modulo" data-modulo="<?= $mid ?>">
      <div class="co-modulo-testa">
        <form method="post" style="display:flex;gap:.4rem;flex:1;min-width:12rem"><?= csrf_field() ?><input type="hidden" name="azione" value="modulo_rinomina"><input type="hidden" name="modulo" value="<?= $mid ?>">
          <input type="text" name="titolo" value="<?= h($m['titolo']) ?>" maxlength="200" aria-label="Nome del modulo"><button class="btn btn-secondary btn-sm" type="submit">Salva</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="modulo_su"><input type="hidden" name="modulo" value="<?= $mid ?>"><button class="btn btn-secondary btn-sm" type="submit" title="Sposta il modulo in su"<?= $mi === 0 ? ' disabled' : '' ?>>&uarr;</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="modulo_giu"><input type="hidden" name="modulo" value="<?= $mid ?>"><button class="btn btn-secondary btn-sm" type="submit" title="Sposta il modulo in giù"<?= $mi === count($moduli) - 1 ? ' disabled' : '' ?>>&darr;</button></form>
        <form method="post" data-conferma="Eliminare il modulo «<?= h($m['titolo']) ?>»?"><?= csrf_field() ?><input type="hidden" name="azione" value="modulo_elimina"><input type="hidden" name="modulo" value="<?= $mid ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form>
      </div>
      <ol class="co-lez">
      <?php $ls = $lezioniPer[$mid] ?? []; foreach ($ls as $li => $l): ?>
        <li data-lezione="<?= h($l['slug']) ?>"><span class="co-nome"><a href="<?= h(corsi_admin_url('lezione', ['id' => $l['id']])) ?>"><?= h($l['titolo']) ?></a><?= $l['video_url'] !== '' || $l['video_file'] !== '' ? ' <small title="Ha un video">&#9654; video</small>' : '' ?></span>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="lezione_su"><input type="hidden" name="lezione" value="<?= (int)$l['id'] ?>"><button class="btn btn-secondary btn-sm co-su" type="submit" title="Sposta in su"<?= $li === 0 ? ' disabled' : '' ?>>&uarr;</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="lezione_giu"><input type="hidden" name="lezione" value="<?= (int)$l['id'] ?>"><button class="btn btn-secondary btn-sm co-giu" type="submit" title="Sposta in giù"<?= $li === count($ls) - 1 ? ' disabled' : '' ?>>&darr;</button></form>
          <a class="btn btn-secondary btn-sm" href="<?= h(corsi_admin_url('lezione', ['id' => $l['id']])) ?>">Modifica</a>
          <form method="post" data-conferma="Eliminare la lezione «<?= h($l['titolo']) ?>»?"><?= csrf_field() ?><input type="hidden" name="azione" value="lezione_elimina"><input type="hidden" name="lezione" value="<?= (int)$l['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form>
        </li>
      <?php endforeach; ?>
      </ol>
      <form method="post" class="co-nuova-lezione" style="display:flex;gap:.4rem;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="azione" value="lezione_nuova"><input type="hidden" name="modulo" value="<?= $mid ?>">
        <input type="text" name="titolo" maxlength="200" required placeholder="Titolo della lezione nuova" aria-label="Titolo della lezione nuova" style="flex:1;min-width:12rem"><button class="btn btn-primary btn-sm" type="submit">Aggiungi lezione</button></form>
    </div>
  <?php endforeach; ?>
  <form method="post" id="co-nuovo-modulo" style="display:flex;gap:.4rem;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="azione" value="modulo_nuovo">
    <input type="text" name="titolo" maxlength="200" required placeholder="Nome del modulo nuovo" aria-label="Nome del modulo nuovo" style="flex:1;min-width:12rem"><button class="btn btn-secondary" type="submit">Aggiungi modulo</button></form>
  <p class="hint">Le frecce cambiano l'ordine: i membri seguono le lezioni in quest'ordine, modulo dopo modulo.</p>
</div></div>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
