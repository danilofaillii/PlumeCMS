<?php
/**
 * Impostazioni > Cambia database (2.37.0): sposta tutto il database del sito su un altro database (lo stesso hosting o un altro
 * server MySQL o MariaDB) e, a copia verificata, fa usare al sito quello nuovo. Motore in includes/cambia-db.php.
 * Solo per l'amministratore; ogni azione in POST con il token CSRF; partire, ricominciare e tornare indietro chiedono la sua password.
 * La password del database nuovo non torna mai nella pagina (salvo nelle righe da incollare a mano nel config, solo a lui).
 */
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/cambia-db.php';
require_admin();

$adminTitle = 'Cambia database';
$utente = (string)($_SESSION['admin_username'] ?? '');
$errori = [];
$valori = null;
$io = current_user();

// Un giro della copia, chiesto dalla pagina (admin/assets/contatti.js, data-nl-giro): risposta JSON.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'giro') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!csrf_ok()) { http_response_code(403); echo json_encode(['errore' => 'La sessione è scaduta: ricarica la pagina.']); exit; }
    session_write_close();
    @ignore_user_abort(true);
    ob_start();
    try { $r = cambiadb_giro(); } catch (Throwable $e) { error_log('cambia-db giro: ' . get_class($e)); $r = ['errore' => 'Il giro non è andato a buon fine.']; }
    ob_end_clean();
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

/** La password dell'amministratore, per le azioni che cambiano il database del sito. */
function cambiadb_password_admin(array &$io): bool {
    return sicurezza_password_verifica($io, (string)($_POST['password_admin'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    try {
        if ($azione === 'prova') {
            [$d, $errori] = cambiadb_valida($_POST);
            $valori = $d;
            $pass = (string)($_POST['password'] ?? '');
            if (strlen($pass) > 200) $errori['password'] = 'La password è troppo lunga.';
            if (!$errori) {
                if (!cambiadb_prova_ammessa()) throw new CambiaDbErrore('Hai provato la connessione troppe volte in pochi minuti (' . CAMBIADB_PROVE_MAX . ' ogni ' . (CAMBIADB_PROVE_FINESTRA / 60) . ' minuti). Aspetta qualche minuto e riprova.');
                try {
                    $r = cambiadb_prova($d, $pass);
                    $_SESSION['cambiadb_provato'] = ['dati' => $d, 'pass' => cambiadb_cifra($pass), 'quando' => time(), 'versione' => $r['versione'], 'esistenti' => $r['esistenti']];
                    sicurezza_log('cambia_db', $utente, (int)($io['id'] ?? 0), 'prova della connessione riuscita: ' . $d['host'] . ':' . $d['porta'] . '/' . $d['nome'] . ' (' . $r['versione'] . ', ' . $r['esistenti'] . ' tabelle con il prefisso ' . $d['prefisso'] . ')');
                    header('Location: cambia-db.php#pronto'); exit;
                } catch (CambiaDbErrore $e) {
                    unset($_SESSION['cambiadb_provato']);
                    sicurezza_log('cambia_db', $utente, (int)($io['id'] ?? 0), 'prova della connessione non riuscita: ' . $d['host'] . ':' . $d['porta'] . '/' . $d['nome']);
                    throw $e;
                }
            }
        } elseif ($azione === 'avvia') {
            $p = $_SESSION['cambiadb_provato'] ?? null;
            if (!is_array($p) || time() - (int)$p['quando'] > 1800) { unset($_SESSION['cambiadb_provato']); throw new CambiaDbErrore('Prima prova la connessione al database nuovo (la prova vale mezz\'ora).'); }
            if (!cambiadb_password_admin($io)) { sicurezza_log('cambia_db', $utente, (int)($io['id'] ?? 0), 'avvio rifiutato: password dell\'amministratore sbagliata'); throw new CambiaDbErrore('La tua password non è giusta: il cambio non parte.'); }
            if (!cambiadb_prova_ammessa()) throw new CambiaDbErrore('Troppi tentativi di connessione in pochi minuti: aspetta qualche minuto e riprova.');
            cambiadb_avvia($p['dati'], cambiadb_decifra((string)$p['pass']), !empty($_POST['sostituisci']), $utente);
            unset($_SESSION['cambiadb_provato']);
            header('Location: cambia-db.php'); exit;
        } elseif ($azione === 'dimentica') {
            unset($_SESSION['cambiadb_provato']);
            header('Location: cambia-db.php'); exit;
        } elseif ($azione === 'annulla') {
            flash('success', cambiadb_annulla($utente));
            header('Location: cambia-db.php'); exit;
        } elseif ($azione === 'ricomincia') {
            if (!cambiadb_password_admin($io)) throw new CambiaDbErrore('La tua password non è giusta: la copia non ricomincia.');
            cambiadb_ricomincia($utente);
            header('Location: cambia-db.php'); exit;
        } elseif ($azione === 'controlla') {
            $m = cambiadb_controlla_manuale();
            if ($m !== '') throw new CambiaDbErrore($m);
            header('Location: cambia-db.php'); exit;
        } elseif ($azione === 'torna') {
            if (empty($_POST['conferma'])) throw new CambiaDbErrore('Per tornare al database di prima spunta la conferma.');
            if (!cambiadb_password_admin($io)) throw new CambiaDbErrore('La tua password non è giusta: il sito resta sul database nuovo.');
            $r = cambiadb_torna($utente);
            if ($r['ok']) { flash('success', $r['messaggio']); header('Location: cambia-db.php'); exit; }
            $_SESSION['cambiadb_torna_righe'] = $r['manuale'];
            flash('error', $r['messaggio']);
            header('Location: cambia-db.php#torna-a-mano'); exit;
        }
    } catch (CambiaDbErrore $e) {
        flash('error', $e->getMessage());
        if ($azione !== 'prova') { header('Location: cambia-db.php'); exit; }
    }
}

$l = cambiadb_lavoro();
$u = cambiadb_ultimo();
// Il passaggio (o il ritorno) si registra al primo caricamento con il config nuovo, nel database che il sito usa adesso.
if ($u && empty($u['registrato']) && ($u['fase'] ?? '') === 'fatto' && DB_NAME === ($u['dest']['nome'] ?? '') && DB_PREFIX === ($u['dest']['prefisso'] ?? '')) {
    sicurezza_log('cambia_db', (string)($u['utente'] ?? $utente), (int)($io['id'] ?? 0), 'passaggio fatto: il sito usa ' . $u['dest']['host'] . ':' . $u['dest']['porta'] . '/' . $u['dest']['nome'] . ' (prefisso ' . $u['dest']['prefisso'] . '), ' . (int)$u['tabelle'] . ' tabelle, ' . (int)$u['righe'] . ' righe. Il database di prima (' . ($u['prima']['host'] ?? '') . ':' . ($u['prima']['porta'] ?? '') . '/' . ($u['prima']['nome'] ?? '') . ') resta intatto.');
    $u['registrato'] = true;
    try { cambiadb_ultimo_scrivi($u); } catch (Throwable $e) {}
}
$provato = $_SESSION['cambiadb_provato'] ?? null;
if (is_array($provato) && time() - (int)$provato['quando'] > 1800) { unset($_SESSION['cambiadb_provato']); $provato = null; }
$righeTorna = $_SESSION['cambiadb_torna_righe'] ?? null; unset($_SESSION['cambiadb_torna_righe']);
[$hostOra, $portaOra] = cambiadb_host_attuale();
$valori = $valori ?? (is_array($provato) ? $provato['dati'] : ['host' => $hostOra !== '' ? $hostOra : 'localhost', 'porta' => $portaOra, 'nome' => '', 'utente' => '', 'prefisso' => DB_PREFIX]);
$attiva = $l && cambiadb_manutenzione_attiva($l);
$scaduto = $l && cambiadb_scaduto($l);

/** Un campo del modulo con l'errore sotto (regole di interfaccia 4.2 e 4.3). */
function cambiadb_campo(string $nome, string $etichetta, string $valore, array $errori, string $tipo = 'text', string $aiuto = '', array $attr = []): string {
    $id = (string)($attr['id'] ?? 'cdb-' . $nome); unset($attr['id']); $err = $errori[$nome] ?? '';
    $desc = trim(($aiuto !== '' ? $id . '-aiuto ' : '') . ($err !== '' ? $id . '-errore' : ''));
    $a = '';
    foreach ($attr as $k => $v) $a .= ' ' . $k . '="' . h($v) . '"';
    return '<div class="form-group"><label for="' . $id . '">' . h($etichetta) . '</label>'
        . '<input type="' . $tipo . '" id="' . $id . '" name="' . $nome . '"' . ($tipo === 'password' ? ' value="" autocomplete="new-password"' : ' value="' . h($valore) . '"') . $a
        . ($err !== '' ? ' aria-invalid="true"' : '') . ($desc !== '' ? ' aria-describedby="' . $desc . '"' : '') . '>'
        . ($err !== '' ? '<p class="errore-campo" id="' . $id . '-errore" role="alert">' . h($err) . '</p>' : '')
        . ($aiuto !== '' ? '<p class="hint" id="' . $id . '-aiuto">' . h($aiuto) . '</p>' : '') . '</div>';
}

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.cdb-largo{max-width:760px}
.cdb-due{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,1fr);gap:0 1rem}
@media(max-width:640px){.cdb-due{grid-template-columns:minmax(0,1fr)}}
.cdb-riepilogo{margin:.2rem 0 1rem;padding:0;list-style:none;font-size:.9rem;line-height:1.6}
.cdb-riepilogo b{display:inline-block;min-width:9.5rem}
.cdb-spunta{display:flex;gap:.5rem;align-items:flex-start;font-size:.9rem;margin:.6rem 0 1rem}
.cdb-spunta input{margin-top:.25rem;flex:0 0 auto}
.cdb-righe{background:var(--codice-fondo);color:var(--codice-testo);padding:.9rem 1rem;border-radius:8px;font-family:var(--font-codice,monospace);font-size:.82rem;white-space:pre-wrap;word-break:break-all;margin:.6rem 0 1rem}
.cdb-passi{margin:.4rem 0 1rem 1.2rem;font-size:.9rem;line-height:1.55}
.cdb-azioni{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-top:.8rem}
.cdb-nota{font-size:.85rem;color:var(--text-muted);line-height:1.5;margin:.4rem 0 0}
.cdb-esito-ok{border-left:4px solid var(--successo-bordo)}
.cdb-esito-ko{border-left:4px solid var(--errore-bordo)}
</style>
<div class="cdb-largo">

<?php if ($l && $l['fase'] === 'copia' && !$scaduto): $av = cambiadb_avanzamento($l); $pct = (int)round($av['fatte'] * 100 / max(1, $av['totale'])); ?>
<div class="form-card" id="cdb-lavoro" data-nl-giro="cambia-db.php" data-csrf="<?= h(csrf_token()) ?>">
  <h3>Copia in corso verso il database nuovo</h3>
  <p class="hint" style="margin-top:0">Da <b><?= h($l['versione_prima'] ?? '') ?></b> a <b><?= h($l['dest']['nome']) ?></b> su <?= h($l['dest']['host']) ?> (<?= h($l['versione_dest'] ?? '') ?>), prefisso <code><?= h($l['dest']['prefisso']) ?></code>. Il lavoro va a giri brevi: lascia aperta questa pagina. Intanto il sito mostra ai visitatori «Torno subito» e il pannello mostra solo questa pagina, così nessuna modifica si perde.</p>
  <div class="nli-barra" role="progressbar" aria-label="Avanzamento della copia" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>" data-nl-barra><span style="width:<?= $pct ?>%"></span></div>
  <p data-nl-testo role="status"><?= h($av['testo']) ?></p>
  <p class="errore-campo" data-nl-errore role="alert" hidden></p>
  <button class="btn btn-secondary btn-sm" type="button" data-nl-riprendi hidden>Riprendi la copia</button>
  <p class="cdb-nota">Se chiudi la pagina, la copia si ferma; dopo <?= (int)round(cambiadb_scadenza('copia') / 60) ?> minuti il sito torna normale da solo, sul database di adesso, che non viene mai toccato.</p>
  <form method="post" class="cdb-azioni" data-conferma="Annullo la copia? Il sito resta sul database di adesso e nel database nuovo si cancellano le tabelle copiate fin qui." data-conferma-ok="Annulla la copia" data-conferma-pericolo>
    <?= csrf_field() ?><input type="hidden" name="azione" value="annulla"><button class="btn btn-danger" type="submit">Annulla la copia</button>
  </form>
</div>

<?php elseif ($l && $l['fase'] === 'copia' && $scaduto): ?>
<div class="form-card cdb-esito-ko" id="cdb-fermo">
  <h3>La copia si è fermata</h3>
  <p>La pagina è rimasta chiusa più di <?= (int)round(cambiadb_scadenza('copia') / 60) ?> minuti, e il sito è tornato normale sul database di adesso (che non è stato toccato). Nel frattempo il sito può essere cambiato: la copia ricomincia da capo.</p>
  <form method="post" style="margin-top:.8rem">
    <?= csrf_field() ?><input type="hidden" name="azione" value="ricomincia">
    <?= cambiadb_campo('password_admin', 'La tua password', '', [], 'password', 'La password con cui entri nel pannello: serve per rifare il cambio.', ['required' => 'required', 'autocomplete' => 'current-password']) ?>
    <button class="btn btn-primary" type="submit">Ricomincia la copia</button>
  </form>
  <form method="post" class="cdb-azioni" data-conferma="Annullo il cambio? Il sito resta sul database di adesso." data-conferma-ok="Annulla il cambio" data-conferma-pericolo>
    <?= csrf_field() ?><input type="hidden" name="azione" value="annulla"><button class="btn btn-danger" type="submit">Annulla il cambio</button>
  </form>
</div>

<?php elseif ($l && $l['fase'] === 'manuale'): $righe = cambiadb_righe_manuali($l); ?>
<div class="form-card" id="cdb-manuale">
  <h3>Ultimo passo: cambia a mano il file del database</h3>
  <p>La copia è finita e i conti tornano: <?= count($l['tabelle']) ?> tabelle, <?= number_format(array_sum(array_column($l['tabelle'], 'righe')), 0, ',', '.') ?> righe, tutte uguali. Il sito però non può cambiare da solo il file <code>includes/config.local.php</code> (<?= h((string)($l['motivo_manuale'] ?? '')) ?>). Fallo tu, con il gestore dei file dell'hosting:</p>
  <ol class="cdb-passi">
    <li>Apri il pannello dell'hosting (in cPanel: <b>Gestione file</b>) ed entra nella cartella del sito, poi in <code>includes</code>.</li>
    <li>Fai clic destro su <code>config.local.php</code> e scegli <b>Modifica</b> (se il file è protetto, prima <b>Permessi</b>: 644).</li>
    <li>Trova le righe che cominciano con <code>define('DB_HOST'</code>, <code>define('DB_NAME'</code>, <code>define('DB_USER'</code>, <code>define('DB_PASS'</code> e <code>define('CMS_PREFISSO'</code> e sostituiscile con queste cinque (se <code>CMS_PREFISSO</code> non c'è, aggiungila sotto <code>DB_PASS</code>):</li>
  </ol>
  <pre class="cdb-righe" id="cdb-righe"><?= h(implode("\n", $righe)) ?></pre>
  <ol class="cdb-passi" start="4">
    <li>Salva il file, torna qui e premi <b>Ho cambiato il file, controlla</b>.</li>
  </ol>
  <p class="cdb-nota">Queste righe contengono la password del database nuovo: non copiarle altrove. Finché non controlli, il sito resta in «Torno subito» (al massimo <?= (int)round(cambiadb_scadenza('manuale') / 60) ?> minuti, poi torna sul database di adesso).</p>
  <div class="cdb-azioni">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="controlla"><button class="btn btn-primary" type="submit">Ho cambiato il file, controlla</button></form>
    <form method="post" data-conferma="Annullo il cambio? Il sito resta sul database di adesso e nel database nuovo si cancellano le tabelle copiate." data-conferma-ok="Annulla il cambio" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="annulla"><button class="btn btn-danger" type="submit">Annulla il cambio</button></form>
  </div>
</div>

<?php else: ?>

<?php if ($u && ($u['fase'] ?? '') === 'fatto' && DB_NAME === ($u['dest']['nome'] ?? '')): ?>
<div class="form-card cdb-esito-ok" id="cdb-fatto">
  <h3>Il sito usa il database nuovo</h3>
  <p role="status">Dal <?= h(date('d/m/Y H:i', (int)$u['quando'])) ?> il sito usa <b><?= h($u['dest']['nome']) ?></b> su <?= h($u['dest']['host']) ?> (<?= h((string)($u['versione_dest'] ?? '')) ?>), prefisso <code><?= h($u['dest']['prefisso']) ?></code>: <?= (int)$u['tabelle'] ?> tabelle e <?= number_format((int)$u['righe'], 0, ',', '.') ?> righe copiate, con il numero di righe controllato tabella per tabella.</p>
  <?php if (!empty($u['cambi'])): ?><p class="cdb-nota">Adattate al server nuovo: <?= h(implode('; ', array_map(fn($k, $n) => $k . ' (' . $n . ($n === 1 ? ' tabella)' : ' tabelle)'), array_keys($u['cambi']), $u['cambi']))) ?>.</p><?php endif; ?>
  <p><b class="cdb-intatto">Il database di prima (<?= h((string)($u['prima']['nome'] ?? '')) ?>) resta intatto.</b> <?= h(CMS_NOME) ?> non lo cancella mai: quando hai visto per qualche giorno che tutto funziona, puoi eliminarlo a mano dal pannello dell'hosting (in cPanel: Database MySQL).</p>
  <details id="torna"<?= $righeTorna ? ' open' : '' ?>><summary>Torna al database di prima</summary>
    <p class="cdb-nota">Il sito riprende a usare il database di prima, così com'era al momento del passaggio. <b>Le modifiche fatte dopo il passaggio (articoli, contatti, invii dei form) stanno solo nel database nuovo</b> e nel database di prima non ci sono.</p>
    <?php if ($righeTorna): ?>
      <div id="torna-a-mano"><p>Il file <code>includes/config.local.php</code> non si può scrivere da qui: rimetti queste righe a mano con il gestore dei file dell'hosting, al posto di quelle del database.</p><pre class="cdb-righe"><?= h(implode("\n", (array)$righeTorna)) ?></pre></div>
    <?php endif; ?>
    <form method="post" data-conferma="Torno al database di prima? Le modifiche fatte dopo il passaggio restano solo nel database nuovo." data-conferma-ok="Torna al database di prima" data-conferma-pericolo>
      <?= csrf_field() ?><input type="hidden" name="azione" value="torna">
      <label class="cdb-spunta"><input type="checkbox" name="conferma" value="1" required> <span>Ho capito: le modifiche fatte dopo il passaggio non ci saranno.</span></label>
      <?= cambiadb_campo('password_admin', 'La tua password', '', [], 'password', '', ['required' => 'required', 'autocomplete' => 'current-password', 'id' => 'cdb-password-torna']) ?>
      <button class="btn btn-danger" type="submit">Torna al database di prima</button>
    </form>
  </details>
</div>
<?php elseif ($u && ($u['fase'] ?? '') === 'tornato'): ?>
<div class="form-card" id="cdb-tornato"><h3>Il sito è tornato al database di prima</h3><p role="status">Dal <?= h(date('d/m/Y H:i', (int)($u['tornato'] ?? $u['quando']))) ?> il sito usa di nuovo <b><?= h((string)($u['prima']['nome'] ?? '')) ?></b>. Il database nuovo (<?= h($u['dest']['nome']) ?>) resta com'era: se non ti serve più, eliminalo dal pannello dell'hosting.</p></div>
<?php elseif ($u && ($u['fase'] ?? '') === 'errore' && time() - (int)$u['quando'] < 86400): ?>
<div class="form-card cdb-esito-ko" id="cdb-errore"><h3>L'ultimo cambio non è riuscito</h3><p role="alert"><?= h((string)$u['messaggio']) ?></p><p class="cdb-nota">Il sito usa il database di sempre, che non è stato toccato.</p></div>
<?php endif; ?>

<?php if ($provato): $pd = $provato['dati']; ?>
<div class="form-card" id="pronto">
  <h3>Connessione riuscita: pronto per la copia</h3>
  <ul class="cdb-riepilogo">
    <li><b>Server</b> <?= h($pd['host']) ?>, porta <?= (int)$pd['porta'] ?> (<?= h($provato['versione']) ?>)</li>
    <li><b>Database</b> <?= h($pd['nome']) ?>, utente <?= h($pd['utente']) ?></li>
    <li><b>Prefisso delle tabelle</b> <code><?= h($pd['prefisso']) ?></code></li>
    <li><b>Da copiare</b> <?= count(copia_tabelle_sito()) ?> tabelle di questo sito</li>
  </ul>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="avvia">
    <?php if ((int)$provato['esistenti'] > 0): ?>
      <p class="errore-campo" role="alert">Nel database nuovo ci sono già <?= (int)$provato['esistenti'] ?> tabelle con il prefisso <code><?= h($pd['prefisso']) ?></code>.</p>
      <label class="cdb-spunta"><input type="checkbox" name="sostituisci" value="1" required> <span>Sostituisci le tabelle con questo prefisso nel database nuovo (solo quelle: le altre tabelle di quel database restano come sono).</span></label>
    <?php endif; ?>
    <?= cambiadb_campo('password_admin', 'La tua password', '', [], 'password', 'La password con cui entri nel pannello: conferma che sei tu a spostare il sito.', ['required' => 'required', 'autocomplete' => 'current-password', 'id' => 'cdb-password-avvia']) ?>
    <p class="cdb-nota">Durante la copia il sito mostra ai visitatori «Torno subito» per qualche minuto. Il database di adesso si legge e basta: resta intatto.</p>
    <div class="cdb-azioni"><button class="btn btn-primary" type="submit">Copia e passa al database nuovo</button></div>
  </form>
  <form method="post" class="cdb-azioni"><?= csrf_field() ?><input type="hidden" name="azione" value="dimentica"><button class="btn btn-secondary btn-sm" type="submit">Cambia i dati del database</button></form>
</div>
<?php endif; ?>

<form method="post" class="form-card" id="cdb-modulo" novalidate>
  <h3>Il database nuovo</h3>
  <p class="hint" style="margin-top:0">Sposta tutto il database del sito su un altro database, dello stesso hosting o di un altro server, e poi fa usare al sito quello nuovo. Prima crea il database nuovo e il suo utente nel pannello dell'hosting (in cPanel: <b>Database MySQL</b>), con tutti i privilegi. Adesso il sito usa <b><?= h(DB_NAME) ?></b> su <?= h($hostOra) ?>, prefisso <code><?= h(DB_PREFIX) ?></code>.</p>
  <?php if ($errori): ?><p class="errore-campo" role="alert"><?= count($errori) === 1 ? '1 campo da sistemare: lo trovi segnato in rosso qui sotto.' : count($errori) . ' campi da sistemare: li trovi segnati in rosso qui sotto.' ?></p><?php endif; ?>
  <?= csrf_field() ?><input type="hidden" name="azione" value="prova">
  <div class="cdb-due">
    <?= cambiadb_campo('host', 'Server (host)', (string)$valori['host'], $errori, 'text', 'Quasi sempre localhost. Solo il nome o l\'indirizzo IP del server, senza http:// e senza porta.', ['autocomplete' => 'off', 'spellcheck' => 'false']) ?>
    <?= cambiadb_campo('porta', 'Porta', (string)$valori['porta'], $errori, 'text', 'Quasi sempre 3306.', ['inputmode' => 'numeric', 'autocomplete' => 'off']) ?>
  </div>
  <?= cambiadb_campo('nome', 'Nome del database', (string)$valori['nome'], $errori, 'text', 'Come lo mostra il pannello dell\'hosting, compreso quello che c\'è davanti al trattino basso (per esempio account_nuovo).', ['autocomplete' => 'off', 'spellcheck' => 'false', 'placeholder' => 'per esempio: account_nuovo']) ?>
  <?= cambiadb_campo('utente', 'Utente', (string)$valori['utente'], $errori, 'text', '', ['autocomplete' => 'off', 'spellcheck' => 'false']) ?>
  <?= cambiadb_campo('password', 'Password dell\'utente', '', $errori, 'password', 'Non si mostra più dopo l\'invio.') ?>
  <?= cambiadb_campo('prefisso', 'Prefisso delle tabelle', (string)$valori['prefisso'], $errori, 'text', 'Di partenza lo stesso di adesso. Solo lettere minuscole, numeri e trattino basso.', ['autocomplete' => 'off', 'spellcheck' => 'false']) ?>
  <div class="cdb-azioni"><button class="btn btn-primary" type="submit">Prova la connessione</button></div>
  <details class="pn-aiuto" style="margin-top:1rem"><summary>Come funziona il cambio</summary>
    <ol class="cdb-passi">
      <li><b>Prova la connessione</b>: il sito si collega al database nuovo, dice la versione del server, controlla i permessi e se ci sono già tabelle con quel prefisso.</li>
      <li><b>Copia e passa al database nuovo</b>: copia tutte le tabelle del sito, a giri, con una barra di avanzamento. Intanto il sito mostra «Torno subito».</li>
      <li>Alla fine conta le righe di ogni tabella nei due database. Se qualcosa non torna si ferma e non cambia niente.</li>
      <li>A copia controllata salva una copia del file <code>includes/config.local.php</code> (non si apre dal web), scrive quello nuovo e prova il sito sul database nuovo. Se la prova non va, rimette il file di prima.</li>
      <li>Il database di prima non si cancella mai: resta lì finché non lo elimini tu.</li>
    </ol>
  </details>
</form>
<?php endif; ?>
</div>
<script src="<?= pannello_file('assets/contatti.js') ?>"></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
