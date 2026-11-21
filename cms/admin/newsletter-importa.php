<?php
/**
 * 2.9.0: Contatti > Importa contatti, come Brevo (Contacts > Import contacts), in quattro passi:
 *   1 Carica: un file CSV, TXT o XLSX (UTF-8 o Windows-1252), oppure Copia e incolla;
 *   2 Abbina i dati: per ogni colonna un attributo (abbinato da solo se il nome coincide), "Crea un nuovo attributo" o
 *     "Non importare"; EMAIL obbligatoria;
 *   3 Scegli la lista: una o più liste, o una nuova in una cartella;
 *   4 Concludi: le opzioni di Brevo, il consenso da spuntare, l'avviso, poi "Conferma l'importazione".
 * Il lavoro va a giri (da questa pagina e dal cron); alla fine il resoconto con il CSV delle righe scartate.
 * Motore in includes/newsletter-importa.php. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-importa.php';
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_importa_schema();

$adminTitle = 'Importa contatti';
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$imp = $id ? newsletter_importazione($id) : null;
$utenteId = (int)(current_user()['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'giro') {
    // Un giro di lavoro chiesto dalla pagina: risposta JSON.
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!csrf_ok()) { http_response_code(403); echo json_encode(['errore' => 'La sessione è scaduta: ricarica la pagina.']); exit; }
    session_write_close();
    @ignore_user_abort(true);
    @set_time_limit(90);
    try { newsletter_lavori_giro(8); } catch (Throwable $e) { error_log('importa giro: ' . $e->getMessage()); }
    $imp = $id ? newsletter_importazione($id) : null;
    echo json_encode($imp ? ['finito' => !in_array($imp['stato'], ['pronta', 'in_corso'], true), 'fatte' => (int)$imp['fatte'], 'totale' => (int)$imp['righe'],
        'testo' => (int)$imp['fatte'] . ' righe su ' . (int)$imp['righe']] : ['finito' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id && !$imp) { flash('error', 'Questa importazione non c\'è più: le bozze si cancellano dopo un giorno. Ricomincia dal passo 1.'); header('Location: newsletter-importa.php'); exit; }
if ($imp && ($_GET['scarica'] ?? '') === 'scarti' && $imp['scarti_file'] !== '') newsletter_file_scarica((string)$imp['scarti_file'], 'righe-scartate-importazione-' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $torna = 'newsletter-importa.php' . ($id ? '?id=' . $id : '');
    if ($azione === 'carica') {
        $modo = ($_POST['modo'] ?? '') === 'incolla' ? 'incolla' : 'file';
        $r = null;
        if ($modo === 'incolla') {
            $testo = (string)($_POST['testo'] ?? '');
            if (trim($testo) === '') $r = ['errore' => 'Incolla i contatti nella casella: la prima riga con i nomi delle colonne (per esempio EMAIL, NOME).'];
            elseif (strlen($testo) > 5242880) $r = ['errore' => 'Il testo incollato è troppo lungo (oltre 5 MB): carica un file invece.'];
            else {
                $tmp = tempnam(sys_get_temp_dir(), 'nlinc');
                file_put_contents($tmp, $testo);
                $r = newsletter_importa_prepara($tmp, 'testo-incollato.txt', 'incolla', $utenteId);
                @unlink($tmp);
            }
        } else {
            $f = $_FILES['file'] ?? null;
            $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) $r = ['errore' => 'Il file supera il limite di caricamento del server (' . pannello_peso(pannello_limite_caricamento()) . '): dividilo in più file, oppure alza il limite dal pannello del tuo hosting.'];
            elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) $r = ['errore' => 'Scegli il file con i contatti (CSV, TXT o XLSX), poi premi Avanti.'];
            else $r = newsletter_importa_prepara((string)$f['tmp_name'], (string)$f['name'], 'file', $utenteId);
        }
        if (isset($r['errore'])) {
            $_SESSION['nli_errore'] = ['campo' => $modo, 'testo' => $r['errore']];
            flash('error', 'Il passo 1 non è andato: lo trovi spiegato sotto il campo.');
            header('Location: newsletter-importa.php'); exit;
        }
        header('Location: newsletter-importa.php?id=' . (int)$r['id']); exit;
    }
    if (!$imp) { header('Location: newsletter-importa.php'); exit; }
    if ($azione === 'abbina') {
        $e = newsletter_importa_salva_abbinamento($id, (array)($_POST['col'] ?? []), (array)($_POST['nuovo'] ?? []));
        if ($e) {
            $_SESSION['nli_errori'] = $e;
            $_SESSION['nli_scelte'] = ['col' => (array)($_POST['col'] ?? []), 'nuovo' => (array)($_POST['nuovo'] ?? [])];
            $n = count(array_diff_key($e, ['tutti' => 1]));
            flash('error', $n ? ($n === 1 ? '1 colonna da sistemare: la trovi segnata in rosso qui sotto.' : "$n colonne da sistemare: le trovi segnate in rosso qui sotto.") : $e['tutti']);
            header('Location: newsletter-importa.php?id=' . $id . '&passo=2'); exit;
        }
        header('Location: newsletter-importa.php?id=' . $id . '&passo=3'); exit;
    }
    if ($azione === 'liste') {
        $e = newsletter_importa_salva_liste($id, (array)($_POST['liste'] ?? []), (string)($_POST['lista_nuova'] ?? ''), (int)($_POST['cartella'] ?? 0));
        if ($e !== '') { $_SESSION['nli_errori'] = ['liste' => $e]; flash('error', $e); header('Location: newsletter-importa.php?id=' . $id . '&passo=3'); exit; }
        header('Location: newsletter-importa.php?id=' . $id . '&passo=4'); exit;
    }
    if ($azione === 'conferma') {
        $e = newsletter_importa_conferma($id, (array)($_POST['opz'] ?? []), !empty($_POST['consenso']));
        if ($e !== '') { $_SESSION['nli_errori'] = ['consenso' => $e]; flash('error', $e); header('Location: newsletter-importa.php?id=' . $id . '&passo=4'); exit; }
        // I file piccoli finiscono subito; quelli grandi continuano a giri (questa pagina, poi il cron).
        try { newsletter_lavori_giro(8); } catch (Throwable $x) { error_log('importa: ' . $x->getMessage()); }
        header('Location: newsletter-importa.php?id=' . $id); exit;
    }
    if ($azione === 'elimina') {
        newsletter_importa_elimina($id);
        flash('success', 'Importazione lasciata perdere: niente è entrato nei contatti. Puoi caricare un altro file.');
        header('Location: newsletter-importa.php'); exit;
    }
    header('Location: ' . $torna); exit;
}

newsletter_importa_pulizia();
$errori = $_SESSION['nli_errori'] ?? [];
$errore1 = $_SESSION['nli_errore'] ?? null;
$scelteFatte = $_SESSION['nli_scelte'] ?? ['col' => [], 'nuovo' => []];
unset($_SESSION['nli_errori'], $_SESSION['nli_errore'], $_SESSION['nli_scelte']);
if (!$imp) $passo = 1;
elseif ($imp['stato'] === 'bozza') $passo = max(2, min((int)$imp['passo'], (int)($_GET['passo'] ?? $imp['passo'])));
elseif (in_array($imp['stato'], ['pronta', 'in_corso'], true)) $passo = 'lavoro';
else $passo = 'resoconto';
$passi = [1 => 'Carica', 2 => 'Abbina i dati', 3 => 'Scegli la lista', 4 => 'Concludi'];
$attrImp = newsletter_importa_attributi();
$tipiNuovi = array_intersect_key(NL_ATTR_TIPI, array_flip(['text', 'float', 'date', 'boolean']));
$nomiListe = array_column(newsletter_liste(), 'nome', 'id');
$erroreCampo = fn(string $k, string $idc) => isset($errori[$k]) ? '<p class="errore-campo" id="' . h($idc) . '-err" role="alert">' . h($errori[$k]) . '</p>' : '';

require_once __DIR__ . '/includes/admin-header.php';
?>
<p><a href="newsletter-contatti.php">&larr; Contatti</a></p>
<?php if (is_int($passo)): ?>
<ol class="nli-passi" aria-label="I passi dell'importazione">
  <?php foreach ($passi as $k => $v): $fatto = $k < $passo; ?>
  <li class="<?= $fatto ? 'fatto' : ($k === $passo ? 'qui' : '') ?>"<?= $k === $passo ? ' aria-current="step"' : '' ?>>
    <?php if ($fatto && $imp && $k > 1 && $k <= (int)$imp['passo']): ?><a href="newsletter-importa.php?id=<?= $id ?>&amp;passo=<?= $k ?>"><?php endif; ?>
    <span class="nli-numero" aria-hidden="true"><?= $fatto ? '&#10003;' : $k ?></span> <?= h($v) ?><?= $fatto ? '<span class="sr-solo"> (fatto)</span>' : '' ?>
    <?php if ($fatto && $imp && $k > 1 && $k <= (int)$imp['passo']): ?></a><?php endif; ?>
  </li>
  <?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ($passo === 1):
  $limite = pannello_limite_caricamento();
  $modo = ($errore1['campo'] ?? '') === 'incolla' ? 'incolla' : 'file';
  $recenti = db()->query('SELECT * FROM ' . tbl('newsletter_importazioni') . " WHERE stato <> 'bozza' ORDER BY id DESC LIMIT 10")->fetchAll(); ?>
<form method="post" enctype="multipart/form-data" class="form-card nli-largo" id="nli-carica">
  <?= csrf_field() ?><input type="hidden" name="azione" value="carica">
  <h3>Passo 1: carica i contatti</h3>
  <p class="hint" style="margin-top:0">La prima riga porta i nomi delle colonne (per esempio EMAIL, NOME, COGNOME): al passo dopo li abbini agli attributi dei contatti. Non serve una colonna con l'identificativo del contatto: lo dà plumeCMS.</p>
  <fieldset class="nlk-scelte nli-modo">
    <legend>Come vuoi caricare i contatti?</legend>
    <label><input type="radio" name="modo" value="file" data-nli-modo<?= $modo === 'file' ? ' checked' : '' ?>> Carica un file</label>
    <label><input type="radio" name="modo" value="incolla" data-nli-modo<?= $modo === 'incolla' ? ' checked' : '' ?>> Copia e incolla</label>
  </fieldset>
  <div data-nli-pannello="file"<?= $modo === 'file' ? '' : ' hidden' ?>>
    <div class="form-group"><label for="nli-file">File con i contatti</label>
      <input type="file" id="nli-file" name="file" accept=".csv,.txt,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" aria-describedby="nli-file-aiuto<?= ($errore1['campo'] ?? '') === 'file' ? ' nli-file-err' : '' ?>"<?= ($errore1['campo'] ?? '') === 'file' ? ' aria-invalid="true"' : '' ?><?= $limite > 0 ? ' data-peso-massimo="' . $limite . '"' : '' ?>>
      <div class="hint" id="nli-file-aiuto">CSV, TXT o XLSX (Excel o LibreOffice), in UTF-8 o Windows-1252: gli accenti si leggono in tutti e due. Separatore punto e virgola, virgola o tabulazione: lo riconosce da solo. Questo server accetta file fino a <?= h($limite > 0 ? pannello_peso($limite) : 'nessun limite scritto') ?>: per un file più grande, dividilo.</div>
      <?php if (($errore1['campo'] ?? '') === 'file'): ?><p class="errore-campo" id="nli-file-err" role="alert"><?= h($errore1['testo']) ?></p><?php endif; ?></div>
  </div>
  <div data-nli-pannello="incolla"<?= $modo === 'incolla' ? '' : ' hidden' ?>>
    <div class="form-group"><label for="nli-testo">Contatti copiati da un foglio di calcolo</label>
      <textarea id="nli-testo" name="testo" rows="8" class="nlx-corto" placeholder="per esempio:&#10;EMAIL;NOME&#10;anna@esempio.it;Anna" aria-describedby="nli-testo-aiuto<?= ($errore1['campo'] ?? '') === 'incolla' ? ' nli-testo-err' : '' ?>"<?= ($errore1['campo'] ?? '') === 'incolla' ? ' aria-invalid="true"' : '' ?>></textarea>
      <div class="hint" id="nli-testo-aiuto">Seleziona le celle in Excel, LibreOffice o Fogli Google, copia e incolla qui, con la riga dei nomi delle colonne in cima. Va bene anche un indirizzo per riga.</div>
      <?php if (($errore1['campo'] ?? '') === 'incolla'): ?><p class="errore-campo" id="nli-testo-err" role="alert"><?= h($errore1['testo']) ?></p><?php endif; ?></div>
  </div>
  <button class="btn btn-primary" type="submit">Avanti: abbina i dati</button>
</form>

<div class="form-card nli-largo" style="margin-top:1.25rem">
  <h3>Importazioni recenti</h3>
  <?php if ($recenti): ?>
  <div class="ad-tabella"><table class="table">
    <thead><tr><th scope="col">Quando</th><th scope="col">Da dove</th><th scope="col">Stato</th><th scope="col">Nuovi</th><th scope="col">Aggiornati</th><th scope="col">Invariati</th><th scope="col">Errori</th><th scope="col">Resoconto</th></tr></thead>
    <tbody><?php foreach ($recenti as $r): ?>
      <tr><td><?= h(date('d/m/Y H:i', strtotime($r['creato']))) ?></td>
        <td><?= $r['origine'] === 'incolla' ? 'Copia e incolla' : h(newsletter_4byte_fuori($r['nome_file'])) ?></td>
        <td><?= h(['pronta' => 'In coda', 'in_corso' => 'In corso', 'finita' => 'Finita', 'errore' => 'Fermata da un errore'][$r['stato']] ?? $r['stato']) ?></td>
        <td><?= (int)$r['nuovi'] ?></td><td><?= (int)$r['aggiornati'] ?></td><td><?= (int)$r['invariati'] ?></td><td><?= (int)$r['errori'] + (int)$r['doppioni'] ?></td>
        <td><a href="newsletter-importa.php?id=<?= (int)$r['id'] ?>" aria-label="Apri il resoconto del <?= h(date('d/m/Y H:i', strtotime($r['creato']))) ?>">Apri il resoconto</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><p class="empty-state">Ancora nessuna importazione: carica il primo file qui sopra.</p><?php endif; ?>
</div>

<?php elseif ($passo === 2):
  $prime = $imp['anteprima']; ?>
<form method="post" class="form-card" id="nli-abbina">
  <?= csrf_field() ?><input type="hidden" name="azione" value="abbina">
  <h3>Passo 2: abbina i dati</h3><input type="hidden" name="id" value="<?= $id ?>">
  <p class="hint" style="margin-top:0"><?= $imp['origine'] === 'incolla' ? 'Testo incollato' : 'File «' . h(newsletter_4byte_fuori($imp['nome_file'])) . '»' ?>: <b><?= (int)$imp['righe'] ?></b> righe di contatti e <?= count($imp['intestazione']) ?> colonne. Per ogni colonna scegli l'attributo dove va: le colonne con lo stesso nome di un attributo sono già abbinate. Una colonna che non ti serve: «Non importare». La colonna con le email va abbinata a EMAIL.</p>
  <?php if (isset($errori['tutti'])): ?><p class="errore-campo" role="alert"><?= h($errori['tutti']) ?></p><?php endif; ?>
  <div class="ad-tabella"><table class="table nli-abbina">
    <thead><tr><th scope="col">Colonna del file</th><th scope="col">Prime righe</th><th scope="col">Attributo dei contatti</th></tr></thead>
    <tbody>
    <?php foreach ($imp['intestazione'] as $i => $t): $sc = (string)($scelteFatte['col'][$i] ?? $imp['mappatura'][$i] ?? ''); $nv = (array)($scelteFatte['nuovo'][$i] ?? []); $ic = 'nli-col-' . $i; $sbagliata = isset($errori[$i]); ?>
      <tr>
        <td><b><?= h(newsletter_4byte_fuori((string)$t)) ?></b></td>
        <td><?php $es = array_values(array_filter(array_map(fn($r) => (string)($r[$i] ?? ''), $prime), fn($x) => $x !== '')); ?>
          <?= $es ? '<ul class="nli-esempi">' . implode('', array_map(fn($x) => '<li>' . h(mb_strimwidth($x, 0, 60, '...')) . '</li>', array_slice($es, 0, 3))) . '</ul>' : '<span class="nlx-sotto">vuota nelle prime righe</span>' ?></td>
        <td>
          <label class="sr-solo" for="<?= $ic ?>">Attributo per la colonna «<?= h((string)$t) ?>»</label>
          <select name="col[<?= $i ?>]" id="<?= $ic ?>" data-nli-col<?= $sbagliata ? ' aria-invalid="true" aria-describedby="' . $ic . '-err"' : '' ?>>
            <option value="">Non importare</option>
            <optgroup label="Attributi">
              <?php foreach ($attrImp as $n => $a): ?><option value="<?= h($n) ?>"<?= $sc === $n ? ' selected' : '' ?>><?= h($n) ?> (<?= h(newsletter_attributo_etichetta($a)) ?>)</option><?php endforeach; ?>
              <option value="_CONSENSO"<?= $sc === '_CONSENSO' ? ' selected' : '' ?>>Data del consenso</option>
            </optgroup>
            <option value="_TAG"<?= $sc === '_TAG' ? ' selected' : '' ?>>Tag (più tag separati da virgola o punto e virgola)</option>
            <option value="_NUOVO"<?= $sc === '_NUOVO' ? ' selected' : '' ?>>Crea un nuovo attributo...</option>
          </select>
          <div class="nli-nuovo" data-nli-nuovo<?= $sc === '_NUOVO' ? '' : ' hidden' ?>>
            <div class="form-group"><label for="<?= $ic ?>-nome">Nome del nuovo attributo</label>
              <input type="text" id="<?= $ic ?>-nome" name="nuovo[<?= $i ?>][nome]" maxlength="50" value="<?= h((string)($nv['nome'] ?? newsletter_attributo_nome((string)$t))) ?>" aria-describedby="<?= $ic ?>-aiuto"></div>
            <div class="form-group"><label for="<?= $ic ?>-tipo">Tipo</label>
              <select id="<?= $ic ?>-tipo" name="nuovo[<?= $i ?>][tipo]"><?php foreach ($tipiNuovi as $k => $v): ?><option value="<?= $k ?>"<?= (isset($nv['tipo']) ? $nv['tipo'] === $k : ($k === 'date' && preg_match('/DATA|DATE|NASCITA/i', (string)$t))) ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
              <div class="hint" id="<?= $ic ?>-aiuto">Lettere maiuscole, numeri e trattini bassi (per esempio CITTA). Nome e tipo non si cambiano dopo. Si crea quando premi Avanti.</div></div>
          </div>
          <?php if ($sbagliata): ?><p class="errore-campo" id="<?= $ic ?>-err" role="alert"><?= h($errori[$i]) ?></p><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="nl-riga" style="margin-top:1rem">
    <button class="btn btn-primary" type="submit">Avanti: scegli la lista</button>
  </div>
</form>
<form method="post" class="nli-lascia" data-conferma="Lasciare perdere questa importazione? Il file caricato si cancella: niente entra nei contatti."><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $id ?>">
  <button class="btn btn-secondary btn-sm" type="submit">Lascia perdere e carica un altro file</button></form>

<?php elseif ($passo === 3):
  $cartelle = newsletter_cartelle(); ?>
<form method="post" class="form-card nli-largo" id="nli-liste">
  <?= csrf_field() ?><input type="hidden" name="azione" value="liste">
  <h3>Passo 3: scegli la lista</h3><input type="hidden" name="id" value="<?= $id ?>">
  <p class="hint" style="margin-top:0">I contatti del file entrano nelle liste che spunti (anche più di una), oppure in una lista nuova. Chi c'è già entra anche lui, senza diventare un doppione.</p>
  <?php if (isset($errori['liste'])): ?><p class="errore-campo" id="nli-liste-err" role="alert"><?= h($errori['liste']) ?></p><?php endif; ?>
  <?php foreach ($cartelle as $c): if (!$c['liste']) continue; ?>
  <fieldset class="nlk-scelte"<?= isset($errori['liste']) ? ' aria-describedby="nli-liste-err"' : '' ?>>
    <legend><?= h(newsletter_4byte_fuori($c['nome'])) ?></legend>
    <?php foreach ($c['liste'] as $l): ?><label><input type="checkbox" name="liste[]" value="<?= (int)$l['id'] ?>"<?= in_array((int)$l['id'], $imp['liste'], true) ? ' checked' : '' ?>> <?= h(newsletter_4byte_fuori($l['nome'])) ?> <span class="nlx-sotto" style="display:inline">(<?= (int)$l['totali'] ?> contatti)</span></label><?php endforeach; ?>
  </fieldset>
  <?php endforeach; ?>
  <fieldset class="nlk-scelte">
    <legend>Oppure crea una lista nuova</legend>
    <div class="nlx-campi">
      <div class="form-group"><label for="nli-nuova">Nome della lista nuova</label><input type="text" id="nli-nuova" name="lista_nuova" maxlength="100" placeholder="per esempio: Clienti 2026"></div>
      <div class="form-group"><label for="nli-cartella">Cartella</label><select id="nli-cartella" name="cartella"><?php foreach ($cartelle as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori($c['nome'])) ?></option><?php endforeach; ?></select></div>
    </div>
  </fieldset>
  <div class="nl-riga"><a class="btn btn-secondary" href="newsletter-importa.php?id=<?= $id ?>&amp;passo=2">Indietro: abbina i dati</a><button class="btn btn-primary" type="submit">Avanti: concludi</button></div>
</form>

<?php elseif ($passo === 4):
  $o = $imp['opzioni'];
  $abbinati = array_values(array_filter($imp['mappatura'])); ?>
<form method="post" class="form-card nli-largo" id="nli-conferma">
  <?= csrf_field() ?><input type="hidden" name="azione" value="conferma">
  <h3>Passo 4: concludi</h3><input type="hidden" name="id" value="<?= $id ?>">
  <dl class="nli-riepilogo">
    <div><dt>Da dove</dt><dd><?= $imp['origine'] === 'incolla' ? 'Testo incollato' : h(newsletter_4byte_fuori($imp['nome_file'])) ?></dd></div>
    <div><dt>Righe di contatti</dt><dd><?= (int)$imp['righe'] ?></dd></div>
    <div><dt>Attributi che si importano</dt><dd><?= h(implode(', ', array_map(fn($n) => $n === '_CONSENSO' ? 'data del consenso' : ($n === '_TAG' ? 'tag' : $n), $abbinati))) ?></dd></div>
    <div><dt>Liste</dt><dd><?= h(implode(', ', array_map(fn($l) => newsletter_4byte_fuori((string)($nomiListe[$l] ?? '')), $imp['liste']))) ?></dd></div>
  </dl>
  <fieldset class="nlk-scelte nli-opzioni">
    <legend>Opzioni</legend>
    <label><input type="checkbox" name="opz[aggiorna]" value="1" data-nli-aggiorna<?= $o['aggiorna'] ? ' checked' : '' ?> aria-describedby="nli-o1"> Aggiorna gli attributi dei contatti che ci sono già</label>
    <p class="hint" id="nli-o1">Spenta: chi c'è già resta com'è ed entra soltanto nelle liste scelte.</p>
    <label><input type="checkbox" name="opz[vuote]" value="1" data-nli-vuote<?= $o['vuote'] ? ' checked' : '' ?><?= $o['aggiorna'] ? '' : ' disabled' ?> aria-describedby="nli-o2"> Importa le celle vuote per cancellare i valori che ci sono</label>
    <p class="hint" id="nli-o2">Solo con l'aggiornamento acceso: una cella vuota nel file svuota quell'attributo nel contatto. Spenta, le celle vuote non toccano niente.</p>
    <label><input type="checkbox" name="opz[nera]" value="1"<?= $o['nera'] ? ' checked' : '' ?> aria-describedby="nli-o3"> Metti in lista nera email tutti i contatti del file</label>
    <p class="hint" id="nli-o3">Per importare una lista di indirizzi che non devono ricevere niente (per esempio chi si è disiscritto da un altro servizio). Motivo: messo in lista nera con un'importazione.</p>
    <label><input type="checkbox" name="opz[flussi]" value="1"<?= $o['flussi'] ? ' checked' : '' ?> aria-describedby="nli-o4"> Fai partire le automazioni delle liste per i contatti importati</label>
    <p class="hint" id="nli-o4">Spenta di partenza: chi entra con un'importazione non riceve le email di benvenuto delle automazioni.</p>
  </fieldset>
  <?php if (in_array('_TAG', $imp['mappatura'], true)): /* 2.23.0 */ ?>
  <fieldset class="nlk-scelte nli-opzioni" id="nli-tag">
    <legend>Tag dei contatti che ci sono già</legend>
    <label><input type="radio" name="opz[tag]" value="aggiungi"<?= ($o['tag'] ?? 'aggiungi') !== 'sostituisci' ? ' checked' : '' ?> aria-describedby="nli-t1"> Aggiungi ai tag che ha</label>
    <p class="hint" id="nli-t1">I tag del file si aggiungono; quelli che il contatto aveva restano.</p>
    <label><input type="radio" name="opz[tag]" value="sostituisci"<?= ($o['tag'] ?? '') === 'sostituisci' ? ' checked' : '' ?> aria-describedby="nli-t2"> Sostituisci i tag che ha</label>
    <p class="hint" id="nli-t2">Il contatto tiene solo i tag scritti nel file. Una cella vuota non toglie niente. I contatti nuovi ricevono i tag del file in tutti e due i casi; i tag nuovi nascono da soli. L'innesco «Riceve un tag» parte solo con la spunta delle automazioni qui sopra.</p>
  </fieldset>
  <?php endif; ?>
  <p class="nl-avviso" role="note"><b>Attenzione:</b> alcune di queste opzioni sovrascrivono i dati dei contatti che ci sono già o li mettono in lista nera, e non si torna indietro.</p>
  <div class="nli-consenso<?= isset($errori['consenso']) ? ' sbagliato' : '' ?>">
    <label><input type="checkbox" name="consenso" value="1" required aria-describedby="nli-consenso-aiuto<?= isset($errori['consenso']) ? ' nli-consenso-err' : '' ?>"<?= isset($errori['consenso']) ? ' aria-invalid="true"' : '' ?>> Confermo che questi contatti hanno dato il loro consenso esplicito a ricevere le mie email</label>
    <p class="hint" id="nli-consenso-aiuto">Si mandano email solo a chi ha dato un consenso esplicito. Il consenso si segna nel registro di ogni contatto nuovo, con il numero di questa importazione.</p>
    <?php if (isset($errori['consenso'])): ?><p class="errore-campo" id="nli-consenso-err" role="alert"><?= h($errori['consenso']) ?></p><?php endif; ?>
  </div>
  <div class="nl-riga"><a class="btn btn-secondary" href="newsletter-importa.php?id=<?= $id ?>&amp;passo=3">Indietro: scegli la lista</a><button class="btn btn-primary" type="submit">Conferma l'importazione</button></div>
</form>

<?php elseif ($passo === 'lavoro'):
  $pct = (int)round((int)$imp['fatte'] * 100 / max(1, (int)$imp['righe'])); ?>
<div class="form-card nli-largo" data-nl-giro="newsletter-importa.php" data-id="<?= $id ?>" data-csrf="<?= h(csrf_token()) ?>">
  <h3>Importazione in corso</h3>
  <p class="hint" style="margin-top:0">Il lavoro va a giri brevi. Puoi lasciare aperta questa pagina, oppure chiuderla: il cron lo continua ogni 5 minuti e il resoconto lo trovi in Contatti, Importa contatti, Importazioni recenti.</p>
  <div class="nli-barra" role="progressbar" aria-label="Avanzamento dell'importazione" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>" data-nl-barra><span style="width:<?= $pct ?>%"></span></div>
  <p data-nl-testo role="status"><?= (int)$imp['fatte'] ?> righe su <?= (int)$imp['righe'] ?></p>
  <p class="errore-campo" data-nl-errore role="alert" hidden></p>
  <button class="btn btn-secondary btn-sm" type="button" data-nl-riprendi hidden>Riprendi</button>
</div>

<?php else: /* resoconto */
  $d = $imp['dettagli']; ?>
<div class="form-card nli-largo" id="nli-resoconto">
  <h3>Resoconto dell'importazione</h3>
  <?php if ($imp['stato'] === 'errore'): ?><p class="errore-campo" role="alert">L'importazione si è fermata: <?= h($imp['errore']) ?> Le righe fatte prima dell'errore sono entrate; per le altre rifai l'importazione con lo stesso file (chi c'è già non diventa un doppione).</p><?php endif; ?>
  <p class="hint" style="margin-top:0"><?= h(date('d/m/Y H:i', strtotime($imp['creato']))) ?>, <?= $imp['origine'] === 'incolla' ? 'testo incollato' : 'file «' . h(newsletter_4byte_fuori($imp['nome_file'])) . '»' ?>, <?= (int)$imp['righe'] ?> righe. Liste: <?= h(implode(', ', array_map(fn($l) => newsletter_4byte_fuori((string)($nomiListe[$l] ?? 'lista eliminata')), $imp['liste']))) ?>.</p>
  <dl class="nlk-numeri">
    <div><dt>Nuovi</dt><dd><?= (int)$imp['nuovi'] ?></dd></div>
    <div><dt>Aggiornati</dt><dd><?= (int)$imp['aggiornati'] ?></dd></div>
    <div><dt>Invariati</dt><dd><?= (int)$imp['invariati'] ?></dd></div>
    <div><dt>Doppioni nel file</dt><dd><?= (int)$imp['doppioni'] ?></dd></div>
    <div><dt>Errori</dt><dd><?= (int)$imp['errori'] ?></dd></div>
  </dl>
  <ul class="nli-note">
    <?php if ((int)($d['bloccati'] ?? 0)): ?><li><?= (int)$d['bloccati'] === 1 ? '1 fra gli invariati è' : (int)$d['bloccati'] . ' fra gli invariati sono' ?> in lista nera: <?= (int)$d['bloccati'] === 1 ? 'resta fuori dalle liste e non torna iscritto' : 'restano fuori dalle liste e non tornano iscritti' ?>.</li><?php endif; ?>
    <?php if ((int)($d['non_valide'] ?? 0)): ?><li><?= (int)$d['non_valide'] === 1 ? '1 riga' : (int)$d['non_valide'] . ' righe' ?> senza un'email valida.</li><?php endif; ?>
    <?php if ((int)($d['soppressi'] ?? 0)): ?><li><?= (int)$d['soppressi'] === 1 ? '1 persona aveva' : (int)$d['soppressi'] . ' persone avevano' ?> chiesto di cancellare i loro dati: non si importano.</li><?php endif; ?>
    <?php if ((int)($d['ext'] ?? 0)): ?><li><?= (int)$d['ext'] === 1 ? '1 riga' : (int)$d['ext'] . ' righe' ?> con un identificativo esterno già di un altro contatto.</li><?php endif; ?>
    <?php if ($imp['opzioni']['nera']): ?><li>Tutti i contatti del file sono in lista nera (motivo: importazione).</li><?php endif; ?>
  </ul>
  <div class="nl-riga">
    <?php if ($imp['scarti_file'] !== ''): ?><a class="btn btn-secondary" href="newsletter-importa.php?id=<?= $id ?>&amp;scarica=scarti">Scarica le righe scartate (CSV)</a><?php endif; ?>
    <?php if (count($imp['liste']) === 1): ?><a class="btn btn-secondary" href="newsletter-contatti.php?lista=<?= (int)$imp['liste'][0] ?>">Vedi i contatti della lista</a><?php endif; ?>
    <a class="btn btn-primary" href="newsletter-importa.php">Importa altri contatti</a>
  </div>
  <?php if ($imp['scarti_file'] !== ''): ?><p class="hint">Il file delle righe scartate resta <?= NL_FILE_GIORNI ?> giorni: ogni riga dice il motivo.</p><?php endif; ?>
</div>
<?php endif; ?>
<script src="<?= pannello_file('assets/contatti.js') ?>"></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
