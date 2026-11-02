<?php
/**
 * Aiuti da riga di comando per prova-form-vecchi.js (2.16.0): i form di prima, seminati su una 2.15.0 e confrontati dopo
 * l'aggiornamento. Usa solo funzioni che c'erano già nella 2.15.0 (gira su tutte e due le versioni). Mai nel prodotto.
 * uso: php prove/form-vecchi-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"            righe in JSON ({p} diventa il prefisso delle tabelle)
 *   semina <porta smtp>      posta verso il server finto; il file riservato "vecchio-regalo"; le liste "FV ..." e cinque form "FV ...":
 *                            contatti con l'avviso a due indirizzi, iscrizione con doppia conferma, file e email di conferma
 *                            scritta a mano, iscrizione senza conferma con file, tutti i tipi di campo, iscrizione con nome e
 *                            cognome; stampa gli id
 *   html <id>                form_html() del form, come in una pagina
 *   ricevi <id> <json post>  form_ricevi() con un captcha risolto davvero: stampa il codice ('' = riuscito) e l'esito del file
 *   togli <prefisso>         toglie i contatti <prefisso>-...@fv-prova.it, i loro invii e le consegne (per rifare un giro)
 *   sblocca                  il limite di invii all'ora per indirizzo (le prove vengono tutte da 127.0.0.1)
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: form-vecchi-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.7';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
require_once $dir . '/includes/form.php';
require_once $dir . '/includes/scaricabili.php';
newsletter_schema();
form_schema();
$t = fn($n) => tbl($n);
$out = null;
switch ($cmd) {
case 'sql':
    $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
    $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
    break;
case 'semina':
    save_setting('posta_smtp', json_encode(['host' => '127.0.0.1', 'porta' => (int)$arg[0], 'sicurezza' => 'nessuna', 'utente' => '', 'password' => '', 'mittente' => 'sito@esempio.it', 'nome' => 'Studio Prova']));
    save_setting('newsletter_config', json_encode(['per_ora' => 50000, 'per_giorno' => 0, 'rispondi_a' => '']));
    $notifica = function_exists('posta_notifica_email') ? posta_notifica_email() : '';
    if ($notifica === '') save_setting('notifica_email', 'avvisi@fv-prova.it');
    scaricabili_installa();
    $d = scaricabili_dir_riservati();
    if (!is_file("$d/vecchio-regalo.pdf")) file_put_contents("$d/vecchio-regalo.pdf", "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    scaricabili_salva_info('vecchio-regalo', ['titolo' => 'Il regalo di prima', '_file' => 'vecchio-regalo.pdf']);
    $lista = function (string $n): int {
        $id = (int)db()->query('SELECT id FROM ' . tbl('newsletter_liste') . ' WHERE nome = ' . db()->quote($n))->fetchColumn();
        if ($id) return $id;
        db()->prepare('INSERT INTO ' . tbl('newsletter_liste') . ' (nome, descrizione, creato) VALUES (?, \'\', NOW())')->execute([$n]);
        return (int)db()->lastInsertId();
    };
    $LA = $lista('FV Ebook'); $LB = $lista('FV Clienti');
    $c = fn($et, $tipo, $obb = false, $meta = '', $op = '') => ['etichetta' => $et, 'tipo' => $tipo, 'obbligatorio' => $obb, 'meta' => $meta, 'opzioni' => $op, 'segnaposto' => ''];
    $base = ['attivo' => 1, 'grazie_titolo' => 'Grazie FV', 'grazie_testo' => "Ricevuto.\nA presto.", 'grazie_link' => '/blog/'];
    $forms = [
        'contatti' => ['nome' => 'FV Contatti', 'titolo' => 'Scrivici', 'intro' => 'Rispondiamo in giornata.', 'pulsante' => 'Manda', 'consenso' => 'Accetto il trattamento dei dati.',
            'campi' => [$c('Nome', 'testo', true, 'nome'), $c('Email', 'email', true, 'email'), $c('Messaggio', 'testolungo')], 'avviso' => 1, 'avviso_a' => 'uno@fv-avvisi.it, due@fv-avvisi.it', 'lista_id' => 0],
        'doppia' => ['nome' => 'FV Ebook', 'titolo' => 'L\'ebook gratis', 'intro' => '', 'pulsante' => 'Voglio l\'ebook', 'consenso' => '',
            'campi' => [$c('Nome', 'testo', false, 'nome'), $c('Email', 'email', true, 'email')], 'avviso' => 0, 'lista_id' => $LA, 'lista_conferma' => 1, 'omaggio' => 'vecchio-regalo',
            'ce' => ['oggetto' => '{nome}, conferma per {lista} &#x1F389;', 'titolo' => 'Manca un clic', 'testo' => "Ciao {nome},\nconferma per avere il regalo.", 'pulsante' => 'Confermo', 'colore' => '#0a7d32']],
        'subito' => ['nome' => 'FV Subito', 'titolo' => '', 'intro' => 'Entra subito.', 'pulsante' => 'Iscrivimi', 'consenso' => '',
            'campi' => [$c('Email', 'email', true, 'email')], 'avviso' => 0, 'lista_id' => $LA, 'lista_conferma' => 0, 'omaggio' => 'vecchio-regalo'],
        'tutti' => ['nome' => 'FV Tutti i campi', 'titolo' => 'Richiesta', 'intro' => '', 'pulsante' => 'Invia', 'consenso' => '',
            'campi' => [$c('Nome', 'testo', true, 'nome'), $c('Cognome', 'testo', false, 'cognome'), $c('Email', 'email', true, 'email'), $c('Telefono', 'telefono', false, 'cellulare'),
                $c('Servizio', 'scelta', true, '', "Taglio\nColore"), $c('Giorni', 'multiscelta', false, '', 'Lunedì, Martedì'), $c('Ho letto le condizioni', 'spunta', true),
                $c('Data', 'data'), $c('Ora', 'ora'), $c('Persone', 'numero'), $c('Note', 'testolungo')], 'avviso' => 1, 'lista_id' => 0],
        'clienti' => ['nome' => 'FV Clienti', 'titolo' => 'Area clienti', 'intro' => '', 'pulsante' => 'Iscriviti', 'consenso' => '',
            'campi' => [$c('Nome', 'testo', true, 'nome'), $c('Cognome', 'testo', true, 'cognome'), $c('Email', 'email', true, 'email')], 'avviso' => 0, 'lista_id' => $LB, 'lista_conferma' => 1],
    ];
    $out = ['liste' => [$LA, $LB]];
    foreach ($forms as $k => $f) {
        $id = (int)db()->query('SELECT id FROM ' . tbl('moduli') . ' WHERE nome = ' . db()->quote($f['nome']))->fetchColumn();
        if (isset($f['ce'])) $f['ce'] = array_map(fn($x) => html_entity_decode($x, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $f['ce']);
        $out[$k] = form_salva($id, $f + $base);
    }
    break;
case 'html':
    $f = form_leggi((int)$arg[0]);
    echo form_html($f, '/prova/');
    exit;
case 'ricevi':
    $f = form_leggi((int)$arg[0]);
    $p = json_decode((string)$arg[1], true) ?: [];
    $s = moduli_captcha_sfida();
    for ($n = 0; $n <= $s['max']; $n++) if (hash('sha256', $s['sale'] . $n) === $s['sfida']) break;
    $s['n'] = $n;
    $p['captcha'] = base64_encode(json_encode($s));
    $p['privacy'] = '1';
    $p['sito_web'] = '';
    $r = form_ricevi($f, $p, '/prova/');
    $out = ['esito' => $r, 'omaggio' => form_ultimo_omaggio()];
    break;
case 'togli':
    $pre = preg_replace('/[^a-z]/', '', (string)$arg[0]);
    $ids = db()->query('SELECT id FROM ' . tbl('newsletter_iscritti') . " WHERE email LIKE '{$pre}-%@fv-prova.it'")->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) { db()->exec('DELETE FROM ' . tbl('scaricabili_consegne') . ' WHERE iscritto_id IN (' . implode(',', array_map('intval', $ids)) . ')'); newsletter_togli_iscritti($ids); }
    db()->exec('DELETE FROM ' . tbl('moduli_invii') . " WHERE email LIKE '{$pre}-%@fv-prova.it'");
    $out = count($ids);
    break;
case 'sblocca':
    db()->exec('UPDATE ' . tbl('moduli_invii') . ' SET aggiornato = aggiornato - INTERVAL 2 HOUR WHERE aggiornato > NOW() - INTERVAL 2 HOUR');
    db()->exec('UPDATE ' . tbl('newsletter_iscritti') . " SET aggiornato = aggiornato - INTERVAL 2 HOUR WHERE email LIKE '%@fv-prova.it' AND aggiornato > NOW() - INTERVAL 2 HOUR");
    $out = true;
    break;
default:
    fwrite(STDERR, "comando sconosciuto: $cmd\n");
    exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
