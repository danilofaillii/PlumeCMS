<?php
// Comandi da riga di comando per le prove delle pagine per zona (2.28.0: prova-zone.js, gruppo 43 di tools/sicurezza-prova.js). Stampa JSON.
// uso: php prove/zone-cli.php <cartella CMS> <comando> [argomenti]
//   elenco                      il registro delle pagine per zona (con stato e segnaposto restanti)
//   azzera                      toglie dal registro ogni pagina per zona e cancella le sue pagine (DELETE, non il cestino)
//   crea <json>                 crea le bozze come dal pannello (zone_valida + zone_crea_bozze): {servizio, zone: "a\nb", sede, servizio_pagina, titolo, descrizione}
//   segnaposto <id pagina>      quanti riquadri [DA SCRIVERE] restano
//   togli <id pagina>           toglie la pagina dal registro e la cancella (per la pulizia delle prove)
//   scrivi <id pagina> [quanti] scrive il testo al posto dei segnaposto (tutti, o solo i primi N) nella bozza dell'editor, come farebbe una persona
//   pubblica <id pagina>        prova a pubblicare dall'editor (editor_salva con pubblica): {ok} oppure {errore}
//   pubblicabile <id pagina>    zone_pubblicabile
//   jsonld <percorso>           il JSON-LD calcolato di quell'indirizzo (seo_jsonld_di)
//   sql <query>                 una query (SELECT) con {p} al posto del prefisso
//   redattore <crea|elimina>    l'utente redattore "zone_prova_red" (password Red-Zone-Prova-2026)
//   cfg                         il gruppo "zone" della configurazione SEO (migrato, voci, elenco della 2.35.0) e le righe della tabella (2.36.0)
//   semina <N> [servizio] [stato] 2.36.0, prova di carico: N pagine per zona pubblicate (o in bozza con stato=bozza) copiate da una
//                               pagina per zona vera (creata e scritta qui), inserite a blocchi direttamente nelle tabelle
//   conta                       quante voci ha il registro (zone_conta)
//   widget <id pagina> <json>   cambia le impostazioni del widget Altre zone della pagina (pubblicata e bozza), passando da editor_valida: {"quante":5,"ordine":"vicine"}
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/includes/config.php';
require_once $argv[1] . '/includes/sito-seo.php';
require_once $argv[1] . '/includes/seo.php';
require_once $argv[1] . '/includes/sedi.php';
require_once $argv[1] . '/includes/zone.php';
require_once $argv[1] . '/includes/editor-controlli.php';
require_once $argv[1] . '/includes/editor.php';
$cmd = $argv[2] ?? '';
$esci = function ($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; };
$pulito = function (array $z) { $r = $z['riga']; unset($z['riga']); return $z + ['titolo' => $r['title'], 'slug' => $r['slug'], 'stato' => zone_stato(['riga' => $r] + $z), 'segnaposto' => zone_segnaposto_pagina((int)$z['pagina'])]; };
switch ($cmd) {
    case 'elenco': $esci(array_values(array_map($pulito, zone_tutte())));
    case 'azzera':
        $tolte = [];
        foreach (zone_tutte() as $id => $z) { db()->prepare('DELETE FROM ' . tbl('pages') . ' WHERE id = ?')->execute([$id]); $tolte[] = $z['riga']['slug']; }
        zone_salva_elenco([]);
        $esci(['zone' => 0, 'pagine_tolte' => $tolte]);
    case 'crea':
        $in = json_decode((string)($argv[3] ?? '{}'), true) ?: [];
        $v = zone_valida($in);
        if ($v['errori']) $esci(['errori' => $v['errori']]);
        try { $esci(zone_crea_bozze($v['valori'], 'prova') + ['errori' => []]); } catch (Throwable $e) { $esci(['errori' => ['_' => $e->getMessage()]]); }
    case 'segnaposto': $esci(['restanti' => zone_segnaposto_pagina((int)($argv[3] ?? 0))]);
    case 'togli':
        $id = (int)($argv[3] ?? 0);
        $c = zone_elimina($id);
        db()->prepare('DELETE FROM ' . tbl('pages') . ' WHERE id = ?')->execute([$id]);
        $esci(['tolta' => $c, 'pagina' => $id]);
    case 'pubblicabile': $esci(zone_pubblicabile((int)($argv[3] ?? 0)));
    case 'scrivi':
        $id = (int)($argv[3] ?? 0); $quanti = isset($argv[4]) ? (int)$argv[4] : 999;
        editor_carica_widget_esterni();
        $p = editor_pagina($id, 'pagine');
        if (!$p) $esci(['errore' => 'pagina non trovata']);
        $albero = editor_decodifica((string)($p['struttura_bozza'] ?: $p['struttura']));
        if (!$albero) $esci(['errore' => 'nessun albero']);
        $fatti = 0;
        $giro = function (array &$nodi) use (&$giro, &$fatti, $quanti): void {
            foreach ($nodi as &$e) {
                if (in_array($e['tipo'] ?? '', ['sezione', 'colonna'], true)) { $giro($e['figli']); continue; }
                if (($e['tipo'] ?? '') === 'testo' && $fatti < $quanti && str_contains((string)($e['impostazioni']['contenuto'] ?? ''), ZONE_SEGNAPOSTO)) {
                    $h = (string)$e['impostazioni']['contenuto'];
                    $h = preg_replace('~<p>\s*' . preg_quote(htmlspecialchars(ZONE_SEGNAPOSTO, ENT_QUOTES, 'UTF-8'), '~') . '[^<]*</p>~u', '<p>Interveniamo in giornata su perdite, scaldabagni e impianti nuovi: un testo scritto dalla prova al posto del segnaposto.</p>', $h);
                    $h = preg_replace('~<p>\s*' . preg_quote(ZONE_SEGNAPOSTO, '~') . '[^<]*</p>~u', '<p>Interveniamo in giornata su perdite, scaldabagni e impianti nuovi: un testo scritto dalla prova al posto del segnaposto.</p>', $h);
                    $h = preg_replace('~<p><em>Aiuto per chi scrive:.*?</em></p>~su', '', $h);
                    $e['impostazioni']['contenuto'] = $h; $fatti++;
                }
            }
        };
        $giro($albero['elementi']);
        editor_salva($id, editor_valida($albero), false, 'prova', 'riquadri scritti dalla prova', 'pagine');
        $esci(['scritti' => $fatti, 'restanti' => zone_segnaposto_pagina($id)]);
    case 'pubblica':
        $id = (int)($argv[3] ?? 0);
        editor_carica_widget_esterni();
        $p = editor_pagina($id, 'pagine');
        if (!$p) $esci(['errore' => 'pagina non trovata']);
        $albero = editor_decodifica((string)($p['struttura_bozza'] ?: $p['struttura']));
        try {
            editor_salva($id, $albero ?: ['elementi' => []], true, 'prova', 'pubblicata dalla prova', 'pagine');
            if ($p['status'] !== 'published') db()->prepare('UPDATE ' . tbl('pages') . " SET status = 'published', publish_at = COALESCE(publish_at, NOW()) WHERE id = ?")->execute([$id]);
            $esci(['ok' => true, 'stato' => contenuti_stato('pagine', contenuti_leggi('pagine', $id))]);
        } catch (RuntimeException $e) { $esci(['ok' => false, 'errore' => $e->getMessage(), 'stato' => contenuti_stato('pagine', contenuti_leggi('pagine', $id))]); }
    case 'jsonld':
        editor_carica_widget_esterni();
        $esci(seo_jsonld_di((string)($argv[3] ?? '/')));
    case 'sql':
        $q = str_replace('{p}', DB_PREFIX, (string)($argv[3] ?? ''));
        $st = db()->query($q);
        $esci($st && stripos(ltrim($q), 'SELECT') === 0 ? $st->fetchAll(PDO::FETCH_ASSOC) : ['ok' => true]);
    case 'redattore':
        $u = 'zone_prova_red';
        if (($argv[3] ?? '') === 'elimina') { db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute([$u]); $esci(['eliminato' => $u]); }
        db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE username = ?')->execute([$u]);
        db()->prepare('INSERT INTO ' . tbl('users') . ' (username, password_hash, display_name, email, role) VALUES (?, ?, ?, ?, ?)')->execute([$u, function_exists('sicurezza_password_hash') ? sicurezza_password_hash('Red-Zone-Prova-2026') : password_hash('Red-Zone-Prova-2026', PASSWORD_DEFAULT), 'Redattore prova zone', 'zone_prova_red@esempio.it', 'editor']);
        $esci(['creato' => $u]);
    case 'cfg':
        $c = seo_cfg(true)['zone'] ?? [];
        $righe = 0; try { $righe = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('zone'))->fetchColumn(); } catch (Throwable $e) { $righe = -1; }
        $esci(['migrato' => (int)($c['migrato'] ?? 0), 'voci' => (int)($c['voci'] ?? 0), 'elenco' => count((array)($c['elenco'] ?? [])), 'tabella' => $righe]);
    case 'conta': $esci(['conta' => zone_conta()]);
    case 'widget':
        $id = (int)($argv[3] ?? 0); $imp = json_decode((string)($argv[4] ?? '{}'), true) ?: [];
        editor_carica_widget_esterni();
        $st = db()->prepare('SELECT struttura, struttura_bozza FROM ' . tbl('pages') . ' WHERE id = ?'); $st->execute([$id]); $r = $st->fetch();
        if (!$r) $esci(['errore' => 'pagina non trovata']);
        $cambia = function (string $json) use ($imp): string {
            $a = editor_decodifica($json); if (!$a) return $json;
            $giro = function (array &$nodi) use (&$giro, $imp): void { foreach ($nodi as &$e) { if (in_array($e['tipo'] ?? '', ['sezione', 'colonna'], true)) { $giro($e['figli']); continue; } if (($e['tipo'] ?? '') === 'zone') { $e['impostazioni'] = $imp + (array)($e['impostazioni'] ?? []); foreach ($imp as $k => $x) if ($x === null) unset($e['impostazioni'][$k]); } } };
            $giro($a['elementi']);
            return json_encode(editor_valida($a), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };
        $nuova = (string)$r['struttura'] !== '' ? $cambia((string)$r['struttura']) : '';
        $bozza = (string)$r['struttura_bozza'] !== '' ? $cambia((string)$r['struttura_bozza']) : null;
        db()->prepare('UPDATE ' . tbl('pages') . ' SET struttura = ?, struttura_bozza = ? WHERE id = ?')->execute([$nuova, $bozza, $id]);
        $esci(['ok' => true]);
    case 'semina':
        $n = max(1, (int)($argv[3] ?? 100)); $servizio = (string)($argv[4] ?? 'Carico'); $bozza = ($argv[5] ?? '') === 'bozza';
        set_time_limit(0);
        // Il modello: una pagina per zona vera, scritta e pubblicata, che poi si copia.
        $r = zone_crea_bozze(['servizio' => $servizio, 'zone' => ['Modello di carico'], 'sede' => '', 'titolo' => '%servizio% a %zona%', 'descrizione' => ''], 'prova');
        $mod = $r['create'][0]['pagina'] ?? (zone_esiste($servizio, 'Modello di carico')['pagina'] ?? 0);
        if (!$mod) $esci(['errore' => 'modello non creato']);
        editor_carica_widget_esterni();
        $p = editor_pagina($mod, 'pagine');
        $albero = editor_decodifica((string)($p['struttura_bozza'] ?: $p['struttura']));
        $json = json_encode($albero, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = str_replace(ZONE_SEGNAPOSTO, 'Testo scritto', $json);
        $json = preg_replace('~<p><em>Aiuto per chi scrive:.*?</em></p>~su', '', $json);
        $pdo = db(); $pt = tbl('pages'); $zt = tbl('zone');
        $max = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM $pt")->fetchColumn();
        $slugBase = contenuti_slug($servizio);
        $fatte = 0; $t0 = microtime(true);
        for ($i = 0; $i < $n; $i += 500) {
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO $pt (title, slug, status, publish_at, robots, seo_description, struttura, struttura_bozza, html, created_at, updated_at) VALUES (?, ?, ?, ?, 'index', ?, ?, NULL, '', NOW(), NOW())");
            $reg = $pdo->prepare("INSERT INTO $zt (pagina, servizio, zona, sede, servizio_pagina, creata) VALUES (?, ?, ?, '', '', NOW())");
            for ($j = $i; $j < min($n, $i + 500); $j++) {
                $zona = sprintf('Comune %05d', $j + 1);
                $struttura = str_replace('Modello di carico', $zona, $json);
                $ins->execute([$servizio . ' a ' . $zona, $slugBase . '-a-comune-' . sprintf('%05d', $j + 1), $bozza ? 'draft' : 'published', $bozza ? null : date('Y-m-d H:i:s', time() - 60), $servizio . ' a ' . $zona . ': prova di carico.', $bozza ? '' : $struttura]);
                if ($bozza) $pdo->prepare("UPDATE $pt SET struttura_bozza = ? WHERE id = ?")->execute([str_replace('Testo scritto', ZONE_SEGNAPOSTO, $struttura), (int)$pdo->lastInsertId()]);
                $reg->execute([(int)$pdo->lastInsertId(), $servizio, $zona]);
                $fatte++;
            }
            $pdo->commit();
        }
        zone_aggiorna_voci();
        $esci(['seminate' => $fatte, 'modello' => $mod, 'secondi' => round(microtime(true) - $t0, 1), 'voci' => zone_conta()]);
    default: $esci(['errore' => 'comando sconosciuto']);
}
