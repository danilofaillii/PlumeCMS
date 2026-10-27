<?php
/**
 * Contatti: richieste di Laser Coaching da chiamare, iscritti all'ebook, file dell'ebook.
 * Motore in includes/moduli.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/moduli.php';
require_admin(); // sicurezza (ruoli): solo amministratore
moduli_schema();

$adminTitle = 'Contatti';

function contatti_csv(string $nome, array $righe): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    if ($righe) fputcsv($f, array_keys($righe[0]), ';', '"', '\\');
    foreach ($righe as $r) fputcsv($f, sicurezza_csv_riga($r), ';', '"', '\\');
    exit;
}

if (($_GET['scarica'] ?? '') === 'laser') {
    contatti_csv('laser-coaching-' . date('Y-m-d') . '.csv', db()->query('SELECT creato AS richiesta, nome, cognome, email, cellulare, stato, note, pagina FROM ' . tbl('richieste_laser') . ' ORDER BY id DESC')->fetchAll());
}
if (($_GET['scarica'] ?? '') === 'ebook') {
    contatti_csv('iscritti-ebook-' . date('Y-m-d') . '.csv', db()->query('SELECT creato AS richiesta, nome, cognome, email, cellulare, IF(confermato_at IS NULL, "in attesa", "confermato") AS stato, confermato_at AS confermato_il, IF(newsletter = 1, "si", "no") AS newsletter, scaricato AS download, consenso_versione FROM ' . tbl('iscrizioni_ebook') . ' ORDER BY id DESC')->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = $_POST['azione'] ?? '';
    if ($azione === 'stato_laser') {
        $stato = in_array($_POST['stato'] ?? '', ['da_chiamare', 'chiamato', 'annullato'], true) ? $_POST['stato'] : 'da_chiamare';
        db()->prepare('UPDATE ' . tbl('richieste_laser') . ' SET stato = ?, note = ?, aggiornato = aggiornato WHERE id = ?')
            ->execute([$stato, trim((string)($_POST['note'] ?? '')), (int)($_POST['id'] ?? 0)]);
        flash('success', 'Richiesta aggiornata.');
    } elseif ($azione === 'ebook_file') {
        $f = $_FILES['pdf'] ?? null;
        $titolo = trim((string)($_POST['titolo'] ?? '')) ?: 'Fuori dagli schemi';
        $c = moduli_ebook_config();
        $c['titolo'] = $titolo;
        if ($f && $f['error'] === UPLOAD_ERR_OK) {
            $testa = (string)file_get_contents($f['tmp_name'], false, null, 0, 5);
            if ($testa !== '%PDF-') {
                flash('error', 'Il file caricato non è un PDF.');
                header('Location: contatti.php');
                exit;
            }
            $nome = 'ebook-' . date('Ymd-His') . '.pdf';
            if (move_uploaded_file($f['tmp_name'], moduli_cartella_privata() . '/' . $nome)) {
                $vecchio = $c['file'];
                $c['file'] = $nome;
                $c['nome_file'] = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($f['name'], PATHINFO_FILENAME)) . '.pdf';
                $c['caricato'] = date('Y-m-d H:i');
                if ($vecchio !== '' && $vecchio !== $nome) @unlink(moduli_cartella_privata() . '/' . basename($vecchio));
                flash('success', 'Ebook caricato: ' . $c['nome_file'] . '. I link già spediti scaricano il file nuovo.');
            } else {
                flash('error', 'Il file non si è salvato: la cartella new/privato non è scrivibile.');
            }
        } elseif ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
            flash('error', 'Caricamento non riuscito (codice ' . (int)$f['error'] . '). Il limite del server è ' . ini_get('upload_max_filesize') . '.');
        } else {
            flash('success', 'Titolo salvato.');
        }
        save_setting('ebook_config', json_encode($c, JSON_UNESCAPED_UNICODE));
    }
    header('Location: contatti.php');
    exit;
}

$laser = db()->query('SELECT * FROM ' . tbl('richieste_laser') . " ORDER BY stato = 'da_chiamare' DESC, id DESC LIMIT 300")->fetchAll();
$ebook = db()->query('SELECT * FROM ' . tbl('iscrizioni_ebook') . ' ORDER BY id DESC LIMIT 300')->fetchAll();
$numeri = db()->query('SELECT
    (SELECT COUNT(*) FROM ' . tbl('richieste_laser') . " WHERE stato = 'da_chiamare') AS da_chiamare,
    (SELECT COUNT(*) FROM " . tbl('iscrizioni_ebook') . ' WHERE confermato_at IS NOT NULL) AS confermati,
    (SELECT COUNT(*) FROM ' . tbl('iscrizioni_ebook') . ' WHERE confermato_at IS NULL) AS in_attesa,
    (SELECT COUNT(*) FROM ' . tbl('iscrizioni_ebook') . ' WHERE newsletter = 1 AND confermato_at IS NOT NULL) AS newsletter')->fetch();
$cfg = moduli_ebook_config();
$file = moduli_ebook_file();
$notifica = posta_notifica_email();
$posta = posta_config();

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.cont-numeri{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
.cont-numeri div{background:#fff;border:1px solid var(--border);border-radius:10px;padding:1rem}
.cont-numeri b{display:block;font-size:1.8rem}
.cont-avviso{background:#fff4e5;border:1px solid #f0c27a;border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem}
.stato-da_chiamare{color:#b42318;font-weight:700}.stato-chiamato{color:#2d6a31}.stato-annullato{color:#888}
.cont-form{display:flex;gap:.4rem;align-items:center;flex-wrap:wrap}
.cont-form input[type=text]{min-width:160px;padding:.3rem .5rem;border:1px solid var(--border);border-radius:6px}
@media(max-width:900px){.cont-numeri{grid-template-columns:1fr 1fr}}
</style>

<?php if (!$posta): ?><div class="cont-avviso">La posta SMTP non è configurata: le email partono con mail() di PHP, se il server la consente. Impostazioni, scheda Posta.</div><?php endif; ?>
<?php if ($notifica === ''): ?><div class="cont-avviso">Manca l'indirizzo dove ricevere l'avviso delle richieste di Laser Coaching: Impostazioni, scheda Posta, ultimo campo.</div><?php endif; ?>
<?php if (!$file): ?><div class="cont-avviso">Manca il PDF dell'ebook: chi conferma l'email riceve un link che per ora non scarica niente. Caricalo qui sotto.</div><?php endif; ?>

<div class="cont-numeri">
  <div><b><?= (int)$numeri['da_chiamare'] ?></b>Laser Coaching da chiamare</div>
  <div><b><?= (int)$numeri['confermati'] ?></b>Ebook, email confermate</div>
  <div><b><?= (int)$numeri['in_attesa'] ?></b>Ebook, in attesa di conferma</div>
  <div><b><?= (int)$numeri['newsletter'] ?></b>Consenso alla newsletter</div>
</div>

<div class="form-card">
  <h3>Laser Coaching</h3>
  <p style="font-size:.85rem;color:var(--text-muted)">Sul sito c'è la promessa di una chiamata entro 24 ore. In cima quelle ancora da fare. <a href="?scarica=laser">Scarica tutto (CSV)</a></p>
  <div style="overflow-x:auto"><table class="table">
    <tr><th>Arrivata</th><th>Nome</th><th>Cellulare</th><th>Email</th><th>Stato e note</th></tr>
    <?php foreach ($laser as $r): $scade = strtotime($r['creato']) + 86400; ?>
    <tr>
      <td><?= h(date('d/m H:i', strtotime($r['creato']))) ?><?php if ($r['stato'] === 'da_chiamare'): ?><br><small class="<?= $scade < time() ? 'stato-da_chiamare' : '' ?>">entro <?= h(date('d/m H:i', $scade)) ?></small><?php endif; ?></td>
      <td><?= h($r['nome'] . ' ' . $r['cognome']) ?></td>
      <td><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $r['cellulare'])) ?>"><?= h($r['cellulare']) ?></a></td>
      <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></td>
      <td><form method="post" class="cont-form"><?= csrf_field() ?><input type="hidden" name="azione" value="stato_laser"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <select name="stato" class="stato-<?= h($r['stato']) ?>">
          <?php foreach (['da_chiamare' => 'Da chiamare', 'chiamato' => 'Chiamato', 'annullato' => 'Annullato'] as $k => $v): ?><option value="<?= $k ?>" <?= $r['stato'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="note" value="<?= h((string)$r['note']) ?>" placeholder="note">
        <button class="btn btn-secondary btn-sm" type="submit">Salva</button></form></td>
    </tr>
    <?php endforeach; if (!$laser): ?><tr><td colspan="5">Ancora nessuna richiesta.</td></tr><?php endif; ?>
  </table></div>
</div>

<div class="form-card" style="margin-top:1.5rem">
  <h3>Ebook</h3>
  <form method="post" enctype="multipart/form-data" class="cont-form" style="margin-bottom:1rem">
    <?= csrf_field() ?><input type="hidden" name="azione" value="ebook_file">
    <label>Titolo <input type="text" name="titolo" value="<?= h($cfg['titolo']) ?>"></label>
    <label>PDF <input type="file" name="pdf" accept="application/pdf"></label>
    <button class="btn btn-primary btn-sm" type="submit">Salva</button>
  </form>
  <p style="font-size:.85rem;color:var(--text-muted)">
    File attuale: <?= $file ? h($cfg['nome_file']) . ', ' . (filesize($file) >= 1048576 ? number_format(filesize($file) / 1048576, 1, ',', '') . ' MB' : max(1, round(filesize($file) / 1024)) . ' KB') . ', caricato il ' . h($cfg['caricato'] ?? '') : '<b>nessuno</b>' ?>.
    Il file sta in una cartella protetta: si scarica solo con il link dell'email, valido <?= MODULI_DOWNLOAD_GIORNI ?> giorni.
    <a href="?scarica=ebook">Scarica gli iscritti (CSV)</a>
  </p>
  <div style="overflow-x:auto"><table class="table">
    <tr><th>Richiesta</th><th>Nome</th><th>Email</th><th>Cellulare</th><th>Stato</th><th>Newsletter</th><th>Download</th></tr>
    <?php foreach ($ebook as $r): ?>
    <tr>
      <td><?= h(date('d/m/Y H:i', strtotime($r['creato']))) ?></td>
      <td><?= h($r['nome'] . ' ' . $r['cognome']) ?></td>
      <td><?= h($r['email']) ?></td>
      <td><?= h($r['cellulare']) ?></td>
      <td><?= $r['confermato_at'] ? 'confermato' : 'in attesa' ?></td>
      <td><?= $r['newsletter'] ? 'sì' : 'no' ?></td>
      <td><?= (int)$r['scaricato'] ?></td>
    </tr>
    <?php endforeach; if (!$ebook): ?><tr><td colspan="7">Ancora nessuna richiesta.</td></tr><?php endif; ?>
  </table></div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
