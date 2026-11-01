<?php
/**
 * 2.8.0: Newsletter e form > Contatti, come Brevo (CRM > Contacts): schede Contatti e Liste.
 *   Contatti: le cinque colonne fisse, Personalizza le colonne, ricerca, Carica una lista, filtro dello stato, 50/100/500
 *             per pagina, selezione (anche di tutti quelli del filtro) e le azioni di gruppo: Aggiungi a liste, Togli dalle
 *             liste, Sposta, Metti in lista nera, Modifica (solo data, sì/no, categoria), Esporta, Elimina definitivamente.
 *             Crea un contatto, Importa contatti, Metti in lista nera degli indirizzi.
 *   Liste:    le cartelle con le loro liste; crea, rinomina, sposta in un'altra cartella, elimina.
 * Motore in includes/newsletter-contatti.php. Solo amministratore.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();

$adminTitle = 'Contatti';
$scheda = ($_GET['scheda'] ?? '') === 'liste' ? 'liste' : 'contatti';
$utenteId = (int)(current_user()['id'] ?? 0);

/** Le colonne scelte da questo utente (nomi di attributi), nell'ordine scelto. */
function nlc_colonne(int $u): array {
    $c = json_decode((string)get_setting('newsletter_colonne_u' . $u, ''), true);
    $attr = newsletter_attributi();
    return array_values(array_filter(is_array($c) ? $c : [], fn($n) => is_string($n) && isset($attr[$n]) && $attr[$n]['categoria'] !== 'global'));
}

/** Dove tornare dopo un'azione: la stessa scheda con gli stessi filtri. */
function nlc_torna(array $q, string $scheda = 'contatti'): never {
    $f = newsletter_contatti_filtro($q)[2];
    $qs = ['scheda' => $scheda] + array_filter($f) + (isset($q['per']) ? ['per' => (int)$q['per']] : []);
    header('Location: newsletter-contatti.php?' . http_build_query($qs));
    exit;
}

/** Gli id su cui lavora un'azione di gruppo: tutti quelli del filtro, oppure le righe spuntate. */
function nlc_scelti(array $p): array {
    if (!empty($p['tutti'])) return newsletter_contatti_ids($p);
    return array_values(array_unique(array_filter(array_map('intval', (array)($p['sel'] ?? [])))));
}

if (($_GET['scarica'] ?? '') === 'tutti') {
    nlc_esporta(newsletter_contatti_ids([]), 'contatti');
}

/** Il CSV dei contatti (Brevo: Esporta): EMAIL, gli attributi, lo stato, il motivo della lista nera, le liste. */
function nlc_esporta(array $ids, string $nome): never {
    $attr = array_filter(newsletter_attributi(), fn($a) => $a['categoria'] !== 'global');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, array_merge(array_keys($attr), ['ISCRITTO', 'LISTA_NERA', 'MOTIVO', 'LISTE']), ';', '"', '\\');
    $nomiListe = array_column(newsletter_liste(), 'nome', 'id');
    foreach (array_chunk($ids, 500) as $pezzo) {
        $valori = newsletter_valori_di($pezzo);
        $righe = [];
        foreach (db()->query('SELECT * FROM ' . tbl('newsletter_iscritti') . ' WHERE id IN (' . implode(',', array_map('intval', $pezzo)) . ')') as $r) $righe[(int)$r['id']] = $r;
        foreach ($pezzo as $id) {
            if (!isset($righe[$id])) continue;
            $r = $righe[$id];
            $out = [];
            foreach ($attr as $n => $a) $out[] = $a['tipo'] === 'date' ? (string)($valori[$id][$n] ?? '') : newsletter_valore_testo($a, $valori[$id][$n] ?? '');
            $out[] = $r['stato'] === 'attivo' ? 'sì' : 'no';
            $out[] = in_array($r['stato'], NL_BLOCCATI, true) ? 'sì' : 'no';
            $out[] = newsletter_motivo_testo($r);
            $out[] = implode(', ', array_map(fn($l) => $nomiListe[$l] ?? '', newsletter_liste_di($id)));
            fputcsv($f, sicurezza_csv_riga($out), ';', '"', '\\');
        }
    }
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
        $ids = nlc_scelti($_POST);
        $cosa = (string)($_POST['cosa'] ?? '');
        $lista = (int)($_POST['lista_dest'] ?? 0);
        if (!$ids) { flash('error', 'Spunta almeno un contatto, oppure scegli "tutti i contatti del filtro".'); nlc_torna($_POST); }
        if ($cosa === 'esporta') nlc_esporta($ids, 'contatti-scelti');
        if ($cosa === 'aggiungi') {
            if (!$lista || newsletter_nome_lista($lista) === '') { flash('error', 'Scegli la lista in cui aggiungere i contatti.'); nlc_torna($_POST); }
            foreach ($ids as $i) newsletter_in_lista($i, $lista);
            flash('success', count($ids) . ' contatti aggiunti alla lista «' . newsletter_nome_lista($lista) . '».');
        } elseif ($cosa === 'togli') {
            $da = (int)($_POST['lista_da'] ?? 0);
            $n = newsletter_togli_da_liste($ids, $da > 0 ? [$da] : []);
            flash('success', $da > 0 ? "$n contatti tolti dalla lista «" . newsletter_nome_lista($da) . '».' : "Contatti tolti da tutte le loro liste ($n appartenenze).");
        } elseif ($cosa === 'sposta') {
            // Brevo: Sposta = aggiungi alla lista nuova e togli dalla lista d'origine (quella caricata).
            $da = (int)($_POST['lista'] ?? 0);
            if ($da <= 0) { flash('error', 'Per spostare, carica prima la lista d\'origine con "Carica una lista", poi spunta i contatti.'); nlc_torna($_POST); }
            if (!$lista || $lista === $da || newsletter_nome_lista($lista) === '') { flash('error', 'Scegli la lista di arrivo, diversa da quella caricata.'); nlc_torna($_POST); }
            foreach ($ids as $i) newsletter_in_lista($i, $lista);
            newsletter_togli_da_liste($ids, [$da]);
            flash('success', count($ids) . ' contatti spostati da «' . newsletter_nome_lista($da) . '» a «' . newsletter_nome_lista($lista) . '».');
        } elseif ($cosa === 'blocca') {
            $n = 0;
            $st = db()->prepare('SELECT stato FROM ' . tbl('newsletter_iscritti') . ' WHERE id = ?');
            foreach ($ids as $i) { $st->execute([$i]); if (!in_array($st->fetchColumn(), NL_BLOCCATI, true) && newsletter_contatto_blocca($i, 'adminBlocked')) $n++; }
            flash('success', "$n contatti messi in lista nera: non ricevono più campagne né automazioni." . ($n < count($ids) ? ' Gli altri erano già in lista nera.' : ''));
        } elseif ($cosa === 'modifica') {
            $n = newsletter_modifica_in_massa($ids, (string)($_POST['attributo'] ?? ''), $_POST['valore'] ?? '');
            $n < 0 ? flash('error', 'Il valore non va bene per questo attributo, oppure l\'attributo non si può cambiare in massa (come in Brevo, solo Data, Sì/No e Categoria).')
                : flash('success', "Attributo cambiato a $n contatti.");
        } elseif ($cosa === 'elimina') {
            $n = newsletter_contatti_elimina($ids);
            flash('success', "$n contatti eliminati definitivamente, con attributi, cronologia e statistiche. Le loro email restano nella lista di soppressione: un'importazione non li rimetterà dentro.");
        } else {
            flash('error', 'Scegli un\'azione.');
        }
        nlc_torna($_POST);
    }
    if ($azione === 'importa') {
        $f = $_FILES['csv'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Scegli il file CSV con i contatti (per esempio esportato da Brevo).');
        } elseif (empty($_POST['consenso_brevo'])) {
            flash('error', 'Serve la spunta: i contatti importati devono avere dato il loro consenso.');
        } else {
            $lista = (int)($_POST['lista'] ?? 0);
            $nuova = trim((string)($_POST['lista_nuova'] ?? ''));
            if ($nuova !== '') {
                $r = newsletter_lista_crea($nuova, (int)($_POST['cartella'] ?? 0));
                if (is_int($r)) $lista = $r;
                else { $st = db()->prepare('SELECT id FROM ' . tbl('newsletter_liste') . ' WHERE nome = ?'); $st->execute([newsletter_testo_pulito($nuova, 100)]); $lista = (int)$st->fetchColumn(); }
            }
            // 2.0.0: i flussi della lista partono per gli importati solo con la spunta (spenta di partenza).
            $r = newsletter_importa_csv($f['tmp_name'], $lista ?: newsletter_lista_principale(), !empty($_POST['flussi_import']));
            isset($r['errore']) ? flash('error', $r['errore'])
                : flash('success', "Importati {$r['nuovi']} contatti nuovi. Già presenti: {$r['gia']} (lasciati com'erano). Saltati perché in lista nera o con i dati cancellati: {$r['saltati']}. Righe scartate perché senza email valida: {$r['scartati']}."
                    . (!empty($_POST['flussi_import']) ? ' I flussi della lista sono partiti per gli importati.' : ''));
        }
        nlc_torna([]);
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
</nav>

<?php if ($scheda === 'contatti'):
  [$where, $par, $f] = newsletter_contatti_filtro($_GET);
  $per = in_array((int)($_GET['per'] ?? 50), [50, 100, 500], true) ? (int)($_GET['per'] ?? 50) : 50;
  $tot = db()->prepare('SELECT COUNT(*) FROM ' . tbl('newsletter_iscritti') . " i$where");
  $tot->execute($par);
  $totale = (int)$tot->fetchColumn();
  $pagine = max(1, (int)ceil($totale / $per));
  $pag = min($pagine, max(1, (int)($_GET['pag'] ?? 1)));
  // Brevo: ordine per data di creazione, dalla più recente.
  $st = db()->prepare('SELECT i.* FROM ' . tbl('newsletter_iscritti') . " i$where ORDER BY i.creato DESC, i.id DESC LIMIT $per OFFSET " . (($pag - 1) * $per));
  $st->execute($par);
  $righe = $st->fetchAll();
  $colonne = nlc_colonne($utenteId);
  $valori = $colonne ? newsletter_valori_di(array_column($righe, 'id'), $colonne) : [];
  $nascosti = '<input type="hidden" name="cerca" value="' . h($f['cerca']) . '"><input type="hidden" name="stato" value="' . h($f['stato']) . '"><input type="hidden" name="lista" value="' . (int)$f['lista'] . '"><input type="hidden" name="per" value="' . $per . '">';
  $qBase = array_filter(['cerca' => $f['cerca'], 'stato' => $f['stato'], 'lista' => $f['lista'] ?: null, 'per' => $per !== 50 ? $per : null]);
  $modificabili = array_filter($attributi, fn($a) => !(int)$a['sistema'] && in_array($a['tipo'], ['date', 'boolean', 'category'], true));
?>
<div class="form-card">
  <div class="nl-riga" style="justify-content:space-between">
    <h3 style="margin:0">Contatti</h3>
    <div class="nl-riga">
      <a class="btn btn-primary btn-sm" href="newsletter-contatto.php?nuovo=1">Crea un contatto</a>
      <a class="btn btn-secondary btn-sm" href="#nlc-importa">Importa contatti</a>
      <button class="btn btn-secondary btn-sm" type="button" data-apri-dialogo="#nlc-colonne">Personalizza le colonne</button>
    </div>
  </div>
  <p class="hint" style="margin-top:0"><?= (int)array_sum(array_intersect_key($numeri, array_flip(['attivo', 'in_attesa', 'uscito', 'rimbalzato', 'spam', 'bloccato']))) ?> contatti: <?= (int)$numeri['attivo'] ?> iscritti, <?= (int)$numeri['in_attesa'] ?> in attesa di conferma, <a href="?stato=lista_nera"><?= (int)$numeri['lista_nera'] ?> in lista nera</a>.</p>
  <?php if ($numeri['segnalati']): ?><p class="nl-avviso" role="status"><b><?= (int)$numeri['segnalati'] ?></b> <?= $numeri['segnalati'] === 1 ? 'contatto ha' : 'contatti hanno' ?> avuto tre errori temporanei di fila (soft bounce): al quinto di fila vanno in lista nera. <a href="?stato=errori">Vedi chi</a>.</p><?php endif; ?>

  <form method="get" class="nlc-filtri" role="search" aria-label="Cerca i contatti">
    <div class="form-group"><label for="nlc-lista">Carica una lista</label>
      <?= $tendinaListe('lista', 'Tutti i contatti', (int)$f['lista'], '<option value="-1"' . ($f['lista'] === -1 ? ' selected' : '') . '>In nessuna lista</option>', 'nlc-lista') ?></div>
    <div class="form-group"><label for="nlc-cerca">Cerca</label>
      <input type="search" id="nlc-cerca" name="cerca" value="<?= h($f['cerca']) ?>" placeholder="per esempio: anna@ oppure Rossi" aria-describedby="nlc-cerca-aiuto">
      <div class="hint" id="nlc-cerca-aiuto">Email (anche un pezzo), nome, cognome o identificativo esterno.</div></div>
    <div class="form-group"><label for="nlc-stato">Stato dell'email</label>
      <select id="nlc-stato" name="stato">
        <option value="">Tutti</option>
        <option value="iscritti"<?= $f['stato'] === 'iscritti' ? ' selected' : '' ?>>Iscritti</option>
        <option value="in_attesa"<?= $f['stato'] === 'in_attesa' ? ' selected' : '' ?>>In attesa di conferma</option>
        <option value="lista_nera"<?= $f['stato'] === 'lista_nera' ? ' selected' : '' ?>>In lista nera (tutti i motivi)</option>
        <?php foreach (NL_MOTIVI as $k => $m): ?><option value="motivo:<?= h($k) ?>"<?= $f['stato'] === 'motivo:' . $k ? ' selected' : '' ?>>In lista nera: <?= h(lcfirst($m[0])) ?></option><?php endforeach; ?>
        <option value="errori"<?= $f['stato'] === 'errori' ? ' selected' : '' ?>>Iscritti con 3 errori temporanei di fila</option>
      </select></div>
    <div class="form-group"><label for="nlc-per">Per pagina</label>
      <select id="nlc-per" name="per"><?php foreach ([50, 100, 500] as $x): ?><option value="<?= $x ?>"<?= $per === $x ? ' selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select></div>
    <div class="nlc-filtri-azioni"><button class="btn btn-secondary btn-sm" type="submit">Cerca</button>
      <?php if ($qBase && array_diff_key($qBase, ['per' => 1])): ?><a href="newsletter-contatti.php">Togli i filtri</a><?php endif; ?></div>
  </form>

  <form method="post" id="nlc-gruppo" class="nlc-gruppo" aria-label="Azioni sui contatti scelti">
    <?= csrf_field() ?><input type="hidden" name="azione" value="gruppo"><?= $nascosti ?>
    <fieldset>
      <legend>Azioni sui contatti scelti</legend>
      <div class="nl-riga">
        <label class="nlc-tutti"><input type="checkbox" name="tutti" value="1" id="nlc-tutti"> Tutti i contatti <?= $qBase && array_diff_key($qBase, ['per' => 1]) ? 'del filtro' : '' ?> (<b><?= $totale ?></b>), non solo quelli spuntati in questa pagina</label>
      </div>
      <div class="nlc-campi">
        <div class="form-group"><label for="nlc-cosa">Azione</label>
          <select name="cosa" id="nlc-cosa" data-nlc-azione>
            <option value="">Scegli...</option>
            <option value="aggiungi">Aggiungi a una lista</option>
            <option value="togli">Togli dalle liste</option>
            <option value="sposta"<?= $f['lista'] > 0 ? '' : ' disabled' ?>>Sposta in un'altra lista<?= $f['lista'] > 0 ? '' : ' (carica prima una lista)' ?></option>
            <option value="blocca">Metti in lista nera</option>
            <option value="modifica"<?= $modificabili ? '' : ' disabled' ?>>Modifica un attributo<?= $modificabili ? '' : ' (nessun attributo Data, Sì/No o Categoria)' ?></option>
            <option value="esporta">Esporta (CSV)</option>
            <option value="elimina">Elimina definitivamente</option>
          </select></div>
        <div class="form-group" data-nlc-per="aggiungi sposta"><label for="nlc-lista-dest">Lista di arrivo</label><?= $tendinaListe('lista_dest', 'Scegli la lista...', 0, '', 'nlc-lista-dest') ?></div>
        <div class="form-group" data-nlc-per="togli"><label for="nlc-lista-da">Da quale lista</label><?= $tendinaListe('lista_da', 'Da tutte le liste', (int)max(0, $f['lista']), '', 'nlc-lista-da') ?></div>
        <div class="form-group" data-nlc-per="modifica"><label for="nlc-attr">Attributo</label>
          <select name="attributo" id="nlc-attr"><?php foreach ($modificabili as $n => $a): ?><option value="<?= h($n) ?>" data-tipo="<?= h($a['tipo']) ?>"><?= h(newsletter_attributo_etichetta($a)) ?> (<?= h(NL_ATTR_TIPI[$a['tipo']]) ?>)</option><?php endforeach; ?></select></div>
        <div class="form-group" data-nlc-per="modifica"><label for="nlc-valore">Nuovo valore</label>
          <input type="text" name="valore" id="nlc-valore" placeholder="per esempio: 2026-12-31, sì, oppure il nome dell'opzione" aria-describedby="nlc-valore-aiuto">
          <div class="hint" id="nlc-valore-aiuto">Data come 2026-12-31 o 31/12/2026; Sì/No come sì o no; Categoria con il nome dell'opzione. Vuoto toglie il valore.</div></div>
      </div>
      <button class="btn btn-primary btn-sm" type="submit" id="nlc-applica" data-conferma="Applicare l'azione ai contatti scelti?">Applica</button>
    </fieldset>
  </form>

  <div class="ad-tabella"><table class="table nlc-tabella">
    <thead><tr><th scope="col"><input type="checkbox" data-seleziona-tutti=".nlc-sel" aria-label="Spunta tutti i contatti di questa pagina"></th>
      <?php foreach (NL_COLONNE_FISSE as $k => $v): ?><th scope="col"><?= h($v) ?></th><?php endforeach; ?>
      <?php foreach ($colonne as $n): ?><th scope="col"><?= h(newsletter_attributo_etichetta($attributi[$n])) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($righe as $r): $id = (int)$r['id']; $nera = in_array($r['stato'], NL_BLOCCATI, true); ?>
      <tr>
        <td><input type="checkbox" class="nlc-sel" name="sel[]" value="<?= $id ?>" form="nlc-gruppo" aria-label="Scegli <?= h($r['email']) ?>"></td>
        <td><a href="newsletter-contatto.php?id=<?= $id ?>"><?= h($r['email']) ?></a><?php $nm = trim(newsletter_4byte_fuori($r['nome'] . ' ' . $r['cognome'])); if ($nm !== ''): ?><span class="nlc-sotto"><?= h($nm) ?></span><?php endif; ?></td>
        <td><?= $r['stato'] === 'attivo' ? '<span class="nlc-stato nlc-si">&#10003; Sì</span>' : ($r['stato'] === 'in_attesa' ? '<span class="nlc-stato">&#8230; In attesa di conferma</span>' : '<span class="nlc-stato">No</span>') ?>
          <?php if ($r['stato'] === 'attivo' && (int)$r['errori_di_fila'] >= 3): ?><span class="nl-errori" title="<?= h($r['ultimo_errore']) ?>"><?= (int)$r['errori_di_fila'] ?> errori temporanei di fila</span><?php endif; ?></td>
        <td><?= $nera ? '<span class="nlc-stato nlc-nera">&#10007; Sì</span><span class="nlc-sotto">' . h(newsletter_motivo_testo($r)) . ($r['blocco_at'] ? ', ' . h(date('d/m/Y', strtotime($r['blocco_at']))) : '') . '</span>' : 'No' ?></td>
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
  <?php if ($pagine > 1): $link = fn($p) => 'newsletter-contatti.php?' . http_build_query($qBase + ['pag' => $p]); ?>
  <nav class="nlc-pagine" aria-label="Pagine dei contatti">
    <?php if ($pag > 1): ?><a class="btn btn-secondary btn-sm" href="<?= h($link(1)) ?>">Prima pagina</a> <a class="btn btn-secondary btn-sm" href="<?= h($link($pag - 1)) ?>">Pagina precedente</a><?php endif; ?>
    <span>Pagina <?= $pag ?> di <?= $pagine ?> (<?= $totale ?> contatti)</span>
    <?php if ($pag < $pagine): ?><a class="btn btn-secondary btn-sm" href="<?= h($link($pag + 1)) ?>">Pagina successiva</a><?php endif; ?>
  </nav>
  <?php endif; ?>
  <p class="hint"><a href="?scarica=tutti">Scarica tutti i contatti (CSV)</a></p>
</div>

<details class="form-card nlc-riquadro" id="nlc-importa">
  <summary><h3>Importa contatti</h3></summary>
  <p class="hint">Un file CSV con la colonna EMAIL (e, se ci sono, NOME, COGNOME e la data del consenso in una colonna CONSENSO). Da Brevo: Contatti, seleziona una lista, Esporta, formato CSV, solo i contatti iscritti. Chi è già qui resta com'è ed entra anche nella lista scelta; chi è in lista nera o ha chiesto di cancellare i suoi dati resta fuori.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="azione" value="importa">
    <div class="form-group"><label for="nlc-csv">File CSV</label><input type="file" id="nlc-csv" name="csv" accept=".csv,text/csv"<?= pannello_limite_caricamento() > 0 ? ' data-peso-massimo="' . pannello_limite_caricamento() . '"' : '' ?>></div>
    <div class="form-group"><label for="nlc-imp-lista">Nella lista</label><?= $tendinaListe('lista', '', 0, '', 'nlc-imp-lista') ?></div>
    <div class="form-group"><label for="nlc-imp-nuova">Oppure in una lista nuova</label><input type="text" id="nlc-imp-nuova" name="lista_nuova" maxlength="100" placeholder="per esempio: Clienti 2026"></div>
    <div class="form-group"><label for="nlc-imp-cart">Cartella della lista nuova</label><select id="nlc-imp-cart" name="cartella"><?php foreach ($cartelle as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h(newsletter_4byte_fuori($c['nome'])) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label><input type="checkbox" name="consenso_brevo" value="1"> Sono contatti iscritti, con il loro consenso</label>
      <label><input type="checkbox" name="flussi_import" value="1"> Fai partire i flussi per gli importati</label></div>
    <button class="btn btn-primary btn-sm" type="submit">Importa</button>
  </form>
</details>

<details class="form-card nlc-riquadro" id="nlc-nera">
  <summary><h3>Metti in lista nera degli indirizzi</h3></summary>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="lista_nera_indirizzi">
    <div class="form-group"><label for="nlc-indirizzi">Indirizzi email</label>
      <textarea id="nlc-indirizzi" name="indirizzi" rows="4" class="nlc-corto" placeholder="per esempio: chi.non.vuole@esempio.it" aria-describedby="nlc-indirizzi-aiuto"></textarea>
      <div class="hint" id="nlc-indirizzi-aiuto">Uno per riga, fino a 1000. Chi non è ancora un contatto diventa un contatto in lista nera: non riceverà mai campagne né automazioni. Motivo: messo in lista nera a mano.</div></div>
    <button class="btn btn-primary btn-sm" type="submit">Metti in lista nera</button>
  </form>
</details>

<dialog id="nlc-colonne" class="nl-dialogo" aria-labelledby="nlc-colonne-titolo">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="azione" value="colonne"><?= $nascosti ?>
    <h2 id="nlc-colonne-titolo">Personalizza le colonne</h2>
    <p class="hint">Le cinque colonne Contatto, Iscritto, In lista nera, Data di creazione e Ultima modifica ci sono sempre. Spunta gli attributi da aggiungere e mettili in ordine trascinandoli o con le frecce.</p>
    <ul class="nlc-ordine" data-nlc-ordina>
      <?php $ordine = array_merge($colonne, array_values(array_diff(array_keys(array_filter($attributi, fn($a) => $a['categoria'] !== 'global' && !in_array($a['nome'], ['CREATION_DATE', 'LAST_CHANGED'], true))), $colonne)));
      foreach ($ordine as $n): $a = $attributi[$n]; ?>
      <li><span class="nlc-maniglia" aria-hidden="true">&#8942;&#8942;</span>
        <label><input type="checkbox" name="colonne[]" value="<?= h($n) ?>"<?= in_array($n, $colonne, true) ? ' checked' : '' ?>> <?= h(newsletter_attributo_etichetta($a)) ?> <small>(<?= h($n) ?>)</small></label>
        <button type="button" class="btn btn-secondary btn-sm" data-nlc-su aria-label="Sposta su <?= h(newsletter_attributo_etichetta($a)) ?>">&#8593;</button>
        <button type="button" class="btn btn-secondary btn-sm" data-nlc-giu aria-label="Sposta giù <?= h(newsletter_attributo_etichetta($a)) ?>">&#8595;</button></li>
      <?php endforeach; ?>
    </ul>
    <div class="pn-conferma-azioni"><button type="button" class="btn btn-secondary" data-chiudi-dialogo="#nlc-colonne">Annulla</button><button type="submit" class="btn btn-primary">Salva le colonne</button></div>
  </form>
</dialog>

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
      <div class="form-group"><label for="nlc-cart-nome">Nome della cartella</label><input type="text" id="nlc-cart-nome" name="nome" maxlength="100" required placeholder="per esempio: Eventi 2026"></div>
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
            <td><a href="newsletter-contatti.php?lista=<?= $lid ?>"><b><?= h($nomeL) ?></b></a><?php if ($l['descrizione'] !== ''): ?><span class="nlc-sotto"><?= h(newsletter_4byte_fuori($l['descrizione'])) ?></span><?php endif; ?>
              <?php if (in_array($lid, $sistemaListe, true)): ?><span class="nlc-sotto">usata dal sito per le iscrizioni</span><?php endif; ?></td>
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
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
