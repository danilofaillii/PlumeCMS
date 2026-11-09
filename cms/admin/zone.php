<?php
/**
 * SEO e AEO > Zone (2.28.0): le pagine per zona, la seconda fase della SEO geografica. Motore in includes/zone.php (2.36.0: il
 * registro nella tabella <prefisso>zone, senza tetto). Solo amministratore.
 *   zone.php                              il registro delle pagine per zona (50 per pagina) e il generatore delle bozze
 *   zone.php?servizio=Idraulico           il registro filtrato per servizio
 *   zone.php?cerca=bologna&pag=2          la ricerca per zona, servizio o titolo, e la pagina dell'elenco
 *   zone.php?continua=1                   "Crea le bozze" a pezzi: la pagina continua da sola con le zone che restano
 *   zone.php?ritrova=1                    2.37.1: cerca le pagine per zona rimaste fuori dal registro (la 2.35.0 si fermava a 200);
 *                                         la prima apertura dopo l'aggiornamento le cerca da sola
 * Il generatore crea solo BOZZE: ogni pagina ha tre riquadri guidati con il segnaposto [DA SCRIVERE] e finché restano la pagina
 * non si pubblica da nessuna strada (zone_blocco_pubblicazione). Chi scrive (anche un redattore) apre la bozza nell'editor,
 * compila i riquadri e pubblica. Come sedi.php, una pagina sua e non una scheda di seo.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_once dirname(__DIR__) . '/includes/sedi.php';
require_once dirname(__DIR__) . '/includes/zone.php';
require_admin();
$adminTitle = 'Zone';
$errori = [];
$valori = null; // i valori del modulo dopo un invio con errori
$filtro = sedi_testo($_GET['servizio'] ?? '', 80);
$cerca = sedi_testo($_GET['cerca'] ?? '', 80);
$pag = isset($_GET['pag']) && is_scalar($_GET['pag']) && preg_match('/^\d{1,7}$/', (string)$_GET['pag']) ? max(1, (int)$_GET['pag']) : 1;
// "Crea le bozze" a pezzi (2.36.0): un giro lungo si ferma dopo ZONE_PEZZO_SECONDI e il resto aspetta nella sessione.
$pezzi = isset($_SESSION['zone_pezzi']) && is_array($_SESSION['zone_pezzi']) ? $_SESSION['zone_pezzi'] : null;
/** Un pezzo del giro: crea le bozze, somma ai pezzi di prima; ritorna dove andare. */
$pezzo = function (array $valori, array $prima, string $autore): string {
    $r = zone_crea_bozze($valori, $autore, zone_pezzo_secondi());
    $tot = ['servizio' => $valori['servizio'], 'create' => (int)($prima['create'] ?? 0) + count($r['create']),
        'nomi' => array_slice(array_merge((array)($prima['nomi'] ?? []), array_map(fn($c) => $c['titolo'], $r['create'])), 0, 8),
        'esistenti' => array_slice(array_merge((array)($prima['esistenti'] ?? []), array_map(fn($e) => $e['titolo'], $r['esistenti'])), 0, 8),
        'n_esistenti' => (int)($prima['n_esistenti'] ?? 0) + count($r['esistenti']), 'fatte' => (int)($prima['fatte'] ?? 0) + count($r['create']) + count($r['esistenti'])];
    if ($r['restanti']) {
        $_SESSION['zone_pezzi'] = $tot + ['valori' => ['zone' => array_values($r['restanti'])] + $valori, 'restano' => count($r['restanti'])];
        return 'zone.php?continua=1';
    }
    unset($_SESSION['zone_pezzi']);
    $n = $tot['create'];
    $nomi = implode(', ', array_map(fn($t) => '«' . $t . '»', $tot['nomi'])) . ($n > count($tot['nomi']) ? ' e altre ' . ($n - count($tot['nomi'])) : '');
    if ($n > 0) flash('success', ($n === 1 ? '1 bozza creata: ' : $n . ' bozze create: ') . $nomi . '. Ognuna ha tre riquadri con il segnaposto ' . ZONE_SEGNAPOSTO . ': aprila nell\'editor, scrivi il testo al posto dei segnaposto e poi pubblica. Finché un segnaposto resta, la pagina non si pubblica.');
    $ne = $tot['n_esistenti'];
    if ($ne > 0) flash('info', 'C\'è già: ' . implode(', ', array_map(fn($t) => '«' . $t . '»', $tot['esistenti'])) . ($ne > count($tot['esistenti']) ? ' e altre ' . ($ne - count($tot['esistenti'])) : '') . ($n === 0 ? '. Nessuna bozza nuova.' : '.'));
    return 'zone.php?servizio=' . rawurlencode($valori['servizio']);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $autore = (string)(current_user()['username'] ?? '');
    try {
        switch ($azione) {
            case 'crea':
                $in = is_array($_POST['zone'] ?? null) ? $_POST['zone'] : [];
                $v = zone_valida($in);
                if ($v['errori']) {
                    $errori = $v['errori'];
                    $valori = $in;
                    flash('error', count($errori) . (count($errori) === 1 ? ' campo da sistemare: lo trovi segnato' : ' campi da sistemare: li trovi segnati') . ' in rosso qui sotto.');
                    break;
                }
                unset($_SESSION['zone_pezzi']);
                header('Location: ' . $pezzo($v['valori'], [], $autore)); exit;
            case 'continua':
                if (!$pezzi || empty($pezzi['valori']['zone'])) { unset($_SESSION['zone_pezzi']); throw new RuntimeException('Non c\'è nessun giro da continuare: le bozze sono già state create (le trovi nell\'elenco).'); }
                header('Location: ' . $pezzo($pezzi['valori'], $pezzi, $autore)); exit;
            case 'ferma':
                unset($_SESSION['zone_pezzi']);
                flash('info', 'Giro fermato: le bozze già create restano nell\'elenco, le altre zone no. Per crearle, riscrivile nel riquadro qui sotto.');
                header('Location: zone.php'); exit;
            case 'togli':
                $id = (int)($_POST['id'] ?? 0);
                $z = zone_di_pagina($id);
                if (!$z) throw new RuntimeException('Questa pagina non è più nel registro.');
                zone_elimina($id);
                flash('success', '«' . $z['riga']['title'] . '» tolta dal registro delle pagine per zona. La pagina resta nel sito com\'è (' . (zone_pubblicata($z) ? 'pubblicata' : 'in bozza') . '): se non la vuoi più, cestinala da Pagine.');
                $torna = [];
                parse_str((string)($_POST['torna'] ?? ''), $torna);
                $torna = array_filter(['servizio' => sedi_testo($torna['servizio'] ?? $filtro, 80), 'cerca' => sedi_testo($torna['cerca'] ?? '', 80), 'pag' => preg_match('/^\d{1,7}$/', (string)($torna['pag'] ?? '')) ? (string)$torna['pag'] : '']);
                header('Location: zone.php' . ($torna ? '?' . http_build_query($torna) : '')); exit;
            case 'ritrova':
                if (!function_exists('zone_ritrova')) throw new RuntimeException('Ricarica la pagina fra qualche secondo e riprova: l\'aggiornamento sta ancora finendo.');
                $n = zone_ritrova();
                flash($n > 0 ? 'success' : 'info', $n > 0 ? ($n === 1 ? '1 pagina per zona rimessa nel registro' : $n . ' pagine per zona rimesse nel registro') . ': da adesso hanno di nuovo i dati per Google (il servizio e la zona servita), le briciole e il widget Altre zone. Le trovi nell\'elenco qui sotto.' : 'Nessuna pagina per zona da rimettere nel registro: sono già tutte qui sotto.');
                header('Location: zone.php'); exit;
            case 'lascia_fuori':
                if (function_exists('zone_segna_controllato')) zone_segna_controllato();
                flash('info', 'Va bene: quelle pagine restano fuori dal registro e il pannello non te le propone più. Se cambi idea, in fondo all\'elenco c\'è «Cerca le pagine per zona fuori dal registro».');
                header('Location: zone.php'); exit;
            default:
                throw new RuntimeException('Azione sconosciuta.');
        }
    } catch (RuntimeException | InvalidArgumentException $e) {
        flash('error', $e->getMessage());
        header('Location: zone.php'); exit;
    } catch (Throwable $e) {
        error_log('zone.php: ' . $e->getMessage());
        flash('error', 'Qualcosa non ha funzionato: ' . $e->getMessage());
        header('Location: zone.php'); exit;
    }
}

$migrata = zone_migra(); // un registro della 2.35.0 rimasto nella configurazione passa nella tabella (una volta sola)
if (!$migrata) flash('error', 'Il registro delle pagine per zona della versione di prima non è riuscito a passare nella tabella nuova del database: per ora l\'elenco qui sotto è vuoto, ma niente è perso. Ricarica la pagina fra qualche minuto; se resta così, il dettaglio è nel log degli errori.');
zone_pulisci(); // le pagine sparite dal cestino escono dal registro
// 2.37.1: le pagine per zona rimaste fuori dal registro (la 2.35.0 si fermava a 200). La prima apertura dopo l'aggiornamento le cerca
// da sola; se non ce ne sono il pannello non le cerca più, se ci sono il riquadro resta finché non si sceglie. ?ritrova=1 le cerca sempre.
$fuori = null;
$cercaFuori = isset($_GET['ritrova']);
if ($migrata && function_exists('zone_fuori_registro') && ($cercaFuori || empty(zone_cfg()['controllato']))) {
    $fuori = zone_fuori_registro();
    if (!$fuori['voci']) { zone_segna_controllato(); if (!$cercaFuori && !$fuori['doppioni']) $fuori = null; }
}
$servizi = zone_servizi();
if ($filtro !== '' && !in_array(mb_strtolower($filtro), array_map('mb_strtolower', $servizi), true)) $filtro = '';
$conteggiTutte = zone_conteggi();
$elenco = zone_elenco(['servizio' => $filtro, 'cerca' => $cerca, 'pagina' => $pag]);
$mostrate = $elenco['voci'];
$conteggi = $filtro === '' && $cerca === '' ? $conteggiTutte : zone_conteggi(['servizio' => $filtro, 'cerca' => $cerca]);
$avvisi = zone_avvisi_widget();
$continua = isset($_GET['continua']) && $pezzi && !empty($pezzi['valori']['zone']);
$sedi = sedi_tutte();
$modelli = zone_modelli();
// I valori nel modulo: quelli appena scritti (con errori), oppure quelli di partenza (le zone dall'unione delle sedi).
$f = ['servizio' => $filtro, 'zone' => implode("\n", array_map(fn($a) => $a['name'], sedi_area_servita())), 'sede' => count($sedi) === 1 ? array_key_first($sedi) : 'principale',
    'servizio_pagina' => '', 'titolo' => $modelli['titolo'], 'descrizione' => $modelli['descrizione']];
if ($valori !== null) foreach ($f as $k => $x) $f[$k] = (string)($valori[$k] ?? '');
$pagine = zone_pagine_sito();
$opzioniSede = sedi_opzioni_widget(false);
$csrf = csrf_token();
$campo = fn(string $k) => isset($errori[$k]) ? ' class="zn-sbagliato"' : '';
$aria = fn(string $k) => isset($errori[$k]) ? ' aria-invalid="true" aria-describedby="zn-err-' . $k . '"' : '';
$err = fn(string $k) => isset($errori[$k]) ? '<div class="errore-campo zn-errore" id="zn-err-' . $k . '" role="alert">' . h($errori[$k]) . '</div>' : '';
$stati = ['bozza' => 'Bozza', 'programmato' => 'Programmata', 'pubblicato' => 'Pubblicata', 'cestino' => 'Nel cestino'];

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.zn-sotto{font-size:.84rem;color:var(--text-muted);margin:0 0 .9rem;line-height:1.5}
.zn-sotto b{color:var(--text)}
.zn-filtro{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:0 0 .9rem}
.zn-filtro label{font-size:.86rem;color:var(--text-muted)}
.zn-filtro select{padding:.4rem .6rem}
.zn-stato{display:inline-block;padding:.1rem .55rem;border-radius:999px;font-size:.74rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border:1px solid var(--border);color:var(--text-muted);background:var(--bg)}
.zn-stato.zn-pubblicata{background:var(--sage-light);color:var(--successo-testo);border-color:var(--successo-bordo)}
.zn-stato.zn-bozza{background:var(--attenzione-chiaro);color:var(--attenzione-testo);border-color:var(--attenzione-bordo)}
.zn-da-scrivere{font-size:.84rem}
.zn-da-scrivere b{color:var(--attenzione-testo)}
.zn-pronta{color:var(--successo-testo);font-weight:600}
.zn-pronta::before{content:"\2713\00a0"}
td .zn-tipo{display:block;font-size:.8rem;color:var(--text-muted)}
.zn-azioni{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
.zn-azioni form{display:inline}
.zn-campi{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 1.4rem}
.zn-campi .zn-largo{grid-column:1/-1}
.zn-fascia{grid-column:1/-1;margin:.6rem 0 .8rem;padding-top:.8rem;border-top:1px solid var(--border);font-size:.78rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted)}
.zn-campi .form-group.zn-sbagliato input,.zn-campi .form-group.zn-sbagliato select,.zn-campi .form-group.zn-sbagliato textarea{border:2px solid var(--errore)!important}
.zn-errore{margin-top:.35rem;font-size:.84rem;color:var(--errore-testo)}
.zn-campi .form-group textarea{min-height:0;height:auto;resize:vertical;font-family:var(--font);font-size:.9rem;line-height:1.5}
.zn-riquadri{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem;margin:.4rem 0 .2rem}
.zn-riquadri div{border:1px dashed var(--bordo-forte);border-radius:8px;padding:.6rem .8rem;font-size:.84rem;line-height:1.45;background:var(--bg)}
.zn-riquadri b{display:block;margin-bottom:.2rem;color:var(--text)}
.zn-riquadri code{font-size:.8rem}
.zn-numeri{display:flex;flex-wrap:wrap;gap:.4rem 1.2rem;margin:0 0 .9rem;font-size:.9rem;color:var(--text)}
.zn-filtro input[type=search]{flex:1 1 14rem;min-width:0;max-width:22rem;padding:.4rem .6rem}
.zn-cerca-aiuto{flex-basis:100%;margin:0}
.zn-quante{margin:0 0 .5rem}
.zn-avviso{margin:0 0 .9rem;padding:.7rem .9rem;border:1px solid var(--info-bordo);border-left-width:4px;border-radius:8px;background:var(--info-chiaro);color:var(--info-testo);font-size:.88rem;line-height:1.5}
.zn-avviso b{color:inherit}
.zn-pagine{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:.9rem}
.zn-pagine span{font-size:.88rem;color:var(--text-muted)}
.zn-ferma{margin:-.4rem 0 1.2rem}
.zn-fuori{border-left:4px solid var(--attenzione-bordo)}
.zn-fuori ul{margin:.2rem 0 .8rem;padding-left:1.2rem;font-size:.88rem;line-height:1.6}
.zn-fuori li span{color:var(--text-muted)}
.zn-cerca-fuori{margin:.5rem 0 0;font-size:.86rem}
@media (max-width:760px){.zn-fuori .btn{min-height:44px}.zn-campi{grid-template-columns:minmax(0,1fr)}.zn-riquadri{grid-template-columns:minmax(0,1fr)}.zn-filtro select{max-width:100%}}
[hidden]{display:none!important}
</style>

<nav class="imp-schede" aria-label="Schede di SEO e AEO">
  <?php foreach (['panoramica' => 'Panoramica', 'motori' => 'Motori di ricerca', 'copertura' => 'Copertura', 'prestazioni' => 'Prestazioni', 'analisi' => 'Analisi e tag', 'globale' => 'Globale SEO', 'schema' => 'Schema e AEO', 'redirect' => 'Redirect e 404', 'log' => 'Registro'] as $k => $v): ?><a href="seo.php?scheda=<?= $k ?>"><?= h($v) ?></a><?php endforeach; ?><a href="sedi.php">Sedi</a><a href="zone.php" class="attiva" aria-current="page">Zone</a><a href="seo-tabella.php">Tabella SEO</a>
</nav>

<?php if ($continua): ?>
<form method="post" class="form-card" id="zn-continua" aria-live="polite">
  <?= csrf_field() ?>
  <h3>Sto creando le bozze a pezzi</h3>
  <input type="hidden" name="azione" value="continua">
  <p class="zn-sotto">Per «<?= h((string)$pezzi['servizio']) ?>»: <b><?= (int)$pezzi['fatte'] ?></b> zone fatte (<?= (int)$pezzi['create'] ?> bozze nuove), ne restano <b><?= (int)$pezzi['restano'] ?></b>. Un giro lungo si fa a pezzi di pochi secondi, così il server non si ferma a metà: la pagina continua da sola. Non chiuderla finché non compare il messaggio verde.</p>
  <div class="zn-azioni"><button class="btn btn-primary" type="submit" id="zn-continua-bottone">Continua a creare le bozze</button></div>
</form>
<form method="post" class="zn-ferma" id="zn-ferma"><?= csrf_field() ?><input type="hidden" name="azione" value="ferma"><button class="btn btn-secondary btn-sm" type="submit">Ferma il giro (le bozze già create restano)</button></form>
<script<?= nonce_attr() ?>>setTimeout(function () { var f = document.getElementById('zn-continua'); if (f) { var b = document.getElementById('zn-continua-bottone'); if (b) { b.disabled = true; b.textContent = 'Continuo...'; } f.submit(); } }, 400);</script>
<?php endif; ?>

<?php if ($fuori !== null): $nf = count($fuori['voci']); $nd = count($fuori['doppioni']); ?>
<div class="form-card zn-fuori" id="zn-fuori" role="region" aria-labelledby="zn-fuori-titolo">
  <?php if ($nf > 0): ?>
  <h3 id="zn-fuori-titolo"><?= $nf === 1 ? '1 pagina per zona è fuori dal registro' : $nf . ' pagine per zona sono fuori dal registro' ?></h3>
  <p class="zn-sotto"><?= $nf === 1 ? 'È nel sito, ma non' : 'Sono nel sito, ma non' ?> in questo elenco: fino alla versione 2.35.0 il registro teneva al massimo 200 pagine per zona e le altre restavano fuori. Così a Google mancano il servizio e la zona servita, mancano le briciole e il widget <b>Altre zone</b> non <?= $nf === 1 ? 'la' : 'le' ?> elenca. <?= $nf === 1 ? 'Si riconosce' : 'Si riconoscono' ?> dal widget Altre zone e dall'apertura con cui <?= $nf === 1 ? 'è nata' : 'sono nate' ?>: servizio e zona si leggono da lì. <b><?= $nf === 1 ? 'Rimettila nel registro' : 'Rimettile nel registro' ?></b> e <?= $nf === 1 ? 'torna' : 'tornano' ?> nell'elenco qui sotto, con i dati per Google; le pagine non cambiano.</p>
  <ul>
    <?php foreach (array_slice($fuori['voci'], 0, ZONE_FUORI_MOSTRATE) as $v): ?><li><b><?= h($v['titolo']) ?></b> <span>(servizio «<?= h($v['servizio']) ?>», zona «<?= h($v['zona']) ?>», /<?= h($v['slug']) ?>/)</span></li><?php endforeach; ?>
    <?php if ($nf > ZONE_FUORI_MOSTRATE): ?><li>e altre <?= $nf - ZONE_FUORI_MOSTRATE ?></li><?php endif; ?>
  </ul>
  <?php else: ?>
  <h3 id="zn-fuori-titolo">Nessuna pagina per zona da rimettere nel registro</h3>
  <p class="zn-sotto">Fra le pagine del sito, quelle nate come pagine per zona sono già tutte nell'elenco qui sotto.</p>
  <?php endif; ?>
  <?php if ($nd > 0): ?>
  <p class="zn-sotto" id="zn-doppioni"><?= $nd === 1 ? '1 pagina ha' : $nd . ' pagine hanno' ?> lo stesso servizio e la stessa zona di una pagina che è già nel registro, e restano fuori: sono doppioni. Guarda quale tenere e cestina l'altra da Pagine: <?= implode(', ', array_map(fn($v) => '«' . h($v['titolo']) . '» (/' . h($v['slug']) . '/)', array_slice($fuori['doppioni'], 0, 8))) ?><?= $nd > 8 ? ' e altre ' . ($nd - 8) : '' ?>.</p>
  <?php endif; ?>
  <?php if ($nf > 0): ?>
  <div class="zn-azioni">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="ritrova"><button class="btn btn-primary" type="submit" id="zn-ritrova"><?= $nf === 1 ? 'Rimettila nel registro' : 'Rimettile nel registro' ?></button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="lascia_fuori"><button class="btn btn-secondary" type="submit" id="zn-lascia-fuori"><?= $nf === 1 ? 'Lasciala fuori' : 'Lasciale fuori' ?></button></form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="form-card zn-elenco" id="zn-elenco">
  <h3>Le pagine per zona</h3>
  <p class="zn-sotto">Una pagina per ogni coppia <b>servizio + zona</b> ("Idraulico a Casalecchio di Reno"): è così che chi cerca un servizio nel suo comune trova te. Qui sotto <b>Crea le bozze</b> le fa nascere con l'apertura, tre riquadri da scrivere, il widget <b>Altre zone</b> e i dati della sede; nessuna si pubblica da sola. Tu (o un redattore) apri ogni bozza nell'editor, scrivi al posto dei segnaposto <code><?= h(ZONE_SEGNAPOSTO) ?></code> e pubblichi: finché un segnaposto resta, la pagina resta bozza. Così niente pagine vuote tutte uguali, che Google ignora. Nella pagina pubblicata i dati per Google dicono il servizio, la zona servita e la sede che lo fa.</p>
  <?php if ($conteggiTutte['totale'] === 0): ?>
  <div class="empty-state">Ancora nessuna pagina per zona: scrivi il servizio e le zone nel riquadro qui sotto, <b>Crea le pagine per zona</b>, e premi <b>Crea le bozze</b>.</div>
  <?php else: ?>
  <p class="zn-numeri" id="zn-numeri"><span><b><?= $conteggiTutte['totale'] ?></b> <?= $conteggiTutte['totale'] === 1 ? 'pagina per zona' : 'pagine per zona' ?> in tutto</span><span><b><?= $conteggiTutte['pubblicate'] ?></b> <?= $conteggiTutte['pubblicate'] === 1 ? 'pubblicata' : 'pubblicate' ?></span><span><b><?= $conteggiTutte['da_scrivere'] ?></b> <?= $conteggiTutte['da_scrivere'] === 1 ? 'bozza' : 'bozze' ?> con riquadri da scrivere</span></p>
  <?php foreach ($avvisi as $av): ?>
  <div class="zn-avviso" role="status"><span class="sr-solo">Consiglio: </span>Il servizio <b>«<?= h($av['servizio']) ?>»</b> ha <b><?= $av['pubblicate'] ?></b> pagine per zona pubblicate e il widget <b>Altre zone</b> le mostra tutte (<?= $av['pubblicate'] - 1 ?> link in ogni pagina): un elenco così lungo non lo legge nessuno e Google lo pesa poco. Apri una pagina nell'editor, nel widget Altre zone imposta <b>Quante mostrarne</b> fra 12 e 20 e <b>Quali mostrare</b> su "Le più vicine", poi fai lo stesso nelle altre pagine di quel servizio.</div>
  <?php endforeach; ?>
  <form method="get" class="zn-filtro" id="zn-filtro" role="search">
    <?php if (count($servizi) > 1): ?>
    <label for="zn-filtro-servizio">Servizio</label>
    <select name="servizio" id="zn-filtro-servizio" data-invia-al-cambio><option value="">Tutti i servizi (<?= $conteggiTutte['totale'] ?>)</option><?php foreach ($servizi as $sv): ?><option value="<?= h($sv) ?>" <?= mb_strtolower($sv) === mb_strtolower($filtro) ? 'selected' : '' ?>><?= h($sv) ?></option><?php endforeach; ?></select>
    <?php elseif ($filtro !== ''): ?><input type="hidden" name="servizio" value="<?= h($filtro) ?>"><?php endif; ?>
    <label for="zn-cerca">Cerca</label>
    <input type="search" name="cerca" id="zn-cerca" value="<?= h($cerca) ?>" maxlength="80" placeholder="per esempio: Bologna" aria-describedby="zn-cerca-aiuto">
    <button class="btn btn-secondary btn-sm" type="submit">Cerca</button>
    <?php if ($cerca !== '' || $filtro !== ''): ?><a class="btn btn-secondary btn-sm" href="zone.php">Mostra tutte</a><?php endif; ?>
    <span class="hint zn-cerca-aiuto" id="zn-cerca-aiuto">Cerca nella zona, nel servizio e nel titolo della pagina.</span>
  </form>
  <?php if ($mostrate): ?>
  <p class="hint zn-quante" id="zn-quante"><?php if ($elenco['totale'] > count($mostrate)): ?>Righe <?= ($elenco['pagina'] - 1) * $elenco['per_pagina'] + 1 ?>-<?= ($elenco['pagina'] - 1) * $elenco['per_pagina'] + count($mostrate) ?> di <?= $elenco['totale'] ?><?php else: ?><?= $elenco['totale'] ?> <?= $elenco['totale'] === 1 ? 'pagina' : 'pagine' ?><?php endif; ?><?= $cerca !== '' ? ' per «' . h($cerca) . '»' : '' ?><?= $filtro !== '' ? ' del servizio «' . h($filtro) . '»' : '' ?> (<?= $conteggi['pubblicate'] ?> pubblicate, <?= $conteggi['da_scrivere'] ?> con riquadri da scrivere).</p>
  <table class="table" id="zn-tabella">
    <thead><tr><th>Servizio</th><th>Zona</th><th>Pagina</th><th>Stato</th><th>Da scrivere</th><th>Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($mostrate as $id => $z): $stato = zone_stato($z); $restanti = zone_segnaposto_pagina($id); $riga = $z['riga']; ?>
    <tr>
      <td><?= h($z['servizio']) ?></td>
      <td><?= h($z['zona']) ?></td>
      <td><?php if ($stato === 'pubblicato'): ?><a href="<?= h(cms_sito_base() . contenuti_percorso('pagine', $riga)) ?>" target="_blank">/<?= h($riga['slug']) ?>/</a><?php else: ?><a href="<?= h(cms_sito_base() . contenuti_percorso('pagine', $riga)) ?>?preview=1" target="_blank">/<?= h($riga['slug']) ?>/</a><?php endif; ?><span class="zn-tipo"><?= h($riga['title']) ?></span></td>
      <td><span class="zn-stato zn-<?= $stato === 'pubblicato' ? 'pubblicata' : ($stato === 'bozza' ? 'bozza' : $stato) ?>"><?= h($stati[$stato] ?? $stato) ?></span></td>
      <td class="zn-da-scrivere"><?php if ($restanti > 0): ?>riquadri da scrivere: <b><?= $restanti ?></b><?php else: ?><span class="zn-pronta">pronta</span><?php endif; ?></td>
      <td><div class="zn-azioni">
        <a class="btn btn-secondary btn-sm" href="editor.php?tipo=pagine&amp;id=<?= (int)$id ?>">Apri nell'editor</a>
        <form method="post" data-conferma="Vuoi togliere «<?= h($riga['title']) ?>» dal registro delle pagine per zona? La pagina resta nel sito com'è; sparisce solo da questo elenco, dal widget Altre zone e dai dati per Google della zona." data-conferma-ok="Togli dal registro" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="togli"><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="torna" value="<?= h(http_build_query(array_filter(['servizio' => $filtro, 'cerca' => $cerca, 'pag' => $elenco['pagina'] > 1 ? $elenco['pagina'] : '']))) ?>"><button class="btn btn-danger btn-sm" type="submit">Togli dal registro</button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($elenco['pagine'] > 1): $link = fn(int $p) => 'zone.php?' . http_build_query(array_filter(['servizio' => $filtro, 'cerca' => $cerca, 'pag' => $p > 1 ? $p : ''])) . '#zn-elenco'; ?>
  <nav class="zn-pagine" id="zn-pagine" aria-label="Pagine dell'elenco delle pagine per zona">
    <?php if ($elenco['pagina'] > 1): ?><a class="btn btn-secondary btn-sm" href="<?= h($link(1)) ?>">Prima pagina</a><a class="btn btn-secondary btn-sm" href="<?= h($link($elenco['pagina'] - 1)) ?>" rel="prev">Pagina precedente</a><?php endif; ?>
    <span>Pagina <?= $elenco['pagina'] ?> di <?= $elenco['pagine'] ?></span>
    <?php if ($elenco['pagina'] < $elenco['pagine']): ?><a class="btn btn-secondary btn-sm" href="<?= h($link($elenco['pagina'] + 1)) ?>" rel="next">Pagina successiva</a><a class="btn btn-secondary btn-sm" href="<?= h($link($elenco['pagine'])) ?>">Ultima pagina</a><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php elseif ($cerca !== ''): ?>
  <div class="empty-state" id="zn-nessuna">Nessuna pagina per zona per «<?= h($cerca) ?>»<?= $filtro !== '' ? ' nel servizio «' . h($filtro) . '»' : '' ?>. Prova con una parola più corta (per esempio solo il nome del comune), oppure <a href="zone.php">mostra tutte</a>.</div>
  <?php else: ?>
  <div class="empty-state">Nessuna pagina per zona per questo servizio: scegli "Tutti i servizi" nella tendina.</div>
  <?php endif; ?>
  <p class="hint" style="margin-top:.8rem">Le bozze si scrivono dall'editor (anche un redattore può): cerca i tre riquadri con <code><?= h(ZONE_SEGNAPOSTO) ?></code>, scrivi il testo al loro posto e cancella la riga di aiuto, poi <b>Pubblica</b>. Le pagine pubblicate stanno nella sitemap come ogni pagina e si elencano a vicenda con il widget Altre zone (con tante zone, in ogni pagina il widget ne mostra quante decidi tu: <b>Quante mostrarne</b>). Per vedere i dati che Google legge: SEO e AEO, Schema e AEO, <b>Anteprima del JSON-LD di un indirizzo</b>.</p>
  <?php endif; ?>
  <p class="zn-cerca-fuori"><a href="zone.php?ritrova=1#zn-fuori" id="zn-cerca-fuori">Cerca le pagine per zona fuori dal registro</a> <span class="hint">(per esempio quelle tolte con "Togli dal registro", o rimaste fuori con le versioni fino alla 2.35.0)</span></p>
</div>

<form method="post" class="form-card" id="zn-modulo" autocomplete="off">
  <?= csrf_field() ?>
  <h3>Crea le pagine per zona</h3>
  <input type="hidden" name="azione" value="crea">
  <div class="zn-campi">
    <div class="form-group<?= $campo('servizio') ?>"><label for="zn-servizio">Servizio</label><input type="text" name="zone[servizio]" id="zn-servizio" value="<?= h($f['servizio']) ?>" maxlength="80" placeholder="per esempio: Idraulico" required<?= $aria('servizio') ?>><?= $err('servizio') ?><div class="hint">Come lo cercherebbe un cliente: "Idraulico", "Pulizie di uffici", "Dentista". Va nel titolo di ogni pagina.</div></div>
    <div class="form-group<?= $campo('servizio_pagina') ?>"><label for="zn-servizio-pagina">Pagina del servizio (facoltativa)</label><select name="zone[servizio_pagina]" id="zn-servizio-pagina"<?= $aria('servizio_pagina') ?>><option value="">Nessuna</option><?php foreach ($pagine as $k => $n): ?><option value="<?= h($k) ?>" <?= $f['servizio_pagina'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select><?= $err('servizio_pagina') ?><div class="hint">La pagina del sito che racconta il servizio: entra nelle briciole che Google legge (Home, il servizio, la zona).</div></div>
    <div class="form-group<?= $campo('zone') ?>"><label for="zn-zone">Zone</label><textarea name="zone[zone]" id="zn-zone" rows="6" placeholder="una per riga, per esempio:&#10;Bologna&#10;Casalecchio di Reno&#10;Provincia di Bologna" required<?= $aria('zone') ?>><?= h($f['zone']) ?></textarea><?= $err('zone') ?><div class="hint">I comuni o le province, uno per riga, al massimo <?= ZONE_GIRO_MAX ?> per giro; in tutto quante vuoi, con più giri. Sono già scritte le zone servite delle sedi. Una coppia servizio + zona che c'è già non si rifà.</div></div>
    <div class="form-group<?= $campo('sede') ?>"><label for="zn-sede">Sede di riferimento</label>
      <?php if (!$sedi): ?><select id="zn-sede" disabled><option>Nessuna sede ancora</option></select><div class="hint">Senza sedi le pagine nascono lo stesso, senza Dati dell'attività e Barra contatti; i dati per Google dicono l'organizzazione. Meglio prima una sede in <a href="sedi.php">SEO e AEO, Sedi</a>.</div>
      <?php elseif (count($sedi) === 1): ?><input type="hidden" name="zone[sede]" value="<?= h(array_key_first($sedi)) ?>"><select id="zn-sede" disabled><option><?= h(reset($sedi)['nome']) ?></option></select><div class="hint">L'unica sede: i widget Dati dell'attività e Barra contatti leggono da lei, e per Google è lei che fa il servizio in quella zona.</div>
      <?php else: ?><select name="zone[sede]" id="zn-sede"<?= $aria('sede') ?>><?php foreach ($opzioniSede as $k => $n): ?><option value="<?= h($k) ?>" <?= $f['sede'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select><?= $err('sede') ?><div class="hint">La sede che serve queste zone: i widget Dati dell'attività e Barra contatti leggono da lei, e per Google è lei che fa il servizio.</div><?php endif; ?>
    </div>
    <div class="zn-fascia">Titolo e descrizione delle pagine</div>
    <div class="form-group<?= $campo('titolo') ?>"><label for="zn-titolo">Modello del titolo</label><input type="text" name="zone[titolo]" id="zn-titolo" value="<?= h($f['titolo']) ?>" maxlength="120"<?= $aria('titolo') ?>><?= $err('titolo') ?><div class="hint"><code>%servizio%</code> e <code>%zona%</code> diventano i valori: "%servizio% a %zona%" dà "Idraulico a Bologna". Il titolo fa anche l'indirizzo (/idraulico-a-bologna/).</div></div>
    <div class="form-group<?= $campo('descrizione') ?>"><label for="zn-descrizione">Modello della descrizione per Google</label><textarea name="zone[descrizione]" id="zn-descrizione" rows="2" maxlength="300"<?= $aria('descrizione') ?>><?= h($f['descrizione']) ?></textarea><?= $err('descrizione') ?><div class="hint">Le due righe sotto il titolo nei risultati (fino a 160 caratteri) e il testo dell'apertura. Anche qui <code>%servizio%</code> e <code>%zona%</code>.</div></div>
    <div class="form-group zn-largo">
      <div class="hint">Ogni bozza nasce con questi tre riquadri, da scrivere a mano per ogni zona (è la parte che fa la differenza per Google e per chi legge):</div>
      <div class="zn-riquadri">
        <div><b>Cosa facciamo a &lt;zona&gt;</b>quali interventi fai lì, per chi, in quanto tempo</div>
        <div><b>Un caso vero a &lt;zona&gt;</b>un lavoro fatto davvero: il problema, cosa avete fatto, come è finita</div>
        <div><b>Come si arriva a &lt;zona&gt;</b>da quale sede partite, quanto ci mettete, giorni e orari</div>
      </div>
    </div>
  </div>
  <div style="margin-top:.6rem"><button class="btn btn-primary" type="submit">Crea le bozze</button></div>
</form>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
