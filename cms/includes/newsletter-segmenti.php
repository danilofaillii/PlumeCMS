<?php
/**
 * 2.10.0: i segmenti come Brevo (docs-sviluppo/brevo-confronto.md, fase 3, capitoli 1.4 e 3.4).
 *
 * Il motore delle condizioni: un insieme di regole (JSON) diventa una condizione SQL sui contatti (alias i, la tabella
 * newsletter_iscritti). Lo usano i segmenti salvati, "Aggiungi filtro" della pagina Contatti, l'esportazione per segmento
 * e i bivi dei flussi (newsletter_flusso_condizione). Le campagne lo useranno dalla fase 4 (destinatari ed esclusioni).
 *
 * Forma delle regole (due livelli, come i gruppi E/O di Brevo):
 *   {"op": "e"|"o", "gruppi": [{"op": "e"|"o", "condizioni": [{"campo": ..., "op": ..., ...}, ...]}, ...]}
 * Ogni condizione: campo (un nome dell'elenco chiuso di newsletter_regole_campi(), o attr:NOME di un attributo), op (un
 * operatore dell'elenco del suo tipo), e solo le chiavi che servono: valore, valore2, n, unita, campagna, non.
 * Sicurezza: nomi di colonna, tabelle e operatori non arrivano mai dal JSON all'SQL. newsletter_regole_valida() rifiuta
 * quello che non è nell'elenco e riscrive i valori nel loro tipo; newsletter_regole_where() scrive l'SQL con le colonne
 * dell'elenco chiuso e tutti i valori come parametri (?), gli id come interi.
 * Al massimo 100 condizioni (Brevo), 20 gruppi, segmenti dentro segmenti fino a 5 livelli e senza giri.
 */

const NL_SEG_CONDIZIONI_MAX = 100;
const NL_SEG_GRUPPI_MAX = 20;
const NL_SEG_MASSIMO = 300;          // scelta di plumeCMS: Brevo non dice il suo limite
const NL_SEG_CARTELLE_MASSIMO = 100;
const NL_VISTE_MASSIMO = 30;         // per utente
const NL_SEG_PROFONDITA = 5;
/** Le unità delle date relative (Brevo: X giorni, settimane, mesi fa) -> la parola di MySQL. Elenco chiuso. */
const NL_SEG_UNITA = ['giorni' => 'DAY', 'settimane' => 'WEEK', 'mesi' => 'MONTH'];
/** Gli operatori per tipo di valore, con il testo che si legge. Elenco chiuso: nient'altro arriva all'SQL. */
const NL_SEG_OPERATORI = [
    'text' => ['uguale' => 'è', 'diverso' => 'non è', 'contiene' => 'contiene', 'non_contiene' => 'non contiene', 'comincia' => 'comincia con', 'finisce' => 'finisce con', 'vuoto' => 'è vuoto', 'pieno' => 'non è vuoto'],
    'float' => ['uguale' => 'è uguale a', 'diverso' => 'è diverso da', 'maggiore' => 'è maggiore di', 'minore' => 'è minore di', 'fra' => 'è fra', 'vuoto' => 'è vuoto', 'pieno' => 'non è vuoto'],
    'date' => ['uguale' => 'è il', 'prima' => 'è prima del', 'dopo' => 'è dopo il', 'fra' => 'è fra', 'ultimi' => 'negli ultimi', 'piu_di' => 'più di ... fa', 'prossimi' => 'nei prossimi', 'mese_corrente' => 'in questo mese', 'vuoto' => 'è vuota', 'pieno' => 'non è vuota'],
    'boolean' => ['si' => 'è sì', 'no' => 'è no (o non impostato)'],
    'category' => ['uno_di' => 'è uno di', 'nessuno_di' => 'non è nessuno di', 'vuoto' => 'è vuoto', 'pieno' => 'non è vuoto'],
    'multiple-choice' => ['almeno_una' => 'ha almeno una di', 'tutte' => 'ha tutte', 'nessuna_di' => 'non ha nessuna di', 'vuoto' => 'è vuoto', 'pieno' => 'non è vuoto'],
    'lista' => ['in' => 'è in almeno una di', 'non_in' => 'non è in nessuna di'],
    'nessuna_lista' => ['si' => 'non sta in nessuna lista', 'no' => 'sta in almeno una lista'],
    'segmento' => ['in' => 'fa parte del segmento', 'non_in' => 'non fa parte del segmento'],
    'email' => ['aperta' => 'ha aperto', 'non_aperta' => 'non ha aperto', 'cliccata' => 'ha cliccato', 'non_cliccata' => 'non ha cliccato', 'ricevuta' => 'ha ricevuto', 'non_ricevuta' => 'non ha ricevuto'],
    'link' => ['cliccato' => 'ha cliccato il link', 'non_cliccato' => 'non ha cliccato il link'],
    'form' => ['inviato' => 'ha inviato', 'non_inviato' => 'non ha inviato'],
    'lista_nera' => ['si' => 'è in lista nera', 'no' => 'non è in lista nera', 'motivo' => 'è in lista nera per'],
    'stato' => ['iscritto' => 'è iscritto', 'non_iscritto' => 'non è iscritto', 'in_attesa' => 'è in attesa di conferma'],
    'iscritto_da' => ['almeno' => 'da almeno', 'meno' => 'da meno di'],
    'attivita' => ['si' => 'ha aperto o cliccato qualcosa', 'no' => 'non ha aperto né cliccato niente'],
    'flusso_email' => ['aperta' => "ha aperto l'email", 'non_aperta' => "non ha aperto l'email", 'cliccata' => "ha cliccato nell'email", 'non_cliccata' => "non ha cliccato nell'email"],
];
/** Le colonne di newsletter_iscritti degli attributi di sistema, con il tipo per le condizioni. Elenco chiuso. */
const NL_SEG_COLONNE = ['email' => 'text', 'nome' => 'text', 'cognome' => 'text', 'ext_id' => 'text', 'opt_in' => 'boolean', 'doi_at' => 'boolean', 'creato' => 'date', 'aggiornato' => 'date'];

function newsletter_segmenti_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    newsletter_schema();
    newsletter_flussi_schema();
    $tabelle = [
        'newsletter_segmenti' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_segmenti') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `cartella_id` INT(11) NOT NULL DEFAULT 0,
            `condizioni` MEDIUMTEXT,
            `conteggio` INT(11) NOT NULL DEFAULT 0,
            `contato_at` DATETIME DEFAULT NULL,
            `utente_id` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `nome` (`nome`), KEY `cartella` (`cartella_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_segmenti_cartelle' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_segmenti_cartelle') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `nome` (`nome`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        // Le viste della pagina Contatti: filtri e colonne con un nome, per utente.
        'newsletter_viste' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_viste') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `utente_id` INT(11) NOT NULL DEFAULT 0,
            `nome` VARCHAR(100) NOT NULL,
            `filtro` TEXT,
            `colonne` TEXT,
            `ordine` VARCHAR(40) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `utente` (`utente_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME . 'indici-1');
    if (get_setting('newsletter_segmenti_impronta', '') === $impronta) return;
    foreach ($tabelle as $sql) db()->exec($sql);
    // Indici per le condizioni sul comportamento: conteggio sotto un secondo con migliaia di contatti ed eventi.
    $indici = [
        'newsletter_eventi' => ['seg_contatto' => '(`iscritto_id`, `tipo`, `creato`)'],
        'newsletter_coda' => ['seg_contatto' => '(`iscritto_id`, `stato`, `inviata_at`)'],
        'newsletter_storia' => ['seg_tipo' => '(`contatto_id`, `tipo`, `creato`)'],
    ];
    try { require_once __DIR__ . '/form.php'; form_schema(); $indici['moduli_invii'] = ['seg_email' => '(`email`, `creato`)']; } catch (Throwable $e) { error_log('segmenti, form: ' . $e->getMessage()); }
    foreach ($indici as $t => $lista) {
        $ci = db()->query('SHOW INDEX FROM ' . tbl($t))->fetchAll(PDO::FETCH_COLUMN, 2);
        foreach ($lista as $nome => $col) if (!in_array($nome, $ci, true)) db()->exec('ALTER TABLE ' . tbl($t) . " ADD KEY `$nome` $col");
    }
    save_setting('newsletter_segmenti_impronta', $impronta);
}

// ------------------------------------------------------------
// I campi delle condizioni (elenco chiuso)
// ------------------------------------------------------------

/**
 * I campi delle condizioni, per il costruttore e per il controllo: chiave => [gruppo, etichetta, tipo dei valori].
 * Gli attributi (attr:NOME) prendono il tipo dell'attributo; calcolati e globali non si usano (il loro valore si calcola
 * contatto per contatto, non in SQL). $flusso: anche le condizioni che valgono solo dentro un flusso.
 */
function newsletter_regole_campi(bool $flusso = false): array {
    $c = [];
    foreach (newsletter_attributi() as $n => $a) {
        if (in_array($a['categoria'], ['calculated', 'global'], true)) continue;
        $tipo = (int)$a['sistema'] ? (NL_SEG_COLONNE[$a['colonna']] ?? '') : $a['tipo'];
        if ($tipo === '' || !isset(NL_SEG_OPERATORI[$tipo])) continue;
        $c['attr:' . $n] = ['Dati del contatto', newsletter_attributo_etichetta($a), $tipo];
    }
    $c += [
        'stato' => ['Iscrizione e lista nera', 'Iscrizione all\'email', 'stato'],
        'lista_nera' => ['Iscrizione e lista nera', 'Lista nera', 'lista_nera'],
        'iscritto_da' => ['Iscrizione e lista nera', 'Iscritto da tanti giorni', 'iscritto_da'],
        'lista' => ['Liste e segmenti', 'Liste', 'lista'],
        'nessuna_lista' => ['Liste e segmenti', 'In nessuna lista', 'nessuna_lista'],
        'segmento' => ['Liste e segmenti', 'Segmento', 'segmento'],
        'email' => ['Email', 'Email aperte, cliccate, ricevute', 'email'],
        'link' => ['Email', 'Link preciso cliccato', 'link'],
        'attivita' => ['Email', 'Aperture e clic recenti (qualsiasi email)', 'attivita'],
        'form' => ['Form', 'Form inviato', 'form'],
    ];
    if ($flusso) $c['flusso_email'] = ['Questa automazione', 'Email di questa automazione', 'flusso_email'];
    return $c;
}

/** Il JSON per il costruttore del pannello: campi, operatori, unità, liste, segmenti, campagne, form, motivi, link noti. */
function newsletter_regole_definizione(int $segmentoEscluso = 0): array {
    newsletter_segmenti_schema();
    $campi = [];
    foreach (newsletter_regole_campi() as $k => [$g, $et, $tipo]) {
        $d = ['g' => $g, 'e' => $et, 't' => $tipo];
        if (str_starts_with($k, 'attr:')) {
            $a = newsletter_attributi()[substr($k, 5)];
            if ($a['opzioni']) $d['o'] = array_map(fn($x) => newsletter_4byte_fuori((string)$x), $a['opzioni']);
        }
        $campi[$k] = $d;
    }
    $liste = [];
    foreach (newsletter_liste() as $l) $liste[(int)$l['id']] = newsletter_4byte_fuori((string)$l['nome']);
    $seg = [];
    foreach (newsletter_segmenti() as $s) if ((int)$s['id'] !== $segmentoEscluso) $seg[(int)$s['id']] = newsletter_4byte_fuori((string)$s['nome']);
    $camp = [];
    foreach (db()->query('SELECT id, oggetto FROM ' . tbl('newsletter_campagne') . " WHERE stato IN ('in_invio', 'sospesa', 'inviata') ORDER BY id DESC LIMIT 200") as $r) $camp[(int)$r['id']] = newsletter_4byte_fuori((string)$r['oggetto']) ?: '(senza oggetto)';
    $form = [];
    try { foreach (db()->query('SELECT id, nome FROM ' . tbl('moduli') . ' ORDER BY nome') as $r) $form[(int)$r['id']] = newsletter_4byte_fuori((string)$r['nome']); } catch (PDOException $e) {}
    $link = db()->query('SELECT url FROM ' . tbl('newsletter_eventi') . " WHERE tipo = 'clic' AND url <> '' GROUP BY url ORDER BY MAX(id) DESC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
    $motivi = [];
    foreach (NL_MOTIVI as $k => $m) $motivi[$k] = $m[0];
    return ['campi' => $campi, 'operatori' => NL_SEG_OPERATORI, 'unita' => array_combine(array_keys(NL_SEG_UNITA), ['giorni', 'settimane', 'mesi']),
        'liste' => $liste, 'segmenti' => $seg, 'campagne' => $camp, 'form' => $form, 'link' => $link, 'motivi' => $motivi, 'massimo' => NL_SEG_CONDIZIONI_MAX];
}

// ------------------------------------------------------------
// Controllo delle regole
// ------------------------------------------------------------

/**
 * Legge e controlla le regole (array o JSON, anche in base64 per gli indirizzi). Ritorna ['regole' => regole pulite]
 * oppure ['errore' => messaggio in parole semplici]. Niente arriva all'SQL senza passare da qui.
 * $ctx: flusso (id del flusso: ammette flusso_email), segmento (id del segmento che si salva: niente giri).
 */
function newsletter_regole_valida($in, array $ctx = []): array {
    if (is_string($in)) {
        $s = trim($in);
        if (strlen($s) > 200000) return ['errore' => 'Le condizioni sono troppo lunghe.'];
        if ($s !== '' && $s[0] !== '{') $s = (string)base64_decode(strtr($s, '-_', '+/'), true);
        // Profondità 7: due livelli di gruppi più i valori. Un annidamento più fondo non si legge (attacco).
        $in = $s === '' ? [] : json_decode($s, true, 7);
        if (!is_array($in)) return ['errore' => 'Le condizioni non si leggono: rifai il filtro.'];
    }
    if (!is_array($in)) return ['errore' => 'Le condizioni non si leggono: rifai il filtro.'];
    $campi = newsletter_regole_campi(!empty($ctx['flusso']));
    $gruppi = [];
    $tot = 0;
    $gIn = $in['gruppi'] ?? [];
    if (!is_array($gIn)) return ['errore' => 'Le condizioni non si leggono: rifai il filtro.'];
    if (count($gIn) > NL_SEG_GRUPPI_MAX) return ['errore' => 'Al massimo ' . NL_SEG_GRUPPI_MAX . ' gruppi di condizioni.'];
    foreach (array_values($gIn) as $gi => $g) {
        if (!is_array($g) || !is_array($g['condizioni'] ?? null)) return ['errore' => 'Le condizioni non si leggono: rifai il filtro.'];
        $conds = [];
        foreach (array_values($g['condizioni']) as $ci => $c) {
            if (++$tot > NL_SEG_CONDIZIONI_MAX) return ['errore' => 'Al massimo ' . NL_SEG_CONDIZIONI_MAX . ' condizioni in tutto: togline qualcuna.'];
            $dove = 'Condizione ' . ($ci + 1) . ($gi || count($gIn) > 1 ? ' del gruppo ' . ($gi + 1) : '');
            $r = newsletter_condizione_valida($c, $campi, $ctx);
            if (is_string($r)) return ['errore' => "$dove: $r"];
            $conds[] = $r;
        }
        if ($conds) $gruppi[] = ['op' => ($g['op'] ?? 'e') === 'o' ? 'o' : 'e', 'condizioni' => $conds];
    }
    return ['regole' => ['op' => ($in['op'] ?? 'e') === 'o' ? 'o' : 'e', 'gruppi' => $gruppi]];
}

/** Un intero da un valore che può essere qualunque cosa. */
function nl_seg_int($v): int { return is_scalar($v) && preg_match('/^-?\d{1,9}$/', trim((string)$v)) ? (int)$v : 0; }

/** Una condizione pulita, oppure il messaggio d'errore. */
function newsletter_condizione_valida($c, array $campi, array $ctx) {
    if (!is_array($c)) return 'non si legge.';
    $campo = is_string($c['campo'] ?? null) ? $c['campo'] : '';
    if (!isset($campi[$campo])) return 'scegli su cosa filtrare.';
    $tipo = $campi[$campo][2];
    $op = is_string($c['op'] ?? null) ? $c['op'] : '';
    if (!isset(NL_SEG_OPERATORI[$tipo][$op])) return 'scegli la condizione.';
    $out = ['campo' => $campo, 'op' => $op];
    if (!empty($c['non'])) $out['non'] = true;
    $num = fn($k) => nl_seg_int($c[$k] ?? 0);
    $periodo = function () use ($c, &$out) {
        $n = max(0, min(36500, nl_seg_int($c['n'] ?? 0)));
        if ($n > 0) { $out['n'] = $n; $out['unita'] = isset(NL_SEG_UNITA[$c['unita'] ?? '']) ? (string)$c['unita'] : 'giorni'; }
    };
    $ids = function ($v, int $max = 50): array {
        $v = is_array($v) ? $v : [$v];
        return array_values(array_unique(array_filter(array_map('nl_seg_int', array_slice($v, 0, $max)), fn($x) => $x > 0)));
    };
    if (str_starts_with($campo, 'attr:')) {
        $a = newsletter_attributi()[substr($campo, 5)];
        if (in_array($op, ['vuoto', 'pieno', 'mese_corrente', 'si', 'no'], true)) return $out;
        if (in_array($tipo, ['category', 'multiple-choice'], true)) {
            $v = array_values(array_filter($ids($c['valore'] ?? [], 200), fn($x) => isset($a['opzioni'][$x])));
            if (!$v) return 'scegli almeno un\'opzione.';
            $out['valore'] = $v;
            return $out;
        }
        if ($tipo === 'date' && in_array($op, ['ultimi', 'piu_di', 'prossimi'], true)) {
            $periodo();
            if (empty($out['n'])) return 'scrivi quanti giorni, settimane o mesi.';
            return $out;
        }
        $leggi = function ($v) use ($tipo) {
            if (!is_scalar($v) || trim((string)$v) === '') return null;
            if ($tipo === 'text') return newsletter_testo_pulito((string)$v, 500);
            [$ok, $x] = newsletter_valore_leggi(['tipo' => $tipo, 'opzioni' => []], (string)$v);
            return $ok && $x !== '' ? $x : null;
        };
        $v = $leggi($c['valore'] ?? null);
        if ($v === null) return $tipo === 'date' ? 'scrivi la data (per esempio 2026-12-31).' : ($tipo === 'float' ? 'scrivi il numero.' : 'scrivi il valore.');
        $out['valore'] = $v;
        if ($op === 'fra') {
            $v2 = $leggi($c['valore2'] ?? null);
            if ($v2 === null) return 'scrivi anche il secondo valore.';
            if ($v2 < $v) [$v, $v2] = [$v2, $v];
            $out['valore'] = $v; $out['valore2'] = $v2;
        }
        return $out;
    }
    switch ($tipo) {
        case 'lista':
            $v = $ids($c['valore'] ?? []);
            if (!$v) return 'scegli almeno una lista.';
            $out['valore'] = $v;
            return $out;
        case 'segmento':
            $v = $num('valore');
            if ($v <= 0) return 'scegli il segmento.';
            if (!empty($ctx['segmento']) && $v === (int)$ctx['segmento']) return 'un segmento non può contenere se stesso.';
            if (!empty($ctx['segmento']) && newsletter_segmento_contiene($v, (int)$ctx['segmento'])) return 'quel segmento usa già questo: farebbe un giro senza fine.';
            $out['valore'] = $v;
            return $out;
        case 'email':
            $out['campagna'] = max(-2, $num('campagna'));
            $periodo();
            return $out;
        case 'link':
            $u = is_scalar($c['valore'] ?? null) ? mb_substr(trim((string)$c['valore']), 0, 500) : '';
            if ($u === '') return 'scrivi o scegli il link.';
            $out['valore'] = $u;
            $out['campagna'] = max(-2, $num('campagna'));
            return $out;
        case 'form':
            $out['valore'] = max(0, $num('valore'));
            $periodo();
            return $out;
        case 'lista_nera':
            if ($op === 'motivo') {
                $v = array_values(array_filter(is_array($c['valore'] ?? null) ? $c['valore'] : [$c['valore'] ?? ''], fn($m) => is_string($m) && isset(NL_MOTIVI[$m])));
                if (!$v) return 'scegli almeno un motivo.';
                $out['valore'] = array_values(array_unique($v));
            }
            return $out;
        case 'iscritto_da':
            $out['n'] = max(0, min(36500, $num('n')));
            return $out;
        case 'attivita':
            $out['n'] = max(1, min(36500, $num('n') ?: 30));
            if (!empty($c['tutti'])) $out['tutti'] = true;
            return $out;
        case 'flusso_email':
            $v = $num('valore');
            if ($v <= 0) return 'scegli l\'email dell\'automazione.';
            $out['valore'] = $v;
            return $out;
    }
    return $out; // stato, nessuna_lista: niente valori
}

// ------------------------------------------------------------
// Dalle regole all'SQL
// ------------------------------------------------------------

/**
 * La condizione SQL (sui contatti i) di regole già controllate. I valori vanno in $par, nell'ordine. Regole vuote = '1'
 * (tutti i contatti). $ctx: flusso (id), _visti (segmenti già aperti: niente giri), _livello.
 */
function newsletter_regole_where(array $regole, array &$par, array $ctx = []): string {
    $gruppi = [];
    foreach ($regole['gruppi'] ?? [] as $g) {
        $pezzi = [];
        foreach ($g['condizioni'] as $c) {
            $p = nl_seg_condizione_sql($c, $par, $ctx);
            $pezzi[] = !empty($c['non']) ? "NOT COALESCE(($p), 0)" : "($p)";
        }
        if ($pezzi) $gruppi[] = '(' . implode($g['op'] === 'o' ? ' OR ' : ' AND ', $pezzi) . ')';
    }
    if (!$gruppi) return '1';
    return '(' . implode(($regole['op'] ?? 'e') === 'o' ? ' OR ' : ' AND ', $gruppi) . ')';
}

/** Il periodo di una condizione su una colonna data e ora: " AND col >= NOW() - INTERVAL ? DAY", o niente. */
function nl_seg_periodo(array $c, string $col, array &$par): string {
    if (empty($c['n'])) return '';
    $par[] = (int)$c['n'];
    return " AND $col >= DATE_SUB(NOW(), INTERVAL ? " . (NL_SEG_UNITA[$c['unita'] ?? 'giorni'] ?? 'DAY') . ')';
}

/** L'ultima campagna partita (per "l'ultima campagna"), 0 se nessuna. */
function nl_seg_ultima_campagna(): int {
    static $u = null;
    return $u ??= (int)db()->query('SELECT id FROM ' . tbl('newsletter_campagne') . " WHERE stato IN ('in_invio', 'sospesa', 'inviata') ORDER BY COALESCE(invio_at, aggiornato) DESC, id DESC LIMIT 1")->fetchColumn();
}

/** Il filtro sulla campagna di un evento o di una riga della coda: 0 qualsiasi campagna, -1 l'ultima, -2 qualsiasi email. */
function nl_seg_campagna(int $c, string $col, array &$par): string {
    if ($c === -2) return '';
    if ($c === 0) return " AND $col > 0";
    $par[] = $c === -1 ? nl_seg_ultima_campagna() : $c;
    return " AND $col = ?";
}

/** I segnaposti di un elenco di valori: "?, ?, ?" (e i valori in $par). */
function nl_seg_in(array $v, array &$par): string {
    foreach ($v as $x) $par[] = $x;
    return implode(', ', array_fill(0, count($v), '?'));
}

function nl_seg_condizione_sql(array $c, array &$par, array $ctx): string {
    $op = $c['op'];
    $ev = tbl('newsletter_eventi'); $co = tbl('newsletter_coda'); $il = tbl('newsletter_iscritti_liste');
    if (str_starts_with($c['campo'], 'attr:')) return nl_seg_attributo_sql($c, $par);
    switch ($c['campo']) {
        case 'stato':
            return ['iscritto' => "i.stato = 'attivo'", 'non_iscritto' => "i.stato <> 'attivo'", 'in_attesa' => "i.stato = 'in_attesa'"][$op];
        case 'lista_nera':
            $nera = "i.stato IN ('" . implode("', '", NL_BLOCCATI) . "')";
            if ($op === 'si') return $nera;
            if ($op === 'no') return "NOT $nera";
            // Anche le righe di prima della 2.8.0, senza motivo, con lo stato del motivo (come il filtro della pagina).
            $stati = array_values(array_unique(array_map(fn($m) => NL_MOTIVI[$m][1], $c['valore'])));
            $a = nl_seg_in($c['valore'], $par);
            $b = nl_seg_in($stati, $par);
            return "$nera AND (i.blocco_motivo IN ($a) OR (i.blocco_motivo = '' AND i.stato IN ($b)))";
        case 'iscritto_da':
            // Come il bivio "è iscritto da almeno tanti giorni" dei flussi (newsletter_flusso_condizione della 2.9.0).
            $par[] = (int)$c['n'];
            return 'COALESCE(i.consenso_at, i.creato) ' . ($op === 'almeno' ? '<=' : '>') . ' DATE_SUB(NOW(), INTERVAL ? DAY)';
        case 'lista':
            $s = "EXISTS (SELECT 1 FROM $il l WHERE l.iscritto_id = i.id AND l.lista_id IN (" . nl_seg_in($c['valore'], $par) . '))';
            return $op === 'in' ? $s : "NOT $s";
        case 'nessuna_lista':
            $s = "EXISTS (SELECT 1 FROM $il l WHERE l.iscritto_id = i.id)";
            return $op === 'si' ? "NOT $s" : $s;
        case 'segmento':
            $sid = (int)$c['valore'];
            $visti = $ctx['_visti'] ?? [];
            $livello = (int)($ctx['_livello'] ?? 0);
            $seg = in_array($sid, $visti, true) || $livello >= NL_SEG_PROFONDITA ? null : newsletter_segmento($sid);
            $r = $seg ? newsletter_regole_valida($seg['regole_grezze'], ['segmento' => $sid]) : [];
            $dentro = isset($r['regole']) ? newsletter_regole_where($r['regole'], $par, ['_visti' => array_merge($visti, [$sid]), '_livello' => $livello + 1] + $ctx) : '0';
            return $op === 'in' ? $dentro : "NOT COALESCE($dentro, 0)";
        case 'email':
            $base = in_array($op, ['ricevuta', 'non_ricevuta'], true) ? 'ricevuta' : (str_contains($op, 'apert') ? 'apertura' : 'clic');
            $camp = (int)$c['campagna'];
            $ricevuta = function () use ($c, $camp, &$par, $co) {
                $s = "EXISTS (SELECT 1 FROM $co q WHERE q.iscritto_id = i.id AND q.stato = 'inviata'" . nl_seg_campagna($camp, 'q.campagna_id', $par) . nl_seg_periodo($c, 'q.inviata_at', $par) . ')';
                if ($camp === -2) {
                    $s2 = 'EXISTS (SELECT 1 FROM ' . tbl('newsletter_storia') . " h WHERE h.contatto_id = i.id AND h.tipo = 'flusso_email'" . nl_seg_periodo($c, 'h.creato', $par) . ')';
                    $s = "($s OR $s2)";
                }
                return $s;
            };
            if ($base === 'ricevuta') { $s = $ricevuta(); return $op === 'ricevuta' ? $s : "NOT $s"; }
            // Una campagna precisa (o l'ultima): "non aperta" = l'ha ricevuta e non l'ha aperta. Qualsiasi: nessuna apertura.
            // La parte "ricevuta" si scrive prima: i suoi parametri vengono prima nell'SQL.
            $prima = str_starts_with($op, 'non_') && ($camp > 0 || $camp === -1) ? $ricevuta() . ' AND ' : '';
            $par[] = $base;
            $s = "EXISTS (SELECT 1 FROM $ev e WHERE e.iscritto_id = i.id AND e.tipo = ?" . nl_seg_campagna($camp, 'e.campagna_id', $par) . nl_seg_periodo($c, 'e.creato', $par) . ')';
            return str_starts_with($op, 'non_') ? $prima . "NOT $s" : $s;
        case 'link':
            $par[] = $c['valore'];
            $s = "EXISTS (SELECT 1 FROM $ev e WHERE e.iscritto_id = i.id AND e.tipo = 'clic' AND e.url = ?" . nl_seg_campagna((int)$c['campagna'], 'e.campagna_id', $par) . ')';
            return $op === 'cliccato' ? $s : "NOT $s";
        case 'form':
            $w = '';
            if ((int)$c['valore'] > 0) { $par[] = (int)$c['valore']; $w = ' AND m.modulo_id = ?'; }
            $s = 'EXISTS (SELECT 1 FROM ' . tbl('moduli_invii') . " m WHERE m.email = i.email$w" . nl_seg_periodo($c, 'm.creato', $par) . ')';
            return $op === 'inviato' ? $s : "NOT $s";
        case 'attivita':
            // Come i bivi "attivo" e "inattivo" dei flussi: con tutti = qualunque evento (anche rimbalzi e uscite).
            $par[] = (int)$c['n'];
            $s = "EXISTS (SELECT 1 FROM $ev e WHERE e.iscritto_id = i.id" . (!empty($c['tutti']) ? '' : " AND e.tipo IN ('apertura', 'clic')") . ' AND e.creato > DATE_SUB(NOW(), INTERVAL ? DAY))';
            return $op === 'si' ? $s : "NOT $s";
        case 'flusso_email':
            $par[] = (int)($ctx['flusso'] ?? 0); $par[] = (int)$c['valore']; $par[] = str_contains($op, 'clicc') ? 'clic' : 'apertura';
            $s = "EXISTS (SELECT 1 FROM $ev e WHERE e.flusso_id = ? AND e.passo = ? AND e.iscritto_id = i.id AND e.tipo = ?)";
            return str_starts_with($op, 'non_') ? "NOT $s" : $s;
    }
    return '0';
}

/** La condizione su un attributo: di sistema sulla sua colonna (dall'elenco chiuso), gli altri su newsletter_valori. */
function nl_seg_attributo_sql(array $c, array &$par): string {
    $a = newsletter_attributi()[substr($c['campo'], 5)] ?? null;
    if (!$a) return '0';
    $op = $c['op'];
    if ((int)$a['sistema']) {
        $col = $a['colonna'];
        $tipo = NL_SEG_COLONNE[$col] ?? '';
        if ($tipo === '') return '0';
        if ($tipo === 'boolean') {
            $vero = $col === 'doi_at' ? 'i.doi_at IS NOT NULL' : "i.`$col` = 1";
            return $op === 'si' ? $vero : "NOT ($vero)";
        }
        $e = $tipo === 'date' ? "DATE(i.`$col`)" : "COALESCE(i.`$col`, '')";
        $pieno = $tipo === 'date' ? "i.`$col` IS NOT NULL" : "COALESCE(i.`$col`, '') <> ''";
        if ($op === 'vuoto') return "NOT ($pieno)";
        if ($op === 'pieno') return $pieno;
        return "$pieno AND " . nl_seg_confronto($tipo, $e, $c, $par);
    }
    $aid = (int)$a['id'];
    $t = tbl('newsletter_valori');
    $esiste = fn(string $w) => "EXISTS (SELECT 1 FROM $t v WHERE v.contatto_id = i.id AND v.attributo_id = $aid$w)";
    $tipo = $a['tipo'];
    if ($op === 'vuoto') return 'NOT ' . $esiste('');
    if ($op === 'pieno') return $esiste('');
    switch ($tipo) {
        case 'boolean':
            $s = $esiste(' AND v.v_num = 1');
            return $op === 'si' ? $s : "NOT $s";
        case 'category':
            $s = $esiste(' AND v.v_num IN (' . nl_seg_in($c['valore'], $par) . ')');
            return $op === 'uno_di' ? $s : "NOT $s";
        case 'multiple-choice':
            if ($op === 'tutte') {
                $pezzi = [];
                foreach ($c['valore'] as $o) { $par[] = $o; $pezzi[] = $esiste(' AND v.opzione = ?'); }
                return implode(' AND ', $pezzi);
            }
            $s = $esiste(' AND v.opzione IN (' . nl_seg_in($c['valore'], $par) . ')');
            return $op === 'almeno_una' ? $s : "NOT $s";
    }
    $col = ['text' => 'v.v_testo', 'float' => 'v.v_num', 'date' => 'v.v_data'][$tipo] ?? '';
    if ($col === '') return '0';
    // Le negazioni (non è, non contiene, è diverso) valgono anche per chi non ha il valore, come "non" davanti.
    if (in_array($op, ['diverso', 'non_contiene'], true)) {
        $pos = ['diverso' => 'uguale', 'non_contiene' => 'contiene'][$op];
        return 'NOT ' . $esiste(' AND ' . nl_seg_confronto($tipo, $col, ['op' => $pos] + $c, $par));
    }
    return $esiste(' AND ' . nl_seg_confronto($tipo, $col, $c, $par));
}

/** Il confronto di un valore ($e: un'espressione SQL dell'elenco chiuso) con il valore della condizione. */
function nl_seg_confronto(string $tipo, string $e, array $c, array &$par): string {
    $op = $c['op'];
    $like = fn($s) => addcslashes((string)$s, '%_\\');
    switch ($op) {
        case 'uguale': $par[] = $c['valore']; return "$e = ?";
        case 'diverso': $par[] = $c['valore']; return "$e <> ?";
        case 'contiene': $par[] = '%' . $like($c['valore']) . '%'; return "$e LIKE ? ESCAPE '\\\\'";
        case 'non_contiene': $par[] = '%' . $like($c['valore']) . '%'; return "$e NOT LIKE ? ESCAPE '\\\\'";
        case 'comincia': $par[] = $like($c['valore']) . '%'; return "$e LIKE ? ESCAPE '\\\\'";
        case 'finisce': $par[] = '%' . $like($c['valore']); return "$e LIKE ? ESCAPE '\\\\'";
        case 'maggiore': case 'dopo': $par[] = $c['valore']; return "$e > ?";
        case 'minore': case 'prima': $par[] = $c['valore']; return "$e < ?";
        case 'fra': $par[] = $c['valore']; $par[] = $c['valore2']; return "$e BETWEEN ? AND ?";
        case 'ultimi': case 'piu_di': case 'prossimi':
            $u = NL_SEG_UNITA[$c['unita'] ?? 'giorni'] ?? 'DAY';
            $par[] = (int)$c['n'];
            if ($op === 'ultimi') return "$e >= DATE_SUB(CURDATE(), INTERVAL ? $u) AND $e <= CURDATE()";
            if ($op === 'piu_di') return "$e < DATE_SUB(CURDATE(), INTERVAL ? $u)";
            return "$e >= CURDATE() AND $e <= DATE_ADD(CURDATE(), INTERVAL ? $u)";
        case 'mese_corrente':
            return "$e >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND $e < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)";
    }
    return '0';
}

// ------------------------------------------------------------
// Uso delle regole
// ------------------------------------------------------------

/** Quanti contatti soddisfano le regole, e in quanti millisecondi. */
function newsletter_regole_conta(array $regole, array $ctx = []): array {
    newsletter_segmenti_schema();
    $par = [];
    $w = newsletter_regole_where($regole, $par, $ctx);
    $t = microtime(true);
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i WHERE $w");
    $st->execute($par);
    return ['n' => (int)$st->fetchColumn(), 'ms' => (int)round((microtime(true) - $t) * 1000)];
}

/** Un contatto soddisfa le regole? (i bivi dei flussi) */
function newsletter_regole_vale(array $regole, int $contatto, array $ctx = []): bool {
    $par = [];
    $w = newsletter_regole_where($regole, $par, $ctx);
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i WHERE i.id = ? AND $w");
    $st->execute(array_merge([$contatto], $par));
    return (int)$st->fetchColumn() > 0;
}

/** Le regole di un bivio dei flussi: le nove condizioni di prima diventano condizioni del motore, con lo stesso esito. */
function newsletter_bivio_regole(string $cond, int $v): array {
    $c = match ($cond) {
        'in_lista' => ['campo' => 'lista', 'op' => 'in', 'valore' => [$v]],
        'non_in_lista' => ['campo' => 'lista', 'op' => 'non_in', 'valore' => [$v]],
        'aperta', 'non_aperta', 'cliccata', 'non_cliccata' => ['campo' => 'flusso_email', 'op' => $cond, 'valore' => $v],
        'attivo' => ['campo' => 'attivita', 'op' => 'si', 'n' => max(1, $v), 'tutti' => true],
        'inattivo' => ['campo' => 'attivita', 'op' => 'no', 'n' => max(1, $v), 'tutti' => true],
        'iscritto_da' => ['campo' => 'iscritto_da', 'op' => 'almeno', 'n' => max(0, $v)],
        'in_segmento' => ['campo' => 'segmento', 'op' => 'in', 'valore' => $v],
        'non_in_segmento' => ['campo' => 'segmento', 'op' => 'non_in', 'valore' => $v],
        default => null,
    };
    return ['op' => 'e', 'gruppi' => $c === null ? [] : [['op' => 'e', 'condizioni' => [$c]]]];
}

/** La condizione di un bivio vale per questo contatto? Le regole passano dal controllo come tutte le altre. */
function newsletter_bivio_vale(int $flusso, string $cond, int $v, int $contatto): bool {
    $r = newsletter_bivio_regole($cond, $v);
    if (!$r['gruppi']) return true; // condizione sconosciuta: vale, come prima
    // Lista, email o segmento non scelti (id 0): come prima, "sta in", "ha aperto" e "ha cliccato" no, le negative sì.
    if ($v <= 0 && in_array($r['gruppi'][0]['condizioni'][0]['campo'], ['lista', 'flusso_email', 'segmento'], true)) return str_starts_with($cond, 'non_');
    $ok = newsletter_regole_valida($r, ['flusso' => $flusso]);
    if (!isset($ok['regole'])) return false;
    return newsletter_regole_vale($ok['regole'], $contatto, ['flusso' => $flusso]);
}

/** Le regole in parole, per l'elenco dei segmenti e la pagina Contatti. */
function newsletter_regole_testo(array $regole): string {
    static $def = null, $campi = null;
    $campi ??= newsletter_regole_campi(true);
    $g = [];
    foreach ($regole['gruppi'] ?? [] as $gr) {
        $p = [];
        foreach ($gr['condizioni'] as $c) {
            [, $et, $tipo] = $campi[$c['campo']] ?? ['', $c['campo'], ''];
            $s = $et . ' ' . (NL_SEG_OPERATORI[$tipo][$c['op']] ?? $c['op']);
            $v = $c['valore'] ?? null;
            if ($v !== null) {
                $def ??= newsletter_regole_definizione();
                $nomi = match ($tipo) {
                    'lista' => $def['liste'], 'segmento' => $def['segmenti'], 'form' => $def['form'] + [0 => 'qualsiasi form'], 'lista_nera' => $def['motivi'],
                    default => $def['campi'][$c['campo']]['o'] ?? [],
                };
                $vv = array_map(fn($x) => $nomi[$x] ?? (is_float($x) ? newsletter_numero_testo($x) : (string)$x), is_array($v) ? $v : [$v]);
                $s .= ' ' . implode(', ', $vv) . (isset($c['valore2']) ? ' e ' . (is_float($c['valore2']) ? newsletter_numero_testo($c['valore2']) : $c['valore2']) : '');
            }
            if (isset($c['campagna'])) {
                $def ??= newsletter_regole_definizione();
                $s .= ' (' . ([0 => 'qualsiasi campagna', -1 => 'l\'ultima campagna', -2 => 'qualsiasi email'][(int)$c['campagna']] ?? 'campagna «' . ($def['campagne'][(int)$c['campagna']] ?? 'n. ' . (int)$c['campagna']) . '»') . ')';
            }
            if (!empty($c['n']) && $tipo !== 'date') $s .= in_array($tipo, ['iscritto_da', 'attivita'], true) ? ' ' . $c['n'] . ' giorni' : ', negli ultimi ' . $c['n'] . ' ' . ($c['unita'] ?? 'giorni');
            elseif (!empty($c['n'])) $s .= ' ' . $c['n'] . ' ' . ($c['unita'] ?? 'giorni');
            if (!empty($c['non'])) $s = 'NON (' . $s . ')';
            $p[] = $s;
        }
        $g[] = count($p) > 1 ? '(' . implode($gr['op'] === 'o' ? ' O ' : ' E ', $p) . ')' : ($p[0] ?? '');
    }
    return newsletter_4byte_fuori(implode(($regole['op'] ?? 'e') === 'o' ? ' O ' : ' E ', $g));
}

// ------------------------------------------------------------
// Segmenti salvati e categorie
// ------------------------------------------------------------

/** I segmenti, con il nome della categoria. */
function newsletter_segmenti(): array {
    newsletter_segmenti_schema();
    return db()->query('SELECT s.*, COALESCE(c.nome, \'\') AS cartella FROM ' . tbl('newsletter_segmenti') . ' s LEFT JOIN ' . tbl('newsletter_segmenti_cartelle') . ' c ON c.id = s.cartella_id ORDER BY s.nome')->fetchAll();
}

/** Un segmento, con le regole grezze (JSON letto) in regole_grezze. */
function newsletter_segmento(int $id): ?array {
    newsletter_segmenti_schema();
    static $cache = [];
    if (isset($cache[$id])) return $cache[$id];
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_segmenti') . ' WHERE id = ?');
    $st->execute([$id]);
    $s = $st->fetch();
    if (!$s) return null;
    $s['regole_grezze'] = json_decode((string)$s['condizioni'], true, 7) ?: ['op' => 'e', 'gruppi' => []];
    return $cache[$id] = $s;
}

/** Il segmento $dentro usa (anche attraverso altri segmenti) il segmento $cerca? */
function newsletter_segmento_contiene(int $dentro, int $cerca, int $livello = 0): bool {
    if ($livello > NL_SEG_PROFONDITA) return true;
    $s = newsletter_segmento($dentro);
    if (!$s) return false;
    foreach ($s['regole_grezze']['gruppi'] ?? [] as $g) foreach ((array)($g['condizioni'] ?? []) as $c) {
        if (is_array($c) && ($c['campo'] ?? '') === 'segmento') {
            $v = nl_seg_int($c['valore'] ?? 0);
            if ($v === $cerca || newsletter_segmento_contiene($v, $cerca, $livello + 1)) return true;
        }
    }
    return false;
}

/** La condizione SQL di un segmento salvato (per le pagine e l'esportazione). '0' se non c'è. */
function newsletter_segmento_where(int $id, array &$par): string {
    $s = newsletter_segmento($id);
    if (!$s) return '0';
    $r = newsletter_regole_valida($s['regole_grezze'], ['segmento' => $id]);
    return isset($r['regole']) ? newsletter_regole_where($r['regole'], $par, ['_visti' => [$id]]) : '0';
}

/**
 * Crea o salva un segmento. Ritorna l'id, oppure il messaggio d'errore. $cartella: id di una categoria, o 0; $nuova: il
 * nome di una categoria nuova (vince su $cartella).
 */
function newsletter_segmento_salva(int $id, string $nome, $regole, int $cartella = 0, string $nuova = '', int $utente = 0) {
    newsletter_segmenti_schema();
    $nome = newsletter_testo_pulito($nome, 100);
    if ($nome === '') return 'Scrivi il nome del segmento.';
    $r = newsletter_regole_valida($regole, ['segmento' => $id]);
    if (isset($r['errore'])) return $r['errore'];
    if (!$r['regole']['gruppi']) return 'Aggiungi almeno una condizione: un segmento senza condizioni sarebbe uguale a tutti i contatti.';
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_segmenti') . ' WHERE nome = ? AND id <> ?');
    $st->execute([$nome, $id]);
    if ($st->fetchColumn()) return 'Esiste già un segmento che si chiama così: scegli un altro nome.';
    if (!$id && (int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_segmenti'))->fetchColumn() >= NL_SEG_MASSIMO) return 'Hai già ' . NL_SEG_MASSIMO . ' segmenti: è il massimo. Eliminane uno che non usi più.';
    if (trim($nuova) !== '') {
        $c = newsletter_seg_cartella_crea($nuova);
        if (is_string($c)) return $c;
        $cartella = $c;
    } elseif ($cartella && !db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_segmenti_cartelle') . ' WHERE id = ' . (int)$cartella)->fetchColumn()) {
        $cartella = 0;
    }
    $json = json_encode($r['regole'], JSON_INVALID_UTF8_SUBSTITUTE); // fuori ASCII come \uXXXX: tabelle a 3 byte
    $conto = newsletter_regole_conta($r['regole'], ['_visti' => $id ? [$id] : []]);
    if ($id) {
        db()->prepare('UPDATE ' . tbl('newsletter_segmenti') . ' SET nome = ?, cartella_id = ?, condizioni = ?, conteggio = ?, contato_at = NOW(), aggiornato = NOW() WHERE id = ?')->execute([$nome, $cartella, $json, $conto['n'], $id]);
    } else {
        db()->prepare('INSERT INTO ' . tbl('newsletter_segmenti') . ' (nome, cartella_id, condizioni, conteggio, contato_at, utente_id, creato, aggiornato) VALUES (?, ?, ?, ?, NOW(), ?, NOW(), NOW())')->execute([$nome, $cartella, $json, $conto['n'], $utente]);
        $id = (int)db()->lastInsertId();
    }
    return $id;
}

/** Ricalcola il numero di contatti di un segmento e lo salva. */
function newsletter_segmento_conta(int $id): int {
    $par = [];
    $w = newsletter_segmento_where($id, $par);
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i WHERE $w");
    $st->execute($par);
    $n = (int)$st->fetchColumn();
    db()->prepare('UPDATE ' . tbl('newsletter_segmenti') . ' SET conteggio = ?, contato_at = NOW() WHERE id = ?')->execute([$n, $id]);
    return $n;
}

/** Chi usa un segmento: altri segmenti e flussi (bivi). Elenco di frasi; vuoto = si può eliminare. */
function newsletter_segmento_usato(int $id): array {
    $out = [];
    foreach (newsletter_segmenti() as $s) {
        if ((int)$s['id'] === $id) continue;
        foreach ((json_decode((string)$s['condizioni'], true, 7)['gruppi'] ?? []) as $g) foreach ((array)($g['condizioni'] ?? []) as $c) {
            if (($c['campo'] ?? '') === 'segmento' && nl_seg_int($c['valore'] ?? 0) === $id) { $out[] = 'il segmento «' . newsletter_4byte_fuori($s['nome']) . '»'; break 2; }
        }
    }
    // 2.15.0: anche le divisioni a più rami e le condizioni di uscita delle automazioni.
    $usa = function (array $r) use ($id) { foreach ($r['gruppi'] ?? [] as $g) foreach ((array)($g['condizioni'] ?? []) as $c) if (($c['campo'] ?? '') === 'segmento' && nl_seg_int($c['valore'] ?? 0) === $id) return true; return false; };
    foreach (newsletter_flussi() as $f) foreach ($f['grafo']['nodi'] as $n) {
        $si = ($n['tipo'] === 'bivio' && in_array($n['dati']['condizione'], ['in_segmento', 'non_in_segmento'], true) && (int)$n['dati']['valore'] === $id)
            || ($n['tipo'] === 'dividi' && array_filter($n['dati']['rami'], fn($r) => $usa($r['regole']))) || (int)($f['uscita']['segmento'] ?? 0) === $id;
        if ($si) { $out[] = 'l\'automazione «' . $f['nome'] . '»'; break; }
    }
    return array_values(array_unique($out));
}

/** Elimina un segmento. Ritorna '' oppure l'errore (un segmento usato da un altro o da un flusso non si elimina). */
function newsletter_segmento_elimina(int $id): string {
    $u = newsletter_segmento_usato($id);
    if ($u) return 'Questo segmento lo usa ' . implode(' e ', $u) . ': togli prima la condizione da lì, poi elimina il segmento.';
    db()->prepare('DELETE FROM ' . tbl('newsletter_segmenti') . ' WHERE id = ?')->execute([$id]);
    return '';
}

/**
 * Crea una lista con i contatti che oggi sono nel segmento (Brevo: una lista nuova da un segmento). La lista è ferma: chi
 * entra nel segmento dopo non entra nella lista. Ritorna ['id' => lista, 'n' => contatti] oppure ['errore' => ...].
 */
function newsletter_segmento_in_lista(int $id, string $nome, int $cartella): array {
    $s = newsletter_segmento($id);
    if (!$s) return ['errore' => 'Il segmento non c\'è più.'];
    $l = newsletter_lista_crea($nome, $cartella, 'Dal segmento «' . newsletter_4byte_fuori($s['nome']) . '», ' . date('d/m/Y'));
    if (!is_int($l)) return ['errore' => $l];
    $par = [];
    $w = newsletter_segmento_where($id, $par);
    $il = tbl('newsletter_iscritti_liste');
    $st = db()->prepare("INSERT IGNORE INTO $il (iscritto_id, lista_id, aggiunto) SELECT i.id, ?, NOW() FROM " . tbl('newsletter_iscritti') . " i WHERE $w");
    $st->execute(array_merge([$l], $par));
    $n = $st->rowCount();
    // La cronologia di ogni contatto, in un colpo solo.
    db()->prepare('INSERT INTO ' . tbl('newsletter_storia') . " (contatto_id, tipo, rif_id, dettaglio, utente_id, creato) SELECT iscritto_id, 'lista_aggiunto', lista_id, ?, ?, NOW() FROM $il WHERE lista_id = ?")
        ->execute([mb_substr(newsletter_nome_lista($l), 0, 255), session_status() === PHP_SESSION_ACTIVE ? (int)($_SESSION['admin_user_id'] ?? 0) : 0, $l]);
    return ['id' => $l, 'n' => $n];
}

/** Le categorie dei segmenti, con quanti segmenti ha ognuna. */
function newsletter_seg_cartelle(): array {
    newsletter_segmenti_schema();
    return db()->query('SELECT c.*, (SELECT COUNT(*) FROM ' . tbl('newsletter_segmenti') . ' s WHERE s.cartella_id = c.id) AS segmenti FROM ' . tbl('newsletter_segmenti_cartelle') . ' c ORDER BY c.nome')->fetchAll();
}

/** Crea una categoria di segmenti. Ritorna l'id oppure il messaggio d'errore. */
function newsletter_seg_cartella_crea(string $nome) {
    newsletter_segmenti_schema();
    $nome = newsletter_testo_pulito($nome, 100);
    if ($nome === '') return 'Scrivi il nome della categoria.';
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_segmenti_cartelle') . ' WHERE nome = ?');
    $st->execute([$nome]);
    if ($g = (int)$st->fetchColumn()) return $g;
    if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('newsletter_segmenti_cartelle'))->fetchColumn() >= NL_SEG_CARTELLE_MASSIMO) return 'Hai già ' . NL_SEG_CARTELLE_MASSIMO . ' categorie: è il massimo.';
    db()->prepare('INSERT INTO ' . tbl('newsletter_segmenti_cartelle') . ' (nome, creato) VALUES (?, NOW())')->execute([$nome]);
    return (int)db()->lastInsertId();
}

/** Elimina una categoria: i suoi segmenti restano, senza categoria. */
function newsletter_seg_cartella_elimina(int $id): void {
    db()->prepare('UPDATE ' . tbl('newsletter_segmenti') . ' SET cartella_id = 0 WHERE cartella_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_segmenti_cartelle') . ' WHERE id = ?')->execute([$id]);
}

/**
 * I modelli pronti (Brevo: Segment templates; l'elenco completo di Brevo non si è trovato, capitolo 5 del piano): chiave
 * => [nome, descrizione, regole].
 */
function newsletter_segmenti_modelli(): array {
    $g = fn(array ...$c) => ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => $c]]];
    $iscr = ['campo' => 'stato', 'op' => 'iscritto'];
    return [
        'non_coinvolti' => ['Contatti non coinvolti', 'Iscritti che non hanno aperto né cliccato una campagna negli ultimi 30 giorni.',
            $g($iscr, ['campo' => 'email', 'op' => 'non_aperta', 'campagna' => 0, 'n' => 30, 'unita' => 'giorni'], ['campo' => 'email', 'op' => 'non_cliccata', 'campagna' => 0, 'n' => 30, 'unita' => 'giorni'])],
        'non_aprono_90' => ['Chi non apre da 90 giorni', 'Iscritti che non hanno aperto nessuna campagna negli ultimi 90 giorni.',
            $g($iscr, ['campo' => 'email', 'op' => 'non_aperta', 'campagna' => 0, 'n' => 90, 'unita' => 'giorni'])],
        'coinvolti' => ['Contatti coinvolti', 'Iscritti che hanno aperto o cliccato una campagna negli ultimi 30 giorni.',
            ['op' => 'e', 'gruppi' => [['op' => 'e', 'condizioni' => [$iscr]], ['op' => 'o', 'condizioni' => [['campo' => 'email', 'op' => 'aperta', 'campagna' => 0, 'n' => 30, 'unita' => 'giorni'], ['campo' => 'email', 'op' => 'cliccata', 'campagna' => 0, 'n' => 30, 'unita' => 'giorni']]]]]],
        'nuovi_mese' => ['Nuovi iscritti di questo mese', 'Iscritti con la data di creazione in questo mese.',
            $g($iscr, ['campo' => 'attr:CREATION_DATE', 'op' => 'mese_corrente'])],
        'cliccato_ultima' => ['Chi ha cliccato l\'ultima campagna', 'Contatti che hanno cliccato un link dell\'ultima campagna partita.',
            $g(['campo' => 'email', 'op' => 'cliccata', 'campagna' => -1])],
        'aperto_ultima' => ['Chi ha aperto l\'ultima campagna', 'Contatti che hanno aperto l\'ultima campagna partita.',
            $g(['campo' => 'email', 'op' => 'aperta', 'campagna' => -1])],
        'non_aperto_ultima' => ['Chi non ha aperto l\'ultima campagna', 'Contatti che hanno ricevuto l\'ultima campagna e non l\'hanno aperta.',
            $g(['campo' => 'email', 'op' => 'non_aperta', 'campagna' => -1])],
        'form_30' => ['Hanno compilato un form da poco', 'Contatti che hanno inviato un form negli ultimi 30 giorni.',
            $g(['campo' => 'form', 'op' => 'inviato', 'valore' => 0, 'n' => 30, 'unita' => 'giorni'])],
        'nessuna_lista' => ['In nessuna lista', 'Contatti che non stanno in nessuna lista.', $g(['campo' => 'nessuna_lista', 'op' => 'si'])],
        'lista_nera' => ['In lista nera', 'Contatti in lista nera, per qualunque motivo.', $g(['campo' => 'lista_nera', 'op' => 'si'])],
    ];
}

// ------------------------------------------------------------
// Viste della pagina Contatti
// ------------------------------------------------------------

/** Le viste salvate di un utente. */
function newsletter_viste(int $utente): array {
    newsletter_segmenti_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_viste') . ' WHERE utente_id = ? ORDER BY nome');
    $st->execute([$utente]);
    $out = [];
    foreach ($st->fetchAll() as $v) {
        $v['filtro'] = json_decode((string)$v['filtro'], true) ?: [];
        $v['colonne'] = json_decode((string)$v['colonne'], true) ?: [];
        $out[(int)$v['id']] = $v;
    }
    return $out;
}

/**
 * Salva una vista: il nome, i filtri della pagina (già normalizzati da newsletter_contatti_filtro) e le colonne.
 * Ritorna l'id oppure il messaggio d'errore. Una vista con lo stesso nome si sovrascrive.
 */
function newsletter_vista_salva(int $utente, string $nome, array $filtro, array $colonne, int $per = 50) {
    newsletter_segmenti_schema();
    $nome = newsletter_testo_pulito($nome, 100);
    if ($nome === '') return 'Scrivi il nome della vista.';
    if (in_array(mb_strtolower($nome), ['tutti i contatti'], true)) return '«Tutti i contatti» c\'è già e non si cambia: scegli un altro nome.';
    $attr = newsletter_attributi();
    $colonne = array_values(array_slice(array_filter($colonne, fn($n) => is_string($n) && isset($attr[$n]) && $attr[$n]['categoria'] !== 'global'), 0, 30));
    $f = array_filter(['cerca' => (string)($filtro['cerca'] ?? ''), 'stato' => (string)($filtro['stato'] ?? ''), 'lista' => (int)($filtro['lista'] ?? 0), 'segmento' => (int)($filtro['segmento'] ?? 0), 'f' => (string)($filtro['f'] ?? '')]);
    if (in_array($per, [100, 500], true)) $f['per'] = $per;
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_viste') . ' WHERE utente_id = ? AND nome = ?');
    $st->execute([$utente, $nome]);
    if ($g = (int)$st->fetchColumn()) {
        db()->prepare('UPDATE ' . tbl('newsletter_viste') . ' SET filtro = ?, colonne = ? WHERE id = ?')->execute([json_encode($f), json_encode($colonne), $g]);
        return $g;
    }
    if (count(newsletter_viste($utente)) >= NL_VISTE_MASSIMO) return 'Hai già ' . NL_VISTE_MASSIMO . ' viste: è il massimo. Eliminane una.';
    db()->prepare('INSERT INTO ' . tbl('newsletter_viste') . ' (utente_id, nome, filtro, colonne, creato) VALUES (?, ?, ?, ?, NOW())')->execute([$utente, $nome, json_encode($f), json_encode($colonne)]);
    return (int)db()->lastInsertId();
}

function newsletter_vista_elimina(int $utente, int $id): void {
    db()->prepare('DELETE FROM ' . tbl('newsletter_viste') . ' WHERE id = ? AND utente_id = ?')->execute([$id, $utente]);
}
