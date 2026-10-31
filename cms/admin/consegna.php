<?php
/**
 * Consegna delle email (2.1.0): perché le email arrivino nella posta in arrivo e non nello spam.
 * Schede: Da dove cominciare, Dominio (SPF, DKIM, DMARC, MX), Come partono (SMTP, Brevo, Amazon SES, Mailgun),
 * Mittenti, Rimbalzi e spam (gli indirizzi dei webhook), Riscaldamento. Il motore è includes/consegna.php.
 * Solo amministratore: le chiavi dei servizi non tornano mai nella pagina (solo "salvata il ...").
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/newsletter.php';
require_admin();
consegna_schema();
newsletter_schema();
newsletter_flussi_schema();

$adminTitle = 'Consegna delle email';
$schede = ['inizio' => 'Da dove cominciare', 'dominio' => 'Dominio', 'servizio' => 'Come partono', 'mittenti' => 'Mittenti', 'avvisi' => 'Rimbalzi e spam', 'riscaldamento' => 'Riscaldamento'];
$scheda = isset($schede[$_GET['scheda'] ?? '']) ? (string)$_GET['scheda'] : 'inizio';
$testo = fn(string $k, int $max = 300) => mb_substr(trim(is_string($_POST[$k] ?? null) ? $_POST[$k] : ''), 0, $max);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = $testo('azione', 40);
    $torna = $scheda;
    $c = consegna_config();
    switch ($azione) {
        case 'servizio':
            $nuovo = $testo('servizio', 10);
            $c['servizio'] = isset(CONSEGNA_SERVIZI[$nuovo]) ? $nuovo : 'smtp';
            $errori = [];
            // Brevo: la chiave API comincia con xkeysib-; quella SMTP (xsmtpsib-) va in Configurazione email.
            $k = $testo('brevo_chiave', 200);
            if ($k !== '') {
                if (str_starts_with($k, 'xsmtpsib-')) $errori[] = 'Questa è la chiave SMTP di Brevo (comincia con "xsmtpsib-"): qui serve la chiave API, che comincia con "xkeysib-". La chiave SMTP va in Configurazione email.';
                elseif (!preg_match('/^[A-Za-z0-9_-]{20,200}$/', $k)) $errori[] = 'La chiave API di Brevo non sembra intera: copiala di nuovo (comincia con "xkeysib-").';
                else { $c['brevo']['chiave'] = consegna_cifra($k); $c['brevo']['salvata'] = date('Y-m-d H:i'); }
            }
            $reg = $testo('ses_regione', 30);
            if ($reg !== '') {
                if (preg_match('/^[a-z]{2}(-[a-z]+)+-\d$/', $reg)) $c['ses']['regione'] = $reg; else $errori[] = 'La regione di Amazon SES non è valida: si scrive come "eu-west-1" o "eu-south-1".';
            }
            $id = strtoupper($testo('ses_id', 40));
            if ($id !== '') {
                if (preg_match('/^(AKIA|ASIA)[A-Z0-9]{16}$/', $id)) $c['ses']['id_chiave'] = $id; else $errori[] = 'L\'ID della chiave di accesso di Amazon non è valido: sono 20 caratteri e comincia con "AKIA".';
            }
            $seg = $testo('ses_segreto', 100);
            if ($seg !== '') {
                if (preg_match('~^[A-Za-z0-9/+=]{30,80}$~', $seg)) { $c['ses']['segreto'] = consegna_cifra($seg); $c['ses']['salvata'] = date('Y-m-d H:i'); }
                else $errori[] = 'La chiave segreta di Amazon non sembra intera: sono 40 caratteri, copiala di nuovo.';
            }
            $set = $testo('ses_set', 64);
            $c['ses']['set'] = preg_match('/^[A-Za-z0-9_-]{0,64}$/', $set) ? $set : $c['ses']['set'];
            $dom = strtolower($testo('mailgun_dominio', 190));
            if ($dom !== '') {
                if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $dom)) $c['mailgun']['dominio'] = $dom; else $errori[] = 'Il dominio di Mailgun non è valido (per esempio mg.tuosito.it).';
            }
            $c['mailgun']['regione'] = $testo('mailgun_regione', 4) === 'us' ? 'us' : 'eu';
            $mk = $testo('mailgun_chiave', 200);
            if ($mk !== '') {
                if (preg_match('/^[A-Za-z0-9_-]{20,200}$/', $mk)) { $c['mailgun']['chiave'] = consegna_cifra($mk); $c['mailgun']['salvata'] = date('Y-m-d H:i'); }
                else $errori[] = 'La chiave API di Mailgun non sembra intera: copiala di nuovo.';
            }
            consegna_config_salva($c);
            $ceAttivo = consegna_servizio_attivo();
            if ($errori) flash('error', implode(' ', $errori));
            elseif ($c['servizio'] !== 'smtp' && $ceAttivo === '') flash('error', 'Salvato, ma mancano dei dati per ' . CONSEGNA_SERVIZI[$c['servizio']] . ': finché non li scrivi le email partono come prima (Configurazione email).');
            else flash('success', $ceAttivo !== '' ? 'Salvato: le email partono da ' . CONSEGNA_SERVIZI[$ceAttivo] . '. Manda una prova qui sotto.' : 'Salvato: le email partono dal server di Configurazione email.');
            $torna = 'servizio';
            break;
        case 'togli_chiavi':
            $quale = $testo('quale', 10);
            if ($quale === 'brevo') $c['brevo'] = ['chiave' => '', 'salvata' => ''];
            if ($quale === 'ses') $c['ses']['segreto'] = $c['ses']['salvata'] = '';
            if ($quale === 'mailgun') $c['mailgun']['chiave'] = $c['mailgun']['salvata'] = '';
            if ($c['servizio'] === $quale) $c['servizio'] = 'smtp';
            consegna_config_salva($c);
            flash('success', 'Chiavi tolte. Le email partono dal server di Configurazione email.');
            $torna = 'servizio';
            break;
        case 'prova':
            $a = $testo('prova_a', 190);
            if (!filter_var($a, FILTER_VALIDATE_EMAIL)) { flash('error', 'Scrivi un indirizzo valido per la prova.'); $torna = 'servizio'; break; }
            $come = consegna_servizio_attivo();
            $html = moduli_email_html('Prova di consegna',
                '<p>Se leggi questa email, il sito spedisce correttamente' . ($come !== '' ? ' con <b>' . h(CONSEGNA_SERVIZI[$come]) . '</b>' : '') . '.</p>'
                . '<p>In allegato c&#039;è un piccolo file di testo: se lo vedi, anche gli allegati funzionano.</p><p>Inviata il ' . date('d/m/Y \a\l\l\e H:i') . '.</p>');
            $allegato = ['nome' => 'prova-plumecms.txt', 'tipo' => 'text/plain; charset=UTF-8', 'dati' => "Allegato di prova di plumeCMS.\nInviato il " . date('d/m/Y H:i') . ".\n"];
            if (posta_invia($a, 'Prova di consegna dal pannello', $html, moduli_intestazioni(), 'prova', [$allegato])) flash('success', "Prova spedita a $a" . ($come !== '' ? ' con ' . CONSEGNA_SERVIZI[$come] : '') . '. Controlla anche la cartella Spam.');
            else flash('error', 'La prova non è partita: ' . posta_errore());
            $torna = 'servizio';
            break;
        case 'mittente_aggiungi':
            $e = consegna_mittente_aggiungi($testo('email', 190), $testo('nome', 100), $testo('rispondi_a', 190));
            $e === '' ? flash('success', 'Mittente aggiunto. Ti abbiamo mandato un\'email a quell\'indirizzo: apri il link e premi "Confermo il mittente". Poi torna qui.') : flash('error', $e);
            $torna = 'mittenti';
            break;
        case 'mittente_conferma':
            $e = consegna_mittente_manda_conferma((int)($_POST['id'] ?? 0));
            $e === '' ? flash('success', 'Conferma rimandata: controlla la posta di quell\'indirizzo (anche lo Spam).') : flash('error', $e);
            $torna = 'mittenti';
            break;
        case 'mittente_predefinito':
            consegna_mittente_predefinisci((int)($_POST['id'] ?? 0)) ? flash('success', 'Mittente predefinito cambiato.') : flash('error', 'Solo un mittente confermato può essere il predefinito.');
            $torna = 'mittenti';
            break;
        case 'mittente_togli':
            consegna_mittente_togli((int)($_POST['id'] ?? 0));
            flash('success', 'Mittente tolto. Le campagne e i flussi che lo usavano partono dal predefinito.');
            $torna = 'mittenti';
            break;
        case 'mailgun_firma':
            $f = $testo('mailgun_firma', 200);
            if ($f !== '' && preg_match('/^[A-Za-z0-9_-]{16,200}$/', $f)) { $c['mailgun']['firma'] = consegna_cifra($f); consegna_config_salva($c); flash('success', 'Chiave di firma di Mailgun salvata.'); }
            else flash('error', 'La chiave di firma di Mailgun non sembra intera: copiala di nuovo.');
            $torna = 'avvisi';
            break;
        case 'cambia_indirizzi':
            save_setting('posta_avvisi_sale', bin2hex(random_bytes(16)));
            flash('success', 'Indirizzi cambiati: quelli vecchi non funzionano più. Incolla i nuovi nei servizi.');
            $torna = 'avvisi';
            break;
        case 'riscaldamento':
            $r = consegna_riscaldamento();
            $acceso = !empty($_POST['acceso']);
            if ($acceso && (!$r['acceso'] || !empty($_POST['ricomincia']) || $r['inizio'] === '')) $r['inizio'] = date('Y-m-d');
            $r['acceso'] = $acceso ? 1 : 0;
            $r['partenza'] = max(10, min(5000, (int)($_POST['partenza'] ?? 50)));
            $r['arrivo'] = max(0, min(100000, (int)($_POST['arrivo'] ?? 0)));
            $r['fattore'] = in_array((string)($_POST['fattore'] ?? ''), ['1.3', '1.5', '2'], true) ? (float)$_POST['fattore'] : 1.5;
            save_setting('posta_riscaldamento', json_encode($r));
            $s = consegna_riscaldamento_stato();
            flash('success', $acceso ? 'Riscaldamento acceso: oggi al massimo ' . $s['oggi'] . ' email, poi sale da solo fino a ' . $s['arrivo'] . '.' : 'Riscaldamento spento: valgono solo i limiti di Newsletter, Impostazioni.');
            $torna = 'riscaldamento';
            break;
    }
    header('Location: consegna.php?scheda=' . $torna);
    exit;
}

$cfg = consegna_config();
$ceAttivo = consegna_servizio_attivo();
$scelto = consegna_servizio_scelto();
$mittenti = consegna_mittenti();
$predefinito = consegna_mittente_predefinito();
[$mittOra, $nomeOra] = posta_mittente();
$caldo = consegna_riscaldamento_stato();
$smtp = posta_config();
$ultimo = json_decode((string)get_setting('consegna_ultimo_controllo', ''), true) ?: [];

// Il controllo del dominio si fa sulla scheda Dominio, e si ricorda (per Da dove cominciare).
$controllo = null;
if ($scheda === 'dominio') {
    $domini = consegna_domini();
    $dominio = strtolower(trim((string)($_GET['dominio'] ?? ($domini[0] ?? ''))));
    $selettore = trim((string)($_GET['selettore'] ?? ''));
    if ($dominio !== '') {
        $controllo = consegna_controlla_dominio($dominio, $selettore);
        if ($controllo['valido'] && $controllo['dns']) save_setting('consegna_ultimo_controllo', json_encode(['dominio' => $controllo['dominio'], 'da_fare' => $controllo['da_fare'], 'quando' => date('Y-m-d H:i')]));
    }
}
$stati = ['ok' => ['A posto', 'ce-ok'], 'attenzione' => ['Da sistemare', 'ce-att'], 'manca' => ['Manca', 'ce-no'], 'sconosciuto' => ['Non so', 'ce-att']];
$quando = fn(string $d) => $d !== '' ? date('d/m/Y \a\l\l\e H:i', strtotime($d)) : '';

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.ce-schede{display:flex;flex-wrap:wrap;gap:.35rem;margin:0 0 1rem}
.ce-schede a{padding:.5rem .9rem;border:1px solid var(--border);border-radius:8px;text-decoration:none;color:var(--text-muted);font-weight:600;font-size:.9rem}
.ce-schede a.attiva{background:var(--surface);color:var(--text);border-color:var(--coral)}
.ce-intro{font-size:.9rem;color:var(--text-muted);margin:0 0 1rem;max-width:760px}
.ce-passi{list-style:none;margin:0;padding:0;display:grid;gap:.75rem}
.ce-passi li{display:flex;gap:.9rem;align-items:flex-start;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:.9rem 1rem}
.ce-num{flex:0 0 2rem;height:2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;background:var(--fondo-2)}
.ce-passi li > div{min-width:0;flex:1}
.ce-passi li > div > b{display:block;margin-bottom:.15rem}
.ce-passi p{margin:.15rem 0 .4rem;font-size:.88rem;color:var(--text-muted)}
.ce-stato{display:inline-block;font-size:.75rem;font-weight:700;padding:.15rem .55rem;border-radius:999px;margin-left:.35rem;vertical-align:middle}
.ce-ok{background:var(--sage-light);color:var(--successo-testo)}
.ce-att{background:var(--mustard-light);color:var(--attenzione-testo)}
.ce-no{background:var(--errore-chiaro);color:var(--errore-testo)}
.ce-voce{border:1px solid var(--border);border-radius:10px;padding:1rem;margin:0 0 .9rem;background:var(--surface)}
.ce-voce h4{margin:0 0 .35rem;font-size:1rem}
.ce-voce p{margin:.2rem 0 .5rem;font-size:.9rem}
.ce-record{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.35rem .75rem;align-items:center;background:var(--fondo-2);border-radius:8px;padding:.7rem;margin:.5rem 0}
.ce-record span{font-size:.8rem;color:var(--text-muted)}
.ce-record input,.ce-record textarea,.ce-copia{width:100%;min-width:0;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85rem;padding:.4rem .55rem;border:1px solid var(--border);border-radius:6px;background:var(--campo);color:var(--text);cursor:copy}
.ce-record textarea{resize:vertical;overflow-wrap:anywhere;line-height:1.4}
.ce-copia{min-height:13rem;line-height:1.45}
.ce-trovato{font-size:.8rem;color:var(--text-muted);overflow-wrap:anywhere}
.ce-trovato code{white-space:normal;overflow-wrap:anywhere}
.ce-dove{font-size:.82rem;color:var(--text-muted)}
.ce-riga{display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end}
.ce-riga .form-group{margin:0;flex:1 1 200px;min-width:0}
.ce-servizio[hidden]{display:none}
.ce-servizio{border:1px solid var(--border);border-radius:10px;padding:1rem;margin:0 0 1rem;min-width:0}
.ce-servizio legend{font-weight:700;padding:0 .4rem}
.ce-servizio ol,.ce-passo ol{margin:.3rem 0 .8rem 1.1rem;padding:0;font-size:.88rem}
.ce-servizio li,.ce-passo li{margin:.15rem 0}
.ce-tabella{width:100%;border-collapse:collapse;font-size:.88rem}
.ce-tabella th,.ce-tabella td{text-align:left;padding:.45rem .5rem;border-bottom:1px solid var(--border);vertical-align:top;overflow-wrap:anywhere}
.ce-azioni{display:flex;flex-wrap:wrap;gap:.35rem}
.ce-azioni form{margin:0}
.ce-giorni{display:flex;flex-wrap:wrap;gap:.35rem;margin:.5rem 0 0;padding:0;list-style:none}
.ce-giorni li{background:var(--fondo-2);border-radius:8px;padding:.35rem .6rem;font-size:.82rem}
.ce-giorni li.oggi{outline:2px solid var(--coral)}
@media (max-width:640px){.ce-tabella thead{display:none}.ce-tabella tr{display:block;border-bottom:1px solid var(--border);padding:.4rem 0}.ce-tabella td{display:block;border:0;padding:.15rem 0}.ce-record{grid-template-columns:minmax(0,1fr)}}
</style>

<nav class="ce-schede" aria-label="Schede della consegna">
  <?php foreach ($schede as $k => $v): ?><a href="?scheda=<?= $k ?>" class="<?= $scheda === $k ? 'attiva' : '' ?>"<?= $scheda === $k ? ' aria-current="page"' : '' ?>><?= h($v) ?></a><?php endforeach; ?>
</nav>

<?php if ($scheda === 'inizio'):
  $mittBuono = $mittOra !== '';
  $domOk = $ultimo && (int)$ultimo['da_fare'] === 0;
  $avvisiOk = $scelto === 'smtp' ? null : consegna_ultimo_avviso($scelto) !== ''; ?>
<p class="ce-intro">Le email del sito (newsletter, conferme, avvisi dei form) arrivano nella posta in arrivo solo se il tuo dominio dice chiaramente chi può spedire per lui. Qui lo controlli e lo sistemi, un passo alla volta. Non serve essere tecnici: dove c'è da chiedere qualcosa all'hosting, il pannello ti dà il testo da copiare.</p>
<ol class="ce-passi">
  <li><span class="ce-num">1</span><div><b>Il mittente <span class="ce-stato <?= $mittBuono ? 'ce-ok' : 'ce-no' ?>"><?= $mittBuono ? 'Fatto' : 'Da fare' ?></span></b>
    <p><?= $mittBuono ? 'Le email partono da <b>' . h($nomeOra) . ' &lt;' . h($mittOra) . '&gt;</b>.' : 'Manca l\'indirizzo che spedisce.' ?> Usa un indirizzo del tuo dominio (per esempio info@tuosito.it), mai uno @gmail.com o @libero.it.</p>
    <a href="?scheda=mittenti">Vai ai mittenti</a></div></li>
  <li><span class="ce-num">2</span><div><b>Il dominio <span class="ce-stato <?= $domOk ? 'ce-ok' : ($ultimo ? 'ce-att' : 'ce-no') ?>"><?= $domOk ? 'A posto' : ($ultimo ? (int)$ultimo['da_fare'] . ' da sistemare' : 'Da controllare') ?></span></b>
    <p>SPF, DKIM e DMARC sono tre righe nel DNS del dominio: dicono a Gmail e Outlook che le email sono davvero tue. <?= $ultimo ? 'Ultimo controllo di ' . h((string)$ultimo['dominio']) . ': ' . h($quando((string)$ultimo['quando'])) . '.' : '' ?></p>
    <a href="?scheda=dominio">Controlla il dominio</a></div></li>
  <li><span class="ce-num">3</span><div><b>Come partono <span class="ce-stato ce-ok"><?= h($ceAttivo !== '' ? CONSEGNA_SERVIZI[$ceAttivo] : ($smtp ? 'Server SMTP' : 'Server del sito')) ?></span></b>
    <p>Per poche centinaia di email al giorno basta il server di posta dell'hosting. Per di più, o se le email finiscono spesso nello spam, conviene un servizio come Brevo, Amazon SES o Mailgun.</p>
    <a href="?scheda=servizio">Scegli come partono</a></div></li>
  <li><span class="ce-num">4</span><div><b>Rimbalzi e spam <span class="ce-stato <?= $avvisiOk === null ? 'ce-ok' : ($avvisiOk ? 'ce-ok' : 'ce-att') ?>"><?= $avvisiOk === null ? 'Non serve' : ($avvisiOk ? 'Collegato' : 'Da collegare') ?></span></b>
    <p><?= $avvisiOk === null ? 'Con il server SMTP il pannello vede i rimbalzi da solo, al momento dell\'invio.' : 'Il servizio deve dire al sito chi rimbalza e chi segnala spam: così quegli indirizzi escono subito dall\'elenco.' ?></p>
    <a href="?scheda=avvisi">Vedi gli indirizzi da collegare</a></div></li>
  <li><span class="ce-num">5</span><div><b>Riscaldamento <span class="ce-stato <?= $caldo['acceso'] ? 'ce-ok' : 'ce-att' ?>"><?= $caldo['acceso'] ? 'Acceso, giorno ' . (int)$caldo['giorno'] : 'Spento' ?></span></b>
    <p>Un indirizzo nuovo che manda subito migliaia di email sembra uno spammer. Il riscaldamento parte con poche email al giorno e sale da solo.</p>
    <a href="?scheda=riscaldamento">Imposta il riscaldamento</a></div></li>
</ol>

<?php elseif ($scheda === 'dominio'): ?>
<p class="ce-intro">Il pannello legge il DNS del dominio da cui partono le email e ti dice cosa va e cosa manca. Dove serve una modifica trovi il record esatto: lo incolli tu nel DNS, oppure copi il messaggio in fondo e lo mandi all'assistenza dell'hosting.</p>
<form method="get" class="form-card ce-riga" style="margin-bottom:1rem">
  <input type="hidden" name="scheda" value="dominio">
  <div class="form-group"><label for="ce-dominio">Dominio del mittente</label>
    <input type="text" id="ce-dominio" name="dominio" value="<?= h($dominio) ?>" list="ce-domini" placeholder="tuosito.it" autocomplete="off">
    <datalist id="ce-domini"><?php foreach ($domini as $d): ?><option value="<?= h($d) ?>"><?php endforeach; ?></datalist></div>
  <div class="form-group"><label for="ce-selettore">Selettore DKIM (facoltativo)</label>
    <input type="text" id="ce-selettore" name="selettore" value="<?= h($selettore) ?>" placeholder="se il servizio te ne ha dato uno">
  </div>
  <button class="btn btn-primary" type="submit">Controlla</button>
</form>
<?php if ($controllo && !$controllo['valido']): ?>
  <p class="flash flash-error">"<?= h($dominio) ?>" non è un dominio. Scrivi solo la parte dopo la chiocciola, per esempio tuosito.it.</p>
<?php elseif ($controllo && !$controllo['dns']): ?>
  <p class="flash flash-info" id="ce-dns-muto">Il DNS non risponde: da questo server non si riesce a leggere il dominio <?= h($controllo['dominio']) ?>. Può essere un blocco del server o un dominio che non esiste. Riprova fra qualche minuto; se resta così, chiedi all'hosting se il server può fare richieste DNS.</p>
<?php elseif ($controllo): ?>
  <?php if (!empty($controllo['gratuito'])): ?><p class="flash flash-error" id="ce-gratuito"><b><?= h($controllo['dominio']) ?></b> è un indirizzo di posta gratuita: non puoi cambiarne il DNS e Gmail, Yahoo e Outlook rifiutano o mettono nello spam le newsletter che partono a suo nome da altri server. Usa un indirizzo del tuo dominio.</p><?php endif; ?>
  <p class="ce-intro"><?= $controllo['da_fare'] ? '<b>' . (int)$controllo['da_fare'] . '</b> ' . ($controllo['da_fare'] === 1 ? 'cosa da sistemare' : 'cose da sistemare') . ' per ' . h($controllo['dominio']) . '.' : 'Tutto a posto per <b>' . h($controllo['dominio']) . '</b>.' ?>
    Servizio considerato: <?= h(CONSEGNA_SERVIZI[$controllo['servizio']] ?? $controllo['servizio']) ?>. Dopo una modifica al DNS possono servire fino a 24 ore prima che si veda qui.</p>
  <?php foreach ($controllo['voci'] as $k => $v): [$etichetta, $classe] = $stati[$v['stato']] ?? $stati['sconosciuto']; ?>
  <section class="ce-voce" id="ce-<?= h($k) ?>">
    <h4><?= h($v['titolo']) ?> <span class="ce-stato <?= $classe ?>"><?= $etichetta ?></span></h4>
    <p><?= h($v['frase']) ?></p>
    <?php if ($v['record']): ?>
    <div class="ce-record">
      <span>Tipo</span><input type="text" readonly value="<?= h($v['record']['tipo']) ?>" data-copia-se-stesso aria-label="Tipo del record">
      <span>Nome</span><input type="text" readonly value="<?= h($v['record']['nome']) ?>" data-copia-se-stesso aria-label="Nome del record">
      <span>Valore</span><textarea readonly rows="<?= strlen($v['record']['valore']) > 60 ? 3 : 1 ?>" data-copia-se-stesso aria-label="Valore del record" class="ce-valore"><?= h($v['record']['valore']) ?></textarea>
    </div>
    <p class="ce-dove">Tocca una casella per copiarla. <?= h($v['dove']) ?></p>
    <?php elseif ($v['stato'] !== 'ok'): ?><p class="ce-dove"><?= h($v['dove']) ?></p><?php endif; ?>
    <?php if ($v['trovato']): ?><details class="ce-trovato"><summary>Cosa c'è adesso nel DNS</summary><?php foreach ($v['trovato'] as $t): ?><div><code><?= h((string)$t) ?></code></div><?php endforeach; ?></details><?php endif; ?>
  </section>
  <?php endforeach; ?>
  <?php if ($controllo['messaggio'] !== ''): ?>
  <div class="form-card">
    <h3>Il messaggio per l'hosting</h3>
    <p class="ce-intro">Se il DNS lo gestisce l'hosting, copia questo testo e mandalo alla sua assistenza. Tocca la casella per copiarlo.</p>
    <textarea class="ce-copia" readonly data-copia-se-stesso id="ce-messaggio" aria-label="Messaggio per l'hosting"><?= h($controllo['messaggio']) ?></textarea>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php elseif ($scheda === 'servizio'): ?>
<p class="ce-intro">Scegli da dove partono le email. Il server dell'hosting va bene per pochi invii; un servizio di spedizione regge numeri alti, ha una reputazione già costruita e dice al sito chi rimbalza e chi segnala spam.</p>
<form method="post" class="form-card" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="azione" value="servizio">
  <div class="form-group"><label for="ce-servizio">Come partono le email</label>
    <select id="ce-servizio" name="servizio">
      <?php foreach (CONSEGNA_SERVIZI as $k => $v): ?><option value="<?= $k ?>" <?= $cfg['servizio'] === $k ? 'selected' : '' ?>><?= h($k === 'smtp' ? $v : $v . ' (con le API)') ?></option><?php endforeach; ?>
    </select>
    <div class="hint">Adesso partono da: <b id="ce-adesso"><?= h($ceAttivo !== '' ? CONSEGNA_SERVIZI[$ceAttivo] . ' (API)' : ($smtp ? 'il server SMTP ' . $smtp['host'] : 'il server del sito, con mail() di PHP')) ?></b>.</div></div>

  <fieldset class="ce-servizio" data-servizio="smtp">
    <legend>Server di posta (SMTP)</legend>
    <p class="ce-dove">Si usa il server scritto in <a href="impostazioni.php">Configurazione email</a>. Anche Brevo si può usare così, senza le API:</p>
    <ol>
      <li>in Brevo apri il menu in alto a destra, <b>SMTP e API</b>, scheda <b>SMTP</b>, e premi <b>Genera una nuova chiave SMTP</b>;</li>
      <li>in <a href="impostazioni.php">Configurazione email</a> scrivi: server <code>smtp-relay.brevo.com</code>, porta <code>587</code>, sicurezza <b>STARTTLS</b>, utente il <b>Login</b> che Brevo mostra in quella pagina, password la chiave SMTP;</li>
      <li>come indirizzo del mittente usa un indirizzo che hai verificato in Brevo (Mittenti, domini e IP dedicati).</li>
    </ol>
  </fieldset>

  <fieldset class="ce-servizio" data-servizio="brevo">
    <legend>Brevo</legend>
    <ol>
      <li>Entra in Brevo, menu in alto a destra, <b>SMTP e API</b>, scheda <b>Chiavi API</b>, <b>Genera una nuova chiave API</b>.</li>
      <li>Copia la chiave (comincia con <code>xkeysib-</code>) e incollala qui sotto. Brevo la mostra una volta sola.</li>
      <li>In Brevo, <b>Mittenti, domini e IP dedicati</b>: aggiungi e verifica il mittente e autentica il dominio.</li>
    </ol>
    <div class="form-group"><label for="ce-brevo">Chiave API di Brevo</label>
      <input type="password" id="ce-brevo" name="brevo_chiave" value="" autocomplete="new-password" placeholder="<?= $cfg['brevo']['chiave'] !== '' ? 'salvata: lascia vuoto per tenerla' : 'xkeysib-...' ?>">
      <div class="hint"><?= $cfg['brevo']['chiave'] !== '' ? 'Chiave salvata il ' . h($quando((string)$cfg['brevo']['salvata'])) . ', cifrata. Per sicurezza non si mostra più: per cambiarla incolla quella nuova.' : 'Nessuna chiave salvata.' ?></div></div>
  </fieldset>

  <fieldset class="ce-servizio" data-servizio="ses">
    <legend>Amazon SES</legend>
    <ol>
      <li>In Amazon SES, <b>Identità verificate</b>: crea l'identità del tuo dominio e copia nel DNS i tre record DKIM. Finché l'account è in "sandbox", chiedi ad Amazon l'accesso alla produzione.</li>
      <li>In <b>IAM</b>, <b>Utenti</b>: crea un utente con il solo permesso <code>ses:SendEmail</code> (o <code>ses:SendRawEmail</code>), poi <b>Credenziali di sicurezza</b>, <b>Crea chiave di accesso</b>.</li>
      <li>Incolla qui sotto la regione, l'ID della chiave e la chiave segreta.</li>
    </ol>
    <div class="ce-riga">
      <div class="form-group"><label for="ce-ses-regione">Regione</label><input type="text" id="ce-ses-regione" name="ses_regione" value="<?= h((string)$cfg['ses']['regione']) ?>" placeholder="eu-west-1" list="ce-regioni">
        <datalist id="ce-regioni"><?php foreach (['eu-west-1', 'eu-south-1', 'eu-central-1', 'eu-west-2', 'eu-west-3', 'eu-north-1', 'us-east-1', 'us-west-2'] as $r): ?><option value="<?= $r ?>"><?php endforeach; ?></datalist></div>
      <div class="form-group"><label for="ce-ses-id">ID della chiave di accesso</label><input type="text" id="ce-ses-id" name="ses_id" value="<?= h((string)$cfg['ses']['id_chiave']) ?>" placeholder="AKIA..."></div>
    </div>
    <div class="form-group"><label for="ce-ses-segreto">Chiave segreta</label>
      <input type="password" id="ce-ses-segreto" name="ses_segreto" value="" autocomplete="new-password" placeholder="<?= $cfg['ses']['segreto'] !== '' ? 'salvata: lascia vuoto per tenerla' : '40 caratteri' ?>">
      <div class="hint"><?= $cfg['ses']['segreto'] !== '' ? 'Salvata il ' . h($quando((string)$cfg['ses']['salvata'])) . ', cifrata. Non si mostra più.' : 'Nessuna chiave salvata.' ?></div></div>
    <div class="form-group"><label for="ce-ses-set">Set di configurazione (facoltativo)</label><input type="text" id="ce-ses-set" name="ses_set" value="<?= h((string)$cfg['ses']['set']) ?>" placeholder="per esempio plumecms">
      <div class="hint">Serve se vuoi ricevere rimbalzi e spam con un "set di configurazione" invece che dall'identità.</div></div>
  </fieldset>

  <fieldset class="ce-servizio" data-servizio="mailgun">
    <legend>Mailgun</legend>
    <ol>
      <li>In Mailgun, <b>Send</b>, <b>Domains</b>: aggiungi un dominio di invio (per esempio <code>mg.tuosito.it</code>) e copia nel DNS i record che ti dà.</li>
      <li>Menu in alto a destra, <b>API Security</b> (o <b>API Keys</b>): crea una chiave e incollala qui sotto.</li>
      <li>Scegli la regione in cui hai aperto l'account: Europa (EU) o Stati Uniti (US).</li>
    </ol>
    <div class="ce-riga">
      <div class="form-group"><label for="ce-mg-dominio">Dominio di invio</label><input type="text" id="ce-mg-dominio" name="mailgun_dominio" value="<?= h((string)$cfg['mailgun']['dominio']) ?>" placeholder="mg.tuosito.it"></div>
      <div class="form-group"><label for="ce-mg-regione">Regione</label><select id="ce-mg-regione" name="mailgun_regione"><option value="eu" <?= $cfg['mailgun']['regione'] !== 'us' ? 'selected' : '' ?>>Europa (EU)</option><option value="us" <?= $cfg['mailgun']['regione'] === 'us' ? 'selected' : '' ?>>Stati Uniti (US)</option></select></div>
    </div>
    <div class="form-group"><label for="ce-mg-chiave">Chiave API di Mailgun</label>
      <input type="password" id="ce-mg-chiave" name="mailgun_chiave" value="" autocomplete="new-password" placeholder="<?= $cfg['mailgun']['chiave'] !== '' ? 'salvata: lascia vuoto per tenerla' : 'la chiave privata' ?>">
      <div class="hint"><?= $cfg['mailgun']['chiave'] !== '' ? 'Salvata il ' . h($quando((string)$cfg['mailgun']['salvata'])) . ', cifrata. Non si mostra più.' : 'Nessuna chiave salvata.' ?></div></div>
  </fieldset>
  <button class="btn btn-primary" type="submit">Salva</button>
</form>

<div class="form-card" style="margin-top:1rem">
  <h3>Prova l'invio</h3>
  <p class="ce-intro">Manda un'email di prova con un piccolo allegato, con il modo scelto adesso (<?= h($ceAttivo !== '' ? CONSEGNA_SERVIZI[$ceAttivo] : ($smtp ? 'server SMTP' : 'server del sito')) ?>). Se non arriva, guarda anche nello Spam.</p>
  <form method="post" class="ce-riga">
    <?= csrf_field() ?><input type="hidden" name="azione" value="prova">
    <div class="form-group"><label for="ce-prova">Manda la prova a</label><input type="email" id="ce-prova" name="prova_a" value="<?= h(posta_notifica_email()) ?>" required></div>
    <button class="btn btn-secondary" type="submit">Manda la prova</button>
  </form>
</div>

<?php $conChiavi = array_filter(['brevo' => $cfg['brevo']['chiave'] !== '', 'ses' => $cfg['ses']['segreto'] !== '', 'mailgun' => $cfg['mailgun']['chiave'] !== '']); if ($conChiavi): ?>
<div class="form-card" style="margin-top:1rem">
  <h3>Togli le chiavi salvate</h3>
  <div class="ce-azioni"><?php foreach ($conChiavi as $k => $_): ?>
    <form method="post" data-conferma="Togliere le chiavi di <?= h(CONSEGNA_SERVIZI[$k]) ?>?"><?= csrf_field() ?><input type="hidden" name="azione" value="togli_chiavi"><input type="hidden" name="quale" value="<?= $k ?>"><button class="btn btn-danger btn-sm" type="submit">Togli le chiavi di <?= h(CONSEGNA_SERVIZI[$k]) ?></button></form>
  <?php endforeach; ?></div>
</div>
<?php endif; ?>
<script<?= nonce_attr() ?>>
(function () {
  var s = document.getElementById('ce-servizio');
  function mostra() { document.querySelectorAll('.ce-servizio').forEach(function (f) { f.hidden = f.getAttribute('data-servizio') !== s.value; }); }
  s.addEventListener('change', mostra); mostra();
})();
</script>

<?php elseif ($scheda === 'mittenti'): ?>
<p class="ce-intro">Il mittente è il nome e l'indirizzo che le persone vedono quando ricevono un'email. Puoi averne più di uno (per esempio "Anna dello Studio" e "Studio Rossi Notizie"): ogni campagna e ogni flusso può scegliere il suo. Ogni mittente nuovo si conferma con un link mandato a quell'indirizzo.</p>
<div class="form-card">
  <h3>I mittenti</h3>
  <table class="ce-tabella" id="ce-mittenti">
    <thead><tr><th>Nome e indirizzo</th><th>Stato</th><th></th></tr></thead>
    <tbody>
    <?php $cfgMitt = consegna_mittente_config(); if ($cfgMitt !== ''): ?>
    <tr><td><b><?= h((string)($smtp['nome'] ?? '') ?: (string)cms_impostazione('sito_nome', CMS_NOME)) ?></b><br><?= h($cfgMitt) ?></td>
      <td><span class="ce-stato ce-ok">Da Configurazione email</span><?= !$predefinito ? ' <span class="ce-stato ce-ok">Predefinito</span>' : '' ?></td>
      <td><div class="ce-azioni"><a class="btn btn-secondary btn-sm" href="impostazioni.php">Cambia</a><?php if ($predefinito): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="mittente_predefinito"><input type="hidden" name="id" value="0"><button class="btn btn-secondary btn-sm" type="submit">Rendi predefinito</button></form><?php endif; ?></div></td></tr>
    <?php endif; ?>
    <?php foreach ($mittenti as $m): $dom = substr((string)strrchr((string)$m['email'], '@'), 1); ?>
    <tr data-mittente="<?= h((string)$m['email']) ?>"><td><b><?= h((string)$m['nome'] ?: '(senza nome)') ?></b><br><?= h((string)$m['email']) ?><?= $m['rispondi_a'] !== '' ? '<br><small>Risposte a ' . h((string)$m['rispondi_a']) . '</small>' : '' ?>
        <?php if (in_array($dom, CONSEGNA_GRATUITI, true)): ?><br><small class="ce-stato ce-no">Indirizzo gratuito: adatto solo a poche email</small><?php endif; ?></td>
      <td><?= $m['verificato_at'] ? '<span class="ce-stato ce-ok">Confermato</span>' : '<span class="ce-stato ce-att">Aspetta la conferma</span>' ?><?= $m['predefinito'] && $m['verificato_at'] ? ' <span class="ce-stato ce-ok">Predefinito</span>' : '' ?></td>
      <td><div class="ce-azioni">
        <?php if ($m['verificato_at'] && !$m['predefinito']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="mittente_predefinito"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Rendi predefinito</button></form><?php endif; ?>
        <?php if (!$m['verificato_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="mittente_conferma"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Rimanda la conferma</button></form><?php endif; ?>
        <a class="btn btn-secondary btn-sm" href="?scheda=dominio&amp;dominio=<?= h(rawurlencode($dom)) ?>">Controlla il dominio</a>
        <form method="post" data-conferma="Togliere il mittente <?= h((string)$m['email']) ?>?"><?= csrf_field() ?><input type="hidden" name="azione" value="mittente_togli"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Togli</button></form>
      </div></td></tr>
    <?php endforeach; ?>
    <?php if (!$mittenti && $cfgMitt === ''): ?><tr><td colspan="3">Ancora nessun mittente. Aggiungine uno qui sotto, oppure scrivilo in <a href="impostazioni.php">Configurazione email</a>.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<form method="post" class="form-card" style="margin-top:1rem" id="ce-nuovo-mittente">
  <?= csrf_field() ?><input type="hidden" name="azione" value="mittente_aggiungi">
  <h3>Aggiungi un mittente</h3>
  <ol class="ce-intro" style="padding-left:1.1rem">
    <li>Scrivi nome e indirizzo e premi "Aggiungi e manda la conferma".</li>
    <li>Apri la posta di quell'indirizzo: arriva un'email "Conferma il mittente".</li>
    <li>Premi il pulsante nell'email, poi "Confermo il mittente" nella pagina che si apre.</li>
  </ol>
  <div class="ce-riga">
    <div class="form-group"><label for="ce-m-nome">Nome che vedono le persone</label><input type="text" id="ce-m-nome" name="nome" maxlength="100" placeholder="Studio Rossi"></div>
    <div class="form-group"><label for="ce-m-email">Indirizzo</label><input type="email" id="ce-m-email" name="email" maxlength="190" required placeholder="notizie@tuosito.it"></div>
    <div class="form-group"><label for="ce-m-risp">Le risposte arrivano a (facoltativo)</label><input type="email" id="ce-m-risp" name="rispondi_a" maxlength="190" placeholder="uguale all'indirizzo"></div>
  </div>
  <div class="hint" style="margin:.5rem 0 1rem">Usa un indirizzo del tuo dominio. Se le email partono con Brevo, Amazon SES o Mailgun, il mittente va verificato anche lì.</div>
  <button class="btn btn-primary" type="submit">Aggiungi e manda la conferma</button>
</form>

<?php elseif ($scheda === 'avvisi'):
  $avvisi = consegna_avvisi_recenti(30);
  $nomiEvento = ['rimbalzo' => 'Rimbalzo', 'temporaneo' => 'Errore temporaneo', 'spam' => 'Segnalato come spam', 'uscita' => 'Uscito']; ?>
<p class="ce-intro">Quando un indirizzo non esiste (rimbalzo) o qualcuno preme "è spam", il servizio di spedizione lo dice al sito con un avviso automatico (webhook). Il sito toglie subito quella persona dall'elenco: continuare a scriverle rovina la reputazione del dominio. Copia l'indirizzo del tuo servizio e incollalo dove dicono i passi.</p>
<?php if ($ceAttivo === '' && $scelto === 'smtp'): ?><p class="flash flash-info">Adesso le email partono dal server SMTP: i rimbalzi immediati il pannello li vede da solo. Questi indirizzi servono quando usi Brevo, Amazon SES o Mailgun (anche Brevo via SMTP).</p><?php endif; ?>

<section class="ce-voce ce-passo" id="ce-avvisi-brevo">
  <h4>Brevo <?php $u = consegna_ultimo_avviso('brevo'); ?><span class="ce-stato <?= $u ? 'ce-ok' : 'ce-att' ?>"><?= $u ? 'Ultimo avviso ' . h($quando($u)) : 'Nessun avviso ancora' ?></span></h4>
  <div class="ce-record"><span>Indirizzo</span><input type="text" readonly value="<?= h(consegna_webhook_url('brevo')) ?>" data-copia-se-stesso aria-label="Indirizzo del webhook di Brevo"></div>
  <ol>
    <li>In Brevo apri <b>Transazionale</b> (Transactional), <b>Impostazioni</b>, <b>Webhook</b>, e premi <b>Aggiungi un nuovo webhook</b>.</li>
    <li>Incolla l'indirizzo qui sopra come URL.</li>
    <li>Spunta: <b>Hard bounce</b>, <b>Soft bounce</b>, <b>Blocked</b>, <b>Invalid email</b>, <b>Spam</b> (Complaint), <b>Unsubscribed</b>. Salva.</li>
  </ol>
</section>

<section class="ce-voce ce-passo" id="ce-avvisi-ses">
  <h4>Amazon SES <?php $u = consegna_ultimo_avviso('ses'); ?><span class="ce-stato <?= $u ? 'ce-ok' : 'ce-att' ?>"><?= $u ? 'Ultimo avviso ' . h($quando($u)) : 'Nessun avviso ancora' ?></span></h4>
  <div class="ce-record"><span>Indirizzo</span><input type="text" readonly value="<?= h(consegna_webhook_url('ses')) ?>" data-copia-se-stesso aria-label="Indirizzo del webhook di Amazon SES"></div>
  <ol>
    <li>In <b>Amazon SNS</b> crea un argomento (Topic) di tipo Standard, per esempio <code>plumecms-avvisi</code>.</li>
    <li>Nell'argomento premi <b>Crea sottoscrizione</b>: protocollo <b>HTTPS</b>, endpoint l'indirizzo qui sopra. Il sito conferma da solo la sottoscrizione.</li>
    <li>In <b>Amazon SES</b>, <b>Identità verificate</b>, il tuo dominio, <b>Notifiche</b>: per <b>Rimbalzi</b> (Bounce) e <b>Reclami</b> (Complaint) scegli quell'argomento.</li>
  </ol>
  <?php if ($cfg['ses']['argomento'] !== ''): ?><p class="ce-dove">Argomento collegato: <code><?= h((string)$cfg['ses']['argomento']) ?></code>. Gli avvisi di altri argomenti si rifiutano.</p><?php endif; ?>
</section>

<section class="ce-voce ce-passo" id="ce-avvisi-mailgun">
  <h4>Mailgun <?php $u = consegna_ultimo_avviso('mailgun'); ?><span class="ce-stato <?= $u ? 'ce-ok' : 'ce-att' ?>"><?= $u ? 'Ultimo avviso ' . h($quando($u)) : 'Nessun avviso ancora' ?></span></h4>
  <div class="ce-record"><span>Indirizzo</span><input type="text" readonly value="<?= h(consegna_webhook_url('mailgun')) ?>" data-copia-se-stesso aria-label="Indirizzo del webhook di Mailgun"></div>
  <ol>
    <li>In Mailgun, <b>Send</b>, <b>Webhooks</b>, scegli il dominio di invio e premi <b>Add webhook</b>.</li>
    <li>Per <b>Permanent failure</b>, <b>Temporary failure</b>, <b>Spam complaints</b> e <b>Unsubscribes</b> incolla l'indirizzo qui sopra.</li>
    <li>Nella stessa pagina copia la <b>HTTP webhook signing key</b> e incollala qui sotto: il sito controlla che ogni avviso venga davvero da Mailgun.</li>
  </ol>
  <form method="post" class="ce-riga">
    <?= csrf_field() ?><input type="hidden" name="azione" value="mailgun_firma">
    <div class="form-group"><label for="ce-mg-firma">HTTP webhook signing key</label><input type="password" id="ce-mg-firma" name="mailgun_firma" value="" autocomplete="new-password" placeholder="<?= $cfg['mailgun']['firma'] !== '' ? 'salvata: incolla solo per cambiarla' : 'la chiave di firma' ?>"></div>
    <button class="btn btn-secondary" type="submit">Salva la chiave di firma</button>
  </form>
</section>

<form method="post" data-conferma="Cambiare gli indirizzi? Quelli incollati nei servizi smettono di funzionare." style="margin:0 0 1rem">
  <?= csrf_field() ?><input type="hidden" name="azione" value="cambia_indirizzi">
  <button class="btn btn-secondary btn-sm" type="submit">Cambia gli indirizzi</button>
  <span class="ce-dove">Solo se un indirizzo è finito in mani sbagliate: dopo vanno incollati di nuovo nei servizi.</span>
</form>

<div class="form-card">
  <h3>Ultimi avvisi ricevuti</h3>
  <table class="ce-tabella" id="ce-avvisi">
    <thead><tr><th>Quando</th><th>Servizio</th><th>Cosa</th><th>Indirizzo</th><th>Cosa ha fatto il sito</th></tr></thead>
    <tbody>
    <?php foreach ($avvisi as $a): ?><tr><td><?= h($quando((string)$a['quando'])) ?></td><td><?= h(CONSEGNA_SERVIZI[$a['servizio']] ?? (string)$a['servizio']) ?></td><td><?= h($nomiEvento[$a['evento']] ?? (string)$a['evento']) ?></td><td><?= h((string)$a['email']) ?></td><td><?= h((string)$a['esito']) ?></td></tr><?php endforeach; ?>
    <?php if (!$avvisi): ?><tr><td colspan="5">Nessun avviso ancora. Arrivano quando un'email rimbalza o qualcuno la segnala come spam.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php else: /* riscaldamento */
  $giorni = [];
  for ($g = 0; $g < 14; $g++) {
      $inizioGiro = $caldo['acceso'] && $caldo['inizio'] !== '' ? $caldo['inizio'] : date('Y-m-d');
      $s = consegna_riscaldamento_stato(strtotime($inizioGiro . ' 12:00:00') + $g * 86400, $inizioGiro);
      $giorni[] = $s['oggi'];
      if ($s['finito']) break;
  }
  $nl = newsletter_config(); ?>
<p class="ce-intro">Gmail, Outlook e gli altri si fidano di un indirizzo che spedisce sempre più o meno le stesse quantità. Un indirizzo nuovo, o fermo da mesi, che manda subito migliaia di email sembra uno spammer. Con il riscaldamento il sito parte con poche email al giorno e sale da solo, un giorno dopo l'altro, fino al limite che scrivi. Le email che non entrano partono il giorno dopo.</p>
<?php if ($caldo['acceso']): ?>
<div class="form-card" id="ce-caldo-stato" style="margin-bottom:1rem">
  <h3>A che punto è</h3>
  <p><b>Giorno <?= (int)$caldo['giorno'] ?></b>: oggi partono al massimo <b><?= (int)$caldo['oggi'] ?></b> email<?= $caldo['finito'] ? '. Il riscaldamento è arrivato al limite scritto: puoi lasciarlo acceso o spegnerlo.' : ', domani ' . (int)$caldo['domani'] . '. Arriva a ' . (int)$caldo['arrivo'] . ' fra ' . (int)$caldo['giorni_mancanti'] . ' ' . ((int)$caldo['giorni_mancanti'] === 1 ? 'giorno' : 'giorni') . '.' ?></p>
  <p class="ce-dove">Nelle ultime 24 ore ne sono partite <?= posta_inviate(86400) ?>. Contano tutte le email del sito, anche conferme e avvisi; conferme e avvisi però partono sempre, senza aspettare.</p>
</div>
<?php endif; ?>
<form method="post" class="form-card">
  <?= csrf_field() ?><input type="hidden" name="azione" value="riscaldamento">
  <div class="form-group" style="display:flex;gap:.5rem;align-items:center"><input type="checkbox" id="ce-acceso" name="acceso" value="1" <?= $caldo['acceso'] ? 'checked' : '' ?> style="width:auto"><label for="ce-acceso" style="margin:0;text-transform:none;letter-spacing:0">Riscaldamento acceso</label></div>
  <div class="ce-riga">
    <div class="form-group"><label for="ce-partenza">Email il primo giorno</label><input type="number" id="ce-partenza" name="partenza" min="10" max="5000" value="<?= (int)$caldo['partenza'] ?>"><div class="hint">50 va bene per quasi tutti.</div></div>
    <div class="form-group"><label for="ce-arrivo">Fino a quante al giorno</label><input type="number" id="ce-arrivo" name="arrivo" min="0" max="100000" value="<?= (int)consegna_riscaldamento()['arrivo'] ?: '' ?>" placeholder="<?= (int)$caldo['arrivo'] ?>"><div class="hint">Vuoto: il limite al giorno di Newsletter, Impostazioni<?= (int)$nl['per_giorno'] ? ' (' . (int)$nl['per_giorno'] . ')' : ', o 2000' ?>.</div></div>
    <div class="form-group"><label for="ce-fattore">Quanto sale ogni giorno</label><select id="ce-fattore" name="fattore">
      <?php foreach (['1.3' => 'Piano (+30% al giorno)', '1.5' => 'Normale (+50% al giorno)', '2' => 'Svelto (il doppio ogni giorno)'] as $k => $v): ?><option value="<?= $k ?>" <?= abs((float)$k - (float)$caldo['fattore']) < 0.01 ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
  </div>
  <?php if ($caldo['acceso']): ?><div class="form-group" style="display:flex;gap:.5rem;align-items:center"><input type="checkbox" id="ce-ricomincia" name="ricomincia" value="1" style="width:auto"><label for="ce-ricomincia" style="margin:0;text-transform:none;letter-spacing:0">Ricomincia da oggi (dal primo giorno)</label></div><?php endif; ?>
  <p class="ce-dove">Come cresce<?= $caldo['acceso'] ? '' : ', se lo accendi oggi' ?>:</p>
  <ul class="ce-giorni" id="ce-giorni"><?php foreach ($giorni as $i => $n): ?><li class="<?= $caldo['acceso'] && $i + 1 === (int)$caldo['giorno'] ? 'oggi' : '' ?>">giorno <?= $i + 1 ?>: <b><?= (int)$n ?></b></li><?php endforeach; ?></ul>
  <div style="margin-top:1rem"><button class="btn btn-primary" type="submit">Salva</button></div>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
