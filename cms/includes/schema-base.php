<?php
/**
 * Le tabelle di base del CMS, per un'installazione nuova: articoli, pagine, categorie, tag, media, utenti, impostazioni.
 * Sono le stesse del publisher di oggi (ricavate dal database vivo il 26 ottobre 2026), con il prefisso di tbl() e senza
 * nomi del sito di partenza. Le colonne aggiunte negli anni le garantisce comunque sito_schema_aggiorna().
 * Le altre tabelle le creano i loro file: sito.php (glossario, redirect, revisioni), seo.php, design.php, menu-nav.php,
 * modelli.php, form.php, popup.php, moduli.php, newsletter.php, newsletter-flussi.php, editor-temi.php; i moduli le loro.
 */

function cms_schema_base(): array {
    return [
        'articles' => "CREATE TABLE IF NOT EXISTS " . tbl('articles') . "  (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(500) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `keyword_target` varchar(255) DEFAULT '',
  `keywords_secondarie` text,
  `meta_description` text,
  `excerpt` text,
  `content` longtext,
  `featured_image` varchar(500) DEFAULT '',
  `featured_image_alt` varchar(500) DEFAULT '',
  `type` enum('pillar','cluster') DEFAULT 'cluster',
  `category` varchar(100) DEFAULT '',
  `tags` varchar(500) DEFAULT '',
  `cta` varchar(100) DEFAULT '',
  `pillar_ref` varchar(255) DEFAULT '',
  `pillar_title` varchar(500) DEFAULT '',
  `week` tinyint DEFAULT '1',
  `day` varchar(20) DEFAULT '',
  `publish_date` date DEFAULT NULL,
  `status` enum('draft','published','scheduled') DEFAULT 'draft',
  `seo_title` varchar(255) DEFAULT '',
  `seo_description` text,
  `infographic_type` enum('none','steps','checklist','compare','stats','quote','flow') DEFAULT 'none',
  `infographic_title` varchar(500) DEFAULT '',
  `infographic_data` text,
  `noindex` tinyint(1) DEFAULT '0',
  `author` varchar(100) DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `wp_post_id` int DEFAULT NULL,
  `wp_hash` varchar(32) DEFAULT NULL,
  `publish_time` time NOT NULL DEFAULT '06:00:00',
  `content_format` varchar(10) NOT NULL DEFAULT 'markdown',
  `tldr` text,
  `faq` text,
  `robots` varchar(10) NOT NULL DEFAULT '',
  `categorie` varchar(500) NOT NULL DEFAULT '',
  `tag_slugs` text,
  `immagine_url` varchar(500) NOT NULL DEFAULT '',
  `wp_id` int DEFAULT NULL,
  `cestino` datetime DEFAULT NULL,
  `struttura` longtext,
  `struttura_bozza` longtext,
  `struttura_aggiornata` datetime DEFAULT NULL,
  `struttura_origine` varchar(80) NOT NULL DEFAULT '',
  `header_id` int NOT NULL DEFAULT '0',
  `footer_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `publish_date` (`publish_date`),
  KEY `category` (`category`),
  KEY `status` (`status`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'categories' => "CREATE TABLE IF NOT EXISTS " . tbl('categories') . "  (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `robots` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'tags' => "CREATE TABLE IF NOT EXISTS " . tbl('tags') . "  (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'media' => "CREATE TABLE IF NOT EXISTS " . tbl('media') . "  (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `path` varchar(500) NOT NULL,
  `alt` varchar(500) DEFAULT '',
  `caption` varchar(500) DEFAULT '',
  `mime_type` varchar(100) DEFAULT '',
  `file_size` int DEFAULT '0',
  `width` int DEFAULT '0',
  `height` int DEFAULT '0',
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'users' => "CREATE TABLE IF NOT EXISTS " . tbl('users') . "  (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(200) DEFAULT '',
  `role` enum('admin','editor') DEFAULT 'editor',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'settings' => "CREATE TABLE IF NOT EXISTS " . tbl('settings') . "  (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'pages' => "CREATE TABLE IF NOT EXISTS " . tbl('pages') . "  (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(500) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `meta_description` varchar(500) DEFAULT '',
  `blocks` longtext,
  `status` enum('draft','published') DEFAULT 'published',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `html` longtext,
  `seo_title` varchar(255) NOT NULL DEFAULT '',
  `seo_description` text,
  `robots` varchar(10) NOT NULL DEFAULT '',
  `tldr` text,
  `faq` text,
  `servizio` text,
  `immagine_url` varchar(500) NOT NULL DEFAULT '',
  `publish_at` datetime DEFAULT NULL,
  `wp_id` int DEFAULT NULL,
  `struttura` longtext,
  `struttura_bozza` longtext,
  `struttura_aggiornata` datetime DEFAULT NULL,
  `cestino` datetime DEFAULT NULL,
  `excerpt` text,
  `author` varchar(100) NOT NULL DEFAULT '',
  `publish_time` time NOT NULL DEFAULT '06:00:00',
  `header_id` int NOT NULL DEFAULT '0',
  `footer_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

/**
 * Crea o aggiorna tutto: tabelle di base, colonne del sito, tabelle di ogni voce del pannello, viste, tabelle dei moduli.
 * La chiama l'installatore; si può richiamare senza danni (tutto è IF NOT EXISTS o idempotente). Torna le righe di log.
 */
function cms_installa_tutto(): array {
    $log = [];
    foreach (cms_schema_base() as $nome => $sql) { db()->exec($sql); $log[] = "tabella $nome"; }
    $inc = dirname(__DIR__) . '/includes/';
    foreach (['functions', 'sito', 'contenuti', 'editor', 'editor-temi', 'design', 'menu-nav', 'modelli', 'seo', 'form', 'popup', 'moduli', 'newsletter', 'newsletter-flussi', 'media', 'versioni', 'scaricabili'] as $f) {
        if (is_file($inc . $f . '.php')) require_once $inc . $f . '.php';
    }
    foreach (sito_schema_aggiorna() as $r) $log[] = $r;
    foreach (array_merge(contenuti_schema_tabelle(), sito_schema_tabelle()) as $nome => $sql) { db()->exec($sql); $log[] = "tabella $nome"; }
    foreach (['editor_temi_schema', 'design_installa', 'menu_installa', 'modelli_installa', 'seo_installa', 'form_schema', 'popup_schema', 'moduli_schema', 'newsletter_schema', 'newsletter_flussi_schema', 'versioni_installa', 'scaricabili_installa'] as $fn) {
        if (function_exists($fn)) { $fn(); $log[] = $fn; }
    }
    sito_viste(); $log[] = 'viste degli articoli';
    foreach (cms_moduli_installa() as $r) $log[] = $r;
    return $log;
}
