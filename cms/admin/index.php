<?php
/**
 * Dashboard: il quadro della giornata. Cosa esce, cosa aspetta, chi ha scritto, come va la newsletter,
 * cosa chiede attenzione (cron fermo, pacchetti in attesa, richieste da richiamare).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/contenuti.php';
require_once dirname(__DIR__) . '/includes/form.php';
require_once dirname(__DIR__) . '/includes/newsletter.php';
require_login();
sito_schema_aggiorna();
newsletter_schema();
form_schema();
$adminTitle = 'Dashboard';

$n = fn(string $sql) => (int)db()->query($sql)->fetchColumn();
$art = contenuti_conteggi('articoli');
$pag = contenuti_conteggi('pagine');
// I tipi dei moduli (danilofailli.it: aforismi), con i loro conteggi e il pulsante "+ Nuovo".
$tipiExtra = array_diff_key(contenuti_tipi(), ['articoli' => 1, 'pagine' => 1]);
$contExtra = [];
foreach (array_keys($tipiExtra) as $k) $contExtra[$k] = contenuti_conteggi($k);
$prossimi = db()->query('SELECT id, title, slug, publish_date, publish_time FROM ' . tbl('articles') . " WHERE cestino IS NULL AND status <> 'draft' AND TIMESTAMP(publish_date, publish_time) > NOW() ORDER BY publish_date, publish_time LIMIT 8")->fetchAll();
$ultimi = db()->query('SELECT id, title, slug, updated_at FROM ' . tbl('articles') . ' WHERE cestino IS NULL ORDER BY updated_at DESC LIMIT 6')->fetchAll();
$nl = newsletter_numeri();
$ultimaCampagna = db()->query('SELECT * FROM ' . tbl('newsletter_campagne') . " WHERE stato IN ('inviata','in_invio') ORDER BY invio_at DESC, id DESC LIMIT 1")->fetch();
$eventi = $ultimaCampagna ? newsletter_conteggio_eventi((int)$ultimaCampagna['id']) : null;
$laser = $n('SELECT COUNT(*) FROM ' . tbl('richieste_laser') . " WHERE stato = 'da_chiamare'");
$inviiNuovi = $n('SELECT COUNT(*) FROM ' . tbl('moduli_invii') . ' WHERE letto = 0');
$inviiSettimana = $n('SELECT COUNT(*) FROM ' . tbl('moduli_invii') . ' WHERE creato > DATE_SUB(NOW(), INTERVAL 7 DAY)');
$ebookAttesa = $n('SELECT COUNT(*) FROM ' . tbl('iscrizioni_ebook') . ' WHERE confermato_at IS NULL');
// Numeri in più dai moduli (danilofailli.it: le compilazioni del test negli ultimi 7 giorni): [['valore' => n, 'etichetta' => '...']].
$numeriModuli = (array)cms_filtro('dashboard_numeri', []);
$cronLog = dirname(__DIR__) . '/logs/cron-daily.log';
$cronUltimo = is_file($cronLog) ? filemtime($cronLog) : 0;
$cronFermo = !$cronUltimo || $cronUltimo < time() - 36 * 3600;
$nlLog = dirname(__DIR__) . '/logs/newsletter.log';
$pacchettiAttesa = 0;
try { require_once dirname(__DIR__) . '/includes/aggiornamenti.php'; $pacchettiAttesa = count(array_filter(agg_lista_pacchetti(), fn($p) => ($p['stato'] ?? '') !== 'applicato')); } catch (Throwable $e) {}
$avvisi = [];
$dbAdmin = is_admin(); // sicurezza (ruoli): il redattore vede solo i contenuti; newsletter, richieste, sistema e avvisi sono dell'amministratore
if ($cronFermo) $avvisi[] = ['Il cron del mattino non gira da ' . ($cronUltimo ? date('d/m H:i', $cronUltimo) : 'mai') . ': controlla la riga in cPanel.', 'cron-log.php'];
if ($laser) $avvisi[] = ["$laser " . ($laser === 1 ? 'persona aspetta la chiamata' : 'persone aspettano la chiamata') . ' della Laser Coaching.', 'contatti.php'];
if ($inviiNuovi) $avvisi[] = ["$inviiNuovi " . ($inviiNuovi === 1 ? 'compilazione nuova' : 'compilazioni nuove') . ' dai form.', 'form-submissions.php'];
if ($pacchettiAttesa) $avvisi[] = ["$pacchettiAttesa " . ($pacchettiAttesa === 1 ? 'pacchetto caricato e non ancora applicato' : 'pacchetti caricati e non ancora applicati') . '.', 'aggiornamenti.php'];
if (posta_notifica_email() === '') $avvisi[] = ["Manca l'indirizzo degli avvisi: senza, niente email del mattino.", 'impostazioni.php'];

if (!$dbAdmin) $avvisi = [];
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.db-griglia{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem;margin-top:1rem}
.db-scatola{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.2rem}
.db-scatola h3{font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:.7rem;display:flex;justify-content:space-between}
.db-scatola h3 a{font-size:.75rem;text-transform:none;letter-spacing:0;color:#2271b1;text-decoration:none}
.db-numeri{display:flex;gap:1.2rem;flex-wrap:wrap;margin-bottom:.6rem}
.db-numeri b{display:block;font-size:1.6rem;line-height:1.1}
.db-numeri span{font-size:.75rem;color:var(--text-muted)}
.db-lista{list-style:none;margin:0;padding:0;font-size:.85rem}
.db-lista li{display:flex;justify-content:space-between;gap:.6rem;padding:.35rem 0;border-bottom:1px solid var(--border)}
.db-lista li:last-child{border-bottom:0}
.db-lista a{color:var(--text);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.db-lista small{color:var(--text-muted);white-space:nowrap}
.db-avvisi{background:#fff3cd;border:1px solid #f1dfae;border-radius:var(--radius);padding:.8rem 1.2rem}
.db-avvisi a{color:#7a5a00;font-weight:600}
.db-azioni{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
</style>

<div class="db-azioni">
  <a class="btn btn-primary btn-sm" href="editor.php?nuova=1&amp;tipo=articoli">+ Articolo</a>
  <a class="btn btn-secondary btn-sm" href="editor.php?nuova=1&amp;tipo=pagine">+ Pagina</a>
<?php foreach ($tipiExtra as $k => $t): ?>  <a class="btn btn-secondary btn-sm" href="editor.php?nuova=1&amp;tipo=<?= h($k) ?>">+ <?= h($t['Uno'] ?? ucfirst($t['uno'])) ?></a>
<?php endforeach; ?>
<?php if ($dbAdmin): ?>  <a class="btn btn-secondary btn-sm" href="newsletter-campagna.php">+ Campagna</a>
<?php endif; ?>
  <a class="btn btn-secondary btn-sm" href="<?= $homeId ? 'editor.php?id=' . $homeId : 'contenuti.php?tipo=pagine' ?>">Home nell'editor</a>
</div>

<?php if ($avvisi): ?>
<div class="db-avvisi">
  <?php foreach ($avvisi as [$t, $l]): ?><div>⚠ <?= h($t) ?> <a href="<?= h($l) ?>">Apri</a></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="db-griglia">
  <div class="db-scatola">
    <h3>Contenuti <a href="contenuti.php?tipo=articoli">tutti</a></h3>
    <div class="db-numeri">
      <div><b><?= $art['pubblicato'] ?></b><span>articoli online</span></div>
      <div><b><?= $art['programmato'] ?></b><span>programmati</span></div>
      <div><b><?= $art['bozza'] ?></b><span>bozze</span></div>
      <div><b><?= $pag['pubblicato'] ?></b><span>pagine</span></div>
<?php foreach ($tipiExtra as $i => $t): ?>      <div><b><?= $contExtra[$i]['pubblicato'] ?></b><span><?= h($t['tanti']) ?></span></div>
<?php endforeach; ?>
    </div>
    <h3 style="margin-top:.6rem">Prossime uscite</h3>
    <ul class="db-lista">
      <?php if (!$prossimi): ?><li><span style="color:var(--text-muted)">Niente in programma: la coda è vuota.</span></li><?php endif; ?>
      <?php foreach ($prossimi as $p): ?><li><a href="contenuto.php?tipo=articoli&amp;id=<?= $p['id'] ?>"><?= h($p['title']) ?></a><small><?= h(substr((string)$p['publish_date'], 0, 10)) ?> <?= h(substr((string)$p['publish_time'], 0, 5)) ?></small></li><?php endforeach; ?>
    </ul>
  </div>

<?php if ($dbAdmin): ?>
  <div class="db-scatola">
    <h3>Newsletter <a href="newsletter-statistiche.php">statistiche</a></h3>
    <div class="db-numeri">
      <div><b><?= (int)($nl['attivo'] ?? 0) ?></b><span>iscritti attivi</span></div>
      <div><b><?= (int)($nl['in_attesa'] ?? 0) ?></b><span>da confermare</span></div>
      <div><b><?= (int)($nl['uscito'] ?? 0) ?></b><span>usciti</span></div>
    </div>
    <?php if ($ultimaCampagna): ?>
    <ul class="db-lista">
      <li><a href="newsletter-campagna.php?id=<?= (int)$ultimaCampagna['id'] ?>">Ultima: <?= h($ultimaCampagna['oggetto']) ?></a><small><?= (int)$ultimaCampagna['inviate'] ?> inviate · <?= (int)$eventi['apertura'] ?> aperte · <?= (int)$eventi['clic'] ?> clic</small></li>
    </ul>
    <?php else: ?><p style="font-size:.85rem;color:var(--text-muted)">Nessuna campagna inviata ancora.</p><?php endif; ?>
  </div>

  <div class="db-scatola">
    <h3>Richieste e form <a href="form-submissions.php">vedi</a></h3>
    <div class="db-numeri">
      <div><b><?= $laser ?></b><span>da richiamare</span></div>
      <div><b><?= $ebookAttesa ?></b><span>ebook da confermare</span></div>
      <div><b><?= $inviiSettimana ?></b><span>form, 7 giorni</span></div>
<?php foreach ($numeriModuli as $nm): ?>      <div><b><?= h((string)($nm['valore'] ?? '')) ?></b><span><?= h((string)($nm['etichetta'] ?? '')) ?></span></div>
<?php endforeach; ?>
    </div>
  </div>

<?php endif; ?>
  <div class="db-scatola">
    <h3>Ultime modifiche</h3>
    <ul class="db-lista">
      <?php foreach ($ultimi as $u): ?><li><a href="contenuto.php?tipo=articoli&amp;id=<?= $u['id'] ?>"><?= h($u['title']) ?></a><small><?= h(substr((string)$u['updated_at'], 0, 16)) ?></small></li><?php endforeach; ?>
    </ul>
  </div>

<?php if ($dbAdmin): ?>
  <div class="db-scatola">
    <h3>Sistema <a href="cron-log.php">log</a></h3>
    <ul class="db-lista">
      <li><span>Cron del mattino</span><small><?= $cronUltimo ? date('d/m/Y H:i', $cronUltimo) : 'mai girato' ?></small></li>
      <li><span>Cron della newsletter</span><small><?= is_file($nlLog) ? date('d/m/Y H:i', filemtime($nlLog)) : 'mai girato' ?></small></li>
      <li><span>Pacchetti da applicare</span><small><?= $pacchettiAttesa ?></small></li>
      <li><span>Posta</span><small><?= ($smtpCfg = posta_config()) ? 'SMTP ' . h((string)$smtpCfg['host']) : 'mail() di PHP' ?></small></li>
    </ul>
  </div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
