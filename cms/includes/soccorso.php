<?php
/**
 * La pagina di soccorso del pannello (2.25.1). Il 4 novembre 2026, su un sito vero, un ripristino andato male ha lasciato il
 * database senza la tabella users: ogni pagina del pannello (anche il login) moriva con un errore fatale, perché il primo
 * controllo della sessione legge gli utenti. Ora, prima di qualunque funzione che crei tabelle (sicurezza_schema(),
 * sito_schema_aggiorna()), auth.php chiede a soccorso_stato() se il database è raggiungibile e se ci sono users e settings
 * del prefisso. Se no, risponde con questa pagina (503, senza sessione, senza login: il login non sarebbe possibile) che dice
 * in parole semplici cosa succede e come rimettere il sito da una copia con phpMyAdmin. Tre casi:
 *   - il database non si raggiunge (connessione rifiutata, utente o password sbagliati, database inesistente);
 *   - mancano le tabelle del prefisso (users, e di solito tutte le altre); se nel database c'è un'altra installazione
 *     (settings e users con un altro prefisso) lo dice: forse il config indica il prefisso sbagliato;
 *   - manca solo settings (users c'è).
 * Niente percorsi assoluti del server, mai la password, mai l'utente del database. La pagina esce anche a chi non è entrato nel
 * pannello (il login non c'è): il nome del database e il prefisso delle tabelle (quello che serve in phpMyAdmin), la risposta
 * tecnica del server (con l'utente e l'host del database coperti), gli altri prefissi nel database, l'elenco delle copie in
 * aggiornamenti/copie/ (nome, data, etichetta, peso, sito d'origine dal manifest) e le prime SOCCORSO_REGISTRO_RIGHE righe
 * dell'ultimo registro di ripristino si mostrano SOLO se esiste il file aggiornamenti/soccorso.txt, la "porta" che il
 * proprietario crea con il gestore dei file dell'hosting (la pagina spiega come), e solo per SOCCORSO_PORTA_ORE ore dalla sua
 * creazione (dopo la pagina lo ignora e dice di ricrearlo: un file dimenticato non resta una porta aperta per sempre); a
 * chiunque altro solo le istruzioni, con il rimando al config. Nessuna azione dalla pagina (niente download, niente
 * ripristino). Il file soccorso.txt si cancella da solo alla prima pagina del pannello che funziona, e la pagina ricorda di
 * cancellarlo a lavoro finito.
 */

/** 2.25.1: per quante ore vale il file aggiornamenti/soccorso.txt dalla sua creazione. */
const SOCCORSO_PORTA_ORE = 24;
/** 2.25.1: quante righe dell'ultimo registro di ripristino mostra la pagina (il resto sta nel file, in aggiornamenti/copie/). */
const SOCCORSO_REGISTRO_RIGHE = 15;

/** Il file che apre l'elenco delle copie nella pagina di soccorso. */
function soccorso_file_elenco(): string {
    return dirname(__DIR__) . '/aggiornamenti/soccorso.txt';
}

/** 2.25.1: la porta è aperta? 'si' (il file c'è ed è fresco), 'scaduto' (c'è ma ha più di SOCCORSO_PORTA_ORE ore), 'no'. */
function soccorso_porta(): string {
    $f = soccorso_file_elenco();
    if (!is_file($f)) return 'no';
    $t = (int)@filemtime($f);
    return $t > 0 && $t >= time() - SOCCORSO_PORTA_ORE * 3600 ? 'si' : 'scaduto';
}

/** 2.25.1: la risposta tecnica del server senza l'utente e l'host del database ("Access denied for user 'x'@'y'" -> "... 'utente'@'host'"). */
function soccorso_messaggio_coperto(string $msg): string {
    if (defined('DB_USER') && DB_USER !== '') $msg = preg_replace('/(?<![A-Za-z0-9_])' . preg_quote(DB_USER, '/') . '(?![A-Za-z0-9_])/', 'utente', $msg); // parola intera: un nome corto non copre pezzi di altre parole
    $msg = preg_replace("/'utente'@'[^']*'/", "'utente'@'host'", $msg);
    if (defined('DB_HOST') && DB_HOST !== '') $msg = str_replace(DB_HOST, 'host', $msg);
    return $msg;
}

/**
 * Lo stato del database per il pannello: null se il database risponde e ha users e settings del prefisso; altrimenti
 * ['caso' => 'connessione'|'tabelle'|'settings', ...]. Non crea e non cambia niente (solo SHOW TABLES).
 */
function soccorso_stato(): ?array {
    try {
        $pdo = db();
        $ha = fn(string $t): bool => (bool)$pdo->query('SHOW TABLES LIKE ' . $pdo->quote(addcslashes(DB_PREFIX . $t, '%_\\')))->fetchColumn();
        $users = $ha('users'); $settings = $ha('settings');
        if ($users && $settings) return null;
        $tutte = array_map(fn($r) => (string)$r[0], $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));
    } catch (PDOException $e) {
        $cod = (int)(isset($e->errorInfo[1]) ? $e->errorInfo[1] : $e->getCode());
        return ['caso' => 'connessione', 'codice' => $cod, 'messaggio' => $e->getMessage()];
    }
    $altri = [];
    $set = array_flip($tutte);
    foreach ($tutte as $t) {
        if (preg_match('/^(.*)settings$/', $t, $m) && $m[1] !== DB_PREFIX && isset($set[$m[1] . 'users'])) $altri[] = $m[1];
    }
    $conPrefisso = count(array_filter($tutte, fn($t) => DB_PREFIX !== '' && str_starts_with($t, DB_PREFIX)));
    return ['caso' => $users ? 'settings' : 'tabelle', 'users' => $users, 'settings' => $settings, 'altri' => array_values(array_unique($altri)), 'con_prefisso' => $conPrefisso];
}

/** Il controllo da fare in testa a ogni pagina del pannello: se il database non è in ordine risponde e chiude. */
function soccorso_controlla(): void {
    $s = soccorso_stato();
    if ($s === null) {
        if (is_file(soccorso_file_elenco())) @unlink(soccorso_file_elenco()); // il pannello funziona: l'elenco non serve più
        return;
    }
    soccorso_rispondi($s);
    exit;
}

/** Le copie sul server per la pagina (senza percorsi): nome, data, etichetta, peso, sito d'origine. */
function soccorso_copie(): array {
    $out = [];
    try {
        require_once __DIR__ . '/copia-sito.php';
        foreach (copia_elenco(false) as $c) { // senza il calcolo della firma: la pagina può uscire a chiunque, tante volte
            $m = $c['manifest'];
            $out[] = ['file' => $c['file'], 'data' => $c['data'], 'peso' => $c['peso'], 'etichetta' => is_array($m) ? (string)($m['etichetta'] ?? '') : '',
                'sito' => is_array($m) ? (string)($m['sito'] ?? '') : '', 'tabelle' => is_array($m) ? (int)($m['tabelle'] ?? 0) : 0, 'valida' => is_array($m)];
        }
    } catch (Throwable $e) { error_log('soccorso: ' . $e->getMessage()); }
    return $out;
}

/** La pagina (o il JSON per le chiamate -api.php): 503, senza memoria del browser. */
function soccorso_rispondi(array $s): void {
    $h = fn(string $x): string => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
    $api = str_ends_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '-api.php') || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json');
    if (!headers_sent()) { http_response_code(503); header('Cache-Control: no-store'); header('Retry-After: 600'); }
    $titolo = $s['caso'] === 'connessione' ? 'Il pannello non raggiunge il database' : ($s['caso'] === 'tabelle' ? 'Il database del sito è vuoto o incompleto' : 'Il database del sito è incompleto');
    if ($api) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['errore' => $titolo . ': apri il pannello per le istruzioni.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
    $db = (string)(defined('DB_NAME') ? DB_NAME : ''); $pref = DB_PREFIX;
    // La porta del proprietario (aggiornamenti/soccorso.txt, fresca di meno di SOCCORSO_PORTA_ORE ore): solo con quella escono
    // il nome del database, il prefisso, la risposta del server, le copie e il registro. L'utente del database mai.
    $stato = soccorso_porta();
    $porta = $stato === 'si';
    $copie = $porta ? soccorso_copie() : [];
    $registro = null;
    if ($porta) { try { require_once __DIR__ . '/copia-sito.php'; $registro = copia_registro_ultimo(); } catch (Throwable $e) {} }
    $css = function_exists('cms_base') ? cms_base() . '/admin/assets/css/aspetti.css' : 'assets/css/aspetti.css';
    ?>
<!DOCTYPE html>
<html lang="it" data-pannello="aria">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title><?= $h($titolo) ?></title>
  <link rel="stylesheet" href="<?= $h($css) ?>">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; gap: 1.2rem; padding: 2rem 1rem; }
    .marchio { font-size: 1.05rem; font-weight: 700; color: var(--text); letter-spacing: -.01em; }
    .marchio strong { color: var(--coral); }
    .box { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 2rem 2.2rem; width: 100%; max-width: 720px; box-shadow: var(--ombra-grande); }
    .box h1 { font-size: 1.35rem; margin-bottom: .6rem; }
    .box h2 { font-size: 1rem; margin: 1.4rem 0 .5rem; padding: .45rem .7rem; background: var(--testata, var(--bg)); color: var(--testata-testo, var(--text)); border-left: 4px solid var(--coral); border-radius: 6px; }
    .box p, .box li { font-size: .95rem; line-height: 1.55; }
    .box p { margin-bottom: .7rem; }
    .box ol { padding-left: 1.4rem; }
    .box ol li { margin-bottom: .5rem; }
    .avviso { border: 1px solid var(--errore-bordo); background: var(--errore-chiaro); color: var(--errore-testo); border-radius: 8px; padding: .75rem .9rem; margin-bottom: 1rem; }
    .info { border: 1px solid var(--info-bordo); background: var(--info-chiaro); color: var(--info-testo); border-radius: 8px; padding: .75rem .9rem; margin: .6rem 0; }
    .tec { font-size: .82rem; color: var(--text-muted); }
    code { font-family: monospace; font-size: .9em; background: var(--bg); padding: .05rem .3rem; border-radius: 4px; }
    table { width: 100%; border-collapse: collapse; font-size: .86rem; margin-top: .4rem; }
    th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); padding: .45rem .5rem; border-bottom: 1px solid var(--border); }
    td { padding: .5rem; border-bottom: 1px solid var(--border); vertical-align: top; overflow-wrap: anywhere; }
    td.fisso { white-space: nowrap; }
    .registro { background: var(--codice-fondo); color: var(--codice-testo); padding: .8rem 1rem; border-radius: 8px; font-family: monospace; font-size: .78rem; white-space: pre-wrap; word-break: break-word; max-height: 300px; overflow: auto; }
    @media (max-width: 640px) { .box { padding: 1.2rem 1rem; } body { padding: 1rem .6rem; } }
  </style>
</head>
<body>
<div class="marchio" aria-hidden="true"><strong>plume</strong>CMS</div>
<main class="box" id="soccorso" data-caso="<?= $h($s['caso']) ?>">
  <h1><?= $h($titolo) ?></h1>
  <?php if ($s['caso'] === 'connessione'): ?>
  <div class="avviso" role="alert"><?php if ($porta): ?>Il sito non riesce a collegarsi al database <b><?= $h($db) ?></b> con l'utente e la password scritti nel file <code>includes/config.local.php</code> (utente e password non si mostrano).<?php else: ?>Il sito non riesce a collegarsi al database con i dati scritti nel file <code>includes/config.local.php</code> (nome del database, utente e password).<?php endif; ?></div>
  <p>Finché il database non risponde, né il sito né il pannello possono aprirsi. Nessun dato è stato toccato.</p>
  <h2>Cosa fare</h2>
  <ol>
    <li>Nel pannello del tuo hosting (la voce dei database MySQL) controlla che il database <?= $porta ? '<b>' . $h($db) . '</b>' : 'scritto nel file (riga <code>DB_NAME</code>)' ?> esista e che l'utente scritto nel file (riga <code>DB_USER</code>) ne abbia l'accesso.</li>
    <li>Se hai spostato il sito o cambiato la password del database, con il gestore dei file dell'hosting apri <code>includes/config.local.php</code> e riscrivi le righe <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> e <code>DB_PASS</code> con i dati giusti.</li>
    <li>Se il database è fermo per una manutenzione dell'hosting, aspetta qualche minuto e ricarica questa pagina.</li>
  </ol>
  <?php if ($porta): ?>
  <p class="tec">(Risposta del server: codice <?= (int)$s['codice'] ?>, <?= $h(mb_substr(soccorso_messaggio_coperto((string)$s['messaggio']), 0, 200)) ?>; l'utente e l'host del database sono coperti)</p>
  <?php else: ?>
  <p class="tec">Il nome del database e la risposta tecnica del server si mostrano qui solo con il file <code>soccorso.txt</code> (vedi sotto): questa pagina la vede anche chi non è entrato nel pannello.</p>
  <?php endif; ?>
  <?php else: ?>
  <div class="avviso" role="alert">
    <?php if ($s['caso'] === 'tabelle' && $porta): ?>
      Il database <b><?= $h($db) ?></b> non ha le tabelle del sito con il prefisso <b><?= $h($pref) ?></b>: manca la tabella degli utenti (<code><?= $h($pref) ?>users</code>)<?= empty($s['settings']) ? ' e quella delle impostazioni (<code>' . $h($pref) . 'settings</code>)' : '' ?>. Senza utenti nessuno può entrare nel pannello.
    <?php elseif ($s['caso'] === 'tabelle'): ?>
      Nel database del sito manca la tabella degli utenti<?= empty($s['settings']) ? ' e quella delle impostazioni' : '' ?>. Senza utenti nessuno può entrare nel pannello.
    <?php elseif ($porta): ?>
      Nel database <b><?= $h($db) ?></b> manca la tabella delle impostazioni del sito (<code><?= $h($pref) ?>settings</code>): il pannello non può partire.
    <?php else: ?>
      Nel database del sito manca la tabella delle impostazioni: il pannello non può partire.
    <?php endif; ?>
  </div>
  <?php if ($porta && !empty($s['altri'])): ?>
  <div class="info" role="status">Nel database ci sono tabelle di <?= $h(CMS_NOME) ?> con un altro prefisso: <b><?= $h(implode(', ', array_map(fn($q) => $q === '' ? '(nessun prefisso)' : $q, $s['altri']))) ?></b>. Forse il file <code>includes/config.local.php</code> dice il prefisso sbagliato: controlla la riga <code>define('CMS_PREFISSO', ...)</code>. Se è così, basta correggerla.</div>
  <?php endif; ?>
  <p>Succede quando un ripristino o un'importazione del database si è interrotto a metà, o quando il database è stato svuotato. Il sito pubblico intanto risponde "Torno subito". Il rimedio è rimettere il database da una copia: ci vogliono pochi minuti.</p>
  <h2>Rimettere il sito da una copia</h2>
  <ol id="soccorso-passi">
    <li>Con il gestore dei file del tuo hosting (o un programma FTP) apri la cartella del sito, poi <code>aggiornamenti/copie/</code>. Scarica la copia più recente che ha <b>prima-del-ripristino</b> nel nome: è quella che il pannello fa da solo prima di ogni ripristino, con il sito com'era. Se non c'è, prendi la copia più recente che hai (anche sul tuo computer), purché fatta su questo hosting: una copia fatta su un altro hosting può essere rifiutata da phpMyAdmin (il suo database è diverso e non conosce le collazioni di questo); quella si ripristina dal pannello, <b>Impostazioni &gt; Copia del sito</b>, che la adatta da solo, dopo che il pannello è ripartito con una copia di questo server.</li>
    <li>Sul computer apri lo zip ed estrai il file <code>db.sql.gz</code>.</li>
    <li>In phpMyAdmin (dal pannello dell'hosting) scegli a sinistra il database <?= $porta ? '<b>' . $h($db) . '</b>' : 'del sito (il nome è nella riga <code>DB_NAME</code> del file <code>includes/config.local.php</code>; con il file <code>soccorso.txt</code> lo vedi anche qui)' ?>, apri la scheda <b>Importa</b>, scegli il file <code>db.sql.gz</code> e premi <b>Esegui</b>. Il file ha già dentro le istruzioni per sostituire le tabelle; phpMyAdmin legge i file <code>.gz</code> da solo (se lo rifiuta, decomprimi prima il file e carica <code>db.sql</code>).</li>
    <li>Torna qui e ricarica la pagina: deve comparire l'accesso al pannello, con gli utenti di quella copia.</li>
    <li>Se la copia aveva anche immagini, temi o moduli diversi, dal pannello (<b>Impostazioni &gt; Copia del sito</b>) ripristina la stessa copia: rimette anche i file.</li>
  </ol>
  <?php endif; ?>
  <h2>Le copie sul server</h2>
  <?php if ($stato === 'scaduto'): ?>
  <p id="soccorso-scaduto">Il file <code>aggiornamenti/soccorso.txt</code> c'è ma ha più di <?= (int)SOCCORSO_PORTA_ORE ?> ore: la pagina non lo considera più (un file dimenticato non deve restare una porta aperta). Se ti serve ancora, cancellalo e crealo di nuovo con il gestore dei file, poi ricarica; a lavoro finito cancellalo.</p>
  <?php elseif (!$porta): ?>
  <p>Per vedere qui il nome del database e il prefisso delle tabelle del sito (servono in phpMyAdmin), l'elenco delle copie che stanno sul server (nome, data, peso, da quale sito vengono) e le prime righe dell'ultimo registro di ripristino, crea con il gestore dei file dell'hosting un file vuoto chiamato <code>soccorso.txt</code> nella cartella <code>aggiornamenti/</code> del sito (quella con dentro <code>copie/</code>) e ricarica questa pagina. L'utente del database e la password non si mostrano mai. Il file vale <?= (int)SOCCORSO_PORTA_ORE ?> ore e si cancella da solo quando il pannello riparte; finché c'è, quei dati li vede chiunque apra questa pagina: a lavoro finito cancellalo tu. Da questa pagina le copie non si scaricano e non si ripristinano: si prendono con il gestore dei file.</p>
  <?php elseif (!$copie): ?>
  <p>Nella cartella <code>aggiornamenti/copie/</code> non c'è nessuna copia. Usa una copia salvata sul tuo computer.</p>
  <?php else: ?>
  <p>Queste sono le copie nella cartella <code>aggiornamenti/copie/</code> del sito (si prendono con il gestore dei file dell'hosting):</p>
  <table id="soccorso-copie">
    <thead><tr><th>Copia</th><th>Fatta il</th><th>Cosa</th><th>Peso</th><th>Dal sito</th></tr></thead>
    <tbody>
    <?php foreach ($copie as $c): ?>
      <tr><td><?= $h($c['file']) ?></td><td class="fisso"><?= $h(date('d/m/Y H:i', $c['data'])) ?></td><td><?= $c['valida'] ? $h($c['etichetta'] . ($c['tabelle'] ? ', ' . $c['tabelle'] . ' tabelle' : '')) : 'non si legge' ?></td><td class="fisso"><?= $h(function_exists('agg_fmt_bytes') ? agg_fmt_bytes($c['peso']) : (string)$c['peso']) ?></td><td><?= $h($c['sito']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($registro): ?>
  <h2>L'ultimo ripristino</h2>
  <p>Dalla copia <code><?= $h($registro['copia']) ?></code>, il <?= $h(date('d/m/Y H:i', $registro['data'])) ?>: <b><?= $registro['ok'] ? 'riuscito' : 'con errori' ?></b><?= count($registro['righe']) > SOCCORSO_REGISTRO_RIGHE ? ' (le prime ' . (int)SOCCORSO_REGISTRO_RIGHE . ' righe di ' . count($registro['righe']) . '; il registro intero è in <code>aggiornamenti/copie/</code>)' : '' ?>.</p>
  <pre class="registro"><?= $h(implode("\n", array_slice($registro['righe'], 0, SOCCORSO_REGISTRO_RIGHE))) ?></pre>
  <?php endif; ?>
  <p id="soccorso-ricorda" class="tec">A lavoro finito cancella il file <code>aggiornamenti/soccorso.txt</code> (il pannello lo toglie da solo appena riparte; vale comunque <?= (int)SOCCORSO_PORTA_ORE ?> ore).</p>
  <?php endif; ?>
</main>
</body>
</html>
<?php
}
