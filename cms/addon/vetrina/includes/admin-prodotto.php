<?php
/**
 * Vetrina > scheda di un prodotto (addon, 2.0.0): nome, descrizioni, prezzo e prezzo scontato (IVA inclusa), foto
 * (più di una: si caricano qui e vanno nella libreria Media, cartella vetrina/), categorie, scorte, varianti semplici
 * (fino a due caratteristiche, per esempio Taglia e Colore, con prezzo e scorte per variante), evidenza e pubblicazione.
 */
require_once __DIR__ . '/admin-comune.php';
require_once dirname(__DIR__, 3) . '/includes/media.php';

$id = (int)($_GET['id'] ?? 0);
$p = $id ? vt_prodotto($id, false) : null;
if ($id && !$p) { flash('error', 'Prodotto non trovato.'); vt_admin_vai('prodotti'); }

$errore = '';
$d = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $d = $_POST;
    // Le foto: quelle che c'erano (meno quelle da togliere, la principale per prima), più quelle caricate adesso.
    $foto = array_values(array_filter(array_map('strval', (array)($_POST['foto'] ?? [])), fn($u) => !in_array($u, (array)($_POST['togli_foto'] ?? []), true)));
    $princ = (string)($_POST['principale'] ?? '');
    if ($princ !== '' && in_array($princ, $foto, true)) $foto = array_values(array_unique(array_merge([$princ], $foto)));
    $avvisi = [];
    $file = $_FILES['foto_nuove'] ?? null;
    if ($file && is_array($file['name'] ?? null)) {
        foreach ($file['name'] as $i => $nome) {
            if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $r = media_carica(['name' => $nome, 'type' => $file['type'][$i] ?? '', 'tmp_name' => $file['tmp_name'][$i] ?? '', 'error' => $file['error'][$i] ?? 4, 'size' => $file['size'][$i] ?? 0], 'vetrina');
            if (isset($r['url'])) $foto[] = $r['url']; else $avvisi[] = $nome . ': ' . $r['errore'];
        }
    }
    $d['foto'] = $foto;
    $d['varianti'] = (array)($_POST['var'] ?? []);
    $d['categorie'] = (array)($_POST['categorie'] ?? []);
    $r = vt_prodotto_salva($id, $d);
    if (is_int($r)) {
        flash($avvisi ? 'error' : 'success', 'Prodotto salvato.' . ($avvisi ? ' Alcune foto non sono state caricate: ' . implode('; ', $avvisi) : ''));
        vt_admin_vai('prodotto', ['id' => $r]);
    }
    $errore = $r;
    // Si resta sulla pagina con quello che è stato scritto.
    $p = ($p ?? ['id' => 0, 'varianti' => [], 'categorie' => []]);
}

$val = function (string $k, $pred = '') use ($d, $p) {
    if ($d !== null) return is_scalar($d[$k] ?? null) ? (string)$d[$k] : (string)$pred;
    return $p !== null && array_key_exists($k, $p) && is_scalar($p[$k]) ? (string)$p[$k] : (string)$pred;
};
$spunta = fn(string $k, bool $pred) => $d !== null ? !empty($d[$k]) : ($p !== null && isset($p[$k]) ? (bool)$p[$k] : $pred);
$fotoOra = $d !== null ? (array)$d['foto'] : ($p['foto'] ?? []);
$catOra = array_map('intval', $d !== null ? (array)$d['categorie'] : ($p['categorie'] ?? []));
$varianti = $d !== null ? array_values(array_filter((array)$d['varianti'], 'is_array')) : array_map(fn($v) => ['id' => $v['id'], 'valore1' => $v['valore1'], 'valore2' => $v['valore2'], 'prezzo' => vt_cent_campo($v['prezzo_cent']), 'scorta' => $v['scorta'] === null ? '' : (string)$v['scorta']], $p['varianti'] ?? []);
$varianti = array_values(array_filter($varianti, fn($v) => trim(($v['valore1'] ?? '') . ($v['valore2'] ?? '')) !== '' || !empty($v['id'])));
for ($i = 0; $i < 3; $i++) $varianti[] = ['id' => 0, 'valore1' => '', 'valore2' => '', 'prezzo' => '', 'scorta' => ''];

$adminTitle = $p && !empty($p['id']) ? 'Prodotto: ' . $p['nome'] : 'Nuovo prodotto';
require CMS_ADMIN . '/includes/admin-header.php';
echo vt_admin_stile();
$categorie = vt_categorie();
?>
<p><a href="<?= h(vt_admin_url('prodotti')) ?>">&lsaquo; Tutti i prodotti</a><?php if ($p && !empty($p['id']) && !empty($p['pubblicato'])): ?> &middot; <a href="<?= h(rtrim(cms_url_sito(), '/') . vt_base() . $p['slug'] . '/') ?>" target="_blank">Vedi sul sito</a><?php endif; ?></p>
<?php if ($errore !== ''): ?><div class="flash flash-error" role="alert" id="vt-errore"><?= h($errore) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" id="vt-prodotto"><?= csrf_field() ?>
<div class="vt-a-griglia">
  <div>
    <div class="form-card">
      <h3>Il prodotto</h3>
      <div class="form-group"><label for="vt-nome">Nome</label><input type="text" id="vt-nome" name="nome" maxlength="200" required value="<?= h($val('nome')) ?>" placeholder="Per esempio: Tazza in ceramica smaltata"></div>
      <div class="form-group"><label for="vt-breve">Frase breve (sotto il nome, nella griglia)</label><input type="text" id="vt-breve" name="breve" maxlength="400" value="<?= h($val('breve')) ?>" placeholder="Fatta a mano, 300 ml"></div>
      <div class="form-group"><label for="vt-descrizione">Descrizione</label><textarea id="vt-descrizione" name="descrizione" class="vt-a-medio" maxlength="20000" placeholder="Materiali, misure, come si usa, come si lava. Una riga vuota fra un paragrafo e l'altro."><?= h($val('descrizione')) ?></textarea></div>
      <div class="vt-a-campi">
        <div class="form-group"><label for="vt-prezzo">Prezzo (€, IVA inclusa)</label><input type="text" id="vt-prezzo" name="prezzo" inputmode="decimal" required value="<?= h($d !== null ? $val('prezzo') : vt_cent_campo($p['prezzo_cent'] ?? null)) ?>" placeholder="24,90"></div>
        <div class="form-group"><label for="vt-scontato">Prezzo scontato (€, facoltativo)</label><input type="text" id="vt-scontato" name="scontato" inputmode="decimal" value="<?= h($d !== null ? $val('scontato') : vt_cent_campo($p['scontato_cent'] ?? null)) ?>" placeholder="19,90"></div>
        <div class="form-group"><label for="vt-aliquota">IVA compresa (%)</label><input type="text" id="vt-aliquota" name="aliquota" inputmode="decimal" value="<?= h($val('aliquota', (string)vt_impostazioni()['aliquota'])) ?>"></div>
        <div class="form-group"><label for="vt-codice">Codice (facoltativo)</label><input type="text" id="vt-codice" name="codice" maxlength="60" value="<?= h($val('codice')) ?>" placeholder="TZ-01"></div>
      </div>
    </div>

    <div class="form-card" style="margin-top:1rem">
      <h3>Foto</h3>
      <div class="vt-a-fotolista" id="vt-foto">
        <?php foreach ($fotoOra as $i => $f): if (!vt_foto_ammessa((string)$f)) continue; ?>
          <figure><img src="<?= h((string)$f) ?>" alt=""><input type="hidden" name="foto[]" value="<?= h((string)$f) ?>">
            <label class="vt-a-spunta"><input type="radio" name="principale" value="<?= h((string)$f) ?>"<?= $i === 0 ? ' checked' : '' ?>> Principale</label>
            <label class="vt-a-spunta"><input type="checkbox" name="togli_foto[]" value="<?= h((string)$f) ?>"> Togli</label></figure>
        <?php endforeach; ?>
      </div>
      <?php // 2.1.0: una foto già in Media (o caricata dalla finestra) si aggiunge senza ricaricarla: pannello.js copia questo modello. ?>
      <template id="vt-foto-modello"><figure><img data-url-src src="" alt=""><input type="hidden" name="foto[]" data-url-valore value="">
        <label class="vt-a-spunta"><input type="radio" name="principale" data-url-valore value=""> Principale</label>
        <label class="vt-a-spunta"><input type="checkbox" name="togli_foto[]" data-url-valore value=""> Togli</label></figure></template>
      <div class="form-group" style="margin-top:.8rem"><button type="button" class="btn btn-secondary btn-sm" data-scegli-media-lista="#vt-foto" data-scegli-media-modello="#vt-foto-modello" data-scegli-media-titolo="Aggiungi una foto dai Media">Scegli dai Media</button></div>
      <div class="form-group"><label for="vt-foto-nuove">Oppure carica dal computer (anche più di una insieme)</label><input type="file" id="vt-foto-nuove" name="foto_nuove[]" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" multiple>
        <p class="hint">JPG, PNG o WebP. La foto principale è quella della griglia e di Google. Le foto si alleggeriscono da sole e finiscono anche in Media; dai Media si possono aggiungere solo le immagini caricate (non quelle del pannello).</p></div>
    </div>

    <div class="form-card" style="margin-top:1rem">
      <h3>Varianti (facoltative)</h3>
      <p class="hint" style="margin-top:0">Se il prodotto ha taglie, colori o formati, scrivi qui i nomi delle caratteristiche e una riga per variante. Prezzo vuoto: vale quello del prodotto. Scorte vuote: non le conti.</p>
      <div class="vt-a-campi">
        <div class="form-group"><label for="vt-opz1">Prima caratteristica</label><input type="text" id="vt-opz1" name="opzione1" maxlength="40" value="<?= h($val('opzione1')) ?>" placeholder="Taglia"></div>
        <div class="form-group"><label for="vt-opz2">Seconda caratteristica (facoltativa)</label><input type="text" id="vt-opz2" name="opzione2" maxlength="40" value="<?= h($val('opzione2')) ?>" placeholder="Colore"></div>
      </div>
      <div class="vt-a-tabella-wrap"><table class="vt-a-varianti" id="vt-varianti"><thead><tr><th>Prima</th><th>Seconda</th><th>Prezzo €</th><th>Scorte</th><th>Togli</th></tr></thead><tbody>
        <?php foreach ($varianti as $i => $v): ?>
          <tr><td><input type="hidden" name="var[<?= $i ?>][id]" value="<?= (int)($v['id'] ?? 0) ?>"><input type="text" name="var[<?= $i ?>][valore1]" maxlength="60" value="<?= h((string)($v['valore1'] ?? '')) ?>" aria-label="Prima caratteristica, riga <?= $i + 1 ?>"></td>
            <td><input type="text" name="var[<?= $i ?>][valore2]" maxlength="60" value="<?= h((string)($v['valore2'] ?? '')) ?>" aria-label="Seconda caratteristica, riga <?= $i + 1 ?>"></td>
            <td><input type="text" name="var[<?= $i ?>][prezzo]" inputmode="decimal" value="<?= h((string)($v['prezzo'] ?? '')) ?>" aria-label="Prezzo, riga <?= $i + 1 ?>" style="max-width:7rem"></td>
            <td><input type="text" name="var[<?= $i ?>][scorta]" inputmode="numeric" value="<?= h((string)($v['scorta'] ?? '')) ?>" aria-label="Scorte, riga <?= $i + 1 ?>" style="max-width:5rem"></td>
            <td><?php if (!empty($v['id'])): ?><input type="checkbox" name="var[<?= $i ?>][togli]" value="1" aria-label="Togli la riga <?= $i + 1 ?>"><?php endif; ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <p class="hint">Servono più righe? Salva: ne compaiono altre tre vuote.</p>
    </div>
  </div>
  <aside>
    <div class="form-card">
      <h3>Pubblicazione</h3>
      <label class="vt-a-spunta"><input type="checkbox" name="pubblicato" value="1"<?= $spunta('pubblicato', true) ? ' checked' : '' ?>> Sul sito</label>
      <label class="vt-a-spunta"><input type="checkbox" name="evidenza" value="1"<?= $spunta('evidenza', false) ? ' checked' : '' ?>> In evidenza (nel widget Prodotti)</label>
      <label class="vt-a-spunta"><input type="checkbox" name="esaurito" value="1"<?= $spunta('esaurito', false) ? ' checked' : '' ?>> Esaurito (non si può ordinare)</label>
      <div class="form-group" style="margin-top:.8rem"><label for="vt-scorta">Scorte (senza varianti)</label><input type="text" id="vt-scorta" name="scorta" inputmode="numeric" value="<?= h($d !== null ? $val('scorta') : (($p['scorta'] ?? null) === null ? '' : (string)$p['scorta'])) ?>" placeholder="vuoto: non le conto">
        <p class="hint">Scendono da sole a ogni ordine. A zero il prodotto risulta esaurito.</p></div>
      <div class="form-group"><label for="vt-slug">Indirizzo</label><input type="text" id="vt-slug" name="slug" maxlength="120" value="<?= h($val('slug')) ?>" placeholder="dal nome"><p class="hint"><?= h(vt_base()) ?>…/</p></div>
      <div class="form-group"><label for="vt-ordine">Posizione nell'elenco</label><input type="number" id="vt-ordine" name="ordine" min="-9999" max="9999" value="<?= h($val('ordine', '0')) ?>"><p class="hint">I numeri più bassi vengono prima.</p></div>
      <button type="submit" class="btn btn-primary" id="vt-salva">Salva il prodotto</button>
    </div>
    <div class="form-card" style="margin-top:1rem">
      <h3>Categorie</h3>
      <?php if (!$categorie): ?><p class="hint">Nessuna categoria. Le crei in <a href="<?= h(vt_admin_url('categorie')) ?>">Vetrina &gt; Categorie</a>.</p><?php endif; ?>
      <?php foreach ($categorie as $c): ?><label class="vt-a-spunta"><input type="checkbox" name="categorie[]" value="<?= (int)$c['id'] ?>"<?= in_array((int)$c['id'], $catOra, true) ? ' checked' : '' ?>> <?= h($c['nome']) ?></label><?php endforeach; ?>
    </div>
  </aside>
</div>
</form>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
