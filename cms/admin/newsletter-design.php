<?php
/**
 * L'editor delle email a blocchi (2.12.0): newsletter-design.php?campagna=N oppure ?modello=N; dalla 2.15.0 anche
 * ?flusso=F&nodo=N, il passo Email di un'automazione (il design sta nel grafo, nl_aut_design_salva()).
 * Pannello a sinistra (Contenuti, Modifica, Stile, Salvati), tela con computer e telefono, annulla e ripeti, salvataggio
 * automatico. Le azioni arrivano in POST da admin/assets/newsletter-design.js e rispondono in JSON; il design viaggia in
 * base64 (campi-protetti.js) e si pulisce sempre con nd_design() (includes/newsletter-design.php). Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-design.php';
require_admin();
nd_schema();

$cid = (int)($_GET['campagna'] ?? 0);
$mid = (int)($_GET['modello'] ?? 0);
$camp = $cid ? newsletter_campagna($cid) : null;
$mod = !$camp && $mid ? nd_modello($mid) : null;
$fid = (int)($_GET['flusso'] ?? 0); $nid = (int)($_GET['nodo'] ?? 0);
$flu = !$camp && !$mod && $fid ? newsletter_flusso($fid) : null;
$fnodo = $flu ? newsletter_flusso_nodo($flu['grafo'], $nid) : null;
if ($fnodo && $fnodo['tipo'] !== 'email') $fnodo = null;
if (!$camp && !$fnodo && (!$mod || $mod['editor'] !== 'blocchi')) { header('Location: ' . ($mid ? 'newsletter-modelli.php' : ($fid ? 'newsletter-flussi.php' : 'newsletter.php'))); exit; }
$modificabile = $mod || $fnodo || newsletter_campagna_modificabile($camp);
// 2.13.0: ?versione=b, il design della versione B di un test A/B sul contenuto (includes/newsletter-ab.php).
$verB = $camp && ($_GET['versione'] ?? '') === 'b' && $camp['tipo'] === 'ab' && function_exists('nl_ab') && nl_ab($cid)['cosa'] === 'design';
if ($verB) $camp = nl_ab_versione($camp, 'b');
$indietro = $camp ? 'newsletter-campagna.php?id=' . $cid . ($verB ? '&versione=b' : '') . '&apri=design#sez-design' : ($fnodo ? 'newsletter-flussi.php?id=' . $fid : 'newsletter-modelli.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!csrf_ok()) { http_response_code(403); exit(json_encode(['errore' => 'Sessione scaduta: ricarica la pagina (le modifiche non salvate restano nella tela finché non la chiudi).'])); }
    $risposta = function (array $r) { echo json_encode($r + ['sezioni' => nd_sezioni()], JSON_UNESCAPED_UNICODE); exit; };
    $leggi = function (string $k): ?array {
        $j = (string)($_POST[$k] ?? '');
        if (strlen($j) > ND_MAX_JSON) return null;
        $a = json_decode($j, true, 16);
        return is_array($a) ? $a : null;
    };
    $azione = (string)($_POST['azione'] ?? '');
    if (in_array($azione, ['salva', 'anteprima', 'modello'], true)) {
        $in = $leggi('design');
        if (!$in) $risposta(['errore' => 'Il design non è leggibile o è troppo grande: ricarica la pagina e riprova.']);
        $d = nd_pulisci($in);
    }
    switch ($azione) {
        case 'salva':
            if (!$modificabile) $risposta(['errore' => 'Questa campagna è già partita: il design non si cambia più.']);
            if ($camp) {
                $c = newsletter_campagna($cid);
                if (!$c || !newsletter_campagna_modificabile($c)) $risposta(['errore' => 'Questa campagna è già partita: il design non si cambia più.']);
                if ($verB) nl_ab_scrivi_b($cid, ['editor' => 'blocchi', 'design' => nd_json($d)]);
                else db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET editor = 'blocchi', design = ?, aggiornato = NOW() WHERE id = ?")->execute([nd_json($d), $cid]);
            } elseif ($fnodo) {
                nl_aut_design_salva($fid, $nid, nd_json($d));
            } else {
                nd_modello_cambia($mid, ['design' => nd_json($d)]);
            }
            $risposta(['ok' => true, 'ora' => date('H:i'), 'design' => $d]);
        case 'anteprima':
            // L'email di conferma (tag optin) non è una campagna: niente piede obbligatorio, il pulsante porta il link di conferma.
            if ($mod && $mod['tag'] === 'optin') $risposta(['html' => newsletter_personalizza(str_replace(['<!--nd-piede-->', '{{ doubleoptin }}'], ['', '#'], nd_html($d, ['oggetto' => (string)$mod['oggetto']])), ['id' => 0, 'nome' => 'Giulia', 'email' => ''], true), 'oggetto' => newsletter_4byte_fuori((string)$mod['oggetto'])]);
            $c = ($camp ?: ($fnodo ? nl_email_campagna($flu, $fnodo['dati']) : ['id' => 0, 'oggetto' => newsletter_4byte_fuori((string)$mod['oggetto']), 'anteprima' => ''])) + ['link_web' => 0, 'link_preferenze' => 0];
            $c['editor'] = 'blocchi'; $c['design'] = nd_json($d); $c['utm'] = 0;
            [$ogg, $html] = newsletter_email($c, ['id' => 0, 'nome' => 'Giulia', 'email' => ''], []);
            $risposta(['html' => $html, 'oggetto' => $ogg]);
        case 'modello':
            $r = nd_modello_crea((string)($_POST['nome'] ?? ''), $d, ['attivo' => !empty($_POST['attivo']), 'oggetto' => $camp ? newsletter_4byte_fuori((string)$camp['oggetto']) : '']);
            $risposta(is_int($r) ? ['ok' => true, 'id' => $r, 'messaggio' => 'Modello salvato' . (!empty($_POST['attivo']) ? ' e attivo: lo trovi nella sezione Design di ogni campagna.' : ': attivalo in Modelli per usarlo nelle campagne.')] : ['errore' => $r]);
        case 'sezione_salva':
            $r = nd_sezione_crea((string)($_POST['nome'] ?? ''), $leggi('sezione'), !empty($_POST['sincronizzata']));
            $risposta(is_int($r) ? ['ok' => true, 'id' => $r] : ['errore' => $r]);
        case 'sezione_aggiorna':
            $r = nd_sezione_aggiorna((int)($_POST['id'] ?? 0), $leggi('sezione'));
            $risposta(is_array($r) ? ['ok' => true, 'messaggio' => 'Sezione aggiornata ovunque: ' . $r[0] . ($r[0] === 1 ? ' campagna in bozza' : ' campagne in bozza') . ' e ' . $r[1] . ($r[1] === 1 ? ' modello' : ' modelli') . '. Le campagne già partite restano come sono.'] : ['errore' => $r]);
        case 'sezione_elimina':
            nd_sezione_elimina((int)($_POST['id'] ?? 0));
            $risposta(['ok' => true]);
    }
    $risposta(['errore' => 'Azione sconosciuta.']);
}

$design = nd_design((string)($camp ? $camp['design'] : ($fnodo ? $fnodo['dati']['design'] : $mod['design']))) ?? nd_design_vuoto();
$nome = (newsletter_4byte_fuori((string)($camp ? ($camp['nome'] ?: $camp['oggetto']) : ($fnodo ? $fnodo['dati']['oggetto'] : $mod['nome']))) ?: 'Email senza nome') . ($verB ? ' (versione B)' : '');
$torna = $camp ? 'Torna al riepilogo della campagna' : ($fnodo ? 'Torna all\'automazione' : 'Torna ai modelli');
$colori = [];
foreach ((function () { require_once dirname(__DIR__) . '/includes/editor.php'; return editor_globali()['colori'] ?? []; })() as $g) if (nd_colore($g['valore'] ?? '')) $colori[] = ['nome' => (string)$g['nome'], 'valore' => nd_colore($g['valore'])];
$dati = [
    'design' => $design, 'stile' => nd_spec_stile(), 'sezione' => nd_spec_sezione(), 'blocchi' => nd_spec_blocchi(),
    'layout' => ND_LAYOUT, 'reti' => ND_RETI, 'font' => ND_FONT, 'icone' => nd_asset('email/'), 'colori' => $colori, 'sezioni' => nd_sezioni(),
    'indirizzo' => newsletter_indirizzo_azienda(), 'campagna' => (bool)$camp, 'modificabile' => $modificabile, 'csrf' => csrf_token(),
];
?>
<!DOCTYPE html>
<html lang="it" data-pannello="<?= h(pannello_aspetto()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editor dell'email: <?= h($nome) ?></title>
<link rel="stylesheet" href="<?= pannello_file('assets/quill/quill.snow.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/css/aspetti.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/css/admin.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/editor/editor.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/css/scegli-link.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/newsletter-design.css') ?>">
<meta name="pn-csrf" content="<?= h(csrf_token()) ?>">
<script src="<?= pannello_file('assets/campi-protetti.js') ?>"></script>
</head>
<body class="ed-app nd-app">
<a class="admin-salta" href="#nd-tela">Vai alla tela dell'email</a>
<header class="ed-barra">
  <a class="ed-indietro" href="<?= h($indietro) ?>" aria-label="<?= h($torna) ?>" title="<?= h($torna) ?>">&larr;</a>
  <div class="ed-nome"><b><?= h($nome) ?></b><small><?= $camp ? 'Campagna: il design dell\'email' : ($fnodo ? 'Automazione «' . h($flu['nome']) . '»: email #' . $nid : 'Modello di email') ?></small></div>
  <div class="ed-misure nd-misure" role="group" aria-label="Larghezza della tela">
    <button type="button" data-nd-misura="pc" class="attivo" aria-pressed="true" title="Come su un computer">&#128421;<span class="sr-solo">Computer</span></button>
    <button type="button" data-nd-misura="tel" aria-pressed="false" title="Come su un telefono (390 px)">&#9647;<span class="sr-solo">Telefono</span></button>
  </div>
  <div class="ed-storia">
    <button type="button" id="nd-annulla" title="Annulla (Ctrl+Z)" aria-label="Annulla l'ultima modifica" disabled>&#8630;</button>
    <button type="button" id="nd-ripeti" title="Ripeti (Ctrl+Y)" aria-label="Ripeti la modifica annullata" disabled>&#8631;</button>
  </div>
  <span class="ed-stato" id="nd-stato" role="status"></span>
  <button type="button" class="ed-b" id="nd-anteprima">Anteprima</button>
  <?php if ($camp): ?><button type="button" class="ed-b nd-largo" id="nd-come-modello">Salva come modello</button><?php endif; ?>
  <button type="button" class="ed-b ed-b-primo" id="nd-salva"<?= $modificabile ? '' : ' disabled' ?>>Salva</button>
</header>
<?php if (!$modificabile): ?><p class="nd-avviso" role="status">Questa campagna è già partita: puoi guardare il design ma non cambiarlo. Per una nuova versione usa «Duplica» nell'elenco delle campagne.</p><?php endif; ?>
<div class="ed-corpo">
  <aside class="ed-pannello" aria-label="Pannello dell'editor">
    <nav class="ed-schede-pannello" role="tablist" aria-label="Pannello dell'editor">
      <button type="button" role="tab" id="nd-scheda-contenuti" aria-controls="nd-p-contenuti" data-nd-scheda="contenuti" class="attivo" aria-selected="true" tabindex="0">Contenuti</button>
      <button type="button" role="tab" id="nd-scheda-modifica" aria-controls="nd-p-modifica" data-nd-scheda="modifica" aria-selected="false" tabindex="-1">Modifica</button>
      <button type="button" role="tab" id="nd-scheda-stile" aria-controls="nd-p-stile" data-nd-scheda="stile" aria-selected="false" tabindex="-1">Stile</button>
      <button type="button" role="tab" id="nd-scheda-salvati" aria-controls="nd-p-salvati" data-nd-scheda="salvati" aria-selected="false" tabindex="-1">Salvati</button>
    </nav>
    <section class="ed-pannello-vista" id="nd-p-contenuti" role="tabpanel" aria-labelledby="nd-scheda-contenuti">
      <p class="ed-aiuto ed-aiuto-sopra">Trascina un blocco o una sezione nella tela, oppure fai clic (o premi Invio): entra dopo l'elemento selezionato.</p>
      <h2 class="nd-titoletto">Blocchi</h2>
      <div class="ed-griglia-widget" id="nd-tavolozza-blocchi"></div>
      <h2 class="nd-titoletto">Sezioni a colonne</h2>
      <div class="nd-griglia-sezioni" id="nd-tavolozza-sezioni"></div>
    </section>
    <section class="ed-pannello-vista" id="nd-p-modifica" role="tabpanel" aria-labelledby="nd-scheda-modifica" hidden>
      <div class="ed-modifica-testa"><b id="nd-modifica-nome">Niente selezionato</b></div>
      <div id="nd-controlli"><p class="ed-aiuto">Fai clic su un blocco o su una sezione della tela per cambiarli.</p></div>
    </section>
    <section class="ed-pannello-vista" id="nd-p-stile" role="tabpanel" aria-labelledby="nd-scheda-stile" hidden>
      <p class="ed-aiuto ed-aiuto-sopra">Lo stile vale per tutta l'email: ogni blocco lo segue, se non ha un colore suo.</p>
      <div id="nd-controlli-stile"></div>
    </section>
    <section class="ed-pannello-vista" id="nd-p-salvati" role="tabpanel" aria-labelledby="nd-scheda-salvati" hidden>
      <p class="ed-aiuto ed-aiuto-sopra">Le sezioni salvate si riusano in ogni email. Quelle sincronizzate, se le cambi e premi «Aggiorna in tutte le email», cambiano in ogni campagna in bozza e in ogni modello che le usa.</p>
      <div id="nd-elenco-salvati"></div>
    </section>
  </aside>
  <main class="ed-tela-cornice nd-cornice" id="nd-cornice">
    <div class="nd-fondo" id="nd-fondo"><div class="nd-tela" id="nd-tela" tabindex="-1" aria-label="L'email"></div></div>
  </main>
</div>

<dialog id="nd-dlg-anteprima" class="ed-dialogo ed-dialogo-largo" aria-labelledby="nd-anteprima-titolo">
  <h2 id="nd-anteprima-titolo">Anteprima dell'email</h2>
  <p class="ed-aiuto">Così la riceve chi legge (con il nome «Giulia» al posto dei dati veri). <span id="nd-anteprima-oggetto"></span></p>
  <div class="ed-misure nd-misure-anteprima" role="group" aria-label="Larghezza dell'anteprima">
    <button type="button" data-nd-ant="pc" class="attivo" aria-pressed="true">Computer</button><button type="button" data-nd-ant="tel" aria-pressed="false">Telefono</button>
  </div>
  <div class="nd-ant-cornice"><iframe id="nd-anteprima-iframe" title="Anteprima dell'email" sandbox></iframe></div>
  <div class="ed-dialogo-azioni"><button type="button" class="ed-b" data-chiudi-dialogo="#nd-dlg-anteprima">Chiudi</button></div>
</dialog>
<dialog id="nd-dlg-nome" class="ed-dialogo" aria-labelledby="nd-nome-titolo">
  <form method="dialog" id="nd-form-nome">
    <h2 id="nd-nome-titolo">Salva</h2>
    <label>Nome<input name="nome" id="nd-nome-campo" required maxlength="150"></label>
    <label class="ed-spunta"><input type="checkbox" name="spunta" id="nd-nome-spunta" checked> <span id="nd-nome-spunta-testo"></span></label>
    <p class="ed-aiuto" id="nd-nome-aiuto"></p>
    <p class="ed-errore" id="nd-nome-errore" role="alert" hidden></p>
    <div class="ed-dialogo-azioni"><button type="button" class="ed-b" data-chiudi-dialogo="#nd-dlg-nome">Annulla</button><button type="submit" class="ed-b ed-b-primo" id="nd-nome-ok">Salva</button></div>
  </form>
</dialog>

<script id="nd-dati" type="application/json"><?= str_replace('</', '<\/', json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></script>
<script src="<?= pannello_file('assets/pannello.js') ?>"></script>
<script src="<?= pannello_file('assets/quill/quill.js') ?>"></script>
<script src="<?= pannello_file('assets/editor/Sortable.min.js') ?>"></script>
<script src="<?= pannello_file('assets/scegli-link.js') ?>"></script>
<script src="<?= pannello_file('assets/newsletter-design.js') ?>"></script>
</body>
</html>
