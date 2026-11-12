<?php
/**
 * 2.16.0, fase 9 del piano Brevo: Newsletter e form > Pagine e consenso (includes/newsletter-consenso.php).
 * Schede: Gruppi di consenso (accesi o spenti, spenti di partenza: decisione di Danilo; nome, descrizione, ingresso
 * automatico o manuale) e Pagine di disiscrizione (oltre alla predefinita di Impostazioni, quelle da scegliere nella
 * campagna, con il questionario e "Esci solo da questa lista"). Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/newsletter-consenso.php';
require_admin(); // sicurezza (ruoli): solo amministratore
nl_consenso_schema();
newsletter_campagne_schema();

$adminTitle = 'Pagine e consenso';
$ncSchede = ['gruppi' => 'Gruppi di consenso', 'pagine' => 'Pagine di disiscrizione'];
$ncScheda = is_string($_GET['scheda'] ?? null) && isset($ncSchede[$_GET['scheda']]) ? $_GET['scheda'] : 'gruppi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ncS = fn($k) => is_scalar($_POST[$k] ?? null) ? (string)$_POST[$k] : ''; // un campo arrivato come elenco vale vuoto
    $ncAz = $ncS('azione');
    $ncTorna = is_string($_POST['scheda'] ?? null) && isset($ncSchede[$_POST['scheda']]) ? $_POST['scheda'] : 'gruppi';
    $ncDove = 'newsletter-consenso.php?scheda=' . $ncTorna;
    if ($ncAz === 'gruppi_attivi') {
        save_setting('newsletter_gruppi_attivi', !empty($_POST['attivi']) ? '1' : '0');
        flash('success', !empty($_POST['attivi']) ? 'Gruppi di consenso accesi: ogni campagna nuova sceglie il suo gruppo.' : 'Gruppi di consenso spenti: le campagne vanno a tutti gli iscritti delle liste scelte, come prima.');
    } elseif ($ncAz === 'gruppo') {
        $r = nl_gruppo_salva((int)$ncS('id'), $ncS('nome'), $ncS('descrizione'), $ncS('ingresso'));
        is_int($r) ? flash('success', 'Gruppo salvato.') : flash('error', $r);
    } elseif ($ncAz === 'gruppo_elimina') {
        nl_gruppo_elimina((int)$ncS('id')) ? flash('success', 'Gruppo eliminato. Le campagne in bozza che lo usavano vanno scelte di nuovo.') : flash('error', 'Il gruppo non c\'è più.');
    } elseif ($ncAz === 'pagina') {
        $r = nl_pagina_uscita_salva((int)$ncS('id'), is_array($_POST['pg'] ?? null) ? $_POST['pg'] : []);
        if (is_int($r)) { flash('success', 'Pagina di disiscrizione salvata. La scegli nella campagna, in Impostazioni aggiuntive.'); $ncDove .= '&pagina=' . $r; }
        else flash('error', $r);
    } elseif ($ncAz === 'pagina_elimina') {
        nl_pagina_uscita_elimina((int)$ncS('id')) ? flash('success', 'Pagina eliminata: le campagne che la usavano tornano alla pagina predefinita.') : flash('error', 'La pagina non c\'è più.');
    }
    header('Location: ' . $ncDove);
    exit;
}

$ncGruppi = nl_gruppi();
$ncConti = nl_gruppi_conteggi();
$ncPagine = nl_pagine_uscita();
$ncPaginaId = (int)($_GET['pagina'] ?? 0);
$ncNuova = isset($_GET['nuova']);
$ncPagina = $ncPaginaId && isset($ncPagine[$ncPaginaId]) ? $ncPagine[$ncPaginaId] : null;
$ncModificaG = (int)($_GET['gruppo'] ?? 0);
require_once __DIR__ . '/includes/admin-header.php';
?>
<p><a href="newsletter.php?scheda=impostazioni">&larr; Impostazioni della newsletter</a></p>
<nav class="imp-schede" aria-label="Pagine e consenso">
  <?php foreach ($ncSchede as $k => $et): ?><a href="newsletter-consenso.php?scheda=<?= $k ?>"<?= $ncScheda === $k ? ' class="attiva" aria-current="page"' : '' ?>><?= h($et) ?></a><?php endforeach; ?>
</nav>
<?php if ($ncScheda === 'gruppi'): ?>
<form method="post" class="form-card" id="nc-attivi">
  <?= csrf_field() ?><input type="hidden" name="azione" value="gruppi_attivi">
  <h3>Gruppi di consenso</h3><input type="hidden" name="scheda" value="gruppi">
  <p class="hint" style="margin-top:0">Un gruppo di consenso è un tipo di email che il contatto può scegliere di ricevere o no, per esempio «Offerte» o «Novità del blog». Con i gruppi accesi ogni campagna sceglie il suo gruppo, e arriva solo a chi ci sta dentro. Nel centro preferenze il contatto sceglie i suoi gruppi, oppure esce da tutti e resta iscritto.</p>
  <label class="nl-casella" style="display:flex;gap:.5rem;align-items:center;min-height:36px"><input type="checkbox" name="attivi" value="1"<?= nl_gruppi_attivi() ? ' checked' : '' ?>> Usa i gruppi di consenso</label>
  <p class="hint"><?= nl_gruppi_attivi() ? '<b>Accesi.</b> Una campagna senza gruppo non parte: lo scegli in Destinatari.' : '<b>Spenti.</b> Le campagne vanno a tutti gli iscritti delle liste e dei segmenti scelti, come sempre.' ?></p>
  <button class="btn btn-primary" type="submit">Salva</button>
</form>
<div class="ut-griglia" style="margin-top:1.25rem">
  <div class="form-card">
    <h3>I gruppi</h3>
    <?php if (!$ncGruppi): ?><div class="empty-state"><p>Ancora nessun gruppo: crea il primo con il riquadro qui accanto.</p></div>
    <?php else: ?>
    <div class="admin-table-wrap"><table class="table">
      <thead><tr><th scope="col">Gruppo</th><th scope="col">Ingresso</th><th scope="col">Iscritti dentro</th><th scope="col">Azioni</th></tr></thead>
      <tbody><?php foreach ($ncGruppi as $gid => $g): ?>
        <tr><td><b><?= h($g['nome']) ?></b><?= $g['descrizione'] !== '' ? '<br><small>' . h($g['descrizione']) . '</small>' : '' ?></td>
          <td><?= $g['ingresso'] === 'manual' ? 'Manuale' : 'Automatico' ?></td><td><?= (int)($ncConti[$gid] ?? 0) ?></td>
          <td><a href="newsletter-consenso.php?scheda=gruppi&amp;gruppo=<?= (int)$gid ?>#nc-gruppo">Modifica</a>
            <form method="post" style="display:inline" data-conferma="Eliminare il gruppo «<?= h($g['nome']) ?>»? I contatti escono dal gruppo, le campagne in bozza che lo usavano vanno scelte di nuovo." data-conferma-ok="Elimina il gruppo" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="gruppo_elimina"><input type="hidden" name="scheda" value="gruppi"><input type="hidden" name="id" value="<?= (int)$gid ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <?php $ncG = $ncModificaG && isset($ncGruppi[$ncModificaG]) ? $ncGruppi[$ncModificaG] : ['id' => 0, 'nome' => '', 'descrizione' => '', 'ingresso' => 'automatic']; ?>
  <form method="post" class="form-card" id="nc-gruppo">
    <?= csrf_field() ?><input type="hidden" name="azione" value="gruppo">
    <h3><?= $ncG['id'] ? 'Modifica il gruppo' : 'Crea un gruppo' ?></h3><input type="hidden" name="scheda" value="gruppi"><input type="hidden" name="id" value="<?= (int)$ncG['id'] ?>">
    <div class="form-group"><label for="nc-g-nome">Nome</label><input type="text" id="nc-g-nome" name="nome" maxlength="100" required value="<?= h($ncG['nome']) ?>" placeholder="per esempio: Offerte" aria-describedby="nc-g-nome-aiuto"><div class="hint" id="nc-g-nome-aiuto">Lo legge il contatto nel centro preferenze.</div></div>
    <div class="form-group"><label for="nc-g-descr">Descrizione (facoltativa)</label><input type="text" id="nc-g-descr" name="descrizione" maxlength="500" value="<?= h($ncG['descrizione']) ?>" placeholder="per esempio: Sconti e promozioni, una volta al mese"></div>
    <fieldset class="nlk-scelte"><legend>Chi è dentro all'inizio</legend>
      <?php foreach (NL_GRUPPI_INGRESSI as $k => $et): ?><label><input type="radio" name="ingresso" value="<?= $k ?>"<?= $ncG['ingresso'] === $k ? ' checked' : '' ?>> <?= h($et) ?></label><?php endforeach; ?>
    </fieldset>
    <button class="btn btn-primary" type="submit"><?= $ncG['id'] ? 'Salva il gruppo' : 'Crea il gruppo' ?></button>
    <?php if ($ncG['id']): ?> <a class="btn btn-secondary" href="newsletter-consenso.php?scheda=gruppi">Annulla</a><?php endif; ?>
  </form>
</div>
<div class="form-card" style="margin-top:1.25rem">
  <h3>Consenso al tracciamento</h3>
  <p class="hint" style="margin-top:0">Ogni contatto può dire di no al tracciamento di aperture e clic: dal centro preferenze, da un form con il blocco «Consenso al tracciamento», o dalla sua scheda (attributo «Consenso al tracciamento»). Chi dice di no riceve le email senza il pixel e con i link diretti; nelle statistiche e nei segmenti su aperture e clic non conta.</p>
</div>
<?php else: ?>
<?php if (!$ncPagina && !$ncNuova): ?>
<div class="form-card">
  <h3>Pagine di disiscrizione</h3>
  <p class="hint" style="margin-top:0">La pagina che si apre dal link «Esci dalla newsletter». La <b>predefinita</b> la cambi in <a href="newsletter.php?scheda=impostazioni#nl-pagine">Impostazioni</a>; qui ne crei altre, con testi, questionario e «Esci solo da questa lista» diversi, e le scegli campagna per campagna (Impostazioni aggiuntive).</p>
  <div class="admin-table-wrap"><table class="table">
    <thead><tr><th scope="col">Pagina</th><th scope="col">Campagne che la usano</th><th scope="col">Questionario</th><th scope="col">Azioni</th></tr></thead>
    <tbody>
      <tr><td><b>Pagina predefinita</b></td><td>tutte le altre</td><td><?= newsletter_pagine_testi()['questionario'] ? 'Sì' : 'No' ?></td><td><a href="newsletter.php?scheda=impostazioni#nl-pagine">Modifica</a></td></tr>
      <?php foreach ($ncPagine as $pid => $pg): $pt = nl_pagina_uscita_testi($pid); $pn = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_campagne') . ' WHERE pagina_uscita_id = ' . (int)$pid)->fetchColumn(); ?>
      <tr><td><b><?= h($pg['nome']) ?></b></td><td><?= $pn ?></td><td><?= $pt['questionario'] ? 'Sì' : 'No' ?></td>
        <td><a href="newsletter-consenso.php?scheda=pagine&amp;pagina=<?= (int)$pid ?>">Modifica</a>
          <form method="post" style="display:inline" data-conferma="Eliminare la pagina «<?= h($pg['nome']) ?>»? Le campagne che la usano tornano alla pagina predefinita." data-conferma-ok="Elimina la pagina" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="pagina_elimina"><input type="hidden" name="scheda" value="pagine"><input type="hidden" name="id" value="<?= (int)$pid ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (!$ncPagine): ?><p class="hint">Ancora nessuna pagina in più: con «Crea una pagina» ne fai una, per esempio per le campagne promozionali.</p><?php endif; ?>
  <p><a class="btn btn-primary" href="newsletter-consenso.php?scheda=pagine&amp;nuova=1">Crea una pagina</a></p>
</div>
<?php else:
  $ncT = $ncPagina ? (json_decode((string)$ncPagina['testi'], true) ?: []) : ['questionario' => true, 'solo_lista' => true];
  $ncB = newsletter_pagine_testi();
  $ncV = fn($k) => h(newsletter_4byte_fuori((string)($ncT[$k] ?? ''))); ?>
<form method="post" class="form-card" id="nc-pagina">
  <?= csrf_field() ?><input type="hidden" name="azione" value="pagina">
  <h3><?= $ncPagina ? 'Modifica la pagina' : 'Crea una pagina di disiscrizione' ?></h3><input type="hidden" name="scheda" value="pagine"><input type="hidden" name="id" value="<?= (int)($ncPagina['id'] ?? 0) ?>">
  <p class="hint" style="margin-top:0">Lascia vuoto un campo per usare il testo della pagina predefinita (lo vedi scritto in grigio). <code>{email}</code> diventa l'indirizzo di chi legge, <code>{newsletter}</code> il nome della newsletter.</p>
  <div class="form-group"><label for="nc-p-nome">Nome della pagina (nel pannello)</label><input type="text" id="nc-p-nome" name="pg[nome]" maxlength="100" required value="<?= h((string)($ncPagina['nome'] ?? '')) ?>" placeholder="per esempio: Promozioni"></div>
  <div class="form-group"><label for="nc-p-ut">Titolo della pagina</label><input type="text" id="nc-p-ut" name="pg[uscita_titolo]" maxlength="200" value="<?= $ncV('uscita_titolo') ?>" placeholder="<?= h($ncB['uscita_titolo']) ?>"></div>
  <div class="form-group"><label for="nc-p-ux">Testo sopra il pulsante «Sì, toglimi dall'elenco»</label><textarea id="nc-p-ux" name="pg[uscita_testo]" rows="2" maxlength="1000" placeholder="<?= h($ncB['uscita_testo']) ?>"><?= $ncV('uscita_testo') ?></textarea></div>
  <label class="nl-casella"><input type="checkbox" name="pg[solo_lista]" value="1"<?= !empty($ncT['solo_lista']) ? ' checked' : '' ?>> Offri «Esci solo da questa lista»</label>
  <div class="form-group"><label for="nc-p-ft">Titolo dopo l'uscita</label><input type="text" id="nc-p-ft" name="pg[fatto_titolo]" maxlength="200" value="<?= $ncV('fatto_titolo') ?>" placeholder="<?= h($ncB['fatto_titolo']) ?>"></div>
  <div class="form-group"><label for="nc-p-fx">Testo dopo l'uscita</label><textarea id="nc-p-fx" name="pg[fatto_testo]" rows="2" maxlength="1000" placeholder="<?= h($ncB['fatto_testo']) ?>"><?= $ncV('fatto_testo') ?></textarea></div>
  <label class="nl-casella"><input type="checkbox" name="pg[questionario]" value="1"<?= !empty($ncT['questionario']) ? ' checked' : '' ?>> Fai il questionario: chiedi perché esce</label>
  <div class="form-group"><label for="nc-p-qt">Domanda del questionario</label><input type="text" id="nc-p-qt" name="pg[questionario_titolo]" maxlength="200" value="<?= $ncV('questionario_titolo') ?>" placeholder="<?= h($ncB['questionario_titolo']) ?>"></div>
  <fieldset class="nlk-scelte"><legend>Le risposte possibili</legend>
    <?php foreach (NL_USCITA_MOTIVI as $k => $et): ?><div class="form-group" style="margin:.3rem 0"><label for="nc-p-m-<?= $k ?>" class="sr-solo">Risposta: <?= h($et) ?></label><input type="text" id="nc-p-m-<?= $k ?>" name="pg[motivi][<?= $k ?>]" maxlength="150" value="<?= h(newsletter_4byte_fuori((string)($ncT['motivi'][$k] ?? ''))) ?>" placeholder="<?= h($ncB['motivi'][$k]) ?>"></div><?php endforeach; ?>
  </fieldset>
  <div class="form-group"><label for="nc-p-gx">Ringraziamento dopo la risposta</label><input type="text" id="nc-p-gx" name="pg[grazie_testo]" maxlength="500" value="<?= $ncV('grazie_testo') ?>" placeholder="<?= h($ncB['grazie_testo']) ?>"></div>
  <button class="btn btn-primary" type="submit"><?= $ncPagina ? 'Salva la pagina' : 'Crea la pagina' ?></button>
  <a class="btn btn-secondary" href="newsletter-consenso.php?scheda=pagine">Torna alle pagine</a>
</form>
<?php endif; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
