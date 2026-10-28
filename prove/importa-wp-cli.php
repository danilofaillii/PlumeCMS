<?php
/**
 * Aiuto per prove/prova-importa-wp.js: legge il CMS installato e stampa JSON. Solo per le prove, mai nel prodotto.
 *   php prove/importa-wp-cli.php <cartella CMS> foto        conteggi di tabelle e file (per "annulla rimette tutto com'era")
 *   php prove/importa-wp-cli.php <cartella CMS> contenuti   articoli e pagine con i campi da confrontare con WordPress
 *   php prove/importa-wp-cli.php <cartella CMS> menu        i menu con le voci
 *   php prove/importa-wp-cli.php <cartella CMS> stato       lo stato dell'import (senza il segreto) e se contiene la password
 *   php prove/importa-wp-cli.php <cartella CMS> dimentica   toglie la password cifrata (come dopo un giorno fermo)
 *   php prove/importa-wp-cli.php <cartella CMS> cerca <file> il testo del file (una password) compare nel database o nei log?
 */
if (PHP_SAPI !== 'cli') exit;
[$_, $cms, $cosa] = $argv + [null, null, ''];
chdir($cms);
require "$cms/includes/config.php";
require_once "$cms/includes/db.php";
$out = [];
switch ($cosa) {
    case 'foto':
        foreach (['articles', 'pages', 'categories', 'tags', 'menu', 'redirect', 'revisioni'] as $t) $out[$t] = (int)db()->query('SELECT COUNT(*) FROM ' . tbl($t))->fetchColumn();
        $out['menu_posizioni'] = db()->query('SELECT id, posizione FROM ' . tbl('menu') . ' ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $file = [];
        $up = cms_uploads_dir();
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up, FilesystemIterator::SKIP_DOTS)) as $f) if ($f->isFile()) $file[] = substr($f->getPathname(), strlen($up));
        sort($file);
        $out['file'] = $file;
        $out['php_in_uploads'] = count(array_filter($file, fn($f) => preg_match('/\.(php\d?|phtml|phar)$/i', $f)));
        $out['nascosti'] = (string)get_setting('importa_wp_nascosti', '');
        break;
    case 'contenuti':
        foreach (['articles' => 'articolo', 'pages' => 'pagina'] as $t => $g) {
            foreach (db()->query('SELECT * FROM ' . tbl($t) . ' ORDER BY id')->fetchAll() as $r) {
                $out[] = ['genere' => $g, 'id' => (int)$r['id'], 'titolo' => $r['title'], 'slug' => $r['slug'], 'status' => $r['status'],
                    'data' => $t === 'pages' ? substr((string)$r['publish_at'], 0, 16) : $r['publish_date'] . ' ' . substr((string)$r['publish_time'], 0, 5),
                    'categorie' => (string)($r['categorie'] ?? ''), 'tags' => (string)($r['tags'] ?? ''), 'immagine' => (string)$r['immagine_url'], 'autore' => (string)($r['author'] ?? ''),
                    'seo_title' => (string)$r['seo_title'], 'seo_description' => (string)$r['seo_description'], 'excerpt' => (string)($r['excerpt'] ?? ''),
                    'testo' => (string)($t === 'pages' ? $r['html'] : $r['content'])];
            }
        }
        break;
    case 'menu':
        foreach (db()->query('SELECT * FROM ' . tbl('menu') . ' ORDER BY id')->fetchAll() as $r) $out[] = ['id' => (int)$r['id'], 'nome' => $r['nome'], 'posizione' => $r['posizione'], 'voci' => json_decode((string)$r['voci'], true)];
        break;
    case 'stato':
        $s = json_decode((string)get_setting('importa_wp_stato', ''), true) ?: [];
        $out = ['fase' => $s['fase'] ?? '', 'segreto' => isset($s['segreto']), 'lavoro' => $s['lavoro'] ?? '', 'cursore' => $s['cursore'] ?? null];
        break;
    case 'dimentica':
        // Come dopo un giorno senza lavoro: la password cifrata si toglie dallo stato.
        $s = json_decode((string)get_setting('importa_wp_stato', ''), true) ?: [];
        unset($s['segreto']);
        save_setting('importa_wp_stato', (string)json_encode($s, JSON_UNESCAPED_SLASHES));
        $out = ['fatto' => true];
        break;
    case 'cerca':
        // La password (con e senza spazi) non deve comparire in chiaro da nessuna parte: impostazioni, registro, log.
        $p = trim((string)file_get_contents($argv[3]));
        $varianti = array_unique([$p, preg_replace('/\s+/', '', $p), base64_encode('admin:' . preg_replace('/\s+/', '', $p))]);
        $trovati = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM ' . tbl('settings'))->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) foreach ($varianti as $x) if ($x !== '' && str_contains((string)$v, $x)) $trovati[] = "impostazione $k";
        foreach (db()->query('SELECT dettagli FROM ' . tbl('sicurezza_log'))->fetchAll(PDO::FETCH_COLUMN) as $v) foreach ($varianti as $x) if ($x !== '' && str_contains((string)$v, $x)) $trovati[] = 'registro della sicurezza';
        foreach (glob("$cms/logs/*") ?: [] as $f) if (is_file($f)) { $t = (string)file_get_contents($f); foreach ($varianti as $x) if ($x !== '' && str_contains($t, $x)) $trovati[] = 'log ' . basename($f); }
        $out = ['trovati' => array_values(array_unique($trovati))];
        break;
    default:
        fwrite(STDERR, "cosa: foto | contenuti | menu | stato | cerca <file>\n");
        exit(1);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
