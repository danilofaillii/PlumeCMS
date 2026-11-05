<?php
/**
 * SEO e AEO > Zone (2.28.0): le pagine per zona, la seconda fase della SEO geografica. Motore in includes/zone.php (registro
 * nel gruppo "zone" della configurazione SEO). Solo amministratore.
 *   zone.php                      il registro delle pagine per zona e il generatore delle bozze
 *   zone.php?servizio=Idraulico   il registro filtrato per servizio
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
                $r = zone_crea_bozze($v['valori'], $autore);
                $n = count($r['create']);
                if ($n > 0) {
                    $nomi = array_map(fn($c) => '«' . $c['titolo'] . '»', $r['create']);
                    flash('success', ($n === 1 ? '1 bozza creata: ' : $n . ' bozze create: ') . implode(', ', $nomi) . '. Ognuna ha tre riquadri con il segnaposto ' . ZONE_SEGNAPOSTO . ': aprila nell\'editor, scrivi il testo al posto dei segnaposto e poi pubblica. Finché un segnaposto resta, la pagina non si pubblica.');
                }
                if ($r['esistenti']) flash('info', 'C\'è già: ' . implode(', ', array_map(fn($e) => '«' . $e['titolo'] . '»', $r['esistenti'])) . ($n === 0 ? '. Nessuna bozza nuova.' : '.'));
                if ($r['errore'] !== '') flash('error', $r['errore']);
                header('Location: zone.php?servizio=' . rawurlencode($v['valori']['servizio'])); exit;
            case 'togli':
                $id = (int)($_POST['id'] ?? 0);
                $z = zone_di_pagina($id);
                if (!$z) throw new RuntimeException('Questa pagina non è più nel registro.');
                zone_elimina($id);
                flash('success', '«' . $z['riga']['title'] . '» tolta dal registro delle pagine per zona. La pagina resta nel sito com\'è (' . (zone_pubblicata($z) ? 'pubblicata' : 'in bozza') . '): se non la vuoi più, cestinala da Pagine.');
                header('Location: zone.php' . ($filtro !== '' ? '?servizio=' . rawurlencode($filtro) : '')); exit;
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

zone_pulisci(); // le pagine sparite dal cestino escono dal registro
$tutte = zone_tutte();
$servizi = zone_servizi();
if ($filtro !== '' && !in_array(mb_strtolower($filtro), array_map('mb_strtolower', $servizi), true)) $filtro = '';
$mostrate = $filtro === '' ? $tutte : array_filter($tutte, fn($z) => mb_strtolower($z['servizio']) === mb_strtolower($filtro));
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
@media (max-width:760px){.zn-campi{grid-template-columns:minmax(0,1fr)}.zn-riquadri{grid-template-columns:minmax(0,1fr)}}
[hidden]{display:none!important}
</style>

<nav class="imp-schede" aria-label="Schede di SEO e AEO">
  <?php foreach (['panoramica' => 'Panoramica', 'motori' => 'Motori di ricerca', 'copertura' => 'Copertura', 'prestazioni' => 'Prestazioni', 'analisi' => 'Analisi e tag', 'globale' => 'Globale SEO', 'schema' => 'Schema e AEO', 'redirect' => 'Redirect e 404', 'log' => 'Registro'] as $k => $v): ?><a href="seo.php?scheda=<?= $k ?>"><?= h($v) ?></a><?php endforeach; ?><a href="sedi.php">Sedi</a><a href="zone.php" class="attiva" aria-current="page">Zone</a>
</nav>

<div class="form-card zn-elenco" id="zn-elenco">
  <h3>Le pagine per zona</h3>
  <p class="zn-sotto">Una pagina per ogni coppia <b>servizio + zona</b> ("Idraulico a Casalecchio di Reno"): è così che chi cerca un servizio nel suo comune trova te. Qui sotto <b>Crea le bozze</b> le fa nascere con l'apertura, tre riquadri da scrivere, il widget <b>Altre zone</b> e i dati della sede; nessuna si pubblica da sola. Tu (o un redattore) apri ogni bozza nell'editor, scrivi al posto dei segnaposto <code><?= h(ZONE_SEGNAPOSTO) ?></code> e pubblichi: finché un segnaposto resta, la pagina resta bozza. Così niente pagine vuote tutte uguali, che Google ignora. Nella pagina pubblicata i dati per Google dicono il servizio, la zona servita e la sede che lo fa.</p>
  <?php if (!$tutte): ?>
  <div class="empty-state">Ancora nessuna pagina per zona: scrivi il servizio e le zone nel riquadro qui sotto, <b>Crea le pagine per zona</b>, e premi <b>Crea le bozze</b>.</div>
  <?php else: ?>
  <?php if (count($servizi) > 1): ?>
  <form method="get" class="zn-filtro" id="zn-filtro">
    <label for="zn-filtro-servizio">Servizio</label>
    <select name="servizio" id="zn-filtro-servizio" data-invia-al-cambio><option value="">Tutti i servizi (<?= count($tutte) ?>)</option><?php foreach ($servizi as $sv): ?><option value="<?= h($sv) ?>" <?= mb_strtolower($sv) === mb_strtolower($filtro) ? 'selected' : '' ?>><?= h($sv) ?></option><?php endforeach; ?></select>
    <noscript><button class="btn btn-secondary btn-sm" type="submit">Filtra</button></noscript>
  </form>
  <?php endif; ?>
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
        <form method="post" data-conferma="Vuoi togliere «<?= h($riga['title']) ?>» dal registro delle pagine per zona? La pagina resta nel sito com'è; sparisce solo da questo elenco, dal widget Altre zone e dai dati per Google della zona." data-conferma-ok="Togli dal registro" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="togli"><input type="hidden" name="id" value="<?= (int)$id ?>"><button class="btn btn-danger btn-sm" type="submit">Togli dal registro</button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$mostrate): ?><div class="empty-state">Nessuna pagina per zona per questo servizio: scegli "Tutti i servizi" nella tendina.</div><?php endif; ?>
  <p class="hint" style="margin-top:.8rem">Le bozze si scrivono dall'editor (anche un redattore può): cerca i tre riquadri con <code><?= h(ZONE_SEGNAPOSTO) ?></code>, scrivi il testo al loro posto e cancella la riga di aiuto, poi <b>Pubblica</b>. Le pagine pubblicate stanno nella sitemap come ogni pagina e si elencano a vicenda con il widget Altre zone. Per vedere i dati che Google legge: SEO e AEO, Schema e AEO, <b>Anteprima del JSON-LD di un indirizzo</b>.</p>
  <?php endif; ?>
</div>

<form method="post" class="form-card" id="zn-modulo" autocomplete="off">
  <?= csrf_field() ?>
  <h3>Crea le pagine per zona</h3>
  <input type="hidden" name="azione" value="crea">
  <div class="zn-campi">
    <div class="form-group<?= $campo('servizio') ?>"><label for="zn-servizio">Servizio</label><input type="text" name="zone[servizio]" id="zn-servizio" value="<?= h($f['servizio']) ?>" maxlength="80" placeholder="per esempio: Idraulico" required<?= $aria('servizio') ?>><?= $err('servizio') ?><div class="hint">Come lo cercherebbe un cliente: "Idraulico", "Pulizie di uffici", "Dentista". Va nel titolo di ogni pagina.</div></div>
    <div class="form-group<?= $campo('servizio_pagina') ?>"><label for="zn-servizio-pagina">Pagina del servizio (facoltativa)</label><select name="zone[servizio_pagina]" id="zn-servizio-pagina"<?= $aria('servizio_pagina') ?>><option value="">Nessuna</option><?php foreach ($pagine as $k => $n): ?><option value="<?= h($k) ?>" <?= $f['servizio_pagina'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select><?= $err('servizio_pagina') ?><div class="hint">La pagina del sito che racconta il servizio: entra nelle briciole che Google legge (Home, il servizio, la zona).</div></div>
    <div class="form-group<?= $campo('zone') ?>"><label for="zn-zone">Zone</label><textarea name="zone[zone]" id="zn-zone" rows="6" placeholder="una per riga, per esempio:&#10;Bologna&#10;Casalecchio di Reno&#10;Provincia di Bologna" required<?= $aria('zone') ?>><?= h($f['zone']) ?></textarea><?= $err('zone') ?><div class="hint">I comuni o le province, uno per riga, al massimo <?= ZONE_GIRO_MAX ?> per giro (<?= ZONE_MAX ?> pagine per zona in tutto). Sono già scritte le zone servite delle sedi. Una coppia servizio + zona che c'è già non si rifà.</div></div>
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
