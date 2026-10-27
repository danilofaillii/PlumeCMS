<?php
/**
 * Temi (gruppo Editor, solo amministratore, 27 ottobre 2026): i temi del sito in schede, con anteprima, attiva e
 * personalizza; l'aspetto di base (nessun tema); l'avviso quando il tema attivo si è rotto. Motore in includes/temi.php.
 * Questa pagina legge solo i JSON dei temi: il PHP di un tema non gira mai nel pannello.
 *
 *   POST azione=attiva tema=<nome>     applica stile, header e footer del tema e lo rende attivo
 *   POST azione=base                   nessun tema: il sito usa sito.css e lo Stile del sito com'è
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/temi.php';
require_admin(); // sicurezza (ruoli): solo amministratore
$adminTitle = 'Temi';
$autore = (string)(current_user()['username'] ?? '');

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
            default:
                throw new RuntimeException('Azione sconosciuta.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: temi.php');
    exit;
}

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
.tt-azioni{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:auto;padding-top:.4rem}
.tt-azioni form{display:inline}
.tt-dove{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;margin-top:1.4rem;font-size:.86rem;color:var(--text-muted)}
.tt-dove p{margin:.3rem 0}
</style>

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
      <?php if ($t['errori']): ?><p class="tt-errori"><?= h(implode(' ', $t['errori'])) ?></p><?php endif; ?>
      <div class="tt-azioni">
        <a class="btn btn-secondary btn-sm" href="<?= h($anteprima($slug)) ?>" target="_blank" rel="noopener">Anteprima</a>
        <?php if (!$attivo || $rotto): ?>
        <form method="post" data-conferma="<?= h($conferma) ?>"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="azione" value="attiva"><input type="hidden" name="tema" value="<?= h($slug) ?>"><button class="btn btn-primary btn-sm"><?= $rotto ? 'Riprova ad attivarlo' : 'Attiva' ?></button></form>
        <?php else: ?>
        <a class="btn btn-secondary btn-sm" href="design.php">Personalizza</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="tt-dove">
  <p><b>Dove stanno i temi.</b> Ogni tema è una cartella dentro <code>temi/</code>, nella cartella del CMS sul server. <?= $temi ? 'Qui ne vedi ' . count($temi) . '.' : 'Per ora non ce n\'è nessuno.' ?></p>
  <p>Per chi scrive un tema: com'è fatto, cosa può cambiare e cosa succede se sbaglia sta in <code>docs/temi.md</code>.</p>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
