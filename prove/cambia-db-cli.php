<?php
/**
 * Aiuto da riga di comando per prove/prova-cambia-db.js (2.37.0). Non carica il CMS (durante un cambio config.php ferma i cron):
 * legge il config.local.php del sito e si collega da sé.
 *   php cambia-db-cli.php semina <cartella cms>          contatti, un invio di form, una tabella con colonne generate e
 *                                                        collazione utf8mb4_0900_ai_ci (3000 righe), una senza chiave primaria
 *   php cambia-db-cli.php conte <cartella cms>           {tabella: righe} delle tabelle del prefisso, dal database del config
 *   php cambia-db-cli.php conte-db <host> <porta> <db> <utente> <password> <prefisso>
 *   php cambia-db-cli.php impronte <cartella cms>        {tabella: md5 di tutte le righe in ordine}
 *   php cambia-db-cli.php impronte-db <host> <porta> <db> <utente> <password> <prefisso>
 *   php cambia-db-cli.php config <cartella cms>          {DB_HOST, DB_NAME, DB_USER, CMS_PREFISSO} (mai la password)
 *   php cambia-db-cli.php sql-db <host> <porta> <db> <utente> <password> <sql>   una istruzione sul database dato
 */
$cmd = $argv[1] ?? '';
function cfg(string $cms): array {
    $t = (string)file_get_contents($cms . '/includes/config.local.php');
    $v = [];
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'CMS_PREFISSO'] as $k) if (preg_match("/define\\('" . $k . "',\\s*'((?:[^'\\\\]|\\\\.)*)'\\)/", $t, $m)) $v[$k] = strtr($m[1], ['\\\\' => '\\', "\\'" => "'"]);
    return $v;
}
function pdo_da(string $host, int $porta, string $db, string $u, string $p): PDO {
    $pdo = new PDO('mysql:host=' . $host . ';port=' . $porta . ';dbname=' . $db . ';charset=utf8mb4', $u, $p, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
function pdo_cfg(array $c): PDO {
    $h = $c['DB_HOST']; $porta = 3306;
    if (preg_match('/^(.*?);port=(\d+)$/', $h, $m)) { $h = $m[1]; $porta = (int)$m[2]; }
    return pdo_da($h, $porta, $c['DB_NAME'], $c['DB_USER'], $c['DB_PASS']);
}
function tabelle(PDO $pdo, string $pref): array {
    $out = [];
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $r) if ($r[1] !== 'VIEW' && str_starts_with($r[0], $pref)) $out[] = $r[0];
    sort($out);
    return $out;
}
function conte(PDO $pdo, string $pref): array {
    $o = [];
    foreach (tabelle($pdo, $pref) as $t) $o[substr($t, strlen($pref))] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    return $o;
}
function impronte(PDO $pdo, string $pref): array {
    $o = [];
    foreach (tabelle($pdo, $pref) as $t) {
        $h = hash_init('md5');
        $righe = [];
        foreach ($pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_NUM) as $r) $righe[] = json_encode(array_map(fn($x) => $x === null ? null : bin2hex((string)$x), $r));
        sort($righe); // ordine indipendente dal server (le tabelle senza chiave primaria)
        foreach ($righe as $r) hash_update($h, $r . "\n");
        $o[substr($t, strlen($pref))] = hash_final($h);
    }
    return $o;
}
if ($cmd === 'semina') {
    $c = cfg($argv[2]); $pdo = pdo_cfg($c); $p = $c['CMS_PREFISSO'];
    for ($i = 1; $i <= 3; $i++) $pdo->exec("INSERT IGNORE INTO `{$p}newsletter_iscritti` (email, nome, stato, creato, aggiornato) VALUES ('contatto$i@esempio.it', 'Contatto $i', 'confermato', NOW(), NOW())");
    $pdo->exec("INSERT INTO `{$p}moduli_invii` (modulo_id, email, nome, dati, pagina, creato, aggiornato) VALUES (1, 'maria@esempio.it', 'Maria Rossi', '{\"Messaggio\":\"Vorrei un preventivo, grazie! L\\'ufficio è aperto?\"}', '/contatti/', NOW(), NOW())");
    $pdo->exec("DROP TABLE IF EXISTS `{$p}prova_generate`");
    $pdo->exec("CREATE TABLE `{$p}prova_generate` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `n` INT NOT NULL, `doppio` INT GENERATED ALWAYS AS (`n` * 2) STORED, `testo` VARCHAR(200) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '', `dati` BLOB, `creato` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY `k_testo` (`testo`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    $st = $pdo->prepare("INSERT INTO `{$p}prova_generate` (n, testo, dati, creato) VALUES (?, ?, ?, ?)");
    $pdo->beginTransaction();
    for ($i = 1; $i <= 3000; $i++) $st->execute([$i, "Riga $i: perché l'«apostrofo» \\ e \"virgolette\";\nDROP TABLE x; -- " . str_repeat('è', $i % 50), random_bytes(16) . "\0\x1a'", '2025-06-' . sprintf('%02d', 1 + $i % 28) . ' 10:' . sprintf('%02d', $i % 60) . ':00']);
    $pdo->commit();
    $pdo->exec("DROP TABLE IF EXISTS `{$p}prova_senza_pk`");
    $pdo->exec("CREATE TABLE `{$p}prova_senza_pk` (`a` VARCHAR(40) NOT NULL, `b` INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st = $pdo->prepare("INSERT INTO `{$p}prova_senza_pk` VALUES (?, ?)");
    for ($i = 1; $i <= 700; $i++) $st->execute(['valore ' . ($i % 37), $i % 5 ? $i : null]);
    echo "seminato\n";
} elseif ($cmd === 'conte') {
    $c = cfg($argv[2]); echo json_encode(conte(pdo_cfg($c), $c['CMS_PREFISSO'])), "\n";
} elseif ($cmd === 'conte-db') {
    echo json_encode(conte(pdo_da($argv[2], (int)$argv[3], $argv[4], $argv[5], $argv[6]), $argv[7])), "\n";
} elseif ($cmd === 'impronte') {
    $c = cfg($argv[2]); echo json_encode(impronte(pdo_cfg($c), $c['CMS_PREFISSO'])), "\n";
} elseif ($cmd === 'impronte-db') {
    echo json_encode(impronte(pdo_da($argv[2], (int)$argv[3], $argv[4], $argv[5], $argv[6]), $argv[7])), "\n";
} elseif ($cmd === 'config') {
    $c = cfg($argv[2]); unset($c['DB_PASS']); echo json_encode($c), "\n";
} elseif ($cmd === 'sql-db') {
    $pdo = pdo_da($argv[2], (int)$argv[3], $argv[4], $argv[5], $argv[6]);
    $st = $pdo->query($argv[7]);
    echo json_encode($st->columnCount() ? $st->fetchAll(PDO::FETCH_NUM) : ['ok']), "\n";
} else { fwrite(STDERR, "comando sconosciuto\n"); exit(1); }
