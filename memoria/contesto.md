# Contesto

- **Proprietario**: Danilo Failli. Non è uno sviluppatore: istruzioni passo per passo.
- **Primo cliente**: danilofailli.it, su Hostcare (PHP 8.4.27, MySQL 8.0.46, Apache 2.4.69, cPanel 138).
  Usa plumeCMS in `/new/` con il suo modulo `moduli/danilofailli/`, nel suo repository.
- **Nome**: plumeCMS, scelto il 26 ottobre 2026. Prima "Officina" (provvisorio) e "CarbonaraCMS".
- **Riferimento per l'editor**: Elementor Pro 4.3.2 (codice letto per la specifica del 25 ottobre 2026; la specifica è rimasta fuori dal repository).

## Glossario
- **Nucleo**: il CMS generico (`cms/includes`, `admin`, `sito`, `api`, `install`).
- **Modulo**: una cartella in `cms/moduli/<nome>/` con `modulo.php`, che aggiunge tipi di contenuto, pagine, menu, filtri, cron.
- **Pacchetto**: uno zip per il pannello Aggiornamenti (file, SQL, redirect, file da togliere), con snapshot e ripristino automatico.
- **Versioni**: la cronologia di ogni modifica fatta con l'editor, in `<prefisso>editor_versioni`.
- **CSP**: Content-Security-Policy, la regola che dice al browser quali script eseguire. Parte in "solo rapporto", poi "applica".
