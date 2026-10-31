# Installare il CMS

## In cinque passi

1. **Scarica e carica.** Estrai lo zip del prodotto nella cartella del sito sul server (con cPanel: Gestione file, dentro
   `public_html` per la radice del dominio, oppure dentro una sottocartella come `public_html/cms`).
2. **Il database.** Da cPanel, voce *Database MySQL*: crea un database e un utente con tutti i privilegi su quel database.
   Tieni a portata di mano nome, utente e password.
3. **Apri il sito nel browser.** Finché non esiste `includes/config.local.php`, qualsiasi indirizzo apre l'installatore
   (`/install/`). Il primo passo controlla i requisiti: PHP 8.2 o più recente, le estensioni, le cartelle scrivibili
   (`uploads/`, `includes/`, `aggiornamenti/`, `logs/`).
4. **Compila i tre passi**: database (con il prefisso delle tabelle, di norma `cms_`), sito (nome, descrizione, indirizzo
   rilevato e modificabile, lingua, fuso orario, email) e amministratore (nome utente, email, password di almeno 12 caratteri, non tra le più comuni).
5. **Scegli che sito vuoi fare e installa.** Vuoto (le pagine di sempre), Personale, Attività, Blog o Portfolio: con un
   kit il CMS parte con la home, le pagine principali e il menu già costruiti con l'editor, e testi d'esempio da
   sostituire. L'installatore scrive `includes/config.local.php`, crea le tabelle, l'amministratore e i contenuti di
   partenza: la home nell'editor, le pagine con un form dei contatti, un articolo di benvenuto, header e footer con il
   menu Principale, lo stile predefinito. Da quel momento `/install/` rimanda al pannello (`/admin/`).

## Dopo l'installazione

- **Il cron.** Da cPanel, voce *Cron Jobs*: `php /percorso/del/cms/cron-daily.php` una volta al giorno (alle 06:00) e
  `php /percorso/del/cms/cron-newsletter.php` ogni 5 minuti. I cron girano solo da riga di comando: dal web rispondono
  403. Se il tuo hosting sa lanciare un cron solo come indirizzo web, vedi il manuale d'uso, capitolo 26 (`CMS_CRON_TOKEN`).
- **Le email.** Pannello, *Impostazioni, Configurazione email*: il server SMTP del tuo hosting. Senza, il CMS usa `mail()`.
- **Il .htaccess (importante per la sicurezza).** Lo zip contiene un `.htaccess` per la radice del dominio
  (è `install/esempi/htaccess-radice.txt`): nega dal web le cartelle riservate (`includes` con `config.local.php`,
  `logs`, `aggiornamenti`, `sql`, `docs`, `tools`, `test`), i file `VERSIONE`, `*.md`, `*.log`, `*.sql`, e spegne
  PHP nella cartella delle immagini. In una sottocartella usa `install/esempi/htaccess-sottocartella.txt`. In
  `uploads/` c'è già un `.htaccess` che spegne PHP e manda gli SVG in sandbox (`install/esempi/htaccess-uploads.txt`).
  **Per Nginx** (che non legge gli `.htaccess`): copia le regole di `install/esempi/nginx.txt` dentro il blocco
  `server { }`. La pagina Sicurezza del pannello prova da sola a leggere `config.local.php` e i log dal web e ti
  avvisa se il server li serve.

## Sicurezza

Dopo l'installazione, dal pannello: *Impostazioni, Sicurezza*. Da lì attivi il secondo fattore, decidi il limite ai
tentativi di login, le scadenze della sessione, la Content-Security-Policy e altro. La guida passo per passo, con cosa
fare se ti chiudi fuori, è nel manuale d'uso, capitolo 27. Conviene anche togliere la cartella `install/` quando il sito è in
piedi (la pagina Sicurezza ha un pulsante che lo fa).

## Radice o sottocartella

Il CMS capisce da solo dove sta (`cms_base()`): nella radice del dominio serve il sito dal suo `index.php`; in una
sottocartella (per esempio `/cms/`) il pannello risponde a `/cms/admin/` e il sito pubblico vive nella radice con un
`index.php` di una riga (`install/esempi/index-radice.php`) e il `.htaccess` descritto in
`install/esempi/htaccess-sottocartella.txt`. Se il rilevamento sbaglia, nel `config.local.php`: `define('CMS_BASE', '/cms');`.

Le immagini stanno in `uploads/` dentro il CMS. Per metterle altrove: `CMS_UPLOADS` e `CMS_UPLOADS_URL` nel `config.local.php`.

## Aggiornare

Pannello, *Impostazioni, Aggiornamenti*: carica il pacchetto `<nome>-<versione>-aggiornamento.zip`. Il pannello fa uno
snapshot, sostituisce i file del nucleo e lascia moduli, immagini, configurazione e database. Per tornare indietro c'è il ripristino.

## Se qualcosa va storto

- L'installatore si ferma con un errore: il dettaglio è in `logs/php-errori.log`. Un'installazione fallita a metà cancella
  il `config.local.php` appena scritto, così si può ripartire dal passo del database.
- Pagina bianca: con cPanel, *Select PHP Version*, controlla che `pdo_mysql` e `mbstring` siano accese.
- Il sito risponde ma gli indirizzi interni danno 404: manca il `.htaccess` oppure `mod_rewrite` è spento.
