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
    header('Location: ' . $dove, true, 303);
    exit;
}

$tipo = (string)($_POST['modulo'] ?? '');
// La pagina da cui arriva il modulo: solo percorsi interni.
$pagina = (string)($_POST['pagina'] ?? '/');
if (!preg_match('~^/[a-z0-9/-]*$~', $pagina)) $pagina = '/';

$form = null;
if (preg_match('/^form:(\d+)$/', $tipo, $m)) { $form = form_leggi((int)$m[1]); $tipo = 'form'; }
// Il modulo stava dentro un popup: con un errore la pagina riapre quel popup.
$popup = ctype_digit((string)($_POST['popup'] ?? '')) ? '&popup=' . (int)$_POST['popup'] : '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($tipo, ['newsletter', 'form'], true) || ($tipo === 'form' && !$form)) modulo_torna('/');

try {
    if ($tipo === 'form') {
        $errore = form_ricevi($form, $_POST, $pagina);
        if ($errore !== '') modulo_torna($pagina . '?modulo=' . rawurlencode($errore) . '&f=' . (int)$form['id'] . $popup . '#modulo-f' . (int)$form['id']);
        // 1.11.0: come va il file del form (dopo la conferma, per email, o il link sulla pagina).
        $om = form_ultimo_omaggio();
        modulo_torna(FORM_PAGINA_GRAZIE . '?f=' . (int)$form['id'] . ($om ? '&o=' . $om['stato'] . (isset($om['k']) ? '&k=' . rawurlencode($om['k']) : '') : ''));
    }
    [$dati, $errore] = moduli_leggi($_POST, false);
    if ($errore === '') $errore = newsletter_richiesta($dati);
} catch (Throwable $e) {
    error_log('modulo: ' . $e->getMessage());
    $errore = 'posta';
}

if ($errore !== '') modulo_torna($pagina . '?modulo=' . $errore . $popup . '#modulo');
modulo_torna(NEWSLETTER_GRAZIE);
