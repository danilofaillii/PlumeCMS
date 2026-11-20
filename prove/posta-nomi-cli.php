<?php
/**
 * 2.40.1 (prova-posta-nomi.py): manda email con i nomi difficili nel From, nel To e nel Reply-To, da ogni strada della posta,
 * con le funzioni vere del CMS. Il controllo dei messaggi arrivati lo fa prova-posta-nomi.py con email.headerregistry di Python.
 * I nomi arrivano in un file JSON (lista di testi); ogni email porta l'indice del nome nell'oggetto ("nome 3").
 * Lascia dati di prova (impostazioni di posta, mittenti "@nomi-prova.it", un form "Prova nomi"): va su un sito da buttare.
 *
 * uso: php prove/posta-nomi-cli.php <cartella CMS> smtp <porta smtp> <nomi.json>        From da Configurazione email, To, Reply-To
 *      php prove/posta-nomi-cli.php <cartella CMS> mittente <porta smtp> <nomi.json>    From del mittente scelto (Consegna), con SMTP
 *      php -d sendmail_path="python3 -I prove/sendmail-finto.py <cartella>" prove/posta-nomi-cli.php <cartella CMS> mail - <nomi.json>
 *      php prove/posta-nomi-cli.php <cartella CMS> brevo|ses|mailgun <porta api finta> <nomi.json>
 *      php prove/posta-nomi-cli.php <cartella CMS> form <porta smtp> <nomi.json>        avvisi del form e della richiesta di prenotazione
 *      php prove/posta-nomi-cli.php <cartella CMS> funzioni - <nomi.json>               posta_nome_indirizzo() e il ritorno, in JSON
 *      php prove/posta-nomi-cli.php <cartella CMS> pulisci
 * Le chiavi delle API sono quelle di prove/consegna-comune.js (CHIAVI), il server finto è prove/api-posta-finta.py.
 */
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REMOTE_ADDR'] = '127.0.0.9';
[, $dir, $cosa] = $argv;
$porta = $argv[3] ?? '';
if (in_array($cosa, ['brevo', 'ses', 'mailgun'], true) && !defined('CMS_POSTA_API_PROVA')) define('CMS_POSTA_API_PROVA', 'http://127.0.0.1:' . (int)$porta);
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/posta.php';
require_once $dir . '/includes/moduli.php';
consegna_schema();

$nomi = isset($argv[4]) ? json_decode((string)file_get_contents($argv[4]), true) : [];
$smtp = fn(string $nome, string $host = '127.0.0.1') => save_setting('posta_smtp', json_encode(['host' => $host, 'porta' => (int)$porta, 'sicurezza' => 'nessuna',
    'utente' => '', 'password' => '', 'mittente' => 'sito@nomi-prova.it', 'nome' => $nome], JSON_UNESCAPED_UNICODE));
// Partenza pulita: niente servizio con le API, niente mittente predefinito di Consegna.
$riparti = function () {
    consegna_config_salva(['servizio' => 'smtp']);
    db()->exec('UPDATE ' . tbl('posta_mittenti') . ' SET predefinito = 0');
};
$intest = fn(string $nome) => moduli_intestazioni('risposte@nomi-prova.it', $nome) . 'X-Plume-A: ' . base64_encode($nome) . "\r\n";
$esito = [];

if ($cosa === 'pulisci') {
    $riparti();
    db()->exec('DELETE FROM ' . tbl('posta_mittenti') . " WHERE email LIKE '%@nomi-prova.it'");
    save_setting('posta_smtp', '');
    echo "ok\n"; exit;
}

if ($cosa === 'funzioni') {
    foreach ($nomi as $i => $n) {
        $h = posta_nome_indirizzo($n, 'sito@nomi-prova.it');
        $esito[] = ['i' => $i, 'intestazione' => $h, 'ritorno' => posta_nome_da_intestazione($h), 'pulito' => posta_nome_pulito($n), 'to' => posta_to('a@nomi-prova.it', $n)];
    }
    echo json_encode($esito, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit;
}

if ($cosa === 'smtp' || $cosa === 'mail') {
    $riparti();
    foreach ($nomi as $i => $n) {
        $smtp($n, $cosa === 'smtp' ? '127.0.0.1' : '');
        $esito[] = posta_invia("dest$i@nomi-prova.it", "nome $i", '<p>Prova dei nomi</p>', $intest($n), 'prova') ? 'ok' : posta_errore();
    }
}

if ($cosa === 'mittente') {
    $riparti();
    $smtp('Configurazione');
    foreach ($nomi as $i => $n) {
        $email = "mitt$i@nomi-prova.it";
        db()->prepare('DELETE FROM ' . tbl('posta_mittenti') . ' WHERE email = ?')->execute([$email]);
        // Il nome pulito come lo salva consegna_mittente_aggiungi() (niente caratteri a 4 byte, a capo, < >: la colonna è utf8mb3).
        $n = mb_substr(trim(preg_replace('/[\x{10000}-\x{10FFFF}\r\n<>]/u', '', $n)), 0, 100);
        db()->prepare('INSERT INTO ' . tbl('posta_mittenti') . ' (email, nome, rispondi_a, creato, verificato_at) VALUES (?, ?, ?, NOW(), NOW())')->execute([$email, $n, '']);
        $id = (int)db()->lastInsertId();
        // Come una campagna: From del mittente scelto nelle intestazioni, che posta_smtp() rilegge con consegna_mittente_per().
        $esito[] = posta_invia("dest$i@nomi-prova.it", "nome $i", '<p>Prova dei nomi</p>', moduli_intestazioni() . consegna_intestazioni_mittente($id), 'prova') ? 'ok' : posta_errore();
    }
}

if (in_array($cosa, ['brevo', 'ses', 'mailgun'], true)) {
    $riparti();
    $c = consegna_config();
    $c['servizio'] = $cosa;
    $c['brevo']['chiave'] = consegna_cifra('xkeysib-' . str_repeat('a1b2c3d4', 8) . '-ProvaBrevo123456');
    $c['ses'] = ['regione' => 'eu-west-1', 'id_chiave' => 'AKIAPROVAPROVA123456', 'segreto' => consegna_cifra('Prova/Segreta+Amazon' . str_repeat('X', 20)), 'set' => '', 'argomento' => '', 'salvata' => ''];
    $c['mailgun'] = ['dominio' => 'mg.nomi-prova.it', 'regione' => 'eu', 'chiave' => consegna_cifra('key-' . str_repeat('f0e1d2c3', 4)), 'firma' => '', 'salvata' => ''];
    consegna_config_salva($c);
    foreach ($nomi as $i => $n) {
        $smtp($n, '');
        $esito[] = posta_invia("dest$i@nomi-prova.it", "nome $i", '<p>Prova dei nomi</p>', $intest($n), 'prova') ? 'ok' : posta_errore();
    }
    $riparti();
}

if ($cosa === 'form') {
    $riparti();
    $smtp('Prova nomi');
    save_setting('notifica_email', 'negozio@nomi-prova.it');
    $risolvi = function (): string {
        $s = moduli_captcha_sfida();
        for ($n = 0; $n <= $s['max']; $n++) if (hash('sha256', $s['sale'] . $n) === $s['sfida']) break;
        return base64_encode(json_encode($s + ['n' => $n]));
    };
    require_once $dir . '/includes/form.php';
    require_once $dir . '/includes/richiesta-prenotazione.php';
    form_schema();
    $id = (int)db()->query('SELECT id FROM ' . tbl('moduli') . " WHERE slug = 'prova-nomi'")->fetchColumn();
    if (!$id) $id = form_salva(0, ['nome' => 'Prova nomi', 'slug' => 'prova-nomi', 'attivo' => 1, 'avviso' => 1, 'campi' => [
        ['etichetta' => 'Nome', 'tipo' => 'testo', 'obbligatorio' => 1], ['etichetta' => 'Email', 'tipo' => 'email', 'obbligatorio' => 1]]]);
    $m = form_leggi($id);
    foreach ($nomi as $i => $n) {
        // Il form: Reply-To con il nome di chi ha compilato. Il campo testo del form taglia a 255 e non accetta gli a capo.
        db()->exec('DELETE FROM ' . tbl('moduli_invii') . ' WHERE ip = ' . db()->quote(moduli_ip()));
        $p = ['privacy' => '1', 'captcha' => $risolvi()];
        foreach ($m['campi'] as $c) $p['c_' . $c['nome']] = $c['tipo'] === 'email' ? "form$i@nomi-prova.it" : $n;
        $esito[] = 'form ' . $i . ': ' . (form_ricevi($m, $p, '/contatti/') ?: 'ok');
        // La richiesta di prenotazione: Reply-To con il nome di chi chiede.
        db()->exec('DELETE FROM ' . tbl('moduli_invii') . ' WHERE ip = ' . db()->quote(moduli_ip()));
        [$b, $f] = rp_firma(['modalita' => 'tavolo', 'max' => 20, 'a' => ['negozio@nomi-prova.it'], 'titolo' => 'Cena']);
        $esito[] = 'richiesta ' . $i . ': ' . (rp_ricevi(['rp_cfg' => $b, 'rp_firma' => $f, 'data' => date('Y-m-d', strtotime('+3 days')), 'ora' => '20:30', 'persone' => '4',
            'nome' => $n, 'email' => "richiesta$i@nomi-prova.it", 'privacy' => '1', 'captcha' => $risolvi()], '/prenota/') ?: 'ok');
    }
    db()->exec('DELETE FROM ' . tbl('moduli_invii') . ' WHERE ip = ' . db()->quote(moduli_ip()));
}

echo json_encode($esito, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
