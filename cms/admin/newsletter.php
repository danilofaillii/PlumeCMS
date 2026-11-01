<?php
/**
 * Newsletter: campagne, iscritti, importazione (per esempio da Brevo), ritmo di invio.
 * Motore in includes/newsletter.php, editor in newsletter-campagna.php, invio in cron-newsletter.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-email.php'; // 2.7.0: l'email di conferma comune
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();

$adminTitle = 'Newsletter';
$scheda = in_array($_GET['scheda'] ?? '', ['campagne', 'iscritti', 'liste', 'impostazioni'], true) ? $_GET['scheda'] : 'campagne';
if ($scheda === 'impostazioni') $adminTitle = 'Impostazioni della newsletter';

// 2.8.0: le schede Iscritti e Liste sono diventate Contatti e Liste (newsletter-contatti.php, come Brevo). Gli indirizzi
// vecchi rimandano là con gli stessi filtri: 302 e no-store, mai 301 (trappola del 301 che il browser ricorda).
if (in_array($scheda, ['iscritti', 'liste'], true) || ($_GET['scarica'] ?? '') === 'iscritti') {
    $q = array_intersect_key($_GET, array_flip(['cerca', 'stato', 'lista']));
    if ($scheda === 'liste') $q = ['scheda' => 'liste'];
    if (($_GET['scarica'] ?? '') === 'iscritti') $q = ['scarica' => 'tutti'];
    header('Cache-Control: no-store');
    header('Location: newsletter-contatti.php' . ($q ? '?' . http_build_query($q) : ''), true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = $_POST['azione'] ?? '';
    if ($azione === 'nuova') {
        $ora = date('Y-m-d H:i:s');
        db()->prepare('INSERT INTO ' . tbl('newsletter_campagne') . " (oggetto, corpo, liste, creato, aggiornato) VALUES ('', '', ?, ?, ?)")->execute([(string)newsletter_lista_principale(), $ora, $ora]);
        header('Location: newsletter-campagna.php?id=' . (int)db()->lastInsertId());
        exit;
    }
    if ($azione === 'duplica') {
        $c = newsletter_campagna((int)($_POST['id'] ?? 0));
        if ($c) {
            $ora = date('Y-m-d H:i:s');
            db()->prepare('INSERT INTO ' . tbl('newsletter_campagne') . ' (oggetto, anteprima, corpo, pulsante_testo, pulsante_link, liste, mittente_id, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute(['Copia di ' . $c['oggetto'], $c['anteprima'], $c['corpo'], $c['pulsante_testo'], $c['pulsante_link'], $c['liste'], (int)($c['mittente_id'] ?? 0), $ora, $ora]);
            header('Location: newsletter-campagna.php?id=' . (int)db()->lastInsertId());
            exit;
        }
    }
    if ($azione === 'elimina') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM ' . tbl('newsletter_campagne') . " WHERE id = ? AND stato = 'bozza'")->execute([$id]);
        flash('success', 'Bozza eliminata.');
    }
    if ($azione === 'impostazioni') {
        $rispondi = trim((string)($_POST['rispondi_a'] ?? ''));
        save_setting('newsletter_config', json_encode([
            'per_ora' => max(10, min(2000, (int)($_POST['per_ora'] ?? 150))),
            'per_giorno' => max(0, min(100000, (int)($_POST['per_giorno'] ?? 0))),
            'rispondi_a' => filter_var($rispondi, FILTER_VALIDATE_EMAIL) ? $rispondi : '',
        ]));
        flash('success', 'Impostazioni della newsletter salvate.');
        header('Location: newsletter.php?scheda=impostazioni');
        exit;
    }
    // 2.7.0: l'email di conferma comune (modulo della newsletter e base per i form). Solo le parti diverse dal testo di partenza.
    if ($azione === 'conferma_email') {
        $j = nl_email_da_salvare($_POST['ce'] ?? [], true, false);
        save_setting('newsletter_conferma_email', $j);
        flash('success', $j === '' ? 'Email di conferma: si usano i testi di partenza.' : 'Email di conferma salvata. Vale per il modulo della newsletter e per i form che non hanno testi loro.');
        header('Location: newsletter.php?scheda=impostazioni#nl-conferma');
        exit;
    }
    if ($azione === 'giro') {
        $log = newsletter_invia_giro();
        flash('success', 'Giro di invio: ' . ($log ? implode('; ', $log) : 'niente da mandare') . '.');
    }
    header('Location: newsletter.php');
    exit;
}

$numeri = newsletter_numeri();
$liste = newsletter_liste();
$nomeLista = array_column($liste, 'nome', 'id');
$cfg = newsletter_config();
$ultimoGiro = @file(__DIR__ . '/../logs/newsletter.log', FILE_IGNORE_NEW_LINES) ?: [];
$ultimoGiro = end($ultimoGiro) ?: '';
$statoNome = ['bozza' => 'Bozza', 'programmata' => 'Programmata', 'in_invio' => 'In invio', 'sospesa' => 'Sospesa', 'inviata' => 'Inviata'];

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.nl-numeri{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.25rem}
.nl-numeri div{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:1rem}
.nl-numeri b{display:block;font-size:1.8rem}
.nl-schede{display:flex;gap:.25rem;margin-bottom:1rem}
.nl-schede a{padding:.5rem 1rem;border:1px solid var(--border);border-radius:8px 8px 0 0;text-decoration:none;color:var(--text-muted);font-weight:600}
.nl-schede a.attiva{background:var(--surface);color:var(--text)}
.nl-barra{height:6px;background:var(--fondo-2);border-radius:3px;min-width:120px}.nl-barra div{height:6px;background:var(--coral);border-radius:3px}
.nl-riga{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center}
.nl-avviso{background:var(--mustard-light);color:var(--attenzione-testo);border-radius:8px;padding:.6rem .9rem;margin:0 0 1rem}
.nl-errori{display:block;font-size:.78rem;color:var(--attenzione-testo)}
</style>

<div class="nl-numeri">
  <div><b><?= $numeri['attivo'] ?></b>iscritti attivi</div>
  <div><b><?= $numeri['in_attesa'] ?></b>in attesa di conferma</div>
  <div><b><?= $numeri['uscito'] ?></b>usciti</div>
  <div><b><?= $numeri['rimbalzato'] ?></b><a href="newsletter-contatti.php?stato=motivo:hardBounce">rimbalzati</a></div>
  <?php if ($numeri['spam']): ?><div><b><?= (int)$numeri['spam'] ?></b><a href="newsletter-contatti.php?stato=motivo:contactFlaggedAsSpam">hanno segnalato spam</a></div><?php endif; ?>
  <?php if ($numeri['bloccato']): ?><div><b><?= (int)$numeri['bloccato'] ?></b><a href="newsletter-contatti.php?stato=lista_nera">in lista nera per altri motivi</a></div><?php endif; ?>
</div>
<?php $nlCaldo = consegna_riscaldamento_stato(); if ($nlCaldo['acceso']): ?><p class="nl-avviso" role="status" id="nl-riscaldamento">Riscaldamento acceso, giorno <?= (int)$nlCaldo['giorno'] ?>: oggi partono al massimo <b><?= (int)$nlCaldo['oggi'] ?></b> email<?= $nlCaldo['finito'] ? ' (arrivato al limite scritto)' : ', domani ' . (int)$nlCaldo['domani'] ?>. <a href="consegna.php?scheda=riscaldamento">Vedi</a>.</p><?php endif; ?>
<?php if ($numeri['segnalati']): ?><p class="nl-avviso" role="status"><b><?= (int)$numeri['segnalati'] ?></b> <?= $numeri['segnalati'] === 1 ? 'iscritto ha' : 'iscritti hanno' ?> avuto tre errori temporanei di fila: <?= $numeri['segnalati'] === 1 ? 'riceve' : 'ricevono' ?> ancora, ma conviene controllare l'indirizzo. <a href="newsletter-contatti.php?stato=errori">Vedi chi</a>.</p><?php endif; ?>

<nav class="nl-schede">
  <?php foreach (['campagne' => 'Campagne email', 'impostazioni' => 'Impostazioni'] as $k => $v): ?>
    <a href="?scheda=<?= $k ?>" class="<?= $scheda === $k ? 'attiva' : '' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($scheda === 'campagne'):
  $campagne = db()->query('SELECT * FROM ' . tbl('newsletter_campagne') . ' ORDER BY id DESC LIMIT 200')->fetchAll(); ?>
<div class="form-card">
  <div class="nl-riga" style="justify-content:space-between;margin-bottom:1rem">
    <h3 style="margin:0">Campagne</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="nuova"><button class="btn btn-primary" type="submit">+ Nuova campagna</button></form>
  </div>
  <div style="overflow-x:auto"><table class="table">
    <tr><th>Oggetto</th><th>Liste</th><th>Stato</th><th>Quando</th><th>Avanzamento</th><th></th></tr>
    <?php foreach ($campagne as $c): $pct = $c['destinatari'] ? round($c['inviate'] / $c['destinatari'] * 100) : 0; ?>
    <tr>
      <td><a href="newsletter-campagna.php?id=<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori($c['oggetto']) ?: '(senza oggetto)') ?></a></td>
      <td><small><?= h(implode(', ', array_map(fn($l) => $nomeLista[$l] ?? '', newsletter_liste_campagna($c)))) ?></small></td>
      <td><?= $statoNome[$c['stato']] ?? h($c['stato']) ?></td>
      <td><?= $c['invio_at'] ? h(date('d/m/Y H:i', strtotime($c['invio_at']))) : '' ?></td>
      <td><?php if ($c['destinatari']): ?><div class="nl-barra"><div style="width:<?= $pct ?>%"></div></div><small><?= (int)$c['inviate'] ?> di <?= (int)$c['destinatari'] ?><?= $c['errori'] ? ', ' . (int)$c['errori'] . ' non partite' : '' ?></small><?php endif; ?></td>
      <td class="nl-riga">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="duplica"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Duplica</button></form>
        <?php if ($c['stato'] === 'bozza'): ?><form method="post" data-conferma="Eliminare questa bozza?"><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Elimina</button></form><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; if (!$campagne): ?><tr><td colspan="6">Ancora nessuna campagna. Premi "Nuova campagna".</td></tr><?php endif; ?>
  </table></div>
  <p style="font-size:.85rem;color:var(--text-muted);margin-top:1rem">
    Le email partono dal cron ogni 5 minuti, al massimo <?= (int)$cfg['per_ora'] ?> l'ora<?= (int)$cfg['per_giorno'] ? ' e ' . (int)$cfg['per_giorno'] . ' al giorno' : '' ?>, contando tutte le email del sito. Ultimo giro: <?= h($ultimoGiro ?: 'nessuno registrato') ?>.
  </p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="giro"><button class="btn btn-secondary btn-sm" type="submit">Fai partire un giro adesso</button></form>
</div>

<?php else: ?>
<div class="form-card">
  <h3>Attributi dei contatti</h3>
  <p class="hint" style="margin-top:0">I campi dei contatti (città, data di nascita, cliente sì o no...), da usare nelle email con i segnaposti come {{ contact.NOME }}. Come in Brevo, Impostazioni &gt; Contatti &gt; Attributi dei contatti.</p>
  <a class="btn btn-secondary btn-sm" href="newsletter-attributi.php">Apri gli attributi dei contatti</a>
</div>
<div class="form-card" style="margin-top:1.25rem">
  <h3>Impostazioni</h3>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="impostazioni">
    <div class="form-group"><label for="per_ora">Email al massimo ogni ora</label><input type="number" id="per_ora" name="per_ora" min="10" max="2000" value="<?= (int)$cfg['per_ora'] ?>">
      <div class="hint">I server condivisi limitano le email per ora: chiedi al tuo hosting il limite del tuo piano e scrivi qui un numero un po' più basso. Contano tutte le email del sito (conferme, avvisi dei form, flussi, campagne): nell'ultima ora ne sono partite <?= posta_inviate(3600) ?>.</div></div>
    <div class="form-group"><label for="per_giorno">Email al massimo al giorno</label><input type="number" id="per_giorno" name="per_giorno" min="0" max="100000" value="<?= (int)$cfg['per_giorno'] ?>">
      <div class="hint">Nelle ultime 24 ore, contando tutte le email del sito. 0 vuol dire nessun limite. Nelle ultime 24 ore ne sono partite <?= posta_inviate(86400) ?>.
        Indirizzo nuovo o tanti iscritti tutti insieme? Accendi il <a href="consegna.php?scheda=riscaldamento">riscaldamento</a>: il limite parte basso e sale da solo.</div></div>
    <div class="form-group"><label for="rispondi_a">Le risposte alla newsletter arrivano a</label><input type="email" id="rispondi_a" name="rispondi_a" value="<?= h($cfg['rispondi_a']) ?>" placeholder="la tua email">
      <div class="hint">Vuoto: le risposte vanno all'indirizzo che spedisce (scheda Posta).</div></div>
    <button class="btn btn-primary" type="submit">Salva</button>
  </form>
  <p style="font-size:.85rem;color:var(--text-muted);margin-top:1rem">
    Cron da aggiungere in cPanel, ogni 5 minuti:<br><code>/usr/local/bin/php <?= h(cms_radice()) ?>/cron-newsletter.php</code>
  </p>
</div>
<?php /* 2.7.0: l'email di conferma dell'iscrizione, valori comuni */ require_once __DIR__ . '/includes/email-conferma-campi.php'; $ecValori = nl_email_comuni(); ?>
<?= ec_stile_html() ?>
<div class="form-card" id="nl-conferma">
  <h3>Email di conferma dell'iscrizione</h3>
  <form method="post" data-email-conferma="email-conferma-api.php" data-contesto="comuni" data-csrf="<?= h(csrf_token()) ?>">
    <?= csrf_field() ?><input type="hidden" name="azione" value="conferma_email">
    <p class="hint" style="margin-top:0">L'email con il pulsante «Confermo l'iscrizione» che arriva a chi si iscrive con il modulo della newsletter del sito. Vale anche per i form che iscrivono a una lista con l'email di conferma, per le parti che il form non cambia (Form, scheda del form, riquadro Email di conferma). Lascia vuoto un campo per usare il testo di partenza.</p>
    <?= ec_segnaposti_html('comuni') ?>
    <?= ec_campi_html($ecValori, nl_email_partenza(true), 'comuni', []) ?>
    <?= ec_anteprima_html() ?>
    <p style="margin-top:1rem"><button class="btn btn-primary" type="submit">Salva l'email di conferma</button></p>
  </form>
</div>
<script src="<?= pannello_file('assets/email-conferma.js') ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
