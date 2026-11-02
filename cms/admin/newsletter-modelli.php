<?php
/**
 * Newsletter e form > Modelli (2.12.0): i tuoi modelli di email (attivo o inattivo, rinomina, modifica nell'editor a blocchi,
 * duplica, elimina), la galleria dei modelli pronti (includes/newsletter-modelli/*.json) e l'email di conferma
 * dell'iscrizione: quella predefinita (le parti di Newsletter > Impostazioni, 2.7.0) o un modello con il tag optin.
 * 2.18.0: "Usato in" (i passi Email delle automazioni che richiamano il modello, nl_modelli_usati()), conferme che li elencano
 * prima di eliminare o rendere inattivo, testo di anteprima, modelli in codice HTML (crea e modifica, ?codice=N).
 * Motore in includes/newsletter-design.php. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-design.php';
require_admin();
nd_schema();

$scheda = ($_GET['scheda'] ?? '') === 'galleria' ? 'galleria' : 'miei';
$campagna = (int)($_GET['campagna'] ?? $_POST['campagna'] ?? 0);
$camp = $campagna ? newsletter_campagna($campagna) : null;
if ($camp && !newsletter_campagna_modificabile($camp)) $camp = null;
// 2.13.0: la galleria per la versione B di un test A/B sul contenuto (?versione=b).
$verB = $camp && ($_GET['versione'] ?? $_POST['versione'] ?? '') === 'b' && $camp['tipo'] === 'ab' && function_exists('nl_ab') && nl_ab($campagna)['cosa'] === 'design';
$qvB = $verB ? '&versione=b' : '';
// 2.18.0: dove i modelli sono richiamati dalle automazioni (newsletter-flussi.php: con opcache il file può essere ancora quello di prima).
$usati = function_exists('nl_modelli_usati') ? nl_modelli_usati() : [];
/** "automazione «A» (email #3, #7), automazione «B» (email #2)": i passi che richiamano il modello, in parole. */
$doveUsato = function (int $id) use ($usati): string {
    $per = [];
    foreach ($usati[$id] ?? [] as $x) $per[$x['flusso']] = ['nome' => $x['nome'], 'nodi' => array_merge($per[$x['flusso']]['nodi'] ?? [], ['#' . $x['nodo']])];
    return implode(', ', array_map(fn($x) => 'automazione «' . $x['nome'] . '» (email ' . implode(', ', $x['nodi']) . ')', $per));
};
$flussoTorna = (int)($_GET['flusso'] ?? $_POST['flusso'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $m = $id ? nd_modello($id) : null;
    $vai = 'newsletter-modelli.php';
    $nomeM = $m ? newsletter_4byte_fuori((string)$m['nome']) : '';
    switch ($azione) {
        case 'nuovo':
            // 2.18.0: anche un modello in codice HTML; parte dall'email dell'editor semplice, con il link per uscire.
            $html = ($_POST['tipo'] ?? '') === 'html';
            $r = nd_modello_crea((string)($_POST['nome'] ?? ''), null, $html ? ['editor' => 'html', 'html' => nl_camp_html_partenza(['oggetto' => '', 'anteprima' => '', 'corpo' => '<p>Ciao {nome},</p><p>scrivi qui il testo.</p>', 'pulsante_testo' => '', 'pulsante_link' => '', 'mittente_id' => 0])] : []);
            if (is_int($r)) { header('Location: ' . ($html ? 'newsletter-modelli.php?codice=' : 'newsletter-design.php?modello=') . $r); exit; }
            flash('error', $r);
            break;
        case 'codice':
            // 2.18.0: oggetto, anteprima e codice di un modello in codice HTML (come il Codice HTML delle campagne: gli script si
            // tolgono quando l'email si compone, nl_camp_html()).
            if (!$m || $m['editor'] !== 'html') { flash('error', 'Modello sconosciuto.'); break; }
            $hh = (string)($_POST['html'] ?? '');
            if (strlen($hh) > 1500000) { flash('error', 'Il codice HTML è troppo lungo (più di 1,5 MB): togli le immagini incorporate e usa i loro indirizzi.'); $vai = 'newsletter-modelli.php?codice=' . $id; break; }
            if (trim($hh) === '') { flash('error', 'Il codice HTML è vuoto: incolla l\'email, oppure elimina il modello.'); $vai = 'newsletter-modelli.php?codice=' . $id; break; }
            nd_modello_cambia($id, ['html' => newsletter_4byte_dentro($hh), 'oggetto' => nd_nome($_POST['oggetto'] ?? ''), 'anteprima' => nd_anteprima($_POST['anteprima'] ?? '')]);
            flash('success', "Modello «$nomeM» salvato" . (($usati[$id] ?? []) ? ': le automazioni che lo richiamano mandano da adesso questa versione.' : '.'));
            $vai = 'newsletter-modelli.php?codice=' . $id . ($flussoTorna ? '&flusso=' . $flussoTorna : '');
            break;
        case 'attiva':
            if ($m) { nd_modello_cambia($id, ['attivo' => empty($_POST['si']) ? 0 : 1]); flash('success', empty($_POST['si']) ? "«$nomeM» non è più attivo: non compare più fra i modelli delle campagne." . (($usati[$id] ?? []) ? ' Le automazioni che lo richiamano continuano a mandarlo.' : '') : "«$nomeM» è attivo: lo scegli nella sezione Design di ogni campagna."); }
            break;
        case 'rinomina':
            $n = nd_nome($_POST['nome'] ?? '');
            if ($m && $n !== '') { nd_modello_cambia($id, ['nome' => $n]); flash('success', 'Nome del modello salvato.'); } else flash('error', 'Scrivi il nome del modello.');
            break;
        case 'oggetto':
            if (!$m) break;
            // 2.18.0: un modello richiamato dalle automazioni non diventa l'email di conferma (quei passi si fermerebbero).
            if (!empty($_POST['optin']) && $m['tag'] !== 'optin' && ($usati[$id] ?? [])) { flash('error', "«$nomeM» è usato da " . $doveUsato($id) . ': non può diventare l\'email di conferma. Duplicalo e spunta la copia.'); break; }
            nd_modello_cambia($id, ['oggetto' => nd_nome($_POST['oggetto'] ?? ''), 'anteprima' => nd_anteprima($_POST['anteprima'] ?? ''), 'tag' => !empty($_POST['optin']) ? 'optin' : '']);
            flash('success', 'Modello salvato.');
            break;
        case 'duplica':
            $n = $m ? nd_modello_duplica($id) : 0;
            $n ? flash('success', "Copia di «$nomeM» creata: nasce inattiva.") : flash('error', 'Modello sconosciuto.');
            break;
        case 'elimina':
            $u = $doveUsato($id);
            $m && nd_modello_elimina($id) ? flash($u !== '' ? 'error' : 'success', "Modello «$nomeM» eliminato. Le campagne che lo hanno usato tengono il loro design." . ($u !== '' ? " Attenzione: lo richiamava $u. Quelle email non partono e le persone si fermano lì finché non scegli un altro modello nel passo." : '')) : flash('error', 'Modello sconosciuto.');
            break;
        case 'galleria':
            $slug = (string)($_POST['slug'] ?? '');
            if (!empty($_POST['in_campagna']) && !$camp) { flash('error', 'Questa campagna non si può più modificare: è già partita.'); break; }
            if ($camp && !empty($_POST['in_campagna'])) {
                $g = nd_galleria()[$slug] ?? null;
                $e = $g ? ($verB ? nl_ab_da_modello($campagna, null, $g['design']) : nd_campagna_da_modello($campagna, null, $g['design'])) : 'Modello della galleria sconosciuto.';
                if ($e === '') { header('Location: newsletter-design.php?campagna=' . $campagna . $qvB); exit; }
                flash('error', $e);
                $vai .= '?scheda=galleria&campagna=' . $campagna . $qvB;
                break;
            }
            $n = nd_galleria_copia($slug);
            $n ? flash('success', 'Il modello è fra i tuoi modelli, già attivo: cambialo come vuoi con «Modifica».') : flash('error', 'Modello della galleria sconosciuto.');
            break;
        case 'doi':
            $scelto = (int)($_POST['doi'] ?? 0);
            $ok = $scelto === 0 || in_array($scelto, array_map(fn($x) => (int)$x['id'], nd_modelli_optin()), true);
            if ($ok) { save_setting('newsletter_doi_modello', (string)$scelto); flash('success', $scelto ? 'Da adesso l\'email di conferma dell\'iscrizione è quella del modello scelto (tranne nei form che hanno la loro).' : 'Da adesso l\'email di conferma è quella predefinita, con le parti scritte in Impostazioni.'); }
            else flash('error', 'Scegli un modello attivo con «Email di conferma dell\'iscrizione» spuntato.');
            break;
    }
    header('Location: ' . $vai);
    exit;
}

$modelli = nd_modelli();
$galleria = nd_galleria();
// 2.18.0: la pagina del codice di un modello in codice HTML.
$codice = isset($_GET['codice']) ? nd_modello((int)$_GET['codice']) : null;
if ($codice && $codice['editor'] !== 'html') { header('Location: newsletter-design.php?modello=' . (int)$codice['id']); exit; }
if (isset($_GET['codice']) && !$codice) { flash('error', 'Questo modello non c\'è più.'); header('Location: newsletter-modelli.php'); exit; }
$optin = nd_modelli_optin();
$doi = (int)get_setting('newsletter_doi_modello', '0');
/** L'anteprima di un design, con il nome finto dei segnaposti. */
$vista = function (array $d, bool $optin = false): string {
    // L'email di conferma non è una campagna: niente piede obbligatorio.
    if ($optin) return newsletter_personalizza(str_replace(['<!--nd-piede-->', '{{ doubleoptin }}'], ['', '#'], nd_html($d)), ['id' => 0, 'nome' => 'Giulia', 'email' => ''], true);
    $c = ['id' => 0, 'oggetto' => '', 'anteprima' => '', 'editor' => 'blocchi', 'design' => nd_json($d), 'link_web' => 0, 'link_preferenze' => 0];
    return newsletter_email($c, ['id' => 0, 'nome' => 'Giulia', 'email' => ''], [])[1];
};
$adminTitle = $codice ? 'Modello: ' . newsletter_4byte_fuori((string)$codice['nome']) : 'Modelli di email';
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.ndm-griglia{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,250px),1fr));gap:1rem}
.ndm-carta{border:1px solid var(--bordo-campo);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;background:var(--surface)}
.ndm-mini{height:300px;overflow:hidden;background:#f4f4f6 /* fisso: il fondo delle email */;position:relative}
.ndm-mini iframe{width:640px;height:670px;border:0;transform:scale(.45);transform-origin:0 0;pointer-events:none;position:absolute;left:50%;top:0;margin-left:-144px}
.ndm-carta-dentro{padding:.8rem .9rem 1rem;display:flex;flex-direction:column;gap:.4rem;flex:1}
.ndm-carta-dentro p{margin:0;font-size:.88rem;color:var(--text-muted)}
.ndm-azioni{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:auto}
.ndm-azioni form{margin:0}
.ndm-stato{display:inline-flex;gap:.3rem;align-items:center;font-size:.78rem;font-weight:700;padding:.1rem .55rem;border-radius:999px;border:1px solid currentColor}
.ndm-attivo{color:var(--successo-testo);background:var(--successo-chiaro)}
.ndm-spento{color:var(--text-muted);background:var(--fondo-2)}
.ndm-tabella td{vertical-align:top}
.ndm-tabella details form{margin-top:.4rem}
.ndm-usato ul{margin:.2rem 0 0;padding-left:1rem}
.ndm-codice{min-height:22rem;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.82rem}
</style>
<?php if ($codice): $ci = (int)$codice['id']; $cu = $doveUsato($ci); ?>
<p><a href="<?= $flussoTorna ? 'newsletter-flussi.php?id=' . $flussoTorna : 'newsletter-modelli.php' ?>">&larr; <?= $flussoTorna ? 'Torna all\'automazione' : 'Torna ai modelli' ?></a></p>
<form method="post" class="form-card" id="ndm-codice"><?= csrf_field() ?><input type="hidden" name="azione" value="codice"><input type="hidden" name="id" value="<?= $ci ?>"><input type="hidden" name="flusso" value="<?= $flussoTorna ?>">
  <h3>Modello in codice HTML: <?= h(newsletter_4byte_fuori((string)$codice['nome'])) ?></h3>
  <?php if ($cu !== ''): ?><p class="hint" role="status">Usato in: <?= h($cu) ?>. Quando salvi, quelle email partono da subito con questa versione.</p><?php endif; ?>
  <div class="form-group"><label for="ndm-c-ogg">Oggetto</label><input type="text" id="ndm-c-ogg" name="oggetto" maxlength="200" value="<?= h(newsletter_4byte_fuori((string)$codice['oggetto'])) ?>" placeholder="per esempio: Il capitolo 5, {nome}" aria-describedby="ndm-c-ogg-aiuto"><div class="hint" id="ndm-c-ogg-aiuto">Vale nelle automazioni che lo richiamano quando il passo non ha un oggetto suo.</div></div>
  <div class="form-group"><label for="ndm-c-ant">Testo di anteprima</label><input type="text" id="ndm-c-ant" name="anteprima" maxlength="255" value="<?= h(newsletter_4byte_fuori((string)($codice['anteprima'] ?? ''))) ?>" placeholder="per esempio: Cinque minuti di lettura" aria-describedby="ndm-c-ant-aiuto"><div class="hint" id="ndm-c-ant-aiuto">Quello che la posta mostra accanto all'oggetto. Vuoto: nessuno.</div></div>
  <div class="form-group"><label for="ndm-c-html">Il codice HTML dell'email</label>
    <textarea id="ndm-c-html" name="html" class="ndm-codice" spellcheck="false" required aria-describedby="ndm-c-html-aiuto"><?= h(newsletter_4byte_fuori((string)$codice['html'])) ?></textarea>
    <div class="hint" id="ndm-c-html-aiuto">Metti <code>{{ unsubscribe }}</code> dove vuoi il link per uscire (se manca, lo aggiungiamo noi in fondo). Valgono <code>{nome}</code> e i segnaposti come <code>{{ contact.NOME }}</code>. Gli script si tolgono: nessun programma di posta li esegue.</div></div>
  <button class="btn btn-primary" type="submit">Salva il modello</button>
</form>
<?php require_once __DIR__ . '/includes/admin-footer.php'; exit; endif; ?>
<nav class="imp-schede" aria-label="Modelli">
  <a href="newsletter-modelli.php<?= $camp ? '?campagna=' . $campagna : '' ?>"<?= $scheda === 'miei' ? ' class="attiva" aria-current="page"' : '' ?>>I tuoi modelli</a>
  <a href="newsletter-modelli.php?scheda=galleria<?= $camp ? '&amp;campagna=' . $campagna : '' ?>"<?= $scheda === 'galleria' ? ' class="attiva" aria-current="page"' : '' ?>>Galleria</a>
</nav>
<?php if ($camp): ?><p class="nlr-stato-box" role="status" style="padding:.7rem 1rem;border-radius:8px;background:var(--info-chiaro);color:var(--info-testo);border:1px solid var(--info-bordo)">Stai scegliendo il design per la campagna «<a href="newsletter-campagna.php?id=<?= $campagna ?><?= $verB ? '&amp;versione=b' : '' ?>&amp;apri=design#sez-design"><?= h(newsletter_4byte_fuori((string)($camp['nome'] ?: $camp['oggetto']))) ?></a>»<?= $verB ? ', versione B' : '' ?>.</p><?php endif; ?>

<?php if ($scheda === 'galleria'): ?>
<div class="form-card">
  <h3>Galleria dei modelli pronti</h3>
  <p class="hint" style="margin:0 0 .9rem">Modelli fatti per le occasioni più comuni. <?= $camp ? '«Usa in questa campagna» porta il design nella campagna e apre l\'editor; «Aggiungi ai miei modelli» lo copia fra i tuoi modelli.' : '«Aggiungi ai miei modelli» lo copia fra i tuoi modelli, già attivo: poi lo cambi come vuoi.' ?> I testi e i colori sono di esempio: cambiali con quelli tuoi.</p>
  <div class="ndm-griglia">
    <?php foreach ($galleria as $slug => $g): ?>
    <div class="ndm-carta">
      <div class="ndm-mini"><iframe title="Anteprima: <?= h($g['nome']) ?>" sandbox loading="lazy" tabindex="-1" srcdoc="<?= h($vista($g['design'], $g['tag'] === 'optin')) ?>"></iframe></div>
      <div class="ndm-carta-dentro"><b><?= h($g['nome']) ?></b><p><?= h($g['descrizione']) ?></p>
        <?php if ($g['tag'] === 'optin'): ?><p>Per l'email di conferma dell'iscrizione: il pulsante porta il link di conferma.</p><?php endif; ?>
        <div class="ndm-azioni">
          <?php if ($camp && $g['tag'] !== 'optin'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="galleria"><input type="hidden" name="slug" value="<?= h($slug) ?>"><input type="hidden" name="campagna" value="<?= $campagna ?>"><input type="hidden" name="in_campagna" value="1"><?= $verB ? '<input type="hidden" name="versione" value="b">' : '' ?><button class="btn btn-primary btn-sm" type="submit">Usa in questa campagna</button></form><?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="galleria"><input type="hidden" name="slug" value="<?= h($slug) ?>"><button class="btn <?= $camp ? 'btn-secondary' : 'btn-primary' ?> btn-sm" type="submit">Aggiungi ai miei modelli</button></form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="form-card">
  <h3>I tuoi modelli</h3>
  <p class="hint" style="margin:0 0 .9rem">Un modello è un design da riusare: nelle campagne porta solo il design (mittente, destinatari e oggetto restano della campagna). Nasce inattivo: lo scegli nelle campagne quando è attivo. Nelle automazioni un'email può richiamarlo («Da un modello»): lo legge ogni volta che parte, e «Usato in» dice dove.</p>
  <form method="post" class="nl-riga" style="gap:.5rem;align-items:flex-end;margin-bottom:1rem"><?= csrf_field() ?><input type="hidden" name="azione" value="nuovo">
    <div class="form-group" style="margin:0;flex:1;min-width:min(100%,16rem)"><label for="ndm-nuovo">Nome del modello nuovo</label><input type="text" id="ndm-nuovo" name="nome" maxlength="150" required placeholder="per esempio: Newsletter del mese"></div>
    <div class="form-group" style="margin:0;min-width:min(100%,12rem)"><label for="ndm-tipo">Come lo scrivi</label><select id="ndm-tipo" name="tipo"><option value="blocchi">Con l'editor a blocchi</option><option value="html">Incollo il codice HTML</option></select></div>
    <button class="btn btn-primary" type="submit">Crea un modello</button>
  </form>
  <?php if (!$modelli): ?>
  <div class="empty-state"><p>Ancora nessun modello: creane uno qui sopra, prendine uno dalla <a href="newsletter-modelli.php?scheda=galleria">Galleria</a>, oppure salva il design di una campagna con «Salva come modello» nell'editor a blocchi.</p></div>
  <?php else: ?>
  <div class="ad-tabella"><table class="table ndm-tabella">
    <thead><tr><th scope="col">Nome</th><th scope="col">Stato</th><th scope="col">Tipo</th><th scope="col">Usato in</th><th scope="col">Aggiornato</th><th scope="col">Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($modelli as $m): $mn = newsletter_4byte_fuori((string)$m['nome']); $mi = (int)$m['id']; $mu = $doveUsato($mi); ?>
    <tr>
      <td data-etichetta="Nome"><b><?= h($mn) ?></b><?= $m['tag'] === 'optin' ? '<br><small>Email di conferma dell\'iscrizione</small>' : '' ?>
        <details><summary>Rinomina</summary><form method="post" class="nl-riga" style="gap:.4rem"><?= csrf_field() ?><input type="hidden" name="azione" value="rinomina"><input type="hidden" name="id" value="<?= $mi ?>">
          <label class="sr-solo" for="ndm-nome-<?= $mi ?>">Nome del modello</label><input type="text" id="ndm-nome-<?= $mi ?>" name="nome" maxlength="150" value="<?= h($mn) ?>" required><button class="btn btn-secondary btn-sm" type="submit">Salva il nome</button></form></details>
        <details><summary>Oggetto e uso</summary><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="oggetto"><input type="hidden" name="id" value="<?= $mi ?>">
          <div class="form-group"><label for="ndm-ogg-<?= $mi ?>">Oggetto</label><input type="text" id="ndm-ogg-<?= $mi ?>" name="oggetto" maxlength="200" value="<?= h(newsletter_4byte_fuori((string)$m['oggetto'])) ?>" placeholder="per esempio: Conferma l'iscrizione" aria-describedby="ndm-ogg-aiuto-<?= $mi ?>"><div class="hint" id="ndm-ogg-aiuto-<?= $mi ?>">Vale per l'email di conferma e per le automazioni che richiamano il modello senza un oggetto loro.</div></div>
          <div class="form-group"><label for="ndm-ant-<?= $mi ?>">Testo di anteprima</label><input type="text" id="ndm-ant-<?= $mi ?>" name="anteprima" maxlength="255" value="<?= h(newsletter_4byte_fuori((string)($m['anteprima'] ?? ''))) ?>" placeholder="per esempio: Cinque minuti di lettura"></div>
          <label><input type="checkbox" name="optin" value="1"<?= $m['tag'] === 'optin' ? ' checked' : '' ?>> Email di conferma dell'iscrizione (il pulsante con il link di conferma)</label>
          <div><button class="btn btn-secondary btn-sm" type="submit">Salva</button></div></form></details></td>
      <td data-etichetta="Stato"><span class="ndm-stato <?= (int)$m['attivo'] ? 'ndm-attivo' : 'ndm-spento' ?>"><span aria-hidden="true"><?= (int)$m['attivo'] ? '&#10003;' : '&#9675;' ?></span> <?= (int)$m['attivo'] ? 'Attivo' : 'Inattivo' ?></span></td>
      <td data-etichetta="Tipo"><?= $m['editor'] === 'html' ? 'Codice HTML' : 'Editor a blocchi' ?></td>
      <td data-etichetta="Usato in" class="ndm-usato"><?php if (!($usati[$mi] ?? [])): ?><span class="hint">Nessuna automazione</span><?php else: $_per = []; foreach ($usati[$mi] as $_x) $_per[$_x['flusso']][] = $_x; ?><ul><?php foreach ($_per as $_fid => $_xx): ?><li><a href="newsletter-flussi.php?id=<?= (int)$_fid ?>"><?= h($_xx[0]['nome']) ?></a>, email <?= h(implode(', ', array_map(fn($x) => '#' . $x['nodo'], $_xx))) ?><?= $_xx[0]['stato'] === 'inattiva' ? ' <small>(inattiva)</small>' : '' ?></li><?php endforeach; ?></ul><?php endif; ?></td>
      <td data-etichetta="Aggiornato"><?= h(date('d/m/Y H:i', strtotime((string)$m['aggiornato']))) ?></td>
      <td data-etichetta="Azioni"><div class="ndm-azioni">
        <?php if ($m['editor'] === 'blocchi'): ?><a class="btn btn-secondary btn-sm" href="newsletter-design.php?modello=<?= $mi ?>">Modifica</a><?php else: ?><a class="btn btn-secondary btn-sm" href="newsletter-modelli.php?codice=<?= $mi ?>">Modifica il codice</a><?php endif; ?>
        <form method="post"<?= (int)$m['attivo'] && $mu !== '' ? ' data-conferma="' . h("Rendere inattivo «{$mn}»? Lo richiama $mu: quelle email continuano a partire con questo modello. Inattivo vuol dire solo che non compare più fra le scelte nuove.") . '" data-conferma-ok="Rendi inattivo"' : '' ?>><?= csrf_field() ?><input type="hidden" name="azione" value="attiva"><input type="hidden" name="id" value="<?= $mi ?>"><input type="hidden" name="si" value="<?= (int)$m['attivo'] ? '' : '1' ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= (int)$m['attivo'] ? 'Rendi inattivo' : 'Rendi attivo' ?></button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="duplica"><input type="hidden" name="id" value="<?= $mi ?>"><button class="btn btn-secondary btn-sm" type="submit">Duplica</button></form>
        <form method="post" data-conferma="<?= h("Eliminare il modello «{$mn}»? Le campagne che lo hanno usato tengono il loro design." . ($mu !== '' ? " Attenzione: lo richiama $mu. Quelle email non partiranno più e le persone si fermeranno lì finché non scegli un altro modello nel passo." : '')) ?>" data-conferma-ok="Elimina il modello"<?= $mu !== '' ? ' data-conferma-pericolo' : '' ?>><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $mi ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="form-card" id="ndm-conferma">
  <h3>Email di conferma dell'iscrizione</h3>
  <p class="hint" style="margin:0 0 .9rem">L'email che parte quando qualcuno si iscrive e deve confermare l'indirizzo (doppio consenso). La predefinita si scrive a parti in <a href="newsletter.php?scheda=impostazioni">Impostazioni</a>, riquadro «Email di conferma». Puoi usare invece un tuo modello attivo con «Email di conferma dell'iscrizione» spuntato (in «Oggetto e uso»): nella galleria c'è «Conferma dell'iscrizione». I form con una loro email di conferma tengono la loro.</p>
  <form method="post" class="nl-riga" style="gap:.5rem;align-items:flex-end"><?= csrf_field() ?><input type="hidden" name="azione" value="doi">
    <div class="form-group" style="margin:0;flex:1;min-width:min(100%,16rem)"><label for="ndm-doi">Email di conferma</label>
      <select id="ndm-doi" name="doi"><option value="0">Predefinita (le parti di Impostazioni)</option><?php foreach ($optin as $m): ?><option value="<?= (int)$m['id'] ?>"<?= $doi === (int)$m['id'] ? ' selected' : '' ?>><?= h(newsletter_4byte_fuori((string)$m['nome'])) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Usa questa email di conferma</button>
  </form>
  <?php if (!$optin): ?><p class="hint">Nessun modello di conferma attivo: per ora vale la predefinita.</p><?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
