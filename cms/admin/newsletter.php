<?php
/**
 * Newsletter: campagne, iscritti, importazione (per esempio da Brevo), ritmo di invio.
 * Motore in includes/newsletter.php, editor in newsletter-campagna.php, invio in cron-newsletter.php.
 * 2.11.0 (includes/newsletter-campagne.php): Crea campagna con il nome, elenco con le viste, Report, Duplica con la
 * scelta delle parti, Invia ai nuovi contatti, Rimanda ai non aperti, Archivia, Annulla, Elimina solo in bozza; in
 * Impostazioni l'indirizzo dell'azienda, le pagine di disiscrizione e preferenze, i contatti non coinvolti.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-email.php'; // 2.7.0: l'email di conferma comune
require_once __DIR__ . '/../includes/newsletter-importa.php'; // 2.9.0: la lista dei test
require_once __DIR__ . '/../includes/newsletter-campagne.php'; // 2.11.0
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();
newsletter_campagne_schema();

$adminTitle = 'Campagne email';
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
    $cid = (int)($_POST['id'] ?? 0);
    $torna = 'newsletter.php' . (isset($_POST['vista']) && isset(NL_CAMP_VISTE[$_POST['vista']]) ? '?vista=' . $_POST['vista'] : '');
    if ($azione === 'nuova') {
        // 2.11.0: Crea campagna > Normale (2.13.0: o Test A/B) > nome della campagna, poi il riepilogo.
        $r = newsletter_campagna_crea((string)($_POST['nome'] ?? ''), (string)($_POST['tipo'] ?? 'normale'));
        if (is_string($r)) { flash('error', $r); header('Location: newsletter.php?crea=1#nl-crea'); exit; }
        header('Location: newsletter-campagna.php?id=' . $r);
        exit;
    }
    if ($azione === 'duplica') {
        $parti = array_values(array_intersect(array_keys(NL_CAMP_PARTI), (array)($_POST['parti'] ?? [])));
        $nuovo = newsletter_campagna_duplica($cid, $parti);
        if ($nuovo) { flash('success', 'Campagna duplicata: controlla le sezioni e programma l\'invio.'); header('Location: newsletter-campagna.php?id=' . $nuovo); exit; }
        flash('error', 'Campagna non trovata.');
    }
    if ($azione === 'nuovi' || $azione === 'rinvio') {
        $r = newsletter_campagna_figlia($cid, $azione, (string)($_POST['oggetto'] ?? ''));
        if (is_string($r)) flash('error', $r);
        else { flash('success', $azione === 'nuovi' ? 'Campagna per i nuovi contatti pronta: arriva solo a chi non ha ricevuto la prima. Scegli quando mandarla.' : 'Rinvio pronto: arriva solo a chi ha ricevuto la campagna e non l\'ha aperta. Scegli quando mandarlo.'); header('Location: newsletter-campagna.php?id=' . $r); exit; }
    }
    if ($azione === 'elimina') {
        if (newsletter_campagna_elimina($cid)) flash('success', 'Bozza eliminata.');
        else flash('error', 'Si elimina solo una campagna in bozza: quelle partite si archiviano.');
    }
    if ($azione === 'archivia' || $azione === 'ripristina') {
        $ok = newsletter_campagna_archivia($cid, $azione === 'archivia');
        if ($azione === 'archivia') $ok ? flash('success', 'Campagna archiviata: la trovi nella vista Archiviate.') : flash('error', 'Si archiviano solo le campagne inviate, sospese o annullate.');
        else flash('success', 'Campagna tolta dall\'archivio.');
    }
    if ($azione === 'annulla') {
        newsletter_campagna_annulla($cid) ? flash('success', 'Campagna annullata: le email non ancora partite non partono più.') : flash('error', 'Si annulla solo una campagna programmata, in invio o sospesa.');
    }
    if ($azione === 'impostazioni') {
        $rispondi = trim((string)($_POST['rispondi_a'] ?? ''));
        save_setting('newsletter_config', json_encode([
            'per_ora' => max(10, min(2000, (int)($_POST['per_ora'] ?? 150))),
            'per_giorno' => max(0, min(100000, (int)($_POST['per_giorno'] ?? 0))),
            'rispondi_a' => filter_var($rispondi, FILTER_VALIDATE_EMAIL) ? $rispondi : '',
            'coinvolti_giorni' => max(30, min(365, (int)($_POST['coinvolti_giorni'] ?? 90))), // 2.11.0
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
    // 2.9.0: la lista dei test (Brevo: Impostazioni > Lista dei test): fino a 20 indirizzi, tutti già contatti.
    if ($azione === 'lista_test') {
        $e = newsletter_lista_test_salva((string)($_POST['indirizzi'] ?? ''));
        if ($e !== '') { flash('error', $e); $_SESSION['nl_test_testo'] = mb_substr((string)($_POST['indirizzi'] ?? ''), 0, 5000); }
        else flash('success', 'Lista dei test salvata: le prove delle campagne si mandano da qui.');
        header('Location: newsletter.php?scheda=impostazioni#nl-test');
        exit;
    }
    // 2.11.0: l'indirizzo dell'azienda nel piede delle campagne (obbligatorio per legge nelle email promozionali).
    if ($azione === 'indirizzo') {
        $ind = trim(preg_replace("/\n{2,}/", "\n", str_replace("\r", '', strip_tags((string)($_POST['indirizzo'] ?? '')))));
        save_setting('newsletter_indirizzo', newsletter_4byte_dentro(mb_substr($ind, 0, 400)));
        flash('success', $ind === '' ? 'Indirizzo dell\'azienda tolto: il piede delle campagne resta senza.' : 'Indirizzo dell\'azienda salvato: esce in fondo a ogni campagna.');
        header('Location: newsletter.php?scheda=impostazioni#nl-indirizzo');
        exit;
    }
    // 2.11.0: i testi della pagina di disiscrizione e del centro preferenze, le liste e i dati che il contatto può cambiare.
    if ($azione === 'pagine') {
        $base = newsletter_pagine_partenza();
        $salva = [];
        foreach ($base as $k => $v) {
            if (is_string($v)) {
                $t = trim(str_replace("\r", '', strip_tags((string)($_POST['pg'][$k] ?? ''))));
                if ($t !== '' && $t !== $v) $salva[$k] = newsletter_4byte_dentro(mb_substr($t, 0, 1000));
            }
        }
        foreach (NL_USCITA_MOTIVI as $k => $v) {
            $t = trim(strip_tags(str_replace(["\r", "\n"], ' ', (string)($_POST['pg']['motivi'][$k] ?? ''))));
            if ($t !== '' && $t !== $v) $salva['motivi'][$k] = newsletter_4byte_dentro(mb_substr($t, 0, 100));
        }
        $salva['questionario'] = !empty($_POST['pg']['questionario']);
        $attr = newsletter_attributi();
        $salva['attributi'] = array_values(array_filter((array)($_POST['pg']['attributi'] ?? []), fn($n) => is_string($n) && isset($attr[$n]) && nl_attributo_preferibile($attr[$n])));
        save_setting('newsletter_pagine', json_encode($salva, JSON_UNESCAPED_UNICODE));
        $pub = array_map('intval', (array)($_POST['pubbliche'] ?? []));
        db()->exec('UPDATE ' . tbl('newsletter_liste') . ' SET pubblica = 0');
        if ($pub) db()->exec('UPDATE ' . tbl('newsletter_liste') . ' SET pubblica = 1 WHERE id IN (' . implode(',', array_filter($pub)) . ')');
        flash('success', 'Pagine di disiscrizione e preferenze salvate.');
        header('Location: newsletter.php?scheda=impostazioni#nl-pagine');
        exit;
    }
    if ($azione === 'giro') {
        $log = newsletter_invia_giro();
        flash('success', 'Giro di invio: ' . ($log ? implode('; ', $log) : 'niente da mandare') . '.');
    }
    header('Location: ' . $torna);
    exit;
}

$numeri = newsletter_numeri();
$liste = newsletter_liste();
$nomeLista = array_column($liste, 'nome', 'id');
$cfg = newsletter_config();
$ultimoGiro = @file(__DIR__ . '/../logs/newsletter.log', FILE_IGNORE_NEW_LINES) ?: [];
$ultimoGiro = end($ultimoGiro) ?: '';
$statoNome = NL_CAMP_STATI;
$segNomi = nl_camp_segmenti();

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
.nl-sotto-titolo{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);border-top:1px solid var(--border);padding-top:.8rem;margin:1.2rem 0 .6rem}
.nl-casella{display:flex;gap:.45rem;align-items:center;min-height:36px;margin:.3rem 0 .8rem;font-weight:400}
.nl-avviso{background:var(--mustard-light);color:var(--attenzione-testo);border-radius:8px;padding:.6rem .9rem;margin:0 0 1rem}
.nl-errori{display:block;font-size:.78rem;color:var(--attenzione-testo)}
.nl-viste{display:flex;flex-wrap:wrap;gap:.35rem;margin:0 0 1rem}
.nl-viste a{display:inline-flex;align-items:center;gap:.35rem;min-height:36px;padding:.3rem .8rem;border:1px solid var(--bordo-campo);border-radius:999px;text-decoration:none;color:var(--text);font-size:.88rem}
.nl-viste a.attiva{background:var(--coral);border-color:var(--coral);color:var(--su-accento);font-weight:600}
.nl-conto{font-size:.78rem;opacity:.85}
.nl-stato{display:inline-block;padding:.1rem .55rem;border-radius:999px;border:1px solid var(--bordo-campo);font-size:.8rem;white-space:nowrap}
.nl-tipo-ab{display:inline-block;margin-left:.35rem;padding:0 .45rem;border-radius:6px;background:var(--info-chiaro);color:var(--info-testo);border:1px solid var(--info-bordo);font-size:.75rem;font-weight:700;vertical-align:middle}
.nl-azioni{position:relative}
.nl-azioni>summary{list-style:none}.nl-azioni>summary::-webkit-details-marker{display:none}
.nl-azioni-menu{display:flex;flex-direction:column;gap:.35rem;margin-top:.4rem;min-width:12rem}
.nl-azioni-menu form{margin:0}.nl-azioni-menu .btn{width:100%;justify-content:center;text-align:center}
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
  $vista = isset(NL_CAMP_VISTE[$_GET['vista'] ?? '']) ? $_GET['vista'] : 'tutte';
  $conti = newsletter_campagne_conteggi();
  $campagne = db()->query('SELECT * FROM ' . tbl('newsletter_campagne') . ' WHERE ' . NL_CAMP_VISTE[$vista][1] . ' ORDER BY id DESC LIMIT 200')->fetchAll();
  $dup = isset($_GET['duplica']) ? newsletter_campagna((int)$_GET['duplica']) : null;
  $rim = isset($_GET['rimanda']) ? newsletter_campagna((int)$_GET['rimanda']) : null;
  $nomeC = fn($c) => newsletter_4byte_fuori((string)($c['nome'] !== '' ? $c['nome'] : $c['oggetto'])) ?: '(senza nome)';
  $indirizzoManca = newsletter_indirizzo_azienda() === ''; ?>
<?php if ($indirizzoManca): ?><p class="nl-avviso" role="status" id="nl-avviso-indirizzo">Manca l'indirizzo dell'azienda nel piede delle email: per legge le email promozionali devono dire chi le manda e dove. <a href="newsletter.php?scheda=impostazioni#nl-indirizzo">Aggiungilo in Impostazioni</a>.</p><?php endif; ?>
<details class="form-card nlx-riquadro" id="nl-crea"<?= isset($_GET['crea']) ? ' open' : '' ?>>
  <summary><h3>Crea una campagna</h3></summary>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="nuova">
    <fieldset class="nlk-scelte"><legend>Tipo di campagna</legend>
      <label><input type="radio" name="tipo" value="normale" checked> Normale: una sola versione dell'email per tutti</label>
      <label><input type="radio" name="tipo" value="ab"> Test A/B: due versioni (oggetto o contenuto) a due gruppi di prova, poi la migliore a tutti gli altri</label>
    </fieldset>
    <div class="form-group"><label for="nl-crea-nome">Nome della campagna</label><input type="text" id="nl-crea-nome" name="nome" maxlength="150" required placeholder="per esempio: Novità di novembre" aria-describedby="nl-crea-aiuto">
      <div class="hint" id="nl-crea-aiuto">Serve a te per ritrovarla nell'elenco: chi riceve l'email non lo vede.</div></div>
    <button class="btn btn-primary" type="submit">Crea la campagna</button>
  </form>
</details>

<?php if ($dup): ?>
<form method="post" class="form-card" id="nl-duplica" style="margin-top:1.25rem">
  <h3>Duplica «<?= h($nomeC($dup)) ?>»</h3>
  <?= csrf_field() ?><input type="hidden" name="azione" value="duplica"><input type="hidden" name="id" value="<?= (int)$dup['id'] ?>">
  <fieldset class="nlk-scelte"><legend>Cosa copiare nella campagna nuova</legend>
    <?php foreach (NL_CAMP_PARTI as $k => [$et]): ?><label><input type="checkbox" name="parti[]" value="<?= $k ?>" checked> <?= h($et) ?></label><?php endforeach; ?>
  </fieldset>
  <p class="hint">Quello che non copi parte vuoto. La campagna nuova nasce in bozza, con il nome «Copia di <?= h($nomeC($dup)) ?>».</p>
  <div class="nl-riga"><button class="btn btn-primary" type="submit">Duplica la campagna</button> <a class="btn btn-secondary" href="newsletter.php">Lascia stare</a></div>
</form>
<?php endif; ?>
<?php if ($rim): $rimErr = newsletter_rinvio_possibile($rim); ?>
<form method="post" class="form-card" id="nl-rimanda" style="margin-top:1.25rem">
  <h3>Rimanda «<?= h($nomeC($rim)) ?>» ai non aperti</h3>
  <?= csrf_field() ?><input type="hidden" name="azione" value="rinvio"><input type="hidden" name="id" value="<?= (int)$rim['id'] ?>">
  <?php if ($rimErr !== ''): ?><p class="nl-avviso" role="alert"><?= h($rimErr) ?></p><a class="btn btn-secondary" href="newsletter.php">Torna all'elenco</a>
  <?php else: ?>
  <p class="hint" style="margin-top:0">Arriva a <b><?= newsletter_non_aperti((int)$rim['id']) ?></b> contatti che l'hanno ricevuta e non l'hanno aperta (contati adesso). Si fa una volta sola. Un oggetto diverso aiuta: chi non l'ha aperta forse non ha notato il primo.</p>
  <div class="form-group"><label for="nl-rim-oggetto">Oggetto del rinvio</label><input type="text" id="nl-rim-oggetto" name="oggetto" maxlength="200" value="<?= h(newsletter_4byte_fuori((string)$rim['oggetto'])) ?>"></div>
  <div class="nl-riga"><button class="btn btn-primary" type="submit">Prepara il rinvio</button> <a class="btn btn-secondary" href="newsletter.php">Lascia stare</a></div>
  <?php endif; ?>
</form>
<?php endif; ?>

<div class="form-card" style="margin-top:1.25rem">
  <h3>Campagne</h3>
  <nav class="nl-viste" aria-label="Viste delle campagne">
    <?php foreach (NL_CAMP_VISTE as $k => [$et]): if ($k !== 'tutte' && $k !== $vista && !$conti[$k]) continue; ?>
      <a href="?vista=<?= $k ?>"<?= $vista === $k ? ' class="attiva" aria-current="page"' : '' ?>><?= h($et) ?> <span class="nl-conto"><?= (int)$conti[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($campagne): ?><table class="table nl-elenco">
    <thead><tr><th>Campagna</th><th>Stato</th><th>A chi</th><th>Quando</th><th>Avanzamento</th><th>Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($campagne as $c): $pct = $c['destinatari'] ? round($c['inviate'] / $c['destinatari'] * 100) : 0; $cId = (int)$c['id'];
      $aChi = [];
      if ((int)$c['rinvio_di']) $aChi[] = 'Chi non ha aperto la campagna di partenza';
      else {
        foreach (newsletter_liste_campagna($c) as $l) $aChi[] = $nomeLista[$l] ?? '';
        foreach (nl_ids((string)$c['segmenti']) as $sg) if (isset($segNomi[$sg])) $aChi[] = 'segmento ' . newsletter_4byte_fuori($segNomi[$sg]);
        if ((int)$c['padre_id']) $aChi[] = 'solo i nuovi contatti';
      } ?>
    <tr>
      <td><a href="newsletter-campagna.php?id=<?= $cId ?>"><b><?= h($nomeC($c)) ?></b></a><?= $c['tipo'] === 'ab' ? '<span class="nl-tipo-ab">Test A/B</span>' : '' ?><?php if ($c['oggetto'] !== '' && $c['nome'] !== $c['oggetto']): ?><span class="nlx-sotto">Oggetto: <?= h(newsletter_4byte_fuori($c['oggetto'])) ?></span><?php endif; ?><?php if ($c['tag'] !== ''): ?><span class="nlx-sotto">Tag: <?= h(newsletter_4byte_fuori($c['tag'])) ?></span><?php endif; ?></td>
      <td><span class="nl-stato nl-stato-<?= h($c['stato']) ?>"><?= h($statoNome[$c['stato']] ?? $c['stato']) ?></span><?= (int)$c['archiviata'] ? ' <span class="nlx-sotto">archiviata</span>' : '' ?></td>
      <td><small><?= h(implode(', ', array_filter($aChi)) ?: 'nessuno ancora') ?></small></td>
      <td><?= $c['invio_at'] ? h(date('d/m/Y H:i', strtotime($c['invio_at']))) : '' ?></td>
      <td><?php if ($c['destinatari']): ?><div class="nl-barra" role="img" aria-label="<?= $pct ?> per cento partite"><div style="width:<?= $pct ?>%"></div></div><small><?= (int)$c['inviate'] ?> di <?= (int)$c['destinatari'] ?><?= $c['errori'] ? ', ' . (int)$c['errori'] . ' non partite' : '' ?></small><?php endif; ?></td>
      <td>
        <details class="nl-azioni"><summary class="btn btn-secondary btn-sm">Azioni<span class="sr-solo"> per «<?= h($nomeC($c)) ?>»</span></summary>
          <div class="nl-azioni-menu">
          <a class="btn btn-secondary btn-sm" href="newsletter-campagna.php?id=<?= $cId ?>"><?= newsletter_campagna_modificabile($c) ? 'Apri e modifica' : 'Apri' ?></a>
          <?php if (in_array($c['stato'], ['in_invio', 'sospesa', 'inviata', 'annullata'], true)): ?><a class="btn btn-secondary btn-sm" href="newsletter-report.php?id=<?= $cId ?>">Report</a><?php endif; ?>
          <a class="btn btn-secondary btn-sm" href="newsletter.php?duplica=<?= $cId ?>#nl-duplica">Duplica</a>
          <?php if (in_array($c['stato'], ['inviata', 'in_invio', 'sospesa'], true) && !(int)$c['rinvio_di']): ?><form method="post" data-conferma="Preparare una copia di questa campagna per i contatti che non l'hanno ricevuta?" data-conferma-ok="Prepara la copia"><?= csrf_field() ?><input type="hidden" name="azione" value="nuovi"><input type="hidden" name="id" value="<?= $cId ?>"><button class="btn btn-secondary btn-sm" type="submit">Invia ai nuovi contatti</button></form><?php endif; ?>
          <?php if ($c['stato'] === 'inviata' && !(int)$c['rinvio_di'] && !(int)$c['rinvio_fatto'] && (int)$c['lotti'] <= 1): ?><a class="btn btn-secondary btn-sm" href="newsletter.php?rimanda=<?= $cId ?>#nl-rimanda">Rimanda ai non aperti</a><?php endif; ?>
          <?php if (in_array($c['stato'], ['inviata', 'sospesa', 'annullata'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="<?= (int)$c['archiviata'] ? 'ripristina' : 'archivia' ?>"><input type="hidden" name="id" value="<?= $cId ?>"><input type="hidden" name="vista" value="<?= h($vista) ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= (int)$c['archiviata'] ? 'Togli dall\'archivio' : 'Archivia' ?></button></form><?php endif; ?>
          <?php if (in_array($c['stato'], ['programmata', 'in_invio', 'sospesa'], true)): ?><form method="post" data-conferma="Annullare la campagna? Le email non ancora partite non partiranno più, e non si può riprendere." data-conferma-ok="Annulla la campagna" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="annulla"><input type="hidden" name="id" value="<?= $cId ?>"><button class="btn btn-danger btn-sm" type="submit">Annulla la campagna</button></form><?php endif; ?>
          <?php if ($c['stato'] === 'bozza'): ?><form method="post" data-conferma="Eliminare questa bozza? Non si può tornare indietro." data-conferma-ok="Elimina la bozza" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $cId ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form><?php endif; ?>
          </div>
        </details>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table><?php endif; ?>
  <?php if (!$campagne): ?><div class="empty-state"><p><?= $vista === 'tutte' ? 'Ancora nessuna campagna: crea la prima con «Crea una campagna» qui sopra.' : 'Nessuna campagna in questa vista. Guarda in <a href="newsletter.php">Tutte</a>.' ?></p></div><?php endif; ?>
  <p style="font-size:.85rem;color:var(--text-muted);margin-top:1rem">
    Le email partono dal cron ogni 5 minuti, al massimo <?= (int)$cfg['per_ora'] ?> l'ora<?= (int)$cfg['per_giorno'] ? ' e ' . (int)$cfg['per_giorno'] . ' al giorno' : '' ?>, contando tutte le email del sito. Ultimo giro: <?= h($ultimoGiro ?: 'nessuno registrato') ?>.
  </p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="giro"><button class="btn btn-secondary btn-sm" type="submit">Fai partire un giro adesso</button></form>
</div>

<?php else: ?>
<div class="form-card">
  <h3>Attributi dei contatti</h3>
  <p class="hint" style="margin-top:0">I campi dei contatti (città, data di nascita, cliente sì o no...), da usare nelle email con i segnaposti come {{ contact.NOME }}.</p>
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
    <div class="form-group"><label for="coinvolti_giorni">Contatti non coinvolti: giorni senza aperture né clic</label><input type="number" id="coinvolti_giorni" name="coinvolti_giorni" min="30" max="365" value="<?= newsletter_non_coinvolti_giorni() ?>" aria-describedby="coinvolti-aiuto">
      <div class="hint" id="coinvolti-aiuto">Nelle campagne, «Non mandare ai contatti non coinvolti» salta chi in questi giorni ha ricevuto almeno <?= NL_NON_COINVOLTI_EMAIL ?> campagne senza aprirne né cliccarne nessuna. Da 30 a 365, di partenza 90.</div></div>
    <div class="form-group"><label for="rispondi_a">Le risposte alla newsletter arrivano a</label><input type="email" id="rispondi_a" name="rispondi_a" value="<?= h($cfg['rispondi_a']) ?>" placeholder="la tua email">
      <div class="hint">Vuoto: le risposte vanno all'indirizzo che spedisce (scheda Posta).</div></div>
    <button class="btn btn-primary" type="submit">Salva</button>
  </form>
  <p style="font-size:.85rem;color:var(--text-muted);margin-top:1rem">
    Cron da aggiungere in cPanel, ogni 5 minuti:<br><code>/usr/local/bin/php <?= h(cms_radice()) ?>/cron-newsletter.php</code>
  </p>
</div>
<form method="post" class="form-card" id="nl-indirizzo" style="margin-top:1.25rem">
  <?= csrf_field() ?><input type="hidden" name="azione" value="indirizzo">
  <h3>Indirizzo dell'azienda nel piede</h3>
  <p class="hint" style="margin-top:0">Per legge le email promozionali devono dire chi le manda: nome dell'azienda o della persona e indirizzo. Esce in fondo a ogni campagna, sotto il link per uscire.</p>
  <div class="form-group"><label for="nl-indirizzo-testo">Nome e indirizzo</label>
    <textarea id="nl-indirizzo-testo" name="indirizzo" rows="3" class="nlx-corto" maxlength="400" placeholder="per esempio: Studio Rossi, via Roma 1, 00100 Roma" aria-describedby="nl-indirizzo-aiuto"><?= h(newsletter_4byte_fuori(newsletter_indirizzo_azienda())) ?></textarea>
    <div class="hint" id="nl-indirizzo-aiuto">Due o tre righe al massimo. Vuoto: il piede resta senza indirizzo, e l'elenco delle campagne te lo ricorda.</div></div>
  <button class="btn btn-primary" type="submit">Salva l'indirizzo</button>
</form>
<?php $pgT = newsletter_pagine_testi(); $pgB = newsletter_pagine_partenza(); $pgS = json_decode((string)get_setting('newsletter_pagine', ''), true) ?: [];
  $pgV = fn($k) => h(newsletter_4byte_fuori((string)($pgS[$k] ?? '')));
  $pgListe = db()->query('SELECT id, nome, pubblica FROM ' . tbl('newsletter_liste') . ' ORDER BY nome')->fetchAll(); ?>
<form method="post" class="form-card" id="nl-pagine" style="margin-top:1.25rem">
  <?= csrf_field() ?><input type="hidden" name="azione" value="pagine">
  <h3>Pagine di disiscrizione e preferenze</h3>
  <p class="hint" style="margin-top:0">Le pagine che si aprono dai link in fondo alle email: «Esci dalla newsletter» e «Aggiorna le tue preferenze». Lascia vuoto un campo per usare il testo di partenza (lo vedi scritto in grigio). <code>{email}</code> diventa l'indirizzo di chi legge, <code>{newsletter}</code> il nome della newsletter.</p>
  <h4 class="nl-sotto-titolo">Disiscrizione</h4>
  <div class="form-group"><label for="pg-ut">Titolo della pagina</label><input type="text" id="pg-ut" name="pg[uscita_titolo]" maxlength="200" value="<?= $pgV('uscita_titolo') ?>" placeholder="<?= h($pgB['uscita_titolo']) ?>"></div>
  <div class="form-group"><label for="pg-ux">Testo sopra il pulsante «Sì, toglimi dall'elenco»</label><textarea id="pg-ux" name="pg[uscita_testo]" rows="2" class="nlx-corto" placeholder="<?= h($pgB['uscita_testo']) ?>"><?= $pgV('uscita_testo') ?></textarea></div>
  <div class="form-group"><label for="pg-ft">Titolo dopo l'uscita</label><input type="text" id="pg-ft" name="pg[fatto_titolo]" maxlength="200" value="<?= $pgV('fatto_titolo') ?>" placeholder="<?= h($pgB['fatto_titolo']) ?>"></div>
  <div class="form-group"><label for="pg-fx">Testo dopo l'uscita</label><textarea id="pg-fx" name="pg[fatto_testo]" rows="2" class="nlx-corto" placeholder="<?= h($pgB['fatto_testo']) ?>"><?= $pgV('fatto_testo') ?></textarea></div>
  <label class="nl-casella"><input type="checkbox" name="pg[questionario]" value="1"<?= $pgT['questionario'] ? ' checked' : '' ?>> Fai il questionario: chiedi perché esce</label>
  <div class="form-group"><label for="pg-qt">Domanda del questionario</label><input type="text" id="pg-qt" name="pg[questionario_titolo]" maxlength="200" value="<?= $pgV('questionario_titolo') ?>" placeholder="<?= h($pgB['questionario_titolo']) ?>"></div>
  <fieldset class="nlk-scelte"><legend>Le risposte possibili</legend>
    <?php foreach (NL_USCITA_MOTIVI as $k => $v): ?><div class="form-group" style="margin:.3rem 0"><label for="pg-m-<?= $k ?>" class="sr-solo">Risposta: <?= h($v) ?></label><input type="text" id="pg-m-<?= $k ?>" name="pg[motivi][<?= $k ?>]" maxlength="100" value="<?= h(newsletter_4byte_fuori((string)($pgS['motivi'][$k] ?? ''))) ?>" placeholder="<?= h($v) ?>"></div><?php endforeach; ?>
  </fieldset>
  <div class="form-group"><label for="pg-gx">Ringraziamento dopo la risposta</label><input type="text" id="pg-gx" name="pg[grazie_testo]" maxlength="300" value="<?= $pgV('grazie_testo') ?>" placeholder="<?= h($pgB['grazie_testo']) ?>"></div>
  <h4 class="nl-sotto-titolo">Centro preferenze</h4>
  <div class="form-group"><label for="pg-pt">Titolo della pagina</label><input type="text" id="pg-pt" name="pg[pref_titolo]" maxlength="200" value="<?= $pgV('pref_titolo') ?>" placeholder="<?= h($pgB['pref_titolo']) ?>"></div>
  <div class="form-group"><label for="pg-px">Testo in cima</label><textarea id="pg-px" name="pg[pref_testo]" rows="2" class="nlx-corto" placeholder="<?= h($pgB['pref_testo']) ?>"><?= $pgV('pref_testo') ?></textarea></div>
  <fieldset class="nlk-scelte"><legend>Le liste che il contatto vede e può scegliere</legend>
    <?php foreach ($pgListe as $l): ?><label><input type="checkbox" name="pubbliche[]" value="<?= (int)$l['id'] ?>"<?= (int)$l['pubblica'] ? ' checked' : '' ?>> <?= h(newsletter_4byte_fuori($l['nome'])) ?></label><?php endforeach; ?>
    <p class="hint">Le liste non spuntate restano nascoste: chi ci sta dentro continua a riceverle, e ne esce solo con «Esci da tutte le email».</p>
  </fieldset>
  <fieldset class="nlk-scelte"><legend>I dati che il contatto può cambiare</legend>
    <?php foreach (newsletter_attributi() as $an => $aa): if (!nl_attributo_preferibile($aa)) continue; ?><label><input type="checkbox" name="pg[attributi][]" value="<?= h($an) ?>"<?= in_array($an, $pgT['attributi'], true) ? ' checked' : '' ?>> <?= h(newsletter_4byte_fuori(newsletter_attributo_etichetta($aa))) ?></label><?php endforeach; ?>
    <p class="hint">L'email non si cambia da qui: chi ne vuole un'altra si iscrive di nuovo con quella, e la conferma.</p>
  </fieldset>
  <button class="btn btn-primary" type="submit">Salva le pagine</button>
</form>
<?php $nlTest = newsletter_lista_test(); $nlTestTesto = $_SESSION['nl_test_testo'] ?? implode("\n", array_column($nlTest, 'email')); unset($_SESSION['nl_test_testo']); ?>
<form method="post" class="form-card" id="nl-test" style="margin-top:1.25rem">
  <?= csrf_field() ?><input type="hidden" name="azione" value="lista_test">
  <h3>Lista dei test</h3>
  <p class="hint" style="margin-top:0">Gli indirizzi a cui mandare le prove delle campagne, prima di spedirle a tutti. La prova arriva a ognuno con i suoi dati (nome, attributi), come la riceverebbe davvero.</p>
  <div class="form-group"><label for="nl-test-indirizzi">Indirizzi della lista dei test</label>
    <textarea id="nl-test-indirizzi" name="indirizzi" rows="5" class="nlx-corto" placeholder="per esempio: tu@esempio.it" aria-describedby="nl-test-aiuto"><?= h($nlTestTesto) ?></textarea>
    <div class="hint" id="nl-test-aiuto">Uno per riga, al massimo <?= NL_TEST_MASSIMO ?>. Ognuno deve essere già un contatto: se non lo è, crealo prima in <a href="newsletter-contatto.php?nuovo=1">Contatti, Crea un contatto</a>.</div></div>
  <button class="btn btn-primary" type="submit">Salva la lista dei test</button>
</form>
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
