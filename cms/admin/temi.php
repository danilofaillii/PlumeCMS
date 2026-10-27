<?php
/**
 * Temi (gruppo Editor, solo amministratore, 27 ottobre 2026): i temi del sito in schede, con anteprima, attiva e
 * personalizza; l'aspetto di base (nessun tema); l'avviso quando il tema attivo si è rotto. Motore in includes/temi.php.
 * Questa pagina legge solo i JSON dei temi: il PHP di un tema non gira mai nel pannello.
 *
 *   POST azione=attiva tema=<nome>     applica stile, header e footer del tema e lo rende attivo
 *   POST azione=base                   nessun tema: il sito usa sito.css e lo Stile del sito com'è
 *   POST azione=carica file sostituisci  installa uno zip di tema (con PHP: firmato o finestra di Sicurezza aperta)
 *   POST azione=elimina tema=<nome>    toglie un tema non in uso (copia in aggiornamenti/temi-precedenti)
 *   GET  scarica=<nome>                lo zip di un tema;  GET scarica=attuale  l'aspetto di adesso come tema
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/temi.php';
require_admin(); // sicurezza (ruoli): solo amministratore
$adminTitle = 'Temi';
$autore = (string)(current_user()['username'] ?? '');

// Scaricare: lo zip di un tema, oppure l'aspetto di adesso come tema nuovo. Il file provvisorio si cancella dopo l'invio.
if (isset($_GET['scarica'])) {
    $s = (string)$_GET['scarica'];
    try {
        if ($s === 'attuale') { $f = temi_zip_aspetto_attuale(); $nome = 'tema-il-mio-aspetto-' . date('Y-m-d') . '.zip'; }
        else {
            $t = temi_leggi($s);
            if (!$t) throw new RuntimeException('Tema non trovato.');
            $f = temi_zip_crea($s); $nome = 'tema-' . $s . '-' . preg_replace('/[^0-9A-Za-z.]+/', '-', $t['versione']) . '.zip';
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        header('Location: temi.php');
        exit;
    }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . filesize($f));
    header('Cache-Control: no-store');
    readfile($f);
    @unlink($f);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        switch ($_POST['azione'] ?? '') {
            case 'attiva':
                $slug = (string)($_POST['tema'] ?? '');
                $t = temi_leggi($slug);
                if (!$t) throw new RuntimeException('Tema non trovato: la cartella temi/' . $slug . ' non c\'è o il suo tema.json non si legge.');
                temi_attiva($slug, $autore);
                flash('success', 'Tema "' . $t['nome'] . '" attivo: il sito ha i suoi colori, caratteri e pulsanti'
                    . ($t['modelli'] ? ', il suo header e il suo footer' : '') . '. Lo stile di prima è in Versioni'
                    . ($t['modelli'] ? '; header e footer di prima restano in Header e footer' : '') . '. Ogni dettaglio si ritocca da Stile del sito.');
                break;
            case 'base':
                temi_disattiva();
                flash('success', 'Nessun tema: il sito usa l\'aspetto di base con lo Stile del sito di adesso. Header e footer restano quelli predefiniti in Header e footer.');
                break;
            case 'carica':
                $f = $_FILES['file'] ?? null;
                $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('Lo zip supera la dimensione che il server accetta (' . ini_get('upload_max_filesize') . '). Si alza da cPanel, voce Select PHP Version, Options: upload_max_filesize.');
                if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)($f['tmp_name'] ?? ''))) throw new RuntimeException('Nessun file ricevuto: scegli lo zip del tema e riprova.');
                if (strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) !== 'zip') throw new RuntimeException('Il file deve essere uno zip (.zip).');
                $r = temi_installa_zip((string)$f['tmp_name'], !empty($_POST['sostituisci']), $autore);
                flash('success', implode(' ', $r['righe']) . ' Per vederlo sul sito: Anteprima, poi Attiva.');
                break;
            case 'elimina':
                $copia = temi_elimina((string)($_POST['tema'] ?? ''), $autore);
                flash('success', 'Tema tolto dalla cartella temi/. Una copia resta in aggiornamenti/temi-precedenti/' . $copia . ' (dal pannello non si vede; per riaverlo serve lo zip, oppure chi gestisce il server la rimette in temi/).');
                break;
            default:
                throw new RuntimeException('Azione sconosciuta.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: temi.php');
    exit;
}

temi_proteggi(); // temi/.htaccess: dal web nella cartella dei temi non si esegue niente
$temi = temi_elenco();
$scelto = temi_scelto();
$guasto = temi_guasto_info();
$sito = rtrim(cms_url_sito(), '/');
$anteprima = fn(string $slug) => $sito . '/?preview=1&tema=' . rawurlencode($slug);
$csrf = csrf_token();

/** I colori di un tema per la scheda: sfondo, testo, principale, secondario (dallo stile del tema, altrimenti i predefiniti). */
$colori = function (array $stile): array {
    $p = design_predefiniti()['colori']['sistema'];
    $s = (array)($stile['colori']['sistema'] ?? []);
    $ok = fn($k) => isset($s[$k]) && is_string($s[$k]) && design_colore_valido($s[$k], false) && $s[$k] !== '' ? $s[$k] : $p[$k];
    return ['sfondo' => $ok('sfondo'), 'testo' => $ok('testo'), 'primario' => $ok('primario'), 'secondario' => $ok('secondario')];
};
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.tt-intro{margin:0 0 1rem;font-size:.9rem;color:var(--text-muted);max-width:820px}
.tt-guasto{background:#fdecea;border:1px solid #f5c2bd;border-radius:var(--radius);padding:1rem 1.1rem;margin:0 0 1.2rem;color:#7a1f15}
.tt-guasto h2{margin:0 0 .4rem;font-size:1.05rem}
.tt-guasto p{margin:.3rem 0;font-size:.88rem}
.tt-guasto code{font-size:.8rem;background:rgba(255,255,255,.6);padding:.1rem .3rem;border-radius:4px;word-break:break-word}
.tt-guasto .tt-azioni{margin-top:.7rem}
.tt-griglia{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem}
.tt-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;display:flex;flex-direction:column}
.tt-card.attivo{border-color:var(--sage, #6b8f71);box-shadow:0 0 0 2px var(--sage-light, #dfe9e0)}
.tt-immagine{aspect-ratio:16/10;background:#f3f1ee;display:block;width:100%;object-fit:cover;border-bottom:1px solid var(--border)}
.tt-finto{aspect-ratio:16/10;padding:14px 16px;display:flex;flex-direction:column;gap:9px;border-bottom:1px solid var(--border);overflow:hidden}
.tt-finto .tt-barra{display:flex;justify-content:space-between;align-items:center;font:700 11px/1 system-ui,sans-serif;letter-spacing:.06em;opacity:.9}
.tt-finto .tt-barra i{display:inline-block;width:26px;height:5px;border-radius:3px;margin-left:5px;opacity:.5}
.tt-finto .tt-titolo{font:700 20px/1.1 system-ui,sans-serif;margin-top:8px}
.tt-finto .tt-riga{height:6px;border-radius:3px;opacity:.35;width:80%}
.tt-finto .tt-riga.corta{width:55%}
.tt-finto .tt-pulsanti{display:flex;gap:8px;margin-top:auto}
.tt-finto .tt-pulsanti span{font:700 10px/1 system-ui,sans-serif;padding:8px 12px;border-radius:7px;letter-spacing:.05em}
.tt-corpo{padding:.9rem 1.1rem 1rem;display:flex;flex-direction:column;gap:.5rem;flex:1}
.tt-corpo h3{margin:0;font-size:1.02rem;display:flex;gap:.4rem;align-items:center;flex-wrap:wrap}
.tt-corpo h3 .badge{font-size:.68rem}
.tt-meta{font-size:.78rem;color:var(--text-muted);margin:0}
.tt-desc{font-size:.86rem;margin:0;color:var(--text)}
.tt-errori{font-size:.8rem;color:#a12a1c;margin:0}
.tt-ig{display:flex;align-items:center;gap:5px;font-size:.76rem;color:var(--text-muted);margin:0}
.tt-ig i{display:inline-block;width:14px;height:14px;border-radius:50%;border:1px solid rgba(0,0,0,.12)}
.tt-azioni{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:auto;padding-top:.4rem}
.tt-azioni form{display:inline}
.tt-barra{display:flex;gap:.5rem;flex-wrap:wrap;margin:0 0 .8rem}
.tt-carica{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;margin-top:1.4rem;font-size:.88rem}
.tt-carica h2{margin:0 0 .4rem;font-size:1.05rem}
.tt-carica p{margin:.3rem 0;color:var(--text-muted)}
.tt-carica-riga{display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;margin-top:.6rem}
.tt-carica-riga label{display:flex;gap:.35rem;align-items:center;font-size:.85rem}
.tt-dove{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;margin-top:1.4rem;font-size:.86rem;color:var(--text-muted)}
.tt-dove p{margin:.3rem 0}
</style>

<div class="tt-barra"><a class="btn btn-secondary btn-sm" href="temi.php?scarica=attuale">Scarica l'aspetto di adesso come tema</a><a class="btn btn-secondary btn-sm" href="#carica">Carica un tema</a></div>
<p class="tt-intro">Un tema cambia l'aspetto del sito in un colpo solo: colori, caratteri, pulsanti, sfondo, header e footer. Pagine, articoli, menu e immagini non cambiano. Prima di attivarlo guardalo con <b>Anteprima</b>: si apre il sito vero con quel tema, solo per te. Dopo, ogni dettaglio si ritocca da <b>Stile del sito</b> e da <b>Header e footer</b>.</p>

<?php if ($guasto): $tg = $temi[$guasto['slug']] ?? null; ?>
<div class="tt-guasto" role="alert">
  <h2>Il tema "<?= h($tg['nome'] ?? $guasto['slug']) ?>" si è rotto</h2>
  <p>Il <?= h(date('d/m/Y \a\l\l\e H:i', strtotime((string)$guasto['quando']) ?: time())) ?> il suo codice PHP ha dato un errore. Per non lasciare il sito in panne, da quel momento <b>il sito usa l'aspetto di base</b>: chi lo visita lo vede, con i colori dello Stile del sito.</p>
  <p>L'errore, per chi ha scritto il tema: <code><?= h((string)$guasto['errore']) ?></code></p>
  <p>Cosa fare: se il tema è stato corretto, premi <b>Riprova ad attivarlo</b>. Altrimenti scegli un altro tema qui sotto, oppure resta sull'aspetto di base.</p>
  <div class="tt-azioni">
    <?php if ($tg): ?>
    <form method="post"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="attiva"><input type="hidden" name="tema" value="<?= h($tg['slug']) ?>"><button class="btn btn-primary btn-sm">Riprova ad attivarlo</button></form>
    <a class="btn btn-secondary btn-sm" href="<?= h($anteprima($tg['slug'])) ?>" target="_blank" rel="noopener">Anteprima</a>
    <?php endif; ?>
    <form method="post"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="base"><button class="btn btn-secondary btn-sm">Resta sull'aspetto di base</button></form>
  </div>
</div>
<?php endif; ?>

<div class="tt-griglia">
<?php
// La prima scheda: nessun tema.
$cb = $colori([]);
$baseAttivo = $scelto === '';
// Con il tema scelto guasto il sito sta usando l'aspetto di base: la scheda lo dice.
$baseAlPosto = !$baseAttivo && $guasto && $guasto['slug'] === $scelto;
?>
  <div class="tt-card<?= $baseAttivo || $baseAlPosto ? ' attivo' : '' ?>" id="tema-base">
    <div class="tt-finto" style="background:<?= h($cb['sfondo']) ?>;color:<?= h($cb['testo']) ?>" aria-hidden="true">
      <div class="tt-barra"><span><?= h(mb_strtoupper(design_sito()['nome'])) ?></span><span><i style="background:<?= h($cb['testo']) ?>"></i><i style="background:<?= h($cb['testo']) ?>"></i></span></div>
      <div class="tt-titolo">Aspetto di base</div><div class="tt-riga" style="background:<?= h($cb['testo']) ?>"></div><div class="tt-riga corta" style="background:<?= h($cb['testo']) ?>"></div>
      <div class="tt-pulsanti"><span style="background:<?= h($cb['primario']) ?>;color:#fff">PULSANTE</span><span style="border:1px solid <?= h($cb['secondario']) ?>;color:<?= h($cb['secondario']) ?>">SECONDO</span></div>
    </div>
    <div class="tt-corpo">
      <h3>Aspetto di base<?= $baseAttivo ? ' <span class="badge badge-published">attivo</span>' : '' ?><?= $baseAlPosto ? ' <span class="badge badge-scheduled">in uso adesso</span>' : '' ?></h3>
      <p class="tt-meta">Nessun tema</p>
      <p class="tt-desc">Il sito con il foglio di stile del CMS e lo Stile del sito così com'è. Lo stesso aspetto che il sito usa da solo se un tema si rompe.</p>
      <div class="tt-azioni">
        <a class="btn btn-secondary btn-sm" href="<?= h($anteprima('base')) ?>" target="_blank" rel="noopener">Anteprima</a>
        <?php if (!$baseAttivo): ?>
        <form method="post" data-conferma="Tornare all'aspetto di base? Il tema si spegne: lo Stile del sito resta quello di adesso, header e footer restano quelli predefiniti. Contenuti e menu non cambiano."><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="base"><button class="btn btn-primary btn-sm">Usa l'aspetto di base</button></form>
        <?php else: ?>
        <a class="btn btn-secondary btn-sm" href="design.php">Personalizza</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php foreach ($temi as $slug => $t):
    $attivo = $scelto === $slug;
    $rotto = $guasto && $guasto['slug'] === $slug;
    $c = $colori($t['stile']);
    $conferma = 'Attivare il tema "' . $t['nome'] . '"? Cambiano colori, caratteri, pulsanti e sfondo'
        . ($t['modelli'] ? '; header e footer del tema diventano i predefiniti (quelli di adesso restano in Header e footer)' : '')
        . '. Pagine, articoli, menu e immagini non cambiano. Lo stile di adesso resta nelle Versioni.'
        . ($t['php'] ? ' Questo tema contiene codice PHP: attivalo solo se viene da una fonte di cui ti fidi.' : '');
?>
  <div class="tt-card<?= $attivo && !$rotto ? ' attivo' : '' ?>" id="tema-<?= h($slug) ?>">
    <?php if ($t['anteprima'] !== ''): ?>
    <img class="tt-immagine" src="<?= h($t['anteprima']) ?>" alt="Anteprima del tema <?= h($t['nome']) ?>" loading="lazy">
    <?php else: ?>
    <div class="tt-finto" style="background:<?= h($c['sfondo']) ?>;color:<?= h($c['testo']) ?>" aria-hidden="true">
      <div class="tt-barra"><span><?= h(mb_strtoupper(design_sito()['nome'])) ?></span><span><i style="background:<?= h($c['testo']) ?>"></i><i style="background:<?= h($c['testo']) ?>"></i></span></div>
      <div class="tt-titolo"><?= h($t['nome']) ?></div><div class="tt-riga" style="background:<?= h($c['testo']) ?>"></div><div class="tt-riga corta" style="background:<?= h($c['testo']) ?>"></div>
      <div class="tt-pulsanti"><span style="background:<?= h($c['primario']) ?>;color:#fff">PULSANTE</span><span style="border:1px solid <?= h($c['secondario']) ?>;color:<?= h($c['secondario']) ?>">SECONDO</span></div>
    </div>
    <?php endif; ?>
    <div class="tt-corpo">
      <h3><?= h($t['nome']) ?>
        <?= $attivo && !$rotto ? '<span class="badge badge-published">attivo</span>' : '' ?>
        <?= $rotto ? '<span class="badge badge-pillar">guasto</span>' : '' ?>
        <?= $t['php'] ? '<span class="badge badge-cluster" title="Il tema contiene codice PHP: ' . h($t['viste'] ? 'ridisegna ' . implode(', ', $t['viste']) : 'filtri ed eventi') . '">PHP</span>' : '' ?>
      </h3>
      <p class="tt-meta">Versione <?= h($t['versione']) ?><?= $t['autore'] !== '' ? ' &middot; ' . h($t['autore']) : '' ?> &middot; cartella temi/<?= h($slug) ?></p>
      <?php if ($t['descrizione'] !== ''): ?><p class="tt-desc"><?= h($t['descrizione']) ?></p><?php endif; ?>
      <?php if ($t['infografiche']['voci']): ?><p class="tt-ig" title="I colori che il tema dà alle infografiche">Infografiche:<?php foreach ($t['infografiche']['voci'] as $k => $c): ?> <i style="background:<?= h($c) ?>" title="<?= h($k) ?>"></i><?php endforeach; ?></p><?php endif; ?>
      <?php if ($t['errori']): ?><p class="tt-errori"><?= h(implode(' ', $t['errori'])) ?></p><?php endif; ?>
      <div class="tt-azioni">
        <a class="btn btn-secondary btn-sm" href="<?= h($anteprima($slug)) ?>" target="_blank" rel="noopener">Anteprima</a>
        <?php if (!$attivo || $rotto): ?>
        <form method="post" data-conferma="<?= h($conferma) ?>"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="attiva"><input type="hidden" name="tema" value="<?= h($slug) ?>"><button class="btn btn-primary btn-sm"><?= $rotto ? 'Riprova ad attivarlo' : 'Attiva' ?></button></form>
        <?php else: ?>
        <a class="btn btn-secondary btn-sm" href="design.php">Personalizza</a>
        <?php endif; ?>
        <a class="btn btn-secondary btn-sm" href="temi.php?scarica=<?= h($slug) ?>">Scarica</a>
        <?php if (!$attivo): ?>
        <form method="post" data-conferma="<?= h('Togliere il tema "' . $t['nome'] . '" dalla cartella temi/? Il sito non cambia (non è il tema in uso). Una copia resta sul server in aggiornamenti/temi-precedenti.') ?>"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="elimina"><input type="hidden" name="tema" value="<?= h($slug) ?>"><button class="btn btn-danger btn-sm">Elimina</button></form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php
$cfgSic = [];
try { require_once dirname(__DIR__) . '/includes/sicurezza.php'; $cfgSic = sicurezza_cfg(); } catch (Throwable $e) {}
$finestra = (int)($cfgSic['root_php_fino'] ?? 0) > time();
?>
<form method="post" enctype="multipart/form-data" class="tt-carica" id="carica" data-senza-protezione>
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="carica">
  <h2>Carica un tema</h2>
  <p>Scegli lo zip del tema (quello che si scarica da qui, da un'altra installazione o da chi l'ha disegnato) e premi <b>Carica</b>. Il tema arriva nella cartella <code>temi/</code> ma <b>non si attiva da solo</b>: prima lo guardi con Anteprima.</p>
  <p>Dentro lo zip sono ammessi solo JSON, CSS, immagini (png, jpg, webp, gif, avif), caratteri, testo e PHP. <b>Un tema con PHP</b> esegue codice sul tuo server: per caricarlo apri prima per un'ora la <a href="sicurezza.php?scheda=impostazioni">finestra per i file PHP</a> (Impostazioni &gt; Sicurezza, scheda Impostazioni, riquadro in fondo: password e "Apri la finestra per un'ora"), poi torna qui. Non serve se lo zip è firmato con la chiave del sito. <?= $finestra ? '<b>La finestra adesso è aperta fino alle ' . h(date('H:i', (int)$cfgSic['root_php_fino'])) . '.</b>' : 'Adesso la finestra è chiusa.' ?></p>
  <div class="tt-carica-riga">
    <input type="file" name="file" accept=".zip,application/zip" required aria-label="Zip del tema">
    <label><input type="checkbox" name="sostituisci" value="1"> Sostituisci il tema con lo stesso nome (la versione di prima resta in una copia)</label>
    <button class="btn btn-primary btn-sm">Carica</button>
  </div>
</form>

<div class="tt-dove">
  <p><b>Dove stanno i temi.</b> Ogni tema è una cartella dentro <code>temi/</code>, nella cartella del CMS sul server. <?= $temi ? 'Qui ne vedi ' . count($temi) . '.' : 'Per ora non ce n\'è nessuno.' ?></p>
  <p>Per chi scrive un tema: com'è fatto, cosa può cambiare e cosa succede se sbaglia sta in <code>docs/temi.md</code>.</p>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
