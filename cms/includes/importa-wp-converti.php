<?php
/**
 * Import da WordPress (1.4.0): il testo di un articolo o di una pagina diventa HTML semplice e sicuro.
 *
 * Si parte dall'HTML che WordPress stesso disegna (content.rendered): i blocchi di Gutenberg sono gia paragrafi, titoli,
 * figure, liste, citazioni e tabelle. Poi, con il parser DOM:
 * - gli embed di YouTube e Vimeo restano video (un iframe costruito qui, con l'id controllato); gli altri embed diventano link;
 * - le immagini del vecchio sito si scaricano nella libreria (importa_wp_media) e l'indirizzo si riscrive; via srcset e sizes,
 *   che puntano alle misure di WordPress;
 * - i link interni del vecchio sito puntano agli indirizzi nuovi; i link a file di wp-content/uploads (un PDF) scaricano il file;
 * - gli shortcode dei plugin che WordPress non ha eseguito ([contact-form-7 ...]) si tolgono;
 * - tutto passa da sicurezza_html_pulito (profilo "articolo"): via script, stili pericolosi, eventi, iframe, moduli;
 * - i caratteri a 4 byte (emoji) diventano entita: le colonne del CMS possono essere utf8 a 3 byte.
 */

require_once __DIR__ . '/importa-wp.php';

/** YouTube o Vimeo da un indirizzo: ['youtube', id] / ['vimeo', id] oppure null. */
function importa_wp_video(string $u): ?array {
    if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $u, $m)) return ['youtube', $m[1]];
    if (preg_match('~(?:player\.)?vimeo\.com/(?:video/)?(\d{5,12})~', $u, $m)) return ['vimeo', $m[1]];
    return null;
}

function importa_wp_video_html(string $tipo, string $id): string {
    $src = $tipo === 'youtube' ? 'https://www.youtube-nocookie.com/embed/' . $id : 'https://player.vimeo.com/video/' . $id;
    return '<figure class="importa-video"><iframe src="' . h($src) . '" title="Video" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen style="width:100%;aspect-ratio:16/9;border:0"></iframe></figure>';
}

/** I nomi degli shortcode di plugin nel testo grezzo (non quelli di WordPress stesso). */
function importa_wp_shortcode_nomi(string $raw): array {
    if (!preg_match_all('/\[([a-zA-Z][a-zA-Z0-9_-]*)[\s\]\/]/', $raw, $m)) return [];
    return array_values(array_unique(array_filter($m[1], fn($n) => !in_array(strtolower($n), ['caption', 'wp_caption', 'gallery', 'audio', 'video', 'playlist', 'embed'], true))));
}

/**
 * Il testo nuovo. $voce: la voce dell'indice (per le note), $note: si riempie con quello che non e entrato.
 */
function importa_wp_html(array $s, string $lavoro, string $html, string $raw, array &$note): string {
    // Shortcode che WordPress non ha eseguito: restano scritti cosi nel testo disegnato. Via il comando, resta quello che c'era dentro.
    foreach (importa_wp_shortcode_nomi($raw) as $n) {
        $q = preg_quote($n, '~');
        $prima = $html;
        $html = (string)preg_replace('~\[/?' . $q . '(?:\s[^\]]*)?/?\]~', '', $html);
        if ($prima !== $html) $note['shortcode'][$n] = true;
    }
    if (trim($html) === '') return '';
    $dom = new DOMDocument('1.0', 'UTF-8');
    $prima = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="importa-radice">' . $html . '</div></body></html>', LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prima);
    $radice = $dom->getElementById('importa-radice');
    if (!$radice) return '';
    $xp = new DOMXPath($dom);

    // Embed: video veri o link.
    foreach (iterator_to_array($xp->query('//figure[contains(concat(" ", normalize-space(@class), " "), " wp-block-embed ")] | //iframe')) as $e) {
        if (!$e->parentNode) continue;
        $url = $e->nodeName === 'iframe' ? (string)$e->getAttribute('src') : '';
        if ($url === '') foreach ($xp->query('.//iframe', $e) as $f) { $url = (string)$f->getAttribute('src'); break; }
        if ($url === '') $url = trim((string)$e->textContent);
        if (!preg_match('~^https?://\S+$~', $url)) $url = (string)(preg_match('~https?://\S+~', $url, $m) ? $m[0] : '');
        $v = $url !== '' ? importa_wp_video($url) : null;
        $nuovo = $dom->createElement($v ? 'div' : 'p');
        if ($v) { $nuovo->setAttribute('data-importa-video', $v[0] . '-' . $v[1]); $note['video'] = ($note['video'] ?? 0) + 1; }
        elseif ($url !== '') { $a = $dom->createElement('a'); $a->setAttribute('href', $url); $a->appendChild($dom->createTextNode($url)); $nuovo->appendChild($a); $note['embed_link'] = ($note['embed_link'] ?? 0) + 1; }
        $e->parentNode->replaceChild($nuovo, $e);
    }

    // Immagini, video e audio con file del vecchio sito.
    foreach (iterator_to_array($xp->query('//img | //video | //audio | //source')) as $e) {
        $src = (string)$e->getAttribute('src');
        $e->removeAttribute('srcset'); $e->removeAttribute('sizes');
        if ($src === '' || !importa_wp_del_sito($s, $src)) { if ($src !== '' && preg_match('~^https?://~', $src)) $note['immagini_fuori'] = ($note['immagini_fuori'] ?? 0) + 1; continue; }
        $id = preg_match('/\bwp-image-(\d+)\b/', (string)$e->getAttribute('class'), $m) ? (int)$m[1] : 0;
        $motivo = '';
        $nuovo = importa_wp_media($s, $lavoro, $src, $id, $motivo);
        if ($nuovo !== null) $e->setAttribute('src', $nuovo);
        else $note['immagini_ko'][] = basename((string)parse_url($src, PHP_URL_PATH)) . ($motivo !== '' ? ': ' . $motivo : '');
    }

    // Link: verso gli indirizzi nuovi; i file di uploads si scaricano.
    foreach ($xp->query('//a[@href]') as $a) {
        $href = (string)$a->getAttribute('href');
        if (!importa_wp_del_sito($s, $href) && !str_starts_with($href, '/')) continue;
        $assoluto = str_starts_with($href, '/') ? rtrim((string)$s['sito']['home'], '/') . $href : $href;
        $percorso = importa_wp_percorso($s, $assoluto);
        if (str_contains($percorso, '/wp-content/uploads/')) {
            $motivo = '';
            $nuovo = importa_wp_media($s, $lavoro, $assoluto, 0, $motivo);
            if ($nuovo !== null) $a->setAttribute('href', $nuovo);
            continue;
        }
        $nuovo = importa_wp_link_nuovo($s, $assoluto);
        if ($nuovo !== null) $a->setAttribute('href', $nuovo);
        else $note['link_vecchi'] = ($note['link_vecchi'] ?? 0) + 1;
    }

    $out = '';
    foreach (iterator_to_array($radice->childNodes) as $n) $out .= $dom->saveHTML($n);
    $out = sicurezza_html_pulito($out, 'articolo');
    // I video, dopo il sanificatore: l'iframe lo costruisce il CMS, con un id di sole lettere e cifre.
    $out = (string)preg_replace_callback('~<div data-importa-video="(youtube|vimeo)-([A-Za-z0-9_-]{6,20})"></div>~', fn($m) => importa_wp_video_html($m[1], $m[2]), $out);
    $out = (string)preg_replace('~<p>\s*</p>~', '', $out);
    return sito_senza_4byte(trim($out));
}

/**
 * L'indirizzo nuovo di un link del vecchio sito, dalla mappa del lavoro (vecchio percorso -> nuovo); la home resta "/".
 * Null se il link porta a qualcosa che non entra (resta com'era).
 */
function importa_wp_link_nuovo(array $s, string $url): ?string {
    $p = importa_wp_percorso($s, $url);
    $frammento = (string)(parse_url($url, PHP_URL_FRAGMENT) ?? '');
    $frammento = $frammento !== '' ? '#' . $frammento : '';
    $p = preg_replace('~#.*$~', '', $p);
    if ($p === '/' || $p === '') return '/' . $frammento;
    $mappa = $s['mappa'] ?? [];
    $chiavi = [$p, rtrim($p, '/') . '/', rtrim($p, '/')];
    foreach ($chiavi as $k) if (isset($mappa[$k])) return $mappa[$k] . $frammento;
    return null;
}
