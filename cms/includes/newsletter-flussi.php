<?php
/**
 * Newsletter, parte seconda: aperture e clic delle email, e i flussi automatici.
 *
 * Aperture e clic: ogni email porta un'immagine di un pixel e i link passano da api/nl.php, che segna
 * l'evento in newdf_newsletter_eventi e rimanda alla pagina vera. Il conteggio è per persona (una apertura
 * conta una volta). Chi blocca le immagini non risulta: i numeri sono per difetto, come ovunque.
 *
 * Flussi: un grafo. Parte da un innesco (iscrizione, ingresso in una lista, form compilato, apertura o clic di
 * una campagna, nessuna interazione da tanti giorni) e passa di nodo in nodo lungo gli archi: email (con più
 * uscite: subito dopo, se apre, se clicca, se non fa niente entro un tempo), attesa, bivio su condizione, fine.
 * Ogni persona in cammino è una riga di newdf_newsletter_corsi: il nodo dove sta, quando si muove, se è in
 * ascolto di un'apertura o un clic. Le email le manda lo stesso cron della newsletter, dentro lo stesso limite;
 * aperture e clic la spostano subito (newsletter_flusso_evento). Un flusso vecchio, scritto come sequenza,
 * si converte in grafo alla lettura (newsletter_flusso_grafo).
 */

const NL_INNESCHI = ['iscrizione' => 'Si iscrive alla newsletter (conferma fatta)', 'lista' => 'Entra in una lista', 'form' => 'Compila un form', 'apertura' => 'Apre una campagna', 'clic' => 'Fa clic in una campagna', 'inattivo' => 'Non apre e non clicca niente da tanti giorni'];
/** Le condizioni di un bivio. 'id' dice cosa chiede la seconda casella: una lista, un'email del flusso, un numero di giorni. */
const NL_CONDIZIONI = [
    'in_lista' => ['Sta in una lista', 'lista'], 'non_in_lista' => ['Non sta in una lista', 'lista'],
    'aperta' => ["Ha aperto un'email di questo flusso", 'email'], 'non_aperta' => ["Non ha aperto un'email di questo flusso", 'email'],
    'cliccata' => ["Ha cliccato in un'email di questo flusso", 'email'], 'non_cliccata' => ["Non ha cliccato in un'email di questo flusso", 'email'],
    'inattivo' => ['Non apre e non clicca niente da almeno tanti giorni', 'giorni'], 'attivo' => ['Ha aperto o cliccato qualcosa negli ultimi tanti giorni', 'giorni'],
    'iscritto_da' => ['È iscritto da almeno tanti giorni', 'giorni'],
];
/** Le uscite di ogni tipo di nodo, con l'etichetta che si legge sulla tela. */
const NL_USCITE = [
    'innesco' => ['poi' => 'Poi'],
    'email' => ['poi' => 'Subito dopo', 'aperta' => 'Se apre', 'cliccata' => 'Se clicca', 'nessuna' => 'Se non fa niente entro il tempo'],
    'attesa' => ['poi' => 'Poi'],
    'bivio' => ['si' => 'Sì', 'no' => 'No'],
    'fine' => [],
];

function newsletter_flussi_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $tabelle = [
        'newsletter_eventi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_eventi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `campagna_id` INT(11) NOT NULL DEFAULT 0,
            `flusso_id` INT(11) NOT NULL DEFAULT 0,
            `passo` INT(11) NOT NULL DEFAULT 0,
            `iscritto_id` INT(11) NOT NULL,
            `tipo` VARCHAR(10) NOT NULL,
            `url` VARCHAR(500) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `campagna` (`campagna_id`, `tipo`, `iscritto_id`), KEY `flusso` (`flusso_id`, `passo`, `iscritto_id`, `tipo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_flussi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_flussi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `attivo` TINYINT(1) NOT NULL DEFAULT 0,
            `innesco` VARCHAR(20) NOT NULL DEFAULT 'iscrizione',
            `innesco_id` INT(11) NOT NULL DEFAULT 0,
            `passi` MEDIUMTEXT,
            `inviate` INT(11) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `innesco` (`attivo`, `innesco`, `innesco_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'newsletter_corsi' => 'CREATE TABLE IF NOT EXISTS ' . tbl('newsletter_corsi') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `flusso_id` INT(11) NOT NULL,
            `iscritto_id` INT(11) NOT NULL,
            `passo` INT(11) NOT NULL DEFAULT 0,
            `prossimo` DATETIME DEFAULT NULL,
            `stato` VARCHAR(10) NOT NULL DEFAULT 'attivo',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `flusso_iscritto` (`flusso_id`, `iscritto_id`), KEY `pronti` (`stato`, `prossimo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME . 'grafo');
    if (get_setting('newsletter_flussi_impronta', '') !== $impronta) {
        foreach ($tabelle as $sql) db()->exec($sql);
        // Colonne arrivate con il grafo: in ascolto di cosa, e un contatore dei salti per fermare i giri infiniti.
        // Una per una: una tabella creata da un pacchetto precedente può averne una sola delle due.
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('newsletter_corsi'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('ascolto', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_corsi') . ' ADD COLUMN `ascolto` VARCHAR(10) DEFAULT NULL');
        if (!in_array('salti', $col, true)) db()->exec('ALTER TABLE ' . tbl('newsletter_corsi') . ' ADD COLUMN `salti` INT(11) NOT NULL DEFAULT 0');
        save_setting('newsletter_flussi_impronta', $impronta);
    }
}

// ------------------------------------------------------------
// Tracciamento
// ------------------------------------------------------------

/** La firma di un evento: nessuno può inventarsi aperture di altri. */
function nl_firma(int $c, int $f, int $p, int $i): string {
    return substr(hash_hmac('sha256', "$c|$f|$p|$i", moduli_segreto()), 0, 20);
}

/**
 * sicurezza (attacco 1): la firma dell'indirizzo di destinazione di un clic. Senza, chi riceve una newsletter poteva
 * cambiare u= e usare il dominio del sito per mandare la gente altrove. Il rimando esterno vale solo con questa firma.
 */
function nl_firma_url(int $c, int $f, int $p, int $i, string $url): string {
    return substr(hash_hmac('sha256', "u|$c|$f|$p|$i|$url", moduli_segreto()), 0, 24);
}

/** Il rimando verso $url e firmato per questo evento? (I link spediti prima del 26 ottobre non hanno k: valgono solo verso il sito.) */
function newsletter_url_firmato(int $c, int $f, int $p, int $i, string $url, string $k): bool {
    return $i > 0 && $k !== '' && $url !== '' && hash_equals(nl_firma_url($c, $f, $p, $i, $url), $k);
}

function nl_url_evento(string $azione, array $t, int $iscritto, string $url = ''): string {
    $c = (int)($t['c'] ?? 0); $f = (int)($t['f'] ?? 0); $p = (int)($t['p'] ?? 0);
    $q = ['a' => $azione, 'c' => $c, 'f' => $f, 'p' => $p, 'i' => $iscritto, 't' => nl_firma($c, $f, $p, $iscritto)];
    if ($url !== '') { $q['u'] = $url; $q['k'] = nl_firma_url($c, $f, $p, $iscritto, $url); }
    return MODULI_URL . cms_base() . '/api/nl.php?' . http_build_query($q);
}

/** Mette il pixel e riscrive i link dell'email. Il link di uscita resta diretto. */
function newsletter_traccia(string $html, array $t, int $iscritto): string {
    $html = preg_replace_callback('~href="(https?://[^"]+)"~i', function ($m) use ($t, $iscritto) {
        $u = html_entity_decode($m[1]);
        if (str_contains($u, cms_base() . '/api/nl.php') || str_contains($u, NEWSLETTER_ESCI)) return $m[0];
        return 'href="' . h(nl_url_evento('c', $t, $iscritto, $u)) . '"';
    }, $html);
    $pixel = '<img src="' . h(nl_url_evento('a', $t, $iscritto)) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">';
    return str_contains($html, '</body>') ? str_replace('</body>', $pixel . '</body>', $html) : $html . $pixel;
}

/** Segna un evento (dall'api). Ritorna true se la firma regge. */
function newsletter_evento(string $tipo, int $c, int $f, int $p, int $i, string $t, string $url = ''): bool {
    if (!$i || !hash_equals(nl_firma($c, $f, $p, $i), $t)) return false;
    newsletter_flussi_schema();
    $tipo = $tipo === 'clic' ? 'clic' : 'apertura';
    // Una riga per persona e tipo (per i clic, per indirizzo): i conteggi sono per persona.
    $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_eventi') . ' WHERE campagna_id = ? AND flusso_id = ? AND passo = ? AND iscritto_id = ? AND tipo = ? AND url = ? LIMIT 1');
    $st->execute([$c, $f, $p, $i, $tipo, mb_substr($url, 0, 500)]);
    if ($st->fetchColumn()) return true;
    db()->prepare('INSERT INTO ' . tbl('newsletter_eventi') . ' (campagna_id, flusso_id, passo, iscritto_id, tipo, url, creato) VALUES (?,?,?,?,?,?,NOW())')->execute([$c, $f, $p, $i, $tipo, mb_substr($url, 0, 500)]);
    // Un clic vale anche come apertura.
    if ($tipo === 'clic') newsletter_evento('apertura', $c, $f, $p, $i, $t);
    if ($c) newsletter_flussi_innesco($tipo, $c, $i);
    if ($f) newsletter_flusso_evento($f, $p, $i, $tipo);
    return true;
}

/** Aperture e clic per persona, di una campagna. */
function newsletter_conteggio_eventi(int $campagna): array {
    newsletter_flussi_schema();
    $st = db()->prepare('SELECT tipo, COUNT(DISTINCT iscritto_id) AS n FROM ' . tbl('newsletter_eventi') . ' WHERE campagna_id = ? GROUP BY tipo');
    $st->execute([$campagna]);
    $out = ['apertura' => 0, 'clic' => 0];
    foreach ($st->fetchAll() as $r) $out[$r['tipo']] = (int)$r['n'];
    return $out;
}

/** Le campagne inviate con i loro numeri, per la pagina Statistiche. */
function newsletter_statistiche_campagne(): array {
    newsletter_flussi_schema();
    $out = [];
    foreach (db()->query('SELECT c.*, (SELECT COUNT(DISTINCT e.iscritto_id) FROM ' . tbl('newsletter_eventi') . " e WHERE e.campagna_id = c.id AND e.tipo = 'apertura') AS aperture, (SELECT COUNT(DISTINCT e.iscritto_id) FROM " . tbl('newsletter_eventi') . " e WHERE e.campagna_id = c.id AND e.tipo = 'clic') AS clic FROM " . tbl('newsletter_campagne') . " c WHERE c.stato IN ('inviata','in_invio','sospesa') ORDER BY c.invio_at DESC, c.id DESC")->fetchAll() as $c) {
        $c['tasso_aperture'] = $c['inviate'] ? round($c['aperture'] * 100 / $c['inviate'], 1) : 0;
        $c['tasso_clic'] = $c['inviate'] ? round($c['clic'] * 100 / $c['inviate'], 1) : 0;
        $out[] = $c;
    }
    return $out;
}

/** I link più cliccati di una campagna. */
function newsletter_clic_per_link(int $campagna): array {
    $st = db()->prepare('SELECT url, COUNT(DISTINCT iscritto_id) AS n FROM ' . tbl('newsletter_eventi') . " WHERE campagna_id = ? AND tipo = 'clic' AND url <> '' GROUP BY url ORDER BY n DESC LIMIT 30");
    $st->execute([$campagna]);
    return $st->fetchAll();
}

/** Iscritti attivi nuovi per mese, ultimi 12 mesi. */
function newsletter_iscritti_per_mese(): array {
    return db()->query('SELECT DATE_FORMAT(creato, "%Y-%m") AS mese, COUNT(*) AS n, SUM(stato = "attivo") AS attivi, SUM(stato = "uscito") AS usciti FROM ' . tbl('newsletter_iscritti') . ' WHERE creato >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY mese ORDER BY mese')->fetchAll();
}

// ------------------------------------------------------------
// Flussi: il grafo
// ------------------------------------------------------------

function newsletter_flussi(): array {
    newsletter_flussi_schema();
    $out = [];
    foreach (db()->query('SELECT f.*, (SELECT COUNT(*) FROM ' . tbl('newsletter_corsi') . " c WHERE c.flusso_id = f.id AND c.stato = 'attivo') AS in_corso, (SELECT COUNT(*) FROM " . tbl('newsletter_corsi') . " c WHERE c.flusso_id = f.id AND c.stato = 'finito') AS finiti FROM " . tbl('newsletter_flussi') . ' f ORDER BY f.attivo DESC, f.nome')->fetchAll() as $f) {
        $f['nome'] = newsletter_4byte_fuori((string)$f['nome']);
        $f['grafo'] = newsletter_flusso_grafo($f);
        $out[] = $f;
    }
    return $out;
}

function newsletter_flusso(int $id): ?array {
    newsletter_flussi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_flussi') . ' WHERE id = ?');
    $st->execute([$id]);
    $f = $st->fetch() ?: null;
    if ($f) { $f['nome'] = newsletter_4byte_fuori((string)$f['nome']); $f['grafo'] = newsletter_flusso_grafo($f); }
    return $f;
}

/** Il grafo di una riga del database. Una sequenza vecchia (elenco di passi) diventa grafo qui. */
function newsletter_flusso_grafo(array $f): array {
    $g = json_decode((string)($f['passi'] ?? ''), true) ?: [];
    if (isset($g['nodi'])) return newsletter_flusso_grafo_valido($g);
    // Formato di prima: passi in fila, ognuno con attesa (giorni) e condizione sull'email precedente o su una lista.
    $nodi = [['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 40, 'dati' => []]];
    $archi = [];
    $da = ['nodo' => 0, 'uscita' => 'poi'];
    $n = 1; $y = 40; $ultimaEmail = 0;
    foreach ((array)$g as $p) {
        if (!is_array($p) || trim((string)($p['oggetto'] ?? '')) === '') continue;
        if ((int)($p['attesa'] ?? 0) > 0) {
            $y += 150;
            $nodi[] = ['id' => $n, 'tipo' => 'attesa', 'x' => 40, 'y' => $y, 'dati' => ['minuti' => (int)$p['attesa'] * 1440]];
            $archi[] = ['da' => $da['nodo'], 'uscita' => $da['uscita'], 'a' => $n];
            $da = ['nodo' => $n, 'uscita' => 'poi']; $n++;
        }
        $cond = (string)($p['condizione'] ?? 'sempre');
        $mappa = ['aperto' => ['aperta', $ultimaEmail], 'non_aperto' => ['non_aperta', $ultimaEmail], 'clic' => ['cliccata', $ultimaEmail], 'in_lista' => ['in_lista', (int)($p['condizione_id'] ?? 0)], 'non_in_lista' => ['non_in_lista', (int)($p['condizione_id'] ?? 0)]];
        $bivio = 0;
        if (isset($mappa[$cond]) && ($ultimaEmail || str_contains($cond, 'lista'))) {
            $y += 150;
            $nodi[] = ['id' => $n, 'tipo' => 'bivio', 'x' => 40, 'y' => $y, 'dati' => ['condizione' => $mappa[$cond][0], 'valore' => $mappa[$cond][1]]];
            $archi[] = ['da' => $da['nodo'], 'uscita' => $da['uscita'], 'a' => $n];
            $bivio = $n; $n++;
        }
        $y += 150;
        $nodi[] = ['id' => $n, 'tipo' => 'email', 'x' => $bivio ? 360 : 40, 'y' => $y, 'dati' => ['oggetto' => $p['oggetto'], 'anteprima' => $p['anteprima'] ?? '', 'corpo' => $p['corpo'] ?? '', 'pulsante_testo' => $p['pulsante_testo'] ?? '', 'pulsante_link' => $p['pulsante_link'] ?? '', 'ascolto' => 0]];
        if ($bivio) {
            $archi[] = ['da' => $bivio, 'uscita' => 'si', 'a' => $n];
            // Il "no" del bivio salta l'email e raggiunge quello che viene dopo: lo si chiude al giro successivo.
            $da = ['nodo' => $n, 'uscita' => 'poi', 'anche' => ['nodo' => $bivio, 'uscita' => 'no']];
        } else {
            $archi[] = ['da' => $da['nodo'], 'uscita' => $da['uscita'], 'a' => $n];
            $da = ['nodo' => $n, 'uscita' => 'poi'];
        }
        if (!empty($da['anche']) && $da['nodo'] !== $n) { $archi[] = ['da' => $da['anche']['nodo'], 'uscita' => $da['anche']['uscita'], 'a' => $n]; unset($da['anche']); }
        $ultimaEmail = $n; $n++;
    }
    return newsletter_flusso_grafo_valido(['nodi' => $nodi, 'archi' => $archi]);
}

/** Ripulisce un grafo arrivato dalla tela: nodi con tipo e dati noti, archi solo fra nodi esistenti e da uscite esistenti, una per uscita. */
function newsletter_flusso_grafo_valido($g): array {
    $g = is_array($g) ? $g : [];
    $nodi = []; $ids = [];
    foreach ((array)($g['nodi'] ?? []) as $n) {
        if (!is_array($n)) continue;
        $id = (int)($n['id'] ?? -1);
        $tipo = (string)($n['tipo'] ?? '');
        if ($id < 0 || isset($ids[$id]) || !isset(NL_USCITE[$tipo])) continue;
        if ($tipo === 'innesco' && $id !== 0) continue;
        if ($tipo !== 'innesco' && $id === 0) continue;
        $d = (array)($n['dati'] ?? []);
        // Ogni campo passa da una stringa: un valore strano arrivato dal browser (un elenco, un oggetto) non deve far cadere la pagina.
        $s = fn($k, $max) => mb_substr(trim(is_scalar($d[$k] ?? null) ? (string)$d[$k] : ''), 0, $max);
        $link = $s('pulsante_link', 500);
        if ($link !== '' && !preg_match('~^(https?://|mailto:|tel:)~i', $link)) $link = 'https://' . ltrim($link, '/');
        $cond = is_scalar($d['condizione'] ?? null) ? (string)$d['condizione'] : '';
        $dati = match ($tipo) {
            'email' => [
                'oggetto' => $s('oggetto', 255), 'anteprima' => $s('anteprima', 255),
                'corpo' => is_scalar($d['corpo'] ?? null) ? (string)$d['corpo'] : '', 'pulsante_testo' => $s('pulsante_testo', 100), 'pulsante_link' => $link,
                'ascolto' => max(0, min(365 * 1440, (int)($d['ascolto'] ?? 0))),
            ],
            'attesa' => ['minuti' => max(1, min(365 * 1440, (int)($d['minuti'] ?? 1440)))],
            'bivio' => ['condizione' => isset(NL_CONDIZIONI[$cond]) ? $cond : 'in_lista', 'valore' => max(0, (int)($d['valore'] ?? 0))],
            default => [],
        };
        $ids[$id] = true;
        $nodi[] = ['id' => $id, 'tipo' => $tipo, 'x' => max(0, min(6000, (int)($n['x'] ?? 0))), 'y' => max(0, min(6000, (int)($n['y'] ?? 0))), 'dati' => $dati];
    }
    if (!isset($ids[0])) array_unshift($nodi, ['id' => 0, 'tipo' => 'innesco', 'x' => 40, 'y' => 40, 'dati' => []]);
    $tipi = array_column($nodi, 'tipo', 'id');
    $archi = []; $visti = [];
    foreach ((array)($g['archi'] ?? []) as $a) {
        if (!is_array($a)) continue;
        $da = (int)($a['da'] ?? -1); $u = (string)($a['uscita'] ?? ''); $al = (int)($a['a'] ?? -1);
        if (!isset($tipi[$da], $tipi[$al]) || $da === $al || $al === 0 || !isset(NL_USCITE[$tipi[$da]][$u]) || isset($visti["$da|$u"])) continue;
        $visti["$da|$u"] = true;
        $archi[] = ['da' => $da, 'uscita' => $u, 'a' => $al];
    }
    return ['nodi' => $nodi, 'archi' => $archi];
}

/** Il nodo con questo id, o null. */
function newsletter_flusso_nodo(array $g, int $id): ?array {
    foreach ($g['nodi'] as $n) if ($n['id'] === $id) return $n;
    return null;
}

/** Dove porta un'uscita di un nodo: l'id del nodo di arrivo, o null se l'uscita è libera (il cammino finisce). */
function newsletter_flusso_arrivo(array $g, int $da, string $uscita): ?int {
    foreach ($g['archi'] as $a) if ($a['da'] === $da && $a['uscita'] === $uscita) return $a['a'];
    return null;
}

/** Le email del flusso, per i menu (bivi: "ha aperto l'email..."). */
function newsletter_flusso_email(array $g): array {
    $out = [];
    foreach ($g['nodi'] as $n) if ($n['tipo'] === 'email') $out[$n['id']] = $n['dati']['oggetto'] !== '' ? $n['dati']['oggetto'] : 'Email ' . $n['id'];
    return $out;
}

/** Il grafo com'è arrivato dal form (JSON nel campo nascosto; il base64 di campi-protetti.js lo scioglie già auth.php). */
function newsletter_flusso_grafo_dal_form($grafo): array {
    if (is_array($grafo)) return $grafo;
    $g = json_decode(is_scalar($grafo) ? trim((string)$grafo) : '', true);
    if (!is_array($g)) throw new RuntimeException('Il disegno del flusso non è arrivato intero. Riprova a salvare.');
    return $g;
}

function newsletter_flusso_salva(int $id, array $d): int {
    newsletter_flussi_schema();
    $nome = mb_substr(trim(is_scalar($d['nome'] ?? null) ? (string)$d['nome'] : ''), 0, 100);
    if ($nome === '') throw new RuntimeException('Il flusso ha bisogno di un nome.');
    $g = newsletter_flusso_grafo_valido(newsletter_flusso_grafo_dal_form($d['grafo'] ?? []));
    $email = array_filter($g['nodi'], fn($n) => $n['tipo'] === 'email');
    if (!$email) throw new RuntimeException('Metti almeno un blocco Email sulla tela.');
    foreach ($email as $n) if ($n['dati']['oggetto'] === '') throw new RuntimeException("Un'email è senza oggetto: aprila e scrivilo.");
    if (newsletter_flusso_arrivo($g, 0, 'poi') === null) throw new RuntimeException("Collega l'innesco al primo blocco: trascina dal suo pallino a un blocco.");
    foreach ($g['nodi'] as &$n) if ($n['tipo'] === 'email') $n['dati']['corpo'] = newsletter_4byte_dentro($n['dati']['corpo']);
    unset($n);
    $innescoScelto = is_scalar($d['innesco'] ?? null) ? (string)$d['innesco'] : '';
    $innesco = isset(NL_INNESCHI[$innescoScelto]) ? $innescoScelto : 'iscrizione';
    $innescoId = (int)($d['innesco_id'] ?? 0);
    if ($innesco === 'inattivo' && $innescoId < 1) throw new RuntimeException("Per l'innesco \"non fa niente da tanti giorni\" serve il numero di giorni.");
    // Le colonne sono utf8 a tre byte: un'emoji nel nome o nell'oggetto farebbe rifiutare la riga (o la taglierebbe).
    // Il nome passa dalle entità HTML, il grafo si salva in JSON con i caratteri fuori ASCII scritti come \uXXXX.
    $passi = json_encode($g, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($passi === false) throw new RuntimeException('Il flusso contiene caratteri che non si possono salvare: ' . json_last_error_msg());
    $v = ['nome' => mb_substr(newsletter_4byte_dentro($nome), 0, 100), 'attivo' => !empty($d['attivo']) ? 1 : 0, 'innesco' => $innesco, 'innesco_id' => $innescoId, 'passi' => $passi];
    if ($id) {
        db()->prepare('UPDATE ' . tbl('newsletter_flussi') . ' SET nome = :nome, attivo = :attivo, innesco = :innesco, innesco_id = :innesco_id, passi = :passi, aggiornato = NOW() WHERE id = :id')->execute($v + ['id' => $id]);
        return $id;
    }
    db()->prepare('INSERT INTO ' . tbl('newsletter_flussi') . ' (nome, attivo, innesco, innesco_id, passi, creato, aggiornato) VALUES (:nome, :attivo, :innesco, :innesco_id, :passi, NOW(), NOW())')->execute($v);
    return (int)db()->lastInsertId();
}

function newsletter_flusso_elimina(int $id): void {
    db()->prepare('DELETE FROM ' . tbl('newsletter_corsi') . ' WHERE flusso_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('newsletter_flussi') . ' WHERE id = ?')->execute([$id]);
}

/** Mette una persona in cammino su un flusso, al nodo dopo l'innesco. Una volta sola per flusso e persona. */
function newsletter_flusso_parti(array $f, int $iscritto): void {
    $g = $f['grafo'] ?? newsletter_flusso_grafo($f);
    $primo = newsletter_flusso_arrivo($g, 0, 'poi');
    if ($primo === null) return;
    db()->prepare('INSERT IGNORE INTO ' . tbl('newsletter_corsi') . " (flusso_id, iscritto_id, passo, prossimo, stato, ascolto, salti, creato, aggiornato) VALUES (?, ?, ?, NOW(), 'attivo', NULL, 0, NOW(), NOW())")->execute([(int)$f['id'], $iscritto, $primo]);
}

/** Un innesco: fa partire i flussi che lo aspettano, per questo iscritto. */
function newsletter_flussi_innesco(string $innesco, int $innescoId, int $iscritto): void {
    if (!$iscritto) return;
    newsletter_flussi_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_flussi') . ' WHERE attivo = 1 AND innesco = ? AND (innesco_id = 0 OR innesco_id = ?)');
    $st->execute([$innesco, $innescoId]);
    foreach ($st->fetchAll() as $f) newsletter_flusso_parti($f, $iscritto);
}

/** L'innesco "inattivo da N giorni": a ogni giro cerca gli iscritti attivi che non aprono e non cliccano da N giorni (e sono iscritti da almeno N giorni) e li mette in cammino. */
function newsletter_flussi_inattivi(): void {
    $st = db()->query('SELECT * FROM ' . tbl('newsletter_flussi') . " WHERE attivo = 1 AND innesco = 'inattivo' AND innesco_id > 0");
    foreach ($st->fetchAll() as $f) {
        $g = max(1, (int)$f['innesco_id']);
        $q = db()->prepare('SELECT i.id FROM ' . tbl('newsletter_iscritti') . " i WHERE i.stato = 'attivo' AND COALESCE(i.consenso_at, i.creato) <= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND NOT EXISTS (SELECT 1 FROM " . tbl('newsletter_eventi') . ' e WHERE e.iscritto_id = i.id AND e.creato > DATE_SUB(NOW(), INTERVAL ? DAY))
            AND NOT EXISTS (SELECT 1 FROM ' . tbl('newsletter_corsi') . ' c WHERE c.flusso_id = ? AND c.iscritto_id = i.id) LIMIT 200');
        $q->execute([$g, $g, (int)$f['id']]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $i) newsletter_flusso_parti($f, (int)$i);
    }
}

/** La condizione di un bivio vale per questa persona? */
function newsletter_flusso_condizione(array $f, array $nodo, int $iscritto): bool {
    $c = $nodo['dati']['condizione']; $v = (int)$nodo['dati']['valore'];
    switch ($c) {
        case 'in_lista': case 'non_in_lista':
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti_liste') . ' WHERE iscritto_id = ? AND lista_id = ?');
            $st->execute([$iscritto, $v]);
            $si = (int)$st->fetchColumn() > 0;
            return $c === 'in_lista' ? $si : !$si;
        case 'aperta': case 'non_aperta': case 'cliccata': case 'non_cliccata':
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_eventi') . ' WHERE flusso_id = ? AND passo = ? AND iscritto_id = ? AND tipo = ?');
            $st->execute([(int)$f['id'], $v, $iscritto, str_contains($c, 'clicc') ? 'clic' : 'apertura']);
            $si = (int)$st->fetchColumn() > 0;
            return str_starts_with($c, 'non_') ? !$si : $si;
        case 'inattivo': case 'attivo':
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_eventi') . ' WHERE iscritto_id = ? AND creato > DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$iscritto, max(1, $v)]);
            $si = (int)$st->fetchColumn() > 0;
            return $c === 'attivo' ? $si : !$si;
        case 'iscritto_da':
            $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ? AND COALESCE(consenso_at, creato) <= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$iscritto, max(0, $v)]);
            return (int)$st->fetchColumn() > 0;
    }
    return true;
}

/** Sposta un cammino lungo un'uscita: al nodo di arrivo (da elaborare subito) o alla fine se l'uscita è libera. */
function newsletter_flusso_vai(array $g, int $corso, int $da, string $uscita, int $salti): void {
    $a = newsletter_flusso_arrivo($g, $da, $uscita);
    if ($a === null || $salti > 60) {
        db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = 'finito', prossimo = NULL, ascolto = NULL, aggiornato = NOW() WHERE id = ?")->execute([$corso]);
        return;
    }
    db()->prepare('UPDATE ' . tbl('newsletter_corsi') . ' SET passo = ?, prossimo = NOW(), ascolto = NULL, salti = ?, aggiornato = NOW() WHERE id = ?')->execute([$a, $salti + 1, $corso]);
}

/** Un'apertura o un clic su un'email di un flusso: se la persona è in ascolto su quel nodo, parte dall'uscita giusta. */
function newsletter_flusso_evento(int $flusso, int $nodo, int $iscritto, string $tipo): void {
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_corsi') . " WHERE flusso_id = ? AND iscritto_id = ? AND passo = ? AND stato = 'attivo' AND ascolto = 'email'");
    $st->execute([$flusso, $iscritto, $nodo]);
    $c = $st->fetch();
    if (!$c) return;
    $f = newsletter_flusso($flusso);
    if (!$f) return;
    $g = $f['grafo'];
    $uscita = null;
    // Un clic segue "se clicca"; se quell'uscita è libera vale come apertura. Un'apertura con solo "se clicca" collegato resta in ascolto.
    if ($tipo === 'clic' && newsletter_flusso_arrivo($g, $nodo, 'cliccata') !== null) $uscita = 'cliccata';
    elseif (newsletter_flusso_arrivo($g, $nodo, 'aperta') !== null) $uscita = 'aperta';
    if ($uscita === null) return;
    newsletter_flusso_vai($g, (int)$c['id'], $nodo, $uscita, (int)$c['salti']);
}

/**
 * Un giro dei flussi: elabora i cammini arrivati alla loro ora, manda al massimo $posti email.
 * Un cammino scorre finché incontra un'attesa, un'email in ascolto, o la fine. Ritorna le righe di log.
 */
function newsletter_flussi_giro(int $posti): array {
    newsletter_flussi_schema();
    $log = [];
    if ($posti <= 0) return $log;
    newsletter_flussi_inattivi();
    $righe = db()->query('SELECT c.*, i.email, i.nome, i.cognome, i.stato AS stato_iscritto FROM ' . tbl('newsletter_corsi') . ' c JOIN ' . tbl('newsletter_iscritti') . " i ON i.id = c.iscritto_id WHERE c.stato = 'attivo' AND c.prossimo <= NOW() ORDER BY c.prossimo LIMIT " . (int)($posti * 3))->fetchAll();
    $flussi = [];
    $inviate = 0;
    $aggiorna = db()->prepare('UPDATE ' . tbl('newsletter_corsi') . ' SET passo = ?, prossimo = ?, ascolto = ?, salti = ?, aggiornato = NOW() WHERE id = ?');
    foreach ($righe as $r) {
        if ($inviate >= $posti) break;
        $f = $flussi[$r['flusso_id']] ??= newsletter_flusso((int)$r['flusso_id']);
        if (!$f || !$f['attivo'] || $r['stato_iscritto'] !== 'attivo') {
            db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = 'fermato', prossimo = NULL, ascolto = NULL, aggiornato = NOW() WHERE id = ?")->execute([$r['id']]);
            continue;
        }
        $g = $f['grafo'];
        $corso = (int)$r['id']; $iscritto = (int)$r['iscritto_id'];
        $nodoId = (int)$r['passo']; $salti = (int)$r['salti'];
        // In ascolto e il tempo è scaduto: nessuna interazione.
        if ($r['ascolto'] === 'email') {
            newsletter_flusso_vai($g, $corso, $nodoId, newsletter_flusso_arrivo($g, $nodoId, 'nessuna') !== null ? 'nessuna' : 'poi', $salti);
            $dopo = db()->query('SELECT passo, salti, stato FROM ' . tbl('newsletter_corsi') . " WHERE id = $corso")->fetch();
            if (!$dopo || $dopo['stato'] !== 'attivo') continue;
            $nodoId = (int)$dopo['passo']; $salti = (int)$dopo['salti'];
        }
        // Scorre i nodi finché il cammino si ferma.
        for ($passi = 0; $passi < 30; $passi++) {
            $nodo = newsletter_flusso_nodo($g, $nodoId);
            if (!$nodo || $nodo['tipo'] === 'fine' || $nodo['tipo'] === 'innesco') {
                db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = 'finito', prossimo = NULL, ascolto = NULL, aggiornato = NOW() WHERE id = ?")->execute([$corso]);
                break;
            }
            if ($nodo['tipo'] === 'attesa') {
                $a = newsletter_flusso_arrivo($g, $nodoId, 'poi');
                if ($a === null) { db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = 'finito', prossimo = NULL, aggiornato = NOW() WHERE id = ?")->execute([$corso]); break; }
                $aggiorna->execute([$a, date('Y-m-d H:i:s', time() + $nodo['dati']['minuti'] * 60), null, $salti + 1, $corso]);
                break;
            }
            if ($nodo['tipo'] === 'bivio') {
                $u = newsletter_flusso_condizione($f, $nodo, $iscritto) ? 'si' : 'no';
                $a = newsletter_flusso_arrivo($g, $nodoId, $u);
                if ($a === null || $salti > 60) { db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = 'finito', prossimo = NULL, aggiornato = NOW() WHERE id = ?")->execute([$corso]); break; }
                $nodoId = $a; $salti++;
                $aggiorna->execute([$nodoId, date('Y-m-d H:i:s'), null, $salti, $corso]);
                continue;
            }
            // Email.
            if ($inviate >= $posti) break; // resta dov'è, al prossimo giro
            $d = $nodo['dati'];
            $c = ['id' => 0, 'oggetto' => $d['oggetto'], 'anteprima' => $d['anteprima'], 'corpo' => $d['corpo'], 'pulsante_testo' => $d['pulsante_testo'], 'pulsante_link' => $d['pulsante_link']];
            [$oggetto, $html, $intest] = newsletter_email($c, ['id' => $iscritto, 'nome' => $r['nome'], 'email' => $r['email']], ['f' => (int)$f['id'], 'p' => $nodoId]);
            if (!posta_invia($r['email'], $oggetto, $html, $intest)) { $log[] = 'flusso ' . $f['nome'] . ': email non partita verso ' . $r['email'] . ' (' . posta_errore() . ')'; break; }
            $inviate++;
            db()->prepare('UPDATE ' . tbl('newsletter_flussi') . ' SET inviate = inviate + 1 WHERE id = ?')->execute([$f['id']]);
            $ascolta = $d['ascolto'] > 0 && (newsletter_flusso_arrivo($g, $nodoId, 'aperta') !== null || newsletter_flusso_arrivo($g, $nodoId, 'cliccata') !== null || newsletter_flusso_arrivo($g, $nodoId, 'nessuna') !== null);
            if ($ascolta) {
                $aggiorna->execute([$nodoId, date('Y-m-d H:i:s', time() + $d['ascolto'] * 60), 'email', $salti, $corso]);
                break;
            }
            $a = newsletter_flusso_arrivo($g, $nodoId, 'poi');
            if ($a === null) { db()->prepare('UPDATE ' . tbl('newsletter_corsi') . " SET stato = 'finito', prossimo = NULL, aggiornato = NOW() WHERE id = ?")->execute([$corso]); break; }
            $nodoId = $a; $salti++;
            $aggiorna->execute([$nodoId, date('Y-m-d H:i:s'), null, $salti, $corso]);
        }
    }
    if ($inviate) $log[] = "flussi: $inviate email mandate";
    return $log;
}
