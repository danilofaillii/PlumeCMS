<?php
/**
 * Controlla il server (1.4.0): con un clic prova davvero, dal server dove gira il sito, quello che serve a plumeCMS
 * (includes/controllo-server.php): PHP ed estensioni, HTTPS, cron, email, sito degli aggiornamenti, cartelle,
 * protezioni del .htaccess. Verde, giallo o rosso, con cosa fare scritto per chi non e tecnico.
 * Solo amministratore; il controllo e l'email di prova sono POST con il token CSRF (l'email passa da tipo-sito.php).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_admin();
require_once dirname(__DIR__) . '/includes/controllo-server.php';

$adminTitle = 'Controlla il server';
$risultati = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    session_write_close(); // le richieste al sito stesso non aspettano la sessione di questa
    $risultati = server_controlli();
}
$conta = ['ok' => 0, 'avviso' => 0, 'ko' => 0];
if ($risultati) foreach ($risultati as $voci) foreach ($voci as $x) $conta[$x['esito']]++;
$parole = ['ok' => 'a posto', 'avviso' => 'da sapere', 'ko' => 'da sistemare'];
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.cs-testa{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.25rem;margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between}
.cs-testa p{font-size:.9rem;margin:0;flex:1 1 380px;line-height:1.5}
.cs-azioni{display:flex;gap:.5rem;flex-wrap:wrap}
.cs-somma{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
.cs-somma span{font-size:.85rem;font-weight:600;padding:.35rem .8rem;border-radius:999px}
.cs-gruppo{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:.9rem 1.25rem;margin-bottom:1rem}
.cs-gruppo h2{font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:0 0 .5rem}
.cs-voce{display:flex;gap:.8rem;align-items:flex-start;padding:.6rem 0;border-top:1px solid var(--border)}
.cs-voce:first-of-type{border-top:0}
.cs-segno{flex:none;width:12px;height:12px;border-radius:50%;margin-top:.35rem}
.cs-ok .cs-segno,.cs-somma .ok{background:var(--sage)}
.cs-avviso .cs-segno,.cs-somma .avviso{background:var(--mustard)}
.cs-ko .cs-segno,.cs-somma .ko{background:var(--coral)}
.cs-somma .ok{background:var(--sage-light)}.cs-somma .avviso{background:var(--mustard-light)}.cs-somma .ko{background:var(--coral-light)}
.cs-voce b{font-size:.92rem}
.cs-voce p{font-size:.86rem;margin:.15rem 0 0;line-height:1.45}
.cs-fare{color:var(--text-muted)}
.cs-voce a{font-size:.82rem;font-weight:600;color:var(--coral);text-decoration:none}
</style>

<div class="cs-testa">
  <p>Il pannello prova, dal server dove gira il sito, tutto quello che serve a <?= h(CMS_NOME) ?>: la versione di PHP, le estensioni, il certificato HTTPS, il cron, le email, il collegamento al sito degli aggiornamenti, le cartelle e le protezioni dei file. Non cambia niente: guarda e dice.</p>
  <div class="cs-azioni">
    <form method="post"><?= csrf_field() ?><button type="submit" class="btn btn-primary" id="cs-controlla"><?= $risultati ? 'Controlla di nuovo' : 'Controlla adesso' ?></button></form>
    <form method="post" action="tipo-sito.php"><?= csrf_field() ?><input type="hidden" name="azione" value="email_prova"><input type="hidden" name="ritorno" value="controlla"><button type="submit" class="btn btn-secondary" id="cs-email">Manda un'email di prova</button></form>
  </div>
</div>

<?php if ($risultati): ?>
<div class="cs-somma" role="status">
<?php foreach ($conta as $k => $n): if (!$n) continue; ?>  <span class="<?= $k ?>" data-conta="<?= $k ?>"><?= $n ?> <?= h($parole[$k]) ?></span>
<?php endforeach; ?>
</div>
<?php foreach ($risultati as $gruppo => $voci): ?>
<section class="cs-gruppo">
  <h2><?= h($gruppo) ?></h2>
<?php foreach ($voci as $x): ?>
  <div class="cs-voce cs-<?= h($x['esito']) ?>" data-esito="<?= h($x['esito']) ?>">
    <span class="cs-segno" aria-hidden="true"></span>
    <div>
      <b><?= h($x['titolo']) ?></b> <span class="sr-only">(<?= h($parole[$x['esito']]) ?>)</span>
      <p><?= h($x['detto']) ?></p>
      <?php if ($x['fare'] !== ''): ?><p class="cs-fare">Cosa fare: <?= h($x['fare']) ?></p><?php endif; ?>
      <?php if ($x['link'] !== '' && $x['esito'] !== 'ok'): ?><a href="<?= h($x['link']) ?>">Vai alla pagina</a><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
<style>.cs-voce .sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}</style>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
