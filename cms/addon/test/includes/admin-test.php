<?php
/**
 * Test > Test: l'elenco dei test, Nuovo test (vuoto o da un modello), Duplica, Elimina, e la procedura in cinque passi
 * (?id=N&passo=1..5): 1 Presentazione, 2 Domande e aree, 3 Punteggi e fasce (con il simulatore), 4 Risultati, 5 Dopo il
 * test (email, lista, automazioni, privacy) e Pubblica. Ogni passo salva solo i suoi campi. Solo amministratore.
 */
require_once __DIR__ . '/admin-comune.php';
require_once dirname(__DIR__, 3) . '/includes/newsletter.php';
$adminTitle = 'Test e questionari';

/**
 * 2.23.0: le domande sulla persona dal passo 2 (campi persona[k][usa|ordine|testo|nome|risposte] e persona_obbligatorie).
 * Le risposte sono una per riga: una riga uguale a una risposta che c'era tiene la sua chiave (le statistiche restano
 * attaccate), una riga nuova prende una chiave nuova.
 */
function test_admin_persona(array $vecchia): array {
    $in = is_array($_POST['persona'] ?? null) ? $_POST['persona'] : [];
    $cat = test_persona_catalogo();
    $prima = array_column($vecchia['domande'], null, 'k');
    $scelte = [];
    foreach ($cat as $k => $c) {
        $x = is_array($in[$k] ?? null) ? $in[$k] : [];
        if (empty($x['usa'])) continue;
        $base = $prima[$k]['opzioni'] ?? $c['opzioni'];
        $perTesto = [];
        foreach (array_merge($base, $c['opzioni']) as $o) $perTesto[mb_strtolower($o['t'])] ??= $o['k'];
        $usate = [];
        $ops = [];
        foreach (preg_split('/\R/', is_string($x['risposte'] ?? null) ? $x['risposte'] : '') as $riga) {
            $t = test_testo($riga, 120);
            if ($t === '' || mb_strtolower($t) === mb_strtolower(TEST_PERSONA_NR_TESTO)) continue;
            $kk = $perTesto[mb_strtolower($t)] ?? '';
            if ($kk === '' || isset($usate[$kk])) $kk = '';
            $ops[] = ['k' => $kk, 't' => $t];
            if ($kk !== '') $usate[$kk] = true;
        }
        // Le righe nuove: la prima chiave libera o1, o2... (che non sia già di una risposta di prima o del catalogo).
        $occupate = $usate + array_flip(array_column($base, 'k')) + array_flip(array_column($c['opzioni'], 'k'));
        $n = 1;
        foreach ($ops as &$o) if ($o['k'] === '') { while (isset($occupate['o' . $n])) $n++; $o['k'] = 'o' . $n; $occupate[$o['k']] = true; }
        unset($o);
        $scelte[] = ['ordine' => (int)($x['ordine'] ?? 0), 'pos' => count($scelte), 'q' => ['k' => $k, 'nome' => $x['nome'] ?? '', 'testo' => $x['testo'] ?? '', 'opzioni' => $ops]];
    }
    usort($scelte, fn($a, $b) => $a['ordine'] <=> $b['ordine'] ?: $a['pos'] <=> $b['pos']);
    return ['obbligatorie' => ($_POST['persona_obbligatorie'] ?? '') === '1', 'domande' => array_column($scelte, 'q')];
}

/**
 * 2.24.0: i pezzi della forma comune dei cinque passi. Una carta compatta (pulsante) che apre la sua finestra; la finestra
 * del pannello (<dialog>, aperta da admin-test.js) con la testata in fascia, Chiudi in alto e Fatto in fondo; il lato con il
 * telefono dell'anteprima. $titolo e $corpo della carta sono già HTML (il chiamante usa h()).
 */
function test_admin_carta(string $fin, string $titolo, string $corpo = '', string $bollini = '', string $extra = ''): string {
    return '<button type="button" class="ts-carta" data-apri="' . h($fin) . '" aria-haspopup="dialog"' . $extra . '><span class="ts-carta-tit">' . $titolo . '</span>'
        . ($corpo !== '' ? '<span class="ts-carta-corpo">' . $corpo . '</span>' : '') . '<span class="ts-bollini">' . $bollini . '</span></button>';
}

function test_admin_finestra(string $id, string $titolo, string $testa = ''): string {
    return '<dialog class="ts-finestra" id="' . h($id) . '" aria-labelledby="' . h($id) . '-t"><div class="ts-fin-testa"><h2 id="' . h($id) . '-t">' . h($titolo) . '</h2>'
        . '<span class="ts-fin-bottoni">' . $testa . '<button type="button" class="btn btn-secondary btn-sm" data-chiudi-fin>Chiudi</button></span></div><div class="ts-fin-corpo">';
}

function test_admin_finestra_fine(string $piede = ''): string {
    return '</div><div class="ts-fin-piede">' . $piede . '<button type="button" class="btn btn-primary" data-chiudi-fin>Fatto</button></div></dialog>';
}

function test_admin_lato(string $sopra = ''): string {
    return '<aside class="ts-lato" id="ts-lato" aria-labelledby="ts-lato-t"><div class="ts-lato-testa"><h2 class="ts-lato-tit" id="ts-lato-t">Come lo vede chi fa il test</h2>'
        . '<button type="button" class="btn btn-secondary btn-sm ts-lato-chiudi" data-ts-anteprima-chiudi>Chiudi l\'anteprima</button></div>' . $sopra
        . '<div class="ts-tel"><div class="ts-tel-schermo" id="ts-tel"></div></div><div class="ts-puntini" id="ts-puntini"></div></aside>';
}

/** Un JSON arrivato dal pannello (campi protetti: già decodificato da auth.php). */
function test_admin_json(string $campo): array {
    $j = json_decode((string)($_POST[$campo] ?? ''), true, 32);
    return is_array($j) ? $j : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($azione === 'crea') {
            $mod = (string)($_POST['modello'] ?? '');
            $modelli = test_modelli();
            if ($mod !== '' && isset($modelli[$mod])) $nuovo = test_crea($modelli[$mod]['nome'], $modelli[$mod]['tipo'], $modelli[$mod]['dati']);
            else $nuovo = test_crea(test_testo($_POST['nome'] ?? '', 200) ?: 'Test senza nome', (string)($_POST['tipo'] ?? 'punteggio'));
            flash('success', 'Test creato in bozza: sul sito non si vede finché non lo pubblichi (passo 5).');
            test_admin_vai('test', ['id' => $nuovo, 'passo' => 1]);
        }
        $t = $id ? test_leggi($id) : null;
        if (!$t) { flash('error', 'Questo test non c\'è più.'); test_admin_vai('test'); }
        if ($azione === 'duplica') { $n = test_duplica($id); flash('success', 'Copia creata, in bozza: «' . $t['nome'] . ' (copia)».'); test_admin_vai('test', ['id' => $n, 'passo' => 1]); }
        if ($azione === 'elimina') { test_elimina($id); flash('success', 'Test «' . $t['nome'] . '» eliminato, con le sue statistiche.'); test_admin_vai('test'); }
        if ($azione === 'pubblica') {
            $prob = test_problemi($t);
            if ($prob) { flash('error', 'Il test non si può ancora pubblicare: ' . implode(' ', $prob)); test_admin_vai('test', ['id' => $id, 'passo' => 5]); }
            test_salva($id, ['stato' => 'pubblicato']);
            flash('success', 'Test pubblicato: la sua pagina è ' . cms_url_sito() . test_url(test_leggi($id)));
            test_admin_vai('test', ['id' => $id, 'passo' => 5]);
        }
        if ($azione === 'bozza') { test_salva($id, ['stato' => 'bozza']); flash('success', 'Il test è tornato in bozza: la sua pagina non si apre più.'); test_admin_vai('test', ['id' => $id, 'passo' => 5]); }
        if ($azione === 'salva') {
            $passo = max(1, min(5, (int)($_POST['passo'] ?? 1)));
            $d = $t['dati'];
            $campi = [];
            $avvisi = [];
            switch ($passo) {
                case 1:
                    $campi = ['nome' => $_POST['nome'] ?? '', 'slug' => $_POST['slug'] ?? '', 'durata' => (int)($_POST['durata'] ?? 0)];
                    if (isset(TEST_TIPI[$_POST['tipo'] ?? '']) && $_POST['tipo'] !== $t['tipo']) { $campi['tipo'] = $_POST['tipo']; $avvisi[] = 'Il tipo è cambiato: controlla domande, fasce e risultati nei passi dopo.'; }
                    $d['presentazione'] = test_testo($_POST['presentazione'] ?? $d['presentazione'], 2000, true);
                    break;
                case 2:
                    $j = test_admin_json('dati_json');
                    $prima = count($j['domande'] ?? []);
                    // Le fasce delle aree si scrivono nel passo 3: un'area che c'era già le tiene.
                    $vecchie = array_column($d['aree'], null, 'k');
                    foreach ($j['aree'] ?? [] as $i => $a) if (is_array($a) && isset($vecchie[$a['k'] ?? ''])) $j['aree'][$i]['fasce'] = $vecchie[$a['k']]['fasce'];
                    foreach (['scala', 'aree', 'profili', 'domande', 'modo', 'pagine'] as $k) if (array_key_exists($k, $j)) $d[$k] = $j[$k]; // 2.24.0: modo e pagine
                    $d['casuale'] = !empty($_POST['casuale']);
                    if (isset($_POST['persona_obbligatorie'])) $d['persona'] = test_admin_persona($d['persona']); // 2.23.0
                    $dopo = count(test_normalizza($d, $campi['tipo'] ?? $t['tipo'])['domande']);
                    if ($dopo < $prima) $avvisi[] = ($prima - $dopo) . ($prima - $dopo === 1 ? ' domanda non è stata salvata' : ' domande non sono state salvate') . ': manca il testo, oppure una scelta ha meno di due risposte.';
                    break;
                case 3:
                    $j = test_admin_json('fasce_json');
                    if (array_key_exists('fasce', $j)) $d['fasce'] = $j['fasce'];
                    foreach ($d['aree'] as $i => $a) if (isset($j['aree'][$a['k']])) {
                        // I testi delle fasce si scrivono nel passo 4: una fascia che c'era già li tiene.
                        $vec = array_column($a['fasce'], null, 'k');
                        $d['aree'][$i]['fasce'] = array_map(fn($f) => is_array($f) && isset($vec[$f['k'] ?? '']) ? $f + array_intersect_key($vec[$f['k']], ['breve' => 1, 'testo' => 1, 'pulsante_testo' => 1, 'pulsante_link' => 1]) : $f, (array)$j['aree'][$a['k']]);
                    }
                    $vec = array_column($t['dati']['fasce'], null, 'k');
                    $d['fasce'] = array_map(fn($f) => is_array($f) && isset($vec[$f['k'] ?? '']) ? $f + array_intersect_key($vec[$f['k']], ['breve' => 1, 'testo' => 1, 'pulsante_testo' => 1, 'pulsante_link' => 1]) : $f, (array)$d['fasce']);
                    break;
                case 4:
                    $testi = is_array($_POST['testi'] ?? null) ? $_POST['testi'] : [];
                    $prendi = function (array $x, string $chiave) use ($testi): array {
                        $v = $testi[$chiave] ?? null;
                        if (!is_array($v)) return $x;
                        foreach (['breve', 'testo', 'pulsante_testo', 'pulsante_link'] as $c) if (array_key_exists($c, $v)) $x[$c] = $v[$c];
                        return $x;
                    };
                    foreach ($d['fasce'] as $i => $f) $d['fasce'][$i] = $prendi($f, 'totale|' . $f['k']);
                    foreach ($d['aree'] as $i => $a) foreach ($a['fasce'] as $j2 => $f) $d['aree'][$i]['fasce'][$j2] = $prendi($f, 'area|' . $a['k'] . '|' . $f['k']);
                    foreach ($d['profili'] as $i => $p) $d['profili'][$i] = $prendi($p, 'profilo|' . $p['k']);
                    $j = test_admin_json('regole_json');
                    if (array_key_exists('regole', $j)) $d['regole'] = $j['regole'];
                    $d['avvertenza'] = (string)($_POST['avvertenza'] ?? $d['avvertenza']);
                    break;
                case 5:
                    $d['dopo'] = ['email' => !empty($_POST['email']), 'consenso' => $_POST['consenso'] ?? '', 'lista' => (int)($_POST['lista'] ?? 0), 'iscrizione' => $_POST['iscrizione'] ?? '', 'solo_email' => !empty($_POST['solo_email'])];
                    break;
            }
            $campi['dati'] = $d;
            test_salva($id, $campi);
            $t2 = test_leggi($id);
            if ($passo === 3) {
                foreach (array_merge($t2['tipo'] !== 'profili' ? [test_fasce_controlla($t2['dati']['fasce'])] : [], array_map(fn($a) => test_fasce_controlla($a['fasce'], 'dell\'area «' . $a['nome'] . '»'), $t2['dati']['aree'])) as $e) if ($e !== '') $avvisi[] = $e;
            }
            flash($avvisi ? 'info' : 'success', 'Passo ' . $passo . ' salvato.' . ($avvisi ? ' ' . implode(' ', $avvisi) : ''));
            $avanti = ($_POST['vai'] ?? '') === 'avanti' && $passo < 5;
            test_admin_vai('test', ['id' => $id, 'passo' => $avanti ? $passo + 1 : $passo]);
        }
    } catch (Throwable $e) {
        error_log('test: pannello: ' . $e->getMessage());
        flash('error', 'Non sono riuscito a salvare: ' . $e->getMessage());
        test_admin_vai('test', $id ? ['id' => $id, 'passo' => (int)($_POST['passo'] ?? 1)] : []);
    }
}

$id = (int)($_GET['id'] ?? 0);
$t = $id ? test_leggi($id) : null;
$nuovo = !$t && isset($_GET['nuovo']);
$passo = max(1, min(5, (int)($_GET['passo'] ?? 1)));
if ($t) $adminTitle = 'Test: ' . $t['nome'];
require CMS_ADMIN . '/includes/admin-header.php';
test_admin_schede('test');

// ------------------------------------------------------------ elenco
if (!$t && !$nuovo):
    $tutti = test_tutti(); ?>
<div class="ct-testa ts-testa"><p class="ts-intro">Un test fa delle domande e dà un risultato: un punteggio con la sua fascia, il grafico delle aree, oppure il profilo che somiglia di più. Chi lo fa vede il risultato subito e, se vuole, lo riceve per email. Le risposte restano anonime.</p><a href="<?= h(test_admin_url('test', ['nuovo' => 1])) ?>" class="btn btn-primary">+ Nuovo test</a></div>
<div class="admin-table-wrap ct-tabella">
  <table class="ts-tabella">
    <thead><tr><th>Test</th><th>Tipo</th><th>Stato</th><th>Completati</th></tr></thead>
    <tbody>
    <?php if (!$tutti): ?><tr><td colspan="4"><div class="empty-state"><p>Ancora nessun test: crea il primo con «+ Nuovo test». Puoi partire da uno dei tre modelli pronti.</p><a href="<?= h(test_admin_url('test', ['nuovo' => 1])) ?>" class="btn btn-primary">+ Nuovo test</a></div></td></tr><?php endif; ?>
    <?php foreach ($tutti as $x): ?>
      <tr class="ct-riga" data-test="<?= (int)$x['id'] ?>">
        <td class="ct-titolo"><a class="ct-nome" href="<?= h(test_admin_url('test', ['id' => $x['id'], 'passo' => 1])) ?>"><?= h($x['nome']) ?></a><div class="ct-indirizzo"><?= h(test_url($x)) ?></div>
          <div class="ct-azioni ts-azioni-riga">
            <a href="<?= h(test_admin_url('test', ['id' => $x['id'], 'passo' => 1])) ?>">Modifica</a>
            <a href="<?= h(test_admin_url('statistiche', ['id' => $x['id']])) ?>">Statistiche</a>
            <a href="<?= h(cms_url_sito() . test_url($x) . ($x['stato'] === 'pubblicato' ? '' : '?preview=1')) ?>" target="_blank" rel="noopener"><?= $x['stato'] === 'pubblicato' ? 'Vedi la pagina' : 'Anteprima' ?></a>
            <form method="post" class="ts-in-riga"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button type="submit" name="azione" value="duplica" class="ts-link">Duplica</button></form>
            <form method="post" class="ts-in-riga" data-conferma="Eliminare il test «<?= h($x['nome']) ?>» con tutte le sue statistiche? Non si torna indietro." data-conferma-ok="Elimina il test" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><input type="hidden" name="azione" value="elimina"><button type="submit" class="ts-link ts-elimina">Elimina</button></form>
          </div></td>
        <td class="ct-muto"><?= h(TEST_TIPI[$x['tipo']]) ?></td>
        <td><?= test_stato_badge($x['stato']) ?></td>
        <td class="ct-muto"><a href="<?= h(test_admin_url('statistiche', ['id' => $x['id']])) ?>"><?= (int)$x['completati'] ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
// ------------------------------------------------------------ nuovo test
elseif ($nuovo): ?>
<p><a href="<?= h(test_admin_url('test')) ?>">&lsaquo; Tutti i test</a></p>
<div class="sc-scatola ts-scatola"><h3>Che test vuoi creare?</h3><div class="sc-corpo">
  <p class="hint" style="margin-top:0">Scegli il tipo: poi lo costruisci in cinque passi, e ogni passo si salva con «Salva» o «Avanti». Il test nasce in bozza.</p>
  <form method="post" id="ts-crea"><?= csrf_field() ?><input type="hidden" name="azione" value="crea">
    <div class="form-group"><label for="ts-nome-nuovo">Nome del test</label><input type="text" id="ts-nome-nuovo" name="nome" maxlength="200" placeholder="per esempio: Quanto sei in forma?" aria-describedby="ts-nome-nuovo-aiuto"><div class="hint" id="ts-nome-nuovo-aiuto">Lo vedono tutti: è il titolo della pagina del test. Lo puoi cambiare dopo.</div></div>
    <ul class="ts-tipi">
      <?php foreach (TEST_TIPI as $k => $et): ?><li><button type="submit" name="tipo" value="<?= $k ?>" class="ts-tipo"><b><?= h($et) ?></b><span><?= h(TEST_TIPI_SPIEGA[$k]) ?></span></button></li><?php endforeach; ?>
    </ul>
  </form>
</div></div>
<div class="sc-scatola ts-scatola"><h3>Oppure parti da un modello pronto</h3><div class="sc-corpo">
  <p class="hint" style="margin-top:0">Domande e testi d'esempio scritti per plumeCMS: cambiali come vuoi. Non sono strumenti di valutazione clinica.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="crea">
    <ul class="ts-tipi">
      <?php foreach (test_modelli() as $k => $m): ?><li><button type="submit" name="modello" value="<?= h($k) ?>" class="ts-tipo"><b><?= h($m['nome']) ?></b><small><?= h(TEST_TIPI[$m['tipo']]) ?></small><span><?= h($m['descrizione']) ?></span></button></li><?php endforeach; ?>
    </ul>
  </form>
</div></div>
<?php
// ------------------------------------------------------------ la procedura in cinque passi
// 2.24.0: ogni passo ha la stessa forma. A sinistra il lavoro, fatto di carte compatte con i bollini: una carta si apre in
// una finestra del pannello (<dialog>, Esc chiude) con i campi raggruppati e stretti. A destra il telefono con l'anteprima
// dal vivo di quello che il passo cambia per chi fa il test (assets/admin-test.js); sotto 900 px il telefono si apre a
// schermo intero con il pulsante «Anteprima». I campi stanno dentro il modulo anche quando la loro finestra è chiusa: il
// salvataggio di ogni passo manda gli stessi campi di prima.
else:
    $d = $t['dati'];
    $vedi = cms_url_sito() . test_url($t) . ($t['stato'] === 'pubblicato' ? '' : '?preview=1');
    $j = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $tsListe = [];
    foreach (newsletter_liste() as $l) $tsListe[(int)$l['id']] = newsletter_4byte_fuori((string)$l['nome']);
    $iniziali = ['tipo' => $t['tipo'], 'passo' => $passo, 'nome' => $t['nome'], 'stato' => $t['stato'], 'dati' => $d, 'tipiDomanda' => TEST_DOMANDE_TIPI, 'modi' => TEST_MODI,
        'conti' => [count(array_filter($d['domande'], fn($q) => !$q['condizione'])) + count($d['persona']['domande']), count($d['domande']) + count($d['persona']['domande'])],
        'secondiDomanda' => TEST_SECONDI_DOMANDA, 'titoloEmail' => TEST_TITOLO_EMAIL, 'nr' => TEST_PERSONA_NR_TESTO, 'avvertenzaPartenza' => TEST_AVVERTENZA,
        'max' => ['domande' => TEST_MAX_DOMANDE, 'opzioni' => TEST_MAX_OPZIONI, 'aree' => TEST_MAX_AREE, 'profili' => TEST_MAX_PROFILI, 'fasce' => TEST_MAX_FASCE, 'regole' => TEST_MAX_REGOLE, 'pagine' => TEST_MAX_PAGINE, 'titolo' => TEST_MAX_TITOLO_PAGINA]]; ?>
<div class="fo-testa ts-testa-passi">
  <p class="fo-sopra"><a href="<?= h(test_admin_url('test')) ?>">Test</a> › <?= h($t['nome']) ?> <span class="badge badge-scheduled"><?= h(TEST_TIPI[$t['tipo']]) ?></span> <?= test_stato_badge($t['stato']) ?> <a class="ts-vedi" href="<?= h($vedi) ?>" target="_blank" rel="noopener"><?= $t['stato'] === 'pubblicato' ? 'Vedi la pagina' : 'Anteprima della pagina' ?></a></p>
  <nav class="fo-passi" aria-label="I passi del test"><ol>
    <?php foreach (TEST_PASSI as $n => $et): $tsQui = $n === $passo; ?>
      <li class="<?= $tsQui ? 'qui' : ($n < $passo ? 'fatto' : '') ?>"><?php if (!$tsQui): ?><a href="<?= h(test_admin_url('test', ['id' => $t['id'], 'passo' => $n])) ?>"><span class="fo-num"><?= $n ?></span> <?= h($et) ?></a><?php else: ?><span aria-current="step"><span class="fo-num"><?= $n ?></span> <?= h($et) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ol></nav>
</div>
<form method="post" id="ts-form" class="ts-passo" data-passo="<?= $passo ?>" action="<?= h(test_admin_url('test', ['id' => $t['id'], 'passo' => $passo])) ?>">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="passo" value="<?= $passo ?>"><input type="hidden" name="azione" value="salva">
  <p class="ts-anteprima-riga"><button type="button" class="btn btn-secondary ts-anteprima-btn" data-ts-anteprima aria-controls="ts-lato" aria-expanded="false">Anteprima: come lo vede chi fa il test</button></p>
<?php if ($passo === 1): $ed = 'editor.php?tipo=test_presentazione&id=' . (int)$t['id'];
    $tsPresStato = trim((string)$t['struttura_bozza']) !== '' ? '<span class="badge badge-scheduled">Bozza non pubblicata</span>' : (trim((string)$t['struttura']) !== '' ? '<span class="badge badge-published">Pubblicata</span>' : '<span class="badge badge-draft">Ancora da comporre</span>'); ?>
  <div class="sc-scatola ts-scatola"><h3>1. Presentazione</h3><div class="sc-corpo">
    <p class="hint ts-aiuto-passo">Tocca una carta per cambiarla: il telefono a destra mostra la pagina del test come la vede chi la apre.</p>
    <div class="ts-lavoro">
      <div class="ts-banco"><div class="ts-carte">
        <?= test_admin_carta('ts-fin-nome', 'Nome e indirizzo', '<b data-vivo="nome">' . h($t['nome']) . '</b><span class="ts-carta-sotto" data-vivo="indirizzo">' . h(TEST_PERCORSO . $t['slug'] . '/') . '</span>') ?>
        <?= test_admin_carta('ts-fin-tipo', 'Tipo e durata', '<span data-vivo="quanto">' . h(test_quanto_testo($t)) . '</span>', '<span class="ts-bollino" data-vivo="tipo">' . h(TEST_TIPI[$t['tipo']]) . '</span>') ?>
        <div class="ts-carta ts-carta-ferma"><span class="ts-carta-tit">Presentazione con l'editor visuale</span><span class="ts-carta-corpo">Il testo fra il titolo e il pulsante «Inizia il test»: testo, immagini, video, qualunque widget.</span><span class="ts-bollini"><?= $tsPresStato ?></span><a class="btn btn-secondary btn-sm" href="<?= h($ed) ?>">Componi la presentazione con l'editor visuale</a></div>
        <?= test_admin_carta('ts-fin-pres', 'Testo breve per Google e i social', '<span data-vivo="pres">' . h(mb_substr($d['presentazione'], 0, 140)) . '</span>', $d['presentazione'] === '' ? '<span class="ts-bollino ts-bollino-manca">Vuoto</span>' : '') ?>
      </div></div>
      <?= test_admin_lato() ?>
    </div>
  </div></div>
  <?= test_admin_finestra('ts-fin-nome', 'Nome e indirizzo') ?>
    <div class="ts-griglia-2">
      <div class="form-group"><label for="ts-nome">Nome del test</label><input type="text" id="ts-nome" name="nome" value="<?= h($t['nome']) ?>" maxlength="200" aria-describedby="ts-nome-aiuto"><div class="hint" id="ts-nome-aiuto">Il titolo della pagina e dell'email con il risultato. Vuoto: resta quello di prima.</div></div>
      <div class="form-group"><label for="ts-slug">Indirizzo della pagina</label><input type="text" id="ts-slug" name="slug" value="<?= h($t['slug']) ?>" maxlength="120" aria-describedby="ts-slug-aiuto"><div class="hint" id="ts-slug-aiuto"><?= h(cms_url_sito() . TEST_PERCORSO) ?><b><?= h($t['slug']) ?></b>/. Lettere minuscole, numeri e trattini; vuoto: dal nome.</div></div>
    </div>
  <?= test_admin_finestra_fine() ?>
  <?= test_admin_finestra('ts-fin-tipo', 'Tipo e durata') ?>
    <div class="ts-griglia-2">
      <div class="form-group"><label for="ts-tipo">Tipo di test</label><select id="ts-tipo" name="tipo" aria-describedby="ts-tipo-aiuto"><?php foreach (TEST_TIPI as $k => $et): ?><option value="<?= $k ?>"<?= $t['tipo'] === $k ? ' selected' : '' ?>><?= h($et) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="ts-durata">Durata in minuti</label><input type="number" id="ts-durata" name="durata" min="0" max="240" value="<?= (int)$t['durata'] ?>" aria-describedby="ts-durata-aiuto"><div class="hint" id="ts-durata-aiuto">0 = la calcola il sito (circa <?= TEST_SECONDI_DOMANDA ?> secondi a domanda).</div></div>
    </div>
    <p class="hint" id="ts-tipo-aiuto"><?= h(TEST_TIPI_SPIEGA[$t['tipo']]) ?> Cambiare tipo dopo aver scritto le domande toglie quello che il tipo nuovo non usa (aree, profili, fasce).</p>
  <?= test_admin_finestra_fine() ?>
  <?= test_admin_finestra('ts-fin-pres', 'Testo breve per Google e i social') ?>
    <div class="form-group"><label for="ts-pres">Presentazione breve</label><textarea id="ts-pres" name="presentazione" rows="5" maxlength="2000" aria-describedby="ts-pres-aiuto"><?= h($d['presentazione']) ?></textarea><div class="hint" id="ts-pres-aiuto">Va nella descrizione della pagina per Google e per i social, e sulla pagina se la presentazione con l'editor non c'è. Una riga vuota fa un paragrafo nuovo.</div></div>
  <?= test_admin_finestra_fine() ?>
<?php elseif ($passo === 2):
    /* 2.23.0: le domande sulla persona (catalogo in catalogo-persona.php, copiate nel test quando si accendono) */
    $tsPers = array_column($d['persona']['domande'], null, 'k'); $tsOrd = array_flip(array_keys($tsPers)); $tsCat = test_persona_catalogo(); $tsCatK = array_keys($tsCat);
    uksort($tsCat, fn($a, $b) => (isset($tsOrd[$a]) ? $tsOrd[$a] : 1000 + array_search($a, $tsCatK, true)) <=> (isset($tsOrd[$b]) ? $tsOrd[$b] : 1000 + array_search($b, $tsCatK, true))); ?>
  <input type="hidden" name="dati_json" id="ts-dati-json" value="">
  <div class="sc-scatola ts-scatola"><h3>2. Domande e pagine</h3><div class="sc-corpo">
    <div class="ts-barra-passo">
      <div class="ts-modo" role="group" aria-labelledby="ts-modo-t"><span class="ts-modo-t" id="ts-modo-t">Come si mostrano le domande</span>
        <span class="ts-seg"><?php foreach (TEST_MODI as $k => $et): ?><button type="button" data-modo="<?= $k ?>" aria-pressed="<?= ($d['modo'] ?? 'una') === $k ? 'true' : 'false' ?>"><?= h($et) ?></button><?php endforeach; ?></span></div>
      <p class="hint ts-riassunto" id="ts-riassunto" aria-live="polite"></p>
      <div class="ts-strumenti">
        <button type="button" class="btn btn-secondary btn-sm" data-piu-d>+ Domanda</button>
        <?php if ($t['tipo'] === 'aree'): ?><button type="button" class="btn btn-secondary btn-sm" data-apri="ts-fin-aree" aria-haspopup="dialog" data-conta="aree">Aree</button><?php endif; ?>
        <?php if ($t['tipo'] === 'profili'): ?><button type="button" class="btn btn-secondary btn-sm" data-apri="ts-fin-profili" aria-haspopup="dialog" data-conta="profili">Profili</button><?php endif; ?>
        <?php if ($t['tipo'] !== 'profili'): ?><button type="button" class="btn btn-secondary btn-sm" data-apri="ts-fin-scala" aria-haspopup="dialog">Le parole della scala</button><?php endif; ?>
        <label class="nl-casella fo-casella ts-casuale"><input type="checkbox" name="casuale" value="1"<?= $d['casuale'] ? ' checked' : '' ?> aria-describedby="ts-casuale-aiuto"> Ordine casuale</label>
      </div>
    </div>
    <p class="hint ts-aiuto-passo" id="ts-casuale-aiuto">Le colonne sono le pagine, le carte sono le domande. Tocca una carta (o la domanda nel telefono) per aprirla; con «A pagine» trascinala in un'altra colonna o nel riquadro tratteggiato per fare una pagina nuova (senza mouse: la tendina «Pagina» nella scheda). Con l'ordine casuale le domande si mescolano dentro la loro pagina; una condizionale resta dopo la sua. L'ultima pagina, sempre in fondo, ha le domande sulla persona.</p>
    <p class="ts-annuncio sr-solo" id="ts-annuncio" aria-live="polite"></p>
    <div class="ts-lavoro">
      <div class="ts-banco"><div class="ts-lavagna" id="ts-lavagna"></div></div>
      <?= test_admin_lato() ?>
    </div>
  </div></div>
  <?= test_admin_finestra('ts-fin-domanda', 'Domanda', '<button type="button" class="btn btn-danger btn-sm" data-togli-d>Togli la domanda</button>') ?>
    <div id="ts-domanda-corpo"></div>
  <?= test_admin_finestra_fine('<button type="button" class="btn btn-secondary btn-sm" data-sposta-d="-1">Sposta prima</button><button type="button" class="btn btn-secondary btn-sm" data-sposta-d="1">Sposta dopo</button>') ?>
  <?= test_admin_finestra('ts-fin-pagina', 'Pagina') ?>
    <div id="ts-pagina-corpo"></div>
  <?= test_admin_finestra_fine('<button type="button" class="btn btn-danger btn-sm" data-togli-pag-fin>Togli la pagina</button>') ?>
  <?php if ($t['tipo'] === 'aree'): ?>
  <?= test_admin_finestra('ts-fin-aree', 'Aree') ?><p class="hint">Ogni domanda appartiene a un'area (per esempio Energia, Sonno, Relazioni). Ogni area ha la sua percentuale e le sue fasce (passo 3).</p><div id="ts-aree-ed"></div><?= test_admin_finestra_fine() ?>
  <?php elseif ($t['tipo'] === 'profili'): ?>
  <?= test_admin_finestra('ts-fin-profili', 'Profili') ?><p class="hint">I risultati possibili («Sei un Esploratore»). <b>L'ordine conta:</b> se due profili hanno gli stessi punti vince quello più in alto. Trascina ⋮⋮ o usa le frecce. I testi si scrivono nel passo 4.</p><div id="ts-profili-ed"></div><?= test_admin_finestra_fine() ?>
  <?php endif; ?>
  <?php if ($t['tipo'] !== 'profili'): ?>
  <?= test_admin_finestra('ts-fin-scala', 'Le parole della scala') ?><p class="hint">Le risposte delle domande di tipo Scala, con i loro punti. Di partenza: Mai 0, Raramente 1, A volte 2, Spesso 3, Sempre 4.</p><div id="ts-scala-ed"></div><?= test_admin_finestra_fine() ?>
  <?php endif; ?>
  <?= test_admin_finestra('ts-fin-persona', 'Domande sulla persona') ?>
  <div id="ts-persona">
    <p class="hint">Non danno punti: compaiono alla fine del test, nell'ultima pagina, prima del risultato. Servono alle statistiche per gruppi (per età, occupazione, settore...) e, per chi si iscrive alla newsletter, diventano tag del contatto («Età: 35-44»). Si salvano anonime con l'esito.</p>
    <fieldset class="fo-scelte ts-griglia-2"><legend>Le domande sulla persona sono</legend>
      <label class="nl-casella fo-casella"><input type="radio" name="persona_obbligatorie" value="0"<?= $d['persona']['obbligatorie'] ? '' : ' checked' ?>> Facoltative (con «<?= h(TEST_PERSONA_NR_TESTO) ?>»)</label>
      <label class="nl-casella fo-casella"><input type="radio" name="persona_obbligatorie" value="1"<?= $d['persona']['obbligatorie'] ? ' checked' : '' ?>> Obbligatorie</label>
    </fieldset>
    <p class="hint">Spunta «Usa questa domanda» in quelle che ti servono. Il numero in «Ordine» decide l'ordine (la più bassa prima).</p>
    <?php $tsN = 0; foreach ($tsCat as $pk => $pc): $tsN++; $usata = isset($tsPers[$pk]); $q = $usata ? $tsPers[$pk] : $pc; $pid = 'tsp-' . $pk; ?>
    <details class="ts-persona-voce" data-persona-k="<?= h($pk) ?>"<?= $usata ? ' open' : '' ?>><summary><b><?= h($q['nome']) ?></b> <span class="badge <?= $usata ? 'badge-published' : 'badge-draft' ?>" data-persona-stato><?= $usata ? 'In uso' : 'Non usata' ?></span> <span class="hint"><?= h($q['testo']) ?></span></summary>
      <label class="nl-casella fo-casella"><input type="checkbox" name="persona[<?= h($pk) ?>][usa]" value="1"<?= $usata ? ' checked' : '' ?>> Usa questa domanda</label>
      <div class="ts-griglia-3 ts-persona-riga">
        <div class="form-group"><label for="<?= $pid ?>-o">Ordine</label><input type="number" id="<?= $pid ?>-o" name="persona[<?= h($pk) ?>][ordine]" min="0" max="99" value="<?= $usata ? (int)$tsOrd[$pk] + 1 : $tsN ?>"></div>
        <div class="form-group"><label for="<?= $pid ?>-n">Nome breve (tag e statistiche)</label><input type="text" id="<?= $pid ?>-n" name="persona[<?= h($pk) ?>][nome]" maxlength="40" value="<?= h($q['nome']) ?>"></div>
        <div class="form-group"><label for="<?= $pid ?>-t">Testo della domanda</label><input type="text" id="<?= $pid ?>-t" name="persona[<?= h($pk) ?>][testo]" maxlength="300" value="<?= h($q['testo']) ?>"></div>
      </div>
      <div class="form-group"><label for="<?= $pid ?>-r">Risposte, una per riga</label><textarea id="<?= $pid ?>-r" name="persona[<?= h($pk) ?>][risposte]" rows="<?= min(8, count($q['opzioni']) + 1) ?>" aria-describedby="<?= $pid ?>-ra"><?= h(implode("\n", array_column($q['opzioni'], 't'))) ?></textarea>
        <div class="hint" id="<?= $pid ?>-ra">Da 2 a <?= TEST_PERSONA_MAX_OPZIONI ?> risposte. «<?= h(TEST_PERSONA_NR_TESTO) ?>» si aggiunge da sola quando le domande sono facoltative. Cambiare il testo di una risposta la rende una risposta nuova nelle statistiche.</div></div>
    </details>
    <?php endforeach; ?>
  </div>
  <?= test_admin_finestra_fine() ?>
<?php elseif ($passo === 3):
    $prove = in_array((int)($_GET['prove'] ?? 200), [100, 200, 500, 1000], true) ? (int)($_GET['prove'] ?? 200) : 200;
    $sim = $d['domande'] ? test_simula($t, $prove) : null; ?>
  <input type="hidden" name="fasce_json" id="ts-fasce-json" value="">
  <div class="sc-scatola ts-scatola"><h3>3. Punteggi e fasce</h3><div class="sc-corpo">
    <div class="ts-lavoro">
      <div class="ts-banco">
      <?php if ($t['tipo'] === 'profili'): ?>
        <p class="hint ts-aiuto-passo">In un test a profili non ci sono fasce: vince il profilo con più punti. I punti di ogni risposta si danno nel passo 2; in parità vince il profilo più in alto nell'elenco. Tocca un profilo per vedere il suo risultato nel telefono.</p>
        <div class="ts-carte ts-carte-fila" id="ts-profili-carte"></div>
      <?php else: ?>
        <p class="hint ts-aiuto-passo">Il punteggio va da 0 a 100 (percentuale dei punti possibili, solo sulle domande mostrate e risposte). Le fasce devono coprire da 0 a 100 senza buchi: dopo una fascia che finisce a 33 la successiva comincia a 34. Tocca una fascia per cambiarla: il telefono mostra il suo risultato. I testi si scrivono nel passo 4.</p>
        <section class="ts-gruppo-fasce" aria-labelledby="ts-fasce-t"><h4 class="ts-sotto" id="ts-fasce-t">Fasce del totale</h4><div class="ts-fasce-ed" id="ts-fasce-ed" data-scope="totale"></div></section>
        <?php foreach ($d['aree'] as $a): ?>
        <section class="ts-gruppo-fasce" aria-labelledby="ts-fasce-t-<?= h($a['k']) ?>"><h4 class="ts-sotto" id="ts-fasce-t-<?= h($a['k']) ?>">Fasce dell'area «<?= h($a['nome']) ?>»</h4><div class="ts-fasce-ed ts-fasce-area" data-scope="<?= h($a['k']) ?>"></div></section>
        <?php endforeach; ?>
      <?php endif; ?>
      </div>
      <?= test_admin_lato() ?>
    </div>
  </div></div>
  <?= test_admin_finestra('ts-fin-fascia', 'Fascia', '<button type="button" class="btn btn-danger btn-sm" data-togli-f>Togli la fascia</button>') ?>
    <div id="ts-fascia-corpo"></div>
  <?= test_admin_finestra_fine() ?>
  <div class="sc-scatola ts-scatola" id="simulatore"><h3>Simulatore</h3><div class="sc-corpo">
  <?php if (!$sim): ?><p class="hint" style="margin-top:0">Il simulatore parte quando il test ha almeno una domanda (passo 2).</p>
  <?php else:
      $nomeF = fn(array $l, string $k) => (array_values(array_filter($l, fn($x) => $x['k'] === $k))[0]['nome'] ?? ($k === '' ? 'nessuna' : $k)); ?>
    <p class="hint" style="margin-top:0">Calcolato sul test <b>salvato</b>: dopo aver cambiato le fasce, salva e guarda di nuovo. Le prove a caso rispondono a caso a tutte le domande.</p>
    <div class="ad-tabella"><table class="ts-sim" data-no-schede>
      <thead><tr><th scope="col">Prova</th><?php if ($t['tipo'] === 'profili'): ?><th scope="col">Profilo che vince</th><?php else: ?><th scope="col">Totale</th><th scope="col">Fascia</th><?php foreach ($d['aree'] as $a): ?><th scope="col"><?= h($a['nome']) ?></th><?php endforeach; ?><th scope="col">Regola</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($sim['fisse'] as $f): $r = $f['r']; ?>
        <tr><th scope="row"><?= h($f['nome']) ?></th>
        <?php if ($t['tipo'] === 'profili'): ?><td><?= h($nomeF($d['profili'], $r['profilo'])) ?></td>
        <?php else: ?><td><?= $r['totale'] === null ? '-' : (int)$r['totale'] ?></td><td><?= h($nomeF($d['fasce'], $r['fascia'])) ?></td>
          <?php foreach ($d['aree'] as $a): $x = $r['aree'][$a['k']]; ?><td><?= $x['pct'] === null ? '-' : (int)$x['pct'] . ' (' . h($nomeF($a['fasce'], $x['fascia'])) . ')' ?></td><?php endforeach; ?>
          <td><?= $r['regola'] !== '' ? h($nomeF($d['regole'], $r['regola'])) : '-' ?></td><?php endif; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <h4 class="fo-sotto"><?= (int)$sim['quanti'] ?> prove a caso</h4>
    <ul class="ts-sim-conta">
      <?php if ($t['tipo'] === 'profili'): foreach ($d['profili'] as $p): ?><li><?= h($p['nome']) ?>: <b><?= (int)($sim['conta']['profilo'][$p['k']] ?? 0) ?></b></li><?php endforeach;
      else: foreach ($d['fasce'] as $f): ?><li>Totale «<?= h($f['nome']) ?>»: <b><?= (int)($sim['conta']['fascia'][$f['k']] ?? 0) ?></b></li><?php endforeach;
        foreach ($d['aree'] as $a) foreach ($a['fasce'] as $f): ?><li><?= h($a['nome']) ?> «<?= h($f['nome']) ?>»: <b><?= (int)($sim['conta']['aree'][$a['k']][$f['k']] ?? 0) ?></b></li><?php endforeach;
        foreach ($d['regole'] as $rg): ?><li>Regola «<?= h($rg['nome']) ?>»: <b><?= (int)($sim['conta']['regola'][$rg['k']] ?? 0) ?></b></li><?php endforeach;
      endif; ?>
    </ul>
    <?php if ($sim['mai']): ?><div class="ts-avviso" role="status"><p><b>Da controllare</b> (né al minimo, né a metà, né al massimo, né in <?= (int)$sim['quanti'] ?> prove a caso):</p><ul><?php foreach ($sim['mai'] as $m): ?><li><?= h($m) ?></li><?php endforeach; ?></ul><p class="hint">Una fascia stretta può uscire di rado: prova con più prove, oppure allargala.</p></div>
    <?php else: ?><p class="ts-bene" role="status">&#10003; Ogni <?= $t['tipo'] === 'profili' ? 'profilo vince' : 'fascia viene raggiunta' ?> almeno una volta.</p><?php endif; ?>
    <p class="ts-sim-azioni"><?php foreach ([100, 500, 1000] as $n): ?><a class="btn btn-secondary btn-sm" href="<?= h(test_admin_url('test', ['id' => $t['id'], 'passo' => 3, 'prove' => $n])) ?>#simulatore">Rifai con <?= $n ?> prove</a> <?php endforeach; ?></p>
  <?php endif; ?>
  </div></div>
<?php elseif ($passo === 4):
    // Una carta per fascia (o profilo, o fascia di un'area) e la sua finestra con la risposta breve e completa (Quill) e il pulsante.
    $tsFin = [];
    $campoTesto = function (string $chiave, array $x, string $nome, array $dati) use (&$tsFin) {
        $id = 'tt-' . substr(md5($chiave), 0, 10);
        $att = '';
        foreach ($dati as $k => $v) $att .= ' data-' . $k . '="' . h((string)$v) . '"';
        $boll = '<span class="ts-bollino' . (trim(strip_tags((string)($x['breve'] ?? ''))) === '' ? ' ts-bollino-manca' : '') . '" data-boll="breve">' . (trim(strip_tags((string)($x['breve'] ?? ''))) === '' ? 'Breve: manca' : 'Breve') . '</span>'
            . '<span class="ts-bollino' . (trim(strip_tags((string)$x['testo'])) === '' ? ' ts-bollino-manca' : '') . '" data-boll="testo">' . (trim(strip_tags((string)$x['testo'])) === '' ? 'Completa: manca' : 'Completa') . '</span>'
            . '<span class="ts-bollino" data-boll="pulsante"' . (trim((string)$x['pulsante_testo']) === '' ? ' hidden' : '') . '>Pulsante</span>';
        echo test_admin_carta($id . '-fin', h($nome), '', $boll, ' data-testo="' . h($id) . '"' . $att);
        $tsFin[] = [$id, $chiave, $x, $nome];
    }; ?>
  <input type="hidden" name="regole_json" id="ts-regole-json" value="">
  <div class="sc-scatola ts-scatola"><h3>4. Risultati</h3><div class="sc-corpo">
    <p class="hint ts-aiuto-passo">Per ogni <?= $t['tipo'] === 'profili' ? 'profilo' : 'fascia' ?> due testi: la <b>risposta breve</b>, a schermo alla fine del test, e la <b>risposta completa</b>, che arriva per email (e a schermo se la breve è vuota, o se nel passo 5 il risultato completo non è «solo per email»). Poi un pulsante facoltativo. Tocca una carta: il telefono mostra il risultato; con «Email» vedi l'email.</p>
    <div class="ts-lavoro">
      <div class="ts-banco">
        <section class="ts-gruppo-fasce" aria-labelledby="ts-ris-t"><h4 class="ts-sotto" id="ts-ris-t"><?= $t['tipo'] === 'profili' ? 'Profili' : 'Fasce del totale' ?></h4><div class="ts-carte ts-carte-fila">
        <?php if ($t['tipo'] === 'profili'): foreach ($d['profili'] as $p) $campoTesto('profilo|' . $p['k'], $p, $p['nome'], ['tipo' => 'profilo', 'nome' => $p['nome']]);
          if (!$d['profili']): ?><p class="hint">Ancora nessun profilo: aggiungili nel <a href="<?= h(test_admin_url('test', ['id' => $t['id'], 'passo' => 2])) ?>">passo 2</a>.</p><?php endif;
        else: foreach ($d['fasce'] as $f) $campoTesto('totale|' . $f['k'], $f, $f['nome'] . ' (da ' . $f['da'] . ' a ' . $f['a'] . ')', ['tipo' => 'totale', 'nome' => $f['nome'], 'da' => $f['da'], 'a' => $f['a']]);
          if (!$d['fasce']): ?><p class="hint">Ancora nessuna fascia: aggiungile nel <a href="<?= h(test_admin_url('test', ['id' => $t['id'], 'passo' => 3])) ?>">passo 3</a>.</p><?php endif;
        endif; ?>
        </div></section>
        <?php foreach ($d['aree'] as $a): ?>
        <section class="ts-gruppo-fasce" aria-labelledby="ts-ris-a-<?= h($a['k']) ?>"><h4 class="ts-sotto" id="ts-ris-a-<?= h($a['k']) ?>">Area «<?= h($a['nome']) ?>» (escono sotto il grafico)</h4><div class="ts-carte ts-carte-fila">
          <?php foreach ($a['fasce'] as $f) $campoTesto('area|' . $a['k'] . '|' . $f['k'], $f, $a['nome'] . ': ' . $f['nome'] . ' (da ' . $f['da'] . ' a ' . $f['a'] . ')', ['tipo' => 'area', 'nome' => $f['nome'], 'area' => $a['nome'], 'da' => $f['da'], 'a' => $f['a']]); ?>
        </div></section>
        <?php endforeach; ?>
        <?php if ($t['tipo'] !== 'profili'): ?>
        <section class="ts-gruppo-fasce" aria-labelledby="ts-regole-t"><h4 class="ts-sotto" id="ts-regole-t">Regole sul risultato</h4>
          <p class="hint">Una regola vince sulle fasce: per esempio «area Energia in fascia Bassa <b>e</b> area Tempo in fascia Poco». Si guardano in ordine, dalla prima: vale la prima che si avvera.</p>
          <div id="ts-regole-ed"></div></section>
        <?php endif; ?>
        <section class="ts-gruppo-fasce" aria-labelledby="ts-avv-t"><h4 class="ts-sotto" id="ts-avv-t">In fondo</h4><div class="ts-carte">
          <?= test_admin_carta('ts-fin-avvertenza', 'Avvertenza', '<span data-vivo="avvertenza">' . h($d['avvertenza'] !== '' ? $d['avvertenza'] : 'Nessuna avvertenza.') . '</span>') ?>
        </div></section>
      </div>
      <?= test_admin_lato('<div class="ts-seg ts-vista" role="group" aria-label="Che cosa mostra il telefono"><button type="button" data-vista="schermo" aria-pressed="true">A schermo</button><button type="button" data-vista="email" aria-pressed="false">Email</button></div>') ?>
    </div>
  </div></div>
  <?php foreach ($tsFin as [$id, $chiave, $x, $nome]): ?>
  <?= test_admin_finestra($id . '-fin', $nome) ?>
    <fieldset class="ts-testo-ris"><legend class="sr-solo"><?= h($nome) ?></legend>
      <div class="form-group"><label for="<?= $id ?>-br">Risposta breve (a schermo)</label><textarea id="<?= $id ?>-br" name="testi[<?= h($chiave) ?>][breve]" rows="3" class="ts-ricco"><?= h($x['breve'] ?? '') ?></textarea></div>
      <div class="form-group"><label for="<?= $id ?>">Risposta completa (per email; a schermo se la breve è vuota)</label><textarea id="<?= $id ?>" name="testi[<?= h($chiave) ?>][testo]" rows="4" class="ts-ricco"><?= h($x['testo']) ?></textarea></div>
      <div class="ts-griglia-2 ts-riga-pulsante"><div class="form-group"><label for="<?= $id ?>-bt">Testo del pulsante (facoltativo)</label><input type="text" id="<?= $id ?>-bt" name="testi[<?= h($chiave) ?>][pulsante_testo]" value="<?= h($x['pulsante_testo']) ?>" maxlength="80" placeholder="per esempio: Prenota una chiamata"></div>
        <div class="form-group"><label for="<?= $id ?>-bl">Link del pulsante</label><input type="text" id="<?= $id ?>-bl" name="testi[<?= h($chiave) ?>][pulsante_link]" value="<?= h($x['pulsante_link']) ?>" maxlength="500" placeholder="per esempio: /contatti/" data-link-sito></div></div>
    </fieldset>
  <?= test_admin_finestra_fine() ?>
  <?php endforeach; ?>
  <?php if ($t['tipo'] !== 'profili'): ?>
  <?= test_admin_finestra('ts-fin-regola', 'Regola', '<button type="button" class="btn btn-danger btn-sm" data-togli-r>Togli la regola</button>') ?>
    <div id="ts-regola-corpo"></div>
  <?= test_admin_finestra_fine('<button type="button" class="btn btn-secondary btn-sm" data-sposta-r="-1">Guarda prima</button><button type="button" class="btn btn-secondary btn-sm" data-sposta-r="1">Guarda dopo</button>') ?>
  <?php endif; ?>
  <?= test_admin_finestra('ts-fin-avvertenza', 'Avvertenza') ?>
    <div class="form-group"><label for="ts-avvertenza">Avvertenza in fondo al test e al risultato</label><textarea id="ts-avvertenza" name="avvertenza" rows="3" maxlength="600" aria-describedby="ts-avv-aiuto"><?= h($d['avvertenza']) ?></textarea><div class="hint" id="ts-avv-aiuto">Vuota: nessuna avvertenza. Di partenza: «<?= h(TEST_AVVERTENZA) ?>»</div></div>
  <?= test_admin_finestra_fine() ?>
<?php elseif ($passo === 5):
    $prob = test_problemi($t);
    $flussi = [];
    try {
        require_once dirname(__DIR__, 3) . '/includes/newsletter-flussi.php';
        foreach (db()->query('SELECT id, nome, inneschi, stato FROM ' . tbl('newsletter_flussi'))->fetchAll() as $f) foreach ((array)json_decode((string)$f['inneschi'], true) as $x) if (($x['tipo'] ?? '') === 'test' && in_array((int)($x['id'] ?? 0), [0, $t['id']], true)) { $flussi[(int)$f['id']] = $f; }
    } catch (Throwable $e) { error_log('test: flussi: ' . $e->getMessage()); }
    $tsDopo = $d['dopo']; ?>
  <div class="sc-scatola ts-scatola"><h3>5. Dopo il test</h3><div class="sc-corpo">
    <p class="hint ts-aiuto-passo">Cosa succede alla fine del test. Tocca una carta per cambiarla: il telefono mostra la fine del test, con il riquadro dell'email, il consenso e la casella della newsletter.</p>
    <div class="ts-lavoro">
      <div class="ts-banco"><div class="ts-carte">
        <?= test_admin_carta('ts-fin-email', 'Email del risultato', '<span data-vivo="email">' . ($tsDopo['solo_email'] ? 'Il risultato completo solo per email.' : ($tsDopo['email'] ? 'Il risultato si può ricevere per email.' : 'Niente email: il risultato solo a schermo.')) . '</span>', '<span class="ts-bollino" data-vivo="email-boll">' . ($tsDopo['solo_email'] || $tsDopo['email'] ? 'Acceso' : 'Spento') . '</span>') ?>
        <?= test_admin_carta('ts-fin-newsletter', 'Newsletter', '<span data-vivo="lista">' . h($tsDopo['lista'] && isset($tsListe[$tsDopo['lista']]) ? 'Lista: ' . $tsListe[$tsDopo['lista']] : 'Nessuna lista: niente casella per la newsletter.') . '</span>') ?>
        <?= test_admin_carta('ts-fin-automazioni', 'Automazioni', h($flussi ? count($flussi) . ($flussi && count($flussi) === 1 ? ' automazione usa' : ' automazioni usano') . ' questo test.' : 'Nessuna automazione usa ancora questo test.'), $flussi ? '<span class="ts-bollino">' . count($flussi) . '</span>' : '') ?>
        <?= test_admin_carta('ts-fin-privacy', 'Privacy', 'Cosa conserva il sito: l\'esito anonimo, con il solo giorno.') ?>
      </div></div>
      <?= test_admin_lato() ?>
    </div>
  </div></div>
  <?= test_admin_finestra('ts-fin-email', 'Email del risultato') ?>
    <div class="ts-griglia-2">
      <div><label class="nl-casella fo-casella"><input type="checkbox" name="solo_email" value="1"<?= $tsDopo['solo_email'] ? ' checked' : '' ?> aria-describedby="ts-solo-aiuto"> Il risultato completo solo per email</label>
        <div class="hint" id="ts-solo-aiuto">Il test finisce con la risposta breve e sotto «<?= h(TEST_TITOLO_EMAIL) ?>». Spenta: a schermo la breve e sotto la completa.</div></div>
      <div><label class="nl-casella fo-casella"><input type="checkbox" name="email" value="1"<?= $tsDopo['email'] ? ' checked' : '' ?> aria-describedby="ts-email-aiuto"> Offri il risultato per email</label>
        <div class="hint" id="ts-email-aiuto">Sotto il risultato «Vuoi ricevere il risultato per email?». Si chiede entro <?= TEST_EMAIL_ORE ?> ore dal test, dalla pagina del risultato.</div></div>
    </div>
    <div class="form-group"><label for="ts-consenso">Testo del consenso (casella da spuntare)</label><textarea id="ts-consenso" name="consenso" rows="3" maxlength="600" aria-describedby="ts-consenso-aiuto"><?= h($tsDopo['consenso']) ?></textarea><div class="hint" id="ts-consenso-aiuto">Un test sulla salute tratta un dato particolare: il consenso è separato dall'iscrizione e la casella parte vuota.</div></div>
  <?= test_admin_finestra_fine() ?>
  <?= test_admin_finestra('ts-fin-newsletter', 'Newsletter') ?>
    <div class="ts-griglia-2">
      <div class="form-group"><label for="ts-lista">Iscrizione a una lista (facoltativa)</label><select id="ts-lista" name="lista" aria-describedby="ts-lista-aiuto"><option value="0">Nessuna: niente casella</option><?php foreach ($tsListe as $lid => $lnome): ?><option value="<?= $lid ?>"<?= $lid === $tsDopo['lista'] ? ' selected' : '' ?>><?= h($lnome) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="ts-iscrizione">Testo della casella per iscriversi</label><input type="text" id="ts-iscrizione" name="iscrizione" value="<?= h($tsDopo['iscrizione']) ?>" maxlength="300"></div>
    </div>
    <p class="hint" id="ts-lista-aiuto">Con una lista, sotto il consenso compare una seconda casella, separata, per iscriversi. Arriva l'email per confermare (doppia conferma, come i form): solo chi si iscrive entra nei Contatti, con l'esito del test nella sua scheda. Alla conferma riceve come tag le risposte sulla persona («Età: 35-44») e l'esito («Test <?= h($t['nome']) ?>: ...»). Chi lascia l'email senza la newsletter riceve solo il risultato.</p>
  <?= test_admin_finestra_fine() ?>
  <?= test_admin_finestra('ts-fin-automazioni', 'Automazioni') ?>
    <p class="hint">Un'automazione con l'innesco <b>«Completa un test»</b> parte per chi si iscrive dalla pagina del risultato, dopo la conferma. Puoi filtrare sul risultato (una fascia, un'area, un profilo, una regola) e mandare email diverse a chi ha avuto risultati diversi.</p>
    <?php if ($flussi): ?><ul class="ts-flussi"><?php foreach ($flussi as $f): ?><li><a href="newsletter-flussi.php?id=<?= (int)$f['id'] ?>"><?= h(newsletter_4byte_fuori((string)$f['nome'])) ?></a> <span class="badge <?= $f['stato'] === 'attiva' ? 'badge-published' : 'badge-draft' ?>"><?= $f['stato'] === 'attiva' ? 'Attiva' : 'Non attiva' ?></span></li><?php endforeach; ?></ul>
    <?php else: ?><p>Nessuna automazione usa ancora questo test. <a href="newsletter-flussi.php">Crea un'automazione</a> e scegli l'innesco «Completa un test».</p><?php endif; ?>
  <?= test_admin_finestra_fine() ?>
  <?= test_admin_finestra('ts-fin-privacy', 'Privacy') ?>
    <ul class="ts-privacy">
      <li>Le risposte sono <b>anonime</b>: si conservano l'esito e i punteggi con il solo giorno, per le statistiche. Niente nome, niente indirizzo IP, niente ora.</li>
      <li>Le risposte domanda per domanda sono solo contatori («Spesso» scelto 12 volte): nessun legame con una persona.</li>
      <li>Il link al risultato contiene solo l'esito, firmato: non si può inventare un risultato cambiandolo.</li>
      <li>L'email si usa per mandare il risultato e si salva solo se la persona si iscrive alla lista: allora l'esito va nella sua scheda dei Contatti.</li>
      <li>Le risposte sulla persona si salvano con l'esito, anonime. Nelle statistiche per gruppi un gruppo con meno di <?= TEST_GRUPPO_MINIMO ?> persone non si mostra. Diventano tag solo di chi si iscrive, dopo la conferma.</li>
    </ul>
  <?= test_admin_finestra_fine() ?>
<?php endif; ?>
  <div class="fo-azioni">
    <?php if ($passo > 1): ?><a class="btn btn-secondary" href="<?= h(test_admin_url('test', ['id' => $t['id'], 'passo' => $passo - 1])) ?>">Indietro</a><?php endif; ?>
    <button type="submit" class="btn btn-secondary" name="vai" value="resta">Salva</button>
    <?php if ($passo < 5): ?><button type="submit" class="btn btn-primary" name="vai" value="avanti">Salva e vai avanti: <?= h(strtolower(TEST_PASSI[$passo + 1])) ?></button><?php endif; ?>
  </div>
</form>
<?php if ($passo === 5): ?>
  <div class="sc-scatola ts-scatola" id="pubblica"><h3>Pubblica</h3><div class="sc-corpo">
    <?php if ($prob): ?><div class="ts-avviso" role="status"><p><b>Prima di pubblicare sistema:</b></p><ul><?php foreach ($prob as $p): ?><li><?= h($p) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($t['stato'] === 'pubblicato'): ?>
      <p>Il test è pubblicato: <a href="<?= h(cms_url_sito() . test_url($t)) ?>" target="_blank" rel="noopener"><?= h(cms_url_sito() . test_url($t)) ?></a>. Per metterlo in una pagina usa il widget <b>Riquadro in evidenza</b> e scegli il test, oppure un link con «Scegli dal sito».</p>
      <form method="post" data-conferma="Rimettere il test in bozza? La sua pagina non si aprirà più finché non lo pubblichi di nuovo." data-conferma-ok="Rimetti in bozza"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button type="submit" name="azione" value="bozza" class="btn btn-secondary">Rimetti in bozza</button></form>
    <?php else: ?>
      <p>Il test è in bozza: lo vedi solo tu, con <a href="<?= h($vedi) ?>" target="_blank" rel="noopener">l'anteprima</a>. Salva il passo 5, poi pubblica.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button type="submit" name="azione" value="pubblica" class="btn btn-primary"<?= $prob ? ' disabled aria-describedby="ts-pub-aiuto"' : '' ?>>Pubblica il test</button><?php if ($prob): ?> <span class="hint" id="ts-pub-aiuto">Si accende quando i punti qui sopra sono sistemati.</span><?php endif; ?></form>
    <?php endif; ?>
  </div></div>
<?php endif; ?>
<?php if ($passo === 1): ?>
  <div class="ts-elimina-box"><form method="post" data-conferma="Duplicare il test «<?= h($t['nome']) ?>»? La copia nasce in bozza." data-conferma-ok="Duplica"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button type="submit" name="azione" value="duplica" class="btn btn-secondary btn-sm">Duplica il test</button></form>
  <form method="post" data-conferma="Eliminare il test «<?= h($t['nome']) ?>» con tutte le sue statistiche? Non si torna indietro." data-conferma-ok="Elimina il test" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="azione" value="elimina"><button type="submit" class="sc-cestina">Elimina il test</button></form></div>
<?php endif; ?>
<?php if ($passo === 4): ?><link rel="stylesheet" href="<?= pannello_file('assets/quill/quill.snow.css') ?>"><script src="<?= pannello_file('assets/quill/quill.js') ?>"></script><?php endif; ?>
<?php if ($passo === 2): ?><script src="<?= pannello_file('assets/editor/Sortable.min.js') ?>"></script><?php endif; ?>
<script type="application/json" id="ts-iniziali"><?= json_encode($iniziali, $j) ?></script>
<script src="<?= h(test_admin_asset('admin-test.js')) ?>"></script>
<?php endif; ?>
<?php require CMS_ADMIN . '/includes/admin-footer.php';
