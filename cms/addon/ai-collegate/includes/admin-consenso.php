<?php
/**
 * AI collegate > il consenso: un'AI chiede di collegarsi (dalla pagina /api/mcp/authorize del sito, dopo il login).
 * Solo l'amministratore arriva qui (addon-pagina.php); mostra nome dell'AI, indirizzo di ritorno e permessi chiesti
 * come caselle; Consenti o Rifiuta con il token CSRF. Dopo, si torna all'AI con una pagina e non con un redirect del
 * modulo: la CSP del pannello (form-action 'self') bloccherebbe il salto verso un altro sito dopo un POST.
 */
require_once __DIR__ . '/admin-comune.php';
require_once __DIR__ . '/oauth.php';
$adminTitle = 'Collegare un\'AI al sito';
$r = aic_richiesta((string)($_GET['r'] ?? ''));
$ritorno = '';
$esito = '';
if ($r && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $u = current_user();
    if (($_POST['azione'] ?? '') === 'consenti') {
        $scelti = array_values(array_intersect(aic_scope_pulito((array)($_POST['permessi'] ?? [])), $r['scope']));
        if (!cms_modulo('statistiche')) $scelti = array_values(array_diff($scelti, ['statistiche']));
        if (!$scelti) { flash('error', 'Spunta almeno un permesso, oppure premi Rifiuta.'); aic_admin_vai('consenso', ['r' => $r['id']]); }
        $ritorno = aic_consenso_dato($r, (int)$u['id'], $scelti);
        $esito = 'consenti';
        aic_registra(['nome' => $r['client']['nome']], 'consenso', 'ok', 'permesso dato da ' . $u['username'] . ': ' . implode(', ', $scelti));
        sicurezza_log('ai_consenso', (string)$u['username'], (int)$u['id'], 'AI «' . $r['client']['nome'] . '» verso ' . parse_url($r['redirect_uri'], PHP_URL_HOST) . ', permessi: ' . implode(', ', $scelti));
    } else {
        $ritorno = aic_consenso_negato($r);
        $esito = 'rifiuta';
        aic_registra(['nome' => $r['client']['nome']], 'consenso', 'negato', 'permesso rifiutato da ' . $u['username']);
    }
}
require CMS_ADMIN . '/includes/admin-header.php';
echo '<link rel="stylesheet" href="' . h(cms_base() . '/addon/ai-collegate/assets/admin.css?v=' . (int)@filemtime(dirname(__DIR__) . '/assets/admin.css')) . '">';
if ($ritorno !== ''): ?>
<div class="form-card aic-consenso" id="aic-fatto">
  <h3><?= $esito === 'consenti' ? 'Permesso dato' : 'Permesso rifiutato' ?></h3>
  <p role="status"><?= $esito === 'consenti' ? 'Fatto: «' . h($r['client']['nome']) . '» è collegata al sito. Adesso torni alla tua AI.' : 'Hai rifiutato: «' . h($r['client']['nome']) . '» non è collegata. Adesso torni alla tua AI.' ?></p>
  <p><a class="btn btn-primary" id="aic-torna" href="<?= h($ritorno) ?>" rel="noopener">Torna alla tua AI</a></p>
  <p class="hint">Se non torni da solo fra un momento, premi il pulsante. Puoi revocare il collegamento quando vuoi da <a href="<?= h(aic_admin_url('collegate')) ?>">AI collegate</a>.</p>
</div>
<script<?= sicurezza_nonce_attr() ?>>setTimeout(function () { var a = document.getElementById('aic-torna'); if (a) location.replace(a.href); }, 600);</script>
<?php elseif (!$r): ?>
<div class="form-card aic-consenso">
  <h3>Richiesta scaduta o non valida</h3>
  <div class="empty-state" role="alert">Questa richiesta di collegamento non c'è più (dura un quarto d'ora) oppure il link è sbagliato. Ricomincia il collegamento dalla tua AI: premi di nuovo <b>Connetti</b>.</div>
</div>
<?php else: $host = (string)parse_url($r['redirect_uri'], PHP_URL_HOST); ?>
<form method="post" class="form-card aic-consenso" id="aic-consenso">
  <?= csrf_field() ?>
  <h3>Collegare un'AI al sito</h3>
  <p class="aic-chi"><b><?= h((string)$r['client']['nome']) ?></b> chiede di collegarsi a <b><?= h(aic_testo(cms_impostazione('sito_nome', CMS_NOME), 100)) ?></b>.</p>
  <dl class="aic-dati">
    <dt>Torna a</dt><dd><code><?= h($host) ?></code></dd>
    <?php if ($r['client']['sito'] !== ''): ?><dt>Sito dell'AI</dt><dd><code><?= h((string)parse_url($r['client']['sito'], PHP_URL_HOST)) ?></code></dd><?php endif; ?>
    <dt>Registrata il</dt><dd><?= h(aic_data($r['client']['creato'])) ?></dd>
  </dl>
  <p class="hint">Consenti solo se hai appena premuto Connetti nella tua AI e l'indirizzo "Torna a" è quello della tua AI (per esempio <code>claude.ai</code> o <code>chatgpt.com</code>). Se non sai da dove arriva questa richiesta, premi Rifiuta.</p>
  <?= aic_caselle_permessi('permessi', $r['scope'], $r['scope']) ?>
  <p class="aic-regola">Qualunque permesso tu dia, l'AI non pubblica mai: le bozze le pubblichi tu, e le modifiche a quello che è già online sono proposte da approvare. Non tocca utenti, password, aggiornamenti, copie, temi né la sicurezza.</p>
  <div class="aic-azioni">
    <button class="btn btn-primary" type="submit" name="azione" value="consenti">Consenti</button>
    <button class="btn btn-secondary" type="submit" name="azione" value="rifiuta">Rifiuta</button>
  </div>
</form>
<?php endif;
require CMS_ADMIN . '/includes/admin-footer.php';
