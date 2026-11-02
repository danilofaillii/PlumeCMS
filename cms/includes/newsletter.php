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
 * 2.0.0:
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
require_once __DIR__ . '/newsletter-email.php'; // 2.7.0: l'email di conferma modificabile

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
            `mittente_id` INT(11) NOT NULL DEFAULT 0,
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
        // 2.0.0: una riga per ogni email di conferma mandata. La persona entra nella lista solo quando conferma.
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
        // 2.0.0: gli inneschi dei flussi arrivati mentre la persona non aveva ancora confermato.
        'newsletter_inneschi_attesa' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_inneschi_attesa') . " (
            `iscritto_id` INT(11) NOT NULL,
            `innesco` VARCHAR(20) NOT NULL,
            `innesco_id` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`iscritto_id`, `innesco`, `innesco_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // 2.0.0: chi ha chiesto la cancellazione dei dati, come impronta sha256 dell'email in minuscolo.
        'newsletter_soppressi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_soppressi') . " (
            `impronta` CHAR(64) NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`impronta`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // 2.8.0, la base dei contatti come Brevo (docs-sviluppo/brevo-confronto.md, capitolo 3).
        // Gli attributi: quelli di sistema legati a una colonna di newsletter_iscritti (colonna), quelli normali con i
        // valori in newsletter_valori, quelli di categoria con le opzioni, i calcolati e i globali con una formula.
        'newsletter_attributi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_attributi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(50) NOT NULL,
            `etichetta` VARCHAR(100) NOT NULL DEFAULT '',
            `categoria` VARCHAR(12) NOT NULL DEFAULT 'normal',
            `tipo` VARCHAR(16) NOT NULL DEFAULT 'text',
            `sistema` TINYINT(1) NOT NULL DEFAULT 0,
            `colonna` VARCHAR(30) NOT NULL DEFAULT '',
            `formula` VARCHAR(255) NOT NULL DEFAULT '',
            `ordine` INT(11) NOT NULL DEFAULT 0,
            `in_scheda` TINYINT(1) NOT NULL DEFAULT 1,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `nome` (`nome`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_attributi_opzioni' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_attributi_opzioni') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `attributo_id` INT(11) NOT NULL,
            `valore` INT(11) NOT NULL DEFAULT 0,
            `etichetta` VARCHAR(200) NOT NULL DEFAULT '',
            `ordine` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), UNIQUE KEY `attributo_valore` (`attributo_id`, `valore`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // Una riga per valore (per la scelta multipla una per opzione): niente colonne JSON né colonne generate.
        'newsletter_valori' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_valori') . " (
            `contatto_id` INT(11) NOT NULL,
            `attributo_id` INT(11) NOT NULL,
            `opzione` INT(11) NOT NULL DEFAULT 0,
            `v_testo` VARCHAR(500) NOT NULL DEFAULT '',
            `v_num` DECIMAL(20,6) DEFAULT NULL,
            `v_data` DATE DEFAULT NULL,
            PRIMARY KEY (`contatto_id`, `attributo_id`, `opzione`),
            KEY `testo` (`attributo_id`, `v_testo`(100)), KEY `num` (`attributo_id`, `v_num`), KEY `data` (`attributo_id`, `v_data`), KEY `opz` (`attributo_id`, `opzione`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_cartelle' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_cartelle') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `nome` (`nome`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // Chi si è disiscritto da una sola lista (Brevo: listUnsubscribed): non riceve le campagne di quella lista.
        'newsletter_liste_uscite' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_liste_uscite') . " (
            `contatto_id` INT(11) NOT NULL,
            `lista_id` INT(11) NOT NULL,
            `quando` DATETIME NOT NULL,
            PRIMARY KEY (`contatto_id`, `lista_id`), KEY `lista` (`lista_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // La storia del contatto: quello che non sta già in coda ed eventi (liste, form, attributi, lista nera...).
        'newsletter_storia' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_storia') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `contatto_id` INT(11) NOT NULL,
            `tipo` VARCHAR(20) NOT NULL,
            `rif_id` INT(11) NOT NULL DEFAULT 0,
            `dettaglio` VARCHAR(255) NOT NULL DEFAULT '',
            `utente_id` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `contatto` (`contatto_id`, `creato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // Il registro del consenso: in più rispetto a Brevo (che tiene solo OPT_IN e DOUBLE_OPT-IN).
        'newsletter_consensi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_consensi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `contatto_id` INT(11) NOT NULL,
            `tipo` VARCHAR(12) NOT NULL DEFAULT 'opt_in',
            `testo` TEXT,
            `form_id` INT(11) NOT NULL DEFAULT 0,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `contatto` (`contatto_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME);
    if (get_setting('newsletter_schema_impronta', '') !== $impronta) {
        foreach ($tabelle as $sql) db()->exec($sql);
        // Colonna arrivata dopo la prima versione (23 ottobre, sera): le liste della campagna.
        $c = db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . DB_PREFIX . "newsletter_campagne' AND COLUMN_NAME = 'liste'")->fetchColumn();
        if (!$c) db()->exec('ALTER TABLE ' . tbl('newsletter_campagne') . " ADD COLUMN `liste` VARCHAR(255) NOT NULL DEFAULT '' AFTER `errori`");
        // 2.0.0: rimbalzi ed errori temporanei di fila. Le tabelle nate prima non hanno le colonne.
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('newsletter_iscritti'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('errori_di_fila', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_iscritti') . ' ADD COLUMN `errori_di_fila` INT(11) NOT NULL DEFAULT 0 AFTER `ip`');
        if (!in_array('ultimo_errore', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_iscritti') . " ADD COLUMN `ultimo_errore` VARCHAR(255) NOT NULL DEFAULT '' AFTER `errori_di_fila`");
        if (!in_array('rimbalzato_at', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_iscritti') . ' ADD COLUMN `rimbalzato_at` DATETIME DEFAULT NULL AFTER `ultimo_errore`');
        // 2.1.0: il mittente scelto per la campagna (0 = il predefinito), includes/consegna.php.
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('newsletter_campagne'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('mittente_id', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_campagne') . ' ADD COLUMN `mittente_id` INT(11) NOT NULL DEFAULT 0 AFTER `liste`');
        $fatto = true;
        // La lista di partenza. Chi era iscritto prima delle liste entra nella principale, una volta sola: dalla 2.0.0
        // chi aspetta la conferma di un form non sta in nessuna lista, e non deve finire nella principale.
        $primaVolta = !(int)get_setting('newsletter_lista_principale', 0);
        $principale = newsletter_lista_di_sistema('principale', newsletter_nome(), 'Chi si iscrive dal sito, e gli iscritti di prima delle liste');
        if ($primaVolta) {
            db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) SELECT i.id, ?, NOW() FROM ' . tbl('newsletter_iscritti') . ' i
                WHERE NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = i.id)')->execute([$principale]);
        }
        // Le campagne di prima delle liste vanno alla principale. 2.11.0: da quando ci sono i segmenti (colonna segmenti)
        // una campagna nuova può non avere liste, e allora non si tocca.
        if (!in_array('segmenti', $col, true)) db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET liste = ? WHERE liste = ''")->execute([(string)$principale]);
        newsletter_contatti_migra(); // 2.8.0
        save_setting('newsletter_schema_impronta', $impronta);
    }
    $fatto = true;
}

/** 2.8.0: i motivi della lista nera, con i codici di Brevo, e lo stato di newsletter_iscritti che ognuno dà. */
const NL_MOTIVI = [
    'unsubscribedViaEmail' => ['Si è disiscritto dal link dell\'email', 'uscito'],
    'unsubscribedViaMA' => ['Si è disiscritto da un\'automazione', 'uscito'],
    'unsubscribedViaApi' => ['Si è disiscritto dal servizio di invio', 'uscito'],
    'adminBlocked' => ['Messo in lista nera a mano', 'bloccato'],
    'hardBounce' => ['Hard bounce: il server di posta ha rifiutato l\'indirizzo', 'rimbalzato'],
    'softBounce' => ['Cinque soft bounce di fila', 'bloccato'],
    'contactFlaggedAsSpam' => ['Ha segnalato un\'email come spam', 'spam'],
    'import' => ['Messo in lista nera con un\'importazione', 'bloccato'],
];
/** Gli stati che vogliono dire "in lista nera" per il canale email. */
const NL_BLOCCATI = ['uscito', 'rimbalzato', 'spam', 'bloccato'];
/** Quanti soft bounce di fila mettono un contatto in lista nera (Brevo: 5). */
const NL_SOFT_BOUNCE_MAX = 5;

/**
 * 2.8.0: colonne e righe nuove della base dei contatti, e la migrazione dei dati di prima, una volta sola. Nessuna tabella
 * si rinomina e nessuna colonna si toglie: gli stati di oggi restano e diventano anche motivi della lista nera.
 */
function newsletter_contatti_migra(): void {
    $t = tbl('newsletter_iscritti');
    $col = db()->query('SHOW COLUMNS FROM ' . $t)->fetchAll(PDO::FETCH_COLUMN);
    $aggiungi = [
        'ext_id' => 'VARCHAR(190) DEFAULT NULL',
        'blocco_motivo' => "VARCHAR(24) NOT NULL DEFAULT ''",
        'blocco_at' => 'DATETIME DEFAULT NULL',
        'opt_in' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'doi_at' => 'DATETIME DEFAULT NULL',
        'tracciamento' => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];
    foreach ($aggiungi as $c => $def) if (!in_array($c, $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `$c` $def");
    $ind = db()->query('SHOW INDEX FROM ' . $t)->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('ext_id', $ind, true)) db()->exec("ALTER TABLE $t ADD UNIQUE KEY `ext_id` (`ext_id`)");
    $l = tbl('newsletter_liste');
    $col = db()->query('SHOW COLUMNS FROM ' . $l)->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('cartella_id', $col, true)) db()->exec("ALTER TABLE $l ADD COLUMN `cartella_id` INT(11) NOT NULL DEFAULT 0");
    // Gli attributi di sistema (Brevo: EMAIL, FIRSTNAME, LASTNAME, EXT_ID, OPT_IN, DOUBLE_OPT-IN, CREATION_DATE, LAST_CHANGED).
    $sis = [
        ['EMAIL', 'Email', 'text', 'email'], ['NOME', 'Nome', 'text', 'nome'], ['COGNOME', 'Cognome', 'text', 'cognome'],
        ['EXT_ID', 'Identificativo esterno', 'text', 'ext_id'], ['OPT_IN', 'Consenso (casella del form)', 'boolean', 'opt_in'],
        ['DOUBLE_OPT-IN', 'Doppio opt-in confermato', 'boolean', 'doi_at'], ['CREATION_DATE', 'Data di creazione', 'date', 'creato'],
        ['LAST_CHANGED', 'Ultima modifica', 'date', 'aggiornato'],
    ];
    $ins = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_attributi') . " (nome, etichetta, categoria, tipo, sistema, colonna, ordine, creato) VALUES (?, ?, 'normal', ?, 1, ?, ?, NOW())");
    foreach ($sis as $i => $a) $ins->execute([$a[0], $a[1], $a[2], $a[3], $i + 1]);
    if (get_setting('newsletter_contatti_migrati', '') === '1') return;
    // Gli stati di oggi diventano motivi della lista nera, con la data che c'era.
    db()->exec("UPDATE $t SET blocco_motivo = 'unsubscribedViaEmail', blocco_at = COALESCE(uscito_at, aggiornato) WHERE stato = 'uscito' AND blocco_motivo = ''");
    db()->exec("UPDATE $t SET blocco_motivo = 'hardBounce', blocco_at = COALESCE(rimbalzato_at, aggiornato) WHERE stato = 'rimbalzato' AND blocco_motivo = ''");
    db()->exec("UPDATE $t SET blocco_motivo = 'contactFlaggedAsSpam', blocco_at = COALESCE(uscito_at, aggiornato) WHERE stato = 'spam' AND blocco_motivo = ''");
    // DOUBLE_OPT-IN: chi è attivo dopo una conferma del link; OPT_IN: chi ha dato il consenso del modulo o del form.
    db()->exec("UPDATE $t SET doi_at = consenso_at WHERE doi_at IS NULL AND consenso_at IS NOT NULL AND fonte IN ('modulo', 'form') AND stato IN ('attivo', 'uscito', 'rimbalzato', 'spam')");
    db()->exec("UPDATE $t SET opt_in = 1 WHERE consenso_at IS NOT NULL AND consenso_versione NOT IN ('', 'import')");
    // Il registro del consenso parte da quello che c'era (versione, data, IP).
    db()->exec('INSERT INTO ' . tbl('newsletter_consensi') . " (contatto_id, tipo, testo, form_id, ip, creato)
        SELECT i.id, IF(i.fonte = 'import', 'import', 'opt_in'), i.consenso_versione, 0, i.ip, i.consenso_at FROM $t i
        WHERE i.consenso_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM " . tbl('newsletter_consensi') . ' c WHERE c.contatto_id = i.id)');
    // Le liste di prima in una cartella "Le tue liste".
    db()->exec('INSERT IGNORE INTO ' . tbl('newsletter_cartelle') . " (nome, creato) VALUES ('Le tue liste', NOW())");
    $cart = (int)db()->query('SELECT id FROM ' . tbl('newsletter_cartelle') . " WHERE nome = 'Le tue liste'")->fetchColumn();
    db()->exec("UPDATE $l SET cartella_id = $cart WHERE cartella_id = 0");
    save_setting('newsletter_contatti_migrati', '1');
}

/** 2.8.0: una riga nella storia del contatto (Cronologia della scheda). Non ferma mai chi la chiama. */
function newsletter_storia_scrivi(int $id, string $tipo, string $dettaglio = '', int $rif = 0): void {
    if (!$id) return;
    try {
        $u = 0;
        if (session_status() === PHP_SESSION_ACTIVE) $u = (int)($_SESSION['admin_user_id'] ?? 0);
        db()->prepare('INSERT INTO ' . tbl('newsletter_storia') . ' (contatto_id, tipo, rif_id, dettaglio, utente_id, creato) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([$id, mb_substr($tipo, 0, 20), $rif, mb_substr(newsletter_4byte_dentro($dettaglio), 0, 255), $u]);
    } catch (Throwable $e) { error_log('newsletter_storia: ' . $e->getMessage()); }
}

/**
 * 2.8.0: mette un contatto in lista nera email con il motivo (codici di Brevo, NL_MOTIVI). Stato, motivo, data, coda e
 * storia cambiano insieme. Chi è in lista nera non riceve più niente (campagne, automazioni, coda).
 * Ritorna false se il motivo non esiste o il contatto non c'è.
 */
function newsletter_contatto_blocca(int $id, string $motivo, string $dettaglio = ''): bool {
    if (!isset(NL_MOTIVI[$motivo])) return false;
    newsletter_schema();
    $stato = NL_MOTIVI[$motivo][1];
    $st = db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = ?, blocco_motivo = ?, blocco_at = NOW(),
        uscito_at = IF(? IN ('uscito', 'spam', 'bloccato'), NOW(), uscito_at), rimbalzato_at = IF(? = 'rimbalzato', NOW(), rimbalzato_at), aggiornato = NOW() WHERE id = ?");
    $st->execute([$stato, $motivo, $stato, $stato, $id]);
    if (!$st->rowCount()) return false;
    db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET stato = 'saltata' WHERE iscritto_id = ? AND stato = 'attesa'")->execute([$id]);
    newsletter_storia_scrivi($id, 'bloccato', NL_MOTIVI[$motivo][0] . ($dettaglio !== '' ? ': ' . $dettaglio : ''));
    return true;
}

/**
 * 2.8.0: toglie un contatto dalla lista nera email (Brevo: solo dalla scheda, uno alla volta). Torna iscritto; se aveva
 * chiesto la cancellazione dei dati, l'impronta si toglie (lo ha deciso l'amministratore, dopo l'avviso).
 */
function newsletter_contatto_sblocca(int $id, string $perche = 'a mano'): bool {
    newsletter_schema();
    $st = db()->prepare('SELECT email, stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c || !in_array($c['stato'], NL_BLOCCATI, true)) return false;
    db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'attivo', blocco_motivo = '', blocco_at = NULL, uscito_at = NULL, rimbalzato_at = NULL,
        errori_di_fila = 0, ultimo_errore = '', aggiornato = NOW() WHERE id = ?")->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_soppressi') . ' WHERE impronta = ?')->execute([newsletter_impronta((string)$c['email'])]);
    newsletter_storia_scrivi($id, 'sbloccato', 'Tolto dalla lista nera: ' . $perche);
    return true;
}

/**
 * 2.8.0: un soft bounce (errore temporaneo). errori_di_fila conta ogni tentativo, come prima (da 3 in su si segnala).
 * Regola di Brevo: 5 soft bounce di fila sulle campagne = lista nera. Un soft bounce è un messaggio che non è arrivato
 * (avviso del servizio di invio, oppure l'ultimo dei tre tentativi della coda: $messaggio), non ogni tentativo; "di fila"
 * vuol dire dopo l'ultima email partita bene o l'ultima uscita dalla lista nera.
 */
function newsletter_soft_bounce(int $id, string $errore, bool $messaggio = true): void {
    db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET errori_di_fila = errori_di_fila + 1, ultimo_errore = ? WHERE id = ?')->execute([mb_substr($errore, 0, 255), $id]);
    if (!$messaggio) return;
    newsletter_storia_scrivi($id, 'soft_bounce', $errore);
    $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$id]);
    if ($st->fetchColumn() !== 'attivo') return;
    $q = db()->prepare('SELECT GREATEST(
            COALESCE((SELECT MAX(inviata_at) FROM ' . tbl('newsletter_coda') . " WHERE iscritto_id = ? AND stato = 'inviata'), '1970-01-01'),
            COALESCE((SELECT MAX(creato) FROM " . tbl('newsletter_storia') . " WHERE contatto_id = ? AND tipo IN ('flusso_email', 'sbloccato', 'doi')), '1970-01-01'))");
    $q->execute([$id, $id]);
    $da = (string)$q->fetchColumn();
    $n = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_storia') . " WHERE contatto_id = ? AND tipo = 'soft_bounce' AND creato >= ?");
    $n->execute([$id, $da]);
    if ((int)$n->fetchColumn() >= NL_SOFT_BOUNCE_MAX) newsletter_contatto_blocca($id, 'softBounce', $errore);
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
    if ($st->rowCount() === 1) newsletter_storia_scrivi($iscritto, 'lista_aggiunto', newsletter_nome_lista($lista), $lista);
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
        WHERE i.stato = 'attivo' AND l.lista_id IN (" . implode(',', $liste) . ')' . newsletter_sql_uscite_lista())->fetchColumn();
}

/** 2.8.0: chi si è disiscritto da una lista non la riceve (condizione sulla riga l dell'appartenenza). */
function newsletter_sql_uscite_lista(): string {
    return ' AND NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_liste_uscite') . ' u WHERE u.contatto_id = l.iscritto_id AND u.lista_id = l.lista_id)';
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
 * Opzioni (2.0.0), tutte facoltative:
 *   lista     la lista in cui entra alla conferma (predefinita: la principale, il modulo della newsletter)
 *   form      l'id del form d'origine (lo ritrova newsletter_conferma_esito())
 *   cosa      per cosa si iscrive, nelle frasi dell'email ("la newsletter di...", il titolo del form)
 *   oggetto, testo (HTML), pulsante: per riscrivere l'email (vincono su tutto)
 *   email     (2.7.0) le parti dell'email scritte nel form (nl_email_conferma(), includes/newsletter-email.php)
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
    newsletter_storia_scrivi($id, $form ? 'form' : 'iscrizione', ($form ? 'Ha compilato un form' : 'Si è iscritto dal modulo della newsletter') . ': aspetta la conferma dell\'email', $form);
    // Una richiesta nuova per la stessa lista e lo stesso form prende il posto di quella di prima.
    db()->prepare('DELETE FROM ' . tbl('newsletter_conferme') . ' WHERE iscritto_id = ? AND lista_id = ? AND form_id = ?')->execute([$id, $lista, $form]);
    db()->prepare('INSERT INTO ' . tbl('newsletter_conferme') . ' (token, iscritto_id, lista_id, form_id, scade, creato) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $id, $lista, $form, $scade, $ora]);
    $link = MODULI_URL . NEWSLETTER_CONFERMA . '?t=' . $token;
    $principaleQui = $lista === $principale && !$form;
    $cosa = trim((string)($opz['cosa'] ?? '')) ?: ($principaleQui ? lcfirst(newsletter_nome()) : (newsletter_nome_lista($lista) ?: 'questa lista'));
    // 2.7.0: l'email si compone in includes/newsletter-email.php, con le parti scritte nel form (opzione 'email'), quelle
    // comuni di Newsletter > Impostazioni e, per quello che resta vuoto, i testi di sempre.
    [$oggetto, $html] = nl_email_conferma(['principale' => $principaleQui, 'cosa' => $cosa, 'nome' => (string)$d['nome'], 'link' => $link,
        'parti' => is_array($opz['email'] ?? null) ? $opz['email'] : [], 'opz' => $opz]);
    return posta_invia($email, $oggetto, $html, moduli_intestazioni(), 'conferma') ? '' : 'posta';
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
        // I link mandati prima della 2.0.0: il token stava nella riga dell'iscritto, e la lista era già scritta.
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
    // 2.8.0: DOUBLE_OPT-IN e OPT_IN, e la lista nera si toglie (è il contatto stesso che lo chiede, con il link).
    db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'attivo', consenso_at = NOW(), consenso_versione = ?, token_conferma = NULL, conferma_scade = NULL, uscito_at = NULL,
        rimbalzato_at = NULL, errori_di_fila = 0, ultimo_errore = '', blocco_motivo = '', blocco_at = NULL, doi_at = NOW(), opt_in = 1, aggiornato = NOW() WHERE id = ?")->execute([MODULI_CONSENSO_VERSIONE, $id]);
    newsletter_storia_scrivi($id, 'doi', 'Ha confermato l\'iscrizione dal link dell\'email' . ($lista ? ' (lista ' . newsletter_nome_lista($lista) . ')' : ''), $form);
    try {
        db()->prepare('INSERT INTO ' . tbl('newsletter_consensi') . " (contatto_id, tipo, testo, form_id, ip, creato) VALUES (?, 'doi', ?, ?, ?, NOW())")
            ->execute([$id, MODULI_CONSENSO_VERSIONE, $form, function_exists('moduli_ip') ? moduli_ip() : '']);
    } catch (Throwable $e) { error_log('newsletter_consensi: ' . $e->getMessage()); }
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

/**
 * Il link di uscita. 2.1.0: con $traccia (['c' => campagna] o ['f' => flusso, 'p' => passo]) porta anche da quale email si
 * esce, firmato (k): le statistiche contano le disiscrizioni per campagna (newsletter_uscita_registra).
 */
function newsletter_link_uscita(int $id, ?array $traccia = null): string {
    $url = MODULI_URL . NEWSLETTER_ESCI . '?i=' . $id . '&t=' . newsletter_token_uscita($id);
    if ($traccia) {
        $c = (int)($traccia['c'] ?? 0); $f = (int)($traccia['f'] ?? 0); $p = (int)($traccia['p'] ?? 0);
        if ($c || $f) $url .= "&c=$c&f=$f&p=$p&k=" . nl_firma($c, $f, $p, $id);
    }
    return $url;
}

/** Dopo un'uscita dal link: se il link porta la campagna (o il flusso) firmata, la disiscrizione si conta lì. */
function newsletter_uscita_registra(int $id, array $q): void {
    $c = (int)($q['c'] ?? 0); $f = (int)($q['f'] ?? 0); $p = (int)($q['p'] ?? 0);
    $k = is_string($q['k'] ?? null) ? $q['k'] : '';
    if (!$id || (!$c && !$f) || $k === '' || !hash_equals(nl_firma($c, $f, $p, $id), $k)) return;
    newsletter_evento_interno('uscita', $c, $f, $p, $id);
}

/**
 * 2.1.0: la persona ha premuto "è spam" (avviso del servizio di spedizione). Vale come uscita e soppressione: stato
 * 'spam', niente più email (campagne, flussi, coda), l'importazione la salta. Solo una sua nuova iscrizione confermata
 * la rimette dentro.
 */
function newsletter_segnala_spam(int $id): void {
    newsletter_schema();
    $st = db()->prepare('SELECT email FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$id]);
    $email = (string)$st->fetchColumn();
    if ($email === '') return;
    db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'spam', blocco_motivo = 'contactFlaggedAsSpam', blocco_at = NOW(), uscito_at = NOW(), aggiornato = NOW() WHERE id = ?")->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_coda') . " WHERE iscritto_id = ? AND stato = 'attesa'")->execute([$id]);
    newsletter_storia_scrivi($id, 'bloccato', NL_MOTIVI['contactFlaggedAsSpam'][0]);
    db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_soppressi') . ' (impronta, creato) VALUES (?, NOW())')->execute([newsletter_impronta($email)]);
}

/** L'iscritto del link di uscita, se il link è autentico. */
function newsletter_iscritto_da_uscita(string $i, string $t): ?array {
    if (!ctype_digit($i) || !hash_equals(newsletter_token_uscita((int)$i), $t)) return null;
    newsletter_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([(int)$i]);
    return $st->fetch() ?: null;
}

/** L'uscita (disiscrizione): lista nera email. 2.8.0: con il motivo di Brevo (dal link dell'email, se non si dice altro). */
function newsletter_esci(int $id, string $motivo = 'unsubscribedViaEmail'): void {
    if (!isset(NL_MOTIVI[$motivo]) || NL_MOTIVI[$motivo][1] !== 'uscito') $motivo = 'unsubscribedViaEmail';
    $st = db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'uscito', blocco_motivo = ?, blocco_at = NOW(), uscito_at = NOW(), aggiornato = NOW() WHERE id = ?");
    $st->execute([$motivo, $id]);
    if ($st->rowCount()) newsletter_storia_scrivi($id, 'bloccato', NL_MOTIVI[$motivo][0]);
    // Chi esce non riceve più niente, neanche le email già in coda.
    db()->prepare('DELETE FROM ' . tbl('newsletter_coda') . " WHERE iscritto_id = ? AND stato = 'attesa'")->execute([$id]);
}

/** Il server di posta ha rifiutato l'indirizzo per sempre (5xx): non riceve più niente, né campagne né flussi. */
function newsletter_rimbalzato(int $id, string $errore): void {
    $st = db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'rimbalzato', blocco_motivo = 'hardBounce', blocco_at = NOW(), rimbalzato_at = NOW(), ultimo_errore = ?, aggiornato = NOW() WHERE id = ? AND stato = 'attivo'");
    $st->execute([mb_substr($errore, 0, 255), $id]);
    if ($st->rowCount()) newsletter_storia_scrivi($id, 'bloccato', NL_MOTIVI['hardBounce'][0] . ($errore !== '' ? ': ' . $errore : ''));
    db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET stato = 'saltata' WHERE iscritto_id = ? AND stato = 'attesa'")->execute([$id]);
}

/**
 * Dopo una spedizione a un iscritto (campagna o flusso), secondo posta_esito(): a buon fine azzera gli errori di fila,
 * un rifiuto definitivo lo segna rimbalzato, un rifiuto temporaneo conta un errore di fila (da 3 in su si segnala nel
 * pannello, ma resta attivo). Gli errori del server (connessione, accesso) non sono colpa dell'indirizzo.
 */
function newsletter_esito_invio(int $id, bool $ultimoTentativo = false): void {
    $e = posta_esito();
    if ($e === 'ok') db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET errori_di_fila = 0, ultimo_errore = '' WHERE id = ? AND errori_di_fila > 0")->execute([$id]);
    elseif ($e === 'rimbalzo') newsletter_rimbalzato($id, posta_errore());
    elseif ($e === 'temporaneo') newsletter_soft_bounce($id, posta_errore(), $ultimoTentativo); // 2.8.0: al quinto messaggio di fila, lista nera
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
    // 2.8.0: attributi, storia, consensi e uscite dalle liste.
    foreach (['newsletter_valori', 'newsletter_storia', 'newsletter_consensi', 'newsletter_liste_uscite'] as $t) {
        db()->exec('DELETE FROM ' . tbl($t) . " WHERE contatto_id IN ($in)");
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
    // 2.9.0: importazioni in bozza di un giorno, scarti ed esportazioni di 7 giorni.
    if (function_exists('newsletter_importa_pulizia')) { try { newsletter_importa_pulizia(); } catch (Throwable $e) { $log[] = 'ERRORE pulizia dei file dei contatti: ' . $e->getMessage(); } }
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

// 2.9.0: newsletter_importa_csv() sta in includes/newsletter-importa.php (involucro del motore nuovo dell'importazione).

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
    $s = str_replace('{nome}', $html ? h($nome) : $nome, $s);
    // 2.8.0: i segnaposti degli attributi come Brevo, {{ contact.NOME }} (includes/newsletter-contatti.php).
    if (str_contains($s, '{{') && function_exists('newsletter_segnaposti')) $s = newsletter_segnaposti($s, $iscritto, $html);
    return $s;
}

/**
 * L'email completa per un iscritto (o per una prova: id 0).
 * 2.11.0 (includes/newsletter-campagne.php): codice HTML incollato (editor html), versione web e link delle preferenze
 * (se la campagna li chiede), indirizzo dell'azienda nel piede (se è scritto), UTM, Rispondi a e campo "A" della
 * campagna, testo semplice scritto a mano. Con i valori di partenza (le campagne di prima) l'HTML è uguale al byte.
 */
function newsletter_email(array $c, array $iscritto, ?array $traccia = null): array {
    $oggetto = newsletter_4byte_fuori(newsletter_personalizza($c['oggetto'], $iscritto, false));
    if ($traccia === null && !empty($c['id'])) $traccia = ['c' => (int)$c['id']];
    $id = (int)($iscritto['id'] ?? 0);
    $uscita = $id ? newsletter_link_uscita($id, $traccia) : MODULI_URL . NEWSLETTER_ESCI;
    $x = function_exists('nl_camp_extra') ? nl_camp_extra($c, $iscritto) : ['web' => '', 'pref' => '', 'pref_sempre' => '', 'indirizzo' => ''];
    if (($c['editor'] ?? 'semplice') === 'html' && function_exists('nl_camp_html')) {
        $html = nl_camp_html($c, $iscritto, $uscita, $x, $oggetto);
    } else {
        $corpo = newsletter_corpo_email((string)$c['corpo']);
        if (function_exists('nl_camp_link_speciali') && str_contains($corpo, '{{')) $corpo = nl_camp_link_speciali($corpo, $uscita, $x, true);
        $corpo = newsletter_personalizza($corpo, $iscritto, true);
        require_once __DIR__ . '/sicurezza-html.php';
        $pl = sicurezza_url_pulito((string)($c['pulsante_link'] ?? '')); // 2.2: niente javascript: e simili nel pulsante dell'email
        $btn = (($c['pulsante_testo'] ?? '') !== '' && $pl !== '')
            ? '<p style="margin:26px 0"><a href="' . h($pl) . '" target="_blank" style="display:inline-block;background:#7c3aed;color:#fff;padding:14px 26px;border-radius:10px;text-decoration:none;font-weight:700">' . h($c['pulsante_testo']) . '</a></p>'
            : '';
        // Il testo di anteprima: quello che i programmi di posta mostrano accanto all'oggetto.
        $anteprima = $c['anteprima'] !== '' ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">' . h(newsletter_4byte_fuori(newsletter_personalizza($c['anteprima'], $iscritto, false))) . str_repeat('&#8204;&nbsp;', 40) . '</div>' : '';
        $html = '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($oggetto) . '</title></head>'
            . '<body style="margin:0;background:#f4f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2330">' . $anteprima
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
            . ($x['web'] !== '' ? '<p style="margin:0 0 10px;font-size:12px;color:#888;text-align:center"><a href="' . h($x['web']) . '" style="color:#888">Vedi questa email nel browser</a></p>' : '')
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px">'
            . '<tr><td style="background:#060a17;color:#fff;padding:22px 30px;font-weight:700;letter-spacing:.06em">' . (string)cms_impostazione('newsletter_intestazione', mb_strtoupper(h((string)cms_impostazione('sito_nome', CMS_NOME)))) . '</td></tr>'
            . '<tr><td style="padding:30px">' . $corpo . $btn . '</td></tr>'
            . '<tr><td style="padding:18px 30px 26px;border-top:1px solid #eee;font-size:12px;line-height:1.6;color:#888">'
            . 'Ricevi questa email perché ti sei iscritto alla ' . h(lcfirst(newsletter_nome())) . ' di ' . h((string)cms_impostazione('newsletter_firma', cms_impostazione('sito_nome', CMS_NOME))) . '. '
            . '<a href="' . h($uscita) . '" style="color:#888">Esci dalla newsletter</a>, basta un clic.'
            . ($x['pref'] !== '' ? ' <a href="' . h($x['pref']) . '" style="color:#888">Aggiorna le tue preferenze</a>.' : '')
            . ($x['indirizzo'] !== '' ? '<br>' . str_replace("\n", '<br>', h($x['indirizzo'])) : '') . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
    if (!empty($c['utm']) && function_exists('nl_camp_utm')) $html = nl_camp_utm($html, $c);
    // Pixel di apertura e link tracciati: solo per le email vere, non per le prove.
    if ($id && $traccia) $html = newsletter_traccia($html, $traccia, $id);
    $cfg = newsletter_config();
    // 2.1.0: il mittente scelto (confermato) con il suo Reply-To; il riferimento che i servizi rimandano con rimbalzi e spam.
    $mitt = consegna_intestazioni_mittente((int)($c['mittente_id'] ?? 0));
    $rispondi = $cfg['rispondi_a'];
    if ($mitt !== '' && str_contains($mitt, 'Reply-To:')) $rispondi = '';
    // 2.11.0: il Rispondi a della campagna vince su quello del mittente e su quello delle impostazioni.
    $rc = trim((string)($c['rispondi_a'] ?? ''));
    if ($rc !== '' && filter_var($rc, FILTER_VALIDATE_EMAIL)) { $mitt = preg_replace('/^Reply-To:[^\r\n]*\r\n/m', '', $mitt); $rispondi = $rc; }
    $intest = moduli_intestazioni($rispondi) . $mitt
        . ($id ? "List-Unsubscribe: <$uscita>\r\nList-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n" : '')
        . ($id && $traccia ? 'X-Plume-Rif: ' . consegna_rif((int)($traccia['c'] ?? 0), (int)($traccia['f'] ?? 0), (int)($traccia['p'] ?? 0), $id) . "\r\n" : '');
    // 2.11.0: il campo "A" e il testo semplice viaggiano in intestazioni interne che posta.php toglie (posta_interne()).
    if (function_exists('posta_interne')) {
        $a = trim((string)($c['campo_a'] ?? ''));
        if ($a !== '') {
            $a = trim(preg_replace('/\s+/u', ' ', newsletter_4byte_fuori(newsletter_personalizza($a, $iscritto, false))));
            if ($a !== '') $intest .= 'X-Plume-A: ' . base64_encode(mb_substr($a, 0, 150)) . "\r\n";
        }
        $t = function_exists('nl_camp_testo') ? nl_camp_testo($c, $iscritto, $uscita, $x) : null;
        if ($t !== null) $intest .= 'X-Plume-Testo: ' . base64_encode($t) . "\r\n";
    }
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
    $r = ['in_attesa' => 0, 'attivo' => 0, 'uscito' => 0, 'rimbalzato' => 0, 'spam' => 0, 'bloccato' => 0];
    foreach (db()->query('SELECT stato, COUNT(*) n FROM ' . tbl('newsletter_iscritti') . ' GROUP BY stato') as $x) $r[$x['stato']] = (int)$x['n'];
    $r['lista_nera'] = $r['uscito'] + $r['rimbalzato'] + $r['spam'] + $r['bloccato']; // 2.8.0
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
        // 2.11.0: liste e segmenti, esclusioni, non coinvolti, figlie, lotti (includes/newsletter-campagne.php).
        if (function_exists('newsletter_campagna_avvia')) { $log[] = newsletter_campagna_avvia($c); continue; }
        $liste = newsletter_liste_campagna($c);
        if (!$liste) {
            // Le sue liste sono state cancellate: torna bozza, invece di partire verso nessuno o verso tutti.
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'bozza', invio_at = NULL WHERE id = ?")->execute([$id]);
            $log[] = "campagna $id senza liste: tornata bozza";
            continue;
        }
        // Chi sta in più liste scelte riceve l'email una volta sola (chiave unica campagna + iscritto).
        db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_coda') . ' (campagna_id, iscritto_id) SELECT DISTINCT ?, i.id FROM ' . tbl('newsletter_iscritti') . ' i
            JOIN ' . tbl('newsletter_iscritti_liste') . " l ON l.iscritto_id = i.id WHERE i.stato = 'attivo' AND l.lista_id IN (" . implode(',', $liste) . ')' . newsletter_sql_uscite_lista())->execute([$id]);
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
        // 2.11.0: le colonne dei lotti nella coda (dopo: l'ora da cui un lotto può partire).
        $lotti = function_exists('newsletter_campagne_schema');
        if ($lotti) newsletter_campagne_schema();
        $log = newsletter_avvia_programmate();
        // 2.0.0: il limite conta tutte le email del CMS (registro della posta): conferme, avvisi, flussi, campagne.
        // Le campagne della coda restano contate anche se il registro è appena nato (il primo giro dopo l'aggiornamento).
        $cfg = newsletter_config();
        $ultimaOra = max(posta_inviate(3600), (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_coda') . " WHERE inviata_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn());
        $posti = min($massimo ?? NEWSLETTER_PER_GIRO, max(0, (int)$cfg['per_ora'] - $ultimaOra));
        if ($posti === 0) return array_merge($log, ['limite orario raggiunto']);
        // 2.1.0: con il riscaldamento acceso il limite al giorno è quello di oggi (sale da solo), mai sopra quello scritto.
        $alGiorno = (int)$cfg['per_giorno'];
        $caldo = consegna_limite_riscaldamento();
        if ($caldo > 0) $alGiorno = $alGiorno > 0 ? min($alGiorno, $caldo) : $caldo;
        if ($alGiorno > 0) {
            $posti = min($posti, max(0, $alGiorno - posta_inviate(86400)));
            if ($posti === 0) return array_merge($log, [$caldo > 0 && $caldo === $alGiorno ? "limite del riscaldamento raggiunto ($caldo email in 24 ore)" : 'limite giornaliero raggiunto']);
        }
        $righe = db()->query('SELECT q.id, q.campagna_id, q.tentativi, i.id AS iscritto_id, i.email, i.nome, i.cognome FROM ' . tbl('newsletter_coda') . ' q
            JOIN ' . tbl('newsletter_campagne') . ' c ON c.id = q.campagna_id AND c.stato = \'in_invio\'
            JOIN ' . tbl('newsletter_iscritti') . " i ON i.id = q.iscritto_id AND i.stato = 'attivo'
            WHERE q.stato = 'attesa'" . ($lotti ? ' AND (q.dopo IS NULL OR q.dopo <= NOW())' : '') . ' ORDER BY q.id LIMIT ' . (int)$posti)->fetchAll();
        $campagne = [];
        $ok = 0; $ko = 0; $rimbalzi = 0;
        foreach ($righe as $r) {
            $c = $campagne[$r['campagna_id']] ??= newsletter_campagna((int)$r['campagna_id']);
            [$oggetto, $html, $intest] = newsletter_email($c, ['id' => $r['iscritto_id'], 'nome' => $r['nome'], 'email' => $r['email']]);
            $partita = posta_invia($r['email'], $oggetto, $html, $intest, 'campagna');
            newsletter_esito_invio((int)$r['iscritto_id'], (int)$r['tentativi'] + 1 >= 3); // 2.8.0: il terzo tentativo è il soft bounce del messaggio
            if ($partita) {
                db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET stato = 'inviata', inviata_at = NOW() WHERE id = ?")->execute([$r['id']]);
                db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET inviate = inviate + 1 WHERE id = ?')->execute([$r['campagna_id']]);
                $ok++;
            } elseif (posta_esito() === 'rimbalzo') {
                // L'indirizzo non esiste: niente altri tentativi (il server di posta funziona, si va avanti).
                db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET tentativi = tentativi + 1, errore = ?, stato = 'errore' WHERE id = ?")->execute([mb_substr(posta_errore(), 0, 255), $r['id']]);
                db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET errori = errori + 1 WHERE id = ?')->execute([$r['campagna_id']]);
                newsletter_evento_interno('rimbalzo', (int)$r['campagna_id'], 0, 0, (int)$r['iscritto_id']);
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
require_once __DIR__ . '/newsletter-contatti.php'; // 2.8.0: contatti, attributi, cartelle (come Brevo)
require_once __DIR__ . '/newsletter-importa.php'; // 2.9.0: importazione, esportazione, lista dei test (come Brevo)
require_once __DIR__ . '/newsletter-segmenti.php'; // 2.10.0: segmenti, filtri e viste (come Brevo)
require_once __DIR__ . '/newsletter-campagne.php'; // 2.11.0: campagne a riepilogo, disiscrizione con il questionario, centro preferenze
