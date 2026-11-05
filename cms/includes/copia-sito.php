<?php
/**
 * Copia del sito (1.3.0, 27 ottobre 2026): tutto il sito in uno zip, da scaricare, e il ripristino da uno zip.
 * Pannello: admin/copia-sito.php (Impostazioni > Copia del sito), solo per l'amministratore.
 *
 * Nella copia: il database con il prefisso del sito (db.sql.gz, una istruzione per riga, lo stesso formato degli
 * snapshot di includes/aggiornamenti.php), le immagini e i file caricati (uploads/), i temi (temi/), i moduli (moduli/).
 * Le impostazioni stanno nel database e quindi ci sono. Fuori: includes/config.local.php, con la password del
 * database, e ogni file nascosto (.htaccess, .user.ini).
 *
 *   manifest.json   formato "plumecms-copia", versione, versione del CMS, prefisso, sito, data, quanti file,
 *                   e la firma: l'HMAC-SHA256 dell'impronta dello zip (stesso calcolo dei pacchetti firmati,
 *                   sicurezza_pacchetto_impronta) con la chiave di questo sito, che nella copia non c'è
 *   db.sql.gz       il database
 *   uploads/... temi/... moduli/...
 *
 * Le copie stanno in aggiornamenti/copie/, cartella che il server web non serve mai (.htaccess di agg_dir(), regole
 * della radice): si scaricano solo dal pannello. Se ne tengono COPIA_DA_TENERE, le più vecchie si cancellano.
 *
 * Ripristino: prima una copia del sito com'è adesso ("prima del ripristino"), poi il database e i file. Le regole:
 *   - ogni nome nello zip e controllato: niente "..", niente percorsi assoluti, solo uploads/, temi/, moduli/ (zip slip);
 *   - del database passano solo le istruzioni del formato delle copie (DROP TABLE, CREATE TABLE, INSERT INTO) sulle
 *     tabelle con il prefisso della copia, riscritte con il prefisso di questo sito: niente altro SQL;
 *   - un file eseguibile (.php e simili) entra solo da una copia firmata da questo sito, e solo in temi/ e moduli/;
 *     da una copia di un altro sito (o modificata) si salta, e il pannello lo dice. In uploads/ mai;
 *   - l'indirizzo del sito (sito_url) resta quello di questa installazione;
 *   - limiti: 2 GB estratti in tutto, 300 MB per file, rapporto di compressione sotto controllo.
 *   - (2.1) dopo i file, uploads/, temi/ e moduli/ diventano esattamente come nella copia: i file che ci sono nel sito ma
 *     non nella copia si spostano in aggiornamenti/scarto/ripristino-AAAAMMGG-HHMMSS/ (non si cancellano: restano
 *     COPIA_SCARTO_GIORNI giorni, poi li toglie il ripristino dopo). Fuori da questa regola: i file nascosti (.htaccess),
 *     i collegamenti, i temi del prodotto ("autore": "plumeCMS" in tema.json, arrivano con gli aggiornamenti). Con
 *     "tieni i file in più" ($tieni) restano dove sono, come fino alla 2.0.
 *
 * Due installazioni nello stesso database (2.1): con i prefissi cms_ e cms_b_ le tabelle cms_b_* cominciano anche con
 * cms_. La copia di cms_ le lasciava entrare (e il ripristino le riscriveva). Ora copia_tabelle_del_prefisso() toglie le
 * tabelle di un prefisso più lungo che ha le sue settings e users (un'altra installazione), il manifest elenca le tabelle
 * della copia (elenco_tabelle) e il ripristino tocca solo quelle; per una copia vecchia, senza elenco, la stessa regola
 * si applica ai nomi che si trovano nel database della copia.
 *
 * Il ripristino sicuro (2.25.1, dopo il guasto del 4 novembre 2026 su un sito vero: il ripristino eseguiva DROP TABLE e subito
 * dopo la CREATE della copia; la CREATE, scritta da un MySQL 8, portava COLLATE=utf8mb4_0900_ai_ci, che il server di arrivo
 * non conosceva; la tabella restava cancellata, il ciclo andava avanti e il sito finiva senza users, settings e pages). Ora:
 *   - niente DROP prima di sapere che la CREATE funziona: la copia si ricrea in TABELLE DI APPOGGIO, con un prefisso che non
 *     comincia mai con quello del sito (rip<6 esadecimali>_<prefisso><tabella>, copia_nomi_appoggio()); al primo errore le
 *     tabelle di appoggio si cancellano e il sito resta com'era (copia_ripristina_db());
 *   - se tutto va bene, UNA sola RENAME TABLE scambia le tabelle vive con quelle di appoggio (le vive vanno in parcheggio,
 *     vec<esadecimali>_<prefisso><tabella>): MySQL la esegue tutta o niente; poi le parcheggiate si cancellano. Le tabelle del
 *     sito che non stanno nella copia restano come sono (elencate nel registro); le viste si rifanno (sito_viste()) e le
 *     impronte degli schemi si azzerano, così al primo accesso le colonne nuove si aggiungono da sole;
 *   - le collazioni e i set di caratteri che questo server non conosce diventano i più vicini conosciuti, fuori dagli apici
 *     (copia_create_portabile(): utf8mb4_0900_ai_ci -> utf8mb4_unicode_ci, utf8mb3 -> utf8...), e il registro lo dice;
 *   - un blocco GET_LOCK per tutto il ripristino (un secondo clic non ne fa partire due), un marcatore su file con i nomi di
 *     appoggio (aggiornamenti/copie/.ripristino-in-corso.json): un ripristino interrotto lascia tabelle che il ripristino dopo
 *     toglie, solo quelle nominate dal marcatore; le INSERT solo con valori letterali (mai una sottoquery o una funzione), e
 *     ogni riga entro max_allowed_packet del server di arrivo; le CREATE senza vincoli o rimandi verso altre tabelle o verso file
 *     (REFERENCES, UNION=, CONNECTION=, DATA DIRECTORY, INDEX DIRECTORY, TABLESPACE, fuori dagli apici: plumeCMS non li scrive
 *     mai, e con due installazioni nello stesso database un vincolo legherebbe una tabella di questo sito a una dell'altro;
 *     copia_create_ammessa());
 *   - l'indirizzo del sito (sito_url) si scrive nella settings di appoggio PRIMA dello scambio: mai una finestra con
 *     l'indirizzo dell'altro sito;
 *   - il registro del ripristino si scrive sempre su file (copia_registro_scrivi(), <copia>.ripristino.log) e in sicurezza_log,
 *     e il pannello lo mostra anche dopo un nuovo accesso; se il database fallisce ci si ferma PRIMA dei file.
 */

require_once __DIR__ . '/aggiornamenti.php';
require_once __DIR__ . '/sicurezza.php';

const COPIA_FORMATO = 'plumecms-copia';
const COPIA_VERSIONE = 1;
const COPIA_DA_TENERE = 6;
/** 2.25.1: davanti ai nomi di appoggio (rip<6 esadecimali>_) e di parcheggio (vec<6 esadecimali>_) del ripristino sicuro. */
const COPIA_APPOGGIO = 'rip';
const COPIA_PARCHEGGIO = 'vec';
/** 2.25.1: il marcatore di un ripristino in corso, in aggiornamenti/copie/: i nomi di appoggio e di parcheggio da togliere se si interrompe. */
const COPIA_MARCATORE = '.ripristino-in-corso.json';
/** 2.25.1: i blocchi INSERT del dump si chiudono prima di superare questa misura (max_allowed_packet parte da 4 MB su MySQL 5.7). */
const COPIA_INSERT_MAX = 1048576;
/** 2.25.1: quanti registri dei ripristini (<copia>.ripristino.log) restano in aggiornamenti/copie/. */
const COPIA_REGISTRI_DA_TENERE = 10;
const COPIA_CARTELLE = ['uploads', 'temi', 'moduli'];
const COPIA_MAX_TOTALE = 2147483648;   // 2 GB estratti
const COPIA_MAX_FILE = 314572800;      // 300 MB per file
/** (2.1) Quanti giorni restano i file messi da parte da un ripristino (aggiornamenti/scarto/), prima che il ripristino dopo li tolga. */
const COPIA_SCARTO_GIORNI = 30;
/** Le estensioni che il server potrebbe eseguire: entrano solo da una copia firmata da questo sito, in temi/ e moduli/. */
const COPIA_ESEGUIBILI = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'pht', 'pl', 'py', 'cgi', 'sh', 'shtml'];

/** La cartella delle copie, dentro aggiornamenti/ (vietata al web). */
function copia_dir(): string {
    $d = agg_dir() . '/copie';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

/** La cartella vera sul disco di una delle radici della copia. */
function copia_radice(string $nome): string {
    return match ($nome) {
        'uploads' => cms_uploads_dir(),
        'temi' => cms_radice() . '/temi',
        'moduli' => cms_radice() . '/moduli',
        default => throw new RuntimeException('Cartella non ammessa: ' . $nome),
    };
}

/** I nomi delle copie sul server: copia-AAAAMMGG-HHMMSS-<etichetta>.zip. */
function copia_nome_valido(string $f): bool {
    return (bool)preg_match('/^copia-\d{8}-\d{6}-[a-z0-9-]{1,40}\.zip$/', $f);
}

/**
 * Le copie sul server, dalla più recente: file, peso, data, e il manifest se si legge. $conFirma = false (2.25.1, la pagina di
 * soccorso) salta il calcolo della firma (l'HMAC di tutto lo zip): 'firmata' resta false.
 */
function copia_elenco(bool $conFirma = true): array {
    $out = [];
    foreach ((array)@scandir(copia_dir()) as $f) {
        if (!is_string($f) || !copia_nome_valido($f)) continue;
        $p = copia_dir() . '/' . $f;
        $m = null;
        try { $m = copia_leggi_manifest($p); } catch (Throwable $e) { $m = null; }
        $out[] = ['file' => $f, 'peso' => (int)filesize($p), 'data' => (int)filemtime($p), 'manifest' => $m, 'firmata' => ($m && $conFirma) ? copia_firmata($p, $m) : false];
    }
    usort($out, fn($a, $b) => strcmp($b['file'], $a['file']));
    return $out;
}

function copia_percorso(string $file): string {
    $file = basename($file);
    if (!copia_nome_valido($file) || !is_file(copia_dir() . '/' . $file)) throw new RuntimeException('Copia non trovata.');
    return copia_dir() . '/' . $file;
}

function copia_elimina(string $file): void {
    @unlink(copia_percorso($file));
}

/** Tiene le ultime COPIA_DA_TENERE copie. $salva (2.1): copie da non togliere mai (quella che si sta ripristinando). */
function copia_pulisci(array $salva = []): void {
    foreach (array_slice(copia_elenco(), COPIA_DA_TENERE) as $c) if (!in_array($c['file'], $salva, true)) @unlink(copia_dir() . '/' . $c['file']);
}

/** I file di una radice, ricorsivi, senza i file e le cartelle nascosti. Ritorna [percorso nello zip => percorso sul disco]. */
function copia_file_di(string $nome): array {
    $base = copia_radice($nome);
    $out = [];
    if (!is_dir($base)) return $out;
    $base = rtrim(str_replace('\\', '/', (string)realpath($base)), '/');
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        fn($f) => $f->getFilename()[0] !== '.'), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($it as $f) {
        if (!$f->isFile() || $f->isLink()) continue;
        $vero = str_replace('\\', '/', (string)$f->getRealPath());
        if (!str_starts_with($vero, $base . '/')) continue; // un collegamento che porta fuori non entra
        $out[$nome . '/' . substr($vero, strlen($base) + 1)] = $vero;
    }
    return $out;
}

/**
 * (2.1) Le tabelle di questa installazione fra $tutte, con il prefisso $p. Una tabella che comincia con $p ma appartiene a
 * un'altra installazione con un prefisso più lungo (cms_ e cms_b_ nello stesso database) resta fuori: un prefisso più
 * lungo $q è di un'altra installazione quando ci sono sia $q.'settings' sia $q.'users', le due tabelle che ogni
 * installazione ha. Le tabelle dei moduli e degli addon (membri_sessioni, pren_servizi...) non hanno questa coppia.
 */
function copia_tabelle_del_prefisso(array $tutte, string $p): array {
    $mie = array_values(array_filter(array_map('strval', $tutte), fn($t) => $p !== '' && str_starts_with($t, $p)));
    $altri = copia_altri_prefissi($mie, $p);
    return array_values(array_filter($mie, fn($t) => !copia_di_altri($t, $altri)));
}

/** (2.1) I prefissi più lunghi di $p che fra $tutte hanno settings e users: altre installazioni nello stesso database. */
function copia_altri_prefissi(array $tutte, string $p): array {
    $tutte = array_map('strval', $tutte);
    $altri = [];
    foreach ($tutte as $t) {
        if ($p !== '' && str_starts_with($t, $p) && preg_match('/^(.+_)settings$/', $t, $m) && strlen($m[1]) > strlen($p) && in_array($m[1] . 'users', $tutte, true)) $altri[] = $m[1];
    }
    return array_values(array_unique($altri));
}

/** (2.1) La tabella $t comincia con uno dei prefissi di $altri? */
function copia_di_altri(string $t, array $altri): bool {
    foreach ($altri as $q) if (str_starts_with($t, $q)) return true;
    return false;
}

/** (2.1) Le tabelle vere (non le viste) di questa installazione, con il loro nome intero. */
function copia_tabelle_sito(): array {
    $tabelle = [];
    foreach (db()->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $r) if (strtoupper((string)($r[1] ?? '')) !== 'VIEW') $tabelle[] = (string)$r[0];
    return copia_tabelle_del_prefisso($tabelle, DB_PREFIX);
}

/**
 * (2.1) Il database della copia: le tabelle date, nello stesso formato di agg_dump_db() (una istruzione per riga), che
 * copia_ripristina_db() e i CMS più vecchi sanno rileggere. Non usa agg_dump_db() perché quella prende tutte le tabelle
 * che cominciano con il prefisso, anche quelle di un'altra installazione.
 * 2.25.1: i blocchi INSERT si chiudono PRIMA di superare COPIA_INSERT_MAX (1 MB): sul server di arrivo ogni riga deve stare
 * in max_allowed_packet (il ripristino lo controlla). Una sola riga più grande di 1 MB resta com'è.
 */
function copia_dump_db(string $gzPath, array $tabelle): array {
    $pdo = db();
    $gz = gzopen($gzPath, 'wb6');
    if (!$gz) throw new RuntimeException('Impossibile scrivere ' . $gzPath);
    gzwrite($gz, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';\n");
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n");
    $righe = 0;
    foreach ($tabelle as $t) {
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $t) . '`')->fetch(PDO::FETCH_NUM);
        gzwrite($gz, 'DROP TABLE IF EXISTS `' . $t . "`;\n");
        gzwrite($gz, str_replace(["\r\n", "\n"], ' ', (string)$create[1]) . ";\n");
        $gen = agg_colonne_generate($pdo, $t); // 2.1.0: DEFAULT al posto delle colonne generate (Eventi, Prenotazioni)
        $pdo->setAttribute(agg_pdo_buffered_attr(), false);
        $st = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $t) . '`');
        $buf = '';
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $vals = agg_riga_sql($row, $gen);
            if ($buf !== '' && strlen($buf) + strlen($vals) + 1 > COPIA_INSERT_MAX) { gzwrite($gz, $buf . ";\n"); $buf = ''; }
            $buf .= ($buf === '' ? 'INSERT INTO `' . $t . '` VALUES ' : ',') . $vals;
            $righe++;
        }
        if ($buf !== '') gzwrite($gz, $buf . ";\n");
        $st->closeCursor();
        $pdo->setAttribute(agg_pdo_buffered_attr(), true);
    }
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    return ['tabelle' => count($tabelle), 'righe' => $righe];
}

/**
 * Crea una copia del sito. $etichetta: "manuale", "prima-del-ripristino"... Ritorna il nome del file.
 * Il lavoro e lungo su un sito grande: niente limite di tempo e niente interruzione se il browser chiude.
 */
function copia_crea(string $etichetta = 'manuale', array $salva = []): string {
    if (!class_exists('ZipArchive')) throw new RuntimeException('La libreria PHP per gli zip (ZipArchive) manca sul server.');
    if (!function_exists('gzopen')) throw new RuntimeException('La compressione zlib manca sul server.');
    @set_time_limit(0);
    @ignore_user_abort(true);
    $nome = 'copia-' . date('Ymd-His') . '-' . (agg_slugify($etichetta) ?: 'copia') . '.zip';
    $zipPath = copia_dir() . '/' . $nome;
    $tmpDb = copia_dir() . '/.db-' . bin2hex(random_bytes(6)) . '.sql.gz';
    try {
        $tabelle = copia_tabelle_sito();
        $db = copia_dump_db($tmpDb, $tabelle);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Non riesco a scrivere la copia in ' . copia_dir());
        $zip->addFile($tmpDb, 'db.sql.gz');
        $zip->setCompressionName('db.sql.gz', ZipArchive::CM_STORE); // già compresso
        $quanti = 0; $peso = 0;
        foreach (COPIA_CARTELLE as $c) {
            foreach (copia_file_di($c) as $dentro => $vero) {
                $zip->addFile($vero, $dentro);
                $quanti++; $peso += (int)filesize($vero);
            }
        }
        if (!$zip->close()) throw new RuntimeException('Lo zip della copia non si chiude: spazio sul disco finito?');
        // La firma si calcola sullo zip finito (tutte le voci tranne manifest.json), poi si aggiunge il manifest.
        $zip = new ZipArchive();
        $zip->open($zipPath);
        $manifest = [
            'formato' => COPIA_FORMATO, 'versione' => COPIA_VERSIONE, 'cms_versione' => cms_versione(), 'creato' => date('Y-m-d H:i:s'),
            'sito' => cms_url_sito(), 'prefisso' => DB_PREFIX, 'etichetta' => $etichetta,
            'tabelle' => $db['tabelle'], 'righe' => $db['righe'], 'file' => $quanti, 'peso_file' => $peso,
            // 2.1: le tabelle della copia senza il prefisso: il ripristino tocca solo queste.
            'elenco_tabelle' => array_map(fn($t) => substr($t, strlen(DB_PREFIX)), $tabelle),
            // 2.25.1: il server del database che ha scritto le CREATE (il ripristino lo dice accanto a quello di arrivo).
            'server_versione' => copia_server_versione(),
        ];
        $chiave = sicurezza_chiave_pacchetti();
        $manifest['firma'] = $chiave !== '' ? hash_hmac('sha256', sicurezza_pacchetto_impronta($zip), $chiave) : '';
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();
        @chmod($zipPath, 0600); // la copia ha dati sensibili: la legge solo l'utente del sito
    } catch (Throwable $e) {
        @unlink($zipPath);
        throw $e;
    } finally {
        @unlink($tmpDb);
    }
    if (function_exists('sicurezza_log')) sicurezza_log('copia', (string)($_SESSION['admin_username'] ?? 'cron'), (int)($_SESSION['admin_user_id'] ?? 0), 'copia del sito creata: ' . $nome);
    copia_pulisci($salva);
    return $nome;
}

/** Legge e controlla il manifest e i nomi delle voci di una copia. Ritorna il manifest. */
function copia_leggi_manifest(string $zipPath): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('La libreria PHP per gli zip (ZipArchive) manca sul server.');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('Il file non è uno zip leggibile.');
    try {
        $m = json_decode((string)$zip->getFromName('manifest.json'), true);
        if (!is_array($m) || ($m['formato'] ?? '') !== COPIA_FORMATO) throw new RuntimeException('Questo zip non è una copia del sito fatta da ' . CMS_NOME . ' (manca manifest.json con il formato "' . COPIA_FORMATO . '"). Un pacchetto di aggiornamento si carica da Aggiornamenti.');
        if ((int)($m['versione'] ?? 0) > COPIA_VERSIONE) throw new RuntimeException('La copia è stata fatta da una versione più nuova di ' . CMS_NOME . ': aggiorna prima questo sito.');
        if (!preg_match('/^[a-z0-9_]{1,30}$/', (string)($m['prefisso'] ?? ''))) throw new RuntimeException('Il prefisso delle tabelle nella copia non è valido.');
        if ($zip->locateName('db.sql.gz') === false) throw new RuntimeException('Nella copia manca il database (db.sql.gz).');
        // sicurezza: zip slip e zip bomba. Ogni nome e relativo, senza "..", dentro una delle cartelle della copia.
        $totale = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $n = (string)($st['name'] ?? '');
            if (!copia_nome_voce_ok($n)) throw new RuntimeException('Nome non ammesso nella copia: ' . mb_substr($n, 0, 120));
            $dim = (int)($st['size'] ?? 0);
            if ($dim > COPIA_MAX_FILE) throw new RuntimeException('File troppo grande nella copia: ' . $n);
            if ($dim > 10485760 && (int)($st['comp_size'] ?? 0) > 0 && $dim / (int)$st['comp_size'] > 200) throw new RuntimeException('File compresso in modo sospetto nella copia: ' . $n);
            $totale += $dim;
        }
        if ($totale > COPIA_MAX_TOTALE) throw new RuntimeException('La copia, estratta, supera 2 GB.');
        return $m;
    } finally {
        $zip->close();
    }
}

/** Un nome di voce ammesso: manifest.json, db.sql.gz, o un file dentro uploads/, temi/, moduli/ senza parti nascoste o "..". */
function copia_nome_voce_ok(string $n): bool {
    if ($n === 'manifest.json' || $n === 'db.sql.gz') return true;
    if ($n === '' || str_contains($n, "\0") || str_contains($n, '\\') || str_starts_with($n, '/') || preg_match('~^[a-z]:~i', $n)) return false;
    $parti = explode('/', rtrim($n, '/'));
    if (count($parti) < 2 || !in_array($parti[0], COPIA_CARTELLE, true)) return false;
    foreach ($parti as $p) if ($p === '' || $p === '.' || $p === '..' || $p[0] === '.') return false;
    return true;
}

/** La copia e firmata da questo sito? (stessa chiave dei pacchetti, che nella copia non c'è) */
function copia_firmata(string $zipPath, array $m): bool {
    $firma = (string)($m['firma'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $firma)) return false;
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return false;
    $ok = sicurezza_pacchetto_firma_ok($zip, $firma);
    $zip->close();
    return $ok;
}

// ------------------------------------------------------------
// 2.25.1: il ripristino sicuro del database (appoggio, scambio, collazioni)
// ------------------------------------------------------------

/** La versione del server del database (SELECT VERSION()), per il manifest e per il registro. */
function copia_server_versione(): string {
    try { return (string)db()->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable $e) { return ''; }
}

/** "MySQL 8.0.46" o "MariaDB 10.6.28" da una versione di SELECT VERSION(); '' resta ''. */
function copia_nome_server(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (stripos($v, 'mariadb') !== false) return 'MariaDB ' . preg_replace('/[-_].*$/', '', $v);
    return 'MySQL ' . preg_replace('/[-_].*$/', '', $v);
}

/** Il nome del blocco del ripristino: vale per tutto il server MySQL, con database e prefisso dentro (come l'invio della newsletter). */
function copia_blocco_nome(): string {
    return 'plume_ripristino_' . md5(DB_NAME . '|' . DB_PREFIX);
}

/**
 * Prende il blocco del ripristino (GET_LOCK senza attesa). Ritorna false se un altro ripristino è in corso su questo sito (un
 * secondo clic su Ripristina, un'altra scheda del browser), o un aggiornamento (agg_applica() lo tiene per tutto il lavoro: il
 * suo ripristino automatico e un ripristino da Copia del sito non si incrociano mai). Rientrante: se questa stessa connessione
 * lo ha già, conta un livello in più e copia_blocco_lascia() lo libera solo all'ultimo livello (il ripristino dello snapshot
 * dentro un aggiornamento non libera il blocco dell'aggiornamento). Un processo PHP ucciso a metà chiude la connessione e
 * MySQL libera il blocco da sé.
 */
function copia_blocco_prendi(): bool {
    $pdo = db();
    $nome = $pdo->quote(copia_blocco_nome());
    $chi = $pdo->query("SELECT IS_USED_LOCK($nome)")->fetchColumn();
    if ($chi !== null && $chi !== false) {
        if ((int)$chi !== (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()) return false;
        $GLOBALS['copia_blocco_livelli'] = (int)($GLOBALS['copia_blocco_livelli'] ?? 0) + 1;
        return true;
    }
    if ((int)$pdo->query("SELECT GET_LOCK($nome, 0)")->fetchColumn() !== 1) return false;
    $GLOBALS['copia_blocco_livelli'] = 1;
    return true;
}

/** Lascia il blocco del ripristino (all'ultimo livello: vedi copia_blocco_prendi()). */
function copia_blocco_lascia(): void {
    $l = (int)($GLOBALS['copia_blocco_livelli'] ?? 0);
    if ($l > 1) { $GLOBALS['copia_blocco_livelli'] = $l - 1; return; }
    $GLOBALS['copia_blocco_livelli'] = 0;
    try { db()->query('SELECT RELEASE_LOCK(' . db()->quote(copia_blocco_nome()) . ')'); } catch (Throwable $e) {}
}

/** Il marcatore di un ripristino in corso (aggiornamenti/copie/.ripristino-in-corso.json). */
function copia_marcatore_file(): string {
    return copia_dir() . '/' . COPIA_MARCATORE;
}

/** I nomi scritti nel marcatore (solo quelli con il marchio di lavoro e il prefisso del sito dentro): ['appoggio' => [...], 'parcheggio' => [...]]. */
function copia_marcatore_leggi(): array {
    $out = ['appoggio' => [], 'parcheggio' => []];
    $f = copia_marcatore_file();
    if (!is_file($f)) return $out;
    $m = json_decode((string)@file_get_contents($f), true);
    if (!is_array($m)) return $out;
    foreach (['appoggio' => COPIA_APPOGGIO, 'parcheggio' => COPIA_PARCHEGGIO] as $k => $marchio) {
        $re = '/^' . $marchio . '[0-9a-f]{6}_' . preg_quote(DB_PREFIX, '/') . '[a-z0-9_]{1,60}$/';
        foreach ((array)($m[$k] ?? []) as $t) if (is_string($t) && preg_match($re, $t)) $out[$k][] = $t;
    }
    return $out;
}

/** Scrive il marcatore con i nomi dati; con nessun nome lo toglie. Ritorna false se non si scrive. */
function copia_marcatore_scrivi(array $appoggio, array $parcheggio): bool {
    $appoggio = array_values(array_unique($appoggio)); $parcheggio = array_values(array_unique($parcheggio));
    if (!$appoggio && !$parcheggio) { @unlink(copia_marcatore_file()); return true; }
    return @file_put_contents(copia_marcatore_file(), json_encode(['appoggio' => $appoggio, 'parcheggio' => $parcheggio, 'prefisso' => DB_PREFIX, 'quando' => date('Y-m-d H:i:s')]), LOCK_EX) !== false;
}

/**
 * Toglie le tabelle di appoggio e di parcheggio rimaste da un ripristino interrotto: solo quelle nominate dal marcatore,
 * solo con il nostro prefisso dentro, e solo quando nessun ripristino è in corso (chi chiama ha già il blocco: un ripristino
 * ancora vivo lo terrebbe). Ritorna i nomi tolti. Se una DROP fallisce, il marcatore resta con i nomi rimasti (e il ripristino
 * dopo li porta nel suo marcatore, copia_ripristina_db(); Copia del sito prova a toglierli a ogni apertura della pagina).
 */
function copia_pulisci_interrotto(): array {
    if (!is_file(copia_marcatore_file())) return [];
    $m = copia_marcatore_leggi();
    $pdo = db();
    $esistenti = array_flip(array_map(fn($r) => (string)$r[0], $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM)));
    $tolte = []; $rimaste = ['appoggio' => [], 'parcheggio' => []];
    foreach ($m as $k => $nomi) {
        foreach ($nomi as $t) {
            if (!isset($esistenti[$t])) continue;
            try { $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`'); $tolte[] = $t; }
            catch (Throwable $e) { $rimaste[$k][] = $t; error_log('copia: tabella di lavoro non tolta ' . $t . ': ' . $e->getMessage()); }
        }
    }
    copia_marcatore_scrivi($rimaste['appoggio'], $rimaste['parcheggio']);
    return $tolte;
}

/**
 * I nomi di appoggio e di parcheggio per le tabelle (suffissi) date: rip<6 esadecimali>_<prefisso><suffisso> e
 * vec<...>. Prima di cominciare: ogni nome sta nei 64 caratteri di MySQL, nessuno esiste già, nessuno comincia con il
 * prefisso del sito (le copie e gli snapshot prendono tutto ciò che comincia con il prefisso). Altrimenti RuntimeException
 * con il nome della tabella, senza toccare niente. Ritorna ['app' => prefisso, 'vec' => prefisso, 'esistenti' => [nome => true]].
 */
function copia_nomi_appoggio(array $suffissi): array {
    $pdo = db();
    // I nomi di lavoro non devono cominciare con il prefisso del sito. L'installatore chiude ogni prefisso con "_", quindi non
    // succede mai; con un prefisso scritto a mano senza (rip1, v, ve...) si cambia la parte casuale, fino a venti volte.
    for ($giro = 0; ; $giro++) {
        $hex = bin2hex(random_bytes(3));
        $app = COPIA_APPOGGIO . $hex . '_'; $vec = COPIA_PARCHEGGIO . $hex . '_';
        if (DB_PREFIX === '' || (!str_starts_with($app, DB_PREFIX) && !str_starts_with($vec, DB_PREFIX))) break;
        if ($giro >= 20) throw new RuntimeException('Il prefisso delle tabelle di questo sito (' . DB_PREFIX . ') comincia come i nomi di lavoro del ripristino (' . COPIA_APPOGGIO . ', ' . COPIA_PARCHEGGIO . '): il ripristino sicuro non può partire con questo prefisso. Niente è stato toccato.');
    }
    $esistenti = array_flip(array_map(fn($r) => (string)$r[0], $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM)));
    foreach ($suffissi as $s) {
        foreach ([$app . DB_PREFIX . $s, $vec . DB_PREFIX . $s] as $nome) {
            if (strlen($nome) > 64) throw new RuntimeException('Il nome di lavoro di una tabella sarebbe troppo lungo per MySQL (oltre 64 caratteri): ' . $nome . '. Il prefisso delle tabelle di questo sito (' . DB_PREFIX . ') è troppo lungo per il ripristino sicuro. Niente è stato toccato.');
            if (isset($esistenti[$nome])) throw new RuntimeException('Nel database esiste già una tabella con il nome di lavoro ' . $nome . ': riprova il ripristino (i nomi di lavoro cambiano a ogni tentativo). Niente è stato toccato.');
        }
    }
    return ['app' => $app, 'vec' => $vec, 'esistenti' => $esistenti];
}

/** Le collazioni e i set di caratteri che questo server conosce (SHOW COLLATION, SHOW CHARACTER SET), una volta per richiesta. */
function copia_server_collazioni(): array {
    static $c = null;
    if ($c !== null) return $c;
    $coll = []; $pred = []; $cs = [];
    foreach (db()->query('SHOW COLLATION')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nome = strtolower((string)($r['Collation'] ?? '')); $set = strtolower((string)($r['Charset'] ?? ''));
        if ($nome === '') continue;
        $coll[$nome] = $set;
        if (strtolower((string)($r['Default'] ?? '')) === 'yes') $pred[$set] = $nome;
    }
    foreach (db()->query('SHOW CHARACTER SET')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $set = strtolower((string)($r['Charset'] ?? ''));
        if ($set !== '') $cs[$set] = true;
    }
    return $c = ['collazioni' => $coll, 'predefinite' => $pred, 'charset' => $cs];
}

/**
 * La collazione da usare al posto di una che questo server non conosce (decisione del 4 novembre 2026): utf8mb4_0900_ai_ci
 * -> utf8mb4_unicode_ci; utf8mb4_0900_bin e utf8mb4_0900_as_cs -> utf8mb4_bin; le altre utf8mb4_0900_* -> utf8mb4_unicode_ci;
 * utf8mb3_* -> utf8_* con lo stesso suffisso se conosciuta, altrimenti utf8_unicode_ci; qualunque altra -> la predefinita del
 * suo set di caratteri qui (SHOW COLLATION, Default = Yes). Una conosciuta torna com'è; '' se non si trova niente.
 */
function copia_collazione_vicina(string $nome): string {
    $s = copia_server_collazioni();
    $nome = strtolower($nome);
    if (isset($s['collazioni'][$nome])) return $nome;
    $c = fn(string $x): string => isset($s['collazioni'][$x]) ? $x : '';
    if (str_starts_with($nome, 'utf8mb4_0900_')) {
        if (in_array($nome, ['utf8mb4_0900_bin', 'utf8mb4_0900_as_cs'], true)) return $c('utf8mb4_bin') ?: ($s['predefinite']['utf8mb4'] ?? '');
        return $c('utf8mb4_unicode_ci') ?: ($s['predefinite']['utf8mb4'] ?? '');
    }
    // MySQL 5.7 e MariaDB fino alla 10.5 elencano utf8_*, MySQL 8.0.30+ e MariaDB 10.6+ elencano utf8mb3_* (e accettano utf8_*
    // come alias): una sconosciuta si cerca prima con l'altro nome e lo stesso suffisso (utf8_bin -> utf8mb3_bin, utf8mb3_bin ->
    // utf8_bin: mai una general_ci al posto di una bin o di una unicode_ci), poi con unicode_ci, poi la predefinita del set.
    if (str_starts_with($nome, 'utf8mb3_')) return $c('utf8_' . substr($nome, 8)) ?: $c('utf8_unicode_ci') ?: $c('utf8mb3_unicode_ci') ?: ($s['predefinite']['utf8'] ?? $s['predefinite']['utf8mb3'] ?? '');
    if (str_starts_with($nome, 'utf8_')) return $c('utf8mb3_' . substr($nome, 5)) ?: $c('utf8mb3_unicode_ci') ?: $c('utf8_unicode_ci') ?: ($s['predefinite']['utf8mb3'] ?? $s['predefinite']['utf8'] ?? '');
    $set = explode('_', $nome, 2)[0];
    $alias = ['utf8mb3' => 'utf8', 'utf8' => 'utf8mb3'];
    return $s['predefinite'][$set] ?? $s['predefinite'][$alias[$set] ?? ''] ?? '';
}

/** Il set di caratteri da usare al posto di uno che questo server non elenca: utf8mb3 -> utf8 e utf8 -> utf8mb3 (alias). '' se non c'è. */
function copia_charset_vicino(string $nome): string {
    $s = copia_server_collazioni();
    $nome = strtolower($nome);
    if (isset($s['charset'][$nome])) return $nome;
    if ($nome === 'utf8mb3' && isset($s['charset']['utf8'])) return 'utf8';
    if ($nome === 'utf8' && isset($s['charset']['utf8mb3'])) return 'utf8mb3';
    return '';
}

/**
 * Spezza una riga SQL in pezzi fuori e dentro gli apici: '...' e "..." con gli escape con la barra e l'apice raddoppiato,
 * `...` con l'apice inverso raddoppiato. Ritorna [[testo, dentroApici], ...], nell'ordine. Le sostituzioni e i controlli si
 * fanno solo sui pezzi fuori dagli apici: un DEFAULT o un COMMENT con la parola COLLATE dentro resta com'è.
 */
function copia_sql_pezzi(string $sql): array {
    $out = []; $len = strlen($sql); $i = 0;
    while ($i < $len) {
        $j = strcspn($sql, "'`\"", $i);
        if ($j > 0) $out[] = [substr($sql, $i, $j), false];
        $i += $j;
        if ($i >= $len) break;
        $q = $sql[$i]; $k = $i + 1;
        while (true) {
            $k += strcspn($sql, $q === '`' ? '`' : $q . '\\', $k);
            if ($k >= $len) { $k = $len; break; }
            if ($sql[$k] === '\\') { $k += 2; continue; }
            if ($k + 1 < $len && $sql[$k + 1] === $q) { $k += 2; continue; }
            $k++; break;
        }
        $k = min($k, $len);
        $out[] = [substr($sql, $i, $k - $i), true];
        $i = $k;
    }
    return $out;
}

/**
 * Riscrive una CREATE TABLE per questo server: le collazioni (COLLATE=x della tabella, COLLATE x delle colonne), i set di
 * caratteri (DEFAULT CHARSET=x, CHARACTER SET x) e gli introduttori davanti alle stringhe (_utf8mb3'...' nelle colonne
 * generate) che il server non conosce diventano i più vicini conosciuti. Solo fuori dagli apici; AUTO_INCREMENT=N e tutto
 * il resto restano intatti. In $cambi si contano le tabelle con la sostituzione ('collazione x -> y' => quante CREATE).
 */
function copia_create_portabile(string $riga, array &$cambi): string {
    $pezzi = copia_sql_pezzi($riga);
    $out = '';
    // Una sostituzione si conta una volta per CREATE (per tabella), anche se compare sulla tabella e su più colonne.
    $fatti = [];
    $conta = function (string $k) use (&$cambi, &$fatti): void { if (isset($fatti[$k])) return; $fatti[$k] = true; $cambi[$k] = ($cambi[$k] ?? 0) + 1; };
    foreach ($pezzi as $i => [$t, $dentro]) {
        if ($dentro) { $out .= $t; continue; }
        $t = preg_replace_callback('/\b(COLLATE\s*=?\s*)([A-Za-z0-9_]+)/i', function ($m) use ($conta) {
            $v = copia_collazione_vicina($m[2]);
            if ($v === '' || strcasecmp($v, $m[2]) === 0) return $m[0];
            $conta('la collazione ' . strtolower($m[2]) . ' -> ' . $v);
            return $m[1] . $v;
        }, $t);
        $t = preg_replace_callback('/\b((?:CHARSET|CHARACTER\s+SET)\s*=?\s*)([A-Za-z0-9_]+)/i', function ($m) use ($conta) {
            $v = copia_charset_vicino($m[2]);
            if ($v === '' || strcasecmp($v, $m[2]) === 0) return $m[0];
            $conta('il set di caratteri ' . strtolower($m[2]) . ' -> ' . $v);
            return $m[1] . $v;
        }, $t);
        // L'introduttore (_utf8mb3) sta subito prima di una stringa fra apici.
        if (isset($pezzi[$i + 1]) && $pezzi[$i + 1][1]) {
            $t = preg_replace_callback('/(^|[\s(,=])_([A-Za-z0-9]+)$/', function ($m) use ($conta) {
                $v = copia_charset_vicino($m[2]);
                if ($v === '' || strcasecmp($v, $m[2]) === 0) return $m[0];
                $conta('il set di caratteri ' . strtolower($m[2]) . ' -> ' . $v);
                return $m[1] . '_' . $v;
            }, $t);
        }
        $out .= $t;
    }
    return $out;
}

/**
 * sicurezza (2.25.1): una CREATE TABLE della copia ha solo quello che copia_dump_db() e agg_dump_db() scrivono (lo SHOW CREATE
 * TABLE di una tabella di plumeCMS)? Fuori dagli apici non devono comparire: SELECT e LIKE (CREATE ... AS SELECT, CREATE ... LIKE),
 * REFERENCES (un vincolo verso un'altra tabella: con due installazioni nello stesso database legherebbe una tabella di questo sito a
 * una dell'altro, che poi non si cancella più, o cancella righe di questo sito a cascata), UNION= (una tabella MERGE sopra altre
 * tabelle), CONNECTION= (FEDERATED verso un altro server), DATA DIRECTORY e INDEX DIRECTORY (file fuori dal database), TABLESPACE.
 * plumeCMS non scrive mai nessuna di queste parole nelle sue tabelle. Ritorna '' se la riga va bene, altrimenti la parola trovata.
 */
function copia_create_ammessa(string $riga): string {
    foreach (copia_sql_pezzi($riga) as [$t, $dentro]) {
        if ($dentro) continue;
        if (preg_match('/\b(?:SELECT|LIKE|REFERENCES|TABLESPACE|DATA\s+DIRECTORY|INDEX\s+DIRECTORY)\b|\b(?:UNION|CONNECTION)\s*=/i', $t, $m)) return strtoupper(preg_replace(['/\s+/', '/\s*=/'], [' ', '='], trim($m[0])));
    }
    return '';
}

/**
 * sicurezza (2.25.1): la parte VALUES di un INSERT contiene solo valori letterali? Stringhe fra apici (come le scrive
 * agg_sql_val), numeri, NULL, DEFAULT, parentesi e virgole. Una parentesi seguita da SELECT, un nome di funzione (NOW()), una
 * variabile o un nome fra apici inversi la fanno rifiutare: con due installazioni nello stesso database una copia non firmata
 * potrebbe altrimenti copiare dati dell'altra installazione dentro una pagina pubblica.
 */
function copia_insert_letterale(string $riga): bool {
    $p = strpos($riga, ' VALUES ');
    if ($p === false) return false;
    foreach (copia_sql_pezzi(substr($riga, $p + 8)) as [$t, $dentro]) {
        if ($dentro) { if ($t[0] === '`') return false; continue; }
        if (!preg_match('/^(?:\s|[(),;]|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|NULL|DEFAULT)*$/i', $t)) return false;
    }
    return true;
}

/** La tabella di una riga del dump, dal nome di lavoro: il suffisso senza i prefissi (per i messaggi). */
function copia_suffisso_di(string $nomeLavoro, string $prefissoLavoro): string {
    return substr($nomeLavoro, strlen($prefissoLavoro) + strlen(DB_PREFIX));
}

/**
 * Un errore del database in parole semplici (regola 4.1 delle regole di interfaccia): cosa è successo e cosa fare; il testo
 * tecnico in fondo fra parentesi. $tabella: la tabella (senza prefisso) su cui si lavorava.
 */
function copia_errore_parole(Throwable $e, string $tabella = ''): string {
    $msg = $e->getMessage();
    $cod = (int)(($e instanceof PDOException && isset($e->errorInfo[1])) ? $e->errorInfo[1] : 0);
    if ($cod === 0 && preg_match('/\b(1\d{3}|2\d{3}|3\d{3})\b/', $msg, $mm)) $cod = (int)$mm[1];
    $dove = $tabella !== '' ? ' (tabella ' . $tabella . ')' : '';
    $parole = match ($cod) {
        1273 => 'La copia usa un ordinamento dei caratteri (collazione) che questo server non conosce' . $dove . '. Fai la copia da un server con un database più vicino a questo, oppure chiedi all\'assistenza dell\'hosting di aggiornare il database.',
        1115 => 'La copia usa un set di caratteri che questo server non conosce' . $dove . '.',
        1286 => 'La copia usa un motore di tabelle (ENGINE) che questo server non ha' . $dove . '. Il database di questo hosting è diverso da quello che ha fatto la copia.',
        1062 => 'Due righe della copia risultano uguali per questo server' . $dove . ': con la collazione sostituita, due testi diversi sul server di origine qui coincidono. Correggi o togli una delle due righe sul sito di origine, rifai la copia e riprova.',
        1146 => 'La copia ha dati per una tabella di cui non ha la struttura' . $dove . ': è incompleta o modificata. Rifai la copia dal sito di origine.',
        1050 => 'La copia ha la stessa tabella due volte' . $dove . ': è stata modificata. Rifai la copia dal sito di origine.',
        1205, 1213 => 'Il database era occupato da un altro lavoro (il cron, un invio della newsletter): aspetta un minuto e riprova.',
        1142, 1044 => 'L\'utente del database di questo sito non ha il permesso di creare o rinominare tabelle' . $dove . '. Dal pannello dell\'hosting dai all\'utente tutti i privilegi sul database.',
        1114, 1030, 1021, 3 => 'Lo spazio del database (o del disco) è finito' . $dove . '. Libera spazio dal pannello dell\'hosting e riprova.',
        1071 => 'Una chiave della copia è troppo lunga per questo server' . $dove . ': il database dell\'hosting è una versione vecchia (MySQL 5.6, o 5.7 con il formato delle righe vecchio).',
        2006, 2013 => 'La connessione al database è caduta durante il ripristino: la copia è grande o il server è lento. Riprova; se succede ancora, chiedi all\'hosting di alzare i tempi massimi del database.',
        default => 'Il database ha rifiutato un\'istruzione della copia' . $dove . '.',
    };
    return $parole . ' (' . mb_substr($msg, 0, 400) . ')';
}

/**
 * Ripristina il database da db.sql.gz di una copia (2.25.1: senza mai cancellare prima di sapere che la copia si ricrea).
 * Passano solo le istruzioni del formato delle copie (CREATE TABLE e INSERT ... VALUES su tabelle con il prefisso della copia,
 * una per riga; le DROP si ignorano: non servono più), riscritte con un prefisso di appoggio; alla fine una sola RENAME TABLE
 * mette le tabelle al loro nome vero. Chi chiama ha già il blocco (copia_blocco_prendi()).
 * $sitoUrl: l'indirizzo di questa installazione, scritto nella settings di appoggio prima dello scambio (null = non toccare).
 * $manifest: il manifest della copia (server_versione per il registro).
 * Ritorna ['ok', 'eseguite', 'saltate', 'altre', 'errori' => [...], 'motivo' (parole semplici se non ok), 'tabelle' => suffissi
 * rimessi, 'nuove' => suffissi che il sito non aveva, 'solo_sito' => tabelle del sito non nella copia, 'righe' => righe per il
 * registro].
 */
function copia_ripristina_db(string $gzPath, string $prefissoCopia, ?array $suffissi = null, ?string $sitoUrl = null, array $manifest = []): array {
    $pdo = db();
    agg_sql_mode();
    $righe = []; $errori = [];
    $esci = function (string $motivo) use (&$righe, &$errori): array {
        return ['ok' => false, 'eseguite' => 0, 'saltate' => 0, 'altre' => 0, 'errori' => $errori, 'motivo' => $motivo, 'tabelle' => [], 'nuove' => [], 'solo_sito' => [], 'righe' => $righe];
    };
    // 2.1: quali tabelle della copia sono del sito (senza il prefisso). Dal manifest; per una copia vecchia, dai nomi delle
    // tabelle create nel suo database, togliendo quelle di un'altra installazione con un prefisso più lungo.
    if ($suffissi === null) {
        $gz = gzopen($gzPath, 'rb');
        if (!$gz) throw new RuntimeException('Il database della copia non si legge.');
        $nomi = [];
        while (($riga = gzgets($gz, 1048576)) !== false) {
            if (preg_match('~^CREATE TABLE `([a-z0-9_]{1,90})`~', $riga, $mm)) $nomi[] = $mm[1];
            if (!str_ends_with($riga, "\n")) while (($resto = gzgets($gz, 1048576)) !== false && !str_ends_with($resto, "\n"));
        }
        gzclose($gz);
        $suffissi = array_map(fn($t) => substr($t, strlen($prefissoCopia)), copia_tabelle_del_prefisso($nomi, $prefissoCopia));
    }
    $suffissi = array_values(array_unique(array_filter(array_map('strval', $suffissi), fn($s) => preg_match('/^[a-z0-9_]{1,60}$/', $s))));
    $ammesse = array_flip($suffissi);
    // E mai una tabella di un'altra installazione che sta in questo database (cms_b_ accanto a cms_), qualunque cosa dica la
    // copia: una copia caricata con un elenco inventato ("b_settings") non arriva alle tabelle dell'altro sito.
    $tutte = array_map(fn($r) => (string)$r[0], $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));
    $altriQui = copia_altri_prefissi($tutte, DB_PREFIX);
    $mie = array_flip(copia_tabelle_sito()); // le tabelle vere di questa installazione (senza le viste)
    // 1. Le tabelle rimaste da un ripristino interrotto, poi i nomi di lavoro, poi il marcatore: prima di toccare il database.
    $tolte = copia_pulisci_interrotto();
    if ($tolte) $righe[] = 'Tabelle di lavoro rimaste da un ripristino interrotto, tolte: ' . count($tolte) . ' (' . implode(', ', array_slice($tolte, 0, 8)) . (count($tolte) > 8 ? '...' : '') . ').';
    // Le tabelle che una DROP non ha tolto restano nel marcatore, insieme ai nomi di questo ripristino: nessuna si perde.
    $eredita = copia_marcatore_leggi();
    if ($eredita['appoggio'] || $eredita['parcheggio']) $righe[] = 'ATTENZIONE: ' . count(array_merge($eredita['appoggio'], $eredita['parcheggio'])) . ' tabelle di lavoro di un ripristino interrotto non si sono cancellate (restano nel marcatore per il ripristino dopo).';
    $marca = fn(array $appoggio, array $parcheggio): bool => copia_marcatore_scrivi(array_merge($eredita['appoggio'], $appoggio), array_merge($eredita['parcheggio'], $parcheggio));
    $suffissiAmmessi = array_values(array_filter($suffissi, fn($s) => !copia_di_altri(DB_PREFIX . $s, $altriQui)));
    try { $nomi = copia_nomi_appoggio($suffissiAmmessi); } catch (RuntimeException $e) { return $esci($e->getMessage()); }
    $app = $nomi['app']; $vec = $nomi['vec']; $esistenti = $nomi['esistenti'];
    $maxPacket = (int)$pdo->query('SELECT @@max_allowed_packet')->fetchColumn();
    if (!$marca(array_map(fn($s) => $app . DB_PREFIX . $s, $suffissiAmmessi), array_map(fn($s) => $vec . DB_PREFIX . $s, $suffissiAmmessi))) return $esci('Non riesco a scrivere il file di lavoro del ripristino in aggiornamenti/copie/ (permessi della cartella, o disco pieno). Niente è stato toccato.');
    // 2. La copia, tabella per tabella, nelle tabelle di appoggio.
    $gz = gzopen($gzPath, 'rb');
    if (!$gz) { $marca([], []); throw new RuntimeException('Il database della copia non si legge.'); }
    $p = preg_quote($prefissoCopia, '~');
    $n = 0; $saltate = 0; $altre = 0; $create = []; $cambi = []; $motivo = '';
    $fkOn = function () use ($pdo): void { try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $e) {} };
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    while (($riga = gzgets($gz)) !== false) {
        $riga = rtrim($riga, "\r\n");
        if ($riga === '') continue;
        if (in_array($riga, ["SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';", 'SET FOREIGN_KEY_CHECKS=0;', 'SET FOREIGN_KEY_CHECKS=1;'], true)) continue;
        // Le viste (cms_v_published_articles...) le crea lo schema del sito, con il suo prefisso: quelle della copia no.
        if (str_starts_with($riga, 'DROP VIEW IF EXISTS `') || preg_match('~^CREATE (ALGORITHM=\S+ )?(DEFINER=\S+ )?(SQL SECURITY \S+ )?VIEW `~', $riga)) continue;
        // sicurezza: solo queste tre forme, su una tabella del prefisso della copia (nome semplice fra apici inversi).
        if (!preg_match('~^(DROP TABLE IF EXISTS |CREATE TABLE |INSERT INTO )`' . $p . '([a-z0-9_]{1,60})`~', $riga, $m)) { $saltate++; continue; }
        // 2.1: una tabella di un'altra installazione (prefisso più lungo) che una copia vecchia si era portata dietro.
        if (!isset($ammesse[$m[2]]) || copia_di_altri(DB_PREFIX . $m[2], $altriQui)) { $altre++; continue; }
        // Una riga, una istruzione: un secondo comando dopo un ";" (fuori dalle stringhe) fa saltare la riga intera.
        // E solo la forma che scrive agg_dump_db: CREATE TABLE con le colonne (non "AS SELECT" ne "LIKE"), INSERT con VALUES.
        $forma = match ($m[1]) {
            'DROP TABLE IF EXISTS ' => (bool)preg_match('~^DROP TABLE IF EXISTS `[a-z0-9_]+`;?$~', $riga),
            'CREATE TABLE ' => (bool)preg_match('~^CREATE TABLE `[a-z0-9_]+` \(~', $riga),
            default => (bool)preg_match('~^INSERT INTO `[a-z0-9_]+` VALUES \(~', $riga),
        };
        if (!$forma || ($m[1] !== 'DROP TABLE IF EXISTS ' && count(agg_split_sql($riga)) !== 1)) { $saltate++; continue; }
        if ($m[1] === 'DROP TABLE IF EXISTS ') continue; // 2.25.1: le DROP della copia non servono più: l'appoggio nasce vuoto
        $suf = $m[2];
        $riga = $m[1] . '`' . $app . DB_PREFIX . $suf . '`' . substr($riga, strlen($m[0]));
        if (substr($riga, -1) === ';') $riga = substr($riga, 0, -1);
        if ($m[1] === 'CREATE TABLE ') {
            // sicurezza: niente vincoli o rimandi verso altre tabelle o verso file (REFERENCES, UNION=...), fuori dagli apici.
            $parola = copia_create_ammessa($riga);
            if ($parola !== '') { $motivo = 'La copia contiene un\'istruzione che ' . CMS_NOME . ' non scrive mai nelle copie (nella struttura della tabella ' . $suf . ' c\'è ' . $parola . ': un vincolo o un rimando verso un\'altra tabella, un altro server o un file): è stata modificata. Il ripristino non si fa.'; $errori[] = 'CREATE TABLE con ' . $parola . ' nella tabella ' . $suf; break; }
            $riga = copia_create_portabile($riga, $cambi);
        } else {
            // sicurezza: solo valori letterali; e la riga deve stare in max_allowed_packet del server di arrivo.
            if (!copia_insert_letterale($riga)) { $motivo = 'La copia contiene un\'istruzione che ' . CMS_NOME . ' non scrive mai nelle copie (una funzione o una sottoquery dentro i dati della tabella ' . $suf . '): è stata modificata. Il ripristino non si fa.'; $errori[] = 'INSERT con valori non letterali nella tabella ' . $suf; break; }
            if ($maxPacket > 0 && strlen($riga) > $maxPacket) { $motivo = 'Una riga della copia pesa ' . agg_fmt_bytes(strlen($riga)) . ' (tabella ' . $suf . '), il server ne accetta al massimo ' . agg_fmt_bytes($maxPacket) . ' (max_allowed_packet). Chiedi all\'assistenza dell\'hosting di alzare max_allowed_packet, oppure rifai la copia con ' . CMS_NOME . ' 2.25.1 o successivo, che scrive righe più piccole.'; $errori[] = 'riga di ' . strlen($riga) . ' byte oltre max_allowed_packet (' . $maxPacket . ') nella tabella ' . $suf; break; }
        }
        try {
            $pdo->exec($riga);
            $n++;
            if ($m[1] === 'CREATE TABLE ') $create[$suf] = true;
            if (defined('CMS_RIPRISTINO_PAUSA')) usleep((int)CMS_RIPRISTINO_PAUSA); // solo per le prove (un ripristino lento da interrompere)
        } catch (Throwable $e) {
            // Nel registro mai i valori di un INSERT (ci sono le password cifrate degli utenti): solo la tabella e il peso della riga.
            $errori[] = ($m[1] === 'CREATE TABLE ' ? substr($riga, 0, 80) . ' ...' : 'INSERT INTO `' . $app . DB_PREFIX . $suf . '` (riga di ' . agg_fmt_bytes(strlen($riga)) . ', valori non riportati)') . ' : ' . mb_substr($e->getMessage(), 0, 300);
            $motivo = copia_errore_parole($e, $suf);
            break;
        }
    }
    gzclose($gz);
    if ($motivo === '' && !$create) { $motivo = 'Nella copia non c\'è nessuna tabella di questo sito (prefisso ' . $prefissoCopia . ' nella copia, ' . count($suffissi) . ' nell\'elenco). Il ripristino non si fa.'; }
    // 3. Prima dello scambio: nella settings di appoggio l'indirizzo di questa installazione e nessuna impronta degli schemi
    // (sito_schema, sicurezza_schema, newsletter_schema...: al primo accesso le colonne nuove si aggiungono da sole).
    if ($motivo === '' && isset($create['settings'])) {
        try {
            // Le impronte (*_impronta) e i segni di versione degli schemi (menu_schema, modelli_schema, scaricabili_schema...):
            // tutti marcatori che il primo accesso ricalcola; mai scaricabili_impronte, che sono dati (i download contati).
            $pdo->exec('DELETE FROM `' . $app . DB_PREFIX . "settings` WHERE setting_key LIKE '%impronta' OR setting_key LIKE '%\\_schema'");
            if ($sitoUrl !== null && $sitoUrl !== '') {
                $st = $pdo->prepare('INSERT INTO `' . $app . DB_PREFIX . 'settings` (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2');
                $st->execute([':k' => 'sito_url', ':v' => $sitoUrl, ':v2' => $sitoUrl]);
            }
        } catch (Throwable $e) { $errori[] = 'settings di appoggio: ' . $e->getMessage(); $motivo = copia_errore_parole($e, 'settings'); }
    }
    if ($motivo !== '') {
        // Niente è cambiato: via le tabelle di appoggio, via il marcatore (un appoggio che non si cancella resta nel marcatore).
        $nonTolte = [];
        foreach (array_keys($create) as $suf) { try { $pdo->exec('DROP TABLE IF EXISTS `' . $app . DB_PREFIX . $suf . '`'); } catch (Throwable $e) { $nonTolte[] = $app . DB_PREFIX . $suf; error_log('copia: appoggio non tolto: ' . $e->getMessage()); } }
        $marca($nonTolte, []);
        $fkOn();
        $r = $esci($motivo);
        $r['eseguite'] = $n; $r['saltate'] = $saltate; $r['altre'] = $altre;
        return $r;
    }
    // 4. Lo scambio, in una sola RENAME TABLE: le tabelle vive vanno in parcheggio, quelle di appoggio al nome vero.
    $coppie = []; $parcheggiate = []; $nuove = [];
    foreach (array_keys($create) as $suf) {
        $vivo = DB_PREFIX . $suf;
        if (isset($mie[$vivo])) { $coppie[] = '`' . $vivo . '` TO `' . $vec . $vivo . '`'; $parcheggiate[] = $vec . $vivo; }
        elseif (isset($esistenti[$vivo])) {
            // Il nome esiste ma non è una tabella di questa installazione (una vista con quel nome): non si scambia.
            $nonTolte = [];
            foreach (array_keys($create) as $s2) { try { $pdo->exec('DROP TABLE IF EXISTS `' . $app . DB_PREFIX . $s2 . '`'); } catch (Throwable $e) { $nonTolte[] = $app . DB_PREFIX . $s2; } }
            $marca($nonTolte, []);
            $fkOn();
            return $esci('Nel database c\'è già qualcosa con il nome ' . $vivo . ' che non è una tabella di questo sito: il ripristino non si fa.');
        } else $nuove[] = $suf;
        $coppie[] = '`' . $app . $vivo . '` TO `' . $vivo . '`';
    }
    // Il tempo massimo di attesa dei blocchi vale solo per la RENAME (una query lunga in corso, il cron, non ferma tutto per
    // sempre): dopo torna quello di prima, perché la connessione serve ancora alle pagine di questa richiesta.
    $attesaPrima = 0;
    try { $attesaPrima = (int)$pdo->query('SELECT @@session.lock_wait_timeout')->fetchColumn(); } catch (Throwable $e) {}
    $attesaTorna = function () use ($pdo, $attesaPrima): void { if ($attesaPrima > 0) { try { $pdo->exec('SET SESSION lock_wait_timeout = ' . $attesaPrima); } catch (Throwable $e) {} } };
    try {
        $pdo->exec('SET SESSION lock_wait_timeout = 10');
        $pdo->exec('RENAME TABLE ' . implode(', ', $coppie));
    } catch (Throwable $e) {
        $attesaTorna();
        $nonTolte = [];
        foreach (array_keys($create) as $suf) { try { $pdo->exec('DROP TABLE IF EXISTS `' . $app . DB_PREFIX . $suf . '`'); } catch (Throwable $e2) { $nonTolte[] = $app . DB_PREFIX . $suf; } }
        $marca($nonTolte, []);
        $fkOn();
        $errori[] = 'RENAME TABLE: ' . $e->getMessage();
        return $esci('Lo scambio delle tabelle non è riuscito, e il sito è rimasto com\'era. ' . copia_errore_parole($e));
    }
    $attesaTorna();
    // 5. Dopo lo scambio il database È quello della copia: da qui nessuna eccezione esce (ogni passo in try), così chi chiama
    // non può scambiare un intoppo di pulizia per un ripristino non fatto. Via le tabelle parcheggiate, le viste rifatte
    // (SELECT * congela le colonne di quando sono nate).
    $nonTolte = [];
    foreach ($parcheggiate as $t) { try { $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`'); } catch (Throwable $e) { $nonTolte[] = $t; error_log('copia: parcheggio non tolto ' . $t . ': ' . $e->getMessage()); } }
    $fkOn();
    if ($nonTolte) $righe[] = 'ATTENZIONE: ' . count($nonTolte) . ' tabelle vecchie non si sono cancellate (restano in parcheggio, le toglie il ripristino dopo, o Copia del sito alla prossima apertura): ' . implode(', ', $nonTolte) . '.';
    $marca([], $nonTolte);
    try {
        require_once __DIR__ . '/sito.php';
        if (function_exists('sito_viste')) sito_viste();
    } catch (Throwable $e) { $righe[] = 'ATTENZIONE: le viste degli articoli non si sono rifatte (' . mb_substr($e->getMessage(), 0, 200) . '): si rifanno da sole al primo accesso al pannello.'; }
    // Il registro.
    $soloSito = array_values(array_filter(array_keys($mie), fn($t) => !isset($create[substr($t, strlen(DB_PREFIX))])));
    $righe[] = 'Database: ' . count($create) . ' tabelle rimesse dalla copia, ' . $n . ' istruzioni eseguite' . ($saltate ? ', ' . $saltate . ' righe non ammesse saltate' : '') . '.';
    if ($nuove) $righe[] = 'Tabelle della copia che il sito non aveva: ' . count($nuove) . ' (' . implode(', ', array_slice($nuove, 0, 20)) . (count($nuove) > 20 ? '...' : '') . ').';
    if ($soloSito) $righe[] = 'Tabelle del sito che non stanno nella copia, lasciate come sono: ' . count($soloSito) . ' (' . implode(', ', array_map(fn($t) => substr($t, strlen(DB_PREFIX)), array_slice($soloSito, 0, 20))) . (count($soloSito) > 20 ? '...' : '') . ').';
    if ($cambi) {
        $origine = trim((string)($manifest['server_versione'] ?? ''));
        $qui = copia_server_versione();
        $parti = [];
        foreach ($cambi as $k => $q) $parti[] = 'in ' . $q . ($q === 1 ? ' tabella ' : ' tabelle ') . $k;
        $righe[] = 'Copia fatta su ' . ($origine !== '' ? copia_nome_server($origine) : 'un server di cui la copia non dice la versione') . ', qui ' . ($qui !== '' ? copia_nome_server($qui) : 'un server sconosciuto') . ': ' . implode('; ', $parti) . '. Le CREATE sono state adattate a questo server.';
    }
    return ['ok' => true, 'eseguite' => $n, 'saltate' => $saltate, 'altre' => $altre, 'errori' => [], 'motivo' => '', 'tabelle' => array_keys($create), 'nuove' => $nuove, 'solo_sito' => $soloSito, 'righe' => $righe];
}

/** 2.25.1: il file del registro di un ripristino (aggiornamenti/copie/<copia>.ripristino.log). */
function copia_registro_file(string $copia): string {
    return copia_dir() . '/' . basename($copia) . '.ripristino.log';
}

/**
 * 2.25.1: scrive il registro di un ripristino su file, prima di disegnare la pagina: così si vede anche se la sessione cade
 * (la copia ha altri utenti) o la pagina non arriva. Prima riga: "plumeCMS ripristino <data> esito=ok|errore copia=<nome>".
 * Si tengono gli ultimi COPIA_REGISTRI_DA_TENERE. Ritorna il nome del file (o '' se non si scrive).
 */
function copia_registro_scrivi(string $copia, bool $ok, array $log): string {
    $f = copia_registro_file($copia);
    $testo = CMS_NOME . ' ripristino ' . date('Y-m-d H:i:s') . ' esito=' . ($ok ? 'ok' : 'errore') . ' copia=' . basename($copia) . "\n" . implode("\n", $log) . "\n";
    if (@file_put_contents($f, $testo, LOCK_EX) === false) { error_log('copia: registro non scritto: ' . $f); return ''; }
    @chmod($f, 0600);
    $tutti = copia_registri();
    foreach (array_slice($tutti, COPIA_REGISTRI_DA_TENERE) as $v) @unlink($v['file']);
    return basename($f);
}

/** 2.25.1: i registri dei ripristini sul server, dal più recente: file, copia, data, ok, righe. */
function copia_registri(): array {
    $out = [];
    foreach ((array)@scandir(copia_dir()) as $f) {
        if (!is_string($f) || !preg_match('/^copia-\d{8}-\d{6}-[a-z0-9-]{1,40}\.zip\.ripristino\.log$/', $f)) continue;
        $p = copia_dir() . '/' . $f;
        $righe = preg_split('/\r?\n/', rtrim((string)@file_get_contents($p, false, null, 0, 262144)));
        $testa = (string)array_shift($righe);
        $out[] = ['file' => $p, 'copia' => substr($f, 0, -strlen('.ripristino.log')), 'data' => (int)filemtime($p), 'ok' => str_contains($testa, 'esito=ok'), 'righe' => $righe];
    }
    usort($out, fn($a, $b) => $b['data'] <=> $a['data'] ?: strcmp($b['file'], $a['file']));
    return $out;
}

/** 2.25.1: l'ultimo registro di ripristino, o null. */
function copia_registro_ultimo(): ?array {
    return copia_registri()[0] ?? null;
}

/**
 * Ripristina il sito da una copia sul server (aggiornamenti/copie/<file>). Prima fa una copia del sito com'è adesso.
 * $tieni (2.1): true = i file che ci sono nel sito ma non nella copia restano dove sono (come fino alla 2.0); false (il
 * predefinito) = si spostano nello scarto, e uploads/, temi/ e moduli/ diventano come nella copia.
 * 2.25.1: il database si rimette con copia_ripristina_db() (tabelle di appoggio e scambio): se non riesce ci si ferma PRIMA dei
 * file e il sito è come prima; il blocco del ripristino vale per tutto il lavoro; i file di uploads/ di una copia non firmata
 * passano da media_ha_codice(); se i file falliscono a metà, ok=false e il registro dice di ripristinare la copia
 * prima-del-ripristino; il registro si scrive sempre su file e in sicurezza_log.
 * Ritorna ['ok', 'log' => righe, 'prima' => la copia di sicurezza, 'scarto' => ['cartella', 'file' => [...]] oppure null,
 * 'fase' => 'prima' (niente cambiato) | 'database' (database rimesso, file no) | 'file' (database e file rimessi, errore solo
 * nelle operazioni finali: i file in più da mettere da parte o le protezioni dei temi) | 'fatto', 'registro' => il file del registro].
 */
function copia_ripristina(string $file, bool $tieni = false): array {
    @set_time_limit(0);
    @ignore_user_abort(true);
    $zipPath = copia_percorso($file);
    $m = copia_leggi_manifest($zipPath);
    $firmata = copia_firmata($zipPath, $m);
    $log = []; $prima = ''; $scarto = null; $fase = 'prima'; $ok = true;
    $log[] = 'Ripristino cominciato il ' . date('d/m/Y') . ' alle ' . date('H:i:s') . ' dalla copia ' . basename($zipPath) . '.';
    $log[] = 'Copia letta: fatta il ' . ($m['creato'] ?? '?') . ' da ' . CMS_NOME . ' ' . ($m['cms_versione'] ?? '?') . ' su ' . ($m['sito'] ?? '?') . ', ' . (int)($m['tabelle'] ?? 0) . ' tabelle, ' . (int)($m['file'] ?? 0) . ' file.';
    $log[] = $firmata ? 'Firmata da questo sito: entrano anche i temi e i moduli con PHP.' : 'Non firmata da questo sito (viene da un altro sito, o è stata modificata): i file eseguibili (.php e simili) si saltano.';
    $chiude = function () use (&$log, &$ok, &$prima, &$scarto, &$fase, $zipPath, $firmata): array {
        $registro = copia_registro_scrivi(basename($zipPath), $ok, $log); // sempre, prima di tutto il resto
        $esito = $ok ? 'sito ripristinato dalla copia ' : match ($fase) { 'prima' => 'ripristino NON fatto (sito come prima) dalla copia ', 'database' => 'ripristino con errori nei file dalla copia ', default => 'ripristino fatto, con errori nelle operazioni finali, dalla copia ' };
        try { if (function_exists('sicurezza_log')) sicurezza_log('copia', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), $esito . basename($zipPath) . ($firmata ? ' (firmata)' : ' (non firmata)')); }
        catch (Throwable $e) { error_log('copia: registro della sicurezza non scritto: ' . $e->getMessage()); }
        return ['ok' => $ok, 'log' => $log, 'prima' => $prima, 'scarto' => $scarto, 'fase' => $fase, 'registro' => $registro];
    };
    // sicurezza (2.25.1): un ripristino alla volta. Un secondo clic, o un'altra scheda, non ne fa partire due; e nemmeno durante
    // un aggiornamento (agg_applica() tiene lo stesso blocco).
    if (!copia_blocco_prendi()) {
        $ok = false;
        $log[] = 'Il ripristino non si è fatto: un altro ripristino è già in corso su questo sito (o un aggiornamento, che se qualcosa va male rimette il sito da solo). Aspetta che finisca (la pagina di chi lo ha avviato lo dice), poi riprova. Il sito è come prima.';
        return $chiude();
    }
    $tmp = copia_dir() . '/.ripristino-' . bin2hex(random_bytes(6)) . '.sql.gz';
    $zip = null;
    try {
        // 1. La rete di sicurezza: il sito di adesso, in una copia.
        // 2.1: la copia che si ripristina non si toglie nella pulizia (prima, con sei copie più nuove, spariva a metà).
        $prima = copia_crea('prima-del-ripristino', [basename($zipPath)]);
        $log[] = 'Copia di sicurezza del sito di prima: ' . $prima . ' (la trovi nell\'elenco, si ripristina come le altre).';
        $sitoUrl = (string)get_setting('sito_url', '');
        // 2. Database.
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('La copia non si apre.');
        $in = $zip->getStream('db.sql.gz');
        $out = fopen($tmp, 'wb');
        if (!$in || !$out) throw new RuntimeException('Non riesco a estrarre il database della copia.');
        stream_copy_to_stream($in, $out); fclose($in); fclose($out);
        $elenco = isset($m['elenco_tabelle']) && is_array($m['elenco_tabelle'])
            ? array_values(array_filter($m['elenco_tabelle'], fn($t) => is_string($t) && preg_match('/^[a-z0-9_]{1,60}$/', $t))) : null;
        $r = copia_ripristina_db($tmp, (string)$m['prefisso'], $elenco, $sitoUrl, $m);
        foreach ($r['righe'] as $x) $log[] = $x;
        if ($r['altre']) $log[] = 'Tabelle di un\'altra installazione nello stesso database (prefisso più lungo), lasciate stare: ' . $r['altre'] . ' righe.';
        if (!$r['ok']) {
            $ok = false;
            $log[] = 'Il ripristino non si è fatto: il sito è come prima (database, immagini, temi e moduli non sono stati toccati).';
            $log[] = 'Motivo: ' . $r['motivo'];
            foreach ($r['errori'] as $e) $log[] = 'ERRORE nel database: ' . $e;
            return $chiude();
        }
        $fase = 'database';
        // 3. File.
        $scritti = 0; $saltati = []; $conCodice = []; $falliti = []; $nellaCopia = [];
        if (!$firmata) require_once __DIR__ . '/media.php'; // media_ha_codice()
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string)$zip->getNameIndex($i);
            if ($n === 'manifest.json' || $n === 'db.sql.gz' || str_ends_with($n, '/')) continue;
            $nellaCopia[$n] = true; // anche un file saltato è "nella copia": quello del sito non si sposta
            if (!copia_nome_voce_ok($n)) { $saltati[] = $n; continue; }
            [$cartella, $resto] = explode('/', $n, 2);
            $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
            $eseguibile = in_array($ext, COPIA_ESEGUIBILI, true) || preg_match('/\.(php\d*|phtml|phar)\./i', basename($n));
            if ($eseguibile && ($cartella === 'uploads' || !$firmata)) { $saltati[] = $n; continue; }
            $dest = copia_radice($cartella) . '/' . $resto;
            if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) { $falliti[] = $n; continue; }
            // 2.25.1: un file di uploads/ di una copia non firmata passa dal controllo dei caricamenti (media_ha_codice): si
            // scrive in un file di lavoro, si guarda, e solo poi va al suo posto.
            $controlla = $cartella === 'uploads' && !$firmata && function_exists('media_ha_codice');
            $dove = $controlla ? copia_dir() . '/.file-' . bin2hex(random_bytes(6)) : $dest;
            $in = $zip->getStream($n);
            $out = $in ? @fopen($dove, 'wb') : false;
            if (!$in || !$out) { $falliti[] = $n; if ($in) fclose($in); continue; }
            $copiato = stream_copy_to_stream($in, $out); fclose($in); fclose($out);
            if ($copiato === false) { @unlink($dove); $falliti[] = $n; continue; }
            if ($controlla) {
                if (media_ha_codice($dove)) { @unlink($dove); $conCodice[] = $n; continue; }
                if (!@rename($dove, $dest) && !(@copy($dove, $dest) && @unlink($dove))) { @unlink($dove); $falliti[] = $n; continue; }
            }
            @chmod($dest, 0644);
            $scritti++;
        }
        $log[] = 'File rimessi: ' . $scritti . '.';
        if ($saltati) $log[] = 'File saltati (' . count($saltati) . '): ' . implode(', ', array_slice($saltati, 0, 12)) . (count($saltati) > 12 ? '...' : '');
        if ($conCodice) $log[] = 'File di uploads/ con codice dentro, non rimessi (' . count($conCodice) . ', la copia non è firmata da questo sito): ' . implode(', ', array_slice($conCodice, 0, 12)) . (count($conCodice) > 12 ? '...' : '');
        if ($falliti) {
            $ok = false;
            $log[] = 'ERRORE nei file: ' . count($falliti) . ' file non si sono scritti (' . implode(', ', array_slice($falliti, 0, 8)) . (count($falliti) > 8 ? '...' : '') . '): disco pieno o permessi delle cartelle.';
            $log[] = 'Il database è stato rimesso, i file no: ripristina la copia ' . $prima . ' (prima-del-ripristino), che rimette tutto com\'era, poi risolvi lo spazio o i permessi e riprova.';
            return $chiude();
        }
        $zip->close(); $zip = null;
        $fase = 'file'; // database e file della copia ci sono tutti: da qui un errore riguarda solo le operazioni finali
        // 4. (2.1) I file che ci sono nel sito ma non nella copia.
        if ($tieni) {
            $log[] = 'File in più (nel sito ma non nella copia): lasciati dove sono, come hai chiesto.';
        } else {
            copia_scarto_pulisci();
            $scarto = copia_metti_da_parte($nellaCopia);
            if ($scarto['file']) {
                $log[] = 'File in più (nel sito ma non nella copia) spostati da parte: ' . count($scarto['file']) . ', in aggiornamenti/scarto/' . $scarto['cartella'] . '/ (restano ' . COPIA_SCARTO_GIORNI . ' giorni).';
                foreach (array_slice($scarto['file'], 0, 40) as $f) $log[] = '  ' . $f;
                if (count($scarto['file']) > 40) $log[] = '  ... e altri ' . (count($scarto['file']) - 40) . '.';
            } else {
                $log[] = 'Nessun file in più: uploads/, temi/ e moduli/ sono come nella copia.';
            }
            foreach ($scarto['errori'] as $e) { $log[] = 'ERRORE nello spostare un file in più: ' . $e; $ok = false; }
            if ($scarto['errori']) $log[] = 'Database e file della copia sono stati rimessi tutti: solo questi file in più non si sono spostati da parte (restano dove sono).';
        }
        // Le protezioni delle cartelle restano quelle del CMS (i .htaccess non sono mai nella copia).
        if (is_file(__DIR__ . '/temi.php')) { require_once __DIR__ . '/temi.php'; if (function_exists('temi_proteggi')) temi_proteggi(); }
        if ($ok) $fase = 'fatto';
        $log[] = 'Ripristino finito il ' . date('d/m/Y') . ' alle ' . date('H:i:s') . '.';
        return $chiude();
    } catch (Throwable $e) {
        $ok = false;
        if ($fase === 'prima') {
            $log[] = 'Il ripristino non si è fatto: il sito è come prima (database, immagini, temi e moduli non sono stati toccati).';
            $log[] = 'Motivo: ' . ($e instanceof PDOException ? copia_errore_parole($e) : $e->getMessage());
        } elseif ($fase === 'file') {
            $log[] = 'ERRORE nelle operazioni finali (i file in più da mettere da parte, o le protezioni delle cartelle dei temi): ' . $e->getMessage();
            $log[] = 'Database e file della copia sono stati rimessi tutti: il sito è quello della copia. Controlla la cartella aggiornamenti/scarto/ e, in Sicurezza, che le protezioni delle cartelle ci siano.';
        } else {
            $log[] = 'ERRORE nei file: ' . $e->getMessage();
            $log[] = 'Il database è stato rimesso, i file no: ripristina la copia ' . $prima . ' (prima-del-ripristino), che rimette tutto com\'era.';
        }
        return $chiude();
    } finally {
        if ($zip) $zip->close();
        @unlink($tmp);
        copia_blocco_lascia();
    }
}

// ------------------------------------------------------------
// 2.1: i file in più dopo un ripristino (aggiornamenti/scarto/)
// ------------------------------------------------------------

/** La cartella dello scarto, dentro aggiornamenti/ (vietata al web come le copie). */
function copia_scarto_dir(): string {
    $d = agg_dir() . '/scarto';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

/** Un tema che arriva con il prodotto (tema.json con "autore": "plumeCMS"): i suoi file non si spostano mai. */
function copia_tema_del_prodotto(string $slug): bool {
    static $c = [];
    if (isset($c[$slug])) return $c[$slug];
    $j = json_decode((string)@file_get_contents(copia_radice('temi') . '/' . $slug . '/tema.json'), true);
    return $c[$slug] = is_array($j) && ($j['autore'] ?? '') === 'plumeCMS';
}

/**
 * Sposta in aggiornamenti/scarto/ripristino-<data>/ i file di uploads/, temi/ e moduli/ che non sono in $nellaCopia
 * (['uploads/foto.jpg' => true, ...]). Non tocca i file nascosti, i collegamenti (copia_file_di non li elenca) e i temi del
 * prodotto. Dopo lo spostamento toglie le cartelle rimaste vuote. Ritorna ['cartella', 'file' => [...], 'errori' => [...]].
 */
function copia_metti_da_parte(array $nellaCopia): array {
    $cartella = 'ripristino-' . date('Ymd-His');
    $base = copia_scarto_dir() . '/' . $cartella;
    $spostati = []; $errori = []; $toccate = [];
    foreach (COPIA_CARTELLE as $radice) {
        foreach (copia_file_di($radice) as $dentro => $vero) {
            if (isset($nellaCopia[$dentro])) continue;
            $parti = explode('/', $dentro);
            if ($radice === 'temi' && count($parti) > 2 && copia_tema_del_prodotto($parti[1])) continue;
            $dest = $base . '/' . $dentro;
            if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) { $errori[] = $dentro . ' (cartella dello scarto non creata)'; continue; }
            // rename sullo stesso disco; uploads/ può stare su un altro disco (uploads_dir): allora copia e cancella.
            $ok = @rename($vero, $dest) || (@copy($vero, $dest) && @unlink($vero));
            if (!$ok) { $errori[] = $dentro; continue; }
            $spostati[] = $dentro;
            $toccate[dirname($vero)] = true;
        }
        // Le cartelle rimaste vuote (senza nemmeno un file nascosto) si tolgono, mai la radice.
        $rad = rtrim(str_replace('\\', '/', (string)realpath(copia_radice($radice))), '/');
        $dirs = array_keys($toccate);
        usort($dirs, fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($dirs as $d) {
            for ($x = $d; $rad !== '' && str_starts_with($x, $rad . '/'); $x = dirname($x)) {
                $dentro = @scandir($x);
                if (!is_array($dentro) || count($dentro) > 2 || !@rmdir($x)) break;
            }
        }
        $toccate = [];
    }
    if ($spostati) {
        @file_put_contents($base . '/LEGGIMI.txt', "File che c'erano nel sito ma non nella copia ripristinata il " . date('d/m/Y H:i') . ".\n"
            . "Per rimetterne uno, riportalo nella stessa cartella del sito (uploads/, temi/ o moduli/).\n"
            . 'Questa cartella si cancella da sola dopo ' . COPIA_SCARTO_GIORNI . " giorni, al ripristino successivo.\n\n" . implode("\n", $spostati) . "\n");
    } elseif (is_dir($base)) {
        @rmdir($base);
    }
    return ['cartella' => $cartella, 'file' => $spostati, 'errori' => $errori];
}

function copia_scarto_nome_valido(string $n): bool {
    return (bool)preg_match('/^ripristino-\d{8}-\d{6}$/', $n);
}

/** Le cartelle dello scarto, dalla più recente: nome, data, quanti file, peso. */
function copia_scarto_elenco(): array {
    $out = [];
    foreach ((array)@scandir(copia_scarto_dir()) as $n) {
        if (!is_string($n) || !copia_scarto_nome_valido($n) || !is_dir(copia_scarto_dir() . '/' . $n)) continue;
        $quanti = 0; $peso = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(copia_scarto_dir() . '/' . $n, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile() && $f->getFilename() !== 'LEGGIMI.txt') { $quanti++; $peso += (int)$f->getSize(); }
        $out[] = ['nome' => $n, 'data' => (int)filemtime(copia_scarto_dir() . '/' . $n), 'file' => $quanti, 'peso' => $peso];
    }
    usort($out, fn($a, $b) => strcmp($b['nome'], $a['nome']));
    return $out;
}

/** Cancella una cartella dello scarto (dal pannello). */
function copia_scarto_elimina(string $nome): void {
    if ($nome !== basename($nome) || !copia_scarto_nome_valido($nome) || !is_dir(copia_scarto_dir() . '/' . $nome)) throw new RuntimeException('Cartella dei file messi da parte non trovata.');
    $dir = copia_scarto_dir() . '/' . $nome;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { if ($f->isDir() && !$f->isLink()) @rmdir($f->getPathname()); else @unlink($f->getPathname()); }
    @rmdir($dir);
}

/** Toglie le cartelle dello scarto più vecchie di COPIA_SCARTO_GIORNI giorni. */
function copia_scarto_pulisci(): void {
    foreach (copia_scarto_elenco() as $x) {
        if ($x['data'] < time() - COPIA_SCARTO_GIORNI * 86400) { try { copia_scarto_elimina($x['nome']); } catch (Throwable $e) {} }
    }
}

/** Uno zip caricato dal pannello entra fra le copie sul server (dopo il controllo del manifest). Ritorna il nome. */
function copia_registra_caricata(string $tmp): string {
    copia_leggi_manifest($tmp);
    $nome = 'copia-' . date('Ymd-His') . '-caricata.zip';
    $dest = copia_dir() . '/' . $nome;
    if (!@move_uploaded_file($tmp, $dest) && !@rename($tmp, $dest)) throw new RuntimeException('Non riesco a salvare la copia caricata in ' . copia_dir());
    @chmod($dest, 0600);
    return $nome;
}

/** Il limite di caricamento del server, in byte (il più piccolo fra upload_max_filesize e post_max_size). */
function copia_limite_caricamento(): int {
    $b = function (string $v): int {
        $v = trim($v); if ($v === '') return 0;
        $n = (float)$v; $u = strtolower(substr($v, -1));
        return (int)($u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n)));
    };
    $l = array_filter([$b((string)ini_get('upload_max_filesize')), $b((string)ini_get('post_max_size'))]);
    return $l ? min($l) : 0;
}
