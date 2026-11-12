<?php
/**
 * Popup: elenco e scheda. Dove compare, quando, ogni quanto, cosa mostra (titolo, testo, pulsante, modulo o form).
 * 2.39.0: "Cosa mostra" ha due modi: "Campi semplici" (com'era) e "Costruito con l'editor visuale" (un modello dell'editor
 * di tipo "popup", includes/popup.php: popup_modello_crea, popup_disegno). Larghezza, posizione e fondo per tutti e due.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/popup.php';
require_once dirname(__DIR__) . '/includes/modelli.php';
require_once dirname(__DIR__) . '/includes/editor.php'; // editor_puo_pubblicare (Chi pubblica)
require_admin(); // sicurezza (ruoli): solo amministratore
$adminTitle = 'Popup';
$id = (int)($_GET['id'] ?? 0);
$nuovo = isset($_GET['nuovo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $azione = (string)($_POST['azione'] ?? '');
    try {
        if ($azione === 'elimina') { popup_elimina($id); flash('success', 'Popup eliminato.'); header('Location: popup.php'); exit; }
        // "Apri nell'editor" salva la scheda come "Costruito con l'editor visuale", crea il disegno al primo clic e apre l'editor.
        if ($azione === 'editor') $_POST['contenuto'] = 'editor';
        $id = popup_salva($id, $_POST);
        if ($azione === 'editor') {
            header('Location: editor.php?tipo=modelli&id=' . popup_modello_crea($id));
            exit;
        }
        $p = popup_leggi($id);
        if ($p && $p['attivo'] && $p['contenuto'] === 'editor' && !popup_modello_pubblicato(popup_modello($p))) {
            flash('error', 'Popup salvato e acceso, ma sul sito non esce ancora: il disegno non è pubblicato. Premi "Apri nell\'editor" e poi Pubblica.');
        } else {
            flash('success', !empty($_POST['attivo']) ? 'Popup salvato e acceso: compare sul sito secondo le regole scelte.' : 'Popup salvato, spento.');
        }
        header('Location: popup.php?id=' . $id);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        header('Location: popup.php?' . ($id ? 'id=' . $id : 'nuovo=1'));
    }
    exit;
}
$p = $id ? popup_leggi($id) : null;
if ($id && !$p) { http_response_code(404); exit('Popup non trovato.'); }
$moduli = editor_moduli_disponibili();
require_once __DIR__ . '/includes/admin-header.php';
?>
<link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>">
<style>
/* 2.39.0: le due carte di "Cosa mostra"; i campi semplici e il riquadro dell'editor si alternano senza JavaScript. */
.pp-carte{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;margin:0 0 .4rem;border:0;padding:0}
@media (max-width:640px){.pp-carte{grid-template-columns:minmax(0,1fr)}}
.pp-carte legend{font-size:.78rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;padding:0}
.sc-corpo label.pp-carta{display:flex;gap:.6rem;align-items:flex-start;margin:0;padding:.8rem .9rem;border:2px solid var(--bordo-campo);border-radius:10px;background:var(--campo);color:var(--text);cursor:pointer;font-weight:400;font-size:.88rem;min-height:44px}
.pp-carta input{margin-top:.2rem;accent-color:var(--coral)}
.pp-carta b{display:block;font-size:.92rem}
.pp-carta span{color:var(--text-muted);font-size:.8rem}
.sc-corpo label.pp-carta:has(input:checked){border-color:var(--coral);background:var(--coral-light)}
.pp-carta:has(input:focus-visible){outline:2px solid var(--coral);outline-offset:2px}
.pp-scheda:has(#pp-c-editor:checked) .pp-campi{display:none}
.pp-scheda:has(#pp-c-campi:checked) .pp-editor{display:none}
.pp-stato-disegno{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin:.2rem 0 .7rem;font-size:.88rem}
.pp-avviso{margin:.6rem 0 0;padding:.6rem .8rem;border-radius:8px;border:1px solid var(--attenzione-bordo);background:var(--mustard-light);color:var(--attenzione-testo);font-size:.85rem}
.pp-avviso-breve{font-size:.76rem;color:var(--attenzione-testo)}
.pp-anteprima{width:100%;height:520px;border:1px solid var(--border);border-radius:10px;background:var(--fondo-2);display:block}
.pp-azioni{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
.pp-casella{display:flex !important;gap:.5rem;align-items:center;font-weight:400 !important;color:var(--text) !important;font-size:.88rem !important}
</style>
<?php if (!$p && !$nuovo): $tutti = popup_tutti(); ?>
<div class="ct-testa"><p style="color:var(--text-muted);font-size:.88rem;margin:0">Una finestra che compare sopra la pagina: dopo qualche secondo, a una certa profondità, o quando il mouse esce. Chi la chiude non la rivede per i giorni che scegli.</p><a href="popup.php?nuovo=1" class="btn btn-primary">+ Nuovo popup</a></div>
<div class="admin-table-wrap ct-tabella">
  <table>
    <thead><tr><th>Popup</th><th>Dove</th><th>Quando</th><th>Mostra</th><th>Stato</th></tr></thead>
    <tbody>
    <?php if (!$tutti): ?><tr><td colspan="5"><div class="empty-state"><p>Nessun popup ancora: crea il primo con "+ Nuovo popup".</p><a href="popup.php?nuovo=1" class="btn btn-primary">+ Nuovo popup</a></div></td></tr><?php endif; ?>
    <?php foreach ($tutti as $x): $xm = $x['contenuto'] === 'editor' ? popup_modello($x) : null; ?>
      <tr class="ct-riga">
        <td class="ct-titolo"><a class="ct-nome" href="popup.php?id=<?= $x['id'] ?>"><?= h($x['nome']) ?></a><div class="ct-azioni"><a href="popup.php?id=<?= $x['id'] ?>">Modifica</a></div></td>
        <td class="ct-muto"><?= h(POPUP_DOVE[$x['dove']] ?? $x['dove']) ?></td>
        <td class="ct-muto"><?= $x['innesco'] === 'secondi' ? 'dopo ' . (int)$x['valore'] . ' secondi' : ($x['innesco'] === 'scroll' ? 'al ' . (int)$x['valore'] . '% della pagina' : 'quando il mouse esce') ?> · ogni <?= (int)$x['frequenza_giorni'] ?: 'volta' ?><?= $x['frequenza_giorni'] ? ' giorni' : '' ?></td>
        <?php if ($x['contenuto'] === 'editor'): ?>
        <td class="ct-muto">Costruito con l'editor · <?= popup_modello_pubblicato($xm) ? 'disegno pubblicato' : 'disegno non ancora pubblicato' ?></td>
        <?php else: ?>
        <td class="ct-muto"><?= h(mb_substr($x['titolo'], 0, 50)) ?><?= $x['modulo'] ? ' + ' . h($moduli[$x['modulo']] ?? $x['modulo']) : '' ?></td>
        <?php endif; ?>
        <td><span class="badge <?= $x['attivo'] ? 'badge-published' : 'badge-draft' ?>"><?= $x['attivo'] ? 'Acceso' : 'Spento' ?></span><?= $x['attivo'] && $x['contenuto'] === 'editor' && !popup_modello_pubblicato($xm) ? ' <span class="pp-avviso-breve">non esce: disegno da pubblicare</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else:
  $v = $p ?: ['id' => 0, 'nome' => '', 'attivo' => 0, 'occhiello' => '', 'titolo' => '', 'testo' => '', 'immagine' => '', 'pulsante_testo' => '', 'pulsante_link' => '', 'modulo' => '', 'dove' => 'tutte', 'percorsi' => '', 'innesco' => 'secondi', 'valore' => 8, 'frequenza_giorni' => 7,
    'contenuto' => 'campi', 'modello_id' => 0, 'larghezza' => 'media', 'posizione' => 'centro', 'sfondo_finestra' => 1];
  $m = $p ? popup_modello($p) : null;
  $pubblicato = popup_modello_pubblicato($m);
  $modifiche = popup_modello_modifiche($m);
  $anteprima = $v['id'] ? cms_sito_base() . '/?preview=1&popup_prova=' . (int)$v['id'] : '';
?>
<form method="post" class="pp-scheda">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
  <div class="sc-griglia">
    <div>
      <div class="sc-scatola"><h3>Cosa mostra</h3><div class="sc-corpo">
        <label for="pp-nome">Nome (nel pannello)</label><input type="text" name="nome" id="pp-nome" value="<?= h($v['nome']) ?>" required>
        <fieldset class="pp-carte" style="margin-top:.8rem">
          <legend>Come lo costruisci</legend>
          <label class="pp-carta" for="pp-c-campi"><input type="radio" name="contenuto" id="pp-c-campi" value="campi" <?= $v['contenuto'] !== 'editor' ? 'checked' : '' ?>><span><b>Campi semplici</b>Titolo, testo, immagine, un pulsante e un modulo: si riempiono qui sotto.</span></label>
          <label class="pp-carta" for="pp-c-editor"><input type="radio" name="contenuto" id="pp-c-editor" value="editor" <?= $v['contenuto'] === 'editor' ? 'checked' : '' ?>><span><b>Costruito con l'editor visuale</b>Sezioni, colonne e tutti i widget, come una pagina.</span></label>
        </fieldset>
        <div class="pp-campi">
          <label for="pp-occhiello">Occhiello</label><input type="text" name="occhiello" id="pp-occhiello" value="<?= h($v['occhiello']) ?>">
          <label for="pp-titolo">Titolo</label><input type="text" name="titolo" id="pp-titolo" value="<?= h($v['titolo']) ?>">
          <label for="pp-testo">Testo (HTML semplice: paragrafi, grassetto, link)</label><textarea name="testo" id="pp-testo" rows="5"><?= h($v['testo']) ?></textarea>
          <label for="pp-immagine">Immagine</label><input type="text" name="immagine" id="pp-immagine" value="<?= h($v['immagine']) ?>" placeholder="<?= h(cms_uploads_url()) ?>/…" data-immagine>
          <div class="sc-riga"><div><label for="pp-pulsante">Testo del pulsante</label><input type="text" name="pulsante_testo" id="pp-pulsante" value="<?= h($v['pulsante_testo']) ?>"></div><div><label for="pp-link">Link del pulsante</label><input type="text" name="pulsante_link" id="pp-link" value="<?= h($v['pulsante_link']) ?>" placeholder="per esempio /contatti/" data-link-sito></div></div>
          <p class="hint">Con il link <b>#chiudi</b> il pulsante chiude il popup, come la X.</p>
          <label for="pp-modulo">Modulo dentro il popup</label>
          <select name="modulo" id="pp-modulo"><option value="">Nessuno</option><?php foreach ($moduli as $k => $n): ?><option value="<?= h($k) ?>" <?= $v['modulo'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
        </div>
        <div class="pp-editor" id="pp-editor">
          <div class="pp-stato-disegno" id="pp-stato-disegno">
            <b>Il disegno:</b>
            <?php if (!$m): ?><span class="badge badge-draft">Non ancora creato</span>
            <?php elseif ($pubblicato): ?><span class="badge badge-published">Pubblicato</span>
            <?php else: ?><span class="badge badge-draft">Bozza, non pubblicato</span><?php endif; ?>
            <?php if ($m && $pubblicato && $modifiche): ?><span class="pp-avviso-breve">Ci sono modifiche non pubblicate</span><?php endif; ?>
          </div>
          <p class="hint" style="margin:0 0 .7rem">Lo componi nell'editor visuale, come una pagina: titolo, testo, foto, pulsanti, un form. La X per chiudere c'è sempre. Un pulsante con il link <b>#chiudi</b> chiude il popup. <?= $m ? '' : 'Al primo clic il disegno nasce con un titolo, un testo e un pulsante' . ($v['titolo'] !== '' ? ' presi dai campi semplici' : '') . ', da cambiare.' ?></p>
          <div class="pp-azioni">
            <button type="submit" class="btn btn-primary" name="azione" value="editor" id="pp-apri-editor">Apri nell'editor</button>
            <?php if ($m): ?><a class="btn btn-secondary" href="versioni.php?ambito=modello&amp;oggetto=<?= (int)$m['id'] ?>">Versioni del disegno</a><?php endif; ?>
          </div>
          <?php if ($m && !$pubblicato): ?><p class="pp-avviso" id="pp-avviso-pubblica" role="note">Finché il disegno non è pubblicato il popup <b>non esce sul sito</b>, anche se è acceso. Apri nell'editor e premi <b>Pubblica</b>.</p><?php endif; ?>
          <?php if (!editor_puo_pubblicare()): ?><p class="pp-avviso" role="note">Il disegno lo pubblica solo chi ha il permesso (Editor &gt; Impostazioni dell'editor, "Chi pubblica").</p><?php endif; ?>
        </div>
      </div></div>
      <?php if ($anteprima !== '' && ($v['contenuto'] === 'campi' || $m)): ?>
      <div class="sc-scatola"><h3>Anteprima</h3><div class="sc-corpo">
        <p class="hint" style="margin:0 0 .6rem">Il popup aperto sulla home com'è salvato adesso<?= $v['contenuto'] === 'editor' ? ', con le modifiche del disegno non ancora pubblicate' : '' ?>. Chi visita il sito non vede questa anteprima.</p>
        <iframe class="pp-anteprima" id="pp-anteprima" title="Anteprima del popup sulla home" loading="lazy" src="<?= h($anteprima) ?>"></iframe>
        <p style="margin:.6rem 0 0"><a href="<?= h($anteprima) ?>" target="_blank" rel="noopener">Apri l'anteprima in una scheda nuova</a></p>
      </div></div>
      <?php endif; ?>
    </div>
    <aside>
      <div class="sc-scatola"><h3>Stato</h3><div class="sc-corpo">
        <label class="pp-casella"><input type="checkbox" name="attivo" value="1" <?= $v['attivo'] ? 'checked' : '' ?>> Acceso</label>
        <?php if ($v['attivo'] && $v['contenuto'] === 'editor' && !$pubblicato): ?><p class="pp-avviso" role="note">Acceso, ma non esce sul sito: il disegno non è pubblicato.</p><?php endif; ?>
        <div style="display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap"><button type="submit" class="btn btn-primary">Salva</button><a class="btn btn-secondary" href="popup.php">Elenco</a></div>
        <?php if ($v['id']): ?><div style="margin-top:.6rem;text-align:right"><button type="submit" class="sc-cestina" name="azione" value="elimina" data-conferma="Eliminare il popup<?= $m ? ' e il suo disegno' : '' ?>?" data-conferma-pericolo data-conferma-ok="Elimina il popup">Elimina</button></div><?php endif; ?>
      </div></div>
      <div class="sc-scatola"><h3>Dove e quando</h3><div class="sc-corpo">
        <label for="pp-dove">Su quali pagine</label>
        <select name="dove" id="pp-dove"><?php foreach (POPUP_DOVE as $k => $n): ?><option value="<?= $k ?>" <?= $v['dove'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
        <label for="pp-percorsi">Indirizzi (uno per riga, solo per "indirizzi elencati")</label><textarea name="percorsi" id="pp-percorsi" rows="3" placeholder="/contatti/&#10;/servizi/" data-link-sito="riga"><?= h($v['percorsi']) ?></textarea>
        <label for="pp-innesco">Quando compare</label>
        <select name="innesco" id="pp-innesco"><?php foreach (POPUP_INNESCHI as $k => $n): ?><option value="<?= $k ?>" <?= $v['innesco'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
        <label for="pp-valore">Secondi, oppure percentuale della pagina</label><input type="number" name="valore" id="pp-valore" value="<?= (int)$v['valore'] ?>" min="0" max="600">
        <label for="pp-giorni">Ogni quanti giorni la stessa persona lo rivede (0 = sempre)</label><input type="number" name="frequenza_giorni" id="pp-giorni" value="<?= (int)$v['frequenza_giorni'] ?>" min="0" max="365">
      </div></div>
      <div class="sc-scatola"><h3>Aspetto della finestra</h3><div class="sc-corpo">
        <label for="pp-larghezza">Larghezza</label>
        <select name="larghezza" id="pp-larghezza"><?php foreach (POPUP_LARGHEZZE as $k => $n): ?><option value="<?= h($k) ?>" <?= $v['larghezza'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
        <label for="pp-posizione">Posizione</label>
        <select name="posizione" id="pp-posizione"><?php foreach (POPUP_POSIZIONI as $k => $n): ?><option value="<?= h($k) ?>" <?= $v['posizione'] === $k ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
        <p class="hint">Le barre sono larghe quanto lo schermo. Al telefono la finestra non va mai oltre lo schermo.</p>
        <input type="hidden" name="sfondo_finestra" value="0">
        <label class="pp-casella" style="margin-top:.7rem"><input type="checkbox" name="sfondo_finestra" value="1" <?= $v['sfondo_finestra'] ? 'checked' : '' ?>> Finestra con il fondo, il bordo e il margine</label>
        <p class="hint">Togli la spunta se il disegno ha già il suo fondo: allora decide tutto il disegno.</p>
      </div></div>
    </aside>
  </div>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
