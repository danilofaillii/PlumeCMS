<?php
/**
 * 2.8.0: Newsletter e form > Impostazioni > Attributi dei contatti, come Brevo (Settings > Contacts > Contact attributes).
 * Schede: Attributi normali (di sistema, normali e di categoria), Attributi calcolati (per contatto), Valori globali.
 * Il pulsante in fondo apre la finestra "Crea un attributo": tipo, nome, etichetta, opzioni o formula. Nome e tipo non si
 * cambiano dopo; cancellare un attributo toglie i suoi valori a tutti i contatti. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();

$adminTitle = 'Attributi dei contatti';
$schede = ['normali' => 'Attributi normali', 'calcolati' => 'Attributi calcolati', 'globali' => 'Valori globali'];
$scheda = isset($schede[$_GET['scheda'] ?? '']) ? $_GET['scheda'] : 'normali';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $torna = (string)($_POST['scheda'] ?? 'normali');
    if (!isset($schede[$torna])) $torna = 'normali';
    if ($azione === 'crea') {
        $tipo = (string)($_POST['tipo'] ?? '');
        if ($torna === 'calcolati') $tipo = 'calculated';
        if ($torna === 'globali') $tipo = 'global';
        $e = newsletter_attributo_crea(['nome' => (string)($_POST['nome'] ?? ''), 'tipo' => $tipo, 'etichetta' => (string)($_POST['etichetta'] ?? ''),
            'opzioni' => (string)($_POST['opzioni'] ?? ''), 'formula' => (string)($_POST['formula'] ?? '')]);
        $e === '' ? flash('success', 'Attributo ' . newsletter_attributo_nome((string)($_POST['nome'] ?? '')) . ' creato.') : flash('error', $e);
    } elseif ($azione === 'modifica') {
        $nuove = preg_split('/\r\n|\r|\n/', (string)($_POST['nuove'] ?? ''));
        $e = newsletter_attributo_modifica((int)($_POST['id'] ?? 0), (string)($_POST['etichetta'] ?? ''), $nuove, (array)($_POST['togli'] ?? []));
        $e === '' ? flash('success', 'Attributo aggiornato.') : flash('error', $e);
    } elseif ($azione === 'elimina') {
        newsletter_attributo_elimina((int)($_POST['id'] ?? 0)) ? flash('success', 'Attributo eliminato, con i suoi valori in tutti i contatti.') : flash('error', 'Gli attributi di sistema non si eliminano.');
    }
    header('Location: newsletter-attributi.php' . ($torna !== 'normali' ? '?scheda=' . $torna : ''));
    exit;
}

$attributi = newsletter_attributi();
$categoria = ['normali' => ['normal', 'category'], 'calcolati' => ['calculated'], 'globali' => ['global']][$scheda];
$righe = array_filter($attributi, fn($a) => in_array($a['categoria'], $categoria, true));
$personali = count(array_filter($attributi, fn($a) => !(int)$a['sistema']));
require_once __DIR__ . '/includes/admin-header.php';
?>
<p><a href="newsletter.php?scheda=impostazioni">&larr; Impostazioni della newsletter</a></p>
<nav class="imp-schede" aria-label="Tipi di attributo">
  <?php foreach ($schede as $k => $v): ?><a href="newsletter-attributi.php<?= $k !== 'normali' ? '?scheda=' . $k : '' ?>"<?= $scheda === $k ? ' class="attiva" aria-current="page"' : '' ?>><?= $v ?></a><?php endforeach; ?>
</nav>

<div class="form-card">
  <h3><?= h($schede[$scheda]) ?></h3>
  <p class="hint" style="margin-top:0"><?= [
    'normali' => 'Un attributo è un campo del contatto (città, data di nascita, cliente sì o no...). Serve per personalizzare le email con i segnaposti (per esempio {{ contact.NOME }}), e più avanti per i segmenti e i campi dei form. Quelli di sistema li crea plumeCMS e non si cancellano.',
    'calcolati' => 'Un attributo calcolato ha un valore diverso per ogni contatto, ricavato da una formula: per esempio quante email ha aperto negli ultimi 15 giorni. Si ricalcola ogni volta che serve.',
    'globali' => 'Un valore globale è un numero calcolato su tutti i contatti insieme, con una formula: per esempio quanti iscritti hai, o la somma di un attributo numerico. Si usa nelle email come gli altri attributi.',
  ][$scheda] ?></p>
  <div class="ad-tabella"><table class="table">
    <thead><tr><th scope="col">Nome</th><th scope="col">Etichetta</th><th scope="col">Tipo</th><th scope="col"><?= $scheda === 'normali' ? 'Opzioni' : 'Formula' ?></th><?php if ($scheda === 'globali'): ?><th scope="col">Valore adesso</th><?php endif; ?><th scope="col">Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($righe as $a): $aid = (int)$a['id']; $et = newsletter_attributo_etichetta($a); ?>
      <tr>
        <td><code><?= h($a['nome']) ?></code><?= isset(array_flip(NL_ATTR_ALIAS)[$a['nome']]) ? '<span class="nlx-sotto">anche: ' . h(array_flip(NL_ATTR_ALIAS)[$a['nome']]) . '</span>' : '' ?></td>
        <td><?= h($a['etichetta'] !== '' ? $et : '') ?></td>
        <td><?= h($a['categoria'] === 'category' ? 'Categoria' : ($a['categoria'] === 'calculated' ? 'Calcolato' : ($a['categoria'] === 'global' ? 'Globale' : NL_ATTR_TIPI[$a['tipo']] ?? $a['tipo']))) ?><?= (int)$a['sistema'] ? '<span class="nlx-sotto">di sistema</span>' : '' ?></td>
        <td><?= $scheda === 'normali' ? h(implode(', ', array_map('newsletter_4byte_fuori', $a['opzioni']))) : '<code>' . h($a['formula']) . '</code>' ?></td>
        <?php if ($scheda === 'globali'): ?><td><?= h(newsletter_numero_testo(newsletter_formula_valore((string)$a['formula'], 0))) ?></td><?php endif; ?>
        <td><?php if ((int)$a['sistema']): ?><span class="hint">non si cambia</span><?php else: ?>
          <details class="nll-modifica"><summary class="btn btn-secondary btn-sm">Modifica</summary>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="modifica"><input type="hidden" name="id" value="<?= $aid ?>"><input type="hidden" name="scheda" value="<?= $scheda ?>">
              <div class="form-group"><label for="nla-e<?= $aid ?>">Etichetta</label><input type="text" id="nla-e<?= $aid ?>" name="etichetta" value="<?= h($a['etichetta'] !== '' ? $et : '') ?>" maxlength="100"></div>
              <?php if (in_array($a['tipo'], ['category', 'multiple-choice'], true)): ?>
                <?php if ($a['opzioni']): ?><fieldset class="nlk-scelte"><legend>Togli delle opzioni</legend><?php foreach ($a['opzioni'] as $k => $o): ?><label><input type="checkbox" name="togli[]" value="<?= (int)$k ?>"> <?= h(newsletter_4byte_fuori($o)) ?></label><?php endforeach; ?></fieldset><?php endif; ?>
                <div class="form-group"><label for="nla-o<?= $aid ?>">Aggiungi opzioni</label><textarea id="nla-o<?= $aid ?>" name="nuove" rows="3" class="nlx-corto" placeholder="una per riga"></textarea>
                  <div class="hint">Una per riga, al massimo 200 caratteri. Chi aveva un'opzione tolta la perde.</div></div>
              <?php endif; ?>
              <p class="hint">Nome e tipo non si cambiano: per cambiarli crea un attributo nuovo.</p>
              <button class="btn btn-primary btn-sm" type="submit">Salva l'attributo</button>
            </form>
            <form method="post" data-conferma="Eliminare l'attributo <?= h($a['nome']) ?>? I suoi valori si perdono per tutti i contatti. Non si torna indietro." data-conferma-pericolo>
              <?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $aid ?>"><input type="hidden" name="scheda" value="<?= $scheda ?>">
              <button class="btn btn-danger btn-sm" type="submit" style="margin-top:.5rem">Elimina l'attributo</button>
            </form>
          </details><?php endif; ?></td>
      </tr>
    <?php endforeach; if (!$righe): ?>
      <tr><td colspan="<?= $scheda === 'globali' ? 6 : 5 ?>"><div class="empty-state"><p>Ancora nessun <?= $scheda === 'calcolati' ? 'attributo calcolato' : 'valore globale' ?>: crealo con il pulsante qui sotto.</p></div></td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
  <p style="margin-top:1rem"><button class="btn btn-primary" type="button" data-apri-dialogo="#nla-crea"><?= $scheda === 'normali' ? 'Crea un attributo' : ($scheda === 'calcolati' ? 'Crea un attributo calcolato' : 'Crea un valore globale') ?></button></p>
  <p class="hint">Attributi creati: <?= $personali ?> su <?= NL_ATTR_MASSIMO ?>. Al massimo <?= NL_CALCOLATI_MASSIMO ?> calcolati e <?= NL_GLOBALI_MASSIMO ?> valori globali.</p>
</div>

<dialog id="nla-crea" class="nl-dialogo" aria-labelledby="nla-crea-titolo">
  <form method="post" data-nla-crea>
    <?= csrf_field() ?><input type="hidden" name="azione" value="crea"><input type="hidden" name="scheda" value="<?= $scheda ?>">
    <h2 id="nla-crea-titolo"><?= $scheda === 'normali' ? 'Crea un attributo' : ($scheda === 'calcolati' ? 'Crea un attributo calcolato' : 'Crea un valore globale') ?></h2>
    <?php if ($scheda === 'normali'): ?>
    <div class="form-group"><label for="nla-tipo">1. Tipo</label>
      <select id="nla-tipo" name="tipo" required data-nla-tipo><option value="">Scegli il tipo...</option><?php foreach (NL_ATTR_TIPI as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
      <div class="hint">Testo, Numero, Data, Sì/No (serve anche per le caselle da spuntare), Categoria (una scelta fra opzioni), Scelta multipla (più scelte).</div></div>
    <?php endif; ?>
    <div class="form-group"><label for="nla-nome"><?= $scheda === 'normali' ? '2.' : '1.' ?> Nome</label>
      <input type="text" id="nla-nome" name="nome" maxlength="50" required placeholder="per esempio: <?= $scheda === 'normali' ? 'CITTA' : ($scheda === 'calcolati' ? 'APERTURE_15_GIORNI' : 'ISCRITTI_TOTALI') ?>" aria-describedby="nla-nome-aiuto">
      <div class="hint" id="nla-nome-aiuto">Si scrive in maiuscolo, con lettere, numeri e trattini bassi (gli spazi diventano trattini bassi). Nei segnaposti: {{ contact.NOME_ATTRIBUTO }}. Non si cambia dopo.</div></div>
    <div class="form-group"><label for="nla-etichetta"><?= $scheda === 'normali' ? '3.' : '2.' ?> Etichetta</label>
      <input type="text" id="nla-etichetta" name="etichetta" maxlength="100" placeholder="per esempio: Città" aria-describedby="nla-et-aiuto"><div class="hint" id="nla-et-aiuto">Facoltativa: il nome in parole che vedi nelle tabelle e nella scheda del contatto.</div></div>
    <?php if ($scheda === 'normali'): ?>
    <div class="form-group" data-nla-opzioni hidden><label for="nla-opzioni">4. Opzioni</label>
      <textarea id="nla-opzioni" name="opzioni" rows="4" class="nlx-corto" placeholder="per esempio:&#10;Cliente&#10;Fornitore" aria-describedby="nla-op-aiuto"></textarea>
      <div class="hint" id="nla-op-aiuto">Una per riga, al massimo 200 caratteri ciascuna. Nella Categoria ogni opzione prende anche un numero (1, 2, 3...).</div></div>
    <?php else: ?>
    <div class="form-group"><label for="nla-formula">3. Formula</label>
      <input type="text" id="nla-formula" name="formula" maxlength="255" required placeholder="per esempio: <?= $scheda === 'calcolati' ? 'COUNT[READS,READS,&gt;,NOW(-15)]' : 'COUNT[CONTACTS]' ?>" aria-describedby="nla-f-aiuto">
      <details class="pn-aiuto" id="nla-f-aiuto"><summary>Come si scrive la formula</summary>
        <p>Con COUNT (conta)<?= $scheda === 'globali' ? ' e SUM (somma)' : '' ?>:</p>
        <ul>
          <li><code>COUNT[READS]</code>: le email aperte<?= $scheda === 'globali' ? ' da tutti i contatti' : '' ?>; <code>CLICKS</code> i clic, <code>SENT</code> le email ricevute, <code>FORMS</code> i form compilati<?= $scheda === 'globali' ? ', <code>CONTACTS</code> i contatti iscritti' : '' ?>.</li>
          <li><code>COUNT[READS,READS,&gt;,NOW(-15)]</code>: solo negli ultimi 15 giorni (con <code>&lt;</code>: prima di 15 giorni fa).</li>
          <?php if ($scheda === 'globali'): ?><li><code>SUM[PUNTI]</code>: la somma dell'attributo numerico PUNTI di tutti i contatti.</li><?php endif; ?>
        </ul></details></div>
    <?php endif; ?>
    <div class="pn-conferma-azioni"><button type="button" class="btn btn-secondary" data-chiudi-dialogo="#nla-crea">Annulla</button><button type="submit" class="btn btn-primary">Crea l'attributo</button></div>
  </form>
</dialog>
<script src="<?= pannello_file('assets/contatti.js') ?>"></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
