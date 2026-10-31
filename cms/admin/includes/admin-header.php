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
    ['Newsletter e form', 'newsletter.php', 'email', in_array($qui, ['newsletter.php', 'newsletter-campagna.php', 'newsletter-flussi.php', 'newsletter-statistiche.php', 'form-submissions.php', 'form.php'], true), array_values(array_filter([
        ['Campagne', 'newsletter.php', in_array($qui, ['newsletter.php', 'newsletter-campagna.php'], true) && !in_array($_GET['scheda'] ?? '', ['iscritti', 'liste', 'impostazioni'], true)],
        ['Iscritti e liste', 'newsletter.php?scheda=iscritti', $qui === 'newsletter.php' && in_array($_GET['scheda'] ?? '', ['iscritti', 'liste', 'impostazioni'], true)],
        // 1.9.1, richiesta di Danilo: Form sta qui, accanto ai suoi invii (prima era una voce a sé). Solo amministratore.
        $sonoAdminMenu ? ['Form', 'form.php', $qui === 'form.php'] : null,
        ['Invii dei form', 'form-submissions.php', $qui === 'form-submissions.php'],
        ['Flussi automatici', 'newsletter-flussi.php', $qui === 'newsletter-flussi.php'],
        ['Statistiche', 'newsletter-statistiche.php', $qui === 'newsletter-statistiche.php'],
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
    $sonoAdminMenu ? ['SEO e AEO', 'seo.php', 'cerca', $qui === 'seo.php', [
        ['Panoramica', 'seo.php?scheda=panoramica', $qui === 'seo.php' && in_array($_GET['scheda'] ?? 'panoramica', ['panoramica'], true)],
        ['Motori di ricerca', 'seo.php?scheda=motori', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'motori'],
        ['Copertura e prestazioni', 'seo.php?scheda=copertura', $qui === 'seo.php' && in_array($_GET['scheda'] ?? '', ['copertura', 'prestazioni'], true)],
        ['Analisi e tag', 'seo.php?scheda=analisi', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'analisi'],
        ['Globale SEO', 'seo.php?scheda=globale', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'globale'],
        ['Schema e AEO', 'seo.php?scheda=schema', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'schema'],
        ['Redirect e 404', 'seo.php?scheda=redirect', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'redirect'],
    ]] : null,
    // Il gruppo Impostazioni è dell'amministratore: per il redattore il filtro dei ruoli qui sotto lo toglie tutto (anche Log cron, cron-log.php è solo admin).
    ['Impostazioni', $sonoAdminMenu ? 'impostazioni.php' : 'cron-log.php', 'impostazioni', in_array($qui, ['impostazioni.php', 'consegna.php', 'addon.php', 'aggiornamenti.php', 'copia-sito.php', 'importa-wordpress.php', 'cron-log.php', 'sicurezza.php', 'controlla-server.php'], true), array_values(array_filter([
        $sonoAdminMenu ? ['Configurazione email', 'impostazioni.php', $qui === 'impostazioni.php'] : null,
        $sonoAdminMenu ? ['Consegna delle email', 'consegna.php', $qui === 'consegna.php'] : null, // 2.1.0: dominio, servizi, mittenti, rimbalzi, riscaldamento
        $sonoAdminMenu ? ['Sicurezza', 'sicurezza.php', $qui === 'sicurezza.php'] : null, // sicurezza: checklist, 2FA, blocchi, CSP, registro
        $sonoAdminMenu ? ['Addon', 'addon.php', $qui === 'addon.php'] : null, // 2.0.0: le funzioni in più, da accendere
        $sonoAdminMenu ? ['Aggiornamenti', 'aggiornamenti.php', $qui === 'aggiornamenti.php'] : null,
        $sonoAdminMenu ? ['Copia del sito', 'copia-sito.php', $qui === 'copia-sito.php'] : null, // 1.3.0: scarica e ripristina tutto il sito
        $sonoAdminMenu ? ['Importa da WordPress', 'importa-wordpress.php', $qui === 'importa-wordpress.php'] : null, // 1.4.0
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
    $puo = function (string $url): bool {
        $pag = basename((string)parse_url($url, PHP_URL_PATH));
        parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
        return auth_redattore_puo($pag, (string)($q['tipo'] ?? ''));
    };
    $filtrato = [];
    foreach ($menu as $v) {
        $sotto = array_values(array_filter($v[4] ?? [], fn($x) => $puo((string)$x[1])));
        if (!$puo((string)$v[1])) {
            if (!$sotto) continue;
            $v[1] = $sotto[0][1];
        }
        if (isset($v[4])) $v[4] = $sotto;
        $filtrato[] = $v;
    }
    $menu = $filtrato;
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
</head>
<body class="admin-body">
<div class="admin-sidebar-overlay" data-menu-mobile></div>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-logo">
      <strong>plume</strong>CMS
    </div>
    <nav class="admin-nav">
      <?php foreach ($menu as $v): [$nome, $href, $icona, $attivo] = $v; $sotto = $v[4] ?? []; ?>
      <?php if (!$sotto): ?>
      <a href="<?= h($href) ?>" class="<?= $attivo ? 'active' : '' ?>"><span class="nav-icon"><?= $iconaMenu((string)$icona) ?></span> <?= h($nome) ?></a>
      <?php else: ?>
      <details class="nav-gruppo" <?= $attivo ? 'open' : '' ?>>
        <summary class="<?= $attivo ? 'active' : '' ?>"><a href="<?= h($href) ?>"><span class="nav-icon"><?= $iconaMenu((string)$icona) ?></span> <?= h($nome) ?></a><span class="nav-freccia" title="Apri o chiudi il gruppo">&#9656;</span></summary>
        <div class="nav-sotto">
          <?php foreach ($sotto as [$sn, $sh, $sa]): ?>
          <a href="<?= h($sh) ?>" class="<?= $sa ? 'active' : '' ?>"><?= h($sn) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endif; ?>
      <?php endforeach; ?>
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
        <button class="admin-menu-toggle" data-menu-mobile>&#9776;</button>
        <h1 class="admin-page-title"><?= htmlspecialchars($adminTitle) ?></h1>
      </div>
      <div class="admin-user">
        <?= htmlspecialchars($user['display_name'] ?: $user['username']) ?>
      </div>
    </header>
    <div class="admin-content">
      <?= render_flash() ?>
