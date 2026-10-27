<?php
/**
 * Sanificatori: HTML su parser DOM, CSS confinato, SVG, indirizzi, chiamate in uscita, immagini ricodificate.
 * Niente regex sull'HTML: il browser legge l'HTML con un parser, e un filtro che non lo fa si aggira sempre.
 */

// ------------------------------------------------------------
// Indirizzi
// ------------------------------------------------------------

/** Un indirizzo per href/src: http, https, percorso interno, ancora, mailto, tel. Tutto il resto (javascript:, data:, vbscript:) sparisce. */
function sicurezza_url_pulito(string $url, bool $immagine = false): string {
    $url = trim(preg_replace('/[\x00-\x20\x7F]+/', '', $url) ?? '');
    if ($url === '') return '';
    if ($immagine && preg_match('~^data:image/(png|jpeg|gif|webp|avif);base64,[A-Za-z0-9+/=]+$~', $url)) return $url;
    if (preg_match('~^(https?://|/|#|\./|\.\./|\?)~i', $url)) return $url;
    if (!$immagine && preg_match('~^(mailto:|tel:|sms:)~i', $url)) return $url;
    // Senza schema ("pagina/" o "immagine.png"): relativo, va bene se non contiene due punti prima di una barra.
    if (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) return $url;
    return '';
}

/** Un redirect puo andare solo dentro il sito: percorso che comincia con una barra sola. Altrimenti la home. */
function sicurezza_percorso_interno(string $p, string $altrimenti = '/'): string {
    $p = trim($p);
    if ($p === '' || !preg_match('~^/(?!/)[^\s\x00-\x1F\\\\]*$~', $p)) return $altrimenti;
    return $p;
}

/** Un ritorno dentro il pannello: "pagina.php?..." (relativo alla cartella admin) oppure un percorso interno. Altrimenti $altrimenti. */
function sicurezza_ritorno_pannello(string $p, string $altrimenti): string {
    $p = trim($p);
    if (preg_match('~^[a-z0-9_-]+\.php(\?[^\s\x00-\x1F\\\\]*)?(#[A-Za-z0-9_-]*)?$~i', $p)) return $p;
    return sicurezza_percorso_interno($p, $altrimenti);
}

// ------------------------------------------------------------
// CSV (sicurezza, attacco 2: formula injection)
// ------------------------------------------------------------

/**
 * Una cella di un CSV: se comincia con = + - @, tab o a capo, Excel e LibreOffice la leggerebbero come formula
 * (=HYPERLINK, =cmd|...). Un apice davanti la rende testo. I numeri puri (anche negativi, con virgola) restano numeri.
 */
function sicurezza_csv_cella($v) {
    if ($v === null || is_int($v) || is_float($v) || is_bool($v)) return $v;
    $v = (string)$v;
    if ($v === '' || preg_match('/^-?\d+([.,]\d+)?$/', $v)) return $v;
    return preg_match('/^[=+\-@\t\r\n]/', $v) ? "'" . $v : $v;
}

function sicurezza_csv_riga(array $r): array {
    return array_map('sicurezza_csv_cella', $r);
}

// ------------------------------------------------------------
// HTML
// ------------------------------------------------------------

/** I tag e gli attributi ammessi per profilo. 'ricco': il testo del widget Testo e dei popup. 'articolo': un articolo o una pagina intera. */
function sicurezza_html_profili(): array {
    $inline = ['strong', 'b', 'em', 'i', 'u', 's', 'a', 'span', 'sup', 'sub', 'code', 'cite', 'mark', 'small', 'abbr', 'kbd', 'q', 'del', 'ins', 'time', 'br'];
    $blocchi = ['p', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote'];
    return [
        'ricco' => ['tag' => array_merge($inline, $blocchi), 'attr' => ['a' => ['href', 'title', 'target', 'rel'], '*' => ['class']], 'classi' => '/^ql-align-(center|right|justify)$/'],
        'articolo' => ['tag' => array_merge($inline, $blocchi, ['h1', 'h5', 'h6', 'div', 'section', 'article', 'aside', 'header', 'footer', 'nav', 'figure', 'figcaption', 'img', 'picture', 'source',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col', 'pre', 'hr', 'dl', 'dt', 'dd', 'details', 'summary', 'video', 'audio']),
            'attr' => ['a' => ['href', 'title', 'target', 'rel', 'download'], 'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'decoding', 'srcset', 'sizes'], 'source' => ['src', 'srcset', 'type', 'media'],
                'video' => ['src', 'controls', 'poster', 'width', 'height', 'muted', 'loop', 'playsinline', 'preload'], 'audio' => ['src', 'controls', 'preload', 'loop'],
                'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'], 'col' => ['span'], 'time' => ['datetime'], 'abbr' => ['title'], 'details' => ['open'], 'ol' => ['start', 'reversed', 'type'],
                '*' => ['class', 'id', 'title', 'lang', 'dir', 'style', 'role', 'aria-label', 'aria-hidden', 'aria-labelledby', 'aria-describedby', 'tabindex', 'itemprop', 'itemscope', 'itemtype', 'data-*']],
            'classi' => null],
    ];
}

/**
 * Pulisce un pezzo di HTML con il parser DOM: tiene solo i tag e gli attributi del profilo, toglie script, eventi,
 * stili pericolosi e indirizzi con schemi vietati. I tag sconosciuti si aprono (resta il testo), quelli pericolosi spariscono con il contenuto.
 */
function sicurezza_html_pulito(string $html, string $profilo = 'ricco'): string {
    if (trim($html) === '') return '';
    $p = sicurezza_html_profili()[$profilo] ?? sicurezza_html_profili()['ricco'];
    $tagOk = array_flip($p['tag']);
    $viaConTutto = array_flip(['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'meta', 'link', 'base', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'svg', 'math', 'template', 'noscript', 'xmp', 'plaintext', 'title', 'head', 'canvas', 'dialog', 'slot', 'portal']);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $prima = libxml_use_internal_errors(true);
    // L'entita per i caratteri fuori dal piano base le lascia stare: il documento e dichiarato UTF-8.
    $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="sicurezza-radice">' . $html . '</div></body></html>', LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prima);
    $radice = $dom->getElementById('sicurezza-radice');
    if (!$radice) return '';
    sicurezza_html_nodo($radice, $tagOk, $viaConTutto, $p);
    $out = '';
    foreach (iterator_to_array($radice->childNodes) as $n) $out .= $dom->saveHTML($n);
    return $out;
}

function sicurezza_html_nodo(DOMNode $nodo, array $tagOk, array $viaConTutto, array $p): void {
    foreach (iterator_to_array($nodo->childNodes) as $f) {
        if ($f instanceof DOMComment || $f instanceof DOMProcessingInstruction || $f instanceof DOMDocumentType) { $nodo->removeChild($f); continue; }
        if (!($f instanceof DOMElement)) continue;
        $tag = strtolower($f->tagName);
        if (isset($viaConTutto[$tag])) { $nodo->removeChild($f); continue; }
        if (!isset($tagOk[$tag])) {
            // Tag sconosciuto: restano i figli al suo posto.
            while ($f->firstChild) $nodo->insertBefore($f->firstChild, $f);
            $nodo->removeChild($f);
            continue;
        }
        sicurezza_html_attributi($f, $tag, $p);
        sicurezza_html_nodo($f, $tagOk, $viaConTutto, $p);
    }
}

function sicurezza_html_attributi(DOMElement $e, string $tag, array $p): void {
    $ammessi = array_merge($p['attr']['*'] ?? [], $p['attr'][$tag] ?? []);
    $dataOk = in_array('data-*', $ammessi, true);
    foreach (iterator_to_array($e->attributes) as $a) {
        $nome = strtolower($a->name);
        $val = $a->value;
        $ok = in_array($nome, $ammessi, true) || ($dataOk && str_starts_with($nome, 'data-') && preg_match('/^data-[a-z0-9-]+$/', $nome));
        if (!$ok || str_starts_with($nome, 'on') || str_contains($nome, ':')) { $e->removeAttribute($a->name); continue; }
        switch ($nome) {
            case 'href': case 'src': case 'poster':
                $v = sicurezza_url_pulito($val, $nome !== 'href');
                if ($v === '') $e->removeAttribute($a->name); else $e->setAttribute($a->name, $v);
                break;
            case 'srcset':
                $parti = [];
                foreach (explode(',', $val) as $x) { $x = trim($x); $u = sicurezza_url_pulito((string)strtok($x, ' '), true); if ($u !== '' && !str_starts_with($u, 'data:')) $parti[] = $x; }
                if ($parti) $e->setAttribute($a->name, implode(', ', $parti)); else $e->removeAttribute($a->name);
                break;
            case 'style':
                $v = sicurezza_stile_inline_pulito($val);
                if ($v === '') $e->removeAttribute($a->name); else $e->setAttribute($a->name, $v);
                break;
            case 'class':
                if (!empty($p['classi'])) {
                    $cl = array_filter(preg_split('/\s+/', trim($val)) ?: [], fn($c) => preg_match($p['classi'], $c));
                    if ($cl) $e->setAttribute('class', implode(' ', $cl)); else $e->removeAttribute('class');
                } else {
                    $e->setAttribute('class', trim(preg_replace('/[^A-Za-z0-9 _:-]/', '', $val)));
                }
                break;
            case 'id':
                $e->setAttribute('id', preg_replace('/[^A-Za-z0-9_-]/', '', $val));
                break;
            case 'target':
                if ($val !== '_blank') $e->removeAttribute('target');
                break;
            case 'itemtype':
                if (!preg_match('~^https?://schema\.org/~', $val)) $e->removeAttribute('itemtype');
                break;
            default:
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $val)) $e->setAttribute($a->name, preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $val));
        }
    }
    if ($tag === 'a' && $e->getAttribute('target') === '_blank') {
        $rel = array_unique(array_merge(preg_split('/\s+/', trim($e->getAttribute('rel'))) ?: [], ['noopener', 'noreferrer']));
        $e->setAttribute('rel', trim(implode(' ', array_filter($rel))));
    }
    if ($tag === 'a' && $e->hasAttribute('rel')) $e->setAttribute('rel', trim(preg_replace('/[^a-z ]/', '', strtolower($e->getAttribute('rel')))));
}

/** Uno style="..." senza url() esterni, expression, behavior, import, binding. */
function sicurezza_stile_inline_pulito(string $css): string {
    $css = str_replace(["\0", '\\'], '', $css);
    if (preg_match('/expression\s*\(|behavior\s*:|-moz-binding|@import|javascript:|vbscript:|<\/?style/i', $css)) return '';
    $css = preg_replace_callback('/url\s*\(\s*([\'"]?)([^)\'"]*)\1\s*\)/i', function ($m) {
        $u = trim($m[2]);
        return (str_starts_with($u, '/') && !str_starts_with($u, '//')) || preg_match('~^data:image/(png|jpeg|gif|webp|svg\+xml);~', $u) ? 'url(' . $u . ')' : 'none';
    }, $css);
    return trim(preg_replace('/[\x00-\x1F]/', '', $css));
}

// ------------------------------------------------------------
// CSS
// ------------------------------------------------------------

/** Parole e costrutti vietati in qualunque CSS scritto dal pannello. */
function sicurezza_css_pulisci(string $css): string {
    $css = str_replace("\0", '', $css);
    $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;
    $css = preg_replace('~</?\s*style[^>]*>|<[^>]*>~i', '', $css) ?? $css;
    $css = preg_replace('~@import[^;{]*;?|@charset[^;]*;|@namespace[^;]*;~i', '', $css) ?? $css;
    $css = preg_replace('~expression\s*\([^)]*\)|behavior\s*:[^;}]*|-moz-binding\s*:[^;}]*~i', '', $css) ?? $css;
    $css = preg_replace('~(javascript|vbscript|data:text/html)\s*:~i', '', $css) ?? $css;
    // url(): solo percorsi interni del sito, oppure immagini data:.
    $css = preg_replace_callback('/url\s*\(\s*([\'"]?)([^)\'"]*)\1\s*\)/i', function ($m) {
        $u = trim($m[2]);
        if ((str_starts_with($u, '/') && !str_starts_with($u, '//')) || preg_match('~^data:image/(png|jpeg|gif|webp|svg\+xml);~', $u) || preg_match('~^[A-Za-z0-9_.-][A-Za-z0-9_./-]*$~', $u)) return 'url(' . $u . ')';
        return 'none';
    }, $css) ?? $css;
    return $css;
}

/**
 * Confina un CSS a un elemento: ogni selettore viene preceduto da $sel (la parola "selector" indica l'elemento stesso);
 * @media e @supports si attraversano; le altre regole @ (font-face, keyframes, import) non entrano.
 * Cosi "body{display:none}" diventa ".ed-x body{...}" e non tocca piu niente fuori dall'elemento.
 */
function sicurezza_css_confina(string $css, string $sel): string {
    $css = sicurezza_css_pulisci($css);
    return sicurezza_css_blocchi($css, $sel, 0);
}

function sicurezza_css_blocchi(string $css, string $sel, int $livello): string {
    $out = '';
    $i = 0; $n = strlen($css);
    while ($i < $n) {
        $apre = strpos($css, '{', $i);
        if ($apre === false) break;
        $testa = trim(substr($css, $i, $apre - $i));
        // Il blocco bilanciato
        $prof = 0; $j = $apre;
        for (; $j < $n; $j++) {
            if ($css[$j] === '{') $prof++;
            elseif ($css[$j] === '}') { $prof--; if ($prof === 0) break; }
        }
        $corpo = substr($css, $apre + 1, max(0, $j - $apre - 1));
        $i = $j + 1;
        if ($testa === '') continue;
        if ($testa[0] === '@') {
            if (preg_match('/^@(media|supports|container|layer)\b/i', $testa) && $livello < 3) $out .= $testa . '{' . sicurezza_css_blocchi($corpo, $sel, $livello + 1) . '}';
            continue; // @font-face, @keyframes, @import e il resto non entrano
        }
        if (str_contains($corpo, '{')) continue; // annidamento CSS: non ammesso
        $selettori = [];
        foreach (explode(',', $testa) as $s) {
            $s = trim(preg_replace('/\s+/', ' ', $s));
            if ($s === '') continue;
            if (preg_match('/[^A-Za-z0-9 _.#:>+~*\[\]="\'()\-,|^$]/', $s)) continue;
            $selettori[] = str_contains($s, 'selector') ? str_replace('selector', $sel, $s) : $sel . ' ' . $s;
        }
        if (!$selettori) continue;
        // Il corpo resta com'era scritto (spazi compresi): "selector .t { color: red }" diventa ".ed-ID .t { color: red }".
        $out .= implode(',', $selettori) . ' {' . $corpo . '}';
    }
    return $out;
}

// ------------------------------------------------------------
// SVG
// ------------------------------------------------------------

/** Un SVG senza script, eventi, foreignObject, riferimenti esterni ed entita. Ritorna null se il file non e un SVG leggibile. */
function sicurezza_svg_pulito(string $svg): ?string {
    if (stripos($svg, '<!ENTITY') !== false || stripos($svg, '<!DOCTYPE') !== false) $svg = preg_replace('/<!DOCTYPE[^>]*(\[.*?\])?>/is', '', $svg) ?? $svg;
    if (stripos($svg, '<!ENTITY') !== false) return null;
    $dom = new DOMDocument();
    $prima = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($prima);
    if (!$ok || !$dom->documentElement || strtolower($dom->documentElement->localName) !== 'svg') return null;
    $via = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video', 'animate', 'set', 'handler', 'listener'];
    $todo = [$dom->documentElement];
    while ($todo) {
        $e = array_pop($todo);
        if (!($e instanceof DOMElement)) continue;
        if (in_array(strtolower($e->localName), $via, true)) { $e->parentNode?->removeChild($e); continue; }
        foreach (iterator_to_array($e->attributes) as $a) {
            $nome = strtolower($a->name);
            $loc = strtolower($a->localName);
            $v = trim($a->value);
            if (str_starts_with($loc, 'on')) { $e->removeAttributeNode($a); continue; }
            if ($loc === 'href' || $nome === 'xlink:href' || $loc === 'src' || $loc === 'from' || $loc === 'to' || $loc === 'values') {
                if ($loc === 'href' || $nome === 'xlink:href') {
                    if (!($v === '' || str_starts_with($v, '#') || preg_match('~^data:image/(png|jpeg|gif|webp);base64,~', $v))) $e->removeAttributeNode($a);
                }
                continue;
            }
            if ($loc === 'style') { $s = sicurezza_stile_inline_pulito($v); if ($s === '') $e->removeAttributeNode($a); else $a->value = $s; continue; }
            if (preg_match('/javascript:|url\s*\(/i', $v) && !preg_match('/^url\(#/i', $v)) $e->removeAttributeNode($a);
        }
        if (strtolower($e->localName) === 'style') $e->textContent = sicurezza_css_pulisci($e->textContent);
        foreach ($e->childNodes as $c) if ($c instanceof DOMElement) $todo[] = $c;
        foreach (iterator_to_array($e->childNodes) as $c) if ($c instanceof DOMProcessingInstruction) $e->removeChild($c);
    }
    $out = $dom->saveXML($dom->documentElement);
    return $out === false ? null : '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $out;
}

// ------------------------------------------------------------
// Immagini: nuova codifica
// ------------------------------------------------------------

/**
 * Riapre un'immagine con GD e la riscrive: via i metadati, via qualunque cosa nascosta dopo i pixel.
 * Ritorna vero se il file e stato riscritto, falso se GD non puo (formato non supportato, GIF animata): il file resta com'e.
 */
function sicurezza_immagine_ricodifica(string $file, string $ext): bool {
    if (!function_exists('imagecreatefromstring')) return false;
    $ext = strtolower($ext);
    if ($ext === 'gif') {
        // Una GIF animata ha piu di un blocco immagine: ricodificarla la fermerebbe. Si lascia stare (PHP e spento in uploads/).
        $d = (string)file_get_contents($file);
        if (preg_match_all('/\x00\x21\xF9\x04/', $d) > 1) return false;
    }
    if ($ext === 'avif' && !function_exists('imageavif')) return false;
    if ($ext === 'webp' && !function_exists('imagewebp')) return false;
    $dati = (string)file_get_contents($file);
    $img = @imagecreatefromstring($dati);
    if (!$img) return false;
    // Orientamento EXIF dei JPEG: la foto resta dritta anche senza i metadati.
    if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o === 3) $img = imagerotate($img, 180, 0);
        elseif ($o === 6) $img = imagerotate($img, -90, 0);
        elseif ($o === 8) $img = imagerotate($img, 90, 0);
    }
    $tmp = $file . '.tmp';
    $ok = match ($ext) {
        'jpg', 'jpeg' => imagejpeg($img, $tmp, 90),
        'png' => (function () use ($img, $tmp) { imagealphablending($img, false); imagesavealpha($img, true); return imagepng($img, $tmp, 6); })(),
        'gif' => imagegif($img, $tmp),
        'webp' => (function () use ($img, $tmp) { imagealphablending($img, false); imagesavealpha($img, true); return imagewebp($img, $tmp, 90); })(),
        'avif' => imageavif($img, $tmp, 70),
        default => false,
    };
    imagedestroy($img);
    if (!$ok || !is_file($tmp) || filesize($tmp) === 0) { @unlink($tmp); return false; }
    return rename($tmp, $file);
}

// ------------------------------------------------------------
// Chiamate in uscita (SSRF)
// ------------------------------------------------------------

/** Gli host verso cui il CMS puo fare chiamate HTTP (IndexNow, Google, verifica dei motori). I moduli e la pagina Sicurezza ne aggiungono. */
function sicurezza_uscita_host_base(): array {
    return ['api.indexnow.org', 'www.bing.com', 'bing.com', 'yandex.com', 'oauth2.googleapis.com', 'www.googleapis.com', 'searchconsole.googleapis.com', 'indexing.googleapis.com', 'www.google.com', 'google.com'];
}

/** Un IP e privato, locale o di link-local? */
function sicurezza_ip_privato(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * Controlla un indirizzo prima di una chiamata in uscita: schema http/https, host in allowlist, risoluzione DNS
 * che non porti a un IP privato. Ritorna ['ok' => true, 'ip' => ..., 'host' => ...] oppure ['ok' => false, 'errore' => ...].
 */
function sicurezza_uscita_controlla(string $url, array $hostExtra = []): array {
    $p = parse_url($url);
    if (!$p || !in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true) || empty($p['host'])) return ['ok' => false, 'errore' => 'Indirizzo non valido per una chiamata in uscita.'];
    $host = strtolower($p['host']);
    if (!empty($p['user']) || !empty($p['pass'])) return ['ok' => false, 'errore' => 'Credenziali nell\'indirizzo: non ammesse.'];
    $ammessi = array_merge(sicurezza_uscita_host_base(), $hostExtra);
    if (function_exists('cms_filtro')) $ammessi = (array)cms_filtro('uscita_host', $ammessi);
    if (function_exists('sicurezza_cfg')) { try { foreach (preg_split('/[\s,]+/', (string)sicurezza_cfg()['uscita_host']) as $h) if ($h !== '') $ammessi[] = strtolower(trim($h)); } catch (Throwable $e) {} }
    $ok = false;
    foreach ($ammessi as $a) { $a = strtolower(trim($a)); if ($a === '') continue; if ($host === $a || (str_starts_with($a, '*.') && str_ends_with($host, substr($a, 1)))) { $ok = true; break; } }
    if (!$ok) return ['ok' => false, 'errore' => "Host non consentito per le chiamate in uscita: $host (si aggiunge nella pagina Sicurezza)."];
    if (filter_var($host, FILTER_VALIDATE_IP)) { if (sicurezza_ip_privato($host)) return ['ok' => false, 'errore' => 'Indirizzo IP privato: non ammesso.']; return ['ok' => true, 'ip' => $host, 'host' => $host]; }
    $ips = @gethostbynamel($host);
    if (!$ips) return ['ok' => false, 'errore' => "Il nome $host non si risolve (rete chiusa o DNS fermo)."];
    foreach ($ips as $ip) if (sicurezza_ip_privato($ip)) return ['ok' => false, 'errore' => "Il nome $host porta a un indirizzo privato ($ip): chiamata rifiutata."];
    return ['ok' => true, 'ip' => $ips[0], 'host' => $host, 'porta' => (int)($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80))];
}
