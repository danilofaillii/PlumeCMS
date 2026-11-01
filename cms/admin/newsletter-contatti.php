<?php
/**
 * 2.8.0: Newsletter e form > Contatti, come Brevo (CRM > Contacts): schede Contatti e Liste.
 *   Contatti: le cinque colonne fisse, Personalizza le colonne, ricerca, Carica una lista, filtro dello stato, 50/100/500
 *             per pagina, selezione (anche di tutti quelli del filtro) e le azioni di gruppo: Aggiungi a liste, Togli dalle
 *             liste, Sposta, Metti in lista nera, Modifica (solo data, sì/no, categoria), Esporta, Elimina definitivamente.
 *             Crea un contatto, Importa contatti (2.9.0: la pagina a passi newsletter-importa.php), Esporta (2.9.0: la
 *             finestra di Brevo, con attributi, separatore e filtri per lista o campagna, a giri), Metti in lista nera
 *             degli indirizzi.
 *   Liste:    le cartelle con le loro liste; crea, rinomina, sposta in un'altra cartella, elimina.
 * 2.10.0: Carica un segmento, "Aggiungi filtro" con le condizioni dei segmenti e "Salva come segmento", viste salvate
 * (filtri e colonne con un nome, per utente), esportazione di un segmento. La scheda Segmenti è newsletter-segmenti.php.
 * Motore in includes/newsletter-contatti.php e includes/newsletter-segmenti.php. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-importa.php'; // 2.9.0
require_once __DIR__ . '/../includes/newsletter-segmenti.php'; // 2.10.0
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();
newsletter_importa_schema(); // 2.9.0: le tabelle delle esportazioni (anche su un sito appena aggiornato)
newsletter_segmenti_schema(); // 2.10.0: segmenti e viste (anche su un sito appena aggiornato)
if (($_GET['scheda'] ?? '') === 'segmenti') { header('Cache-Control: no-store'); header('Location: newsletter-segmenti.php', true, 302); exit; }

$adminTitle = 'Contatti';
$scheda = ($_GET['scheda'] ?? '') === 'liste' ? 'liste' : 'contatti';
$utenteId = (int)(current_user()['id'] ?? 0);

/** Le colonne scelte da questo utente (nomi di attributi), nell'ordine scelto. */
function nlc_colonne(int $u): array {
    $c = json_decode((string)get_setting('newsletter_colonne_u' . $u, ''), true);
    $attr = newsletter_attributi();
    return array_values(array_filter(is_array($c) ? $c : [], fn($n) => is_string($n) && isset($attr[$n]) && $attr[$n]['categoria'] !== 'global'));
}

/** "1 contatto", "3 contatti". */
function nlc_n(int $n): string { return $n === 1 ? '1 contatto' : "$n contatti"; }

/** Dove tornare dopo un'azione: la stessa scheda con gli stessi filtri. */
function nlc_torna(array $q, string $scheda = 'contatti'): never {
    $f = newsletter_contatti_filtro($q)[2];
    unset($f['errore_filtro']);
    $qs = ['scheda' => $scheda] + array_filter($f) + (isset($q['per']) ? ['per' => (int)$q['per']] : []) + (!empty($q['vista']) ? ['vista' => (int)$q['vista']] : []);
    header('Location: newsletter-contatti.php?' . http_build_query($qs));
    exit;
}

/** Gli id su cui lavora un'azione di gruppo: tutti quelli del filtro, oppure le righe spuntate. */
function nlc_scelti(array $p): array {
    if (!empty($p['tutti'])) return newsletter_contatti_ids($p);
    return array_values(array_unique(array_filter(array_map('intval', (array)($p['sel'] ?? [])))));
}

/**
 * 2.9.0: Esporta come Brevo (finestra Esporta): i contatti scelti (o tutti quelli del filtro), una lista o una campagna,
 * con gli attributi e il separatore scelti. Un file piccolo esce subito; uno grande continua a giri e si scarica dal
 * riquadro Esportazioni. Senza campi della finestra (la 2.8.0, le prove) tutte le colonne e il punto e virgola.
 */
function nlc_esporta_avvia(array $p, int $u): never {
    $orig = (string)($p['esp_origine'] ?? 'scelti');
    if ($orig === 'lista') $f = ['tipo' => 'lista', 'lista' => (int)($p['esp_lista'] ?? 0), 'chi' => isset(NL_ESP_LISTA[$p['esp_lista_chi'] ?? '']) ? (string)$p['esp_lista_chi'] : 'tutti'];
    elseif ($orig === 'segmento') $f = ['tipo' => 'segmento', 'segmento' => (int)($p['esp_segmento'] ?? 0)];
    elseif ($orig === 'campagna') $f = ['tipo' => 'campagna', 'campagna' => (int)($p['esp_campagna'] ?? 0), 'chi' => isset(NL_ESP_CAMPAGNA[$p['esp_campagna_chi'] ?? '']) ? (string)$p['esp_campagna_chi'] : 'aperto'];
    elseif (!empty($p['tutti'])) $f = ['tipo' => 'contatti', 'q' => newsletter_contatti_filtro($p)[2]];
    else $f = ['tipo' => 'ids', 'ids' => nlc_scelti($p)];
    $col = isset($p['esp_col']) ? array_filter((array)$p['esp_col'], 'is_string') : array_keys(newsletter_esporta_colonne());
    $r = newsletter_esporta_crea($f, $col, (string)($p['esp_sep'] ?? ';'), $u);
    if (!is_int($r)) { flash('error', $r); nlc_torna($p); }
    if (newsletter_esporta_giro($r, microtime(true) + 8)) newsletter_file_scarica((string)newsletter_esportazione($r)['file'], 'contatti');
    flash('success', 'Esportazione avviata: i contatti sono tanti e il file si prepara a giri. Quando è pronto lo scarichi dal riquadro Esportazioni qui sotto: la pagina si aggiorna da sola.');
    header('Location: newsletter-contatti.php#nlx-esportazioni');
    exit;
}

if (($_GET['scarica'] ?? '') === 'tutti') {
    // Tutti i contatti, tutte le colonne (il collegamento di prima): si prepara tutto in questa richiesta.
    $r = newsletter_esporta_crea(['tipo' => 'contatti', 'q' => []], array_keys(newsletter_esporta_colonne()), ';', $utenteId);
    while (!newsletter_esporta_giro($r, microtime(true) + 20));
    newsletter_file_scarica((string)newsletter_esportazione($r)['file'], 'contatti');
}
if (isset($_GET['esportazione'])) {
    $e = newsletter_esportazione((int)$_GET['esportazione']);
    if ($e && $e['stato'] === 'finita') newsletter_file_scarica((string)$e['file'], 'contatti');
    flash('error', 'Questa esportazione non c\'è più o non è ancora pronta: i file restano ' . NL_FILE_GIORNI . ' giorni.');
    header('Location: newsletter-contatti.php#nlx-esportazioni'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'esporta_giro') {
    // 2.9.0: un giro delle esportazioni chiesto dal riquadro Esportazioni: risposta JSON.
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!csrf_ok()) { http_response_code(403); echo json_encode(['errore' => 'La sessione è scaduta: ricarica la pagina.']); exit; }
    session_write_close();
    @set_time_limit(90);
    try { newsletter_lavori_giro(8); } catch (Throwable $e) { error_log('esporta giro: ' . $e->getMessage()); }
    $r = db()->query('SELECT COUNT(*) AS n, COALESCE(SUM(righe), 0) AS f, COALESCE(SUM(totale), 0) AS t FROM ' . tbl('newsletter_esportazioni') . " WHERE stato = 'in_corso'")->fetch();
    echo json_encode(['finito' => !(int)$r['n'], 'fatte' => (int)$r['f'], 'totale' => (int)$r['t'], 'testo' => (int)$r['f'] . ' contatti su ' . (int)$r['t']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_once dirname(__DIR__) . '/includes/sicurezza-html.php';
    $azione = (string)($_POST['azione'] ?? '');
    if ($azione === 'colonne') {
        $scelte = array_values(array_unique(array_filter((array)($_POST['colonne'] ?? []), 'is_string')));
        $attr = newsletter_attributi();
        $scelte = array_values(array_filter($scelte, fn($n) => isset($attr[$n]) && $attr[$n]['categoria'] !== 'global'));
        save_setting('newsletter_colonne_u' . $utenteId, json_encode(array_slice($scelte, 0, 30)));
        flash('success', 'Colonne della tabella salvate.');
        nlc_torna($_POST);
    }
    if ($azione === 'gruppo') {
        $cosa = !empty($_POST['esporta_avvia']) ? 'esporta' : (string)($_POST['cosa'] ?? '');
        if ($cosa === 'esporta') nlc_esporta_avvia($_POST, $utenteId);
        $ids = nlc_scelti($_POST);
        $lista = (int)($_POST['lista_dest'] ?? 0);
        if (!$ids) { flash('error', 'Spunta almeno un contatto, oppure scegli "tutti i contatti del filtro".'); nlc_torna($_POST); }
        if ($cosa === 'aggiungi') {
            if (!$lista || newsletter_nome_lista($lista) === '') { flash('error', 'Scegli la lista in cui aggiungere i contatti.'); nlc_torna($_POST); }
            foreach ($ids as $i) newsletter_in_lista($i, $lista);
            flash('success', nlc_n(count($ids)) . ' aggiunt' . (count($ids) === 1 ? 'o' : 'i') . ' alla lista «' . newsletter_nome_lista($lista) . '».');
        } elseif ($cosa === 'togli') {
            $da = (int)($_POST['lista_da'] ?? 0);
            $n = newsletter_togli_da_liste($ids, $da > 0 ? [$da] : []);
            flash('success', $da > 0 ? nlc_n($n) . ' tolt' . ($n === 1 ? 'o' : 'i') . ' dalla lista «' . newsletter_nome_lista($da) . '».' : "Contatti tolti da tutte le loro liste ($n appartenenze).");
        } elseif ($cosa === 'sposta') {
            // Brevo: Sposta = aggiungi alla lista nuova e togli dalla lista d'origine (quella caricata).
            $da = (int)($_POST['lista'] ?? 0);
            if ($da <= 0) { flash('error', 'Per spostare, carica prima la lista d\'origine con "Carica una lista", poi spunta i contatti.'); nlc_torna($_POST); }
            if (!$lista || $lista === $da || newsletter_nome_lista($lista) === '') { flash('error', 'Scegli la lista di arrivo, diversa da quella caricata.'); nlc_torna($_POST); }
            foreach ($ids as $i) newsletter_in_lista($i, $lista);
            newsletter_togli_da_liste($ids, [$da]);
            flash('success', nlc_n(count($ids)) . ' spostat' . (count($ids) === 1 ? 'o' : 'i') . ' da «' . newsletter_nome_lista($da) . '» a «' . newsletter_nome_lista($lista) . '».');
        } elseif ($cosa === 'blocca') {
            $n = 0;
            $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
            foreach ($ids as $i) { $st->execute([$i]); if (!in_array($st->fetchColumn(), NL_BLOCCATI, true) && newsletter_contatto_blocca($i, 'adminBlocked')) $n++; }
            flash('success', nlc_n($n) . ($n === 1 ? ' messo' : ' messi') . ' in lista nera: non ricevono più campagne né automazioni.' . ($n < count($ids) ? ' Gli altri erano già in lista nera.' : ''));
        } elseif ($cosa === 'modifica') {
            $n = newsletter_modifica_in_massa($ids, (string)($_POST['attributo'] ?? ''), $_POST['valore'] ?? '');
            $n < 0 ? flash('error', 'Il valore non va bene per questo attributo, oppure l\'attributo non si può cambiare in massa (come in Brevo, solo Data, Sì/No e Categoria).')
                : flash('success', 'Attributo cambiato a ' . nlc_n($n) . '.');
        } elseif ($cosa === 'elimina') {
            $n = newsletter_contatti_elimina($ids);
            flash('success', nlc_n($n) . ($n === 1 ? ' eliminato' : ' eliminati') . " definitivamente, con attributi, cronologia e statistiche. Le loro email restano nella lista di soppressione: un'importazione non li rimetterà dentro.");
        } else {
            flash('error', 'Scegli un\'azione.');
        }
        nlc_torna($_POST);
    }
    if ($azione === 'vista_salva') {
        // 2.10.0: una vista = i filtri della pagina e le colonne di adesso, con un nome.
        $q = newsletter_contatti_filtro($_POST)[2];
        $r = newsletter_vista_salva($utenteId, (string)($_POST['nome_vista'] ?? ''), $q, array_filter((array)($_POST['colonne_vista'] ?? []), 'is_string'), (int)($_POST['per'] ?? 50));
        if (is_int($r)) { flash('success', 'Vista salvata: la trovi fra le schede sopra la tabella.'); header('Location: newsletter-contatti.php?vista=' . $r); exit; }
        flash('error', $r);
        nlc_torna($_POST);
    }
    if ($azione === 'vista_elimina') {
        newsletter_vista_elimina($utenteId, (int)($_POST['vista_id'] ?? 0));
        flash('success', 'Vista eliminata. I contatti e i segmenti restano.');
        header('Location: newsletter-contatti.php'); exit;
    }
    if ($azione === 'lista_nera_indirizzi') {
        // "Riempibile a mano": gli indirizzi scritti qui vanno in lista nera (contatti nuovi se non c'erano).
        $righe = preg_split('/[\s,;]+/', (string)($_POST['indirizzi'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $n = 0; $scartati = 0;
        foreach (array_slice($righe, 0, 1000) as $e) {
            $e = mb_strtolower(trim($e));
            if (!filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($e) > 190) { $scartati++; continue; }
            $id = newsletter_id_da_email($e);
            if (!$id) {
                $ora = date('Y-m-d H:i:s');
                db()->prepare('INSERT INTO ' . tbl('newsletter_iscritti') . " (email, stato, fonte, creato, aggiornato) VALUES (?, 'attivo', 'manuale', ?, ?)")->execute([$e, $ora, $ora]);
                $id = (int)db()->lastInsertId();
                newsletter_storia_scrivi($id, 'creato', 'Creato a mano per metterlo in lista nera');
            }
            $c = newsletter_contatto($id);
            if ($c && !in_array($c['stato'], NL_BLOCCATI, true) && newsletter_contatto_blocca($id, 'adminBlocked')) $n++;
        }
        flash($n || !$scartati ? 'success' : 'error', "$n indirizzi messi in lista nera." . ($scartati ? " $scartati non erano email valide e sono stati saltati." : ''));
        header('Location: newsletter-contatti.php?stato=lista_nera');
        exit;
    }
    // Liste e cartelle
    if ($azione === 'cartella_crea') {
        $r = newsletter_cartella_crea((string)($_POST['nome'] ?? ''));
        is_int($r) ? flash('success', 'Cartella creata.') : flash('error', $r);
        header('Location: newsletter-contatti.php?scheda=liste'); exit;
    }
    if ($azione === 'cartella_rinomina') {
        $nome = newsletter_testo_pulito((string)($_POST['nome'] ?? ''), 100);
        try {
            if ($nome === '') flash('error', 'Scrivi il nome della cartella.');
            else { db()->prepare('UPDATE ' . tbl('newsletter_cartelle') . ' SET nome = ? WHERE id = ?')->execute([$nome, (int)($_POST['id'] ?? 0)]); flash('success', 'Cartella rinominata.'); }
        } catch (PDOException $e) { flash('error', 'Esiste già una cartella con questo nome.'); }
        header('Location: newsletter-contatti.php?scheda=liste'); exit;
    }
    if ($azione === 'cartella_elimina') {
        $e = newsletter_cartella_elimina((int)($_POST['id'] ?? 0));
        $e === '' ? flash('success', 'Cartella eliminata con le sue liste. I contatti restano.') : flash('error', $e);
        header('Location: newsletter-contatti.php?scheda=liste'); exit;
    }
    if ($azione === 'lista_crea') {
        $r = newsletter_lista_crea((string)($_POST['nome'] ?? ''), (int)($_POST['cartella'] ?? 0), (string)($_POST['descrizione'] ?? ''));
        is_int($r) ? flash('success', 'Lista «' . newsletter_nome_lista($r) . '» creata. Nasce vuota: aggiungi i contatti dalla scheda Contatti.') : flash('error', $r);
        header('Location: newsletter-contatti.php?scheda=liste'); exit;
    }
    if ($azione === 'lista_modifica') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = newsletter_testo_pulito((string)($_POST['nome'] ?? ''), 100);
        $cart = (int)($_POST['cartella'] ?? 0);
        try {
            if ($nome === '') flash('error', 'Scrivi il nome della lista.');
            else {
                db()->prepare('UPDATE ' . tbl('newsletter_liste') . ' SET nome = ?, descrizione = ?, cartella_id = IF(? > 0 AND EXISTS (SELECT 1 FROM ' . tbl('newsletter_cartelle') . ' c WHERE c.id = ?), ?, cartella_id) WHERE id = ?')
                    ->execute([$nome, newsletter_testo_pulito((string)($_POST['descrizione'] ?? ''), 255), $cart, $cart, $cart, $id]);
                flash('success', 'Lista aggiornata.');
            }
        } catch (PDOException $e) { flash('error', (int)($e->errorInfo[1] ?? 0) === 1062 ? 'Esiste già una lista con questo nome.' : 'La lista non si è salvata.'); }
        header('Location: newsletter-contatti.php?scheda=liste'); exit;
    }
    if ($azione === 'lista_elimina') {
        newsletter_lista_elimina((int)($_POST['id'] ?? 0))
            ? flash('success', 'Lista eliminata. I contatti restano, nelle altre loro liste.')
            : flash('error', 'Questa lista la usa il sito per le iscrizioni: si può rinominare, non eliminare.');
        header('Location: newsletter-contatti.php?scheda=liste'); exit;
    }
    nlc_torna($_POST);
}

$numeri = newsletter_numeri();
$cartelle = newsletter_cartelle();
$liste = newsletter_liste();
$nomeLista = array_column($liste, 'nome', 'id');
$attributi = newsletter_attributi();
$sistemaListe = newsletter_liste_di_sistema();

/** Una tendina delle liste, raggruppate per cartella. */
$tendinaListe = function (string $nome, string $vuota, int $scelta = 0, string $piu = '', string $id = '') use ($cartelle): string {
    $h = '<select name="' . h($nome) . '"' . ($id !== '' ? ' id="' . h($id) . '"' : '') . '>' . ($vuota !== '' ? '<option value="0">' . h($vuota) . '</option>' : '');
    foreach ($cartelle as $c) {
        if (!$c['liste']) continue;
        $h .= '<optgroup label="' . h(newsletter_4byte_fuori($c['nome'])) . '">';
        foreach ($c['liste'] as $l) $h .= '<option value="' . (int)$l['id'] . '"' . ($scelta === (int)$l['id'] ? ' selected' : '') . '>' . h(newsletter_4byte_fuori($l['nome'])) . '</option>';
        $h .= '</optgroup>';
    }
    return $h . $piu . '</select>';
};

require_once __DIR__ . '/includes/admin-header.php';
?>
<nav class="imp-schede" aria-label="Contatti">
  <a href="newsletter-contatti.php"<?= $scheda === 'contatti' ? ' class="attiva" aria-current="page"' : '' ?>>Contatti</a>
  <a href="newsletter-contatti.php?scheda=liste"<?= $scheda === 'liste' ? ' class="attiva" aria-current="page"' : '' ?>>Liste</a>
  <a href="newsletter-segmenti.php">Segmenti</a>
</nav>

<?php if ($scheda === 'contatti'):
  // 2.10.0: una vista salvata porta i suoi filtri e le sue colonne.
  $viste = newsletter_viste($utenteId);
  $vistaId = (int)($_GET['vista'] ?? 0);
  if (!isset($viste[$vistaId])) $vistaId = 0;
  $qPag = $vistaId ? $viste[$vistaId]['filtro'] + ['pag' => $_GET['pag'] ?? 1] : $_GET;
  [$where, $par, $f] = newsletter_contatti_filtro($qPag);
  $per = in_array((int)($qPag['per'] ?? 50), [50, 100, 500], true) ? (int)($qPag['per'] ?? 50) : 50;
  $tot = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i$where");
  $tot->execute($par);
  $totale = (int)$tot->fetchColumn();
  $pagine = max(1, (int)ceil($totale / $per));
  $pag = min($pagine, max(1, (int)($qPag['pag'] ?? 1)));
  // Brevo: ordine per data di creazione, dalla più recente.
  $st = db()->prepare('SELECT i.* FROM ' . tbl('newsletter_iscritti') . " i$where ORDER BY i.creato DESC, i.id DESC LIMIT $per OFFSET " . (($pag - 1) * $per));
  $st->execute($par);
  $righe = $st->fetchAll();
  $colonne = $vistaId ? array_values(array_filter($viste[$vistaId]['colonne'], fn($n) => isset($attributi[$n]))) : nlc_colonne($utenteId);
  $valori = $colonne ? newsletter_valori_di(array_column($righe, 'id'), $colonne) : [];
  $nascosti = '<input type="hidden" name="cerca" value="' . h($f['cerca']) . '"><input type="hidden" name="stato" value="' . h($f['stato']) . '"><input type="hidden" name="lista" value="' . (int)$f['lista'] . '"><input type="hidden" name="per" value="' . $per . '">'
    . '<input type="hidden" name="segmento" value="' . (int)$f['segmento'] . '"><input type="hidden" name="f" value="' . h($f['f']) . '">' . ($vistaId ? '<input type="hidden" name="vista" value="' . $vistaId . '">' : '');
  $qBase = array_filter(['cerca' => $f['cerca'], 'stato' => $f['stato'], 'lista' => $f['lista'] ?: null, 'segmento' => $f['segmento'] ?: null, 'f' => $f['f'] ?: null, 'per' => $per !== 50 ? $per : null]);
  $qLink = $vistaId ? ['vista' => $vistaId] : $qBase; // i link delle pagine restano nella vista
  $segmenti = newsletter_segmenti();
  $nomeSeg = array_column(array_map(fn($x) => ['id' => (int)$x['id'], 'nome' => newsletter_4byte_fuori((string)$x['nome'])], $segmenti), 'nome', 'id');
  $regoleF = $f['f'] !== '' ? (newsletter_regole_valida($f['f'])['regole'] ?? null) : null;
  $modificabili = array_filter($attributi, fn($a) => !(int)$a['sistema'] && in_array($a['tipo'], ['date', 'boolean', 'category'], true));
?>
<nav class="nlv-viste" aria-label="Viste dei contatti">
  <a href="newsletter-contatti.php"<?= !$vistaId ? ' class="attiva" aria-current="page"' : '' ?>>Tutti i contatti</a>
  <?php foreach ($viste as $vid => $v): ?><a href="newsletter-contatti.php?vista=<?= (int)$vid ?>"<?= $vistaId === (int)$vid ? ' class="attiva" aria-current="page"' : '' ?>><?= h(newsletter_4byte_fuori($v['nome'])) ?></a><?php endforeach; ?>
  <button type="button" class="btn btn-secondary btn-sm" data-apri-dialogo="#nlv-salva">+ Salva come vista</button>
</nav>
<div class="form-card">
  <div class="nl-riga" style="justify-content:space-between">
    <h3 style="margin:0"><?= $vistaId ? 'Vista «' . h(newsletter_4byte_fuori($viste[$vistaId]['nome'])) . '»' : 'Contatti' ?></h3>
    <div class="nl-riga">
      <a class="btn btn-primary btn-sm" href="newsletter-contatto.php?nuovo=1">Crea un contatto</a>
      <a class="btn btn-secondary btn-sm" href="newsletter-importa.php">Importa contatti</a>
      <button class="btn btn-secondary btn-sm" type="button" data-apri-dialogo="#nlx-esporta">Esporta i contatti</button>
      <button class="btn btn-secondary btn-sm" type="button" data-apri-dialogo="#nlx-colonne">Personalizza le colonne</button>
    </div>
  </div>
  <p class="hint" style="margin-top:0"><?= (int)array_sum(array_intersect_key($numeri, array_flip(['attivo', 'in_attesa', 'uscito', 'rimbalzato', 'spam', 'bloccato']))) ?> contatti: <?= (int)$numeri['attivo'] ?> iscritti, <?= (int)$numeri['in_attesa'] ?> in attesa di conferma, <a href="?stato=lista_nera"><?= (int)$numeri['lista_nera'] ?> in lista nera</a>.</p>
  <?php if ($numeri['segnalati']): ?><p class="nl-avviso" role="status"><b><?= (int)$numeri['segnalati'] ?></b> <?= $numeri['segnalati'] === 1 ? 'contatto ha' : 'contatti hanno' ?> avuto tre errori temporanei di fila (soft bounce): al quinto di fila vanno in lista nera. <a href="?stato=errori">Vedi chi</a>.</p><?php endif; ?>

  <form method="get" class="nlx-filtri" role="search" aria-label="Cerca i contatti">
    <div class="form-group"><label for="nlx-lista">Carica una lista</label>
      <?= $tendinaListe('lista', 'Tutti i contatti', (int)$f['lista'], '<option value="-1"' . ($f['lista'] === -1 ? ' selected' : '') . '>In nessuna lista</option>', 'nlx-lista') ?></div>
    <div class="form-group"><label for="nlx-segmento">Carica un segmento</label>
      <select id="nlx-segmento" name="segmento"><option value="0"><?= $segmenti ? 'Nessun segmento' : 'Ancora nessun segmento' ?></option><?php foreach ($nomeSeg as $sid => $sn): ?><option value="<?= (int)$sid ?>"<?= (int)$f['segmento'] === (int)$sid ? ' selected' : '' ?>><?= h($sn) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="nlx-cerca">Cerca</label>
      <input type="search" id="nlx-cerca" name="cerca" value="<?= h($f['cerca']) ?>" placeholder="per esempio: anna@ oppure Rossi" aria-describedby="nlx-cerca-aiuto">
      <div class="hint" id="nlx-cerca-aiuto">Email (anche un pezzo), nome, cognome o identificativo esterno.</div></div>
    <div class="form-group"><label for="nlx-stato">Stato dell'email</label>
      <select id="nlx-stato" name="stato">
        <option value="">Tutti</option>
        <option value="iscritti"<?= $f['stato'] === 'iscritti' ? ' selected' : '' ?>>Iscritti</option>
        <option value="in_attesa"<?= $f['stato'] === 'in_attesa' ? ' selected' : '' ?>>In attesa di conferma</option>
        <option value="lista_nera"<?= $f['stato'] === 'lista_nera' ? ' selected' : '' ?>>In lista nera (tutti i motivi)</option>
        <?php foreach (NL_MOTIVI as $k => $m): ?><option value="motivo:<?= h($k) ?>"<?= $f['stato'] === 'motivo:' . $k ? ' selected' : '' ?>>In lista nera: <?= h(lcfirst($m[0])) ?></option><?php endforeach; ?>
        <option value="errori"<?= $f['stato'] === 'errori' ? ' selected' : '' ?>>Iscritti con 3 errori temporanei di fila</option>
      </select></div>
    <div class="form-group"><label for="nlx-per">Per pagina</label>
      <select id="nlx-per" name="per"><?php foreach ([50, 100, 500] as $x): ?><option value="<?= $x ?>"<?= $per === $x ? ' selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select></div>
    <input type="hidden" name="f" value="<?= h($f['f']) ?>">
    <div class="nlx-filtri-azioni"><button class="btn btn-secondary btn-sm" type="submit">Cerca</button>
      <?php if ($qBase && array_diff_key($qBase, ['per' => 1])): ?><a href="newsletter-contatti.php">Togli i filtri</a><?php endif; ?></div>
  </form>
  <?php if (!empty($f['errore_filtro'])): ?><p class="errore-campo" role="alert">Il filtro non si è potuto usare: <?= h($f['errore_filtro']) ?></p><?php endif; ?>
  <?php if ($regoleF): ?><p class="nl-avviso" role="status">Filtro: <?= h(newsletter_regole_testo($regoleF)) ?>.</p><?php endif; ?>

  <details class="nlx-riquadro-filtro" id="nlx-filtro"<?= $regoleF || isset($_GET['filtro']) ? ' open' : '' ?>>
    <summary><?= $regoleF ? 'Cambia il filtro' : 'Aggiungi filtro' ?></summary>
    <form method="get" class="nlx-filtro-form" aria-label="Filtra i contatti con delle condizioni">
      <input type="hidden" name="cerca" value="<?= h($f['cerca']) ?>"><input type="hidden" name="stato" value="<?= h($f['stato']) ?>"><input type="hidden" name="lista" value="<?= (int)$f['lista'] ?>"><input type="hidden" name="segmento" value="<?= (int)$f['segmento'] ?>"><input type="hidden" name="per" value="<?= $per ?>">
      <input type="hidden" name="f" id="nlx-f" value="<?= h($f['f']) ?>">
      <p class="hint">Le stesse condizioni dei segmenti: dati del contatto, liste, email aperte o cliccate, form inviati, lista nera. Il filtro vale insieme alla ricerca e alla lista caricate sopra.</p>
      <div class="nls-editor" data-nls-editor="#nlx-f" data-nls-conta="newsletter-segmenti.php" data-csrf="<?= h(csrf_token()) ?>"></div>
      <p class="nls-numero" data-nls-numero role="status" aria-live="polite"></p>
      <div class="nl-riga">
        <button class="btn btn-primary btn-sm" type="submit">Applica il filtro</button>
        <button class="btn btn-secondary btn-sm" type="button" data-apri-dialogo="#nls-salva">Salva come segmento</button>
        <?php if ($regoleF): ?><a href="<?= h('newsletter-contatti.php?' . http_build_query(array_diff_key($qBase, ['f' => 1]))) ?>">Togli il filtro</a><?php endif; ?>
      </div>
    </form>
  </details>

  <form method="post" id="nlx-gruppo" class="nlx-gruppo" aria-label="Azioni sui contatti scelti">
    <?= csrf_field() ?><input type="hidden" name="azione" value="gruppo"><?= $nascosti ?>
    <fieldset>
      <legend>Azioni sui contatti scelti</legend>
      <div class="nl-riga">
        <label class="nlx-tutti"><input type="checkbox" name="tutti" value="1" id="nlx-tutti"> Tutti i contatti <?= $qBase && array_diff_key($qBase, ['per' => 1]) ? 'del filtro' : '' ?> (<b><?= $totale ?></b>), non solo quelli spuntati in questa pagina</label>
      </div>
      <div class="nlx-campi">
        <div class="form-group"><label for="nlx-cosa">Azione</label>
          <select name="cosa" id="nlx-cosa" data-nlx-azione>
            <option value="">Scegli...</option>
            <option value="aggiungi">Aggiungi a una lista</option>
            <option value="togli">Togli dalle liste</option>
            <option value="sposta"<?= $f['lista'] > 0 ? '' : ' disabled' ?>>Sposta in un'altra lista<?= $f['lista'] > 0 ? '' : ' (carica prima una lista)' ?></option>
            <option value="blocca">Metti in lista nera</option>
            <option value="modifica"<?= $modificabili ? '' : ' disabled' ?>>Modifica un attributo<?= $modificabili ? '' : ' (nessun attributo Data, Sì/No o Categoria)' ?></option>
            <option value="esporta">Esporta (CSV)</option>
            <option value="elimina">Elimina definitivamente</option>
          </select></div>
        <div class="form-group" data-nlx-per="aggiungi sposta"><label for="nlx-lista-dest">Lista di arrivo</label><?= $tendinaListe('lista_dest', 'Scegli la lista...', 0, '', 'nlx-lista-dest') ?></div>
        <div class="form-group" data-nlx-per="togli"><label for="nlx-lista-da">Da quale lista</label><?= $tendinaListe('lista_da', 'Da tutte le liste', (int)max(0, $f['lista']), '', 'nlx-lista-da') ?></div>
        <div class="form-group" data-nlx-per="modifica"><label for="nlx-attr">Attributo</label>
          <select name="attributo" id="nlx-attr"><?php foreach ($modificabili as $n => $a): ?><option value="<?= h($n) ?>" data-tipo="<?= h($a['tipo']) ?>"><?= h(newsletter_attributo_etichetta($a)) ?> (<?= h(NL_ATTR_TIPI[$a['tipo']]) ?>)</option><?php endforeach; ?></select></div>
        <div class="form-group" data-nlx-per="modifica"><label for="nlx-valore">Nuovo valore</label>
          <input type="text" name="valore" id="nlx-valore" placeholder="per esempio: 2026-12-31, sì, oppure il nome dell'opzione" aria-describedby="nlx-valore-aiuto">
          <div class="hint" id="nlx-valore-aiuto">Data come 2026-12-31 o 31/12/2026; Sì/No come sì o no; Categoria con il nome dell'opzione. Vuoto toglie il valore.</div></div>
      </div>
      <button class="btn btn-secondary btn-sm" type="submit" id="nlx-applica" data-conferma="Applicare l'azione ai contatti scelti?">Applica</button>
    </fieldset>
    <?php $espCol = newsletter_esporta_colonne(); $espCamp = newsletter_esporta_campagne(); ?>
    <dialog id="nlx-esporta" class="nl-dialogo" aria-labelledby="nlx-esporta-titolo"<?= isset($_GET['esporta']) ? ' data-nlx-apri' : '' ?>>
      <h2 id="nlx-esporta-titolo">Esporta i contatti</h2>
      <p class="hint">Un file CSV da aprire con Excel, LibreOffice o Fogli Google. Le celle che cominciano con = + - o @ escono con un apostrofo davanti, così il foglio di calcolo non le prende per formule.</p>
      <fieldset class="nlk-scelte">
        <legend>Quali contatti</legend>
        <label><input type="radio" name="esp_origine" value="scelti"<?= $segmenti && isset($_GET['esporta']) && $f['segmento'] ? '' : ' checked' ?> data-nlx-origine> I contatti scelti nella tabella (le righe spuntate, oppure «Tutti i contatti»)</label>
        <label><input type="radio" name="esp_origine" value="lista" data-nlx-origine> Una lista</label>
        <div class="nlx-campi" data-nlx-origine-per="lista">
          <div class="form-group"><label for="nlx-esp-lista">Lista</label><?= $tendinaListe('esp_lista', '', max(0, (int)$f['lista']), '', 'nlx-esp-lista') ?></div>
          <div class="form-group"><label for="nlx-esp-lista-chi">Chi</label><select id="nlx-esp-lista-chi" name="esp_lista_chi"><?php foreach (NL_ESP_LISTA as $k => $v): ?><option value="<?= $k ?>"><?= h(ucfirst($v)) ?></option><?php endforeach; ?></select></div>
        </div>
        <label><input type="radio" name="esp_origine" value="segmento" data-nlx-origine<?= $segmenti ? '' : ' disabled' ?><?= $segmenti && isset($_GET['esporta']) && $f['segmento'] ? ' checked' : '' ?>> Un segmento<?= $segmenti ? '' : ' (ancora nessun segmento)' ?></label>
        <?php if ($segmenti): ?><div class="nlx-campi" data-nlx-origine-per="segmento">
          <div class="form-group"><label for="nlx-esp-seg">Segmento</label><select id="nlx-esp-seg" name="esp_segmento"><?php foreach ($nomeSeg as $sid => $sn): ?><option value="<?= (int)$sid ?>"<?= (int)$f['segmento'] === (int)$sid ? ' selected' : '' ?>><?= h($sn) ?></option><?php endforeach; ?></select></div>
        </div><?php endif; ?>
        <label><input type="radio" name="esp_origine" value="campagna" data-nlx-origine<?= $espCamp ? '' : ' disabled' ?>> Una campagna<?= $espCamp ? '' : ' (nessuna campagna ancora partita)' ?></label>
        <?php if ($espCamp): ?><div class="nlx-campi" data-nlx-origine-per="campagna">
          <div class="form-group"><label for="nlx-esp-camp">Campagna</label><select id="nlx-esp-camp" name="esp_campagna"><?php foreach ($espCamp as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori((string)$c['oggetto']) ?: '(senza oggetto)') ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label for="nlx-esp-camp-chi">Chi</label><select id="nlx-esp-camp-chi" name="esp_campagna_chi"><?php foreach (NL_ESP_CAMPAGNA as $k => $v): ?><option value="<?= $k ?>"><?= h(ucfirst($v)) ?></option><?php endforeach; ?></select></div>
        </div><?php endif; ?>
      </fieldset>
      <fieldset class="nlk-scelte nlx-esp-colonne">
        <legend>Attributi da esportare</legend>
        <?php foreach ($espCol as $n => $et): ?><label><input type="checkbox" name="esp_col[]" value="<?= h($n) ?>" checked<?= $n === 'EMAIL' ? ' disabled' : '' ?>> <?= h($et) ?> <small>(<?= h($n) ?>)</small></label><?php endforeach; ?>
        <p class="hint">EMAIL c'è sempre.</p>
      </fieldset>
      <fieldset class="nlk-scelte">
        <legend>Separatore delle colonne</legend>
        <label><input type="radio" name="esp_sep" value=";" checked> Punto e virgola (Excel in italiano)</label>
        <label><input type="radio" name="esp_sep" value=","> Virgola</label>
      </fieldset>
      <p class="errore-campo" id="nlx-esporta-err" role="alert" hidden></p>
      <div class="pn-conferma-azioni"><button type="button" class="btn btn-secondary" data-chiudi-dialogo="#nlx-esporta">Annulla</button><button type="submit" class="btn btn-primary" name="esporta_avvia" value="1" id="nlx-esporta-avvia">Avvia l'esportazione</button></div>
    </dialog>
  </form>

  <div class="ad-tabella"><table class="table nlx-tabella">
    <thead><tr><th scope="col"><input type="checkbox" data-seleziona-tutti=".nlx-sel" aria-label="Spunta tutti i contatti di questa pagina"></th>
      <?php foreach (NL_COLONNE_FISSE as $k => $v): ?><th scope="col"><?= h($v) ?></th><?php endforeach; ?>
      <?php foreach ($colonne as $n): ?><th scope="col"><?= h(newsletter_attributo_etichetta($attributi[$n])) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($righe as $r): $id = (int)$r['id']; $nera = in_array($r['stato'], NL_BLOCCATI, true); ?>
      <tr>
        <td><input type="checkbox" class="nlx-sel" name="sel[]" value="<?= $id ?>" form="nlx-gruppo" aria-label="Scegli <?= h($r['email']) ?>"></td>
        <td><a href="newsletter-contatto.php?id=<?= $id ?>"><?= h($r['email']) ?></a><?php $nm = trim(newsletter_4byte_fuori($r['nome'] . ' ' . $r['cognome'])); if ($nm !== ''): ?><span class="nlx-sotto"><?= h($nm) ?></span><?php endif; ?></td>
        <td><?= $r['stato'] === 'attivo' ? '<span class="nlx-stato nlx-si">&#10003; Sì</span>' : ($r['stato'] === 'in_attesa' ? '<span class="nlx-stato">&#8230; In attesa di conferma</span>' : '<span class="nlx-stato">No</span>') ?>
          <?php if ($r['stato'] === 'attivo' && (int)$r['errori_di_fila'] >= 3): ?><span class="nl-errori" title="<?= h($r['ultimo_errore']) ?>"><?= (int)$r['errori_di_fila'] ?> errori temporanei di fila</span><?php endif; ?></td>
        <td><?= $nera ? '<span class="nlx-stato nlx-nera">&#10007; Sì</span><span class="nlx-sotto">' . h(newsletter_motivo_testo($r)) . ($r['blocco_at'] ? ', ' . h(date('d/m/Y', strtotime($r['blocco_at']))) : '') . '</span>' : 'No' ?></td>
        <td><?= h(date('d/m/Y H:i', strtotime($r['creato']))) ?></td>
        <td><?= h(date('d/m/Y H:i', strtotime($r['aggiornato']))) ?></td>
        <?php foreach ($colonne as $n): ?><td><?= h(newsletter_valore_testo($attributi[$n], $valori[$id][$n] ?? '')) ?></td><?php endforeach; ?>
      </tr>
    <?php endforeach; if (!$righe): ?>
      <tr><td colspan="<?= 6 + count($colonne) ?>"><div class="empty-state">
        <?php if ($qBase && array_diff_key($qBase, ['per' => 1])): ?><p>Nessun contatto con questi filtri<?= $f['cerca'] !== '' ? ' per «' . h($f['cerca']) . '»' : '' ?>. Prova una parte più corta dell'email, oppure <a href="newsletter-contatti.php">togli i filtri</a>.</p>
        <?php else: ?><p>Ancora nessun contatto. Creane uno con «Crea un contatto», oppure importa un file con «Importa contatti».</p><?php endif; ?>
      </div></td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
  <?php if ($pagine > 1): $link = fn($p) => 'newsletter-contatti.php?' . http_build_query($qLink + ['pag' => $p]); ?>
  <nav class="nlx-pagine" aria-label="Pagine dei contatti">
    <?php if ($pag > 1): ?><a class="btn btn-secondary btn-sm" href="<?= h($link(1)) ?>">Prima pagina</a> <a class="btn btn-secondary btn-sm" href="<?= h($link($pag - 1)) ?>">Pagina precedente</a><?php endif; ?>
    <span>Pagina <?= $pag ?> di <?= $pagine ?> (<?= $totale ?> contatti)</span>
    <?php if ($pag < $pagine): ?><a class="btn btn-secondary btn-sm" href="<?= h($link($pag + 1)) ?>">Pagina successiva</a><?php endif; ?>
  </nav>
  <?php endif; ?>
  <p class="hint"><a href="?scarica=tutti">Scarica tutti i contatti (CSV, tutte le colonne)</a></p>
</div>

<span id="nlx-importa" data-spostato="newsletter-importa.php" hidden></span>
<?php $esportazioni = db()->query('SELECT * FROM ' . tbl('newsletter_esportazioni') . ' ORDER BY id DESC LIMIT 10')->fetchAll();
  $inCorso = array_filter($esportazioni, fn($e) => $e['stato'] === 'in_corso');
  if ($esportazioni): ?>
<div class="form-card" id="nlx-esportazioni" style="margin-top:1.25rem"<?= $inCorso ? ' data-nl-giro="newsletter-contatti.php" data-azione="esporta_giro" data-csrf="' . h(csrf_token()) . '"' : '' ?>>
  <h3>Esportazioni</h3>
  <p class="hint" style="margin-top:0">I file restano qui <?= NL_FILE_GIORNI ?> giorni, poi si cancellano da soli: contengono dati personali.</p>
  <?php if ($inCorso): ?><div class="nli-barra" role="progressbar" aria-label="Avanzamento delle esportazioni" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-nl-barra><span style="width:0"></span></div>
  <p data-nl-testo role="status">Preparo il file...</p><p class="errore-campo" data-nl-errore role="alert" hidden></p><button class="btn btn-secondary btn-sm" type="button" data-nl-riprendi hidden>Riprendi</button><?php endif; ?>
  <div class="ad-tabella"><table class="table">
    <thead><tr><th scope="col">Quando</th><th scope="col">Cosa</th><th scope="col">Contatti</th><th scope="col">Stato</th><th scope="col">Scarica</th></tr></thead>
    <tbody><?php foreach ($esportazioni as $e): ?>
      <tr><td><?= h(date('d/m/Y H:i', strtotime($e['creato']))) ?></td><td><?= h(newsletter_4byte_fuori($e['descrizione'])) ?></td><td><?= (int)$e['righe'] ?><?= $e['stato'] === 'in_corso' ? ' di ' . (int)$e['totale'] : '' ?></td>
        <td><?= $e['stato'] === 'finita' ? 'Pronta, fino al ' . h(date('d/m/Y', strtotime($e['scade']))) : ($e['stato'] === 'in_corso' ? 'In preparazione' : 'Fermata da un errore') ?></td>
        <td><?php if ($e['stato'] === 'finita'): ?><a class="btn btn-secondary btn-sm" href="newsletter-contatti.php?esportazione=<?= (int)$e['id'] ?>" aria-label="Scarica il CSV del <?= h(date('d/m/Y H:i', strtotime($e['creato']))) ?>">Scarica il CSV</a><?php else: ?>&#8230;<?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<details class="form-card nlx-riquadro" id="nlx-nera">
  <summary><h3>Metti in lista nera degli indirizzi</h3></summary>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="lista_nera_indirizzi">
    <div class="form-group"><label for="nlx-indirizzi">Indirizzi email</label>
      <textarea id="nlx-indirizzi" name="indirizzi" rows="4" class="nlx-corto" placeholder="per esempio: chi.non.vuole@esempio.it" aria-describedby="nlx-indirizzi-aiuto"></textarea>
      <div class="hint" id="nlx-indirizzi-aiuto">Uno per riga, fino a 1000. Chi non è ancora un contatto diventa un contatto in lista nera: non riceverà mai campagne né automazioni. Motivo: messo in lista nera a mano.</div></div>
    <button class="btn btn-primary btn-sm" type="submit">Metti in lista nera</button>
  </form>
</details>

<dialog id="nlx-colonne" class="nl-dialogo" aria-labelledby="nlx-colonne-titolo">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="colonne"><?= $nascosti ?>
    <h2 id="nlx-colonne-titolo">Personalizza le colonne</h2>
    <p class="hint">Le cinque colonne Contatto, Iscritto, In lista nera, Data di creazione e Ultima modifica ci sono sempre. Spunta gli attributi da aggiungere e mettili in ordine trascinandoli o con le frecce.</p>
    <ul class="nlx-ordine" data-nlx-ordina>
      <?php $ordine = array_merge($colonne, array_values(array_diff(array_keys(array_filter($attributi, fn($a) => $a['categoria'] !== 'global' && !in_array($a['nome'], ['CREATION_DATE', 'LAST_CHANGED'], true))), $colonne)));
      foreach ($ordine as $n): $a = $attributi[$n]; ?>
      <li><span class="nlx-maniglia" aria-hidden="true">&#8942;&#8942;</span>
        <label><input type="checkbox" name="colonne[]" value="<?= h($n) ?>"<?= in_array($n, $colonne, true) ? ' checked' : '' ?>> <?= h(newsletter_attributo_etichetta($a)) ?> <small>(<?= h($n) ?>)</small></label>
        <button type="button" class="btn btn-secondary btn-sm" data-nlx-su aria-label="Sposta su <?= h(newsletter_attributo_etichetta($a)) ?>">&#8593;</button>
        <button type="button" class="btn btn-secondary btn-sm" data-nlx-giu aria-label="Sposta giù <?= h(newsletter_attributo_etichetta($a)) ?>">&#8595;</button></li>
      <?php endforeach; ?>
    </ul>
    <div class="pn-conferma-azioni"><button type="button" class="btn btn-secondary" data-chiudi-dialogo="#nlx-colonne">Annulla</button><button type="submit" class="btn btn-primary">Salva le colonne</button></div>
  </form>
</dialog>

<dialog id="nls-salva" class="nl-dialogo" aria-labelledby="nls-salva-titolo">
  <form method="post" action="newsletter-segmenti.php">
    <?= csrf_field() ?><input type="hidden" name="azione" value="salva"><input type="hidden" name="torna" value="contatti">
    <input type="hidden" name="regole" value="<?= h($f['f']) ?>" data-nls-copia="#nlx-f">
    <h2 id="nls-salva-titolo">Salva come segmento</h2>
    <p class="hint">Il segmento tiene le condizioni del filtro (non la ricerca né la lista caricata) e si aggiorna da solo.</p>
    <?php $segCart = newsletter_seg_cartelle(); ?>
    <div class="form-group"><label for="nls-salva-nome">Nome del segmento</label><input type="text" id="nls-salva-nome" name="nome" maxlength="100" required placeholder="per esempio: Clienti di Roma"></div>
    <div class="form-group"><label for="nls-salva-cart">Categoria</label><select id="nls-salva-cart" name="cartella"><option value="0">Senza categoria</option><?php foreach ($segCart as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori($c['nome'])) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="nls-salva-nuova">Oppure una categoria nuova</label><input type="text" id="nls-salva-nuova" name="cartella_nuova" maxlength="100" placeholder="per esempio: Vendite"></div>
    <div class="pn-conferma-azioni"><button type="button" class="btn btn-secondary" data-chiudi-dialogo="#nls-salva">Annulla</button><button type="submit" class="btn btn-primary">Salva il segmento</button></div>
  </form>
</dialog>

<dialog id="nlv-salva" class="nl-dialogo" aria-labelledby="nlv-salva-titolo">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="vista_salva"><?= $nascosti ?>
    <?php foreach ($colonne as $n): ?><input type="hidden" name="colonne_vista[]" value="<?= h($n) ?>"><?php endforeach; ?>
    <h2 id="nlv-salva-titolo">Salva come vista</h2>
    <p class="hint">Una vista ricorda i filtri di adesso (ricerca, lista, segmento, stato, filtro) e le colonne della tabella. La trovi fra le schede sopra la tabella. Le viste sono tue: gli altri utenti non le vedono.</p>
    <div class="form-group"><label for="nlv-nome">Nome della vista</label><input type="text" id="nlv-nome" name="nome_vista" maxlength="100" required value="<?= $vistaId ? h(newsletter_4byte_fuori($viste[$vistaId]['nome'])) : '' ?>" placeholder="per esempio: Clienti attivi"></div>
    <div class="pn-conferma-azioni"><button type="button" class="btn btn-secondary" data-chiudi-dialogo="#nlv-salva">Annulla</button><button type="submit" class="btn btn-primary">Salva la vista</button></div>
  </form>
  <?php if ($vistaId): ?><form method="post" data-conferma="Eliminare la vista «<?= h(newsletter_4byte_fuori($viste[$vistaId]['nome'])) ?>»? I contatti restano." data-conferma-pericolo data-conferma-ok="Elimina la vista" style="margin-top:.75rem">
    <?= csrf_field() ?><input type="hidden" name="azione" value="vista_elimina"><input type="hidden" name="vista_id" value="<?= $vistaId ?>">
    <button class="btn btn-danger btn-sm" type="submit">Elimina questa vista</button></form><?php endif; ?>
</dialog>
<script type="application/json" id="nls-def"><?= json_encode(newsletter_regole_definizione(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>

<?php else: /* Liste */ ?>
<div class="ut-griglia">
  <div>
    <form method="post" class="form-card">
      <?= csrf_field() ?><input type="hidden" name="azione" value="lista_crea">
      <h3>Crea una lista</h3>
      <div class="form-group"><label for="nll-nome">Nome della lista</label><input type="text" id="nll-nome" name="nome" maxlength="100" required placeholder="per esempio: Clienti"></div>
      <div class="form-group"><label for="nll-cart">Cartella</label><select id="nll-cart" name="cartella"><?php foreach ($cartelle as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori($c['nome'])) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="nll-descr">Descrizione</label><input type="text" id="nll-descr" name="descrizione" maxlength="255" placeholder="facoltativa"></div>
      <button class="btn btn-primary btn-sm" type="submit">Crea la lista</button>
    </form>
    <form method="post" class="form-card" style="margin-top:1rem">
      <?= csrf_field() ?><input type="hidden" name="azione" value="cartella_crea">
      <h3>Crea una cartella</h3>
      <div class="form-group"><label for="nlx-cart-nome">Nome della cartella</label><input type="text" id="nlx-cart-nome" name="nome" maxlength="100" required placeholder="per esempio: Eventi 2026"></div>
      <button class="btn btn-primary btn-sm" type="submit">Crea la cartella</button>
    </form>
    <p class="hint" style="margin-top:1rem">Ogni lista sta in una cartella. Un contatto può stare in più liste o in nessuna; una campagna va alle liste che scegli, e chi sta in due di quelle liste la riceve una volta sola. Al massimo <?= NL_LISTE_MASSIMO ?> liste e <?= NL_CARTELLE_MASSIMO ?> cartelle.</p>
  </div>
  <div>
  <?php foreach ($cartelle as $c): $cid = (int)$c['id']; $nomeC = newsletter_4byte_fuori($c['nome']); ?>
    <section class="form-card nll-cartella" aria-labelledby="nll-c<?= $cid ?>">
      <h3 id="nll-c<?= $cid ?>"><?= h($nomeC) ?></h3>
      <div class="nl-riga" style="margin-bottom:.75rem">
        <form method="post" class="nl-riga"><?= csrf_field() ?><input type="hidden" name="azione" value="cartella_rinomina"><input type="hidden" name="id" value="<?= $cid ?>">
          <label class="sr-solo" for="nll-rc<?= $cid ?>">Nuovo nome della cartella <?= h($nomeC) ?></label>
          <input type="text" id="nll-rc<?= $cid ?>" name="nome" value="<?= h($nomeC) ?>" maxlength="100" class="nll-campo">
          <button class="btn btn-secondary btn-sm" type="submit">Rinomina la cartella</button></form>
        <form method="post" data-conferma="Eliminare la cartella «<?= h($nomeC) ?>» e le sue <?= count($c['liste']) ?> liste? I contatti restano. Non si torna indietro." data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="cartella_elimina"><input type="hidden" name="id" value="<?= $cid ?>">
          <button class="btn btn-danger btn-sm" type="submit">Elimina la cartella</button></form>
      </div>
      <?php if ($c['liste']): ?>
      <div class="ad-tabella"><table class="table">
        <thead><tr><th scope="col">Lista</th><th scope="col">Iscritti</th><th scope="col">Contatti in tutto</th><th scope="col">Creata il</th><th scope="col">Azioni</th></tr></thead>
        <tbody>
        <?php foreach ($c['liste'] as $l): $lid = (int)$l['id']; $nomeL = newsletter_4byte_fuori($l['nome']); ?>
          <tr>
            <td><a href="newsletter-contatti.php?lista=<?= $lid ?>"><b><?= h($nomeL) ?></b></a><?php if ($l['descrizione'] !== ''): ?><span class="nlx-sotto"><?= h(newsletter_4byte_fuori($l['descrizione'])) ?></span><?php endif; ?>
              <?php if (in_array($lid, $sistemaListe, true)): ?><span class="nlx-sotto">usata dal sito per le iscrizioni</span><?php endif; ?></td>
            <td><a href="newsletter-contatti.php?lista=<?= $lid ?>&amp;stato=iscritti"><?= (int)$l['attivi'] ?></a></td>
            <td><a href="newsletter-contatti.php?lista=<?= $lid ?>"><?= (int)$l['totali'] ?></a></td>
            <td><?= h(date('d/m/Y', strtotime($l['creato']))) ?></td>
            <td><details class="nll-modifica"><summary class="btn btn-secondary btn-sm">Modifica</summary>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="lista_modifica"><input type="hidden" name="id" value="<?= $lid ?>">
                <div class="form-group"><label for="nll-n<?= $lid ?>">Nome</label><input type="text" id="nll-n<?= $lid ?>" name="nome" value="<?= h($nomeL) ?>" maxlength="100"></div>
                <div class="form-group"><label for="nll-d<?= $lid ?>">Descrizione</label><input type="text" id="nll-d<?= $lid ?>" name="descrizione" value="<?= h(newsletter_4byte_fuori($l['descrizione'])) ?>" maxlength="255"></div>
                <div class="form-group"><label for="nll-k<?= $lid ?>">Cartella</label><select id="nll-k<?= $lid ?>" name="cartella"><?php foreach ($cartelle as $c2): ?><option value="<?= (int)$c2['id'] ?>"<?= (int)$c2['id'] === $cid ? ' selected' : '' ?>><?= h(newsletter_4byte_fuori($c2['nome'])) ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-primary btn-sm" type="submit">Salva la lista</button></form>
              <?php if (!in_array($lid, $sistemaListe, true)): ?><form method="post" data-conferma="Eliminare la lista «<?= h($nomeL) ?>»? I contatti restano, nelle altre loro liste."><?= csrf_field() ?><input type="hidden" name="azione" value="lista_elimina"><input type="hidden" name="id" value="<?= $lid ?>">
                <button class="btn btn-danger btn-sm" type="submit" style="margin-top:.5rem">Elimina la lista</button></form><?php endif; ?>
            </details></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php else: ?><p class="empty-state" style="padding:1rem">Cartella vuota: crea una lista qui accanto e scegli questa cartella.</p><?php endif; ?>
    </section>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<script src="<?= pannello_file('assets/editor/Sortable.min.js') ?>"></script>
<script src="<?= pannello_file('assets/contatti.js') ?>"></script>
<script src="<?= pannello_file('assets/segmenti.js') ?>"></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
