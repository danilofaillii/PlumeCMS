<?php
/**
 * Sezioni pronte (1.3.0, 27 ottobre 2026): blocchi già impaginati da mettere in una pagina con un clic (pannello
 * Sezioni dell'editor) o trascinandoli sulla tela. Una volta dentro sono sezioni normali, con colonne e widget
 * normali: si modificano come tutto il resto.
 *
 * Le definizioni sono dati, in includes/sezioni-pronte-dati.php: niente codice, solo array nella forma corta qui sotto.
 * Gli stessi dati li usa il kit di partenza dell'installatore (includes/kit-partenza.php), che compone le home con
 * questi blocchi.
 *
 * Forma corta di una sezione:
 *   ['imp' => [impostazioni della sezione], 'colonne' => [ colonna, colonna, ... ]]
 *   una colonna e un elenco di widget, oppure ['imp' => [impostazioni della colonna], 'widget' => [widget, ...]];
 *   un widget e [tipo, [impostazioni]].
 * Senza 'colonne', 'widget' => [...] vuol dire una colonna sola. Le larghezze delle colonne, se mancano, si dividono
 * come fa l'editor (impostaColonne in editor.js): uguali al computer, metà sul tablet da tre colonne in su, piene sul telefono.
 *
 * Colori: solo i globali (globale:primario, globale:sfondo-2...) e gli sfondi della sezione che seguono il tema
 * (vetro, gradiente). Cosi un blocco sta bene con il tema chiaro e con quello scuro.
 *
 *   sezioni_pronte_categorie()          le categorie, nell'ordine del pannello
 *   sezioni_pronte()                    tutte le varianti, per id (dati grezzi)
 *   sezioni_pronte_elementi($id)        le sezioni dell'editor di una variante, validate (null se non c'e)
 *   sezioni_pronte_per_editor()         l'elenco per il pannello Sezioni: id, categoria, nome, descrizione, elementi
 *   sezioni_pronte_costruisci($sezioni) forma corta -> sezioni dell'editor (non validate)
 */

require_once __DIR__ . '/editor.php';

const SEZIONI_PRONTE_CATEGORIE = [
    'apertura'      => 'Apertura',
    'servizi'       => 'Servizi',
    'chi'           => 'Chi siamo',
    'numeri'        => 'Numeri',
    'testimonianze' => 'Testimonianze',
    'prezzi'        => 'Prezzi',
    'faq'           => 'Domande frequenti',
    'contatti'      => 'Contatti',
    'invito'        => "Invito all'azione",
];

function sezioni_pronte_categorie(): array {
    return SEZIONI_PRONTE_CATEGORIE;
}

/** I dati: id => ['categoria', 'nome', 'descrizione', 'sezioni' => [forma corta]]. Un file che manca o e rotto non spegne l'editor. */
function sezioni_pronte(): array {
    static $d = null;
    if ($d !== null) return $d;
    $d = [];
    try {
        $x = require __DIR__ . '/sezioni-pronte-dati.php';
        foreach (is_array($x) ? $x : [] as $id => $v) {
            if (!is_string($id) || !preg_match('/^[a-z0-9-]{2,60}$/', $id) || !is_array($v) || !isset(SEZIONI_PRONTE_CATEGORIE[$v['categoria'] ?? ''])) continue;
            $d[$id] = $v;
        }
    } catch (Throwable $e) {
        error_log('sezioni pronte: ' . $e->getMessage());
    }
    return $d;
}

/** Un widget della forma corta. "form:contatti" diventa il form Contatti del sito (o il primo che c'e): l'id cambia da sito a sito. */
function sezioni_pronte_widget(array $w): ?array {
    $tipo = (string)($w[0] ?? '');
    $imp = is_array($w[1] ?? null) ? $w[1] : [];
    if ($tipo === 'modulo' && ($imp['tipo'] ?? '') === 'form:contatti') {
        $id = sezioni_pronte_form_contatti();
        if (!$id) return null;
        $imp['tipo'] = 'form:' . $id;
    }
    return ['id' => editor_id(), 'tipo' => $tipo, 'impostazioni' => $imp];
}

/** L'id del form dei contatti: quello che si chiama Contatti, altrimenti il primo attivo. 0 se non ce n'e. */
function sezioni_pronte_form_contatti(): int {
    static $id = null;
    if ($id !== null) return $id;
    $id = 0;
    try {
        require_once __DIR__ . '/form.php';
        foreach (form_tutti() as $f) {
            if (!$f['attivo']) continue;
            if (!$id) $id = (int)$f['id'];
            if (mb_strtolower((string)$f['nome']) === 'contatti') { $id = (int)$f['id']; break; }
        }
    } catch (Throwable $e) { $id = 0; }
    return $id;
}

/** Forma corta -> sezioni dell'editor. Da passare a editor_valida() prima di salvarle. */
function sezioni_pronte_costruisci(array $sezioni): array {
    $out = [];
    foreach ($sezioni as $s) {
        if (!is_array($s)) continue;
        $colonne = $s['colonne'] ?? [$s['widget'] ?? []];
        $n = max(1, count($colonne));
        $figli = [];
        foreach ($colonne as $c) {
            $c = is_array($c) ? $c : [];
            $imp = isset($c['widget']) ? (array)($c['imp'] ?? []) : [];
            $lista = isset($c['widget']) ? (array)$c['widget'] : $c;
            if ($n > 1 && !isset($imp['larghezza'])) $imp['larghezza'] = ['d' => (int)floor(100 / $n), 'm' => 100] + ($n > 2 ? ['t' => 50] : []);
            $widget = [];
            foreach ($lista as $w) if (is_array($w) && ($x = sezioni_pronte_widget($w))) $widget[] = $x;
            $figli[] = ['id' => editor_id(), 'tipo' => 'colonna', 'impostazioni' => $imp, 'figli' => $widget];
        }
        $out[] = ['id' => editor_id(), 'tipo' => 'sezione', 'impostazioni' => (array)($s['imp'] ?? []), 'figli' => $figli];
    }
    return $out;
}

/** Le sezioni dell'editor di una variante, validate come ogni albero che arriva al server. */
function sezioni_pronte_elementi(string $id): ?array {
    $v = sezioni_pronte()[$id] ?? null;
    if (!$v) return null;
    $albero = editor_valida(['elementi' => sezioni_pronte_costruisci((array)($v['sezioni'] ?? []))]);
    return $albero ? $albero['elementi'] : null;
}

/** L'elenco per il pannello Sezioni dell'editor (va nel JSON della pagina). */
function sezioni_pronte_per_editor(): array {
    $out = [];
    foreach (sezioni_pronte() as $id => $v) {
        $el = sezioni_pronte_elementi($id);
        if (!$el) continue;
        $out[] = ['id' => $id, 'categoria' => $v['categoria'], 'nome' => (string)($v['nome'] ?? $id), 'descrizione' => (string)($v['descrizione'] ?? ''), 'elementi' => $el];
    }
    return $out;
}
