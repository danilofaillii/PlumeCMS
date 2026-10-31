<?php
/**
 * La conferma di un mittente (2.1.0, includes/consegna.php). Il link arriva all'indirizzo del mittente; la pagina chiede
 * un clic sul pulsante (POST): i filtri antispam aprono i link delle email, e non devono confermare al posto della persona.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/posta.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ini_set('display_errors', '0');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');

$t = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : '';
$valido = preg_match('/^[a-f0-9]{64}$/', $t) === 1;
$titolo = 'Conferma il mittente';
$testo = '';
try {
    if (!$valido) {
        $testo = '<p>Il link non è valido. Copialo intero dall&#039;email, oppure chiedi di mandarne uno nuovo dal pannello.</p>';
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $m = consegna_mittente_conferma($t);
        if ($m) {
            $titolo = 'Mittente confermato';
            $testo = '<p>Fatto: <b>' . h((string)$m['email']) . '</b> ora si può usare come mittente delle email del sito.</p><p>Puoi chiudere questa pagina.</p>';
        } else {
            $testo = '<p>Il link è scaduto o è già stato usato. Dal pannello (Consegna delle email, Mittenti) premi "Rimanda la conferma".</p>';
        }
    } else {
        $testo = '<p>Premi il pulsante per confermare che questo indirizzo può spedire le email del sito.</p>'
            . '<form method="post" action="?t=' . h($t) . '"><button type="submit" style="font:inherit;font-weight:700;background:#5b3fd6;color:#fff;border:0;border-radius:10px;padding:14px 26px;cursor:pointer">Confermo il mittente</button></form>';
    }
} catch (Throwable $e) {
    error_log('mittente: ' . $e->getMessage());
    $testo = '<p>Qualcosa non ha funzionato. Riprova fra qualche minuto.</p>';
}
?><!DOCTYPE html>
<html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title><?= h($titolo) ?></title></head>
<body style="margin:0;background:#f4f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2330">
<main style="max-width:520px;margin:48px auto;padding:28px;background:#fff;border-radius:12px;font-size:17px;line-height:1.6">
<h1 style="font-size:24px;margin:0 0 16px"><?= h($titolo) ?></h1>
<?= $testo ?>
</main></body></html>
