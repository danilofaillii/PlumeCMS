<?php
// Il pannello gestisce da sé errori e avvisi: includes/config.php, trovando questa costante, non li spegne.
define('PUBLISHER_ERRORI', 'pannello');
// Errori: tutto nel log del publisher; a schermo solo errori e avvisi veri, mai le deprecazioni
// (su PHP 8.4 una deprecazione stampata prima di un header() rompeva i redirect dopo un salvataggio).
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
@mkdir(dirname(__DIR__, 2) . '/logs', 0755, true);
ini_set('error_log', dirname(__DIR__, 2) . '/logs/php-errori.log');
set_error_handler(function (int $n, string $msg, string $file, int $riga): bool {
    if ($n & (E_DEPRECATED | E_USER_DEPRECATED)) { error_log("Deprecato: $msg in $file:$riga"); return true; }
    return false;
});

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/sicurezza.php';
require_once __DIR__ . '/../../includes/pannello-aspetto.php'; // 1.4.0: gli aspetti del pannello

// Indirizzo pulito. Con un pezzo in più dopo il nome del file (/admin/index.php/qualcosa) Apache apre lo stesso la pagina,
// ma i fogli di stile e gli script, scritti relativi, si cercano dentro "index.php/" e il pannello appare senza grafica
// (26 ottobre 2026, prima installazione di plumeCMS sul server). Si torna all'indirizzo del file, con la stessa query.
if (PHP_SAPI !== 'cli' && ($_SERVER['PATH_INFO'] ?? '') !== '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $pulito = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if ($pulito !== '' && str_starts_with($pulito, '/')) {
        $q = (string)($_SERVER['QUERY_STRING'] ?? '');
        header('Location: ' . $pulito . ($q !== '' ? '?' . $q : ''), true, 301);
        exit;
    }
}

// Tutto l'HTML passa da un buffer: un avviso stampato prima di header('Location') non blocca più il redirect.
// sicurezza: a fine pagina il buffer calcola gli hash dei gestori in linea e manda le intestazioni (CSP compresa).
// Il buffer si apre sempre (anche sopra output_buffering del php.ini): il callback deve vedere l'HTML finito.
if (PHP_SAPI !== 'cli') ob_start('sicurezza_pannello_fine');

// sicurezza: cookie HttpOnly, SameSite=Lax, Secure su https; un id di sessione sconosciuto non viene accettato.
sicurezza_sessione_avvia();

/**
 * Campi protetti. Il browser (admin/assets/campi-protetti.js) manda i testi lunghi, HTML, JSON e SQL in base64 con il
 * prefisso ~b64~: il firewall del server (ModSecurity, su cPanel) non li scambia per un attacco e non risponde 403.
 * Qui tornano in chiaro, prima che una pagina legga $_POST. Un valore senza prefisso passa com'è: senza JavaScript tutto funziona uguale.
 */
function campi_protetti_decodifica(array $a): array {
    foreach ($a as $k => $v) {
        if (is_array($v)) $a[$k] = campi_protetti_decodifica($v);
        elseif (is_string($v) && str_starts_with($v, '~b64~')) {
            $d = base64_decode(substr($v, 5), true);
            if ($d !== false) $a[$k] = $d;
        }
    }
    return $a;
}
if (!empty($_POST)) $_POST = campi_protetti_decodifica($_POST);

$GLOBALS['dap_embedded'] = false;

function is_logged_in(): bool {
    return !empty($_SESSION['admin_user_id']);
}

/**
 * sicurezza (attacco 2, ruoli): le sole pagine del pannello che un redattore puo aprire. Tutto il resto e dell'amministratore,
 * per ogni pagina e ogni azione (require_login lo controlla, anche se la pagina dimentica require_admin).
 * Il redattore gestisce articoli, pagine (e i tipi di contenuto dei moduli), media, categorie e tag; non i modelli.
 * Un modulo aggiunge le sue pagine con il filtro 'redattore_pagine'.
 */
const AUTH_REDATTORE_PAGINE = ['index.php', 'contenuti.php', 'contenuto.php', 'editor.php', 'editor-api.php', 'editor-anteprima.php', 'seo-api.php',
    'media.php', 'categories.php', 'versioni.php', 'account.php', 'password.php', 'logout.php', 'login.php'];

/** Il redattore puo aprire questa pagina (nome del file, con il ?tipo= se serve)? Lo usano require_login e il menu. */
function auth_redattore_puo(string $pagina, string $tipo = ''): bool {
    $pagine = AUTH_REDATTORE_PAGINE;
    if (function_exists('cms_filtro')) $pagine = array_merge($pagine, (array)cms_filtro('redattore_pagine', []));
    if (!in_array($pagina, $pagine, true)) return false;
    // Header e footer passano dall'editor con tipo=modelli: sono dell'amministratore.
    if ($tipo === 'modelli') return false;
    return true;
}

/** Le pagine che un utente puo aprire anche con la sessione "a meta" (2FA da configurare): il suo accesso e l'uscita. */
const AUTH_PAGINE_LIBERE = ['account.php', 'logout.php', 'login.php'];

function require_login(): void {
    if (!is_logged_in()) {
        auth_rifiuta('login.php');
    }
    $user = current_user();
    if (!$user) { sicurezza_sessione_chiudi('logout'); auth_rifiuta('login.php'); }
    // sicurezza: scadenza assoluta, inattivita, sessioni chiuse da "Esci da tutti", IP consentiti.
    $motivo = sicurezza_sessione_controlla($user);
    if ($motivo !== '') {
        sicurezza_sessione_chiudi($motivo === 'ip' ? 'ip_rifiutato' : 'sessione_scaduta');
        auth_rifiuta('login.php?motivo=' . $motivo);
    }
    // 2FA obbligatorio per gli amministratori: prima si configura, poi si lavora.
    if (sicurezza_2fa_da_configurare($user) && !in_array(basename((string)$_SERVER['SCRIPT_NAME']), AUTH_PAGINE_LIBERE, true)) {
        auth_rifiuta('account.php?2fa=obbligatorio');
    }
    // Ruoli: un redattore fuori dalle sue pagine torna alla dashboard (le chiamate API ricevono 403).
    if (($user['role'] ?? '') !== 'admin') {
        $qui = basename((string)$_SERVER['SCRIPT_NAME']);
        $tipo = (string)($_GET['tipo'] ?? $_POST['tipo'] ?? '');
        if (!auth_redattore_puo($qui, $tipo)) {
            if (str_ends_with($qui, '-api.php') || $_SERVER['REQUEST_METHOD'] === 'POST') {
                http_response_code(403);
                if (str_ends_with($qui, '-api.php')) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['errore' => 'Riservato all\'amministratore.']); }
                else echo 'Riservato all\'amministratore.';
                exit;
            }
            flash('error', 'Questa pagina è riservata all\'amministratore.');
            header('Location: index.php');
            exit;
        }
    }
    // Su un database appena importato le viste possono mancare: il pannello le ricrea, una volta per sessione.
    if (empty($_SESSION['viste_ok'])) {
        require_once __DIR__ . '/../../includes/sito.php';
        sito_viste_se_mancano();
        $_SESSION['viste_ok'] = 1;
    }
}

/** Rimanda al login (o risponde 401 in JSON a una chiamata API, che non sa seguire un redirect). */
function auth_rifiuta(string $dove): never {
    $api = str_ends_with((string)$_SERVER['SCRIPT_NAME'], '-api.php') || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json');
    if ($api) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['errore' => 'Sessione scaduta: entra di nuovo nel pannello.']);
        exit;
    }
    header('Location: ' . $dove);
    exit;
}

/** Vero per un amministratore. Un redattore scrive e pubblica, ma non tocca utenti, impostazioni, aggiornamenti e SQL. */
function is_admin(): bool {
    return (current_user()['role'] ?? 'admin') === 'admin';
}

/** Le pagine riservate all'amministratore: un redattore torna alla dashboard con un avviso. */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        flash('error', 'Questa pagina è riservata all\'amministratore.');
        header('Location: index.php');
        exit;
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        sicurezza_schema();
        $stmt = db()->prepare('SELECT * FROM ' . tbl('users') . ' WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['admin_user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

/**
 * Primo passo del login: nome (o email) e password. Ritorna lo stato:
 *   'ok'        entrato (sessione completa)
 *   '2fa'       password giusta, manca il codice del secondo fattore (login_2fa)
 *   'bloccato'  troppi tentativi: in $secondi quanto manca
 *   'no'        credenziali sbagliate
 */
function login_esteso(string $username, string $password, int &$secondi = 0): string {
    sicurezza_schema();
    // Il blocco per IP ferma tutto. Quello per nome utente (20 sbagliati in 15 minuti) lascia entrare, con la password
    // giusta, chi arriva da un IP "di casa" (sicurezza_ip_fidato): un anonimo non chiude fuori il proprietario.
    $blocchi = sicurezza_login_blocchi($username);
    $secondi = $blocchi['ip'];
    if ($secondi > 0) { sicurezza_log('login_bloccato', $username); return 'bloccato'; }
    if (!sicurezza_spento() && sicurezza_cfg()['ip_attivo'] && !sicurezza_ip_consentito(sicurezza_ip(), (string)sicurezza_cfg()['ip_elenco'])) {
        sicurezza_log('ip_rifiutato', $username);
        return 'no';
    }
    $stmt = db()->prepare('SELECT * FROM ' . tbl('users') . ' WHERE username = :u1 OR email = :u2');
    $stmt->execute([':u1' => $username, ':u2' => $username]);
    $user = $stmt->fetch();
    if (!$user || !sicurezza_password_verifica($user, $password)) {
        // Stesso tempo di risposta con utente inesistente o password sbagliata.
        if (!$user) password_verify($password, sicurezza_hash_finto()); // stesso algoritmo e costo degli account veri
        sicurezza_login_fallito($username);
        if ($blocchi['utente'] > 0) { $secondi = $blocchi['utente']; return 'bloccato'; }
        return 'no';
    }
    if ($blocchi['utente'] > 0) {
        // Password giusta durante il blocco del nome utente: passa solo da un IP di casa, e resta una riga nel registro.
        // Da un altro IP la risposta e la stessa di prima ("troppi tentativi"): la password giusta non si distingue.
        if (!sicurezza_ip_fidato((int)$user['id'])) { $secondi = $blocchi['utente']; sicurezza_log('login_bloccato', $username, (int)$user['id'], 'password giusta da un IP non di casa durante il blocco del nome utente'); return 'bloccato'; }
        sicurezza_log('login_durante_blocco', (string)$user['username'], (int)$user['id'], 'IP gia usato per accessi riusciti, mai bloccato');
        $GLOBALS['sic_durante_blocco'] = true;
    }
    if (sicurezza_2fa_richiesto($user)) {
        session_regenerate_id(true);
        $_SESSION['sic_pre'] = ['id' => (int)$user['id'], 'quando' => time()];
        return '2fa';
    }
    sicurezza_login_completa($user);
    return 'ok';
}

/** Compatibilita: vero se entrato. */
function login(string $username, string $password): bool {
    return login_esteso($username, $password) === 'ok';
}

/** Secondo passo: il codice TOTP (sei cifre) oppure un codice di recupero. */
function login_2fa(string $codice): bool {
    $pre = $_SESSION['sic_pre'] ?? null;
    if (!$pre || time() - (int)$pre['quando'] > 300) { unset($_SESSION['sic_pre']); return false; }
    $st = db()->prepare('SELECT * FROM ' . tbl('users') . ' WHERE id = ?'); $st->execute([(int)$pre['id']]);
    $user = $st->fetch();
    if (!$user) return false;
    $b = sicurezza_login_blocchi((string)$user['username']);
    if ($b['ip'] > 0) return false;
    if ($b['utente'] > 0 && !sicurezza_ip_fidato((int)$user['id'])) return false;
    if ($b['utente'] > 0) $GLOBALS['sic_durante_blocco'] = true;
    $passo = sicurezza_totp_verifica((string)$user['totp_segreto'], $codice, (int)$user['totp_ultimo']);
    if ($passo > 0) {
        db()->prepare('UPDATE ' . tbl('users') . ' SET totp_ultimo = ? WHERE id = ?')->execute([$passo, (int)$user['id']]);
    } elseif (!sicurezza_recupero_usa($user, $codice)) {
        sicurezza_log('login_2fa_fallito', (string)$user['username'], (int)$user['id']);
        sicurezza_login_fallito((string)$user['username']);
        return false;
    }
    sicurezza_login_completa($user);
    return true;
}

function logout(): void {
    sicurezza_sessione_chiudi('logout');
    header('Location: login.php');
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

/** Il token e giusto e la richiesta viene da questo sito (Origin/Referer)? Lo usano i form e le API. */
function csrf_ok(?string $token = null): bool {
    $token = $token ?? (string)($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));
    $ok = $token !== '' && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token) && sicurezza_origine_ok();
    if (!$ok) sicurezza_log('csrf_rifiutato', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), basename((string)$_SERVER['SCRIPT_NAME']));
    return $ok;
}

function verify_csrf(): void {
    if (!csrf_ok()) {
        http_response_code(403);
        die('Token CSRF non valido. Ricarica la pagina.');
    }
}

/** Il nonce per gli <script> in linea delle pagine del pannello: <script<?= nonce_attr() ?>>. */
function nonce_attr(): string {
    return sicurezza_nonce_attr();
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function render_flash(): string {
    if (empty($_SESSION['flash'])) return '';
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = $f['type'] === 'error' ? 'flash-error' : ($f['type'] === 'success' ? 'flash-success' : 'flash-info');
        $out .= '<div class="flash ' . $cls . '">' . htmlspecialchars($f['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    $_SESSION['flash'] = [];
    return $out;
}
