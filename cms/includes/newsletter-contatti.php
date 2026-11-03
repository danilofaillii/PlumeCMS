<?php
/**
 * 2.8.0: la base dei contatti come Brevo (docs-sviluppo/brevo-confronto.md, fase 1).
 *
 * Contatti (tabella newsletter_iscritti, la stessa di prima), attributi con i tipi di Brevo, valori degli attributi,
 * cartelle delle liste, lista nera con il motivo, cronologia e statistiche della scheda del contatto, segnaposti
 * {{ contact.NOME }} nelle email. Lo schema e la migrazione stanno in newsletter_schema() (includes/newsletter.php),
 * con la lista nera (newsletter_contatto_blocca, newsletter_contatto_sblocca) e la storia (newsletter_storia_scrivi).
 *
 * Attributi:
 *   - di sistema (sistema = 1): legati a una colonna di newsletter_iscritti (colonna). Non si cancellano;
 *   - normali (categoria 'normal'): text, float, date, boolean, multiple-choice; categoria ('category'): una scelta fra
 *     opzioni con valore numerico ed etichetta. I valori stanno in newsletter_valori, una riga per valore;
 *   - calcolati ('calculated', per contatto) e globali ('global', su tutti i contatti): una formula COUNT o SUM come
 *     Brevo, calcolata quando serve (newsletter_formula_valore). Si ricalcolano sempre: è il "ricorrente" di Brevo.
 * Regole di Brevo: al massimo 200 attributi, 50 calcolati, 30 globali; nome e tipo non si cambiano; un valore del tipo
 * sbagliato si ignora senza errore; le opzioni al massimo 200 caratteri; cancellare un attributo toglie i suoi valori a
 * tutti; la modifica in massa vale solo per data, sì/no e categoria.
 */

const NL_ATTR_TIPI = ['text' => 'Testo', 'float' => 'Numero', 'date' => 'Data', 'boolean' => 'Sì/No', 'category' => 'Categoria', 'multiple-choice' => 'Scelta multipla'];
const NL_ATTR_MASSIMO = 200;
const NL_CALCOLATI_MASSIMO = 50;
const NL_GLOBALI_MASSIMO = 30;
const NL_LISTE_MASSIMO = 300;
const NL_CARTELLE_MASSIMO = 300;
/** I nomi di Brevo per gli attributi di sistema che in plumeCMS hanno il nome italiano: valgono anche nei segnaposti. */
const NL_ATTR_ALIAS = ['FIRSTNAME' => 'NOME', 'LASTNAME' => 'COGNOME'];
/** Le fonti delle formule: cosa si conta (COUNT) per contatto. Nomi di Brevo e nomi italiani. */
const NL_FORMULA_FONTI = [
    'READS' => 'aperture', 'APERTURE' => 'aperture', 'CLICKS' => 'clic', 'CLIC' => 'clic',
    'SENT' => 'invii', 'INVII' => 'invii', 'FORMS' => 'form', 'FORM' => 'form', 'CONTACTS' => 'contatti', 'CONTATTI' => 'contatti',
];
/** Le colonne della tabella Contatti che ci sono sempre (Brevo: CONTACT, SUBSCRIBED, BLOCKLISTED, CREATION_DATE, LAST_CHANGED). */
const NL_COLONNE_FISSE = ['CONTATTO' => 'Contatto', 'ISCRITTO' => 'Iscritto', 'LISTA_NERA' => 'In lista nera', 'CREATION_DATE' => 'Data di creazione', 'LAST_CHANGED' => 'Ultima modifica'];

// ------------------------------------------------------------
// Attributi
// ------------------------------------------------------------

/** Tutti gli attributi, di sistema prima, con le opzioni (per categoria e scelta multipla). Chiave: il nome. */
function newsletter_attributi(bool $ricarica = false): array {
    static $cache = null;
    if ($cache !== null && !$ricarica) return $cache;
    newsletter_schema();
    $cache = [];
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_attributi') . ' ORDER BY sistema DESC, ordine, id') as $a) {
        $a['opzioni'] = [];
        $cache[$a['nome']] = $a;
    }
    $per = [];
    foreach ($cache as $n => $a) $per[(int)$a['id']] = $n;
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_attributi_opzioni') . ' ORDER BY ordine, valore') as $o) {
        if (isset($per[(int)$o['attributo_id']])) $cache[$per[(int)$o['attributo_id']]]['opzioni'][(int)$o['valore']] = $o['etichetta'];
    }
    return $cache;
}

/** Il nome di un attributo come lo scrive Brevo: maiuscolo, lettere senza accenti, numeri e trattini bassi. */
function newsletter_attributo_nome(string $n): string {
    $n = trim($n);
    $n = strtr($n, ['à' => 'A', 'á' => 'A', 'è' => 'E', 'é' => 'E', 'ì' => 'I', 'í' => 'I', 'ò' => 'O', 'ó' => 'O', 'ù' => 'U', 'ú' => 'U',
        'À' => 'A', 'È' => 'E', 'É' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U']);
    $n = strtoupper(preg_replace('/[\s\-]+/', '_', $n));
    return preg_replace('/[^A-Z0-9_]/', '', $n);
}

/** Il testo di un'etichetta o di un'opzione: emoji come entità (tabelle a 3 byte), niente controlli, al massimo $max. */
function newsletter_testo_pulito(string $s, int $max): string {
    $s = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s));
    return mb_substr(newsletter_4byte_dentro($s), 0, $max);
}

/**
 * Crea un attributo. $d: nome, tipo, etichetta, categoria ('normal', 'category', 'calculated', 'global'), opzioni
 * (righe di testo), formula. Ritorna '' oppure il messaggio d'errore in parole semplici.
 */
function newsletter_attributo_crea(array $d): string {
    newsletter_schema();
    $nome = newsletter_attributo_nome((string)($d['nome'] ?? ''));
    $tipo = (string)($d['tipo'] ?? '');
    $cat = (string)($d['categoria'] ?? 'normal');
    if (in_array($tipo, ['calculated', 'global'], true)) { $cat = $tipo; $tipo = 'float'; }
    if ($tipo === 'category') $cat = 'category';
    if (!in_array($cat, ['normal', 'category', 'calculated', 'global'], true)) return 'Scegli il tipo dell\'attributo.';
    if (!isset(NL_ATTR_TIPI[$tipo])) return 'Scegli il tipo dell\'attributo.';
    if ($nome === '' || !preg_match('/^[A-Z][A-Z0-9_]{0,49}$/', $nome)) return 'Scrivi il nome dell\'attributo: lettere, numeri e trattini bassi, e deve cominciare con una lettera (per esempio CITTA o DATA_NASCITA).';
    $tutti = newsletter_attributi(true);
    if (isset($tutti[$nome]) || isset(NL_ATTR_ALIAS[$nome]) || in_array($nome, ['CONTACT_ID', 'SMS', 'WHATSAPP', 'LANDLINE_NUMBER', 'SUBSCRIBED', 'BLOCKLISTED', 'CONTATTO', 'ISCRITTO', 'LISTA_NERA'], true)) {
        return "Esiste già un attributo che si chiama $nome: scegli un altro nome.";
    }
    $personali = array_filter($tutti, fn($a) => !(int)$a['sistema']);
    if (count($personali) >= NL_ATTR_MASSIMO) return 'Hai già ' . NL_ATTR_MASSIMO . ' attributi: è il massimo. Cancellane uno che non usi più.';
    if ($cat === 'calculated' && count(array_filter($personali, fn($a) => $a['categoria'] === 'calculated')) >= NL_CALCOLATI_MASSIMO) return 'Hai già ' . NL_CALCOLATI_MASSIMO . ' attributi calcolati: è il massimo.';
    if ($cat === 'global' && count(array_filter($personali, fn($a) => $a['categoria'] === 'global')) >= NL_GLOBALI_MASSIMO) return 'Hai già ' . NL_GLOBALI_MASSIMO . ' valori globali: è il massimo.';
    $formula = '';
    if ($cat === 'calculated' || $cat === 'global') {
        $formula = strtoupper(preg_replace('/\s+/', '', (string)($d['formula'] ?? '')));
        $e = newsletter_formula_controlla($formula, $cat);
        if ($e !== '') return $e;
    }
    $opzioni = [];
    if (in_array($tipo, ['category', 'multiple-choice'], true)) {
        $righe = is_array($d['opzioni'] ?? null) ? $d['opzioni'] : preg_split('/\r\n|\r|\n/', (string)($d['opzioni'] ?? ''));
        foreach ($righe as $r) {
            $r = trim((string)$r);
            if ($r === '') continue;
            if (mb_strlen($r) > 200) return 'Un\'opzione può avere al massimo 200 caratteri: accorcia «' . mb_substr($r, 0, 40) . '...».';
            $opzioni[] = newsletter_testo_pulito($r, 200);
        }
        $opzioni = array_values(array_unique($opzioni));
        if (!$opzioni) return 'Scrivi almeno un\'opzione, una per riga.';
    }
    $et = newsletter_testo_pulito((string)($d['etichetta'] ?? ''), 100);
    $ordine = (int)db()->query('SELECT COALESCE(MAX(ordine), 0) + 1 FROM ' . tbl('newsletter_attributi'))->fetchColumn();
    db()->prepare('INSERT INTO ' . tbl('newsletter_attributi') . ' (nome, etichetta, categoria, tipo, sistema, colonna, formula, ordine, creato) VALUES (?, ?, ?, ?, 0, \'\', ?, ?, NOW())')
        ->execute([$nome, $et, $cat, $tipo, $formula, $ordine]);
    $id = (int)db()->lastInsertId();
    $ins = db()->prepare('INSERT INTO ' . tbl('newsletter_attributi_opzioni') . ' (attributo_id, valore, etichetta, ordine) VALUES (?, ?, ?, ?)');
    foreach ($opzioni as $i => $o) $ins->execute([$id, $i + 1, $o, $i + 1]);
    newsletter_attributi(true);
    return '';
}

/** Cambia l'etichetta (il nome resta: Brevo non lo cambia) e aggiunge o toglie opzioni. Ritorna '' o l'errore. */
function newsletter_attributo_modifica(int $id, string $etichetta, array $nuove = [], array $togli = []): string {
    $a = newsletter_attributo_per_id($id);
    if (!$a || (int)$a['sistema']) return 'Gli attributi di sistema non si modificano.';
    db()->prepare('UPDATE ' . tbl('newsletter_attributi') . ' SET etichetta = ? WHERE id = ?')->execute([newsletter_testo_pulito($etichetta, 100), $id]);
    if (in_array($a['tipo'], ['category', 'multiple-choice'], true)) {
        foreach ($nuove as $o) {
            $o = trim((string)$o);
            if ($o === '') continue;
            if (mb_strlen($o) > 200) return 'Un\'opzione può avere al massimo 200 caratteri.';
            $v = (int)db()->query('SELECT COALESCE(MAX(valore), 0) + 1 FROM ' . tbl('newsletter_attributi_opzioni') . ' WHERE attributo_id = ' . $id)->fetchColumn();
            db()->prepare('INSERT INTO ' . tbl('newsletter_attributi_opzioni') . ' (attributo_id, valore, etichetta, ordine) VALUES (?, ?, ?, ?)')->execute([$id, $v, newsletter_testo_pulito($o, 200), $v]);
        }
        foreach ($togli as $v) {
            $v = (int)$v;
            db()->prepare('DELETE FROM ' . tbl('newsletter_attributi_opzioni') . ' WHERE attributo_id = ? AND valore = ?')->execute([$id, $v]);
            // Chi aveva quell'opzione la perde (come in Brevo, deleteMultiAttributeOptions).
            if ($a['tipo'] === 'multiple-choice') db()->prepare('DELETE FROM ' . tbl('newsletter_valori') . ' WHERE attributo_id = ? AND opzione = ?')->execute([$id, $v]);
            else db()->prepare('DELETE FROM ' . tbl('newsletter_valori') . ' WHERE attributo_id = ? AND v_num = ?')->execute([$id, $v]);
        }
    }
    newsletter_attributi(true);
    return '';
}

/** Cancella un attributo non di sistema: i suoi valori si perdono per tutti i contatti. */
function newsletter_attributo_elimina(int $id): bool {
    $a = newsletter_attributo_per_id($id);
    if (!$a || (int)$a['sistema']) return false;
    db()->prepare('DELETE FROM ' . tbl('newsletter_valori') . ' WHERE attributo_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_attributi_opzioni') . ' WHERE attributo_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_attributi') . ' WHERE id = ? AND sistema = 0')->execute([$id]);
    newsletter_attributi(true);
    return true;
}

function newsletter_attributo_per_id(int $id): ?array {
    foreach (newsletter_attributi() as $a) if ((int)$a['id'] === $id) return $a;
    return null;
}

/** L'etichetta da mostrare: quella scritta, altrimenti il nome. */
function newsletter_attributo_etichetta(array $a): string {
    return newsletter_4byte_fuori((string)($a['etichetta'] !== '' ? $a['etichetta'] : $a['nome']));
}

// ------------------------------------------------------------
// Formule (attributi calcolati e globali)
// ------------------------------------------------------------

/**
 * Una formula come Brevo: COUNT[FONTE] o COUNT[FONTE,FONTE,>,NOW(-15)] (gli ultimi 15 giorni), SUM[ATTRIBUTO] per i
 * valori globali (somma di un attributo numerico su tutti i contatti). Fonti: READS (aperture), CLICKS (clic), SENT
 * (email ricevute), FORMS (form compilati); per i globali anche CONTACTS (i contatti iscritti). Ritorna l'analisi o null.
 */
function newsletter_formula_analizza(string $f): ?array {
    $f = strtoupper(preg_replace('/\s+/', '', $f));
    if (!preg_match('/^(COUNT|SUM)\[([A-Z][A-Z0-9_\-]{0,49})(?:,([A-Z][A-Z0-9_\-]{0,49}),(<|>|<=|>=),NOW\((-?\d{1,4})\))?\]$/', $f, $m)) return null;
    return ['funzione' => $m[1], 'fonte' => $m[2], 'giorni' => isset($m[5]) ? abs((int)$m[5]) : 0, 'operatore' => $m[4] ?? '', 'cond' => $m[3] ?? ''];
}

/** '' se la formula va bene per quella categoria, altrimenti l'errore in parole semplici. */
function newsletter_formula_controlla(string $f, string $cat): string {
    $a = newsletter_formula_analizza($f);
    $esempio = $cat === 'global' ? 'COUNT[CONTACTS] oppure SUM[PUNTI]' : 'COUNT[READS,READS,>,NOW(-15)]';
    if (!$a) return "La formula non si legge: scrivila come negli esempi, per esempio $esempio.";
    if ($a['cond'] !== '' && $a['cond'] !== $a['fonte']) return 'Nella condizione della formula ripeti la stessa fonte (per esempio COUNT[READS,READS,>,NOW(-15)]).';
    if ($a['funzione'] === 'COUNT') {
        $fonte = NL_FORMULA_FONTI[$a['fonte']] ?? '';
        if ($fonte === '') return 'COUNT conta READS (aperture), CLICKS (clic), SENT (email ricevute), FORMS (form compilati)' . ($cat === 'global' ? ' o CONTACTS (contatti iscritti)' : '') . '.';
        if ($fonte === 'contatti' && $cat !== 'global') return 'CONTACTS si usa solo nei valori globali.';
        return '';
    }
    // SUM: solo di un attributo numerico (Brevo: SUM non si usa con un attributo di testo), e solo nei globali.
    if ($cat !== 'global') return 'SUM si usa nei valori globali: per un contatto usa COUNT.';
    $attr = newsletter_attributi()[$a['fonte']] ?? null;
    if (!$attr || $attr['tipo'] !== 'float' || in_array($attr['categoria'], ['calculated', 'global'], true)) return 'SUM somma un attributo di tipo Numero: crea prima l\'attributo, poi la formula (per esempio SUM[PUNTI]).';
    return '';
}

/** Il valore di una formula, per un contatto ($id > 0) o per tutti ($id = 0, valori globali). */
function newsletter_formula_valore(string $formula, int $id = 0): float {
    $a = newsletter_formula_analizza($formula);
    if (!$a) return 0;
    newsletter_flussi_schema();
    $quando = function (string $col) use ($a): string {
        if (!$a['giorni'] || $a['operatore'] === '') return '';
        // NOW(-15) con > : più recente di 15 giorni fa; con < : più vecchio.
        $op = in_array($a['operatore'], ['<', '>', '<=', '>='], true) ? $a['operatore'] : '>';
        return " AND $col $op DATE_SUB(NOW(), INTERVAL " . (int)$a['giorni'] . ' DAY)';
    };
    $chi = $id ? ' = ' . $id : ' IS NOT NULL';
    if ($a['funzione'] === 'SUM') {
        $attr = newsletter_attributi()[$a['fonte']] ?? null;
        if (!$attr) return 0;
        return (float)db()->query('SELECT COALESCE(SUM(v.v_num), 0) FROM ' . tbl('newsletter_valori') . ' v JOIN ' . tbl('newsletter_iscritti') . ' i ON i.id = v.contatto_id WHERE v.attributo_id = ' . (int)$attr['id'] . ($id ? ' AND v.contatto_id = ' . $id : ''))->fetchColumn();
    }
    $fonte = NL_FORMULA_FONTI[$a['fonte']] ?? '';
    switch ($fonte) {
        case 'aperture':
        case 'clic':
            $tipo = $fonte === 'clic' ? 'clic' : 'apertura';
            return (float)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_eventi') . " WHERE tipo = '$tipo' AND iscritto_id$chi" . $quando('creato'))->fetchColumn();
        case 'invii':
            return (float)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_coda') . " WHERE stato = 'inviata' AND iscritto_id$chi" . $quando('inviata_at'))->fetchColumn()
                + (float)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_storia') . " WHERE tipo = 'flusso_email' AND contatto_id$chi" . $quando('creato'))->fetchColumn();
        case 'form':
            try {
                $w = $id ? ' AND i.id = ' . $id : '';
                return (float)db()->query('SELECT COUNT(*) FROM ' . tbl('moduli_invii') . ' m JOIN ' . tbl('newsletter_iscritti') . " i ON i.email = m.email WHERE 1$w" . $quando('m.creato'))->fetchColumn();
            } catch (PDOException $e) { return 0; }
        case 'contatti':
            return (float)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " WHERE stato = 'attivo'" . $quando('creato'))->fetchColumn();
    }
    return 0;
}

/** Un numero scritto come in Italia, senza zeri inutili (12,5; 3). */
function newsletter_numero_testo($n): string {
    $n = (float)$n;
    $s = number_format($n, 6, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
}

// ------------------------------------------------------------
// Valori degli attributi
// ------------------------------------------------------------

/** La riga del contatto (newsletter_iscritti), o null. */
function newsletter_contatto(int $id): ?array {
    newsletter_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * I valori degli attributi di più contatti, grezzi: [id contatto => [NOME => valore]]. Testo: stringa (con le entità);
 * numero: float; data: 'AAAA-MM-GG'; sì/no: 1 o 0; categoria: il numero dell'opzione; scelta multipla: lista di numeri.
 * Gli attributi di sistema si leggono dalle colonne, i calcolati dalla formula.
 */
function newsletter_valori_di(array $ids, ?array $soloNomi = null): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $attr = newsletter_attributi();
    $out = array_fill_keys($ids, []);
    $in = implode(',', $ids);
    $vuole = fn($n) => $soloNomi === null || in_array($n, $soloNomi, true);
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_iscritti') . " WHERE id IN ($in)") as $r) {
        foreach ($attr as $n => $a) {
            if (!(int)$a['sistema'] || !$vuole($n)) continue;
            $v = $r[$a['colonna']] ?? null;
            if ($a['colonna'] === 'doi_at') $v = $v ? 1 : 0;
            elseif ($a['tipo'] === 'boolean') $v = (int)$v;
            elseif ($a['tipo'] === 'date') $v = $v ? substr((string)$v, 0, 10) : '';
            $out[(int)$r['id']][$n] = $v ?? '';
        }
    }
    $perId = [];
    foreach ($attr as $n => $a) if (!(int)$a['sistema'] && $vuole($n)) $perId[(int)$a['id']] = $a;
    if ($perId) {
        $st = db()->query('SELECT * FROM ' . tbl('newsletter_valori') . " WHERE contatto_id IN ($in) AND attributo_id IN (" . implode(',', array_keys($perId)) . ') ORDER BY opzione');
        foreach ($st as $v) {
            $a = $perId[(int)$v['attributo_id']];
            $c = (int)$v['contatto_id'];
            switch ($a['tipo']) {
                case 'text': $out[$c][$a['nome']] = (string)$v['v_testo']; break;
                case 'float': $out[$c][$a['nome']] = (float)$v['v_num']; break;
                case 'date': $out[$c][$a['nome']] = (string)$v['v_data']; break;
                case 'boolean': $out[$c][$a['nome']] = (int)$v['v_num']; break;
                case 'category': $out[$c][$a['nome']] = (int)$v['v_num']; break;
                case 'multiple-choice': $out[$c][$a['nome']][] = (int)$v['opzione']; break;
            }
        }
        foreach ($perId as $a) {
            if ($a['categoria'] !== 'calculated') continue;
            foreach ($ids as $c) $out[$c][$a['nome']] = newsletter_formula_valore((string)$a['formula'], $c);
        }
    }
    return $out;
}

/** Un valore grezzo in parole, per la tabella, la scheda e i segnaposti. */
function newsletter_valore_testo(array $a, $v): string {
    if ($v === null || $v === '' || $v === []) return '';
    switch ($a['tipo']) {
        case 'boolean': return (int)$v ? 'Sì' : 'No';
        case 'date': $t = strtotime((string)$v); return $t ? date('d/m/Y', $t) : '';
        case 'float': return newsletter_numero_testo($v);
        case 'category': return newsletter_4byte_fuori((string)($a['opzioni'][(int)$v] ?? ''));
        case 'multiple-choice': return implode(', ', array_map(fn($o) => newsletter_4byte_fuori((string)($a['opzioni'][(int)$o] ?? '')), (array)$v));
    }
    return newsletter_4byte_fuori((string)$v);
}

/**
 * Legge un valore scritto per un attributo. Ritorna [ok, valore normalizzato]: ok = false se il valore è del tipo
 * sbagliato (Brevo lo ignora senza errore); il valore '' (o []) vuol dire "togli il valore".
 */
function newsletter_valore_leggi(array $a, $v): array {
    if (is_array($v) && $a['tipo'] !== 'multiple-choice') return [false, null];
    if (!is_array($v)) $v = trim((string)$v);
    if ($v === '' || $v === []) return [true, $a['tipo'] === 'multiple-choice' ? [] : ''];
    switch ($a['tipo']) {
        case 'text':
            return [true, newsletter_testo_pulito((string)$v, 500)];
        case 'float':
            $n = str_replace([' ', "\u{00A0}"], '', (string)$v);
            if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $n)) $n = str_replace('.', '', $n);
            $n = str_replace(',', '.', $n);
            if (!is_numeric($n) || abs((float)$n) >= 1e14) return [false, null];
            return [true, (float)$n];
        case 'date':
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $f) {
                $d = DateTime::createFromFormat('!' . $f, (string)$v);
                if ($d && $d->format($f) === (string)$v && (int)$d->format('Y') >= 1000) return [true, $d->format('Y-m-d')];
            }
            return [false, null];
        case 'boolean':
            $s = mb_strtolower((string)$v);
            if (in_array($s, ['1', 'sì', 'si', 'true', 'yes', 'vero', 'on'], true)) return [true, 1];
            if (in_array($s, ['0', 'no', 'false', 'falso', 'off'], true)) return [true, 0];
            return [false, null];
        case 'category':
            if (ctype_digit((string)$v) && isset($a['opzioni'][(int)$v])) return [true, (int)$v];
            // Come Brevo nei filtri: vale anche l'etichetta.
            foreach ($a['opzioni'] as $k => $et) if (mb_strtolower(newsletter_4byte_fuori($et)) === mb_strtolower((string)$v)) return [true, (int)$k];
            return [false, null];
        case 'multiple-choice':
            $scelte = [];
            foreach ((array)$v as $x) {
                $x = trim((string)$x);
                if ($x === '') continue;
                if (ctype_digit($x) && isset($a['opzioni'][(int)$x])) { $scelte[] = (int)$x; continue; }
                $trovata = false;
                foreach ($a['opzioni'] as $k => $et) if (mb_strtolower(newsletter_4byte_fuori($et)) === mb_strtolower($x)) { $scelte[] = (int)$k; $trovata = true; }
                if (!$trovata) return [false, null];
            }
            return [true, array_values(array_unique($scelte))];
    }
    return [false, null];
}

/**
 * Salva gli attributi di un contatto. $dati: [NOME => valore]; solo i nomi presenti si toccano. I valori del tipo
 * sbagliato si ignorano (come Brevo). Gli attributi di sistema scrivibili: EMAIL, NOME, COGNOME, EXT_ID, OPT_IN.
 * Ritorna ['cambiati' => [nomi], 'errori' => [messaggi]] (errori solo per EMAIL ed EXT_ID già usati o non validi).
 */
function newsletter_valori_salva(int $id, array $dati, bool $storia = true): array {
    $c = newsletter_contatto($id);
    $out = ['cambiati' => [], 'errori' => []];
    if (!$c) return $out;
    $attr = newsletter_attributi();
    $prima = newsletter_valori_di([$id])[$id] ?? [];
    foreach ($dati as $n => $v) {
        $n = NL_ATTR_ALIAS[$n] ?? (string)$n;
        $a = $attr[$n] ?? null;
        if (!$a || in_array($a['categoria'], ['calculated', 'global'], true)) continue;
        if ((int)$a['sistema']) {
            $e = newsletter_contatto_colonna($c, $a, $v);
            if ($e === true) $out['cambiati'][] = $n; elseif (is_string($e)) $out['errori'][] = $e;
            continue;
        }
        [$ok, $val] = newsletter_valore_leggi($a, $v);
        if (!$ok) continue; // del tipo sbagliato: si ignora, senza errore (Brevo)
        if ($val === ($prima[$n] ?? ($a['tipo'] === 'multiple-choice' ? [] : ''))) continue;
        $aid = (int)$a['id'];
        db()->prepare('DELETE FROM ' . tbl('newsletter_valori') . ' WHERE contatto_id = ? AND attributo_id = ?')->execute([$id, $aid]);
        if ($val !== '' && $val !== []) {
            $ins = db()->prepare('INSERT INTO ' . tbl('newsletter_valori') . ' (contatto_id, attributo_id, opzione, v_testo, v_num, v_data) VALUES (?, ?, ?, ?, ?, ?)');
            switch ($a['tipo']) {
                case 'text': $ins->execute([$id, $aid, 0, $val, null, null]); break;
                case 'float': case 'boolean': case 'category': $ins->execute([$id, $aid, 0, '', $val, null]); break;
                case 'date': $ins->execute([$id, $aid, 0, '', null, $val]); break;
                case 'multiple-choice': foreach ($val as $o) $ins->execute([$id, $aid, $o, '', null, null]); break;
            }
        }
        $out['cambiati'][] = $n;
    }
    if ($out['cambiati']) {
        db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . ' SET aggiornato = NOW() WHERE id = ?')->execute([$id]);
        if ($storia) newsletter_storia_scrivi($id, 'attributo', 'Attributi cambiati: ' . implode(', ', $out['cambiati']));
    }
    return $out;
}

/** Scrive un attributo di sistema nella sua colonna. true se è cambiato, false se no, una stringa se è un errore. */
function newsletter_contatto_colonna(array $c, array $a, $v) {
    if (is_array($v)) return false;
    $v = trim((string)$v);
    $id = (int)$c['id'];
    $t = tbl('newsletter_iscritti');
    switch ($a['colonna']) {
        case 'nome': case 'cognome':
            $v = mb_substr(newsletter_testo_pulito($v, 100), 0, 100);
            if ($v === (string)$c[$a['colonna']]) return false;
            db()->prepare("UPDATE $t SET `{$a['colonna']}` = ? WHERE id = ?")->execute([$v, $id]);
            return true;
        case 'ext_id':
            $v = mb_substr(newsletter_testo_pulito($v, 190), 0, 190);
            if ($v === (string)($c['ext_id'] ?? '')) return false;
            if ($v !== '') {
                $st = db()->prepare("SELECT id FROM $t WHERE ext_id = ? AND id <> ?");
                $st->execute([$v, $id]);
                if ($st->fetchColumn()) return "L'identificativo esterno $v è già di un altro contatto: due contatti non possono averlo uguale.";
            }
            db()->prepare("UPDATE $t SET ext_id = ? WHERE id = ?")->execute([$v === '' ? null : $v, $id]);
            return true;
        case 'opt_in':
            [$ok, $b] = newsletter_valore_leggi($a, $v);
            if (!$ok || $b === '' || (int)$b === (int)$c['opt_in']) return false;
            db()->prepare("UPDATE $t SET opt_in = ? WHERE id = ?")->execute([(int)$b, $id]);
            return true;
        case 'tracciamento': // 2.16.0: il consenso al tracciamento di aperture e clic
            [$ok, $b] = newsletter_valore_leggi($a, $v);
            if (!$ok || $b === '' || !array_key_exists('tracciamento', $c) || (int)$b === (int)$c['tracciamento']) return false;
            db()->prepare("UPDATE $t SET tracciamento = ? WHERE id = ?")->execute([(int)$b, $id]);
            return true;
        case 'email':
            $v = mb_strtolower($v);
            if ($v === (string)$c['email']) return false;
            if (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 190) return "L'email «" . mb_substr($v, 0, 60) . "» non è valida: controlla di averla scritta per intero (per esempio nome@esempio.it).";
            $st = db()->prepare("SELECT id FROM $t WHERE email = ?");
            $st->execute([$v]);
            if ($st->fetchColumn()) return "L'email $v è già di un altro contatto.";
            db()->prepare("UPDATE $t SET email = ? WHERE id = ?")->execute([$v, $id]);
            newsletter_storia_scrivi($id, 'email', 'Email cambiata da ' . $c['email'] . ' a ' . $v);
            // Brevo: cambiare l'email di un contatto in lista nera toglie la lista nera e lo fa tornare iscritto.
            if (in_array($c['stato'], NL_BLOCCATI, true)) newsletter_contatto_sblocca($id, 'email cambiata');
            return true;
    }
    return false;
}

// ------------------------------------------------------------
// Contatti
// ------------------------------------------------------------

/**
 * Crea un contatto a mano (Brevo: Crea un contatto). $dati: [NOME => valore], EMAIL obbligatoria; $liste: id.
 * Ritorna ['id' => nuovo] oppure ['esiste' => id] (non crea doppioni) oppure ['errore' => messaggio].
 */
function newsletter_contatto_crea(array $dati, array $liste = []): array {
    newsletter_schema();
    $email = mb_strtolower(trim((string)($dati['EMAIL'] ?? '')));
    if ($email === '') return ['errore' => 'Scrivi l\'email del contatto: serve per mandargli le email.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) return ['errore' => "L'email «" . mb_substr($email, 0, 60) . "» non è valida: controlla di averla scritta per intero (per esempio nome@esempio.it)."];
    if ($gia = newsletter_id_da_email($email)) return ['esiste' => $gia];
    $ext = trim((string)($dati['EXT_ID'] ?? ''));
    if ($ext !== '') {
        $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_iscritti') . ' WHERE ext_id = ?');
        $st->execute([newsletter_testo_pulito($ext, 190)]);
        if ($g = (int)$st->fetchColumn()) return ['esiste' => $g];
    }
    $ora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO ' . tbl('newsletter_iscritti') . " (email, stato, fonte, consenso_versione, creato, aggiornato) VALUES (?, 'attivo', 'manuale', 'manuale', ?, ?)")->execute([$email, $ora, $ora]);
    $id = (int)db()->lastInsertId();
    newsletter_storia_scrivi($id, 'creato', 'Creato a mano dal pannello');
    unset($dati['EMAIL']);
    newsletter_valori_salva($id, $dati, false);
    foreach (array_unique(array_map('intval', $liste)) as $l) if ($l > 0) newsletter_in_lista($id, $l);
    return ['id' => $id];
}

/** Lo stato del canale email in parole: Iscritto, In attesa di conferma, In lista nera (con il motivo). */
function newsletter_stato_testo(array $c): string {
    if ($c['stato'] === 'attivo') return 'Iscritto';
    if ($c['stato'] === 'in_attesa') return 'In attesa di conferma';
    return 'In lista nera';
}

/** Il motivo della lista nera in parole ('' se non è in lista nera). */
function newsletter_motivo_testo(array $c): string {
    if (!in_array($c['stato'], NL_BLOCCATI, true)) return '';
    $m = (string)($c['blocco_motivo'] ?? '');
    if (isset(NL_MOTIVI[$m])) return NL_MOTIVI[$m][0];
    return ['uscito' => NL_MOTIVI['unsubscribedViaEmail'][0], 'rimbalzato' => NL_MOTIVI['hardBounce'][0], 'spam' => NL_MOTIVI['contactFlaggedAsSpam'][0], 'bloccato' => NL_MOTIVI['adminBlocked'][0]][$c['stato']] ?? '';
}

/**
 * I filtri della pagina Contatti, uguali per l'elenco e per le azioni di gruppo.
 *   cerca   un pezzo di email, nome o cognome (o l'identificativo esterno)
 *   lista   l'id di una lista caricata (-1 = in nessuna lista)
 *   stato   iscritti | lista_nera | in_attesa | errori | motivo:<codice>; valgono ancora gli stati di prima della 2.8.0
 *   segmento  2.10.0: l'id di un segmento caricato
 *   f         2.10.0: "Aggiungi filtro", le regole del motore dei segmenti (JSON in base64, newsletter_regole_valida)
 * Ritorna [where, parametri, filtri normalizzati]; con un filtro f che non si legge, errore_filtro nei filtri.
 */
function newsletter_contatti_filtro(array $q): array {
    $cerca = mb_substr(trim((string)($q['cerca'] ?? '')), 0, 190);
    $stato = (string)($q['stato'] ?? '');
    $vecchi = ['attivo' => 'iscritti', 'uscito' => 'motivo:unsubscribedViaEmail', 'rimbalzato' => 'motivo:hardBounce', 'spam' => 'motivo:contactFlaggedAsSpam'];
    $stato = $vecchi[$stato] ?? $stato;
    if (!in_array($stato, ['iscritti', 'lista_nera', 'in_attesa', 'errori'], true) && !(str_starts_with($stato, 'motivo:') && isset(NL_MOTIVI[substr($stato, 7)]))) $stato = '';
    $lista = (int)($q['lista'] ?? 0);
    $w = []; $par = [];
    if ($cerca !== '') {
        $l = '%' . addcslashes($cerca, '%_\\') . '%';
        $w[] = "(i.email LIKE ? ESCAPE '\\\\' OR i.nome LIKE ? ESCAPE '\\\\' OR i.cognome LIKE ? ESCAPE '\\\\' OR i.ext_id LIKE ? ESCAPE '\\\\' OR CONCAT(i.nome, ' ', i.cognome) LIKE ? ESCAPE '\\\\')";
        array_push($par, $l, $l, $l, $l, $l);
    }
    if ($stato === 'iscritti') $w[] = "i.stato = 'attivo'";
    elseif ($stato === 'in_attesa') $w[] = "i.stato = 'in_attesa'";
    elseif ($stato === 'lista_nera') $w[] = "i.stato IN ('uscito', 'rimbalzato', 'spam', 'bloccato')";
    elseif ($stato === 'errori') $w[] = "i.stato = 'attivo' AND i.errori_di_fila >= 3";
    elseif ($stato !== '') {
        $m = substr($stato, 7);
        $st = NL_MOTIVI[$m][1];
        // Anche le righe di prima della migrazione, senza motivo, con lo stato giusto.
        $w[] = "i.stato IN ('uscito', 'rimbalzato', 'spam', 'bloccato') AND (i.blocco_motivo = ? OR (i.blocco_motivo = '' AND i.stato = ?))";
        $par[] = $m; $par[] = $st;
    }
    if ($lista > 0) { $w[] = 'EXISTS (SELECT 1 FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = i.id AND l.lista_id = ?)'; $par[] = $lista; }
    if ($lista === -1) $w[] = 'NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_iscritti_liste') . ' l WHERE l.iscritto_id = i.id)';
    // 2.10.0: segmento caricato e "Aggiungi filtro" con il motore dei segmenti (il file si carica qui: opcache).
    $segmento = max(0, (int)($q['segmento'] ?? 0));
    $fz = is_string($q['f'] ?? null) ? mb_substr(trim($q['f']), 0, 20000) : '';
    $errF = '';
    if (($segmento || $fz !== '') && !function_exists('newsletter_regole_valida')) require_once __DIR__ . '/newsletter-segmenti.php';
    if ($segmento && function_exists('newsletter_segmento_where')) {
        if (newsletter_segmento($segmento)) $w[] = newsletter_segmento_where($segmento, $par); else $segmento = 0;
    }
    if ($fz !== '' && function_exists('newsletter_regole_valida')) {
        $r = newsletter_regole_valida($fz);
        if (isset($r['errore'])) { $errF = $r['errore']; $fz = ''; }
        elseif (!$r['regole']['gruppi']) $fz = '';
        else { $fz = rtrim(strtr(base64_encode(json_encode($r['regole'], JSON_INVALID_UTF8_SUBSTITUTE)), '+/', '-_'), '='); $w[] = newsletter_regole_where($r['regole'], $par); }
    }
    $norm = ['cerca' => $cerca, 'stato' => $stato, 'lista' => $lista, 'segmento' => $segmento, 'f' => $fz];
    if ($errF !== '') $norm['errore_filtro'] = $errF;
    return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $par, $norm];
}

/** Gli id di tutti i contatti del filtro (le azioni su "tutti i N"), al massimo 100000. */
function newsletter_contatti_ids(array $q): array {
    [$where, $par] = newsletter_contatti_filtro($q);
    $st = db()->prepare('SELECT i.id FROM ' . tbl('newsletter_iscritti') . " i$where ORDER BY i.id LIMIT 100000");
    $st->execute($par);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Toglie i contatti da liste (tutte se $liste è vuota). Ritorna quante appartenenze ha tolto. */
function newsletter_togli_da_liste(array $ids, array $liste = []): int {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return 0;
    $liste = array_values(array_filter(array_map('intval', $liste)));
    $n = 0;
    foreach (array_chunk($ids, 500) as $pezzo) {
        $in = implode(',', $pezzo);
        $w = $liste ? ' AND lista_id IN (' . implode(',', $liste) . ')' : '';
        $righe = db()->query('SELECT iscritto_id, lista_id FROM ' . tbl('newsletter_iscritti_liste') . " WHERE iscritto_id IN ($in)$w")->fetchAll();
        db()->exec('DELETE FROM ' . tbl('newsletter_iscritti_liste') . " WHERE iscritto_id IN ($in)$w");
        foreach ($righe as $r) {
            newsletter_storia_scrivi((int)$r['iscritto_id'], 'lista_tolto', newsletter_nome_lista((int)$r['lista_id']), (int)$r['lista_id']);
            newsletter_flussi_innesco('lista_tolto', (int)$r['lista_id'], (int)$r['iscritto_id']); // 2.15.0: l'innesco "tolto da una lista"
        }
        $n += count($righe);
    }
    return $n;
}

/**
 * Modifica in massa un attributo (Brevo: Altre azioni > Modifica): solo attributi data, sì/no e categoria. Ritorna
 * quanti contatti ha cambiato, oppure -1 se l'attributo non si può cambiare così o il valore è del tipo sbagliato.
 */
function newsletter_modifica_in_massa(array $ids, string $nome, $valore): int {
    $a = newsletter_attributi()[$nome] ?? null;
    if (!$a || (int)$a['sistema'] || !in_array($a['tipo'], ['date', 'boolean', 'category'], true)) return -1;
    [$ok] = newsletter_valore_leggi($a, $valore);
    if (!$ok) return -1;
    $n = 0;
    foreach (array_filter(array_map('intval', $ids)) as $id) if (newsletter_valori_salva($id, [$nome => $valore])['cambiati']) $n++;
    return $n;
}

/**
 * Elimina definitivamente (Brevo: Delete permanently). Scelta di plumeCMS, più prudente di Brevo: è "Cancella dati"
 * del GDPR, quindi l'impronta dell'email resta nella lista di soppressione e un'importazione non la rimette dentro.
 * Con gli invii dei form se ne vanno anche i loro file. Ritorna quanti contatti ha eliminato.
 */
function newsletter_contatti_elimina(array $ids): int {
    $n = 0;
    require_once __DIR__ . '/form.php';
    foreach (array_filter(array_map('intval', $ids)) as $id) {
        try {
            form_schema();
            $st = db()->prepare('SELECT i.id FROM ' . tbl('moduli_invii') . ' i JOIN ' . tbl('newsletter_iscritti') . ' n ON n.email = i.email WHERE n.id = ?');
            $st->execute([$id]);
            form_allegati_cancella($st->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) { error_log('elimina contatto, file dei form: ' . $e->getMessage()); }
        if (newsletter_cancella_dati($id) !== '') $n++;
    }
    if ($n && function_exists('form_file_orfani')) form_file_orfani();
    return $n;
}

/** Le liste di un contatto (id). */
function newsletter_liste_di(int $id): array {
    $st = db()->prepare('SELECT lista_id FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ?');
    $st->execute([$id]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// ------------------------------------------------------------
// Cartelle e liste
// ------------------------------------------------------------

/** Gli id delle liste di sistema (la principale, quelle dei moduli): si rinominano, non si eliminano. */
function newsletter_liste_di_sistema(): array {
    $ids = [];
    foreach (db()->query('SELECT setting_key, setting_value FROM ' . tbl('settings') . " WHERE setting_key LIKE 'newsletter\\_lista\\_%'") as $r) {
        if ((int)$r['setting_value'] > 0 && ctype_digit((string)$r['setting_value'])) $ids[] = (int)$r['setting_value'];
    }
    return array_values(array_unique($ids));
}

/** Le cartelle con le loro liste (newsletter_liste() con i conteggi). Una lista senza cartella va nella prima. */
function newsletter_cartelle(): array {
    newsletter_schema();
    $c = [];
    foreach (db()->query('SELECT * FROM ' . tbl('newsletter_cartelle') . ' ORDER BY nome') as $r) $c[(int)$r['id']] = $r + ['liste' => []];
    if (!$c) {
        db()->exec('INSERT IGNORE INTO ' . tbl('newsletter_cartelle') . " (nome, creato) VALUES ('Le tue liste', NOW())");
        return newsletter_cartelle();
    }
    $prima = array_key_first($c);
    foreach (newsletter_liste() as $l) {
        $k = isset($c[(int)($l['cartella_id'] ?? 0)]) ? (int)$l['cartella_id'] : $prima;
        $c[$k]['liste'][] = $l;
    }
    return $c;
}

/** Crea una cartella. Ritorna l'id, oppure un messaggio d'errore. */
function newsletter_cartella_crea(string $nome) {
    $nome = newsletter_testo_pulito($nome, 100);
    if ($nome === '') return 'Scrivi il nome della cartella.';
    if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_cartelle'))->fetchColumn() >= NL_CARTELLE_MASSIMO) return 'Hai già ' . NL_CARTELLE_MASSIMO . ' cartelle: è il massimo.';
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_cartelle') . ' WHERE nome = ?');
    $st->execute([$nome]);
    if ($st->fetchColumn()) return 'Esiste già una cartella con questo nome.';
    db()->prepare('INSERT INTO ' . tbl('newsletter_cartelle') . ' (nome, creato) VALUES (?, NOW())')->execute([$nome]);
    return (int)db()->lastInsertId();
}

/**
 * Elimina una cartella e le sue liste (Brevo: i contatti restano, senza ritorno). Una cartella con una lista di sistema
 * non si elimina: prima si sposta quella lista. Ritorna '' o l'errore.
 */
function newsletter_cartella_elimina(int $id): string {
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_liste') . ' WHERE cartella_id = ?');
    $st->execute([$id]);
    $liste = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (array_intersect($liste, newsletter_liste_di_sistema())) return 'In questa cartella c\'è una lista che usa il sito per le iscrizioni: spostala in un\'altra cartella, poi elimina la cartella.';
    if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_cartelle'))->fetchColumn() <= 1) return 'Questa è l\'unica cartella: le liste devono stare in una cartella. Creane un\'altra prima di eliminarla.';
    foreach ($liste as $l) newsletter_lista_elimina($l);
    db()->prepare('DELETE FROM ' . tbl('newsletter_cartelle') . ' WHERE id = ?')->execute([$id]);
    return '';
}

/** Crea una lista in una cartella. Ritorna l'id oppure il messaggio d'errore. */
function newsletter_lista_crea(string $nome, int $cartella, string $descr = '') {
    $nome = newsletter_testo_pulito($nome, 100);
    $descr = newsletter_testo_pulito($descr, 255);
    if ($nome === '') return 'Scrivi il nome della lista.';
    if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_liste'))->fetchColumn() >= NL_LISTE_MASSIMO) return 'Hai già ' . NL_LISTE_MASSIMO . ' liste: è il massimo.';
    if (!$cartella || !db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_cartelle') . ' WHERE id = ' . (int)$cartella)->fetchColumn()) {
        $cartella = (int)array_key_first(newsletter_cartelle());
    }
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_liste') . ' WHERE nome = ?');
    $st->execute([$nome]);
    if ($st->fetchColumn()) return 'Esiste già una lista con questo nome.';
    db()->prepare('INSERT INTO ' . tbl('newsletter_liste') . ' (nome, descrizione, cartella_id, creato) VALUES (?, ?, ?, NOW())')->execute([$nome, $descr, $cartella]);
    return (int)db()->lastInsertId();
}

/** Elimina una lista (non di sistema): i contatti restano, nelle altre loro liste. */
function newsletter_lista_elimina(int $id): bool {
    if (in_array($id, newsletter_liste_di_sistema(), true)) return false;
    db()->prepare('DELETE FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE lista_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_liste_uscite') . ' WHERE lista_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_liste') . ' WHERE id = ?')->execute([$id]);
    return true;
}

// ------------------------------------------------------------
// Scheda del contatto: statistiche e cronologia
// ------------------------------------------------------------

/**
 * Le statistiche email di un contatto negli ultimi $giorni (Brevo: 90): inviate, consegnate, aperte, cliccate (con i
 * link), hard bounce, soft bounce, spam, disiscrizioni. Campagne (coda ed eventi) e automazioni (storia ed eventi).
 */
function newsletter_contatto_statistiche(int $id, int $giorni = 90): array {
    newsletter_flussi_schema();
    $da = date('Y-m-d H:i:s', time() - $giorni * 86400);
    $uno = function (string $sql, array $p) { $st = db()->prepare($sql); $st->execute($p); return (int)$st->fetchColumn(); };
    $inviate = $uno('SELECT COUNT(*) FROM ' . tbl('newsletter_coda') . " WHERE iscritto_id = ? AND stato = 'inviata' AND inviata_at >= ?", [$id, $da])
        + $uno('SELECT COUNT(*) FROM ' . tbl('newsletter_storia') . " WHERE contatto_id = ? AND tipo = 'flusso_email' AND creato >= ?", [$id, $da]);
    $ev = fn(string $tipo) => $uno('SELECT COUNT(DISTINCT campagna_id, flusso_id, passo) FROM ' . tbl('newsletter_eventi') . ' WHERE iscritto_id = ? AND tipo = ? AND creato >= ?', [$id, $tipo, $da]);
    $hard = $ev('rimbalzo');
    $link = db()->prepare('SELECT url, COUNT(*) n, MAX(creato) ultimo FROM ' . tbl('newsletter_eventi') . " WHERE iscritto_id = ? AND tipo = 'clic' AND creato >= ? GROUP BY url ORDER BY n DESC, ultimo DESC LIMIT 20");
    $link->execute([$id, $da]);
    return [
        'inviate' => $inviate, 'consegnate' => max(0, $inviate - $hard), 'aperte' => $ev('apertura'), 'cliccate' => $ev('clic'),
        'hard' => $hard, 'soft' => $uno('SELECT COUNT(*) FROM ' . tbl('newsletter_storia') . " WHERE contatto_id = ? AND tipo = 'soft_bounce' AND creato >= ?", [$id, $da]),
        'spam' => $ev('spam'), 'uscite' => $ev('uscita'), 'link' => $link->fetchAll(), 'giorni' => $giorni,
    ];
}

/** I tipi della Cronologia, con il nome da mostrare nel filtro. */
const NL_CRONOLOGIA_TIPI = [
    'email' => 'Email ricevute', 'apertura' => 'Aperture', 'clic' => 'Clic', 'form' => 'Form compilati', 'liste' => 'Liste',
    'lista_nera' => 'Lista nera e disiscrizioni', 'rimbalzi' => 'Rimbalzi', 'attributi' => 'Attributi e dati', 'iscrizione' => 'Iscrizione e conferma',
];

/**
 * La cronologia del contatto, dalla più recente: campagne ricevute (coda), aperture, clic, rimbalzi, spam e uscite
 * (eventi), form compilati (moduli_invii con la sua email) e la storia (liste, lista nera, attributi, importazioni...).
 * $f: tipo (una chiave di NL_CRONOLOGIA_TIPI), da, a (AAAA-MM-GG), cerca. Ogni voce: quando, tipo, testo.
 */
function newsletter_contatto_cronologia(int $id, array $f = [], int $quante = 200): array {
    newsletter_flussi_schema();
    $c = newsletter_contatto($id);
    if (!$c) return [];
    $tipo = (string)($f['tipo'] ?? '');
    $da = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($f['da'] ?? '')) ? $f['da'] . ' 00:00:00' : '1970-01-01 00:00:00';
    $a = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($f['a'] ?? '')) ? $f['a'] . ' 23:59:59' : '2999-12-31 23:59:59';
    $vuole = fn(string $t) => $tipo === '' || $tipo === $t;
    $voci = [];
    $nomeC = [];
    foreach (db()->query('SELECT id, oggetto FROM ' . tbl('newsletter_campagne')) as $r) $nomeC[(int)$r['id']] = newsletter_4byte_fuori((string)$r['oggetto']) ?: '(senza oggetto)';
    $nomeF = [];
    foreach (db()->query('SELECT id, nome FROM ' . tbl('newsletter_flussi')) as $r) $nomeF[(int)$r['id']] = newsletter_4byte_fuori((string)$r['nome']);
    $da_dove = fn(array $r) => (int)$r['campagna_id'] ? 'campagna «' . ($nomeC[(int)$r['campagna_id']] ?? 'cancellata') . '»' : 'automazione «' . ($nomeF[(int)$r['flusso_id']] ?? 'cancellata') . '»';
    if ($vuole('email')) {
        $st = db()->prepare('SELECT campagna_id, inviata_at FROM ' . tbl('newsletter_coda') . " WHERE iscritto_id = ? AND stato = 'inviata' AND inviata_at BETWEEN ? AND ? ORDER BY inviata_at DESC LIMIT $quante");
        $st->execute([$id, $da, $a]);
        foreach ($st as $r) $voci[] = ['quando' => $r['inviata_at'], 'tipo' => 'email', 'testo' => 'Ha ricevuto la campagna «' . ($nomeC[(int)$r['campagna_id']] ?? 'cancellata') . '»'];
    }
    $mappaEv = ['apertura' => 'apertura', 'clic' => 'clic', 'rimbalzo' => 'rimbalzi', 'spam' => 'lista_nera', 'uscita' => 'lista_nera'];
    $tipiEv = array_keys(array_filter($mappaEv, fn($t) => $vuole($t)));
    if ($tipiEv) {
        $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_eventi') . ' WHERE iscritto_id = ? AND tipo IN (\'' . implode("','", $tipiEv) . "') AND creato BETWEEN ? AND ? ORDER BY creato DESC LIMIT $quante");
        $st->execute([$id, $da, $a]);
        foreach ($st as $r) {
            $testo = ['apertura' => 'Ha aperto la ' . $da_dove($r), 'clic' => 'Ha cliccato ' . $r['url'] . ' nella ' . $da_dove($r), 'rimbalzo' => 'Hard bounce sulla ' . $da_dove($r),
                'spam' => 'Ha segnalato come spam la ' . $da_dove($r), 'uscita' => 'Si è disiscritto dalla ' . $da_dove($r)][$r['tipo']];
            $voci[] = ['quando' => $r['creato'], 'tipo' => $mappaEv[$r['tipo']], 'testo' => $testo];
        }
    }
    if ($vuole('form')) {
        try {
            $st = db()->prepare('SELECT i.creato, i.pagina, m.nome FROM ' . tbl('moduli_invii') . ' i LEFT JOIN ' . tbl('moduli') . " m ON m.id = i.modulo_id WHERE i.email = ? AND i.creato BETWEEN ? AND ? ORDER BY i.creato DESC LIMIT $quante");
            $st->execute([$c['email'], $da, $a]);
            foreach ($st as $r) $voci[] = ['quando' => $r['creato'], 'tipo' => 'form', 'testo' => 'Ha compilato il form «' . ($r['nome'] ?? 'cancellato') . '»' . ($r['pagina'] !== '' ? ' nella pagina ' . $r['pagina'] : '')];
        } catch (PDOException $e) {}
    }
    $mappaS = ['lista_aggiunto' => 'liste', 'lista_tolto' => 'liste', 'bloccato' => 'lista_nera', 'sbloccato' => 'lista_nera', 'soft_bounce' => 'rimbalzi',
        'attributo' => 'attributi', 'email' => 'attributi', 'creato' => 'iscrizione', 'importato' => 'iscrizione', 'iscrizione' => 'iscrizione', 'doi' => 'iscrizione', 'form' => 'form', 'flusso_email' => 'email', 'preferenze' => 'attributi', 'test' => 'form']; // 2.22.0: test con l'addon Test e questionari
    $tipiS = array_keys(array_filter($mappaS, fn($t) => $vuole($t)));
    if ($tipiS) {
        $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_storia') . ' WHERE contatto_id = ? AND tipo IN (\'' . implode("','", $tipiS) . "') AND creato BETWEEN ? AND ? ORDER BY creato DESC, id DESC LIMIT $quante");
        $st->execute([$id, $da, $a]);
        foreach ($st as $r) {
            $d = newsletter_4byte_fuori((string)$r['dettaglio']);
            $testo = ['lista_aggiunto' => 'Aggiunto alla lista «' . $d . '»', 'lista_tolto' => 'Tolto dalla lista «' . $d . '»', 'bloccato' => 'In lista nera: ' . $d, 'sbloccato' => $d,
                'soft_bounce' => 'Soft bounce (errore temporaneo): ' . $d, 'flusso_email' => 'Ha ricevuto l\'email dell\'automazione ' . $d][$r['tipo']] ?? $d;
            $voci[] = ['quando' => $r['creato'], 'tipo' => $mappaS[$r['tipo']], 'testo' => $testo];
        }
    }
    $cerca = mb_strtolower(trim((string)($f['cerca'] ?? '')));
    if ($cerca !== '') $voci = array_values(array_filter($voci, fn($v) => str_contains(mb_strtolower($v['testo']), $cerca)));
    usort($voci, fn($x, $y) => strcmp($y['quando'], $x['quando']));
    return array_slice($voci, 0, $quante);
}

// ------------------------------------------------------------
// Segnaposti nelle email
// ------------------------------------------------------------

/**
 * I segnaposti degli attributi come Brevo: {{ contact.NOME }}, con i filtri | upper, | lower, | capfirst e
 * | default:"testo" (anche default : 'testo'). FIRSTNAME e LASTNAME valgono come NOME e COGNOME. Un attributo che non
 * esiste, o vuoto, diventa il valore predefinito (o niente). In una prova (id 0) valgono i dati passati in $iscritto.
 */
function newsletter_segnaposti(string $s, array $iscritto, bool $html): string {
    $id = (int)($iscritto['id'] ?? 0);
    static $cache = [];
    return preg_replace_callback('/\{\{(.{1,200}?)\}\}/su', function ($m) use ($id, $iscritto, $html, &$cache) {
        $dentro = $html ? html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $m[1];
        if (!preg_match('/^\s*contact\.([A-Za-z0-9_\-]{1,50})\s*((?:\|\s*[a-z]+\s*(?::\s*(?:"[^"]*"|\'[^\']*\'))?\s*)*)$/u', $dentro, $p)) return $m[0];
        $nome = strtoupper($p[1]);
        $nome = NL_ATTR_ALIAS[$nome] ?? $nome;
        $attr = newsletter_attributi();
        $v = '';
        if (isset($attr[$nome])) {
            $a = $attr[$nome];
            if ($a['categoria'] === 'global') $v = newsletter_numero_testo(newsletter_formula_valore((string)$a['formula'], 0));
            elseif ($id) {
                $cache[$id] ??= newsletter_valori_di([$id])[$id] ?? [];
                $v = newsletter_valore_testo($a, $cache[$id][$nome] ?? '');
            } else {
                $chiave = ['NOME' => 'nome', 'COGNOME' => 'cognome', 'EMAIL' => 'email'][$nome] ?? '';
                $v = $chiave !== '' ? trim((string)($iscritto[$chiave] ?? '')) : '';
            }
        }
        preg_match_all('/\|\s*([a-z]+)\s*(?::\s*(?:"([^"]*)"|\'([^\']*)\'))?/u', $p[2], $filtri, PREG_SET_ORDER);
        foreach ($filtri as $fl) {
            $arg = ($fl[2] ?? '') !== '' ? $fl[2] : ($fl[3] ?? '');
            switch ($fl[1]) {
                case 'default': if (trim($v) === '') $v = $arg; break;
                case 'upper': $v = mb_strtoupper($v); break;
                case 'lower': $v = mb_strtolower($v); break;
                case 'capfirst': $v = mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1); break;
            }
        }
        return $html ? h($v) : $v;
    }, $s);
}
