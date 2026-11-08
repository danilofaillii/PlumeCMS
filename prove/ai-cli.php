<?php
// Comandi da riga di comando per le prove dell'addon AI collegate (2.32.0: prova-ai-collegate.js, gruppo 46 di
// tools/sicurezza-prova.js). Stampa JSON.
// uso: php prove/ai-cli.php <cartella CMS> <comando> [argomenti]
//   accendi | spegni            accende o spegne l'addon (moduli/addon-attivi.json) e crea le tabelle
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
}
$esci(['errore' => 'comando sconosciuto']);
