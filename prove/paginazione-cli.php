<?php
/**
 * 2.39.2: i dati per prove/prova-paginazione-seo.js (solo prove, mai nel prodotto). Stampa JSON.
 * uso: php prove/paginazione-cli.php <cartella CMS> semina <n>     n articoli pubblicati "articolo-paginato-NN" nella categoria
 *                                                                  "pagine-prova" e con il tag "pagine-prova-tag", ognuno con la
 *                                                                  sua descrizione; la categoria ha una descrizione
 *      php prove/paginazione-cli.php <cartella CMS> blog-editor     la pagina "Blog di prova" (/blog-di-prova/) fatta con l'editor
 *                                                                  (titolo e widget Ultimi articoli), scelta come pagina del blog,
 *                                                                  con titolo e descrizione per Google
 *      php prove/paginazione-cli.php <cartella CMS> seo <gruppo> <chiave> <valore>   un valore di SEO e AEO (per esempio robots
 *                                                                  paginazione noindex, titolo sep |); "azzera" toglie il gruppo
 *      php prove/paginazione-cli.php <cartella CMS> audit           i doppioni di titolo e descrizione della Tabella SEO e di
 *                                                                  site_audit (AI collegate): percorsi con un problema "uguale a"
 *      php prove/paginazione-cli.php <cartella CMS> pulisci         toglie articoli, categoria, tag, pagina e rimette il blog su /blog/
 */
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
$dir = $argv[1] ?? '';
$cmd = $argv[2] ?? '';
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: paginazione-cli.php <cartella CMS> semina|blog-editor|seo|audit|pulisci ...\n"); exit(2); }
require $dir . '/includes/config.php';
require_once $dir . '/includes/sito-seo.php';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
$pdo = db();
$art = tbl('articles'); $cat = tbl('categories'); $tag = tbl('tags'); $pag = tbl('pages');

switch ($cmd) {
    case 'semina':
        $n = max(1, min(200, (int)($argv[3] ?? 30)));
        $pdo->prepare("DELETE FROM $cat WHERE slug = 'pagine-prova'")->execute();
        $pdo->prepare("INSERT INTO $cat (slug, name, description, sort_order) VALUES ('pagine-prova', 'Pagine di prova', 'Gli articoli di prova della paginazione, uno dopo l\\'altro.', 90)")->execute();
        $pdo->prepare("DELETE FROM $tag WHERE slug = 'pagine-prova-tag'")->execute();
        $pdo->prepare("INSERT INTO $tag (slug, name) VALUES ('pagine-prova-tag', 'Pagine prova tag')")->execute();
        $pdo->prepare("DELETE FROM $art WHERE slug LIKE 'articolo-paginato-%'")->execute();
        $ins = $pdo->prepare("INSERT INTO $art (title, slug, status, publish_date, publish_time, content, content_format, meta_description, category, categorie, tag_slugs, tags)
            VALUES (?, ?, 'published', DATE_SUB(CURDATE(), INTERVAL ? DAY), '08:00:00', ?, 'html', ?, 'pagine-prova', 'pagine-prova', 'pagine-prova-tag', 'Pagine prova tag')");
        for ($i = 1; $i <= $n; $i++) {
            $nn = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $ins->execute(["Articolo paginato $nn", "articolo-paginato-$nn", $i + 1, '<p>Il testo dell\'articolo di prova numero ' . $nn . '.</p>',
                "La descrizione dell'articolo di prova numero $nn, diversa da tutte le altre."]);
        }
        $esci(['articoli' => $n]);
    case 'blog-editor':
        require_once $dir . '/includes/editor-controlli.php';
        require_once $dir . '/includes/editor.php';
        editor_carica_widget_esterni();
        $sez = fn(array $w) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => [$w]]]];
        $t = editor_widget('titolo'); $t['impostazioni'] = ['testo' => 'Il blog di prova', 'livello' => 'h1'] + $t['impostazioni'];
        $a = editor_widget('articoli');
        $st = $pdo->prepare("SELECT id FROM $pag WHERE slug = 'blog-di-prova'"); $st->execute(); $id = (int)$st->fetchColumn();
        if (!$id) { $pdo->prepare("INSERT INTO $pag (title, slug, status, publish_at) VALUES ('Blog di prova', 'blog-di-prova', 'published', NOW())")->execute(); $id = (int)$pdo->lastInsertId(); }
        $pdo->prepare("UPDATE $pag SET seo_title = '', seo_description = 'Gli articoli del blog di prova: consigli pratici, novità e storie, ogni settimana.' WHERE id = ?")->execute([$id]);
        editor_salva($id, editor_valida(['elementi' => [$sez($t), $sez($a)]]), true, 'prova', 'pagina di prova della paginazione');
        save_setting('pagina_blog', '/blog-di-prova/');
        $esci(['pagina' => $id, 'blog' => '/blog-di-prova/']);
    case 'seo':
        $g = (string)($argv[3] ?? ''); $k = (string)($argv[4] ?? ''); $v = (string)($argv[5] ?? '');
        $s = json_decode((string)get_setting('seo_config', ''), true);
        if (!is_array($s)) $s = [];
        if ($k === 'azzera') unset($s[$g]); else $s[$g][$k] = $v;
        save_setting('seo_config', json_encode($s, JSON_UNESCAPED_UNICODE));
        $esci(['seo' => $s[$g] ?? null]);
    case 'audit':
        require_once $dir . '/includes/seo-tabella.php';
        $out = ['tabella' => [], 'site_audit' => [], 'percorsi_paginati' => 0];
        foreach (seo_tabella_righe() as $r) {
            if (str_contains((string)$r['percorso'], '/page/')) $out['percorsi_paginati']++;
            foreach (['sem_titolo', 'sem_descrizione'] as $k) if (str_contains(strip_tags((string)($r[$k]['testo'] ?? '')), 'uguale a')) $out['tabella'][] = $r['percorso'] . ' ' . $k;
        }
        $f = $dir . '/addon/ai-collegate/includes/strumenti.php';
        if (is_file($f)) {
            require_once $dir . '/includes/moduli.php';
            require_once $f;
            $a = aic_t_site_audit([], ['limite' => 500]);
            foreach ($a['problemi'] as $p) {
                if (str_contains((string)$p['percorso'], '/page/')) $out['percorsi_paginati']++;
                if (str_contains((string)$p['problema'], 'uguale a')) $out['site_audit'][] = $p['percorso'] . ' ' . $p['problema'];
            }
            $out['site_audit_totali'] = $a['totali'];
        }
        $esci($out);
    case 'pulisci':
        $pdo->prepare("DELETE FROM $art WHERE slug LIKE 'articolo-paginato-%'")->execute();
        $pdo->prepare("DELETE FROM $cat WHERE slug = 'pagine-prova'")->execute();
        $pdo->prepare("DELETE FROM $tag WHERE slug = 'pagine-prova-tag'")->execute();
        $pdo->prepare("DELETE FROM $pag WHERE slug = 'blog-di-prova'")->execute();
        save_setting('pagina_blog', '/blog/');
        $esci(['pulito' => true]);
}
fwrite(STDERR, "comando sconosciuto: $cmd\n");
exit(2);
