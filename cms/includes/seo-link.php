<?php
/**
 * 2.29.0: i link interni suggeriti ("questo articolo parla di X, hai tre pagine su X: collegale").
 *
 * Dalla parola chiave del contenuto e dalle parole del suo titolo cerca fra gli altri contenuti pubblicati (titolo, parola
 * chiave, riassunto, indirizzo) e propone fino a SEO_LINK_MAX pagine o articoli. L'indice è leggero: una lettura per
 * richiesta di sito_contenuti_elenco('cerca') (i contenuti pubblici, senza i riservati "Solo per i membri" e senza
 * quelli rediretti), niente tabelle nuove. Lo usa seo_analisi() (includes/seo.php): la voce "link_interni" dice quante
 * pagine parlano di X e diventa verde quando almeno una delle suggerite è già collegata nel testo.
 */

const SEO_LINK_MAX = 5;
const SEO_LINK_MIN_PUNTI = 2;
const SEO_LINK_STOP = ['di', 'da', 'del', 'della', 'delle', 'dei', 'degli', 'dello', 'dal', 'dalla', 'dalle', 'dai', 'il', 'la', 'le', 'lo', 'li', 'gli', 'un', 'una', 'uno', 'e', 'ed', 'a', 'al', 'alla', 'alle', 'ai', 'agli', 'allo',
    'in', 'nel', 'nella', 'nelle', 'nei', 'negli', 'nello', 'con', 'per', 'come', 'che', 'chi', 'non', 'si', 'se', 'o', 'ma', 'su', 'sul', 'sulla', 'sulle', 'sui', 'tra', 'fra', 'piu', 'meno', 'cosa', 'come', 'quando', 'dove',
    'perche', 'tuo', 'tua', 'tuoi', 'tue', 'suo', 'sua', 'suoi', 'sue', 'mio', 'mia', 'nostro', 'nostra', 'nostri', 'nostre', 'vostro', 'vostra', 'questo', 'questa', 'questi', 'queste', 'quello', 'quella', 'anche', 'ogni', 'tutto', 'tutti',
    'tutte', 'senza', 'sono', 'essere', 'fare', 'fai', 'nuovo', 'nuova', 'nuovi', 'nuove', 'the', 'and', 'for', 'with', 'blog', 'home', 'sito', 'pagina', 'articolo'];

/** Le parole significative di una frase, normalizzate (seo_norm), senza quelle vuote, almeno tre lettere, senza doppioni. */
function seo_link_termini(string $frase): array {
    $out = [];
    foreach (seo_parole(seo_norm($frase)) as $p) {
        if (mb_strlen($p) < 3 || in_array($p, SEO_LINK_STOP, true) || in_array($p, $out, true) || ctype_digit($p)) continue;
        $out[] = $p;
    }
    return $out;
}

/** Il tipo di contenuto (chiave di contenuti_tipi) di una voce di sito_contenuti(): articoli, pagine, o il tipo del modulo con quella tabella. */
function seo_link_tipo_di(array $c): string {
    $fonte = (string)($c['fonte'] ?? '');
    if ($fonte === 'articles') return 'articoli';
    if ($fonte === 'pages') return 'pagine';
    foreach (contenuti_tipi() as $k => $t) if (($t['tabella'] ?? '') === $fonte) return (string)$k;
    return '';
}

/**
 * L'indice dei contenuti pubblicati che si possono suggerire: tipo, id, titolo, percorso, e i campi normalizzati dove cercare
 * (titolo, parola chiave, indirizzo, riassunto). Una volta per richiesta. Fuori: la home, la pagina del blog, i rediretti,
 * il glossario (le sue voci sono noindex) e i contenuti che un addon toglie dagli elenchi (l'Area riservata).
 */
function seo_link_indice(): array {
    static $indice = null;
    if ($indice !== null) return $indice;
    $indice = [];
    if (!function_exists('sito_contenuti_elenco')) return $indice;
    try { $tutti = sito_contenuti_elenco('cerca'); } catch (Throwable $e) { error_log('seo_link_indice: ' . $e->getMessage()); return $indice; }
    $blog = defined('SITO_PAGINA_BLOG') ? SITO_PAGINA_BLOG : '/blog/';
    foreach ($tutti as $c) {
        if (!empty($c['rediretto']) || ($c['tipo'] ?? '') === 'glossario') continue;
        $percorso = (string)($c['percorso'] ?? '');
        if ($percorso === '' || $percorso === '/' || $percorso === $blog) continue;
        $tipo = seo_link_tipo_di($c);
        if ($tipo === '') continue;
        $kw = (string)($c['seo']['keyword'] ?? '');
        $riassunto = trim((string)($c['estratto'] ?? '') . ' ' . (string)($c['tldr'] ?? '') . ' ' . (string)($c['seo']['descrizione'] ?? ''));
        $indice[] = [
            'tipo' => $tipo, 'id' => (int)($c['riga']['id'] ?? 0), 'titolo' => (string)$c['titolo'], 'percorso' => $percorso, 'slug' => (string)($c['slug'] ?? ''),
            'n_titolo' => ' ' . seo_norm((string)$c['titolo']) . ' ', 'n_kw' => ' ' . seo_norm($kw) . ' ', 'n_slug' => ' ' . str_replace('-', ' ', (string)($c['slug'] ?? '')) . ' ',
            'n_riassunto' => ' ' . seo_norm(mb_substr($riassunto, 0, 600)) . ' ',
        ];
    }
    return $indice;
}

/** La parola (normalizzata) c'è in un campo normalizzato, all'inizio di una parola ("vaso" trova "vaso" e "vasoio", non "travaso"). */
function seo_link_trova(string $campo, string $parola): bool {
    return $parola !== '' && str_contains($campo, ' ' . $parola);
}

/** L'indirizzo (/percorso/) è già collegato nell'HTML del testo? Con o senza la cartella del sito, relativo o assoluto. */
function seo_link_collegato(string $html, string $percorso): bool {
    $p = rtrim($percorso, '/');
    if ($p === '' || $html === '') return false;
    return (bool)preg_match('~href\s*=\s*["\'](?:https?://[^"\'/]+)?(?:/[a-z0-9_-]+)?' . preg_quote($p, '~') . '/?(?:[?#][^"\']*)?["\']~i', $html);
}

/**
 * I suggerimenti per un contenuto. $tipo e $id dicono quale contenuto è (per escluderlo); con $id 0 si esclude per indirizzo
 * ($slug). $html è il testo del contenuto (per sapere quali suggeriti sono già collegati), $keyword la parola chiave,
 * $titolo il titolo. Ritorna ['parola' => X, 'voci' => [...], 'collegati' => n, 'da_collegare' => n]: ogni voce ha tipo, id,
 * titolo, percorso, url (intero, da copiare), punti, collegato.
 */
function seo_link_suggeriti(string $tipo, int $id, string $html, string $keyword, string $titolo = '', string $slug = ''): array {
    $vuoto = ['parola' => '', 'voci' => [], 'collegati' => 0, 'da_collegare' => 0];
    $kwNorm = seo_norm($keyword);
    $termini = array_values(array_unique(array_merge(seo_link_termini($keyword), seo_link_termini($titolo))));
    if (!$termini) return $vuoto;
    $punteggi = [];
    $conta = []; // quante voci suggerite contengono ogni termine: serve a scegliere la parola X quando non c'è una keyword
    foreach (seo_link_indice() as $v) {
        // Il contenuto stesso non si suggerisce: per tipo e id, oppure per indirizzo (la scheda classica prima di Ricontrolla non manda l'id).
        $stesso = ($tipo !== '' && $v['tipo'] === $tipo && $id > 0) ? $v['id'] === $id : ($slug !== '' && $v['slug'] === $slug && ($tipo === '' || $v['tipo'] === $tipo));
        if ($stesso) continue;
        $punti = 0; $miei = [];
        foreach ($termini as $t) {
            $p = 0;
            if (seo_link_trova($v['n_kw'], $t)) $p += 3;
            if (seo_link_trova($v['n_titolo'], $t)) $p += 2;
            if (seo_link_trova($v['n_slug'], $t)) $p += 1;
            if (seo_link_trova($v['n_riassunto'], $t)) $p += 1;
            if ($p > 0) { $punti += $p; $miei[] = $t; }
        }
        if ($kwNorm !== '') {
            if (trim($v['n_kw']) === $kwNorm) $punti += 4;
            if (str_contains($v['n_titolo'], ' ' . $kwNorm)) $punti += 4;
        }
        if ($punti < SEO_LINK_MIN_PUNTI) continue;
        $punteggi[] = ['punti' => $punti, 'termini' => $miei, 'voce' => $v];
    }
    if (!$punteggi) return $vuoto;
    usort($punteggi, fn($a, $b) => $b['punti'] <=> $a['punti'] ?: strcmp($a['voce']['titolo'], $b['voce']['titolo']));
    $punteggi = array_slice($punteggi, 0, SEO_LINK_MAX);
    $base = function_exists('cms_url_sito') ? cms_url_sito() : '';
    $voci = []; $collegati = 0;
    foreach ($punteggi as $x) {
        $v = $x['voce'];
        $c = seo_link_collegato($html, $v['percorso']);
        if ($c) $collegati++;
        foreach ($x['termini'] as $t) $conta[$t] = ($conta[$t] ?? 0) + 1;
        $voci[] = ['tipo' => $v['tipo'], 'id' => $v['id'], 'titolo' => $v['titolo'], 'percorso' => $v['percorso'], 'url' => $base . $v['percorso'], 'punti' => $x['punti'], 'collegato' => $c];
    }
    $parola = trim($keyword);
    if ($parola === '' && $conta) { arsort($conta); $parola = (string)array_key_first($conta); }
    return ['parola' => $parola, 'voci' => $voci, 'collegati' => $collegati, 'da_collegare' => count($voci) - $collegati];
}
