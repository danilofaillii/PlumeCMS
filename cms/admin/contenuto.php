<?php
/**
 * La scheda di un articolo, di una pagina o di un contenuto di un modulo: titolo e permalink,
 * editor Visuale/Testo, estratto; a destra Pubblica (stato, data, ora, anteprima), Categorie, Tag,
 * Immagine in evidenza, Attributi; sotto SEO, risposte dirette, revisioni.
 *   contenuto.php?tipo=articoli&id=12      contenuto.php?tipo=pagine (nuova)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/contenuti.php';
require_once dirname(__DIR__) . '/includes/editor.php';
require_login();
// Un contenuto nuovo nasce nell'editor visuale: la scheda classica serve solo ai contenuti che esistono già.
if (empty($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['scheda'])) { header('Location: editor.php?nuova=1&tipo=' . (isset(contenuti_tipi()[$_GET['tipo'] ?? '']) && ($_GET['tipo'] ?? '') !== 'pagine' ? $_GET['tipo'] : 'pagine')); exit; }

sito_schema_aggiorna();

$tipo = isset(contenuti_tipi()[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : 'articoli';
$T = contenuti_tipo($tipo);
$id = (int)($_GET['id'] ?? 0);
$r = $id ? contenuti_leggi($tipo, $id) : null;
if ($id && !$r) { http_response_code(404); exit($T['Uno'] . ' non trovato. <a href="contenuti.php?tipo=' . $tipo . '">Torna all\'elenco</a>'); }
$nuovo = !$r;
$adminTitle = $nuovo ? 'Aggiungi ' . $T['uno'] : 'Modifica ' . $T['uno'];
$autore = (string)(current_user()['username'] ?? '');
$home = $tipo === 'pagine' && $r && $r['slug'] === (string)get_setting('pagina_home', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        switch ($_POST['azione'] ?? 'salva') {
            case 'cestina':
                if ($home) throw new RuntimeException('La home non va nel cestino.');
                contenuti_cestina($tipo, [$id]);
                flash('success', $T['Uno'] . ' spostato nel cestino.');
                header('Location: contenuti.php?tipo=' . $tipo);
                exit;
            case 'ripristina':
                contenuti_ripristina_revisione($tipo, $id, (int)$_POST['revisione'], $autore);
                flash('success', 'Revisione ripristinata.');
                break;
            default:
                $d = $_POST;
                $d['faq'] = admin_faq_da_testo((string)($_POST['faq_testo'] ?? ''));
                if (($d['invio'] ?? '') === 'bozza') $d['stato'] = 'bozza';
                elseif (($d['invio'] ?? '') === 'pubblica') $d['stato'] = 'pubblicato';
                if ($home && ($d['stato'] ?? '') === 'bozza') throw new RuntimeException('La home non può andare in bozza.');
                $id = contenuti_salva($tipo, $id, $d, $autore);
                $salvato = contenuti_leggi($tipo, $id);
                $stato = contenuti_stato($tipo, $salvato);
                flash('success', $nuovo ? $T['Uno'] . ' creato' . ($stato === 'bozza' ? ' in bozza.' : ($stato === 'programmato' ? ' e programmato.' : ' e pubblicato.')) : $T['Uno'] . ' aggiornato.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        if (!$id) { $r = $_POST + ['id' => 0]; }
    }
    if ($id) { header('Location: contenuto.php?tipo=' . $tipo . '&id=' . $id); exit; }
}

// Valori del modulo: dalla riga oppure, dopo un errore su un contenuto nuovo, da quello che era stato scritto.
$sc = [
    'title' => $r['title'] ?? '', 'slug' => $r['slug'] ?? '', 'contenuto' => $r[$T['campo_contenuto']] ?? ($r['contenuto'] ?? ''),
    'excerpt' => $r['excerpt'] ?? '', 'author' => $r['author'] ?? (string)cms_impostazione('autore_predefinito', ''),
    'seo_title' => $r['seo_title'] ?? '', 'seo_description' => $r['seo_description'] ?? '', 'meta_description' => $r['meta_description'] ?? '',
    'robots' => $r['robots'] ?? '', 'tldr' => $r['tldr'] ?? '', 'faq_testo' => isset($r['faq_testo']) ? $r['faq_testo'] : admin_faq_a_testo($r['faq'] ?? ''),
    'immagine' => $r['immagine_url'] ?? ($r['immagine'] ?? ($r['featured_image'] ?? '')), 'immagine_alt' => $r['featured_image_alt'] ?? ($r['immagine_alt'] ?? ''),
    'type' => $r['type'] ?? 'cluster', 'keyword_target' => $r['keyword_target'] ?? '', 'keywords_secondarie' => $r['keywords_secondarie'] ?? '',
    'cta' => $r['cta'] ?? '', 'pillar_ref' => $r['pillar_ref'] ?? '', 'pillar_title' => $r['pillar_title'] ?? '', 'author_quote' => $r['author_quote'] ?? '',
    'content_format' => $r['content_format'] ?? 'html',
];
$quando = $r && !empty($r['id']) ? contenuti_quando($tipo, $r) : '';
$sc['data'] = $r['data'] ?? (substr($quando, 0, 10) ?: date('Y-m-d'));
// Un contenuto nuovo parte da adesso: "Pubblica" lo mette online subito (fino alla 1.2.7 partiva dalle 06:00 di oggi).
$sc['ora'] = $r['ora'] ?? (substr($quando, 11, 5) ?: date('H:i'));
$stato = $r && !empty($r['id']) ? contenuti_stato($tipo, $r) : 'bozza';
$sc['stato'] = $r['stato'] ?? ($stato === 'bozza' ? 'bozza' : 'pubblicato');
$cats = $T['tassonomie'] ? ($r && !empty($r['id']) ? contenuti_categorie_di($r) : (array)($r['categorie'] ?? [])) : [];
$tagNomi = $T['tassonomie'] ? ($r && !empty($r['id']) ? contenuti_tag_di($r) : sito_lista((string)($r['tags'] ?? ''))) : [];
$percorso = $r && !empty($r['id']) ? contenuti_percorso($tipo, $r) : '/' . ($sc['slug'] ?: 'nuovo') . '/';
$conEditor = $r && !empty($r['id']) && (trim((string)$r['struttura']) !== '' || trim((string)$r['struttura_bozza']) !== '');
$revisioni = $id ? contenuti_revisioni($tipo, $id) : [];
$parole = contenuti_parole((string)$sc['contenuto']);
$csrf = csrf_token();

require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">

<form method="post" id="sc-form" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="azione" value="salva"><input type="hidden" name="invio" id="sc-invio" value="">
  <input type="hidden" name="content_format" value="<?= h($sc['content_format']) ?>">
  <div class="sc-griglia">
    <div>
      <input class="sc-titolo" type="text" name="title" id="sc-title" value="<?= h($sc['title']) ?>" placeholder="Aggiungi il titolo" required autocomplete="off">
      <div class="sc-permalink">
        <b>Permalink:</b> <span><?= h(cms_url_sito()) ?></span><?= $home ? '<span>/</span><input type="hidden" name="slug" value="' . h($sc['slug']) . '">' : '' ?>
        <?php if (!$home): ?><span>/</span><input type="text" name="slug" id="sc-slug" value="<?= h($sc['slug']) ?>" pattern="[a-z0-9\-]+" placeholder="indirizzo" <?= $nuovo ? 'data-auto="1"' : '' ?>><span>/</span><?php endif; ?>
        <?php if (!$nuovo): ?><a href="<?= h(cms_sito_base() . $percorso) ?><?= $stato === 'pubblicato' ? '' : '?preview=1' ?>" target="_blank"><?= $stato === 'pubblicato' ? 'Visualizza' : 'Anteprima' ?></a><?php endif; ?>
        <?php if (!$nuovo && $stato === 'pubblicato'): ?><small>Se lo cambi, dal vecchio indirizzo parte un redirect 301.</small><?php endif; ?>
      </div>

      <?php if ($conEditor): ?>
      <div class="sc-editor-visuale"><span><b>Composto con l'editor visuale.</b> Sul sito esce la struttura dell'editor; il testo qui sotto resta come riserva e per le ricerche.</span><a class="btn btn-primary btn-sm" href="editor.php?tipo=<?= $tipo ?>&amp;id=<?= $id ?>">Apri nell'editor visuale</a></div>
      <?php elseif (!$nuovo): ?>
      <div class="sc-editor-visuale"><span>Vuoi comporlo a sezioni e widget con l'editor visuale?</span><a class="btn btn-secondary btn-sm" href="editor.php?tipo=<?= $tipo ?>&amp;id=<?= $id ?>">Apri nell'editor visuale</a></div>
      <?php endif; ?>

      <div class="te" id="te">
        <div class="te-schede">
          <button type="button" class="btn btn-secondary btn-sm" style="margin-right:auto;background:var(--coral);color:var(--su-accento);border:0" data-al-clic="teInfografica()">+ Infografica</button>
          <button type="button" class="btn btn-secondary btn-sm" style="margin-right:.5rem" data-al-clic="scMediaPer('teInserisciImmagine')">+ Immagine</button>
          <button type="button" data-scheda="visuale" class="attivo">Visuale</button>
          <button type="button" data-scheda="testo">Testo</button>
        </div>
        <?php if ($sc['content_format'] === 'markdown'): ?>
        <div class="te-md">Questo testo è in <b>Markdown</b>. La scheda Visuale lo mostra convertito: appena salvi da Visuale diventa HTML per sempre. Da Testo resta Markdown.</div>
        <?php endif; ?>
        <div class="te-barra" id="te-barra">
          <select data-cmd="formatBlock" title="Formato"><option value="p">Paragrafo</option><option value="h2">Titolo 2</option><option value="h3">Titolo 3</option><option value="h4">Titolo 4</option><option value="blockquote">Citazione</option></select>
          <span class="te-sep"></span>
          <button type="button" data-cmd="bold" title="Grassetto (Ctrl+B)"><b>B</b></button>
          <button type="button" data-cmd="italic" title="Corsivo (Ctrl+I)"><i>I</i></button>
          <button type="button" data-cmd="underline" title="Sottolineato"><u>U</u></button>
          <button type="button" data-cmd="strikeThrough" title="Barrato"><s>S</s></button>
          <span class="te-sep"></span>
          <button type="button" data-cmd="insertUnorderedList" title="Elenco puntato">• ≡</button>
          <button type="button" data-cmd="insertOrderedList" title="Elenco numerato">1. ≡</button>
          <span class="te-sep"></span>
          <button type="button" data-az="link" title="Link (Ctrl+K)">&#128279;</button>
          <button type="button" data-cmd="unlink" title="Togli il link">⛓</button>
          <button type="button" data-cmd="insertHorizontalRule" title="Linea">&mdash;</button>
          <span class="te-sep"></span>
          <button type="button" data-cmd="removeFormat" title="Togli la formattazione">Tx</button>
          <button type="button" data-cmd="undo" title="Annulla">↶</button>
          <button type="button" data-cmd="redo" title="Ripeti">↷</button>
        </div>
        <div class="te-visuale" id="te-visuale" contenteditable="true" spellcheck="true"></div>
        <textarea class="te-testo" id="te-testo" name="contenuto" hidden><?= h($sc['contenuto']) ?></textarea>
        <div class="te-piede"><span id="te-parole"><?= $parole ?> parole</span><span>Ctrl+S salva</span></div>
      </div>

      <div class="sc-scatola">
        <h3>Estratto</h3>
        <div class="sc-corpo">
          <textarea name="excerpt" rows="3"><?= h($sc['excerpt']) ?></textarea>
          <div class="hint">Il riassunto breve per le schede e i social. Se resta vuoto, si usa la descrizione per Google.</div>
        </div>
      </div>

      <div class="sc-scatola">
        <h3>SEO e Google</h3>
        <div class="sc-corpo">
          <label>Titolo per Google (vuoto = il titolo)</label><input type="text" name="seo_title" value="<?= h($sc['seo_title']) ?>" maxlength="255">
          <label>Descrizione per Google</label><textarea name="meta_description" rows="2" maxlength="500" id="sc-meta"><?= h($sc['meta_description']) ?></textarea>
          <div class="hint"><span id="sc-meta-conta"><?= mb_strlen($sc['meta_description']) ?></span>/160 caratteri. Vale per lo snippet nei risultati.</div>
          <label>Descrizione alternativa (se diversa dalla meta)</label><textarea name="seo_description" rows="2"><?= h($sc['seo_description']) ?></textarea>
          <?php if ($T['attributi']): ?>
          <div class="sc-riga">
            <div><label>Keyword principale</label><input type="text" name="keyword_target" value="<?= h($sc['keyword_target']) ?>"></div>
            <div><label>Keyword correlate (virgola)</label><input type="text" name="keywords_secondarie" value="<?= h($sc['keywords_secondarie']) ?>"></div>
          </div>
          <?php endif; ?>
          <label>Indicizzazione</label>
          <select name="robots"><option value="" <?= $sc['robots'] === '' ? 'selected' : '' ?>>Automatica (visibile a Google)</option><option value="index" <?= $sc['robots'] === 'index' ? 'selected' : '' ?>>Sempre visibile</option><option value="noindex" <?= $sc['robots'] === 'noindex' ? 'selected' : '' ?>>Nascosto a Google (noindex)</option></select>
        </div>
      </div>

      <?php // seo: il controllo come Rank Math (includes/seo.php, seo_analisi). Il pulsante ricontrolla i campi del modulo senza salvare.
      require_once dirname(__DIR__) . '/includes/seo.php';
      $seoAnalisi = seo_analisi($sc + ['faq' => admin_faq_da_testo((string)$sc['faq_testo'])]); ?>
      <div class="sc-scatola" id="seo-controllo">
        <h3>Controllo SEO: <?= $seoAnalisi['punteggio'] ?>/100</h3>
        <div class="sc-corpo">
          <div id="seo-controllo-corpo"><?= seo_analisi_html($seoAnalisi) ?></div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.6rem;gap:.5rem;flex-wrap:wrap"><span class="hint">Le soglie (densita 1,8% e 1,5%, parole del primo paragrafo, In breve) stanno in <a href="seo.php?scheda=schema">SEO e AEO</a>.</span><button type="button" class="btn btn-secondary btn-sm" id="seo-ricontrolla">Ricontrolla</button></div>
        </div>
      </div>
      <script src="<?= pannello_file('assets/seo-controllo.js') ?>" defer></script>

      <div class="sc-scatola">
        <h3>Risposte dirette (motori di ricerca e IA)</h3>
        <div class="sc-corpo">
          <label>In breve</label><textarea name="tldr" rows="3"><?= h($sc['tldr']) ?></textarea>
          <div class="hint">Da 40 a 60 parole. Risponde subito alla domanda principale, in cima al testo.</div>
          <label>Domande frequenti</label><textarea name="faq_testo" rows="6"><?= h($sc['faq_testo']) ?></textarea>
          <div class="hint">Un blocco per domanda: prima riga la domanda, sotto la risposta. Blocchi separati da una riga vuota. Entrano nei dati per Google (FAQPage).</div>
        </div>
      </div>

      <?php if ($revisioni): ?>
      <div class="sc-scatola chiusa">
        <h3>Revisioni (<?= count($revisioni) ?>)</h3>
        <div class="sc-corpo">
          <div class="hint" style="margin-bottom:.5rem">Ogni salvataggio tiene una copia di com'era prima. Ripristinare riporta titolo, testo, indirizzo e tutto il resto a quella versione; la versione di adesso finisce qui sotto.</div>
          <?php foreach ($revisioni as $rev): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--border);font-size:.85rem">
            <span><?= h($rev['creato']) ?> <span class="hint" style="display:inline"><?= h($rev['autore']) ?><?= $rev['nota'] ? ' · ' . h($rev['nota']) : '' ?> · <?= round($rev['peso'] / 1024) ?> KB</span></span>
            <button type="submit" class="btn btn-secondary btn-sm" formaction="contenuto.php?tipo=<?= $tipo ?>&amp;id=<?= $id ?>" name="azione" value="ripristina" data-campo="revisione" data-valore="<?= $rev['id'] ?>" data-conferma="Ripristinare la versione del <?= h($rev['creato']) ?>?">Ripristina</button>
          </div>
          <?php endforeach; ?>
          <input type="hidden" name="revisione" value="">
        </div>
      </div>
      <?php endif; ?>
    </div>

    <aside>
      <div class="sc-scatola">
        <h3>Pubblica</h3>
        <div class="sc-corpo">
          <div class="sc-stato-riga">Stato: <b><?= $nuovo ? 'Nuovo' : CONTENUTI_STATI[$stato] ?></b></div>
          <label>Visibilità</label>
          <select name="stato" id="sc-stato"><option value="pubblicato" <?= $sc['stato'] === 'pubblicato' ? 'selected' : '' ?>>Pubblicato</option><option value="bozza" <?= $sc['stato'] === 'bozza' ? 'selected' : '' ?>>Bozza</option></select>
          <label>Data e ora di uscita</label>
          <div class="sc-riga"><input type="date" name="data" value="<?= h($sc['data']) ?>"><input type="time" name="ora" value="<?= h($sc['ora']) ?>" step="60"></div>
          <div class="hint">Pubblicato con una data futura = programmato: esce da solo a quell'ora, al minuto.</div>
          <label>Autore</label><input type="text" name="author" value="<?= h($sc['author']) ?>" maxlength="100">
          <div class="sc-pubblica-azioni">
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
              <?php if (!$home): ?><button type="submit" class="btn btn-secondary btn-sm" data-campo-id="sc-invio" data-valore="bozza">Salva bozza</button><?php endif; ?>
              <?php if (!$nuovo): ?><a class="btn btn-secondary btn-sm" href="<?= h(cms_sito_base() . $percorso) ?>?preview=1" target="_blank" data-al-clic="scAnteprima(event)">Anteprima</a><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary" data-campo-id="sc-invio" data-valore="pubblica"><?= $nuovo || $stato === 'bozza' ? 'Pubblica' : 'Aggiorna' ?></button>
          </div>
          <?php if (!$nuovo && !$home): ?>
          <div style="margin-top:.6rem;text-align:right"><button type="submit" class="sc-cestina" name="azione" value="cestina" data-conferma="Spostare nel cestino?">Sposta nel cestino</button></div>
          <?php endif; ?>
        </div>
      </div>

      <?php // modelli: header e footer di questo contenuto (Theme Builder, includes/modelli.php)
      if (!$nuovo && is_file(dirname(__DIR__) . '/includes/modelli.php')): require_once dirname(__DIR__) . '/includes/modelli.php'; ?>
      <div class="sc-scatola">
        <h3>Header e footer</h3>
        <div class="sc-corpo">
          <label>Header</label>
          <select name="header_id"><?php foreach (modelli_opzioni_scelta('header') as $k => $n): ?><option value="<?= h($k) ?>" <?= (int)($r['header_id'] ?? 0) === (int)$k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
          <label>Footer</label>
          <select name="footer_id"><?php foreach (modelli_opzioni_scelta('footer') as $k => $n): ?><option value="<?= h($k) ?>" <?= (int)($r['footer_id'] ?? 0) === (int)$k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
          <div class="hint">Predefinito = quello con la stellina in <a href="modelli.php">Header e footer</a>. Nessuno = la pagina esce senza.</div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($T['tassonomie']): ?>
      <div class="sc-scatola">
        <h3>Categorie</h3>
        <div class="sc-corpo">
          <div class="sc-cat-lista">
            <?php foreach (contenuti_categorie() as $slug => $c): ?>
            <label><input type="checkbox" name="categorie[]" value="<?= h($slug) ?>" <?= in_array($slug, $cats, true) ? 'checked' : '' ?>> <?= h($c['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label>Aggiungi una categoria nuova</label><input type="text" name="categoria_nuova" placeholder="Nome">
          <div class="hint">La prima spuntata è quella principale (nelle briciole e nell'occhiello).</div>
        </div>
      </div>

      <div class="sc-scatola">
        <h3>Tag</h3>
        <div class="sc-corpo">
          <input type="hidden" name="tags" id="sc-tags" value="<?= h(implode(', ', $tagNomi)) ?>">
          <div id="sc-tag-chips"></div>
          <input type="text" id="sc-tag-nuovo" list="sc-tag-lista" placeholder="Scrivi e premi Invio" autocomplete="off" style="margin-top:.4rem">
          <datalist id="sc-tag-lista"><?php foreach (contenuti_tag_tutti() as $t): ?><option value="<?= h($t) ?>"><?php endforeach; ?></datalist>
          <div class="hint">Separa i tag con la virgola o con Invio.</div>
        </div>
      </div>
      <?php endif; ?>

      <div class="sc-scatola">
        <h3>Immagine in evidenza</h3>
        <div class="sc-corpo sc-immagine">
          <input type="hidden" name="immagine" id="sc-immagine" value="<?= h($sc['immagine']) ?>">
          <img id="sc-immagine-anteprima" src="<?= h($sc['immagine']) ?>" alt="" <?= $sc['immagine'] === '' ? 'hidden' : '' ?>>
          <div class="sc-img-azioni">
            <button type="button" class="btn btn-secondary btn-sm" data-al-clic="scMediaPer('scImmagine')"><?= $sc['immagine'] === '' ? 'Scegli l\'immagine' : 'Cambia' ?></button>
            <button type="button" class="btn btn-secondary btn-sm" id="sc-immagine-togli" data-al-clic="scImmagine('')" <?= $sc['immagine'] === '' ? 'hidden' : '' ?>>Togli</button>
          </div>
          <?php if ($T['attributi']): ?><label>Testo alternativo</label><input type="text" name="immagine_alt" value="<?= h($sc['immagine_alt']) ?>"><?php endif; ?>
        </div>
      </div>

      <?php if ($T['citazione']): ?>
      <div class="sc-scatola">
        <h3>Citazione</h3>
        <div class="sc-corpo">
          <label>Autore della frase (facoltativo)</label><input type="text" name="author_quote" value="<?= h($sc['author_quote']) ?>" placeholder="Es: Oscar Wilde" maxlength="255">
        </div>
      </div>
      <?php endif; ?>

      <?php if ($T['attributi']): ?>
      <div class="sc-scatola">
        <h3>Attributi dell'articolo</h3>
        <div class="sc-corpo">
          <label>Tipo</label><select name="type"><option value="cluster" <?= $sc['type'] === 'cluster' ? 'selected' : '' ?>>Cluster</option><option value="pillar" <?= $sc['type'] === 'pillar' ? 'selected' : '' ?>>Pillar (guida completa)</option></select>
          <label>Pillar padre (slug)</label><input type="text" name="pillar_ref" value="<?= h($sc['pillar_ref']) ?>">
          <label>Titolo del pillar padre</label><input type="text" name="pillar_title" value="<?= h($sc['pillar_title']) ?>">
        </div>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</form>

<?php require __DIR__ . '/includes/infografica-modal.php'; ?>

<dialog id="sc-media" class="sc-media">
  <div class="sc-media-dentro">
    <h2>Immagini</h2>
    <div class="sc-media-filtri">
      <label class="btn btn-secondary btn-sm">Carica<input type="file" id="sc-media-file" accept="image/*" hidden></label>
      <select id="sc-media-cartella"><option value="">Tutte le cartelle</option></select>
      <input type="search" id="sc-media-cerca" placeholder="Cerca nel nome…">
      <input type="url" id="sc-media-url" placeholder="oppure incolla un indirizzo" style="min-width:200px"><button type="button" class="btn btn-secondary btn-sm" data-al-clic="scMediaUrl()">Usa</button>
    </div>
    <p class="hint" id="sc-media-errore" style="color:var(--errore-testo)" hidden></p>
    <div class="sc-media-griglia" id="sc-media-griglia"></div>
    <div class="sc-media-pagine" id="sc-media-pagine"></div>
  </div>
  <div class="sc-media-azioni"><button type="button" class="btn btn-secondary btn-sm" data-chiudi-dialogo="#sc-media">Chiudi</button></div>
</dialog>

<script<?= nonce_attr() ?>>window.SC = <?= json_encode(['csrf' => $csrf, 'tipo' => $tipo, 'id' => $id, 'formato' => $sc['content_format'], 'nuovo' => $nuovo], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= pannello_file('assets/infografica.js') ?>"></script>
<script src="<?= pannello_file('assets/contenuto.js') ?>"></script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
