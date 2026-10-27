<?php
/**
 * SEO e AEO: la pagina di configurazione di Search Console, Bing, IndexNow, statistiche, titoli, robots,
 * sitemap, redirect e 404, dati strutturati, llms.txt e il controllo SEO di ogni contenuto.
 * Il motore e in includes/seo.php. Documento: docs/seo.md.
 *   seo.php?scheda=panoramica | motori | copertura | prestazioni | analisi | globale | schema | redirect | log
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/sito/viste.php';
require_once dirname(__DIR__) . '/sito/extra.php';
require_once dirname(__DIR__) . '/includes/moduli.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_admin();
seo_installa();
// I tipi con il loro formato del titolo e la loro regola di indicizzazione: articoli e pagine, più i tipi dei moduli del sito.
$seoTitoli = ['home' => 'Home', 'articolo' => 'Articoli', 'pagina' => 'Pagine'];
$seoRobots = ['articoli' => 'Articoli', 'pagine' => 'Pagine'];
foreach (seo_tipi_moduli() as $k => $tm) { $seoTitoli[(string)$tm['uno']] = (string)$tm['Tanti']; $seoRobots[$k] = (string)$tm['Tanti']; }
$seoTitoli += ['archivio' => 'Categorie, tag, archivi', 'altro' => 'Altre pagine (cerca, 404, glossario)'];
$seoRobots += ['categorie' => 'Categorie', 'tag' => 'Tag', 'data' => 'Archivi per data', 'cerca' => 'Pagina di ricerca', 'paginazione' => 'Pagine 2, 3... degli archivi e del blog'];

$adminTitle = 'SEO e AEO';
$schede = ['panoramica' => 'Panoramica', 'motori' => 'Motori di ricerca', 'copertura' => 'Copertura', 'prestazioni' => 'Prestazioni',
    'analisi' => 'Analisi e tag', 'globale' => 'Globale SEO', 'schema' => 'Schema e AEO', 'redirect' => 'Redirect e 404', 'log' => 'Registro'];
$scheda = isset($schede[$_GET['scheda'] ?? '']) ? $_GET['scheda'] : 'panoramica';
$torna = fn(string $s, string $extra = '') => 'seo.php?scheda=' . $s . ($extra !== '' ? '&' . $extra : '');

/** Un gruppo di interruttori 0/1 dal form. */
function seo_form_interruttori(array $chiavi, string $prefisso): array {
    $out = [];
    foreach ($chiavi as $k) $out[$k] = !empty($_POST[$prefisso][$k]) ? 1 : 0;
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $dove = $torna($scheda);
    try {
        switch ($azione) {
            // ---- Motori di ricerca
            case 'salva_verifica':
                $v = $_POST['verifica'] ?? [];
                $g = trim((string)($v['google'] ?? ''));
                // Si accetta il valore del meta tag, il tag intero incollato, oppure il nome del file google....html.
                if (preg_match('/content=["\']([^"\']+)["\']/', $g, $m)) $g = $m[1];
                $b = trim((string)($v['bing'] ?? ''));
                if (preg_match('/content=["\']([^"\']+)["\']/', $b, $m)) $b = $m[1];
                $file = trim((string)($v['bing_file'] ?? ''));
                if ($file !== '' && !str_contains($file, '<users>')) throw new RuntimeException('Il file BingSiteAuth.xml deve contenere <users><user>...</user></users>: incolla il contenuto intero del file scaricato da Bing.');
                seo_salva('verifica', ['google' => $g, 'bing' => $b, 'bing_file' => $file, 'yandex' => trim((string)($v['yandex'] ?? '')), 'pinterest' => trim((string)($v['pinterest'] ?? ''))]);
                flash('success', 'Codici di verifica salvati. Ora torna nel servizio e premi Verifica.');
                break;
            case 'indexnow_genera':
                seo_indexnow_genera();
                flash('success', 'Chiave IndexNow generata. Il sito la serve gia in /' . seo_indexnow_chiave() . '.txt.');
                break;
            case 'salva_indexnow':
                seo_salva('indexnow', ['attivo' => !empty($_POST['indexnow']['attivo']) ? 1 : 0, 'subito' => !empty($_POST['indexnow']['subito']) ? 1 : 0]);
                flash('success', 'Impostazioni IndexNow salvate.');
                break;
            case 'indexnow_coda':
                flash('info', seo_coda_invia(10000, 20));
                break;
            case 'indexnow_sitemap':
                flash('info', seo_indexnow_tutta_sitemap());
                break;
            case 'indexnow_url':
                $u = trim((string)($_POST['url'] ?? ''));
                if ($u === '') throw new RuntimeException('Scrivi un indirizzo.');
                seo_coda_aggiungi($u, 'manuale');
                flash('info', seo_coda_invia(100, 15));
                break;
            case 'google_sa':
                $email = seo_google_sa_salva((string)($_POST['sa_json'] ?? ''));
                flash('success', "Service account salvato: $email. Ora aggiungilo come utente in Search Console (scheda Motori, riquadro Google, passo 3).");
                break;
            case 'google_sa_togli':
                seo_google_sa_togli();
                flash('success', 'Service account rimosso dal database.');
                break;
            case 'salva_google':
                seo_salva('google', ['proprieta' => trim((string)($_POST['google']['proprieta'] ?? '')) ?: SITO_URL . '/', 'indexing_attivo' => !empty($_POST['google']['indexing_attivo']) ? 1 : 0]);
                flash('success', 'Impostazioni Google salvate.');
                break;
            case 'google_prova':
                $r = seo_gsc_prova();
                if (isset($r['errore'])) flash('error', 'Collegamento non riuscito. ' . $r['errore']);
                else {
                    $siti = array_map(fn($x) => $x['siteUrl'] . ' (' . ($x['permissionLevel'] ?? '') . ')', $r['dati']['siteEntry'] ?? []);
                    flash('success', $siti ? 'Collegamento riuscito. Il service account vede: ' . implode(', ', $siti) : 'Collegamento riuscito, ma il service account non vede nessuna proprieta: aggiungilo come utente in Search Console.');
                }
                break;
            case 'google_indexing_url':
                $u = trim((string)($_POST['url'] ?? ''));
                if ($u === '') throw new RuntimeException('Scrivi un indirizzo.');
                $r = seo_google_indexing($u);
                flash(isset($r['errore']) ? 'error' : 'success', isset($r['errore']) ? $r['errore'] : 'Google ha ricevuto la notifica per ' . $u . '.');
                break;
            // ---- Copertura
            case 'ispeziona':
                $u = trim((string)($_POST['url'] ?? ''));
                if ($u === '') throw new RuntimeException('Scrivi un indirizzo.');
                $r = seo_gsc_ispeziona($u);
                flash(isset($r['errore']) ? 'error' : 'success', isset($r['errore']) ? $r['errore'] : 'Ispezionato: ' . seo_copertura_italiano($r['copertura']) . '.');
                break;
            case 'ispeziona_lotto':
                [$fatti, $err] = seo_gsc_ispeziona_lotto((int)($_POST['quanti'] ?? 20));
                flash($err !== '' ? 'error' : 'success', "$fatti indirizzi ispezionati." . ($err !== '' ? ' Poi: ' . $err : ''));
                break;
            // ---- Prestazioni
            case 'prestazioni_aggiorna':
                foreach (['date', 'page', 'query'] as $d) { $r = seo_gsc_prestazioni($d, 28, 50, true); if (isset($r['errore'])) { flash('error', $r['errore']); break 2; } }
                flash('success', 'Prestazioni aggiornate da Search Console.');
                break;
            // ---- Analisi e tag
            case 'salva_analisi':
                $a = $_POST['analisi'] ?? [];
                $pulisci = fn(string $k, string $re) => (preg_match($re, trim((string)($a[$k] ?? ''))) ? trim((string)$a[$k]) : '');
                if (trim((string)($a['ga4'] ?? '')) !== '' && !preg_match('/^G-[A-Z0-9]{4,}$/', trim((string)$a['ga4']))) throw new RuntimeException('L\'ID di Google Analytics 4 ha la forma G-XXXXXXXXXX.');
                if (trim((string)($a['gtm'] ?? '')) !== '' && !preg_match('/^GTM-[A-Z0-9]{4,}$/', trim((string)$a['gtm']))) throw new RuntimeException('L\'ID di Tag Manager ha la forma GTM-XXXXXXX.');
                seo_salva('analisi', ['ga4' => $pulisci('ga4', '/^G-[A-Z0-9]{4,}$/'), 'gtm' => $pulisci('gtm', '/^GTM-[A-Z0-9]{4,}$/'), 'pixel' => $pulisci('pixel', '/^\d{6,20}$/'),
                    'clarity' => $pulisci('clarity', '/^[a-z0-9]{6,20}$/i'), 'consenso' => ($a['consenso'] ?? '') === 'subito' ? 'subito' : 'banner',
                    'banner_testo' => (string)($a['banner_testo'] ?? ''), 'codice_head' => (string)($a['codice_head'] ?? ''),
                    'codice_body_inizio' => (string)($a['codice_body_inizio'] ?? ''), 'codice_body_fine' => (string)($a['codice_body_fine'] ?? '')]);
                flash('success', 'Analisi e tag salvati. Controlla il sorgente del sito: i tag compaiono nel <head>.');
                break;
            // ---- Globale
            case 'salva_titoli':
                $t = $_POST['titolo'] ?? []; $m = $_POST['meta'] ?? [];
                $tipiTitolo = array_keys($seoTitoli);
                foreach ($tipiTitolo as $k) if (!str_contains((string)($t[$k] ?? ''), '%titolo%')) throw new RuntimeException("Il formato del titolo ($k) deve contenere %titolo%.");
                seo_salva('titolo', ['sep' => trim((string)($t['sep'] ?? '-')) ?: '-', 'sito' => trim((string)($t['sito'] ?? '')) ?: SITO_NOME] + array_intersect_key($t, array_flip($tipiTitolo)));
                seo_salva('meta', ['descrizione' => (string)($m['descrizione'] ?? ''), 'og_immagine' => (string)($m['og_immagine'] ?? ''), 'twitter_account' => ltrim(trim((string)($m['twitter_account'] ?? '')), '@'), 'robots_avanzati' => !empty($m['robots_avanzati']) ? 1 : 0]);
                flash('success', 'Titoli e meta salvati.');
                break;
            case 'salva_robots':
                $r = [];
                foreach (array_keys($seoRobots) as $k) $r[$k] = in_array($_POST['robots'][$k] ?? '', ['index', 'noindex'], true) ? $_POST['robots'][$k] : 'auto';
                seo_salva('robots', $r);
                flash('success', 'Regole di indicizzazione salvate.');
                break;
            case 'salva_robots_txt':
                $ai = [];
                foreach (array_keys(SEO_CRAWLER_AI) as $bot) $ai[$bot] = !empty($_POST['ai'][$bot]) ? 1 : 0;
                $p = (string)($_POST['robots_txt'] ?? '');
                if (!empty($_POST['ripristina'])) $p = '';
                if (trim($p) !== '' && !preg_match('/^\s*(#|User-agent|Allow|Disallow|Sitemap|Crawl-delay|Host)/im', $p)) throw new RuntimeException('Il robots.txt non sembra valido: ogni riga comincia con User-agent, Allow, Disallow, Sitemap o #.');
                seo_salva('robots_txt', ['personale' => $p, 'ai' => $ai]);
                flash('success', trim($p) !== '' ? 'robots.txt personalizzato salvato: il sito lo serve subito.' : 'robots.txt: si usa il predefinito' . (array_filter($ai) ? ' con i crawler AI scelti fermati.' : '.'));
                break;
            case 'salva_llms':
                seo_salva('llms', ['personale' => !empty($_POST['ripristina']) ? '' : (string)($_POST['llms_txt'] ?? ''), 'full' => !empty($_POST['llms_full']) ? 1 : 0]);
                flash('success', 'llms.txt salvato.');
                break;
            case 'salva_sitemap':
                seo_salva('sitemap', seo_form_interruttori(['pagine', 'categorie', 'immagini', 'divisa'], 'sitemap'));
                flash('success', 'Sitemap salvata. Google la rilegge da solo; Bing e Yandex la ricevono via IndexNow.');
                break;
            // ---- Schema e AEO
            case 'salva_schema':
                seo_salva('schema', seo_form_interruttori(['article', 'faq', 'breadcrumb', 'website', 'searchaction', 'speakable', 'organization', 'person'], 'schema'));
                $o = $_POST['org'] ?? []; $p = $_POST['persona'] ?? []; $ae = $_POST['aeo'] ?? [];
                if (!preg_match('/^[A-Za-z]+$/', (string)($o['tipo'] ?? ''))) throw new RuntimeException('Il tipo di attivita e una sola parola di schema.org, per esempio ProfessionalService o Organization.');
                seo_salva('org', array_intersect_key($o, seo_predefiniti()['org']));
                seo_salva('persona', array_intersect_key($p, seo_predefiniti()['persona']));
                seo_salva('aeo', ['parole_primo_paragrafo' => max(20, (int)($ae['parole_primo_paragrafo'] ?? 60)), 'tldr_min' => max(10, (int)($ae['tldr_min'] ?? 40)), 'tldr_max' => max(20, (int)($ae['tldr_max'] ?? 60))]);
                flash('success', 'Dati strutturati e AEO salvati.');
                break;
            // ---- Redirect e 404
            case 'redirect_nuovo':
                $da = trim((string)($_POST['da'] ?? '')); $a = trim((string)($_POST['a'] ?? ''));
                if ($da === '' || $a === '') throw new RuntimeException('Servono sia il vecchio indirizzo sia quello nuovo.');
                $codice = in_array((int)($_POST['codice'] ?? 301), [301, 302, 307, 308, 410], true) ? (int)$_POST['codice'] : 301;
                sito_salva_redirect($da, $a, $codice, 'exact', 'pannello');
                if (!empty($_POST['da_404'])) { $st = db()->prepare('DELETE FROM ' . tbl('seo_404') . ' WHERE percorso = ?'); $st->execute([$da]); }
                seo_coda_aggiungi($da, 'redirect');
                flash('success', "Redirect salvato: $da porta a $a.");
                break;
            case 'redirect_stato':
                db()->prepare('UPDATE ' . tbl('redirect') . ' SET attivo = 1 - attivo WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
                flash('success', 'Redirect aggiornato.');
                break;
            case 'redirect_elimina':
                db()->prepare('DELETE FROM ' . tbl('redirect') . ' WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
                flash('success', 'Redirect eliminato.');
                break;
            case 'elimina_404':
                seo_404_togli((int)($_POST['id'] ?? 0));
                flash('success', 'Riga tolta dal monitor.');
                break;
            case 'svuota_404':
                db()->exec('DELETE FROM ' . tbl('seo_404'));
                flash('success', 'Monitor dei 404 svuotato.');
                break;
            default:
                throw new RuntimeException('Azione sconosciuta.');
        }
    } catch (RuntimeException | InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('seo.php: ' . $e->getMessage());
        flash('error', 'Qualcosa non ha funzionato: ' . $e->getMessage());
    }
    header('Location: ' . $dove);
    exit;
}

$cfg = seo_cfg();
$chiave = seo_indexnow_chiave();
$sa = seo_google_sa();
$csrf = csrf_token();
$sel = fn($a, $b) => $a === $b ? 'selected' : '';
$chk = fn($v) => !empty($v) ? 'checked' : '';

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.seo-schede{display:flex;gap:.25rem;flex-wrap:wrap;margin-bottom:1rem;border-bottom:1px solid var(--border)}
.seo-schede a{padding:.55rem .9rem;border-radius:8px 8px 0 0;font-size:.86rem;color:var(--text-muted);text-decoration:none;border:1px solid transparent;border-bottom:0}
.seo-schede a.attiva{background:var(--surface);color:var(--text);border-color:var(--border);font-weight:600}
.seo-griglia{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1rem;align-items:start}
.seo-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem}
.seo-card h3{font-size:.82rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.8rem}
.seo-card .form-group textarea{min-height:90px;font-family:var(--font)}
.seo-card .form-group textarea.codice{font-family:Consolas,Monaco,monospace;font-size:.82rem;min-height:140px}
.seo-nota{font-size:.84rem;color:var(--text-muted);margin-bottom:.8rem;line-height:1.5}
.seo-nota b{color:var(--text)}
.seo-passi{font-size:.86rem;line-height:1.55;padding-left:1.2rem;margin-bottom:.8rem}
.seo-passi li{margin-bottom:.35rem}
.seo-tab{width:100%;border-collapse:collapse;font-size:.84rem}
.seo-tab th,.seo-tab td{padding:.45rem .5rem;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.seo-tab th{font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);cursor:pointer;user-select:none;white-space:nowrap}
.seo-tab th.ord::after{content:" \25BE"}
.seo-tab th.ord.su::after{content:" \25B4"}
.seo-pt{display:inline-block;min-width:2.3em;text-align:center;padding:.15rem .4rem;border-radius:999px;font-weight:700;color:#fff;font-size:.8rem}
.seo-pt.verde{background:#2d7a3a}.seo-pt.giallo{background:#b07a00}.seo-pt.rosso{background:#b3261e}
.seo-ok{color:#2d7a3a}.seo-no{color:#b3261e}.seo-att{color:#b07a00}
.seo-chiave{font-family:Consolas,Monaco,monospace;background:var(--bg);padding:.3rem .5rem;border-radius:6px;word-break:break-all}
.seo-numeri{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
.seo-numeri a{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:.6rem 1rem;text-decoration:none;color:var(--text);font-size:.85rem}
.seo-numeri a b{display:block;font-size:1.3rem}
.seo-numeri a.attivo{border-color:var(--coral)}
.seo-inline{display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap}
.seo-inline .form-group{margin:0;flex:1;min-width:200px}
.seo-interruttori label{display:flex;gap:.5rem;align-items:flex-start;font-size:.86rem;padding:.35rem 0;border-bottom:1px solid var(--border)}
.seo-interruttori label input{margin-top:.2rem}
.seo-interruttori small{display:block;color:var(--text-muted)}
pre.seo-pre{background:#1d1510;color:#f3e9dd;padding:.9rem;border-radius:8px;font-size:.78rem;overflow:auto;max-height:420px;white-space:pre-wrap;word-break:break-word}
.seo-filtri{display:flex;gap:.5rem;flex-wrap:wrap;margin:.5rem 0 1rem}
.seo-filtri input{padding:.45rem .7rem;border:1px solid var(--border);border-radius:8px;font-size:.86rem}
</style>

<nav class="seo-schede">
  <?php foreach ($schede as $k => $v): ?><a href="?scheda=<?= $k ?>" class="<?= $scheda === $k ? 'attiva' : '' ?>"><?= h($v) ?></a><?php endforeach; ?>
</nav>

<?php if ($scheda === 'panoramica'): $righe = seo_panoramica(); $mancanti = array_filter($righe, fn($r) => !$r['tldr'] || !$r['faq'] || !$r['descrizione'] || !$r['immagine']); ?>
<div class="seo-numeri">
  <a href="?scheda=panoramica"><b><?= count($righe) ?></b>contenuti pubblicati</a>
  <a href="?scheda=panoramica"><b><?= count(array_filter($righe, fn($r) => $r['colore'] === 'verde')) ?></b>con punteggio alto (80+)</a>
  <a href="?scheda=panoramica"><b><?= count(array_filter($righe, fn($r) => $r['colore'] === 'rosso')) ?></b>sotto 50</a>
  <a href="?scheda=panoramica#mancanti"><b><?= count($mancanti) ?></b>senza In breve, FAQ, descrizione o immagine</a>
  <a href="?scheda=copertura"><b><?= seo_copertura_numeri()['non_indicizzato'] ?></b>scansionati non indicizzati (Search Console)</a>
</div>
<div class="seo-card">
  <h3>Punteggio SEO di ogni contenuto pubblicato</h3>
  <p class="seo-nota">Il punteggio e lo stesso della scheda di ogni contenuto (riquadro Controllo SEO): keyword nel titolo, nella descrizione, nell'indirizzo, in apertura e nei sottotitoli; densita 1,8% e correlate 1,5%; lunghezza di titolo e descrizione; immagine e alt; link; lunghezza; leggibilita; In breve e domande frequenti. Clicca un'intestazione per ordinare.</p>
  <div class="seo-filtri"><input type="search" id="seo-cerca" placeholder="Filtra per titolo o keyword"></div>
  <div style="overflow-x:auto"><table class="seo-tab" id="seo-panoramica">
    <thead><tr><th data-k="punteggio" data-n="1">Punteggio</th><th data-k="titolo">Titolo</th><th data-k="tipo">Tipo</th><th data-k="keyword">Keyword</th><th data-k="parole" data-n="1">Parole</th><th data-k="robots">Robots</th><th>In breve</th><th>FAQ</th><th>Descr.</th><th>Img</th><th>Apertura</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($righe as $r): ?>
    <tr data-punteggio="<?= $r['punteggio'] ?>" data-titolo="<?= h(mb_strtolower($r['titolo'])) ?>" data-tipo="<?= $r['tipo'] ?>" data-keyword="<?= h(mb_strtolower($r['keyword'])) ?>" data-parole="<?= $r['parole'] ?>" data-robots="<?= $r['robots'] ?>">
      <td><span class="seo-pt <?= $r['colore'] ?>"><?= $r['punteggio'] ?></span></td>
      <td><a href="contenuto.php?tipo=<?= $r['tipo'] ?>&amp;id=<?= $r['id'] ?>"><?= h($r['titolo']) ?></a><?= $r['type'] === 'pillar' ? ' <small style="color:var(--coral)">pillar</small>' : '' ?><br><small class="hint" style="display:inline"><?= h($r['percorso']) ?></small></td>
      <td><?= $r['tipo'] === 'articoli' ? 'Articolo' : 'Pagina' ?></td>
      <td><?= h($r['keyword']) ?: '<span class="seo-att">manca</span>' ?></td>
      <td><?= $r['parole'] ?></td>
      <td><?= $r['robots'] ?></td>
      <td><?= $r['tldr'] ? '<span class="seo-ok">&#10003;</span>' : '<span class="seo-no">&#10007;</span>' ?></td>
      <td><?= $r['faq'] ? '<span class="seo-ok">&#10003;</span>' : '<span class="seo-no">&#10007;</span>' ?></td>
      <td><?= $r['descrizione'] ? '<span class="seo-ok">&#10003;</span>' : '<span class="seo-no">&#10007;</span>' ?></td>
      <td><?= $r['immagine'] ? '<span class="seo-ok">&#10003;</span>' : '<span class="seo-no">&#10007;</span>' ?></td>
      <td title="Primo paragrafo entro <?= (int)$cfg['aeo']['parole_primo_paragrafo'] ?> parole"><?= $r['primo_paragrafo'] ? '<span class="seo-ok">&#10003;</span>' : '<span class="seo-att">lungo</span>' ?></td>
      <td><a href="<?= h(cms_sito_base() . $r['percorso']) ?>" target="_blank" class="btn btn-secondary btn-sm">Vedi</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="hint" id="mancanti" style="margin-top:.8rem">Apertura: il primo paragrafo risponde alla domanda in poche parole (impostazione nella scheda Schema e AEO). Le colonne In breve, FAQ, Descrizione e Immagine dicono se il contenuto ha quello che serve ai motori di risposta.</p>
</div>
<script<?= nonce_attr() ?>>
(function(){
  var t=document.getElementById('seo-panoramica'),tb=t.tBodies[0],righe=Array.prototype.slice.call(tb.rows),dir={};
  t.querySelectorAll('th[data-k]').forEach(function(th){th.addEventListener('click',function(){
    var k=th.dataset.k,num=th.dataset.n==='1';dir[k]=dir[k]==='asc'?'desc':'asc';
    t.querySelectorAll('th').forEach(function(x){x.classList.remove('ord','su')});th.classList.add('ord');if(dir[k]==='asc')th.classList.add('su');
    righe.sort(function(a,b){var x=a.dataset[k],y=b.dataset[k];if(num){x=+x;y=+y}return (x<y?-1:x>y?1:0)*(dir[k]==='asc'?1:-1)});
    righe.forEach(function(r){tb.appendChild(r)});
  })});
  document.getElementById('seo-cerca').addEventListener('input',function(){var q=this.value.toLowerCase();righe.forEach(function(r){r.hidden=q!==''&&r.dataset.titolo.indexOf(q)<0&&r.dataset.keyword.indexOf(q)<0})});
})();
</script>

<?php elseif ($scheda === 'motori'): ?>
<div class="seo-griglia">
  <div class="seo-card">
    <h3>Verifica della proprieta</h3>
    <p class="seo-nota">Ogni motore chiede una prova che il sito e tuo. Qui incolli il codice e il sito lo pubblica da solo (meta tag nel <code>&lt;head&gt;</code> o file in radice). Poi torni nel servizio e premi <b>Verifica</b>.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_verifica">
      <div class="form-group"><label>Google Search Console</label><input type="text" name="verifica[google]" value="<?= h($cfg['verifica']['google']) ?>" placeholder="codice del meta tag, oppure google1234abcd.html" autocomplete="off">
        <div class="hint">Search Console, Aggiungi proprieta, Prefisso URL <code><?= SITO_URL ?>/</code>, metodo <b>Tag HTML</b>: incolla il tag intero o solo il codice. Oppure metodo File HTML: scrivi il nome del file (google....html) e il sito lo serve in radice.</div></div>
      <div class="form-group"><label>Bing Webmaster Tools</label><input type="text" name="verifica[bing]" value="<?= h($cfg['verifica']['bing']) ?>" placeholder="codice del meta tag msvalidate.01" autocomplete="off">
        <div class="hint">Bing Webmaster Tools, Aggiungi sito, opzione <b>Meta tag HTML</b>. In alternativa Bing si puo collegare importando la proprieta da Search Console, senza codice.</div></div>
      <div class="form-group"><label>Bing: contenuto di BingSiteAuth.xml (facoltativo)</label><textarea name="verifica[bing_file]" rows="3" placeholder="&lt;?xml version=&quot;1.0&quot;?&gt;&lt;users&gt;&lt;user&gt;CODICE&lt;/user&gt;&lt;/users&gt;"><?= h($cfg['verifica']['bing_file']) ?></textarea>
        <div class="hint">Solo se scegli il metodo del file XML: il sito risponde a /BingSiteAuth.xml con questo contenuto.</div></div>
      <div class="form-group"><label>Yandex Webmaster</label><input type="text" name="verifica[yandex]" value="<?= h($cfg['verifica']['yandex']) ?>" placeholder="codice del meta tag yandex-verification" autocomplete="off"></div>
      <div class="form-group"><label>Pinterest</label><input type="text" name="verifica[pinterest]" value="<?= h($cfg['verifica']['pinterest']) ?>" placeholder="codice del meta tag p:domain_verify" autocomplete="off"></div>
      <button class="btn btn-primary" type="submit">Salva i codici</button>
    </form>
    <?php if (seo_meta_verifica() !== ''): ?><p class="hint" style="margin-top:.8rem">Adesso nel <code>&lt;head&gt;</code> del sito:<br><code><?= h(str_replace("\n", ' ', seo_meta_verifica())) ?></code></p><?php endif; ?>
  </div>

  <div class="seo-card">
    <h3>IndexNow (Bing, Yandex, Seznam, Naver)</h3>
    <p class="seo-nota">IndexNow avvisa i motori nel momento in cui una pagina esce o cambia: niente attese della scansione. <b>Google non lo usa</b>, e dal 2023 ha anche chiuso il vecchio "ping" della sitemap: con Google contano la sitemap, i link interni e, da qui, la scheda Copertura. Bing e Yandex invece ricevono ogni indirizzo via IndexNow e si passano la notifica a vicenda.</p>
    <?php if ($chiave === ''): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="indexnow_genera"><button class="btn btn-primary" type="submit">Genera la chiave IndexNow</button></form>
      <p class="hint" style="margin-top:.6rem">Il pannello crea una chiave casuale e il sito la pubblica in <code>/chiave.txt</code>: e tutto quello che il protocollo chiede. Non serve registrarsi.</p>
    <?php else: ?>
      <p style="font-size:.86rem">Chiave: <span class="seo-chiave"><?= h($chiave) ?></span><br><a href="<?= SITO_URL ?>/<?= h($chiave) ?>.txt" target="_blank">Controlla il file /<?= h($chiave) ?>.txt</a> (deve mostrare la chiave)</p>
      <form method="post" style="margin-top:.8rem"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_indexnow">
        <div class="seo-interruttori">
          <label><input type="checkbox" name="indexnow[attivo]" value="1" <?= $chk($cfg['indexnow']['attivo']) ?>><span>IndexNow attivo<small>Ogni contenuto pubblicato o modificato va in coda e viene inviato.</small></span></label>
          <label><input type="checkbox" name="indexnow[subito]" value="1" <?= $chk($cfg['indexnow']['subito']) ?>><span>Invio immediato al salvataggio<small>Se spento, la coda parte dal cron (ogni 5 minuti e alle 06:00).</small></span></label>
        </div>
        <div style="margin-top:.6rem;display:flex;gap:.5rem;flex-wrap:wrap"><button class="btn btn-primary btn-sm" type="submit">Salva</button>
          <button class="btn btn-secondary btn-sm" type="submit" name="azione" value="indexnow_genera" data-conferma="Rigenerare la chiave? Quella vecchia smette di valere.">Rigenera la chiave</button></div>
      </form>
      <hr style="border:0;border-top:1px solid var(--border);margin:1rem 0">
      <p style="font-size:.86rem">In coda: <b><?= seo_coda_conta() ?></b> indirizzi.</p>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="indexnow_coda"><button class="btn btn-secondary btn-sm" type="submit">Invia la coda adesso</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="indexnow_sitemap"><button class="btn btn-primary btn-sm" type="submit">Invia tutta la sitemap adesso</button></form>
      </div>
      <form method="post" class="seo-inline" style="margin-top:.8rem"><?= csrf_field() ?><input type="hidden" name="azione" value="indexnow_url">
        <div class="form-group"><label>Un indirizzo da inviare</label><input type="text" name="url" placeholder="/una-pagina-del-sito/"></div><button class="btn btn-secondary btn-sm" type="submit">Invia</button></form>
      <?php $ultimi = seo_log_elenco(5, 'indexnow'); if ($ultimi): ?><p class="hint" style="margin-top:.8rem">Ultimi esiti:</p><ul style="font-size:.8rem;padding-left:1.1rem"><?php foreach ($ultimi as $l): ?><li><?= h($l['quando']) ?> <?= h($l['azione']) ?> <b class="<?= $l['esito'] === 'ok' ? 'seo-ok' : 'seo-no' ?>"><?= h($l['esito']) ?></b> <?= $l['quanti'] ? $l['quanti'] . ' url, ' : '' ?><?= h(mb_substr(strtok((string)$l['dettaglio'], "\n") ?: '', 0, 140)) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="seo-card">
    <h3>Google: service account per Search Console</h3>
    <p class="seo-nota">Per leggere i dati di Search Console da qui (copertura, prestazioni, sitemap) serve un <b>service account</b> di Google Cloud, un utente tecnico con una chiave JSON. La chiave resta nel database del pannello e non si vede piu: qui compare solo l'email.</p>
    <?php if ($sa): ?>
      <p style="font-size:.86rem">Service account: <b><?= h($sa['client_email']) ?></b><br>Progetto: <?= h((string)($sa['project_id'] ?? '')) ?><br>Chiave privata: salvata (mascherata)</p>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.6rem 0">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="google_prova"><button class="btn btn-primary btn-sm" type="submit">Prova il collegamento</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="google_sa_togli"><button class="btn btn-secondary btn-sm" type="submit" data-conferma="Togliere il service account dal database?">Rimuovi</button></form>
      </div>
    <?php endif; ?>
    <details <?= $sa ? '' : 'open' ?>><summary style="cursor:pointer;font-size:.86rem;font-weight:600">Come si crea, passo per passo</summary>
    <ol class="seo-passi">
      <li>Vai su <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a> con l'account Google che ha Search Console. Crea un progetto (nome libero, per esempio "il-mio-sito-seo").</li>
      <li>Menu, <b>API e servizi</b>, <b>Libreria</b>: cerca e abilita <b>Google Search Console API</b> (e, solo se la vorrai usare, <b>Web Search Indexing API</b>).</li>
      <li>Menu, <b>IAM e amministrazione</b>, <b>Service account</b>, <b>Crea service account</b>: nome libero, nessun ruolo, Fine. Apri il service account, scheda <b>Chiavi</b>, <b>Aggiungi chiave</b>, <b>Crea nuova chiave</b>, tipo <b>JSON</b>: il browser scarica un file.</li>
      <li>Apri il file con un editor di testo, copia tutto e incollalo qui sotto. Salva.</li>
      <li>Vai in <a href="https://search.google.com/search-console/users" target="_blank">Search Console, Impostazioni, Utenti e autorizzazioni</a>, <b>Aggiungi utente</b>: incolla l'email del service account (quella che compare qui sopra dopo il salvataggio), autorizzazione <b>Completa</b> (serve per l'ispezione degli URL).</li>
      <li>Torna qui e premi <b>Prova il collegamento</b>.</li>
    </ol></details>
    <form method="post" style="margin-top:.6rem"><?= csrf_field() ?><input type="hidden" name="azione" value="google_sa">
      <div class="form-group"><label><?= $sa ? 'Sostituisci la chiave JSON' : 'Chiave JSON del service account' ?></label><textarea name="sa_json" rows="4" class="codice" placeholder='{"type": "service_account", "project_id": "...", "private_key": "...", "client_email": "..."}'></textarea>
        <div class="hint">Viaggia protetta (base64) fino al pannello e si salva solo nel database. Mai nel repository, mai in chat.</div></div>
      <button class="btn btn-secondary btn-sm" type="submit">Salva la chiave</button>
    </form>
    <form method="post" style="margin-top:1rem"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_google">
      <div class="form-group"><label>Proprieta di Search Console</label><input type="text" name="google[proprieta]" value="<?= h($cfg['google']['proprieta']) ?>">
        <div class="hint">Come e scritta in Search Console: <code><?= SITO_URL ?>/</code> per una proprieta Prefisso URL, oppure <code>sc-domain:<?= h((string)preg_replace('/^www\./', '', (string)parse_url(SITO_URL, PHP_URL_HOST))) ?></code> per una proprieta Dominio.</div></div>
      <div class="seo-interruttori"><label><input type="checkbox" name="google[indexing_attivo]" value="1" <?= $chk($cfg['google']['indexing_attivo']) ?>><span>Google Indexing API al salvataggio (da usare con giudizio)<small>Google la ammette ufficialmente solo per pagine JobPosting e BroadcastEvent. Su un blog funziona a volte, ma fuori dalle regole: per questo e spenta. Quota 200 notifiche al giorno. Richiede la Web Search Indexing API abilitata e il service account come proprietario in Search Console.</small></span></label></div>
      <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.6rem">Salva</button>
    </form>
    <?php if ($sa && !empty($cfg['google']['indexing_attivo'])): ?>
    <form method="post" class="seo-inline" style="margin-top:.8rem"><?= csrf_field() ?><input type="hidden" name="azione" value="google_indexing_url">
      <div class="form-group"><label>Notifica un indirizzo a Google (Indexing API)</label><input type="text" name="url" placeholder="/un-articolo/"></div><button class="btn btn-secondary btn-sm" type="submit">Notifica</button></form>
    <?php endif; ?>
  </div>

  <div class="seo-card">
    <h3>Sitemap e Google</h3>
    <p class="seo-nota">La sitemap e <a href="<?= SITO_URL ?>/sitemap.xml" target="_blank"><?= SITO_URL ?>/sitemap.xml</a>, con <?= count(seo_sitemap_voci()) ?> indirizzi, ricalcolata a ogni richiesta. In Search Console va inviata una volta (Sitemap, Aggiungi nuova sitemap): poi Google la rilegge da solo. Il vecchio indirizzo di "ping" (<code>google.com/ping?sitemap=</code>) e stato chiuso da Google nel 2023 e non va piu usato.</p>
    <?php if ($sa): $sm = seo_gsc_sitemaps(); if (isset($sm['errore'])): ?><p class="seo-no" style="font-size:.84rem"><?= h($sm['errore']) ?></p><?php else: ?>
      <table class="seo-tab"><thead><tr><th>Sitemap in Search Console</th><th>Ultima lettura</th><th>Indirizzi</th><th>Errori</th></tr></thead><tbody>
      <?php foreach ($sm['dati']['sitemap'] ?? [] as $x): ?><tr><td><?= h($x['path'] ?? '') ?></td><td><?= h(substr((string)($x['lastDownloaded'] ?? ''), 0, 16)) ?></td><td><?= (int)(($x['contents'][0]['submitted'] ?? 0)) ?> inviati, <?= (int)(($x['contents'][0]['indexed'] ?? 0)) ?> indicizzati</td><td><?= (int)($x['errors'] ?? 0) ?> errori, <?= (int)($x['warnings'] ?? 0) ?> avvisi</td></tr><?php endforeach; ?>
      <?php if (empty($sm['dati']['sitemap'])): ?><tr><td colspan="4">Nessuna sitemap inviata per questa proprieta.</td></tr><?php endif; ?>
      </tbody></table>
    <?php endif; endif; ?>
  </div>
</div>

<?php elseif ($scheda === 'copertura'): $filtro = (string)($_GET['filtro'] ?? ''); $n = seo_copertura_numeri(); $oggi = seo_gsc_ispezioni_oggi(); ?>
<div class="seo-numeri">
  <?php foreach (['' => 'Tutti i controllati', 'indicizzato' => 'Indicizzati', 'non_indicizzato' => 'Scansionati, non indicizzati', 'escluso' => 'Esclusi o altro'] as $k => $v): ?>
  <a href="?scheda=copertura&amp;filtro=<?= $k ?>" class="<?= $filtro === $k ? 'attivo' : '' ?>"><b><?= $n[$k === '' ? 'tutti' : $k] ?></b><?= $v ?></a>
  <?php endforeach; ?>
  <a href="?scheda=copertura"><b><?= $oggi ?>/<?= SEO_QUOTA_ISPEZIONI ?></b>ispezioni oggi</a>
</div>
<div class="seo-card" style="margin-bottom:1rem">
  <h3>Ispezione degli indirizzi (Search Console)</h3>
  <p class="seo-nota">Il problema storico del sito: pagine note a Google e fuori dall'indice ("Scansionata, al momento non indicizzata"). Qui si chiede a Search Console il verdetto di ogni indirizzo e si salva: verdetto, stato di copertura, ultima scansione, canonical scelto da Google. La quota di Google e di <?= SEO_QUOTA_ISPEZIONI ?> ispezioni al giorno: i lotti partono dagli indirizzi mai controllati o controllati da piu tempo.</p>
  <?php if (!$sa): ?><p class="seo-att" style="font-size:.86rem">Serve il service account Google: scheda <a href="?scheda=motori">Motori di ricerca</a>.</p><?php else: ?>
  <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
    <form method="post" class="seo-inline"><?= csrf_field() ?><input type="hidden" name="azione" value="ispeziona"><div class="form-group"><label>Un indirizzo</label><input type="text" name="url" placeholder="/una-pagina-del-sito/"></div><button class="btn btn-primary btn-sm" type="submit">Ispeziona</button></form>
    <form method="post" class="seo-inline"><?= csrf_field() ?><input type="hidden" name="azione" value="ispeziona_lotto"><div class="form-group" style="min-width:120px"><label>Lotto</label><select name="quanti"><option value="10">10 indirizzi</option><option value="25" selected>25 indirizzi</option><option value="50">50 indirizzi</option><option value="100">100 indirizzi</option></select></div><button class="btn btn-secondary btn-sm" type="submit">Ispeziona i prossimi</button></form>
  </div>
  <p class="hint" style="margin-top:.5rem">Un lotto da 100 dura un paio di minuti: ogni ispezione e una chiamata a Google. Se il server taglia la richiesta, usa lotti da 25.</p>
  <?php endif; ?>
</div>
<div class="seo-card">
  <div style="overflow-x:auto"><table class="seo-tab">
    <thead><tr><th>Indirizzo</th><th>Verdetto</th><th>Copertura</th><th>Ultima scansione di Google</th><th>Canonical scelto da Google</th><th>robots.txt</th><th>Controllato</th></tr></thead>
    <tbody>
    <?php foreach (seo_copertura($filtro) as $r): $p = str_replace(SITO_URL, '', $r['url']); ?>
    <tr><td><a href="<?= h($r['url']) ?>" target="_blank"><?= h($p) ?></a></td>
      <td class="<?= $r['verdetto'] === 'PASS' ? 'seo-ok' : ($r['verdetto'] === 'FAIL' ? 'seo-no' : 'seo-att') ?>"><?= h(seo_copertura_italiano($r['verdetto'])) ?></td>
      <td><?= h(seo_copertura_italiano($r['copertura'])) ?></td>
      <td><?= h((string)$r['ultima_scansione'] ?: 'mai') ?></td>
      <td><?= $r['canonical_google'] === '' ? '' : ($r['canonical_google'] === $r['url'] ? 'questa pagina' : '<span class="seo-att">' . h(str_replace(SITO_URL, '', $r['canonical_google'])) . '</span>') ?></td>
      <td><?= h($r['robots_txt'] === 'ALLOWED' ? 'permesso' : $r['robots_txt']) ?></td>
      <td><?= h((string)$r['controllato']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$n['tutti']): ?><tr><td colspan="7">Nessun indirizzo ancora ispezionato.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>

<?php elseif ($scheda === 'prestazioni'): ?>
<div class="seo-card" style="margin-bottom:1rem">
  <h3>Prestazioni degli ultimi 28 giorni (Search Console)</h3>
  <?php if (!$sa): ?><p class="seo-att" style="font-size:.86rem">Serve il service account Google: scheda <a href="?scheda=motori">Motori di ricerca</a>.</p>
  <?php else: $pd = seo_gsc_prestazioni('date'); ?>
    <?php if (isset($pd['errore'])): ?><p class="seo-no" style="font-size:.86rem"><?= h($pd['errore']) ?></p>
    <?php else: $tc = array_sum(array_column($pd['righe'], 'clic')); $ti = array_sum(array_column($pd['righe'], 'impressioni')); ?>
    <p class="seo-nota">Dal <?= h($pd['inizio']) ?> al <?= h($pd['fine']) ?> (Google consegna i dati con 2-3 giorni di ritardo). <b><?= $tc ?></b> clic, <b><?= $ti ?></b> impressioni, CTR <b><?= $ti ? round($tc / $ti * 100, 1) : 0 ?>%</b>. <?= isset($pd['cache']) ? 'Letto alle ' . h($pd['cache']) . ', in cache per un\'ora.' : '' ?></p>
    <?= seo_grafico_svg($pd['righe']) ?>
    <form method="post" style="margin-top:.8rem"><?= csrf_field() ?><input type="hidden" name="azione" value="prestazioni_aggiorna"><button class="btn btn-secondary btn-sm" type="submit">Rileggi adesso da Search Console</button></form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php if ($sa && !isset($pd['errore'])): ?>
<div class="seo-griglia">
  <?php foreach (['page' => 'Per pagina', 'query' => 'Per ricerca (query)'] as $dim => $nome): $pp = seo_gsc_prestazioni($dim); ?>
  <div class="seo-card"><h3><?= $nome ?></h3>
    <?php if (isset($pp['errore'])): ?><p class="seo-no" style="font-size:.86rem"><?= h($pp['errore']) ?></p><?php else: ?>
    <div style="overflow-x:auto"><table class="seo-tab"><thead><tr><th><?= $dim === 'page' ? 'Pagina' : 'Ricerca' ?></th><th>Clic</th><th>Impr.</th><th>CTR</th><th>Pos.</th></tr></thead><tbody>
    <?php foreach ($pp['righe'] as $r): ?><tr><td><?= $dim === 'page' ? '<a href="' . h($r['chiave']) . '" target="_blank">' . h(str_replace(SITO_URL, '', $r['chiave'])) . '</a>' : h($r['chiave']) ?></td><td><?= $r['clic'] ?></td><td><?= $r['impressioni'] ?></td><td><?= $r['ctr'] ?>%</td><td><?= $r['posizione'] ?></td></tr><?php endforeach; ?>
    <?php if (!$pp['righe']): ?><tr><td colspan="5">Nessun dato.</td></tr><?php endif; ?>
    </tbody></table></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($scheda === 'analisi'): $a = $cfg['analisi']; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_analisi">
<div class="seo-griglia">
  <div class="seo-card">
    <h3>Statistiche e tag</h3>
    <p class="seo-nota">Gli ID si incollano e il sito carica gli script da solo, nel <code>&lt;head&gt;</code> di ogni pagina. Lascia vuoto quello che non usi.</p>
    <div class="form-group"><label>Google Analytics 4: ID di misurazione</label><input type="text" name="analisi[ga4]" value="<?= h($a['ga4']) ?>" placeholder="G-XXXXXXXXXX"><div class="hint">Analytics, Amministrazione, Flussi di dati, il flusso web: "ID misurazione".</div></div>
    <div class="form-group"><label>Google Tag Manager</label><input type="text" name="analisi[gtm]" value="<?= h($a['gtm']) ?>" placeholder="GTM-XXXXXXX"><div class="hint">Se usi Tag Manager, metti GA4 dentro il contenitore e lascia vuoto il campo sopra, per non contare due volte.</div></div>
    <div class="form-group"><label>Meta Pixel (Facebook, Instagram)</label><input type="text" name="analisi[pixel]" value="<?= h($a['pixel']) ?>" placeholder="solo numeri"></div>
    <div class="form-group"><label>Microsoft Clarity</label><input type="text" name="analisi[clarity]" value="<?= h($a['clarity']) ?>" placeholder="ID del progetto"><div class="hint">Mappe di calore e registrazioni, gratis. Clarity, Settings, Overview: "Project ID".</div></div>
  </div>
  <div class="seo-card">
    <h3>Consenso ai cookie</h3>
    <p class="seo-nota">Il sito oggi non ha un banner dei cookie. Statistiche e pixel usano cookie, e in Italia servono il consenso: con la scelta qui sotto il sito mostra un banner leggero fatto in casa (Accetta / Rifiuta, scelta ricordata nel browser) e carica i tag <b>solo dopo il si</b>. Prima del consenso non parte niente.</p>
    <div class="seo-interruttori">
      <label><input type="radio" name="analisi[consenso]" value="banner" <?= $a['consenso'] !== 'subito' ? 'checked' : '' ?>><span>Carica i tag solo dopo il consenso (banner del sito)<small>Consigliato. Il banner compare solo se c'e almeno un tag.</small></span></label>
      <label><input type="radio" name="analisi[consenso]" value="subito" <?= $a['consenso'] === 'subito' ? 'checked' : '' ?>><span>Carica subito, senza banner<small>Solo se il consenso lo raccoglie un altro strumento, che poi chiama <code>seoTagAvvia()</code>.</small></span></label>
    </div>
    <div class="form-group" style="margin-top:.8rem"><label>Testo del banner</label><textarea name="analisi[banner_testo]" rows="2"><?= h($a['banner_testo']) ?></textarea></div>
  </div>
  <div class="seo-card">
    <h3>Codice personalizzato</h3>
    <p class="seo-nota">Per tag che non stanno nelle caselle sopra. Viaggia protetto fino al pannello (ModSecurity non lo ferma). Il codice entra cosi com'e: un errore qui si vede su tutto il sito.</p>
    <p class="seo-nota" style="color:#b45309"><strong>Attenzione:</strong> questo e HTML grezzo, inserito in ogni pagina senza controlli. Mettici solo codice di cui conosci la provenienza. Chi puo scriverlo si decide in Impostazioni, Sicurezza (voce "Codice libero head/body"): se e spento, il codice resta salvato ma non esce sul sito.</p>
    <div class="form-group"><label>Nel &lt;head&gt;</label><textarea name="analisi[codice_head]" class="codice" rows="4"><?= h($a['codice_head']) ?></textarea></div>
    <div class="form-group"><label>Subito dopo &lt;body&gt;</label><textarea name="analisi[codice_body_inizio]" class="codice" rows="3"><?= h($a['codice_body_inizio']) ?></textarea></div>
    <div class="form-group"><label>Prima di &lt;/body&gt;</label><textarea name="analisi[codice_body_fine]" class="codice" rows="3"><?= h($a['codice_body_fine']) ?></textarea></div>
  </div>
</div>
<div style="margin-top:1rem"><button class="btn btn-primary" type="submit">Salva analisi e tag</button></div>
</form>

<?php elseif ($scheda === 'globale'): $t = $cfg['titolo']; $m = $cfg['meta']; $rb = $cfg['robots']; ?>
<div class="seo-griglia">
  <div class="seo-card">
    <h3>Titoli e meta</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_titoli">
      <p class="seo-nota">Segnaposto: <code>%titolo%</code>, <code>%sep%</code>, <code>%sito%</code>. Con i valori di partenza i titoli restano quelli di oggi ("Titolo - <?= h(SITO_NOME) ?>"). Il titolo per Google scritto in un contenuto vince sempre sul formato.</p>
      <div class="seo-inline"><div class="form-group"><label>Separatore</label><input type="text" name="titolo[sep]" value="<?= h($t['sep']) ?>" maxlength="5"></div><div class="form-group" style="flex:3"><label>Nome del sito nei titoli</label><input type="text" name="titolo[sito]" value="<?= h($t['sito']) ?>"></div></div>
      <?php foreach ($seoTitoli as $k => $nome): ?>
      <div class="form-group" style="margin-top:.8rem"><label><?= $nome ?></label><input type="text" name="titolo[<?= $k ?>]" value="<?= h($t[$k] ?? '%titolo% %sep% %sito%') ?>"></div>
      <?php endforeach; ?>
      <div class="form-group"><label>Descrizione predefinita</label><textarea name="meta[descrizione]" rows="2" maxlength="300"><?= h($m['descrizione']) ?></textarea><div class="hint">Per le pagine senza descrizione ne estratto. Vuota = nessun meta description (Google sceglie da solo).</div></div>
      <div class="form-group"><label>Immagine social predefinita (Open Graph)</label><input type="text" name="meta[og_immagine]" value="<?= h($m['og_immagine']) ?>" placeholder="https://..."><div class="hint">Per i contenuti senza immagine in evidenza. La card Twitter resta summary_large_image.</div></div>
      <div class="form-group"><label>Account X / Twitter del sito</label><input type="text" name="meta[twitter_account]" value="<?= h($m['twitter_account']) ?>" placeholder="nomeaccount"></div>
      <div class="seo-interruttori"><label><input type="checkbox" name="meta[robots_avanzati]" value="1" <?= $chk($m['robots_avanzati']) ?>><span>Direttive avanzate nel meta robots<small><code>max-snippet:-1, max-image-preview:large, max-video-preview:-1</code>: Google puo mostrare snippet lunghi e immagini grandi (anche nelle risposte IA). Oggi acceso.</small></span></label></div>
      <button class="btn btn-primary" type="submit" style="margin-top:.8rem">Salva titoli e meta</button>
    </form>
  </div>

  <div class="seo-card">
    <h3>Indicizzazione per tipo</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_robots">
      <p class="seo-nota"><b>Automatica</b> è la regola di partenza: articoli e pagine index (salvo scelta nel contenuto), categorie vuote o nascoste da un modulo noindex, tag noindex, archivi per data noindex, ricerca noindex. Forzare un valore qui vale per tutto il tipo: fallo solo con una ragione.</p>
      <?php foreach ($seoRobots as $k => $nome): ?>
      <div class="form-group"><label><?= $nome ?></label><select name="robots[<?= $k ?>]"><option value="auto" <?= $sel($rb[$k] ?? 'auto', 'auto') ?>>Automatica (come oggi)</option><option value="index" <?= $sel($rb[$k] ?? 'auto', 'index') ?>>Sempre index</option><option value="noindex" <?= $sel($rb[$k] ?? 'auto', 'noindex') ?>>Sempre noindex</option></select></div>
      <?php endforeach; ?>
      <p class="hint">Canonical: sempre sul dominio con www (<?= SITO_URL ?>), come oggi. Il singolo contenuto si imposta nella sua scheda, voce Indicizzazione.</p>
      <button class="btn btn-primary" type="submit">Salva</button>
    </form>
  </div>

  <div class="seo-card">
    <h3>robots.txt</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_robots_txt">
      <p class="seo-nota">Il predefinito e quello di oggi (pannello, cron, log, api, feed e ricerca chiusi; due sitemap dichiarate). Se scrivi qui sotto, il sito serve esattamente il tuo testo. Vuoto = predefinito piu i crawler AI fermati qui sotto.</p>
      <div class="form-group"><label>robots.txt personalizzato</label><textarea name="robots_txt" class="codice" rows="10" placeholder="<?= h(seo_robots_txt_predefinito()) ?>"><?= h($cfg['robots_txt']['personale']) ?></textarea></div>
      <details><summary style="cursor:pointer;font-size:.84rem">Anteprima di quello che il sito serve adesso</summary><pre class="seo-pre"><?= h(seo_robots_txt()) ?></pre></details>
      <h3 style="margin-top:1rem">Crawler delle intelligenze artificiali</h3>
      <p class="seo-nota">Ogni interruttore aggiunge al robots.txt predefinito un blocco <code>User-agent: ... Disallow: /</code>. Per l'AEO (essere citati dalle risposte delle IA) conviene <b>lasciare passare</b> i bot di ricerca (OAI-SearchBot, PerplexityBot) e decidere con calma quelli di addestramento. Il robots.txt e una richiesta: i bot seri la rispettano.</p>
      <div class="seo-interruttori">
      <?php foreach (SEO_CRAWLER_AI as $bot => [$chi, $cosa]): ?>
        <label><input type="checkbox" name="ai[<?= h($bot) ?>]" value="1" <?= $chk($cfg['robots_txt']['ai'][$bot] ?? 0) ?>><span>Blocca <b><?= h($bot) ?></b> (<?= h($chi) ?>)<small><?= h($cosa) ?></small></span></label>
      <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:.5rem;margin-top:.8rem"><button class="btn btn-primary" type="submit">Salva robots.txt</button><button class="btn btn-secondary" type="submit" name="ripristina" value="1" data-conferma="Tornare al robots.txt predefinito?">Ripristina il predefinito</button></div>
    </form>
  </div>

  <div class="seo-card">
    <h3>Sitemap</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_sitemap">
      <div class="seo-interruttori">
        <label><input type="checkbox" name="sitemap[pagine]" value="1" <?= $chk($cfg['sitemap']['pagine']) ?>><span>Pagine (oltre agli articoli)</span></label>
        <label><input type="checkbox" name="sitemap[categorie]" value="1" <?= $chk($cfg['sitemap']['categorie']) ?>><span>Categorie indicizzabili con articoli</span></label>
        <label><input type="checkbox" name="sitemap[immagini]" value="1" <?= $chk($cfg['sitemap']['immagini']) ?>><span>Immagine in evidenza di ogni contenuto (sitemap immagini)</span></label>
        <label><input type="checkbox" name="sitemap[divisa]" value="1" <?= $chk($cfg['sitemap']['divisa']) ?>><span>Sitemap divise per tipo con indice<small>/sitemap.xml diventa un indice che punta a sitemap-articoli.xml, sitemap-pagine.xml, sitemap-categorie.xml. Oggi: una sola sitemap.</small></span></label>
      </div>
      <p class="hint" style="margin-top:.6rem">Adesso: <?= count(seo_sitemap_voci()) ?> indirizzi, aggiornata a ogni richiesta (ultima modifica <?= h(substr((string)(seo_sitemap_voci()[0]['lastmod'] ?? ''), 0, 10)) ?>). Dentro vanno solo pagine che rispondono 200 e sono index; i redirect restano fuori.</p>
      <button class="btn btn-primary" type="submit" style="margin-top:.6rem">Salva sitemap</button>
    </form>
  </div>
</div>

<?php elseif ($scheda === 'schema'): $o = $cfg['org']; $p = $cfg['persona']; $s = $cfg['schema']; $ae = $cfg['aeo']; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_schema">
<div class="seo-griglia">
  <div class="seo-card">
    <h3>Organizzazione (JSON-LD)</h3>
    <p class="seo-nota">Il nodo che ogni pagina dichiara su chi pubblica il sito. I valori di partenza sono quelli gia in uso.</p>
    <div class="form-group"><label>Tipo di attivita (schema.org)</label><input type="text" name="org[tipo]" value="<?= h($o['tipo']) ?>"><div class="hint">ProfessionalService (oggi), Organization, LocalBusiness, Person...</div></div>
    <div class="form-group"><label>Nome</label><input type="text" name="org[nome]" value="<?= h($o['nome']) ?>"></div>
    <div class="form-group"><label>Nome del sito (WebSite)</label><input type="text" name="org[nome_sito]" value="<?= h($o['nome_sito']) ?>"></div>
    <div class="form-group"><label>Logo (indirizzo)</label><input type="text" name="org[logo]" value="<?= h($o['logo']) ?>"></div>
    <div class="form-group"><label>Immagine (indirizzo)</label><input type="text" name="org[immagine]" value="<?= h($o['immagine']) ?>"></div>
    <div class="form-group"><label>Descrizione</label><textarea name="org[descrizione]" rows="2"><?= h($o['descrizione']) ?></textarea></div>
    <div class="seo-inline"><div class="form-group"><label>Lingua</label><input type="text" name="org[lingua]" value="<?= h($o['lingua']) ?>"></div><div class="form-group"><label>Area servita</label><input type="text" name="org[area]" value="<?= h($o['area']) ?>"></div></div>
    <div class="form-group" style="margin-top:.8rem"><label>Tipo di servizio</label><input type="text" name="org[servizio]" value="<?= h($o['servizio']) ?>"></div>
    <div class="seo-inline"><div class="form-group"><label>Telefono (facoltativo)</label><input type="text" name="org[telefono]" value="<?= h($o['telefono']) ?>"></div><div class="form-group"><label>Email (facoltativa)</label><input type="text" name="org[email]" value="<?= h($o['email']) ?>"></div></div>
    <div class="form-group" style="margin-top:.8rem"><label>Indirizzo (facoltativo)</label><input type="text" name="org[indirizzo]" value="<?= h($o['indirizzo']) ?>"></div>
    <div class="form-group"><label>Profili social dell'attivita (sameAs, uno per riga)</label><textarea name="org[sameas]" rows="3"><?= h($o['sameas']) ?></textarea></div>
  </div>
  <div class="seo-card">
    <h3>Persona (E-E-A-T)</h3>
    <p class="seo-nota">L'autore degli articoli. Piu e preciso, piu i motori capiscono chi parla e con quale competenza.</p>
    <div class="form-group"><label>Nome</label><input type="text" name="persona[nome]" value="<?= h($p['nome']) ?>"></div>
    <div class="form-group"><label>Ruolo</label><input type="text" name="persona[ruolo]" value="<?= h($p['ruolo']) ?>"></div>
    <div class="form-group"><label>Foto (indirizzo)</label><input type="text" name="persona[immagine]" value="<?= h($p['immagine']) ?>"></div>
    <div class="form-group"><label>Descrizione</label><textarea name="persona[descrizione]" rows="3"><?= h($p['descrizione']) ?></textarea></div>
    <div class="form-group"><label>Competenze (knowsAbout, una per riga)</label><textarea name="persona[conosce]" rows="5"><?= h($p['conosce']) ?></textarea></div>
    <div class="seo-inline"><div class="form-group"><label>Certificazione</label><input type="text" name="persona[credenziale]" value="<?= h($p['credenziale']) ?>"></div><div class="form-group"><label>Rilasciata da</label><input type="text" name="persona[credenziale_ente]" value="<?= h($p['credenziale_ente']) ?>"></div></div>
    <div class="form-group" style="margin-top:.8rem"><label>Profili personali verificati (sameAs, uno per riga)</label><textarea name="persona[sameas]" rows="3"><?= h($p['sameas']) ?></textarea><div class="hint">Solo profili veri e tuoi: Instagram, TikTok, LinkedIn, YouTube...</div></div>
  </div>
  <div class="seo-card">
    <h3>Cosa emette ogni pagina</h3>
    <div class="seo-interruttori">
      <label><input type="checkbox" name="schema[website]" value="1" <?= $chk($s['website']) ?>><span>WebSite<small>Il nodo del sito, con nome e lingua.</small></span></label>
      <label><input type="checkbox" name="schema[searchaction]" value="1" <?= $chk($s['searchaction']) ?>><span>SearchAction nel WebSite<small>Dice a Google che il sito ha una ricerca interna (/?s=). Oggi spento.</small></span></label>
      <label><input type="checkbox" name="schema[organization]" value="1" <?= $chk($s['organization']) ?>><span>Organizzazione</span></label>
      <label><input type="checkbox" name="schema[person]" value="1" <?= $chk($s['person']) ?>><span>Persona (autore)</span></label>
      <label><input type="checkbox" name="schema[article]" value="1" <?= $chk($s['article']) ?>><span>Article (BlogPosting) sugli articoli<small>Titolo, date, autore, immagine, keyword, abstract = In breve, numero di parole.</small></span></label>
      <label><input type="checkbox" name="schema[faq]" value="1" <?= $chk($s['faq']) ?>><span>FAQPage dove ci sono domande frequenti</span></label>
      <label><input type="checkbox" name="schema[breadcrumb]" value="1" <?= $chk($s['breadcrumb']) ?>><span>BreadcrumbList (Home, Blog, categoria, articolo)</span></label>
      <label><input type="checkbox" name="schema[speakable]" value="1" <?= $chk($s['speakable']) ?>><span>Speakable sulla WebPage<small>Indica agli assistenti vocali e alle IA le parti da leggere: il riquadro In breve e il titolo. Oggi spento.</small></span></label>
    </div>
    <p class="hint" style="margin-top:.6rem">HowTo: non c'e un interruttore perche nessun contenuto ha passi strutturati come dati; se un giorno servira, si aggiunge un widget "passi" con i dati e il nodo si emette da li.</p>
    <h3 style="margin-top:1rem">Risposte dirette (AEO)</h3>
    <p class="seo-nota">I motori di risposta (ChatGPT, Perplexity, le AI Overview di Google) pescano la frase che risponde subito. Nella scheda di ogni contenuto ci sono gia <b>In breve</b> (diventa <code>abstract</code> nell'Article e il riquadro in cima) e le <b>Domande frequenti</b> (FAQPage). Qui le soglie dei controlli.</p>
    <div class="seo-inline"><div class="form-group"><label>Primo paragrafo: massimo parole</label><input type="number" name="aeo[parole_primo_paragrafo]" value="<?= (int)$ae['parole_primo_paragrafo'] ?>" min="20"></div><div class="form-group"><label>In breve: da</label><input type="number" name="aeo[tldr_min]" value="<?= (int)$ae['tldr_min'] ?>" min="10"></div><div class="form-group"><label>a parole</label><input type="number" name="aeo[tldr_max]" value="<?= (int)$ae['tldr_max'] ?>" min="20"></div></div>
    <div style="margin-top:1rem"><button class="btn btn-primary" type="submit">Salva schema e AEO</button></div>
  </div>
</div>
</form>
<div class="seo-griglia" style="margin-top:1rem">
  <div class="seo-card">
    <h3>Anteprima del JSON-LD di un indirizzo</h3>
    <div class="seo-inline"><div class="form-group"><label>Indirizzo del sito</label><input type="text" id="seo-jsonld-url" value="/" placeholder="/una-pagina-del-sito/"></div><button class="btn btn-primary btn-sm" type="button" id="seo-jsonld-vai">Mostra</button></div>
    <p class="hint" style="margin:.5rem 0" id="seo-jsonld-esito"></p>
    <pre class="seo-pre" id="seo-jsonld-pre" hidden></pre>
    <p class="hint">Verifica esterna: <a id="seo-rrt" href="https://search.google.com/test/rich-results?url=<?= rawurlencode(SITO_URL . '/') ?>" target="_blank">Test dei risultati avanzati di Google</a> &middot; <a id="seo-sv" href="https://validator.schema.org/#url=<?= rawurlencode(SITO_URL . '/') ?>" target="_blank">Schema Markup Validator</a> (aprono l'indirizzo pubblico: funzionano sul sito vivo).</p>
  </div>
  <div class="seo-card">
    <h3>llms.txt e llms-full.txt (AEO)</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="salva_llms">
      <p class="seo-nota"><a href="<?= SITO_URL ?>/llms.txt" target="_blank">/llms.txt</a> e l'indice del sito per le IA (formato llmstxt.org): servizi, pillar, articoli, glossario, calcolato dal database. Se scrivi qui sotto, il sito serve il tuo testo. <a href="<?= SITO_URL ?>/llms-full.txt" target="_blank">/llms-full.txt</a> aggiunge il testo intero dei pillar e degli articoli indicizzati.</p>
      <div class="form-group"><label>llms.txt personalizzato (vuoto = calcolato)</label><textarea name="llms_txt" class="codice" rows="8" placeholder="<?= h(mb_substr(extra_llms(), 0, 600)) ?>..."><?= h($cfg['llms']['personale']) ?></textarea></div>
      <div class="seo-interruttori"><label><input type="checkbox" name="llms_full" value="1" <?= $chk($cfg['llms']['full']) ?>><span>Servi anche /llms-full.txt con i testi interi</span></label></div>
      <div style="display:flex;gap:.5rem;margin-top:.8rem"><button class="btn btn-primary btn-sm" type="submit">Salva</button><button class="btn btn-secondary btn-sm" type="submit" name="ripristina" value="1">Torna al calcolato</button></div>
    </form>
  </div>
</div>
<script<?= nonce_attr() ?>>
(function(){
  var u=document.getElementById('seo-jsonld-url'),pre=document.getElementById('seo-jsonld-pre'),es=document.getElementById('seo-jsonld-esito');
  document.getElementById('seo-jsonld-vai').addEventListener('click',function(){
    es.textContent='Calcolo...';pre.hidden=true;
    var fd=new FormData();fd.append('_csrf','<?= h($csrf) ?>');fd.append('azione','jsonld');fd.append('url',u.value);
    fetch('seo-api.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
      if(j.errore){es.textContent=j.errore;es.className='hint seo-no';return}
      es.className='hint seo-ok';es.textContent='JSON valido: '+j.nodi+' nodi ('+j.tipi.join(', ')+'). '+(j.avvisi.length?j.avvisi.join(' '):'');
      pre.textContent=j.testo;pre.hidden=false;
      var abs='<?= SITO_URL ?>'+(u.value.indexOf('/')===0?u.value:'/'+u.value);
      document.getElementById('seo-rrt').href='https://search.google.com/test/rich-results?url='+encodeURIComponent(abs);
      document.getElementById('seo-sv').href='https://validator.schema.org/#url='+encodeURIComponent(abs);
    }).catch(function(e){es.textContent='Risposta non leggibile: '+e;es.className='hint seo-no'});
  });
})();
</script>

<?php elseif ($scheda === 'redirect'): $q = seo_404_elenco(300); ?>
<div class="seo-griglia">
  <div class="seo-card">
    <h3>Monitor dei 404</h3>
    <p class="seo-nota">Ogni indirizzo che il sito non trova finisce qui, con quante volte e da dove arrivavano. Sonde e file di sistema restano fuori. Un 404 che arriva da un link vero merita un redirect: il pulsante lo prepara.</p>
    <?php if ($q): ?>
    <form method="post" style="margin-bottom:.6rem"><?= csrf_field() ?><input type="hidden" name="azione" value="svuota_404"><button class="btn btn-secondary btn-sm" type="submit" data-conferma="Svuotare il monitor?">Svuota il monitor</button></form>
    <div style="overflow-x:auto"><table class="seo-tab"><thead><tr><th>Indirizzo</th><th>Volte</th><th>Da dove</th><th>Ultimo</th><th></th></tr></thead><tbody>
    <?php foreach ($q as $r): ?>
    <tr><td><?= h($r['percorso']) ?></td><td><?= (int)$r['conteggio'] ?></td><td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($r['referer']) ?>"><?= h($r['referer']) ?: '<span class="hint" style="display:inline">diretto</span>' ?></td><td><?= h(substr($r['ultimo'], 0, 16)) ?></td>
      <td style="white-space:nowrap"><button type="button" class="btn btn-primary btn-sm" data-al-clic="seoRedirectDa(<?= h(json_encode($r['percorso'])) ?>)">Crea redirect</button>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="azione" value="elimina_404"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Togli</button></form></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="hint">Nessun 404 registrato finora.</p><?php endif; ?>
  </div>
  <div class="seo-card">
    <h3>Nuovo redirect</h3>
    <p class="seo-nota">Stessa tabella dei redirect dei pacchetti (Aggiornamenti) e di quelli che nascono da soli quando cambia l'indirizzo di un contenuto: una sola lista, nessun doppione.</p>
    <form method="post" id="seo-redirect-form"><?= csrf_field() ?><input type="hidden" name="azione" value="redirect_nuovo"><input type="hidden" name="da_404" id="seo-da-404" value="">
      <div class="form-group"><label>Vecchio indirizzo</label><input type="text" name="da" id="seo-redirect-da" placeholder="/vecchio-articolo/" required></div>
      <div class="form-group"><label>Nuovo indirizzo</label><input type="text" name="a" placeholder="/articolo-nuovo/" required></div>
      <div class="form-group"><label>Codice</label><select name="codice"><option value="301">301, spostato per sempre (il normale)</option><option value="302">302, temporaneo</option><option value="307">307, temporaneo (stesso metodo)</option><option value="308">308, permanente (stesso metodo)</option><option value="410">410, pagina eliminata</option></select></div>
      <button class="btn btn-primary" type="submit">Salva il redirect</button>
    </form>
    <script<?= nonce_attr() ?>>function seoRedirectDa(p){document.getElementById('seo-redirect-da').value=p;document.getElementById('seo-da-404').value='1';document.querySelector('#seo-redirect-form [name=a]').focus();}</script>
  </div>
</div>
<div class="seo-card" style="margin-top:1rem">
  <h3>Redirect attivi e spenti</h3>
  <div style="overflow-x:auto"><table class="seo-tab"><thead><tr><th>Da</th><th>A</th><th>Codice</th><th>Confronto</th><th>Origine</th><th>Stato</th><th></th></tr></thead><tbody>
  <?php foreach (seo_redirect_elenco() as $r): ?>
  <tr><td><?= h($r['da']) ?></td><td><?= h($r['a']) ?></td><td><?= (int)$r['codice'] ?></td><td><?= h($r['confronto']) ?></td><td><?= h($r['origine']) ?></td><td><?= $r['attivo'] ? '<span class="seo-ok">attivo</span>' : '<span class="seo-att">spento</span>' ?></td>
    <td style="white-space:nowrap"><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="azione" value="redirect_stato"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= $r['attivo'] ? 'Spegni' : 'Accendi' ?></button></form>
    <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="azione" value="redirect_elimina"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit" data-conferma="Eliminare il redirect?">Elimina</button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>

<?php elseif ($scheda === 'log'): ?>
<div class="seo-card">
  <h3>Registro delle chiamate (IndexNow, Google)</h3>
  <div style="overflow-x:auto"><table class="seo-tab"><thead><tr><th>Quando</th><th>Servizio</th><th>Azione</th><th>Esito</th><th>Codice</th><th>Quanti</th><th>Dettaglio</th></tr></thead><tbody>
  <?php foreach (seo_log_elenco(200) as $l): ?>
  <tr><td><?= h($l['quando']) ?></td><td><?= h($l['servizio']) ?></td><td><?= h($l['azione']) ?></td><td class="<?= $l['esito'] === 'ok' ? 'seo-ok' : 'seo-no' ?>"><?= h($l['esito']) ?></td><td><?= (int)$l['codice'] ?></td><td><?= (int)$l['quanti'] ?></td><td><details><summary><?= h(mb_substr(strtok((string)$l['dettaglio'], "\n") ?: '', 0, 120)) ?></summary><pre class="seo-pre"><?= h((string)$l['dettaglio']) ?></pre></details></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
