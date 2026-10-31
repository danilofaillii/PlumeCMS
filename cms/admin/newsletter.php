<?php
/**
 * Newsletter: campagne, iscritti, importazione (per esempio da Brevo), ritmo di invio.
 * Motore in includes/newsletter.php, editor in newsletter-campagna.php, invio in cron-newsletter.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();

$adminTitle = 'Newsletter';
$scheda = in_array($_GET['scheda'] ?? '', ['campagne', 'iscritti', 'liste', 'impostazioni'], true) ? $_GET['scheda'] : 'campagne';

/** I filtri della scheda Iscritti, uguali per l'elenco e per le azioni su tutti i risultati. */
function nl_filtro(array $q): array {
    $cerca = trim((string)($q['cerca'] ?? ''));
    $stato = in_array($q['stato'] ?? '', ['attivo', 'in_attesa', 'uscito', 'rimbalzato', 'errori'], true) ? $q['stato'] : '';
    $lista = (int)($q['lista'] ?? 0);
    $w = []; $par = [];
    if ($cerca !== '') { $q = '%' . addcslashes($cerca, '%_\\') . '%'; $w[] = "(i.email LIKE ? ESCAPE '\\\\' OR i.nome LIKE ? ESCAPE '\\\\' OR i.cognome LIKE ? ESCAPE '\\\\')"; array_push($par, $q, $q, $q); }
    // 'errori': attivi con tre errori temporanei di fila o più (2.0.0).
    if ($stato === 'errori') $w[] = "i.stato = 'attivo' AND i.errori_di_fila >= 3";
    elseif ($stato !== '') { $w[] = 'i.stato = ?'; $par[] = $stato; }
    if ($lista > 0) { $w[] = 'EXISTS (SELECT 1 FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = i.id AND l.lista_id = ?)'; $par[] = $lista; }
    if ($lista === -1) { $w[] = 'NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = i.id)'; }
    return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $par, ['cerca' => $cerca, 'stato' => $stato, 'lista' => $lista]];
}

function nl_torna_iscritti(): never {
    header('Location: newsletter.php?' . http_build_query(['scheda' => 'iscritti'] + array_filter(nl_filtro($_POST)[2])));
    exit;
}

if (($_GET['scarica'] ?? '') === 'iscritti') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-iscritti-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, ['email', 'nome', 'cognome', 'stato', 'fonte', 'consenso', 'consenso_versione', 'iscritto_il', 'uscito_il', 'liste'], ';', '"', '\\');
    foreach (db()->query('SELECT i.email, i.nome, i.cognome, i.stato, i.fonte, i.consenso_at, i.consenso_versione, i.creato, i.uscito_at,
        (SELECT GROUP_CONCAT(n.nome ORDER BY n.id SEPARATOR \', \') FROM ' . tbl('newsletter_iscritti_liste') . ' l JOIN ' . tbl('newsletter_liste') . ' n ON n.id = l.lista_id WHERE l.iscritto_id = i.id) AS liste
        FROM ' . tbl('newsletter_iscritti') . ' i ORDER BY i.id') as $r) {
        fputcsv($f, sicurezza_csv_riga(array_values($r)), ';', '"', '\\');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = $_POST['azione'] ?? '';
    if ($azione === 'nuova') {
        $ora = date('Y-m-d H:i:s');
        db()->prepare('INSERT INTO ' . tbl('newsletter_campagne') . " (oggetto, corpo, liste, creato, aggiornato) VALUES ('', '', ?, ?, ?)")->execute([(string)newsletter_lista_principale(), $ora, $ora]);
        header('Location: newsletter-campagna.php?id=' . (int)db()->lastInsertId());
        exit;
    }
    if ($azione === 'duplica') {
        $c = newsletter_campagna((int)($_POST['id'] ?? 0));
        if ($c) {
            $ora = date('Y-m-d H:i:s');
            db()->prepare('INSERT INTO ' . tbl('newsletter_campagne') . ' (oggetto, anteprima, corpo, pulsante_testo, pulsante_link, liste, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?)')
                ->execute(['Copia di ' . $c['oggetto'], $c['anteprima'], $c['corpo'], $c['pulsante_testo'], $c['pulsante_link'], $c['liste'], $ora, $ora]);
            header('Location: newsletter-campagna.php?id=' . (int)db()->lastInsertId());
            exit;
        }
    }
    if ($azione === 'elimina') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM ' . tbl('newsletter_campagne') . " WHERE id = ? AND stato = 'bozza'")->execute([$id]);
        flash('success', 'Bozza eliminata.');
    }
    if ($azione === 'importa') {
        $f = $_FILES['csv'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Scegli il file CSV con i contatti (per esempio esportato da Brevo).');
        } elseif (empty($_POST['consenso_brevo'])) {
            flash('error', 'Serve la spunta: i contatti importati devono avere dato il loro consenso.');
        } else {
            $lista = (int)($_POST['lista'] ?? 0);
            $nuova = trim((string)($_POST['lista_nuova'] ?? ''));
            if ($nuova !== '') {
                db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_liste') . ' (nome, creato) VALUES (?, NOW())')->execute([mb_substr($nuova, 0, 100)]);
                $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_liste') . ' WHERE nome = ?');
                $st->execute([mb_substr($nuova, 0, 100)]);
                $lista = (int)$st->fetchColumn();
            }
            // 2.0.0: i flussi della lista partono per gli importati solo con la spunta (spenta di partenza).
            $r = newsletter_importa_csv($f['tmp_name'], $lista ?: newsletter_lista_principale(), !empty($_POST['flussi_import']));
            isset($r['errore']) ? flash('error', $r['errore'])
                : flash('success', "Importati {$r['nuovi']} iscritti nuovi. Già presenti: {$r['gia']} (lasciati com'erano). Saltati perché usciti, rimbalzati o con i dati cancellati: {$r['saltati']}. Righe scartate perché senza email valida: {$r['scartati']}."
                    . (!empty($_POST['flussi_import']) ? ' I flussi della lista sono partiti per gli importati.' : ''));
        }
        header('Location: newsletter.php?scheda=iscritti');
        exit;
    }
    if ($azione === 'lista_crea' || $azione === 'lista_rinomina') {
        $nome = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 100);
        $descr = mb_substr(trim((string)($_POST['descrizione'] ?? '')), 0, 255);
        try {
            if ($nome === '') flash('error', 'Scrivi il nome della lista.');
            elseif ($azione === 'lista_crea') { db()->prepare('INSERT INTO ' . tbl('newsletter_liste') . ' (nome, descrizione, creato) VALUES (?, ?, NOW())')->execute([$nome, $descr]); flash('success', "Lista «{$nome}» creata."); }
            else { db()->prepare('UPDATE ' . tbl('newsletter_liste') . ' SET nome = ?, descrizione = ? WHERE id = ?')->execute([$nome, $descr, (int)$_POST['id']]); flash('success', 'Lista aggiornata.'); }
        } catch (PDOException $e) {
            flash('error', 'Esiste già una lista con questo nome.');
        }
        header('Location: newsletter.php?scheda=liste');
        exit;
    }
    if ($azione === 'lista_elimina') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === newsletter_lista_principale()) {
            flash('error', 'Questa lista la usa il sito per le iscrizioni: si può rinominare, non eliminare.');
        } else {
            db()->prepare('DELETE FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE lista_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM ' . tbl('newsletter_liste') . ' WHERE id = ?')->execute([$id]);
            flash('success', 'Lista eliminata. Gli iscritti restano, nelle altre loro liste.');
        }
        header('Location: newsletter.php?scheda=liste');
        exit;
    }
    if ($azione === 'liste_iscritto') {
        $id = (int)($_POST['id'] ?? 0);
        $scelte = array_map('intval', (array)($_POST['liste'] ?? []));
        db()->prepare('DELETE FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ?')->execute([$id]);
        foreach ($scelte as $l) newsletter_in_lista($id, $l);
        flash('success', 'Liste dell\'iscritto aggiornate.');
        nl_torna_iscritti();
    }
    if ($azione === 'liste_massa') {
        $lista = (int)($_POST['lista_dest'] ?? 0);
        [$where, $par] = nl_filtro($_POST);
        $ids = db()->prepare('SELECT i.id FROM ' . tbl('newsletter_iscritti') . " i$where");
        $ids->execute($par);
        $ids = $ids->fetchAll(PDO::FETCH_COLUMN);
        if (!$lista || !$ids) {
            flash('error', 'Scegli una lista: e i risultati della ricerca devono essere almeno uno.');
        } elseif (($_POST['verso'] ?? '') === 'togli') {
            db()->prepare('DELETE FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE lista_id = ? AND iscritto_id IN (' . implode(',', array_map('intval', $ids)) . ')')->execute([$lista]);
            flash('success', count($ids) . ' iscritti tolti dalla lista.');
        } else {
            foreach ($ids as $i) newsletter_in_lista((int)$i, $lista);
            flash('success', count($ids) . ' iscritti aggiunti alla lista.');
        }
        nl_torna_iscritti();
    }
    if ($azione === 'esci' || $azione === 'cancella') {
        $id = (int)($_POST['id'] ?? 0);
        if ($azione === 'esci') {
            newsletter_esci($id);
            flash('success', 'Iscritto tolto dalla newsletter.');
        } else {
            // Diritto alla cancellazione: via tutti i dati dell'iscritto, anche aperture, clic, flussi e invii dei form
            // (2.0.0); l'impronta dell'email resta nella lista di soppressione, che l'importazione rispetta.
            // 2.1: con gli invii dei form se ne vanno anche i file che avevano allegato (prima, finché gli invii ci sono).
            require_once dirname(__DIR__) . '/includes/form.php';
            try {
                form_schema();
                $st = db()->prepare('SELECT i.id FROM ' . tbl('moduli_invii') . ' i JOIN ' . tbl('newsletter_iscritti') . ' n ON n.email = i.email WHERE n.id = ?');
                $st->execute([$id]);
                form_allegati_cancella($st->fetchAll(PDO::FETCH_COLUMN));
            } catch (Throwable $e) { error_log('cancella dati, file dei form: ' . $e->getMessage()); }
            newsletter_cancella_dati($id);
            form_file_orfani();
            flash('success', 'Dati dell\'iscritto cancellati: iscrizione, liste, aperture e clic, flussi e invii dei form con la sua email. Un\'importazione non lo rimetterà dentro.');
        }
        nl_torna_iscritti();
    }
    if ($azione === 'impostazioni') {
        $rispondi = trim((string)($_POST['rispondi_a'] ?? ''));
        save_setting('newsletter_config', json_encode([
            'per_ora' => max(10, min(2000, (int)($_POST['per_ora'] ?? 150))),
            'per_giorno' => max(0, min(100000, (int)($_POST['per_giorno'] ?? 0))),
            'rispondi_a' => filter_var($rispondi, FILTER_VALIDATE_EMAIL) ? $rispondi : '',
        ]));
        flash('success', 'Impostazioni della newsletter salvate.');
        header('Location: newsletter.php?scheda=impostazioni');
        exit;
    }
    if ($azione === 'giro') {
        $log = newsletter_invia_giro();
        flash('success', 'Giro di invio: ' . ($log ? implode('; ', $log) : 'niente da mandare') . '.');
    }
    header('Location: newsletter.php');
    exit;
}

$numeri = newsletter_numeri();
$liste = newsletter_liste();
$nomeLista = array_column($liste, 'nome', 'id');
$cfg = newsletter_config();
$ultimoGiro = @file(__DIR__ . '/../logs/newsletter.log', FILE_IGNORE_NEW_LINES) ?: [];
$ultimoGiro = end($ultimoGiro) ?: '';
$statoNome = ['bozza' => 'Bozza', 'programmata' => 'Programmata', 'in_invio' => 'In invio', 'sospesa' => 'Sospesa', 'inviata' => 'Inviata'];

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.nl-numeri{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.25rem}
.nl-numeri div{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:1rem}
.nl-numeri b{display:block;font-size:1.8rem}
.nl-schede{display:flex;gap:.25rem;margin-bottom:1rem}
.nl-schede a{padding:.5rem 1rem;border:1px solid var(--border);border-radius:8px 8px 0 0;text-decoration:none;color:var(--text-muted);font-weight:600}
.nl-schede a.attiva{background:var(--surface);color:var(--text)}
.nl-barra{height:6px;background:var(--fondo-2);border-radius:3px;min-width:120px}.nl-barra div{height:6px;background:var(--coral);border-radius:3px}
.nl-riga{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center}
.nl-avviso{background:var(--mustard-light);color:var(--attenzione-testo);border-radius:8px;padding:.6rem .9rem;margin:0 0 1rem}
.nl-errori{display:block;font-size:.78rem;color:var(--attenzione-testo)}
</style>

<div class="nl-numeri">
  <div><b><?= $numeri['attivo'] ?></b>iscritti attivi</div>
  <div><b><?= $numeri['in_attesa'] ?></b>in attesa di conferma</div>
  <div><b><?= $numeri['uscito'] ?></b>usciti</div>
  <div><b><?= $numeri['rimbalzato'] ?></b><a href="?scheda=iscritti&amp;stato=rimbalzato">rimbalzati</a></div>
</div>
<?php if ($numeri['segnalati']): ?><p class="nl-avviso" role="status"><b><?= (int)$numeri['segnalati'] ?></b> <?= $numeri['segnalati'] === 1 ? 'iscritto ha' : 'iscritti hanno' ?> avuto tre errori temporanei di fila: <?= $numeri['segnalati'] === 1 ? 'riceve' : 'ricevono' ?> ancora, ma conviene controllare l'indirizzo. <a href="?scheda=iscritti&amp;stato=errori">Vedi chi</a>.</p><?php endif; ?>

<nav class="nl-schede">
  <?php foreach (['campagne' => 'Campagne', 'iscritti' => 'Iscritti', 'liste' => 'Liste', 'impostazioni' => 'Impostazioni'] as $k => $v): ?>
    <a href="?scheda=<?= $k ?>" class="<?= $scheda === $k ? 'attiva' : '' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($scheda === 'campagne'):
  $campagne = db()->query('SELECT * FROM ' . tbl('newsletter_campagne') . ' ORDER BY id DESC LIMIT 200')->fetchAll(); ?>
<div class="form-card">
  <div class="nl-riga" style="justify-content:space-between;margin-bottom:1rem">
    <h3 style="margin:0">Campagne</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="nuova"><button class="btn btn-primary" type="submit">+ Nuova campagna</button></form>
  </div>
  <div style="overflow-x:auto"><table class="table">
    <tr><th>Oggetto</th><th>Liste</th><th>Stato</th><th>Quando</th><th>Avanzamento</th><th></th></tr>
    <?php foreach ($campagne as $c): $pct = $c['destinatari'] ? round($c['inviate'] / $c['destinatari'] * 100) : 0; ?>
    <tr>
      <td><a href="newsletter-campagna.php?id=<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori($c['oggetto']) ?: '(senza oggetto)') ?></a></td>
      <td><small><?= h(implode(', ', array_map(fn($l) => $nomeLista[$l] ?? '', newsletter_liste_campagna($c)))) ?></small></td>
      <td><?= $statoNome[$c['stato']] ?? h($c['stato']) ?></td>
      <td><?= $c['invio_at'] ? h(date('d/m/Y H:i', strtotime($c['invio_at']))) : '' ?></td>
      <td><?php if ($c['destinatari']): ?><div class="nl-barra"><div style="width:<?= $pct ?>%"></div></div><small><?= (int)$c['inviate'] ?> di <?= (int)$c['destinatari'] ?><?= $c['errori'] ? ', ' . (int)$c['errori'] . ' non partite' : '' ?></small><?php endif; ?></td>
      <td class="nl-riga">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="duplica"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Duplica</button></form>
        <?php if ($c['stato'] === 'bozza'): ?><form method="post" data-conferma="Eliminare questa bozza?"><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Elimina</button></form><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; if (!$campagne): ?><tr><td colspan="6">Ancora nessuna campagna. Premi "Nuova campagna".</td></tr><?php endif; ?>
  </table></div>
  <p style="font-size:.85rem;color:var(--text-muted);margin-top:1rem">
    Le email partono dal cron ogni 5 minuti, al massimo <?= (int)$cfg['per_ora'] ?> l'ora<?= (int)$cfg['per_giorno'] ? ' e ' . (int)$cfg['per_giorno'] . ' al giorno' : '' ?>, contando tutte le email del sito. Ultimo giro: <?= h($ultimoGiro ?: 'nessuno registrato') ?>.
  </p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="giro"><button class="btn btn-secondary btn-sm" type="submit">Fai partire un giro adesso</button></form>
</div>

<?php elseif ($scheda === 'iscritti'):
  [$where, $par, $f] = nl_filtro($_GET);
  $st = db()->prepare('SELECT i.*, (SELECT GROUP_CONCAT(l.lista_id) FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = i.id) AS sue_liste
      FROM ' . tbl('newsletter_iscritti') . " i$where ORDER BY i.id DESC LIMIT 300");
  $st->execute($par);
  $iscritti = $st->fetchAll();
  $totaleSt = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i$where");
  $totaleSt->execute($par);
  $totale = (int)$totaleSt->fetchColumn();
  $nascosti = fn() => '<input type="hidden" name="cerca" value="' . h($f['cerca']) . '"><input type="hidden" name="stato" value="' . h($f['stato']) . '"><input type="hidden" name="lista" value="' . (int)$f['lista'] . '">';
  $sceltaLista = fn($nome, $vuota) => '<select name="' . $nome . '">' . ($vuota !== '' ? '<option value="0">' . $vuota . '</option>' : '')
      . implode('', array_map(fn($l) => '<option value="' . (int)$l['id'] . '">' . h($l['nome']) . '</option>', $liste)) . '</select>'; ?>
<div class="form-card">
  <h3>Importa i contatti</h3>
  <p style="font-size:.85rem;color:var(--text-muted)">Un file CSV con la colonna EMAIL (e, se ci sono, NOME, COGNOME e la data del consenso in una colonna CONSENSO). Da Brevo: Contatti, seleziona una lista, Esporta, formato CSV, solo i contatti iscritti, senza quelli disiscritti o bloccati. Se avevi più liste, esportale una alla volta e scegli qui la lista di arrivo. Chi è già qui resta com'è ed entra anche nella lista scelta; chi è uscito, è rimbalzato o ha chiesto di cancellare i suoi dati resta fuori.</p>
  <form method="post" enctype="multipart/form-data" class="nl-riga">
    <?= csrf_field() ?><input type="hidden" name="azione" value="importa">
    <input type="file" name="csv" accept=".csv,text/csv">
    <label style="font-size:.85rem">nella lista <?= $sceltaLista('lista', '') ?></label>
    <label style="font-size:.85rem">oppure in una lista nuova <input type="text" name="lista_nuova" placeholder="nome" style="padding:.3rem .5rem;border:1px solid var(--border);border-radius:6px"></label>
    <label style="font-size:.85rem"><input type="checkbox" name="consenso_brevo" value="1"> Sono contatti iscritti, con il loro consenso</label>
    <label style="font-size:.85rem"><input type="checkbox" name="flussi_import" value="1"> Fai partire i flussi per gli importati</label>
    <button class="btn btn-primary btn-sm" type="submit">Importa</button>
  </form>
</div>
<div class="form-card" style="margin-top:1.25rem">
  <form method="get" class="nl-riga" style="margin-bottom:1rem">
    <input type="hidden" name="scheda" value="iscritti">
    <input type="text" name="cerca" value="<?= h($f['cerca']) ?>" placeholder="email o nome" style="padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px">
    <select name="stato"><option value="">Tutti gli stati</option><?php foreach (['attivo' => 'Attivi', 'in_attesa' => 'In attesa', 'uscito' => 'Usciti', 'rimbalzato' => 'Rimbalzati', 'errori' => 'Con 3 errori di fila'] as $k => $v): ?><option value="<?= $k ?>" <?= $f['stato'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    <select name="lista"><option value="0">Tutte le liste</option><?php foreach ($liste as $l): ?><option value="<?= (int)$l['id'] ?>" <?= $f['lista'] === (int)$l['id'] ? 'selected' : '' ?>><?= h($l['nome']) ?></option><?php endforeach; ?><option value="-1" <?= $f['lista'] === -1 ? 'selected' : '' ?>>In nessuna lista</option></select>
    <button class="btn btn-secondary btn-sm" type="submit">Cerca</button>
    <a href="?scarica=iscritti" style="margin-left:auto">Scarica tutti (CSV)</a>
  </form>
  <form method="post" class="nl-riga" style="margin-bottom:1rem;font-size:.85rem">
    <?= csrf_field() ?><input type="hidden" name="azione" value="liste_massa"><?= $nascosti() ?>
    <span><b><?= $totale ?></b> risultati.</span>
    <select name="verso"><option value="aggiungi">Aggiungi tutti i risultati</option><option value="togli">Togli tutti i risultati</option></select>
    <span>alla lista</span> <?= $sceltaLista('lista_dest', 'scegli…') ?>
    <button class="btn btn-secondary btn-sm" type="submit" data-conferma="Applicare a <?= $totale ?> iscritti?">Applica</button>
  </form>
  <div style="overflow-x:auto"><table class="table">
    <tr><th>Email</th><th>Nome</th><th>Stato</th><th>Liste</th><th>Da</th><th>Iscritto il</th><th></th></tr>
    <?php foreach ($iscritti as $r): $sue = array_map('intval', array_filter(explode(',', (string)$r['sue_liste']))); ?>
    <tr>
      <td><?= h($r['email']) ?></td><td><?= h(trim($r['nome'] . ' ' . $r['cognome'])) ?></td>
      <td><?= ['attivo' => 'attivo', 'in_attesa' => 'in attesa', 'uscito' => 'uscito', 'rimbalzato' => 'rimbalzato'][$r['stato']] ?? h($r['stato']) ?>
        <?php if ($r['stato'] === 'rimbalzato'): ?><span class="nl-errori" title="<?= h($r['ultimo_errore']) ?>">il server di posta ha rifiutato l'indirizzo: non riceve più niente</span>
        <?php elseif ((int)$r['errori_di_fila'] >= 3): ?><span class="nl-errori" title="<?= h($r['ultimo_errore']) ?>"><?= (int)$r['errori_di_fila'] ?> errori temporanei di fila</span><?php endif; ?></td>
      <td><details><summary style="cursor:pointer"><?= h(implode(', ', array_map(fn($l) => $nomeLista[$l] ?? '', $sue)) ?: 'nessuna') ?></summary>
        <form method="post" style="padding:.4rem 0"><?= csrf_field() ?><input type="hidden" name="azione" value="liste_iscritto"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?= $nascosti() ?>
          <?php foreach ($liste as $l): ?><label style="display:block;font-size:.85rem"><input type="checkbox" name="liste[]" value="<?= (int)$l['id'] ?>" <?= in_array((int)$l['id'], $sue, true) ? 'checked' : '' ?>> <?= h($l['nome']) ?></label><?php endforeach; ?>
          <button class="btn btn-secondary btn-sm" type="submit">Salva le liste</button></form></details></td>
      <td><?= h($r['fonte']) ?></td><td><?= h(date('d/m/Y', strtotime($r['creato']))) ?></td>
      <td class="nl-riga">
        <?php if ($r['stato'] !== 'uscito'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="esci"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?= $nascosti() ?><button class="btn btn-secondary btn-sm" type="submit">Togli dalla newsletter</button></form><?php endif; ?>
        <form method="post" data-conferma="Cancellare tutti i dati di questo iscritto? Non si torna indietro."><?= csrf_field() ?><input type="hidden" name="azione" value="cancella"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?= $nascosti() ?><button class="btn btn-secondary btn-sm" type="submit">Cancella dati</button></form>
      </td>
    </tr>
    <?php endforeach; if (!$iscritti): ?><tr><td colspan="7">Nessun iscritto trovato.</td></tr><?php endif; ?>
  </table></div>
  <?php if ($totale > 300): ?><p style="font-size:.85rem;color:var(--text-muted)">Mostrati i primi 300 su <?= $totale ?>: usa la ricerca per trovare gli altri.</p><?php endif; ?>
</div>

<?php elseif ($scheda === 'liste'): ?>
<div class="form-card">
  <h3>Liste</h3>
  <p style="font-size:.85rem;color:var(--text-muted)">Un iscritto può stare in più liste. Una campagna va alle liste che scegli, e chi sta in due di quelle liste la riceve una volta sola. Chi esce dalla newsletter esce da tutte.</p>
  <div style="overflow-x:auto"><table class="table">
    <tr><th>Nome e descrizione</th><th>Attivi</th><th>In tutto</th><th></th></tr>
    <?php $sistema = [newsletter_lista_principale() => 'iscrizioni dal sito'];
    foreach ($liste as $l): ?>
    <tr>
      <td><form method="post" class="nl-riga"><?= csrf_field() ?><input type="hidden" name="azione" value="lista_rinomina"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <input type="text" name="nome" value="<?= h($l['nome']) ?>" maxlength="100" style="padding:.3rem .5rem;border:1px solid var(--border);border-radius:6px;font-weight:600">
        <input type="text" name="descrizione" value="<?= h($l['descrizione']) ?>" maxlength="255" placeholder="descrizione" style="padding:.3rem .5rem;border:1px solid var(--border);border-radius:6px;min-width:260px">
        <button class="btn btn-secondary btn-sm" type="submit">Salva</button>
        <?php if (isset($sistema[(int)$l['id']])): ?><small style="color:var(--text-muted)">usata dal sito: <?= $sistema[(int)$l['id']] ?></small><?php endif; ?></form></td>
      <td><a href="?scheda=iscritti&amp;lista=<?= (int)$l['id'] ?>&amp;stato=attivo"><?= (int)$l['attivi'] ?></a></td>
      <td><a href="?scheda=iscritti&amp;lista=<?= (int)$l['id'] ?>"><?= (int)$l['totali'] ?></a></td>
      <td><?php if (!isset($sistema[(int)$l['id']])): ?><form method="post" data-conferma="Eliminare la lista? Gli iscritti restano, nelle altre loro liste."><?= csrf_field() ?><input type="hidden" name="azione" value="lista_elimina"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Elimina</button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <h3 style="margin-top:1.5rem">Nuova lista</h3>
  <form method="post" class="nl-riga"><?= csrf_field() ?><input type="hidden" name="azione" value="lista_crea">
    <input type="text" name="nome" maxlength="100" placeholder="Nome, per esempio: Clienti" required style="padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;min-width:280px">
    <input type="text" name="descrizione" maxlength="255" placeholder="Descrizione (facoltativa)" style="padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;min-width:260px">
    <button class="btn btn-primary btn-sm" type="submit">Crea</button>
  </form>
</div>

<?php else: ?>
<div class="form-card">
  <h3>Impostazioni</h3>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="impostazioni">
    <div class="form-group"><label for="per_ora">Email al massimo ogni ora</label><input type="number" id="per_ora" name="per_ora" min="10" max="2000" value="<?= (int)$cfg['per_ora'] ?>">
      <div class="hint">I server condivisi limitano le email per ora: chiedi al tuo hosting il limite del tuo piano e scrivi qui un numero un po' più basso. Contano tutte le email del sito (conferme, avvisi dei form, flussi, campagne): nell'ultima ora ne sono partite <?= posta_inviate(3600) ?>.</div></div>
    <div class="form-group"><label for="per_giorno">Email al massimo al giorno</label><input type="number" id="per_giorno" name="per_giorno" min="0" max="100000" value="<?= (int)$cfg['per_giorno'] ?>">
      <div class="hint">Nelle ultime 24 ore, contando tutte le email del sito. 0 vuol dire nessun limite. Nelle ultime 24 ore ne sono partite <?= posta_inviate(86400) ?>.</div></div>
    <div class="form-group"><label for="rispondi_a">Le risposte alla newsletter arrivano a</label><input type="email" id="rispondi_a" name="rispondi_a" value="<?= h($cfg['rispondi_a']) ?>" placeholder="la tua email">
      <div class="hint">Vuoto: le risposte vanno all'indirizzo che spedisce (scheda Posta).</div></div>
    <button class="btn btn-primary" type="submit">Salva</button>
  </form>
  <p style="font-size:.85rem;color:var(--text-muted);margin-top:1rem">
    Cron da aggiungere in cPanel, ogni 5 minuti:<br><code>/usr/local/bin/php <?= h(cms_radice()) ?>/cron-newsletter.php</code>
  </p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
