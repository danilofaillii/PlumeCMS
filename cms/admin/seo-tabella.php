<?php
/**
 * SEO e AEO > Tabella SEO (2.29.0): tutto il sito in una tabella, una riga per contenuto (articoli, pagine, i tipi dei moduli,
 * bozze comprese): titolo, titolo per Google, descrizione, lunghezze, robots, un semaforo per il titolo e uno per la descrizione,
 * le immagini senza alt. Titolo per Google e descrizione si correggono nella riga e si salvano senza ricaricare
 * (admin/assets/seo-tabella.js -> seo-api.php, azione tabella_salva -> includes/seo-tabella.php). Filtri: solo problemi, tipo,
 * ricerca; ordine per semaforo (i problemi in cima), titolo o tipo. Al telefono la tabella diventa schede (pannello.js).
 * Anche il redattore (è in AUTH_REDATTORE_PAGINE): salva i contenuti come dalla scheda, non vede la configurazione SEO.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_once dirname(__DIR__) . '/includes/editor-controlli.php';
require_once dirname(__DIR__) . '/includes/editor.php';
require_once dirname(__DIR__) . '/includes/seo-tabella.php';
require_login();
sito_schema_aggiorna();
editor_carica_widget_esterni();
$adminTitle = 'Tabella SEO';
$righe = seo_tabella_righe();
$conProblemi = count(array_filter($righe, fn($r) => $r['problemi'] > 0));
$senzaAlt = array_sum(array_column($righe, 'alt_mancanti'));
$tipi = [];
foreach ($righe as $r) $tipi[$r['tipo']] = contenuti_tipi()[$r['tipo']]['Tanti'] ?? $r['tipo'];
$csrf = csrf_token();
$segno = ['verde' => '&#10003;', 'giallo' => '!', 'rosso' => '&#10007;'];
$parola = ['verde' => 'a posto', 'giallo' => 'da migliorare', 'rosso' => 'da sistemare'];
$sem = fn(array $s, string $cosa) => '<span class="st-sem st-' . $s['colore'] . '" data-sem="' . $s['colore'] . '"><span class="st-segno" aria-hidden="true">' . $segno[$s['colore']] . '</span><span class="sr-solo">' . $cosa . ' ' . $parola[$s['colore']] . ': </span>' . h($s['testo']) . '</span>';

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.st-sotto{font-size:.86rem;color:var(--text-muted);margin:0 0 .9rem;line-height:1.5}
.st-sotto b{color:var(--text)}
.st-numeri{display:flex;gap:.8rem;flex-wrap:wrap;margin:0 0 1rem}
.st-numeri span{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:.5rem .9rem;font-size:.85rem}
.st-numeri span b{display:block;font-size:1.25rem}
.st-filtri{display:flex;gap:.6rem 1rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem}
.st-filtri .form-group{margin:0;min-width:160px}
.st-filtri .form-group input,.st-filtri .form-group select{width:100%}
.st-filtri .st-spunta{display:flex;align-items:center;gap:.4rem;font-size:.9rem;padding:.55rem 0}
.st-filtri .st-spunta input{width:1.1rem;height:1.1rem}
table.st-tab{width:100%;border-collapse:collapse;font-size:.86rem}
.st-tab th,.st-tab td{padding:.55rem .5rem;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.st-tab th{font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);white-space:nowrap}
.st-tab td.st-cont{min-width:170px;max-width:260px}
.st-tab td.st-campo{min-width:220px}
.st-tab input[type=text],.st-tab textarea{width:100%;padding:.4rem .55rem;border:1px solid var(--bordo-campo);border-radius:8px;font:inherit;font-size:.85rem;background:var(--campo);color:var(--text)}
.st-tab textarea{min-height:58px;resize:vertical;font-family:var(--font)}
.st-tab input:focus-visible,.st-tab textarea:focus-visible{outline:2px solid var(--coral);outline-offset:1px}
.st-tab .st-cont a{font-weight:600}
.st-tab .st-meta{display:block;font-size:.76rem;color:var(--text-muted);margin-top:.15rem;overflow-wrap:anywhere}
.st-tab .st-azioni{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.35rem}
.st-tab .st-azioni a{font-size:.74rem;padding:.15rem .5rem}
.st-sem{display:block;font-size:.78rem;font-weight:600;margin-bottom:.3rem}
.st-sem .st-segno{display:inline-block;width:1.1em;font-weight:700}
.st-verde{color:var(--successo-testo)}.st-giallo{color:var(--attenzione-testo)}.st-rosso{color:var(--errore-testo)}
.st-stato{display:inline-block;font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:.08rem .45rem;border-radius:999px;border:1px solid var(--border);color:var(--text-muted);margin-left:.3rem;vertical-align:middle}
.st-stato.pubblicato{border-color:var(--successo-bordo);color:var(--successo-testo);background:var(--sage-light)}
.st-esito{display:block;font-size:.76rem;margin-top:.25rem;min-height:1em;color:var(--text-muted)}
.st-esito.st-ok{color:var(--successo-testo)}.st-esito.st-no{color:var(--errore-testo)}
.st-tab tr[hidden]{display:none}
.st-alt a{font-weight:600;color:var(--attenzione-testo)}
.st-vuoto{padding:1rem;text-align:center;color:var(--text-muted)}
[hidden]{display:none!important}
@media (max-width:640px){.st-tab td.st-cont,.st-tab td.st-campo{max-width:none;min-width:0}}
</style>

<?php if (is_admin()): ?>
<nav class="imp-schede" aria-label="Schede di SEO e AEO">
  <?php foreach (['panoramica' => 'Panoramica', 'motori' => 'Motori di ricerca', 'copertura' => 'Copertura', 'prestazioni' => 'Prestazioni', 'analisi' => 'Analisi e tag', 'globale' => 'Globale SEO', 'schema' => 'Schema e AEO', 'redirect' => 'Redirect e 404', 'log' => 'Registro'] as $k => $v): ?><a href="seo.php?scheda=<?= $k ?>"><?= h($v) ?></a><?php endforeach; ?><a href="sedi.php">Sedi</a><a href="seo-tabella.php" class="attiva" aria-current="page">Tabella SEO</a>
</nav>
<?php endif; ?>

<div class="form-card" id="st-card">
  <h3>Tabella SEO di tutto il sito</h3>
  <p class="st-sotto">Ogni contenuto con il <b>titolo per Google</b> (quello che esce nei risultati: vuoto vuol dire il titolo con il formato di Globale SEO) e la <b>descrizione</b> (le due righe sotto il titolo). Correggi direttamente nelle caselle: si salvano quando esci dalla casella, e il semaforo si aggiorna subito. Verde = a posto, giallo = da migliorare (troppo corto o lungo), rosso = da sistemare (manca, troppo lungo, o uguale a un altro contenuto: Google non sa quale mostrare).</p>
  <div class="st-numeri">
    <span><b id="st-n-tutti"><?= count($righe) ?></b>contenuti</span>
    <span><b id="st-n-problemi"><?= $conProblemi ?></b>con qualcosa da sistemare</span>
    <span><b id="st-n-alt"><?= $senzaAlt ?></b>immagini senza alt <a href="media.php?scheda=alt">(Media &gt; Senza alt)</a></span>
  </div>
  <?php if (!$righe): ?>
  <div class="empty-state">Ancora nessun contenuto: scrivi il primo articolo da <a href="contenuti.php?tipo=articoli">Articoli</a> o la prima pagina da <a href="contenuti.php?tipo=pagine">Pagine</a>.</div>
  <?php else: ?>
  <form class="st-filtri" id="st-filtri" autocomplete="off">
    <div class="form-group"><label for="st-cerca">Cerca</label><input type="search" id="st-cerca" placeholder="per esempio: contatti"></div>
    <div class="form-group"><label for="st-tipo">Tipo</label><select id="st-tipo"><option value="">Tutti</option><?php foreach ($tipi as $k => $n): ?><option value="<?= h($k) ?>"><?= h($n) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="st-ordina">Ordina per</label><select id="st-ordina"><option value="semaforo">Semaforo (prima i problemi)</option><option value="titolo">Titolo</option><option value="tipo">Tipo</option></select></div>
    <label class="st-spunta"><input type="checkbox" id="st-solo-problemi"> Solo i contenuti con problemi</label>
  </form>
  <table class="st-tab" id="st-tabella" data-csrf="<?= h($csrf) ?>">
    <thead><tr><th>Contenuto</th><th>Titolo per Google</th><th>Descrizione per Google</th><th>Robots</th><th>Immagini senza alt</th></tr></thead>
    <tbody>
    <?php foreach ($righe as $i => $r): ?>
    <tr data-tipo="<?= h($r['tipo']) ?>" data-id="<?= $r['id'] ?>" data-cerca="<?= h(mb_strtolower($r['titolo'] . ' ' . $r['percorso'] . ' ' . $r['seo_title'])) ?>" data-problemi="<?= $r['problemi'] ?>" data-peso="<?= $r['peso'] ?>" data-titolo="<?= h(mb_strtolower($r['titolo'])) ?>" data-tipo-nome="<?= h($r['tipo_nome']) ?>" data-ordine="<?= $i ?>">
      <td class="st-cont"><a href="<?= h($r['scheda_url']) ?>"><?= h($r['titolo']) ?></a><?= $r['stato'] !== 'pubblicato' ? '<span class="st-stato">' . h(CONTENUTI_STATI[$r['stato']]) . '</span>' : '' ?><span class="st-meta"><?= h($r['tipo_nome']) ?> &middot; <?= h($r['percorso']) ?></span>
        <span class="st-azioni"><a class="btn btn-secondary btn-sm" href="<?= h($r['editor_url']) ?>">Editor</a><a class="btn btn-secondary btn-sm" href="<?= h($r['url_sito']) ?><?= $r['stato'] !== 'pubblicato' ? '?preview=1' : '' ?>" target="_blank">Vedi</a></span></td>
      <td class="st-campo"><?= $sem($r['sem_titolo'], 'Titolo') ?><label class="sr-solo" for="st-t-<?= h($r['tipo']) ?>-<?= $r['id'] ?>">Titolo per Google di <?= h($r['titolo']) ?></label><input type="text" id="st-t-<?= h($r['tipo']) ?>-<?= $r['id'] ?>" name="seo_title" value="<?= h($r['seo_title']) ?>" maxlength="255" placeholder="<?= h($r['titolo_auto']) ?>" data-campo="seo_title"><span class="st-esito" role="status"></span></td>
      <td class="st-campo"><?= $sem($r['sem_descrizione'], 'Descrizione') ?><label class="sr-solo" for="st-d-<?= h($r['tipo']) ?>-<?= $r['id'] ?>">Descrizione per Google di <?= h($r['titolo']) ?></label><textarea id="st-d-<?= h($r['tipo']) ?>-<?= $r['id'] ?>" name="descrizione" rows="2" maxlength="500" placeholder="Le due righe sotto il titolo nei risultati (da 70 a 160 caratteri)" data-campo="descrizione"><?= h($r['descrizione']) ?></textarea><span class="st-esito" role="status"></span></td>
      <td><?= h($r['robots']) ?></td>
      <td class="st-alt"><?= $r['alt_mancanti'] > 0 ? '<a href="media.php?scheda=alt">' . $r['alt_mancanti'] . '</a>' : '0' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="st-vuoto" id="st-nessuno" hidden>Nessun contenuto corrisponde ai filtri: prova una parola più corta, togli il tipo, oppure spegni "Solo i contenuti con problemi".</p>
  <p class="hint" style="margin-top:.8rem">Il titolo per Google va da 30 a 60 caratteri (sopra 70 Google lo taglia), la descrizione da 70 a 160. Un titolo o una descrizione uguali in due contenuti escono rossi: scrivine di diversi. Per il resto (keyword, In breve, domande frequenti) apri la scheda del contenuto, riquadro Controllo SEO.</p>
  <?php endif; ?>
</div>
<script src="<?= pannello_file('assets/seo-tabella.js') ?>" defer></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
