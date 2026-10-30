<?php
/**
 * Form costruiti dal pannello: campi a scelta, consenso, lista newsletter in cui finire, avviso via email,
 * messaggio di ringraziamento. Si mettono nelle pagine con il widget Modulo dell'editor visuale,
 * e arrivano dallo stesso ingresso dei moduli fissi (api/modulo.php, con "modulo=form:ID").
 *
 *   <prefisso>moduli         la definizione: nome, slug, campi (JSON), cosa succede dopo
 *   <prefisso>moduli_invii   ogni compilazione, con i dati in JSON
 */

require_once __DIR__ . '/moduli.php';
require_once __DIR__ . '/newsletter.php';

const FORM_PAGINA_GRAZIE = '/grazie/';
const FORM_TIPI_CAMPO = ['testo' => 'Testo breve', 'email' => 'Email', 'telefono' => 'Telefono', 'testolungo' => 'Testo lungo', 'scelta' => 'Scelta singola (menu)', 'multiscelta' => 'Scelta multipla (caselle)', 'spunta' => 'Casella da spuntare', 'data' => 'Data', 'numero' => 'Numero'];
// Nomi che il modulo usa già per sé: un campo non può chiamarsi così.
const FORM_NOMI_RISERVATI = ['modulo', 'pagina', 'captcha', 'privacy', 'sito_web', '_csrf', 'popup', 'newsletter', 'f'];

function form_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $tabelle = [
        'moduli' => 'CREATE TABLE IF NOT EXISTS ' . tbl('moduli') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(100) NOT NULL,
            `titolo` VARCHAR(255) NOT NULL DEFAULT '',
            `intro` TEXT,
            `campi` MEDIUMTEXT,
            `pulsante` VARCHAR(100) NOT NULL DEFAULT 'Invia',
            `consenso` VARCHAR(500) NOT NULL DEFAULT '',
            `lista_id` INT(11) NOT NULL DEFAULT 0,
            `lista_conferma` TINYINT(1) NOT NULL DEFAULT 1,
            `avviso` TINYINT(1) NOT NULL DEFAULT 1,
            `avviso_a` VARCHAR(500) NOT NULL DEFAULT '',
            `omaggio` VARCHAR(130) NOT NULL DEFAULT '',
            `grazie_titolo` VARCHAR(255) NOT NULL DEFAULT 'Grazie, ricevuto.',
            `grazie_testo` TEXT,
            `grazie_link` VARCHAR(500) NOT NULL DEFAULT '',
            `attivo` TINYINT(1) NOT NULL DEFAULT 1,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'moduli_invii' => 'CREATE TABLE IF NOT EXISTS ' . tbl('moduli_invii') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `modulo_id` INT(11) NOT NULL,
            `email` VARCHAR(190) NOT NULL DEFAULT '',
            `nome` VARCHAR(200) NOT NULL DEFAULT '',
            `dati` MEDIUMTEXT,
            `pagina` VARCHAR(190) NOT NULL DEFAULT '',
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `letto` TINYINT(1) NOT NULL DEFAULT 0,
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `modulo` (`modulo_id`, `id`), KEY `ip` (`ip`, `aggiornato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME);
    if (get_setting('form_schema_impronta', '') !== $impronta) {
        foreach ($tabelle as $sql) db()->exec($sql);
        // 1.9.0: a chi va l'avviso di ogni form. Le tabelle nate prima non hanno la colonna.
        $col = db()->query('SHOW COLUMNS FROM ' . tbl('moduli'))->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('avviso_a', $col, true)) db()->exec('ALTER TABLE ' . tbl('moduli') . " ADD COLUMN `avviso_a` VARCHAR(500) NOT NULL DEFAULT '' AFTER `avviso`");
        // 1.11.0: il file riservato che il form dà dopo la conferma dell'email.
        if (!in_array('omaggio', $col, true)) db()->exec('ALTER TABLE ' . tbl('moduli') . " ADD COLUMN `omaggio` VARCHAR(130) NOT NULL DEFAULT '' AFTER `avviso_a`");
        save_setting('form_schema_impronta', $impronta);
    }
}

/** I form, attivi e no. */
function form_tutti(): array {
    form_schema();
    return db()->query('SELECT m.*, (SELECT COUNT(*) FROM ' . tbl('moduli_invii') . ' i WHERE i.modulo_id = m.id) AS invii, (SELECT COUNT(*) FROM ' . tbl('moduli_invii') . ' i WHERE i.modulo_id = m.id AND i.letto = 0) AS nuovi FROM ' . tbl('moduli') . ' m ORDER BY m.nome')->fetchAll();
}

function form_leggi(int $id): ?array {
    form_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('moduli') . ' WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch() ?: null;
    if ($m) {
        $m['campi'] = form_campi_validi(json_decode((string)$m['campi'], true) ?: []);
        foreach (['intro', 'grazie_testo', 'titolo', 'consenso', 'grazie_titolo', 'grazie_link', 'pulsante', 'avviso_a', 'omaggio'] as $k) $m[$k] = (string)($m[$k] ?? '');
    }
    return $m;
}

/** Le opzioni di una scelta: una per riga, oppure separate da virgola quando stanno su una riga sola. */
function form_opzioni(mixed $o): array {
    if (is_array($o)) $righe = array_map('strval', $o);
    else {
        $o = (string)$o;
        $righe = preg_match('/\R/', $o) ? preg_split('/\R/', $o) : explode(',', $o);
    }
    $out = [];
    foreach ($righe as $r) {
        $r = mb_substr(trim($r), 0, 150);
        if ($r !== '' && !in_array($r, $out, true)) $out[] = $r;
    }
    return array_slice($out, 0, 100);
}

/** I campi ripuliti: nome come chiave, tipo conosciuto, opzioni per le scelte. */
function form_campi_validi($campi): array {
    $out = []; $visti = []; $etichette = [];
    foreach ((array)$campi as $c) {
        if (!is_array($c)) continue;
        $etichetta = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($c['etichetta'] ?? ''))), 0, 150);
        if ($etichetta === '') continue;
        $tipo = is_string($c['tipo'] ?? null) && isset(FORM_TIPI_CAMPO[$c['tipo']]) ? $c['tipo'] : 'testo';
        // Il nome tecnico: quello che arriva, oppure dall'etichetta senza accenti. Mai due uguali.
        $nome = preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim((string)($c['nome'] ?? ''))));
        if (trim((string)$nome, '_') === '') {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $etichetta);
            $nome = preg_replace('/[^a-z0-9]+/', '_', strtolower($ascii !== false ? $ascii : $etichetta));
        }
        $nome = mb_substr(trim((string)$nome, '_'), 0, 40) ?: 'campo';
        if (in_array($nome, FORM_NOMI_RISERVATI, true) || ctype_digit($nome)) $nome = 'campo_' . $nome;
        $base = $nome; $k = 2;
        while (isset($visti[$nome])) $nome = $base . '_' . $k++;
        $visti[$nome] = true;
        // Due campi con la stessa etichetta: la seconda si distingue, così nei dati non si coprono.
        $et = $etichetta; $k = 2;
        while (isset($etichette[mb_strtolower($et)])) $et = $etichetta . ' (' . $k++ . ')';
        $etichette[mb_strtolower($et)] = true;
        $out[] = [
            'nome' => $nome, 'etichetta' => $et, 'tipo' => $tipo,
            'obbligatorio' => !empty($c['obbligatorio']) && $c['obbligatorio'] !== 'false',
            'segnaposto' => mb_substr(trim((string)($c['segnaposto'] ?? '')), 0, 150),
            'opzioni' => in_array($tipo, ['scelta', 'multiscelta'], true) ? form_opzioni($c['opzioni'] ?? '') : [],
            'meta' => in_array($tipo, ['testo', 'email', 'telefono'], true) && in_array($c['meta'] ?? '', ['nome', 'cognome', 'email', 'cellulare'], true) ? $c['meta'] : '',
        ];
    }
    return $out;
}

/**
 * Gli indirizzi a cui va l'avviso di un form (1.9.0): separati da virgole, punti e virgola, spazi o a capo, al massimo
 * FORM_AVVISO_MAX, senza doppioni. Un indirizzo sbagliato ferma il salvataggio e si dice quale.
 */
const FORM_AVVISO_MAX = 5;
function form_avviso_indirizzi(mixed $testo): array {
    $out = [];
    foreach (preg_split('/[\s,;]+/', is_scalar($testo) ? (string)$testo : '', -1, PREG_SPLIT_NO_EMPTY) as $e) {
        if (!filter_var($e, FILTER_VALIDATE_EMAIL) || mb_strlen($e) > 190) throw new RuntimeException("«{$e}» non è un indirizzo email valido (Dopo l'invio, Manda l'avviso a).");
        $out[strtolower($e)] = $e;
    }
    if (count($out) > FORM_AVVISO_MAX) throw new RuntimeException("L'avviso va al massimo a " . FORM_AVVISO_MAX . ' indirizzi.');
    return array_values($out);
}

/** A chi parte l'avviso di questo form: i suoi indirizzi, oppure quello degli avvisi del sito (Impostazioni). */
function form_avviso_destinatari(array $m): array {
    try { $a = form_avviso_indirizzi($m['avviso_a'] ?? ''); } catch (RuntimeException $e) { $a = []; }
    if (!$a && posta_notifica_email() !== '') $a = [posta_notifica_email()];
    return $a;
}

function form_salva(int $id, array $d): int {
    form_schema();
    $nome = mb_substr(trim((string)($d['nome'] ?? '')), 0, 100);
    if ($nome === '') throw new RuntimeException('Il form ha bisogno di un nome.');
    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)(($d['slug'] ?? '') ?: $nome)) ?: $nome));
    $slug = mb_substr(trim($slug, '-'), 0, 100) ?: 'form';
    $c = db()->prepare('SELECT COUNT(*) FROM ' . tbl('moduli') . ' WHERE slug = ? AND id <> ?');
    $c->execute([$slug, $id]);
    if ($c->fetchColumn()) throw new RuntimeException("C'è già un form con il nome breve «{$slug}».");
    $campi = form_campi_validi(is_string($d['campi'] ?? null) ? json_decode($d['campi'], true) : ($d['campi'] ?? []));
    if (!$campi) throw new RuntimeException('Aggiungi almeno un campo, con la sua etichetta.');
    foreach ($campi as $c) if (in_array($c['tipo'], ['scelta', 'multiscelta'], true) && !$c['opzioni']) throw new RuntimeException('Il campo «' . $c['etichetta'] . '» è una scelta: scrivi le opzioni tra cui scegliere.');
    $emails = array_filter($campi, fn($x) => $x['tipo'] === 'email');
    $v = [
        'nome' => $nome, 'slug' => $slug, 'titolo' => mb_substr(trim((string)($d['titolo'] ?? '')), 0, 255), 'intro' => trim((string)($d['intro'] ?? '')),
        'campi' => json_encode($campi, JSON_UNESCAPED_UNICODE), 'pulsante' => mb_substr(trim((string)($d['pulsante'] ?? '')), 0, 100) ?: 'Invia',
        'consenso' => mb_substr(trim((string)($d['consenso'] ?? '')), 0, 500), 'lista_id' => (int)($d['lista_id'] ?? 0),
        'lista_conferma' => !empty($d['lista_conferma']) ? 1 : 0, 'avviso' => !empty($d['avviso']) ? 1 : 0,
        'avviso_a' => implode(', ', form_avviso_indirizzi($d['avviso_a'] ?? '')),
        'omaggio' => trim((string)($d['omaggio'] ?? '')),
        'grazie_titolo' => mb_substr(trim((string)($d['grazie_titolo'] ?? '')), 0, 255) ?: 'Grazie, ricevuto.', 'grazie_testo' => trim((string)($d['grazie_testo'] ?? '')),
        'grazie_link' => mb_substr(trim((string)($d['grazie_link'] ?? '')), 0, 500), 'attivo' => !empty($d['attivo']) ? 1 : 0,
    ];
    if ($v['lista_id'] && !$emails) throw new RuntimeException('Per iscrivere a una lista serve un campo di tipo Email.');
    // 1.11.0: il file da dare dopo la conferma è un file riservato di Download, e serve una lista (chi lo riceve è un iscritto).
    if ($v['omaggio'] !== '') {
        require_once __DIR__ . '/scaricabili.php';
        $x = scaricabili_elenco()[$v['omaggio']] ?? null;
        if (!$x || !$x['riservato']) throw new RuntimeException('Il file da dare dopo la conferma deve essere un file riservato di Download (Download, carica con «Riservato»).');
        if (!$v['lista_id']) throw new RuntimeException('Per dare un file serve una lista: scegli in quale lista iscrivere chi compila il form.');
    }
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($v)));
        $p = []; foreach ($v as $k => $x) $p[":$k"] = $x; $p[':id'] = $id;
        db()->prepare('UPDATE ' . tbl('moduli') . " SET $set, aggiornato = NOW() WHERE id = :id")->execute($p);
        return $id;
    }
    $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($v)));
    $segna = implode(', ', array_map(fn($k) => ":$k", array_keys($v)));
    $p = []; foreach ($v as $k => $x) $p[":$k"] = $x;
    db()->prepare('INSERT INTO ' . tbl('moduli') . " ($cols, creato, aggiornato) VALUES ($segna, NOW(), NOW())")->execute($p);
    return (int)db()->lastInsertId();
}

function form_elimina(int $id): void {
    db()->prepare('DELETE FROM ' . tbl('moduli_invii') . ' WHERE modulo_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('moduli') . ' WHERE id = ?')->execute([$id]);
}

// ------------------------------------------------------------
// Sul sito
// ------------------------------------------------------------

/** Il messaggio per un codice d'errore e, se c'è, il campo da segnare (etichetta oppure tipo). */
function form_errore(string $codice): array {
    if (str_starts_with($codice, 'campo:')) { $et = mb_substr(substr($codice, 6), 0, 150); return ['Controlla il campo «' . $et . '».', $et]; }
    $msg = MODULI_ERRORI[$codice] ?? '';
    return [$msg, $msg !== '' && in_array($codice, ['email', 'cellulare'], true) ? ($codice === 'email' ? 'tipo:email' : 'tipo:telefono') : ''];
}

/** Il form in HTML, con le stesse difese dei moduli fissi (captcha, campo esca, consenso). $campoErrato segna il campo sbagliato. */
function form_html(array $m, string $percorso, string $errore = '', string $campoErrato = ''): string {
    $campi = ''; $id = (int)$m['id'];
    foreach ($m['campi'] as $c) {
        $n = 'c_' . $c['nome'];
        $errato = $campoErrato !== '' && ($campoErrato === $c['etichetta'] || $campoErrato === 'tipo:' . $c['tipo']);
        $req = $c['obbligatorio'] ? ' required' : '';
        $inv = $errato ? ' aria-invalid="true"' : '';
        $ph = $c['segnaposto'] !== '' ? ' placeholder="' . h($c['segnaposto']) . '"' : '';
        $cl = ($errato ? ' errato' : '') . (in_array($c['tipo'], ['testolungo', 'multiscelta', 'spunta'], true) ? ' form-largo' : '');
        $et = '<span>' . h($c['etichetta']) . ($c['obbligatorio'] ? '' : ' <small>(facoltativo)</small>') . '</span>';
        $apri = trim($cl) !== '' ? '<label class="' . trim($cl) . '">' : '<label>';
        switch ($c['tipo']) {
            case 'testolungo': $campi .= "$apri$et<textarea name=\"$n\" rows=\"4\" maxlength=\"3000\"$req$ph$inv></textarea></label>"; break;
            case 'scelta':
                $campi .= "$apri$et<select name=\"$n\"$req$inv><option value=\"\">Scegli…</option>" . implode('', array_map(fn($o) => '<option value="' . h($o) . '">' . h($o) . '</option>', $c['opzioni'])) . '</select></label>'; break;
            case 'multiscelta':
                $campi .= '<fieldset class="form-largo multiscelta' . ($errato ? ' errato' : '') . '"' . $inv . '><legend>' . h($c['etichetta']) . ($c['obbligatorio'] ? '' : ' <small>(facoltativo)</small>') . '</legend>'
                    . implode('', array_map(fn($o) => '<label class="spunta"><input type="checkbox" name="' . $n . '[]" value="' . h($o) . '"> <span>' . h($o) . '</span></label>', $c['opzioni'])) . '</fieldset>'; break;
            case 'spunta': $campi .= '<label class="spunta' . trim($cl) . '"><input type="checkbox" name="' . $n . '" value="1"' . $req . $inv . '> <span>' . h($c['etichetta']) . ($c['obbligatorio'] ? '' : ' <small>(facoltativo)</small>') . '</span></label>'; break;
            case 'email': $campi .= "$apri$et<input type=\"email\" name=\"$n\" autocomplete=\"email\" maxlength=\"190\"$req$ph$inv></label>"; break;
            case 'telefono': $campi .= "$apri$et<input type=\"tel\" name=\"$n\" autocomplete=\"tel\" maxlength=\"30\"$req$ph$inv></label>"; break;
            case 'data': $campi .= "$apri$et<input type=\"date\" name=\"$n\"$req$inv></label>"; break;
            case 'numero': $campi .= "$apri$et<input type=\"number\" name=\"$n\" step=\"any\"$req$ph$inv></label>"; break;
            default: $campi .= "$apri$et<input type=\"text\" name=\"$n\" maxlength=\"300\"" . ($c['meta'] === 'nome' ? ' autocomplete="given-name"' : ($c['meta'] === 'cognome' ? ' autocomplete="family-name"' : '')) . "$req$ph$inv></label>";
        }
    }
    $consenso = $m['consenso'] !== '' ? h($m['consenso']) : 'Ho letto l&#039;<a href="/privacy-policy/" target="_blank">informativa privacy</a> e accetto il trattamento dei miei dati per questa richiesta.';
    $titoloId = "modulo-titolo-f$id";
    return '<section class="modulo" id="modulo-f' . $id . '" data-form="' . $id . '"' . ($m['titolo'] !== '' ? ' aria-labelledby="' . $titoloId . '"' : ' aria-label="' . h($m['nome']) . '"') . '>' . ($m['titolo'] !== '' ? '<h2 id="' . $titoloId . '">' . h($m['titolo']) . '</h2>' : '') . ($m['intro'] !== '' ? '<p>' . nl2br(h($m['intro'])) . '</p>' : '')
        . ($errore !== '' ? '<p class="modulo-errore" role="alert">' . h($errore) . '</p>' : '')
        . '<form method="post" action="' . cms_base() . '/api/modulo.php" data-captcha>'
        . '<input type="hidden" name="modulo" value="form:' . $id . '"><input type="hidden" name="pagina" value="' . h($percorso) . '"><input type="hidden" name="captcha" value="">'
        . '<div class="modulo-campi">' . $campi . '</div>'
        . '<div class="esca" aria-hidden="true"><label>Sito web <input name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<label class="spunta"><input type="checkbox" name="privacy" value="1" required> <span>' . $consenso . '</span></label>'
        . '<label class="captcha"><input type="checkbox"> <span>Sono una persona</span> <span class="captcha-stato" aria-live="polite"></span></label>'
        . '<button class="btn btn-viola" type="submit">' . h($m['pulsante']) . '</button>'
        . '</form><script src="' . (function_exists('sito_versione_asset') ? sito_versione_asset('moduli.js') : cms_base() . '/sito/assets/moduli.js') . '" defer></script></section>';
}

/** Riceve una compilazione. Ritorna '' oppure il codice d'errore (MODULI_ERRORI o 'campo:NOME'). */
function form_ricevi(array $m, array $p, string $pagina): string {
    form_schema();
    if (empty($m['attivo'])) return 'robot';
    if (($p['sito_web'] ?? '') !== '') return 'robot';
    $dati = []; $email = ''; $nome = [];
    foreach ($m['campi'] as $c) {
        $v = $p['c_' . $c['nome']] ?? '';
        if ($c['tipo'] === 'multiscelta') {
            $scelte = array_values(array_unique(array_map(fn($x) => trim((string)$x), is_array($v) ? $v : ($v === '' ? [] : [$v]))));
            foreach ($scelte as $x) if (!in_array($x, $c['opzioni'], true)) return 'campo:' . $c['etichetta'];
            $v = implode(', ', $scelte);
        }
        $v = is_array($v) ? '' : trim((string)$v);
        $v = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $v);
        if ($c['tipo'] === 'spunta') $v = $v !== '' ? 'sì' : '';
        if ($c['obbligatorio'] && $v === '') return 'campo:' . $c['etichetta'];
        if ($v !== '') {
            if ($c['tipo'] === 'email' && (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 190)) return 'email';
            if ($c['tipo'] === 'telefono' && !preg_match('/^\+?[0-9 .\/()-]{6,30}$/', $v)) return 'cellulare';
            if ($c['tipo'] === 'scelta' && !in_array($v, $c['opzioni'], true)) return 'campo:' . $c['etichetta'];
            if ($c['tipo'] === 'data' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return 'campo:' . $c['etichetta'];
            if ($c['tipo'] === 'numero' && !is_numeric(str_replace(',', '.', $v))) return 'campo:' . $c['etichetta'];
        }
        $dati[$c['etichetta']] = mb_substr($v, 0, $c['tipo'] === 'testolungo' ? 3000 : 300);
        if ($c['tipo'] === 'email' && $email === '') $email = mb_strtolower($v);
        if ($c['meta'] === 'nome' || $c['meta'] === 'cognome') $nome[$c['meta']] = $v;
    }
    if (empty($p['privacy'])) return 'privacy';
    if (!moduli_captcha_valido((string)($p['captcha'] ?? ''))) return 'captcha';
    if (moduli_troppi('moduli_invii')) return 'troppi';
    // 1.11.0: con la doppia conferma, il limite delle richieste di iscrizione si controlla prima di salvare: la persona
    // vede l'errore di sempre, invece del grazie senza l'email di conferma.
    if ((int)$m['lista_id'] > 0 && $email !== '' && !empty($m['lista_conferma']) && newsletter_stato_email($email) !== 'attivo' && newsletter_troppe_richieste()) return 'troppi';
    $ora = moduli_adesso();
    $nomeIntero = trim(($nome['nome'] ?? '') . ' ' . ($nome['cognome'] ?? ''));
    if ($nomeIntero === '') foreach ($dati as $k => $v) { if ($v !== '' && preg_match('/nome/i', $k)) { $nomeIntero = $v; break; } }
    db()->prepare('INSERT INTO ' . tbl('moduli_invii') . ' (modulo_id, email, nome, dati, pagina, ip, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([(int)$m['id'], $email, mb_substr($nomeIntero, 0, 200), json_encode($dati, JSON_UNESCAPED_UNICODE), mb_substr($pagina, 0, 190), moduli_ip(), $ora, $ora]);
    $invioId = (int)db()->lastInsertId();
    // Nella lista della newsletter, con o senza doppia conferma.
    if ((int)$m['lista_id'] > 0 && $email !== '') {
        try {
            $attivoPrima = newsletter_stato_email($email) === 'attivo';
            $r = form_iscrivi($email, $nome['nome'] ?? '', $nome['cognome'] ?? '', (int)$m['lista_id'], (bool)$m['lista_conferma'], $m);
            if ($r !== '') error_log("form: iscrizione con conferma non riuscita ($r)");
            // 1.11.0: il file del form. Con la conferma da fare arriva dopo la conferma; chi è già iscritto lo riceve
            // subito per email (non sulla pagina: da qui nessuno prende il file scrivendo l'email di un altro);
            // senza conferma, per email e anche sulla pagina di ringraziamento.
            if (($m['omaggio'] ?? '') !== '' && $r === '') {
                if (!empty($m['lista_conferma']) && !$attivoPrima) form_ultimo_omaggio(['stato' => 'conferma']);
                else {
                    $link = form_omaggio_consegna($m, newsletter_id_da_email($email), $email);
                    $k = $link !== '' ? (string)parse_url($link, PHP_URL_QUERY) : '';
                    form_ultimo_omaggio(empty($m['lista_conferma']) && $k !== '' ? ['stato' => 'link', 'k' => substr($k, 2)] : ['stato' => 'email']);
                }
            }
        } catch (Throwable $e) { error_log('form lista: ' . $e->getMessage()); }
    }
    if (!empty($m['avviso'])) {
        // 1.9.0: agli indirizzi scelti nel form, uno per uno; senza, a quello degli avvisi del sito.
        foreach (form_avviso_destinatari($m) as $a) {
            $righe = implode('', array_map(fn($k, $v) => '<p style="margin:0 0 10px"><b>' . h($k) . ':</b> ' . nl2br(h($v)) . '</p>', array_keys($dati), $dati));
            [$oggetto, $corpo] = ['Nuova compilazione: ' . $m['nome'], moduli_email_html('Form «' . $m['nome'] . '»', $righe . '<p style="color:#888;font-size:13px">Dalla pagina ' . h($pagina) . ', il ' . date('d/m/Y H:i') . '.</p>', 'Apri nel pannello', cms_url_pannello() . 'form-submissions.php?modulo=' . (int)$m['id'])];
            if (!posta_invia($a, $oggetto, $corpo, moduli_intestazioni($email), 'avviso_form')) error_log('form: avviso non partito: ' . posta_errore());
        }
    }
    // L'innesco "Compila un form": subito per chi è attivo; per chi deve ancora confermare si ricorda e parte alla conferma.
    newsletter_flussi_innesco('form', (int)$m['id'], $email !== '' ? newsletter_id_da_email($email) : 0);
    return '';
}

/**
 * Un iscritto in una lista, da un form: con conferma manda l'email di conferma (1.11.0: per la lista del form, non per
 * la newsletter principale, con il titolo del form nell'email; entra nella lista solo quando conferma), senza entra
 * subito attivo. Chi è già attivo entra subito nella lista. Ritorna '' oppure il codice d'errore di newsletter_richiesta().
 */
function form_iscrivi(string $email, string $nome, string $cognome, int $lista, bool $conferma, array $m = []): string {
    newsletter_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE email = ?');
    $st->execute([$email]);
    $i = $st->fetch();
    $ora = moduli_adesso();
    if ($i && $i['stato'] === 'attivo') { newsletter_in_lista((int)$i['id'], $lista); return ''; }
    if ($conferma) {
        $cosa = trim((string)($m['titolo'] ?? '')) ?: trim((string)($m['nome'] ?? '')) ?: newsletter_nome_lista($lista);
        return newsletter_richiesta(['nome' => $nome, 'cognome' => $cognome, 'email' => $email], ['lista' => $lista, 'form' => (int)($m['id'] ?? 0), 'cosa' => $cosa, 'fonte' => 'form']);
    }
    if (!$i) {
        db()->prepare('INSERT INTO ' . tbl('newsletter_iscritti') . " (email, nome, cognome, stato, fonte, consenso_versione, consenso_at, ip, creato, aggiornato) VALUES (?,?,?,'attivo','form',?,?,?,?,?)")
            ->execute([$email, mb_substr($nome, 0, 100), mb_substr($cognome, 0, 100), MODULI_CONSENSO_VERSIONE, $ora, moduli_ip(), $ora, $ora]);
    } else {
        db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'attivo', consenso_at = ?, uscito_at = NULL, rimbalzato_at = NULL, errori_di_fila = 0, aggiornato = ? WHERE id = ?")->execute([$ora, $ora, $i['id']]);
    }
    $id = newsletter_id_da_email($email);
    newsletter_in_lista($id, $lista);
    newsletter_flussi_innesco('lista', $lista, $id);
    return '';
}

/** Pagina di ringraziamento di un form (dopo l'invio). $o e $k: come va il file del form (form_ultimo_omaggio, nell'indirizzo). */
function form_grazie_html(array $m, string $o = '', string $k = ''): string {
    $piu = '';
    if (($m['omaggio'] ?? '') !== '') {
        require_once __DIR__ . '/scaricabili.php';
        $x = scaricabili_elenco()[$m['omaggio']] ?? null;
        $titolo = $x ? h($x['titolo']) : 'il file';
        if ($o === 'conferma') $piu = '<p class="form-omaggio"><b>Ultimo passo:</b> ti abbiamo mandato un&#039;email. Apri il link per confermare il tuo indirizzo e ricevi subito «' . $titolo . '». Se non la vedi, guarda nello spam.</p>';
        elseif ($o === 'email') $piu = '<p class="form-omaggio">Sei già nella lista: ti abbiamo mandato per email il link per scaricare «' . $titolo . '».</p>';
        elseif ($o === 'link' && $x && scaricabili_verifica_chiave($m['omaggio'], $k)) {
            $piu = '<p class="form-omaggio">Ecco «' . $titolo . '». Ti abbiamo mandato il link anche per email.</p><p><a class="btn btn-viola" href="' . h(rtrim(cms_url_sito(), '/') . '/scarica/' . $m['omaggio'] . '/?k=' . $k) . '">Scarica</a></p>';
        }
    }
    return vista_cortesia('Ricevuto', h($m['grazie_titolo']), ($m['grazie_testo'] !== '' ? nl2br(h($m['grazie_testo'])) : 'Ti rispondo appena posso.') . $piu, $m['grazie_link'] !== '' ? '<a class="btn btn-viola" href="' . h($m['grazie_link']) . '">Continua</a>' : '');
}

/** Come va il file del form nell'invio appena ricevuto (lo legge api/modulo.php per la pagina di ringraziamento). */
function form_ultimo_omaggio(?array $nuovo = null): ?array {
    static $e = null;
    if ($nuovo !== null) $e = $nuovo;
    return $e;
}

/**
 * Dà il file del form a un iscritto: il link personale (scaricabili_link_personale, registra la consegna) e l'email con il
 * pulsante Scarica. Ritorna il link ('' se il file non c'è più o non è riservato).
 */
function form_omaggio_consegna(array $m, int $iscritto, string $email): string {
    require_once __DIR__ . '/scaricabili.php';
    $nome = (string)($m['omaggio'] ?? '');
    $x = scaricabili_elenco()[$nome] ?? null;
    if ($nome === '' || !$x || !$x['riservato'] || $iscritto <= 0) return '';
    $link = scaricabili_link_personale($nome, $iscritto);
    if ($link === '') return '';
    $corpo = '<p>Ecco il file che hai chiesto: <b>' . h($x['titolo']) . '</b>.</p><p>Il link è personale e vale ' . scaricabili_giorni() . ' giorni.</p>';
    if (!posta_invia($email, 'Ecco il tuo file: ' . $x['titolo'], moduli_email_html($x['titolo'], $corpo, 'Scarica', $link), moduli_intestazioni(), 'omaggio')) error_log('form: email del file non partita: ' . posta_errore());
    return $link;
}

/** La pagina di conferma per chi ha confermato l'email da un form (non la newsletter): con il file del form, se c'è. */
function form_conferma_html(array $m, string $link): string {
    $lista = newsletter_nome_lista((int)$m['lista_id']);
    if ($link !== '') {
        require_once __DIR__ . '/scaricabili.php';
        $x = scaricabili_elenco()[$m['omaggio']] ?? null;
        return vista_cortesia('Email confermata', 'Ecco il tuo file.', '<p>Grazie: il tuo indirizzo è confermato. Scarica «' . h($x['titolo'] ?? 'il file') . '» qui sotto; ti abbiamo mandato il link anche per email (vale ' . scaricabili_giorni() . ' giorni).</p>',
            '<p><a class="btn btn-viola" href="' . h($link) . '">Scarica</a></p>');
    }
    return vista_cortesia('Email confermata', 'Fatto, sei dentro.', '<p>Grazie: il tuo indirizzo è confermato' . ($lista !== '' ? ' e sei nella lista «' . h($lista) . '»' : '') . '.</p>');
}
