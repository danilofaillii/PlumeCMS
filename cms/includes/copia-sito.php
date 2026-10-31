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
 */

require_once __DIR__ . '/aggiornamenti.php';
require_once __DIR__ . '/sicurezza.php';

const COPIA_FORMATO = 'plumecms-copia';
const COPIA_VERSIONE = 1;
const COPIA_DA_TENERE = 6;
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

/** Le copie sul server, dalla più recente: file, peso, data, e il manifest se si legge. */
function copia_elenco(): array {
    $out = [];
    foreach ((array)@scandir(copia_dir()) as $f) {
        if (!is_string($f) || !copia_nome_valido($f)) continue;
        $p = copia_dir() . '/' . $f;
        $m = null;
        try { $m = copia_leggi_manifest($p); } catch (Throwable $e) { $m = null; }
        $out[] = ['file' => $f, 'peso' => (int)filesize($p), 'data' => (int)filemtime($p), 'manifest' => $m, 'firmata' => $m ? copia_firmata($p, $m) : false];
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

/** Tiene le ultime COPIA_DA_TENERE copie. */
function copia_pulisci(): void {
    foreach (array_slice(copia_elenco(), COPIA_DA_TENERE) as $c) @unlink(copia_dir() . '/' . $c['file']);
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
    $altri = [];
    foreach ($mie as $t) {
        if (preg_match('/^(.+_)settings$/', $t, $m) && strlen($m[1]) > strlen($p) && in_array($m[1] . 'users', $mie, true)) $altri[] = $m[1];
    }
    return array_values(array_filter($mie, function ($t) use ($altri) {
        foreach ($altri as $q) if (str_starts_with($t, $q)) return false;
        return true;
    }));
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
        $pdo->setAttribute(agg_pdo_buffered_attr(), false);
        $st = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $t) . '`');
        $buf = '';
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $buf .= ($buf === '' ? 'INSERT INTO `' . $t . '` VALUES ' : ',') . '(' . implode(',', array_map('agg_sql_val', $row)) . ')';
            $righe++;
            if (strlen($buf) > 400000) { gzwrite($gz, $buf . ";\n"); $buf = ''; }
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
function copia_crea(string $etichetta = 'manuale'): string {
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
    copia_pulisci();
    return $nome;
}

/** Legge e controlla il manifest e i nomi delle voci di una copia. Ritorna il manifest. */
function copia_leggi_manifest(string $zipPath): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('La libreria PHP per gli zip (ZipArchive) manca sul server.');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('Il file non e uno zip leggibile.');
    try {
        $m = json_decode((string)$zip->getFromName('manifest.json'), true);
        if (!is_array($m) || ($m['formato'] ?? '') !== COPIA_FORMATO) throw new RuntimeException('Questo zip non e una copia del sito fatta da ' . CMS_NOME . ' (manca manifest.json con il formato "' . COPIA_FORMATO . '"). Un pacchetto di aggiornamento si carica da Aggiornamenti.');
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

/**
 * Ripristina il database da db.sql.gz di una copia. Passano solo le istruzioni del formato delle copie, sulle tabelle
 * con il prefisso della copia, e il prefisso diventa quello di questo sito. Ritorna ['eseguite', 'saltate', 'errori'].
 */
function copia_ripristina_db(string $gzPath, string $prefissoCopia, ?array $suffissi = null): array {
    $pdo = db();
    agg_sql_mode();
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
    $ammesse = array_flip(array_map('strval', $suffissi));
    $gz = gzopen($gzPath, 'rb');
    if (!$gz) throw new RuntimeException('Il database della copia non si legge.');
    $p = preg_quote($prefissoCopia, '~');
    $nostro = DB_PREFIX;
    $n = 0; $saltate = 0; $altre = 0; $errori = [];
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
        if (!isset($ammesse[$m[2]])) { $altre++; continue; }
        // Una riga, una istruzione: un secondo comando dopo un ";" (fuori dalle stringhe) fa saltare la riga intera.
        // E solo la forma che scrive agg_dump_db: CREATE TABLE con le colonne (non "AS SELECT" ne "LIKE"), INSERT con VALUES.
        $forma = match ($m[1]) {
            'DROP TABLE IF EXISTS ' => (bool)preg_match('~^DROP TABLE IF EXISTS `[a-z0-9_]+`;?$~', $riga),
            'CREATE TABLE ' => (bool)preg_match('~^CREATE TABLE `[a-z0-9_]+` \(~', $riga) && !preg_match('~\b(SELECT|LIKE)\b~i', $riga),
            default => (bool)preg_match('~^INSERT INTO `[a-z0-9_]+` VALUES \(~', $riga),
        };
        if (!$forma || ($m[1] !== 'DROP TABLE IF EXISTS ' && count(agg_split_sql($riga)) !== 1)) { $saltate++; continue; }
        $riga = $m[1] . '`' . $nostro . $m[2] . '`' . substr($riga, strlen($m[0]));
        if (substr($riga, -1) === ';') $riga = substr($riga, 0, -1);
        try { $pdo->exec($riga); $n++; }
        catch (Throwable $e) { if (count($errori) < 30) $errori[] = substr($riga, 0, 80) . ' ... : ' . $e->getMessage(); }
    }
    gzclose($gz);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return ['eseguite' => $n, 'saltate' => $saltate, 'altre' => $altre, 'errori' => $errori];
}

/**
 * Ripristina il sito da una copia sul server (aggiornamenti/copie/<file>). Prima fa una copia del sito com'è adesso.
 * $tieni (2.1): true = i file che ci sono nel sito ma non nella copia restano dove sono (come fino alla 2.0); false (il
 * predefinito) = si spostano nello scarto, e uploads/, temi/ e moduli/ diventano come nella copia.
 * Ritorna ['ok', 'log' => righe, 'prima' => la copia di sicurezza, 'scarto' => ['cartella', 'file' => [...]] oppure null].
 */
function copia_ripristina(string $file, bool $tieni = false): array {
    @set_time_limit(0);
    @ignore_user_abort(true);
    $zipPath = copia_percorso($file);
    $m = copia_leggi_manifest($zipPath);
    $firmata = copia_firmata($zipPath, $m);
    $log = [];
    $log[] = 'Copia letta: fatta il ' . ($m['creato'] ?? '?') . ' da ' . CMS_NOME . ' ' . ($m['cms_versione'] ?? '?') . ' su ' . ($m['sito'] ?? '?') . ', ' . (int)($m['tabelle'] ?? 0) . ' tabelle, ' . (int)($m['file'] ?? 0) . ' file.';
    $log[] = $firmata ? 'Firmata da questo sito: entrano anche i temi e i moduli con PHP.' : 'Non firmata da questo sito (viene da un altro sito, o è stata modificata): i file eseguibili (.php e simili) si saltano.';
    // 1. La rete di sicurezza: il sito di adesso, in una copia.
    $prima = copia_crea('prima-del-ripristino');
    $log[] = 'Copia di sicurezza del sito di prima: ' . $prima . ' (la trovi nell\'elenco, si ripristina come le altre).';
    $sitoUrl = (string)get_setting('sito_url', '');
    // 2. Database.
    $tmp = copia_dir() . '/.ripristino-' . bin2hex(random_bytes(6)) . '.sql.gz';
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('La copia non si apre.');
    try {
        $in = $zip->getStream('db.sql.gz');
        $out = fopen($tmp, 'wb');
        if (!$in || !$out) throw new RuntimeException('Non riesco a estrarre il database della copia.');
        stream_copy_to_stream($in, $out); fclose($in); fclose($out);
        $elenco = isset($m['elenco_tabelle']) && is_array($m['elenco_tabelle'])
            ? array_values(array_filter($m['elenco_tabelle'], fn($t) => is_string($t) && preg_match('/^[a-z0-9_]{1,60}$/', $t))) : null;
        $r = copia_ripristina_db($tmp, (string)$m['prefisso'], $elenco);
        $log[] = 'Database: ' . $r['eseguite'] . ' istruzioni eseguite' . ($r['saltate'] ? ', ' . $r['saltate'] . ' righe non ammesse saltate' : '') . '.';
        if ($r['altre']) $log[] = 'Tabelle di un\'altra installazione nello stesso database (prefisso più lungo), lasciate stare: ' . $r['altre'] . ' righe.';
        foreach ($r['errori'] as $e) $log[] = 'ERRORE nel database: ' . $e;
        if ($sitoUrl !== '') save_setting('sito_url', $sitoUrl); // l'indirizzo resta quello di questa installazione
        // 3. File.
        $scritti = 0; $saltati = []; $nellaCopia = [];
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
            if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) { $saltati[] = $n; continue; }
            $in = $zip->getStream($n);
            $out = $in ? @fopen($dest, 'wb') : false;
            if (!$in || !$out) { $saltati[] = $n; if ($in) fclose($in); continue; }
            stream_copy_to_stream($in, $out); fclose($in); fclose($out);
            @chmod($dest, 0644);
            $scritti++;
        }
        $log[] = 'File rimessi: ' . $scritti . '.';
        if ($saltati) $log[] = 'File saltati (' . count($saltati) . '): ' . implode(', ', array_slice($saltati, 0, 12)) . (count($saltati) > 12 ? '...' : '');
    } finally {
        $zip->close();
        @unlink($tmp);
    }
    // 4. (2.1) I file che ci sono nel sito ma non nella copia.
    $scarto = null;
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
        foreach ($scarto['errori'] as $e) $log[] = 'ERRORE nello spostare un file in più: ' . $e;
    }
    // Le protezioni delle cartelle restano quelle del CMS (i .htaccess non sono mai nella copia).
    if (is_file(__DIR__ . '/temi.php')) { require_once __DIR__ . '/temi.php'; if (function_exists('temi_proteggi')) temi_proteggi(); }
    if (function_exists('sicurezza_log')) sicurezza_log('copia', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'sito ripristinato dalla copia ' . basename($file) . ($firmata ? ' (firmata)' : ' (non firmata)'));
    return ['ok' => !array_filter($log, fn($r) => str_starts_with($r, 'ERRORE')), 'log' => $log, 'prima' => $prima, 'scarto' => $scarto];
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
    $nome = basename($nome);
    if (!copia_scarto_nome_valido($nome) || !is_dir(copia_scarto_dir() . '/' . $nome)) throw new RuntimeException('Cartella dei file messi da parte non trovata.');
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
