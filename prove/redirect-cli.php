<?php
/**
 * Redirect e articoli di prova per prova-robots-articoli.js (solo prove, mai nel prodotto).
 * uso: php prove/redirect-cli.php <cartella CMS> aggiungi <da> <a> <confronto>
 *      php prove/redirect-cli.php <cartella CMS> togli <da>
 *      php prove/redirect-cli.php <cartella CMS> bozza <slug di un articolo esistente> <slug nuovo>   (copia in bozza)
 *      php prove/redirect-cli.php <cartella CMS> togli-articolo <slug>
 */
[$dir, $azione, $x, $y, $z] = array_pad(array_slice($argv, 1, 5), 5, '');
if ($dir === '' || $azione === '') { fwrite(STDERR, "uso: redirect-cli.php <cartella CMS> aggiungi|togli|bozza|togli-articolo ...\n"); exit(2); }
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
$t = tbl('redirect'); $a = tbl('articles');
if ($azione === 'aggiungi') {
    db()->prepare("DELETE FROM $t WHERE da = ?")->execute([$x]);
    db()->prepare("INSERT INTO $t (da, a, codice, confronto, attivo, origine) VALUES (?, ?, 301, ?, 1, 'prova')")->execute([$x, $y, $z ?: 'exact']);
} elseif ($azione === 'togli') {
    db()->prepare("DELETE FROM $t WHERE da = ?")->execute([$x]);
} elseif ($azione === 'bozza') {
    $st = db()->prepare("SELECT * FROM $a WHERE slug = ? LIMIT 1"); $st->execute([$x]); $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { fwrite(STDERR, "articolo $x non trovato\n"); exit(1); }
    db()->prepare("DELETE FROM $a WHERE slug = ?")->execute([$y]);
    unset($r['id']); $r['slug'] = $y; $r['status'] = 'draft'; $r['title'] .= ' (bozza)';
    if (array_key_exists('wp_id', $r)) $r['wp_id'] = null;
    foreach (['created_at', 'updated_at'] as $c) unset($r[$c]);
    $col = array_keys($r);
    db()->prepare("INSERT INTO $a (`" . implode('`,`', $col) . "`) VALUES (" . implode(',', array_fill(0, count($col), '?')) . ")")->execute(array_values($r));
} elseif ($azione === 'togli-articolo') {
    db()->prepare("DELETE FROM $a WHERE slug = ?")->execute([$x]);
}
echo "ok\n";
