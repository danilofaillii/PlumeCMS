<?php
/**
 * Media > Senza alt (2.29.0): le immagini senza testo alternativo, con la correzione in posto. La include admin/media.php
 * (media.php?scheda=alt). Motore in includes/media-alt.php; il salvataggio senza ricaricare in admin/assets/media-alt.js
 * (seo-api.php, azione media_alt_salva). Due elenchi:
 *   - Nella libreria: le immagini dei Media senza un alt loro, una casella per riga (l'alt entra in ogni pagina che usa la foto
 *     con l'alt vuoto);
 *   - Nelle pagine: le immagini dei contenuti pubblicati con l'alt vuoto nel widget (e senza un alt nella libreria), con la
 *     stessa casella e il link all'editor o alla scheda.
 */
require_once dirname(__DIR__, 2) . '/includes/media-alt.php';
require_once dirname(__DIR__, 2) . '/includes/sito-seo.php';
require_once dirname(__DIR__, 2) . '/includes/seo.php';
require_once dirname(__DIR__, 2) . '/includes/editor-controlli.php';
require_once dirname(__DIR__, 2) . '/includes/editor.php';
sito_schema_aggiorna();
editor_carica_widget_esterni();
$senza = media_alt_senza();
$con = media_alt_con();
$nellePagine = media_alt_nelle_pagine();
$perPagina = 60;
$paginaAlt = max(1, (int)($_GET['p'] ?? 1));
$pagineAlt = max(1, (int)ceil(count($senza) / $perPagina));
$paginaAlt = min($paginaAlt, $pagineAlt);
$vociAlt = array_slice($senza, ($paginaAlt - 1) * $perPagina, $perPagina);
$csrf = csrf_token();
$nellaLibreria = [];
foreach (media_elenco() as $m) $nellaLibreria[media_alt_chiave($m['url'])] = true;
require_once __DIR__ . '/admin-header.php';
?>
<style>
.ma-sotto{font-size:.86rem;color:var(--text-muted);margin:0 0 .9rem;line-height:1.5}
.ma-sotto b{color:var(--text)}
.ma-numeri{display:flex;gap:.8rem;flex-wrap:wrap;margin:0 0 1rem}
.ma-numeri span{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:.5rem .9rem;font-size:.85rem}
.ma-numeri span b{display:block;font-size:1.25rem}
.ma-elenco{display:grid;gap:.6rem}
.ma-voce{display:grid;grid-template-columns:96px minmax(0,1fr);gap:.8rem;align-items:center;padding:.6rem;border:1px solid var(--border);border-radius:10px;background:var(--surface)}
.ma-voce .ma-foto{width:96px;height:72px;object-fit:contain;background:var(--bg);border-radius:6px;display:block}
.ma-voce .ma-nome{font-size:.82rem;font-weight:600;overflow-wrap:anywhere}
.ma-voce .ma-dove{font-size:.76rem;color:var(--text-muted);overflow-wrap:anywhere;margin-top:.1rem}
.ma-voce .ma-dove a{font-weight:600}
.ma-voce input[type=text]{width:100%;margin-top:.35rem;padding:.4rem .55rem;border:1px solid var(--bordo-campo);border-radius:8px;font:inherit;font-size:.85rem;background:var(--campo);color:var(--text)}
.ma-voce input:focus-visible,.ma-con input:focus-visible{outline:2px solid var(--coral);outline-offset:1px}
.ma-esito{display:block;font-size:.76rem;margin-top:.2rem;min-height:1em;color:var(--text-muted)}
.ma-esito.ma-ok{color:var(--successo-testo)}.ma-esito.ma-no{color:var(--errore-testo)}
.ma-voce.ma-fatta{opacity:.6}
.ma-pagine{display:flex;gap:.4rem;justify-content:center;margin-top:1rem;flex-wrap:wrap}
.ma-con{margin-top:1rem}
.ma-con summary{cursor:pointer;font-weight:600;font-size:.9rem}
.ma-con table{width:100%;border-collapse:collapse;font-size:.84rem;margin-top:.6rem}
.ma-con td,.ma-con th{padding:.4rem .5rem;border-bottom:1px solid var(--border);text-align:left;vertical-align:top;overflow-wrap:anywhere}
[hidden]{display:none!important}
@media (max-width:640px){.ma-voce{grid-template-columns:72px minmax(0,1fr)}.ma-voce .ma-foto{width:72px;height:54px}}
</style>

<nav class="imp-schede" aria-label="Schede dei Media">
  <a href="media.php">Libreria</a><a href="media.php?scheda=alt" class="attiva" aria-current="page">Senza alt (<?= count($senza) + count($nellePagine) ?>)</a>
</nav>

<div class="form-card" id="ma-pagine">
  <h3>Nelle pagine: immagini con il testo alternativo vuoto</h3>
  <p class="ma-sotto">Il <b>testo alternativo</b> (alt) dice cosa c'è nella foto a chi non la vede (legge la pagina con un lettore di schermo) e a Google, che senza non la capisce e non la porta in Google Immagini. Qui ci sono le immagini dei <b>contenuti pubblicati</b> con l'alt vuoto nel widget. Scrivi l'alt nella casella: si salva nella libreria dei Media e vale per <b>tutte le pagine</b> che usano quella foto senza un alt loro (anche l'immagine in evidenza e il testo della scheda). Oppure apri l'editor e scrivilo nel widget.</p>
  <?php if (!$nellePagine): ?>
  <div class="empty-state">Nessuna immagine senza alt nei contenuti pubblicati: tutte hanno il loro testo alternativo.</div>
  <?php else: ?>
  <div class="ma-elenco" id="ma-elenco-pagine">
    <?php foreach ($nellePagine as $i => $x): $k = media_alt_chiave($x['immagine']); $inLib = $k !== '' && isset($nellaLibreria[$k]); ?>
    <div class="ma-voce" data-chiave="<?= h($k) ?>">
      <img class="ma-foto" src="<?= h($x['immagine']) ?>" alt="" loading="lazy">
      <div>
        <div class="ma-nome"><?= h($x['titolo']) ?> <span class="ma-dove">(<?= h((string)(contenuti_tipi()[$x['tipo']]['uno'] ?? $x['tipo'])) ?>, <?= h($x['percorso']) ?>)</span></div>
        <div class="ma-dove"><?= h($x['dove']) ?> &middot; <?= h(basename((string)$x['immagine'])) ?> &middot; <?php if ($x['editor_url'] !== ''): ?><a href="<?= h($x['editor_url']) ?>">Apri nell'editor</a> &middot; <?php endif; ?><a href="<?= h($x['scheda_url']) ?>">Scheda</a></div>
        <?php if ($inLib): ?>
        <label class="sr-solo" for="ma-p-<?= $i ?>">Testo alternativo di <?= h(basename((string)$x['immagine'])) ?></label>
        <input type="text" id="ma-p-<?= $i ?>" value="" maxlength="<?= MEDIA_ALT_MAX ?>" placeholder="Cosa si vede nella foto, per esempio: Il negozio in via Rizzoli, con la vetrina illuminata" data-alt-url="<?= h($x['immagine']) ?>" data-alt-csrf="<?= h($csrf) ?>">
        <span class="ma-esito" role="status"></span>
        <?php else: ?>
        <span class="ma-esito">Questa immagine non sta nella libreria dei Media (un indirizzo esterno): scrivi l'alt nel widget, dall'editor.</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="form-card" id="ma-libreria">
  <h3>Nella libreria: immagini senza un alt loro</h3>
  <p class="ma-sotto">Tutte le immagini dei Media che non hanno ancora un testo alternativo nella libreria. Non è obbligatorio scriverlo per ognuna: conta per le foto che usi nelle pagine. Le miniature non sono in elenco.</p>
  <div class="ma-numeri"><span><b id="ma-n-senza"><?= count($senza) ?></b>senza alt</span><span><b id="ma-n-con"><?= count($con) ?></b>con l'alt</span></div>
  <?php if (!$senza): ?>
  <div class="empty-state">Tutte le immagini della libreria hanno il loro testo alternativo.</div>
  <?php else: ?>
  <div class="ma-elenco" id="ma-elenco-libreria">
    <?php foreach ($vociAlt as $i => $m): ?>
    <div class="ma-voce" data-chiave="<?= h($m['chiave']) ?>">
      <img class="ma-foto" src="<?= h($m['url']) ?>" alt="" loading="lazy">
      <div>
        <div class="ma-nome"><?= h($m['nome']) ?></div>
        <div class="ma-dove"><?= h(media_cartelle()[$m['cartella']] ?? $m['cartella']) ?><?= $m['sotto'] !== '' ? ' &middot; ' . h($m['sotto']) : '' ?></div>
        <label class="sr-solo" for="ma-l-<?= $i ?>">Testo alternativo di <?= h($m['nome']) ?></label>
        <input type="text" id="ma-l-<?= $i ?>" value="" maxlength="<?= MEDIA_ALT_MAX ?>" placeholder="Cosa si vede nella foto" data-alt-url="<?= h($m['url']) ?>" data-alt-csrf="<?= h($csrf) ?>">
        <span class="ma-esito" role="status"></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($pagineAlt > 1): ?>
  <div class="ma-pagine">
    <?php for ($i = 1; $i <= $pagineAlt; $i++): if ($pagineAlt > 15 && abs($i - $paginaAlt) > 4 && $i !== 1 && $i !== $pagineAlt) { if (abs($i - $paginaAlt) === 5) echo '<span style="color:var(--text-muted)">&hellip;</span>'; continue; } ?>
    <a href="media.php?scheda=alt&amp;p=<?= $i ?>" class="btn btn-sm <?= $i === $paginaAlt ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
  <?php if ($con): ?>
  <details class="ma-con" id="ma-con"><summary>Le immagini con l'alt (<?= count($con) ?>): per cambiarlo</summary>
    <table data-no-schede><thead><tr><th>Immagine</th><th>Testo alternativo</th></tr></thead><tbody>
    <?php foreach ($con as $i => $m): ?>
    <tr><td><?= h($m['nome']) ?><br><small style="color:var(--text-muted)"><?= h($m['chiave']) ?></small></td><td><label class="sr-solo" for="ma-c-<?= $i ?>">Testo alternativo di <?= h($m['nome']) ?></label><input type="text" id="ma-c-<?= $i ?>" value="<?= h($m['alt']) ?>" maxlength="<?= MEDIA_ALT_MAX ?>" data-alt-url="<?= h($m['url']) ?>" data-alt-csrf="<?= h($csrf) ?>" style="width:100%;padding:.35rem .5rem;border:1px solid var(--bordo-campo);border-radius:8px;font:inherit;font-size:.85rem;background:var(--campo);color:var(--text)"><span class="ma-esito" role="status"></span></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </details>
  <?php endif; ?>
</div>
<script src="<?= pannello_file('assets/media-alt.js') ?>" defer></script>
<?php require_once __DIR__ . '/admin-footer.php'; ?>
