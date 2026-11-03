<?php
/**
 * 2.10.0: Newsletter e form > Contatti > Segmenti, come Brevo (CRM > Contacts > Segments; docs-sviluppo/brevo-confronto.md,
 * fase 3). Elenco per categoria con la ricerca e il numero di contatti; Crea un segmento: da zero o da un modello, le
 * condizioni con i gruppi E/O, il conteggio che si aggiorna, il nome, la categoria. Da un segmento: vedi i contatti, crea
 * una lista, esporta. Motore in includes/newsletter-segmenti.php, costruttore in admin/assets/segmenti.js.
 * Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-segmenti.php'; // anche su un sito appena aggiornato (opcache)
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_segmenti_schema(); // le tabelle nuove, anche se la pagina è la prima ad aprirsi dopo l'aggiornamento

$adminTitle = 'Segmenti';
$utenteId = (int)(current_user()['id'] ?? 0);

/** Torna all'elenco (o al segmento) dopo un'azione. */
function nls_torna(string $dove = 'newsletter-segmenti.php'): never { header('Location: ' . $dove); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'conta') {
    // Il conteggio che si aggiorna mentre si scrivono le condizioni: risposta JSON.
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!csrf_ok()) { http_response_code(403); echo json_encode(['errore' => 'La sessione è scaduta: ricarica la pagina.']); exit; }
    session_write_close();
    $r = newsletter_regole_valida((string)($_POST['regole'] ?? ''), ['segmento' => (int)($_POST['id'] ?? 0)]);
    if (isset($r['errore'])) { echo json_encode(['errore' => $r['errore']]); exit; }
    $c = newsletter_regole_conta($r['regole'], ['_visti' => array_filter([(int)($_POST['id'] ?? 0)])]);
    echo json_encode(['n' => $c['n'], 'ms' => $c['ms'], 'testo' => $r['regole']['gruppi'] ? ($c['n'] === 1 ? '1 contatto rispetta' : number_format($c['n'], 0, ',', '.') . ' contatti rispettano') . ' oggi queste condizioni.' : 'Nessuna condizione ancora: valgono tutti i contatti (' . number_format($c['n'], 0, ',', '.') . ').']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($azione === 'salva') {
        $torna = ($_POST['torna'] ?? '') === 'contatti';
        $r = newsletter_segmento_salva($id, (string)($_POST['nome'] ?? ''), (string)($_POST['regole'] ?? ''), (int)($_POST['cartella'] ?? 0), (string)($_POST['cartella_nuova'] ?? ''), $utenteId);
        if (is_int($r)) {
            $s = newsletter_segmento($r);
            flash('success', 'Segmento «' . newsletter_4byte_fuori((string)$s['nome']) . '» ' . ($id ? 'salvato' : 'creato') . ': oggi ha ' . nlc_seg_n((int)$s['conteggio']) . '. Si aggiorna da solo: chi entra nelle condizioni entra nel segmento, chi ne esce esce.');
            nls_torna($torna ? 'newsletter-contatti.php?segmento=' . $r : 'newsletter-segmenti.php');
        }
        // Errore: la pagina torna con quello che si era scritto (nome, categoria, condizioni).
        flash('error', $r);
        $_SESSION['nls_bozza'] = ['id' => $id, 'nome' => mb_substr((string)($_POST['nome'] ?? ''), 0, 100), 'regole' => mb_substr((string)($_POST['regole'] ?? ''), 0, 200000), 'cartella' => (int)($_POST['cartella'] ?? 0), 'cartella_nuova' => mb_substr((string)($_POST['cartella_nuova'] ?? ''), 0, 100)];
        if ($torna && !$id) nls_torna('newsletter-contatti.php?f=' . rawurlencode(mb_substr((string)($_POST['regole'] ?? ''), 0, 20000)) . '#nlx-filtro');
        nls_torna('newsletter-segmenti.php?' . ($id ? 'id=' . $id : 'nuovo=1&modello=bozza'));
    }
    if ($azione === 'elimina') {
        $e = newsletter_segmento_elimina($id);
        $e === '' ? flash('success', 'Segmento eliminato. I contatti restano: un segmento è solo un filtro.') : flash('error', $e);
        nls_torna();
    }
    if ($azione === 'lista') {
        $r = newsletter_segmento_in_lista($id, (string)($_POST['nome_lista'] ?? ''), (int)($_POST['cartella_lista'] ?? 0));
        isset($r['errore']) ? flash('error', $r['errore'])
            : flash('success', 'Lista «' . newsletter_4byte_fuori(newsletter_nome_lista($r['id'])) . '» creata con ' . nlc_seg_n($r['n']) . ' del segmento. La lista non cambia più da sola: chi entra nel segmento dopo non entra nella lista.');
        nls_torna(isset($r['errore']) ? 'newsletter-segmenti.php' : 'newsletter-contatti.php?scheda=liste');
    }
    if ($azione === 'ricalcola') {
        $fino = microtime(true) + 15; $n = 0;
        foreach (newsletter_segmenti() as $s) { if (microtime(true) > $fino) break; newsletter_segmento_conta((int)$s['id']); $n++; }
        flash('success', "Numeri aggiornati: $n segmenti contati adesso.");
        nls_torna();
    }
    if ($azione === 'cartella_crea') {
        $r = newsletter_seg_cartella_crea((string)($_POST['nome'] ?? ''));
        is_int($r) ? flash('success', 'Categoria pronta: scegliela quando crei o modifichi un segmento.') : flash('error', $r);
        nls_torna();
    }
    if ($azione === 'cartella_rinomina') {
        $nome = newsletter_testo_pulito((string)($_POST['nome'] ?? ''), 100);
        try {
            if ($nome === '') flash('error', 'Scrivi il nome della categoria.');
            else { db()->prepare('UPDATE ' . tbl('newsletter_segmenti_cartelle') . ' SET nome = ? WHERE id = ?')->execute([$nome, $id]); flash('success', 'Categoria rinominata.'); }
        } catch (PDOException $e) { flash('error', 'Esiste già una categoria con questo nome.'); }
        nls_torna();
    }
    if ($azione === 'cartella_elimina') {
        newsletter_seg_cartella_elimina($id);
        flash('success', 'Categoria eliminata. I suoi segmenti restano, senza categoria.');
        nls_torna();
    }
    nls_torna();
}

/** "1 contatto", "1.234 contatti". */
function nlc_seg_n(int $n): string { return $n === 1 ? '1 contatto' : number_format($n, 0, ',', '.') . ' contatti'; }

$segId = (int)($_GET['id'] ?? 0);
$nuovo = isset($_GET['nuovo']);
$seg = $segId ? newsletter_segmento($segId) : null;
if ($segId && !$seg) { flash('error', 'Questo segmento non c\'è più.'); nls_torna(); }
$cartelle = newsletter_seg_cartelle();
$modelli = newsletter_segmenti_modelli();

require_once __DIR__ . '/includes/admin-header.php';
?>
<nav class="imp-schede" aria-label="Contatti">
  <a href="newsletter-contatti.php">Contatti</a>
  <a href="newsletter-contatti.php?scheda=liste">Liste</a>
  <a href="newsletter-segmenti.php" class="attiva" aria-current="page">Segmenti</a>
  <a href="newsletter-tag.php">Tag</a>
</nav>

<?php if ($nuovo && !isset($_GET['modello'])): /* Passo 1: da zero o da un modello */ ?>
<div class="form-card nls-largo">
  <h3>Crea un segmento</h3>
  <p class="hint" style="margin-top:0">Un segmento è un gruppo di contatti che si aggiorna da solo: chi rispetta le condizioni entra, chi smette di rispettarle esce. Comincia da zero oppure da un modello pronto, che puoi cambiare.</p>
  <ul class="nls-modelli">
    <li><a class="nls-modello nls-zero" href="newsletter-segmenti.php?nuovo=1&amp;modello=zero"><b>Crea da zero</b><span>Scegli tu le condizioni, una alla volta.</span></a></li>
    <?php foreach ($modelli as $k => [$nome, $descr]): ?>
    <li><a class="nls-modello" href="newsletter-segmenti.php?nuovo=1&amp;modello=<?= h($k) ?>"><b><?= h($nome) ?></b><span><?= h($descr) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <p><a href="newsletter-segmenti.php">Torna ai segmenti</a></p>
</div>

<?php elseif ($nuovo || $seg): /* Le condizioni, il conteggio, il nome, la categoria */
  $bozza = $_SESSION['nls_bozza'] ?? null; unset($_SESSION['nls_bozza']);
  if ($bozza && (int)($bozza['id'] ?? -1) !== $segId) $bozza = null; // la bozza vale solo per il segmento da cui arriva
  $modello = (string)($_GET['modello'] ?? 'zero');
  if ($bozza) { $regole = newsletter_regole_valida($bozza['regole'])['regole'] ?? ['op' => 'e', 'gruppi' => []]; $nomeV = $bozza['nome']; $cartV = (int)$bozza['cartella']; $nuovaV = $bozza['cartella_nuova']; }
  elseif ($seg) { $regole = newsletter_regole_valida($seg['regole_grezze'], ['segmento' => $segId])['regole'] ?? ['op' => 'e', 'gruppi' => []]; $nomeV = newsletter_4byte_fuori((string)$seg['nome']); $cartV = (int)$seg['cartella_id']; $nuovaV = ''; }
  else { $regole = isset($modelli[$modello]) ? (newsletter_regole_valida($modelli[$modello][2])['regole'] ?? []) : ['op' => 'e', 'gruppi' => []]; $nomeV = isset($modelli[$modello]) ? $modelli[$modello][0] : ''; $cartV = 0; $nuovaV = ''; }
  $b64 = rtrim(strtr(base64_encode(json_encode($regole ?: ['op' => 'e', 'gruppi' => []])), '+/', '-_'), '=');
?>
<form method="post" class="form-card nls-largo" id="nls-form">
  <?= csrf_field() ?><input type="hidden" name="azione" value="salva">
  <h3><?= $seg ? 'Modifica il segmento' : 'Crea un segmento' ?></h3>
  <input type="hidden" name="id" value="<?= $segId ?>">
  <?php if (!$seg && isset($modelli[$modello])): ?><p class="hint" style="margin-top:0">Dal modello «<?= h($modelli[$modello][0]) ?>»: <?= h($modelli[$modello][1]) ?> Cambia quello che vuoi.</p><?php endif; ?>
  <h4 class="nls-passo"><span class="nli-numero" aria-hidden="true">1</span> Le condizioni</h4>
  <input type="hidden" name="regole" id="nls-regole" value="<?= h($b64) ?>">
  <div class="nls-editor" data-nls-editor="#nls-regole" data-nls-conta="newsletter-segmenti.php" data-nls-id="<?= $segId ?>" data-csrf="<?= h(csrf_token()) ?>"></div>
  <h4 class="nls-passo"><span class="nli-numero" aria-hidden="true">2</span> Quanti contatti</h4>
  <p class="nls-numero" data-nls-numero role="status" aria-live="polite">Conto i contatti...</p>
  <h4 class="nls-passo"><span class="nli-numero" aria-hidden="true">3</span> Nome e categoria</h4>
  <div class="nlx-campi">
    <div class="form-group"><label for="nls-nome">Nome del segmento</label><input type="text" id="nls-nome" name="nome" maxlength="100" required value="<?= h($nomeV) ?>" placeholder="per esempio: Clienti di Roma"></div>
    <div class="form-group"><label for="nls-cart">Categoria</label>
      <select id="nls-cart" name="cartella"><option value="0">Senza categoria</option><?php foreach ($cartelle as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $cartV === (int)$c['id'] ? ' selected' : '' ?>><?= h(newsletter_4byte_fuori($c['nome'])) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="nls-cart-nuova">Oppure una categoria nuova</label><input type="text" id="nls-cart-nuova" name="cartella_nuova" maxlength="100" value="<?= h($nuovaV) ?>" placeholder="per esempio: Vendite" aria-describedby="nls-cart-aiuto">
      <div class="hint" id="nls-cart-aiuto">Se la scrivi, il segmento va lì.</div></div>
  </div>
  <div class="nl-riga">
    <button class="btn btn-primary" type="submit"><?= $seg ? 'Salva il segmento' : 'Crea il segmento' ?></button>
    <a class="btn btn-secondary" href="newsletter-segmenti.php">Annulla</a>
  </div>
</form>
<?php if ($seg): $usato = newsletter_segmento_usato($segId); ?>
<div class="form-card nls-largo" style="margin-top:1.25rem">
  <h3>Usa questo segmento</h3>
  <div class="nl-riga">
    <a class="btn btn-secondary btn-sm" href="newsletter-contatti.php?segmento=<?= $segId ?>">Vedi i contatti</a>
    <a class="btn btn-secondary btn-sm" href="newsletter-contatti.php?segmento=<?= $segId ?>&amp;esporta=1">Esporta i contatti</a>
  </div>
  <?php if ($usato): ?><p class="hint">Lo usa <?= h(implode(' e ', $usato)) ?>: per eliminarlo, togli prima la condizione da lì.</p>
  <?php else: ?>
  <form method="post" data-conferma="Eliminare il segmento «<?= h($nomeV) ?>»? I contatti restano: si perde solo il filtro." data-conferma-pericolo data-conferma-ok="Elimina il segmento" style="margin-top:.75rem">
    <?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $segId ?>">
    <button class="btn btn-danger btn-sm" type="submit">Elimina il segmento</button></form>
  <?php endif; ?>
</div>
<?php endif; ?>
<script type="application/json" id="nls-def"><?= json_encode(newsletter_regole_definizione($segId), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>

<?php else: /* Elenco */
  $tutti = newsletter_segmenti();
  // I numeri vecchi di più di dieci minuti si ricalcolano, per al massimo tre secondi (gli altri con "Aggiorna i numeri").
  $fino = microtime(true) + 3;
  foreach ($tutti as &$s) {
    if (microtime(true) < $fino && (!$s['contato_at'] || strtotime($s['contato_at']) < time() - 600)) { $s['conteggio'] = newsletter_segmento_conta((int)$s['id']); $s['contato_at'] = date('Y-m-d H:i:s'); }
  }
  unset($s);
  $cerca = mb_substr(trim((string)($_GET['cerca'] ?? '')), 0, 100);
  $catF = (int)($_GET['categoria'] ?? -1);
  $visti = array_values(array_filter($tutti, fn($s) => ($cerca === '' || mb_stripos(newsletter_4byte_fuori($s['nome']), $cerca) !== false) && ($catF < 0 || (int)$s['cartella_id'] === $catF)));
  $perCat = [];
  foreach ($visti as $s) $perCat[(int)$s['cartella_id']][] = $s;
  $nomiCat = [0 => 'Senza categoria'] + array_column(array_map(fn($c) => ['id' => (int)$c['id'], 'nome' => newsletter_4byte_fuori($c['nome'])], $cartelle), 'nome', 'id');
  $listeCart = newsletter_cartelle();
?>
<div class="form-card">
  <div class="nl-riga" style="justify-content:space-between">
    <h3 style="margin:0">Segmenti</h3>
    <div class="nl-riga">
      <a class="btn btn-primary btn-sm" href="newsletter-segmenti.php?nuovo=1">Crea un segmento</a>
      <?php if ($tutti): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="ricalcola"><button class="btn btn-secondary btn-sm" type="submit">Aggiorna i numeri</button></form><?php endif; ?>
    </div>
  </div>
  <p class="hint" style="margin-top:0">Un segmento raccoglie i contatti che rispettano delle condizioni (attributi, liste, email aperte o cliccate, form inviati, lista nera) e si aggiorna da solo. Lo carichi nella pagina Contatti, lo esporti, ne fai una lista; nelle automazioni lo usi nelle divisioni e nelle condizioni di uscita.</p>
  <?php if ($tutti): ?>
  <form method="get" class="nlx-filtri" role="search" aria-label="Cerca i segmenti">
    <div class="form-group"><label for="nls-cerca">Cerca un segmento</label><input type="search" id="nls-cerca" name="cerca" value="<?= h($cerca) ?>" placeholder="per esempio: clienti"></div>
    <div class="form-group"><label for="nls-catf">Categoria</label><select id="nls-catf" name="categoria"><option value="-1">Tutte le categorie</option><?php foreach ($nomiCat as $k => $v): ?><option value="<?= (int)$k ?>"<?= $catF === (int)$k ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></div>
    <div class="nlx-filtri-azioni"><button class="btn btn-secondary btn-sm" type="submit">Cerca</button><?php if ($cerca !== '' || $catF >= 0): ?><a href="newsletter-segmenti.php">Togli i filtri</a><?php endif; ?></div>
  </form>
  <?php endif; ?>
  <?php if (!$tutti): ?>
    <div class="empty-state"><p>Ancora nessun segmento. Creane uno con «Crea un segmento»: puoi partire da un modello, per esempio «Chi non apre da 90 giorni». Oppure filtra i contatti con «Aggiungi filtro» nella pagina Contatti e premi «Salva come segmento».</p></div>
  <?php elseif (!$visti): ?>
    <div class="empty-state"><p>Nessun segmento<?= $cerca !== '' ? ' per «' . h($cerca) . '»' : '' ?> in questa categoria. Prova una parola più corta, oppure <a href="newsletter-segmenti.php">togli i filtri</a>.</p></div>
  <?php endif; ?>
  <?php foreach ($perCat as $cid => $righe): ?>
  <h4 class="nls-categoria"><?= h($nomiCat[$cid] ?? 'Senza categoria') ?></h4>
  <div class="ad-tabella"><table class="table nls-tabella">
    <thead><tr><th scope="col">Segmento</th><th scope="col">Contatti</th><th scope="col">Ultima modifica</th><th scope="col">Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($righe as $s): $sid = (int)$s['id']; $nomeS = newsletter_4byte_fuori((string)$s['nome']);
      $reg = newsletter_regole_valida(json_decode((string)$s['condizioni'], true, 7) ?: [], ['segmento' => $sid]); ?>
      <tr>
        <td><a href="newsletter-segmenti.php?id=<?= $sid ?>"><b><?= h($nomeS) ?></b></a><span class="nlx-sotto"><?= h(isset($reg['regole']) ? newsletter_regole_testo($reg['regole']) : $reg['errore']) ?></span></td>
        <td><a href="newsletter-contatti.php?segmento=<?= $sid ?>"><?= h(nlc_seg_n((int)$s['conteggio'])) ?></a><?php if ($s['contato_at']): ?><span class="nlx-sotto">contati il <?= h(date('d/m/Y H:i', strtotime($s['contato_at']))) ?></span><?php endif; ?></td>
        <td><?= h(date('d/m/Y H:i', strtotime($s['aggiornato']))) ?></td>
        <td><div class="nl-riga">
          <a class="btn btn-secondary btn-sm" href="newsletter-segmenti.php?id=<?= $sid ?>" aria-label="Modifica il segmento <?= h($nomeS) ?>">Modifica</a>
          <a class="btn btn-secondary btn-sm" href="newsletter-contatti.php?segmento=<?= $sid ?>" aria-label="Vedi i contatti del segmento <?= h($nomeS) ?>">Vedi i contatti</a>
          <a class="btn btn-secondary btn-sm" href="newsletter-contatti.php?segmento=<?= $sid ?>&amp;esporta=1" aria-label="Esporta i contatti del segmento <?= h($nomeS) ?>">Esporta</a>
          <button class="btn btn-secondary btn-sm" type="button" data-apri-dialogo="#nls-lista-<?= $sid ?>" aria-label="Crea una lista dal segmento <?= h($nomeS) ?>">Crea una lista</button>
        </div>
        <dialog id="nls-lista-<?= $sid ?>" class="nl-dialogo" aria-labelledby="nls-lista-t<?= $sid ?>">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="lista"><input type="hidden" name="id" value="<?= $sid ?>">
            <h2 id="nls-lista-t<?= $sid ?>">Crea una lista dal segmento «<?= h($nomeS) ?>»</h2>
            <p class="hint">La lista nuova prende i contatti che oggi sono nel segmento (<?= h(nlc_seg_n((int)$s['conteggio'])) ?>). Dopo non cambia più da sola, come ogni lista.</p>
            <div class="form-group"><label for="nls-ln<?= $sid ?>">Nome della lista</label><input type="text" id="nls-ln<?= $sid ?>" name="nome_lista" maxlength="100" required value="<?= h(mb_substr($nomeS, 0, 100)) ?>"></div>
            <div class="form-group"><label for="nls-lc<?= $sid ?>">Cartella</label><select id="nls-lc<?= $sid ?>" name="cartella_lista"><?php foreach ($listeCart as $lc): ?><option value="<?= (int)$lc['id'] ?>"><?= h(newsletter_4byte_fuori($lc['nome'])) ?></option><?php endforeach; ?></select></div>
            <div class="pn-conferma-azioni"><button type="button" class="btn btn-secondary" data-chiudi-dialogo="#nls-lista-<?= $sid ?>">Annulla</button><button type="submit" class="btn btn-primary">Crea la lista</button></div>
          </form>
        </dialog></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endforeach; ?>
</div>

<details class="form-card nlx-riquadro" id="nls-categorie"<?= $cartelle ? '' : '' ?>>
  <summary><h3>Categorie dei segmenti</h3></summary>
  <form method="post" class="nl-riga" style="align-items:flex-end">
    <?= csrf_field() ?><input type="hidden" name="azione" value="cartella_crea">
    <div class="form-group" style="margin:0"><label for="nls-cn">Nome della categoria nuova</label><input type="text" id="nls-cn" name="nome" maxlength="100" required placeholder="per esempio: Vendite"></div>
    <button class="btn btn-primary btn-sm" type="submit">Crea la categoria</button>
  </form>
  <?php if ($cartelle): ?><ul class="nls-cartelle">
    <?php foreach ($cartelle as $c): $cid = (int)$c['id']; $nomeC = newsletter_4byte_fuori($c['nome']); ?>
    <li><form method="post" class="nl-riga"><?= csrf_field() ?><input type="hidden" name="azione" value="cartella_rinomina"><input type="hidden" name="id" value="<?= $cid ?>">
        <label class="sr-solo" for="nls-rc<?= $cid ?>">Nuovo nome della categoria <?= h($nomeC) ?></label>
        <input type="text" id="nls-rc<?= $cid ?>" name="nome" value="<?= h($nomeC) ?>" maxlength="100" class="nll-campo">
        <span class="hint"><?= (int)$c['segmenti'] ?> segmenti</span>
        <button class="btn btn-secondary btn-sm" type="submit">Rinomina</button></form>
      <form method="post" data-conferma="Eliminare la categoria «<?= h($nomeC) ?>»? I suoi segmenti restano, senza categoria."><?= csrf_field() ?><input type="hidden" name="azione" value="cartella_elimina"><input type="hidden" name="id" value="<?= $cid ?>">
        <button class="btn btn-danger btn-sm" type="submit">Elimina la categoria</button></form></li>
    <?php endforeach; ?>
  </ul><?php else: ?><p class="hint">Ancora nessuna categoria: scrivi il nome qui sopra e premi «Crea la categoria».</p><?php endif; ?>
</details>
<?php endif; ?>
<script src="<?= pannello_file('assets/contatti.js') ?>"></script>
<script src="<?= pannello_file('assets/segmenti.js') ?>"></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
