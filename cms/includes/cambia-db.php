<?php
/**
 * Cambia database (2.37.0, richiesta di Danilo: "migrare tutto il db su un altro DB e a fine copia utilizzare il nuovo DB").
 * Pannello: admin/cambia-db.php (Impostazioni > Cambia database), solo per l'amministratore. Manuale tecnico, capitolo 48.
 *
 * Come funziona:
 *   1. Prova la connessione (cambiadb_prova()): si collega al database nuovo con un tempo massimo breve, dice la versione del
 *      server, prova i permessi con una tabella e una vista di prova create e tolte, conta le tabelle che hanno già il prefisso
 *      scelto. Rifiuta lo stesso database di adesso (stesso server, stessa porta, stesso nome e stesso prefisso: si confronta
 *      quello che risponde il server, @@hostname, @@port e DATABASE(), non solo quello che si è scritto). Gli errori sono in
 *      parole semplici, senza il messaggio del server (che porta utente e host).
 *   2. La copia (cambiadb_avvia(), poi cambiadb_giro() a giri chiesti dalla pagina): le tabelle del prefisso del sito (le
 *      stesse della copia del sito, copia_tabelle_sito()), struttura e righe, nel database nuovo in TABELLE DI APPOGGIO
 *      (rip<6 esadecimali>_<prefisso nuovo><tabella>), con le difese del ripristino sicuro della 2.25.1: collazioni e set di
 *      caratteri adattati al server di arrivo (copia_create_portabile() con copia_collazioni_di()), colonne generate scritte
 *      DEFAULT (agg_riga_sql()), DEFAULT_GENERATED rispettato (agg_colonne_generate()), una istruzione per volta (connessione
 *      con le istruzioni impilate spente), INSERT a blocchi sotto COPIA_INSERT_MAX e sotto max_allowed_packet del server nuovo.
 *      Il database di adesso si legge e basta: non si tocca mai.
 *   3. Durante la copia il sito pubblico risponde 503 "Torno subito" con Retry-After (cambiadb_manutenzione_sito(), da
 *      includes/config.php) e il pannello mostra solo la pagina del cambio (cambiadb_pannello_blocca(), da auth.php). Se la
 *      pagina si chiude, dopo CAMBIADB_SCADENZA secondi senza un giro la manutenzione finisce da sola: il sito torna normale
 *      sul database di prima e la copia si può ricominciare o annullare.
 *   4. Alla fine si contano le righe, tabella per tabella, su tutti e due i database. Una tabella che non torna si ricopia (al
 *      più CAMBIADB_RIPROVE volte, poi ci si ferma senza cambiare niente). Poi UNA sola RENAME TABLE sul database nuovo mette le
 *      tabelle al loro nome (con "Sostituisci", quelle che c'erano con lo stesso prefisso vanno in parcheggio vec<...>_ e si
 *      cancellano solo a passaggio riuscito), e si rifanno le due viste degli articoli.
 *   5. Il passaggio (cambiadb_config_passa()): una copia del config di adesso, non leggibile dal web
 *      (includes/config.local.prima-<data>.php, che comincia con un'uscita immediata; la cartella includes/ è vietata dal web),
 *      poi il config nuovo con lo stesso formato dell'installatore (cambiano solo DB_HOST, DB_NAME, DB_USER, DB_PASS e
 *      CMS_PREFISSO), scritto in un file temporaneo e rinominato; poi una connessione con i valori riletti dal file e un
 *      controllo (users, settings, la versione del sito). Se il controllo fallisce si rimette il config di prima.
 *   6. Se il config non è scrivibile: niente passaggio da solo. La pagina mostra all'amministratore le righe da incollare con
 *      il gestore dei file dell'hosting e "Ho cambiato il file, controlla" (fase "manuale", manutenzione per CAMBIADB_SCADENZA_MANUALE).
 *   7. Il database di prima non si cancella mai. "Torna al database di prima" rimette il config salvato (cambiadb_torna()).
 *
 * Stato del lavoro: aggiornamenti/cambia-db/lavoro.json (cartella vietata dal web), con la password del database nuovo cifrata
 * (AES-256-GCM, chiave da CMS_SEGRETO). L'esito dell'ultimo cambio: aggiornamenti/cambia-db/ultimo.json, senza password.
 * La password non torna mai nella pagina, non va nei registri, negli indirizzi e nei messaggi.
 */

require_once __DIR__ . '/copia-sito.php';

/** Dopo quanti secondi senza un giro la manutenzione finisce da sola (la pagina chiusa). CMS_CAMBIADB_SCADENZA nel config per le prove. */
const CAMBIADB_SCADENZA = 600;
/** Quanto resta in manutenzione il sito mentre l'amministratore incolla le righe nel config (fase manuale). */
const CAMBIADB_SCADENZA_MANUALE = 3600;
/** Quanti secondi lavora un giro (poi la pagina chiede il giro dopo). CMS_CAMBIADB_GIRO nel config per le prove. */
const CAMBIADB_GIRO = 4;
/** Righe lette per volta da una tabella. */
const CAMBIADB_BLOCCO = 500;
/** Quante volte si ricopia una tabella con le righe che non tornano, prima di fermarsi. */
const CAMBIADB_RIPROVE = 3;
/** Prove della connessione ammesse per sessione in CAMBIADB_PROVE_FINESTRA secondi (il pannello non diventa uno scanner di porte). */
const CAMBIADB_PROVE_MAX = 10;
const CAMBIADB_PROVE_FINESTRA = 600;
/** Tempo massimo per collegarsi al database nuovo, in secondi. */
const CAMBIADB_TIMEOUT = 3;

/** Un errore da mostrare così com'è a chi usa il pannello (parole semplici, mai il messaggio del server o la password). */
class CambiaDbErrore extends RuntimeException {}

// ------------------------------------------------------------
// File di stato
// ------------------------------------------------------------

/** La cartella del cambio, dentro aggiornamenti/ (vietata al web). */
function cambiadb_dir(): string {
    $d = agg_dir('cambia-db');
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

function cambiadb_lavoro_file(): string { return dirname(__DIR__) . '/aggiornamenti/cambia-db/lavoro.json'; }
function cambiadb_ultimo_file(): string { return dirname(__DIR__) . '/aggiornamenti/cambia-db/ultimo.json'; }

/** Il lavoro in corso, o null. */
function cambiadb_lavoro(): ?array {
    $f = cambiadb_lavoro_file();
    if (!is_file($f)) return null;
    $l = json_decode((string)@file_get_contents($f), true);
    return is_array($l) && isset($l['fase'], $l['dest']) ? $l : null;
}

/** Scrive un file JSON con permessi 0600, attraverso un file temporaneo (un giro interrotto non lascia un file a metà). */
function cambiadb_scrivi_json(string $f, array $dati): void {
    cambiadb_dir();
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) throw new CambiaDbErrore('Non riesco a scrivere nella cartella aggiornamenti/cambia-db/ del sito (permessi della cartella, o disco pieno). Niente è stato toccato.');
    @chmod($tmp, 0600);
    if (!@rename($tmp, $f)) { @unlink($tmp); throw new CambiaDbErrore('Non riesco a scrivere nella cartella aggiornamenti/cambia-db/ del sito. Niente è stato toccato.'); }
}

function cambiadb_lavoro_scrivi(array $l): void { $l['aggiornato'] = time(); cambiadb_scrivi_json(cambiadb_lavoro_file(), $l); }
function cambiadb_lavoro_togli(): void { @unlink(cambiadb_lavoro_file()); }

/** L'esito dell'ultimo cambio (o del ritorno), o null. */
function cambiadb_ultimo(): ?array {
    $f = cambiadb_ultimo_file();
    if (!is_file($f)) return null;
    $u = json_decode((string)@file_get_contents($f), true);
    return is_array($u) ? $u : null;
}
function cambiadb_ultimo_scrivi(array $u): void { cambiadb_scrivi_json(cambiadb_ultimo_file(), $u); }

function cambiadb_scadenza(string $fase): int {
    if ($fase === 'manuale') return defined('CMS_CAMBIADB_SCADENZA_MANUALE') ? max(5, (int)CMS_CAMBIADB_SCADENZA_MANUALE) : CAMBIADB_SCADENZA_MANUALE;
    return defined('CMS_CAMBIADB_SCADENZA') ? max(5, (int)CMS_CAMBIADB_SCADENZA) : CAMBIADB_SCADENZA;
}

/** Il config di adesso indica già il database del lavoro (l'amministratore ha incollato le righe)? */
function cambiadb_config_e_dest(array $l): bool {
    $d = $l['dest'];
    return defined('DB_HOST') && defined('DB_NAME') && DB_HOST === cambiadb_host_config($d) && DB_NAME === $d['nome'] && (string)DB_USER === $d['utente'] && DB_PREFIX === $d['prefisso'];
}

/**
 * Il sito è in manutenzione per il cambio? Sì mentre la copia lavora (un giro da meno di CAMBIADB_SCADENZA secondi) e mentre
 * l'amministratore incolla le righe nel config (fase manuale). Una pagina chiusa non lascia il sito fermo per sempre.
 */
function cambiadb_manutenzione_attiva(?array $l = null): bool {
    $l = $l ?? cambiadb_lavoro();
    if (!$l || !in_array($l['fase'], ['copia', 'manuale'], true)) return false;
    if ($l['fase'] === 'manuale' && cambiadb_config_e_dest($l)) return false;
    return time() - (int)($l['aggiornato'] ?? 0) < cambiadb_scadenza((string)$l['fase']);
}

/** La copia si è fermata da sola (pagina chiusa da più di CAMBIADB_SCADENZA secondi)? */
function cambiadb_scaduto(array $l): bool {
    return in_array($l['fase'], ['copia', 'manuale'], true) && !cambiadb_manutenzione_attiva($l) && !($l['fase'] === 'manuale' && cambiadb_config_e_dest($l));
}

/**
 * Da includes/config.php (sito pubblico, API, cron), solo se aggiornamenti/cambia-db/lavoro.json esiste: durante la copia il
 * sito risponde 503 "Torno subito" con Retry-After, le API in JSON, i cron saltano il giro. Nessuna scrittura si perde.
 */
function cambiadb_manutenzione_sito(): void {
    if (!cambiadb_manutenzione_attiva()) return;
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Cambio del database in corso: questo giro salta.\n"); exit(0); }
    while (ob_get_level()) @ob_end_clean();
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $json = str_contains($script, '/api/') || str_contains($uri, '/api/') || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json');
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 120');
        header('Cache-Control: no-store');
        header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=utf-8');
    }
    if ($json) { echo json_encode(['errore' => 'Il sito si è fermato un momento. Riprova tra qualche minuto.'], JSON_UNESCAPED_UNICODE); exit; }
    // La stessa pagina di includes/errori.php (errori_rispondi(), sito pubblico).
    echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><title>Torno subito</title><body style="font-family:system-ui;background:#060a17;color:#e6ebf5;padding:15vh 24px;text-align:center"><h1>Torno subito.</h1><p>Il sito si è fermato un momento. Riprova tra qualche minuto.</p></body></html>';
    exit;
}

/**
 * Da admin/includes/auth.php: durante la copia il pannello mostra solo la pagina del cambio. Le altre pagine rimandano lì
 * (all'amministratore) o dicono che il cambio è in corso (al redattore); le API rispondono 503 in JSON. Il login e l'uscita
 * restano aperti (chi non è entrato arriva al login dalle pagine come sempre).
 */
function cambiadb_pannello_blocca(): void {
    if (PHP_SAPI === 'cli' || !is_file(cambiadb_lavoro_file()) || !cambiadb_manutenzione_attiva()) return;
    $qui = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($qui, ['cambia-db.php', 'login.php', 'logout.php', 'account.php', 'password-dimenticata.php', 'password-reimposta.php'], true)) return;
    if (empty($_SESSION['admin_user_id'])) return; // la pagina stessa manda al login
    $api = str_ends_with($qui, '-api.php') || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json');
    $admin = function_exists('is_admin') && is_admin();
    while (ob_get_level() > 1) @ob_end_clean();
    if ($api) {
        http_response_code(503); header('Retry-After: 60'); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['errore' => 'È in corso il cambio del database: riprova fra qualche minuto.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($admin) { header('Location: cambia-db.php', true, 302); exit; }
    http_response_code(503); header('Retry-After: 120'); header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cambio del database in corso</title><body style="font-family:system-ui;padding:12vh 16px;text-align:center;line-height:1.5"><h1>Cambio del database in corso</h1><p>L\'amministratore sta spostando il sito su un altro database. Il pannello torna disponibile fra qualche minuto.</p><p><a href="logout.php">Esci</a></p></body></html>';
    exit;
}

// ------------------------------------------------------------
// La password del database nuovo, cifrata nel file di stato
// ------------------------------------------------------------

function cambiadb_chiave(): string {
    $base = defined('CMS_SEGRETO') && CMS_SEGRETO !== '' ? (string)CMS_SEGRETO : (defined('DB_PASS') ? (string)DB_PASS . '|' . DB_NAME : 'plumecms');
    return hash('sha256', $base . '|cambia-db', true);
}

function cambiadb_cifra(string $chiaro): string {
    $iv = random_bytes(12); $tag = '';
    $c = openssl_encrypt($chiaro, 'aes-256-gcm', cambiadb_chiave(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($c === false) throw new CambiaDbErrore('La cifratura non è disponibile su questo server (estensione openssl).');
    return base64_encode($iv . $tag . $c);
}

function cambiadb_decifra(string $cifrato): string {
    $b = base64_decode($cifrato, true);
    if ($b === false || strlen($b) < 28) throw new CambiaDbErrore('La password del database nuovo non si legge più: annulla e ricomincia dalla prova della connessione.');
    $p = openssl_decrypt(substr($b, 28), 'aes-256-gcm', cambiadb_chiave(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
    if ($p === false) throw new CambiaDbErrore('La password del database nuovo non si legge più: annulla e ricomincia dalla prova della connessione.');
    return $p;
}

// ------------------------------------------------------------
// I dati del database nuovo
// ------------------------------------------------------------

/**
 * Controlla i dati scritti nel modulo. Ritorna [dati puliti, errori per campo]. Host: nome o indirizzo IP, senza schema,
 * percorso o porta; porta 1-65535; nome del database e prefisso con le regole dell'installatore (install/index.php).
 */
function cambiadb_valida(array $in): array {
    $e = [];
    $host = strtolower(trim((string)($in['host'] ?? '')));
    $portaTesto = trim((string)($in['porta'] ?? ''));
    $nome = trim((string)($in['nome'] ?? ''));
    $utente = trim((string)($in['utente'] ?? ''));
    $prefisso = strtolower(trim((string)($in['prefisso'] ?? '')));
    if ($host === '') $e['host'] = 'Scrivi il server del database: quasi sempre localhost.';
    elseif (strlen($host) > 253 || !(preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/', $host) || filter_var($host, FILTER_VALIDATE_IP)))
        $e['host'] = 'Scrivi solo il nome del server o il suo indirizzo IP, per esempio localhost oppure db.esempio.it: senza http://, senza barre e senza la porta (la porta ha il suo campo).';
    if ($portaTesto === '') $portaTesto = '3306';
    if (!preg_match('/^\d{1,5}$/', $portaTesto) || (int)$portaTesto < 1 || (int)$portaTesto > 65535) $e['porta'] = 'La porta è un numero da 1 a 65535: quasi sempre 3306.';
    if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $nome)) $e['nome'] = 'Il nome del database può avere solo lettere, numeri, trattino e trattino basso (per esempio account_nuovo).';
    if ($utente === '' || strlen($utente) > 80 || preg_match('/[\x00-\x1f\x7f]/', $utente)) $e['utente'] = $utente === '' ? 'Serve l\'utente del database.' : 'L\'utente del database non è valido.';
    if ($prefisso === '') $prefisso = DB_PREFIX;
    if (!preg_match('/^[a-z0-9_]{1,20}$/', $prefisso)) $e['prefisso'] = 'Il prefisso può avere solo lettere minuscole, numeri e trattino basso (per esempio cms_).';
    elseif (!str_ends_with($prefisso, '_')) $prefisso .= '_';
    if (preg_match('/^(rip|vec)[0-9a-f]{6}_/', $prefisso)) $e['prefisso'] = 'Questo prefisso somiglia ai nomi di lavoro della copia (rip..., vec...): scegline un altro.';
    return [['host' => $host, 'porta' => (int)$portaTesto, 'nome' => $nome, 'utente' => $utente, 'prefisso' => $prefisso], $e];
}

/** Il valore di DB_HOST del config per questi dati, nel formato dell'installatore: "server" oppure "server;port=N". */
function cambiadb_host_config(array $d): string {
    return (string)$d['host'] . ((int)$d['porta'] !== 3306 ? ';port=' . (int)$d['porta'] : '');
}

/** Server e porta di adesso, letti da DB_HOST ("127.0.0.1;port=3308"). */
function cambiadb_host_attuale(): array {
    $h = defined('DB_HOST') ? (string)DB_HOST : '';
    $porta = 3306;
    if (preg_match('/^(.*?);port=(\d+)/', $h, $m)) { $h = $m[1]; $porta = (int)$m[2]; }
    return [$h, $porta];
}

/**
 * Si collega al database nuovo: tempo massimo breve, istruzioni impilate spente, modalità SQL lasca come il ripristino della
 * copia, lo stesso fuso orario della connessione di adesso (i TIMESTAMP restano gli stessi). Su errore CambiaDbErrore con le
 * parole semplici: mai il messaggio del server, che porta utente e host (né, nelle tracce, la password: la si passa al
 * costruttore dentro questo try e l'eccezione originale non esce).
 */
function cambiadb_connetti(array $d, string $pass, int $timeout = CAMBIADB_TIMEOUT): PDO {
    $dsn = 'mysql:host=' . $d['host'] . ';port=' . (int)$d['porta'] . ';dbname=' . $d['nome'] . ';charset=utf8mb4';
    $op = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => $timeout];
    if (db_attr_istruzioni_impilate() !== null) $op[db_attr_istruzioni_impilate()] = false;
    $errore = null;
    try {
        $pdo = new PDO($dsn, $d['utente'], $pass, $op);
    } catch (PDOException $e) {
        $errore = cambiadb_errore_connessione($e, $d);
        error_log('cambia-db: connessione al database nuovo non riuscita (codice ' . cambiadb_codice($e) . ')');
    }
    if ($errore !== null) throw new CambiaDbErrore($errore);
    try {
        $tz = (string)db()->query('SELECT @@session.time_zone')->fetchColumn();
        $pdo->exec('SET time_zone = ' . $pdo->quote($tz !== '' ? $tz : date('P')));
        $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    } catch (Throwable $e) {
        throw new CambiaDbErrore('Il database nuovo risponde, ma rifiuta le impostazioni della connessione. Chiedi all\'assistenza dell\'hosting.');
    }
    return $pdo;
}

/** Il codice numerico di un errore PDO (1045, 2002...). */
function cambiadb_codice(Throwable $e): int {
    $c = ($e instanceof PDOException && isset($e->errorInfo[1])) ? (int)$e->errorInfo[1] : 0;
    if ($c === 0 && preg_match('/\[(\d{4})\]/', $e->getMessage(), $m)) $c = (int)$m[1];
    return $c;
}

/** L'errore di connessione in parole semplici (regola 4.1), senza dettagli del server. */
function cambiadb_errore_connessione(PDOException $e, array $d): string {
    $c = cambiadb_codice($e);
    $msg = $e->getMessage();
    if ($c === 1045) return 'Utente o password del database nuovo sbagliati. Controlla nel pannello dell\'hosting (in cPanel: Database MySQL) il nome esatto dell\'utente, che spesso comincia con il nome del tuo account, e riscrivi la password.';
    if ($c === 1044) return 'L\'utente «' . $d['utente'] . '» non può usare il database «' . $d['nome'] . '». In cPanel, voce Database MySQL, aggiungi l\'utente al database con tutti i privilegi.';
    if ($c === 1049) return 'Il database «' . $d['nome'] . '» non esiste su quel server. Crealo prima dal pannello dell\'hosting (in cPanel: Database MySQL) e controlla che il nome sia scritto giusto, compreso quello che c\'è davanti al trattino basso.';
    if ($c === 2005 || ($c === 2002 && preg_match('/getaddrinfo|Name or service|name resolution|No such host|Unknown MySQL server host/i', $msg))) return 'Il server «' . $d['host'] . '» non esiste. Controlla il campo Server: quasi sempre è localhost.';
    if (in_array($c, [2002, 2003, 2006, 2013], true)) return 'Il server «' . $d['host'] . '» non risponde sulla porta ' . (int)$d['porta'] . '. Controlla server e porta (quasi sempre localhost e 3306); se il database sta su un altro server, deve accettare collegamenti da questo.';
    return 'Il database nuovo non accetta la connessione. Controlla server, porta, nome del database, utente e password.';
}

/** "server|porta|database" come lo dice il server stesso: due indirizzi diversi per lo stesso database danno lo stesso valore. */
function cambiadb_identita(PDO $pdo): string {
    $r = $pdo->query('SELECT @@hostname, @@port, DATABASE()')->fetch(PDO::FETCH_NUM);
    return strtolower((string)$r[0]) . '|' . (int)$r[1] . '|' . (string)$r[2];
}

/** È il database di adesso, con lo stesso prefisso? (confronto su quello che dicono i due server, e su quello che è scritto) */
function cambiadb_stesso(PDO $dest, array $d): bool {
    if ($d['prefisso'] !== DB_PREFIX) return false;
    [$h, $p] = cambiadb_host_attuale();
    if (strtolower($h) === $d['host'] && $p === (int)$d['porta'] && DB_NAME === $d['nome']) return true;
    try { return cambiadb_identita($dest) === cambiadb_identita(db()); } catch (Throwable $e) { return false; }
}

/** Le tabelle e le viste del database nuovo con il prefisso dato (mai quelle di un'altra installazione con un prefisso più lungo). */
function cambiadb_tabelle_dest(PDO $pdo, string $prefisso): array {
    $tutte = []; $tipi = [];
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $r) { $tutte[] = (string)$r[0]; $tipi[(string)$r[0]] = strtoupper((string)($r[1] ?? '')); }
    $mie = copia_tabelle_del_prefisso($tutte, $prefisso);
    $out = ['tabelle' => [], 'viste' => [], 'tutte' => $tutte];
    foreach ($mie as $t) $out[$tipi[$t] === 'VIEW' ? 'viste' : 'tabelle'][] = $t;
    return $out;
}

/** Un nome di lavoro (rip<esadecimali>_ / vec<esadecimali>_) che non c'è nel database nuovo. */
function cambiadb_marchi(PDO $pdo, string $prefisso, array $suffissi): array {
    $tutte = array_map(fn($r) => (string)$r[0], $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));
    for ($giro = 0; $giro < 20; $giro++) {
        $hex = bin2hex(random_bytes(3));
        $app = COPIA_APPOGGIO . $hex . '_'; $vec = COPIA_PARCHEGGIO . $hex . '_';
        if (str_starts_with($app, $prefisso) || str_starts_with($vec, $prefisso)) continue;
        $libero = true;
        foreach ($tutte as $t) if (str_starts_with($t, $app) || str_starts_with($t, $vec)) { $libero = false; break; }
        if (!$libero) continue;
        foreach ($suffissi as $s) if (strlen($app . $prefisso . $s) > 64) throw new CambiaDbErrore('Il nome di una tabella nel database nuovo sarebbe troppo lungo per MySQL (oltre 64 caratteri, tabella ' . $s . '): scegli un prefisso più corto.');
        return [$app, $vec];
    }
    throw new CambiaDbErrore('Non trovo nomi di lavoro liberi nel database nuovo: riprova.');
}

/**
 * Prova la connessione: versione, permessi (CREATE, INSERT, ALTER, INDEX, DROP e CREATE VIEW con una tabella e una vista di
 * prova create e tolte), tabelle già presenti con il prefisso. Ritorna ['ok', 'messaggio', 'versione', 'esistenti' (quante),
 * 'max_packet']; un errore è CambiaDbErrore.
 */
function cambiadb_prova(array $d, string $pass): array {
    $pdo = cambiadb_connetti($d, $pass);
    if (cambiadb_stesso($pdo, $d)) throw new CambiaDbErrore('Questo è il database che il sito usa adesso, con lo stesso prefisso: scegli un altro database (o almeno un altro prefisso).');
    $versione = copia_nome_server((string)$pdo->query('SELECT VERSION()')->fetchColumn());
    // I permessi, con una tabella di prova dal nome che non può essere di nessuno (e mai con il prefisso del sito).
    $t = 'plumeprova_' . bin2hex(random_bytes(4));
    $passo = 'CREATE';
    try {
        $pdo->exec('CREATE TABLE `' . $t . '` (`id` INT NOT NULL PRIMARY KEY, `n` INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $passo = 'INSERT'; $pdo->exec('INSERT INTO `' . $t . '` VALUES (1, 1)');
        $passo = 'ALTER'; $pdo->exec('ALTER TABLE `' . $t . '` ADD COLUMN `m` INT NULL');
        $passo = 'INDEX'; $pdo->exec('CREATE INDEX `i_n` ON `' . $t . '` (`n`)');
        $passo = 'CREATE VIEW'; $pdo->exec('CREATE OR REPLACE VIEW `' . $t . '_v` AS SELECT * FROM `' . $t . '`');
        $passo = 'DROP'; $pdo->exec('DROP VIEW IF EXISTS `' . $t . '_v`'); $pdo->exec('DROP TABLE `' . $t . '`');
    } catch (Throwable $e) {
        try { $pdo->exec('DROP VIEW IF EXISTS `' . $t . '_v`'); } catch (Throwable $x) {}
        try { $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`'); } catch (Throwable $x) {}
        $cosa = ['CREATE' => 'creare tabelle', 'INSERT' => 'scrivere righe', 'ALTER' => 'modificare tabelle (ALTER)', 'INDEX' => 'creare indici', 'CREATE VIEW' => 'creare viste (CREATE VIEW)', 'DROP' => 'cancellare tabelle (DROP)'][$passo];
        throw new CambiaDbErrore('L\'utente «' . $d['utente'] . '» si collega, ma non ha il permesso di ' . $cosa . ' nel database nuovo. In cPanel, voce Database MySQL, aggiungi l\'utente al database con TUTTI i privilegi, poi riprova.');
    }
    $q = cambiadb_tabelle_dest($pdo, $d['prefisso']);
    $max = (int)$pdo->query('SELECT @@max_allowed_packet')->fetchColumn();
    return ['ok' => true, 'versione' => $versione, 'esistenti' => count($q['tabelle']) + count($q['viste']), 'max_packet' => $max];
}

/** Il limite delle prove della connessione, per sessione: true se si può provare (e conta il tentativo). */
function cambiadb_prova_ammessa(): bool {
    $ora = time();
    $l = array_values(array_filter((array)($_SESSION['cambiadb_prove'] ?? []), fn($t) => is_int($t) && $t > $ora - CAMBIADB_PROVE_FINESTRA));
    if (count($l) >= CAMBIADB_PROVE_MAX) { $_SESSION['cambiadb_prove'] = $l; return false; }
    $l[] = $ora;
    $_SESSION['cambiadb_prove'] = $l;
    return true;
}

// ------------------------------------------------------------
// La copia
// ------------------------------------------------------------

/** Le colonne della chiave primaria di una tabella del sito (per leggerla a pezzi in ordine), [] se non ne ha. */
function cambiadb_chiave_primaria(string $t): array {
    $c = [];
    foreach (db()->query('SHOW KEYS FROM `' . str_replace('`', '``', $t) . "` WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC) as $r) $c[(int)$r['Seq_in_index']] = (string)$r['Column_name'];
    ksort($c);
    return array_values($c);
}

function cambiadb_conta(PDO $pdo, string $t): int {
    return (int)$pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $t) . '`')->fetchColumn();
}

/** L'elenco delle tabelle da copiare, con le righe di adesso. */
function cambiadb_elenco_tabelle(): array {
    $out = [];
    foreach (copia_tabelle_sito() as $t) {
        $out[] = ['t' => substr($t, strlen(DB_PREFIX)), 'righe' => cambiadb_conta(db(), $t), 'fatte' => 0, 'creata' => false, 'fatta' => false, 'pk' => cambiadb_chiave_primaria($t)];
    }
    return $out;
}

/**
 * Comincia il cambio: riprova la connessione e le condizioni (stesso database, tabelle già presenti senza "Sostituisci"),
 * scrive il lavoro e mette il sito in manutenzione. La copia vera la fanno i giri. Ritorna il lavoro.
 */
function cambiadb_avvia(array $d, string $pass, bool $sostituisci, string $utente): array {
    if (cambiadb_lavoro()) throw new CambiaDbErrore('C\'è già un cambio del database in corso: continualo o annullalo.');
    if (!copia_blocco_prendi()) throw new CambiaDbErrore('In questo momento il sito sta facendo un altro lavoro sul database (un ripristino o un aggiornamento): riprova fra un minuto.');
    try {
        $pdo = cambiadb_connetti($d, $pass);
        if (cambiadb_stesso($pdo, $d)) throw new CambiaDbErrore('Questo è il database che il sito usa adesso, con lo stesso prefisso: scegli un altro database (o almeno un altro prefisso).');
        $q = cambiadb_tabelle_dest($pdo, $d['prefisso']);
        $esistenti = count($q['tabelle']) + count($q['viste']);
        if ($esistenti && !$sostituisci) throw new CambiaDbErrore('Nel database nuovo ci sono già ' . $esistenti . ' tabelle con il prefisso ' . $d['prefisso'] . '. Scegli un altro prefisso, oppure spunta «Sostituisci le tabelle con questo prefisso nel database nuovo».');
        $tabelle = cambiadb_elenco_tabelle();
        if (!$tabelle) throw new CambiaDbErrore('Non trovo le tabelle del sito: niente da copiare.');
        [$app, $vec] = cambiadb_marchi($pdo, $d['prefisso'], array_column($tabelle, 't'));
        $l = [
            'id' => bin2hex(random_bytes(6)), 'iniziato' => time(), 'utente' => $utente, 'fase' => 'copia',
            'dest' => $d + ['sostituisci' => $sostituisci, 'pass' => cambiadb_cifra($pass)],
            'versione_dest' => copia_nome_server((string)$pdo->query('SELECT VERSION()')->fetchColumn()),
            'versione_prima' => copia_nome_server(copia_server_versione()),
            'app' => $app, 'vec' => $vec, 'tabelle' => $tabelle, 'riprove' => 0, 'cambi' => [],
        ];
        [$h, $p] = cambiadb_host_attuale();
        $l['prima'] = ['host' => $h, 'porta' => $p, 'nome' => DB_NAME, 'utente' => (string)DB_USER, 'prefisso' => DB_PREFIX];
        cambiadb_lavoro_scrivi($l);
        if (function_exists('sicurezza_log')) sicurezza_log('cambia_db', $utente, (int)($_SESSION['admin_user_id'] ?? 0), 'copia avviata verso ' . $d['host'] . ':' . $d['porta'] . '/' . $d['nome'] . ' (prefisso ' . $d['prefisso'] . ', ' . count($tabelle) . ' tabelle, ' . array_sum(array_column($tabelle, 'righe')) . ' righe' . ($sostituisci ? ', sostituisce ' . $esistenti . ' tabelle' : '') . '), dal database ' . $h . ':' . $p . '/' . DB_NAME);
        return $l;
    } finally {
        copia_blocco_lascia();
    }
}

/** Avanzamento del lavoro per la pagina: righe fatte, righe in tutto, testo. */
function cambiadb_avanzamento(array $l): array {
    $fatte = 0; $tot = 0; $tab = 0;
    foreach ($l['tabelle'] as $t) { $fatte += min((int)$t['fatte'], (int)$t['righe']); $tot += (int)$t['righe']; if (!empty($t['fatta'])) $tab++; }
    $n = count($l['tabelle']);
    return ['fatte' => $fatte, 'totale' => max(1, $tot), 'testo' => $tab . ' tabelle su ' . $n . ', ' . number_format($fatte, 0, ',', '.') . ' righe su ' . number_format($tot, 0, ',', '.')];
}

/** Crea nel database nuovo la tabella di appoggio di una tabella del sito, con la CREATE adattata a quel server. */
function cambiadb_crea_tabella(PDO $dest, array &$l, string $suf, array $srv): void {
    $app = $l['app'] . $l['dest']['prefisso'] . $suf;
    $create = (string)db()->query('SHOW CREATE TABLE `' . str_replace('`', '``', DB_PREFIX . $suf) . '`')->fetch(PDO::FETCH_NUM)[1];
    $create = str_replace(["\r\n", "\n"], ' ', $create);
    $create = preg_replace('~^CREATE TABLE `(?:[^`]|``)+`~', 'CREATE TABLE `' . $app . '`', $create, 1, $fatto);
    if ($fatto !== 1) throw new CambiaDbErrore('Non riesco a leggere la struttura della tabella ' . $suf . '.');
    $cambi = (array)($l['cambi'] ?? []);
    $create = copia_create_portabile($create, $cambi, $srv);
    $l['cambi'] = $cambi;
    $dest->exec('DROP TABLE IF EXISTS `' . $app . '`');
    $dest->exec($create);
}

/**
 * Un giro della copia (chiesto dalla pagina): lavora al più CAMBIADB_GIRO secondi, poi salva dove è arrivato. Quando tutte le
 * tabelle sono copiate conta le righe, fa lo scambio dei nomi nel database nuovo e il passaggio del config. Ritorna lo stato
 * per la pagina: ['finito' => bool, 'fatte', 'totale', 'testo'] (o 'errore' per un problema di rete della pagina).
 */
function cambiadb_giro(): array {
    $l = cambiadb_lavoro();
    if (!$l || $l['fase'] !== 'copia' || cambiadb_scaduto($l)) return ['finito' => true, 'fatte' => 1, 'totale' => 1, 'testo' => ''];
    if (!copia_blocco_prendi()) return ['finito' => false] + cambiadb_avanzamento($l) + ['testo' => 'Un altro giro è ancora al lavoro: aspetto.'];
    $budget = defined('CMS_CAMBIADB_GIRO') ? max(1, (int)CMS_CAMBIADB_GIRO) : CAMBIADB_GIRO;
    $t0 = microtime(true);
    $dest = null;
    try {
        @set_time_limit(120);
        $l['aggiornato'] = time(); cambiadb_lavoro_scrivi($l);
        $dest = cambiadb_connetti($l['dest'], cambiadb_decifra((string)$l['dest']['pass']), 10);
        $srv = copia_collazioni_di($dest);
        $max = (int)$dest->query('SELECT @@max_allowed_packet')->fetchColumn();
        $limite = $max > 4096 ? min(COPIA_INSERT_MAX, $max - 2048) : COPIA_INSERT_MAX;
        $pd = $l['dest']['prefisso'];
        foreach ($l['tabelle'] as $i => $tb) {
            if (!empty($tb['fatta'])) continue;
            $suf = (string)$tb['t'];
            $app = $l['app'] . $pd . $suf;
            if (empty($tb['creata'])) {
                cambiadb_crea_tabella($dest, $l, $suf, $srv);
                $l['tabelle'][$i]['creata'] = true; $l['tabelle'][$i]['fatte'] = 0;
                cambiadb_lavoro_scrivi($l);
            } elseif (cambiadb_conta($dest, $app) !== (int)$tb['fatte']) {
                // Un giro interrotto fra l'INSERT e il salvataggio: la tabella si rifà da capo.
                cambiadb_crea_tabella($dest, $l, $suf, $srv);
                $l['tabelle'][$i]['fatte'] = 0;
                cambiadb_lavoro_scrivi($l);
            }
            $sorg = DB_PREFIX . $suf;
            $gen = agg_colonne_generate(db(), $sorg);
            $pk = (array)$tb['pk'];
            $ordine = $pk ? ' ORDER BY ' . implode(', ', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', $pk)) : '';
            while (true) {
                $da = (int)$l['tabelle'][$i]['fatte'];
                // Senza chiave primaria la tabella si copia tutta in una volta (l'ordine non è garantito fra due letture).
                $sql = 'SELECT * FROM `' . str_replace('`', '``', $sorg) . '`' . ($pk ? $ordine . ' LIMIT ' . CAMBIADB_BLOCCO . ' OFFSET ' . $da : '');
                $pdo = db();
                $pdo->setAttribute(agg_pdo_buffered_attr(), false);
                $letti = 0; $buf = ''; $blocchi = [];
                try {
                    $st = $pdo->query($sql);
                    while ($row = $st->fetch(PDO::FETCH_NUM)) {
                        $vals = agg_riga_sql($row, $gen);
                        if ($buf !== '' && strlen($buf) + strlen($vals) + 1 > $limite) { $blocchi[] = $buf; $buf = ''; }
                        $buf .= ($buf === '' ? 'INSERT INTO `' . $app . '` VALUES ' : ',') . $vals;
                        $letti++;
                        // I blocchi pronti si mandano subito (la lettura resta aperta sulla connessione di adesso, l'INSERT va sull'altra).
                        foreach ($blocchi as $b) { if ($max > 0 && strlen($b) > $max) throw new CambiaDbErrore('Una riga della tabella ' . $suf . ' pesa più di quanto il database nuovo accetta (max_allowed_packet ' . agg_fmt_bytes($max) . '). Chiedi all\'assistenza dell\'hosting di alzarlo.'); $dest->exec($b); }
                        $blocchi = [];
                    }
                    $st->closeCursor();
                } finally {
                    $pdo->setAttribute(agg_pdo_buffered_attr(), true);
                }
                if ($buf !== '') { if ($max > 0 && strlen($buf) > $max) throw new CambiaDbErrore('Una riga della tabella ' . $suf . ' pesa più di quanto il database nuovo accetta (max_allowed_packet ' . agg_fmt_bytes($max) . '). Chiedi all\'assistenza dell\'hosting di alzarlo.'); $dest->exec($buf); }
                if (defined('CMS_CAMBIADB_PAUSA')) usleep((int)CMS_CAMBIADB_PAUSA); // solo per le prove (una copia lenta da interrompere)
                $l['tabelle'][$i]['fatte'] = $da + $letti;
                $finita = !$pk || $letti < CAMBIADB_BLOCCO;
                if ($finita) $l['tabelle'][$i]['fatta'] = true;
                cambiadb_lavoro_scrivi($l);
                if ($finita || microtime(true) - $t0 > $budget) break;
            }
            if (microtime(true) - $t0 > $budget) break;
        }
        $tutte = !array_filter($l['tabelle'], fn($t) => empty($t['fatta']));
        if (!$tutte) return ['finito' => false] + cambiadb_avanzamento($l);
        // Tutte copiate: le righe, tabella per tabella. Una che non torna (una riga scritta durante la copia: un accesso, un
        // tentativo respinto) si ricopia; dopo CAMBIADB_RIPROVE giri ci si ferma senza cambiare niente.
        $diverse = [];
        foreach ($l['tabelle'] as $i => $tb) {
            $a = cambiadb_conta(db(), DB_PREFIX . $tb['t']); $b = cambiadb_conta($dest, $l['app'] . $pd . $tb['t']);
            if ($a !== $b) { $diverse[] = $tb['t'] . ' (' . $a . ' righe qui, ' . $b . ' nel nuovo)'; $l['tabelle'][$i] = ['t' => $tb['t'], 'righe' => $a, 'fatte' => 0, 'creata' => false, 'fatta' => false, 'pk' => $tb['pk']]; }
        }
        if ($diverse) {
            $l['riprove'] = (int)$l['riprove'] + 1;
            if ($l['riprove'] > CAMBIADB_RIPROVE) throw new CambiaDbErrore('Il numero di righe non torna, anche dopo ' . CAMBIADB_RIPROVE . ' tentativi: ' . implode(', ', array_slice($diverse, 0, 6)) . '. Qualcosa continua a scrivere nel database durante la copia. Riprova più tardi; il sito resta sul database di prima.');
            cambiadb_lavoro_scrivi($l);
            return ['finito' => false] + cambiadb_avanzamento($l) + ['testo' => 'Ricopio ' . count($diverse) . ' tabelle cambiate durante la copia.'];
        }
        return cambiadb_scambia_e_passa($l, $dest);
    } catch (Throwable $e) {
        $motivo = $e instanceof CambiaDbErrore ? $e->getMessage() : copia_errore_parole($e);
        error_log('cambia-db: copia fermata: ' . get_class($e) . ' ' . cambiadb_codice($e));
        cambiadb_fallito($l, $dest, $motivo);
        return ['finito' => true, 'fatte' => 1, 'totale' => 1, 'testo' => ''];
    } finally {
        copia_blocco_lascia();
    }
}

/** La copia non va: via le tabelle di lavoro dal database nuovo, via il lavoro, l'esito con il motivo. Il sito torna normale. */
function cambiadb_fallito(array $l, ?PDO $dest, string $motivo): void {
    $l = cambiadb_lavoro() ?? $l; // lo stato più recente: dopo lo scambio dice quali tabelle rimettere al loro nome
    try {
        $dest = $dest ?? cambiadb_connetti($l['dest'], cambiadb_decifra((string)$l['dest']['pass']), 5);
        cambiadb_dest_disfa($dest, $l);
    } catch (Throwable $e) { $motivo .= ' (Nel database nuovo possono essere rimaste tabelle di lavoro che cominciano con ' . $l['app'] . ': si possono cancellare dal pannello dell\'hosting.)'; }
    cambiadb_lavoro_togli();
    cambiadb_ultimo_scrivi(['fase' => 'errore', 'quando' => time(), 'messaggio' => $motivo, 'dest' => cambiadb_dest_pubblico($l['dest'])]);
    if (function_exists('sicurezza_log')) sicurezza_log('cambia_db', (string)($l['utente'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'copia fermata, il sito resta sul database di prima: ' . mb_substr($motivo, 0, 300));
}

/** I dati del database nuovo senza la password (per ultimo.json e per la pagina). */
function cambiadb_dest_pubblico(array $d): array {
    return ['host' => (string)$d['host'], 'porta' => (int)$d['porta'], 'nome' => (string)$d['nome'], 'utente' => (string)$d['utente'], 'prefisso' => (string)$d['prefisso']];
}

/**
 * Riporta il database nuovo come prima del cambio: le tabelle di appoggio si cancellano; se lo scambio c'era già stato, una sola
 * RENAME rimette le tabelle parcheggiate al loro nome (e le nostre, copiate, vanno via). Tocca solo i nomi registrati nel lavoro.
 */
function cambiadb_dest_disfa(PDO $dest, array $l): void {
    $pd = $l['dest']['prefisso'];
    $esistenti = array_flip(array_map(fn($r) => (string)$r[0], $dest->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM)));
    if (!empty($l['scambiato'])) {
        $coppie = [];
        foreach ((array)$l['rinominate'] as $t) if (isset($esistenti[$pd . $t])) $coppie[] = '`' . $pd . $t . '` TO `' . $l['app'] . $pd . $t . '`';
        foreach ((array)($l['parcheggiate'] ?? []) as $t) if (isset($esistenti[$l['vec'] . $t])) $coppie[] = '`' . $l['vec'] . $t . '` TO `' . $t . '`';
        if ($coppie) $dest->exec('RENAME TABLE ' . implode(', ', $coppie));
        $esistenti = array_flip(array_map(fn($r) => (string)$r[0], $dest->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM)));
    }
    foreach ($l['tabelle'] as $tb) {
        $n = $l['app'] . $pd . $tb['t'];
        if (isset($esistenti[$n])) $dest->exec('DROP TABLE IF EXISTS `' . $n . '`');
    }
}

/**
 * Lo scambio nel database nuovo (una RENAME sola) e il passaggio del config. Ritorna lo stato finale per la pagina.
 */
function cambiadb_scambia_e_passa(array $l, PDO $dest): array {
    $pd = $l['dest']['prefisso'];
    $q = cambiadb_tabelle_dest($dest, $pd);
    if (($q['tabelle'] || $q['viste']) && empty($l['dest']['sostituisci'])) throw new CambiaDbErrore('Durante la copia nel database nuovo sono comparse tabelle con il prefisso ' . $pd . '. Il cambio si ferma; il sito resta sul database di prima.');
    $coppie = []; $parcheggiate = [];
    $nostre = array_flip(array_map(fn($t) => $pd . $t['t'], $l['tabelle']));
    foreach ($q['tabelle'] as $t) {
        if (strlen($l['vec'] . $t) > 64) throw new CambiaDbErrore('Il nome di parcheggio della tabella ' . $t . ' nel database nuovo sarebbe troppo lungo: scegli un prefisso più corto.');
        $coppie[] = '`' . $t . '` TO `' . $l['vec'] . $t . '`'; $parcheggiate[] = $t;
    }
    // Le viste con il nostro stesso nome di tabella non si possono scambiare: ci si ferma.
    foreach ($q['viste'] as $v) if (isset($nostre[$v])) throw new CambiaDbErrore('Nel database nuovo c\'è una vista che si chiama come una tabella del sito (' . $v . '): il cambio si ferma.');
    foreach ($l['tabelle'] as $tb) $coppie[] = '`' . $l['app'] . $pd . $tb['t'] . '` TO `' . $pd . $tb['t'] . '`';
    $dest->exec('SET SESSION lock_wait_timeout = 10');
    $dest->exec('RENAME TABLE ' . implode(', ', $coppie));
    $l['scambiato'] = true; $l['rinominate'] = array_column($l['tabelle'], 't'); $l['parcheggiate'] = $parcheggiate;
    cambiadb_lavoro_scrivi($l);
    try {
        // Le due viste degli articoli, come sito_viste() (includes/sito.php), con il prefisso nuovo.
        $dest->exec('CREATE OR REPLACE VIEW `' . $pd . 'v_published_articles` AS SELECT * FROM `' . $pd . 'articles`'
            . " WHERE `status` = 'published' AND `cestino` IS NULL AND `publish_date` IS NOT NULL AND `publish_date` <= CURDATE()");
        $dest->exec('CREATE OR REPLACE VIEW `' . $pd . 'v_scheduled_articles` AS SELECT * FROM `' . $pd . 'articles`'
            . " WHERE `status` IN ('published','scheduled') AND `cestino` IS NULL AND `publish_date` > CURDATE()");
        // Un controllo finale, sulle tabelle con il nome vero.
        foreach ($l['tabelle'] as $tb) {
            $a = cambiadb_conta(db(), DB_PREFIX . $tb['t']); $b = cambiadb_conta($dest, $pd . $tb['t']);
            if ($a !== $b) throw new CambiaDbErrore('Dopo lo scambio la tabella ' . $tb['t'] . ' ha ' . $b . ' righe nel database nuovo e ' . $a . ' qui. Il cambio si ferma; il sito resta sul database di prima.');
        }
    } catch (Throwable $e) {
        throw $e instanceof CambiaDbErrore ? $e : new CambiaDbErrore('Le tabelle sono copiate, ma il database nuovo ha rifiutato le viste degli articoli: il cambio si ferma e il sito resta sul database di prima.');
    }
    $esito = cambiadb_config_passa($l['dest'], cambiadb_decifra((string)$l['dest']['pass']));
    if ($esito['ok']) {
        cambiadb_dopo_passaggio($l, $dest, (string)$esito['backup']);
        return ['finito' => true, 'fatte' => 1, 'totale' => 1, 'testo' => 'Fatto'];
    }
    if (!empty($esito['manuale'])) {
        $l['fase'] = 'manuale';
        $l['motivo_manuale'] = (string)$esito['motivo'];
        // Una copia del config di adesso, se la cartella lo permette: serve a "Torna al database di prima" dopo il passaggio a mano.
        if (empty($l['backup'])) { $c = cambiadb_config_salva_copia((string)@file_get_contents(cambiadb_config_file())); $l['backup'] = $c !== '' ? basename($c) : ''; }
        cambiadb_lavoro_scrivi($l);
        return ['finito' => true, 'fatte' => 1, 'totale' => 1, 'testo' => ''];
    }
    throw new CambiaDbErrore((string)$esito['motivo']);
}

/** Passaggio riuscito: via le tabelle parcheggiate nel database nuovo, l'esito, il lavoro tolto. */
function cambiadb_dopo_passaggio(array $l, PDO $dest, string $backup): void {
    $avvisi = [];
    foreach ((array)($l['parcheggiate'] ?? []) as $t) { try { $dest->exec('DROP TABLE IF EXISTS `' . $l['vec'] . $t . '`'); } catch (Throwable $e) { $avvisi[] = $l['vec'] . $t; } }
    [$h, $p] = cambiadb_host_attuale();
    $prima = ['host' => $h, 'porta' => $p, 'nome' => DB_NAME, 'utente' => (string)DB_USER, 'prefisso' => DB_PREFIX];
    if (isset($l['prima'])) $prima = $l['prima'];
    cambiadb_ultimo_scrivi(['fase' => 'fatto', 'quando' => time(), 'iniziato' => (int)$l['iniziato'], 'dest' => cambiadb_dest_pubblico($l['dest']), 'prima' => $prima,
        'backup' => $backup, 'tabelle' => count($l['tabelle']), 'righe' => array_sum(array_column($l['tabelle'], 'righe')), 'versione_dest' => (string)($l['versione_dest'] ?? ''),
        'versione_prima' => (string)($l['versione_prima'] ?? ''), 'cambi' => (array)($l['cambi'] ?? []), 'sostituite' => count((array)($l['parcheggiate'] ?? [])), 'avvisi' => $avvisi,
        'registrato' => false, 'utente' => (string)($l['utente'] ?? '')]);
    cambiadb_lavoro_togli();
}

/**
 * Annulla il cambio in corso (copia, scaduto o fase manuale): il database nuovo torna com'era (tabelle di lavoro cancellate,
 * le parcheggiate rimesse), il lavoro si toglie, il sito resta (o torna) sul database di prima.
 */
function cambiadb_annulla(string $utente): string {
    $l = cambiadb_lavoro();
    if (!$l) return 'Nessun cambio in corso.';
    if ($l['fase'] === 'manuale' && cambiadb_config_e_dest($l)) throw new CambiaDbErrore('Il config indica già il database nuovo: il sito lo sta usando. Per tornare indietro usa «Torna al database di prima» dopo il controllo.');
    if (!copia_blocco_prendi()) throw new CambiaDbErrore('Un giro della copia è ancora al lavoro: riprova fra qualche secondo.');
    $nota = '';
    try {
        $dest = cambiadb_connetti($l['dest'], cambiadb_decifra((string)$l['dest']['pass']), 5);
        cambiadb_dest_disfa($dest, $l);
    } catch (Throwable $e) {
        $nota = ' Il database nuovo non risponde: le tabelle di lavoro che cominciano con ' . $l['app'] . ' sono rimaste là e si possono cancellare dal pannello dell\'hosting.';
    } finally {
        copia_blocco_lascia();
    }
    cambiadb_lavoro_togli();
    cambiadb_ultimo_scrivi(['fase' => 'annullato', 'quando' => time(), 'messaggio' => 'Cambio annullato: il sito usa il database di sempre.' . $nota, 'dest' => cambiadb_dest_pubblico($l['dest'])]);
    if (function_exists('sicurezza_log')) sicurezza_log('cambia_db', $utente, (int)($_SESSION['admin_user_id'] ?? 0), 'cambio annullato (database nuovo ' . $l['dest']['host'] . ':' . $l['dest']['porta'] . '/' . $l['dest']['nome'] . ')');
    return 'Cambio annullato: il sito usa il database di sempre, che non è mai stato toccato.' . $nota;
}

/** Ricomincia una copia che si era fermata (pagina chiusa troppo a lungo): le tabelle di lavoro da capo, righe ricontate. */
function cambiadb_ricomincia(string $utente): void {
    $l = cambiadb_lavoro();
    if (!$l || $l['fase'] !== 'copia') throw new CambiaDbErrore('Non c\'è una copia da ricominciare.');
    if (!copia_blocco_prendi()) throw new CambiaDbErrore('Un giro della copia è ancora al lavoro: riprova fra qualche secondo.');
    try {
        $dest = cambiadb_connetti($l['dest'], cambiadb_decifra((string)$l['dest']['pass']));
        cambiadb_dest_disfa($dest, $l);
        $l['tabelle'] = cambiadb_elenco_tabelle();
        $l['riprove'] = 0; $l['cambi'] = []; unset($l['scambiato'], $l['rinominate'], $l['parcheggiate']);
        cambiadb_lavoro_scrivi($l);
    } finally {
        copia_blocco_lascia();
    }
    if (function_exists('sicurezza_log')) sicurezza_log('cambia_db', $utente, (int)($_SESSION['admin_user_id'] ?? 0), 'copia ricominciata da capo');
}

// ------------------------------------------------------------
// Il config
// ------------------------------------------------------------

function cambiadb_config_file(): string { return __DIR__ . '/config.local.php'; }

/**
 * Le costanti definite con un valore letterale in un config (define('NOME', 'valore')), lette con il tokenizzatore di PHP (il
 * file non si esegue). Ritorna [nome => valore] (stringhe, numeri come testo).
 */
function cambiadb_config_valori(string $testo): array {
    $t = array_values(array_filter(@token_get_all($testo), fn($x) => !is_array($x) || !in_array($x[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    $out = [];
    $str = function ($tok): ?string {
        if (!is_array($tok)) return null;
        if ($tok[0] === T_LNUMBER || $tok[0] === T_DNUMBER) return (string)$tok[1];
        if ($tok[0] !== T_CONSTANT_ENCAPSED_STRING) return null;
        $s = $tok[1];
        if ($s[0] === "'") return strtr(substr($s, 1, -1), ["\\\\" => "\\", "\\'" => "'"]);
        if (preg_match('/\$/', $s)) return null;
        return stripcslashes(substr($s, 1, -1));
    };
    for ($i = 0, $n = count($t); $i + 5 < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_STRING || strtolower($t[$i][1]) !== 'define' || $t[$i + 1] !== '(' || $t[$i + 3] !== ',' || $t[$i + 5] !== ')') continue;
        $nome = $str($t[$i + 2]); $val = $str($t[$i + 4]);
        if ($nome !== null && $val !== null) $out[$nome] = $val;
    }
    return $out;
}

/** La riga define() di una costante, come la scrive l'installatore. */
function cambiadb_riga(string $nome, string $valore): string {
    return 'define(' . var_export($nome, true) . ', ' . var_export($valore, true) . ');';
}

/** Le righe del database per questi dati (le stesse dell'installatore). */
function cambiadb_righe(array $d, string $pass): array {
    return ['DB_HOST' => cambiadb_riga('DB_HOST', cambiadb_host_config($d)), 'DB_NAME' => cambiadb_riga('DB_NAME', $d['nome']), 'DB_USER' => cambiadb_riga('DB_USER', $d['utente']),
        'DB_PASS' => cambiadb_riga('DB_PASS', $pass), 'CMS_PREFISSO' => cambiadb_riga('CMS_PREFISSO', $d['prefisso'])];
}

/**
 * Il config nuovo: lo stesso testo con le righe del database e del prefisso cambiate (una riga define per costante, come
 * l'installatore). Se manca CMS_PREFISSO (le installazioni di prima lo prendono dal predefinito) la riga si aggiunge dopo
 * DB_PASS. Ritorna null se le righe del database non si trovano nella forma dell'installatore (allora si incolla a mano).
 */
function cambiadb_config_nuovo(string $testo, array $righe): ?string {
    foreach ($righe as $nome => $riga) {
        $re = '/^[ \t]*define\(\s*([\'"])' . preg_quote($nome, '/') . '\1\s*,\s*(?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\$]|\\\\.)*"|\d+)\s*\)\s*;[^\n]*$/m';
        $quante = preg_match_all($re, $testo);
        if ($quante === 1) { $testo = preg_replace_callback($re, fn() => $riga, $testo); continue; }
        if ($quante === 0 && $nome === 'CMS_PREFISSO') {
            $rePass = '/^[ \t]*define\(\s*([\'"])DB_PASS\1[^\n]*$/m';
            if (preg_match($rePass, $testo)) { $testo = preg_replace_callback($rePass, fn($m) => $m[0] . "\n" . $riga, $testo, 1); continue; }
        }
        return null;
    }
    return $testo;
}

/** La copia del config di prima, non leggibile dal web: comincia con un'uscita immediata (un 404 se qualcuno la apre). */
const CAMBIADB_GUARDIA = "<?php http_response_code(404); exit; /* plumeCMS, Cambia database: copia del config.local.php di prima. Non si apre dal web. */ ?>\n";

/** Salva il config di adesso accanto a quello vero (includes/config.local.prima-<data>.php) o, se non si può, in aggiornamenti/cambia-db/. Ritorna il percorso. */
function cambiadb_config_salva_copia(string $testo): string {
    $data = date('Ymd-His');
    foreach ([__DIR__, cambiadb_dir()] as $dir) {
        if (!is_dir($dir) || !is_writable($dir)) continue;
        $f = $dir . '/config.local.prima-' . $data . '.php';
        for ($i = 2; is_file($f) && $i < 50; $i++) $f = $dir . '/config.local.prima-' . $data . '-' . $i . '.php';
        if (@file_put_contents($f, CAMBIADB_GUARDIA . $testo, LOCK_EX) !== false) { @chmod($f, 0600); return $f; }
    }
    return '';
}

/** Il testo di una copia salvata del config, senza la riga di guardia. */
function cambiadb_config_leggi_copia(string $f): ?string {
    $base = basename($f);
    if (!preg_match('/^config\.local\.prima-\d{8}-\d{6}(?:-\d+)?\.php$/', $base)) return null;
    foreach ([__DIR__ . '/' . $base, cambiadb_dir() . '/' . $base] as $p) {
        if (!is_file($p)) continue;
        $t = (string)file_get_contents($p);
        if (!str_starts_with($t, CAMBIADB_GUARDIA)) return null;
        return substr($t, strlen(CAMBIADB_GUARDIA));
    }
    return null;
}

/** Il config si può scrivere da qui (il file e la sua cartella, per la scrittura con il file temporaneo)? */
function cambiadb_config_scrivibile(): bool {
    $f = cambiadb_config_file();
    return is_file($f) && is_writable($f) && is_writable(dirname($f));
}

/** Scrive il config: un file temporaneo nella stessa cartella e una rinomina (mai un config a metà). */
function cambiadb_config_scrivi(string $testo): bool {
    $f = cambiadb_config_file();
    $tmp = dirname($f) . '/.config.local.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $testo, LOCK_EX) !== strlen($testo)) { @unlink($tmp); return false; }
    $perm = @fileperms($f);
    @chmod($tmp, $perm ? ($perm & 0777) : 0640);
    if (!@rename($tmp, $f)) { @unlink($tmp); return false; }
    clearstatcache(true, $f);
    agg_opcache_dimentica($f);
    return true;
}

/**
 * Il controllo dopo il passaggio, con i valori riletti dal file scritto: ci si collega, users e settings si leggono, c'è
 * almeno un utente. Ritorna '' se va bene, altrimenti il motivo in parole semplici.
 */
function cambiadb_config_controlla(string $testo, string $prefissoSenzaRiga = ''): string {
    if (defined('CMS_CAMBIADB_GUASTO') && CMS_CAMBIADB_GUASTO === 'controllo') return 'guasto di prova (CMS_CAMBIADB_GUASTO).'; // solo per le prove
    $v = cambiadb_config_valori($testo);
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) if (!isset($v[$k])) return 'Nel config scritto non trovo ' . $k . '.';
    $host = $v['DB_HOST']; $porta = 3306;
    if (preg_match('/^(.*?);port=(\d+)$/', $host, $m)) { $host = $m[1]; $porta = (int)$m[2]; }
    // Un config senza CMS_PREFISSO (installazioni di prima) usa il predefinito di cms.php: chi chiama sa qual era.
    $pref = $v['CMS_PREFISSO'] ?? ($prefissoSenzaRiga !== '' ? $prefissoSenzaRiga : DB_PREFIX);
    try {
        $pdo = cambiadb_connetti(['host' => $host, 'porta' => $porta, 'nome' => $v['DB_NAME'], 'utente' => $v['DB_USER'], 'prefisso' => $pref], $v['DB_PASS'], 5);
        $n = (int)$pdo->query('SELECT COUNT(*) FROM `' . $pref . 'users`')->fetchColumn();
        $pdo->query('SELECT setting_value FROM `' . $pref . "settings` WHERE setting_key = 'cms_versione_installata'")->fetchColumn();
        $pdo->query('SELECT COUNT(*) FROM `' . $pref . 'v_published_articles`')->fetchColumn();
        if ($n < 1) return 'Nel database indicato dal config non c\'è nessun utente.';
    } catch (CambiaDbErrore $e) {
        return $e->getMessage();
    } catch (Throwable $e) {
        return 'Il database indicato dal config non ha le tabelle del sito (utenti, impostazioni o articoli non si leggono).';
    }
    return '';
}

/**
 * Il passaggio: copia del config di adesso, config nuovo, controllo; se il controllo fallisce si rimette quello di prima.
 * Ritorna ['ok' => true, 'backup'] oppure ['ok' => false, 'manuale' => bool, 'motivo'].
 */
function cambiadb_config_passa(array $d, string $pass): array {
    $f = cambiadb_config_file();
    $prima = is_file($f) ? (string)file_get_contents($f) : '';
    if ($prima === '') return ['ok' => false, 'manuale' => true, 'motivo' => 'Non riesco a leggere includes/config.local.php.'];
    $nuovo = cambiadb_config_nuovo($prima, cambiadb_righe($d, $pass));
    if ($nuovo === null) return ['ok' => false, 'manuale' => true, 'motivo' => 'Il file includes/config.local.php non ha le righe del database nella forma che scrive l\'installatore: va cambiato a mano.'];
    if (!cambiadb_config_scrivibile()) return ['ok' => false, 'manuale' => true, 'motivo' => 'Il file includes/config.local.php non si può scrivere da qui (permessi del file o della cartella).'];
    $copia = cambiadb_config_salva_copia($prima);
    if ($copia === '') return ['ok' => false, 'manuale' => true, 'motivo' => 'Non riesco a salvare una copia del config di adesso (permessi della cartella includes/).'];
    if (!cambiadb_config_scrivi($nuovo)) return ['ok' => false, 'manuale' => true, 'motivo' => 'Il file includes/config.local.php non si è lasciato scrivere.'];
    $scritto = (string)@file_get_contents($f);
    $motivo = $scritto === $nuovo ? cambiadb_config_controlla($scritto) : 'Il config riletto non è quello scritto.';
    if ($motivo !== '') {
        cambiadb_config_scrivi($prima);
        return ['ok' => false, 'manuale' => false, 'motivo' => 'Il config nuovo è stato scritto, ma il controllo non è andato: ' . $motivo . ' Ho rimesso il config di prima: il sito resta sul database di prima.'];
    }
    return ['ok' => true, 'backup' => basename($copia)];
}

/**
 * Fase manuale: l'amministratore ha incollato le righe? Ritorna '' se il sito usa il database nuovo (e chiude il lavoro),
 * altrimenti il motivo.
 */
function cambiadb_controlla_manuale(): string {
    $l = cambiadb_lavoro();
    if (!$l || $l['fase'] !== 'manuale') return 'Nessun cambio in attesa del config.';
    if (!cambiadb_config_e_dest($l)) return 'Il file includes/config.local.php indica ancora il database di prima (o le righe non sono scritte esatte). Controlla di averlo salvato con le righe qui sotto, poi riprova.';
    $motivo = cambiadb_config_controlla((string)@file_get_contents(cambiadb_config_file()));
    if ($motivo !== '') return $motivo;
    // Qui db() è già il database nuovo: le parcheggiate si tolgono da lì.
    cambiadb_dopo_passaggio($l, db(), (string)($l['backup'] ?? ''));
    return '';
}

/** Le righe da incollare a mano (solo all'amministratore, solo nella pagina del cambio): con la password, perché vanno nel file. */
function cambiadb_righe_manuali(array $l): array {
    return cambiadb_righe($l['dest'], cambiadb_decifra((string)$l['dest']['pass']));
}

/**
 * Torna al database di prima: rimette il config salvato prima del passaggio (dopo averne fatto una copia), solo se il database
 * di prima risponde e ha le tabelle. Ritorna ['ok', 'messaggio', 'manuale' => righe da incollare se il config non si scrive].
 */
function cambiadb_torna(string $utente): array {
    $u = cambiadb_ultimo();
    if (!$u || ($u['fase'] ?? '') !== 'fatto') throw new CambiaDbErrore('Non c\'è un cambio da annullare.');
    $vecchio = (string)($u['backup'] ?? '') !== '' ? cambiadb_config_leggi_copia((string)$u['backup']) : null;
    if ($vecchio === null) throw new CambiaDbErrore('Non trovo la copia del config di prima (il cambio è stato completato incollando le righe a mano). Rimetti a mano nel file includes/config.local.php le righe del database di prima.');
    $motivo = cambiadb_config_controlla($vecchio, (string)($u['prima']['prefisso'] ?? ''));
    if ($motivo !== '') throw new CambiaDbErrore('Il database di prima non risponde più come serve: ' . $motivo . ' Il sito resta sul database nuovo.');
    $f = cambiadb_config_file();
    $adesso = (string)@file_get_contents($f);
    if (!cambiadb_config_scrivibile()) {
        $v = cambiadb_config_valori($vecchio);
        $righe = [];
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'CMS_PREFISSO'] as $k) if (isset($v[$k])) $righe[$k] = cambiadb_riga($k, $v[$k]);
        return ['ok' => false, 'manuale' => $righe, 'messaggio' => 'Il file includes/config.local.php non si può scrivere da qui: rimetti a mano le righe qui sotto.'];
    }
    $copia = cambiadb_config_salva_copia($adesso);
    if ($copia === '' || !cambiadb_config_scrivi($vecchio)) throw new CambiaDbErrore('Non riesco a scrivere il file includes/config.local.php. Il sito resta sul database nuovo.');
    $u['fase'] = 'tornato'; $u['tornato'] = time(); $u['backup_nuovo'] = basename($copia);
    cambiadb_ultimo_scrivi($u);
    if (function_exists('sicurezza_log')) sicurezza_log('cambia_db', $utente, (int)($_SESSION['admin_user_id'] ?? 0), 'tornato al database di prima (' . ($u['prima']['host'] ?? '') . ':' . ($u['prima']['porta'] ?? '') . '/' . ($u['prima']['nome'] ?? '') . ')');
    return ['ok' => true, 'messaggio' => 'Il sito usa di nuovo il database di prima. Le modifiche fatte dopo il passaggio restano solo nel database nuovo.'];
}
