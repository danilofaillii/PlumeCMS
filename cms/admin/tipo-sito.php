<?php
/**
 * Le azioni dei riquadri nuovi della dashboard (1.4.0): cambiare il tipo di sito in prova, renderlo definitivo,
 * l'email di prova dei Primi passi, nascondere i Primi passi. Solo POST, solo amministratore (la pagina non sta fra
 * quelle del redattore: require_login gli risponde 403), sempre con il token CSRF. Poi si torna alla dashboard.
 * Il lavoro sta in includes/kit-partenza.php (kit_cambia, kit_definitivo), includes/controllo-server.php e
 * includes/primi-passi.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once dirname(__DIR__) . '/includes/kit-partenza.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
verify_csrf();
$azione = (string)($_POST['azione'] ?? '');
$autore = (string)(current_user()['username'] ?? '');
$ritorno = ($_POST['ritorno'] ?? '') === 'controlla' ? 'controlla-server.php' : 'index.php';

try {
    if ($azione === 'cambia') {
        $nuovo = (string)($_POST['kit'] ?? '');
        $nome = kit_elenco()[$nuovo]['nome'] ?? '';
        session_write_close(); // la copia del sito puo durare: le altre schede del pannello non aspettano
        ob_start();
        $r = kit_cambia($nuovo, $autore);
        ob_end_clean();
        @session_start();
        flash('success', 'Il sito ora è ' . $nome . '. Prima del cambio il pannello ha fatto una copia del sito (' . $r['copia'] . ', in Impostazioni, Copia del sito).');
        header('Location: index.php#tipo-sito'); exit;
    } elseif ($azione === 'definitivo') {
        kit_definitivo($autore);
        flash('success', 'Fatto: il tipo di sito è definitivo. Da qui in poi lo cambi pagina per pagina con l\'editor.');
    } elseif ($azione === 'email_prova') {
        require_once dirname(__DIR__) . '/includes/controllo-server.php';
        [$ok, $frase] = server_email_prova((string)(current_user()['email'] ?? ''));
        flash($ok ? 'success' : 'error', $frase);
        header('Location: ' . $ritorno . ($ritorno === 'index.php' ? '#primi-passi' : '')); exit;
    } elseif ($azione === 'nascondi_passi') {
        require_once dirname(__DIR__) . '/includes/primi-passi.php';
        if (!primi_passi_nascondi()) throw new RuntimeException('I primi passi si nascondono quando sono tutti fatti.');
        flash('success', 'Primi passi completati: la lista non compare più.');
    } else {
        throw new RuntimeException('Azione sconosciuta.');
    }
} catch (Throwable $e) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    while (ob_get_level() > 1) ob_end_clean();
    flash('error', $e->getMessage());
}
header('Location: ' . $ritorno);
exit;
