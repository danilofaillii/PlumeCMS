<?php
/**
 * Moduli del sito: ebook gratuito con doppia conferma dell'email, richieste di Laser Coaching.
 *
 * Ebook: modulo, email con il link di conferma, conferma, seconda email con il link per scaricare.
 * Laser Coaching: modulo, pagina di cortesia, email immediata a Danilo con il numero da chiamare.
 * Captcha fatto in casa (prova di lavoro): il browser risolve un piccolo calcolo, niente servizi esterni.
 * Le email partono da posta_invia() (SMTP della scheda Posta). I token stanno nel database solo come hash.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/posta.php';

define('MODULI_URL', cms_url_sito()); // l'indirizzo del sito nelle email
const MODULI_CONSENSO_VERSIONE = '2026-10-23';
const MODULI_CONFERMA_GIORNI = 7;     // validità del link di conferma
const MODULI_DOWNLOAD_GIORNI = 30;    // validità del link per scaricare
const MODULI_LIMITE_ORA = 5;          // invii per indirizzo IP e per modulo, in un'ora
const MODULI_CAPTCHA_MAX = 60000;     // difficoltà: in media mezzo secondo nel browser
const MODULI_CAPTCHA_DURATA = 1200;   // secondi di validità di una sfida

// Pagine del percorso, agli indirizzi di oggi dove esistono.
const MODULI_PAGINA_EBOOK = '/ebook/';
const MODULI_PAGINA_EBOOK_ATTESA = '/fuori-dagli-schemi-download/';
const MODULI_PAGINA_LASER_GRAZIE = '/laser-coaching-grazie/';

function moduli_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $tabelle = [
        'iscrizioni_ebook' => 'CREATE TABLE IF NOT EXISTS ' . tbl('iscrizioni_ebook') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `cognome` VARCHAR(100) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `cellulare` VARCHAR(40) NOT NULL,
            `consenso_versione` VARCHAR(20) NOT NULL,
            `newsletter` TINYINT(1) NOT NULL DEFAULT 0,
            `token_conferma` CHAR(64) DEFAULT NULL,
            `conferma_scade` DATETIME DEFAULT NULL,
            `confermato_at` DATETIME DEFAULT NULL,
            `token_download` CHAR(64) DEFAULT NULL,
            `download_scade` DATETIME DEFAULT NULL,
            `scaricato` INT(11) NOT NULL DEFAULT 0,
            `ultimo_scarico_at` DATETIME DEFAULT NULL,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `email` (`email`), KEY `token_conferma` (`token_conferma`), KEY `token_download` (`token_download`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        'richieste_laser' => 'CREATE TABLE IF NOT EXISTS ' . tbl('richieste_laser') . " (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `cognome` VARCHAR(100) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `cellulare` VARCHAR(40) NOT NULL,
            `consenso_versione` VARCHAR(20) NOT NULL,
            `pagina` VARCHAR(190) NOT NULL DEFAULT '',
            `stato` VARCHAR(20) NOT NULL DEFAULT 'da_chiamare',
            `note` TEXT,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `creato` DATETIME NOT NULL,
            `aggiornato` DATETIME NOT NULL,
            PRIMARY KEY (`id`), KEY `stato` (`stato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
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
// Ebook
// ------------------------------------------------------------

function moduli_ebook_config(): array {
    $c = json_decode((string)get_setting('ebook_config', ''), true);
    return (is_array($c) ? $c : []) + ['titolo' => (string)cms_impostazione('ebook_titolo', 'Ebook'), 'file' => '', 'nome_file' => (string)cms_impostazione('ebook_nome_file', 'ebook.pdf')];
}

/** Cartella dei file protetti: si scaricano solo con un link valido. */
function moduli_cartella_privata(): string {
    $d = dirname(__DIR__) . '/privato';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    if (!file_exists("$d/.htaccess")) @file_put_contents("$d/.htaccess", "Require all denied\n");
    return $d;
}

function moduli_ebook_file(): ?string {
    $f = moduli_ebook_config()['file'];
    $p = moduli_cartella_privata() . '/' . basename($f);
    return ($f !== '' && is_file($p)) ? $p : null;
}

/** Primo passo: salva la richiesta e manda l'email di conferma. Ritorna '' oppure il codice d'errore. */
function moduli_ebook_richiesta(array $d, bool $newsletter): string {
    moduli_schema();
    if (moduli_troppi('iscrizioni_ebook')) return 'troppi';
    $token = bin2hex(random_bytes(32));
    $ora = moduli_adesso();
    db()->prepare('INSERT INTO ' . tbl('iscrizioni_ebook') . ' (nome, cognome, email, cellulare, consenso_versione, newsletter, token_conferma, conferma_scade, ip, creato, aggiornato)
        VALUES (:n, :c, :e, :t, :v, :nl, :tk, DATE_ADD(NOW(), INTERVAL ' . MODULI_CONFERMA_GIORNI . ' DAY), :ip, :o, :o2)
        ON DUPLICATE KEY UPDATE nome = VALUES(nome), cognome = VALUES(cognome), cellulare = VALUES(cellulare), consenso_versione = VALUES(consenso_versione),
            newsletter = GREATEST(newsletter, VALUES(newsletter)), token_conferma = VALUES(token_conferma), conferma_scade = VALUES(conferma_scade), ip = VALUES(ip), aggiornato = VALUES(aggiornato)')
        ->execute([':n' => $d['nome'], ':c' => $d['cognome'], ':e' => $d['email'], ':t' => $d['cellulare'], ':v' => MODULI_CONSENSO_VERSIONE,
            ':nl' => $newsletter ? 1 : 0, ':tk' => hash('sha256', $token), ':ip' => moduli_ip(), ':o' => $ora, ':o2' => $ora]);
    $link = MODULI_URL . '/conferma-ebook/?t=' . $token;
    [$oggetto, $corpo] = moduli_email_conferma($d['nome'], $link);
    return posta_invia($d['email'], $oggetto, $corpo, moduli_intestazioni()) ? '' : 'posta';
}

/** Secondo passo: il link di conferma. Ritorna la riga confermata, oppure null se il link è scaduto o sconosciuto. */
function moduli_ebook_conferma(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    moduli_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('iscrizioni_ebook') . ' WHERE token_conferma = ? AND conferma_scade > NOW()');
    $st->execute([hash('sha256', $token)]);
    $r = $st->fetch();
    if (!$r) return null;
    $download = bin2hex(random_bytes(32));
    db()->prepare('UPDATE ' . tbl('iscrizioni_ebook') . ' SET confermato_at = COALESCE(confermato_at, NOW()), token_conferma = NULL, conferma_scade = NULL,
        token_download = ?, download_scade = DATE_ADD(NOW(), INTERVAL ' . MODULI_DOWNLOAD_GIORNI . ' DAY), aggiornato = NOW() WHERE id = ?')
        ->execute([hash('sha256', $download), $r['id']]);
    // Consenso alla newsletter dato nel modulo: con l'email appena confermata l'iscrizione è attiva.
    if ((int)$r['newsletter'] === 1) {
        require_once __DIR__ . '/newsletter.php';
        newsletter_da_ebook($r);
    }
    [$oggetto, $corpo] = moduli_email_ebook($r['nome'], MODULI_URL . '/scarica-ebook/?t=' . $download);
    $r['email_partita'] = posta_invia($r['email'], $oggetto, $corpo, moduli_intestazioni());
    return $r;
}

/** Terzo passo: il link per scaricare. Ritorna il percorso del file, oppure il motivo per cui non si scarica. */
function moduli_ebook_scarica(string $token): array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return [null, 'link'];
    moduli_schema();
    $st = db()->prepare('SELECT id FROM ' . tbl('iscrizioni_ebook') . ' WHERE token_download = ? AND download_scade > NOW()');
    $st->execute([hash('sha256', $token)]);
    $id = $st->fetchColumn();
    if (!$id) return [null, 'link'];
    $file = moduli_ebook_file();
    if (!$file) return [null, 'file'];
    db()->prepare('UPDATE ' . tbl('iscrizioni_ebook') . ' SET scaricato = scaricato + 1, ultimo_scarico_at = NOW() WHERE id = ?')->execute([$id]);
    return [$file, ''];
}

// ------------------------------------------------------------
// Laser Coaching
// ------------------------------------------------------------

function moduli_laser_richiesta(array $d, string $pagina): string {
    moduli_schema();
    if (moduli_troppi('richieste_laser')) return 'troppi';
    $ora = moduli_adesso();
    db()->prepare('INSERT INTO ' . tbl('richieste_laser') . ' (nome, cognome, email, cellulare, consenso_versione, pagina, ip, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$d['nome'], $d['cognome'], $d['email'], $d['cellulare'], MODULI_CONSENSO_VERSIONE, mb_substr($pagina, 0, 190), moduli_ip(), $ora, $ora]);
    // Avviso a Danilo, subito: la promessa è una chiamata entro 24 ore.
    $a = posta_notifica_email();
    if ($a !== '') {
        [$oggetto, $corpo] = moduli_email_avviso_laser($d);
        if (!posta_invia($a, $oggetto, $corpo, moduli_intestazioni($d['email']))) error_log('moduli: avviso laser non partito: ' . posta_errore());
    } else {
        error_log('moduli: richiesta laser senza email di notifica configurata');
    }
    return '';
}

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

/** La firma in fondo alle email: "Danilo" su danilofailli.it, altrimenti il nome del sito. Sempre lo stesso HTML, così si può agganciare. */
function moduli_firma_html(): string {
    return '<p style="margin-top:28px">' . h((string)cms_impostazione('email_firma', cms_impostazione('sito_nome', CMS_NOME))) . '</p>';
}

/** Il nome del sito nelle frasi delle email ("su danilofailli.it"): l'host dell'indirizzo del sito. */
function moduli_host(): string {
    return (string)(parse_url(MODULI_URL, PHP_URL_HOST) ?: cms_impostazione('sito_nome', CMS_NOME));
}

function moduli_email_conferma(string $nome, string $link): array {
    $corpo = '<p>Ciao ' . h($nome) . ',</p><p>manca un clic. Premi il pulsante e confermi che questo indirizzo è tuo: subito dopo ti arriva la seconda email, con il link per scaricare «' . h(moduli_ebook_config()['titolo']) . '».</p>'
        . '<p>Il link vale ' . MODULI_CONFERMA_GIORNI . ' giorni.</p>';
    $html = moduli_email_html('Conferma il tuo indirizzo', $corpo, 'Confermo il mio indirizzo', $link);
    $html = str_replace(moduli_firma_html(), moduli_firma_html() . '<p style="font-size:12px;color:#888">Hai ricevuto questa email perché il tuo indirizzo è stato inserito nel modulo dell\'ebook su ' . h(moduli_host()) . '. Se la richiesta l\'ha fatta qualcun altro, ignorala: senza conferma l\'indirizzo resta fuori da ogni elenco.</p>', $html);
    return ['Conferma il tuo indirizzo e ricevi l\'ebook', $html];
}

function moduli_email_ebook(string $nome, string $link): array {
    $t = h(moduli_ebook_config()['titolo']);
    $corpo = '<p>Ciao ' . h($nome) . ',</p><p>eccolo: «' . $t . '» è pronto per te.</p>'
        . '<p>Il link resta attivo ' . MODULI_DOWNLOAD_GIORNI . ' giorni. Salva il file sul telefono o sul computer, così lo ritrovi quando ti serve.</p>'
        . '<p>Dentro ci sono quattro strumenti: confini, presenza, dialogo, responsabilità. Parti da quello che oggi ti pesa di più.</p>'
        . '<p>Quando vuoi trasformare una pagina in un passo sul lavoro, c\'è la <a href="' . MODULI_URL . '/laser-coaching/">Laser Coaching</a>: 15 minuti al telefono, gratuita.</p>';
    return ["Il tuo ebook «" . moduli_ebook_config()['titolo'] . "»", moduli_email_html('Il tuo ebook', $corpo, "Scarica l'ebook", $link)];
}

function moduli_email_avviso_laser(array $d): array {
    $entro = date('d/m/Y \a\l\l\e H:i', time() + 86400);
    $tel = preg_replace('/[^0-9+]/', '', $d['cellulare']);
    $corpo = '<p>Nuova richiesta di Laser Coaching. La promessa sul sito è una chiamata entro 24 ore: <b>entro ' . $entro . '</b>.</p>'
        . '<table cellpadding="6" style="font-size:16px"><tr><td>Nome</td><td><b>' . h($d['nome'] . ' ' . $d['cognome']) . '</b></td></tr>'
        . '<tr><td>Cellulare</td><td><a href="tel:' . h($tel) . '">' . h($d['cellulare']) . '</a></td></tr>'
        . '<tr><td>Email</td><td><a href="mailto:' . h($d['email']) . '">' . h($d['email']) . '</a></td></tr></table>'
        . '<p>Dopo la chiamata, segnala come chiamato nel pannello, voce Contatti.</p>';
    return ['Laser Coaching: chiama ' . $d['nome'] . ' ' . $d['cognome'] . ' entro ' . date('d/m H:i', time() + 86400),
        moduli_email_html('Da chiamare entro 24 ore', $corpo, 'Apri i contatti nel pannello', MODULI_URL . cms_base() . '/admin/contatti.php')];
}
