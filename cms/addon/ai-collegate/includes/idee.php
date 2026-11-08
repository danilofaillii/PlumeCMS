<?php
/**
 * Addon AI collegate (2.34.0): articoli nuovi a partire dagli argomenti. suggest_topics calcola dai contenuti pubblicati i temi
 * coperti e i "buchi" (parole chiave citate senza un articolo dedicato, tag con un solo articolo, categorie povere, domande
 * frequenti che possono diventare articoli, articoli molto letti senza articoli collegati): dà materiale, non inventa titoli.
 * propose_article_ideas salva fino a 20 idee (tabella ai_idee); l'amministratore le approva, le scarta o le corregge nella
 * pagina "Idee per articoli", le ordina e dà una data prevista (solo promemoria). list_article_ideas le restituisce;
 * create_draft con idea_id scrive la bozza e lega l'idea ("scritta, in bozza"). Mai pubblicare. Manuale tecnico, 47.19-47.21.
 */
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/stile.php';

/** Le idee in una chiamata, quelle in attesa che un collegamento può lasciare, le voci di una scaletta e i collegati. */
const AIC_IDEE_PER_CHIAMATA = 20;
const AIC_IDEE_MAX_ATTESA = 100;
const AIC_IDEE_SCALETTA_MAX = 15;
const AIC_IDEE_COLLEGATI_MAX = 8;
/** Gli stati di un'idea: in attesa di decisione, approvata (da scrivere), scritta (c'è la bozza), scartata. */
const AIC_IDEE_STATI = ['attesa' => 'Da decidere', 'da_scrivere' => 'Da scrivere', 'scritta' => 'Scritta (in bozza)', 'scartata' => 'Scartata'];
/** Gli articoli pubblicati letti al massimo da suggest_topics (i più recenti). */
const AIC_IDEE_MAX_ARTICOLI = 1000;

// ------------------------------------------------------------
// suggest_topics
// ------------------------------------------------------------

/** I termini di una frase per i confronti (parole di almeno 3 lettere, senza parole vuote). */
function aic_idee_termini(string $s): array {
    $out = [];
    foreach (explode(' ', aic_stile_norm($s)) as $p) if (mb_strlen($p) >= 3 && !in_array($p, AIC_STILE_VUOTE, true) && !ctype_digit($p) && !in_array($p, $out, true)) $out[] = $p;
    return $out;
}

/** Il testo semplice di un articolo per suggest_topics, senza disegnare: testo della scheda o stringhe dell'albero. */
function aic_idee_testo(array $r): string {
    $j = trim((string)($r['struttura'] ?? ''));
    if ($j !== '') {
        $a = json_decode($j, true);
        if (is_array($a)) {
            $t = [];
            array_walk_recursive($a, function ($v, $k) use (&$t) { if (is_string($v) && mb_strlen($v) > 2 && !preg_match('~^(#|/|https?:|\{|rgba?\(|var\(|[a-z0-9_-]+$)~', $v)) $t[] = $v; });
            return aic_stile_piano(implode(' . ', $t));
        }
    }
    return aic_stile_piano((string)($r['content'] ?? ''));
}

/** Gli spunti per articoli nuovi. Ritorna il JSON di suggest_topics. */
function aic_idee_spunti(array $a = []): array {
    $max = max(5, min(40, (int)($a['quanti'] ?? 25)));
    $t = tbl('articles');
    $righe = db()->query('SELECT id, title, slug, content, struttura, faq, keyword_target, keywords_secondarie, categorie, category, tag_slugs, tags FROM ' . $t . ' WHERE '
        . contenuti_sql_stato('articoli', 'pubblicato') . ' ORDER BY publish_date DESC, id DESC LIMIT ' . AIC_IDEE_MAX_ARTICOLI)->fetchAll();
    $pagine = db()->query('SELECT id, title, slug FROM ' . tbl('pages') . ' WHERE ' . contenuti_sql_stato('pagine', 'pubblicato') . ' LIMIT 2000')->fetchAll();
    $tagNomi = db()->query('SELECT slug, name FROM ' . tbl('tags'))->fetchAll(PDO::FETCH_KEY_PAIR);
    $cats = contenuti_categorie();
    if (!$righe) return ['articoli_letti' => 0, 'temi_coperti' => [], 'spunti' => [], 'nota' => 'Il sito non ha ancora articoli pubblicati: non ci sono argomenti da cui partire. Chiedi al proprietario di quali temi vuole parlare.', 'come_leggerlo' => aic_idee_come_leggerlo()];
    // Indici: per ogni articolo i termini del titolo, della parola chiave e dello slug ("dedicato"), e il testo normalizzato.
    $art = [];
    foreach ($righe as $r) {
        $testo = aic_idee_testo($r);
        $art[(int)$r['id']] = [
            'id' => (int)$r['id'], 'titolo' => (string)$r['title'], 'percorso' => contenuti_percorso('articoli', $r),
            'dedicato' => ' ' . aic_stile_norm($r['title'] . ' ' . $r['keyword_target'] . ' ' . str_replace('-', ' ', (string)$r['slug'])) . ' ',
            'norm' => ' ' . aic_stile_norm($r['title'] . ' ' . $testo) . ' ',
            'testo' => $testo, 'r' => $r,
        ];
    }
    $dedicatoPagine = [];
    foreach ($pagine as $p) $dedicatoPagine[] = ' ' . aic_stile_norm($p['title'] . ' ' . str_replace('-', ' ', (string)$p['slug'])) . ' ';
    $haDedicato = function (string $tema) use ($art, $dedicatoPagine): ?array {
        $ter = aic_idee_termini($tema);
        if (!$ter) return ['id' => 0];
        foreach ($art as $x) { $tutti = true; foreach ($ter as $w) if (!str_contains($x['dedicato'], ' ' . $w)) { $tutti = false; break; } if ($tutti) return $x; }
        foreach ($dedicatoPagine as $d) { $tutti = true; foreach ($ter as $w) if (!str_contains($d, ' ' . $w)) { $tutti = false; break; } if ($tutti) return ['id' => -1]; }
        return null;
    };
    $doveCitato = function (string $tema) use ($art): array {
        $ter = aic_idee_termini($tema);
        $out = [];
        if (!$ter) return $out;
        $frase = ' ' . implode(' ', $ter);
        foreach ($art as $x) {
            $ok = str_contains($x['norm'], ' ' . aic_stile_norm($tema)) || str_contains($x['norm'], $frase);
            if (!$ok && count($ter) > 1) { $ok = true; foreach ($ter as $w) if (!str_contains($x['norm'], ' ' . $w)) { $ok = false; break; } }
            if ($ok) $out[] = $x;
        }
        return $out;
    };
    $rif = fn(array $x) => ['id' => $x['id'], 'titolo' => $x['titolo'], 'percorso' => $x['percorso']];
    $spunti = []; $visti = [];
    $aggiungi = function (string $tipo, string $tema, string $perche, array $nasce, int $forza) use (&$spunti, &$visti, $rif) {
        $k = aic_stile_norm($tema);
        if ($k === '' || isset($visti[$k])) return;
        $visti[$k] = true;
        $nasce = array_slice($nasce, 0, 5);
        $spunti[] = ['tipo' => $tipo, 'tema' => $tema, 'perche' => $perche, 'nasce_da' => array_map($rif, $nasce),
            'link_interni_consigliati' => array_map(fn($x) => ['titolo' => $x['titolo'], 'percorso' => $x['percorso']], array_slice($nasce, 0, 4)), 'forza' => $forza];
    };
    // 1. Parole chiave correlate citate negli articoli e senza un articolo dedicato.
    $correlate = [];
    foreach ($art as $x) foreach (preg_split('/[,;\n]+/', (string)$x['r']['keywords_secondarie']) as $k) { $k = trim(aic_testo($k, 120)); if (mb_strlen($k) >= 4) $correlate[mb_strtolower($k)] = $k; }
    foreach ($correlate as $k) {
        if ($haDedicato($k) !== null) continue;
        $dove = $doveCitato($k);
        $aggiungi('parola_chiave_senza_articolo', $k, 'È fra le parole chiave correlate' . ($dove ? ' e la citano ' . count($dove) . ' articoli' : '') . ', ma nessun articolo ha questo argomento nel titolo, nella parola chiave o nell\'indirizzo.', $dove, 80 + 10 * min(8, count($dove)));
    }
    // 2. Espressioni ricorrenti nel testo (in almeno tre articoli) senza un articolo dedicato.
    // Un'espressione che sta in quasi tutti gli articoli (oltre il 60%, con almeno cinque articoli) è una formula dello stile
    // ("ora tocca a te"), non un argomento: non diventa uno spunto.
    $perArt = array_map(fn($x) => aic_stile_token($x['testo']), $art);
    $ric = aic_stile_ricorrenti($perArt);
    $formula = count($art) >= 5 ? (int)floor(count($art) * 0.6) : PHP_INT_MAX;
    foreach (array_merge($ric['espressioni_di_3'], $ric['espressioni_di_2']) as $e) {
        if ($e['articoli'] < 3 || $e['articoli'] > $formula || count(aic_idee_termini($e['testo'])) < 2) continue;
        if ($haDedicato($e['testo']) !== null) continue;
        $dove = $doveCitato($e['testo']);
        $aggiungi('tema_citato_senza_articolo', $e['testo'], '«' . $e['testo'] . '» compare ' . $e['volte'] . ' volte in ' . $e['articoli'] . ' articoli, ma non ha un articolo suo.', $dove, 40 + 5 * min(8, $e['articoli']));
    }
    // 3. Domande frequenti delle schede e sottotitoli a domanda che possono diventare articoli.
    foreach ($art as $x) {
        $domande = array_column(function_exists('sito_faq_da_json') ? sito_faq_da_json((string)$x['r']['faq']) : [], 'domanda');
        $j = trim((string)$x['r']['struttura']);
        $html = $j !== '' ? implode(' ', array_map(fn($w) => (string)$w, (function () use ($j) { $o = []; $a = json_decode($j, true); if (is_array($a)) array_walk_recursive($a, function ($v) use (&$o) { if (is_string($v) && str_contains($v, '<h')) $o[] = $v; }); return $o; })())) : (string)$x['r']['content'];
        if (preg_match_all('~<h[23]\b[^>]*>([\s\S]*?)</h[23]>~i', $html, $mh)) foreach ($mh[1] as $hh) { $q = aic_stile_piano($hh); if (str_ends_with($q, '?')) $domande[] = $q; }
        foreach (array_slice($domande, 0, 10) as $q) {
            $q = aic_testo($q, 200);
            if (mb_strlen($q) < 10 || count(aic_idee_termini($q)) < 2 || $haDedicato($q) !== null) continue;
            $aggiungi('domanda_che_diventa_articolo', $q, 'È una domanda dei lettori dentro «' . $x['titolo'] . '»: con una risposta lunga e completa diventa un articolo suo (e l\'articolo di partenza lo collega).', [$x], 45);
        }
    }
    // 4. Tag con un solo articolo e categorie povere.
    $tagArt = []; $catArt = [];
    foreach ($art as $x) {
        $ts = sito_lista((string)($x['r']['tag_slugs'] ?? '')) ?: array_map('contenuti_slug', sito_lista((string)($x['r']['tags'] ?? '')));
        foreach (array_unique($ts) as $s) if ($s !== '') $tagArt[$s][] = $x;
        foreach (contenuti_categorie_di($x['r']) as $c) if ($c !== '') $catArt[$c][] = $x;
    }
    foreach ($tagArt as $s => $xs) if (count($xs) === 1) {
        $nome = (string)($tagNomi[$s] ?? $s);
        $aggiungi('tag_con_un_articolo', $nome, 'Il tag «' . $nome . '» ha un solo articolo: un secondo articolo sullo stesso tema rende utile la pagina del tag e collega i due.', $xs, 35);
    }
    $categorieOut = [];
    foreach ($cats as $s => $c) {
        $nArt = count($catArt[$s] ?? []);
        $categorieOut[] = ['categoria' => (string)$c['name'], 'slug' => (string)$s, 'articoli' => $nArt];
        if ($nArt <= 2) $aggiungi('categoria_povera', (string)$c['name'], 'La categoria «' . $c['name'] . '» ha ' . ($nArt === 0 ? 'nessun articolo' : ($nArt === 1 ? 'un solo articolo' : 'due articoli')) . ': chi la apre trova poco.', $catArt[$s] ?? [], 30 + (2 - $nArt) * 5);
    }
    // 5. Articoli molto letti senza articoli collegati (solo con l'addon Statistiche acceso).
    $letti = ['disponibile' => false];
    if (cms_modulo('statistiche') && ($dir = cms_modulo_dir('statistiche')) && is_file($dir . '/includes/statistiche.php')) {
        require_once $dir . '/includes/statistiche.php';
        if (function_exists('st_classifica')) {
            $letti = ['disponibile' => true, 'giorni' => 90];
            $perPercorso = [];
            foreach ($art as $x) $perPercorso[$x['percorso']] = $x;
            $entranti = [];
            foreach ($art as $x) foreach (aic_stile_link_interni((string)$x['r']['content'] . ' ' . (string)$x['r']['struttura']) as $u) { $u = '/' . trim(strtok($u, '?#'), '/') . '/'; if (isset($perPercorso[$u]) && $perPercorso[$u]['id'] !== $x['id']) $entranti[$u][$x['id']] = true; }
            foreach (st_classifica('pagina', date('Y-m-d', time() - 89 * 86400), date('Y-m-d'), 30) as $s) {
                $u = '/' . trim((string)($s['pagina'] ?? ''), '/') . '/';
                if (!isset($perPercorso[$u])) continue;
                $n = count($entranti[$u] ?? []);
                if ($n > 1) continue;
                $x = $perPercorso[$u];
                $aggiungi('molto_letto_senza_collegati', $x['titolo'], '«' . $x['titolo'] . '» ha ' . (int)$s['v'] . ' visite negli ultimi 90 giorni ma ' . ($n ? 'un solo articolo' : 'nessun articolo') . ' del sito lo collega: un articolo vicino di argomento porta chi legge da lì al resto del sito.', [$x], 70 + min(30, (int)$s['v']));
            }
        }
    }
    usort($spunti, fn($a, $b) => [$b['forza'], $a['tema']] <=> [$a['forza'], $b['tema']]);
    $tagOut = [];
    foreach ($tagArt as $s => $xs) $tagOut[] = ['tag' => (string)($tagNomi[$s] ?? $s), 'articoli' => count($xs)];
    usort($tagOut, fn($a, $b) => $b['articoli'] <=> $a['articoli']);
    $kw = [];
    foreach ($art as $x) { $k = trim((string)$x['r']['keyword_target']); if ($k !== '') $kw[] = ['parola_chiave' => $k, 'articolo' => $x['titolo'], 'id' => $x['id']]; }
    usort($categorieOut, fn($a, $b) => $b['articoli'] <=> $a['articoli']);
    return [
        'articoli_letti' => count($art),
        'temi_coperti' => ['categorie' => $categorieOut, 'tag' => array_slice($tagOut, 0, 25), 'parole_chiave' => array_slice($kw, 0, 60),
            'titoli' => array_map(fn($x) => ['id' => $x['id'], 'titolo' => $x['titolo'], 'percorso' => $x['percorso']], array_slice(array_values($art), 0, 60))],
        'spunti' => array_slice($spunti, 0, $max),
        'statistiche' => $letti,
        'come_leggerlo' => aic_idee_come_leggerlo(),
    ];
}

function aic_idee_come_leggerlo(): array {
    return [
        'Gli spunti sono calcolati dal sito sugli articoli pubblicati (al massimo i ' . AIC_IDEE_MAX_ARTICOLI . ' più recenti) e sulle pagine: non sono titoli, sono il materiale da cui proporre articoli nuovi.',
        'Un tema "ha un articolo dedicato" se tutte le sue parole (senza le parole vuote) stanno nel titolo, nella parola chiave o nell\'indirizzo di un articolo, o nel titolo di una pagina.',
        'Tipi: parola_chiave_senza_articolo (fra le parole correlate delle schede), tema_citato_senza_articolo (espressione in almeno tre articoli ma non in quasi tutti: quelle oltre il 60% degli articoli sono formule dello stile), domanda_che_diventa_articolo (domande frequenti e sottotitoli a domanda), tag_con_un_articolo, categoria_povera (due articoli o meno), molto_letto_senza_collegati (solo con l\'addon Statistiche: tra le 30 pagine più viste in 90 giorni, collegato da al massimo un articolo).',
        '"nasce_da" sono gli articoli in cui il tema compare; "link_interni_consigliati" sono gli articoli che il nuovo articolo dovrebbe collegare (e da cui farsi collegare). "forza" ordina gli spunti: più alta, più utile.',
        'Poi proponi le idee con propose_article_ideas (titolo, parola chiave, a chi serve, scaletta, articoli da collegare, motivo): le approva l\'amministratore. Le approvate le leggi con list_article_ideas e le scrivi con create_draft passando idea_id.',
    ];
}

// ------------------------------------------------------------
// Le idee (tabella ai_idee)
// ------------------------------------------------------------

function aic_idea(int $id): ?array {
    aic_schema();
    if ($id < 1) return null;
    $st = db()->prepare('SELECT * FROM ' . tbl('ai_idee') . ' WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function aic_idee_in_attesa(): int {
    try {
        aic_schema();
        return (int)db()->query('SELECT COUNT(*) FROM ' . tbl('ai_idee') . " WHERE stato = 'attesa'")->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** Una scaletta pulita: elenco di punti in testo semplice. */
function aic_idee_scaletta($in): array {
    if (is_string($in)) $in = preg_split('/\n+/', $in);
    if (!is_array($in)) return [];
    $out = [];
    foreach (array_values($in) as $x) { $t = aic_testo(is_array($x) ? ($x['punto'] ?? $x['testo'] ?? '') : $x, 300); $t = ltrim($t, "-*\x{2022} \t"); if ($t !== '') $out[] = $t; if (count($out) >= AIC_IDEE_SCALETTA_MAX) break; }
    return $out;
}

/** Gli articoli da collegare: id di articoli o pagine pubblicati di questo sito. Ritorna [collegati, avvisi]. */
function aic_idee_collegati($in): array {
    if (!is_array($in)) return [[], []];
    $out = []; $avvisi = [];
    foreach (array_slice(array_values($in), 0, AIC_IDEE_COLLEGATI_MAX * 2) as $x) {
        $tipo = is_array($x) && in_array($x['tipo'] ?? 'articoli', ['articoli', 'pagine'], true) ? (string)($x['tipo'] ?? 'articoli') : 'articoli';
        $id = is_array($x) ? ($x['id'] ?? 0) : $x;
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) { $avvisi[] = 'Un articolo da collegare senza un id valido è stato ignorato.'; continue; }
        $r = contenuti_leggi($tipo, (int)$id);
        if (!$r || !empty($r['cestino']) || contenuti_stato($tipo, $r) !== 'pubblicato') { $avvisi[] = 'Il contenuto ' . $tipo . ' ' . (int)$id . ' non è pubblicato (o non esiste): tolto dagli articoli da collegare.'; continue; }
        $k = $tipo . ':' . (int)$id;
        if (isset($out[$k])) continue;
        $out[$k] = ['tipo' => $tipo, 'id' => (int)$id, 'titolo' => (string)$r['title'], 'percorso' => contenuti_percorso($tipo, $r)];
        if (count($out) >= AIC_IDEE_COLLEGATI_MAX) break;
    }
    return [array_values($out), array_values(array_unique($avvisi))];
}

/** Valida un'idea che arriva dall'AI. Ritorna [campi, avvisi]. Lancia AicErrore con il numero dell'idea. */
function aic_idea_valida($x, int $n): array {
    if (!is_array($x)) throw new AicErrore('Idea ' . $n . ': deve essere un oggetto {titolo, parola_chiave, a_chi_serve, scaletta, collegati, motivo}.');
    $titolo = aic_testo($x['titolo'] ?? '', 300);
    if (mb_strlen($titolo) < 5) throw new AicErrore('Idea ' . $n . ': manca il titolo (almeno 5 caratteri di testo semplice).');
    $motivo = aic_testo($x['motivo'] ?? '', 1000, true);
    if (mb_strlen($motivo) < 5) throw new AicErrore('Idea ' . $n . ': manca il motivo (perché questo articolo serve al sito: lo legge l\'amministratore).');
    [$coll, $avvisi] = aic_idee_collegati($x['collegati'] ?? []);
    foreach ($avvisi as &$a) $a = 'Idea ' . $n . ': ' . $a;
    unset($a);
    return [['titolo' => $titolo, 'parola_chiave' => aic_testo($x['parola_chiave'] ?? '', 200), 'pubblico' => aic_testo($x['a_chi_serve'] ?? '', 500),
        'scaletta' => aic_idee_scaletta($x['scaletta'] ?? []), 'collegati' => $coll, 'motivo' => $motivo], $avvisi];
}

/** propose_article_ideas: fino a 20 idee in una chiamata, tutte o nessuna. */
function aic_idee_proponi(array $conn, array $a): array {
    aic_schema();
    $in = $a['idee'] ?? null;
    if (!is_array($in) || !$in || !array_is_list($in)) throw new AicErrore('"idee" deve essere un elenco di idee (da 1 a ' . AIC_IDEE_PER_CHIAMATA . ').');
    if (count($in) > AIC_IDEE_PER_CHIAMATA) throw new AicErrore('Troppe idee in una chiamata: ' . count($in) . ' (massimo ' . AIC_IDEE_PER_CHIAMATA . '). Nessuna idea salvata.');
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('ai_idee') . " WHERE connessione_id = ? AND stato = 'attesa'");
    $st->execute([(int)$conn['id']]);
    $attesa = (int)$st->fetchColumn();
    if ($attesa + count($in) > AIC_IDEE_MAX_ATTESA) throw new AicErrore('Questo collegamento ha già ' . $attesa . ' idee in attesa (al massimo ' . AIC_IDEE_MAX_ATTESA . '): aspetta che l\'amministratore decida. Nessuna idea salvata.', 429);
    $valide = []; $avvisi = []; $titoli = [];
    foreach ($in as $i => $x) {
        [$v, $av] = aic_idea_valida($x, $i + 1);
        $k = aic_stile_norm($v['titolo']);
        if (isset($titoli[$k])) throw new AicErrore('Idea ' . ($i + 1) . ': stesso titolo dell\'idea ' . $titoli[$k] . '. Nessuna idea salvata.');
        $titoli[$k] = $i + 1;
        $valide[] = $v; $avvisi = array_merge($avvisi, $av);
    }
    $ordine = (int)db()->query('SELECT COALESCE(MAX(ordine), 0) FROM ' . tbl('ai_idee'))->fetchColumn();
    $ins = db()->prepare('INSERT INTO ' . tbl('ai_idee') . ' (connessione_id, client, titolo, parola_chiave, pubblico, scaletta, collegati, motivo, stato, ordine, creato) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'attesa\', ?, NOW())');
    $ids = [];
    db()->beginTransaction();
    try {
        foreach ($valide as $v) {
            $ins->execute([(int)$conn['id'], mb_substr((string)$conn['nome'], 0, 200), $v['titolo'], $v['parola_chiave'], $v['pubblico'], json_encode($v['scaletta'], JSON_UNESCAPED_UNICODE), json_encode($v['collegati'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $v['motivo'], ++$ordine]);
            $ids[] = ['idea_id' => (int)db()->lastInsertId(), 'titolo' => $v['titolo']];
        }
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    return ['idee' => $ids, 'stato' => 'attesa', 'avvisi' => $avvisi,
        'nota' => 'Idee registrate: l\'amministratore le vede in AI collegate > Idee per articoli e le approva, le corregge o le scarta. Le approvate le trovi con list_article_ideas (stato "da_scrivere") e le scrivi con create_draft passando idea_id.',
        '_registro' => count($ids) . ' idee proposte'];
}

/** Un'idea come la vede l'AI (list_article_ideas). */
function aic_idea_per_ai(array $r): array {
    $out = ['idea_id' => (int)$r['id'], 'stato' => (string)$r['stato'], 'stato_testo' => AIC_IDEE_STATI[$r['stato']] ?? (string)$r['stato'], 'titolo' => (string)$r['titolo'],
        'parola_chiave' => (string)$r['parola_chiave'], 'a_chi_serve' => (string)$r['pubblico'], 'scaletta' => json_decode((string)$r['scaletta'], true) ?: [],
        'collegati' => json_decode((string)$r['collegati'], true) ?: [], 'motivo' => (string)$r['motivo'], 'data_prevista' => $r['data_prevista'] ? (string)$r['data_prevista'] : null,
        'ordine' => (int)$r['ordine'], 'nota_amministratore' => (string)$r['nota'], 'proposta_da' => (string)$r['client'], 'creata' => (string)$r['creato']];
    if ((int)$r['articolo_id'] > 0) {
        $a = contenuti_leggi('articoli', (int)$r['articolo_id']);
        $out['bozza'] = $a ? ['id' => (int)$a['id'], 'stato' => !empty($a['cestino']) ? 'cestino' : contenuti_stato('articoli', $a), 'titolo' => (string)$a['title']] : ['id' => (int)$r['articolo_id'], 'stato' => 'eliminata'];
    }
    return $out;
}

/** list_article_ideas: tutte le idee del sito (anche di altri collegamenti), filtro per stato, nell'ordine del piano. */
function aic_idee_elenco(array $a): array {
    aic_schema();
    $stato = (string)($a['stato'] ?? 'da_scrivere');
    $per = max(1, min(50, (int)($a['per_pagina'] ?? 20)));
    $pagina = max(1, (int)($a['pagina'] ?? 1));
    $dove = isset(AIC_IDEE_STATI[$stato]) ? ' WHERE stato = ?' : '';
    $par = $dove ? [$stato] : [];
    $c = db()->prepare('SELECT COUNT(*) FROM ' . tbl('ai_idee') . $dove); $c->execute($par);
    $tot = (int)$c->fetchColumn();
    $st = db()->prepare('SELECT * FROM ' . tbl('ai_idee') . $dove . " ORDER BY (data_prevista IS NULL), DATE_FORMAT(data_prevista, '%Y-%m'), ordine, id LIMIT " . (($pagina - 1) * $per) . ', ' . $per);
    $st->execute($par);
    return ['idee' => array_map('aic_idea_per_ai', $st->fetchAll()), 'totale' => $tot, 'pagina' => $pagina, 'per_pagina' => $per, 'stato' => isset(AIC_IDEE_STATI[$stato]) ? $stato : 'tutte',
        'nota' => 'Scrivi le idee "da_scrivere" con create_draft (tipo articoli) passando idea_id: la bozza resta legata all\'idea. Segui il profilo di stile (get_style_profile). Non si pubblica mai: lo fa una persona dal pannello.'];
}

/**
 * Controlla un idea_id per create_draft o update_draft. Ritorna la riga dell'idea. $articolo: l'id della bozza che si sta
 * modificando (update_draft), 0 per una bozza nuova.
 */
function aic_idea_per_bozza($ideaId, string $tipo, int $articolo = 0): array {
    if (!is_int($ideaId) && !(is_string($ideaId) && ctype_digit($ideaId))) throw new AicErrore('idea_id deve essere il numero di un\'idea (da list_article_ideas).');
    if ($tipo !== 'articoli') throw new AicErrore('idea_id vale solo per gli articoli (tipo "articoli").');
    $i = aic_idea((int)$ideaId);
    if (!$i) throw new AicErrore('Idea ' . (int)$ideaId . ' non trovata su questo sito: controlla con list_article_ideas.', 404);
    if ($i['stato'] === 'scritta' && $articolo > 0 && (int)$i['articolo_id'] === $articolo) return $i;
    if ($i['stato'] === 'scritta') throw new AicErrore('L\'idea ' . (int)$i['id'] . ' è già scritta: la bozza è l\'articolo ' . (int)$i['articolo_id'] . '. Per cambiarla usa update_draft su quell\'articolo.', 409);
    if ($i['stato'] !== 'da_scrivere') throw new AicErrore('L\'idea ' . (int)$i['id'] . ' è "' . (AIC_IDEE_STATI[$i['stato']] ?? $i['stato']) . '": si scrivono solo le idee approvate dall\'amministratore (stato "da_scrivere").', 409);
    return $i;
}

/** Lega un'idea alla bozza scritta: stato "scritta". */
function aic_idea_lega(int $idea, int $articolo): void {
    db()->prepare('UPDATE ' . tbl('ai_idee') . " SET stato = 'scritta', articolo_id = ? WHERE id = ? AND stato IN ('da_scrivere', 'scritta')")->execute([$articolo, $idea]);
}

// ------------------------------------------------------------
// Pannello: decisioni, modifiche, piano
// ------------------------------------------------------------

/** Approva o scarta (dal pannello). Solo idee in attesa (scartare vale anche per quelle da scrivere). */
function aic_idea_decidi(int $id, bool $approva, string $chi): bool {
    $st = db()->prepare('UPDATE ' . tbl('ai_idee') . ' SET stato = ?, deciso = NOW(), deciso_da = ? WHERE id = ? AND stato IN (' . ($approva ? "'attesa'" : "'attesa', 'da_scrivere'") . ')');
    $st->execute([$approva ? 'da_scrivere' : 'scartata', mb_substr($chi, 0, 100), $id]);
    return $st->rowCount() > 0;
}

/** Modifica dal pannello: titolo, parola chiave, a chi serve, scaletta, nota per l'AI, data prevista. */
function aic_idea_modifica(int $id, array $v): void {
    $i = aic_idea($id);
    if (!$i || in_array($i['stato'], ['scartata'], true)) throw new RuntimeException('Idea non trovata o scartata.');
    $titolo = aic_testo($v['titolo'] ?? '', 300);
    if (mb_strlen($titolo) < 5) throw new RuntimeException('Il titolo deve avere almeno 5 caratteri.');
    $data = aic_idea_data($v['data_prevista'] ?? '');
    db()->prepare('UPDATE ' . tbl('ai_idee') . ' SET titolo = ?, parola_chiave = ?, pubblico = ?, scaletta = ?, nota = ?, data_prevista = ? WHERE id = ?')
        ->execute([$titolo, aic_testo($v['parola_chiave'] ?? '', 200), aic_testo($v['pubblico'] ?? '', 500), json_encode(aic_idee_scaletta((string)($v['scaletta'] ?? '')), JSON_UNESCAPED_UNICODE), aic_testo($v['nota'] ?? '', 500), $data, $id]);
}

/** Una data AAAA-MM-GG valida o null. Lancia per una data scritta male. */
function aic_idea_data($d): ?string {
    $d = trim((string)$d);
    if ($d === '') return null;
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || (int)$m[1] < 2000 || (int)$m[1] > 2100) throw new RuntimeException('Data prevista non valida: usa il calendario (giorno, mese, anno).');
    return $d;
}

/** Sposta un'idea da scrivere su o giù nel piano (scambia l'ordine con la vicina dello stesso stato). */
function aic_idea_sposta(int $id, string $verso): bool {
    $i = aic_idea($id);
    if (!$i || !in_array($i['stato'], ['da_scrivere', 'scritta'], true)) return false;
    $su = $verso === 'su';
    // Si sposta dentro il suo mese (o fra le idee senza data): il piano è diviso per mese, e nel mese conta l'ordine.
    $mese = $i['data_prevista'] ? substr((string)$i['data_prevista'], 0, 7) : '';
    $st = db()->prepare('SELECT id, ordine FROM ' . tbl('ai_idee') . " WHERE stato IN ('da_scrivere', 'scritta') AND " . ($mese === '' ? 'data_prevista IS NULL' : "DATE_FORMAT(data_prevista, '%Y-%m') = ?") . ' AND '
        . ($su ? '(ordine < ? OR (ordine = ? AND id < ?)) ORDER BY ordine DESC, id DESC' : '(ordine > ? OR (ordine = ? AND id > ?)) ORDER BY ordine, id') . ' LIMIT 1');
    $st->execute(array_merge($mese === '' ? [] : [$mese], [(int)$i['ordine'], (int)$i['ordine'], $id]));
    $v = $st->fetch();
    if (!$v) return false;
    $o1 = (int)$i['ordine']; $o2 = (int)$v['ordine'];
    if ($o1 === $o2) $o2 = $su ? $o1 - 1 : $o1 + 1;
    $up = db()->prepare('UPDATE ' . tbl('ai_idee') . ' SET ordine = ? WHERE id = ?');
    $up->execute([$o2, $id]); $up->execute([$o1, (int)$v['id']]);
    return true;
}
