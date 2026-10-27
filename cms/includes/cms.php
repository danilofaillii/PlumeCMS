<?php
/**
 * Il CMS: nome, versione, radice, prefisso delle tabelle, base URL, impostazioni con i valori di partenza, moduli.
 *
 * Questo file lo carica config.php per primo (dopo config.local.php) e non tocca il database: le funzioni che
 * leggono le impostazioni passano da get_setting() quando il database c'è e, se manca una voce, dal predefinito
 * che i moduli dichiarano (moduli/<nome>/modulo.php, chiave 'impostazioni'). Così il nucleo non contiene niente
 * scritto fisso di un sito: il nome, l'indirizzo, l'email, le pagine speciali li dice l'installazione (impostazioni)
 * oppure il modulo del sito (danilofailli.it: moduli/danilofailli/).
 *
 * Il CMS gira nella radice del dominio o in una sottocartella: cms_base() è il percorso della sua cartella
 * ('' nella radice, '/new' su danilofailli.it), ricavato da DOCUMENT_ROOT o fissato con CMS_BASE nel config.local.php.
 */

// Chi carica questo file prima di config.php (i file ponte dei moduli, index.php) trova comunque le costanti del config.local.php.
if (!defined('DB_NAME') && !defined('CMS_INSTALLER') && is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';
if (!defined('CMS_NOME')) define('CMS_NOME', 'plumeCMS');          // nome del prodotto, scelto da Danilo il 26 ottobre 2026
if (!defined('CMS_PREFISSO')) define('CMS_PREFISSO', 'newdf_');     // il prefisso delle tabelle; l'installatore lo scrive nel config.local.php

/** La cartella del CMS sul disco (quella con includes/, admin/, sito/). */
function cms_radice(): string {
    return dirname(__DIR__);
}

if (!defined('CMS_ADMIN')) define('CMS_ADMIN', dirname(__DIR__) . '/admin'); // per le pagine dei moduli: gli include del pannello

/** La versione del CMS (file VERSIONE nella radice). */
function cms_versione(): string {
    static $v = null;
    if ($v !== null) return $v;
    $f = cms_radice() . '/VERSIONE';
    return $v = is_file($f) ? trim((string)file_get_contents($f)) : '0.0.0';
}

/** C'è un'installazione? Vuol dire: esiste includes/config.local.php con le credenziali. */
function cms_installato(): bool {
    return is_file(__DIR__ . '/config.local.php');
}

/**
 * Il percorso della cartella del CMS nell'indirizzo: '' se sta nella radice del dominio, '/new' se sta in /new/.
 * Da CMS_BASE se il config lo dice; altrimenti dalla posizione della cartella rispetto a DOCUMENT_ROOT; in ultimo
 * dallo script in esecuzione. Da riga di comando, senza altre informazioni, ''.
 */
function cms_base(): string {
    static $b = null;
    if ($b !== null) return $b;
    if (defined('CMS_BASE')) return $b = rtrim((string)CMS_BASE, '/');
    $pulisci = fn(string $p) => rtrim(str_replace('\\', '/', (string)(realpath($p) ?: $p)), '/');
    $radice = $pulisci(cms_radice());
    $doc = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($doc !== '') {
        $doc = $pulisci($doc);
        if ($radice === $doc) return $b = '';
        if (str_starts_with($radice, $doc . '/')) return $b = substr($radice, strlen($doc));
    }
    $file = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
    $nome = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if ($file !== '' && $nome !== '') {
        $file = $pulisci($file);
        if (str_starts_with($file, $radice . '/')) {
            $rel = substr($file, strlen($radice));
            if (str_ends_with($nome, $rel)) return $b = substr($nome, 0, -strlen($rel));
        }
    }
    return $b = '';
}

/** Lo schema e l'host della richiesta in corso ('' da riga di comando). */
function cms_origine(): string {
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') return '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    return ($https ? 'https' : 'http') . '://' . $host;
}

/**
 * L'indirizzo del sito pubblico: l'impostazione sito_url (scritta dall'installatore o dal pannello), altrimenti il
 * predefinito del modulo del sito, altrimenti quello rilevato dalla richiesta (schema, host e cartella del CMS).
 * Senza slash finale.
 */
function cms_url_sito(): string {
    static $u = null;
    if ($u !== null) return $u;
    $v = (string)cms_impostazione('sito_url', '');
    if ($v === '') { $o = cms_origine(); $v = $o !== '' ? $o . cms_base() : ''; }
    return $u = rtrim($v, '/');
}

/**
 * Il sito pubblico in una cartella (https://esempio.it/test): le viste scrivono i link dalla radice ("/blog/"),
 * qui diventano "/test/blog/". Tocca solo gli attributi che portano un indirizzo e che cominciano con una sola barra;
 * lascia stare "//host", gli indirizzi con lo schema e quelli che hanno già la cartella davanti (assets, immagini).
 * Nella radice del dominio non cambia niente. Dalla 1.1.7.
 */
function cms_con_cartella(string $html): string {
    $b = cms_sito_base();
    if ($b === '') return $html;
    $c = preg_quote(ltrim($b, '/'), '~');
    return (string)preg_replace('~(\s(?:href|src|action|poster|data-src)=)(["\'])/(?!/)(?!' . $c . '(?:[/"\'?#]|$))~i', '$1$2' . $b . '/', $html);
}

/** Il percorso del sito pubblico nell'indirizzo ('' nella radice del dominio, '/sub' in una sottocartella). */
function cms_sito_base(): string {
    $p = (string)parse_url(cms_url_sito(), PHP_URL_PATH);
    return rtrim($p, '/');
}

/** L'indirizzo assoluto del pannello, per esempio https://www.danilofailli.it/new/admin/. */
function cms_url_pannello(): string {
    $o = cms_url_sito();
    $origine = preg_replace('~^(https?://[^/]+).*$~', '$1', $o) ?: cms_origine();
    return $origine . cms_base() . '/admin/';
}

// ------------------------------------------------------------
// Impostazioni: database, poi i predefiniti dei moduli
// ------------------------------------------------------------

/** Un'impostazione: dal database se c'è (get_setting), altrimenti il predefinito del modulo, altrimenti $predefinito. */
function cms_impostazione(string $chiave, $predefinito = null) {
    static $cache = [];
    if (!array_key_exists($chiave, $cache)) {
        $v = null;
        if (function_exists('get_setting')) {
            try { $v = get_setting($chiave, null); } catch (Throwable $e) { $v = null; }
        }
        $cache[$chiave] = $v;
    }
    $v = $cache[$chiave];
    if ($v !== null && $v !== '') return $v;
    $m = cms_predefinito($chiave);
    return $m !== null ? $m : $predefinito;
}

/** Il predefinito di un'impostazione dichiarato da un modulo (chiave 'impostazioni' del manifesto). */
function cms_predefinito(string $chiave) {
    foreach (cms_moduli() as $m) {
        if (array_key_exists($chiave, $m['impostazioni'] ?? [])) return $m['impostazioni'][$chiave];
    }
    return null;
}

/** La cartella e l'indirizzo delle immagini caricate: uploads/ dentro il CMS, salvo diversa dichiarazione (config o modulo). */
function cms_uploads_dir(): string {
    if (defined('CMS_UPLOADS')) return rtrim((string)CMS_UPLOADS, '/');
    $m = cms_predefinito('uploads_dir');
    return $m !== null ? rtrim((string)$m, '/') : cms_radice() . '/uploads';
}

function cms_uploads_url(): string {
    if (defined('CMS_UPLOADS_URL')) return rtrim((string)CMS_UPLOADS_URL, '/');
    $m = cms_predefinito('uploads_url');
    return $m !== null ? rtrim((string)$m, '/') : cms_base() . '/uploads';
}

// ------------------------------------------------------------
// Moduli: moduli/<nome>/modulo.php ritorna un array (il manifesto)
// ------------------------------------------------------------

/**
 * I moduli presenti, nome => manifesto. Il manifesto è l'array che modulo.php ritorna; chiavi (tutte facoltative):
 *   etichetta, versione, descrizione
 *   impostazioni    => [chiave => valore di partenza]           (cms_impostazione li usa come ripiego)
 *   tipi_contenuto  => [chiave => definizione]                  (come contenuti_tipi(): tabella, etichette, campi)
 *   menu            => [voci del pannello]                      (stesso formato di admin-header.php: nome, url, icona, attivo, sottovoci)
 *   pannello        => [nome => file]                           (pagine del pannello: admin/<nome>.php le serve con cms_pagina_modulo)
 *   rotte           => [['percorso' => regex, 'file' => ...]]   (indirizzi pubblici serviti dal modulo prima della mappa del sito)
 *   installa        => callable                                 (crea le sue tabelle; la chiama l'installatore e cms_installa)
 *   cron            => [callable]                               (passi del cron giornaliero)
 *   widget          => [file]                                   (widget dell'editor da caricare con editor_registra_widget)
 *   azioni          => [nome => callable]                       (azioni dei pacchetti del pannello Aggiornamenti)
 *   filtri          => [nome => callable]                       (cms_filtro: il modulo cambia un valore del nucleo)
 *   eventi          => [nome => callable]                       (cms_evento: il nucleo avvisa, il modulo fa)
 */
function cms_moduli(): array {
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    foreach (glob(cms_radice() . '/moduli/*/modulo.php') ?: [] as $f) {
        $nome = basename(dirname($f));
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $nome) || is_file(dirname($f) . '/DISATTIVO')) continue;
        try {
            $def = require $f;
        } catch (Throwable $e) {
            error_log("cms: modulo $nome non caricato: " . $e->getMessage());
            continue;
        }
        if (!is_array($def)) continue;
        $m[$nome] = $def + ['nome' => $nome, 'etichetta' => ucfirst($nome), 'versione' => '', 'cartella' => dirname($f)];
    }
    return $m;
}

function cms_modulo(string $nome): ?array {
    return cms_moduli()[$nome] ?? null;
}

/** La cartella di un modulo sul disco e il suo indirizzo (per gli asset). */
function cms_modulo_dir(string $nome): string {
    return cms_radice() . '/moduli/' . $nome;
}

function cms_modulo_url(string $nome): string {
    return cms_base() . '/moduli/' . $nome;
}

/** Un valore passa per i filtri dei moduli: ognuno riceve il valore e gli argomenti e ritorna il valore nuovo. */
function cms_filtro(string $nome, $valore, ...$arg) {
    foreach (cms_moduli() as $m) {
        $f = $m['filtri'][$nome] ?? null;
        if ($f && is_callable($f)) $valore = $f($valore, ...$arg);
    }
    return $valore;
}

/** Un evento: ogni modulo che lo ascolta viene chiamato con gli argomenti. */
function cms_evento(string $nome, ...$arg): void {
    foreach (cms_moduli() as $m) {
        $f = $m['eventi'][$nome] ?? null;
        if ($f && is_callable($f)) $f(...$arg);
    }
}

/** Tutte le voci di una chiave a elenco dei manifesti (menu, rotte, widget, cron...), in ordine di modulo. */
function cms_moduli_elenco(string $chiave): array {
    $out = [];
    foreach (cms_moduli() as $nome => $m) foreach ((array)($m[$chiave] ?? []) as $k => $v) $out[] = ['modulo' => $nome, 'chiave' => $k, 'valore' => $v];
    return $out;
}

/** Serve una pagina del pannello dichiarata da un modulo (lo shim admin/<nome>.php chiama questa funzione). */
function cms_pagina_modulo(string $modulo, string $pagina): void {
    $m = cms_modulo($modulo);
    $f = $m['pannello'][$pagina] ?? $m['api'][$pagina] ?? null;
    if (!$m || !$f || !is_file($f)) { http_response_code(404); exit('Pagina del modulo non trovata.'); }
    require $f;
}

/**
 * Le voci di menu dei moduli dentro il menu del pannello. Una voce del modulo: nome, url, icona, attivo (i file che la
 * accendono), attivo_tipo (il ?tipo= che la accende), sotto (sottovoci), admin (solo amministratore), dopo (il nome della
 * voce del nucleo dopo cui va) oppure gruppo (il nome del gruppo in cui entra come sottovoce). $qui e $tipo: la pagina aperta.
 */
function cms_menu_integra(array $menu, string $qui, string $tipo, bool $admin): array {
    $voce = function (array $v) use ($qui, $tipo): array {
        $attivo = in_array($qui, (array)($v['attivo'] ?? []), true) && (empty($v['attivo_tipo']) || $v['attivo_tipo'] === $tipo);
        $sotto = [];
        foreach ((array)($v['sotto'] ?? []) as $s) $sotto[] = [(string)$s['nome'], (string)$s['url'], in_array($qui, (array)($s['attivo'] ?? []), true)];
        return [(string)$v['nome'], (string)$v['url'], (string)($v['icona'] ?? '&#9679;'), $attivo, $sotto];
    };
    foreach (cms_moduli_elenco('menu') as $x) {
        $v = $x['valore'];
        if (!empty($v['admin']) && !$admin) continue;
        if (!empty($v['gruppo'])) {
            foreach ($menu as &$g) {
                if (($g[0] ?? '') !== $v['gruppo']) continue;
                $sotto = $g[4] ?? []; $posto = count($sotto);
                if (!empty($v['dopo'])) foreach ($sotto as $i => $s) if (($s[0] ?? '') === $v['dopo']) { $posto = $i + 1; break; }
                array_splice($sotto, $posto, 0, [[(string)$v['nome'], (string)$v['url'], in_array($qui, (array)($v['attivo'] ?? []), true)]]);
                $g[4] = $sotto;
                if (in_array($qui, (array)($v['attivo'] ?? []), true)) $g[3] = true;
            }
            unset($g);
            continue;
        }
        $nuova = $voce($v);
        $posto = count($menu);
        if (!empty($v['dopo'])) foreach ($menu as $i => $g) if (($g[0] ?? '') === $v['dopo']) { $posto = $i + 1; break; }
        array_splice($menu, $posto, 0, [$nuova]);
    }
    return $menu;
}

/** Crea le tabelle di tutti i moduli (l'installatore e la pagina Informazioni). Ritorna il log. */
function cms_moduli_installa(): array {
    $log = [];
    foreach (cms_moduli() as $nome => $m) {
        $f = $m['installa'] ?? null;
        if ($f && is_callable($f)) { $r = $f(); $log[] = "modulo $nome: " . (is_string($r) && $r !== '' ? $r : 'tabelle a posto'); }
    }
    return $log;
}
