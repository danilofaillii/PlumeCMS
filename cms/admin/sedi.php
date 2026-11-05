<?php
/**
 * SEO e AEO > Sedi (2.27.0): le sedi dell'attività e la SEO geografica. Motore in includes/sedi.php (dati nel gruppo
 * "sedi" della configurazione SEO), tipi in includes/sedi-tipi.php, mini mappa e ricerca delle coordinate in
 * admin/assets/sedi.js (la ricerca passa dal server: seo-api.php, azione sedi_cerca). Solo amministratore.
 *   sedi.php                 l'elenco e il modulo per una sede nuova
 *   sedi.php?modifica=<id>   l'elenco e il modulo con quella sede
 * Una pagina sua e non una scheda di seo.php: la pagina SEO ha già nove schede, e il modulo di una sede è lungo (venti
 * campi, la mini mappa). Dal menu SEO e AEO e dalla fila delle schede di seo.php ci si arriva lo stesso.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/sito-seo.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_once dirname(__DIR__) . '/includes/sedi.php';
require_admin();
$adminTitle = 'Sedi';
$errori = [];
$valori = null; // i valori del modulo dopo un salvataggio con errori
$modifica = preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', (string)($_GET['modifica'] ?? '')) ? (string)$_GET['modifica'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $autore = (string)(current_user()['username'] ?? '');
    try {
        switch ($azione) {
            case 'salva':
                $in = is_array($_POST['sede'] ?? null) ? $_POST['sede'] : [];
                $idCorrente = preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', (string)($_POST['id'] ?? '')) ? (string)$_POST['id'] : '';
                if ($idCorrente !== '' && !sedi_leggi($idCorrente)) throw new RuntimeException('La sede da modificare non c\'è più: ricarica la pagina.');
                $v = sedi_valida($in, $idCorrente);
                if ($v['errori']) {
                    $errori = $v['errori'];
                    $valori = $in; $modifica = $idCorrente;
                    flash('error', count($errori) . (count($errori) === 1 ? ' campo da sistemare: lo trovi segnato' : ' campi da sistemare: li trovi segnati') . ' in rosso qui sotto.');
                    break;
                }
                $sede = sedi_salva($v['sede']);
                $msg = $idCorrente === '' ? 'Sede «' . $sede['nome'] . '» salvata.' : 'Sede «' . $sede['nome'] . '» aggiornata.';
                if (!empty($_POST['crea_pagina']) && !sedi_pagina_riga($sede)) {
                    $pid = sedi_crea_pagina($sede, $autore);
                    $msg .= ' La sua pagina è pronta e pubblicata: /' . (string)(contenuti_leggi('pagine', $pid)['slug'] ?? '') . '/ (la cambi con l\'editor, da Pagine).';
                }
                flash('success', $msg . ' Ogni pagina del sito ora dichiara le sedi a Google.');
                header('Location: sedi.php'); exit;
            case 'elimina':
                $id = (string)($_POST['id'] ?? '');
                $s = sedi_leggi($id);
                if (!$s) throw new RuntimeException('Sede non trovata.');
                sedi_elimina($id);
                flash('success', 'Sede «' . $s['nome'] . '» eliminata.' . (sedi_pagina_riga($s) ? ' La sua pagina resta nel sito: se non la vuoi più, cestinala da Pagine.' : ''));
                header('Location: sedi.php'); exit;
            case 'principale':
                $id = (string)($_POST['id'] ?? '');
                if (!sedi_imposta_principale($id)) throw new RuntimeException('Sede non trovata.');
                flash('success', 'Ora «' . sedi_leggi($id)['nome'] . '» è la sede principale: la sua città va nei titoli (%citta%) e nei widget che leggono "la sede principale".');
                header('Location: sedi.php'); exit;
            case 'crea_pagina':
                $id = (string)($_POST['id'] ?? '');
                $s = sedi_leggi($id);
                if (!$s) throw new RuntimeException('Sede non trovata.');
                $pid = sedi_crea_pagina($s, $autore);
                flash('success', 'La pagina di «' . $s['nome'] . '» è pronta e pubblicata: /' . (string)(contenuti_leggi('pagine', $pid)['slug'] ?? '') . '/. La cambi con l\'editor, da Pagine.');
                header('Location: sedi.php'); exit;
            default:
                throw new RuntimeException('Azione sconosciuta.');
        }
    } catch (RuntimeException | InvalidArgumentException $e) {
        flash('error', $e->getMessage());
        header('Location: sedi.php'); exit;
    } catch (Throwable $e) {
        error_log('sedi.php: ' . $e->getMessage());
        flash('error', 'Qualcosa non ha funzionato: ' . $e->getMessage());
        header('Location: sedi.php'); exit;
    }
}

$sedi = sedi_tutte();
$corrente = $modifica !== '' ? sedi_leggi($modifica) : null;
if ($modifica !== '' && !$corrente && $valori === null) { flash('error', 'La sede da modificare non c\'è più.'); $modifica = ''; }
// I valori nel modulo: quelli appena scritti (con errori), quelli della sede da modificare, oppure quelli di partenza.
$f = $corrente ?? sedi_vuota();
if ($valori !== null) {
    $f = array_replace($f, array_intersect_key($valori, $f));
    $f['zona'] = (string)($valori['zona'] ?? '');
    $f['chiusure'] = (string)($valori['chiusure'] ?? '');
    $orari = is_array($valori['orari'] ?? null) ? $valori['orari'] : [];
    $f['orari'] = array_fill_keys(SEDI_GIORNI, ''); foreach (SEDI_GIORNI as $g) $f['orari'][$g] = (string)($orari[$g] ?? '');
    $f['principale'] = !empty($valori['principale']); $f['pagina'] = (int)($valori['pagina'] ?? 0);
} else {
    $f['zona'] = implode("\n", $f['zona']);
    $f['chiusure'] = sedi_chiusure_testo($f['chiusure']);
}
$nuova = $corrente === null;
$creaPaginaSpunta = $valori !== null ? !empty($_POST['crea_pagina']) : $nuova;
// Le pagine del sito, per collegare a una sede una pagina che c'è già.
$pagine = [];
foreach (db()->query('SELECT id, title, slug, status, cestino FROM ' . tbl('pages') . ' ORDER BY title')->fetchAll() as $r) if (empty($r['cestino'])) $pagine[(int)$r['id']] = $r['title'] . ' (/' . $r['slug'] . '/' . ($r['status'] === 'published' ? '' : ', bozza') . ')';
$csrf = csrf_token();
$campo = fn(string $k) => isset($errori[$k]) ? ' class="sd-sbagliato"' : '';
$aria = fn(string $k) => isset($errori[$k]) ? ' aria-invalid="true" aria-describedby="sd-err-' . $k . '"' : '';
$err = fn(string $k) => isset($errori[$k]) ? '<div class="errore-campo sd-errore" id="sd-err-' . $k . '" role="alert">' . h($errori[$k]) . '</div>' : '';
$giorni = ['lun' => 'Lunedì', 'mar' => 'Martedì', 'mer' => 'Mercoledì', 'gio' => 'Giovedì', 'ven' => 'Venerdì', 'sab' => 'Sabato', 'dom' => 'Domenica'];
$tipiOpz = sedi_tipi();

require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.sd-elenco .sd-principale{display:inline-block;margin-left:.4rem;padding:.1rem .5rem;border-radius:999px;font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;background:var(--sage-light);color:var(--successo-testo);border:1px solid var(--successo-bordo)}
.sd-elenco td .sd-tipo{display:block;font-size:.8rem;color:var(--text-muted)}
.sd-azioni{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
.sd-azioni form{display:inline}
.sd-campi{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 1.4rem}
.sd-campi .sd-largo{grid-column:1/-1}
.sd-sotto{font-size:.84rem;color:var(--text-muted);margin:0 0 .9rem;line-height:1.5}
.sd-sotto b{color:var(--text)}
.sd-fascia{grid-column:1/-1;margin:.6rem 0 .8rem;padding-top:.8rem;border-top:1px solid var(--border);font-size:.78rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted)}
.sd-campi .form-group.sd-sbagliato input,.sd-campi .form-group.sd-sbagliato select,.sd-campi .form-group.sd-sbagliato textarea{border:2px solid var(--errore)!important}
.sd-errore{margin-top:.35rem;font-size:.84rem;color:var(--errore-testo)}
.sd-coord{display:grid;grid-template-columns:1fr 1fr auto;gap:.6rem;align-items:end}
.sd-coord .form-group{margin:0}
#sd-geo-esito{margin:.5rem 0 0;font-size:.84rem;color:var(--text-muted)}
#sd-geo-esito.sd-no{color:var(--errore-testo)}
#sd-geo-esito.sd-ok{color:var(--successo-testo)}
.sd-mappa{position:relative;margin:.8rem 0 .5rem;max-width:560px}
.sd-mappa-tessere{position:relative;width:100%;aspect-ratio:1;display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(3,1fr);border:1px solid var(--bordo-campo);border-radius:8px;overflow:hidden;background:var(--bg);cursor:crosshair}
.sd-mappa-tessere img{width:100%;height:100%;display:block;pointer-events:none;user-select:none}
.sd-mappa-segnaposto{position:absolute;width:22px;height:22px;margin:-22px 0 0 -11px;pointer-events:none;color:var(--coral);filter:drop-shadow(0 1px 2px rgba(0,0,0,.5))}
.sd-mappa-zoom{position:absolute;top:8px;right:8px;display:flex;flex-direction:column;gap:4px}
.sd-mappa-zoom button{min-width:44px;min-height:44px;font-size:1.3rem;font-weight:700;line-height:1;padding:0}
.sd-mappa-credito{position:absolute;left:0;bottom:0;padding:.1rem .4rem;font-size:.68rem;background:var(--surface);color:var(--text-muted);border-radius:0 6px 0 6px}
.sd-orari{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 1rem;grid-column:1/-1}
.sd-orari .form-group{margin-bottom:.6rem}
.sd-campi .form-group textarea{min-height:0;height:auto;resize:vertical;font-family:var(--font);font-size:.9rem;line-height:1.5}
.sd-interruttori label{display:flex;gap:.6rem;align-items:flex-start;font-size:.9rem;padding:.45rem 0;border-top:1px solid var(--border)}
.sd-interruttori label input{margin-top:.25rem;flex:none}
.sd-interruttori small{display:block;color:var(--text-muted);margin-top:.15rem;line-height:1.45}
@media (max-width:760px){.sd-campi,.sd-orari{grid-template-columns:minmax(0,1fr)}.sd-coord{grid-template-columns:1fr 1fr}.sd-coord .sd-cerca{grid-column:1/-1}}
[hidden]{display:none!important}
</style>

<nav class="imp-schede" aria-label="Schede di SEO e AEO">
  <?php foreach (['panoramica' => 'Panoramica', 'motori' => 'Motori di ricerca', 'copertura' => 'Copertura', 'prestazioni' => 'Prestazioni', 'analisi' => 'Analisi e tag', 'globale' => 'Globale SEO', 'schema' => 'Schema e AEO', 'redirect' => 'Redirect e 404', 'log' => 'Registro'] as $k => $v): ?><a href="seo.php?scheda=<?= $k ?>"><?= h($v) ?></a><?php endforeach; ?><a href="sedi.php" class="attiva" aria-current="page">Sedi</a>
</nav>

<div class="form-card sd-elenco" id="sd-elenco">
  <h3>Le sedi dell'attività</h3>
  <p class="sd-sotto">Ogni sede che scrivi qui entra nei <b>dati per Google</b> di tutte le pagine (una LocalBusiness con indirizzo, coordinate, orari, telefono, zona servita) e la puoi mostrare sul sito con i widget <b>Sedi</b> e <b>Dati dell'attività</b>, oppure far leggere a Mappa, Orari di apertura e Barra contatti con la tendina <b>Dati dalla sede</b>. La <b>sede principale</b> dà la città ai titoli (<code>%citta%</code> in Globale SEO). Senza sedi il sito resta com'è.</p>
  <?php if (!$sedi): ?>
  <div class="empty-state">Ancora nessuna sede: scrivi la prima nel riquadro qui sotto, <b>Nuova sede</b>. Se l'attività ha una sede sola va benissimo: serve lo stesso a Google.</div>
  <?php else: ?>
  <table class="table" id="sd-tabella">
    <thead><tr><th>Sede</th><th>Indirizzo</th><th>Telefono</th><th>Pagina</th><th>Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($sedi as $id => $s): $riga = sedi_pagina_riga($s); $nomeTipo = sedi_tipo_nome($s['tipo'], $s['tipo_testo']); ?>
    <tr>
      <td><a href="sedi.php?modifica=<?= h($id) ?>"><strong><?= h($s['nome']) ?></strong></a><?= $s['principale'] ? '<span class="sd-principale">Principale</span>' : '' ?><?= $nomeTipo !== '' ? '<span class="sd-tipo">' . h($nomeTipo) . '</span>' : '' ?></td>
      <td><?= h(sedi_indirizzo_testo($s)) ?><?= sedi_ha_coordinate($s) ? '<span class="sd-tipo">' . h($s['lat'] . ', ' . $s['lng']) . '</span>' : '<span class="sd-tipo">senza coordinate</span>' ?></td>
      <td><?= h($s['telefono']) ?: '<span class="sd-tipo">nessuno</span>' ?></td>
      <td><?php if ($riga && empty($riga['cestino'])): ?><a href="<?= h(cms_sito_base() . contenuti_percorso('pagine', $riga)) ?>" target="_blank">/<?= h($riga['slug']) ?>/</a><?= contenuti_stato('pagine', $riga) !== 'pubblicato' ? ' <span class="sd-tipo">(' . h(CONTENUTI_STATI[contenuti_stato('pagine', $riga)]) . ')</span>' : '' ?> <a class="btn btn-secondary btn-sm" href="editor.php?tipo=pagine&amp;id=<?= (int)$riga['id'] ?>">Apri nell'editor</a>
        <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="crea_pagina"><input type="hidden" name="id" value="<?= h($id) ?>"><button class="btn btn-secondary btn-sm" type="submit">Crea la pagina</button></form><?php endif; ?></td>
      <td><div class="sd-azioni">
        <a class="btn btn-secondary btn-sm" href="sedi.php?modifica=<?= h($id) ?>#sd-modulo">Modifica</a>
        <?php if (!$s['principale']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="principale"><input type="hidden" name="id" value="<?= h($id) ?>"><button class="btn btn-secondary btn-sm" type="submit">Rendi principale</button></form><?php endif; ?>
        <form method="post" data-conferma="Vuoi eliminare la sede «<?= h($s['nome']) ?>»? Sparisce dai dati per Google e dai widget. La sua pagina resta nel sito: se non la vuoi più, cestinala da Pagine." data-conferma-ok="Elimina la sede" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= h($id) ?>"><button class="btn btn-danger btn-sm" type="submit">Elimina</button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint" style="margin-top:.8rem">Per vedere i dati che Google legge: SEO e AEO, Schema e AEO, <b>Anteprima del JSON-LD di un indirizzo</b> (nella pagina di una sede la sua scheda è la <code>mainEntity</code>).</p>
  <?php endif; ?>
</div>

<form method="post" class="form-card" id="sd-modulo" autocomplete="off">
  <?= csrf_field() ?>
  <h3><?= $nuova ? 'Nuova sede' : 'Modifica la sede «' . h($corrente['nome']) . '»' ?></h3>
  <input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="<?= h($modifica) ?>">
  <?php if (!$nuova): ?><p class="sd-sotto">Stai modificando una sede che c'è già. Per aggiungerne un'altra: <a href="sedi.php#sd-modulo">Nuova sede</a>.</p><?php endif; ?>
  <div class="sd-campi">
    <div class="form-group<?= $campo('nome') ?>"><label for="sd-nome">Nome della sede</label><input type="text" name="sede[nome]" id="sd-nome" value="<?= h($f['nome']) ?>" maxlength="80" placeholder="per esempio: Sede di Bologna" required<?= $aria('nome') ?>><?= $err('nome') ?><div class="hint">Compare sul sito e nei dati per Google. Con una sede sola va bene anche il nome dell'attività.</div></div>
    <div class="form-group<?= $campo('tipo') ?>"><label for="sd-tipo">Tipo di attività</label><select name="sede[tipo]" id="sd-tipo"<?= $aria('tipo') ?>><?php foreach ($tipiOpz as $k => $n): ?><option value="<?= h($k) ?>" <?= $f['tipo'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select><?= $err('tipo') ?><div class="hint">La categoria che Google riconosce. Se non c'è, l'ultima voce: <b>Altro, lo scrivo io</b>.</div></div>
    <div class="form-group<?= $campo('tipo_testo') ?>" id="sd-tipo-testo-riga"<?= $f['tipo'] === SEDI_TIPO_ALTRO ? '' : ' hidden' ?>><label for="sd-tipo-testo">Che attività è</label><input type="text" name="sede[tipo_testo]" id="sd-tipo-testo" value="<?= h($f['tipo_testo']) ?>" maxlength="<?= SEDI_TIPO_TESTO_MAX ?>" placeholder="per esempio: centro di meditazione"<?= $aria('tipo_testo') ?>><?= $err('tipo_testo') ?><div class="hint">Si salva con la prima lettera maiuscola. Sul sito esce così; a Google arriva come attività generica (accetta solo le sue categorie).</div></div>
    <div class="form-group"><label for="sd-descrizione">Descrizione breve</label><textarea name="sede[descrizione]" id="sd-descrizione" rows="2" maxlength="600" placeholder="per esempio: La sede storica, in centro, a due passi dalla stazione."><?= h($f['descrizione']) ?></textarea><div class="hint">Due righe. Vanno nella pagina della sede e nei dati per Google.</div></div>

    <div class="sd-fascia">Dove sta</div>
    <div class="form-group"><label for="sd-via">Via e numero</label><input type="text" name="sede[via]" id="sd-via" value="<?= h($f['via']) ?>" maxlength="120" placeholder="per esempio: Via Rizzoli 1"></div>
    <div class="form-group"><label for="sd-cap">CAP</label><input type="text" name="sede[cap]" id="sd-cap" value="<?= h($f['cap']) ?>" maxlength="10" placeholder="per esempio: 40125"></div>
    <div class="form-group<?= $campo('citta') ?>"><label for="sd-citta">Città</label><input type="text" name="sede[citta]" id="sd-citta" value="<?= h($f['citta']) ?>" maxlength="80" placeholder="per esempio: Bologna" required<?= $aria('citta') ?>><?= $err('citta') ?></div>
    <div class="form-group"><label for="sd-provincia">Provincia</label><input type="text" name="sede[provincia]" id="sd-provincia" value="<?= h($f['provincia']) ?>" maxlength="40" placeholder="per esempio: BO"></div>
    <div class="form-group"><label for="sd-paese">Paese</label><input type="text" name="sede[paese]" id="sd-paese" value="<?= h($f['paese']) ?>" maxlength="60"></div>
    <div class="form-group sd-largo<?= $campo('lat') ?>">
      <label for="sd-lat">Coordinate</label>
      <div class="sd-coord">
        <div class="form-group"><label for="sd-lat" class="sr-solo">Latitudine</label><input type="text" name="sede[lat]" id="sd-lat" value="<?= h($f['lat']) ?>" inputmode="decimal" placeholder="latitudine, per esempio 44.4949" aria-label="Latitudine"<?= $aria('lat') ?>></div>
        <div class="form-group"><label for="sd-lng" class="sr-solo">Longitudine</label><input type="text" name="sede[lng]" id="sd-lng" value="<?= h($f['lng']) ?>" inputmode="decimal" placeholder="longitudine, per esempio 11.3426" aria-label="Longitudine"></div>
        <button type="button" class="btn btn-secondary sd-cerca" id="sd-cerca">Cerca sulla mappa</button>
      </div>
      <?= $err('lat') ?>
      <p id="sd-geo-esito" role="status"></p>
      <div class="hint">Premi <b>Cerca sulla mappa</b>: il sito chiede le coordinate dell'indirizzo scritto sopra al servizio delle mappe (dal server, con l'indirizzo e basta). Poi puoi spostare il segnaposto con un clic sulla mini mappa, o scrivere i numeri a mano. Senza coordinate Google usa l'indirizzo, e la Mappa dalla sede usa l'indirizzo.</div>
      <div class="sd-mappa" id="sd-mappa" hidden>
        <div class="sd-mappa-tessere" id="sd-tessere" role="img" aria-label="Mappa intorno alle coordinate: un clic sposta il segnaposto"></div>
        <svg class="sd-mappa-segnaposto" id="sd-segnaposto" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
        <div class="sd-mappa-zoom"><button type="button" class="btn btn-secondary" data-sd-zoom="1" aria-label="Avvicina la mappa">+</button><button type="button" class="btn btn-secondary" data-sd-zoom="-1" aria-label="Allontana la mappa">&minus;</button></div>
        <span class="sd-mappa-credito">Mappa di OpenStreetMap</span>
      </div>
    </div>

    <div class="sd-fascia">Contatti e prezzi</div>
    <div class="form-group"><label for="sd-telefono">Telefono</label><input type="text" name="sede[telefono]" id="sd-telefono" value="<?= h($f['telefono']) ?>" maxlength="40" placeholder="per esempio: +39 051 123456"><div class="hint">Con il prefisso: sul sito si può chiamare con un tocco.</div></div>
    <div class="form-group<?= $campo('email') ?>"><label for="sd-email">Email</label><input type="text" name="sede[email]" id="sd-email" value="<?= h($f['email']) ?>" maxlength="120" placeholder="per esempio: bologna@esempio.it"<?= $aria('email') ?>><?= $err('email') ?></div>
    <div class="form-group"><label for="sd-prezzo">Fascia di prezzo</label><select name="sede[prezzo]" id="sd-prezzo"><?php foreach (SEDI_PREZZI as $pz): ?><option value="<?= h($pz) ?>" <?= $f['prezzo'] === $pz ? 'selected' : '' ?>><?= $pz === '' ? 'Non dirla' : h($pz) ?></option><?php endforeach; ?></select><div class="hint">Da &euro; (economico) a &euro;&euro;&euro;&euro;: Google la mostra nella scheda.</div></div>
    <div class="form-group<?= $campo('zona') ?>"><label for="sd-zona">Zona servita</label><textarea name="sede[zona]" id="sd-zona" rows="3" placeholder="una per riga, per esempio:&#10;Bologna&#10;Casalecchio di Reno&#10;Provincia di Bologna"<?= $aria('zona') ?>><?= h($f['zona']) ?></textarea><?= $err('zona') ?><div class="hint">I comuni o le province dove lavori, uno per riga (al massimo <?= SEDI_ZONA_MAX ?>). Vanno nei dati per Google della sede e dei servizi.</div></div>

    <div class="sd-fascia">Orari di apertura</div>
    <div class="sd-orari">
      <?php foreach ($giorni as $g => $n): ?><div class="form-group"><label for="sd-orario-<?= $g ?>"><?= $n ?></label><input type="text" name="sede[orari][<?= $g ?>]" id="sd-orario-<?= $g ?>" value="<?= h($f['orari'][$g]) ?>" maxlength="80" placeholder="<?= $g === 'dom' ? 'Chiuso' : 'per esempio: 9:00-13:00, 15:00-19:00' ?>"></div><?php endforeach; ?>
      <div class="form-group"><label for="sd-chiusure">Ferie e chiusure</label><textarea name="sede[chiusure]" id="sd-chiusure" rows="2" placeholder="una per riga, per esempio:&#10;2026-12-24 - 2026-12-26: Natale&#10;2027-01-06"><?= h($f['chiusure']) ?></textarea></div>
    </div>
    <div class="form-group sd-largo"><div class="hint">Lo stesso formato del widget Orari di apertura: "9:00-13:00, 15:00-19:00", "9-13", "24 ore"; Chiuso (o vuoto) per un giorno di chiusura. Le chiusure: "AAAA-MM-GG", oppure "AAAA-MM-GG - AAAA-MM-GG: motivo". I widget Orari, Sedi e Dati dell'attività li leggono da qui e dicono "Aperto ora" o "Chiuso".</div></div>

    <div class="sd-fascia">Google, foto e pagina</div>
    <div class="form-group<?= $campo('google_scheda') ?>"><label for="sd-google-scheda">Link alla scheda Google</label><input type="text" name="sede[google_scheda]" id="sd-google-scheda" value="<?= h($f['google_scheda']) ?>" maxlength="500" placeholder="per esempio: https://maps.app.goo.gl/..."<?= $aria('google_scheda') ?>><?= $err('google_scheda') ?><div class="hint">Dal Profilo dell'attività su Google: Condividi, copia il link. Solo indirizzi https di google.com, g.page o maps.app.goo.gl.</div></div>
    <div class="form-group<?= $campo('google_recensione') ?>"><label for="sd-google-recensione">Link "lascia una recensione"</label><input type="text" name="sede[google_recensione]" id="sd-google-recensione" value="<?= h($f['google_recensione']) ?>" maxlength="500" placeholder="per esempio: https://g.page/r/.../review"<?= $aria('google_recensione') ?>><?= $err('google_recensione') ?><div class="hint">Dal Profilo dell'attività: Chiedi recensioni, copia il link. I widget mostrano il pulsante solo se c'è. Le recensioni restano su Google: il sito non mostra stelle (Google non le accetta per le recensioni che un'attività pubblica di sé).</div></div>
    <div class="form-group"><label for="sd-foto">Foto della sede</label><input type="text" name="sede[foto]" id="sd-foto" value="<?= h($f['foto']) ?>" placeholder="/uploads/..." data-immagine maxlength="500"><div class="hint">Nei widget e nei dati per Google.</div></div>
    <div class="form-group"><label for="sd-pagina">Pagina della sede</label><select name="sede[pagina]" id="sd-pagina"><option value="0">Nessuna pagina</option><?php foreach ($pagine as $pid => $pn): ?><option value="<?= $pid ?>" <?= (int)$f['pagina'] === $pid ? 'selected' : '' ?>><?= h($pn) ?></option><?php endforeach; ?></select><div class="hint">La pagina che racconta questa sede: Google la lega alla sede (<code>url</code> e <code>mainEntity</code>) e il widget Sedi ci mette il pulsante "La sede".</div></div>
    <?php if ($nuova || !sedi_pagina_riga($corrente)): ?>
    <div class="form-group sd-largo sd-interruttori"><label><input type="checkbox" name="crea_pagina" value="1" <?= $creaPaginaSpunta ? 'checked' : '' ?>><span>Crea la pagina della sede<small>Una pagina vera, pubblicata, con l'apertura, i dati dell'attività, la mappa, gli orari, la descrizione e la barra dei contatti, tutti letti da questa sede. Titolo e indirizzo dal nome (per esempio /sede-di-bologna/). La cambi con l'editor quando vuoi.</small></span></label></div>
    <?php endif; ?>
    <div class="form-group sd-largo sd-interruttori"><label><input type="checkbox" name="sede[principale]" value="1" <?= $f['principale'] || !$sedi ? 'checked' : '' ?> <?= !$sedi ? 'disabled' : '' ?>><span>Sede principale<small>Una sola: dà la città ai titoli (<code>%citta%</code>) e ai widget che leggono "la sede principale".<?= !$sedi ? ' La prima sede è principale per forza.' : '' ?></small></span></label></div>
  </div>
  <div style="margin-top:.6rem"><button class="btn btn-primary" type="submit"><?= $nuova ? 'Salva la sede' : 'Salva le modifiche' ?></button> <?php if (!$nuova): ?><a class="btn btn-secondary" href="sedi.php">Annulla</a><?php endif; ?></div>
</form>
<script src="<?= pannello_file('assets/sedi.js') ?>" defer></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
