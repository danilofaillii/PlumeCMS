<?php
/**
 * Plugin Name: plumeCMS, prova dell'import
 * Description: Solo per il WordPress di prova di prove/wp-installa.php. Imita Yoast SEO nella REST (il campo
 * yoast_head_json con titolo e descrizione, calcolati dai meta _yoast_wpseo_title e _yoast_wpseo_metadesc come fa
 * Yoast) e, con la variabile d'ambiente WP_REST_CHIUSA=1 sul server, chiude la REST come un plugin di sicurezza.
 * Mai su un sito vero.
 */

// La REST chiusa da un "plugin di sicurezza": tutte le richieste, anche con le credenziali, ricevono 403.
add_filter('rest_authentication_errors', function ($e) {
    if (getenv('WP_REST_CHIUSA') === '1') return new WP_Error('rest_disabled', 'The REST API has been disabled by an administrator.', ['status' => 403]);
    return $e;
}, 99);

// yoast_head_json come lo scrive Yoast: titolo con il modello "%%title%% %%sep%% %%sitename%%" se il meta manca.
add_action('rest_api_init', function () {
    foreach (['post', 'page'] as $tipo) {
        register_rest_field($tipo, 'yoast_head_json', ['get_callback' => function ($p) {
            $id = (int)$p['id'];
            $sito = get_bloginfo('name');
            $t = (string)get_post_meta($id, '_yoast_wpseo_title', true);
            $t = $t !== '' ? str_replace(['%%title%%', '%%sep%%', '%%sitename%%'], [get_the_title($id), '-', $sito], $t) : get_the_title($id) . ' - ' . $sito;
            $d = (string)get_post_meta($id, '_yoast_wpseo_metadesc', true);
            $out = ['title' => html_entity_decode($t, ENT_QUOTES, 'UTF-8'), 'robots' => ['index' => 'index', 'follow' => 'follow'], 'og_type' => 'article'];
            if ($d !== '') { $out['description'] = $d; $out['og_description'] = $d; }
            return $out;
        }]);
    }
});

// 2.19.1: guasti finti per provare il ponte (prova-ponte.js). L'opzione plume_prova_guasto dice quale:
// "eccezione" un errore che si può intercettare mentre WordPress prepara le categorie, "fatale" un errore fatale nei filtri
// dopo la risposta (come certi plugin su rest_post_dispatch). Vuota: niente.
add_action('rest_api_init', function () {
    $g = get_option('plume_prova_guasto');
    if ($g === 'eccezione') add_filter('rest_prepare_category', function () { throw new RuntimeException('guasto di prova nelle categorie'); });
    if ($g === 'fatale') add_filter('rest_post_dispatch', function ($r) { if (isset($_GET['plume_ponte'])) trigger_error('guasto fatale di prova nei filtri', E_USER_ERROR); return $r; }, 5);
});

// 2.19.1: "media", la prima immagine ha le misure salvate come testo vuoto (il caso di arketypos.it, un logo PNG del 2022):
// la REST di WordPress va in errore se le si chiede media_details (string * float in wp_constrain_dimensions).
add_filter('wp_get_attachment_metadata', function ($m, $id) {
    if (get_option('plume_prova_guasto') !== 'media' || !is_array($m)) return $m;
    global $wpdb;
    $primo = (int)$wpdb->get_var("SELECT MIN(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");
    if ((int)$id !== $primo) return $m;
    $m['width'] = ''; $m['height'] = '';
    foreach ((array)($m['sizes'] ?? []) as $k => $v) { $m['sizes'][$k]['width'] = ''; $m['sizes'][$k]['height'] = ''; }
    return $m;
}, 10, 2);
