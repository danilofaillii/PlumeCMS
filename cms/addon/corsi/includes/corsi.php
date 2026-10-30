<?php
/**
 * Corsi online (addon corsi, 2.0.0): il motore. Richiede l'Area riservata (addon membri, includes/membri.php).
 *
 * Tabelle (prefisso del sito):
 *   corsi               id, slug, titolo, descrizione, immagine, accesso (tutti | assegnati), stato (bozza | pubblicato), ordine
 *   corsi_moduli        id, corso_id, titolo, ordine
 *   corsi_lezioni       id, corso_id, modulo_id, slug, titolo, testo (HTML pulito), video_url, video_file, materiali (JSON), durata, ordine
 *   corsi_iscritti      corso_id, membro_id, assegnato (dall'amministratore), iniziato, ultimo, ultima_lezione, completato
 *   corsi_progressi     membro_id, lezione_id, completata
 *   corsi_certificati   codice (pubblico), corso_id, membro_id, nome, titolo del corso, data
 *
 * Video caricati: in uploads/corsi-<16 caratteri a caso>/ (nome che non si indovina, .htaccess che chiude tutto): si vedono
 * solo da /corsi/<corso>/video/<lezione>/, con il controllo dell'accesso. Materiali: i file riservati di Download
 * (includes/scaricabili.php), mandati da /corsi/<corso>/materiale/<lezione>/<file>/ solo a chi può vedere la lezione.
 */

require_once dirname(__DIR__, 2) . '/membri/includes/membri.php';
require_once dirname(__DIR__, 3) . '/includes/scaricabili.php';

const CORSI_ACCESSI = ['tutti' => 'Tutti i membri', 'assegnati' => 'Solo i membri a cui lo assegno'];
const CORSI_STATI = ['bozza' => 'Bozza', 'pubblicato' => 'Pubblicato'];
const CORSI_VIDEO_TIPI = ['mp4' => 'video/mp4', 'webm' => 'video/webm'];
const CORSI_SLUG_RISERVATI = ['certificato', 'video', 'materiale'];
const CORSI_ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // per i codici dei certificati: niente 0, O, 1, I

function corsi_installa(): string {
    $t = [
        'CREATE TABLE IF NOT EXISTS ' . tbl('corsi') . " (
            `id` INT NOT NULL AUTO_INCREMENT, `slug` VARCHAR(120) NOT NULL, `titolo` VARCHAR(200) NOT NULL, `descrizione` TEXT,
            `immagine` VARCHAR(500) NOT NULL DEFAULT '', `accesso` VARCHAR(12) NOT NULL DEFAULT 'tutti', `stato` VARCHAR(12) NOT NULL DEFAULT 'bozza',
            `ordine` INT NOT NULL DEFAULT 0, `creato` DATETIME NOT NULL, `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('corsi_moduli') . " (
            `id` INT NOT NULL AUTO_INCREMENT, `corso_id` INT NOT NULL, `titolo` VARCHAR(200) NOT NULL, `ordine` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), KEY `corso` (`corso_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('corsi_lezioni') . " (
            `id` INT NOT NULL AUTO_INCREMENT, `corso_id` INT NOT NULL, `modulo_id` INT NOT NULL, `slug` VARCHAR(120) NOT NULL, `titolo` VARCHAR(200) NOT NULL,
            `testo` MEDIUMTEXT, `video_url` VARCHAR(500) NOT NULL DEFAULT '', `video_file` VARCHAR(120) NOT NULL DEFAULT '', `materiali` TEXT,
            `durata` VARCHAR(40) NOT NULL DEFAULT '', `ordine` INT NOT NULL DEFAULT 0, `creato` DATETIME NOT NULL, `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `corso_slug` (`corso_id`, `slug`), KEY `modulo` (`modulo_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('corsi_iscritti') . " (
            `corso_id` INT NOT NULL, `membro_id` INT NOT NULL, `assegnato` TINYINT NOT NULL DEFAULT 0, `iniziato` DATETIME DEFAULT NULL,
            `ultimo` DATETIME DEFAULT NULL, `ultima_lezione` INT NOT NULL DEFAULT 0, `completato` DATETIME DEFAULT NULL,
            PRIMARY KEY (`corso_id`, `membro_id`), KEY `membro` (`membro_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('corsi_progressi') . " (
            `membro_id` INT NOT NULL, `lezione_id` INT NOT NULL, `completata` DATETIME NOT NULL,
            PRIMARY KEY (`membro_id`, `lezione_id`), KEY `lezione` (`lezione_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('corsi_certificati') . " (
            `codice` CHAR(14) NOT NULL, `corso_id` INT NOT NULL, `membro_id` INT NOT NULL, `nome` VARCHAR(120) NOT NULL, `corso_titolo` VARCHAR(200) NOT NULL,
            `data` DATETIME NOT NULL,
            PRIMARY KEY (`codice`), UNIQUE KEY `corso_membro` (`corso_id`, `membro_id`), KEY `membro` (`membro_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($t as $sql) db()->exec($sql);
    save_setting('corsi_schema', md5(implode('', $t)));
    return 'tabelle dei corsi a posto';
}

function corsi_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    membri_schema();
    if ((string)get_setting('corsi_schema', '') === '') corsi_installa();
}

function corsi_admin_url(string $p, array $q = []): string {
    return 'addon-pagina.php?' . http_build_query(['a' => 'corsi', 'p' => $p] + $q);
}

/** Uno slug da un titolo: minuscole, numeri e trattini. */
function corsi_slug(string $t): string {
    $t = mb_strtolower(trim($t));
    $t = strtr($t, ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', "'" => '-', '’' => '-']);
    $t = (string)preg_replace('/[^a-z0-9]+/', '-', $t);
    return substr(trim($t, '-'), 0, 100);
}

// ------------------------------------------------------------
// Corsi
// ------------------------------------------------------------

function corsi_tutti(bool $soloPubblicati = false): array {
    corsi_schema();
    return db()->query('SELECT * FROM ' . tbl('corsi') . ($soloPubblicati ? " WHERE stato = 'pubblicato'" : '') . ' ORDER BY ordine, id')->fetchAll();
}

function corsi_leggi(int $id): ?array {
    corsi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('corsi') . ' WHERE id = ?'); $st->execute([$id]);
    return $st->fetch() ?: null;
}

function corsi_per_slug(string $slug): ?array {
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,119}$/', $slug)) return null;
    corsi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('corsi') . ' WHERE slug = ?'); $st->execute([$slug]);
    return $st->fetch() ?: null;
}

/** Crea un corso dal titolo. Ritorna l'id. */
function corsi_crea(string $titolo): int {
    corsi_schema();
    $titolo = membri_testo($titolo, 200);
    if ($titolo === '') throw new RuntimeException('Scrivi il titolo del corso.');
    $slug = corsi_slug_libero('corsi', corsi_slug($titolo) ?: 'corso');
    db()->prepare('INSERT INTO ' . tbl('corsi') . ' (slug, titolo, descrizione, creato, aggiornato, ordine) VALUES (?, ?, \'\', NOW(), NOW(), ?)')
        ->execute([$slug, $titolo, (int)db()->query('SELECT COALESCE(MAX(ordine), 0) + 1 FROM ' . tbl('corsi'))->fetchColumn()]);
    $id = (int)db()->lastInsertId();
    db()->prepare('INSERT INTO ' . tbl('corsi_moduli') . ' (corso_id, titolo, ordine) VALUES (?, ?, 1)')->execute([$id, 'Modulo 1']);
    return $id;
}

/** Uno slug che non c'è ancora (nella tabella dei corsi, o fra le lezioni del corso $corso). */
function corsi_slug_libero(string $tabella, string $slug, int $corso = 0, int $tranne = 0): string {
    $base = $slug; $n = 1;
    while (true) {
        if ($tabella === 'corsi') { $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('corsi') . ' WHERE slug = ? AND id <> ?'); $st->execute([$slug, $tranne]); }
        else { $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('corsi_lezioni') . ' WHERE corso_id = ? AND slug = ? AND id <> ?'); $st->execute([$corso, $slug, $tranne]); }
        if (!(int)$st->fetchColumn() && !in_array($slug, CORSI_SLUG_RISERVATI, true)) return $slug;
        $slug = substr($base, 0, 95) . '-' . (++$n);
    }
}

function corsi_salva(int $id, array $d): void {
    $c = corsi_leggi($id);
    if (!$c) throw new RuntimeException('Corso non trovato.');
    $titolo = membri_testo((string)($d['titolo'] ?? $c['titolo']), 200);
    if ($titolo === '') throw new RuntimeException('Il titolo non può essere vuoto.');
    $slug = corsi_slug((string)($d['slug'] ?? $c['slug'])) ?: corsi_slug($titolo);
    $slug = corsi_slug_libero('corsi', $slug, 0, $id);
    $img = trim((string)($d['immagine'] ?? $c['immagine']));
    if ($img !== '' && sicurezza_url_pulito($img, true) === '') throw new RuntimeException('L\'indirizzo dell\'immagine non va bene: usa un\'immagine di Media (comincia con /uploads/) o un indirizzo https.');
    $accesso = isset(CORSI_ACCESSI[$d['accesso'] ?? '']) ? $d['accesso'] : $c['accesso'];
    $stato = isset(CORSI_STATI[$d['stato'] ?? '']) ? $d['stato'] : $c['stato'];
    db()->prepare('UPDATE ' . tbl('corsi') . ' SET titolo = ?, slug = ?, descrizione = ?, immagine = ?, accesso = ?, stato = ?, aggiornato = NOW() WHERE id = ?')
        ->execute([$titolo, $slug, membri_testo((string)($d['descrizione'] ?? $c['descrizione']), 3000), mb_substr($img, 0, 500), $accesso, $stato, $id]);
}

function corsi_elimina(int $id): void {
    corsi_schema();
    foreach (corsi_lezioni($id) as $l) corsi_lezione_elimina((int)$l['id']);
    foreach (['corsi_moduli', 'corsi_iscritti', 'corsi_certificati'] as $t) db()->prepare('DELETE FROM ' . tbl($t) . ' WHERE corso_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('corsi') . ' WHERE id = ?')->execute([$id]);
}

// ------------------------------------------------------------
// Moduli e lezioni
// ------------------------------------------------------------

function corsi_moduli(int $corso): array {
    corsi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('corsi_moduli') . ' WHERE corso_id = ? ORDER BY ordine, id'); $st->execute([$corso]);
    return $st->fetchAll();
}

function corsi_modulo(int $id): ?array {
    $st = db()->prepare('SELECT * FROM ' . tbl('corsi_moduli') . ' WHERE id = ?'); $st->execute([$id]);
    return $st->fetch() ?: null;
}

function corsi_modulo_crea(int $corso, string $titolo): int {
    $titolo = membri_testo($titolo, 200) ?: 'Modulo nuovo';
    $st = db()->prepare('SELECT COALESCE(MAX(ordine), 0) + 1 FROM ' . tbl('corsi_moduli') . ' WHERE corso_id = ?'); $st->execute([$corso]);
    db()->prepare('INSERT INTO ' . tbl('corsi_moduli') . ' (corso_id, titolo, ordine) VALUES (?, ?, ?)')->execute([$corso, $titolo, (int)$st->fetchColumn()]);
    return (int)db()->lastInsertId();
}

/** Tutte le lezioni del corso nell'ordine in cui si seguono: ordine del modulo, poi ordine della lezione. */
function corsi_lezioni(int $corso): array {
    corsi_schema();
    $st = db()->prepare('SELECT l.* FROM ' . tbl('corsi_lezioni') . ' l JOIN ' . tbl('corsi_moduli') . ' m ON m.id = l.modulo_id WHERE l.corso_id = ? ORDER BY m.ordine, m.id, l.ordine, l.id');
    $st->execute([$corso]);
    return $st->fetchAll();
}

function corsi_lezione(int $id): ?array {
    corsi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('corsi_lezioni') . ' WHERE id = ?'); $st->execute([$id]);
    return $st->fetch() ?: null;
}

function corsi_lezione_crea(int $corso, int $modulo, string $titolo): int {
    $m = corsi_modulo($modulo);
    if (!$m || (int)$m['corso_id'] !== $corso) throw new RuntimeException('Modulo non trovato.');
    $titolo = membri_testo($titolo, 200);
    if ($titolo === '') throw new RuntimeException('Scrivi il titolo della lezione.');
    $slug = corsi_slug_libero('lezioni', corsi_slug($titolo) ?: 'lezione', $corso);
    $st = db()->prepare('SELECT COALESCE(MAX(ordine), 0) + 1 FROM ' . tbl('corsi_lezioni') . ' WHERE modulo_id = ?'); $st->execute([$modulo]);
    db()->prepare('INSERT INTO ' . tbl('corsi_lezioni') . ' (corso_id, modulo_id, slug, titolo, testo, materiali, ordine, creato, aggiornato) VALUES (?, ?, ?, ?, \'\', \'[]\', ?, NOW(), NOW())')
        ->execute([$corso, $modulo, $slug, $titolo, (int)$st->fetchColumn()]);
    return (int)db()->lastInsertId();
}

/** I materiali di una lezione: i nomi dei file riservati di Download. */
function corsi_materiali(array $l): array {
    $m = json_decode((string)($l['materiali'] ?? '[]'), true);
    return array_values(array_filter(is_array($m) ? $m : [], fn($x) => is_string($x) && preg_match('/^[a-z0-9][a-z0-9._-]{0,120}$/', $x)));
}

function corsi_lezione_salva(int $id, array $d): void {
    $l = corsi_lezione($id);
    if (!$l) throw new RuntimeException('Lezione non trovata.');
    $titolo = membri_testo((string)($d['titolo'] ?? $l['titolo']), 200);
    if ($titolo === '') throw new RuntimeException('Il titolo non può essere vuoto.');
    $slug = corsi_slug((string)($d['slug'] ?? '')) ?: corsi_slug($titolo);
    $slug = corsi_slug_libero('lezioni', $slug ?: 'lezione', (int)$l['corso_id'], $id);
    $modulo = (int)($d['modulo_id'] ?? $l['modulo_id']);
    $m = corsi_modulo($modulo);
    if (!$m || (int)$m['corso_id'] !== (int)$l['corso_id']) throw new RuntimeException('Modulo non trovato.');
    $video = trim((string)($d['video_url'] ?? $l['video_url']));
    if ($video !== '' && corsi_video_embed($video) === null) throw new RuntimeException('L\'indirizzo del video non va bene: copia quello di un video di YouTube (youtube.com/watch?v=... oppure youtu.be/...) o di Vimeo (vimeo.com/123456).');
    $riservati = array_filter(scaricabili_elenco(), fn($x) => $x['riservato']);
    $materiali = array_values(array_filter((array)($d['materiali'] ?? corsi_materiali($l)), fn($x) => is_string($x) && isset($riservati[$x])));
    $testo = sicurezza_html_pulito(function_exists('sito_senza_4byte') ? sito_senza_4byte((string)($d['testo'] ?? $l['testo'])) : (string)($d['testo'] ?? $l['testo']), 'articolo');
    if ((int)$m['id'] !== (int)$l['modulo_id']) {
        $st = db()->prepare('SELECT COALESCE(MAX(ordine), 0) + 1 FROM ' . tbl('corsi_lezioni') . ' WHERE modulo_id = ?'); $st->execute([$modulo]);
        db()->prepare('UPDATE ' . tbl('corsi_lezioni') . ' SET modulo_id = ?, ordine = ? WHERE id = ?')->execute([$modulo, (int)$st->fetchColumn(), $id]);
    }
    db()->prepare('UPDATE ' . tbl('corsi_lezioni') . ' SET titolo = ?, slug = ?, testo = ?, video_url = ?, materiali = ?, durata = ?, aggiornato = NOW() WHERE id = ?')
        ->execute([$titolo, $slug, $testo, mb_substr($video, 0, 500), json_encode($materiali), membri_testo((string)($d['durata'] ?? $l['durata']), 40), $id]);
}

function corsi_lezione_elimina(int $id): void {
    $l = corsi_lezione($id);
    if (!$l) return;
    if ($l['video_file'] !== '') @unlink(corsi_video_dir() . '/' . basename((string)$l['video_file']));
    db()->prepare('DELETE FROM ' . tbl('corsi_progressi') . ' WHERE lezione_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('corsi_lezioni') . ' WHERE id = ?')->execute([$id]);
}

/** Sposta una lezione (o un modulo) di un posto in su o in giù, scambiando l'ordine con la vicina. */
function corsi_sposta(string $cosa, int $id, int $verso): void {
    if ($cosa === 'modulo') {
        $x = corsi_modulo($id); if (!$x) return;
        $fratelli = corsi_moduli((int)$x['corso_id']); $tab = 'corsi_moduli';
    } else {
        $x = corsi_lezione($id); if (!$x) return;
        $st = db()->prepare('SELECT * FROM ' . tbl('corsi_lezioni') . ' WHERE modulo_id = ? ORDER BY ordine, id'); $st->execute([(int)$x['modulo_id']]);
        $fratelli = $st->fetchAll(); $tab = 'corsi_lezioni';
    }
    $ids = array_map(fn($r) => (int)$r['id'], $fratelli);
    $i = array_search($id, $ids, true);
    $j = $i === false ? false : $i + ($verso < 0 ? -1 : 1);
    if ($i === false || $j < 0 || $j >= count($ids)) return;
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    $up = db()->prepare('UPDATE ' . tbl($tab) . ' SET ordine = ? WHERE id = ?');
    foreach ($ids as $k => $v) $up->execute([$k + 1, $v]);
}

function corsi_modulo_elimina(int $id): void {
    $m = corsi_modulo($id);
    if (!$m) return;
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('corsi_lezioni') . ' WHERE modulo_id = ?'); $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) throw new RuntimeException('Il modulo ha ancora delle lezioni: spostale in un altro modulo o eliminale, poi elimina il modulo.');
    if (count(corsi_moduli((int)$m['corso_id'])) <= 1) throw new RuntimeException('Un corso ha almeno un modulo.');
    db()->prepare('DELETE FROM ' . tbl('corsi_moduli') . ' WHERE id = ?')->execute([$id]);
}

// ------------------------------------------------------------
// Video
// ------------------------------------------------------------

/** L'indirizzo da incorporare per un video di YouTube o di Vimeo, oppure null se l'indirizzo non è di uno dei due. */
function corsi_video_embed(string $url): ?string {
    $url = trim($url);
    if (strlen($url) > 500 || !preg_match('~^https?://~i', $url)) return null;
    $p = parse_url($url);
    if (!$p || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['port'])) return null;
    $host = strtolower($p['host']);
    $path = (string)($p['path'] ?? '');
    parse_str((string)($p['query'] ?? ''), $q);
    $yt = null;
    if (in_array($host, ['www.youtube.com', 'youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'], true)) {
        if ($path === '/watch' && is_string($q['v'] ?? null)) $yt = $q['v'];
        elseif (preg_match('~^/(?:embed|shorts|live)/([^/]+)/?$~', $path, $m)) $yt = $m[1];
    } elseif ($host === 'youtu.be' && preg_match('~^/([^/]+)/?$~', $path, $m)) $yt = $m[1];
    if ($yt !== null) return preg_match('/^[A-Za-z0-9_-]{11}$/', $yt) ? 'https://www.youtube-nocookie.com/embed/' . $yt : null;
    if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('~^/(\d{5,12})/?$~', $path, $m)) return 'https://player.vimeo.com/video/' . $m[1];
    if ($host === 'player.vimeo.com' && preg_match('~^/video/(\d{5,12})/?$~', $path, $m)) return 'https://player.vimeo.com/video/' . $m[1];
    return null;
}

/** La cartella dei video caricati: nome a caso, ricordato nelle impostazioni, chiusa al web. */
function corsi_video_dir(): string {
    $nome = (string)get_setting('corsi_video_cartella', '');
    if (!preg_match('/^corsi-[a-f0-9]{16}$/', $nome)) { $nome = 'corsi-' . bin2hex(random_bytes(8)); save_setting('corsi_video_cartella', $nome); }
    $d = cms_uploads_dir() . '/' . $nome;
    if (!is_dir($d)) @mkdir($d, 0755, true);
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "# Video dei corsi di plumeCMS: si vedono solo dal sito, a chi segue il corso.\nRequire all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\nOptions -Indexes\n");
    if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
    return $d;
}

/** Il file caricato è davvero un video mp4 o webm? Si guarda l'inizio del file, non il nome. */
function corsi_video_ok(string $percorso, string $ext): bool {
    $t = (string)@file_get_contents($percorso, false, null, 0, 16);
    if ($ext === 'mp4') return strlen($t) >= 12 && substr($t, 4, 4) === 'ftyp';
    if ($ext === 'webm') return str_starts_with($t, "\x1A\x45\xDF\xA3");
    return false;
}

/** Un video caricato dal pannello ($_FILES['video']) per una lezione. Ritorna '' se va bene, altrimenti il motivo. */
function corsi_video_carica($f, int $lezione): string {
    $l = corsi_lezione($lezione);
    if (!$l) return 'Lezione non trovata.';
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return 'Il video supera il limite di caricamento del server (' . ini_get('upload_max_filesize') . '). Mettilo su YouTube o Vimeo e incolla qui l\'indirizzo, oppure chiedi all\'hosting di alzare il limite.';
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) return 'Caricamento del video non riuscito: riprova.';
    $ext = strtolower(pathinfo(basename((string)$f['name']), PATHINFO_EXTENSION));
    if (!isset(CORSI_VIDEO_TIPI[$ext])) return 'Si caricano video .mp4 o .webm.';
    if (!corsi_video_ok((string)$f['tmp_name'], $ext)) return 'Il file non è un video .' . $ext . ': non è stato caricato.';
    $nome = 'lezione-' . $lezione . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file((string)$f['tmp_name'], corsi_video_dir() . '/' . $nome)) return 'Non si è potuto scrivere il video sul server.';
    @chmod(corsi_video_dir() . '/' . $nome, 0644);
    if ($l['video_file'] !== '') @unlink(corsi_video_dir() . '/' . basename((string)$l['video_file']));
    db()->prepare('UPDATE ' . tbl('corsi_lezioni') . ' SET video_file = ?, aggiornato = NOW() WHERE id = ?')->execute([$nome, $lezione]);
    return '';
}

function corsi_video_togli(int $lezione): void {
    $l = corsi_lezione($lezione);
    if (!$l || $l['video_file'] === '') return;
    @unlink(corsi_video_dir() . '/' . basename((string)$l['video_file']));
    db()->prepare('UPDATE ' . tbl('corsi_lezioni') . " SET video_file = '' WHERE id = ?")->execute([$lezione]);
}

/** Manda un video caricato, anche a pezzi (Range): così si può andare avanti e indietro. */
function corsi_manda_video(string $percorso): never {
    $ext = strtolower(pathinfo($percorso, PATHINFO_EXTENSION));
    $peso = (int)filesize($percorso);
    $da = 0; $a = $peso - 1;
    header('Accept-Ranges: bytes');
    header('Content-Type: ' . (CORSI_VIDEO_TIPI[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    header('Content-Disposition: inline');
    sicurezza_intestazioni_base();
    $range = (string)($_SERVER['HTTP_RANGE'] ?? '');
    if ($range !== '') {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) || ($m[1] === '' && $m[2] === '')) { http_response_code(416); header("Content-Range: bytes */$peso"); exit; }
        if ($m[1] === '') { $da = max(0, $peso - (int)$m[2]); }
        else { $da = (int)$m[1]; if ($m[2] !== '') $a = min($a, (int)$m[2]); }
        if ($da > $a || $da >= $peso) { http_response_code(416); header("Content-Range: bytes */$peso"); exit; }
        http_response_code(206);
        header("Content-Range: bytes $da-$a/$peso");
    }
    header('Content-Length: ' . ($a - $da + 1));
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
    $fh = fopen($percorso, 'rb');
    fseek($fh, $da);
    $resto = $a - $da + 1;
    while ($resto > 0 && !feof($fh)) { $pezzo = fread($fh, (int)min(262144, $resto)); echo $pezzo; $resto -= strlen($pezzo); flush(); }
    fclose($fh);
    exit;
}

// ------------------------------------------------------------
// Accesso, iscritti, avanzamento
// ------------------------------------------------------------

/** Il membro può seguire il corso? Corso pubblicato, membro attivo, e (se il corso è "assegnati") assegnato. */
function corsi_puo(array $corso, ?array $membro): bool {
    if (!$membro || $corso['stato'] !== 'pubblicato' || ($membro['stato'] ?? '') !== 'attivo') return false;
    if ($corso['accesso'] === 'tutti') return true;
    $st = db()->prepare('SELECT assegnato FROM ' . tbl('corsi_iscritti') . ' WHERE corso_id = ? AND membro_id = ?');
    $st->execute([(int)$corso['id'], (int)$membro['id']]);
    return (int)$st->fetchColumn() === 1;
}

function corsi_assegna(int $corso, int $membro, bool $si): void {
    corsi_schema();
    if ($si) db()->prepare('INSERT INTO ' . tbl('corsi_iscritti') . ' (corso_id, membro_id, assegnato) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE assegnato = 1')->execute([$corso, $membro]);
    else db()->prepare('UPDATE ' . tbl('corsi_iscritti') . ' SET assegnato = 0 WHERE corso_id = ? AND membro_id = ?')->execute([$corso, $membro]);
}

/** Gli iscritti del corso (chi l'ha cominciato o a chi è assegnato), con nome, email e lezioni fatte. */
function corsi_iscritti(int $corso): array {
    corsi_schema();
    $st = db()->prepare('SELECT i.*, m.nome, m.email, m.stato AS membro_stato,
        (SELECT COUNT(*) FROM ' . tbl('corsi_progressi') . ' p JOIN ' . tbl('corsi_lezioni') . ' l ON l.id = p.lezione_id WHERE p.membro_id = i.membro_id AND l.corso_id = i.corso_id) AS fatte,
        (SELECT codice FROM ' . tbl('corsi_certificati') . ' c WHERE c.corso_id = i.corso_id AND c.membro_id = i.membro_id) AS certificato
        FROM ' . tbl('corsi_iscritti') . ' i JOIN ' . tbl('membri') . ' m ON m.id = i.membro_id WHERE i.corso_id = ? ORDER BY m.nome, m.email');
    $st->execute([$corso]);
    return $st->fetchAll();
}

/** Gli id delle lezioni del corso completate dal membro. */
function corsi_completate(int $membro, int $corso): array {
    $st = db()->prepare('SELECT p.lezione_id FROM ' . tbl('corsi_progressi') . ' p JOIN ' . tbl('corsi_lezioni') . ' l ON l.id = p.lezione_id WHERE p.membro_id = ? AND l.corso_id = ?');
    $st->execute([$membro, $corso]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Quanto ha fatto: ['fatte', 'totale', 'percento', 'prossima' (la prima lezione da fare, o l'ultima vista), 'completato']. */
function corsi_avanzamento(int $membro, array $corso): array {
    $lezioni = corsi_lezioni((int)$corso['id']);
    $fatte = array_flip(corsi_completate($membro, (int)$corso['id']));
    $n = count(array_filter($lezioni, fn($l) => isset($fatte[(int)$l['id']])));
    $st = db()->prepare('SELECT ultima_lezione FROM ' . tbl('corsi_iscritti') . ' WHERE corso_id = ? AND membro_id = ?');
    $st->execute([(int)$corso['id'], $membro]);
    $ultima = (int)$st->fetchColumn();
    $prossima = null;
    // Riprendi da dove eri: la prima lezione non fatta dopo l'ultima vista, altrimenti la prima non fatta del corso.
    $dopo = false;
    foreach ($lezioni as $l) { if ($dopo && !isset($fatte[(int)$l['id']])) { $prossima = $l; break; } if ((int)$l['id'] === $ultima) { $dopo = true; if (!isset($fatte[$ultima])) { $prossima = $l; break; } } }
    if (!$prossima) foreach ($lezioni as $l) if (!isset($fatte[(int)$l['id']])) { $prossima = $l; break; }
    $tot = count($lezioni);
    return ['fatte' => $n, 'totale' => $tot, 'percento' => $tot ? (int)floor($n * 100 / $tot) : 0, 'prossima' => $prossima, 'completato' => $tot > 0 && $n === $tot, 'iniziato' => $ultima > 0 || $n > 0];
}

/** Il membro apre una lezione: ricordiamo dove è arrivato (per "Riprendi da dove eri"). */
function corsi_segna_visita(int $membro, array $lezione): void {
    db()->prepare('INSERT INTO ' . tbl('corsi_iscritti') . ' (corso_id, membro_id, iniziato, ultimo, ultima_lezione) VALUES (?, ?, NOW(), NOW(), ?)
        ON DUPLICATE KEY UPDATE iniziato = COALESCE(iniziato, NOW()), ultimo = NOW(), ultima_lezione = VALUES(ultima_lezione)')
        ->execute([(int)$lezione['corso_id'], $membro, (int)$lezione['id']]);
}

/** Segna (o toglie) una lezione come completata. Se il corso è finito, nasce il certificato. */
function corsi_completa(array $membro, array $lezione, bool $si): void {
    if ($si) db()->prepare('INSERT IGNORE INTO ' . tbl('corsi_progressi') . ' (membro_id, lezione_id, completata) VALUES (?, ?, NOW())')->execute([(int)$membro['id'], (int)$lezione['id']]);
    else db()->prepare('DELETE FROM ' . tbl('corsi_progressi') . ' WHERE membro_id = ? AND lezione_id = ?')->execute([(int)$membro['id'], (int)$lezione['id']]);
    $corso = corsi_leggi((int)$lezione['corso_id']);
    if (!$corso) return;
    $av = corsi_avanzamento((int)$membro['id'], $corso);
    db()->prepare('UPDATE ' . tbl('corsi_iscritti') . ' SET completato = ' . ($av['completato'] ? 'COALESCE(completato, NOW())' : 'NULL') . ' WHERE corso_id = ? AND membro_id = ?')->execute([(int)$corso['id'], (int)$membro['id']]);
    if ($av['completato']) corsi_certificato_crea($membro, $corso);
}

// ------------------------------------------------------------
// Certificati
// ------------------------------------------------------------

function corsi_codice_nuovo(): string {
    $c = '';
    for ($i = 0; $i < 12; $i++) $c .= CORSI_ALFABETO[random_int(0, 31)];
    return substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4);
}

/** Il certificato del membro per il corso: c'è già, oppure nasce adesso (con il nome di oggi e la data di oggi). */
function corsi_certificato_crea(array $membro, array $corso): array {
    $c = corsi_certificato_di((int)$membro['id'], (int)$corso['id']);
    if ($c) return $c;
    for ($i = 0; $i < 5; $i++) {
        try {
            db()->prepare('INSERT INTO ' . tbl('corsi_certificati') . ' (codice, corso_id, membro_id, nome, corso_titolo, data) VALUES (?, ?, ?, ?, ?, NOW())')
                ->execute([corsi_codice_nuovo(), (int)$corso['id'], (int)$membro['id'], (string)($membro['nome'] ?: $membro['email']), (string)$corso['titolo']]);
            break;
        } catch (PDOException $e) {
            if ((string)$e->getCode() !== '23000') throw $e;
            if ($c = corsi_certificato_di((int)$membro['id'], (int)$corso['id'])) return $c;
        }
    }
    return corsi_certificato_di((int)$membro['id'], (int)$corso['id']) ?? [];
}

function corsi_certificato_di(int $membro, int $corso): ?array {
    $st = db()->prepare('SELECT * FROM ' . tbl('corsi_certificati') . ' WHERE membro_id = ? AND corso_id = ?'); $st->execute([$membro, $corso]);
    return $st->fetch() ?: null;
}

/** Un certificato dal suo codice pubblico (maiuscole o minuscole, con o senza trattini). */
function corsi_certificato_per_codice(string $codice): ?array {
    $c = strtoupper(preg_replace('/[\s-]+/', '', $codice) ?? '');
    if (!preg_match('/^[' . CORSI_ALFABETO . ']{12}$/', $c)) return null;
    corsi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('corsi_certificati') . ' WHERE codice = ?');
    $st->execute([substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4)]);
    return $st->fetch() ?: null;
}

/** Un membro cancellato: via avanzamento, assegnazioni e certificati. */
function corsi_dimentica_membro(int $id): void {
    corsi_schema();
    foreach (['corsi_progressi', 'corsi_iscritti', 'corsi_certificati'] as $t) db()->prepare('DELETE FROM ' . tbl($t) . ' WHERE membro_id = ?')->execute([$id]);
}
