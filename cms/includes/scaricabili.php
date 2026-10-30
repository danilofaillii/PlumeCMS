<?php
/**
 * Download manager (1.2.4, 27 ottobre 2026): i file che il sito offre da scaricare.
 *
 * I file stanno in uploads/scaricabili/ e si aprono da /scarica/<nome>/ (sito/index.php): <nome> e il nome del file
 * senza estensione, quindi due file con lo stesso nome e estensioni diverse non possono convivere. Il disco e la verita:
 * un file messo li con il gestore dei file o con un pacchetto compare da solo. La tabella {prefisso}scaricabili tiene
 * solo quello che il disco non sa: titolo, descrizione, versione, quante volte è stato scaricato e quando l'ultima.
 *
 * Pannello: admin/scaricabili.php (voce Download). Widget: includes/editor-widget-download.php.
 *
 *   scaricabili_elenco()                       tutti i file, con i dati della tabella
 *   scaricabili_leggi($nome)                   un file, o null
 *   scaricabili_carica($file, $info, $sostituisci)   da $_FILES: ['nome' => ...] oppure ['errore' => ...]
 *   scaricabili_salva_info($nome, $info)       titolo, descrizione, versione
 *   scaricabili_elimina($nome)                 file e riga
 *   scaricabili_conta($nome)                   un download in più (non per i robot)
 *   scaricabili_serie()                        le serie: per ognuna l'ultima versione (1.2.5)
 *   scaricabili_versioni_nel_testo($html)      {versione:<serie>} diventa il numero dell'ultima versione (1.2.5)
 *   scaricabili_sha256($file)                  l'impronta SHA-256 di un file, ricordata finché il file non cambia (1.3.0, api/versione.php)
 *   scaricabili_rendi_riservato($nome, $si)    sposta un file fra i pubblici e i riservati (1.11.0)
 *   scaricabili_link_personale($nome, $id)     il link firmato e a scadenza per un file riservato (1.11.0)
 *   scaricabili_verifica_chiave($nome, $k)     l'iscritto di un link personale valido, 0 se scaduto, null se falso (1.11.0)
 *
 * File riservati (1.11.0): stanno in uploads/riservati-<16 caratteri a caso>/, una cartella con un .htaccess che
 * chiude tutto e un nome che non si indovina (vale anche su Nginx, dove il .htaccess non conta). /scarica/<nome>/ li
 * manda solo con ?k=<chiave>: iscritto, scadenza e firma HMAC con il segreto del sito. Non fanno parte delle serie e il
 * widget Download non li mostra. Chi li ha scaricati sta nella tabella {prefisso}scaricabili_consegne.
 *
 * Serie (1.2.5): un file con la versione nel nome (plumecms-1.2.4.zip, plumecms-1.2.4-aggiornamento.zip) fa parte di
 * una serie, il cui nome e quello del file con "ultima" al posto della versione (plumecms-ultima,
 * plumecms-ultima-aggiornamento). /scarica/<serie>/ manda sempre il file con la versione più alta: per pubblicare una
 * versione nuova basta caricare il file, e i link, i pulsanti e i testi con {versione:<serie>} la seguono da soli.
 * Un numero solo (listino-2026) non e una versione: servono almeno due numeri separati da un punto.
 */

require_once __DIR__ . '/db.php';

/** Le estensioni ammesse, con il tipo che si manda al browser, il nome breve e l'icona (includes/icone.php). */
const SCARICABILI_TIPI = [
    'pdf'  => ['application/pdf', 'PDF', 'pdf'],
    'zip'  => ['application/zip', 'ZIP', 'archivio'],
    'epub' => ['application/epub+zip', 'ePub', 'libro'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Word', 'documento-testo'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Excel', 'tabella'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'PowerPoint', 'documenti'],
    'odt'  => ['application/vnd.oasis.opendocument.text', 'ODT', 'documento-testo'],
    'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'ODS', 'tabella'],
    'mp3'  => ['audio/mpeg', 'MP3', 'musica'],
    'txt'  => ['text/plain; charset=utf-8', 'Testo', 'documento'],
    'csv'  => ['text/csv; charset=utf-8', 'CSV', 'tabella'],
];

/** Chi scarica per mestiere e non e una persona: non si conta. */
const SCARICABILI_ROBOT = '~bot|crawl|spider|slurp|fetch|preview|headless|curl|wget|python|httpclient|monitor|scan~i';

function scaricabili_dir(): string {
    $d = cms_uploads_dir() . '/scaricabili';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

/** La cartella dei file riservati (1.11.0): nome a caso, ricordato nelle impostazioni; chiusa al web con .htaccess. */
function scaricabili_dir_riservati(): string {
    $nome = (string)get_setting('scaricabili_riservati', '');
    if (!preg_match('/^riservati-[a-f0-9]{16}$/', $nome)) {
        $nome = 'riservati-' . bin2hex(random_bytes(8));
        save_setting('scaricabili_riservati', $nome);
    }
    $d = cms_uploads_dir() . '/' . $nome;
    if (!is_dir($d)) @mkdir($d, 0755, true);
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "# File riservati di plumeCMS: si scaricano solo dal sito, con un link personale.\nRequire all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\nOptions -Indexes\n");
    if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
    return $d;
}

function scaricabili_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    // 1.11.0: chi ha ricevuto e scaricato un file riservato (una riga per file e iscritto).
    $consegne = 'CREATE TABLE IF NOT EXISTS ' . tbl('scaricabili_consegne') . " (
        `file` VARCHAR(190) NOT NULL,
        `iscritto_id` INT(11) NOT NULL,
        `creato` DATETIME NOT NULL,
        `scaricati` INT UNSIGNED NOT NULL DEFAULT 0,
        `ultimo` DATETIME DEFAULT NULL,
        PRIMARY KEY (`file`, `iscritto_id`), KEY `iscritto` (`iscritto_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $sql = 'CREATE TABLE IF NOT EXISTS ' . tbl('scaricabili') . " (
        `file` VARCHAR(190) NOT NULL,
        `titolo` VARCHAR(200) NOT NULL DEFAULT '',
        `descrizione` TEXT,
        `versione` VARCHAR(40) NOT NULL DEFAULT '',
        `conteggio` INT UNSIGNED NOT NULL DEFAULT 0,
        `ultimo` DATETIME DEFAULT NULL,
        `aggiornato` DATETIME DEFAULT NULL,
        PRIMARY KEY (`file`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $impronta = md5($sql . $consegne);
    if (get_setting('scaricabili_schema') === $impronta) return;
    db()->exec($sql);
    db()->exec($consegne);
    save_setting('scaricabili_schema', $impronta);
}

/** Il nome di un file per l'indirizzo e per il disco: minuscole, numeri, punto, trattino, trattino basso. */
function scaricabili_nome_pulito(string $nome): string {
    $nome = strtolower(trim($nome));
    $nome = strtr($nome, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', ' ' => '-']);
    $nome = preg_replace('/[^a-z0-9._-]+/', '-', $nome);
    $nome = preg_replace('/-{2,}/', '-', trim($nome, '-.'));
    return substr($nome, 0, 120);
}

/** Un titolo leggibile dal nome del file, per chi non ne ha scritto uno: "manuale-tecnico" diventa "Manuale tecnico". */
function scaricabili_titolo_da_nome(string $nome): string {
    $t = trim(str_replace(['-', '_'], ' ', $nome));
    return $t === '' ? $nome : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
}

function scaricabili_righe(): array {
    try {
        scaricabili_installa();
        $out = [];
        foreach (db()->query('SELECT * FROM ' . tbl('scaricabili'))->fetchAll() as $r) $out[$r['file']] = $r;
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/** Tutti i file da scaricare, dal più recente: nome (senza estensione), file, ext, tipo, icona, peso, data, titolo, descrizione, versione, conteggio, ultimo, url. */
function scaricabili_elenco(): array {
    $righe = scaricabili_righe();
    $out = [];
    // 1.11.0: prima i pubblici, poi i riservati (un nome non può stare in tutte e due: lo impediscono carica e sposta).
    foreach ([[scaricabili_dir(), false], [scaricabili_dir_riservati(), true]] as [$dir, $riservato])
    foreach ((array)@scandir($dir) as $f) {
        if ($f === '' || $f[0] === '.' || !is_file("$dir/$f")) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!isset(SCARICABILI_TIPI[$ext])) continue;
        $nome = substr($f, 0, -strlen($ext) - 1);
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,120}$/', $nome) || isset($out[$nome])) continue;
        $r = $righe[$f] ?? [];
        [$vNome, $serie] = $riservato ? ['', ''] : scaricabili_versione_da_nome($nome);
        $out[$nome] = [
            'nome' => $nome, 'file' => $f, 'ext' => $ext, 'tipo' => SCARICABILI_TIPI[$ext][1], 'icona' => SCARICABILI_TIPI[$ext][2],
            'peso' => (int)filesize("$dir/$f"), 'data' => (int)filemtime("$dir/$f"),
            'titolo' => trim((string)($r['titolo'] ?? '')) ?: scaricabili_titolo_da_nome($nome),
            'titolo_scritto' => trim((string)($r['titolo'] ?? '')),
            'descrizione' => (string)($r['descrizione'] ?? ''), 'versione' => trim((string)($r['versione'] ?? '')) ?: $vNome,
            'versione_nome' => $vNome, 'serie' => $serie,
            'conteggio' => (int)($r['conteggio'] ?? 0), 'ultimo' => (string)($r['ultimo'] ?? ''),
            'url' => $riservato ? '' : '/scarica/' . $nome . '/',
            'riservato' => $riservato, 'percorso' => "$dir/$f",
        ];
    }
    uasort($out, fn($a, $b) => $b['data'] <=> $a['data'] ?: strcmp($a['nome'], $b['nome']));
    return $out;
}

/**
 * Un file per nome; se il nome e quello di una serie, l'ultima versione della serie (con l'indirizzo della serie).
 * I riservati solo con $riservati (pannello, link personale): per il sito e il widget non esistono.
 */
function scaricabili_leggi(string $nome, bool $riservati = false): ?array {
    $tutti = scaricabili_elenco();
    if (isset($tutti[$nome])) return !$tutti[$nome]['riservato'] || $riservati ? $tutti[$nome] : null;
    $x = scaricabili_serie($tutti)[$nome] ?? null;
    if ($x) $x['url'] = '/scarica/' . $nome . '/';
    return $x;
}

/** La versione scritta nel nome del file e il nome della serie: ['1.2.4', 'plumecms-ultima'], oppure ['', '']. */
function scaricabili_versione_da_nome(string $nome): array {
    if (!preg_match('/(?<![a-z0-9])v?(\d+(?:\.\d+){1,3})(?![a-z0-9])/', $nome, $m, PREG_OFFSET_CAPTURE)) return ['', ''];
    return [$m[1][0], substr_replace($nome, 'ultima', $m[0][1], strlen($m[0][0]))];
}

/** Le serie: nome della serie => il file con la versione più alta (a pari versione, il più recente). */
function scaricabili_serie(?array $tutti = null): array {
    $out = [];
    foreach ($tutti ?? scaricabili_elenco() as $x) {
        if ($x['serie'] === '' || !empty($x['riservato'])) continue;
        $c = $out[$x['serie']] ?? null;
        if (!$c || version_compare($x['versione_nome'], $c['versione_nome'], '>') || ($x['versione_nome'] === $c['versione_nome'] && $x['data'] > $c['data'])) $out[$x['serie']] = $x;
    }
    return $out;
}

/** Nei testi del sito {versione:plumecms-ultima} diventa la versione dell'ultimo file della serie (vuoto se la serie non c'è). */
function scaricabili_versioni_nel_testo(string $html): string {
    if (!str_contains($html, '{versione:')) return $html;
    try { $serie = scaricabili_serie(); } catch (Throwable $e) { $serie = []; }
    return preg_replace_callback('/\{versione:([a-z0-9][a-z0-9._-]{0,120})\}/', fn($m) => isset($serie[$m[1]]) ? htmlspecialchars($serie[$m[1]]['versione_nome']) : '', $html);
}

/** Il peso in parole: 900 KB, 1,2 MB. */
function scaricabili_peso(int $byte): string {
    if ($byte < 1024 * 1024) return max(1, (int)round($byte / 1024)) . ' KB';
    return str_replace('.', ',', (string)round($byte / 1048576, 1)) . ' MB';
}

/** Il file caricato e davvero di quel tipo? Si guarda l'inizio del file, non il nome. */
function scaricabili_contenuto_ok(string $percorso, string $ext): bool {
    $testa = (string)@file_get_contents($percorso, false, null, 0, 8);
    switch ($ext) {
        case 'pdf': return str_starts_with($testa, '%PDF');
        case 'zip': case 'epub': case 'docx': case 'xlsx': case 'pptx': case 'odt': case 'ods': return str_starts_with($testa, "PK\x03\x04") || str_starts_with($testa, "PK\x05\x06");
        case 'mp3': return str_starts_with($testa, 'ID3') || (strlen($testa) > 1 && ord($testa[0]) === 0xFF && (ord($testa[1]) & 0xE0) === 0xE0);
        case 'txt': case 'csv':
            $pezzo = (string)@file_get_contents($percorso, false, null, 0, 65536);
            return !str_contains($pezzo, "\0") && !preg_match('/<\?php|<script/i', $pezzo);
    }
    return false;
}

/**
 * Un file caricato dal pannello ($_FILES['file']). $info: titolo, descrizione, versione. Con $sostituisci un file con
 * lo stesso nome si rimpiazza (il conteggio resta): e così che si pubblica una versione nuova allo stesso indirizzo.
 */
function scaricabili_carica($f, array $info = [], bool $sostituisci = false, bool $riservato = false): array {
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['errore' => 'Scegli un file.'];
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return ['errore' => 'Il file supera il limite di caricamento del server (' . ini_get('upload_max_filesize') . ').'];
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) return ['errore' => 'Caricamento non riuscito: riprova.'];
    $originale = basename((string)$f['name']);
    $ext = strtolower(pathinfo($originale, PATHINFO_EXTENSION));
    if (!isset(SCARICABILI_TIPI[$ext])) return ['errore' => 'Tipo di file non ammesso. Si possono offrire: ' . implode(', ', array_keys(SCARICABILI_TIPI)) . '.'];
    $nome = scaricabili_nome_pulito(substr($originale, 0, -strlen($ext) - 1));
    if ($nome === '' || !preg_match('/^[a-z0-9]/', $nome)) $nome = 'file-' . date('Ymd-His');
    if (!scaricabili_contenuto_ok((string)$f['tmp_name'], $ext)) return ['errore' => "Il contenuto non è quello di un file .$ext: il file non è stato caricato."];
    $dir = $riservato ? scaricabili_dir_riservati() : scaricabili_dir();
    $altroDir = $riservato ? scaricabili_dir() : scaricabili_dir_riservati();
    $dest = "$dir/$nome.$ext";
    foreach (array_keys(SCARICABILI_TIPI) as $altra) {
        if ($altra !== $ext && is_file("$dir/$nome.$altra")) return ['errore' => "C'è già $nome.$altra: due file con lo stesso nome avrebbero lo stesso indirizzo. Rinomina il file e riprova."];
        if (is_file("$altroDir/$nome.$altra")) return ['errore' => "C'è già $nome.$altra fra i file " . ($riservato ? 'pubblici' : 'riservati') . ': usa un altro nome, oppure sposta quello che c\'è.'];
    }
    if (is_file($dest) && !$sostituisci) return ['errore' => "C'è già un file $nome.$ext. Per mettere la versione nuova spunta \"Sostituisci\"."];
    if (!move_uploaded_file((string)$f['tmp_name'], $dest)) return ['errore' => 'Non si è potuto scrivere il file nella cartella ' . ($riservato ? 'dei file riservati.' : 'uploads/scaricabili.')];
    @chmod($dest, 0644);
    // Nel caricamento contano solo i campi scritti: una versione nuova caricata con il modulo vuoto tiene titolo e descrizione.
    scaricabili_salva_info($nome, array_filter($info, fn($x) => trim((string)$x) !== '') + ['_file' => "$nome.$ext"]);
    if (function_exists('sicurezza_log')) sicurezza_log('impostazioni', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'download: caricato ' . "$nome.$ext");
    return ['nome' => $nome, 'file' => "$nome.$ext"];
}

/** Titolo, descrizione e versione di un file. Le chiavi che mancano in $info restano come sono. */
function scaricabili_salva_info(string $nome, array $info): bool {
    $s = $info['_file'] ?? null;
    if ($s === null) { $x = scaricabili_leggi($nome, true); if (!$x) return false; $s = $x['file']; }
    scaricabili_installa();
    $pulisci = fn($v, $max) => mb_substr(trim(str_replace(["\r", "\0"], '', (string)$v)), 0, $max);
    $campi = [];
    foreach (['titolo' => 200, 'descrizione' => 2000, 'versione' => 40] as $k => $max) if (array_key_exists($k, $info)) $campi[$k] = scaricabili_testo($pulisci($info[$k], $max));
    db()->prepare('INSERT INTO ' . tbl('scaricabili') . ' (file, aggiornato) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE aggiornato = NOW()')->execute([$s]);
    foreach ($campi as $k => $v) db()->prepare('UPDATE ' . tbl('scaricabili') . " SET `$k` = ? WHERE file = ?")->execute([$v, $s]);
    return true;
}

/** Le colonne della tabella sono utf8mb4, ma i caratteri a 4 byte si salvano lo stesso come entita: il resto del CMS fa così. */
function scaricabili_testo(string $s): string {
    return function_exists('sito_senza_4byte') ? sito_senza_4byte($s) : preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
}

function scaricabili_elimina(string $nome): bool {
    $x = scaricabili_elenco()[$nome] ?? null; // per nome esatto, pubblico o riservato (mai una serie)
    if (!$x) return false;
    $ok = @unlink($x['percorso']);
    try { db()->prepare('DELETE FROM ' . tbl('scaricabili') . ' WHERE file = ?')->execute([$x['file']]); } catch (Throwable $e) {}
    if ($ok && function_exists('sicurezza_log')) sicurezza_log('impostazioni', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'download: eliminato ' . $x['file']);
    return $ok;
}

/** Un download in più: solo richieste GET intere di una persona (non i robot, non i pezzi di un download ripreso). */
function scaricabili_conta(string $file): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || preg_match(SCARICABILI_ROBOT, $ua)) return;
    if (!empty($_SERVER['HTTP_RANGE']) && !preg_match('/^bytes=0-/', (string)$_SERVER['HTTP_RANGE'])) return;
    try {
        scaricabili_installa();
        db()->prepare('INSERT INTO ' . tbl('scaricabili') . ' (file, conteggio, ultimo) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE conteggio = conteggio + 1, ultimo = NOW()')->execute([basename($file)]);
    } catch (Throwable $e) {
        error_log('scaricabili: conteggio non salvato: ' . $e->getMessage());
    }
}

/**
 * L'impronta SHA-256 di un file di uploads/scaricabili/ (1.3.0): la chiede api/versione.php, così chi scarica un
 * aggiornamento controlla che il file sia arrivato intero. Si calcola una volta e si ricorda nell'impostazione
 * scaricabili_impronte, con data e peso del file: se il file cambia, si ricalcola.
 */
function scaricabili_sha256(string $file): string {
    $p = scaricabili_dir() . '/' . basename($file);
    if (!is_file($p)) return '';
    $chiave = basename($file) . '|' . filemtime($p) . '|' . filesize($p);
    $mappa = json_decode((string)get_setting('scaricabili_impronte', '{}'), true);
    if (!is_array($mappa)) $mappa = [];
    if (isset($mappa[$chiave]) && preg_match('/^[a-f0-9]{64}$/', (string)$mappa[$chiave])) return $mappa[$chiave];
    $h = (string)hash_file('sha256', $p);
    // Solo le impronte dei file che ci sono ancora, più quella nuova.
    $vivi = [];
    foreach ($mappa as $k => $v) if (is_file(scaricabili_dir() . '/' . explode('|', (string)$k)[0])) $vivi[$k] = $v;
    $vivi[$chiave] = $h;
    save_setting('scaricabili_impronte', json_encode(array_slice($vivi, -200, null, true)));
    return $h;
}

// ------------------------------------------------------------
// File riservati e link personali (1.11.0)
// ------------------------------------------------------------

/** Sposta un file fra i pubblici e i riservati. Il conteggio e i dati restano (stessa riga della tabella). */
function scaricabili_rendi_riservato(string $nome, bool $riservato): bool {
    $x = scaricabili_elenco()[$nome] ?? null;
    if (!$x || $x['riservato'] === $riservato) return (bool)$x;
    $dest = ($riservato ? scaricabili_dir_riservati() : scaricabili_dir()) . '/' . $x['file'];
    if (is_file($dest) || !@rename($x['percorso'], $dest)) return false;
    if (function_exists('sicurezza_log')) sicurezza_log('impostazioni', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'download: ' . $x['file'] . ($riservato ? ' reso riservato' : ' reso pubblico'));
    return true;
}

/** Quanti giorni vale un link personale (impostazione scaricabili_giorni, di partenza 30). */
function scaricabili_giorni(): int {
    $g = (int)get_setting('scaricabili_giorni', '30');
    return $g >= 1 && $g <= 3650 ? $g : 30;
}

function scaricabili_firma(string $nome, int $id, int $scade): string {
    require_once __DIR__ . '/moduli.php';
    return substr(hash_hmac('sha256', 'scarica|' . $nome . '|' . $id . '|' . $scade, moduli_segreto()), 0, 32);
}

/**
 * Il link personale di un file riservato per un iscritto: /scarica/<nome>/?k=<id>.<scadenza>.<firma>, assoluto (per le
 * email). Registra la consegna. Stringa vuota se il file non c'è o non è riservato.
 */
function scaricabili_link_personale(string $nome, int $iscritto, ?int $giorni = null): string {
    $x = scaricabili_elenco()[$nome] ?? null;
    if (!$x || !$x['riservato'] || $iscritto <= 0) return '';
    $scade = time() + 86400 * ($giorni ?? scaricabili_giorni());
    try {
        scaricabili_installa();
        db()->prepare('INSERT IGNORE INTO ' . tbl('scaricabili_consegne') . ' (file, iscritto_id, creato) VALUES (?, ?, NOW())')->execute([$x['file'], $iscritto]);
    } catch (Throwable $e) { error_log('scaricabili: consegna non registrata: ' . $e->getMessage()); }
    return rtrim(cms_url_sito(), '/') . '/scarica/' . $nome . '/?k=' . $iscritto . '.' . $scade . '.' . scaricabili_firma($nome, $iscritto, $scade);
}

/** La chiave di un link personale: l'id dell'iscritto se valida, 0 se vera ma scaduta, null se falsa o malformata. */
function scaricabili_verifica_chiave(string $nome, string $k): ?int {
    if (!preg_match('/^(\d{1,10})\.(\d{9,11})\.([a-f0-9]{32})$/', $k, $m)) return null;
    if (!hash_equals(scaricabili_firma($nome, (int)$m[1], (int)$m[2]), $m[3])) return null;
    return (int)$m[2] >= time() ? (int)$m[1] : 0;
}

/** Un download riservato fatto: per iscritto (non per i robot, come il conteggio di tutti). */
function scaricabili_conta_consegna(string $file, int $iscritto): void {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || preg_match(SCARICABILI_ROBOT, $ua) || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    try {
        scaricabili_installa();
        db()->prepare('INSERT INTO ' . tbl('scaricabili_consegne') . ' (file, iscritto_id, creato, scaricati, ultimo) VALUES (?, ?, NOW(), 1, NOW())
            ON DUPLICATE KEY UPDATE scaricati = scaricati + 1, ultimo = NOW()')->execute([basename($file), $iscritto]);
    } catch (Throwable $e) { error_log('scaricabili: consegna non contata: ' . $e->getMessage()); }
}

/** Chi ha ricevuto un file riservato: email, quando, quante volte l'ha scaricato. Per il pannello. */
function scaricabili_consegne(string $file, int $limite = 200): array {
    try {
        scaricabili_installa();
        $st = db()->prepare('SELECT c.*, i.email, i.nome FROM ' . tbl('scaricabili_consegne') . ' c LEFT JOIN ' . tbl('newsletter_iscritti') . ' i ON i.id = c.iscritto_id
            WHERE c.file = ? ORDER BY c.creato DESC LIMIT ' . max(1, min(2000, $limite)));
        $st->execute([$file]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}
