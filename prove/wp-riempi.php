<?php
/**
 * Riempie il WordPress di prova (prove/wp-installa.php) con un sito vero e proprio, sempre uguale:
 *   php prove/wp-riempi.php <cartella di WordPress> <cartella dei segreti>
 * Crea 33 articoli (27 pubblicati, 4 bozze, 1 in attesa di revisione, 1 programmato), 6 pagine (una figlia), categorie
 * annidate, 8 tag, 15 immagini vere fatte con GD (in evidenza e nel testo, una galleria), un PDF, un menu con sottovoci
 * nella posizione "primary" del tema Twenty Twenty-One, 5 commenti, un secondo autore, blocchi di Gutenberg di ogni tipo
 * (paragrafi, titoli, immagini, galleria, liste, citazioni, tabelle, un embed di YouTube), uno shortcode di un plugin che
 * non c'e, una pagina fatta "con Elementor", un'emoji nel titolo, titolo e descrizione SEO di Yoast nei meta.
 *
 * Nella cartella dei segreti (fuori dal repository, file 0600) scrive:
 *   app.txt    la password per le applicazioni, come WordPress la mostra (sei gruppi di quattro), nuova a ogni giro;
 *   login.txt  la password di accesso dell'amministratore (per provare l'errore "password normale");
 *   atteso.json quello che l'import deve trovare: conteggi e, per ogni contenuto, titolo, slug, data, stato, categorie.
 * Niente di questo si stampa.
 */
if (PHP_SAPI !== 'cli') exit;
[$_, $dir, $segreti] = $argv + [null, null, null];
if (!$dir || !$segreti) { fwrite(STDERR, "uso: php wp-riempi.php <cartella di WordPress> <cartella dei segreti>\n"); exit(1); }
$dir = rtrim(realpath($dir), '/');
if (!is_dir($segreti)) mkdir($segreti, 0700, true);
$home = '';
foreach (file("$dir/wp-config.php") as $r) if (preg_match("~define\('WP_HOME', '([^']+)'~", $r, $m)) $home = $m[1];
$_SERVER['HTTP_HOST'] = parse_url($home, PHP_URL_HOST) . ':' . parse_url($home, PHP_URL_PORT);
$_SERVER['REQUEST_URI'] = '/';
require "$dir/wp-load.php";
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
mt_srand(2026);

// Via i contenuti d'esempio di WordPress: il sito di prova e sempre lo stesso.
foreach (get_posts(['post_type' => ['post', 'page', 'attachment'], 'post_status' => 'any', 'numberposts' => -1]) as $p) wp_delete_post($p->ID, true);
foreach (get_comments(['status' => 'all']) as $c) wp_delete_comment($c->comment_ID, true);
foreach (['category', 'post_tag', 'nav_menu'] as $tx) foreach (get_terms(['taxonomy' => $tx, 'hide_empty' => false]) as $t) if ((int)$t->term_id !== (int)get_option('default_category')) wp_delete_term($t->term_id, $tx);
switch_theme('twentytwentyone');

$admin = get_user_by('login', 'admin');
wp_set_current_user($admin->ID);
$login = wp_generate_password(20, false);
wp_set_password($login, $admin->ID);
wp_update_user(['ID' => $admin->ID, 'display_name' => 'Marco Bianchi', 'first_name' => 'Marco', 'last_name' => 'Bianchi']);
$giulia = get_user_by('login', 'giulia') ?: get_user_by('id', wp_insert_user(['user_login' => 'giulia', 'user_pass' => wp_generate_password(20), 'user_email' => 'giulia@example.org', 'display_name' => 'Giulia Rossi', 'role' => 'author']));

// Categorie annidate e tag.
$cat = [];
$cat['notizie'] = wp_insert_term('Notizie', 'category', ['slug' => 'notizie', 'description' => 'Le novita della bottega'])['term_id'];
$cat['eventi'] = wp_insert_term('Eventi', 'category', ['slug' => 'eventi', 'parent' => $cat['notizie']])['term_id'];
$cat['ricette'] = wp_insert_term('Ricette', 'category', ['slug' => 'ricette'])['term_id'];
$cat['dolci'] = wp_insert_term('Dolci', 'category', ['slug' => 'dolci', 'parent' => $cat['ricette']])['term_id'];
$cat['salati'] = wp_insert_term('Salati', 'category', ['slug' => 'salati', 'parent' => $cat['ricette']])['term_id'];
$tagNomi = ['Farina', 'Lievito madre', 'Stagione', 'Vegano', 'Veloce', 'Forno', 'Tradizione', 'Bambini'];
foreach ($tagNomi as $t) wp_insert_term($t, 'post_tag');

// 15 immagini vere: GD, colori e scritte diverse, JPEG e PNG.
$tmp = sys_get_temp_dir() . '/wp-prova-img-' . getmypid();
@mkdir($tmp);
$img = [];
$colori = [[200, 80, 60], [60, 120, 180], [90, 160, 90], [220, 170, 40], [130, 70, 160]];
for ($i = 1; $i <= 15; $i++) {
    $g = imagecreatetruecolor(1200, 800);
    [$r, $v, $b] = $colori[$i % 5];
    imagefilledrectangle($g, 0, 0, 1200, 800, imagecolorallocate($g, $r, $v, $b));
    for ($k = 0; $k < 12; $k++) imagefilledellipse($g, mt_rand(0, 1200), mt_rand(0, 800), mt_rand(60, 300), mt_rand(60, 300), imagecolorallocatealpha($g, 255, 255, 255, mt_rand(60, 110)));
    imagestring($g, 5, 40, 40, "Foto di prova $i", imagecolorallocate($g, 255, 255, 255));
    $png = $i % 4 === 0;
    $f = "$tmp/foto-prova-$i." . ($png ? 'png' : 'jpg');
    $png ? imagepng($g, $f) : imagejpeg($g, $f, 85);
    imagedestroy($g);
    $id = media_handle_sideload(['name' => basename($f), 'tmp_name' => $f], 0, "Foto di prova $i");
    if (is_wp_error($id)) { fwrite(STDERR, $id->get_error_message() . "\n"); exit(1); }
    update_post_meta($id, '_wp_attachment_image_alt', "Testo alternativo della foto $i");
    $img[$i] = ['id' => $id, 'url' => wp_get_attachment_url($id)];
}
// Un PDF vero (piccolo).
$pdf = "$tmp/listino-prezzi.pdf";
file_put_contents($pdf, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
$pdfId = media_handle_sideload(['name' => 'listino-prezzi.pdf', 'tmp_name' => $pdf], 0, 'Listino prezzi');
$pdfUrl = wp_get_attachment_url($pdfId);

$blocco = [
    'p' => fn($t) => "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->\n\n",
    'h' => fn($t) => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$t</h2>\n<!-- /wp:heading -->\n\n",
    'img' => fn($n) => "<!-- wp:image {\"id\":{$img[$n]['id']},\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"" . (wp_get_attachment_image_src($img[$n]['id'], 'large')[0]) . "\" alt=\"Testo alternativo della foto $n\" class=\"wp-image-{$img[$n]['id']}\"/><figcaption class=\"wp-element-caption\">Didascalia della foto $n</figcaption></figure>\n<!-- /wp:image -->\n\n",
    'lista' => fn() => "<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>Farina di grano tenero</li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li>Acqua tiepida</li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li>Un pizzico di sale</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n",
    'cit' => fn() => "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>Il pane buono chiede tempo.</p>\n<!-- /wp:paragraph --><cite>La nonna</cite></blockquote>\n<!-- /wp:quote -->\n\n",
    'tab' => fn() => "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr><th>Ingrediente</th><th>Grammi</th></tr></thead><tbody><tr><td>Farina</td><td>500</td></tr><tr><td>Acqua</td><td>350</td></tr></tbody></table></figure>\n<!-- /wp:table -->\n\n",
    'embed' => fn() => "<!-- wp:embed {\"url\":\"https://www.youtube.com/watch?v=dQw4w9WgXcQ\",\"type\":\"video\",\"providerNameSlug\":\"youtube\",\"responsive\":true} -->\n<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube\"><div class=\"wp-block-embed__wrapper\">\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\n</div></figure>\n<!-- /wp:embed -->\n\n",
    'gal' => fn() => "<!-- wp:gallery {\"linkTo\":\"none\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">" . implode('', array_map(fn($n) => "<!-- wp:image {\"id\":{$img[$n]['id']},\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"" . (wp_get_attachment_image_src($img[$n]['id'], 'large')[0]) . "\" alt=\"Galleria $n\" class=\"wp-image-{$img[$n]['id']}\"/></figure>\n<!-- /wp:image -->", [11, 12, 13])) . "</figure>\n<!-- /wp:gallery -->\n\n",
];

$titoli = ['Il pane di casa', 'Focaccia alta e soffice', 'La crostata della domenica', 'Biscotti al burro', 'Pizza in teglia', 'Grissini torinesi',
    'Il lievito madre spiegato bene', 'Torta di mele della nonna', 'Taralli pugliesi', 'Ciambellone morbido', 'Pane integrale', 'Cornetti sfogliati',
    'Apriamo il sabato pomeriggio', 'La festa del pane a ottobre', 'Corso di panificazione per bambini', 'Nuovi orari della bottega', 'Il forno a legna nuovo',
    'Panettone artigianale', 'Pasta frolla senza segreti', 'Muffin ai mirtilli', 'Schiacciata all\'uva', 'Pan di Spagna', 'Calzoni fritti',
    'Brioche col tuppo', 'Tarallucci al vino', 'Ricetta veloce: piadina', 'Ricetta col sorriso &#x1F600;', 'Bozza: il pane nero', 'Bozza: dolci di Natale',
    'Bozza: farine antiche', 'Bozza: idee per l\'estate', 'In revisione: la pinsa', 'Programmato: la colomba di Pasqua'];
$cats = [['ricette'], ['ricette', 'salati'], ['dolci'], ['dolci'], ['salati'], ['salati'], ['ricette'], ['dolci'], ['salati'], ['dolci'], ['ricette'], ['dolci'],
    ['notizie'], ['eventi'], ['eventi'], ['notizie'], ['notizie'], ['dolci'], ['dolci'], ['dolci'], ['dolci'], ['dolci'], ['salati'], ['dolci'], ['dolci'], ['salati'], ['ricette'],
    ['ricette'], ['dolci'], ['ricette'], ['notizie'], ['salati'], ['dolci']];
$post = [];
foreach ($titoli as $i => $titolo) {
    $n = $i + 1;
    $stato = $n <= 27 ? 'publish' : ($n <= 31 ? 'draft' : ($n === 32 ? 'pending' : 'future'));
    $data = $stato === 'future' ? '2027-03-20 09:30:00' : date('Y-m-d H:i:s', strtotime('2024-02-01 08:15:00') + $i * 86400 * 23 + $i * 3700);
    $c = $blocco['p']("Questo e il testo dell'articolo <strong>$n</strong>, con un <a href=\"" . home_url('/chi-siamo/') . "\">link alla pagina Chi siamo</a>.");
    if ($n % 3 === 0) $c .= $blocco['h']('Gli ingredienti') . $blocco['lista']();
    if ($n % 4 === 0) $c .= $blocco['img'](($n % 10) + 1);
    if ($n % 5 === 0) $c .= $blocco['cit']();
    if ($n === 6) $c .= $blocco['tab']();
    if ($n === 7) $c .= $blocco['embed']() . $blocco['gal']();
    if ($n === 9) $c .= $blocco['p']('Scrivici: [contact-form-7 id="12" title="Contatti"]');
    if ($n === 10) $c .= $blocco['p']("Scarica il <a href=\"$pdfUrl\">listino dei prezzi</a>.");
    if ($n === 27) $c .= $blocco['p']('Un sorriso: &#x1F600; e una stella &#x2B50;');
    if ($n === 2) $c .= $blocco['p']('Guarda anche <a href="' . home_url('/category/ricette/salati/') . '">tutti i salati</a>.');
    $id = wp_insert_post([
        'post_title' => html_entity_decode($titolo, ENT_QUOTES, 'UTF-8'), 'post_content' => html_entity_decode($c, ENT_NOQUOTES, 'UTF-8'), 'post_status' => $stato,
        'post_date' => $data, 'post_author' => $n % 4 === 0 ? $giulia->ID : $admin->ID,
        'post_excerpt' => $n % 6 === 0 ? "Riassunto scritto a mano dell'articolo $n." : '',
        'post_category' => array_map(fn($s) => $cat[$s], $cats[$i]),
        'tags_input' => [$tagNomi[$i % 8], $tagNomi[($i + 3) % 8]],
    ], true);
    if (is_wp_error($id)) { fwrite(STDERR, $id->get_error_message() . "\n"); exit(1); }
    if ($n <= 20) set_post_thumbnail($id, $img[(($n - 1) % 15) + 1]['id']);
    if ($n % 7 === 1) { update_post_meta($id, '_yoast_wpseo_title', "SEO: $titolo %%sep%% %%sitename%%"); update_post_meta($id, '_yoast_wpseo_metadesc', "La descrizione SEO dell'articolo $n, scritta in Yoast."); }
    $post[] = $id;
}

// Pagine: una figlia, una con uno shortcode, una "fatta con Elementor".
$pagina = fn($t, $s, $c, $padre = 0, $stato = 'publish', $ordine = 0) => wp_insert_post(['post_type' => 'page', 'post_title' => $t, 'post_name' => $s, 'post_content' => $c, 'post_status' => $stato, 'post_parent' => $padre, 'menu_order' => $ordine, 'post_author' => $admin->ID, 'post_date' => '2024-01-10 10:00:00']);
$chi = $pagina('Chi siamo', 'chi-siamo', $blocco['p']('Siamo una bottega di pane dal 1962.') . $blocco['img'](14), 0, 'publish', 1);
update_post_meta($chi, '_yoast_wpseo_metadesc', 'Chi siamo: la storia della bottega.');
set_post_thumbnail($chi, $img[14]['id']);
$team = $pagina('Il team', 'il-team', $blocco['p']('Marco, Giulia e il forno.'), $chi, 'publish', 2);
$contatti = $pagina('Contatti', 'contatti', $blocco['p']('Scrivici con il modulo qui sotto.') . "<!-- wp:shortcode -->\n[contact-form-7 id=\"12\" title=\"Contatti\"]\n<!-- /wp:shortcode -->", 0, 'publish', 3);
$servizi = $pagina('Servizi', 'servizi', $blocco['p']('Pane su ordinazione, corsi, catering.') . $blocco['tab'](), 0, 'publish', 4);
$promo = $pagina('Promozioni', 'promozioni', '<div class="elementor elementor-77"><div class="elementor-section"><div class="elementor-widget-container"><h2>Promo di ottobre</h2><p>Due pani al prezzo di uno.</p></div></div></div>', 0, 'publish', 5);
update_post_meta($promo, '_elementor_edit_mode', 'builder');
$privacy = $pagina('Informativa privacy', 'informativa-privacy', $blocco['p']('Testo da completare.'), 0, 'draft', 6);

// Commenti (non importabili: il report lo dice).
for ($k = 0; $k < 5; $k++) wp_insert_comment(['comment_post_ID' => $post[$k], 'comment_author' => "Lettore $k", 'comment_author_email' => "lettore$k@example.org", 'comment_content' => 'Che buono!', 'comment_approved' => 1]);

// Menu con sottovoci, nella posizione "primary" del tema.
$menuId = wp_create_nav_menu('Menu principale');
$voce = fn(array $a) => wp_update_nav_menu_item($menuId, 0, $a + ['menu-item-status' => 'publish']);
$voce(['menu-item-title' => 'Home', 'menu-item-url' => home_url('/'), 'menu-item-type' => 'custom']);
$vChi = $voce(['menu-item-object' => 'page', 'menu-item-object-id' => $chi, 'menu-item-type' => 'post_type']);
$voce(['menu-item-object' => 'page', 'menu-item-object-id' => $team, 'menu-item-type' => 'post_type', 'menu-item-parent-id' => $vChi]);
$vRic = $voce(['menu-item-object' => 'category', 'menu-item-object-id' => $cat['ricette'], 'menu-item-type' => 'taxonomy']);
$voce(['menu-item-object' => 'category', 'menu-item-object-id' => $cat['dolci'], 'menu-item-type' => 'taxonomy', 'menu-item-parent-id' => $vRic]);
$voce(['menu-item-object' => 'post', 'menu-item-object-id' => $post[0], 'menu-item-type' => 'post_type', 'menu-item-title' => 'Il nostro pane', 'menu-item-parent-id' => $vRic]);
$voce(['menu-item-object' => 'page', 'menu-item-object-id' => $contatti, 'menu-item-type' => 'post_type']);
$voce(['menu-item-title' => 'Il mulino amico', 'menu-item-url' => 'https://example.org/mulino', 'menu-item-type' => 'custom', 'menu-item-target' => '_blank']);
set_theme_mod('nav_menu_locations', ['primary' => $menuId]);
// Qualcosa di WordPress, alla chiusura di questo processo, rimette la posizione di prima: si riscrive da un processo a parte, alla fine.
$posizione = $menuId;

// La password per le applicazioni: nuova a ogni giro (le vecchie si tolgono).
WP_Application_Passwords::delete_all_application_passwords($admin->ID);
[$app] = WP_Application_Passwords::create_new_application_password($admin->ID, ['name' => 'plumeCMS']);
$scrivi = function ($f, $s) use ($segreti) { file_put_contents("$segreti/$f", $s); chmod("$segreti/$f", 0600); };
$scrivi('app.txt', WP_Application_Passwords::chunk_password($app));
$scrivi('login.txt', $login);

// Quello che l'import deve trovare.
$atteso = ['home' => home_url(), 'articoli' => [], 'pagine' => [], 'categorie' => [], 'tag' => count($tagNomi), 'media' => 16, 'immagini' => 15, 'commenti' => 5, 'autori' => 2, 'menu_voci' => 8];
foreach ($post as $id) {
    $p = get_post($id);
    $atteso['articoli'][] = ['titolo' => $p->post_title, 'slug' => trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', rawurldecode($p->post_name ?: sanitize_title($p->post_title))), '-'), 'stato' => $p->post_status, 'data' => $p->post_date,
        'categorie' => wp_get_post_categories($id, ['fields' => 'slugs']), 'evidenza' => has_post_thumbnail($id), 'link' => get_permalink($id), 'id' => $id];
}
foreach ([$chi, $team, $contatti, $servizi, $promo, $privacy] as $id) {
    $p = get_post($id);
    $atteso['pagine'][] = ['titolo' => $p->post_title, 'slug' => $p->post_name, 'stato' => $p->post_status, 'link' => get_permalink($id), 'id' => $id];
}
foreach (get_terms(['taxonomy' => 'category', 'hide_empty' => false]) as $t) $atteso['categorie'][] = ['slug' => $t->slug, 'nome' => $t->name, 'link' => get_term_link($t)];
$scrivi('atteso.json', json_encode($atteso, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
array_map('unlink', glob("$tmp/*")); @rmdir($tmp);
passthru(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('$_SERVER["HTTP_HOST"] = ' . var_export($_SERVER['HTTP_HOST'], true) . '; $_SERVER["REQUEST_URI"] = "/"; require ' . var_export("$dir/wp-load.php", true) . '; set_theme_mod("nav_menu_locations", ["primary" => ' . (int)$posizione . ']);'));
echo 'WordPress riempito: ' . count($post) . " articoli, 6 pagine, 16 media. Segreti in $segreti\n";
