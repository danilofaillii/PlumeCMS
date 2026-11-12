<?php
/**
 * Addon AI collegate: le proposte. Un'AI non cambia mai un contenuto pubblicato né la libreria dei Media: lascia una proposta
 * (valore di prima, valore nuovo, motivo) che l'amministratore approva, corregge e approva, oppure scarta, nel pannello.
 * L'approvazione passa dalle strade del CMS: contenuti_salva (revisione e redirect), editor_salva (versione), media_alt_salva.
 * Manuale tecnico, 47.5.
 */
require_once __DIR__ . '/ai.php';

require_once cms_radice() . '/includes/contenuti.php';
require_once cms_radice() . '/includes/seo-tabella.php';

const AIC_PROPOSTA_STATI = ['attesa', 'approvata', 'scartata', 'superata'];

/** I campi che si possono proporre: nome per l'AI => [etichetta, tipi ammessi]. */
function aic_campi_proposta(): array {
    return [
        'titolo' => ['Titolo', ['articoli', 'pagine']],
        'titolo_seo' => ['Titolo per Google', ['articoli', 'pagine']],
        'descrizione_seo' => ['Descrizione per Google', ['articoli', 'pagine']],
        'parola_chiave' => ['Parola chiave', ['articoli']],
        'in_breve' => ['In breve', ['articoli', 'pagine']],
        'estratto' => ['Estratto', ['articoli', 'pagine']],
        'domande_frequenti' => ['Domande frequenti', ['articoli', 'pagine']],
        'immagine' => ['Immagine in evidenza', ['articoli', 'pagine']],
        'alt_immagine' => ['Alt dell\'immagine in evidenza', ['articoli']],
        'testo' => ['Testo', ['articoli', 'pagine']],
        'alt' => ['Alt di un\'immagine della libreria', ['media']],
        // 2.38.0: gli altri dati di un'immagine della libreria (propose_media_data, includes/media-dati.php)
        'titolo_immagine' => ['Titolo di un\'immagine della libreria', ['media']],
        'didascalia' => ['Didascalia di un\'immagine della libreria', ['media']],
        'descrizione_immagine' => ['Descrizione di un\'immagine della libreria', ['media']],
    ];
}

/** 2.38.0: il campo dei dati della libreria (includes/media-dati.php) di un campo di proposta "media". */
function aic_campo_media(string $campo): string {
    return ['alt' => 'alt', 'titolo_immagine' => 'titolo', 'didascalia' => 'didascalia', 'descrizione_immagine' => 'descrizione'][$campo] ?? 'alt';
}

/** Il widget dell'albero con quell'id (riferimento), oppure null. */
function &aic_widget_in_albero(array &$albero, string $id) {
    $nulla = null;
    foreach ($albero['elementi'] as &$s) foreach ($s['figli'] as &$c) foreach ($c['figli'] as &$w) if (($w['id'] ?? '') === $id) return $w;
    return $nulla;
}

/** Il campo di testo di un widget che una proposta può cambiare: 'contenuto' del Testo (HTML), 'testo' del Titolo. */
function aic_campo_testo_widget(string $tipo): ?string {
    return ['testo' => 'contenuto', 'titolo' => 'testo', 'frase' => 'testo', 'citazione' => 'testo'][$tipo] ?? null;
}

/** Il valore di adesso di un campo di una proposta (per "prima"). */
function aic_proposta_valore_attuale(string $tipo, ?array $r, string $campo, string $widget = '', string $url = ''): string {
    if ($tipo === 'media') {
        require_once cms_radice() . '/includes/media-alt.php';
        if ($campo === 'alt' || !is_file(cms_radice() . '/includes/media-dati.php')) return media_alt_di($url);
        require_once cms_radice() . '/includes/media-dati.php';
        return (string)media_dati_di($url, true)[aic_campo_media($campo)];
    }
    switch ($campo) {
        case 'titolo': return (string)$r['title'];
        case 'titolo_seo': return (string)$r['seo_title'];
        case 'descrizione_seo': return seo_tabella_descrizione($r)['testo'];
        case 'parola_chiave': return (string)($r['keyword_target'] ?? '');
        case 'in_breve': return (string)$r['tldr'];
        case 'estratto': return (string)$r['excerpt'];
        case 'domande_frequenti': return (string)($r['faq'] ?: '');
        case 'immagine': return json_encode(['url' => (string)($r['immagine_url'] ?: ($r['featured_image'] ?? '')), 'alt' => (string)($r['featured_image_alt'] ?? '')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        case 'alt_immagine': return (string)($r['featured_image_alt'] ?? '');
        case 'testo':
            if ($widget !== '') {
                require_once __DIR__ . '/strumenti.php';
                aic_carica_editor();
                $a = editor_decodifica((string)$r['struttura']);
                if (!$a) return '';
                $w = &aic_widget_in_albero($a, $widget);
                if ($w === null) return '';
                $k = aic_campo_testo_widget((string)$w['tipo']);
                return $k ? (string)($w['impostazioni'][$k] ?? '') : '';
            }
            return (string)($r[contenuti_tipo($tipo)['campo_contenuto']] ?? '');
    }
    return '';
}

/** Il valore nuovo ripulito, come si salva nella proposta (testo; JSON per domande e immagine). */
function aic_proposta_valore_pulito(string $tipo, string $campo, $v, string $widgetTipo = ''): string {
    switch ($campo) {
        case 'titolo': $x = aic_testo($v, 500); if ($x === '') throw new AicErrore('Il titolo non può essere vuoto.'); return $x;
        case 'titolo_seo': return aic_testo($v, 255);
        case 'descrizione_seo': return aic_testo($v, 500);
        case 'parola_chiave': return aic_testo($v, 255);
        case 'in_breve': return aic_testo($v, 1000, true);
        case 'estratto': return aic_testo($v, 2000, true);
        case 'alt_immagine': case 'alt': return aic_testo($v, 300);
        case 'titolo_immagine': return aic_testo($v, 200);
        case 'didascalia': return aic_testo($v, 500);
        case 'descrizione_immagine': return aic_testo($v, 2000, true);
        case 'domande_frequenti':
            require_once __DIR__ . '/strumenti.php';
            return aic_faq(is_string($v) ? (json_decode($v, true) ?? []) : $v);
        case 'immagine':
            require_once __DIR__ . '/strumenti.php';
            $v = is_string($v) ? (json_decode($v, true) ?? ['url' => $v]) : $v;
            if (!is_array($v)) throw new AicErrore('Per l\'immagine il valore è {url, alt}.');
            return json_encode(['url' => aic_immagine_libreria((string)($v['url'] ?? '')), 'alt' => aic_testo($v['alt'] ?? '', 300)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        case 'testo':
            if ($widgetTipo === 'titolo' || $widgetTipo === 'frase' || $widgetTipo === 'citazione') return aic_testo($v, $widgetTipo === 'titolo' ? 500 : 2000, $widgetTipo !== 'titolo');
            return aic_html($v);
    }
    throw new AicErrore('Campo sconosciuto.');
}

/** Crea una proposta. $gruppo (2.33.0): il codice della scheda dell'articolo che lega più proposte (propose_article_changes). */
function aic_proposta_crea(array $conn, array $a, string $gruppo = ''): array {
    return aic_proposta_salva($conn, aic_proposta_prepara($conn, $a), $gruppo);
}

/**
 * 2.33.0: la parte che controlla (nessuna scrittura): ritorna la riga da salvare o lancia AicErrore. Divisa da
 * aic_proposta_salva() perché propose_article_changes controlla tutte le modifiche prima di scriverne una (o tutte o nessuna).
 */
function aic_proposta_prepara(array $conn, array $a, int $giaInAttesa = 0): array {
    aic_schema();
    $tipo = (string)($a['tipo'] ?? '');
    $campo = (string)($a['campo'] ?? '');
    $campi = aic_campi_proposta();
    if (!isset($campi[$campo])) throw new AicErrore('Campo sconosciuto: ' . mb_substr($campo, 0, 40) . '. Ammessi: ' . implode(', ', array_keys($campi)) . '.');
    if (!in_array($tipo, $campi[$campo][1], true)) throw new AicErrore('Il campo "' . $campo . '" vale per: ' . implode(', ', $campi[$campo][1]) . '.');
    $motivo = aic_testo($a['motivo'] ?? '', 1000, true);
    if (mb_strlen($motivo) < 5) throw new AicErrore('Scrivi il motivo della proposta (lo legge l\'amministratore per decidere).');
    if (!array_key_exists('valore', $a)) throw new AicErrore('Manca il valore nuovo.');
    $n = db()->prepare('SELECT COUNT(*) FROM ' . tbl('ai_proposte') . " WHERE connessione_id = ? AND stato = 'attesa'");
    $n->execute([(int)$conn['id']]);
    if ((int)$n->fetchColumn() + $giaInAttesa >= AIC_PROPOSTE_MAX) throw new AicErrore('Ci sono già ' . AIC_PROPOSTE_MAX . ' proposte in attesa da questo collegamento: aspetta che l\'amministratore decida.', 429);
    $r = null; $url = ''; $widget = ''; $wTipo = ''; $id = 0;
    if ($tipo === 'media') {
        require_once __DIR__ . '/strumenti.php';
        $url = aic_immagine_libreria((string)($a['url'] ?? ''));
        if ($url === '') throw new AicErrore('Per i media serve "url": l\'indirizzo dell\'immagine nella libreria.');
    } else {
        $r = contenuti_leggi($tipo, (int)($a['id'] ?? 0));
        if (!$r || !empty($r['cestino'])) throw new AicErrore('Contenuto non trovato: controlla tipo e id con list_content.', 404);
        $id = (int)$r['id'];
        if ($campo === 'testo') {
            $haAlbero = trim((string)$r['struttura']) !== '';
            $widget = is_string($a['widget_id'] ?? null) ? preg_replace('/[^a-z0-9]/', '', $a['widget_id']) : '';
            if ($haAlbero) {
                if ($widget === '') throw new AicErrore('Questo contenuto è fatto con l\'editor: indica in "widget_id" l\'id del widget Testo o Titolo da cambiare (lo trovi con get_content, nell\'albero pubblicato).');
                require_once __DIR__ . '/strumenti.php';
                aic_carica_editor();
                $alb = editor_decodifica((string)$r['struttura']);
                $w = $alb ? aic_widget_in_albero($alb, $widget) : null;
                if (!$w) throw new AicErrore('Nell\'albero pubblicato non c\'è un widget con id "' . $widget . '".', 404);
                $wTipo = (string)$w['tipo'];
                if (!aic_campo_testo_widget($wTipo)) throw new AicErrore('Il widget "' . $widget . '" è un ' . $wTipo . ': si possono proporre testi solo per i widget Testo, Titolo, Frase in evidenza e Citazione.');
            } elseif ($widget !== '') throw new AicErrore('Questo contenuto non è fatto con l\'editor: togli "widget_id" e proponi il testo intero.');
        }
    }
    $dopo = aic_proposta_valore_pulito($tipo, $campo, $a['valore'], $wTipo);
    $prima = aic_proposta_valore_attuale($tipo, $r, $campo, $widget, $url);
    if ($dopo === $prima) throw new AicErrore('Il valore proposto è uguale a quello di adesso.');
    return ['tipo' => $tipo, 'contenuto_id' => $id, 'media_url' => $url, 'campo' => $campo, 'widget_id' => $widget, 'prima' => $prima, 'dopo' => $dopo, 'motivo' => $motivo];
}

/** 2.33.0: scrive una proposta preparata da aic_proposta_prepara(). */
function aic_proposta_salva(array $conn, array $x, string $gruppo = ''): array {
    ['tipo' => $tipo, 'contenuto_id' => $id, 'media_url' => $url, 'campo' => $campo, 'widget_id' => $widget, 'prima' => $prima, 'dopo' => $dopo, 'motivo' => $motivo] = $x;
    // Una proposta nuova sullo stesso campo prende il posto di quella in attesa (che diventa "superata").
    db()->prepare('UPDATE ' . tbl('ai_proposte') . " SET stato = 'superata', deciso = NOW(), nota = 'sostituita da una proposta più recente' WHERE stato = 'attesa' AND tipo = ? AND contenuto_id = ? AND media_url = ? AND campo = ? AND widget_id = ?")
        ->execute([$tipo, $id, $url, $campo, $widget]);
    db()->prepare('INSERT INTO ' . tbl('ai_proposte') . ' (connessione_id, client, tipo, contenuto_id, media_url, campo, widget_id, prima, dopo, motivo, stato, creato, gruppo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'attesa\', NOW(), ?)')
        ->execute([(int)$conn['id'], mb_substr((string)$conn['nome'], 0, 200), $tipo, $id, $url, $campo, $widget, $prima, $dopo, $motivo, preg_replace('/[^a-z0-9-]/', '', $gruppo)]);
    return ['id' => (int)db()->lastInsertId(), 'tipo' => $tipo, 'contenuto_id' => $id, 'campo' => $campo, 'prima' => $prima, 'dopo' => $dopo];
}

function aic_proposta(int $id): ?array {
    aic_schema();
    $st = db()->prepare('SELECT * FROM ' . tbl('ai_proposte') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Applica una proposta (dal pannello, solo amministratore). $valore: il valore corretto a mano ("Modifica e approva"), che
 * passa dalla stessa pulizia; null = quello proposto. Lancia RuntimeException con il messaggio da mostrare.
 */
function aic_proposta_applica(array $p, ?string $valore, string $autore): void {
    if ($p['stato'] !== 'attesa') throw new RuntimeException('Questa proposta è già stata decisa.');
    $tipo = (string)$p['tipo']; $campo = (string)$p['campo'];
    require_once __DIR__ . '/strumenti.php';
    if ($tipo === 'menu' || $campo === 'navigazione') {
        // 2.33.0: un menu intero o i blocchi in fondo a una pagina. Niente "Modifica e approva": si approva o si scarta.
        require_once __DIR__ . '/navigazione.php';
        if ($valore !== null) throw new RuntimeException('Questa proposta non si corregge qui: approvala e ritocca dal pannello, oppure scartala.');
        if ($tipo === 'menu') aic_nav_applica_menu($p);
        else aic_nav_applica_correzione($p, $autore);
        db()->prepare('UPDATE ' . tbl('ai_proposte') . " SET stato = 'approvata', deciso = NOW(), deciso_da = ?, nota = 'approvata' WHERE id = ?")->execute([mb_substr($autore, 0, 100), (int)$p['id']]);
        return;
    }
    if ($tipo === 'media') {
        require_once cms_radice() . '/includes/media-alt.php';
        try { $v = $valore === null ? (string)$p['dopo'] : aic_proposta_valore_pulito('media', $campo, $valore); }
        catch (AicErrore $e) { throw new RuntimeException($e->getMessage()); }
        if ($campo === 'alt') $r = media_alt_salva((string)$p['media_url'], $v);
        else {
            // 2.38.0: titolo, didascalia, descrizione nei dati della foto (gli altri campi restano come sono).
            require_once cms_radice() . '/includes/media-dati.php';
            $k = aic_campo_media($campo);
            $r = media_dati_salva((string)$p['media_url'], [$k => $v], [$k]);
        }
        if (isset($r['errore'])) throw new RuntimeException($r['errore']);
    } else {
        $r = contenuti_leggi($tipo, (int)$p['contenuto_id']);
        if (!$r || !empty($r['cestino'])) throw new RuntimeException('Il contenuto non c\'è più (o è nel cestino).');
        $wTipo = '';
        if ($campo === 'testo' && $p['widget_id'] !== '') {
            aic_carica_editor();
            $alb = editor_decodifica((string)$r['struttura']);
            $w = $alb ? aic_widget_in_albero($alb, (string)$p['widget_id']) : null;
            if (!$w) throw new RuntimeException('Nella pagina pubblicata quel widget non c\'è più: la proposta non si può applicare. Scartala.');
            $wTipo = (string)$w['tipo'];
        }
        try { $v = $valore === null ? (string)$p['dopo'] : aic_proposta_valore_pulito($tipo, $campo, $campo === 'domande_frequenti' ? (json_decode($valore, true) ?? aic_faq_da_testo($valore)) : $valore, $wTipo); }
        catch (AicErrore $e) { throw new RuntimeException($e->getMessage()); }
        if ($campo === 'testo' && $p['widget_id'] !== '') {
            // Nell'albero pubblicato (e nella bozza dell'editor, se c'è: le modifiche non pubblicate dell'amministratore restano).
            $k = aic_campo_testo_widget($wTipo);
            $bozza = trim((string)$r['struttura_bozza']) !== '' ? editor_decodifica((string)$r['struttura_bozza']) : null;
            $w = &aic_widget_in_albero($alb, (string)$p['widget_id']);
            $w['impostazioni'][$k] = $v;
            unset($w);
            editor_salva((int)$r['id'], $alb, true, $autore, 'proposta dell\'AI approvata', $tipo);
            if ($bozza) {
                $wb = &aic_widget_in_albero($bozza, (string)$p['widget_id']);
                if ($wb !== null) $wb['impostazioni'][$k] = $v;
                unset($wb);
                editor_salva((int)$r['id'], $bozza, false, $autore, 'bozza di prima, con la proposta dell\'AI', $tipo);
            }
        } else {
            $d = [];
            switch ($campo) {
                case 'titolo': $d['title'] = $v; break;
                case 'titolo_seo': $d['seo_title'] = $v; break;
                case 'descrizione_seo': $d[seo_tabella_descrizione($r)['colonna']] = $v; break;
                case 'parola_chiave': $d['keyword_target'] = $v; break;
                case 'in_breve': $d['tldr'] = $v; break;
                case 'estratto': $d['excerpt'] = $v; break;
                case 'domande_frequenti': $d['faq'] = $v; break;
                case 'alt_immagine': $d['immagine_alt'] = $v; break;
                case 'testo': $d['contenuto'] = $v; break;
                case 'immagine':
                    $im = json_decode($v, true) ?: [];
                    $d['immagine'] = (string)($im['url'] ?? '');
                    if (contenuti_tipo($tipo)['attributi'] && array_key_exists('alt', $im)) $d['immagine_alt'] = (string)$im['alt'];
                    break;
            }
            $base = array_replace(aic_base_da_riga($tipo, $r), $d);
            contenuti_salva($tipo, (int)$r['id'], $base, $autore);
        }
    }
    db()->prepare('UPDATE ' . tbl('ai_proposte') . " SET stato = 'approvata', deciso = NOW(), deciso_da = ?, nota = ?, dopo = ? WHERE id = ?")
        ->execute([mb_substr($autore, 0, 100), $valore === null ? 'approvata' : 'corretta e approvata', $valore === null ? $p['dopo'] : $v, (int)$p['id']]);
}

function aic_proposta_scarta(array $p, string $autore): void {
    if ($p['stato'] !== 'attesa') throw new RuntimeException('Questa proposta è già stata decisa.');
    db()->prepare('UPDATE ' . tbl('ai_proposte') . " SET stato = 'scartata', deciso = NOW(), deciso_da = ?, nota = 'scartata' WHERE id = ?")->execute([mb_substr($autore, 0, 100), (int)$p['id']]);
}

/** Le domande frequenti scritte a mano nel pannello: domanda sulla prima riga, risposta sotto, una riga vuota fra le coppie. */
function aic_faq_da_testo(string $t): array {
    $out = [];
    foreach (preg_split('/\R\s*\R/', trim($t)) as $b) {
        $righe = preg_split('/\R/', trim($b));
        $q = trim(array_shift($righe) ?? '');
        $r = trim(implode(' ', $righe));
        if ($q !== '' && $r !== '') $out[] = ['domanda' => $q, 'risposta' => $r];
    }
    return $out;
}

/** 2.33.0: il nome del campo di una proposta nel pannello (anche i due della navigazione, che propose_change non conosce). */
function aic_campo_etichetta(string $campo): string {
    return ['voci' => 'Menu', 'navigazione' => 'Blocchi in fondo alla pagina'][$campo] ?? (aic_campi_proposta()[$campo][0] ?? $campo);
}

/** Il valore di una proposta da mostrare nel pannello (testo leggibile). */
function aic_proposta_leggibile(string $campo, ?string $v): string {
    $v = (string)$v;
    if ($campo === 'navigazione') {
        $f = json_decode($v, true);
        return is_array($f) ? 'In fondo si aggiunge una sezione:' . "\n" . (string)($f['leggibile'] ?? '') : $v;
    }
    if ($campo === 'domande_frequenti') {
        $f = json_decode($v, true);
        return is_array($f) ? implode("\n\n", array_map(fn($x) => ($x['domanda'] ?? '') . "\n" . ($x['risposta'] ?? ''), $f)) : $v;
    }
    if ($campo === 'immagine') {
        $f = json_decode($v, true);
        return is_array($f) ? trim(($f['url'] ?? '') . "\nalt: " . ($f['alt'] ?? '')) : $v;
    }
    return $v;
}
