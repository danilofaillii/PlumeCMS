<?php
/**
 * Kit di partenza (1.3.0, 27 ottobre 2026): all'ultimo passo dell'installatore si sceglie "Che sito vuoi fare?".
 * Vuoto e il sito di sempre (le pagine di partenza dell'installatore); gli altri kit costruiscono con l'editor la home,
 * le pagine principali e il menu dell'header, con testi d'esempio da sostituire.
 *
 * Ogni kit e un file di soli dati in includes/kit/<id>.php:
 *   'nome', 'descrizione', 'ordine'
 *   'home'     => [blocco, ...]           le sezioni della home
 *   'pagine'   => [['titolo', 'slug', 'sezioni' => [blocco, ...]], ...]
 *   'menu'     => [['etichetta', '/indirizzo/'], ...]   il menu Principale (header)
 *   'articoli' => [['titolo', 'slug', 'estratto', 'contenuto'], ...]   articoli d'esempio, oltre a quello di benvenuto
 *   'form'     => ['titolo' => ..., 'intro' => ...]   come si presenta il form dei contatti
 * Un blocco e una sezione nella forma corta di includes/sezioni-pronte.php, oppure ['pronta' => '<id>'] per una delle
 * sezioni pronte dell'editor, cosi com'e. Nei testi {sito} diventa il nome del sito e {descrizione} la sua descrizione;
 * nelle immagini {immagine:N} diventa l'indirizzo di un disegno astratto che il kit scrive in uploads/kit/ (niente
 * immagini da fuori: le disegna kit_immagini(), sono SVG semplici e si sostituiscono dalla libreria come ogni foto).
 *
 * Gli indirizzi dei link si scrivono dalla radice del sito (/contatti/): il sito li porta da solo nella cartella del CMS
 * quando sta in una sottocartella (cms_con_cartella), come per le pagine di partenza di sempre.
 */

// sezioni-pronte.php (e con lui l'editor) si carica solo per costruire: l'elenco dei kit serve all'installatore prima
// che esista il config.local.php, quando il motore non si puo ancora caricare.

/** I kit che ci sono: id => ['nome', 'descrizione']. "vuoto" e sempre il primo e non ha un file. */
function kit_elenco(): array {
    $out = ['vuoto' => ['nome' => 'Vuoto', 'descrizione' => 'Le pagine di sempre: una home di benvenuto, Chi sono, Blog e Contatti. Per chi vuole costruire da zero.', 'ordine' => 0]];
    foreach (glob(__DIR__ . '/kit/*.php') ?: [] as $f) {
        $id = basename($f, '.php');
        if (!preg_match('/^[a-z0-9-]{2,40}$/', $id) || $id === 'vuoto') continue;
        $k = kit_leggi($id);
        if ($k) $out[$id] = ['nome' => (string)$k['nome'], 'descrizione' => (string)($k['descrizione'] ?? ''), 'ordine' => (int)($k['ordine'] ?? 50)];
    }
    uasort($out, fn($a, $b) => $a['ordine'] <=> $b['ordine']);
    return $out;
}

/** I dati di un kit, o null. Un file rotto non ferma l'installatore: il kit semplicemente non c'e. */
function kit_leggi(string $id): ?array {
    if (!preg_match('/^[a-z0-9-]{2,40}$/', $id) || !is_file(__DIR__ . '/kit/' . $id . '.php')) return null;
    try { $k = require __DIR__ . '/kit/' . $id . '.php'; } catch (Throwable $e) { error_log('kit ' . $id . ': ' . $e->getMessage()); return null; }
    return is_array($k) && !empty($k['nome']) ? $k : null;
}

/** Sostituisce i segnaposto in tutte le stringhe di un array. */
function kit_sostituisci($x, array $mappa) {
    if (is_string($x)) return strtr($x, $mappa);
    if (is_array($x)) { foreach ($x as $k => $v) $x[$k] = kit_sostituisci($v, $mappa); }
    return $x;
}

/** I blocchi di un kit -> sezioni dell'editor validate. */
function kit_sezioni(array $blocchi, array $mappa): array {
    require_once __DIR__ . '/sezioni-pronte.php';
    $corte = [];
    $pronte = sezioni_pronte();
    foreach ($blocchi as $b) {
        if (isset($b['pronta'])) { foreach ((array)($pronte[$b['pronta']]['sezioni'] ?? []) as $s) $corte[] = $s; }
        else $corte[] = $b;
    }
    $albero = editor_valida(['elementi' => sezioni_pronte_costruisci(kit_sostituisci($corte, $mappa))]);
    return $albero ? $albero['elementi'] : [];
}

/**
 * Disegni astratti per le foto dei kit: sfondo sfumato, un alone, qualche forma geometrica e una trama di punti, nei
 * colori di partenza del sito. Uno per numero (1-8), scritti una volta sola in uploads/kit/. Ritorna numero => indirizzo.
 */
function kit_immagini(): array {
    $dir = cms_uploads_dir() . '/kit';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return [];
    $coppie = [['#1e1b4b', '#0e7490'], ['#3b0764', '#1d4ed8'], ['#0f172a', '#6d28d9'], ['#164e63', '#4c1d95'], ['#312e81', '#0891b2'], ['#1f2937', '#9333ea'], ['#0c4a6e', '#701a75'], ['#172554', '#0f766e']];
    $out = [];
    foreach ($coppie as $i => [$a, $b]) {
        $n = $i + 1;
        $file = $dir . '/disegno-' . $n . '.svg';
        if (!is_file($file)) {
            // Posizioni diverse per ogni disegno, ma sempre le stesse: due installazioni hanno gli stessi disegni.
            $cx = 280 + ($n * 137) % 640; $cy = 220 + ($n * 89) % 460; $r = 260 + ($n * 53) % 160;
            $qx = 120 + ($n * 211) % 760; $qy = 160 + ($n * 157) % 520; $rot = ($n * 23) % 50 - 25;
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 900" width="1200" height="900">'
                . '<defs><linearGradient id="f" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="' . $b . '"/></linearGradient>'
                . '<radialGradient id="l" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="#22d3ee" stop-opacity="0.55"/><stop offset="1" stop-color="#22d3ee" stop-opacity="0"/></radialGradient>'
                . '<radialGradient id="v" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="#c084fc" stop-opacity="0.5"/><stop offset="1" stop-color="#c084fc" stop-opacity="0"/></radialGradient>'
                . '<pattern id="p" width="36" height="36" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.6" fill="#ffffff" fill-opacity="0.12"/></pattern></defs>'
                . '<rect width="1200" height="900" fill="url(#f)"/><rect width="1200" height="900" fill="url(#p)"/>'
                . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="url(#l)"/>'
                . '<circle cx="' . (1200 - $cx) . '" cy="' . (900 - $cy) . '" r="' . ($r + 60) . '" fill="url(#v)"/>'
                . '<g transform="rotate(' . $rot . ' 600 450)" fill="none" stroke="#ffffff" stroke-opacity="0.35" stroke-width="3">'
                . '<rect x="' . $qx . '" y="' . $qy . '" width="300" height="300" rx="48"/>'
                . '<circle cx="' . ($qx + 420) . '" cy="' . ($qy + 90) . '" r="110"/>'
                . '<path d="M' . ($qx - 60) . ' ' . ($qy + 420) . ' l180 -180 l180 180"/></g>'
                . '<circle cx="' . ($qx + 300) . '" cy="' . $qy . '" r="14" fill="#22d3ee"/>'
                . '<circle cx="' . ($qx + 40) . '" cy="' . ($qy + 300) . '" r="9" fill="#c084fc"/></svg>';
            if (@file_put_contents($file, $svg) === false) continue;
            @chmod($file, 0644);
        }
        $out[$n] = cms_uploads_url() . '/kit/disegno-' . $n . '.svg';
    }
    return $out;
}

/** La mappa dei segnaposto: {sito}, {descrizione}, {immagine:N}. */
function kit_mappa(string $sito, string $descrizione): array {
    $m = ['{sito}' => $sito, '{descrizione}' => $descrizione !== '' ? $descrizione : 'Una frase che dice cosa fai e per chi: la scrivi in Impostazioni o qui, nell\'editor.'];
    foreach (kit_immagini() as $n => $u) $m['{immagine:' . $n . '}'] = $u;
    return $m;
}

// ------------------------------------------------------------
// 1.4.0: costruire un kit, e il tipo di sito "in prova"
// ------------------------------------------------------------
//
// Il kit si costruisce con kit_costruisci(), la stessa funzione per l'installatore e per il cambio dalla dashboard.
// Ogni cosa che il kit crea entra in un registro (impostazione kit_prova, JSON) con la sua impronta: lo sha1 dei campi
// che una persona puo cambiare, preso subito dopo averla creata. Al cambio di kit si toglie solo cio che ha ancora la
// stessa impronta (il kit l'ha fatto e nessuno l'ha toccato); il resto resta, e il riquadro lo dice.
//
//   kit_prova = {"kit": "portfolio", "stato": "prova"|"definitivo", "dal": "AAAA-MM-GG HH:MM:SS",
//                "creati": [{"t": "pagina"|"articolo"|"form"|"menu"|"immagine", "id": 12, "file": "kit/disegno-1.svg",
//                            "nome": "Chi sono", "slug": "chi-sono", "impronta": "<sha1>"}, ...],
//                "tenuti": ["frasi dell'ultimo cambio"], "storia": [{"da", "a", "quando", "copia"}], "definitivo_il": "..."}
//
// L'impostazione la scrive solo l'installatore della 1.4.0: un sito che si aggiorna non la ha e non vede niente.

const KIT_IMPOSTAZIONE = 'kit_prova';

/** I campi di una pagina o di un articolo che una persona puo cambiare (il resto lo scrive il CMS da solo). */
const KIT_CAMPI_IMPRONTA = ['title', 'slug', 'status', 'html', 'content', 'excerpt', 'struttura', 'struttura_bozza', 'seo_title', 'seo_description',
    'meta_description', 'robots', 'tldr', 'faq', 'immagine_url', 'featured_image', 'featured_image_alt', 'header_id', 'footer_id', 'publish_at',
    'publish_date', 'publish_time', 'categorie', 'tags', 'tag_slugs', 'author', 'type', 'keyword_target', 'cta', 'content_format'];
/** I campi del form dei contatti che contano. */
const KIT_CAMPI_FORM = ['nome', 'slug', 'titolo', 'intro', 'campi', 'pulsante', 'consenso', 'lista_id', 'lista_conferma', 'avviso', 'grazie_titolo', 'grazie_testo', 'grazie_link', 'attivo'];

/** Lo stato del tipo di sito in prova, o null (sito che si e aggiornato, o installato prima della 1.4.0). */
function kit_prova(): ?array {
    $s = json_decode((string)get_setting(KIT_IMPOSTAZIONE, ''), true);
    return is_array($s) && isset($s['kit'], $s['stato']) ? $s + ['creati' => [], 'tenuti' => [], 'storia' => []] : null;
}

function kit_prova_salva(array $s): void {
    save_setting(KIT_IMPOSTAZIONE, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Vero finche si puo ancora cambiare tipo di sito: installazione della 1.4.0 o successiva, e niente Definitivo. */
function kit_in_prova(): bool {
    $s = kit_prova();
    return $s !== null && $s['stato'] === 'prova';
}

/** L'impronta di quello che una voce del registro e adesso; null se non c'e piu. */
function kit_impronta(array $voce): ?string {
    $t = (string)($voce['t'] ?? '');
    if ($t === 'pagina' || $t === 'articolo') {
        $tab = tbl($t === 'pagina' ? 'pages' : 'articles');
        $st = db()->prepare('SELECT * FROM ' . $tab . ' WHERE id = ?');
        $st->execute([(int)$voce['id']]);
        $r = $st->fetch();
        if (!$r) return null;
        $campi = [];
        foreach (KIT_CAMPI_IMPRONTA as $k) if (array_key_exists($k, $r)) $campi[$k] = (string)$r[$k];
        return sha1(json_encode($campi, JSON_UNESCAPED_UNICODE));
    }
    if ($t === 'form') {
        $st = db()->prepare('SELECT * FROM ' . tbl('moduli') . ' WHERE id = ?');
        $st->execute([(int)$voce['id']]);
        $r = $st->fetch();
        if (!$r) return null;
        $campi = [];
        foreach (KIT_CAMPI_FORM as $k) if (array_key_exists($k, $r)) $campi[$k] = (string)$r[$k];
        return sha1(json_encode($campi, JSON_UNESCAPED_UNICODE));
    }
    if ($t === 'menu') {
        $st = db()->prepare('SELECT nome, posizione, voci FROM ' . tbl('menu') . ' WHERE id = ?');
        $st->execute([(int)$voce['id']]);
        $r = $st->fetch();
        return $r ? sha1($r['nome'] . "\n" . $r['posizione'] . "\n" . $r['voci']) : null;
    }
    if ($t === 'immagine') {
        $f = cms_uploads_dir() . '/' . (string)($voce['file'] ?? '');
        return preg_match('~^kit/disegno-\d+\.svg$~', (string)($voce['file'] ?? '')) && is_file($f) ? sha1_file($f) : null;
    }
    return null;
}

/** La home del kit Vuoto: quella di sempre dell'installatore (fino alla 1.3.1 scritta nell'installatore). */
function kit_vuoto_home(string $sito, string $descrizione): array {
    return [
        editor_sezione([editor_widget('hero', ['occhiello' => $descrizione !== '' ? '' : 'Benvenuto', 'titolo' => $sito . ', *benvenuto.*', 'testo' => $descrizione ?: 'Questa è la tua home: aprila nell\'editor e cambia testi, colori e blocchi come vuoi.',
            'pulsanti' => [['testo' => 'Leggi il blog', 'link' => '/blog/', 'stile' => 'viola'], ['testo' => 'Contatti', 'link' => '/contatti/', 'stile' => 'filo']], 'immagine' => '', 'etichetta_onda' => '', 'barre' => [], 'margine_sotto' => ['d' => 0]])], ['spazio_sopra' => ['d' => 64], 'spazio_sotto' => ['d' => 40]]),
        editor_sezione([editor_widget('titolo', ['occhiello' => 'Da dove cominciare', 'testo' => 'Tre cose da fare adesso', 'livello' => 'h2', 'margine_sotto' => ['d' => 28]]),
            editor_widget('schede', ['voci' => [
                ['titolo' => 'Cambia la grafica', 'testo' => 'Colori, caratteri e spaziature in un posto solo: Stile del sito.', 'link' => cms_base() . '/admin/design.php', 'invito' => 'Apri lo Stile →', 'evidenza' => true],
                ['titolo' => 'Scrivi il primo articolo', 'testo' => 'Dal pannello, voce Articoli: titolo, testo, immagine in evidenza, data di uscita.', 'link' => cms_base() . '/admin/contenuti.php?tipo=articoli', 'invito' => 'Vai agli articoli →', 'evidenza' => false],
                ['titolo' => 'Sistema header e footer', 'testo' => 'La testata e il piede sono modelli dell\'editor, con il menu Principale.', 'link' => cms_base() . '/admin/modelli.php', 'invito' => 'Apri i modelli →', 'evidenza' => false],
            ], 'colonne' => ['d' => 3, 't' => 2, 'm' => 1], 'margine_sotto' => ['d' => 0]])]),
        editor_sezione([editor_widget('titolo', ['occhiello' => 'Blog', 'testo' => 'Gli ultimi articoli', 'livello' => 'h2', 'margine_sotto' => ['d' => 28]]), editor_widget('articoli', ['numero' => 3, 'solo_pillar' => false, 'margine_sotto' => ['d' => 0]])]),
    ];
}

/**
 * Costruisce i contenuti di un kit: il form dei contatti, le pagine (home compresa), gli articoli, il menu Principale.
 * La usano l'installatore (database vuoto) e il cambio di tipo di sito (kit_cambia). Servono gia caricati contenuti.php,
 * editor.php, form.php, menu-nav.php e modelli.php, e il menu Principale gia creato (menu_installa).
 * Un indirizzo gia occupato (una pagina che la persona ha scritto o cambiato) non si tocca: la pagina del kit si salta.
 * $opz: 'form_id' => il form da usare (gia esistente), 'form_nostro' => true se e ancora quello del kit e si puo
 *       riscrivere titolo e intro; 'menu' => false per lasciare il menu com'e; 'oggi' => la data di uscita.
 * Ritorna ['log' => [...], 'creati' => [voci del registro], 'saltati' => [['tipo', 'titolo', 'slug'], ...]].
 */
function kit_costruisci(string $kitId, string $sito, string $descrizione, string $autore, array $opz = []): array {
    $kitDati = $kitId !== 'vuoto' ? kit_leggi($kitId) : null;
    if ($kitId !== 'vuoto' && !$kitDati) throw new RuntimeException('Il tipo di sito «' . $kitId . '» non c\'è.');
    $oggi = (string)($opz['oggi'] ?? date('Y-m-d'));
    $log = []; $creati = []; $saltati = [];

    // Il form dei contatti: lo stesso per ogni kit, cambiano titolo e frase.
    $formDati = ['nome' => 'Contatti', 'titolo' => (string)($kitDati['form']['titolo'] ?? 'Scrivimi'), 'intro' => (string)($kitDati['form']['intro'] ?? 'Rispondo appena posso.'), 'pulsante' => 'Invia', 'attivo' => 1, 'avviso' => 1,
        'campi' => [['etichetta' => 'Nome', 'tipo' => 'testo', 'obbligatorio' => 1, 'meta' => 'nome'], ['etichetta' => 'Email', 'tipo' => 'email', 'obbligatorio' => 1, 'meta' => 'email'], ['etichetta' => 'Messaggio', 'tipo' => 'testolungo', 'obbligatorio' => 1]],
        'consenso' => 'Acconsento al trattamento dei dati per ricevere una risposta.', 'grazie_titolo' => 'Grazie, ricevuto.', 'grazie_testo' => 'Ti rispondo al più presto.'];
    $formId = (int)($opz['form_id'] ?? 0);
    if ($formId && !empty($opz['form_nostro'])) {
        $f = form_leggi($formId);
        form_salva($formId, ['slug' => (string)$f['slug'], 'lista_id' => (int)$f['lista_id'], 'lista_conferma' => (int)$f['lista_conferma'], 'grazie_link' => (string)$f['grazie_link']] + $formDati);
        $creati[] = ['t' => 'form', 'id' => $formId, 'nome' => 'Contatti'];
        $log[] = 'form Contatti';
    } elseif (!$formId) {
        $st = db()->prepare('SELECT id FROM ' . tbl('moduli') . " WHERE slug = 'contatti'");
        $st->execute();
        $formId = (int)$st->fetchColumn();
        if (!$formId) {
            $formId = form_salva(0, $formDati);
            $creati[] = ['t' => 'form', 'id' => $formId, 'nome' => 'Contatti'];
            $log[] = 'form Contatti';
        }
    }

    // Le pagine. Un indirizzo gia preso resta di chi lo ha.
    $pagina = function (string $titolo, string $slug, array $albero, string $testo = '') use ($autore, $oggi, &$creati, &$saltati, &$log): int {
        if (!contenuti_slug_libero('pagine', $slug, 0)) { $saltati[] = ['pagina', $titolo, $slug]; return 0; }
        $id = contenuti_salva('pagine', 0, ['title' => $titolo, 'slug' => $slug, 'stato' => 'pubblicato', 'data' => $oggi, 'ora' => '00:00', 'contenuto' => $testo, 'robots' => 'index'], $autore);
        editor_salva($id, editor_valida(['elementi' => $albero]), true, $autore, 'Pagina di partenza', 'pagine');
        $creati[] = ['t' => 'pagina', 'id' => $id, 'nome' => $titolo, 'slug' => $slug];
        return $id;
    };
    $blog = [editor_sezione([editor_widget('titolo', ['testo' => 'Blog', 'livello' => 'h1'])])];
    if ($kitDati) {
        // Le pagine del kit: la home, Blog (l'elenco lo disegna il sito) e le pagine del kit, tutte con l'editor.
        $mappa = kit_mappa($sito, $descrizione);
        if ($pagina('Home', 'home', kit_sezioni((array)$kitDati['home'], $mappa))) $log[] = 'kit ' . $kitDati['nome'] . ': pagina Home (impostata come home)';
        if ($pagina('Blog', 'blog', $blog)) $log[] = 'pagina Blog (/blog/)';
        foreach ((array)($kitDati['pagine'] ?? []) as $pk) {
            if ($pagina((string)$pk['titolo'], (string)$pk['slug'], kit_sezioni((array)$pk['sezioni'], $mappa))) $log[] = 'pagina ' . $pk['titolo'];
        }
        // I disegni del kit, nel registro: si tolgono quando nessun kit li usa piu e nessuno li ha cambiati.
        foreach (array_keys(kit_immagini()) as $n) $creati[] = ['t' => 'immagine', 'file' => 'kit/disegno-' . $n . '.svg', 'nome' => 'disegno ' . $n];
    } else {
        if ($pagina('Home', 'home', kit_vuoto_home($sito, $descrizione))) $log[] = 'pagina Home (impostata come home)';
        // La pagina del blog: l'indirizzo esiste perché esiste la pagina; l'elenco degli articoli lo disegna il sito.
        if ($pagina('Blog', 'blog', $blog)) $log[] = 'pagina Blog (/blog/)';
        if ($pagina('Chi sono', 'chi-sono', [editor_sezione([editor_widget('titolo', ['testo' => 'Chi sono', 'livello' => 'h1']),
            editor_widget('testo', ['testo' => '<p>Qui racconti chi sei e cosa fai. Due o tre paragrafi bastano: cosa offri, a chi, perché ti si può dare fiducia.</p><p>Questa pagina è un esempio: aprila nell\'editor e riscrivila.</p>'])], ['larghezza' => 'articolo'])])) $log[] = 'pagina Chi sono';
        if ($pagina('Contatti', 'contatti', [editor_sezione([editor_widget('titolo', ['testo' => 'Contatti', 'livello' => 'h1']),
            editor_widget('testo', ['testo' => '<p>Scrivimi dal modulo qui sotto: arriva all\'indirizzo email del sito.</p>']), editor_widget('modulo', ['tipo' => 'form:' . $formId])], ['larghezza' => 'articolo'])])) $log[] = 'pagina Contatti con il form';
    }

    // Gli articoli: quello di benvenuto e quelli d'esempio del kit.
    $articolo = function (array $a) use ($autore, &$creati, &$saltati): int {
        if (!contenuti_slug_libero('articoli', (string)$a['slug'], 0)) { $saltati[] = ['articolo', $a['title'], $a['slug']]; return 0; }
        $id = contenuti_salva('articoli', 0, $a + ['stato' => 'pubblicato', 'ora' => '00:00', 'content_format' => 'html', 'categorie' => ['notizie'], 'robots' => 'index'], $autore);
        $creati[] = ['t' => 'articolo', 'id' => $id, 'nome' => $a['title'], 'slug' => $a['slug']];
        return $id;
    };
    $art = $articolo(['title' => 'Benvenuto nel tuo nuovo sito', 'slug' => 'benvenuto', 'data' => $oggi,
        'contenuto' => '<h2>Il primo articolo</h2><p>Questo articolo lo ha scritto l\'installatore. Serve a far vedere come esce un articolo sul sito: titolo, data, testo, i capitoli nel sommario.</p><h2>Cosa fare adesso</h2><p>Aprilo dal pannello (voce Articoli), cambia il testo oppure cestinalo e scrivi il tuo. Ogni articolo ha una data e un\'ora di uscita: puoi programmarlo e uscirà da solo.</p>',
        'excerpt' => 'Il primo articolo del sito, scritto dall\'installatore: aprilo, cambialo o cestinalo.']);
    if ($art) $log[] = 'articolo di benvenuto (' . $art . ')';
    foreach ((array)($kitDati['articoli'] ?? []) as $k => $a) {
        // Gli articoli d'esempio del kit escono un giorno prima l'uno dell'altro, cosi l'ordine del blog e quello scritto.
        if ($articolo(['title' => $a['titolo'], 'slug' => $a['slug'], 'data' => date('Y-m-d', strtotime($oggi . ' -' . ($k + 1) . ' day')), 'contenuto' => $a['contenuto'], 'excerpt' => $a['estratto']])) $log[] = 'articolo d\'esempio: ' . $a['titolo'];
    }

    // Il menu Principale con le pagine del kit.
    if (($opz['menu'] ?? true) && ($m = menu_per_posizione('principale'))) {
        $v = fn(string $e, string $u) => ['id' => menu_id_voce(), 'etichetta' => $e, 'tipo' => 'link', 'rif' => '', 'url' => $u, 'nuova' => false, 'classi' => '', 'figli' => []];
        $voci = $kitDati ? array_map(fn($x) => $v((string)$x[0], (string)$x[1]), (array)$kitDati['menu']) : [$v('Home', '/'), $v('Chi sono', '/chi-sono/'), $v('Blog', '/blog/'), $v('Contatti', '/contatti/')];
        menu_salva((int)$m['id'], (string)$m['nome'], 'principale', $voci);
        $creati[] = ['t' => 'menu', 'id' => (int)$m['id'], 'nome' => (string)$m['nome']];
    }

    // Le impronte, adesso che tutto e scritto.
    foreach ($creati as $i => $c) $creati[$i]['impronta'] = (string)kit_impronta($c);
    return ['log' => $log, 'creati' => $creati, 'saltati' => $saltati];
}

/** Il primo stato del tipo di sito in prova, scritto dall'installatore. */
function kit_prova_inizia(string $kitId, array $creati): void {
    kit_prova_salva(['kit' => $kitId, 'stato' => 'prova', 'dal' => date('Y-m-d H:i:s'), 'creati' => array_values($creati), 'tenuti' => [], 'storia' => []]);
}

/** Toglie per sempre una pagina o un articolo del kit (con le sue revisioni). La home si toglie anche lei: si rifa subito dopo. */
function kit_togli_contenuto(string $tipo, int $id): void {
    $tab = tbl($tipo === 'pagine' ? 'pages' : 'articles');
    db()->prepare('DELETE FROM ' . $tab . ' WHERE id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . tbl('revisioni') . ' WHERE tipo = ? AND contenuto_id = ?')->execute([$tipo, $id]);
    try { db()->prepare('DELETE FROM ' . tbl('pagine_revisioni') . ' WHERE tipo = ? AND pagina_id = ?')->execute([$tipo, $id]); } catch (Throwable $e) { /* tabella vecchia, puo mancare */ }
}

/** Un disegno del kit e ancora usato da qualche pagina, articolo o modello? */
function kit_immagine_usata(string $file): bool {
    $q = '%' . str_replace(['%', '_'], ['\%', '\_'], $file) . '%';
    foreach (['pages' => ['struttura', 'struttura_bozza', 'html'], 'articles' => ['struttura', 'struttura_bozza', 'content', 'featured_image']] as $t => $cols) {
        $dove = implode(' OR ', array_map(fn($c) => "`$c` LIKE ?", $cols));
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl($t) . " WHERE $dove");
        $st->execute(array_fill(0, count($cols), $q));
        if ((int)$st->fetchColumn()) return true;
    }
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('modelli') . ' WHERE struttura LIKE ? OR struttura_bozza LIKE ?');
        $st->execute([$q, $q]);
        if ((int)$st->fetchColumn()) return true;
    } catch (Throwable $e) {}
    return false;
}

/**
 * Cambia il tipo di sito. Prima una copia del sito (includes/copia-sito.php), poi: via quello che il kit di adesso ha
 * creato e nessuno ha toccato, dentro il kit nuovo. Quello che la persona ha scritto o cambiato resta.
 * Ritorna ['copia' => nome dello zip, 'tenuti' => [frasi], 'kit' => id nuovo]. Eccezione se non si puo (Definitivo, kit
 * che non c'e, stesso kit): in quel caso non si e toccato niente.
 */
function kit_cambia(string $nuovo, string $autore): array {
    $s = kit_prova();
    if (!$s) throw new RuntimeException('Questo sito non ha un tipo di sito in prova.');
    if ($s['stato'] !== 'prova') throw new RuntimeException('Il tipo di sito è già definitivo: non si cambia più da qui.');
    $kit = kit_elenco();
    if (!isset($kit[$nuovo])) throw new RuntimeException('Tipo di sito sconosciuto.');
    if ($nuovo === $s['kit']) throw new RuntimeException('Il sito è già ' . $kit[$nuovo]['nome'] . '.');
    foreach (['contenuti', 'editor', 'form', 'menu-nav', 'modelli', 'copia-sito'] as $f) require_once __DIR__ . '/' . $f . '.php';
    @set_time_limit(0);
    @ignore_user_abort(true);
    $copia = copia_crea('prima-del-cambio');
    try {
        return kit_cambia_dopo_copia($s, $nuovo, $autore, $copia, $kit);
    } catch (Throwable $e) {
        error_log('kit_cambia: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        throw new RuntimeException('Il cambio si è fermato a metà (' . $e->getMessage() . '). Il sito di prima è nella copia ' . $copia . ': ripristinala da Impostazioni, Copia del sito.');
    }
}

/** Il cambio vero, dopo la copia: lo chiama solo kit_cambia. */
function kit_cambia_dopo_copia(array $s, string $nuovo, string $autore, string $copia, array $kit): array {

    $tenuti = []; $tenutiSlug = []; $immaginiVecchie = [];
    // Il menu si riscrive solo se e ancora quello del kit: uno cambiato (adesso o a un cambio di prima) resta della persona.
    $opz = ['menu' => false];
    foreach ($s['creati'] as $c) {
        $adesso = kit_impronta($c);
        $uguale = $adesso !== null && $adesso === (string)($c['impronta'] ?? '');
        switch ($c['t']) {
            case 'pagina': case 'articolo':
                if ($adesso === null) break; // l'ha gia tolta la persona
                if ($uguale) kit_togli_contenuto($c['t'] === 'pagina' ? 'pagine' : 'articoli', (int)$c['id']);
                else { $tenutiSlug[] = $c['t'] . ':' . ($c['slug'] ?? ''); $tenuti[] = ($c['slug'] ?? '') === 'home' && $c['t'] === 'pagina' ? 'La home: l\'hai cambiata ed è rimasta la tua. Le altre pagine sono del sito ' . $kit[$nuovo]['nome'] . '.' : (($c['t'] === 'pagina' ? 'La pagina «' : 'L\'articolo «') . $c['nome'] . '»: ' . ($c['t'] === 'pagina' ? 'l\'hai cambiata ed è rimasta' : 'l\'hai cambiato ed è rimasto') . ' com\'era.'); }
                break;
            case 'form':
                if ($adesso === null) break;
                $opz['form_id'] = (int)$c['id'];
                $opz['form_nostro'] = $uguale;
                if (!$uguale) $tenuti[] = 'Il form dei contatti: l\'hai cambiato ed è rimasto com\'era.';
                break;
            case 'menu':
                if ($uguale) $opz['menu'] = true;
                elseif ($adesso !== null) $tenuti[] = 'Il menu principale: l\'hai cambiato ed è rimasto com\'era.';
                break;
            case 'immagine':
                if ($uguale) $immaginiVecchie[] = (string)$c['file'];
                break;
        }
    }
    $r = kit_costruisci($nuovo, (string)cms_impostazione('sito_nome', CMS_NOME), (string)cms_impostazione('sito_descrizione', ''), $autore, $opz);
    foreach ($r['saltati'] as [$tipo, $titolo, $slug]) {
        if (in_array($tipo . ':' . $slug, $tenutiSlug, true)) continue; // gia detto: e la pagina cambiata dalla persona
        $tenuti[] = $slug === 'home'
            ? 'La home: è rimasta la tua, perché l\'avevi cambiata. Le altre pagine sono del sito ' . $kit[$nuovo]['nome'] . '.'
            : ($tipo === 'pagina' ? 'La pagina «' . $titolo . '» del sito ' . $kit[$nuovo]['nome'] . ' non è stata creata: all\'indirizzo /' . $slug . '/ c\'è già la tua.'
                                  : 'L\'articolo d\'esempio «' . $titolo . '» non è stato creato: all\'indirizzo /' . $slug . '/ c\'è già il tuo.');
    }
    // I disegni del kit di prima che il kit nuovo non usa e che nessuna pagina mostra piu.
    $nuoviFile = array_column(array_filter($r['creati'], fn($c) => $c['t'] === 'immagine'), 'file');
    foreach ($immaginiVecchie as $f) {
        if (in_array($f, $nuoviFile, true) || kit_immagine_usata($f)) continue;
        @unlink(cms_uploads_dir() . '/' . $f);
    }
    // Le immagini rimaste in uso (in una pagina tenuta) restano nel registro, con la loro impronta.
    foreach ($immaginiVecchie as $f) {
        if (!in_array($f, $nuoviFile, true) && is_file(cms_uploads_dir() . '/' . $f)) $r['creati'][] = ['t' => 'immagine', 'file' => $f, 'nome' => basename($f), 'impronta' => (string)sha1_file(cms_uploads_dir() . '/' . $f)];
    }
    $s['storia'][] = ['da' => $s['kit'], 'a' => $nuovo, 'quando' => date('Y-m-d H:i:s'), 'copia' => $copia];
    $s['kit'] = $nuovo;
    $s['creati'] = array_values($r['creati']);
    $s['tenuti'] = $tenuti;
    kit_prova_salva($s);
    if (function_exists('sicurezza_log')) sicurezza_log('kit', $autore, (int)($_SESSION['admin_user_id'] ?? 0), 'tipo di sito cambiato in ' . $nuovo . ', copia ' . $copia);
    return ['copia' => $copia, 'tenuti' => $tenuti, 'kit' => $nuovo];
}

/** Definitivo: il tipo di sito resta questo, il riquadro sparisce e il cambio non si puo piu fare. */
function kit_definitivo(string $autore): void {
    $s = kit_prova();
    if (!$s) throw new RuntimeException('Questo sito non ha un tipo di sito in prova.');
    if ($s['stato'] !== 'prova') return;
    $s['stato'] = 'definitivo';
    $s['definitivo_il'] = date('Y-m-d H:i:s');
    $s['tenuti'] = [];
    kit_prova_salva($s);
    if (function_exists('sicurezza_log')) sicurezza_log('kit', $autore, (int)($_SESSION['admin_user_id'] ?? 0), 'tipo di sito definitivo: ' . $s['kit']);
}

/** Vero se la persona ha scritto qualcosa: una pagina o un articolo che il kit non ha creato, o uno del kit cambiato. */
function kit_contenuto_scritto(): bool {
    $s = kit_prova();
    $ids = ['pagina' => [], 'articolo' => []];
    foreach ((array)($s['creati'] ?? []) as $c) {
        if (!isset($ids[$c['t'] ?? ''])) continue;
        $ids[$c['t']][] = (int)$c['id'];
        $adesso = kit_impronta($c);
        if ($adesso !== null && $adesso !== (string)($c['impronta'] ?? '')) return true;
    }
    foreach (['pagina' => 'pages', 'articolo' => 'articles'] as $t => $tab) {
        $fuori = $ids[$t] ? ' AND id NOT IN (' . implode(',', $ids[$t]) . ')' : '';
        if ((int)db()->query('SELECT COUNT(*) FROM ' . tbl($tab) . ' WHERE cestino IS NULL' . $fuori)->fetchColumn()) return true;
    }
    return false;
}
