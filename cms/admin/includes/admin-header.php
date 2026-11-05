<?php
$user = current_user();
$adminTitle = $adminTitle ?? 'Admin';
$qui = basename($_SERVER['SCRIPT_NAME']);
// 2.0.0: le pagine degli addon passano tutte da addon-pagina.php; per il menu ognuna è "addon-pagina.php?a=<addon>&p=<pagina>".
if ($qui === 'addon-pagina.php') $qui = 'addon-pagina.php?a=' . (string)($_GET['a'] ?? '') . '&p=' . (string)($_GET['p'] ?? '');
$ctTipo = $_GET['tipo'] ?? '';
$ctQui = in_array($qui, ['contenuti.php', 'contenuto.php', 'editor.php'], true);
$sonoAdminMenu = is_admin();
// Le pagine del gruppo Editor: con una di queste aperta il gruppo si apre da solo.
const EDITOR_MENU_PAGINE = ['temi.php', 'design.php', 'modelli.php', 'menu.php', 'editor-temi.php', 'editor-widget.php', 'versioni.php', 'editor-impostazioni.php', 'informazioni.php'];
$homeId = (int)db()->query('SELECT id FROM ' . tbl('pages') . ' WHERE slug = ' . db()->quote((string)get_setting('pagina_home', '')))->fetchColumn();

/**
 * Il menu: voci semplici e gruppi con le sottovoci. 'qui' dice quando la voce è quella aperta.
 */
$menu = [
    ['Dashboard', 'index.php', 'grafico-barre', $qui === 'index.php'],
    ['Articoli', 'contenuti.php?tipo=articoli', 'documento-testo', ($ctQui && ($ctTipo === '' || $ctTipo === 'articoli')) || $qui === 'fine-articolo.php'],
    ['Pagine', 'contenuti.php?tipo=pagine', 'documento', $ctQui && $ctTipo === 'pagine', [['Home nell\'editor', $homeId ? 'editor.php?id=' . $homeId : 'contenuti.php?tipo=pagine', false]]],
    ['Popup', 'popup.php', 'finestra', $qui === 'popup.php'],
    ['Categorie', 'categories.php', 'cartella', $qui === 'categories.php' && ($_GET['tipo'] ?? '') !== 'tag'],
    ['Tag', 'categories.php?tipo=tag', 'etichetta', $qui === 'categories.php' && ($_GET['tipo'] ?? '') === 'tag'],
    ['Media', 'media.php', 'immagine', $qui === 'media.php'],
    $sonoAdminMenu ? ['Download', 'scaricabili.php', 'download', $qui === 'scaricabili.php'] : null, // download manager (1.2.4)
    $sonoAdminMenu ? ['Utenti', 'utenti.php', 'utenti', $qui === 'utenti.php'] : null,
    // 2.8.0: il menu come Brevo (docs-sviluppo/brevo-confronto.md, capitolo 2): Contatti (con Liste), poi Campagne email,
    // Form, Invii dei form, Flussi automatici, Statistiche, Impostazioni (con Attributi dei contatti). Le voci delle fasi
    // dopo (Segmenti, Modelli, Automazioni) arrivano con le loro pagine.
    ['Newsletter e form', 'newsletter-contatti.php', 'email', in_array($qui, ['newsletter.php', 'newsletter-campagna.php', 'newsletter-flussi.php', 'newsletter-statistiche.php', 'newsletter-report.php', 'form-submissions.php', 'form.php', 'newsletter-contatti.php', 'newsletter-contatto.php', 'newsletter-attributi.php', 'newsletter-importa.php', 'newsletter-segmenti.php', 'newsletter-modelli.php', 'newsletter-consenso.php', 'newsletter-tag.php'], true), array_values(array_filter([
        ['Contatti', 'newsletter-contatti.php', in_array($qui, ['newsletter-contatti.php', 'newsletter-contatto.php', 'newsletter-importa.php', 'newsletter-segmenti.php'], true)],
        $sonoAdminMenu ? ['Tag', 'newsletter-tag.php', $qui === 'newsletter-tag.php'] : null, // 2.23.0: i tag dei contatti
        ['Campagne email', 'newsletter.php', in_array($qui, ['newsletter.php', 'newsletter-campagna.php', 'newsletter-report.php'], true) && ($_GET['scheda'] ?? '') !== 'impostazioni'],
        ['Modelli', 'newsletter-modelli.php', $qui === 'newsletter-modelli.php'], // 2.12.0
        // 1.9.1, richiesta di Danilo: Form sta qui, accanto ai suoi invii (prima era una voce a sé). Solo amministratore.
        $sonoAdminMenu ? ['Form', 'form.php', $qui === 'form.php'] : null,
        ['Invii dei form', 'form-submissions.php', $qui === 'form-submissions.php'],
        ['Automazioni', 'newsletter-flussi.php', $qui === 'newsletter-flussi.php'],
        ['Statistiche', 'newsletter-statistiche.php', $qui === 'newsletter-statistiche.php'],
        ['Impostazioni', 'newsletter.php?scheda=impostazioni', $qui === 'newsletter.php' && ($_GET['scheda'] ?? '') === 'impostazioni'],
        ['Attributi dei contatti', 'newsletter-attributi.php', $qui === 'newsletter-attributi.php'],
        $sonoAdminMenu ? ['Pagine e consenso', 'newsletter-consenso.php', $qui === 'newsletter-consenso.php'] : null, // 2.16.0
    ]))],
    // 1.5.0: email del mattino, calendario e testi dei social (includes/social.php). Solo amministratore.
    $sonoAdminMenu ? ['Social', 'social.php', 'megafono', $qui === 'social.php', [
        ['Email del mattino', 'social.php?scheda=mattino', $qui === 'social.php' && in_array($_GET['scheda'] ?? 'mattino', ['mattino'], true)],
        ['Calendario', 'social.php?scheda=calendario', $qui === 'social.php' && ($_GET['scheda'] ?? '') === 'calendario'],
        ['Testi dei contenuti', 'social.php?scheda=testi', $qui === 'social.php' && ($_GET['scheda'] ?? '') === 'testi'],
        ['Impostazioni', 'social.php?scheda=impostazioni', $qui === 'social.php' && ($_GET['scheda'] ?? '') === 'impostazioni'],
    ]] : null,
    // menu (26 ottobre): tutto quello che è dell'editor in un gruppo solo. Le pagine nuove: Temi, Widget, Versioni (agente versioni), Impostazioni dell'editor.
    ['Editor', 'design.php', 'matita', in_array($qui, EDITOR_MENU_PAGINE, true), array_values(array_filter([
        $sonoAdminMenu ? ['Temi', 'temi.php', $qui === 'temi.php'] : null,
        ['Stile del sito', 'design.php', $qui === 'design.php'],
        ['Header e footer', 'modelli.php', $qui === 'modelli.php'],
        ['Menu di navigazione', 'menu.php', $qui === 'menu.php'],
        ['Colori delle infografiche', 'editor-temi.php', $qui === 'editor-temi.php'],
        $sonoAdminMenu ? ['Widget', 'editor-widget.php', $qui === 'editor-widget.php'] : null,
        ['Versioni', 'versioni.php', $qui === 'versioni.php'],
        $sonoAdminMenu ? ['Impostazioni dell\'editor', 'editor-impostazioni.php', $qui === 'editor-impostazioni.php'] : null,
        ['Informazioni', 'informazioni.php', $qui === 'informazioni.php'],
    ]))],
    // seo: Search Console, Bing, IndexNow, statistiche, robots, sitemap, redirect e 404, dati strutturati, llms.txt. Solo amministratore.
    $sonoAdminMenu ? ['SEO e AEO', 'seo.php', 'cerca', in_array($qui, ['seo.php', 'sedi.php'], true), [
        ['Panoramica', 'seo.php?scheda=panoramica', $qui === 'seo.php' && in_array($_GET['scheda'] ?? 'panoramica', ['panoramica'], true)],
        ['Motori di ricerca', 'seo.php?scheda=motori', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'motori'],
        ['Copertura e prestazioni', 'seo.php?scheda=copertura', $qui === 'seo.php' && in_array($_GET['scheda'] ?? '', ['copertura', 'prestazioni'], true)],
        ['Analisi e tag', 'seo.php?scheda=analisi', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'analisi'],
        ['Globale SEO', 'seo.php?scheda=globale', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'globale'],
        ['Schema e AEO', 'seo.php?scheda=schema', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'schema'],
        ['Redirect e 404', 'seo.php?scheda=redirect', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'redirect'],
        ['Sedi', 'sedi.php', $qui === 'sedi.php'], // 2.27.0: le sedi e la SEO geografica (admin/sedi.php)
    ]] : null,
    // Il gruppo Impostazioni è dell'amministratore: per il redattore il filtro dei ruoli qui sotto lo toglie tutto (anche Log cron, cron-log.php è solo admin).
    ['Impostazioni', $sonoAdminMenu ? 'impostazioni.php' : 'cron-log.php', 'impostazioni', in_array($qui, ['impostazioni.php', 'consegna.php', 'addon.php', 'aggiornamenti.php', 'copia-sito.php', 'cron-log.php', 'sicurezza.php', 'controlla-server.php'], true), array_values(array_filter([
        $sonoAdminMenu ? ['Configurazione email', 'impostazioni.php', $qui === 'impostazioni.php' && ($_GET['scheda'] ?? '') !== 'sito'] : null,
        $sonoAdminMenu ? ['Sito', 'impostazioni.php?scheda=sito', $qui === 'impostazioni.php' && ($_GET['scheda'] ?? '') === 'sito'] : null, // UX 2026-11: indirizzo e frase in fondo al sito
        $sonoAdminMenu ? ['Consegna delle email', 'consegna.php', $qui === 'consegna.php'] : null, // 2.1.0: dominio, servizi, mittenti, rimbalzi, riscaldamento
        $sonoAdminMenu ? ['Sicurezza', 'sicurezza.php', $qui === 'sicurezza.php'] : null, // sicurezza: checklist, 2FA, blocchi, CSP, registro
        $sonoAdminMenu ? ['Addon', 'addon.php', $qui === 'addon.php'] : null, // 2.0.0: le funzioni in più, da accendere
        $sonoAdminMenu ? ['Aggiornamenti', 'aggiornamenti.php', $qui === 'aggiornamenti.php'] : null,
        $sonoAdminMenu ? ['Copia del sito', 'copia-sito.php', $qui === 'copia-sito.php'] : null, // 1.3.0: scarica e ripristina tutto il sito
        ['Log cron', 'cron-log.php', $qui === 'cron-log.php'],
        $sonoAdminMenu ? ['Controlla il server', 'controlla-server.php', $qui === 'controlla-server.php'] : null, // 1.4.0: prove vere dal server
    ]))],
];
$menu = array_values(array_filter($menu));
// 1.4.0: le icone del menu sono quelle disegnate del set (includes/icone.php), del colore del testo: seguono l'aspetto.
// Una voce di un modulo può ancora dare un'icona sua (un'entita HTML): passa com'è.
require_once dirname(__DIR__, 2) . '/includes/icone.php';
$iconaMenu = fn(string $i): string => preg_match('/^[a-z-]+$/', $i) && icona_esiste($i) ? icona_svg($i, 'nav-svg') : $i;
// Le voci dei moduli del sito: ogni modulo aggiunge le sue pagine al menu (chiave menu del manifest).
$menu = cms_menu_integra($menu, $qui, (string)$ctTipo, $sonoAdminMenu);
// Ruoli: il redattore vede solo le voci che può aprire (le stesse regole di require_login, auth_redattore_puo).
if (!$sonoAdminMenu) {
    // 2.3.2: nomi propri del menu (_menu...): questo file si include dentro le pagine, e i nomi comuni ($v, $x...) le sovrascrivevano.
    $_menuPuo = function (string $url): bool {
        $pag = basename((string)parse_url($url, PHP_URL_PATH));
        parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
        return auth_redattore_puo($pag, (string)($q['tipo'] ?? ''));
    };
    $_menuFiltrato = [];
    foreach ($menu as $_menuVoce) {
        $_menuSotto = array_values(array_filter($_menuVoce[4] ?? [], fn($x) => $_menuPuo((string)$x[1])));
        if (!$_menuPuo((string)$_menuVoce[1])) {
            if (!$_menuSotto) continue;
            $_menuVoce[1] = $_menuSotto[0][1];
        }
        if (isset($_menuVoce[4])) $_menuVoce[4] = $_menuSotto;
        $_menuFiltrato[] = $_menuVoce;
    }
    $menu = $_menuFiltrato;
    unset($_menuPuo, $_menuFiltrato, $_menuVoce, $_menuSotto);
}
?>
<!DOCTYPE html>
<html lang="it" data-pannello="<?= h(pannello_aspetto($user)) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="pn-csrf" content="<?= h(csrf_token()) ?>"><?php /* 2.1.0: il token per le chiamate di pannello.js (Scegli dai Media) anche nelle pagine senza form */ ?>
  <title><?= htmlspecialchars($adminTitle) ?> &middot; Pannello</title>
  <link rel="stylesheet" href="<?= pannello_file('assets/css/aspetti.css') ?>"><link rel="stylesheet" href="<?= pannello_file('assets/css/admin.css') ?>"><?php if (str_starts_with($qui, 'addon-pagina.php')): /* 2.0.0: i riquadri delle pagine degli addon (.sc-scatola) */ ?><link rel="stylesheet" href="<?= pannello_file('assets/css/contenuti.css') ?>"><?php endif; ?>
  <script src="<?= pannello_file('assets/campi-protetti.js') ?>"></script>
  <script src="<?= pannello_file('assets/pannello.js') ?>" defer></script>
  <link rel="stylesheet" href="<?= pannello_file('assets/css/scegli-link.css') ?>"><script src="<?= pannello_file('assets/scegli-link.js') ?>" defer></script><?php /* 2.2: Scegli dal sito (data-link-sito, window.pnLink) */ ?>
</head>
<body class="admin-body">
<a class="admin-salta" href="#contenuto">Vai al contenuto</a><?php /* UX 2026-11: chi usa la tastiera salta il menu (WCAG 2.4.1) */ ?>
<div class="admin-sidebar-overlay" data-menu-mobile></div>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-logo">
      <strong>plume</strong>CMS <span class="admin-versione" id="admin-versione" title="La versione di <?= h(CMS_NOME) ?> installata">v. <?= h(cms_versione()) ?></span>
    </div>
    <nav class="admin-nav">
      <?php /* 2.3.2: nomi _m... e unset alla fine: prima $sa, $v, $nome... sovrascrivevano le variabili della pagina (il service account di Google spariva dal riquadro SEO). */ ?>
      <?php foreach ($menu as $_mVoce): [$_mNome, $_mHref, $_mIcona, $_mAttivo] = $_mVoce; $_mSotto = $_mVoce[4] ?? []; ?>
      <?php if (!$_mSotto): ?>
      <a href="<?= h($_mHref) ?>" class="<?= $_mAttivo ? 'active' : '' ?>"><span class="nav-icon"><?= $iconaMenu((string)$_mIcona) ?></span> <?= h($_mNome) ?></a>
      <?php else: ?>
      <details class="nav-gruppo" <?= $_mAttivo ? 'open' : '' ?>>
        <summary class="<?= $_mAttivo ? 'active' : '' ?>"><a href="<?= h($_mHref) ?>"><span class="nav-icon"><?= $iconaMenu((string)$_mIcona) ?></span> <?= h($_mNome) ?></a><span class="nav-freccia" title="Apri o chiudi il gruppo">&#9656;</span></summary>
        <div class="nav-sotto">
          <?php foreach ($_mSotto as [$_mSn, $_mSh, $_mSa]): ?>
          <a href="<?= h($_mSh) ?>" class="<?= $_mSa ? 'active' : '' ?>"><?= h($_mSn) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endif; ?>
      <?php endforeach; unset($_mVoce, $_mNome, $_mHref, $_mIcona, $_mAttivo, $_mSotto, $_mSn, $_mSh, $_mSa); ?>
    </nav>
    <div class="admin-sidebar-footer">
      <a href="<?= h(cms_url_sito()) ?>/" target="_blank"><?= icona_svg('occhio', 'nav-svg') ?> Vedi sito</a>
      <a href="password.php"><?= icona_svg('chiave', 'nav-svg') ?> Password</a>
      <a href="account.php" class="<?= $qui === 'account.php' ? 'active' : '' ?>"><?= icona_svg('lucchetto', 'nav-svg') ?> Il mio accesso</a>
      <a href="logout.php"><?= icona_svg('esci', 'nav-svg') ?> Esci</a>
    </div>
  </aside>
  <main class="admin-main">
    <header class="admin-topbar">
      <div style="display:flex;align-items:center;gap:0.75rem">
        <button type="button" class="admin-menu-toggle" data-menu-mobile aria-label="Menu del pannello" aria-expanded="false">&#9776;</button><?php /* UX 2026-11: il pulsante dice cosa fa a chi non lo vede */ ?>
        <span class="admin-versione admin-versione-tel">v. <?= h(cms_versione()) ?></span><?php /* 2.3.0: al telefono il menu è chiuso, la versione sta qui */ ?>
        <h1 class="admin-page-title"><?= htmlspecialchars($adminTitle) ?></h1>
      </div>
      <div class="admin-user">
        <?= htmlspecialchars($user['display_name'] ?: $user['username']) ?>
      </div>
    </header>
    <div class="admin-content" id="contenuto" tabindex="-1">
      <?= render_flash() ?>
