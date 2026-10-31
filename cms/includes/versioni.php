<?php
/**
 * Versioni di ogni modifica fatta con l'editor e con le sue voci del pannello.
 *
 * Una tabella sola, {prefisso}editor_versioni: a ogni salvataggio che cambia davvero qualcosa resta una copia dei dati,
 * con la data, chi l'ha fatta, una nota e un'impronta (sha1) per non scrivere due volte la stessa cosa.
 * Ambiti:
 *   contenuto:<tipo>   struttura dell'editor (albero), stato bozza/pubblicata, header e footer scelti; e la scheda classica
 *   modello            header e footer (albero, nome, stato, predefinito, condizioni, priorità)
 *   menu               un menu di navigazione (nome, posizione, voci)
 *   stile              lo Stile del sito (tutte le schede)
 *   tema               un tema delle infografiche (nome, valori)
 *   impostazioni       le impostazioni dell'editor (le voci editor_* della tabella delle impostazioni)
 * Limite per oggetto dall'impostazione editor_versioni_limite (predefinito 50): le versioni pubblicate e quelle con una
 * nota scritta a mano non si potano mai. I dati oltre 64 KB stanno compressi (gzip).
 *
 * API: editor_versione_salva(), editor_versioni(), editor_versione(), editor_versione_ripristina(), editor_versioni_confronta().
 * Il ripristino passa per i salvataggi normali (editor_salva, design_salva, menu_salva...) e lascia a sua volta una
 * versione "ripristino della versione N". Le vecchie tabelle {prefisso}pagine_revisioni e {prefisso}revisioni si
 * importano una volta sola (versioni_migra) e restano al loro posto.
 */

require_once __DIR__ . '/db.php';

const VERSIONI_SCHEMA = '1';
const VERSIONI_LIMITE_PREDEFINITO = 50;
const VERSIONI_COMPRIMI_OLTRE = 65536;

// ------------------------------------------------------------
// Tabella, migrazione, servizio
// ------------------------------------------------------------

/** Crea la tabella (l'installatore e il primo uso). Idempotente. */
function versioni_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    if (get_setting('versioni_schema', '') === VERSIONI_SCHEMA) return;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('editor_versioni') . " (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `ambito` VARCHAR(60) NOT NULL,
        `oggetto_id` INT(11) NOT NULL DEFAULT 0,
        `oggetto_nome` VARCHAR(200) NOT NULL DEFAULT '',
        `dati` LONGBLOB,
        `compresso` TINYINT(1) NOT NULL DEFAULT 0,
        `impronta` CHAR(40) NOT NULL DEFAULT '',
        `autore` VARCHAR(100) NOT NULL DEFAULT '',
        `nota` VARCHAR(255) NOT NULL DEFAULT '',
        `nota_mano` TINYINT(1) NOT NULL DEFAULT 0,
        `pubblicata` TINYINT(1) NOT NULL DEFAULT 0,
        `creato` DATETIME NOT NULL,
        `dimensione` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`), KEY `oggetto` (`ambito`, `oggetto_id`, `id`), KEY `creato` (`creato`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    save_setting('versioni_schema', VERSIONI_SCHEMA);
}

/** Tabella pronta e vecchie revisioni importate: lo chiamano tutte le funzioni che leggono o scrivono. */
function versioni_pronto(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    versioni_installa();
    versioni_migra();
}

/**
 * Una volta sola: le righe di {prefisso}pagine_revisioni (editor) e {prefisso}revisioni (scheda classica) entrano nelle
 * versioni, con data, autore e nota di allora. Le due tabelle restano com'erano.
 */
function versioni_migra(): void {
    if (get_setting('versioni_migrate', '') === '1') return;
    $pdo = db();
    $esiste = fn(string $t) => (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote(DB_PREFIX . $t))->fetchColumn();
    $ins = $pdo->prepare('INSERT INTO ' . tbl('editor_versioni') . ' (ambito, oggetto_id, oggetto_nome, dati, compresso, impronta, autore, nota, nota_mano, pubblicata, creato, dimensione) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)');
    $nomi = [];
    $nome = function (string $tipo, int $id) use (&$nomi): string {
        $k = "$tipo:$id";
        if (!isset($nomi[$k])) {
            $nomi[$k] = '';
            try {
                if ($tipo === 'modelli') { $st = db()->prepare('SELECT nome FROM ' . tbl('modelli') . ' WHERE id = ?'); }
                else { require_once __DIR__ . '/contenuti.php'; $tab = contenuti_tipi()[$tipo]['tabella'] ?? null; $st = $tab ? db()->prepare('SELECT title FROM ' . tbl($tab) . ' WHERE id = ?') : null; }
                if ($st) { $st->execute([$id]); $nomi[$k] = (string)($st->fetchColumn() ?: ''); }
            } catch (Throwable $e) { /* tabella assente: resta vuoto */ }
        }
        return $nomi[$k];
    };
    $n = 0;
    if ($esiste('pagine_revisioni')) {
        foreach ($pdo->query('SELECT * FROM ' . tbl('pagine_revisioni') . ' ORDER BY id') as $r) {
            $albero = json_decode((string)$r['struttura'], true);
            if (!is_array($albero)) continue;
            $tipo = (string)($r['tipo'] ?: 'pagine');
            $ambito = $tipo === 'modelli' ? 'modello' : 'contenuto:' . $tipo;
            $dati = ['albero' => $albero, 'stato' => 'pubblicata'];
            [$blob, $comp, $dim] = versioni_impacchetta($dati);
            $ins->execute([$ambito, (int)$r['pagina_id'], mb_substr($nome($tipo, (int)$r['pagina_id']), 0, 200), $blob, $comp, versioni_impronta($dati), (string)$r['autore'], mb_substr((string)$r['nota'], 0, 255), 1, (string)$r['creato'], $dim]);
            $n++;
        }
    }
    if ($esiste('revisioni')) {
        foreach ($pdo->query('SELECT * FROM ' . tbl('revisioni') . ' ORDER BY id') as $r) {
            $riga = json_decode((string)$r['dati'], true);
            if (!is_array($riga)) continue;
            $tipo = (string)$r['tipo'];
            $dati = ['scheda' => versioni_scheda_pulita($riga)];
            [$blob, $comp, $dim] = versioni_impacchetta($dati);
            $ins->execute(['contenuto:' . $tipo, (int)$r['contenuto_id'], mb_substr((string)($riga['title'] ?? $nome($tipo, (int)$r['contenuto_id'])), 0, 200), $blob, $comp, versioni_impronta($dati), (string)$r['autore'], mb_substr('scheda: ' . (string)$r['nota'], 0, 255), 0, (string)$r['creato'], $dim]);
            $n++;
        }
    }
    save_setting('versioni_migrate', '1');
    if ($n) error_log("versioni: importate $n revisioni dalle tabelle di prima");
}

/** Chi sta lavorando nel pannello ('' da riga di comando o senza sessione). */
function versioni_autore(): string {
    if (function_exists('current_user')) {
        try { $u = current_user(); if (is_array($u)) return mb_substr((string)($u['username'] ?? ''), 0, 100); } catch (Throwable $e) { /* senza sessione */ }
    }
    return mb_substr((string)($_SESSION['username'] ?? ''), 0, 100);
}

/** Quante versioni tenere per oggetto (impostazione editor_versioni_limite, da 5 a 500; predefinito 50). */
function versioni_limite(): int {
    $n = (int)(function_exists('cms_impostazione') ? cms_impostazione('editor_versioni_limite', VERSIONI_LIMITE_PREDEFINITO) : get_setting('editor_versioni_limite', VERSIONI_LIMITE_PREDEFINITO));
    return max(5, min(500, $n ?: VERSIONI_LIMITE_PREDEFINITO));
}

/** Durante un ripristino i salvataggi normali tacciono: la versione la scrive il ripristino, con la sua nota. */
function versioni_silenzio(?bool $on = null): bool {
    static $s = false;
    if ($on !== null) $s = $on;
    return $s;
}

/** Gli ambiti con l'etichetta per il pannello. */
function versioni_ambiti(): array {
    $a = [];
    try {
        require_once __DIR__ . '/contenuti.php';
        foreach (contenuti_tipi() as $k => $t) $a['contenuto:' . $k] = ucfirst((string)($t['tanti'] ?? $k));
    } catch (Throwable $e) {
        $a['contenuto:pagine'] = 'Pagine'; $a['contenuto:articoli'] = 'Articoli';
    }
    // 2.1: i contenitori dell'editor dei moduli accesi (le lezioni dei corsi...).
    try { require_once __DIR__ . '/editor.php'; foreach (editor_contenitori() as $k => $d) $a['contenitore:' . $k] = (string)$d['etichetta']; } catch (Throwable $e) { /* senza moduli */ }
    return $a + ['modello' => 'Header e footer', 'menu' => 'Menu di navigazione', 'stile' => 'Stile del sito', 'tema' => 'Colori delle infografiche', 'impostazioni' => 'Impostazioni dell\'editor'];
}

function versioni_ambito_etichetta(string $ambito): string {
    return versioni_ambiti()[$ambito] ?? ucfirst(str_replace(['contenuto:', 'contenitore:', '_'], ['', '', ' '], $ambito));
}

// ------------------------------------------------------------
// JSON, impronta, compressione
// ------------------------------------------------------------

/** JSON con le emoji scritte come coppie \uXXXX (la stessa forma dell'editor): uguale da confrontare, sicuro nella colonna. */
function versioni_json(array $dati): string {
    $json = json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) $json = json_encode($dati, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', function ($m) {
        $c = mb_ord($m[0]) - 0x10000;
        return sprintf('\\u%04x\\u%04x', 0xD800 + ($c >> 10), 0xDC00 + ($c & 0x3FF));
    }, $json);
}

/** Le chiavi degli oggetti in ordine, a ogni livello: due dati uguali danno la stessa impronta anche se scritti in ordine diverso. */
function versioni_canonico($v) {
    if (!is_array($v)) return $v;
    $lista = array_keys($v) === range(0, count($v) - 1);
    $out = [];
    foreach ($v as $k => $x) $out[$k] = versioni_canonico($x);
    if (!$lista) ksort($out, SORT_STRING);
    return $out;
}

function versioni_impronta(array $dati): string {
    return sha1(versioni_json(versioni_canonico($dati)));
}

/** [blob, compresso, dimensione del JSON]. Oltre 64 KB il JSON viaggia compresso. */
function versioni_impacchetta(array $dati): array {
    $json = versioni_json($dati);
    $dim = strlen($json);
    if ($dim > VERSIONI_COMPRIMI_OLTRE && function_exists('gzcompress')) return [gzcompress($json, 6), 1, $dim];
    return [$json, 0, $dim];
}

function versioni_spacchetta(?string $blob, int $compresso): ?array {
    if ($blob === null || $blob === '') return null;
    if ($compresso) { $blob = @gzuncompress($blob); if ($blob === false) return null; }
    $d = json_decode($blob, true);
    return is_array($d) ? $d : null;
}

/** La riga di un contenuto senza le colonne che cambiano da sole o che vivono altrove (le strutture hanno le loro versioni). */
function versioni_scheda_pulita(array $riga): array {
    unset($riga['struttura'], $riga['struttura_bozza'], $riga['struttura_aggiornata'], $riga['updated_at'], $riga['created_at'], $riga['wp_hash'], $riga['_tipo']);
    return $riga;
}

// ------------------------------------------------------------
// API
// ------------------------------------------------------------

/**
 * Salva una versione, se i dati sono diversi dall'ultima dello stesso oggetto. Ritorna l'id, oppure 0 se non c'era
 * niente di nuovo (o se un ripristino è in corso). $opz: pubblicata (bool), nota_mano (bool), autore (string).
 */
function editor_versione_salva(string $ambito, int $oggettoId, string $nome, array $dati, string $nota = '', array $opz = []): int {
    if (versioni_silenzio()) return 0;
    versioni_pronto();
    $impronta = versioni_impronta($dati);
    $st = db()->prepare('SELECT id, impronta FROM ' . tbl('editor_versioni') . ' WHERE ambito = ? AND oggetto_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$ambito, $oggettoId]);
    $ultima = $st->fetch();
    if ($ultima && $ultima['impronta'] === $impronta && empty($opz['forza'])) return 0;
    [$blob, $comp, $dim] = versioni_impacchetta($dati);
    $autore = mb_substr((string)($opz['autore'] ?? ''), 0, 100) ?: versioni_autore();
    db()->prepare('INSERT INTO ' . tbl('editor_versioni') . ' (ambito, oggetto_id, oggetto_nome, dati, compresso, impronta, autore, nota, nota_mano, pubblicata, creato, dimensione) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)')
        ->execute([$ambito, $oggettoId, mb_substr(trim($nome), 0, 200), $blob, $comp, $impronta, $autore, mb_substr(trim($nota), 0, 255), !empty($opz['nota_mano']) ? 1 : 0, !empty($opz['pubblicata']) ? 1 : 0, $dim]);
    $id = (int)db()->lastInsertId();
    versioni_pota($ambito, $oggettoId);
    return $id;
}

/** Oltre il limite si tolgono le più vecchie, mai le pubblicate né quelle con una nota scritta a mano. */
function versioni_pota(string $ambito, int $oggettoId): void {
    $st = db()->prepare('SELECT id, pubblicata, nota_mano FROM ' . tbl('editor_versioni') . ' WHERE ambito = ? AND oggetto_id = ? ORDER BY id DESC');
    $st->execute([$ambito, $oggettoId]);
    $righe = $st->fetchAll();
    $limite = versioni_limite();
    if (count($righe) <= $limite) return;
    $via = [];
    foreach (array_slice($righe, $limite) as $r) if (!$r['pubblicata'] && !$r['nota_mano']) $via[] = (int)$r['id'];
    if ($via) db()->exec('DELETE FROM ' . tbl('editor_versioni') . ' WHERE id IN (' . implode(',', $via) . ')');
}

/**
 * L'elenco (senza i dati), dalla più recente. $ambito '' = tutti; 'contenuto' = tutti i tipi di contenuto.
 * $oggettoId 0 = tutti gli oggetti dell'ambito. Ritorna anche il totale per la paginazione.
 */
function editor_versioni(string $ambito = '', int $oggettoId = 0, int $limite = 300, int $da = 0): array {
    versioni_pronto();
    $dove = []; $p = [];
    if ($ambito === 'contenuto') { $dove[] = "ambito LIKE 'contenuto:%'"; }
    elseif ($ambito !== '') { $dove[] = 'ambito = ?'; $p[] = $ambito; }
    if ($oggettoId) { $dove[] = 'oggetto_id = ?'; $p[] = $oggettoId; }
    $w = $dove ? ' WHERE ' . implode(' AND ', $dove) : '';
    $st = db()->prepare('SELECT id, ambito, oggetto_id, oggetto_nome, autore, nota, nota_mano, pubblicata, creato, dimensione FROM ' . tbl('editor_versioni') . $w . ' ORDER BY id DESC LIMIT ' . (int)$limite . ' OFFSET ' . (int)$da);
    $st->execute($p);
    return $st->fetchAll();
}

function versioni_conta(string $ambito = '', int $oggettoId = 0): int {
    versioni_pronto();
    $dove = []; $p = [];
    if ($ambito === 'contenuto') { $dove[] = "ambito LIKE 'contenuto:%'"; }
    elseif ($ambito !== '') { $dove[] = 'ambito = ?'; $p[] = $ambito; }
    if ($oggettoId) { $dove[] = 'oggetto_id = ?'; $p[] = $oggettoId; }
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('editor_versioni') . ($dove ? ' WHERE ' . implode(' AND ', $dove) : ''));
    $st->execute($p);
    return (int)$st->fetchColumn();
}

/** Una versione con i dati decodificati, oppure null. */
function editor_versione(int $id): ?array {
    versioni_pronto();
    $st = db()->prepare('SELECT * FROM ' . tbl('editor_versioni') . ' WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) return null;
    $r['dati'] = versioni_spacchetta($r['dati'], (int)$r['compresso']) ?? [];
    return $r;
}

/** La versione subito prima di questa, dello stesso oggetto. */
function versioni_precedente(array $v): ?array {
    $st = db()->prepare('SELECT id FROM ' . tbl('editor_versioni') . ' WHERE ambito = ? AND oggetto_id = ? AND id < ? ORDER BY id DESC LIMIT 1');
    $st->execute([$v['ambito'], (int)$v['oggetto_id'], (int)$v['id']]);
    $id = (int)$st->fetchColumn();
    return $id ? editor_versione($id) : null;
}

/** Gli oggetti di un ambito che hanno versioni: id => nome (l'ultimo registrato). */
function versioni_oggetti(string $ambito): array {
    versioni_pronto();
    $st = db()->prepare('SELECT oggetto_id, oggetto_nome FROM ' . tbl('editor_versioni') . ' WHERE ambito = ? ORDER BY id DESC');
    $st->execute([$ambito]);
    $o = [];
    foreach ($st->fetchAll() as $r) if (!isset($o[(int)$r['oggetto_id']])) $o[(int)$r['oggetto_id']] = (string)$r['oggetto_nome'];
    return $o;
}

/** Una nota scritta a mano: la versione non si pota più. */
function versioni_nota(int $id, string $nota): void {
    versioni_pronto();
    $nota = mb_substr(trim(preg_replace('/\s+/u', ' ', $nota)), 0, 255);
    $nota = preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', fn($m) => '&#x' . dechex(mb_ord($m[0])) . ';', $nota);
    db()->prepare('UPDATE ' . tbl('editor_versioni') . ' SET nota = ?, nota_mano = ? WHERE id = ?')->execute([$nota, $nota !== '' ? 1 : 0, $id]);
}

// ------------------------------------------------------------
// Lo stato di adesso di un oggetto, nella stessa forma delle versioni
// ------------------------------------------------------------

/** I dati di un contenuto dalla sua riga: albero (bozza se c'è, se no quella pubblicata), stato, header e footer. */
function versioni_dati_contenuto(array $riga): array {
    require_once __DIR__ . '/editor.php';
    $bozza = trim((string)($riga['struttura_bozza'] ?? ''));
    $pub = trim((string)($riga['struttura'] ?? ''));
    $albero = $bozza !== '' ? json_decode($bozza, true) : ($pub !== '' ? json_decode($pub, true) : null);
    return ['albero' => is_array($albero) ? $albero : null, 'stato' => $bozza !== '' ? 'bozza' : ($pub !== '' ? 'pubblicata' : 'senza editor'),
        'header_id' => (int)($riga['header_id'] ?? 0), 'footer_id' => (int)($riga['footer_id'] ?? 0)];
}

/** 2.1: i dati di un contenitore dell'editor (una lezione di un corso...) dalla sua riga: albero e stato. */
function versioni_dati_contenitore(array $riga): array {
    $d = versioni_dati_contenuto($riga);
    return ['albero' => $d['albero'], 'stato' => $d['stato']];
}

/** I dati di un modello dalla sua riga. */
function versioni_dati_modello(array $m): array {
    $bozza = trim((string)($m['struttura_bozza'] ?? ''));
    $pub = trim((string)($m['struttura'] ?? ''));
    $albero = $bozza !== '' ? json_decode($bozza, true) : ($pub !== '' ? json_decode($pub, true) : null);
    $cond = is_array($m['condizioni'] ?? null) ? $m['condizioni'] : (json_decode((string)($m['condizioni'] ?? '[]'), true) ?: []);
    return ['albero' => is_array($albero) ? $albero : null, 'stato' => $bozza !== '' ? 'bozza' : 'pubblicata', 'nome' => (string)$m['nome'], 'tipo' => (string)$m['tipo'],
        'status' => (string)$m['status'], 'predefinito' => (bool)$m['predefinito'], 'condizioni' => $cond, 'priorita' => (int)($m['priorita'] ?? 0)];
}

/** Le impostazioni dell'editor: tutte le voci editor_* della tabella delle impostazioni. */
function versioni_impostazioni_attuali(): array {
    $o = [];
    try {
        foreach (db()->query('SELECT setting_key, setting_value FROM ' . tbl('settings') . " WHERE setting_key LIKE 'editor\\_%' ORDER BY setting_key")->fetchAll() as $r) $o[$r['setting_key']] = (string)$r['setting_value'];
    } catch (Throwable $e) { /* senza tabella */ }
    return $o;
}

/** Una versione delle impostazioni dell'editor (la pagina Impostazioni dell'editor la chiama dopo il salvataggio). */
function versioni_impostazioni_salva(string $nota = ''): int {
    return editor_versione_salva('impostazioni', 0, 'Impostazioni dell\'editor', versioni_impostazioni_attuali(), $nota);
}

/** Lo stato di adesso di un oggetto, nella forma delle versioni; null se l'oggetto non esiste più. */
function versioni_dati_attuali(string $ambito, int $id): ?array {
    if (str_starts_with($ambito, 'contenuto:')) {
        require_once __DIR__ . '/editor.php';
        $riga = editor_pagina($id, editor_tipo_valido(substr($ambito, 10)));
        return $riga ? versioni_dati_contenuto($riga) : null;
    }
    if (str_starts_with($ambito, 'contenitore:')) { // 2.1
        require_once __DIR__ . '/editor.php';
        $tipo = substr($ambito, 12);
        if (!editor_contenitore($tipo)) return null;
        $riga = editor_pagina($id, $tipo);
        return $riga ? versioni_dati_contenitore($riga) : null;
    }
    switch ($ambito) {
        case 'modello':
            require_once __DIR__ . '/modelli.php';
            $m = modelli_leggi($id);
            return $m ? versioni_dati_modello($m) : null;
        case 'stile':
            require_once __DIR__ . '/design.php';
            return design_valori(true);
        case 'menu':
            require_once __DIR__ . '/menu-nav.php';
            $m = menu_leggi($id);
            return $m ? ['nome' => $m['nome'], 'posizione' => $m['posizione'], 'voci' => $m['voci']] : null;
        case 'tema':
            require_once __DIR__ . '/editor-temi.php';
            $t = editor_temi_salvati()['t' . $id] ?? null;
            return $t ? ['nome' => $t['nome'], 'valori' => $t['valori']] : null;
        case 'impostazioni':
            return versioni_impostazioni_attuali();
    }
    return null;
}

/** Il nome di adesso di un oggetto (per gli elenchi), oppure quello registrato nell'ultima versione. */
function versioni_nome_oggetto(string $ambito, int $id): string {
    try {
        if (str_starts_with($ambito, 'contenuto:')) { require_once __DIR__ . '/editor.php'; $r = editor_pagina($id, editor_tipo_valido(substr($ambito, 10))); if ($r) return (string)$r['title']; }
        elseif (str_starts_with($ambito, 'contenitore:')) { require_once __DIR__ . '/editor.php'; $t = substr($ambito, 12); $r = editor_contenitore($t) ? editor_pagina($id, $t) : null; if ($r) return (string)$r['title']; }
        elseif ($ambito === 'modello') { require_once __DIR__ . '/modelli.php'; $m = modelli_leggi($id); if ($m) return (string)$m['nome']; }
        elseif ($ambito === 'menu') { require_once __DIR__ . '/menu-nav.php'; $m = menu_leggi($id); if ($m) return (string)$m['nome']; }
        elseif ($ambito === 'tema') { require_once __DIR__ . '/editor-temi.php'; $t = editor_temi_salvati()['t' . $id] ?? null; if ($t) return (string)$t['nome']; }
        elseif ($ambito === 'stile') return 'Stile del sito';
        elseif ($ambito === 'impostazioni') return 'Impostazioni dell\'editor';
    } catch (Throwable $e) { /* si ripiega sul nome registrato */ }
    return versioni_oggetti($ambito)[$id] ?? ('n. ' . $id);
}

// ------------------------------------------------------------
// Ripristino
// ------------------------------------------------------------

/**
 * Riporta l'oggetto a quella versione, passando per i salvataggi normali, e registra una versione nuova
 * "ripristino della versione N". Ritorna una frase per il pannello. Lancia RuntimeException se non può.
 */
function editor_versione_ripristina(int $versioneId, string $autore = ''): string {
    $v = editor_versione($versioneId);
    if (!$v) throw new RuntimeException('Versione non trovata.');
    $ambito = (string)$v['ambito']; $id = (int)$v['oggetto_id']; $dati = $v['dati'];
    $autore = $autore !== '' ? $autore : versioni_autore();
    $nota = 'ripristino della versione ' . $versioneId . ($v['creato'] ? ' del ' . versioni_data_breve((string)$v['creato']) : '');
    versioni_silenzio(true);
    try {
        if (str_starts_with($ambito, 'contenuto:')) {
            require_once __DIR__ . '/editor.php';
            $tipo = editor_tipo_valido(substr($ambito, 10));
            $riga = editor_pagina($id, $tipo);
            if (!$riga) throw new RuntimeException('Il contenuto non esiste più.');
            $tab = tbl(editor_tabella($tipo));
            if (isset($dati['scheda']) && is_array($dati['scheda'])) {
                versioni_ripristina_scheda($tipo, $id, $dati['scheda']);
            } else {
                if (array_key_exists('header_id', $dati) || array_key_exists('footer_id', $dati)) {
                    require_once __DIR__ . '/modelli.php';
                    db()->prepare('UPDATE ' . $tab . ' SET header_id = ?, footer_id = ? WHERE id = ?')->execute([modelli_valore_scelta($dati['header_id'] ?? $riga['header_id'] ?? 0), modelli_valore_scelta($dati['footer_id'] ?? $riga['footer_id'] ?? 0), $id]);
                }
                if (is_array($dati['albero'] ?? null)) {
                    $albero = editor_valida($dati['albero']);
                    if (!$albero) throw new RuntimeException('La struttura salvata in questa versione non è leggibile.');
                    editor_salva($id, $albero, ($dati['stato'] ?? '') === 'pubblicata', $autore, $nota, $tipo);
                }
            }
            $nome = (string)(editor_pagina($id, $tipo)['title'] ?? $v['oggetto_nome']);
            $nuovi = versioni_dati_attuali($ambito, $id);
            if (isset($dati['scheda'])) $nuovi = ['scheda' => versioni_scheda_pulita(editor_pagina($id, $tipo) ?? [])];
        } elseif (str_starts_with($ambito, 'contenitore:')) {
            // 2.1: un contenitore di un modulo (una lezione): solo l'albero, con il salvataggio normale.
            require_once __DIR__ . '/editor.php';
            $tipo = substr($ambito, 12);
            if (!editor_contenitore($tipo)) throw new RuntimeException('Il modulo di questo contenuto è spento: riaccendilo per ripristinare.');
            if (!editor_pagina($id, $tipo)) throw new RuntimeException('Il contenuto non esiste più.');
            if (is_array($dati['albero'] ?? null)) {
                $albero = editor_valida($dati['albero']);
                if (!$albero) throw new RuntimeException('La struttura salvata in questa versione non è leggibile.');
                editor_salva($id, $albero, ($dati['stato'] ?? '') === 'pubblicata', $autore, $nota, $tipo);
            }
            $nome = (string)(editor_pagina($id, $tipo)['title'] ?? $v['oggetto_nome']);
            $nuovi = versioni_dati_attuali($ambito, $id);
        } elseif ($ambito === 'modello') {
            require_once __DIR__ . '/modelli.php'; require_once __DIR__ . '/editor.php';
            $m = modelli_leggi($id);
            if (!$m) throw new RuntimeException('Il modello non esiste più.');
            if (!empty($dati['nome']) && $dati['nome'] !== $m['nome']) modelli_rinomina($id, (string)$dati['nome']);
            if (isset($dati['condizioni'])) modelli_condizioni_salva($id, (array)$dati['condizioni'], (int)($dati['priorita'] ?? 0));
            if (isset($dati['status'])) modelli_stato($id, $dati['status'] === 'published');
            if (!empty($dati['predefinito']) && !$m['predefinito']) modelli_predefinito($id);
            if (is_array($dati['albero'] ?? null)) {
                $albero = editor_valida($dati['albero']);
                if (!$albero) throw new RuntimeException('La struttura salvata in questa versione non è leggibile.');
                editor_salva($id, $albero, ($dati['stato'] ?? '') === 'pubblicata', $autore, $nota, 'modelli');
            }
            $nome = (string)(modelli_leggi($id)['nome'] ?? $v['oggetto_nome']);
            $nuovi = versioni_dati_attuali($ambito, $id);
        } elseif ($ambito === 'stile') {
            require_once __DIR__ . '/design.php';
            design_salva($dati, $autore);
            $nome = 'Stile del sito'; $nuovi = versioni_dati_attuali($ambito, 0); $id = 0;
        } elseif ($ambito === 'menu') {
            require_once __DIR__ . '/menu-nav.php';
            if (menu_leggi($id)) {
                menu_salva($id, (string)($dati['nome'] ?? ''), (string)($dati['posizione'] ?? ''), (array)($dati['voci'] ?? []));
            } else {
                // Il menu era stato eliminato: rinasce con lo stesso id, così i widget che lo mostravano lo ritrovano.
                db()->prepare('INSERT INTO ' . tbl('menu') . ' (id, nome, posizione, voci, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())')
                    ->execute([$id, mb_substr((string)($dati['nome'] ?? 'Menu'), 0, 100) ?: 'Menu', array_key_exists((string)($dati['posizione'] ?? ''), MENU_POSIZIONI) ? (string)$dati['posizione'] : '', versioni_json(menu_pulisci_voci((array)($dati['voci'] ?? [])))]);
            }
            $nome = (string)($dati['nome'] ?? $v['oggetto_nome']);
            $nuovi = ['nome' => (string)($dati['nome'] ?? ''), 'posizione' => (string)($dati['posizione'] ?? ''), 'voci' => menu_pulisci_voci((array)($dati['voci'] ?? []))];
        } elseif ($ambito === 'tema') {
            require_once __DIR__ . '/editor-temi.php';
            editor_temi_schema();
            $nomeTema = editor_tema_nome_valido((string)($dati['nome'] ?? ''));
            $json = json_encode(editor_tema_pulisci((array)($dati['valori'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $st = db()->prepare('UPDATE ' . tbl('editor_temi') . ' SET nome = ?, valori = ?, aggiornato = NOW() WHERE id = ?');
            $st->execute([$nomeTema, $json, $id]);
            if (!$st->rowCount() && !db()->query('SELECT id FROM ' . tbl('editor_temi') . ' WHERE id = ' . (int)$id)->fetchColumn()) {
                db()->prepare('INSERT INTO ' . tbl('editor_temi') . ' (id, nome, valori, creato, aggiornato) VALUES (?, ?, ?, NOW(), NOW())')->execute([$id, $nomeTema, $json]);
            }
            $nome = $nomeTema; $nuovi = versioni_dati_attuali($ambito, $id);
        } elseif ($ambito === 'impostazioni') {
            foreach ($dati as $k => $x) if (is_string($k) && str_starts_with($k, 'editor_') && is_scalar($x)) save_setting($k, (string)$x);
            $nome = 'Impostazioni dell\'editor'; $nuovi = versioni_dati_attuali($ambito, 0); $id = 0;
        } else {
            throw new RuntimeException('Ambito sconosciuto: ' . $ambito);
        }
    } finally {
        versioni_silenzio(false);
    }
    $nuovo = is_array($nuovi) ? editor_versione_salva($ambito, $id, $nome, $nuovi, $nota, ['pubblicata' => ($dati['stato'] ?? '') === 'pubblicata' || !empty($v['pubblicata'])]) : 0;
    return $nuovo ? "Ripristinata la versione $versioneId: è la versione $nuovo." : "La versione $versioneId era già uguale a quella di adesso: niente da cambiare.";
}

/** La scheda classica di un contenuto torna com'era (stesse regole di contenuti_ripristina_revisione: stato, data e ora restano). */
function versioni_ripristina_scheda(string $tipo, int $id, array $dati): void {
    require_once __DIR__ . '/contenuti.php';
    $t = contenuti_tipo($tipo);
    $adesso = contenuti_leggi($tipo, $id);
    if (!$adesso) throw new RuntimeException('Contenuto non trovato.');
    $colonne = db()->query('SHOW COLUMNS FROM ' . tbl($t['tabella']))->fetchAll(PDO::FETCH_COLUMN);
    $set = []; $p = [':id' => $id];
    foreach ($dati as $k => $x) {
        if ($k === 'id' || !is_string($k) || !in_array($k, $colonne, true) || in_array($k, ['struttura', 'struttura_bozza', 'struttura_aggiornata', 'cestino', 'wp_post_id', 'wp_hash', 'wp_id', 'created_at', 'updated_at', 'status', 'publish_date', 'publish_time', 'publish_at'], true)) continue;
        if ($k === 'slug' && !contenuti_slug_libero($tipo, (string)$x, $id)) continue;
        if (is_array($x)) continue;
        $set[] = "`$k` = :$k";
        $p[":$k"] = $x;
    }
    if ($set) db()->prepare('UPDATE ' . tbl($t['tabella']) . ' SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($p);
    if (isset($p[':slug']) && (string)$p[':slug'] !== $adesso['slug'] && contenuti_stato($tipo, $adesso) === 'pubblicato') {
        contenuti_redirect(contenuti_percorso($tipo, $adesso), contenuti_percorso($tipo, ['slug' => (string)$p[':slug']] + $adesso));
    }
}

// ------------------------------------------------------------
// Confronto leggibile
// ------------------------------------------------------------

/**
 * La differenza fra due versioni, leggibile da chi non programma. $a e $b: id di versione, oppure un array
 * ['ambito' => ..., 'oggetto_id' => ..., 'dati' => ...] (per esempio lo stato di adesso). $a è "prima", $b è "dopo".
 * Ritorna ['riassunto' => 'frase breve', 'righe' => [...], 'uguali' => bool].
 * Una riga: ['tipo' => aggiunto|tolto|spostato|modificato, 'cosa' => 'Widget Titolo «Chi sono»', 'dove' => 'Sezione 2 › Colonna 1',
 *            'campi' => [['etichetta' => ..., 'prima' => ..., 'dopo' => ...]]].
 */
function editor_versioni_confronta($a, $b): array {
    $va = is_array($a) ? $a : editor_versione((int)$a);
    $vb = is_array($b) ? $b : editor_versione((int)$b);
    if (!$va || !$vb) throw new RuntimeException('Versione non trovata.');
    $da = is_array($va['dati'] ?? null) ? $va['dati'] : [];
    $db_ = is_array($vb['dati'] ?? null) ? $vb['dati'] : [];
    $righe = [];
    $conta = ['aggiunto' => [], 'tolto' => [], 'spostato' => [], 'modificato' => []];
    $alberoA = $da['albero'] ?? null; $alberoB = $db_['albero'] ?? null;
    if (is_array($alberoA) || is_array($alberoB)) {
        foreach (versioni_confronta_alberi(is_array($alberoA) ? $alberoA : ['elementi' => []], is_array($alberoB) ? $alberoB : ['elementi' => []]) as $r) {
            $righe[] = $r;
            $conta[$r['tipo']][] = $r['classe'];
        }
        unset($da['albero'], $db_['albero']);
    }
    // Il resto chiave per chiave (stile, modelli, menu, temi, impostazioni, header e footer di un contenuto).
    $campi = versioni_confronta_chiavi($da, $db_, (string)($va['ambito'] ?? $vb['ambito'] ?? ''));
    if ($campi) {
        $righe[] = ['tipo' => 'modificato', 'classe' => 'impostazione', 'cosa' => versioni_titolo_chiavi((string)($va['ambito'] ?? $vb['ambito'] ?? '')), 'dove' => '', 'campi' => $campi];
        $conta['modificato'][] = 'impostazione';
        // Le impostazioni cambiate si contano una per una nel riassunto.
        for ($i = 1; $i < count($campi); $i++) $conta['modificato'][] = 'impostazione';
    }
    return ['riassunto' => versioni_riassunto($conta), 'righe' => $righe, 'uguali' => !$righe];
}

/** "3 widget modificati, 1 sezione aggiunta, 2 impostazioni cambiate". Vuoto se niente è cambiato. */
function versioni_riassunto(array $conta): string {
    $nomi = ['widget' => ['widget', 'widget', 'm'], 'sezione' => ['sezione', 'sezioni', 'f'], 'colonna' => ['colonna', 'colonne', 'f'], 'impostazione' => ['impostazione', 'impostazioni', 'f']];
    $verbi = ['aggiunto' => ['aggiunto', 'aggiunta', 'aggiunti', 'aggiunte'], 'tolto' => ['tolto', 'tolta', 'tolti', 'tolte'], 'spostato' => ['spostato', 'spostata', 'spostati', 'spostate'], 'modificato' => ['modificato', 'modificata', 'modificati', 'modificate']];
    $parti = [];
    foreach (['modificato', 'aggiunto', 'tolto', 'spostato'] as $az) {
        foreach (array_count_values($conta[$az] ?? []) as $classe => $n) {
            [$uno, $tanti, $g] = $nomi[$classe] ?? [$classe, $classe, 'm'];
            $verbo = $az === 'modificato' && $classe === 'impostazione' ? ($n === 1 ? 'cambiata' : 'cambiate') : $verbi[$az][($n === 1 ? 0 : 2) + ($g === 'f' ? 1 : 0)];
            $parti[] = $n . ' ' . ($n === 1 ? $uno : $tanti) . ' ' . $verbo;
        }
    }
    return implode(', ', $parti);
}

/** Tutti gli elementi di un albero in piano: id => [tipo, impostazioni, genitore, indice, dove]. */
function versioni_appiattisci_albero(array $albero): array {
    $out = [];
    $giro = function (array $lista, string $genitore, string $dove) use (&$giro, &$out) {
        $nSez = 0; $nCol = 0;
        foreach ($lista as $i => $x) {
            if (!is_array($x) || empty($x['id'])) continue;
            $tipo = (string)($x['tipo'] ?? 'widget');
            $qui = $dove;
            if ($tipo === 'sezione') { $nSez++; $qui = 'Sezione ' . $nSez; }
            elseif ($tipo === 'colonna') { $nCol++; $qui = ($dove !== '' ? $dove . ' › ' : '') . 'Colonna ' . $nCol; }
            $out[(string)$x['id']] = ['tipo' => $tipo, 'impostazioni' => is_array($x['impostazioni'] ?? null) ? $x['impostazioni'] : [], 'genitore' => $genitore, 'indice' => (int)$i, 'dove' => $tipo === 'sezione' || $tipo === 'colonna' ? $dove : $qui, 'nome_dove' => $qui];
            if (!empty($x['figli']) && is_array($x['figli'])) $giro($x['figli'], (string)$x['id'], $qui);
        }
    };
    $giro($albero['elementi'] ?? [], '', '');
    return $out;
}

/** Il confronto di due alberi: elementi aggiunti, tolti, spostati, e per quelli rimasti i controlli cambiati. */
function versioni_confronta_alberi(array $a, array $b): array {
    require_once __DIR__ . '/editor.php';
    $pa = versioni_appiattisci_albero($a); $pb = versioni_appiattisci_albero($b);
    $def = editor_definizioni();
    $righe = [];
    $classe = fn(string $tipo) => in_array($tipo, ['sezione', 'colonna'], true) ? $tipo : 'widget';
    $riga = function (string $azione, array $el, array $campi = []) use ($def, $classe): array {
        return ['tipo' => $azione, 'classe' => $classe($el['tipo']), 'cosa' => versioni_nome_elemento($el, $def), 'dove' => $el['dove'], 'campi' => $campi];
    };
    foreach ($pb as $id => $el) if (!isset($pa[$id])) $righe[] = $riga('aggiunto', $el, versioni_campi_elemento($el, [], $def));
    foreach ($pa as $id => $el) if (!isset($pb[$id])) $righe[] = $riga('tolto', $el);
    foreach ($pa as $id => $ea) {
        if (!isset($pb[$id])) continue;
        $eb = $pb[$id];
        // Spostato: cambia il genitore, oppure l'ordine fra i fratelli rimasti.
        $spostato = $ea['genitore'] !== $eb['genitore'];
        if (!$spostato) {
            $fratelliA = array_values(array_filter(array_keys($pa), fn($k) => $pa[$k]['genitore'] === $ea['genitore'] && isset($pb[$k])));
            $fratelliB = array_values(array_filter(array_keys($pb), fn($k) => $pb[$k]['genitore'] === $eb['genitore'] && isset($pa[$k])));
            usort($fratelliA, fn($x, $y) => $pa[$x]['indice'] <=> $pa[$y]['indice']);
            usort($fratelliB, fn($x, $y) => $pb[$x]['indice'] <=> $pb[$y]['indice']);
            $spostato = array_search($id, $fratelliA, true) !== array_search($id, $fratelliB, true);
        }
        if ($spostato) {
            $r = $riga('spostato', $eb);
            if ($ea['nome_dove'] !== $eb['nome_dove'] || $ea['dove'] !== $eb['dove']) $r['campi'][] = ['etichetta' => 'Posizione', 'prima' => $ea['dove'] !== '' ? $ea['dove'] : 'in alto', 'dopo' => $eb['dove'] !== '' ? $eb['dove'] : 'in alto'];
            $righe[] = $r;
        }
        $campi = versioni_campi_elemento($eb, $ea['impostazioni'], $def);
        if ($campi) $righe[] = $riga('modificato', $eb, $campi);
    }
    return $righe;
}

/** Il nome di un elemento per una persona: "Widget Titolo «Chi sono»", "Sezione 2", "Colonna 1". */
function versioni_nome_elemento(array $el, array $def): string {
    $tipo = $el['tipo'];
    if ($tipo === 'sezione' || $tipo === 'colonna') return $el['nome_dove'] !== '' ? $el['nome_dove'] : ucfirst($tipo);
    $et = (string)($def[$tipo]['etichetta'] ?? ucfirst(str_replace('_', ' ', $tipo)));
    $testo = '';
    foreach (['testo', 'titolo', 'occhiello', 'contenuto', 'etichetta', 'nome', 'codice', 'frase', 'domanda'] as $k) {
        $v = $el['impostazioni'][$k] ?? null;
        if (is_string($v) && trim(strip_tags($v)) !== '') { $testo = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); break; }
    }
    if ($testo !== '') $testo = ' «' . (mb_strlen($testo) > 48 ? mb_substr($testo, 0, 47) . '…' : $testo) . '»';
    return 'Widget ' . $et . $testo;
}

/** I controlli di un elemento che cambiano fra $prima e le impostazioni di $el, con etichetta e valori in parole. */
function versioni_campi_elemento(array $el, array $prima, array $def): array {
    $dopo = $el['impostazioni'];
    $ctl = [];
    foreach ($def[$el['tipo']]['controlli'] ?? [] as $c) $ctl[$c['nome']] = $c;
    $campi = [];
    foreach (array_unique(array_merge(array_keys($prima), array_keys($dopo))) as $k) {
        $x = $prima[$k] ?? null; $y = $dopo[$k] ?? null;
        if (versioni_json(['v' => versioni_canonico($x)]) === versioni_json(['v' => versioni_canonico($y)])) continue;
        $c = $ctl[$k] ?? null;
        $et = $c ? (string)$c['etichetta'] : versioni_etichetta_chiave((string)$k);
        if ($c && !empty($c['sezione']) && $c['sezione'] !== $et) $et = $c['sezione'] . ' › ' . $et;
        if ($c && ($c['scheda'] ?? 'contenuto') !== 'contenuto') $et = ($c['scheda'] === 'stile' ? 'Stile' : 'Avanzate') . ' › ' . $et;
        $campi[] = ['etichetta' => $et, 'prima' => versioni_formatta_valore($c, $x), 'dopo' => versioni_formatta_valore($c, $y)];
    }
    return $campi;
}

/** Un valore in parole. $ctl: la definizione del controllo se la conosciamo (opzioni, tipo). */
function versioni_formatta_valore(?array $ctl, $v): string {
    if ($v === null) return '(non impostato)';
    if (is_bool($v)) return $v ? 'Sì' : 'No';
    if (is_int($v) || is_float($v)) return (string)$v;
    if (is_string($v)) {
        if ($v === '') return '(vuoto)';
        if ($ctl && ($ctl['tipo'] ?? '') === 'scelta' && isset($ctl['opzioni'][$v])) return (string)$ctl['opzioni'][$v];
        if (preg_match('/^globale:(?:font-)?([a-z0-9-]+)$/', $v, $m)) return 'globale: ' . $m[1];
        $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($t === '' && $v !== '') $t = trim($v);
        return mb_strlen($t) > 160 ? mb_substr($t, 0, 159) . '…' : $t;
    }
    if (!is_array($v)) return (string)$v;
    if (!$v) return '(vuoto)';
    $senza = $ctl ? ['stati' => false, 'responsive' => false] + $ctl : null;
    if (array_key_exists('normale', $v) || array_key_exists('hover', $v)) {
        $p = [];
        if (array_key_exists('normale', $v)) $p[] = 'normale: ' . versioni_formatta_valore($senza, $v['normale']);
        if (array_key_exists('hover', $v) && $v['hover'] !== null && $v['hover'] !== '' && $v['hover'] !== []) $p[] = 'al passaggio: ' . versioni_formatta_valore($senza, $v['hover']);
        return implode('; ', $p);
    }
    if (array_intersect(array_keys($v), ['d', 't', 'm']) && !array_diff(array_keys($v), ['d', 't', 'm'])) {
        $p = [];
        foreach (['d' => 'desktop', 't' => 'tablet', 'm' => 'telefono'] as $k => $n) if (array_key_exists($k, $v)) $p[] = $n . ' ' . versioni_formatta_valore($senza, $v[$k]);
        return implode(', ', $p);
    }
    if (array_key_exists('v', $v) && array_key_exists('u', $v) && count($v) <= 3) return ($v['v'] === '' || $v['v'] === null) ? '(vuoto)' : $v['v'] . $v['u'];
    if (array_key_exists('sopra', $v) && array_key_exists('sinistra', $v)) {
        $u = (string)($v['u'] ?? 'px');
        $lati = array_map(fn($k) => ($v[$k] === '' || $v[$k] === null) ? '–' : $v[$k], ['sopra', 'destra', 'sotto', 'sinistra']);
        return implode(' ', $lati) . ' ' . $u;
    }
    if (array_keys($v) === range(0, count($v) - 1)) {
        if (!array_filter($v, 'is_array')) return implode(', ', array_map(fn($x) => versioni_formatta_valore(null, $x), $v));
        $n = count($v);
        $primo = '';
        foreach (['testo', 'titolo', 'etichetta', 'nome', 'voce', 'domanda', 'url'] as $k) if (isset($v[0][$k]) && is_string($v[0][$k]) && $v[0][$k] !== '') { $primo = versioni_formatta_valore(null, $v[0][$k]); break; }
        return $n . ($n === 1 ? ' voce' : ' voci') . ($primo !== '' ? ' (la prima: ' . $primo . ')' : '');
    }
    $p = [];
    foreach ($v as $k => $x) {
        if ($x === '' || $x === null || $x === [] || $x === false) continue;
        $p[] = versioni_etichetta_chiave((string)$k) . ' ' . versioni_formatta_valore(null, $x);
        if (count($p) >= 8) { $p[] = '…'; break; }
    }
    return $p ? implode(', ', $p) : '(vuoto)';
}

/** Il confronto chiave per chiave di due array annidati, con le etichette italiane. */
function versioni_confronta_chiavi(array $a, array $b, string $ambito = ''): array {
    $fa = versioni_appiattisci($a); $fb = versioni_appiattisci($b);
    $campi = [];
    foreach (array_unique(array_merge(array_keys($fa), array_keys($fb))) as $k) {
        $x = array_key_exists($k, $fa) ? $fa[$k] : null; $y = array_key_exists($k, $fb) ? $fb[$k] : null;
        if (versioni_json(['v' => $x]) === versioni_json(['v' => $y])) continue;
        if (($x === '' || $x === null) && ($y === '' || $y === null)) continue;
        $campi[] = ['etichetta' => versioni_etichetta_percorso($k), 'prima' => versioni_formatta_chiave($k, $x, $ambito), 'dopo' => versioni_formatta_chiave($k, $y, $ambito)];
    }
    return $campi;
}

/** Un array annidato in piano: "colori.sistema.primario" => valore. Le liste di scalari restano intere; le liste di oggetti si indicizzano per id (o nome). */
function versioni_appiattisci(array $v, string $prefisso = ''): array {
    $out = [];
    foreach ($v as $k => $x) {
        $chiave = $prefisso === '' ? (string)$k : $prefisso . '.' . $k;
        if (is_array($x) && $x !== [] && array_keys($x) === range(0, count($x) - 1) && array_filter($x, 'is_array') === $x) {
            // Lista di oggetti (voci di menu, colori personalizzati, condizioni): una voce per riga, con il suo nome.
            $usati = [];
            foreach ($x as $i => $o) {
                $nome = (string)($o['nome'] ?? $o['etichetta'] ?? $o['titolo'] ?? $o['dove'] ?? $o['id'] ?? $i);
                $id = (string)($o['id'] ?? $nome);
                $seg = '#' . $id . '|' . $nome;
                if (isset($usati[$seg])) $seg .= '|' . $i;
                $usati[$seg] = true;
                foreach (versioni_appiattisci($o, $chiave . '.' . $seg) as $kk => $vv) $out[$kk] = $vv;
            }
            if (!$x) $out[$chiave] = [];
        } elseif (is_array($x) && $x !== [] && array_keys($x) !== range(0, count($x) - 1) && !array_key_exists('v', $x) && !array_key_exists('sopra', $x)) {
            foreach (versioni_appiattisci($x, $chiave) as $kk => $vv) $out[$kk] = $vv;
        } else {
            $out[$chiave] = $x;
        }
    }
    return $out;
}

/** Il valore di una chiave appiattita in parole; header_id e footer_id dicono il nome del modello. */
function versioni_formatta_chiave(string $k, $v, string $ambito): string {
    $ultimo = (string)substr(strrchr('.' . $k, '.'), 1);
    if (in_array($ultimo, ['header_id', 'footer_id'], true) && ($v === null || is_numeric($v))) {
        $n = (int)$v;
        if ($v === null) return '(non impostato)';
        if ($n === 0) return 'Predefinito';
        if ($n < 0) return 'Nessuno';
        try { require_once __DIR__ . '/modelli.php'; $m = modelli_tutti()[$n] ?? null; return $m ? $m['nome'] : "modello n. $n"; } catch (Throwable $e) { return "modello n. $n"; }
    }
    if ($ultimo === 'stato' && is_string($v)) return ['bozza' => 'Bozza', 'pubblicata' => 'Pubblicata', 'senza editor' => 'Senza editor (testo della scheda)'][$v] ?? $v;
    if ($ultimo === 'status' && is_string($v)) return ['published' => 'Pubblicato', 'draft' => 'Bozza'][$v] ?? $v;
    if ($ultimo === 'posizione' && is_string($v) && defined('MENU_POSIZIONI') && isset(MENU_POSIZIONI[$v])) return MENU_POSIZIONI[$v];
    if ($ultimo === 'tipo' && is_string($v) && defined('MENU_TIPI_VOCE') && isset(MENU_TIPI_VOCE[$v])) return MENU_TIPI_VOCE[$v];
    if ($ultimo === 'escludi') return $v ? 'escludi' : 'includi';
    return versioni_formatta_valore(null, $v);
}

/** "colori.sistema.primario" → "Colori › Colori di sistema › Primario". Le voci di lista (#id|nome) mostrano il nome. */
function versioni_etichetta_percorso(string $k): string {
    $parti = [];
    foreach (explode('.', $k) as $seg) {
        if (str_starts_with($seg, '#')) { $p = explode('|', substr($seg, 1)); $parti[] = '«' . ($p[1] ?? $p[0]) . '»'; continue; }
        $parti[] = versioni_etichetta_chiave($seg);
    }
    return implode(' › ', $parti);
}

/** L'etichetta italiana di una chiave; se non la conosciamo, la chiave in chiaro. */
function versioni_etichetta_chiave(string $k): string {
    static $e = null;
    if ($e === null) $e = [
        'colori' => 'Colori', 'font' => 'Caratteri', 'tipografia' => 'Tipografia', 'pulsanti' => 'Pulsanti', 'immagini' => 'Immagini', 'campi' => 'Campi dei moduli', 'layout' => 'Layout',
        'sfondo' => 'Sfondo', 'sito' => 'Identità del sito', 'css' => 'CSS personalizzato', 'sistema' => 'Colori di sistema', 'personalizzati' => 'Personalizzati',
        'primario' => 'Primario', 'secondario' => 'Secondario', 'testo' => 'Testo', 'accento' => 'Accento', 'corpo' => 'Corpo del testo', 'paragrafo' => 'Paragrafo', 'link' => 'Link', 'link_hover' => 'Link al passaggio',
        'h1' => 'Titolo H1', 'h2' => 'Titolo H2', 'h3' => 'Titolo H3', 'h4' => 'Titolo H4', 'h5' => 'Titolo H5', 'h6' => 'Titolo H6',
        'famiglia' => 'Carattere', 'dimensione' => 'Dimensione', 'peso' => 'Peso', 'stile' => 'Stile', 'trasformazione' => 'Maiuscole e minuscole', 'decorazione' => 'Decorazione', 'altezza_riga' => 'Altezza della riga',
        'spaziatura' => 'Spaziatura', 'spaziatura_lettere' => 'Spaziatura delle lettere', 'spaziatura_parole' => 'Spaziatura delle parole', 'colore' => 'Colore', 'spazio_paragrafi' => 'Spazio fra i paragrafi', 'ombra_testo' => 'Ombra del testo',
        'normale' => 'Normale', 'hover' => 'Al passaggio del mouse', 'bordo_colore' => 'Colore del bordo', 'ombra' => 'Ombra', 'bordo_tipo' => 'Tipo di bordo', 'bordo_larghezza' => 'Spessore del bordo', 'raggio' => 'Angoli arrotondati',
        'padding' => 'Spazio interno', 'opacita' => 'Opacità', 'filtri' => 'Filtri', 'sfocatura' => 'Sfocatura', 'luminosita' => 'Luminosità', 'contrasto' => 'Contrasto', 'saturazione' => 'Saturazione', 'tonalita' => 'Tonalità',
        'transizione' => 'Transizione', 'etichetta' => 'Etichetta', 'campo' => 'Campo', 'fuoco' => 'Campo attivo', 'contorno' => 'Contorno', 'larghezza' => 'Larghezza', 'spazio_widget' => 'Spazio fra i widget',
        'spazio_colonne' => 'Spazio fra le colonne', 'tablet' => 'Soglia tablet', 'telefono' => 'Soglia telefono', 'immagine' => 'Immagine', 'posizione' => 'Posizione', 'ripetizione' => 'Ripetizione', 'allegato' => 'Scorrimento',
        'velo' => 'Velo', 'sfumature_sito' => 'Sfumature del sito', 'nome' => 'Nome', 'sottotitolo' => 'Sottotitolo', 'descrizione' => 'Descrizione', 'logo' => 'Logo', 'favicon' => 'Icona del sito', 'valore' => 'Valore',
        'id' => 'Id', 'voci' => 'Voci', 'url' => 'Indirizzo', 'rif' => 'Riferimento', 'tipo' => 'Tipo', 'nuova' => 'Apri in una scheda nuova', 'classi' => 'Classi CSS', 'figli' => 'Sottovoci',
        'condizioni' => 'Condizioni', 'priorita' => 'Priorità', 'predefinito' => 'Predefinito', 'status' => 'Stato', 'stato' => 'Stato nell\'editor', 'header_id' => 'Header', 'footer_id' => 'Footer', 'albero' => 'Struttura',
        'valori' => 'Valori', 'dove' => 'Dove', 'escludi' => 'Includi o escludi', 'd' => 'Desktop', 't' => 'Tablet', 'm' => 'Telefono', 'v' => 'Valore', 'u' => 'Unità',
        'sopra' => 'Sopra', 'destra' => 'Destra', 'sotto' => 'Sotto', 'sinistra' => 'Sinistra', 'legati' => 'Lati legati',
        'testata' => 'Testata', 'testata_colore' => 'Colore della testata', 'testata_colore2' => 'Secondo colore della testata', 'testata_testo' => 'Testo della testata', 'occhiello_colore' => 'Colore dell\'occhiello',
        'corpo_colore' => 'Colore della card', 'bordo_spessore' => 'Spessore del bordo', 'angoli' => 'Angoli', 'testo_dimensione' => 'Dimensione del testo', 'carattere' => 'Carattere', 'colore_libero' => 'Colore libero',
        'scheda' => 'Scheda', 'title' => 'Titolo', 'slug' => 'Indirizzo', 'content' => 'Testo', 'html' => 'Testo', 'excerpt' => 'Estratto', 'author' => 'Autore', 'seo_title' => 'Titolo per Google', 'seo_description' => 'Descrizione per Google',
        'meta_description' => 'Meta description', 'robots' => 'Indicizzazione', 'tldr' => 'In breve', 'faq' => 'Domande frequenti', 'immagine_url' => 'Immagine in evidenza', 'featured_image' => 'Immagine in evidenza', 'featured_image_alt' => 'Testo alternativo dell\'immagine',
        'category' => 'Categoria', 'categorie' => 'Categorie', 'tags' => 'Tag', 'tag_slugs' => 'Tag', 'keyword_target' => 'Parola chiave', 'keywords_secondarie' => 'Parole chiave secondarie', 'cta' => 'Invito', 'pillar_ref' => 'Pillar di riferimento',
        'pillar_title' => 'Titolo del pillar', 'author_quote' => 'Autore della frase', 'publish_at' => 'Data di uscita', 'noindex' => 'Nascosto a Google', 'content_format' => 'Formato del testo', 'blocks' => 'Blocchi', 'servizio' => 'Servizio',
        'editor_versioni_limite' => 'Versioni da tenere per oggetto', 'editor_colonne_max' => 'Colonne massime', 'editor_animazioni' => 'Animazioni', 'editor_cartella_media' => 'Cartella delle immagini', 'editor_chi_pubblica' => 'Chi può pubblicare',
    ];
    if (isset($e[$k])) return $e[$k];
    if (str_starts_with($k, 'editor_')) return ucfirst(str_replace('_', ' ', substr($k, 7)));
    return ucfirst(str_replace('_', ' ', $k));
}

/** Il titolo della riga "chiave per chiave" secondo l'ambito. */
function versioni_titolo_chiavi(string $ambito): string {
    if (str_starts_with($ambito, 'contenuto:')) return 'Impostazioni del contenuto';
    return ['modello' => 'Impostazioni del modello', 'stile' => 'Stile del sito', 'menu' => 'Menu', 'tema' => 'Tema', 'impostazioni' => 'Impostazioni dell\'editor'][$ambito] ?? 'Impostazioni';
}

/** Il riassunto di una versione rispetto alla precedente dello stesso oggetto ('' se è la prima o niente è cambiato). */
function versioni_riassunto_di(array $v): string {
    try {
        $prec = versioni_precedente($v);
        if (!$prec) return 'prima versione';
        $piena = isset($v['dati']) && is_array($v['dati']) ? $v : editor_versione((int)$v['id']);
        return editor_versioni_confronta($prec, $piena)['riassunto'] ?: 'nessuna differenza';
    } catch (Throwable $e) {
        return '';
    }
}

/** Il confronto in HTML, per il pannello e per l'editor. */
function versioni_confronto_html(array $diff, string $etA = 'Prima', string $etB = 'Dopo'): string {
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    if (!$diff['righe']) return '<p class="vs-uguali">Nessuna differenza fra le due versioni.</p>';
    $out = '<div class="vs-confronto"><p class="vs-riassunto">' . $h($diff['riassunto']) . '</p>';
    $parole = ['aggiunto' => 'Aggiunto', 'tolto' => 'Tolto', 'spostato' => 'Spostato', 'modificato' => 'Modificato'];
    foreach ($diff['righe'] as $r) {
        $out .= '<div class="vs-riga vs-' . $h($r['tipo']) . '"><div class="vs-testa"><span class="vs-tag">' . $parole[$r['tipo']] . '</span> <b>' . $h($r['cosa']) . '</b>' . ($r['dove'] !== '' ? ' <small>in ' . $h($r['dove']) . '</small>' : '') . '</div>';
        if (!empty($r['campi'])) {
            $out .= '<table class="vs-campi"><thead><tr><th>Cosa</th><th>' . $h($etA) . '</th><th>' . $h($etB) . '</th></tr></thead><tbody>';
            foreach ($r['campi'] as $c) $out .= '<tr><td>' . $h($c['etichetta']) . '</td><td class="vs-prima">' . $h($c['prima']) . '</td><td class="vs-dopo">' . $h($c['dopo']) . '</td></tr>';
            $out .= '</tbody></table>';
        }
        $out .= '</div>';
    }
    return $out . '</div>';
}

/** Il CSS del confronto (lo stesso nel pannello e nell'editor). */
function versioni_confronto_css(): string {
    return '.vs-confronto{font-size:13px}.vs-riassunto{font-weight:600;margin:0 0 .6rem}.vs-uguali{color:#6b7280;font-size:13px}
.vs-riga{border:1px solid #e5e7eb;border-left-width:4px;border-radius:8px;padding:.5rem .7rem;margin:0 0 .5rem;background:#fff;color:#222}
.vs-aggiunto{border-left-color:#16a34a}.vs-tolto{border-left-color:#dc2626}.vs-spostato{border-left-color:#d97706}.vs-modificato{border-left-color:#2563eb}
.vs-testa small{color:#6b7280}.vs-tag{display:inline-block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:1px 6px;border-radius:4px;background:#f3f4f6;color:#374151;margin-right:4px}
.vs-aggiunto .vs-tag{background:#dcfce7;color:#166534}.vs-tolto .vs-tag{background:#fee2e2;color:#991b1b}.vs-spostato .vs-tag{background:#fef3c7;color:#92400e}.vs-modificato .vs-tag{background:#dbeafe;color:#1e40af}
.vs-campi{width:100%;border-collapse:collapse;margin-top:.4rem;font-size:12.5px;table-layout:fixed}.vs-campi th{text-align:left;font-weight:600;color:#6b7280;padding:2px 6px;border-bottom:1px solid #e5e7eb}
.vs-campi td{padding:3px 6px;vertical-align:top;border-bottom:1px solid #f3f4f6;word-break:break-word}.vs-campi th:first-child,.vs-campi td:first-child{width:34%}
.vs-prima{color:#991b1b;background:#fff5f5}.vs-dopo{color:#166534;background:#f3fbf5}';
}

/** "26/10/2026 alle 14:05" da una data del database. */
function versioni_data_breve(string $d): string {
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) . ' alle ' . date('H:i', $t) : $d;
}
