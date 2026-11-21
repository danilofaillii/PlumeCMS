<?php
/**
 * 2.23.0: Newsletter e form > Tag. I tag dei contatti: l'elenco con quanti contatti hanno ogni tag (il numero porta ai
 * Contatti filtrati "con il tag"), crea, rinomina, elimina (con la conferma del pannello). I tag si danno dalla scheda del
 * contatto, dall'azione di gruppo dei Contatti, dai form, dall'importazione, dalle automazioni e dall'addon dei test.
 * Motore in includes/newsletter-tag.php. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-tag.php';
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();
newsletter_tag_schema();
$adminTitle = 'Tag dei contatti';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = is_string($_POST['azione'] ?? null) ? $_POST['azione'] : '';
    $id = (int)($_POST['id'] ?? 0);
    if ($azione === 'crea') {
        $r = newsletter_tag_crea(is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '');
        if (is_int($r)) flash('success', 'Tag «' . newsletter_tag_vedi(newsletter_tag_nome_di($r)) . '» pronto: dallo ai contatti dalla loro scheda, oppure dall\'elenco dei Contatti con l\'azione «Aggiungi un tag».');
        else { flash('error', 'Il tag non è stato creato: lo trovi spiegato sotto il campo.'); $_SESSION['nlt_errore'] = $r; }
    } elseif ($azione === 'rinomina') {
        $e = newsletter_tag_rinomina($id, is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '');
        $e === '' ? flash('success', 'Tag rinominato. I contatti che lo avevano lo hanno ancora, con il nome nuovo; segmenti e automazioni lo seguono.') : flash('error', $e);
    } elseif ($azione === 'elimina') {
        $nome = newsletter_tag_vedi(newsletter_tag_nome_di($id));
        $n = newsletter_tag_elimina($id);
        flash('success', $nome !== '' ? 'Tag «' . $nome . '» eliminato: ' . ($n === 1 ? '1 contatto lo aveva' : "$n contatti lo avevano") . ' e lo hanno perso. I contatti restano.' : 'Questo tag non c\'era più.');
    }
    header('Location: newsletter-tag.php');
    exit;
}

$tutti = newsletter_tag_tutti();
$errore = is_string($_SESSION['nlt_errore'] ?? null) ? $_SESSION['nlt_errore'] : '';
unset($_SESSION['nlt_errore']);
$usati = [];
// Dove si usa un tag: segmenti, automazioni (inneschi e azioni), form. Solo per dirlo, non blocca l'eliminazione.
try {
    foreach (db()->query('SELECT nome, condizioni FROM ' . tbl('newsletter_segmenti'))->fetchAll() as $sg) {
        foreach ((array)(json_decode((string)$sg['condizioni'], true)['gruppi'] ?? []) as $g) foreach ((array)($g['condizioni'] ?? []) as $c) {
            if (($c['campo'] ?? '') === 'tag') foreach ((array)($c['valore'] ?? []) as $t) $usati[(int)$t]['Segmento «' . newsletter_4byte_fuori((string)$sg['nome']) . '»'] = true;
        }
    }
} catch (Throwable $e) {}
foreach (newsletter_flussi() as $fl) {
    foreach ($fl['inneschi'] as $x) if ($x['tipo'] === 'tag') foreach ($x['tag'] ?? [] as $t) $usati[(int)$t]['Automazione «' . $fl['nome'] . '»'] = true;
    foreach ($fl['grafo']['nodi'] as $n) if (in_array($n['tipo'], ['tag_aggiungi', 'tag_togli'], true)) foreach ($n['dati']['tag'] as $t) $usati[(int)$t]['Automazione «' . $fl['nome'] . '»'] = true;
}
require_once __DIR__ . '/includes/admin-header.php';
?>
<nav class="imp-schede" aria-label="Contatti">
  <a href="newsletter-contatti.php">Contatti</a>
  <a href="newsletter-contatti.php?scheda=liste">Liste</a>
  <a href="newsletter-segmenti.php">Segmenti</a>
  <a href="newsletter-tag.php" class="attiva" aria-current="page">Tag</a>
</nav>
<div class="ut-griglia">
  <div>
    <form method="post" class="form-card" id="nlt-crea">
      <?= csrf_field() ?><input type="hidden" name="azione" value="crea">
      <h3>Crea un tag</h3>
      <div class="form-group"><label for="nlt-nome">Nome del tag</label>
        <input type="text" id="nlt-nome" name="nome" maxlength="<?= NL_TAG_NOME_MAX ?>" required placeholder="per esempio: Cliente" aria-describedby="nlt-nome-aiuto<?= $errore !== '' ? ' nlt-nome-err' : '' ?>"<?= $errore !== '' ? ' aria-invalid="true"' : '' ?>>
        <div class="hint" id="nlt-nome-aiuto">Fino a <?= NL_TAG_NOME_MAX ?> caratteri, senza virgole. Maiuscole e minuscole non contano: «cliente» e «Cliente» sono lo stesso tag.</div>
        <?php if ($errore !== ''): ?><p class="errore-campo" id="nlt-nome-err" role="alert"><?= h($errore) ?></p><?php endif; ?></div>
      <button class="btn btn-primary btn-sm" type="submit">Crea il tag</button>
    </form>
    <details class="form-card pn-aiuto" style="margin-top:1rem">
      <summary>Come si usano i tag</summary>
      <p>Un tag è un'etichetta della persona (per esempio «Cliente», «Ha comprato il corso», «Età: 35-44»). Un contatto può averne tanti.</p>
      <ul>
        <li>Si danno dalla scheda del contatto, dall'elenco dei Contatti (azione di gruppo «Aggiungi un tag»), da un form (passo 1, «A chi compila dai questi tag»), da un'importazione (una colonna abbinata a «Tag»), da un'automazione (azione «Aggiungi tag») e dai test.</li>
        <li>Si usano per filtrare i Contatti, nei segmenti («Tag: ha almeno uno di questi tag») e come innesco di un'automazione («Riceve un tag»).</li>
        <li>Un tag nuovo nasce da solo la prima volta che lo dai a qualcuno.</li>
      </ul>
    </details>
  </div>
  <div class="form-card">
    <h3>Tag</h3>
    <?php if (!$tutti): ?>
      <div class="empty-state"><p>Ancora nessun tag: creane uno con il riquadro qui accanto, oppure dallo a un contatto dalla sua scheda (il tag nasce da solo).</p></div>
    <?php else: ?>
    <p class="hint" style="margin-top:0"><?= count($tutti) === 1 ? '1 tag' : count($tutti) . ' tag' ?>. Il numero dei contatti apre l'elenco dei Contatti con quel tag.</p>
    <div class="ad-tabella"><table class="table nlt-tabella">
      <thead><tr><th scope="col">Tag</th><th scope="col">Contatti</th><th scope="col">Si usa in</th><th scope="col">Azioni</th></tr></thead>
      <tbody>
      <?php foreach ($tutti as $t): $nome = newsletter_tag_vedi($t['nome']); $tid = $t['id']; ?>
        <tr>
          <td><b><?= h($nome) ?></b><span class="nlx-sotto">creato il <?= h(date('d/m/Y', strtotime($t['creato']))) ?></span></td>
          <td><a href="newsletter-contatti.php?tag=<?= $tid ?>" aria-label="<?= h(($t['contatti'] === 1 ? '1 contatto' : $t['contatti'] . ' contatti') . ' con il tag ' . $nome) ?>"><?= (int)$t['contatti'] ?></a></td>
          <td><?= isset($usati[$tid]) ? h(implode(', ', array_keys($usati[$tid]))) : '<span class="nlx-sotto">da nessuna parte</span>' ?></td>
          <td><details class="nll-modifica"><summary class="btn btn-secondary btn-sm">Rinomina o elimina</summary>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="rinomina"><input type="hidden" name="id" value="<?= $tid ?>">
              <div class="form-group"><label for="nlt-r<?= $tid ?>">Nuovo nome di «<?= h($nome) ?>»</label><input type="text" id="nlt-r<?= $tid ?>" name="nome" value="<?= h($nome) ?>" maxlength="<?= NL_TAG_NOME_MAX ?>" required></div>
              <button class="btn btn-primary btn-sm" type="submit">Rinomina il tag</button></form>
            <form method="post" data-conferma="Eliminare il tag «<?= h($nome) ?>»? Lo perdono <?= $t['contatti'] === 1 ? '1 contatto' : (int)$t['contatti'] . ' contatti' ?><?= isset($usati[$tid]) ? ' e smette di valere in: ' . h(implode(', ', array_keys($usati[$tid]))) : '' ?>. I contatti restano. Non si torna indietro." data-conferma-ok="Elimina il tag" data-conferma-pericolo style="margin-top:.5rem">
              <?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $tid ?>">
              <button class="btn btn-danger btn-sm" type="submit">Elimina il tag</button></form>
          </details></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
