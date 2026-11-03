<?php
/**
 * Aiuti da riga di comando per prova-test.js e attacchi-test.js (addon Test e questionari, 2.22.0), su un CMS installato
 * con l'addon acceso. Mai nel prodotto.
 * uso: php prove/test-cli.php <cartella CMS> <comando> [argomenti]
 *   sql "<query>"                  righe in JSON ({p} diventa il prefisso delle tabelle)
 *   calcola <id> <json risposte>   test_calcola() sul test salvato
 *   simula <id> [prove]            test_simula()
 *   smtp <porta>                   la posta verso il server finto, limite orario largo
 *   lista <nome>                   l'id della lista (creata se manca)
 *   salva_flusso <json>            newsletter_flusso_salva(0, json): stampa l'id o {errore}
 *   giro                           un giro della newsletter (campagne e automazioni)
 *   innesco <test> <contatto> <esiti separati da spazio>   newsletter_flussi_innesco('test', ...)
 *   esito_firmato <id> <json corto> i parametri e e f di un link firmato (per gli attacchi: un esito "inventato" ma firmato bene non serve, qui si firma solo per confronto)
 *   pulisci_limiti                 svuota test_limiti (dopo gli invii a raffica)
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: test-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
require_once $dir . '/includes/newsletter-flussi.php';
require_once $dir . '/addon/test/includes/test.php';
newsletter_schema();
newsletter_flussi_schema();
test_schema();
$out = null;
switch ($cmd) {
    case 'sql':
        $q = str_replace('{p}', defined('DB_PREFIX') ? DB_PREFIX : (defined('CMS_PREFISSO') ? CMS_PREFISSO : ''), (string)$arg[0]);
        $st = db()->query($q);
        $out = $st->columnCount() ? $st->fetchAll(PDO::FETCH_ASSOC) : $st->rowCount();
        break;
    case 'calcola':
        $t = test_leggi((int)$arg[0]);
        $out = $t ? test_calcola($t, (array)json_decode((string)$arg[1], true)) : null;
        break;
    case 'simula':
        $t = test_leggi((int)$arg[0]);
        $out = $t ? test_simula($t, (int)($arg[1] ?? 200)) : null;
        break;
    case 'smtp':
        save_setting('posta_smtp', json_encode(['host' => '127.0.0.1', 'porta' => (int)$arg[0], 'sicurezza' => 'nessuna', 'utente' => '', 'password' => '', 'mittente' => 'sito@esempio.it', 'nome' => 'Studio Prova']));
        save_setting('newsletter_config', json_encode(['per_ora' => 50000, 'per_giorno' => 0, 'rispondi_a' => '']));
        $out = true;
        break;
    case 'lista':
        $id = (int)db()->query('SELECT id FROM ' . tbl('newsletter_liste') . ' WHERE nome = ' . db()->quote((string)$arg[0]))->fetchColumn();
        if (!$id) { db()->prepare('INSERT INTO ' . tbl('newsletter_liste') . ' (nome, descrizione, creato) VALUES (?, \'\', NOW())')->execute([(string)$arg[0]]); $id = (int)db()->lastInsertId(); }
        $out = $id;
        break;
    case 'salva_flusso':
        try { $out = newsletter_flusso_salva(0, json_decode((string)$arg[0], true)); } catch (Throwable $e) { $out = ['errore' => $e->getMessage()]; }
        break;
    case 'giro':
        $out = newsletter_invia_giro();
        break;
    case 'innesco':
        newsletter_flussi_innesco('test', (int)$arg[0], (int)$arg[1], array_values(array_filter(explode(' ', (string)($arg[2] ?? '')))));
        $out = true;
        break;
    case 'esito_firmato':
        $out = test_esito_parametri((array)json_decode((string)$arg[1], true));
        break;
    case 'pagina_riquadro':
        // Una pagina pubblicata /<slug>/ con un widget Riquadro in evidenza: link scritto a mano e, se c'è, il test scelto.
        require_once $dir . '/includes/editor-controlli.php';
        require_once $dir . '/includes/editor.php';
        editor_carica_widget_esterni();
        [$slug, $test, $link] = $arg + [0 => 'riquadro-test', 1 => '0', 2 => '/contatti/'];
        $w = editor_widget('riquadro_test', ['titolo' => 'Fai il test', 'pulsante_link' => $link] + ((int)$test ? ['test' => (string)(int)$test] : []));
        $albero = editor_valida(['elementi' => [editor_sezione([$w])]]);
        $st = db()->prepare('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ?'); $st->execute([$slug]);
        $id = (int)$st->fetchColumn();
        if (!$id) { db()->prepare('INSERT INTO ' . tbl('pages') . " (title, slug, status, publish_at) VALUES (?, ?, 'published', NOW())")->execute([ucfirst(str_replace('-', ' ', $slug)), $slug]); $id = (int)db()->lastInsertId(); }
        db()->prepare('UPDATE ' . tbl('pages') . ' SET struttura = ?, struttura_bozza = NULL WHERE id = ?')->execute([editor_json($albero), $id]);
        $out = ['id' => $id, 'test' => $albero['elementi'][0]['figli'][0]['figli'][0]['impostazioni']['test'] ?? null];
        break;
    case 'pulisci_limiti':
        $out = db()->exec('DELETE FROM ' . tbl('test_limiti'));
        break;
    default:
        fwrite(STDERR, "comando sconosciuto\n"); exit(2);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
