<?php
/**
 * 2.16.0, fase 9 del piano Brevo (docs-sviluppo/brevo-confronto.md, capitoli 1.7, 1.9, 3.5, 3.8): il consenso e le pagine
 * pubbliche che non sono i form.
 *
 *   - gruppi di consenso (Brevo: Impostazioni > Contatti > Gruppi di consenso), spenti di partenza (decisione di Danilo):
 *     <prefisso>newsletter_gruppi_consenso (nome, descrizione, ingresso automatic|manual) e
 *     <prefisso>newsletter_contatti_gruppi (contatto, gruppo, iscritto 1|0). Con "automatic" il contatto è dentro finché
 *     non esce (riga a 0); con "manual" è dentro solo con una riga a 1. Accesi, ogni campagna ne sceglie uno;
 *   - consenso al tracciamento per contatto (colonna newsletter_iscritti.tracciamento, dalla 2.8.0, attributo di sistema
 *     TRACCIAMENTO dalla 2.16.0): senza consenso niente pixel né link tracciati (newsletter_traccia), nessun evento segnato
 *     dai link vecchi (newsletter_evento) e i segmenti su aperture e clic non contano il contatto;
 *   - pagine di disiscrizione (<prefisso>newsletter_pagine_uscita): oltre alla predefinita (i testi di Newsletter >
 *     Impostazioni, come prima) quelle create in Newsletter > Pagine e consenso, scelte nella campagna
 *     (newsletter_campagne.pagina_uscita_id). Ogni pagina può offrire l'uscita da una sola lista (newsletter_liste_uscite);
 *   - registro del consenso (newsletter_consensi, dalla 2.8.0): una riga per ogni form compilato con il testo della
 *     dichiarazione e della casella, la data e l'indirizzo IP.
 *
 * Chi usa queste funzioni da un altro file fa require_once di questo file (opcache: un file nuovo non è mai vecchio).
 */

require_once __DIR__ . '/newsletter.php';

const NL_GRUPPI_INGRESSI = ['automatic' => 'Automatico: tutti i contatti sono dentro finché non escono', 'manual' => 'Manuale: entra solo chi lo sceglie'];
const NL_GRUPPI_MASSIMO = 50;
const NL_PAGINE_USCITA_MASSIMO = 30;

function nl_consenso_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    newsletter_schema();
    $tabelle = [
        'newsletter_gruppi_consenso' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_gruppi_consenso') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `descrizione` VARCHAR(500) NOT NULL DEFAULT '',
            `ingresso` VARCHAR(10) NOT NULL DEFAULT 'automatic',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `nome` (`nome`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_contatti_gruppi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_contatti_gruppi') . " (
            `contatto_id` INT(11) NOT NULL,
            `gruppo_id` INT(11) NOT NULL,
            `iscritto` TINYINT(1) NOT NULL DEFAULT 1,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`contatto_id`, `gruppo_id`), KEY `gruppo` (`gruppo_id`, `iscritto`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // Le pagine di disiscrizione in più: i testi in JSON con le stesse chiavi di newsletter_pagine_testi().
        'newsletter_pagine_uscita' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_pagine_uscita') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `testi` TEXT,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `nome` (`nome`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME . 'campagne-gruppo-pagina conferme-altre tracciamento-attributo');
    if (get_setting('nl_consenso_impronta', '') === $impronta) return;
    foreach ($tabelle as $sql) db()->exec($sql);
    // Le campagne: la pagina di disiscrizione e il gruppo di consenso (0 = la predefinita, nessun gruppo: come prima).
    if (function_exists('newsletter_campagne_schema')) newsletter_campagne_schema();
    $t = tbl('newsletter_campagne');
    $col = db()->query('SHOW COLUMNS FROM ' . $t)->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('pagina_uscita_id', $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `pagina_uscita_id` INT(11) NOT NULL DEFAULT 0");
    if (!in_array('gruppo_id', $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `gruppo_id` INT(11) NOT NULL DEFAULT 0");
    // Le liste in più di un form con la doppia conferma: entrano tutte quando il contatto conferma.
    $c = tbl('newsletter_conferme');
    $col = db()->query('SHOW COLUMNS FROM ' . $c)->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('altre_liste', $col, true)) db()->exec("ALTER TABLE $c ADD COLUMN `altre_liste` VARCHAR(255) NOT NULL DEFAULT ''");
    // Il consenso al tracciamento come attributo di sistema: si vede nella scheda del contatto, nei segmenti, nell'importazione.
    db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_attributi') . " (nome, etichetta, categoria, tipo, sistema, colonna, ordine, creato) VALUES ('TRACCIAMENTO', 'Consenso al tracciamento', 'normal', 'boolean', 1, 'tracciamento', 9, NOW())")->execute();
    save_setting('nl_consenso_impronta', $impronta);
}

// ------------------------------------------------------------
// Gruppi di consenso
// ------------------------------------------------------------

/** I gruppi di consenso sono accesi (Newsletter > Pagine e consenso). Spenti di partenza. */
function nl_gruppi_attivi(): bool {
    return get_setting('newsletter_gruppi_attivi', '0') === '1';
}

/** Tutti i gruppi, con quanti contatti iscritti alla newsletter sono dentro. [id => riga] */
function nl_gruppi(): array {
    nl_consenso_schema();
    $out = [];
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_gruppi_consenso') . ' ORDER BY nome')->fetchAll() as $g) {
        $g['nome'] = newsletter_4byte_fuori((string)$g['nome']);
        $g['descrizione'] = newsletter_4byte_fuori((string)$g['descrizione']);
        $out[(int)$g['id']] = $g;
    }
    return $out;
}

function nl_gruppo(int $id): ?array {
    return nl_gruppi()[$id] ?? null;
}

/** Crea o cambia un gruppo. Ritorna l'id, oppure il messaggio d'errore. */
function nl_gruppo_salva(int $id, string $nome, string $descr, string $ingresso) {
    nl_consenso_schema();
    $nome = newsletter_4byte_dentro(mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($nome))), 0, 100));
    $descr = newsletter_4byte_dentro(mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($descr))), 0, 500));
    if ($nome === '') return 'Scrivi il nome del gruppo: è quello che il contatto legge nel centro preferenze.';
    if (!isset(NL_GRUPPI_INGRESSI[$ingresso])) $ingresso = 'automatic';
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_gruppi_consenso') . ' WHERE nome = ? AND id <> ?');
    $st->execute([$nome, $id]);
    if ($st->fetchColumn()) return 'C\'è già un gruppo con questo nome: scegline un altro.';
    if ($id) {
        db()->prepare('UPDATE ' . tbl('newsletter_gruppi_consenso') . ' SET nome = ?, descrizione = ?, ingresso = ? WHERE id = ?')->execute([$nome, $descr, $ingresso, $id]);
        return $id;
    }
    if (count(nl_gruppi()) >= NL_GRUPPI_MASSIMO) return 'Hai già ' . NL_GRUPPI_MASSIMO . ' gruppi: togline uno prima di crearne un altro.';
    db()->prepare('INSERT INTO ' . tbl('newsletter_gruppi_consenso') . ' (nome, descrizione, ingresso, creato) VALUES (?, ?, ?, NOW())')->execute([$nome, $descr, $ingresso]);
    return (int)db()->lastInsertId();
}

function nl_gruppo_elimina(int $id): bool {
    nl_consenso_schema();
    $st = db()->prepare('DELETE FROM ' . tbl('newsletter_gruppi_consenso') . ' WHERE id = ?');
    $st->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_contatti_gruppi') . ' WHERE gruppo_id = ?')->execute([$id]);
    db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET gruppo_id = 0 WHERE gruppo_id = ? AND stato IN ('bozza', 'programmata')")->execute([$id]);
    return $st->rowCount() > 0;
}

/** In quali gruppi è dentro il contatto: [id gruppo => true|false], per tutti i gruppi. */
function nl_contatto_gruppi(int $id): array {
    nl_consenso_schema();
    $righe = [];
    $st = db()->prepare('SELECT gruppo_id, iscritto FROM ' . tbl('newsletter_contatti_gruppi') . ' WHERE contatto_id = ?');
    $st->execute([$id]);
    foreach ($st->fetchAll() as $r) $righe[(int)$r['gruppo_id']] = (int)$r['iscritto'];
    $out = [];
    foreach (nl_gruppi() as $gid => $g) $out[$gid] = isset($righe[$gid]) ? $righe[$gid] === 1 : $g['ingresso'] === 'automatic';
    return $out;
}

/** Il contatto entra (o esce) da un gruppo. Scrive la storia solo quando cambia qualcosa. */
function nl_contatto_gruppo_imposta(int $id, int $gruppo, bool $dentro, string $da = 'centro preferenze'): void {
    $g = nl_gruppo($gruppo);
    if (!$g || !$id) return;
    $prima = nl_contatto_gruppi($id)[$gruppo] ?? null;
    db()->prepare('INSERT INTO ' . tbl('newsletter_contatti_gruppi') . ' (contatto_id, gruppo_id, iscritto, aggiornato) VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE iscritto = VALUES(iscritto), aggiornato = VALUES(aggiornato)')->execute([$id, $gruppo, $dentro ? 1 : 0]);
    if ($prima !== $dentro) {
        newsletter_storia_scrivi($id, 'gruppo', ($dentro ? 'Entra nel gruppo di consenso ' : 'Esce dal gruppo di consenso ') . $g['nome'] . ' (' . $da . ')', $gruppo);
        nl_consenso_registra($id, 'gruppo', ($dentro ? 'Sì: ' : 'No: ') . $g['nome'], 0);
    }
}

/** La condizione SQL "il contatto i.id è nel gruppo" (per le campagne). '' se il gruppo non c'è. */
function nl_sql_gruppo(int $gruppo): string {
    $g = nl_gruppo($gruppo);
    if (!$g) return '';
    $t = tbl('newsletter_contatti_gruppi');
    return $g['ingresso'] === 'manual'
        ? "EXISTS (SELECT 1 FROM $t cg WHERE cg.contatto_id = i.id AND cg.gruppo_id = $gruppo AND cg.iscritto = 1)"
        : "NOT EXISTS (SELECT 1 FROM $t cg WHERE cg.contatto_id = i.id AND cg.gruppo_id = $gruppo AND cg.iscritto = 0)";
}

/** Quanti contatti iscritti sono in ogni gruppo: [id => n]. */
function nl_gruppi_conteggi(): array {
    $out = [];
    foreach (nl_gruppi() as $gid => $g) {
        $out[$gid] = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i WHERE i.stato = 'attivo' AND " . nl_sql_gruppo($gid))->fetchColumn();
    }
    return $out;
}

// ------------------------------------------------------------
// Consenso al tracciamento e registro del consenso
// ------------------------------------------------------------

/** Il contatto acconsente al tracciamento di aperture e clic (di partenza sì, come prima della 2.16.0). */
function nl_tracciamento_ok(int $id): bool {
    if (!$id) return false;
    $st = db()->prepare('SELECT tracciamento FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$id]);
    $v = $st->fetchColumn();
    return $v === false || (int)$v === 1;
}

function nl_tracciamento_imposta(int $id, bool $si, string $da = 'centro preferenze'): void {
    if (!$id) return;
    $st = db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET tracciamento = ?, aggiornato = NOW() WHERE id = ? AND tracciamento <> ?');
    $st->execute([$si ? 1 : 0, $id, $si ? 1 : 0]);
    if ($st->rowCount()) {
        newsletter_storia_scrivi($id, 'attributo', ($si ? 'Acconsente' : 'Non acconsente più') . ' al tracciamento di aperture e clic (' . $da . ')');
        nl_consenso_registra($id, 'tracciamento', $si ? 'Sì al tracciamento di aperture e clic' : 'No al tracciamento di aperture e clic', 0);
    }
}

/**
 * Una riga nel registro del consenso: chi, che cosa (opt_in, doi, preferenze, gruppo, tracciamento), il testo esatto
 * che ha visto, da quale form, l'indirizzo IP e l'ora. Non ferma mai chi la chiama.
 */
function nl_consenso_registra(int $id, string $tipo, string $testo, int $form): void {
    if (!$id) return;
    try {
        db()->prepare('INSERT INTO ' . tbl('newsletter_consensi') . ' (contatto_id, tipo, testo, form_id, ip, creato) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([$id, mb_substr(preg_replace('/[^a-z_]/', '', $tipo), 0, 12), newsletter_4byte_dentro(mb_substr($testo, 0, 5000)), $form, function_exists('moduli_ip') ? moduli_ip() : '']);
    } catch (Throwable $e) { error_log('newsletter_consensi: ' . $e->getMessage()); }
}

/** Il registro del consenso di un contatto, dal più recente. */
function nl_consensi_di(int $id, int $quanti = 50): array {
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_consensi') . ' WHERE contatto_id = ? ORDER BY id DESC LIMIT ' . max(1, min(500, $quanti)));
    $st->execute([$id]);
    return $st->fetchAll();
}

// ------------------------------------------------------------
// Pagine di disiscrizione
// ------------------------------------------------------------

/** Le pagine di disiscrizione create (la predefinita, id 0, sono i testi di Newsletter > Impostazioni). [id => riga] */
function nl_pagine_uscita(): array {
    nl_consenso_schema();
    $out = [];
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_pagine_uscita') . ' ORDER BY nome')->fetchAll() as $p) {
        $p['nome'] = newsletter_4byte_fuori((string)$p['nome']);
        $out[(int)$p['id']] = $p;
    }
    return $out;
}

/** I testi di una pagina: quelli scritti, poi quelli della predefinita, poi quelli di partenza. */
function nl_pagina_uscita_testi(int $id): array {
    $base = newsletter_pagine_testi();
    if (!$id) return $base;
    $p = nl_pagine_uscita()[$id] ?? null;
    $s = $p ? json_decode((string)$p['testi'], true) : null;
    if (!is_array($s)) return $base;
    $out = $base;
    foreach ($base as $k => $v) {
        if (in_array($k, ['questionario', 'solo_lista'], true)) { if (array_key_exists($k, $s)) $out[$k] = (bool)$s[$k]; continue; }
        if ($k === 'attributi') continue;
        if ($k === 'motivi') { foreach ($v as $mk => $mv) if (trim((string)($s['motivi'][$mk] ?? '')) !== '') $out[$k][$mk] = newsletter_4byte_fuori((string)$s['motivi'][$mk]); continue; }
        if (trim((string)($s[$k] ?? '')) !== '') $out[$k] = newsletter_4byte_fuori((string)$s[$k]);
    }
    return $out;
}

/** Le chiavi di testo di una pagina di disiscrizione che si scrivono nel pannello. */
const NL_PAGINA_USCITA_CAMPI = ['uscita_titolo' => 200, 'uscita_testo' => 1000, 'fatto_titolo' => 200, 'fatto_testo' => 1000, 'questionario_titolo' => 200, 'grazie_testo' => 500];

/** Crea o cambia una pagina. $d: nome, testi (le chiavi di NL_PAGINA_USCITA_CAMPI), questionario, solo_lista, motivi. */
function nl_pagina_uscita_salva(int $id, array $d) {
    nl_consenso_schema();
    $nome = newsletter_4byte_dentro(mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags(is_scalar($d['nome'] ?? null) ? (string)$d['nome'] : ''))), 0, 100));
    if ($nome === '') return 'Scrivi il nome della pagina: serve a te per sceglierla nella campagna, chi legge non lo vede.';
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_pagine_uscita') . ' WHERE nome = ? AND id <> ?');
    $st->execute([$nome, $id]);
    if ($st->fetchColumn()) return 'C\'è già una pagina con questo nome.';
    $t = ['questionario' => !empty($d['questionario']), 'solo_lista' => !empty($d['solo_lista']), 'motivi' => []];
    $sc = fn($x) => is_scalar($x) ? (string)$x : '';
    foreach (NL_PAGINA_USCITA_CAMPI as $k => $max) $t[$k] = newsletter_4byte_dentro(mb_substr(trim(str_replace("\r", '', strip_tags($sc($d[$k] ?? '')))), 0, $max));
    foreach (array_keys(NL_USCITA_MOTIVI) as $mk) {
        $v = trim(strip_tags(is_array($d['motivi'] ?? null) ? $sc($d['motivi'][$mk] ?? '') : ''));
        if ($v !== '') $t['motivi'][$mk] = newsletter_4byte_dentro(mb_substr($v, 0, 150));
    }
    $j = json_encode($t, JSON_UNESCAPED_UNICODE);
    if ($id) {
        db()->prepare('UPDATE ' . tbl('newsletter_pagine_uscita') . ' SET nome = ?, testi = ?, aggiornato = NOW() WHERE id = ?')->execute([$nome, $j, $id]);
        return $id;
    }
    if (count(nl_pagine_uscita()) >= NL_PAGINE_USCITA_MASSIMO) return 'Hai già ' . NL_PAGINE_USCITA_MASSIMO . ' pagine di disiscrizione: togline una prima.';
    db()->prepare('INSERT INTO ' . tbl('newsletter_pagine_uscita') . ' (nome, testi, creato, aggiornato) VALUES (?, ?, NOW(), NOW())')->execute([$nome, $j]);
    return (int)db()->lastInsertId();
}

function nl_pagina_uscita_elimina(int $id): bool {
    nl_consenso_schema();
    $st = db()->prepare('DELETE FROM ' . tbl('newsletter_pagine_uscita') . ' WHERE id = ?');
    $st->execute([$id]);
    db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET pagina_uscita_id = 0 WHERE pagina_uscita_id = ?')->execute([$id]);
    return $st->rowCount() > 0;
}

/** La pagina di disiscrizione di una campagna (0 = la predefinita, anche se la pagina scelta non c'è più). */
function nl_pagina_uscita_di_campagna(int $campagna): int {
    if (!$campagna) return 0;
    nl_consenso_schema();
    $st = db()->prepare('SELECT pagina_uscita_id FROM ' . tbl('newsletter_campagne') . ' WHERE id = ?');
    $st->execute([$campagna]);
    $p = (int)$st->fetchColumn();
    return $p && isset(nl_pagine_uscita()[$p]) ? $p : 0;
}

/** Le liste di una campagna in cui il contatto è dentro e da cui non è già uscito: [id => nome]. */
function nl_liste_campagna_contatto(int $campagna, int $id): array {
    $c = $campagna ? newsletter_campagna($campagna) : null;
    if (!$c || !$id) return [];
    $liste = array_values(array_filter(array_map('intval', explode(',', (string)$c['liste']))));
    if (!$liste) return [];
    $st = db()->prepare('SELECT x.id, x.nome FROM ' . tbl('newsletter_iscritti_liste') . ' l JOIN ' . tbl('newsletter_liste') . ' x ON x.id = l.lista_id
        WHERE l.iscritto_id = ? AND l.lista_id IN (' . implode(',', $liste) . ')' . newsletter_sql_uscite_lista() . ' ORDER BY x.nome');
    $st->execute([$id]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['id']] = newsletter_4byte_fuori((string)$r['nome']);
    return $out;
}

/** Esce da una lista sola (non riceve più le sue campagne, il resto resta). Ritorna true se è cambiato. */
function nl_esci_da_lista(int $id, int $lista, string $da = 'pagina di disiscrizione'): bool {
    $st = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_liste_uscite') . ' (contatto_id, lista_id, quando) VALUES (?, ?, NOW())');
    $st->execute([$id, $lista]);
    if (!$st->rowCount()) return false;
    newsletter_storia_scrivi($id, 'lista_uscito', 'Dalla ' . $da . ': non riceve più la lista ' . newsletter_nome_lista($lista), $lista);
    newsletter_flussi_innesco('lista_tolto', $lista, $id);
    return true;
}
