<?php
/**
 * 2.29.0: le immagini senza testo alternativo (alt): la correzione in posto.
 *
 *  - La libreria: ogni immagine di Media può avere un alt suo, nella tabella <prefisso>media_alt (nata al primo uso: percorso,
 *    alt, aggiornato). Si scrive da Media > Senza alt (admin/media.php, scheda alt) una casella per riga, salvata senza
 *    ricaricare (seo-api.php, azione media_alt_salva). Sul sito l'alt della libreria entra in ogni <img> di quell'immagine
 *    che esce con l'alt vuoto (media_alt_applica(), chiamata da sito_html() in sito/index.php): una correzione sola vale per
 *    tutte le pagine che usano la foto. Senza alt salvati (impostazione media_alt_n a 0) il sito esce identico a prima.
 *  - Nelle pagine: le immagini usate nei widget dei contenuti pubblicati (Immagine, Galleria, Carosello, Slider, le pagine di
 *    Scorri di lato, i loghi... ogni controllo "immagine" o "galleria" che ha accanto un campo alt), nel testo della scheda
 *    classica (<img> senza alt) e l'immagine in evidenza degli articoli, con l'alt vuoto e senza un alt nella libreria:
 *    media_alt_nelle_pagine() e media_alt_contenuto(), con il link all'editor o alla scheda. La Tabella SEO (includes/seo-tabella.php)
 *    usa la stessa funzione per la colonna "immagini senza alt".
 * L'Apertura resta fuori: la sua foto è decorativa (alt vuoto e aria-hidden per scelta) e non ha un campo alt.
 */

const MEDIA_ALT_MAX = 300;

/** La tabella degli alt della libreria, creata al primo uso. */
function media_alt_schema(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . tbl('media_alt') . " (
        `percorso` VARCHAR(500) NOT NULL,
        `alt` VARCHAR(300) NOT NULL DEFAULT '',
        `aggiornato` DATETIME DEFAULT NULL,
        PRIMARY KEY (`percorso`(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // 2.38.0: i dati delle immagini (includes/media-dati.php) stanno nella stessa tabella, una colonna per campo, aggiunte qui
    // al primo uso: le righe e gli alt di prima restano come sono.
    try {
        $cols = array_map('strval', db()->query('SHOW COLUMNS FROM ' . tbl('media_alt'))->fetchAll(PDO::FETCH_COLUMN));
        $nuove = [];
        foreach (MEDIA_ALT_COLONNE_DATI as $c => $def) if (!in_array($c, $cols, true)) $nuove[] = 'ADD COLUMN `' . $c . '` ' . $def;
        if ($nuove) db()->exec('ALTER TABLE ' . tbl('media_alt') . ' ' . implode(', ', $nuove));
    } catch (Throwable $e) { error_log('media_alt_schema: ' . $e->getMessage()); }
}

/** 2.38.0: cancella la riga di un'immagine solo se non le resta niente (né alt né altri dati). */
function media_alt_togli_se_vuota(string $k): void {
    $vuote = "alt = ''";
    foreach (array_keys(MEDIA_ALT_COLONNE_DATI) as $c) if ($c !== 'autore_tipo' && $c !== 'origine') $vuote .= " AND `$c` = ''";
    db()->prepare('DELETE FROM ' . tbl('media_alt') . " WHERE percorso = ? AND $vuote")->execute([$k]);
}

/** 2.38.0: le colonne dei dati delle immagini (testo semplice, utf8mb4, NOT NULL con un valore di partenza: va con sql_mode stretto). */
const MEDIA_ALT_COLONNE_DATI = [
    'titolo' => "VARCHAR(200) NOT NULL DEFAULT ''",
    'didascalia' => "VARCHAR(500) NOT NULL DEFAULT ''",
    'descrizione' => "VARCHAR(2000) NOT NULL DEFAULT ''",
    'autore' => "VARCHAR(200) NOT NULL DEFAULT ''",
    'autore_tipo' => "VARCHAR(20) NOT NULL DEFAULT ''",
    'licenza' => "VARCHAR(20) NOT NULL DEFAULT ''",
    'licenza_nome' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'licenza_url' => "VARCHAR(500) NOT NULL DEFAULT ''",
    'fonte_url' => "VARCHAR(500) NOT NULL DEFAULT ''",
    'origine' => "VARCHAR(20) NOT NULL DEFAULT ''",
];

/**
 * La chiave di un'immagine: il percorso senza l'origine del sito e senza la cartella del CMS ("/uploads/pagine/2026/11/foto.jpg"),
 * così la stessa foto si riconosce scritta come /uploads/..., /cartella/uploads/... o con l'indirizzo intero.
 */
function media_alt_chiave(string $url): string {
    $u = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($u === '' || str_contains($u, '..')) return '';
    $u = preg_replace('~[?#].*$~', '', $u);
    $origini = array_filter([function_exists('cms_origine') ? cms_origine() : '', function_exists('cms_url_sito') ? preg_replace('~^(https?://[^/]+).*$~', '$1', cms_url_sito()) : '', function_exists('cms_uploads_url') ? preg_replace('~^(https?://[^/]+).*$~', '$1', cms_uploads_url()) : '']);
    foreach ($origini as $o) if ($o !== '' && preg_match('~^https?://~', $o) && str_starts_with($u, $o . '/')) { $u = substr($u, strlen($o)); break; }
    if (preg_match('~^https?://~', $u)) return $u; // un'immagine su un altro dominio: la chiave è l'indirizzo intero
    $base = function_exists('cms_base') ? cms_base() : '';
    if ($base !== '' && $base !== '/' && str_starts_with($u, $base . '/')) $u = substr($u, strlen($base));
    return str_starts_with($u, '/') ? $u : '';
}

/** Tutti gli alt salvati: chiave => alt. Una volta per richiesta; vuoto senza la tabella o senza righe (nessuna query se media_alt_n è 0). */
function media_alt_tutti(bool $forza = false): array {
    static $tutti = null;
    if ($tutti !== null && !$forza) return $tutti;
    $tutti = [];
    if (!$forza && (int)get_setting('media_alt_n', '0') <= 0) return $tutti;
    try {
        media_alt_schema();
        foreach (db()->query('SELECT percorso, alt FROM ' . tbl('media_alt') . " WHERE alt <> ''")->fetchAll() as $r) $tutti[(string)$r['percorso']] = (string)$r['alt'];
    } catch (Throwable $e) { error_log('media_alt_tutti: ' . $e->getMessage()); }
    return $tutti;
}

/** L'alt della libreria di un'immagine ('' se non c'è). */
function media_alt_di(string $url): string {
    $k = media_alt_chiave($url);
    return $k === '' ? '' : (media_alt_tutti()[$k] ?? '');
}

/** Un alt pulito: testo semplice, senza tag né righe, al massimo MEDIA_ALT_MAX caratteri. */
function media_alt_pulito($alt): string {
    $a = trim(preg_replace('/\s+/u', ' ', strip_tags((string)(is_scalar($alt) ? $alt : ''))));
    if (function_exists('sito_senza_4byte')) $a = sito_senza_4byte($a);
    return mb_substr($a, 0, MEDIA_ALT_MAX);
}

/**
 * Salva l'alt di un'immagine della libreria (vuoto = lo toglie). L'immagine deve stare nella libreria (media_elenco()).
 * Ritorna ['ok' => true, 'alt' => ..., 'percorso' => chiave] oppure ['errore' => ...].
 */
function media_alt_salva(string $url, $alt): array {
    $k = media_alt_chiave($url);
    if ($k === '') return ['errore' => 'Indirizzo dell\'immagine non valido.'];
    $nella = false;
    foreach (media_elenco() as $m) if (media_alt_chiave($m['url']) === $k) { $nella = true; break; }
    if (!$nella) return ['errore' => 'Questa immagine non sta nella libreria dei Media.'];
    $a = media_alt_pulito($alt);
    media_alt_schema();
    if ($a === '') {
        // 2.38.0: la riga resta se ha altri dati (titolo, didascalia, crediti...): si svuota solo l'alt.
        db()->prepare('UPDATE ' . tbl('media_alt') . " SET alt = '', aggiornato = NOW() WHERE percorso = ?")->execute([$k]);
        media_alt_togli_se_vuota($k);
    }
    else db()->prepare('INSERT INTO ' . tbl('media_alt') . ' (percorso, alt, aggiornato) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE alt = VALUES(alt), aggiornato = NOW()')->execute([$k, $a]);
    save_setting('media_alt_n', (string)(int)db()->query('SELECT COUNT(*) FROM ' . tbl('media_alt') . " WHERE alt <> ''")->fetchColumn());
    media_alt_tutti(true);
    return ['ok' => true, 'alt' => $a, 'percorso' => $k];
}

/** Le immagini della libreria senza un alt (niente miniature), nello stesso formato di media_elenco() più 'chiave'. */
function media_alt_senza(): array {
    $tutti = media_alt_tutti(true);
    $out = [];
    foreach (media_elenco() as $m) {
        if ($m['miniatura']) continue;
        $k = media_alt_chiave($m['url']);
        if ($k === '' || isset($tutti[$k])) continue;
        $out[] = $m + ['chiave' => $k];
    }
    return $out;
}

/** Le immagini della libreria con l'alt, per la scheda del pannello (le ultime scritte prima). */
function media_alt_con(): array {
    $tutti = media_alt_tutti(true);
    $out = [];
    foreach (media_elenco() as $m) {
        if ($m['miniatura']) continue;
        $k = media_alt_chiave($m['url']);
        if ($k !== '' && isset($tutti[$k])) $out[] = $m + ['chiave' => $k, 'alt' => $tutti[$k]];
    }
    return $out;
}

/**
 * Sul sito: ogni <img> con l'alt vuoto (o senza alt) di un'immagine che ha un alt nella libreria lo riceve. Senza alt salvati
 * l'HTML torna identico. Si applica alla pagina intera (sito_html), prima della cartella del sito.
 */
function media_alt_applica(string $html): string {
    $tutti = media_alt_tutti();
    if (!$tutti || !str_contains($html, '<img')) return $html;
    return (string)preg_replace_callback('~<img\b[^>]*>~i', function ($m) use ($tutti) {
        $tag = $m[0];
        if (!preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $s)) return $tag;
        $k = media_alt_chiave($s[1]);
        if ($k === '' || !isset($tutti[$k])) return $tag;
        $alt = ' alt="' . htmlspecialchars($tutti[$k], ENT_QUOTES, 'UTF-8') . '"';
        if (preg_match('/\salt\s*=\s*(""|\'\')/i', $tag)) return (string)preg_replace('/\salt\s*=\s*(""|\'\')/i', $alt, $tag, 1);
        if (!preg_match('/\salt\s*=/i', $tag)) return (string)preg_replace('/^<img\b/i', '<img' . $alt, $tag, 1);
        return $tag;
    }, $html);
}

/** I controlli di un widget (o i campi di un ripetitore) per nome. */
function media_alt_per_nome(array $controlli): array {
    $out = [];
    foreach ($controlli as $c) if (isset($c['nome'])) $out[(string)$c['nome']] = $c;
    return $out;
}

/** Il nome del campo alt accanto a un controllo immagine ("alt" o "<nome>_alt"), oppure null se la foto è decorativa. */
function media_alt_campo_alt(array $perNome, string $nome): ?string {
    if (isset($perNome[$nome . '_alt'])) return $nome . '_alt';
    if (isset($perNome['alt'])) return 'alt';
    return null;
}

/** Le immagini senza alt di un widget dell'editor: [['immagine' => url, 'campo' => etichetta del controllo], ...]. */
function media_alt_widget(array $w): array {
    if (function_exists('editor_carica_widget_esterni')) editor_carica_widget_esterni();
    $def = editor_definizioni()[(string)($w['tipo'] ?? '')] ?? null;
    if (!$def || !is_array($w['impostazioni'] ?? [])) return [];
    $out = [];
    $v = editor_valori((string)$w['tipo'], $w['impostazioni'] ?? []);
    $perNome = media_alt_per_nome($def['controlli']);
    $manca = fn($img, $alt) => trim((string)$img) !== '' && trim((string)$alt) === '' && media_alt_di((string)$img) === '';
    foreach ($def['controlli'] as $ctl) {
        $n = (string)($ctl['nome'] ?? ''); $t = (string)($ctl['tipo'] ?? '');
        if ($t === 'immagine') {
            $altNome = media_alt_campo_alt($perNome, $n);
            if ($altNome === null) continue;
            if ($manca($v[$n] ?? '', $v[$altNome] ?? '')) $out[] = ['immagine' => (string)$v[$n], 'campo' => (string)($ctl['etichetta'] ?? $n)];
        } elseif ($t === 'galleria') {
            foreach ((array)($v[$n] ?? []) as $x) if (is_array($x) && $manca($x['immagine'] ?? '', $x['alt'] ?? '')) $out[] = ['immagine' => (string)$x['immagine'], 'campo' => (string)($ctl['etichetta'] ?? $n)];
        } elseif ($t === 'ripetitore' && is_array($ctl['campi'] ?? null)) {
            $sotto = media_alt_per_nome($ctl['campi']);
            foreach ($ctl['campi'] as $cc) {
                if (($cc['tipo'] ?? '') !== 'immagine') continue;
                $altNome = media_alt_campo_alt($sotto, (string)$cc['nome']);
                if ($altNome === null) continue;
                foreach ((array)($v[$n] ?? []) as $x) if (is_array($x) && $manca($x[$cc['nome']] ?? '', $x[$altNome] ?? '')) $out[] = ['immagine' => (string)$x[$cc['nome']], 'campo' => (string)($ctl['etichetta'] ?? $n)];
            }
        }
    }
    return $out;
}

/**
 * Le immagini senza alt di un contenuto (la riga del database con struttura e testo): nei widget dell'editor (la versione
 * pubblicata), nel testo della scheda (<img> senza alt, solo se non c'è l'editor) e l'immagine in evidenza degli articoli.
 * Ogni voce: immagine, dove (il nome del widget o "Testo della scheda" o "Immagine in evidenza"), widget_id (per l'editor).
 */
function media_alt_contenuto(string $tipo, array $r): array {
    $out = [];
    $t = contenuti_tipo($tipo);
    $struttura = trim((string)($r['struttura'] ?? ''));
    if ($struttura !== '') {
        $albero = editor_decodifica($struttura);
        foreach ($albero['elementi'] ?? [] as $s) foreach ($s['figli'] ?? [] as $c) foreach ($c['figli'] ?? [] as $w) {
            foreach (media_alt_widget($w) as $x) $out[] = ['immagine' => $x['immagine'], 'dove' => (string)(editor_definizioni()[$w['tipo']]['etichetta'] ?? $w['tipo']) . ($x['campo'] !== '' ? ' (' . $x['campo'] . ')' : ''), 'widget_id' => (string)($w['id'] ?? '')];
        }
    } else {
        $html = (string)($r[$t['campo_contenuto']] ?? '');
        if (($r['content_format'] ?? 'html') === 'markdown' && function_exists('markdown_to_html')) $html = markdown_to_html(preg_replace('/\[IG:[\s\S]*?\[\/IG\]/', ' ', $html));
        if (preg_match_all('~<img\b[^>]*>~i', $html, $imgs)) {
            foreach ($imgs[0] as $im) {
                if (preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $im) || !preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $im, $s)) continue;
                if (media_alt_di($s[1]) !== '') continue;
                $out[] = ['immagine' => html_entity_decode($s[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'dove' => 'Testo della scheda', 'widget_id' => ''];
            }
        }
    }
    if (!empty($t['attributi'])) {
        $ev = trim((string)($r['immagine_url'] ?: ($r['featured_image'] ?? '')));
        if ($ev !== '' && trim((string)($r['featured_image_alt'] ?? '')) === '' && media_alt_di($ev) === '') $out[] = ['immagine' => $ev, 'dove' => 'Immagine in evidenza', 'widget_id' => ''];
    }
    return $out;
}

/** Le immagini senza alt in tutti i contenuti pubblicati, per Media > Senza alt: tipo, id, titolo, percorso, editor_url, scheda_url, immagine, dove. */
function media_alt_nelle_pagine(): array {
    $out = [];
    foreach (contenuti_tipi() as $tipo => $t) {
        $w = contenuti_sql_stato($tipo, 'pubblicato');
        try { $righe = db()->query('SELECT * FROM ' . tbl($t['tabella']) . " WHERE $w")->fetchAll(); } catch (Throwable $e) { error_log('media_alt_nelle_pagine: ' . $e->getMessage()); continue; }
        foreach ($righe as $r) {
            foreach (media_alt_contenuto($tipo, $r) as $x) {
                $out[] = $x + ['tipo' => $tipo, 'id' => (int)$r['id'], 'titolo' => (string)$r['title'], 'percorso' => contenuti_percorso($tipo, $r),
                    'editor_url' => trim((string)($r['struttura'] ?? '')) !== '' ? 'editor.php?tipo=' . $tipo . '&id=' . (int)$r['id'] : '', 'scheda_url' => 'contenuto.php?tipo=' . $tipo . '&id=' . (int)$r['id']];
            }
        }
    }
    return $out;
}
