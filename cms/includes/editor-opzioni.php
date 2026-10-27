<?php
/**
 * Le opzioni dell'editor (voce Editor > Impostazioni dell'editor) e il trasloco di tutte le impostazioni
 * dell'editor in un JSON unico (Esporta / Importa), per portarle in un'altra installazione del CMS.
 *
 * Le opzioni vivono nella tabella delle impostazioni con il prefisso editor_:
 *   editor_versioni_limite   quante versioni tenere per oggetto (includes/versioni.php la legge con versioni_limite() e pota oltre)
 *   editor_pubblica          chi pubblica dall'editor: 'tutti' (anche il redattore) oppure 'admin'
 *   editor_colonne_max       quante colonne al massimo in una sezione (da 2 a 6)
 *   editor_animazioni        1 = le animazioni d'ingresso scelte nella scheda Avanzate escono sul sito, 0 = restano spente
 *   editor_widget_disattivi  JSON con i tipi di widget nascosti dalla tavolozza (le pagine che li contengono li mostrano ancora)
 *
 * Il JSON dell'esportazione: { "formato": "editor-impostazioni", "versione": 1, "cms": "1.0.0", "creato": "...",
 *   "stile": {...tutte le sezioni dello Stile del sito...}, "modelli": [header e footer con strutture e condizioni],
 *   "menu": [menu di navigazione con le voci], "temi": [temi delle infografiche salvati], "impostazioni": {...le opzioni qui sopra...},
 *   "widget_disattivi": [...] }.
 * L'importazione dice prima cosa cambierebbe (editor_importa_anteprima) e poi applica solo gli ambiti scelti (editor_importa).
 * Modelli, menu e temi si riconoscono dal nome (e dal tipo, per i modelli): quelli con lo stesso nome si aggiornano, gli altri si aggiungono.
 */

const EDITOR_OPZIONI_PREDEFINITE = ['versioni_limite' => 50, 'pubblica' => 'tutti', 'colonne_max' => 4, 'animazioni' => 1, 'widget_disattivi' => []];
const EDITOR_ESPORTA_FORMATO = 'editor-impostazioni';
const EDITOR_ESPORTA_VERSIONE = 1;
const EDITOR_ESPORTA_AMBITI = ['stile' => 'Stile del sito', 'modelli' => 'Header e footer', 'menu' => 'Menu di navigazione', 'temi' => 'Colori delle infografiche', 'impostazioni' => "Impostazioni dell'editor", 'widget_disattivi' => 'Widget attivi'];

/** Tutte le opzioni, con i predefiniti per quelle mai salvate. Cache per richiesta. */
function editor_opzioni(bool $ricarica = false): array {
    static $cache = null;
    if ($cache !== null && !$ricarica) return $cache;
    $o = EDITOR_OPZIONI_PREDEFINITE;
    if (function_exists('get_setting')) {
        foreach (EDITOR_OPZIONI_PREDEFINITE as $k => $pred) {
            try { $v = get_setting('editor_' . $k, null); } catch (Throwable $e) { $v = null; }
            if ($v === null || $v === '') continue;
            $o[$k] = $k === 'widget_disattivi' ? (json_decode((string)$v, true) ?: []) : $v;
        }
    }
    return $cache = editor_opzioni_pulisci($o);
}

function editor_opzione(string $chiave) {
    return editor_opzioni()[$chiave] ?? (EDITOR_OPZIONI_PREDEFINITE[$chiave] ?? null);
}

/** Solo valori ammessi: numeri nei loro intervalli, scelte fra quelle note, tipi di widget con un nome valido. */
function editor_opzioni_pulisci(array $o): array {
    $p = EDITOR_OPZIONI_PREDEFINITE;
    $out = [];
    $out['versioni_limite'] = max(5, min(500, (int)($o['versioni_limite'] ?? $p['versioni_limite'])));
    $out['pubblica'] = ($o['pubblica'] ?? '') === 'admin' ? 'admin' : 'tutti';
    $out['colonne_max'] = max(2, min(6, (int)($o['colonne_max'] ?? $p['colonne_max'])));
    $out['animazioni'] = (int)!empty($o['animazioni'] ?? $p['animazioni']);
    $w = [];
    foreach ((array)($o['widget_disattivi'] ?? []) as $t) {
        $t = (string)$t;
        if (preg_match('/^[a-z][a-z0-9_]{1,40}$/', $t) && !in_array($t, ['sezione', 'colonna'], true)) $w[$t] = true;
    }
    $out['widget_disattivi'] = array_keys($w);
    sort($out['widget_disattivi']);
    return $out;
}

/** Salva tutte le opzioni (dopo la pulizia) e, se c'è il motore delle versioni, registra la versione. */
function editor_opzioni_salva(array $o, string $autore = '', string $nota = ''): void {
    $o = editor_opzioni_pulisci($o + editor_opzioni());
    foreach ($o as $k => $v) save_setting('editor_' . $k, is_array($v) ? json_encode($v) : (string)$v);
    editor_opzioni(true);
    editor_opzioni_versione($o, $autore, $nota);
}

/** Il gancio verso includes/versioni.php (agente versioni): dopo ogni salvataggio delle opzioni una versione dell'ambito "impostazioni". */
function editor_opzioni_versione(array $o, string $autore, string $nota): void {
    if (!function_exists('versioni_impostazioni_salva') && is_file(__DIR__ . '/versioni.php')) {
        try { require_once __DIR__ . '/versioni.php'; } catch (Throwable $e) { return; }
    }
    if (function_exists('versioni_impostazioni_salva')) {
        try { versioni_impostazioni_salva($nota); } catch (Throwable $e) { error_log('editor: versione delle impostazioni non salvata: ' . $e->getMessage()); }
    }
}

/** I tipi di widget nascosti dalla tavolozza. */
function editor_widget_disattivi(): array {
    return editor_opzione('widget_disattivi');
}

function editor_widget_attivo(string $tipo): bool {
    return !in_array($tipo, editor_widget_disattivi(), true);
}

/** L'utente di adesso può pubblicare dall'editor? Fuori dal pannello (nessuna sessione) la risposta è sì: decide chi chiama. */
function editor_puo_pubblicare(): bool {
    if (editor_opzione('pubblica') !== 'admin') return true;
    return function_exists('is_admin') ? is_admin() : true;
}

/** Le animazioni d'ingresso escono sul sito? */
function editor_animazioni_attive(): bool {
    return (int)editor_opzione('animazioni') === 1;
}

// ------------------------------------------------------------
// Esporta
// ------------------------------------------------------------

function editor_opzioni_carica_motori(): void {
    foreach (['design.php', 'modelli.php', 'menu-nav.php', 'editor-temi.php'] as $f) if (is_file(__DIR__ . '/' . $f)) require_once __DIR__ . '/' . $f;
}

/** Tutte le impostazioni dell'editor in un array pronto per il JSON. */
function editor_esporta(): array {
    editor_opzioni_carica_motori();
    $modelli = [];
    foreach (modelli_tutti(true) as $m) {
        if (!empty($m['cestino'])) continue;
        $modelli[] = [
            'tipo' => (string)$m['tipo'], 'nome' => (string)$m['nome'], 'status' => (string)$m['status'], 'predefinito' => (int)$m['predefinito'],
            'condizioni' => $m['condizioni'], 'priorita' => (int)$m['priorita'],
            'struttura' => json_decode((string)($m['struttura'] ?? ''), true), 'struttura_bozza' => json_decode((string)($m['struttura_bozza'] ?? ''), true),
        ];
    }
    $menu = [];
    foreach (menu_tutti() as $m) $menu[] = ['nome' => (string)$m['nome'], 'posizione' => (string)$m['posizione'], 'voci' => $m['voci']];
    $temi = [];
    foreach (editor_temi_salvati() as $t) $temi[] = ['nome' => $t['nome'], 'valori' => $t['valori']];
    $o = editor_opzioni();
    $disattivi = $o['widget_disattivi'];
    unset($o['widget_disattivi']);
    return [
        'formato' => EDITOR_ESPORTA_FORMATO, 'versione' => EDITOR_ESPORTA_VERSIONE, 'cms' => function_exists('cms_versione') ? cms_versione() : '',
        'creato' => date('Y-m-d H:i:s'),
        'stile' => design_valori(), 'modelli' => $modelli, 'menu' => $menu, 'temi' => $temi, 'impostazioni' => $o, 'widget_disattivi' => $disattivi,
    ];
}

/** Il JSON da scaricare: solo ASCII (le lettere fuori dal piano base restano \uXXXX), così passa ovunque. */
function editor_esporta_json(): string {
    return json_encode(editor_esporta(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// ------------------------------------------------------------
// Importa
// ------------------------------------------------------------

/** Legge e controlla il JSON di un'esportazione. Lancia RuntimeException con la spiegazione se non va. */
function editor_importa_leggi(string $json): array {
    if (strlen($json) > 20 * 1024 * 1024) throw new RuntimeException('Il file è troppo grande (oltre 20 MB).');
    $d = json_decode($json, true);
    if (!is_array($d)) throw new RuntimeException('Il file non è un JSON leggibile.');
    if (($d['formato'] ?? '') !== EDITOR_ESPORTA_FORMATO) throw new RuntimeException('Il file non è un\'esportazione delle impostazioni dell\'editor (manca la riga "formato").');
    if ((int)($d['versione'] ?? 0) > EDITOR_ESPORTA_VERSIONE) throw new RuntimeException('Il file viene da una versione più nuova del CMS: aggiorna prima questa installazione.');
    foreach (['modelli', 'menu', 'temi', 'widget_disattivi'] as $k) if (isset($d[$k]) && !is_array($d[$k])) throw new RuntimeException("La voce \"$k\" del file non è un elenco.");
    foreach (['stile', 'impostazioni'] as $k) if (isset($d[$k]) && !is_array($d[$k])) throw new RuntimeException("La voce \"$k\" del file non è leggibile.");
    return $d;
}

/** Un albero dell'editor dal file: valido oppure null. */
function editor_importa_albero($x): ?array {
    if (!is_array($x)) return null;
    require_once __DIR__ . '/editor.php';
    return editor_valida($x);
}

/** Una chiave per riconoscere un modello: tipo e nome. */
function editor_importa_chiave_modello(array $m): string {
    return (($m['tipo'] ?? 'header') === 'footer' ? 'footer' : 'header') . '|' . mb_strtolower(trim((string)($m['nome'] ?? '')));
}

/** Le differenze fra due array, come elenco di righe "sezione.chiave: prima -> dopo". */
function editor_importa_differenze(array $prima, array $dopo, string $prefisso = ''): array {
    $out = [];
    foreach ($dopo as $k => $v) {
        $nome = $prefisso === '' ? (string)$k : $prefisso . '.' . $k;
        if (is_array($v) && is_array($prima[$k] ?? null) && !array_is_list($v)) {
            $out = array_merge($out, editor_importa_differenze($prima[$k], $v, $nome));
        } elseif (($prima[$k] ?? null) != $v) {
            $da = $prima[$k] ?? null;
            $f = fn($x) => is_array($x) ? (array_is_list($x) ? count($x) . ' voci' : 'gruppo') : ($x === null ? 'vuoto' : (is_bool($x) ? ($x ? 'sì' : 'no') : mb_substr((string)$x, 0, 60)));
            $out[] = ['chiave' => $nome, 'prima' => $f($da), 'dopo' => $f($v)];
        }
    }
    return $out;
}

/**
 * Cosa cambierebbe importando il file: per ogni ambito, le righe con stato 'nuovo', 'cambia' o 'uguale' e un dettaglio leggibile.
 * Ritorna ['stile' => ['presente' => bool, 'righe' => [...]], ...].
 */
function editor_importa_anteprima(array $d): array {
    editor_opzioni_carica_motori();
    $out = [];
    // Stile: chiave per chiave, dopo la stessa pulizia del salvataggio.
    if (isset($d['stile'])) {
        $nuovo = design_pulisci(design_fondi(design_predefiniti(), $d['stile']));
        $diff = editor_importa_differenze(design_valori(), $nuovo);
        $righe = [];
        foreach ($diff as $x) $righe[] = ['stato' => 'cambia', 'nome' => $x['chiave'], 'dettaglio' => $x['prima'] . ' -> ' . $x['dopo']];
        if (!$righe) $righe[] = ['stato' => 'uguale', 'nome' => 'Stile del sito', 'dettaglio' => 'identico a quello di adesso'];
        $out['stile'] = ['presente' => true, 'righe' => $righe];
    }
    // Modelli: per tipo e nome.
    if (isset($d['modelli'])) {
        $miei = [];
        foreach (modelli_tutti(true) as $m) if (empty($m['cestino'])) $miei[editor_importa_chiave_modello($m)] = $m;
        $righe = [];
        foreach ($d['modelli'] as $m) {
            if (!is_array($m) || trim((string)($m['nome'] ?? '')) === '') continue;
            $k = editor_importa_chiave_modello($m);
            $tipo = explode('|', $k)[0];
            $etichetta = ucfirst($tipo) . ': ' . (string)$m['nome'];
            $struttura = editor_importa_albero($m['struttura'] ?? null);
            $bozza = editor_importa_albero($m['struttura_bozza'] ?? null);
            if (!isset($miei[$k])) { $righe[] = ['stato' => 'nuovo', 'nome' => $etichetta, 'dettaglio' => ($struttura ? 'con struttura pubblicata' : 'in bozza') . (!empty($m['predefinito']) ? ', predefinito' : '')]; continue; }
            $mio = $miei[$k];
            $cambi = [];
            if (json_encode($struttura) !== json_encode(json_decode((string)($mio['struttura'] ?? ''), true))) $cambi[] = 'struttura';
            if (json_encode($bozza) !== json_encode(json_decode((string)($mio['struttura_bozza'] ?? ''), true))) $cambi[] = 'bozza';
            if (json_encode(modelli_pulisci_condizioni($m['condizioni'] ?? [])) !== json_encode($mio['condizioni'])) $cambi[] = 'condizioni';
            if ((int)($m['priorita'] ?? 0) !== (int)$mio['priorita']) $cambi[] = 'priorità';
            if ((string)($m['status'] ?? 'draft') !== (string)$mio['status']) $cambi[] = 'stato';
            if ((int)!empty($m['predefinito']) !== (int)$mio['predefinito']) $cambi[] = 'predefinito';
            $righe[] = $cambi ? ['stato' => 'cambia', 'nome' => $etichetta, 'dettaglio' => 'cambia: ' . implode(', ', $cambi)] : ['stato' => 'uguale', 'nome' => $etichetta, 'dettaglio' => 'identico'];
        }
        $out['modelli'] = ['presente' => true, 'righe' => $righe];
    }
    // Menu: per nome.
    if (isset($d['menu'])) {
        $miei = [];
        foreach (menu_tutti() as $m) $miei[mb_strtolower(trim((string)$m['nome']))] = $m;
        $righe = [];
        foreach ($d['menu'] as $m) {
            if (!is_array($m) || trim((string)($m['nome'] ?? '')) === '') continue;
            $k = mb_strtolower(trim((string)$m['nome']));
            $voci = menu_pulisci_voci($m['voci'] ?? []);
            if (!isset($miei[$k])) { $righe[] = ['stato' => 'nuovo', 'nome' => (string)$m['nome'], 'dettaglio' => count($voci) . ' voci' . (($m['posizione'] ?? '') !== '' ? ', posizione ' . (MENU_POSIZIONI[$m['posizione']] ?? $m['posizione']) : '')]; continue; }
            $cambi = [];
            if (json_encode($voci) !== json_encode($miei[$k]['voci'])) $cambi[] = 'voci (' . count($miei[$k]['voci']) . ' -> ' . count($voci) . ')';
            if ((string)($m['posizione'] ?? '') !== (string)$miei[$k]['posizione']) $cambi[] = 'posizione';
            $righe[] = $cambi ? ['stato' => 'cambia', 'nome' => (string)$m['nome'], 'dettaglio' => 'cambia: ' . implode(', ', $cambi)] : ['stato' => 'uguale', 'nome' => (string)$m['nome'], 'dettaglio' => 'identico'];
        }
        $out['menu'] = ['presente' => true, 'righe' => $righe];
    }
    // Temi: per nome.
    if (isset($d['temi'])) {
        $miei = [];
        foreach (editor_temi_salvati() as $t) $miei[mb_strtolower($t['nome'])] = $t;
        $righe = [];
        foreach ($d['temi'] as $t) {
            if (!is_array($t) || trim((string)($t['nome'] ?? '')) === '') continue;
            $k = mb_strtolower(trim((string)$t['nome']));
            $valori = editor_tema_pulisci(is_array($t['valori'] ?? null) ? $t['valori'] : []);
            if (!isset($miei[$k])) { $righe[] = ['stato' => 'nuovo', 'nome' => (string)$t['nome'], 'dettaglio' => count($valori) . ' valori']; continue; }
            $diff = editor_importa_differenze($miei[$k]['valori'], $valori);
            $righe[] = $diff ? ['stato' => 'cambia', 'nome' => (string)$t['nome'], 'dettaglio' => 'cambia: ' . implode(', ', array_column($diff, 'chiave'))] : ['stato' => 'uguale', 'nome' => (string)$t['nome'], 'dettaglio' => 'identico'];
        }
        $out['temi'] = ['presente' => true, 'righe' => $righe];
    }
    // Impostazioni dell'editor e widget disattivi.
    if (isset($d['impostazioni'])) {
        $mie = editor_opzioni();
        $nuove = editor_opzioni_pulisci($d['impostazioni'] + ['widget_disattivi' => $mie['widget_disattivi']]);
        $righe = [];
        $etichette = ['versioni_limite' => 'Versioni da tenere', 'pubblica' => 'Chi pubblica', 'colonne_max' => 'Colonne massime', 'animazioni' => 'Animazioni'];
        foreach ($etichette as $k => $e) {
            if ($mie[$k] != $nuove[$k]) $righe[] = ['stato' => 'cambia', 'nome' => $e, 'dettaglio' => $mie[$k] . ' -> ' . $nuove[$k]];
        }
        if (!$righe) $righe[] = ['stato' => 'uguale', 'nome' => "Impostazioni dell'editor", 'dettaglio' => 'identiche'];
        $out['impostazioni'] = ['presente' => true, 'righe' => $righe];
    }
    if (isset($d['widget_disattivi'])) {
        $mie = editor_widget_disattivi();
        $nuovi = editor_opzioni_pulisci(['widget_disattivi' => $d['widget_disattivi']])['widget_disattivi'];
        $righe = [];
        foreach (array_diff($nuovi, $mie) as $t) $righe[] = ['stato' => 'cambia', 'nome' => $t, 'dettaglio' => 'si spegne'];
        foreach (array_diff($mie, $nuovi) as $t) $righe[] = ['stato' => 'cambia', 'nome' => $t, 'dettaglio' => 'si riaccende'];
        if (!$righe) $righe[] = ['stato' => 'uguale', 'nome' => 'Widget attivi', 'dettaglio' => 'identici (' . count($nuovi) . ' spenti)'];
        $out['widget_disattivi'] = ['presente' => true, 'righe' => $righe];
    }
    return $out;
}

/** Applica il file per gli ambiti scelti. Ritorna il log, una riga per cosa fatta. */
function editor_importa(array $d, array $ambiti, string $autore = ''): array {
    editor_opzioni_carica_motori();
    $log = [];
    $nota = 'importazione da file';
    if (in_array('stile', $ambiti, true) && isset($d['stile'])) {
        design_salva(design_fondi(design_predefiniti(), $d['stile']), $autore, $nota);
        $log[] = 'Stile del sito aggiornato.';
    }
    if (in_array('modelli', $ambiti, true) && isset($d['modelli'])) {
        $miei = [];
        foreach (modelli_tutti(true) as $m) if (empty($m['cestino'])) $miei[editor_importa_chiave_modello($m)] = $m;
        $n = 0;
        foreach ($d['modelli'] as $m) {
            if (!is_array($m) || trim((string)($m['nome'] ?? '')) === '') continue;
            $k = editor_importa_chiave_modello($m);
            $tipo = explode('|', $k)[0];
            $struttura = editor_importa_albero($m['struttura'] ?? null);
            $bozza = editor_importa_albero($m['struttura_bozza'] ?? null);
            $cond = modelli_pulisci_condizioni($m['condizioni'] ?? []);
            $stato = ($m['status'] ?? 'draft') === 'published' && $struttura ? 'published' : 'draft';
            $id = isset($miei[$k]) ? (int)$miei[$k]['id'] : modelli_crea($tipo, (string)$m['nome'], $bozza ?: $struttura);
            db()->prepare('UPDATE ' . tbl('modelli') . ' SET struttura = ?, struttura_bozza = ?, struttura_aggiornata = NOW(), status = ?, condizioni = ?, priorita = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$struttura ? modelli_json($struttura) : null, $bozza ? modelli_json($bozza) : null, $stato, json_encode($cond, JSON_UNESCAPED_SLASHES), (int)($m['priorita'] ?? 0), $id]);
            if (!empty($m['predefinito']) && $stato === 'published') modelli_predefinito($id);
            if (function_exists('modelli_versione')) modelli_versione($id, $nota); // versioni: il gancio di modelli.php
            $n++;
        }
        $log[] = "Header e footer: $n modelli scritti.";
    }
    if (in_array('menu', $ambiti, true) && isset($d['menu'])) {
        $miei = [];
        foreach (menu_tutti() as $m) $miei[mb_strtolower(trim((string)$m['nome']))] = $m;
        $n = 0;
        foreach ($d['menu'] as $m) {
            if (!is_array($m) || trim((string)($m['nome'] ?? '')) === '') continue;
            $k = mb_strtolower(trim((string)$m['nome']));
            $id = menu_salva(isset($miei[$k]) ? (int)$miei[$k]['id'] : 0, (string)$m['nome'], (string)($m['posizione'] ?? ''), (array)($m['voci'] ?? []));
            $n++;
        }
        $log[] = "Menu di navigazione: $n menu scritti.";
    }
    if (in_array('temi', $ambiti, true) && isset($d['temi'])) {
        $miei = [];
        foreach (editor_temi_salvati() as $t) $miei[mb_strtolower($t['nome'])] = $t;
        $n = 0;
        foreach ($d['temi'] as $t) {
            if (!is_array($t) || trim((string)($t['nome'] ?? '')) === '') continue;
            $k = mb_strtolower(trim((string)$t['nome']));
            $valori = editor_tema_pulisci(is_array($t['valori'] ?? null) ? $t['valori'] : []);
            if (isset($miei[$k])) {
                db()->prepare('UPDATE ' . tbl('editor_temi') . ' SET valori = ?, aggiornato = NOW() WHERE id = ?')->execute([json_encode($valori, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$miei[$k]['id']]);
                $id = (int)$miei[$k]['id'];
                if (function_exists('editor_tema_versione')) editor_tema_versione($id, $nota); // versioni: il gancio di editor-temi.php
            } else {
                $id = editor_tema_salva((string)$t['nome'], $valori); // versiona da solo
            }
            $n++;
        }
        $log[] = "Colori delle infografiche: $n temi scritti.";
    }
    $opz = [];
    if (in_array('impostazioni', $ambiti, true) && isset($d['impostazioni'])) $opz = array_intersect_key($d['impostazioni'], array_flip(['versioni_limite', 'pubblica', 'colonne_max', 'animazioni']));
    if (in_array('widget_disattivi', $ambiti, true) && isset($d['widget_disattivi'])) $opz['widget_disattivi'] = $d['widget_disattivi'];
    if ($opz) {
        editor_opzioni_salva($opz, $autore, $nota);
        $log[] = isset($opz['widget_disattivi']) ? "Impostazioni dell'editor e widget attivi aggiornati." : "Impostazioni dell'editor aggiornate.";
    }
    return $log;
}
