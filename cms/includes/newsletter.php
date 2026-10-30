<?php
/**
 * La newsletter del sito, senza servizi esterni.
 *
 * Iscritti: modulo con doppia conferma dell'email (stesso captcha dei moduli), oppure importazione dei contatti
 * esportati da un altro servizio (per esempio Brevo).
 * Campagne: scritte nell'editor del pannello, inviate da una coda a ritmo controllato da cron-newsletter.php
 * (i server condivisi limitano le email per ora). Ogni email ha l'uscita in un clic, anche dall'intestazione
 * List-Unsubscribe che leggono Gmail e Outlook.
 *
 * 1.11.0:
 *   - ogni richiesta di conferma è una riga di <prefisso>newsletter_conferme (token, lista, form d'origine): la persona
 *     entra nella lista (e partono i flussi) solo alla conferma, e un form con doppia conferma non la mette più nella
 *     lista principale. newsletter_conferma_esito() dice chi ha confermato e da quale form (aggancio per i moduli);
 *   - <prefisso>newsletter_inneschi_attesa: gli inneschi "Compila un form" di chi deve ancora confermare, fatti partire
 *     alla conferma;
 *   - <prefisso>newsletter_soppressi: l'impronta (sha256) delle email cancellate con "Cancella dati": l'importazione
 *     le salta;
 *   - stato 'rimbalzato' (il server di posta ha rifiutato l'indirizzo con un 5xx) e conteggio degli errori temporanei
 *     di fila (errori_di_fila, segnalato da 3 in su);
 *   - il limite orario e quello giornaliero contano tutte le email del CMS (posta_registro in includes/posta.php).
 */

require_once __DIR__ . '/moduli.php';

define('NEWSLETTER_PAGINA', (string)cms_impostazione('newsletter_pagina', '/newsletter/'));
const NEWSLETTER_GRAZIE = '/newsletter-grazie/';

/** Il nome della newsletter ("Newsletter" se non è impostato): il nome della newsletter del sito (lista principale, email). */
function newsletter_nome(): string {
    $n = trim((string)cms_impostazione('newsletter_nome', ''));
    return $n !== '' ? "Newsletter $n" : 'Newsletter';
}
const NEWSLETTER_CONFERMA = '/newsletter-conferma/';
const NEWSLETTER_ESCI = '/newsletter-esci/';
const NEWSLETTER_PER_GIRO = 40;   // email al massimo per ogni passaggio del cron

function newsletter_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $tabelle = [
        'newsletter_iscritti' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_iscritti') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(190) NOT NULL,
            `nome` VARCHAR(100) NOT NULL DEFAULT '',
            `cognome` VARCHAR(100) NOT NULL DEFAULT '',
            `stato` VARCHAR(20) NOT NULL DEFAULT 'in_attesa',
            `fonte` VARCHAR(20) NOT NULL DEFAULT 'modulo',
            `consenso_versione` VARCHAR(20) NOT NULL DEFAULT '',
            `consenso_at` DATETIME DEFAULT NULL,
            `token_conferma` CHAR(64) DEFAULT NULL,
            `conferma_scade` DATETIME DEFAULT NULL,
            `uscito_at` DATETIME DEFAULT NULL,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `errori_di_fila` INT(11) NOT NULL DEFAULT 0,
            `ultimo_errore` VARCHAR(255) NOT NULL DEFAULT '',
            `rimbalzato_at` DATETIME DEFAULT NULL,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `email` (`email`), KEY `stato` (`stato`), KEY `token_conferma` (`token_conferma`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_campagne' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_campagne') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `oggetto` VARCHAR(255) NOT NULL DEFAULT '',
            `anteprima` VARCHAR(255) NOT NULL DEFAULT '',
            `corpo` MEDIUMTEXT,
            `pulsante_testo` VARCHAR(100) NOT NULL DEFAULT '',
            `pulsante_link` VARCHAR(500) NOT NULL DEFAULT '',
            `stato` VARCHAR(20) NOT NULL DEFAULT 'bozza',
            `invio_at` DATETIME DEFAULT NULL,
            `destinatari` INT(11) NOT NULL DEFAULT 0,
            `inviate` INT(11) NOT NULL DEFAULT 0,
            `errori` INT(11) NOT NULL DEFAULT 0,
            `liste` VARCHAR(255) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `stato` (`stato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_liste' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_liste') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `descrizione` VARCHAR(255) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `nome` (`nome`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_iscritti_liste' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_iscritti_liste') . " (
            `iscritto_id` INT(11) NOT NULL,
            `lista_id` INT(11) NOT NULL,
            `aggiunto` DATETIME NOT NULL,
            PRIMARY KEY (`iscritto_id`, `lista_id`), KEY `lista` (`lista_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_coda' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_coda') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `campagna_id` INT(11) NOT NULL,
            `iscritto_id` INT(11) NOT NULL,
            `stato` VARCHAR(10) NOT NULL DEFAULT 'attesa',
            `tentativi` TINYINT NOT NULL DEFAULT 0,
            `errore` VARCHAR(255) NOT NULL DEFAULT '',
            `inviata_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `campagna_iscritto` (`campagna_id`, `iscritto_id`), KEY `attesa` (`stato`, `campagna_id`), KEY `inviata_at` (`inviata_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // 1.11.0: una riga per ogni email di conferma mandata. La persona entra nella lista solo quando conferma.
        'newsletter_conferme' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_conferme') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `token` CHAR(64) NOT NULL,
            `iscritto_id` INT(11) NOT NULL,
            `lista_id` INT(11) NOT NULL DEFAULT 0,
            `form_id` INT(11) NOT NULL DEFAULT 0,
            `scade` DATETIME NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `token` (`token`), KEY `iscritto` (`iscritto_id`), KEY `scade` (`scade`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // 1.11.0: gli inneschi dei flussi arrivati mentre la persona non aveva ancora confermato.
        'newsletter_inneschi_attesa' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_inneschi_attesa') . " (
            `iscritto_id` INT(11) NOT NULL,
            `innesco` VARCHAR(20) NOT NULL,
            `innesco_id` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`iscritto_id`, `innesco`, `innesco_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // 1.11.0: chi ha chiesto la cancellazione dei dati, come impronta sha256 dell'email in minuscolo.
        'newsletter_soppressi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_soppressi') . " (
            `impronta` CHAR(64) NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`impronta`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME);
    if (get_setting('newsletter_schema_impronta', '') !== $impronta) {
        foreach ($tabelle as $sql) db()->exec($sql);
        // Colonna arrivata dopo la prima versione (23 ottobre, sera): le liste della campagna.
        $c = db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . DB_PREFIX . "newsletter_campagne' AND COLUMN_NAME = 'liste'")->fetchColumn();
        if (!$c) db()->exec('ALTER TABLE ' . tbl('newsletter_campagne') . " ADD COLUMN `liste` VARCHAR(255) NOT NULL DEFAULT '' AFTER `errori`");
        // 1.11.0: rimbalzi ed errori temporanei di fila. Le tabelle nate prima non hanno le colonne.
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('newsletter_iscritti'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('errori_di_fila', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_iscritti') . ' ADD COLUMN `errori_di_fila` INT(11) NOT NULL DEFAULT 0 AFTER `ip`');
        if (!in_array('ultimo_errore', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_iscritti') . " ADD COLUMN `ultimo_errore` VARCHAR(255) NOT NULL DEFAULT '' AFTER `errori_di_fila`");
        if (!in_array('rimbalzato_at', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_iscritti') . ' ADD COLUMN `rimbalzato_at` DATETIME DEFAULT NULL AFTER `ultimo_errore`');
        $fatto = true;
        // La lista di partenza. Chi era iscritto prima delle liste entra nella principale, una volta sola: dalla 1.11.0
        // chi aspetta la conferma di un form non sta in nessuna lista, e non deve finire nella principale.
        $primaVolta = !(int)get_setting('newsletter_lista_principale', 0);
        $principale = newsletter_lista_di_sistema('principale', newsletter_nome(), 'Chi si iscrive dal sito, e gli iscritti di prima delle liste');
        if ($primaVolta) {
            db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) SELECT i.id, ?, NOW() FROM ' . tbl('newsletter_iscritti') . ' i
                WHERE NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = i.id)')->execute([$principale]);
        }
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET liste = ? WHERE liste = ''")->execute([(string)$principale]);
        save_setting('newsletter_schema_impronta', $impronta);
    }
    $fatto = true;
}

// ------------------------------------------------------------
// Liste
// ------------------------------------------------------------

/** Una lista di sistema (la principale, o quella di un modulo): l'id sta nelle impostazioni, il nome si può cambiare dal pannello. */
function newsletter_lista_di_sistema(string $chiave, string $nome, string $descrizione): int {
    $id = (int)get_setting('newsletter_lista_' . $chiave, 0);
    if ($id && db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_liste') . ' WHERE id = ' . $id)->fetchColumn()) return $id;
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_liste') . ' WHERE nome = ?');
    $st->execute([$nome]);
    $id = (int)$st->fetchColumn();
    if (!$id) {
        db()->prepare('INSERT INTO ' . tbl('newsletter_liste') . ' (nome, descrizione, creato) VALUES (?, ?, NOW())')->execute([$nome, $descrizione]);
        $id = (int)db()->lastInsertId();
    }
    save_setting('newsletter_lista_' . $chiave, (string)$id);
    return $id;
}

function newsletter_lista_principale(): int {
    newsletter_schema();
    return newsletter_lista_di_sistema('principale', newsletter_nome(), '');
}

/** Tutte le liste, con quanti iscritti attivi e quanti in tutto. */
function newsletter_liste(): array {
    newsletter_schema();
    return db()->query('SELECT l.*, COUNT(il.iscritto_id) AS totali, COALESCE(SUM(i.stato = \'attivo\'), 0) AS attivi
        FROM ' . tbl('newsletter_liste') . ' l LEFT JOIN ' . tbl('newsletter_iscritti_liste') . ' il ON il.lista_id = l.id
        LEFT JOIN ' . tbl('newsletter_iscritti') . ' i ON i.id = il.iscritto_id GROUP BY l.id ORDER BY l.id')->fetchAll();
}

/** Mette un iscritto in una lista. $flussi = false: i flussi della lista non partono (l'importazione, di partenza). */
function newsletter_in_lista(int $iscritto, int $lista, bool $flussi = true): void {
    if (!$iscritto || !$lista) return;
    $st = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) VALUES (?, ?, NOW())');
    $st->execute([$iscritto, $lista]);
    // Entrato adesso in una lista, e già attivo: i flussi di quella lista partono.
    if ($st->rowCount() === 1 && $flussi) {
        $a = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
        $a->execute([$iscritto]);
        if ($a->fetchColumn() === 'attivo') newsletter_flussi_innesco('lista', $lista, $iscritto);
    }
}

function newsletter_id_da_email(string $email): int {
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_iscritti') . ' WHERE email = ?');
    $st->execute([$email]);
    return (int)$st->fetchColumn();
}

/** Gli id delle liste scritti in una campagna ("3,5"), solo quelli che esistono ancora. */
function newsletter_liste_campagna(array $c): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($c['liste'] ?? ''))))));
    if (!$ids) return [];
    return array_map('intval', db()->query('SELECT id FROM ' . tbl('newsletter_liste') . ' WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_COLUMN));
}

/** Quante persone riceverebbero una campagna sulle liste date: attivi, contati una volta sola. */
function newsletter_destinatari(array $liste): int {
    $liste = array_filter(array_map('intval', $liste));
    if (!$liste) return 0;
    return (int)db()->query('SELECT COUNT(DISTINCT i.id) FROM ' . tbl('newsletter_iscritti') . ' i JOIN ' . tbl('newsletter_iscritti_liste') . " l ON l.iscritto_id = i.id
        WHERE i.stato = 'attivo' AND l.lista_id IN (" . implode(',', $liste) . ')')->fetchColumn();
}

/** Il nome di una lista ('' se non esiste). */
function newsletter_nome_lista(int $id): string {
    $st = db()->prepare('SELECT nome FROM ' . tbl('newsletter_liste') . ' WHERE id = ?');
    $st->execute([$id]);
    return (string)$st->fetchColumn();
}

/** per_ora: email al massimo in un'ora; per_giorno: nelle ultime 24 ore (0 = nessun limite). Contano tutte le email del CMS. */
function newsletter_config(): array {
    $c = json_decode((string)get_setting('newsletter_config', ''), true);
    return (is_array($c) ? $c : []) + ['per_ora' => 150, 'per_giorno' => 0, 'rispondi_a' => ''];
}

/** L'impronta di un'email per la lista di soppressione: sha256 dell'indirizzo in minuscolo, senza spazi. */
function newsletter_impronta(string $email): string {
    return hash('sha256', mb_strtolower(trim($email)));
}

function newsletter_soppresso(string $email): bool {
    newsletter_schema();
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_soppressi') . ' WHERE impronta = ?');
    $st->execute([newsletter_impronta($email)]);
    return (int)$st->fetchColumn() > 0;
}

/** Lo stato di un'email nell'elenco ('' se non c'è). */
function newsletter_stato_email(string $email): string {
    newsletter_schema();
    $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE email = ?');
    $st->execute([mb_strtolower(trim($email))]);
    return (string)$st->fetchColumn();
}

/** Troppe richieste di iscrizione da questo indirizzo IP nell'ultima ora (lo stesso limite dei moduli). */
function newsletter_troppe_richieste(): bool {
    newsletter_schema();
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . ' WHERE ip = ? AND aggiornato > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $st->execute([moduli_ip()]);
    return (int)$st->fetchColumn() >= MODULI_LIMITE_ORA;
}

// ------------------------------------------------------------
// Iscritti
// ------------------------------------------------------------

/**
 * Una richiesta di iscrizione con doppia conferma: salva la persona (nuova: in attesa; chi c'è già resta nel suo stato),
 * scrive la richiesta in newsletter_conferme e manda l'email con il link. Ritorna '' oppure il codice d'errore
 * ('troppi', 'posta').
 * Opzioni (1.11.0), tutte facoltative:
 *   lista     la lista in cui entra alla conferma (predefinita: la principale, il modulo della newsletter)
 *   form      l'id del form d'origine (lo ritrova newsletter_conferma_esito())
 *   cosa      per cosa si iscrive, nelle frasi dell'email ("la newsletter di...", il titolo del form)
 *   oggetto, testo (HTML), pulsante: per riscrivere l'email
 *   fonte     la colonna fonte di un iscritto nuovo ('modulo', 'form')
 */
function newsletter_richiesta(array $d, array $opz = []): string {
    newsletter_schema();
    if (newsletter_troppe_richieste()) return 'troppi';
    $principale = newsletter_lista_principale();
    $lista = (int)($opz['lista'] ?? 0) ?: $principale;
    $form = max(0, (int)($opz['form'] ?? 0));
    $fonte = preg_match('/^[a-z_]{1,20}$/', (string)($opz['fonte'] ?? '')) ? (string)$opz['fonte'] : ($form ? 'form' : 'modulo');
    $email = mb_strtolower(trim((string)$d['email']));
    $token = bin2hex(random_bytes(32));
    $ora = moduli_adesso();
    $scade = date('Y-m-d H:i:s', time() + MODULI_CONFERMA_GIORNI * 86400);
    // Chi c'è già resta nel suo stato (attivo, uscito, rimbalzato): cambia solo alla conferma. Il nome si aggiorna se arriva.
    db()->prepare('INSERT INTO ' . tbl('newsletter_iscritti') . " (email, nome, cognome, stato, fonte, consenso_versione, conferma_scade, ip, creato, aggiornato)
        VALUES (:e, :n, :c, 'in_attesa', :f, :v, :s, :ip, :o, :o2)
        ON DUPLICATE KEY UPDATE nome = IF(VALUES(nome) <> '', VALUES(nome), nome), cognome = IF(VALUES(cognome) <> '', VALUES(cognome), cognome),
            conferma_scade = IF(stato = 'attivo', conferma_scade, VALUES(conferma_scade)), ip = VALUES(ip), aggiornato = VALUES(aggiornato)")
        ->execute([':e' => $email, ':n' => mb_substr((string)$d['nome'], 0, 100), ':c' => mb_substr((string)($d['cognome'] ?? ''), 0, 100), ':f' => $fonte, ':v' => MODULI_CONSENSO_VERSIONE, ':s' => $scade, ':ip' => moduli_ip(), ':o' => $ora, ':o2' => $ora]);
    $id = newsletter_id_da_email($email);
    // Una richiesta nuova per la stessa lista e lo stesso form prende il posto di quella di prima.
    db()->prepare('DELETE FROM ' . tbl('newsletter_conferme') . ' WHERE iscritto_id = ? AND lista_id = ? AND form_id = ?')->execute([$id, $lista, $form]);
    db()->prepare('INSERT INTO ' . tbl('newsletter_conferme') . ' (token, iscritto_id, lista_id, form_id, scade, creato) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $id, $lista, $form, $scade, $ora]);
    $link = MODULI_URL . NEWSLETTER_CONFERMA . '?t=' . $token;
    if ($lista === $principale && !$form) {
        $cosa = trim((string)($opz['cosa'] ?? '')) ?: lcfirst(newsletter_nome());
        $frase = 'confermi l&#039;iscrizione alla ' . h($cosa);
        $oggetto = "Conferma l'iscrizione a " . (trim((string)cms_impostazione('newsletter_nome', '')) ?: 'questa newsletter');
        $fuori = 'senza conferma l&#039;indirizzo resta fuori dalla newsletter';
    } else {
        $cosa = trim((string)($opz['cosa'] ?? '')) ?: (newsletter_nome_lista($lista) ?: 'questa lista');
        $frase = 'confermi l&#039;iscrizione a «' . h($cosa) . '»';
        $oggetto = "Conferma l'iscrizione: " . $cosa;
        $fuori = 'senza conferma l&#039;indirizzo resta fuori';
    }
    $nome = trim((string)$d['nome']);
    $corpo = (string)($opz['testo'] ?? '') !== '' ? (string)$opz['testo']
        : '<p>Ciao' . ($nome !== '' ? ' ' . h($nome) : '') . ',</p><p>manca un clic. Premi il pulsante e ' . $frase . '.</p><p>Il link vale ' . MODULI_CONFERMA_GIORNI . ' giorni.</p>';
    $pulsante = trim((string)($opz['pulsante'] ?? '')) ?: 'Confermo l&#039;iscrizione';
    $html = str_replace(moduli_firma_html(), moduli_firma_html() . '<p style="font-size:12px;color:#888">Se la richiesta l&#039;ha fatta qualcun altro, ignora questa email: ' . $fuori . '.</p>',
        moduli_email_html('Conferma l\'iscrizione', $corpo, html_entity_decode($pulsante, ENT_QUOTES, 'UTF-8'), $link));
    $oggetto = trim((string)($opz['oggetto'] ?? '')) ?: $oggetto;
    return posta_invia($email, str_replace(["\r", "\n"], ' ', $oggetto), $html, moduli_intestazioni(), 'conferma') ? '' : 'posta';
}

/**
 * Il link di conferma. Ritorna null (link scaduto, già usato o falso) oppure com'è andata:
 *   ['iscritto' => id, 'email' => ..., 'lista' => id della lista in cui è entrato, 'form' => id del form d'origine
 *    (0 se dal modulo della newsletter), 'nuovo' => true se prima non era attivo]
 * Alla conferma riuscita parte anche l'evento dei moduli e dei temi 'newsletter_confermata', con lo stesso array.
 *
 * Aggancio per chi deve fare qualcosa alla conferma (per esempio consegnare un file omaggio del form d'origine):
 *   - nel manifest di un modulo: 'eventi' => ['newsletter_confermata' => function (array $esito) { if ($esito['form'] === 3) ... }];
 *   - oppure nella pagina di conferma, dopo newsletter_conferma(): $esito = newsletter_ultima_conferma(); (null se non è riuscita)
 *   - oppure al posto di newsletter_conferma(): $esito = newsletter_conferma_esito($token);
 */
function newsletter_conferma_esito(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    newsletter_schema();
    $hash = hash('sha256', $token);
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_conferme') . ' WHERE token = ? AND scade > NOW() LIMIT 1');
    $st->execute([$hash]);
    $c = $st->fetch();
    if ($c) {
        $id = (int)$c['iscritto_id']; $lista = (int)$c['lista_id']; $form = (int)$c['form_id'];
        // Un link vale una volta sola: chi lo usa per primo lo toglie.
        $del = db()->prepare('DELETE FROM ' . tbl('newsletter_conferme') . ' WHERE id = ?');
        $del->execute([(int)$c['id']]);
        if ($del->rowCount() !== 1) return null;
    } else {
        // I link mandati prima della 1.11.0: il token stava nella riga dell'iscritto, e la lista era già scritta.
        $i = db()->prepare('SELECT id FROM ' . tbl('newsletter_iscritti') . ' WHERE token_conferma = ? AND conferma_scade > NOW() LIMIT 1');
        $i->execute([$hash]);
        $id = (int)$i->fetchColumn();
        $lista = newsletter_lista_principale(); $form = 0;
    }
    if (!$id) return null;
    $i = db()->prepare('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $i->execute([$id]);
    $iscritto = $i->fetch();
    if (!$iscritto) return null;
    $nuovo = $iscritto['stato'] !== 'attivo';
    db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'attivo', consenso_at = NOW(), consenso_versione = ?, token_conferma = NULL, conferma_scade = NULL, uscito_at = NULL,
        rimbalzato_at = NULL, errori_di_fila = 0, ultimo_errore = '', aggiornato = NOW() WHERE id = ?")->execute([MODULI_CONSENSO_VERSIONE, $id]);
    // Ha confermato di sua volontà: se aveva chiesto la cancellazione, l'impronta non serve più.
    db()->prepare('DELETE FROM ' . tbl('newsletter_soppressi') . ' WHERE impronta = ?')->execute([newsletter_impronta((string)$iscritto['email'])]);
    // Flussi: "si iscrive alla newsletter" solo per la lista principale; poi la lista in cui entra adesso
    // (newsletter_in_lista fa partire i suoi flussi) e, per chi non era attivo, le liste in cui stava già.
    if ($lista === newsletter_lista_principale()) newsletter_flussi_innesco('iscrizione', 0, $id);
    $gia = db()->prepare('SELECT lista_id FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ?');
    $gia->execute([$id]);
    $gia = array_map('intval', $gia->fetchAll(PDO::FETCH_COLUMN));
    newsletter_in_lista($id, $lista);
    if ($nuovo) foreach ($gia as $l) newsletter_flussi_innesco('lista', $l, $id);
    // Gli inneschi arrivati mentre aspettava la conferma (un form compilato).
    $att = db()->prepare('SELECT innesco, innesco_id FROM ' . tbl('newsletter_inneschi_attesa') . ' WHERE iscritto_id = ?');
    $att->execute([$id]);
    $attese = $att->fetchAll();
    db()->prepare('DELETE FROM ' . tbl('newsletter_inneschi_attesa') . ' WHERE iscritto_id = ?')->execute([$id]);
    foreach ($attese as $x) newsletter_flussi_innesco((string)$x['innesco'], (int)$x['innesco_id'], $id);
    $esito = ['iscritto' => $id, 'email' => (string)$iscritto['email'], 'lista' => $lista, 'form' => $form, 'nuovo' => $nuovo];
    newsletter_ultima_conferma($esito);
    if (function_exists('cms_evento')) {
        try { cms_evento('newsletter_confermata', $esito); } catch (Throwable $e) { error_log('newsletter_confermata: ' . $e->getMessage()); }
    }
    return $esito;
}

/** La pagina di conferma del sito: vero se il link era buono. Il dettaglio sta in newsletter_ultima_conferma(). */
function newsletter_conferma(string $token): bool {
    return newsletter_conferma_esito($token) !== null;
}

/** L'esito dell'ultima conferma riuscita in questa richiesta (vedi newsletter_conferma_esito), o null. */
function newsletter_ultima_conferma(?array $nuovo = null): ?array {
    static $e = null;
    if ($nuovo !== null) $e = $nuovo;
    return $e;
}

/**
 * Ricorda un innesco per chi non ha ancora confermato (in attesa, o con una richiesta di conferma aperta):
 * parte alla conferma. Ritorna true se l'ha ricordato.
 */
function newsletter_innesco_in_attesa(string $innesco, int $innescoId, int $iscritto): bool {
    newsletter_schema();
    $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$iscritto]);
    $stato = (string)$st->fetchColumn();
    if ($stato === '' || $stato === 'attivo') return false;
    if ($stato !== 'in_attesa') {
        $c = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_conferme') . ' WHERE iscritto_id = ? AND scade > NOW()');
        $c->execute([$iscritto]);
        if (!(int)$c->fetchColumn()) return false;
    }
    db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_inneschi_attesa') . ' (iscritto_id, innesco, innesco_id, creato) VALUES (?, ?, ?, NOW())')->execute([$iscritto, $innesco, $innescoId]);
    return true;
}

function newsletter_token_uscita(int $id): string {
    return substr(hash_hmac('sha256', 'uscita|' . $id, moduli_segreto()), 0, 32);
}

function newsletter_link_uscita(int $id): string {
    return MODULI_URL . NEWSLETTER_ESCI . '?i=' . $id . '&t=' . newsletter_token_uscita($id);
}

/** L'iscritto del link di uscita, se il link è autentico. */
function newsletter_iscritto_da_uscita(string $i, string $t): ?array {
    if (!ctype_digit($i) || !hash_equals(newsletter_token_uscita((int)$i), $t)) return null;
    newsletter_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([(int)$i]);
    return $st->fetch() ?: null;
}

function newsletter_esci(int $id): void {
    db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'uscito', uscito_at = NOW(), aggiornato = NOW() WHERE id = ?")->execute([$id]);
    // Chi esce non riceve più niente, neanche le email già in coda.
    db()->prepare('DELETE FROM ' . tbl('newsletter_coda') . " WHERE iscritto_id = ? AND stato = 'attesa'")->execute([$id]);
}

/** Il server di posta ha rifiutato l'indirizzo per sempre (5xx): non riceve più niente, né campagne né flussi. */
function newsletter_rimbalzato(int $id, string $errore): void {
    db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'rimbalzato', rimbalzato_at = NOW(), ultimo_errore = ?, aggiornato = NOW() WHERE id = ? AND stato = 'attivo'")
        ->execute([mb_substr($errore, 0, 255), $id]);
    db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET stato = 'saltata' WHERE iscritto_id = ? AND stato = 'attesa'")->execute([$id]);
}

/**
 * Dopo una spedizione a un iscritto (campagna o flusso), secondo posta_esito(): a buon fine azzera gli errori di fila,
 * un rifiuto definitivo lo segna rimbalzato, un rifiuto temporaneo conta un errore di fila (da 3 in su si segnala nel
 * pannello, ma resta attivo). Gli errori del server (connessione, accesso) non sono colpa dell'indirizzo.
 */
function newsletter_esito_invio(int $id): void {
    $e = posta_esito();
    if ($e === 'ok') db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET errori_di_fila = 0, ultimo_errore = '' WHERE id = ? AND errori_di_fila > 0")->execute([$id]);
    elseif ($e === 'rimbalzo') newsletter_rimbalzato($id, posta_errore());
    elseif ($e === 'temporaneo') db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET errori_di_fila = errori_di_fila + 1, ultimo_errore = ? WHERE id = ?')->execute([mb_substr(posta_errore(), 0, 255), $id]);
}

/** Toglie dal database le righe di questi iscritti, in tutte le tabelle della newsletter e dei flussi. */
function newsletter_togli_iscritti(array $ids): void {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return;
    newsletter_flussi_schema();
    $in = implode(',', $ids);
    foreach (['newsletter_coda', 'newsletter_iscritti_liste', 'newsletter_conferme', 'newsletter_inneschi_attesa', 'newsletter_eventi', 'newsletter_corsi'] as $t) {
        db()->exec('DELETE FROM ' . tbl($t) . " WHERE iscritto_id IN ($in)");
    }
    db()->exec('DELETE FROM ' . tbl('newsletter_iscritti') . " WHERE id IN ($in)");
}

/**
 * Diritto alla cancellazione: via l'iscritto, le sue aperture e i suoi clic, i suoi cammini nei flussi, le richieste di
 * conferma e gli invii dei form con la sua email. L'impronta dell'email resta nella lista di soppressione: l'importazione
 * non lo rimette dentro (un'iscrizione sua, con la doppia conferma, sì). Ritorna l'email cancellata, '' se non c'era.
 */
function newsletter_cancella_dati(int $id): string {
    newsletter_schema();
    $st = db()->prepare('SELECT email FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$id]);
    $email = (string)$st->fetchColumn();
    if ($email === '') return '';
    newsletter_togli_iscritti([$id]);
    // Gli invii dei form con questa email (la tabella c'è solo se un form esiste).
    try { db()->prepare('DELETE FROM ' . tbl('moduli_invii') . ' WHERE email = ?')->execute([$email]); } catch (PDOException $e) {}
    db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_soppressi') . ' (impronta, creato) VALUES (?, NOW())')->execute([newsletter_impronta($email)]);
    return $email;
}

/**
 * La pulizia, una volta al giorno (dal cron): il registro della posta più vecchio di 60 giorni, le richieste di conferma
 * scadute, gli iscritti ancora in attesa con la conferma scaduta da più di 30 giorni. Ritorna le righe di log.
 */
function newsletter_pulizia(bool $forza = false): array {
    newsletter_schema();
    $oggi = date('Y-m-d');
    if (!$forza && get_setting('newsletter_pulizia_giorno', '') === $oggi) return [];
    save_setting('newsletter_pulizia_giorno', $oggi);
    $log = [];
    $n = posta_pulisci_registro(60);
    if ($n) $log[] = "registro della posta: $n righe vecchie tolte";
    db()->exec('DELETE FROM ' . tbl('newsletter_conferme') . ' WHERE scade < NOW()');
    $ids = db()->query('SELECT id FROM ' . tbl('newsletter_iscritti') . " WHERE stato = 'in_attesa'
        AND COALESCE(conferma_scade, DATE_ADD(aggiornato, INTERVAL " . MODULI_CONFERMA_GIORNI . ' DAY)) < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_conferme') . ' c WHERE c.iscritto_id = ' . tbl('newsletter_iscritti') . '.id) LIMIT 2000')->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) { newsletter_togli_iscritti($ids); $log[] = count($ids) . ' iscritti mai confermati tolti'; }
    // Inneschi in attesa di chi nel frattempo non c'è più o non ha confermato in tempo.
    db()->exec('DELETE FROM ' . tbl('newsletter_inneschi_attesa') . ' WHERE creato < DATE_SUB(NOW(), INTERVAL 40 DAY)');
    return $log;
}

/** Una data di consenso scritta nel CSV (2026-03-01, 2026-03-01 10:20, 01/03/2026...) in formato del database, o null. */
function newsletter_data_csv(string $v): ?string {
    $v = trim($v);
    if ($v === '') return null;
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.vP', 'Y-m-d\TH:i:s'] as $f) {
        $d = DateTime::createFromFormat('!' . $f, $v);
        if ($d && $d->format('Y') >= 1990 && $d->getTimestamp() <= time() + 86400) return $d->format('Y-m-d H:i:s');
    }
    return null;
}

/**
 * Importazione dei contatti esportati da un altro servizio, per esempio Brevo (CSV). Colonne riconosciute: EMAIL,
 * NOME/FIRSTNAME, COGNOME/LASTNAME e, se c'è, la data del consenso (CONSENSO, DATA CONSENSO, OPTIN...).
 * 1.11.0: entrano come attivi con fonte 'import' e la data del consenso del file (vuota se il file non la porta).
 * Chi è già nell'elenco resta com'è ed entra anche lui nella lista; chi è uscito, è rimbalzato o ha chiesto la
 * cancellazione dei dati (lista di soppressione) si salta. I flussi della lista partono solo con $flussi.
 */
function newsletter_importa_csv(string $file, int $lista, bool $flussi = false): array {
    newsletter_schema();
    $f = fopen($file, 'r');
    if (!$f) return ['errore' => 'File illeggibile.'];
    $prima = (string)fgets($f);
    $prima = preg_replace('/^\xEF\xBB\xBF/', '', $prima);
    $sep = substr_count($prima, ';') > substr_count($prima, ',') ? ';' : ',';
    $testa = array_map(fn($x) => mb_strtoupper(trim($x, " \t\n\r\0\x0B\"")), str_getcsv($prima, $sep, '"', '\\'));
    $col = fn(array $nomi) => array_values(array_filter(array_keys($testa), fn($i) => in_array($testa[$i], $nomi, true)))[0] ?? null;
    $cE = $col(['EMAIL', 'E-MAIL', 'INDIRIZZO EMAIL']);
    $cN = $col(['NOME', 'FIRSTNAME', 'FIRST NAME', 'PRENOM']);
    $cC = $col(['COGNOME', 'LASTNAME', 'LAST NAME', 'NOM']);
    $cD = $col(['CONSENSO', 'CONSENSO_AT', 'DATA CONSENSO', 'DATA_CONSENSO', 'DATA ISCRIZIONE', 'OPTIN', 'OPT_IN', 'OPTIN_DATE', 'DATE_OPTIN', 'DOUBLE_OPT-IN', 'CONSENT_DATE']);
    if ($cE === null) return ['errore' => 'Nel file manca la colonna EMAIL.'];
    $n = ['nuovi' => 0, 'gia' => 0, 'scartati' => 0, 'saltati' => 0];
    $ora = moduli_adesso();
    $ins = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti') . " (email, nome, cognome, stato, fonte, consenso_versione, consenso_at, creato, aggiornato)
        VALUES (?, ?, ?, 'attivo', 'import', 'import', ?, ?, ?)");
    $chi = db()->prepare('SELECT id, stato FROM ' . tbl('newsletter_iscritti') . ' WHERE email = ?');
    $pulisci = fn($v) => mb_substr(trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$v)), 0, 100);
    while (($r = fgetcsv($f, 0, $sep, '"', '\\')) !== false) {
        $email = mb_strtolower(trim((string)($r[$cE] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) { $n['scartati']++; continue; }
        if (newsletter_soppresso($email)) { $n['saltati']++; continue; }
        $chi->execute([$email]);
        $c = $chi->fetch();
        if ($c && in_array($c['stato'], ['uscito', 'rimbalzato'], true)) { $n['saltati']++; continue; }
        if (!$c) {
            $ins->execute([$email, $pulisci($cN !== null ? ($r[$cN] ?? '') : ''), $pulisci($cC !== null ? ($r[$cC] ?? '') : ''), $cD !== null ? newsletter_data_csv((string)($r[$cD] ?? '')) : null, $ora, $ora]);
            $ins->rowCount() ? $n['nuovi']++ : $n['gia']++;
        } else {
            $n['gia']++;
        }
        if ($lista) newsletter_in_lista(newsletter_id_da_email($email), $lista, $flussi);
    }
    fclose($f);
    return $n;
}

// ------------------------------------------------------------
// Campagne: dall'editor all'email
// ------------------------------------------------------------

/**
 * L'HTML dell'editor (Quill) diventa HTML da email: liste vere, allineamenti e stili scritti sugli elementi,
 * perché molti programmi di posta ignorano i fogli di stile.
 */
function newsletter_corpo_email(string $html): string {
    if (trim($html) === '') return '';
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="radice">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $x = new DOMXPath($doc);
    foreach (iterator_to_array($x->query('//script|//style|//iframe|//span[contains(@class,"ql-ui")]')) as $n) $n->parentNode->removeChild($n);
    // Quill 2 scrive tutte le liste come <ol>, con data-list sui singoli punti.
    foreach (iterator_to_array($x->query('//ol')) as $ol) {
        $primo = $x->query('./li', $ol)->item(0);
        if ($primo && $primo->getAttribute('data-list') === 'bullet') {
            $ul = $doc->createElement('ul');
            while ($ol->firstChild) $ul->appendChild($ol->firstChild);
            $ol->parentNode->replaceChild($ul, $ol);
        }
    }
    $stili = [
        'p' => 'margin:0 0 16px;font-size:16px;line-height:1.65;color:#1f2330',
        'h2' => 'margin:28px 0 12px;font-size:22px;line-height:1.3;color:#0b1020',
        'h3' => 'margin:22px 0 10px;font-size:18px;line-height:1.35;color:#0b1020',
        'a' => 'color:#7c3aed;text-decoration:underline',
        'img' => 'max-width:100%;height:auto;display:block;margin:8px 0 16px;border-radius:8px',
        'ul' => 'margin:0 0 16px;padding-left:22px', 'ol' => 'margin:0 0 16px;padding-left:22px',
        'li' => 'margin:0 0 6px;font-size:16px;line-height:1.6;color:#1f2330',
        'blockquote' => 'margin:0 0 16px;padding:4px 0 4px 16px;border-left:3px solid #22d3ee;color:#444',
    ];
    foreach ($x->query('//*[@id="radice"]//*') as $el) {
        /** @var DOMElement $el */
        $stile = $stili[$el->tagName] ?? '';
        if (preg_match('/ql-align-(center|right|justify)/', $el->getAttribute('class'), $m)) $stile .= ';text-align:' . $m[1];
        foreach (['class', 'data-list', 'contenteditable', 'spellcheck'] as $a) $el->removeAttribute($a);
        if ($el->tagName === 'a') { $el->setAttribute('target', '_blank'); $el->removeAttribute('rel'); }
        if ($stile !== '') $el->setAttribute('style', ltrim($stile, ';'));
    }
    $out = '';
    foreach ($doc->getElementById('radice')->childNodes as $c) $out .= $doc->saveHTML($c);
    return $out;
}

/** Emoji e altri caratteri a 4 byte: nel database come entità HTML (colonne utf8 a 3 byte), nell'oggetto come caratteri veri. */
function newsletter_4byte_dentro(string $s): string {
    return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', fn($m) => '&#x' . strtoupper(dechex(mb_ord($m[0]))) . ';', $s);
}
function newsletter_4byte_fuori(string $s): string {
    return preg_replace_callback('/&#x([0-9a-f]{5,6});/i', fn($m) => mb_chr(hexdec($m[1])), $s);
}

/** {nome} nell'oggetto e nel testo diventa il nome dell'iscritto. */
function newsletter_personalizza(string $s, array $iscritto, bool $html): string {
    $nome = trim((string)($iscritto['nome'] ?? ''));
    return str_replace('{nome}', $html ? h($nome) : $nome, $s);
}

/** L'email completa per un iscritto (o per una prova: id 0). */
function newsletter_email(array $c, array $iscritto, ?array $traccia = null): array {
    $oggetto = newsletter_4byte_fuori(newsletter_personalizza($c['oggetto'], $iscritto, false));
    if ($traccia === null && !empty($c['id'])) $traccia = ['c' => (int)$c['id']];
    $corpo = newsletter_personalizza(newsletter_corpo_email((string)$c['corpo']), $iscritto, true);
    $id = (int)($iscritto['id'] ?? 0);
    $uscita = $id ? newsletter_link_uscita($id) : MODULI_URL . NEWSLETTER_ESCI;
    $btn = (($c['pulsante_testo'] ?? '') !== '' && ($c['pulsante_link'] ?? '') !== '')
        ? '<p style="margin:26px 0"><a href="' . h($c['pulsante_link']) . '" target="_blank" style="display:inline-block;background:#7c3aed;color:#fff;padding:14px 26px;border-radius:10px;text-decoration:none;font-weight:700">' . h($c['pulsante_testo']) . '</a></p>'
        : '';
    // Il testo di anteprima: quello che i programmi di posta mostrano accanto all'oggetto.
    $anteprima = $c['anteprima'] !== '' ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">' . h(newsletter_4byte_fuori(newsletter_personalizza($c['anteprima'], $iscritto, false))) . str_repeat('&#8204;&nbsp;', 40) . '</div>' : '';
    $html = '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($oggetto) . '</title></head>'
        . '<body style="margin:0;background:#f4f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2330">' . $anteprima
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px">'
        . '<tr><td style="background:#060a17;color:#fff;padding:22px 30px;font-weight:700;letter-spacing:.06em">' . (string)cms_impostazione('newsletter_intestazione', mb_strtoupper(h((string)cms_impostazione('sito_nome', CMS_NOME)))) . '</td></tr>'
        . '<tr><td style="padding:30px">' . $corpo . $btn . '</td></tr>'
        . '<tr><td style="padding:18px 30px 26px;border-top:1px solid #eee;font-size:12px;line-height:1.6;color:#888">'
        . 'Ricevi questa email perché ti sei iscritto alla ' . h(lcfirst(newsletter_nome())) . ' di ' . h((string)cms_impostazione('newsletter_firma', cms_impostazione('sito_nome', CMS_NOME))) . '. '
        . '<a href="' . h($uscita) . '" style="color:#888">Esci dalla newsletter</a>, basta un clic.</td></tr>'
        . '</table></td></tr></table></body></html>';
    // Pixel di apertura e link tracciati: solo per le email vere, non per le prove.
    if ($id && $traccia) $html = newsletter_traccia($html, $traccia, $id);
    $cfg = newsletter_config();
    $intest = moduli_intestazioni($cfg['rispondi_a'])
        . ($id ? "List-Unsubscribe: <$uscita>\r\nList-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n" : '');
    return [$oggetto, $html, $intest];
}

function newsletter_campagna(int $id): ?array {
    newsletter_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_campagne') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function newsletter_numeri(): array {
    newsletter_schema();
    $r = ['in_attesa' => 0, 'attivo' => 0, 'uscito' => 0, 'rimbalzato' => 0];
    foreach (db()->query('SELECT stato, COUNT(*) n FROM ' . tbl('newsletter_iscritti') . ' GROUP BY stato') as $x) $r[$x['stato']] = (int)$x['n'];
    // Attivi con tre errori temporanei di fila o più: da controllare.
    $r['segnalati'] = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " WHERE stato = 'attivo' AND errori_di_fila >= 3")->fetchColumn();
    return $r;
}

// ------------------------------------------------------------
// Invio: la coda
// ------------------------------------------------------------

/** Campagne arrivate alla loro ora: si prepara la coda con gli iscritti attivi in quel momento. */
function newsletter_avvia_programmate(): array {
    $log = [];
    $pronte = db()->query('SELECT * FROM ' . tbl('newsletter_campagne') . " WHERE stato = 'programmata' AND invio_at <= NOW()")->fetchAll();
    foreach ($pronte as $c) {
        $id = (int)$c['id'];
        $liste = newsletter_liste_campagna($c);
        if (!$liste) {
            // Le sue liste sono state cancellate: torna bozza, invece di partire verso nessuno o verso tutti.
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'bozza', invio_at = NULL WHERE id = ?")->execute([$id]);
            $log[] = "campagna $id senza liste: tornata bozza";
            continue;
        }
        // Chi sta in più liste scelte riceve l'email una volta sola (chiave unica campagna + iscritto).
        db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_coda') . ' (campagna_id, iscritto_id) SELECT DISTINCT ?, i.id FROM ' . tbl('newsletter_iscritti') . ' i
            JOIN ' . tbl('newsletter_iscritti_liste') . " l ON l.iscritto_id = i.id WHERE i.stato = 'attivo' AND l.lista_id IN (" . implode(',', $liste) . ')')->execute([$id]);
        $n = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_coda') . ' WHERE campagna_id = ' . (int)$id)->fetchColumn();
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'in_invio', destinatari = ?, aggiornato = NOW() WHERE id = ?")->execute([$n, $id]);
        $log[] = "campagna $id avviata: $n destinatari";
    }
    return $log;
}

/** Un passaggio del cron: manda fino al limite orario. */
function newsletter_invia_giro(?int $massimo = null): array {
    newsletter_schema();
    if (!db()->query("SELECT GET_LOCK('" . DB_PREFIX . "newsletter_invio', 0)")->fetchColumn()) return ['un altro invio è in corso'];
    try {
        $log = newsletter_avvia_programmate();
        // 1.11.0: il limite conta tutte le email del CMS (registro della posta): conferme, avvisi, flussi, campagne.
        // Le campagne della coda restano contate anche se il registro è appena nato (il primo giro dopo l'aggiornamento).
        $cfg = newsletter_config();
        $ultimaOra = max(posta_inviate(3600), (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_coda') . " WHERE inviata_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn());
        $posti = min($massimo ?? NEWSLETTER_PER_GIRO, max(0, (int)$cfg['per_ora'] - $ultimaOra));
        if ($posti === 0) return array_merge($log, ['limite orario raggiunto']);
        if ((int)$cfg['per_giorno'] > 0) {
            $posti = min($posti, max(0, (int)$cfg['per_giorno'] - posta_inviate(86400)));
            if ($posti === 0) return array_merge($log, ['limite giornaliero raggiunto']);
        }
        $righe = db()->query('SELECT q.id, q.campagna_id, q.tentativi, i.id AS iscritto_id, i.email, i.nome, i.cognome FROM ' . tbl('newsletter_coda') . ' q
            JOIN ' . tbl('newsletter_campagne') . ' c ON c.id = q.campagna_id AND c.stato = \'in_invio\'
            JOIN ' . tbl('newsletter_iscritti') . " i ON i.id = q.iscritto_id AND i.stato = 'attivo'
            WHERE q.stato = 'attesa' ORDER BY q.id LIMIT " . (int)$posti)->fetchAll();
        $campagne = [];
        $ok = 0; $ko = 0; $rimbalzi = 0;
        foreach ($righe as $r) {
            $c = $campagne[$r['campagna_id']] ??= newsletter_campagna((int)$r['campagna_id']);
            [$oggetto, $html, $intest] = newsletter_email($c, ['id' => $r['iscritto_id'], 'nome' => $r['nome'], 'email' => $r['email']]);
            $partita = posta_invia($r['email'], $oggetto, $html, $intest, 'campagna');
            newsletter_esito_invio((int)$r['iscritto_id']);
            if ($partita) {
                db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET stato = 'inviata', inviata_at = NOW() WHERE id = ?")->execute([$r['id']]);
                db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET inviate = inviate + 1 WHERE id = ?')->execute([$r['campagna_id']]);
                $ok++;
            } elseif (posta_esito() === 'rimbalzo') {
                // L'indirizzo non esiste: niente altri tentativi (il server di posta funziona, si va avanti).
                db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET tentativi = tentativi + 1, errore = ?, stato = 'errore' WHERE id = ?")->execute([mb_substr(posta_errore(), 0, 255), $r['id']]);
                db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET errori = errori + 1 WHERE id = ?')->execute([$r['campagna_id']]);
                $rimbalzi++;
            } else {
                // Tre tentativi, a giri diversi; poi l'indirizzo si segna come errore.
                $fine = $r['tentativi'] + 1 >= 3;
                db()->prepare('UPDATE ' . tbl('newsletter_coda') . ' SET tentativi = tentativi + 1, errore = ?, stato = ? WHERE id = ?')
                    ->execute([mb_substr(posta_errore(), 0, 255), $fine ? 'errore' : 'attesa', $r['id']]);
                if ($fine) db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET errori = errori + 1 WHERE id = ?')->execute([$r['campagna_id']]);
                $ko++;
                if ($ko >= 3 && $ok === 0) { $log[] = 'posta ferma: ' . posta_errore(); break; }
            }
        }
        // Coda vuota: campagna inviata. Le righe di chi è uscito nel frattempo si chiudono.
        db()->exec('UPDATE ' . tbl('newsletter_coda') . ' q JOIN ' . tbl('newsletter_iscritti') . " i ON i.id = q.iscritto_id SET q.stato = 'saltata' WHERE q.stato = 'attesa' AND i.stato <> 'attivo'");
        db()->exec('UPDATE ' . tbl('newsletter_campagne') . " c SET stato = 'inviata', aggiornato = NOW() WHERE stato = 'in_invio'
            AND NOT EXISTS (SELECT 1 FROM " . tbl('newsletter_coda') . " q WHERE q.campagna_id = c.id AND q.stato = 'attesa')");
        if ($ok || $ko) $log[] = "inviate $ok, non partite $ko";
        if ($rimbalzi) $log[] = "indirizzi rifiutati dal server di posta (rimbalzati): $rimbalzi";
        // I flussi automatici, con i posti rimasti nel limite orario.
        $log = array_merge($log, newsletter_flussi_giro($posti - $ok - $ko - $rimbalzi));
        return $log;
    } finally {
        db()->query("SELECT RELEASE_LOCK('" . DB_PREFIX . "newsletter_invio')");
    }
}

require_once __DIR__ . '/newsletter-flussi.php';
