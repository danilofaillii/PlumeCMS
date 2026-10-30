<?php
/**
 * Prenotazioni > Agenda: la settimana (sette colonne, sul telefono una sotto l'altra) o il giorno, con le prenotazioni.
 * ?vista=settimana|giorno&d=AAAA-MM-GG. Un clic su una prenotazione apre la sua scheda in Prenotazioni.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Agenda';
$tz = new DateTimeZone(date_default_timezone_get());
$vista = ($_GET['vista'] ?? '') === 'giorno' ? 'giorno' : 'settimana';
$d = is_string($_GET['d'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['d']) ? (DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['d'], $tz) ?: new DateTimeImmutable('today', $tz)) : new DateTimeImmutable('today', $tz);
$inizio = $vista === 'settimana' ? $d->modify('-' . ((int)$d->format('N') - 1) . ' day') : $d;
$giorni = $vista === 'settimana' ? 7 : 1;
$fine = $inizio->modify("+$giorni day");
$st = db()->prepare('SELECT * FROM ' . tbl('pren_prenotazioni') . " WHERE inizio_ts >= ? AND inizio_ts < ? AND stato <> 'annullata' ORDER BY inizio_ts, risorsa");
$st->execute([$inizio->getTimestamp(), $fine->getTimestamp()]);
$perGiorno = [];
foreach ($st->fetchAll() as $p) $perGiorno[(new DateTimeImmutable('@' . $p['inizio_ts']))->setTimezone($tz)->format('Y-m-d')][] = $p;
$daConfermare = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('pren_prenotazioni') . " WHERE stato = 'da_confermare' AND inizio_ts > " . time())->fetchColumn();
$passo = $vista === 'settimana' ? 7 : 1;
$oggi = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
$titolo = $vista === 'settimana' ? 'Settimana dal ' . pren_giorno_esteso($inizio->getTimestamp(), false) . ' al ' . pren_giorno_esteso($fine->modify('-1 day')->getTimestamp()) : ucfirst(pren_giorno_esteso($inizio->getTimestamp()));
require CMS_ADMIN . '/includes/admin-header.php';
pren_admin_schede('agenda');
?>
<?php if ($daConfermare): ?><div class="pa-avviso"><a href="<?= h(pren_admin_url('elenco', ['filtro' => 'da_confermare'])) ?>"><b><?= $daConfermare ?> <?= $daConfermare === 1 ? 'prenotazione aspetta' : 'prenotazioni aspettano' ?> la tua conferma</b></a></div><?php endif; ?>
<div class="pa-barra">
  <h2><?= h($titolo) ?></h2>
  <a class="btn btn-secondary btn-sm" href="<?= h(pren_admin_url('agenda', ['vista' => $vista, 'd' => $inizio->modify("-$passo day")->format('Y-m-d')])) ?>">&lsaquo; Prima</a>
  <a class="btn btn-secondary btn-sm" href="<?= h(pren_admin_url('agenda', ['vista' => $vista])) ?>">Oggi</a>
  <a class="btn btn-secondary btn-sm" href="<?= h(pren_admin_url('agenda', ['vista' => $vista, 'd' => $inizio->modify("+$passo day")->format('Y-m-d')])) ?>">Dopo &rsaquo;</a>
  <a class="btn btn-<?= $vista === 'giorno' ? 'primary' : 'secondary' ?> btn-sm" href="<?= h(pren_admin_url('agenda', ['vista' => 'giorno', 'd' => $vista === 'giorno' ? $inizio->format('Y-m-d') : $oggi])) ?>">Giorno</a>
  <a class="btn btn-<?= $vista === 'settimana' ? 'primary' : 'secondary' ?> btn-sm" href="<?= h(pren_admin_url('agenda', ['vista' => 'settimana', 'd' => $inizio->format('Y-m-d')])) ?>">Settimana</a>
</div>
<?php if ($vista === 'settimana'): ?>
<div class="pa-settimana">
  <?php for ($i = 0; $i < 7; $i++): $g = $inizio->modify("+$i day"); $k = $g->format('Y-m-d'); ?>
  <section class="pa-giorno<?= $k === $oggi ? ' oggi' : '' ?>" data-giorno="<?= h($k) ?>">
    <h3><a href="<?= h(pren_admin_url('agenda', ['vista' => 'giorno', 'd' => $k])) ?>"><?= h(pren_giorno_esteso($g->getTimestamp(), false)) ?></a></h3>
    <?php foreach ($perGiorno[$k] ?? [] as $p): ?>
      <a class="pa-voce <?= h($p['stato']) ?>" href="<?= h(pren_admin_url('elenco', ['id' => $p['id']])) ?>"><b><?= h(pren_ora((int)$p['inizio_ts'])) ?> <?= h((string)$p['nome']) ?></b><?= h((string)$p['servizio']) ?><?= $p['risorsa'] !== '' ? ' &middot; ' . h((string)$p['risorsa']) : '' ?><?= $p['stato'] === 'da_confermare' ? ' &middot; da confermare' : '' ?></a>
    <?php endforeach; ?>
    <?php if (empty($perGiorno[$k])): ?><p class="pa-vuoto">Nessuna prenotazione.</p><?php endif; ?>
  </section>
  <?php endfor; ?>
</div>
<?php else: ?>
<div class="pa-giornata">
  <?php foreach ($perGiorno[$inizio->format('Y-m-d')] ?? [] as $p): ?>
  <div class="pa-riga">
    <span class="pa-ora"><?= h(pren_ora((int)$p['inizio_ts'])) ?>&ndash;<?= h(pren_ora((int)$p['fine_ts'])) ?></span>
    <span><b><?= h((string)$p['nome']) ?></b> &middot; <?= h((string)$p['servizio']) ?><?= $p['risorsa'] !== '' ? ' con ' . h((string)$p['risorsa']) : '' ?><br><span class="pa-muto"><?= h((string)$p['telefono']) ?> <?= h((string)$p['email']) ?><?= $p['note'] !== '' ? ' &middot; ' . h((string)$p['note']) : '' ?></span></span>
    <span class="pa-azioni"><?= pren_stato_badge((string)$p['stato']) ?> <a class="btn btn-secondary btn-sm" href="<?= h(pren_admin_url('elenco', ['id' => $p['id']])) ?>">Apri</a></span>
  </div>
  <?php endforeach; ?>
  <?php if (empty($perGiorno[$inizio->format('Y-m-d')])): ?><p class="pa-vuoto">Nessuna prenotazione in questo giorno.</p><?php endif; ?>
</div>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
