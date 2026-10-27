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
        foreach (['intro', 'grazie_testo', 'titolo', 'consenso', 'grazie_titolo', 'grazie_link', 'pulsante'] as $k) $m[$k] = (string)($m[$k] ?? '');
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
        'grazie_titolo' => mb_substr(trim((string)($d['grazie_titolo'] ?? '')), 0, 255) ?: 'Grazie, ricevuto.', 'grazie_testo' => trim((string)($d['grazie_testo'] ?? '')),
        'grazie_link' => mb_substr(trim((string)($d['grazie_link'] ?? '')), 0, 500), 'attivo' => !empty($d['attivo']) ? 1 : 0,
    ];
    if ($v['lista_id'] && !$emails) throw new RuntimeException('Per iscrivere a una lista serve un campo di tipo Email.');
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
    $ora = moduli_adesso();
    $nomeIntero = trim(($nome['nome'] ?? '') . ' ' . ($nome['cognome'] ?? ''));
    if ($nomeIntero === '') foreach ($dati as $k => $v) { if ($v !== '' && preg_match('/nome/i', $k)) { $nomeIntero = $v; break; } }
    db()->prepare('INSERT INTO ' . tbl('moduli_invii') . ' (modulo_id, email, nome, dati, pagina, ip, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([(int)$m['id'], $email, mb_substr($nomeIntero, 0, 200), json_encode($dati, JSON_UNESCAPED_UNICODE), mb_substr($pagina, 0, 190), moduli_ip(), $ora, $ora]);
    $invioId = (int)db()->lastInsertId();
    // Nella lista della newsletter, con o senza doppia conferma.
    if ((int)$m['lista_id'] > 0 && $email !== '') {
        try { form_iscrivi($email, $nome['nome'] ?? '', $nome['cognome'] ?? '', (int)$m['lista_id'], (bool)$m['lista_conferma']); } catch (Throwable $e) { error_log('form lista: ' . $e->getMessage()); }
    }
    if (!empty($m['avviso'])) {
        $a = posta_notifica_email();
        if ($a !== '') {
            $righe = implode('', array_map(fn($k, $v) => '<p style="margin:0 0 10px"><b>' . h($k) . ':</b> ' . nl2br(h($v)) . '</p>', array_keys($dati), $dati));
            [$oggetto, $corpo] = ['Nuova compilazione: ' . $m['nome'], moduli_email_html('Form «' . $m['nome'] . '»', $righe . '<p style="color:#888;font-size:13px">Dalla pagina ' . h($pagina) . ', il ' . date('d/m/Y H:i') . '.</p>', 'Apri nel pannello', cms_url_pannello() . 'form-submissions.php?modulo=' . (int)$m['id'])];
            if (!posta_invia($a, $oggetto, $corpo, moduli_intestazioni($email))) error_log('form: avviso non partito: ' . posta_errore());
        }
    }
    newsletter_flussi_innesco('form', (int)$m['id'], $email !== '' ? newsletter_id_da_email($email) : 0);
    return '';
}

/** Un iscritto in una lista, da un form: con conferma manda l'email di conferma, senza entra subito attivo. */
function form_iscrivi(string $email, string $nome, string $cognome, int $lista, bool $conferma): void {
    newsletter_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE email = ?');
    $st->execute([$email]);
    $i = $st->fetch();
    $ora = moduli_adesso();
    if ($i && $i['stato'] === 'attivo') { newsletter_in_lista((int)$i['id'], $lista); return; }
    if ($conferma) {
        $r = newsletter_richiesta(['nome' => $nome, 'cognome' => $cognome, 'email' => $email]);
        if ($r !== '' && $r !== 'gia') error_log("form: iscrizione con conferma non riuscita ($r)");
        $id = newsletter_id_da_email($email);
        if ($id) newsletter_in_lista($id, $lista);
        return;
    }
    if (!$i) {
        db()->prepare('INSERT INTO ' . tbl('newsletter_iscritti') . " (email, nome, cognome, stato, fonte, consenso_versione, consenso_at, ip, creato, aggiornato) VALUES (?,?,?,'attivo','form',?,?,?,?,?)")
            ->execute([$email, mb_substr($nome, 0, 100), mb_substr($cognome, 0, 100), MODULI_CONSENSO_VERSIONE, $ora, moduli_ip(), $ora, $ora]);
    } else {
        db()->prepare('UPDATE ' . tbl('newsletter_iscritti') . " SET stato = 'attivo', consenso_at = ?, aggiornato = ? WHERE id = ?")->execute([$ora, $ora, $i['id']]);
    }
    $id = newsletter_id_da_email($email);
    newsletter_in_lista($id, $lista);
    newsletter_flussi_innesco('lista', $lista, $id);
}

/** Pagina di ringraziamento di un form (dopo l'invio). */
function form_grazie_html(array $m): string {
    return vista_cortesia('Ricevuto', h($m['grazie_titolo']), $m['grazie_testo'] !== '' ? nl2br(h($m['grazie_testo'])) : 'Ti rispondo appena posso.', $m['grazie_link'] !== '' ? '<a class="btn btn-viola" href="' . h($m['grazie_link']) . '">Continua</a>' : '');
}
