<?php
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function slug_to_label(string $slug): string {
    return ucwords(str_replace('-', ' ', $slug));
}

function text_to_id(string $text): string {
    $id = strtolower(trim($text));
    $id = preg_replace('/[^a-z0-9\s\-]/', '', $id);
    $id = preg_replace('/[\s]+/', '-', $id);
    return preg_replace('/-+/', '-', trim($id, '-'));
}

function markdown_to_html(string $md): string {
    // Proteggi blocchi HTML (infografiche) PRIMA di qualsiasi parsing
    $htmlBlocks = [];
    $md = preg_replace_callback(
        '/(<div\s[^>]*style=[^>]*max-width[^>]*>[\s\S]*?<\/div>\s*<\/div>)\s*$/m',
        function ($m) use (&$htmlBlocks) {
            $key = '<!--HTMLBLOCK' . count($htmlBlocks) . '-->';
            $htmlBlocks[$key] = $m[1];
            return $key;
        },
        $md
    );

    // Titoli con id per anchor link
    $md = preg_replace_callback('/^#### (.+)$/m', fn($m) => '<h4 id="' . text_to_id($m[1]) . '">' . $m[1] . '</h4>', $md);
    $md = preg_replace_callback('/^### (.+)$/m', fn($m) => '<h3 id="' . text_to_id($m[1]) . '">' . $m[1] . '</h3>', $md);
    $md = preg_replace_callback('/^## (.+)$/m', fn($m) => '<h2 id="' . text_to_id($m[1]) . '">' . $m[1] . '</h2>', $md);
    $md = preg_replace_callback('/^# (.+)$/m', fn($m) => '<h1 id="' . text_to_id($m[1]) . '">' . $m[1] . '</h1>', $md);
    // Bold e italic
    $md = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $md);
    $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);
    $md = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $md);
    // Link
    $md = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $md);
    // HR
    $md = preg_replace('/^---+$/m', '<hr>', $md);
    // Blockquote
    $md = preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $md);
    // Liste UL
    $md = preg_replace_callback(
        '/((?:^- .+\n?)+)/m',
        fn($m) => '<ul>' . preg_replace('/^- (.+)$/m', '<li>$1</li>', $m[1]) . '</ul>',
        $md
    );
    // Liste OL
    $md = preg_replace_callback(
        '/((?:^\d+\. .+\n?)+)/m',
        fn($m) => '<ol>' . preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $m[1]) . '</ol>',
        $md
    );
    // Paragrafi (righe non già HTML)
    $lines = explode("\n", $md);
    $out = [];
    $inBlock = false;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') { $inBlock = false; $out[] = ''; continue; }
        // Un tag di blocco (titolo, elenco, div...) chiude il paragrafo; un tag inline (link, grassetto, corsivo) lo continua.
        if ((preg_match('/^</', $trimmed) && !preg_match('~^<(a|strong|em|b|i|u|s|span|code|sup|sub|mark|cite|small)[\s>]~i', $trimmed)) || strpos($trimmed, '<!--HTMLBLOCK') !== false) {
            $inBlock = false; $out[] = $trimmed; continue;
        }
        if (!$inBlock) { $out[] = '<p>' . $trimmed; $inBlock = true; }
        else { $out[count($out) - 1] .= ' ' . $trimmed; }
    }
    $html = implode("\n", $out);
    // Chiudi paragrafi aperti (solo quelli senza </p>)
    $htmlLines = explode("\n", $html);
    foreach ($htmlLines as &$hl) {
        if (str_starts_with(trim($hl), '<p>') && strpos($hl, '</p>') === false) {
            $hl .= '</p>';
        }
    }
    unset($hl);
    $html = implode("\n", $htmlLines);

    // Reinserisci blocchi HTML protetti
    foreach ($htmlBlocks as $key => $block) {
        $html = str_replace($key, $block, $html);
    }

    return $html;
}

function site_url(string $path = ''): string {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function cta_info(string $cta): array {
    $map = [
        'laser coaching 15 min' => [
            'label' => 'Prenota la tua Laser Coaching gratuita da 15 minuti',
            'href'  => site_url('laser-coaching/'),
            'desc'  => '15 minuti per fare chiarezza su 1 situazione specifica.',
        ],
        'percorso D.O.C.' => [
            'label' => 'Scopri il Percorso D.O.C.',
            'href'  => site_url('percorso-doc/'),
            'desc'  => 'Un percorso strutturato per cambiare il modo in cui stai al lavoro.',
        ],
        'ebook gratuito' => [
            'label' => "Scarica l'Ebook Gratuito",
            'href'  => site_url('ebook/'),
            'desc'  => '"Fuori dagli schemi" — confini, presenza, dialogo, responsabilità.',
        ],
        'workshop tensioni' => [
            'label' => 'Info Workshop',
            'href'  => site_url('workshop/'),
            'desc'  => 'Lavoro pratico su conflitti, fiducia e ruoli.',
        ],
        'workshop comunicazione' => [
            'label' => 'Info Workshop',
            'href'  => site_url('workshop/'),
            'desc'  => 'Lavoro pratico su comunicazione e dinamiche di gruppo.',
        ],
    ];
    return $map[$cta] ?? $map['laser coaching 15 min'];
}
