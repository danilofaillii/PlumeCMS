<?php
/**
 * Legge o scrive un'impostazione di un CMS installato, da riga di comando (per le prove, mai nel prodotto).
 * uso: php prove/settings-cli.php <cartella CMS> leggi <chiave>
 *      php prove/settings-cli.php <cartella CMS> scrivi <chiave> <valore>
 */
[$dir, $azione, $chiave] = array_pad(array_slice($argv, 1, 3), 3, '');
if ($dir === '' || !in_array($azione, ['leggi', 'scrivi'], true) || $chiave === '') {
    fwrite(STDERR, "uso: settings-cli.php <cartella CMS> leggi|scrivi <chiave> [valore]\n");
    exit(2);
}
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
if ($azione === 'leggi') {
    echo get_setting($chiave, ''), "\n";
} else {
    save_setting($chiave, (string)($argv[4] ?? ''));
}
