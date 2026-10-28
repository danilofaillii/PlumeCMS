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
// I tipi di contenuto dei moduli (per esempio un catalogo o delle ricette), con i loro conteggi e il pulsante "+ Nuovo".
$tipiExtra = array_diff_key(contenuti_tipi(), ['articoli' => 1, 'pagine' => 1]);
$contExtra = [];
foreach (array_keys($tipiExtra) as $k) $contExtra[$k] = contenuti_conteggi($k);
$prossimi = db()->query('SELECT id, title, slug, publish_date, publish_time FROM ' . tbl('articles') . " WHERE cestino IS NULL AND status <> 'draft' AND TIMESTAMP(publish_date, publish_time) > NOW() ORDER BY publish_date, publish_time LIMIT 8")->fetchAll();
$ultimi = db()->query('SELECT id, title, slug, updated_at FROM ' . tbl('articles') . ' WHERE cestino IS NULL ORDER BY updated_at DESC LIMIT 6')->fetchAll();
$nl = newsletter_numeri();
$ultimaCampagna = db()->query('SELECT * FROM ' . tbl('newsletter_campagne') . " WHERE stato IN ('inviata','in_invio') ORDER BY invio_at DESC, id DESC LIMIT 1")->fetch();
$eventi = $ultimaCampagna ? newsletter_conteggio_eventi((int)$ultimaCampagna['id']) : null;
$inviiNuovi = $n('SELECT COUNT(*) FROM ' . tbl('moduli_invii') . ' WHERE letto = 0');
$inviiSettimana = $n('SELECT COUNT(*) FROM ' . tbl('moduli_invii') . ' WHERE creato > DATE_SUB(NOW(), INTERVAL 7 DAY)');
// Numeri in più dai moduli (per esempio le richieste ricevute negli ultimi 7 giorni): [['valore' => n, 'etichetta' => '...']].
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
if ($inviiNuovi) $avvisi[] = ["$inviiNuovi " . ($inviiNuovi === 1 ? 'compilazione nuova' : 'compilazioni nuove') . ' dai form.', 'form-submissions.php'];
if ($pacchettiAttesa) $avvisi[] = ["$pacchettiAttesa " . ($pacchettiAttesa === 1 ? 'pacchetto caricato e non ancora applicato' : 'pacchetti caricati e non ancora applicati') . '.', 'aggiornamenti.php'];
if (posta_notifica_email() === '') $avvisi[] = ["Manca l'indirizzo degli avvisi: senza, niente email del mattino.", 'impostazioni.php'];

if (!$dbAdmin) $avvisi = [];
// 1.3.0: c'e una versione nuova di plumeCMS? Solo per l'amministratore, da quello che il pannello sa gia (includes/versione-nuova.php).
$versioneNuova = null; $versioneDaControllare = false;
if ($dbAdmin) { try { require_once dirname(__DIR__) . '/includes/versione-nuova.php'; $versioneNuova = versione_nuova(); $versioneDaControllare = versione_da_controllare(); } catch (Throwable $e) {} }
// 1.4.0: il tipo di sito in prova e i primi passi, solo nelle installazioni nuove (impostazioni scritte dall'installatore
// della 1.4.0: un sito che si aggiorna non le ha) e solo per l'amministratore. Le azioni vanno a tipo-sito.php.
$kitProva = null; $kitTutti = []; $passi = [];
if ($dbAdmin) {
    try {
        require_once dirname(__DIR__) . '/includes/kit-partenza.php';
        $kitProva = kit_in_prova() ? kit_prova() : null;
        if ($kitProva) $kitTutti = kit_elenco();
        require_once dirname(__DIR__) . '/includes/primi-passi.php';
        if (primi_passi_attivi()) $passi = primi_passi();
    } catch (Throwable $e) { error_log('dashboard, tipo di sito e primi passi: ' . $e->getMessage()); }
}
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.db-griglia{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem;margin-top:1rem}
.db-scatola{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.2rem}
.db-scatola h3{font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:.7rem;display:flex;justify-content:space-between}
.db-scatola h3 a{font-size:.75rem;text-transform:none;letter-spacing:0;color:var(--link);text-decoration:none}
.db-numeri{display:flex;gap:1.2rem;flex-wrap:wrap;margin-bottom:.6rem}
.db-numeri b{display:block;font-size:1.6rem;line-height:1.1}
.db-numeri span{font-size:.75rem;color:var(--text-muted)}
.db-lista{list-style:none;margin:0;padding:0;font-size:.85rem}
.db-lista li{display:flex;justify-content:space-between;gap:.6rem;padding:.35rem 0;border-bottom:1px solid var(--border)}
.db-lista li:last-child{border-bottom:0}
.db-lista a{color:var(--text);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.db-lista small{color:var(--text-muted);white-space:nowrap}
.db-avvisi{background:var(--mustard-light);color:var(--attenzione-testo);border:1px solid var(--attenzione-bordo);border-radius:var(--radius);padding:.8rem 1.2rem}
.db-avvisi a{color:var(--attenzione-testo);font-weight:600}
.db-azioni{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
.db-versione{display:flex;gap:.4rem 1rem;flex-wrap:wrap;align-items:center;background:var(--info-chiaro);border:1px solid var(--info-bordo);border-radius:var(--radius);padding:.8rem 1.2rem;margin-bottom:1rem;color:var(--info-testo)}
.db-versione[hidden]{display:none}
.db-versione span{font-size:.88rem;flex:1 1 280px}
/* 1.4.0: tipo di sito in prova e primi passi. Solo le variabili del pannello, niente colori scritti qui. */
.db-kit,.db-passi{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.25rem;margin-bottom:1rem}
.db-kit h2,.db-passi h2{font-size:1.1rem;margin:0 0 .3rem}
.db-kit>p,.db-passi>p{font-size:.88rem;color:var(--text-muted);margin:0 0 .9rem;max-width:62rem}
.db-kit-carte{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem}
.db-kit-carta{margin:0}
.db-kit-carta button{all:unset;box-sizing:border-box;display:flex;flex-direction:column;gap:.3rem;width:100%;height:100%;padding:.7rem;border:1.5px solid var(--border);border-radius:var(--radius);background:var(--surface);cursor:pointer;color:var(--text)}
.db-kit-carta button:hover{border-color:var(--coral)}
.db-kit-carta button:focus-visible{outline:2px solid var(--coral);outline-offset:2px}
.db-kit-carta.attuale button{border-color:var(--coral);background:var(--coral-light);cursor:default}
.db-kit-carta b{font-size:.95rem}
.db-kit-carta small{font-size:.78rem;color:var(--text-muted);line-height:1.35}
.db-kit-carta em{font-style:normal;font-size:.72rem;font-weight:600;color:var(--coral);text-transform:uppercase;letter-spacing:.05em}
.db-kit-disegno{position:relative;display:block;height:58px;border-radius:8px;background:var(--bg);border:1px solid var(--border);overflow:hidden;margin-bottom:.2rem}
.db-kit-disegno i{position:absolute;display:block;border-radius:3px;background:var(--border)}
.db-k-vuoto i:nth-child(1){left:10px;top:11px;width:60%;height:8px;background:var(--text-muted)}
.db-k-vuoto i:nth-child(2){left:10px;top:26px;width:40%;height:5px}
.db-k-vuoto i:nth-child(3){left:10px;top:38px;width:24%;height:9px;border-radius:5px;background:var(--coral)}
.db-k-vuoto i:nth-child(4){display:none}
.db-k-personale i:nth-child(1){right:12px;top:9px;width:40px;height:40px;border-radius:50%;background:var(--sage)}
.db-k-personale i:nth-child(2){left:10px;top:13px;width:46%;height:8px;background:var(--text-muted)}
.db-k-personale i:nth-child(3){left:10px;top:27px;width:34%;height:5px}
.db-k-personale i:nth-child(4){left:10px;top:38px;width:22%;height:9px;border-radius:5px;background:var(--coral)}
.db-k-attivita i:nth-child(1){inset:0 0 20px 0;border-radius:0;background:var(--sage-light)}
.db-k-attivita i:nth-child(2){left:10px;top:11px;width:46%;height:8px;background:var(--sage)}
.db-k-attivita i:nth-child(3){left:10px;bottom:5px;width:26%;height:10px}
.db-k-attivita i:nth-child(4){right:10px;bottom:5px;width:26%;height:10px}
.db-k-blog i:nth-child(1){left:10px;top:9px;width:56%;height:8px;background:var(--text-muted)}
.db-k-blog i:nth-child(2){left:10px;top:24px;width:26%;height:24px}
.db-k-blog i:nth-child(3){left:37%;top:24px;width:26%;height:24px}
.db-k-blog i:nth-child(4){right:10px;top:24px;width:26%;height:24px}
.db-k-portfolio i{top:8px;height:42px;width:29%}
.db-k-portfolio i:nth-child(1){left:7px;background:var(--mustard-light)}
.db-k-portfolio i:nth-child(2){left:35.5%;height:19px;background:var(--coral-light)}
.db-k-portfolio i:nth-child(3){left:35.5%;top:31px;height:19px;background:var(--sage-light)}
.db-k-portfolio i:nth-child(4){right:7px;background:var(--mustard)}
.db-kit-tenuti{margin:.9rem 0 0;padding:.7rem 1rem;border-radius:var(--radius);background:var(--sage-light);font-size:.85rem}
.db-kit-tenuti ul{margin:.3rem 0 0 1.1rem}
.db-kit-piede{display:flex;flex-wrap:wrap;gap:.6rem 1rem;align-items:center;justify-content:space-between;margin-top:1rem;padding-top:.9rem;border-top:1px solid var(--border);font-size:.85rem;color:var(--text-muted)}
.db-kit-piede span{flex:1 1 320px}
.db-passi-conta{font-size:.8rem;font-weight:600;color:var(--text-muted);margin-left:.4rem}
.db-passi ol{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.6rem}
.db-passi li{display:flex;gap:.7rem;align-items:flex-start;padding:.65rem .8rem;border:1px solid var(--border);border-radius:var(--radius)}
.db-passi li.fatto{background:var(--sage-light);border-color:var(--sage-light)}
.db-passi-segno{flex:none;width:22px;height:22px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--surface)}
.db-passi li.fatto .db-passi-segno{background:var(--sage);border-color:var(--sage)}
.db-passi li b{display:block;font-size:.9rem}
.db-passi li small{display:block;font-size:.8rem;color:var(--text-muted);margin:.15rem 0 .35rem}
.db-passi li a{font-size:.8rem;font-weight:600;color:var(--accento-testo);text-decoration:none}
.db-passi li form{display:block;margin-top:.45rem}
.db-passi-fine{display:flex;justify-content:flex-end;margin-top:.8rem}
.db-passi .sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
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

<div class="db-versione" id="db-versione"<?= $versioneNuova ? '' : ' hidden' ?>>
  <b><?= h(CMS_NOME) ?> <span id="db-versione-numero"><?= h($versioneNuova['versione'] ?? '') ?></span> è disponibile.</b>
  <span>Hai la <?= h(cms_versione()) ?>. Da Aggiornamenti la scarichi e la prepari con un clic; il sito cambia solo quando premi Applica.</span>
  <a class="btn btn-primary btn-sm" href="aggiornamenti.php#agg-versione">Vai ad Aggiornamenti</a>
</div>
<?php if ($versioneDaControllare): /* il controllo del giorno, dopo il caricamento: la dashboard non aspetta la rete */ ?>
<script<?= nonce_attr() ?>>
fetch('aggiornamenti.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': <?= json_encode(csrf_token()) ?> }, body: new URLSearchParams({ action: 'versione_controlla', _csrf: <?= json_encode(csrf_token()) ?> }) })
  .then(function (r) { return r.json(); })
  .then(function (j) { if (j.nuova) { document.getElementById('db-versione-numero').textContent = j.versione; document.getElementById('db-versione').hidden = false; } })
  .catch(function () {});
</script>
<?php endif; ?>

<?php if ($kitProva): $kitNome = $kitTutti[$kitProva['kit']]['nome'] ?? $kitProva['kit']; ?>
<section class="db-kit" id="tipo-sito" aria-labelledby="tipo-sito-titolo">
  <h2 id="tipo-sito-titolo">Stai provando il sito <?= h($kitNome) ?></h2>
  <p>Il sito è partito con pagine, menu e testi d'esempio. Finché non premi <b>Definitivo</b> puoi cambiare tipo di sito con un clic: prima il pannello fa da solo una copia del sito, poi cambia solo quello che il kit aveva preparato e che non hai toccato. Le pagine e gli articoli che hai scritto o cambiato restano.</p>
  <div class="db-kit-carte">
<?php foreach ($kitTutti as $kid => $k): $kitQui = $kid === $kitProva['kit']; ?>
    <form method="post" action="tipo-sito.php" class="db-kit-carta<?= $kitQui ? ' attuale' : '' ?>"<?= $kitQui ? '' : ' data-conferma="' . h('Il sito diventa ' . $k['nome'] . '. Le pagine che hai scritto o cambiato restano. Prima del cambio il pannello fa una copia del sito.') . '"' ?>>
      <?= csrf_field() ?><input type="hidden" name="azione" value="cambia"><input type="hidden" name="kit" value="<?= h($kid) ?>">
      <button type="submit"<?= $kitQui ? ' disabled aria-current="true"' : '' ?> data-kit="<?= h($kid) ?>">
        <span class="db-kit-disegno db-k-<?= h($kid) ?>" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <?php if ($kitQui): ?><em>In prova adesso</em><?php endif; ?>
        <b><?= h($k['nome']) ?></b><small><?= h($k['descrizione']) ?></small>
      </button>
    </form>
<?php endforeach; ?>
  </div>
<?php if ($kitProva['storia']): ?>
  <div class="db-kit-tenuti" id="tipo-sito-tenuti">
    <?php if ($kitProva['tenuti']): ?><b>Cosa è rimasto com'era</b>
    <ul><?php foreach ($kitProva['tenuti'] as $t): ?><li><?= h($t) ?></li><?php endforeach; ?></ul>
    <?php else: ?><b>Cambio fatto.</b> Tutto quello che il sito di prima aveva preparato era ancora intatto ed è stato sostituito; quello che avevi scritto tu è rimasto.<?php endif; ?>
  </div>
<?php endif; ?>
  <div class="db-kit-piede">
    <span>Ti piace così? Con Definitivo il riquadro sparisce e il tipo di sito non si cambia più da qui: il resto lo cambi con l'editor, pagina per pagina.</span>
    <form method="post" action="tipo-sito.php" data-conferma="<?= h('Il sito resta ' . $kitNome . '. Il riquadro sparisce e il tipo di sito non si potrà più cambiare da qui. Confermi?') ?>">
      <?= csrf_field() ?><input type="hidden" name="azione" value="definitivo">
      <button type="submit" class="btn btn-primary btn-sm" id="kit-definitivo">Definitivo</button>
    </form>
  </div>
</section>
<?php endif; ?>

<?php if ($passi): $fatti = count(array_filter($passi, fn($x) => $x['fatto'])); ?>
<section class="db-passi" id="primi-passi" aria-labelledby="primi-passi-titolo">
  <h2 id="primi-passi-titolo">Primi passi <span class="db-passi-conta"><?= $fatti ?> su <?= count($passi) ?></span></h2>
  <p>Le cose da fare perché il sito sia pronto. Si spuntano da sole quando sono fatte.</p>
  <ol>
<?php foreach ($passi as $x): ?>
    <li class="<?= $x['fatto'] ? 'fatto' : '' ?>" data-passo="<?= h($x['chiave']) ?>">
      <span class="db-passi-segno" aria-hidden="true"><?= $x['fatto'] ? '&#10003;' : '' ?></span>
      <div><b><?= h($x['titolo']) ?><span class="sr-only"><?= $x['fatto'] ? ' (fatto)' : ' (da fare)' ?></span></b><small><?= h($x['detto']) ?></small>
        <a href="<?= h($x['link']) ?>"><?= h($x['invito']) ?></a>
        <?php if ($x['chiave'] === 'email'): ?><form method="post" action="tipo-sito.php"><?= csrf_field() ?><input type="hidden" name="azione" value="email_prova"><button type="submit" class="btn btn-secondary btn-sm" id="passi-email">Manda un'email di prova</button></form><?php endif; ?>
      </div>
    </li>
<?php endforeach; ?>
  </ol>
<?php if ($fatti === count($passi)): ?>
  <div class="db-passi-fine"><form method="post" action="tipo-sito.php"><?= csrf_field() ?><input type="hidden" name="azione" value="nascondi_passi"><button type="submit" class="btn btn-secondary btn-sm">Tutto fatto: nascondi</button></form></div>
<?php endif; ?>
</section>
<?php endif; ?>

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
