<?php
/**
 * Plugin Name: plumeCMS Ponte
 * Description: Collega questo sito WordPress a plumeCMS per l'import, senza password per le applicazioni. Solo lettura: il ponte non scrive, non cancella e non cambia niente. Si apre da Strumenti &gt; plumeCMS Ponte e si chiude da solo dopo 48 ore.
 * Version: 1.0.1
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * Author: plumeCMS
 * License: GPL-3.0-or-later
 * Text Domain: plumecms-ponte
 *
 * Come funziona. In Strumenti > plumeCMS Ponte un amministratore apre il ponte: il plugin crea un codice casuale (32 byte) e lo
 * mostra. Il codice si copia in plumeCMS (Impostazioni > Importa da WordPress, "Con il plugin Ponte"). plumeCMS manda ogni
 * richiesta in POST a /?plume_ponte=1 con: la rotta della REST da leggere (solo GET), la richiesta, l'ora, un numero a caso e
 * la firma HMAC-SHA256 di tutto con il codice. Il plugin controlla la firma, l'ora (5 minuti), che il numero non sia già
 * stato usato, e risponde con quello che la REST di WordPress darebbe all'amministratore che ha aperto il ponte.
 * La firma viaggia nel corpo della richiesta: funziona anche sugli hosting che tolgono l'intestazione Authorization e
 * quando un plugin di sicurezza chiude la REST a chi viene da fuori.
 *
 * Sicurezza:
 * - solo lettura: si eseguono solo richieste GET, e solo sulle rotte dei contenuti (wp/v2 e i tipi di contenuto registrati),
 *   mai su password per le applicazioni, plugin, temi, utenti diversi da quelli dei contenuti;
 * - il codice scade dopo 48 ore e si cancella; Chiudi il ponte lo cancella subito; disattivando il plugin si cancella;
 * - dopo 20 firme sbagliate in 15 minuti da uno stesso indirizzo, quell'indirizzo resta fuori per 15 minuti;
 * - mai il codice nei registri o nelle risposte.
 */
if (!defined('ABSPATH')) exit;

define('PLUME_PONTE_VERSIONE', '1.0.1');
define('PLUME_PONTE_DURATA', 172800);   // 48 ore
define('PLUME_PONTE_FINESTRA', 300);    // 5 minuti di differenza ammessa fra gli orologi
define('PLUME_PONTE_ERRORI', 20);       // firme sbagliate prima del blocco
define('PLUME_PONTE_BLOCCO', 900);      // 15 minuti

/** Lo stato del ponte: ['codice', 'utente', 'scade'] oppure null se chiuso o scaduto (e allora si cancella). */
function plume_ponte_stato() {
    $s = get_option('plume_ponte');
    if (!is_array($s) || empty($s['codice']) || empty($s['utente'])) return null;
    if ((int)$s['scade'] < time()) { delete_option('plume_ponte'); return null; }
    return $s;
}

function plume_ponte_b64url($b) {
    return rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
}

/** Il codice da copiare in plumeCMS: "PLUME1-" e 43 caratteri. */
function plume_ponte_apri($utente) {
    $codice = 'PLUME1-' . plume_ponte_b64url(random_bytes(32));
    update_option('plume_ponte', ['codice' => $codice, 'utente' => (int)$utente, 'scade' => time() + PLUME_PONTE_DURATA, 'aperto' => time()], false);
    return $codice;
}

function plume_ponte_chiudi() {
    delete_option('plume_ponte');
}

/** Disattivato il plugin: via anche l'ultimo errore e la scelta di saltare i filtri. */
function plume_ponte_disattiva() {
    plume_ponte_chiudi();
    delete_option('plume_ponte_errore');
    delete_option('plume_ponte_senza_filtri');
}

register_deactivation_hook(__FILE__, 'plume_ponte_disattiva');

// ------------------------------------------------------------
// La pagina Strumenti > plumeCMS Ponte
// ------------------------------------------------------------

add_action('admin_menu', function () {
    add_management_page('plumeCMS Ponte', 'plumeCMS Ponte', 'manage_options', 'plumecms-ponte', 'plume_ponte_pagina');
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($l) {
    array_unshift($l, '<a href="' . esc_url(admin_url('tools.php?page=plumecms-ponte')) . '">Apri il ponte</a>');
    return $l;
});

add_action('admin_post_plume_ponte', function () {
    if (!current_user_can('manage_options')) wp_die('Solo un amministratore può aprire o chiudere il ponte.', '', ['response' => 403]);
    check_admin_referer('plume_ponte');
    $cosa = isset($_POST['cosa']) ? sanitize_key(wp_unslash($_POST['cosa'])) : '';
    if ($cosa === 'apri') plume_ponte_apri(get_current_user_id());
    elseif ($cosa === 'chiudi') plume_ponte_chiudi();
    wp_safe_redirect(admin_url('tools.php?page=plumecms-ponte&fatto=' . $cosa));
    exit;
});

function plume_ponte_pagina() {
    if (!current_user_can('manage_options')) return;
    $s = plume_ponte_stato();
    $fatto = isset($_GET['fatto']) ? sanitize_key(wp_unslash($_GET['fatto'])) : '';
    $indirizzo = home_url();
    ?>
    <div class="wrap" id="plume-ponte">
      <h1>plumeCMS Ponte</h1>
      <?php if ($fatto === 'apri' && $s): ?><div class="notice notice-success"><p>Ponte aperto. Copia il codice qui sotto in plumeCMS.</p></div><?php endif; ?>
      <?php if ($fatto === 'chiudi'): ?><div class="notice notice-success"><p>Ponte chiuso: il codice non vale più.</p></div><?php endif; ?>
      <p style="max-width:720px">Il ponte permette a plumeCMS di leggere questo sito per importarlo: articoli, pagine, categorie, tag, immagini, menu e SEO. <b>Solo lettura</b>: il ponte non scrive, non cancella e non cambia niente su WordPress. Non serve la password per le applicazioni.</p>
      <?php $err = get_option('plume_ponte_errore'); if (is_array($err) && !empty($err['messaggio'])): ?>
        <div class="notice notice-warning" id="plume-ponte-errore"><p><b>Ultimo errore del ponte</b> (<?php echo esc_html(wp_date('j F Y, H:i', (int)$err['quando'])); ?>, lettura di <code><?php echo esc_html($err['rotta']); ?></code>, fase <?php echo esc_html($err['fase']); ?>):<br><code style="white-space:pre-wrap"><?php echo esc_html($err['messaggio']); ?></code><br>Mandalo a chi ti segue con plumeCMS: dice quale parte di WordPress si è fermata.<?php if (get_option('plume_ponte_senza_filtri')): ?> Il ponte ora salta i filtri degli altri plugin e riprova da solo: in plumeCMS premi Riprova.<?php endif; ?></p></div>
      <?php endif; ?>
      <?php if ($s): $autore = get_userdata((int)$s['utente']); ?>
        <h2>Il ponte è aperto</h2>
        <ol style="max-width:720px;line-height:1.7">
          <li>Copia il codice con il pulsante <b>Copia il codice</b>.</li>
          <li>In plumeCMS apri <b>Impostazioni &gt; Importa da WordPress</b> e scegli <b>Con il plugin Ponte</b>.</li>
          <li>Scrivi l'indirizzo <code><?php echo esc_html($indirizzo); ?></code>, incolla il codice e premi <b>Collegati e analizza il sito</b>.</li>
        </ol>
        <p><label for="plume-ponte-codice"><b>Codice del ponte</b></label><br>
          <input id="plume-ponte-codice" type="text" readonly value="<?php echo esc_attr($s['codice']); ?>" style="width:min(100%,560px);font-family:monospace;font-size:14px;padding:6px 8px">
          <button type="button" class="button button-primary" id="plume-ponte-copia">Copia il codice</button></p>
        <p>Il codice vale fino a <b><?php echo esc_html(wp_date('j F Y, H:i', (int)$s['scade'])); ?></b>, poi il ponte si chiude da solo. plumeCMS legge il sito come <b><?php echo esc_html($autore ? $autore->display_name : '?'); ?></b>. Il codice è una chiave: non mandarlo per email e non incollarlo in chat.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <?php wp_nonce_field('plume_ponte'); ?><input type="hidden" name="action" value="plume_ponte"><input type="hidden" name="cosa" value="chiudi">
          <button class="button" type="submit">Chiudi il ponte</button>
        </form>
        <p style="max-width:720px;color:#50575e">A import finito chiudi il ponte, poi disattiva e cancella il plugin da <b>Plugin</b>.</p>
        <script>
        document.getElementById('plume-ponte-copia').addEventListener('click', function () {
          var c = document.getElementById('plume-ponte-codice'), b = this;
          c.select();
          (navigator.clipboard ? navigator.clipboard.writeText(c.value) : Promise.reject()).catch(function () { document.execCommand('copy'); })
            .finally(function () { b.textContent = 'Copiato'; setTimeout(function () { b.textContent = 'Copia il codice'; }, 2000); });
        });
        </script>
      <?php else: ?>
        <h2>Il ponte è chiuso</h2>
        <p>Premi <b>Apri il ponte</b>: compare un codice da copiare in plumeCMS. Vale 48 ore.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <?php wp_nonce_field('plume_ponte'); ?><input type="hidden" name="action" value="plume_ponte"><input type="hidden" name="cosa" value="apri">
          <button class="button button-primary" type="submit">Apri il ponte</button>
        </form>
      <?php endif; ?>
    </div>
    <?php
}

// ------------------------------------------------------------
// Le richieste di plumeCMS: POST /?plume_ponte=1
// ------------------------------------------------------------

function plume_ponte_rispondi($codice, array $dati) {
    while (ob_get_level() > 0) ob_end_clean();
    nocache_headers();
    status_header($codice);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo wp_json_encode(['ponte' => 1, 'versione' => PLUME_PONTE_VERSIONE] + $dati);
    exit;
}

function plume_ponte_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
}

function plume_ponte_sbagliata($errore) {
    $k = 'plume_ponte_e_' . md5(plume_ponte_ip());
    $n = (int)get_transient($k);
    set_transient($k, $n + 1, PLUME_PONTE_BLOCCO);
    plume_ponte_rispondi(403, ['errore' => $errore]);
}

/** La rotta si può leggere? L'indice, wp/v2 (tranne le parti che non servono a un import) e i tipi di contenuto con la REST. */
function plume_ponte_rotta_ammessa($rotta) {
    if (!is_string($rotta) || $rotta === '' || $rotta[0] !== '/' || strlen($rotta) > 200) return false;
    if (!preg_match('~^/[A-Za-z0-9/_.-]*$~', $rotta) || strpos($rotta, '..') !== false) return false;
    if ($rotta === '/') return true;
    if (preg_match('~/application-passwords~i', $rotta)) return false;
    if (preg_match('~^/wp/v2/(plugins|themes|block-directory|widget-types|widgets|sidebars|global-styles|font-|templates|template-parts|users/[^/]+/)~i', $rotta)) return false;
    if (preg_match('~^/wp/v2(/|$)~', $rotta)) return true;
    foreach (get_post_types(['show_in_rest' => true], 'objects') as $t) {
        $ns = !empty($t->rest_namespace) ? $t->rest_namespace : 'wp/v2';
        $base = !empty($t->rest_base) ? $t->rest_base : $t->name;
        $p = '/' . trim($ns, '/') . '/' . trim($base, '/');
        if ($rotta === $p || strpos($rotta, $p . '/') === 0) return true;
    }
    return false;
}

add_action('wp_loaded', function () {
    if (!isset($_GET['plume_ponte'])) return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') plume_ponte_rispondi(405, ['errore' => 'metodo']);
    if ((int)get_transient('plume_ponte_e_' . md5(plume_ponte_ip())) >= PLUME_PONTE_ERRORI) plume_ponte_rispondi(429, ['errore' => 'bloccato']);
    $s = plume_ponte_stato();
    if (!$s) plume_ponte_sbagliata('chiuso');

    $rotta = isset($_POST['rotta']) ? (string)wp_unslash($_POST['rotta']) : '';
    $q = isset($_POST['q']) ? (string)wp_unslash($_POST['q']) : '';
    $t = isset($_POST['t']) ? (string)wp_unslash($_POST['t']) : '';
    $n = isset($_POST['n']) ? (string)wp_unslash($_POST['n']) : '';
    $firma = isset($_POST['firma']) ? (string)wp_unslash($_POST['firma']) : '';
    if (!preg_match('/^[0-9]{9,11}$/', $t) || !preg_match('/^[a-f0-9]{32}$/', $n) || !preg_match('/^[a-f0-9]{64}$/', $firma) || strlen($q) > 4000) plume_ponte_sbagliata('richiesta');
    $giusta = hash_hmac('sha256', 'plume-ponte|1|' . $t . '|' . $n . '|' . $rotta . '|' . $q, (string)$s['codice']);
    if (!hash_equals($giusta, $firma)) plume_ponte_sbagliata('firma');
    if (abs(time() - (int)$t) > PLUME_PONTE_FINESTRA) plume_ponte_rispondi(403, ['errore' => 'ora', 'ora' => time()]);
    if (get_transient('plume_ponte_n_' . $n)) plume_ponte_rispondi(403, ['errore' => 'ripetuta']);
    set_transient('plume_ponte_n_' . $n, 1, 2 * PLUME_PONTE_FINESTRA + 60);

    if (!plume_ponte_rotta_ammessa($rotta)) plume_ponte_rispondi(403, ['errore' => 'rotta']);
    $param = $q === '' ? [] : json_decode($q, true);
    if (!is_array($param)) plume_ponte_rispondi(400, ['errore' => 'richiesta']);
    // Solo lettura anche così: niente cambio di metodo, busta o JSONP passati come parametri.
    unset($param['_method'], $param['_envelope'], $param['_jsonp']);

    $utente = get_userdata((int)$s['utente']);
    if (!$utente || !user_can($utente, 'manage_options')) { plume_ponte_chiudi(); plume_ponte_rispondi(403, ['errore' => 'utente']); }
    wp_set_current_user($utente->ID);

    // 1.0.1: se WordPress o un altro plugin va in errore mentre si prepara la risposta, il ponte risponde lo stesso con il motivo
    // (solo a chi ha firmato giusto) e lo scrive nella sua pagina. Un errore fatale nei filtri dopo la risposta li fa saltare
    // dalla richiesta dopo: si applica solo _fields, come fa WordPress.
    $GLOBALS['plume_ponte_in_corso'] = ['rotta' => $rotta, 'fase' => 'avvio'];
    ob_start();
    register_shutdown_function('plume_ponte_fine');
    // Il gestore degli errori fatali di WordPress (la pagina "errore critico") si registra prima e chiude tutto con wp_die:
    // con WP_SANDBOX_SCRAPING, la costante che WordPress usa per le sue verifiche interne, si fa da parte e risponde il ponte.
    if (!defined('WP_SANDBOX_SCRAPING')) define('WP_SANDBOX_SCRAPING', true);
    try {
        $req = new WP_REST_Request('GET', $rotta);
        $req->set_query_params(array_map(function ($v) { return is_scalar($v) ? (string)$v : $v; }, $param));
        $GLOBALS['plume_ponte_in_corso']['fase'] = 'rest';
        $server = rest_get_server();
        $res = rest_ensure_response(rest_do_request($req));
        $GLOBALS['plume_ponte_in_corso']['fase'] = 'filtri';
        if (get_option('plume_ponte_senza_filtri')) $res = rest_filter_response_fields($res, $server, $req);
        else {
            try { $res = apply_filters('rest_post_dispatch', $res, $server, $req); }
            catch (Throwable $e) { plume_ponte_segna_errore($e->getMessage() . ' (' . plume_ponte_dove($e->getFile(), $e->getLine()) . ')', 'filtri'); $res = rest_filter_response_fields(rest_ensure_response(rest_do_request($req)), $server, $req); }
        }
        $GLOBALS['plume_ponte_in_corso']['fase'] = 'dati';
        $intest = [];
        foreach ($res->get_headers() as $k => $v) {
            $k = strtolower((string)$k);
            if (in_array($k, ['x-wp-total', 'x-wp-totalpages'], true)) $intest[$k] = (string)$v;
        }
        $corpo = $server->response_to_data($res, isset($param['_embed']));
    } catch (Throwable $e) {
        $m = $e->getMessage() . ' (' . plume_ponte_dove($e->getFile(), $e->getLine()) . ')';
        plume_ponte_segna_errore($m, $GLOBALS['plume_ponte_in_corso']['fase']);
        unset($GLOBALS['plume_ponte_in_corso']);
        plume_ponte_rispondi(500, ['errore' => 'interno', 'dettaglio' => $m]);
    }
    unset($GLOBALS['plume_ponte_in_corso']);
    plume_ponte_rispondi(200, ['codice' => (int)$res->get_status(), 'header' => $intest, 'corpo' => $corpo]);
}, 1);

/** Il file di un errore senza il percorso del server: wp-content/plugins/... */
function plume_ponte_dove($file, $riga) {
    $f = str_replace('\\', '/', (string)$file);
    $a = str_replace('\\', '/', ABSPATH);
    return (strpos($f, $a) === 0 ? substr($f, strlen($a)) : basename($f)) . ':' . (int)$riga;
}

/** L'ultimo errore, per la pagina Strumenti > plumeCMS Ponte (mai il codice, mai i dati). */
function plume_ponte_segna_errore($messaggio, $fase) {
    $c = isset($GLOBALS['plume_ponte_in_corso']['rotta']) ? $GLOBALS['plume_ponte_in_corso']['rotta'] : '';
    update_option('plume_ponte_errore', ['quando' => time(), 'rotta' => $c, 'fase' => (string)$fase, 'messaggio' => mb_substr((string)$messaggio, 0, 600)], false);
    if ($fase === 'filtri') update_option('plume_ponte_senza_filtri', 1, false);
}

/** Alla fine della richiesta: un errore fatale in mezzo al lavoro diventa una risposta del ponte con il motivo. */
function plume_ponte_fine() {
    if (empty($GLOBALS['plume_ponte_in_corso'])) return;
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) return;
    $fase = $GLOBALS['plume_ponte_in_corso']['fase'];
    $m = strtok((string)$e['message'], "\n") . ' (' . plume_ponte_dove($e['file'], $e['line']) . ')';
    plume_ponte_segna_errore($m, $fase);
    unset($GLOBALS['plume_ponte_in_corso']);
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) { status_header(500); header('Content-Type: application/json; charset=utf-8'); }
    echo wp_json_encode(['ponte' => 1, 'versione' => PLUME_PONTE_VERSIONE, 'errore' => 'interno', 'fase' => $fase, 'dettaglio' => $m]);
}
