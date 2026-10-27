<?php
$user = current_user();
$adminTitle = $adminTitle ?? 'Admin';
$qui = basename($_SERVER['SCRIPT_NAME']);
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
    ['Dashboard', 'index.php', '📊', $qui === 'index.php'],
    ['Articoli', 'contenuti.php?tipo=articoli', '📝', $ctQui && ($ctTipo === '' || $ctTipo === 'articoli')],
    ['Pagine', 'contenuti.php?tipo=pagine', '📄', $ctQui && $ctTipo === 'pagine', [['Home nell\'editor', $homeId ? 'editor.php?id=' . $homeId : 'contenuti.php?tipo=pagine', false]]],
    ['Form', 'form.php', '🧾', $qui === 'form.php'],
    ['Popup', 'popup.php', '🪟', $qui === 'popup.php'],
    ['Categorie', 'categories.php', '📁', $qui === 'categories.php' && ($_GET['tipo'] ?? '') !== 'tag'],
    ['Tag', 'categories.php?tipo=tag', '🏷', $qui === 'categories.php' && ($_GET['tipo'] ?? '') === 'tag'],
    ['Media', 'media.php', '🖼', $qui === 'media.php'],
    $sonoAdminMenu ? ['Download', 'scaricabili.php', '&#11015;', $qui === 'scaricabili.php'] : null, // download manager (1.2.4)
    $sonoAdminMenu ? ['Utenti', 'utenti.php', '👤', $qui === 'utenti.php'] : null,
    ['Newsletter e contatti', 'newsletter.php', '✉️', in_array($qui, ['newsletter.php', 'newsletter-campagna.php', 'contatti.php', 'newsletter-flussi.php', 'newsletter-statistiche.php', 'form-submissions.php'], true), [
        ['Campagne', 'newsletter.php', in_array($qui, ['newsletter.php', 'newsletter-campagna.php'], true) && !in_array($_GET['scheda'] ?? '', ['iscritti', 'liste', 'impostazioni'], true)],
        ['Iscritti e liste', 'newsletter.php?scheda=iscritti', $qui === 'newsletter.php' && in_array($_GET['scheda'] ?? '', ['iscritti', 'liste', 'impostazioni'], true)],
        ['Contatti (Laser, ebook)', 'contatti.php', $qui === 'contatti.php'],
        ['Invii dei form', 'form-submissions.php', $qui === 'form-submissions.php'],
        ['Flussi automatici', 'newsletter-flussi.php', $qui === 'newsletter-flussi.php'],
        ['Statistiche', 'newsletter-statistiche.php', $qui === 'newsletter-statistiche.php'],
    ]],
    // menu (26 ottobre): tutto quello che è dell'editor in un gruppo solo. Le pagine nuove: Temi, Widget, Versioni (agente versioni), Impostazioni dell'editor.
    ['Editor', 'design.php', '&#9998;', in_array($qui, EDITOR_MENU_PAGINE, true), array_values(array_filter([
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
    $sonoAdminMenu ? ['SEO e AEO', 'seo.php', '&#128269;', $qui === 'seo.php', [
        ['Panoramica', 'seo.php?scheda=panoramica', $qui === 'seo.php' && in_array($_GET['scheda'] ?? 'panoramica', ['panoramica'], true)],
        ['Motori di ricerca', 'seo.php?scheda=motori', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'motori'],
        ['Copertura e prestazioni', 'seo.php?scheda=copertura', $qui === 'seo.php' && in_array($_GET['scheda'] ?? '', ['copertura', 'prestazioni'], true)],
        ['Analisi e tag', 'seo.php?scheda=analisi', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'analisi'],
        ['Globale SEO', 'seo.php?scheda=globale', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'globale'],
        ['Schema e AEO', 'seo.php?scheda=schema', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'schema'],
        ['Redirect e 404', 'seo.php?scheda=redirect', $qui === 'seo.php' && ($_GET['scheda'] ?? '') === 'redirect'],
    ]] : null,
    // Il redattore vede solo Log cron e Diagnosi: posta, aggiornamenti e SQL sono dell'amministratore.
    ['Impostazioni', $sonoAdminMenu ? 'impostazioni.php' : 'cron-log.php', '⚙', in_array($qui, ['impostazioni.php', 'aggiornamenti.php', 'copia-sito.php', 'cron-log.php', 'sicurezza.php'], true), array_values(array_filter([
        $sonoAdminMenu ? ['Configurazione email', 'impostazioni.php', $qui === 'impostazioni.php'] : null,
        $sonoAdminMenu ? ['Sicurezza', 'sicurezza.php', $qui === 'sicurezza.php'] : null, // sicurezza: checklist, 2FA, blocchi, CSP, registro
        $sonoAdminMenu ? ['Aggiornamenti', 'aggiornamenti.php', $qui === 'aggiornamenti.php'] : null,
        $sonoAdminMenu ? ['Copia del sito', 'copia-sito.php', $qui === 'copia-sito.php'] : null, // 1.3.0: scarica e ripristina tutto il sito
        ['Log cron', 'cron-log.php', $qui === 'cron-log.php'],
    ]))],
];
$menu = array_values(array_filter($menu));
// Le voci dei moduli (danilofailli.it: Aforismi, Test, Social copy, Importa SQL, Diagnosi articoli).
$menu = cms_menu_integra($menu, $qui, (string)$ctTipo, $sonoAdminMenu);
// Ruoli: il redattore vede solo le voci che puo aprire (le stesse regole di require_login, auth_redattore_puo).
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
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($adminTitle) ?> — Pannello</title>
  <link rel="stylesheet" href="assets/css/admin.css">
  <script src="assets/campi-protetti.js"></script>
  <script src="assets/pannello.js" defer></script>
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
      <a href="<?= h($href) ?>" class="<?= $attivo ? 'active' : '' ?>"><span class="nav-icon"><?= $icona ?></span> <?= h($nome) ?></a>
      <?php else: ?>
      <details class="nav-gruppo" <?= $attivo ? 'open' : '' ?>>
        <summary class="<?= $attivo ? 'active' : '' ?>"><a href="<?= h($href) ?>"><span class="nav-icon"><?= $icona ?></span> <?= h($nome) ?></a><span class="nav-freccia" title="Apri o chiudi il gruppo">▸</span></summary>
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
      <a href="<?= h(cms_url_sito()) ?>/" target="_blank">👁 Vedi sito</a>
      <a href="password.php">🔑 Password</a>
      <a href="account.php">&#128274; Il mio accesso</a>
      <a href="logout.php">🚪 Esci</a>
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
