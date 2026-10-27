<?php
/**
 * Il corpo di un articolo scritto in Markdown: testo convertito, infografiche, riquadro del test,
 * blocco "Leggi anche" e invito finale. Gli articoli in HTML passano così come sono (sito_html_articolo).
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
    $html .= corpo_riquadro_test($article);
    $html .= corpo_leggi_anche($article);
    if (!empty($article['cta'])) {
        $html .= "\n\n" . corpo_invito($article['cta']);
    }
    return corpo_compatta_blocchi($html);
}

/** Un articolo è online quando è pubblicato ed è arrivato alla sua data e ora. */
function sito_condizione_online(): string {
    return 'AND cestino IS NULL AND TIMESTAMP(publish_date, publish_time) <= NOW()';
}

/**
 * Box "Fai il test burnout" per gli articoli delle categorie stress/burnout.
 * Obiettivo: portare il test da 60 a 300+ risposte per il report con dati
 * proprietari (Cantiere 2a).
 */
function corpo_riquadro_test(array $article): string {
    $cats = ['burnout', 'stress-da-lavoro', 'stress-lavoro'];
    if (!in_array($article['category'] ?? '', $cats, true)) return '';
    return '<div class="df-test-box" style="background:#fef9f0;border:2px solid #e8a830;border-radius:16px;padding:1.8rem 2rem;text-align:center;margin:2.5rem 0 0.5rem">'
        . '<p style="font-size:1.15rem;font-weight:800;margin:0 0 0.4rem;color:#2d1b0e">Quanto sei vicino al burnout, in numeri?</p>'
        . '<p style="font-size:0.92rem;color:#6b4c35;margin:0 auto 1.1rem;max-width:480px">3 minuti, 12 domande, risultato immediato. Il test che centinaia di professionisti stanno usando per dare un nome a quello che sentono.</p>'
        . '<a href="' . SITO_URL . '/test-burnout/" style="display:inline-block;background:#e8a830;color:#2d1b0e;font-weight:700;font-size:0.95rem;padding:0.75rem 1.8rem;border-radius:10px;text-decoration:none">Fai il test adesso</a>'
        . '</div>';
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

        $html = '<div class="df-leggi-anche" style="margin:2.5rem 0 0.5rem">'
              . '<hr style="border:none;border-top:1px solid #e8ddd0;margin:0 0 1.3rem">'
              . '<p style="font-size:1.15rem;font-weight:800;margin:0 0 1rem;color:#2d1b0e">Leggi anche</p>'
              . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.8rem">';
        foreach ($cards as $c) {
            $border = !empty($c['pillar']) ? '2px solid #e8553e' : '1px solid #e8ddd0';
            $badge  = !empty($c['pillar']) ? '<span style="display:block;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:#e8553e;font-weight:700;margin-bottom:0.3rem">La guida completa</span>' : '';
            $html .= '<a href="/' . htmlspecialchars($c['slug']) . '/" style="display:block;background:#ffffff;border:' . $border . ';border-radius:12px;padding:1rem 1.2rem;font-weight:600;font-size:0.92rem;line-height:1.4;text-decoration:none;color:#2d1b0e">'
                   . $badge . htmlspecialchars($c['title']) . ' &rarr;</a>';
        }
        $html .= '</div></div>';
        return "\n\n" . $html;
    } catch (Exception $e) {
        return '';
    }
}

/**
 * CTA come box cliccabile (link alla radice del sito, non /new/).
 * Prima la CTA era testo puro non linkato.
 */
function corpo_invito(string $cta): string {
    $map = [
        'laser coaching 15 min' => [
            'label' => 'Fai chiarezza in 15 minuti',
            'desc'  => 'Prenota una Laser Coaching gratuita: mi racconti una situazione specifica e usciamo con almeno una cosa concreta da fare.',
            'btn'   => 'Prenota i tuoi 15 minuti gratuiti',
            'href'  => SITO_URL . '/laser-coaching/',
        ],
        'percorso D.O.C.' => [
            'label' => 'Cambia il modo in cui stai al lavoro',
            'desc'  => 'Il Percorso D.O.C. e un percorso strutturato per chi vive dinamiche tossiche in ufficio e vuole uscirne con metodo.',
            'btn'   => 'Scopri il Percorso D.O.C.',
            'href'  => SITO_URL . '/percorso-doc/',
        ],
        'ebook gratuito' => [
            'label' => 'Scarica l\'ebook gratuito',
            'desc'  => '"Fuori dagli Schemi" - confini, presenza, dialogo e responsabilita nelle relazioni di lavoro e private.',
            'btn'   => 'Scarica "Fuori dagli Schemi"',
            'href'  => SITO_URL . '/ebook/',
        ],
        'workshop tensioni' => [
            'label' => 'Lavora su conflitti, fiducia e ruoli',
            'desc'  => 'Un percorso pratico per trasformare le tensioni del tuo team in collaborazione.',
            'btn'   => 'Scopri il Percorso D.O.C.',
            'href'  => SITO_URL . '/percorso-doc/',
        ],
        'workshop comunicazione' => [
            'label' => 'Lavora su comunicazione e dinamiche di gruppo',
            'desc'  => 'Un percorso pratico su comunicazione e dinamiche di gruppo.',
            'btn'   => 'Scopri il Percorso D.O.C.',
            'href'  => SITO_URL . '/percorso-doc/',
        ],
    ];
    $c = $map[$cta] ?? $map['laser coaching 15 min'];
    return '<div class="article-cta-box" style="background:linear-gradient(135deg,#2d5a5f 0%,#1a3a3e 100%);border-radius:16px;padding:2rem;text-align:center;margin:2.5rem 0 1rem;color:#fff">'
        . '<p style="font-size:1.25rem;font-weight:800;margin:0 0 0.5rem;color:#fff">' . htmlspecialchars($c['label']) . '</p>'
        . '<p style="font-size:0.95rem;opacity:0.85;margin:0 auto 1.3rem;max-width:520px;color:#fff">' . htmlspecialchars($c['desc']) . '</p>'
        . '<a href="' . htmlspecialchars($c['href']) . '" style="display:inline-block;background:#e8553e;color:#ffffff;font-weight:700;font-size:1rem;padding:0.85rem 2rem;border-radius:10px;text-decoration:none">' . htmlspecialchars($c['btn']) . '</a>'
        . '</div>';
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
