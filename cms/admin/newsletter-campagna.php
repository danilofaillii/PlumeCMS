<?php
/**
 * Il riepilogo di una campagna (2.11.0, fase 4 del piano Brevo): sezioni Mittente, Destinatari, Oggetto, Design,
 * Impostazioni aggiuntive; ognuna si apre, si salva da sola e dice se è a posto. Poi Anteprima e prova (con i dati di un
 * contatto scelto, alla lista dei test) e Programma (Invia subito, o giorno e ora nel fuso del sito). Le sezioni non a
 * posto bloccano l'invio e dicono cosa manca. Motore in includes/newsletter-campagne.php; il testo dell'editor semplice
 * si salva com'è e diventa HTML da email all'invio (newsletter_corpo_email()).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/../includes/newsletter.php';
require_once __DIR__ . '/../includes/newsletter-importa.php'; // 2.9.0: la lista dei test
require_once __DIR__ . '/../includes/newsletter-campagne.php'; // 2.11.0
require_once __DIR__ . '/../includes/newsletter-design.php'; // 2.12.0: editor a blocchi e modelli
require_once __DIR__ . '/../includes/newsletter-ab.php'; // 2.13.0: il test A/B
require_admin(); // sicurezza (ruoli): solo amministratore
newsletter_schema();
newsletter_campagne_schema();
nd_schema();
$STRADE = ['blocchi' => 'Editor a blocchi', 'semplice' => 'Editor semplice', 'html' => 'Codice HTML'];

$id = (int)($_GET['id'] ?? 0);
$c = newsletter_campagna($id);
if (!$c) { header('Location: newsletter.php'); exit; }
$modificabile = newsletter_campagna_modificabile($c);
$SEZIONI = ['mittente' => 'Mittente', 'destinatari' => 'Destinatari', 'oggetto' => 'Oggetto', 'design' => 'Design', 'impostazioni' => 'Impostazioni aggiuntive'];
// 2.13.0: il test A/B. ?versione=b mostra la versione B nell'anteprima e, nel test sul contenuto, nella sezione Design.
$isAb = $c['tipo'] === 'ab';
$ab = $isAb ? nl_ab($id) : null;
if ($isAb) $SEZIONI = array_slice($SEZIONI, 0, 2, true) + ['ab' => 'Test A/B'] + array_slice($SEZIONI, 2, null, true);
$ver = $isAb && ($_GET['versione'] ?? $_POST['versione'] ?? '') === 'b' ? 'b' : 'a';
$verB = $ver === 'b' && $ab['cosa'] === 'design'; // la sezione Design lavora sulla versione B
$qv = $ver === 'b' ? '&versione=b' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $azione = (string)($_POST['azione'] ?? '');
    $vai = '';
    switch ($azione) {
        case 'sezione':
            $sez = (string)($_POST['sez'] ?? '');
            if (!isset($SEZIONI[$sez]) && $sez !== 'nome') break;
            $e = newsletter_campagna_salva_sezione($id, $sez, $_POST);
            if ($e !== '') { flash('error', $e); $vai = $qv . '&apri=' . $sez . '#sez-' . $sez; break; }
            flash('success', $sez === 'nome' ? 'Nome della campagna salvato.' : ($SEZIONI[$sez] . ($sez === 'design' && $verB ? ' della versione B' : '') . ': salvato.'));
            $vai = $sez === 'nome' ? '' : ($sez === 'design' ? $qv : '') . '#sez-' . $sez;
            if ($sez === 'design' && isset($_POST['cambia_editor'])) {
                // 2.12.0: scegliendo l'editor a blocchi si apre subito l'editor.
                if (($_POST['editor'] ?? '') === 'blocchi') { header('Location: newsletter-design.php?campagna=' . $id . ($verB ? '&versione=b' : '')); exit; }
                $vai = $qv . '&apri=design#sez-design';
            }
            if ($sez === 'ab' && ($_POST['cosa'] ?? '') === 'design') $vai = '&apri=ab#sez-ab';
            break;
        case 'usa_modello':
            $m = nd_modello((int)($_POST['modello_id'] ?? 0));
            $e = $m && (int)$m['attivo'] && $m['tag'] !== 'optin' ? ($verB ? nl_ab_da_modello($id, $m) : nd_campagna_da_modello($id, $m)) : 'Scegli un modello attivo.';
            if ($e !== '') { flash('error', $e); $vai = $qv . '&apri=design#sez-design'; break; }
            flash('success', 'Design ' . ($verB ? 'della versione B ' : '') . 'preso dal modello «' . newsletter_4byte_fuori((string)$m['nome']) . '».');
            if ($m['editor'] === 'blocchi') { header('Location: newsletter-design.php?campagna=' . $id . ($verB ? '&versione=b' : '')); exit; }
            $vai = $qv . '&apri=design#sez-design';
            break;
        case 'prova':
            // 2.9.0: la prova va ai contatti spuntati della lista dei test, ognuno con i suoi dati (senza tracciamento: le
            // prove non contano nelle statistiche), e se serve a un altro indirizzo scritto a mano.
            $a = trim((string)($_POST['prova_a'] ?? ''));
            $test = [];
            foreach (newsletter_lista_test() as $t) if (in_array((int)$t['id'], array_map('intval', (array)($_POST['prova_test'] ?? [])), true)) $test[] = $t;
            $vai = '#sez-prova';
            if (!$test && !filter_var($a, FILTER_VALIDATE_EMAIL)) { flash('error', $a === '' ? 'Spunta almeno un indirizzo della lista dei test, oppure scrivi l\'indirizzo a cui mandare la prova.' : 'L\'indirizzo per la prova non è valido: scrivilo per intero (per esempio nome@esempio.it).'); $vai = '&apri=prova#sez-prova'; break; }
            $spedite = []; $nonPartite = [];
            // 2.13.0: nel test A/B la prova manda le due versioni.
            $versioni = $isAb ? ['[PROVA A] ' => nl_ab_versione($c, 'a'), '[PROVA B] ' => nl_ab_versione($c, 'b')] : ['[PROVA] ' => $c];
            foreach ($test as $t) foreach ($versioni as $pre => $cx) {
                [$ogg, $html, $intest] = newsletter_email($cx, ['id' => (int)$t['id'], 'nome' => (string)$t['nome'], 'email' => (string)$t['email']], []);
                posta_invia((string)$t['email'], $pre . $ogg, $html, $intest, 'prova') ? $spedite[] = $t['email'] : $nonPartite[] = $t['email'];
            }
            $spedite = array_values(array_unique($spedite)); $nonPartite = array_values(array_unique($nonPartite));
            if (filter_var($a, FILTER_VALIDATE_EMAIL) && !in_array(mb_strtolower($a), array_map('mb_strtolower', array_column($test, 'email')), true)) {
                $cid = newsletter_id_da_email(mb_strtolower($a));
                $chi = $cid ? newsletter_contatto($cid) : null;
                $partita = true;
                foreach ($versioni as $pre => $cx) {
                    [$ogg, $html, $intest] = newsletter_email($cx, $chi ? ['id' => $cid, 'nome' => (string)$chi['nome'], 'email' => $a] : ['id' => 0, 'nome' => trim((string)($_POST['prova_nome'] ?? '')) ?: 'Giulia', 'email' => $a], []);
                    $partita = posta_invia($a, $pre . $ogg, $html, $intest, 'prova') && $partita;
                }
                $partita ? $spedite[] = $a : $nonPartite[] = $a;
                if (!empty($_POST['prova_aggiungi'])) {
                    $e = $cid ? newsletter_lista_test_aggiungi($cid) : "L'indirizzo $a non è un contatto: per metterlo nella lista dei test crealo prima in Contatti.";
                    if ($e !== '') flash('error', $e);
                }
            }
            if ($spedite) flash('success', ($isAb ? 'Prova delle due versioni spedita a ' : 'Prova spedita a ') . implode(', ', $spedite) . '.');
            if ($nonPartite) flash('error', 'Prova non partita per ' . implode(', ', $nonPartite) . ': ' . posta_errore());
            break;
        case 'invia':
            if (!$modificabile) { flash('error', 'Questa campagna è già partita.'); break; }
            $manca = newsletter_campagna_manca($c);
            if ($manca) { flash('error', 'Prima di inviare: ' . implode(' ', $manca)); $vai = '#nlr-invio'; break; }
            $n = newsletter_campagna_conta($c);
            if (($_POST['quando'] ?? '') === 'programma') {
                $quando = strtotime((string)($_POST['invio_at'] ?? ''));
                if (!$quando) { flash('error', 'Scegli giorno e ora dell\'invio.'); $vai = '#nlr-invio'; break; }
                if ($quando < time() - 60) { flash('error', 'Giorno e ora sono già passati: scegli un momento che deve ancora venire, oppure «Invia subito».'); $vai = '#nlr-invio'; break; }
                $quando = max($quando, time());
                db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'programmata', invio_at = ?, aggiornato = NOW() WHERE id = ?")->execute([date('Y-m-d H:i:00', $quando), $id]);
                flash('success', 'Campagna programmata per il ' . date('d/m/Y \a\l\l\e H:i', $quando) . " (ora di " . date_default_timezone_get() . "), verso $n contatti (contati adesso). Parte con il primo giro del cron dopo quell'ora, entro 5 minuti.");
            } else {
                db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'programmata', invio_at = NOW(), aggiornato = NOW() WHERE id = ?")->execute([$id]);
                flash('success', "Campagna in partenza verso $n contatti: il cron la spedisce a giri, entro 5 minuti e nel limite orario.");
            }
            break;
        case 'togli_programma':
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'bozza', invio_at = NULL WHERE id = ? AND stato = 'programmata'")->execute([$id]);
            flash('success', 'Programmazione tolta: la campagna è di nuovo una bozza.');
            break;
        case 'annulla':
            newsletter_campagna_annulla($id) ? flash('success', 'Campagna annullata: le email non ancora partite non partono più.') : flash('error', 'Si annulla solo una campagna programmata, in invio o sospesa.');
            break;
        case 'sospendi':
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'sospesa' WHERE id = ? AND stato = 'in_invio'")->execute([$id]);
            flash('success', 'Invio sospeso. Le email già partite restano partite.');
            break;
        case 'riprendi':
            db()->prepare('UPDATE ' . tbl('newsletter_campagne') . " SET stato = 'in_invio' WHERE id = ? AND stato = 'sospesa'")->execute([$id]);
            flash('success', 'Invio ripreso.');
            break;
    }
    header('Location: newsletter-campagna.php?id=' . $id . $vai);
    exit;
}

$sez = newsletter_campagna_sezioni($c);
$manca = $modificabile ? newsletter_campagna_manca($c) : [];
$apri = (string)($_GET['apri'] ?? '');
// L'anteprima con i dati di un contatto (scelto per email), altrimenti "Giulia".
$antEmail = mb_strtolower(trim((string)($_GET['contatto'] ?? '')));
$antChi = $antEmail !== '' ? newsletter_contatto(newsletter_id_da_email($antEmail)) : null;
$antIscr = $antChi ? ['id' => (int)$antChi['id'], 'nome' => (string)$antChi['nome'], 'email' => (string)$antChi['email']] : ['id' => 0, 'nome' => 'Giulia', 'email' => ''];
$cv = $isAb ? nl_ab_versione($c, $ver) : $c; // la versione mostrata nell'anteprima
$cd = $verB ? $cv : $c; // la versione mostrata nella sezione Design
if ($verB && function_exists('nl_camp_design_stato')) { [$okB, $mancaB] = nl_camp_design_stato($cd); $sez['design'] = ['ok' => $okB, 'manca' => $mancaB]; } // il segno della versione che si guarda
[$oggProva, $htmlProva, $intProva] = newsletter_email($cv, $antIscr, []);
$testoProva = posta_testo_da_html($verB || $ver === 'a' ? $htmlProva : newsletter_email($cd, $antIscr, [])[1]);
$liste = newsletter_liste();
$segmenti = function_exists('newsletter_segmenti') ? newsletter_segmenti() : [];
$sceltiL = nl_ids((string)$c['liste']); $sceltiS = nl_ids((string)$c['segmenti']);
$esclL = nl_ids((string)$c['escludi_liste']); $esclS = nl_ids((string)$c['escludi_segmenti']);
$listaTest = newsletter_lista_test();
$mittentiScelta = consegna_mittenti_scelta();
[$mittPre, $nomePre] = posta_mittente();
$nomeC = newsletter_4byte_fuori((string)($c['nome'] !== '' ? $c['nome'] : $c['oggetto'])) ?: 'Campagna senza nome';
$madre = (int)$c['rinvio_di'] ? newsletter_campagna((int)$c['rinvio_di']) : ((int)$c['padre_id'] ? newsletter_campagna((int)$c['padre_id']) : null);
$dis = $modificabile ? '' : ' disabled';
$modelliAttivi = array_values(array_filter(nd_modelli(true), fn($m) => $m['tag'] !== 'optin')); // l'email di conferma non è una campagna
$modelloUsato = (int)($cd['modello_id'] ?? 0) ? nd_modello((int)$cd['modello_id']) : null;
$adminTitle = 'Campagna: ' . $nomeC;

/** L'intestazione di una sezione: titolo, segno di fatto o da completare, riassunto. */
$testa = function (string $k, string $titolo, string $riassunto) use ($sez, $modificabile) {
    $ok = $sez[$k]['ok'] ?? true;
    // Una campagna partita non si modifica: niente segni di "a posto" o "da completare".
    if (!$modificabile) return '<summary><h3>' . h($titolo) . '</h3><span class="nlr-riassunto">' . h($riassunto) . '</span></summary>';
    $segno = $ok ? '<span class="nlr-stato nlr-ok"><span aria-hidden="true">&#10003;</span> A posto</span>' : '<span class="nlr-stato nlr-manca"><span aria-hidden="true">!</span> Da completare</span>';
    return '<summary><h3>' . h($titolo) . '</h3> ' . $segno . '<span class="nlr-riassunto">' . ($riassunto !== '' ? h($riassunto) : '') . '</span></summary>'
        . (!$ok && $modificabile ? '<p class="nlr-manca-testo" role="status">' . h($sez[$k]['manca']) . '</p>' : '');
};
$aperta = fn(string $k) => $apri === $k ? ' open' : '';

// I riassunti delle sezioni.
$mittScelto = (int)$c['mittente_id'] && isset($mittentiScelta[(int)$c['mittente_id']]) ? $mittentiScelta[(int)$c['mittente_id']] : ($mittPre !== '' ? $nomePre . ' <' . $mittPre . '>' : 'nessun mittente');
$nomeL = array_column($liste, 'nome', 'id'); $nomeS = array_column($segmenti, 'nome', 'id');
$dest = [];
foreach ($sceltiL as $l) if (isset($nomeL[$l])) $dest[] = newsletter_4byte_fuori($nomeL[$l]);
foreach ($sceltiS as $s) if (isset($nomeS[$s])) $dest[] = 'segmento ' . newsletter_4byte_fuori($nomeS[$s]);
$riaDest = (int)$c['rinvio_di'] ? 'Chi non ha aperto la campagna di partenza' : ($dest ? implode(', ', $dest) : 'nessuna lista né segmento');
$riaDest .= ' · ' . ($modificabile ? (int)$sez['destinatari']['n'] . ' contatti' : (int)$c['destinatari'] . ' destinatari');

require_once __DIR__ . '/includes/admin-header.php';
?>
<link href="<?= pannello_file('assets/quill/quill.snow.css') ?>" rel="stylesheet">
<style>
#editor{min-height:380px;background:var(--campo);color:var(--text);font-size:16px}
.nlr-sez{margin-top:1rem}
.nlr-sez>summary{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .7rem;position:relative;padding-right:2.8rem}
.nlr-sez>summary::after{position:absolute;right:1.4rem;top:.7rem}
.nlr-azioni .btn:disabled{opacity:.45;cursor:not-allowed}
.nlr-sez form>fieldset>.btn-primary{margin-top:.8rem}
.nlr-stato{display:inline-flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:700;padding:.1rem .55rem;border-radius:999px;border:1px solid currentColor}
.nlr-ok{color:var(--successo-testo);background:var(--successo-chiaro)}
.nlr-manca{color:var(--attenzione-testo);background:var(--attenzione-chiaro)}
.nlr-riassunto{flex-basis:100%;font-size:.85rem;font-weight:400;color:var(--testata-testo);opacity:.9;overflow-wrap:anywhere}
.nlr-manca-testo{margin:0 0 1rem;padding:.55rem .8rem;border-radius:8px;background:var(--attenzione-chiaro);color:var(--attenzione-testo);border:1px solid var(--attenzione-bordo)}
.nlr-stato-box{padding:.7rem 1rem;border-radius:8px;background:var(--info-chiaro);color:var(--info-testo);border:1px solid var(--info-bordo);margin:0 0 1rem}
.nlr-colonne{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:0 1.25rem}
.nlr-anteprima iframe{width:100%;height:720px;border:1px solid var(--border);border-radius:10px;background:#f4f4f6 /* fisso: il fondo dell email */;display:block;margin:0 auto;transition:width .2s}
.nlr-anteprima.telefono iframe{width:390px;max-width:100%}
.nlr-schede{display:flex;gap:.4rem;flex-wrap:wrap;margin:.6rem 0}
.nlr-schede button[aria-pressed=true],.nlr-versioni a[aria-current]{background:var(--coral);color:var(--su-accento);border-color:var(--coral)}
.nlr-ab-tabella td,.nlr-ab-tabella th{overflow-wrap:anywhere}
#nlr-ab-perc{width:100%;accent-color:var(--coral)}
.nlr-numeri{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.6rem;margin:.6rem 0}
.nlr-numeri div{border:1px solid var(--border);border-radius:8px;padding:.6rem .8rem}.nlr-numeri b{display:block;font-size:1.4rem}
.nlr-azioni{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-top:1rem}
.nlr-azioni form{margin:0}
.nlr-quando label{display:flex;gap:.45rem;align-items:center;min-height:36px;font-weight:400}
.nlr-testo{font-family:var(--font-mono,monospace);font-size:.85rem;min-height:12rem}
.nlr-codice{font-family:var(--font-mono,monospace);font-size:.82rem;min-height:22rem}
.nd-strade-titolo{margin:0 0 .5rem;font-size:.95rem}
.nd-strade{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr));gap:.6rem;margin:0 0 .6rem}
.nd-strada{display:flex;flex-direction:column;gap:.35rem;align-items:flex-start;border:1px solid var(--bordo-campo);border-radius:10px;padding:.7rem .8rem;margin:0;min-width:0}
.nd-strada>span:not(.nd-uso){font-size:.86rem;color:var(--text-muted)}
.nd-strada select{width:100%}
.nd-strada .btn{margin-top:auto}
.nd-in-uso{border:2px solid var(--coral);background:var(--coral-light)}
.nd-uso{display:inline-block;font-size:.75rem;font-weight:700;padding:.05rem .5rem;border-radius:999px;background:var(--coral);color:var(--su-accento)}
</style>

<p><a href="newsletter.php">&larr; Tutte le campagne</a></p>
<details class="nll-modifica" style="margin:0 0 .8rem"><summary class="btn btn-secondary btn-sm">Rinomina la campagna</summary>
  <form method="post" class="nl-riga" style="margin-top:.5rem;gap:.5rem;align-items:flex-end">
    <?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="nome">
    <div class="form-group" style="margin:0;flex:1;min-width:min(100%,16rem)"><label for="nlr-nome">Nome della campagna (lo vedi solo tu)</label><input type="text" id="nlr-nome" name="nome" maxlength="150" value="<?= h($nomeC) ?>" required></div>
    <button class="btn btn-primary btn-sm" type="submit">Salva il nome</button>
  </form>
</details>

<?php if ($madre): ?><p class="nlr-stato-box" role="status"><?= (int)$c['rinvio_di'] ? 'Rinvio ai non aperti di' : 'Copia per i nuovi contatti di' ?> «<a href="newsletter-campagna.php?id=<?= (int)$madre['id'] ?>"><?= h(newsletter_4byte_fuori((string)($madre['nome'] ?: $madre['oggetto']))) ?></a>»: <?= (int)$c['rinvio_di'] ? 'arriva solo a chi l\'ha ricevuta e non l\'ha aperta.' : 'arriva solo a chi non ha ricevuto quella campagna (né un\'altra sua copia per i nuovi contatti).' ?></p><?php endif; ?>
<?php if ($c['stato'] !== 'bozza'): ?>
<div class="nlr-stato-box" role="status">
  <?php if ($c['stato'] === 'programmata'): ?>Programmata per il <b><?= h(date('d/m/Y H:i', strtotime((string)$c['invio_at']))) ?></b> (ora di <?= h(date_default_timezone_get()) ?>). Puoi ancora modificarla.
  <?php elseif ($isAb && $ab['fase'] === 'prova' && in_array($c['stato'], ['in_invio', 'sospesa', 'inviata'], true)): $abScade = nl_ab_scade($ab); ?>Test A/B in corso<?= $c['stato'] === 'sospesa' ? ' (invio sospeso)' : '' ?>: <?= (int)$c['inviate'] ?> di <?= 2 * (int)$ab['gruppo'] ?> email di prova partite (<?= (int)$ab['gruppo'] ?> per versione). La versione vincente si sceglie dopo il <b><?= h(date('d/m/Y H:i', $abScade)) ?></b>, al primo giro del cron (entro 5 minuti)<?= (int)$ab['resto'] ? ', e parte agli altri ' . (int)$ab['resto'] . ' contatti' : '' ?>.
  <?php elseif ($c['stato'] === 'in_invio' || $c['stato'] === 'sospesa'): ?><?= $c['stato'] === 'sospesa' ? 'Invio sospeso' : 'In invio' ?>: <?= (int)$c['inviate'] ?> di <?= (int)$c['destinatari'] ?> partite<?= $c['errori'] ? ', ' . (int)$c['errori'] . ' non partite' : '' ?><?= (int)$c['lotti'] > 1 ? ', in ' . (int)$c['lotti'] . ' lotti ogni ' . (int)$c['lotti_intervallo'] . ' minuti' : '' ?>.
  <?php elseif ($c['stato'] === 'annullata'): ?>Annullata: <?= (int)$c['inviate'] ?> email erano già partite, le altre non partono più.
  <?php else: ?>Inviata: <?= (int)$c['inviate'] ?> email partite<?= $c['errori'] ? ', ' . (int)$c['errori'] . ' non partite' : '' ?>. Per una nuova versione usa «Duplica» nell'elenco.<?php endif; ?>
  <?= (int)$c['archiviata'] ? '<br>Questa campagna è archiviata.' : '' ?>
</div>
<?php endif; ?>
<?php if (in_array($c['stato'], ['in_invio', 'sospesa', 'inviata', 'annullata'], true)):
  $ev = newsletter_conteggio_eventi($id); $mot = newsletter_uscite_motivi_conta($id);
  $usc = (int)db()->query('SELECT COUNT(DISTINCT iscritto_id) FROM ' . tbl('newsletter_eventi') . " WHERE campagna_id = $id AND tipo = 'uscita'")->fetchColumn(); ?>
<div class="form-card" id="nlr-report">
  <h3>Report</h3>
  <div class="nlr-numeri">
    <div><b><?= (int)$c['inviate'] ?></b>inviate</div>
    <div><b><?= (int)$ev['apertura'] ?></b>hanno aperto<?= $c['inviate'] ? ' (' . round($ev['apertura'] * 100 / $c['inviate'], 1) . '%)' : '' ?></div>
    <div><b><?= (int)$ev['clic'] ?></b>hanno cliccato<?= $c['inviate'] ? ' (' . round($ev['clic'] * 100 / $c['inviate'], 1) . '%)' : '' ?></div>
    <div><b><?= $usc ?></b>sono usciti</div>
  </div>
  <?php if ($mot['totale']): ?><p style="margin:.4rem 0 .2rem"><b>Perché sono usciti</b> (dal questionario)</p>
  <ul class="nlr-motivi" style="margin:0 0 .6rem;padding-left:1.2rem"><?php foreach ($mot['n'] as $k => $v): if (!$v) continue; ?><li><?= h(NL_USCITA_MOTIVI[$k]) ?>: <b><?= $v ?></b></li><?php endforeach; ?></ul><?php endif; ?>
  <p class="hint">Aperture e clic contano una volta per persona. <a href="newsletter-statistiche.php?campagna=<?= $id ?>">Tutti i numeri e i link cliccati</a>.</p>
</div>
<?php if ($isAb && $ab['fase'] !== ''): $abR = nl_ab_risultati($id); $abV = (string)$ab['vincitore']; ?>
<div class="form-card" id="nlr-ab-risultati">
  <h3>Risultati del test A/B</h3>
  <p role="status" class="nlr-ab-esito"><?php if ($abV === ''): ?>Non ancora: la versione vincente si sceglie dopo il <b><?= h(date('d/m/Y H:i', nl_ab_scade($ab))) ?></b>, secondo <?= h(mb_strtolower(NL_AB_CRITERI[$ab['criterio']])) ?>.
  <?php elseif ($abV === 'pari'): ?><b>Pari</b>: le due versioni hanno lo stesso <?= $ab['criterio'] === 'clic' ? 'tasso di clic' : 'tasso di apertura' ?>, e al resto è partita la versione A (scelta il <?= h(date('d/m/Y H:i', strtotime((string)$ab['scelto_at']))) ?>).
  <?php else: ?><b>Ha vinto la versione <?= strtoupper($abV) ?></b>, per <?= h(mb_strtolower(NL_AB_CRITERI[$ab['criterio']])) ?>: scelta il <?= h(date('d/m/Y H:i', strtotime((string)$ab['scelto_at']))) ?><?= (int)$ab['resto'] ? ' e mandata agli altri ' . (int)$ab['resto'] . ' contatti' : '' ?>.<?php endif; ?></p>
  <table class="table nlr-ab-tabella">
    <thead><tr><th>Versione</th><th><?= $ab['cosa'] === 'oggetto' ? 'Oggetto' : 'Contenuto' ?></th><th>Inviate</th><th>Hanno aperto</th><th>Hanno cliccato</th></tr></thead>
    <tbody>
    <?php foreach (['a', 'b'] as $v): $x = $abR[$v]; ?>
      <tr data-versione="<?= $v ?>"><td><b><?= strtoupper($v) ?></b><?= $abV === $v ? ' <span class="nlr-stato nlr-ok"><span aria-hidden="true">&#10003;</span> Vincente</span>' : ($abV === 'pari' && $v === 'a' ? ' <span class="nlr-stato nlr-ok">Partita al resto</span>' : '') ?></td>
        <td><?= h($ab['cosa'] === 'oggetto' ? newsletter_4byte_fuori((string)nl_ab_versione($c, $v)['oggetto']) : 'Design ' . strtoupper($v)) ?></td>
        <td><?= (int)$x['inviate'] ?></td><td><?= (int)$x['apertura'] ?> (<?= $x['tasso_aperture'] ?>%)</td><td><?= (int)$x['clic'] ?> (<?= $x['tasso_clic'] ?>%)</td></tr>
    <?php endforeach; ?>
    <?php if ((int)$ab['resto']): ?><tr data-versione="r"><td>Il resto</td><td><?= $abV === '' ? 'aspetta la vincente' : 'versione ' . ($abV === 'b' ? 'B' : 'A') ?></td><td><?= (int)$abR['r']['inviate'] ?> di <?= (int)$ab['resto'] ?></td><td><?= (int)$abR['r']['apertura'] ?> (<?= $abR['r']['tasso_aperture'] ?>%)</td><td><?= (int)$abR['r']['clic'] ?> (<?= $abR['r']['tasso_clic'] ?>%)</td></tr><?php endif; ?>
    </tbody>
  </table>
  <p class="hint">Ogni gruppo di prova: <?= (int)$ab['percentuale'] ?>% dei destinatari, scelti a caso; ognuno riceve una versione sola. I tassi sono sulle email inviate al gruppo.</p>
</div>
<?php endif; ?>
<?php endif; ?>
<?php if ($manca): ?><div class="nlr-manca-testo" role="status" id="nlr-manca"><b>Per inviare <?= count($manca) === 1 ? 'manca una cosa' : 'mancano ' . count($manca) . ' cose' ?>:</b><ul style="margin:.3rem 0 0;padding-left:1.2rem"><?php foreach ($manca as $m): ?><li><?= h($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<details class="form-card nlx-riquadro nlr-sez" id="sez-mittente"<?= $aperta('mittente') ?>>
  <?= $testa('mittente', 'Mittente', $mittScelto) ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="mittente">
    <fieldset style="border:0;padding:0;margin:0;min-width:0"<?= $dis ?>>
    <div class="form-group"><label for="mittente_id">Chi manda l'email</label>
      <select id="mittente_id" name="mittente_id">
        <option value="0">Il predefinito<?= $mittPre !== '' ? ': ' . h($nomePre . ' <' . $mittPre . '>') : '' ?></option>
        <?php foreach ($mittentiScelta as $mid => $mn): ?><option value="<?= (int)$mid ?>" <?= (int)$c['mittente_id'] === (int)$mid ? 'selected' : '' ?>><?= h($mn) ?></option><?php endforeach; ?>
      </select>
      <div class="hint">I mittenti si aggiungono e si confermano in <a href="consegna.php?scheda=mittenti">Consegna delle email</a>.</div></div>
    <?php if ($modificabile): ?><button class="btn btn-primary" type="submit">Salva il mittente</button><?php endif; ?>
    </fieldset>
  </form>
</details>

<details class="form-card nlx-riquadro nlr-sez" id="sez-destinatari"<?= $aperta('destinatari') ?>>
  <?= $testa('destinatari', 'Destinatari', $riaDest) ?>
  <?php if ((int)$c['rinvio_di']): ?>
  <p>Questo rinvio arriva solo a chi ha ricevuto «<?= h(newsletter_4byte_fuori((string)($madre['nome'] ?? ''))) ?>» e non l'ha aperta, ed è ancora iscritto: oggi <b><?= (int)$sez['destinatari']['n'] ?></b> contatti. Il numero vero si conta quando parte.</p>
  <?php else: ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="destinatari">
    <fieldset style="border:0;padding:0;margin:0;min-width:0"<?= $dis ?>>
    <div class="nlr-colonne">
      <fieldset class="nlk-scelte"><legend>Invia a queste liste</legend>
        <?php foreach ($liste as $l): ?><label><input type="checkbox" name="liste[]" value="<?= (int)$l['id'] ?>" <?= in_array((int)$l['id'], $sceltiL, true) ? 'checked' : '' ?>> <?= h(newsletter_4byte_fuori($l['nome'])) ?> <small style="color:var(--text-muted)">(<?= (int)$l['attivi'] ?> iscritti)</small></label><?php endforeach; ?>
        <?php if (!$liste): ?><p class="hint">Ancora nessuna lista: creala in <a href="newsletter-contatti.php?scheda=liste">Contatti, Liste</a>.</p><?php endif; ?>
      </fieldset>
      <fieldset class="nlk-scelte"><legend>e a questi segmenti</legend>
        <?php foreach ($segmenti as $s): ?><label><input type="checkbox" name="segmenti[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $sceltiS, true) ? 'checked' : '' ?>> <?= h(newsletter_4byte_fuori($s['nome'])) ?> <small style="color:var(--text-muted)">(<?= (int)$s['conteggio'] ?> <?= (int)$s['conteggio'] === 1 ? 'contatto' : 'contatti' ?>)</small></label><?php endforeach; ?>
        <?php if (!$segmenti): ?><p class="hint">Ancora nessun segmento: si creano in <a href="newsletter-segmenti.php">Contatti, Segmenti</a>.</p><?php endif; ?>
      </fieldset>
    </div>
    <details class="pn-aiuto"<?= $esclL || $esclS ? ' open' : '' ?>><summary>Escludi delle liste o dei segmenti</summary>
    <div class="nlr-colonne">
      <fieldset class="nlk-scelte"><legend>Non mandare a chi sta in queste liste</legend>
        <?php foreach ($liste as $l): ?><label><input type="checkbox" name="escludi_liste[]" value="<?= (int)$l['id'] ?>" <?= in_array((int)$l['id'], $esclL, true) ? 'checked' : '' ?>> <?= h(newsletter_4byte_fuori($l['nome'])) ?></label><?php endforeach; ?>
      </fieldset>
      <fieldset class="nlk-scelte"><legend>Non mandare a chi sta in questi segmenti</legend>
        <?php foreach ($segmenti as $s): ?><label><input type="checkbox" name="escludi_segmenti[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $esclS, true) ? 'checked' : '' ?>> <?= h(newsletter_4byte_fuori($s['nome'])) ?></label><?php endforeach; ?>
        <?php if (!$segmenti): ?><p class="hint">Nessun segmento.</p><?php endif; ?>
      </fieldset>
    </div>
    <p class="hint">L'esclusione vince: chi sta in una lista scelta e anche in una esclusa non la riceve.</p>
    </details>
    <label class="nl-casella" style="display:flex;gap:.45rem;align-items:center;min-height:36px;margin:.6rem 0"><input type="checkbox" name="non_coinvolti" value="1" <?= (int)$c['non_coinvolti'] ? 'checked' : '' ?>> Non mandare ai contatti non coinvolti</label>
    <p class="hint" style="margin-top:0">Non coinvolti: chi negli ultimi <?= newsletter_non_coinvolti_giorni() ?> giorni ha ricevuto almeno <?= NL_NON_COINVOLTI_EMAIL ?> campagne senza aprirne né cliccarne nessuna (si cambia in <a href="newsletter.php?scheda=impostazioni">Impostazioni</a>).</p>
    <p class="hint nlr-conteggio" role="status">Con le scelte salvate la campagna arriva a <b data-nlr-destinatari><?= (int)$sez['destinatari']['n'] ?></b> contatti iscritti: chi sta in più liste o segmenti la riceve una volta sola. Il numero si aggiorna quando salvi.</p>
    <?php if ($modificabile): ?><button class="btn btn-primary" type="submit">Salva i destinatari</button><?php endif; ?>
    </fieldset>
  </form>
  <?php endif; ?>
</details>

<?php if ($isAb):
  $abN = (int)$sez['destinatari']['n']; $abG = nl_ab_gruppo($abN, (int)$ab['percentuale']);
  $riaAb = NL_AB_COSA[$ab['cosa']] . ' · gruppi del ' . (int)$ab['percentuale'] . '% · ' . mb_strtolower(NL_AB_CRITERI[$ab['criterio']]) . ' · ' . (int)$ab['durata'] . ' ' . ((int)$ab['durata'] === 1 ? ($ab['unita'] === 'ore' ? 'ora' : 'giorno') : $ab['unita']); ?>
<details class="form-card nlx-riquadro nlr-sez" id="sez-ab"<?= $aperta('ab') ?>>
  <?= $testa('ab', 'Test A/B', $riaAb) ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="ab">
    <fieldset style="border:0;padding:0;margin:0;min-width:0"<?= $dis ?>>
    <fieldset class="nlk-scelte"><legend>Cosa vuoi provare</legend>
      <label><input type="radio" name="cosa" value="oggetto" <?= $ab['cosa'] === 'oggetto' ? 'checked' : '' ?>> L'oggetto: stessa email, due oggetti diversi</label>
      <label><input type="radio" name="cosa" value="design" <?= $ab['cosa'] === 'design' ? 'checked' : '' ?>> Il contenuto: stesso oggetto, due design diversi</label>
    </fieldset>
    <?php if ($ab['cosa'] === 'oggetto'): ?>
    <p class="hint">Versione A: «<?= h(trim(newsletter_4byte_fuori((string)$c['oggetto'])) ?: 'ancora da scrivere nella sezione Oggetto') ?>».</p>
    <div class="form-group"><label for="nlr-oggetto-b">Oggetto della versione B</label><input type="text" id="nlr-oggetto-b" name="oggetto_b" maxlength="200" value="<?= h(newsletter_4byte_fuori((string)$ab['oggetto_b'])) ?>" placeholder="per esempio: Solo per te, {{ contact.NOME }}: le novità"></div>
    <div class="form-group"><label for="nlr-anteprima-b">Testo di anteprima della versione B (facoltativo)</label><input type="text" id="nlr-anteprima-b" name="anteprima_b" maxlength="200" value="<?= h(newsletter_4byte_fuori((string)$ab['anteprima_b'])) ?>"></div>
    <?php else: ?>
    <p class="hint">La versione B è nata come copia della A: cambiala nella sezione Design, scheda «Versione B» (<a href="newsletter-campagna.php?id=<?= $id ?>&amp;versione=b&amp;apri=design#sez-design">apri la versione B</a>).</p>
    <?php endif; ?>
    <div class="form-group"><label for="nlr-ab-perc">Grandezza di ogni gruppo di prova: <output id="nlr-ab-perc-val" for="nlr-ab-perc"><?= (int)$ab['percentuale'] ?>%</output></label>
      <input type="range" id="nlr-ab-perc" name="percentuale" min="1" max="<?= NL_AB_PERC_MAX ?>" step="1" value="<?= (int)$ab['percentuale'] ?>" data-ab-n="<?= $abN ?>" aria-describedby="nlr-ab-gruppi">
      <div class="hint" id="nlr-ab-gruppi" data-ab-gruppi>Versione A a <?= $abG ?> contatti, versione B a <?= $abG ?>, la vincente agli altri <?= max(0, $abN - 2 * $abG) ?> (contati adesso: <?= $abN ?>).</div></div>
    <fieldset class="nlk-scelte"><legend>Come si sceglie la versione vincente</legend>
      <?php foreach (NL_AB_CRITERI as $k => $et): ?><label><input type="radio" name="criterio" value="<?= $k ?>" <?= $ab['criterio'] === $k ? 'checked' : '' ?>> <?= h($et) ?> più alto</label><?php endforeach; ?>
    </fieldset>
    <div class="nlr-colonne">
      <div class="form-group"><label for="nlr-ab-durata">Durata del test</label><input type="number" id="nlr-ab-durata" name="durata" min="1" max="23" value="<?= (int)$ab['durata'] ?>" aria-describedby="nlr-ab-durata-aiuto"></div>
      <div class="form-group"><label for="nlr-ab-unita">In</label><select id="nlr-ab-unita" name="unita"><option value="ore" <?= $ab['unita'] === 'ore' ? 'selected' : '' ?>>ore (al massimo 23)</option><option value="giorni" <?= $ab['unita'] === 'giorni' ? 'selected' : '' ?>>giorni (al massimo 7)</option></select></div>
    </div>
    <p class="hint" id="nlr-ab-durata-aiuto">Dalla partenza dei gruppi di prova. Alla fine il cron sceglie da solo la versione con il tasso più alto e la manda agli altri; a parità parte la versione A. Si manda subito o si programma; niente lotti nel test A/B.</p>
    <?php if ($modificabile): ?><button class="btn btn-primary" type="submit">Salva il test A/B</button><?php endif; ?>
    </fieldset>
  </form>
</details>
<?php endif; ?>

<details class="form-card nlx-riquadro nlr-sez" id="sez-oggetto"<?= $aperta('oggetto') ?>>
  <?= $testa('oggetto', 'Oggetto', trim(newsletter_4byte_fuori((string)$c['oggetto'])) ?: 'nessun oggetto') ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="oggetto">
    <fieldset style="border:0;padding:0;margin:0;min-width:0"<?= $dis ?>>
    <div class="form-group"><label for="oggetto">Oggetto</label><input type="text" id="oggetto" name="oggetto" maxlength="200" value="<?= h(newsletter_4byte_fuori($c['oggetto'])) ?>" placeholder="per esempio: Le novità di novembre, {{ contact.NOME | default:&quot;amica, amico&quot; }}"></div>
    <div class="form-group"><label for="anteprima">Testo di anteprima (facoltativo)</label><input type="text" id="anteprima" name="anteprima" maxlength="200" value="<?= h(newsletter_4byte_fuori($c['anteprima'])) ?>" aria-describedby="anteprima-aiuto">
      <div class="hint" id="anteprima-aiuto">La riga grigia che i programmi di posta mostrano accanto all'oggetto.</div></div>
    <details class="pn-aiuto"><summary>Come mettere il nome o un altro dato di chi legge</summary>
      <p>Scrivi <code>{{ contact.NOME }}</code> dove vuoi il nome, <code>{{ contact.COGNOME }}</code> per il cognome, e così ogni attributo dei contatti (<a href="newsletter-attributi.php">elenco degli attributi</a>). Se a qualcuno manca il dato, con <code>{{ contact.NOME | default:"amica, amico" }}</code> esce quel testo. Altri filtri: <code>| upper</code> (tutto maiuscolo), <code>| lower</code>, <code>| capfirst</code> (prima lettera maiuscola). Vale anche <code>{nome}</code>, come prima. Valgono nell'oggetto, nel testo di anteprima e nel testo dell'email.</p>
    </details>
    <?php if ($modificabile): ?><button class="btn btn-primary" type="submit">Salva l'oggetto</button><?php endif; ?>
    </fieldset>
  </form>
</details>

<details class="form-card nlx-riquadro nlr-sez" id="sez-design"<?= $aperta('design') ?>>
  <?= $testa('design', 'Design', ($isAb && $ab['cosa'] === 'design' ? 'Versione A: ' . ($STRADE[$c['editor']] ?? 'Editor semplice') . ' · Versione B: ' . ($STRADE[$ab['editor_b']] ?? 'Editor semplice') : ($STRADE[$c['editor']] ?? 'Editor semplice') . ($modelloUsato ? ' (dal modello «' . newsletter_4byte_fuori((string)$modelloUsato['nome']) . '»)' : ''))) ?>
  <?php if ($isAb && $ab['cosa'] === 'design'): /* 2.13.0: le due versioni del test sul contenuto */ ?>
  <nav class="nlr-schede nlr-versioni" aria-label="Versione del design">
    <a class="btn btn-secondary btn-sm" href="newsletter-campagna.php?id=<?= $id ?>&amp;apri=design#sez-design"<?= $verB ? '' : ' aria-current="page"' ?>>Versione A</a>
    <a class="btn btn-secondary btn-sm" href="newsletter-campagna.php?id=<?= $id ?>&amp;versione=b&amp;apri=design#sez-design"<?= $verB ? ' aria-current="page"' : '' ?>>Versione B</a>
  </nav>
  <p class="hint" style="margin-top:0">Stai cambiando la <b>versione <?= $verB ? 'B' : 'A' ?></b>.</p>
  <?php endif; ?>
  <?php if ($modificabile): /* 2.12.0: le cinque strade per il design */ ?>
  <h4 class="nd-strade-titolo">Come fai il design</h4>
  <div class="nd-strade">
    <?php $vh = $verB ? '<input type="hidden" name="versione" value="b">' : ''; ?>
    <form method="post" class="nd-strada<?= $cd['editor'] === 'blocchi' ? ' nd-in-uso' : '' ?>"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="design"><input type="hidden" name="cambia_editor" value="1"><input type="hidden" name="editor" value="blocchi"><?= $vh ?>
      <b>Editor a blocchi</b><?= $cd['editor'] === 'blocchi' ? ' <span class="nd-uso">In uso</span>' : '' ?><span>Sezioni a colonne, titoli, immagini, pulsanti, social e video: trascini e componi.</span>
      <?php if ($cd['editor'] === 'blocchi'): ?><a class="btn btn-primary btn-sm" href="newsletter-design.php?campagna=<?= $id ?><?= $verB ? '&amp;versione=b' : '' ?>">Apri l'editor a blocchi</a><?php else: ?><button class="btn btn-secondary btn-sm" type="submit">Usa l'editor a blocchi</button><?php endif; ?>
    </form>
    <form method="post" class="nd-strada"<?= $modelliAttivi ? ' data-conferma="Il design di adesso viene sostituito da quello del modello. Continuare?" data-conferma-ok="Usa il modello"' : '' ?>><?= csrf_field() ?><input type="hidden" name="azione" value="usa_modello"><?= $vh ?>
      <b>Un tuo modello</b><span>Il design di un modello salvato (solo i modelli attivi).</span>
      <?php if ($modelliAttivi): ?>
      <label class="sr-solo" for="nd-modello">Modello</label><select id="nd-modello" name="modello_id"><?php foreach ($modelliAttivi as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h(newsletter_4byte_fuori((string)$m['nome'])) ?></option><?php endforeach; ?></select>
      <button class="btn btn-secondary btn-sm" type="submit">Usa questo modello</button>
      <?php else: ?><span class="hint">Ancora nessun modello attivo: salvane uno dall'editor a blocchi, o attivalo in <a href="newsletter-modelli.php">Modelli</a>.</span><?php endif; ?>
    </form>
    <div class="nd-strada"><b>La galleria</b><span>Modelli pronti: benvenuto, newsletter del mese, offerta, evento, nuovo articolo e altri.</span>
      <a class="btn btn-secondary btn-sm" href="newsletter-modelli.php?scheda=galleria&amp;campagna=<?= $id ?><?= $verB ? '&amp;versione=b' : '' ?>">Scegli nella galleria</a></div>
    <form method="post" class="nd-strada<?= $cd['editor'] === 'semplice' ? ' nd-in-uso' : '' ?>"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="design"><input type="hidden" name="cambia_editor" value="1"><input type="hidden" name="editor" value="semplice"><?= $vh ?>
      <b>Editor semplice</b><?= $cd['editor'] === 'semplice' ? ' <span class="nd-uso">In uso</span>' : '' ?><span>Testo, titoli, immagini, link e un pulsante, come in una lettera.</span>
      <?php if ($cd['editor'] !== 'semplice'): ?><button class="btn btn-secondary btn-sm" type="submit">Usa l'editor semplice</button><?php endif; ?>
    </form>
    <form method="post" class="nd-strada<?= $cd['editor'] === 'html' ? ' nd-in-uso' : '' ?>"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="design"><input type="hidden" name="cambia_editor" value="1"><input type="hidden" name="editor" value="html"><?= $vh ?>
      <b>Codice HTML</b><?= $cd['editor'] === 'html' ? ' <span class="nd-uso">In uso</span>' : '' ?><span>Incolli un'email già fatta.</span>
      <?php if ($cd['editor'] !== 'html'): ?><button class="btn btn-secondary btn-sm" type="submit">Usa il codice HTML</button><?php endif; ?>
    </form>
  </div>
  <p class="hint">Cambiando strada non perdi niente: il design di ogni editor resta salvato e lo ritrovi se torni indietro.</p>
  <?php endif; ?>
  <form method="post" id="nl-form"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="design"><input type="hidden" name="editor" value="<?= h(isset($STRADE[$cd['editor']]) ? $cd['editor'] : 'semplice') ?>"><?= $verB ? '<input type="hidden" name="versione" value="b">' : '' ?>
    <fieldset style="border:0;padding:0;margin:0;min-width:0"<?= $dis ?>>
    <?php if ($cd['editor'] === 'blocchi'): ?>
    <p>L'email è fatta con l'editor a blocchi: la vedi in <a href="#sez-prova">Anteprima e prova</a>. <?= $modificabile ? 'Per cambiarla, «Apri l\'editor a blocchi» qui sopra: le modifiche si salvano da sole.' : '<a href="newsletter-design.php?campagna=' . $id . ($verB ? '&amp;versione=b' : '') . '">Guarda il design nell\'editor</a>.' ?></p>
    <?php elseif ($cd['editor'] === 'html'): ?>
    <div class="form-group"><label for="nlr-html">Il codice HTML dell'email</label>
      <textarea id="nlr-html" name="html" class="nlr-codice" spellcheck="false" aria-describedby="nlr-html-aiuto"><?= h(newsletter_4byte_fuori((string)$cd['html'])) ?></textarea>
      <div class="hint" id="nlr-html-aiuto">Metti <code>{{ unsubscribe }}</code> dove vuoi il link per uscire (se manca, lo aggiungiamo noi in fondo), <code>{{ update_profile }}</code> per le preferenze e <code>{{ mirror }}</code> per la versione web. Valgono i segnaposti come <code>{{ contact.NOME }}</code>. Gli script si tolgono: nessun programma di posta li esegue.</div></div>
    <?php else: ?>
    <input type="hidden" name="corpo" id="corpo">
    <div class="form-group"><label id="editor-etichetta">Testo dell'email</label><div id="editor" aria-labelledby="editor-etichetta"></div></div>
    <div class="form-group"><label for="pulsante_testo">Pulsante in fondo (facoltativo)</label>
      <input type="text" id="pulsante_testo" name="pulsante_testo" maxlength="100" placeholder="per esempio: Leggi la guida" value="<?= h(newsletter_4byte_fuori((string)$cd['pulsante_testo'])) ?>">
      <div style="margin-top:.4rem"><input type="url" name="pulsante_link" id="nl-pulsante-link" maxlength="500" placeholder="<?= h(cms_url_sito()) ?>/..." value="<?= h((string)$cd['pulsante_link']) ?>" aria-label="Link del pulsante" data-link-sito="assoluto"></div></div>
    <?php endif; ?>
    <details class="pn-aiuto"<?= (int)$cd['testo_modificato'] ? ' open' : '' ?>><summary>Testo semplice (per chi legge le email senza grafica)</summary>
      <label class="nl-casella" style="display:flex;gap:.45rem;align-items:center;min-height:36px"><input type="checkbox" name="testo_modificato" value="1" <?= (int)$cd['testo_modificato'] ? 'checked' : '' ?>> Lo scrivo io</label>
      <div class="form-group"><label for="nlr-testo">Testo semplice</label>
        <textarea id="nlr-testo" name="testo" class="nlr-testo" aria-describedby="nlr-testo-aiuto"><?= h((int)$cd['testo_modificato'] && trim((string)$cd['testo']) !== '' ? newsletter_4byte_fuori((string)$cd['testo']) : $testoProva) ?></textarea>
        <div class="hint" id="nlr-testo-aiuto">Senza la spunta si rifà da solo dall'email a ogni invio (qui vedi quello di adesso, con i dati dell'anteprima). Con la spunta parte questo, con i segnaposti; il link per uscire si aggiunge se manca.</div></div>
    </details>
    <?php if ($modificabile): ?><button class="btn btn-primary" type="submit"><?= $verB ? 'Salva il design della versione B' : 'Salva il design' ?></button><?php endif; ?>
    </fieldset>
  </form>
</details>

<details class="form-card nlx-riquadro nlr-sez" id="sez-impostazioni"<?= $aperta('impostazioni') ?>>
  <?php $riaImp = array_filter([$c['rispondi_a'] !== '' ? 'risposte a ' . $c['rispondi_a'] : '', (int)$c['link_web'] ? 'versione web' : '', (int)$c['link_preferenze'] ? 'link delle preferenze' : '', (int)$c['utm'] ? 'UTM' : '', $c['tag'] !== '' ? 'tag ' . newsletter_4byte_fuori($c['tag']) : '', (int)$c['lotti'] > 1 ? (int)$c['lotti'] . ' lotti' : '']); ?>
  <?= $testa('impostazioni', 'Impostazioni aggiuntive', $riaImp ? implode(', ', $riaImp) : 'nessuna') ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="sezione"><input type="hidden" name="sez" value="impostazioni">
    <fieldset style="border:0;padding:0;margin:0;min-width:0"<?= $dis ?>>
    <div class="nlr-colonne">
      <div class="form-group"><label for="nlr-rispondi">Rispondi a</label><input type="email" id="nlr-rispondi" name="rispondi_a" maxlength="190" value="<?= h($c['rispondi_a']) ?>" placeholder="per esempio: info@esempio.it" aria-describedby="nlr-rispondi-aiuto">
        <div class="hint" id="nlr-rispondi-aiuto">Dove arrivano le risposte a questa campagna. Vuoto: come il mittente.</div></div>
      <div class="form-group"><label for="nlr-campo-a">Personalizza il campo «A»</label><input type="text" id="nlr-campo-a" name="campo_a" maxlength="190" value="<?= h(newsletter_4byte_fuori($c['campo_a'])) ?>" placeholder="per esempio: {{ contact.NOME }} {{ contact.COGNOME }}" aria-describedby="nlr-campo-a-aiuto">
        <div class="hint" id="nlr-campo-a-aiuto">Il nome che chi riceve vede accanto al suo indirizzo. Vuoto: solo l'indirizzo.</div></div>
      <div class="form-group"><label for="nlr-tag">Tag</label><input type="text" id="nlr-tag" name="tag" maxlength="190" value="<?= h(newsletter_4byte_fuori($c['tag'])) ?>" placeholder="per esempio: promozioni" aria-describedby="nlr-tag-aiuto">
        <div class="hint" id="nlr-tag-aiuto">Una parola per ritrovare la campagna nell'elenco. Chi riceve non la vede.</div></div>
    </div>
    <label class="nl-casella" style="display:flex;gap:.45rem;align-items:center;min-height:36px"><input type="checkbox" name="link_web" value="1" <?= (int)$c['link_web'] ? 'checked' : '' ?>> In cima all'email il link «Vedi questa email nel browser»</label>
    <label class="nl-casella" style="display:flex;gap:.45rem;align-items:center;min-height:36px"><input type="checkbox" name="link_preferenze" value="1" <?= (int)$c['link_preferenze'] ? 'checked' : '' ?>> In fondo all'email il link «Aggiorna le tue preferenze»</label>
    <label class="nl-casella" style="display:flex;gap:.45rem;align-items:center;min-height:36px"><input type="checkbox" name="utm" value="1" <?= (int)$c['utm'] ? 'checked' : '' ?>> Aggiungi i parametri UTM ai link verso il tuo sito</label>
    <div class="form-group"><label for="nlr-utm">Nome della campagna nei parametri UTM</label><input type="text" id="nlr-utm" name="utm_campagna" maxlength="100" value="<?= h($c['utm_campagna']) ?>" placeholder="per esempio: novita-novembre" aria-describedby="nlr-utm-aiuto">
      <div class="hint" id="nlr-utm-aiuto">Nelle statistiche del sito (per esempio Google Analytics) vedi le visite arrivate da questa campagna. Vuoto: il nome della campagna. Valgono solo i link verso il tuo sito.</div></div>
    <?php if ($isAb): ?><p class="hint">Invio a lotti: non c'è nel test A/B (i gruppi di prova partono insieme, il resto parte con la vincente).</p><?php else: ?>
    <fieldset class="nlk-scelte"><legend>Invio a lotti</legend>
      <label><input type="checkbox" name="lotti_attivi" value="1" <?= (int)$c['lotti'] > 1 ? 'checked' : '' ?>> Manda la campagna in più lotti, a distanza di tempo</label>
      <div class="nlr-colonne">
        <div class="form-group"><label for="nlr-lotti">Quanti lotti</label><input type="number" id="nlr-lotti" name="lotti" min="2" max="<?= NL_LOTTI_MAX ?>" value="<?= max(2, (int)$c['lotti']) ?>"></div>
        <div class="form-group"><label for="nlr-intervallo">Minuti fra un lotto e l'altro</label><input type="number" id="nlr-intervallo" name="lotti_intervallo" min="5" max="10080" value="<?= (int)$c['lotti_intervallo'] ?: 60 ?>"></div>
      </div>
      <p class="hint">Fino a <?= NL_LOTTI_MAX ?> lotti. Il primo parte all'ora scelta, gli altri dopo: utile per non mandare tutto insieme. Una campagna a lotti non si rimanda ai non aperti.</p>
    </fieldset>
    <?php endif; ?>
    <?php if ($modificabile): ?><button class="btn btn-primary" type="submit">Salva le impostazioni</button><?php endif; ?>
    </fieldset>
  </form>
</details>

<details class="form-card nlx-riquadro nlr-sez" id="sez-prova"<?= $aperta('prova') || $antEmail !== '' ? ' open' : '' ?>>
  <summary><h3>Anteprima e prova</h3><span class="nlr-riassunto">Come la vede chi la riceve, e una prova nella tua casella</span></summary>
  <?php if ($isAb): ?>
  <nav class="nlr-schede nlr-versioni" aria-label="Versione dell'anteprima">
    <a class="btn btn-secondary btn-sm" href="newsletter-campagna.php?id=<?= $id ?>&amp;apri=prova#sez-prova"<?= $ver === 'a' ? ' aria-current="page"' : '' ?>>Versione A</a>
    <a class="btn btn-secondary btn-sm" href="newsletter-campagna.php?id=<?= $id ?>&amp;versione=b&amp;apri=prova#sez-prova"<?= $ver === 'b' ? ' aria-current="page"' : '' ?>>Versione B</a>
  </nav>
  <p class="hint" style="margin-top:0">Anteprima della <b>versione <?= strtoupper($ver) ?></b>. La prova manda tutte e due le versioni.</p>
  <?php endif; ?>
  <form method="get" class="nl-riga" style="gap:.5rem;align-items:flex-end;margin-bottom:.6rem">
    <input type="hidden" name="id" value="<?= $id ?>"><?= $ver === 'b' ? '<input type="hidden" name="versione" value="b">' : '' ?>
    <div class="form-group" style="margin:0;flex:1;min-width:min(100%,16rem)"><label for="nlr-contatto">Guarda l'anteprima con i dati di un contatto</label>
      <input type="email" id="nlr-contatto" name="contatto" list="nlr-test-elenco" value="<?= h($antEmail) ?>" placeholder="per esempio: tu@esempio.it" aria-describedby="nlr-contatto-aiuto">
      <datalist id="nlr-test-elenco"><?php foreach ($listaTest as $t): ?><option value="<?= h($t['email']) ?>"><?php endforeach; ?></datalist></div>
    <button class="btn btn-secondary btn-sm" type="submit">Mostra con i suoi dati</button>
  </form>
  <p class="hint" id="nlr-contatto-aiuto" style="margin-top:0"><?= $antEmail !== '' && !$antChi ? '<b>' . h($antEmail) . ' non è un contatto</b>: l\'anteprima usa il nome «Giulia».' : ($antChi ? 'Anteprima con i dati di ' . h($antChi['email']) . '.' : 'Senza un contatto, l\'anteprima usa il nome «Giulia».') ?> Mostra l'ultima versione salvata.</p>
  <div class="nlr-schede" role="group" aria-label="Larghezza dell'anteprima">
    <button type="button" class="btn btn-secondary btn-sm" data-nlr-largo="computer" aria-pressed="true">Computer</button>
    <button type="button" class="btn btn-secondary btn-sm" data-nlr-largo="telefono" aria-pressed="false">Telefono</button>
  </div>
  <p style="margin:.2rem 0 .5rem"><b>Oggetto:</b> <span data-nlr-oggetto><?= h($oggProva) ?></span></p>
  <div class="nlr-anteprima" id="nlr-anteprima"><iframe title="Anteprima dell'email" sandbox srcdoc="<?= h($htmlProva) ?>"></iframe></div>
  <form method="post" style="margin-top:1rem"><?= csrf_field() ?>
    <fieldset class="nlk-scelte" id="nlc-prova">
      <legend>Manda una prova</legend>
      <?php if ($listaTest): ?>
      <?php foreach ($listaTest as $t): ?><label><input type="checkbox" name="prova_test[]" value="<?= (int)$t['id'] ?>" checked> <?= h($t['email']) ?><?= trim($t['nome'] . ' ' . $t['cognome']) !== '' ? ' <span class="nlx-sotto" style="display:inline">(' . h(trim(newsletter_4byte_fuori($t['nome'] . ' ' . $t['cognome']))) . ')</span>' : '' ?></label><?php endforeach; ?>
      <p class="hint">Dalla <a href="newsletter.php?scheda=impostazioni#nl-test">lista dei test</a>: ognuno la riceve con i suoi dati. Le prove non contano nelle statistiche.</p>
      <?php else: ?><p class="hint">Nessun indirizzo nella <a href="newsletter.php?scheda=impostazioni#nl-test">lista dei test</a>: aggiungili in Impostazioni, oppure scrivi qui sotto a chi mandare la prova.</p><?php endif; ?>
      <div class="form-group"><label for="nlc-prova-a"><?= $listaTest ? 'Anche a un altro indirizzo' : 'Manda la prova a' ?></label><input type="email" id="nlc-prova-a" name="prova_a" value="<?= $listaTest ? '' : h(posta_notifica_email()) ?>" placeholder="per esempio: tu@esempio.it"></div>
      <label><input type="checkbox" name="prova_aggiungi" value="1"> Aggiungi questo indirizzo alla lista dei test (deve essere un contatto)</label>
      <div class="nlr-azioni"><button class="btn btn-secondary" type="submit" name="azione" value="prova">Manda la prova</button></div>
    </fieldset>
  </form>
</details>

<div class="form-card" id="nlr-invio" style="margin-top:1rem">
  <h3><?= $modificabile ? 'Programma' : 'Invio' ?></h3>
  <?php if ($modificabile): ?>
  <form method="post"<?= $manca ? '' : ' data-conferma="' . ($isAb ? 'Partire con il test A/B? Le due versioni vanno a due gruppi di prova, poi la vincente agli altri: in tutto ' . (int)$sez['destinatari']['n'] . ' contatti. Dopo la partenza non si cambia più.' : 'Mandare la campagna a ' . (int)$sez['destinatari']['n'] . ' contatti? Dopo la partenza non si cambia più.') . '" data-conferma-ok="Sì, mandala"' ?>><?= csrf_field() ?><input type="hidden" name="azione" value="invia">
    <fieldset class="nlk-scelte nlr-quando"<?= $manca ? ' disabled' : '' ?>><legend>Quando</legend>
      <label><input type="radio" name="quando" value="subito" <?= $c['stato'] !== 'programmata' ? 'checked' : '' ?>> Invia subito (parte entro 5 minuti)</label>
      <label><input type="radio" name="quando" value="programma" <?= $c['stato'] === 'programmata' ? 'checked' : '' ?>> Programma per giorno e ora</label>
      <div class="form-group" style="margin:.3rem 0 0"><label for="nlr-quando">Giorno e ora (ora di <?= h(date_default_timezone_get()) ?>)</label><input type="datetime-local" id="nlr-quando" name="invio_at" value="<?= h($c['invio_at'] ? date('Y-m-d\TH:i', strtotime((string)$c['invio_at'])) : date('Y-m-d\TH:i', strtotime('+1 day 09:00'))) ?>"></div>
    </fieldset>
    <?php if ($manca): ?><p class="hint" role="status">Il pulsante si accende quando tutte le sezioni sono a posto: guarda il riquadro giallo in cima.</p><?php endif; ?>
    <div class="nlr-azioni"><button class="btn btn-primary" type="submit"<?= $manca ? ' disabled aria-disabled="true"' : '' ?>><?= $c['stato'] === 'programmata' ? 'Salva la programmazione' : 'Manda la campagna' ?></button></div>
  </form>
  <?php if ($c['stato'] === 'programmata'): ?>
  <div class="nlr-azioni">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="togli_programma"><button class="btn btn-secondary" type="submit">Togli la programmazione</button></form>
    <form method="post" data-conferma="Annullare la campagna? Non partirà più, e non si può riprendere." data-conferma-ok="Annulla la campagna" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="annulla"><button class="btn btn-danger" type="submit">Annulla la campagna</button></form>
  </div>
  <?php endif; ?>
  <?php else: ?>
  <div class="nlr-azioni">
    <?php if ($c['stato'] === 'in_invio'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="sospendi"><button class="btn btn-secondary" type="submit">Sospendi l'invio</button></form><?php endif; ?>
    <?php if ($c['stato'] === 'sospesa'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="azione" value="riprendi"><button class="btn btn-primary" type="submit">Riprendi l'invio</button></form><?php endif; ?>
    <?php if (in_array($c['stato'], ['in_invio', 'sospesa'], true)): ?><form method="post" data-conferma="Annullare la campagna? Le email non ancora partite non partiranno più." data-conferma-ok="Annulla la campagna" data-conferma-pericolo><?= csrf_field() ?><input type="hidden" name="azione" value="annulla"><button class="btn btn-danger" type="submit">Annulla la campagna</button></form><?php endif; ?>
    <?php if (!in_array($c['stato'], ['in_invio', 'sospesa'], true)): ?><p class="hint" style="margin:0">La campagna non si modifica più. Dall'elenco puoi duplicarla, mandarla ai nuovi contatti o rimandarla a chi non l'ha aperta.</p><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script<?= nonce_attr() ?>>
(function () {
  // 2.13.0: il cursore dei gruppi del test A/B dice quanti contatti va a ogni versione.
  var cur = document.getElementById('nlr-ab-perc');
  if (cur) cur.addEventListener('input', function () {
    var n = +cur.getAttribute('data-ab-n') || 0, p = +cur.value, g = n >= 2 ? Math.max(1, Math.floor(n * p / 100)) : 0;
    document.getElementById('nlr-ab-perc-val').textContent = p + '%';
    document.querySelector('[data-ab-gruppi]').textContent = 'Versione A a ' + g + ' contatti, versione B a ' + g + ', la vincente agli altri ' + Math.max(0, n - 2 * g) + ' (contati adesso: ' + n + ').';
  });
  var box = document.getElementById('nlr-anteprima');
  document.querySelectorAll('[data-nlr-largo]').forEach(function (b) {
    b.addEventListener('click', function () {
      var tel = b.getAttribute('data-nlr-largo') === 'telefono';
      box.classList.toggle('telefono', tel);
      document.querySelectorAll('[data-nlr-largo]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    });
  });
})();
</script>
<?php if ($cd['editor'] === 'semplice'): ?>
<script src="<?= pannello_file('assets/quill/quill.js') ?>"></script>
<script<?= nonce_attr() ?>>
(function () {
  var quill = new Quill('#editor', {
    theme: 'snow',
    readOnly: <?= $modificabile ? 'false' : 'true' ?>,
    placeholder: 'Scrivi qui. {{ contact.NOME }} diventa il nome di chi legge.',
    modules: { toolbar: { container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], ['link', 'image'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote'], [{ align: [] }], ['clean']],
      handlers: { image: carica } } }
  });
  quill.clipboard.dangerouslyPasteHTML(<?= json_encode((string)$cd['corpo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);
  // 2.2: il pulsante link sceglie una pagina del sito, con l'indirizzo intero (serve alle email). scegli-link.js arriva con defer.
  document.addEventListener('DOMContentLoaded', function () { if (window.pnLink) window.pnLink.quill(quill, { assoluto: true }); });
  // Le immagini vanno sul server, non dentro l'email: si sceglie dai Media (o si carica dalla stessa finestra, in
  // newsletter-immagine.php, senza WebP) e si inserisce l'indirizzo completo, quello che serve ai programmi di posta (2.1.0).
  function carica() {
    var r = quill.getSelection(true);
    window.pnMedia.apri({ titolo: 'Un\'immagine per l\'email', carica: 'newsletter-immagine.php', assoluto: true,
      accetta: 'image/png,image/jpeg,image/gif,image/webp',
      scegli: function (u) { quill.insertEmbed(r ? r.index : quill.getLength(), 'image', u, 'user'); } });
  }
  document.getElementById('nl-form').addEventListener('submit', function () {
    document.getElementById('corpo').value = quill.getLength() > 1 ? quill.root.innerHTML : '';
  });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
