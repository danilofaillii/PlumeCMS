<?php
/**
 * Prenotazioni > Nuova prenotazione (2.1): l'attività scrive una prenotazione presa per telefono o di persona.
 * Prima si sceglie servizio, persona e giorno (modulo GET: cambiando una tendina la pagina si ricarica con gli orari
 * liberi), poi l'ora fra quelle libere oppure "Un altro orario" (fuori dagli orari di lavoro, con l'avviso), e il cliente:
 * nome, telefono, email facoltativa, nota. Le regole contro le doppie prenotazioni sono quelle del sito
 * (pren_prenota_pannello() in prenotazioni.php). Il POST vuole il token CSRF; un errore ripresenta il modulo con i campi.
 */
require_once __DIR__ . '/admin-comune.php';
$adminTitle = 'Nuova prenotazione';
$tz = new DateTimeZone(date_default_timezone_get());
$oggi = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
$servizi = pren_servizi(true);
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $post ? $_POST : $_GET;
$sid = (int)($in['servizio'] ?? $in['s'] ?? 0);
$s = null;
foreach ($servizi as $x) if ((int)$x['id'] === $sid) $s = $x;
if (!$s && $servizi) $s = $servizi[0];
$persone = $s ? pren_risorse_servizio($s) : [];
$nomi = array_column($persone, 'nome', 'id');
$rid = (int)($in['risorsa'] ?? $in['r'] ?? 0);
if (!isset($nomi[$rid])) $rid = 0;
$giorno = is_string($in['giorno'] ?? $in['d'] ?? null) && pren_istante((string)($in['giorno'] ?? $in['d']), '12:00') ? (string)($in['giorno'] ?? $in['d']) : $oggi;
$errore = '';
$campi = ['nome' => '', 'telefono' => '', 'email' => '', 'note' => '', 'ora' => '', 'ora_altra' => '', 'risorsa_altra' => (string)$rid, 'avvisa' => '1'];

if ($post) {
    verify_csrf();
    foreach ($campi as $k => $x) if ($k !== 'avvisa') $campi[$k] = pren_testo($_POST[$k] ?? '', $k === 'note' ? 1000 : 190, $k === 'note');
    $campi['avvisa'] = !empty($_POST['avvisa']) ? '1' : '';
    $altra = $campi['ora'] === 'altra';
    $email = mb_strtolower($campi['email']);
    if (!$s) $errore = 'Prima scrivi almeno un servizio in Servizi.';
    elseif ($campi['nome'] === '') $errore = 'Scrivi il nome del cliente.';
    elseif ($campi['telefono'] === '') $errore = 'Scrivi il telefono del cliente: serve per richiamarlo se cambia qualcosa.';
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errore = '«' . $campi['email'] . '» non è un indirizzo email valido. Correggilo, oppure lascia il campo vuoto.';
    elseif ($campi['ora'] === '') $errore = 'Scegli l\'ora: una di quelle libere, oppure «Un altro orario».';
    else {
        $r = pren_prenota_pannello(['servizio' => (int)$s['id'], 'risorsa' => $altra ? (int)$campi['risorsa_altra'] : $rid, 'giorno' => $giorno, 'ora' => $altra ? $campi['ora_altra'] : $campi['ora'],
            'nome' => mb_substr($campi['nome'], 0, 150), 'telefono' => mb_substr($campi['telefono'], 0, 40), 'email' => $email, 'note' => $campi['note']], $altra, $campi['avvisa'] === '1');
        if (is_array($r)) {
            $p = pren_leggi($r['id']);
            flash('success', 'Prenotazione salvata: ' . $p['nome'] . ', ' . pren_quando($p) . ($p['risorsa'] !== '' ? ' con ' . $p['risorsa'] : '') . '.'
                . ($r['fuori'] ? ' È fuori dagli orari liberi: l\'hai segnata tu, il sito non la offre ad altri.' : '')
                . ($r['email'] ? ' Al cliente è partita l\'email di conferma con il file per il calendario.' : ($email !== '' && $campi['avvisa'] === '1' ? ' L\'email di conferma non è partita: controlla Impostazioni > Configurazione email.' : ' Nessuna email al cliente.')));
            pren_admin_vai('elenco', ['id' => (int)$p['id']]);
        }
        $chi = $nomi[$altra ? (int)$campi['risorsa_altra'] : $rid] ?? '';
        $errore = [
            'servizio' => 'Scegli un servizio fra quelli attivi.',
            'persona' => $altra ? 'Per un orario fuori dai posti liberi scegli con chi è l\'appuntamento.' : 'Quella persona non fa questo servizio.',
            'quando' => 'Il giorno o l\'ora non vanno bene. Scrivi l\'ora così: 9:30. (Quel giorno quell\'ora potrebbe non esistere, per il cambio dell\'ora.)',
            'posto' => 'Quell\'orario non è più libero (qualcuno l\'ha appena preso, o è fuori dagli orari di lavoro): scegline un altro.',
            'occupato' => ($chi !== '' ? $chi : 'Quella persona') . ' a quell\'ora ha già un appuntamento: due appuntamenti che si sovrappongono non si possono segnare.',
        ][$r] ?? 'Non è stato possibile salvare la prenotazione.';
    }
}

$posti = $s ? pren_posti($s, $rid, $giorno, null, 0, true) : [];
// Senza posti in questo giorno: il primo giorno libero nelle quattro settimane dopo.
$prossimo = '';
if ($s && !$posti) {
    $g0 = DateTimeImmutable::createFromFormat('!Y-m-d', $giorno, $tz);
    for ($i = 1; $i <= 28 && $prossimo === ''; $i++) { $g = $g0->modify("+$i day")->format('Y-m-d'); if (pren_posti($s, $rid, $g, null, 0, true)) $prossimo = $g; }
}
$gTs = (new DateTimeImmutable($giorno . ' 12:00:00', $tz))->getTimestamp();
$giornoDi = fn(int $n) => (new DateTimeImmutable($giorno . ' 12:00:00', $tz))->modify(($n > 0 ? '+' : '') . $n . ' day')->format('Y-m-d');
$q = fn(array $x) => pren_admin_url('nuova', $x + ['s' => $s ? (int)$s['id'] : 0, 'r' => $rid, 'd' => $giorno]);
require CMS_ADMIN . '/includes/admin-header.php';
pren_admin_schede('nuova');
?>
<p class="pa-aiuto" style="font-size:.9rem;max-width:52rem;margin:0 0 1rem">Per una prenotazione presa al telefono o al banco. Scegli servizio, persona e giorno: sotto compaiono gli orari liberi, gli stessi che vede chi prenota dal sito.</p>
<?php if ($errore !== ''): ?><div class="flash flash-error" id="pa-nuova-errore" role="alert"><?= h($errore) ?></div><?php endif; ?>
<?php if (!$servizi): ?>
<div class="pa-modulo"><p>Per prenotare serve almeno un servizio attivo. Aggiungilo in <a href="<?= h(pren_admin_url('servizi', ['nuovo' => 1])) ?>">Servizi</a>.</p></div>
<?php else: ?>
<form method="get" class="pa-modulo" id="pa-nuova-quando" action="addon-pagina.php">
  <input type="hidden" name="a" value="prenotazioni"><input type="hidden" name="p" value="nuova">
  <h3>1. Cosa, con chi, quando</h3>
  <div class="pa-campi">
    <label>Servizio<select name="s" data-invia-al-cambio><?php foreach ($servizi as $x): ?><option value="<?= (int)$x['id'] ?>"<?= (int)$x['id'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($x['nome'] . ' (' . (int)$x['durata'] . ' min' . ($x['prezzo'] !== '' ? ', ' . $x['prezzo'] : '') . ')') ?></option><?php endforeach; ?></select></label>
    <label>Con chi<select name="r" data-invia-al-cambio><option value="0">Chi è libero</option><?php foreach ($persone as $x): ?><option value="<?= (int)$x['id'] ?>"<?= (int)$x['id'] === $rid ? ' selected' : '' ?>><?= h((string)$x['nome']) ?></option><?php endforeach; ?></select></label>
    <label>Giorno<input type="date" name="d" value="<?= h($giorno) ?>" required data-invia-al-cambio></label>
  </div>
  <p class="pa-azioni" style="margin:.8rem 0 0"><button class="btn btn-secondary btn-sm" type="submit">Mostra gli orari liberi</button>
    <a class="btn btn-secondary btn-sm" href="<?= h($q(['d' => $giornoDi(-1)])) ?>">&lsaquo; Giorno prima</a>
    <a class="btn btn-secondary btn-sm" href="<?= h($q(['d' => $giornoDi(1)])) ?>">Giorno dopo &rsaquo;</a></p>
</form>

<form method="post" class="pa-modulo" id="pa-nuova"><?= csrf_field() ?>
  <input type="hidden" name="servizio" value="<?= (int)$s['id'] ?>"><input type="hidden" name="risorsa" value="<?= $rid ?>"><input type="hidden" name="giorno" value="<?= h($giorno) ?>">
  <h3>2. L'ora, <?= h(pren_giorno_esteso($gTs)) ?></h3>
  <fieldset class="pa-ore">
    <legend class="pa-aiuto"><?= $posti ? 'Gli orari liberi per ' . h((string)$s['nome']) . ($rid ? ' con ' . h($nomi[$rid]) : '') . ':' : 'Nessun orario libero in questo giorno' . ($rid ? ' con ' . h($nomi[$rid]) : '') . '.' ?></legend>
    <div class="pa-ore-griglia">
    <?php foreach ($posti as $ora => $chi): ?>
      <label class="pa-ora-scelta"><input type="radio" name="ora" value="<?= h((string)$ora) ?>"<?= $campi['ora'] === (string)$ora ? ' checked' : '' ?>><span><b><?= h((string)$ora) ?></b><?php if (!$rid && count($persone) > 1): ?><small><?= h($nomi[$chi[0]] ?? '') ?></small><?php endif; ?></span></label>
    <?php endforeach; ?>
      <label class="pa-ora-scelta pa-ora-altra"><input type="radio" name="ora" value="altra"<?= $campi['ora'] === 'altra' ? ' checked' : '' ?>><span><b>Un altro orario</b><small>fuori dai posti liberi</small></span></label>
    </div>
    <?php if (!$posti && $prossimo !== ''): ?><p class="pa-aiuto" id="pa-prossimo">Il primo giorno libero: <a href="<?= h($q(['d' => $prossimo])) ?>"><?= h(pren_giorno_esteso((new DateTimeImmutable($prossimo . ' 12:00:00', $tz))->getTimestamp())) ?></a>.</p><?php endif; ?>
  </fieldset>
  <div class="pa-forza" id="pa-forza">
    <p class="pa-avviso" style="margin:.8rem 0"><b>Attenzione:</b> un orario fuori dai posti liberi (prima dell'apertura, in un giorno chiuso, durante le ferie) lo segni sotto la tua responsabilità. Il sito controlla solo che la persona non abbia già un altro appuntamento a quell'ora.</p>
    <div class="pa-campi">
      <label>Ora<input name="ora_altra" value="<?= h($campi['ora_altra']) ?>" placeholder="8:15" pattern="\d{1,2}:\d{2}" inputmode="numeric" maxlength="5"><span class="pa-aiuto">Ore e minuti, per esempio 8:15.</span></label>
      <label>Con chi<select name="risorsa_altra"><option value="0">Scegli</option><?php foreach ($persone as $x): ?><option value="<?= (int)$x['id'] ?>"<?= (int)$x['id'] === (int)$campi['risorsa_altra'] ? ' selected' : '' ?>><?= h((string)$x['nome']) ?></option><?php endforeach; ?></select></label>
    </div>
  </div>
  <h3 style="margin-top:1.4rem">3. Il cliente</h3>
  <div class="pa-campi">
    <label>Nome e cognome<input name="nome" required maxlength="150" autocomplete="off" value="<?= h($campi['nome']) ?>"></label>
    <label>Telefono<input name="telefono" type="tel" required maxlength="40" autocomplete="off" value="<?= h($campi['telefono']) ?>"></label>
    <label>Email (facoltativa)<input name="email" type="email" maxlength="190" autocomplete="off" value="<?= h($campi['email']) ?>"><span class="pa-aiuto">Con l'email il cliente riceve la conferma, il promemoria e il link per annullare o spostare da solo.</span></label>
    <label class="pa-largo">Nota (facoltativa, la vedi solo tu)<textarea name="note" rows="2" maxlength="1000"><?= h($campi['note']) ?></textarea></label>
    <label class="pa-spunta pa-largo"><input type="checkbox" name="avvisa" value="1"<?= $campi['avvisa'] === '1' ? ' checked' : '' ?>> Manda al cliente l'email di conferma (solo se hai scritto l'email)</label>
  </div>
  <p class="pa-azioni" style="margin-top:1rem"><button class="btn btn-primary" type="submit">Salva la prenotazione</button> <a class="btn btn-secondary" href="<?= h(pren_admin_url('agenda', ['vista' => 'giorno', 'd' => $giorno])) ?>">Annulla</a></p>
</form>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
