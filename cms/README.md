# plumeCMS

Un CMS in PHP e MySQL con un editor visuale a sezioni, colonne e widget, header e footer disegnati nell'editor, menu,
stile del sito in un posto solo, SEO e AEO (sitemap, robots, JSON-LD, IndexNow, Search Console, llms.txt), newsletter
con doppia conferma e flussi automatici, form, popup, utenti, pacchetti di aggiornamento.

Il nucleo è generico: quello che è di un sito solo sta nel suo modulo, in `moduli/<nome>/` (`docs/cms-moduli.md`).

- Installazione: `INSTALLA.md` (cinque passi dal browser).
- **Manuale tecnico**, tutto il CMS pezzo per pezzo: `docs/manuale-tecnico.md`.
- **Manuale dei temi**, per costruirsi il proprio tema: `docs/manuale-temi.md`.
- Com'è fatto e come si estende: `docs/cms.md`, `docs/cms-moduli.md`, `docs/moduli.md`.
- Editor, stile, header e footer, SEO, pannello: `docs/editor-pagine.md`, `docs/stile-sito.md`, `docs/header-footer-menu.md`, `docs/seo.md`, `docs/pannello.md`.
- Novità per versione: `CHANGELOG.md`. Versione corrente: file `VERSIONE`.

Requisiti: PHP 8.2 o più recente (collaudato con 8.4) con PDO MySQL, mbstring, openssl, json; MySQL 8 o MariaDB 10.6+;
Apache con mod_rewrite oppure Nginx. Nessuna libreria esterna da installare: Quill e SortableJS sono dentro.

## Licenza

plumeCMS è software libero: puoi usarlo, studiarlo, modificarlo e ridistribuirlo secondo i termini della
GNU General Public License, versione 3 o (a tua scelta) una versione successiva, pubblicata dalla Free Software
Foundation. Il testo completo è nel file `LICENSE`.

Chi ridistribuisce plumeCMS, anche modificato, deve farlo con la stessa licenza e con il codice sorgente. I siti fatti
con plumeCMS, i loro contenuti e i temi e moduli che non ne copiano il codice restano di chi li fa.

plumeCMS è distribuito sperando che sia utile, ma SENZA ALCUNA GARANZIA, nemmeno implicita di COMMERCIABILITÀ o di
IDONEITÀ A UNO SCOPO PARTICOLARE. Per i dettagli, la GNU General Public License.

Copyright (C) 2026 Danilo Failli.
