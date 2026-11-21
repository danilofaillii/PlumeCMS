<?php
/**
 * 2.23.0: i tag dei contatti (le etichette delle persone). Un contatto può avere tanti tag; un tag nasce da solo la prima
 * volta che si assegna (o dalla pagina Tag). Si usano nei filtri dei Contatti, nei segmenti ("ha il tag", "non ha il tag"),
 * nelle automazioni (innesco "Riceve un tag", azioni "Aggiungi tag" e "Togli tag"), nei form ("A chi compila dai questi
 * tag"), nell'importazione (una colonna "Tag") e nell'esportazione (la colonna TAG). Li dà anche l'addon Test e questionari.
 *
 * Attenzione: la colonna `tag` di newsletter_flussi e il campo `tag` dei modelli delle email NON sono questi: sono etichette
 * per ordinare automazioni e modelli.
 *
 * Tabelle (utf8 a tre byte come il resto della newsletter, create qui da newsletter_tag_schema()):
 *   newsletter_tag           id, nome (UNIQUE: la collazione utf8_general_ci non distingue maiuscole, minuscole e accenti),
 *                            creato. Il nome si scrive senza HTML, senza virgole e punti e virgola (separano i tag), fino a
 *                            NL_TAG_NOME_MAX caratteri; le emoji si salvano come entità (newsletter_4byte_dentro).
 *   newsletter_contatti_tag  contatto_id, tag_id, aggiunto (PRIMARY contatto_id + tag_id)
 *   newsletter_tag_attese    iscritto_id, nome, origine, creato: i tag di chi deve ancora confermare l'iscrizione (form con
 *                            la doppia conferma, addon dei test); si danno alla conferma (newsletter_tag_alla_conferma()).
 *   colonna `tag` di moduli  i tag di un form (nomi separati da virgola), se la tabella dei form c'è.
 * È un file nuovo della 2.23.0: chi lo usa lo carica con require_once (opcache non può dare una sua versione vecchia).
 */

const NL_TAG_NOME_MAX = 60;           // caratteri del nome che si vede
const NL_TAG_MASSIMO = 5000;          // tag in tutto
const NL_TAG_PER_CONTATTO = 200;      // tag di un contatto
const NL_TAG_PER_VOLTA = 50;          // tag in una sola assegnazione (un campo, una cella, un form)

function newsletter_tag_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $tabelle = [
        'newsletter_tag' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_tag') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(255) NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `nome` (`nome`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_contatti_tag' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_contatti_tag') . " (
            `contatto_id` INT(11) NOT NULL,
            `tag_id` INT(11) NOT NULL,
            `aggiunto` DATETIME NOT NULL,
            PRIMARY KEY (`contatto_id`, `tag_id`), KEY `tag` (`tag_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_tag_attese' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_tag_attese') . " (
            `iscritto_id` INT(11) NOT NULL,
            `nome` VARCHAR(255) NOT NULL,
            `origine` VARCHAR(160) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`iscritto_id`, `nome`), KEY `creato` (`creato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME . 'moduli-tag');
    if (get_setting('newsletter_tag_impronta', '') === $impronta) return;
    foreach ($tabelle as $sql) db()->exec($sql);
    newsletter_tag_colonna_form();
    save_setting('newsletter_tag_impronta', $impronta);
}

/** La colonna `tag` della tabella dei form, se la tabella c'è (si ricontrolla anche quando si salva un form). */
function newsletter_tag_colonna_form(): bool {
    try {
        $c = db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . DB_PREFIX . "moduli'")->fetchColumn();
        if (!(int)$c) return false;
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('moduli'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('tag', $col, true)) db()->exec('ALTER TABLE ' . tbl('moduli') . " ADD COLUMN `tag` VARCHAR(1000) NOT NULL DEFAULT ''");
        return true;
    } catch (Throwable $e) { error_log('newsletter_tag: colonna dei form: ' . $e->getMessage()); return false; }
}

// ------------------------------------------------------------
// Nomi
// ------------------------------------------------------------

/**
 * Il nome di un tag come si salva: niente HTML né caratteri di controllo, niente virgole e punti e virgola (separano i
 * tag), spazi uniti, al massimo NL_TAG_NOME_MAX caratteri visibili, le emoji come entità. '' se non resta niente.
 */
function newsletter_tag_nome(mixed $s): string {
    if (!is_scalar($s)) return '';
    $s = mb_scrub((string)$s, 'UTF-8');
    $s = newsletter_4byte_fuori($s); // un'entità di un'emoji scritta a mano conta come un carattere
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = (string)preg_replace('/[\x00-\x1F\x7F<>,;]+/u', ' ', $s);
    $s = trim((string)preg_replace('/\s+/u', ' ', $s));
    $s = trim(mb_substr($s, 0, NL_TAG_NOME_MAX));
    return $s === '' ? '' : newsletter_4byte_dentro($s);
}

/** Il nome da mostrare (le entità delle emoji tornano emoji). */
function newsletter_tag_vedi(string $nome): string {
    return newsletter_4byte_fuori($nome);
}

/** I nomi scritti in un campo: separati da virgola, punto e virgola o a capo, puliti, senza doppioni, al massimo NL_TAG_PER_VOLTA. */
function newsletter_tag_dividi(mixed $testo): array {
    $out = [];
    foreach (is_array($testo) ? $testo : preg_split('/[,;\r\n]+/', is_scalar($testo) ? (string)$testo : '') as $p) {
        $n = newsletter_tag_nome($p);
        if ($n !== '' && !isset($out[mb_strtolower($n)])) $out[mb_strtolower($n)] = $n;
        if (count($out) >= NL_TAG_PER_VOLTA) break;
    }
    return array_values($out);
}

// ------------------------------------------------------------
// Tag
// ------------------------------------------------------------

/** L'id del tag con questo nome (maiuscole, minuscole e accenti non contano), 0 se non c'è. */
function newsletter_tag_trova(string $nome): int {
    newsletter_tag_schema();
    $n = newsletter_tag_nome($nome);
    if ($n === '') return 0;
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_tag') . ' WHERE nome = ?');
    $st->execute([$n]);
    return (int)$st->fetchColumn();
}

/** Crea un tag (o dà quello che c'è già con lo stesso nome). Ritorna l'id, oppure il messaggio d'errore. */
function newsletter_tag_crea(string $nome): int|string {
    newsletter_tag_schema();
    $n = newsletter_tag_nome($nome);
    if ($n === '') return 'Scrivi il nome del tag: lettere, numeri e spazi, senza virgole (per esempio Cliente).';
    if ($gia = newsletter_tag_trova($n)) return $gia;
    if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_tag'))->fetchColumn() >= NL_TAG_MASSIMO) return 'Ci sono già ' . NL_TAG_MASSIMO . ' tag: è il massimo. Elimina quelli che non usi più nella pagina Tag.';
    try {
        db()->prepare('INSERT INTO ' . tbl('newsletter_tag') . ' (nome, creato) VALUES (?, NOW())')->execute([$n]);
        return (int)db()->lastInsertId();
    } catch (PDOException $e) {
        // Due richieste insieme con lo stesso nome: vince la prima, l'altra prende il suo id.
        if ($gia = newsletter_tag_trova($n)) return $gia;
        throw $e;
    }
}

/** Tutti i tag con quanti contatti li hanno, in ordine di nome. [['id', 'nome', 'contatti']] */
function newsletter_tag_tutti(): array {
    newsletter_tag_schema();
    return array_map(fn($r) => ['id' => (int)$r['id'], 'nome' => (string)$r['nome'], 'contatti' => (int)$r['contatti'], 'creato' => (string)$r['creato']],
        db()->query('SELECT t.id, t.nome, t.creato, COUNT(ct.contatto_id) AS contatti FROM ' . tbl('newsletter_tag') . ' t LEFT JOIN ' . tbl('newsletter_contatti_tag') . ' ct ON ct.tag_id = t.id GROUP BY t.id, t.nome, t.creato ORDER BY t.nome')->fetchAll());
}

/** I nomi dei tag per id (da mostrare), per tendine e caselle. [id => nome visibile] */
function newsletter_tag_nomi(): array {
    newsletter_tag_schema();
    $out = [];
    foreach (db()->query('SELECT id, nome FROM ' . tbl('newsletter_tag') . ' ORDER BY nome') as $r) $out[(int)$r['id']] = newsletter_tag_vedi((string)$r['nome']);
    return $out;
}

/** Quanti contatti ha ogni tag. [id => n] */
function newsletter_tag_conteggi(): array {
    newsletter_tag_schema();
    $out = [];
    foreach (db()->query('SELECT tag_id, COUNT(*) AS n FROM ' . tbl('newsletter_contatti_tag') . ' GROUP BY tag_id') as $r) $out[(int)$r['tag_id']] = (int)$r['n'];
    return $out;
}

/** Il nome di un tag (salvato, con le entità), '' se non c'è. */
function newsletter_tag_nome_di(int $id): string {
    newsletter_tag_schema();
    $st = db()->prepare('SELECT nome FROM ' . tbl('newsletter_tag') . ' WHERE id = ?');
    $st->execute([$id]);
    return (string)$st->fetchColumn();
}

/** Rinomina un tag. Ritorna '' oppure il messaggio d'errore. I contatti, i segmenti e le automazioni lo seguono (usano l'id). */
function newsletter_tag_rinomina(int $id, string $nuovo): string {
    $vecchio = newsletter_tag_nome_di($id);
    if ($vecchio === '') return 'Questo tag non c\'è più.';
    $n = newsletter_tag_nome($nuovo);
    if ($n === '') return 'Scrivi il nome nuovo del tag: lettere, numeri e spazi, senza virgole.';
    $gia = newsletter_tag_trova($n);
    if ($gia && $gia !== $id) return 'Esiste già un tag «' . newsletter_tag_vedi(newsletter_tag_nome_di($gia)) . '»: scegli un altro nome, oppure elimina uno dei due.';
    db()->prepare('UPDATE ' . tbl('newsletter_tag') . ' SET nome = ? WHERE id = ?')->execute([$n, $id]);
    return '';
}

/** Elimina un tag: lo perdono tutti i contatti che lo avevano (la storia di ognuno lo ricorda). Ritorna quanti contatti lo avevano. */
function newsletter_tag_elimina(int $id): int {
    $nome = newsletter_tag_nome_di($id);
    if ($nome === '') return 0;
    $st = db()->prepare('SELECT contatto_id FROM ' . tbl('newsletter_contatti_tag') . ' WHERE tag_id = ? LIMIT 100000');
    $st->execute([$id]);
    $chi = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    db()->prepare('DELETE FROM ' . tbl('newsletter_contatti_tag') . ' WHERE tag_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_tag') . ' WHERE id = ?')->execute([$id]);
    foreach ($chi as $c) newsletter_storia_scrivi($c, 'tag_tolto', '«' . newsletter_tag_vedi($nome) . '» (tag eliminato dalla pagina Tag)', $id);
    return count($chi);
}

// ------------------------------------------------------------
// I tag dei contatti
// ------------------------------------------------------------

/** I tag di un contatto. [id => nome visibile] */
function newsletter_tag_di(int $contatto): array {
    newsletter_tag_schema();
    $st = db()->prepare('SELECT t.id, t.nome FROM ' . tbl('newsletter_contatti_tag') . ' ct JOIN ' . tbl('newsletter_tag') . ' t ON t.id = ct.tag_id WHERE ct.contatto_id = ? ORDER BY t.nome');
    $st->execute([$contatto]);
    $out = [];
    foreach ($st as $r) $out[(int)$r['id']] = newsletter_tag_vedi((string)$r['nome']);
    return $out;
}

/** I tag di tanti contatti insieme (per l'elenco e l'esportazione). [contatto => [id => nome visibile]] */
function newsletter_tag_di_tanti(array $contatti): array {
    newsletter_tag_schema();
    $contatti = array_values(array_unique(array_filter(array_map('intval', $contatti))));
    $out = [];
    foreach (array_chunk($contatti, 500) as $pezzo) {
        $st = db()->query('SELECT ct.contatto_id, t.id, t.nome FROM ' . tbl('newsletter_contatti_tag') . ' ct JOIN ' . tbl('newsletter_tag') . ' t ON t.id = ct.tag_id WHERE ct.contatto_id IN (' . implode(',', $pezzo) . ') ORDER BY t.nome');
        foreach ($st as $r) $out[(int)$r['contatto_id']][(int)$r['id']] = newsletter_tag_vedi((string)$r['nome']);
    }
    return $out;
}

/** Gli id dei tag: da id (numeri) o da nomi (creati se mancano, con $crea). Al massimo NL_TAG_PER_VOLTA. */
function newsletter_tag_ids(array $tag, bool $crea = true): array {
    $ids = [];
    foreach (array_slice($tag, 0, NL_TAG_PER_VOLTA) as $t) {
        if (is_int($t)) {
            if ($t > 0 && newsletter_tag_nome_di($t) !== '') $ids[$t] = true;
            continue;
        }
        if (!is_string($t)) continue;
        $id = $crea ? newsletter_tag_crea($t) : newsletter_tag_trova($t);
        if (is_int($id) && $id > 0) $ids[$id] = true;
    }
    return array_keys($ids);
}

/**
 * Aggiunge dei tag a un contatto: $tag sono nomi (un tag nuovo nasce da solo) o id interi. $origine va nella storia
 * ("dalla scheda", "dall'automazione «...»"). Con $inneschi parte l'innesco "Riceve un tag" delle automazioni per ogni
 * tag nuovo del contatto (solo per chi è iscritto: newsletter_flussi_innesco lo controlla). Ritorna quanti tag ha aggiunto.
 */
function newsletter_tag_aggiungi(int $contatto, array $tag, string $origine = '', bool $inneschi = true): int {
    if ($contatto <= 0 || !$tag) return 0;
    newsletter_tag_schema();
    $c = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $c->execute([$contatto]);
    if (!(int)$c->fetchColumn()) return 0;
    $ha = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_contatti_tag') . ' WHERE contatto_id = ?');
    $ha->execute([$contatto]);
    $posti = NL_TAG_PER_CONTATTO - (int)$ha->fetchColumn();
    $ins = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_contatti_tag') . ' (contatto_id, tag_id, aggiunto) VALUES (?, ?, NOW())');
    $nuovi = [];
    foreach (newsletter_tag_ids($tag) as $id) {
        if ($posti <= 0) break;
        $ins->execute([$contatto, $id]);
        if ($ins->rowCount() !== 1) continue;
        $posti--;
        $nuovi[] = $id;
        newsletter_storia_scrivi($contatto, 'tag_aggiunto', '«' . newsletter_tag_vedi(newsletter_tag_nome_di($id)) . '»' . ($origine !== '' ? ' (' . $origine . ')' : ''), $id);
    }
    if ($nuovi) db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET aggiornato = NOW() WHERE id = ?')->execute([$contatto]);
    if ($inneschi && function_exists('newsletter_flussi_innesco')) foreach ($nuovi as $id) {
        try { newsletter_flussi_innesco('tag', $id, $contatto); } catch (Throwable $e) { error_log('newsletter_tag: innesco: ' . $e->getMessage()); }
    }
    return count($nuovi);
}

/** Toglie dei tag (id o nomi) a un contatto. Ritorna quanti ne ha tolti. */
function newsletter_tag_togli(int $contatto, array $tag, string $origine = ''): int {
    if ($contatto <= 0 || !$tag) return 0;
    newsletter_tag_schema();
    $del = db()->prepare('DELETE FROM ' . tbl('newsletter_contatti_tag') . ' WHERE contatto_id = ? AND tag_id = ?');
    $n = 0;
    foreach (newsletter_tag_ids($tag, false) as $id) {
        $del->execute([$contatto, $id]);
        if ($del->rowCount() !== 1) continue;
        $n++;
        newsletter_storia_scrivi($contatto, 'tag_tolto', '«' . newsletter_tag_vedi(newsletter_tag_nome_di($id)) . '»' . ($origine !== '' ? ' (' . $origine . ')' : ''), $id);
    }
    if ($n) db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET aggiornato = NOW() WHERE id = ?')->execute([$contatto]);
    return $n;
}

/** I tag di un contatto diventano esattamente questi (l'importazione con "sostituisci"). Ritorna [aggiunti, tolti]. */
function newsletter_tag_imposta(int $contatto, array $tag, string $origine = '', bool $inneschi = true): array {
    $voluti = newsletter_tag_ids($tag);
    $via = array_diff(array_keys(newsletter_tag_di($contatto)), $voluti);
    $tolti = $via ? newsletter_tag_togli($contatto, array_values($via), $origine) : 0;
    return [newsletter_tag_aggiungi($contatto, $voluti, $origine, $inneschi), $tolti];
}

/** L'azione di gruppo della pagina Contatti: aggiunge o toglie gli stessi tag a tanti contatti. Ritorna a quanti ha cambiato qualcosa. */
function newsletter_tag_gruppo(array $contatti, array $tag, bool $aggiungi): int {
    $ids = $aggiungi ? newsletter_tag_ids($tag) : newsletter_tag_ids($tag, false);
    if (!$ids) return 0;
    $n = 0;
    foreach (array_values(array_unique(array_filter(array_map('intval', $contatti)))) as $c) {
        if (($aggiungi ? newsletter_tag_aggiungi($c, $ids, 'azione di gruppo nei Contatti') : newsletter_tag_togli($c, $ids, 'azione di gruppo nei Contatti')) > 0) $n++;
    }
    return $n;
}

// ------------------------------------------------------------
// Tag che aspettano la conferma dell'iscrizione
// ------------------------------------------------------------

/** Ricorda dei tag (nomi) per chi deve ancora confermare: arrivano con la conferma. */
function newsletter_tag_in_attesa(int $iscritto, array $nomi, string $origine = ''): void {
    if ($iscritto <= 0) return;
    newsletter_tag_schema();
    $ins = db()->prepare('REPLACE INTO ' . tbl('newsletter_tag_attese') . ' (iscritto_id, nome, origine, creato) VALUES (?, ?, ?, NOW())');
    foreach (newsletter_tag_dividi($nomi) as $n) $ins->execute([$iscritto, $n, mb_substr(newsletter_4byte_dentro($origine), 0, 160)]);
}

/** Alla conferma dell'iscrizione (newsletter_conferma_esito): i tag che aspettavano si danno, con l'innesco "Riceve un tag". */
function newsletter_tag_alla_conferma(int $iscritto): int {
    newsletter_tag_schema();
    $st = db()->prepare('SELECT nome, origine FROM ' . tbl('newsletter_tag_attese') . ' WHERE iscritto_id = ? AND creato > DATE_SUB(NOW(), INTERVAL 40 DAY) ORDER BY creato, nome');
    $st->execute([$iscritto]);
    $righe = $st->fetchAll();
    db()->prepare('DELETE FROM ' . tbl('newsletter_tag_attese') . ' WHERE iscritto_id = ?')->execute([$iscritto]);
    $per = [];
    foreach ($righe as $r) $per[(string)$r['origine']][] = (string)$r['nome'];
    $n = 0;
    foreach ($per as $origine => $nomi) $n += newsletter_tag_aggiungi($iscritto, $nomi, newsletter_4byte_fuori($origine) . ($origine !== '' ? ', ' : '') . 'alla conferma dell\'iscrizione');
    return $n;
}

/** Dà dei tag subito a chi è iscritto, altrimenti li ricorda fino alla conferma. Ritorna 'subito' o 'attesa' ('' se niente). */
function newsletter_tag_dai(int $iscritto, array $nomi, string $origine = ''): string {
    if ($iscritto <= 0 || !($nomi = newsletter_tag_dividi($nomi))) return '';
    $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$iscritto]);
    if ($st->fetchColumn() === 'attivo') { newsletter_tag_aggiungi($iscritto, $nomi, $origine); return 'subito'; }
    newsletter_tag_in_attesa($iscritto, $nomi, $origine);
    return 'attesa';
}

/** Pulizia (cron, una volta al giorno): i tag in attesa di chi non ha confermato in 40 giorni. */
function newsletter_tag_pulizia(): int {
    newsletter_tag_schema();
    return (int)db()->exec('DELETE FROM ' . tbl('newsletter_tag_attese') . ' WHERE creato < DATE_SUB(NOW(), INTERVAL 40 DAY)');
}

// ------------------------------------------------------------
// I tag dei form
// ------------------------------------------------------------

/** I tag di un form (nomi da mostrare). */
function newsletter_tag_form(array $m): array {
    return array_map('newsletter_tag_vedi', newsletter_tag_dividi((string)($m['tag'] ?? '')));
}

/** Salva i tag di un form (passo 1 del costruttore). */
function newsletter_tag_form_salva(int $form, mixed $testo): void {
    newsletter_tag_schema();
    if (!newsletter_tag_colonna_form()) return;
    $nomi = newsletter_tag_dividi($testo);
    db()->prepare('UPDATE ' . tbl('moduli') . ' SET tag = ? WHERE id = ?')->execute([mb_substr(implode(', ', array_slice($nomi, 0, 20)), 0, 1000), $form]);
}

/** Dopo un invio che iscrive (o aggiorna il profilo): i tag del form, subito o alla conferma. */
function newsletter_tag_dal_form(array $m, int $iscritto): string {
    $nomi = newsletter_tag_dividi((string)($m['tag'] ?? ''));
    if (!$nomi || $iscritto <= 0) return '';
    return newsletter_tag_dai($iscritto, $nomi, 'dal form «' . newsletter_4byte_fuori((string)($m['nome'] ?? '')) . '»');
}
