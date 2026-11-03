<?php
/**
 * 2.9.0: importazione ed esportazione dei contatti come Brevo, e la lista dei test (docs-sviluppo/brevo-confronto.md,
 * fase 2, capitoli 1.3, 1.5 e 3.5).
 *
 * Importazione, i quattro passi di Brevo: 1 Carica (CSV, TXT, XLSX in UTF-8 o Windows-1252, oppure Copia e incolla),
 * 2 Abbina i dati (colonna per colonna: un attributo, "Crea un nuovo attributo", "Non importare"; EMAIL obbligatoria),
 * 3 Scegli la lista (una o più, o una nuova in una cartella), 4 Concludi (opzioni e consenso), poi il lavoro a giri dalla
 * pagina e dal cron (newsletter_lavori_giro). Il file caricato si riscrive subito in un file di righe JSON, nella cartella
 * privata dei contatti (newsletter_dir_privata): i giri lo leggono da dove erano arrivati (posizione in byte).
 * Regole di Brevo: i doppioni del file si tolgono da soli; chi c'è già non diventa un doppione; senza "Aggiorna" chi
 * c'è già resta com'è ma entra nelle liste; con "Celle vuote" una cella vuota cancella il valore; un valore del tipo
 * sbagliato si ignora. Scelte di plumeCMS (da prima della 2.9.0): chi è in lista nera o ha chiesto di cancellare i suoi
 * dati non torna iscritto e non entra nelle liste (conta fra gli invariati, o fra gli errori se i dati sono cancellati).
 *
 * Esportazione: un CSV con gli attributi scelti e il separatore (punto e virgola o virgola), sui contatti scelti, su una
 * lista (tutti, iscritti, in lista nera, disiscritti solo da quella lista) o su una campagna (aperto, non aperto,
 * cliccato, non cliccato, disiscritti, rimbalzi). A giri anche lei; il file resta 7 giorni.
 *
 * XLSX senza librerie: ZipArchive e XMLReader, con le difese: nomi interni controllati, niente DTD né entità, un tetto
 * ai byte decompressi letto davvero (non quello scritto nello zip).
 */

require_once __DIR__ . '/newsletter-tag.php'; // 2.23.0: la colonna dei tag (file nuovo: opcache non ne ha una copia vecchia)

const NL_IMP_COLONNE_MAX = 200;
const NL_IMP_CELLA_MAX = 1000;
const NL_IMP_PEZZO = 200;            // righe per transazione
const NL_XLSX_TETTO = 41943040;      // 40 MB decompressi in tutto: oltre, è una "zip bomba" o un file da dividere
const NL_FILE_GIORNI = 7;            // esportazioni e scarti restano 7 giorni
const NL_TEST_MASSIMO = 20;          // Brevo: al massimo 20 indirizzi nella lista dei test
/** Le colonne di stato che l'esportazione aggiunge dopo gli attributi (come la 2.8.0). */
const NL_ESP_EXTRA = ['ISCRITTO' => 'Iscritto (sì o no)', 'LISTA_NERA' => 'In lista nera (sì o no)', 'MOTIVO' => 'Motivo della lista nera', 'LISTE' => 'Liste', 'TAG' => 'Tag']; // 2.23.0: TAG
/** 2.23.0: i nomi di colonna che si abbinano da soli a "Tag" (_TAG), se non c'è un attributo con quel nome. */
const NL_IMP_TAG_NOMI = ['TAG', 'TAGS', 'ETICHETTA', 'ETICHETTE'];
/** Nomi di colonna che si abbinano da soli a un attributo (oltre al nome uguale). _CONSENSO = la data del consenso. */
const NL_IMP_SINONIMI = [
    'E_MAIL' => 'EMAIL', 'MAIL' => 'EMAIL', 'INDIRIZZO_EMAIL' => 'EMAIL', 'EMAIL_ADDRESS' => 'EMAIL', 'POSTA_ELETTRONICA' => 'EMAIL',
    'FIRST_NAME' => 'NOME', 'PRENOM' => 'NOME', 'LAST_NAME' => 'COGNOME', 'SURNAME' => 'COGNOME', 'NOM' => 'COGNOME',
    'CONSENSO' => '_CONSENSO', 'CONSENSO_AT' => '_CONSENSO', 'DATA_CONSENSO' => '_CONSENSO', 'DATA_ISCRIZIONE' => '_CONSENSO',
    'OPTIN_DATE' => '_CONSENSO', 'DATE_OPTIN' => '_CONSENSO', 'CONSENT_DATE' => '_CONSENSO',
];

function newsletter_importa_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    newsletter_schema();
    newsletter_tag_schema(); // 2.23.0: prima dei giri, che lavorano dentro una transazione (un CREATE TABLE la chiuderebbe)
    $tabelle = [
        'newsletter_importazioni' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_importazioni') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `utente_id` INT(11) NOT NULL DEFAULT 0,
            `origine` VARCHAR(10) NOT NULL DEFAULT 'file',
            `nome_file` VARCHAR(255) NOT NULL DEFAULT '',
            `file` VARCHAR(100) NOT NULL DEFAULT '',
            `intestazione` TEXT,
            `anteprima` MEDIUMTEXT,
            `mappatura` TEXT,
            `opzioni` TEXT,
            `liste` VARCHAR(255) NOT NULL DEFAULT '',
            `passo` TINYINT NOT NULL DEFAULT 2,
            `stato` VARCHAR(10) NOT NULL DEFAULT 'bozza',
            `righe` INT(11) NOT NULL DEFAULT 0,
            `fatte` INT(11) NOT NULL DEFAULT 0,
            `posizione` BIGINT NOT NULL DEFAULT 0,
            `nuovi` INT(11) NOT NULL DEFAULT 0,
            `aggiornati` INT(11) NOT NULL DEFAULT 0,
            `invariati` INT(11) NOT NULL DEFAULT 0,
            `doppioni` INT(11) NOT NULL DEFAULT 0,
            `errori` INT(11) NOT NULL DEFAULT 0,
            `dettagli` TEXT,
            `scarti_file` VARCHAR(100) NOT NULL DEFAULT '',
            `errore` VARCHAR(255) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            `finito` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`), KEY `stato` (`stato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // Le email già viste in un'importazione in corso: i doppioni del file si riconoscono anche fra un giro e l'altro.
        'newsletter_importa_viste' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_importa_viste') . " (
            `importazione_id` INT(11) NOT NULL,
            `impronta` CHAR(32) NOT NULL,
            PRIMARY KEY (`importazione_id`, `impronta`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_esportazioni' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_esportazioni') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `utente_id` INT(11) NOT NULL DEFAULT 0,
            `descrizione` VARCHAR(255) NOT NULL DEFAULT '',
            `filtro` MEDIUMTEXT,
            `colonne` TEXT,
            `separatore` CHAR(1) NOT NULL DEFAULT ';',
            `stato` VARCHAR(10) NOT NULL DEFAULT 'in_corso',
            `file` VARCHAR(100) NOT NULL DEFAULT '',
            `righe` INT(11) NOT NULL DEFAULT 0,
            `totale` INT(11) NOT NULL DEFAULT 0,
            `ultimo_id` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            `finito` DATETIME DEFAULT NULL,
            `scade` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `stato` (`stato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME);
    if (get_setting('newsletter_importa_impronta', '') === $impronta) return;
    foreach ($tabelle as $sql) db()->exec($sql);
    save_setting('newsletter_importa_impronta', $impronta);
}

/** La cartella privata dei file dei contatti (importazioni, scarti, esportazioni): nome a caso, chiusa al web. */
function newsletter_dir_privata(): string {
    $nome = (string)get_setting('newsletter_cartella_file', '');
    if (!preg_match('/^contatti-[a-f0-9]{16}$/', $nome)) {
        $nome = 'contatti-' . bin2hex(random_bytes(8));
        save_setting('newsletter_cartella_file', $nome);
    }
    $d = cms_uploads_dir() . '/' . $nome;
    if (!is_dir($d)) @mkdir($d, 0755, true);
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "# File dei contatti di plumeCMS: si scaricano solo dal pannello.\nRequire all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\nOptions -Indexes\n");
    if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
    return $d;
}

/** Il percorso di un file della cartella privata, solo se il nome è uno dei nostri (niente percorsi). */
function newsletter_file_privato(string $nome): string {
    if (!preg_match('/^(imp|scarti|esp)-\d+-[a-f0-9]{12}\.(jsonl|csv)$/', $nome)) return '';
    return newsletter_dir_privata() . '/' . $nome;
}

// ------------------------------------------------------------
// Lettura dei file: CSV, TXT, XLSX, copia e incolla
// ------------------------------------------------------------

/** Una cella pulita: senza controlli, al massimo NL_IMP_CELLA_MAX caratteri. */
function newsletter_importa_cella($v): string {
    $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$v) ?? '');
    return mb_substr($v, 0, NL_IMP_CELLA_MAX);
}

/** Le righe di un CSV o TXT: codifica (UTF-8, altrimenti Windows-1252) e separatore indovinati. Un generatore. */
function newsletter_csv_righe(string $file): Generator {
    $f = fopen($file, 'rb');
    if (!$f) throw new RuntimeException('Il file non si legge: caricalo di nuovo.');
    $utf8 = true; $prima = '';
    while (($l = fgets($f)) !== false) {
        if ($prima === '' && trim($l) !== '') $prima = $l;
        if (!mb_check_encoding($l, 'UTF-8')) { $utf8 = false; break; }
    }
    if (!$utf8) {
        // Windows-1252 (Excel per Windows): si riscrive in UTF-8, riga per riga (un byte per carattere: le righe non si rompono).
        $tmp = tempnam(sys_get_temp_dir(), 'nlimp');
        $o = fopen($tmp, 'wb');
        rewind($f);
        while (($l = fgets($f)) !== false) fwrite($o, mb_convert_encoding($l, 'UTF-8', 'Windows-1252'));
        fclose($o); fclose($f);
        $f = fopen($tmp, 'rb');
        $prima = mb_convert_encoding($prima, 'UTF-8', 'Windows-1252');
        @unlink($tmp); // resta aperto finché serve (sui sistemi in cui il file si toglie davvero dopo la chiusura)
    }
    rewind($f);
    $prima = preg_replace('/^\xEF\xBB\xBF/', '', $prima);
    $conti = ["\t" => substr_count($prima, "\t"), ';' => substr_count($prima, ';'), ',' => substr_count($prima, ','), '|' => substr_count($prima, '|')];
    arsort($conti);
    $sep = (string)array_key_first($conti);
    if ($conti[$sep] === 0) $sep = ';';
    $n = 0;
    while (($r = fgetcsv($f, 0, $sep, '"', '')) !== false) {
        if ($n++ === 0 && isset($r[0])) $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$r[0]);
        if ($r === [null]) continue;
        yield $r;
    }
    fclose($f);
}

/** Legge una parte di uno zip con un tetto ai byte decompressi (contati davvero, non quelli dichiarati). */
function newsletter_zip_leggi(ZipArchive $z, string $nome, int &$resto): ?string {
    if ($z->locateName($nome) === false) return null;
    $s = $z->getStream($nome);
    if (!$s) throw new RuntimeException('Il file Excel è rovinato: una sua parte non si apre. Salvalo di nuovo da Excel o LibreOffice e riprova.');
    $out = '';
    while (!feof($s)) {
        $pezzo = fread($s, 65536);
        if ($pezzo === false) break;
        $resto -= strlen($pezzo);
        if ($resto < 0) { fclose($s); throw new RuntimeException('Il file Excel, aperto, diventa troppo grande (oltre ' . (NL_XLSX_TETTO >> 20) . ' MB): dividilo in più file, oppure salvalo come CSV.'); }
        $out .= $pezzo;
    }
    fclose($s);
    if (stripos($out, '<!DOCTYPE') !== false || stripos($out, '<!ENTITY') !== false) throw new RuntimeException('Il file Excel contiene una dichiarazione DTD, che un foglio di calcolo vero non ha: per sicurezza non si legge. Salvalo di nuovo da Excel o LibreOffice.');
    return $out;
}

/** Un XMLReader su un testo XML già controllato (niente rete, niente entità esterne). */
function newsletter_xml(string $xml): XMLReader {
    $r = new XMLReader();
    if (!$r->XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT)) throw new RuntimeException('Il file Excel è rovinato: una sua parte non si legge.');
    return $r;
}

/**
 * Le righe del primo foglio di un XLSX (Excel, LibreOffice): testi condivisi, testi in linea, numeri, sì/no e date (i
 * numeri seriali con un formato di data diventano AAAA-MM-GG). Un generatore; RuntimeException con il messaggio in parole.
 */
function newsletter_xlsx_righe(string $file): Generator {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Questo server non legge i file Excel (manca l\'estensione zip di PHP): salva il file come CSV e caricalo così.');
    $z = new ZipArchive();
    if ($z->open($file, ZipArchive::RDONLY) !== true) throw new RuntimeException('Il file non è un file Excel (.xlsx) valido. Un .xls vecchio va salvato come .xlsx o come CSV.');
    $dichiarati = 0;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $st = $z->statIndex($i);
        $n = (string)($st['name'] ?? '');
        // Nomi interni: niente percorsi assoluti, risalite, barre rovesciate, due punti o caratteri di controllo.
        if ($n === '' || strlen($n) > 255 || $n[0] === '/' || str_contains($n, '\\') || str_contains($n, ':') || preg_match('~(^|/)\.\.(/|$)~', $n) || preg_match('/[\x00-\x1F]/', $n)) {
            $z->close();
            throw new RuntimeException('Il file Excel contiene parti con nomi non ammessi: per sicurezza non si legge. Salvalo di nuovo da Excel o LibreOffice.');
        }
        $dichiarati += (int)($st['size'] ?? 0);
    }
    if ($z->numFiles > 2000 || $dichiarati > NL_XLSX_TETTO) { $z->close(); throw new RuntimeException('Il file Excel, aperto, diventa troppo grande (oltre ' . (NL_XLSX_TETTO >> 20) . ' MB): dividilo in più file, oppure salvalo come CSV.'); }
    $resto = NL_XLSX_TETTO;
    try {
        // Il primo foglio: workbook.xml dice il suo rId, i rel dicono il file.
        $foglio = 'xl/worksheets/sheet1.xml';
        $wb = newsletter_zip_leggi($z, 'xl/workbook.xml', $resto);
        if ($wb === null) throw new RuntimeException('Il file non è un file Excel (.xlsx) valido: manca il foglio. Salvalo di nuovo da Excel o LibreOffice.');
        $data1904 = (bool)preg_match('/date1904="(1|true)"/', $wb);
        $rid = preg_match('/<(?:\w+:)?sheet\b[^>]*\br:id="([^"]+)"/', $wb, $m) ? $m[1] : (preg_match('/<(?:\w+:)?sheet\b[^>]*\bid="([^"]+)"/', $wb, $m) ? $m[1] : '');
        $rels = newsletter_zip_leggi($z, 'xl/_rels/workbook.xml.rels', $resto);
        if ($rid !== '' && $rels !== null && preg_match_all('/<Relationship\b[^>]*>/', $rels, $tutte)) {
            foreach ($tutte[0] as $t) {
                if (!preg_match('/\bId="' . preg_quote($rid, '/') . '"/', $t) || !preg_match('/\bTarget="([^"]+)"/', $t, $tg)) continue;
                $dest = preg_replace('~^/?(xl/)?~', '', $tg[1]);
                if (preg_match('~^worksheets/[A-Za-z0-9_\-]{1,60}\.xml$~', $dest)) $foglio = 'xl/' . $dest;
            }
        }
        // Testi condivisi
        $condivisi = [];
        $ss = newsletter_zip_leggi($z, 'xl/sharedStrings.xml', $resto);
        if ($ss !== null) {
            $r = newsletter_xml($ss);
            while (@$r->read()) if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'si') $condivisi[] = $r->readString();
            $r->close();
        }
        // Stili: quali formati di cella sono date
        $date = [];
        $sty = newsletter_zip_leggi($z, 'xl/styles.xml', $resto);
        if ($sty !== null) {
            $formati = [];
            $r = newsletter_xml($sty);
            $dentro = false; $k = 0;
            while (@$r->read()) {
                if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'numFmt') $formati[(int)$r->getAttribute('numFmtId')] = (string)$r->getAttribute('formatCode');
                if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'cellXfs') $dentro = !$r->isEmptyElement;
                elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'cellXfs') $dentro = false;
                elseif ($dentro && $r->nodeType === XMLReader::ELEMENT && $r->localName === 'xf') {
                    $id = (int)$r->getAttribute('numFmtId');
                    $codice = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $formati[$id] ?? '');
                    $date[$k++] = ($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47) || ($id >= 27 && $id <= 36) || ($id >= 50 && $id <= 58)
                        || ($id >= 164 && preg_match('/[dmyh]/i', $codice) && !preg_match('/[0#?]/', $codice));
                }
            }
            $r->close();
        }
        $xml = newsletter_zip_leggi($z, $foglio, $resto);
        if ($xml === null) throw new RuntimeException('Il file Excel non ha fogli da leggere.');
    } finally {
        $z->close();
    }
    $r = newsletter_xml($xml);
    unset($xml);
    $riga = null; $col = -1; $t = ''; $s = 0; $v = '';
    while (@$r->read()) {
        if ($r->nodeType === XMLReader::ELEMENT) {
            switch ($r->localName) {
                case 'row':
                    $riga = []; $col = -1;
                    if ($r->isEmptyElement) $riga = null;
                    break;
                case 'c':
                    $ref = (string)$r->getAttribute('r');
                    if (preg_match('/^([A-Z]{1,3})\d*$/', $ref, $m)) { $col = 0; foreach (str_split($m[1]) as $ch) $col = $col * 26 + ord($ch) - 64; $col--; } else $col++;
                    $t = (string)$r->getAttribute('t'); $s = (int)$r->getAttribute('s'); $v = '';
                    if ($r->isEmptyElement && $riga !== null && $col < NL_IMP_COLONNE_MAX) $riga[$col] = '';
                    break;
                case 'v': $v .= $r->readString(); break;
                case 'is': $v .= $r->readString(); break;
            }
        } elseif ($r->nodeType === XMLReader::END_ELEMENT) {
            if ($r->localName === 'c' && $riga !== null && $col >= 0 && $col < NL_IMP_COLONNE_MAX) {
                if ($t === 's') $v = (string)($condivisi[(int)$v] ?? '');
                elseif ($t === 'e') $v = '';
                elseif (($t === '' || $t === 'n') && is_numeric($v)) {
                    $num = (float)$v;
                    if (!empty($date[$s])) {
                        $giorni = $num + ($data1904 ? 1462 : 0);
                        $ts = (int)round(($giorni - 25569) * 86400);
                        $v = $giorni >= 1 ? gmdate(fmod($num, 1) > 0.00001 ? 'Y-m-d H:i' : 'Y-m-d', $ts) : gmdate('H:i', $ts);
                    } else {
                        $v = rtrim(rtrim(sprintf('%.15F', (float)sprintf('%.15g', $num)), '0'), '.');
                    }
                }
                $riga[$col] = $v;
            } elseif ($r->localName === 'row' && $riga !== null) {
                if ($riga) { $max = max(array_keys($riga)); $piena = array_fill(0, $max + 1, ''); foreach ($riga as $k => $x) $piena[$k] = $x; yield $piena; }
                $riga = null;
            }
        }
    }
    $r->close();
}

/**
 * Passo 1: un file caricato (o il testo incollato) diventa un'importazione in bozza, con il file riscritto in righe JSON.
 * $origine: 'file' o 'incolla'. Ritorna ['id' => n] oppure ['errore' => messaggio in parole semplici].
 */
function newsletter_importa_prepara(string $percorso, string $nomeFile, string $origine, int $utente = 0): array {
    newsletter_importa_schema();
    $est = strtolower(pathinfo($nomeFile, PATHINFO_EXTENSION));
    if ($origine === 'incolla') $est = 'txt';
    if (!in_array($est, ['csv', 'txt', 'xlsx'], true)) {
        return ['errore' => $est === 'xls' ? 'I file .xls (Excel vecchio) non si leggono: aprilo in Excel o LibreOffice e salvalo come .xlsx o .csv.' : 'Questo tipo di file non si legge: carica un file .csv, .txt o .xlsx.'];
    }
    if (!is_file($percorso) || filesize($percorso) === 0) return ['errore' => $origine === 'incolla' ? 'Incolla i contatti nella casella: la prima riga con i nomi delle colonne (per esempio EMAIL, NOME).' : 'Il file è vuoto: scegline un altro.'];
    if ($est === 'xlsx') {
        $testa = (string)file_get_contents($percorso, false, null, 0, 4);
        if ($testa !== "PK\x03\x04") return ['errore' => 'Il file ha il nome di un file Excel ma non lo è: salvalo di nuovo da Excel o LibreOffice come .xlsx, oppure come CSV.'];
    }
    $ora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO ' . tbl('newsletter_importazioni') . " (utente_id, origine, nome_file, stato, passo, creato, aggiornato) VALUES (?, ?, ?, 'bozza', 2, ?, ?)")
        ->execute([$utente, $origine === 'incolla' ? 'incolla' : 'file', mb_substr(newsletter_testo_pulito(basename($nomeFile), 200), 0, 200), $ora, $ora]);
    $id = (int)db()->lastInsertId();
    $nome = 'imp-' . $id . '-' . bin2hex(random_bytes(6)) . '.jsonl';
    $dest = newsletter_dir_privata() . '/' . $nome;
    $o = fopen($dest, 'wb');
    $righe = 0; $testa = null; $anteprima = [];
    try {
        if (!$o) throw new RuntimeException('Non riesco a scrivere nella cartella dei file (uploads): controlla i permessi delle cartelle con il tuo hosting.');
        $gen = $est === 'xlsx' ? newsletter_xlsx_righe($percorso) : newsletter_csv_righe($percorso);
        foreach ($gen as $r) {
            $r = array_map('newsletter_importa_cella', array_slice($r, 0, NL_IMP_COLONNE_MAX));
            if (implode('', $r) === '') continue; // riga vuota
            if ($testa === null) {
                // Senza intestazioni (una colonna di indirizzi incollata): le colonne prendono un nome e la riga è un dato.
                $haEmail = false;
                foreach ($r as $c) if (filter_var($c, FILTER_VALIDATE_EMAIL)) $haEmail = true;
                $testa = [];
                foreach ($r as $i => $c) $testa[] = $haEmail ? 'Colonna ' . ($i + 1) : ($c !== '' ? $c : 'Colonna ' . ($i + 1));
                fwrite($o, json_encode($testa, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
                if (!$haEmail) continue;
            }
            fwrite($o, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            if (count($anteprima) < 5) $anteprima[] = $r;
            $righe++;
        }
        if ($testa === null || $righe === 0) throw new RuntimeException('Nel file non ci sono contatti: serve una riga con i nomi delle colonne (per esempio EMAIL) e almeno una riga sotto.');
    } catch (Throwable $e) {
        if ($o) fclose($o);
        @unlink($dest);
        db()->prepare('DELETE FROM ' . tbl('newsletter_importazioni') . ' WHERE id = ?')->execute([$id]);
        if (!$e instanceof RuntimeException) error_log('newsletter importa: ' . $e->getMessage());
        return ['errore' => $e instanceof RuntimeException ? $e->getMessage() : 'Il file non si legge: controlla che sia un CSV, un TXT o un XLSX e riprova.'];
    }
    fclose($o);
    db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . ' SET file = ?, intestazione = ?, anteprima = ?, righe = ?, mappatura = ? WHERE id = ?')
        ->execute([$nome, json_encode($testa, JSON_UNESCAPED_UNICODE), newsletter_4byte_dentro(json_encode($anteprima, JSON_UNESCAPED_UNICODE)), $righe, json_encode(newsletter_importa_abbina($testa, $anteprima)), $id]);
    return ['id' => $id];
}

/** Un'importazione, con intestazione, anteprima, mappatura, opzioni e liste già lette. */
function newsletter_importazione(int $id): ?array {
    newsletter_importa_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_importazioni') . ' WHERE id = ?');
    $st->execute([$id]);
    $i = $st->fetch();
    if (!$i) return null;
    $i['intestazione'] = json_decode((string)$i['intestazione'], true) ?: [];
    $i['anteprima'] = json_decode(newsletter_4byte_fuori((string)$i['anteprima']), true) ?: [];
    $i['mappatura'] = json_decode((string)$i['mappatura'], true) ?: [];
    $i['opzioni'] = (json_decode((string)$i['opzioni'], true) ?: []) + ['aggiorna' => false, 'vuote' => false, 'nera' => false, 'flussi' => false, 'tag' => 'aggiungi'];
    $i['dettagli'] = json_decode((string)$i['dettagli'], true) ?: [];
    $i['liste'] = array_values(array_filter(array_map('intval', explode(',', (string)$i['liste']))));
    return $i;
}

/** Gli attributi che si possono importare: quelli che si scrivono (non calcolati, non globali, non di sola lettura). */
function newsletter_importa_attributi(): array {
    return array_filter(newsletter_attributi(), fn($a) => !in_array($a['categoria'], ['calculated', 'global'], true)
        && (!(int)$a['sistema'] || in_array($a['colonna'], ['email', 'nome', 'cognome', 'ext_id', 'opt_in'], true)));
}

/** L'abbinamento di partenza (Brevo: le colonne con lo stesso nome di un attributo si abbinano da sole). */
function newsletter_importa_abbina(array $testa, array $anteprima = []): array {
    $attr = newsletter_importa_attributi();
    $usati = []; $out = [];
    foreach ($testa as $t) {
        $n = newsletter_attributo_nome((string)$t);
        $n = NL_ATTR_ALIAS[$n] ?? NL_IMP_SINONIMI[$n] ?? (in_array($n, NL_IMP_TAG_NOMI, true) && !isset($attr[$n]) ? '_TAG' : $n);
        $ok = ($n === '_CONSENSO' || $n === '_TAG' || isset($attr[$n])) && !isset($usati[$n]);
        if ($ok) $usati[$n] = true;
        $out[] = $ok ? $n : '';
    }
    // Nessuna colonna si chiama EMAIL: quella con gli indirizzi nelle prime righe (per esempio "Colonna 1" incollata).
    if (!isset($usati['EMAIL'])) foreach ($out as $i => $n) {
        if ($n !== '') continue;
        foreach ($anteprima as $r) if (filter_var((string)($r[$i] ?? ''), FILTER_VALIDATE_EMAIL)) { $out[$i] = 'EMAIL'; break 2; }
    }
    return $out;
}

/**
 * Passo 2: salva l'abbinamento. $scelte[i] = nome dell'attributo, '' (non importare), '_CONSENSO', '_TAG' (2.23.0) o '_NUOVO'; $nuovi[i] =
 * ['nome', 'tipo'] per "Crea un nuovo attributo" (si crea subito, come in Brevo). Ritorna gli errori per colonna
 * ([indice => messaggio], 'tutti' => messaggio generale); vuoto se è andato bene.
 */
function newsletter_importa_salva_abbinamento(int $id, array $scelte, array $nuovi): array {
    $imp = newsletter_importazione($id);
    if (!$imp || $imp['stato'] !== 'bozza') return ['tutti' => 'Questa importazione non si può più cambiare.'];
    $attr = newsletter_importa_attributi();
    $err = []; $map = []; $usati = [];
    foreach ($imp['intestazione'] as $i => $t) {
        $s = (string)($scelte[$i] ?? '');
        if ($s === '_NUOVO') {
            $nome = newsletter_attributo_nome((string)($nuovi[$i]['nome'] ?? ''));
            $tipo = (string)($nuovi[$i]['tipo'] ?? 'text');
            if (!in_array($tipo, ['text', 'float', 'date', 'boolean'], true)) $tipo = 'text';
            if (!isset(newsletter_attributi()[$nome])) {
                $e = newsletter_attributo_crea(['nome' => $nome, 'tipo' => $tipo, 'etichetta' => mb_substr((string)$t, 0, 100)]);
                if ($e !== '') { $err[$i] = $e; $map[] = ''; continue; }
                $attr = newsletter_importa_attributi();
            } elseif (!isset($attr[$nome])) { $err[$i] = "L'attributo $nome esiste già ma non si può importare: scegli un altro nome."; $map[] = ''; continue; }
            $s = $nome;
        }
        if ($s !== '' && $s !== '_CONSENSO' && $s !== '_TAG' && !isset($attr[$s])) $s = '';
        if ($s !== '' && isset($usati[$s])) { $err[$i] = ($s === '_TAG' ? 'I tag sono già abbinati' : 'Questo attributo è già abbinato') . ' alla colonna «' . $imp['intestazione'][$usati[$s]] . '»: ' . ($s === '_TAG' ? 'metti tutti i tag in una colonna sola, separati da virgola.' : 'ogni attributo va a una colonna sola.'); $map[] = ''; continue; }
        if ($s !== '') $usati[$s] = $i;
        $map[] = $s;
    }
    if (!isset($usati['EMAIL']) && !$err) $err['tutti'] = 'Abbina la colonna con gli indirizzi email all\'attributo EMAIL: senza email i contatti non si importano.';
    db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . ' SET mappatura = ?, passo = IF(? AND passo < 3, 3, passo), aggiornato = NOW() WHERE id = ?')
        ->execute([json_encode($map), $err ? 0 : 1, $id]);
    return $err;
}

/** Passo 3: le liste (esistenti, o una nuova in una cartella). Ritorna '' o l'errore. */
function newsletter_importa_salva_liste(int $id, array $liste, string $nuova = '', int $cartella = 0): string {
    $imp = newsletter_importazione($id);
    if (!$imp || $imp['stato'] !== 'bozza') return 'Questa importazione non si può più cambiare.';
    $esistono = array_map('intval', array_column(newsletter_liste(), 'id'));
    $liste = array_values(array_intersect(array_unique(array_map('intval', $liste)), $esistono));
    if (trim($nuova) !== '') {
        $r = newsletter_lista_crea($nuova, $cartella);
        if (!is_int($r)) return $r;
        $liste[] = $r;
    }
    if (!$liste) return 'Scegli almeno una lista, oppure scrivi il nome di una lista nuova: i contatti importati entrano lì.';
    db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . ' SET liste = ?, passo = GREATEST(passo, 4), aggiornato = NOW() WHERE id = ?')->execute([implode(',', $liste), $id]);
    return '';
}

/** Passo 4: le opzioni e la conferma. Con il consenso spuntato l'importazione parte (stato pronta). Ritorna '' o l'errore. */
function newsletter_importa_conferma(int $id, array $opz, bool $consenso): string {
    $imp = newsletter_importazione($id);
    if (!$imp || $imp['stato'] !== 'bozza') return 'Questa importazione è già partita.';
    if (!in_array('EMAIL', $imp['mappatura'], true)) return 'Torna al passo 2 e abbina la colonna EMAIL.';
    if (!$imp['liste']) return 'Torna al passo 3 e scegli una lista.';
    if (!$consenso) return 'Spunta la casella del consenso: si importano solo contatti che hanno dato il consenso a ricevere le tue email.';
    $o = ['aggiorna' => !empty($opz['aggiorna']), 'nera' => !empty($opz['nera']), 'flussi' => !empty($opz['flussi']), 'tag' => ($opz['tag'] ?? '') === 'sostituisci' ? 'sostituisci' : 'aggiungi'];
    $o['vuote'] = $o['aggiorna'] && !empty($opz['vuote']); // Brevo: le celle vuote contano solo se si aggiorna
    db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . " SET opzioni = ?, stato = 'pronta', aggiornato = NOW() WHERE id = ? AND stato = 'bozza'")->execute([json_encode($o), $id]);
    return '';
}

// ------------------------------------------------------------
// Il lavoro a giri
// ------------------------------------------------------------

/**
 * Un giro di un'importazione fino a $fino (microtime). Ritorna true quando è finita. Le righe si fanno a pezzi da
 * NL_IMP_PEZZO in una transazione: contatori, posizione nel file e righe viste cambiano insieme.
 */
function newsletter_importa_giro(int $id, float $fino): bool {
    $imp = newsletter_importazione($id);
    if (!$imp || !in_array($imp['stato'], ['pronta', 'in_corso'], true)) return true;
    $file = newsletter_file_privato((string)$imp['file']);
    $f = $file !== '' ? @fopen($file, 'rb') : false;
    if (!$f) {
        db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . " SET stato = 'errore', errore = ?, finito = NOW() WHERE id = ?")->execute(['Il file caricato non c\'è più: rifai l\'importazione.', $id]);
        return true;
    }
    if ($imp['stato'] === 'pronta') db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . " SET stato = 'in_corso' WHERE id = ?")->execute([$id]);
    $pos = (int)$imp['posizione'];
    if ($pos === 0) { fgets($f); $pos = ftell($f); } else fseek($f, $pos);
    $finita = false;
    while (microtime(true) < $fino) {
        $pezzo = [];
        while (count($pezzo) < NL_IMP_PEZZO && ($l = fgets($f)) !== false) {
            $r = json_decode($l, true);
            $pezzo[] = is_array($r) ? $r : [];
        }
        $dopo = ftell($f);
        if ($pezzo) {
            db()->beginTransaction();
            try {
                $esito = newsletter_importa_pezzo($imp, $pezzo);
                db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . ' SET posizione = ?, fatte = fatte + ?, nuovi = nuovi + ?, aggiornati = aggiornati + ?,
                    invariati = invariati + ?, doppioni = doppioni + ?, errori = errori + ?, dettagli = ?, aggiornato = NOW() WHERE id = ?')
                    ->execute([$dopo, count($pezzo), $esito['nuovi'], $esito['aggiornati'], $esito['invariati'], $esito['doppioni'], $esito['errori'], json_encode($esito['dettagli']), $id]);
                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                fclose($f);
                error_log('newsletter importa ' . $id . ': ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
                db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . " SET stato = 'errore', errore = ?, finito = NOW() WHERE id = ?")->execute([mb_substr('Errore imprevisto: ' . $e->getMessage(), 0, 255), $id]);
                return true;
            }
            newsletter_importa_scarti($imp, $esito['scarti']);
            $imp['dettagli'] = $esito['dettagli'];
            $imp['fatte'] = (int)$imp['fatte'] + count($pezzo);
        }
        if (count($pezzo) < NL_IMP_PEZZO) { $finita = true; break; }
    }
    fclose($f);
    if ($finita) {
        db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . " SET stato = 'finita', finito = NOW(), fatte = righe WHERE id = ?")->execute([$id]);
        db()->prepare('DELETE FROM ' . tbl('newsletter_importa_viste') . ' WHERE importazione_id = ?')->execute([$id]);
        @unlink($file); // le righe sono dentro: il file caricato non serve più
    }
    return $finita;
}

/** Le righe scartate in coda al CSV degli scarti (riga del file, motivo, poi le celle come nel file). */
function newsletter_importa_scarti(array &$imp, array $scarti): void {
    if (!$scarti) return;
    require_once __DIR__ . '/sicurezza-html.php';
    $nome = (string)$imp['scarti_file'];
    if ($nome === '') {
        $nome = 'scarti-' . (int)$imp['id'] . '-' . bin2hex(random_bytes(6)) . '.csv';
        db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . ' SET scarti_file = ? WHERE id = ?')->execute([$nome, (int)$imp['id']]);
        $imp['scarti_file'] = $nome;
        $f = fopen(newsletter_dir_privata() . '/' . $nome, 'wb');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, sicurezza_csv_riga(array_merge(['RIGA', 'MOTIVO'], $imp['intestazione'])), ';', '"', '');
    } else {
        $f = fopen(newsletter_dir_privata() . '/' . $nome, 'ab');
    }
    if (!$f) return;
    foreach ($scarti as $s) fputcsv($f, sicurezza_csv_riga(array_merge([$s[0], $s[1]], $s[2])), ';', '"', '');
    fclose($f);
}

/** Toglie l'apostrofo che un'esportazione mette davanti alle celle che sembrano formule: andata e ritorno uguali. */
function newsletter_importa_valore(string $v): string {
    return preg_match("/^'[=+\-@\t\r\n]/", $v) ? substr($v, 1) : $v;
}

/** Un pezzo di righe dentro una transazione. Ritorna i contatori, le righe scartate e i dettagli aggiornati. */
function newsletter_importa_pezzo(array $imp, array $pezzo): array {
    $t = tbl('newsletter_iscritti');
    $id = (int)$imp['id'];
    $map = $imp['mappatura'];
    $o = $imp['opzioni'];
    $attr = newsletter_attributi();
    $colE = array_search('EMAIL', $map, true);
    $out = ['nuovi' => 0, 'aggiornati' => 0, 'invariati' => 0, 'doppioni' => 0, 'errori' => 0, 'scarti' => [],
        'dettagli' => $imp['dettagli'] + ['bloccati' => 0, 'soppressi' => 0, 'non_valide' => 0, 'ext' => 0]];
    $riga0 = (int)$imp['fatte'] + 2; // la riga 1 del file sono le intestazioni
    // Prima le email del pezzo, poi tre domande per tutto il pezzo: già viste in un giro prima (doppioni), con i dati
    // cancellati (lista di soppressione), già contatti.
    $email = [];
    foreach ($pezzo as $k => $r) $email[$k] = mb_strtolower(trim(newsletter_importa_valore((string)($r[$colE] ?? ''))));
    $valide = array_filter($email, fn($e) => $e !== '' && strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL));
    $gia = $soppr = [];
    if ($valide) {
        $md5 = array_values(array_unique(array_map('md5', $valide)));
        $st = db()->prepare('SELECT impronta FROM ' . tbl('newsletter_importa_viste') . ' WHERE importazione_id = ? AND impronta IN (' . implode(',', array_fill(0, count($md5), '?')) . ')');
        $st->execute(array_merge([$id], $md5));
        $gia = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
        $sha = array_values(array_unique(array_map('newsletter_impronta', $valide)));
        $st = db()->prepare('SELECT impronta FROM ' . tbl('newsletter_soppressi') . ' WHERE impronta IN (' . implode(',', array_fill(0, count($sha), '?')) . ')');
        $st->execute($sha);
        $soppr = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    }
    $buone = []; $viste = [];
    foreach ($pezzo as $k => $r) {
        $e = $email[$k];
        if (!isset($valide[$k])) {
            $out['errori']++; $out['dettagli']['non_valide']++;
            $out['scarti'][] = [$riga0 + $k, $e === '' ? 'Manca l\'email' : 'Email non valida', $r];
            continue;
        }
        $m = md5($e);
        if (isset($gia[$m])) { $out['doppioni']++; $out['scarti'][] = [$riga0 + $k, 'Doppione: la stessa email è già in una riga prima', $r]; continue; }
        $gia[$m] = 1; $viste[] = $m;
        if (isset($soppr[newsletter_impronta($e)])) {
            $out['errori']++; $out['dettagli']['soppressi']++;
            $out['scarti'][] = [$riga0 + $k, 'Ha chiesto di cancellare i suoi dati: non si importa', $r];
            continue;
        }
        $buone[$e] = [$riga0 + $k, $r];
    }
    if ($viste) db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_importa_viste') . ' (importazione_id, impronta) VALUES ' . implode(',', array_fill(0, count($viste), '(?, ?)')))
        ->execute(array_merge(...array_map(fn($m) => [$id, $m], $viste)));
    if (!$buone) return $out;
    // I contatti che ci sono già, tutti insieme, e i loro valori degli attributi abbinati.
    $esistenti = [];
    $in = implode(',', array_fill(0, count($buone), '?'));
    $st = db()->prepare("SELECT * FROM $t WHERE email IN ($in)");
    $st->execute(array_keys($buone));
    foreach ($st as $c) $esistenti[$c['email']] = $c;
    $nomi = array_values(array_filter($map, fn($n) => $n !== '' && $n !== '_CONSENSO' && $n !== '_TAG' && $n !== 'EMAIL'));
    // 2.23.0: la colonna dei tag (più tag separati da virgola o punto e virgola), aggiunti a quelli che ha o al loro posto.
    $colT = array_search('_TAG', $map, true);
    $tagModo = ($o['tag'] ?? 'aggiungi') === 'sostituisci' ? 'sostituisci' : 'aggiungi';
    $origineT = 'importazione n. ' . $id;
    $tagDi = function (array $r) use ($colT): array { return $colT === false ? [] : newsletter_tag_dividi(newsletter_importa_valore((string)($r[$colT] ?? ''))); };
    $prima = $esistenti && $o['aggiorna'] ? newsletter_valori_di(array_map(fn($c) => (int)$c['id'], $esistenti), $nomi) : [];
    $liste = $imp['liste'];
    $nomeFile = $imp['origine'] === 'incolla' ? 'testo incollato' : 'file ' . newsletter_4byte_fuori((string)$imp['nome_file']);
    $ora = date('Y-m-d H:i:s');
    $ins = db()->prepare("INSERT INTO $t (email, stato, fonte, consenso_versione, consenso_at, creato, aggiornato) VALUES (?, 'attivo', 'import', 'import', ?, ?, ?)");
    $inL = db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_iscritti_liste') . ' (iscritto_id, lista_id, aggiunto) VALUES (?, ?, NOW())');
    $nomiL = array_column(newsletter_liste(), 'nome', 'id');
    $flussi = $o['flussi'] && !$o['nera'];
    $storia = []; $consensi = [];
    $testoCons = 'Importazione n. ' . $id . ': chi ha importato ha confermato il consenso esplicito dei contatti';
    // Nelle liste: la riga della storia si scrive solo se il contatto ci entra adesso; i flussi solo se chiesti.
    $inListe = function (int $cid) use ($liste, $inL, $nomiL, $flussi, $id, &$storia) {
        foreach ($liste as $l) {
            $inL->execute([$cid, $l]);
            if (!$inL->rowCount()) continue;
            $storia[] = [$cid, 'lista_aggiunto', $l, (string)($nomiL[$l] ?? '')];
            if ($flussi) newsletter_flussi_innesco('lista', $l, $cid);
        }
    };
    foreach ($buone as $email => [$n, $r]) {
        $valori = []; $consenso = null;
        foreach ($map as $i => $nome) {
            if ($nome === '' || $nome === 'EMAIL') continue;
            $v = newsletter_importa_valore((string)($r[$i] ?? ''));
            if ($nome === '_CONSENSO') { $consenso = newsletter_data_csv($v); continue; }
            if ($nome === '_TAG') continue;
            $a = $attr[$nome] ?? null;
            if (!$a) continue;
            if ($a['tipo'] === 'date' && preg_match('/^(\S+)[ T]\d{1,2}:\d{2}/', $v, $m)) $v = $m[1];
            $valori[$nome] = $a['tipo'] === 'multiple-choice' && $v !== '' ? array_map('trim', explode(',', $v)) : $v;
        }
        $c = $esistenti[$email] ?? null;
        if (!$c) {
            // EXT_ID già di un altro contatto: Brevo lo mette fra gli errori ("ID esterni doppi").
            if (isset($valori['EXT_ID']) && $valori['EXT_ID'] !== '') {
                $x = db()->prepare("SELECT id FROM $t WHERE ext_id = ?");
                $x->execute([newsletter_testo_pulito($valori['EXT_ID'], 190)]);
                if ($x->fetchColumn()) { $out['errori']++; $out['dettagli']['ext']++; $out['scarti'][] = [$n, 'Identificativo esterno (EXT_ID) già di un altro contatto', $r]; continue; }
            }
            $ins->execute([$email, $consenso, $ora, $ora]);
            $cid = (int)db()->lastInsertId();
            $storia[] = [$cid, 'importato', $id, 'Importato dal ' . $nomeFile . ' (importazione n. ' . $id . ')'];
            $consensi[] = $cid;
            $c = ['id' => $cid, 'email' => $email, 'nome' => '', 'cognome' => '', 'ext_id' => null, 'opt_in' => 0, 'stato' => 'attivo'];
            newsletter_importa_valori($c, [], $valori, false, true);
            if ($o['nera']) newsletter_contatto_blocca($cid, 'import', 'importazione n. ' . $id);
            $inListe($cid);
            if ($tg = $tagDi($r)) newsletter_tag_aggiungi($cid, $tg, $origineT, $flussi);
            $out['nuovi']++;
            continue;
        }
        $cid = (int)$c['id'];
        if (in_array($c['stato'], NL_BLOCCATI, true)) { $out['invariati']++; $out['dettagli']['bloccati']++; continue; }
        $cambiati = [];
        if ($o['aggiorna']) {
            $cambiati = newsletter_importa_valori($c, $prima[$cid] ?? [], $valori, $o['vuote'], false);
            if ($consenso !== null && $consenso !== (string)$c['consenso_at']) { db()->prepare("UPDATE $t SET consenso_at = ? WHERE id = ?")->execute([$consenso, $cid]); $cambiati[] = 'data del consenso'; }
            if ($cambiati) {
                db()->prepare("UPDATE $t SET aggiornato = NOW() WHERE id = ?")->execute([$cid]);
                $storia[] = [$cid, 'attributo', $id, 'Aggiornato dall\'importazione n. ' . $id . ': ' . implode(', ', $cambiati)];
            }
        }
        if ($o['nera'] && newsletter_contatto_blocca($cid, 'import', 'importazione n. ' . $id)) $cambiati[] = 'lista nera';
        $inListe($cid);
        // 2.23.0: i tag anche a chi c'è già (sono la ragione per cui si è abbinata la colonna). Una cella vuota non cambia niente.
        if ($tg = $tagDi($r)) {
            [$piu, $meno] = $tagModo === 'sostituisci' ? newsletter_tag_imposta($cid, $tg, $origineT, $flussi) : [newsletter_tag_aggiungi($cid, $tg, $origineT, $flussi), 0];
            if ($piu || $meno) $cambiati[] = 'tag';
        }
        $cambiati ? $out['aggiornati']++ : $out['invariati']++;
    }
    // La storia e il registro del consenso tutti insieme, a blocchi.
    $u = session_status() === PHP_SESSION_ACTIVE ? (int)($_SESSION['admin_user_id'] ?? 0) : 0;
    foreach (array_chunk($storia, 300) as $b) {
        db()->prepare('INSERT INTO ' . tbl('newsletter_storia') . ' (contatto_id, tipo, rif_id, dettaglio, utente_id, creato) VALUES ' . implode(',', array_fill(0, count($b), '(?, ?, ?, ?, ?, NOW())')))
            ->execute(array_merge(...array_map(fn($x) => [$x[0], $x[1], $x[2], mb_substr(newsletter_4byte_dentro($x[3]), 0, 255), $u], $b)));
    }
    foreach (array_chunk($consensi, 300) as $b) {
        db()->prepare('INSERT INTO ' . tbl('newsletter_consensi') . " (contatto_id, tipo, testo, form_id, ip, creato) VALUES " . implode(',', array_fill(0, count($b), "(?, 'import', ?, 0, '', NOW())")))
            ->execute(array_merge(...array_map(fn($c) => [$c, $testoCons], $b)));
    }
    return $out;
}

/**
 * Scrive i valori di un contatto dall'importazione, senza rileggere tutto il contatto a ogni riga. $prima: i valori di
 * prima (newsletter_valori_di). $vuote: una cella vuota toglie il valore. Il tipo sbagliato si ignora. Ritorna i nomi
 * degli attributi cambiati.
 */
function newsletter_importa_valori(array $c, array $prima, array $valori, bool $vuote, bool $nuovo): array {
    static $del = null, $ins = null;
    $del ??= db()->prepare('DELETE FROM ' . tbl('newsletter_valori') . ' WHERE contatto_id = ? AND attributo_id = ?');
    $ins ??= db()->prepare('INSERT INTO ' . tbl('newsletter_valori') . ' (contatto_id, attributo_id, opzione, v_testo, v_num, v_data) VALUES (?, ?, ?, ?, ?, ?)');
    $attr = newsletter_attributi();
    $id = (int)$c['id'];
    $set = []; $par = []; $cambiati = [];
    foreach ($valori as $nome => $v) {
        $a = $attr[$nome] ?? null;
        if (!$a) continue;
        $vuoto = $v === '' || $v === [];
        if ($vuoto && ($nuovo || !$vuote)) continue;
        if ((int)$a['sistema']) {
            $col = $a['colonna'];
            if ($col === 'opt_in') {
                [$ok, $b] = $vuoto ? [true, 0] : newsletter_valore_leggi($a, $v);
                if (!$ok || $b === '' || (int)$b === (int)$c['opt_in']) continue;
                $set[] = 'opt_in = ?'; $par[] = (int)$b;
            } elseif ($col === 'ext_id') {
                $x = $vuoto ? null : newsletter_testo_pulito((string)$v, 190);
                if ($x === ($c['ext_id'] ?? null) || ($x !== null && $x === (string)$c['ext_id'])) continue;
                if ($x !== null) {
                    $q = db()->prepare('SELECT id FROM ' . tbl('newsletter_iscritti') . ' WHERE ext_id = ? AND id <> ?');
                    $q->execute([$x, $id]);
                    if ($q->fetchColumn()) continue;
                }
                $set[] = 'ext_id = ?'; $par[] = $x;
            } elseif ($col === 'nome' || $col === 'cognome') {
                $x = $vuoto ? '' : newsletter_testo_pulito((string)$v, 100);
                if ($x === (string)$c[$col]) continue;
                $set[] = "`$col` = ?"; $par[] = $x;
            } else continue;
            $cambiati[] = $nome;
            continue;
        }
        [$ok, $val] = newsletter_valore_leggi($a, $v);
        if (!$ok) continue; // del tipo sbagliato: si ignora senza errore (Brevo)
        if ($val === ($prima[$nome] ?? ($a['tipo'] === 'multiple-choice' ? [] : ''))) continue;
        $aid = (int)$a['id'];
        if (!$nuovo) $del->execute([$id, $aid]);
        if ($val !== '' && $val !== []) {
            switch ($a['tipo']) {
                case 'text': $ins->execute([$id, $aid, 0, $val, null, null]); break;
                case 'float': case 'boolean': case 'category': $ins->execute([$id, $aid, 0, '', $val, null]); break;
                case 'date': $ins->execute([$id, $aid, 0, '', null, $val]); break;
                case 'multiple-choice': foreach ($val as $op) $ins->execute([$id, $aid, $op, '', null, null]); break;
            }
        }
        $cambiati[] = $nome;
    }
    if ($set) { $par[] = $id; db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($par); }
    return $cambiati;
}

/**
 * Il giro dei lavori in sottofondo (importazioni, poi esportazioni), con un blocco contro due giri insieme (pagina e
 * cron). Ritorna le righe per il registro del cron.
 */
function newsletter_lavori_giro(int $secondi = 15): array {
    newsletter_importa_schema();
    $blocco = 'nl_lavori_' . md5(DB_NAME . DB_PREFIX);
    if ((int)db()->query('SELECT GET_LOCK(' . db()->quote($blocco) . ', 0)')->fetchColumn() !== 1) return [];
    $log = [];
    try {
        $fino = microtime(true) + $secondi;
        foreach (db()->query('SELECT id FROM ' . tbl('newsletter_importazioni') . " WHERE stato IN ('pronta', 'in_corso') ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $i) {
            if (microtime(true) >= $fino) break;
            $fatta = newsletter_importa_giro((int)$i, $fino);
            $log[] = 'importazione n. ' . $i . ($fatta ? ' finita' : ' avanti');
        }
        foreach (db()->query('SELECT id FROM ' . tbl('newsletter_esportazioni') . " WHERE stato = 'in_corso' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $e) {
            if (microtime(true) >= $fino) break;
            $fatta = newsletter_esporta_giro((int)$e, $fino);
            $log[] = 'esportazione n. ' . $e . ($fatta ? ' finita' : ' avanti');
        }
    } finally {
        db()->query('SELECT RELEASE_LOCK(' . db()->quote($blocco) . ')');
    }
    return $log;
}

/** Pulizia (una volta al giorno e all'apertura delle pagine): bozze di un giorno, scarti ed esportazioni di 7 giorni. */
function newsletter_importa_pulizia(): void {
    newsletter_importa_schema();
    $d = newsletter_dir_privata();
    foreach (db()->query('SELECT id, file, scarti_file FROM ' . tbl('newsletter_importazioni') . " WHERE stato = 'bozza' AND aggiornato < DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchAll() as $r) {
        foreach ([$r['file'], $r['scarti_file']] as $n) if ($n !== '' && ($p = newsletter_file_privato((string)$n)) !== '') @unlink($p);
        db()->prepare('DELETE FROM ' . tbl('newsletter_importazioni') . ' WHERE id = ?')->execute([(int)$r['id']]);
    }
    foreach (db()->query('SELECT id, scarti_file FROM ' . tbl('newsletter_importazioni') . " WHERE scarti_file <> '' AND stato IN ('finita', 'errore') AND finito < DATE_SUB(NOW(), INTERVAL " . NL_FILE_GIORNI . ' DAY)')->fetchAll() as $r) {
        if (($p = newsletter_file_privato((string)$r['scarti_file'])) !== '') @unlink($p);
        db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . " SET scarti_file = '' WHERE id = ?")->execute([(int)$r['id']]);
    }
    foreach (db()->query('SELECT id, file FROM ' . tbl('newsletter_esportazioni') . ' WHERE scade < NOW()')->fetchAll() as $r) {
        if (($p = newsletter_file_privato((string)$r['file'])) !== '') @unlink($p);
        db()->prepare('DELETE FROM ' . tbl('newsletter_esportazioni') . ' WHERE id = ?')->execute([(int)$r['id']]);
    }
    unset($d);
}

/**
 * Involucro della 2.0.0, usato prima della 2.9.0 dalla pagina Contatti: importa un CSV in una lista con il motore nuovo,
 * abbinando le colonne da sole, senza aggiornare chi c'è già. Ritorna i numeri di prima (nuovi, gia, scartati, saltati).
 */
// Dentro un if: per qualche secondo dopo l'aggiornamento opcache può dare la newsletter.php di prima, che la dichiarava.
if (!function_exists('newsletter_importa_csv')) {
function newsletter_importa_csv(string $file, int $lista, bool $flussi = false): array {
    $p = newsletter_importa_prepara($file, 'contatti.csv', 'file');
    if (isset($p['errore'])) return ['errore' => $p['errore']];
    $id = (int)$p['id'];
    $imp = newsletter_importazione($id);
    // I nomi della colonna del consenso che leggeva la 2.0.0
    $map = $imp['mappatura'];
    foreach ($imp['intestazione'] as $i => $t) if ($map[$i] === '' && in_array(mb_strtoupper(trim((string)$t)), ['OPTIN', 'OPT_IN', 'DOUBLE_OPT-IN'], true) && !in_array('_CONSENSO', $map, true)) $map[$i] = '_CONSENSO';
    if (!in_array('EMAIL', $map, true)) { newsletter_importa_elimina($id); return ['errore' => 'Nel file manca la colonna EMAIL.']; }
    db()->prepare('UPDATE ' . tbl('newsletter_importazioni') . ' SET mappatura = ?, liste = ?, opzioni = ?, stato = \'pronta\' WHERE id = ?')
        ->execute([json_encode($map), (string)$lista, json_encode(['aggiorna' => false, 'vuote' => false, 'nera' => false, 'flussi' => $flussi]), $id]);
    while (!newsletter_importa_giro($id, microtime(true) + 20));
    $i = newsletter_importazione($id);
    return ['nuovi' => (int)$i['nuovi'], 'gia' => (int)$i['invariati'] + (int)$i['aggiornati'] - (int)($i['dettagli']['bloccati'] ?? 0) + (int)$i['doppioni'],
        'scartati' => (int)($i['dettagli']['non_valide'] ?? 0), 'saltati' => (int)($i['dettagli']['bloccati'] ?? 0) + (int)($i['dettagli']['soppressi'] ?? 0), 'id' => $id];
}
}

/** Toglie un'importazione in bozza e i suoi file. */
function newsletter_importa_elimina(int $id): void {
    $i = newsletter_importazione($id);
    if (!$i || !in_array($i['stato'], ['bozza', 'finita', 'errore'], true)) return;
    foreach ([$i['file'], $i['scarti_file']] as $n) if ($n !== '' && ($p = newsletter_file_privato((string)$n)) !== '') @unlink($p);
    db()->prepare('DELETE FROM ' . tbl('newsletter_importazioni') . ' WHERE id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_importa_viste') . ' WHERE importazione_id = ?')->execute([$id]);
}

// ------------------------------------------------------------
// Esportazione
// ------------------------------------------------------------

/** Le campagne da cui si esporta (quelle partite), dalla più recente. */
function newsletter_esporta_campagne(): array {
    return db()->query('SELECT id, oggetto, invio_at FROM ' . tbl('newsletter_campagne') . " WHERE stato IN ('in_invio', 'sospesa', 'inviata') ORDER BY id DESC LIMIT 200")->fetchAll();
}

const NL_ESP_LISTA = ['tutti' => 'tutti i contatti', 'iscritti' => 'solo gli iscritti', 'lista_nera' => 'solo quelli in lista nera', 'usciti_lista' => 'solo i disiscritti da questa lista'];
const NL_ESP_CAMPAGNA = ['aperto' => 'hanno aperto', 'non_aperto' => 'non hanno aperto', 'cliccato' => 'hanno cliccato', 'non_cliccato' => 'non hanno cliccato', 'disiscritti' => 'si sono disiscritti', 'rimbalzi' => 'rimbalzi'];

/** La condizione SQL di un filtro dell'esportazione (sui contatti i). Ritorna [where, parametri]. */
function newsletter_esporta_where(array $f): array {
    $il = tbl('newsletter_iscritti_liste'); $ev = tbl('newsletter_eventi'); $co = tbl('newsletter_coda');
    switch ($f['tipo'] ?? '') {
        case 'contatti':
            [$w, $p] = newsletter_contatti_filtro((array)($f['q'] ?? []));
            return [$w !== '' ? substr($w, 7) : '1', $p];
        case 'segmento':
            // 2.10.0: i contatti di un segmento, ricalcolato a ogni giro (il motore si carica qui: opcache).
            if (!function_exists('newsletter_segmento_where')) require_once __DIR__ . '/newsletter-segmenti.php';
            $p = [];
            return [newsletter_segmento_where((int)($f['segmento'] ?? 0), $p), $p];
        case 'ids':
            $ids = array_values(array_filter(array_map('intval', (array)($f['ids'] ?? []))));
            return [$ids ? 'i.id IN (' . implode(',', $ids) . ')' : '0', []];
        case 'lista':
            $l = (int)($f['lista'] ?? 0);
            $in = "EXISTS (SELECT 1 FROM $il l WHERE l.iscritto_id = i.id AND l.lista_id = $l)";
            $esce = 'EXISTS (SELECT 1 FROM ' . tbl('newsletter_liste_uscite') . " u WHERE u.contatto_id = i.id AND u.lista_id = $l)";
            return [match ($f['chi'] ?? 'tutti') {
                'iscritti' => "$in AND i.stato = 'attivo' AND NOT $esce",
                'lista_nera' => "$in AND i.stato IN ('uscito', 'rimbalzato', 'spam', 'bloccato')",
                'usciti_lista' => $esce,
                default => $in,
            }, []];
        case 'campagna':
            $c = (int)($f['campagna'] ?? 0);
            $evento = fn($t) => "EXISTS (SELECT 1 FROM $ev e WHERE e.iscritto_id = i.id AND e.campagna_id = $c AND e.tipo = '$t')";
            $ricevuta = "EXISTS (SELECT 1 FROM $co q WHERE q.iscritto_id = i.id AND q.campagna_id = $c AND q.stato = 'inviata')";
            return [match ($f['chi'] ?? 'aperto') {
                'non_aperto' => "$ricevuta AND NOT " . $evento('apertura'),
                'cliccato' => $evento('clic'),
                'non_cliccato' => "$ricevuta AND NOT " . $evento('clic'),
                'disiscritti' => $evento('uscita'),
                'rimbalzi' => '(' . $evento('rimbalzo') . " OR EXISTS (SELECT 1 FROM $co q WHERE q.iscritto_id = i.id AND q.campagna_id = $c AND q.stato = 'errore'))",
                default => $evento('apertura'),
            }, []];
    }
    return ['0', []];
}

/** Le colonne che si possono esportare: gli attributi (non globali) e le colonne di stato. Chiave => etichetta. */
function newsletter_esporta_colonne(): array {
    $out = [];
    foreach (newsletter_attributi() as $n => $a) if ($a['categoria'] !== 'global') $out[$n] = newsletter_attributo_etichetta($a);
    return $out + NL_ESP_EXTRA;
}

/**
 * Crea un'esportazione. $f: filtro (tipo contatti/ids/lista/campagna); $colonne: nomi (EMAIL c'è sempre, nell'ordine
 * degli attributi); $sep: ';' o ','. Ritorna l'id, oppure un messaggio d'errore.
 */
function newsletter_esporta_crea(array $f, array $colonne, string $sep, int $utente = 0) {
    newsletter_importa_schema();
    $tutte = newsletter_esporta_colonne();
    $colonne = array_values(array_filter(array_keys($tutte), fn($n) => $n === 'EMAIL' || in_array($n, $colonne, true)));
    if (!in_array($sep, [';', ','], true)) $sep = ';';
    $descr = '';
    if (($f['tipo'] ?? '') === 'lista') {
        $nome = newsletter_nome_lista((int)($f['lista'] ?? 0));
        if ($nome === '') return 'Scegli la lista da esportare.';
        $descr = 'Lista «' . newsletter_4byte_fuori($nome) . '», ' . (NL_ESP_LISTA[$f['chi'] ?? ''] ?? NL_ESP_LISTA['tutti']);
    } elseif (($f['tipo'] ?? '') === 'campagna') {
        $c = newsletter_campagna((int)($f['campagna'] ?? 0));
        if (!$c) return 'Scegli la campagna da esportare.';
        $descr = 'Campagna «' . (newsletter_4byte_fuori((string)$c['oggetto']) ?: 'senza oggetto') . '», ' . (NL_ESP_CAMPAGNA[$f['chi'] ?? ''] ?? NL_ESP_CAMPAGNA['aperto']);
    } elseif (($f['tipo'] ?? '') === 'segmento') {
        if (!function_exists('newsletter_segmento')) require_once __DIR__ . '/newsletter-segmenti.php';
        $s = newsletter_segmento((int)($f['segmento'] ?? 0));
        if (!$s) return 'Scegli il segmento da esportare.';
        $descr = 'Segmento «' . newsletter_4byte_fuori((string)$s['nome']) . '»';
    } elseif (($f['tipo'] ?? '') === 'ids') {
        if (!array_filter((array)($f['ids'] ?? []))) return 'Spunta almeno un contatto nella tabella, oppure la casella «Tutti i contatti».';
        $descr = 'Contatti scelti nella tabella';
    } else {
        $descr = 'Contatti del filtro';
    }
    [$w, $p] = newsletter_esporta_where($f);
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i WHERE $w");
    $st->execute($p);
    $tot = (int)$st->fetchColumn();
    $ora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO ' . tbl('newsletter_esportazioni') . " (utente_id, descrizione, filtro, colonne, separatore, stato, totale, creato, scade) VALUES (?, ?, ?, ?, ?, 'in_corso', ?, ?, DATE_ADD(?, INTERVAL " . NL_FILE_GIORNI . ' DAY))')
        ->execute([$utente, mb_substr(newsletter_4byte_dentro($descr), 0, 255), json_encode($f), json_encode($colonne), $sep, $tot, $ora, $ora]);
    return (int)db()->lastInsertId();
}

function newsletter_esportazione(int $id): ?array {
    newsletter_importa_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_esportazioni') . ' WHERE id = ?');
    $st->execute([$id]);
    $e = $st->fetch();
    return $e ?: null;
}

/** Un giro di un'esportazione fino a $fino. Ritorna true quando il file è pronto. */
function newsletter_esporta_giro(int $id, float $fino): bool {
    $e = newsletter_esportazione($id);
    if (!$e || $e['stato'] !== 'in_corso') return true;
    require_once __DIR__ . '/sicurezza-html.php';
    $f = json_decode((string)$e['filtro'], true) ?: [];
    $colonne = json_decode((string)$e['colonne'], true) ?: ['EMAIL'];
    $sep = (string)$e['separatore'];
    $nome = (string)$e['file'];
    if ($nome === '') {
        $nome = 'esp-' . $id . '-' . bin2hex(random_bytes(6)) . '.csv';
        $o = fopen(newsletter_dir_privata() . '/' . $nome, 'wb');
        fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, $colonne, $sep, '"', '');
        fclose($o);
        db()->prepare('UPDATE ' . tbl('newsletter_esportazioni') . ' SET file = ? WHERE id = ?')->execute([$nome, $id]);
    }
    $o = fopen(newsletter_dir_privata() . '/' . $nome, 'ab');
    if (!$o) return true;
    [$w, $p] = newsletter_esporta_where($f);
    $attr = newsletter_attributi();
    $nomiListe = array_column(newsletter_liste(), 'nome', 'id');
    $soloAttr = array_values(array_filter($colonne, fn($n) => isset($attr[$n])));
    $ultimo = (int)$e['ultimo_id']; $righe = (int)$e['righe']; $finita = false;
    while (microtime(true) < $fino) {
        $st = db()->prepare('SELECT i.* FROM ' . tbl('newsletter_iscritti') . " i WHERE ($w) AND i.id > ? ORDER BY i.id LIMIT 500");
        $st->execute(array_merge($p, [$ultimo]));
        $pezzo = $st->fetchAll();
        if (!$pezzo) { $finita = true; break; }
        $valori = newsletter_valori_di(array_column($pezzo, 'id'), $soloAttr);
        $tagPezzo = in_array('TAG', $colonne, true) && !isset($attr['TAG']) ? newsletter_tag_di_tanti(array_column($pezzo, 'id')) : []; // 2.23.0
        foreach ($pezzo as $r) {
            $cid = (int)$r['id']; $out = [];
            foreach ($colonne as $n) {
                if (isset($attr[$n])) { $a = $attr[$n]; $v = $valori[$cid][$n] ?? ''; $out[] = $a['tipo'] === 'date' ? (string)$v : newsletter_valore_testo($a, $v); continue; }
                $out[] = match ($n) {
                    'ISCRITTO' => $r['stato'] === 'attivo' ? 'sì' : 'no',
                    'LISTA_NERA' => in_array($r['stato'], NL_BLOCCATI, true) ? 'sì' : 'no',
                    'MOTIVO' => newsletter_motivo_testo($r),
                    'LISTE' => implode(', ', array_map(fn($l) => newsletter_4byte_fuori((string)($nomiListe[$l] ?? '')), newsletter_liste_di($cid))),
                    'TAG' => implode(', ', $tagPezzo[$cid] ?? []),
                    default => '',
                };
            }
            fputcsv($o, sicurezza_csv_riga($out), $sep, '"', '');
            $righe++; $ultimo = $cid;
        }
        db()->prepare('UPDATE ' . tbl('newsletter_esportazioni') . ' SET ultimo_id = ?, righe = ? WHERE id = ?')->execute([$ultimo, $righe, $id]);
        if (count($pezzo) < 500) { $finita = true; break; }
    }
    fclose($o);
    if ($finita) db()->prepare('UPDATE ' . tbl('newsletter_esportazioni') . " SET stato = 'finita', finito = NOW(), righe = ?, totale = GREATEST(totale, ?) WHERE id = ?")->execute([$righe, $righe, $id]);
    return $finita;
}

/** Manda al browser un file della cartella privata come allegato, e chiude. */
function newsletter_file_scarica(string $nome, string $comeSiChiama): never {
    $p = newsletter_file_privato($nome);
    if ($p === '' || !is_file($p)) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Il file non c\'è più: i file restano ' . NL_FILE_GIORNI . ' giorni. Rifai l\'esportazione.'; exit; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9\-]/', '-', strtolower($comeSiChiama)) . '-' . date('Y-m-d') . '.csv"');
    header('Content-Length: ' . filesize($p));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($p);
    exit;
}

// ------------------------------------------------------------
// Lista dei test (Brevo: Impostazioni > Lista dei test)
// ------------------------------------------------------------

/** I contatti della lista dei test (solo quelli che esistono ancora), nell'ordine salvato. */
function newsletter_lista_test(): array {
    $ids = array_values(array_filter(array_map('intval', (array)(json_decode((string)get_setting('newsletter_lista_test', ''), true) ?: []))));
    if (!$ids) return [];
    $righe = [];
    foreach (db()->query('SELECT id, email, nome, cognome, stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id IN (' . implode(',', $ids) . ')') as $r) $righe[(int)$r['id']] = $r;
    return array_values(array_filter(array_map(fn($i) => $righe[$i] ?? null, $ids)));
}

/**
 * Salva la lista dei test da un testo con un indirizzo per riga. Regole di Brevo: al massimo 20, e ognuno deve essere già
 * un contatto. Ritorna '' oppure l'errore (e allora non salva niente).
 */
function newsletter_lista_test_salva(string $testo): string {
    $righe = array_values(array_unique(array_filter(array_map(fn($x) => mb_strtolower(trim($x)), preg_split('/[\s,;]+/', $testo)))));
    if (count($righe) > NL_TEST_MASSIMO) return 'Al massimo ' . NL_TEST_MASSIMO . ' indirizzi nella lista dei test: ne hai scritti ' . count($righe) . '.';
    $ids = []; $non = []; $male = [];
    foreach ($righe as $e) {
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { $male[] = $e; continue; }
        $id = newsletter_id_da_email($e);
        $id ? $ids[] = $id : $non[] = $e;
    }
    if ($male) return 'Questi non sono indirizzi email validi: ' . implode(', ', array_slice($male, 0, 5)) . '. Correggili e salva di nuovo.';
    if ($non) return 'Questi indirizzi non sono ancora contatti: ' . implode(', ', array_slice($non, 0, 5)) . '. Ogni indirizzo della lista dei test deve essere un contatto: crealo in Contatti, Crea un contatto, poi salva di nuovo.';
    save_setting('newsletter_lista_test', json_encode($ids));
    return '';
}

/** Aggiunge un contatto alla lista dei test (dalla prova di una campagna). Ritorna '' o l'errore. */
function newsletter_lista_test_aggiungi(int $id): string {
    $ids = array_map(fn($c) => (int)$c['id'], newsletter_lista_test());
    if (in_array($id, $ids, true)) return '';
    if (count($ids) >= NL_TEST_MASSIMO) return 'La lista dei test ha già ' . NL_TEST_MASSIMO . ' indirizzi: è il massimo.';
    $ids[] = $id;
    save_setting('newsletter_lista_test', json_encode($ids));
    return '';
}
