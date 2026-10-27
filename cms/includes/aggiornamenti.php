<?php
/**
 * Motore dei pacchetti di aggiornamento.
 *
 * Un pacchetto e uno zip con dentro un manifest.json, file da copiare sul
 * sito, file SQL da eseguire, redirect da creare, articoli e test in JSON.
 * Prima di applicarlo il motore fa uno snapshot (tutto il
 * database piu i file che verranno sovrascritti). Se un passo fallisce,
 * ripristina lo snapshot da solo.
 *
 * Cartella di lavoro: /new/aggiornamenti/ (protetta da .htaccess)
 *   pacchetti/<id>.zip   il pacchetto caricato
 *   pacchetti/<id>.json  stato, log, date
 *   snapshot/<id>/       db.sql.gz, files/, meta.json
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/sito.php';

// Azioni che un pacchetto può chiedere per nome. Solo queste: un pacchetto non esegue codice a piacere.
// 'attiva-sito-nuovo' era il pacchetto del passaggio: ormai il sito legge sempre da qui.
const AGG_AZIONI = ['attiva-sito-nuovo' => 'agg_azione_nulla'];

/** Le azioni ammesse: quelle del nucleo più quelle dichiarate dai moduli (danilofailli.it: la conversione degli articoli, il vecchio test). */
function agg_azioni(): array {
    $a = AGG_AZIONI;
    foreach (cms_moduli_elenco('azioni') as $x) if (is_callable($x['valore'])) $a[(string)$x['chiave']] = $x['valore'];
    return $a;
}


function agg_azione_nulla(): string {
    sito_viste();
    return 'Niente da fare: il sito pubblico legge già da questo pannello.';
}

const AGG_SNAPSHOT_DA_TENERE = 5;

// ------------------------------------------------------------
// Percorsi
// ------------------------------------------------------------

function agg_dir(string $sub = ''): string {
    $base = dirname(__DIR__) . '/aggiornamenti';
    foreach (['', '/pacchetti', '/snapshot'] as $d) {
        if (!is_dir($base . $d)) @mkdir($base . $d, 0755, true);
    }
    $ht = $base . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    return $base . ($sub ? '/' . trim($sub, '/') : '');
}

/** Radice del sito, dove sta wp-config.php. */
function agg_site_root(): string {
    // La radice del dominio. CMS nella radice (installazione nuova): la cartella del CMS. CMS in una sottocartella con le
    // immagini fuori (danilofailli.it: /new con wp-content/uploads nella radice): la cartella sopra. CMS_RADICE_SITO la fissa.
    if (defined('CMS_RADICE_SITO')) return rtrim((string)CMS_RADICE_SITO, '/');
    return str_starts_with(cms_uploads_dir(), cms_radice() . '/') ? cms_radice() : dirname(cms_radice());
}

/**
 * Traduce una destinazione del manifest in un percorso assoluto.
 * Radici ammesse: uploads/ (wp-content/uploads), new/ (il publisher), root/ (radice del sito).
 */
/** Cancella una cartella con tutto quello che contiene. */
function agg_rimuovi_cartella(string $dir): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

/** Le estensioni che un pacchetto senza firma puo scrivere nella radice del sito (root/): file che il server non esegue. */
const AGG_ROOT_STATICI = ['txt', 'xml', 'html', 'htm', 'ico', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'pdf', 'css', 'js', 'woff', 'woff2', 'json', 'webmanifest', 'map'];

/** I soli .htaccess che un pacchetto puo scrivere. */
const AGG_HTACCESS_AMMESSI = ['root/.htaccess', 'new/.htaccess', 'uploads/.htaccess'];

function agg_dest_path(string $dest): string {
    $dest = str_replace('\\', '/', trim($dest));
    // Unici file nascosti ammessi: i .htaccess della radice del sito, della cartella del CMS e di quella delle immagini
    // (dalla 1.1.3: i divieti del CMS arrivano anche con un pacchetto). Nessun altro .htaccess, in nessuna sottocartella.
    if (in_array($dest, AGG_HTACCESS_AMMESSI, true)) $m = [$dest, strstr($dest, '/', true), '.htaccess'];
    elseif (!preg_match('~^(uploads|new|root)/([A-Za-z0-9_][A-Za-z0-9_./ -]*)$~', $dest, $m)) {
        throw new RuntimeException("Destinazione non ammessa: $dest");
    }
    foreach (explode('/', $m[2]) as $seg) {
        if ($seg === '..' || $seg === '' ) throw new RuntimeException("Destinazione non ammessa: $dest");
    }
    $rel = $m[2];
    switch ($m[1]) {
        case 'uploads': $base = cms_uploads_dir(); break;
        case 'new':
            if (str_starts_with($rel, 'aggiornamenti/') || $rel === 'includes/config.local.php') {
                throw new RuntimeException("Destinazione protetta: $dest");
            }
            $base = dirname(__DIR__); break;
        default: $base = agg_site_root();
    }
    return $base . '/' . $rel;
}

function agg_slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'pacchetto';
}

function agg_fmt_bytes(int $b): string {
    if ($b > 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b > 1024) return round($b / 1024) . ' KB';
    return $b . ' B';
}

// ------------------------------------------------------------
// Pacchetti
// ------------------------------------------------------------

/** Apre uno zip e ne legge e valida il manifest. Ritorna il manifest normalizzato. */
function agg_leggi_manifest(string $zipPath): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('La libreria PHP per gli zip (ZipArchive) manca sul server.');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('Zip illeggibile.');
    $raw = $zip->getFromName('manifest.json');
    if ($raw === false) { $zip->close(); throw new RuntimeException('Nel pacchetto manca manifest.json.'); }
    $m = json_decode($raw, true);
    if (!is_array($m)) { $zip->close(); throw new RuntimeException('manifest.json non e un JSON valido.'); }
    // sicurezza: voci dello zip con nomi puliti (niente "..", niente percorsi assoluti), dimensione totale e rapporto di compressione sotto controllo (zip bomb).
    $totaleByte = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        $nome = str_replace('\\', '/', (string)($st['name'] ?? ''));
        if ($nome === '' || str_starts_with($nome, '/') || preg_match('~(^|/)\.\.(/|$)~', $nome) || str_contains($nome, "\0")) { $zip->close(); throw new RuntimeException("Nome non ammesso nello zip: $nome"); }
        $totaleByte += (int)($st['size'] ?? 0);
        if ((int)($st['size'] ?? 0) > 200 * 1048576) { $zip->close(); throw new RuntimeException("File troppo grande nello zip: $nome"); }
    }
    if ($totaleByte > 600 * 1048576) { $zip->close(); throw new RuntimeException('Pacchetto troppo grande una volta estratto (oltre 600 MB).'); }
    // sicurezza: firma HMAC del pacchetto (tools/pacchetto.py --firma). Se c'e si verifica; se la pagina Sicurezza la vuole obbligatoria, senza firma non si va avanti.
    require_once __DIR__ . '/sicurezza.php';
    $firma = trim((string)($m['firma'] ?? ''));
    $firmaObbligatoria = false;
    try { $firmaObbligatoria = (bool)sicurezza_cfg()['firma_pacchetti']; } catch (Throwable $e) {}
    if ($firma !== '' && !sicurezza_pacchetto_firma_ok($zip, $firma)) { $zip->close(); throw new RuntimeException('La firma del pacchetto non corrisponde: il file e stato modificato o la chiave e diversa.'); }
    if ($firma === '' && $firmaObbligatoria) { $zip->close(); throw new RuntimeException('Pacchetto senza firma: la pagina Sicurezza richiede pacchetti firmati (tools/pacchetto.py --firma).'); }

    $out = [
        'nome'        => trim((string)($m['nome'] ?? '')),
        'descrizione' => trim((string)($m['descrizione'] ?? '')),
        'creato'      => trim((string)($m['creato'] ?? '')),
        'applica_dal' => trim((string)($m['applica_dal'] ?? '')),
        'sql'         => [],
        'file'        => [],
        'redirect'    => [],
        'elimina'     => [],
        'articoli'    => [],
        'test'        => [],
        'azioni'      => [],
        'note'        => trim((string)($m['note'] ?? '')),
        'firmato'     => $firma !== '',
    ];
    if ($out['nome'] === '') { $zip->close(); throw new RuntimeException('Il manifest non ha un nome.'); }
    if ($out['applica_dal'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $out['applica_dal'])) {
        $zip->close(); throw new RuntimeException('applica_dal deve essere una data AAAA-MM-GG.');
    }
    foreach ((array)($m['sql'] ?? []) as $s) {
        $s = (string)$s;
        if ($zip->locateName($s) === false) { $zip->close(); throw new RuntimeException("File SQL assente nello zip: $s"); }
        $out['sql'][] = $s;
    }
    foreach ((array)($m['file'] ?? []) as $f) {
        if (!is_array($f) || empty($f['da']) || empty($f['a'])) { $zip->close(); throw new RuntimeException('Ogni voce di "file" deve avere "da" e "a".'); }
        if ($zip->locateName($f['da']) === false) { $zip->close(); throw new RuntimeException("File assente nello zip: {$f['da']}"); }
        agg_dest_path($f['a']);
        $out['file'][] = ['da' => (string)$f['da'], 'a' => (string)$f['a']];
    }
    // sicurezza (attacco 7): nella radice del sito (root/) un pacchetto senza firma scrive solo file statici e il .htaccess.
    // Un file che il server esegue (.php e simili) entra in root/ solo se il pacchetto e firmato (tools/pacchetto.py --firma).
    $finestra = false;
    try { $finestra = (int)sicurezza_cfg()['root_php_fino'] > time(); } catch (Throwable $e) {}
    if (!$out['firmato'] && !$finestra) {
        foreach ($out['file'] as $f) {
            if (!str_starts_with($f['a'], 'root/') || $f['a'] === 'root/.htaccess') continue;
            $ext = strtolower(pathinfo($f['a'], PATHINFO_EXTENSION));
            if (!in_array($ext, AGG_ROOT_STATICI, true)) { $zip->close(); throw new RuntimeException("Il file {$f['a']} va nella radice del sito e il server lo eseguirebbe: serve un pacchetto firmato (tools/pacchetto.py --firma, oppure --rifirma sullo zip), oppure apri per un'ora la finestra in Impostazioni, Sicurezza (serve la tua password)."); }
        }
    }
    foreach ((array)($m['redirect'] ?? []) as $r) {
        if (!is_array($r) || empty($r['da']) || empty($r['a'])) { $zip->close(); throw new RuntimeException('Ogni redirect deve avere "da" e "a".'); }
        $out['redirect'][] = ['da' => (string)$r['da'], 'a' => (string)$r['a']];
    }
    // File da togliere dal server (stesse destinazioni ammesse per i file da scrivere). Finiscono prima nello snapshot.
    foreach ((array)($m['elimina'] ?? []) as $e) {
        agg_dest_path((string)$e);
        $out['elimina'][] = (string)$e;
    }
    // Articoli in JSON, uno per file, con data e ora di uscita (vedi sito_importa_articolo).
    foreach ((array)($m['azioni'] ?? []) as $a) {
        if (!isset(agg_azioni()[$a])) { $zip->close(); throw new RuntimeException("Azione sconosciuta: $a"); }
        $out['azioni'][] = (string)$a;
    }
    foreach (['articoli', 'test'] as $chiave) {
        foreach ((array)($m[$chiave] ?? []) as $f) {
            $f = (string)$f;
            $raw = $zip->getFromName($f);
            if ($raw === false) { $zip->close(); throw new RuntimeException("File assente nello zip: $f"); }
            if (!is_array(json_decode($raw, true))) { $zip->close(); throw new RuntimeException("$f non e un JSON valido."); }
            $out[$chiave][] = $f;
        }
    }
    $zip->close();
    return $out;
}

/** Registra un pacchetto caricato. Ritorna l'id. */
function agg_registra_pacchetto(string $tmpZip, string $nomeOriginale): string {
    $manifest = agg_leggi_manifest($tmpZip);
    $id = date('Ymd-His') . '-' . agg_slugify($manifest['nome']);
    $dest = agg_dir('pacchetti') . '/' . $id . '.zip';
    if (!@rename($tmpZip, $dest) && !@copy($tmpZip, $dest)) {
        if (!@move_uploaded_file($tmpZip, $dest)) throw new RuntimeException('Impossibile salvare lo zip in ' . $dest);
    }
    $stato = [
        'id'          => $id,
        'nome'        => $manifest['nome'],
        'file_zip'    => $nomeOriginale,
        'caricato'    => date('Y-m-d H:i:s'),
        'applica_dal' => $manifest['applica_dal'],
        'stato'       => 'in_attesa',
        'manifest'    => $manifest,
        'log'         => [],
        'snapshot'    => null,
        'applicato'   => null,
    ];
    agg_salva_stato($stato);
    return $id;
}

function agg_salva_stato(array $stato): void {
    file_put_contents(agg_dir('pacchetti') . '/' . $stato['id'] . '.json', json_encode($stato, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function agg_leggi_stato(string $id): ?array {
    $f = agg_dir('pacchetti') . '/' . basename($id) . '.json';
    if (!file_exists($f)) return null;
    $s = json_decode(file_get_contents($f), true);
    return is_array($s) ? $s : null;
}

function agg_lista_pacchetti(): array {
    $out = [];
    foreach (glob(agg_dir('pacchetti') . '/*.json') as $f) {
        $s = json_decode(file_get_contents($f), true);
        if (is_array($s)) $out[$s['id']] = $s;
    }
    krsort($out);
    return array_values($out);
}

/** Un pacchetto fallito torna "in attesa": il cron lo riprova alla sua data, o Danilo lo riapplica. */
function agg_rimetti_in_attesa(string $id): void {
    $s = agg_leggi_stato($id);
    if (!$s) throw new RuntimeException('Pacchetto non trovato.');
    $s['stato'] = 'in_attesa';
    $s['applicato'] = null;
    $s['log'] = array_merge($s['log'], ['Rimesso in attesa il ' . date('Y-m-d H:i:s')]);
    agg_salva_stato($s);
}

function agg_elimina_pacchetto(string $id): void {
    $id = basename($id);
    @unlink(agg_dir('pacchetti') . '/' . $id . '.zip');
    @unlink(agg_dir('pacchetti') . '/' . $id . '.json');
}

/** I pacchetti in attesa la cui data e arrivata. */
function agg_pacchetti_da_applicare(): array {
    $oggi = date('Y-m-d');
    $out = [];
    foreach (agg_lista_pacchetti() as $p) {
        if ($p['stato'] === 'in_attesa' && ($p['applica_dal'] === '' || $p['applica_dal'] <= $oggi)) $out[] = $p;
    }
    // dal piu vecchio al piu nuovo
    usort($out, fn($a, $b) => strcmp($a['id'], $b['id']));
    return $out;
}

/** Applica tutti i pacchetti in scadenza. Usato dal cron delle 06:00. Ritorna righe di log. */
function agg_applica_in_attesa(): array {
    $righe = [];
    foreach (agg_pacchetti_da_applicare() as $p) {
        $res = agg_applica($p['id']);
        $righe[] = ($res['ok'] ? 'OK ' : 'FALLITO ') . $p['nome'] . ': ' . $res['riassunto'];
    }
    return $righe;
}

// ------------------------------------------------------------
// Snapshot
// ------------------------------------------------------------

/** Costante PDO per le query non bufferizzate: PHP 8.5 ha rinominato quella vecchia. */
function agg_pdo_buffered_attr(): int {
    if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')) {
        return constant('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY');
    }
    return constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY');
}

/**
 * Mette la connessione fuori dalla modalita stretta del server: gli snapshot
 * e i file SQL dei pacchetti possono portare date vuote '0000-00-00'.
 */
function agg_sql_mode(): void {
    static $fatto = false;
    if ($fatto) return;
    db()->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    $fatto = true;
}

function agg_sql_val($v): string {
    if ($v === null) return 'NULL';
    return "'" . strtr((string)$v, ["\\" => "\\\\", "'" => "\\'", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]) . "'";
}

/** Scrive tutto il database in un file .sql.gz, una istruzione per riga. Ritorna statistiche. */
function agg_dump_db(string $gzPath): array {
    $pdo = db();
    $gz = gzopen($gzPath, 'wb6');
    if (!$gz) throw new RuntimeException('Impossibile scrivere ' . $gzPath);
    gzwrite($gz, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';\n");
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n");
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $righe = 0;
    foreach ($tables as $t) {
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $t) . '`')->fetch(PDO::FETCH_NUM);
        $isView = stripos($create[1], 'CREATE') === 0 && stripos($create[1], ' VIEW ') !== false && stripos($create[1], 'CREATE TABLE') !== 0;
        gzwrite($gz, ($isView ? 'DROP VIEW IF EXISTS `' : 'DROP TABLE IF EXISTS `') . $t . "`;\n");
        gzwrite($gz, str_replace(["\r\n", "\n"], ' ', $create[1]) . ";\n");
        if ($isView) continue;
        $pdo->setAttribute(agg_pdo_buffered_attr(), false);
        $st = $pdo->query('SELECT * FROM `' . $t . '`');
        $buf = ''; $n = 0;
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $vals = '(' . implode(',', array_map('agg_sql_val', $row)) . ')';
            $buf .= ($buf === '' ? 'INSERT INTO `' . $t . '` VALUES ' : ',') . $vals;
            $n++; $righe++;
            if (strlen($buf) > 400000) { gzwrite($gz, $buf . ";\n"); $buf = ''; }
        }
        if ($buf !== '') gzwrite($gz, $buf . ";\n");
        $st->closeCursor();
        $pdo->setAttribute(agg_pdo_buffered_attr(), true);
    }
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    return ['tabelle' => count($tables), 'righe' => $righe, 'dimensione' => filesize($gzPath)];
}

/**
 * Riesegue un dump riga per riga. Un errore su una riga non ferma il
 * ripristino: le righe fallite vengono raccolte e riportate.
 * Ritorna ['eseguite' => int, 'errori' => string[]].
 */
function agg_restore_db(string $gzPath): array {
    $pdo = db();
    agg_sql_mode();
    $gz = gzopen($gzPath, 'rb');
    if (!$gz) throw new RuntimeException('Snapshot del database illeggibile: ' . $gzPath);
    $n = 0; $errori = [];
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    while (($line = gzgets($gz)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === '') continue;
        if (substr($line, -1) === ';') $line = substr($line, 0, -1);
        try {
            $pdo->exec($line);
            $n++;
        } catch (Throwable $e) {
            if (count($errori) < 30) $errori[] = substr($line, 0, 80) . ' ... : ' . $e->getMessage();
        }
    }
    gzclose($gz);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return ['eseguite' => $n, 'errori' => $errori];
}

/**
 * Crea uno snapshot: database intero piu copia dei file elencati in $destinazioni
 * (destinazioni del manifest, es. "uploads/contenuti/x.png"). Ritorna l'id.
 */
function agg_crea_snapshot(string $etichetta, array $destinazioni = []): string {
    @set_time_limit(600);
    @ignore_user_abort(true);
    $id = date('Ymd-His') . '-' . agg_slugify($etichetta);
    $dir = agg_dir('snapshot') . '/' . $id;
    @mkdir($dir . '/files', 0755, true);
    $db = agg_dump_db($dir . '/db.sql.gz');
    $files = [];
    foreach ($destinazioni as $dest) {
        $abs = agg_dest_path($dest);
        $esisteva = file_exists($abs);
        if ($esisteva) {
            $copia = $dir . '/files/' . $dest;
            @mkdir(dirname($copia), 0755, true);
            if (!copy($abs, $copia)) throw new RuntimeException('Impossibile copiare nello snapshot: ' . $dest);
        }
        $files[] = ['dest' => $dest, 'esisteva' => $esisteva];
    }
    $meta = [
        'id' => $id, 'etichetta' => $etichetta, 'creato' => date('Y-m-d H:i:s'),
        'db' => $db, 'files' => $files,
    ];
    file_put_contents($dir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    agg_pulisci_snapshot();
    return $id;
}

function agg_lista_snapshot(): array {
    $out = [];
    foreach (glob(agg_dir('snapshot') . '/*/meta.json') as $f) {
        $m = json_decode(file_get_contents($f), true);
        if (is_array($m)) $out[$m['id']] = $m;
    }
    krsort($out);
    return array_values($out);
}

function agg_leggi_snapshot(string $id): ?array {
    $f = agg_dir('snapshot') . '/' . basename($id) . '/meta.json';
    if (!file_exists($f)) return null;
    $m = json_decode(file_get_contents($f), true);
    return is_array($m) ? $m : null;
}

function agg_elimina_snapshot(string $id): void {
    $dir = agg_dir('snapshot') . '/' . basename($id);
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $p) { $p->isDir() ? @rmdir($p->getPathname()) : @unlink($p->getPathname()); }
    @rmdir($dir);
}

function agg_pulisci_snapshot(): void {
    $lista = agg_lista_snapshot();
    foreach (array_slice($lista, AGG_SNAPSHOT_DA_TENERE) as $s) agg_elimina_snapshot($s['id']);
}

/** Rimette database e file come erano nello snapshot. Ritorna righe di log. */
function agg_ripristina(string $id): array {
    @set_time_limit(600);
    @ignore_user_abort(true);
    $m = agg_leggi_snapshot($id);
    if (!$m) throw new RuntimeException('Snapshot non trovato: ' . $id);
    $dir = agg_dir('snapshot') . '/' . basename($id);
    $log = [];
    $r = agg_restore_db($dir . '/db.sql.gz');
    $log[] = "Database ripristinato: {$r['eseguite']} istruzioni eseguite, {$m['db']['tabelle']} tabelle.";
    foreach ($r['errori'] as $e) $log[] = 'ERRORE nel ripristino: ' . $e;
    if ($r['errori']) $log[] = count($r['errori']) . ' istruzioni del ripristino sono fallite: mandami questo log.';
    foreach ($m['files'] as $f) {
        $abs = agg_dest_path($f['dest']);
        if ($f['esisteva']) {
            $copia = $dir . '/files/' . $f['dest'];
            @mkdir(dirname($abs), 0755, true);
            if (copy($copia, $abs)) $log[] = 'File rimesso: ' . $f['dest'];
            else $log[] = 'ATTENZIONE, file non rimesso: ' . $f['dest'];
        } else {
            if (file_exists($abs)) { @unlink($abs); $log[] = 'File rimosso (prima non esisteva): ' . $f['dest']; }
        }
    }
    return $log;
}

// ------------------------------------------------------------
// Applicazione di un pacchetto
// ------------------------------------------------------------

/**
 * Spezza un file SQL in istruzioni. A differenza di Importa SQL tiene conto
 * delle stringhe: un ; dentro un testo tra apici non spezza niente, e le
 * righe di commento che iniziano con -- vengono tolte invece di far saltare
 * il blocco.
 */
function agg_split_sql(string $sql): array {
    $out = [];
    $cur = '';
    $len = strlen($sql);
    $inStr = false;
    $lineStart = true;
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inStr) {
            $cur .= $c;
            if ($c === '\\' && $i + 1 < $len) { $cur .= $sql[++$i]; continue; }
            if ($c === "'") {
                if ($i + 1 < $len && $sql[$i + 1] === "'") { $cur .= "'"; $i++; continue; }
                $inStr = false;
            }
            continue;
        }
        if ($lineStart && $c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") $i++;
            continue;
        }
        $lineStart = ($c === "\n");
        if ($c === "'") { $inStr = true; $cur .= $c; continue; }
        if ($c === ';') {
            $s = trim($cur);
            if ($s !== '') $out[] = $s;
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    $s = trim($cur);
    if ($s !== '') $out[] = $s;
    return $out;
}

/**
 * Applica un pacchetto. Ordine: snapshot, file, SQL, test e articoli, azioni, redirect.
 * Se un passo fallisce ripristina lo snapshot. Ritorna ['ok', 'riassunto', 'log'].
 */
/**
 * Avanzamento di un'operazione lunga (applica, ripristina).
 * Il pannello legge questo file mentre l'operazione gira, cosi Danilo vede
 * a che punto siamo invece di guardare una pagina ferma.
 */
function agg_progresso_file(string $id): string {
    return agg_dir() . '/progresso-' . preg_replace('/[^A-Za-z0-9_.-]/', '', basename($id)) . '.json';
}

function agg_progresso(string $id, int $fatti, int $totale, string $testo, bool $finito = false, bool $ok = true): void {
    @file_put_contents(agg_progresso_file($id), json_encode([
        'fatti'      => $fatti,
        'totale'     => max(1, $totale),
        'testo'      => $testo,
        'finito'     => $finito,
        'ok'         => $ok,
        'aggiornato' => time(),
    ], JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function agg_progresso_leggi(string $id): ?array {
    $f = agg_progresso_file($id);
    if (!is_file($f)) return null;
    $d = json_decode((string)@file_get_contents($f), true);
    return is_array($d) ? $d : null;
}

/** Toglie i file di avanzamento piu vecchi di un giorno. */
function agg_progresso_pulisci(): void {
    foreach (glob(agg_dir() . '/progresso-*.json') ?: [] as $f) {
        if (filemtime($f) < time() - 86400) @unlink($f);
    }
}

function agg_applica(string $id): array {
    @set_time_limit(600);
    @ignore_user_abort(true);
    $stato = agg_leggi_stato($id);
    if (!$stato) return ['ok' => false, 'riassunto' => 'pacchetto non trovato', 'log' => []];
    $zipPath = agg_dir('pacchetti') . '/' . basename($id) . '.zip';
    $log = [];
    $t0 = microtime(true);
    $snapshotId = null;
    agg_progresso_pulisci();
    agg_progresso($id, 0, 6, 'Apro il pacchetto');
    $passo = 0;
    $totale = 6;
    try {
        agg_sql_mode();
        $m = agg_leggi_manifest($zipPath);
        $totale = 4 + count($m['file']) + count($m['elimina']) + count($m['sql']) + count($m['articoli']) + count($m['test']) + count($m['azioni']) + count($m['redirect']);
        agg_progresso($id, ++$passo, $totale, 'Pacchetto letto: ' . count($m['file']) . ' file, ' . count($m['sql']) . ' file SQL, ' . count($m['articoli']) . ' articoli');
        $log[] = 'Pacchetto letto: ' . count($m['file']) . ' file, ' . count($m['sql']) . ' file SQL, ' . count($m['articoli']) . ' articoli, ' . count($m['redirect']) . ' redirect.';

        // 1. snapshot
        agg_progresso($id, $passo, $totale, 'Creo lo snapshot del database, e il passo piu lento');
        $snapshotId = agg_crea_snapshot($m['nome'], array_merge(array_column($m['file'], 'a'), $m['elimina']));
        $snap = agg_leggi_snapshot($snapshotId);
        $log[] = 'Snapshot creato: ' . $snapshotId . ' (' . $snap['db']['tabelle'] . ' tabelle, ' . $snap['db']['righe'] . ' righe, ' . agg_fmt_bytes($snap['db']['dimensione']) . ').';
        $stato['snapshot'] = $snapshotId;
        agg_salva_stato($stato);
        agg_progresso($id, ++$passo, $totale, 'Snapshot creato: ' . $snap['db']['tabelle'] . ' tabelle, ' . number_format((int)$snap['db']['righe'], 0, ',', '.') . ' righe');

        // Colonne e tabelle del sito nuovo: dopo lo snapshot, cosi un ripristino le toglie.
        $schema = sito_schema_aggiorna();
        foreach (cms_moduli_elenco('schema') as $x) if (is_callable($x['valore'])) $schema = array_merge($schema, (array)($x['valore'])()); // le tabelle dei moduli (i test)
        foreach ($schema as $r) $log[] = 'Schema: ' . $r;

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('Zip illeggibile.');

        // 2. file
        foreach ($m['file'] as $f) {
            $data = $zip->getFromName($f['da']);
            if ($data === false) throw new RuntimeException('File non leggibile nello zip: ' . $f['da']);
            $abs = agg_dest_path($f['a']);
            @mkdir(dirname($abs), 0755, true);
            if (file_put_contents($abs, $data) === false) throw new RuntimeException('Impossibile scrivere ' . $f['a']);
            $log[] = 'File scritto: ' . $f['a'] . ' (' . agg_fmt_bytes(strlen($data)) . ')';
            agg_progresso($id, ++$passo, $totale, 'Copio i file: ' . basename($f['a']));
        }

        // 2b. file da togliere (quelli che servivano solo al sito di prima)
        foreach ($m['elimina'] as $e) {
            $abs = agg_dest_path($e);
            if (is_file($abs)) { if (!@unlink($abs)) throw new RuntimeException('Impossibile cancellare ' . $e); $log[] = 'File tolto: ' . $e; }
            elseif (is_dir($abs)) { agg_rimuovi_cartella($abs); $log[] = 'Cartella tolta: ' . $e; }
            else $log[] = 'Già assente: ' . $e;
            agg_progresso($id, ++$passo, $totale, 'Tolgo: ' . basename($e));
        }

        // 3. SQL
        foreach ($m['sql'] as $s) {
            $sql = $zip->getFromName($s);
            $stmts = agg_split_sql($sql);
            $tot = 0;
            $n = 0;
            $quante = count($stmts);
            agg_progresso($id, $passo, $totale, 'Eseguo ' . basename($s) . ': ' . $quante . ' istruzioni');
            foreach ($stmts as $q) {
                $tot += (int)db()->exec($q);
                $n++;
                if ($n % 20 === 0) agg_progresso($id, $passo, $totale, 'Eseguo ' . basename($s) . ': istruzione ' . $n . ' di ' . $quante);
            }
            $log[] = 'SQL ' . $s . ': ' . count($stmts) . ' istruzioni, ' . $tot . ' righe interessate.';
            agg_progresso($id, ++$passo, $totale, basename($s) . ': ' . $quante . ' istruzioni, ' . $tot . ' righe cambiate');
        }
        // 3b. test e articoli in JSON
        foreach ($m['test'] as $f) {
            $esitoTest = cms_filtro('pacchetto_test', null, (array)json_decode($zip->getFromName($f), true));
            $log[] = is_string($esitoTest) ? $esitoTest : 'Test ' . basename($f) . ': nessun modulo lo importa (manca il modulo dei test)';
            agg_progresso($id, ++$passo, $totale, 'Test: ' . basename($f, '.json'));
        }
        foreach ($m['azioni'] as $a) {
            $log[] = (string)(agg_azioni()[$a])();
            agg_progresso($id, ++$passo, $totale, 'Azione: ' . $a);
        }
        foreach ($m['articoli'] as $f) {
            $log[] = sito_importa_articolo(json_decode($zip->getFromName($f), true));
            agg_progresso($id, ++$passo, $totale, 'Articolo: ' . basename($f, '.json'));
        }
        $zip->close();

        // 4. redirect, nella tabella del sito
        foreach ($m['redirect'] as $r) {
            $log[] = sito_salva_redirect($r['da'], $r['a']);
            agg_progresso($id, ++$passo, $totale, 'Redirect: ' . $r['da']);
        }

        agg_progresso($id, ++$passo, $totale, 'Quasi fatto');

        $stato['stato'] = 'applicato';
        $stato['applicato'] = date('Y-m-d H:i:s');
        if (function_exists('sicurezza_log')) sicurezza_log('pacchetto', (string)($_SESSION['admin_username'] ?? 'cron'), (int)($_SESSION['admin_user_id'] ?? 0), $m['nome'] . ' (' . count($m['file']) . ' file, ' . count($m['sql']) . ' sql' . ($m['firmato'] ? ', firmato' : ', senza firma') . ')');
        $stato['log'] = $log;
        agg_salva_stato($stato);
        $sec = round(microtime(true) - $t0, 1);
        agg_progresso($id, $totale, $totale, 'Fatto in ' . $sec . ' secondi', true, true);
        return ['ok' => true, 'riassunto' => 'applicato in ' . $sec . ' secondi, snapshot ' . $snapshotId, 'log' => $log];

    } catch (Throwable $e) {
        $log[] = 'ERRORE: ' . $e->getMessage();
        agg_progresso($id, $passo, $totale, 'Errore: ' . $e->getMessage() . '. Rimetto il sito come prima.', false, false);
        if ($snapshotId) {
            try {
                $righe = agg_ripristina($snapshotId);
                $conErrori = false;
                foreach ($righe as $r) { $log[] = 'Ripristino: ' . $r; if (str_starts_with($r, 'ERRORE')) $conErrori = true; }
                $log[] = $conErrori
                    ? 'Il ripristino ha avuto errori: usa il pulsante Ripristina sullo snapshot ' . $snapshotId . ' e mandami il log.'
                    : 'Il sito e tornato come prima dell\'aggiornamento.';
            } catch (Throwable $e2) {
                $log[] = 'RIPRISTINO FALLITO: ' . $e2->getMessage() . '. Usa il pulsante Ripristina sullo snapshot ' . $snapshotId . '.';
            }
        }
        $stato['stato'] = 'fallito';
        $stato['log'] = $log;
        agg_salva_stato($stato);
        agg_progresso($id, $totale, $totale, 'Non applicato: ' . $e->getMessage(), true, false);
        return ['ok' => false, 'riassunto' => $e->getMessage(), 'log' => $log];
    }
}

/** Come get_article_by_slug ma senza filtro sullo stato: serve anche per le bozze. */
function get_article_by_slug_any(string $slug): ?array {
    $st = db()->prepare('SELECT * FROM ' . tbl('articles') . ' WHERE slug = :s LIMIT 1');
    $st->execute([':s' => $slug]);
    $a = $st->fetch();
    return $a ?: null;
}

/** Controlli di sistema mostrati in cima alla pagina. */
function agg_controlli(): array {
    $c = [];
    $c[] = ['ok' => class_exists('ZipArchive'), 'testo' => 'Libreria zip (ZipArchive)'];
    $c[] = ['ok' => function_exists('gzopen'), 'testo' => 'Compressione (zlib)'];
    $c[] = ['ok' => is_writable(agg_dir()), 'testo' => 'Cartella di lavoro scrivibile: ' . agg_dir()];
    $c[] = ['ok' => is_writable(cms_uploads_dir()), 'testo' => 'Cartella immagini scrivibile: ' . cms_uploads_dir()];
    $c[] = ['ok' => is_file(agg_site_root() . '/index.php') && str_contains((string)file_get_contents(agg_site_root() . '/index.php'), 'sito/index.php'),
        'testo' => 'Sito installato nella radice: ' . agg_site_root() . '/index.php'];
    $lim = ini_get('upload_max_filesize') . ' / ' . ini_get('post_max_size');
    $c[] = ['ok' => true, 'testo' => 'Limite di caricamento del server: ' . $lim];
    return $c;
}
