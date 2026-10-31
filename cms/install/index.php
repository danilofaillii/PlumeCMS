<?php
/**
 * L'installatore del CMS: si apre da solo finché non esiste includes/config.local.php.
 * Cinque passi: requisiti, database, sito e amministratore, installazione, fine. Solo PHP, niente librerie.
 * Quando l'installazione è finita scrive install/.installato e da quel momento questa pagina rimanda al pannello.
 */
define('CMS_INSTALLER', true);
define('PUBLISHER_ERRORI', 'installer');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@mkdir(dirname(__DIR__) . '/logs', 0755, true);
ini_set('error_log', dirname(__DIR__) . '/logs/php-errori.log');
mb_internal_encoding('UTF-8');

$radice = dirname(__DIR__);
// Il config.local.php, se c'è, viene prima di cms.php: così il prefisso e le altre costanti sono quelle scritte dall'installatore.
if (is_file($radice . '/includes/config.local.php')) require_once $radice . '/includes/config.local.php';
require_once $radice . '/includes/cms.php';
require_once $radice . '/includes/sicurezza-password.php'; // le stesse regole della password del pannello
require_once $radice . '/includes/kit-partenza.php'; // 1.3.0: "Che sito vuoi fare?" (i kit di partenza)

session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
$_SESSION['inst'] ??= [];
$_SESSION['inst_csrf'] ??= bin2hex(random_bytes(16));
$d =& $_SESSION['inst'];
$passo = max(1, min(5, (int)($_GET['passo'] ?? 1)));
$errori = [];

// Già installato: via al pannello. Fa eccezione la pagina finale di chi ha appena installato (la sessione lo sa).
if (is_file(__DIR__ . '/.installato') && cms_installato() && !($passo === 5 && !empty($d['fatto']))) {
    header('Location: ' . cms_base() . '/admin/', true, 302);
    exit;
}
if (cms_installato() && !is_file(__DIR__ . '/.installato') && empty($d['scritto'])) {
    // Un config.local.php c'è già (installazione a mano o un sito che esiste): l'installatore non lo tocca.
    inst_pagina('Il CMS è già configurato', '<p>Esiste già <code>includes/config.local.php</code>: questa installazione è stata fatta a mano, oppure il sito è già in funzione. L\'installatore non riscrive niente.</p><p><a class="btn" href="' . ih(cms_base()) . '/admin/">Vai al pannello</a></p>', 0);
}

function ih($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function inst_csrf_ok(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals((string)$_SESSION['inst_csrf'], (string)($_POST['_csrf'] ?? ''));
}

/** L'indirizzo del sito (senza la cartella del CMS), rilevato dalla richiesta. */
function inst_url_rilevato(): string {
    // Schema, host e cartella del CMS: https://esempio.it/test se il CMS sta in /test (26 ottobre 2026: prima mancava la cartella).
    return cms_origine() . cms_base();
}

/** I requisiti: ognuno con esito e spiegazione in italiano semplice. */
function inst_requisiti(string $radice): array {
    $r = [];
    $r[] = ['PHP 8.2 o più recente (hai ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.2.0', '>='), 'Chiedi al tuo hosting di passare a PHP 8.2, 8.3 o 8.4.'];
    foreach (['pdo_mysql' => 'PDO MySQL (il database)', 'mbstring' => 'mbstring (testi con accenti)', 'openssl' => 'openssl (sicurezza)', 'json' => 'json'] as $ext => $nome) {
        $r[] = [$nome, extension_loaded($ext), "L'estensione PHP $ext non è attiva: si accende da cPanel, voce Select PHP Version."];
    }
    $r[] = ['gd oppure imagick (immagini)', extension_loaded('gd') || extension_loaded('imagick'), 'Senza, le immagini si caricano lo stesso ma non si ridimensionano.', true];
    $r[] = ['zip (pacchetti di aggiornamento)', class_exists('ZipArchive'), 'Senza, il pannello Aggiornamenti non apre i pacchetti.', true];
    foreach (['uploads', 'includes', 'aggiornamenti', 'logs'] as $c) {
        $p = $radice . '/' . $c;
        if (!is_dir($p)) @mkdir($p, 0755, true);
        $r[] = ["Cartella $c/ scrivibile", is_dir($p) && is_writable($p), "Dai i permessi di scrittura (755 o 775) alla cartella $c/ dal gestore file di cPanel."];
    }
    return $r;
}

/** Connessione al database con i dati del passo 2. Con $senzaDb true si collega al server, senza scegliere il database. */
function inst_pdo(array $db, bool $senzaDb = false): PDO {
    $host = $db['host']; $porta = '';
    if (preg_match('/^(.+):(\d+)$/', $host, $m)) { $host = $m[1]; $porta = ';port=' . $m[2]; }
    $dsn = 'mysql:host=' . $host . $porta . ($senzaDb ? '' : ';dbname=' . $db['nome']) . ';charset=utf8mb4';
    return new PDO($dsn, $db['utente'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
}

function inst_pagina(string $titolo, string $corpo, int $passo): void {
    $base = cms_base();
    $passi = ['Requisiti', 'Database', 'Sito e amministratore', 'Installazione', 'Fine'];
    $barra = '';
    foreach ($passi as $i => $n) {
        $k = $i + 1;
        $barra .= '<li class="' . ($k < $passo ? 'fatto' : ($k === $passo ? 'attivo' : '')) . '"><span>' . $k . '</span> ' . ih($n) . '</li>';
    }
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . ih($titolo) . ' - ' . ih(CMS_NOME) . '</title>'
        . '<link rel="stylesheet" href="' . ih($base) . '/install/install.css"></head><body><div class="inst">'
        . '<header><h1>' . ih(CMS_NOME) . ' <small>versione ' . ih(cms_versione()) . '</small></h1><p>Installazione guidata</p></header>'
        . ($passo ? '<ol class="passi">' . $barra . '</ol>' : '')
        . '<main><h2>' . ih($titolo) . '</h2>' . $corpo . '</main>'
        . '<footer>Le credenziali restano in <code>includes/config.local.php</code>, sul tuo server. Niente parte verso l\'esterno.</footer></div></body></html>';
    exit;
}

function inst_errori(array $errori): string {
    if (!$errori) return '';
    return '<div class="errore"><ul><li>' . implode('</li><li>', array_map('ih', $errori)) . '</li></ul></div>';
}

function inst_campo(string $nome, string $etichetta, string $valore, string $tipo = 'text', string $aiuto = '', bool $obbl = true): string {
    return '<div class="campo"><label for="' . $nome . '">' . ih($etichetta) . '</label><input type="' . $tipo . '" id="' . $nome . '" name="' . $nome . '" value="' . ih($valore) . '"' . ($obbl ? ' required' : '') . ($tipo === 'password' ? ' autocomplete="new-password"' : '') . '>'
        . ($aiuto !== '' ? '<small>' . ih($aiuto) . '</small>' : '') . '</div>';
}

$csrf = '<input type="hidden" name="_csrf" value="' . ih($_SESSION['inst_csrf']) . '">';

// ------------------------------------------------------------------
// Passo 1: requisiti
// ------------------------------------------------------------------
if ($passo === 1) {
    $req = inst_requisiti($radice);
    $bloccanti = array_filter($req, fn($r) => !$r[1] && empty($r[3]));
    $righe = '';
    foreach ($req as $r) {
        $righe .= '<li class="' . ($r[1] ? 'ok' : (empty($r[3]) ? 'no' : 'avviso')) . '"><b>' . ($r[1] ? 'OK' : (empty($r[3]) ? 'Manca' : 'Avviso')) . '</b> ' . ih($r[0]) . (!$r[1] ? '<small>' . ih($r[2]) . '</small>' : '') . '</li>';
    }
    inst_pagina('Requisiti', '<p>Prima di cominciare, un controllo di quello che serve sul server.</p><ul class="requisiti">' . $righe . '</ul>'
        . ($bloccanti ? '<p class="nota">Sistema i punti segnati con <b>Manca</b> e poi <a href="?passo=1">ricontrolla</a>.</p>' : '<p><a class="btn" href="?passo=2">Avanti: il database</a></p>'), 1);
}

// ------------------------------------------------------------------
// Passo 2: database
// ------------------------------------------------------------------
if ($passo === 2) {
    $db = $d['db'] ?? ['host' => 'localhost', 'nome' => '', 'utente' => '', 'password' => '', 'prefisso' => 'cms_', 'crea' => 0];
    if (inst_csrf_ok()) {
        $db = [
            'host' => trim((string)($_POST['host'] ?? 'localhost')) ?: 'localhost', 'nome' => trim((string)($_POST['nome'] ?? '')),
            'utente' => trim((string)($_POST['utente'] ?? '')), 'password' => (string)($_POST['password'] ?? ''),
            'prefisso' => strtolower(trim((string)($_POST['prefisso'] ?? 'cms_'))) ?: 'cms_', 'crea' => !empty($_POST['crea']) ? 1 : 0,
        ];
        if (!preg_match('/^[a-z0-9_]{1,20}$/', $db['prefisso'])) $errori[] = 'Il prefisso può avere solo lettere minuscole, numeri e trattino basso (per esempio cms_).';
        if (!str_ends_with($db['prefisso'], '_')) $db['prefisso'] .= '_';
        if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $db['nome'])) $errori[] = 'Il nome del database può avere solo lettere, numeri, trattino e trattino basso.';
        if ($db['utente'] === '') $errori[] = "Serve l'utente del database.";
        if (!$errori) {
            try {
                inst_pdo($db);
            } catch (PDOException $e) {
                $codice = (int)$e->getCode();
                if ($codice === 1049 && $db['crea']) {
                    try {
                        $pdo = inst_pdo($db, true);
                        $pdo->exec('CREATE DATABASE `' . str_replace('`', '', $db['nome']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                        inst_pdo($db);
                    } catch (PDOException $e2) {
                        $errori[] = 'Il database non esiste e non si riesce a crearlo: ' . $e2->getMessage() . ' Crealo da cPanel (Database MySQL) e dai all\'utente tutti i privilegi.';
                    }
                } elseif ($codice === 1049) {
                    $errori[] = "Il database «{$db['nome']}» non esiste. Crealo da cPanel oppure spunta «Crealo se manca».";
                } else {
                    $errori[] = 'Connessione rifiutata: ' . $e->getMessage();
                }
            }
        }
        if (!$errori) {
            $d['db'] = $db;
            header('Location: ?passo=3'); exit;
        }
    }
    inst_pagina('Database', '<p>I dati del database MySQL. Su cPanel li trovi (o li crei) nella voce <b>Database MySQL</b>: un database, un utente con tutti i privilegi su quel database.</p>'
        . inst_errori($errori) . '<form method="post">' . $csrf
        . inst_campo('host', 'Server (host)', $db['host'], 'text', 'Quasi sempre localhost. Con una porta diversa da quella standard: server:porta.')
        . inst_campo('nome', 'Nome del database', $db['nome'])
        . inst_campo('utente', 'Utente', $db['utente'])
        . inst_campo('password', 'Password', $db['password'], 'password', '', false)
        . inst_campo('prefisso', 'Prefisso delle tabelle', $db['prefisso'], 'text', 'Le tabelle si chiameranno prefisso_articles, prefisso_pages... Utile se nel database c\'è già altro.')
        . '<div class="campo spunta"><label><input type="checkbox" name="crea" value="1"' . ($db['crea'] ? ' checked' : '') . '> Crealo se manca (serve un utente con il permesso di creare database)</label></div>'
        . '<p><a class="btn-filo" href="?passo=1">Indietro</a> <button class="btn" type="submit">Prova la connessione e vai avanti</button></p></form>', 2);
}

// ------------------------------------------------------------------
// Passo 3: sito e amministratore
// ------------------------------------------------------------------
if ($passo === 3) {
    if (empty($d['db'])) { header('Location: ?passo=2'); exit; }
    $s = $d['sito'] ?? ['nome' => '', 'descrizione' => '', 'url' => inst_url_rilevato(), 'lingua' => 'it', 'fuso' => 'Europe/Rome', 'email' => '',
        'admin_nome' => 'admin', 'admin_email' => '', 'admin_password' => ''];
    if (inst_csrf_ok()) {
        $s = [
            'nome' => trim((string)($_POST['nome'] ?? '')), 'descrizione' => trim((string)($_POST['descrizione'] ?? '')),
            'url' => rtrim(trim((string)($_POST['url'] ?? '')), '/'), 'lingua' => preg_match('/^[a-z]{2}$/', (string)($_POST['lingua'] ?? '')) ? $_POST['lingua'] : 'it',
            'fuso' => in_array($_POST['fuso'] ?? '', timezone_identifiers_list(), true) ? $_POST['fuso'] : 'Europe/Rome',
            'email' => trim((string)($_POST['email'] ?? '')),
            'admin_nome' => trim((string)($_POST['admin_nome'] ?? '')), 'admin_email' => trim((string)($_POST['admin_email'] ?? '')), 'admin_password' => (string)($_POST['admin_password'] ?? ''),
        ];
        if ($s['nome'] === '') $errori[] = 'Serve il nome del sito.';
        if (!preg_match('~^https?://[^/\s?#]+(/[^\s?#]*[^/\s?#])?$~', $s['url'])) $errori[] = "L'indirizzo del sito deve essere come https://www.esempio.it oppure https://www.esempio.it/cartella, senza barra finale.";
        if ($s['email'] !== '' && !filter_var($s['email'], FILTER_VALIDATE_EMAIL)) $errori[] = "L'email del sito non è valida.";
        if (!preg_match('/^[A-Za-z0-9._-]{3,100}$/', $s['admin_nome'])) $errori[] = "Il nome utente dell'amministratore: almeno 3 caratteri, solo lettere, numeri, punto, trattino.";
        if (!filter_var($s['admin_email'], FILTER_VALIDATE_EMAIL)) $errori[] = "L'email dell'amministratore non è valida (serve per recuperare la password).";
        // sicurezza (attacco 3): come a regime, 12 caratteri, diversa dal nome e dall'email, non fra le più comuni.
        if (($errPw = sicurezza_password_errore($s['admin_password'], $s['admin_nome'], $s['admin_email'])) !== '') $errori[] = $errPw;
        if ((string)($_POST['admin_password2'] ?? '') !== $s['admin_password']) $errori[] = 'Le due password non coincidono.';
        if (!$errori) {
            $d['sito'] = $s;
            header('Location: ?passo=4'); exit;
        }
    }
    $fusi = '';
    foreach (['Europe/Rome', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Madrid', 'Europe/Zurich', 'UTC', 'America/New_York', 'America/Sao_Paulo'] as $f) $fusi .= '<option value="' . $f . '"' . ($s['fuso'] === $f ? ' selected' : '') . '>' . $f . '</option>';
    inst_pagina('Sito e amministratore', inst_errori($errori) . '<form method="post">' . $csrf
        . '<h3>Il sito</h3>'
        . inst_campo('nome', 'Nome del sito', $s['nome'], 'text', 'Compare nella testata e nei titoli delle pagine.')
        . inst_campo('descrizione', 'Descrizione in una riga', $s['descrizione'], 'text', 'Per i motori di ricerca e per la home di partenza.', false)
        . inst_campo('url', 'Indirizzo del sito', $s['url'], 'url', 'Rilevato dalla richiesta: cambialo se il sito risponderà a un altro indirizzo.')
        . '<div class="due">' . inst_campo('lingua', 'Lingua (codice)', $s['lingua'], 'text', 'it, en, fr...')
        . '<div class="campo"><label for="fuso">Fuso orario</label><select id="fuso" name="fuso">' . $fusi . '</select><small>Le date e ore di uscita degli articoli.</small></div></div>'
        . inst_campo('email', 'Email del sito', $s['email'], 'email', 'Da cui partono gli avvisi e le email ai lettori (si può cambiare dopo, in Impostazioni).', false)
        . '<h3>L\'amministratore</h3>'
        . inst_campo('admin_nome', 'Nome utente', $s['admin_nome'])
        . inst_campo('admin_email', 'Email', $s['admin_email'], 'email')
        . '<div class="due">' . inst_campo('admin_password', 'Password (almeno 12 caratteri, non fra le più comuni)', '', 'password') . inst_campo('admin_password2', 'Ripeti la password', '', 'password') . '</div>'
        . '<p><a class="btn-filo" href="?passo=2">Indietro</a> <button class="btn" type="submit">Avanti: installa</button></p></form>', 3);
}

// ------------------------------------------------------------------
// Passo 4: installazione
// ------------------------------------------------------------------
if ($passo === 4) {
    if (empty($d['db']) || empty($d['sito'])) { header('Location: ?passo=2'); exit; }
    $esegui = !empty($_GET['esegui']) && !empty($d['scritto']);
    $kit = kit_elenco();
    if (!inst_csrf_ok() && !$esegui) {
        $s = $d['sito']; $db = $d['db'];
        // 1.3.0: il kit di partenza. Vuoto (il sito di sempre) e scelto se non si tocca niente.
        $scelto = isset($kit[$d['kit'] ?? '']) ? $d['kit'] : 'vuoto';
        $carte = '';
        foreach ($kit as $id => $k) {
            $carte .= '<label class="kit kit-' . ih($id) . '"><input type="radio" name="kit" value="' . ih($id) . '"' . ($id === $scelto ? ' checked' : '') . '>'
                . '<span class="kit-disegno" aria-hidden="true"><i></i><i></i><i></i><i></i></span><b>' . ih($k['nome']) . '</b><small>' . ih($k['descrizione']) . '</small></label>';
        }
        inst_pagina('Installazione', '<form method="post">' . $csrf
            . '<h3>Che sito vuoi fare?</h3><p class="nota-kit">Il CMS parte con la home, le pagine principali e il menu già fatti con l\'editor, e testi d\'esempio che dicono cosa scriverci. Si cambia tutto dopo, una sezione alla volta.</p>'
            . '<div class="kit-scelta">' . $carte . '</div>'
            . '<h3>Cosa succede premendo il pulsante</h3><ul class="elenco">'
            . '<li>si scrive <code>includes/config.local.php</code> con i dati del database (<b>' . ih($db['nome']) . '</b>, prefisso <b>' . ih($db['prefisso']) . '</b>) e una chiave segreta generata adesso;</li>'
            . '<li>si creano le tabelle del CMS' . (cms_moduli() ? ' e dei moduli (' . ih(implode(', ', array_keys(cms_moduli()))) . ')' : '') . ';</li>'
            . '<li>si crea l\'amministratore <b>' . ih($s['admin_nome']) . '</b>;</li>'
            . '<li>si preparano i contenuti di partenza del sito scelto: la home nell\'editor, le pagine, un form dei contatti, un articolo di benvenuto, header e footer con il menu Principale, lo stile predefinito, sitemap e robots.txt.</li></ul>'
            . '<p><a class="btn-filo" href="?passo=3">Indietro</a> <button class="btn" type="submit">Installa adesso</button></p></form>', 4);
    }
    if (!$esegui) $d['kit'] = isset($kit[(string)($_POST['kit'] ?? '')]) ? (string)$_POST['kit'] : 'vuoto';
    // 1.7.0: il sito su misura. Scelta la carta, prima di installare si risponde alle quattro domande (una pagina in più
    // dentro il passo 4); le risposte restano nella sessione e il kit le legge quando costruisce il sito.
    if (!$esegui && $d['kit'] === 'su-misura' && empty($_POST['sm'])) {
        require_once $radice . '/includes/sito-su-misura.php';
        inst_pagina('Il tuo sito su misura', '<form method="post" class="sm">' . $csrf . '<input type="hidden" name="kit" value="su-misura"><input type="hidden" name="sm" value="1">'
            . '<p class="nota-kit">Quattro domande e il sito si compone da solo: home, pagine, menu e testi pensati per la tua attività, con foto finte che dicono cosa metterci. Le sostituisci dal pannello, in Media, quando hai le tue.</p>'
            . su_misura_campi_html($d['su_misura'] ?? su_misura_risposte_vuote(), 'ih')
            . '<p><a class="btn-filo" href="?passo=4">Indietro</a> <button class="btn" type="submit">Installa adesso</button></p></form>', 4);
    }
    if (!$esegui && $d['kit'] === 'su-misura') {
        require_once $radice . '/includes/sito-su-misura.php';
        $d['su_misura'] = su_misura_pulisci($_POST);
    }
    $s = $d['sito']; $db = $d['db'];
    $log = $d['log'] ?? [];
    try {
      if (!$esegui) {
        // 1. config.local.php. Poi si ricomincia con una richiesta nuova, così PHP legge le costanti appena scritte
        // (il prefisso delle tabelle prima di tutto) e niente resta definito con i valori di partenza.
        $segreto = bin2hex(random_bytes(32));
        $q = fn(string $v) => var_export($v, true);
        $config = "<?php\n// Scritto dall'installatore di " . CMS_NOME . " il " . date('Y-m-d H:i') . ". Credenziali del database: non va nel repository.\n"
            . "define('DB_HOST', {$q(preg_match('/^(.+):(\d+)$/', $db['host'], $mh) ? $mh[1] . ';port=' . $mh[2] : $db['host'])});\ndefine('DB_NAME', {$q($db['nome'])});\ndefine('DB_USER', {$q($db['utente'])});\ndefine('DB_PASS', {$q($db['password'])});\n"
            . "define('DB_CHARSET', 'utf8mb4');\ndefine('CMS_PREFISSO', {$q($db['prefisso'])});\n"
            . "define('CMS_SEGRETO', {$q($segreto)});\n"
            . "define('CMS_FUSO', {$q($s['fuso'])});\n"
            . (($inst_cartella = rtrim((string)parse_url($s['url'], PHP_URL_PATH), '/')) !== ''
                ? "define('CMS_BASE', {$q($inst_cartella)});   // la cartella del sito, dall'indirizzo scelto nell'installazione\n"
                : "// define('CMS_BASE', '/sottocartella');   // solo se il rilevamento automatico sbaglia\n")
            . "// define('CMS_UPLOADS', __DIR__ . '/../uploads'); define('CMS_UPLOADS_URL', '/uploads');   // per spostare le immagini\n";
        $fileConfig = $radice . '/includes/config.local.php';
        if (file_put_contents($fileConfig, $config) === false) throw new RuntimeException("Non riesco a scrivere $fileConfig: controlla i permessi della cartella includes/.");
        $log[] = 'includes/config.local.php scritto';
        $d['scritto'] = true; $d['log'] = $log;
        header('Location: ?passo=4&esegui=1'); exit;
      }
        // 2. Il CMS con il suo config (letto in testa a questa pagina).
        require_once $radice . '/includes/config.php';
        require_once $radice . '/includes/db.php';
        require_once $radice . '/includes/functions.php';
        require_once $radice . '/includes/schema-base.php';
        foreach (cms_installa_tutto() as $r) $log[] = $r;

        // 3. Impostazioni del sito.
        foreach (['sito_nome' => $s['nome'], 'sito_titolo' => $s['nome'], 'sito_descrizione' => $s['descrizione'], 'sito_url' => $s['url'], 'sito_lingua' => $s['lingua'],
            'autore_predefinito' => $s['nome'], 'pagina_blog' => '/blog/', 'blog_nome' => 'Blog', 'blog_occhiello' => 'Il blog', 'blog_titolo' => 'Gli articoli',
            'pagina_home' => 'home', 'home_descrizione' => $s['descrizione'], 'cms_versione_installata' => cms_versione()] as $k => $v) save_setting($k, (string)$v);
        if ($s['email'] !== '') save_setting('posta_smtp', json_encode(['host' => '', 'porta' => 465, 'sicurezza' => 'ssl', 'utente' => '', 'password' => '', 'mittente' => $s['email'], 'nome' => $s['nome']]));
        $log[] = 'impostazioni del sito';

        // 4. Amministratore.
        $n = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('users'))->fetchColumn();
        if ($n === 0) {
            db()->prepare('INSERT INTO ' . tbl('users') . ' (username, email, password_hash, display_name, role) VALUES (?, ?, ?, ?, ?)')
                ->execute([$s['admin_nome'], $s['admin_email'], sicurezza_password_hash($s['admin_password']), $s['nome'], 'admin']);
            $log[] = 'amministratore ' . $s['admin_nome'];
        } else {
            $log[] = "utenti già presenti ($n): l'amministratore non è stato creato";
        }

        // 5. Contenuti di partenza (solo su un database vuoto di pagine).
        require_once $radice . '/includes/sito.php';
        require_once $radice . '/includes/contenuti.php';
        require_once $radice . '/includes/editor.php';
        require_once $radice . '/includes/form.php';
        require_once $radice . '/includes/menu-nav.php';
        require_once $radice . '/includes/modelli.php';
        if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('pages'))->fetchColumn() === 0) {
            $autore = $s['nome'];
            // Oggi alle 0:00, già passato: con le 6:00 chi installava fra mezzanotte e l'alba trovava il sito vuoto (fino alla 1.2.7).
            $oggi = date('Y-m-d');
            // 1.3.0: il kit scelto al passo 4 (Vuoto e il sito di sempre). Dalla 1.4.0 lo costruisce kit_costruisci(),
            // la stessa funzione del cambio di tipo di sito dalla dashboard, e cio che crea entra nel registro kit_prova.
            $kitId = (string)($d['kit'] ?? 'vuoto');
            if ($kitId === 'su-misura') {
                // 1.7.0: le risposte prima del kit, che le legge dalle impostazioni (le stesse che riapre il pannello).
                require_once $radice . '/includes/sito-su-misura.php';
                su_misura_salva_risposte($d['su_misura'] ?? su_misura_risposte_vuote());
            }
            if ($kitId !== 'vuoto' && !kit_leggi($kitId)) $kitId = 'vuoto';
            // Menu e modelli (header e footer) di partenza prima dei contenuti: il kit scrive le sue voci nel menu Principale.
            menu_installa(); modelli_installa();
            if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('menu'))->fetchColumn() === 0) menu_crea_di_partenza();
            if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('modelli'))->fetchColumn() === 0) modelli_crea_di_partenza();
            $costruito = kit_costruisci($kitId, $s['nome'], $s['descrizione'], $autore, ['oggi' => $oggi]);
            foreach ($costruito['log'] as $r) $log[] = $r;
            // 1.4.0: il tipo di sito resta "in prova" finché non si preme Definitivo nella dashboard; e i primi passi.
            kit_prova_inizia($kitId, $costruito['creati']);
            save_setting('primi_passi', 'mostra');
            $log[] = 'menu Principale, header e footer';
            if (function_exists('design_installa')) { design_installa(); $log[] = 'stile predefinito'; }
            // temi: temi/.htaccess (dal web nella cartella dei temi non si esegue niente).
            require_once dirname(__DIR__) . '/includes/temi.php';
            if (temi_proteggi()) $log[] = 'protezione della cartella dei temi';
        } else {
            $log[] = 'pagine già presenti: nessun contenuto di partenza';
        }
        // sitemap.xml e robots.txt li genera il sito a ogni richiesta (sito/extra.php): niente da scrivere.
        file_put_contents(__DIR__ . '/.installato', date('c') . "\n");
        $d['fatto'] = true; $d['log'] = $log; unset($d['scritto']);
        unset($d['db']['password'], $d['sito']['admin_password']);
        header('Location: ?passo=5'); exit;
    } catch (Throwable $e) {
        error_log('installatore: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        // Un'installazione fallita a metà non lascia un config.local.php che bloccherebbe l'installatore.
        if (is_file($radice . '/includes/config.local.php') && !is_file(__DIR__ . '/.installato')) @unlink($radice . '/includes/config.local.php');
        unset($d['scritto']);
        inst_pagina('Qualcosa non ha funzionato', '<div class="errore"><p>' . ih($e->getMessage()) . '</p></div>'
            . '<p>Fin qui era andato tutto bene:</p><ul class="elenco"><li>' . implode('</li><li>', array_map('ih', $log ?: ['niente'])) . '</li></ul>'
            . '<p>Il dettaglio tecnico è in <code>logs/php-errori.log</code>. Puoi correggere e <a class="btn" href="?passo=2">riprovare dal database</a>.</p>', 4);
    }
}

// ------------------------------------------------------------------
// Passo 5: fine
// ------------------------------------------------------------------
if ($passo === 5) {
    if (empty($d['fatto'])) { header('Location: ?passo=1'); exit; }
    $base = cms_base();
    $sitoUrl = rtrim((string)($d['sito']['url'] ?? ''), '/');
    inst_pagina('Installazione completata', '<p class="ok-grande">Il sito è pronto.</p>'
        . '<p><a class="btn" href="' . ih($base) . '/admin/login.php">Entra nel pannello</a> <a class="btn-filo" href="' . ih($sitoUrl ?: $base) . '/" target="_blank">Apri il sito</a></p>'
        . '<h3>Cosa è stato fatto</h3><ul class="elenco piccolo"><li>' . implode('</li><li>', array_map('ih', $d['log'] ?? [])) . '</li></ul>'
        . '<h3>Da sapere</h3><ul class="elenco"><li>La cartella <code>install/</code> ora rimanda al pannello. Puoi anche cancellarla.</li>'
        . '<li>Il cron: una volta al giorno <code>php ' . ih($radice) . '/cron-daily.php</code> (alle 06:00) e ogni 5 minuti <code>php ' . ih($radice) . '/cron-newsletter.php</code>. Si impostano da cPanel, voce Cron Jobs.</li>'
        . '<li>Le email: in <b>Impostazioni, Configurazione email</b> metti il server SMTP del tuo hosting, così confermi iscrizioni e ricevi gli avvisi.</li></ul>', 5);
}
