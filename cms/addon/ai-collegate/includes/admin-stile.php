<?php
/**
 * AI collegate > Stile del sito (2.34.0): il profilo di stile scritto dall'AI (save_style_profile) o a mano, con le misure degli
 * articoli in parole, chi l'ha salvato e quando, la correzione a mano (Salva il profilo) e le ultime dieci versioni con
 * Ripristina. Solo amministratore (addon-pagina.php), azioni con CSRF. Manuale tecnico, 47.18.
 */
require_once __DIR__ . '/admin-comune.php';
require_once __DIR__ . '/stile.php';
foreach (['contenuti.php', 'seo.php'] as $_aicF) require_once cms_radice() . '/includes/' . $_aicF;
unset($_aicF);
$adminTitle = 'Stile del sito';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $chi = (string)(current_user()['username'] ?? '');
    $azione = (string)($_POST['azione'] ?? '');
    try {
        if ($azione === 'salva') {
            $testo = aic_stile_testo_pulito((string)($_POST['testo'] ?? ''));
            $ora = aic_stile_profilo();
            if ($ora && trim((string)$ora['testo']) === $testo) flash('info', 'Il profilo non è cambiato: niente da salvare.');
            else {
                aic_stile_salva($testo, $ora ? (array)$ora['misure'] : aic_stile_misure_breve(aic_stile_analizza()), $chi, 0, 'pannello');
                aic_registra(['id' => 0, 'nome' => $chi], 'stile', 'ok', 'profilo di stile corretto a mano da ' . $chi);
                flash('success', 'Profilo di stile salvato. La versione di prima resta qui sotto, fra le versioni precedenti.');
            }
        } elseif ($azione === 'misura') {
            $ora = aic_stile_profilo();
            if (!$ora) throw new RuntimeException('Prima serve un profilo: scrivilo qui sopra o chiedilo alla tua AI.');
            aic_stile_salva((string)$ora['testo'], aic_stile_misure_breve(aic_stile_analizza()), $chi, 0, 'misure');
            flash('success', 'Misure ricalcolate sugli articoli pubblicati di adesso. Il testo del profilo è lo stesso.');
        } elseif ($azione === 'ripristina') {
            aic_stile_ripristina(ctype_digit((string)($_POST['id'] ?? '')) ? (int)$_POST['id'] : 0, $chi);
            aic_registra(['id' => 0, 'nome' => $chi], 'stile', 'ok', 'versione del profilo di stile ripristinata da ' . $chi);
            flash('success', 'Versione ripristinata: ora è il profilo in uso. Quella che c\'era resta fra le versioni precedenti.');
        }
    } catch (AicErrore | RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    aic_admin_vai('stile');
}
$p = aic_stile_profilo();
$versioni = aic_stile_versioni();
$m = $p['misure'] ?? [];
$origini = ['ai' => 'scritto dall\'AI', 'pannello' => 'corretto a mano', 'ripristino' => 'versione ripristinata', 'misure' => 'misure ricalcolate'];
$persone = ['tu' => 'Dà del tu a chi legge', 'voi' => 'Dà del voi a chi legge', 'noi' => 'Parla con il noi', 'impersonale' => 'Impersonale (né tu, né voi, né noi)'];
$num = fn($x) => is_numeric($x) ? str_replace('.', ',', (string)(0 + $x)) : '&ndash;';
require CMS_ADMIN . '/includes/admin-header.php';
aic_admin_schede('stile');
?>
<div class="form-card" id="profilo">
  <h3>Il profilo di stile</h3>
  <p class="hint">Come scrivono gli articoli del sito: tono, persona, parole da usare e da evitare, struttura, regole. La tua AI lo legge prima di scrivere un articolo nuovo, così le bozze somigliano a quelle che hai già.</p>
  <?php if ($p): ?>
  <p class="aic-chi-salvato">Salvato il <b><?= h(aic_data($p['creato'])) ?></b> da <b><?= h((string)$p['autore']) ?></b> (<?= h($origini[$p['origine']] ?? (string)$p['origine']) ?>).</p>
  <?php else: ?>
  <div class="empty-state">Ancora nessun profilo di stile. Chiedi alla tua AI: «Analizza lo stile degli articoli del sito e salva il profilo di stile». Oppure scrivilo tu qui sotto.</div>
  <?php endif; ?>
  <form method="post" class="aic-profilo-form">
    <?= csrf_field() ?><input type="hidden" name="azione" value="salva">
    <div class="form-group">
      <label for="aic-profilo-testo">Testo del profilo</label>
      <textarea id="aic-profilo-testo" name="testo" rows="16" maxlength="<?= AIC_STILE_PROFILO_MAX ?>" aria-describedby="aic-profilo-aiuto" placeholder="per esempio: Tono: amichevole e diretto. Persona: diamo del tu."><?= h((string)($p['testo'] ?? '')) ?></textarea>
      <p class="hint" id="aic-profilo-aiuto">Solo testo, senza codice; al massimo <?= number_format(AIC_STILE_PROFILO_MAX, 0, ',', '.') ?> caratteri. Correggi quello che non ti somiglia: la versione di prima resta recuperabile.</p>
    </div>
    <button class="btn btn-primary" type="submit">Salva il profilo</button>
  </form>
</div>

<div class="form-card" id="misure">
  <h3>Le misure degli articoli</h3>
  <?php if (!$m || empty($m['lunghezza'])): ?>
  <div class="empty-state"><?= $p ? 'Il profilo non ha misure: il sito non aveva articoli pubblicati quando è stato salvato. Pubblica qualche articolo, poi premi «Ricalcola le misure».' : 'Le misure arrivano con il profilo: le conta il sito sugli articoli pubblicati (al massimo gli ultimi ' . AIC_STILE_MAX_ARTICOLI . ').' ?></div>
  <?php else: $pe = $m['persona'] ?? []; ?>
  <p class="hint">Contate dal sito il <?= h(aic_data($m['calcolato'] ?? null)) ?> su <?= (int)($m['articoli_misurati'] ?? 0) ?> articoli pubblicati<?= isset($m['articoli_pubblicati']) && (int)$m['articoli_pubblicati'] > (int)$m['articoli_misurati'] ? ' (i più recenti di ' . (int)$m['articoli_pubblicati'] . ')' : '' ?>. Nessuna è inventata.</p>
  <dl class="aic-misure">
    <dt>Lunghezza</dt><dd><?= $num($m['lunghezza']['parole_mediana'] ?? null) ?> parole a metà classifica (media <?= $num($m['lunghezza']['parole_media'] ?? null) ?>; dalla più corta di <?= $num($m['lunghezza']['parole_minimo'] ?? null) ?> alla più lunga di <?= $num($m['lunghezza']['parole_massimo'] ?? null) ?>)</dd>
    <dt>Frasi</dt><dd><?= $num($m['frasi']['parole_per_frase_media'] ?? null) ?> parole per frase in media</dd>
    <dt>Paragrafi</dt><dd><?= $num($m['paragrafi']['parole_per_paragrafo_media'] ?? null) ?> parole per paragrafo, <?= $num($m['paragrafi']['paragrafi_per_articolo'] ?? null) ?> paragrafi per articolo</dd>
    <dt>Sottotitoli</dt><dd><?= $num($m['sottotitoli']['h2_per_articolo'] ?? null) ?> H2 e <?= $num($m['sottotitoli']['h3_per_articolo'] ?? null) ?> H3 per articolo; senza H2 il <?= (int)($m['sottotitoli']['articoli_senza_h2_percento'] ?? 0) ?>%</dd>
    <dt>Elenchi puntati</dt><dd>nel <?= (int)($m['elenchi']['articoli_con_elenchi_percento'] ?? 0) ?>% degli articoli</dd>
    <dt>Domande a chi legge</dt><dd><?= $num($m['domande_ai_lettori']['per_articolo'] ?? null) ?> per articolo; il <?= (int)($m['domande_ai_lettori']['attacchi_con_domanda_percento'] ?? 0) ?>% comincia con una domanda</dd>
    <dt>Persona</dt><dd><?= h($persone[$pe['dominante'] ?? ''] ?? 'non misurata') ?><?php if (!empty($pe['articoli'])): ?> (articoli: tu <?= (int)($pe['articoli']['tu'] ?? 0) ?>, voi <?= (int)($pe['articoli']['voi'] ?? 0) ?>, noi <?= (int)($pe['articoli']['noi'] ?? 0) ?>, impersonali <?= (int)($pe['articoli']['impersonale'] ?? 0) ?>)<?php endif; ?></dd>
    <dt>Grassetti</dt><dd><?= $num($m['grassetti']['per_articolo'] ?? null) ?> per articolo</dd>
    <dt>Emoji</dt><dd><?= $num($m['emoji']['per_articolo'] ?? null) ?> per articolo</dd>
    <dt>Link interni</dt><dd><?= $num($m['link_interni']['per_articolo'] ?? null) ?> per articolo; senza link il <?= (int)($m['link_interni']['articoli_senza_link_percento'] ?? 0) ?>%</dd>
    <dt>Immagini</dt><dd><?= $num($m['immagini']['per_articolo'] ?? null) ?> per articolo</dd>
    <dt>Domande frequenti</dt><dd>nel <?= (int)($m['domande_frequenti']['articoli_con_domande_frequenti_percento'] ?? 0) ?>% degli articoli</dd>
    <dt>Titoli</dt><dd><?= $num($m['titoli']['caratteri_mediana'] ?? null) ?> caratteri; titolo per Google <?= $num($m['titoli']['titolo_google_media'] ?? null) ?>, descrizione per Google <?= $num($m['titoli']['descrizione_google_media'] ?? null) ?> caratteri in media</dd>
    <?php if (!empty($m['categorie'])): ?><dt>Categorie più usate</dt><dd><?= h(implode(', ', array_map(fn($c) => $c['categoria'] . ' (' . $c['articoli'] . ')', array_slice($m['categorie'], 0, 6)))) ?></dd><?php endif; ?>
    <?php if (!empty($m['tag'])): ?><dt>Tag più usati</dt><dd><?= h(implode(', ', array_map(fn($c) => $c['tag'] . ' (' . $c['articoli'] . ')', array_slice($m['tag'], 0, 8)))) ?></dd><?php endif; ?>
    <?php if (!empty($m['ricorrenti']['parole'])): ?><dt>Parole ricorrenti</dt><dd><?= h(implode(', ', array_column(array_slice($m['ricorrenti']['parole'], 0, 15), 'testo'))) ?></dd><?php endif; ?>
    <?php if (!empty($m['ricorrenti']['espressioni_di_2'])): ?><dt>Espressioni ricorrenti</dt><dd><?= h(implode(', ', array_column(array_slice($m['ricorrenti']['espressioni_di_2'], 0, 10), 'testo'))) ?></dd><?php endif; ?>
    <?php if (!empty($m['widget_editor']['sequenze_piu_comuni'])): ?><dt>Struttura tipica nell'editor</dt><dd><?= h((string)$m['widget_editor']['sequenze_piu_comuni'][0]['sequenza']) ?> (<?= (int)$m['widget_editor']['sequenze_piu_comuni'][0]['articoli'] ?> articoli)</dd><?php endif; ?>
  </dl>
  <?php endif; ?>
  <?php if ($p): ?>
  <form method="post" class="aic-azioni"><?= csrf_field() ?><input type="hidden" name="azione" value="misura"><button class="btn btn-secondary" type="submit">Ricalcola le misure</button><span class="hint">Conta di nuovo sugli articoli pubblicati di adesso; il testo del profilo resta.</span></form>
  <?php endif; ?>
</div>

<div class="form-card" id="versioni">
  <h3>Versioni precedenti</h3>
  <?php if (!$versioni): ?>
  <div class="empty-state">Ancora nessuna versione precedente: quando il profilo cambia (dall'AI o da qui), quello di prima resta in questo elenco. Si tengono le ultime <?= AIC_STILE_VERSIONI ?>.</div>
  <?php else: ?>
  <table class="table" id="aic-versioni">
    <thead><tr><th>Quando</th><th>Chi</th><th>Inizio del testo</th><th>Azione</th></tr></thead>
    <tbody>
    <?php foreach ($versioni as $v): ?>
    <tr>
      <td><?= h(aic_data($v['creato'])) ?></td>
      <td><?= h((string)$v['autore']) ?><br><span class="hint"><?= h($origini[$v['origine']] ?? (string)$v['origine']) ?></span></td>
      <td class="aic-inizio"><?= h(mb_substr((string)$v['inizio'], 0, 160)) ?><?= (int)$v['caratteri'] > 160 ? '...' : '' ?> <span class="hint">(<?= (int)$v['caratteri'] ?> caratteri)</span></td>
      <td><form method="post" data-conferma="Rimettere in uso questa versione del profilo di stile? Quella di adesso resta fra le versioni precedenti." data-conferma-ok="Ripristina la versione"><?= csrf_field() ?><input type="hidden" name="azione" value="ripristina"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Ripristina</button></form></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
