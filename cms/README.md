# plumeCMS

Un CMS in PHP e MySQL con un editor visuale a sezioni, colonne e widget, header e footer disegnati nell'editor, menu,
stile del sito in un posto solo, SEO e AEO (sitemap, robots, JSON-LD, IndexNow, Search Console, llms.txt), newsletter
con doppia conferma e flussi automatici, form, popup, utenti, pacchetti di aggiornamento (con l'avviso della versione
nuova), copia del sito scaricabile e ripristinabile, sezioni pronte nell'editor e kit di partenza nell'installatore.

Nasce dal publisher di danilofailli.it: il nucleo è generico, quello che è di quel sito sta in `moduli/danilofailli/`.

- Installazione: `INSTALLA.md` (cinque passi dal browser).
- **Manuale tecnico**, tutto il CMS pezzo per pezzo: `docs/manuale-tecnico.md`.
- **Manuale dei temi**, per costruirsi il proprio tema: `docs/manuale-temi.md`.
- Com'è fatto e come si estende: `docs/cms.md`, `docs/cms-moduli.md`, `docs/moduli.md`.
- Editor, stile, header e footer, SEO, pannello: `docs/editor-pagine.md`, `docs/stile-sito.md`, `docs/header-footer-menu.md`, `docs/seo.md`, `docs/pannello.md`.
- Novità per versione: `CHANGELOG.md`. Versione corrente: file `VERSIONE`.

Requisiti: PHP 8.2 o più recente (collaudato con 8.4) con PDO MySQL, mbstring, openssl, json; MySQL 8 o MariaDB 10.6+;
Apache con mod_rewrite oppure Nginx. Nessuna libreria esterna da installare: Quill e SortableJS sono dentro.
