<?php
/**
 * Social (1.5.0): l'email del mattino, il calendario dei testi social, i testi pronti di ogni contenuto, le impostazioni.
 * Il motore sta in includes/social.php; il cron del mattino manda le email (includes/cron.php). Solo amministratore:
 * il redattore scrive i testi nella scheda del contenuto (riquadro Social).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/social.php';
require_once dirname(__DIR__) . '/includes/contenuti.php';
require_once dirname(__DIR__) . '/includes/posta.php';
require_admin();
social_installa();

$schede = ['mattino' => 'Email del mattino', 'calendario' => 'Calendario', 'testi' => 'Testi dei contenuti', 'impostazioni' => 'Impostazioni'];
$scheda = isset($schede[$_GET['scheda'] ?? '']) ? $_GET['scheda'] : 'mattino';
$avvisi = posta_notifica_email();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $az = (string)($_POST['azione'] ?? '');
    $torna = 'social.php?scheda=' . $scheda;
    try {
        if ($az === 'impostazioni') {
            social_impostazioni_salva($_POST);
            flash('success', 'Impostazioni dei social salvate.');
        } elseif ($az === 'manda_mattino') {
            $giorno = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['giorno'] ?? '')) ? (string)$_POST['giorno'] : date('Y-m-d');
            $m = social_email_mattino($giorno);
            if (!$m) throw new RuntimeException('Quel giorno non esce niente: non c\'è un\'email da mandare.');
            if ($avvisi === '') throw new RuntimeException('Manca l\'indirizzo degli avvisi: scrivilo in Impostazioni > Configurazione email.');
            if (!social_invia($avvisi, $m[0], $m[1])) throw new RuntimeException('L\'email non è partita: ' . posta_errore());
            flash('success', 'Email "' . $m[0] . '" mandata a ' . $avvisi . '.');
            $torna .= '&giorno=' . $giorno;
        } elseif ($az === 'programmato_salva') {
            $id = social_programmato_salva((int)($_POST['id'] ?? 0), $_POST);
            flash('success', 'Testo del ' . date('d/m/Y', strtotime((string)$_POST['data_invio'])) . ' salvato.');
        } elseif ($az === 'programmato_elimina') {
            db()->prepare('DELETE FROM ' . tbl('social_programmati') . ' WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Testo tolto dal calendario.');
        } elseif ($az === 'programmato_rimetti') {
            db()->prepare('UPDATE ' . tbl('social_programmati') . ' SET inviato_at = NULL WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Il testo ripartirà con il prossimo giro del mattino.');
        } elseif ($az === 'programmato_manda') {
            $r = social_programmato((int)($_POST['id'] ?? 0));
            $e = $r ? social_email_programmato($r) : null;
            if (!$e) throw new RuntimeException('Testo non trovato, o il suo contenuto non c\'è più.');
            if ($avvisi === '') throw new RuntimeException('Manca l\'indirizzo degli avvisi: scrivilo in Impostazioni > Configurazione email.');
            if (!social_invia($avvisi, $e[0], $e[1])) throw new RuntimeException('L\'email non è partita: ' . posta_errore());
            flash('success', 'Email "' . $e[0] . '" mandata a ' . $avvisi . '. Nel calendario il testo resta com\'era.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        if ($az === 'programmato_salva') $torna .= ((int)($_POST['id'] ?? 0) ? '&modifica=' . (int)$_POST['id'] : '&nuovo=1');
    }
    header('Location: ' . $torna);
    exit;
}

$imp = social_impostazioni();
$adminTitle = 'Social';
$csrf = csrf_field();
$tipi = contenuti_tipi();
// I contenuti da collegare al calendario e da mostrare in Testi: non in cestino, i più recenti prima.
$contenuti = [];
foreach ($tipi as $k => $t) {
    try {
        $ordine = $k === 'pagine' ? 'publish_at' : 'TIMESTAMP(publish_date, publish_time)';
        foreach (db()->query('SELECT * FROM ' . tbl($t['tabella']) . " WHERE cestino IS NULL AND status <> 'draft' ORDER BY $ordine DESC, id DESC LIMIT 300")->fetchAll() as $r) $contenuti[] = [$k, $r];
    } catch (Throwable $e) {
    }
}
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<style>
.so-schede{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1rem}
.so-schede a{padding:.45rem .9rem;border-radius:999px;border:1px solid var(--border);background:var(--surface);color:var(--text);text-decoration:none;font-size:.86rem;font-weight:600}
.so-schede a.attiva{background:var(--coral);border-color:var(--coral);color:var(--su-accento)}
.so-intro{color:var(--text-muted);font-size:.9rem;max-width:860px;margin:0 0 1rem}
.so-anteprima{background:#eef0f5 /* fisso: l'email com'è nella posta */;border:1px solid var(--border);border-radius:var(--radius);padding:1rem;max-width:720px}
.so-anteprima-corpo{background:#fff /* fisso: l'email com'è nella posta */;color:#1f2937;border-radius:10px;padding:1rem}
.so-riga{display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem}
.so-riga label{display:flex;flex-direction:column;font-size:.8rem;font-weight:600;color:var(--text-muted);gap:.25rem}
.so-riga input[type=date]{padding:.45rem .6rem;border:1px solid var(--border);border-radius:8px;background:var(--campo);color:var(--text);font:inherit}
.so-carta{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;margin-bottom:1rem}
.so-carta h3{margin:0 0 .2rem;font-size:1rem}
.so-meta{font-size:.8rem;color:var(--text-muted);margin:0 0 .7rem}
.so-imm{display:flex;gap:.7rem;flex-wrap:wrap;margin:0 0 .8rem}
.so-imm a{font-size:.78rem;color:var(--accento-testo)}
.so-imm img{display:block;height:110px;width:auto;border:1px solid var(--border);border-radius:6px;margin-bottom:.25rem}
.so-canali{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.8rem}
.so-canale b{display:block;font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem;color:var(--text)}
.so-punto{display:inline-block;width:.6rem;height:.6rem;border-radius:50%;margin-right:.4rem;vertical-align:.05em}
.so-canale textarea{width:100%;min-height:150px;padding:.6rem;border:1px solid var(--border);border-radius:8px;background:var(--fondo-2);color:var(--text);font:inherit;font-size:.84rem;line-height:1.45;resize:vertical;cursor:copy}
.so-canale .so-condividi{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.3rem;font-size:.8rem}
.so-canale .so-condividi a{color:var(--accento-testo)}
.so-tabella td,.so-tabella th{vertical-align:top}
.so-stato-ok{color:var(--successo-testo);font-weight:600}
.so-stato-attesa{color:var(--attenzione-testo);font-weight:600}
.so-azioni{display:flex;gap:.4rem;flex-wrap:wrap}
.so-azioni form{display:inline}
.so-form .sc-corpo{display:grid;grid-template-columns:1fr 1fr;gap:.2rem 1rem}
.so-form .so-largo{grid-column:1/-1}
@media (max-width:760px){.so-form .sc-corpo{grid-template-columns:1fr}}
</style>

<nav class="so-schede" aria-label="Sezioni dei social">
<?php foreach ($schede as $k => $n): ?><a href="social.php?scheda=<?= $k ?>" class="<?= $k === $scheda ? 'attiva' : '' ?>"<?= $k === $scheda ? ' aria-current="page"' : '' ?>><?= h($n) ?></a><?php endforeach; ?>
</nav>

<?php if ($avvisi === ''): ?>
<div class="flash flash-error">Manca l'indirizzo degli avvisi: senza, le email del mattino non partono. Scrivilo in <a href="impostazioni.php">Impostazioni &gt; Configurazione email</a>.</div>
<?php endif; ?>

<?php if ($scheda === 'mattino'):
    $giorno = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['giorno'] ?? '')) ? (string)$_GET['giorno'] : date('Y-m-d');
    $m = social_email_mattino($giorno); ?>
<p class="so-intro">Ogni mattina il cron manda a <b><?= h($avvisi ?: 'l\'indirizzo degli avvisi') ?></b> l'email <b>"Oggi sul sito <?= h(social_dominio()) ?>"</b>: i contenuti che escono quel giorno, ognuno con le due immagini per i social e i testi pronti per ogni canale. Qui la vedi prima che parta.<?= $imp['email_mattino'] ? '' : ' <b>Oggi è spenta</b>: si accende in Impostazioni.' ?></p>
<form method="get" class="so-riga"><input type="hidden" name="scheda" value="mattino">
  <label>Giorno<input type="date" name="giorno" value="<?= h($giorno) ?>"></label>
  <button class="btn btn-secondary btn-sm">Mostra</button>
</form>
<?php if (!$m): ?>
<div class="so-carta"><p style="margin:0">Il <?= h(date('d/m/Y', strtotime($giorno))) ?> non esce niente: quel giorno l'email non parte. Un articolo con quella data di uscita (anche programmato) la fa partire.</p></div>
<?php else: preg_match('~<body[^>]*>(.*)</body>~s', $m[1], $b); ?>
<form method="post" class="so-riga"><?= $csrf ?><input type="hidden" name="azione" value="manda_mattino"><input type="hidden" name="giorno" value="<?= h($giorno) ?>">
  <button class="btn btn-primary btn-sm"<?= $avvisi === '' ? ' disabled' : '' ?>>Mandala adesso a <?= h($avvisi ?: 'me') ?></button>
  <span class="hint">Oggetto: <b><?= h($m[0]) ?></b></span>
</form>
<div class="so-anteprima"><div class="so-anteprima-corpo"><?= str_replace('src="' . h(social_assoluto('/')), 'src="/', $b[1] ?? '') ?></div></div>
<?php endif; ?>

<?php elseif ($scheda === 'calendario'):
    $modifica = isset($_GET['modifica']) ? social_programmato((int)$_GET['modifica']) : null;
    $nuovo = isset($_GET['nuovo']) || $modifica;
    $v = $modifica ?: ['id' => 0, 'data_invio' => date('Y-m-d', strtotime('+1 day')), 'tipo' => 'articoli', 'contenuto_id' => 0, 'titolo' => '', 'ancora' => '', 'linkedin' => '', 'facebook' => '', 'whatsapp' => '', 'immagine_og' => '', 'immagine_ig' => ''];
    $righe = social_programmati(date('Y-m-d', strtotime('-30 days')));
    $titoli = [];
    foreach ($contenuti as [$k, $c]) $titoli[$k . ':' . $c['id']] = $c['title']; ?>
<p class="so-intro">I testi social con un giorno: ogni mattina il cron manda un'email per ogni testo con la data di oggi (e per quelli rimasti indietro), con il link al contenuto e le immagini. Per esempio una settimana di post per un articolo lungo, un capitolo al giorno. Dopo l'invio il testo è segnato e non riparte.</p>
<?php if ($nuovo): ?>
<form method="post" class="sc-scatola so-form"><?= $csrf ?><input type="hidden" name="azione" value="programmato_salva"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
  <h3><?= $v['id'] ? 'Modifica il testo del ' . h(date('d/m/Y', strtotime($v['data_invio']))) : 'Un testo nuovo nel calendario' ?></h3>
  <div class="sc-corpo">
    <div><label for="so-data">Giorno</label><input type="date" id="so-data" name="data_invio" value="<?= h($v['data_invio']) ?>" required></div>
    <div><label for="so-cont">Contenuto a cui porta</label><select id="so-cont" name="contenuto" required><option value="">Scegli...</option>
      <?php foreach ($contenuti as [$k, $c]): $val = $k . ':' . $c['id']; ?><option value="<?= h($val) ?>"<?= $val === $v['tipo'] . ':' . $v['contenuto_id'] ? ' selected' : '' ?>><?= h(($tipi[$k]['Uno'] ?? $k) . ': ' . social_testo_vero((string)$c['title'])) ?></option><?php endforeach; ?>
    </select></div>
    <div><label for="so-tit">Titolo del giorno (facoltativo)</label><input type="text" id="so-tit" name="titolo" value="<?= h(social_testo_vero((string)$v['titolo'])) ?>" placeholder="Per esempio il capitolo di oggi"></div>
    <div><label for="so-anc">Ancora (facoltativa)</label><input type="text" id="so-anc" name="ancora" value="<?= h($v['ancora']) ?>" placeholder="per-esempio-un-capitolo"><div class="hint">Il link porta a quel punto della pagina: l'id di un titolo, senza #.</div></div>
    <div class="so-largo"><label for="so-li">LinkedIn</label><textarea id="so-li" name="linkedin" rows="4"><?= h(social_testo_vero((string)$v['linkedin'])) ?></textarea></div>
    <div class="so-largo"><label for="so-fb">Facebook e Instagram</label><textarea id="so-fb" name="facebook" rows="4"><?= h(social_testo_vero((string)$v['facebook'])) ?></textarea></div>
    <div class="so-largo"><label for="so-wa">WhatsApp e Telegram</label><textarea id="so-wa" name="whatsapp" rows="3"><?= h(social_testo_vero((string)$v['whatsapp'])) ?></textarea><div class="hint">Un testo vuoto prende quello scritto nel riquadro Social del contenuto. Il link e gli hashtag li aggiunge il CMS.</div></div>
    <div><label for="so-og">Immagine per LinkedIn e Facebook (facoltativa)</label><input type="text" id="so-og" name="immagine_og" value="<?= h($v['immagine_og']) ?>" placeholder="/uploads/..." data-immagine></div>
    <div><label for="so-ig">Immagine per Instagram (facoltativa)</label><input type="text" id="so-ig" name="immagine_ig" value="<?= h($v['immagine_ig']) ?>" placeholder="/uploads/..." data-immagine><div class="hint">Vuote = quelle fatte dall'immagine in evidenza del contenuto. Scegli dai Media apre la libreria (e ci carichi anche un'immagine nuova).</div></div>
    <div class="so-largo so-azioni"><button class="btn btn-primary">Salva</button><a class="btn btn-secondary" href="social.php?scheda=calendario">Annulla</a></div>
  </div>
</form>
<?php else: ?>
<p><a class="btn btn-primary btn-sm" href="social.php?scheda=calendario&amp;nuovo=1">+ Un testo nel calendario</a></p>
<?php endif; ?>
<div class="admin-table-wrap ct-tabella so-tabella">
  <table>
    <thead><tr><th>Giorno</th><th>Testo</th><th>Contenuto</th><th>Stato</th><th></th></tr></thead>
    <tbody>
    <?php if (!$righe): ?><tr><td colspan="5"><div class="empty-state"><p>Il calendario è vuoto: premi "+ Un testo nel calendario".</p></div></td></tr><?php endif; ?>
    <?php foreach ($righe as $r): ?>
      <tr>
        <td><b><?= h(date('d/m/Y', strtotime($r['data_invio']))) ?></b></td>
        <td><?= h(social_testo_vero((string)($r['titolo'] ?: mb_substr((string)($r['linkedin'] ?: $r['facebook'] ?: $r['whatsapp']), 0, 80)))) ?: '<span class="hint">i testi del contenuto</span>' ?></td>
        <td class="ct-muto"><?= h(social_testo_vero((string)($titoli[$r['tipo'] . ':' . $r['contenuto_id']] ?? 'non c\'è più'))) ?><?= $r['ancora'] !== '' ? ' <span class="hint">#' . h($r['ancora']) . '</span>' : '' ?></td>
        <td><?= $r['inviato_at'] ? '<span class="so-stato-ok">Inviato il ' . h(date('d/m H:i', strtotime($r['inviato_at']))) . '</span>' : '<span class="so-stato-attesa">In attesa</span>' ?></td>
        <td class="so-azioni">
          <a class="btn btn-secondary btn-sm" href="social.php?scheda=calendario&amp;modifica=<?= (int)$r['id'] ?>">Modifica</a>
          <form method="post"><?= $csrf ?><input type="hidden" name="azione" value="programmato_manda"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm">Mandalo adesso a me</button></form>
          <?php if ($r['inviato_at']): ?><form method="post"><?= $csrf ?><input type="hidden" name="azione" value="programmato_rimetti"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm">Rimetti in attesa</button></form><?php endif; ?>
          <form method="post" data-conferma="Togliere questo testo dal calendario?"><?= $csrf ?><input type="hidden" name="azione" value="programmato_elimina"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm" style="color:var(--errore-testo)">Elimina</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($scheda === 'testi'): ?>
<p class="so-intro">I testi pronti per ogni canale, dei contenuti più recenti. <b>Fai clic su un testo per copiarlo</b>, poi incollalo nel social. I testi si cambiano nella scheda del contenuto, riquadro Social.</p>
<?php $mostrati = 0; foreach ($contenuti as [$k, $c]): if ($k === 'pagine' || $mostrati >= 15) continue; $mostrati++;
    $testi = social_testi($k, $c); $img = social_immagini($k, $c); $url = social_url($k, $c); $cond = social_condividi($url, $testi); ?>
<div class="so-carta">
  <h3><?= h(social_testo_vero((string)$c['title'])) ?></h3>
  <p class="so-meta"><?= h($tipi[$k]['Uno'] ?? $k) ?> &middot; <?= h(CONTENUTI_STATI[contenuti_stato($k, $c)] ?? '') ?> &middot; <?= h(substr(contenuti_quando($k, $c), 0, 16)) ?> &middot; <a href="contenuto.php?tipo=<?= h($k) ?>&amp;id=<?= (int)$c['id'] ?>#social">Cambia i testi</a></p>
  <?php if ($img['og'] !== ''): ?><div class="so-imm">
    <div><img src="<?= h(social_relativo($img['og'])) ?>" alt="Immagine per LinkedIn e Facebook"><a href="<?= h(social_relativo($img['og'])) ?>" download>Scarica 1200x630</a></div>
    <div><img src="<?= h(social_relativo($img['ig'])) ?>" alt="Immagine per Instagram"><a href="<?= h(social_relativo($img['ig'])) ?>" download>Scarica 1080x1350</a></div>
  </div><?php endif; ?>
  <div class="so-canali">
  <?php foreach ($testi as $canale => $t): ?>
    <div class="so-canale"><b><i class="so-punto" style="background:<?= SOCIAL_COLORI[$canale] ?? 'var(--text-muted)' ?>"></i><?= h(SOCIAL_CANALI[$canale] ?? $canale) ?></b>
      <textarea readonly data-copia-se-stesso aria-label="Testo per <?= h(SOCIAL_CANALI[$canale] ?? $canale) ?>"><?= h($t) ?></textarea>
      <div class="so-condividi"><?php foreach (['linkedin' => ['linkedin'], 'facebook' => ['facebook'], 'whatsapp' => ['whatsapp', 'telegram']][$canale] ?? [] as $x): if (isset($cond[$x])): ?><a href="<?= h($cond[$x][1]) ?>" target="_blank" rel="noopener"><?= h($cond[$x][0]) ?></a><?php endif; endforeach; ?></div>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; if (!$mostrati): ?><div class="so-carta"><p style="margin:0">Ancora nessun articolo pubblicato o programmato.</p></div><?php endif; ?>

<?php else: ?>
<form method="post" class="sc-scatola" style="max-width:860px"><?= $csrf ?><input type="hidden" name="azione" value="impostazioni">
  <h3>Impostazioni dei social</h3>
  <div class="sc-corpo">
    <label>Le email del mattino</label>
    <label style="font-weight:400"><input type="checkbox" name="email_mattino" value="1"<?= $imp['email_mattino'] ? ' checked' : '' ?>> "Oggi sul sito": i contenuti che escono oggi, con testi e immagini</label>
    <label style="font-weight:400"><input type="checkbox" name="social_giorno" value="1"<?= $imp['social_giorno'] ? ' checked' : '' ?>> "Social di oggi": i testi del calendario</label>
    <div class="hint">Arrivano a <?= h($avvisi ?: 'l\'indirizzo degli avvisi (da scrivere)') ?>, al giro del mattino del cron.</div>
    <label style="margin-top:1rem">I canali</label>
    <?php foreach (SOCIAL_CANALI as $k => $n): ?><label style="font-weight:400"><input type="checkbox" name="canale[<?= $k ?>]" value="1"<?= !empty($imp['canali'][$k]) ? ' checked' : '' ?>> <?= h($n) ?></label><?php endforeach; ?>
    <label for="so-h-li" style="margin-top:1rem">Hashtag fissi per LinkedIn</label><input type="text" id="so-h-li" name="hashtag[linkedin]" value="<?= h(social_testo_vero($imp['hashtag']['linkedin'])) ?>" placeholder="#iltuomarchio #lavoro">
    <label for="so-h-fb">Hashtag fissi per Facebook</label><input type="text" id="so-h-fb" name="hashtag[facebook]" value="<?= h(social_testo_vero($imp['hashtag']['facebook'])) ?>">
    <label for="so-h-ig">Hashtag fissi per Instagram</label><input type="text" id="so-h-ig" name="hashtag[instagram]" value="<?= h(social_testo_vero($imp['hashtag']['instagram'])) ?>">
    <label for="so-h-cat">Hashtag per categoria</label>
    <textarea id="so-h-cat" name="hashtag_categorie" rows="5" placeholder="ricette: #ricette #cucina&#10;eventi: #eventi"><?= h(social_testo_vero($imp['hashtag_categorie'])) ?></textarea>
    <div class="hint">Una riga per categoria: il nome (o lo slug), due punti, gli hashtag. Si aggiungono a quelli fissi quando il contenuto è in quella categoria.</div>
    <label for="so-wa-l" style="margin-top:1rem">Un link in più nei testi di WhatsApp (facoltativo)</label><input type="text" id="so-wa-l" name="whatsapp_link" value="<?= h($imp['whatsapp_link']) ?>" placeholder="/prenota/ oppure https://...">
    <label for="so-wa-e">Con la frase</label><input type="text" id="so-wa-e" name="whatsapp_etichetta" value="<?= h(social_testo_vero($imp['whatsapp_etichetta'])) ?>" placeholder="Prenota una chiamata:">
    <div style="margin-top:1rem"><button class="btn btn-primary">Salva</button></div>
  </div>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
