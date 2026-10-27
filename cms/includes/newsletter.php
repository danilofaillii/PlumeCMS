<?php
/**
 * Newsletter FuoriDalCoso, al posto di Brevo.
 *
 * Iscritti: modulo con doppia conferma dell'email (stesso captcha dei moduli), oppure consenso dato nel
 * modulo dell'ebook (l'email lì è già confermata), oppure importazione dei contatti esportati da Brevo.
 * Campagne: scritte nell'editor del pannello, inviate da una coda a ritmo controllato da cron-newsletter.php
 * (i server condivisi limitano le email per ora). Ogni email ha l'uscita in un clic, anche dall'intestazione
 * List-Unsubscribe che leggono Gmail e Outlook.
 */

require_once __DIR__ . '/moduli.php';

define('NEWSLETTER_PAGINA', (string)cms_impostazione('newsletter_pagina', '/newsletter/'));
const NEWSLETTER_GRAZIE = '/newsletter-grazie/';

/** "Newsletter FuoriDalCoso", oppure "Newsletter": il nome della newsletter del sito (lista principale, email). */
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
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME);
    if (get_setting('newsletter_schema_impronta', '') !== $impronta) {
        foreach ($tabelle as $sql) db()->exec($sql);
        // Colonna arrivata dopo la prima versione (23 ottobre, sera): le liste della campagna.
        $c = db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . DB_PREFIX . "newsletter_campagne' AND COLUMN_NAME = 'liste'")->fetchColumn();
        if (!$c) db()->exec('ALTER TABLE ' . tbl('newsletter_campagne') . " ADD COLUMN `liste` VARCHAR(255) NOT NULL DEFAULT '' AFTER `errori`");
        $fatto = true;
        // Le due liste di partenza. Chi era iscritto prima delle liste entra nella principale.
        $principale = newsletter_lista_di_sistema('principale', newsletter_nome(), 'Chi si iscrive dal sito, e gli iscritti di prima delle liste');
        newsletter_lista_di_sistema('ebook', 'Lettori ebook', "Chi ha chiesto l'ebook e ha dato il consenso alla newsletter");
        db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) SELECT i.id, ?, NOW() FROM ' . tbl('newsletter_iscritti') . ' i
            WHERE NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = i.id)')->execute([$principale]);
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET liste = ? WHERE liste = ''")->execute([(string)$principale]);
        save_setting('newsletter_schema_impronta', $impronta);
    }
    $fatto = true;
}

// ------------------------------------------------------------
// Liste
// ------------------------------------------------------------

/** Una lista di sistema (principale, ebook): l'id sta nelle impostazioni, il nome si può cambiare dal pannello. */
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

function newsletter_lista_ebook(): int {
    newsletter_schema();
    return newsletter_lista_di_sistema('ebook', 'Lettori ebook', '');
}

/** Tutte le liste, con quanti iscritti attivi e quanti in tutto. */
function newsletter_liste(): array {
    newsletter_schema();
    return db()->query('SELECT l.*, COUNT(il.iscritto_id) AS totali, COALESCE(SUM(i.stato = \'attivo\'), 0) AS attivi
        FROM ' . tbl('newsletter_liste') . ' l LEFT JOIN ' . tbl('newsletter_iscritti_liste') . ' il ON il.lista_id = l.id
        LEFT JOIN ' . tbl('newsletter_iscritti') . ' i ON i.id = il.iscritto_id GROUP BY l.id ORDER BY l.id')->fetchAll();
}

function newsletter_in_lista(int $iscritto, int $lista): void {
    $st = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) VALUES (?, ?, NOW())');
    $st->execute([$iscritto, $lista]);
    // Entrato adesso in una lista, e già attivo: i flussi di quella lista partono.
    if ($st->rowCount() === 1) {
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

function newsletter_config(): array {
    $c = json_decode((string)get_setting('newsletter_config', ''), true);
    return (is_array($c) ? $c : []) + ['per_ora' => 150, 'rispondi_a' => ''];
}

// ------------------------------------------------------------
// Iscritti
// ------------------------------------------------------------

/** Modulo della newsletter: salva in attesa e manda l'email di conferma. Ritorna '' oppure il codice d'errore. */
function newsletter_richiesta(array $d): string {
    newsletter_schema();
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . ' WHERE ip = ? AND aggiornato > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $st->execute([moduli_ip()]);
    if ((int)$st->fetchColumn() >= MODULI_LIMITE_ORA) return 'troppi';
    $token = bin2hex(random_bytes(32));
    $ora = moduli_adesso();
    // Chi è già attivo resta attivo: riceve comunque l'email, così vede che è dentro.
    db()->prepare('INSERT INTO ' . tbl('newsletter_iscritti') . " (email, nome, cognome, stato, fonte, consenso_versione, token_conferma, conferma_scade, ip, creato, aggiornato)
        VALUES (:e, :n, :c, 'in_attesa', 'modulo', :v, :t, DATE_ADD(NOW(), INTERVAL " . MODULI_CONFERMA_GIORNI . " DAY), :ip, :o, :o2)
        ON DUPLICATE KEY UPDATE nome = VALUES(nome), cognome = VALUES(cognome), stato = IF(stato = 'attivo', 'attivo', 'in_attesa'),
            consenso_versione = VALUES(consenso_versione), token_conferma = VALUES(token_conferma), conferma_scade = VALUES(conferma_scade), ip = VALUES(ip), aggiornato = VALUES(aggiornato)")
        ->execute([':e' => $d['email'], ':n' => $d['nome'], ':c' => $d['cognome'], ':v' => MODULI_CONSENSO_VERSIONE, ':t' => hash('sha256', $token), ':ip' => moduli_ip(), ':o' => $ora, ':o2' => $ora]);
    newsletter_in_lista(newsletter_id_da_email($d['email']), newsletter_lista_principale());
    $link = MODULI_URL . NEWSLETTER_CONFERMA . '?t=' . $token;
    $corpo = '<p>Ciao ' . h($d['nome']) . ',</p><p>manca un clic. Premi il pulsante e confermi l&#039;iscrizione alla ' . h(lcfirst(newsletter_nome())) . '.</p><p>Il link vale ' . MODULI_CONFERMA_GIORNI . ' giorni.</p>';
    $html = str_replace(moduli_firma_html(), moduli_firma_html() . '<p style="font-size:12px;color:#888">Se la richiesta l&#039;ha fatta qualcun altro, ignora questa email: senza conferma l&#039;indirizzo resta fuori dalla newsletter.</p>',
        moduli_email_html('Conferma l&#039;iscrizione', $corpo, 'Confermo l&#039;iscrizione', $link));
    return posta_invia($d['email'], "Conferma l'iscrizione a " . (trim((string)cms_impostazione('newsletter_nome', '')) ?: 'questa newsletter'), $html, moduli_intestazioni()) ? '' : 'posta';
}

function newsletter_conferma(string $token): bool {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return false;
    newsletter_schema();
    // Prima si trova la persona dal suo token, poi si conferma per id: così i flussi partono per lei e non
    // per l'ultima riga toccata nello stesso minuto.
    $i = db()->prepare('SELECT id FROM ' . tbl('newsletter_iscritti') . ' WHERE token_conferma = ? AND conferma_scade > NOW() LIMIT 1');
    $i->execute([hash('sha256', $token)]);
    $id = (int)$i->fetchColumn();
    if (!$id) return false;
    $st = db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'attivo', consenso_at = NOW(), token_conferma = NULL, conferma_scade = NULL, uscito_at = NULL, aggiornato = NOW() WHERE id = ?");
    $st->execute([$id]);
    if ($st->rowCount() !== 1) return false;
    // Flussi che partono all'iscrizione, e per ogni lista in cui la persona sta.
    if ($id) {
        newsletter_flussi_innesco('iscrizione', 0, $id);
        $l = db()->prepare('SELECT lista_id FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ?');
        $l->execute([$id]);
        foreach ($l->fetchAll(PDO::FETCH_COLUMN) as $lista) newsletter_flussi_innesco('lista', (int)$lista, $id);
    }
    return true;
}

/** Consenso dato nel modulo dell'ebook: l'email è confermata con il link dell'ebook, quindi l'iscritto è attivo. */
function newsletter_da_ebook(array $r): void {
    newsletter_schema();
    $ora = moduli_adesso();
    db()->prepare('INSERT INTO ' . tbl('newsletter_iscritti') . " (email, nome, cognome, stato, fonte, consenso_versione, consenso_at, ip, creato, aggiornato)
        VALUES (?, ?, ?, 'attivo', 'ebook', ?, NOW(), ?, ?, ?)
        ON DUPLICATE KEY UPDATE stato = 'attivo', consenso_versione = VALUES(consenso_versione), consenso_at = NOW(), uscito_at = NULL, aggiornato = VALUES(aggiornato)")
        ->execute([$r['email'], $r['nome'], $r['cognome'], $r['consenso_versione'], $r['ip'], $ora, $ora]);
    $id = newsletter_id_da_email($r['email']);
    newsletter_in_lista($id, newsletter_lista_principale());
    newsletter_in_lista($id, newsletter_lista_ebook());
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

/**
 * Importazione dei contatti esportati da Brevo (CSV). Colonne riconosciute: EMAIL, NOME/FIRSTNAME, COGNOME/LASTNAME.
 * Entrano come attivi con fonte 'brevo', nella lista scelta. Chi è già nell'elenco resta com'è (chi era uscito
 * resta fuori), ma entra anche lui nella lista.
 */
function newsletter_importa_csv(string $file, int $lista): array {
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
    if ($cE === null) return ['errore' => 'Nel file manca la colonna EMAIL.'];
    $n = ['nuovi' => 0, 'gia' => 0, 'scartati' => 0];
    $ora = moduli_adesso();
    $ins = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti') . " (email, nome, cognome, stato, fonte, consenso_versione, consenso_at, creato, aggiornato)
        VALUES (?, ?, ?, 'attivo', 'brevo', 'brevo', ?, ?, ?)");
    while (($r = fgetcsv($f, 0, $sep, '"', '\\')) !== false) {
        $email = mb_strtolower(trim((string)($r[$cE] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $n['scartati']++; continue; }
        $pulisci = fn($v) => mb_substr(trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$v)), 0, 100);
        $ins->execute([$email, $pulisci($cN !== null ? ($r[$cN] ?? '') : ''), $pulisci($cC !== null ? ($r[$cC] ?? '') : ''), $ora, $ora, $ora]);
        $ins->rowCount() ? $n['nuovi']++ : $n['gia']++;
        if ($lista) newsletter_in_lista(newsletter_id_da_email($email), $lista);
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
    $r = ['in_attesa' => 0, 'attivo' => 0, 'uscito' => 0];
    foreach (db()->query('SELECT stato, COUNT(*) n FROM ' . tbl('newsletter_iscritti') . ' GROUP BY stato') as $x) $r[$x['stato']] = (int)$x['n'];
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
        $ultimaOra = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_coda') . " WHERE inviata_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
        $posti = min($massimo ?? NEWSLETTER_PER_GIRO, max(0, (int)newsletter_config()['per_ora'] - $ultimaOra));
        if ($posti === 0) return array_merge($log, ['limite orario raggiunto']);
        $righe = db()->query('SELECT q.id, q.campagna_id, q.tentativi, i.id AS iscritto_id, i.email, i.nome, i.cognome FROM ' . tbl('newsletter_coda') . ' q
            JOIN ' . tbl('newsletter_campagne') . ' c ON c.id = q.campagna_id AND c.stato = \'in_invio\'
            JOIN ' . tbl('newsletter_iscritti') . " i ON i.id = q.iscritto_id AND i.stato = 'attivo'
            WHERE q.stato = 'attesa' ORDER BY q.id LIMIT " . (int)$posti)->fetchAll();
        $campagne = [];
        $ok = 0; $ko = 0;
        foreach ($righe as $r) {
            $c = $campagne[$r['campagna_id']] ??= newsletter_campagna((int)$r['campagna_id']);
            [$oggetto, $html, $intest] = newsletter_email($c, ['id' => $r['iscritto_id'], 'nome' => $r['nome'], 'email' => $r['email']]);
            if (posta_invia($r['email'], $oggetto, $html, $intest)) {
                db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET stato = 'inviata', inviata_at = NOW() WHERE id = ?")->execute([$r['id']]);
                db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET inviate = inviate + 1 WHERE id = ?')->execute([$r['campagna_id']]);
                $ok++;
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
        // I flussi automatici, con i posti rimasti nel limite orario.
        $log = array_merge($log, newsletter_flussi_giro($posti - $ok - $ko));
        return $log;
    } finally {
        db()->query("SELECT RELEASE_LOCK('" . DB_PREFIX . "newsletter_invio')");
    }
}

require_once __DIR__ . '/newsletter-flussi.php';
