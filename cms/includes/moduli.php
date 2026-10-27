<?php
/**
 * I pezzi comuni dei moduli da compilare del sito (newsletter e form del costruttore): lettura e controllo dei campi,
 * captcha fatto in casa (prova di lavoro: il browser risolve un piccolo calcolo, niente servizi esterni), limite di
 * invii per indirizzo IP, impaginazione delle email. Le email partono da posta_invia() (SMTP della scheda Posta).
 * I moduli con un percorso loro (un download dopo la conferma, una richiesta da richiamare) li porta il modulo del sito.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/posta.php';

define('MODULI_URL', cms_url_sito()); // l'indirizzo del sito nelle email
const MODULI_CONSENSO_VERSIONE = '2026-10-23';
const MODULI_CONFERMA_GIORNI = 7;     // validità del link di conferma
const MODULI_LIMITE_ORA = 5;          // invii per indirizzo IP e per modulo, in un'ora
const MODULI_CAPTCHA_MAX = 60000;     // difficoltà: in media mezzo secondo nel browser
const MODULI_CAPTCHA_DURATA = 1200;   // secondi di validità di una sfida


function moduli_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $tabelle = [
        'captcha_usati' => 'CREATE TABLE IF NOT EXISTS ' . tbl('captcha_usati') . ' (
            `sale` CHAR(24) NOT NULL,
            `scade` DATETIME NOT NULL,
            PRIMARY KEY (`sale`), KEY `scade` (`scade`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    ];
    $impronta = md5(serialize($tabelle) . DB_NAME);
    if (get_setting('moduli_schema_impronta', '') !== $impronta) {
        foreach ($tabelle as $sql) db()->exec($sql);
        save_setting('moduli_schema_impronta', $impronta);
    }
    $fatto = true;
}

function moduli_adesso(): string {
    return date('Y-m-d H:i:s');
}

// ------------------------------------------------------------
// Captcha: prova di lavoro firmata
// ------------------------------------------------------------

function moduli_segreto(): string {
    $s = (string)get_setting('moduli_segreto', '');
    if (strlen($s) < 32) {
        $s = bin2hex(random_bytes(32));
        save_setting('moduli_segreto', $s);
    }
    return $s;
}

/** Una sfida: il browser cerca il numero n tale che sha256(sale . n) = sfida. */
function moduli_captcha_sfida(): array {
    $sale = bin2hex(random_bytes(12));
    $n = random_int(0, MODULI_CAPTCHA_MAX);
    $scade = time() + MODULI_CAPTCHA_DURATA;
    $sfida = hash('sha256', $sale . $n);
    $firma = hash_hmac('sha256', "$sale|$sfida|$scade|" . MODULI_CAPTCHA_MAX, moduli_segreto());
    return ['sale' => $sale, 'sfida' => $sfida, 'scade' => $scade, 'max' => MODULI_CAPTCHA_MAX, 'firma' => $firma];
}

/** Controlla la soluzione arrivata con il modulo. Ogni sfida vale una volta sola. */
function moduli_captcha_valido(string $risposta): bool {
    $r = json_decode(base64_decode($risposta, true) ?: '', true);
    if (!is_array($r)) return false;
    foreach (['sale', 'sfida', 'scade', 'max', 'firma', 'n'] as $k) if (!isset($r[$k])) return false;
    $sale = (string)$r['sale'];
    if (!preg_match('/^[a-f0-9]{24}$/', $sale) || (int)$r['scade'] < time()) return false;
    $attesa = hash_hmac('sha256', "$sale|{$r['sfida']}|{$r['scade']}|{$r['max']}", moduli_segreto());
    if (!hash_equals($attesa, (string)$r['firma'])) return false;
    if (!hash_equals((string)$r['sfida'], hash('sha256', $sale . (int)$r['n']))) return false;
    moduli_schema();
    db()->exec('DELETE FROM ' . tbl('captcha_usati') . ' WHERE scade < NOW()');
    try {
        db()->prepare('INSERT INTO ' . tbl('captcha_usati') . ' (sale, scade) VALUES (?, ?)')->execute([$sale, date('Y-m-d H:i:s', (int)$r['scade'])]);
    } catch (PDOException $e) {
        return false; // già usata
    }
    return true;
}

// ------------------------------------------------------------
// Dati del modulo
// ------------------------------------------------------------

/** Campi comuni ai moduli. Ritorna [dati, errore]. La newsletter chiede solo nome, cognome ed email. */
function moduli_leggi(array $p, bool $conCellulare = true): array {
    $d = [
        'nome' => trim((string)($p['nome'] ?? '')),
        'cognome' => trim((string)($p['cognome'] ?? '')),
        'email' => mb_strtolower(trim((string)($p['email'] ?? ''))),
        'cellulare' => trim((string)($p['cellulare'] ?? '')),
    ];
    if (($p['sito_web'] ?? '') !== '') return [$d, 'robot'];          // campo esca, invisibile alle persone
    if ($d['nome'] === '' || $d['cognome'] === '') return [$d, 'nome'];
    if (mb_strlen($d['nome']) > 100 || mb_strlen($d['cognome']) > 100) return [$d, 'nome'];
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 190) return [$d, 'email'];
    $cifre = preg_replace('/\D/', '', $d['cellulare']);
    if ($conCellulare && (!preg_match('/^\+?[0-9 .\/()-]{6,30}$/', $d['cellulare']) || strlen($cifre) < 8 || strlen($cifre) > 15)) return [$d, 'cellulare'];
    if (empty($p['privacy'])) return [$d, 'privacy'];
    if (!moduli_captcha_valido((string)($p['captcha'] ?? ''))) return [$d, 'captcha'];
    // Niente caratteri a 4 byte: la colonna è utf8 a 3 byte e li taglierebbe.
    foreach (['nome', 'cognome'] as $k) $d[$k] = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $d[$k]);
    return [$d, ''];
}

function moduli_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function moduli_troppi(string $tabella): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl($tabella) . ' WHERE ip = ? AND aggiornato > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $st->execute([moduli_ip()]);
    return (int)$st->fetchColumn() >= MODULI_LIMITE_ORA;
}

const MODULI_ERRORI = [
    'nome' => 'Scrivi nome e cognome.',
    'email' => "Controlla l'indirizzo email: sembra incompleto.",
    'cellulare' => 'Controlla il numero di cellulare, con il prefisso se è di un altro paese.',
    'privacy' => "Per andare avanti serve la spunta sull'informativa privacy.",
    'captcha' => 'Spunta la casella "Sono una persona" e aspetta il segno verde.',
    'troppi' => 'Hai già inviato diverse richieste. Riprova tra un\'ora.',
    'posta' => "L'email non è partita. Riprova tra qualche minuto.",
    'robot' => 'Richiesta non valida.',
];

// ------------------------------------------------------------
// Email
// ------------------------------------------------------------

function moduli_intestazioni(string $rispondiA = ''): string {
    return "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n" . ($rispondiA !== '' ? "Reply-To: $rispondiA\r\n" : '');
}

function moduli_email_html(string $titolo, string $corpo, string $pulsante = '', string $link = ''): string {
    $btn = $pulsante !== ''
        ? '<p style="margin:28px 0"><a href="' . h($link) . '" style="display:inline-block;background:#7c3aed;color:#fff;padding:14px 26px;border-radius:10px;text-decoration:none;font-weight:700">' . h($pulsante) . '</a></p>'
          . '<p style="font-size:12px;color:#888">Se il pulsante non si apre, copia questo indirizzo nel browser:<br><span style="word-break:break-all">' . h($link) . '</span></p>'
        : '';
    return '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"></head><body style="margin:0;background:#f4f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2330">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px">'
        . '<tr><td style="background:#060a17;color:#fff;padding:22px 30px;font-weight:700;letter-spacing:.06em">' . moduli_email_intestazione() . '</td></tr>'
        . '<tr><td style="padding:30px;font-size:16px;line-height:1.6"><h1 style="font-size:22px;margin:0 0 16px">' . h($titolo) . '</h1>' . $corpo . $btn . moduli_firma_html() . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** La riga in testa alle email del sito (HTML): il modulo del sito dà la sua, altrimenti il nome del sito in maiuscolo. */
function moduli_email_intestazione(): string {
    return (string)cms_impostazione('email_intestazione', mb_strtoupper(h((string)cms_impostazione('sito_nome', CMS_NOME))));
}

/** La firma in fondo alle email: l'impostazione email_firma, altrimenti il nome del sito. Sempre lo stesso HTML, così si può agganciare. */
function moduli_firma_html(): string {
    return '<p style="margin-top:28px">' . h((string)cms_impostazione('email_firma', cms_impostazione('sito_nome', CMS_NOME))) . '</p>';
}

/** Il nome del sito nelle frasi delle email ("su esempio.it"): l'host dell'indirizzo del sito. */
function moduli_host(): string {
    return (string)(parse_url(MODULI_URL, PHP_URL_HOST) ?: cms_impostazione('sito_nome', CMS_NOME));
}
