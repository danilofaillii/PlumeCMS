<?php
// Comandi da riga di comando per le prove della Pulizia (2.38.0: prova-pulizia.js, gruppo 51 di tools/sicurezza-prova.js). Stampa JSON.
// uso: php prove/pulizia-cli.php <cartella CMS> <comando> [argomenti]
//   semina           mette sul sito avanzi finti e piccoli: 5 snapshot, 3 pacchetti (2 applicati), 3 copie, una cartella dello scarto,
//                    avanzi del cambio database, log (in uso, archiviati, vecchi, recenti, protetti), file dei contatti (vecchi, recenti,
//                    uno di un'esportazione in corso), 30 versioni di una pagina (più una pubblicata e una con nota, vecchie), due pagine
//                    nel cestino (40 e 5 giorni), righe vecchie nei registri, tabelle di lavoro rip/vec (più una di un'altra installazione
//                    e una senza prefisso), immagini usate (in una pagina, solo in un'impostazione, solo in un form), non usate, forse usate
//   stato            quello che c'è adesso: file, righe, tabelle, peso del database (information_schema) e delle cartelle (du)
//   piani            i piani di partenza delle voci (byte e n), per confrontarli con la pagina
//   marcatore <on|off>  il marcatore di un ripristino in corso (aggiornamenti/copie/.ripristino-in-corso.json)
//   stima <byte>     scrive la stima ricordata per la dashboard (per provare l'avviso)
//   via              toglie quello che "semina" ha lasciato fuori dalle voci (tabelle dell'altra installazione, impostazione, ecc.)
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/pulizia.php';
require_once $argv[1] . '/includes/versioni.php';
$cmd = $argv[2] ?? '';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
$R = rtrim($argv[1], '/');
$up = cms_uploads_dir();
$casuale = fn(int $n) => random_bytes($n);
$data = function (string $f, int $giorni) { @touch($f, time() - $giorni * 86400); };
$immagine = function (string $f, string $tipo = 'png') {
    @mkdir(dirname($f), 0755, true);
    $im = imagecreatetruecolor(40, 30); imagefill($im, 0, 0, imagecolorallocate($im, random_int(0, 255), 120, 200));
    $tipo === 'jpg' ? imagejpeg($im, $f, 80) : imagepng($im, $f);
};
$pdo = db();

if ($cmd === 'semina') {
    // prima togli i resti di una semina di prima (la prova si può rilanciare sullo stesso sito)
    versioni_pronto(); sicurezza_schema();
    $pdo->exec('DELETE FROM ' . tbl('editor_versioni') . ' WHERE oggetto_id IN (900001, 900002)');
    $pdo->exec('DELETE FROM ' . tbl('pages') . " WHERE slug IN ('pulizia-vecchia-cestino', 'pulizia-recente-cestino')");
    $pdo->exec('DELETE FROM ' . tbl('sicurezza_log') . " WHERE utente = 'pul'");
    $pdo->exec('DELETE FROM ' . tbl('sicurezza_tentativi') . " WHERE chiave LIKE 'pulprova:%'");
    try { $pdo->exec('DELETE FROM ' . tbl('seo_log') . " WHERE servizio = 'prova' AND azione = 'pulizia'"); } catch (Throwable $e) {}
    try { $pdo->exec('DELETE FROM ' . tbl('newsletter_esportazioni') . " WHERE descrizione LIKE 'prova pulizia%'"); } catch (Throwable $e) {}
    try { $pdo->exec('DROP TABLE IF EXISTS `' . DB_PREFIX . 'pulprova_vuota`'); } catch (Throwable $e) {}
    // snapshot: 5, uno al giorno, 100 KB ciascuno
    for ($i = 1; $i <= 5; $i++) {
        $id = sprintf('202611%02d-100000-prova-pulizia-%d', $i, $i);
        $d = agg_dir('snapshot') . '/' . $id; @mkdir($d . '/files', 0755, true);
        file_put_contents($d . '/db.sql.gz', $casuale(102400));
        file_put_contents($d . '/meta.json', json_encode(['id' => $id, 'etichetta' => 'prova pulizia ' . $i, 'creato' => sprintf('2026-11-%02d 10:00:00', $i), 'db' => [], 'files' => []]));
    }
    // pacchetti: 2 applicati, 1 in attesa
    foreach ([['20261101-090000-vecchio-a', 'applicato'], ['20261102-090000-vecchio-b', 'applicato'], ['20261103-090000-in-attesa', 'in_attesa']] as [$id, $st]) {
        file_put_contents(agg_dir('pacchetti') . '/' . $id . '.zip', $casuale(51200));
        file_put_contents(agg_dir('pacchetti') . '/' . $id . '.json', json_encode(['id' => $id, 'nome' => 'Pacchetto ' . $id, 'stato' => $st, 'caricato' => '2026-11-01 09:00:00', 'applicato' => $st === 'applicato' ? '2026-11-01 09:01:00' : null, 'applica_dal' => '', 'snapshot' => null, 'manifest' => ['nome' => 'Pacchetto ' . $id, 'descrizione' => 'Pacchetto finto della prova della Pulizia', 'note' => '', 'versione' => '', 'applica_dal' => '', 'file' => [], 'sql' => [], 'redirect' => [], 'articoli' => [], 'azioni' => []], 'log' => []]));
    }
    // copie: 3
    foreach (['20261101-080000', '20261102-080000', '20261103-080000'] as $i => $q) { $f = copia_dir() . '/copia-' . $q . '-prova.zip'; file_put_contents($f, $casuale(40960 * ($i + 1))); touch($f, strtotime(substr($q, 0, 8) . ' 08:00')); }
    // scarto
    $sc = copia_scarto_dir() . '/ripristino-20261101-101010'; @mkdir($sc . '/uploads', 0755, true);
    file_put_contents($sc . '/uploads/vecchia.jpg', $casuale(30720)); file_put_contents($sc . '/LEGGIMI.txt', "prova\n");
    // cambio database
    file_put_contents($R . '/includes/config.local.prima-20261101-101010.php', CAMBIADB_GUARDIA . "<?php define('DB_PASS', 'finta-pulizia');\n");
    @mkdir($R . '/aggiornamenti/cambia-db', 0755, true);
    file_put_contents($R . '/aggiornamenti/cambia-db/ultimo.json', json_encode(['fase' => 'fatto', 'quando' => time() - 86400 * 20, 'backup' => 'config.local.prima-20261101-101010.php', 'dest' => ['nome' => 'altro', 'prefisso' => 'cms_', 'host' => 'x', 'porta' => 3306], 'prima' => ['nome' => DB_NAME]]));
    // log
    $L = $R . '/logs'; @mkdir($L, 0755, true);
    $righe = ''; for ($i = 1; $i <= 500; $i++) $righe .= date('Y-m-d H:i:s') . ' | riga di prova ' . $i . ' ' . str_repeat('x', 60) . "\n";
    file_put_contents($L . '/cron-daily.log', $righe); $data($L . '/cron-daily.log', 2);
    file_put_contents($L . '/php-errori.log', $righe);
    file_put_contents($L . '/vecchio.log.1', $casuale(20480));
    file_put_contents($L . '/fermo.log', $casuale(10240)); $data($L . '/fermo.log', 60);
    file_put_contents($L . '/recente.log', 'recente');
    file_put_contents($L . '/cron-stato.json', '{"prova":1}'); $data($L . '/cron-stato.json', 90);
    file_put_contents($L . '/controllo-accesso.txt', 'sonda'); $data($L . '/controllo-accesso.txt', 90);
    // file dei contatti: la cartella privata del sito
    $nome = (string)get_setting('newsletter_cartella_file', '');
    if (!preg_match('/^contatti-[a-f0-9]{16}$/', $nome)) { $nome = 'contatti-' . bin2hex(random_bytes(8)); save_setting('newsletter_cartella_file', $nome); }
    $C = $up . '/' . $nome; @mkdir($C, 0755, true); file_put_contents($C . '/.htaccess', "Require all denied\n");
    file_put_contents($C . '/esp-1-aaaaaaaaaaaa.csv', $casuale(8192)); $data($C . '/esp-1-aaaaaaaaaaaa.csv', 10);
    file_put_contents($C . '/scarti-2-bbbbbbbbbbbb.csv', $casuale(4096)); $data($C . '/scarti-2-bbbbbbbbbbbb.csv', 12);
    file_put_contents($C . '/esp-3-cccccccccccc.csv', $casuale(2048)); // recente
    file_put_contents($C . '/esp-4-dddddddddddd.csv', $casuale(2048)); $data($C . '/esp-4-dddddddddddd.csv', 20); // in corso
    require_once $R . '/includes/newsletter.php'; require_once $R . '/includes/newsletter-importa.php';
    newsletter_importa_schema();
    $pdo->prepare('INSERT INTO ' . tbl('newsletter_esportazioni') . " (utente_id, descrizione, filtro, colonne, separatore, stato, totale, creato, scade, file) VALUES (1, 'prova pulizia in corso', '{}', '[]', ';', 'in_corso', 1, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'esp-4-dddddddddddd.csv')")->execute();
    $pdo->prepare('INSERT INTO ' . tbl('newsletter_esportazioni') . " (utente_id, descrizione, filtro, colonne, separatore, stato, totale, creato, scade, file) VALUES (1, 'prova pulizia finita', '{}', '[]', ';', 'pronta', 1, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'esp-1-aaaaaaaaaaaa.csv')")->execute();
    // versioni: 30 di una pagina finta (oggetto 900001), più una pubblicata e una con la nota, le più vecchie
    versioni_pronto();
    $ins = $pdo->prepare('INSERT INTO ' . tbl('editor_versioni') . " (ambito, oggetto_id, oggetto_nome, dati, compresso, impronta, autore, nota, nota_mano, pubblicata, creato, dimensione) VALUES ('contenuto:pagine', 900001, 'Pagina prova pulizia', ?, 0, ?, 'prova', ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR), ?)");
    $ins->execute([str_repeat('p', 3000), md5('pub'), '', 0, 1, 200, 3000]);
    $ins->execute([str_repeat('n', 3000), md5('nota'), 'nota a mano', 1, 0, 199, 3000]);
    for ($i = 30; $i >= 1; $i--) $ins->execute([json_encode(['v' => $i, 'testo' => str_repeat('a', 2000)]), md5('v' . $i), '', 0, 0, $i, 2000]);
    // cestino: due pagine
    $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status, html, cestino) VALUES ('Pulizia vecchia nel cestino', 'pulizia-vecchia-cestino', 'draft', ?, DATE_SUB(NOW(), INTERVAL 40 DAY))")->execute([str_repeat('c', 5000)]);
    $pdo->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status, html, cestino) VALUES ('Pulizia recente nel cestino', 'pulizia-recente-cestino', 'draft', '', DATE_SUB(NOW(), INTERVAL 5 DAY))")->execute();
    // registri
    sicurezza_schema();
    // accessi e blocchi a 150 giorni: oltre i 90 della pulizia, sotto i 180 dopo cui sicurezza.php li toglie da solo (una volta su 200)
    $pdo->exec('INSERT INTO ' . tbl('sicurezza_log') . " (evento, utente, ip, dettagli, creato) VALUES ('csrf_rifiutato', 'pul', '1.2.3.4', 'vecchio', DATE_SUB(NOW(), INTERVAL 200 DAY)), ('csrf_rifiutato', 'pul', '1.2.3.4', 'vecchio 2', DATE_SUB(NOW(), INTERVAL 120 DAY)), ('login', 'pul', '1.2.3.4', 'accesso vecchio da tenere', DATE_SUB(NOW(), INTERVAL 150 DAY)), ('blocco', 'pul', '1.2.3.4', 'ip:9.9.9.9 blocco vecchio da tenere', DATE_SUB(NOW(), INTERVAL 150 DAY))");
    $pdo->exec('INSERT INTO ' . tbl('sicurezza_tentativi') . " (chiave, falliti, ultimo, bloccato_fino) VALUES ('pulprova:vecchio', 2, DATE_SUB(NOW(), INTERVAL 3 DAY), NULL), ('pulprova:bloccato', 9, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY))");
    require_once $R . '/includes/seo.php';
    if (function_exists('seo_schema')) seo_schema();
    try { $pdo->exec('INSERT INTO ' . tbl('seo_log') . " (quando, servizio, azione, esito, dettaglio) VALUES (DATE_SUB(NOW(), INTERVAL 150 DAY), 'prova', 'pulizia', 'ok', 'vecchio'), (NOW(), 'prova', 'pulizia', 'ok', 'recente')"); } catch (Throwable $e) {}
    // tabelle di lavoro: due del sito, una di un'altra installazione (cms_b_), una senza il prefisso
    $p = DB_PREFIX;
    $pdo->exec("CREATE TABLE IF NOT EXISTS `rip0a0b0c_{$p}finta` (id INT PRIMARY KEY, t TEXT) ENGINE=InnoDB");
    $pdo->exec("INSERT IGNORE INTO `rip0a0b0c_{$p}finta` VALUES (1, REPEAT('r', 5000)), (2, REPEAT('r', 5000))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `vec0a0b0c_{$p}finta2` (id INT PRIMARY KEY) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}b_settings` (k VARCHAR(10)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}b_users` (k VARCHAR(10)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `rip0a0b0c_{$p}b_users` (k VARCHAR(10)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ripaaaaaa_altrosito_x` (k VARCHAR(10)) ENGINE=InnoDB");
    // una tabella con spazio vuoto dentro (per Ottimizza): 9 MB scritti e cancellati
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}pulprova_vuota` (id INT AUTO_INCREMENT PRIMARY KEY, t MEDIUMTEXT) ENGINE=InnoDB");
    $insV = $pdo->prepare("INSERT INTO `{$p}pulprova_vuota` (t) VALUES (?)");
    for ($i = 0; $i < 3000; $i++) $insV->execute([str_repeat(chr(97 + $i % 26), 3000)]);
    $pdo->exec("DELETE FROM `{$p}pulprova_vuota` WHERE id % 10 <> 0");
    // immagini
    $I = $up . '/2026/11';
    foreach (['usata-pagina.jpg' => 'jpg', 'usata-impostazione.png' => 'png', 'usata-form.png' => 'png', 'non-usata.jpg' => 'jpg', 'non-usata-2.png' => 'png', 'non-usata-2-300x300.png' => 'png', 'forse.jpg' => 'jpg', 'spazio nome.jpg' => 'jpg', 'versione-sola.png' => 'png', 'usata-tema.png' => 'png'] as $n => $t) { $immagine($I . '/' . $n, $t); $data($I . '/' . $n, 2); }
    file_put_contents($I . '/non-usata.jpg.webp', $casuale(1500)); $data($I . '/non-usata.jpg.webp', 2);
    $pg = (int)$pdo->query('SELECT id FROM ' . tbl('pages') . ' WHERE cestino IS NULL ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->prepare('UPDATE ' . tbl('pages') . ' SET html = CONCAT(COALESCE(html, \'\'), ?) WHERE id = ?')->execute(['<img src="' . cms_uploads_url() . '/2026/11/usata-pagina.jpg" alt=""><img src="/uploads/altra-cartella/forse.jpg" alt="">', $pg]);
    save_setting('pul_prova_logo', '{"logo":"\/uploads\/2026\/11\/usata-impostazione.png"}');
    $pdo->prepare('UPDATE ' . tbl('moduli') . ' SET grazie_testo = CONCAT(COALESCE(grazie_testo, \'\'), ?) ORDER BY id LIMIT 1')->execute([' <img src="/uploads/2026/11/usata-form.png">']);
    $pdo->prepare('INSERT INTO ' . tbl('editor_versioni') . " (ambito, oggetto_id, oggetto_nome, dati, compresso, impronta, autore, nota, nota_mano, pubblicata, creato, dimensione) VALUES ('contenuto:pagine', 900002, 'altra', ?, 1, 'x', 'prova', '', 0, 0, NOW(), 10)")->execute([gzcompress('{"src":"/uploads/2026/11/versione-sola.png"}')]);
    @mkdir($R . '/temi/pulprova', 0755, true); file_put_contents($R . '/temi/pulprova/stile.css', '.x{background:url(/uploads/2026/11/usata-tema.png)}');
    $esci(['ok' => true, 'pagina' => $pg, 'contatti' => $nome]);
}

if ($cmd === 'marcatore') {
    $f = copia_marcatore_file();
    if (($argv[3] ?? '') === 'on') file_put_contents($f, json_encode(['prefisso' => DB_PREFIX, 'appoggio' => [['rip0a0b0c_', 'finta']], 'parcheggio' => [], 'quando' => date('Y-m-d H:i:s')]));
    else @unlink($f);
    $esci(['marcatore' => is_file($f)]);
}

if ($cmd === 'piani') {
    pulizia_scadenza(true, 60);
    $o = [];
    foreach (pulizia_ids() as $id) { [$p] = pulizia_parametri($id, null); $pi = pulizia_piano($id, $p, $id === 'media' ? pulizia_media_controlla() : null); $o[$id] = ['byte' => $pi['byte'], 'n' => $pi['n'], 'righe' => $pi['righe'], 'bloccata' => $pi['bloccata'], 'chiavi' => array_map(fn($e) => $e['chiave'], pulizia_proposti($pi)), 'tutti' => array_map(fn($e) => $e['chiave'], $pi['elementi'])]; }
    $m = pulizia_media_controlla();
    $o['media_forse'] = array_map(fn($f) => $f['motivo'], $m['forse']);
    $esci($o);
}

if ($cmd === 'stato') {
    $du = function (string $d) { if (!is_dir($d)) return 0; $t = 0; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS)) as $f) if ($f->isFile()) $t += $f->getSize(); return $t; };
    $ls = fn(string $d) => is_dir($d) ? array_values(array_diff(scandir($d), ['.', '..'])) : [];
    $nome = (string)get_setting('newsletter_cartella_file', '');
    $tab = array_map(fn($r) => (string)$r[0], $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));
    $uno = fn(string $q) => $pdo->query(str_replace('{p}', DB_PREFIX, $q))->fetchColumn();
    try { $pdo->exec('SET SESSION information_schema_stats_expiry = 0'); } catch (Throwable $e) {}
    $st = $pdo->prepare('SELECT TABLE_NAME, DATA_LENGTH + INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?'); $st->execute([DB_NAME]);
    $esci([
        'snapshot' => $ls(agg_dir('snapshot')), 'du_snapshot' => $du(agg_dir('snapshot')),
        'pacchetti' => $ls(agg_dir('pacchetti')), 'copie' => $ls(copia_dir()), 'scarto' => $ls(copia_scarto_dir()),
        'includes_prima' => array_values(array_filter($ls($R . '/includes'), fn($f) => str_starts_with($f, 'config.local'))),
        'cambiadb' => $ls($R . '/aggiornamenti/cambia-db'), 'logs' => $ls($R . '/logs'),
        'cron_mtime' => is_file($R . '/logs/cron-daily.log') ? filemtime($R . '/logs/cron-daily.log') : 0,
        'cron_righe' => is_file($R . '/logs/cron-daily.log') ? count(file($R . '/logs/cron-daily.log')) : 0,
        'contatti' => $nome ? $ls($up . '/' . $nome) : [], 'immagini' => $ls($up . '/2026/11'),
        'versioni_900001' => (int)$uno("SELECT COUNT(*) FROM {p}editor_versioni WHERE oggetto_id = 900001"),
        'versioni_protette' => (int)$uno("SELECT COUNT(*) FROM {p}editor_versioni WHERE oggetto_id = 900001 AND (pubblicata = 1 OR nota_mano = 1)"),
        'cestino' => $pdo->query('SELECT slug FROM ' . tbl('pages') . ' WHERE cestino IS NOT NULL ORDER BY slug')->fetchAll(PDO::FETCH_COLUMN),
        'log_login' => (int)$uno("SELECT COUNT(*) FROM {p}sicurezza_log WHERE utente = 'pul' AND evento IN ('login','blocco')"),
        'log_vecchi' => (int)$uno("SELECT COUNT(*) FROM {p}sicurezza_log WHERE utente = 'pul' AND evento = 'csrf_rifiutato'"),
        'tentativi' => $pdo->query('SELECT chiave FROM ' . tbl('sicurezza_tentativi') . " WHERE chiave LIKE 'pulprova:%' ORDER BY chiave")->fetchAll(PDO::FETCH_COLUMN),
        'esportazioni' => $pdo->query('SELECT file FROM ' . tbl('newsletter_esportazioni') . " WHERE descrizione LIKE 'prova pulizia%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN),
        'lavoro' => array_values(array_filter($tab, fn($t) => preg_match('/^(rip|vec)[0-9a-f]{6}_/', $t))),
        'tabelle' => count($tab), 'pesi' => array_map('intval', array_column($st->fetchAll(PDO::FETCH_NUM), 1, 0)),
        'marcatore' => is_file(copia_marcatore_file()),
    ]);
}

if ($cmd === 'stima') { save_setting('pulizia_stima', json_encode(['quando' => time(), 'byte' => (int)($argv[3] ?? 0)])); $esci(['ok' => true]); }

if ($cmd === 'via') {
    $p = DB_PREFIX;
    foreach (["{$p}pulprova_vuota", "{$p}b_settings", "{$p}b_users", "rip0a0b0c_{$p}b_users", 'ripaaaaaa_altrosito_x'] as $t) $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`');
    $pdo->exec('DELETE FROM ' . tbl('settings') . " WHERE setting_key = 'pul_prova_logo'");
    @unlink($R . '/temi/pulprova/stile.css'); @rmdir($R . '/temi/pulprova');
    $esci(['ok' => true]);
}
$esci(['errore' => 'comando sconosciuto']);
