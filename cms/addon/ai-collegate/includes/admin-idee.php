<?php
/**
 * AI collegate > Idee per articoli (2.34.0): le idee proposte dalle AI (propose_article_ideas). Da decidere: Approva (diventa
 * "da scrivere"), Scarta, Modifica (titolo, parola chiave, a chi serve, scaletta, nota per l'AI, data prevista). Il piano
 * editoriale: le idee da scrivere e scritte divise per mese della data prevista (solo un promemoria: niente si pubblica da
 * solo), con Sposta prima e Sposta dopo dentro il mese e il link alla bozza. Solo amministratore, azioni con CSRF.
 * Manuale tecnico, 47.21.
 */
require_once __DIR__ . '/admin-comune.php';
require_once __DIR__ . '/idee.php';
require_once cms_radice() . '/includes/contenuti.php';
$adminTitle = 'Idee per articoli';
$viste = ['attesa' => 'Da decidere', 'piano' => 'Piano editoriale', 'scartata' => 'Scartate', 'tutte' => 'Tutte'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $chi = (string)(current_user()['username'] ?? '');
    $azione = (string)($_POST['azione'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $vista = array_key_exists($_POST['vista'] ?? '', $viste) ? (string)$_POST['vista'] : 'attesa';
    $i = aic_idea($id);
    try {
        if (!$i) throw new RuntimeException('Idea non trovata.');
        if ($azione === 'approva') {
            if (!aic_idea_decidi($id, true, $chi)) throw new RuntimeException('Questa idea non è più da decidere.');
            flash('success', 'Idea approvata: ora è «da scrivere» nel piano editoriale. La tua AI la trova e scrive la bozza; tu la pubblichi quando vuoi.');
        } elseif ($azione === 'scarta') {
            if (!aic_idea_decidi($id, false, $chi)) throw new RuntimeException('Questa idea non si può più scartare (è già scritta o scartata).');
            flash('success', 'Idea scartata: non cambia niente sul sito.');
        } elseif ($azione === 'modifica') {
            aic_idea_modifica($id, $_POST);
            if (!empty($_POST['approva']) && $i['stato'] === 'attesa') { aic_idea_decidi($id, true, $chi); flash('success', 'Idea corretta e approvata: ora è «da scrivere».'); }
            else flash('success', 'Idea salvata.');
        } elseif ($azione === 'sposta') {
            if (!aic_idea_sposta($id, ($_POST['verso'] ?? '') === 'su' ? 'su' : 'giu')) flash('info', 'L\'idea è già la prima o l\'ultima del suo mese.');
        } else throw new RuntimeException('Azione sconosciuta.');
        if ($azione !== 'sposta') aic_registra(['id' => (int)$i['connessione_id'], 'nome' => $i['client']], 'idea', 'ok', $azione . ' da ' . $chi . ': ' . (string)$i['titolo']);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    aic_admin_vai('idee', ['vista' => $vista], $i && $azione === 'sposta' ? 'idea-' . $id : '');
}
$nAttesa = aic_idee_in_attesa();
$vista = array_key_exists($_GET['vista'] ?? '', $viste) ? (string)$_GET['vista'] : ($nAttesa ? 'attesa' : 'piano');
$dove = ['attesa' => "WHERE stato = 'attesa'", 'piano' => "WHERE stato IN ('da_scrivere', 'scritta')", 'scartata' => "WHERE stato = 'scartata'", 'tutte' => ''][$vista];
$elenco = db()->query('SELECT * FROM ' . tbl('ai_idee') . ' ' . $dove . " ORDER BY (data_prevista IS NULL), DATE_FORMAT(data_prevista, '%Y-%m'), ordine, id LIMIT 300")->fetchAll();
$mesi = ['01' => 'gennaio', '02' => 'febbraio', '03' => 'marzo', '04' => 'aprile', '05' => 'maggio', '06' => 'giugno', '07' => 'luglio', '08' => 'agosto', '09' => 'settembre', '10' => 'ottobre', '11' => 'novembre', '12' => 'dicembre'];
$meseDi = fn(array $i) => $i['data_prevista'] ? substr((string)$i['data_prevista'], 0, 7) : '';
$meseTesto = fn(string $m) => $m === '' ? 'Senza data prevista' : ucfirst($mesi[substr($m, 5, 2)] ?? '') . ' ' . substr($m, 0, 4);
require CMS_ADMIN . '/includes/admin-header.php';
aic_admin_schede('idee');

$mostra = function (array $i, string $vista) use ($meseDi): void {
    $scaletta = json_decode((string)$i['scaletta'], true) ?: [];
    $coll = json_decode((string)$i['collegati'], true) ?: [];
    $art = (int)$i['articolo_id'] > 0 ? contenuti_leggi('articoli', (int)$i['articolo_id']) : null;
    $piano = in_array($i['stato'], ['da_scrivere', 'scritta'], true);
    $id = (int)$i['id'];
    ?>
<article class="form-card aic-idea" id="idea-<?= $id ?>" data-idea="<?= $id ?>" data-stato="<?= h((string)$i['stato']) ?>">
  <h3><?= h((string)$i['titolo']) ?> <span class="aic-stato aic-idea-<?= h((string)$i['stato']) ?>"><?= h(AIC_IDEE_STATI[$i['stato']] ?? (string)$i['stato']) ?></span></h3>
  <p class="hint">Proposta da <b><?= h((string)$i['client']) ?></b>, <?= h(aic_data($i['creato'])) ?><?= $i['data_prevista'] ? ' &middot; <b>data prevista ' . h(date('d/m/Y', strtotime((string)$i['data_prevista']))) . '</b>' : '' ?><?= $i['deciso'] ? ' &middot; decisa da ' . h((string)$i['deciso_da']) . ' il ' . h(aic_data($i['deciso'])) : '' ?></p>
  <dl class="aic-dati">
    <?php if ($i['parola_chiave'] !== ''): ?><dt>Parola chiave</dt><dd><?= h((string)$i['parola_chiave']) ?></dd><?php endif; ?>
    <?php if ($i['pubblico'] !== ''): ?><dt>A chi serve</dt><dd><?= h((string)$i['pubblico']) ?></dd><?php endif; ?>
    <dt>Perché</dt><dd><?= nl2br(h((string)$i['motivo']), false) ?></dd>
    <?php if ($i['nota'] !== ''): ?><dt>Nota per l'AI</dt><dd><?= h((string)$i['nota']) ?></dd><?php endif; ?>
    <?php if ($art): ?><dt>Bozza</dt><dd><a href="editor.php?tipo=articoli&amp;id=<?= (int)$art['id'] ?>"><?= h((string)$art['title']) ?></a> (<?= h(mb_strtolower(CONTENUTI_STATI[!empty($art['cestino']) ? 'cestino' : contenuti_stato('articoli', $art)] ?? '')) ?>) &middot; <a href="contenuto.php?tipo=articoli&amp;id=<?= (int)$art['id'] ?>">scheda</a></dd>
    <?php elseif ((int)$i['articolo_id'] > 0): ?><dt>Bozza</dt><dd>eliminata</dd><?php endif; ?>
  </dl>
  <?php if ($scaletta): ?><p class="aic-etichetta">Scaletta</p><ol class="aic-scaletta"><?php foreach ($scaletta as $s): ?><li><?= h((string)$s) ?></li><?php endforeach; ?></ol><?php endif; ?>
  <?php if ($coll): ?><p class="aic-etichetta">Articoli da collegare</p><ul class="aic-collegati"><?php foreach ($coll as $c): ?><li><a href="<?= h(cms_url_sito() . (string)($c['percorso'] ?? '/')) ?>" target="_blank" rel="noopener"><?= h((string)($c['titolo'] ?? '')) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
  <div class="aic-azioni">
    <?php if ($i['stato'] === 'attesa'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="vista" value="<?= h($vista) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-primary" name="azione" value="approva">Approva l'idea</button></form>
    <?php endif; ?>
    <?php if ($piano): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="vista" value="<?= h($vista) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="azione" value="sposta"><button class="btn btn-secondary btn-sm" name="verso" value="su" aria-label="Sposta prima nel mese: <?= h((string)$i['titolo']) ?>">&#8593; Sposta prima</button> <button class="btn btn-secondary btn-sm" name="verso" value="giu" aria-label="Sposta dopo nel mese: <?= h((string)$i['titolo']) ?>">&#8595; Sposta dopo</button></form>
    <?php endif; ?>
    <?php if (in_array($i['stato'], ['attesa', 'da_scrivere'], true)): ?>
    <form method="post" data-conferma="Scartare l'idea «<?= h((string)$i['titolo']) ?>»? Non cambia niente sul sito." data-conferma-ok="Scarta l'idea"><?= csrf_field() ?><input type="hidden" name="vista" value="<?= h($vista) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-danger btn-sm" name="azione" value="scarta">Scarta</button></form>
    <?php endif; ?>
  </div>
  <?php if ($i['stato'] !== 'scartata'): ?>
  <details class="aic-modifica" id="modifica-<?= $id ?>">
    <summary><?= $piano ? 'Modifica o dai una data' : 'Modifica prima di approvare' ?></summary>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="vista" value="<?= h($vista) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="azione" value="modifica">
      <div class="form-group"><label for="t-<?= $id ?>">Titolo</label><input type="text" id="t-<?= $id ?>" name="titolo" maxlength="300" value="<?= h((string)$i['titolo']) ?>" required></div>
      <div class="form-group"><label for="k-<?= $id ?>">Parola chiave</label><input type="text" id="k-<?= $id ?>" name="parola_chiave" maxlength="200" value="<?= h((string)$i['parola_chiave']) ?>"></div>
      <div class="form-group"><label for="p-<?= $id ?>">A chi serve</label><input type="text" id="p-<?= $id ?>" name="pubblico" maxlength="500" value="<?= h((string)$i['pubblico']) ?>"></div>
      <div class="form-group"><label for="s-<?= $id ?>">Scaletta</label><textarea id="s-<?= $id ?>" name="scaletta" rows="5" aria-describedby="sa-<?= $id ?>"><?= h(implode("\n", $scaletta)) ?></textarea><p class="hint" id="sa-<?= $id ?>">Un punto per riga, al massimo <?= AIC_IDEE_SCALETTA_MAX ?>.</p></div>
      <div class="form-group"><label for="n-<?= $id ?>">Nota per l'AI</label><input type="text" id="n-<?= $id ?>" name="nota" maxlength="500" value="<?= h((string)$i['nota']) ?>" aria-describedby="na-<?= $id ?>"><p class="hint" id="na-<?= $id ?>">La legge la tua AI prima di scrivere (per esempio: cita il nostro corso di aprile).</p></div>
      <div class="form-group"><label for="d-<?= $id ?>">Data prevista</label><input type="date" id="d-<?= $id ?>" name="data_prevista" value="<?= h((string)($i['data_prevista'] ?? '')) ?>" aria-describedby="da-<?= $id ?>"><p class="hint" id="da-<?= $id ?>">Solo un promemoria per il piano: l'articolo non si pubblica da solo.</p></div>
      <?php if ($i['stato'] === 'attesa'): ?><label class="aic-spunta"><input type="checkbox" name="approva" value="1" checked> <span>E approva l'idea</span></label><?php endif; ?>
      <button class="btn btn-primary btn-sm" type="submit">Salva l'idea</button>
    </form>
  </details>
  <?php endif; ?>
</article>
    <?php
};
?>
<nav class="aic-filtro aic-viste" aria-label="Quali idee">
  <?php foreach ($viste as $k => $t): ?><a class="btn btn-sm <?= $k === $vista ? 'btn-primary' : 'btn-secondary' ?>" href="<?= h(aic_admin_url('idee', ['vista' => $k])) ?>"<?= $k === $vista ? ' aria-current="page"' : '' ?>><?= h($t) ?><?= $k === 'attesa' && $nAttesa ? ' (' . $nAttesa . ')' : '' ?></a><?php endforeach; ?>
</nav>
<div class="aic-regola">Le idee le propone la tua AI dopo aver guardato gli argomenti del sito. Tu decidi: approvi (diventa «da scrivere»), correggi o scarti. La tua AI scrive solo le idee approvate, sempre in bozza: gli articoli li pubblichi tu.</div>
<?php if (!$elenco): ?>
<div class="form-card"><h3><?= h($viste[$vista]) ?></h3><div class="empty-state"><?php
    if ($vista === 'attesa') echo 'Nessuna idea da decidere. Chiedi alla tua AI: «Guarda gli argomenti del sito e proponimi cinque idee per articoli nuovi».';
    elseif ($vista === 'piano') echo 'Il piano è vuoto: approva qualche idea in «Da decidere» e qui le trovi, divise per mese.';
    elseif ($vista === 'scartata') echo 'Nessuna idea scartata.';
    else echo 'Ancora nessuna idea: chiedi alla tua AI di proporne qualcuna partendo dagli argomenti del sito.';
?></div></div>
<?php elseif ($vista === 'piano'):
    $gruppi = [];
    foreach ($elenco as $i) $gruppi[$meseDi($i)][] = $i;
    foreach ($gruppi as $mm => $idee): ?>
<section class="aic-mese" aria-labelledby="mese-<?= h($mm === '' ? 'senza' : $mm) ?>">
  <h2 class="aic-mese-titolo" id="mese-<?= h($mm === '' ? 'senza' : $mm) ?>"><?= h($meseTesto($mm)) ?> <span class="hint">(<?= count($idee) ?> <?= count($idee) === 1 ? 'articolo' : 'articoli' ?>)</span></h2>
  <?php foreach ($idee as $i) $mostra($i, $vista); ?>
</section>
<?php endforeach;
else:
    foreach ($elenco as $i) $mostra($i, $vista);
endif;
require CMS_ADMIN . '/includes/admin-footer.php';
