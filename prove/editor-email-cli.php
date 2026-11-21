<?php
/**
 * Aiuti da riga di comando per prova-editor-email.js (2.12.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/editor-email-cli.php <cartella CMS> <comando> [argomenti]
 *   pulisci                    toglie campagne, modelli e sezioni salvate della prova ("EE ..."), i contatti @ee-prova.it, il modello di conferma
 *   design <campagna|m:modello>  il design salvato, pulito, in JSON
 *   pulito <file json>         il design di quel file dopo nd_design() (la pulizia del server)
 *   link <valore>              nd_link() del valore
 *   html <campagna> <contatto> l'email per quel contatto (oggetto e html, senza tracciamento)
 *   png <file> <larghezza> <altezza>  scrive un PNG di prova
 *   doi <email> <nome> [titolo del form]  una richiesta di iscrizione (l'email di conferma parte); col titolo, come un form con la sua email
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: editor-email-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
require_once $dir . '/includes/newsletter-design.php';
nd_schema();
$t = fn($n) => tbl($n);
$out = null;
switch ($cmd) {
case 'pulisci':
    $camp = db()->query('SELECT id FROM ' . $t('newsletter_campagne') . " WHERE nome LIKE 'EE %' OR nome LIKE 'Copia di EE %'")->fetchAll(PDO::FETCH_COLUMN);
    if ($camp) { $in = implode(',', array_map('intval', $camp)); foreach (['newsletter_coda', 'newsletter_eventi'] as $x) db()->exec('DELETE FROM ' . $t($x) . " WHERE campagna_id IN ($in)"); db()->exec('DELETE FROM ' . $t('newsletter_campagne') . " WHERE id IN ($in)"); }
    db()->exec('DELETE FROM ' . $t('newsletter_modelli') . " WHERE nome LIKE 'EE %' OR nome LIKE 'Copia di EE %' OR origine <> ''");
    db()->exec('DELETE FROM ' . $t('newsletter_sezioni_salvate') . " WHERE nome LIKE 'EE %'");
    $ids = db()->query('SELECT id FROM ' . $t('newsletter_iscritti') . " WHERE email LIKE '%@ee-prova.it'")->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) newsletter_togli_iscritti($ids);
    save_setting('newsletter_doi_modello', '0');
    $out = count($camp);
    break;
case 'design':
    $a = (string)($arg[0] ?? '');
    $r = str_starts_with($a, 'm:') ? nd_modello((int)substr($a, 2)) : newsletter_campagna((int)$a);
    $out = $r ? ['editor' => $r['editor'], 'design' => nd_design((string)$r['design']), 'grezzo' => (string)$r['design']] : null;
    break;
case 'pulito':
    $out = nd_design((string)file_get_contents($arg[0]));
    break;
case 'link':
    $out = nd_link((string)($arg[0] ?? ''));
    break;
case 'html':
    $c = newsletter_campagna((int)$arg[0]);
    $i = newsletter_contatto((int)$arg[1]);
    [$o, $h] = newsletter_email($c, ['id' => (int)$i['id'], 'nome' => $i['nome'], 'email' => $i['email']], []);
    $out = ['oggetto' => $o, 'html' => $h];
    break;
case 'png':
    $im = imagecreatetruecolor((int)$arg[1], (int)$arg[2]);
    for ($y = 0; $y < (int)$arg[2]; $y++) imageline($im, 0, $y, (int)$arg[1], $y, imagecolorallocate($im, 30 + (int)(120 * $y / (int)$arg[2]), 90, 160));
    imagefilledellipse($im, (int)($arg[1] / 2), (int)($arg[2] / 2), (int)($arg[2] / 2), (int)($arg[2] / 2), imagecolorallocate($im, 250, 200, 60));
    imagepng($im, $arg[0]);
    $out = true;
    break;
case 'doi':
    $opz = isset($arg[2]) ? ['email' => ['titolo' => $arg[2]]] : [];
    $_SERVER['REMOTE_ADDR'] = '10.' . random_int(1, 254) . '.' . random_int(1, 254) . '.' . random_int(1, 254); // il limite delle richieste per indirizzo
    $out = newsletter_richiesta(['email' => $arg[0], 'nome' => $arg[1] ?? ''], $opz);
    break;
default:
    fwrite(STDERR, "comando sconosciuto\n"); exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
