<?php
/**
 * 2.38.0: la scheda di un'immagine della libreria (media.php?immagine=<indirizzo>). La include admin/media.php, che salva
 * (azione dati_salva) e rinomina (azione dati_rinomina). Motore in includes/media-dati.php. Campi: alt con il conteggio,
 * titolo, didascalia, descrizione, autore, licenza, pagina della licenza, fonte; in sola lettura dimensioni, peso, formato,
 * data e dove è usata. Anche il redattore (la libreria è sua). Niente gestori in linea: il conteggio sta in assets/media-scheda.js.
 * Variabili con il prefisso $_sk perché il file sta nello spazio di media.php.
 */
require_once dirname(__DIR__, 2) . '/includes/media-dati.php';
$_skVoce = media_dati_voce((string)($_GET['immagine'] ?? ''));
if (!$_skVoce) { flash('error', 'Questa immagine non sta nella libreria dei Media: scegline una qui sotto.'); header('Location: media.php'); exit; }
$_skDati = media_dati_di($_skVoce['url'], true);
$_skErrori = $_SESSION['media_scheda_errori'] ?? [];
$_skInviati = $_SESSION['media_scheda_inviati'] ?? null;
unset($_SESSION['media_scheda_errori'], $_SESSION['media_scheda_inviati']);
if (is_array($_skInviati)) $_skDati = array_replace($_skDati, array_intersect_key($_skInviati, $_skDati));
$_skInfo = media_dati_file_info($_skVoce['url']);
$_skUsata = media_dati_usata($_skVoce['url']);
$_skLicenze = media_dati_licenze();
$adminTitle = 'Scheda dell\'immagine';
$_skCampo = function (string $nome, string $etichetta, string $aiuto, string $html) use ($_skErrori): void {
    $err = $_skErrori[$nome] ?? '';
    echo '<div class="form-group ms-campo' . ($err !== '' ? ' ms-sbagliato' : '') . '"><label for="ms-' . $nome . '">' . $etichetta . '</label>'
        . str_replace('{attr}', 'id="ms-' . $nome . '" name="' . $nome . '" aria-describedby="ms-' . $nome . '-aiuto' . ($err !== '' ? ' ms-' . $nome . '-errore" aria-invalid="true' : '') . '"', $html)
        . ($err !== '' ? '<div class="ms-errore" id="ms-' . $nome . '-errore" role="alert">' . h($err) . '</div>' : '')
        . '<div class="hint" id="ms-' . $nome . '-aiuto">' . $aiuto . '</div></div>';
};
$_skPeso = $_skInfo['peso'] >= 1048576 ? number_format($_skInfo['peso'] / 1048576, 1, ',', '.') . ' MB' : max(1, (int)round($_skInfo['peso'] / 1024)) . ' KB';
require_once __DIR__ . '/admin-header.php';
?>
<style>
.ms-colonna{min-width:0;display:grid;gap:1.2rem;align-content:start}
.ms-griglia{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:1.2rem;align-items:start}
.ms-foto{background:var(--bg);border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;justify-content:center;aspect-ratio:4/3;overflow:hidden}
.ms-foto img{max-width:100%;max-height:100%;object-fit:contain}
.ms-dati{list-style:none;margin:.8rem 0 0;padding:0;font-size:.86rem;display:grid;gap:.25rem}
.ms-dati li{display:flex;justify-content:space-between;gap:.6rem;border-bottom:1px solid var(--border);padding:.25rem 0}
.ms-dati li span:first-child{color:var(--text-muted)}
.ms-dati li span:last-child{overflow-wrap:anywhere;text-align:right}
.ms-campo input[type=text],.ms-campo input[type=url],.ms-campo select,.ms-campo textarea{width:100%}
.ms-campo textarea{min-height:6.5rem;height:auto;font:inherit;line-height:1.5;resize:vertical}
.ms-sotto{margin:1.2rem 0 .2rem;padding-top:.8rem;border-top:1px solid var(--border);font-size:.82rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)}
.ms-conta{font-size:.78rem;color:var(--text-muted);margin-top:.2rem}
.ms-conta.ms-fuori{color:var(--attenzione-testo,var(--text))}
.ms-sbagliato input,.ms-sbagliato select,.ms-sbagliato textarea{border:2px solid var(--errore,#b00020)}
.ms-errore{color:var(--errore-testo);font-size:.82rem;margin-top:.2rem}
.ms-due{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:0 1rem}
.ms-usata{list-style:none;padding:0;margin:.4rem 0 0;display:grid;gap:.35rem;font-size:.88rem}
.ms-usata li{overflow-wrap:anywhere}
.ms-forse{display:inline-block;font-size:.72rem;padding:.05rem .4rem;border-radius:6px;border:1px solid var(--bordo-campo);margin-left:.3rem}
.ms-azioni{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center}
[hidden]{display:none!important}
@media (max-width:760px){.ms-griglia,.ms-due{grid-template-columns:minmax(0,1fr)}}
</style>
<nav class="imp-schede" aria-label="Schede dei Media">
  <a href="media.php">Libreria</a><a href="media.php?scheda=dati">Dati delle immagini</a>
</nav>
<p><a href="media.php">&larr; Torna alla libreria</a></p>
<?php if ($_skErrori): ?><div class="flash flash-error" role="alert"><?= count($_skErrori) === 1 ? 'Un campo da sistemare: lo trovi segnato in rosso qui sotto.' : count($_skErrori) . ' campi da sistemare: li trovi segnati in rosso qui sotto.' ?></div><?php endif; ?>
<div class="ms-griglia">
  <div class="ms-colonna">
  <section class="form-card ms-lato" aria-labelledby="ms-t-foto">
    <h3 id="ms-t-foto"><?= h($_skVoce['nome']) ?></h3>
    <div class="ms-foto"><img src="<?= h($_skVoce['url']) ?>" alt=""></div>
    <ul class="ms-dati" aria-label="Dati del file">
      <li><span>Dimensioni</span><span><?= $_skInfo['larghezza'] ? (int)$_skInfo['larghezza'] . ' &times; ' . (int)$_skInfo['altezza'] . ' pixel' : 'non lette' ?></span></li>
      <li><span>Peso</span><span><?= h($_skPeso) ?><?= $_skInfo['peso'] > MEDIA_DATI_PESANTE && !$_skVoce['svg'] ? ' (pesante: oltre 500 KB)' : '' ?></span></li>
      <li><span>Formato</span><span><?= h($_skInfo['formato']) ?></span></li>
      <li><span>Caricata</span><span><?= $_skInfo['data'] ? h(date('d/m/Y H:i', $_skInfo['data'])) : '' ?></span></li>
      <li><span>Cartella</span><span><?= h(media_cartelle()[$_skVoce['cartella']] ?? $_skVoce['cartella']) ?><?= $_skVoce['sotto'] !== '' ? ' &middot; ' . h($_skVoce['sotto']) : '' ?></span></li>
      <li><span>Indirizzo</span><span><input type="text" value="<?= h($_skVoce['url']) ?>" readonly aria-label="Indirizzo dell'immagine" data-copia-se-stesso style="width:100%;font-size:.75rem"></span></li>
      <?php if ($_skDati['origine'] !== ''): ?><li><span>Arrivata da</span><span>importata dall'AI collegata (<?= h($_skDati['origine']) ?>)</span></li><?php endif; ?>
    </ul>
  </section>

<section class="form-card" id="ms-usata" aria-labelledby="ms-t-usata">
    <h3 id="ms-t-usata">Dove è usata</h3>
    <?php if (!$_skUsata): ?>
    <div class="empty-state">Non la trovo in nessuna pagina, articolo, modello, form, popup o newsletter. Puoi usarla dall'editor, con Scegli dai Media.</div>
    <?php else: ?>
    <?php if (array_filter($_skUsata, fn($_x) => !$_x['certo'])): ?><p class="hint">"Forse" vuol dire che il nome del file compare, ma non nella versione pubblicata (una bozza, un modello spento) o in un punto che non so leggere.</p><?php endif; ?>
    <ul class="ms-usata">
      <?php foreach ($_skUsata as $_u): ?>
      <li><b><?= h($_u['dove']) ?></b>: <?= $_u['link'] !== '' ? '<a href="' . h($_u['link']) . '">' . h($_u['titolo'] !== '' ? $_u['titolo'] : 'apri') . '</a>' : h($_u['titolo']) ?><?php if (!$_u['certo']): ?><span class="ms-forse">forse</span><?php endif; ?><?= $_u['nota'] !== '' ? ' <span class="hint">(' . h($_u['nota']) . ')</span>' : '' ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if (in_array($_skVoce['cartella'], ['uploads', 'pagine', 'contenuti'], true)): ?>
    <details class="pn-aiuto" id="ms-rinomina"><summary>Rinomina il file</summary>
      <?php if ($_skUsata): ?>
      <p class="hint">Non si può: la foto è usata (o forse usata) qui sopra, e cambiare il nome del file romperebbe quei collegamenti. Il nome del file conta poco per Google: conta molto di più l'alt.</p>
      <?php else: ?>
      <p class="hint">La foto non è usata da nessuna parte: puoi darle un nome leggibile, preso dal titolo (per esempio <code>vetrina-di-sera-a1b2c3.jpg</code>). Scrivi e salva prima il titolo.</p>
      <form method="post" data-conferma="Rinominare il file con il nome preso dal titolo? Se qualcuno ha copiato l'indirizzo di prima fuori dal sito, quel link non funzionerà più." data-conferma-ok="Rinomina il file">
        <?= csrf_field() ?><input type="hidden" name="azione" value="dati_rinomina"><input type="hidden" name="url" value="<?= h($_skVoce['url']) ?>">
        <button class="btn btn-secondary btn-sm" type="submit"<?= $_skDati['titolo'] === '' ? ' disabled aria-describedby="ms-rin-perche"' : '' ?>>Rinomina il file dal titolo</button>
        <?php if ($_skDati['titolo'] === ''): ?><span class="hint" id="ms-rin-perche">Si accende quando la foto ha un titolo salvato.</span><?php endif; ?>
      </form>
      <?php endif; ?>
    </details>
    <?php endif; ?>
  </section>
  </div>

  <form method="post" class="form-card ms-modulo" id="ms-modulo" aria-labelledby="ms-t-dati">
    <h3 id="ms-t-dati">I dati della foto</h3>
    <?= csrf_field() ?><input type="hidden" name="azione" value="dati_salva"><input type="hidden" name="url" value="<?= h($_skVoce['url']) ?>">
    <?php
    $_skCampo('alt', 'Testo alternativo (alt)', 'Cosa si vede, per chi non vede la foto e per Google. Consigliati da ' . MEDIA_DATI_ALT_CONSIGLIATO[0] . ' a ' . MEDIA_DATI_ALT_CONSIGLIATO[1] . ' caratteri; non cominciare con "immagine di" o "foto di". Vale in tutte le pagine che usano la foto senza un alt loro.',
        '<input type="text" {attr} maxlength="' . MEDIA_ALT_MAX . '" value="' . h($_skDati['alt']) . '" placeholder="per esempio: La vetrina del negozio in via Rizzoli, illuminata di sera" data-ms-conta="' . MEDIA_DATI_ALT_CONSIGLIATO[0] . ',' . MEDIA_DATI_ALT_CONSIGLIATO[1] . '"><div class="ms-conta" aria-live="polite" data-ms-conta-per="ms-alt"></div>');
    $_skCampo('titolo', 'Titolo', 'Un nome leggibile della foto: lo vedi nella libreria e lo leggono i dati per Google. Non compare sul sito come attributo title.',
        '<input type="text" {attr} maxlength="' . MEDIA_DATI_TESTI['titolo'] . '" value="' . h($_skDati['titolo']) . '" placeholder="per esempio: Vetrina di sera">');
    $_skCampo('didascalia', 'Didascalia', 'Il testo sotto la foto: esce nei widget Immagine e Galleria solo se nel widget accendi "Mostra la didascalia della libreria" e lì la didascalia è vuota.',
        '<input type="text" {attr} maxlength="' . MEDIA_DATI_TESTI['didascalia'] . '" value="' . h($_skDati['didascalia']) . '" placeholder="per esempio: Il negozio dopo il rinnovo del 2026">');
    $_skCampo('descrizione', 'Descrizione', 'Più lunga dell\'alt: cosa mostra la foto, il contesto, chi c\'è. Serve a chi usa un lettore di schermo e a Google (dati dell\'immagine principale della pagina).',
        '<textarea {attr} rows="4" maxlength="' . MEDIA_DATI_TESTI['descrizione'] . '" placeholder="per esempio: La vetrina con le novità di stagione, fotografata dalla strada">' . h($_skDati['descrizione']) . '</textarea>');
    ?>
    <h4 class="ms-sotto">Crediti e licenza</h4>
    <p class="hint">Chi ha scattato la foto e a quali condizioni la puoi usare. Per le foto importate dall'AI sono già scritti: controllali. Google li mostra nelle immagini con la licenza.</p>
    <div class="ms-due">
    <?php
    $_skCampo('autore', 'Autore o crediti', 'Il nome di chi l\'ha scattata (o dello studio). Vuoto se la foto è tua e non vuoi indicarlo.',
        '<input type="text" {attr} maxlength="' . MEDIA_DATI_TESTI['autore'] . '" value="' . h($_skDati['autore']) . '" placeholder="per esempio: Giulia Rossi">');
    $_skCampo('autore_tipo', 'L\'autore è', 'Una persona o un\'organizzazione (studio, agenzia, archivio).',
        '<select {attr}><option value="persona"' . ($_skDati['autore_tipo'] !== 'organizzazione' ? ' selected' : '') . '>Una persona</option><option value="organizzazione"' . ($_skDati['autore_tipo'] === 'organizzazione' ? ' selected' : '') . '>Un\'organizzazione</option></select>');
    $_skOpz = '';
    foreach ($_skLicenze as $k => [$et]) $_skOpz .= '<option value="' . h($k) . '"' . ($_skDati['licenza'] === $k ? ' selected' : '') . '>' . h($et) . '</option>';
    $_skCampo('licenza', 'Licenza', 'Se non la sai, lascia "Non indicata": meglio niente che una licenza sbagliata.', '<select {attr} data-ms-licenza>' . $_skOpz . '</select>');
    $_skCampo('licenza_nome', 'Nome della licenza', 'Per "Licenza dell\'archivio" o "Altra licenza": per esempio "Licenza dell\'archivio fotografico".',
        '<input type="text" {attr} maxlength="' . MEDIA_DATI_TESTI['licenza_nome'] . '" value="' . h($_skDati['licenza_nome']) . '" placeholder="per esempio: Licenza dell\'archivio">');
    ?>
    </div>
    <?php
    $_skCampo('licenza_url', 'Pagina della licenza', 'L\'indirizzo della pagina che spiega la licenza. Per le licenze CC, se lo lasci vuoto, usiamo la pagina ufficiale.',
        '<input type="url" {attr} maxlength="500" value="' . h($_skDati['licenza_url']) . '" placeholder="https://...">');
    $_skCampo('fonte_url', 'Indirizzo della fonte', 'Dove hai preso la foto (la pagina dell\'archivio o del fotografo): è anche dove si può chiedere la licenza.',
        '<input type="url" {attr} maxlength="500" value="' . h($_skDati['fonte_url']) . '" placeholder="https://...">');
    ?>
    <div class="ms-azioni"><button class="btn btn-primary" type="submit">Salva i dati della foto</button><span class="hint">Testo semplice: eventuali tag HTML vengono tolti.</span></div>
  </form>
</div>


<script src="<?= pannello_file('assets/media-scheda.js') ?>" defer></script>
<?php require_once __DIR__ . '/admin-footer.php'; ?>
