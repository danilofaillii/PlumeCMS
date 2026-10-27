<?php
/**
 * Header e footer del sito (Theme Builder): più modelli, uno predefinito per tipo, condizioni come in Elementor,
 * scelta per singolo contenuto.
 *
 * Un modello è una riga di newdf_modelli: tipo (header | footer), nome, struttura dell'editor visuale
 * (le stesse sezioni, colonne e widget delle pagine: includes/editor.php), bozza, stato, stellina del predefinito,
 * condizioni in JSON e priorità. Le revisioni stanno in newdf_pagine_revisioni con tipo "modelli".
 *
 * Chi decide quale modello esce su una pagina (modelli_scegli):
 *   1. la scelta del contenuto: colonne header_id e footer_id su articoli, pagine e aforismi
 *      (0 = predefinito, -1 = nessuno, > 0 = quel modello);
 *   2. le condizioni dei modelli pubblicati: tutto il sito, un tipo di contenuto, una categoria, un contenuto;
 *      vince la più specifica, a parità la priorità più alta; una condizione "escludi" toglie il modello;
 *   3. il predefinito del tipo (la stellina);
 *   4. se non c'è niente, la testata e il piede scritti nel codice (vista_pagina), come prima.
 *
 * Il primo avvio crea "Header del sito" e "Footer del sito", costruiti con i widget del tema
 * (includes/editor-widget-tema.php) uguali a occhio alla testata e al piede di prima, e li mette predefiniti.
 */

require_once __DIR__ . '/sito.php';
require_once __DIR__ . '/menu-nav.php';

const MODELLI_VERSIONE_SCHEMA = '1';
const MODELLI_TIPI = ['header' => 'Header', 'footer' => 'Footer'];
/** Dove può uscire un modello: tutto il sito, ogni tipo di contenuto (anche dei moduli), gli archivi, una categoria, un contenuto preciso. */
function modelli_dove(): array {
    static $d = null;
    if ($d !== null) return $d;
    require_once __DIR__ . '/contenuti.php';
    $d = ['sito' => 'Tutto il sito'];
    foreach (contenuti_tipi() as $k => $t) {
        $femminile = ($t['genere'] ?? (str_ends_with($t['uno'], 'a') ? 'f' : 'm')) === 'f';
        $art = $femminile ? 'Tutte le ' : (preg_match('/^[aeiou]/i', $t['tanti']) ? 'Tutti gli ' : 'Tutti i ');
        $d[$k] = $art . $t['tanti'];
    }
    return $d += ['archivi' => 'Blog, archivi, glossario e ricerca', 'categoria' => 'Articoli di una categoria', 'contenuto' => 'Un contenuto preciso'];
}
/** Quanto è specifica una condizione: vince la più alta. */
function modelli_peso(string $dove): int {
    return ['sito' => 1, 'archivi' => 2, 'categoria' => 3, 'contenuto' => 4][$dove] ?? 2;
}

// ------------------------------------------------------------
// Schema e modelli di partenza
// ------------------------------------------------------------

/** Crea la tabella e, la prima volta, i due modelli di partenza. Una query di controllo per richiesta. */
function modelli_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    if (get_setting('modelli_schema', '') === MODELLI_VERSIONE_SCHEMA) return;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('modelli') . ' (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `tipo` VARCHAR(10) NOT NULL DEFAULT \'header\',
        `nome` VARCHAR(150) NOT NULL,
        `struttura` LONGTEXT,
        `struttura_bozza` LONGTEXT,
        `struttura_aggiornata` DATETIME DEFAULT NULL,
        `status` VARCHAR(10) NOT NULL DEFAULT \'draft\',
        `predefinito` TINYINT(1) NOT NULL DEFAULT 0,
        `condizioni` TEXT,
        `priorita` INT(11) NOT NULL DEFAULT 0,
        `cestino` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`), KEY `tipo` (`tipo`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
    // Le colonne header_id e footer_id dei contenuti: le aggiunge lo schema del sito (contenuti_schema_colonne).
    sito_schema_aggiorna();
    menu_installa();
    if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('modelli'))->fetchColumn() === 0) modelli_crea_di_partenza();
    save_setting('modelli_schema', MODELLI_VERSIONE_SCHEMA);
}

/** I due modelli di partenza, pubblicati e predefiniti: la testata e il piede com'erano nel codice. */
function modelli_crea_di_partenza(): void {
    require_once __DIR__ . '/editor.php';
    $alberi = cms_filtro('modelli_partenza', ['header' => modelli_albero_header_partenza(), 'footer' => modelli_albero_footer_partenza()]);
    foreach (['header' => 'Header del sito', 'footer' => 'Footer del sito'] as $tipo => $nome) {
        if (empty($alberi[$tipo])) continue;
        $id = modelli_crea($tipo, $nome, $alberi[$tipo]);
        db()->prepare('UPDATE ' . tbl('modelli') . " SET status = 'published', predefinito = 1, struttura = struttura_bozza, struttura_bozza = NULL WHERE id = ?")->execute([$id]);
    }
}

/** L'header di partenza del CMS: marchio e menu Principale (il modulo del sito può darne uno suo con il filtro modelli_partenza). */
function modelli_albero_header_partenza(): array {
    $principale = menu_per_posizione('principale');
    return editor_valida(['elementi' => [editor_sezione([
        editor_widget('logo_sito', ['margine_sotto' => ['d' => 0]]),
        editor_widget('menu_nav', ['menu' => (string)($principale['id'] ?? ''), 'disposizione' => 'orizzontale', 'spingi' => true, 'collasso' => 960, 'hamburger_testo' => 'Menu', 'margine_sotto' => ['d' => 0]]),
    ], ['larghezza' => 'contenuta', 'spazio_sopra' => ['d' => 0, 'm' => 0], 'spazio_sotto' => ['d' => 0, 'm' => 0], 'spazio_colonne' => ['d' => 28]])]]);
}

/** Il footer di partenza del CMS: marchio, frase del sito e copyright. */
function modelli_albero_footer_partenza(): array {
    return editor_valida(['elementi' => [editor_sezione([
        editor_widget('logo_sito', ['margine_sotto' => ['d' => 8]]), editor_widget('descrizione_sito', ['margine_sotto' => ['d' => 16]]), editor_widget('copyright', ['margine_sotto' => ['d' => 0]]),
    ], ['larghezza' => 'contenuta', 'spazio_sopra' => ['d' => 56, 'm' => 56], 'spazio_sotto' => ['d' => 40, 'm' => 40], 'colore_testo' => '#a7b1c6'])]]);
}

// ------------------------------------------------------------
// Lettura
// ------------------------------------------------------------

function modelli_tutti(bool $anchePesanti = false): array {
    static $cache = [];
    $k = $anchePesanti ? 'p' : 'l';
    if (isset($cache[$k])) return $cache[$k];
    modelli_installa();
    $cols = $anchePesanti ? '*' : 'id, tipo, nome, status, predefinito, condizioni, priorita, cestino, struttura_aggiornata, created_at, updated_at, (struttura IS NOT NULL AND struttura <> \'\') AS pubblicata, (struttura_bozza IS NOT NULL AND struttura_bozza <> \'\') AS con_bozza';
    $out = [];
    foreach (db()->query("SELECT $cols FROM " . tbl('modelli') . ' ORDER BY tipo, predefinito DESC, nome')->fetchAll() as $r) {
        $c = json_decode((string)$r['condizioni'], true);
        $r['condizioni'] = modelli_pulisci_condizioni(is_array($c) ? $c : []);
        $out[(int)$r['id']] = $r;
    }
    return $cache[$k] = $out;
}

function modelli_leggi(int $id): ?array {
    modelli_installa();
    $st = db()->prepare('SELECT * FROM ' . tbl('modelli') . ' WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch() ?: null;
    if ($r) {
        $c = json_decode((string)$r['condizioni'], true);
        $r['condizioni'] = modelli_pulisci_condizioni(is_array($c) ? $c : []);
    }
    return $r;
}

/** I modelli di un tipo che possono uscire sul sito: pubblicati, con una struttura, non nel cestino. */
function modelli_pubblicati(string $tipo): array {
    return array_values(array_filter(modelli_tutti(), fn($m) => $m['tipo'] === $tipo && $m['status'] === 'published' && !$m['cestino'] && $m['pubblicata']));
}

/** Per le tendine "Header" e "Footer" di un contenuto: 0 Predefinito, -1 Nessuno, poi i modelli pubblicati. */
function modelli_opzioni_scelta(string $tipo): array {
    $o = ['0' => 'Predefinito (' . MODELLI_TIPI[$tipo] . ' del sito)', '-1' => 'Nessuno'];
    foreach (modelli_pubblicati($tipo) as $m) $o[(string)$m['id']] = $m['nome'] . ($m['predefinito'] ? ' (predefinito)' : '');
    return $o;
}

/** Il valore di header_id/footer_id ripulito: 0, -1 oppure l'id di un modello che esiste. */
function modelli_valore_scelta($x): int {
    $n = (int)$x;
    if ($n <= 0) return $n < 0 ? -1 : 0;
    return isset(modelli_tutti()[$n]) ? $n : 0;
}

/** La riga come la vuole l'editor visuale (editor.php lavora con title, slug, status...). */
function modelli_riga_editor(int $id): ?array {
    $m = modelli_leggi($id);
    if (!$m || $m['cestino']) return null;
    return $m + ['title' => $m['nome'], 'slug' => '', 'html' => '', 'content' => '', 'seo_title' => '', 'seo_description' => '', 'robots' => '', 'tldr' => '', '_tipo' => 'modelli', '_modello' => $m];
}

// ------------------------------------------------------------
// Scrittura
// ------------------------------------------------------------

function modelli_json(array $albero): string {
    require_once __DIR__ . '/editor.php';
    return editor_json($albero);
}

/** Un modello nuovo, in bozza. $albero: la struttura di partenza (vuota se manca). Ritorna l'id. */
function modelli_crea(string $tipo, string $nome, ?array $albero = null): int {
    modelli_installa();
    if (!isset(MODELLI_TIPI[$tipo])) throw new RuntimeException('Tipo di modello sconosciuto.');
    $nome = mb_substr(trim($nome), 0, 150) ?: MODELLI_TIPI[$tipo] . ' nuovo';
    if ($albero === null) {
        // Un modello nuovo parte da una sezione vuota senza spazi sopra e sotto (un header è alto quanto il suo contenuto).
        require_once __DIR__ . '/editor.php';
        $albero = editor_valida(['elementi' => [editor_sezione([], ['larghezza' => 'contenuta', 'spazio_sopra' => ['d' => $tipo === 'header' ? 0 : 56, 'm' => $tipo === 'header' ? 0 : 56], 'spazio_sotto' => ['d' => $tipo === 'header' ? 0 : 40, 'm' => $tipo === 'header' ? 0 : 40], 'spazio_colonne' => ['d' => 28]])]]);
    }
    db()->prepare('INSERT INTO ' . tbl('modelli') . " (tipo, nome, struttura_bozza, struttura_aggiornata, status, condizioni, created_at, updated_at) VALUES (?, ?, ?, NOW(), 'draft', '[]', NOW(), NOW())")
        ->execute([$tipo, $nome, modelli_json($albero)]);
    return (int)db()->lastInsertId();
}

function modelli_duplica(int $id): int {
    $m = modelli_leggi($id);
    if (!$m) throw new RuntimeException('Modello non trovato.');
    db()->prepare('INSERT INTO ' . tbl('modelli') . " (tipo, nome, struttura_bozza, struttura_aggiornata, status, condizioni, priorita, created_at, updated_at) VALUES (?, ?, ?, NOW(), 'draft', '[]', 0, NOW(), NOW())")
        ->execute([$m['tipo'], mb_substr($m['nome'] . ' (copia)', 0, 150), $m['struttura'] ?: $m['struttura_bozza']]);
    return (int)db()->lastInsertId();
}

function modelli_rinomina(int $id, string $nome): void {
    $nome = mb_substr(trim($nome), 0, 150);
    if ($nome === '') throw new RuntimeException('Il nome non può restare vuoto.');
    db()->prepare('UPDATE ' . tbl('modelli') . ' SET nome = ?, updated_at = NOW() WHERE id = ?')->execute([$nome, $id]);
}

/** Pubblicato o bozza. Un modello in bozza non esce sul sito, anche se predefinito. */
function modelli_stato(int $id, bool $pubblicato): void {
    db()->prepare('UPDATE ' . tbl('modelli') . ' SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$pubblicato ? 'published' : 'draft', $id]);
    modelli_versione($id, $pubblicato ? 'pubblicato' : 'messo in bozza'); // versioni
}

/** La stellina: un solo predefinito per tipo. */
function modelli_predefinito(int $id): void {
    $m = modelli_leggi($id);
    if (!$m) throw new RuntimeException('Modello non trovato.');
    $prima = null;
    foreach (modelli_tutti() as $x) if ($x['tipo'] === $m['tipo'] && $x['predefinito'] && (int)$x['id'] !== $id) $prima = $x;
    db()->prepare('UPDATE ' . tbl('modelli') . ' SET predefinito = 0 WHERE tipo = ?')->execute([$m['tipo']]);
    db()->prepare('UPDATE ' . tbl('modelli') . " SET predefinito = 1, status = 'published', updated_at = NOW() WHERE id = ?")->execute([$id]);
    // versioni: il cambio del predefinito resta nella cronologia, con una nota automatica.
    modelli_versione($id, 'predefinito: ' . $m['nome'] . ($prima ? ' al posto di ' . $prima['nome'] : ''));
    if ($prima) modelli_versione((int)$prima['id'], 'non più predefinito: al suo posto ' . $m['nome']);
}

/** versioni: una copia del modello (struttura, nome, stato, predefinito, condizioni) in {prefisso}editor_versioni. Mai un errore da qui. */
function modelli_versione(int $id, string $nota): void {
    try {
        require_once __DIR__ . '/versioni.php';
        $st = db()->prepare('SELECT * FROM ' . tbl('modelli') . ' WHERE id = ?');
        $st->execute([$id]);
        $m = $st->fetch();
        if (!$m) return;
        editor_versione_salva('modello', $id, (string)$m['nome'], versioni_dati_modello($m), $nota, ['pubblicata' => trim((string)$m['struttura_bozza']) === '' && trim((string)$m['struttura']) !== '']);
    } catch (Throwable $e) {
        error_log('versioni (modello): ' . $e->getMessage());
    }
}

function modelli_cestina(int $id): void {
    $m = modelli_leggi($id);
    if ($m && $m['predefinito']) throw new RuntimeException('Il modello predefinito non va nel cestino: prima scegline un altro con la stellina.');
    db()->prepare('UPDATE ' . tbl('modelli') . ' SET cestino = NOW() WHERE id = ?')->execute([$id]);
}

function modelli_ripristina(int $id): void {
    db()->prepare('UPDATE ' . tbl('modelli') . ' SET cestino = NULL WHERE id = ?')->execute([$id]);
}

/** Via per sempre: anche le revisioni; i contenuti che lo avevano scelto tornano al predefinito. */
function modelli_elimina(int $id): void {
    $m = modelli_leggi($id);
    if (!$m) return;
    if ($m['predefinito']) throw new RuntimeException('Il modello predefinito non si elimina: prima scegline un altro con la stellina.');
    db()->prepare('DELETE FROM ' . tbl('modelli') . ' WHERE id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('pagine_revisioni') . " WHERE tipo = 'modelli' AND pagina_id = ?")->execute([$id]);
    $col = $m['tipo'] . '_id';
    require_once __DIR__ . '/contenuti.php';
    foreach (contenuti_tipi() as $def) {
        $t = $def['tabella'];
        if (sito_colonna_esiste($t, $col)) db()->prepare('UPDATE ' . tbl($t) . " SET `$col` = 0 WHERE `$col` = ?")->execute([$id]);
    }
}

/** Le condizioni: [{"dove":"sito|articoli|pagine|aforismi|archivi|categoria|contenuto","valore":"","escludi":false}]. */
function modelli_pulisci_condizioni($c): array {
    $out = [];
    foreach (is_array($c) ? array_slice($c, 0, 40) : [] as $r) {
        if (!is_array($r) || !isset(modelli_dove()[$r['dove'] ?? ''])) continue;
        $out[] = ['dove' => $r['dove'], 'valore' => mb_substr(preg_replace('/[^a-z0-9:\-]/', '', strtolower((string)($r['valore'] ?? ''))), 0, 120), 'escludi' => !empty($r['escludi'])];
    }
    return $out;
}

function modelli_condizioni_salva(int $id, array $condizioni, int $priorita): void {
    db()->prepare('UPDATE ' . tbl('modelli') . ' SET condizioni = ?, priorita = ?, updated_at = NOW() WHERE id = ?')
        ->execute([json_encode(modelli_pulisci_condizioni($condizioni), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), max(-99, min(99, $priorita)), $id]);
    modelli_versione($id, 'condizioni cambiate'); // versioni
}

/** Le condizioni in parole, per l'elenco del pannello. */
function modelli_condizioni_testo(array $m): string {
    $parti = [];
    foreach ($m['condizioni'] as $c) {
        $t = modelli_dove()[$c['dove']];
        if ($c['dove'] === 'categoria') $t = 'Categoria: ' . $c['valore'];
        if ($c['dove'] === 'contenuto') $t = 'Contenuto: ' . modelli_nome_contenuto($c['valore']);
        $parti[] = ($c['escludi'] ? 'tranne ' : '') . $t;
    }
    return implode(' · ', $parti);
}

/** "articoli:12" → il titolo del contenuto. */
function modelli_nome_contenuto(string $rif): string {
    require_once __DIR__ . '/contenuti.php';
    if (!preg_match('/^([a-z0-9_-]+):(\d+)$/', $rif, $m) || !isset(contenuti_tipi()[$m[1]])) return $rif;
    $tab = contenuti_tipi()[$m[1]]['tabella'];
    $st = db()->prepare('SELECT title FROM ' . tbl($tab) . ' WHERE id = ?');
    $st->execute([(int)$m[2]]);
    return (string)($st->fetchColumn() ?: $rif);
}

// ------------------------------------------------------------
// Quale modello esce su questa pagina
// ------------------------------------------------------------

/**
 * Il contenuto che il sito sta mostrando: tipo (articoli, pagine, aforismi, altro), la riga del database,
 * le categorie, il percorso, il titolo. Dal front controller arriva $GLOBALS['sito_percorso']; dal pannello
 * (anteprima nell'editor) arriva direttamente la riga in $GLOBALS['sito_contenuto_riga'].
 */
function modelli_contesto(): array {
    static $ctx = null;
    if ($ctx !== null) return $ctx;
    $ctx = ['tipo' => 'altro', 'riga' => [], 'categorie' => [], 'percorso' => (string)($GLOBALS['sito_percorso'] ?? ''), 'titolo' => '', 'c' => null];
    if (!empty($GLOBALS['sito_contenuto_riga']) && is_array($GLOBALS['sito_contenuto_riga'])) {
        $r = $GLOBALS['sito_contenuto_riga'];
        require_once __DIR__ . '/contenuti.php';
        $ctx['tipo'] = isset(contenuti_tipi()[$r['_tipo'] ?? '']) ? $r['_tipo'] : 'altro';
        $ctx['riga'] = $r;
        $ctx['categorie'] = sito_lista((string)($r['categorie'] ?? '')) ?: array_values(array_filter([(string)($r['category'] ?? '')]));
        $ctx['titolo'] = (string)($r['title'] ?? '');
        return $ctx;
    }
    if ($ctx['percorso'] !== '' && function_exists('sito_mappa')) {
        $v = sito_mappa()[$ctx['percorso']] ?? null;
        if ($v && isset($v['c']) && in_array($v['genere'], ['home', 'contenuto', 'test'], true)) {
            $c = $v['c'];
            require_once __DIR__ . '/contenuti.php';
            $ctx['tipo'] = 'altro';
            foreach (contenuti_tipi() as $k => $def) if ($def['tabella'] === $c['fonte']) { $ctx['tipo'] = $k; break; }
            $ctx['riga'] = $c['riga'];
            $ctx['categorie'] = $c['categorie'];
            $ctx['titolo'] = $c['titolo'];
            $ctx['c'] = $c;
        }
    }
    return $ctx;
}

/** Quanto una condizione calza con il contesto: null se non calza, altrimenti il suo peso. */
function modelli_condizione_peso(array $c, array $ctx): ?int {
    $ok = match ($c['dove']) {
        'sito' => true,
        'archivi' => $ctx['tipo'] === 'altro',
        'categoria' => $ctx['tipo'] !== 'altro' && $ctx['tipo'] !== 'pagine' && in_array($c['valore'], $ctx['categorie'], true),
        'contenuto' => $ctx['tipo'] !== 'altro' && $c['valore'] === $ctx['tipo'] . ':' . (int)($ctx['riga']['id'] ?? 0),
        default => $ctx['tipo'] === $c['dove'],
    };
    return $ok ? modelli_peso($c['dove']) : null;
}

/**
 * Il modello di un tipo per il contesto. Ritorna la riga, false se il contenuto ha scelto "nessuno",
 * null se non c'è nessun modello (si torna al codice di prima).
 */
function modelli_scegli(string $tipo, ?array $ctx = null) {
    $ctx = $ctx ?? modelli_contesto();
    // Anteprima dal pannello: ?header=ID o ?footer=ID forzano un modello (anche in bozza) per vederlo sul sito.
    if (defined('SITO_ANTEPRIMA') && SITO_ANTEPRIMA && isset($_GET[$tipo]) && ctype_digit((string)$_GET[$tipo])) {
        $m = modelli_leggi((int)$_GET[$tipo]);
        if ($m && $m['tipo'] === $tipo) return $m;
    }
    $pubblicati = modelli_pubblicati($tipo);
    $scelta = (int)($ctx['riga'][$tipo . '_id'] ?? 0);
    if ($scelta === -1) return false;
    if ($scelta > 0) foreach ($pubblicati as $m) if ((int)$m['id'] === $scelta) return $m;
    $migliore = null;
    $punteggio = -1;
    foreach ($pubblicati as $m) {
        $peso = -1;
        $escluso = false;
        foreach ($m['condizioni'] as $c) {
            $p = modelli_condizione_peso($c, $ctx);
            if ($p === null) continue;
            if ($c['escludi']) { $escluso = true; break; }
            $peso = max($peso, $p);
        }
        if ($escluso || $peso < 0) continue;
        $p = $peso * 1000 + (int)$m['priorita'];
        if ($p > $punteggio) { $punteggio = $p; $migliore = $m; }
    }
    if ($migliore) return $migliore;
    foreach ($pubblicati as $m) if ($m['predefinito']) return $m;
    return null;
}

// ------------------------------------------------------------
// Disegno
// ------------------------------------------------------------

/** L'albero pubblicato (o la bozza, se si chiede). */
function modelli_albero(array $m, bool $bozza = false): array {
    require_once __DIR__ . '/editor.php';
    if (!isset($m['struttura'])) $m = modelli_leggi((int)$m['id']) ?? $m;
    $a = $bozza ? (editor_decodifica($m['struttura_bozza'] ?? null) ?? editor_decodifica($m['struttura'] ?? null)) : editor_decodifica($m['struttura'] ?? null);
    return $a ?? ['versione' => 1, 'elementi' => []];
}

/** [html, css] del modello disegnato con l'editor. */
function modelli_disegna(array $m, bool $bozza = false): array {
    require_once __DIR__ . '/editor.php';
    $ctx = modelli_contesto();
    return editor_disegna(modelli_albero($m, $bozza), $ctx['percorso'] ?: '/');
}

/** La cornice di un header: dentro ci va l'HTML delle sezioni, oppure la tela dell'editor. */
function modelli_cornice(string $tipo, string $dentro, int $id = 0): string {
    $attr = ($id ? ' data-modello="' . $id . '"' : '');
    return $tipo === 'header'
        ? '<header class="ed-testata ed-modello" id="testata"' . $attr . '>' . $dentro . '</header>'
        : '<footer class="ed-piede ed-modello" id="piede"' . $attr . '>' . $dentro . '</footer>';
}

/**
 * L'header (o il footer) di questa pagina: [html, css], oppure null se nessun modello esiste (vista_pagina
 * usa allora il codice di prima), oppure ['', ''] se il contenuto ha scelto "Nessuno".
 * Nell'editor di un modello (GLOBALS modelli_anteprima) il suo posto lo prende la tela.
 */
function modelli_pezzo(string $tipo): ?array {
    try {
        $ant = $GLOBALS['modelli_anteprima'] ?? null;
        if (is_array($ant) && ($ant['tipo'] ?? '') === $tipo) {
            return [modelli_cornice($tipo, '<div class="ed-pagina ed-modello-pagina" id="ed-pagina"></div>', (int)($ant['id'] ?? 0)), ''];
        }
        // versioni: l'anteprima di una versione di un modello (admin/versioni.php?anteprima=N) disegna quell'albero al posto del modello scelto.
        $vs = $GLOBALS['modelli_versione'] ?? null;
        if (is_array($vs) && ($vs['tipo'] ?? '') === $tipo && is_array($vs['albero'] ?? null)) {
            require_once __DIR__ . '/editor.php';
            [$html, $css] = editor_disegna($vs['albero'], modelli_contesto()['percorso'] ?: '/');
            return [modelli_cornice($tipo, $html, (int)($vs['id'] ?? 0)), $css];
        }
        // temi: in anteprima di un tema (?preview=1&tema=nome) l'header e il footer sono quelli del tema, se li ha.
        $ta = $GLOBALS['tema_anteprima']['modelli'][$tipo] ?? null;
        if (is_array($ta)) {
            require_once __DIR__ . '/editor.php';
            $al = editor_valida($ta);
            if ($al !== null) {
                [$html, $css] = editor_disegna($al, modelli_contesto()['percorso'] ?: '/');
                return [modelli_cornice($tipo, $html), $css];
            }
        }
        $m = modelli_scegli($tipo);
        if ($m === null) return null;
        if ($m === false) return ['', ''];
        [$html, $css] = modelli_disegna($m);
        return [modelli_cornice($tipo, $html, (int)$m['id']), $css];
    } catch (Throwable $e) {
        // Un modello rotto non deve spegnere il sito: si torna al codice di prima e si lascia traccia nel log.
        error_log('modelli: ' . $e->getMessage());
        return null;
    }
}

function modelli_header_per(): ?array { return modelli_pezzo('header'); }
function modelli_footer_per(): ?array { return modelli_pezzo('footer'); }

/** Il segnaposto del contenuto nella tela dell'editor di un header o di un footer. */
function modelli_segnaposto_contenuto(string $tipo): string {
    return '<div class="wrap ed-segnaposto" aria-hidden="true"><div class="ed-segnaposto-riga ed-segnaposto-titolo"></div><div class="ed-segnaposto-riga"></div><div class="ed-segnaposto-riga"></div><div class="ed-segnaposto-riga corta"></div>'
        . '<p>Qui va il contenuto della pagina. ' . ($tipo === 'header' ? "L'header che stai componendo sta sopra." : 'Il footer che stai componendo sta sotto.') . '</p></div>';
}
