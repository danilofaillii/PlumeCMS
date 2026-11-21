-- Dati finti come quelli del pannello di prima (danilofailli.it), per provare social_porta_dati_vecchi() (1.5.0).
-- Prefisso cms_: si cambia se l installazione ne ha un altro. Poi: php -r con social_installa(), vedi prove/LEGGIMI.md.
DROP TABLE IF EXISTS cms_social_contenuti; DROP TABLE IF EXISTS cms_social_programmati;
DELETE FROM cms_settings WHERE setting_key IN ('social_schema','social_dati_vecchi');
ALTER TABLE cms_articles ADD COLUMN social_summary TEXT;
UPDATE cms_articles SET social_summary = 'Il trafiletto di prima.' WHERE id = 1;
DROP TABLE IF EXISTS cms_social_pillar;
CREATE TABLE cms_social_pillar (id INT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(191) NOT NULL, giorno TINYINT UNSIGNED NOT NULL, data_invio DATE NOT NULL, categoria VARCHAR(64) NOT NULL DEFAULT '', keyword VARCHAR(191) NOT NULL DEFAULT '', titolo_pillar VARCHAR(255) NOT NULL DEFAULT '', ancora VARCHAR(191) NOT NULL DEFAULT '', titolo_sezione VARCHAR(255) NOT NULL DEFAULT '', linkedin TEXT NOT NULL, facebook_instagram TEXT NOT NULL, whatsapp TEXT NOT NULL, immagine_og VARCHAR(255) NOT NULL DEFAULT '', immagine_ig VARCHAR(255) NOT NULL DEFAULT '', inviato_at DATETIME NULL DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO cms_social_pillar (slug, giorno, data_invio, titolo_pillar, ancora, titolo_sezione, linkedin, facebook_instagram, whatsapp, immagine_og, immagine_ig, inviato_at) VALUES
('benvenuto', 1, CURDATE() - INTERVAL 1 DAY, 'Il pillar', '', '', 'li1', 'fb1', 'wa1', '2025/10/og1.png', '2025/10/ig1.png', NOW()),
('benvenuto', 2, CURDATE(), 'Il pillar', 'cosa-fare-adesso', 'Cosa fare adesso', 'li2', 'fb2', 'wa2', '', '', NULL),
('non-esiste', 1, CURDATE(), 'x', '', '', 'x', 'x', 'x', '', '', NULL);
