<?php
/**
 * Newsletter, parte seconda: aperture e clic delle email, e le automazioni (fino alla 2.14.0 "flussi automatici").
 *
 * Aperture e clic: ogni email porta un'immagine di un pixel e i link passano da api/nl.php, che segna
 * l'evento in <prefisso>newsletter_eventi e rimanda alla pagina vera. Il conteggio è per persona (una apertura
 * conta una volta). Chi blocca le immagini non risulta: i numeri sono per difetto, come ovunque.
 *
 * Automazioni: un grafo. Parte da uno o più inneschi (iscrizione, lista, tolto da una lista, form, apertura o clic di una
 * campagna, nessuna interazione da tanti giorni, disiscrizione, anniversario di una data, aggiunta a mano) e passa di nodo
 * in nodo lungo gli archi: azioni (email, aggiungi o togli da una lista, aggiorna un attributo, webhook) e regole (attesa,
 * attendi un evento, Sì o No, divisione a più rami, divisione in percentuale, fine). Ogni persona in cammino è una riga di
 * <prefisso>newsletter_corsi (dalla 2.15.0 con il giro: chi rientra fa un giro nuovo); quello che succede va nel registro
 * (newsletter_flussi_registro) e nei contatori per passo (newsletter_flussi_numeri). Le email le manda lo stesso cron della
 * newsletter, dentro lo stesso limite; aperture e clic la spostano subito (newsletter_flusso_evento). Webhook, modelli,
 * anniversari, statistiche e attività stanno in includes/newsletter-automazioni.php.
 */

const NL_INNESCHI = ['iscrizione' => 'Si iscrive alla newsletter (conferma fatta)', 'lista' => 'Entra in una lista', 'lista_tolto' => 'Viene tolto da una lista', 'form' => 'Compila un form', 'apertura' => 'Apre una campagna', 'clic' => 'Fa clic in una campagna', 'inattivo' => 'Non apre e non clicca niente da tanti giorni', 'disiscritto' => 'Si disiscrive dalla newsletter', 'anniversario' => 'Anniversario di una data', 'mano' => 'Aggiunto a mano dal pannello', 'tag' => 'Riceve un tag']; // 2.23.0: tag
/**
 * 2.22.0: i nomi degli inneschi, quelli del nucleo più quelli degli addon accesi (filtro automazioni_inneschi: l'addon
 * Test e questionari aggiunge 'test', "Completa un test"). Un innesco 'test' salvato resta anche con l'addon spento.
 */
function nl_inneschi_nomi(): array {
    static $n = null;
    if ($n !== null) return $n;
    $n = NL_INNESCHI;
    if (function_exists('cms_filtro')) {
        try { $x = cms_filtro('automazioni_inneschi', []); } catch (Throwable $e) { error_log('automazioni_inneschi: ' . $e->getMessage()); $x = []; }
        foreach (is_array($x) ? $x : [] as $k => $v) if (is_string($k) && preg_match('/^[a-z][a-z0-9_]{1,19}$/', $k) && !isset($n[$k]) && is_string($v)) $n[$k] = $v;
    }
    if (!isset($n['test'])) $n['test'] = 'Completa un test (addon Test e questionari spento)';
    return $n;
}

/** Le condizioni di un bivio. 'id' dice cosa chiede la seconda casella: una lista, un'email del flusso, un numero di giorni. */
const NL_CONDIZIONI = [
    'in_lista' => ['Sta in una lista', 'lista'], 'non_in_lista' => ['Non sta in una lista', 'lista'],
    'aperta' => ["Ha aperto un'email di questa automazione", 'email'], 'non_aperta' => ["Non ha aperto un'email di questa automazione", 'email'],
    'cliccata' => ["Ha cliccato in un'email di questa automazione", 'email'], 'non_cliccata' => ["Non ha cliccato in un'email di questa automazione", 'email'],
    'inattivo' => ['Non apre e non clicca niente da almeno tanti giorni', 'giorni'], 'attivo' => ['Ha aperto o cliccato qualcosa negli ultimi tanti giorni', 'giorni'],
    'iscritto_da' => ['È iscritto da almeno tanti giorni', 'giorni'],
    // 2.10.0: i segmenti (motore in includes/newsletter-segmenti.php).
    'in_segmento' => ['Fa parte di un segmento', 'segmento'], 'non_in_segmento' => ['Non fa parte di un segmento', 'segmento'],
];
/** Le uscite di ogni tipo di nodo (elenco chiuso dei tipi), con l'etichetta della tela. Divisioni: uscite dei loro rami (nl_uscite()). */
const NL_USCITE = [
    'innesco' => ['poi' => 'Poi'],
    'email' => ['poi' => 'Subito dopo', 'aperta' => 'Se apre', 'cliccata' => 'Se clicca', 'nessuna' => 'Se non fa niente entro il tempo'],
    'attesa' => ['poi' => 'Poi'],
    'bivio' => ['si' => 'Sì', 'no' => 'No'],
    'fine' => [],
    // 2.15.0
    'evento' => ['si' => 'Se succede', 'no' => 'Se non succede in tempo'],
    'dividi' => [], 'percento' => [],
    'lista_aggiungi' => ['poi' => 'Poi'], 'lista_togli' => ['poi' => 'Poi'], 'attributo' => ['poi' => 'Poi'], 'webhook' => ['poi' => 'Poi'],
    // 2.23.0: i tag dei contatti (includes/newsletter-tag.php).
    'tag_aggiungi' => ['poi' => 'Poi'], 'tag_togli' => ['poi' => 'Poi'],
];
const NL_AUT_STATI = ['attiva' => 'Attiva', 'in_pausa' => 'In pausa', 'inattiva' => 'Inattiva'];
const NL_AUT_MAX_NODI = 100;     // blocchi sulla tela
const NL_AUT_MAX_INNESCHI = 10;
const NL_AUT_MAX_RAMI = 8;       // rami di una divisione condizionale (più l'altrimenti)
const NL_AUT_MAX_SALTI = 250;    // passi di un cammino: oltre si ferma (un giro infinito non arriva mai fin qui: la tela non ne ammette)
const NL_AUT_PASSI_GIRO = 50;    // passi di un cammino in un giro del cron

function newsletter_flussi_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $tabelle = [
        'newsletter_eventi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_eventi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `campagna_id` INT(11) NOT NULL DEFAULT 0,
            `flusso_id` INT(11) NOT NULL DEFAULT 0,
            `passo` INT(11) NOT NULL DEFAULT 0,
            `iscritto_id` INT(11) NOT NULL,
            `tipo` VARCHAR(10) NOT NULL,
            `url` VARCHAR(500) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `campagna` (`campagna_id`, `tipo`, `iscritto_id`), KEY `flusso` (`flusso_id`, `passo`, `iscritto_id`, `tipo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_flussi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_flussi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `attivo` TINYINT(1) NOT NULL DEFAULT 0,
            `innesco` VARCHAR(20) NOT NULL DEFAULT 'iscrizione',
            `innesco_id` INT(11) NOT NULL DEFAULT 0,
            `passi` MEDIUMTEXT,
            `inviate` INT(11) NOT NULL DEFAULT 0,
            `mittente_id` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            `stato` VARCHAR(10) NOT NULL DEFAULT 'inattiva',
            `inneschi` TEXT,
            `tag` VARCHAR(255) NOT NULL DEFAULT '',
            `rientro` TINYINT(1) NOT NULL DEFAULT 0,
            `rientro_attesa` INT(11) NOT NULL DEFAULT 0,
            `uscita` TEXT,
            PRIMARY KEY (`id`), KEY `innesco` (`attivo`, `innesco`, `innesco_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_corsi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_corsi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `flusso_id` INT(11) NOT NULL,
            `iscritto_id` INT(11) NOT NULL,
            `passo` INT(11) NOT NULL DEFAULT 0,
            `prossimo` DATETIME DEFAULT NULL,
            `stato` VARCHAR(10) NOT NULL DEFAULT 'attivo',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            `ascolto` VARCHAR(10) DEFAULT NULL,
            `salti` INT(11) NOT NULL DEFAULT 0,
            `giro` INT(11) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`), UNIQUE KEY `flusso_giro` (`flusso_id`, `iscritto_id`, `giro`), KEY `pronti` (`stato`, `prossimo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // 2.15.0: il registro delle automazioni (Attività) e i contatori per passo (statistiche, divisione in percentuale).
        'newsletter_flussi_registro' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_flussi_registro') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `flusso_id` INT(11) NOT NULL,
            `corso_id` INT(11) NOT NULL DEFAULT 0,
            `iscritto_id` INT(11) NOT NULL DEFAULT 0,
            `nodo` INT(11) NOT NULL DEFAULT 0,
            `evento` VARCHAR(10) NOT NULL,
            `dettaglio` VARCHAR(255) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `flusso` (`flusso_id`, `id`), KEY `iscritto` (`iscritto_id`), KEY `creato` (`creato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_flussi_numeri' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_flussi_numeri') . " (
            `flusso_id` INT(11) NOT NULL,
            `nodo` INT(11) NOT NULL,
            `chiave` VARCHAR(12) NOT NULL,
            `n` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`flusso_id`, `nodo`, `chiave`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME . 'grafo ripetuti automazioni giro');
    if (get_setting('newsletter_flussi_impronta', '') !== $impronta) {
        foreach ($tabelle as $sql) db()->exec($sql);
        // Colonne arrivate con il grafo: in ascolto di cosa, e un contatore dei salti per fermare i giri infiniti.
        // Una per una: una tabella creata da un pacchetto precedente può averne una sola delle due.
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('newsletter_corsi'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('ascolto', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_corsi') . ' ADD COLUMN `ascolto` VARCHAR(10) DEFAULT NULL');
        if (!in_array('salti', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_corsi') . ' ADD COLUMN `salti` INT(11) NOT NULL DEFAULT 0');
        // 2.15.0: il giro (chi rientra in un'automazione ne fa uno nuovo). La chiave unica passa da (flusso, iscritto) a
        // (flusso, iscritto, giro): i cammini di prima sono tutti il giro 1 e restano dove sono.
        if (!in_array('giro', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_corsi') . ' ADD COLUMN `giro` INT(11) NOT NULL DEFAULT 1');
        $ind = db()->query('SHOW INDEX FROM ' . tbl('newsletter_corsi'))->fetchAll(PDO::FETCH_COLUMN, 2);
        if (!in_array('flusso_giro', $ind, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_corsi') . ' ADD UNIQUE KEY `flusso_giro` (`flusso_id`, `iscritto_id`, `giro`)');
        if (in_array('flusso_iscritto', $ind, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_corsi') . ' DROP INDEX `flusso_iscritto`');
        // 2.1.0: il mittente scelto per il flusso (0 = il predefinito), includes/consegna.php.
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('newsletter_flussi'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('mittente_id', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_flussi') . ' ADD COLUMN `mittente_id` INT(11) NOT NULL DEFAULT 0 AFTER `inviate`');
        // 2.15.0: stato (attivo = 1 -> attiva; attivo = 0 -> inattiva: fino alla 2.14.0 un flusso spento fermava anche chi
        // era dentro), più inneschi, tag, rientro, condizioni di uscita. `attivo` resta e si scrive insieme a `stato`.
        $nuove = ['stato' => "VARCHAR(10) NOT NULL DEFAULT 'inattiva'", 'inneschi' => 'TEXT', 'tag' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'rientro' => 'TINYINT(1) NOT NULL DEFAULT 0', 'rientro_attesa' => 'INT(11) NOT NULL DEFAULT 0', 'uscita' => 'TEXT'];
        foreach ($nuove as $k => $tipo) if (!in_array($k, $col, true)) {
            db()->exec('ALTER TABLE ' . tbl('newsletter_flussi') . " ADD COLUMN `$k` $tipo");
            if ($k === 'stato') db()->exec('UPDATE ' . tbl('newsletter_flussi') . " SET stato = IF(attivo = 1, 'attiva', 'inattiva')");
        }
        // 2.14.0: aperture e clic ripetuti (volte, ultimo) e l'indice per contatto (orario migliore, elenchi del report).
        // Le righe di prima valgono una volta: i conteggi per persona restano quelli di prima.
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('newsletter_eventi'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('volte', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_eventi') . ' ADD COLUMN `volte` INT(11) NOT NULL DEFAULT 1');
        if (!in_array('ultimo', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_eventi') . ' ADD COLUMN `ultimo` DATETIME DEFAULT NULL');
        $ind = db()->query('SHOW INDEX FROM ' . tbl('newsletter_eventi'))->fetchAll(PDO::FETCH_COLUMN, 2);
        if (!in_array('iscritto', $ind, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_eventi') . ' ADD KEY `iscritto` (`iscritto_id`, `tipo`, `creato`)');
        save_setting('newsletter_flussi_impronta', $impronta);
    }
}

// ------------------------------------------------------------
// Tracciamento
// ------------------------------------------------------------

/** La firma di un evento: nessuno può inventarsi aperture di altri. */
function nl_firma(int $c, int $f, int $p, int $i): string {
    return substr(hash_hmac('sha256', "$c|$f|$p|$i", moduli_segreto()), 0, 20);
}

/**
 * sicurezza (attacco 1): la firma dell'indirizzo di destinazione di un clic. Senza, chi riceve una newsletter poteva
 * cambiare u= e usare il dominio del sito per mandare la gente altrove. Il rimando esterno vale solo con questa firma.
 */
function nl_firma_url(int $c, int $f, int $p, int $i, string $url): string {
    return substr(hash_hmac('sha256', "u|$c|$f|$p|$i|$url", moduli_segreto()), 0, 24);
}

/** Il rimando verso $url e firmato per questo evento? (I link spediti prima del 26 ottobre non hanno k: valgono solo verso il sito.) */
function newsletter_url_firmato(int $c, int $f, int $p, int $i, string $url, string $k): bool {
    return $i > 0 && $k !== '' && $url !== '' && hash_equals(nl_firma_url($c, $f, $p, $i, $url), $k);
}

function nl_url_evento(string $azione, array $t, int $iscritto, string $url = ''): string {
    $c = (int)($t['c'] ?? 0); $f = (int)($t['f'] ?? 0); $p = (int)($t['p'] ?? 0);
    $q = ['a' => $azione, 'c' => $c, 'f' => $f, 'p' => $p, 'i' => $iscritto, 't' => nl_firma($c, $f, $p, $iscritto)];
    if ($url !== '') { $q['u'] = $url; $q['k'] = nl_firma_url($c, $f, $p, $iscritto, $url); }
    // 2.11.0: con il sito in una cartella MODULI_URL ha già la cartella (cms_url_sito): prima usciva /test/test/api/nl.php e
    // aperture e clic non si contavano. Nella radice cms_base() è vuoto: l'indirizzo resta identico al byte.
    $base = MODULI_URL;
    if (cms_base() !== '' && !str_ends_with($base, cms_base())) $base .= cms_base();
    return $base . '/api/nl.php?' . http_build_query($q);
}

/** Mette il pixel e riscrive i link dell'email. Il link di uscita resta diretto. */
/** 2.16.0: il contatto acconsente al tracciamento di aperture e clic (colonna tracciamento, 1 di partenza). */
function newsletter_traccia_consentita(int $id): bool {
    static $col = null, $visti = [];
    if (!$id) return false;
    if (isset($visti[$id])) return $visti[$id];
    $col ??= in_array('tracciamento', db()->query('SHOW COLUMNS FROM ' . tbl('newsletter_iscritti'))->fetchAll(PDO::FETCH_COLUMN), true);
    if (!$col) return true;
    $st = db()->prepare('SELECT tracciamento FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$id]);
    $v = $st->fetchColumn();
    if (count($visti) > 500) $visti = [];
    return $visti[$id] = $v === false || (int)$v === 1;
}

function newsletter_traccia(string $html, array $t, int $iscritto): string {
    // 2.16.0: senza il consenso al tracciamento (centro preferenze, form, scheda del contatto) l'email esce senza pixel e
    // con i link diretti, come una prova.
    if (!newsletter_traccia_consentita($iscritto)) return $html;
    $html = preg_replace_callback('~href="(https?://[^"]+)"~i', function ($m) use ($t, $iscritto) {
        $u = html_entity_decode($m[1]);
        if (str_contains($u, cms_base() . '/api/nl.php') || str_contains($u, NEWSLETTER_ESCI)) return $m[0];
        // 2.11.0: anche i link firmati delle preferenze e della versione web restano diretti.
        if (str_contains($u, '/newsletter-preferenze/') || str_contains($u, '/newsletter-web/')) return $m[0];
        return 'href="' . h(nl_url_evento('c', $t, $iscritto, $u)) . '"';
    }, $html);
    $pixel = '<img src="' . h(nl_url_evento('a', $t, $iscritto)) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">';
    return str_contains($html, '</body>') ? str_replace('</body>', $pixel . '</body>', $html) : $html . $pixel;
}

/**
 * Segna un evento (dall'api). Ritorna true se la firma regge. Una riga per persona e tipo (per i clic, per indirizzo):
 * i conteggi unici sono per persona. 2.14.0: la stessa apertura o lo stesso clic di nuovo aumenta `volte` e scrive
 * `ultimo` (aperture e clic totali del report); gli inneschi e i flussi partono solo la prima volta, come prima.
 * $ripeti false: il clic che vale anche come apertura non conta un'apertura in più.
 */
function newsletter_evento(string $tipo, int $c, int $f, int $p, int $i, string $t, string $url = '', bool $ripeti = true): bool {
    if (!$i || !hash_equals(nl_firma($c, $f, $p, $i), $t)) return false;
    // 2.16.0: un link tracciato di un'email di prima, aperto da chi poi ha tolto il consenso: si va dove porta, senza segnare niente.
    if (!newsletter_traccia_consentita($i)) return true;
    newsletter_flussi_schema();
    $tipo = $tipo === 'clic' ? 'clic' : 'apertura';
    $url = mb_substr($url, 0, 500);
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_eventi') . ' WHERE campagna_id = ? AND flusso_id = ? AND passo = ? AND iscritto_id = ? AND tipo = ? AND url = ? LIMIT 1');
    $st->execute([$c, $f, $p, $i, $tipo, $url]);
    $gia = (int)$st->fetchColumn();
    if ($gia) {
        if ($ripeti) db()->prepare('UPDATE ' . tbl('newsletter_eventi') . ' SET volte = volte + 1, ultimo = NOW() WHERE id = ?')->execute([$gia]);
        return true;
    }
    db()->prepare('INSERT INTO ' . tbl('newsletter_eventi') . ' (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) VALUES (?,?,?,?,?,?,NOW())')->execute([$c, $f, $p, $i, $tipo, $url]);
    // Un clic vale anche come apertura (una volta: le aperture totali contano il pixel).
    if ($tipo === 'clic') newsletter_evento('apertura', $c, $f, $p, $i, $t, '', false);
    if ($c) newsletter_flussi_innesco($tipo, $c, $i);
    if ($f) newsletter_flusso_evento($f, $p, $i, $tipo);
    return true;
}

/**
 * 2.1.0: un evento che non arriva da un link dell'email ma dal CMS stesso: 'rimbalzo' (server di posta o avviso del
 * servizio), 'spam' (avviso del servizio), 'uscita' (link di uscita con la campagna firmata), dalla 2.14.0 'soft'.
 * Una riga per persona.
 */
function newsletter_evento_interno(string $tipo, int $c, int $f, int $p, int $i): void {
    // 2.14.0: anche 'soft', il rimbalzo temporaneo (l'ultimo tentativo della coda o l'avviso del servizio), per il report.
    if (!$i || (!$c && !$f) || !in_array($tipo, ['rimbalzo', 'spam', 'uscita', 'soft'], true)) return;
    newsletter_flussi_schema();
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_eventi') . " WHERE campagna_id = ? AND flusso_id = ? AND passo = ? AND iscritto_id = ? AND tipo = ? LIMIT 1");
    $st->execute([$c, $f, $p, $i, $tipo]);
    if ($st->fetchColumn()) return;
    db()->prepare('INSERT INTO ' . tbl('newsletter_eventi') . " (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) VALUES (?, ?, ?, ?, ?, '', NOW())")->execute([$c, $f, $p, $i, $tipo]);
}

/** Aperture e clic per persona, di una campagna. */
function newsletter_conteggio_eventi(int $campagna): array {
    newsletter_flussi_schema();
    $st = db()->prepare('SELECT tipo, COUNT(DISTINCT iscritto_id) AS n FROM ' . tbl('newsletter_eventi') . ' WHERE campagna_id = ? GROUP BY tipo');
    $st->execute([$campagna]);
    $out = ['apertura' => 0, 'clic' => 0];
    foreach ($st->fetchAll() as $r) $out[$r['tipo']] = (int)$r['n'];
    return $out;
}

/** Le campagne inviate con i loro numeri, per la pagina Statistiche. */
function newsletter_statistiche_campagne(): array {
    newsletter_flussi_schema();
    $out = [];
    // 2.1.0: anche rimbalzi, disiscrizioni e segnalazioni di spam, per persona.
    $conta = fn(string $tipo) => '(SELECT COUNT(DISTINCT e.iscritto_id) FROM ' . tbl('newsletter_eventi') . " e WHERE e.campagna_id = c.id AND e.tipo = '$tipo') AS ";
    foreach (db()->query('SELECT c.*, ' . $conta('apertura') . 'aperture, ' . $conta('clic') . 'clic, ' . $conta('rimbalzo') . 'rimbalzi, ' . $conta('uscita') . 'uscite, ' . $conta('spam') . 'spam FROM '
        . tbl('newsletter_campagne') . " c WHERE c.stato IN ('inviata','in_invio','sospesa') ORDER BY c.invio_at DESC, c.id DESC")->fetchAll() as $c) {
        $c['tasso_aperture'] = $c['inviate'] ? round($c['aperture'] * 100 / $c['inviate'], 1) : 0;
        $c['tasso_clic'] = $c['inviate'] ? round($c['clic'] * 100 / $c['inviate'], 1) : 0;
        // I rimbalzi si contano su tutte le email provate (partite più quelle rifiutate subito dal server), gli altri sulle partite.
        $provate = (int)$c['inviate'] + (int)$c['errori'];
        $c['tasso_rimbalzi'] = $provate ? round($c['rimbalzi'] * 100 / $provate, 1) : 0;
        $c['tasso_uscite'] = $c['inviate'] ? round($c['uscite'] * 100 / $c['inviate'], 1) : 0;
        $c['tasso_spam'] = $c['inviate'] ? round($c['spam'] * 100 / $c['inviate'], 2) : 0;
        $out[] = $c;
    }
    return $out;
}

/** I link più cliccati di una campagna. */
function newsletter_clic_per_link(int $campagna): array {
    $st = db()->prepare('SELECT url, COUNT(DISTINCT iscritto_id) AS n FROM ' . tbl('newsletter_eventi') . " WHERE campagna_id = ? AND tipo = 'clic' AND url <> '' GROUP BY url ORDER BY n DESC LIMIT 30");
    $st->execute([$campagna]);
    return $st->fetchAll();
}

/** Iscritti attivi nuovi per mese, ultimi 12 mesi. */
function newsletter_iscritti_per_mese(): array {
    return db()->query('SELECT DATE_FORMAT(creato, "%Y-%m") AS mese, COUNT(*) AS n, SUM(stato = "attivo") AS attivi, SUM(stato = "uscito") AS usciti FROM ' . tbl('newsletter_iscritti') . ' WHERE creato >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY mese ORDER BY mese')->fetchAll();
}

// ------------------------------------------------------------
// Automazioni: il grafo
// ------------------------------------------------------------

/** Le automazioni, con i numeri dei cammini (giri compresi). */
function newsletter_flussi(): array {
    newsletter_flussi_schema();
    $out = [];
    $c = tbl('newsletter_corsi');
    foreach (db()->query("SELECT f.*, (SELECT COUNT(*) FROM $c c WHERE c.flusso_id = f.id AND c.stato = 'attivo') AS in_corso, (SELECT COUNT(*) FROM $c c WHERE c.flusso_id = f.id AND c.stato = 'finito') AS finiti,
        (SELECT COUNT(*) FROM $c c WHERE c.flusso_id = f.id) AS entrati, (SELECT COUNT(*) FROM $c c WHERE c.flusso_id = f.id AND c.stato IN ('tolto', 'fermato')) AS usciti FROM " . tbl('newsletter_flussi') . " f ORDER BY f.stato = 'attiva' DESC, f.nome")->fetchAll() as $f) {
        $out[] = nl_flusso_riga($f);
    }
    return $out;
}

function newsletter_flusso(int $id): ?array {
    newsletter_flussi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_flussi') . ' WHERE id = ?');
    $st->execute([$id]);
    $f = $st->fetch() ?: null;
    return $f ? nl_flusso_riga($f) : null;
}

/** Una riga del database pronta per l'uso: nome leggibile, grafo, inneschi, stato, uscita. */
function nl_flusso_riga(array $f): array {
    $f['nome'] = newsletter_4byte_fuori((string)$f['nome']);
    $f['grafo'] = newsletter_flusso_grafo($f);
    $f['stato'] = isset(NL_AUT_STATI[$f['stato'] ?? '']) ? $f['stato'] : ((int)$f['attivo'] ? 'attiva' : 'inattiva');
    $f['inneschi'] = nl_inneschi_validi(json_decode((string)($f['inneschi'] ?? ''), true) ?: [['tipo' => (string)$f['innesco'], 'id' => (int)$f['innesco_id']]]);
    $u = json_decode((string)($f['uscita'] ?? ''), true);
    $f['uscita'] = ['disiscritto' => !empty($u['disiscritto']), 'segmento' => max(0, (int)($u['segmento'] ?? 0))];
    $f['tag'] = newsletter_4byte_fuori((string)($f['tag'] ?? ''));
    return $f;
}

/** Gli inneschi puliti: tipo dall'elenco chiuso, id (lista, form, campagna, giorni), attributo per l'anniversario. Al massimo NL_AUT_MAX_INNESCHI, senza doppi. */
function nl_inneschi_validi($in): array {
    $out = []; $visti = [];
    foreach (is_array($in) ? array_slice($in, 0, NL_AUT_MAX_INNESCHI * 3) : [] as $x) {
        if (!is_array($x) || !isset(nl_inneschi_nomi()[$t = (string)($x['tipo'] ?? '')])) continue;
        $id = max(-365, min(3650, (int)($x['id'] ?? 0)));
        $attr = $t === 'anniversario' && preg_match('/^[A-Z0-9_]{1,50}$/', (string)($x['attr'] ?? '')) ? (string)$x['attr'] : '';
        if (!in_array($t, ['lista', 'lista_tolto', 'form', 'apertura', 'clic', 'inattivo', 'anniversario', 'test'], true)) $id = 0;
        if ($t === 'anniversario') $id = max(0, min(365, $id));
        // 2.22.0: "Completa un test": id del test e, facoltativo, il risultato (f:<fascia>, a:<area>:<fascia>, p:<profilo>, r:<regola>).
        if ($t === 'test') { $id = max(0, min(999999999, (int)($x['id'] ?? 0))); $attr = preg_match('/^[a-z]:[a-z0-9:]{1,40}$/', (string)($x['esito'] ?? '')) ? (string)$x['esito'] : ''; }
        // 2.23.0: "Riceve un tag": i tag scelti (id), nessuno = qualunque tag.
        $tag = [];
        if ($t === 'tag') {
            $tag = array_values(array_unique(array_filter(array_map(fn($v) => is_scalar($v) && preg_match('/^\d{1,9}$/', (string)$v) ? (int)$v : 0, array_slice(is_array($x['tag'] ?? null) ? $x['tag'] : [], 0, 50)))));
            sort($tag);
            $attr = implode(',', $tag);
        }
        if (isset($visti["$t|$id|$attr"]) || count($out) >= NL_AUT_MAX_INNESCHI) continue;
        $visti["$t|$id|$attr"] = true;
        $out[] = ['tipo' => $t, 'id' => $id] + ($t === 'anniversario' ? ['attr' => $attr] : []) + ($t === 'test' ? ['esito' => $attr] : []) + ($t === 'tag' ? ['tag' => $tag] : []);
    }
    return $out;
}

/** Il grafo di una riga del database. Una sequenza vecchia (elenco di passi) diventa grafo qui. */
function newsletter_flusso_grafo(array $f): array {
    $g = json_decode((string)($f['passi'] ?? ''), true, 64) ?: [];
    if (isset($g['nodi'])) return newsletter_flusso_grafo_valido($g);
    // Formato di prima: passi in fila, ognuno con attesa (giorni) e condizione sull'email precedente o su una lista.
    $nodi = [['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 40, 'dati' => []]];
    $archi = [];
    $da = ['nodo' => 0, 'uscita' => 'poi'];
    $n = 1; $y = 40; $ultimaEmail = 0;
    foreach ((array)$g as $p) {
        if (!is_array($p) || trim((string)($p['oggetto'] ?? '')) === '') continue;
        if ((int)($p['attesa'] ?? 0) > 0) {
            $y += 150;
            $nodi[] = ['id' => $n, 'tipo' => 'attesa', 'x' => 40, 'y' => $y, 'dati' => ['minuti' => (int)$p['attesa'] * 1440]];
            $archi[] = ['da' => $da['nodo'], 'uscita' => $da['uscita'], 'a' => $n];
            $da = ['nodo' => $n, 'uscita' => 'poi']; $n++;
        }
        $cond = (string)($p['condizione'] ?? 'sempre');
        $mappa = ['aperto' => ['aperta', $ultimaEmail], 'non_aperto' => ['non_aperta', $ultimaEmail], 'clic' => ['cliccata', $ultimaEmail], 'in_lista' => ['in_lista', (int)($p['condizione_id'] ?? 0)], 'non_in_lista' => ['non_in_lista', (int)($p['condizione_id'] ?? 0)]];
        $bivio = 0;
        if (isset($mappa[$cond]) && ($ultimaEmail || str_contains($cond, 'lista'))) {
            $y += 150;
            $nodi[] = ['id' => $n, 'tipo' => 'bivio', 'x' => 40, 'y' => $y, 'dati' => ['condizione' => $mappa[$cond][0], 'valore' => $mappa[$cond][1]]];
            $archi[] = ['da' => $da['nodo'], 'uscita' => $da['uscita'], 'a' => $n];
            $bivio = $n; $n++;
        }
        $y += 150;
        $nodi[] = ['id' => $n, 'tipo' => 'email', 'x' => $bivio ? 360 : 40, 'y' => $y, 'dati' => ['oggetto' => $p['oggetto'], 'anteprima' => $p['anteprima'] ?? '', 'corpo' => $p['corpo'] ?? '', 'pulsante_testo' => $p['pulsante_testo'] ?? '', 'pulsante_link' => $p['pulsante_link'] ?? '', 'ascolto' => 0]];
        if ($bivio) {
            $archi[] = ['da' => $bivio, 'uscita' => 'si', 'a' => $n];
            // Il "no" del bivio salta l'email e raggiunge quello che viene dopo: lo si chiude al giro successivo.
            $da = ['nodo' => $n, 'uscita' => 'poi', 'anche' => ['nodo' => $bivio, 'uscita' => 'no']];
        } else {
            $archi[] = ['da' => $da['nodo'], 'uscita' => $da['uscita'], 'a' => $n];
            $da = ['nodo' => $n, 'uscita' => 'poi'];
        }
        if (!empty($da['anche']) && $da['nodo'] !== $n) { $archi[] = ['da' => $da['anche']['nodo'], 'uscita' => $da['anche']['uscita'], 'a' => $n]; unset($da['anche']); }
        $ultimaEmail = $n; $n++;
    }
    return newsletter_flusso_grafo_valido(['nodi' => $nodi, 'archi' => $archi]);
}

/** Le uscite di un nodo, con le etichette: fisse per tipo, quelle delle divisioni dai loro rami. */
function nl_uscite(array $n): array {
    if ($n['tipo'] === 'dividi') {
        $u = [];
        foreach ($n['dati']['rami'] as $i => $r) $u["r$i"] = $r['nome'] !== '' ? $r['nome'] : 'Ramo ' . ($i + 1);
        return $u + ['altro' => 'Altrimenti'];
    }
    if ($n['tipo'] === 'percento') {
        $u = [];
        foreach ($n['dati']['rami'] as $i => $p) $u["p$i"] = $p . '%';
        return $u;
    }
    return NL_USCITE[$n['tipo']] ?? [];
}

/** Le regole di un ramo: arrivano come array o come JSON in base64 (il costruttore dei segmenti). Non si controllano qui. */
function nl_regole_grezze($r): array {
    if (is_string($r) && $r !== '' && strlen($r) < 200000) $r = json_decode($r[0] === '{' ? $r : (string)base64_decode(strtr($r, '-_', '+/'), true), true, 7);
    return is_array($r) && is_array($r['gruppi'] ?? null) ? ['op' => ($r['op'] ?? 'e') === 'o' ? 'o' : 'e', 'gruppi' => array_slice(array_values($r['gruppi']), 0, 20)] : ['op' => 'e', 'gruppi' => []];
}

/**
 * Ripulisce un grafo arrivato dalla tela o dal database: tipi da NL_USCITE (elenco chiuso), dati di ogni tipo con i loro
 * campi e i loro limiti, al massimo NL_AUT_MAX_NODI blocchi, archi solo fra nodi esistenti e da uscite esistenti, una per uscita.
 * I giri chiusi e i controlli che vogliono un messaggio (oggetto mancante, percentuali) li guarda newsletter_flusso_salva().
 */
function newsletter_flusso_grafo_valido($g): array {
    $g = is_array($g) ? $g : [];
    $nodi = []; $ids = [];
    foreach (array_slice(is_array($g['nodi'] ?? null) ? $g['nodi'] : [], 0, NL_AUT_MAX_NODI + 1) as $n) {
        if (!is_array($n)) continue;
        $id = (int)($n['id'] ?? -1);
        $tipo = (string)($n['tipo'] ?? '');
        if ($id < 0 || $id > 100000 || isset($ids[$id]) || !array_key_exists($tipo, NL_USCITE)) continue;
        if ($tipo === 'innesco' && $id !== 0) continue;
        if ($tipo !== 'innesco' && $id === 0) continue;
        $d = is_array($n['dati'] ?? null) ? $n['dati'] : [];
        // Ogni campo passa da una stringa: un valore strano arrivato dal browser (un elenco, un oggetto) non deve far cadere la pagina.
        $s = fn($k, $max) => mb_substr(trim(is_scalar($d[$k] ?? null) ? (string)$d[$k] : ''), 0, $max);
        $int = fn($k, $min, $max, $def = 0) => max($min, min($max, is_numeric($d[$k] ?? null) ? (int)$d[$k] : $def));
        $link = $s('pulsante_link', 500);
        if ($link !== '' && !preg_match('~^(https?://|mailto:|tel:)~i', $link)) $link = 'https://' . ltrim($link, '/');
        $cond = is_scalar($d['condizione'] ?? null) ? (string)$d['condizione'] : '';
        $dati = match ($tipo) {
            'email' => [
                'oggetto' => $s('oggetto', 255), 'anteprima' => $s('anteprima', 255),
                'corpo' => is_scalar($d['corpo'] ?? null) ? mb_substr((string)$d['corpo'], 0, 300000) : '', 'pulsante_testo' => $s('pulsante_testo', 100), 'pulsante_link' => $link,
                'ascolto' => $int('ascolto', 0, 365 * 1440),
            ] + nl_email_extra($d, $s, $int),
            'attesa' => ['minuti' => $int('minuti', 1, 365 * 1440, 1440)],
            'bivio' => ['condizione' => isset(NL_CONDIZIONI[$cond]) ? $cond : 'in_lista', 'valore' => $int('valore', 0, 100000)],
            'evento' => ['evento' => $s('evento', 10) === 'clic' ? 'clic' : 'apertura', 'email' => $int('email', 0, 100000), 'massimo' => $int('massimo', 1, 365 * 1440, 1440)],
            'dividi' => ['rami' => array_map(fn($r) => ['nome' => mb_substr(trim(is_array($r) && is_scalar($r['nome'] ?? null) ? (string)$r['nome'] : ''), 0, 60), 'regole' => nl_regole_grezze(is_array($r) ? ($r['regole'] ?? null) : null)],
                array_slice(array_values(is_array($d['rami'] ?? null) ? $d['rami'] : []), 0, NL_AUT_MAX_RAMI)) ?: [['nome' => '', 'regole' => ['op' => 'e', 'gruppi' => []]]]],
            'percento' => ['rami' => array_map(fn($p) => max(1, min(99, (int)$p)), array_slice(array_values(array_filter(is_array($d['rami'] ?? null) ? $d['rami'] : [], 'is_numeric')), 0, 5)) ?: [50, 50]],
            'lista_aggiungi', 'lista_togli' => ['lista' => $int('lista', 0, 1000000)],
            'attributo' => ['nome' => preg_match('/^[A-Z0-9_]{1,50}$/', $s('nome', 50)) ? $s('nome', 50) : '', 'valore' => $s('valore', 500)],
            'webhook' => ['url' => $s('url', 500)],
            'tag_aggiungi', 'tag_togli' => ['tag' => array_values(array_unique(array_filter(array_map(fn($v) => is_scalar($v) && preg_match('/^\d{1,9}$/', (string)$v) ? (int)$v : 0, array_slice(is_array($d['tag'] ?? null) ? $d['tag'] : [], 0, 20)))))],
            default => [],
        };
        if ($tipo === 'percento' && count($dati['rami']) < 2) $dati['rami'] = [50, 50];
        $ids[$id] = true;
        $nodi[] = ['id' => $id, 'tipo' => $tipo, 'x' => max(0, min(6000, (int)($n['x'] ?? 0))), 'y' => max(0, min(6000, (int)($n['y'] ?? 0))), 'dati' => $dati];
    }
    if (!isset($ids[0])) array_unshift($nodi, ['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 40, 'dati' => []]);
    $uscite = [];
    foreach ($nodi as $n) $uscite[$n['id']] = nl_uscite($n);
    $archi = []; $visti = [];
    foreach (array_slice(is_array($g['archi'] ?? null) ? $g['archi'] : [], 0, NL_AUT_MAX_NODI * 12) as $a) {
        if (!is_array($a)) continue;
        $da = (int)($a['da'] ?? -1); $u = is_scalar($a['uscita'] ?? null) ? (string)$a['uscita'] : ''; $al = (int)($a['a'] ?? -1);
        if (!isset($uscite[$da], $uscite[$al]) || $da === $al || $al === 0 || !isset($uscite[$da][$u]) || isset($visti["$da|$u"])) continue;
        $visti["$da|$u"] = true;
        $archi[] = ['da' => $da, 'uscita' => $u, 'a' => $al];
    }
    return ['nodi' => $nodi, 'archi' => $archi];
}

/** I campi del passo Email arrivati con la 2.15.0. Un'email di prima non li ha: valgono i predefiniti, e l'email resta quella di prima. */
function nl_email_extra(array $d, callable $s, callable $int): array {
    $orario = $s('orario', 10);
    // 2.17.1: "html" (un modello in codice HTML già copiato). 2.18.0: "modello" vuol dire che il passo richiama il modello
    // numero 'modello' e lo legge a ogni invio (nl_passo_modello()): l'id resta salvato, design e html restano vuoti.
    $ed = $s('editor', 10);
    return ['editor' => in_array($ed, ['blocchi', 'html', 'modello'], true) ? $ed : 'semplice', 'design' => is_string($d['design'] ?? null) ? mb_substr($d['design'], 0, 400000) : '',
        // Il codice HTML (da un modello) si tiene quando il grafo si rilegge dal database; al salvataggio newsletter_flusso_salva()
        // lo rimette sempre da quello già salvato o dal modello, quindi quello del browser non conta.
        'html' => is_string($d['html'] ?? null) ? mb_substr($d['html'], 0, 1500000) : '',
        'modello' => $int('modello', 0, 1000000), 'mittente_id' => $int('mittente_id', 0, 1000000),
        'orario' => in_array($orario, ['ora', 'migliore'], true) ? $orario : 'subito', 'ora' => $int('ora', 0, 23, 10)];
}

/** Il nodo con questo id, o null. */
function newsletter_flusso_nodo(array $g, int $id): ?array {
    foreach ($g['nodi'] as $n) if ($n['id'] === $id) return $n;
    return null;
}

/** Dove porta un'uscita di un nodo: l'id del nodo di arrivo, o null se l'uscita è libera (il cammino finisce). */
function newsletter_flusso_arrivo(array $g, int $da, string $uscita): ?int {
    foreach ($g['archi'] as $a) if ($a['da'] === $da && $a['uscita'] === $uscita) return $a['a'];
    return null;
}

/** Le email del flusso, per i menu (bivi: "ha aperto l'email..."). */
function newsletter_flusso_email(array $g): array {
    $out = [];
    foreach ($g['nodi'] as $n) if ($n['tipo'] === 'email') $out[$n['id']] = $n['dati']['oggetto'] !== '' ? $n['dati']['oggetto'] : 'Email ' . $n['id'];
    return $out;
}

/** Il grafo com'è arrivato dal form (JSON nel campo nascosto; il base64 di campi-protetti.js lo scioglie già auth.php). */
function newsletter_flusso_grafo_dal_form($grafo): array {
    if (is_array($grafo)) return $grafo;
    $s = is_scalar($grafo) ? trim((string)$grafo) : '';
    $g = strlen($s) <= 2000000 ? json_decode($s, true, 64) : null;
    if (!is_array($g)) throw new RuntimeException('Il disegno dell\'automazione non è arrivato intero. Riprova a salvare.');
    if (is_array($g['nodi'] ?? null) && count($g['nodi']) > NL_AUT_MAX_NODI) throw new RuntimeException('Al massimo ' . NL_AUT_MAX_NODI . ' blocchi in un\'automazione: dividila in due.');
    return $g;
}

/** Il primo nodo che torna indietro (un giro chiuso), o null. Visita in profondità dall'innesco e da ogni nodo. */
function nl_grafo_giro(array $g): ?int {
    $fuori = [];
    foreach ($g['archi'] as $a) $fuori[$a['da']][] = $a['a'];
    $colore = [];
    foreach ($g['nodi'] as $n) {
        if (isset($colore[$n['id']])) continue;
        $pila = [[$n['id'], 0]];
        $colore[$n['id']] = 1;
        while ($pila) {
            [$v, $i] = $pila[count($pila) - 1];
            if ($i < count($fuori[$v] ?? [])) {
                $pila[count($pila) - 1][1]++;
                $w = $fuori[$v][$i];
                if (($colore[$w] ?? 0) === 1) return $w;
                if (!isset($colore[$w])) { $colore[$w] = 1; $pila[] = [$w, 0]; }
            } else { $colore[$v] = 2; array_pop($pila); }
        }
    }
    return null;
}

/**
 * Salva un'automazione. $d: nome, stato (o attivo, come prima), inneschi (JSON o elenco; o innesco e innesco_id, come prima),
 * grafo, mittente_id, tag, rientro, rientro_attesa (ore), uscita_disiscritto, uscita_segmento. Ritorna l'id.
 */
function newsletter_flusso_salva(int $id, array $d): int {
    newsletter_flussi_schema();
    $vecchio = $id ? newsletter_flusso($id) : null;
    $nome = mb_substr(trim(is_scalar($d['nome'] ?? null) ? (string)$d['nome'] : ''), 0, 100);
    if ($nome === '') throw new RuntimeException('L\'automazione ha bisogno di un nome.');
    $g = newsletter_flusso_grafo_valido(newsletter_flusso_grafo_dal_form($d['grafo'] ?? []));
    $tipi = array_count_values(array_column($g['nodi'], 'tipo'));
    if (count($g['nodi']) < 2) throw new RuntimeException('Metti almeno un blocco sulla tela, dopo l\'innesco.');
    if (newsletter_flusso_arrivo($g, 0, 'poi') === null) throw new RuntimeException("Collega l'innesco al primo blocco: trascina dal suo pallino a un blocco.");
    if (($giro = nl_grafo_giro($g)) !== null) throw new RuntimeException("Il disegno fa un giro chiuso: un collegamento torna al blocco #$giro. Togli il collegamento che torna indietro (un'automazione va sempre avanti).");
    if (!function_exists('nd_modello')) @include_once __DIR__ . '/newsletter-design.php';
    $mod = function_exists('nd_modello') ? 'nd_modello' : null;
    $stacca = is_numeric($d['stacca'] ?? null) ? (int)$d['stacca'] : -1; // 2.18.0: il passo da staccare dal suo modello
    foreach ($g['nodi'] as &$n) {
        $dn = &$n['dati'];
        $dove = '#' . $n['id'];
        switch ($n['tipo']) {
            case 'email':
                $dn['corpo'] = newsletter_4byte_dentro($dn['corpo']);
                // Il design a blocchi lo scrive l'editor delle email (nl_aut_design_salva) e il codice HTML arriva solo da un
                // modello: quelli del browser non valgono.
                $prima = $vecchio ? newsletter_flusso_nodo($vecchio['grafo'], $n['id']) : null;
                $dn['design'] = $prima && $prima['tipo'] === 'email' ? (string)($prima['dati']['design'] ?? '') : '';
                $dn['html'] = $prima && $prima['tipo'] === 'email' ? (string)($prima['dati']['html'] ?? '') : '';
                if ($dn['editor'] === 'modello') {
                    // 2.18.0: "Da un modello" richiama il modello (si legge a ogni invio, nl_passo_modello()); la 2.17.1 lo copiava.
                    if ($dn['modello'] <= 0) throw new RuntimeException("L'email $dove: scegli il modello da usare, oppure il testo semplice o l'editor a blocchi.");
                    $m = $mod ? $mod($dn['modello']) : null;
                    if (!$m || $m['tag'] === 'optin') throw new RuntimeException("L'email $dove: il modello scelto non c'è più. Scegline un altro.");
                    $oggM = trim(newsletter_4byte_fuori((string)$m['oggetto']));
                    if ($stacca === $n['id']) {
                        // "Stacca dal modello": il contenuto di adesso si copia nel passo, che da qui in poi è indipendente (come la 2.17.1).
                        if ($m['editor'] === 'html') { $dn['html'] = (string)$m['html']; $dn['editor'] = 'html'; }
                        else { $dn['design'] = (string)$m['design']; $dn['editor'] = 'blocchi'; }
                        if ($dn['oggetto'] === '') $dn['oggetto'] = mb_substr($oggM, 0, 255);
                        if ($dn['anteprima'] === '') $dn['anteprima'] = mb_substr(trim(newsletter_4byte_fuori((string)($m['anteprima'] ?? ''))), 0, 255);
                        $dn['modello'] = 0;
                    } else {
                        $dn['design'] = ''; $dn['html'] = '';
                        if ($dn['oggetto'] === '' && $oggM === '') throw new RuntimeException("L'email $dove: scrivi l'oggetto, oppure dallo al modello «" . newsletter_4byte_fuori((string)$m['nome']) . "» (Modelli, «Oggetto e uso»).");
                    }
                } else {
                    $dn['modello'] = 0;
                }
                if ($dn['editor'] === 'html' && trim($dn['html']) === '') $dn['editor'] = 'semplice';
                if ($dn['oggetto'] === '' && $dn['editor'] !== 'modello') throw new RuntimeException("L'email $dove è senza oggetto: aprila e scrivilo.");
                break;
            case 'percento':
                if (array_sum($dn['rami']) !== 100) throw new RuntimeException("La divisione in percentuale $dove fa " . array_sum($dn['rami']) . '%: le parti devono fare 100.');
                break;
            case 'dividi':
                foreach ($dn['rami'] as $i => &$r) {
                    if (!$r['regole']['gruppi']) throw new RuntimeException("La divisione $dove: il ramo " . ($i + 1) . ' non ha condizioni. Aggiungine una, o togli il ramo.');
                    $ok = newsletter_regole_valida($r['regole'], ['flusso' => max(1, $id)]);
                    if (isset($ok['errore'])) throw new RuntimeException("La divisione $dove, ramo " . ($i + 1) . ': ' . $ok['errore']);
                    $r['regole'] = $ok['regole'];
                    $r['nome'] = newsletter_4byte_dentro($r['nome']);
                }
                unset($r);
                break;
            case 'lista_aggiungi': case 'lista_togli':
                if ($dn['lista'] < 1) throw new RuntimeException("Il blocco $dove: scegli la lista.");
                break;
            case 'tag_aggiungi': case 'tag_togli':
                // 2.23.0: solo tag che esistono (i tag nuovi si creano nella pagina Tag).
                if (!function_exists('newsletter_tag_nomi')) require_once __DIR__ . '/newsletter-tag.php';
                $dn['tag'] = array_values(array_intersect($dn['tag'], array_keys(newsletter_tag_nomi())));
                if (!$dn['tag']) throw new RuntimeException("Il blocco $dove: scegli almeno un tag. I tag nuovi si creano in Newsletter e form, Tag.");
                break;
            case 'attributo':
                $a = $dn['nome'] !== '' && function_exists('newsletter_attributi') ? (newsletter_attributi()[$dn['nome']] ?? null) : null;
                if (!$a || in_array($a['categoria'], ['calculated', 'global'], true) || ((int)$a['sistema'] && !in_array($a['colonna'], ['nome', 'cognome'], true))) throw new RuntimeException("Il blocco $dove: scegli l'attributo da cambiare.");
                if ($dn['valore'] !== '' && !newsletter_valore_leggi($a, $dn['valore'])[0]) throw new RuntimeException("Il blocco $dove: il valore non va bene per l'attributo " . $dn['nome'] . ' (' . (NL_ATTR_TIPI[$a['tipo']] ?? $a['tipo']) . ').');
                $dn['valore'] = newsletter_4byte_dentro($dn['valore']);
                break;
            case 'webhook':
                if (!function_exists('nl_aut_webhook_controlla')) require_once __DIR__ . '/newsletter-automazioni.php';
                $e = nl_aut_webhook_controlla($dn['url'], true);
                if ($e['errore'] !== '') throw new RuntimeException("Il webhook $dove: " . $e['errore']);
                break;
        }
        unset($dn);
    }
    unset($n);
    // Inneschi: elenco nuovo (JSON) oppure i due campi di prima.
    $inn = $d['inneschi'] ?? null;
    if (is_string($inn)) $inn = json_decode($inn, true, 4);
    if (!is_array($inn)) $inn = [['tipo' => is_scalar($d['innesco'] ?? null) ? (string)$d['innesco'] : 'iscrizione', 'id' => (int)($d['innesco_id'] ?? 0)]];
    $inn = nl_inneschi_validi($inn);
    if (!$inn) throw new RuntimeException('Scegli almeno un innesco: quando entra una persona nell\'automazione.');
    foreach ($inn as $x) {
        if ($x['tipo'] === 'inattivo' && $x['id'] < 1) throw new RuntimeException("Per l'innesco \"non fa niente da tanti giorni\" serve il numero di giorni.");
        if ($x['tipo'] === 'anniversario' && $x['attr'] === '') throw new RuntimeException("Per l'innesco \"anniversario\" scegli l'attributo con la data.");
    }
    $stato = is_scalar($d['stato'] ?? null) && isset(NL_AUT_STATI[(string)$d['stato']]) ? (string)$d['stato'] : (!empty($d['attivo']) ? 'attiva' : 'inattiva');
    $ore = max(0, min(8760, (int)($d['rientro_attesa'] ?? 0)));
    $rientro = !empty($d['rientro']) ? 1 : 0;
    if ($rientro && $ore < 1) $ore = 1; // almeno un'ora: due automazioni non si rimbalzano un contatto a ogni giro
    $uscita = ['disiscritto' => !empty($d['uscita_disiscritto']), 'segmento' => max(0, (int)($d['uscita_segmento'] ?? 0))];
    if ($uscita['segmento'] && function_exists('newsletter_segmento') && !newsletter_segmento($uscita['segmento'])) $uscita['segmento'] = 0;
    $tag = implode(', ', array_slice(array_values(array_unique(array_filter(array_map(fn($t) => mb_substr(trim(strip_tags($t)), 0, 40), explode(',', is_scalar($d['tag'] ?? null) ? (string)$d['tag'] : ''))))), 0, 5));
    // Le colonne sono utf8 a tre byte: un'emoji nel nome o nell'oggetto farebbe rifiutare la riga (o la taglierebbe).
    // Il nome passa dalle entità HTML, il grafo si salva in JSON con i caratteri fuori ASCII scritti come \uXXXX.
    $passi = json_encode($g, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($passi === false) throw new RuntimeException('L\'automazione contiene caratteri che non si possono salvare: ' . json_last_error_msg());
    $v = ['nome' => mb_substr(newsletter_4byte_dentro($nome), 0, 100), 'attivo' => $stato === 'attiva' ? 1 : 0, 'innesco' => $inn[0]['tipo'], 'innesco_id' => $inn[0]['id'], 'passi' => $passi,
        'stato' => $stato, 'inneschi' => json_encode($inn), 'tag' => mb_substr(newsletter_4byte_dentro($tag), 0, 255), 'rientro' => $rientro, 'rientro_attesa' => $ore, 'uscita' => json_encode($uscita)];
    if ($id) {
        db()->prepare('UPDATE ' . tbl('newsletter_flussi') . ' SET nome = :nome, attivo = :attivo, innesco = :innesco, innesco_id = :innesco_id, passi = :passi, stato = :stato, inneschi = :inneschi, tag = :tag, rientro = :rientro, rientro_attesa = :rientro_attesa, uscita = :uscita, aggiornato = NOW() WHERE id = :id')->execute($v + ['id' => $id]);
    } else {
        db()->prepare('INSERT INTO ' . tbl('newsletter_flussi') . ' (nome, attivo, innesco, innesco_id, passi, stato, inneschi, tag, rientro, rientro_attesa, uscita, creato, aggiornato) VALUES (:nome, :attivo, :innesco, :innesco_id, :passi, :stato, :inneschi, :tag, :rientro, :rientro_attesa, :uscita, NOW(), NOW())')->execute($v);
        $id = (int)db()->lastInsertId();
    }
    // 2.1.0: il mittente del flusso, solo se è un mittente confermato (0 = il predefinito).
    if (array_key_exists('mittente_id', $d)) {
        $m = consegna_mittente((int)$d['mittente_id']);
        db()->prepare('UPDATE ' . tbl('newsletter_flussi') . ' SET mittente_id = ? WHERE id = ?')->execute([$m && $m['verificato_at'] ? (int)$m['id'] : 0, $id]);
    }
    if ($stato === 'inattiva') nl_aut_togli_tutti($id, 'automazione inattiva');
    nl_flussi_attivi(true);
    return $id;
}

function newsletter_flusso_elimina(int $id): void {
    foreach (['newsletter_corsi', 'newsletter_flussi_registro', 'newsletter_flussi_numeri'] as $t) db()->prepare('DELETE FROM ' . tbl($t) . ' WHERE flusso_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_flussi') . ' WHERE id = ?')->execute([$id]);
}

/** Una riga del registro dell'automazione (Attività). */
function nl_registro(int $f, int $corso, int $iscritto, int $nodo, string $evento, string $dettaglio = ''): void {
    db()->prepare('INSERT INTO ' . tbl('newsletter_flussi_registro') . ' (flusso_id, corso_id, iscritto_id, nodo, evento, dettaglio, creato) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$f, $corso, $iscritto, $nodo, $evento, mb_substr(newsletter_4byte_dentro($dettaglio), 0, 255)]);
}

/** Un contatore di un passo: passati, inviate, o il ramo preso (r0, p1, si, altro...). */
function nl_conta(int $f, int $nodo, string $chiave, int $quanti = 1): void {
    db()->prepare('INSERT INTO ' . tbl('newsletter_flussi_numeri') . ' (flusso_id, nodo, chiave, n) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE n = n + VALUES(n)')->execute([$f, $nodo, $chiave, $quanti]);
}

/** Toglie dall'automazione tutti quelli che ci sono dentro (Inattiva, eliminazione di una condizione...). Ritorna quanti. */
function nl_aut_togli_tutti(int $f, string $perche): int {
    $ids = db()->prepare('SELECT id, iscritto_id, passo FROM ' . tbl('newsletter_corsi') . " WHERE flusso_id = ? AND stato = 'attivo' LIMIT 5000");
    $ids->execute([$f]);
    $n = 0;
    foreach ($ids->fetchAll() as $c) $n += nl_corso_chiudi((int)$c['id'], 'tolto', $perche, $f, (int)$c['iscritto_id'], (int)$c['passo']);
    return $n;
}

/** Chiude un cammino: finito, fermato o tolto, con la riga del registro. Ritorna 1 se l'ha chiuso adesso. */
function nl_corso_chiudi(int $corso, string $stato, string $perche, int $f, int $iscritto, int $nodo): int {
    $st = db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = ?, prossimo = NULL, ascolto = NULL, aggiornato = NOW() WHERE id = ? AND stato = 'attivo'");
    $st->execute([$stato, $corso]);
    if (!$st->rowCount()) return 0;
    nl_registro($f, $corso, $iscritto, $nodo, $stato === 'finito' ? 'finito' : ($stato === 'tolto' && str_starts_with($perche, 'esce') ? 'uscito' : $stato), $perche);
    return 1;
}

/**
 * Mette una persona in cammino, al nodo dopo l'innesco. Una volta sola per giro: chi è già dentro non rientra; chi ha finito
 * rientra solo se l'automazione lo permette e l'attesa è passata (l'anniversario rientra una volta l'anno). Ritorna l'id del cammino o 0.
 */
function newsletter_flusso_parti(array $f, int $iscritto, string $perche = ''): int {
    $g = $f['grafo'] ?? newsletter_flusso_grafo($f);
    $primo = newsletter_flusso_arrivo($g, 0, 'poi');
    if ($primo === null || !$iscritto) return 0;
    $st = db()->prepare('SELECT COALESCE(MAX(giro), 0) AS giro, SUM(stato = \'attivo\') AS dentro, MAX(aggiornato) AS ultimo, MAX(creato) AS entrato FROM ' . tbl('newsletter_corsi') . ' WHERE flusso_id = ? AND iscritto_id = ?');
    $st->execute([(int)$f['id'], $iscritto]);
    $p = $st->fetch();
    if ((int)$p['giro'] > 0) {
        if ((int)$p['dentro'] > 0) return 0;
        $anno = $perche === 'anniversario' && strtotime((string)$p['entrato']) < time() - 300 * 86400;
        if (!$anno && (empty($f['rientro']) || strtotime((string)$p['ultimo']) > time() - max(1, (int)$f['rientro_attesa']) * 3600)) return 0;
    }
    $ins = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_corsi') . " (flusso_id, iscritto_id, passo, prossimo, stato, ascolto, salti, giro, creato, aggiornato) VALUES (?, ?, ?, NOW(), 'attivo', NULL, 0, ?, NOW(), NOW())");
    $ins->execute([(int)$f['id'], $iscritto, $primo, (int)$p['giro'] + 1]);
    if (!$ins->rowCount()) return 0;
    $corso = (int)db()->lastInsertId();
    nl_registro((int)$f['id'], $corso, $iscritto, 0, 'entrato', (nl_inneschi_nomi()[$perche] ?? $perche) . ((int)$p['giro'] > 0 ? ' (rientro, giro ' . ((int)$p['giro'] + 1) . ')' : ''));
    nl_conta((int)$f['id'], 0, 'passati');
    nl_conta((int)$f['id'], $primo, 'passati');
    return $corso;
}

/** Le automazioni attive (solo loro fanno entrare qualcuno), una volta per richiesta. */
function nl_flussi_attivi(bool $ricarica = false): array {
    static $tutti = null;
    if ($tutti === null || $ricarica) {
        $tutti = [];
        foreach (db()->query('SELECT * FROM ' . tbl('newsletter_flussi') . " WHERE stato = 'attiva'")->fetchAll() as $f) $tutti[] = nl_flusso_riga($f);
    }
    return $tutti;
}

/**
 * Un innesco: fa partire le automazioni attive che lo aspettano (uno dei loro inneschi), per questo iscritto.
 * 2.22.0: $esiti sono i codici del risultato di un test (innesco 'test'): un innesco con il filtro sul risultato parte
 * solo se il suo codice è fra questi. Gli altri inneschi non lo usano.
 */
function newsletter_flussi_innesco(string $innesco, int $innescoId, int $iscritto, array $esiti = []): void {
    if (!$iscritto) return;
    newsletter_flussi_schema();
    // 2.0.0: solo per chi è attivo. Prima un cammino partiva anche per chi doveva ancora confermare, il giro lo fermava
    // e la chiave unica (flusso, iscritto) non lo faceva più ripartire. Chi aspetta la conferma: l'innesco "form" si
    // ricorda e parte alla conferma (newsletter_conferma_esito); gli altri inneschi arrivano già da soli dopo la conferma.
    // 2.15.0: "si disiscrive" parte proprio per chi non è più attivo.
    $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$iscritto]);
    if ($innesco !== 'disiscritto' && $st->fetchColumn() !== 'attivo') {
        if ($innesco === 'form') newsletter_innesco_in_attesa($innesco, $innescoId, $iscritto);
        return;
    }
    foreach (nl_flussi_attivi() as $f) foreach ($f['inneschi'] as $x) {
        if ($x['tipo'] !== $innesco) continue;
        // 2.23.0: "Riceve un tag" guarda i tag scelti (nessuno = qualunque); gli altri inneschi come prima.
        $vale = $innesco === 'tag' ? (empty($x['tag']) || in_array($innescoId, $x['tag'], true))
            : (($x['id'] === 0 || $x['id'] === $innescoId) && (($x['esito'] ?? '') === '' || in_array($x['esito'], $esiti, true)));
        if ($vale) { newsletter_flusso_parti($f, $iscritto, $innesco); break; }
    }
}

/** L'innesco "inattivo da N giorni": a ogni giro cerca gli iscritti attivi che non aprono e non cliccano da N giorni (e sono iscritti da almeno N giorni) e li mette in cammino. */
function newsletter_flussi_inattivi(): void {
    foreach (nl_flussi_attivi() as $f) foreach ($f['inneschi'] as $x) {
        if ($x['tipo'] !== 'inattivo' || $x['id'] < 1) continue;
        $g = $x['id'];
        // Senza rientro: chi è già passato di qui non si guarda più (come prima). Con il rientro: chi non è dentro e ha finito da abbastanza.
        $gia = $f['rientro'] ? " AND (c.stato = 'attivo' OR c.aggiornato > DATE_SUB(NOW(), INTERVAL " . max(1, (int)$f['rientro_attesa']) . ' HOUR))' : '';
        $q = db()->prepare('SELECT i.id FROM ' . tbl('newsletter_iscritti') . " i WHERE i.stato = 'attivo' AND COALESCE(i.consenso_at, i.creato) <= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND NOT EXISTS (SELECT 1 FROM " . tbl('newsletter_eventi') . ' e WHERE e.iscritto_id = i.id AND e.creato > DATE_SUB(NOW(), INTERVAL ? DAY))
            AND NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_corsi') . " c WHERE c.flusso_id = ? AND c.iscritto_id = i.id$gia) LIMIT 200");
        $q->execute([$g, $g, (int)$f['id']]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $i) newsletter_flusso_parti($f, (int)$i, 'inattivo');
    }
}

/**
 * La condizione di un bivio vale per questa persona? 2.10.0: con il motore dei segmenti (newsletter_bivio_vale), che
 * traduce le nove condizioni di prima nelle sue con lo stesso esito. Il codice di prima resta se il motore manca
 * (opcache può dare per qualche secondo i file di prima dopo l'aggiornamento).
 */
function newsletter_flusso_condizione(array $f, array $nodo, int $iscritto): bool {
    $c = $nodo['dati']['condizione']; $v = (int)$nodo['dati']['valore'];
    if (!function_exists('newsletter_bivio_vale')) @include_once __DIR__ . '/newsletter-segmenti.php';
    if (function_exists('newsletter_bivio_vale')) return newsletter_bivio_vale((int)$f['id'], (string)$c, $v, $iscritto);
    switch ($c) {
        case 'in_lista': case 'non_in_lista':
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ? AND lista_id = ?');
            $st->execute([$iscritto, $v]);
            $si = (int)$st->fetchColumn() > 0;
            return $c === 'in_lista' ? $si : !$si;
        case 'aperta': case 'non_aperta': case 'cliccata': case 'non_cliccata':
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_eventi') . ' WHERE flusso_id = ? AND passo = ? AND iscritto_id = ? AND tipo = ?');
            $st->execute([(int)$f['id'], $v, $iscritto, str_contains($c, 'clicc') ? 'clic' : 'apertura']);
            $si = (int)$st->fetchColumn() > 0;
            return str_starts_with($c, 'non_') ? !$si : $si;
        case 'inattivo': case 'attivo':
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_eventi') . ' WHERE iscritto_id = ? AND creato > DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$iscritto, max(1, $v)]);
            $si = (int)$st->fetchColumn() > 0;
            return $c === 'attivo' ? $si : !$si;
        case 'iscritto_da':
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ? AND COALESCE(consenso_at, creato) <= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$iscritto, max(0, $v)]);
            return (int)$st->fetchColumn() > 0;
    }
    return true;
}

/** Sposta un cammino lungo un'uscita: al nodo di arrivo (da elaborare al prossimo giro) o alla fine se l'uscita è libera. */
function newsletter_flusso_vai(array $g, int $corso, int $da, string $uscita, int $salti, int $f = 0, int $iscritto = 0): void {
    $a = newsletter_flusso_arrivo($g, $da, $uscita);
    if ($f) nl_conta($f, $da, $uscita);
    if ($a === null || $salti > NL_AUT_MAX_SALTI) {
        if ($f) nl_corso_chiudi($corso, 'finito', $a === null ? '' : 'troppi passi: fermato', $f, $iscritto, $da);
        else db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = 'finito', prossimo = NULL, ascolto = NULL, aggiornato = NOW() WHERE id = ?")->execute([$corso]);
        return;
    }
    db()->prepare('UPDATE ' . tbl('newsletter_corsi') . ' SET passo = ?, prossimo = NOW(), ascolto = NULL, salti = ?, aggiornato = NOW() WHERE id = ?')->execute([$a, $salti + 1, $corso]);
    if ($f) nl_conta($f, $a, 'passati');
}

/**
 * Un'apertura o un clic su un'email di un'automazione: chi è in ascolto su quell'email parte dall'uscita giusta; chi è
 * in "Attendi un evento" su quell'email (o su qualsiasi email dell'automazione) parte dal Sì.
 */
function newsletter_flusso_evento(int $flusso, int $nodo, int $iscritto, string $tipo): void {
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_corsi') . " WHERE flusso_id = ? AND iscritto_id = ? AND stato = 'attivo' AND ((passo = ? AND ascolto = 'email') OR ascolto = 'evento')");
    $st->execute([$flusso, $iscritto, $nodo]);
    $righe = $st->fetchAll();
    if (!$righe) return;
    $f = newsletter_flusso($flusso);
    if (!$f || $f['stato'] === 'inattiva') return;
    $g = $f['grafo'];
    foreach ($righe as $c) {
        if ($c['ascolto'] === 'evento') {
            $n = newsletter_flusso_nodo($g, (int)$c['passo']);
            if ($n && $n['tipo'] === 'evento' && ($n['dati']['evento'] === $tipo || $tipo === 'clic') && in_array($n['dati']['email'], [0, $nodo], true)) {
                nl_registro($flusso, (int)$c['id'], $iscritto, (int)$c['passo'], 'evento', ($tipo === 'clic' ? 'ha cliccato' : 'ha aperto') . " l'email #$nodo");
                newsletter_flusso_vai($g, (int)$c['id'], (int)$c['passo'], 'si', (int)$c['salti'], $flusso, $iscritto);
            }
            continue;
        }
        $uscita = null;
        // Un clic segue "se clicca"; se quell'uscita è libera vale come apertura. Un'apertura con solo "se clicca" collegato resta in ascolto.
        if ($tipo === 'clic' && newsletter_flusso_arrivo($g, $nodo, 'cliccata') !== null) $uscita = 'cliccata';
        elseif (newsletter_flusso_arrivo($g, $nodo, 'aperta') !== null) $uscita = 'aperta';
        if ($uscita === null) continue;
        newsletter_flusso_vai($g, (int)$c['id'], $nodo, $uscita, (int)$c['salti'], $flusso, $iscritto);
    }
}

/**
 * 2.18.0: il modello che un passo Email richiama (editor 'modello', id in 'modello'), letto adesso dal database, e il motivo
 * per cui non si può mandare ('' se si può): il modello non c'è, è quello dell'email di conferma (tag optin), non ha oggetto
 * (né il passo ne ha uno), non ha contenuto. Un modello inattivo si manda lo stesso: "inattivo" vale per le scelte nuove.
 */
function nl_passo_modello(array $d): array {
    $id = (int)($d['modello'] ?? 0);
    if (!function_exists('nd_modello')) @include_once __DIR__ . '/newsletter-design.php';
    $m = $id > 0 && function_exists('nd_modello') ? nd_modello($id) : null;
    if (!$m) return [null, "modello #$id non trovato: email non mandata"];
    if ($m['tag'] === 'optin') return [null, "modello #$id è l'email di conferma dell'iscrizione: email non mandata"];
    if (trim((string)($d['oggetto'] ?? '')) === '' && trim(newsletter_4byte_fuori((string)$m['oggetto'])) === '') return [null, "modello #$id senza oggetto: email non mandata"];
    if ($m['editor'] === 'html' ? trim((string)$m['html']) === '' : !(function_exists('nd_design') && nd_design((string)$m['design']))) return [null, "modello #$id senza contenuto: email non mandata"];
    return [$m, ''];
}

/**
 * 2.18.0: dove sono richiamati i modelli, per Modelli ("Usato in"), le conferme e la dashboard.
 * id del modello => [['flusso' => id, 'nome' => nome dell'automazione, 'stato' => stato, 'nodo' => id del passo], ...].
 */
function nl_modelli_usati(): array {
    newsletter_flussi_schema();
    $out = [];
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_flussi') . ' ORDER BY nome, id')->fetchAll() as $r) {
        if (!str_contains((string)$r['passi'], '"modello"')) continue;
        $f = nl_flusso_riga($r);
        foreach ($f['grafo']['nodi'] as $n) {
            if ($n['tipo'] === 'email' && $n['dati']['editor'] === 'modello' && $n['dati']['modello'] > 0) $out[$n['dati']['modello']][] = ['flusso' => (int)$f['id'], 'nome' => $f['nome'], 'stato' => $f['stato'], 'nodo' => $n['id']];
        }
    }
    return $out;
}

/**
 * 2.18.0: per la dashboard, i passi che richiamano un modello che non si può mandare (non c'è più, o è diventato l'email di
 * conferma) nelle automazioni non inattive, con quante persone sono ferme lì. [['flusso', 'nome', 'stato', 'nodo', 'modello', 'fermi'], ...].
 */
function nl_passi_senza_modello(): array {
    $usati = nl_modelli_usati();
    if (!$usati) return [];
    if (!function_exists('nd_schema')) @include_once __DIR__ . '/newsletter-design.php';
    if (!function_exists('nd_schema')) return [];
    nd_schema();
    $buoni = array_map('intval', db()->query('SELECT id FROM ' . tbl('newsletter_modelli') . " WHERE tag <> 'optin'")->fetchAll(PDO::FETCH_COLUMN));
    $fermi = [];
    foreach (db()->query('SELECT flusso_id, passo, COUNT(*) AS n FROM ' . tbl('newsletter_corsi') . " WHERE stato = 'attivo' AND ascolto = 'modello' GROUP BY flusso_id, passo")->fetchAll() as $x) $fermi[$x['flusso_id'] . '|' . $x['passo']] = (int)$x['n'];
    $out = [];
    foreach ($usati as $mid => $dove) {
        if (in_array((int)$mid, $buoni, true)) continue;
        foreach ($dove as $x) if ($x['stato'] !== 'inattiva') $out[] = $x + ['modello' => (int)$mid, 'fermi' => $fermi[$x['flusso'] . '|' . $x['nodo']] ?? 0];
    }
    return $out;
}

/** L'email di un passo, come campagna per newsletter_email(). Un'email di prima (editor semplice) esce identica al byte. */
function nl_email_campagna(array $f, array $d): array {
    $c = ['id' => 0, 'oggetto' => $d['oggetto'], 'anteprima' => $d['anteprima'], 'corpo' => $d['corpo'], 'pulsante_testo' => $d['pulsante_testo'], 'pulsante_link' => $d['pulsante_link'], 'mittente_id' => (int)(($d['mittente_id'] ?? 0) ?: ($f['mittente_id'] ?? 0))];
    if (($d['editor'] ?? '') === 'modello') {
        // 2.18.0: il modello com'è adesso (design a blocchi o codice HTML); oggetto e anteprima del passo vincono, vuoti valgono
        // quelli del modello. Senza modello usabile il giro non arriva qui (nl_corso_scorri() ferma la persona sul passo).
        [$m] = nl_passo_modello($d);
        if (!$m) return $c;
        if (trim((string)$c['oggetto']) === '') $c['oggetto'] = newsletter_4byte_fuori((string)$m['oggetto']);
        if (trim((string)$c['anteprima']) === '') $c['anteprima'] = newsletter_4byte_fuori((string)($m['anteprima'] ?? ''));
        if ($m['editor'] === 'html') {
            if (!function_exists('nl_camp_html')) @include_once __DIR__ . '/newsletter-campagne.php';
            $c += ['editor' => 'html', 'html' => (string)$m['html']];
        } else {
            $c += ['editor' => 'blocchi', 'design' => (string)$m['design']];
        }
        return $c;
    }
    if (($d['editor'] ?? '') === 'blocchi') $c += ['editor' => 'blocchi', 'design' => (string)$d['design']];
    elseif (($d['editor'] ?? '') === 'html' && trim((string)($d['html'] ?? '')) !== '') {
        // Il codice HTML lo compone nl_camp_html() delle campagne: nel giro del cron quel file può non essere caricato, e senza
        // newsletter_email() ripiegherebbe in silenzio sul testo semplice (trovato dalla prova della 2.17.1).
        if (!function_exists('nl_camp_html')) @include_once __DIR__ . '/newsletter-campagne.php';
        $c += ['editor' => 'html', 'html' => (string)$d['html']];
    }
    return $c;
}

/** Quando parte un'email con l'orario: il prossimo "alle HH" (oggi o domani); all'orario migliore, l'ora del contatto. */
function nl_email_quando(array $d, int $iscritto): int {
    $h = (int)$d['ora'];
    if ($d['orario'] === 'migliore') {
        if (!function_exists('nl_orario_ore')) @include_once __DIR__ . '/newsletter-report.php';
        if (function_exists('nl_orario_ore')) $h = nl_orario_ore([$iscritto])[$iscritto] ?? $h;
    }
    $t = strtotime(date('Y-m-d') . sprintf(' %02d:00:00', $h));
    // 2.18.0: se il turno arriva dentro l'ora scelta (per esempio alle 8:03, dopo un'attesa di 7 giorni partita da un'email delle
    // 8:03) l'email parte adesso. Prima slittava al giorno dopo, e "attesa 7 giorni, alle 8" mandava un'email ogni 8 giorni.
    if ($t <= time() && time() < $t + 3600) return time();
    return $t > time() ? $t : $t + 86400;
}

/**
 * Un giro delle automazioni: elabora i cammini arrivati alla loro ora, manda al massimo $posti email.
 * Un cammino scorre finché incontra un'attesa, un'email in ascolto, un evento da aspettare o la fine. Ritorna le righe di log.
 */
function newsletter_flussi_giro(int $posti): array {
    newsletter_flussi_schema();
    $log = [];
    if ($posti <= 0) return $log;
    nl_flussi_attivi(true);
    newsletter_flussi_inattivi();
    if (!function_exists('nl_aut_giro_prima')) @include_once __DIR__ . '/newsletter-automazioni.php';
    if (function_exists('nl_aut_giro_prima')) $log = nl_aut_giro_prima(); // anniversari e condizioni di uscita
    $righe = db()->query('SELECT c.*, i.email, i.nome, i.cognome, i.stato AS stato_iscritto FROM ' . tbl('newsletter_corsi') . ' c JOIN ' . tbl('newsletter_iscritti') . " i ON i.id = c.iscritto_id WHERE c.stato = 'attivo' AND c.prossimo <= NOW() ORDER BY c.prossimo, c.id LIMIT " . (int)($posti * 3 + 50))->fetchAll();
    $flussi = [];
    $conto = ['inviate' => 0, 'posti' => $posti, 'webhook' => 0];
    foreach ($righe as $r) {
        if ($conto['inviate'] >= $posti) break;
        $f = $flussi[$r['flusso_id']] ??= newsletter_flusso((int)$r['flusso_id']);
        $corso = (int)$r['id']; $iscritto = (int)$r['iscritto_id'];
        if (!$f) { db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = 'fermato', prossimo = NULL, ascolto = NULL, aggiornato = NOW() WHERE id = ?")->execute([$corso]); continue; }
        $fid = (int)$f['id'];
        if ($f['stato'] === 'inattiva') { nl_corso_chiudi($corso, 'tolto', 'automazione inattiva', $fid, $iscritto, (int)$r['passo']); continue; }
        $soloAzioni = false;
        if ($r['stato_iscritto'] !== 'attivo') {
            // Chi non riceve più email: esce se l'automazione lo chiede; resta (senza email) se è entrato perché si è disiscritto; sennò si ferma, come prima.
            if ($f['uscita']['disiscritto']) { nl_corso_chiudi($corso, 'tolto', 'esce: non è più iscritto', $fid, $iscritto, (int)$r['passo']); continue; }
            if (!in_array('disiscritto', array_column($f['inneschi'], 'tipo'), true)) { nl_corso_chiudi($corso, 'fermato', 'non è più iscritto', $fid, $iscritto, (int)$r['passo']); continue; }
            $soloAzioni = true;
        }
        nl_corso_scorri($f, $r, $conto, $log, $soloAzioni);
    }
    if ($conto['inviate']) $log[] = 'automazioni: ' . $conto['inviate'] . ' email mandate';
    return $log;
}

/** Fa scorrere un cammino finché si ferma. $conto: email mandate e posti del giro, webhook chiamati. */
function nl_corso_scorri(array $f, array $r, array &$conto, array &$log, bool $soloAzioni): void {
    $g = $f['grafo']; $fid = (int)$f['id'];
    $corso = (int)$r['id']; $iscritto = (int)$r['iscritto_id'];
    $nodoId = (int)$r['passo']; $salti = (int)$r['salti'];
    $aggiorna = db()->prepare('UPDATE ' . tbl('newsletter_corsi') . ' SET passo = ?, prossimo = ?, ascolto = ?, salti = ?, aggiornato = NOW() WHERE id = ?');
    $ferma = function (int $quando, ?string $ascolto) use (&$nodoId, &$salti, $aggiorna, $corso) { $aggiorna->execute([$nodoId, date('Y-m-d H:i:s', $quando), $ascolto, $salti, $corso]); };
    // Esce da un'uscita: al nodo dopo (true) o alla fine (false).
    $vai = function (string $u) use (&$nodoId, &$salti, $g, $fid, $corso, $iscritto, $aggiorna): bool {
        $a = newsletter_flusso_arrivo($g, $nodoId, $u);
        nl_conta($fid, $nodoId, $u);
        if ($a === null || $salti >= NL_AUT_MAX_SALTI) { nl_corso_chiudi($corso, 'finito', $a === null ? '' : 'troppi passi: fermato', $fid, $iscritto, $nodoId); return false; }
        $nodoId = $a; $salti++;
        nl_conta($fid, $a, 'passati');
        $aggiorna->execute([$nodoId, date('Y-m-d H:i:s'), null, $salti, $corso]);
        return true;
    };
    $inviaOra = false;
    // Il tempo di un ascolto è passato.
    if ($r['ascolto'] === 'email' && !$vai(newsletter_flusso_arrivo($g, $nodoId, 'nessuna') !== null ? 'nessuna' : 'poi')) return;
    if ($r['ascolto'] === 'evento') { nl_registro($fid, $corso, $iscritto, $nodoId, 'evento', 'il tempo massimo è passato'); if (!$vai('no')) return; }
    if ($r['ascolto'] === 'attesa' && !$vai('poi')) return;
    if ($r['ascolto'] === 'orario') $inviaOra = true;
    for ($k = 0; $k < NL_AUT_PASSI_GIRO; $k++) {
        $nodo = newsletter_flusso_nodo($g, $nodoId);
        if (!$nodo || $nodo['tipo'] === 'fine' || $nodo['tipo'] === 'innesco') { nl_corso_chiudi($corso, 'finito', '', $fid, $iscritto, $nodoId); return; }
        $d = $nodo['dati'];
        switch ($nodo['tipo']) {
            case 'attesa':
                nl_registro($fid, $corso, $iscritto, $nodoId, 'attesa', 'aspetta fino al ' . date('d/m/Y H:i', time() + $d['minuti'] * 60));
                $ferma(time() + $d['minuti'] * 60, 'attesa');
                return;
            case 'evento':
                // Già successo da quando è entrato? Allora non serve aspettare.
                $q = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_eventi') . ' WHERE flusso_id = ? AND iscritto_id = ? AND tipo = ? AND creato >= ?' . ($d['email'] ? ' AND passo = ?' : ''));
                $q->execute(array_merge([$fid, $iscritto, $d['evento'], (string)$r['creato']], $d['email'] ? [$d['email']] : []));
                if ((int)$q->fetchColumn() > 0) { nl_registro($fid, $corso, $iscritto, $nodoId, 'evento', 'era già successo'); if (!$vai('si')) return; continue 2; }
                $ferma(time() + $d['massimo'] * 60, 'evento');
                return;
            case 'bivio':
                $u = newsletter_flusso_condizione($f, $nodo, $iscritto) ? 'si' : 'no';
                nl_registro($fid, $corso, $iscritto, $nodoId, 'bivio', $u === 'si' ? 'Sì' : 'No');
                if (!$vai($u)) return;
                continue 2;
            case 'dividi': case 'percento':
                $u = $nodo['tipo'] === 'dividi' ? nl_dividi_ramo($fid, $nodo, $iscritto) : nl_percento_ramo($fid, $nodo);
                nl_registro($fid, $corso, $iscritto, $nodoId, 'bivio', nl_uscite($nodo)[$u] ?? $u);
                if (!$vai($u)) return;
                continue 2;
            case 'lista_aggiungi': case 'lista_togli': case 'attributo': case 'webhook':
                if ($nodo['tipo'] === 'webhook' && $conto['webhook'] >= 20) { $ferma(time(), null); return; } // al prossimo giro: un giro resta breve
                if (!function_exists('nl_aut_azione')) require_once __DIR__ . '/newsletter-automazioni.php';
                if ($nodo['tipo'] === 'webhook') $conto['webhook']++;
                nl_registro($fid, $corso, $iscritto, $nodoId, 'azione', nl_aut_azione($f, $nodo, $r));
                if (!$vai('poi')) return;
                continue 2;
            case 'tag_aggiungi': case 'tag_togli':
                // 2.23.0: i tag (includes/newsletter-tag.php, file nuovo: niente copie vecchie in opcache).
                if (!function_exists('newsletter_tag_aggiungi')) require_once __DIR__ . '/newsletter-tag.php';
                $nomiT = array_intersect_key(newsletter_tag_nomi(), array_flip($d['tag']));
                $origine = 'automazione «' . $f['nome'] . '»';
                $n = $nomiT ? ($nodo['tipo'] === 'tag_aggiungi' ? newsletter_tag_aggiungi($iscritto, array_keys($nomiT), $origine) : newsletter_tag_togli($iscritto, array_keys($nomiT), $origine)) : 0;
                nl_registro($fid, $corso, $iscritto, $nodoId, 'azione', !$nomiT ? 'i tag non ci sono più: niente da fare' : (($nodo['tipo'] === 'tag_aggiungi' ? ($n ? 'tag aggiunti: ' : 'aveva già i tag: ') : ($n ? 'tag tolti: ' : 'non aveva i tag: ')) . implode(', ', $nomiT)));
                if (!$vai('poi')) return;
                continue 2;
        }
        // Email.
        if ($soloAzioni) { nl_registro($fid, $corso, $iscritto, $nodoId, 'email', 'non mandata: non è iscritto'); if (!$vai('poi')) return; continue; }
        if (($d['editor'] ?? '') === 'modello') {
            // 2.18.0: il modello richiamato non si può mandare (eliminato, diventato l'email di conferma, senza oggetto): niente
            // email vuota. La persona resta su questo passo e riprova ogni ora (prima dell'orario: un passo "alle 8" che aspetta il modello
            // resta segnato come fermo; quando il modello torna, aspetta le 8 e parte); il registro lo dice una volta, la dashboard lo segnala.
            [, $perche] = nl_passo_modello($d);
            if ($perche !== '') {
                $gia = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_flussi_registro') . ' WHERE corso_id = ? AND nodo = ? AND dettaglio = ?');
                $gia->execute([$corso, $nodoId, $perche]);
                if (!(int)$gia->fetchColumn()) { nl_registro($fid, $corso, $iscritto, $nodoId, 'email', $perche); $log[] = 'automazione ' . $f['nome'] . ", passo #$nodoId: $perche"; }
                $ferma(time() + 3600, 'modello');
                return;
            }
        }
        if ($d['orario'] !== 'subito' && !$inviaOra) { $ferma(nl_email_quando($d, $iscritto), 'orario'); return; }
        if ($conto['inviate'] >= $conto['posti']) { $ferma(time(), $inviaOra ? 'orario' : null); return; } // resta dov'è, al prossimo giro
        $inviaOra = false;
        [$oggetto, $html, $intest] = newsletter_email(nl_email_campagna($f, $d), ['id' => $iscritto, 'nome' => $r['nome'], 'email' => $r['email']], ['f' => $fid, 'p' => $nodoId]);
        $partita = posta_invia($r['email'], $oggetto, $html, $intest, 'flusso');
        newsletter_esito_invio($iscritto);
        if (!$partita && posta_esito() === 'rimbalzo') {
            // Indirizzo rifiutato per sempre: l'iscritto è rimbalzato, il suo cammino si ferma, gli altri vanno avanti.
            nl_corso_chiudi($corso, 'fermato', 'indirizzo rifiutato dal server di posta', $fid, $iscritto, $nodoId);
            newsletter_evento_interno('rimbalzo', 0, $fid, $nodoId, $iscritto);
            $log[] = 'automazione ' . $f['nome'] . ': indirizzo rifiutato, iscritto segnato rimbalzato (' . posta_errore() . ')';
            return;
        }
        if (!$partita) { $log[] = 'automazione ' . $f['nome'] . ': email non partita verso ' . $r['email'] . ' (' . posta_errore() . ')'; $ferma(time(), null); return; }
        $conto['inviate']++;
        db()->prepare('UPDATE ' . tbl('newsletter_flussi') . ' SET inviate = inviate + 1 WHERE id = ?')->execute([$fid]);
        nl_conta($fid, $nodoId, 'inviate');
        nl_registro($fid, $corso, $iscritto, $nodoId, 'email', newsletter_4byte_fuori((string)$oggetto));
        // 2.8.0: l'email del flusso nella Cronologia del contatto (le campagne stanno già nella coda).
        if (function_exists('newsletter_storia_scrivi')) newsletter_storia_scrivi($iscritto, 'flusso_email', $f['nome'] . ': ' . newsletter_4byte_fuori((string)($d['oggetto'] !== '' ? $d['oggetto'] : $oggetto)), $fid);
        $ascolta = $d['ascolto'] > 0 && (newsletter_flusso_arrivo($g, $nodoId, 'aperta') !== null || newsletter_flusso_arrivo($g, $nodoId, 'cliccata') !== null || newsletter_flusso_arrivo($g, $nodoId, 'nessuna') !== null);
        if ($ascolta) { $ferma(time() + $d['ascolto'] * 60, 'email'); return; }
        if (!$vai('poi')) return;
    }
}

/** Il primo ramo di una divisione condizionale che vale per il contatto (r0, r1...), o "altro". */
function nl_dividi_ramo(int $f, array $nodo, int $iscritto): string {
    foreach ($nodo['dati']['rami'] as $i => $r) {
        $ok = newsletter_regole_valida($r['regole'], ['flusso' => $f]);
        if (isset($ok['regole']) && $ok['regole']['gruppi'] && newsletter_regole_vale($ok['regole'], $iscritto, ['flusso' => $f])) return "r$i";
    }
    return 'altro';
}

/** Il ramo di una divisione in percentuale: quello più indietro rispetto alla sua parte (le proporzioni tornano esatte). */
function nl_percento_ramo(int $f, array $nodo): string {
    $st = db()->prepare('SELECT chiave, n FROM ' . tbl('newsletter_flussi_numeri') . ' WHERE flusso_id = ? AND nodo = ?');
    $st->execute([$f, $nodo['id']]);
    $n = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $tot = 0;
    foreach ($nodo['dati']['rami'] as $i => $p) $tot += (int)($n["p$i"] ?? 0);
    $meglio = 'p0'; $max = -INF;
    foreach ($nodo['dati']['rami'] as $i => $p) {
        $manca = $p * ($tot + 1) / 100 - (int)($n["p$i"] ?? 0);
        if ($manca > $max + 1e-9) { $max = $manca; $meglio = "p$i"; }
    }
    return $meglio;
}
