# plumeCMS: manuale tecnico

Versione del manuale: 1.11, per plumeCMS 1.5.0 (28 ottobre 2026).

Questo manuale descrive come è fatto plumeCMS e come funziona, pezzo per pezzo. È scritto leggendo il codice:
ogni numero, limite e nome di funzione viene dai file veri, con il riferimento `file:riga` quando serve a chi
vuole controllare. Dove un documento più vecchio della cartella `docs/` dice una cosa diversa, vale il codice;
l'elenco di quei punti sta nell'appendice A.

I percorsi sono relativi alla cartella del CMS, quella che si carica sul server (nel repository è `cms/`).
`includes/sito.php` vuol dire `cms/includes/sito.php`.

Per costruire un tema c'è un manuale a parte: `docs/manuale-temi.md`. Per **usare** plumeCMS (scrivere, costruire
pagine, gestire il sito) c'è il manuale d'uso, `docs/manuale-uso.md`, con le schermate: questo racconta come è fatto dentro.

## Indice

1. Cos'è plumeCMS e come è fatto
2. Requisiti e installazione
3. Configurazione: `config.local.php` e le costanti
4. Il percorso di una richiesta
5. Il CMS in una cartella: base URL
6. Il database
7. Il pannello e i ruoli
8. Contenuti: articoli, pagine, categorie, tag
9. L'editor visuale
10. Stile del sito
11. Header, footer e menu
12. Temi
13. Versioni
14. Media
15. Form, popup, newsletter e posta
16. SEO e AEO, file calcolati, feed
17. Moduli: manifest, filtri, eventi
18. Sicurezza
19. Aggiornamenti e pacchetti
20. Cron
21. Il server: `.htaccess` e Nginx
22. Errori e log
23. Strumenti e prove
24. Risoluzione dei problemi
25. Riferimento rapido delle funzioni
26. Import da WordPress (dalla 1.4.0)

Appendice A. Dove i documenti vecchi non corrispondono al codice

---

## 1. Cos'è plumeCMS e come è fatto

plumeCMS è un CMS in PHP e MySQL. Al centro c'è un editor visuale a sezioni, colonne e widget, con le schede
Contenuto, Stile e Avanzate per ogni elemento. Intorno all'editor: contenuti (articoli e pagine), stile globale
del sito, header e footer costruiti con l'editor, menu, temi, versioni di ogni modifica, form, popup, newsletter,
SEO, sicurezza e aggiornamenti dal pannello.

Il CMS è fatto di un **nucleo** generico e di **moduli** facoltativi. Il nucleo è quello che c'è nello zip del
prodotto; i moduli stanno in `moduli/<nome>/` e aggiungono pagine, tipi di contenuto, widget e comportamenti
attraverso filtri ed eventi (capitolo 17). Nel prodotto la cartella `moduli/` è vuota.

Niente librerie esterne: SortableJS e Quill sono serviti dal CMS stesso, le icone sono disegnate in casa.

plumeCMS è software libero, con licenza **GNU GPL versione 3 o successiva** (dalla 1.2.8): il testo è in `LICENSE`, nella
cartella del CMS. Chi lo ridistribuisce, anche modificato, lo fa con la stessa licenza e con il codice sorgente; i siti
fatti con plumeCMS, i loro contenuti e i temi e moduli che non ne copiano il codice restano di chi li fa.

### 1.1 Le cartelle

```
index.php           front controller: tutte le richieste del sito passano da qui
cron-daily.php      compiti del giorno (pacchetti programmati, moduli, IndexNow)
cron-newsletter.php invio della newsletter a giri, coda IndexNow
VERSIONE            il numero di versione (oggi 1.4.0)
CHANGELOG.md, README.md, INSTALLA.md
.htaccess           regole di Apache (copia di install/esempi/htaccess-radice.txt)

includes/           il motore
  cms.php           nome, prefisso, base URL, moduli, filtri ed eventi
  config.php        avvio: errori, config.local.php, fuso, intestazioni di sicurezza
  config.local.php  scritto dall'installatore: database, prefisso, segreto (non è nello zip)
  db.php            connessione PDO, tbl(), impostazioni
  schema-base.php   tabelle di base e cms_installa_tutto()
  sito*.php         dati, mappa degli indirizzi, SEO delle pagine
  contenuti.php     articoli, pagine, stati, revisioni, cestino
  editor*.php       editor: albero, validazione, disegno, controlli, widget
  sezioni-pronte*.php  le sezioni pronte dell'editor: motore e dati (9.11)
  kit-partenza.php, kit/  i kit di partenza dell'installatore: motore e un file di dati per kit (2.2); il tipo di sito in prova (2.4)
  primi-passi.php   i Primi passi della dashboard (2.4)
  cron.php          i due giri del cron, il loro stato e le righe da copiare (20)
  controllo-server.php  Controlla il server e l'email di prova (7.4)
  design.php        Stile del sito
  modelli.php       header e footer
  menu-nav.php      menu di navigazione
  temi.php          motore dei temi
  versioni.php      versioni di ogni modifica
  media.php         libreria immagini
  form.php, popup.php, moduli.php, newsletter*.php, posta.php
  seo.php           SEO e AEO
  sicurezza*.php    sessioni, password, 2FA, CSP, sanificatori
  aggiornamenti.php pacchetti di aggiornamento
  versione-nuova.php  "c'e una versione nuova": controllo e Scarica e prepara (19.6)
  copia-sito.php    copia del sito: scarica e ripristina (19.7)
admin/              il pannello
sito/               il sito pubblico: index.php (instradamento), viste.php, extra.php (feed, robots, llms), assets/
api/                endpoint pubblici: nl.php (aperture e clic della newsletter, firma HMAC), csp.php (rapporti CSP),
                    versione.php (l'ultima versione di una serie del download manager, 19.6)
install/            l'installatore e gli esempi di .htaccess e Nginx
moduli/             i moduli (vuota nel prodotto)
temi/               i temi del sito (Cosmo, Aria, Bottega di partenza)
uploads/            le immagini caricate
logs/               i log
aggiornamenti/      pacchetti caricati, snapshot, avanzamento, copie/ (le copie del sito, 19.7)
docs/               la documentazione
```

### 1.2 Le idee che reggono tutto

- **Una sola base di codice.** Ogni installazione usa lo stesso nucleo. Ciò che è specifico di un sito sta in un
  modulo o in un tema, mai nel nucleo.
- **Prefisso delle tabelle configurabile.** Tutte le query passano da `tbl('nome')`, che aggiunge il prefisso
  (`includes/db.php:8`). Più installazioni possono stare nello stesso database.
- **Base URL configurabile.** Il CMS funziona nella radice del dominio o in una cartella (capitolo 5).
- **Il pannello non esegue mai il PHP di un tema** e un errore di un tema non spegne il sito (capitolo 12).
- **Ogni modifica dell'editor ha una versione** che si può confrontare e ripristinare (capitolo 13).
- **Le tabelle si creano da sole.** Ogni parte ha la sua funzione `*_installa()` o `*_schema()`, che crea o
  aggiorna le sue tabelle e salva un'impronta per non rifare il lavoro a ogni richiesta.

---

## 2. Requisiti e installazione

### 2.1 Requisiti

Controllati dall'installatore (`install/index.php:53-67`):

| Requisito | Tipo |
|---|---|
| PHP 8.2 o più nuovo | bloccante |
| estensioni `pdo_mysql`, `mbstring`, `openssl`, `json` | bloccante |
| cartelle `uploads/`, `includes/`, `aggiornamenti/`, `logs/` scrivibili (le crea se mancano) | bloccante |
| `gd` oppure `imagick` (ricodifica delle immagini) | avviso |
| `ZipArchive` (pacchetti di aggiornamento, temi da zip) | avviso |

MySQL 8.0 (o MariaDB compatibile). Il CMS lavora in modalità SQL stretta (capitolo 6).

Il CMS è provato su PHP 8.3 con MySQL 8.0, con Apache vero per i `.htaccess`. Il primo server di produzione
(Hostcare) ha PHP 8.4.27, MySQL 8.0.46 in modalità stretta, Apache 2.4 con cPanel e ModSecurity: su PHP 8.4.27
sono provate la sintassi di ogni file e i `.htaccess`, non ancora il CMS intero.

### 2.2 L'installatore, passo per passo

Si caricano i file nella cartella scelta e si apre l'indirizzo nel browser. Finché non esiste
`includes/config.local.php`, ogni pagina rimanda (302) a `install/` (`includes/config.php:27-31`).

1. **Requisiti.** La tabella sopra. Con un requisito bloccante non si va avanti.
2. **Database.** Host (predefinito `localhost`; si può scrivere `host:porta`), nome, utente, password, prefisso
   delle tabelle (predefinito `cms_`, solo `a-z 0-9 _`, al massimo 20 caratteri, il `_` finale si aggiunge da
   solo). Con la spunta "Crealo se manca" e il database inesistente (errore 1049) lo crea in
   `utf8mb4_unicode_ci`. La connessione si prova con 5 secondi di attesa.
3. **Sito e amministratore.** Nome del sito (obbligatorio), descrizione, indirizzo (`https://dominio` oppure
   `https://dominio/cartella`, senza barra finale), lingua (2 lettere), fuso orario, email. Amministratore:
   nome utente (3-100 caratteri `A-Za-z0-9._-`), email, password secondo le regole del capitolo 18.3.
4. **Installazione.** Prima la domanda **"Che sito vuoi fare?"** (dalla 1.3.0): cinque carte, Vuoto (scelto se non si
   tocca niente), Personale, Attività, Blog, Portfolio (`kit_elenco()`, `includes/kit-partenza.php:27`). Poi, in due richieste:
   - scrive `includes/config.local.php` (2.3);
   - crea tutte le tabelle con `cms_installa_tutto()` (capitolo 6), salva le impostazioni del sito, crea
     l'amministratore (solo se `users` è vuota) e i contenuti di partenza (solo se `pages` è vuota):
     - menu, header e footer di partenza, poi i contenuti del kit con `kit_costruisci()` (dalla 1.4.0,
       `includes/kit-partenza.php:211`, la stessa funzione del cambio di tipo di sito dalla dashboard, 2.4):
     - form "Contatti" (Nome, Email, Messaggio, consenso; titolo e frase dal kit);
     - con Vuoto, pagine fatte con l'editor: Home (slug `home`), Blog, Chi sono, Contatti; con un kit, la Home, Blog e
       le pagine del kit (vedi sotto);
     - articolo "Benvenuto nel tuo nuovo sito" (slug `benvenuto`, categoria `notizie`), piu gli articoli d'esempio del kit;
     - menu "principale" con Home, Chi sono, Blog, Contatti (o le voci del kit); header e footer di partenza; stile di partenza;
     - `temi/.htaccess` (scritto da `temi_proteggi()`);
     - (1.4.0) il registro di quello che il kit ha creato, con le impronte (`kit_prova_inizia()`, impostazione `kit_prova`,
       2.4) e `primi_passi` = `mostra`. Solo l'installatore scrive queste due impostazioni: un sito che si aggiorna non le ha.
   - Pagine e articoli escono alle 00:00 del giorno dell'installazione (fino alla 1.2.7 alle 06:00: installando di
     notte la home rispondeva 404 fino alle 6).
   - Alla fine scrive `install/.installato` con la data.
   - Se qualcosa va storto: scrive l'errore nel log, **cancella `config.local.php`** (se `.installato` non c'è)
     e mostra l'errore. Si può ripetere l'installazione.
5. **Fine.** Link al pannello e al sito, istruzioni per il cron (capitolo 20) e per la posta.

Dopo l'installazione:
- con `install/.installato` presente, `install/` rimanda al pannello;
- con `config.local.php` presente ma senza `.installato`, l'installatore mostra "Il CMS è già configurato" e non
  riscrive niente;
- dalla pagina Sicurezza si può togliere la cartella `install/` (azione `rimuovi_install`).

L'installatore non scrive file `robots.txt` o `sitemap.xml`: sono calcolati a ogni richiesta (capitolo 16.6).

**I kit di partenza** (1.3.0, `includes/kit-partenza.php`). Ogni kit è un file di soli dati, `includes/kit/<id>.php`, che
ritorna un array: `nome`, `descrizione`, `ordine`, `form` (titolo e frase del form dei contatti), `home` e `pagine` (le
sezioni, nella forma corta delle sezioni pronte, 9.11, oppure `['pronta' => '<id>']` per una sezione pronta così com'è),
`menu` (le voci del menu Principale), `articoli` (articoli d'esempio, usciti uno per giorno prima dell'installazione).

| Kit | Home | Pagine | Menu |
|---|---|---|---|
| Vuoto | quella di sempre | Blog, Chi sono, Contatti | Home, Chi sono, Blog, Contatti |
| Personale | Apertura Scheda (ritratto), tre servizi, testimonianze, ultimi articoli, invito | Blog, Chi sono, Contatti | Home, Chi sono, Blog, Contatti |
| Attività | Apertura Copertina, numeri, tre servizi con icone, carosello di testimonianze, invito | Blog, Servizi (con prezzi e domande), Chi siamo, Contatti | Home, Servizi, Chi siamo, Blog, Contatti |
| Blog | Apertura Minimale, griglia degli articoli, chi scrive, invito | Blog, Chi sono, Contatti; due articoli d'esempio in più | Home, Blog, Chi sono, Contatti |
| Portfolio | Apertura Mosaico, tre lavori, testimonianza, come lavoro, invito | Blog, Lavori (galleria), Chi sono, Contatti | Home, Lavori, Chi sono, Blog, Contatti |

- Nei testi `{sito}` diventa il nome del sito, `{descrizione}` la descrizione in una riga (o una frase che spiega dove
  scriverla); nelle immagini `{immagine:N}` (1-8) diventa un disegno astratto SVG che `kit_immagini()`
  (`includes/kit-partenza.php:70`) scrive in `uploads/kit/disegno-N.svg`: sfondo sfumato, aloni e forme nei colori di
  partenza, sempre uguali da un'installazione all'altra. Nessuna immagine da fuori.
- Ogni pagina ha un solo h1 (l'Apertura, anche in forma di Banner nelle pagine interne).
- I link si scrivono dalla radice (`/contatti/`): con il CMS in una cartella li porta lì `cms_con_cartella()` (capitolo 5).
- Un kit rotto o mancante non ferma l'installatore: semplicemente non compare fra le carte.

### 2.4 Il tipo di sito in prova e i primi passi (dalla 1.4.0)

Nelle installazioni nuove la dashboard (`admin/index.php`), solo per l'amministratore, ha due riquadri in più. Le azioni
vanno a `admin/tipo-sito.php`: solo POST, `require_admin()` (non è fra le pagine del redattore, che riceve 403), token
CSRF con `verify_csrf()` (anche il controllo dell'Origin).

**"Stai provando il sito <kit>"** (`#tipo-sito`), finché `kit_in_prova()` (`includes/kit-partenza.php:147`). Le cinque
carte di `kit_elenco()`; ognuna è un form con `azione=cambia`, `kit=<id>` e la conferma `data-conferma` ("Il sito diventa
Portfolio. Le pagine che hai scritto o cambiato restano."). Il pulsante **Definitivo** (`azione=definitivo`) chiama
`kit_definitivo()` (`:409`): `stato` diventa `definitivo` e il riquadro non compare più.

Il registro, impostazione `kit_prova` (JSON): `kit`, `stato` (`prova` | `definitivo`), `dal`, `creati`, `tenuti` (le frasi
dell'ultimo cambio), `storia` (da, a, quando, nome della copia). Ogni voce di `creati` è una cosa che il kit ha fatto,
`t` = `pagina` | `articolo` | `form` | `menu` | `immagine`, con `id` (o `file` per i disegni) e la sua **impronta**:
`kit_impronta()` (`:153`), lo sha1 dei campi che una persona può cambiare (`KIT_CAMPI_IMPRONTA` per pagine e articoli:
titolo, indirizzo, stato, testi, struttura e bozza dell'editor, SEO, immagine, date, categorie; `KIT_CAMPI_FORM`; per il
menu nome, posizione e voci; per un disegno lo sha1 del file), presa subito dopo averla creata. Le colonne che il CMS
scrive da solo (`updated_at`, `struttura_aggiornata`, `cestino`) non contano: aprire una pagina nell'editor senza
cambiarla non la "tocca".

`kit_cambia($nuovo, $autore)` (`:332`):
1. rifiuta (eccezione, niente toccato) senza registro, dopo il Definitivo, con un kit che non c'è o con lo stesso kit;
2. fa una copia del sito con `copia_crea('prima-del-cambio')` (19.7);
3. per ogni voce del registro: impronta uguale, la cosa si toglie (`kit_togli_contenuto()`, per sempre, con le sue
   revisioni; la copia c'è); impronta diversa, resta e finisce nelle frasi di `tenuti`; non c'è più, niente. Il form dei
   contatti non si toglie: se è ancora quello del kit gli si riscrivono titolo e frase, altrimenti resta com'è. Il menu
   Principale si riscrive solo se è ancora quello del kit;
4. `kit_costruisci()` del kit nuovo: un indirizzo già occupato (una pagina tenuta, o scritta dalla persona) non si tocca e
   la pagina del kit si salta (con una frase, se non è già detta: "all'indirizzo /chi-sono/ c'è già la tua");
5. i disegni del kit di prima che nessuna pagina, articolo o modello usa più (`kit_immagine_usata()`) si cancellano; quelli
   ancora usati da una pagina tenuta restano nel registro;
6. salva il registro nuovo. Se qualcosa si ferma dopo la copia, il messaggio dice il nome della copia da ripristinare.

**Primi passi** (`#primi-passi`, `includes/primi-passi.php`), finché `primi_passi` = `mostra`. Sei voci calcolate a ogni
apertura della dashboard (`primi_passi()`, `:17`):

| Voce | Fatta quando |
|---|---|
| Nome e logo del sito | lo Stile del sito ha un logo o un'icona |
| I tuoi colori | colori, caratteri, tipografia o pulsanti dello Stile diversi da quelli di partenza (anche per un tema) |
| Le email partono | l'ultima email di prova è partita (impostazione `posta_prova`, scritta da `server_email_prova()`, 7.4) |
| Il cron è attivo | tutti e due i giri arrivati dal cron vero di recente (`cron_attivo()`, capitolo 20) |
| La prima pagina o il primo articolo | una pagina o un articolo fuori dal registro, o uno del kit cambiato (`kit_contenuto_scritto()`) |
| Il tipo di sito è definitivo | `kit_prova` definitivo |

"Manda un'email di prova" (`azione=email_prova`) scrive all'indirizzo dell'utente collegato. "Tutto fatto: nascondi"
(`azione=nascondi_passi`) compare solo con sei voci fatte, e `primi_passi_nascondi()` lo ricontrolla sul server prima di
scrivere `nascosto`.

### 2.3 Cosa scrive l'installatore in `config.local.php`

(`install/index.php:237-249`)

```php
define('DB_HOST', 'localhost');          // con host:porta diventa 'host;port=NNNN', che va nel DSN
define('DB_NAME', '...');
define('DB_USER', '...');
define('DB_PASS', '...');
define('DB_CHARSET', 'utf8mb4');
define('CMS_PREFISSO', 'cms_');
define('CMS_SEGRETO', '...');            // 32 byte casuali in esadecimale
define('CMS_FUSO', 'Europe/Rome');
define('CMS_BASE', '/cartella');         // attiva solo se l'indirizzo ha una cartella, altrimenti commentata
// define('CMS_UPLOADS', ...); define('CMS_UPLOADS_URL', ...);   commentate, per chi vuole spostare le immagini
```

Non scrive `CMS_CRON_TOKEN`, `CMS_FIRMA_PACCHETTI`, `CMS_RADICE_SITO`, `CMS_PROXY_FIDATO`: si aggiungono a mano
se servono (capitolo 3).

---

## 3. Configurazione: `config.local.php` e le costanti

Tutte le costanti si definiscono in `includes/config.local.php`. Il file non è nello zip del prodotto e il server
web non lo serve mai (`.htaccess`, capitolo 21). Non va messo in un repository.

| Costante | A cosa serve | Predefinito se manca |
|---|---|---|
| `DB_HOST` | server MySQL | variabile d'ambiente `DB_HOST`, altrimenti `127.0.0.1` |
| `DB_NAME`, `DB_USER`, `DB_PASS` | database, utente, password | variabile d'ambiente, altrimenti vuoto |
| `DB_CHARSET` | charset della connessione | `utf8` (l'installatore scrive `utf8mb4`) |
| `CMS_PREFISSO` | prefisso delle tabelle | `newdf_` (vedi nota) |
| `CMS_BASE` | cartella del CMS nell'indirizzo (`/test`) | rilevata (capitolo 5) |
| `CMS_FUSO` | fuso orario | `Europe/Rome` (anche se il valore non è valido) |
| `CMS_SEGRETO` | chiave di riserva per la firma dei pacchetti | nessuno |
| `CMS_FIRMA_PACCHETTI` | chiave HMAC dei pacchetti e dei temi firmati | se vuota si usa `CMS_SEGRETO` |
| `CMS_CRON_TOKEN` | permette di lanciare i cron dal web; almeno 32 caratteri | nessuno: dal web i cron rispondono 403 |
| `CMS_UPLOADS`, `CMS_UPLOADS_URL` | cartella e indirizzo delle immagini caricate | quelli dichiarati da un modulo, altrimenti `uploads/` del CMS |
| `CMS_RADICE_SITO` | radice del dominio per i pacchetti (`root/`) | rilevata (capitolo 19.2) |
| `CMS_PROXY_FIDATO` | crede all'intestazione `X-Forwarded-For` (CMS dietro un proxy) | spenta: vale solo `REMOTE_ADDR` |
| `CMS_SICUREZZA_SPEGNI` | riga di emergenza: spegne 2FA e lista IP (capitolo 18.11) | spenta |
| `CMS_AGGIORNAMENTI_URL` | il sito a cui chiedere l'ultima versione (un plumeCMS, senza barra finale; 19.6); vince sul campo del pannello | l'indirizzo scritto in Aggiornamenti, poi `VERSIONE_SITO_UFFICIALE` |
| `CMS_AGGIORNAMENTI_HTTP_PROVA` | solo per le prove: con `true` il controllo della versione accetta anche `http://` | spenta (solo HTTPS) |
| `CMS_IMPORTA_WP_LOCALE` | solo per le prove in locale: elenco `host:porta` (separati da virgole) da cui l'import da WordPress accetta indirizzi interni e `http://` (capitolo 26) | spenta: solo siti pubblici, porte 80 e 443, credenziali solo in HTTPS |
| `CMS_IMPORTA_WP_GIRO` | solo per le prove: i secondi di un giro dell'import (anche decimali, minimo 0.2) | 8 secondi |

Note:
- `CMS_PREFISSO` vale `newdf_` quando manca perché le installazioni di prima dell'installatore non lo scrivono nel loro config.
  L'installatore lo scrive sempre, quindi le installazioni nuove non ne risentono.
- Costanti interne, da non toccare: `CMS_NOME` (`plumeCMS`), `CMS_ADMIN` (cartella del pannello sul disco),
  `DB_PREFIX` (copia di `CMS_PREFISSO`), `CMS_INSTALLER` (siamo nell'installatore), `PUBLISHER_ERRORI` (chi
  gestisce gli errori, capitolo 22).
- Costanti storiche: `SITE_URL`, `SITE_NAME`, `SITE_DESC` (`includes/config.php:48-50`). Il codice nuovo usa
  `SITO_URL` (`includes/sito.php:20`), che legge l'impostazione `sito_url` dal database. `SITE_URL` invece legge
  solo il valore di un modulo o la richiesta: le due possono essere diverse. Nel codice nuovo si usa
  `cms_url_sito()`.

### 3.1 Le impostazioni nel database

Tutto il resto sta nella tabella `settings` (chiave e valore), letta con `get_setting($chiave, $predefinito)` e
scritta con `save_setting($chiave, $valore)` (`includes/db.php:105-116`). La funzione da usare è
`cms_impostazione($chiave, $predefinito)` (`includes/cms.php:121`): legge il database, poi il valore dichiarato
da un modulo, poi il predefinito; una stringa vuota conta come assente.

Impostazioni scritte dall'installatore: `sito_nome`, `sito_titolo`, `sito_descrizione`, `sito_url`,
`sito_lingua`, `autore_predefinito`, `pagina_blog` (`/blog/`), `blog_nome`, `blog_occhiello`, `blog_titolo`,
`pagina_home` (`home`), `home_descrizione`, `cms_versione_installata`, e `posta_smtp` se c'è un'email.

Altre impostazioni importanti, scritte dal pannello: `sicurezza_cfg` (capitolo 18), `seo_config` (16),
`posta_smtp` e `notifica_email` (15), `editor_*` (9.10), `tema_attivo` e `tema_guasto` (12), `moduli_segreto`
(segreto di newsletter e captcha, 15), `versione_controllo` e `versione_controllo_cache` (19.6),
`scaricabili_impronte` (gli sha256 dei file da scaricare, 14.1), `kit_prova` e `primi_passi` (2.4, solo dall'installatore
della 1.4.0 in poi), `posta_prova` (l'ultima email di prova: quando, a chi, com'è andata, 7.3).

---

## 4. Il percorso di una richiesta

### 4.1 L'avvio comune: `includes/config.php`

Ogni richiesta, del sito o del pannello, passa da `config.php`, in quest'ordine:

1. Se nessuno l'ha già fatto, gli errori vanno solo nel log `logs/php-errori.log`, mai a video; le deprecazioni
   si scrivono come "Deprecato: ..." e non si mostrano (`config.php:5-16`).
2. Carica `config.local.php`, se c'è.
3. Carica `includes/cms.php` (nome, prefisso, base URL, moduli).
4. Se il CMS non è installato: dalla riga di comando esce con un messaggio, dal web rimanda a `install/`.
5. Imposta il fuso orario (`CMS_FUSO`).
6. Valori predefiniti delle costanti `DB_*`.
7. Carica `sicurezza.php` e manda le intestazioni di base (capitolo 18.8).
8. Definisce le costanti storiche `SITE_*`.

### 4.2 Il sito pubblico

`index.php` carica `config.php`, poi:
- se `cms_sito_base() === cms_base()` (sito e CMS nella stessa cartella, anche nella radice) carica
  `sito/index.php`;
- altrimenti risponde 302 con `Cache-Control: no-store` verso l'indirizzo del sito. È il caso del CMS in una
  cartella che ospita solo il pannello (capitolo 5).

`sito/index.php` carica (attraverso `includes/sito-seo.php` e la sua catena) dati, mappa, viste, extra, moduli e
newsletter, poi:
- `SITO_ANTEPRIMA` è vero con `?preview=1` e la sessione di un amministratore; in anteprima aggiunge `no-store`;
- `temi_avvia_sito()` carica il tema attivo (capitolo 12);
- ricava il percorso da `REQUEST_URI` e, se il sito sta in una cartella, toglie la cartella.

Poi decide, **in quest'ordine** (`sito/index.php`):

| # | Cosa | Risposta |
|---|---|---|
| 1 | c'è una query string: evento `sito_query($q, $percorso)` | un modulo può rispondere e chiudere |
| 2 | home con `?p=ID` o `?page_id=ID` | 301 all'indirizzo vero del contenuto |
| 2b | home con `?s=testo` | pagina di ricerca, 200 |
| 3 | `/articoli/qualcosa` | 301 a `/qualcosa` |
| 4 | un redirect della tabella `redirect` (16.5) | il codice del redirect, di solito 301 |
| 5 | `/wp-sitemap.xml` | 301 a `/sitemap.xml` |
| 6 | gli indirizzi dei moduli del sito (evento `sito_percorso`), poi i percorsi fissi della newsletter: `/newsletter-grazie/`, `/newsletter-conferma/`, `/newsletter-esci/` | pagine dei moduli (15) |
| 7 | `/grazie/?f=ID` | ringraziamento di un form |
| 8 | `/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/favicon.ico` | file calcolati (16.6); l'icona del sito (1.4.0, sotto) |
| 9 | file speciali SEO: verifica Google, `BingSiteAuth.xml`, chiave IndexNow, sitemap divise, `llms-full.txt` | 200 o 404 |
| 9b | `/scarica/<nome>/` (dalla 1.2.3) | il file `<nome>.<estensione>` di `uploads/scaricabili/`, oppure l'ultima versione della serie `<nome>`, come allegato, e lo conta (14.1); 404 se non c'è |
| 10 | percorsi che finiscono con `/feed/` | feed RSS, o 404 |
| 11 | il percorso è nella mappa del sito (`sito_mappa()`) | la pagina, secondo il genere (sotto) |
| 12 | il percorso senza barra finale esiste con la barra | 301 con la barra |
| 13 | paginazione oltre l'ultima pagina (`/blog/page/9/`) | 301 alla prima pagina |
| 14 | nient'altro | 404, registrato nella tabella `seo_404` |
| - | il database non risponde (`PDOException`) | 503 con `Retry-After: 600` |

Generi della mappa (passo 11): `home` (fatta con l'editor oppure scritta nel codice), `contenuto` (articolo,
pagina, voce di glossario), `blog`, `glossario`, `archivio` (categoria o tag). Un genere sconosciuto lancia
l'evento `sito_genere($voce, $meta)`: è il modo in cui un modulo serve pagine sue.

Ogni pagina HTML passa da `sito_html()`, che applica in ordine `vista_pagina()` (la cornice: head, header,
footer), `seo_inietta_html()` (meta, JSON-LD, codici di analisi) e `cms_con_cartella()` (i link nella cartella,
capitolo 5). La CSP del sito si aggiunge alla risposta (`sito/index.php:33`).

**L'icona del sito** (1.4.0). `vista_favicon()` (`sito/viste.php:457`) scrive nell'head l'icona dello Stile del sito
(`design_sito()['favicon']`) se c'è; altrimenti l'icona di partenza di plumeCMS, `sito/assets/favicon.ico` (16, 32 e 48
pixel) e `sito/assets/favicon.svg`, con `SITO_ASSET` davanti (giusto anche con il CMS in una cartella). `/favicon.ico`,
quando nella cartella non c'è un file vero con quel nome (lo servirebbe il server web), passa da `sito_favicon()`
(`sito/index.php:84`): con un'icona nello Stile un 302 `no-store` a quella (dipende dalle impostazioni, 21: mai un 301),
altrimenti l'icona di partenza, 200 `image/x-icon`, un giorno di cache. I due file li disegna `tools/favicon-partenza.py`
(una piuma bianca su un quadrato grafite `#2f323d`); fino alla 1.3.1 un'installazione nuova rispondeva 404.

### 4.3 Il pannello

Ogni pagina del pannello comincia con `admin/includes/auth.php`, che:
1. dichiara il pannello (`PUBLISHER_ERRORI = 'pannello'`): errori a video, deprecazioni solo nel log;
2. carica `config.php`, `db.php`, `sicurezza.php`;
3. con un pezzo di troppo dopo il file (`/admin/index.php/qualcosa`) rimanda all'indirizzo pulito (301);
4. apre il buffer di uscita `sicurezza_pannello_fine`, che a fine pagina manda le intestazioni e la CSP del
   pannello; un avviso stampato non blocca più i redirect;
5. avvia la sessione (capitolo 18.2);
6. con un POST, decodifica i campi col prefisso `~b64~` (capitolo 18.9).

Poi la pagina chiama `require_login()` o `require_admin()` (capitolo 7).

---

## 5. Il CMS in una cartella: base URL

Due valori decidono tutto:
- `cms_base()`: la cartella del CMS nell'indirizzo (`''` nella radice, `/test` in una cartella);
- `cms_sito_base()`: la cartella del sito, presa dall'impostazione `sito_url`.

`cms_base()` (`includes/cms.php:45-67`) usa, nell'ordine: la costante `CMS_BASE`; il confronto fra la cartella
del CMS sul disco e `DOCUMENT_ROOT`; `SCRIPT_FILENAME` e `SCRIPT_NAME`; altrimenti `''`. Dalla riga di comando,
senza `CMS_BASE`, vale `''`.

Tre configurazioni:

| Caso | `cms_base()` | `cms_sito_base()` | Cosa succede |
|---|---|---|---|
| a. tutto nella radice (`https://esempio.it`) | `''` | `''` | `index.php` serve il sito |
| b. tutto in una cartella (`https://esempio.it/test`) | `/test` | `/test` | `index.php` serve il sito; il percorso perde `/test`; i link `/x` diventano `/test/x` |
| c. pannello in una cartella (`/new`), sito nella radice | `/new` | `''` | `index.php` della cartella rimanda (302) al sito; nella radice servono `install/esempi/index-radice.php` e l'`.htaccess` di `install/esempi/htaccess-sottocartella.txt` |

`cms_con_cartella($html)` (`includes/cms.php:96`) aggiunge la cartella del sito agli attributi `href`, `src`,
`action`, `poster`, `data-src` che cominciano con una sola barra. Non tocca gli indirizzi completi, quelli con
`//` e quelli che hanno già la cartella davanti. Chi scrive HTML per il sito scrive i link da `/` e lascia fare
a questa funzione.

Il sito scrive `cms_base()` in `<meta name="cms-base">`, per i JavaScript.

I rimandi che dipendono dalla configurazione sono 302 con `no-store`, mai 301: il browser ricorda un 301 e lo
ripete anche dopo che la configurazione è cambiata.

Funzioni utili: `cms_url_sito()` (indirizzo del sito senza barra finale), `cms_url_pannello()` (indirizzo del
pannello con la barra finale), `cms_origine()` (`https://host` della richiesta), `cms_radice()` (cartella del
CMS sul disco).

---

## 6. Il database

### 6.1 La connessione

`db()` (`includes/db.php:12`) apre una sola connessione PDO per richiesta, con:
- eccezioni sugli errori, risultati come array associativi, prepared statement veri (non emulati);
- `time_zone` uguale al fuso di PHP;
- `sql_mode` esplicito: `ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE,
  ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION`. Il CMS si comporta allo stesso modo su ogni server, anche
  quelli che la modalità stretta non l'hanno.

Ogni nome di tabella passa da `tbl('nome')`, che restituisce `` `prefisso_nome` `` con gli apici inversi.
Il prefisso non si scrive mai a mano nel codice: il costruttore dello zip rifiuta `newdf_` fuori da `cms.php` e
`db.php`.

### 6.2 Come nascono le tabelle

`cms_installa_tutto()` (`includes/schema-base.php:158-173`) crea, in ordine:
1. le tabelle di base (`cms_schema_base()`);
2. le colonne in più del sito (`sito_schema_aggiorna()`, estendibile con il filtro `schema_colonne`);
3. contenuti e sito (`contenuti_schema_tabelle`, `sito_schema_tabelle`);
4. colori delle infografiche, stile, menu, modelli, SEO, form, popup, moduli del nucleo, newsletter, flussi,
   versioni;
5. le viste SQL (`sito_viste()`);
6. i moduli (`cms_moduli_installa()`).

Quasi ogni funzione confronta un'impronta (md5 dello schema) salvata in `settings` e non rifà il lavoro se lo
schema non è cambiato. Una tabella nuova si aggiunge sempre così, con la sua funzione `*_installa()`, e si prova
anche con il database in modalità stretta.

Le tabelle della sicurezza nascono a parte, al primo login (`sicurezza_schema()`, chiamata da
`current_user()`).

### 6.3 Le tabelle

Tutte con il prefisso davanti. Le colonne sono quelle principali.

**Base** (`includes/schema-base.php`)

| Tabella | Contiene |
|---|---|
| `articles` | articoli: titolo, slug unico, contenuto, estratto, immagine, categoria e tag, data e ora di pubblicazione, stato, SEO (titolo, descrizione, robots, tldr, faq, keyword), struttura dell'editor (`struttura`, `struttura_bozza`), `header_id`, `footer_id`, `cestino` |
| `pages` | pagine: titolo, slug unico, html, stato, SEO, `publish_at`, struttura dell'editor, `header_id`, `footer_id`, `cestino` |
| `categories` | slug, nome, descrizione, ordine, robots |
| `tags` | slug, nome |
| `media` | file caricati: nome, percorso, alt, didascalia, tipo, dimensioni |
| `users` | utenti: nome utente, email, hash della password, nome visibile, ruolo (`admin` o `editor`), ultimo accesso, più le colonne della 2FA e della sessione e, dalla 1.4.0, `pannello_aspetto` (7.3) |
| `settings` | impostazioni chiave e valore |

**Contenuti e sito**

| Tabella | Contiene |
|---|---|
| `revisioni` | revisioni della scheda classica dei contenuti (ultime 20 per contenuto) |
| `glossario` | voci di glossario |
| `redirect` | redirect: da, a, codice, tipo di confronto, attivo, origine |
| `pagine_revisioni` | tabella storica delle revisioni dell'editor: oggi si importa una volta in `editor_versioni` |
| `social_contenuti` | 1.5.0: i testi per i social di un contenuto (`tipo`, `contenuto_id`, `testo`, `linkedin`, `facebook`, `whatsapp`) |
| `social_programmati` | 1.5.0: il calendario dei testi social (`data_invio`, contenuto, titolo, ancora, tre testi, due immagini, `inviato_at`) |

**Editor**

| Tabella | Contiene |
|---|---|
| `design` | Stile del sito: una riga per sezione, in JSON |
| `editor_temi` | tavolozze dei colori delle infografiche |
| `modelli` | header e footer: struttura, stato, predefinito, condizioni, priorità |
| `menu` | menu: nome, posizione, voci in JSON |
| `editor_versioni` | le versioni di ogni modifica (capitolo 13) |

**Form, popup, newsletter**

| Tabella | Contiene |
|---|---|
| `moduli`, `moduli_invii` | i form del costruttore e i loro invii |
| `popup` | i popup |
| `captcha_usati` | captcha già usati (dalla 1.2.8 il nucleo non crea più `iscrizioni_ebook` e `richieste_laser`: chi le ha le tiene, senza che il nucleo le usi) |
| `newsletter_iscritti`, `newsletter_liste`, `newsletter_iscritti_liste` | iscritti e liste |
| `newsletter_campagne`, `newsletter_coda` | campagne e coda di invio |
| `newsletter_eventi`, `newsletter_flussi`, `newsletter_corsi` | aperture e clic, flussi automatici, persone dentro un flusso |

**Download**: `scaricabili` (titolo, descrizione, versione, conteggio dei file da scaricare; dalla 1.2.4).

**Import da WordPress**: `importa_wp_voci` (dalla 1.4.0, `utf8mb4`): una riga per ogni cosa entrata con un import (lavoro, genere, id e indirizzo su WordPress, id e riferimento in plumeCMS, stato finale), per Conferma e Annulla (capitolo 26).

**SEO**: `seo_coda` (coda IndexNow), `seo_log`, `seo_url` (ispezioni di Search Console), `seo_404`.

**Sicurezza**: `sicurezza_log` (registro), `sicurezza_tentativi` (blocchi), `sicurezza_sessioni`,
`sicurezza_token` (token monouso), `sicurezza_csp` (rapporti CSP).

**Viste SQL**: `v_published_articles` (articoli pubblicati, fuori dal cestino, con data non futura) e
`v_scheduled_articles` (articoli programmati).

Le tabelle di base, della sicurezza e `editor_versioni` sono in `utf8mb4`; molte altre sono in `utf8` a 3 byte.
Per questo i caratteri a 4 byte (emoji) nei dati si salvano come entità HTML o come `\uXXXX` nel JSON: in
modalità stretta MySQL rifiuterebbe la riga (errore 1366).

La tabella `form_submissions`, usata da una vecchia funzione di `db.php`, nel nucleo non si crea: la porta un modulo che
ne ha bisogno. La funzione che la scrive non è chiamata dal nucleo.

---

## 7. Il pannello e i ruoli

### 7.1 Ruoli

Due ruoli (`admin/utenti.php:12`):
- **Amministratore** (`admin`): tutto.
- **Redattore** (`editor`): solo contenuti, media, categorie e tag.

Il redattore può aprire solo queste pagine (`AUTH_REDATTORE_PAGINE`, `admin/includes/auth.php:69-70`):
`index`, `contenuti`, `contenuto`, `editor`, `editor-api`, `editor-anteprima`, `seo-api`, `media`,
`categories`, `versioni`, `account`, `password`, `logout`, `login`. L'editor con `tipo=modelli` (header e
footer) è sempre negato. Un modulo aggiunge pagine con il filtro `redattore_pagine`.

Fuori dal suo elenco il redattore viene rimandato alla dashboard; un POST o una pagina `*-api.php` ricevono 403.
Il menu gli mostra solo le voci permesse.

Per il redattore, inoltre:
- l'HTML della scheda classica passa dal sanificatore (`sicurezza_html_pulito`);
- nell'editor ogni widget HTML nuovo o cambiato diventa testo (`editor_blinda_codice`);
- pubblica dall'editor solo se l'impostazione "Chi pubblica" vale "tutti" (9.10);
- in Versioni vede e ripristina solo le versioni dei contenuti;
- la dashboard gli mostra solo i contenuti.

Le funzioni: `require_login()` (sessione valida, 2FA se obbligatoria, ruolo), `require_admin()` (in più il ruolo
admin), `is_admin()`, `current_user()`.

### 7.2 Le pagine del pannello

| Menu | Pagina | Chi |
|---|---|---|
| Dashboard | `index.php` | tutti |
| Articoli | `contenuti.php?tipo=articoli` | tutti |
| Pagine (e "Home nell'editor") | `contenuti.php?tipo=pagine`, `editor.php?id=home` | tutti |
| Form | `form.php` | admin |
| Popup | `popup.php` | admin |
| Categorie, Tag | `categories.php`, `categories.php?tipo=tag` | tutti |
| Media | `media.php` | tutti |
| Download | `scaricabili.php`: i file da scaricare (14.1) | admin |
| Utenti | `utenti.php` | admin |
| Newsletter e form | `newsletter.php` (Campagne, Iscritti e liste), `form-submissions.php`, `newsletter-flussi.php`, `newsletter-statistiche.php` | admin |
| Editor > Temi | `temi.php` | admin |
| Editor > Stile del sito | `design.php` | admin |
| Editor > Header e footer | `modelli.php` | admin |
| Editor > Menu | `menu.php` | admin |
| Editor > Colori delle infografiche | `editor-temi.php` | admin |
| Editor > Widget | `editor-widget.php` | admin |
| Editor > Versioni | `versioni.php` | tutti (il redattore solo per i contenuti) |
| Editor > Impostazioni | `editor-impostazioni.php` | admin |
| Editor > Informazioni | `informazioni.php` | admin |
| Social | `social.php` con le schede mattino, calendario, testi, impostazioni (27) | admin |
| SEO e AEO | `seo.php` con le schede panoramica, motori, copertura, prestazioni, analisi, globale, schema, redirect, registro | admin |
| Impostazioni | `impostazioni.php` (email e SMTP), `sicurezza.php`, `aggiornamenti.php`, `copia-sito.php` (19.7), `importa-wordpress.php` (26), `cron-log.php` (20), `controlla-server.php` (7.4) | admin |

Pagine fuori dal menu: `contenuto.php` (scheda classica di un contenuto), `editor.php` (editor visuale),
`editor-api.php` (le chiamate JSON dell'editor), `editor-anteprima.php` (la tela dentro l'iframe), `seo-api.php`
(analisi SEO, JSON-LD, ispezione), `newsletter-campagna.php`, `account.php` (2FA e dispositivi),
`password.php`, `login.php`, `logout.php`, `password-dimenticata.php`, `password-reimposta.php`, `tipo-sito.php` (le
azioni dei riquadri della dashboard, 2.4).

Le voci dei moduli si aggiungono al menu con la chiave `menu` del manifest (capitolo 17).

Alcune pagine vecchie (`articles.php`, `pages.php`, `page-edit.php`, e simili) esistono solo come redirect 301
verso `contenuti.php`, per i segnalibri.

### 7.3 Gli aspetti del pannello (dalla 1.4.0)

Ogni utente sceglie i colori del suo pannello, per sé: **Aria** (chiaro, bianco e blu, il predefinito), **Bottega**
(carta crema, terracotta e salvia), **Cosmo** (scuro, notte e viola), **Lavagna** (scuro, ardesia e gesso). Il sito non
cambia: non sono i temi del sito (capitolo 12), ne prendono solo i nomi e i colori.

- **Dove si sceglie**: Il mio accesso (`admin/account.php`), riquadro "Aspetto del pannello": quattro miniature
  cliccabili e "Usa questo aspetto". Il POST (`azione=aspetto`) passa da `verify_csrf()` e salva solo per l'utente
  della sessione (`$io['id']`, mai un id dal modulo) con `pannello_aspetto_salva()`, che rifiuta ogni valore fuori
  dai quattro (`includes/pannello-aspetto.php:31`). Il redattore può: `account.php` è fra le sue pagine.
- **Dove si salva**: colonna `users.pannello_aspetto varchar(12) NOT NULL DEFAULT 'aria'`, creata da
  `sicurezza_schema()` (`includes/sicurezza.php:113`, elenco `$colonne`) al primo accesso dopo l'aggiornamento. Chi
  c'era prima parte da Aria.
- **Come si applica**: `pannello_aspetto()` (`includes/pannello-aspetto.php:25`) dà l'aspetto dell'utente collegato
  (Aria se manca o non vale); `admin/includes/admin-header.php:92` e le due radici di `admin/editor.php` lo scrivono
  come `<html data-pannello="cosmo">`. Login, password dimenticata e reimpostata scrivono sempre `aria` (non si sa
  ancora chi è); l'installatore ha i colori di Aria nel suo foglio (`install/install.css`). Nessun JavaScript: il foglio
  arriva prima del contenuto, la pagina nasce con i colori giusti.
- **I colori**: tutti in `admin/assets/css/aspetti.css`, un blocco di variabili per aspetto su `[data-pannello="..."]`
  (Aria anche su `:root`). Dalla 1.4.1 ogni pagina lo collega da sé, prima di `admin.css` o di `editor.css`
  (`admin-header.php`, le due radici di `editor.php`, login, password dimenticata e reimpostata): nella 1.4.0 lo importavano
  `admin.css` e `editor.css` con `@import`, che non può portare la data nell'indirizzo (vedi 7.5). Le regole valgono su qualunque elemento: le miniature della scelta sono pannelli veri in
  piccolo con `data-pannello` sullo `span`.
- **Le variabili**: restano quelle di sempre (`--bg`, `--surface`, `--text`, `--text-muted`, `--border`, `--coral`,
  `--coral-light`, `--coral-hover`, `--sage`, `--sage-light`, `--mustard`, `--mustard-light`, `--sidebar`,
  `--sidebar-hover`, `--radius`, `--font`), definite in tutti e quattro; `--coral` è l'accento dell'aspetto (blu, terracotta,
  viola, giallo gesso). Le nuove, per significato: `--fondo-2`, `--campo`, `--riga-hover`, `--bordo-forte`,
  `--su-accento` (testo sopra `--coral`: bianco nei chiari, scuro nei due scuri), `--accento-testo`, `--link`,
  `--successo-testo`/`-bordo`, `--errore`/`-chiaro`/`-testo`/`-bordo`, `--attenzione-testo`/`-bordo`,
  `--info-chiaro`/`-testo`/`-bordo`, `--sidebar-testo`, `--sidebar-testo-2`, `--sidebar-attivo`, `--sidebar-su-attivo`,
  `--sidebar-bordo`, `--logo`, `--logo-accento`, `--codice-fondo`/`-testo`, `--ombra`, `--ombra-grande`, `--velo`,
  `--fuoco`, `--tela-fondo`, `--font-titolo`, `--font-codice`, `--cat-verde`/`-viola`/`-ambra`/`-blu`/`-grigio`.
  **Attenzione**: in Aria `--sidebar` è bianca. Il testo sopra la barra laterale usa `--sidebar-testo`, quello sopra
  un pulsante pieno `--su-accento`: mai `#fff` scritto a mano.
- **Una pagina nuova del pannello** usa solo variabili: nessun colore scritto a mano. Le eccezioni volute (etichette
  bianche sopra le foto, colori di categoria, il fondo bianco del codice QR, le anteprime del sito) portano un commento
  `fisso` sulla stessa riga; `prove/prova-aspetti.js` cerca tutti gli altri.
- **Editor**: l'interfaccia (barra, pannello, dialoghi, sezioni pronte) segue l'aspetto con le sue `--ed-*` che
  puntano alle variabili del pannello (`admin/assets/editor/editor.css:3`); la tela no, perché mostra il sito.
- **Icone del menu**: dalla 1.4.0 sono le icone disegnate del set (`includes/icone.php`, `icona_svg()`) al posto delle
  emoji, del colore del testo. Una voce di un modulo può ancora dare un'icona sua come entità HTML.
- **Carattere**: il pannello usa Inter servito in casa (`sito/assets/font/`), Bottega e Lavagna hanno un carattere
  loro per i titoli (`--font-titolo`: Georgia, Trebuchet MS).
- **Contrasto**: misurato da `prove/prova-aspetti.js` su ogni testo visibile di ogni pagina, in tutti e quattro:
  almeno 4,5 a 1 (3 a 1 per il testo grande), anche `--text-muted` e i messaggi di successo, errore e avviso.
- **Correzione insieme**: `assets/css/style.css` (le infografiche dell'anteprima, caricato nella scheda di un articolo)
  definiva le sue variabili su `:root` e copriva `--coral` e `--border` del pannello su quella pagina: dalla 1.4.0
  valgono solo dentro `#ig-preview` e `.te-visuale`.


### 7.4 Controlla il server (dalla 1.4.0)

Impostazioni > Controlla il server (`admin/controlla-server.php`, link anche da Informazioni). Prima del clic solo la
spiegazione; "Controlla adesso" (POST con il token) chiama `server_controlli()` (`includes/controllo-server.php:45`), che
ritorna gruppi di controlli con esito `ok` (verde), `avviso` (giallo: serve solo ad alcune funzioni) o `ko` (rosso), una
frase su cosa si è visto e una su cosa fare:

- **PHP e database**: versione (8.2 o più), estensioni (`pdo_mysql`, `mbstring`, `openssl`, `json`, `zlib`, `zip`
  necessarie; `dom`, `gd`/`imagick`, `curl` gialle se mancano), MySQL che risponde;
- **Indirizzo del sito**: rosso se `sito_url` non è https; se lo è, una richiesta vera a `/robots.txt` con il certificato
  verificato;
- **Cron**: `cron_attivo()` (capitolo 20);
- **Email**: l'ultima email di prova (`server_email_ultima()`); il pulsante "Manda un'email di prova" passa da
  `tipo-sito.php` con `ritorno=controlla` e chiama `server_email_prova()` (`:153`), la stessa dei Primi passi;
- **Aggiornamenti**: `versione_chiedi()` al sito ufficiale (19.6), giallo se non risponde;
- **Cartelle**: una scrittura vera (e cancellata) in `uploads/`, `logs/`, `aggiornamenti/`, `temi/`;
- **Protezioni (.htaccess)**: `server_http()` chiede al sito stesso (`server_base_web()`) `includes/config.local.php`,
  un file scritto apposta in `logs/` con dentro una parola a caso, e `VERSIONE`: devono rispondere 403 (o 404) e la
  parola non deve uscire. Se nessuna richiesta riceve risposta (server che non chiama se stesso) l'esito è giallo.

Le richieste: timeout di 5-6 secondi, nessun redirect seguito, al massimo 64 KB letti. Con il server integrato di PHP
(`php -S`) le richieste al sito stesso vogliono `PHP_CLI_SERVER_WORKERS=4`, altrimenti il server aspetta se stesso fino
al timeout.

---

### 7.5 Fogli e script del pannello con la data (dalla 1.4.1)

Ogni foglio e script del pannello si scrive con `pannello_file('assets/...')` (`admin/includes/auth.php`), che aggiunge
`?v=` e la data del file: `assets/css/admin.css?v=1730112345`. Il `.htaccess` fa tenere CSS e JS un mese al browser
(`mod_expires`); senza la data, dopo un aggiornamento il browser usava i fogli vecchi. È successo con la 1.4.0 sul primo
sito aggiornato: pannello con i colori della 1.3 e i quattro aspetti tutti uguali (finché non si ricaricava con
Cmd+Maiusc+R). Il file nuovo, scritto dal pacchetto, ha una data nuova e quindi un indirizzo nuovo. È lo stesso modo del
sito (`sito_versione_asset()`, `sito/viste.php:28`). Niente `@import` fra i fogli del pannello: un foglio importato non
porta la data. La prova è `prove/prova-file-pannello.js`.

## 8. Contenuti: articoli, pagine, categorie, tag

Il motore sta in `includes/contenuti.php`. Le pagine del pannello sono `contenuti.php` (elenco) e
`contenuto.php` (scheda classica); l'editor visuale è al capitolo 9.

### 8.1 Tipi di contenuto

`contenuti_tipi()` (`includes/contenuti.php:18-37`):

| Tipo | Tabella | Campo del testo | Tassonomie |
|---|---|---|---|
| `articoli` | `articles` | `content` | categorie, tag, attributi SEO (pillar o cluster, keyword) |
| `pagine` | `pages` | `html` | nessuna |

Un modulo aggiunge tipi con la chiave `tipi_contenuto` del manifest. `contenuti_tipo($tipo)` lancia
un'eccezione se il tipo non esiste.

### 8.2 Stati

Nel database ci sono solo `status` (`draft` o `published`) e `cestino` (una data, oppure vuoto). Lo stato che
si vede nasce da questi (`contenuti_stato()`, `includes/contenuti.php:99-104`):

| Stato | Quando |
|---|---|
| cestino | `cestino` ha una data |
| bozza | `status = draft` |
| programmato | `status = published` con data e ora future |
| pubblicato | `status = published` con data e ora passate |

Gli articoli hanno data (`publish_date`) e ora (`publish_time`, predefinita 06:00); le pagine hanno
`publish_at`. Un contenuto programmato diventa pubblico da solo quando arriva l'ora: non serve il cron.

### 8.3 L'elenco

Venti contenuti per pagina, schede per stato, filtri, ordinamento per data, titolo, ultima modifica, autore.
Azioni di massa (pubblica, bozza, cestino), modifica rapida in riga, duplica (copia in bozza con slug
`-copia`), cestino con ripristino. Si elimina per sempre solo ciò che è già nel cestino. La home non va mai nel
cestino e non torna mai in bozza.

### 8.4 Il salvataggio

`contenuti_salva($tipo, $id, $dati, $autore)` (`includes/contenuti.php:271-366`):
- titolo obbligatorio; slug in ASCII minuscolo, unico per tipo;
- stato solo `bozza` o `pubblicato` (programmato nasce dalla data); data `AAAA-MM-GG`, ora `HH:MM`;
- campi SEO: titolo SEO (255 caratteri), meta descrizione (500), robots `index` o `noindex`, riassunto
  (`tldr`), domande frequenti (JSON), immagine;
- solo articoli: tipo pillar o cluster, keyword principale e secondarie, invito, pillar di riferimento, formato
  del testo (HTML o Markdown);
- prima di modificare scrive una revisione (8.6);
- se cambia lo slug di un contenuto già pubblicato crea da solo un redirect 301 dal vecchio indirizzo al nuovo,
  senza catene;
- alla fine avvisa i motori con IndexNow, se il contenuto è pubblico e indicizzabile (16.8).

Dalla 1.4.2 la scheda (`admin/contenuto.php`, azione `salva` con `invio=pubblica`), quando il contenuto **era in bozza**,
pubblica anche la bozza dell'editor visuale: `editor_salva(..., true, ..., 'pubblicato dalla scheda')` con
`struttura_bozza`. Prima l'articolo scritto nell'editor (Salva bozza) e pubblicato dalla scheda usciva con la
struttura iniziale, senza testo. "Aggiorna" su un contenuto già pubblicato non tocca una bozza dell'editor in lavorazione.

### 8.5 Categorie e tag

- Le categorie stanno in `categories` (slug, nome, descrizione, robots). Un articolo ne tiene l'elenco nel campo
  `categorie` (separate da virgole); `category` contiene la prima.
- I tag nascono dal campo libero dell'articolo (separati da virgole) e si salvano in `tag_slugs` e `tags`.
- Indirizzi degli archivi: `/category/<slug>/` e `/tag/<slug>/`.
- Il filtro `categorie_protette` (predefinito `senza-categoria`) elenca le categorie che non si possono
  eliminare; il filtro `categorie_noindex` quelle che non si indicizzano.

### 8.6 Revisioni della scheda classica

Ogni salvataggio dalla scheda classica scrive una revisione (ultime 20 per contenuto, tabella `revisioni`) e,
insieme, una versione nell'ambito `contenuto:<tipo>` (capitolo 13). Il ripristino di una revisione riporta
testo e campi, ma lascia stato, data e ora come sono adesso.

---

## 9. L'editor visuale

Il cuore del CMS. Il motore sta in `includes/editor.php`, i controlli in `includes/editor-controlli.php`, i
widget in `includes/editor-widget*.php`, le opzioni in `includes/editor-opzioni.php`. La pagina è
`admin/editor.php`, la tela è `admin/editor-anteprima.php` dentro un iframe, le chiamate passano da
`admin/editor-api.php`.

### 9.1 L'albero

Una pagina dell'editor è un JSON (`includes/editor.php:5-9`):

```json
{
  "versione": 1,
  "elementi": [
    { "id": "a1b2c3d", "tipo": "sezione", "impostazioni": { },
      "figli": [
        { "id": "e4f5a6b", "tipo": "colonna", "impostazioni": { },
          "figli": [
            { "id": "c7d8e9f", "tipo": "titolo", "impostazioni": { "testo": "Ciao" } }
          ] }
      ] }
  ]
}
```

Tre livelli fissi: sezione, colonna, widget. Niente widget dentro altri widget.

Il JSON si salva con i caratteri a 4 byte scritti come `\uXXXX` (`editor_json()`).

### 9.2 La validazione

Ogni albero che arriva al server passa da `editor_valida()` (`includes/editor.php:148-175`):
- al massimo **200 sezioni**, **6 colonne** per sezione, **100 widget** per colonna: il resto si scarta;
- gli id validi sono 4-12 caratteri `a-z0-9`, unici; gli altri si rigenerano (7 caratteri esadecimali);
- i tipi di widget sconosciuti si scartano; una sezione senza colonne ne riceve una vuota;
- ogni impostazione passa dal pulitore del suo controllo.

Limiti dei valori (`editor_pulisci_valore`, `includes/editor.php:37-94`):

| Controllo | Limite |
|---|---|
| testo | 500 caratteri |
| testo lungo | 5000 caratteri |
| testo ricco | 60000 caratteri, HTML ripulito |
| codice (widget HTML) | 400000 caratteri |
| immagine | solo indirizzi `http(s)://` o che cominciano con `/` |
| link | `http(s)://`, `/`, `#`, `mailto:`, `tel:` |
| ripetitore (elenchi di voci) | 40 voci |
| scelta (tendina) | solo i valori dell'elenco; dalla 1.2.4 un controllo può dichiarare `accetta` (espressione regolare) per tenere valori fuori elenco, come i file del widget Download che arrivano dopo la pagina |
| colore | esadecimale, `rgb()`/`rgba()`, `hsl()`/`hsla()`, `transparent`, `globale:<id>`, `ig:<id>` |

**Nomi dei controlli** (1.4.0). Un controllo di un widget non deve chiamarsi come uno delle schede Avanzate
(`editor-controlli.php`: `posizione`, `animazione`, `padding`, `sticky`...): i valori dei due finiscono nella stessa
chiave e, alla validazione, quello che non sta fra le opzioni dell'altro si svuota in silenzio. Era il caso di "Posizione
dell'icona" (Riquadro con icona), "Posizione dell'immagine" (Riquadro con immagine) e "Angolo" (Pulsante fisso): dalla
1.4.0 si chiamano `posizione_icona`, `posizione_immagine` e `posizione_angolo`. Un controllo rinominato dichiara
`'prima' => '<nome di prima>'`: `editor_nomi_di_prima()` (`includes/editor.php:116`), chiamata da
`editor_pulisci_impostazioni()` e `editor_valori()`, passa al nome nuovo il valore salvato con quello vecchio se è fra le
opzioni. Nei Riquadri anche l'allineamento scelto vale per il testo: `ewx_css_allinea_ribox()`
(`includes/editor-widget-extra.php:278`) scrive `--ew-ta`, che `.ew-ribox` usa per `text-align` (prima il `center` di
`widget.css` vinceva su tutto); con l'icona o l'immagine di lato, "Centro" resta il "a sinistra" di sempre. Restano, da sistemare in una versione prossima senza cambiare le pagine che ci sono, due
nomi doppi: `animazione` del Titolo animato (la sua "Animazione delle parole" perde tutto tranne Zoom) e `padding` di Social, Condividi, Avviso e
Flip box.

### 9.3 I colori globali

Un colore può essere un riferimento invece di un valore:
- `globale:primario` diventa `var(--g-colore-primario, <ripiego>)`: segue lo Stile del sito (capitolo 10);
- `ig:rosso` diventa `var(--ig-rosso)`: segue la tavolozza delle infografiche, che segue il tema.

Così una pagina cambia colore quando cambia lo stile o il tema, senza toccarla.

### 9.4 Il disegno

`editor_disegna($albero, $percorso, $anteprima)` (`includes/editor.php:279-340`) restituisce l'HTML e il CSS.

Markup:

```html
<section class="ed-sezione ed-<id> ...">
  <div class="ed-contenitore">
    <div class="ed-riga">
      <div class="ed-colonna ed-<id>">
        <div class="ed-widget ed-w-<tipo> ed-<id>"> ... </div>
      </div>
    </div>
  </div>
</section>
```

- Larghezza della sezione: contenuta, stretta, piena, articolo, personalizzata.
- In anteprima ogni elemento ha anche `data-ed-id` e `data-ed-tipo`, che usa l'editor.
- Classi per nascondere un elemento: `ed-no-d` (computer), `ed-no-t` (tablet), `ed-no-m` (telefono).

CSS: ogni valore si raccoglie per misura (computer, tablet, telefono) e per selettore `.ed-<id>`. Tablet e
telefono ereditano dalla misura più grande. L'uscita è il CSS del computer, poi `@media (max-width: <tablet>px)`
e `@media (max-width: <telefono>px)`, con le soglie dello Stile del sito (predefinite 1024 e 767).

### 9.5 I controlli di gruppo

Come in Elementor, un controllo può essere un gruppo di valori (`EDITOR_TIPI_GRUPPO`,
`includes/editor-controlli.php:18`): carattere, tipografia, sfondo, bordo, ombra, ombra del testo, dimensioni,
misura, cursore, allineamento, scelta di icone, icona, galleria, data e ora, trasformazione, intestazione.

Unità ammesse: `px`, `%`, `em`, `rem`, `vw`, `vh`. Un valore può essere diverso per misura (computer, tablet,
telefono) e per stato (`normale`, `hover`).

### 9.6 Le schede Contenuto, Stile, Avanzate

Ogni controllo appartiene a una scheda. La scheda Avanzate è uguale per tutti gli elementi
(`editor_controlli_avanzate`, `includes/editor-controlli.php:675-725`):

- **Layout**: margine, padding, spazio sotto, larghezza, z-index, ancora (id), classe CSS, tag HTML (solo sezione
  e colonna).
- **Effetti di movimento**: animazione d'ingresso, durata, ritardo, sticky.
- **Solo widget**: trasformazione, sfondo, bordo, ombra, transizione, posizione assoluta o fissa.
- **Responsive**: nascondi su computer, tablet, telefono; inverti le colonne su tablet e telefono (solo sezione).
- **Attributi**: solo `data-*`, `aria-*`, `title`, `lang`, `role`, `tabindex`, `rel`, `itemprop`, al massimo 20.
- **CSS personalizzato**: al massimo 20000 caratteri, ripulito; la parola `selector` diventa `.ed-<id>` e ogni
  regola resta confinata nell'elemento.

### 9.7 I widget

Ottanta widget, in cinque famiglie.

**Nucleo** (`includes/editor-widget.php`)
- Base: titolo, testo, immagine, pulsanti, spazio, linea, elenco.
- Contenuto: frase in evidenza, passi, schede con link, presentazione, apertura (hero), numeri, domande
  frequenti, video, infografica, tabella, scheda riassuntiva.
- Dal sito: ultimi articoli, modulo (newsletter, `form:ID` e i moduli dei moduli del sito), invito, riquadro in evidenza, leggi anche,
  download (dalla 1.2.4, `includes/editor-widget-download.php`, capitolo 14.1).
- Avanzati: HTML.

Apertura e Presentazione hanno nella scheda Stile la sezione **Foto** (dalla 1.2.2, `es_foto()` in
`includes/editor-widget.php`):
- trattamento: velo colorato sul bianco e nero (come prima), solo bianco e nero, foto naturale;
- colori del velo (tre per l'Apertura, due per la Presentazione) e intensità (0-100%), scritti come variabili
  `--velo-1`, `--velo-2`, `--velo-3`, `--velo-o` lette da `sito.css`;
- effetto animato: zoom lento, bagliore che attraversa, cornice luminosa che gira, alone che pulsa, scintille che
  salgono; con i suoi due colori (`--fx-1`, `--fx-2`, di partenza ciano e viola del sito) e la velocità (`--fx-v`).
  Trattamento ed effetto diventano classi sulla foto (`ed-foto-<x>`, `ed-fx ed-fx-<x>`, `editor_foto_classi()`);
  gli effetti si fermano per chi chiede meno movimento al sistema (`prefers-reduced-motion`).

L'Apertura ha anche l'interruttore **Mostra l'onda sopra la foto** (scheda Contenuto): spento, il riquadro con
le barre che ondeggiano non compare. Con i valori di partenza le due foto sono identiche a quelle della 1.2.1.

Dalla 1.2.6 l'Apertura ha la tendina **Disposizione** (scheda Contenuto, primo controllo): undici modi di mettere
insieme gli stessi campi (occhiello, titolo con l'evidenza, testo, pulsanti, foto con la sezione Foto, onda, barre).
Cambiando disposizione i testi restano.

| Disposizione | Com'è fatta |
|---|---|
| `classica` | testo a sinistra, foto a destra: quella di sempre, identica al pixel alla 1.2.5 (resta in `editor-widget.php`) |
| `centrata` | tutto al centro, foto larga e bassa sotto |
| `copertina` | foto a tutto sfondo con un velo scuro, testo bianco sopra |
| `divisa` | testo su un fondo di colore, foto fino al bordo |
| `rivista` | titolo enorme a tutta larghezza, testo e numeri a lato, foto a nastro |
| `scheda` | tutto in un riquadro, ritratto rotondo con l'anello di colore |
| `minimale` | niente foto: parole grandi e i numeri delle barre sotto una linea sottile |
| `mosaico` | parole a sinistra, a destra una griglia di riquadri: foto, onda, barre |
| `inclinata` | foto ruotata di qualche grado su forme di colore |
| `banner` | fascia bassa per le pagine interne, titolo moderato e pulsanti sulla stessa riga |
| `diagonale` | fondo con il gradiente dei due colori del sito, tagliato in obliquo |

Come è fatto (`includes/editor-hero.php`):
- l'elenco è `EDITOR_HERO_DISPOSIZIONI`; il `case 'hero'` di `editor-widget.php` chiama `editor_hero_<id>($v)` per
  ogni disposizione che non è la classica;
- le funzioni sono in `editor-hero-a.php` (centrata, copertina, divisa, rivista, scheda) e `editor-hero-b.php`
  (le altre cinque), costruite con i pezzi comuni: `editor_hero_apri()`, `editor_hero_parole()`,
  `editor_hero_pulsanti()`, `editor_hero_foto()`, `editor_hero_onda()`, `editor_hero_barre()`, `editor_hero_numeri()`;
- ogni disposizione esce dentro `<div class="hero ed-hero ed-hero-v ed-hero-<id>">`, con le classi della classica
  (`.occhiello`, `.lead`, `.azioni`, `.btn`, `.chip`): i colori, i caratteri e i controlli della scheda Stile valgono
  anche per lei;
- il CSS è in `sito/assets/hero-base.css`, `hero-a.css`, `hero-b.css`, ogni regola sotto `.ed-hero-<id>`, colori solo
  dalle variabili del sito (quindi segue i temi chiari e scuri); si carica solo nelle pagine con un'Apertura;
- le regole sul contenitore si scrivono `.ed-hero.ed-hero-<id>`: `sito.css` ha `.ed-w-hero .hero{padding:0}`, che con
  un solo nome di classe vince e toglie il margine (successo nella 1.2.6, corretto nella 1.2.7).

**Infografiche** (14, prefisso `ig_`): passi, timeline, checklist, tabella, liste, numeri, card, mappa, flusso,
fasi, barre, prima e dopo, zone, domande. Usano i colori `--ig-*` della tavolozza (capitolo 12).

**Tema** (11, `includes/editor-widget-tema.php`), per header, footer e modelli: logo del sito, nome del sito,
descrizione del sito, menu di navigazione, ricerca, titolo del contenuto, testo del contenuto, immagine in
evidenza, informazioni del contenuto, briciole di pane, copyright (con `{anno}` e `{sito}`).

**Extra** (30, `includes/editor-widget-extra*.php`), ripresi dai widget di Elementor free e Pro:
- Elementi: icona, riquadro con icona, riquadro con immagine, pulsante, elenco con icone, valutazione,
  testimonianza, avviso, ancora del menu, citazione, mappa, pulsante fisso.
- Numeri e tempo: contatore, barra di avanzamento, conto alla rovescia.
- Gallerie: galleria, carosello, carosello di testimonianze, flip box.
- Interazione: fisarmonica, alterna, schede, sommario.
- Marketing: social, condividi, invito libero, titolo animato, listino, tabella prezzi.
- Dal sito: griglia di contenuti.

I widget si spengono da Editor > Widget: un widget spento sparisce solo dalla tavolozza, le pagine che lo usano
continuano a mostrarlo.

### 9.8 Scrivere un widget nuovo

Un widget si registra con `editor_registra_widget($tipo, $definizione, $disegna)`
(`includes/editor-controlli.php:39-50`), di solito da un modulo (chiave `widget` del manifest).

```php
editor_registra_widget('saluto', [
    'etichetta' => 'Saluto',
    'icona'     => '*',
    'gruppo'    => 'Altri',
    'controlli' => [
        // ec(nome, tipo, etichetta, predefinito, scheda, extra)
        ec('testo', 'testo', 'Testo', 'Ciao!'),
        ec('colore', 'colore', 'Colore', '', 'stile', ['selettore' => ' .saluto', 'proprieta' => 'color']),
    ],
], function (array $v, array $ctx): string {
    return '<p class="saluto">' . htmlspecialchars((string)($v['testo'] ?? '')) . '</p>';
});

editor_registra_asset('saluto', ['css' => 'saluto.css']);   // file in sito/assets/
// dalla 1.2.6 anche più file, caricati nell'ordine scritto: ['css' => ['saluto.css', 'saluto-varianti.css']]
```

Regole:
- il nome rispetta `^[a-z][a-z0-9_]{1,40}$`, non comincia con `ig_` e non è `sezione` o `colonna`;
  altrimenti `editor_registra_widget` lancia un'eccezione;
- i controlli sono un elenco costruito con `ec($nome, $tipo, $etichetta, $predefinito, $scheda, $extra)`
  (`includes/editor-widget.php:16`); fra gli extra: `opzioni`, `min`, `max`, `passo`, `unita`, `responsive`,
  `stati`, `selettore`, `proprieta`, `condizione`, `sezione`, `campi` (per il ripetitore), `aiuto`;
- un controllo con `selettore` scrive il proprio CSS: il selettore si attacca a `.ed-<id>` dell'elemento, quindi
  `' .saluto'` (con lo spazio) vuol dire "il `.saluto` dentro il widget" e `''` il widget stesso;
- la funzione di disegno riceve i valori già puliti e restituisce l'HTML interno; il testo va sempre passato da
  `htmlspecialchars`;
- la scheda Avanzate si aggiunge da sola;
- CSS e JS del widget (file di `sito/assets/`) si caricano sul sito solo nelle pagine che lo contengono
  (classe `ed-w-<tipo>` nell'HTML);
- il widget registrato da un modulo porta l'origine `modulo:<nome>`, che la pagina Editor > Widget mostra;
- un file di widget con un errore finisce nel log e l'editor continua a funzionare senza quel widget.

Per vedere i nomi e i tipi di controllo dei widget esistenti si legge la loro definizione in
`includes/editor-widget.php` (helper `ec()`).

### 9.9 Salvare e pubblicare

- **Salva** scrive la bozza (`struttura_bozza`): il sito non cambia.
- **Pubblica** scrive `struttura`, svuota la bozza, mette il contenuto pubblicato e, se manca, la data di oggi.
- Ogni salvataggio crea una versione (capitolo 13).

Le azioni di `admin/editor-api.php`, tutte in POST con il token CSRF e risposta JSON: `disegna`, `salva`,
`revisioni`, `revisione`, `confronta`, `nota`, `impostazioni` (titolo, slug, stato, header e footer, SEO),
`temi` (tavolozze delle infografiche), `seo`, `togli` (svuota la struttura e torna alla scheda classica),
`markdown`, `carica` (immagine nella cartella `pagine`), `media` (libreria, 60 per pagina).

### 9.10 Impostazioni dell'editor (Editor > Impostazioni)

`EDITOR_OPZIONI_PREDEFINITE` (`includes/editor-opzioni.php:21`):

| Opzione | Predefinito | Valori |
|---|---|---|
| versioni da tenere per ogni oggetto | 50 | 5-500 |
| chi pubblica dall'editor | tutti | `tutti` o `admin` |
| colonne al massimo nell'interfaccia | 4 | 2-6 (il server taglia comunque a 6) |
| animazioni | accese | |
| widget spenti | nessuno | |

Ogni salvataggio delle impostazioni crea una versione (ambito `impostazioni`).

**Esporta e importa.** Esporta scrive un JSON (`formato: "editor-impostazioni"`, versione 1) con stile, header e
footer, menu, tavolozze, impostazioni e widget spenti. Importa legge al massimo 20 MB, mostra un'anteprima e
applica solo le parti scelte: header e footer si abbinano per tipo e nome, menu e tavolozze per nome; quelli con
lo stesso nome si aggiornano, gli altri si aggiungono.


### 9.11 Sezioni pronte (dalla 1.3.0)

Nel pannello dell'editor la linguetta **Sezioni** (accanto a Widget) offre 30 sezioni già impaginate, in nove gruppi:
Apertura (4), Servizi (4), Chi siamo (3), Numeri (3), Testimonianze (3), Prezzi (3), Domande frequenti (3), Contatti (3),
Invito all'azione (4). Ognuna ha un'anteprima schematica, il nome e una riga di descrizione; c'è la ricerca.

- **Clic**: la variante entra sotto la sezione selezionata (o sotto quella che contiene l'elemento selezionato), altrimenti
  in fondo alla pagina (`inserisciPronta()`, `admin/assets/editor/editor.js:303`). **Trascinamento**: si posa prima o dopo
  la sezione sotto il puntatore, con una linea che lo mostra (`text/ed-sezione`, `admin/assets/editor/anteprima.js:101`).
- Le sezioni entrano copiate, con id nuovi: sono sezioni normali, con colonne e widget esistenti, tutte modificabili.
  Alcune varianti sono fatte di due sezioni (il titolo sopra, le colonne sotto, senza spazio fra le due).
- **Dati**: `includes/sezioni-pronte-dati.php`, solo array, nella forma corta di `includes/sezioni-pronte.php`:
  `['imp' => [sezione], 'colonne' => [[widget, ...], ['imp' => [colonna], 'widget' => [...]]]]`, un widget è
  `[tipo, [impostazioni]]`; senza larghezze, le colonne si dividono come fa l'editor. `sezioni_pronte_costruisci()`
  (`includes/sezioni-pronte.php:92`) le trasforma in sezioni dell'editor, `sezioni_pronte_elementi()` le valida con
  `editor_valida()`, `sezioni_pronte_per_editor()` le mette nel JSON della pagina dell'editor (`admin/editor.php:115`).
- **Colori**: solo quelli che seguono il sito e i temi (i predefiniti dei widget, gli sfondi Vetro e Gradiente della
  sezione). Provate con il tema di base, Cosmo, Aria e Bottega: contrasto del testo sopra 4,5 a 1.
- **Form**: `form:contatti` nei dati diventa il form Contatti del sito (o il primo attivo); senza form il widget non entra.
- Testi d'esempio neutri, che dicono cosa scriverci. Nessuna immagine: dove servirebbe una foto il campo resta vuoto.
- Header e footer (Editor > Header e footer) non hanno la linguetta.

Un modulo può aggiungere varianti? Non ancora: i dati sono un file solo del nucleo.

---

## 10. Stile del sito

`includes/design.php`, pagina Editor > Stile del sito (`admin/design.php`). È l'equivalente dei "Global" di
Elementor.

### 10.1 Le sezioni

`DESIGN_SEZIONI`: colori, font, tipografia, pulsanti, immagini, campi, layout, sfondo, sito, css. Ognuna è una
riga JSON della tabella `design`; il salvataggio è in una transazione e tiene la copia precedente (pulsante
"Torna indietro").

Predefiniti (`design_predefiniti()`, filtrabile con `design_predefiniti`):
- colori di sistema: primario `#a855f7`, secondario `#22d3ee`, testo `#e6ebf5`, accento `#4ade80`, sfondo
  `#060a17`; personalizzati di partenza: `sfondo-2`, `testo-2`, `testo-3`, `bordo`;
- font di sistema: primario e secondario Space Grotesk, testo e accento Inter;
- tipografia: corpo, paragrafo, link, link al passaggio, titoli da H1 a H6;
- pulsanti (normale e al passaggio), immagini (filtri), campi dei form (etichetta, campo, fuoco);
- layout: larghezza 1180, spazio fra widget 24, spazio fra colonne 32, tablet 1024, telefono 767;
- sito: nome, sottotitolo, descrizione, logo, favicon;
- CSS libero del sito.

### 10.2 Le regole

(`design_pulisci()`, `includes/design.php:417-535`)
- Colori: esadecimale da 3 a 8 cifre, `rgb()`/`rgba()`, `hsl()`/`hsla()`, `transparent`, `globale:<id>`. Un
  riferimento a un colore globale che non esiste più torna vuoto.
- Colori personalizzati: al massimo 40, id `a-z 0-9 -` fino a 40 caratteri.
- Layout: larghezza 600-2400, spazi 0-200, tablet 600-1600, telefono 320-1000 e sempre minore del tablet.
- CSS libero: ripulito da `sicurezza_css_pulisci` (capitolo 18.10), al massimo 60000 caratteri.

### 10.3 Il CSS che esce

`design_css()` scrive su `:root`:
- `--g-colore-<id>` per ogni colore, `--g-font-<id>` (più `-peso` e `-dimensione`), `--g-larghezza`,
  `--g-spazio-widget`, `--g-spazio-colonne`;
- il **ponte** verso il foglio del sito (`sito/assets/sito.css`): `--viola`, `--ciano`, `--testo`, `--bg`,
  `--bg-2`, `--testo-2`, `--testo-3`, `--bordo`, `--accento`, le versioni `-rgb` per le trasparenze,
  `--f-titolo`, `--f-testo`, `--max`;
- regole per elemento (titoli, link, pulsanti, campi) solo per i valori impostati.

`design_head()` mette nell'head i caratteri Google scelti (`<link id="g-fonts">`) e il CSS
(`<style id="g-css">`).

Lo Stile del sito vince sempre sul tema: il suo `<style>` viene dopo il CSS del tema (capitolo 12).

---

## 11. Header, footer e menu

### 11.1 Header e footer (Theme Builder)

`includes/modelli.php`, pagina Editor > Header e footer. Un header o un footer è un **modello**: un albero
dell'editor (capitolo 9) salvato nella tabella `modelli` con tipo, nome, stato, predefinito, condizioni e
priorità. Al primo avvio nascono "Header del sito" e "Footer del sito".

**Condizioni.** Ogni modello può dire dove si usa: tutto il sito, un tipo di contenuto, gli archivi, una
categoria, un contenuto preciso; ogni condizione può anche escludere. Al massimo 40 condizioni.

**Quale modello si usa** (`modelli_scegli()`, `includes/modelli.php:350-381`), in quest'ordine:
1. in anteprima, `?header=ID` o `?footer=ID`;
2. la scelta del contenuto (campo Header e footer della pagina: nessuno, oppure un modello preciso);
3. le condizioni dei modelli pubblicati. Vince il punteggio più alto: peso della condizione per 1000 più la
   priorità. Pesi: tutto il sito 1, archivi 2, tipo di contenuto 2, categoria 3, contenuto preciso 4. Una
   condizione "escludi" toglie il modello;
4. il modello predefinito;
5. nessun modello: header e footer scritti nel codice (`vista_header_footer()`, filtro `testata_fissa`).

Cornici sul sito: `<header class="ed-testata ed-modello" id="testata">` e
`<footer class="ed-piede ed-modello" id="piede">`. Un modello con un errore finisce nel log e il sito torna a
header e footer del codice.

### 11.2 Menu

`includes/menu-nav.php`, pagina Editor > Menu.
- Tabella `menu`: nome, posizione, voci in JSON.
- Tipi di voce: link libero, pagina, articolo, categoria.
- Posizioni: nessuna, `principale`, `piede-1`, `piede-2`, `piede-3`, `telefono`.
- Una voce: `{id, etichetta, tipo, rif, url, nuova, classi, figli}`. Al massimo 3 livelli e 200 voci; etichetta
  fino a 100 caratteri; indirizzi `http(s)://`, `/`, `#`, `mailto:`, `tel:`.
- Indirizzi calcolati: categoria `/category/<slug>/`, pagina o articolo `/<slug>/`, la home `/`.

Il menu si mette nell'header con il widget **Menu di navigazione** (`menu_nav`). Il widget può indicare un menu
preciso oppure, dalla 1.2.3, "quello in posizione: ..." (valore `posizione:principale`, `posizione:piede-1`...): il menu
che ha quella posizione, qualunque sia il suo id. Temi e pacchetti usano sempre la posizione, perché gli id dei menu
cambiano da un sito all'altro.

---

## 12. Temi

Il motore sta in `includes/temi.php`, la pagina in Editor > Temi (`admin/temi.php`, solo admin). La guida
completa per costruirne uno è `docs/manuale-temi.md`; qui c'è come funziona dentro.

### 12.1 Com'è fatto un tema

Una cartella `temi/<slug>/`:

| File | Obbligatorio | A cosa serve |
|---|---|---|
| `tema.json` | sì | nome, versione, autore, descrizione, file usati, colori delle infografiche |
| `stile.json` | no | lo Stile del sito del tema (capitolo 10) |
| `modelli.json` | no | header e footer del tema (alberi dell'editor) |
| `tema.css` | no | il foglio di stile del tema |
| `anteprima.png` | no | l'immagine nella pagina Temi |
| `funzioni.php` | no | filtri ed eventi del tema |
| `viste/*.php` | no | viste che sostituiscono quelle del CMS (per esempio `404.php`) |

Partono con il prodotto tre temi: Cosmo, Aria, Bottega.

### 12.2 Attivare un tema

Quando si attiva un tema il CMS:
- salva una versione dello stile attuale, poi applica `stile.json`;
- crea header e footer da `modelli.json` e li rende predefiniti;
- scrive `tema_attivo` nelle impostazioni.

Le sezioni dello stile che un tema imposta (colori, caratteri, tipografia, pulsanti, immagini, campi, layout,
sfondo) ripartono dai predefiniti del CMS e prendono i valori del tema; identità del sito (nome, logo, favicon) e
CSS personalizzato restano di chi li ha scritti; lo stile di prima resta come
versione (capitolo 13) e si recupera da Editor > Versioni. Header e footer creati da un tema si riusano se si
riattiva lo stesso tema; quelli che c'erano prima restano in Header e footer, solo non più predefiniti.

"Usa l'aspetto di base" spegne il tema e basta (`temi_disattiva()`): il sito torna a `sito.css`, lo Stile del
sito resta quello di adesso, header e footer restano quelli predefiniti. Contenuti e menu non cambiano mai.

Prima di attivare si può vedere il tema con l'anteprima: `?preview=1&tema=<slug>` (con `tema=base` per l'aspetto
senza tema), solo per l'amministratore.

### 12.3 Sul sito

`temi_avvia_sito()` carica il tema attivo. Ordine dei fogli nell'head:
1. `sito.css` del CMS;
2. il CSS del CMS per i temi;
3. `<style id="tema-ig">` con le variabili `--ig-*` delle infografiche, calcolate dal blocco `infografiche` di
   `tema.json` a ogni pagina (non si salvano nel database);
4. `tema.css` del tema;
5. `<style id="g-css">`, lo Stile del sito (vince sul tema);
6. il CSS delle pagine dell'editor.

Il PHP del tema (`funzioni.php`, `viste/*.php`) gira **solo sul sito**, mai nel pannello. I suoi filtri ed
eventi passano per ultimi, dopo quelli dei moduli (`cms_filtro`, `cms_evento`).

### 12.4 Un tema che si rompe

Se il PHP di un tema lancia un errore, anche fatale (`temi_controllo_fatale`), il CMS segna il tema come guasto
(impostazione `tema_guasto`) e da quel momento il sito usa l'aspetto di base: niente CSS, viste o PHP del
tema, con lo Stile del sito di adesso. La richiesta in cui nasce l'errore continua lo stesso; un errore fatale
rompe solo quella, e dalla successiva il sito è sull'aspetto di base. La pagina Temi mostra l'errore.
Il tema torna a funzionare con "Riprova ad attivarlo", dopo averlo corretto. Un errore nato durante l'anteprima di un tema non lo segna come guasto.

### 12.5 Caricare e scaricare

Un tema si carica da zip nella pagina Temi e si scarica come zip. Uno zip **con file PHP** si accetta solo se è
firmato (HMAC con la chiave dei pacchetti, `tools/pacchetto.py --rifirma`) oppure con la "finestra di un'ora"
aperta dalla pagina Sicurezza (capitolo 18.7). Il file `temi/.htaccess`, scritto da `temi_proteggi()`, vieta al
server web di eseguire il PHP dentro `temi/`.

---

## 13. Versioni

`includes/versioni.php`, pagina Editor > Versioni. Una tabella sola, `editor_versioni`, per tutte le modifiche.

### 13.1 Cosa si versiona

| Ambito | Oggetto |
|---|---|
| `contenuto:<tipo>` | un articolo, una pagina (editor e scheda classica) |
| `modello` | un header o un footer |
| `menu` | un menu |
| `stile` | lo Stile del sito |
| `tema` | una tavolozza delle infografiche |
| `impostazioni` | le impostazioni dell'editor |

Ogni versione tiene: i dati (compressi sopra 64 KB), un'impronta sha1, l'autore, una nota (automatica o scritta a
mano), se era una pubblicazione, la data, la dimensione. Se l'impronta è uguale a quella dell'ultima versione non
si scrive niente.

### 13.2 Quante se ne tengono

Per ogni oggetto, il numero dell'opzione "versioni da tenere" (predefinito 50, da 5 a 500). La potatura non
cancella mai le versioni pubblicate né quelle con una nota scritta a mano.

### 13.3 Confronto e ripristino

Il confronto mostra le differenze in modo leggibile (cosa è cambiato, in quale elemento) e l'anteprima della
versione. Il ripristino (`editor_versione_ripristina()`) passa dai salvataggi normali di ogni parte, quindi
rispetta le stesse regole di validazione, e crea a sua volta una versione "ripristino della versione N". Un menu
o una tavolozza eliminati rinascono con lo stesso id.

Il redattore vede e ripristina solo le versioni dei contenuti.

---

## 14. Media

`includes/media.php`, pagina Media (`admin/media.php`).

- **Cartelle mostrate**: uploads (per anno), contenuti, pagine, newsletter, copertine degli articoli
  (`assets/img/articles`), pannello (`assets/img/uploads`).
- **Tipi letti**: jpg, jpeg, png, webp, gif, svg, avif. Le miniature (nome che finisce con `-NNNxNNN`) sono
  nascoste.
- **Caricamento** (`media_carica($file, $cartella = 'pagine', $maxMb = 8)`):
  - il tipo si legge dal contenuto, non dal nome: JPEG, PNG, GIF, WebP, AVIF; l'SVG passa dal sanificatore;
  - destinazione `uploads/<cartella>/AAAA/MM/`;
  - nome: lo slug del nome originale (fino a 60 caratteri), un trattino, 6 caratteri casuali, l'estensione.
- **Ricodifica**: dalla 1.4.0 JPEG e PNG passano da `foto_alleggerisci()` (14.2): raddrizzate, rimpicciolite oltre il
  lato massimo, senza metadati, con la WebP accanto. Le altre (e JPEG e PNG con "Foto più leggere" spento o senza GD e
  Imagick) si riscrivono come prima con `sicurezza_immagine_ricodifica()`: GD, via i metadati (JPEG e WebP qualità 90,
  PNG livello 6, AVIF 70), orientamento EXIF 3, 6 e 8. Le GIF animate restano come sono.
- **Le WebP accanto** (`foto.jpg.webp`) non compaiono nella libreria (`foto_e_webp_compagna()`, `includes/media.php:57`)
  e si cancellano con la foto (`media_elimina()`, `includes/media.php:146`).
- **Cancellazione**: il pulsante compare per le cartelle pagine, pannello e newsletter. Lato server
  `media_elimina()` accetta solo file dentro la cartella uploads e le due cartelle immagini del pannello.

La cartella `uploads/` ha il suo `.htaccess` (capitolo 21) che vieta di eseguire script e chiude gli SVG in una
sandbox.

### 14.1 Download manager (dalla 1.2.4)

I file che il sito offre da scaricare: manuali, ebook, listini, programmi. Motore `includes/scaricabili.php`, pagina
**Download** del pannello (`admin/scaricabili.php`, solo admin), widget **Download** dell'editor.

- **Dove stanno**: `uploads/scaricabili/`. Il disco è la verità: un file messo lì con il gestore dei file o con un
  pacchetto compare da solo. La tabella `scaricabili` tiene solo titolo, descrizione, versione, conteggio e data
  dell'ultimo download.
- **Indirizzo**: `/scarica/<nome>/`, dove `<nome>` è il nome del file senza estensione (`Listino 2026.pdf` diventa
  `listino-2026.pdf` e si scarica da `/scarica/listino-2026/`). Per questo due file con lo stesso nome ed estensioni
  diverse non possono convivere. L'indirizzo passa da PHP perché il `.htaccess` del CMS vieta gli `.zip` dal web; il file
  parte come allegato, con `X-Robots-Tag: noindex`.
- **Tipi ammessi** (`SCARICABILI_TIPI`): pdf, zip, epub, docx, xlsx, pptx, odt, ods, mp3, txt, csv. Al caricamento si
  controlla anche l'inizio del file (un PDF comincia con `%PDF`, gli zip e i documenti Office con `PK`, un testo non ha
  byte nulli né `<?php` o `<script`): un file con il nome sbagliato si rifiuta.
- **Versione nuova**: si carica un file con lo stesso nome spuntando **Sostituisci**. L'indirizzo resta, il conteggio
  resta; titolo, descrizione e versione cambiano solo se scritti.
- **Conteggio** (`scaricabili_conta()`): una richiesta GET intera di una persona. Non contano i robot (user agent con bot,
  crawl, spider, headless, curl, wget, python e simili) né i pezzi di un download ripreso.
- **Limite**: il pannello mostra il limite di caricamento del server; un file più grande si mette nella cartella con il
  gestore dei file dell'hosting.
- **Serie e ultima versione** (dalla 1.2.5): un file con la versione nel nome (due o più numeri separati da punti, con
  o senza `v` davanti: `plumecms-1.2.4.zip`, `guida-v2.0.pdf`) fa parte di una serie, il cui nome è quello del file con
  `ultima` al posto della versione (`plumecms-ultima`, `plumecms-ultima-aggiornamento`, `guida-ultima`).
  `/scarica/<serie>/` manda il file con la versione più alta (`version_compare`; a pari versione il più recente). Nei testi
  delle pagine, titolo della scheda e meta compresi, `{versione:<serie>}` diventa quel numero (`scaricabili_versioni_nel_testo()`,
  chiamata da `sito_html()` e, nella tela dell'editor, da `editor_disegna()`); una serie che non c'è dà un testo vuoto.
  Così una versione nuova si pubblica caricando solo il file. Un numero solo (`listino-2026`) non è una versione.

- **Impronta e versione per gli altri siti** (dalla 1.3.0): `api/versione.php?serie=<serie>` risponde in JSON con versione,
  nome del file, titolo, `sha256`, dimensione, indirizzo `/scarica/<nome>/` e data dell'ultimo file della serie. Lo sha256
  si calcola una volta (`scaricabili_sha256()`, `includes/scaricabili.php:256`) e si ricorda nell'impostazione
  `scaricabili_impronte` finché il file non cambia. Accetta solo GET e nomi di serie (con `ultima`); 400, 404, 405
  altrimenti. È così che il sito di plumeCMS dice da sé qual è l'ultima versione (19.6).

**Widget Download** (gruppo "Dal sito"): i file scelti uno per uno (o una serie, "Sempre l'ultima"; `{versione}` nel
titolo diventa il numero del file mostrato), ognuno con titolo e descrizione propri se servono,
oppure gli ultimi N caricati. Mostra a scelta icona del tipo, descrizione, tipo e peso, versione, data, conteggio (con il
testo `{n} download`). Disposizione a elenco o a schede (da 1 a 4 per riga, per misura). Il CSS sta in
`sito/assets/download.css` e si carica solo nelle pagine che hanno il widget. Un file eliminato sparisce dal sito; nella
tela dell'editor compare l'avviso "File non trovato".

---

### 14.2 Foto più leggere (dalla 1.4.0)

`includes/foto.php`. Al caricamento di una foto JPEG o PNG dalla pagina Media, dall'editor (`editor-api.php`, azione
`carica`) o dalla newsletter (`admin/newsletter-immagine.php`):

1. **Orientamento**: `foto_orientamento()` (`includes/foto.php:55`) legge il tag EXIF Orientation dai byte del JPEG
   (non serve l'estensione exif); `foto_gd_raddrizza()` applica tutti gli otto casi, anche quelli allo specchio.
2. **Lato massimo**: se il lato lungo supera `foto_lato_max` (2560 di partenza, da 800 a 8000) la foto si rimpicciolisce
   con le stesse proporzioni (`imagecopyresampled`, trasparenza tenuta nelle PNG).
3. **Riscrittura**: JPEG qualità 85, PNG livello 6. Spariscono tutti i metadati (posizione GPS, modello del telefono) e
   qualunque cosa nascosta dopo i pixel, come con la ricodifica di sempre.
4. **WebP accanto**: `<nome>.jpg.webp` (o `.png.webp`) qualità 80, tenuta solo se pesa meno dell'originale. Non per la
   newsletter: molti programmi di posta non la mostrano.

`foto_alleggerisci()` (`includes/foto.php:108`) ritorna `fatto => false` e lascia il file com'è se l'impostazione è
spenta, se manca un motore (`foto_motore()`: GD, altrimenti Imagick), se la foto non si legge o se la memoria non basta
(`foto_memoria_basta()` prova ad alzare `memory_limit`); chi chiama ripiega sulla ricodifica di sempre. Nessun errore a
video, il motivo va nel log. Con Imagick (`foto_con_imagick()`) il percorso è lo stesso: **non provato** su un server
vero, l'ambiente delle prove ha solo GD.

**Sul sito**: `sito_html()` (`sito/index.php:56`), con l'impostazione accesa, manda `Vary: Accept` e, se il browser
dichiara `image/webp` nell'intestazione Accept della pagina, passa la pagina da `foto_webp_nel_html()`
(`includes/foto.php:199`): negli attributi `src`, `srcset`, `data-src`, `data-srcset`, `style` e nei blocchi `<style>`
(gli sfondi delle sezioni dell'editor) l'indirizzo di una foto della cartella delle immagini diventa quello della sua
WebP, solo se la WebP c'è sul disco. Link, `og:image` e dati strutturati non cambiano. Gli indirizzi di prima restano
validi: la foto originale non si sposta. Chrome, Edge e i browser basati su Chromium dichiarano la WebP; chi non la
dichiara nella richiesta della pagina (Safari, per esempio) riceve l'originale, già rimpicciolito.

**Impostazioni**: pagina Media, riquadro "Foto più leggere", solo per l'amministratore (il POST di un redattore riceve
403): interruttore (`foto_leggere`, `'1'` o `'0'`) e lato massimo (`foto_lato_max`). Il riquadro dice con che cosa lavora
il server (GD, Imagick o nessuno) e se la WebP è disponibile.

**Le foto già caricate non si toccano**: nessuna nasce rimpicciolita o con la WebP dopo l'aggiornamento, quindi il sito
resta identico al pixel. Non c'è un pulsante per alleggerire quelle vecchie (scelta voluta: cambierebbe le foto del sito
in un colpo solo).

## 15. Form, popup, newsletter e posta

### 15.1 Form

`includes/form.php`, pagina Form. Si costruisce un form nel pannello e lo si mette in una pagina con il widget
Modulo (`form:ID`).

- Tabelle: `moduli` (definizione) e `moduli_invii` (invii).
- Tipi di campo: testo, email, telefono, testo lungo, scelta, scelta multipla, spunta, data, numero.
- Ogni form ha: titolo, introduzione, campi, testo del pulsante, consenso, lista della newsletter (con o senza
  conferma), avviso per email, pagina di ringraziamento (`/grazie/?f=ID`).

All'invio (`form_ricevi()`, `includes/form.php:225-275`), in ordine:
1. campo esca invisibile (`sito_web`): se è pieno, è un robot;
2. validazione di ogni campo secondo il tipo;
3. consenso alla privacy obbligatorio;
4. captcha (prova di lavoro fatta dal browser, 15.4);
5. limite di invii per IP;
6. salvataggio dell'invio;
7. iscrizione alla lista scelta, con o senza doppio consenso;
8. avviso all'indirizzo delle notifiche;
9. innesco dei flussi della newsletter con origine `form`.

Gli invii si leggono in Newsletter e contatti > Invii dei form, e si esportano in CSV (celle protette contro le
formule, capitolo 18.10).

### 15.2 Popup

`includes/popup.php`, pagina Popup.
- Dove: tutte le pagine, home, articoli, pagine, percorsi scelti.
- Quando: dopo N secondi, a una percentuale di scorrimento, all'uscita (il mouse va verso la barra).
- Valore dell'innesco 0-600; frequenza 0-365 giorni, ricordata nel browser del visitatore (`localStorage`).
- Dentro un popup può stare un modulo: newsletter, `form:ID` o un modulo di un modulo del sito.

### 15.3 Posta

`includes/posta.php`, pagina Impostazioni > Configurazione email.
- Con il server SMTP impostato (`posta_smtp`) il CMS usa un client SMTP scritto in casa: SSL sulla porta 465 o
  STARTTLS sulla 587, con autenticazione se c'è un utente.
- Senza SMTP usa `mail()` di PHP.
- Gli avvisi (form, sicurezza) vanno all'indirizzo `notifica_email`.
- La password SMTP è salvata nel database insieme alle altre impostazioni della posta.

### 15.4 I pezzi comuni dei moduli: captcha, campi, limiti

`includes/moduli.php` dà a newsletter e form del costruttore (15.1) le stesse cose:
- lettura e controllo dei campi (nome, cognome, email, privacy), campo esca invisibile alle persone;
- limite: 5 invii l'ora per IP e per modulo;
- l'impaginazione delle email (`moduli_email_html`, intestazione e firma dalle impostazioni);
- captcha a prova di lavoro: il browser risolve un piccolo calcolo, la sfida vale 1200 secondi e non si può
  riusare (`captcha_usati`).

Il segreto che firma captcha e link della newsletter è l'impostazione `moduli_segreto`, creata da sola.

Fino alla 1.2.7 qui c'erano anche i moduli ebook e Laser Coaching, nati per il primo sito: dalla 1.2.8 non sono più
nel nucleo. Un modulo del sito che ha moduli suoi li porta così:
- nel widget Modulo e nei popup: filtro `moduli_disponibili` (aggiunge le voci) e `modulo_html` (li disegna);
  un tipo che nessuno conosce sul sito non esce, nell'editor compare un avviso;
- nel testo delle pagine della scheda classica: filtro `pagina_moduli`;
- i suoi indirizzi (conferma, download, pagina di cortesia): evento `sito_percorso`, prima dei percorsi della
  newsletter; il modulo risponde e chiude.

### 15.5 Newsletter

`includes/newsletter.php` e `includes/newsletter-flussi.php`, gruppo Newsletter e contatti.

**Iscritti e liste.** Stati: in attesa, attivo, uscito. Un iscritto può stare in più liste.

**Doppio consenso.** L'iscrizione salva l'indirizzo in attesa con un token e manda l'email di conferma; il clic
su `/newsletter-conferma/` lo attiva e fa partire i flussi "iscrizione" e "lista".

**Uscita.** Ogni email ha un link di uscita firmato (HMAC) e le intestazioni `List-Unsubscribe` e
`List-Unsubscribe-Post` (uscita con un clic dai programmi di posta). L'uscita vera avviene con un POST su
`/newsletter-esci/`. Chi esce perde anche le email ancora in coda.

**Campagne.** Stati: bozza, programmata, in invio, inviata. Nell'oggetto e nel testo `{nome}` diventa il nome
dell'iscritto.

**Invio.** Dal cron (`cron-newsletter.php`, ogni 5 minuti), `newsletter_invia_giro()`:
- un solo invio alla volta (lock di MySQL);
- avvia le campagne programmate arrivate alla loro ora;
- al massimo 40 email per giro, entro il limite orario (predefinito 150 l'ora);
- tre tentativi per indirizzo, poi errore; si ferma dopo 3 errori senza nessun invio riuscito;
- con i posti rimasti, manda le email dei flussi.

**Flussi automatici.** Un flusso parte da un innesco (iscrizione, lista, form, apertura, clic, inattività) e
segue nodi: email, attesa, bivio (secondo una condizione), fine. Le persone dentro un flusso stanno in
`newsletter_corsi`.

**Statistiche.** Aperture (pixel) e clic (link che passano da `api/nl.php`, firmati) si registrano in
`newsletter_eventi`, solo per le email vere e non per quelle di prova. La pagina Statistiche mostra campagne,
clic per link, iscritti per mese.

---

## 16. SEO e AEO, file calcolati, feed

`includes/seo.php` (configurazione e strumenti), `includes/sito-seo.php` (meta e JSON-LD delle pagine), pagina
SEO e AEO.

### 16.1 Configurazione

Una sola impostazione JSON, `seo_config` (predefiniti in `seo_predefiniti()`, filtrabili con
`seo_predefiniti`). Gruppi:
- verifica (Google, Bing, Yandex, Pinterest);
- IndexNow;
- Google (Search Console);
- analisi (GA4, Tag Manager, Meta Pixel, Clarity, banner del consenso, codice libero);
- titoli (modello `%titolo% %sep% %sito%` per tipo), meta, robots per tipo;
- `robots.txt` (testo personale, blocco dei crawler AI);
- `llms.txt` (testo personale, versione completa);
- sitemap (pagine, immagini, divisa);
- schema (Article, FAQ, Breadcrumb, WebSite, SearchAction, Speakable, Organization, Person);
- AEO (risposta nel primo paragrafo entro 60 parole, riassunto di 40-60 parole);
- organizzazione e persona.

### 16.2 Le schede

Panoramica, motori, copertura, prestazioni, analisi, globale, schema, redirect, registro. Il menu laterale ne
mostra sette (copertura e prestazioni insieme, il registro si apre dalla pagina).

### 16.3 Meta e JSON-LD

`sito_meta()` scrive titolo, descrizione (se manca: i primi 160 caratteri del testo), robots, canonical,
Open Graph e `article:*`. Il JSON-LD ha una parte fissa (WebSite con SearchAction, Organization o
ProfessionalService, Person) e una per pagina (BlogPosting, FAQPage, BreadcrumbList, Speakable), ognuna
accendibile e spegnibile.

### 16.4 Analisi del contenuto

`seo_analisi()` controlla: keyword nel titolo, nella descrizione, nell'indirizzo, all'inizio; densità della
keyword e delle correlate; lunghezza del testo, del titolo e della descrizione; immagine e testo alternativo;
link interni ed esterni; paragrafi; primo paragrafo; riassunto; domande frequenti; leggibilità; trattini lunghi.
Il redattore la usa dall'editor (`seo-api.php`).

### 16.5 Redirect e 404

- Tabella `redirect`: da, a, codice (predefinito 301), confronto (`exact`, `contains`, `start`, `end`,
  `regex`), attivo, origine (per esempio `slug` per quelli nati da un cambio di slug).
- Si gestiscono nella scheda Redirect; si creano anche da soli (8.4) e con i pacchetti (19.1).
- I 404 si contano nella tabella `seo_404` (senza file statici e sonde dei robot), tenendo le ultime 3000 righe:
  la scheda mostra quali indirizzi mancano e permette di creare il redirect.

### 16.6 File calcolati

Nessuno di questi è un file sul disco: il CMS li scrive a ogni richiesta.

| Indirizzo | Cosa contiene |
|---|---|
| `/robots.txt` | il testo personale, altrimenti: `Allow: /`, divieti per pannello, cron, log, API, ricerca, autori, feed; i blocchi dei crawler AI scelti; l'indirizzo della sitemap |
| `/sitemap.xml` | home, articoli e (a scelta) pagine indicizzabili, categorie con articoli; a scelta le immagini; oppure l'indice di `sitemap-articoli.xml`, `sitemap-pagine.xml`, `sitemap-categorie.xml` |
| `/llms.txt` | il testo personale, altrimenti: nome e descrizione del sito, nota di citazione (filtro `llms_intestazione`), servizi (filtro `llms_servizi`), guide pillar, articoli, glossario, feed e sitemap |
| `/llms-full.txt` | il testo completo dei contenuti, solo se acceso |
| file di verifica | il file `.html` di Google, `/BingSiteAuth.xml`, `/<chiave IndexNow>.txt` |

### 16.7 Feed

RSS 2.0 in italiano: `/feed/` (ultimi 10 articoli indicizzabili), `/category/<slug>/feed/`,
`/tag/<slug>/feed/`; `/comments/feed/` e `/<articolo>/feed/` esistono vuoti, per chi li cerca come su
WordPress.

### 16.8 Search Console e IndexNow

- **Search Console**: con un account di servizio Google (chiave JSON caricata nel pannello) il CMS firma da sé
  un JWT RS256 con openssl e chiede l'ispezione degli indirizzi (quota 2000 al giorno) e le prestazioni.
- **IndexNow**: la chiave si pubblica in `/<chiave>.txt`. A ogni salvataggio di un contenuto pubblico e
  indicizzabile l'indirizzo entra in coda (`seo_coda`); la coda si svuota con i due cron.
- Le chiamate verso l'esterno passano dal controllo SSRF (capitolo 18.10): solo host ammessi.

Le chiamate vere verso Google e IndexNow non sono ancora state provate da un server con la rete aperta.

---

## 17. Moduli: manifest, filtri, eventi

Un modulo è una cartella `moduli/<nome>/` con un file `modulo.php` che restituisce un array (il manifest). La
guida completa è `docs/cms-moduli.md`; qui c'è il riferimento.

### 17.1 Caricamento

`cms_moduli()` (`includes/cms.php:176-193`):
- trova `moduli/*/modulo.php`; il nome della cartella rispetta `^[a-z][a-z0-9_-]*$`;
- un file `DISATTIVO` nella cartella salta il modulo;
- un errore nel caricamento finisce nel log e il modulo si salta;
- il manifest deve essere un array; si aggiungono da soli nome, etichetta, versione, cartella.

### 17.2 Le chiavi del manifest

| Chiave | A cosa serve | Chi la legge |
|---|---|---|
| `etichetta`, `versione`, `descrizione` | nome e dati mostrati in Editor > Informazioni | `admin/informazioni.php` |
| `impostazioni` | valori predefiniti di impostazioni (anche `uploads_dir`, `uploads_url`) | `cms_predefinito()` |
| `tipi_contenuto` | tipi di contenuto in più | `contenuti_tipi()` |
| `menu` | voci del menu del pannello (nome, url, icona, attivo, sotto, admin, dopo, gruppo) | `cms_menu_integra()` |
| `pannello`, `api` | pagine del pannello e API del modulo | `cms_pagina_modulo()` |
| `installa` | funzione che crea le tabelle del modulo | `cms_moduli_installa()` |
| `cron` | passi del cron giornaliero | `cron-daily.php` |
| `widget` | file che registrano widget dell'editor | `includes/editor-controlli.php` |
| `azioni` | azioni dei pacchetti di aggiornamento | `includes/aggiornamenti.php` |
| `schema` | aggiornamento delle tabelle prima di un pacchetto | `includes/aggiornamenti.php` |
| `filtri`, `eventi` | funzioni agganciate ai filtri e agli eventi | `cms_filtro()`, `cms_evento()` |

Esempio minimo:

```php
<?php
return [
    'etichetta' => 'Il mio modulo',
    'versione'  => '1.0',
    'filtri'    => [
        'blog_filtri' => function ($slug) { $slug[] = 'notizie'; return $slug; },
    ],
    'eventi'    => [
        'sito_genere' => function ($voce, $meta) { /* serve una pagina e chiude con exit */ },
    ],
];
```

### 17.3 Filtri ed eventi

`cms_filtro($nome, $valore, ...$argomenti)` passa il valore a ogni modulo, in ordine di cartella, poi al tema
attivo; ognuno restituisce il valore (cambiato o no). `cms_evento($nome, ...$argomenti)` chiama tutti, senza
valore di ritorno.

Elenco completo dei filtri e degli eventi chiamati nel codice:

| Nome | Tipo | Argomenti | Dove |
|---|---|---|---|
| `categorie_protette` | filtro | elenco di slug (`senza-categoria`) | `admin/categories.php:59` |
| `redattore_pagine` | filtro | elenco di pagine in più per il redattore | `admin/includes/auth.php:75` |
| `dashboard_numeri` | filtro | numeri in più sulla dashboard | `admin/index.php:32` |
| `pacchetto_test` | filtro | `null`, definizione di un test | `includes/aggiornamenti.php:636` |
| `design_predefiniti` | filtro | predefiniti dello Stile del sito | `includes/design.php:153` |
| `editor_albero_articolo` | filtro | `null`, articolo | `includes/editor.php:545` |
| `editor_albero_home` | filtro | `null` | `includes/editor.php:557` |
| `menu_partenza` | filtro | menu di partenza | `includes/menu-nav.php:46` |
| `menu_scelte_speciali` | filtro | voci speciali del menu | `includes/menu-nav.php:215` |
| `modelli_partenza` | filtro | `['header','footer']` | `includes/modelli.php:81` |
| `seo_predefiniti` | filtro | configurazione SEO di partenza | `includes/seo.php:157` |
| `uscita_host` | filtro | host ammessi per le chiamate verso l'esterno | `includes/sicurezza-html.php:353` |
| `sicurezza_predefiniti` | filtro | predefiniti della sicurezza (solo chiavi esistenti) | `includes/sicurezza.php:53` |
| `csp_pannello_inline` | filtro | pagine del pannello che hanno bisogno di `unsafe-inline` | `includes/sicurezza.php:762` |
| `csp_pannello_sorgenti` | filtro | sorgenti CSP in più per pagina | `includes/sicurezza.php:766` |
| `sito_contenuti` | filtro | tutti i contenuti, se servono ai redirect | `includes/sito-dati.php:104` |
| `sito_html_contenuto` | filtro | `null`, contenuto | `includes/sito-dati.php:164` |
| `sito_mappa` | filtro | mappa degli indirizzi, contenuti attivi | `includes/sito-mappa.php:40` |
| `servizi_predefiniti` | filtro | pagine servizio per il JSON-LD | `includes/sito-seo.php:155` |
| `jsonld_about` | filtro | temi del JSON-LD della home | `includes/sito-seo.php:160` |
| `categorie_noindex` | filtro | categorie da non indicizzare | `includes/sito.php:24` |
| `schema_colonne` | filtro | colonne in più delle tabelle del sito | `includes/sito.php:75` |
| `importa_contenuto` | filtro | `null`, JSON, riga, categoria | `includes/sito.php:254` |
| `llms_servizi` | filtro | percorsi dei servizi | `sito/extra.php:81` |
| `llms_intestazione` | filtro | righe di testa di `llms.txt` | `sito/extra.php:88` |
| `sito_menu` | filtro | voci del menu scritto nel codice | `sito/viste.php:17` |
| `blog_filtri` | filtro | categorie mostrate come filtri del blog | `sito/viste.php:21` |
| `testata_fissa` | filtro | `null`, menu, menu del telefono | `sito/viste.php:104` |
| `vista_home` | filtro | `null` | `sito/viste.php:482` |
| `moduli_disponibili` | filtro | moduli del widget Modulo e dei popup (1.2.8) | `includes/editor-widget.php:85` |
| `pagina_moduli` | filtro | HTML di una pagina della scheda classica, percorso (1.2.8) | `sito/viste.php:267` |
| `modulo_html` | filtro | `''`, tipo, percorso: disegna un modulo di un modulo (1.2.8) | `sito/viste.php:284` |
| `sito_query` | evento | query string, percorso | `sito/index.php:94` |
| `sito_percorso` | evento | percorso, query: un modulo risponde ai suoi indirizzi (1.2.8) | `sito/index.php:117` |
| `sito_genere` | evento | voce della mappa, metadati | `sito/index.php:222` |

Un tema può usare solo quelli del sito pubblico (`docs/manuale-temi.md`, capitolo 9).

### 17.4 Le pagine di un modulo

Le pagine del pannello e le API di un modulo si servono con `cms_pagina_modulo($modulo, $pagina)`: un file
ponte in `admin/` o `api/` la chiama, e il CMS carica il file del modulo o risponde 404. Nel prodotto non ci sono
file ponte: stanno nel repository del sito che ha il modulo.

---

## 18. Sicurezza

Il motore sta in `includes/sicurezza.php`, `includes/sicurezza-password.php`, `includes/sicurezza-html.php`. La
pagina è Impostazioni > Sicurezza (solo admin). Il documento per l'uso quotidiano è `docs/sicurezza.md`.

### 18.1 Impostazioni

Una sola impostazione JSON, `sicurezza_cfg` (`includes/sicurezza.php:25-45`):

| Chiave | Predefinito | Limiti | Cosa fa |
|---|---|---|---|
| `tentativi_max` | 5 | 3-20 | tentativi falliti per IP prima del blocco |
| `blocco_minuti` | 10 | 1-1440 | durata del primo blocco per IP |
| `sessione_ore` | 12 | 1-720 | durata massima di una sessione |
| `inattivita_minuti` | 120 | 5-1440 | sessione chiusa dopo questa inattività |
| `totp_admin` | 0 | | 2FA obbligatoria per gli amministratori |
| `ip_attivo`, `ip_elenco` | 0, vuoto | | il pannello si apre solo da questi IP |
| `csp_sito`, `csp_pannello` | `applica` | `applica`, `rapporto`, `spenta` | CSP del sito e del pannello |
| `csp_extra` | vuoto | | sorgenti in più, una per riga (`img-src https://...`) |
| `hsts` | 0 | | intestazione HSTS (solo su https) |
| `html_widget`, `codice_libero` | `admin` | | chi può usare il widget HTML e il codice libero |
| `avvisi_email` | 1 | | email per blocchi, nuovi IP, reimpostazioni |
| `firma_pacchetti` | 0 | | pacchetti di aggiornamento solo firmati |
| `uscita_host` | vuoto | | host in più per le chiamate verso l'esterno (`*.dominio` ammesso) |
| `root_php_fino` | 0 | al massimo adesso + 1 ora | la "finestra di un'ora" (18.7) |

Un valore della CSP non valido diventa `rapporto`.

### 18.2 Sessioni

- Cookie: solo cookie, modalità stretta, `HttpOnly`, `SameSite=Lax`, `Secure` su https, durata fino alla
  chiusura del browser.
- Al login: id di sessione nuovo, token CSRF nuovo, la sessione si registra in `sicurezza_sessioni`.
- A ogni pagina: la sessione si rifiuta se è scaduta (`sessione_ore`), inattiva (`inattivita_minuti`), revocata
  (l'utente ha chiuso tutti i dispositivi o cambiato password) o se l'IP non è in elenco.
- "Esci da tutti i dispositivi" e il cambio di password chiudono le altre sessioni.
- Un amministratore che entra da un IP mai visto nei suoi ultimi 60 accessi genera l'evento `nuovo_ip` e
  un'email.

### 18.3 Password

(`includes/sicurezza-password.php`)
- Da 12 a 200 caratteri.
- Non contiene il nome utente, né la parte prima della `@` dell'email.
- Non è un solo carattere ripetuto, né una sequenza (`123456...`, `abcdef...`, `qwerty...`).
- Non è una password comune, nemmeno con qualche carattere in più alla fine.
- Hash argon2id (64 MB, 4 passaggi); dove manca, bcrypt costo 12. Al login l'hash si rifà se le impostazioni
  sono cambiate.
- Per un utente che non esiste si verifica comunque un hash finto: il tempo di risposta non rivela se l'utente
  c'è.

**Password dimenticata**: la risposta è sempre la stessa (non dice se l'email esiste); il link vale 30 minuti e
si usa una volta; nel database resta solo l'impronta del token. Al massimo 3 richieste l'ora per email e 10 per
IP. Reimpostare chiude tutte le sessioni e manda un'email.

### 18.4 Due fattori (TOTP)

- Codici a 6 cifre ogni 30 secondi (come Google Authenticator), con un passo di tolleranza; lo stesso codice non
  si usa due volte.
- 8 codici di recupero (`xxxxx-xxxxx`), ognuno valido una volta.
- Si attiva da Account (QR code); disattivarla o rigenerare i codici chiede la password. Un amministratore
  azzera la 2FA di un altro utente da Utenti.
- Con la 2FA obbligatoria, un amministratore senza 2FA può aprire solo Account, Esci e Login.
- Non si può rendere obbligatoria senza averla attiva su di sé.

### 18.5 Blocchi dei tentativi

- **Per IP**: al tentativo `tentativi_max` sbagliato l'IP si blocca per `blocco_minuti`; ogni tentativo in più
  raddoppia la durata, fino a un giorno. Il blocco per IP ferma anche la password giusta.
- **Per nome utente**: 20 errori in 15 minuti, da qualunque IP, bloccano il nome per 15 minuti, poi la durata
  raddoppia.
- **IP di casa**: da un IP da cui quell'utente è già entrato, mai bloccato, con la password giusta si entra anche
  durante il blocco del nome. Così chi attacca il nome da fuori non chiude fuori il proprietario.
- Il primo blocco manda un'email. La pagina Sicurezza toglie un blocco a mano.

### 18.6 Lista IP

IPv4 e IPv6, indirizzi singoli o reti (`192.168.1.0/24`), separati da spazi, virgole, punti e virgola o righe;
le righe che cominciano con `#` sono commenti. Il salvataggio si rifiuta se l'IP di chi salva non è in elenco.

### 18.7 La finestra di un'ora

Dalla pagina Sicurezza, con la password, si apre una finestra di un'ora (`root_php_fino`) in cui il pannello
accetta senza firma:
- pacchetti di aggiornamento con file eseguibili nella radice del sito (19.2);
- temi con file PHP (12.5).

La finestra si chiude da sola dopo un'ora o con il pulsante.

### 18.8 Intestazioni e CSP

Su ogni risposta: niente `X-Powered-By`; `X-Content-Type-Options: nosniff`;
`Referrer-Policy: strict-origin-when-cross-origin`; `X-Frame-Options: SAMEORIGIN`; `Permissions-Policy` che spegne
fotocamera, microfono, posizione, pagamenti, USB; HSTS (6 mesi) se acceso e su https.

Sulle pagine HTML, la **CSP**:
- `script-src 'self'` più un nonce casuale per ogni pagina: gli script in linea senza nonce non partono;
- `style-src 'self' 'unsafe-inline'` più Google Fonts; `img-src 'self' data: blob: https:`;
  `object-src 'none'`; `form-action 'self'`; `frame-ancestors 'self'`;
- sul sito, `frame-src` per YouTube, Vimeo, Google Maps, OpenStreetMap; le sorgenti di GA4, Tag Manager, Pixel e
  Clarity solo se gli ID nel pannello SEO sono validi;
- in modalità `rapporto` la CSP non blocca ma manda i rapporti a `api/csp.php`, che si leggono nella scheda
  Rapporti CSP.

Nel pannello le intestazioni partono a fine pagina dal buffer `sicurezza_pannello_fine`. Per l'HTML scritto da
un amministratore c'è `sicurezza_nonce_script()`, che aggiunge il nonce ai suoi `<script>`.

### 18.9 Campi protetti contro ModSecurity

ModSecurity blocca molti POST che contengono HTML. Il pannello (`admin/assets/campi-protetti.js`) manda i campi
con HTML in base64 con il prefisso `~b64~`; `auth.php` li decodifica prima che la pagina li legga. Un valore senza
prefisso arriva com'è.

### 18.10 Sanificatori

(`includes/sicurezza-html.php`)
- **Indirizzi** (`sicurezza_url_pulito`): solo `http(s)`, percorsi, `#`, `?`, `mailto:`, `tel:`, `sms:`;
  `data:image` solo per le immagini.
- **Ritorni** (`sicurezza_ritorno_pannello`): solo pagine del pannello, niente redirect verso fuori.
- **HTML** (`sicurezza_html_pulito`): parser DOM; spariscono `script`, `style`, `iframe`, `object`, `embed`,
  `form`, `input`, `svg`, `math`, `template` e gli attributi `on*`; i link con `target=_blank` ricevono
  `rel="noopener noreferrer"`.
- **CSS** (`sicurezza_css_pulisci`): via `@import`, `@charset`, `expression`, `behavior`, `javascript:`,
  `data:text/html`; `url()` solo interni o `data:image`. `sicurezza_css_confina` chiude ogni regola dentro
  `.ed-<id>`.
- **SVG** (`sicurezza_svg_pulito`): via entità, `DOCTYPE`, script, `foreignObject`, animazioni, gestori,
  collegamenti esterni.
- **Immagini**: ricodifica con GD (capitolo 14).
- **CSV**: un apice davanti alle celle che cominciano con `=`, `+`, `-`, `@` (niente formule in Excel).
- **Chiamate verso l'esterno** (SSRF): solo host ammessi (IndexNow, Bing, Yandex, Google, più `uscita_host`);
  niente credenziali nell'indirizzo; niente IP privati, nemmeno dopo la risoluzione DNS.

### 18.11 Registro

Tabella `sicurezza_log`: evento, utente, IP, browser, dettagli, data. Le righe più vecchie di 180 giorni si
cancellano da sole. Eventi: login, login fallito, login bloccato, login durante un blocco, blocco, 2FA fallita,
logout, sessione scaduta, sessioni chiuse, password cambiata, richiesta e reimpostazione della password, 2FA
attivata e disattivata, codice di recupero usato, IP rifiutato, CSRF rifiutato, pacchetto applicato,
impostazioni cambiate, utente, nuovo IP. La scheda Registro filtra per evento, utente, IP e date.

### 18.12 CSRF

Ogni form del pannello ha il campo nascosto `_csrf`; le chiamate JSON mandano l'intestazione `X-CSRF`. Il
controllo (`csrf_ok()`) confronta il token e controlla anche che `Origin` o `Referer`, se ci sono, siano del sito.
Un fallimento risponde 403 e scrive l'evento `csrf_rifiutato`.

### 18.13 Controlli della pagina Sicurezza

La scheda Stato controlla: https, CSP, HSTS, PHP almeno 8.2, `display_errors` ed `expose_php` spenti, permessi di
`config.local.php`, che `config.local.php`, `logs/` e `install/` non si aprano dal web (prova vera con una
richiesta), `temi/.htaccess`, `uploads/.htaccess`, 2FA degli amministratori, lista IP, riga di emergenza, login
falliti nelle ultime 24 ore, blocchi in corso, firma dei pacchetti, versione.

Azioni: salva, sblocca, esci da tutti, apri e chiudi la finestra di un'ora, svuota i rapporti CSP, togli
`install/`, scrivi `uploads/.htaccess`.

### 18.14 Se ci si chiude fuori

Si aggiunge in `includes/config.local.php` (con il gestore dei file del server):

```php
define('CMS_SICUREZZA_SPEGNI', true);
```

Spegne la 2FA e la lista IP, non i blocchi dei tentativi. Si entra, si sistema, si toglie la riga: la pagina
Sicurezza la segnala in rosso finché c'è. Per un blocco dei tentativi si aspetta che scada, oppure si svuota la
tabella `<prefisso>sicurezza_tentativi` dal database.

---

## 19. Aggiornamenti e pacchetti

`includes/aggiornamenti.php`, pagina Impostazioni > Aggiornamenti. Un pacchetto è uno zip con un
`manifest.json` e i file che porta.

### 19.1 Il manifest

```json
{
  "nome": "plumeCMS 1.2.1",
  "descrizione": "...",
  "creato": "2026-10-27",
  "applica_dal": "",
  "file":     [ { "da": "new/includes/temi.php", "a": "new/includes/temi.php" } ],
  "elimina":  [ "new/vecchio.php" ],
  "sql":      [ "sql/001.sql" ],
  "azioni":   [ ],
  "articoli": [ ],
  "test":     [ ],
  "redirect": [ { "da": "/vecchio/", "a": "/nuovo/" } ],
  "note": "...",
  "firma": "..."
}
```

- `nome` è obbligatorio. `applica_dal` (`AAAA-MM-GG`) programma il pacchetto: si applica col cron di quel
  giorno.
- Ogni file citato deve esserci nello zip; `azioni` devono esistere nel CMS o nei moduli.

**Pagine, stile e impostazioni** (dalla 1.2.3): un pacchetto può portare un sito intero.

| Voce | Cosa contiene | Cosa fa |
|---|---|---|
| `impostazioni` | `{"sito_nome": "...", ...}`; ammesse solo `sito_nome`, `sito_titolo`, `sito_sottotitolo`, `sito_descrizione`, `home_descrizione` | le scrive nelle impostazioni |
| `editor` | un JSON nel formato di Editor > Impostazioni, Esporta (`formato: "editor-impostazioni"`) | lo importa come il pulsante Importa |
| `editor_ambiti` | quali parti di `editor` importare: `stile`, `modelli`, `menu`, `temi` | predefinito: stile, modelli, menu |
| `pagine` | un JSON per pagina: `{slug, titolo, struttura, seo_titolo, seo_descrizione, robots, estratto}` | crea la pagina o aggiorna quella con lo stesso slug, pubblicata; la versione di prima resta in Versioni (`sito_importa_pagina()`) |

Chi usa queste voci mette anche l'azione `sito-pagine-e-stile`, che da sola non fa niente: un motore più vecchio
della 1.2.3 non la conosce e rifiuta il pacchetto, invece di saltare in silenzio le voci che non sa leggere.
`tools/pacchetto.py` lo pretende. Un esempio completo è il sito di plumeCMS (`sito-plumecms/costruisci.py`, 23.5).

**Ordine di applicazione** (`agg_applica()`):
1. snapshot: copia del database intero e dei file che il pacchetto tocca;
2. aggiornamento delle tabelle (`sito_schema_aggiorna` e `schema` dei moduli);
3. file;
4. elimina;
5. SQL;
6. test;
7. azioni;
8. articoli;
9. impostazioni, editor (stile, header e footer, menu), pagine;
10. redirect.

Se un passo fallisce, il CMS **ripristina lo snapshot** e il pacchetto resta "fallito"; si può rimettere in
attesa.

Due trappole già pagate:
- un apostrofo dentro un commento `/* */` di un file SQL fa fallire lo spezzatore delle istruzioni;
- un pacchetto che porta un motore nuovo e insieme usa le sue voci nuove lo applica il motore vecchio: servono
  due pacchetti, prima il codice e poi il resto.

### 19.2 Dove si può scrivere

Le destinazioni (`agg_dest_path()`) cominciano con:
- `new/`: la cartella del CMS;
- `uploads/`: la cartella delle immagini;
- `root/`: la radice del sito (`CMS_RADICE_SITO`, altrimenti la cartella del CMS o quella sopra).

Vietati `..` e segmenti vuoti; protetti `new/aggiornamenti/*` e `new/includes/config.local.php`. Gli unici
`.htaccess` ammessi sono `root/.htaccess`, `new/.htaccess`, `uploads/.htaccess`.

In `root/`, senza firma e senza la finestra di un'ora, passano solo file statici (txt, xml, html, ico,
immagini, pdf, css, js, caratteri, json, webmanifest, map) e `root/.htaccess`.

### 19.3 Limiti e firma

- Nomi dello zip puliti (niente percorsi assoluti, `..`, caratteri nulli); ogni file al massimo 200 MB, il
  totale estratto al massimo 600 MB.
- **Firma**: HMAC-SHA256 dell'impronta (una riga "nome sha256" per ogni file tranne il manifest, in ordine) con
  la chiave `CMS_FIRMA_PACCHETTI` (o `CMS_SEGRETO`). Una firma sbagliata si rifiuta sempre; una firma assente si
  rifiuta solo con `firma_pacchetti` acceso.

### 19.4 Snapshot e avanzamento

- Cartella `aggiornamenti/`: `pacchetti/<id>.zip` e `.json`, `snapshot/<id>/` (database compresso, file,
  descrizione). È chiusa al web con un `.htaccess` scritto da solo.
- Si tengono gli ultimi 5 snapshot. Uno snapshot si può ripristinare a mano.
- Durante l'applicazione la pagina mostra l'avanzamento (file `progresso-<id>.json`, letto ogni secondo).
- Gli avvisi di PHP durante l'applicazione vanno nel log, non nella pagina.
- Ogni pacchetto applicato si registra nel registro della sicurezza, con l'indicazione firmato o no; quelli
  applicati dal cron hanno l'utente `cron`.

### 19.5 Aggiornare un'installazione

1. Si costruisce il pacchetto con `python3 tools/cms-pacchetto.py --aggiornamento` (23.1).
2. Pannello, Impostazioni, Aggiornamenti: si carica lo zip.
3. "Applica ora", si conferma, si aspetta la fine.
4. Si controlla in Editor > Informazioni che la versione sia quella nuova.

Il pacchetto del prodotto non contiene moduli: non va mai applicato a un sito che ha moduli suoi nel nucleo:
quel sito ha i suoi pacchetti costruiti con `--sito`.

### 19.6 "C'è una versione nuova" (dalla 1.3.0)

Motore `includes/versione-nuova.php`. Il pannello chiede al sito ufficiale di plumeCMS l'ultima versione della serie
`plumecms-ultima-aggiornamento`. Quale sito è un parametro (dalla 1.3.1, `versione_sito()`), in quest'ordine:
`CMS_AGGIORNAMENTI_URL` nel `config.local.php` (vince, e il pannello lo dice); l'indirizzo scritto in Aggiornamenti, campo
"Indirizzo del sito da cui arrivano gli aggiornamenti" (impostazione `versione_sito`, solo https, senza `?` e `#`; cambiarlo
dimentica la risposta di prima; vuoto torna al predefinito); `VERSIONE_SITO_UFFICIALE` (`includes/versione-nuova.php:28`). La
domanda passa da `api/versione.php` (14.1). Il sito ufficiale non ha niente di speciale: è un plumeCMS che ha lo zip di aggiornamento
nel suo download manager.

- **Quando**: al massimo una volta al giorno (`VERSIONE_OGNI`); la risposta, buona o di errore, si ricorda in
  `versione_controllo_cache`. Mai durante il caricamento di una pagina: dashboard e Aggiornamenti mostrano quello che il
  pannello sa e, se è vecchio, il browser chiede il controllo dopo (`aggiornamenti.php`, azione `versione_controlla`, con
  il token nell'intestazione `X-CSRF`). Solo per l'amministratore. **Controlla adesso** lo rifà subito.
- **Dove si vede**: nella dashboard una fascia "plumeCMS X è disponibile" con il link; in Aggiornamenti la scheda
  Versione, con **Scarica e prepara** e la casella "Controlla da solo, una volta al giorno" (`versione_controllo`).
- **Scarica e prepara** (`versione_scarica_e_prepara()`, `includes/versione-nuova.php:157`): richiede di nuovo i dati,
  scarica lo zip, controlla che la lunghezza e lo sha256 siano quelli annunciati e lo registra con
  `agg_registra_pacchetto()`, come uno zip caricato a mano: da lì "Applica ora" (19.4). Una versione già in coda non si
  scarica due volte.
- **Sicurezza** (`versione_http()`, `includes/versione-nuova.php:65`): solo HTTPS con il certificato verificato (http
  solo con `CMS_AGGIORNAMENTI_HTTP_PROVA`, per le prove); nessun redirect seguito; il file si scarica solo dallo stesso
  host (schema e porta compresi) del sito ufficiale; risposta JSON di al massimo 16 KB, zip di al massimo 50 MB e mai più
  lungo di quanto annunciato; timeout di 5 secondi per il JSON e 60 per lo zip; ogni errore diventa un messaggio, il
  pannello va avanti. Nella coda lo zip passa da `agg_leggi_manifest()`: se Sicurezza vuole i pacchetti firmati, uno zip
  senza firma non entra. Nessuna chiave privata nel CMS: la garanzia è HTTPS più l'impronta.
- Il registro della sicurezza annota ogni pacchetto scaricato così.

### 19.7 Copia del sito (dalla 1.3.0)

Motore `includes/copia-sito.php`, pagina **Impostazioni > Copia del sito** (`admin/copia-sito.php`), solo per
l'amministratore, ogni azione in POST con il token CSRF.

- **Prepara la copia** (`copia_crea()`, `includes/copia-sito.php:114`): uno zip in `aggiornamenti/copie/` con
  `manifest.json` (formato `plumecms-copia`, versione del CMS, prefisso, sito, quante tabelle, righe e file), `db.sql.gz`
  (le sole tabelle con il prefisso del sito, `agg_dump_db($file, $prefisso)`, stesso formato degli snapshot) e le cartelle
  `uploads/`, `temi/`, `moduli/`. Fuori: `includes/config.local.php` e ogni file o cartella nascosti. Il manifest ha la
  firma HMAC-SHA256 dell'impronta dello zip (`sicurezza_pacchetto_impronta()`) con la chiave dei pacchetti di questo sito.
  Lo zip ha permessi 0600; si scarica solo dal pannello (**Scarica**), la cartella è vietata al web. Se ne tengono 6.
- **Ripristina** (`copia_ripristina()`, `includes/copia-sito.php:253`): da una copia caricata (che entra fra quelle sul
  server) o già sul server; serve la spunta di conferma. Prima fa una copia del sito com'è (`prima-del-ripristino`), poi:
  - nomi dello zip controllati (`copia_nome_voce_ok()`): solo `manifest.json`, `db.sql.gz` e file dentro `uploads/`,
    `temi/`, `moduli/`, niente `..`, percorsi assoluti, barre rovesce, parti nascoste; 300 MB per file, 2 GB in tutto,
    rapporto di compressione sotto controllo;
  - database (`copia_ripristina_db()`, `includes/copia-sito.php:214`): passano solo `DROP TABLE IF EXISTS`, `CREATE TABLE
    (...)` e `INSERT INTO ... VALUES` su tabelle con il prefisso della copia, una istruzione per riga, riscritte con il
    prefisso di questo sito; tutto il resto si salta e si conta. Le viste le crea lo schema del sito. L'impostazione
    `sito_url` resta quella di questa installazione;
  - file: si scrivono sopra quelli che ci sono (quelli in più restano). Un file eseguibile (`.php`, `.phtml`, `.phar`,
    `.php.jpg` e simili) entra solo da una copia firmata da questo sito e mai in `uploads/`; da una copia di un altro sito
    si salta, e il pannello lo dice;
  - alla fine `temi/.htaccess` si riscrive (`temi_proteggi()`). Se l'utente collegato non c'è più fra quelli della copia,
    si torna al login.
- **Limite di caricamento**: la pagina mostra il limite del server; il browser avvisa prima di mandare un file troppo
  grande, e il server spiega anche quando il file supera `post_max_size` (la richiesta arriva vuota, senza token). Una
  copia grande si mette con il gestore dei file in `aggiornamenti/copie/` (nome `copia-AAAAMMGG-HHMMSS-nome.zip`) e si
  ripristina dall'elenco.
- La pagina dice in un riquadro che la copia contiene dati sensibili e che la password del database non c'è.

---

## 20. Cron

Due file nella cartella del CMS, da lanciare dalla riga di comando del server. Dalla 1.4.0 il lavoro sta in
`includes/cron.php` (`cron_giornaliero()` `:44`, `cron_newsletter()` `:88`): i due file controllano chi chiama e la
versione di PHP, poi chiamano quelle funzioni con `da = 'cron'`. Le stesse funzioni le chiama il pulsante **Esegui
adesso** della pagina Log cron con `da = 'pannello'`.

```
0 6 * * *    php /percorso/del/cms/cron-daily.php
*/5 * * * *  php /percorso/del/cms/cron-newsletter.php
```

**`cron-daily.php`**, una volta al giorno:
1. applica i pacchetti di aggiornamento programmati per oggi o prima;
2. esegue i passi `cron` dei moduli, ognuno separato dagli altri (un errore non ferma i successivi);
3. dalla 1.5.0, `social_cron()`: l'email "Oggi sul sito" e i testi del calendario (capitolo 27);
4. svuota la coda IndexNow.
Scrive in `logs/cron-daily.log`, che si legge da Impostazioni > Log cron.

**`cron-newsletter.php`**, ogni 5 minuti: svuota la coda IndexNow (log in `logs/seo.log`) e fa un giro della
newsletter (log in `logs/newsletter.log`, capitolo 15.5).

Dal web i cron rispondono 403, a meno che:
- `CMS_CRON_TOKEN` (almeno 32 caratteri) sia definito e la richiesta lo porti come `?token=` o intestazione
  `X-Cron-Token`;
- e si tolga la riga dell'`.htaccess` che vieta `cron-*.php`.

I cron controllano PHP 8.1 o più nuovo; il CMS ne chiede 8.2.

**Lo stato** (1.4.0): ogni giro, da dove arrivi, scrive `logs/cron-stato.json` (`cron_segna()`): per `giornaliero` e
`newsletter` quando, da dove, quanto è durato e le righe di quello che ha fatto; `giornaliero_cron` e `newsletter_cron`
tengono l'ultimo arrivato dal cron vero. `cron_attivo()` (`:113`) dice "il cron gira" se il giro del mattino dal cron è
più recente di 36 ore (`CRON_GIORNO_ORE`) e quello della newsletter di 60 minuti (`CRON_NEWSLETTER_MIN`): il pulsante non
conta. La newsletter, prima, lasciava traccia solo quando mandava qualcosa.

**La pagina Impostazioni > Log cron** (`admin/cron-log.php`, solo amministratore, azioni in POST con il token): cosa
fanno i due giri; come si va (badge "il cron gira" / "il cron non gira", l'ultimo giro e le sue righe); le due righe da
copiare con il percorso vero (`cron_righe()`, `:136`, con il PHP da riga di comando trovato da `cron_php()`: il `php`
accanto a `PHP_BINARY`, poi `/usr/local/bin/php`, `/usr/bin/php`) e i passi di cPanel; il registro del giro del mattino,
il più recente in alto, e "Svuota il registro". Fino alla 1.3.1 la pagina mostrava le istruzioni del sito da cui il CMS è
nato ("Test connessione WP", "Dry run", "Pubblica ora").

---

## 21. Il server: `.htaccess` e Nginx

### 21.1 Apache: `.htaccess` della cartella del CMS

Uguale a `install/esempi/htaccess-radice.txt`:
- niente elenco delle cartelle, `index.php` come pagina iniziale, firma del server spenta;
- tipi corretti per css, js, svg, webp, woff, woff2 (necessari con `nosniff`);
- https obbligatorio (301), rispettando `X-Forwarded-Proto`;
- 403 su `includes`, `logs`, `aggiornamenti`, `privato`, `data`, `sql`, `docs`, `tools`, `test`;
- 403 su `moduli/*/includes|sql|docs` e su ogni PHP dentro `moduli/` e `temi/`;
- 403 su `VERSIONE`, `CHANGELOG.md`, `README.md`, `INSTALLA.md`, `composer.*`, `package*.json`, `.git`, `.env`,
  `.htpasswd`, `.installato`, `.gitkeep`;
- 403 su `cron-*.php` (da togliere solo per i cron via web);
- 403 sugli script dentro `uploads`, `aggiornamenti`, `logs`, `assets`;
- i file veri e le cartelle `admin`, `api`, `install` passano; tutto il resto va a `index.php`;
- negate le estensioni log, sql, md, json, lock, ini, sh, zip, gz, bak, env e simili; negati
  `config.local.php`, `.htaccess`;
- cache: immagini un anno, css e js un mese.

### 21.2 `uploads/.htaccess`

Uguale a `install/esempi/htaccess-uploads.txt`: niente elenco, niente PHP (`php_flag engine off`, handler
tolti), negati gli script, SVG in sandbox con una CSP propria, `nosniff`. Se manca, la pagina Sicurezza lo scrive.

### 21.3 `temi/.htaccess`

Scritto dal CMS stesso (`temi_proteggi()`): nella cartella dei temi non si esegue niente e i JSON non si leggono.

### 21.4 CMS in una cartella con il sito nella radice

`install/esempi/htaccess-sottocartella.txt`: nei commenti c'è l'`.htaccess` da mettere nella radice del dominio,
che manda tutto a `index.php` della radice (copia di `install/esempi/index-radice.php`); nella cartella del CMS le
stesse regole di sicurezza.

### 21.5 Nginx

`install/esempi/nginx.txt`: le stesse regole come blocchi `location` (deny e 404), CSP in sandbox per gli SVG,
`try_files` per `admin`, `api`, `install` con PHP-FPM, e `location / { try_files $uri /index.php$is_args$args; }`.

---

## 22. Errori e log

| Dove | Errori a video | Log |
|---|---|---|
| sito, API, cron | mai | `logs/php-errori.log` |
| pannello | sì, ma le deprecazioni solo nel log; l'uscita è bufferizzata, quindi un avviso non rompe i redirect | `logs/php-errori.log` |
| installatore | mai | `logs/php-errori.log` |

Altri log: `logs/cron-daily.log`, `logs/newsletter.log`, `logs/seo.log`. La dashboard avvisa se i cron non
girano.

Cosa finisce nel log oltre agli errori di PHP: un modulo che non si carica, un widget rotto, un modello di header
o footer rotto, un tema guasto (`temi: <slug>: <errore>`), il database che non risponde prima del 503, un errore
dell'installatore.

Un `display_errors` acceso nella configurazione del server non tocca il sito, perché il CMS lo spegne da sé.

---

## 23. Strumenti e prove

Nel repository, fuori dalla cartella del CMS.

### 23.1 `tools/cms-pacchetto.py`

- Senza argomenti: scrive `releases/cms/plumecms-<VERSIONE>.zip`, il prodotto da installare.
- `--aggiornamento`: scrive anche `plumecms-<VERSIONE>-aggiornamento.zip`, il pacchetto per il pannello
  Aggiornamenti (tutti i file in `new/`, senza `.htaccess` e senza firma).
- `--sito <commit> <nome>`: pacchetto con i soli file cambiati da un commit; serve al repository di un sito con
  moduli suoi.

Prima di scrivere lo zip controlla e rifiuta:
- errori di sintassi PHP, con `php -n -d short_open_tag=1 -l` (come sui server veri, dove `short_open_tag` è
  acceso);
- il prefisso `newdf_` nel codice fuori da `includes/cms.php` e `includes/db.php`;
- il dominio del primo sito nel codice (commenti esclusi).

Avvisa (senza rifiutare) per i caratteri a 4 byte. Ogni file nello zip esce con permessi 0644 e ogni cartella
0755: con 0600 il server web non legge CSS e immagini.

`VERSIONE` deve essere nella forma `X.Y.Z`; a ogni zip con contenuto diverso serve una versione nuova e una voce in
`CHANGELOG.md`.

### 23.2 `tools/pacchetto.py`

Costruisce un pacchetto da una cartella con `manifest.json` e lo controlla (destinazioni, sintassi PHP, SQL senza
righe `--` e senza caratteri a 4 byte, articoli, test).
- `--firma`: firma con la chiave della variabile d'ambiente `PACCHETTO_CHIAVE` (uguale a `CMS_FIRMA_PACCHETTI`
  del sito, o a `CMS_SEGRETO` se quella manca).
- `--rifirma <zip>`: firma uno zip già fatto, anche quello di un tema; scrive `<nome>-firmato.zip`.

### 23.4 `tools/manuali-pdf.js`

`node tools/manuali-pdf.js` scrive `releases/manuali/manuale-uso.pdf` (dalla 1.4.2), `manuale-tecnico.pdf` e `manuale-temi.pdf`: pandoc converte il
Markdown, Chromium (Playwright) stampa il PDF A4 con copertina, indice cliccabile e numeri di pagina. Carattere Carlito e
niente tag di accessibilità nel PDF: con i tag e il carattere Inter (variabile) il manuale tecnico pesava 2,1 MB, così 0,9. Si rifà a ogni
versione, dopo aver aggiornato i manuali.

Il manuale d'uso ha le schermate: la pagina si apre da un file scritto accanto al Markdown (`docs/.stampa-<nome>.html`,
tolto subito dopo), così `immagini/manuale-uso/...` si trova; ogni immagine sola in un paragrafo diventa una figura con
la didascalia (il testo alternativo); le checklist (`- [ ]`) diventano caselle. Il PDF deve restare sotto 1900 KB
(il limite delle parti del sito di plumeCMS): oggi 1,8 MB.

**`tools/manuale-uso-schermate.js` (1.4.2).** Le schermate del manuale d'uso. Esegue gli esempi del manuale come una
persona, su un plumeCMS appena installato con il kit Attività (`prove/prova-kit.js ... attivita ...`), e fotografa ogni
passo con i pallini rossi numerati: scrive l'articolo "Chiusi per ferie" nell'editor e lo pubblica dalla scheda, ne
programma un altro, costruisce la pagina "Lavora con noi" (sezione a due colonne, immagine, elenco, pulsante, sezione
pronta, form), la mette nel menu, crea un form, un popup, una campagna, un utente, un download. Se un passo non si può
fare, si ferma: il manuale e il pannello restano d'accordo. `node tools/manuale-uso-schermate.js <base> <utente>
<password> [capitolo]`. Le foto nascono PNG in `/tmp/manuale-uso-png` e diventano JPEG una volta sola (800 px, qualità
38) in `docs/immagini/manuale-uso/`; `--stringi` rifà solo la conversione. Le foto d'esempio (disegnate, niente foto di
terzi) e il report dell'import da WordPress (dalla prova contro un WordPress vero) stanno in `esempi/manuale-uso/`.
Le immagini non entrano nello zip del CMS (solo il Markdown, in `docs/`).

### 23.5 Il sito di plumeCMS: `sito-plumecms/costruisci.py`

Il sito da cui si scarica plumeCMS, fatto con plumeCMS. Il costruttore scrive le pagine (home, funzionalità, temi,
documentazione, installazione, scarica, domande frequenti) come alberi dell'editor, lo stile, header, footer e menu, e
mette insieme i file da scaricare: i due zip del CMS, i due PDF, il tema Lavagna. Il risultato sono più pacchetti
per Impostazioni > Aggiornamenti, `releases/sito/sito-plumecms-<versione>-parte-<k>-di-<n>.zip`, da caricare in ordine:
la parte 1 porta pagine, stile, menu e immagini, le altre i file da scaricare.

Ordine, a ogni versione: `python3 tools/cms-pacchetto.py --aggiornamento`, `node tools/manuali-pdf.js`,
`python3 sito-plumecms/costruisci.py`. Dalla 1.2.5 le pagine non scrivono il numero di versione: usano
`{versione:plumecms-ultima}` e i link `/scarica/plumecms-ultima/`, quindi per una versione nuova del CMS basta caricare lo zip
nuovo da Download (il pacchetto del sito serve solo quando cambiano le pagine). Le immagini stanno in `sito-plumecms/immagini/` e si rifanno dalle schermate
con `--schermate <cartella>`. La foto della Copertina della home non è una schermata: è una piuma disegnata in SVG da
`node sito-plumecms/copertina.js <cartella>` (colori del sito, nessuna risorsa esterna), che scrive `copertina.png` nella
stessa cartella delle schermate; nella home esce a colori (trattamento naturale) con lo zoom lento, e il CSS del sito
alleggerisce il velo dove non ci sono parole e, al telefono, sposta il ritaglio sulla piuma. Anche le cinque schermate
della pagina Installazione ("Installalo in 5 minuti", carosello che scorre da solo e si ferma con `prefers-reduced-motion`)
hanno il loro script: `DBPASS=... node sito-plumecms/installatore.js <base> <cartella> <host:porta> <database> <utente>`,
su un plumeCMS appena estratto e mai installato con un database vuoto, installa davvero e fotografa ogni passo
(`installa-1.png` ... `installa-5.png`); nelle foto server, database, indirizzo e cartella diventano quelli d'esempio
di `tuosito.it`. I testi delle novità per chi scarica sono in `NOVITA`, dentro il costruttore.

Ogni parte resta sotto 1900 KB (`--max-kb` per cambiarlo), perché molti hosting non accettano caricamenti più grandi di 2 MB
(`upload_max_filesize` e `post_max_size`, mostrati nei Controlli della pagina Aggiornamenti): un pacchetto unico da 5 MB
è stato rifiutato dal primo server vero, il 27 ottobre 2026.

### 23.3 `tools/sicurezza-prova.js`

Le prove di attacco, con Playwright:

```
CMS_DIR=<cartella del CMS installato> node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]
```

Il prefisso del pannello predefinito è `/new`; per un CMS nella radice si passa `""`. Prova XSS, CSRF, ruoli,
pagine aperte senza login, CSV, open redirect, forza bruta, blocchi per nome utente e IP di casa, upload di PHP e
SVG, file protetti, SQL injection, intestazioni, session fixation, cron dal web, firma della newsletter, CSP del
pannello, pacchetti non firmati, copia del sito, aspetto del pannello e impostazioni delle foto, import da WordPress (SSRF, rimandi,
file PHP mascherati, HTML con script; serve `prove/finto-wp.php` con `FINTO_WP`) (1.4.0); il gruppo 21 la pagina Social (ruolo, token, Origin, contenuti inventati, titolo con HTML, immagine con `javascript:`) (1.5.0). Oggi: 177 prove respinte, due saltate (il test del burnout, del modulo danilofailli, e la newsletter firmata senza `SEGRETO`).

Dopo la prova l'IP di chi l'ha lanciata è bloccato: prima di altre prove si svuota la tabella
`<prefisso>sicurezza_tentativi`.

### 23.4 Le prove in `prove/`

Istruzioni in `prove/LEGGIMI.md`. Le principali:

| File | Cosa prova |
|---|---|
| `prova-install.js` | installazione da zero dal browser, in radice e in cartella, poi un giro da utente |
| `prova-temi.js` | il motore dei temi con i temi di collaudo (anche quelli rotti di proposito) |
| `prova-pannello-temi.js` | la pagina Temi |
| `prova-temi-partenza.js` | ogni tema di partenza: pagine, menu nell'header, contrasto del testo, nessun errore |
| `prova-carica-temi.js` | caricare e scaricare temi da zip, con e senza PHP, firmati e no |
| `prova-download.js` | download manager: caricamenti buoni e cattivi, widget, conteggio, eliminazione |
| `prova-sito-plumecms.js` | il pacchetto del sito di plumeCMS applicato dal pannello, poi ogni pagina e ogni file da scaricare |
| `prova-hero.js` | le undici disposizioni dell'Apertura al computer e al telefono: un solo h1, niente scorrimento orizzontale, foto caricate, contrasto di titolo e testo, pulsanti visibili (pagine create da `pagina-hero.php`) |
| `prova-foto-effetti.js` | sezione Foto di Apertura e Presentazione e interruttore dell'onda, dall'editor al sito |
| `prova-aggiornamento.js` | aggiornare dal pannello con il pacchetto del prodotto |
| `prova-kit.js` | (1.3.0) installazione con un kit di partenza, poi ogni pagina del menu al computer e al telefono, in radice e in cartella |
| `prova-sezioni.js` | (1.3.0) la linguetta Sezioni dell'editor: clic, trascinamento, modifica, pubblicazione; `/sezioni-pronte/` (`pagina-sezioni.php`) con il contrasto |
| `prova-copia.js` | (1.3.0) copia del sito: prepara, scarica, ripristina, e gli zip da respingere (`zip-copie-prova.py`) |
| `prova-importa-wp.js` | (1.4.0) import da WordPress contro un WordPress vero (`wp-installa.php`, `wp-riempi.php`, `router-wp.php`): errori, report, scelta, ripresa, anteprima protetta, Annulla, Conferma, confronto contenuto per contenuto, in radice e in cartella |
| `prova-versione.js` | (1.3.0) "c'è una versione nuova" con due installazioni e un sito ufficiale finto che si comporta male (`finto-ufficiale.php`) |
| `prova-aspetti.js` | (1.4.0) i quattro aspetti del pannello: scelta in Il mio accesso, ogni pagina del menu e l'editor in ognuno, contrasto di ogni testo, colori rimasti scritti a mano |
| `prova-foto-leggere.js` | (1.4.0) foto più leggere: foto grande girata con il GPS, PNG piccola, WebP sul sito e originale per chi non la accetta, lato massimo, spento, newsletter, redattore, cancellazione (`foto-prova.php`, `pagina-foto.php`) |
| `prova-tipo-sito.js` | (1.4.0) il tipo di sito in prova dalla dashboard: cambio con la conferma, copia, pagine tenute e tolte, sito da visitatore, attacchi, Definitivo (`tipo-sito-cli.php`) |
| `prova-primi-passi.js` | (1.4.0) i Primi passi: email di prova con un server di posta finto (`smtp-finto.py`), logo e colori, cron vero e pulsante, nascondi |
| `prova-cron.js` | (1.4.0) la pagina Log cron, Esegui adesso, il cron vero, gli attacchi, e nessuna pagina del pannello che parla di WordPress |
| `prova-controlla-server.js` | (1.4.0) Controlla il server: i sette gruppi, protezioni spente (`logs/controllo-aperto` per il router), email, attacchi |
| `prova-favicon.js` | (1.4.0) l'icona di partenza e quella dello Stile, in radice e in cartella |
| `prova-riquadri.js` | (1.4.0) Riquadri e Pulsante fisso: posizione e allineamento dall'editor al sito (`pagina-riquadri.php`) |
| `foto-sito.js`, `quanto-foto.py` | schermate prima e dopo, confrontate al pixel |
| `router-php-s.php` | router per `php -S` che imita l'`.htaccess` |
| `tema-cli.php` | attiva, disattiva, ripristina lo stile da riga di comando, per le prove |

Regola: una pagina si prova nel browser come un utente, non con una richiesta che risponde 200. Un errore fatale
può stare dentro una pagina che risponde 200.

---

## 24. Risoluzione dei problemi

| Cosa si vede | Perché | Cosa fare |
|---|---|---|
| Pagina bianca o errore 500 dopo un caricamento | spesso un `<?` scritto come testo in un file PHP: con `short_open_tag` acceso diventa codice | guardare `logs/php-errori.log`; nei file PHP `<?xml` si scrive `&lt;?xml` |
| Pannello o installatore senza grafica, CSS in 403 | file caricati con permessi 0600 | permessi 0644 ai file e 0755 alle cartelle; gli zip di `cms-pacchetto.py` li hanno già |
| Pannello senza grafica con un indirizzo come `/admin/index.php/` | gli assets si cercano dentro `index.php/` | dalla 1.1.5 il CMS riporta all'indirizzo pulito; aprire l'indirizzo senza il pezzo in più |
| Errore 1366 salvando un testo | carattere a 4 byte (emoji) in una colonna utf8 a 3 byte | scriverlo come entità HTML (`&#x1F600;`) |
| Un POST del pannello bloccato con 403 dal server | ModSecurity sull'HTML | i campi con HTML devono passare da `campi-protetti.js` (prefisso `~b64~`) |
| Il sito in una cartella rimanda alla radice anche dopo la correzione | il browser ricorda un vecchio 301 | svuotare la cache del browser |
| "Troppi tentativi" anche con la password giusta | blocco per IP | aspettare, oppure svuotare `<prefisso>sicurezza_tentativi` |
| Chiusi fuori per la 2FA o la lista IP | | `CMS_SICUREZZA_SPEGNI` (18.14) |
| Il sito è tornato all'aspetto di base da solo | il PHP del tema ha dato un errore | Editor > Temi mostra l'errore; correggere e "Riprova ad attivarlo" |
| Un pacchetto fallisce e il pannello dice ripristinato | un passo del manifest è andato male, lo snapshot ha rimesso tutto com'era | leggere il resoconto; spesso SQL con un apostrofo in un commento |
| La newsletter non parte | il cron non gira | Impostazioni > Log cron; controllare la riga del cron sul server |
| Lo stile non cambia dopo aver attivato un tema | lo Stile del sito vince sul tema | è voluto: il tema imposta lo Stile del sito all'attivazione; le modifiche successive allo stile restano |
| 404 su `/favicon.ico` in un sito appena installato | non c'è ancora una favicon di partenza | scegliere l'icona in Stile del sito > Sito (il CMS la indica con `<link rel="icon">`); il file `/favicon.ico` nella radice, che alcuni browser cercano lo stesso, si carica a mano |
| Una pagina del pannello risponde 200 ma non funziona | errore fatale dentro la pagina | guardare il log e provare nel browser |

---

## 25. Riferimento rapido delle funzioni

**Base** (`includes/cms.php`, `includes/db.php`)

| Funzione | Cosa fa |
|---|---|
| `cms_radice()` | cartella del CMS sul disco |
| `cms_versione()` | contenuto di `VERSIONE` |
| `cms_installato()` | c'è `config.local.php` |
| `cms_base()` | cartella del CMS nell'indirizzo |
| `cms_sito_base()` | cartella del sito nell'indirizzo |
| `cms_url_sito()` | indirizzo del sito, senza barra finale |
| `cms_url_pannello()` | indirizzo del pannello, con la barra finale |
| `cms_con_cartella($html)` | aggiunge la cartella ai link che cominciano con `/` |
| `cms_impostazione($k, $pred)` | impostazione dal database, dal modulo o predefinita |
| `cms_uploads_dir()`, `cms_uploads_url()` | cartella e indirizzo delle immagini |
| `cms_moduli()`, `cms_modulo($n)`, `cms_modulo_dir($n)`, `cms_modulo_url($n)` | moduli |
| `cms_filtro($nome, $v, ...)`, `cms_evento($nome, ...)` | filtri ed eventi |
| `cms_moduli_elenco($chiave)` | le voci di una chiave del manifest di tutti i moduli |
| `db()` | connessione PDO |
| `tbl($nome)` | nome della tabella con il prefisso |
| `get_setting($k, $pred)`, `save_setting($k, $v)` | impostazioni |
| `is_admin_preview()` | amministratore in anteprima |

**Tipo di sito, primi passi, cron, server** (1.4.0: `includes/kit-partenza.php`, `primi-passi.php`, `cron.php`, `controllo-server.php`)

| Funzione | Cosa fa |
|---|---|
| `kit_costruisci($kit, $sito, $descr, $autore, $opz)` | costruisce form, pagine, articoli e menu di un kit; ritorna log, registro e pagine saltate |
| `kit_prova()`, `kit_in_prova()` | il registro del tipo di sito; vero finché non c'è il Definitivo |
| `kit_impronta($voce)` | l'impronta di adesso di una cosa creata dal kit (null se non c'è più) |
| `kit_cambia($kit, $autore)`, `kit_definitivo($autore)` | cambia tipo di sito (con la copia prima); Definitivo |
| `kit_contenuto_scritto()` | la persona ha scritto o cambiato una pagina o un articolo |
| `primi_passi()`, `primi_passi_nascondi()` | le sei voci dei Primi passi; nascondi solo se tutte fatte |
| `cron_giornaliero($da)`, `cron_newsletter($da)` | i due giri del cron (`cron` o `pannello`) |
| `cron_stato()`, `cron_attivo()`, `cron_righe()` | ultime passate; il cron vero gira; le due righe da copiare |
| `server_controlli()`, `server_email_prova($a)` | Controlla il server; l'email di prova |

**Pannello** (`admin/includes/auth.php`)

| Funzione | Cosa fa |
|---|---|
| `require_login()`, `require_admin()` | protezione della pagina |
| `current_user()`, `is_admin()` | utente collegato |
| `csrf_token()`, `csrf_field()`, `csrf_ok()`, `verify_csrf()` | CSRF |
| `nonce_attr()` | attributo nonce per uno script in linea |

**Contenuti ed editor**

| Funzione | Cosa fa |
|---|---|
| `contenuti_tipi()`, `contenuti_tipo($t)` | tipi di contenuto |
| `contenuti_stato($riga)` | stato di un contenuto |
| `contenuti_salva($tipo, $id, $dati, $autore)` | salvataggio completo |
| `contenuti_cestina`, `contenuti_ripristina`, `contenuti_elimina`, `contenuti_duplica` | cestino e copie |
| `editor_valida($albero)` | valida e pulisce un albero |
| `editor_disegna($albero, $percorso, $anteprima)` | HTML e CSS |
| `editor_salva(...)` | bozza o pubblicazione |
| `editor_registra_widget($tipo, $def, $disegna)`, `editor_registra_asset($tipo, $asset)` | widget nuovi |
| `ec(...)` | definizione di un controllo |
| `design_valori()`, `design_salva()`, `design_css()`, `design_head()` | Stile del sito |
| `modelli_scegli()` | header e footer della pagina |
| `menu_url_voce($voce)` | indirizzo di una voce di menu |
| `editor_versione_ripristina(...)` | ripristino di una versione |

**Temi** (`includes/temi.php`)

| Funzione | Cosa fa |
|---|---|
| `temi_attivo()` | il tema in uso (vuoto se nessuno o guasto) |
| `temi_attiva($slug)`, `temi_disattiva()` | attivare e spegnere |
| `temi_avvia_sito()` | carica il tema sul sito |
| `temi_guasto($t, $errore)` | segna il tema come guasto |
| `temi_proteggi()` | scrive `temi/.htaccess` |

**Sicurezza**

| Funzione | Cosa fa |
|---|---|
| `sicurezza_cfg()` | impostazioni della sicurezza |
| `sicurezza_html_pulito()`, `sicurezza_css_pulisci()`, `sicurezza_svg_pulito()`, `sicurezza_url_pulito()` | sanificatori |
| `sicurezza_nonce_script()` | nonce agli script dell'HTML |
| `sicurezza_password_errore()` | perché una password non va bene (o vuoto) |
| `sicurezza_limite($chiave, $max, $secondi)` | limite generico per le azioni pubbliche |
| `sicurezza_log(...)` | scrive un evento nel registro |

**Media e posta**

| Funzione | Cosa fa |
|---|---|
| `media_carica($file, $cartella, $maxMb)` | caricamento di un'immagine |
| `scaricabili_elenco()`, `scaricabili_carica($file, $info, $sostituisci)`, `scaricabili_salva_info()`, `scaricabili_elimina()` | download manager |
| `media_elimina($url)` | cancellazione |
| `posta_invia(...)` | invio di un'email (SMTP o `mail()`) |


**Novità della 1.3.0** (`includes/sezioni-pronte.php`, `includes/kit-partenza.php`, `includes/versione-nuova.php`, `includes/copia-sito.php`)

| Funzione | Cosa fa |
|---|---|
| `sezioni_pronte()`, `sezioni_pronte_elementi($id)`, `sezioni_pronte_per_editor()` | le sezioni pronte: dati, una variante validata, l'elenco per l'editor |
| `sezioni_pronte_costruisci($sezioni)` | forma corta -> sezioni dell'editor |
| `kit_elenco()`, `kit_leggi($id)`, `kit_sezioni($blocchi, $mappa)`, `kit_immagini()`, `kit_mappa($sito, $descr)` | i kit di partenza |
| `versione_controlla($forza)`, `versione_nuova()`, `versione_scarica_e_prepara()` | il controllo della versione e "Scarica e prepara" |
| `copia_crea($etichetta)`, `copia_ripristina($file)`, `copia_elenco()`, `copia_leggi_manifest($zip)` | la copia del sito |
| `agg_dump_db($file, $prefisso)` | il database in un file; con il prefisso, solo le tabelle del sito |
| `scaricabili_sha256($file)` | l'impronta di un file da scaricare, ricordata |

---

## 26. Import da WordPress (dalla 1.4.0)

Porta in plumeCMS un sito WordPress: articoli, pagine, categorie, tag, media, menu, SEO, con i redirect dai vecchi
indirizzi. Pagina **Impostazioni > Importa da WordPress** (`admin/importa-wordpress.php`, solo amministratore). Il motore sta
in quattro file:

| File | Cosa fa |
|---|---|
| `includes/importa-wp.php` | chiamate in uscita (SSRF), credenziali cifrate, collegamento con i messaggi d'errore, stato del lavoro, tabella `importa_wp_voci` |
| `includes/importa-wp-analisi.php` | l'analisi a passi, l'indice del sito, il report, SEO di Yoast e Rank Math |
| `includes/importa-wp-converti.php` | il testo di WordPress in HTML semplice e sicuro |
| `includes/importa-wp-lavoro.php` | l'import a giri, i media, i menu, Conferma e Annulla |

Il sito WordPress non si tocca: si legge e basta, con la REST (`wp-json/wp/v2`; per i siti senza permalink belli
`?rest_route=/`).

### 26.1 Il percorso

1. **Collegamento** (`importa_wp_collega()`): indirizzo, utente e **password per le applicazioni** di WordPress (Utenti >
   Profilo > Password per le applicazioni, da WordPress 5.6; la pagina lo spiega in tre passi). Si accetta come WordPress la
   mostra, con gli spazi. Il collegamento controlla: indirizzo (SSRF, 26.4), REST aperta (`/wp-json/` o `?rest_route=/`, con
   `wp/v2`), credenziali (`users/me?context=edit`), permessi (amministratore, o almeno `edit_others_posts`). Ogni errore
   diventa una frase: sito che non esiste, che non risponde, certificato non valido, rimando a un altro host, rete interna,
   porta non del web, sito in http (credenziali solo in HTTPS), REST chiusa da un plugin (403, `rest_disabled` e simili),
   password per le applicazioni spente (manca `authentication.application-passwords` nell'indice), password normale al posto
   di quella per le applicazioni (non ha la forma di 24 lettere e cifre), utente sconosciuto, credenziali non accettate (con il
   caso del server che toglie l'intestazione `Authorization`), utente senza permessi.
2. **Analisi** (`importa_wp_analisi_passo()`): a passi (categorie, tag, utenti, pagine, articoli, media, menu, il resto),
   una richiesta per passo, articoli e pagine a 50 per volta con `context=edit` e tutti gli stati (pubblicati, programmati,
   bozze, in attesa di revisione, privati). Si tiene un indice senza i testi: id, slug (`slug` o `generated_slug`, decodificato,
   senza caratteri a 4 byte), titolo, stato, date, percorso del vecchio indirizzo, categorie, tag, immagine in evidenza, note
   (page builder riconosciuti dal testo: Elementor, Divi, WPBakery, Beaver Builder, Avada, Brizy, Oxygen, SiteOrigin;
   shortcode di plugin; embed; immagini su altri host; moduli o script; campi personalizzati), SEO.
3. **Report** (`importa_wp_report()`): per ogni voce i numeri, cosa diventa, e l'elenco di cosa non si porta (commenti, tipi
   personalizzati come i prodotti di WooCommerce, page builder, shortcode, campi personalizzati, file di tipo non ammesso,
   file su un altro host, pagina iniziale, aspetto e plugin, utenti). I contenuti con uno slug già usato in plumeCMS
   (articolo o pagina, anche in bozza o nel cestino, o un indirizzo del sito come `/blog/`) sono elencati: si saltano.
   Anche la struttura dei permalink, ricavata dall'indirizzo di un articolo (`importa_wp_permalink()`).
4. **Scelta**: voci (articoli, pagine, categorie, tag, media, menu, SEO, redirect) e, per gli articoli, gli stati.
   Scorciatoie Tutto, Solo gli articoli, Solo le pagine, Niente.
5. **Import a giri** (`importa_wp_import_giro()`): piano `copia, categorie, tag, media, pagine, articoli, menu, fine`.
   Il primo passo è una copia del sito (`copia_crea('prima-di-importa-wordpress')`, 19.7). Il browser chiede un giro dopo
   l'altro (`azione=giro`, JSON, intestazione `X-CSRF`); ogni giro lavora `IMPORTA_WP_GIRO` secondi (8) e salva il cursore
   nello stato. Un giro alla volta (`GET_LOCK`), la sessione si chiude subito (gli altri pannelli non aspettano), e con
   `ignore_user_abort` un giro iniziato finisce anche se il browser si chiude. Riaprendo la pagina si legge "L'import si è
   fermato qui" e **Riprendi** continua. Ogni voce entrata sta nella tabella: un giro ripetuto non fa doppioni. Una richiesta
   che fallisce si riprova; dopo 3 volte quel lotto si salta, con il motivo.
6. **In prova** (fase `prova`): tutto è invisibile ai visitatori.
   - articoli e pagine sono **bozze**; lo stato di WordPress sta in `importa_wp_voci.stato_finale` (`pubblicato`, `programmato`, `bozza`);
   - categorie e tag nuovi sono nascosti: l'impostazione `importa_wp_nascosti` li toglie da `sito_categorie()` e
     `sito_tag()` (`includes/sito-dati.php`, `sito_nascosti_import()`), tranne nell'anteprima;
   - i redirect hanno `attivo = 0` e `origine = 'wordpress'`; i menu non hanno posizione;
   - i file sono già in `uploads/`, con sei lettere a caso nel nome.
   L'amministratore guarda tutto con l'anteprima del sito (`?preview=1` e la sua sessione, `SITO_ANTEPRIMA`): il riepilogo
   ha il link per ogni contenuto, la tabella di quello che non è entrato con il motivo, e "Da guardare" con quello che
   manca nei contenuti entrati (shortcode tolti, page builder, immagini non scaricate).
7. **Conferma** (`importa_wp_conferma()`): `status = published` per i pubblicati e i programmati (un programmato resta
   programmato: la data è nel futuro), le bozze restano bozze; redirect accesi; `importa_wp_nascosti` svuotata; IndexNow per
   i pubblicati. Con la spunta "Metti nel menu principale...", le voci del menu importato vanno nel menu in posizione
   `principale` (quello dell'header, che il modello indica per id o per posizione); il menu di prima resta in Versioni.
   **Annulla** (`importa_wp_annulla()`): toglie, voce per voce, contenuti (e le loro revisioni), categorie e tag **nuovi**
   (quelli che c'erano già restano), file, menu, redirect. Il resto del sito non si tocca.

### 26.2 Cosa diventa cosa

| WordPress | plumeCMS |
|---|---|
| articolo, pagina | articolo, pagina con `contenuti_salva()` (8.4): titolo, slug, data e ora (da `date_gmt` nel fuso del CMS), autore (nome), estratto scritto a mano, immagine in evidenza, formato `html` |
| testo (blocchi di Gutenberg) | l'HTML che WordPress disegna (`content.rendered`), passato da `importa_wp_html()`: embed di YouTube e Vimeo in un iframe costruito dal CMS (`youtube-nocookie`, id controllato), gli altri embed diventano link; immagini scaricate e riscritte (via `srcset` e `sizes`; con la classe `wp-image-N` si prende l'originale); link interni verso gli indirizzi nuovi (mappa `vecchio percorso -> nuovo`); file di `wp-content/uploads` linkati (un PDF) scaricati; shortcode non eseguiti tolti; poi `sicurezza_html_pulito(..., 'articolo')` e i caratteri a 4 byte in entità |
| pagina figlia (`/chi-siamo/il-team/`) | pagina `/il-team/` con un redirect |
| stato | `publish` pubblicato, `future` programmato, `draft`, `pending`, `private` bozza |
| categorie (anche annidate), tag | categorie e tag (stesso slug); uno slug che c'è già si usa com'è |
| media | file in `uploads/<anno>/<mese>/` (l'anno e il mese di WordPress) |
| menu classici (`wp/v2/menus`, `menu-items`, da WP 5.9) e menu dei temi a blocchi (`wp/v2/navigation`) | menu di navigazione, voci di tipo pagina, articolo, categoria o link, al massimo tre livelli |
| SEO | `seo_title` e `seo_description`: dai meta `_yoast_wpseo_*` o `rank_math_*` se la REST li mostra, altrimenti da `yoast_head_json`; variabili `%%...%%` risolte o tolte, il nome del sito in coda tolto, un titolo uguale a quello del contenuto lasciato vuoto (`importa_wp_seo()`) |
| vecchi indirizzi | redirect 301 per i contenuti pubblicati: il permalink se diverso da `/<slug>/`, `/?p=ID`, per le pagine `/?page_id=ID`; categorie e tag con un percorso diverso. Un redirect con lo stesso `da` che c'è già non si tocca; un percorso con un carattere a 4 byte si salta (colonna utf8) |
| conflitti | un contenuto con uno slug già usato non entra (sicurezza: niente si sovrascrive); link e voci di menu verso di lui portano a quello che c'è in plumeCMS con lo stesso indirizzo |

Non entrano, e il report lo dice: commenti, utenti e password, tipi di contenuto personalizzati, campi personalizzati,
aspetto del tema e widget, plugin, moduli e script nei testi, grafica dei page builder (entra il testo), file che non sono
immagini (JPEG, PNG, GIF, WebP, AVIF, SVG), PDF, MP4 o MP3.

### 26.3 Stato e credenziali

- Stato: l'impostazione `importa_wp_stato` (JSON in ASCII, anche le emoji come `\uXXXX`): fase (`analisi`, `pronto`,
  `importa`, `prova`, `confermato`, `annullato`), base, utente, indice, report, scelta, piano, cursore, mappa, esito.
- La password per le applicazioni sta nello stato solo cifrata (`importa_wp_cifra()`: sodium secretbox, chiave
  `sha256('importa-wp|' . CMS_SEGRETO)`; senza `CMS_SEGRETO`, una chiave casuale nella sessione). Si cancella alla fine
  dell'import, con Annulla, con Conferma, e dopo un giorno senza lavoro (`importa_wp_scadenza()`). Se manca quando serve, la
  pagina chiede di riscriverla. Non compare mai nelle pagine (il campo torna vuoto dopo un errore), nei registri né nei log.
- Registro della sicurezza: evento `importa_wp` (collegato, in prova, confermato, annullato), senza credenziali.

### 26.4 Sicurezza

- Solo amministratore (`require_admin()`, e il redattore riceve 403 sui POST); ogni azione in POST con il token CSRF, i giri
  con `X-CSRF`; `Origin` estranea rifiutata.
- Chiamate in uscita (`importa_wp_get()`): solo `http` e `https`, niente credenziali nell'indirizzo, solo le porte 80 e 443;
  il nome si risolve una volta, ogni IP si controlla (`importa_wp_ip_interno()`: privati, locali, riservati, link-local
  come 169.254.169.254, 100.64.0.0/10) e la connessione si fissa su quell'IP (`CURLOPT_RESOLVE`); nessun proxy; certificato
  verificato; nessun rimando seguito verso un altro host (al massimo 3 sullo stesso host, e da http a https sì, mai il
  contrario); 20 secondi e 8 MB per una risposta JSON, 60 secondi e 25 MB per un file. Le credenziali partono solo in HTTPS.
- `CMS_IMPORTA_WP_LOCALE` (solo prove): gli `host:porta` elencati possono essere interni e in http. Nessun altro.
- File (`importa_wp_media()`): solo dallo stesso sito; il tipo si legge dal contenuto (`getimagesize`, `%PDF-`, `ftyp`, `ID3`,
  `<svg`), mai dal nome; le immagini si riscrivono con GD (via metadati e codice in coda); una GIF che GD non riscrive entra
  solo senza `<?php`, `<?=` o `<script`; PDF, MP4 e MP3 con codice dentro non entrano; l'SVG passa da
  `sicurezza_svg_pulito()`; l'estensione la decide il CMS. Un file PHP chiamato `foto.jpg` non entra.
- Testi: `sicurezza_html_pulito()` (18.10) su tutto; titoli, nomi e descrizioni come testo (`importa_wp_testo()`).

### 26.5 Limiti noti

- Provato con WordPress 6.6.2 sul server integrato di PHP, non su un hosting vero, né con Elementor, WooCommerce o un Yoast
  vero installato (la prova imita `yoast_head_json`).
- Le immagini dentro gli shortcode di un plugin e i fondi dei page builder non si scaricano.
- Le gallerie restano figure una sotto l'altra: il CSS del sito non ha la griglia di Gutenberg.
- Un link a un contenuto non importato (per esempio una bozza esclusa) resta verso il vecchio sito.
- Una copia del sito grande, il primo passo, può superare il tempo di un giro: su un hosting condiviso con limiti stretti
  conviene fare prima la copia da Impostazioni > Copia del sito.

---

## 27. Social e email del mattino (dalla 1.5.0)

Prima stavano nel modulo di danilofailli.it (`includes/notifications.php`, `includes/social-pillar.php`,
`admin/social-copy.php` del modulo), con indirizzi, hashtag e frasi di quel sito scritti nel codice. Dalla 1.5.0 sono del
CMS, per qualunque sito: `includes/social.php` (motore), `admin/social.php` (pagina Social), il riquadro Social della
scheda (`admin/contenuto.php`), un passo del cron del mattino (`includes/cron.php`).

### 27.1 I testi

- **Per contenuto**: tabella `social_contenuti`, chiave (`tipo`, `contenuto_id`). Un testo per tutti e tre facoltativi
  (LinkedIn, Facebook e Instagram, WhatsApp e Telegram). La scheda li salva con `social_contenuto_salva()` dopo
  `contenuti_salva()`, solo se il modulo manda `social_testo`: un salvataggio rapido dall'elenco non li tocca. Senza
  testo vale il primo non vuoto fra estratto, In breve, descrizione SEO, meta descrizione, titolo.
- **Costruzione** (`social_testi($tipo, $riga, $sopra, $ancora)`): per ogni canale acceso testo + link completo
  (`cms_url_sito()` + percorso, con l'ancora) + hashtag. Hashtag = quelli fissi del canale + quelli della categoria
  (`social_hashtag_categoria()`: righe "categoria: #a #b", per nome o slug). WhatsApp: in coda il link in più con la sua
  frase. Substack: testo + "Puoi leggere tutto qui". Poi il filtro dei moduli `social_testi` (testi, riga, tipo).
- **Emoji**: le colonne sono a 3 byte, quindi i caratteri a 4 byte si salvano come entità (`social_senza_4byte()`) e
  tornano caratteri con `social_testo_vero()` (`html_entity_decode`) quando si mostrano o si copiano.
- **Impostazioni**: `settings.social` in JSON (`social_impostazioni()`): canali (Substack spento di partenza), hashtag
  fissi per LinkedIn, Facebook, Instagram, hashtag per categoria, link e frase di WhatsApp (solo `https://`, `http://` o
  `/`), le due email accese o spente.
- **Colori dei canali** (`SOCIAL_COLORI`): un po' più scuri di quelli dei marchi dove serviva, perché ognuno passi 4,5 a 1
  sul bianco e con il testo bianco sopra (Facebook `#1465D8`, WhatsApp `#0E7A6E`, Telegram `#177BB0`, Substack
  `#B8461A`). Valgono nelle email; nel pannello il colore è solo un pallino accanto al nome del canale, che usa il colore
  del testo dell'aspetto (sui fondi scuri di Cosmo e Lavagna il blu non si leggeva). L'anteprima dell'email ha sfondi
  fissi: mostra l'email com'è nella posta.

### 27.2 Le immagini

`social_immagini_da($url, $nome)`: se l'immagine sta in `uploads/` (`social_file_di()`, niente `..`, solo dentro
`cms_uploads_url()`), GD la ritaglia riempiendo e tagliando al centro (`social_ritaglia()`) a 1200x630 (`SOCIAL_OG`) e
1080x1350 (`SOCIAL_IG`), JPEG 86, in `uploads/social/<tipo>-<id>-<impronta>-og.jpg` e `-ig.jpg`. L'impronta (percorso,
data e peso del file) cambia con la foto: si rifanno da sole, una volta. Un'immagine da fuori, o senza GD, si usa così
com'è. Nelle email gli indirizzi sono completi (`social_assoluto()`); nel pannello tornano relativi (`social_relativo()`),
così si vedono anche se il pannello si apre da un altro indirizzo.

### 27.3 Le due email

All'indirizzo degli avvisi (`posta_notifica_email()`), con `posta_invia()` (SMTP del pannello o `mail()`):

- **"Oggi sul sito <dominio>: N articoli"** (`social_email_mattino($data)`): i contenuti non pagine con `publish_date`
  quel giorno, non in bozza né nel cestino, anche se escono più tardi (`social_in_uscita()`); per ognuno titolo, link,
  le due immagini con i pulsanti per scaricarle, un riquadro per canale, i pulsanti per condividere (LinkedIn
  share-offsite, Facebook sharer, wa.me, t.me). "articolo/articoli" dal tipo, "contenuti" con tipi diversi. Una volta al
  giorno: `settings.social_mattino_inviata` tiene la data.
- **"Social di oggi: <titolo>"** (`social_email_programmato($riga)`): una per ogni riga di `social_programmati` con
  `data_invio` fino a oggi e `inviato_at` vuoto (al massimo 30 per giro); dopo l'invio `inviato_at = NOW()`. Testi della
  riga, o del contenuto se vuoti; immagini della riga, o del contenuto. Una riga il cui contenuto non c'è più si segna e
  si salta.

Tutto quello che arriva da chi scrive passa da `htmlspecialchars` nell'email e nel pannello. `social_cron()` ritorna le
righe che finiscono in Log cron ("già inviata oggi", "niente nel calendario", gli errori della posta).

### 27.4 La pagina Social

`admin/social.php`, solo amministratore (`require_admin()`), ogni POST con `verify_csrf()`:
- **Email del mattino**: anteprima del giorno scelto e "Mandala adesso" (non segna la data: il giro del mattino parte lo stesso);
- **Calendario**: righe degli ultimi 30 giorni in avanti; nuovo, modifica, "Mandalo adesso a me", "Rimetti in attesa",
  elimina. `social_programmato_salva()` vuole una data vera, un contenuto che esiste (`tipo:id` di un tipo noto, l'id fatto solo di cifre: `articoli:1 OR 1=1` è rifiutato),
  immagini che cominciano con `/` o `https://`; l'ancora tiene solo lettere, cifre, `-` e `_`;
- **Testi dei contenuti**: i 15 contenuti da leggere più recenti, i testi in `textarea readonly data-copia-se-stesso`
  (un clic li copia, `assets/pannello.js`), le immagini, i link per condividere;
- **Impostazioni**: 27.1.

### 27.5 I dati di prima

Alla nascita delle tabelle, una volta (`settings.social_dati_vecchi`), `social_porta_dati_vecchi()` porta dentro i dati
del pannello da cui è nato plumeCMS, se ci sono: la colonna `articles.social_summary` diventa il testo per i social
(`INSERT IGNORE`), la tabella `social_pillar` diventa il calendario (slug dell'articolo, "Giorno N di 7: capitolo",
ancora, i tre testi, le immagini relative a `uploads`, lo stato di invio), solo se il calendario è vuoto. La prova:
`prove/social-dati-vecchi.sql`. Su danilofailli.it, dopo l'aggiornamento, i passi `cron` "email del mattino" e "social
del pillar" del modulo vanno tolti, se no le email arrivano due volte.

## Appendice A. Dove i documenti vecchi non corrispondono al codice

Alcuni documenti di `docs/` sono nati quando il CMS era il pannello di un solo sito, e in qualche punto sono
rimasti indietro. In questi casi vale il codice, ed è quello che dice questo manuale.

| Documento | Cosa dice | Cosa fa il codice |
|---|---|---|
| `editor-pagine.md`, `contenuti.md`, `header-footer-menu.md` | revisioni dell'editor in `pagine_revisioni`, ultime 30 | tabella `editor_versioni`, 50 per oggetto, da 5 a 500 (capitolo 13) |
| `editor-pagine.md` | il widget HTML passa com'è | per chi non è amministratore diventa testo |
| `editor-pagine.md` | colori solo esadecimali o rgb | anche hsl, `transparent`, `globale:`, `ig:` |
| `editor-pagine.md` | widget nuovi con un `case` in `editor_disegna_widget()` | `editor_registra_widget()` (9.8) |
| `editor-pagine.md` | caricamenti in `wp-content/uploads/pagine`, JPG PNG GIF WebP | `uploads/pagine/AAAA/MM/`, anche AVIF e SVG ripulito |
| `editor-pagine.md` | cita `includes/editor-articoli.php` | il file non c'è nel nucleo |
| `stile-sito.md`, `header-footer-menu.md` | voci sotto "Pagine" | sono nel gruppo Editor |
| `stile-sito.md` | il CSS personalizzato passa intero | passa da `sicurezza_css_pulisci`, fino a 60000 caratteri |
| `newsletter.md` | aperture e clic non si contano | si contano (15.5) |
| `seo.md`, `header-footer-menu.md` | citano file SQL in `sql/` | le tabelle si creano dal codice, `sql/` non c'è |
| `aggiornamenti.md` | sincronizzazione WordPress, chiave `sincronizza`, redirect di Rank Math | il motore non li legge; i redirect vanno nella tabella `redirect` |
| `cms.md` | `index.php` serve il sito solo nella radice | lo serve anche in una cartella (caso b del capitolo 5), con un 302 negli altri casi |
| `cms.md` | `CMS_BASE` solo se il rilevamento sbaglia | l'installatore lo scrive sempre quando l'indirizzo ha una cartella |
| `cms.md` | cita la cartella `share/` | non c'è nel prodotto |
| `pannello.md` | voci Aforismi, Test, Social copy | compaiono solo con il modulo del primo sito |

Piccole incoerenze interne del codice, innocue, da sistemare:
- il commento di `admin/includes/admin-header.php` dice che il redattore vede il Log cron, ma `cron-log.php` è solo
  per amministratori: per il redattore il gruppo Impostazioni non c'è;
- il commento della chiave `rotte` del manifest in `includes/cms.php`: nessun codice la legge;
- `tools/cms-pacchetto.py` dice di rifiutare i caratteri a 4 byte, ma dà solo un avviso;
- il commento di `includes/aggiornamenti.php` parla del rapporto di compressione: il codice controlla solo le
  dimensioni;
- l'evento `upload_rifiutato` del registro esiste ma nessuno lo scrive.
