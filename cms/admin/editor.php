<?php
/**
 * L'editor visuale: pannello a sinistra (widget, impostazioni, navigatore),
 * la pagina vera al centro dentro un iframe (editor-anteprima.php), trascinamento, tre misure di schermo,
 * annulla e ripeti, bozza e pubblicazione, revisioni.
 *
 *   editor.php?id=N            apre una pagina
 *   editor.php?nuova=1         crea una pagina in bozza e la apre
 *   editor.php?id=N&parti=...  prima volta: home (la grafica di oggi), html (il contenuto di oggi), vuota
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/sito/viste.php';
require_once dirname(__DIR__) . '/includes/moduli.php';
require_once dirname(__DIR__) . '/includes/editor.php';
require_once dirname(__DIR__) . '/includes/editor-temi.php';
require_once dirname(__DIR__) . '/includes/versioni.php'; // versioni
require_once dirname(__DIR__) . '/includes/sezioni-pronte.php'; // 1.3.0: il pannello Sezioni
if (is_file(dirname(__DIR__) . '/includes/seo.php')) require_once dirname(__DIR__) . '/includes/seo.php';
editor_schema();
require_once dirname(__DIR__) . '/includes/contenuti.php';

// "+ Aggiungi" dagli elenchi e dalla dashboard: il contenuto nasce in bozza e si apre subito nell'editor visuale.
if (isset($_GET['nuova'])) {
    require_once dirname(__DIR__) . '/includes/contenuti.php';
    $tipo = editor_tipo_valido((string)($_GET['tipo'] ?? ''));
    if ($tipo === 'modelli') $tipo = 'pagine';
    $def = contenuti_tipi()[$tipo];
    $nome = (string)($def['nuovo'] ?? (((($def['genere'] ?? (str_ends_with($def['uno'], 'a') ? 'f' : 'm')) === 'f') ? 'Nuova ' : 'Nuovo ') . $def['uno']));
    $base = contenuti_slug($nome); $n = 1;
    do { $slug = $base . ($n > 1 ? "-$n" : ''); $n++; } while (!contenuti_slug_libero($tipo, $slug, 0));
    $id = contenuti_salva($tipo, 0, ['title' => $nome, 'slug' => $slug, 'stato' => 'bozza', 'contenuto' => ''], $_SESSION['username'] ?? '');
    $albero = !editor_tipo_lettura($tipo)
        ? editor_valida(['elementi' => [editor_sezione([editor_widget('titolo', ['testo' => $nome, 'livello' => 'h1']), editor_widget('testo')])]])
        : editor_valida(['elementi' => [editor_sezione([editor_widget('testo', ['margine_sotto' => ['d' => 0]])], ['larghezza' => 'articolo', 'spazio_sopra' => ['d' => 0], 'spazio_sotto' => ['d' => 0]])]]);
    editor_salva($id, $albero, false, $_SESSION['username'] ?? '', 'creato dall\'editor', $tipo);
    header('Location: editor.php?tipo=' . $tipo . '&id=' . $id);
    exit;
}

$tipo = editor_tipo_valido($_GET['tipo'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$pagina = $id ? editor_pagina($id, $tipo) : null;
if (!$pagina) {
    http_response_code(404);
    exit('Contenuto non trovato. <a href="' . ($tipo === 'modelli' ? 'modelli.php' : 'contenuti.php?tipo=' . $tipo) . '">Torna all\'elenco</a>');
}
// modelli: header e footer del Theme Builder si aprono con lo stesso editor; la scheda di ritorno è l'elenco dei modelli.
$modello = $tipo === 'modelli';
$elenco = $modello ? 'modelli.php' : 'contenuti.php?tipo=' . $tipo;
$scheda = $modello ? 'modelli.php' : 'contenuto.php?tipo=' . $tipo . '&id=' . $id;
// Le tendine Header e Footer del contenuto (includes/modelli.php), se il Theme Builder c'è.
$opzioniModelli = null;
if (!$modello && is_file(dirname(__DIR__) . '/includes/modelli.php')) {
    require_once dirname(__DIR__) . '/includes/modelli.php';
    if (function_exists('modelli_opzioni_scelta')) { try { $opzioniModelli = ['header' => modelli_opzioni_scelta('header'), 'footer' => modelli_opzioni_scelta('footer')]; } catch (Throwable $e) { $opzioniModelli = null; } }
}
$percorso = $modello ? '/?preview=1&' . h($pagina['tipo']) . '=' . $id : editor_percorso_pagina($pagina); // modelli: l'anteprima sul sito forza questo modello
$home = !$modello && editor_e_home($pagina);

// Prima apertura: da dove partire. Nessuna pagina cambia da sola.
if (isset($_GET['parti']) && trim((string)$pagina['struttura']) === '' && trim((string)$pagina['struttura_bozza']) === '') {
    $albero = match ($_GET['parti']) {
        'home' => $home ? editor_albero_home() : null,
        'html' => $tipo === 'modelli' ? null : ($tipo !== 'pagine' ? editor_albero_da_articolo($pagina) : editor_albero_da_html($pagina['title'], (string)$pagina['html'], $percorso, (string)$pagina['tldr'])),
        default => $tipo !== 'pagine' ? editor_valida(['elementi' => [editor_sezione([editor_widget('testo')], ['larghezza' => 'stretta'])]])
            : editor_valida(['elementi' => [editor_sezione([editor_widget('titolo', ['testo' => $pagina['title'], 'livello' => 'h1'])])]]),
    };
    if ($albero) editor_salva($id, $albero, false, '', '', $tipo);
    header('Location: editor.php?tipo=' . $tipo . '&id=' . $id);
    exit;
}

$bozza = editor_decodifica($pagina['struttura_bozza']);
$pubblica = editor_decodifica($pagina['struttura']);
$albero = $bozza ?? $pubblica;
$adminTitle = 'Editor: ' . $pagina['title'];

if ($albero === null): ?>
<!DOCTYPE html>
<html lang="it" data-pannello="<?= h(pannello_aspetto()) ?>"><head><meta charset="utf-8"><title>Editor: <?= h($pagina['title']) ?></title>
<link rel="stylesheet" href="<?= pannello_file('assets/css/aspetti.css') ?>"><link rel="stylesheet" href="<?= pannello_file('assets/css/admin.css') ?>"><link rel="stylesheet" href="<?= pannello_file('assets/editor/editor.css') ?>"><script src="<?= pannello_file('assets/campi-protetti.js') ?>"></script></head>
<body class="ed-scelta-pagina">
<div class="ed-scelta">
  <p class="ed-scelta-sopra"><a href="<?= h($scheda) ?>">← Torna alla scheda</a></p>
  <h1><?= h($pagina['title']) ?> <small><?= h($percorso) ?></small></h1>
  <p><?= $tipo !== 'pagine' ? "Questo contenuto è ancora quello scritto nella scheda. Con l'editor visuale lo componi a sezioni e widget: titolo, data e sommario restano quelli dell'articolo." : 'Questa pagina è ancora quella di prima.' ?> Scegli da dove partire: fino a quando non premi <b>Pubblica</b>, chi visita il sito continua a vedere la versione di oggi.</p>
  <div class="ed-scelta-carte">
    <?php if ($home): ?>
    <a class="ed-carta" href="editor.php?id=<?= $id ?>&amp;parti=home"><b>La grafica di oggi</b><span>La home com'è adesso, già divisa in sezioni e widget: apertura, tre passi, frase, schede, chi sono, ultimi pillar. Cambi quello che vuoi.</span></a>
    <?php endif; ?>
    <?php if (trim((string)($tipo !== 'pagine' ? $pagina['content'] : $pagina['html'])) !== ''): ?>
    <a class="ed-carta" href="editor.php?tipo=<?= $tipo ?>&amp;id=<?= $id ?>&amp;parti=html"><b>Il contenuto di oggi</b><span>Il testo entra intero in un widget HTML<?= $tipo === 'pagine' ? ', con i moduli al loro posto' : '' ?>. Da lì lo rifai un pezzo alla volta.</span></a>
    <?php endif; ?>
    <a class="ed-carta" href="editor.php?tipo=<?= $tipo ?>&amp;id=<?= $id ?>&amp;parti=vuota"><b><?= $tipo !== 'pagine' ? ucfirst(contenuti_tipi()[$tipo]['uno'] ?? 'articolo') . ' vuoto' : 'Pagina vuota' ?></b><span><?= $tipo !== 'pagine' ? 'Una sezione di lettura con un widget Testo.' : 'Solo il titolo.' ?> Costruisci tutto da zero con i widget.</span></a>
  </div>
</div>
</body></html>
<?php exit; endif;

$dati = [
    'id' => $id, 'tipo' => $tipo, 'titolo' => $pagina['title'], 'slug' => $pagina['slug'], 'percorso' => $percorso, 'home' => $home, 'pubblicato' => $pagina['status'] === 'published',
    'stato' => $pagina['status'], 'slugFisso' => $modello, 'modello' => $modello,
    'headerId' => (int)($pagina['header_id'] ?? 0), 'footerId' => (int)($pagina['footer_id'] ?? 0), 'opzioniModelli' => $opzioniModelli,
    'globali' => editor_globali(), 'icone' => editor_icone_set(), 'seo' => ['titolo' => (string)$pagina['seo_title'], 'descrizione' => (string)$pagina['seo_description'], 'robots' => $pagina['robots'] === 'noindex' ? 'noindex' : ''],
    'controlloSeo' => !$modello && function_exists('seo_analisi'),
    'albero' => $albero, 'bozza' => $bozza !== null && $pubblica !== null, 'pubblicata' => $pubblica !== null,
    'haHtml' => trim((string)($tipo !== 'pagine' ? $pagina['content'] : $pagina['html'])) !== '',
    'definizioni' => editor_definizioni(), 'csrf' => csrf_token(), 'temi' => editor_temi_infografica(), 'temiCampi' => editor_temi_campi(),
    'tablet' => editor_soglia('tablet'), 'telefono' => editor_soglia('telefono'),
    // menu: Impostazioni dell'editor (includes/editor-opzioni.php): widget spenti, colonne massime, chi pubblica.
    'widgetDisattivi' => editor_widget_disattivi(), 'colonneMax' => (int)editor_opzione('colonne_max'), 'puoPubblicare' => editor_puo_pubblicare(),
    // 1.3.0: le sezioni pronte (includes/sezioni-pronte.php), non per header e footer.
    'sezioniPronte' => $modello ? [] : sezioni_pronte_per_editor(), 'categorieSezioni' => sezioni_pronte_categorie(),
];
?>
<!DOCTYPE html>
<html lang="it" data-pannello="<?= h(pannello_aspetto()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editor: <?= h($pagina['title']) ?></title>
<link rel="stylesheet" href="<?= pannello_file('assets/quill/quill.snow.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/css/aspetti.css') ?>">
<link rel="stylesheet" href="<?= pannello_file('assets/editor/editor.css') ?>">
<style>.ed-tela[data-misura=t]{width:<?= (int)$dati['tablet'] ?>px}.ed-tela[data-misura=m]{width:<?= min(400, (int)$dati['telefono']) ?>px}
<?= versioni_confronto_css() /* versioni: il confronto nel pannello Revisioni */ ?></style>
<script src="<?= pannello_file('assets/campi-protetti.js') ?>"></script>
</head>
<body class="ed-app">
<header class="ed-barra">
  <a class="ed-indietro" href="<?= h($scheda) ?>" title="Torna alla scheda">←</a>
  <div class="ed-nome"><b id="ed-titolo-pagina"><?= h($pagina['title']) ?></b><small id="ed-percorso"><?= h($percorso) ?></small></div>
  <div class="ed-misure" role="group" aria-label="Misura dello schermo">
    <button type="button" data-misura="d" class="attivo" title="Desktop">&#128421;</button>
    <button type="button" data-misura="t" title="Tablet (fino a <?= EDITOR_TABLET ?> px)">▭</button>
    <button type="button" data-misura="m" title="Telefono (fino a <?= EDITOR_TELEFONO ?> px)">▯</button>
  </div>
  <div class="ed-storia">
    <button type="button" id="ed-annulla" title="Annulla (Ctrl+Z)" disabled>↶</button>
    <button type="button" id="ed-ripeti" title="Ripeti (Ctrl+Y)" disabled>↷</button>
  </div>
  <span class="ed-stato" id="ed-stato"></span>
  <button type="button" class="ed-b" id="ed-apri-impostazioni">Impostazioni</button>
  <button type="button" class="ed-b" id="ed-apri-revisioni">Revisioni</button>
  <a class="ed-b" id="ed-vedi" href="<?= h($modello ? '..' . $percorso : cms_sito_base() . $percorso . ($pagina['status'] === 'published' ? '' : '?preview=1')) ?>" target="_blank"><?= $modello ? 'Vedi sul sito' : 'Vedi la pagina' ?></a>
  <button type="button" class="ed-b" id="ed-salva">Salva bozza</button>
  <button type="button" class="ed-b ed-b-primo" id="ed-pubblica"<?= $dati['puoPubblicare'] ? '' : ' disabled title="Solo un amministratore pubblica (Editor > Impostazioni dell\'editor)"' ?>>Pubblica</button>
</header>
<div class="ed-corpo">
  <aside class="ed-pannello">
    <nav class="ed-schede-pannello">
      <button type="button" data-pannello="widget" class="attivo">Widget</button>
      <?php if ($dati['sezioniPronte']): ?><button type="button" data-pannello="sezioni">Sezioni</button><?php endif; ?>
      <button type="button" data-pannello="modifica">Modifica</button>
      <button type="button" data-pannello="navigatore">Navigatore</button>
    </nav>
    <section class="ed-pannello-vista" id="ed-pannello-widget">
      <input type="search" id="ed-cerca-widget" placeholder="Cerca un widget…" aria-label="Cerca un widget">
      <div id="ed-elenco-widget"></div>
      <p class="ed-aiuto">Trascina un widget nella pagina, oppure fai clic: entra sotto l'elemento selezionato.</p>
    </section>
    <?php if ($dati['sezioniPronte']): ?>
    <section class="ed-pannello-vista" id="ed-pannello-sezioni" hidden>
      <input type="search" id="ed-cerca-sezioni" placeholder="Cerca una sezione…" aria-label="Cerca una sezione">
      <div id="ed-elenco-sezioni"></div>
      <p class="ed-aiuto">Fai clic su una sezione: entra sotto quella selezionata (o in fondo alla pagina). Oppure trascinala nel punto che vuoi. Una volta dentro è una sezione normale: cambi testi, colori e widget come sempre.</p>
    </section>
    <?php endif; ?>
    <section class="ed-pannello-vista" id="ed-pannello-modifica" hidden>
      <div class="ed-modifica-testa"><b id="ed-modifica-nome">Niente selezionato</b><span id="ed-modifica-azioni"></span></div>
      <nav class="ed-schede-controlli" id="ed-schede-controlli"></nav>
      <div id="ed-controlli"><p class="ed-aiuto">Fai clic su un elemento della pagina per modificarlo.</p></div>
    </section>
    <section class="ed-pannello-vista" id="ed-pannello-navigatore" hidden>
      <div id="ed-navigatore"></div>
      <button type="button" class="ed-b" id="ed-aggiungi-sezione">+ Aggiungi una sezione</button>
    </section>
  </aside>
  <main class="ed-tela-cornice">
    <div class="ed-tela" id="ed-tela" data-misura="d">
      <iframe id="ed-iframe" src="editor-anteprima.php?tipo=<?= $tipo ?>&amp;id=<?= $id ?>" title="Anteprima della pagina"></iframe>
    </div>
  </main>
  <!-- versioni: il pannello Revisioni, a destra della tela. Stessa tabella e stesso confronto della pagina Versioni. -->
  <aside class="ed-revisioni" id="ed-revisioni" hidden>
    <div class="ed-revisioni-testa"><h2>Revisioni</h2><button type="button" class="ed-b ed-b-piccolo" id="ed-chiudi-revisioni" title="Chiudi">&#10005;</button></div>
    <p class="ed-aiuto">Ogni salvataggio e ogni pubblicazione lasciano una versione. <b>Cosa è cambiato</b> la confronta con la precedente; <b>Carica nella tela</b> la porta qui, e la pagina cambia solo quando pubblichi di nuovo.</p>
    <div id="ed-elenco-revisioni" class="ed-revisioni-elenco"></div>
    <div class="ed-revisioni-piede">
      <a class="ed-b" id="ed-tutte-versioni" href="versioni.php?ambito=<?= h($modello ? 'modello' : 'contenuto:' . $tipo) ?>&amp;oggetto=<?= $id ?>" target="_blank">Tutte le versioni, anteprima e ripristino</a>
      <?php if ($dati['haHtml']): ?><button type="button" class="ed-b ed-b-pericolo" id="ed-togli-editor">Torna al contenuto della scheda</button><?php endif; ?>
    </div>
  </aside>
</div>

<dialog id="ed-dialogo-impostazioni" class="ed-dialogo">
  <form method="dialog" id="ed-form-impostazioni">
    <h2>Impostazioni della pagina</h2>
    <label>Titolo<input name="titolo" required maxlength="500"></label>
    <label>Indirizzo<span class="ed-slug"><i>/</i><input name="slug" pattern="[a-z0-9\-]+" <?= $dati['slugFisso'] ? 'disabled' : '' ?>><i>/</i></span>
      <small>Se cambi l'indirizzo di un contenuto già pubblicato, dal vecchio parte da solo un redirect 301 al nuovo.</small></label>
    <label>Titolo per Google (vuoto = il titolo)<input name="seo_titolo" maxlength="255"></label>
    <label>Descrizione per Google<textarea name="seo_descrizione" rows="3" maxlength="1000"></textarea></label>
    <label>Indicizzazione<select name="robots"><option value="">Normale (index)</option><option value="noindex">Nascosta a Google (noindex)</option></select></label>
    <label>Stato<select name="stato"><option value="published">Pubblicata</option><option value="draft">Bozza (non visibile sul sito)</option></select></label>
    <?php if ($opzioniModelli): ?>
    <div class="ed-due">
      <label>Header di questa pagina<select name="header_id"><?php foreach ($opzioniModelli['header'] as $k => $n) echo '<option value="' . h($k) . '">' . h($n) . '</option>'; ?></select></label>
      <label>Footer di questa pagina<select name="footer_id"><?php foreach ($opzioniModelli['footer'] as $k => $n) echo '<option value="' . h($k) . '">' . h($n) . '</option>'; ?></select></label>
    </div>
    <?php endif; ?>
    <p class="ed-errore" id="ed-impostazioni-errore" hidden></p>
    <?php if ($dati['controlloSeo']): ?><div class="ed-seo" id="ed-seo"></div><?php endif; ?>
    <div class="ed-dialogo-azioni"><button type="button" class="ed-b" value="chiudi" data-chiudi>Annulla</button><button type="submit" class="ed-b ed-b-primo">Salva impostazioni</button></div>
  </form>
</dialog>

<dialog id="ed-dialogo-media" class="ed-dialogo ed-dialogo-largo">
  <h2>Immagini</h2>
  <div class="ed-media-testa">
    <label class="ed-b">Carica un'immagine<input type="file" id="ed-media-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden></label>
    <label class="ed-media-url">oppure incolla un indirizzo<input type="url" id="ed-media-url" placeholder="https://…"><button type="button" class="ed-b" id="ed-media-usa-url">Usa</button></label>
  </div>
  <div class="ed-media-filtri">
    <select id="ed-media-cartella"><option value="">Tutte le cartelle</option></select>
    <input type="search" id="ed-media-cerca" placeholder="Cerca nel nome…">
    <span class="ed-aiuto" id="ed-media-conta"></span>
  </div>
  <p class="ed-errore" id="ed-media-errore" hidden></p>
  <div class="ed-media-griglia" id="ed-media-griglia"></div>
  <div class="ed-media-pagine" id="ed-media-pagine"></div>
  <div class="ed-dialogo-azioni"><button type="button" class="ed-b" data-chiudi>Chiudi</button></div>
</dialog>

<script id="ed-dati" type="application/json"><?= str_replace('</', '<\/', json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></script>
<script src="<?= pannello_file('assets/quill/quill.js') ?>"></script>
<script src="<?= pannello_file('assets/editor/Sortable.min.js') ?>"></script>
<script src="<?= pannello_file('assets/editor/editor.js') ?>"></script>
</body>
</html>
