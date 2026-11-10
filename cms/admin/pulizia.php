<?php
/**
 * Impostazioni > Pulizia (2.38.0): cosa si può togliere senza rompere il sito, con il peso di ogni voce (file e database), e la
 * cancellazione totale o parziale. Motore in includes/pulizia.php (manuale tecnico, capitolo 50).
 * Solo per l'amministratore. Tre passi: la pagina con le voci (GET), il riepilogo (POST azione=riepilogo), l'esecuzione (POST
 * azione=esegui, con la password dell'amministratore per le voci pesanti). Ogni POST con il token CSRF; niente si cancella in GET.
 * Il controllo delle immagini non usate è in GET (?media=1): legge e basta.
 */
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/pulizia.php';
require_admin();

$adminTitle = 'Pulizia';
$io = current_user();
$utente = (string)($_SESSION['admin_username'] ?? '');
$def = pulizia_definizioni();
$ids = pulizia_ids();
$vista = 'elenco';
$errori = [];      // id della voce (o '_') => messaggio
$errPw = ''; $errZero = '';
$scelte = [];      // id => parametri controllati
$piani = [];
$media = null;
$postati = [];     // i valori scritti, per rimetterli nei campi se c'è un errore

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    if (in_array($azione, ['riepilogo', 'esegui'], true)) {
        $voci = $_POST['voci'] ?? [];
        $voci = is_array($voci) ? array_values(array_unique(array_filter($voci, fn($v) => is_string($v) && in_array($v, $ids, true)))) : [];
        $pIn = is_array($_POST['p'] ?? null) ? $_POST['p'] : [];
        foreach ($ids as $id) if (is_array($pIn[$id] ?? null)) $postati[$id] = $pIn[$id];
        if (!$voci) $errori['_'] = 'Spunta almeno una voce con "Metti nel riepilogo".';
        foreach ($voci as $id) {
            [$p, $err] = pulizia_parametri($id, is_array($pIn[$id] ?? null) ? $pIn[$id] : []);
            if ($err !== '') $errori[$id] = $err;
            $scelte[$id] = $p;
        }
        if (!$errori) {
            if (isset($scelte['media'])) $media = pulizia_media_controlla();
            pulizia_scadenza(true);
            foreach ($scelte as $id => $p) $piani[$id] = pulizia_piano($id, $p, $id === 'media' ? $media : null);
            $daFare = array_filter($piani, fn($pi) => $pi['n'] > 0 && $pi['bloccata'] === '');
            $pesante = (bool)array_filter(array_keys($daFare), fn($id) => !empty($def[$id]['pesante']));
            $serveZero = isset($daFare['snapshot']) && (int)$scelte['snapshot']['n'] === 0;
            if (!$daFare) {
                $errori['_'] = 'Con le scelte fatte non c\'è niente da togliere.';
            } elseif ($azione === 'esegui') {
                $ok = true;
                if ($serveZero && empty($scelte['snapshot']['zero'])) { $errZero = 'Per togliere anche l\'ultimo snapshot spunta la conferma.'; $ok = false; }
                if ($pesante && !sicurezza_password_verifica($io, (string)($_POST['password_admin'] ?? ''))) {
                    $errPw = 'La password non è giusta: niente è stato tolto.';
                    sicurezza_log('pulizia', $utente, (int)($io['id'] ?? 0), 'pulizia rifiutata: password dell\'amministratore sbagliata');
                    $ok = false;
                }
                if ($ok) {
                    $res = pulizia_esegui(array_intersect_key($scelte, $daFare));
                    $righe = [];
                    foreach ($res['voci'] as $id => $r) $righe[] = $id . ': ' . $r['messaggio'] . ($r['byte'] ? ' (' . pulizia_peso((int)$r['byte']) . ')' : '');
                    sicurezza_log('pulizia', $utente, (int)($io['id'] ?? 0), 'pulizia fatta, liberati ' . pulizia_peso((int)$res['byte']) . '. ' . mb_substr(implode('; ', $righe), 0, 1500));
                    $_SESSION['pulizia_resoconto'] = $res + ['quando' => time()];
                    header('Location: pulizia.php#resoconto'); exit;
                }
            }
            if (!isset($errori['_'])) $vista = 'riepilogo';
        }
    }
} elseif (isset($_GET['media'])) {
    $media = pulizia_media_controlla();
}

$resoconto = $_SESSION['pulizia_resoconto'] ?? null;
unset($_SESSION['pulizia_resoconto']);

if ($vista === 'elenco') {
    $piani = pulizia_voci($media);
    $totale = pulizia_totale($piani);
    pulizia_stima_salva($totale['byte']);
    $pesiDb = pulizia_pesi_tabelle();
    $dbTot = 0; foreach ($pesiDb as $x) $dbTot += $x['dati'] + $x['indici'];
}

/** Il valore da rimettere in un campo numerico: quello scritto (se c'è) o quello di partenza. */
function pul_valore(array $postati, string $id, string $campo, int $partenza): string {
    $v = $postati[$id][$campo] ?? null;
    return is_string($v) ? $v : (string)$partenza;
}

/** Una data breve, o un trattino. */
function pul_data(int $t): string { return $t > 0 ? date('d/m/Y H:i', $t) : '-'; }

/** I campi nascosti che riportano le scelte nel passo dopo. */
function pul_nascosti(array $scelte): string {
    $h = '';
    foreach ($scelte as $id => $p) {
        $h .= '<input type="hidden" name="voci[]" value="' . h($id) . '">';
        if (isset($p['n'])) $h .= '<input type="hidden" name="p[' . h($id) . '][n]" value="' . (int)$p['n'] . '">';
        if (isset($p['giorni'])) $h .= '<input type="hidden" name="p[' . h($id) . '][giorni]" value="' . (int)$p['giorni'] . '">';
        foreach ((array)($p['scelta'] ?? []) as $s) $h .= '<input type="hidden" name="p[' . h($id) . '][scelta][]" value="' . h((string)$s) . '">';
    }
    return $h;
}

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.pul-largo{max-width:900px}
.pul-totale{display:flex;flex-wrap:wrap;gap:1rem 2rem;align-items:baseline;margin:.2rem 0 .6rem}
.pul-totale b{font-size:1.9rem;line-height:1.1;color:var(--text)}
.pul-totale span{color:var(--text-muted);font-size:.9rem}
.pul-gruppo{margin:1.8rem 0 .6rem;font-size:1.15rem}
.pul-peso{float:right;font-weight:600;font-size:.95rem;color:var(--testata-testo)}
.pul-perdi{font-size:.88rem;margin:.3rem 0 .7rem;line-height:1.5}
.pul-riga{display:flex;flex-wrap:wrap;gap:.6rem 1rem;align-items:center;margin:.6rem 0}
.pul-riga .form-group{margin:0}
.pul-riga input[type=number]{width:7rem}
.pul-spunta{display:flex;gap:.5rem;align-items:flex-start;font-size:.95rem;margin:.8rem 0 0;font-weight:600}
.pul-spunta input{margin-top:.25rem;flex:0 0 auto;width:1.15rem;height:1.15rem}
.pul-elenco{width:100%;border-collapse:collapse;font-size:.88rem;margin:.4rem 0}
.pul-elenco th,.pul-elenco td{text-align:left;padding:.35rem .5rem;border-bottom:1px solid var(--border);vertical-align:middle;overflow-wrap:anywhere}
.pul-elenco td.pul-num,.pul-elenco th.pul-num{text-align:right;white-space:nowrap}
.pul-va{font-weight:600}
.pul-resta{color:var(--text-muted)}
.pul-mini{width:56px;height:56px;object-fit:cover;border:1px solid var(--border);border-radius:4px;background:var(--fondo-2)}
.pul-blocco{border-left:4px solid var(--attenzione-bordo);padding:.6rem .8rem;background:var(--attenzione-chiaro);color:var(--attenzione-testo);border-radius:4px;font-size:.9rem}
.pul-fondo{position:sticky;bottom:0;z-index:5;box-shadow:0 -4px 14px rgba(0,0,0,.08)}
.pul-azioni{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-top:.8rem}
.pul-riepilogo{margin:.4rem 0 1rem 1.1rem;line-height:1.6}
.pul-riepilogo li{margin:.2rem 0}
.pul-esito-ok{border-left:4px solid var(--successo-bordo)}
.pul-nota{font-size:.85rem;color:var(--text-muted);line-height:1.5;margin:.4rem 0 0}
</style>
<div class="pul-largo">

<?php if ($resoconto): ?>
<section class="form-card pul-esito-ok" id="resoconto">
  <h3>Pulizia fatta: liberati <?= h(pulizia_peso((int)$resoconto['byte'])) ?></h3>
  <ul class="pul-riepilogo" role="status">
    <?php foreach ($resoconto['voci'] as $r): ?>
      <li><b><?= h($r['titolo']) ?></b>: <?= h($r['messaggio']) ?><?= $r['byte'] ? ' Liberati ' . h(pulizia_peso((int)$r['byte'])) . '.' : '' ?>
        <?php foreach ((array)$r['errori'] as $e): ?><br><span class="errore-campo" role="alert"><?= h($e) ?></span><?php endforeach; ?></li>
    <?php endforeach; ?>
  </ul>
  <p class="pul-nota">Il sito e il pannello funzionano come prima. L'operazione è scritta nel registro di Impostazioni &gt; Sicurezza.</p>
</section>
<?php endif; ?>

<?php if ($vista === 'riepilogo'):
  $totRiep = 0; $serveZero = false; $pesante = false;
  foreach ($piani as $id => $pi) { if ($pi['bloccata'] === '' && $pi['n'] > 0) { $totRiep += $pi['byte']; if (!empty($def[$id]['pesante'])) $pesante = true; if ($id === 'snapshot' && (int)$scelte['snapshot']['n'] === 0) $serveZero = true; } }
?>
<form method="post" class="form-card" id="riepilogo">
  <h3>Riepilogo: controlla prima di togliere</h3>
  <?= csrf_field() ?><input type="hidden" name="azione" value="esegui">
  <?= pul_nascosti($scelte) ?>
  <p>Stai per togliere:</p>
  <ul class="pul-riepilogo">
    <?php foreach ($piani as $id => $pi): ?>
      <li><b><?= h($def[$id]['titolo']) ?></b>:
        <?php if ($pi['bloccata'] !== ''): ?>niente, <?= h($pi['bloccata']) ?>
        <?php elseif (!$pi['n']): ?>niente da togliere con queste scelte.
        <?php elseif ($id === 'ottimizza'): ?>riscrive <?= pulizia_num($pi['n']) ?> <?= $pi['n'] === 1 ? 'tabella' : 'tabelle' ?> (circa <?= h(pulizia_peso($pi['byte'])) ?> di spazio vuoto dentro).
        <?php else: ?><?= pulizia_num($pi['n']) ?> <?= $pi['n'] === 1 ? 'elemento' : 'elementi' ?><?= $pi['righe'] && in_array($id, ['versioni', 'registri'], true) ? ' (' . pulizia_num($pi['righe']) . ' righe)' : '' ?>, <?= $pi['stima'] ? 'circa ' : '' ?><?= h(pulizia_peso($pi['byte'])) ?><?= $pi['finito'] ? '' : ' o più' ?>.
          <span class="pul-nota">Si perde: <?= h($def[$id]['perdi']) ?></span>
          <?php if (in_array($id, ['copie', 'media', 'pacchetti'], true)): ?><br><span class="pul-nota">Scelti: <?= h(implode(', ', array_map(fn($e) => $e['nome'], array_slice(pulizia_proposti($pi), 0, 12)))) ?><?= $pi['n'] > 12 ? ' e altri ' . ($pi['n'] - 12) : '' ?>.</span><?php endif; ?>
        <?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
  <p><b>Per un totale di <?= h(pulizia_peso($totRiep)) ?></b><?= isset($piani['ottimizza']) ? ' (più quello che restituisce Ottimizza le tabelle)' : '' ?>. Quello che togli non si può rimettere.</p>
  <?php if ($serveZero): ?>
    <label class="pul-spunta"><input type="checkbox" name="p[snapshot][zero]" value="1"<?= $errZero ? ' aria-invalid="true" aria-describedby="pul-zero-errore"' : '' ?>> <span>Sì, togli anche l'ultimo snapshot: dopo non si può tornare indietro da nessun aggiornamento.</span></label>
    <?php if ($errZero): ?><p class="errore-campo" id="pul-zero-errore" role="alert"><?= h($errZero) ?></p><?php endif; ?>
  <?php endif; ?>
  <?php if ($pesante): ?>
    <div class="form-group" style="margin-top:1rem">
      <label for="pul-password">La tua password</label>
      <input type="password" id="pul-password" name="password_admin" required autocomplete="current-password" aria-describedby="pul-password-aiuto<?= $errPw ? ' pul-password-errore' : '' ?>"<?= $errPw ? ' aria-invalid="true"' : '' ?>>
      <?php if ($errPw): ?><p class="errore-campo" id="pul-password-errore" role="alert"><?= h($errPw) ?></p><?php endif; ?>
      <p class="hint" id="pul-password-aiuto">La password con cui entri nel pannello: serve per togliere snapshot, copie, immagini e righe del database.</p>
    </div>
  <?php endif; ?>
  <div class="pul-azioni">
    <button class="btn btn-pericolo" type="submit">Togli adesso</button>
    <a class="btn btn-secondary" href="pulizia.php">Torna alle voci</a>
  </div>
</form>

<?php else: ?>

<section class="form-card" id="totale">
  <h3>Quanto si può liberare</h3>
  <div class="pul-totale">
    <div><b><?= h(pulizia_peso($totale['byte'])) ?></b><?= $totale['finito'] ? '' : ' <span>o più</span>' ?><br><span>con le proposte di partenza qui sotto</span></div>
    <div><b><?= h(pulizia_peso($dbTot)) ?></b><br><span>pesa il database del sito (<?= pulizia_num(count($pesiDb)) ?> tabelle)</span></div>
  </div>
  <?php if (!$totale['finito']): ?><p class="pul-blocco" role="status">Alcune cartelle sono molto grandi: il conto si è fermato dopo qualche secondo per non bloccare la pagina, quindi i pesi segnati "o più" sono il minimo.</p><?php endif; ?>
  <p class="pul-nota">Niente si toglie da solo: scegli le voci, spunta "Metti nel riepilogo" e premi <b>Prepara il riepilogo</b> in fondo. Prima di togliere vedi sempre il riepilogo. Le immagini non usate non sono nel totale: si controllano a parte.</p>
  <details class="pn-aiuto" style="margin-top:.8rem"><summary>Il peso del database, tabella per tabella</summary>
    <table class="pul-elenco">
      <thead><tr><th>Tabella</th><th class="pul-num">Righe (circa)</th><th class="pul-num">Dati</th><th class="pul-num">Indici</th><th class="pul-num">Totale</th></tr></thead>
      <tbody>
      <?php foreach ($pesiDb as $t => $x): ?>
        <tr><td><?= h($t) ?></td><td class="pul-num"><?= pulizia_num($x['righe']) ?></td><td class="pul-num"><?= h(pulizia_peso($x['dati'])) ?></td><td class="pul-num"><?= h(pulizia_peso($x['indici'])) ?></td><td class="pul-num" data-tabella-peso="<?= h($t) ?>" data-byte="<?= (int)($x['dati'] + $x['indici']) ?>"><?= h(pulizia_peso($x['dati'] + $x['indici'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </details>
</section>

<?php if (isset($errori['_']) || count($errori) > 0): ?>
<p class="errore-campo" role="alert" id="pul-errori"><?= h($errori['_'] ?? (count($errori) === 1 ? '1 voce da sistemare: la trovi segnata in rosso qui sotto.' : count($errori) . ' voci da sistemare: le trovi segnate in rosso qui sotto.')) ?></p>
<?php endif; ?>

<form method="post" id="pul-modulo">
  <?= csrf_field() ?><input type="hidden" name="azione" value="riepilogo">
<?php
$gruppi = ['file' => 'File del sito', 'db' => 'Database', 'media' => 'Immagini'];
foreach ($gruppi as $g => $titoloG):
?>
  <h2 class="pul-gruppo"><?= h($titoloG) ?></h2>
<?php foreach ($ids as $id): if ($def[$id]['gruppo'] !== $g) continue; $d = $def[$id]; $pi = $piani[$id]; $err = $errori[$id] ?? ''; $attivo = $pi['bloccata'] === '' && $pi['elementi'] && ($pi['n'] > 0 || $d['modo'] !== 'tutto'); ?>
  <section class="form-card pul-voce" id="voce-<?= h($id) ?>" data-voce="<?= h($id) ?>" data-byte="<?= (int)$pi['byte'] ?>" data-n="<?= (int)$pi['n'] ?>">
    <h3><?= h($d['titolo']) ?> <span class="pul-peso"><?= $pi['bloccata'] !== '' ? 'bloccata' : ($id === 'ottimizza' ? 'circa ' . h(pulizia_peso($pi['byte'])) : ($pi['stima'] && $pi['byte'] ? 'circa ' : '') . h(pulizia_peso($pi['byte'])) . ($pi['finito'] ? '' : ' o più')) ?></span></h3>
    <p class="hint" style="margin-top:0"><?= h($d['spiega']) ?></p>
    <p class="pul-perdi"><b>Cosa perdi:</b> <?= h($d['perdi']) ?></p>
    <?php if ($pi['bloccata'] !== ''): ?>
      <p class="pul-blocco" role="status"><?= h($pi['bloccata']) ?></p>
    <?php elseif (!empty($pi['da_controllare'])): ?>
      <p class="pul-nota"><?= h($pi['riassunto']) ?></p>
      <div class="pul-azioni"><a class="btn btn-secondary" href="pulizia.php?media=1#voce-media">Controlla le immagini</a></div>
    <?php elseif (!$pi['elementi']): ?>
      <p class="empty-state" style="padding:.6rem 0;margin:0;text-align:left"><?= h($pi['riassunto'] !== '' ? $pi['riassunto'] : 'Niente da togliere.') ?></p>
    <?php else: ?>
      <?php if ($pi['avviso'] !== ''): ?><p class="pul-blocco"><?= h($pi['avviso']) ?></p><?php endif; ?>
      <p>Proposta: <b><?= pulizia_num($pi['n']) ?> <?= $pi['n'] === 1 ? 'elemento' : 'elementi' ?></b> su <?= pulizia_num($pi['tot_n']) ?>, <?= $pi['stima'] && $pi['byte'] ? 'circa ' : '' ?><?= h(pulizia_peso($pi['byte'])) ?><?= $pi['finito'] ? '' : ' o più' ?><?= $pi['righe'] && in_array($id, ['versioni', 'registri', 'lavoro'], true) ? ' (' . pulizia_num($pi['righe']) . ' righe)' : '' ?>.</p>
      <?php if ($d['modo'] === 'ultime' || $d['modo'] === 'giorni'): $campo = $d['modo'] === 'ultime' ? 'n' : 'giorni'; $cid = 'pul-' . $id . '-' . $campo; ?>
        <div class="pul-riga form-group">
          <label for="<?= $cid ?>"><?= $d['modo'] === 'ultime' ? ($id === 'snapshot' ? 'Tieni gli ultimi' : 'Tieni le ultime') : ($id === 'log' ? 'Togli i log fermi da più di (giorni)' : 'Più vecchi di (giorni)') ?></label>
          <input type="number" id="<?= $cid ?>" name="p[<?= h($id) ?>][<?= $campo ?>]" min="<?= (int)$d['min'] ?>" max="<?= PULIZIA_N_MAX ?>" step="1" inputmode="numeric" value="<?= h(pul_valore($postati, $id, $campo, (int)$d[$campo])) ?>"<?= $err ? ' aria-invalid="true" aria-describedby="' . $cid . '-errore"' : '' ?>>
        </div>
        <?php if ($err): ?><p class="errore-campo" id="<?= $cid ?>-errore" role="alert"><?= h($err) ?></p><?php endif; ?>
        <p class="pul-nota">La proposta qui sopra è calcolata con <?= (int)$d[$campo] ?>; se cambi il numero, il riepilogo ricalcola.</p>
      <?php endif; ?>
      <?php if ($d['modo'] === 'scelta'): ?>
        <table class="pul-elenco">
          <thead><tr><th>Togli</th><?php if ($id === 'media'): ?><th>Anteprima</th><?php endif; ?><th>Nome</th><th class="pul-num">Peso</th><th>Data</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($pi['elementi'], 0, PULIZIA_MEDIA_MAX) as $i => $e): $sid = 'pul-' . $id . '-' . $i; $spunta = isset($postati[$id]['scelta']) ? in_array($e['chiave'], (array)$postati[$id]['scelta'], true) : $e['proposto']; ?>
            <tr>
              <td><input type="checkbox" id="<?= $sid ?>" name="p[<?= h($id) ?>][scelta][]" value="<?= h($e['chiave']) ?>"<?= $spunta ? ' checked' : '' ?> aria-label="Togli <?= h($e['nome']) ?>"></td>
              <?php if ($id === 'media'): ?><td><img class="pul-mini" src="<?= h((string)($e['url'] ?? '')) ?>" alt="" loading="lazy"></td><?php endif; ?>
              <td><label for="<?= $sid ?>"><?= h($e['nome']) ?></label><?= $e['nota'] !== '' ? ' <span class="pul-resta">(' . h($e['nota']) . ')</span>' : '' ?></td>
              <td class="pul-num"><?= h(pulizia_peso((int)$e['byte'])) ?></td>
              <td><?= h(pul_data((int)$e['data'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($pi['elementi']) > PULIZIA_MEDIA_MAX): ?><p class="pul-nota">Ne mostro <?= PULIZIA_MEDIA_MAX ?> su <?= pulizia_num(count($pi['elementi'])) ?>, dalle più pesanti: dopo la pulizia controlla di nuovo per vedere le altre.</p><?php endif; ?>
        <?php if ($id === 'media'): ?><p class="pul-nota">Controllo fatto in <?= h(str_replace('.', ',', (string)$media['secondi'])) ?> secondi: <?= pulizia_num((int)$media['usati']) ?> immagini usate, <?= pulizia_num(count($media['forse'])) ?> forse usate, <?= pulizia_num(count($media['liberi'])) ?> non usate. Di partenza nessuna è spuntata: spunta tu quelle che vuoi togliere, dopo averle guardate.</p><?php endif; ?>
      <?php else: ?>
        <details class="pn-aiuto"><summary>Vedi l'elenco (<?= pulizia_num($pi['tot_n']) ?>)</summary>
          <table class="pul-elenco">
            <thead><tr><th>Nome</th><th class="pul-num">Peso</th><th>Data</th><th>Con la proposta</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($pi['elementi'], 0, 200) as $e): ?>
              <tr><td><?= h($e['nome']) ?></td><td class="pul-num"><?= h(pulizia_peso((int)$e['byte'])) ?></td><td><?= h(pul_data((int)$e['data'])) ?></td><td class="<?= $e['proposto'] ? 'pul-va' : 'pul-resta' ?>"><?= $e['proposto'] ? 'si toglie' : 'resta' ?><?= $e['nota'] !== '' && !in_array($e['nota'], ['resta'], true) ? ' (' . h($e['nota']) . ')' : '' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </details>
      <?php endif; ?>
      <?php if ($id === 'media' && $media && $media['forse']): ?>
        <details class="pn-aiuto"><summary>Forse usate: non proposte (<?= pulizia_num(count($media['forse'])) ?>)</summary>
          <table class="pul-elenco"><thead><tr><th>Nome</th><th class="pul-num">Peso</th><th>Perché</th></tr></thead><tbody>
          <?php foreach (array_slice($media['forse'], 0, 200) as $f): ?><tr><td><?= h($f['nome']) ?></td><td class="pul-num"><?= h(pulizia_peso((int)$f['byte'])) ?></td><td><?= h($f['motivo']) ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </details>
      <?php endif; ?>
      <?php if ($attivo): ?>
        <label class="pul-spunta"><input type="checkbox" name="voci[]" value="<?= h($id) ?>"<?= isset($scelte[$id]) ? ' checked' : '' ?>> <span>Metti nel riepilogo</span></label>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; endforeach; ?>

  <div class="form-card pul-fondo">
    <h3>Prepara il riepilogo</h3>
    <p class="pul-nota" style="margin-top:0">Vedrai cosa si toglie e quanto pesa prima di confermare. Per snapshot, copie, immagini e database serve la tua password.</p>
    <div class="pul-azioni"><button class="btn btn-primary" type="submit">Prepara il riepilogo</button></div>
  </div>
</form>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
