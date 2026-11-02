<?php
/**
 * 2.11.0, fase 4 del piano Brevo (docs-sviluppo/brevo-confronto.md, capitoli 1.9, 1.10, 1.11, 1.15, 3.6, 3.8):
 * le campagne a riepilogo, la pagina di disiscrizione con il questionario e il centro preferenze.
 *
 *   - campagne: nome interno, sezioni Mittente, Destinatari (liste, segmenti, esclusioni che vincono, contatti non
 *     coinvolti), Oggetto, Design (editor semplice o codice HTML, testo semplice), Impostazioni aggiuntive (Rispondi a,
 *     campo "A", versione web, link delle preferenze, UTM, tag, invio a lotti); stati in più: annullata, archiviata;
 *     figlie per "Invia ai nuovi contatti" (padre_id) e "Rimanda ai non aperti" (rinvio_di, una volta sola);
 *   - versione web dell'email (/newsletter-web/, firmata per campagna e contatto), indirizzo dell'azienda nel piede;
 *   - pagina di disiscrizione con il questionario dei motivi (newsletter_uscite_motivi) e centro preferenze pubblico
 *     (/newsletter-preferenze/, firmato): liste pubbliche (newsletter_liste.pubblica), uscita da una lista
 *     (newsletter_liste_uscite), attributi consentiti, uscita da tutto.
 *
 * Migrazione senza perdite: le colonne nuove hanno i valori di prima (editor semplice, niente versione web, niente link
 * delle preferenze, niente UTM, un lotto): una campagna di prima esce con lo stesso HTML al byte. L'indirizzo
 * dell'azienda entra nel piede solo quando è scritto in Impostazioni.
 *
 * Chi usa queste funzioni da un altro file fa require_once di questo file (opcache: un file nuovo non è mai vecchio).
 */

require_once __DIR__ . '/newsletter.php';
require_once __DIR__ . '/newsletter-ab.php'; // 2.13.0: il test A/B
require_once __DIR__ . '/newsletter-report.php'; // 2.14.0: report, statistiche, orario migliore

const NEWSLETTER_WEB = '/newsletter-web/';
const NEWSLETTER_PREFERENZE = '/newsletter-preferenze/';
const NL_CAMP_STATI = ['bozza' => 'Bozza', 'programmata' => 'Programmata', 'in_invio' => 'In invio', 'sospesa' => 'Sospesa', 'inviata' => 'Inviata', 'annullata' => 'Annullata'];
/** Le viste dell'elenco: nome => [testo, condizione SQL fissa]. Elenco chiuso. */
const NL_CAMP_VISTE = [
    'tutte' => ['Tutte', 'archiviata = 0'],
    'bozze' => ['Bozze', "archiviata = 0 AND stato = 'bozza'"],
    'programmate' => ['Programmate', "archiviata = 0 AND stato = 'programmata'"],
    'in_invio' => ['In invio', "archiviata = 0 AND stato = 'in_invio'"],
    'inviate' => ['Inviate', "archiviata = 0 AND stato = 'inviata'"],
    'sospese' => ['Sospese', "archiviata = 0 AND stato = 'sospesa'"],
    'annullate' => ['Annullate', "archiviata = 0 AND stato = 'annullata'"],
    'archiviate' => ['Archiviate', 'archiviata = 1'],
];
/** Le parti di una campagna che si possono copiare con Duplica, e le colonne di ognuna. */
const NL_CAMP_PARTI = [
    'mittente' => ['Mittente', ['mittente_id']],
    'destinatari' => ['Destinatari', ['liste', 'segmenti', 'escludi_liste', 'escludi_segmenti', 'non_coinvolti']],
    'oggetto' => ['Oggetto e testo di anteprima', ['oggetto', 'anteprima']],
    'design' => ['Design (blocchi, testo, pulsante, codice HTML, testo semplice)', ['editor', 'corpo', 'html', 'pulsante_testo', 'pulsante_link', 'testo', 'testo_modificato', 'design', 'modello_id']],
    'impostazioni' => ['Impostazioni aggiuntive', ['rispondi_a', 'campo_a', 'tag', 'utm', 'utm_campagna', 'link_web', 'link_preferenze', 'lotti', 'lotti_intervallo']],
];
/** I motivi del questionario della disiscrizione. Le chiavi restano: i testi si possono cambiare in Impostazioni. */
const NL_USCITA_MOTIVI = [
    'non_interessa' => 'Non mi interessa più',
    'troppe' => 'Ricevo troppe email',
    'mai_iscritto' => 'Non mi sono mai iscritto',
    'non_adatti' => 'I contenuti non sono adatti a me',
    'altro' => 'Altro',
];
const NL_LOTTI_MAX = 10;
/** Quante email di campagna senza aperture né clic fanno un contatto "non coinvolto" (nel periodo scelto in Impostazioni). */
const NL_NON_COINVOLTI_EMAIL = 3;

function newsletter_campagne_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    newsletter_schema();
    newsletter_flussi_schema();
    if (function_exists('nd_schema')) nd_schema(); // 2.12.0: design e modello_id nelle campagne, modelli, sezioni salvate
    if (function_exists('nl_ab_schema')) nl_ab_schema(); // 2.13.0: il test A/B (includes/newsletter-ab.php)
    if (function_exists('nl_rep_schema')) nl_rep_schema(); // 2.14.0: orario migliore, eventi ripetuti (includes/newsletter-report.php)
    $colonne = [
        'nome' => "VARCHAR(150) NOT NULL DEFAULT ''",
        'tipo' => "VARCHAR(8) NOT NULL DEFAULT 'normale'",
        'editor' => "VARCHAR(8) NOT NULL DEFAULT 'semplice'",
        'html' => 'MEDIUMTEXT NULL',
        'testo' => 'MEDIUMTEXT NULL',
        'testo_modificato' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'segmenti' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'escludi_liste' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'escludi_segmenti' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'non_coinvolti' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'rispondi_a' => "VARCHAR(190) NOT NULL DEFAULT ''",
        'campo_a' => "VARCHAR(190) NOT NULL DEFAULT ''",
        'tag' => "VARCHAR(190) NOT NULL DEFAULT ''",
        'utm' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'utm_campagna' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'link_web' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'link_preferenze' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'lotti' => 'TINYINT NOT NULL DEFAULT 1',
        'lotti_intervallo' => 'INT(11) NOT NULL DEFAULT 0',
        'archiviata' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'padre_id' => 'INT(11) NOT NULL DEFAULT 0',
        'rinvio_di' => 'INT(11) NOT NULL DEFAULT 0',
        'rinvio_fatto' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
    $motivi = 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_uscite_motivi') . " (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `contatto_id` INT(11) NOT NULL,
        `campagna_id` INT(11) NOT NULL DEFAULT 0,
        `flusso_id` INT(11) NOT NULL DEFAULT 0,
        `motivo` VARCHAR(30) NOT NULL,
        `altro` VARCHAR(500) NOT NULL DEFAULT '',
        `creato` DATETIME NOT NULL,
        PRIMARY KEY (`id`), KEY `contatto` (`contatto_id`), KEY `campagna` (`campagna_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $impronta = md5(serialize($colonne) . $motivi . DB_NAME . 'coda-lotti liste-pubbliche');
    if (get_setting('newsletter_campagne_impronta', '') === $impronta) return;
    db()->exec($motivi);
    $t = tbl('newsletter_campagne');
    $col = db()->query('SHOW COLUMNS FROM ' . $t)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($colonne as $c => $def) if (!in_array($c, $col, true)) db()->exec("ALTER TABLE $t ADD COLUMN `$c` $def");
    $q = tbl('newsletter_coda');
    $col = db()->query('SHOW COLUMNS FROM ' . $q)->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('lotto', $col, true)) db()->exec("ALTER TABLE $q ADD COLUMN `lotto` TINYINT NOT NULL DEFAULT 1");
    if (!in_array('dopo', $col, true)) db()->exec("ALTER TABLE $q ADD COLUMN `dopo` DATETIME DEFAULT NULL");
    $l = tbl('newsletter_liste');
    $col = db()->query('SHOW COLUMNS FROM ' . $l)->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('pubblica', $col, true)) {
        db()->exec("ALTER TABLE $l ADD COLUMN `pubblica` TINYINT(1) NOT NULL DEFAULT 0");
        // La lista principale si vede nel centro preferenze: è quella del modulo della newsletter del sito.
        db()->prepare("UPDATE $l SET pubblica = 1 WHERE id = ?")->execute([newsletter_lista_principale()]);
    }
    // Le campagne di prima: il nome interno è l'oggetto (Brevo chiede il nome alla creazione).
    if (get_setting('newsletter_campagne_migrate', '') !== '1') {
        db()->exec("UPDATE $t SET nome = LEFT(oggetto, 150) WHERE nome = ''");
        save_setting('newsletter_campagne_migrate', '1');
    }
    save_setting('newsletter_campagne_impronta', $impronta);
}

/** Gli id interi (positivi, senza doppioni) di un elenco "3,5". */
function nl_ids(string $s): array {
    return array_values(array_unique(array_filter(array_map('intval', explode(',', $s)), fn($x) => $x > 0)));
}

/** Gli id scelti in un campo del modulo (array o testo), solo fra quelli che esistono in $validi. */
function nl_ids_scelti($v, array $validi): string {
    $v = is_array($v) ? $v : explode(',', (string)$v);
    $ok = array_flip(array_map('intval', $validi));
    $out = [];
    foreach ($v as $x) { $x = is_scalar($x) ? (int)$x : 0; if ($x > 0 && isset($ok[$x])) $out[$x] = $x; }
    return implode(',', array_slice(array_values($out), 0, 40));
}

/** I segmenti salvati che esistono ancora (id => nome). */
function nl_camp_segmenti(): array {
    if (!function_exists('newsletter_segmenti')) return [];
    $out = [];
    foreach (newsletter_segmenti() as $s) $out[(int)$s['id']] = (string)$s['nome'];
    return $out;
}

// ------------------------------------------------------------
// Destinatari
// ------------------------------------------------------------

/**
 * La condizione SQL (contatti i, già attivi) dei destinatari di una campagna: (in una delle liste, senza essere uscito
 * da quella lista, OPPURE in uno dei segmenti) E in nessuna lista e in nessun segmento esclusi (l'esclusione vince) E,
 * se chiesto, non fra i non coinvolti. Le figlie: "rinvio" solo chi ha ricevuto la madre e non l'ha aperta; "nuovi
 * contatti" senza chi ha già ricevuto la madre o un'altra figlia. '' se la campagna non ha nessuna lista né segmento.
 * I valori vanno in $par, nell'ordine.
 */
function newsletter_campagna_where(array $c, array &$par): string {
    newsletter_campagne_schema();
    if (function_exists('newsletter_segmenti_schema')) newsletter_segmenti_schema();
    $il = tbl('newsletter_iscritti_liste'); $co = tbl('newsletter_coda'); $ev = tbl('newsletter_eventi');
    $rinvio = (int)($c['rinvio_di'] ?? 0);
    if ($rinvio) {
        $par[] = $rinvio; $par[] = $rinvio;
        return "EXISTS (SELECT 1 FROM $co q WHERE q.iscritto_id = i.id AND q.campagna_id = ? AND q.stato = 'inviata')
            AND NOT EXISTS (SELECT 1 FROM $ev e WHERE e.iscritto_id = i.id AND e.campagna_id = ? AND e.tipo IN ('apertura', 'clic'))";
    }
    $liste = array_values(array_intersect(nl_ids((string)($c['liste'] ?? '')), array_map('intval', array_column(newsletter_liste(), 'id'))));
    $segValidi = array_keys(nl_camp_segmenti());
    $segmenti = array_values(array_intersect(nl_ids((string)($c['segmenti'] ?? '')), $segValidi));
    $dentro = [];
    if ($liste) $dentro[] = "EXISTS (SELECT 1 FROM $il l WHERE l.iscritto_id = i.id AND l.lista_id IN (" . implode(',', $liste) . ')' . newsletter_sql_uscite_lista() . ')';
    foreach ($segmenti as $s) $dentro[] = 'COALESCE((' . newsletter_segmento_where($s, $par) . '), 0)';
    if (!$dentro) return '';
    $w = '(' . implode(' OR ', $dentro) . ')';
    $escL = array_values(array_intersect(nl_ids((string)($c['escludi_liste'] ?? '')), array_map('intval', array_column(newsletter_liste(), 'id'))));
    if ($escL) $w .= " AND NOT EXISTS (SELECT 1 FROM $il x WHERE x.iscritto_id = i.id AND x.lista_id IN (" . implode(',', $escL) . '))';
    foreach (array_intersect(nl_ids((string)($c['escludi_segmenti'] ?? '')), $segValidi) as $s) $w .= ' AND NOT COALESCE((' . newsletter_segmento_where((int)$s, $par) . '), 0)';
    if (!empty($c['non_coinvolti'])) {
        $g = newsletter_non_coinvolti_giorni();
        $par[] = $g; $par[] = NL_NON_COINVOLTI_EMAIL; $par[] = $g;
        $w .= " AND NOT ((SELECT COUNT(*) FROM $co nq WHERE nq.iscritto_id = i.id AND nq.stato = 'inviata' AND nq.inviata_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) >= ?
            AND NOT EXISTS (SELECT 1 FROM $ev ne WHERE ne.iscritto_id = i.id AND ne.tipo IN ('apertura', 'clic') AND ne.creato >= DATE_SUB(NOW(), INTERVAL ? DAY)))";
    }
    $padre = (int)($c['padre_id'] ?? 0);
    if ($padre) {
        $par[] = $padre; $par[] = $padre; $par[] = (int)($c['id'] ?? 0);
        $w .= " AND NOT EXISTS (SELECT 1 FROM $co pq WHERE pq.iscritto_id = i.id AND pq.campagna_id = ?)
            AND NOT EXISTS (SELECT 1 FROM $co sq JOIN " . tbl('newsletter_campagne') . " sc ON sc.id = sq.campagna_id WHERE sq.iscritto_id = i.id AND sc.padre_id = ? AND sc.id <> ?)";
    }
    return $w;
}

/** Dopo quanti giorni senza aperture né clic un contatto è "non coinvolto" (Impostazioni della newsletter, 90 di partenza). */
function newsletter_non_coinvolti_giorni(): int {
    return max(30, min(365, (int)(newsletter_config()['coinvolti_giorni'] ?? 90)));
}

/** Quanti contatti attivi riceverebbero la campagna adesso (ognuno una volta sola). */
function newsletter_campagna_conta(array $c): int {
    $par = [];
    $w = newsletter_campagna_where($c, $par);
    if ($w === '') return 0;
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i WHERE i.stato = 'attivo' AND $w");
    $st->execute($par);
    return (int)$st->fetchColumn();
}

/**
 * Prepara la coda di una campagna arrivata alla sua ora: i destinatari attivi in quel momento, una riga a testa, e con i
 * lotti l'ora da cui ogni lotto può partire (dall'ora programmata, a intervalli). Ritorna la riga di log.
 */
function newsletter_campagna_avvia(array $c): string {
    newsletter_campagne_schema();
    $id = (int)$c['id'];
    $par = [];
    $w = newsletter_campagna_where($c, $par);
    if ($w === '') {
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'bozza', invio_at = NULL WHERE id = ?")->execute([$id]);
        return "campagna $id senza liste né segmenti: tornata bozza";
    }
    array_unshift($par, $id);
    db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_coda') . ' (campagna_id, iscritto_id) SELECT ?, i.id FROM ' . tbl('newsletter_iscritti') . " i WHERE i.stato = 'attivo' AND $w ORDER BY i.id")->execute($par);
    $n = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_coda') . ' WHERE campagna_id = ' . $id)->fetchColumn();
    $ab = ($c['tipo'] ?? '') === 'ab' && function_exists('nl_ab_avvia'); // 2.13.0: niente lotti nel test A/B
    // 2.14.0: all'orario migliore ogni contatto ha la sua ora (includes/newsletter-report.php): niente lotti.
    $orario = !$ab && (int)($c['orario_migliore'] ?? 0) && function_exists('nl_orario_applica');
    $lotti = $ab || $orario ? 1 : max(1, min(NL_LOTTI_MAX, (int)($c['lotti'] ?? 1)));
    $abLog = $orario ? '; ' . nl_orario_applica($id, $c) : '';
    if ($lotti > 1 && $n > 1) {
        $ids = array_map('intval', db()->query('SELECT id FROM ' . tbl('newsletter_coda') . " WHERE campagna_id = $id AND stato = 'attesa' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
        $tot = count($ids);
        $base = strtotime((string)($c['invio_at'] ?? '')) ?: time();
        $passo = max(0, (int)($c['lotti_intervallo'] ?? 0)) * 60;
        $up = db()->prepare('UPDATE ' . tbl('newsletter_coda') . ' SET lotto = ?, dopo = ? WHERE campagna_id = ? AND id BETWEEN ? AND ?');
        for ($k = 0; $k < $lotti; $k++) {
            $da = intdiv($k * $tot, $lotti); $a = intdiv(($k + 1) * $tot, $lotti) - 1;
            if ($a < $da) continue;
            $up->execute([$k + 1, date('Y-m-d H:i:s', $base + $k * $passo), $id, $ids[$da], $ids[$a]]);
        }
    }
    if ($ab) $abLog = '; ' . nl_ab_avvia($id);
    db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'in_invio', destinatari = ?, aggiornato = NOW() WHERE id = ?")->execute([$n, $id]);
    return "campagna $id avviata: $n destinatari" . ($lotti > 1 ? " in $lotti lotti" : '') . $abLog;
}

// ------------------------------------------------------------
// Le sezioni del riepilogo
// ------------------------------------------------------------

/** Una campagna modificabile: bozza o programmata. */
function newsletter_campagna_modificabile(array $c): bool {
    return in_array($c['stato'], ['bozza', 'programmata'], true) && !(int)($c['archiviata'] ?? 0);
}

/**
 * Lo stato di ogni sezione del riepilogo: [nome => ['ok' => bool, 'manca' => frase per chi legge]]. Una sezione non a posto
 * blocca l'invio, e la frase dice cosa fare.
 */
function newsletter_campagna_sezioni(array $c): array {
    $out = [];
    $mitt = (int)($c['mittente_id'] ?? 0) ? consegna_mittente((int)$c['mittente_id']) : null;
    $okMitt = ($mitt && $mitt['verificato_at']) || posta_mittente()[0] !== '';
    $out['mittente'] = ['ok' => $okMitt, 'manca' => $okMitt ? '' : "Manca l'indirizzo del mittente: aggiungilo e confermalo in Consegna delle email, scheda Mittenti."];
    $par = [];
    $vuota = newsletter_campagna_where($c, $par) === '';
    $n = $vuota ? 0 : newsletter_campagna_conta($c);
    $out['destinatari'] = ['ok' => !$vuota && $n > 0, 'n' => $n, 'manca' => $vuota ? 'Scegli almeno una lista o un segmento a cui mandarla.' : ($n === 0 ? 'Con queste scelte la campagna non arriva a nessun contatto iscritto: cambia liste, segmenti o esclusioni.' : '')];
    $okOgg = trim((string)$c['oggetto']) !== '';
    $out['oggetto'] = ['ok' => $okOgg, 'manca' => $okOgg ? '' : "Scrivi l'oggetto dell'email."];
    [$okDes, $mancaDes] = nl_camp_design_stato($c);
    $out['design'] = ['ok' => $okDes, 'manca' => $mancaDes];
    // 2.13.0: il test A/B ha la sua sezione, dopo Destinatari, con le sue regole (includes/newsletter-ab.php).
    if (($c['tipo'] ?? '') === 'ab' && function_exists('nl_ab_sezione')) $out = array_slice($out, 0, 2, true) + ['ab' => nl_ab_sezione($c, $n)] + array_slice($out, 2, null, true);
    $out['impostazioni'] = ['ok' => true, 'manca' => ''];
    return $out;
}

/** Il design di una campagna è a posto? [bool, frase per chi legge]. */
function nl_camp_design_stato(array $c): array {
    $ed = (string)($c['editor'] ?? 'semplice');
    if ($ed === 'blocchi') $ok = function_exists('nd_ha_contenuto') && nd_ha_contenuto(nd_design((string)($c['design'] ?? '')));
    else $ok = $ed === 'html' ? trim((string)($c['html'] ?? '')) !== '' : trim(strip_tags((string)$c['corpo'], '<img>')) !== '';
    return [$ok, $ok ? '' : ['html' => 'Incolla il codice HTML dell\'email.', 'blocchi' => "Nell'editor a blocchi aggiungi almeno un titolo, un testo o un'immagine."][$ed] ?? "Scrivi il testo dell'email."];
}

/** Le frasi di quello che manca per inviare (vuoto = si può inviare). */
function newsletter_campagna_manca(array $c): array {
    return array_values(array_filter(array_map(fn($s) => $s['ok'] ? '' : $s['manca'], newsletter_campagna_sezioni($c))));
}

/** Salva una sezione del riepilogo da $p (il POST). Ritorna '' oppure l'errore (e niente si salva). */
function newsletter_campagna_salva_sezione(int $id, string $sez, array $p): string {
    newsletter_campagne_schema();
    $c = newsletter_campagna($id);
    if (!$c || !newsletter_campagna_modificabile($c)) return 'Questa campagna non si può più modificare: è già partita.';
    $set = [];
    // 2.13.0: il design della versione B di un test A/B sul contenuto si salva nella sua tabella.
    $versioneB = $sez === 'design' && ($p['versione'] ?? '') === 'b';
    if ($versioneB) {
        if (!function_exists('nl_ab') || $c['tipo'] !== 'ab' || nl_ab($id)['cosa'] !== 'design') return 'La versione B del design c\'è solo in un test A/B sul contenuto.';
        $c = nl_ab_versione($c, 'b');
    }
    switch ($sez) {
        case 'mittente':
            $m = consegna_mittente((int)($p['mittente_id'] ?? 0));
            $set['mittente_id'] = $m && $m['verificato_at'] ? (int)$m['id'] : 0;
            break;
        case 'destinatari':
            if ((int)$c['rinvio_di']) return '';
            $liste = array_map('intval', array_column(newsletter_liste(), 'id'));
            $seg = array_keys(nl_camp_segmenti());
            $set['liste'] = nl_ids_scelti($p['liste'] ?? [], $liste);
            $set['segmenti'] = nl_ids_scelti($p['segmenti'] ?? [], $seg);
            $set['escludi_liste'] = nl_ids_scelti($p['escludi_liste'] ?? [], $liste);
            $set['escludi_segmenti'] = nl_ids_scelti($p['escludi_segmenti'] ?? [], $seg);
            $set['non_coinvolti'] = empty($p['non_coinvolti']) ? 0 : 1;
            break;
        case 'oggetto':
            $set['oggetto'] = newsletter_4byte_dentro(mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($p['oggetto'] ?? ''))), 0, 200));
            $set['anteprima'] = newsletter_4byte_dentro(mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($p['anteprima'] ?? ''))), 0, 200));
            break;
        case 'design':
            $ed = in_array($p['editor'] ?? '', ['html', 'blocchi'], true) ? $p['editor'] : 'semplice';
            if ($ed === 'blocchi' && !function_exists('nd_design')) $ed = 'semplice';
            $set['editor'] = $ed;
            // 2.12.0: passando all'editor a blocchi senza un design, si parte da quello di partenza (titolo, testo, pulsante, piede).
            if ($ed === 'blocchi' && !nd_design((string)($c['design'] ?? ''))) $set['design'] = nd_json(nd_design_vuoto());
            if (array_key_exists('corpo', $p)) $set['corpo'] = newsletter_4byte_dentro((string)$p['corpo']);
            if (array_key_exists('pulsante_testo', $p)) {
                $link = trim((string)($p['pulsante_link'] ?? ''));
                $set['pulsante_testo'] = newsletter_4byte_dentro(mb_substr(trim((string)$p['pulsante_testo']), 0, 100));
                $set['pulsante_link'] = preg_match('~^https?://~', $link) ? mb_substr($link, 0, 500) : '';
            }
            if (array_key_exists('html', $p)) {
                $h = (string)$p['html'];
                if (strlen($h) > 1500000) return 'Il codice HTML è troppo lungo (più di 1,5 MB): togli le immagini incorporate e usa i loro indirizzi.';
                $set['html'] = newsletter_4byte_dentro($h);
            }
            // Passando al codice HTML con la casella vuota, si parte dall'email fatta con l'editor semplice.
            if ($ed === 'html' && trim((string)($set['html'] ?? $c['html'] ?? '')) === '') $set['html'] = newsletter_4byte_dentro(nl_camp_html_partenza(array_merge($c, $set), (string)$c['editor']));
            if (array_key_exists('testo', $p)) {
                $set['testo_modificato'] = empty($p['testo_modificato']) ? 0 : 1;
                $set['testo'] = newsletter_4byte_dentro(mb_substr(str_replace("\r", '', (string)$p['testo']), 0, 200000));
            }
            break;
        case 'impostazioni':
            $r = trim((string)($p['rispondi_a'] ?? ''));
            if ($r !== '' && !filter_var($r, FILTER_VALIDATE_EMAIL)) return "L'indirizzo di «Rispondi a» non è valido: scrivilo per intero (per esempio info@esempio.it), oppure lascialo vuoto.";
            $set['rispondi_a'] = mb_strtolower($r);
            $set['campo_a'] = newsletter_4byte_dentro(mb_substr(trim(str_replace(["\r", "\n", '<', '>', '"'], ' ', (string)($p['campo_a'] ?? ''))), 0, 190));
            $set['tag'] = newsletter_4byte_dentro(mb_substr(trim(strip_tags(str_replace(["\r", "\n"], ' ', (string)($p['tag'] ?? '')))), 0, 190));
            $set['utm'] = empty($p['utm']) ? 0 : 1;
            $set['utm_campagna'] = mb_substr(preg_replace('/[^a-z0-9_-]+/', '-', mb_strtolower(trim((string)($p['utm_campagna'] ?? '')))), 0, 100);
            $set['link_web'] = empty($p['link_web']) ? 0 : 1;
            $set['link_preferenze'] = empty($p['link_preferenze']) ? 0 : 1;
            $lotti = empty($p['lotti_attivi']) || $c['tipo'] === 'ab' ? 1 : (int)($p['lotti'] ?? 1); // niente lotti nel test A/B
            if ($lotti < 1 || $lotti > NL_LOTTI_MAX) return 'I lotti vanno da 2 a ' . NL_LOTTI_MAX . '.';
            $int = (int)($p['lotti_intervallo'] ?? 0);
            if ($lotti > 1 && ($int < 5 || $int > 10080)) return "Fra un lotto e l'altro scrivi da 5 minuti a 7 giorni (10080 minuti): il cron gira ogni 5 minuti.";
            $set['lotti'] = $lotti;
            $set['lotti_intervallo'] = $lotti > 1 ? $int : 0;
            break;
        case 'ab':
            if ($c['tipo'] !== 'ab' || !function_exists('nl_ab_salva')) return 'Questa non è una campagna con il test A/B.';
            return nl_ab_salva($c, $p);
        case 'nome':
            $n = newsletter_4byte_dentro(mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($p['nome'] ?? ''))), 0, 150));
            if ($n === '') return 'Scrivi il nome della campagna: serve a te per ritrovarla, chi la riceve non lo vede.';
            $set['nome'] = $n;
            break;
        default:
            return 'Sezione sconosciuta.';
    }
    if (!$set) return '';
    if ($versioneB) { nl_ab_scrivi_b($id, $set); return ''; }
    $sql = []; $val = [];
    foreach ($set as $k => $v) { $sql[] = "`$k` = ?"; $val[] = $v; }
    $val[] = $id;
    db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET ' . implode(', ', $sql) . ', aggiornato = NOW() WHERE id = ?')->execute($val);
    return '';
}

/** Crea una campagna (Crea campagna > Normale o Test A/B > nome). Ritorna l'id, oppure il messaggio d'errore. */
function newsletter_campagna_crea(string $nome, string $tipo = 'normale') {
    newsletter_campagne_schema();
    $nome = newsletter_4byte_dentro(mb_substr(trim(str_replace(["\r", "\n"], ' ', $nome)), 0, 150));
    if ($nome === '') return 'Scrivi il nome della campagna: serve a te per ritrovarla, chi la riceve non lo vede.';
    if ($tipo === 'ab' && !function_exists('nl_ab')) $tipo = '';
    if (!in_array($tipo, ['normale', 'ab'], true)) return 'Scegli il tipo di campagna: normale oppure test A/B.';
    $ora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO ' . tbl('newsletter_campagne') . " (nome, tipo, oggetto, corpo, liste, link_web, link_preferenze, creato, aggiornato) VALUES (?, ?, '', '', '', 1, 1, ?, ?)")
        ->execute([$nome, $tipo, $ora, $ora]);
    $id = (int)db()->lastInsertId();
    if ($tipo === 'ab') nl_ab($id);
    return $id;
}

/** Duplica una campagna con le parti scelte (NL_CAMP_PARTI). Le altre partono vuote. Ritorna il nuovo id, o 0. */
function newsletter_campagna_duplica(int $id, array $parti, bool $comeAb = true): int {
    newsletter_campagne_schema();
    $c = newsletter_campagna($id);
    if (!$c) return 0;
    // 2.13.0: la copia di un test A/B è un test A/B con le stesse impostazioni (le figlie no: prendono la vincente).
    $ab = $comeAb && $c['tipo'] === 'ab' && function_exists('nl_ab_copia');
    $nuovo = newsletter_campagna_crea('Copia di ' . newsletter_4byte_fuori((string)($c['nome'] ?: $c['oggetto'] ?: 'campagna')), $ab ? 'ab' : 'normale');
    if (!is_int($nuovo)) return 0;
    if ($ab) nl_ab_copia($id, $nuovo);
    $set = [];
    foreach (NL_CAMP_PARTI as $k => [, $colonne]) {
        if (!in_array($k, $parti, true)) continue;
        foreach ($colonne as $col) $set[$col] = $c[$col] ?? null;
    }
    if ($set) {
        $sql = []; $val = [];
        foreach ($set as $k => $v) { $sql[] = "`$k` = ?"; $val[] = $v; }
        $val[] = $nuovo;
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET ' . implode(', ', $sql) . ' WHERE id = ?')->execute($val);
    }
    return $nuovo;
}

/**
 * Una figlia della campagna $id: 'nuovi' (Invia ai nuovi contatti: stesse impostazioni, solo chi non l'ha ricevuta) o
 * 'rinvio' (Rimanda ai non aperti: una volta sola, solo per le campagne inviate senza lotti). Nasce in bozza, con tutto
 * copiato, e si programma dal suo riepilogo. Ritorna l'id oppure il messaggio d'errore.
 */
function newsletter_campagna_figlia(int $id, string $tipo, string $oggetto = '') {
    newsletter_campagne_schema();
    $c = newsletter_campagna($id);
    if (!$c) return 'Campagna sconosciuta.';
    if ($tipo === 'rinvio') {
        $e = newsletter_rinvio_possibile($c);
        if ($e !== '') return $e;
    } elseif ($tipo === 'nuovi') {
        if (!in_array($c['stato'], ['inviata', 'in_invio', 'sospesa'], true)) return 'Si manda ai nuovi contatti solo una campagna già partita.';
        if ((int)$c['rinvio_di']) return 'Un rinvio ai non aperti non si manda ai nuovi contatti: usa la campagna di partenza.';
    } else {
        return 'Tipo sconosciuto.';
    }
    // 2.13.0: la figlia di un test A/B è una campagna normale con la versione vincente.
    $vinta = null;
    if ($c['tipo'] === 'ab' && function_exists('nl_ab')) {
        $ab = nl_ab($id);
        if ($ab['vincitore'] === '') return 'Il test A/B non ha ancora scelto la versione vincente: aspetta la fine del test.';
        $vinta = nl_ab_versione($c, $ab['vincitore'] === 'b' ? 'b' : 'a');
    }
    $nuovo = newsletter_campagna_duplica($id, array_keys(NL_CAMP_PARTI), false);
    if (!$nuovo) return 'Non riesco a creare la campagna.';
    if ($vinta) {
        $col = array_merge(['oggetto', 'anteprima'], NL_AB_DESIGN);
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET ' . implode(', ', array_map(fn($k) => "`$k` = ?", $col)) . ' WHERE id = ?')
            ->execute(array_merge(array_map(fn($k) => $vinta[$k], $col), [$nuovo]));
    }
    $nomeMadre = newsletter_4byte_fuori((string)($c['nome'] ?: $c['oggetto']));
    if ($tipo === 'rinvio') {
        $ogg = trim(str_replace(["\r", "\n"], ' ', $oggetto));
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET nome = ?, rinvio_di = ?, lotti = 1, lotti_intervallo = 0' . ($ogg !== '' ? ', oggetto = ?' : '') . ' WHERE id = ?')
            ->execute(array_merge([newsletter_4byte_dentro(mb_substr('Rinvio ai non aperti: ' . $nomeMadre, 0, 150)), $id], $ogg !== '' ? [newsletter_4byte_dentro(mb_substr($ogg, 0, 200))] : [], [$nuovo]));
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET rinvio_fatto = 1 WHERE id = ?')->execute([$id]);
    } else {
        $radice = (int)$c['padre_id'] ?: $id;
        db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET nome = ?, padre_id = ? WHERE id = ?')
            ->execute([newsletter_4byte_dentro(mb_substr('Ai nuovi contatti: ' . $nomeMadre, 0, 150)), $radice, $nuovo]);
    }
    return $nuovo;
}

/** '' se la campagna si può rimandare ai non aperti, altrimenti perché no. */
function newsletter_rinvio_possibile(array $c): string {
    if ($c['stato'] !== 'inviata') return 'Si rimanda ai non aperti solo una campagna inviata.';
    if ((int)$c['rinvio_di']) return 'Questa è già un rinvio ai non aperti: non si rimanda di nuovo.';
    if ((int)$c['rinvio_fatto']) return 'Questa campagna è già stata rimandata ai non aperti: si fa una volta sola.';
    if ((int)$c['lotti'] > 1) return 'Una campagna mandata a lotti non si rimanda ai non aperti.';
    if (newsletter_non_aperti((int)$c['id']) === 0) return 'Tutti quelli che l\'hanno ricevuta l\'hanno aperta: non c\'è nessuno a cui rimandarla.';
    return '';
}

/** Chi ha ricevuto la campagna, è ancora iscritto e non l'ha aperta. */
function newsletter_non_aperti(int $id): int {
    $par = [];
    $w = newsletter_campagna_where(['rinvio_di' => $id], $par);
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i WHERE i.stato = 'attivo' AND $w");
    $st->execute($par);
    return (int)$st->fetchColumn();
}

/** Elimina una campagna, solo in bozza. Se era un rinvio, la madre si può rimandare di nuovo. */
function newsletter_campagna_elimina(int $id): bool {
    newsletter_campagne_schema();
    $c = newsletter_campagna($id);
    if (!$c || $c['stato'] !== 'bozza') return false;
    db()->prepare('DELETE FROM ' . tbl('newsletter_campagne') . " WHERE id = ? AND stato = 'bozza'")->execute([$id]);
    if ((int)$c['rinvio_di']) db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET rinvio_fatto = 0 WHERE id = ?')->execute([(int)$c['rinvio_di']]);
    return true;
}

/** Annulla una campagna programmata, in invio o sospesa: le email non partite non partono più. */
function newsletter_campagna_annulla(int $id): bool {
    $st = db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'annullata', aggiornato = NOW() WHERE id = ? AND stato IN ('programmata', 'in_invio', 'sospesa')");
    $st->execute([$id]);
    if (!$st->rowCount()) return false;
    db()->prepare('UPDATE ' . tbl('newsletter_coda') . " SET stato = 'saltata' WHERE campagna_id = ? AND stato IN ('attesa', 'riserva')")->execute([$id]); // 2.13.0: anche il resto del test A/B
    return true;
}

/** Archivia (solo inviate, sospese, annullate) o toglie dall'archivio. */
function newsletter_campagna_archivia(int $id, bool $si): bool {
    $st = db()->prepare('UPDATE ' . tbl('newsletter_campagne') . ' SET archiviata = ? WHERE id = ?' . ($si ? " AND stato IN ('inviata', 'sospesa', 'annullata')" : ''));
    $st->execute([$si ? 1 : 0, $id]);
    return $st->rowCount() > 0;
}

/** Quante campagne per vista (per i numeri accanto ai nomi). */
function newsletter_campagne_conteggi(): array {
    newsletter_campagne_schema();
    $out = [];
    foreach (NL_CAMP_VISTE as $k => [, $w]) $out[$k] = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_campagne') . " WHERE $w")->fetchColumn();
    return $out;
}

// ------------------------------------------------------------
// L'email
// ------------------------------------------------------------

function newsletter_firma_web(int $c, int $i): string {
    return substr(hash_hmac('sha256', "web|$c|$i", moduli_segreto()), 0, 24);
}
function newsletter_token_preferenze(int $id): string {
    return substr(hash_hmac('sha256', 'preferenze|' . $id, moduli_segreto()), 0, 32);
}
/** Il link della versione web di una campagna per un contatto (firmato). */
function newsletter_link_web(int $c, int $i): string {
    return MODULI_URL . NEWSLETTER_WEB . '?c=' . $c . '&i=' . $i . '&t=' . newsletter_firma_web($c, $i);
}
/** Il link del centro preferenze di un contatto (firmato); senza contatto la pagina che spiega dove trovarlo. */
function newsletter_link_preferenze(int $i): string {
    return MODULI_URL . NEWSLETTER_PREFERENZE . ($i ? '?i=' . $i . '&t=' . newsletter_token_preferenze($i) : '');
}

/** L'indirizzo dell'azienda per il piede (Impostazioni della newsletter), '' se non è scritto. */
function newsletter_indirizzo_azienda(): string {
    return trim((string)get_setting('newsletter_indirizzo', ''));
}

/**
 * Le parti in più dell'email di una campagna, per newsletter_email(): link della versione web e delle preferenze,
 * indirizzo dell'azienda. Ognuna '' quando non serve: così una campagna di prima esce come prima.
 */
function nl_camp_extra(array $c, array $iscritto): array {
    $id = (int)($iscritto['id'] ?? 0);
    $cid = (int)($c['id'] ?? 0);
    return [
        'web' => !empty($c['link_web']) && $cid ? ($id ? newsletter_link_web($cid, $id) : MODULI_URL . NEWSLETTER_WEB) : '',
        'pref' => !empty($c['link_preferenze']) ? newsletter_link_preferenze($id) : '',
        'pref_sempre' => newsletter_link_preferenze($id),
        'indirizzo' => newsletter_indirizzo_azienda(),
    ];
}

/** {{ unsubscribe }}, {{ mirror }}, {{ update_profile }} (i nomi di Brevo) diventano i link veri. */
function nl_camp_link_speciali(string $s, string $uscita, array $x, bool $html): string {
    $f = fn($u) => $html ? h($u) : $u;
    return preg_replace_callback('/\{\{\s*(unsubscribe|mirror|update_profile)\s*\}\}/i', function ($m) use ($f, $uscita, $x) {
        return match (strtolower($m[1])) { 'unsubscribe' => $f($uscita), 'mirror' => $f($x['web'] ?: ''), default => $f($x['pref_sempre']) };
    }, $s);
}

/** L'email di una campagna con il codice HTML incollato (Design > Codice HTML). */
function nl_camp_html(array $c, array $iscritto, string $uscita, array $x, string $oggetto): string {
    $h = newsletter_4byte_fuori((string)($c['html'] ?? ''));
    // Niente script nelle email: nessun programma di posta li esegue, e la versione web non deve eseguirli.
    $h = preg_replace('~<script\b[^>]*>.*?</script\s*>~is', '', $h);
    $h = nl_camp_link_speciali($h, $uscita, $x, true);
    $h = newsletter_personalizza($h, $iscritto, true);
    if (!str_contains($h, h($uscita))) {
        // Il link di disiscrizione è obbligatorio: se il codice non lo ha, si aggiunge in fondo.
        $piede = '<p style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#888;text-align:center;margin:18px 0">'
            . '<a href="' . h($uscita) . '" style="color:#888">Esci dalla newsletter</a>, basta un clic.'
            . ($x['indirizzo'] !== '' ? '<br>' . str_replace("\n", '<br>', h($x['indirizzo'])) : '') . '</p>';
        $h = preg_match('~</body>~i', $h) ? preg_replace('~</body>~i', $piede . '</body>', $h, 1) : $h . $piede;
    }
    $testa = '';
    if ((string)$c['anteprima'] !== '') $testa .= '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">' . h(newsletter_4byte_fuori(newsletter_personalizza($c['anteprima'], $iscritto, false))) . str_repeat('&#8204;&nbsp;', 40) . '</div>';
    if ($x['web'] !== '') $testa .= '<p style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#888;text-align:center;margin:8px 0"><a href="' . h($x['web']) . '" style="color:#888">Vedi questa email nel browser</a></p>';
    if ($testa !== '') $h = preg_match('~<body\b[^>]*>~i', $h) ? preg_replace('~(<body\b[^>]*>)~i', '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $testa), $h, 1) : $testa . $h;
    if (!preg_match('~<html\b~i', $h)) $h = '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($oggetto) . '</title></head><body>' . $h . '</body></html>';
    return $h;
}

/** Il codice HTML di partenza quando si passa all'editor HTML: l'email dell'editor semplice, con {{ unsubscribe }}. */
function nl_camp_html_partenza(array $c, string $da = 'semplice'): string {
    $c['editor'] = $da === 'blocchi' ? 'blocchi' : 'semplice'; $c['link_web'] = 0; $c['link_preferenze'] = 0; $c['anteprima'] = '';
    [, $html] = newsletter_email($c, ['id' => 0, 'nome' => '', 'email' => ''], []);
    return str_replace('href="' . h(MODULI_URL . NEWSLETTER_ESCI) . '"', 'href="{{ unsubscribe }}"', $html);
}

/** UTM: i link verso il sito stesso prendono utm_source, utm_medium e utm_campaign (se non li hanno già). */
function nl_camp_utm(string $html, array $c): string {
    $nostro = strtolower((string)parse_url(MODULI_URL, PHP_URL_HOST));
    $nome = (string)($c['utm_campagna'] ?? '') ?: trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(newsletter_4byte_fuori((string)($c['nome'] ?? '')))), '-') ?: 'campagna-' . (int)($c['id'] ?? 0);
    return preg_replace_callback('~href="(https?://[^"]+)"~i', function ($m) use ($nostro, $nome) {
        $u = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $host = strtolower((string)parse_url($u, PHP_URL_HOST));
        if ($host === '' || preg_replace('/^www\./', '', $host) !== preg_replace('/^www\./', '', $nostro)) return $m[0];
        if (str_contains($u, 'utm_source=') || str_contains($u, NEWSLETTER_ESCI) || str_contains($u, NEWSLETTER_PREFERENZE) || str_contains($u, NEWSLETTER_WEB) || str_contains($u, '/api/nl.php')) return $m[0];
        $frammento = '';
        if (($p = strpos($u, '#')) !== false) { $frammento = substr($u, $p); $u = substr($u, 0, $p); }
        $u .= (str_contains($u, '?') ? '&' : '?') . 'utm_source=newsletter&utm_medium=email&utm_campaign=' . rawurlencode($nome);
        return 'href="' . h($u . $frammento) . '"';
    }, $html);
}

/** Il testo semplice scritto a mano di una campagna, con segnaposti e link speciali; null se si usa quello generato. */
function nl_camp_testo(array $c, array $iscritto, string $uscita, array $x): ?string {
    if (empty($c['testo_modificato']) || trim((string)($c['testo'] ?? '')) === '') return null;
    $t = nl_camp_link_speciali(newsletter_4byte_fuori((string)$c['testo']), $uscita, $x, false);
    $t = newsletter_4byte_fuori(newsletter_personalizza($t, $iscritto, false));
    // Il link di disiscrizione c'è sempre anche nel testo semplice.
    if (!str_contains($t, $uscita)) $t = rtrim($t) . "\n\n--\nEsci dalla newsletter: $uscita\n";
    if ($x['indirizzo'] !== '' && !str_contains($t, $x['indirizzo'])) $t = rtrim($t) . "\n" . $x['indirizzo'] . "\n";
    return $t;
}

// ------------------------------------------------------------
// Disiscrizione: il questionario
// ------------------------------------------------------------

/** I testi delle pagine pubbliche (Impostazioni della newsletter), con quelli di partenza per quello che è vuoto. */
function newsletter_pagine_testi(): array {
    $s = json_decode((string)get_setting('newsletter_pagine', ''), true);
    $s = is_array($s) ? $s : [];
    $base = newsletter_pagine_partenza();
    $out = [];
    foreach ($base as $k => $v) {
        if ($k === 'questionario') { $out[$k] = array_key_exists($k, $s) ? (bool)$s[$k] : true; continue; }
        if ($k === 'attributi') { $out[$k] = isset($s[$k]) && is_array($s[$k]) ? array_values(array_filter($s[$k], 'is_string')) : $v; continue; }
        if ($k === 'motivi') { $m = is_array($s[$k] ?? null) ? $s[$k] : []; foreach ($v as $mk => $mv) $out[$k][$mk] = trim((string)($m[$mk] ?? '')) !== '' ? (string)$m[$mk] : $mv; continue; }
        $out[$k] = trim((string)($s[$k] ?? '')) !== '' ? (string)$s[$k] : $v;
    }
    return $out;
}

function newsletter_pagine_partenza(): array {
    return [
        'uscita_titolo' => 'Vuoi uscire dalla newsletter?',
        'uscita_testo' => "L'indirizzo {email} smette di ricevere la {newsletter}.",
        'fatto_titolo' => 'Fatto. Sei fuori.',
        'fatto_testo' => "L'indirizzo {email} non riceverà più la newsletter.\nSe cambi idea, ti iscrivi di nuovo dalla pagina della newsletter.",
        'questionario' => true,
        'questionario_titolo' => 'Ci dici perché?',
        'grazie_testo' => 'Grazie: la tua risposta ci aiuta a scrivere email migliori.',
        'pref_titolo' => 'Le tue preferenze',
        'pref_testo' => "Scegli cosa ricevere all'indirizzo {email}. Puoi cambiare idea quando vuoi.",
        'motivi' => NL_USCITA_MOTIVI,
        'attributi' => ['NOME', 'COGNOME'],
    ];
}

/** Gli attributi che un contatto può cambiare dal centro preferenze: mai EMAIL (si cambia con una nuova iscrizione), né calcolati. */
function newsletter_preferenze_attributi(): array {
    $attr = newsletter_attributi();
    $out = [];
    foreach (newsletter_pagine_testi()['attributi'] as $n) {
        $a = $attr[$n] ?? null;
        if ($a && nl_attributo_preferibile($a)) $out[$n] = $a;
    }
    return $out;
}

/** Un attributo che si può mettere nel centro preferenze. */
function nl_attributo_preferibile(array $a): bool {
    if (in_array($a['categoria'], ['calculated', 'global'], true)) return false;
    if ((int)$a['sistema']) return in_array($a['colonna'], ['nome', 'cognome'], true);
    return true;
}

/** Le liste che si vedono nel centro preferenze. */
function newsletter_liste_pubbliche(): array {
    newsletter_campagne_schema();
    return db()->query('SELECT id, nome, descrizione FROM ' . tbl('newsletter_liste') . ' WHERE pubblica = 1 ORDER BY nome')->fetchAll();
}

/**
 * Salva il motivo della disiscrizione (questionario). Uno per uscita: una risposta nuova prende il posto della prima
 * data dopo la stessa uscita. $campagna e $flusso arrivano solo da un link firmato. Ritorna true se l'ha salvato.
 */
function newsletter_uscita_motivo_salva(int $id, string $motivo, string $altro, int $campagna = 0, int $flusso = 0): bool {
    newsletter_campagne_schema();
    if (!isset(NL_USCITA_MOTIVI[$motivo])) return false;
    $c = newsletter_contatto($id);
    if (!$c || !in_array($c['stato'], NL_BLOCCATI, true)) return false;
    $altro = $motivo === 'altro' ? mb_substr(newsletter_4byte_dentro(trim(preg_replace('/\s+/u', ' ', strip_tags($altro)))), 0, 500) : '';
    $da = (string)($c['blocco_at'] ?: $c['uscito_at'] ?: $c['aggiornato']);
    db()->prepare('DELETE FROM ' . tbl('newsletter_uscite_motivi') . ' WHERE contatto_id = ? AND creato >= ?')->execute([$id, $da]);
    db()->prepare('INSERT INTO ' . tbl('newsletter_uscite_motivi') . ' (contatto_id, campagna_id, flusso_id, motivo, altro, creato) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$id, $campagna, $flusso, $motivo, $altro]);
    newsletter_storia_scrivi($id, 'motivo_uscita', 'Perché è uscito: ' . NL_USCITA_MOTIVI[$motivo] . ($altro !== '' ? ' (' . $altro . ')' : ''));
    return true;
}

/** Quanti motivi per tipo (di una campagna, o di tutte con 0), e le ultime risposte "Altro". */
function newsletter_uscite_motivi_conta(int $campagna = 0): array {
    newsletter_campagne_schema();
    $w = $campagna ? ' WHERE campagna_id = ' . (int)$campagna : '';
    $n = array_fill_keys(array_keys(NL_USCITA_MOTIVI), 0);
    foreach (db()->query('SELECT motivo, COUNT(*) AS n FROM ' . tbl('newsletter_uscite_motivi') . "$w GROUP BY motivo") as $r) if (isset($n[$r['motivo']])) $n[$r['motivo']] = (int)$r['n'];
    $altri = db()->query('SELECT altro, creato FROM ' . tbl('newsletter_uscite_motivi') . ($w ? "$w AND" : ' WHERE') . " motivo = 'altro' AND altro <> '' ORDER BY id DESC LIMIT 10")->fetchAll();
    return ['n' => $n, 'totale' => array_sum($n), 'altri' => $altri];
}

/** La campagna (o il flusso) firmata del link di uscita: [campagna, flusso], [0, 0] se il link non la porta o la firma è sbagliata. */
function newsletter_uscita_traccia(int $id, array $q): array {
    $c = (int)($q['c'] ?? 0); $f = (int)($q['f'] ?? 0); $p = (int)($q['p'] ?? 0);
    $k = is_string($q['k'] ?? null) ? $q['k'] : '';
    if (!$id || (!$c && !$f) || $k === '' || !hash_equals(nl_firma($c, $f, $p, $id), $k)) return [0, 0];
    return [$c, $f];
}

// ------------------------------------------------------------
// Pagine pubbliche: disiscrizione e centro preferenze
// ------------------------------------------------------------

/** Un testo delle impostazioni in HTML: {email} e {newsletter}, un paragrafo per riga. */
function nl_pag_testo(string $t, array $c): string {
    $t = h($t);
    $t = str_replace(['{email}', '{newsletter}'], [h((string)($c['email'] ?? '')), h(lcfirst(newsletter_nome()))], $t);
    return implode('', array_map(fn($r) => '<p>' . $r . '</p>', array_filter(array_map('trim', explode("\n", $t)), fn($r) => $r !== '')));
}

function nl_pag(string $titolo, string $testo, string $azione = ''): string {
    $titolo = h($titolo);
    if (function_exists('vista_cortesia')) return vista_cortesia('Newsletter', $titolo, $testo, $azione);
    return '<div class="wrap"><article class="pagina-generica cortesia"><span class="occhiello">Newsletter</span><h1>' . $titolo . '</h1><div class="corpo">' . $testo . '</div>' . $azione . '</article></div>';
}

/**
 * La pagina /newsletter-esci/. $q: la query del link (i, t e la campagna firmata). $post: il POST (vuoto per un GET).
 * Prima il pulsante (un filtro antispam che apre i link non fa uscire nessuno), poi il questionario.
 */
function newsletter_pagina_esci(array $q, array $post, bool $isPost): string {
    newsletter_campagne_schema();
    $i = is_string($q['i'] ?? null) ? $q['i'] : ''; $t = is_string($q['t'] ?? null) ? $q['t'] : '';
    $c = newsletter_iscritto_da_uscita($i, $t);
    $tx = newsletter_pagine_testi();
    if (!$c) return nl_pag('Link non valido.', '<p>Per uscire dalla newsletter usa il link in fondo a una delle email che hai ricevuto.</p>');
    $id = (int)$c['id'];
    [$camp, $flusso] = newsletter_uscita_traccia($id, $q);
    $resto = '';
    foreach (['c', 'f', 'p', 'k'] as $x) if (isset($q[$x]) && is_string($q[$x]) && preg_match('/^[a-f0-9]{1,24}$/', $q[$x])) $resto .= "&$x=" . $q[$x];
    $azione = NEWSLETTER_ESCI . '?i=' . $id . '&t=' . $t . $resto;
    $grazie = '';
    if ($isPost && isset($post['motivo'])) {
        // Il questionario, dopo l'uscita.
        $m = is_string($post['motivo']) ? $post['motivo'] : '';
        if (newsletter_uscita_motivo_salva($id, $m, is_string($post['altro'] ?? null) ? $post['altro'] : '', $camp, $flusso)) $grazie = '<p role="status"><b>' . h($tx['grazie_testo']) . '</b></p>';
    } elseif ($isPost && $c['stato'] !== 'spam') {
        // Il pulsante della pagina, oppure l'uscita in un clic dei programmi di posta (List-Unsubscribe-Post).
        if ($c['stato'] === 'attivo') newsletter_uscita_registra($id, $q);
        newsletter_esci($id);
        $c = newsletter_contatto($id) ?: $c;
    }
    $pref = '<p><a href="' . h(newsletter_link_preferenze($id)) . '">Preferisci scegliere cosa ricevere? Apri le tue preferenze</a></p>';
    if (!in_array($c['stato'], ['uscito', 'spam'], true)) {
        return nl_pag($tx['uscita_titolo'], nl_pag_testo($tx['uscita_testo'], $c),
            '<form method="post" action="' . h($azione) . '"><button class="btn btn-viola" type="submit">Sì, toglimi dall&#039;elenco</button></form>' . (newsletter_liste_pubbliche() ? $pref : ''));
    }
    $corpo = nl_pag_testo($tx['fatto_testo'], $c) . $grazie;
    $dopo = '<p><a class="btn btn-filo" href="' . h(NEWSLETTER_PAGINA) . '">Pagina della newsletter</a></p>';
    if ($tx['questionario'] && $grazie === '' && $c['stato'] === 'uscito') {
        $scelte = '';
        foreach ($tx['motivi'] as $k => $et) {
            $scelte .= '<label class="spunta form-largo"><input type="radio" name="motivo" value="' . h($k) . '" required> <span>' . h($et) . '</span></label>';
        }
        // Le classi del modulo della newsletter del sito (sito.css e temi): stessi campi, stesso aspetto.
        $dopo = '<section class="modulo nl-questionario" aria-labelledby="nl-q-titolo"><h2 id="nl-q-titolo">' . h($tx['questionario_titolo']) . '</h2>'
            . '<form method="post" action="' . h($azione) . '"><div class="modulo-campi">' . $scelte
            . '<label class="form-largo" for="nl-altro"><span>Se hai scelto «' . h($tx['motivi']['altro']) . '», scrivi qui (facoltativo)</span>'
            . '<textarea id="nl-altro" name="altro" rows="3" maxlength="500"></textarea></label></div>'
            . '<button class="btn btn-viola" type="submit">Manda la risposta</button></form></section>' . $dopo;
    }
    return nl_pag($tx['fatto_titolo'], $corpo, $dopo);
}

/**
 * Il centro preferenze /newsletter-preferenze/?i=&t=: liste pubbliche (entra, esce da una lista sola), attributi
 * consentiti, esci da tutto. Ritorna [codice HTTP, HTML, rimando] (rimando: indirizzo per un 303 dopo "esci da tutto").
 */
function newsletter_pagina_preferenze(array $q, array $post, bool $isPost): array {
    newsletter_campagne_schema();
    $i = is_string($q['i'] ?? null) ? $q['i'] : ''; $t = is_string($q['t'] ?? null) ? $q['t'] : '';
    $id = ctype_digit($i) ? (int)$i : 0;
    $tx = newsletter_pagine_testi();
    if (!$id || !hash_equals(newsletter_token_preferenze($id), $t) || !($c = newsletter_contatto($id))) {
        return [200, nl_pag($tx['pref_titolo'], '<p>Questo link non è valido. Per cambiare le tue preferenze usa il link «Aggiorna le tue preferenze» in fondo a una delle email che hai ricevuto.</p>'), ''];
    }
    $azione = NEWSLETTER_PREFERENZE . '?i=' . $id . '&t=' . $t;
    $msg = '';
    $liste = newsletter_liste_pubbliche();
    $attr = newsletter_preferenze_attributi();
    if ($isPost) {
        $cosa = is_string($post['azione'] ?? null) ? $post['azione'] : '';
        if ($cosa === 'esci' && $c['stato'] === 'attivo') {
            newsletter_esci($id);
            return [303, '', newsletter_link_uscita($id)];
        }
        if ($cosa === 'rientra' && $c['stato'] === 'uscito') {
            $e = newsletter_richiesta(['email' => $c['email'], 'nome' => $c['nome'], 'cognome' => $c['cognome']]);
            $msg = $e === '' ? 'Ti abbiamo scritto all\'indirizzo ' . $c['email'] . ': apri l\'email e conferma, e da lì ricevi di nuovo la newsletter.' : 'Non riesco a mandarti l\'email di conferma adesso: riprova fra qualche minuto.';
        }
        if ($cosa === 'salva' && $c['stato'] === 'attivo') {
            $dati = [];
            foreach ($attr as $n => $a) if (isset($post['a'][$n])) $dati[$n] = is_array($post['a'][$n]) ? array_values(array_filter($post['a'][$n], 'is_scalar')) : (string)$post['a'][$n];
            if ($dati) newsletter_valori_salva($id, $dati);
            $voglio = array_map('intval', is_array($post['liste'] ?? null) ? $post['liste'] : []);
            foreach ($liste as $l) newsletter_preferenze_lista($id, (int)$l['id'], in_array((int)$l['id'], $voglio, true));
            $msg = 'Preferenze salvate.';
            $c = newsletter_contatto($id) ?: $c;
        }
    }
    $avviso = $msg !== '' ? '<p role="status" style="padding:.7rem 1rem;border-radius:8px;background:rgba(124,58,237,.08)"><b>' . h($msg) . '</b></p>' : '';
    if ($c['stato'] !== 'attivo') {
        $testo = $c['stato'] === 'in_attesa'
            ? '<p>L\'indirizzo ' . h($c['email']) . ' aspetta ancora la conferma: apri l\'email che ti abbiamo mandato e premi il pulsante.</p>'
            : '<p>L\'indirizzo ' . h($c['email']) . ' non riceve più le nostre email.</p>';
        $form = $c['stato'] === 'uscito' ? '<form method="post" action="' . h($azione) . '"><input type="hidden" name="azione" value="rientra"><button class="btn btn-viola" type="submit">Iscrivimi di nuovo</button></form><p style="font-size:.9rem">Ti mandiamo un\'email: torni dentro quando confermi.</p>' : '';
        return [200, nl_pag($tx['pref_titolo'], $avviso . $testo, $form), ''];
    }
    $mie = newsletter_preferenze_mie($id);
    $valori = newsletter_valori_di([$id])[$id] ?? [];
    $campi = '';
    foreach ($attr as $n => $a) {
        $v = $valori[$n] ?? '';
        $et = h(newsletter_4byte_fuori(newsletter_attributo_etichetta($a)));
        $fid = 'nlp-' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $n));
        $campi .= $a['tipo'] === 'multiple-choice'
            ? '<fieldset class="multiscelta"><legend>' . $et . '</legend>' . nl_pref_campo($a, $n, $v, $fid) . '</fieldset>'
            : '<label for="' . $fid . '"><span>' . $et . '</span>' . nl_pref_campo($a, $n, $v, $fid) . '</label>';
    }
    $scelte = '';
    foreach ($liste as $l) {
        $si = in_array((int)$l['id'], $mie, true);
        $scelte .= '<label class="spunta form-largo"><input type="checkbox" name="liste[]" value="' . (int)$l['id'] . '"' . ($si ? ' checked' : '') . '> <span><b>' . h(newsletter_4byte_fuori((string)$l['nome'])) . '</b>'
            . ((string)$l['descrizione'] !== '' ? '<br><small>' . h(newsletter_4byte_fuori((string)$l['descrizione'])) . '</small>' : '') . '</span></label>';
    }
    $corpo = $avviso . nl_pag_testo($tx['pref_testo'], $c);
    $esci = '<section class="modulo" aria-label="Le tue preferenze"><form method="post" action="' . h($azione) . '" class="nl-preferenze"><input type="hidden" name="azione" value="salva">'
        . ($campi !== '' ? '<h2>I tuoi dati</h2><div class="modulo-campi">' . $campi . '</div>' : '')
        . ($scelte !== '' ? '<h2>Cosa vuoi ricevere</h2><div class="modulo-campi">' . $scelte . '</div>' : '')
        . '<button class="btn btn-viola" type="submit">Salva le preferenze</button></form></section>'
        . '<form method="post" action="' . h($azione) . '"><input type="hidden" name="azione" value="esci">'
        . '<p>Non vuoi più nessuna email da noi?</p><button class="btn btn-filo" type="submit">Esci da tutte le email</button></form>';
    return [200, nl_pag($tx['pref_titolo'], $corpo, $esci), ''];
}

/** Il campo di un attributo nel centro preferenze (nomi a[NOME], valori già scritti). */
function nl_pref_campo(array $a, string $n, $v, string $fid): string {
    $nm = 'a[' . h($n) . ']';
    $st = '';
    switch ($a['tipo']) {
        case 'category':
            $o = '<option value="">(nessuna scelta)</option>';
            foreach ($a['opzioni'] as $k => $et) $o .= '<option value="' . (int)$k . '"' . ((string)$v === (string)$k ? ' selected' : '') . '>' . h(newsletter_4byte_fuori($et)) . '</option>';
            return '<select id="' . $fid . '" name="' . $nm . '" ' . $st . '>' . $o . '</select>';
        case 'multiple-choice':
            $o = '<input type="hidden" name="' . $nm . '[]" value="">';
            foreach ($a['opzioni'] as $k => $et) $o .= '<label class="spunta"><input type="checkbox" name="' . $nm . '[]" value="' . (int)$k . '"' . (in_array((int)$k, array_map('intval', (array)$v), true) ? ' checked' : '') . '> ' . h(newsletter_4byte_fuori($et)) . '</label>';
            return '<span id="' . $fid . '">' . $o . '</span>';
        case 'boolean':
            return '<select id="' . $fid . '" name="' . $nm . '" ' . $st . '><option value="">(nessuna scelta)</option><option value="1"' . ((string)$v === '1' ? ' selected' : '') . '>Sì</option><option value="0"' . ((string)$v === '0' ? ' selected' : '') . '>No</option></select>';
        case 'date':
            return '<input type="date" id="' . $fid . '" name="' . $nm . '" value="' . h((string)$v) . '" ' . $st . '>';
        case 'float':
            return '<input type="number" step="any" id="' . $fid . '" name="' . $nm . '" value="' . h((string)$v) . '" ' . $st . '>';
    }
    return '<input type="text" id="' . $fid . '" name="' . $nm . '" maxlength="' . ((int)$a['sistema'] ? 100 : 500) . '" value="' . h(newsletter_4byte_fuori((string)$v)) . '" ' . $st . '>';
}

/** Gli id delle liste pubbliche in cui il contatto riceve (è dentro e non ne è uscito). */
function newsletter_preferenze_mie(int $id): array {
    $st = db()->prepare('SELECT l.lista_id FROM ' . tbl('newsletter_iscritti_liste') . ' l JOIN ' . tbl('newsletter_liste') . ' x ON x.id = l.lista_id AND x.pubblica = 1
        WHERE l.iscritto_id = ?' . newsletter_sql_uscite_lista());
    $st->execute([$id]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Dal centro preferenze: il contatto vuole (o non vuole più) una lista pubblica. Uscire = una riga in
 * newsletter_liste_uscite (resta nella lista, ma non riceve le sue campagne); rientrare = la riga si toglie, o entra.
 */
function newsletter_preferenze_lista(int $id, int $lista, bool $vuole): void {
    $p = db()->prepare('SELECT pubblica FROM ' . tbl('newsletter_liste') . ' WHERE id = ?');
    $p->execute([$lista]);
    if ((int)$p->fetchColumn() !== 1) return;
    $m = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ? AND lista_id = ?');
    $m->execute([$id, $lista]);
    $dentro = (int)$m->fetchColumn() > 0;
    $u = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_liste_uscite') . ' WHERE contatto_id = ? AND lista_id = ?');
    $u->execute([$id, $lista]);
    $uscito = (int)$u->fetchColumn() > 0;
    $nome = newsletter_nome_lista($lista);
    if ($vuole) {
        if ($uscito) {
            db()->prepare('DELETE FROM ' . tbl('newsletter_liste_uscite') . ' WHERE contatto_id = ? AND lista_id = ?')->execute([$id, $lista]);
            newsletter_storia_scrivi($id, 'lista_rientrato', 'Dal centro preferenze: riceve di nuovo la lista ' . $nome, $lista);
        }
        if (!$dentro) newsletter_in_lista($id, $lista);
    } elseif ($dentro && !$uscito) {
        db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_liste_uscite') . ' (contatto_id, lista_id, quando) VALUES (?, ?, NOW())')->execute([$id, $lista]);
        newsletter_storia_scrivi($id, 'lista_uscito', 'Dal centro preferenze: non riceve più la lista ' . $nome, $lista);
        // Le email di quella lista ancora in coda non partono, se non arrivano anche da un'altra lista o da un segmento della campagna.
    }
}

/** La versione web: [codice, html]. Firmata per campagna e contatto; nessun pixel, nessun conteggio. */
function newsletter_pagina_web(array $q): array {
    newsletter_campagne_schema();
    $c = is_string($q['c'] ?? null) && ctype_digit($q['c']) ? (int)$q['c'] : 0;
    $i = is_string($q['i'] ?? null) && ctype_digit($q['i']) ? (int)$q['i'] : 0;
    $t = is_string($q['t'] ?? null) ? $q['t'] : '';
    if (!$c || !$i || !hash_equals(newsletter_firma_web($c, $i), $t)) return [404, ''];
    $camp = newsletter_campagna($c);
    $iscr = newsletter_contatto($i);
    if (!$camp || !$iscr) return [404, ''];
    if (function_exists('nl_ab_versione') && nl_ab_e($camp)) $camp = nl_ab_versione($camp, nl_ab_versione_di($c, $i)); // 2.13.0: la versione che ha ricevuto
    [, $html] = newsletter_email($camp, ['id' => $i, 'nome' => (string)$iscr['nome'], 'email' => (string)$iscr['email']], []);
    return [200, $html];
}
