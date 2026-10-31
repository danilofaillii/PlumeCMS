<?php
/**
 * Prepara e pulisce i dati per prove/prova-scegli-link.js e prove/attacchi-scegli-link.js (solo per le prove, mai nel prodotto).
 * uso: php prove/scegli-link-cli.php <cartella CMS> <azione> [argomenti]
 *   prepara             pagina "SL Pulsanti" (Apertura con il pulsante Prenota, Immagine, Testo) pubblicata, pagina in bozza
 *                       "SL Listino in bozza", articolo "SL Offerta di primavera" con categoria e tag, articolo con un titolo
 *                       cattivo (<script>, <img onerror>), un redattore "sl-redattrice". Risponde con gli id in JSON.
 *   addon               un evento pubblicato e uno in bozza, un prodotto, un corso pubblicato (gli addon devono essere accesi)
 *   slug <id> <nuovo>   cambia l'indirizzo di una pagina dalla scheda (come Salva nella scheda): parte il redirect?
 *   albero <id>         l'albero pubblicato della pagina (JSON)
 *   leggi <tabella> <colonna> <id>   un valore salvato
 *   tentativi           svuota <prefisso>sicurezza_tentativi (dopo le prove di attacco)
 *   pulisci             toglie tutto quello che "prepara" e "addon" hanno messo
 */
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
[$dir, $azione] = array_pad(array_slice($argv, 1, 2), 2, '');
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/sicurezza.php';
require_once $dir . '/includes/sicurezza-password.php';
require_once $dir . '/includes/contenuti.php';
require_once $dir . '/includes/editor.php';
$out = fn($x) => print(json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
const SL_REDATTORE = 'sl-redattrice';
const SL_PASSWORD = 'Redattrice-Prova-2026';

function sl_id(string $tab, string $slug): int {
    $st = db()->prepare('SELECT id FROM ' . tbl($tab) . ' WHERE slug = ?'); $st->execute([$slug]);
    return (int)$st->fetchColumn();
}

switch ($azione) {
    case 'prepara':
        $pagina = sl_id('pages', 'sl-pulsanti');
        $pagina = contenuti_salva('pagine', $pagina, ['title' => 'SL Pulsanti', 'slug' => 'sl-pulsanti', 'stato' => 'pubblicato', 'contenuto' => '<p>Prova</p>'], 'prova');
        $albero = editor_valida(['elementi' => [
            editor_sezione([editor_widget('hero', ['titolo' => 'Prova dei link', 'testo' => 'Una pagina per Scegli dal sito.', 'pulsanti' => [['testo' => 'Prenota', 'link' => '', 'stile' => 'viola']]])]),
            editor_sezione([editor_widget('immagine', ['immagine' => '/uploads/kit/disegno-1.svg', 'alt' => 'Disegno', 'link' => ['url' => '', 'nuova' => false]])]),
            editor_sezione([editor_widget('testo', ['contenuto' => '<p>Leggi le nostre offerte qui.</p>'])]),
        ]]);
        editor_salva($pagina, $albero, true, 'prova', 'prova Scegli dal sito', 'pagine');
        $bozza = contenuti_salva('pagine', sl_id('pages', 'sl-listino-bozza'), ['title' => 'SL Listino in bozza', 'slug' => 'sl-listino-bozza', 'stato' => 'bozza', 'contenuto' => '<p>Ancora da finire.</p>'], 'prova');
        $prenota = contenuti_salva('pagine', sl_id('pages', 'sl-prenota-ora'), ['title' => 'SL Prenota ora', 'slug' => 'sl-prenota-ora', 'stato' => 'pubblicato', 'contenuto' => '<p>Prenota da qui.</p>'], 'prova');
        $articolo = contenuti_salva('articoli', sl_id('articles', 'sl-offerta-di-primavera'), ['title' => 'SL Offerta di primavera', 'slug' => 'sl-offerta-di-primavera', 'stato' => 'pubblicato',
            'contenuto' => '<p>Le offerte di primavera.</p>', 'data' => date('Y-m-d', time() - 86400), 'categoria_nuova' => 'SL Novità del mese', 'tags' => 'SL Offerte'], 'prova');
        $cattivo = contenuti_salva('articoli', sl_id('articles', 'sl-titolo-cattivo'), ['title' => 'SL Cattivo <script>window.__slXss=1</script><img src=x onerror="window.__slXss=2">', 'slug' => 'sl-titolo-cattivo',
            'stato' => 'pubblicato', 'contenuto' => '<p>Titolo cattivo.</p>', 'data' => date('Y-m-d', time() - 86400)], 'prova');
        $st = db()->prepare('SELECT id FROM ' . tbl('users') . ' WHERE username = ?'); $st->execute([SL_REDATTORE]);
        if (!$st->fetchColumn()) db()->prepare('INSERT INTO ' . tbl('users') . ' (username, email, display_name, role, password_hash) VALUES (?,?,?,?,?)')
            ->execute([SL_REDATTORE, 'sl-redattrice@esempio.it', 'Redattrice di prova', 'editor', sicurezza_password_hash(SL_PASSWORD)]);
        $out(['pagina' => $pagina, 'bozza' => $bozza, 'prenota' => $prenota, 'articolo' => $articolo, 'cattivo' => $cattivo, 'redattore' => SL_REDATTORE, 'password' => SL_PASSWORD,
            'categoria' => contenuti_slug('SL Novità del mese'), 'tag' => contenuti_slug('SL Offerte')]);
        break;

    case 'addon':
        $ora = date('Y-m-d H:i:s');
        $tra = time() + 20 * 86400;
        foreach ([['sl-serata-di-prova', 'SL Serata di prova', 'pubblicato'], ['sl-evento-in-bozza', 'SL Evento in bozza', 'bozza']] as [$slug, $titolo, $stato]) {
            db()->prepare('INSERT IGNORE INTO ' . tbl('evi_eventi') . ' (slug, titolo, inizio, inizio_ts, descrizione, stato, creato, aggiornato) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$slug, $titolo, date('Y-m-d H:i:s', $tra), $tra, 'Una serata per la prova.', $stato, $ora, $ora]);
        }
        db()->prepare('INSERT IGNORE INTO ' . tbl('vetrina_prodotti') . ' (nome, slug, descrizione, prezzo_cent, pubblicato, creato, aggiornato) VALUES (?,?,?,?,1,?,?)')
            ->execute(['SL Candela profumata', 'sl-candela-profumata', 'Una candela.', 1500, $ora, $ora]);
        db()->prepare('INSERT IGNORE INTO ' . tbl('corsi') . " (slug, titolo, descrizione, stato, creato, aggiornato) VALUES (?,?,?,'pubblicato',?,?)")
            ->execute(['sl-corso-di-prova', 'SL Corso di prova', 'Un corso.', $ora, $ora]);
        $out(['ok' => true]);
        break;

    case 'slug':
        contenuti_salva_rapido('pagine', (int)($argv[3] ?? 0), ['slug' => (string)($argv[4] ?? ''), 'autore' => 'prova']);
        $out(['ok' => true, 'redirect' => db()->query('SELECT da, a, codice FROM ' . tbl('redirect') . " WHERE origine = 'slug' ORDER BY id DESC LIMIT 3")->fetchAll()]);
        break;

    case 'albero':
        $st = db()->prepare('SELECT struttura FROM ' . tbl('pages') . ' WHERE id = ?'); $st->execute([(int)($argv[3] ?? 0)]);
        echo (string)$st->fetchColumn(), "\n";
        break;

    case 'leggi':
        $tab = preg_replace('/[^a-z0-9_]/', '', (string)($argv[3] ?? '')); $col = preg_replace('/[^a-z0-9_]/', '', (string)($argv[4] ?? ''));
        $st = db()->prepare('SELECT `' . $col . '` FROM ' . tbl($tab) . ' WHERE id = ?'); $st->execute([(int)($argv[5] ?? 0)]);
        $out(['valore' => $st->fetchColumn()]);
        break;

    case 'tentativi':
        sicurezza_schema();
        $out(['tolti' => db()->exec('DELETE FROM ' . tbl('sicurezza_tentativi'))]);
        break;

    case 'pulisci':
        foreach (['pages', 'articles'] as $t) db()->exec('DELETE FROM ' . tbl($t) . " WHERE slug LIKE 'sl-%'");
        db()->exec('DELETE FROM ' . tbl('categories') . " WHERE slug LIKE 'sl-%'");
        db()->exec('DELETE FROM ' . tbl('tags') . " WHERE slug LIKE 'sl-%'");
        db()->exec('DELETE FROM ' . tbl('redirect') . " WHERE da LIKE '/sl-%' OR a LIKE '/sl-%'");
        db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute([SL_REDATTORE]);
        foreach (['evi_eventi', 'vetrina_prodotti', 'corsi'] as $t) { try { db()->exec('DELETE FROM ' . tbl($t) . " WHERE slug LIKE 'sl-%'"); } catch (PDOException $e) {} }
        try { db()->exec('DELETE FROM ' . tbl('popup') . " WHERE nome LIKE 'SL %'"); } catch (PDOException $e) {}
        try { db()->exec('DELETE FROM ' . tbl('moduli') . " WHERE nome LIKE 'SL %'"); } catch (PDOException $e) {}
        try { db()->exec('DELETE FROM ' . tbl('newsletter_campagne') . " WHERE oggetto LIKE 'SL %'"); } catch (PDOException $e) {}
        try { db()->exec('DELETE FROM ' . tbl('menu') . " WHERE nome LIKE 'SL %'"); } catch (PDOException $e) {}
        $out(['ok' => true]);
        break;

    default:
        fwrite(STDERR, "azione sconosciuta\n");
        exit(2);
}
