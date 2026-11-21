<?php
/**
 * Arrivo dei moduli del sito: la newsletter e i form costruiti dal pannello. Funziona anche senza JavaScript
 * nel modulo (solo il captcha lo richiede): POST normale, poi redirect alla pagina giusta.
 * Con un errore si torna alla pagina del modulo con ?modulo=errore-codice#modulo.
 */
require_once __DIR__ . '/../includes/moduli.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/form.php';

function modulo_torna(string $dove): never {
    // 2.1: con il sito in una cartella (https://esempio.it/test) i percorsi del sito ("/contatti/") prendono la cartella.
    // Prima un form con un errore, o il grazie, portavano alla radice del dominio, fuori dal sito.
    $b = cms_sito_base();
    if ($b !== '' && str_starts_with($dove, '/') && !str_starts_with($dove, '//')) $dove = $b . $dove; // chi chiama passa sempre percorsi del sito, senza cartella
    header('Location: ' . $dove, true, 303);
    exit;
}

// 2.1: un form con un file troppo grande per il server (oltre post_max_size) arriva senza campi: si torna alla pagina
// da cui viene, con l'errore del file, invece che alla home senza spiegazioni.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $da = (string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
    $base = cms_sito_base();
    if ($base !== '' && str_starts_with($da, $base . '/')) $da = substr($da, strlen($base));
    modulo_torna((preg_match('~^/[a-z0-9/-]*$~', $da) ? $da : '/') . '?modulo=file_peso#modulo');
}

$tipo = (string)($_POST['modulo'] ?? '');
// La pagina da cui arriva il modulo: solo percorsi interni.
$pagina = (string)($_POST['pagina'] ?? '/');
if (!preg_match('~^/[a-z0-9/-]*$~', $pagina)) $pagina = '/';

$form = null;
if (preg_match('/^form:(\d+)$/', $tipo, $m)) { $form = form_leggi((int)$m[1]); $tipo = 'form'; }
// Il modulo stava dentro un popup: con un errore la pagina riapre quel popup.
$popup = ctype_digit((string)($_POST['popup'] ?? '')) ? '&popup=' . (int)$_POST['popup'] : '';
// 2.1: il widget Richiesta di prenotazione (includes/richiesta-prenotazione.php): torna alla sua pagina, con l'esito.
if ($tipo === 'prenota' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_once __DIR__ . '/../includes/richiesta-prenotazione.php';
    $w = preg_match('/^[a-z0-9]{1,12}$/', (string)($_POST['rp_w'] ?? '')) ? (string)$_POST['rp_w'] : '';
    try { $errore = rp_ricevi($_POST, $pagina); } catch (Throwable $e) { error_log('richiesta di prenotazione: ' . $e->getMessage()); $errore = 'posta'; }
    modulo_torna($pagina . '?prenota=' . rawurlencode($errore === '' ? 'ok' : $errore) . '&w=' . $w . '#prenota-' . $w);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($tipo, ['newsletter', 'form'], true) || ($tipo === 'form' && !$form)) modulo_torna('/');

try {
    if ($tipo === 'form') {
        $errore = form_ricevi($form, $_POST, $pagina, $_FILES);
        $fid = (int)$form['id'];
        // 2.16.0: dalla pagina ospitata o da un codice incorporato si torna alla pagina ospitata (anche dentro un iframe di un
        // altro sito: è l'unica che ci può stare); un form di profilo si porta dietro il suo link firmato.
        $ospitata = !empty($_POST['ospitata']) && function_exists('fb_url_ospitata');
        if ($ospitata) $pagina = '/modulo/' . $form['slug'] . '/';
        $link = '';
        if (($form['tipo'] ?? '') === 'profilo' && is_string($_POST['pi'] ?? null) && ctype_digit($_POST['pi']) && is_string($_POST['pt'] ?? null) && preg_match('/^[a-f0-9]{32}$/', $_POST['pt'])) $link = '&i=' . (int)$_POST['pi'] . '&t=' . $_POST['pt'];
        // 2.16.0: HTML semplice, senza JavaScript: niente captcha nel browser, la verifica è una domanda nella pagina "ultimo passo".
        if (in_array($errore, ['captcha', 'domanda'], true) && $ospitata && (!empty($_POST['semplice']) || isset($_POST['domanda_risposta']))) {
            require_once __DIR__ . '/../includes/sito-seo.php';
            require_once __DIR__ . '/../sito/viste.php';
            fb_intestazioni_ospitata();
            header('Content-Type: text/html; charset=UTF-8');
            echo fb_pagina_verifica($form, $_POST, $errore === 'domanda');
            exit;
        }
        if ($errore !== '') modulo_torna($pagina . '?modulo=' . rawurlencode($errore) . '&f=' . $fid . $popup . $link . '#modulo-f' . $fid);
        // 2.0.0: come va il file del form (dopo la conferma, per email, o il link sulla pagina).
        $om = form_ultimo_omaggio();
        $omq = $om ? '&o=' . $om['stato'] . (isset($om['k']) ? '&k=' . rawurlencode($om['k']) : '') : '';
        // 2.16.0: Messaggi, "Dopo l'invio": un altro indirizzo, un messaggio al posto del form, o la pagina di ringraziamento.
        $dopo = (string)($form['dopo_invio'] ?? 'pagina');
        if ($dopo === 'url' && ($u = (string)($form['dopo_url'] ?? '')) !== '') {
            if (str_starts_with($u, '/') && !str_starts_with($u, '//')) modulo_torna($u);
            header('Location: ' . $u, true, 303);
            exit;
        }
        if ($ospitata) modulo_torna($pagina . '?fatto=1&f=' . $fid . $omq . $link);
        if ($dopo === 'messaggio') modulo_torna($pagina . '?modulo=ok&f=' . $fid . $omq . $popup . '#modulo-f' . $fid);
        modulo_torna(FORM_PAGINA_GRAZIE . '?f=' . $fid . $omq);
    }
    [$dati, $errore] = moduli_leggi($_POST, false);
    if ($errore === '') $errore = newsletter_richiesta($dati);
} catch (Throwable $e) {
    error_log('modulo: ' . $e->getMessage());
    $errore = 'posta';
}

if ($errore !== '') modulo_torna($pagina . '?modulo=' . $errore . $popup . '#modulo');
modulo_torna(NEWSLETTER_GRAZIE);
