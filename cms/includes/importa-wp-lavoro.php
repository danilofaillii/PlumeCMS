<?php
/**
 * Import da WordPress (1.4.0): il lavoro. Dopo la scelta, a giri brevi (IMPORTA_WP_GIRO secondi ognuno) che riprendono da
 * dove erano: copia del sito, categorie, tag, media, pagine, articoli, menu. Tutto entra "in prova":
 * - articoli e pagine come bozze (lo stato di WordPress si ricorda nella tabella delle voci, colonna stato_finale);
 * - categorie e tag nuovi nascosti ai visitatori (impostazione importa_wp_nascosti, letta da sito_categorie e sito_tag);
 * - redirect spenti (attivo = 0), menu senza posizione;
 * - i file stanno già in uploads/ (con un nome a caso in coda: nessuno li trova senza l'indirizzo).
 * L'anteprima dell'amministratore (?preview=1) mostra tutto come sarà.
 * Conferma: pubblicati pubblicati, programmati programmati, bozze bozze; redirect accesi; menu principale se scelto.
 * Annulla: via tutto quello che è entrato, voce per voce (il resto del sito non si tocca). Prima di cominciare c'è
 * comunque una copia del sito (Impostazioni > Copia del sito), per ogni evenienza.
 * Ogni giro è ripetibile: una voce già entrata (tabella importa_wp_voci) non entra due volte.
 */

require_once __DIR__ . '/importa-wp-analisi.php';
require_once __DIR__ . '/importa-wp-converti.php';
require_once __DIR__ . '/contenuti.php';

const IMPORTA_WP_LOTTO = 10;           // contenuti per richiesta durante l'import
const IMPORTA_WP_TENTATIVI = 3;        // richieste fallite di fila prima di saltare un lotto

const IMPORTA_WP_SCELTE = ['articoli', 'pagine', 'categorie', 'tag', 'media', 'menu', 'seo', 'redirect'];

/** Le scelte dal form: voci spuntate e, per gli articoli, quali stati. */
function importa_wp_scelta_da(array $p): array {
    $sc = [];
    foreach (IMPORTA_WP_SCELTE as $k) $sc[$k] = !empty($p['voce'][$k]);
    $sc['stati'] = array_values(array_intersect((array)($p['stati'] ?? []), ['publish', 'draft', 'future']));
    if ($sc['articoli'] && !$sc['stati']) $sc['articoli'] = false;
    return $sc;
}

/** Comincia l'import: piano dei passi, code, mappa dei link nuovi. */
function importa_wp_avvia(array &$s, array $sc): void {
    if (!array_filter(array_intersect_key($sc, array_flip(IMPORTA_WP_SCELTE)))) throw new ImportaWpErrore('Spunta almeno una voce da importare.');
    $s['scelta'] = $sc;
    $stati = fn(string $st) => $st === 'publish' ? 'publish' : ($st === 'future' ? 'future' : 'draft');
    $s['coda'] = [
        'pagine' => $sc['pagine'] ? array_column($s['indice']['pagine'] ?? [], 'id') : [],
        'articoli' => $sc['articoli'] ? array_column(array_filter($s['indice']['articoli'] ?? [], fn($x) => in_array($stati($x['stato']), $sc['stati'], true)), 'id') : [],
    ];
    $s['piano'] = array_values(array_filter(['copia', $sc['categorie'] ? 'categorie' : '', $sc['tag'] ? 'tag' : '', $sc['media'] ? 'media' : '', $s['coda']['pagine'] ? 'pagine' : '', $s['coda']['articoli'] ? 'articoli' : '', $sc['menu'] ? 'menu' : '', 'fine']));
    $s['cursore'] = ['passo' => 0, 'i' => 0, 'tentativi' => 0];
    $s['esito'] = ['entrati' => [], 'saltati' => [], 'avvisi' => [], 'inizio' => time()];
    // La mappa: vecchio percorso -> nuovo, per i link nei testi e nei menu. Solo quello che entra davvero.
    $m = []; $visti = [];
    foreach (['pagine', 'articoli'] as $t) foreach ($s['indice'][$t] ?? [] as $x) {
        if (!in_array($x['id'], $s['coda'][$t], true) || isset($visti[$x['slug']])) continue;
        // Un contenuto che non entra perché l'indirizzo c'è già: i link verso di lui portano a quello che c'è in plumeCMS
        // (stesso indirizzo), meglio che a un sito che verrà chiuso. Il report lo dice.
        $visti[$x['slug']] = true;
        $m[$x['percorso']] = '/' . $x['slug'] . '/';
        $m['/?p=' . $x['id']] = '/' . $x['slug'] . '/';
        if ($t === 'pagine') $m['/?page_id=' . $x['id']] = '/' . $x['slug'] . '/';
    }
    foreach (['categorie' => 'category', 'tag' => 'tag'] as $t => $base) {
        if (!$sc[$t]) continue;
        foreach ($s['indice'][$t] ?? [] as $c) if ($c['percorso'] !== '') $m[$c['percorso']] = "/$base/" . $c['slug'] . '/';
    }
    $s['mappa'] = $m;
    $s['fase'] = 'importa';
    importa_wp_installa();
}

/** A che punto e l'import: [fatti, totale, testo]. */
function importa_wp_avanzamento(array $s): array {
    $quanti = ['copia' => 1, 'categorie' => count($s['indice']['categorie'] ?? []), 'tag' => count($s['indice']['tag'] ?? []), 'media' => count($s['indice']['media'] ?? []),
        'pagine' => count($s['coda']['pagine'] ?? []), 'articoli' => count($s['coda']['articoli'] ?? []), 'menu' => count($s['indice']['menu']['menu'] ?? []), 'fine' => 1];
    $tot = 0; $fatti = 0;
    $c = $s['cursore'] ?? ['passo' => 0, 'i' => 0];
    foreach ($s['piano'] ?? [] as $k => $p) {
        $tot += $quanti[$p];
        if ($k < $c['passo']) $fatti += $quanti[$p];
        elseif ($k === $c['passo']) $fatti += min($quanti[$p], (int)$c['i']);
    }
    $nomi = ['copia' => 'Faccio una copia del sito (per sicurezza)', 'categorie' => 'Categorie', 'tag' => 'Tag', 'media' => 'Scarico i media', 'pagine' => 'Pagine', 'articoli' => 'Articoli', 'menu' => 'Menu', 'fine' => 'Ultimi controlli'];
    $p = $s['piano'][$c['passo']] ?? 'fine';
    $testo = $nomi[$p] . ($quanti[$p] > 1 ? ': ' . min($quanti[$p], (int)$c['i']) . ' di ' . $quanti[$p] : '');
    return [$fatti, max(1, $tot), $testo];
}

/** Lavora fino a $fino (microtime(true)). Ritorna vero quando l'import è finito e tutto e in prova. */
function importa_wp_import_giro(array &$s, float $fino): bool {
    while (microtime(true) < $fino) {
        $c = &$s['cursore'];
        $passo = $s['piano'][$c['passo']] ?? 'fine';
        $lavoro = (string)$s['lavoro'];
        switch ($passo) {
            case 'copia':
                require_once __DIR__ . '/copia-sito.php';
                ob_start();
                try { $s['copia'] = copia_crea('prima-di-importa-wordpress'); }
                catch (Throwable $e) { ob_end_clean(); throw new ImportaWpErrore('La copia del sito, che si fa prima di importare, non è riuscita: ' . $e->getMessage() . '. L\'import non è partito: niente è cambiato.', 'copia'); }
                ob_end_clean();
                importa_wp_prossimo($c);
                break;
            case 'categorie':
            case 'tag':
                $elenco = array_values($s['indice'][$passo] ?? []);
                if ($c['i'] >= count($elenco)) { importa_wp_prossimo($c); break; }
                importa_wp_termine($s, $lavoro, $passo, $elenco[$c['i']]);
                $c['i']++;
                break;
            case 'media':
                $elenco = $s['indice']['media'] ?? [];
                if ($c['i'] >= count($elenco)) { importa_wp_prossimo($c); break; }
                $m = $elenco[$c['i']];
                $motivo = '';
                if (!in_array($m['mime'], IMPORTA_WP_MIME_OK, true)) $motivo = 'tipo di file non ammesso (' . ($m['mime'] ?: 'sconosciuto') . ')';
                elseif (importa_wp_media($s, $lavoro, $m['url'], $m['id'], $motivo) === null && $motivo === '') $motivo = 'non scaricato';
                if ($motivo !== '') importa_wp_saltato($s, 'File', basename((string)parse_url($m['url'], PHP_URL_PATH)), $motivo);
                $c['i']++;
                break;
            case 'pagine':
            case 'articoli':
                $coda = $s['coda'][$passo];
                if ($c['i'] >= count($coda)) { importa_wp_prossimo($c); break; }
                $lotto = array_slice($coda, $c['i'], IMPORTA_WP_LOTTO);
                try {
                    $r = importa_wp_json($s, $passo === 'pagine' ? '/wp/v2/pages' : '/wp/v2/posts', ['context' => 'edit', 'status' => IMPORTA_WP_STATI, 'include' => implode(',', $lotto), 'per_page' => IMPORTA_WP_LOTTO, 'orderby' => 'include']);
                } catch (ImportaWpErrore $e) {
                    if (in_array($e->tipo, ['password_persa', 'rest_chiusa', 'credenziali'], true) || ++$c['tentativi'] < IMPORTA_WP_TENTATIVI) throw $e;
                    foreach ($lotto as $id) importa_wp_saltato($s, $passo === 'pagine' ? 'Pagina' : 'Articolo', importa_wp_titolo_indice($s, $passo, $id), 'WordPress non ha risposto dopo ' . IMPORTA_WP_TENTATIVI . ' tentativi: ' . $e->getMessage());
                    $c['i'] += count($lotto); $c['tentativi'] = 0;
                    break;
                }
                $c['tentativi'] = 0;
                $perId = [];
                foreach ($r['json'] as $x) if (is_array($x) && isset($x['id'])) $perId[(int)$x['id']] = $x;
                foreach ($lotto as $k => $id) {
                    if ($k > 0 && microtime(true) >= $fino) break 2; // il giro è finito: il prossimo riparte da questo contenuto (almeno uno per giro)
                    if (isset($perId[$id])) importa_wp_contenuto($s, $lavoro, $passo, $perId[$id]);
                    else importa_wp_saltato($s, $passo === 'pagine' ? 'Pagina' : 'Articolo', importa_wp_titolo_indice($s, $passo, $id), 'non c\'è più su WordPress');
                    $c['i']++;
                }
                break;
            case 'menu':
                $menu = $s['indice']['menu']['menu'] ?? [];
                if ($c['i'] >= count($menu)) { importa_wp_prossimo($c); break; }
                importa_wp_menu($s, $lavoro, $menu[$c['i']]);
                $c['i']++;
                break;
            default: // fine
                importa_wp_dimentica_password($s);
                $s['fase'] = 'prova';
                $s['esito']['fine'] = time();
                if (function_exists('sicurezza_log')) sicurezza_log('importa_wp', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'import da ' . $s['base'] . ' in prova');
                return true;
        }
        unset($c);
    }
    return false;
}

function importa_wp_prossimo(array &$c): void {
    $c['passo']++; $c['i'] = 0; $c['tentativi'] = 0;
}

function importa_wp_titolo_indice(array $s, string $tipo, int $id): string {
    foreach ($s['indice'][$tipo] ?? [] as $x) if ($x['id'] === $id) return $x['titolo'];
    return '#' . $id;
}

function importa_wp_saltato(array &$s, string $cosa, string $titolo, string $perche): void {
    if (count($s['esito']['saltati']) < 2000) $s['esito']['saltati'][] = ['cosa' => $cosa, 'titolo' => mb_substr($titolo, 0, 200), 'perche' => mb_substr($perche, 0, 300)];
}

function importa_wp_entrato(array &$s, string $chi): void {
    $s['esito']['entrati'][$chi] = ($s['esito']['entrati'][$chi] ?? 0) + 1;
}

/** Le colonne di un titolo accettano le emoji (utf8mb4)? Se no, i caratteri a 4 byte si tolgono dal titolo. */
function importa_wp_titolo_ok(string $tabella, string $t): string {
    static $mb4 = [];
    if (!isset($mb4[$tabella])) {
        $r = db()->query('SHOW FULL COLUMNS FROM ' . tbl($tabella) . " LIKE 'title'")->fetch();
        $mb4[$tabella] = str_starts_with((string)($r['Collation'] ?? ''), 'utf8mb4');
    }
    return $mb4[$tabella] ? $t : (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $t);
}

/** Lo slug e libero adesso (tabelle lette ogni volta: durante l'import entrano contenuti nuovi)? '' oppure il motivo. */
function importa_wp_slug_libero_ora(string $slug): string {
    foreach (['articles' => 'un articolo', 'pages' => 'una pagina'] as $t => $nome) {
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl($t) . ' WHERE slug = ?');
        $st->execute([$slug]);
        if ((int)$st->fetchColumn()) return 'In plumeCMS c\'è già ' . $nome . ' con l\'indirizzo /' . $slug . '/: lasciato com\'era, questo non entra';
    }
    $r = importa_wp_slug_occupato($slug);
    return $r !== '' ? $r . ': questo non entra' : '';
}

/** Un redirect spento (si accende con la conferma). Uno che esiste già con lo stesso "da" non si tocca. */
function importa_wp_redirect(array &$s, string $lavoro, string $da, string $a): void {
    // Il sito confronta il percorso decodificato (sito/index.php): si salva così. Con caratteri a 4 byte (emoji) la colonna
    // utf8 non lo terrebbe: quel redirect si salta.
    $da = rawurldecode($da);
    if (preg_match('/[\x{10000}-\x{10FFFF}]/u', $da) || !mb_check_encoding($da, 'UTF-8')) { $s['esito']['avvisi']['redirect_emoji'] = ($s['esito']['avvisi']['redirect_emoji'] ?? 0) + 1; return; }
    if (empty($s['scelta']['redirect']) || $da === '' || $da === '/' || rtrim($da, '/') === rtrim($a, '/')) return;
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('redirect') . ' WHERE da = ?');
    $st->execute([$da]);
    if ((int)$st->fetchColumn()) { $s['esito']['avvisi']['redirect_esistenti'] = ($s['esito']['avvisi']['redirect_esistenti'] ?? 0) + 1; return; }
    db()->prepare('INSERT INTO ' . tbl('redirect') . " (da, a, codice, confronto, attivo, origine) VALUES (?, ?, 301, 'exact', 0, 'wordpress')")->execute([mb_substr($da, 0, 500), mb_substr($a, 0, 500)]);
    importa_wp_voce_aggiungi($lavoro, 'redirect', 0, $da, (int)db()->lastInsertId(), $a);
    importa_wp_entrato($s, 'redirect');
}

/** Le categorie e i tag nuovi, nascosti ai visitatori fino alla conferma. */
function importa_wp_nascondi(string $chiave, string $slug): void {
    $n = json_decode((string)get_setting('importa_wp_nascosti', ''), true) ?: [];
    $n[$chiave][] = $slug;
    $n[$chiave] = array_values(array_unique($n[$chiave]));
    save_setting('importa_wp_nascosti', json_encode($n));
}

/** Una categoria o un tag. Se lo slug c'è già in plumeCMS si usa quello che c'è. */
function importa_wp_termine(array &$s, string $lavoro, string $tipo, array $t): void {
    $genere = $tipo === 'tag' ? 'tag' : 'categoria';
    if (importa_wp_voce($lavoro, $genere, $t['id'])) return;
    $tab = $tipo === 'tag' ? 'tags' : 'categories';
    $slug = contenuti_slug(rawurldecode($t['slug'])) ?: contenuti_slug($t['nome']);
    if ($slug === '') { importa_wp_saltato($s, $tipo === 'tag' ? 'Tag' : 'Categoria', $t['nome'], 'nome vuoto'); return; }
    $st = db()->prepare('SELECT id FROM ' . tbl($tab) . ' WHERE slug = ?');
    $st->execute([$slug]);
    $id = (int)$st->fetchColumn();
    if ($id) {
        importa_wp_voce_aggiungi($lavoro, $genere, $t['id'], $t['percorso'], $id, $slug, 'esistente');
    } else {
        if ($tipo === 'tag') db()->prepare('INSERT INTO ' . tbl('tags') . ' (slug, name) VALUES (?, ?)')->execute([$slug, mb_substr(sito_senza_4byte($t['nome']), 0, 200)]);
        else db()->prepare('INSERT INTO ' . tbl('categories') . ' (slug, name, description) VALUES (?, ?, ?)')->execute([$slug, mb_substr(sito_senza_4byte($t['nome']), 0, 200), sito_senza_4byte($t['descrizione'])]);
        $id = (int)db()->lastInsertId();
        importa_wp_voce_aggiungi($lavoro, $genere, $t['id'], $t['percorso'], $id, $slug, 'nuova');
        importa_wp_nascondi($tipo === 'tag' ? 'tag' : 'categorie', $slug);
        importa_wp_entrato($s, $genere);
    }
    $nuovo = ($tipo === 'tag' ? '/tag/' : '/category/') . $slug . '/';
    if ($t['percorso'] !== '' && $t['conta'] > 0) importa_wp_redirect($s, $lavoro, $t['percorso'], $nuovo);
}

/** Lo slug di plumeCMS di un termine di WordPress (dalla tabella delle voci), oppure ''. */
function importa_wp_slug_termine(string $lavoro, string $genere, int $wpId): string {
    $v = importa_wp_voce($lavoro, $genere, $wpId);
    return $v ? (string)$v['plume_rif'] : '';
}

/** La data e l'ora di WordPress nel fuso del CMS (da date_gmt, se c'è). [AAAA-MM-GG, HH:MM] */
function importa_wp_quando(array $x): array {
    $g = (string)($x['date_gmt'] ?? '');
    try {
        $d = $g !== '' && !str_starts_with($g, '0000') ? (new DateTime($g, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get())) : new DateTime((string)($x['date'] ?? 'now'));
    } catch (Throwable $e) { $d = new DateTime(); }
    return [$d->format('Y-m-d'), $d->format('H:i')];
}

/** Un articolo o una pagina, come bozza in prova. */
function importa_wp_contenuto(array &$s, string $lavoro, string $passo, array $x): void {
    $genere = $passo === 'pagine' ? 'pagina' : 'articolo';
    $Cosa = $passo === 'pagine' ? 'Pagina' : 'Articolo';
    $wpId = (int)$x['id'];
    if (importa_wp_voce($lavoro, $genere, $wpId)) return;
    $iv = null;
    foreach ($s['indice'][$passo] as $v) if ($v['id'] === $wpId) { $iv = $v; break; }
    $iv = $iv ?? importa_wp_voce_indice($s, $x, $passo);
    $titolo = importa_wp_testo((string)($x['title']['raw'] ?? $x['title']['rendered'] ?? '')) ?: $iv['titolo'];
    $slug = contenuti_slug($iv['slug']) ?: contenuti_slug($titolo);
    $occupato = $slug === '' ? 'indirizzo vuoto' : importa_wp_slug_libero_ora($slug);
    if ($occupato !== '') { importa_wp_saltato($s, $Cosa, $titolo, $occupato); return; }
    $note = [];
    $html = importa_wp_html($s, $lavoro, (string)($x['content']['rendered'] ?? ''), (string)($x['content']['raw'] ?? ''), $note);
    [$data, $ora] = importa_wp_quando($x);
    $statoFinale = match ((string)$x['status']) { 'publish' => 'pubblicato', 'future' => 'programmato', default => 'bozza' };
    $immagine = '';
    if (!empty($x['featured_media'])) {
        $motivo = '';
        $immagine = (string)importa_wp_media_id($s, $lavoro, (int)$x['featured_media'], $motivo);
        if ($immagine === '' && $motivo !== '') $note['immagini_ko'][] = 'immagine in evidenza: ' . $motivo;
    }
    [$seoT, $seoD] = !empty($s['scelta']['seo']) ? importa_wp_seo($s, $x, $titolo) : ['', ''];
    $estratto = importa_wp_testo((string)($x['excerpt']['raw'] ?? ''));
    $d = [
        'title' => importa_wp_titolo_ok($passo === 'pagine' ? 'pages' : 'articles', $titolo), 'slug' => $slug, 'contenuto' => $html, 'excerpt' => $estratto,
        'stato' => 'bozza', 'data' => $data, 'ora' => $ora,
        'author' => (string)($s['indice']['autori'][(string)($x['author'] ?? 0)] ?? ''),
        'seo_title' => $seoT, 'seo_description' => $seoD, 'immagine' => $immagine, 'content_format' => 'html',
    ];
    if ($passo === 'articoli') {
        $d['categorie'] = array_values(array_filter(array_map(fn($c) => importa_wp_slug_termine($lavoro, 'categoria', (int)$c), (array)($x['categories'] ?? []))));
        $d['tags'] = implode(', ', array_filter(array_map(fn($t) => (string)($s['indice']['tag'][(string)$t]['nome'] ?? ''), !empty($s['scelta']['tag']) ? (array)($x['tags'] ?? []) : [])));
        $d['type'] = 'cluster';
    }
    try {
        $id = contenuti_salva($passo, 0, $d, (string)($_SESSION['admin_username'] ?? 'import'));
    } catch (Throwable $e) {
        importa_wp_saltato($s, $Cosa, $titolo, 'non salvato: ' . $e->getMessage());
        return;
    }
    importa_wp_voce_aggiungi($lavoro, $genere, $wpId, $iv['percorso'], $id, $slug, $statoFinale);
    importa_wp_entrato($s, $genere);
    // Redirect dai vecchi indirizzi, solo per quello che era pubblico.
    if ($statoFinale === 'pubblicato') {
        $nuovo = '/' . $slug . '/';
        if (!str_starts_with($iv['percorso'], '/?')) importa_wp_redirect($s, $lavoro, $iv['percorso'], $nuovo);
        importa_wp_redirect($s, $lavoro, '/?p=' . $wpId, $nuovo);
        if ($passo === 'pagine') importa_wp_redirect($s, $lavoro, '/?page_id=' . $wpId, $nuovo);
    }
    // Quello che non è entrato, per il riepilogo (in parole semplici).
    $av = [];
    if (!empty($iv['note']['builder'])) $av[] = 'fatto con ' . $iv['note']['builder'] . ': entra il testo, la grafica va rifatta nell\'editor';
    if (!empty($note['shortcode'])) $av[] = 'tolti gli shortcode ' . implode(', ', array_map(fn($n) => "[$n]", array_keys($note['shortcode'])));
    if (!empty($note['embed_link'])) $av[] = $note['embed_link'] . ' contenuti incorporati diventati link';
    if (!empty($note['immagini_ko'])) $av[] = 'immagini non scaricate: ' . implode('; ', array_slice($note['immagini_ko'], 0, 3));
    if (!empty($note['immagini_fuori'])) $av[] = $note['immagini_fuori'] . ' immagini su un altro sito, lasciate collegate';
    if (!empty($iv['note']['codice'])) $av[] = 'moduli o script nel testo, tolti per sicurezza';
    if (!empty($iv['note']['campi'])) $av[] = 'campi personalizzati non importati';
    if ($av && count($s['esito']['avvisi']['contenuti'] ?? []) < 1000) $s['esito']['avvisi']['contenuti'][] = ['cosa' => $Cosa, 'titolo' => $titolo, 'slug' => $slug, 'note' => $av];
}

// ------------------------------------------------------------
// Media
// ------------------------------------------------------------

/** L'indice dei media per id. */
function importa_wp_media_indice(array $s): array {
    static $m = null;
    if ($m === null) { $m = []; foreach ($s['indice']['media'] ?? [] as $x) $m[$x['id']] = $x; }
    return $m;
}

/** Il file di un id dei media di WordPress (immagine in evidenza): indirizzo nuovo o null. */
function importa_wp_media_id(array $s, string $lavoro, int $id, string &$motivo): ?string {
    $v = importa_wp_voce($lavoro, 'media', $id);
    if ($v) return (string)$v['plume_rif'];
    $m = importa_wp_media_indice($s)[$id] ?? null;
    if (!$m) {
        try { $r = importa_wp_json($s, '/wp/v2/media/' . $id, ['_fields' => 'id,source_url']); $m = ['url' => (string)($r['json']['source_url'] ?? '')]; }
        catch (ImportaWpErrore $e) { $motivo = $e->getMessage(); return null; }
    }
    return importa_wp_media($s, $lavoro, (string)$m['url'], $id, $motivo);
}

/**
 * Scarica un file del vecchio sito nella libreria: solo dallo stesso sito, al massimo IMPORTA_WP_MAX_FILE, tipo letto dal
 * contenuto (mai dal nome: un PHP chiamato foto.jpg non e un'immagine e non entra), immagini riscritte con GD, SVG ripulito.
 * Ritorna l'indirizzo nuovo, oppure null con il motivo.
 */
function importa_wp_media(array $s, string $lavoro, string $url, int $wpId, string &$motivo): ?string {
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    $url = (string)preg_replace('~[?#].*$~', '', $url);
    if ($url === '') { $motivo = 'indirizzo vuoto'; return null; }
    // Una misura ridotta (foto-1024x683.jpg) con l'id dell'originale: si prende l'originale.
    if ($wpId && ($m = importa_wp_media_indice($s)[$wpId] ?? null) && $m['url'] !== '') $url = $m['url'];
    $gia = importa_wp_voce($lavoro, 'media', 0, $url) ?? ($wpId ? importa_wp_voce($lavoro, 'media', $wpId) : null);
    if ($gia) return (string)$gia['plume_rif'];
    if (!importa_wp_del_sito($s, $url)) { $motivo = 'sta su un altro sito'; return null; }
    // Un file in http su un sito in https: si chiede in https (mai il contrario).
    if (str_starts_with($url, 'http://') && str_starts_with((string)$s['base'], 'https://')) $url = 'https://' . substr($url, 7);
    $tmp = tempnam(sys_get_temp_dir(), 'iwp');
    try {
        try { $r = importa_wp_get($s, $url, ['auth' => false, 'max' => IMPORTA_WP_MAX_FILE, 'timeout' => IMPORTA_WP_TIMEOUT_FILE, 'file' => $tmp]); }
        catch (ImportaWpErrore $e) { $motivo = $e->tipo === 'altro_host' ? 'sta su un altro indirizzo del sito' : $e->getMessage(); return null; }
        if ($r['codice'] !== 200) { $motivo = 'il vecchio sito ha risposto ' . $r['codice']; return null; }
        $ext = importa_wp_tipo_file($tmp);
        if ($ext === null) { $motivo = 'il contenuto non è un\'immagine né un file ammesso (PDF, MP4, MP3): non entra'; return null; }
        if ($ext === 'svg') {
            $pulito = sicurezza_svg_pulito((string)file_get_contents($tmp));
            if ($pulito === null) { $motivo = 'SVG non leggibile'; return null; }
            file_put_contents($tmp, $pulito);
        } elseif (in_array($ext, ['jpg', 'png', 'gif', 'webp', 'avif'], true)) {
            if (!sicurezza_immagine_ricodifica($tmp, $ext) && ($ext !== 'gif' || importa_wp_ha_codice($tmp))) { $motivo = 'immagine che non si riesce a riscrivere: non entra'; return null; }
        } elseif (importa_wp_ha_codice($tmp)) { $motivo = 'il file contiene codice: non entra'; return null; }
        $percorso = (string)parse_url($url, PHP_URL_PATH);
        $rel = preg_match('~/wp-content/uploads/(\d{4})/(\d{2})/~', $percorso, $m) ? $m[1] . '/' . $m[2] : date('Y/m');
        $dir = cms_uploads_dir() . '/' . $rel;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $motivo = 'la cartella uploads non è scrivibile'; return null; }
        $base = trim(substr((string)preg_replace('/[^a-z0-9-]+/', '-', strtolower(rawurldecode(pathinfo($percorso, PATHINFO_FILENAME)))), 0, 60), '-') ?: 'file';
        do { $nome = $base . '-' . importa_wp_lettere(6) . '.' . $ext; } while (file_exists("$dir/$nome"));
        if (!@rename($tmp, "$dir/$nome") && !@copy($tmp, "$dir/$nome")) { $motivo = 'il file non si è salvato'; return null; }
        @chmod("$dir/$nome", 0644);
        $nuovo = cms_uploads_url() . '/' . $rel . '/' . $nome;
        importa_wp_voce_aggiungi($lavoro, 'media', $wpId, $url, 0, $nuovo);
        return $nuovo;
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
}

/** Sei lettere minuscole a caso (niente cifre: un nome che finisce con -123x456 sembrerebbe una miniatura). */
function importa_wp_lettere(int $n): string {
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= chr(97 + random_int(0, 25));
    return $s;
}

/** Il tipo vero del file, dal contenuto: jpg, png, gif, webp, avif, svg, pdf, mp4, mp3 oppure null. */
function importa_wp_tipo_file(string $f): ?string {
    $tipi = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (defined('IMAGETYPE_AVIF')) $tipi[IMAGETYPE_AVIF] = 'avif';
    $info = @getimagesize($f);
    if ($info && isset($tipi[$info[2]])) return $tipi[$info[2]];
    $inizio = (string)file_get_contents($f, false, null, 0, 512);
    if (str_starts_with($inizio, '%PDF-')) return 'pdf';
    if (substr($inizio, 4, 4) === 'ftyp') return 'mp4';
    if (str_starts_with($inizio, 'ID3') || preg_match('/^\xFF[\xFB\xF3\xF2]/', $inizio)) return 'mp3';
    if (preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*<svg[\s>]/is', $inizio)) return 'svg';
    return null;
}

/** Il file contiene codice PHP o uno script (un file "doppio", immagine e programma insieme)? */
function importa_wp_ha_codice(string $f): bool {
    $h = fopen($f, 'rb');
    if (!$h) return true;
    $coda = '';
    while (!feof($h)) {
        $pezzo = $coda . (string)fread($h, 1048576);
        if (preg_match('/<\?(php|=)|<script\b/i', $pezzo)) { fclose($h); return true; }
        $coda = substr($pezzo, -16);
    }
    fclose($h);
    return false;
}

// ------------------------------------------------------------
// Menu
// ------------------------------------------------------------

/** Un menu di WordPress nei menu di plumeCMS, senza posizione (si sceglie alla conferma), con i link ai nuovi indirizzi. */
function importa_wp_menu(array &$s, string $lavoro, array $m): void {
    require_once __DIR__ . '/menu-nav.php';
    if (importa_wp_voce($lavoro, 'menu', $m['id'])) return;
    $nodi = [];
    foreach ($m['voci'] as $v) {
        $voce = ['id' => menu_id_voce(), 'etichetta' => $v['titolo'] ?: 'Voce', 'tipo' => 'link', 'rif' => '', 'url' => '', 'nuova' => $v['nuova'], 'classi' => '', 'figli' => []];
        $gen = match (true) { $v['oggetto'] === 'page' => 'pagina', $v['oggetto'] === 'post' => 'articolo', $v['oggetto'] === 'category' => 'categoria', $v['oggetto'] === 'post_tag' => 'tag', default => '' };
        $dentro = $gen !== '' && $v['oggetto_id'] ? importa_wp_voce($lavoro, $gen, $v['oggetto_id']) : null;
        if ($dentro && in_array($gen, ['pagina', 'articolo', 'categoria'], true)) { $voce['tipo'] = $gen; $voce['rif'] = (string)$dentro['plume_rif']; $voce['url'] = $gen === 'categoria' ? '/category/' . $dentro['plume_rif'] . '/' : '/' . $dentro['plume_rif'] . '/'; }
        elseif ($dentro && $gen === 'tag') $voce['url'] = '/tag/' . $dentro['plume_rif'] . '/';
        elseif (importa_wp_del_sito($s, $v['url'])) $voce['url'] = importa_wp_link_nuovo($s, $v['url']) ?? $v['url'];
        else $voce['url'] = $v['url'];
        if ($voce['url'] === '' || !preg_match('~^(https?://|/|#|mailto:|tel:)~i', $voce['url'])) $voce['url'] = '#';
        $nodi[$v['id']] = ['voce' => $voce, 'padre' => $v['padre']];
    }
    // L'albero: al massimo tre livelli (quelli più profondi salgono al terzo).
    $livello = function ($id) use (&$nodi, &$livello) { $p = $nodi[$id]['padre']; return $p && isset($nodi[$p]) ? 1 + $livello($p) : 0; };
    foreach (array_keys($nodi) as $id) while ($livello($id) > 2) $nodi[$id]['padre'] = $nodi[$nodi[$id]['padre']]['padre'];
    $figli = [];
    foreach ($nodi as $id => $n) $figli[isset($nodi[$n['padre']]) ? $n['padre'] : 0][] = $id;
    $costruisci = function ($padre) use (&$costruisci, &$figli, &$nodi) {
        $out = [];
        foreach ($figli[$padre] ?? [] as $id) { $v = $nodi[$id]['voce']; $v['figli'] = $costruisci($id); $out[] = $v; }
        return $out;
    };
    $nome = mb_substr($m['nome'] ?: 'Menu di WordPress', 0, 80);
    $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('menu') . ' WHERE nome = ?');
    $st->execute([$nome]);
    if ((int)$st->fetchColumn()) $nome .= ' (da WordPress)';
    $id = menu_salva(0, $nome, '', $costruisci(0));
    importa_wp_voce_aggiungi($lavoro, 'menu', $m['id'], implode(', ', $m['posizioni']), $id, $nome);
    importa_wp_entrato($s, 'menu');
}

// ------------------------------------------------------------
// Conferma e annulla
// ------------------------------------------------------------

/** Quello che è entrato, contato dalla tabella (la verita, anche dopo una ripresa). */
function importa_wp_conteggi(string $lavoro): array {
    $st = db()->prepare('SELECT genere, stato_finale, COUNT(*) AS n FROM ' . tbl('importa_wp_voci') . ' WHERE lavoro = ? GROUP BY genere, stato_finale');
    $st->execute([$lavoro]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        if (in_array($r['genere'], ['categoria', 'tag'], true) && $r['stato_finale'] === 'esistente') continue;
        $out[$r['genere']]['tot'] = ($out[$r['genere']]['tot'] ?? 0) + (int)$r['n'];
        if ($r['stato_finale'] !== '') $out[$r['genere']][$r['stato_finale']] = (int)$r['n'];
    }
    return $out;
}

/**
 * Conferma: tutto diventa definitivo. $menuPrincipale: l'id di plumeCMS di un menu importato da mettere come menu principale
 * (0: i menu restano senza posizione).
 */
function importa_wp_conferma(array &$s, int $menuPrincipale = 0): void {
    $lavoro = (string)$s['lavoro'];
    foreach (importa_wp_voci($lavoro) as $v) {
        if (in_array($v['genere'], ['articolo', 'pagina'], true) && in_array($v['stato_finale'], ['pubblicato', 'programmato'], true)) {
            $tab = $v['genere'] === 'pagina' ? 'pages' : 'articles';
            db()->prepare('UPDATE ' . tbl($tab) . " SET status = 'published' WHERE id = ? AND status = 'draft'")->execute([(int)$v['plume_id']]);
        } elseif ($v['genere'] === 'redirect') {
            db()->prepare('UPDATE ' . tbl('redirect') . ' SET attivo = 1 WHERE id = ?')->execute([(int)$v['plume_id']]);
        }
    }
    if ($menuPrincipale) {
        // Le voci del menu importato vanno nel menu principale del sito (quello in posizione "principale", che l'header mostra
        // per id o per posizione). Il menu di prima resta nelle versioni (Editor > Versioni) e si ripristina da li.
        require_once __DIR__ . '/menu-nav.php';
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('importa_wp_voci') . " WHERE lavoro = ? AND genere = 'menu' AND plume_id = ?");
        $st->execute([$lavoro, $menuPrincipale]);
        $nuovo = menu_leggi($menuPrincipale);
        $principale = menu_per_posizione('principale');
        if ((int)$st->fetchColumn() && $nuovo) {
            if ($principale && (int)$principale['id'] !== $menuPrincipale) {
                menu_versione((int)$principale['id'], ['nome' => $principale['nome'], 'posizione' => $principale['posizione'], 'voci' => $principale['voci']], 'prima dell\'import da WordPress');
                menu_salva((int)$principale['id'], $principale['nome'], 'principale', $nuovo['voci']);
            } else {
                menu_salva($menuPrincipale, $nuovo['nome'], 'principale', $nuovo['voci']);
            }
            $s['esito']['menu_principale'] = $menuPrincipale;
        }
    }
    save_setting('importa_wp_nascosti', '');
    // I motori di ricerca sanno degli indirizzi nuovi (IndexNow, se acceso nel pannello SEO).
    foreach (importa_wp_voci($lavoro) as $v) if (in_array($v['genere'], ['articolo', 'pagina'], true) && $v['stato_finale'] === 'pubblicato') contenuti_avvisa_motori($v['genere'] === 'pagina' ? 'pagine' : 'articoli', (int)$v['plume_id']);
    importa_wp_dimentica_password($s);
    $s['fase'] = 'confermato';
    $s['esito']['confermato'] = time();
    if (function_exists('sicurezza_log')) sicurezza_log('importa_wp', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'import da ' . $s['base'] . ' confermato');
}

/** Annulla: via tutto quello che è entrato con questo lavoro. Ritorna quante cose ha tolto. */
function importa_wp_annulla(array &$s): int {
    $lavoro = (string)($s['lavoro'] ?? '');
    $tolti = 0;
    if ($lavoro !== '') {
        importa_wp_installa();
        foreach (array_reverse(importa_wp_voci($lavoro)) as $v) {
            $id = (int)$v['plume_id'];
            switch ($v['genere']) {
                case 'articolo':
                case 'pagina':
                    $tab = $v['genere'] === 'pagina' ? 'pages' : 'articles';
                    db()->prepare('DELETE FROM ' . tbl($tab) . ' WHERE id = ?')->execute([$id]);
                    db()->prepare('DELETE FROM ' . tbl('revisioni') . ' WHERE tipo = ? AND contenuto_id = ?')->execute([$v['genere'] === 'pagina' ? 'pagine' : 'articoli', $id]);
                    break;
                case 'categoria':
                case 'tag':
                    if ($v['stato_finale'] === 'nuova') db()->prepare('DELETE FROM ' . tbl($v['genere'] === 'tag' ? 'tags' : 'categories') . ' WHERE id = ?')->execute([$id]);
                    break;
                case 'redirect':
                    db()->prepare('DELETE FROM ' . tbl('redirect') . ' WHERE id = ? AND origine = \'wordpress\'')->execute([$id]);
                    break;
                case 'menu':
                    db()->prepare('DELETE FROM ' . tbl('menu') . ' WHERE id = ?')->execute([$id]);
                    break;
                case 'media':
                    $u = (string)$v['plume_rif'];
                    $radice = cms_uploads_url();
                    if (str_starts_with($u, $radice . '/') && !str_contains($u, '..')) { $f = cms_uploads_dir() . substr($u, strlen($radice)); if (is_file($f)) @unlink($f); }
                    break;
            }
            $tolti++;
        }
        db()->prepare('DELETE FROM ' . tbl('importa_wp_voci') . ' WHERE lavoro = ?')->execute([$lavoro]);
    }
    save_setting('importa_wp_nascosti', '');
    importa_wp_dimentica_password($s);
    $s['fase'] = 'annullato';
    $s['esito']['annullato'] = time();
    $s['esito']['tolti'] = $tolti;
    if (function_exists('sicurezza_log')) sicurezza_log('importa_wp', (string)($_SESSION['admin_username'] ?? ''), (int)($_SESSION['admin_user_id'] ?? 0), 'import da ' . ($s['base'] ?? '') . " annullato ($tolti voci tolte)");
    return $tolti;
}
