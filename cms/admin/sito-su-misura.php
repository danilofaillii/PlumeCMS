<?php
/**
 * Il sito su misura dalla dashboard (1.7.0): le stesse quattro domande dell'installatore (su_misura_campi_html in
 * includes/sito-su-misura.php), già compilate con le risposte di prima. Salvando, le risposte vanno nelle impostazioni e
 * il sito si ricompone con kit_cambia('su-misura'): copia del sito prima, poi cambia solo quello che il kit aveva
 * preparato e che la persona non ha toccato. Solo amministratore e solo finché il tipo di sito è in prova.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/includes/kit-partenza.php';
require_once dirname(__DIR__) . '/includes/sito-su-misura.php';
require_admin();
$adminTitle = 'Sito su misura';
$prova = kit_in_prova() ? kit_prova() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (!$prova) throw new RuntimeException('Il tipo di sito è già definitivo: il sito su misura non si rifà più da qui.');
        $vecchie = su_misura_risposte();
        su_misura_salva_risposte($_POST);
        session_write_close(); // la copia del sito può durare: le altre schede del pannello non aspettano
        ob_start();
        try {
            $r = kit_cambia('su-misura', (string)(current_user()['username'] ?? ''));
        } catch (Throwable $e) {
            // Il sito non è cambiato: tornano le risposte di prima, così la pagina dice com'è davvero.
            if ($vecchie) su_misura_salva_risposte($vecchie);
            throw $e;
        } finally {
            ob_end_clean();
            @session_start();
        }
        flash('success', 'Il sito su misura è pronto: ' . su_misura_quante_pagine(su_misura_risposte()) . ' pagine con le foto finte da sostituire (Media, Da sostituire). Prima del cambio il pannello ha fatto una copia del sito (' . $r['copia'] . ', in Impostazioni, Copia del sito).');
        header('Location: index.php#tipo-sito'); exit;
    } catch (Throwable $e) {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        while (ob_get_level() > 1) ob_end_clean();
        flash('error', $e->getMessage());
    }
    header('Location: sito-su-misura.php'); exit;
}

$risposte = su_misura_risposte() ?? su_misura_risposte_vuote();
$giaQui = $prova && $prova['kit'] === 'su-misura';
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
.sm-scatola{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.25rem;margin-bottom:1rem;max-width:980px}
.sm-scatola>p{color:var(--text-muted);font-size:.9rem;margin:.2rem 0 1rem;max-width:760px}
.sm-gruppo{border:0;margin:0 0 1.4rem;padding:0;min-width:0}
.sm-gruppo legend{font-weight:700;font-size:1rem;margin-bottom:.5rem;padding:0}
.sm-nota{color:var(--text-muted);font-size:.85rem;margin:.2rem 0 .6rem}
.sm-attivita{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:.6rem}
.sm-carta{position:relative;display:flex;flex-direction:column;gap:.15rem;padding:.6rem 2rem .6rem .75rem;border:1.5px solid var(--border);border-radius:var(--radius);background:var(--surface);cursor:pointer}
.sm-carta input{position:absolute;top:.7rem;right:.6rem;width:17px;height:17px;accent-color:var(--coral)}
.sm-carta:has(input:checked){border-color:var(--coral);background:var(--coral-light)}
.sm-carta:has(input:focus-visible){outline:2px solid var(--coral);outline-offset:2px}
.sm-carta b{font-size:.92rem}.sm-carta small{color:var(--text-muted);font-size:.78rem;line-height:1.35}
.sm-campo{display:block;max-width:420px}.sm-campo span{display:block;font-weight:600;font-size:.88rem;margin-bottom:.25rem}
.sm-campo input,.sm-servizi input{width:100%;box-sizing:border-box}
.sm-servizi{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.5rem .75rem;max-width:760px}
.sm-pagine{display:grid;gap:.4rem;max-width:760px}
.sm-spunta{display:flex;gap:.6rem;align-items:flex-start;padding:.5rem .7rem;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:.88rem}
.sm-spunta input{margin-top:.2rem;width:16px;height:16px;accent-color:var(--coral);flex:none}
.sm-spunta span{color:var(--text-muted)}.sm-spunta b{color:var(--text)}
.sm-spunta.sm-ogni{margin-top:.3rem;border-style:dashed}
.sm-piede{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;border-top:1px solid var(--border);padding-top:1rem}
.sm-piede small{color:var(--text-muted);flex:1 1 320px}
</style>

<div class="sm-scatola" id="sito-su-misura">
<?php if (!$prova): ?>
  <p>Il tipo di sito di questo sito è definitivo (o il sito è nato prima della 1.4.0): il sito su misura non si compone più da qui. Le pagine si cambiano con l'editor, una alla volta.</p>
  <p><a class="btn btn-secondary" href="index.php">Torna alla dashboard</a></p>
<?php else: ?>
  <p><?= $giaQui ? 'Il sito che stai provando è nato da queste risposte. Cambiale e premi il pulsante: il sito si ricompone.' : 'Quattro domande e il sito si compone da solo: home, pagine, menu e testi pensati per la tua attività, con foto finte che dicono cosa metterci.' ?>
    Le pagine che hai già scritto o cambiato restano, e prima del cambio il pannello fa una copia del sito.</p>
  <form method="post" id="sm-modulo" data-conferma="<?= h('Il sito si ricompone con queste risposte. Le pagine che hai scritto o cambiato restano. Prima del cambio il pannello fa una copia del sito.') ?>">
    <?= csrf_field() ?>
    <?= su_misura_campi_html($risposte, 'h') ?>
    <div class="sm-piede">
      <button type="submit" class="btn btn-primary" id="sm-componi"><?= $giaQui ? 'Ricomponi il sito' : 'Componi il sito su misura' ?></button>
      <a class="btn btn-secondary" href="index.php#tipo-sito">Annulla</a>
      <small>Le foto finte stanno in Media, nella cartella Da sostituire: ognuna ha il pulsante Sostituisci.</small>
    </div>
  </form>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
