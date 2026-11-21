<?php
/**
 * Il corpo di un articolo scritto in Markdown: testo convertito, infografiche e blocco "Leggi anche" (il pillar
 * dell'articolo, poi altri della stessa categoria). Gli articoli in HTML passano così come sono (sito_html_articolo).
 */

/** Il corpo finito di un articolo in Markdown. */
function corpo_articolo_markdown(array $article): string {
    if (!function_exists('process_infographic_shortcodes')) require_once __DIR__ . '/infographic.php';
    $html = markdown_to_html($article['content'] ?? '');
    if (strpos($html, '[IG:') !== false) {
        $html = process_infographic_shortcodes($html);
    } elseif (!empty($article['infographic_type']) && $article['infographic_type'] !== 'none') {
        $inf = render_infographic($article);
        if ($inf) $html .= "\n\n" . $inf;
    }
    $html .= corpo_leggi_anche($article);
    return corpo_compatta_blocchi($html);
}

/** Un articolo è online quando è pubblicato ed è arrivato alla sua data e ora. */
function sito_condizione_online(): string {
    return 'AND cestino IS NULL AND TIMESTAMP(publish_date, publish_time) <= NOW()';
}

function corpo_leggi_anche(array $article): string {
    try {
        $slug = $article['slug'] ?? '';
        $cat  = $article['category'] ?? '';
        if (!$slug) return '';

        $cards = [];
        $exclude = [$slug];

        // 1. Cluster -> link al proprio pillar (evidenziato)
        if (($article['type'] ?? '') === 'cluster' && !empty($article['pillar_ref'])) {
            $st = db()->prepare('SELECT slug, title FROM ' . tbl('articles') . '
                WHERE slug = :ref AND status = "published" AND publish_date <= CURDATE() ' . sito_condizione_online() . ' LIMIT 1');
            $st->execute([':ref' => $article['pillar_ref']]);
            if ($p = $st->fetch()) {
                $cards[] = ['slug' => $p['slug'], 'title' => $p['title'], 'pillar' => true];
                $exclude[] = $p['slug'];
            }
        }

        // 2. Pillar -> link ai propri cluster
        if (($article['type'] ?? '') === 'pillar') {
            $st = db()->prepare('SELECT slug, title FROM ' . tbl('articles') . '
                WHERE pillar_ref = :cur AND status = "published" AND publish_date <= CURDATE() ' . sito_condizione_online() . '
                ORDER BY MD5(CONCAT(slug, :seed)) LIMIT 3');
            $st->execute([':cur' => $slug, ':seed' => $slug]);
            foreach ($st->fetchAll() as $r) {
                $cards[] = ['slug' => $r['slug'], 'title' => $r['title'], 'pillar' => false];
                $exclude[] = $r['slug'];
            }
        }

        // 3. Riempi con correlati della stessa categoria fino a 3 (+ eventuale pillar)
        $want = ($cards && !empty($cards[0]['pillar'])) ? 4 : 3;
        if (count($cards) < $want && $cat) {
            $ph = implode(',', array_fill(0, count($exclude), '?'));
            $st = db()->prepare('SELECT slug, title FROM ' . tbl('articles') . '
                WHERE category = ? AND slug NOT IN (' . $ph . ')
                  AND status = "published" AND publish_date <= CURDATE() ' . sito_condizione_online() . '
                ORDER BY MD5(CONCAT(slug, ?)) LIMIT ' . ($want - count($cards)));
            $st->execute(array_merge([$cat], $exclude, [$slug]));
            foreach ($st->fetchAll() as $r) {
                $cards[] = ['slug' => $r['slug'], 'title' => $r['title'], 'pillar' => false];
                $exclude[] = $r['slug'];
            }
        }

        // 4. Categoria piccola: riempi da tutto il catalogo
        if (count($cards) < 3) {
            $ph = implode(',', array_fill(0, count($exclude), '?'));
            $st = db()->prepare('SELECT slug, title FROM ' . tbl('articles') . '
                WHERE slug NOT IN (' . $ph . ')
                  AND status = "published" AND publish_date <= CURDATE() ' . sito_condizione_online() . '
                ORDER BY MD5(CONCAT(slug, ?)) LIMIT ' . (3 - count($cards)));
            $st->execute(array_merge($exclude, [$slug]));
            foreach ($st->fetchAll() as $r) {
                $cards[] = ['slug' => $r['slug'], 'title' => $r['title'], 'pillar' => false];
            }
        }

        if (!$cards) return '';

        $html = '<div class="ed-leggi-anche-box" style="margin:2.5rem 0 0.5rem">'
              . '<hr style="border:none;border-top:1px solid var(--bordo,#e5e5ef);margin:0 0 1.3rem">'
              . '<p style="font-size:1.15rem;font-weight:800;margin:0 0 1rem;color:var(--testo,inherit)">Leggi anche</p>'
              . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.8rem">';
        foreach ($cards as $c) {
            $border = !empty($c['pillar']) ? '2px solid var(--viola,#5b45e0)' : '1px solid var(--bordo,#e5e5ef)';
            $badge  = !empty($c['pillar']) ? '<span style="display:block;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--viola,#5b45e0);font-weight:700;margin-bottom:0.3rem">La guida completa</span>' : '';
            $html .= '<a href="/' . htmlspecialchars($c['slug']) . '/" style="display:block;background:var(--bg-2,#fff);border:' . $border . ';border-radius:12px;padding:1rem 1.2rem;font-weight:600;font-size:0.92rem;line-height:1.4;text-decoration:none;color:var(--testo,inherit)">'
                   . $badge . htmlspecialchars($c['title']) . ' &rarr;</a>';
        }
        $html .= '</div></div>';
        return "\n\n" . $html;
    } catch (Exception $e) {
        return '';
    }
}

function corpo_compatta_blocchi(string $html): string {
    $lines = explode("\n", $html);
    $result = [];
    $depth = 0;
    $buffer = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($depth === 0 && preg_match('/^<div\s+style=/', $trimmed)) {
            $depth = preg_match_all('/<div[\s>]/', $trimmed) - substr_count($trimmed, '</div>');
            $buffer = $trimmed;
            if ($depth <= 0) { $result[] = $buffer; $buffer = ''; $depth = 0; }
        } elseif ($depth > 0) {
            $buffer .= $trimmed;
            $depth += preg_match_all('/<div[\s>]/', $trimmed) - substr_count($trimmed, '</div>');
            if ($depth <= 0) { $result[] = $buffer; $buffer = ''; $depth = 0; }
        } else {
            $result[] = $line;
        }
    }
    if ($buffer) $result[] = $buffer;
    return implode("\n", $result);
}
