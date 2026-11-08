<?php
/**
 * AI collegate > Proposte: le modifiche che le AI propongono a contenuti pubblicati, alla libreria dei Media e (2.33.0) ai
 * menu e al fondo delle pagine. Prima e dopo affiancati, il motivo, e Approva, Modifica e approva, Scarta; Approva le
 * selezionate. Le proposte di una scheda dell'articolo (propose_article_changes) stanno insieme, con Approva tutte e Scarta
 * tutte; una proposta di menu mostra i due alberi affiancati (voci nuove, tolte, spostate, con il nome nuovo).
 * L'approvazione passa dalle strade del CMS (aic_proposta_applica: revisione, versione dell'editor, alt, menu_salva).
 */
require_once __DIR__ . '/admin-comune.php';
require_once __DIR__ . '/proposte.php';
require_once __DIR__ . '/navigazione.php';
$adminTitle = 'Proposte dell\'AI';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $chi = (string)(current_user()['username'] ?? '');
    $azione = (string)($_POST['azione'] ?? '');
    $scheda = in_array($azione, ['scheda_approva', 'scheda_scarta'], true);
    if ($scheda) {
        // Approva tutte o Scarta tutte: le proposte ancora da decidere di quella scheda.
        $st = db()->prepare('SELECT id FROM ' . tbl('ai_proposte') . " WHERE gruppo = ? AND gruppo <> '' AND stato = 'attesa' ORDER BY id LIMIT 200");
        $st->execute([preg_replace('/[^a-z0-9-]/', '', (string)($_POST['gruppo'] ?? ''))]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } else {
        $ids = $azione === 'gruppo' ? array_slice(array_map('intval', (array)($_POST['scelte'] ?? [])), 0, 200) : [(int)($_POST['id'] ?? 0)];
    }
    $scarta = $azione === 'scarta' || $azione === 'scheda_scarta';
    $fatte = 0; $errori = [];
    foreach ($ids as $id) {
        $p = aic_proposta($id);
        if (!$p) continue;
        try {
            if ($scarta) aic_proposta_scarta($p, $chi);
            elseif ($azione === 'modifica') aic_proposta_applica($p, (string)($_POST['valore'] ?? ''), $chi);
            else aic_proposta_applica($p, null, $chi);
            $fatte++;
            aic_registra(['id' => (int)$p['connessione_id'], 'nome' => $p['client']], 'proposta', 'ok', ($scarta ? 'scartata' : 'approvata') . ' da ' . $chi . ' (' . $p['campo'] . ')', (string)$p['tipo'], (int)$p['contenuto_id']);
        } catch (Throwable $e) {
            $errori[] = 'Proposta ' . $id . ': ' . $e->getMessage();
        }
    }
    if ($fatte) flash('success', $scarta ? ($fatte === 1 ? 'Proposta scartata: non cambia niente sul sito.' : $fatte . ' proposte scartate: non cambia niente sul sito.')
        : ($fatte === 1 ? 'Proposta approvata: la modifica è sul sito (la versione di prima resta nelle revisioni).' : $fatte . ' proposte approvate: le modifiche sono sul sito.'));
    if ($errori) flash('error', implode(' ', $errori));
    if (!$fatte && !$errori) flash('error', $azione === 'gruppo' ? 'Spunta almeno una proposta da approvare.' : ($scheda ? 'In questa scheda non ci sono più proposte da decidere.' : 'Proposta non trovata.'));
    aic_admin_vai('proposte', array_filter(['stato' => (string)($_POST['stato'] ?? '')]));
}
$filtri = ['attesa' => 'Da decidere', 'approvata' => 'Approvate', 'scartata' => 'Scartate', 'superata' => 'Superate', 'tutte' => 'Tutte'];
$filtro = array_key_exists($_GET['stato'] ?? '', $filtri) ? (string)$_GET['stato'] : 'attesa';
$st = db()->prepare('SELECT * FROM ' . tbl('ai_proposte') . ($filtro !== 'tutte' ? ' WHERE stato = ?' : '') . ' ORDER BY id DESC LIMIT 200');
$st->execute($filtro !== 'tutte' ? [$filtro] : []);
$elenco = $st->fetchAll();
$stati = ['attesa' => 'Da decidere', 'approvata' => 'Approvata', 'scartata' => 'Scartata', 'superata' => 'Superata'];
require CMS_ADMIN . '/includes/admin-header.php';
aic_admin_schede('proposte');

/** Una proposta. $dentro: dentro una scheda dell'articolo (titolo più piccolo, senza la fascia). */
$mostra = function (array $p, bool $dentro = false) use ($stati): void {
    $titolo = ''; $link = '';
    if ($p['tipo'] === 'media') { $titolo = 'Immagine ' . basename((string)$p['media_url']); $link = 'media.php?scheda=alt'; }
    elseif ($p['tipo'] === 'menu') { $m = aic_nav_menu_fresco((int)$p['contenuto_id']); $titolo = $m ? '«' . $m['nome'] . '»' : 'menu eliminato'; $link = 'menu.php?id=' . (int)$p['contenuto_id']; }
    else { $r = contenuti_leggi((string)$p['tipo'], (int)$p['contenuto_id']); $titolo = $r ? (string)$r['title'] : 'contenuto eliminato'; $link = 'contenuto.php?tipo=' . $p['tipo'] . '&id=' . (int)$p['contenuto_id']; }
    $campo = aic_campo_etichetta((string)$p['campo']);
    $speciale = $p['tipo'] === 'menu' || $p['campo'] === 'navigazione'; // si approva o si scarta, niente "Modifica e approva"
    $html = $p['campo'] === 'testo' && $p['widget_id'] === '' || ($p['campo'] === 'testo' && str_contains((string)$p['dopo'], '<'));
    $vedi = fn(?string $v) => $html ? trim(preg_replace('/\n{3,}/', "\n\n", strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>', '</h2>', '</h3>'], ["\n\n", "\n", "\n", "\n", "\n", "\n\n", "\n\n"], (string)$v)))) : aic_proposta_leggibile((string)$p['campo'], $v);
    $h = $dentro ? 'h4' : 'h3';
    ?>
<article class="<?= $dentro ? 'aic-proposta aic-nella-scheda' : 'form-card aic-proposta' ?><?= $p['tipo'] === 'menu' ? ' aic-proposta-menu' : '' ?>" id="proposta-<?= (int)$p['id'] ?>" data-proposta="<?= (int)$p['id'] ?>" data-stato="<?= h((string)$p['stato']) ?>">
  <<?= $h ?>><?php if ($p['stato'] === 'attesa'): ?><input type="checkbox" class="aic-scelta" name="scelte[]" value="<?= (int)$p['id'] ?>" form="aic-gruppo" aria-label="Seleziona la proposta <?= (int)$p['id'] ?>"> <?php endif; ?><?= h($campo) ?>: <a href="<?= h($link) ?>"><?= h($titolo) ?></a> <span class="aic-stato aic-<?= h((string)$p['stato']) ?>"><?= h($stati[$p['stato']] ?? $p['stato']) ?></span></<?= $h ?>>
  <p class="hint">Da <b><?= h((string)$p['client']) ?></b>, <?= h(aic_data($p['creato'])) ?><?= $p['widget_id'] !== '' ? ' &middot; in un widget della pagina' : '' ?><?= $p['deciso'] ? ' &middot; ' . h((string)$p['nota']) . ' il ' . h(aic_data($p['deciso'])) . ($p['deciso_da'] !== '' ? ' da ' . h((string)$p['deciso_da']) : '') : '' ?></p>
  <p class="aic-motivo"><b>Perché:</b> <?= nl2br(h((string)$p['motivo']), false) ?></p>
  <?php if ($p['tipo'] === 'media'): ?><p><img class="aic-anteprima" src="<?= h(cms_url_sito() . (string)$p['media_url']) ?>" alt=""></p><?php endif; ?>
  <?php if ($p['tipo'] === 'menu'):
      $prima = json_decode((string)$p['prima'], true) ?: []; $dopo = json_decode((string)$p['dopo'], true) ?: [];
      $cf = aic_nav_confronto($prima, $dopo); $rq = $cf['riepilogo'];
      $pezzi = [];
      foreach (['nuove' => ['voce nuova', 'voci nuove'], 'tolte' => ['voce tolta', 'voci tolte'], 'spostate' => ['voce spostata', 'voci spostate'], 'rinominate' => ['voce con il nome nuovo', 'voci con il nome nuovo']] as $k => [$uno, $piu]) if ($rq[$k]) $pezzi[] = $rq[$k] . ' ' . ($rq[$k] === 1 ? $uno : $piu);
  ?>
  <p class="aic-riepilogo"><?= h($pezzi ? implode(', ', $pezzi) : 'Stesse voci, in un altro ordine') ?> (<?= (int)$rq['voci_prima'] ?> voci adesso, <?= (int)$rq['voci_dopo'] ?> nella proposta).</p>
  <div class="aic-confronto aic-confronto-menu">
    <div><div class="aic-etichetta" id="aic-menu-prima-<?= (int)$p['id'] ?>">Il menu adesso</div><div class="aic-valore aic-prima" role="group" aria-labelledby="aic-menu-prima-<?= (int)$p['id'] ?>"><?= aic_nav_albero_html($cf['prima']) ?></div></div>
    <div><div class="aic-etichetta" id="aic-menu-dopo-<?= (int)$p['id'] ?>">Il menu proposto</div><div class="aic-valore aic-dopo" role="group" aria-labelledby="aic-menu-dopo-<?= (int)$p['id'] ?>"><?= aic_nav_albero_html($cf['dopo']) ?></div></div>
  </div>
  <p class="hint aic-legenda">Segni: <span class="aic-segno aic-segno-nuova">nuova</span> voce aggiunta, <span class="aic-segno aic-segno-tolta">tolta</span> voce che sparisce, <span class="aic-segno aic-segno-spostata">spostata</span> voce in un altro posto, <span class="aic-segno aic-segno-nome-nuovo">nome nuovo</span> etichetta cambiata.</p>
  <?php else: ?>
  <div class="aic-confronto">
    <?php foreach (['prima' => ['Adesso', 'aic-prima'], 'dopo' => ['Proposta', 'aic-dopo']] as $_k => [$_et, $_cl]): $_im = $p['campo'] === 'immagine' ? (json_decode((string)$p[$_k], true) ?: []) : null; ?>
    <div><div class="aic-etichetta"><?= $_et ?></div><div class="aic-valore <?= $_cl ?>"><?php if ($_im !== null): ?><?php if (($_im['url'] ?? '') !== ''): ?><img class="aic-anteprima" src="<?= h(cms_url_sito() . (string)$_im['url']) ?>" alt=""><br><?= h((string)$_im['url']) ?><?= ($_im['alt'] ?? '') !== '' ? '<br>alt: ' . h((string)$_im['alt']) : '' ?><?php else: ?><i>nessuna immagine</i><?php endif; ?><?php else: ?><?= ($x = $vedi($p[$_k])) !== '' ? nl2br(h($x), false) : '<i>vuoto</i>' ?><?php endif; ?></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($html): ?><details class="aic-html"><summary>Vedi l'HTML proposto</summary><pre><?= h((string)$p['dopo']) ?></pre></details><?php endif; ?>
  <?php endif; ?>
  <?php if ($p['stato'] === 'attesa'): ?>
  <div class="aic-azioni">
    <form method="post"<?= $p['tipo'] === 'menu' ? ' data-conferma="Sostituire il menu con quello proposto? Il menu di adesso resta nelle versioni: si recupera da Editor &gt; Versioni." data-conferma-ok="Approva il menu"' : '' ?>><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="stato" value="attesa"><button class="btn btn-primary btn-sm" name="azione" value="approva"><?= $p['tipo'] === 'menu' ? 'Approva il menu' : 'Approva' ?></button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="stato" value="attesa"><button class="btn btn-danger btn-sm" name="azione" value="scarta">Scarta</button></form>
  </div>
  <?php if ($speciale): ?>
  <p class="hint"><?= $p['tipo'] === 'menu' ? 'Questa proposta non si corregge qui: se ti va quasi bene, approvala e ritocca le voci in Editor &gt; Menu di navigazione; altrimenti scartala e chiedi all\'AI una proposta nuova. Se nel frattempo qualcuno cambia il menu, l\'approvazione si ferma.' : 'Questa proposta non si corregge qui: approvala e ritocca i blocchi nell\'editor, oppure scartala.' ?></p>
  <?php elseif ($p['campo'] !== 'immagine'): ?>
  <details class="aic-modifica"><summary>Modifica e approva</summary>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="stato" value="attesa"><input type="hidden" name="azione" value="modifica">
      <div class="form-group"><label for="aic-v-<?= (int)$p['id'] ?>">Il valore da salvare</label>
        <textarea name="valore" id="aic-v-<?= (int)$p['id'] ?>" rows="<?= in_array($p['campo'], ['testo', 'domande_frequenti', 'in_breve', 'estratto'], true) ? 8 : 2 ?>"><?= h($p['campo'] === 'domande_frequenti' ? aic_proposta_leggibile('domande_frequenti', $p['dopo']) : (string)$p['dopo']) ?></textarea>
        <div class="hint"><?= $p['campo'] === 'domande_frequenti' ? 'La domanda sulla prima riga, la risposta sotto; una riga vuota fra una coppia e l\'altra.' : ($html ? 'HTML semplice: script, stili e codice vengono tolti.' : 'Correggi e salva: passa dagli stessi controlli della proposta.') ?></div></div>
      <button class="btn btn-secondary btn-sm" type="submit">Salva e approva</button>
    </form>
  </details>
  <?php endif; ?>
  <?php endif; ?>
</article>
<?php
};
?>
<nav class="imp-schede aic-filtri" aria-label="Filtro delle proposte"><?php foreach ($filtri as $k => $n): ?><a href="<?= h(aic_admin_url('proposte', ['stato' => $k])) ?>"<?= $k === $filtro ? ' class="attiva" aria-current="page"' : '' ?>><?= h($n) ?></a><?php endforeach; ?></nav>
<?php if (!$elenco): ?>
<div class="form-card"><h3><?= h($filtri[$filtro]) ?></h3><div class="empty-state"><?= $filtro === 'attesa' ? 'Nessuna proposta da decidere. Quando un\'AI collegata vuole cambiare un contenuto già pubblicato (titolo per Google, descrizione, testo, un alt...) o un menu, la proposta arriva qui.' : 'Nessuna proposta in questo elenco: scegli un altro filtro qui sopra.' ?></div></div>
<?php else: ?>
<?php if ($filtro === 'attesa'): ?>
<form method="post" id="aic-gruppo" class="aic-gruppo" data-conferma="Approvare tutte le proposte spuntate? Le modifiche vanno subito sul sito (le versioni di prima restano nelle revisioni)." data-conferma-ok="Approva le selezionate">
  <?= csrf_field() ?><input type="hidden" name="azione" value="gruppo"><input type="hidden" name="stato" value="attesa">
  <label class="aic-spunta"><input type="checkbox" data-seleziona-tutti=".aic-scelta"> Seleziona tutte</label>
  <button class="btn btn-primary btn-sm" type="submit">Approva le selezionate</button>
</form>
<?php endif; ?>
<?php
$fatti = [];
foreach ($elenco as $p):
    $g = (string)($p['gruppo'] ?? '');
    if ($g === '') { $mostra($p); continue; }
    if (isset($fatti[$g])) continue;
    $fatti[$g] = true;
    $dellaScheda = array_values(array_filter($elenco, fn($x) => (string)($x['gruppo'] ?? '') === $g));
    $daDecidere = count(array_filter($dellaScheda, fn($x) => $x['stato'] === 'attesa'));
    $sTipo = str_starts_with($g, 'pagine-') ? 'pagine' : 'articoli';
    $sId = (int)(explode('-', $g)[1] ?? 0);
    $sRiga = contenuti_leggi($sTipo, $sId);
    $sTitolo = $sRiga ? (string)$sRiga['title'] : 'contenuto eliminato';
?>
<section class="form-card aic-scheda" id="scheda-<?= h($g) ?>" data-gruppo="<?= h($g) ?>" aria-labelledby="scheda-t-<?= h($g) ?>">
  <h3 id="scheda-t-<?= h($g) ?>">Scheda <?= $sTipo === 'articoli' ? 'dell\'articolo' : 'della pagina' ?>: <a href="<?= h('contenuto.php?tipo=' . $sTipo . '&id=' . $sId) ?>"><?= h($sTitolo) ?></a> <span class="aic-conta-scheda"><?= count($dellaScheda) ?> <?= count($dellaScheda) === 1 ? 'proposta' : 'proposte' ?><?= $daDecidere && $daDecidere < count($dellaScheda) ? ', ' . $daDecidere . ' da decidere' : '' ?></span></h3>
  <?php if ($daDecidere): ?>
  <div class="aic-azioni aic-azioni-scheda">
    <form method="post" data-conferma="Approvare tutte le <?= (int)$daDecidere ?> proposte di questa scheda? Le modifiche vanno subito sul sito (le versioni di prima restano nelle revisioni)." data-conferma-ok="Approva tutte"><?= csrf_field() ?><input type="hidden" name="gruppo" value="<?= h($g) ?>"><input type="hidden" name="stato" value="attesa"><button class="btn btn-primary btn-sm" name="azione" value="scheda_approva">Approva tutte</button></form>
    <form method="post" data-conferma="Scartare tutte le <?= (int)$daDecidere ?> proposte di questa scheda? Sul sito non cambia niente." data-conferma-ok="Scarta tutte"><?= csrf_field() ?><input type="hidden" name="gruppo" value="<?= h($g) ?>"><input type="hidden" name="stato" value="attesa"><button class="btn btn-danger btn-sm" name="azione" value="scheda_scarta">Scarta tutte</button></form>
    <span class="hint">Oppure decidi una per una qui sotto.</span>
  </div>
  <?php endif; ?>
  <?php foreach ($dellaScheda as $x) $mostra($x, true); ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
