<?php
/**
 * 2.40.0: i dati per prove/prova-seo-proposte.js (solo prove, mai nel prodotto). Stampa JSON. Usa prove/paginazione-cli.php per
 * articoli, categoria e tag ("semina 30"). Le pagine e gli addon che crea li toglie "pulisci".
 * uso: php prove/seo-proposte-cli.php <cartella CMS> blog <variante>   la pagina "Blog di prova" (/blog-di-prova/) scelta come blog:
 *                                                                   articoli  titolo, testo e Ultimi articoli (10, senza i pillar)
 *                                                                   griglia   titolo, testo e Griglia di articoli (numeri, 15 per pagina)
 *                                                                   senza     titolo e testo, nessun widget che elenca gli articoli
 *                                                                   titolo    solo il titolo (come la pagina Blog di partenza)
 *                                                                   classica  la pagina scritta nella scheda, senza editor
 *      php prove/seo-proposte-cli.php <cartella CMS> conta           quanti articoli escono nel blog (articoli indicizzati)
 *      php prove/seo-proposte-cli.php <cartella CMS> categoria <descrizione>   la descrizione della categoria "pagine-prova"
 *      php prove/seo-proposte-cli.php <cartella CMS> senza-descrizione   la categoria "prova-senza-descrizione" (vuota) con l'articolo 01
 *      php prove/seo-proposte-cli.php <cartella CMS> immagine <url|"">   l'immagine della pagina del blog (scheda)
 *      php prove/seo-proposte-cli.php <cartella CMS> addon            accende Eventi, Test e Vetrina e crea: un evento fra 20 giorni,
 *                                                                   uno di 40 giorni fa, uno in bozza, un test pubblicato e uno in bozza
 *      php prove/seo-proposte-cli.php <cartella CMS> pulisci          toglie pagina, eventi, test, categoria in più; rimette gli addon
 *                                                                   come erano e il blog su /blog/
 */
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
$dir = $argv[1] ?? '';
$cmd = $argv[2] ?? '';
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: seo-proposte-cli.php <cartella CMS> blog|conta|categoria|senza-descrizione|immagine|addon|pulisci ...\n"); exit(2); }
require $dir . '/includes/config.php';
require_once $dir . '/includes/sito-seo.php';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
$pdo = db();
$pag = tbl('pages'); $cat = tbl('categories'); $art = tbl('articles');

switch ($cmd) {
    case 'blog':
        require_once $dir . '/includes/editor-controlli.php';
        require_once $dir . '/includes/editor.php';
        editor_carica_widget_esterni();
        $var = (string)($argv[3] ?? 'articoli');
        $sez = fn(array $ws) => ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => [], 'figli' => [['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => [], 'figli' => $ws]]];
        $w = function (string $tipo, array $imp) { $x = editor_widget($tipo); $x['impostazioni'] = $imp + $x['impostazioni']; return $x; };
        $titolo = $w('titolo', ['testo' => $var === 'titolo' ? 'Blog' : 'Il blog fatto con l\'editor', 'livello' => 'h1']);
        $testo = $w('testo', ['contenuto' => '<p>Un testo scritto nel disegno del blog.</p>']);
        $elementi = match ($var) {
            'articoli' => [$sez([$titolo, $testo]), $sez([$w('articoli', ['numero' => 10, 'solo_pillar' => false])])],
            'griglia' => [$sez([$titolo, $testo]), $sez([$w('griglia_contenuti', ['quanti' => 60, 'paginazione' => 'numeri', 'per_pagina' => 15])])],
            'senza' => [$sez([$titolo, $testo])],
            'titolo' => [$sez([$titolo])],
            default => [],
        };
        $st = $pdo->prepare("SELECT id FROM $pag WHERE slug = 'blog-di-prova'"); $st->execute(); $id = (int)$st->fetchColumn();
        if (!$id) { $pdo->prepare("INSERT INTO $pag (title, slug, status, publish_at) VALUES ('Blog di prova', 'blog-di-prova', 'published', NOW())")->execute(); $id = (int)$pdo->lastInsertId(); }
        $pdo->prepare("UPDATE $pag SET seo_title = '', seo_description = 'Gli articoli del blog di prova: consigli pratici, novità e storie, ogni settimana.', html = ?, struttura = NULL, struttura_bozza = NULL WHERE id = ?")
            ->execute([$var === 'classica' ? '<p>Il testo della pagina del blog scritta nella scheda.</p>' : '', $id]);
        if ($elementi) editor_salva($id, editor_valida(['elementi' => $elementi]), true, 'prova', 'pagina di prova delle proposte SEO');
        save_setting('pagina_blog', '/blog-di-prova/');
        $esci(['pagina' => $id, 'variante' => $var]);
    case 'conta':
        $esci(['articoli' => count(sito_ultimi(100000))]);
    case 'categoria':
        $pdo->prepare("UPDATE $cat SET description = ? WHERE slug = 'pagine-prova'")->execute([(string)($argv[3] ?? '')]);
        $esci(['descrizione' => (string)($argv[3] ?? '')]);
    case 'senza-descrizione':
        $pdo->prepare("DELETE FROM $cat WHERE slug = 'prova-senza-descrizione'")->execute();
        $pdo->prepare("INSERT INTO $cat (slug, name, description, sort_order) VALUES ('prova-senza-descrizione', 'Prova senza descrizione', '', 91)")->execute();
        $pdo->prepare("UPDATE $art SET categorie = 'pagine-prova,prova-senza-descrizione' WHERE slug = 'articolo-paginato-01'")->execute();
        $esci(['categoria' => 'prova-senza-descrizione']);
    case 'immagine':
        $pdo->prepare("UPDATE $pag SET immagine_url = ? WHERE slug = 'blog-di-prova'")->execute([(string)($argv[3] ?? '')]);
        $esci(['immagine' => (string)($argv[3] ?? '')]);
    case 'addon':
        require_once $dir . '/includes/addon.php';
        $prima = cms_addon_attivi();
        if (get_setting('prova_seo_proposte_addon', '') === '') save_setting('prova_seo_proposte_addon', json_encode(array_values($prima)));
        foreach (['eventi-iscrizione', 'test', 'vetrina'] as $n) if (!in_array($n, $prima, true)) addon_attiva($n, true);
        require_once $dir . '/addon/eventi-iscrizione/includes/eventi.php';
        evi_schema();
        $pdo->exec('DELETE FROM ' . tbl('evi_eventi') . " WHERE slug LIKE 'prova-sitemap-%'");
        $ins = $pdo->prepare('INSERT INTO ' . tbl('evi_eventi') . " (slug, titolo, inizio, inizio_ts, fine_ts, luogo, indirizzo, descrizione, foto, prezzo, posti, max_persona, chiusura_ts, attesa, stato, aggiornato, creato) VALUES (?,?,?,?,?,'Sala','Via Prova 1',?,'','',20,2,0,0,?,NOW(),NOW())");
        foreach ([['prova-sitemap-futuro', 'Evento futuro di prova', 20, 'pubblicato'], ['prova-sitemap-vecchio', 'Evento vecchio di prova', -40, 'pubblicato'], ['prova-sitemap-bozza', 'Evento in bozza di prova', 10, 'bozza']] as [$slug, $tit, $giorni, $stato]) {
            $t = time() + $giorni * 86400;
            $ins->execute([$slug, $tit, date('Y-m-d H:i:s', $t), $t, $t + 3600, 'La descrizione dell\'evento ' . $tit . '.', $stato]);
        }
        require_once $dir . '/addon/test/includes/test.php';
        test_schema();
        $pdo->exec('DELETE FROM ' . tbl('test_test') . " WHERE nome LIKE 'Prova sitemap %'");
        $m = test_modelli(); $mm = $m[array_key_first($m)];
        $out = [];
        foreach (['pubblicato' => 'Prova sitemap pubblicato', 'bozza' => 'Prova sitemap bozza'] as $stato => $nome) {
            $id = test_crea($nome, $mm['tipo'] ?? 'punteggio', $mm['dati'] ?? null);
            if ($stato === 'pubblicato') test_salva($id, ['stato' => 'pubblicato']);
            $out[$stato] = test_url(test_leggi($id));
        }
        $esci(['test' => $out, 'eventi' => ['/evento/prova-sitemap-futuro/', '/evento/prova-sitemap-vecchio/', '/evento/prova-sitemap-bozza/']]);
    case 'pulisci':
        $pdo->prepare("DELETE FROM $pag WHERE slug = 'blog-di-prova'")->execute();
        $pdo->prepare("DELETE FROM $cat WHERE slug = 'prova-senza-descrizione'")->execute();
        save_setting('pagina_blog', '/blog/');
        $prima = json_decode((string)get_setting('prova_seo_proposte_addon', ''), true);
        if (is_array($prima)) {
            require_once $dir . '/includes/addon.php';
            foreach (['eventi-iscrizione', 'test', 'vetrina'] as $n) {
                if (is_file($dir . '/addon/eventi-iscrizione/includes/eventi.php') && $n === 'eventi-iscrizione') { try { $pdo->exec('DELETE FROM ' . tbl('evi_eventi') . " WHERE slug LIKE 'prova-sitemap-%'"); } catch (Throwable $e) {} }
                if ($n === 'test') { try { $pdo->exec('DELETE FROM ' . tbl('test_test') . " WHERE nome LIKE 'Prova sitemap %'"); } catch (Throwable $e) {} }
                if (!in_array($n, $prima, true)) { try { addon_attiva($n, false); } catch (Throwable $e) {} }
            }
            $pdo->prepare('DELETE FROM ' . tbl('settings') . " WHERE setting_key = 'prova_seo_proposte_addon'")->execute();
        }
        $esci(['pulito' => true]);
}
fwrite(STDERR, "comando sconosciuto: $cmd\n");
exit(2);
