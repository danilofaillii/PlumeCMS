<?php
/**
 * I contenuti pubblici del sito, come li vede chi visita: articoli, pagine, glossario, i tipi dei moduli,
 * categorie, tag, redirect. Usati dal sito nuovo (sito/).
 *
 * Ogni contenuto è un array con la stessa forma per tutti i tipi. L'HTML si calcola solo quando
 * serve (sito_html_contenuto), perché l'elenco completo si costruisce a ogni richiesta.
 * "Uscito" vuol dire: stato pubblicato e data e ora già passate. Un articolo programmato
 * compare da solo al minuto giusto, senza cron.
 */

require_once __DIR__ . '/sito.php';

function sito_iso(string $d, string $t = '00:00:00'): string {
    return substr($d, 0, 10) . 'T' . substr($t ?: '00:00:00', 0, 8);
}

function sito_faq_da_json($j): array {
    $v = json_decode((string)$j, true);
    return is_array($v) ? array_values(array_filter($v, fn($f) => !empty($f['domanda']) && !empty($f['risposta']))) : [];
}

function sito_immagine(string $img): ?array {
    if ($img === '') return null;
    if (!preg_match('~^https?://~', $img)) $img = SITO_URL . cms_base() . '/' . ltrim($img, '/');
    return ['url' => $img, 'alt' => ''];
}

/** Redirect attivi, una volta per richiesta. */
function sito_redirect(): array {
    static $r = null;
    if ($r !== null) return $r;
    $r = db()->query('SELECT da, a, codice, confronto, origine FROM ' . tbl('redirect') . ' WHERE attivo = 1 ORDER BY id')->fetchAll();
    foreach ($r as &$x) $x['codice'] = (int)$x['codice'];
    return $r;
}

function sito_uscito(array $riga, array $stati = ['published', 'scheduled']): bool {
    if (!empty($riga['cestino'])) return false;
    if (sito_anteprima()) return true;
    return in_array($riga['status'], $stati, true) && strtotime($riga['publish_date'] . ' ' . ($riga['publish_time'] ?? '00:00:00')) <= time();
}

/** Anteprima dal pannello (?preview=1 con la sessione dell'admin): bozze e programmati si vedono come fossero usciti. */
function sito_anteprima(): bool {
    return defined('SITO_ANTEPRIMA') && SITO_ANTEPRIMA;
}

/** Le colonne di una tabella tranne quelle pesanti: l'elenco completo si legge a ogni pagina. */
function sito_colonne_leggere(string $tab, array $pesanti): string {
    $c = array_diff(db()->query('SHOW COLUMNS FROM ' . tbl($tab))->fetchAll(PDO::FETCH_COLUMN), $pesanti);
    return implode(', ', array_map(fn($x) => "`$x`", $c));
}

/** Il campo pesante di una riga, letto solo per la pagina che lo mostra. */
function sito_campo(string $tab, string $campo, int $id): string {
    $st = db()->prepare("SELECT `$campo` FROM " . tbl($tab) . ' WHERE id = :id');
    $st->execute([':id' => $id]);
    return (string)$st->fetchColumn();
}

/**
 * Tutti i contenuti pubblici, senza HTML. Una volta per richiesta.
 * Comprende gli articoli assorbiti dai pillar (rediretto = true): servono agli archivi per data
 * e a /?p=ID, gli indirizzi di prima.
 */
function sito_contenuti(): array {
    static $tutti = null;
    if ($tutti !== null) return $tutti;
    sito_schema_aggiorna();
    $daRedirect = array_flip(array_column(sito_redirect(), 'da'));
    $tutti = [];

    $righe = db()->query('SELECT ' . sito_colonne_leggere('articles', ['content', 'struttura', 'struttura_bozza']) . ", ((struttura IS NOT NULL AND struttura <> '')" . (sito_anteprima() ? " OR (struttura_bozza IS NOT NULL AND struttura_bozza <> '')" : '') . ") AS con_editor FROM " . tbl('articles') . '
        WHERE cestino IS NULL AND (' . (sito_anteprima() ? '1' : "(status IN ('published','scheduled') AND TIMESTAMP(publish_date, publish_time) <= NOW()) OR wp_id IS NOT NULL") . ')')->fetchAll();
    foreach ($righe as $a) {
        $percorso = '/' . $a['slug'] . '/';
        $rediretto = isset($daRedirect[$percorso]);
        if (!sito_uscito($a) && !$rediretto) continue;
        $tutti[] = [
            'tipo' => 'post', 'fonte' => 'articles', 'riga' => $a,
            'id' => (int)($a['wp_id'] ?: 2000000 + $a['id']),
            'slug' => $a['slug'], 'percorso' => $percorso, 'titolo' => $a['title'],
            'data' => sito_iso($a['publish_date'], $a['publish_time']),
            'modificato' => str_replace(' ', 'T', (string)$a['updated_at']),
            'estratto' => strip_tags((string)($a['excerpt'] ?? '')),
            'categorie' => array_values(sito_lista($a['categorie'] ?? '') ?: array_filter([$a['category']])),
            'tag' => array_values(sito_lista($a['tag_slugs'] ?? '') ?: array_map('sito_slug', sito_lista($a['tags'] ?? ''))),
            'immagine' => sito_immagine($a['immagine_url'] ?: (string)$a['featured_image']),
            'seo' => [
                'titolo' => (string)$a['seo_title'],
                'descrizione' => (string)($a['seo_description'] ?: $a['meta_description']),
                'keyword' => (string)$a['keyword_target'], 'canonical' => '',
                'robots' => sito_robots_articolo($a), 'immagine_social' => '',
            ],
            'tldr' => (string)$a['tldr'], 'faq' => sito_faq_da_json($a['faq']),
            // Pillar: solo il tipo. La lunghezza da sola non basta: anche cluster vecchi superano le 3500 parole.
            'pillar' => $a['type'] === 'pillar',
            'servizio' => [], 'rediretto' => $rediretto, 'editor' => (bool)$a['con_editor'],
        ];
    }

    // I contenuti dei moduli (per esempio un catalogo), nello stesso formato.
    $tutti = cms_filtro('sito_contenuti', $tutti, $daRedirect);

    $home = (string)get_setting('pagina_home', '');
    // Una pagina esce con il testo della scheda oppure con la struttura dell'editor (che vince).
    foreach (db()->query('SELECT ' . sito_colonne_leggere('pages', ['html', 'content', 'struttura', 'struttura_bozza']) . ", ((struttura IS NOT NULL AND struttura <> '')" . (sito_anteprima() ? " OR (struttura_bozza IS NOT NULL AND struttura_bozza <> '')" : '') . ") AS con_editor FROM " . tbl('pages')
        . ' WHERE cestino IS NULL AND ' . (sito_anteprima() ? '1' : "status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())")
        . " AND ((html IS NOT NULL AND html <> '') OR (struttura IS NOT NULL AND struttura <> ''))")->fetchAll() as $p) {
        $percorso = $p['slug'] === $home ? '/' : '/' . $p['slug'] . '/';
        $servizio = json_decode((string)$p['servizio'], true);
        $tutti[] = [
            'tipo' => 'page', 'fonte' => 'pages', 'riga' => $p,
            'id' => (int)($p['wp_id'] ?: 3000000 + $p['id']),
            'slug' => $p['slug'], 'percorso' => $percorso, 'titolo' => $p['title'],
            'data' => str_replace(' ', 'T', (string)($p['publish_at'] ?: $p['created_at'])),
            'modificato' => str_replace(' ', 'T', (string)$p['updated_at']),
            'estratto' => '', 'categorie' => [], 'tag' => [],
            'immagine' => sito_immagine((string)$p['immagine_url']),
            'seo' => [
                'titolo' => (string)$p['seo_title'], 'descrizione' => (string)($p['seo_description'] ?: $p['meta_description']),
                'keyword' => '', 'canonical' => '', 'robots' => $p['robots'] === 'noindex' ? 'noindex' : 'index', 'immagine_social' => '',
            ],
            'tldr' => (string)$p['tldr'], 'faq' => sito_faq_da_json($p['faq']), 'pillar' => false,
            'servizio' => is_array($servizio) ? $servizio : [], 'rediretto' => isset($daRedirect[$percorso]),
            'editor' => (bool)$p['con_editor'],
        ];
    }

    foreach (db()->query('SELECT ' . sito_colonne_leggere('glossario', ['html']) . ' FROM ' . tbl('glossario') . " WHERE status = 'published'")->fetchAll() as $g) {
        $tutti[] = [
            'tipo' => 'glossario', 'fonte' => 'glossario', 'riga' => $g,
            'id' => (int)($g['wp_id'] ?: 4000000 + $g['id']),
            'slug' => $g['slug'], 'percorso' => '/glossario/' . $g['slug'] . '/', 'titolo' => $g['title'],
            'data' => str_replace(' ', 'T', (string)($g['publish_at'] ?: $g['updated_at'])),
            'modificato' => str_replace(' ', 'T', (string)$g['updated_at']),
            'estratto' => '', 'categorie' => [], 'tag' => [], 'immagine' => null,
            'seo' => ['titolo' => '', 'descrizione' => '', 'keyword' => '', 'canonical' => '', 'robots' => 'noindex', 'immagine_social' => ''],
            'tldr' => (string)$g['definizione'], 'faq' => [], 'pillar' => false, 'servizio' => [], 'rediretto' => false,
        ];
    }

    // Gli articoli più recenti prima.
    usort($tutti, fn($a, $b) => strcmp($b['data'], $a['data']));
    return $tutti;
}

/** HTML del contenuto, calcolato una volta sola. */
function sito_html_contenuto(array &$c): string {
    if (isset($c['html'])) return $c['html'];
    $r = $c['riga'];
    if ($c['rediretto']) return $c['html'] = '';
    $id = (int)$r['id'];
    switch ($c['fonte']) {
        case 'articles':
            if (!empty($c['editor'])) return $c['html'] = sito_pagina_editor($c)[0];
            return $c['html'] = sito_html_articolo($r + ['content' => sito_campo('articles', 'content', $id)]);
        case 'pages':
            if (!empty($c['editor'])) return $c['html'] = sito_pagina_editor($c)[0];
            return $c['html'] = sito_campo('pages', 'html', $id);
        default:
            // Un tipo di un modulo (per esempio un catalogo) disegna il suo HTML; altrimenti la colonna html.
            $html = cms_filtro('sito_html_contenuto', null, $c);
            if (is_string($html)) return $c['html'] = $html;
            if (!empty($c['editor'])) return $c['html'] = sito_pagina_editor($c)[0];
            return $c['html'] = sito_campo($c['fonte'], 'html', $id);
    }
}

/** Una pagina fatta con l'editor: [html, css, faq]. Calcolata una volta per richiesta. */
function sito_pagina_editor(array &$c): array {
    if (isset($c['editor_disegno'])) return $c['editor_disegno'];
    require_once __DIR__ . '/editor.php';
    $albero = editor_decodifica(sito_campo($c['fonte'], 'struttura', (int)$c['riga']['id']));
    // In anteprima dal pannello, un contenuto non ancora pubblicato dall'editor mostra la sua bozza.
    if (!$albero && sito_anteprima()) $albero = editor_decodifica(sito_campo($c['fonte'], 'struttura_bozza', (int)$c['riga']['id']));
    $albero = $albero ?? ['elementi' => []];
    [$html, $css] = editor_disegna($albero, $c['percorso']);
    return $c['editor_disegno'] = [$html, $css, editor_faq($albero)];
}

/** Categorie con robots: quello salvato, oppure noindex se vuota o se un modulo la nasconde. */
function sito_categorie(): array {
    static $c = null;
    if ($c !== null) return $c;
    $conta = [];
    foreach (sito_contenuti() as $x) if ($x['tipo'] === 'post' && !$x['rediretto']) foreach ($x['categorie'] as $s) $conta[$s] = ($conta[$s] ?? 0) + 1;
    $c = [];
    foreach (db()->query('SELECT * FROM ' . tbl('categories') . ' ORDER BY sort_order, name')->fetchAll() as $r) {
        $n = $conta[$r['slug']] ?? 0;
        $robots = in_array($r['robots'], ['index', 'noindex'], true) ? $r['robots']
            : (($n === 0 || in_array($r['slug'], sito_categorie_noindex(), true)) ? 'noindex' : 'index');
        $c[$r['slug']] = ['id' => (int)$r['id'], 'slug' => $r['slug'], 'name' => $r['name'], 'description' => (string)$r['description'], 'count' => $n, 'robots' => $robots];
    }
    return $c;
}

function sito_tag(): array {
    static $t = null;
    if ($t !== null) return $t;
    $conta = [];
    foreach (sito_contenuti() as $x) if ($x['tipo'] === 'post' && !$x['rediretto']) foreach ($x['tag'] as $s) $conta[$s] = ($conta[$s] ?? 0) + 1;
    $t = [];
    foreach (db()->query('SELECT * FROM ' . tbl('tags') . ' ORDER BY name')->fetchAll() as $r) {
        $t[$r['slug']] = ['id' => (int)$r['id'], 'slug' => $r['slug'], 'name' => $r['name'], 'count' => $conta[$r['slug']] ?? 0, 'robots' => 'noindex'];
    }
    return $t;
}
