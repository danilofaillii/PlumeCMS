<?php
/**
 * Rendering delle infografiche a template.
 * L'articolo ha: infographic_type + infographic_title + infographic_data (JSON)
 */

function render_infographic(array $article): string {
    $type = $article['infographic_type'] ?? 'none';
    if ($type === 'none' || empty($article['infographic_data'])) return '';

    $data = json_decode($article['infographic_data'], true);
    if (!is_array($data)) return '';

    $title = $article['infographic_title'] ?? '';

    switch ($type) {
        case 'steps':     return infographic_steps($title, $data);
        case 'checklist': return infographic_checklist($title, $data);
        case 'compare':   return infographic_compare($title, $data);
        case 'stats':     return infographic_stats($title, $data);
        case 'quote':     return infographic_quote($title, $data);
        case 'flow':      return infographic_flow($title, $data);
    }
    return '';
}

function _igTitle(string $t): string {
    if ($t === '') return '';
    return '<h3 class="ig-title">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</h3>';
}

/**
 * STEPS: passaggi numerati (1→2→3)
 * data: { items: [{label, text}] }
 */
function infographic_steps(string $title, array $data): string {
    if (empty($data['items'])) return '';
    $out = '<figure class="ig ig-steps" role="figure" aria-label="Infografica: ' . htmlspecialchars($title) . '">';
    $out .= _igTitle($title);
    $out .= '<ol class="ig-steps-list">';
    $i = 1;
    foreach ($data['items'] as $item) {
        $label = htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8');
        $text  = htmlspecialchars($item['text']  ?? '', ENT_QUOTES, 'UTF-8');
        $out .= '<li class="ig-step"><span class="ig-step-num">' . str_pad((string)$i, 2, '0', STR_PAD_LEFT) . '</span>';
        $out .= '<div class="ig-step-body"><strong class="ig-step-label">' . $label . '</strong>';
        $out .= '<p class="ig-step-text">' . $text . '</p></div></li>';
        $i++;
    }
    $out .= '</ol></figure>';
    return $out;
}

/**
 * CHECKLIST: lista con icona check / X
 * data: { items: [{text, icon: "check"|"x"}] }
 */
function infographic_checklist(string $title, array $data): string {
    if (empty($data['items'])) return '';
    $out = '<figure class="ig ig-checklist">';
    $out .= _igTitle($title);
    $out .= '<ul class="ig-check-list">';
    foreach ($data['items'] as $item) {
        $icon = ($item['icon'] ?? 'check') === 'x' ? 'x' : 'check';
        $text = htmlspecialchars($item['text'] ?? '', ENT_QUOTES, 'UTF-8');
        $out .= '<li class="ig-check-item ig-icon-' . $icon . '">';
        $out .= '<span class="ig-check-icon" aria-hidden="true">' . ($icon === 'x' ? '✕' : '✓') . '</span>';
        $out .= '<span class="ig-check-text">' . $text . '</span></li>';
    }
    $out .= '</ul></figure>';
    return $out;
}

/**
 * COMPARE: due colonne affiancate (evitare vs fare, prima vs dopo)
 * data: { left_label, right_label, rows: [{left, right}] }
 */
function infographic_compare(string $title, array $data): string {
    if (empty($data['rows'])) return '';
    $ll = htmlspecialchars($data['left_label']  ?? 'Invece di', ENT_QUOTES, 'UTF-8');
    $rl = htmlspecialchars($data['right_label'] ?? 'Fai',       ENT_QUOTES, 'UTF-8');
    $out = '<figure class="ig ig-compare">';
    $out .= _igTitle($title);
    $out .= '<div class="ig-compare-grid">';
    $out .= '<div class="ig-compare-head ig-compare-left">' . $ll . '</div>';
    $out .= '<div class="ig-compare-head ig-compare-right">' . $rl . '</div>';
    foreach ($data['rows'] as $row) {
        $l = htmlspecialchars($row['left']  ?? '', ENT_QUOTES, 'UTF-8');
        $r = htmlspecialchars($row['right'] ?? '', ENT_QUOTES, 'UTF-8');
        $out .= '<div class="ig-compare-cell ig-compare-left">' . $l . '</div>';
        $out .= '<div class="ig-compare-cell ig-compare-right">' . $r . '</div>';
    }
    $out .= '</div></figure>';
    return $out;
}

/**
 * STATS: numeri grandi + etichetta
 * data: { items: [{value, label, caption?}] }
 */
function infographic_stats(string $title, array $data): string {
    if (empty($data['items'])) return '';
    $out = '<figure class="ig ig-stats">';
    $out .= _igTitle($title);
    $out .= '<div class="ig-stats-grid">';
    foreach ($data['items'] as $item) {
        $v = htmlspecialchars($item['value']   ?? '', ENT_QUOTES, 'UTF-8');
        $l = htmlspecialchars($item['label']   ?? '', ENT_QUOTES, 'UTF-8');
        $c = htmlspecialchars($item['caption'] ?? '', ENT_QUOTES, 'UTF-8');
        $out .= '<div class="ig-stat">';
        $out .= '<div class="ig-stat-value">' . $v . '</div>';
        $out .= '<div class="ig-stat-label">' . $l . '</div>';
        if ($c !== '') $out .= '<div class="ig-stat-caption">' . $c . '</div>';
        $out .= '</div>';
    }
    $out .= '</div></figure>';
    return $out;
}

/**
 * QUOTE: citazione grande di rilievo
 * data: { text, author? }
 */
function infographic_quote(string $title, array $data): string {
    if (empty($data['text'])) return '';
    $t = htmlspecialchars($data['text']   ?? '', ENT_QUOTES, 'UTF-8');
    $a = htmlspecialchars($data['author'] ?? '', ENT_QUOTES, 'UTF-8');
    $out = '<figure class="ig ig-quote">';
    $out .= '<blockquote class="ig-quote-text">' . $t . '</blockquote>';
    if ($a !== '') $out .= '<figcaption class="ig-quote-author">— ' . $a . '</figcaption>';
    $out .= '</figure>';
    return $out;
}

/**
 * FLOW: timeline orizzontale (oggi → fra 7gg → fra 1 mese)
 * data: { items: [{label, text}] }
 */
function infographic_flow(string $title, array $data): string {
    if (empty($data['items'])) return '';
    $out = '<figure class="ig ig-flow">';
    $out .= _igTitle($title);
    $out .= '<div class="ig-flow-track">';
    foreach ($data['items'] as $item) {
        $l = htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8');
        $t = htmlspecialchars($item['text']  ?? '', ENT_QUOTES, 'UTF-8');
        $out .= '<div class="ig-flow-step">';
        $out .= '<div class="ig-flow-dot"></div>';
        $out .= '<div class="ig-flow-label">' . $l . '</div>';
        $out .= '<div class="ig-flow-text">' . $t . '</div>';
        $out .= '</div>';
    }
    $out .= '</div></figure>';
    return $out;
}

function process_infographic_shortcodes(string $html): string {
    return preg_replace_callback(
        '/\[IG:(\w+):([^\]]*)\]\s*((?:(?!\[\/IG\]).)*)\s*\[\/IG\]/s',
        function ($m) {
            $type  = trim($m[1]);
            $title = trim($m[2]);
            $json  = trim($m[3]);
            $data  = json_decode($json, true);
            if (!is_array($data)) return '<p style="color:red"><strong>Errore infografica:</strong> JSON non valido</p>';
            return render_infographic([
                'infographic_type'  => $type,
                'infographic_title' => $title,
                'infographic_data'  => $json,
            ]);
        },
        $html
    );
}
