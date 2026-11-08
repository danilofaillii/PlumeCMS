<?php
/**
 * AI collegate > Impostazioni: le chiavi gratuite di Pexels e Unsplash (facoltative; Openverse non ne vuole). Le chiavi non
 * si rivedono mai nel pannello: si vede solo se sono salvate, e si cambiano o si tolgono.
 */
require_once __DIR__ . '/admin-comune.php';
require_once __DIR__ . '/foto.php';
$adminTitle = 'AI collegate: impostazioni';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $s = aic_impostazioni();
    $nuove = [];
    foreach (['pexels', 'unsplash'] as $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        if (!empty($_POST['togli_' . $k])) $nuove[$k] = '';
        elseif ($v !== '') {
            if (!preg_match('/^[A-Za-z0-9_-]{10,200}$/', $v)) { flash('error', 'La chiave di ' . ucfirst($k) . ' non sembra giusta: sono lettere, numeri e trattini, senza spazi. Copiala di nuovo dal sito di ' . ucfirst($k) . '.'); aic_admin_vai('impostazioni'); }
            $nuove[$k] = $v;
        }
    }
    aic_impostazioni_salva($nuove + $s);
    sicurezza_log('ai_impostazioni', (string)(current_user()['username'] ?? ''), (int)(current_user()['id'] ?? 0), 'archivi di foto: ' . implode(', ', aic_foto_archivi_attivi()));
    flash('success', 'Impostazioni salvate. Archivi di foto attivi: ' . implode(', ', aic_foto_archivi_attivi()) . '.');
    aic_admin_vai('impostazioni');
}
$s = aic_impostazioni();
require CMS_ADMIN . '/includes/admin-header.php';
aic_admin_schede('impostazioni');
?>
<form method="post" class="form-card" id="archivi" autocomplete="off">
  <?= csrf_field() ?>
  <h3>Archivi di foto</h3>
  <p>Le AI cercano foto con licenza libera anche per uso commerciale. <b>Openverse</b> è sempre attivo e non chiede niente (solo foto CC0, pubblico dominio e CC BY). Pexels e Unsplash si aggiungono con la loro chiave gratuita.</p>
  <?php foreach (['pexels' => ['Pexels', 'https://www.pexels.com/api/', 'Entra su pexels.com, apri la pagina API e premi "Your API Key".'], 'unsplash' => ['Unsplash', 'https://unsplash.com/developers', 'Entra su unsplash.com/developers, crea una "New Application" e copia la "Access Key".']] as $k => [$nome, $url, $come]): ?>
  <div class="form-group">
    <label for="aic-<?= $k ?>">Chiave di <?= h($nome) ?></label>
    <input type="password" name="<?= $k ?>" id="aic-<?= $k ?>" maxlength="200" placeholder="<?= $s[$k] !== '' ? 'salvata: scrivi qui solo per cambiarla' : 'per esempio: 563492ad6f91700001000001' ?>" autocomplete="new-password">
    <div class="hint"><?= $s[$k] !== '' ? '&#10003; Chiave salvata (non si rivede). ' : 'Nessuna chiave: ' . h($nome) . ' resta spento. ' ?><?= h($come) ?> <a href="<?= h($url) ?>" target="_blank" rel="noopener">Apri <?= h($nome) ?></a></div>
    <?php if ($s[$k] !== ''): ?><label class="aic-spunta"><input type="checkbox" name="togli_<?= $k ?>" value="1"> Togli la chiave di <?= h($nome) ?></label><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Salva le chiavi</button>
</form>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
