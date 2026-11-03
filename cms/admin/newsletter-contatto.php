<?php
/**
 * 2.8.0: la scheda del contatto come Brevo, con le quattro schede Panoramica (attributi modificabili, statistiche email
 * degli ultimi 90 giorni, attività recente), Cronologia (registro completo con filtro per tipo, periodo e ricerca),
 * Liste e Preferenze (canale email Iscritto o In lista nera). Con ?nuovo=1: Crea un contatto.
 * Motore in includes/newsletter-contatti.php. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-tag.php'; // 2.23.0: i tag del contatto
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();

$nuovo = !empty($_GET['nuovo']);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$schede = ['panoramica' => 'Panoramica', 'cronologia' => 'Cronologia', 'liste' => 'Liste', 'preferenze' => 'Preferenze'];
$scheda = isset($schede[$_GET['scheda'] ?? '']) ? $_GET['scheda'] : 'panoramica';
$attributi = newsletter_attributi();

/** I valori degli attributi arrivati dal form (campi a[NOME], scelte multiple a[NOME][]). */
function nlk_dati_form(array $attributi): array {
    $in = is_array($_POST['a'] ?? null) ? $_POST['a'] : [];
    $out = [];
    foreach ($attributi as $n => $a) {
        if (in_array($a['categoria'], ['calculated', 'global'], true) || in_array($n, ['CREATION_DATE', 'LAST_CHANGED', 'DOUBLE_OPT-IN'], true)) continue;
        if ($a['tipo'] === 'multiple-choice') { if (!empty($_POST['m'][$n])) $out[$n] = is_array($in[$n] ?? null) ? array_map('strval', $in[$n]) : []; continue; }
        if (array_key_exists($n, $in) && !is_array($in[$n])) $out[$n] = (string)$in[$n];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $torna = fn(string $s = 'panoramica') => header('Location: newsletter-contatto.php?id=' . $id . ($s !== 'panoramica' ? '&scheda=' . $s : ''));
    if ($azione === 'crea') {
        $liste = array_map('intval', (array)($_POST['liste'] ?? []));
        $nuova = trim((string)($_POST['lista_nuova'] ?? ''));
        if ($nuova !== '') {
            $r = newsletter_lista_crea($nuova, (int)($_POST['cartella'] ?? 0));
            if (is_int($r)) $liste[] = $r; else flash('error', 'La lista nuova non è stata creata: ' . $r);
        }
        $r = newsletter_contatto_crea(nlk_dati_form($attributi), $liste);
        if (isset($r['errore'])) {
            // Regole di interfaccia 4.2: in cima il riassunto, sotto il campo l'errore; i valori scritti restano.
            flash('error', 'Il contatto non è stato creato: c\'è un campo da sistemare, segnato in rosso qui sotto.');
            $_SESSION['nlk_crea'] = ['errore' => $r['errore'], 'dati' => nlk_dati_form($attributi), 'liste' => $liste];
            header('Location: newsletter-contatto.php?nuovo=1'); exit;
        }
        if (isset($r['esiste'])) { flash('info', 'Questo contatto c\'è già: eccolo. Non ne è stato creato un doppione.'); header('Location: newsletter-contatto.php?id=' . (int)$r['esiste']); exit; }
        flash('success', 'Contatto creato. Lo trovi anche nella pagina Contatti.');
        header('Location: newsletter-contatto.php?id=' . (int)$r['id']); exit;
    }
    if (!newsletter_contatto($id)) { flash('error', 'Questo contatto non c\'è più.'); header('Location: newsletter-contatti.php'); exit; }
    if ($azione === 'salva') {
        $r = newsletter_valori_salva($id, nlk_dati_form($attributi));
        foreach ($r['errori'] as $e) flash('error', $e);
        if ($r['cambiati']) flash('success', 'Informazioni del contatto salvate.');
        elseif (!$r['errori']) flash('info', 'Niente da salvare: i valori erano già questi (un valore del tipo sbagliato non si salva).');
        $torna(); exit;
    }
    if ($azione === 'tag_aggiungi') {
        // 2.23.0: uno o più tag scritti nel campo (separati da virgola); un tag nuovo nasce da solo.
        $nomi = newsletter_tag_dividi($_POST['tag'] ?? '');
        if (!$nomi) { flash('error', 'Scrivi il tag da aggiungere (più tag separati da virgola), poi premi «Aggiungi».'); $_SESSION['nlk_tag_errore'] = 'Scrivi il nome del tag: lettere, numeri e spazi, senza virgole dentro il nome.'; header('Location: newsletter-contatto.php?id=' . $id . '#nlk-tag'); exit; }
        $n = newsletter_tag_aggiungi($id, $nomi, 'dalla scheda del contatto');
        $n ? flash('success', $n === 1 ? 'Tag aggiunto.' : "$n tag aggiunti.") : flash('info', count(newsletter_tag_di($id)) >= NL_TAG_PER_CONTATTO ? 'Il contatto ha già ' . NL_TAG_PER_CONTATTO . ' tag: è il massimo.' : 'Il contatto aveva già questi tag.');
        header('Location: newsletter-contatto.php?id=' . $id . '#nlk-tag'); exit;
    }
    if ($azione === 'tag_togli') {
        newsletter_tag_togli($id, [(int)($_POST['tag_id'] ?? 0)], 'dalla scheda del contatto') ? flash('success', 'Tag tolto.') : flash('info', 'Il contatto non aveva più questo tag.');
        header('Location: newsletter-contatto.php?id=' . $id . '#nlk-tag'); exit;
    }
    if ($azione === 'liste') {
        $scelte = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['liste'] ?? [])))));
        $prima = newsletter_liste_di($id);
        newsletter_togli_da_liste([$id], array_diff($prima, $scelte));
        foreach (array_diff($scelte, $prima) as $l) newsletter_in_lista($id, $l);
        flash('success', 'Liste del contatto aggiornate.');
        $torna('liste'); exit;
    }
    if ($azione === 'blocca') {
        newsletter_contatto_blocca($id, 'adminBlocked') ? flash('success', 'Contatto in lista nera: non riceve più campagne né automazioni.') : flash('error', 'Il contatto non è cambiato.');
        $torna('preferenze'); exit;
    }
    if ($azione === 'sblocca') {
        // Brevo: si toglie dalla lista nera solo da qui, uno alla volta, dopo l'avviso legale.
        if (empty($_POST['consenso'])) { flash('error', 'Per togliere il contatto dalla lista nera spunta la casella: devi avere il suo consenso a riscrivergli.'); $torna('preferenze'); exit; }
        newsletter_contatto_sblocca($id) ? flash('success', 'Contatto tolto dalla lista nera: torna iscritto e riceverà le prossime campagne delle sue liste.') : flash('error', 'Il contatto non era in lista nera.');
        $torna('preferenze'); exit;
    }
    if ($azione === 'elimina') {
        newsletter_contatti_elimina([$id]);
        flash('success', 'Contatto eliminato definitivamente, con attributi, cronologia e statistiche. La sua email resta nella lista di soppressione: un\'importazione non lo rimetterà dentro.');
        header('Location: newsletter-contatti.php'); exit;
    }
    $torna(); exit;
}

$cartelle = newsletter_cartelle();

/** Il campo di un attributo, secondo il tipo. */
function nlk_campo(array $a, $v, bool $nuovo, string $errore = ''): string {
    $n = $a['nome'];
    $nid = 'nlk-' . strtolower(preg_replace('/[^A-Za-z0-9]/', '-', $n));
    $et = h(newsletter_attributo_etichetta($a)) . ($a['etichetta'] !== '' && $a['etichetta'] !== $a['nome'] ? ' <small class="nlk-nome">' . h($n) . '</small>' : '');
    $nm = 'a[' . h($n) . ']';
    $solaLettura = in_array($a['categoria'], ['calculated', 'global'], true) || in_array($n, ['CREATION_DATE', 'LAST_CHANGED', 'DOUBLE_OPT-IN'], true);
    if ($solaLettura) {
        if ($nuovo) return '';
        $t = newsletter_valore_testo($a, $v);
        if ($n === 'CREATION_DATE' || $n === 'LAST_CHANGED') $t = $v ? date('d/m/Y', strtotime((string)$v)) : '';
        return '<div class="form-group"><span class="nlk-etichetta">' . $et . '</span><p class="nlk-valore">' . ($t !== '' ? h($t) : '<span class="hint">vuoto</span>') . ($a['categoria'] === 'calculated' ? ' <small class="hint">calcolato: ' . h($a['formula']) . '</small>' : '') . '</p></div>';
    }
    $req = $n === 'EMAIL' ? ' required' : '';
    switch ($a['tipo']) {
        case 'boolean':
            $s = $v === '' || $v === null ? '' : ((int)$v ? '1' : '0');
            return '<div class="form-group"><label for="' . $nid . '">' . $et . '</label><select id="' . $nid . '" name="' . $nm . '">'
                . '<option value=""' . ($s === '' ? ' selected' : '') . '>Non indicato</option><option value="1"' . ($s === '1' ? ' selected' : '') . '>Sì</option><option value="0"' . ($s === '0' ? ' selected' : '') . '>No</option></select></div>';
        case 'category':
            $h = '<div class="form-group"><label for="' . $nid . '">' . $et . '</label><select id="' . $nid . '" name="' . $nm . '"><option value="">Nessuna scelta</option>';
            foreach ($a['opzioni'] as $k => $o) $h .= '<option value="' . (int)$k . '"' . ((string)$v === (string)$k ? ' selected' : '') . '>' . h(newsletter_4byte_fuori($o)) . '</option>';
            return $h . '</select></div>';
        case 'multiple-choice':
            $h = '<fieldset class="form-group nlk-scelte"><legend>' . $et . '</legend><input type="hidden" name="m[' . h($n) . ']" value="1">';
            foreach ($a['opzioni'] as $k => $o) $h .= '<label><input type="checkbox" name="' . $nm . '[]" value="' . (int)$k . '"' . (in_array((int)$k, (array)$v, true) ? ' checked' : '') . '> ' . h(newsletter_4byte_fuori($o)) . '</label>';
            return $h . ($a['opzioni'] ? '' : '<span class="hint">Nessuna opzione: aggiungile in Attributi dei contatti.</span>') . '</fieldset>';
        case 'date':
            return '<div class="form-group"><label for="' . $nid . '">' . $et . '</label><input type="date" id="' . $nid . '" name="' . $nm . '" value="' . h((string)$v) . '"></div>';
        case 'float':
            return '<div class="form-group"><label for="' . $nid . '">' . $et . '</label><input type="text" inputmode="decimal" id="' . $nid . '" name="' . $nm . '" value="' . h($v === '' || $v === null ? '' : str_replace('.', ',', rtrim(rtrim(sprintf('%.6F', (float)$v), '0'), '.'))) . '" placeholder="per esempio: 12,5"></div>';
    }
    $tipo = $n === 'EMAIL' ? 'email' : 'text';
    $max = $n === 'EMAIL' || $n === 'EXT_ID' ? 190 : (in_array($n, ['NOME', 'COGNOME'], true) ? 100 : 500);
    $ph = ['EMAIL' => 'per esempio: anna@esempio.it', 'NOME' => 'per esempio: Anna', 'COGNOME' => 'per esempio: Rossi', 'EXT_ID' => 'per esempio: il codice cliente del gestionale'][$n] ?? '';
    $err = $errore !== '' ? ' aria-invalid="true" aria-describedby="' . $nid . '-errore"' : '';
    return '<div class="form-group"><label for="' . $nid . '">' . $et . ($n === 'EMAIL' ? ' (obbligatoria)' : '') . '</label><input type="' . $tipo . '" id="' . $nid . '" name="' . $nm . '" value="' . h(newsletter_4byte_fuori((string)$v)) . '" maxlength="' . $max . '"' . $req . $err . ($ph !== '' ? ' placeholder="' . h($ph) . '"' : '') . '>'
        . ($errore !== '' ? '<p class="errore-campo" id="' . $nid . '-errore" role="alert">' . h($errore) . '</p>' : '') . '</div>';
}

/** Le caselle delle liste, raggruppate per cartella. */
function nlk_liste(array $cartelle, array $scelte): string {
    $h = '';
    foreach ($cartelle as $c) {
        if (!$c['liste']) continue;
        $h .= '<fieldset class="nlk-scelte"><legend>' . h(newsletter_4byte_fuori($c['nome'])) . '</legend>';
        foreach ($c['liste'] as $l) $h .= '<label><input type="checkbox" name="liste[]" value="' . (int)$l['id'] . '"' . (in_array((int)$l['id'], $scelte, true) ? ' checked' : '') . '> ' . h(newsletter_4byte_fuori($l['nome'])) . '</label>';
        $h .= '</fieldset>';
    }
    return $h;
}

if ($nuovo) {
    $adminTitle = 'Crea un contatto';
    require_once __DIR__ . '/includes/admin-header.php'; ?>
<p><a href="newsletter-contatti.php">&larr; Torna ai contatti</a></p>
<form method="post" class="form-card nlk-largo">
  <?= csrf_field() ?><input type="hidden" name="azione" value="crea">
  <h3>Crea un contatto</h3>
  <p class="hint" style="margin-top:0">Un campo per ogni attributo. Serve almeno l'email; gli altri campi sono facoltativi. Se un contatto con la stessa email (o lo stesso identificativo esterno) c'è già, si apre quello: niente doppioni. Il contatto nasce iscritto: aggiungi solo chi ti ha dato il consenso.</p>
  <div class="nlk-campi">
    <?php $prec = is_array($_SESSION['nlk_crea'] ?? null) ? $_SESSION['nlk_crea'] : ['errore' => '', 'dati' => [], 'liste' => []]; unset($_SESSION['nlk_crea']);
    foreach ($attributi as $a) echo nlk_campo($a, $prec['dati'][$a['nome']] ?? '', true, $a['nome'] === 'EMAIL' ? (string)$prec['errore'] : ''); ?>
  </div>
  <h3>Liste</h3>
  <?= nlk_liste($cartelle, array_map('intval', (array)$prec['liste'])) ?>
  <div class="nlk-campi">
    <div class="form-group"><label for="nlk-lista-nuova">Oppure crea una lista</label><input type="text" id="nlk-lista-nuova" name="lista_nuova" maxlength="100" placeholder="per esempio: Clienti del negozio"></div>
    <div class="form-group"><label for="nlk-cartella">Cartella della lista nuova</label><select id="nlk-cartella" name="cartella"><?php foreach ($cartelle as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori($c['nome'])) ?></option><?php endforeach; ?></select></div>
  </div>
  <p><button class="btn btn-primary" type="submit">Crea</button></p>
</form>
<?php require_once __DIR__ . '/includes/admin-footer.php'; exit;
}

$c = newsletter_contatto($id);
if (!$c) { flash('error', 'Questo contatto non c\'è: forse è stato eliminato.'); header('Location: newsletter-contatti.php'); exit; }
$valori = newsletter_valori_di([$id])[$id] ?? [];
$nera = in_array($c['stato'], NL_BLOCCATI, true);
$nomeIntero = trim(newsletter_4byte_fuori($c['nome'] . ' ' . $c['cognome']));
$adminTitle = 'Contatto: ' . ($nomeIntero !== '' ? $nomeIntero : $c['email']);
require_once __DIR__ . '/includes/admin-header.php';
?>
<p><a href="newsletter-contatti.php">&larr; Torna ai contatti</a></p>
<div class="nlk-testa">
  <div>
    <h2><?= h($nomeIntero !== '' ? $nomeIntero : $c['email']) ?></h2>
    <p><?= h($c['email']) ?> &middot; <span class="nlx-stato <?= $nera ? 'nlx-nera' : ($c['stato'] === 'attivo' ? 'nlx-si' : '') ?>"><?= $nera ? '&#10007; ' : ($c['stato'] === 'attivo' ? '&#10003; ' : '') ?><?= h(newsletter_stato_testo($c)) ?></span>
      <?= $nera ? ' &middot; ' . h(newsletter_motivo_testo($c)) : '' ?> &middot; creato il <?= h(date('d/m/Y', strtotime($c['creato']))) ?></p>
  </div>
  <form method="post" data-conferma="Eliminare definitivamente <?= h($c['email']) ?>? Si perdono attributi, cronologia, statistiche e lista nera. Non si torna indietro. Meglio non cancellare chi è in lista nera: plumeCMS tiene comunque l'impronta dell'email, così un'importazione non lo rimette dentro." data-conferma-ok="Elimina definitivamente" data-conferma-pericolo>
    <?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn-danger btn-sm" type="submit">Elimina definitivamente</button>
  </form>
</div>

<nav class="imp-schede" aria-label="Schede del contatto">
  <?php foreach ($schede as $k => $v): ?><a href="newsletter-contatto.php?id=<?= $id ?><?= $k !== 'panoramica' ? '&amp;scheda=' . $k : '' ?>"<?= $scheda === $k ? ' class="attiva" aria-current="page"' : '' ?>><?= $v ?></a><?php endforeach; ?>
</nav>

<?php if ($scheda === 'panoramica'): $s = newsletter_contatto_statistiche($id); $recenti = newsletter_contatto_cronologia($id, [], 6); ?>
<div class="nlk-griglia">
  <form method="post" class="form-card">
    <?= csrf_field() ?><input type="hidden" name="azione" value="salva">
    <h3>Informazioni del contatto</h3>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="nlk-campi"><?php foreach ($attributi as $a) if ($a['categoria'] !== 'global') echo nlk_campo($a, $valori[$a['nome']] ?? '', false); ?></div>
    <p class="hint">Un valore del tipo sbagliato (per esempio delle lettere in un numero) non si salva. Gli attributi si creano in <a href="newsletter-attributi.php">Attributi dei contatti</a>.</p>
    <button class="btn btn-primary" type="submit">Salva le informazioni</button>
  </form>
  <div>
    <section class="form-card" aria-labelledby="nlk-stat">
      <h3 id="nlk-stat">Statistiche email degli ultimi <?= (int)$s['giorni'] ?> giorni</h3>
      <dl class="nlk-numeri">
        <?php foreach (['inviate' => 'Inviate', 'consegnate' => 'Consegnate', 'aperte' => 'Aperte', 'cliccate' => 'Cliccate', 'hard' => 'Hard bounce', 'soft' => 'Soft bounce', 'spam' => 'Segnalate come spam', 'uscite' => 'Disiscrizioni'] as $k => $v): ?>
        <div><dt><?= $v ?></dt><dd><?= (int)$s[$k] ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <?php if ($s['link']): ?><h3>Link cliccati</h3><ul class="nlk-link"><?php foreach ($s['link'] as $l): ?><li><?= h($l['url']) ?> <small>(<?= (int)$l['n'] ?> clic, l'ultimo il <?= h(date('d/m/Y', strtotime($l['ultimo']))) ?>)</small></li><?php endforeach; ?></ul><?php endif; ?>
      <p class="hint">Campagne e automazioni. Chi blocca le immagini non risulta fra le aperture: i numeri sono per difetto.</p>
    </section>
    <?php $suoiTag = newsletter_tag_di($id); $tuttiTag = newsletter_tag_nomi(); $tagErr = is_string($_SESSION['nlk_tag_errore'] ?? null) ? $_SESSION['nlk_tag_errore'] : ''; unset($_SESSION['nlk_tag_errore']); ?>
    <section class="form-card nlk-tag" id="nlk-tag" aria-labelledby="nlk-tag-t" style="margin-top:1rem">
      <h3 id="nlk-tag-t">Tag</h3>
      <?php if ($suoiTag): ?><ul class="nlk-tag-elenco" aria-label="I tag del contatto">
        <?php foreach ($suoiTag as $tid => $tn): ?><li class="nlk-tag-voce"><a href="newsletter-contatti.php?tag=<?= (int)$tid ?>"><?= h($tn) ?></a><form method="post" class="nlk-tag-togli"><?= csrf_field() ?><input type="hidden" name="azione" value="tag_togli"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tag_id" value="<?= (int)$tid ?>"><button type="submit" aria-label="Togli il tag <?= h($tn) ?>" title="Togli il tag">&#215;</button></form></li><?php endforeach; ?>
      </ul><?php else: ?><p class="hint" style="margin-top:0">Nessun tag: aggiungine uno qui sotto (per esempio «Cliente»).</p><?php endif; ?>
      <form method="post" class="nlk-tag-aggiungi"><?= csrf_field() ?><input type="hidden" name="azione" value="tag_aggiungi"><input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group"><label for="nlk-tag-nuovo">Aggiungi un tag</label>
          <input type="text" id="nlk-tag-nuovo" name="tag" maxlength="600" list="nlk-tag-suggeriti" autocomplete="off" placeholder="per esempio: Cliente" aria-describedby="nlk-tag-aiuto<?= $tagErr !== '' ? ' nlk-tag-err' : '' ?>"<?= $tagErr !== '' ? ' aria-invalid="true"' : '' ?>>
          <datalist id="nlk-tag-suggeriti"><?php foreach (array_diff_key($tuttiTag, $suoiTag) as $tn): ?><option value="<?= h($tn) ?>"><?php endforeach; ?></datalist>
          <div class="hint" id="nlk-tag-aiuto">Scrivi: compaiono i tag che ci sono già. Più tag separati da virgola; un tag nuovo nasce da solo. Tutti i tag in <a href="newsletter-tag.php">Tag</a>.</div>
          <?php if ($tagErr !== ''): ?><p class="errore-campo" id="nlk-tag-err" role="alert"><?= h($tagErr) ?></p><?php endif; ?></div>
        <button class="btn btn-secondary btn-sm" type="submit">Aggiungi il tag</button>
      </form>
    </section>
    <?php $formC = newsletter_contatto_cronologia($id, ['tipo' => 'form'], 5); ?>
    <section class="form-card" aria-labelledby="nlk-form" style="margin-top:1rem">
      <h3 id="nlk-form">Form compilati</h3>
      <?php if ($formC): ?><ul class="nlk-cronologia"><?php foreach ($formC as $v): ?><li><time><?= h(date('d/m/Y H:i', strtotime($v['quando']))) ?></time> <?= h($v['testo']) ?></li><?php endforeach; ?></ul>
        <p><a href="form-submissions.php">Apri gli invii dei form</a></p>
      <?php else: ?><p class="hint">Nessun form compilato con questa email.</p><?php endif; ?>
    </section>
    <section class="form-card" aria-labelledby="nlk-rec" style="margin-top:1rem">
      <h3 id="nlk-rec">Attività recente</h3>
      <?php if ($recenti): ?><ul class="nlk-cronologia"><?php foreach ($recenti as $v): ?><li><time><?= h(date('d/m/Y H:i', strtotime($v['quando']))) ?></time> <?= h($v['testo']) ?></li><?php endforeach; ?></ul>
        <p><a href="newsletter-contatto.php?id=<?= $id ?>&amp;scheda=cronologia">Vedi tutta la cronologia</a></p>
      <?php else: ?><p class="empty-state" style="padding:1rem">Ancora nessuna attività: comparirà qui quando il contatto riceve un'email, compila un form o entra in una lista.</p><?php endif; ?>
    </section>
  </div>
</div>

<?php elseif ($scheda === 'cronologia'):
  $fc = ['tipo' => isset(NL_CRONOLOGIA_TIPI[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : '', 'da' => (string)($_GET['da'] ?? ''), 'a' => (string)($_GET['a'] ?? ''), 'cerca' => mb_substr(trim((string)($_GET['cerca'] ?? '')), 0, 100)];
  $voci = newsletter_contatto_cronologia($id, $fc, 500);
  $filtrata = $fc['tipo'] !== '' || $fc['da'] !== '' || $fc['a'] !== '' || $fc['cerca'] !== ''; ?>
<div class="form-card">
  <h3>Cronologia</h3>
  <form method="get" class="nlx-filtri" role="search" aria-label="Filtra la cronologia">
    <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="scheda" value="cronologia">
    <div class="form-group"><label for="nlk-tipo">Tipo di attività</label><select id="nlk-tipo" name="tipo"><option value="">Tutte</option><?php foreach (NL_CRONOLOGIA_TIPI as $k => $v): ?><option value="<?= $k ?>"<?= $fc['tipo'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="nlk-da">Dal</label><input type="date" id="nlk-da" name="da" value="<?= h($fc['da']) ?>"></div>
    <div class="form-group"><label for="nlk-a">Al</label><input type="date" id="nlk-a" name="a" value="<?= h($fc['a']) ?>"></div>
    <div class="form-group"><label for="nlk-cerca">Cerca</label><input type="search" id="nlk-cerca" name="cerca" value="<?= h($fc['cerca']) ?>" placeholder="per esempio: il nome di una campagna"></div>
    <div class="nlx-filtri-azioni"><button class="btn btn-secondary btn-sm" type="submit">Filtra</button><?php if ($filtrata): ?> <a href="newsletter-contatto.php?id=<?= $id ?>&amp;scheda=cronologia">Togli i filtri</a><?php endif; ?></div>
  </form>
  <div class="ad-tabella"><table class="table">
    <thead><tr><th scope="col">Quando</th><th scope="col">Tipo</th><th scope="col">Cosa è successo</th></tr></thead>
    <tbody>
    <?php foreach ($voci as $v): ?><tr><td><?= h(date('d/m/Y H:i', strtotime($v['quando']))) ?></td><td><?= h(NL_CRONOLOGIA_TIPI[$v['tipo']] ?? $v['tipo']) ?></td><td><?= h($v['testo']) ?></td></tr><?php endforeach; ?>
    <?php if (!$voci): ?><tr><td colspan="3"><div class="empty-state"><p><?= $filtrata ? 'Nessuna attività con questi filtri: prova un periodo più lungo o un altro tipo.' : 'Ancora nessuna attività per questo contatto.' ?></p></div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($scheda === 'liste'): $sue = newsletter_liste_di($id);
  $uscite = db()->prepare('SELECT u.quando, l.nome FROM ' . tbl('newsletter_liste_uscite') . ' u JOIN ' . tbl('newsletter_liste') . ' l ON l.id = u.lista_id WHERE u.contatto_id = ?');
  $uscite->execute([$id]); $uscite = $uscite->fetchAll(); ?>
<form method="post" class="form-card nlk-largo">
  <?= csrf_field() ?><input type="hidden" name="azione" value="liste">
  <h3>Liste del contatto</h3>
  <input type="hidden" name="id" value="<?= $id ?>">
  <p class="hint" style="margin-top:0">Spunta le liste in cui deve stare. Entrando in una lista partono le sue automazioni, se il contatto è iscritto.</p>
  <?= nlk_liste($cartelle, $sue) ?: '<p class="empty-state">Ancora nessuna lista: creane una in <a href="newsletter-contatti.php?scheda=liste">Contatti &gt; Liste</a>.</p>' ?>
  <?php if ($uscite): ?><p class="hint">Disiscritto da: <?= h(implode(', ', array_map(fn($u) => newsletter_4byte_fuori($u['nome']) . ' (' . date('d/m/Y', strtotime($u['quando'])) . ')', $uscite))) ?>. Da queste liste non riceve campagne.</p><?php endif; ?>
  <button class="btn btn-primary" type="submit">Salva le liste</button>
</form>

<?php else: /* Preferenze */ ?>
<section class="form-card nlk-largo" aria-labelledby="nlk-pref">
  <h3 id="nlk-pref">Campagne e automazioni: email</h3>
  <p class="nlk-pref-stato"><span class="nlx-stato <?= $nera ? 'nlx-nera' : ($c['stato'] === 'attivo' ? 'nlx-si' : '') ?>"><?= $nera ? '&#10007; In lista nera' : ($c['stato'] === 'attivo' ? '&#10003; Iscritto' : '&#8230; In attesa di conferma') ?></span>
    <?php if ($nera): ?> &middot; <?= h(newsletter_motivo_testo($c)) ?><?= $c['blocco_at'] ? ', dal ' . h(date('d/m/Y H:i', strtotime($c['blocco_at']))) : '' ?><?php endif; ?></p>
  <?php if (!$nera): ?>
    <p class="hint"><?= $c['stato'] === 'attivo' ? 'Riceve le campagne delle sue liste e le automazioni.' : 'Non ha ancora aperto il link di conferma: riceve le email solo dopo.' ?> In lista nera non riceve più niente, ma resta nei contatti con la sua storia.</p>
    <form method="post" data-conferma="Mettere <?= h($c['email']) ?> in lista nera? Non riceverà più campagne né automazioni." data-conferma-ok="Metti in lista nera">
      <?= csrf_field() ?><input type="hidden" name="azione" value="blocca"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn btn-danger" type="submit">Metti in lista nera</button>
    </form>
  <?php else: ?>
    <div class="nl-avviso" role="note"><b>Attenzione.</b> Scrivere a chi si è disiscritto o ha chiesto di non ricevere email è vietato dalla legge (GDPR) e rovina la reputazione del tuo indirizzo. Togli il contatto dalla lista nera solo se ti ha chiesto di nuovo di ricevere le tue email, e se puoi dimostrarlo. Se è possibile, chiedigli di iscriversi di nuovo dal modulo del sito: la conferma dell'email lo rimette dentro da sola.</div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="azione" value="sblocca"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="form-group"><label><input type="checkbox" name="consenso" value="1" required> Il contatto mi ha dato di nuovo il consenso a ricevere le email</label></div>
      <button class="btn btn-primary" type="submit">Togli dalla lista nera e riscrivi</button>
    </form>
  <?php endif; ?>
  <h3>Consenso</h3>
  <ul class="nlk-cronologia">
    <li>Consenso (OPT_IN): <?= (int)$c['opt_in'] ? 'sì' : 'no' ?><?= $c['consenso_at'] ? ', il ' . h(date('d/m/Y H:i', strtotime($c['consenso_at']))) : '' ?><?= $c['consenso_versione'] !== '' ? ' (' . h($c['consenso_versione']) . ')' : '' ?></li>
    <li>Doppio opt-in (DOUBLE_OPT-IN): <?= $c['doi_at'] ? 'confermato il ' . h(date('d/m/Y H:i', strtotime($c['doi_at']))) : 'no' ?></li>
    <li>Arrivato da: <?= h(['modulo' => 'modulo della newsletter', 'form' => 'un form del sito', 'import' => 'importazione', 'manuale' => 'creato a mano'][$c['fonte']] ?? $c['fonte']) ?><?= $c['ip'] !== '' ? ', indirizzo IP ' . h($c['ip']) : '' ?></li>
  </ul>
</section>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
