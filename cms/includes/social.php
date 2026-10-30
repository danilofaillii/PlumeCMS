<?php
/**
 * Social e email del mattino (1.5.0): funzioni del CMS, per qualunque sito. Prima stavano nel modulo di danilofailli.it.
 *
 * Tre cose:
 *   1. I testi social di ogni contenuto (scheda del contenuto, riquadro Social): un testo, e se serve uno diverso per
 *      LinkedIn, per Facebook e Instagram, per WhatsApp e Telegram. Con il link al contenuto e gli hashtag di Social >
 *      Impostazioni. Tabella {prefisso}social_contenuti. Senza testo si usa l'estratto (o In breve, o la descrizione).
 *   2. Le due immagini per i social, ritagliate con GD dall'immagine in evidenza: 1200x630 per LinkedIn e Facebook,
 *      1080x1350 per Instagram. In uploads/social/, con un nome che cambia quando cambia la foto.
 *   3. Due email del cron del mattino, all'indirizzo degli avvisi (Impostazioni > Configurazione email):
 *        "Oggi sul sito <dominio>": i contenuti che escono oggi, ognuno con le due immagini, i testi pronti per ogni
 *        canale e i pulsanti per condividerli;
 *        "Social di oggi": i testi del calendario (Social > Calendario) con la data di oggi e quelli rimasti indietro,
 *        ognuno con le sue immagini. Dopo l'invio la riga è segnata come inviata e non riparte.
 *
 * Un modulo cambia i testi con il filtro 'social_testi' (array dei testi, riga del contenuto, tipo).
 * I testi possono avere emoji: si salvano come entità (&#x1F600;, colonne a 3 byte) e tornano emoji quando si copiano.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const SOCIAL_CANALI = ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'whatsapp' => 'WhatsApp e Telegram', 'substack' => 'Substack'];
// I colori dei canali, un po' più scuri di quelli dei marchi dove serve: sul bianco, e con il testo bianco sopra, ognuno
// passa 4,5 a 1 (Facebook #1877F2 faceva 4,2, WhatsApp #128C7E 4,1, Telegram #229ED9 3,0).
const SOCIAL_COLORI = ['linkedin' => '#0A66C2', 'facebook' => '#1465D8', 'instagram' => '#C13584', 'whatsapp' => '#0E7A6E', 'substack' => '#B8461A', 'telegram' => '#177BB0'];
const SOCIAL_OG = [1200, 630];
const SOCIAL_IG = [1080, 1350];
const SOCIAL_TESTO_MAX = 4000;

function social_installa(): void {
    static $fatto = false;
    if ($fatto) return;
    $fatto = true;
    $sql = ['CREATE TABLE IF NOT EXISTS ' . tbl('social_contenuti') . " (
        `tipo` VARCHAR(40) NOT NULL,
        `contenuto_id` INT NOT NULL,
        `testo` TEXT,
        `linkedin` TEXT,
        `facebook` TEXT,
        `whatsapp` TEXT,
        `aggiornato` DATETIME DEFAULT NULL,
        PRIMARY KEY (`tipo`, `contenuto_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS ' . tbl('social_programmati') . " (
        `id` INT NOT NULL AUTO_INCREMENT,
        `data_invio` DATE NOT NULL,
        `tipo` VARCHAR(40) NOT NULL DEFAULT 'articoli',
        `contenuto_id` INT NOT NULL DEFAULT 0,
        `titolo` VARCHAR(255) NOT NULL DEFAULT '',
        `ancora` VARCHAR(120) NOT NULL DEFAULT '',
        `linkedin` TEXT,
        `facebook` TEXT,
        `whatsapp` TEXT,
        `immagine_og` VARCHAR(500) NOT NULL DEFAULT '',
        `immagine_ig` VARCHAR(500) NOT NULL DEFAULT '',
        `inviato_at` DATETIME DEFAULT NULL,
        `creato` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `data_invio` (`data_invio`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];
    $impronta = md5(implode('|', $sql));
    if (get_setting('social_schema') === $impronta) return;
    foreach ($sql as $q) db()->exec($q);
    save_setting('social_schema', $impronta);
    try { social_porta_dati_vecchi(); } catch (Throwable $e) { error_log('social: dati di prima non portati: ' . $e->getMessage()); }
}

/**
 * Una volta sola, alla nascita delle tabelle: i dati del pannello da cui è nato plumeCMS, se ci sono. La colonna
 * social_summary degli articoli diventa il "Testo per i social"; la tabella social_pillar (i sette testi di ogni pillar)
 * diventa il calendario, con le immagini e lo stato di invio. Senza quei dati non fa niente.
 */
function social_porta_dati_vecchi(): void {
    if (get_setting('social_dati_vecchi') === '1') return;
    save_setting('social_dati_vecchi', '1');
    $art = tbl('articles');
    $col = db()->query("SHOW COLUMNS FROM $art LIKE 'social_summary'")->fetch();
    if ($col) {
        db()->exec('INSERT IGNORE INTO ' . tbl('social_contenuti') . " (tipo, contenuto_id, testo, linkedin, facebook, whatsapp, aggiornato)
            SELECT 'articoli', id, social_summary, '', '', '', NOW() FROM $art WHERE social_summary IS NOT NULL AND TRIM(social_summary) <> ''");
    }
    $vecchia = tbl('social_pillar');
    try { db()->query("SELECT 1 FROM $vecchia LIMIT 1"); } catch (Throwable $e) { return; } // la tabella di prima non c'è
    if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl('social_programmati'))->fetchColumn() > 0) return;
    $id = db()->prepare("SELECT id FROM $art WHERE slug = ?");
    $nuova = db()->prepare('INSERT INTO ' . tbl('social_programmati') . " (data_invio, tipo, contenuto_id, titolo, ancora, linkedin, facebook, whatsapp, immagine_og, immagine_ig, inviato_at, creato)
        VALUES (?, 'articoli', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $img = fn($v) => trim((string)$v) === '' ? '' : (preg_match('~^(https?://|/)~', (string)$v) ? (string)$v : cms_uploads_url() . '/' . ltrim((string)$v, '/'));
    foreach (db()->query("SELECT * FROM $vecchia ORDER BY data_invio, giorno")->fetchAll() as $r) {
        $id->execute([(string)$r['slug']]);
        $cid = (int)$id->fetchColumn();
        if (!$cid) continue;
        $g = (int)($r['giorno'] ?? 0);
        $titolo = $g === 1 ? 'Giorno 1 di 7: esce ' . ($r['titolo_pillar'] ?? '') : 'Giorno ' . $g . ' di 7: ' . ($r['titolo_sezione'] ?? '');
        $nuova->execute([(string)$r['data_invio'], $cid, mb_substr($titolo, 0, 255), (string)($r['ancora'] ?? ''), (string)($r['linkedin'] ?? ''),
            (string)($r['facebook_instagram'] ?? ($r['instagram'] ?? '')), (string)($r['whatsapp'] ?? ''), $img($r['immagine_og'] ?? ''), $img($r['immagine_ig'] ?? ''),
            $r['inviato_at'] ?? null]);
    }
}

/** I caratteri a 4 byte (emoji) diventano entità: le colonne del database sono a 3 byte (come sito_senza_4byte). */
function social_senza_4byte(string $s): string {
    return (string)preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', fn($m) => sprintf('&#x%X;', mb_ord($m[0], 'UTF-8')), $s);
}

// ------------------------------------------------------------ impostazioni

function social_impostazioni(): array {
    $v = json_decode((string)get_setting('social', ''), true);
    $v = is_array($v) ? $v : [];
    return $v + [
        'canali' => ['linkedin' => 1, 'facebook' => 1, 'instagram' => 1, 'whatsapp' => 1, 'substack' => 0],
        'hashtag' => ['linkedin' => '', 'facebook' => '', 'instagram' => ''],
        'hashtag_categorie' => '',      // una riga per categoria: "burnout: #burnout #lavoro"
        'whatsapp_link' => '',          // un link in più in coda al testo di WhatsApp (per esempio una prenotazione)
        'whatsapp_etichetta' => '',
        'email_mattino' => 1,
        'social_giorno' => 1,
    ];
}

/** Salva quello che arriva dal modulo di Social > Impostazioni. Ritorna le impostazioni salvate. */
function social_impostazioni_salva(array $p): array {
    $tag = fn($s) => mb_substr(trim(preg_replace('/\s+/u', ' ', social_senza_4byte((string)$s))), 0, 500);
    $link = trim((string)($p['whatsapp_link'] ?? ''));
    if ($link !== '' && !preg_match('~^(https?://|/)~i', $link)) throw new RuntimeException('Il link di WhatsApp comincia con https:// oppure con / (una pagina del sito).');
    $v = [
        'canali' => [],
        'hashtag' => [],
        'hashtag_categorie' => mb_substr(social_senza_4byte(str_replace("\r", '', (string)($p['hashtag_categorie'] ?? ''))), 0, 5000),
        'whatsapp_link' => mb_substr($link, 0, 500),
        'whatsapp_etichetta' => mb_substr(trim(social_senza_4byte((string)($p['whatsapp_etichetta'] ?? ''))), 0, 120),
        'email_mattino' => empty($p['email_mattino']) ? 0 : 1,
        'social_giorno' => empty($p['social_giorno']) ? 0 : 1,
    ];
    foreach (SOCIAL_CANALI as $k => $_) $v['canali'][$k] = empty($p['canale'][$k]) ? 0 : 1;
    foreach (['linkedin', 'facebook', 'instagram'] as $k) $v['hashtag'][$k] = $tag($p['hashtag'][$k] ?? '');
    save_setting('social', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return social_impostazioni();
}

/** Gli hashtag di una categoria, da "categoria: #a #b" (una riga per categoria; la categoria è lo slug o il nome). */
function social_hashtag_categoria(string $categoria): string {
    if ($categoria === '') return '';
    foreach (explode("\n", social_impostazioni()['hashtag_categorie']) as $riga) {
        if (!str_contains($riga, ':')) continue;
        [$c, $t] = array_map('trim', explode(':', $riga, 2));
        if ($c !== '' && (strcasecmp($c, $categoria) === 0 || (function_exists('contenuti_slug') && contenuti_slug($c) === $categoria))) return $t;
    }
    return '';
}

// ------------------------------------------------------------ testi

/** Le emoji tornano emoji: i testi si salvano con le entità (&#x1F600;), qui diventano di nuovo caratteri. */
function social_testo_vero(string $s): string {
    return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function social_pulisci(string $s): string {
    return mb_substr(trim(str_replace("\r", '', social_senza_4byte($s))), 0, SOCIAL_TESTO_MAX);
}

/** I testi scritti per un contenuto (vuoti se non c'è niente). */
function social_contenuto(string $tipo, int $id): array {
    social_installa();
    $st = db()->prepare('SELECT * FROM ' . tbl('social_contenuti') . ' WHERE tipo = ? AND contenuto_id = ?');
    $st->execute([$tipo, $id]);
    $r = $st->fetch() ?: [];
    return ['testo' => (string)($r['testo'] ?? ''), 'linkedin' => (string)($r['linkedin'] ?? ''), 'facebook' => (string)($r['facebook'] ?? ''), 'whatsapp' => (string)($r['whatsapp'] ?? '')];
}

/** Salva i testi del riquadro Social della scheda (campi social_testo, social_linkedin, social_facebook, social_whatsapp). */
function social_contenuto_salva(string $tipo, int $id, array $p): void {
    social_installa();
    $v = [];
    foreach (['testo', 'linkedin', 'facebook', 'whatsapp'] as $k) $v[$k] = social_pulisci((string)($p['social_' . $k] ?? ''));
    db()->prepare('INSERT INTO ' . tbl('social_contenuti') . ' (tipo, contenuto_id, testo, linkedin, facebook, whatsapp, aggiornato) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE testo = VALUES(testo), linkedin = VALUES(linkedin), facebook = VALUES(facebook), whatsapp = VALUES(whatsapp), aggiornato = NOW()')
        ->execute([$tipo, $id, $v['testo'], $v['linkedin'], $v['facebook'], $v['whatsapp']]);
}

/** L'indirizzo completo di un contenuto, con l'ancora se c'è. */
function social_url(string $tipo, array $c, string $ancora = ''): string {
    require_once __DIR__ . '/contenuti.php';
    $u = cms_url_sito() . contenuti_percorso($tipo, $c);
    $ancora = trim($ancora, "# \t");
    return $ancora !== '' ? $u . '#' . rawurlencode($ancora) : $u;
}

/**
 * I testi pronti da incollare, uno per canale acceso: il testo (o quello del canale), il link, gli hashtag.
 * $sopra: testi che vincono su quelli del contenuto (le righe del calendario), chiavi linkedin, facebook, whatsapp.
 */
function social_testi(string $tipo, array $c, array $sopra = [], string $ancora = ''): array {
    $imp = social_impostazioni();
    $s = social_contenuto($tipo, (int)($c['id'] ?? 0));
    $base = trim($s['testo']);
    foreach (['excerpt', 'tldr', 'seo_description', 'meta_description', 'title'] as $k) {
        if ($base === '') $base = trim(strip_tags((string)($c[$k] ?? '')));
    }
    $per = fn(string $k) => trim((string)($sopra[$k] ?? '')) !== '' ? trim((string)$sopra[$k]) : (trim($s[$k] ?? '') !== '' ? trim($s[$k]) : $base);
    $url = social_url($tipo, $c, $ancora);
    $cat = social_hashtag_categoria((string)($c['category'] ?? ''));
    $tag = fn(string $k) => trim(trim($imp['hashtag'][$k] ?? '') . ' ' . $cat);
    $coda = fn(string $t) => $t !== '' ? "\n\n" . $t : '';
    $wa = $per('whatsapp') . "\n\n" . $url;
    if ($imp['whatsapp_link'] !== '') {
        $l = str_starts_with($imp['whatsapp_link'], '/') ? cms_url_sito() . $imp['whatsapp_link'] : $imp['whatsapp_link'];
        $wa .= "\n\n" . ($imp['whatsapp_etichetta'] !== '' ? $imp['whatsapp_etichetta'] . ' ' : '') . $l;
    }
    $tutti = [
        'linkedin' => $per('linkedin') . "\n\n" . $url . $coda($tag('linkedin')),
        'facebook' => $per('facebook') . "\n\n" . $url . $coda($tag('facebook')),
        'instagram' => $per('facebook') . "\n\n" . $url . $coda($tag('instagram')),
        'whatsapp' => $wa,
        'substack' => $base . "\n\nPuoi leggere tutto qui: " . $url,
    ];
    $tutti = array_map('social_testo_vero', $tutti);
    if (function_exists('cms_filtro')) $tutti = (array)cms_filtro('social_testi', $tutti, $c, $tipo);
    return array_intersect_key($tutti, array_filter($imp['canali']));
}

/** I pulsanti "condividi": dove portano, per canale (Instagram e Substack non hanno un indirizzo di condivisione). */
function social_condividi(string $url, array $testi): array {
    $out = [];
    if (isset($testi['linkedin'])) $out['linkedin'] = ['Condividi su LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($url)];
    if (isset($testi['facebook'])) $out['facebook'] = ['Condividi su Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url)];
    if (isset($testi['whatsapp'])) {
        $out['whatsapp'] = ['Invia su WhatsApp', 'https://wa.me/?text=' . rawurlencode($testi['whatsapp'])];
        $out['telegram'] = ['Invia su Telegram', 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($testi['whatsapp'])];
    }
    return $out;
}

// ------------------------------------------------------------ immagini

/** Un indirizzo del sito (/uploads/...) diventa completo (https://sito/uploads/...). */
function social_assoluto(string $u): string {
    if ($u === '' || preg_match('~^https?://~i', $u)) return $u;
    $p = parse_url(cms_url_sito());
    $origine = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    return $origine . '/' . ltrim($u, '/');
}

/** Per il pannello: un indirizzo completo del sito torna relativo (/uploads/...), così si vede anche aprendo il pannello da un altro indirizzo. */
function social_relativo(string $u): string {
    $sito = social_assoluto('/');
    return str_starts_with($u, $sito) ? '/' . substr($u, strlen($sito)) : $u;
}

/** Il file sul disco di un'immagine caricata nel sito, o '' se viene da fuori o non c'è. */
function social_file_di(string $u): string {
    $u = (string)parse_url($u, PHP_URL_PATH);
    $base = cms_uploads_url();
    if ($u === '' || !str_starts_with($u, $base . '/') || str_contains($u, '..')) return '';
    $f = cms_uploads_dir() . substr(rawurldecode($u), strlen($base));
    return is_file($f) ? $f : '';
}

/** Ritaglia $file a $l x $a (riempie e taglia al centro) e lo scrive in JPEG. False se GD non c'è o la foto non si legge. */
function social_ritaglia(string $file, string $dest, int $l, int $a): bool {
    if (!function_exists('imagecreatetruecolor')) return false;
    $info = @getimagesize($file);
    if (!$info || $info[0] < 16 || $info[1] < 16 || $info[0] * $info[1] > 40000000) return false;
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
        IMAGETYPE_PNG => @imagecreatefrompng($file),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
        IMAGETYPE_GIF => @imagecreatefromgif($file),
        default => false,
    };
    if (!$src) return false;
    [$sl, $sa] = [$info[0], $info[1]];
    $scala = max($l / $sl, $a / $sa);
    $ll = (int)ceil($l / $scala); $aa = (int)ceil($a / $scala);
    $x = max(0, (int)(($sl - $ll) / 2)); $y = max(0, (int)(($sa - $aa) / 2));
    $out = imagecreatetruecolor($l, $a);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $src, 0, 0, $x, $y, $l, $a, min($ll, $sl), min($aa, $sa));
    @mkdir(dirname($dest), 0755, true);
    $ok = imagejpeg($out, $dest, 86);
    imagedestroy($out); imagedestroy($src);
    if ($ok) @chmod($dest, 0644);
    return $ok;
}

/**
 * Le due immagini per i social di un'immagine del sito: ['og' => indirizzo completo, 'ig' => ...], vuote se non si può.
 * Si fanno una volta: il nome porta l'impronta del file di partenza (percorso e data), una foto nuova fa file nuovi.
 */
function social_immagini_da(string $immagine, string $nome): array {
    $vuote = ['og' => '', 'ig' => ''];
    $file = social_file_di($immagine);
    if ($file === '') return $immagine !== '' ? ['og' => social_assoluto($immagine), 'ig' => social_assoluto($immagine)] : $vuote;
    $impronta = substr(md5($file . '|' . filemtime($file) . '|' . filesize($file)), 0, 10);
    $nome = preg_replace('/[^a-z0-9-]+/', '-', strtolower($nome));
    $out = $vuote;
    foreach (['og' => SOCIAL_OG, 'ig' => SOCIAL_IG] as $k => [$l, $a]) {
        $rel = "/social/$nome-$impronta-$k.jpg";
        $dest = cms_uploads_dir() . $rel;
        if (!is_file($dest) && !social_ritaglia($file, $dest, $l, $a)) return ['og' => social_assoluto($immagine), 'ig' => social_assoluto($immagine)];
        $out[$k] = social_assoluto(cms_uploads_url() . $rel);
    }
    return $out;
}

/** Le immagini di un contenuto, dalla sua immagine in evidenza. */
function social_immagini(string $tipo, array $c): array {
    $img = trim((string)($c['featured_image'] ?? '')) ?: trim((string)($c['immagine_url'] ?? ''));
    return social_immagini_da($img, $tipo . '-' . (int)($c['id'] ?? 0));
}

// ------------------------------------------------------------ email

function social_dominio(): string {
    $h = (string)parse_url(cms_url_sito(), PHP_URL_HOST);
    $p = (string)parse_url(cms_url_sito(), PHP_URL_PATH);
    return preg_replace('/^www\./', '', $h) . rtrim($p, '/');
}

/** Il blocco HTML di un contenuto nell'email: titolo, immagini da scaricare, un riquadro per ogni testo, i pulsanti. */
function social_email_blocco(string $titolo, string $sopra, string $url, array $img, array $testi): string {
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $btn = fn($href, $etichetta, $colore, $scarica = false) => '<a href="' . $h($href) . '"' . ($scarica ? ' download' : ' target="_blank"')
        . ' style="display:inline-block;background:' . $colore . ';color:#ffffff;font-size:13px;font-weight:600;padding:7px 12px;border-radius:6px;text-decoration:none;margin:6px 6px 0 0">' . $h($etichetta) . '</a>';
    $o = ($sopra !== '' ? '<p style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#5b6475;font-weight:700;margin:0 0 4px">' . $h($sopra) . '</p>' : '')
        . '<h2 style="font-size:19px;margin:0 0 4px"><a href="' . $h($url) . '" style="color:#1d4ed8;text-decoration:none">' . $h($titolo) . '</a></h2>'
        . '<p style="margin:0 0 14px;font-size:13px"><a href="' . $h($url) . '" style="color:#5b6475;word-break:break-all">' . $h($url) . '</a></p>';
    if ($img['og'] !== '' || $img['ig'] !== '') {
        $o .= '<div style="margin:0 0 16px;padding:12px;background:#f6f7fb;border:1px solid #e3e6ee;border-radius:8px">';
        if ($img['og'] !== '') $o .= '<img src="' . $h($img['og']) . '" alt="" width="560" style="width:100%;max-width:560px;height:auto;border-radius:6px;display:block">'
            . $btn($img['og'], 'Scarica l\'immagine per LinkedIn e Facebook (1200x630)', '#0A66C2', true);
        if ($img['ig'] !== '') $o .= '<img src="' . $h($img['ig']) . '" alt="" width="160" style="width:160px;height:auto;border-radius:6px;display:block;margin-top:12px">'
            . $btn($img['ig'], 'Scarica l\'immagine per Instagram (1080x1350)', '#C13584', true);
        $o .= '<p style="font-size:12px;color:#6b7280;margin:8px 0 0">Se il pulsante apre l\'immagine invece di scaricarla: tasto destro, "Salva immagine".</p></div>';
    }
    $condividi = social_condividi($url, $testi);
    foreach ($testi as $k => $t) {
        $colore = SOCIAL_COLORI[$k] ?? '#374151';
        $o .= '<div style="margin:0 0 16px"><p style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:' . $colore . ';font-weight:700;margin:0 0 4px">' . $h(SOCIAL_CANALI[$k] ?? $k) . '</p>'
            . '<div style="background:#f6f7fb;border:1px solid #e3e6ee;border-radius:8px;padding:12px;font-size:14px;line-height:1.55;white-space:pre-wrap">' . $h($t) . '</div>';
        foreach (['linkedin' => ['linkedin'], 'facebook' => ['facebook'], 'whatsapp' => ['whatsapp', 'telegram']][$k] ?? [] as $c) {
            if (isset($condividi[$c])) $o .= $btn($condividi[$c][1], $condividi[$c][0], $c === 'telegram' ? SOCIAL_COLORI['telegram'] : $colore);
        }
        $o .= '</div>';
    }
    return $o;
}

function social_email_pagina(string $titolo, string $corpo): string {
    $nome = htmlspecialchars((string)cms_impostazione('sito_nome', CMS_NOME), ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;padding:24px 12px;background:#eef0f5;font-family:Inter,Segoe UI,Helvetica,Arial,sans-serif;color:#1f2937">'
        . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e3e6ee;border-radius:12px;padding:24px">'
        . '<p style="font-size:12px;color:#6b7280;margin:0 0 6px">' . $nome . ' &middot; ' . date('d/m/Y') . '</p>'
        . '<h1 style="font-size:22px;margin:0 0 18px">' . htmlspecialchars($titolo, ENT_QUOTES, 'UTF-8') . '</h1>' . $corpo
        . '<p style="font-size:12px;color:#6b7280;margin:18px 0 0">Copia ogni testo nel suo canale, con la sua immagine. Per cambiare i testi: scheda del contenuto, riquadro Social.</p>'
        . '</div></body></html>';
}

function social_invia(string $a, string $oggetto, string $html): bool {
    require_once __DIR__ . '/posta.php';
    $nome = str_replace(['"', "\r", "\n"], '', (string)cms_impostazione('sito_nome', CMS_NOME));
    $mitt = (string)(posta_config()['mittente'] ?? '');
    $intest = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n" . ($mitt !== '' ? 'From: ' . posta_oggetto($nome) . " <$mitt>\r\n" : '');
    return posta_invia($a, posta_oggetto($oggetto), $html, $intest, 'social');
}

/** I contenuti da leggere (non le pagine) che escono il giorno $data: [[tipo, riga], ...], in ordine di ora. */
function social_in_uscita(string $data): array {
    require_once __DIR__ . '/contenuti.php';
    $out = [];
    foreach (contenuti_tipi() as $tipo => $t) {
        if ($tipo === 'pagine') continue;
        try {
            $st = db()->prepare('SELECT * FROM ' . tbl($t['tabella']) . " WHERE status <> 'draft' AND cestino IS NULL AND publish_date = ? ORDER BY publish_time, id");
            $st->execute([$data]);
            foreach ($st->fetchAll() as $r) $out[] = [$tipo, $r];
        } catch (Throwable $e) {
            // un tipo di un modulo senza le colonne di un articolo: non esce nell'email
        }
    }
    usort($out, fn($a, $b) => strcmp((string)$a[1]['publish_time'], (string)$b[1]['publish_time']));
    return $out;
}

/** L'email "Oggi sul sito": [oggetto, html] per il giorno $data, o null se quel giorno non esce niente. */
function social_email_mattino(string $data): ?array {
    $uscite = social_in_uscita($data);
    if (!$uscite) return null;
    $n = count($uscite);
    $tipi = contenuti_tipi();
    // "1 articolo", "3 articoli"; con tipi diversi (un modulo ne aggiunge) "contenuti".
    $soli = array_unique(array_column($uscite, 0));
    $t = count($soli) === 1 ? ($tipi[$soli[0]] ?? []) : [];
    [$uno, $tanti, $f] = $t ? [$t['uno'], $t['tanti'], ($t['genere'] ?? (str_ends_with($t['uno'], 'a') ? 'f' : 'm')) === 'f'] : ['contenuto', 'contenuti', false];
    $oggetto = 'Oggi sul sito ' . social_dominio() . ': ' . ($n === 1 ? "1 $uno" : "$n $tanti");
    $titolo = $n === 1 ? 'Oggi esce ' . ($f ? 'una ' : 'un ') . $uno : "Oggi escono $n $tanti";
    $corpo = '';
    foreach ($uscite as $i => [$tipo, $c]) {
        if ($i) $corpo .= '<hr style="border:0;border-top:2px solid #e3e6ee;margin:24px 0">';
        $sopra = ($tipi[$tipo]['Uno'] ?? 'Contenuto') . ', esce alle ' . substr((string)$c['publish_time'], 0, 5);
        $corpo .= social_email_blocco((string)$c['title'], $sopra, social_url($tipo, $c), social_immagini($tipo, $c), social_testi($tipo, $c));
    }
    return [$oggetto, social_email_pagina($titolo, $corpo)];
}

// ------------------------------------------------------------ calendario

function social_programmati(?string $da = null, int $quanti = 200): array {
    social_installa();
    $st = db()->prepare('SELECT * FROM ' . tbl('social_programmati') . ' WHERE data_invio >= ? ORDER BY data_invio, id LIMIT ' . max(1, min(1000, $quanti)));
    $st->execute([$da ?? date('Y-m-d', strtotime('-7 days'))]);
    return $st->fetchAll();
}

function social_programmato(int $id): ?array {
    social_installa();
    $st = db()->prepare('SELECT * FROM ' . tbl('social_programmati') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Salva una riga del calendario (id 0 = nuova). Ritorna l'id. */
function social_programmato_salva(int $id, array $p): int {
    social_installa();
    require_once __DIR__ . '/contenuti.php';
    $data = (string)($p['data_invio'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !strtotime($data)) throw new RuntimeException('Scegli il giorno in cui arriva il testo.');
    [$tipo, $cid] = array_pad(explode(':', (string)($p['contenuto'] ?? ''), 2), 2, '');
    $cid = ctype_digit($cid) ? (int)$cid : 0; // solo cifre: "1 OR 1=1" non diventa l'articolo 1
    if (!isset(contenuti_tipi()[$tipo]) || $cid <= 0 || !contenuti_leggi($tipo, $cid)) throw new RuntimeException('Scegli il contenuto a cui porta il testo.');
    $img = function (string $k) use ($p): string {
        $u = trim((string)($p[$k] ?? ''));
        if ($u !== '' && !preg_match('~^(https://|/)~i', $u)) throw new RuntimeException('Le immagini sono indirizzi che cominciano con / (dalla libreria Media) o con https://.');
        return mb_substr($u, 0, 500);
    };
    $v = [
        'data_invio' => $data, 'tipo' => $tipo, 'contenuto_id' => $cid,
        'titolo' => mb_substr(trim(social_senza_4byte((string)($p['titolo'] ?? ''))), 0, 255),
        'ancora' => mb_substr(preg_replace('/[^A-Za-z0-9_-]+/', '', (string)($p['ancora'] ?? '')), 0, 120),
        'linkedin' => social_pulisci((string)($p['linkedin'] ?? '')), 'facebook' => social_pulisci((string)($p['facebook'] ?? '')), 'whatsapp' => social_pulisci((string)($p['whatsapp'] ?? '')),
        'immagine_og' => $img('immagine_og'), 'immagine_ig' => $img('immagine_ig'),
    ];
    if ($id > 0) {
        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($v)));
        db()->prepare('UPDATE ' . tbl('social_programmati') . " SET $set WHERE id = ?")->execute([...array_values($v), $id]);
        return $id;
    }
    $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($v)));
    db()->prepare('INSERT INTO ' . tbl('social_programmati') . " ($cols, creato) VALUES (" . implode(', ', array_fill(0, count($v), '?')) . ', NOW())')->execute(array_values($v));
    return (int)db()->lastInsertId();
}

/** L'email di una riga del calendario: [oggetto, html], o null se il contenuto non c'è più. */
function social_email_programmato(array $r): ?array {
    require_once __DIR__ . '/contenuti.php';
    if (!isset(contenuti_tipi()[$r['tipo']])) return null;
    $c = contenuti_leggi((string)$r['tipo'], (int)$r['contenuto_id']);
    if (!$c) return null;
    $titolo = trim((string)$r['titolo']) !== '' ? (string)$r['titolo'] : (string)$c['title'];
    $img = social_immagini((string)$r['tipo'], $c);
    foreach (['og' => 'immagine_og', 'ig' => 'immagine_ig'] as $k => $col) {
        if (trim((string)$r[$col]) !== '') $img[$k] = social_immagini_da((string)$r[$col], 'giorno-' . (int)$r['id'])[$k];
    }
    $testi = social_testi((string)$r['tipo'], $c, ['linkedin' => $r['linkedin'], 'facebook' => $r['facebook'], 'whatsapp' => $r['whatsapp']], (string)$r['ancora']);
    unset($testi['substack']);
    $sopra = 'Testo del ' . date('d/m/Y', strtotime((string)$r['data_invio'])) . ' per ' . $c['title'];
    $corpo = social_email_blocco(social_testo_vero($titolo), $sopra, social_url((string)$r['tipo'], $c, (string)$r['ancora']), $img, $testi);
    return ['Social di oggi: ' . social_testo_vero($titolo), social_email_pagina('Il testo social di oggi', $corpo)];
}

// ------------------------------------------------------------ cron

/**
 * Il passo del cron del mattino: l'email "Oggi sul sito" (una volta al giorno, anche se il cron gira due volte) e una
 * email per ogni testo del calendario con la data di oggi o prima e mai inviato. Ritorna le righe del log.
 */
function social_cron(bool $forza = false): array {
    social_installa();
    $imp = social_impostazioni();
    $a = '';
    try { require_once __DIR__ . '/posta.php'; $a = posta_notifica_email(); } catch (Throwable $e) {}
    $righe = [];
    $oggi = date('Y-m-d');
    if ($imp['email_mattino']) {
        $m = social_email_mattino($oggi);
        if (!$m) $righe[] = 'Email del mattino: oggi non esce niente';
        elseif ($a === '') $righe[] = 'Email del mattino: manca l\'indirizzo degli avvisi (Impostazioni > Configurazione email)';
        elseif (!$forza && get_setting('social_mattino_inviata') === $oggi) $righe[] = 'Email del mattino: già inviata oggi';
        elseif (social_invia($a, $m[0], $m[1])) { save_setting('social_mattino_inviata', $oggi); $righe[] = 'Email del mattino: inviata (' . $m[0] . ')'; }
        else $righe[] = 'ERRORE email del mattino: ' . (function_exists('posta_errore') ? posta_errore() : 'non partita');
    }
    if ($imp['social_giorno']) {
        $st = db()->prepare('SELECT * FROM ' . tbl('social_programmati') . ' WHERE data_invio <= ? AND inviato_at IS NULL ORDER BY data_invio, id LIMIT 30');
        $st->execute([$oggi]);
        $da = $st->fetchAll();
        if (!$da) $righe[] = 'Social di oggi: niente nel calendario';
        elseif ($a === '') $righe[] = 'Social di oggi: ' . count($da) . ' testi in attesa, manca l\'indirizzo degli avvisi';
        else {
            $segna = db()->prepare('UPDATE ' . tbl('social_programmati') . ' SET inviato_at = NOW() WHERE id = ?');
            foreach ($da as $r) {
                $e = social_email_programmato($r);
                if (!$e) { $segna->execute([$r['id']]); $righe[] = 'Social di oggi: il contenuto del testo ' . $r['id'] . ' non c\'è più, saltato'; continue; }
                if (social_invia($a, $e[0], $e[1])) { $segna->execute([$r['id']]); $righe[] = 'Social di oggi: inviato "' . social_testo_vero((string)($r['titolo'] ?: $r['id'])) . '" (' . $r['data_invio'] . ')'; }
                else $righe[] = 'ERRORE social di oggi, testo ' . $r['id'] . ': ' . posta_errore();
            }
        }
    }
    return $righe;
}
