<?php
/**
 * Vetrina > Impostazioni del negozio (addon, 2.0.0): nome e indirizzo del negozio, spedizione (costo fisso, gratis
 * oltre una soglia) e ritiro, metodi di pagamento (bonifico con IBAN, alla consegna, link di pagamento PayPal.me o
 * Stripe), condizioni di vendita, dati dell'attività, indirizzo degli avvisi. Valuta: euro.
 * Il link di pagamento si accetta solo https (vt_link_valido): javascript:, data: e simili si rifiutano.
 */
require_once __DIR__ . '/admin-comune.php';
require_once dirname(__DIR__, 3) . '/includes/posta.php';

$errori = [];
$s = vt_impostazioni();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $n = [];
    $n['percorso'] = trim((string)preg_replace('/[^a-z0-9-]+/', '-', mb_strtolower(trim((string)($_POST['percorso'] ?? 'negozio')))), '-') ?: 'negozio';
    if (in_array($n['percorso'], ['admin', 'api', 'install', 'uploads', 'addon', 'moduli', 'temi', 'sito', 'assets', 'includes', 'scarica', 'feed', 'grazie', 'blog', 'category', 'tag'], true)) $errori[] = 'L\'indirizzo «' . $n['percorso'] . '» è già usato dal sito: scegline un altro (per esempio negozio).';
    $n['nome_negozio'] = vt_testo($_POST['nome_negozio'] ?? '', 80) ?: 'Negozio';
    $n['intro'] = vt_testo($_POST['intro'] ?? '', 1000, true);
    $n['spedizione'] = empty($_POST['spedizione']) ? 0 : 1;
    $n['ritiro'] = empty($_POST['ritiro']) ? 0 : 1;
    if (!$n['spedizione'] && !$n['ritiro']) $errori[] = 'Accendi almeno una consegna: spedizione o ritiro in negozio.';
    $c = vt_cent($_POST['spedizione_costo'] ?? '0');
    if ($c === null) $errori[] = 'Il costo della spedizione si scrive in euro, per esempio 7,00.'; else $n['spedizione_cent'] = $c;
    $g = trim((string)($_POST['gratis_oltre'] ?? '')) === '' ? 0 : vt_cent($_POST['gratis_oltre']);
    if ($g === null) $errori[] = 'La soglia della spedizione gratis si scrive in euro, per esempio 60,00 (vuoto: mai gratis).'; else $n['gratis_oltre_cent'] = $g;
    $n['spedizione_testo'] = vt_testo($_POST['spedizione_testo'] ?? '', 300);
    $n['ritiro_testo'] = vt_testo($_POST['ritiro_testo'] ?? '', 300);
    $n['bonifico'] = empty($_POST['bonifico']) ? 0 : 1;
    $iban = strtoupper(preg_replace('/\s+/', '', (string)($_POST['iban'] ?? '')));
    if ($iban !== '' && !preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) $errori[] = 'L\'IBAN non sembra giusto: controlla lettere e numeri (per esempio IT60X0542811101000000123456).';
    $n['iban'] = $iban;
    if ($n['bonifico'] && $iban === '') $errori[] = 'Per il bonifico serve l\'IBAN.';
    $n['intestatario'] = vt_testo($_POST['intestatario'] ?? '', 120);
    $n['banca'] = vt_testo($_POST['banca'] ?? '', 120);
    $n['consegna_pag'] = empty($_POST['consegna_pag']) ? 0 : 1;
    $n['link'] = empty($_POST['link']) ? 0 : 1;
    $link = trim((string)($_POST['link_url'] ?? ''));
    if ($link !== '' && !vt_link_valido($link)) $errori[] = 'Il link di pagamento deve cominciare con https:// ed essere un indirizzo vero (per esempio https://paypal.me/tuonome oppure https://buy.stripe.com/...).';
    else $n['link_url'] = $link;
    if ($n['link'] && $link === '') $errori[] = 'Per il pagamento con il link incolla il tuo link di PayPal.me o di Stripe.';
    if (!$n['bonifico'] && !$n['consegna_pag'] && !$n['link']) $errori[] = 'Accendi almeno un modo di pagare.';
    $n['condizioni'] = vt_testo($_POST['condizioni'] ?? '', 20000, true);
    foreach (['attivita_nome' => 120, 'attivita_indirizzo' => 200, 'attivita_piva' => 30, 'attivita_telefono' => 40] as $k => $max) $n[$k] = vt_testo($_POST[$k] ?? '', $max);
    foreach (['attivita_email', 'email_avvisi'] as $k) {
        $e = trim((string)($_POST[$k] ?? ''));
        if ($e !== '' && (!filter_var($e, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $e))) $errori[] = 'L\'indirizzo «' . $e . '» non è un\'email valida.';
        else $n[$k] = $e;
    }
    $al = (float)str_replace(',', '.', (string)($_POST['aliquota'] ?? '22'));
    if ($al < 0 || $al > 50) $errori[] = 'L\'aliquota IVA va da 0 a 50.'; else $n['aliquota'] = $al;
    if (!$errori) {
        vt_impostazioni_salva($n);
        flash('success', 'Impostazioni del negozio salvate.');
        vt_admin_vai('impostazioni');
    }
    $s = $n + $s;
}
$adminTitle = 'Impostazioni del negozio';
require CMS_ADMIN . '/includes/admin-header.php';
echo vt_admin_stile();
$campo = fn(string $k, string $et, string $ph = '', string $tipo = 'text', int $max = 200) => '<div class="form-group"><label for="vt-' . $k . '">' . h($et) . '</label><input type="' . $tipo . '" id="vt-' . $k . '" name="' . $k . '" maxlength="' . $max . '" value="' . h((string)$s[$k]) . '" placeholder="' . h($ph) . '"></div>';
$spunta = fn(string $k, string $et) => '<label class="vt-a-spunta"><input type="checkbox" name="' . $k . '" value="1"' . (!empty($s[$k]) ? ' checked' : '') . '> ' . h($et) . '</label>';
?>
<?php if ($errori): ?><div class="flash flash-error" role="alert" id="vt-errori"><?= implode('<br>', array_map('h', $errori)) ?></div><?php endif; ?>
<form method="post" id="vt-impostazioni"><?= csrf_field() ?>
<div class="vt-a-griglia">
<div>
  <div class="form-card"><h3>Il negozio sul sito</h3>
    <?= $campo('nome_negozio', 'Nome del negozio (titolo della pagina)', 'Negozio', 'text', 80) ?>
    <div class="form-group"><label for="vt-percorso">Indirizzo</label><input type="text" id="vt-percorso" name="percorso" maxlength="40" value="<?= h((string)$s['percorso']) ?>"><p class="hint">Il negozio si apre da <b><?= h(rtrim(cms_url_sito(), '/')) ?>/<?= h((string)$s['percorso']) ?>/</b>. Mettilo nel menu da Editor &gt; Menu di navigazione.</p></div>
    <div class="form-group"><label for="vt-intro">Frase sotto il titolo</label><textarea id="vt-intro" name="intro" class="vt-a-corto" maxlength="1000"><?= h((string)$s['intro']) ?></textarea></div>
  </div>
  <div class="form-card" style="margin-top:1rem"><h3>Consegna</h3>
    <?= $spunta('spedizione', 'Spedizione con costo fisso') ?>
    <div class="vt-a-campi">
      <div class="form-group"><label for="vt-spedizione_costo">Costo della spedizione (€)</label><input type="text" id="vt-spedizione_costo" name="spedizione_costo" inputmode="decimal" value="<?= h(vt_cent_campo((int)$s['spedizione_cent'])) ?>"></div>
      <div class="form-group"><label for="vt-gratis_oltre">Gratis da (€, vuoto: mai)</label><input type="text" id="vt-gratis_oltre" name="gratis_oltre" inputmode="decimal" value="<?= (int)$s['gratis_oltre_cent'] ? h(vt_cent_campo((int)$s['gratis_oltre_cent'])) : '' ?>"></div>
    </div>
    <?= $campo('spedizione_testo', 'Frase sulla spedizione', 'Corriere in 2-4 giorni lavorativi', 'text', 300) ?>
    <?= $spunta('ritiro', 'Ritiro in negozio (gratis)') ?>
    <?= $campo('ritiro_testo', 'Frase sul ritiro', 'Via Roma 1, dal martedì al sabato', 'text', 300) ?>
  </div>
  <div class="form-card" style="margin-top:1rem"><h3>Pagamento</h3>
    <p class="hint" style="margin-top:0">Nessun pagamento passa dal sito: il cliente paga con il metodo scelto e tu segni l'ordine come «Pagato» quando vedi i soldi.</p>
    <?= $spunta('bonifico', 'Bonifico bancario (IBAN e causale con il numero d\'ordine)') ?>
    <div class="vt-a-campi"><?= $campo('iban', 'IBAN', 'IT60X0542811101000000123456', 'text', 40) ?><?= $campo('intestatario', 'Intestato a', 'Bottega Rossi di Anna Rossi', 'text', 120) ?><?= $campo('banca', 'Banca (facoltativa)', '', 'text', 120) ?></div>
    <?= $spunta('consegna_pag', 'Pagamento alla consegna o al ritiro') ?>
    <?= $spunta('link', 'Link di pagamento (PayPal.me o Stripe Payment Link)') ?>
    <?= $campo('link_url', 'Il tuo link di pagamento', 'https://paypal.me/tuonome', 'url', 300) ?>
    <p class="hint">Con <b>PayPal.me</b> l'importo dell'ordine si aggiunge da solo al link. Con un <b>Payment Link di Stripe</b> crea il link con «Lascia che il cliente scelga l'importo»: il numero d'ordine arriva a Stripe come riferimento.</p>
  </div>
  <div class="form-card" style="margin-top:1rem"><h3>Condizioni di vendita</h3>
    <div class="form-group"><label for="vt-condizioni">Testo (pagina <?= h(vt_base()) ?>condizioni/)</label><textarea id="vt-condizioni" name="condizioni" class="vt-a-medio" maxlength="20000"><?= h((string)$s['condizioni']) ?></textarea>
      <p class="hint">Chi ordina spunta di averle lette. Scrivi chi vende, prezzi, tempi di consegna, diritto di recesso di 14 giorni, resi e garanzia. Una riga vuota fra i paragrafi.</p></div>
  </div>
</div>
<aside>
  <div class="form-card"><h3>I dati dell'attività</h3>
    <?= $campo('attivita_nome', 'Nome o ragione sociale', 'Bottega Rossi', 'text', 120) ?>
    <?= $campo('attivita_indirizzo', 'Indirizzo', 'Via Roma 1, 40100 Bologna', 'text', 200) ?>
    <?= $campo('attivita_piva', 'Partita IVA', '01234567890', 'text', 30) ?>
    <?= $campo('attivita_telefono', 'Telefono', '', 'text', 40) ?>
    <?= $campo('attivita_email', 'Email dell\'attività', '', 'email', 190) ?>
    <p class="hint">Escono in fondo alle email degli ordini e alle condizioni di vendita.</p>
  </div>
  <div class="form-card" style="margin-top:1rem"><h3>Avvisi e IVA</h3>
    <?= $campo('email_avvisi', 'Gli ordini nuovi arrivano a', posta_notifica_email() ?: 'ordini@esempio.it', 'email', 190) ?>
    <p class="hint">Vuoto: all'email dell'attività, se no all'indirizzo degli avvisi del sito (Impostazioni &gt; Configurazione email).</p>
    <div class="form-group"><label for="vt-aliquota">IVA predefinita (%)</label><input type="text" id="vt-aliquota" name="aliquota" value="<?= h((string)$s['aliquota']) ?>"><p class="hint">I prezzi si scrivono IVA inclusa. Valuta: euro.</p></div>
    <button type="submit" class="btn btn-primary" id="vt-salva">Salva le impostazioni</button>
  </div>
</aside>
</div>
</form>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
