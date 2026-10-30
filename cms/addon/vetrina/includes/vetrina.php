<?php
/**
 * Il motore della Vetrina (addon, 2.0.0): tabelle, prodotti, varianti, categorie, carrello firmato, prezzi, ordini, email.
 *
 * Regole che non si cambiano:
 *  - il prezzo e il totale si calcolano SEMPRE qui, dal database (vt_carrello_calcola): il browser manda solo quale
 *    prodotto, quale variante e quante unità; un prezzo scritto nel carrello o nel modulo non si legge mai;
 *  - il carrello è un cookie firmato con HMAC (vt_carrello_leggi/scrivi), si crea solo quando si aggiunge un prodotto;
 *  - le scorte scendono all'ordine dentro una transazione (UPDATE ... WHERE scorta >= qta): due ordini insieme non
 *    vendono l'ultimo pezzo due volte; un ordine annullato le rimette;
 *  - i soldi sono interi in centesimi; IVA inclusa nei prezzi.
 * Le tabelle sono utf8 come quelle del nucleo: i caratteri a 4 byte (emoji) si tolgono dai testi (vt_testo).
 */
require_once dirname(__DIR__, 3) . '/includes/cms.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

const VT_STATI = ['nuovo' => 'Nuovo', 'pagato' => 'Pagato', 'spedito' => 'Spedito', 'consegnato' => 'Consegnato', 'annullato' => 'Annullato'];
const VT_METODI = ['bonifico' => 'Bonifico bancario', 'consegna' => 'Pagamento alla consegna o al ritiro', 'link' => 'Pagamento online con il link'];
const VT_QTA_MAX = 99;          // unità di una riga del carrello
const VT_RIGHE_MAX = 30;        // righe del carrello
const VT_ORDINI_ORA = 5;        // richieste d'ordine per indirizzo IP in un'ora
const VT_COOKIE = 'vetrina_carrello';
const VT_RISERVATI = ['carrello', 'ordine', 'condizioni', 'categoria'];

// ------------------------------------------------------------
// Tabelle
// ------------------------------------------------------------

function vt_tabelle(): array {
    return [
        'vetrina_categorie' => 'CREATE TABLE IF NOT EXISTS ' . tbl('vetrina_categorie') . ' (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(120) NOT NULL,
            `slug` VARCHAR(140) NOT NULL,
            `descrizione` TEXT,
            `ordine` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
        'vetrina_prodotti' => 'CREATE TABLE IF NOT EXISTS ' . tbl('vetrina_prodotti') . ' (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(200) NOT NULL,
            `slug` VARCHAR(190) NOT NULL,
            `breve` VARCHAR(400) NOT NULL DEFAULT \'\',
            `descrizione` TEXT,
            `prezzo_cent` INT(11) NOT NULL DEFAULT 0,
            `scontato_cent` INT(11) DEFAULT NULL,
            `aliquota` DECIMAL(4,1) NOT NULL DEFAULT 22.0,
            `foto` TEXT,
            `opzione1` VARCHAR(40) NOT NULL DEFAULT \'\',
            `opzione2` VARCHAR(40) NOT NULL DEFAULT \'\',
            `scorta` INT(11) DEFAULT NULL,
            `esaurito` TINYINT(1) NOT NULL DEFAULT 0,
            `evidenza` TINYINT(1) NOT NULL DEFAULT 0,
            `pubblicato` TINYINT(1) NOT NULL DEFAULT 1,
            `codice` VARCHAR(60) NOT NULL DEFAULT \'\',
            `ordine` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `slug` (`slug`), KEY `pubblicato` (`pubblicato`, `ordine`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
        'vetrina_varianti' => 'CREATE TABLE IF NOT EXISTS ' . tbl('vetrina_varianti') . ' (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `prodotto_id` INT(11) NOT NULL,
            `valore1` VARCHAR(60) NOT NULL DEFAULT \'\',
            `valore2` VARCHAR(60) NOT NULL DEFAULT \'\',
            `prezzo_cent` INT(11) DEFAULT NULL,
            `scorta` INT(11) DEFAULT NULL,
            `attiva` TINYINT(1) NOT NULL DEFAULT 1,
            `ordine` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), KEY `prodotto` (`prodotto_id`, `ordine`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
        'vetrina_prodotti_categorie' => 'CREATE TABLE IF NOT EXISTS ' . tbl('vetrina_prodotti_categorie') . ' (
            `prodotto_id` INT(11) NOT NULL,
            `categoria_id` INT(11) NOT NULL,
            PRIMARY KEY (`prodotto_id`, `categoria_id`), KEY `categoria` (`categoria_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
        'vetrina_ordini' => 'CREATE TABLE IF NOT EXISTS ' . tbl('vetrina_ordini') . ' (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `numero` VARCHAR(20) NOT NULL,
            `stato` VARCHAR(12) NOT NULL DEFAULT \'nuovo\',
            `nome` VARCHAR(120) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `telefono` VARCHAR(40) NOT NULL DEFAULT \'\',
            `indirizzo` VARCHAR(200) NOT NULL DEFAULT \'\',
            `cap` VARCHAR(10) NOT NULL DEFAULT \'\',
            `citta` VARCHAR(100) NOT NULL DEFAULT \'\',
            `consegna` VARCHAR(12) NOT NULL,
            `metodo` VARCHAR(12) NOT NULL,
            `note` TEXT,
            `righe` MEDIUMTEXT NOT NULL,
            `subtotale_cent` INT(11) NOT NULL,
            `spedizione_cent` INT(11) NOT NULL DEFAULT 0,
            `totale_cent` INT(11) NOT NULL,
            `iva_cent` INT(11) NOT NULL DEFAULT 0,
            `consenso` VARCHAR(20) NOT NULL DEFAULT \'\',
            `storia` TEXT,
            `ip` VARCHAR(45) NOT NULL DEFAULT \'\',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `numero` (`numero`), KEY `stato` (`stato`, `creato`), KEY `ip` (`ip`, `creato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    ];
}

/** Crea le tabelle (accensione dell'addon). Ritorna una riga per il log. */
function vt_installa(): string {
    foreach (vt_tabelle() as $sql) db()->exec($sql);
    save_setting('vetrina_schema', md5(serialize(vt_tabelle())));
    return 'tabelle della vetrina a posto';
}

/** Le tabelle ci sono? Si controlla una volta per richiesta con l'impronta (come moduli_schema). */
function vt_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    if (get_setting('vetrina_schema', '') !== md5(serialize(vt_tabelle()))) vt_installa();
    $fatto = true;
}

// ------------------------------------------------------------
// Testi, soldi, impostazioni
// ------------------------------------------------------------

/** Un testo semplice pulito: senza caratteri di controllo (tranne gli a capo se $righe), senza 4 byte, al massimo $max caratteri. */
function vt_testo($v, int $max = 200, bool $righe = false): string {
    $s = is_string($v) || is_numeric($v) ? (string)$v : '';
    if (!mb_check_encoding($s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    $s = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
    $s = $righe ? (string)preg_replace('/[^\P{C}\n]/u', '', str_replace("\r\n", "\n", $s)) : (string)preg_replace('/\p{C}/u', ' ', $s);
    return mb_substr(trim($s), 0, $max);
}

/** "12,50" o "12.50" o "12" in centesimi; null se non è un prezzo (negativo, testo, oltre 1 milione). */
function vt_cent($v): ?int {
    $s = str_replace([' ', '€', "\xc2\xa0"], '', trim((string)$v));
    if ($s === '') return null;
    if (str_contains($s, ',') && str_contains($s, '.')) $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $s)) return null;
    $c = (int)round((float)$s * 100);
    return $c <= 100000000 ? $c : null;
}

/** I centesimi scritti come si leggono: "€ 1.234,50". */
function vt_euro(int $cent): string {
    return '€ ' . number_format($cent / 100, 2, ',', '.');
}

/** I centesimi per un campo del pannello: "12,50" (vuoto per null). */
function vt_cent_campo(?int $c): string {
    return $c === null ? '' : number_format($c / 100, 2, ',', '');
}

function vt_impostazioni_predefinite(): array {
    return [
        'percorso' => 'negozio',
        'nome_negozio' => 'Negozio',
        'intro' => '',
        'spedizione' => 1, 'spedizione_cent' => 700, 'gratis_oltre_cent' => 6000, 'spedizione_testo' => 'Spedizione con corriere in Italia, in 2-4 giorni lavorativi.',
        'ritiro' => 1, 'ritiro_testo' => 'Ritiro in negozio negli orari di apertura: ti scriviamo quando è pronto.',
        'bonifico' => 1, 'iban' => '', 'intestatario' => '', 'banca' => '',
        'consegna_pag' => 1,
        'link' => 0, 'link_url' => '',
        'condizioni' => "Prezzi in euro, IVA inclusa.\nL'ordine diventa definitivo quando lo confermiamo per email.\nPuoi recedere entro 14 giorni dalla consegna: scrivici e ti diciamo come rendere il prodotto.",
        'attivita_nome' => '', 'attivita_indirizzo' => '', 'attivita_piva' => '', 'attivita_email' => '', 'attivita_telefono' => '',
        'email_avvisi' => '',
        'aliquota' => 22,
    ];
}

function vt_impostazioni(): array {
    static $c = null;
    if ($c !== null) return $c;
    $s = json_decode((string)get_setting('vetrina_impostazioni', ''), true);
    return $c = (is_array($s) ? $s : []) + vt_impostazioni_predefinite();
}

/** Salva le impostazioni (già controllate dalla pagina del pannello). */
function vt_impostazioni_salva(array $s): void {
    save_setting('vetrina_impostazioni', json_encode(array_intersect_key($s, vt_impostazioni_predefinite()) + vt_impostazioni(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Il percorso del negozio, con le barre: "/negozio/". */
function vt_base(): string {
    $p = preg_replace('/[^a-z0-9-]/', '', (string)vt_impostazioni()['percorso']) ?: 'negozio';
    return '/' . $p . '/';
}

/** Questo indirizzo è della vetrina? */
function vt_mio_percorso(string $percorso): bool {
    $b = vt_base();
    return $percorso === $b || $percorso === rtrim($b, '/') || str_starts_with($percorso, $b);
}

/** L'indirizzo pubblico assoluto di un percorso del sito ("/negozio/x/" con la cartella e il dominio). */
function vt_url_assoluto(string $percorso): string {
    return rtrim(cms_url_sito(), '/') . $percorso;
}

/** I metodi di pagamento accesi, chiave => etichetta. */
function vt_metodi_attivi(): array {
    $s = vt_impostazioni();
    $out = [];
    if ($s['bonifico'] && trim($s['iban']) !== '') $out['bonifico'] = VT_METODI['bonifico'];
    if ($s['consegna_pag']) $out['consegna'] = VT_METODI['consegna'];
    if ($s['link'] && vt_link_valido((string)$s['link_url'])) $out['link'] = VT_METODI['link'];
    return $out;
}

/** Le consegne accese, chiave => etichetta. */
function vt_consegne_attive(): array {
    $s = vt_impostazioni();
    $out = [];
    if ($s['spedizione']) $out['spedizione'] = 'Spedizione';
    if ($s['ritiro']) $out['ritiro'] = 'Ritiro in negozio';
    return $out;
}

/**
 * Un link di pagamento si accetta solo se è https, con un dominio vero, senza credenziali e senza spazi o virgolette.
 * Così javascript:, data: e simili non entrano mai (né nel pannello né nelle email).
 */
function vt_link_valido(string $u): bool {
    $u = trim($u);
    if ($u === '' || strlen($u) > 300 || !preg_match('~^https://[a-z0-9.-]+\.[a-z]{2,}(/[^\s"\'<>\\\\]*)?$~i', $u)) return false;
    $p = parse_url($u);
    return is_array($p) && ($p['scheme'] ?? '') === 'https' && !isset($p['user']) && !isset($p['pass']) && filter_var($u, FILTER_VALIDATE_URL) !== false;
}

/**
 * Il link di pagamento per un ordine. PayPal.me: l'importo si aggiunge all'indirizzo (paypal.me/nome/25.50EUR).
 * Stripe Payment Link (buy.stripe.com): il numero d'ordine e l'email come client_reference_id e prefilled_email.
 * Un altro link resta com'è (l'importo lo scrive la persona).
 */
function vt_link_pagamento(array $ordine): string {
    $u = trim((string)vt_impostazioni()['link_url']);
    if (!vt_link_valido($u)) return '';
    $host = strtolower((string)parse_url($u, PHP_URL_HOST));
    $importo = number_format((int)$ordine['totale_cent'] / 100, 2, '.', '');
    if ($host === 'paypal.me' || $host === 'www.paypal.me' || ($host === 'www.paypal.com' && str_contains((string)parse_url($u, PHP_URL_PATH), '/paypalme/'))) {
        $u = preg_replace('~/\d+(\.\d{1,2})?(EUR)?/?$~i', '', rtrim(strtok($u, '?#'), '/'));
        return $u . '/' . $importo . 'EUR';
    }
    if ($host === 'buy.stripe.com') {
        return $u . (str_contains($u, '?') ? '&' : '?') . http_build_query(['client_reference_id' => preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$ordine['numero']), 'prefilled_email' => (string)$ordine['email']]);
    }
    return $u;
}

// ------------------------------------------------------------
// Categorie
// ------------------------------------------------------------

function vt_slug(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', 'ç' => 'c', 'ñ' => 'n', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u']);
    $s = trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return substr($s, 0, 120) ?: 'prodotto';
}

function vt_categorie(): array {
    vt_schema();
    return db()->query('SELECT * FROM ' . tbl('vetrina_categorie') . ' ORDER BY ordine, nome')->fetchAll();
}

function vt_categoria_per_slug(string $slug): ?array {
    vt_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('vetrina_categorie') . ' WHERE slug = ?');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

/** Crea o modifica una categoria. Ritorna l'id oppure un errore (testo). */
function vt_categoria_salva(int $id, string $nome, string $descrizione, int $ordine): int|string {
    vt_schema();
    $nome = vt_testo($nome, 120);
    if ($nome === '') return 'Scrivi il nome della categoria.';
    $slug = vt_slug($nome);
    $st = db()->prepare('SELECT id FROM ' . tbl('vetrina_categorie') . ' WHERE slug = ? AND id <> ?');
    $st->execute([$slug, $id]);
    if ($st->fetchColumn()) return 'C\'è già una categoria con questo nome.';
    $descrizione = vt_testo($descrizione, 1000, true);
    if ($id > 0) {
        db()->prepare('UPDATE ' . tbl('vetrina_categorie') . ' SET nome = ?, slug = ?, descrizione = ?, ordine = ? WHERE id = ?')->execute([$nome, $slug, $descrizione, $ordine, $id]);
        return $id;
    }
    db()->prepare('INSERT INTO ' . tbl('vetrina_categorie') . ' (nome, slug, descrizione, ordine) VALUES (?, ?, ?, ?)')->execute([$nome, $slug, $descrizione, $ordine]);
    return (int)db()->lastInsertId();
}

function vt_categoria_elimina(int $id): void {
    vt_schema();
    db()->prepare('DELETE FROM ' . tbl('vetrina_prodotti_categorie') . ' WHERE categoria_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('vetrina_categorie') . ' WHERE id = ?')->execute([$id]);
}

// ------------------------------------------------------------
// Prodotti
// ------------------------------------------------------------

/** Un prodotto dal database, con foto (array), varianti (solo attive se $soloAttive) e id delle categorie. */
function vt_prodotto_completa(array $p, bool $soloAttive = true): array {
    $p['id'] = (int)$p['id'];
    $p['prezzo_cent'] = (int)$p['prezzo_cent'];
    $p['scontato_cent'] = $p['scontato_cent'] === null ? null : (int)$p['scontato_cent'];
    $p['scorta'] = $p['scorta'] === null ? null : (int)$p['scorta'];
    $p['foto'] = array_values(array_filter((array)(json_decode((string)$p['foto'], true) ?: []), 'is_string'));
    $st = db()->prepare('SELECT * FROM ' . tbl('vetrina_varianti') . ' WHERE prodotto_id = ?' . ($soloAttive ? ' AND attiva = 1' : '') . ' ORDER BY ordine, id');
    $st->execute([$p['id']]);
    $p['varianti'] = array_map(function ($v) {
        $v['id'] = (int)$v['id'];
        $v['prezzo_cent'] = $v['prezzo_cent'] === null ? null : (int)$v['prezzo_cent'];
        $v['scorta'] = $v['scorta'] === null ? null : (int)$v['scorta'];
        return $v;
    }, $st->fetchAll());
    $st = db()->prepare('SELECT categoria_id FROM ' . tbl('vetrina_prodotti_categorie') . ' WHERE prodotto_id = ?');
    $st->execute([$p['id']]);
    $p['categorie'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    return $p;
}

function vt_prodotto(int $id, bool $soloPubblicato = true): ?array {
    vt_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('vetrina_prodotti') . ' WHERE id = ?' . ($soloPubblicato ? ' AND pubblicato = 1' : ''));
    $st->execute([$id]);
    $p = $st->fetch();
    return $p ? vt_prodotto_completa($p, $soloPubblicato) : null;
}

function vt_prodotto_per_slug(string $slug): ?array {
    vt_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('vetrina_prodotti') . ' WHERE slug = ? AND pubblicato = 1');
    $st->execute([$slug]);
    $p = $st->fetch();
    return $p ? vt_prodotto_completa($p) : null;
}

/**
 * I prodotti per l'elenco del sito, del widget o del pannello. $f: categoria (id), evidenza (bool), tutti (anche non
 * pubblicati, per il pannello), cerca (testo), limite. Ogni parametro va nella query come segnaposto, mai nel testo.
 */
function vt_prodotti(array $f = []): array {
    vt_schema();
    $w = []; $par = [];
    if (empty($f['tutti'])) $w[] = 'p.pubblicato = 1';
    if (!empty($f['evidenza'])) $w[] = 'p.evidenza = 1';
    if (!empty($f['categoria'])) { $w[] = 'p.id IN (SELECT prodotto_id FROM ' . tbl('vetrina_prodotti_categorie') . ' WHERE categoria_id = ?)'; $par[] = (int)$f['categoria']; }
    if (($f['cerca'] ?? '') !== '') { $w[] = 'p.nome LIKE ?'; $par[] = '%' . addcslashes((string)$f['cerca'], '%_\\') . '%'; }
    $ordina = match ($f['ordina'] ?? '') { 'prezzo' => 'COALESCE(p.scontato_cent, p.prezzo_cent), p.id', 'prezzo-giu' => 'COALESCE(p.scontato_cent, p.prezzo_cent) DESC, p.id', 'nuovi' => 'p.id DESC', default => 'p.ordine, p.id DESC' };
    $sql = 'SELECT p.* FROM ' . tbl('vetrina_prodotti') . ' p' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY ' . $ordina;
    $lim = (int)($f['limite'] ?? 0);
    if ($lim > 0) $sql .= ' LIMIT ' . min(500, $lim);
    $st = db()->prepare($sql);
    $st->execute($par);
    return array_map('vt_prodotto_completa', $st->fetchAll());
}

/** Il prezzo che si paga per un prodotto (o una sua variante): quello della variante, se no lo scontato, se no il pieno. */
function vt_prezzo(array $p, ?array $v = null): int {
    if ($v && $v['prezzo_cent'] !== null) return (int)$v['prezzo_cent'];
    return $p['scontato_cent'] !== null && $p['scontato_cent'] < $p['prezzo_cent'] ? (int)$p['scontato_cent'] : (int)$p['prezzo_cent'];
}

/** Il prezzo pieno barrato da mostrare accanto (solo se c'è uno sconto e la variante non ha un prezzo suo). */
function vt_prezzo_pieno(array $p, ?array $v = null): ?int {
    if ($v && $v['prezzo_cent'] !== null) return null;
    return $p['scontato_cent'] !== null && $p['scontato_cent'] < $p['prezzo_cent'] ? (int)$p['prezzo_cent'] : null;
}

/** Si può comprare? Un prodotto con le varianti è disponibile se almeno una lo è. */
function vt_disponibile(array $p, ?array $v = null): bool {
    if (!empty($p['esaurito'])) return false;
    if ($v) return !empty($v['attiva']) && ($v['scorta'] === null || $v['scorta'] > 0);
    if ($p['varianti']) { foreach ($p['varianti'] as $x) if (vt_disponibile($p, $x)) return true; return false; }
    return $p['scorta'] === null || $p['scorta'] > 0;
}

/** Il nome di una variante: "M, Rosso". */
function vt_variante_nome(array $v): string {
    return implode(', ', array_filter([trim((string)$v['valore1']), trim((string)$v['valore2'])], fn($x) => $x !== ''));
}

/** Le foto ammesse: solo immagini della cartella delle immagini del sito (indirizzi che cominciano con quello degli uploads). */
function vt_foto_ammessa(string $u): bool {
    $base = rtrim(cms_uploads_url(), '/') . '/';
    return str_starts_with($u, $base) && !str_contains($u, '..') && preg_match('~^[A-Za-z0-9_./ %-]+\.(jpe?g|png|webp|gif|avif)$~i', $u) === 1;
}

/**
 * Salva un prodotto dal pannello. $d: i campi del form già letti. Ritorna l'id oppure un errore da mostrare.
 * Varianti: righe [id, valore1, valore2, prezzo, scorta, togli]; quelle vuote si ignorano.
 */
function vt_prodotto_salva(int $id, array $d): int|string {
    vt_schema();
    $nome = vt_testo($d['nome'] ?? '', 200);
    if ($nome === '') return 'Scrivi il nome del prodotto.';
    $prezzo = vt_cent($d['prezzo'] ?? '');
    if ($prezzo === null) return 'Scrivi il prezzo in euro, per esempio 24,90.';
    $sc = trim((string)($d['scontato'] ?? '')) === '' ? null : vt_cent($d['scontato']);
    if (trim((string)($d['scontato'] ?? '')) !== '' && $sc === null) return 'Il prezzo scontato non si legge: scrivilo come 19,90 oppure lascialo vuoto.';
    if ($sc !== null && $sc >= $prezzo) return 'Il prezzo scontato deve essere più basso del prezzo pieno.';
    $scorta = trim((string)($d['scorta'] ?? '')) === '' ? null : (ctype_digit(trim((string)$d['scorta'])) ? min(1000000, (int)$d['scorta']) : -1);
    if ($scorta === -1) return 'Le scorte sono un numero intero (0 o più), oppure vuoto se non le conti.';
    $aliquota = (float)str_replace(',', '.', (string)($d['aliquota'] ?? vt_impostazioni()['aliquota']));
    if ($aliquota < 0 || $aliquota > 50) return 'L\'aliquota IVA va da 0 a 50.';
    $slug = vt_slug(trim((string)($d['slug'] ?? '')) !== '' ? (string)$d['slug'] : $nome);
    if (in_array($slug, VT_RISERVATI, true)) $slug .= '-1';
    $base = $slug; $k = 2;
    while (true) {
        $st = db()->prepare('SELECT id FROM ' . tbl('vetrina_prodotti') . ' WHERE slug = ? AND id <> ?');
        $st->execute([$slug, $id]);
        if (!$st->fetchColumn()) break;
        $slug = $base . '-' . $k++;
    }
    $foto = array_values(array_unique(array_filter(array_map('strval', (array)($d['foto'] ?? [])), 'vt_foto_ammessa')));
    $campi = [
        'nome' => $nome, 'slug' => $slug, 'breve' => vt_testo($d['breve'] ?? '', 400), 'descrizione' => vt_testo($d['descrizione'] ?? '', 20000, true),
        'prezzo_cent' => $prezzo, 'scontato_cent' => $sc, 'aliquota' => $aliquota, 'foto' => json_encode(array_slice($foto, 0, 12), JSON_UNESCAPED_SLASHES),
        'opzione1' => vt_testo($d['opzione1'] ?? '', 40), 'opzione2' => vt_testo($d['opzione2'] ?? '', 40), 'scorta' => $scorta,
        'esaurito' => empty($d['esaurito']) ? 0 : 1, 'evidenza' => empty($d['evidenza']) ? 0 : 1, 'pubblicato' => empty($d['pubblicato']) ? 0 : 1,
        'codice' => vt_testo($d['codice'] ?? '', 60), 'ordine' => max(-9999, min(9999, (int)($d['ordine'] ?? 0))),
    ];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($campi)));
            $pdo->prepare('UPDATE ' . tbl('vetrina_prodotti') . " SET $set, aggiornato = NOW() WHERE id = ?")->execute([...array_values($campi), $id]);
        } else {
            $col = implode(', ', array_map(fn($k) => "`$k`", array_keys($campi)));
            $pdo->prepare('INSERT INTO ' . tbl('vetrina_prodotti') . " ($col, creato, aggiornato) VALUES (" . implode(', ', array_fill(0, count($campi), '?')) . ', NOW(), NOW())')->execute(array_values($campi));
            $id = (int)$pdo->lastInsertId();
        }
        // Categorie: solo quelle che esistono.
        $pdo->prepare('DELETE FROM ' . tbl('vetrina_prodotti_categorie') . ' WHERE prodotto_id = ?')->execute([$id]);
        $esistono = array_map('intval', $pdo->query('SELECT id FROM ' . tbl('vetrina_categorie'))->fetchAll(PDO::FETCH_COLUMN));
        foreach (array_unique(array_map('intval', (array)($d['categorie'] ?? []))) as $c) {
            if (in_array($c, $esistono, true)) $pdo->prepare('INSERT INTO ' . tbl('vetrina_prodotti_categorie') . ' (prodotto_id, categoria_id) VALUES (?, ?)')->execute([$id, $c]);
        }
        // Varianti: si aggiornano quelle di questo prodotto (un id di un altro prodotto non si tocca), si aggiungono le nuove.
        $st = $pdo->prepare('SELECT id FROM ' . tbl('vetrina_varianti') . ' WHERE prodotto_id = ?');
        $st->execute([$id]);
        $mie = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $ordine = 0;
        foreach ((array)($d['varianti'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $vid = (int)($r['id'] ?? 0);
            if ($vid && !in_array($vid, $mie, true)) continue;
            $v1 = vt_testo($r['valore1'] ?? '', 60); $v2 = vt_testo($r['valore2'] ?? '', 60);
            if (!empty($r['togli']) || ($v1 === '' && $v2 === '')) {
                if ($vid) $pdo->prepare('UPDATE ' . tbl('vetrina_varianti') . ' SET attiva = 0 WHERE id = ?')->execute([$vid]); // le varianti già ordinate restano nei vecchi ordini
                continue;
            }
            $vp = trim((string)($r['prezzo'] ?? '')) === '' ? null : vt_cent($r['prezzo']);
            if (trim((string)($r['prezzo'] ?? '')) !== '' && $vp === null) throw new RuntimeException('Il prezzo della variante «' . trim($v1 . ' ' . $v2) . '» non si legge.');
            $vs = trim((string)($r['scorta'] ?? '')) === '' ? null : (ctype_digit(trim((string)$r['scorta'])) ? min(1000000, (int)$r['scorta']) : -1);
            if ($vs === -1) throw new RuntimeException('Le scorte della variante «' . trim($v1 . ' ' . $v2) . '» sono un numero intero, oppure vuoto.');
            if ($vid) $pdo->prepare('UPDATE ' . tbl('vetrina_varianti') . ' SET valore1 = ?, valore2 = ?, prezzo_cent = ?, scorta = ?, attiva = 1, ordine = ? WHERE id = ?')->execute([$v1, $v2, $vp, $vs, $ordine++, $vid]);
            else $pdo->prepare('INSERT INTO ' . tbl('vetrina_varianti') . ' (prodotto_id, valore1, valore2, prezzo_cent, scorta, attiva, ordine) VALUES (?, ?, ?, ?, ?, 1, ?)')->execute([$id, $v1, $v2, $vp, $vs, $ordine++]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($e instanceof RuntimeException && !$e instanceof PDOException) return $e->getMessage();
        throw $e;
    }
    return $id;
}

function vt_prodotto_elimina(int $id): void {
    vt_schema();
    // Le righe degli ordini sono una copia: eliminare un prodotto non cambia gli ordini già arrivati.
    db()->prepare('DELETE FROM ' . tbl('vetrina_prodotti_categorie') . ' WHERE prodotto_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('vetrina_varianti') . ' WHERE prodotto_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('vetrina_prodotti') . ' WHERE id = ?')->execute([$id]);
}

/** Le schede dei prodotti per la sitemap (filtro sitemap_voci del nucleo). */
function vt_sitemap_voci(): array {
    $out = [['loc' => vt_url_assoluto(vt_base()), 'lastmod' => '', 'tipo' => 'pagine', 'immagine' => '']];
    foreach (vt_prodotti() as $p) {
        $out[] = ['loc' => vt_url_assoluto(vt_base() . $p['slug'] . '/'), 'lastmod' => (string)$p['aggiornato'], 'tipo' => 'pagine',
            'immagine' => $p['foto'] ? (preg_match('~^https?://~', $p['foto'][0]) ? $p['foto'][0] : vt_origine() . $p['foto'][0]) : ''];
    }
    return $out;
}

/** Lo schema e l'host del sito ("https://esempio.it"), per gli indirizzi che cominciano con "/". */
function vt_origine(): string {
    return (string)preg_replace('~^(https?://[^/]+).*$~', '$1', cms_url_sito());
}

// ------------------------------------------------------------
// Carrello: cookie firmato con le righe [prodotto, variante, quantità]. Mai i prezzi.
// ------------------------------------------------------------

function vt_segreto(): string {
    $s = (string)get_setting('vetrina_segreto', '');
    if (strlen($s) < 32) { $s = bin2hex(random_bytes(32)); save_setting('vetrina_segreto', $s); }
    return $s;
}

/** Le righe del carrello dal cookie: [[p, v, q], ...]. Un cookie manomesso (firma sbagliata) vale come carrello vuoto. */
function vt_carrello_leggi(): array {
    $c = (string)($_COOKIE[VT_COOKIE] ?? '');
    if ($c === '' || strlen($c) > 3000 || !str_contains($c, '.')) return [];
    [$dati, $firma] = explode('.', $c, 2);
    if (!hash_equals(hash_hmac('sha256', $dati, vt_segreto()), $firma)) return [];
    $r = json_decode((string)base64_decode(strtr($dati, '-_', '+/'), true), true);
    if (!is_array($r)) return [];
    $out = [];
    foreach (array_slice($r, 0, VT_RIGHE_MAX) as $x) {
        if (!is_array($x) || count($x) !== 3) continue;
        [$p, $v, $q] = array_map('intval', $x);
        if ($p > 0 && $v >= 0 && $q >= 1) $out[] = [$p, $v, min(VT_QTA_MAX, $q)];
    }
    return $out;
}

/** Scrive il carrello nel cookie (vuoto: lo cancella). Il cookie dura 30 giorni, HttpOnly, SameSite=Lax. */
function vt_carrello_scrivi(array $righe): void {
    $righe = array_slice(array_values($righe), 0, VT_RIGHE_MAX);
    $opz = ['path' => (cms_sito_base() ?: '') . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => function_exists('sicurezza_https') && sicurezza_https()];
    if (!$righe) { setcookie(VT_COOKIE, '', ['expires' => time() - 3600] + $opz); unset($_COOKIE[VT_COOKIE]); return; }
    $dati = rtrim(strtr(base64_encode(json_encode($righe)), '+/', '-_'), '=');
    $v = $dati . '.' . hash_hmac('sha256', $dati, vt_segreto());
    setcookie(VT_COOKIE, $v, ['expires' => time() + 30 * 86400] + $opz);
    $_COOKIE[VT_COOKIE] = $v;
}

/** Quante unità ci sono nel carrello (per il widget Carrello). Senza cookie: 0, senza toccare il database. */
function vt_carrello_unita(): int {
    return array_sum(array_map(fn($r) => $r[2], vt_carrello_leggi()));
}

/**
 * Aggiunge una riga. Il prodotto deve essere pubblicato e disponibile; la variante deve essere di quel prodotto (una
 * variante di un altro prodotto si rifiuta); un prodotto con varianti ne vuole una. Ritorna '' oppure un errore.
 */
function vt_carrello_aggiungi(int $pid, int $vid, int $q): string {
    $p = vt_prodotto($pid);
    if (!$p) return 'Il prodotto non c\'è più.';
    if ($q < 1 || $q > VT_QTA_MAX) return 'La quantità va da 1 a ' . VT_QTA_MAX . '.';
    $v = null;
    if ($p['varianti']) {
        foreach ($p['varianti'] as $x) if ($x['id'] === $vid) $v = $x;
        if (!$v) return 'Scegli ' . mb_strtolower(trim($p['opzione1'] . ($p['opzione2'] !== '' ? ' e ' . $p['opzione2'] : '')) ?: 'una variante') . '.';
    } elseif ($vid !== 0) return 'Questa variante non è di questo prodotto.';
    if (!vt_disponibile($p, $v)) return 'Questo prodotto è esaurito.';
    $righe = vt_carrello_leggi();
    $trovata = false;
    foreach ($righe as &$r) if ($r[0] === $pid && $r[1] === $vid) { $r[2] = min(VT_QTA_MAX, $r[2] + $q); $trovata = true; }
    unset($r);
    if (!$trovata) {
        if (count($righe) >= VT_RIGHE_MAX) return 'Il carrello è pieno: al massimo ' . VT_RIGHE_MAX . ' prodotti diversi.';
        $righe[] = [$pid, $vid, $q];
    }
    vt_carrello_scrivi($righe);
    return '';
}

/** Cambia le quantità (0 toglie la riga). $q: [indice => quantità]. */
function vt_carrello_aggiorna(array $q): void {
    $righe = vt_carrello_leggi();
    foreach ($righe as $i => &$r) {
        if (!array_key_exists($i, $q)) continue;
        $n = (int)$q[$i];
        $r[2] = max(0, min(VT_QTA_MAX, $n));
    }
    unset($r);
    vt_carrello_scrivi(array_values(array_filter($righe, fn($r) => $r[2] > 0)));
}

/**
 * Il carrello calcolato dal database: righe con nome, variante, prezzo unitario, totale, disponibilità e quanti pezzi
 * restano; subtotale, spedizione (secondo la consegna), totale e IVA compresa. Le righe che non esistono più si tolgono.
 * $righe vuoto: quelle del cookie.
 */
function vt_carrello_calcola(?array $righe = null, string $consegna = 'spedizione'): array {
    $righe ??= vt_carrello_leggi();
    $out = []; $sub = 0; $iva = 0; $problemi = [];
    foreach ($righe as $i => [$pid, $vid, $q]) {
        $p = vt_prodotto((int)$pid);
        if (!$p) { $problemi[] = 'Un prodotto del carrello non c\'è più ed è stato tolto.'; continue; }
        $v = null;
        if ($p['varianti']) { foreach ($p['varianti'] as $x) if ($x['id'] === (int)$vid) $v = $x; if (!$v) { $problemi[] = '«' . $p['nome'] . '»: la variante scelta non c\'è più.'; continue; } }
        elseif ((int)$vid !== 0) { $problemi[] = '«' . $p['nome'] . '»: variante non valida, tolta.'; continue; }
        $q = max(1, min(VT_QTA_MAX, (int)$q));
        $prezzo = vt_prezzo($p, $v);
        $scorta = $v ? $v['scorta'] : $p['scorta'];
        $disp = vt_disponibile($p, $v) && ($scorta === null || $scorta >= $q);
        $tot = $prezzo * $q;
        $sub += $tot;
        $al = (float)$p['aliquota'];
        $iva += (int)round($tot - $tot / (1 + $al / 100));
        $out[] = ['indice' => $i, 'prodotto_id' => $p['id'], 'variante_id' => $v ? $v['id'] : 0, 'nome' => $p['nome'], 'slug' => $p['slug'], 'variante' => $v ? vt_variante_nome($v) : '',
            'foto' => $p['foto'][0] ?? '', 'prezzo_cent' => $prezzo, 'qta' => $q, 'totale_cent' => $tot, 'disponibile' => $disp, 'scorta' => $scorta, 'aliquota' => $al];
    }
    $s = vt_impostazioni();
    $sped = 0;
    if ($consegna === 'spedizione' && $out) {
        $sped = (int)$s['spedizione_cent'];
        if ((int)$s['gratis_oltre_cent'] > 0 && $sub >= (int)$s['gratis_oltre_cent']) $sped = 0;
        // L'IVA della spedizione con l'aliquota predefinita del negozio.
        $iva += (int)round($sped - $sped / (1 + (float)$s['aliquota'] / 100));
    }
    return ['righe' => $out, 'subtotale_cent' => $sub, 'spedizione_cent' => $sped, 'totale_cent' => $sub + $sped, 'iva_cent' => $iva, 'problemi' => $problemi,
        'disponibile' => $out && !array_filter($out, fn($r) => !$r['disponibile'])];
}

// ------------------------------------------------------------
// Ordini
// ------------------------------------------------------------

function vt_ip(): string {
    if (!function_exists('sicurezza_ip')) require_once dirname(__DIR__, 3) . '/includes/sicurezza.php';
    return substr(sicurezza_ip(), 0, 45);
}

/** Troppe richieste d'ordine da questo indirizzo nell'ultima ora? */
function vt_troppi(): bool {
    vt_schema();
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('vetrina_ordini') . ' WHERE ip = ? AND creato > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $st->execute([vt_ip()]);
    return (int)$st->fetchColumn() >= VT_ORDINI_ORA;
}

/**
 * Legge e controlla i dati della richiesta d'ordine. Ritorna [dati, errori (campo => messaggio)].
 * Il captcha si controlla a parte (vale una volta sola).
 */
function vt_ordine_leggi(array $p): array {
    $d = [
        'nome' => vt_testo($p['nome'] ?? '', 120), 'email' => mb_strtolower(vt_testo($p['email'] ?? '', 190)), 'telefono' => vt_testo($p['telefono'] ?? '', 40),
        'indirizzo' => vt_testo($p['indirizzo'] ?? '', 200), 'cap' => vt_testo($p['cap'] ?? '', 10), 'citta' => vt_testo($p['citta'] ?? '', 100),
        'consegna' => (string)($p['consegna'] ?? ''), 'metodo' => (string)($p['metodo'] ?? ''), 'note' => vt_testo($p['note'] ?? '', 2000, true),
        'consenso' => !empty($p['consenso']),
    ];
    $e = [];
    if (($p['sito_web'] ?? '') !== '') $e['robot'] = 'Richiesta non valida.';
    if (mb_strlen($d['nome']) < 2) $e['nome'] = 'Scrivi nome e cognome.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;<>"]/', $d['email'])) $e['email'] = 'Controlla l\'indirizzo email: sembra incompleto.';
    $cifre = preg_replace('/\D/', '', $d['telefono']);
    if (!preg_match('/^\+?[0-9 .\/()-]{6,30}$/', $d['telefono']) || strlen($cifre) < 6 || strlen($cifre) > 15) $e['telefono'] = 'Scrivi un numero di telefono: ti chiamiamo solo se serve per l\'ordine.';
    if (!isset(vt_consegne_attive()[$d['consegna']])) $e['consegna'] = 'Scegli come vuoi ricevere l\'ordine.';
    if ($d['consegna'] === 'spedizione') {
        if (mb_strlen($d['indirizzo']) < 4) $e['indirizzo'] = 'Scrivi via e numero civico.';
        if (!preg_match('/^\d{5}$/', $d['cap'])) $e['cap'] = 'Il CAP ha cinque cifre.';
        if (mb_strlen($d['citta']) < 2) $e['citta'] = 'Scrivi la città.';
    } else { $d['indirizzo'] = $d['cap'] = $d['citta'] = ''; }
    if (!isset(vt_metodi_attivi()[$d['metodo']])) $e['metodo'] = 'Scegli come vuoi pagare.';
    if (!$d['consenso']) $e['consenso'] = 'Per inviare la richiesta serve la spunta sulle condizioni di vendita e sull\'informativa privacy.';
    return [$d, $e];
}

/** Il prossimo numero d'ordine dell'anno: 2026-0001. */
function vt_numero_nuovo(): string {
    $anno = date('Y');
    $st = db()->prepare('SELECT MAX(CAST(SUBSTRING(numero, 6) AS UNSIGNED)) FROM ' . tbl('vetrina_ordini') . ' WHERE numero LIKE ?');
    $st->execute([$anno . '-%']);
    return $anno . '-' . str_pad((string)((int)$st->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
}

/**
 * Crea l'ordine dal carrello. Ricalcola tutto, scala le scorte in una transazione (se un pezzo è finito nel frattempo
 * non crea niente). Ritorna ['ordine' => riga] oppure ['errore' => testo].
 */
function vt_ordine_crea(array $d, array $righeCarrello): array {
    vt_schema();
    $calc = vt_carrello_calcola($righeCarrello, $d['consegna']);
    if (!$calc['righe']) return ['errore' => 'Il carrello è vuoto.'];
    if (!$calc['disponibile']) return ['errore' => 'Uno dei prodotti non è più disponibile nella quantità scelta: controlla il carrello.'];
    $pdo = db();
    for ($giro = 0; $giro < 5; $giro++) {
        $pdo->beginTransaction();
        try {
            foreach ($calc['righe'] as $r) {
                if ($r['scorta'] === null) continue;
                $st = $r['variante_id']
                    ? $pdo->prepare('UPDATE ' . tbl('vetrina_varianti') . ' SET scorta = scorta - ? WHERE id = ? AND prodotto_id = ? AND scorta IS NOT NULL AND scorta >= ?')
                    : $pdo->prepare('UPDATE ' . tbl('vetrina_prodotti') . ' SET scorta = scorta - ? WHERE id = ? AND scorta IS NOT NULL AND scorta >= ?');
                $st->execute($r['variante_id'] ? [$r['qta'], $r['variante_id'], $r['prodotto_id'], $r['qta']] : [$r['qta'], $r['prodotto_id'], $r['qta']]);
                if ($st->rowCount() !== 1) { $pdo->rollBack(); return ['errore' => '«' . $r['nome'] . '» è appena finito: controlla il carrello.']; }
            }
            $numero = vt_numero_nuovo();
            $righe = array_map(fn($r) => array_intersect_key($r, array_flip(['prodotto_id', 'variante_id', 'nome', 'variante', 'prezzo_cent', 'qta', 'totale_cent', 'aliquota'])) + ['scorta_scalata' => $r['scorta'] !== null], $calc['righe']);
            $storia = json_encode([['quando' => date('Y-m-d H:i:s'), 'stato' => 'nuovo']]);
            $pdo->prepare('INSERT INTO ' . tbl('vetrina_ordini') . ' (numero, stato, nome, email, telefono, indirizzo, cap, citta, consegna, metodo, note, righe, subtotale_cent, spedizione_cent, totale_cent, iva_cent, consenso, storia, ip, creato, aggiornato)
                VALUES (?, \'nuovo\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())')
                ->execute([$numero, $d['nome'], $d['email'], $d['telefono'], $d['indirizzo'], $d['cap'], $d['citta'], $d['consegna'], $d['metodo'], $d['note'],
                    json_encode($righe, JSON_UNESCAPED_UNICODE), $calc['subtotale_cent'], $calc['spedizione_cent'], $calc['totale_cent'], $calc['iva_cent'], date('Y-m-d'), $storia, vt_ip()]);
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
            return ['ordine' => vt_ordine($id)];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string)$e->getCode() === '23000') continue; // due ordini con lo stesso numero nello stesso istante: si riprova
            throw $e;
        }
    }
    return ['errore' => 'Il negozio è molto occupato: riprova tra un momento.'];
}

function vt_ordine(int $id): ?array {
    vt_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('vetrina_ordini') . ' WHERE id = ?');
    $st->execute([$id]);
    $o = $st->fetch();
    return $o ? vt_ordine_completa($o) : null;
}

function vt_ordine_per_numero(string $numero): ?array {
    vt_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('vetrina_ordini') . ' WHERE numero = ?');
    $st->execute([$numero]);
    $o = $st->fetch();
    return $o ? vt_ordine_completa($o) : null;
}

function vt_ordine_completa(array $o): array {
    $o['id'] = (int)$o['id'];
    foreach (['subtotale_cent', 'spedizione_cent', 'totale_cent', 'iva_cent'] as $k) $o[$k] = (int)$o[$k];
    $o['righe'] = json_decode((string)$o['righe'], true) ?: [];
    $o['storia'] = json_decode((string)$o['storia'], true) ?: [];
    return $o;
}

/** La firma del link della pagina "ordine ricevuto" (solo chi ha fatto l'ordine la apre). */
function vt_ordine_firma(array $o): string {
    return substr(hash_hmac('sha256', 'ordine|' . $o['numero'] . '|' . $o['email'], vt_segreto()), 0, 32);
}

function vt_ordine_url(array $o): string {
    return vt_base() . 'ordine/fatto/?' . http_build_query(['n' => $o['numero'], 'k' => vt_ordine_firma($o)]);
}

/** Gli ordini per il pannello: filtro per stato e ricerca (nome, email o numero). */
function vt_ordini(string $stato = '', string $cerca = '', int $limite = 200): array {
    vt_schema();
    $w = []; $par = [];
    if (isset(VT_STATI[$stato])) { $w[] = 'stato = ?'; $par[] = $stato; }
    if ($cerca !== '') { $w[] = '(nome LIKE ? OR email LIKE ? OR numero LIKE ?)'; $l = '%' . addcslashes($cerca, '%_\\') . '%'; array_push($par, $l, $l, $l); }
    $st = db()->prepare('SELECT * FROM ' . tbl('vetrina_ordini') . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY id DESC LIMIT ' . max(1, min(1000, $limite)));
    $st->execute($par);
    return array_map('vt_ordine_completa', $st->fetchAll());
}

function vt_ordini_conta(string $stato): int {
    vt_schema();
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('vetrina_ordini') . ' WHERE stato = ?');
    $st->execute([$stato]);
    return (int)$st->fetchColumn();
}

/**
 * Cambia lo stato di un ordine, lo scrive nella storia e manda l'email al cliente (se $avvisa). Annullare rimette le
 * scorte scalate; togliere l'annullamento le riprende (se ci sono ancora: se no l'ordine resta annullato).
 * Ritorna '' oppure un errore.
 */
function vt_ordine_stato(int $id, string $stato, bool $avvisa = true, string $messaggio = ''): string {
    if (!isset(VT_STATI[$stato])) return 'Stato sconosciuto.';
    $o = vt_ordine($id);
    if (!$o) return 'Ordine non trovato.';
    if ($o['stato'] === $stato) return '';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $verso = $stato === 'annullato' ? 1 : ($o['stato'] === 'annullato' ? -1 : 0);
        if ($verso) foreach ($o['righe'] as $r) {
            if (empty($r['scorta_scalata'])) continue;
            $q = (int)$r['qta'];
            $st = !empty($r['variante_id'])
                ? $pdo->prepare('UPDATE ' . tbl('vetrina_varianti') . ' SET scorta = scorta + ? WHERE id = ? AND scorta IS NOT NULL AND scorta + ? >= 0')
                : $pdo->prepare('UPDATE ' . tbl('vetrina_prodotti') . ' SET scorta = scorta + ? WHERE id = ? AND scorta IS NOT NULL AND scorta + ? >= 0');
            $st->execute([$verso * $q, !empty($r['variante_id']) ? (int)$r['variante_id'] : (int)$r['prodotto_id'], $verso * $q]);
            if ($verso < 0 && $st->rowCount() !== 1) { $pdo->rollBack(); return '«' . $r['nome'] . '» non ha più abbastanza pezzi: l\'ordine resta annullato.'; }
        }
        $storia = $o['storia'];
        $storia[] = ['quando' => date('Y-m-d H:i:s'), 'stato' => $stato];
        $pdo->prepare('UPDATE ' . tbl('vetrina_ordini') . ' SET stato = ?, storia = ?, aggiornato = NOW() WHERE id = ?')->execute([$stato, json_encode(array_slice($storia, -50)), $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    if ($avvisa) vt_email_stato(vt_ordine($id), vt_testo($messaggio, 1000, true));
    return '';
}

/** La pulizia del cron: gli indirizzi IP degli ordini servono solo per il limite di un'ora, si tolgono dopo 7 giorni. */
function vt_pulizia(): string {
    vt_schema();
    $st = db()->prepare('UPDATE ' . tbl('vetrina_ordini') . " SET ip = '' WHERE ip <> '' AND creato < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $st->execute();
    return $st->rowCount() ? $st->rowCount() . ' indirizzi IP vecchi tolti dagli ordini' : '';
}

// ------------------------------------------------------------
// Email
// ------------------------------------------------------------

/** La tabella delle righe dell'ordine per le email (HTML con gli stili in linea). */
function vt_email_riepilogo(array $o): string {
    $t = '<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:15px;margin:12px 0">';
    foreach ($o['righe'] as $r) {
        $t .= '<tr style="border-bottom:1px solid #e5e5ea"><td>' . h((string)$r['nome']) . ($r['variante'] !== '' ? '<br><small style="color:#666">' . h((string)$r['variante']) . '</small>' : '')
            . '</td><td style="text-align:center;white-space:nowrap">' . (int)$r['qta'] . ' &times; ' . h(vt_euro((int)$r['prezzo_cent'])) . '</td><td style="text-align:right;white-space:nowrap">' . h(vt_euro((int)$r['totale_cent'])) . '</td></tr>';
    }
    $t .= '<tr><td colspan="2">Prodotti</td><td style="text-align:right">' . h(vt_euro($o['subtotale_cent'])) . '</td></tr>';
    $t .= '<tr><td colspan="2">' . ($o['consegna'] === 'ritiro' ? 'Ritiro in negozio' : 'Spedizione') . '</td><td style="text-align:right">' . ($o['spedizione_cent'] ? h(vt_euro($o['spedizione_cent'])) : 'gratis') . '</td></tr>';
    $t .= '<tr><td colspan="2"><b>Totale</b> <small style="color:#666">(IVA inclusa, ' . h(vt_euro($o['iva_cent'])) . ')</small></td><td style="text-align:right"><b>' . h(vt_euro($o['totale_cent'])) . '</b></td></tr></table>';
    return $t;
}

/** Come si paga questo ordine: il testo per la pagina di conferma e per l'email (HTML). */
function vt_istruzioni_pagamento(array $o): string {
    $s = vt_impostazioni();
    switch ($o['metodo']) {
        case 'bonifico':
            return '<p><b>Paga con un bonifico</b> di <b>' . h(vt_euro($o['totale_cent'])) . '</b>:</p><p>IBAN: <b>' . h((string)$s['iban']) . '</b>'
                . ($s['intestatario'] !== '' ? '<br>Intestato a: ' . h((string)$s['intestatario']) : '') . ($s['banca'] !== '' ? '<br>Banca: ' . h((string)$s['banca']) : '')
                . '<br>Causale: <b>Ordine ' . h((string)$o['numero']) . '</b></p><p>Prepariamo l\'ordine quando il bonifico arriva: ti scriviamo noi.</p>';
        case 'link':
            $l = vt_link_pagamento($o);
            return '<p><b>Paga online</b> ' . h(vt_euro($o['totale_cent'])) . ' con il link qui sotto. Nella causale o nel messaggio scrivi <b>Ordine ' . h((string)$o['numero']) . '</b>.</p>'
                . ($l !== '' ? '<p><a href="' . h($l) . '" style="display:inline-block;background:#1f6f43;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:700" rel="noopener">Paga ' . h(vt_euro($o['totale_cent'])) . '</a></p>' : '');
        default:
            return '<p><b>Paghi ' . ($o['consegna'] === 'ritiro' ? 'al ritiro' : 'alla consegna') . ':</b> ' . h(vt_euro($o['totale_cent'])) . '.</p>';
    }
}

function vt_email_dati_attivita(): string {
    $s = vt_impostazioni();
    $r = array_filter([(string)$s['attivita_nome'], (string)$s['attivita_indirizzo'], $s['attivita_piva'] !== '' ? 'P. IVA ' . $s['attivita_piva'] : '', (string)$s['attivita_telefono'], (string)$s['attivita_email']], fn($x) => trim($x) !== '');
    return $r ? '<p style="font-size:13px;color:#666;margin-top:24px">' . implode('<br>', array_map('h', $r)) . '</p>' : '';
}

/** L'indirizzo a cui arrivano gli avvisi degli ordini: quello del negozio, se no quello degli avvisi del sito. */
function vt_email_negozio(): string {
    $s = vt_impostazioni();
    foreach ([(string)$s['email_avvisi'], (string)$s['attivita_email']] as $e) if (filter_var(trim($e), FILTER_VALIDATE_EMAIL)) return trim($e);
    require_once dirname(__DIR__, 3) . '/includes/posta.php';
    return posta_notifica_email();
}

/** Un'intestazione sicura: niente a capo (header injection), solo un indirizzo valido. */
function vt_rispondi_a(string $email): string {
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $email) ? $email : '';
}

/** Le due email dell'ordine nuovo: al cliente (riepilogo e come pagare) e al negozio (avviso con i dati). */
function vt_email_ordine(array $o): array {
    require_once dirname(__DIR__, 3) . '/includes/moduli.php';
    $esito = ['cliente' => false, 'negozio' => false];
    $nomeSito = (string)cms_impostazione('sito_nome', CMS_NOME);
    $dove = $o['consegna'] === 'ritiro' ? '<p><b>Ritiro in negozio.</b> ' . h((string)vt_impostazioni()['ritiro_testo']) . '</p>'
        : '<p><b>Spedizione a:</b><br>' . h($o['nome']) . '<br>' . h($o['indirizzo']) . '<br>' . h($o['cap'] . ' ' . $o['citta']) . '</p>';
    $link = vt_url_assoluto(vt_ordine_url($o));
    $corpo = '<p>Ciao ' . h($o['nome']) . ', abbiamo ricevuto la tua richiesta d\'ordine <b>' . h($o['numero']) . '</b>. Ecco il riepilogo.</p>'
        . vt_email_riepilogo($o) . vt_istruzioni_pagamento($o) . $dove
        . ($o['note'] !== '' ? '<p><b>Le tue note:</b><br>' . nl2br(h((string)$o['note'])) . '</p>' : '')
        . '<p>Ti scriviamo a ogni passo: pagamento ricevuto, spedizione, consegna.</p>' . vt_email_dati_attivita();
    $negozio = vt_email_negozio();
    $esito['cliente'] = posta_invia($o['email'], 'Ordine ' . $o['numero'] . ' ricevuto - ' . $nomeSito,
        moduli_email_html('Ordine ' . $o['numero'] . ' ricevuto', $corpo, 'Vedi il tuo ordine', $link), moduli_intestazioni(vt_rispondi_a($negozio)), 'vetrina');
    if ($negozio !== '') {
        $pannello = cms_url_pannello() . 'addon-pagina.php?a=vetrina&p=ordini&id=' . $o['id'];
        $c2 = '<p>Nuova richiesta d\'ordine <b>' . h($o['numero']) . '</b> da <b>' . h($o['nome']) . '</b>.</p>'
            . '<p>Email: ' . h($o['email']) . '<br>Telefono: ' . h($o['telefono']) . '<br>Pagamento: ' . h(VT_METODI[$o['metodo']] ?? $o['metodo']) . '</p>'
            . vt_email_riepilogo($o) . $dove . ($o['note'] !== '' ? '<p><b>Note:</b><br>' . nl2br(h((string)$o['note'])) . '</p>' : '');
        $esito['negozio'] = posta_invia($negozio, 'Nuovo ordine ' . $o['numero'] . ' (' . vt_euro($o['totale_cent']) . ')',
            moduli_email_html('Nuovo ordine ' . $o['numero'], $c2, 'Apri l\'ordine nel pannello', $pannello), moduli_intestazioni(vt_rispondi_a($o['email'])), 'vetrina');
    }
    return $esito;
}

/** L'email al cliente quando cambia lo stato dell'ordine. */
function vt_email_stato(array $o, string $messaggio = ''): bool {
    require_once dirname(__DIR__, 3) . '/includes/moduli.php';
    $frasi = [
        'nuovo' => 'Il tuo ordine è di nuovo in lavorazione.',
        'pagato' => 'Abbiamo ricevuto il pagamento. Grazie! Ora prepariamo il tuo ordine.',
        'spedito' => $o['consegna'] === 'ritiro' ? 'Il tuo ordine è pronto: puoi venire a ritirarlo.' : 'Il tuo ordine è partito: arriva nei prossimi giorni.',
        'consegnato' => 'Il tuo ordine risulta consegnato. Speriamo che ti piaccia!',
        'annullato' => 'Il tuo ordine è stato annullato. Se non te lo aspettavi, rispondi a questa email.',
    ];
    $corpo = '<p>Ciao ' . h($o['nome']) . ', ' . h(lcfirst($frasi[$o['stato']] ?? '')) . '</p>'
        . ($messaggio !== '' ? '<p>' . nl2br(h($messaggio)) . '</p>' : '')
        . vt_email_riepilogo($o) . ($o['stato'] === 'nuovo' ? vt_istruzioni_pagamento($o) : '') . vt_email_dati_attivita();
    return posta_invia($o['email'], 'Ordine ' . $o['numero'] . ': ' . mb_strtolower(VT_STATI[$o['stato']]) . ' - ' . (string)cms_impostazione('sito_nome', CMS_NOME),
        moduli_email_html('Ordine ' . $o['numero'] . ': ' . mb_strtolower(VT_STATI[$o['stato']]), $corpo, 'Vedi il tuo ordine', vt_url_assoluto(vt_ordine_url($o))),
        moduli_intestazioni(vt_rispondi_a(vt_email_negozio())), 'vetrina');
}
