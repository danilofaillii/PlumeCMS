<?php
/**
 * AI collegate > Proposte: le modifiche che le AI propongono a contenuti pubblicati e alla libreria dei Media. Prima e dopo
 * affiancati, il motivo, e Approva, Modifica e approva, Scarta; Approva le selezionate. L'approvazione passa dalle strade
 * del CMS (aic_proposta_applica: revisione, versione dell'editor, alt della libreria).
 */
require_once __DIR__ . '/admin-comune.php';
require_once __DIR__ . '/proposte.php';
$adminTitle = 'Proposte dell\'AI';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $chi = (string)(current_user()['username'] ?? '');
    $azione = (string)($_POST['azione'] ?? '');
    $ids = $azione === 'gruppo' ? array_slice(array_map('intval', (array)($_POST['scelte'] ?? [])), 0, 200) : [(int)($_POST['id'] ?? 0)];
    $fatte = 0; $errori = [];
    foreach ($ids as $id) {
        $p = aic_proposta($id);
        if (!$p) continue;
        try {
            if ($azione === 'scarta') aic_proposta_scarta($p, $chi);
            elseif ($azione === 'modifica') aic_proposta_applica($p, (string)($_POST['valore'] ?? ''), $chi);
            else aic_proposta_applica($p, null, $chi);
            $fatte++;
            aic_registra(['id' => (int)$p['connessione_id'], 'nome' => $p['client']], 'proposta', 'ok', ($azione === 'scarta' ? 'scartata' : 'approvata') . ' da ' . $chi . ' (' . $p['campo'] . ')', (string)$p['tipo'], (int)$p['contenuto_id']);
        } catch (Throwable $e) {
            $errori[] = 'Proposta ' . $id . ': ' . $e->getMessage();
        }
    }
    if ($fatte) flash('success', $azione === 'scarta' ? 'Proposta scartata: non cambia niente sul sito.' : ($fatte === 1 ? 'Proposta approvata: la modifica è sul sito (la versione di prima resta nelle revisioni).' : $fatte . ' proposte approvate: le modifiche sono sul sito.'));
    if ($errori) flash('error', implode(' ', $errori));
    if (!$fatte && !$errori) flash('error', $azione === 'gruppo' ? 'Spunta almeno una proposta da approvare.' : 'Proposta non trovata.');
    aic_admin_vai('proposte', array_filter(['stato' => (string)($_POST['stato'] ?? '')]));
}
$filtri = ['attesa' => 'Da decidere', 'approvata' => 'Approvate', 'scartata' => 'Scartate', 'superata' => 'Superate', 'tutte' => 'Tutte'];
$filtro = array_key_exists($_GET['stato'] ?? '', $filtri) ? (string)$_GET['stato'] : 'attesa';
$st = db()->prepare('SELECT * FROM ' . tbl('ai_proposte') . ($filtro !== 'tutte' ? ' WHERE stato = ?' : '') . ' ORDER BY id DESC LIMIT 200');
$st->execute($filtro !== 'tutte' ? [$filtro] : []);
$elenco = $st->fetchAll();
$campi = aic_campi_proposta();
$stati = ['attesa' => 'Da decidere', 'approvata' => 'Approvata', 'scartata' => 'Scartata', 'superata' => 'Superata'];
require CMS_ADMIN . '/includes/admin-header.php';
aic_admin_schede('proposte');
?>
<nav class="imp-schede aic-filtri" aria-label="Filtro delle proposte"><?php foreach ($filtri as $k => $n): ?><a href="<?= h(aic_admin_url('proposte', ['stato' => $k])) ?>"<?= $k === $filtro ? ' class="attiva" aria-current="page"' : '' ?>><?= h($n) ?></a><?php endforeach; ?></nav>
<?php if (!$elenco): ?>
<div class="form-card"><h3><?= h($filtri[$filtro]) ?></h3><div class="empty-state"><?= $filtro === 'attesa' ? 'Nessuna proposta da decidere. Quando un\'AI collegata vuole cambiare un contenuto già pubblicato (titolo per Google, descrizione, testo, un alt...), la proposta arriva qui.' : 'Nessuna proposta in questo elenco: scegli un altro filtro qui sopra.' ?></div></div>
<?php else: ?>
<?php if ($filtro === 'attesa'): ?>
<form method="post" id="aic-gruppo" class="aic-gruppo" data-conferma="Approvare tutte le proposte spuntate? Le modifiche vanno subito sul sito (le versioni di prima restano nelle revisioni)." data-conferma-ok="Approva le selezionate">
  <?= csrf_field() ?><input type="hidden" name="azione" value="gruppo"><input type="hidden" name="stato" value="attesa">
  <label class="aic-spunta"><input type="checkbox" data-seleziona-tutti=".aic-scelta"> Seleziona tutte</label>
  <button class="btn btn-primary btn-sm" type="submit">Approva le selezionate</button>
</form>
<?php endif; ?>
<?php foreach ($elenco as $p):
    $titolo = ''; $link = '';
    if ($p['tipo'] === 'media') { $titolo = 'Immagine ' . basename((string)$p['media_url']); $link = 'media.php?scheda=alt'; }
    else { $r = contenuti_leggi((string)$p['tipo'], (int)$p['contenuto_id']); $titolo = $r ? (string)$r['title'] : 'contenuto eliminato'; $link = 'contenuto.php?tipo=' . $p['tipo'] . '&id=' . (int)$p['contenuto_id']; }
    $campo = $campi[$p['campo']][0] ?? $p['campo'];
    $html = $p['campo'] === 'testo' && $p['widget_id'] === '' || ($p['campo'] === 'testo' && str_contains((string)$p['dopo'], '<'));
    $vedi = fn(?string $v) => $html ? trim(preg_replace('/\n{3,}/', "\n\n", strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>', '</h2>', '</h3>'], ["\n\n", "\n", "\n", "\n", "\n", "\n\n", "\n\n"], (string)$v)))) : aic_proposta_leggibile((string)$p['campo'], $v);
?>
<article class="form-card aic-proposta" id="proposta-<?= (int)$p['id'] ?>" data-proposta="<?= (int)$p['id'] ?>" data-stato="<?= h((string)$p['stato']) ?>">
  <h3><?php if ($p['stato'] === 'attesa'): ?><input type="checkbox" class="aic-scelta" name="scelte[]" value="<?= (int)$p['id'] ?>" form="aic-gruppo" aria-label="Seleziona la proposta <?= (int)$p['id'] ?>"> <?php endif; ?><?= h($campo) ?>: <a href="<?= h($link) ?>"><?= h($titolo) ?></a> <span class="aic-stato aic-<?= h((string)$p['stato']) ?>"><?= h($stati[$p['stato']] ?? $p['stato']) ?></span></h3>
  <p class="hint">Da <b><?= h((string)$p['client']) ?></b>, <?= h(aic_data($p['creato'])) ?><?= $p['widget_id'] !== '' ? ' &middot; widget ' . h((string)$p['widget_id']) : '' ?><?= $p['deciso'] ? ' &middot; ' . h((string)$p['nota']) . ' il ' . h(aic_data($p['deciso'])) . ($p['deciso_da'] !== '' ? ' da ' . h((string)$p['deciso_da']) : '') : '' ?></p>
  <p class="aic-motivo"><b>Perché:</b> <?= nl2br(h((string)$p['motivo']), false) ?></p>
  <?php if ($p['tipo'] === 'media'): ?><p><img class="aic-anteprima" src="<?= h(cms_url_sito() . (string)$p['media_url']) ?>" alt=""></p><?php endif; ?>
  <div class="aic-confronto">
    <?php foreach (['prima' => ['Adesso', 'aic-prima'], 'dopo' => ['Proposta', 'aic-dopo']] as $_k => [$_et, $_cl]): $_im = $p['campo'] === 'immagine' ? (json_decode((string)$p[$_k], true) ?: []) : null; ?>
    <div><div class="aic-etichetta"><?= $_et ?></div><div class="aic-valore <?= $_cl ?>"><?php if ($_im !== null): ?><?php if (($_im['url'] ?? '') !== ''): ?><img class="aic-anteprima" src="<?= h(cms_url_sito() . (string)$_im['url']) ?>" alt=""><br><?= h((string)$_im['url']) ?><?= ($_im['alt'] ?? '') !== '' ? '<br>alt: ' . h((string)$_im['alt']) : '' ?><?php else: ?><i>nessuna immagine</i><?php endif; ?><?php else: ?><?= ($x = $vedi($p[$_k])) !== '' ? nl2br(h($x), false) : '<i>vuoto</i>' ?><?php endif; ?></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($html): ?><details class="aic-html"><summary>Vedi l'HTML proposto</summary><pre><?= h((string)$p['dopo']) ?></pre></details><?php endif; ?>
  <?php if ($p['stato'] === 'attesa'): ?>
  <div class="aic-azioni">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="stato" value="attesa"><button class="btn btn-primary btn-sm" name="azione" value="approva">Approva</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="stato" value="attesa"><button class="btn btn-danger btn-sm" name="azione" value="scarta">Scarta</button></form>
  </div>
  <?php if ($p['campo'] !== 'immagine'): ?>
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
<?php endforeach; ?>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
