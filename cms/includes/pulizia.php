<?php
/**
 * Pulizia (2.38.0): cosa si può togliere dal sito senza romperlo, con il peso di ogni voce, e la cancellazione totale o
 * parziale. Pannello: admin/pulizia.php (Impostazioni > Pulizia), solo per l'amministratore. Manuale tecnico, capitolo 50.
 *
 * Le voci (pulizia_voci()) sono di tre gruppi:
 *   file      pacchetti già applicati, snapshot (tieni le ultime N), copie del sito (a scelta), file messi da parte da un
 *             ripristino, avanzi del cambio database, log, file dei contatti (esportazioni, importazioni, scarti);
 *   database  versioni vecchie dell'editor (tieni le ultime N per oggetto), cestino (da più di N giorni), registri vecchi,
 *             tabelle di lavoro rimaste da un ripristino o da un cambio interrotto, Ottimizza le tabelle;
 *   media     le immagini che nessuna parte del sito cita (solo dopo il controllo, solo quelle spuntate a una a una).
 *
 * Ogni voce ha un "piano" (pulizia_piano()): dati i parametri (n, giorni, scelta) dice quali elementi si tolgono e quanto
 * pesano. La pagina mostra il piano con i parametri di partenza, il riepilogo lo ricalcola con quelli scelti, e l'esecuzione
 * (pulizia_esegui()) lo ricalcola ancora una volta sul server: niente nomi di file o di tabelle arrivano dal browser, solo
 * numeri controllati e scelte che devono stare nell'elenco calcolato in quel momento.
 *
 * Mai toccati: includes/config.local.php, il codice del CMS, uploads/ fuori dai file dei contatti e dalle immagini spuntate,
 * le tabelle senza il prefisso del sito (e quelle di un'altra installazione con un prefisso più lungo nello stesso database).
 * Niente si cancella da solo: la dashboard mostra solo un avviso oltre PULIZIA_SOGLIA_AVVISO.
 */

require_once __DIR__ . '/aggiornamenti.php';
require_once __DIR__ . '/copia-sito.php';

/** Oltre questo peso recuperabile la dashboard mostra l'avviso (200 MB). */
const PULIZIA_SOGLIA_AVVISO = 209715200;
/** Secondi per le misure delle cartelle in una richiesta (CMS_PULIZIA_TEMPO nel config lo cambia, per le prove). */
const PULIZIA_TEMPO = 8;
/** Secondi per il controllo delle immagini non usate (CMS_PULIZIA_TEMPO_MEDIA). */
const PULIZIA_TEMPO_MEDIA = 25;
/** I numeri dei parametri (tieni le ultime N, più vecchi di N giorni) stanno fra 0 e questo. */
const PULIZIA_N_MAX = 100000;
/** Righe che restano in un log in uso quando si accorcia. */
const PULIZIA_LOG_RIGHE = 50;
/** File di logs/ che non si toccano mai. */
const PULIZIA_LOG_PROTETTI = ['cron-stato.json', 'controllo-accesso.txt', '.htaccess', 'index.html', 'index.php'];
/** I log che il CMS scrive: si accorciano, non si cancellano (il pannello legge la loro data e l'ultima riga). */
const PULIZIA_LOG_IN_USO = ['cron-daily.log', 'newsletter.log', 'php-errori.log', 'seo.log'];
/** Quanti file al massimo elenca il controllo delle immagini. */
const PULIZIA_MEDIA_MAX = 400;

// ------------------------------------------------------------
// Misure
// ------------------------------------------------------------

/** Il momento in cui le misure di questa richiesta si fermano. $azzera riparte da adesso (con $secondi, o il predefinito). */
function pulizia_scadenza(bool $azzera = false, int $secondi = 0): float {
    static $fine = null;
    if ($fine === null || $azzera) {
        $s = $secondi > 0 ? $secondi : (defined('CMS_PULIZIA_TEMPO') ? max(1, (int)CMS_PULIZIA_TEMPO) : PULIZIA_TEMPO);
        $fine = microtime(true) + $s;
    }
    return $fine;
}

function pulizia_tempo_finito(): bool { return microtime(true) > pulizia_scadenza(); }

/**
 * Il peso di una cartella (ricorsivo, senza seguire i collegamenti), fino alla scadenza. Ritorna ['byte', 'file', 'finito'];
 * 'finito' false vuol dire che il conto si è fermato a metà (il peso vero è di più).
 */
function pulizia_misura(string $dir): array {
    $byte = 0; $file = 0;
    if (!is_dir($dir) || is_link($dir)) return ['byte' => is_file($dir) ? (int)@filesize($dir) : 0, 'file' => is_file($dir) ? 1 : 0, 'finito' => true];
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($it as $f) {
            if ($f->isLink() || !$f->isFile()) continue;
            $byte += (int)$f->getSize(); $file++;
            if ($file % 200 === 0 && pulizia_tempo_finito()) return ['byte' => $byte, 'file' => $file, 'finito' => false];
        }
    } catch (Throwable $e) {
        return ['byte' => $byte, 'file' => $file, 'finito' => false];
    }
    return ['byte' => $byte, 'file' => $file, 'finito' => true];
}

/** Un peso in parole: "12,3 MB", "640 KB", "512 byte". */
function pulizia_peso(int $b): string {
    if ($b >= 1073741824) return number_format($b / 1073741824, 1, ',', '.') . ' GB';
    if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
    if ($b >= 1024) return number_format($b / 1024, 0, ',', '.') . ' KB';
    return $b . ' byte';
}

/** Un numero con il punto delle migliaia. */
function pulizia_num(int $n): string { return number_format($n, 0, ',', '.'); }

/** Il percorso vero di $p sta dentro $base (e non è un collegamento)? */
function pulizia_dentro(string $p, string $base): bool {
    if (is_link($p)) return false;
    $vb = realpath($base); $vp = realpath($p);
    if ($vb === false || $vp === false) return false;
    $vb = rtrim(str_replace('\\', '/', $vb), '/'); $vp = str_replace('\\', '/', $vp);
    return str_starts_with($vp, $vb . '/');
}

/** Cancella un file solo se sta dentro $base. Ritorna i byte tolti (0 se non l'ha tolto). */
function pulizia_togli_file(string $p, string $base): int {
    if (!is_file($p) || !pulizia_dentro($p, $base)) return 0;
    $b = (int)@filesize($p);
    return @unlink($p) ? $b : 0;
}

/** Cancella una cartella con quello che c'è dentro, solo se sta dentro $base. I collegamenti si tolgono senza seguirli. */
function pulizia_togli_cartella(string $dir, string $base): int {
    if (!is_dir($dir) || !pulizia_dentro($dir, $base)) return 0;
    $tolti = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $p = $f->getPathname();
        if ($f->isLink()) { @unlink($p); continue; }
        if ($f->isDir()) { @rmdir($p); continue; }
        $b = (int)$f->getSize();
        if (@unlink($p)) $tolti += $b;
    }
    @rmdir($dir);
    return $tolti;
}

// ------------------------------------------------------------
// Lavori in corso: quando certe voci si bloccano
// ------------------------------------------------------------

/** Un ripristino o un aggiornamento sta lavorando adesso (il blocco del ripristino è preso da un'altra connessione)? */
function pulizia_blocco_ripristino(): bool {
    try {
        $pdo = db();
        $chi = $pdo->query('SELECT IS_USED_LOCK(' . $pdo->quote(copia_blocco_nome()) . ')')->fetchColumn();
        return $chi !== null && $chi !== false && (int)$chi !== (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    } catch (Throwable $e) { return true; }
}

/** C'è il marcatore di un ripristino in corso o interrotto (aggiornamenti/copie/.ripristino-in-corso.json)? */
function pulizia_marcatore(): bool { return is_file(copia_marcatore_file()); }

/** Un cambio del database è in corso (aggiornamenti/cambia-db/lavoro.json)? */
function pulizia_cambio_in_corso(): bool { return is_file(dirname(__DIR__) . '/aggiornamenti/cambia-db/lavoro.json'); }

/** Il motivo per cui le voci legate a ripristini e cambi si fermano, o ''. */
function pulizia_motivo_lavoro(bool $conMarcatore = true): string {
    if ($conMarcatore && pulizia_marcatore()) return 'C\'è un ripristino della copia in corso o rimasto a metà: finché il segno del ripristino resta (in Impostazioni > Copia del sito il pannello prova a sistemarlo a ogni apertura), questa voce non si tocca.';
    if (pulizia_blocco_ripristino()) return 'Un ripristino o un aggiornamento sta lavorando proprio adesso: riprova fra qualche minuto.';
    if (pulizia_cambio_in_corso()) return 'Un cambio del database è in corso: finiscilo o annullalo in Impostazioni > Cambia database.';
    return '';
}

// ------------------------------------------------------------
// Database: tabelle del sito, pesi, tabelle di lavoro
// ------------------------------------------------------------

/** Le statistiche delle tabelle fresche (MySQL 8 le tiene in memoria fino a un giorno). */
function pulizia_statistiche_fresche(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    try { db()->exec('SET SESSION information_schema_stats_expiry = 0'); } catch (Throwable $e) { /* MariaDB e MySQL 5.7 non l'hanno: le statistiche sono già fresche */ }
}

/** Tutte le tabelle del database (nome => tipo BASE TABLE o VIEW). */
function pulizia_tutte_tabelle(): array {
    $out = [];
    foreach (db()->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $r) $out[(string)$r[0]] = strtoupper((string)($r[1] ?? ''));
    return $out;
}

/**
 * Il peso delle tabelle del sito (solo quelle con il prefisso, senza quelle di un'altra installazione), da information_schema:
 * [nome => ['dati', 'indici', 'libero', 'righe', 'media']], dalla più pesante. Con $nomi, solo quelle.
 */
function pulizia_pesi_tabelle(?array $nomi = null): array {
    pulizia_statistiche_fresche();
    $nomi = $nomi ?? copia_tabelle_sito();
    if (!$nomi) return [];
    $out = [];
    $st = db()->prepare('SELECT TABLE_NAME, DATA_LENGTH, INDEX_LENGTH, DATA_FREE, TABLE_ROWS, AVG_ROW_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'');
    $st->execute([DB_NAME]);
    $vuoi = array_flip($nomi);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) {
        if (!isset($vuoi[(string)$r[0]])) continue;
        $out[(string)$r[0]] = ['dati' => (int)$r[1], 'indici' => (int)$r[2], 'libero' => (int)$r[3], 'righe' => (int)$r[4], 'media' => (int)$r[5]];
    }
    uasort($out, fn($a, $b) => ($b['dati'] + $b['indici']) <=> ($a['dati'] + $a['indici']));
    return $out;
}

/** La tabella del sito $nome (senza prefisso) esiste? */
function pulizia_tabella_c_e(string $nome): bool {
    static $c = null;
    if ($c === null) $c = array_flip(copia_tabelle_sito());
    return isset($c[DB_PREFIX . $nome]);
}

/** Il peso medio di una riga di una tabella del sito (information_schema), almeno 1. */
function pulizia_riga_media(string $nome): int {
    $p = pulizia_pesi_tabelle([DB_PREFIX . $nome]);
    $x = $p[DB_PREFIX . $nome] ?? null;
    if (!$x) return 100;
    if ($x['media'] > 0) return $x['media'];
    return $x['righe'] > 0 ? max(1, intdiv($x['dati'], $x['righe'])) : 100;
}

/**
 * Le tabelle di lavoro rimaste da un ripristino o da un cambio interrotto: rip<6 esadecimali>_ o vec<6 esadecimali>_ seguiti
 * dal prefisso del sito, senza le tabelle che sarebbero di un'altra installazione (prefisso più lungo con settings e users).
 * Con il prefisso vuoto non si sa quali sono del sito: nessuna.
 */
function pulizia_tabelle_lavoro(): array {
    if (DB_PREFIX === '') return [];
    $tutte = pulizia_tutte_tabelle();
    $nomi = array_keys($tutte);
    $altri = copia_altri_prefissi(array_values(array_filter($nomi, fn($t) => str_starts_with($t, DB_PREFIX))), DB_PREFIX);
    $out = [];
    foreach ($tutte as $t => $tipo) {
        if ($tipo === 'VIEW') continue;
        if (!preg_match('/^(?:' . COPIA_APPOGGIO . '|' . COPIA_PARCHEGGIO . ')[0-9a-f]{6}_' . preg_quote(DB_PREFIX, '/') . '([a-z0-9_]{1,60})$/', $t, $m)) continue;
        if (copia_di_altri(DB_PREFIX . $m[1], $altri)) continue;
        $out[] = $t;
    }
    sort($out);
    return $out;
}

// ------------------------------------------------------------
// Le voci
// ------------------------------------------------------------

/**
 * Le voci, nell'ordine della pagina: id => definizione. 'modo': ultime (tieni le ultime N), giorni (più vecchi di N giorni),
 * scelta (spunte su un elenco), tutto, ottimizza. 'pesante': il riepilogo chiede la password dell'amministratore.
 */
function pulizia_definizioni(): array {
    return [
        'pacchetti' => ['gruppo' => 'file', 'modo' => 'scelta', 'pesante' => false, 'titolo' => 'Pacchetti di aggiornamento già applicati',
            'spiega' => 'Gli zip caricati in Impostazioni > Aggiornamenti e già installati: il sito ha già tutto quello che portavano.',
            'perdi' => 'La loro riga nell\'elenco dei pacchetti. Gli snapshot fatti prima di applicarli restano (voce qui sotto).'],
        'snapshot' => ['gruppo' => 'file', 'modo' => 'ultime', 'pesante' => true, 'n' => 3, 'min' => 0, 'titolo' => 'Snapshot degli aggiornamenti',
            'spiega' => 'Le copie di sicurezza che il pannello fa prima di ogni aggiornamento (database e file sovrascritti).',
            'perdi' => 'La possibilità di tornare a com\'era il sito prima degli aggiornamenti più vecchi. Si tengono sempre le ultime che scegli.'],
        'copie' => ['gruppo' => 'file', 'modo' => 'scelta', 'pesante' => true, 'titolo' => 'Copie del sito sul server',
            'spiega' => 'Gli zip fatti in Impostazioni > Copia del sito (anche quelli caricati per un ripristino) che stanno ancora sul server.',
            'perdi' => 'La copia sul server: prima scaricala sul tuo computer se vuoi tenerla. Di partenza resta la più recente.'],
        'scarto' => ['gruppo' => 'file', 'modo' => 'tutto', 'pesante' => false, 'titolo' => 'File messi da parte dai ripristini',
            'spiega' => 'I file che c\'erano nel sito ma non nella copia ripristinata: il ripristino li sposta da parte invece di cancellarli.',
            'perdi' => 'La possibilità di riprendere a mano uno di quei file (altrimenti si tolgono da soli dopo 30 giorni, al ripristino successivo).'],
        'cambiadb' => ['gruppo' => 'file', 'modo' => 'tutto', 'pesante' => true, 'titolo' => 'Avanzi del cambio database',
            'spiega' => 'La copia del file di configurazione di prima del cambio database e i file di stato del cambio.',
            'perdi' => 'Dopo non si può più tornare al database di prima con "Torna al database di prima": la copia del file che serve per tornare non c\'è più.'],
        'log' => ['gruppo' => 'file', 'modo' => 'giorni', 'pesante' => false, 'giorni' => 30, 'min' => 0, 'titolo' => 'Log del sito',
            'spiega' => 'I registri in logs/: quelli fermi da più dei giorni scelti e quelli archiviati si tolgono; quelli in uso si accorciano alle ultime ' . PULIZIA_LOG_RIGHE . ' righe.',
            'perdi' => 'Le righe vecchie dei registri (errori, cron, newsletter): servono solo per capire un problema passato.'],
        'contatti' => ['gruppo' => 'file', 'modo' => 'giorni', 'pesante' => false, 'giorni' => 7, 'min' => 0, 'titolo' => 'File dei contatti (esportazioni e importazioni)',
            'spiega' => 'I file CSV delle esportazioni dei contatti, dei file importati e degli scarti, nella cartella privata delle immagini. Sono dati personali: è meglio non tenerli.',
            'perdi' => 'Il file da scaricare di un\'esportazione o di un\'importazione vecchia. I contatti nel database restano tutti.'],
        'versioni' => ['gruppo' => 'db', 'modo' => 'ultime', 'pesante' => true, 'n' => 20, 'min' => 1, 'titolo' => 'Versioni vecchie dell\'editor',
            'spiega' => 'Le versioni salvate a ogni modifica di pagine, articoli, header, footer e stile (Editor > Versioni). Per ogni oggetto restano le ultime che scegli.',
            'perdi' => 'La possibilità di tornare alle versioni più vecchie. Le versioni pubblicate e quelle con una nota scritta a mano non si toccano mai.'],
        'cestino' => ['gruppo' => 'db', 'modo' => 'giorni', 'pesante' => true, 'giorni' => 30, 'min' => 0, 'titolo' => 'Contenuti nel cestino',
            'spiega' => 'Pagine, articoli e altri contenuti messi nel cestino da più dei giorni scelti.',
            'perdi' => 'Il contenuto, per sempre: dopo non si può più ripristinare dal cestino.'],
        'registri' => ['gruppo' => 'db', 'modo' => 'giorni', 'pesante' => true, 'giorni' => 90, 'min' => 30, 'titolo' => 'Righe vecchie dei registri',
            'spiega' => 'Le righe più vecchie dei giorni scelti nei registri del database (sicurezza, segnalazioni della protezione, motori di ricerca, posta, automazioni, AI collegate, copie altrove) e i tentativi di accesso finiti da più di un giorno.',
            'perdi' => 'La storia vecchia dei registri. Gli accessi riusciti e i blocchi del registro di sicurezza restano (servono a riconoscere i tuoi dispositivi); i blocchi in corso restano. Gli eventi della newsletter non si toccano: servono ai report e alle automazioni.'],
        'lavoro' => ['gruppo' => 'db', 'modo' => 'tutto', 'pesante' => true, 'titolo' => 'Tabelle rimaste da un ripristino o da un cambio interrotto',
            'spiega' => 'Le tabelle di lavoro (con il nome che comincia per rip o vec) che un ripristino della copia o un cambio del database interrotti hanno lasciato nel database.',
            'perdi' => 'Niente che il sito usi: sono copie di lavoro rimaste a metà.'],
        'ottimizza' => ['gruppo' => 'db', 'modo' => 'ottimizza', 'pesante' => true, 'titolo' => 'Ottimizza le tabelle',
            'spiega' => 'Riscrive le tabelle del sito che hanno spazio vuoto dentro (dopo molte cancellazioni), per restituirlo. Si fa per ultima, dopo le altre voci.',
            'perdi' => 'Niente. Mentre lavora le tabelle restano bloccate per qualche secondo: meglio farlo quando il sito ha poche visite.'],
        'media' => ['gruppo' => 'media', 'modo' => 'scelta', 'pesante' => true, 'titolo' => 'Immagini che nessuna parte del sito usa',
            'spiega' => 'Le immagini della libreria Media che non compaiono in nessuna pagina, articolo, modello, impostazione, form, popup, email, addon o tema. Controllale una per una: si tolgono solo quelle che spunti.',
            'perdi' => 'L\'immagine e le sue miniature, per sempre. Se un altro sito la mostra con un collegamento diretto, lì sparisce.'],
    ];
}

/** Le voci che il sito ha (cambia database e media solo se il motore c'è). */
function pulizia_ids(): array {
    $ids = array_keys(pulizia_definizioni());
    if (!is_file(__DIR__ . '/cambia-db.php')) $ids = array_values(array_diff($ids, ['cambiadb']));
    if (!function_exists('media_elenco') && !is_file(__DIR__ . '/media.php')) $ids = array_values(array_diff($ids, ['media']));
    return $ids;
}

/**
 * Un numero di un parametro: intero da $min a PULIZIA_N_MAX, altrimenti null (negativo, con lettere, enorme, una lista).
 * Una stringa vuota vale $vuoto.
 */
function pulizia_numero($v, int $min, ?int $vuoto = null): ?int {
    if (is_array($v)) return null;
    $v = trim((string)$v);
    if ($v === '') return $vuoto;
    if (!preg_match('/^\d{1,6}$/', $v)) return null;
    $n = (int)$v;
    return ($n < $min || $n > PULIZIA_N_MAX) ? null : $n;
}

/**
 * I parametri di una voce dal modulo ($in = $_POST['p'][$id] o []), controllati. Ritorna [parametri, errore]. Senza modulo
 * ($in null) i parametri di partenza.
 */
function pulizia_parametri(string $id, ?array $in): array {
    $d = pulizia_definizioni()[$id] ?? null;
    if (!$d) return [[], 'Voce sconosciuta.'];
    $p = [];
    if ($d['modo'] === 'ultime') {
        $n = $in === null ? $d['n'] : pulizia_numero($in['n'] ?? '', $d['min'], null);
        if ($n === null) return [['n' => $d['n']], 'Scrivi quante tenerne con un numero intero da ' . $d['min'] . ' a ' . pulizia_num(PULIZIA_N_MAX) . '.'];
        $p['n'] = $n;
        $p['zero'] = $in !== null && !empty($in['zero']) && !is_array($in['zero']);
    } elseif ($d['modo'] === 'giorni') {
        $g = $in === null ? $d['giorni'] : pulizia_numero($in['giorni'] ?? '', $d['min'], null);
        if ($g === null) return [['giorni' => $d['giorni']], 'Scrivi i giorni con un numero intero da ' . $d['min'] . ' a ' . pulizia_num(PULIZIA_N_MAX) . '.'];
        $p['giorni'] = $g;
    } elseif ($d['modo'] === 'scelta') {
        if ($in === null) $p['scelta'] = null; // di partenza: la proposta della voce
        else {
            $s = $in['scelta'] ?? [];
            if (!is_array($s)) $s = [];
            $p['scelta'] = array_values(array_unique(array_filter(array_map(fn($x) => is_string($x) ? $x : '', $s), fn($x) => $x !== '' && strlen($x) <= 600)));
        }
    }
    return [$p, ''];
}

/**
 * Il piano di una voce con i parametri dati: ['id', 'elementi' => [[chiave, nome, byte, data, proposto, nota]], 'byte', 'n',
 * 'righe', 'finito', 'bloccata', 'vuota', 'riassunto', 'tot_byte', 'tot_n', 'avviso']. 'elementi' sono tutti quelli che
 * la voce mostra; 'proposto' quelli che si tolgono con questi parametri. $media = il risultato di pulizia_media_controlla().
 */
function pulizia_piano(string $id, array $p, ?array $media = null): array {
    $piano = ['id' => $id, 'elementi' => [], 'byte' => 0, 'n' => 0, 'righe' => 0, 'finito' => true, 'bloccata' => '', 'riassunto' => '', 'tot_byte' => 0, 'tot_n' => 0, 'avviso' => '', 'stima' => false];
    try {
        $f = 'pulizia_piano_' . $id;
        if (!function_exists($f)) { $piano['bloccata'] = 'Voce sconosciuta.'; return $piano; }
        $piano = $f($p, $piano, $media);
    } catch (Throwable $e) {
        error_log('pulizia, piano ' . $id . ': ' . $e->getMessage());
        $piano['bloccata'] = 'Non sono riuscito a calcolare questa voce (il dettaglio è nel log degli errori del sito).';
        $piano['elementi'] = []; $piano['byte'] = 0; $piano['n'] = 0;
    }
    foreach ($piano['elementi'] as $e) { $piano['tot_byte'] += (int)$e['byte']; $piano['tot_n']++; if ($e['proposto']) { $piano['byte'] += (int)$e['byte']; $piano['n']++; $piano['righe'] += (int)($e['righe'] ?? 0); } }
    if ($piano['bloccata'] !== '') { $piano['byte'] = 0; $piano['n'] = 0; $piano['righe'] = 0; }
    return $piano;
}

/** Un elemento di un piano. */
function pulizia_el(string $chiave, string $nome, int $byte, int $data, bool $proposto, string $nota = '', int $righe = 0): array {
    return ['chiave' => $chiave, 'nome' => $nome, 'byte' => $byte, 'data' => $data, 'proposto' => $proposto, 'nota' => $nota, 'righe' => $righe];
}

/** In un piano a scelta: l'elemento $chiave è scelto? (scelta null = la proposta di partenza $diPartenza). */
function pulizia_scelto(array $p, string $chiave, bool $diPartenza): bool {
    return ($p['scelta'] ?? null) === null ? $diPartenza : in_array($chiave, $p['scelta'], true);
}

// --- file ---

function pulizia_piano_pacchetti(array $p, array $piano): array {
    foreach (agg_lista_pacchetti() as $s) {
        if (($s['stato'] ?? '') !== 'applicato' || !preg_match('/^[A-Za-z0-9_.-]+$/', (string)$s['id'])) continue;
        $id = (string)$s['id'];
        $byte = (int)@filesize(agg_dir('pacchetti') . '/' . $id . '.zip') + (int)@filesize(agg_dir('pacchetti') . '/' . $id . '.json');
        $quando = strtotime((string)($s['applicato'] ?? $s['caricato'] ?? '')) ?: 0;
        $piano['elementi'][] = pulizia_el($id, (string)($s['nome'] ?? $id), $byte, $quando, pulizia_scelto($p, $id, true), 'applicato');
    }
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessun pacchetto già applicato sul server.';
    return $piano;
}

/** Gli snapshot, dal più recente: id => [dir, creato, etichetta, completo]. Anche le cartelle senza meta.json di più di un giorno. */
function pulizia_snapshot_elenco(): array {
    $out = [];
    foreach (agg_lista_snapshot() as $m) {
        $id = (string)($m['id'] ?? '');
        if ($id === '' || $id !== basename($id) || !is_dir(agg_dir('snapshot') . '/' . $id)) continue;
        $out[$id] = ['creato' => strtotime((string)($m['creato'] ?? '')) ?: (int)@filemtime(agg_dir('snapshot') . '/' . $id), 'etichetta' => (string)($m['etichetta'] ?? $id), 'completo' => true];
    }
    foreach ((array)@scandir(agg_dir('snapshot')) as $n) {
        if (!is_string($n) || $n === '.' || $n === '..' || isset($out[$n]) || !preg_match('/^[A-Za-z0-9_.-]+$/', $n)) continue;
        $d = agg_dir('snapshot') . '/' . $n;
        if (!is_dir($d) || is_link($d) || is_file($d . '/meta.json') || (int)@filemtime($d) > time() - 86400) continue;
        $out[$n] = ['creato' => (int)@filemtime($d), 'etichetta' => 'snapshot rimasto a metà', 'completo' => false];
    }
    uasort($out, fn($a, $b) => $b['creato'] <=> $a['creato']);
    return $out;
}

function pulizia_piano_snapshot(array $p, array $piano): array {
    $motivo = pulizia_motivo_lavoro(false);
    if ($motivo !== '') { $piano['bloccata'] = $motivo; return $piano; }
    $n = (int)($p['n'] ?? 3); $i = 0;
    foreach (pulizia_snapshot_elenco() as $id => $s) {
        $m = pulizia_misura(agg_dir('snapshot') . '/' . $id);
        if (!$m['finito']) $piano['finito'] = false;
        // gli snapshot rimasti a metà non contano fra quelli da tenere: si tolgono sempre
        $tieni = $s['completo'] && $i < $n;
        if ($s['completo']) $i++;
        $piano['elementi'][] = pulizia_el($id, $s['etichetta'], $m['byte'], $s['creato'], !$tieni, $tieni ? 'resta' : '');
    }
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessuno snapshot sul server.';
    if ($n === 0 && $piano['elementi']) $piano['avviso'] = 'Con 0 si tolgono tutti gli snapshot, anche l\'ultimo: dopo non si può tornare indietro da nessun aggiornamento. Serve la conferma nel riepilogo.';
    return $piano;
}

function pulizia_piano_copie(array $p, array $piano): array {
    $motivo = pulizia_motivo_lavoro(true);
    $prima = true;
    foreach (copia_elenco(false) as $c) {
        $f = (string)$c['file'];
        $piano['elementi'][] = pulizia_el($f, $f, (int)$c['peso'], (int)$c['data'], pulizia_scelto($p, $f, !$prima), $prima ? 'la più recente' : '');
        $prima = false;
    }
    if ($motivo !== '') { $piano['bloccata'] = $motivo; return $piano; }
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessuna copia del sito sul server.';
    return $piano;
}

function pulizia_piano_scarto(array $p, array $piano): array {
    if (pulizia_marcatore() || pulizia_blocco_ripristino()) { $piano['bloccata'] = pulizia_motivo_lavoro(true); return $piano; }
    foreach (copia_scarto_elenco() as $x) $piano['elementi'][] = pulizia_el((string)$x['nome'], (string)$x['nome'] . ' (' . pulizia_num((int)$x['file']) . ' file)', (int)$x['peso'], (int)$x['data'], true);
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessun file messo da parte.';
    return $piano;
}

/** I file degli avanzi del cambio database: percorso => base in cui deve stare. */
function pulizia_cambiadb_file(): array {
    $out = [];
    $guardia = defined('CAMBIADB_GUARDIA') ? CAMBIADB_GUARDIA : "<?php http_response_code(404); exit;";
    $copia = function (string $dir) use (&$out, $guardia) {
        foreach ((array)@scandir($dir) as $n) {
            if (!is_string($n) || !preg_match('/^config\.local\.prima-\d{8}-\d{6}(?:-\d+)?\.php$/', $n)) continue;
            $f = $dir . '/' . $n;
            if (!is_file($f) || is_link($f)) continue;
            // solo le copie scritte dal cambio (cominciano con l'uscita immediata): mai un altro file
            $h = @fopen($f, 'rb'); $testa = $h ? (string)fread($h, 40) : ''; if ($h) fclose($h);
            if (!str_starts_with($testa, substr($guardia, 0, 30))) continue;
            $out[$f] = $dir;
        }
    };
    $copia(__DIR__);
    $dir = dirname(__DIR__) . '/aggiornamenti/cambia-db';
    if (is_dir($dir)) {
        $copia($dir);
        foreach ((array)@scandir($dir) as $n) {
            if (!is_string($n) || !preg_match('/^(?:ultimo\.json|(?:lavoro|ultimo)\.json\.[a-f0-9]{8}\.tmp)$/', $n)) continue;
            if (is_file($dir . '/' . $n) && !is_link($dir . '/' . $n)) $out[$dir . '/' . $n] = $dir;
        }
    }
    return $out;
}

function pulizia_piano_cambiadb(array $p, array $piano): array {
    if (pulizia_cambio_in_corso()) { $piano['bloccata'] = 'Un cambio del database è in corso: finiscilo o annullalo in Impostazioni > Cambia database.'; return $piano; }
    foreach (pulizia_cambiadb_file() as $f => $base) {
        $nome = basename($f) === 'ultimo.json' ? 'stato dell\'ultimo cambio' : (str_starts_with(basename($f), 'config.local.prima-') ? 'copia del file di configurazione di prima (' . basename($f) . ')' : basename($f));
        $piano['elementi'][] = pulizia_el(basename($f), $nome, (int)@filesize($f), (int)@filemtime($f), true);
    }
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessun avanzo di un cambio del database.';
    return $piano;
}

/** La cartella dei log. */
function pulizia_log_dir(): string { return cms_radice() . '/logs'; }

/** Il peso delle ultime $righe righe di un file (quello che resta quando si accorcia). */
function pulizia_coda_peso(string $f, int $righe): int {
    $dim = (int)@filesize($f);
    if ($dim === 0) return 0;
    $h = @fopen($f, 'rb'); if (!$h) return $dim;
    $pezzo = 8192; $pos = $dim; $nl = 0; $resta = $dim;
    // se il file finisce con un a capo, quell'a capo non conta come riga in più
    fseek($h, -1, SEEK_END); $ultimo = fread($h, 1); $salta = $ultimo === "\n" ? 1 : 0;
    while ($pos > 0) {
        $leggi = min($pezzo, $pos); $pos -= $leggi;
        fseek($h, $pos); $buf = (string)fread($h, $leggi);
        for ($i = strlen($buf) - 1; $i >= 0; $i--) {
            if ($buf[$i] !== "\n") continue;
            if ($salta) { $salta = 0; continue; }
            if (++$nl >= $righe) { fclose($h); return $dim - ($pos + $i + 1); }
        }
    }
    fclose($h);
    return $dim;
}

/** Un file di log archiviato (.gz, .zip, .1, .old, .bak, nome.log.qualcosa)? */
function pulizia_log_archiviato(string $n): bool {
    return (bool)preg_match('/(\.(gz|zip|bz2|xz|old|bak|\d+)$)|(\.log[.-].+$)/i', $n);
}

function pulizia_piano_log(array $p, array $piano): array {
    $g = (int)($p['giorni'] ?? 30);
    $dir = pulizia_log_dir();
    if (!is_dir($dir)) { $piano['riassunto'] = 'La cartella dei log è vuota.'; return $piano; }
    $limite = time() - $g * 86400;
    foreach ((array)@scandir($dir) as $n) {
        if (!is_string($n) || $n === '.' || $n === '..' || in_array($n, PULIZIA_LOG_PROTETTI, true)) continue;
        $f = $dir . '/' . $n;
        if (!is_file($f) || is_link($f)) continue;
        $dim = (int)@filesize($f); $mt = (int)@filemtime($f);
        if (in_array($n, PULIZIA_LOG_IN_USO, true)) {
            $resta = pulizia_coda_peso($f, PULIZIA_LOG_RIGHE);
            $via = max(0, $dim - $resta);
            $piano['elementi'][] = pulizia_el($n, $n, $via, $mt, $via > 0, $via > 0 ? 'si accorcia alle ultime ' . PULIZIA_LOG_RIGHE . ' righe' : 'in uso, già corto');
        } elseif (pulizia_log_archiviato($n) || $mt < $limite) {
            $piano['elementi'][] = pulizia_el($n, $n, $dim, $mt, true, pulizia_log_archiviato($n) ? 'archiviato' : 'fermo da più di ' . $g . ' giorni');
        } else {
            $piano['elementi'][] = pulizia_el($n, $n, $dim, $mt, false, 'scritto di recente: resta');
        }
    }
    usort($piano['elementi'], fn($a, $b) => $b['byte'] <=> $a['byte']);
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessun log da togliere.';
    return $piano;
}

/** Le cartelle private dei file dei contatti (uploads/contatti-<16 esadecimali>/). */
function pulizia_contatti_dir(): array {
    $out = [];
    $up = cms_uploads_dir();
    foreach ((array)@scandir($up) as $n) if (is_string($n) && preg_match('/^contatti-[a-f0-9]{16}$/', $n) && is_dir($up . '/' . $n) && !is_link($up . '/' . $n)) $out[] = $up . '/' . $n;
    return $out;
}

/** I file dei contatti ancora in lavorazione (importazioni non finite, esportazioni in corso): nome => true. */
function pulizia_contatti_in_uso(): array {
    $uso = [];
    try {
        if (pulizia_tabella_c_e('newsletter_importazioni')) foreach (db()->query('SELECT file, scarti_file FROM ' . tbl('newsletter_importazioni') . " WHERE stato NOT IN ('finita', 'errore')")->fetchAll(PDO::FETCH_NUM) as $r) { $uso[(string)$r[0]] = true; $uso[(string)$r[1]] = true; }
        if (pulizia_tabella_c_e('newsletter_esportazioni')) foreach (db()->query('SELECT file FROM ' . tbl('newsletter_esportazioni') . " WHERE stato = 'in_corso'")->fetchAll(PDO::FETCH_COLUMN) as $x) $uso[(string)$x] = true;
    } catch (Throwable $e) { error_log('pulizia, file dei contatti in uso: ' . $e->getMessage()); return ['*' => true]; }
    unset($uso['']);
    return $uso;
}

function pulizia_piano_contatti(array $p, array $piano): array {
    $g = (int)($p['giorni'] ?? 7);
    $uso = pulizia_contatti_in_uso();
    if (isset($uso['*'])) { $piano['bloccata'] = 'Non riesco a leggere quali file dei contatti sono ancora in lavorazione: riprova più tardi.'; return $piano; }
    $limite = time() - $g * 86400;
    foreach (pulizia_contatti_dir() as $dir) {
        foreach ((array)@scandir($dir) as $n) {
            if (!is_string($n) || !preg_match('/^(imp|scarti|esp)-\d+-[a-f0-9]{12}\.(jsonl|csv)$/', $n, $m)) continue;
            $f = $dir . '/' . $n;
            if (!is_file($f) || is_link($f)) continue;
            $mt = (int)@filemtime($f);
            $tipo = ['imp' => 'file importato', 'scarti' => 'scarti di un\'importazione', 'esp' => 'esportazione'][$m[1]];
            $inUso = isset($uso[$n]);
            $piano['elementi'][] = pulizia_el(basename($dir) . '/' . $n, $tipo . ' del ' . date('d/m/Y', $mt), (int)@filesize($f), $mt, !$inUso && $mt < $limite, $inUso ? 'in lavorazione: resta' : ($mt < $limite ? '' : 'recente: resta'));
        }
    }
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessun file dei contatti sul server.';
    return $piano;
}

// --- database ---

function pulizia_piano_versioni(array $p, array $piano): array {
    if (!pulizia_tabella_c_e('editor_versioni')) { $piano['riassunto'] = 'Ancora nessuna versione salvata.'; return $piano; }
    $n = max(1, (int)($p['n'] ?? 20));
    $pdo = db();
    $pdo->setAttribute(agg_pdo_buffered_attr(), false);
    try {
        $st = $pdo->query('SELECT id, ambito, oggetto_id, pubblicata, nota_mano, LENGTH(dati) + LENGTH(oggetto_nome) + LENGTH(nota) + 120 AS peso FROM ' . tbl('editor_versioni') . ' ORDER BY ambito, oggetto_id, id DESC');
        $chiave = null; $pos = 0; $via = 0; $byte = 0; $oggetti = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $k = $r['ambito'] . '|' . $r['oggetto_id'];
            if ($k !== $chiave) { $chiave = $k; $pos = 0; }
            $pos++;
            if ($pos > $n && !(int)$r['pubblicata'] && !(int)$r['nota_mano']) { $via++; $byte += (int)$r['peso']; $oggetti[$k] = ($oggetti[$k] ?? 0) + 1; }
        }
        $st->closeCursor();
    } finally {
        $pdo->setAttribute(agg_pdo_buffered_attr(), true);
    }
    if ($via) $piano['elementi'][] = pulizia_el('versioni', pulizia_num($via) . ($via === 1 ? ' versione' : ' versioni') . ' di ' . pulizia_num(count($oggetti)) . (count($oggetti) === 1 ? ' oggetto' : ' oggetti'), $byte, 0, true, '', $via);
    else $piano['riassunto'] = 'Nessun oggetto ha più di ' . $n . ' versioni da togliere.';
    return $piano;
}

/** I tipi di contenuto con il cestino: tipo => tabella (senza prefisso). */
function pulizia_tipi_cestino(): array {
    require_once __DIR__ . '/contenuti.php';
    $out = [];
    foreach (contenuti_tipi() as $tipo => $t) {
        $tab = (string)($t['tabella'] ?? '');
        if ($tab === '' || !preg_match('/^[a-z0-9_]+$/', $tab) || !pulizia_tabella_c_e($tab)) continue;
        try { $col = db()->query('SHOW COLUMNS FROM ' . tbl($tab) . " LIKE 'cestino'")->fetch(); } catch (Throwable $e) { $col = false; }
        if ($col) $out[$tipo] = $tab;
    }
    return $out;
}

function pulizia_piano_cestino(array $p, array $piano): array {
    $g = (int)($p['giorni'] ?? 30);
    foreach (pulizia_tipi_cestino() as $tipo => $tab) {
        $media = pulizia_riga_media($tab);
        $st = db()->prepare('SELECT id, title, cestino FROM ' . tbl($tab) . ' WHERE cestino IS NOT NULL ORDER BY cestino');
        $st->execute();
        foreach ($st->fetchAll() as $r) {
            $q = strtotime((string)$r['cestino']) ?: 0;
            $vecchio = $q < time() - $g * 86400;
            $piano['elementi'][] = pulizia_el($tipo . ':' . (int)$r['id'], trim((string)$r['title']) !== '' ? (string)$r['title'] : '(senza titolo)', $media, $q, $vecchio, $vecchio ? '' : 'nel cestino da meno di ' . $g . ' giorni: resta', 1);
        }
    }
    $piano['stima'] = true;
    if (!$piano['elementi']) $piano['riassunto'] = 'Il cestino è vuoto.';
    return $piano;
}

/** I registri del database: tabella => [colonna della data, condizione in più, etichetta, giorni fissi o 0]. */
function pulizia_registri(): array {
    return [
        'sicurezza_log' => ['creato', "evento NOT IN ('login', 'blocco')", 'registro di sicurezza', 0],
        'sicurezza_tentativi' => ['ultimo', '(bloccato_fino IS NULL OR bloccato_fino < NOW())', 'tentativi di accesso', 1],
        'sicurezza_csp' => ['ultimo', '', 'segnalazioni della protezione delle pagine', 0],
        'seo_log' => ['quando', '', 'registro dei motori di ricerca', 0],
        'posta_registro' => ['quando', '', 'registro della posta', 0],
        'newsletter_flussi_registro' => ['creato', '', 'registro delle automazioni', 0],
        'ai_registro' => ['creato', '', 'registro delle AI collegate', 0],
        'copia_altrove_registro' => ['quando', '', 'registro delle copie altrove', 0],
    ];
}

/** La condizione SQL delle righe vecchie di un registro. */
function pulizia_registro_dove(string $tab, int $giorni): string {
    [$col, $piu, , $fissi] = pulizia_registri()[$tab];
    $g = $fissi > 0 ? $fissi : $giorni;
    return '`' . $col . '` < DATE_SUB(NOW(), INTERVAL ' . (int)$g . ' DAY)' . ($piu !== '' ? ' AND ' . $piu : '');
}

function pulizia_piano_registri(array $p, array $piano): array {
    $g = max(30, (int)($p['giorni'] ?? 90));
    foreach (pulizia_registri() as $tab => [$col, $piu, $etichetta, $fissi]) {
        if (!pulizia_tabella_c_e($tab)) continue;
        $n = (int)db()->query('SELECT COUNT(*) FROM ' . tbl($tab) . ' WHERE ' . pulizia_registro_dove($tab, $g))->fetchColumn();
        $tot = (int)db()->query('SELECT COUNT(*) FROM ' . tbl($tab))->fetchColumn();
        if (!$tot) continue;
        $piano['elementi'][] = pulizia_el($tab, $etichetta . ' (' . pulizia_num($tot) . ' righe)', $n * pulizia_riga_media($tab), 0, $n > 0, $n ? pulizia_num($n) . ' righe ' . ($fissi ? 'finite da più di un giorno' : 'più vecchie di ' . $g . ' giorni') : 'niente di vecchio', $n);
    }
    $piano['stima'] = true;
    if (!$piano['elementi']) $piano['riassunto'] = 'I registri sono vuoti.';
    return $piano;
}

function pulizia_piano_lavoro(array $p, array $piano): array {
    if (DB_PREFIX === '') { $piano['bloccata'] = 'Il sito non ha un prefisso delle tabelle: non si può dire quali tabelle di lavoro sono sue.'; return $piano; }
    $nomi = pulizia_tabelle_lavoro();
    $pesi = $nomi ? pulizia_pesi_tabelle($nomi) : [];
    foreach ($nomi as $t) $piano['elementi'][] = pulizia_el($t, $t, (int)(($pesi[$t]['dati'] ?? 0) + ($pesi[$t]['indici'] ?? 0)), 0, true, '', (int)($pesi[$t]['righe'] ?? 0));
    $motivo = pulizia_motivo_lavoro(true);
    if ($motivo !== '' && $piano['elementi']) { $piano['bloccata'] = $motivo; return $piano; }
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessuna tabella di lavoro rimasta.';
    return $piano;
}

function pulizia_piano_ottimizza(array $p, array $piano): array {
    foreach (pulizia_pesi_tabelle() as $t => $x) if ($x['libero'] > 0) $piano['elementi'][] = pulizia_el($t, $t, $x['libero'], 0, true, 'spazio vuoto dentro la tabella (stima)');
    $piano['stima'] = true;
    if (!$piano['elementi']) $piano['riassunto'] = 'Nessuna tabella con spazio vuoto da restituire.';
    return $piano;
}

// --- immagini ---

function pulizia_piano_media(array $p, array $piano, ?array $media): array {
    if ($media === null) { $piano['riassunto'] = 'Per vedere le immagini non usate premi "Controlla le immagini": il controllo legge tutto il sito e può durare qualche secondo.'; $piano['da_controllare'] = true; return $piano; }
    if (!$media['finito']) { $piano['bloccata'] = $media['motivo'] !== '' ? $media['motivo'] : 'Il controllo non è finito in tempo: per prudenza nessuna immagine è proposta.'; return $piano; }
    foreach ($media['liberi'] as $chiave => $g) {
        $el = pulizia_el((string)$chiave, $g['nome'], $g['byte'], $g['data'], pulizia_scelto($p, (string)$chiave, false), $g['file'] > 1 ? 'con ' . ($g['file'] - 1) . ($g['file'] === 2 ? ' miniatura' : ' miniature') : '');
        $el['membri'] = $g['membri']; $el['url'] = $g['url'];
        $piano['elementi'][] = $el;
    }
    if (!$piano['elementi']) $piano['riassunto'] = 'Tutte le immagini della libreria sono usate (o forse usate) da qualche parte.';
    return $piano;
}

/**
 * Il controllo delle immagini: ogni immagine della libreria (con le sue miniature e la WebP accanto) è "usata" se il suo
 * percorso compare in una colonna di testo di una tabella del sito o in un file dei temi e dei moduli; "forse usata" se
 * compare solo il nome (in un'altra cartella, senza estensione, con un'altra miniatura), se compare solo in una versione
 * vecchia dell'editor, o se ha un nome con caratteri che il controllo non sa riconoscere; altrimenti "libera".
 * Ritorna ['finito', 'motivo', 'liberi' => chiave => gruppo, 'forse' => chiave => gruppo con 'motivo', 'usati' => int,
 * 'totale' => int, 'secondi' => float]. Un gruppo: nome, url (dell'originale), byte, data, file, membri (url).
 */
function pulizia_media_controlla(): array {
    $inizio = microtime(true);
    $s = defined('CMS_PULIZIA_TEMPO_MEDIA') ? max(1, (int)CMS_PULIZIA_TEMPO_MEDIA) : PULIZIA_TEMPO_MEDIA;
    $fine = $inizio + $s;
    $esito = ['finito' => false, 'motivo' => '', 'liberi' => [], 'forse' => [], 'usati' => 0, 'totale' => 0, 'secondi' => 0.0];
    require_once __DIR__ . '/media.php';
    if (!function_exists('media_elenco')) { $esito['motivo'] = 'La libreria delle immagini non si legge.'; return $esito; }
    @set_time_limit($s + 30);
    // 1. i gruppi: cartella + nome senza estensione e senza -LxA delle miniature
    $gruppi = [];
    $upUrl = rtrim(cms_uploads_url(), '/');
    $panUrl = rtrim(cms_base(), '/') . '/assets/img';
    foreach (media_elenco() as $m) {
        $url = (string)$m['url'];
        $rel = str_starts_with($url, $upUrl . '/') ? substr($url, strlen($upUrl) + 1) : (str_starts_with($url, $panUrl . '/') ? 'assets/img/' . substr($url, strlen($panUrl) + 1) : '');
        if ($rel === '') continue;
        $dir = dirname($rel) === '.' ? '' : dirname($rel);
        $nome = (string)$m['nome'];
        $stem = preg_replace('/(-\d{2,4}x\d{2,4}(?:-\d+)?)?\.[A-Za-z0-9]+$/', '', $nome);
        $k = $dir . '/' . $stem;
        if (!isset($gruppi[$k])) $gruppi[$k] = ['nome' => '', 'url' => '', 'byte' => 0, 'data' => 0, 'file' => 0, 'membri' => [], 'rel' => [], 'stem' => $stem, 'strano' => false];
        $g = &$gruppi[$k];
        $g['membri'][] = $url; $g['rel'][] = $rel; $g['file']++; $g['byte'] += (int)$m['peso'];
        $webp = media_percorso_webp_pulizia($url);
        if ($webp !== null && is_file($webp)) $g['byte'] += (int)@filesize($webp);
        $g['data'] = max($g['data'], (int)$m['tempo']);
        if (!$m['miniatura'] || $g['url'] === '') { if (!$m['miniatura'] || $g['nome'] === '') { $g['nome'] = $rel; $g['url'] = $url; } }
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $nome)) $g['strano'] = true;
        unset($g);
    }
    $esito['totale'] = count($gruppi);
    if (!$gruppi) { $esito['finito'] = true; $esito['secondi'] = round(microtime(true) - $inizio, 1); return $esito; }
    // 2. i nomi citati: basename => [percorsi (fino a 4 cartelle) => fonte], e i nomi senza estensione
    $citati = []; $stemi = [];
    $leggi = function (string $testo, string $fonte) use (&$citati, &$stemi) {
        if ($testo === '' || !preg_match('/\.(?:jpe?g|png|webp|gif|svg|avif)/i', $testo)) return;
        $testi = [str_replace('\\/', '/', $testo)];
        if (str_contains($testi[0], '%')) $testi[] = rawurldecode($testi[0]);
        foreach ($testi as $t) {
            if (!preg_match_all('~((?:[A-Za-z0-9_.%-]+/){0,6})([A-Za-z0-9_.%-]+?\.(?:jpe?g|png|webp|gif|svg|avif))(?![A-Za-z0-9])~i', $t, $mm, PREG_SET_ORDER)) continue;
            foreach ($mm as $x) {
                $base = $x[2];
                // la WebP accanto (foto.jpg.webp) cita la foto
                if (preg_match('/^(.+\.(?:jpe?g|png))\.webp$/i', $base, $w)) $base = $w[1];
                $perc = $x[1];
                if (!isset($citati[$base][$perc]) || $citati[$base][$perc] === 'versione') $citati[$base][$perc] = $fonte;
                $st = preg_replace('/(-\d{2,4}x\d{2,4}(?:-\d+)?)?\.[A-Za-z0-9]+$/', '', $base);
                $stemi[strtolower($st)] = true;
            }
        }
    };
    $pdo = db();
    try {
        foreach (copia_tabelle_sito() as $t) {
            if (microtime(true) > $fine) { $esito['motivo'] = 'Il controllo si è fermato dopo ' . $s . ' secondi (il sito ha molti dati): per prudenza nessuna immagine è proposta.'; return $esito; }
            $cols = [];
            foreach ($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $t) . '`')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if (preg_match('/char|text|json|blob|enum|set/i', (string)$c['Type'])) $cols[] = (string)$c['Field'];
            }
            if (!$cols) continue;
            $versioni = $t === DB_PREFIX . 'editor_versioni';
            if ($versioni && !in_array('compresso', $cols, true)) $sel = $cols; else $sel = $cols;
            $sql = 'SELECT ' . implode(', ', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', $sel)) . ($versioni ? ', `compresso`' : '') . ' FROM `' . str_replace('`', '``', $t) . '`';
            $pdo->setAttribute(agg_pdo_buffered_attr(), false);
            $q = $pdo->query($sql);
            $righe = 0;
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                foreach ($cols as $c) {
                    $v = $r[$c];
                    if ($v === null || $v === '') continue;
                    $v = (string)$v;
                    if ($versioni && $c === 'dati' && (int)($r['compresso'] ?? 0)) { $d = @gzuncompress($v); $v = $d === false ? '' : $d; }
                    $leggi($v, $versioni ? 'versione' : 'vivo');
                }
                if (++$righe % 500 === 0 && microtime(true) > $fine) { $q->closeCursor(); $pdo->setAttribute(agg_pdo_buffered_attr(), true); $esito['motivo'] = 'Il controllo si è fermato dopo ' . $s . ' secondi (il sito ha molti dati): per prudenza nessuna immagine è proposta.'; return $esito; }
            }
            $q->closeCursor();
            $pdo->setAttribute(agg_pdo_buffered_attr(), true);
        }
    } catch (Throwable $e) {
        try { $pdo->setAttribute(agg_pdo_buffered_attr(), true); } catch (Throwable $e2) {}
        error_log('pulizia, controllo delle immagini: ' . $e->getMessage());
        $esito['motivo'] = 'Il controllo non è riuscito a leggere tutto il database: per prudenza nessuna immagine è proposta.';
        return $esito;
    }
    // i file dei temi e dei moduli (fogli di stile, JSON, pagine)
    foreach ([cms_radice() . '/temi', cms_radice() . '/moduli'] as $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($it as $f) {
            if ($f->isLink() || !$f->isFile() || $f->getSize() > 2097152 || !preg_match('/\.(css|json|html?|php|js|txt|svg)$/i', $f->getFilename())) continue;
            $leggi((string)@file_get_contents($f->getPathname()), 'vivo');
            if (microtime(true) > $fine) { $esito['motivo'] = 'Il controllo si è fermato dopo ' . $s . ' secondi: per prudenza nessuna immagine è proposta.'; return $esito; }
        }
    }
    // 3. il giudizio, gruppo per gruppo
    foreach ($gruppi as $k => $g) {
        $stato = 'libero'; $motivo = '';
        foreach ($g['rel'] as $rel) {
            $base = basename($rel);
            if (!isset($citati[$base])) continue;
            foreach ($citati[$base] as $perc => $fonte) {
                $tutto = $perc . $base;
                if ($tutto === $rel || str_ends_with($tutto, '/' . $rel) || ($perc !== '' && str_ends_with($rel, '/' . $tutto)) || str_ends_with($tutto, '/' . $rel)) {
                    if ($fonte === 'vivo') { $stato = 'usato'; break 2; }
                    $stato = 'forse'; $motivo = 'citata solo in una versione vecchia dell\'editor';
                } elseif ($stato === 'libero') { $stato = 'forse'; $motivo = 'un file con lo stesso nome è citato in un\'altra cartella'; }
            }
        }
        if ($stato === 'libero' && isset($stemi[strtolower($g['stem'])])) { $stato = 'forse'; $motivo = 'il nome compare con un\'altra estensione o un\'altra misura'; }
        if ($stato === 'libero' && $g['strano']) { $stato = 'forse'; $motivo = 'il nome ha spazi o lettere speciali: il controllo non è sicuro'; }
        if ($stato === 'libero' && $g['data'] > time() - 3600) { $stato = 'forse'; $motivo = 'caricata meno di un\'ora fa: forse la stai usando adesso'; }
        $pub = ['nome' => $g['nome'], 'url' => $g['url'], 'byte' => $g['byte'], 'data' => $g['data'], 'file' => $g['file'], 'membri' => $g['membri']];
        if ($stato === 'usato') $esito['usati']++;
        elseif ($stato === 'forse') $esito['forse'][$g['nome']] = $pub + ['motivo' => $motivo];
        else $esito['liberi'][$g['nome']] = $pub;
    }
    uasort($esito['liberi'], fn($a, $b) => $b['byte'] <=> $a['byte']);
    $esito['finito'] = true;
    $esito['secondi'] = round(microtime(true) - $inizio, 1);
    return $esito;
}

/** Il percorso sul disco della WebP accanto a un'immagine JPEG o PNG della libreria (foto.jpg.webp), o null. */
function media_percorso_webp_pulizia(string $url): ?string {
    if (!preg_match('/\.(jpe?g|png)$/i', $url)) return null;
    $up = rtrim(cms_uploads_url(), '/');
    if (!str_starts_with($url, $up . '/')) return null;
    return cms_uploads_dir() . substr($url, strlen($up)) . '.webp';
}

// ------------------------------------------------------------
// Tutte le voci, il totale, la stima per la dashboard
// ------------------------------------------------------------

/** I piani di tutte le voci con i parametri di partenza (il media solo se $media è il controllo fatto). */
function pulizia_voci(?array $media = null): array {
    pulizia_scadenza(true);
    $out = [];
    foreach (pulizia_ids() as $id) {
        [$p] = pulizia_parametri($id, null);
        $out[$id] = pulizia_piano($id, $p, $id === 'media' ? $media : null);
    }
    return $out;
}

/** Il totale recuperabile di un insieme di piani: senza Ottimizza (una stima del server) e senza le immagini. */
function pulizia_totale(array $piani): array {
    $b = 0; $finito = true;
    foreach ($piani as $id => $pi) {
        if (in_array($id, ['ottimizza', 'media'], true)) continue;
        $b += (int)$pi['byte'];
        if (!$pi['finito']) $finito = false;
    }
    return ['byte' => $b, 'finito' => $finito];
}

/** Ricorda la stima per la dashboard (impostazione pulizia_stima). */
function pulizia_stima_salva(int $byte): void {
    try { save_setting('pulizia_stima', json_encode(['quando' => time(), 'byte' => $byte])); } catch (Throwable $e) {}
}

/**
 * La stima del recuperabile per la dashboard: quella ricordata se ha meno di un giorno, altrimenti calcolata adesso con al più
 * 3 secondi di misure (e ricordata). Mai un errore: in caso di problemi 0.
 */
function pulizia_stima(): int {
    try {
        $s = json_decode((string)get_setting('pulizia_stima', ''), true);
        if (is_array($s) && (int)($s['quando'] ?? 0) > time() - 86400) return (int)($s['byte'] ?? 0);
        pulizia_scadenza(true, 3);
        $piani = [];
        foreach (pulizia_ids() as $id) { if (in_array($id, ['media', 'ottimizza'], true)) continue; [$p] = pulizia_parametri($id, null); $piani[$id] = pulizia_piano($id, $p); }
        $b = pulizia_totale($piani)['byte'];
        pulizia_stima_salva($b);
        return $b;
    } catch (Throwable $e) {
        error_log('pulizia, stima per la dashboard: ' . $e->getMessage());
        return 0;
    }
}

// ------------------------------------------------------------
// Esecuzione
// ------------------------------------------------------------

/**
 * Esegue le voci scelte: $scelte = [id => parametri già controllati]. Ogni piano si ricalcola qui. Ritorna
 * ['voci' => [id => ['titolo', 'byte', 'n', 'righe', 'messaggio', 'errori' => []]], 'byte' => totale liberato].
 * Ottimizza va sempre per ultima.
 */
function pulizia_esegui(array $scelte): array {
    @set_time_limit(600);
    @ignore_user_abort(true);
    pulizia_scadenza(true);
    $def = pulizia_definizioni();
    $ordine = array_values(array_intersect(pulizia_ids(), array_keys($scelte)));
    if (in_array('ottimizza', $ordine, true)) $ordine = array_merge(array_values(array_diff($ordine, ['ottimizza'])), ['ottimizza']);
    $media = in_array('media', $ordine, true) ? pulizia_media_controlla() : null;
    $res = ['voci' => [], 'byte' => 0];
    foreach ($ordine as $id) {
        $piano = pulizia_piano($id, $scelte[$id], $media);
        $r = ['titolo' => $def[$id]['titolo'], 'byte' => 0, 'n' => 0, 'righe' => 0, 'messaggio' => '', 'errori' => []];
        if ($piano['bloccata'] !== '') { $r['messaggio'] = 'Non fatto: ' . $piano['bloccata']; $res['voci'][$id] = $r; continue; }
        try {
            $f = 'pulizia_esegui_' . $id;
            $r = $f($piano, $scelte[$id], $r);
        } catch (Throwable $e) {
            error_log('pulizia, esecuzione ' . $id . ': ' . $e->getMessage());
            $r['errori'][] = 'Qualcosa non è andato: il dettaglio è nel log degli errori del sito.';
        }
        $res['voci'][$id] = $r;
        $res['byte'] += (int)$r['byte'];
    }
    try { save_setting('pulizia_stima', ''); } catch (Throwable $e) {} // la dashboard ricalcola
    return $res;
}

/** Gli elementi proposti di un piano. */
function pulizia_proposti(array $piano): array { return array_values(array_filter($piano['elementi'], fn($e) => $e['proposto'])); }

function pulizia_esegui_pacchetti(array $piano, array $p, array $r): array {
    $base = agg_dir('pacchetti');
    foreach (pulizia_proposti($piano) as $e) {
        $id = basename((string)$e['chiave']);
        $b = pulizia_togli_file($base . '/' . $id . '.zip', $base) + pulizia_togli_file($base . '/' . $id . '.json', $base);
        if (is_file($base . '/' . $id . '.json')) { $r['errori'][] = 'Il pacchetto ' . $id . ' non si è tolto (permessi del file).'; continue; }
        $r['byte'] += $b; $r['n']++;
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' pacchetto tolto' : ' pacchetti tolti') : 'Nessun pacchetto tolto.';
    return $r;
}

function pulizia_esegui_snapshot(array $piano, array $p, array $r): array {
    if ((int)$p['n'] === 0 && empty($p['zero'])) { $r['messaggio'] = 'Non fatto: per togliere anche l\'ultimo snapshot serve la conferma.'; return $r; }
    $base = agg_dir('snapshot');
    foreach (pulizia_proposti($piano) as $e) {
        $id = basename((string)$e['chiave']);
        if ($id === '' || $id === '.' || $id === '..') continue;
        $b = pulizia_togli_cartella($base . '/' . $id, $base);
        if (is_dir($base . '/' . $id)) { $r['errori'][] = 'Lo snapshot ' . $id . ' non si è tolto del tutto (permessi).'; }
        $r['byte'] += $b; $r['n']++;
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' snapshot tolto' : ' snapshot tolti') . ', restano gli ultimi ' . (int)$p['n'] : 'Nessuno snapshot tolto.';
    return $r;
}

function pulizia_esegui_copie(array $piano, array $p, array $r): array {
    $base = copia_dir();
    foreach (pulizia_proposti($piano) as $e) {
        $f = basename((string)$e['chiave']);
        if (!copia_nome_valido($f)) continue;
        $b = pulizia_togli_file($base . '/' . $f, $base);
        if ($b === 0 && is_file($base . '/' . $f)) { $r['errori'][] = 'La copia ' . $f . ' non si è tolta (permessi).'; continue; }
        $r['byte'] += $b; $r['n']++;
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' copia tolta' : ' copie tolte') : 'Nessuna copia tolta.';
    return $r;
}

function pulizia_esegui_scarto(array $piano, array $p, array $r): array {
    $base = copia_scarto_dir();
    foreach (pulizia_proposti($piano) as $e) {
        $n = basename((string)$e['chiave']);
        if (!copia_scarto_nome_valido($n)) continue;
        $r['byte'] += pulizia_togli_cartella($base . '/' . $n, $base); $r['n']++;
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' cartella di file messi da parte tolta' : ' cartelle di file messi da parte tolte') : 'Niente da togliere.';
    return $r;
}

function pulizia_esegui_cambiadb(array $piano, array $p, array $r): array {
    if (pulizia_cambio_in_corso()) { $r['messaggio'] = 'Non fatto: un cambio del database è in corso.'; return $r; }
    foreach (pulizia_cambiadb_file() as $f => $base) {
        // mai il config vero: il nome deve essere una copia (pulizia_cambiadb_file lo controlla già) e diverso da config.local.php
        if (basename($f) === 'config.local.php') continue;
        $b = pulizia_togli_file($f, dirname($base));
        if ($b || !is_file($f)) { $r['byte'] += $b; $r['n']++; }
        else $r['errori'][] = basename($f) . ' non si è tolto (permessi).';
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' file tolto' : ' file tolti') . ': non si può più tornare al database di prima dal pannello.' : 'Niente da togliere.';
    return $r;
}

function pulizia_esegui_log(array $piano, array $p, array $r): array {
    $dir = pulizia_log_dir();
    foreach (pulizia_proposti($piano) as $e) {
        $n = basename((string)$e['chiave']);
        if (in_array($n, PULIZIA_LOG_PROTETTI, true)) continue;
        $f = $dir . '/' . $n;
        if (!is_file($f) || !pulizia_dentro($f, $dir)) continue;
        if (in_array($n, PULIZIA_LOG_IN_USO, true)) {
            // si accorcia alle ultime righe e la data resta quella di prima (la dashboard la legge per dire se il cron gira)
            $prima = (int)filesize($f); $mt = (int)filemtime($f);
            $resta = pulizia_coda_peso($f, PULIZIA_LOG_RIGHE);
            $h = @fopen($f, 'rb'); if (!$h) { $r['errori'][] = $n . ' non si legge.'; continue; }
            fseek($h, max(0, $prima - $resta)); $coda = (string)stream_get_contents($h); fclose($h);
            if (@file_put_contents($f, $coda, LOCK_EX) === false) { $r['errori'][] = $n . ' non si scrive (permessi).'; continue; }
            @touch($f, $mt);
            clearstatcache(true, $f);
            $r['byte'] += max(0, $prima - (int)filesize($f)); $r['n']++;
        } else {
            $b = pulizia_togli_file($f, $dir);
            if ($b || !is_file($f)) { $r['byte'] += $b; $r['n']++; } else $r['errori'][] = $n . ' non si è tolto (permessi).';
        }
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' log tolto o accorciato' : ' log tolti o accorciati') : 'Niente da togliere.';
    return $r;
}

function pulizia_esegui_contatti(array $piano, array $p, array $r): array {
    $dirs = [];
    foreach (pulizia_contatti_dir() as $d) $dirs[basename($d)] = $d;
    foreach (pulizia_proposti($piano) as $e) {
        [$cart, $n] = array_pad(explode('/', (string)$e['chiave'], 2), 2, '');
        if (!isset($dirs[$cart]) || !preg_match('/^(imp|scarti|esp)-\d+-[a-f0-9]{12}\.(jsonl|csv)$/', $n, $m)) continue;
        $b = pulizia_togli_file($dirs[$cart] . '/' . $n, $dirs[$cart]);
        if (!$b && is_file($dirs[$cart] . '/' . $n)) { $r['errori'][] = 'Un file dei contatti non si è tolto (permessi).'; continue; }
        $r['byte'] += $b; $r['n']++;
        // le righe che lo nominano non promettono più un file da scaricare
        try {
            if ($m[1] === 'esp' && pulizia_tabella_c_e('newsletter_esportazioni')) db()->prepare('DELETE FROM ' . tbl('newsletter_esportazioni') . " WHERE file = ? AND stato <> 'in_corso'")->execute([$n]);
            if ($m[1] === 'scarti' && pulizia_tabella_c_e('newsletter_importazioni')) db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . " SET scarti_file = '' WHERE scarti_file = ? AND stato IN ('finita', 'errore')")->execute([$n]);
            if ($m[1] === 'imp' && pulizia_tabella_c_e('newsletter_importazioni')) db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . " SET file = '' WHERE file = ? AND stato IN ('finita', 'errore')")->execute([$n]);
        } catch (Throwable $ex) { error_log('pulizia, righe dei file dei contatti: ' . $ex->getMessage()); }
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' file dei contatti tolto' : ' file dei contatti tolti') : 'Niente da togliere.';
    return $r;
}

function pulizia_esegui_versioni(array $piano, array $p, array $r): array {
    $n = max(1, (int)$p['n']);
    $pdo = db();
    $via = [];
    $st = $pdo->query('SELECT id, ambito, oggetto_id, pubblicata, nota_mano FROM ' . tbl('editor_versioni') . ' ORDER BY ambito, oggetto_id, id DESC');
    $chiave = null; $pos = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $x) {
        $k = $x['ambito'] . '|' . $x['oggetto_id'];
        if ($k !== $chiave) { $chiave = $k; $pos = 0; }
        $pos++;
        if ($pos > $n && !(int)$x['pubblicata'] && !(int)$x['nota_mano']) $via[] = (int)$x['id'];
    }
    foreach (array_chunk($via, 500) as $pezzo) {
        $r['righe'] += (int)$pdo->exec('DELETE FROM ' . tbl('editor_versioni') . ' WHERE pubblicata = 0 AND nota_mano = 0 AND id IN (' . implode(',', array_map('intval', $pezzo)) . ')');
    }
    $r['n'] = $r['righe'];
    $r['byte'] = $piano['byte'];
    $r['messaggio'] = $r['righe'] ? pulizia_num($r['righe']) . ($r['righe'] === 1 ? ' versione tolta' : ' versioni tolte') . ', per ogni oggetto restano le ultime ' . $n : 'Nessuna versione tolta.';
    return $r;
}

function pulizia_esegui_cestino(array $piano, array $p, array $r): array {
    require_once __DIR__ . '/contenuti.php';
    $perTipo = [];
    foreach (pulizia_proposti($piano) as $e) {
        [$tipo, $id] = array_pad(explode(':', (string)$e['chiave'], 2), 2, '');
        if ((int)$id > 0) $perTipo[$tipo][] = (int)$id;
    }
    $tipi = pulizia_tipi_cestino();
    foreach ($perTipo as $tipo => $ids) {
        if (!isset($tipi[$tipo])) continue;
        $r['n'] += (int)contenuti_elimina($tipo, $ids);
    }
    $r['righe'] = $r['n'];
    $r['byte'] = $r['n'] && $piano['n'] ? (int)round($piano['byte'] * $r['n'] / $piano['n']) : 0;
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' contenuto eliminato per sempre dal cestino' : ' contenuti eliminati per sempre dal cestino') : 'Nessun contenuto tolto.';
    return $r;
}

function pulizia_esegui_registri(array $piano, array $p, array $r): array {
    $g = max(30, (int)$p['giorni']);
    foreach (pulizia_proposti($piano) as $e) {
        $tab = (string)$e['chiave'];
        if (!isset(pulizia_registri()[$tab]) || !pulizia_tabella_c_e($tab)) continue;
        $media = pulizia_riga_media($tab);
        $n = (int)db()->exec('DELETE FROM ' . tbl($tab) . ' WHERE ' . pulizia_registro_dove($tab, $g));
        $r['righe'] += $n; $r['byte'] += $n * $media;
    }
    $r['n'] = $r['righe'];
    $r['messaggio'] = $r['righe'] ? pulizia_num($r['righe']) . ($r['righe'] === 1 ? ' riga tolta dai registri' : ' righe tolte dai registri') : 'Nessuna riga tolta.';
    return $r;
}

function pulizia_esegui_lavoro(array $piano, array $p, array $r): array {
    if (!copia_blocco_prendi()) { $r['messaggio'] = 'Non fatto: un ripristino o un aggiornamento sta lavorando proprio adesso.'; return $r; }
    try {
        $ammesse = array_flip(pulizia_tabelle_lavoro());
        foreach (pulizia_proposti($piano) as $e) {
            $t = (string)$e['chiave'];
            if (!isset($ammesse[$t])) continue;
            db()->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t) . '`');
            $r['byte'] += (int)$e['byte']; $r['n']++; $r['righe'] += (int)$e['righe'];
        }
    } finally {
        copia_blocco_lascia();
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' tabella di lavoro tolta' : ' tabelle di lavoro tolte') : 'Niente da togliere.';
    return $r;
}

function pulizia_esegui_ottimizza(array $piano, array $p, array $r): array {
    $nomi = array_map(fn($e) => (string)$e['chiave'], pulizia_proposti($piano));
    $ammesse = array_flip(copia_tabelle_sito());
    $nomi = array_values(array_filter($nomi, fn($t) => isset($ammesse[$t])));
    if (!$nomi) { $r['messaggio'] = 'Nessuna tabella da ottimizzare.'; return $r; }
    $pesa = function (array $nomi): int { $t = 0; foreach (pulizia_pesi_tabelle($nomi) as $x) $t += $x['dati'] + $x['indici'] + $x['libero']; return $t; };
    $prima = $pesa($nomi);
    $fatte = 0;
    $fine = microtime(true) + 240;
    foreach ($nomi as $t) {
        if (microtime(true) > $fine) { $r['errori'][] = 'Mi sono fermato per non superare il tempo massimo: rilancia Ottimizza per le tabelle rimaste.'; break; }
        try { $q = db()->query('OPTIMIZE TABLE `' . str_replace('`', '``', $t) . '`'); if ($q) { $q->fetchAll(); $q->closeCursor(); } $fatte++; }
        catch (Throwable $e) { error_log('pulizia, OPTIMIZE ' . $t . ': ' . $e->getMessage()); }
    }
    $dopo = $pesa($nomi);
    $r['n'] = $fatte;
    $r['byte'] = max(0, $prima - $dopo);
    $r['prima'] = $prima; $r['dopo'] = $dopo;
    $r['messaggio'] = pulizia_num($fatte) . ($fatte === 1 ? ' tabella ottimizzata' : ' tabelle ottimizzate') . ': prima ' . pulizia_peso($prima) . ', dopo ' . pulizia_peso($dopo) . ' (dati, indici e spazio vuoto).';
    return $r;
}

function pulizia_esegui_media(array $piano, array $p, array $r): array {
    require_once __DIR__ . '/media.php';
    foreach (pulizia_proposti($piano) as $e) {
        $membri = (array)($e['membri'] ?? []);
        $tolti = 0;
        foreach ($membri as $url) {
            $webp = media_percorso_webp_pulizia($url);
            $peso = 0;
            $disco = pulizia_media_disco($url);
            if ($disco !== null && is_file($disco)) $peso += (int)@filesize($disco);
            if ($webp !== null && is_file($webp)) $peso += (int)@filesize($webp);
            if (media_elimina($url)) { $tolti++; $r['byte'] += $peso; }
        }
        if ($tolti) $r['n']++;
        elseif ($membri) $r['errori'][] = 'L\'immagine ' . $e['nome'] . ' non si è tolta (permessi).';
    }
    $r['messaggio'] = $r['n'] ? pulizia_num($r['n']) . ($r['n'] === 1 ? ' immagine tolta' : ' immagini tolte') . ' (con le loro miniature)' : 'Nessuna immagine tolta.';
    return $r;
}

/** Il percorso sul disco di un'immagine della libreria, dal suo indirizzo, o null. */
function pulizia_media_disco(string $url): ?string {
    if (str_contains($url, '..')) return null;
    $up = rtrim(cms_uploads_url(), '/');
    if (str_starts_with($url, $up . '/')) return cms_uploads_dir() . substr($url, strlen($up));
    $pan = rtrim(cms_base(), '/') . '/assets/img';
    if (str_starts_with($url, $pan . '/')) return cms_radice() . '/assets/img' . substr($url, strlen($pan));
    return null;
}
