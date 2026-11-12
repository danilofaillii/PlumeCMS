<?php
/**
 * Il motore dell'addon Test e questionari (2.22.0): tabelle, lettura e salvataggio dei test, calcolo del risultato,
 * firma del link condivisibile, simulatore, statistiche anonime.
 *
 * Tre tipi di test:
 *   punteggio  somma dei punti delle risposte, in percentuale, e fasce (basso, medio, alto...);
 *   aree       ogni domanda appartiene a un'area: percentuale e fasce per area, più il totale con le sue fasce;
 *   profili    "che tipo sei": ogni risposta dà punti a uno o più profili, vince quello con più punti (parità: l'ordine
 *              dei profili, che è la priorità scelta da chi scrive il test).
 *
 * Il calcolo si fa sempre qui, sul server (test_calcola()): il browser manda solo le risposte, e il server ricalcola le
 * domande condizionali mostrate, i punti, le percentuali, le fasce e le regole. Le domande non mostrate o lasciate vuote
 * non contano: la percentuale è (punti - minimo) / (massimo - minimo) delle sole domande mostrate e risposte (con il
 * minimo a zero, come nelle scale di partenza, è punti ottenuti / punti massimi).
 *
 * Privacy (scelta di Danilo): niente IP in chiaro, niente legame fra risposte e persone. Si conservano l'esito e i
 * punteggi con il solo giorno (test_risultati), i conteggi delle risposte per domanda (test_risposte, solo numeri) e i
 * tentativi con un codice casuale fatto dal browser (test_tentativi: fin dove è arrivato, per gli abbandoni). L'email
 * entra nella scheda del contatto solo se la persona si iscrive alla lista scelta, con il consenso.
 *
 * Il formato del campo dati (JSON) è descritto in docs-sviluppo/addon/test.md.
 */
require_once dirname(__DIR__, 3) . '/includes/moduli.php';
require_once __DIR__ . '/catalogo-persona.php'; // 2.23.0: le domande sulla persona

const TEST_TIPI = ['punteggio' => 'A punteggio', 'aree' => 'Ad aree', 'profili' => 'Che tipo sei (profili)'];
const TEST_TIPI_SPIEGA = [
    'punteggio' => 'Ogni risposta vale dei punti: il totale, in percentuale, cade in una fascia (per esempio basso, medio, alto) con il suo testo.',
    'aree' => 'Le domande sono divise in aree (per esempio Energia, Sonno, Relazioni): ogni area ha la sua percentuale e le sue fasce, e c\'è anche il totale. Il risultato ha il grafico a barre delle aree.',
    'profili' => 'Ogni risposta dà punti a uno o più profili. Vince il profilo con più punti: "Sei un Esploratore". In parità vince quello più in alto nell\'elenco dei profili.',
];
const TEST_DOMANDE_TIPI = ['scala' => 'Scala (Mai... Sempre)', 'scelta' => 'Scelta singola', 'sino' => 'Sì o no', 'cursore' => 'Cursore da 0 a 10'];
const TEST_SCALA_PARTENZA = [['t' => 'Mai', 'v' => 0], ['t' => 'Raramente', 'v' => 1], ['t' => 'A volte', 'v' => 2], ['t' => 'Spesso', 'v' => 3], ['t' => 'Sempre', 'v' => 4]];
const TEST_AVVERTENZA = 'Questo test non è una diagnosi e non sostituisce il parere di un professionista.';
const TEST_CONSENSO = 'Acconsento a ricevere il risultato del test a questo indirizzo email. So che il risultato può riguardare la mia salute (un dato particolare) e che posso chiedere di cancellarlo quando voglio.';
const TEST_ISCRIZIONE = 'Voglio ricevere anche la newsletter (ti mandiamo un\'email per confermare).';
const TEST_MAX_DOMANDE = 80;
const TEST_MAX_OPZIONI = 12;
const TEST_MAX_AREE = 12;
const TEST_MAX_PROFILI = 12;
const TEST_MAX_FASCE = 8;
const TEST_MAX_REGOLE = 20;
const TEST_SECONDI_DOMANDA = 12;          // per "circa M minuti"
const TEST_SECONDI_MINIMI = 3;            // un invio arrivato prima di 3 secondi dall'apertura della pagina è di un robot
const TEST_LIMITE_ORA = 20;               // invii del risultato per connessione in un'ora
const TEST_LIMITE_EMAIL_ORA = 5;          // email del risultato per connessione in un'ora
const TEST_LIMITE_INIZI_ORA = 120;        // tentativi nuovi per connessione in un'ora (contatore leggero)
const TEST_EMAIL_ORE = 2;                 // per quante ore, dopo il test, si può chiedere il risultato per email
// 2.23.0: le domande sulla persona, i due testi dei risultati, il risultato completo per email.
const TEST_PERSONA_NR = 'nr';             // la risposta "Preferisco non rispondere" delle domande facoltative (non diventa un tag)
const TEST_PERSONA_NR_TESTO = 'Preferisco non rispondere';
const TEST_PERSONA_MAX_OPZIONI = 80;
const TEST_GRUPPO_MINIMO = 5;             // statistiche per gruppi: sotto questo numero non si mostra niente ("meno di 5")
const TEST_TITOLO_EMAIL = 'Lasciami la tua email e ti mando il risultato completo';
// 2.24.0: come si mostrano le domande. 'una' è il comportamento di sempre (con JavaScript una domanda per schermata) ed è il
// modo dei test che non lo dicono (quelli scritti prima della 2.24.0): la loro pagina resta uguale al byte.
const TEST_MODI = ['una' => 'Una per pagina', 'pagine' => 'A pagine, le scegli tu', 'tutte' => 'Tutte in una pagina'];
const TEST_MAX_PAGINE = 80;               // quante pagine al massimo (una per domanda, con le domande al massimo)
const TEST_MAX_TITOLO_PAGINA = 120;
/**
 * L'indirizzo delle pagine dei test. Non /test/: il .htaccess di ogni installazione (e gli esempi per Apache e Nginx)
 * vieta la cartella test/ nella radice del CMS (RewriteRule ^(...|test|test-definizioni)(/|$) - [F,L]), quindi /test/<nome>/
 * darebbe 403 su ogni sito. Cambiarlo vorrebbe dire riscrivere il .htaccess dei siti già installati.
 */
const TEST_PERCORSO = '/fai-il-test/';

// ------------------------------------------------------------
// Tabelle
// ------------------------------------------------------------

function test_tabelle(): array {
    return [
        'test_test' => 'CREATE TABLE IF NOT EXISTS ' . tbl('test_test') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(120) NOT NULL,
            `nome` VARCHAR(200) NOT NULL,
            `tipo` VARCHAR(12) NOT NULL DEFAULT 'punteggio',
            `stato` VARCHAR(12) NOT NULL DEFAULT 'bozza',
            `durata` INT NOT NULL DEFAULT 0,
            `dati` MEDIUMTEXT NOT NULL,
            `struttura` MEDIUMTEXT NULL,
            `struttura_bozza` MEDIUMTEXT NULL,
            `struttura_aggiornata` DATETIME NULL DEFAULT NULL,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Un risultato: solo l'esito e i punteggi, con il giorno (niente ora, niente IP, niente email).
        'test_risultati' => 'CREATE TABLE IF NOT EXISTS ' . tbl('test_risultati') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `test_id` INT NOT NULL,
            `giorno` DATE NOT NULL,
            `totale` SMALLINT NULL DEFAULT NULL,
            `fascia` VARCHAR(20) NOT NULL DEFAULT '',
            `profilo` VARCHAR(20) NOT NULL DEFAULT '',
            `regola` VARCHAR(20) NOT NULL DEFAULT '',
            `aree` TEXT NULL,
            `persona` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `test` (`test_id`, `giorno`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Quante volte ogni risposta è stata data, per domanda: solo contatori.
        'test_risposte' => 'CREATE TABLE IF NOT EXISTS ' . tbl('test_risposte') . " (
            `test_id` INT NOT NULL,
            `domanda` VARCHAR(20) NOT NULL,
            `valore` VARCHAR(20) NOT NULL,
            `n` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`test_id`, `domanda`, `valore`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Un tentativo: codice casuale fatto dal browser, fin dove è arrivato, se ha finito. Per iniziati e abbandoni.
        'test_tentativi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('test_tentativi') . " (
            `codice` CHAR(16) NOT NULL,
            `test_id` INT NOT NULL,
            `giorno` DATE NOT NULL,
            `ultima` VARCHAR(20) NOT NULL DEFAULT '',
            `passi` SMALLINT NOT NULL DEFAULT 0,
            `completato` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`codice`),
            KEY `test` (`test_id`, `giorno`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Il limite per connessione: impronta dell'IP con un sale che cambia ogni giorno, cancellata dopo un giorno.
        'test_limiti' => 'CREATE TABLE IF NOT EXISTS ' . tbl('test_limiti') . " (
            `id` INT NOT NULL AUTO_INCREMENT,
            `impronta` CHAR(64) NOT NULL,
            `chiave` VARCHAR(10) NOT NULL,
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `chi` (`impronta`, `chiave`, `creato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // L'innesco "ha completato il test" di chi deve ancora confermare l'iscrizione: parte alla conferma.
        'test_attese' => 'CREATE TABLE IF NOT EXISTS ' . tbl('test_attese') . " (
            `iscritto_id` INT NOT NULL,
            `test_id` INT NOT NULL,
            `esiti` VARCHAR(1000) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`iscritto_id`, `test_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

function test_installa(): string {
    foreach (test_tabelle() as $sql) db()->exec($sql);
    // 2.23.0: le risposte sulla persona con l'esito (anonime come il resto della riga). Le tabelle della 2.22.0 non hanno la colonna.
    $col = db()->query('SHOW COLUMNS FROM ' . tbl('test_risultati'))->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('persona', $col, true)) db()->exec('ALTER TABLE ' . tbl('test_risultati') . ' ADD COLUMN `persona` TEXT NULL AFTER `aree`');
    save_setting('test_schema_impronta', md5(serialize(test_tabelle()) . DB_NAME));
    return 'tabelle a posto';
}

function test_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    if (get_setting('test_schema_impronta', '') !== md5(serialize(test_tabelle()) . DB_NAME)) test_installa();
    $fatto = true;
}

// ------------------------------------------------------------
// Testi
// ------------------------------------------------------------

/** Un testo che arriva dal web o dal pannello: UTF-8 valido, senza caratteri di controllo, tagliato. */
function test_testo(mixed $s, int $max, bool $righe = false): string {
    $s = is_scalar($s) ? mb_scrub((string)$s, 'UTF-8') : '';
    $s = (string)preg_replace($righe ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $righe ? '' : ' ', $s);
    return mb_substr(trim($s), 0, $max);
}

/** Il testo ricco di un risultato: le regole del widget Testo (sicurezza_html_pulito, profilo 'ricco'). */
function test_ricco(mixed $html): string {
    require_once dirname(__DIR__, 3) . '/includes/sicurezza-html.php';
    $html = test_testo($html, 20000, true);
    return $html === '' ? '' : trim(sicurezza_html_pulito($html, 'ricco'));
}

/**
 * Un link di un pulsante: le regole dei widget (editor_link_sicuro: pagina del sito, https, mailto, tel, ancora; i link
 * scritti a mano come "contatti" o "www.sito.it" diventano /contatti e https://www.sito.it; javascript: e simili no).
 */
function test_link(mixed $u): string {
    $u = test_testo($u, 500);
    if ($u === '') return '';
    if (!function_exists('editor_link_sicuro')) require_once dirname(__DIR__, 3) . '/includes/editor.php';
    return editor_link_sicuro($u);
}

function test_slug(string $s): string {
    $s = mb_strtolower($s);
    $s = strtr($s, ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '&' => ' e ']);
    $s = trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return substr($s, 0, 100) ?: 'test';
}

/** Una chiave interna (domanda, area, fascia...): una lettera e fino a 15 lettere minuscole o cifre. */
function test_chiave(mixed $k, string $prefisso = 'k'): string {
    $k = is_string($k) ? $k : '';
    return preg_match('/^[a-z][a-z0-9]{1,15}$/', $k) ? $k : $prefisso . bin2hex(random_bytes(4));
}

// ------------------------------------------------------------
// Lettura e salvataggio
// ------------------------------------------------------------

/** I dati di partenza di un test nuovo di un tipo (senza domande: le scrive l'autore, o vengono da un modello). */
function test_dati_vuoti(string $tipo): array {
    return [
        'scala' => TEST_SCALA_PARTENZA, 'aree' => [], 'profili' => [], 'domande' => [],
        'fasce' => $tipo === 'profili' ? [] : [
            ['k' => 'basso', 'nome' => 'Basso', 'da' => 0, 'a' => 33, 'breve' => '', 'testo' => '', 'pulsante_testo' => '', 'pulsante_link' => ''],
            ['k' => 'medio', 'nome' => 'Medio', 'da' => 34, 'a' => 66, 'breve' => '', 'testo' => '', 'pulsante_testo' => '', 'pulsante_link' => ''],
            ['k' => 'alto', 'nome' => 'Alto', 'da' => 67, 'a' => 100, 'breve' => '', 'testo' => '', 'pulsante_testo' => '', 'pulsante_link' => ''],
        ],
        'regole' => [], 'avvertenza' => TEST_AVVERTENZA, 'casuale' => false, 'presentazione' => '',
        'dopo' => ['email' => false, 'consenso' => TEST_CONSENSO, 'lista' => 0, 'iscrizione' => TEST_ISCRIZIONE, 'solo_email' => true],
        'persona' => ['obbligatorie' => false, 'domande' => []],
        'modo' => 'una', 'pagine' => [],
    ];
}

/** Le fasce pulite (al massimo TEST_MAX_FASCE), in ordine di "da". */
function test_fasce_pulite(mixed $in): array {
    $out = []; $visti = [];
    foreach (is_array($in) ? array_slice($in, 0, TEST_MAX_FASCE) : [] as $f) {
        if (!is_array($f)) continue;
        $k = test_chiave($f['k'] ?? '', 'f');
        if (isset($visti[$k])) $k = test_chiave('', 'f');
        $visti[$k] = true;
        $out[] = ['k' => $k, 'nome' => test_testo($f['nome'] ?? '', 60) ?: 'Fascia', 'da' => max(0, min(100, (int)($f['da'] ?? 0))), 'a' => max(0, min(100, (int)($f['a'] ?? 100))),
            'breve' => test_ricco($f['breve'] ?? ''), 'testo' => test_ricco($f['testo'] ?? ''), 'pulsante_testo' => test_testo($f['pulsante_testo'] ?? '', 80), 'pulsante_link' => test_link($f['pulsante_link'] ?? '')];
    }
    usort($out, fn($x, $y) => $x['da'] <=> $y['da'] ?: $x['a'] <=> $y['a']);
    return $out;
}

/**
 * I dati di un test controllati, nella forma che usa tutto il resto: ogni chiave c'è, i numeri sono numeri, le chiavi
 * interne sono chiavi, i rimandi (area, condizione, profili, regole) puntano a cose che esistono. Vale per quello che
 * arriva dal pannello e per quello che sta nel database.
 */
function test_normalizza(mixed $d, string $tipo): array {
    $d = is_array($d) ? $d : [];
    $out = test_dati_vuoti($tipo);
    // Scala: da 2 a 7 gradini, etichetta e valore.
    $scala = [];
    foreach (is_array($d['scala'] ?? null) ? array_slice($d['scala'], 0, 7) : [] as $s) {
        if (!is_array($s)) continue;
        $t = test_testo($s['t'] ?? '', 40);
        if ($t === '') continue;
        $scala[] = ['t' => $t, 'v' => max(-100, min(100, (int)($s['v'] ?? count($scala))))];
    }
    $out['scala'] = count($scala) >= 2 ? $scala : TEST_SCALA_PARTENZA;
    // Aree e profili.
    $visti = [];
    foreach (['aree' => TEST_MAX_AREE, 'profili' => TEST_MAX_PROFILI] as $chi => $max) {
        $lista = [];
        foreach (is_array($d[$chi] ?? null) ? array_slice($d[$chi], 0, $max) : [] as $a) {
            if (!is_array($a)) continue;
            $k = test_chiave($a['k'] ?? '', $chi === 'aree' ? 'a' : 'p');
            if (isset($visti[$k])) $k = test_chiave('', $chi === 'aree' ? 'a' : 'p');
            $visti[$k] = true;
            $x = ['k' => $k, 'nome' => test_testo($a['nome'] ?? '', 60) ?: ($chi === 'aree' ? 'Area' : 'Profilo')];
            if ($chi === 'aree') $x['fasce'] = test_fasce_pulite($a['fasce'] ?? []) ?: test_dati_vuoti('punteggio')['fasce'];
            else $x += ['breve' => test_ricco($a['breve'] ?? ''), 'testo' => test_ricco($a['testo'] ?? ''), 'pulsante_testo' => test_testo($a['pulsante_testo'] ?? '', 80), 'pulsante_link' => test_link($a['pulsante_link'] ?? '')];
            $lista[] = $x;
        }
        $out[$chi] = $lista;
    }
    if ($tipo !== 'aree') $out['aree'] = [];
    if ($tipo !== 'profili') $out['profili'] = [];
    $aree = array_column($out['aree'], null, 'k');
    $profili = array_column($out['profili'], null, 'k');
    // Domande.
    $domande = []; $chiavi = [];
    foreach (is_array($d['domande'] ?? null) ? array_slice($d['domande'], 0, TEST_MAX_DOMANDE) : [] as $q) {
        if (!is_array($q)) continue;
        $t = test_testo($q['testo'] ?? '', 400);
        if ($t === '') continue;
        $tipoQ = isset(TEST_DOMANDE_TIPI[$q['tipo'] ?? '']) ? (string)$q['tipo'] : 'scala';
        if ($tipo === 'profili' && !in_array($tipoQ, ['scelta', 'sino'], true)) $tipoQ = 'scelta';
        $k = test_chiave($q['k'] ?? '', 'd');
        if (isset($chiavi[$k]) || isset($visti[$k])) $k = test_chiave('', 'd');
        $x = ['k' => $k, 'testo' => $t, 'aiuto' => test_testo($q['aiuto'] ?? '', 300), 'tipo' => $tipoQ, 'invertita' => !empty($q['invertita']) && $tipo !== 'profili',
            'peso' => round(max(0, min(10, (float)str_replace(',', '.', (string)($q['peso'] ?? 1)))) * 2) / 2, 'obbligatoria' => !array_key_exists('obbligatoria', $q) || !empty($q['obbligatoria']),
            'area' => '', 'opzioni' => [], 'condizione' => null];
        if ($tipo === 'aree') $x['area'] = isset($aree[$q['area'] ?? '']) ? (string)$q['area'] : ($out['aree'][0]['k'] ?? '');
        if ($tipoQ === 'scelta' || $tipoQ === 'sino') {
            $ops = []; $ko = [];
            $grezze = is_array($q['opzioni'] ?? null) ? array_slice($q['opzioni'], 0, TEST_MAX_OPZIONI) : [];
            if ($tipoQ === 'sino') {
                $per = [];
                foreach ($grezze as $o) if (is_array($o) && in_array($o['k'] ?? '', ['si', 'no'], true)) $per[$o['k']] = $o;
                $grezze = [['k' => 'si', 't' => 'Sì'] + ($per['si'] ?? ['punti' => 1]), ['k' => 'no', 't' => 'No'] + ($per['no'] ?? ['punti' => 0])];
                $grezze[0]['t'] = 'Sì'; $grezze[1]['t'] = 'No';
            }
            foreach ($grezze as $o) {
                if (!is_array($o)) continue;
                $ot = test_testo($o['t'] ?? '', 200);
                if ($ot === '') continue;
                $okk = $tipoQ === 'sino' ? (string)$o['k'] : test_chiave($o['k'] ?? '', 'o');
                if (isset($ko[$okk])) $okk = test_chiave('', 'o');
                $ko[$okk] = true;
                $op = ['k' => $okk, 't' => $ot, 'punti' => max(-100, min(100, (int)($o['punti'] ?? 0)))];
                if ($tipo === 'profili') {
                    $pp = [];
                    foreach (is_array($o['profili'] ?? null) ? $o['profili'] : [] as $pk => $n) if (isset($profili[$pk]) && (int)$n !== 0) $pp[$pk] = max(-20, min(20, (int)$n));
                    $op['profili'] = $pp;
                    $op['punti'] = 0;
                }
                $ops[] = $op;
            }
            if (count($ops) < 2) continue; // una scelta con meno di due risposte non è una domanda
            $x['opzioni'] = $ops;
        }
        // Condizione: una domanda che viene prima, non un cursore, e almeno una sua risposta.
        $c = $q['condizione'] ?? null;
        if (is_array($c) && isset($chiavi[$c['domanda'] ?? ''])) {
            $padre = $domande[$chiavi[$c['domanda']]];
            $valide = array_column(test_opzioni($padre, $out), 'k');
            $val = array_values(array_intersect($valide, array_map('strval', is_array($c['valori'] ?? null) ? $c['valori'] : [])));
            if ($padre['tipo'] !== 'cursore' && $val) $x['condizione'] = ['domanda' => $padre['k'], 'valori' => $val];
        }
        $chiavi[$k] = count($domande);
        $domande[] = $x;
    }
    // 2.24.0: il modo e le pagine. L'ordine delle pagine è l'ordine delle domande: se le pagine mettono le domande in un
    // altro ordine (dal pannello non succede: la lavagna le tiene allineate) si riordinano e si ricontrollano le condizioni.
    $out['modo'] = isset(TEST_MODI[$d['modo'] ?? '']) ? (string)$d['modo'] : 'una';
    [$out['pagine'], $domande] = test_pagine_normalizza($d['pagine'] ?? null, $domande);
    $out['domande'] = $domande;
    // Fasce del totale (non nei profili).
    $out['fasce'] = $tipo === 'profili' ? [] : (array_key_exists('fasce', $d) ? test_fasce_pulite($d['fasce']) : $out['fasce']);
    // Regole sul risultato (non nei profili): condizioni su totale o aree, con E/O, in ordine di priorità.
    $regole = [];
    if ($tipo !== 'profili') {
        foreach (is_array($d['regole'] ?? null) ? array_slice($d['regole'], 0, TEST_MAX_REGOLE) : [] as $r) {
            if (!is_array($r)) continue;
            $cond = [];
            foreach (is_array($r['condizioni'] ?? null) ? array_slice($r['condizioni'], 0, 12) : [] as $c) {
                if (!is_array($c)) continue;
                $cosa = (string)($c['cosa'] ?? '');
                $fasce = $cosa === 'totale' ? $out['fasce'] : (str_starts_with($cosa, 'area:') && isset($aree[substr($cosa, 5)]) ? $aree[substr($cosa, 5)]['fasce'] : null);
                if ($fasce === null || !in_array((string)($c['fascia'] ?? ''), array_column($fasce, 'k'), true)) continue;
                $cond[] = ['cosa' => $cosa, 'fascia' => (string)$c['fascia']];
            }
            if (!$cond) continue;
            $regole[] = ['k' => test_chiave($r['k'] ?? '', 'r'), 'nome' => test_testo($r['nome'] ?? '', 80) ?: 'Regola ' . (count($regole) + 1), 'modo' => ($r['modo'] ?? 'e') === 'o' ? 'o' : 'e',
                'condizioni' => $cond, 'breve' => test_ricco($r['breve'] ?? ''), 'testo' => test_ricco($r['testo'] ?? ''), 'pulsante_testo' => test_testo($r['pulsante_testo'] ?? '', 80), 'pulsante_link' => test_link($r['pulsante_link'] ?? '')];
        }
    }
    $out['regole'] = $regole;
    $out['avvertenza'] = array_key_exists('avvertenza', $d) ? test_testo($d['avvertenza'], 600, true) : TEST_AVVERTENZA;
    $out['casuale'] = !empty($d['casuale']);
    $out['presentazione'] = test_testo($d['presentazione'] ?? '', 2000, true);
    $dp = is_array($d['dopo'] ?? null) ? $d['dopo'] : [];
    $out['dopo'] = ['email' => !empty($dp['email']), 'consenso' => test_testo($dp['consenso'] ?? '', 600) ?: TEST_CONSENSO, 'lista' => max(0, (int)($dp['lista'] ?? 0)),
        'iscrizione' => test_testo($dp['iscrizione'] ?? '', 300) ?: TEST_ISCRIZIONE, 'solo_email' => !empty($dp['solo_email'])];
    $out['persona'] = test_persona_normalizza($d['persona'] ?? null);
    return $out;
}

/**
 * 2.24.0: le pagine di un test, controllate: [{titolo, domande: [chiavi]}]. Al massimo TEST_MAX_PAGINE (quello che arriva
 * oltre si scarta prima di guardarlo), titoli di testo semplice tagliati, chiavi che non sono domande del test scartate, una
 * domanda in una sola pagina (la prima), le domande che nessuna pagina ha in fondo all'ultima (con una pagina sola se non ce
 * n'è nessuna). Le pagine vuote restano (il pannello le mostra; sul sito si saltano). Ritorna [pagine, domande nell'ordine
 * delle pagine]: una condizione che dopo il riordino punterebbe a una domanda che viene dopo si toglie.
 */
function test_pagine_normalizza(mixed $in, array $domande): array {
    $perK = array_column($domande, null, 'k');
    $pagine = []; $prese = [];
    foreach (is_array($in) ? array_slice($in, 0, TEST_MAX_PAGINE) : [] as $p) {
        if (!is_array($p)) continue;
        $ks = [];
        foreach (is_array($p['domande'] ?? null) ? array_slice($p['domande'], 0, TEST_MAX_DOMANDE) : [] as $k) {
            if (!is_string($k) || !isset($perK[$k]) || isset($prese[$k])) continue;
            $prese[$k] = true;
            $ks[] = $k;
        }
        $pagine[] = ['titolo' => test_testo($p['titolo'] ?? '', TEST_MAX_TITOLO_PAGINA), 'domande' => $ks];
    }
    $resto = array_values(array_filter(array_keys($perK), fn($k) => !isset($prese[$k])));
    if (!$pagine) $pagine[] = ['titolo' => '', 'domande' => []];
    if ($resto) $pagine[count($pagine) - 1]['domande'] = array_merge($pagine[count($pagine) - 1]['domande'], $resto);
    $ordine = array_merge(...array_map(fn($p) => $p['domande'], $pagine));
    if ($ordine !== array_column($domande, 'k')) {
        $domande = array_map(fn($k) => $perK[$k], $ordine);
        $viste = [];
        foreach ($domande as $i => $q) {
            if ($q['condizione'] && !isset($viste[$q['condizione']['domanda']])) $domande[$i]['condizione'] = null;
            $viste[$q['k']] = true;
        }
    }
    return [$pagine, $domande];
}

/**
 * 2.24.0: le pagine che vede chi fa il test (senza le domande sulla persona, che sono sempre l'ultima pagina):
 * [{titolo, domande: [chiavi]}]. Una per pagina: una domanda per pagina, senza titoli; tutte in una pagina: una pagina sola,
 * senza titolo; a pagine: quelle scelte, senza le vuote.
 */
function test_pagine(array $dati): array {
    $tutte = array_column($dati['domande'], 'k');
    return match ($dati['modo'] ?? 'una') {
        'pagine' => array_values(array_filter($dati['pagine'], fn($p) => $p['domande'])),
        'tutte' => $tutte ? [['titolo' => '', 'domande' => $tutte]] : [],
        default => array_map(fn($k) => ['titolo' => '', 'domande' => [$k]], $tutte),
    };
}

/**
 * 2.23.0: le domande sulla persona di un test, controllate: {obbligatorie: bool, domande: [{k, nome, testo, opzioni: [{k, t}]}]}.
 * Solo domande del catalogo (catalogo-persona.php), una volta sola ciascuna, con le risposte scritte dall'autore (da 2 a
 * TEST_PERSONA_MAX_OPZIONI, chiavi uniche; la chiave TEST_PERSONA_NR è riservata a "Preferisco non rispondere").
 */
function test_persona_normalizza(mixed $p): array {
    $p = is_array($p) ? $p : [];
    $cat = test_persona_catalogo();
    $out = ['obbligatorie' => !empty($p['obbligatorie']), 'domande' => []];
    $viste = [];
    foreach (is_array($p['domande'] ?? null) ? array_slice($p['domande'], 0, count($cat)) : [] as $q) {
        if (!is_array($q) || !is_string($q['k'] ?? null) || !isset($cat[$q['k']]) || isset($viste[$q['k']])) continue;
        $c = $cat[$q['k']];
        $ops = []; $ko = [];
        foreach (is_array($q['opzioni'] ?? null) ? array_slice($q['opzioni'], 0, TEST_PERSONA_MAX_OPZIONI) : [] as $o) {
            if (!is_array($o)) continue;
            $t = test_testo($o['t'] ?? '', 120);
            if ($t === '') continue;
            $k = is_string($o['k'] ?? null) && $o['k'] !== TEST_PERSONA_NR && preg_match('/^[a-z][a-z0-9]{1,15}$/', $o['k']) && !isset($ko[$o['k']]) ? $o['k'] : '';
            if ($k === '') { $n = count($ops) + 1; do { $k = 'o' . $n++; } while (isset($ko[$k])); }
            $ko[$k] = true;
            $ops[] = ['k' => $k, 't' => $t];
        }
        if (count($ops) < 2) $ops = $c['opzioni'];
        $viste[$q['k']] = true;
        $out['domande'][] = ['k' => $c['k'], 'nome' => test_testo($q['nome'] ?? '', 40) ?: $c['nome'], 'testo' => test_testo($q['testo'] ?? '', 300) ?: $c['testo'], 'opzioni' => $ops];
    }
    return $out;
}

/** 2.23.0: il risultato completo va solo per email (e il riquadro dell'email c'è sempre)? */
function test_solo_email(array $t): bool {
    return !empty($t['dati']['dopo']['solo_email']);
}

/** 2.23.0: il riquadro dell'email c'è (scelto nel passo 5, o perché il risultato completo va solo per email). */
function test_email_attiva(array $t): bool {
    return !empty($t['dati']['dopo']['email']) || test_solo_email($t);
}

/** Una riga del database pronta: dati decodificati e controllati. */
function test_riga(array $r): array {
    $r['id'] = (int)$r['id'];
    $r['durata'] = (int)$r['durata'];
    $r['tipo'] = isset(TEST_TIPI[$r['tipo']]) ? $r['tipo'] : 'punteggio';
    $r['dati'] = test_normalizza(json_decode((string)$r['dati'], true), $r['tipo']);
    return $r;
}

function test_tutti(bool $pubblici = false): array {
    test_schema();
    $rows = db()->query('SELECT t.*, (SELECT COUNT(*) FROM ' . tbl('test_risultati') . ' r WHERE r.test_id = t.id) AS completati FROM ' . tbl('test_test') . ' t'
        . ($pubblici ? " WHERE t.stato = 'pubblicato'" : '') . ' ORDER BY t.nome')->fetchAll();
    return array_map('test_riga', $rows);
}

function test_leggi(int $id): ?array {
    test_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('test_test') . ' WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? test_riga($r) : null;
}

function test_per_slug(string $slug): ?array {
    test_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('test_test') . ' WHERE slug = ?');
    $st->execute([$slug]);
    $r = $st->fetch();
    return $r ? test_riga($r) : null;
}

function test_url(array $t): string {
    return TEST_PERCORSO . $t['slug'] . '/';
}

/** Un indirizzo libero per un test: il nome breve, con un numero in fondo se è già usato. */
function test_slug_libero(string $slug, int $id = 0): string {
    $base = $slug; $n = 2;
    while (true) {
        $st = db()->prepare('SELECT id FROM ' . tbl('test_test') . ' WHERE slug = ? AND id <> ?');
        $st->execute([$slug, $id]);
        if (!$st->fetchColumn()) return $slug;
        $slug = substr($base, 0, 95) . '-' . $n++;
    }
}

/** Crea un test (vuoto o da un modello). Ritorna l'id. */
function test_crea(string $nome, string $tipo, ?array $dati = null): int {
    test_schema();
    $tipo = isset(TEST_TIPI[$tipo]) ? $tipo : 'punteggio';
    $nome = test_testo($nome, 200) ?: 'Test senza nome';
    $slug = test_slug_libero(test_slug($nome));
    $dati ??= test_dati_vuoti($tipo);
    // 2.23.0: per i test nuovi (anche dai modelli) il risultato completo va solo per email; una copia tiene la scelta dell'originale.
    if (!is_array($dati['dopo'] ?? null) || !array_key_exists('solo_email', $dati['dopo'])) $dati['dopo'] = (is_array($dati['dopo'] ?? null) ? $dati['dopo'] : []) + ['solo_email' => true];
    $dati = test_normalizza($dati, $tipo);
    $ora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO ' . tbl('test_test') . ' (slug, nome, tipo, stato, durata, dati, creato, aggiornato) VALUES (?, ?, ?, \'bozza\', 0, ?, ?, ?)')
        ->execute([$slug, $nome, $tipo, json_encode($dati, JSON_UNESCAPED_SLASHES), $ora, $ora]);
    $id = (int)db()->lastInsertId();
    test_presentazione_partenza($id, $dati['presentazione']);
    return $id;
}

/** Salva i campi di un test: nome, slug, tipo, durata, stato, dati (array, controllato qui). */
function test_salva(int $id, array $campi): void {
    $t = test_leggi($id);
    if (!$t) throw new RuntimeException('Il test non c\'è più.');
    $tipo = isset(TEST_TIPI[$campi['tipo'] ?? '']) ? (string)$campi['tipo'] : $t['tipo'];
    $nome = array_key_exists('nome', $campi) ? (test_testo($campi['nome'], 200) ?: $t['nome']) : $t['nome'];
    $slug = array_key_exists('slug', $campi) ? test_slug_libero(test_slug(test_testo($campi['slug'], 120) ?: $nome), $id) : $t['slug'];
    $stato = in_array($campi['stato'] ?? '', ['bozza', 'pubblicato'], true) ? (string)$campi['stato'] : $t['stato'];
    $durata = array_key_exists('durata', $campi) ? max(0, min(240, (int)$campi['durata'])) : $t['durata'];
    $dati = test_normalizza(array_key_exists('dati', $campi) ? $campi['dati'] : $t['dati'], $tipo);
    db()->prepare('UPDATE ' . tbl('test_test') . ' SET nome = ?, slug = ?, tipo = ?, stato = ?, durata = ?, dati = ?, aggiornato = ? WHERE id = ?')
        ->execute([$nome, $slug, $tipo, $stato, $durata, json_encode($dati, JSON_UNESCAPED_SLASHES), date('Y-m-d H:i:s'), $id]);
}

function test_duplica(int $id): int {
    $t = test_leggi($id);
    if (!$t) throw new RuntimeException('Il test non c\'è più.');
    $nuovo = test_crea($t['nome'] . ' (copia)', $t['tipo'], $t['dati']);
    db()->prepare('UPDATE ' . tbl('test_test') . ' SET durata = ?, struttura = ?, struttura_bozza = ? WHERE id = ?')->execute([$t['durata'], $t['struttura'], $t['struttura_bozza'], $nuovo]);
    return $nuovo;
}

function test_elimina(int $id): void {
    test_schema();
    foreach (['test_risultati', 'test_risposte', 'test_tentativi', 'test_attese'] as $tab) db()->prepare('DELETE FROM ' . tbl($tab) . ' WHERE test_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('test_test') . ' WHERE id = ?')->execute([$id]);
}

// ------------------------------------------------------------
// La presentazione con l'editor visuale (contenitore dell'editor, manuale tecnico 9.12)
// ------------------------------------------------------------

function test_contenitore(): array {
    return [
        'etichetta' => 'Presentazioni dei test', 'uno' => 'presentazione',
        'riga' => fn(int $id) => test_riga_editor($id),
        'salva' => function (int $id, string $json, bool $pubblica, string $autore = '') {
            if ($pubblica) db()->prepare('UPDATE ' . tbl('test_test') . ' SET struttura = ?, struttura_bozza = NULL, struttura_aggiornata = NOW() WHERE id = ?')->execute([$json, $id]);
            else db()->prepare('UPDATE ' . tbl('test_test') . ' SET struttura_bozza = ?, struttura_aggiornata = NOW() WHERE id = ?')->execute([$json, $id]);
        },
        'impostazioni' => function (int $id, array $d) { if (trim((string)($d['titolo'] ?? '')) !== '') test_salva($id, ['nome' => (string)$d['titolo']]); },
        'permesso' => fn() => function_exists('is_admin') && is_admin(),
        'anteprima' => fn(array $riga) => test_cornice((int)$riga['id']),
    ];
}

function test_riga_editor(int $id): ?array {
    $t = test_leggi($id);
    if (!$t) return null;
    return ['id' => $t['id'], 'title' => (string)$t['nome'], 'slug' => (string)$t['slug'], 'status' => trim((string)$t['struttura']) !== '' ? 'published' : 'draft',
        'struttura' => $t['struttura'], 'struttura_bozza' => $t['struttura_bozza'], 'content' => '', 'html' => '',
        'percorso' => test_url($t), 'vedi' => cms_sito_base() . test_url($t) . '?preview=1', 'scheda' => 'addon-pagina.php?a=test&p=test&id=' . $t['id'] . '&passo=1'];
}

/** La cornice della tela: il titolo sopra, il pulsante "Inizia il test" sotto, il foglio dei test. */
function test_cornice(int $id): array {
    $t = test_leggi($id);
    if (!$t) return [];
    require_once __DIR__ . '/vista.php';
    return ['prima' => '<div class="wrap tq-pagina"><header class="tq-testa"><span class="occhiello">Test</span><h1>' . h($t['nome']) . '</h1><p class="tq-numeri">' . h(test_quanto_testo($t)) . '</p></header></div>',
        'dopo' => '<div class="wrap tq-pagina"><p><span class="btn btn-viola">Inizia il test</span></p><p class="tq-numeri">Qui sotto chi visita trova le domande, ' . h(['una' => 'una per schermata', 'pagine' => 'divise nelle pagine che hai scelto', 'tutte' => 'tutte in una pagina'][$t['dati']['modo']] ?? 'una per schermata') . '.</p></div>',
        'testa' => '<link rel="stylesheet" href="' . h(test_asset('test.css')) . '">'];
}

/** La presentazione di partenza: una sezione stretta con un widget Testo, pubblicata (si cambia con l'editor). */
function test_presentazione_partenza(int $id, string $testo = ''): void {
    require_once dirname(__DIR__, 3) . '/includes/editor.php';
    $html = $testo !== '' ? implode('', array_map(fn($p) => '<p>' . h(trim($p)) . '</p>', preg_split('/\R{2,}/', $testo) ?: [])) : '<p>Scrivi qui la presentazione del test: a chi serve, cosa misura, cosa succede alla fine.</p>';
    $albero = editor_valida(['elementi' => [editor_sezione([editor_widget('testo', ['contenuto' => $html, 'margine_sotto' => ['d' => 0]])], ['larghezza' => 'stretta', 'spazio_sopra' => ['d' => 0], 'spazio_sotto' => ['d' => 16]])]]);
    if (!$albero) return;
    $autore = function_exists('current_user') ? (string)(current_user()['username'] ?? '') : '';
    // Con il contenitore registrato passa dal salvataggio normale (resta la prima versione). Senza (addon appena acceso,
    // manifesto non ancora letto in questa richiesta) si scrive qui: editor_salva() scriverebbe nella tabella delle pagine.
    if (function_exists('editor_contenitore') && editor_contenitore('test_presentazione')) { editor_salva($id, $albero, true, $autore, 'presentazione di partenza', 'test_presentazione'); return; }
    db()->prepare('UPDATE ' . tbl('test_test') . ' SET struttura = ?, struttura_aggiornata = NOW() WHERE id = ?')->execute([editor_json($albero), $id]);
}

// ------------------------------------------------------------
// Calcolo
// ------------------------------------------------------------

/**
 * Le risposte possibili di una domanda, ognuna con i suoi punti grezzi (prima del peso e dell'inversione):
 * [['k' => chiave, 't' => testo, 'punti' => n, 'profili' => [...]]]. Il cursore ha 11 risposte, da '0' a '10'.
 */
function test_opzioni(array $q, array $dati): array {
    return match ($q['tipo']) {
        'scala' => array_map(fn($i, $s) => ['k' => 's' . $i, 't' => $s['t'], 'punti' => (int)$s['v'], 'profili' => []], array_keys($dati['scala']), $dati['scala']),
        'cursore' => array_map(fn($n) => ['k' => (string)$n, 't' => (string)$n, 'punti' => $n, 'profili' => []], range(0, 10)),
        default => array_map(fn($o) => $o + ['profili' => []], $q['opzioni']),
    };
}

/** I punti di una risposta, con l'inversione: [punti, minimo, massimo] prima del peso. */
function test_punti_risposta(array $q, array $dati, string $valore): ?array {
    $ops = test_opzioni($q, $dati);
    $p = array_column($ops, 'punti', 'k');
    if (!array_key_exists($valore, $p)) return null;
    $min = min($p); $max = max($p);
    $v = (int)$p[$valore];
    if ($q['invertita']) $v = $max + $min - $v;
    return [$v, $min, $max];
}

/** Le domande mostrate con queste risposte (le condizionali solo se la loro condizione vale), in ordine. */
function test_mostrate(array $dati, array $risposte): array {
    $mostrate = [];
    foreach ($dati['domande'] as $q) {
        $c = $q['condizione'];
        if ($c && (!isset($mostrate[$c['domanda']]) || !in_array((string)($risposte[$c['domanda']] ?? ''), $c['valori'], true))) continue;
        $mostrate[$q['k']] = true;
    }
    return array_keys($mostrate);
}

/** La fascia di una percentuale intera (0-100): la sua chiave, o '' se nessuna la contiene. */
function test_fascia(array $fasce, int $pct): string {
    foreach ($fasce as $f) if ($pct >= $f['da'] && $pct <= $f['a']) return $f['k'];
    return '';
}

/**
 * Le fasce coprono da 0 a 100 senza buchi né sovrapposizioni? '' se sì, altrimenti cosa sistemare, in parole.
 * Le percentuali sono numeri interi: dopo una fascia che finisce a 33 la successiva comincia a 34.
 */
function test_fasce_controlla(array $fasce, string $di = 'del totale'): string {
    if (!$fasce) return "Mancano le fasce $di: aggiungine almeno una, da 0 a 100.";
    $prima = null;
    foreach ($fasce as $f) {
        if ($f['da'] > $f['a']) return "Nella fascia «{$f['nome']}» $di «da» ({$f['da']}) è più grande di «a» ({$f['a']}).";
        if ($prima === null && $f['da'] !== 0) return "Le fasce $di cominciano da {$f['da']}: la prima deve cominciare da 0.";
        if ($prima !== null) {
            if ($f['da'] <= $prima['a']) return "Le fasce «{$prima['nome']}» e «{$f['nome']}» $di si sovrappongono: «{$prima['nome']}» finisce a {$prima['a']}, quindi «{$f['nome']}» deve cominciare da " . ($prima['a'] + 1) . '.';
            if ($f['da'] > $prima['a'] + 1) return "Fra le fasce «{$prima['nome']}» e «{$f['nome']}» $di c'è un buco: da " . ($prima['a'] + 1) . ' a ' . ($f['da'] - 1) . ' non c\'è nessuna fascia.';
        }
        $prima = $f;
    }
    if ($prima['a'] !== 100) return "Le fasce $di finiscono a {$prima['a']}: l'ultima deve arrivare a 100.";
    return '';
}

/** Tutti i problemi che impediscono di pubblicare un test, in parole. Vuoto: si può pubblicare. */
function test_problemi(array $t): array {
    $d = $t['dati'];
    $p = [];
    if (!$d['domande']) $p[] = 'Il test non ha ancora domande: aggiungile nel passo 2.';
    if ($t['tipo'] !== 'profili') { $e = test_fasce_controlla($d['fasce']); if ($e !== '') $p[] = $e; }
    if ($t['tipo'] === 'aree') {
        if (!$d['aree']) $p[] = 'Un test ad aree ha bisogno di almeno un\'area: aggiungila nel passo 2.';
        foreach ($d['aree'] as $a) {
            $e = test_fasce_controlla($a['fasce'], 'dell\'area «' . $a['nome'] . '»');
            if ($e !== '') $p[] = $e;
            if (!array_filter($d['domande'], fn($q) => $q['area'] === $a['k'])) $p[] = 'L\'area «' . $a['nome'] . '» non ha domande: assegnale almeno una domanda, oppure toglila.';
        }
    }
    if ($t['tipo'] === 'profili') {
        if (count($d['profili']) < 2) $p[] = 'Un test a profili ha bisogno di almeno due profili: aggiungili nel passo 2.';
        foreach ($d['profili'] as $pr) {
            $tocca = false;
            foreach ($d['domande'] as $q) foreach ($q['opzioni'] as $o) if (($o['profili'][$pr['k']] ?? 0) > 0) $tocca = true;
            if (!$tocca) $p[] = 'Nessuna risposta dà punti al profilo «' . $pr['nome'] . '»: non potrà mai vincere.';
        }
    }
    if (!array_filter($d['domande'], fn($q) => !$q['condizione'])) $p[] = 'Tutte le domande sono condizionali: almeno una deve comparire sempre.';
    return $p;
}

/**
 * Il risultato di un test con queste risposte (domanda => valore). Funzione pura: non legge e non scrive il database.
 * Ritorna:
 *   ok         false se mancano risposte obbligatorie o ci sono risposte non valide (allora 'errori' dice quali)
 *   errori     [['domanda' => chiave, 'codice' => 'obbligatoria'|'valore'|'sconosciuta']]
 *   risposte   le risposte valide delle domande mostrate (le altre si scartano)
 *   mostrate   le chiavi delle domande mostrate
 *   punti, minimo, massimo, totale (percentuale intera, null senza risposte), fascia (chiave)
 *   aree       [chiave => ['punti', 'minimo', 'massimo', 'pct', 'fascia']]
 *   profili    [chiave => punti], profilo (il vincitore)
 *   regola     la chiave della prima regola che vale, o ''
 */
function test_calcola(array $test, array $risposte): array {
    $d = $test['dati'];
    $tipo = $test['tipo'];
    $per = array_column($d['domande'], null, 'k');
    $errori = [];
    $pulite = [];
    foreach ($risposte as $k => $v) {
        if (!is_string($k) || !isset($per[$k])) { $errori[] = ['domanda' => is_string($k) ? mb_substr($k, 0, 20) : '', 'codice' => 'sconosciuta']; continue; }
        if (!is_scalar($v)) { $errori[] = ['domanda' => $k, 'codice' => 'valore']; continue; }
        $v = trim((string)$v);
        if ($v === '') continue;
        if (test_punti_risposta($per[$k], $d, $v) === null) { $errori[] = ['domanda' => $k, 'codice' => 'valore']; continue; }
        $pulite[$k] = $v;
    }
    $mostrate = test_mostrate($d, $pulite);
    $r = ['ok' => true, 'errori' => [], 'risposte' => [], 'mostrate' => $mostrate, 'punti' => 0.0, 'minimo' => 0.0, 'massimo' => 0.0, 'totale' => null, 'fascia' => '',
        'aree' => [], 'profili' => [], 'profilo' => '', 'regola' => ''];
    foreach ($d['aree'] as $a) $r['aree'][$a['k']] = ['punti' => 0.0, 'minimo' => 0.0, 'massimo' => 0.0, 'pct' => null, 'fascia' => ''];
    foreach ($d['profili'] as $p) $r['profili'][$p['k']] = 0.0;
    foreach ($mostrate as $k) {
        $q = $per[$k];
        if (!isset($pulite[$k])) { if ($q['obbligatoria']) $errori[] = ['domanda' => $k, 'codice' => 'obbligatoria']; continue; }
        $r['risposte'][$k] = $pulite[$k];
        if ($tipo === 'profili') {
            foreach (test_opzioni($q, $d) as $o) if ($o['k'] === $pulite[$k]) foreach ($o['profili'] as $pk => $n) if (isset($r['profili'][$pk])) $r['profili'][$pk] += $n * $q['peso'];
            continue;
        }
        [$v, $min, $max] = test_punti_risposta($q, $d, $pulite[$k]);
        $r['punti'] += $v * $q['peso']; $r['minimo'] += $min * $q['peso']; $r['massimo'] += $max * $q['peso'];
        if ($q['area'] !== '' && isset($r['aree'][$q['area']])) {
            $r['aree'][$q['area']]['punti'] += $v * $q['peso']; $r['aree'][$q['area']]['minimo'] += $min * $q['peso']; $r['aree'][$q['area']]['massimo'] += $max * $q['peso'];
        }
    }
    // Le domande che non esistono si scartano senza fermare il calcolo; quelle obbligatorie vuote e i valori sbagliati lo fermano.
    $r['errori'] = $errori;
    $r['ok'] = !array_filter($errori, fn($e) => $e['codice'] !== 'sconosciuta');
    $pct = fn(float $p, float $min, float $max) => $max > $min ? (int)round(($p - $min) / ($max - $min) * 100) : null;
    if ($tipo === 'profili') {
        $migliore = null;
        foreach ($d['profili'] as $p) if ($migliore === null || $r['profili'][$p['k']] > $r['profili'][$migliore]) $migliore = $p['k']; // parità: resta il primo
        $r['profilo'] = (string)$migliore;
        return $r;
    }
    $r['totale'] = $pct($r['punti'], $r['minimo'], $r['massimo']);
    if ($r['totale'] === null && $r['risposte']) $r['totale'] = 0;
    $r['fascia'] = $r['totale'] !== null ? test_fascia($d['fasce'], $r['totale']) : '';
    foreach ($d['aree'] as $a) {
        $x = &$r['aree'][$a['k']];
        $x['pct'] = $pct($x['punti'], $x['minimo'], $x['massimo']);
        $x['fascia'] = $x['pct'] !== null ? test_fascia($a['fasce'], $x['pct']) : '';
        unset($x);
    }
    foreach ($d['regole'] as $regola) {
        $vale = array_map(function ($c) use ($r) {
            if ($c['cosa'] === 'totale') return $r['fascia'] === $c['fascia'];
            $a = substr($c['cosa'], 5);
            return ($r['aree'][$a]['fascia'] ?? '') === $c['fascia'];
        }, $regola['condizioni']);
        if ($regola['modo'] === 'o' ? in_array(true, $vale, true) : !in_array(false, $vale, true)) { $r['regola'] = $regola['k']; break; }
    }
    return $r;
}

/** I codici dell'esito, per il filtro dell'innesco delle automazioni: f:<fascia>, a:<area>:<fascia>, p:<profilo>, r:<regola>. */
function test_esiti(array $r): array {
    $e = [];
    if ($r['fascia'] !== '') $e[] = 'f:' . $r['fascia'];
    foreach ($r['aree'] as $k => $a) if ($a['fascia'] !== '') $e[] = 'a:' . $k . ':' . $a['fascia'];
    if ($r['profilo'] !== '') $e[] = 'p:' . $r['profilo'];
    if ($r['regola'] !== '') $e[] = 'r:' . $r['regola'];
    return $e;
}

/** I codici dell'esito di un test con il loro nome, per la tendina "Con quale risultato" delle automazioni. */
function test_esiti_nomi(array $t): array {
    $d = $t['dati'];
    $out = [];
    foreach ($d['fasce'] as $f) $out['f:' . $f['k']] = 'Totale: ' . $f['nome'];
    foreach ($d['aree'] as $a) foreach ($a['fasce'] as $f) $out['a:' . $a['k'] . ':' . $f['k']] = $a['nome'] . ': ' . $f['nome'];
    foreach ($d['profili'] as $p) $out['p:' . $p['k']] = 'Profilo: ' . $p['nome'];
    foreach ($d['regole'] as $rg) $out['r:' . $rg['k']] = 'Regola: ' . $rg['nome'];
    return $out;
}

/** Il nome dell'esito principale: la regola, altrimenti la fascia del totale, altrimenti il profilo. */
function test_esito_nome(array $t, array $r): string {
    $d = $t['dati'];
    if ($r['regola'] !== '') foreach ($d['regole'] as $x) if ($x['k'] === $r['regola']) return $x['nome'];
    if ($t['tipo'] === 'profili') foreach ($d['profili'] as $x) if ($x['k'] === $r['profilo']) return $x['nome'];
    foreach ($d['fasce'] as $x) if ($x['k'] === $r['fascia']) return $x['nome'];
    return '';
}

// ------------------------------------------------------------
// Il link condivisibile: solo l'esito, firmato
// ------------------------------------------------------------

/** L'esito in forma corta (niente risposte, niente dati personali), da mettere nel link. */
function test_esito_corto(array $t, array $r): array {
    $e = ['t' => $t['id'], 'g' => date('Ymd')];
    if ($t['tipo'] === 'profili') {
        $somma = array_sum(array_map(fn($x) => max(0, $x), $r['profili']));
        $e['p'] = $r['profilo'];
        $e['q'] = array_map(fn($x) => $somma > 0 ? (int)round(max(0, $x) / $somma * 100) : 0, $r['profili']);
    } else {
        $e['o'] = $r['totale'];
        $e['f'] = $r['fascia'];
        if ($r['aree']) $e['a'] = array_map(fn($a) => [$a['pct'], $a['fascia']], $r['aree']);
        if ($r['regola'] !== '') $e['r'] = $r['regola'];
    }
    return $e;
}

function test_b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function test_b64_leggi(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/'), true); }

function test_firma(string $cosa): string {
    return substr(hash_hmac('sha256', 'test-esito|' . $cosa, moduli_segreto()), 0, 32);
}

/** I parametri del link del risultato: e (l'esito corto in base64url) e f (la sua firma). */
function test_esito_parametri(array $corto): array {
    $e = test_b64(json_encode($corto, JSON_UNESCAPED_SLASHES));
    return ['e' => $e, 'f' => test_firma($e)];
}

/** Il link intero del risultato (quello da condividere). */
function test_link_esito(array $t, array $corto): string {
    return cms_url_sito() . test_url($t) . 'risultato/?' . http_build_query(test_esito_parametri($corto));
}

/** L'esito di un link, se la firma torna e il link è di questo test; altrimenti null. */
function test_esito_da_link(array $t, mixed $e, mixed $f): ?array {
    if (!is_string($e) || !is_string($f) || strlen($e) > 3000 || !preg_match('/^[A-Za-z0-9_-]+$/', $e) || !preg_match('/^[a-f0-9]{32}$/', $f)) return null;
    if (!hash_equals(test_firma($e), $f)) return null;
    $c = json_decode(test_b64_leggi($e), true);
    if (!is_array($c) || (int)($c['t'] ?? 0) !== (int)$t['id']) return null;
    return $c;
}

/**
 * Il permesso di chiedere il risultato per email, valido TEST_EMAIL_ORE ore dopo il test (non sta nel link da condividere).
 * 2.23.0: $pz sono le risposte sulla persona in forma corta (test_persona_corto()), firmate insieme all'esito: servono solo
 * per i tag di chi si iscrive. Senza risposte la firma è quella della 2.22.0.
 */
function test_chiave_email(string $e, ?int $scade = null, string $pz = ''): string {
    $scade ??= time() + TEST_EMAIL_ORE * 3600;
    return $scade . '.' . substr(hash_hmac('sha256', 'test-email|' . $e . ($pz !== '' ? '|p=' . $pz : '') . '|' . $scade, moduli_segreto()), 0, 32);
}

function test_chiave_email_valida(string $e, mixed $k, string $pz = ''): bool {
    if (!is_string($k) || !preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $k, $m) || (int)$m[1] < time()) return false;
    return hash_equals(test_chiave_email($e, (int)$m[1], $pz), $k);
}

// ------------------------------------------------------------
// 2.23.0: le domande sulla persona
// ------------------------------------------------------------

/**
 * Le risposte sulla persona arrivate con il test, controllate sul test: [risposte (k => chiave della risposta o
 * TEST_PERSONA_NR), errori (k => 'obbligatoria' | 'valore')]. Una domanda che il test non fa si scarta; una risposta che
 * non è fra quelle della domanda è un errore; "Preferisco non rispondere" vale solo se le domande sono facoltative.
 */
function test_persona_valida(array $t, mixed $in): array {
    $p = $t['dati']['persona'];
    $in = is_array($in) ? $in : [];
    $risposte = []; $errori = [];
    foreach ($p['domande'] as $q) {
        $v = is_string($in[$q['k']] ?? null) ? trim($in[$q['k']]) : '';
        if ($v === '') { if ($p['obbligatorie']) $errori[$q['k']] = 'obbligatoria'; continue; }
        if ($v === TEST_PERSONA_NR && !$p['obbligatorie']) { $risposte[$q['k']] = TEST_PERSONA_NR; continue; }
        if (!in_array($v, array_column($q['opzioni'], 'k'), true)) { $errori[$q['k']] = 'valore'; continue; }
        $risposte[$q['k']] = $v;
    }
    return [$risposte, $errori];
}

/** Le risposte sulla persona in forma corta per il permesso dell'email: "eta=o3.genere=nr" (solo chiavi controllate). */
function test_persona_corto(array $risposte): string {
    $pezzi = [];
    foreach ($risposte as $k => $v) if (preg_match('/^[a-z][a-z0-9]{1,15}$/', (string)$k) && preg_match('/^[a-z][a-z0-9]{1,15}$/', (string)$v)) $pezzi[] = $k . '=' . $v;
    return mb_substr(implode('.', $pezzi), 0, 1500);
}

/** Dalla forma corta alle risposte, ricontrollate sul test di adesso (una domanda o una risposta tolta nel frattempo si scarta). */
function test_persona_da_corto(array $t, string $pz): array {
    $in = [];
    foreach (explode('.', $pz) as $x) if (preg_match('/^([a-z][a-z0-9]{1,15})=([a-z][a-z0-9]{1,15})$/', $x, $m)) $in[$m[1]] = $m[2];
    $out = [];
    foreach ($t['dati']['persona']['domande'] as $q) {
        $v = $in[$q['k']] ?? '';
        if ($v === TEST_PERSONA_NR || in_array($v, array_column($q['opzioni'], 'k'), true)) $out[$q['k']] = $v;
    }
    return $out;
}

/**
 * I tag di chi si iscrive: le risposte sulla persona come "Nome breve: risposta" ("Età: 35-44"; "Preferisco non
 * rispondere" non diventa un tag) e l'esito del test ("Test <nome>: <fascia o profilo>"), al massimo 60 caratteri l'uno.
 */
function test_tag_persona(array $t, array $risposte, array $e = []): array {
    $tag = [];
    foreach ($t['dati']['persona']['domande'] as $q) {
        $v = $risposte[$q['k']] ?? '';
        if ($v === '' || $v === TEST_PERSONA_NR) continue;
        $nomi = array_column($q['opzioni'], 't', 'k');
        if (isset($nomi[$v])) $tag[] = mb_substr($q['nome'] . ': ' . $nomi[$v], 0, 60);
    }
    $esito = test_esito_tag($t, $e);
    if ($esito !== '') $tag[] = $esito;
    return $tag;
}

/** Il tag dell'esito: "Test <nome del test>: <fascia del totale, o profilo>", con il nome del test accorciato per stare in 60 caratteri. */
function test_esito_tag(array $t, array $e): string {
    $d = $t['dati'];
    $per = fn(array $l, string $k) => array_values(array_filter($l, fn($x) => $x['k'] === $k))[0]['nome'] ?? '';
    $esito = $t['tipo'] === 'profili' ? $per($d['profili'], (string)($e['p'] ?? '')) : $per($d['fasce'], (string)($e['f'] ?? ''));
    if ($esito === '') return '';
    $coda = ': ' . mb_substr($esito, 0, 40);
    $nome = trim(str_replace([',', ';'], ' ', (string)$t['nome']));
    $spazio = max(5, 60 - 5 - mb_strlen($coda));
    if (mb_strlen($nome) > $spazio) $nome = rtrim(mb_substr($nome, 0, $spazio - 1)) . '…';
    return 'Test ' . $nome . $coda;
}

/**
 * Il codice del modulo del test, il sistema anti-robot dell'invio: quando è stata aperta la pagina, per quale test, firmato.
 * Il test si manda anche senza JavaScript, quindi niente captcha (che è una prova di lavoro fatta dal browser): al suo
 * posto il codice firmato (non si inventa, non vale per un altro test, scade dopo un giorno), il tempo minimo (un robot
 * manda subito: una persona impiega almeno TEST_SECONDI_MINIMI secondi), il campo esca, l'origine e il limite per connessione.
 */
function test_gettone(int $id, ?int $emesso = null): string {
    $emesso ??= time();
    return $emesso . '.' . substr(hash_hmac('sha256', 'test-modulo|' . $id . '|' . $emesso, moduli_segreto()), 0, 32);
}

/** '' se il codice va bene, altrimenti 'scaduto' (falso, di un altro test, più vecchio di un giorno) o 'presto'. */
function test_gettone_controlla(int $id, mixed $g): string {
    if (!is_string($g) || !preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $g, $m)) return 'scaduto';
    if (!hash_equals(test_gettone($id, (int)$m[1]), $g) || (int)$m[1] < time() - 86400 || (int)$m[1] > time() + 60) return 'scaduto';
    return (int)$m[1] > time() - TEST_SECONDI_MINIMI ? 'presto' : '';
}

// ------------------------------------------------------------
// Numeri: quanto dura, limiti, statistiche
// ------------------------------------------------------------

/** "12 domande, circa 3 minuti" (le condizionali contano come "fino a"). */
function test_quanto_testo(array $t): string {
    $d = $t['dati']['domande'];
    $pers = count($t['dati']['persona']['domande'] ?? []); // 2.23.0: anche le domande sulla persona
    $fisse = count(array_filter($d, fn($q) => !$q['condizione'])) + $pers;
    $tutte = count($d) + $pers;
    $min = $t['durata'] > 0 ? $t['durata'] : max(1, (int)ceil($tutte * TEST_SECONDI_DOMANDA / 60));
    $n = $fisse === $tutte ? ($tutte === 1 ? '1 domanda' : $tutte . ' domande') : ($fisse . '-' . $tutte . ' domande');
    return $n . ', circa ' . ($min === 1 ? '1 minuto' : $min . ' minuti');
}

/** L'impronta della connessione: l'IP con un sale che cambia ogni giorno (l'IP in chiaro non si salva mai). */
function test_impronta(): string {
    return hash_hmac('sha256', moduli_ip() . '|' . date('Y-m-d'), moduli_segreto() . 'test-limiti');
}

/** Questa connessione ha superato il limite per questa cosa nell'ultima ora? Se no, conta anche questa volta. */
function test_limite(string $chiave, int $massimo): bool {
    test_schema();
    db()->exec('DELETE FROM ' . tbl('test_limiti') . ' WHERE creato < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('test_limiti') . ' WHERE impronta = ? AND chiave = ? AND creato > ?');
    $st->execute([test_impronta(), $chiave, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)$st->fetchColumn() >= $massimo) return true;
    db()->prepare('INSERT INTO ' . tbl('test_limiti') . ' (impronta, chiave, creato) VALUES (?, ?, NOW())')->execute([test_impronta(), $chiave]);
    return false;
}

/** Un tentativo comincia o avanza (richiesta leggera dal browser: codice casuale, domanda raggiunta). */
function test_tentativo(int $test, string $codice, string $domanda = '', int $passo = 0): void {
    if (!preg_match('/^[a-z0-9]{16}$/', $codice)) return;
    test_schema();
    $st = db()->prepare('SELECT completato FROM ' . tbl('test_tentativi') . ' WHERE codice = ?');
    $st->execute([$codice]);
    $c = $st->fetch();
    if (!$c) {
        if (test_limite('inizio', TEST_LIMITE_INIZI_ORA)) return;
        db()->prepare('INSERT IGNORE INTO ' . tbl('test_tentativi') . ' (codice, test_id, giorno, ultima, passi, completato) VALUES (?, ?, CURDATE(), ?, ?, 0)')->execute([$codice, $test, mb_substr($domanda, 0, 20), max(0, min(500, $passo))]);
        return;
    }
    if ((int)$c['completato'] || $domanda === '') return;
    db()->prepare('UPDATE ' . tbl('test_tentativi') . ' SET ultima = ?, passi = GREATEST(passi, ?) WHERE codice = ? AND test_id = ? AND completato = 0')->execute([mb_substr($domanda, 0, 20), max(0, min(500, $passo)), $codice, $test]);
}

/** Registra un risultato per le statistiche: esito e punteggi con il giorno, contatori delle risposte, tentativo finito. */
function test_registra(array $t, array $r, string $tentativo = '', array $persona = []): void {
    test_schema();
    $aree = $r['aree'] ? json_encode(array_map(fn($a) => $a['pct'], $r['aree'])) : null;
    if ($t['tipo'] === 'profili') $aree = json_encode(array_map(fn($x) => round($x, 1), $r['profili']));
    // 2.23.0: le risposte sulla persona stanno con l'esito, nella stessa riga anonima (niente email, niente IP, solo il giorno).
    db()->prepare('INSERT INTO ' . tbl('test_risultati') . ' (test_id, giorno, totale, fascia, profilo, regola, aree, persona) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?)')
        ->execute([$t['id'], $r['totale'], $r['fascia'], $r['profilo'], $r['regola'], $aree, $persona ? json_encode($persona) : null]);
    $ins = db()->prepare('INSERT INTO ' . tbl('test_risposte') . ' (test_id, domanda, valore, n) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE n = n + 1');
    foreach ($r['risposte'] as $k => $v) $ins->execute([$t['id'], $k, $v]);
    $fatto = 0;
    if (preg_match('/^[a-z0-9]{16}$/', $tentativo)) {
        $st = db()->prepare('UPDATE ' . tbl('test_tentativi') . ' SET completato = 1 WHERE codice = ? AND test_id = ?');
        $st->execute([$tentativo, $t['id']]);
        $fatto = $st->rowCount();
        if (!$fatto) { $c = db()->prepare('SELECT COUNT(*) FROM ' . tbl('test_tentativi') . ' WHERE codice = ?'); $c->execute([$tentativo]); $fatto = (int)$c->fetchColumn(); }
    }
    // Senza JavaScript (o senza il codice del tentativo) l'inizio non è stato contato: si conta adesso, già finito.
    if (!$fatto) db()->prepare('INSERT INTO ' . tbl('test_tentativi') . ' (codice, test_id, giorno, ultima, passi, completato) VALUES (?, ?, CURDATE(), \'\', 0, 1)')->execute([bin2hex(random_bytes(8)), $t['id']]);
}

/** Le statistiche di un test negli ultimi $giorni giorni (0: da sempre). Tutto aggregato. */
function test_statistiche(array $t, int $giorni = 0): array {
    test_schema();
    $da = $giorni > 0 ? date('Y-m-d', time() - ($giorni - 1) * 86400) : '1970-01-01';
    $q = function (string $sql, array $p) { $st = db()->prepare($sql); $st->execute($p); return $st; };
    $iniziati = (int)$q('SELECT COUNT(*) FROM ' . tbl('test_tentativi') . ' WHERE test_id = ? AND giorno >= ?', [$t['id'], $da])->fetchColumn();
    $completati = (int)$q('SELECT COUNT(*) FROM ' . tbl('test_risultati') . ' WHERE test_id = ? AND giorno >= ?', [$t['id'], $da])->fetchColumn();
    $abbandoni = [];
    foreach ($q('SELECT ultima, COUNT(*) AS n FROM ' . tbl('test_tentativi') . ' WHERE test_id = ? AND giorno >= ? AND completato = 0 GROUP BY ultima', [$t['id'], $da]) as $x) $abbandoni[(string)$x['ultima']] = (int)$x['n'];
    $dist = ['fascia' => [], 'profilo' => [], 'regola' => []];
    foreach (array_keys($dist) as $col) foreach ($q("SELECT $col AS k, COUNT(*) AS n FROM " . tbl('test_risultati') . " WHERE test_id = ? AND giorno >= ? AND $col <> '' GROUP BY $col", [$t['id'], $da]) as $x) $dist[$col][(string)$x['k']] = (int)$x['n'];
    $somme = []; $conte = []; $sommaTot = 0; $contaTot = 0;
    foreach ($q('SELECT totale, aree FROM ' . tbl('test_risultati') . ' WHERE test_id = ? AND giorno >= ? ORDER BY id DESC LIMIT 50000', [$t['id'], $da]) as $x) {
        if ($x['totale'] !== null) { $sommaTot += (int)$x['totale']; $contaTot++; }
        foreach ((array)json_decode((string)$x['aree'], true) as $k => $v) if (is_numeric($v)) { $somme[$k] = ($somme[$k] ?? 0) + (float)$v; $conte[$k] = ($conte[$k] ?? 0) + 1; }
    }
    $medie = [];
    foreach ($somme as $k => $s) $medie[$k] = round($s / $conte[$k], 1);
    $risposte = [];
    foreach ($q('SELECT domanda, valore, n FROM ' . tbl('test_risposte') . ' WHERE test_id = ?', [$t['id']]) as $x) $risposte[(string)$x['domanda']][(string)$x['valore']] = (int)$x['n'];
    return ['iniziati' => max($iniziati, $completati), 'completati' => $completati, 'abbandoni' => $abbandoni, 'distribuzione' => $dist,
        'media_totale' => $contaTot ? round($sommaTot / $contaTot, 1) : null, 'medie' => $medie, 'risposte' => $risposte];
}

/**
 * 2.23.0: le statistiche per gruppi, una tabella per ogni domanda sulla persona: per ogni risposta quanti l'hanno data, la
 * distribuzione dei risultati (fascia del totale o profilo, in percentuale) e il punteggio medio (totale e per area).
 * Sempre su tutti i risultati del test (non sul periodo scelto: confrontando due periodi si isolerebbero poche persone).
 * Un gruppo con meno di TEST_GRUPPO_MINIMO persone non mostra niente ("meno di 5"); se quello che resta fuori dai gruppi
 * mostrati (gruppi nascosti e chi non ha risposto) è fra 1 e 4 persone, si nasconde anche il gruppo mostrato più piccolo,
 * perché per differenza dal totale si saprebbe il resto. Ritorna ['totale' => N, 'domande' => [...]].
 */
function test_statistiche_gruppi(array $t): array {
    test_schema();
    $d = $t['dati'];
    $st = db()->prepare('SELECT totale, fascia, profilo, aree, persona FROM ' . tbl('test_risultati') . ' WHERE test_id = ? ORDER BY id DESC LIMIT 50000');
    $st->execute([$t['id']]);
    $righe = [];
    foreach ($st as $x) $righe[] = ['totale' => $x['totale'] === null ? null : (int)$x['totale'], 'fascia' => (string)$x['fascia'], 'profilo' => (string)$x['profilo'],
        'aree' => (array)json_decode((string)$x['aree'], true), 'persona' => (array)json_decode((string)$x['persona'], true)];
    $tot = count($righe);
    $chiaveRis = $t['tipo'] === 'profili' ? 'profilo' : 'fascia';
    $out = ['totale' => $tot, 'domande' => []];
    foreach ($d['persona']['domande'] as $q) {
        $ops = array_merge($q['opzioni'], [['k' => TEST_PERSONA_NR, 't' => TEST_PERSONA_NR_TESTO]]);
        $gruppi = [];
        foreach ($ops as $o) $gruppi[$o['k']] = ['k' => $o['k'], 't' => $o['t'], 'n' => 0, 'ris' => [], 'somma' => 0.0, 'conta' => 0, 'aree' => [], 'aree_n' => [], 'nascosto' => false];
        foreach ($righe as $x) {
            $v = (string)($x['persona'][$q['k']] ?? '');
            if (!isset($gruppi[$v])) continue;
            $g = &$gruppi[$v];
            $g['n']++;
            if ($x[$chiaveRis] !== '') $g['ris'][$x[$chiaveRis]] = ($g['ris'][$x[$chiaveRis]] ?? 0) + 1;
            if ($x['totale'] !== null) { $g['somma'] += $x['totale']; $g['conta']++; }
            if ($t['tipo'] === 'aree') foreach ($x['aree'] as $ak => $av) if (is_numeric($av)) { $g['aree'][$ak] = ($g['aree'][$ak] ?? 0) + (float)$av; $g['aree_n'][$ak] = ($g['aree_n'][$ak] ?? 0) + 1; }
            unset($g);
        }
        // "Preferisco non rispondere" compare solo se le domande sono facoltative o se qualcuno l'ha data prima.
        if ($d['persona']['obbligatorie'] && !$gruppi[TEST_PERSONA_NR]['n']) unset($gruppi[TEST_PERSONA_NR]);
        foreach ($gruppi as &$g) $g['nascosto'] = $g['n'] > 0 && $g['n'] < TEST_GRUPPO_MINIMO;
        unset($g);
        // Il resto (gruppi nascosti e chi non ha risposto a questa domanda) non deve essere fra 1 e 4 persone.
        while (true) {
            $mostrati = array_filter($gruppi, fn($g) => !$g['nascosto'] && $g['n'] > 0);
            $resto = $tot - array_sum(array_column($mostrati, 'n'));
            if ($resto === 0 || $resto >= TEST_GRUPPO_MINIMO || !$mostrati) break;
            uasort($mostrati, fn($a, $b) => $a['n'] <=> $b['n']);
            $gruppi[array_key_first($mostrati)]['nascosto'] = true;
        }
        $righeQ = [];
        foreach ($gruppi as $g) {
            $r = ['k' => $g['k'], 't' => $g['t'], 'nascosto' => $g['nascosto'], 'perche' => $g['nascosto'] ? ($g['n'] < TEST_GRUPPO_MINIMO ? 'pochi' : 'resto') : '', 'n' => $g['nascosto'] ? null : $g['n'], 'dist' => [], 'media' => null, 'aree' => []];
            if (!$g['nascosto'] && $g['n'] > 0) {
                foreach ($g['ris'] as $rk => $rn) $r['dist'][$rk] = (int)round($rn / $g['n'] * 100);
                $r['media'] = $g['conta'] ? round($g['somma'] / $g['conta'], 1) : null;
                foreach ($g['aree'] as $ak => $as) $r['aree'][$ak] = round($as / $g['aree_n'][$ak], 1);
            }
            $righeQ[] = $r;
        }
        $out['domande'][] = ['k' => $q['k'], 'nome' => $q['nome'], 'testo' => $q['testo'], 'righe' => $righeQ];
    }
    return $out;
}

// ------------------------------------------------------------
// Simulatore
// ------------------------------------------------------------

/**
 * Il simulatore del pannello: il risultato con tutte le risposte al minimo, al massimo, a metà, e $quanti volte a caso.
 * Le risposte "al minimo" e "al massimo" seguono i punti (con l'inversione); nei profili, il minimo e il massimo sono la
 * prima e l'ultima risposta. Ritorna le prove fisse, i conteggi delle prove a caso e le fasce (o i profili) mai usciti.
 */
function test_simula(array $t, int $quanti = 200): array {
    $d = $t['dati'];
    $scegli = function (string $come) use ($t, $d): array {
        $ris = [];
        foreach ($d['domande'] as $q) {
            $ops = test_opzioni($q, $d);
            $valori = [];
            foreach ($ops as $o) $valori[$o['k']] = $t['tipo'] === 'profili' ? 0 : test_punti_risposta($q, $d, $o['k'])[0];
            if ($come === 'caso') { $ris[$q['k']] = (string)array_rand($valori); continue; }
            if ($t['tipo'] === 'profili') { $chiavi = array_keys($valori); $ris[$q['k']] = (string)($come === 'minimo' ? $chiavi[0] : ($come === 'massimo' ? end($chiavi) : $chiavi[intdiv(count($chiavi), 2)])); continue; }
            asort($valori);
            $chiavi = array_keys($valori);
            $ris[$q['k']] = (string)($come === 'minimo' ? $chiavi[0] : ($come === 'massimo' ? end($chiavi) : $chiavi[intdiv(count($chiavi) - 1, 2)]));
        }
        // Le risposte delle domande non mostrate non contano (test_calcola le scarta da solo).
        return $ris;
    };
    $fisse = [];
    foreach (['minimo' => 'Tutte al minimo', 'meta' => 'Tutte a metà', 'massimo' => 'Tutte al massimo'] as $come => $nome) $fisse[$come] = ['nome' => $nome, 'r' => test_calcola($t, $scegli($come))];
    $conta = ['fascia' => [], 'profilo' => [], 'regola' => [], 'aree' => []];
    $tutti = array_merge(array_column($fisse, 'r'));
    for ($i = 0; $i < max(0, min(2000, $quanti)); $i++) $tutti[] = test_calcola($t, $scegli('caso'));
    foreach ($tutti as $i => $r) {
        if ($i < 3) continue; // nei conteggi solo le prove a caso
        if ($r['fascia'] !== '') $conta['fascia'][$r['fascia']] = ($conta['fascia'][$r['fascia']] ?? 0) + 1;
        if ($r['profilo'] !== '') $conta['profilo'][$r['profilo']] = ($conta['profilo'][$r['profilo']] ?? 0) + 1;
        if ($r['regola'] !== '') $conta['regola'][$r['regola']] = ($conta['regola'][$r['regola']] ?? 0) + 1;
        foreach ($r['aree'] as $k => $a) if ($a['fascia'] !== '') $conta['aree'][$k][$a['fascia']] = ($conta['aree'][$k][$a['fascia']] ?? 0) + 1;
    }
    // Mai raggiunte: né nelle prove fisse né in quelle a caso.
    $mai = [];
    $uscite = fn(callable $f) => array_unique(array_filter(array_map($f, $tutti), fn($x) => $x !== ''));
    if ($t['tipo'] === 'profili') {
        $vinti = $uscite(fn($r) => $r['profilo']);
        foreach ($d['profili'] as $p) if (!in_array($p['k'], $vinti, true)) $mai[] = 'Il profilo «' . $p['nome'] . '» non ha mai vinto.';
    } else {
        $f = $uscite(fn($r) => $r['fascia']);
        foreach ($d['fasce'] as $x) if (!in_array($x['k'], $f, true)) $mai[] = 'La fascia «' . $x['nome'] . '» del totale non viene mai raggiunta.';
        foreach ($d['aree'] as $a) {
            $f = $uscite(fn($r) => $r['aree'][$a['k']]['fascia'] ?? '');
            foreach ($a['fasce'] as $x) if (!in_array($x['k'], $f, true)) $mai[] = 'La fascia «' . $x['nome'] . '» dell\'area «' . $a['nome'] . '» non viene mai raggiunta.';
        }
        $rg = $uscite(fn($r) => $r['regola']);
        foreach ($d['regole'] as $x) if (!in_array($x['k'], $rg, true)) $mai[] = 'La regola «' . $x['nome'] . '» non vale mai.';
    }
    return ['fisse' => $fisse, 'conta' => $conta, 'quanti' => $quanti, 'mai' => $mai];
}

// ------------------------------------------------------------
// Dopo il test: email, lista, automazioni
// ------------------------------------------------------------

/** Fa partire l'innesco "ha completato il test" delle automazioni per un contatto attivo (o lo ricorda fino alla conferma). */
function test_innesca(int $iscritto, array $t, array $esiti): void {
    if (!$iscritto) return;
    require_once dirname(__DIR__, 3) . '/includes/newsletter-flussi.php';
    if (!function_exists('newsletter_flussi_innesco')) return;
    $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
    $st->execute([$iscritto]);
    if ($st->fetchColumn() === 'attivo') { newsletter_flussi_innesco('test', (int)$t['id'], $iscritto, $esiti); return; }
    test_schema();
    db()->prepare('REPLACE INTO ' . tbl('test_attese') . ' (iscritto_id, test_id, esiti, creato) VALUES (?, ?, ?, NOW())')->execute([$iscritto, (int)$t['id'], mb_substr(implode(' ', $esiti), 0, 1000)]);
}

/** Alla conferma dell'iscrizione (evento newsletter_confermata): partono gli inneschi dei test fatti prima di confermare. */
function test_alla_conferma(array $esito): void {
    test_schema();
    db()->exec('DELETE FROM ' . tbl('test_attese') . ' WHERE creato < DATE_SUB(NOW(), INTERVAL 40 DAY)');
    $st = db()->prepare('SELECT * FROM ' . tbl('test_attese') . ' WHERE iscritto_id = ?');
    $st->execute([(int)$esito['iscritto']]);
    $righe = $st->fetchAll();
    db()->prepare('DELETE FROM ' . tbl('test_attese') . ' WHERE iscritto_id = ?')->execute([(int)$esito['iscritto']]);
    require_once dirname(__DIR__, 3) . '/includes/newsletter-flussi.php';
    foreach ($righe as $x) newsletter_flussi_innesco('test', (int)$x['test_id'], (int)$esito['iscritto'], array_values(array_filter(explode(' ', (string)$x['esiti']))));
}

// ------------------------------------------------------------
// Modelli pronti (testi scritti per plumeCMS, non tratti da test pubblicati)
// ------------------------------------------------------------

function test_modelli(): array {
    require_once __DIR__ . '/modelli.php';
    return test_modelli_pronti();
}
