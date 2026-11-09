<?php
/**
 * 2.28.0: le pagine per zona ("Idraulico a Casalecchio di Reno"), la seconda fase della SEO geografica (richiesta di Danilo:
 * "le pagine per zona, già in programma: è dove la SEO locale si vince").
 *
 * Come funziona. Dal pannello (SEO e AEO > Zone, solo amministratore) si sceglie un servizio, le zone (una per riga, precompilate
 * dall'unione delle zone servite delle sedi) e la sede di riferimento; "Crea le bozze" fa nascere una pagina vera dell'editor per
 * ogni zona, in BOZZA, mai pubblicata da qui. Ogni bozza ha l'apertura (occhiello = la zona, titolo "%servizio% a %zona%"), tre
 * riquadri guidati (tre widget Testo con un segnaposto [DA SCRIVERE] ciascuno e una riga di aiuto: cosa fai in quella zona, un
 * caso vero, come si arriva), il widget Altre zone, Dati dell'attività e la Barra contatti letti dalla sede. Il blocco guidato
 * esiste per impedire pagine vuote tutte uguali: finché un segnaposto resta, la pagina NON si pubblica da nessuna strada
 * (editor, scheda del contenuto, azione in blocco, impostazioni dell'editor, ripristino di una versione): zone_blocco_pubblicazione().
 *
 * Il registro delle pagine per zona: fino alla 2.35.0 stava nel gruppo "zone" della configurazione SEO (l'impostazione seo_config,
 * un JSON letto a ogni richiesta) con un tetto di 200 voci; dalla 2.36.0 sta nella tabella <prefisso>zone (zone_installa(), nata
 * al primo uso), senza tetto. Ogni voce: pagina (id della tabella pages, unico), servizio, zona, sede (id, "principale" o vuoto),
 * servizio_pagina ("pagine:12", la pagina del servizio a cui collegare le briciole, facoltativa), creata. Le pagine sparite si
 * ignorano alla lettura (JOIN con pages) e il pannello le toglie dal registro. Il gruppo "zone" della configurazione tiene i
 * modelli del titolo e della descrizione, il segno della migrazione ("migrato") e quante voci ha la tabella ("voci"): con voci a 0
 * il sito non fa nessuna query sulle zone (e non crea la tabella). Un registro della 2.35.0 rimasto nel JSON passa nella tabella
 * una volta sola (zone_migra()).
 *
 * Dati per Google (includes/sito-seo.php): nella pagina per zona un nodo Service con name "%servizio% a %zona%", areaServed la
 * zona (City o AdministrativeArea, la stessa regola delle sedi), provider la LocalBusiness della sede di riferimento (o
 * l'Organizzazione), mainEntity della WebPage e le briciole Home > pagina del servizio > zona. Senza pagine per zona il sito esce
 * identico a prima, al byte.
 */

require_once __DIR__ . '/sedi.php';

/** Quante zone in un giro di "Crea le bozze" (2.36.0: erano 30; il totale non ha più un tetto). */
const ZONE_GIRO_MAX = 100;
/** Quanti secondi di lavoro per pezzo: oltre, il giro si ferma e la pagina continua da sola con le zone che restano. */
const ZONE_PEZZO_SECONDI = 15;
/** I secondi per pezzo davvero usati: CMS_ZONE_PEZZO_SECONDI nel config li cambia (le prove lo mettono a 0.2 per vedere i pezzi). */
function zone_pezzo_secondi(): float {
    return defined('CMS_ZONE_PEZZO_SECONDI') && is_numeric(CMS_ZONE_PEZZO_SECONDI) ? max(0.05, (float)CMS_ZONE_PEZZO_SECONDI) : (float)ZONE_PEZZO_SECONDI;
}
/** Quante righe per pagina nell'elenco del pannello. */
const ZONE_PER_PAGINA = 50;
/** Oltre quante pagine pubblicate di un servizio il pannello consiglia di limitare il widget Altre zone. */
const ZONE_AVVISO_WIDGET = 30;
/** Il segnaposto dei riquadri guidati: finché c'è, la pagina resta bozza. */
const ZONE_SEGNAPOSTO = '[DA SCRIVERE]';
const ZONE_TITOLO_PREDEFINITO = '%servizio% a %zona%';
const ZONE_DESCRIZIONE_PREDEFINITA = '%servizio% a %zona%: interventi, tempi e contatti della sede più vicina. Scrivici o chiama per un preventivo.';

// ------------------------------------------------------------
// La tabella del registro
// ------------------------------------------------------------

/**
 * Il gruppo "zone" della configurazione SEO al valore di partenza (seo_predefiniti() lo usa). "elenco" è il registro della 2.35.0
 * (vuoto dopo la migrazione), "migrato" il segno che la migrazione è fatta, "voci" quante righe ha la tabella.
 */
function zone_predefiniti(): array {
    return ['elenco' => [], 'titolo' => ZONE_TITOLO_PREDEFINITO, 'descrizione' => ZONE_DESCRIZIONE_PREDEFINITA, 'migrato' => 0, 'voci' => 0];
}

/** 2.36.0: la tabella del registro, nata al primo uso (mai da una visita del sito senza zone: la chiamano solo le scritture e la migrazione). */
function zone_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('zone') . " (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pagina` INT UNSIGNED NOT NULL,
        `servizio` VARCHAR(255) NOT NULL DEFAULT '',
        `zona` VARCHAR(255) NOT NULL DEFAULT '',
        `sede` VARCHAR(60) NOT NULL DEFAULT '',
        `servizio_pagina` VARCHAR(60) NOT NULL DEFAULT '',
        `creata` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `pagina` (`pagina`),
        KEY `servizio` (`servizio`(100), `zona`(100)),
        KEY `zona` (`zona`(100)),
        KEY `sede` (`sede`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $fatto = true;
}

/** Il gruppo "zone" della configurazione com'è adesso. */
function zone_cfg(): array {
    $c = function_exists('seo_cfg') ? (seo_cfg()['zone'] ?? []) : [];
    return is_array($c) ? $c : [];
}

/**
 * Ci sono pagine per zona? Senza, nessuna query: il sito di chi non usa le zone non paga niente (il numero sta nella
 * configurazione SEO, già letta per i titoli). Con un registro della 2.35.0 ancora nel JSON, prima lo migra.
 */
function zone_attive(): bool {
    $c = zone_cfg();
    if (empty($c['migrato']) && !empty($c['elenco']) && is_array($c['elenco'])) { zone_migra(); $c = zone_cfg(); }
    return (int)($c['voci'] ?? 0) > 0;
}

/**
 * 2.36.0: il registro della 2.35.0 (seo_config.zone.elenco) passa nella tabella, una volta sola. Copia in una transazione con
 * INSERT IGNORE (pagina è unica: rifarla non fa doppioni), poi svuota l'elenco del JSON e mette migrato = 1. Se qualcosa va storto
 * a metà la transazione torna indietro e il JSON resta com'era: niente si perde, e la richiesta dopo riprova.
 */
function zone_migra(): bool {
    $c = zone_cfg();
    $elenco = is_array($c['elenco'] ?? null) ? $c['elenco'] : [];
    if (!empty($c['migrato'])) return true;
    $pdo = db();
    try {
        zone_installa();
        $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT IGNORE INTO ' . tbl('zone') . ' (pagina, servizio, zona, sede, servizio_pagina, creata) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($elenco as $z) {
            if (!is_array($z)) continue;
            $n = zone_normalizza($z);
            if ($n['pagina'] <= 0 || $n['servizio'] === '' || $n['zona'] === '') continue;
            $st->execute(zone_valori_sql($n));
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('zone_migra: ' . $e->getMessage());
        return false;
    }
    seo_salva('zone', ['elenco' => [], 'migrato' => 1, 'voci' => zone_conta_tabella()]);
    zone_azzera_cache();
    return true;
}

/** Quante righe ha la tabella (0 se non c'è). */
function zone_conta_tabella(): int {
    try { return (int)db()->query('SELECT COUNT(*) FROM ' . tbl('zone'))->fetchColumn(); } catch (Throwable $e) { return 0; }
}

/** Riscrive nella configurazione quante voci ha la tabella: dopo ogni scrittura del registro. */
function zone_aggiorna_voci(): void {
    $n = zone_conta_tabella();
    if ((int)(zone_cfg()['voci'] ?? -1) !== $n) seo_salva('zone', ['voci' => $n]);
    zone_azzera_cache();
}

function zone_vuota(): array {
    return ['pagina' => 0, 'servizio' => '', 'zona' => '', 'sede' => '', 'servizio_pagina' => '', 'creata' => ''];
}

/** Una voce del registro (dalla tabella, dal JSON di prima o dal generatore), con ogni campo pulito. */
function zone_normalizza(array $z): array {
    $v = zone_vuota();
    $v['pagina'] = max(0, (int)($z['pagina'] ?? 0));
    $v['servizio'] = sedi_testo($z['servizio'] ?? '', 80);
    $v['zona'] = sedi_testo($z['zona'] ?? '', 80);
    $sede = (string)($z['sede'] ?? '');
    $v['sede'] = $sede === 'principale' || preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $sede) ? $sede : '';
    $rif = (string)($z['servizio_pagina'] ?? '');
    $v['servizio_pagina'] = preg_match('/^[a-z0-9_]{1,40}:\d{1,10}$/', $rif) ? $rif : '';
    $v['creata'] = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)($z['creata'] ?? '')) ? (string)$z['creata'] : '';
    return $v;
}

/** I valori di una voce per l'INSERT, nell'ordine pagina, servizio, zona, sede, servizio_pagina, creata. Le emoji come entità. */
function zone_valori_sql(array $n): array {
    $t = fn(string $s) => function_exists('sito_senza_4byte') ? sito_senza_4byte($s) : $s;
    return [$n['pagina'], $t($n['servizio']), $t($n['zona']), $n['sede'], $n['servizio_pagina'], $n['creata'] !== '' ? $n['creata'] : null];
}

/** Le colonne lette insieme alla voce: la riga della pagina (id, title, slug, status, cestino, publish_at). */
const ZONE_SELECT = 'SELECT z.pagina, z.servizio, z.zona, z.sede, z.servizio_pagina, z.creata, p.id AS p_id, p.title AS p_title, p.slug AS p_slug, p.status AS p_status, p.cestino AS p_cestino, p.publish_at AS p_publish_at';

/** Una riga della query diventa la voce di sempre, con la riga della pagina in 'riga'. */
function zone_da_riga(array $r): array {
    $v = zone_normalizza(['pagina' => $r['pagina'], 'servizio' => function_exists('sito_4byte_veri') ? sito_4byte_veri((string)$r['servizio']) : $r['servizio'],
        'zona' => function_exists('sito_4byte_veri') ? sito_4byte_veri((string)$r['zona']) : $r['zona'], 'sede' => $r['sede'], 'servizio_pagina' => $r['servizio_pagina'], 'creata' => (string)$r['creata']]);
    $v['riga'] = ['id' => $r['p_id'], 'title' => $r['p_title'], 'slug' => $r['p_slug'], 'status' => $r['p_status'], 'cestino' => $r['p_cestino'], 'publish_at' => $r['p_publish_at']];
    return $v;
}

/**
 * La query del registro: la voce più la riga della pagina (JOIN: le voci la cui pagina non c'è più non escono). $dove e $ordine
 * sono testo del codice, mai dell'utente: i valori passano sempre da $parametri (legati). Ritorna le voci per id della pagina.
 */
function zone_query(string $dove = '', array $parametri = [], string $ordine = 'z.id', int $quante = 0, int $da = 0): array {
    if (!zone_attive()) return [];
    $sql = ZONE_SELECT . ' FROM ' . tbl('zone') . ' z JOIN ' . tbl('pages') . ' p ON p.id = z.pagina' . ($dove !== '' ? " WHERE $dove" : '') . " ORDER BY $ordine";
    if ($quante > 0) $sql .= ' LIMIT ' . max(0, $da) . ', ' . $quante;
    try {
        $st = db()->prepare($sql);
        $st->execute($parametri);
        $out = [];
        foreach ($st->fetchAll() as $r) { $z = zone_da_riga($r); if ($z['pagina'] > 0 && $z['servizio'] !== '' && $z['zona'] !== '') $out[$z['pagina']] = $z; }
        return $out;
    } catch (Throwable $e) { error_log('zone_query: ' . $e->getMessage()); return []; }
}

/** La condizione SQL "la pagina è pubblicata adesso" (la stessa regola di contenuti_stato()), con :ora = adesso secondo PHP. */
const ZONE_SQL_PUBBLICATA = "(p.cestino IS NULL AND p.status <> 'draft' AND (p.publish_at IS NULL OR p.publish_at <= :ora))";

/** Lo stesso servizio senza badare alle maiuscole (e solo a quelle: "Pulizie" e "Pulìzie" restano due servizi, come prima). */
const ZONE_SQL_SERVIZIO = '(z.servizio = :servizio AND LOWER(z.servizio) COLLATE utf8mb4_bin = LOWER(:servizio_k))';

function zone_cache(?array $nuova = null, bool $azzera = false): array {
    static $c = [];
    if ($azzera) $c = [];
    if ($nuova !== null) $c = $nuova + $c;
    return $c;
}

function zone_azzera_cache(): void {
    zone_cache(null, true);
    zone_tutte(true);
}

/**
 * Tutte le pagine per zona, per id della pagina, con la riga della tabella pages in 'riga'. Dalla 2.36.0 si usa solo dove servono
 * davvero tutte (gli strumenti, le prove): il sito e il pannello leggono con query mirate (zone_di_pagina, zone_di_percorso,
 * zone_elenco, zone_altre_pubblicate).
 */
function zone_tutte(bool $azzera = false): array {
    static $cache = null;
    if ($azzera) { $cache = null; return []; }
    if ($cache !== null) return $cache;
    return $cache = zone_query();
}

/** Prima di scrivere nel registro: la tabella c'è e un registro della 2.35.0 è già passato lì (altrimenti si perderebbe). */
function zone_pronta_per_scrivere(): void {
    if (!zone_migra()) throw new RuntimeException('Il registro delle pagine per zona non è riuscito a passare nella tabella nuova del database: riprova fra poco. Se l\'errore resta, il registro della versione di prima è ancora intatto nella configurazione SEO (il dettaglio è nel log degli errori).');
}

/** Scrive il registro intero (per le prove: azzera). Le voci che non ci sono più si tolgono. */
function zone_salva_elenco(array $elenco): void {
    zone_pronta_per_scrivere();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM ' . tbl('zone'));
        $st = $pdo->prepare('INSERT IGNORE INTO ' . tbl('zone') . ' (pagina, servizio, zona, sede, servizio_pagina, creata) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($elenco as $z) { $n = zone_normalizza(is_array($z) ? $z : []); if ($n['pagina'] > 0 && $n['servizio'] !== '' && $n['zona'] !== '') $st->execute(zone_valori_sql($n)); }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    zone_aggiorna_voci();
}

/** Toglie dal registro le voci la cui pagina è sparita. Ritorna quante. Si chiama dal pannello, mai mentre il sito disegna una pagina. */
function zone_pulisci(): int {
    if (!zone_attive()) return 0;
    $n = (int)db()->exec('DELETE z FROM ' . tbl('zone') . ' z LEFT JOIN ' . tbl('pages') . ' p ON p.id = z.pagina WHERE p.id IS NULL');
    if ($n > 0) zone_aggiorna_voci();
    return max(0, $n);
}

/** Aggiunge (o aggiorna) una voce del registro. Nessun tetto al totale (2.36.0). $conta false: il numero delle voci lo aggiorna chi chiama (un giro intero). */
function zone_registra(array $z, bool $conta = true): void {
    $z = zone_normalizza($z);
    if ($z['pagina'] <= 0) throw new RuntimeException('La pagina per zona non ha un id.');
    if ($z['creata'] === '') $z['creata'] = date('Y-m-d H:i:s');
    zone_pronta_per_scrivere();
    db()->prepare('INSERT INTO ' . tbl('zone') . ' (pagina, servizio, zona, sede, servizio_pagina, creata) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE servizio = VALUES(servizio), zona = VALUES(zona), sede = VALUES(sede), servizio_pagina = VALUES(servizio_pagina)')->execute(zone_valori_sql($z));
    if ($conta || (int)(zone_cfg()['voci'] ?? 0) === 0) zone_aggiorna_voci(); else zone_cache(null, true);
}

/** Toglie una pagina dal registro: la pagina resta nel sito (lo dice il pannello). */
function zone_elimina(int $pagina): bool {
    if ($pagina <= 0 || !zone_attive()) return false;
    $st = db()->prepare('DELETE FROM ' . tbl('zone') . ' WHERE pagina = ?');
    $st->execute([$pagina]);
    $tolta = $st->rowCount() > 0;
    zone_aggiorna_voci();
    return $tolta;
}

/** La voce del registro di questa pagina (id della tabella pages), oppure null. Una query per pagina e per richiesta (nessuna senza zone). */
function zone_di_pagina(int $pagina): ?array {
    if ($pagina <= 0 || !zone_attive()) return null;
    $c = zone_cache();
    if (array_key_exists('p' . $pagina, $c)) return $c['p' . $pagina];
    $z = zone_query('z.pagina = :pagina', [':pagina' => $pagina])[$pagina] ?? null;
    zone_cache(['p' . $pagina => $z]);
    return $z;
}

/** La voce del registro della pagina a questo percorso ("/idraulico-a-bologna/"), oppure null. */
function zone_di_percorso(string $percorso): ?array {
    $slug = trim($percorso, '/');
    if ($slug === '' || str_contains($slug, '/') || !zone_attive()) return null;
    $c = zone_cache();
    if (array_key_exists('s' . $slug, $c)) return $c['s' . $slug];
    $voci = zone_query('p.slug = :slug', [':slug' => $slug]);
    $z = null;
    foreach ($voci as $x) if ($x['riga']['slug'] === $slug) { $z = $x; break; }
    zone_cache(['s' . $slug => $z]);
    return $z;
}

/**
 * 2.36.0: una pagina dell'elenco del pannello. $f: servizio (filtro esatto, senza maiuscole), cerca (nel servizio, nella zona o nel
 * titolo), pagina (da 1), per_pagina. Ritorna ['voci' => [...], 'totale' => N, 'pagina' => P, 'pagine' => quante pagine].
 */
function zone_elenco(array $f = []): array {
    [$dove, $par] = zone_filtri($f);
    $per = max(1, min(500, (int)($f['per_pagina'] ?? ZONE_PER_PAGINA)));
    $totale = zone_conta($dove, $par);
    $pagine = max(1, (int)ceil($totale / $per));
    $pag = max(1, min($pagine, (int)($f['pagina'] ?? 1)));
    $voci = $totale > 0 ? zone_query($dove, $par, 'z.servizio, z.zona, z.pagina', $per, ($pag - 1) * $per) : [];
    return ['voci' => $voci, 'totale' => $totale, 'pagina' => $pag, 'pagine' => $pagine, 'per_pagina' => $per];
}

/** La condizione e i parametri dei filtri dell'elenco. La ricerca usa LIKE con i caratteri jolly dell'utente resi innocui. */
function zone_filtri(array $f): array {
    $dove = []; $par = [];
    $servizio = sedi_testo($f['servizio'] ?? '', 80);
    if ($servizio !== '') { $dove[] = ZONE_SQL_SERVIZIO; $par[':servizio'] = $servizio; $par[':servizio_k'] = $servizio; }
    $cerca = sedi_testo($f['cerca'] ?? '', 80);
    if ($cerca !== '') {
        $like = '%' . addcslashes(function_exists('sito_senza_4byte') ? sito_senza_4byte($cerca) : $cerca, '\\%_') . '%';
        $dove[] = '(z.servizio LIKE :c1 OR z.zona LIKE :c2 OR p.title LIKE :c3)';
        $par[':c1'] = $like; $par[':c2'] = $like; $par[':c3'] = $like;
    }
    return [implode(' AND ', $dove), $par];
}

/** Quante voci (con la pagina che c'è ancora) rispondono alla condizione. */
function zone_conta(string $dove = '', array $par = []): int {
    if (!zone_attive()) return 0;
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('zone') . ' z JOIN ' . tbl('pages') . ' p ON p.id = z.pagina' . ($dove !== '' ? " WHERE $dove" : ''));
        $st->execute($par);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { error_log('zone_conta: ' . $e->getMessage()); return 0; }
}

/**
 * 2.36.0: i numeri in cima all'elenco, con una query: quante in tutto, pubblicate e bozze che hanno ancora un riquadro
 * [DA SCRIVERE] (cercato nel testo salvato della bozza dell'editor, o della versione pubblicata se la bozza non c'è).
 */
function zone_conteggi(array $f = []): array {
    $out = ['totale' => 0, 'pubblicate' => 0, 'da_scrivere' => 0];
    if (!zone_attive()) return $out;
    [$dove, $par] = zone_filtri($f);
    $par[':ora'] = date('Y-m-d H:i:s');
    $par[':seg'] = '%' . addcslashes(ZONE_SEGNAPOSTO, '\\%_') . '%';
    try {
        $st = db()->prepare('SELECT COUNT(*) AS totale, COALESCE(SUM(' . ZONE_SQL_PUBBLICATA . '), 0) AS pubblicate,
            COALESCE(SUM(p.cestino IS NULL AND p.status = \'draft\' AND COALESCE(NULLIF(p.struttura_bozza, \'\'), p.struttura) LIKE :seg), 0) AS da_scrivere
            FROM ' . tbl('zone') . ' z JOIN ' . tbl('pages') . ' p ON p.id = z.pagina' . ($dove !== '' ? " WHERE $dove" : ''));
        $st->execute($par);
        $r = $st->fetch();
        foreach ($out as $k => $x) $out[$k] = (int)($r[$k] ?? 0);
    } catch (Throwable $e) { error_log('zone_conteggi: ' . $e->getMessage()); }
    return $out;
}

/**
 * 2.36.0: i servizi con più di ZONE_AVVISO_WIDGET pagine pubblicate in cui il widget Altre zone le mostra tutte ("Quante mostrarne"
 * a 0). Si guardano al massimo 40 pagine per servizio (le più recenti): ['servizio' => ..., 'pubblicate' => N, 'tutte' => quante
 * delle pagine guardate mostrano tutto]. Per l'avviso del pannello.
 */
function zone_avvisi_widget(): array {
    if (!zone_attive()) return [];
    $out = [];
    try {
        $st = db()->prepare('SELECT MIN(z.servizio) AS servizio, COUNT(*) AS n FROM ' . tbl('zone') . ' z JOIN ' . tbl('pages') . ' p ON p.id = z.pagina WHERE ' . ZONE_SQL_PUBBLICATA . ' GROUP BY LOWER(z.servizio) COLLATE utf8mb4_bin HAVING COUNT(*) > ' . ZONE_AVVISO_WIDGET . ' ORDER BY n DESC LIMIT 20');
        $st->execute([':ora' => date('Y-m-d H:i:s')]);
        $servizi = $st->fetchAll();
    } catch (Throwable $e) { error_log('zone_avvisi_widget: ' . $e->getMessage()); return []; }
    if (!$servizi) return [];
    require_once __DIR__ . '/editor-controlli.php';
    require_once __DIR__ . '/editor.php';
    foreach ($servizi as $s) {
        $st = db()->prepare('SELECT p.struttura FROM ' . tbl('zone') . ' z JOIN ' . tbl('pages') . ' p ON p.id = z.pagina WHERE ' . ZONE_SQL_SERVIZIO . ' AND ' . ZONE_SQL_PUBBLICATA . ' ORDER BY z.id DESC LIMIT 40');
        $st->execute([':servizio' => $s['servizio'], ':servizio_k' => $s['servizio'], ':ora' => date('Y-m-d H:i:s')]);
        $tutte = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $albero = json_decode((string)$json, true);
            if (is_array($albero) && zone_widget_mostra_tutte($albero['elementi'] ?? [])) $tutte++;
        }
        if ($tutte > 0) $out[] = ['servizio' => function_exists('sito_4byte_veri') ? sito_4byte_veri((string)$s['servizio']) : (string)$s['servizio'], 'pubblicate' => (int)$s['n'], 'tutte' => $tutte];
    }
    return $out;
}

/** L'albero ha un widget Altre zone con "Quante mostrarne" a 0 (tutte)? */
function zone_widget_mostra_tutte(array $nodi): bool {
    foreach ($nodi as $e) {
        if (!is_array($e)) continue;
        if (in_array($e['tipo'] ?? '', ['sezione', 'colonna'], true)) { if (zone_widget_mostra_tutte((array)($e['figli'] ?? []))) return true; continue; }
        if (($e['tipo'] ?? '') === 'zone' && (int)($e['impostazioni']['quante'] ?? 0) <= 0) return true;
    }
    return false;
}

/** I modelli del titolo e della descrizione salvati l'ultima volta (quelli di partenza se mai cambiati). */
function zone_modelli(): array {
    $c = seo_cfg()['zone'] ?? [];
    $t = sedi_testo($c['titolo'] ?? '', 120); $d = sedi_testo($c['descrizione'] ?? '', 300);
    return ['titolo' => $t !== '' ? $t : ZONE_TITOLO_PREDEFINITO, 'descrizione' => $d !== '' ? $d : ZONE_DESCRIZIONE_PREDEFINITA];
}

function zone_modelli_salva(string $titolo, string $descrizione): void {
    seo_salva('zone', ['titolo' => sedi_testo($titolo, 120), 'descrizione' => sedi_testo($descrizione, 300)]);
    zone_tutte(true);
}

/** "%servizio% a %zona%" con i valori dentro (anche %Servizio% e %Zona%). */
function zone_componi(string $modello, string $servizio, string $zona): string {
    return trim(str_ireplace(['%servizio%', '%zona%'], [$servizio, $zona], $modello));
}

/** La voce che ha già questa coppia servizio + zona (senza badare a maiuscole e spazi), oppure null. */
function zone_esiste(string $servizio, string $zona): ?array {
    if (!zone_attive()) return null;
    $k = fn(string $s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));
    // La query trova i candidati con la collazione della tabella (senza maiuscole e senza accenti), il confronto di sempre decide.
    $t = fn(string $s) => function_exists('sito_senza_4byte') ? sito_senza_4byte($s) : $s;
    foreach (zone_query('z.servizio = :servizio AND z.zona = :zona', [':servizio' => $t(sedi_testo($servizio, 80)), ':zona' => $t(sedi_testo($zona, 80))]) as $z)
        if ($k($z['servizio']) === $k($servizio) && $k($z['zona']) === $k($zona)) return $z;
    return null;
}

/** I servizi che hanno pagine per zona, senza doppioni, in ordine alfabetico. */
function zone_servizi(): array {
    if (!zone_attive()) return [];
    $out = [];
    try {
        foreach (db()->query('SELECT DISTINCT z.servizio COLLATE utf8mb4_bin AS s FROM ' . tbl('zone') . ' z JOIN ' . tbl('pages') . ' p ON p.id = z.pagina')->fetchAll(PDO::FETCH_COLUMN) as $sv) {
            $sv = function_exists('sito_4byte_veri') ? sito_4byte_veri((string)$sv) : (string)$sv;
            $k = mb_strtolower($sv);
            if ($sv !== '' && !isset($out[$k])) $out[$k] = $sv;
        }
    } catch (Throwable $e) { error_log('zone_servizi: ' . $e->getMessage()); }
    ksort($out);
    return array_values($out);
}

/** Lo stato della pagina di una voce: bozza, programmato, pubblicato, cestino. */
function zone_stato(array $z): string {
    return contenuti_stato('pagine', $z['riga']);
}

/** La pagina di una voce è visibile sul sito adesso? */
function zone_pubblicata(array $z): bool {
    return zone_stato($z) === 'pubblicato';
}

/** L'indirizzo assoluto della pagina di una voce. */
function zone_url(array $z): string {
    return SITO_URL . contenuti_percorso('pagine', $z['riga']);
}

// ------------------------------------------------------------
// La pagina del servizio (la tendina del pannello e le briciole)
// ------------------------------------------------------------

/**
 * Le pagine a cui collegare le pagine per zona: le pagine pubblicate del sito e, se il sito ha un tipo di contenuto "servizi"
 * (un modulo), anche quelli. Chiave "pagine:12" (o "servizi:3") => "Titolo (/indirizzo/)".
 */
function zone_pagine_sito(): array {
    $out = [];
    $home = (string)get_setting('pagina_home', '');
    foreach (db()->query('SELECT id, title, slug, status, cestino FROM ' . tbl('pages') . ' ORDER BY title')->fetchAll() as $r) {
        if (!empty($r['cestino']) || $r['slug'] === $home) continue;
        $out['pagine:' . (int)$r['id']] = $r['title'] . ' (/' . $r['slug'] . '/' . ($r['status'] === 'published' ? '' : ', bozza') . ')';
    }
    if (isset(contenuti_tipi()['servizi'])) {
        $t = contenuti_tipo('servizi');
        try {
            foreach (db()->query('SELECT id, title, slug, status, cestino FROM ' . tbl($t['tabella']) . ' ORDER BY title')->fetchAll() as $r) {
                if (!empty($r['cestino'])) continue;
                $out['servizi:' . (int)$r['id']] = $r['title'] . ' (' . $t['uno'] . ', /' . $r['slug'] . '/' . ($r['status'] === 'published' ? '' : ', bozza') . ')';
            }
        } catch (Throwable $e) { error_log('zone_pagine_sito: ' . $e->getMessage()); }
    }
    return $out;
}

/** La pagina del servizio di una voce ("pagine:12"): ['titolo', 'percorso', 'pubblicata'], oppure null se non c'è (più). */
function zone_servizio_pagina(string $rif): ?array {
    if (!preg_match('/^([a-z0-9_]{1,40}):(\d{1,10})$/', $rif, $m) || !isset(contenuti_tipi()[$m[1]])) return null;
    $r = contenuti_leggi($m[1], (int)$m[2]);
    if (!$r || !empty($r['cestino'])) return null;
    return ['titolo' => (string)$r['title'], 'percorso' => contenuti_percorso($m[1], $r), 'pubblicata' => contenuti_stato($m[1], $r) === 'pubblicato'];
}

// ------------------------------------------------------------
// Il generatore: controllo dei campi e creazione delle bozze
// ------------------------------------------------------------

/**
 * Controlla i campi arrivati dal pannello. Ritorna ['valori' => [servizio, zone, sede, servizio_pagina, titolo, descrizione],
 * 'errori' => [campo => messaggio]].
 */
function zone_valida(array $in): array {
    $errori = [];
    $v = ['servizio' => sedi_testo($in['servizio'] ?? '', 80), 'zone' => [], 'sede' => '', 'servizio_pagina' => '',
        'titolo' => sedi_testo($in['titolo'] ?? '', 120), 'descrizione' => sedi_testo($in['descrizione'] ?? '', 300)];
    if (mb_strlen($v['servizio']) < 2) $errori['servizio'] = 'Scrivi il servizio, per esempio "Idraulico" o "Pulizie di uffici".';
    $righeScritte = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($in['zone'] ?? '')))));
    $zone = [];
    foreach ($righeScritte as $r) { $r = sedi_testo($r, 80); if ($r !== '' && !in_array(mb_strtolower($r), array_map('mb_strtolower', $zone), true)) $zone[] = $r; }
    if (!$zone) $errori['zone'] = 'Scrivi almeno una zona, una per riga: per esempio "Bologna", "Casalecchio di Reno", "Provincia di Bologna".';
    elseif (count($zone) > ZONE_GIRO_MAX) $errori['zone'] = 'Al massimo ' . ZONE_GIRO_MAX . ' zone per giro: ne hai scritte ' . count($zone) . '. Tieni le prime ' . ZONE_GIRO_MAX . ', crea le bozze, poi rifai un giro con le altre (in tutto puoi averne quante vuoi).';
    $v['zone'] = $zone;
    $sedi = sedi_tutte();
    $sede = (string)($in['sede'] ?? '');
    if (count($sedi) === 1) $v['sede'] = array_key_first($sedi);
    elseif (!$sedi) $v['sede'] = '';
    elseif ($sede === 'principale' || isset($sedi[$sede])) $v['sede'] = $sede;
    else $errori['sede'] = 'Scegli la sede di riferimento dall\'elenco.';
    $rif = (string)($in['servizio_pagina'] ?? '');
    if ($rif !== '') {
        if (!preg_match('/^[a-z0-9_]{1,40}:\d{1,10}$/', $rif) || !zone_servizio_pagina($rif)) $errori['servizio_pagina'] = 'La pagina del servizio scelta non c\'è più: scegline un\'altra o lascia "Nessuna".';
        else $v['servizio_pagina'] = $rif;
    }
    if ($v['titolo'] === '') $v['titolo'] = ZONE_TITOLO_PREDEFINITO;
    if (stripos($v['titolo'], '%zona%') === false || stripos($v['titolo'], '%servizio%') === false) $errori['titolo'] = 'Il modello del titolo deve contenere %servizio% e %zona%, per esempio "%servizio% a %zona%": senza, le pagine avrebbero tutte lo stesso titolo.';
    if ($v['descrizione'] === '') $v['descrizione'] = ZONE_DESCRIZIONE_PREDEFINITA;
    return ['valori' => $v, 'errori' => $errori];
}

/** Il testo dei tre riquadri guidati di una zona: titolo, segnaposto e riga di aiuto. Tutto come testo (htmlspecialchars). */
function zone_riquadri_guidati(string $zona): array {
    $z = htmlspecialchars($zona, ENT_QUOTES, 'UTF-8');
    $seg = htmlspecialchars(ZONE_SEGNAPOSTO, ENT_QUOTES, 'UTF-8');
    return [
        '<h3>Cosa facciamo a ' . $z . '</h3><p>' . $seg . ' Cosa fai a ' . $z . ': ...</p><p><em>Aiuto per chi scrive: due o tre frasi concrete su quali interventi fai qui, per chi e in quanto tempo. Scrivi il testo al posto del segnaposto e cancella questa riga.</em></p>',
        '<h3>Un caso vero a ' . $z . '</h3><p>' . $seg . ' Un caso vero a ' . $z . ': ...</p><p><em>Aiuto per chi scrive: un lavoro fatto davvero in questa zona: il problema, cosa avete fatto, come &egrave; finita (senza nomi, se il cliente non vuole). Poi cancella questa riga.</em></p>',
        '<h3>Come si arriva a ' . $z . '</h3><p>' . $seg . ' Come si arriva a ' . $z . ': ...</p><p><em>Aiuto per chi scrive: da quale sede partite, quanto ci mettete, in quali giorni e orari coprite la zona. Poi cancella questa riga.</em></p>',
    ];
}

/**
 * L'albero dell'editor di una pagina per zona: apertura, i tre riquadri guidati, Altre zone, Dati dell'attività e Barra
 * contatti dalla sede (gli ultimi due solo se c'è almeno una sede). $sedeScelta: id della sede, "principale" o vuoto.
 */
function zone_albero(string $servizio, string $zona, string $sedeScelta, string $titolo, string $descrizione): array {
    $conSede = (bool)sedi_tutte();
    $sedeWidget = $sedeScelta !== '' ? $sedeScelta : 'principale';
    $pulsanti = [];
    $contatti = db()->prepare('SELECT slug FROM ' . tbl('pages') . " WHERE slug = 'contatti' AND status = 'published' AND cestino IS NULL");
    $contatti->execute();
    if ($contatti->fetchColumn()) $pulsanti[] = ['testo' => 'Contattaci', 'link' => '/contatti/', 'stile' => 'viola'];
    $riquadri = zone_riquadri_guidati($zona);
    $colonne = [];
    foreach ($riquadri as $html) $colonne[] = ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => ['larghezza' => ['d' => 33, 'm' => 100]], 'figli' => [editor_widget('testo', ['contenuto' => $html])]];
    $albero = [
        editor_sezione([editor_widget('hero', ['occhiello' => $zona, 'titolo' => $servizio . ' a *' . $zona . '*', 'testo' => $descrizione, 'pulsanti' => $pulsanti, 'immagine' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]])], ['spazio_sopra' => ['d' => 48], 'spazio_sotto' => ['d' => 24]]),
        ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => ['spazio_colonne' => ['d' => 32]], 'figli' => $colonne],
    ];
    // Altre zone e Dati dell'attività nella stessa sezione: finché le altre zone sono bozze il widget non esce, e la pagina non ha un vuoto.
    $fondo = [editor_widget('zone', [])];
    if ($conSede) $fondo[] = editor_widget('dati_attivita', ['sede' => $sedeWidget, 'titolo' => 'La sede di riferimento', 'margine_sotto' => ['d' => 0]]);
    else $fondo[0]['impostazioni']['margine_sotto'] = ['d' => 0];
    $albero[] = editor_sezione($fondo, ['larghezza' => 'stretta']);
    if ($conSede) $albero[] = editor_sezione([editor_widget('barra_contatti', ['sede' => $sedeWidget, 'margine_sotto' => ['d' => 0]])], ['spazio_sopra' => ['d' => 0], 'spazio_sotto' => ['d' => 0]]);
    return $albero;
}

/**
 * Crea le bozze: una pagina (tabella pages, stato bozza) con l'albero dell'editor per ogni zona che non ha già la sua pagina
 * per questo servizio. Ritorna ['create' => [[pagina, titolo, slug]], 'esistenti' => [[zona, titolo]], 'restanti' => [le zone non
 * ancora fatte], 'errore' => '']. Mai pubblicate da qui. 2.36.0: niente tetto al totale; con $secondi > 0 il giro si ferma dopo
 * quei secondi di lavoro e le zone che restano tornano in 'restanti' (il pannello continua da solo con un altro pezzo).
 */
function zone_crea_bozze(array $v, string $autore = '', float $secondi = 0): array {
    $inizio = microtime(true);
    require_once __DIR__ . '/editor-controlli.php';
    require_once __DIR__ . '/editor.php';
    editor_carica_widget_esterni();
    $out = ['create' => [], 'esistenti' => [], 'restanti' => [], 'errore' => ''];
    $servizio = sedi_testo($v['servizio'] ?? '', 80);
    $zone = array_slice(array_values(array_filter(array_map(fn($z) => sedi_testo($z, 80), (array)($v['zone'] ?? [])))), 0, ZONE_GIRO_MAX);
    if ($servizio === '' || !$zone) throw new RuntimeException('Servono il servizio e almeno una zona.');
    $sede = (string)($v['sede'] ?? '');
    if ($sede !== 'principale' && $sede !== '' && !sedi_leggi($sede)) $sede = '';
    $modTitolo = sedi_testo($v['titolo'] ?? '', 120) ?: ZONE_TITOLO_PREDEFINITO;
    $modDescr = sedi_testo($v['descrizione'] ?? '', 300) ?: ZONE_DESCRIZIONE_PREDEFINITA;
    $rif = preg_match('/^[a-z0-9_]{1,40}:\d{1,10}$/', (string)($v['servizio_pagina'] ?? '')) && zone_servizio_pagina((string)$v['servizio_pagina']) ? (string)$v['servizio_pagina'] : '';
    zone_pronta_per_scrivere();
    zone_modelli_salva($modTitolo, $modDescr);
    try {
        foreach ($zone as $i => $zona) {
            if ($secondi > 0 && $i > 0 && microtime(true) - $inizio > $secondi) { $out['restanti'] = array_slice($zone, $i); break; }
            $gia = zone_esiste($servizio, $zona);
            if ($gia) { $out['esistenti'][] = ['zona' => $zona, 'titolo' => (string)$gia['riga']['title'], 'pagina' => $gia['pagina']]; continue; }
            $titolo = zone_componi($modTitolo, $servizio, $zona);
            if ($titolo === '') $titolo = $servizio . ' a ' . $zona;
            $descrizione = mb_substr(zone_componi($modDescr, $servizio, $zona), 0, 160);
            $base = contenuti_slug($titolo) ?: contenuti_slug($servizio . '-a-' . $zona) ?: 'zona';
            $base = mb_substr($base, 0, 150);
            $slug = $base; $n = 2;
            while (!contenuti_slug_libero('pagine', $slug, 0)) $slug = $base . '-' . $n++;
            $id = contenuti_salva('pagine', 0, ['title' => $titolo, 'slug' => $slug, 'stato' => 'bozza', 'data' => date('Y-m-d'), 'contenuto' => '', 'robots' => 'index', 'seo_description' => $descrizione], $autore);
            $albero = editor_valida(['elementi' => zone_albero($servizio, $zona, $sede, $titolo, $descrizione)]);
            editor_salva($id, $albero, false, $autore, 'Pagina per zona (bozza guidata)', 'pagine');
            zone_registra(['pagina' => $id, 'servizio' => $servizio, 'zona' => $zona, 'sede' => $sede, 'servizio_pagina' => $rif], false);
            $out['create'][] = ['pagina' => $id, 'titolo' => $titolo, 'slug' => $slug, 'zona' => $zona];
        }
    } finally {
        if ($out['create']) zone_aggiorna_voci(); // il numero delle voci una volta per giro, non a ogni bozza
    }
    return $out;
}

// ------------------------------------------------------------
// I segnaposto e la pubblicazione bloccata
// ------------------------------------------------------------

/** Quanti widget dell'albero hanno ancora un testo con il segnaposto [DA SCRIVERE] (ogni widget conta una volta). */
function zone_segnaposto_restanti($albero): int {
    if (!is_array($albero)) return 0;
    $n = 0;
    $contiene = function ($x) use (&$contiene): bool {
        if (is_string($x)) return str_contains($x, ZONE_SEGNAPOSTO) || str_contains($x, htmlspecialchars(ZONE_SEGNAPOSTO, ENT_QUOTES, 'UTF-8'));
        if (is_array($x)) { foreach ($x as $y) if ($contiene($y)) return true; }
        return false;
    };
    $giro = function (array $nodi) use (&$giro, &$n, $contiene): void {
        foreach ($nodi as $e) {
            if (!is_array($e)) continue;
            $tipo = (string)($e['tipo'] ?? '');
            if (in_array($tipo, ['sezione', 'colonna'], true)) { $giro((array)($e['figli'] ?? [])); continue; }
            if ($tipo !== '' && $contiene($e['impostazioni'] ?? [])) $n++;
        }
    };
    $giro((array)($albero['elementi'] ?? []));
    return $n;
}

/** I segnaposto che restano nella pagina (la bozza dell'editor se c'è, altrimenti la versione pubblicata). */
function zone_segnaposto_pagina(int $pagina): int {
    if ($pagina <= 0) return 0;
    require_once __DIR__ . '/editor-controlli.php';
    require_once __DIR__ . '/editor.php';
    $st = db()->prepare('SELECT struttura, struttura_bozza FROM ' . tbl('pages') . ' WHERE id = ?');
    $st->execute([$pagina]);
    $r = $st->fetch();
    if (!$r) return 0;
    editor_carica_widget_esterni();
    $albero = editor_decodifica((string)($r['struttura_bozza'] ?: $r['struttura']));
    return $albero ? zone_segnaposto_restanti($albero) : 0;
}

/** Il messaggio che spiega perché la pagina resta bozza. */
function zone_messaggio_blocco(int $restanti): string {
    return 'Questa pagina per zona ha ancora ' . $restanti . ($restanti === 1 ? ' riquadro da scrivere' : ' riquadri da scrivere') . ' (cerca ' . ZONE_SEGNAPOSTO . ' nel testo): compilali, poi pubblica. Per ora resta in bozza.';
}

/**
 * Si può pubblicare? Una pagina che non è nel registro sì, sempre. Una pagina per zona solo senza segnaposto: nell'albero dato
 * (quello che l'editor sta per pubblicare) oppure in quello salvato. Ritorna ['ok' => bool, 'restanti' => N, 'messaggio' => ''].
 */
function zone_pubblicabile(int $pagina, ?array $albero = null): array {
    if (!zone_di_pagina($pagina)) return ['ok' => true, 'restanti' => 0, 'messaggio' => ''];
    $n = $albero !== null ? zone_segnaposto_restanti($albero) : zone_segnaposto_pagina($pagina);
    return ['ok' => $n === 0, 'restanti' => $n, 'messaggio' => $n === 0 ? '' : zone_messaggio_blocco($n)];
}

/** Lancia un RuntimeException con il messaggio se la pagina per zona non si può pubblicare. Chiamata da ogni strada che pubblica. */
function zone_blocco_pubblicazione(int $pagina, ?array $albero = null): void {
    $p = zone_pubblicabile($pagina, $albero);
    if (!$p['ok']) throw new RuntimeException($p['messaggio']);
}

/** Per le azioni in blocco: [gli id che si possono pubblicare, [id => messaggio] per quelli fermati]. */
function zone_pubblicabili_tra(array $ids): array {
    $ok = []; $fermate = [];
    foreach (array_map('intval', $ids) as $id) {
        $p = zone_pubblicabile($id);
        if ($p['ok']) $ok[] = $id; else $fermate[$id] = $p['messaggio'];
    }
    return [$ok, $fermate];
}

// ------------------------------------------------------------
// Per il widget Altre zone e per i dati per Google
// ------------------------------------------------------------

/**
 * Le altre pagine per zona dello stesso servizio, pubblicate, in ordine di zona: [['zona', 'titolo', 'url']]. La query prende solo
 * quelle da mostrare. $quante 0 = tutte (come fino alla 2.35.0, stesso ordine al byte). $ordine: "alfabetico" (le prime $quante in
 * ordine di zona) oppure "vicine" (le $quante più vicine a questa zona nell'elenco alfabetico, metà prima e metà dopo: ogni pagina
 * ne mostra di diverse e tutte ricevono link). L'elenco esce sempre in ordine alfabetico.
 */
function zone_altre_pubblicate(array $z, int $quante = 0, string $ordine = 'alfabetico'): array {
    if (!zone_attive() || (int)($z['pagina'] ?? 0) <= 0) return [];
    $k = mb_strtolower((string)$z['servizio']);
    $t = fn(string $s) => function_exists('sito_senza_4byte') ? sito_senza_4byte($s) : $s;
    $base = ZONE_SQL_SERVIZIO . ' AND z.pagina <> :pagina AND ' . ZONE_SQL_PUBBLICATA;
    $par = [':servizio' => $t((string)$z['servizio']), ':servizio_k' => $t((string)$z['servizio']), ':pagina' => (int)$z['pagina'], ':ora' => date('Y-m-d H:i:s')];
    $quante = max(0, $quante);
    if ($quante > 0 && $ordine === 'vicine') {
        // Le seguenti e le precedenti nell'elenco (fino a $quante per parte), poi metà e metà; se una parte è corta, l'altra riempie.
        $dopo = array_values(zone_query($base . ' AND z.zona > :zona', $par + [':zona' => $t((string)$z['zona'])], 'z.zona, z.pagina', $quante));
        $prima = array_values(zone_query($base . ' AND z.zona < :zona', $par + [':zona' => $t((string)$z['zona'])], 'z.zona DESC, z.pagina DESC', $quante));
        $nPrima = min(count($prima), intdiv($quante, 2));
        $nDopo = min(count($dopo), $quante - $nPrima);
        $nPrima = min(count($prima), $quante - $nDopo);
        $voci = array_merge(array_slice($prima, 0, $nPrima), array_slice($dopo, 0, $nDopo));
    } else {
        $voci = zone_query($base, $par, 'z.zona, z.pagina', $quante);
    }
    $out = [];
    foreach ($voci as $x) {
        if ((int)$x['pagina'] === (int)$z['pagina'] || mb_strtolower($x['servizio']) !== $k || !zone_pubblicata($x)) continue;
        $out[] = ['zona' => $x['zona'], 'titolo' => (string)$x['riga']['title'], 'url' => zone_url($x)];
    }
    usort($out, fn($a, $b) => strcoll(mb_strtolower($a['zona']), mb_strtolower($b['zona'])));
    return $out;
}

function zone_jsonld_id(array $c): string {
    return SITO_URL . $c['percorso'] . '#servizio';
}

/** Il nodo Service della pagina per zona: name "%servizio% a %zona%", areaServed la zona, provider la sede di riferimento. */
function zone_jsonld_servizio(array $z, array $c): array {
    $sede = $z['sede'] !== '' ? sedi_per_widget($z['sede']) : null;
    $area = sedi_area_servita_di(['zona' => [$z['zona']]]);
    return array_filter([
        '@type' => 'Service', '@id' => zone_jsonld_id($c), 'name' => $c['titolo'], 'description' => $c['seo']['descrizione'] ?: null,
        'url' => SITO_URL . $c['percorso'], 'serviceType' => $z['servizio'],
        'provider' => ['@id' => $sede ? sedi_jsonld_id($sede) : seo_jsonld_id('servizio')],
        'areaServed' => $area[0] ?? null,
    ], fn($x) => $x !== null && $x !== '');
}

/** Il nodo in più della WebPage: il Service come mainEntity. */
function zone_jsonld_pagina_extra(array $z, array $c): array {
    return ['mainEntity' => ['@id' => zone_jsonld_id($c)]];
}

/** Le briciole: Home > la pagina del servizio (se collegata e pubblicata) > la zona. */
function zone_briciole_voci(array $z, array $c): array {
    $voci = [['Home', '/']];
    $serv = $z['servizio_pagina'] !== '' ? zone_servizio_pagina($z['servizio_pagina']) : null;
    if ($serv && $serv['pubblicata']) $voci[] = [$serv['titolo'], $serv['percorso']];
    $voci[] = [$z['zona'], $c['percorso']];
    return $voci;
}
