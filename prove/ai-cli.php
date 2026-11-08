<?php
// Comandi da riga di comando per le prove dell'addon AI collegate (2.32.0: prova-ai-collegate.js, gruppo 46 di
// tools/sicurezza-prova.js). Stampa JSON.
// uso: php prove/ai-cli.php <cartella CMS> <comando> [argomenti]
//   accendi | spegni            accende o spegne l'addon (moduli/addon-attivi.json) e crea le tabelle
//   statistiche <accendi|spegni> accende o spegne l'addon Statistiche (per lo strumento get_stats)
//   chiave <permessi> [nome]    una chiave personale con quei permessi (separati da virgola); stampa la chiave in chiaro
//   token-scaduto <chiave>      fa scadere tutti i token della connessione di quella chiave (o token di accesso)
//   token-altro-sito <chiave>   cambia l'audience dei token di quella connessione in un altro sito
//   sql <query>                 una query con {p} al posto del prefisso (SELECT stampa le righe)
//   redattore <crea|elimina>    l'utente redattore "ai_prova_red" (password Red-AI-Prova-2026)
//   admin2fa <crea|elimina>     un amministratore "ai_prova_2fa" (password Adm-AI-Prova-2026) con il secondo fattore acceso; stampa il segreto
//   totp <segreto>              il codice a sei cifre di adesso per quel segreto
//   pagina-pubblicata <slug>    una pagina pubblicata fatta con l'editor (titolo e testo), per le proposte; stampa id e id dei widget
//   pulisci                     toglie connessioni, token, codici, client, proposte, registro, foto dell'addon e i contenuti "ai-prova-*"
//   tentativi                   svuota la tabella dei tentativi (limiti di velocità e forza bruta)
//   config-foto <url>           aggiunge CMS_AI_FOTO_PROVA al config.local.php (il server finto delle foto)
//   nav-prepara                 2.33.0: rovina apposta il menu Principale (voce verso una bozza, voce doppia, etichetta lunga e
//                               maiuscole miste) e crea una pagina orfana pubblicata; salva le voci di prima; stampa gli id
//   nav-ripristina              rimette le voci di prima del menu Principale e toglie le pagine "ai-prova-nav-*"
//   menu-cambia <id>            aggiunge una voce al menu (per la prova del conflitto: il menu è cambiato dopo la proposta)
//   menu <id>                   stampa le voci del menu
//   articolo-prova <slug>       un articolo pubblicato fatto con l'editor, con parola chiave, attacco che non risponde, salto
//                               H2 > H4, un paragrafo lungo, frasi datate e un'immagine senza alt; stampa id e id dei widget
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
$dir = $argv[1];
require $dir . '/includes/config.php';
require_once $dir . '/includes/sito-seo.php';
require_once $dir . '/includes/moduli.php';
$cmd = $argv[2] ?? '';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
$addon = $dir . '/addon/ai-collegate/includes/ai.php';
switch ($cmd) {
    case 'accendi':
    case 'spegni':
        require_once $dir . '/includes/addon.php';
        $att = cms_addon_attivi();
        $att = $cmd === 'accendi' ? array_values(array_unique(array_merge($att, ['ai-collegate']))) : array_values(array_diff($att, ['ai-collegate']));
        addon_scrivi($att);
        if ($cmd === 'accendi') { require_once $addon; aic_installa(); }
        $esci(['attivi' => $att]);
    case 'statistiche':
        require_once $dir . '/includes/addon.php';
        $att = cms_addon_attivi();
        $su = ($argv[3] ?? '') === 'accendi';
        addon_scrivi($su ? array_values(array_unique(array_merge($att, ['statistiche']))) : array_values(array_diff($att, ['statistiche'])));
        if ($su) { $def = require $dir . '/addon/statistiche/modulo.php'; ($def['installa'])(); }
        $esci(['attivi' => cms_addon_attivi()]);
    case 'chiave':
        require_once $addon;
        $perm = aic_scope_pulito(explode(',', (string)($argv[3] ?? '')));
        $admin = (int)db()->query('SELECT id FROM ' . tbl('users') . " WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
        $c = aic_connessione_crea('chiave', '', (string)($argv[4] ?? 'Chiave di prova'), $admin, $perm);
        $esci(['connessione' => $c, 'chiave' => aic_token_crea($c, 'chiave', null), 'permessi' => $perm]);
    case 'token-scaduto':
    case 'token-altro-sito':
        require_once $addon;
        $st = db()->prepare('SELECT connessione_id FROM ' . tbl('ai_token') . ' WHERE hash = ?');
        $st->execute([aic_hash((string)($argv[3] ?? ''))]);
        $c = (int)$st->fetchColumn();
        if ($cmd === 'token-scaduto') db()->prepare('UPDATE ' . tbl('ai_token') . ' SET scade = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE connessione_id = ?')->execute([$c]);
        else db()->prepare('UPDATE ' . tbl('ai_token') . " SET audience = 'https://altro-sito.example/api/mcp' WHERE connessione_id = ?")->execute([$c]);
        $esci(['connessione' => $c]);
    case 'sql':
        $q = str_replace('{p}', DB_PREFIX, (string)($argv[3] ?? ''));
        $st = db()->query($q);
        $esci($st && preg_match('/^\s*(SELECT|SHOW)/i', $q) ? $st->fetchAll(PDO::FETCH_ASSOC) : ['ok' => true]);
    case 'redattore':
        db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute(['ai_prova_red']);
        if (($argv[3] ?? '') === 'crea') {
            $cols = array_column(db()->query('SHOW COLUMNS FROM ' . tbl('users'))->fetchAll(), 'Field');
            $v = ['username' => 'ai_prova_red', 'password_hash' => password_hash('Red-AI-Prova-2026', PASSWORD_DEFAULT), 'role' => 'editor', 'email' => 'red@ai-prova.it'];
            if (in_array('display_name', $cols, true)) $v['display_name'] = 'Redattore AI';
            $v = array_intersect_key($v, array_flip($cols));
            db()->prepare('INSERT INTO ' . tbl('users') . ' (' . implode(',', array_keys($v)) . ') VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')')->execute(array_values($v));
        }
        $esci(['ok' => true]);
    case 'admin2fa':
        require_once $dir . '/includes/sicurezza.php';
        sicurezza_schema();
        db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute(['ai_prova_2fa']);
        $segreto = '';
        if (($argv[3] ?? '') === 'crea') {
            $segreto = sicurezza_totp_segreto();
            $cols = array_column(db()->query('SHOW COLUMNS FROM ' . tbl('users'))->fetchAll(), 'Field');
            $v = ['username' => 'ai_prova_2fa', 'password_hash' => password_hash('Adm-AI-Prova-2026', PASSWORD_DEFAULT), 'role' => 'admin', 'email' => 'admin2fa@ai-prova.it', 'totp_segreto' => $segreto, 'totp_attivo' => 1, 'totp_recupero' => '[]'];
            $v = array_intersect_key($v, array_flip($cols));
            db()->prepare('INSERT INTO ' . tbl('users') . ' (' . implode(',', array_keys($v)) . ') VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')')->execute(array_values($v));
        }
        $esci(['segreto' => $segreto]);
    case 'totp':
        require_once $dir . '/includes/sicurezza.php';
        $esci(['codice' => sicurezza_totp_codice((string)($argv[3] ?? ''), (int)floor(time() / 30))]);
    case 'pagina-pubblicata':
        require_once $dir . '/includes/editor-controlli.php';
        require_once $dir . '/includes/editor.php';
        require_once $dir . '/includes/contenuti.php';
        editor_carica_widget_esterni();
        $slug = (string)($argv[3] ?? 'ai-prova-pubblicata');
        db()->prepare('DELETE FROM ' . tbl('pages') . ' WHERE slug = ?')->execute([$slug]);
        $id = contenuti_salva('pagine', 0, ['title' => 'Pagina pubblicata di prova', 'slug' => $slug, 'stato' => 'pubblicato', 'contenuto' => '', 'seo_title' => 'Titolo vecchio', 'seo_description' => 'Descrizione vecchia della pagina di prova.']);
        $t = editor_widget('titolo', ['testo' => 'Titolo vecchio della pagina', 'livello' => 'h1']);
        $x = editor_widget('testo', ['contenuto' => '<p>Testo vecchio della pagina.</p>']);
        editor_salva($id, editor_valida(['elementi' => [editor_sezione([$t, $x])]]), true, 'prova');
        $esci(['id' => $id, 'titolo_id' => $t['id'], 'testo_id' => $x['id']]);
    case 'pulisci':
        require_once $addon;
        aic_schema();
        foreach (['ai_connessioni', 'ai_token', 'ai_codici', 'ai_client', 'ai_proposte', 'ai_registro', 'ai_foto', 'ai_richieste'] as $t) db()->exec('DELETE FROM ' . tbl($t));
        foreach (['pages', 'articles'] as $t) db()->exec('DELETE FROM ' . tbl($t) . " WHERE slug LIKE 'ai-prova%'");
        db()->exec('DELETE FROM ' . tbl('settings') . " WHERE setting_key = 'aic_impostazioni'");
        $esci(['ok' => true]);
    case 'tentativi':
        db()->exec('DELETE FROM ' . tbl('sicurezza_tentativi'));
        $esci(['ok' => true]);
    case 'config-foto':
        $f = $dir . '/includes/config.local.php';
        $c = (string)file_get_contents($f);
        $c = preg_replace("/\ndefine\('CMS_AI_FOTO_PROVA'[^\n]*/", '', $c);
        $c .= "\ndefine('CMS_AI_FOTO_PROVA', " . var_export((string)($argv[3] ?? ''), true) . ");\n";
        file_put_contents($f, $c);
        if (function_exists('opcache_reset')) @opcache_reset();
        $esci(['ok' => true]);
    case 'nav-prepara':
    case 'nav-ripristina':
    case 'menu-cambia':
    case 'menu':
    case 'articolo-prova':
        require_once $dir . '/includes/editor-controlli.php';
        require_once $dir . '/includes/editor.php';
        require_once $dir . '/includes/contenuti.php';
        require_once $dir . '/includes/menu-nav.php';
        editor_carica_widget_esterni();
        $pagina = function (string $slug, string $titolo, string $stato, array $widget) {
            db()->prepare('DELETE FROM ' . tbl('pages') . ' WHERE slug = ?')->execute([$slug]);
            $id = contenuti_salva('pagine', 0, ['title' => $titolo, 'slug' => $slug, 'stato' => $stato, 'contenuto' => '']);
            editor_salva($id, editor_valida(['elementi' => [editor_sezione($widget)]]), $stato === 'pubblicato', 'prova');
            return $id;
        };
        $principale = menu_per_posizione('principale');
        if ($cmd === 'menu') { $st = db()->prepare('SELECT voci FROM ' . tbl('menu') . ' WHERE id = ?'); $st->execute([(int)($argv[3] ?? 0)]); $esci(json_decode((string)$st->fetchColumn(), true)); }
        if ($cmd === 'menu-cambia') {
            $st = db()->prepare('SELECT * FROM ' . tbl('menu') . ' WHERE id = ?'); $st->execute([(int)($argv[3] ?? 0)]); $m = $st->fetch();
            $v = json_decode((string)$m['voci'], true); $v[] = ['etichetta' => 'Aggiunta a mano', 'tipo' => 'link', 'url' => '/', 'figli' => []];
            menu_salva((int)$m['id'], $m['nome'], $m['posizione'], $v);
            $esci(['ok' => true]);
        }
        if ($cmd === 'nav-ripristina') {
            $prima = json_decode((string)get_setting('aic_prova_menu', ''), true);
            if (is_array($prima) && $principale) menu_salva((int)$principale['id'], $principale['nome'], $principale['posizione'], $prima);
            db()->exec('DELETE FROM ' . tbl('pages') . " WHERE slug LIKE 'ai-prova-nav-%'");
            db()->exec('DELETE FROM ' . tbl('articles') . " WHERE slug LIKE 'ai-prova-art-%'");
            $esci(['ok' => true]);
        }
        if ($cmd === 'articolo-prova') {
            $slug = (string)($argv[3] ?? 'ai-prova-art-1');
            db()->prepare('DELETE FROM ' . tbl('articles') . ' WHERE slug = ?')->execute([$slug]);
            $id = contenuti_salva('articoli', 0, ['title' => 'Potare le rose in inverno', 'slug' => $slug, 'stato' => 'pubblicato', 'data' => date('Y-m-d', time() - 86400), 'ora' => '08:00', 'contenuto' => '',
                'keyword_target' => 'potare le rose', 'seo_title' => 'Rose', 'seo_description' => 'Una guida.']);
            $lungo = str_repeat('Le rose hanno bisogno di cure costanti durante tutto il periodo freddo e la potatura va fatta con attrezzi puliti e affilati. ', 9);
            $t1 = editor_widget('testo', ['contenuto' => '<p>Il giardino è un posto bellissimo dove passare il tempo libero con la famiglia, quest\'anno più che mai.</p><h2>Gli attrezzi</h2><p>' . $lungo . '</p><h4>Le forbici</h4><p>Recentemente sono uscite forbici nuove, come nel ' . (date('Y') - 2) . '.</p>']);
            $im = editor_widget('immagine', ['immagine' => '/uploads/segnaposto/nessuna.jpg', 'alt' => '']);
            editor_salva($id, editor_valida(['elementi' => [editor_sezione([$t1, $im])]]), true, 'prova', '', 'articoli');
            $esci(['id' => $id, 'testo_id' => $t1['id']]);
        }
        // nav-prepara
        if (!$principale) $esci(['errore' => 'manca il menu principale']);
        if (get_setting('aic_prova_menu', '') === '') save_setting('aic_prova_menu', json_encode($principale['voci'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $orfana = $pagina('ai-prova-nav-orfana', 'Lavora con noi', 'pubblicato', [editor_widget('titolo', ['testo' => 'Lavora con noi', 'livello' => 'h1']), editor_widget('testo', ['contenuto' => '<p>Cerchiamo persone.</p>'])]);
        $bozza = $pagina('ai-prova-nav-bozza', 'Offerte', 'bozza', [editor_widget('titolo', ['testo' => 'Offerte', 'livello' => 'h1'])]);
        $voci = json_decode((string)get_setting('aic_prova_menu', ''), true);
        $voci[] = ['etichetta' => 'Offerte', 'tipo' => 'link', 'url' => '/ai-prova-nav-bozza/', 'figli' => []];
        $voci[] = ['etichetta' => 'Servizi', 'tipo' => 'link', 'url' => '/servizi/', 'figli' => []];
        $voci[] = ['etichetta' => 'Tutte Le Nostre Domande Frequenti', 'tipo' => 'link', 'url' => '/inesistente-ai-prova/', 'figli' => []];
        menu_salva((int)$principale['id'], $principale['nome'], $principale['posizione'], $voci);
        $esci(['menu' => (int)$principale['id'], 'orfana' => $orfana, 'bozza' => $bozza]);
}
$esci(['errore' => 'comando sconosciuto']);
