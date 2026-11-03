# plumeCMS: manuale tecnico

Versione del manuale: 1.21, per plumeCMS 2.0.3 e le novità della 2.1.0 (31 ottobre 2026).

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
27. Social e email del mattino (dalla 1.5.0)
28. Aggiungi sempre alla fine: il riquadro in fondo agli articoli (dalla 1.6.0)
29. Il sito su misura (dalla 1.7.0)
30. La frase in fondo al sito e il banner del sito ufficiale (dalla 1.8.0)
31. I widget per le attività (dalla 1.10.0)
32. Gli addon (dalla 2.0.0)
33. File riservati e il file dato dai form (dalla 2.0.0)
34. Scegli dai Media, immagine social e pannello al telefono (dalla 2.0.0; Scegli dai Media dappertutto dalla 2.1.0)
35. Consegna delle email: dominio, servizi, mittenti, rimbalzi, riscaldamento (dalla 2.1.0)
36. Copia altrove su Google Drive e Dropbox, riservati fuori dagli elenchi, ripristino pulito (dalla 2.1.0)
37. I widget della 2.1: menu, richiesta di prenotazione, galleria con filtri, scorrevole, contenuto esterno (dalla 2.1.0)
41. Le quindici infografiche disegnate (novembre 2026)

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
VERSIONE            il numero di versione (oggi 1.10.0)
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
  modelli.php       header, footer e il riquadro in fondo agli articoli (28)
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
temi/               i temi del sito (di partenza: Cosmo, Aria, Bottega, Estate, Inverno, Autunno, Primavera, Giallo, Rosso, Verde, Blu)
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
| `CMS_AGGIORNAMENTI_URL` | solo per le prove: il sito a cui chiedere l'ultima versione al posto di quello ufficiale (19.6) | `VERSIONE_SITO_UFFICIALE` |
| `CMS_AGGIORNAMENTI_HTTP_PROVA` | solo per le prove: con `true` il controllo della versione accetta anche `http://` | spenta (solo HTTPS) |

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
| `modelli` | header, footer e il riquadro in fondo agli articoli (tipo `fine`, 28): struttura, stato, predefinito, condizioni, priorità |
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
| `newsletter_eventi`, `newsletter_flussi`, `newsletter_corsi` | aperture e clic, automazioni (flussi), persone dentro un'automazione (dalla 2.15.0 con il giro) |
| `newsletter_flussi_registro`, `newsletter_flussi_numeri` | registro delle automazioni (90 giorni) e contatori per passo (2.15.0, capitolo 15.14) |

**Download**: `scaricabili` (titolo, descrizione, versione, conteggio dei file da scaricare; dalla 1.2.4).

**SEO**: `seo_coda` (coda IndexNow), `seo_log`, `seo_url` (ispezioni di Search Console), `seo_404`.

**Sicurezza**: `sicurezza_log` (registro), `sicurezza_tentativi` (blocchi), `sicurezza_sessioni`,
`sicurezza_token` (token monouso), `sicurezza_csp` (rapporti CSP).

**Viste SQL**: `v_published_articles` (articoli pubblicati, fuori dal cestino, con data non futura) e
`v_scheduled_articles` (articoli programmati).

Le tabelle di base, della sicurezza e `editor_versioni` sono in `utf8mb4`; molte altre sono in `utf8` a 3 byte.
Per questo i caratteri a 4 byte (emoji) nei dati si salvano come entità HTML o come `\uXXXX` nel JSON: in
modalità stretta MySQL rifiuterebbe la riga (errore 1366, o 3988 con la connessione in utf8mb4). Un testo semplice (non
HTML) salvato come entità va rimesso emoji quando si legge, prima di `h()`, se no sul sito e nel pannello esce scritto
`&#x1F377;`: lo fa `sito_4byte_veri()` (`includes/sito.php:178`, il contrario di `sito_senza_4byte()`), oppure
`newsletter_4byte_fuori()` per la newsletter (audit 2026-11, capitolo 39).

La tabella `form_submissions`, usata da una vecchia funzione di `db.php`, nel nucleo non si crea: la porta un modulo che
ne ha bisogno. La funzione che la scrive non è chiamata dal nucleo.

---

## 7. Il pannello e i ruoli

### 7.1 Ruoli

Due ruoli (`admin/utenti.php:12`):
- **Amministratore** (`admin`): tutto.
- **Redattore** (`editor`): solo contenuti, media, categorie e tag.

Il redattore può aprire solo queste pagine (`AUTH_REDATTORE_PAGINE`, `admin/includes/auth.php:81-82`):
`index`, `contenuti`, `contenuto`, `editor`, `editor-api`, `editor-anteprima`, `seo-api`, `link-api` (2.2, Scegli dal sito:
capitolo 38), `media`,
`categories`, `versioni`, `account`, `password`, `logout`, `login`. L'editor con `tipo=modelli` (header,
footer e il riquadro in fondo agli articoli) è sempre negato. Un modulo aggiunge pagine con il filtro `redattore_pagine`.

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
| Articoli > Aggiungi sempre alla fine | `fine-articolo.php`: il riquadro in fondo a ogni articolo (28) | admin |
| Pagine (e "Home nell'editor") | `contenuti.php?tipo=pagine`, `editor.php?id=home` | tutti |
| Form | `form.php` | admin |
| Popup | `popup.php` | admin |
| Categorie, Tag | `categories.php`, `categories.php?tipo=tag` | tutti |
| Media | `media.php` | tutti |
| Download | `scaricabili.php`: i file da scaricare (14.1) | admin |
| Utenti | `utenti.php` | admin |
| Newsletter e form | `newsletter.php` (Campagne, Iscritti e liste), `form-submissions.php`, `newsletter-flussi.php` (Automazioni, 15.14), `newsletter-statistiche.php` | admin |
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
| Impostazioni | `impostazioni.php` (email e SMTP), `sicurezza.php`, `aggiornamenti.php`, `copia-sito.php` (19.7), `cron-log.php` (20), `controlla-server.php` (7.4) | admin |

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
  Dall'UX di novembre 2026 (`docs-sviluppo/ux-ui-2026-11-pannello.md`): `--testata`/`--testata-testo` (la fascia del
  titolo dei riquadri), `--bordo-campo` (bordo di campi, tendine, scelta file e pulsanti secondari, almeno 3:1 sul riquadro
  e sul fondo) e `--segnaposto` (testo d'esempio dei campi, almeno 4,5:1).
  **Attenzione**: in Aria `--sidebar` è bianca. Il testo sopra la barra laterale usa `--sidebar-testo`, quello sopra
  un pulsante pieno `--su-accento`: mai `#fff` scritto a mano.
- **Riquadri e campi** (UX 2026-11, in fondo a `admin/assets/css/admin.css`): il primo `h2`/`h3` di un riquadro
  (`.form-card`, `.seo-card`, `.agg-card`... anche dopo i campi nascosti di un `<form class="form-card">`) diventa una
  fascia a filo dei bordi; ogni classe di riquadro dichiara il suo margine interno in `--riq-y`/`--riq-x`. Una pagina nuova
  usa `.form-card` con il titolo come primo figlio e ha la fascia senza fare niente. I campi dentro `.admin-content`
  prendono `--bordo-campo` con `!important` (vince anche sugli stili in linea) e il contorno dell'accento al fuoco;
  `input[type=file]` ha `::file-selector-button` a pulsante. Le etichette `.form-group label` sono in carattere normale.
  Tutto sta dentro `.admin-content` o `.login-box`: l'editor, che carica `admin.css`, non cambia. La prova è
  `prove/prova-ux-pannello.js`.
- **Comportamenti del pannello aggiunti nel secondo giro UX** (`admin/assets/pannello.js`, 1 novembre 2026):
  - *Conferme*: `data-conferma` (pulsante, link, form) e `data-pn-gruppo-elimina` aprono `window.pnConferma(testo, elemento,
    poi)`, un `<dialog class="pn-conferma" role="alertdialog">`. Il pulsante che conferma ripete il testo del pulsante premuto
    (o `data-conferma-ok`), ed è `.btn-pericolo` (fondo `--errore`, testo `--su-pericolo`) se il nome o la domanda dicono
    elimina, rimuovi, cestina, ripristina, spegni... (o `data-conferma-pericolo`). La conferma si chiede in cattura; dopo il
    sì l'elemento riceve di nuovo il clic con il segno `__pnSi` (partono `data-uno`, `data-campo` e i gestori della pagina).
    All'apertura parte l'evento `pn-conferma` (detail: testo, pericolo, rispondi(si)), che usa `prove/conferme-pannello.js`.
    Senza `HTMLDialogElement` resta `confirm()`.
  - *Riquadri che si aprono dall'indirizzo*: un `#id` nell'indirizzo apre il `<details>` che lo contiene; un elemento con
    `data-spostato="url"` manda a quell'indirizzo (i vecchi `impostazioni.php#indirizzo-sito` e `#firma-sito`).
  - *Tabelle a schede*: le tabelle di `.admin-content` con una testata di almeno tre colonne e righe senza celle unite
    ricevono `data-schede` e, su ogni cella, `data-etichetta`; sotto 640 px `admin.css` le mostra una scheda per riga.
    `data-no-schede` le esclude.
  - Classi: `.pn-aiuto` (aiuto lungo in un `<details>`), `.ut-griglia` (modulo e elenco, una colonna sotto 760 px),
    `.imp-schede` (schede di Impostazioni). Impostazioni ha due schede: `impostazioni.php` (posta) e `?scheda=sito`.
    Dashboard: `#tipo-sito` è un `<details>` aperto alla prima visita (impostazione `dashboard_tipo_sito_visto_<id utente>`).
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

- **Google e IndexNow** (2.2.0, `server_servizi_esterni()`): con il service account salvato, `seo_gsc_prova()` (sola lettura)
  e la proprietà di `seo_gsc_proprieta()` fra quelle viste; senza, giallo e se il server raggiunge `oauth2.googleapis.com`. Con
  la chiave di IndexNow, il file `/<chiave>.txt` letto da fuori e `api.indexnow.org` raggiungibile; senza, giallo.
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
`widget.css` vinceva su tutto); con l'icona o l'immagine di lato, "Centro" resta il "a sinistra" di sempre. Dalla 2.2.0 anche gli altri nomi doppi hanno un
nome loro: `animazione_parole` del Titolo animato (`includes/editor-widget-extra-c.php`), `avviso_padding` (Avviso),
`flip_padding` (Flip box), `voce_padding` (Condividi) e `icona_spazio` (Icone social, `includes/editor-widget-extra-b.php`), tutti
con `'prima'`. Per un controllo senza opzioni (`dimensioni`, `misura`) `editor_nomi_di_prima()` copia il valore vecchio al nome
nuovo e lo lascia anche al vecchio, perché prima valeva per tutti e due: la pagina resta uguale al pixel (una `misura` prende solo
un valore con la forma di una misura, `editor_forma_misura()`). Il Titolo animato salvato prima aveva perso la scelta (diventava
""); ora vale quella di partenza, la scrittura a macchina, che prima non partiva. Prova: `prove/prova-nomi-doppi.php` (9 controlli).

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

**Come si vedono nel pannello (dalla 1.9.2).** Le sezioni a tendina sono `details.ed-sez` (intestazione con il fondo
`--ed-campo`; aperta: bordo e striscia a sinistra `--ed-primo`, il colore d'accento dell'aspetto). Un controllo di gruppo è
un `div.ed-gruppo` dentro il suo `.ed-controllo`: riquadro con fondo `--ed-campo` e striscia sinistra a metà fra accento e
bordo; il nome del gruppo (l'etichetta del controllo che lo contiene) esce in maiuscoletto nel colore d'accento, con la
regola `.ed-controllo:has(>.ed-gruppo)` di `admin/assets/editor/editor.css`. Solo variabili dell'aspetto: vale per tutti e quattro.

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

Dalla 1.2.6 l'Apertura ha la tendina **Disposizione** (scheda Contenuto, primo controllo): undici modi (ventisei dalla 2.17.0, sotto) di mettere
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

**Dalla 2.17.0 le disposizioni sono ventisei**: quindici nuove, scelte da Danilo, nella lingua delle undici (finestra di
vetro con il velo, `.chip` con onda e barre, occhiello, titolo con l'evidenza sfumata). Le undici di prima e la classica
non cambiano: stessi file, stesso HTML al byte (`editor_hero_foto()` ha due parametri in più, `$src` e `$dentro`, vuoti
di partenza: `includes/editor-hero.php:81`), foto identiche al pixel in sei temi, con e senza foto.

| Disposizione | Com'è fatta | Campi in più (scheda Contenuto, sotto le Barre, `'condizione' => ['disposizione' => <id>]`) |
|---|---|---|
| `prospettiva` | finestra in `perspective` con `rotateY(-26deg)`, si raddrizza con `:hover` (transizione di 1,4 s) | |
| `dispositivi` | la stessa foto nello schermo del computer (16:10 con la base) e nel telefono (9:19) davanti; al telefono solo il telefono | |
| `pila` | tre finestre sfalsate (`ed-hpi-f1..3`) con tre `object-position`, le barre in alto a destra | |
| `orbita` | foto in un cerchio (`inset:24%`), due anelli, `.ed-hor-giro` che ruota in 34 s con il riquadro dell'onda che gira al contrario | |
| `terminale` | finestra più una riga di comando: ogni riga si scrive con `clip-path` a `steps(--n)` dopo `--t` (solo CSS, `editor_hero_terminale_righe()`, `editor-hero-c.php:55`) | `terminale_righe` (testolungo; fino a 5 righe da 80 caratteri; `$` comando, `✓` o `+` fatto) |
| `nastro` | parole al centro, una fila di finestre scritta due volte che scorre in 70 s con la maschera che sfuma ai lati | `nastro_foto` (ripetitore di immagini, fino a 7; senza, tagli diversi della foto) |
| `traforata` | la parola dentro l'h1 in uno `span.finestra` nascosto ai lettori di schermo, `background-clip:text` con la foto e il velo (`--velo-1..2` con `color-mix`, ripiego ciano e viola); il titolo intero resta nell'h1, sotto, più piccolo | `parola` (testo; vuota: la prima parola del titolo; massimo 14 lettere, `editor_hero_parola()`) |
| `faro` | cono `conic-gradient` dall'alto, finestra che sale dal fondo, tagliata sotto con `clip-path` | |
| `orizzonte` | sole pieno e sfumato dietro la finestra (opacità 0,85, alone), pavimento a griglia luminosa (linee di 2 px con `drop-shadow`) in `perspective` che corre, finestra che galleggia | |
| `video` | foto con il pulsante play (`button`); al clic `hero-c.js` mette un `<video controls autoplay playsinline>` al posto della foto; senza video niente play | `video` (tipo di controllo nuovo `video`: `editor_video_locale()`, `editor-hero.php:94`) |
| `note` | etichette in un `ul` sulla finestra a `left:x%;top:y%` con `translate(-x%,-y%)` (restano dentro), cursore che si muove; al telefono in fila sotto la foto | `etichette` (ripetitore fino a 3: testo, x e y 0-100) |
| `lente` | la stessa foto scalata 2,4 volte intorno al punto con `clip-path:circle()`, a colori, con l'anello sfumato e il manico | `lente_x`, `lente_y` (0-100; il punto si disegna fra 16 e 84, 20 e 80 per non uscire dalla foto) |
| `costellazione` | niente foto: SVG scritto in casa (`editor_hero_forma()`, `editor-hero-d.php:68`), linee disegnate con `stroke-dashoffset`, punto viola che pulsa; al telefono dietro il testo, tenue | `forma` (scelta: piuma, cerchio, stella, `EDITOR_HERO_FORME`) |
| `primadopo` | la foto "dopo" con il velo, sopra lo strato "prima" ritagliato a `--pos`; la maniglia è un `input type=range` trasparente sopra la finestra (frecce, Inizio, Fine) più il trascinamento con il dito in `hero-c.js` | `immagine_prima` (facoltativa: senza, la stessa foto in bianco e nero spenta) |
| `trittico` | tre colonne: parole, foto in un tablet 3:4,2, tre riquadri (onda, il numero grande, le barre) | `numero` e `numero_etichetta` (testo; di partenza "5 min" e "Installazione"; con il numero vuoto il riquadro mostra la prima barra in % e le barre le altre) |

Come è fatto:
- `EDITOR_HERO_DISPOSIZIONI` (`includes/editor-hero.php:17`), le funzioni in `includes/editor-hero-c.php` e
  `includes/editor-hero-d.php`, il CSS in `sito/assets/hero-c.css` e `hero-d.css` (`EDITOR_HERO_CSS`, `editor-hero.php:51`,
  in quest'ordine dopo `hero-b.css`);
- lo script `sito/assets/hero-c.js` (`EDITOR_HERO_JS`, `editor-hero.php:57`) serve solo a video e prima e dopo: si carica
  dove nell'HTML c'è l'attributo `data-ed-hero-js` (asset `hero-js` con `'segno'`, `includes/editor-controlli.php:86`;
  `editor_asset_pagina()` cerca il segno al posto della classe del widget, `editor-controlli.php:120`); nella tela
  dell'editor si carica sempre (`admin/editor-anteprima.php:49`) e un `MutationObserver` riprende le Aperture ridisegnate;
- la tendina Disposizione ha `'accetta' => '/^[a-z]{3,20}$/'` (`includes/editor-widget.php:289`): una disposizione che
  questo sito non conosce (una pagina arrivata da un sito più nuovo) resta salvata e si vede come la classica;
- i campi nuovi sono in `includes/editor-widget.php:300` e seguenti; il tipo `video` si pulisce in `editor_pulisci_valore()`
  (`includes/editor.php:76`): solo percorsi del sito che finiscono in `.mp4` o `.webm`, niente `//`, `..`, `javascript:`,
  `data:`, indirizzi esterni (ammesso l'indirizzo intero solo se è quello di `cms_uploads_url()` su un altro dominio);
- i video si caricano in `<immagini>/video/AAAA/MM/` (`media_carica_video()`, `includes/media.php:209`: tipo letto
  dall'inizio del file, `ftyp` per MP4 e `1A 45 DF A3` per WebM, nome scelto dal sito, 64 MB al massimo), si elencano con
  `media_video_elenco()` (`media.php:183`, fuori da `media_elenco()`, che resta delle immagini); in Media c'è il riquadro
  **Video** (`admin/media.php:33`), nell'editor la finestra Scegli in modo video (`modoMedia()` e `campoVideo()` in
  `admin/assets/editor/editor.js`, azioni `carica_video` e `video` di `admin/editor-api.php:215`);
- movimento: ogni animazione nuova ha la sua regola `prefers-reduced-motion:reduce`; con meno movimento il terminale è
  già scritto e il nastro, l'orbita, il pavimento, le stelle stanno fermi;
- telefono: ogni disposizione va in colonna sotto 860 px (il trittico sotto 1020 e 700), con `overflow-x:clip` dove la
  scena esce dal riquadro (prospettiva, orbita); nessuna scorre di lato a 390 px.

Prove: `prove/prova-hero.js` (le ventisei, con `SUFFISSO=-nf` senza foto, con `TEMA=` nelle anteprime dei temi),
`prove/prova-aperture-nuove.js` (i campi nuovi sul sito e nell'editor), `prove/prova-hero-pulsanti.js` (ventisei
disposizioni per sei effetti), attacchi gruppo 39 (`prove/pagina-hero-cattive.php`).

**Infografiche** (29, prefisso `ig_`): passi, timeline, checklist, tabella, liste, numeri, card, mappa, flusso,
fasi, barre, prima e dopo, zone, domande; e le quindici disegnate in SVG (imbuto, piramide, matrice, ciclo, ciambella, cerchi
che si toccano, ragnatela, lancetta, iceberg, omini, viaggio, bilancia, mappa di calore, cerchi concentrici, barre al cento
per cento: capitolo 41). Usano i colori `--ig-*` della tavolozza (capitolo 12). In tutto i widget sono 107.

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

**I link dei widget** passano tutti da `editor_link_sicuro()` (`includes/editor-widget.php:528`).
- Restano come sono gli indirizzi che cominciano con `https://`, `http://`, `/`, `#`, `?`, `mailto:` e `tel:`.
- Dalla 2.0.2 i link scritti a mano non si scartano più:
  - `www.sito.it` e `sito.it/pagina` diventano `https://...` (un `.html`, `.pdf` e simili alla fine resta un file del sito);
  - un nome di pagina come `laser-coaching` diventa `/laser-coaching`.
- Si rifiuta (link vuoto, il pulsante punta a `#`) tutto ciò che ha uno schema diverso: `javascript:`, `data:`, `vbscript:`, anche scritto con caratteri di controllo in mezzo.
- Prove: `prove/prova-hero-pulsanti.js` (con `prove/pagina-hero-pulsanti.php`): ventisei disposizioni (undici fino alla 2.16) per sei effetti più i link scritti a mano, al computer e al telefono, e con `TEMA=` nelle anteprime dei temi.

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
footer (e, dalla 1.6.0, il riquadro in fondo agli articoli, 28.4), menu, tavolozze, impostazioni e widget spenti. Importa legge al massimo 20 MB, mostra un'anteprima e
applica solo le parti scelte: header e footer si abbinano per tipo e nome (il riquadro in fondo agli articoli solo per tipo: è uno), menu e tavolozze per nome; quelli con
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

### 9.12 I contenitori dei moduli (dalla 2.1)

Un modulo (o un addon) può far comporre con l'editor un oggetto suo che **non** è un contenuto del sito: niente elenco
Contenuti, sitemap, SEO, menu. Il primo è la lezione dei Corsi online (`docs-sviluppo/addon/corsi.md`). Si dichiara nel
manifesto: `'editor_contenitori' => ['<tipo>' => fn() => [...]]` (tipo: minuscole, numeri e `_`, da 3 a 31 caratteri; mai
`pagine`, `articoli`, `modelli`). Solo i moduli: i temi non possono (non passa da `cms_filtro()`).

| Chiave | Obbligo | Cosa |
|---|---|---|
| `riga($id)` | sì | la riga con le chiavi di una pagina: `title`, `slug`, `status` (`published` se c'è una struttura pubblicata), `struttura`, `struttura_bozza`, `content` (il testo di prima), `percorso`, `vedi` (l'anteprima), `scheda` (la pagina del pannello a cui tornare); null se non c'è |
| `salva($id, $json, $pubblica, $autore)` | sì | scrive la struttura: bozza, o pubblicata togliendo la bozza |
| `etichetta`, `uno` | no | "Lezioni dei corsi" (pagina Versioni), "lezione" (finestra Impostazioni) |
| `permesso()` | no | chi lo apre; senza, solo l'amministratore |
| `impostazioni($id, ['titolo' => ...])` | no | il titolo dalla finestra Impostazioni (che per un contenitore ha solo il titolo) |
| `togli($id)` | no | "Torna al contenuto della scheda" |
| `anteprima($riga)` | no | `['prima' => html, 'dopo' => html, 'testa' => html]`: la cornice della tela e i suoi fogli |

Nel motore (`includes/editor.php`):
- `editor_contenitori()` (`:453`) legge i manifesti una volta per richiesta e scarta le definizioni senza `riga` o `salva`;
  `editor_contenitore()` (`:469`), `editor_contenitore_permesso()` (`:474`), `editor_ambito()` (`:482`: `modello`,
  `contenitore:<tipo>` o `contenuto:<tipo>`);
- `editor_tipo_valido()` (`:496`) riconosce il tipo; `editor_pagina()` (`:512`) chiede la riga al modulo e completa le
  chiavi che mancano; `editor_salva()` (`:554`) chiama `salva` e lascia la versione; `editor_percorso_pagina()` (`:653`) usa
  `percorso`.

Nelle pagine:
- `admin/editor.php:49` (403 senza permesso), `:57` (la freccia torna a `scheda`), `:110` (`slugFisso`, dati
  `contenitore` per `editor.js`), `:155` ("Vedi la pagina" = `vedi`); niente header e footer da scegliere, niente controllo SEO;
- `admin/editor-api.php:45` (403 senza permesso, per ogni azione), `:75` (Pubblica non tocca tabelle del nucleo), `:133`
  (Impostazioni: solo il titolo), `:192` (`togli`); le revisioni con `editor_ambito()`;
- `admin/editor-anteprima.php:16` (403 senza permesso: la tela mostra il contenuto), `:37` (la cornice);
- `admin/assets/editor/editor.js:1117` (finestra Impostazioni con il solo titolo), `:1203` (dopo "Torna al contenuto
  della scheda" si va a `scheda`).

Versioni (`includes/versioni.php`): ambito `contenitore:<tipo>`, con i dati `albero` e `stato` (`versioni_dati_contenitore()`,
`:321`); stato di adesso (`:357`), nome (`:390`), ripristino con il salvataggio normale (`:438`; con il modulo spento:
"riaccendilo per ripristinare"); l'etichetta nella pagina Versioni dal contenitore (`:148`); l'anteprima di una versione in
`admin/versioni.php:92`, con la cornice. Il redattore vede e tocca solo gli ambiti `contenuto:`: i contenitori sono
dell'amministratore.

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

Dalla 1.6.0 c'è un terzo tipo di modello, `fine`: il riquadro che esce in fondo a ogni articolo (Articoli > Aggiungi
sempre alla fine). Non passa da `modelli_scegli()` e non ha condizioni né predefinito: capitolo 28.

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
- Dalla 2.2 il link personalizzato e l'indirizzo di ogni voce hanno **Scegli dal sito**: una pagina, un articolo o una
  categoria scelti così diventano una voce di quel tipo (con `rif`); un indirizzo scritto a mano su una voce con `rif` la fa
  diventare un link personalizzato (capitolo 38).

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

Partono con il prodotto undici temi, nessuno con PHP (solo `tema.json`, `stile.json`, `modelli.json`, `tema.css`, `anteprima.png`, nei temi della 2.5.0 `anteprima.webp` per stare sotto i 2 MB dello zip di aggiornamento):

| Tema | Idea |
|---|---|
| Cosmo | lo scuro di partenza (notte blu, ciano e viola): attivarlo lascia il sito com'era |
| Aria | chiaro e pulito, bianco e un solo blu |
| Bottega | caldo editoriale, carta crema, titoli Georgia, terracotta e salvia |
| Estate (2.5.0) | sabbia chiara, corallo e blu del largo, titoli Space Grotesk, pulsanti a pillola, onda sopra il footer |
| Inverno (2.5.0) | bianco ghiaccio con una trama di brina, blu profondo, titoli Inter sottili, angoli quasi vivi |
| Autunno (2.5.0) | scuro caldo (castagna), zucca e ocra, titoli Palatino; infografiche chiare color crema |
| Primavera (2.5.0) | bianco di germoglio, rosa pesco e verde foglia, titoli Georgia in corsivo, forme tonde |
| Giallo (2.5.0) | tinta piena: sfondo `rgb(255,255,0)`, testo nero, pulsanti neri a lettere gialle, footer nero |
| Rosso (2.5.0) | tinta piena: sfondo `rgb(255,0,0)`, testo nero, titoli in maiuscolo, pulsanti bianco e nero, footer nero |
| Verde (2.5.0) | tinta piena: sfondo `rgb(0,255,0)`, testo nero, titoli a spaziatura fissa, footer nero a lettere verdi |
| Blu (2.5.0) | tinta piena: sfondo `rgb(0,0,255)`, testo bianco, titoli Georgia, pulsanti gialli, footer bianco |

I temi della 2.5.0 finiscono il loro `tema.css` con un blocco uguale per tutti ("Leggibilità dei widget"): i colori
ricavati (`--primario-chiaro`, `--secondario-scuro`, `--su-secondario`, `--errore*`, `--successo*`), il testo dei
pulsanti anche dentro `.corpo` (dove `sito.css` colora i link con `--ciano`), i colori ufficiali dei social più scuri
dove portano testo bianco, lo sfondo dello slider, l'occhiello delle infografiche su testata scura. Sono correzioni
per difetti dei widget del nucleo che con colori chiari si vedono (testo del pulsante dentro un articolo, titolo
dello slider dentro `.corpo`, mappa con testo bianco): i tre temi più vecchi non le hanno. Provati da
`prove/prova-temi-nuovi.js`: anteprima e attivazione dal pannello, sei pagine a 1280 e 390 px, contrasto WCAG di
ogni testo misurato contro quello che c'è davvero dietro (`document.elementsFromPoint`), sfondo esatto delle tinte piene.

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
| `modello` | un header, un footer o il riquadro in fondo agli articoli (28) |
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

`includes/form.php`, pagina Form (dalla 1.9.1 nel menu dentro Newsletter e form, `admin/includes/admin-header.php`). Si costruisce un form nel pannello e lo si mette in una pagina con il widget
Modulo (`form:ID`).

- Tabelle: `moduli` (definizione) e `moduli_invii` (invii).
- Tipi di campo: testo, email, telefono, testo lungo, scelta, scelta multipla, spunta, data, numero; dalla 2.1 anche
  **ora** (`input type=time`, validata `HH:MM`) e **file** (vedi "Il campo File", sotto).
- Ogni form ha: titolo, introduzione, campi, testo del pulsante, consenso, lista della newsletter (con o senza
  conferma), avviso per email, pagina di ringraziamento (`/grazie/?f=ID`).

All'invio (`form_ricevi()`, `includes/form.php:580`; dalla 2.16.0 anche i blocchi nuovi, 15.15), in ordine:
1. campo esca invisibile (`sito_web`): se è pieno, è un robot;
2. validazione di ogni campo secondo il tipo;
3. consenso alla privacy obbligatorio;
4. captcha (prova di lavoro fatta dal browser, 15.4);
5. limite di invii per IP;
6. salvataggio dell'invio;
7. iscrizione alla lista scelta, con o senza doppio consenso (`form_iscrivi()`, `includes/form.php:752`). Dalla 2.0.0
   con il doppio consenso la persona entra **solo** nella lista del form, alla conferma, e l'email di conferma dice per
   cosa è (il titolo del form, o il suo nome, o il nome della lista). Prima del salvataggio, se il form chiede la conferma e
   da quell'IP sono arrivate troppe richieste di iscrizione, il form risponde con l'errore `troppi` (prima: grazie e
   nessuna email);
8. avviso via email, uno per destinatario (`form_avviso_destinatari()`, `includes/form.php:360`): gli indirizzi della
   colonna `avviso_a` del form, oppure, se è vuota, `posta_notifica_email()` (Impostazioni, Posta in uscita);
9. innesco dei flussi della newsletter con origine `form`: subito per chi è attivo; per chi deve ancora confermare si
   ricorda in `newsletter_inneschi_attesa` e parte alla conferma (dalla 2.0.0, 15.5).

**A chi va l'avviso (dalla 1.9.0).** Colonna `moduli.avviso_a` (VARCHAR 500), aggiunta da `form_schema()` anche alle tabelle
nate prima (`includes/form.php:87`). In pannello è la casella "Manda l'avviso a" del riquadro Dopo l'invio
(dalla 2.16.0 nel passo 3, Messaggi, `admin/form.php:277`, solo amministratore). `form_avviso_indirizzi()` (`includes/form.php:349`) divide su virgole, punti e
virgola, spazi e a capo, valida ogni indirizzo con `FILTER_VALIDATE_EMAIL` (niente intestazioni nascoste con un a capo),
toglie i doppioni senza badare alle maiuscole, al massimo `FORM_AVVISO_MAX` (5); un indirizzo sbagliato ferma
`form_salva()` con il suo nome. Prove: `prove/prova-form-avviso.js` (con il server di posta finto), gruppo 25 di
`tools/sicurezza-prova.js`.

Gli invii si leggono in Newsletter e contatti > Invii dei form, e si esportano in CSV (celle protette contro le
formule, capitolo 18.10).

**Il campo File (dalla 2.1).** `FORM_TIPI_CAMPO` e `FORM_FILE_TIPI` (`includes/form.php:19-36`).
- Regole del campo (`form_file_regole()`, `:222`): `estensioni` scelte fra pdf, jpg, png, webp, docx, xlsx, odt, txt (mai
  tipi che si eseguono o che il browser interpreta: niente svg, html, php) e `peso` da 1 a `FORM_FILE_PESO_MAX` (10) MB. Il
  peso vero è il minore fra quello scelto e quello del server (`form_file_limite_server()`, `:228`: upload_max_filesize e
  post_max_size); il costruttore lo dice ("Il server accetta al massimo...").
- Nel pannello (dalla 2.16.0 `admin/assets/form-campi.js:72`, `fmRegoleFile()`) le caselle dei tipi e la tendina del peso; sul sito
  (`form_html()`, `:492`) `input type=file` con `accept` e, sotto, i tipi e il peso (`aria-describedby`); il form prende
  `enctype="multipart/form-data"` solo se ha un campo File (gli altri form restano come prima).
- All'invio (`form_ricevi()`, `:580`; il quarto argomento è `$_FILES`, da `api/modulo.php`), prima del captcha: errore del
  caricamento o peso oltre il massimo dà `file_peso:ETICHETTA`; poi `form_file_controlla()` (`:254`) guarda il **contenuto
  vero**: estensione fra quelle del campo; inizio del file (`%PDF-`, `FFD8FF` più `getimagesize`, la firma PNG,
  `RIFF....WEBP`, lo zip di Office con `word/document.xml`, `xl/workbook.xml` o il `mimetype` di odt e senza programmi
  dentro, testo UTF-8 senza NUL); il tipo MIME di `finfo` fra quelli ammessi; nessun `&lt;?php`, `&lt;?=` o `<script`
  dentro (come `media_ha_codice()`). Se no: `file_tipo:ETICHETTA`. Due file nello stesso campo (array) non passano.
- Il file si salva solo quando tutto il resto è a posto (consenso, captcha, limite): in `form_file_dir()` (`:240`), cioè
  `<cartella dei file riservati>/form/`, con un nome a caso (`bin2hex(random_bytes(16))`) e l'estensione controllata,
  `chmod 0640`. La cartella ha un `.htaccess` che nega tutto (anche quella sopra, capitolo 33) e il router delle prove dà
  403. L'invio ricorda i file nella colonna `moduli_invii.allegati` (JSON: campo, nome com'era, file, peso, tipo), aggiunta da
  `form_schema()` anche alle tabelle vecchie. Se il salvataggio dell'invio fallisce, il file si toglie.
- L'avviso per email allega i file se in tutto stanno sotto `FORM_FILE_ALLEGA_MAX` (4 MB), con `posta_invia(..., $allegati)`;
  più grandi, l'email dice di scaricarli dal pannello.
- Scarico: `admin/form-submissions.php:31`, `?file=<invio>&n=<numero>`, solo amministratore (`require_admin()` della pagina).
  `form_allegati()` (`:311`) tiene solo i nomi `^[a-f0-9]{32}\.[a-z]{3,4}$`: un invio toccato nel database che punta fuori
  (`../`, percorso assoluto) dà 404. Sempre `application/octet-stream`, `Content-Disposition: attachment`, `nosniff`, CSP
  `sandbox`. In Invii dei form, sotto i dati, "Scarica <nome>" con il peso.
- Cancellazione: Elimina in Invii dei form (`form_allegati_cancella()`, `:317`, prima di cancellare le righe), Elimina il form
  (`form_elimina()`), **Cancella dati** della newsletter (`admin/newsletter.php:161`: i file degli invii con quell'email,
  prima di `newsletter_cancella_dati()`). `form_file_orfani()` (`:331`) toglie i file che nessun invio cita più (non quelli
  di meno di un minuto, che possono essere di un invio in corso).
- Un form con un file oltre `post_max_size` arriva a PHP senza campi: `api/modulo.php:22` torna alla pagina di provenienza
  (dal Referer, solo percorsi interni) con `?modulo=file_peso`. `sito/assets/moduli.js` non salva e non rimette i campi file
  nel ripristino dopo un errore (un `value` su un input file lancia un'eccezione).
- Dalla 2.1 `modulo_torna()` (`api/modulo.php:11`) aggiunge la cartella del sito (`cms_sito_base()`) ai percorsi: con il sito in
  una cartella, un form con un errore, il grazie (`/grazie/`) e la richiesta di prenotazione portavano alla radice del dominio.
- Messaggi: `MODULI_ERRORI['file_peso']` e `['file_tipo']` (`includes/moduli.php`), con l'etichetta del campo in
  `form_errore()`.
- Prove: `prove/prova-widget-2-1.js` (parte "Form con Ora e File") e `prove/attacchi-widget-2-1.js` (parti "file" e "scarico").

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
- Senza SMTP usa `mail()` di PHP. Dalla 2.0.0 con il From del mittente scritto in Posta in uscita (anche senza server
  SMTP; altrimenti l'utente; altrimenti `noreply@` il dominio del sito: `posta_mittente()`, `includes/posta.php:120`; dalla 2.1.0
  prima di tutto il mittente predefinito confermato, capitolo 35) e il
  Return-Path con `-f` (se `mail()` lo rifiuta si riprova senza). Prima le email partivano senza From.
- Dalla 2.0.0 ogni email HTML esce in `multipart/alternative`: la parte di testo semplice la scrive `posta_testo_da_html()`
  (`includes/posta.php:139`: paragrafi a capo, link come "testo (indirizzo)", niente immagini né testo di anteprima
  nascosto), il corpo MIME lo compone `posta_mime()` (`:172`), tutto in base64, uguale per SMTP, `mail()` e le API di SES e
  Mailgun. Dalla 2.0.3 `posta_mime()` accetta gli allegati (`multipart/mixed`).
- `posta_invia($a, $oggetto, $corpo, $intestazioni, $tipo, $allegati)` (`includes/posta.php:215`): il quinto argomento è il
  tipo per il registro (`campagna`, `flusso`, `conferma`, `avviso_form`, `prova`, `social`; `altro` di partenza), il sesto la
  lista degli allegati. **Dalla 2.1.0** con un servizio scelto in Consegna delle email (Brevo, Amazon SES, Mailgun) l'email
  parte dalle sue API (`consegna_servizio_attivo()`, `:219`; capitolo 35) e SMTP e `mail()` non si usano. Con SMTP il From
  è il mittente chiesto da chi chiama se è confermato, altrimenti il predefinito, altrimenti quello di Configurazione email
  (`consegna_mittente_per()`, `:324`): anche l'envelope (`MAIL FROM`) usa quell'indirizzo.
- **Registro della posta (dalla 2.0.0).** Ogni spedizione lascia una riga in `posta_registro` (quando, tipo, esito,
  errore; nessun indirizzo), tabella creata da `posta_registro_schema()` (`:58`). `posta_inviate($secondi)` (`:90`) conta
  le email partite: è il conto dei limiti della newsletter (15.5). Il cron toglie le righe oltre 60 giorni
  (`posta_pulisci_registro()`, `:105`).
- **Esito (dalla 2.0.0).** Dopo `posta_invia()`, `posta_esito()` (`:45`) dice `ok`, `rimbalzo` (il server SMTP ha
  rifiutato il destinatario al `RCPT TO` con un 5xx), `temporaneo` (4xx al `RCPT TO`) o `errore` (connessione, accesso,
  server). Il rifiuto del destinatario è l'eccezione `PostaRifiutata` (`:52`) con il codice SMTP; con `mail()` e con le API l'esito
  è solo `ok` o `errore` (i rimbalzi delle API arrivano dopo, con gli avvisi dei servizi: capitolo 35).
- Gli avvisi (form, sicurezza) vanno all'indirizzo `notifica_email`.
- La password SMTP è salvata nel database insieme alle altre impostazioni della posta (in chiaro, come prima). Le chiavi dei
  servizi della 2.1.0 invece sono cifrate (capitolo 35).

### 15.4 I pezzi comuni dei moduli: captcha, campi, limiti

`includes/moduli.php` dà a newsletter e form del costruttore (15.1) le stesse cose:
- lettura e controllo dei campi (nome, cognome, email, privacy), campo esca invisibile alle persone;
- limite: 5 invii l'ora per IP e per modulo;
- l'impaginazione delle email (`moduli_email_html`, intestazione e firma dalle impostazioni; dalla 2.7.0 un quinto argomento
  facoltativo cambia intestazione, firma, frase sotto il pulsante e colore di una sola email, 15.6);
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

**Iscritti e liste.** Stati: in attesa, attivo, uscito, rimbalzato (dalla 2.0.0). Un iscritto può stare in più liste.
Colonne della 2.0.0 in `newsletter_iscritti`: `errori_di_fila`, `ultimo_errore`, `rimbalzato_at`, aggiunte da
`newsletter_schema()` (`includes/newsletter.php:38`) anche alle tabelle nate prima. **Dalla 2.1.0** c'è anche lo stato `spam`
(ha premuto "è spam": capitolo 35) e la colonna `newsletter_campagne.mittente_id` (0 = il mittente predefinito). La migrazione "chi non sta in nessuna
lista entra nella principale" gira una volta sola, quando la lista principale nasce: dalla 2.0.0 chi aspetta la conferma di
un form non sta in nessuna lista e non deve finire nella principale.

**Doppio consenso (dalla 2.0.0).** `newsletter_richiesta($dati, $opzioni)` (`includes/newsletter.php:280`) salva la
persona (una nuova in attesa; chi c'è già resta nel suo stato, anche uscito o rimbalzato, fino alla conferma) e scrive la
richiesta in `newsletter_conferme` (token sha256, iscritto, lista, form d'origine, scadenza a 7 giorni). Opzioni: `lista`
(predefinita la principale: il modulo della newsletter), `form`, `cosa` (per cosa si iscrive, nelle frasi dell'email),
`oggetto`, `testo`, `pulsante`, `fonte` e, dalla 2.7.0, `email` (le parti dell'email scritte nel form; l'email la compone
`nl_email_conferma()`, 15.6). Il modulo della newsletter (`api/modulo.php`) la chiama senza opzioni, come prima;
un form con conferma passa la sua lista, il suo id e il suo titolo, e la persona non entra più nella principale. Errori:
`troppi` (5 richieste l'ora dallo stesso IP: `newsletter_troppe_richieste()`, `:258`) e `posta`.

Il clic su `/newsletter-conferma/` chiama `newsletter_conferma($token)` (`:388`, vero o falso, per la pagina del sito), che
usa `newsletter_conferma_esito($token)` (`:335`): un link vale una volta sola; la persona diventa attiva (consenso adesso,
errori azzerati, via dalla lista di soppressione: ha confermato di sua volontà), entra nella lista della richiesta (i flussi
"lista" partono lì, `newsletter_in_lista()`, `:191`), il flusso "iscrizione" parte solo per la lista principale, per chi non
era attivo partono anche i flussi delle liste in cui stava già, e partono gli inneschi rimasti in attesa. I link mandati prima
della 2.0.0 (token nella riga dell'iscritto) valgono ancora, con la lista principale.

**Aggancio alla conferma.** `newsletter_conferma_esito()` ritorna null oppure
`['iscritto' => id, 'email' => ..., 'lista' => id, 'form' => id del form d'origine (0 dal modulo della newsletter), 'nuovo' => bool]`.
Lo stesso array arriva all'evento dei moduli e dei temi `newsletter_confermata` (`cms_evento`, capitolo 17) e, nella stessa
richiesta, a `newsletter_ultima_conferma()` (`:393`). Per consegnare qualcosa a chi conferma da un certo form: un modulo
dichiara `'eventi' => ['newsletter_confermata' => function (array $e) { if ($e['form'] === 3) { ... } }]`, oppure la pagina di
conferma legge `newsletter_ultima_conferma()` dopo `newsletter_conferma()`.

**Inneschi in attesa (dalla 2.0.0).** `newsletter_flussi_innesco()` (`includes/newsletter-flussi.php:684`) fa partire i
flussi solo per chi è attivo. Prima un cammino partiva anche per chi era in attesa, il giro lo fermava (`fermato`) e la
chiave unica (flusso, iscritto) non lo faceva più ripartire alla conferma. Ora l'innesco `form` di chi deve ancora
confermare si ricorda in `newsletter_inneschi_attesa` (`newsletter_innesco_in_attesa()`, `includes/newsletter.php:403`) e
parte alla conferma.

**Uscita.** Ogni email ha un link di uscita firmato (HMAC) e le intestazioni `List-Unsubscribe` e
`List-Unsubscribe-Post` (uscita con un clic dai programmi di posta). L'uscita vera avviene con un POST su
`/newsletter-esci/`. Chi esce perde anche le email ancora in coda. Dalla 2.1.0 il link porta anche la campagna (o il flusso e
il passo) firmata (`newsletter_link_uscita($id, $traccia)`, `:426`): `newsletter_uscita_registra()` (`:436`, chiamata da
`sito/index.php:177`) conta l'uscita per quella campagna nelle statistiche.

**Cancella dati (dalla 2.0.0).** `newsletter_cancella_dati($id)` (`:510`, pulsante "Cancella dati" in Iscritti,
`admin/newsletter.php:155`) toglie l'iscritto da tutte le tabelle della newsletter (`newsletter_togli_iscritti()`, `:494`:
coda, liste, richieste di conferma, inneschi in attesa, aperture e clic, cammini nei flussi), gli invii dei form con la sua
email (`moduli_invii`) e mette l'impronta sha256 dell'email in minuscolo in `newsletter_soppressi`. Il registro della posta
non ha indirizzi.

**Importazione (dalla 2.0.0).** `newsletter_importa_csv($file, $lista, $flussi)` (`:563`): fonte `import`, consenso con la
data della colonna CONSENSO (o DATA CONSENSO, OPTIN...; `newsletter_data_csv()`, `:546`), altrimenti NULL; si saltano chi è
nella lista di soppressione, chi è uscito, chi è rimbalzato e (dalla 2.1.0) chi ha segnalato spam (anche dalla lista). I flussi della lista partono solo con la
spunta "Fai partire i flussi per gli importati" (`admin/newsletter.php:268`, spenta di partenza). Prima: tutti attivi con
il consenso di adesso, fonte `brevo`, flussi sempre.

**Campagne.** Stati: bozza, programmata, in invio, inviata. Nell'oggetto e nel testo `{nome}` diventa il nome
dell'iscritto. Vanno solo agli attivi.

**Invio.** Dal cron (`cron-newsletter.php`, ogni 5 minuti), `newsletter_invia_giro()` (`:743`):
- un solo invio alla volta (lock di MySQL);
- avvia le campagne programmate arrivate alla loro ora;
- al massimo 40 email per giro, entro il limite orario (predefinito 150 l'ora) e, se impostato, quello al giorno (ultime 24
  ore, `per_giorno` in `newsletter_config()`, `:232`; 0 = nessuno; dalla 2.1.0 con il riscaldamento acceso vale il più basso fra
  questo e quello del giorno di riscaldamento, `:756`, capitolo 35). Dalla 2.0.0 i limiti contano **tutte** le email del CMS
  dal registro della posta (15.3), non solo le campagne; il primo giro dopo l'aggiornamento conta anche la coda;
- tre tentativi per indirizzo, poi errore; si ferma dopo 3 errori senza nessun invio riuscito;
- con i posti rimasti, manda le email dei flussi.

**Rimbalzi (dalla 2.0.0).** Dopo ogni email di campagna o di flusso `newsletter_esito_invio()` (`:486`) legge
`posta_esito()`: `ok` azzera `errori_di_fila`; `rimbalzo` (5xx al `RCPT TO`) chiama `newsletter_rimbalzato()` (`:475`):
stato `rimbalzato`, coda saltata, nessun altro tentativo; i flussi lo fermano al giro dopo; `temporaneo` (4xx) aggiunge un
errore di fila: da 3 in su l'iscritto resta attivo ma il pannello lo segnala (avviso in cima, filtro "Con 3 errori di fila",
nota nella riga). Gli errori del server (connessione, accesso) non contano per l'indirizzo. Niente lettura di caselle IMAP:
i rimbalzi che arrivano dopo, come email, non si vedono. Dalla 2.1.0 quelli dei servizi di spedizione arrivano con i webhook
(capitolo 35), e ogni rimbalzo di una campagna diventa anche un evento `rimbalzo` (`:781`) per le statistiche.

**Pulizia (dalla 2.0.0).** `newsletter_pulizia()` (`:527`), dal cron della newsletter (`cron_newsletter()`,
`includes/cron.php:121`), una volta al giorno (impostazione `newsletter_pulizia_giorno`): registro della posta oltre 60 giorni, richieste di conferma scadute, iscritti
ancora in attesa con la conferma scaduta da più di 30 giorni, inneschi in attesa oltre 40 giorni.

**Automazioni (fino alla 2.14.0 "flussi automatici").** Un'automazione parte da uno o più inneschi (iscrizione, lista,
tolto da una lista, form, apertura, clic, inattività, disiscrizione, anniversario, aggiunta a mano) e segue nodi: email,
azioni sulle liste e sugli attributi, webhook, attesa, attendi un evento, Sì o No, divisioni a più rami e in percentuale,
fine. Le persone dentro stanno in `newsletter_corsi` (un giro per ogni rientro), la storia in `newsletter_flussi_registro`,
i contatori per passo in `newsletter_flussi_numeri`. Il dettaglio nel capitolo 15.14.

**Statistiche.** Aperture (pixel) e clic (link che passano da `api/nl.php`, firmati) si registrano in
`newsletter_eventi`, solo per le email vere e non per quelle di prova. La pagina Statistiche mostra campagne,
clic per link, iscritti per mese. Dalla 2.1.0 anche rimbalzi, uscite e segnalazioni di spam per campagna (eventi `rimbalzo`,
`uscita`, `spam` scritti da `newsletter_evento_interno()`, `includes/newsletter-flussi.php:231`; conti in
`newsletter_statistiche_campagne()`, `:173`), con le soglie di Gmail e Yahoo in rosso (spam oltre lo 0,3%, rimbalzi oltre il 2%). Dalla 2.14.0 la pagina è a schede, con il periodo, e le soglie sono quelle del capitolo 15.13.

Prove: `prove/prova-newsletter-difetti.js` (dalla 2.0.0, con `prove/smtp-finto.py ... rimbalzi`,
`prove/sendmail-finto.py` e `prove/newsletter-cli.php`); per la consegna, capitolo 35.

### 15.6 L'email di conferma modificabile (dalla 2.7.0)

Richiesta di Danilo: "Voglio poter modificare l'email che arriva, in ogni sua parte." Motore in
`includes/newsletter-email.php` (caricato da `includes/newsletter.php:25`, e lui a sua volta carica `newsletter.php`: le due
`require_once` si chiudono a vicenda).

**Le parti** (`NL_EMAIL_PARTI`, `:27`, con lunghezza massima e "una riga sola"): `intestazione` (150, la riga scura in cima),
`oggetto` (255), `titolo` (255), `testo` (5000, HTML limitato, più righe), `pulsante` (100), `colore` (`#rrggbb`), `sotto`
(300, la frase sotto il pulsante), `firma` (300, più righe), `nota` (500, più righe). Il **link** del pulsante non è una parte:
resta sempre quello vero (sicurezza).

**Da dove arriva ogni parte**, nell'ordine: il form (colonna `moduli.conferma_email`, TEXT con il JSON delle parti, NULL =
nessuna; creata da `form_schema()` e aggiunta alle tabelle vecchie, `includes/form.php:55` e `:88`), i valori comuni
(impostazione `newsletter_conferma_email`, stesso JSON, `nl_email_comuni()`, `:75`), il testo di partenza
(`nl_email_partenza($principale)`, `:88`: le frasi di prima, con i segnaposti, nella forma "alla newsletter" per il modulo
della newsletter e "a «...»" per form e liste). Una parte uguale a quella che erediterebbe non si salva
(`nl_email_da_salvare()`, `:112`, con `nl_email_uguali()`, `:123`, che non bada a spazi e a capo di Windows): così "Riempi i
campi vuoti" e Salva senza cambiare niente lasciano tutto com'era.

**La composizione**: `nl_email_conferma($c)` (`:163`) ritorna `[oggetto, html]`. Per ogni parte assente, o uguale alla
partenza, usa **il codice di prima, riga per riga** (`l&#039;` scritto a mano compreso): senza parti scritte l'email esce
uguale al byte a quella della 2.6.0 (`prove/email-conferma-cli.php` ha il codice della 2.6.0 copiato, e la prova lo
confronta con l'email vera). Le vecchie opzioni di `newsletter_richiesta()` (`oggetto`, `testo` HTML, `pulsante`) vincono
ancora su tutto. `newsletter_richiesta()` (`includes/newsletter.php:282`) la chiama con `principale` (lista principale e
nessun form), `cosa`, il nome, il link vero e l'opzione nuova `email` (le parti del form); `form_iscrivi()`
(`includes/form.php:572`) passa le parti del form. Il modulo della newsletter (`api/modulo.php:56`) non cambia: prende i
comuni.

**L'impaginazione**: `moduli_email_html()` (`includes/moduli.php:148`) ha un quinto argomento facoltativo `$parti` (HTML già
pronto): `intestazione`, `firma`, `sotto`, `colore`, `dopo_firma`. Senza, esce come prima: le altre email del sito (avvisi,
file, flussi) non cambiano. La nota "Se la richiesta l'ha fatta qualcun altro" ora entra con `dopo_firma` (prima con uno
`str_replace` sulla firma, che con una firma cambiata non avrebbe più trovato il punto). Il testo del pulsante è bianco o
quasi nero secondo il contrasto con il colore scelto (`nl_email_su_colore()`, `:150`, rapporto WCAG); con il viola di
partenza resta `#fff`.

**Segnaposti** (`nl_email_segnaposti()`, `:129`): `{nome}`, `{lista}` (il `cosa`: titolo del form, o nome, o nome della
lista; "newsletter ..." per il modulo della newsletter), `{giorni}` (`MODULI_CONFERMA_GIORNI`), `{sito}` (`sito_nome`). Con il
nome vuoto `" {nome}"` sparisce con lo spazio. Nei testi semplici i valori entrano come testo e l'escape lo fa chi stampa
(`h()`); nel testo HTML entrano **dopo** la pulizia e con `h()`, così un nome non porta codice nell'email.

**Il testo** (`nl_email_testo_html()`, `:140`): se non ha paragrafi scritti a mano, una riga vuota separa i paragrafi e un a
capo diventa `<br>`; poi `sicurezza_html_pulito($t, 'ricco')` (capitolo 18: tag ammessi del widget Testo, niente `on*`,
indirizzi `javascript:` tolti). La pulizia si fa anche al salvataggio (`nl_email_parte_pulita()`, `:37`, quando il testo
contiene `<`) e di nuovo a ogni invio, per un valore toccato nel database.

**Pulizia al salvataggio** (`nl_email_parte_pulita()`): niente NUL; le parti di una riga perdono a capo e tabulazioni (un
oggetto con `\r\nBcc:` diventa una riga sola: nessuna intestazione nascosta; `posta_invia` toglie comunque gli a capo
dall'oggetto); il colore passa solo come `#rrggbb` (un `red;background:url(...)` si scarta); lunghezza massima. **Emoji**:
le tabelle sono a 3 byte, i caratteri a 4 byte si salvano come entità `&#x1F389;` (`newsletter_4byte_dentro`) e tornano
caratteri alla lettura (`nl_email_da_json()`, `:60`), prima dell'escape (senza, uscirebbe `&amp;#x1F389;`).

**Pannello** (solo amministratore):
- Form, scheda di un form: riquadro **Email di conferma** (`admin/form.php:88`, nella colonna larga sotto Consenso; nel
  riquadro stretto Dopo l'invio c'è il rimando `#fm-conferma`), campi `ce[...]` dentro un `details` aperto quando il form ha
  parti sue; `form_salva()` (`includes/form.php:350`) le salva solo se arrivano (`array_key_exists('ce')`: il kit di
  partenza non le tocca). Con un errore di salvataggio i campi tornano come scritti (`admin/form.php:31`).
- Newsletter > Impostazioni: riquadro **Email di conferma dell'iscrizione** (`admin/newsletter.php:378`, `id="nl-conferma"`),
  azione `conferma_email` (`:184`), confronto con la partenza "newsletter".
- I campi, uguali nelle due pagine: `admin/includes/email-conferma-campi.php` (`ec_campi_html()`, `:20`): etichetta vera,
  aiuto collegato (`aria-describedby`) che dice cosa si usa quando il campo è vuoto (comune o partenza), nessun segnaposto
  dentro i campi (non deve sembrare un valore); `ec_segnaposti_html()` (`:52`) spiega i segnaposti in un `details.pn-aiuto`;
  `ec_anteprima_html()` (`:63`) i pulsanti, il messaggio (`role=status`, `alert` per gli errori) e la cornice.
- `admin/assets/email-conferma.js`: niente gestori in linea (CSP a nonce); `data-email-conferma` (l'indirizzo dell'API),
  `data-contesto`, `data-csrf`; i pulsanti `data-ec-anteprima`, `data-ec-prova`, `data-ec-riempi`; il selettore del colore
  `data-ec-colore` scrive nella casella accanto. I campi viaggiano con `campiProtetti.codifica()` (base64 per ModSecurity).
  L'anteprima entra in un `iframe sandbox` con `srcdoc` (niente script, origine opaca; la CSP del pannello ammette gli stili
  in linea delle email).
- `admin/email-conferma-api.php`: solo POST, token e Origin (`csrf_ok`), solo amministratore (finisce in `-api.php`, quindi
  `require_login()` dà 403 al redattore, e lo controlla di nuovo). `azione=anteprima` ritorna `{oggetto, html}`;
  `azione=prova` manda l'email all'indirizzo dell'utente collegato, con `[PROVA]` nell'oggetto e il link finto
  `nl_email_link_finto()` (`:211`, `?t=prova-senza-valore`: la pagina di conferma lo dà per scaduto e non conferma niente).
  Usa i campi come sono scritti in quel momento, anche non salvati; il nome d'esempio è "Maria".

Intestazione e firma scritte qui valgono **solo** per l'email di conferma: le altre email del sito tengono
`email_intestazione` ed `email_firma` (che un modulo del sito può impostare).

### 15.7 La base dei contatti come Brevo (dalla 2.8.0)

Fase 1 del piano di `docs-sviluppo/brevo-confronto.md` (richiesta di Danilo: "La gestione del menu newsletter e form deve
lavorare esattamente come Brevo"). Motore in `includes/newsletter-contatti.php` (caricato in fondo a `includes/newsletter.php`);
schema, migrazione, lista nera e storia in `includes/newsletter.php` (stesso file di chi li usa: niente problemi di opcache).

**Menu** (`admin/includes/admin-header.php:31`): Newsletter e form > Contatti (`newsletter-contatti.php`, con la scheda Liste),
Campagne email, Form, Invii dei form, Flussi automatici, Statistiche, Impostazioni (`newsletter.php?scheda=impostazioni`),
Attributi dei contatti (`newsletter-attributi.php`). Le voci delle fasi dopo (Segmenti, Modelli, Automazioni) arrivano con le
loro pagine. `newsletter.php?scheda=iscritti` e `?scheda=liste` (e `?scarica=iscritti`) rimandano con 302 e `no-store`
alle pagine nuove con gli stessi filtri (`admin/newsletter.php:17`); le loro azioni POST sono passate nelle pagine nuove.

**Modello dei dati** (nessuna tabella rinominata, nessuna colonna tolta; tabelle nuove nell'elenco di `newsletter_schema()`,
`includes/newsletter.php:132`, utf8 a 3 byte, `NOT NULL DEFAULT` espliciti, niente colonne generate):
- `newsletter_iscritti`, colonne nuove (`newsletter_contatti_migra()`, `:251`): `ext_id` (UNIQUE, NULL), `blocco_motivo`,
  `blocco_at`, `opt_in`, `doi_at`, `tracciamento` (per la fase 9, oggi sempre 1). Stato nuovo `bloccato` (a mano, da
  importazione, 5 soft bounce). "In lista nera email" = `stato IN ('uscito','rimbalzato','spam','bloccato')` (`NL_BLOCCATI`, `:243`).
- `newsletter_attributi` (nome UNIQUE in maiuscolo, etichetta, categoria `normal`/`category`/`calculated`/`global`, tipo,
  `sistema`, `colonna` per quelli di sistema, `formula`, ordine), `newsletter_attributi_opzioni` (valore numerico ed etichetta
  di categoria e scelta multipla), `newsletter_valori` (una riga per valore, chiave contatto + attributo + opzione, con
  `v_testo`, `v_num` DECIMAL(20,6), `v_data`, e gli indici per i segmenti della fase 3).
- `newsletter_cartelle`, `newsletter_liste.cartella_id`; `newsletter_liste_uscite` (disiscritto da una sola lista: le
  campagne di quella lista lo saltano, `newsletter_sql_uscite_lista()`, `:433`; la scrive il centro preferenze della fase 9).
- `newsletter_storia` (cronologia: creato, iscrizione, form, doi, lista_aggiunto, lista_tolto, attributo, email, bloccato,
  sbloccato, soft_bounce, importato, flusso_email) e `newsletter_consensi` (registro del consenso: in più rispetto a Brevo).

**Migrazione** (una volta, impostazione `newsletter_contatti_migrati`): `uscito` -> `unsubscribedViaEmail`, `rimbalzato` ->
`hardBounce`, `spam` -> `contactFlaggedAsSpam`, con la data di prima (`uscito_at`, `rimbalzato_at`); `doi_at` dal consenso di
chi è arrivato da modulo o form (non si distingue chi è entrato da un form senza conferma: approssimato); `opt_in` per chi
ha un consenso che non è `import`; una riga di `newsletter_consensi` per ogni contatto con `consenso_at`; tutte le liste nella
cartella "Le tue liste". Gli attributi di sistema si scrivono a ogni giro di schema (INSERT IGNORE): EMAIL, NOME
(FIRSTNAME), COGNOME (LASTNAME), EXT_ID, OPT_IN, DOUBLE_OPT-IN, CREATION_DATE, LAST_CHANGED. Le righe di prima senza motivo
mostrano il motivo dallo stato (`newsletter_motivo_testo()`, `newsletter-contatti.php:492`).

**Lista nera** (`includes/newsletter.php`): i motivi con i codici di Brevo in `NL_MOTIVI` (`:232`): codice -> testo e stato.
`newsletter_contatto_blocca($id, $motivo)` (`:312`) cambia insieme stato, motivo, data, coda (saltata) e storia;
`newsletter_contatto_sblocca($id)` (`:329`) torna iscritto, toglie l'impronta di soppressione e scrive la storia.
`newsletter_esci($id, $motivo)` (`:679`, predefinito `unsubscribedViaEmail`; dal webhook `unsubscribedViaApi`,
`includes/consegna.php:864`), `newsletter_rimbalzato()` e `newsletter_segnala_spam()` scrivono anche motivo e storia. La
conferma del doppio opt-in (`newsletter_conferma_esito()`) toglie la lista nera, scrive `doi_at`, `opt_in`, la storia e il
consenso. Togliere dalla lista nera si fa solo dalla scheda, uno alla volta, con la spunta del consenso
(`admin/newsletter-contatto.php`, azione `sblocca`); cambiare l'email di un contatto in lista nera lo fa tornare iscritto
(`newsletter_contatto_colonna()`, `newsletter-contatti.php:412`).

**Soft bounce** (`newsletter_soft_bounce()`, `includes/newsletter.php:348`): `errori_di_fila` conta ogni tentativo come
prima (segnalato da 3); la regola di Brevo, 5 soft bounce di fila = lista nera con motivo `softBounce`, conta i **messaggi**:
l'avviso del servizio di invio (`includes/consegna.php:857`, con la copia di riserva per opcache) o il terzo e ultimo
tentativo della coda (`newsletter_esito_invio($id, $ultimoTentativo)`, `:701`); "di fila" vuol dire dopo l'ultima email
partita bene (coda o automazione) o l'ultima uscita dalla lista nera. Le email dei flussi riprovano a ogni giro e non contano.
Nuovi tentativi per 36 ore come Brevo: non fatti (la coda tiene i suoi tre tentativi).

**Attributi** (`includes/newsletter-contatti.php`): tipi `NL_ATTR_TIPI` (`:21`); `newsletter_attributo_crea()` (`:78`) con le
regole di Brevo: nome in maiuscolo (`newsletter_attributo_nome()`, `:60`, accenti tolti, spazi in trattini bassi), unico e
non uguale a FIRSTNAME, LASTNAME, SMS...; al massimo 200 attributi, 50 calcolati, 30 globali; opzioni al massimo 200
caratteri; nome e tipo non si cambiano (`newsletter_attributo_modifica()`, `:126`, cambia solo etichetta e opzioni;
togliere un'opzione la toglie a chi l'aveva); `newsletter_attributo_elimina()` (`:151`) toglie i valori a tutti. Le emoji
diventano entità (`newsletter_testo_pulito()`, `:69`) e tornano caratteri con `newsletter_4byte_fuori()` prima dell'escape.
Valori: `newsletter_valori_di($ids)` (`:266`, sistema dalle colonne, calcolati dalla formula), `newsletter_valore_leggi()`
(`:324`, ritorna "ok" falso per un valore del tipo sbagliato), `newsletter_valori_salva()` (`:373`, il tipo sbagliato si
ignora senza errore, come Brevo), `newsletter_valore_testo()` (`:308`, in parole: Sì/No, data italiana, etichetta).

**Formule** (attributi calcolati e globali, decisione di Danilo del 1 novembre 2026): `newsletter_formula_analizza()` (`:180`)
legge solo `COUNT[FONTE]`, `COUNT[FONTE,FONTE,<|>|<=|>=,NOW(-N)]` e `SUM[ATTRIBUTO]` (regex: niente altro arriva all'SQL);
fonti `NL_FORMULA_FONTI` (`:30`): READS (aperture), CLICKS, SENT (coda più email dei flussi), FORMS (invii dei form con la sua
email), CONTACTS (solo globali: iscritti). SUM solo nei globali e solo su un attributo Numero. Si calcolano a ogni lettura
(`newsletter_formula_valore()`, `:206`): è il "ricorrente" di Brevo. I globali si vedono in Attributi > Valori globali e nei
segnaposti; non c'è una dashboard dei valori globali.

**Contatti** (`admin/newsletter-contatti.php`): colonne fisse `NL_COLONNE_FISSE` (`newsletter-contatti.php:35`) più quelle
scelte (impostazione `newsletter_colonne_u<id utente>`, finestra "Personalizza le colonne" con SortableJS e le frecce per la
tastiera); filtri `newsletter_contatti_filtro()` (`:506`: cerca, lista, stato con gli stati di prima ancora validi negli
indirizzi); 50/100/500 per pagina, ordine per data di creazione decrescente; azioni di gruppo (`azione=gruppo`, `:90`) sulle
righe spuntate o su tutti quelli del filtro (`tutti`, `newsletter_contatti_ids()`, `:536`): aggiungi, togli
(`newsletter_togli_da_liste()`, `:544`), sposta (dalla lista caricata), metti in lista nera, modifica
(`newsletter_modifica_in_massa()`, `:564`: solo data, sì/no, categoria), esporta (CSV con `sicurezza_csv_riga()`), elimina
definitivamente (`newsletter_contatti_elimina()`, `:579`: è "Cancella dati", tiene l'impronta, scelta di plumeCMS confermata da
Danilo). Riquadri Importa contatti (fino alla 2.8.0; dalla 2.9.0 è la pagina a passi, capitolo 15.8) e
Metti in lista nera degli indirizzi (`:151`, crea il contatto se non c'è). Scheda Liste: cartelle e liste
(`newsletter_cartelle()`, `:616`; `newsletter_cartella_elimina()`, `:648`, elimina le liste ma non i contatti, rifiuta una
cartella con una lista di sistema, `newsletter_liste_di_sistema()`, `:607`; limiti 300 e 300).

**Scheda del contatto** (`admin/newsletter-contatto.php`): Panoramica (attributi modificabili, statistiche dei 90 giorni
`newsletter_contatto_statistiche()`, `:692`, form compilati, attività recente), Cronologia
(`newsletter_contatto_cronologia()`, `:720`: coda, eventi, invii dei form, storia, con filtro per tipo, periodo e testo),
Liste, Preferenze (lista nera); `?nuovo=1` è "Crea un contatto" (`newsletter_contatto_crea()`, `:462`: email obbligatoria, un
doppione apre il contatto che c'è, lista nuova dal modulo). JavaScript in `admin/assets/contatti.js` (`data-apri-dialogo`,
`data-nlc-azione`, `data-nlc-ordina`, `data-nla-tipo`; nessun gestore in linea). Stili `.nlx-*`, `.nlk-*`, `.nll-*`,
`.nl-dialogo` in fondo ad `admin.css` (non `.nlc-*`: lo usa già la pagina della campagna).

**Segnaposti** (`newsletter_segnaposti()`, `:785`, chiamata da `newsletter_personalizza()`, `includes/newsletter.php:878`,
con `function_exists`): `{{ contact.NOME }}`, filtri `upper`, `lower`, `capfirst`, `default:"testo"` (anche con gli spazi di
Brevo, `default : 'x'`). FIRSTNAME e LASTNAME valgono come NOME e COGNOME; categoria e scelta multipla escono come etichette,
le date in italiano, i globali calcolati. Nell'HTML il valore entra con `h()`. `{nome}` resta per le campagne di prima.
Le email dei flussi lasciano una riga `flusso_email` nella storia (`includes/newsletter-flussi.php:924`).

Prove: `prove/prova-contatti-brevo.js` con `prove/contatti-cli.php` (con `MIGRATO=1` anche la migrazione di un sito 2.7.0
preparato con `contatti-cli.php semina`); attacchi: gruppo 29 di `tools/sicurezza-prova.js`.

Prove: `prove/prova-email-conferma.js` (con `prove/email-conferma-cli.php` e il server di posta finto): senza modifiche
uguale al byte alla 2.6.0 per un form e per il modulo della newsletter, ogni parte cambiata, segnaposti, emoji in oggetto e
testo, codice cattivo, anteprima, prova, link vero che conferma, redattore, 390 px. Attacchi: gruppo 28 di
`tools/sicurezza-prova.js`.

### 15.8 Importazione, esportazione e lista dei test come Brevo (dalla 2.9.0)

Fase 2 del piano di `docs-sviluppo/brevo-confronto.md` (capitoli 1.3, 1.5, 3.5). Motore in `includes/newsletter-importa.php`
(caricato in fondo a `includes/newsletter.php`, e caricato a mano dalle pagine e dal cron: copia di riserva per opcache);
pagina `admin/newsletter-importa.php`; finestra Esporta e riquadro Esportazioni in `admin/newsletter-contatti.php`; Lista dei
test in `admin/newsletter.php` (Impostazioni) e nella prova di `admin/newsletter-campagna.php`. JavaScript in
`admin/assets/contatti.js` (`data-nli-*`, `data-nlx-origine`, `data-nl-giro`), stili `.nli-*` in fondo ad `admin.css`.

**Tabelle** (`newsletter_importa_schema()`, `newsletter-importa.php:40`, con la sua impronta `newsletter_importa_impronta`;
utf8, `NOT NULL DEFAULT`, niente colonne generate; nessuna tabella di prima cambiata):
- `newsletter_importazioni`: origine (`file`, `incolla`), nome del file, file riscritto (`file`), `intestazione`, `anteprima`
  (prime 5 righe, emoji come entità), `mappatura` (JSON: per colonna un nome di attributo, `''` = non importare,
  `_CONSENSO` = data del consenso), `opzioni` (aggiorna, vuote, nera, flussi), `liste`, `passo` (2-4 mentre è in bozza),
  `stato` (`bozza`, `pronta`, `in_corso`, `finita`, `errore`), `righe`, `fatte`, `posizione` (byte nel file), i contatori
  `nuovi`, `aggiornati`, `invariati`, `doppioni`, `errori`, `dettagli` (JSON: bloccati, soppressi, non valide, EXT_ID doppi),
  `scarti_file`.
- `newsletter_importa_viste`: (importazione, md5 dell'email) delle righe già lette: i doppioni del file si riconoscono anche
  fra un giro e l'altro; si svuota alla fine.
- `newsletter_esportazioni`: descrizione, `filtro` (JSON), `colonne`, `separatore`, `stato`, `file`, `righe`, `totale`,
  `ultimo_id` (il giro riprende da lì), `scade` (7 giorni).
- Impostazioni nuove: `newsletter_cartella_file` (nome a caso della cartella privata), `newsletter_lista_test` (JSON degli id).

**Cartella privata** (`newsletter_dir_privata()`, `:106`): `uploads/contatti-<16 esadecimali>/` con `.htaccess` "Require all
denied" e `index.html` vuoto, come i file riservati (`scaricabili_dir_riservati()`); su Nginx la regola nuova in
`install/esempi/nginx.txt`. I nomi dei file si accettano solo se sono dei nostri (`newsletter_file_privato()`, `:120`:
`imp-`, `scarti-`, `esp-` più id e 12 esadecimali); si scaricano solo dal pannello (`newsletter_file_scarica()`, `:972`,
`no-store`, `nosniff`).

**Passo 1, Carica** (`newsletter_importa_prepara()`, `:311`): CSV e TXT (`newsletter_csv_righe()`, `:136`): se una riga non è
UTF-8 il file si riscrive da Windows-1252; separatore indovinato sulla prima riga fra tabulazione, punto e virgola,
virgola e barra; `fgetcsv` con l'escape vuoto (RFC 4180). XLSX (`newsletter_xlsx_righe()`, `:199`) con `ZipArchive` e
`XMLReader`, senza librerie: nomi interni controllati (niente `/` iniziale, `..`, `\`, `:`, caratteri di controllo); tetto
di 40 MB decompressi sia sui byte dichiarati sia su quelli letti davvero (`newsletter_zip_leggi()`, `:171`); una parte con
`<!DOCTYPE` o `<!ENTITY` si rifiuta (niente DTD, niente entità esterne; `LIBXML_NONET`); primo foglio dai rel di
`workbook.xml`; testi condivisi, testi in linea, testo ricco, `t="b"`, risultati delle formule; i numeri con un formato di
data (14-22, 27-36, 45-47, 50-58 o un formato scritto con d, m, y, h) diventano `AAAA-MM-GG` (anche `date1904`). Ogni riga
diventa una riga JSON nella cartella privata; righe vuote saltate, al massimo 200 colonne e 1000 caratteri per cella. Senza
intestazioni (una colonna di indirizzi incollata) le colonne si chiamano "Colonna N" e la prima riga è un dato.
Errori in parole sotto il campo (sessione `nli_errore`), con la `.xls` vecchia detta per nome.

**Passo 2, Abbina** (`newsletter_importa_abbina()`, `:387`, `newsletter_importa_salva_abbinamento()`, `:410`): si importano
solo gli attributi scrivibili (`newsletter_importa_attributi()`, `:381`: niente calcolati, globali, DOUBLE_OPT-IN,
CREATION_DATE, LAST_CHANGED); abbinamento da solo per nome (con `newsletter_attributo_nome()`), FIRSTNAME e LASTNAME, i
sinonimi di `NL_IMP_SINONIMI` (`:33`, anche i nomi della colonna del consenso della 2.0.0), e la colonna con le email nelle
prime righe se nessuna si chiama EMAIL. "Crea un nuovo attributo" crea subito l'attributo (Testo, Numero, Data, Sì/No) con
`newsletter_attributo_crea()`. Un attributo su due colonne è un errore sotto la colonna; senza EMAIL non si va avanti.

**Passo 3** (`newsletter_importa_salva_liste()`, `:440`): liste esistenti o una nuova con `newsletter_lista_crea()`; almeno una.
**Passo 4** (`newsletter_importa_conferma()`, `:456`): consenso obbligatorio (anche lato server), "celle vuote" solo con
"aggiorna" (come Brevo); lo stato diventa `pronta` e la pagina fa subito un giro di 8 secondi.

**Lavoro a giri** (`newsletter_lavori_giro()`, `:746`, con `GET_LOCK`): dalla pagina (`azione=giro`, JSON, token dal campo o
da `X-CSRF`, `session_write_close`) e dal cron (`includes/cron.php:172`, 15 secondi a passata). `newsletter_importa_giro()`
(`:476`) legge dal byte `posizione`, a pezzi di 200 righe (`newsletter_importa_pezzo()`, `:552`) dentro una transazione con
contatori e posizione: per pezzo una domanda per le email già viste, una per la lista di soppressione, una per i contatti
che ci sono già e una per i loro valori (`newsletter_valori_di()` con i soli nomi abbinati); storia e consensi a blocchi.
Regole: email non valida e chi ha cancellato i dati = errori; doppione = vale la prima riga; nuovo = `attivo`, fonte e
consenso `import`, data del consenso dalla colonna `_CONSENSO`, riga `importato` nella storia e `import` nel registro del
consenso; chi c'è già e non è in lista nera entra nelle liste e, con "aggiorna", prende i valori
(`newsletter_importa_valori()`, `:688`: il tipo sbagliato si ignora, le celle vuote cancellano solo con l'opzione, EXT_ID
già di un altro non si scrive); chi è in lista nera resta com'è e fuori dalle liste (invariato); "lista nera" usa
`newsletter_contatto_blocca($id, 'import')`. L'apostrofo che l'esportazione mette davanti a `= + - @` si toglie
(`newsletter_importa_valore()`, `:547`): andata e ritorno uguali. Le righe scartate vanno nel CSV degli scarti
(`newsletter_importa_scarti()`, `:527`: RIGA, MOTIVO e le celle). Alla fine il file caricato si cancella.
`newsletter_importa_csv()` (`:794`) resta come involucro (dentro un `if (!function_exists())`, per opcache) con i numeri di
prima. Pulizia (`newsletter_importa_pulizia()`, `:770`, da `newsletter_pulizia()` e all'apertura della pagina): bozze dopo un
giorno, scarti ed esportazioni dopo 7.

**Esportazione** (`newsletter_esporta_crea()`, `:880`, `newsletter_esporta_giro()`, `:919`): filtri
(`newsletter_esporta_where()`, `:834`): `contatti` (il filtro della pagina, `newsletter_contatti_filtro()`), `ids`, `lista`
(`NL_ESP_LISTA`: tutti, iscritti, in lista nera, usciti da quella lista con `newsletter_liste_uscite`), `campagna`
(`NL_ESP_CAMPAGNA`: aperto e cliccato da `newsletter_eventi`, non aperto e non cliccato fra chi ha la riga `inviata` in coda,
disiscritti, rimbalzi da eventi o coda in errore). Colonne nell'ordine degli attributi più ISCRITTO, LISTA_NERA, MOTIVO,
LISTE (`NL_ESP_EXTRA`); EMAIL sempre; date `AAAA-MM-GG`, il resto in parole (`newsletter_valore_testo()`); ogni riga passa da
`sicurezza_csv_riga()`. A pezzi di 500 per id; un file che finisce in 8 secondi esce subito (`nlc_esporta_avvia()`,
`admin/newsletter-contatti.php:53`), uno più lungo va a giri e il riquadro Esportazioni chiede i giri da solo
(`azione=esporta_giro`, `:81`) e si ricarica; `?esportazione=<id>` scarica (`:74`). `?scarica=tutti` (il collegamento della
2.8.0) fa tutto in una richiesta. Le azioni di gruppo senza i campi della finestra (la 2.8.0, gli attacchi del gruppo 29)
esportano tutte le colonne con il punto e virgola.

**Lista dei test** (`newsletter_lista_test()`, `:989`, `newsletter_lista_test_salva()`, `:1001`): un indirizzo per riga, al
massimo 20 (`NL_TEST_MASSIMO`), ognuno già un contatto (regole di Brevo), altrimenti non si salva niente. Nella campagna
(`admin/newsletter-campagna.php:45`) la prova va ai contatti spuntati della lista, ognuno con i suoi dati
(`newsletter_email($c, $contatto, [])`: con la traccia vuota niente pixel né link tracciati, quindi niente statistiche), più
l'indirizzo scritto a mano come prima (dati di prova, "Giulia"); "Aggiungi questo indirizzo alla lista dei test"
(`newsletter_lista_test_aggiungi()`, `:1017`).

**Fatto diverso da Brevo, scelte di plumeCMS**: chi è in lista nera non torna iscritto con l'importazione (Brevo non dice
cosa fa: [non trovato] nel capitolo 5 del piano); "Fai partire i flussi" è un'opzione del passo 4 (c'era dalla 2.0.0); il
consenso è una casella obbligatoria anche per i file (in Brevo è sicura solo nelle importazioni da altri servizi); il
resoconto conta a parte i doppioni del file; "manda l'esportazione per email", i gruppi di consenso e il prefisso
telefonico non ci sono (SMS e gruppi fuori da questa fase).

Prove: `prove/prova-importa.js` con `prove/importa-cli.php` (XLSX come Excel e LibreOffice e i quattro XLSX cattivi);
`prova-newsletter-difetti.js` e `prova-contatti-brevo.js` adattati ai passi nuovi e alla finestra Esporta; attacchi: gruppo
30 di `tools/sicurezza-prova.js`.

### 15.9 Segmenti, Aggiungi filtro e viste come Brevo (dalla 2.10.0)

Fase 3 del piano di `docs-sviluppo/brevo-confronto.md` (capitoli 1.4, 3.4, la riga "Filtri" e "Viste" del capitolo 1.1).
Motore in `includes/newsletter-segmenti.php` (caricato in fondo a `includes/newsletter.php` e caricato a mano, con
`require_once`, da chi lo chiama da un altro file: `newsletter_contatti_filtro()`, `newsletter_esporta_where()`,
`newsletter_flusso_condizione()`, le due pagine; è la copia di riserva per opcache); pagina `admin/newsletter-segmenti.php`
(scheda Segmenti accanto a Contatti e Liste; `newsletter-contatti.php?scheda=segmenti` rimanda con 302 e `no-store`);
"Aggiungi filtro", "Salva come segmento", viste ed esportazione di un segmento in `admin/newsletter-contatti.php`;
costruttore delle condizioni in `admin/assets/segmenti.js`; stili `.nls-*`, `.nlv-*`, `.nlx-riquadro-filtro` in fondo ad
`admin.css`. Solo amministratore (`require_admin()` sulle due pagine).

**Tabelle** (`newsletter_segmenti_schema()`, `newsletter-segmenti.php:50`, impronta `newsletter_segmenti_impronta`; utf8,
`NOT NULL DEFAULT`, niente colonne generate; nessuna tabella di prima cambiata, solo indici in più):
- `newsletter_segmenti`: `nome` (UNIQUE), `cartella_id`, `condizioni` (JSON delle regole già pulite, fuori ASCII come
  `\uXXXX`), `conteggio` e `contato_at` (il numero per l'elenco), `utente_id`, `creato`, `aggiornato`. Il segmento non
  salva i membri: le regole diventano SQL ogni volta ("si aggiorna da solo", come Brevo).
- `newsletter_segmenti_cartelle`: le categorie (`nome` UNIQUE).
- `newsletter_viste`: `utente_id`, `nome`, `filtro` (JSON dei filtri della pagina: cerca, stato, lista, segmento, f, per),
  `colonne` (JSON dei nomi degli attributi), `ordine` (per dopo; oggi l'ordine è sempre per data di creazione).
- Indici nuovi per i conteggi (`:91`): `newsletter_eventi.seg_contatto (iscritto_id, tipo, creato)`,
  `newsletter_coda.seg_contatto (iscritto_id, stato, inviata_at)`, `newsletter_storia.seg_tipo (contatto_id, tipo, creato)`,
  `moduli_invii.seg_email (email, creato)`; si aggiungono solo se mancano (`SHOW INDEX`).

**Le regole** (JSON): `{"op": "e"|"o", "gruppi": [{"op": "e"|"o", "condizioni": [...]}]}`, due livelli: i gruppi si
uniscono con `op`, le condizioni di un gruppo con il `op` del gruppo (la forma dei gruppi annidati di Brevo non si è trovata,
capitolo 5 del piano). Una condizione: `campo`, `op` e solo le chiavi che servono (`valore`, `valore2`, `n`, `unita`,
`campagna`, `non`). Campi (`newsletter_regole_campi()`, `:113`): `attr:NOME` per ogni attributo (di sistema sulla sua
colonna, `NL_SEG_COLONNE`, `:48`; gli altri su `newsletter_valori`; calcolati e globali esclusi: si calcolano contatto per
contatto), `stato`, `lista_nera` (con il motivo, anche per le righe senza motivo di prima della 2.8.0, come il filtro della
pagina), `iscritto_da`, `lista`, `nessuna_lista`, `segmento`, `email` (aperta, non aperta, cliccata, non cliccata,
ricevuta, non ricevuta; `campagna` 0 qualsiasi campagna, -1 l'ultima partita, -2 qualsiasi email anche delle automazioni, N
una campagna; periodo `n` + `unita`), `link` (un indirizzo preciso cliccato, con la campagna), `attivita` (aperture e clic
negli ultimi N giorni; con `tutti` qualunque evento, come i bivi di prima), `form` (inviato, uno o qualsiasi, nel periodo),
e solo nei flussi `flusso_email` (aperta o cliccata un'email del flusso). Operatori per tipo in `NL_SEG_OPERATORI` (`:28`):
testo (è, non è, contiene, non contiene, comincia, finisce, vuoto, pieno), numero (uguale, diverso, maggiore, minore, fra),
data (il, prima, dopo, fra, negli ultimi N, più di N fa, nei prossimi N, in questo mese; unità `NL_SEG_UNITA`, `:26`:
giorni, settimane, mesi, come le date dinamiche di Brevo), sì/no (sì; no = 0 o non impostato), categoria (uno di, nessuno
di), scelta multipla (almeno una, tutte, nessuna di). Le negazioni sono operatori (come Brevo: "è / non è", "ha fatto / non
ha fatto"); in più ogni condizione può avere `non` (lo usano i bivi). "Non è", "non contiene", "diverso" valgono anche per
chi non ha il valore. "Non aperta" di una campagna precisa o dell'ultima = l'ha ricevuta e non l'ha aperta; di qualsiasi
campagna = nessuna apertura nel periodo (il modello "Contatti non coinvolti" di Brevo).

**Sicurezza** (`newsletter_regole_valida()`, `:173`, e `newsletter_condizione_valida()`, `:208`): JSON (anche in base64url
per gli indirizzi) al massimo 200000 caratteri e profondità 7 (un annidamento più fondo non si legge); campo e operatore solo
dagli elenchi chiusi, altrimenti errore in parole ("Condizione 2 del gruppo 1: scegli la condizione."); al massimo 100
condizioni (`NL_SEG_CONDIZIONI_MAX`, Brevo) e 20 gruppi; id come interi (`nl_seg_int()`, `:205`); testi con
`newsletter_testo_pulito()` (emoji come entità, come sono salvati), numeri e date con `newsletter_valore_leggi()`; motivi
solo da `NL_MOTIVI`. `newsletter_regole_where()` (`:315`) scrive l'SQL solo con le colonne e le tabelle scritte nel codice
(`nl_seg_condizione_sql()`, `:356`; `nl_seg_attributo_sql()`, `:431`; `nl_seg_confronto()`, `:482`) e tutti i valori come
parametri `?` in `$par`, nell'ordine in cui compaiono nell'SQL (attenzione: una parte scritta prima nell'SQL deve mettere i
suoi parametri prima; "non aperta" di una campagna li mette prima, `:404`). `LIKE` con `addcslashes` e `ESCAPE`; le unità
diventano DAY, WEEK, MONTH da un elenco. Segmenti dentro segmenti: fino a 5 livelli (`NL_SEG_PROFONDITA`), un giro si
rifiuta al salvataggio (`newsletter_segmento_contiene()`, `:616`) e, se arrivasse lo stesso, vale "nessuno" (`_visti`).

**Uso**: `newsletter_regole_conta()` (`:512`, numero e millisecondi), `newsletter_regole_vale()` (`:523`, un contatto),
`newsletter_segmento_where()` (`:630`), `newsletter_regole_testo()` (`:559`, le regole in parole). Segmenti:
`newsletter_segmento_salva()` (`:641`: nome unico, almeno una condizione, categoria esistente o nuova, conteggio subito; al
massimo 300 segmenti, scelta di plumeCMS: Brevo non dice il suo limite), `newsletter_segmento_conta()` (`:671`),
`newsletter_segmento_usato()` (`:682`: altri segmenti e bivi dei flussi) e `newsletter_segmento_elimina()` (`:697`, rifiuta un
segmento usato), `newsletter_segmento_in_lista()` (`:708`: lista nuova con i membri di oggi, con un `INSERT ... SELECT` e la
riga `lista_aggiunto` nella storia di ognuno, senza far partire i flussi della lista), categorie (`:726`-`:745`; eliminare una
categoria lascia i segmenti senza categoria), modelli (`newsletter_segmenti_modelli()`, `:754`: i dieci modelli; quello di
Brevo trovato è "Unengaged email contacts", gli altri sono di plumeCMS), viste (`newsletter_viste()`, `:784`,
`newsletter_vista_salva()`, `:801`: per utente, al massimo 30, lo stesso nome sovrascrive, "Tutti i contatti" è riservato).

**Pagina Segmenti** (`admin/newsletter-segmenti.php`): elenco per categoria con ricerca e filtro della categoria; i numeri
vecchi di più di dieci minuti si ricalcolano aprendo la pagina per al massimo tre secondi, il resto con "Aggiorna i
numeri" (`:64`). `?nuovo=1`: Crea da zero o un modello; poi `?nuovo=1&modello=<chiave>` o `?id=`: le condizioni, il numero
(azione `conta`, `:22`, JSON, token dal campo o da `X-CSRF`, `session_write_close`), nome e categoria; Salva (`:39`): con un
errore la pagina torna con quello che si era scritto (`nls_bozza` in sessione, solo per lo stesso segmento). Da un segmento:
Vedi i contatti, Esporta (`newsletter-contatti.php?segmento=<id>&esporta=1` apre la finestra Esporta con il segmento
scelto), Crea una lista (finestra), Elimina (con `data-conferma`).

**Pagina Contatti** (`admin/newsletter-contatti.php`): `newsletter_contatti_filtro()` (`includes/newsletter-contatti.php:508`)
legge anche `segmento` e `f` (le regole in base64url, riscritte pulite; un `f` che non si legge si scarta e la pagina lo
dice, `errore_filtro`): elenco, conteggio, "tutti i contatti del filtro" ed esportazione del filtro vedono le stesse
condizioni. Tendina "Carica un segmento"; riquadro "Aggiungi filtro" (`<details id="nlx-filtro">`, `:333`: il costruttore
scrive nel campo `f` del suo modulo GET; "Applica il filtro", "Salva come segmento" con la finestra `#nls-salva`, `:498`,
che manda a `newsletter-segmenti.php` con `torna=contatti`; "Togli il filtro"); il filtro in parole sopra la tabella. Viste:
schede `.nlv-viste` sopra la tabella ("Tutti i contatti" fissa), `?vista=<id>` (`:264`) prende filtri e colonne della vista;
finestra `#nlv-salva` (`:512`, azioni `vista_salva` e `vista_elimina`, `:153`). Esporta: origine "Un segmento" (`:390`;
`newsletter_esporta_where()` caso `segmento`, `includes/newsletter-importa.php:840`, ricalcolato a ogni giro; descrizione
"Segmento «nome»", `:899`). La definizione per il costruttore (campi, operatori, liste, segmenti, campagne partite, form,
motivi, gli ultimi 200 link cliccati) esce in `<script type="application/json" id="nls-def">` (`newsletter_regole_definizione()`,
`newsletter-segmenti.php:138`): JSON, non codice, quindi la CSP a nonce non c'entra.

**Costruttore** (`admin/assets/segmenti.js`): `data-nls-editor="#campo"` legge e scrive il campo nascosto (JSON in
base64url); gruppi in `fieldset` con la legenda, ogni condizione con etichette vere ("Condizione 2: su cosa", "Come",
"Valore"...), caselle per liste, opzioni e motivi, "+ E/O: aggiungi una condizione", "+ Aggiungi un gruppo", "Togli" con
`aria-label`; dopo ogni cambio il fuoco torna sul campo cambiato; il numero (`[data-nls-numero]`, `role="status"`) si chiede
mezzo secondo dopo l'ultimo cambio e dice cosa manca se una condizione è incompleta. `data-nls-copia` copia le regole nel
modulo di "Salva come segmento" all'invio.

**Flussi**: i bivi usano il motore. `newsletter_flusso_condizione()` (`includes/newsletter-flussi.php:722`) chiama
`newsletter_bivio_vale()` (`newsletter-segmenti.php:548`), che traduce le nove condizioni di prima
(`newsletter_bivio_regole()`, `:532`: in_lista e non_in_lista -> `lista`; aperta, non_aperta, cliccata, non_cliccata ->
`flusso_email` con il flusso nel contesto; attivo e inattivo -> `attivita` con `tutti`; iscritto_da -> `iscritto_da`) e le
fa passare dal controllo come tutte le altre. I dati dei flussi non cambiano (stessa `condizione` e `valore` nel grafo):
nessuna migrazione, stesso esito (lista, email o segmento non scelti: "sta in", "ha aperto", "ha cliccato" no, le negative
sì, come prima; una condizione sconosciuta vale, come prima). Il codice di prima resta sotto, per quando opcache dà per
qualche secondo il file vecchio. Condizioni nuove del bivio: `in_segmento` e `non_in_segmento` (`NL_CONDIZIONI`,
`newsletter-flussi.php:28`; tendina in `admin/assets/flussi.js:330`, segmenti in `FL_DATI.segmenti`,
`admin/newsletter-flussi.php:130`). Il bivio a più rami con le condizioni dei segmenti è della fase 8.

**Campagne**: non cambiano in questa fase (il piano mette destinatari ed esclusioni nella fase 4, 2.11.0: fatto, capitolo 15.10). Il motore è pronto:
`newsletter_segmento_where()` e `newsletter_regole_where()` danno la condizione da mettere nell'`INSERT ... SELECT` della coda
(`newsletter_avvia_programmate()`, `includes/newsletter.php`) e nel conteggio dei destinatari.

**Fatto diverso da Brevo, scelte di plumeCMS**: limiti di 300 segmenti, 100 categorie e 30 viste per utente (quelli di
Brevo non si sono trovati); le viste sono per utente (in Brevo non si è trovato se sono dell'account); "I miei contatti" non
c'è (è del CRM); le condizioni su sito ed e-commerce non ci sono; con il tracciamento anonimo (fase 9) le condizioni su
aperture e clic andranno spente; "sì/no: no" comprende chi non ha il valore.

Prove: `prove/prova-segmenti.js` con `prove/segmenti-cli.php` (5000 contatti finti con attributi dei sei tipi, stati e
motivi, liste, tre campagne con coda, aperture e clic, email dei flussi, invii dei form; ogni condizione contro una query
scritta a mano, i dieci modelli, cento condizioni, i tempi; i bivi della 2.9.0 copiati com'erano contro il motore su 600
contatti per condizione); attacchi: gruppo 31 di `tools/sicurezza-prova.js`.

### 15.10 Campagne a riepilogo, disiscrizione con il questionario, centro preferenze (dalla 2.11.0)

Fase 4 del piano di `docs-sviluppo/brevo-confronto.md` (capitoli 1.9, 1.10, 1.11, 1.15, 3.6, 3.8), con la pagina di
disiscrizione e il centro preferenze anticipati dalla fase 9 (decisione 5 di Danilo). Motore in
`includes/newsletter-campagne.php` (caricato in fondo a `includes/newsletter.php` e, con `require_once`, da ogni file che ne
chiama le funzioni: le due pagine delle campagne, Statistiche, `sito/index.php`; un file nuovo non è mai vecchio in opcache).
`includes/newsletter.php` lo chiama solo dietro `function_exists` (`newsletter_email()`, `newsletter_avvia_programmate()`,
`newsletter_invia_giro()`). Solo amministratore, come prima (`require_admin()` sulle pagine). Nel testo per chi usa il
pannello non c'è nessun paragone con Brevo.

**Dati** (`newsletter_campagne_schema()`, `newsletter-campagne.php:58`, impronta `newsletter_campagne_impronta`; colonne
`NOT NULL DEFAULT` con i valori che rifanno il comportamento di prima, niente colonne generate, nessuna tabella rinominata):
- `newsletter_campagne`, colonne nuove: `nome` (interno), `tipo` (`normale`; l'A/B è della fase 6), `editor` (`semplice` o
  `html`; il `blocchi` è della fase 5), `html`, `testo` e `testo_modificato` (testo semplice scritto a mano), `segmenti`,
  `escludi_liste`, `escludi_segmenti` (id separati da virgole), `non_coinvolti`, `rispondi_a`, `campo_a`, `tag`, `utm` e
  `utm_campagna`, `link_web` e `link_preferenze` (0 per le campagne di prima, 1 per quelle create con "Crea una campagna"),
  `lotti` (1-10) e `lotti_intervallo` (minuti), `archiviata`, `padre_id` (Invia ai nuovi contatti), `rinvio_di` e
  `rinvio_fatto` (Rimanda ai non aperti). Stato nuovo: `annullata`. Migrazione una volta (`newsletter_campagne_migrate`):
  `nome = oggetto`. `newsletter_schema()` non rimette più la lista principale nelle campagne senza liste quando c'è la
  colonna `segmenti` (`newsletter.php:226`): una campagna nuova può avere solo segmenti, o niente.
- `newsletter_coda`: `lotto` e `dopo` (l'ora da cui la riga può partire; NULL = subito).
- `newsletter_liste`: `pubblica` (si vede nel centro preferenze); alla prima volta la lista principale è pubblica.
- `newsletter_uscite_motivi` (nuova): `contatto_id`, `campagna_id`, `flusso_id` (dal link firmato, sennò 0), `motivo` (chiave
  di `NL_USCITA_MOTIVI`, `:47`), `altro` (500 caratteri, senza HTML, emoji come entità), `creato`.
- Impostazioni nuove: `newsletter_indirizzo` (indirizzo dell'azienda), `newsletter_pagine` (JSON: testi delle due pagine,
  questionario sì o no, testi dei motivi, attributi del centro preferenze), `coinvolti_giorni` dentro `newsletter_config`.

**Destinatari** (`newsletter_campagna_where()`, `:157`): condizione SQL sui contatti `i` (già `stato = 'attivo'`): (in una
delle liste senza esserne uscito, `newsletter_sql_uscite_lista()`, OPPURE in uno dei segmenti, `newsletter_segmento_where()`)
E in nessuna lista esclusa E in nessun segmento escluso (l'esclusione vince) E, con `non_coinvolti`, non fra chi negli ultimi
`coinvolti_giorni` (30-365, 90 di partenza, Impostazioni) ha ricevuto almeno `NL_NON_COINVOLTI_EMAIL` (3) campagne senza
aperture né clic (il criterio di Brevo non si è trovato, capitolo 5 del piano: scelta di plumeCMS). Liste e segmenti si
tengono solo se esistono (gli id passano da `nl_ids_scelti()`, `:130`, al salvataggio, e di nuovo da `array_intersect` qui);
i valori vanno in `$par` nell'ordine. Ogni contatto una volta sola: è un `WHERE` su `newsletter_iscritti`, e la chiave unica
della coda resta. Figlie: `rinvio_di` = chi ha la riga `inviata` nella coda della madre e nessuna apertura o clic su di
lei; `padre_id` = la condizione della campagna meno chi è nella coda della madre o di un'altra figlia della stessa madre.
`newsletter_campagna_conta()` (`:199`) conta; `newsletter_campagna_avvia()` (`:212`) riempie la coda con un
`INSERT IGNORE ... SELECT ... ORDER BY i.id` e, con i lotti, divide le righe in parti uguali per id e scrive `lotto` e
`dopo` = ora programmata + (lotto - 1) x intervallo; senza liste né segmenti la campagna torna bozza, come prima.
`newsletter_invia_giro()` prende solo le righe con `dopo` passato (`newsletter.php:984`); la campagna resta "In invio" finché
c'è una riga in attesa.

**Riepilogo** (`admin/newsletter-campagna.php`): sezioni `<details class="form-card nlx-riquadro nlr-sez">` Mittente
(`:209`), Destinatari (`:224`), Oggetto (`:262`), Design (`:277`), Impostazioni aggiuntive (`:312`), poi Anteprima e prova
(`:343`) e Programma (`#nlr-invio`, `:373`). Ogni sezione ha un modulo suo (azione `sezione`, `sez`, `:29`) che passa da
`newsletter_campagna_salva_sezione()` (`newsletter-campagne.php:278`: valida e salva solo le colonne di quella sezione; un
errore riapre la sezione con `&apri=`); `newsletter_campagna_sezioni()` (`:254`) dice per ognuna "A posto" o "Da completare"
con la frase di cosa fare (`$testa`, `:123`; segno e parola, non solo colore), e `newsletter_campagna_manca()` (`:273`) le
raccoglie nel riquadro in cima e spegne il pulsante dell'invio; il server rifà il controllo (`case 'invia'`, `:64`). Una
campagna partita mostra le sezioni senza segni e con i campi spenti, più il Report (`#nlr-report`, `:194`: inviate, aperture,
clic, uscite, motivi del questionario). Design: "Usa questo editor" cambia `editor`; passando al codice HTML con la casella
vuota si parte dall'email dell'editor semplice (`nl_camp_html_partenza()`, `:546`, con `{{ unsubscribe }}`); il codice viaggia
in base64 (`campi-protetti.js`, è una `textarea`), al massimo 1,5 MB. Il testo semplice: senza la spunta si rifà dall'HTML a
ogni invio (`posta_testo_da_html()`), con la spunta parte `testo`. Programma: Invia subito (`invio_at = NOW()`, il cron la
prende entro 5 minuti) o giorno e ora nel fuso del sito (`date_default_timezone_get()`, scritto accanto al campo; una data
passata si rifiuta); "Togli la programmazione" torna bozza, "Annulla la campagna" (`newsletter_campagna_annulla()`, `:453`)
la chiude e mette `saltata` le righe in attesa. Anteprima: `?contatto=<email>` usa i dati di quel contatto (senza
tracciamento), Computer o Telefono stringe la cornice (script con nonce); la prova va alla lista dei test e, se scritto, a
un altro indirizzo (con i suoi dati se è un contatto).

**Elenco** (`admin/newsletter.php`): "Crea una campagna" (`#nl-crea`, `:199`; azione `nuova`, `:39`,
`newsletter_campagna_crea()`, `:357`: tipo Normale e nome obbligatorio); viste `NL_CAMP_VISTE` (`:28`, elenco chiuso di
condizioni; Tutte, Bozze, Programmate, In invio, Inviate, Sospese, Annullate, Archiviate, con i numeri,
`newsletter_campagne_conteggi()`); per riga il menu Azioni (`details.nl-azioni`): Apri, Report (`newsletter-statistiche.php?
campagna=`), Duplica (`?duplica=`, `#nl-duplica`, `:213`: le cinque parti di `NL_CAMP_PARTI`, `:39`;
`newsletter_campagna_duplica()`, `:369`), Invia ai nuovi contatti e Rimanda ai non aperti (`?rimanda=`, `#nl-rimanda`,
`:224`; `newsletter_campagna_figlia()`, `:394`, e `newsletter_rinvio_possibile()`, `:424`: solo inviate, una volta sola, mai
per una campagna a lotti né per un rinvio; eliminare la bozza del rinvio lo rende di nuovo possibile), Archivia (solo
inviate, sospese, annullate), Annulla (`data-conferma-pericolo`), Elimina (solo bozza, anche dal server:
`newsletter_campagna_elimina()`, `:443`). L'avviso `#nl-avviso-indirizzo` ricorda l'indirizzo dell'azienda che manca.

**L'email** (`newsletter_email()`, `newsletter.php:852`): le parti in più arrivano da `nl_camp_extra()` (`:504`) e sono ''
quando non servono, così una campagna di prima esce uguale al byte (provato: `prove/prova-campagne-vecchie.js`). Editor
semplice: con `link_web` una riga "Vedi questa email nel browser" sopra la scheda; nel piede, dopo "Esci dalla newsletter",
"Aggiorna le tue preferenze" (con `link_preferenze`) e l'indirizzo dell'azienda (se scritto; righe con `<br>`). Codice HTML
(`nl_camp_html()`, `:524`): via gli `<script>`, `{{ unsubscribe }}`, `{{ mirror }}`, `{{ update_profile }}` diventano i link
(`nl_camp_link_speciali()`, `:516`, i nomi di Brevo; valgono anche nell'editor semplice), poi i segnaposti `{{ contact.X }}`;
se il link di uscita non c'è si aggiunge un piede con lui e l'indirizzo; il testo di anteprima e il link della versione web
vanno subito dopo `<body>`. UTM (`nl_camp_utm()`, `:553`): solo i link verso il sito stesso, senza toccare quelli che hanno
già `utm_source` e i link firmati; prima del tracciamento, che li avvolge. Intestazioni: `rispondi_a` della campagna vince su
quello del mittente e delle impostazioni; il campo "A" (personalizzato con i segnaposti, senza a capo né `< > "`) e il testo
semplice scritto a mano (`nl_camp_testo()`, `:569`: segnaposti, link speciali, link di uscita e indirizzo aggiunti se
mancano) viaggiano nelle intestazioni interne `X-Plume-A` e `X-Plume-Testo` (base64) che `posta_interne()`
(`includes/posta.php:272`) toglie in `posta_invia()` (`:218`) prima di qualunque spedizione: diventano il `To: Nome <a>`
(`posta_to()`, `:294`; anche nel MIME di SES e Mailgun e nel `to.name` di Brevo, `includes/consegna.php`) e la parte
`text/plain` (`posta_mime()`, quarto argomento). `newsletter.php` le scrive solo se `posta_interne` esiste (opcache).
`newsletter_traccia()` lascia diretti anche i link delle preferenze e della versione web (`newsletter-flussi.php:129`).

**Versione web** (`/newsletter-web/?c=&i=&t=`, `sito/index.php:189`; `newsletter_pagina_web()`, `:870`): firma
`newsletter_firma_web()` (HMAC di `web|c|i` con `moduli_segreto()`, 24 caratteri); campagna e contatto devono esistere
(qualunque stato: anche le prove alla lista dei test hanno il link); l'email di quel contatto senza pixel; risposta con
`Cache-Control: no-store`, `X-Robots-Tag: noindex` e `Content-Security-Policy: sandbox ...; default-src 'none'` (l'HTML
incollato non esegue niente sul dominio del sito). Firma sbagliata, di un altro contatto o di un'altra campagna: 404.

**Disiscrizione con il questionario** (`/newsletter-esci/`, `sito/index.php:172`; `newsletter_pagina_esci()`, `:695`): con il
link firmato di prima (`newsletter_token_uscita()`). GET: "Vuoi uscire dalla newsletter?" con il pulsante (i filtri antispam
aprono i link: aprire non fa uscire) e, se ci sono liste pubbliche, il link alle preferenze. POST senza `motivo` (il
pulsante, o l'uscita in un clic di Gmail e Outlook, `List-Unsubscribe-Post`): `newsletter_esci()` con il motivo
`unsubscribedViaEmail`, l'uscita contata per la campagna se il link è firmato (`newsletter_uscita_registra()`), poi la
pagina "Fatto. Sei fuori." con il questionario (`.modulo` del sito: stessi stili e temi del modulo della newsletter).
POST con `motivo`: `newsletter_uscita_motivo_salva()` (`:642`): solo un motivo dell'elenco, solo per un contatto già in
lista nera, uno per uscita (una risposta nuova prende il posto della prima), `altro` solo con il motivo "Altro"; campagna o
flusso solo dal link firmato (`newsletter_uscita_traccia()`, `:667`). I cinque motivi (testi di Brevo non trovati,
capitolo 5: questi sono di plumeCMS): Non mi interessa più, Ricevo troppe email, Non mi sono mai iscritto, I contenuti non
sono adatti a me, Altro. Statistiche: `newsletter_uscite_motivi_conta()` (`:657`) nel riquadro `#st-motivi` di
`admin/newsletter-statistiche.php` (`:85`, di tutte o della campagna scelta) e nel Report della campagna.

**Centro preferenze** (`/newsletter-preferenze/?i=&t=`, `sito/index.php:180`; `newsletter_pagina_preferenze()`, `:743`):
firma `newsletter_token_preferenze()` (HMAC di `preferenze|id`, diversa da quella di uscita); link
`newsletter_link_preferenze()` nel piede delle email (con `link_preferenze`), nella pagina di uscita e come
`{{ update_profile }}`. Contatto iscritto: i suoi dati consentiti (`newsletter_preferenze_attributi()`, `:615`: quelli scelti in
Impostazioni fra NOME, COGNOME e gli attributi normali; mai EMAIL, EXT_ID, OPT_IN, calcolati, globali; salvati con
`newsletter_valori_salva()`, che controlla il tipo) e le liste pubbliche (`newsletter_liste_pubbliche()`, `:633`) con la
spunta su quelle che riceve (`newsletter_preferenze_mie()`, `:834`). Salvare (`newsletter_preferenze_lista()`, `:845`, solo
liste pubbliche): togliere la spunta scrive una riga in `newsletter_liste_uscite` (resta nella lista, non riceve più le sue
campagne, la tabella della fase 1); rimetterla toglie la riga o lo fa entrare (partono i flussi della lista); ogni cambio
nella storia del contatto. "Esci da tutte le email": `newsletter_esci()` e 303 alla pagina di uscita con il questionario.
Contatto uscito: "Iscrivimi di nuovo" manda l'email di conferma (`newsletter_richiesta()`, con il suo limite per IP); resta
fuori finché non conferma. Senza firma valida: "Questo link non è valido", nessun dato. I testi delle due pagine
(`newsletter_pagine_testi()`, `:584`; partenza in `newsletter_pagine_partenza()`, `:598`; `{email}` e `{newsletter}`)
si cambiano in Newsletter > Impostazioni (`#nl-pagine`, `admin/newsletter.php:322`, azione `pagine`, `:106`), con le liste
pubbliche e gli attributi; l'indirizzo dell'azienda in `#nl-indirizzo` (`:310`, azione `indirizzo`, `:98`).

**Fatto diverso da Brevo, scelte di plumeCMS**: pagine di disiscrizione multiple (`newsletter_pagine_uscita`) e la scelta per
campagna sono della fase 9 (qui una sola, con i testi modificabili); i motivi e il criterio dei non coinvolti non si sono
trovati e sono di plumeCMS; UTM solo sui link del sito; le figlie "nuovi contatti" e "rinvio" nascono in bozza e si
programmano dal loro riepilogo (Brevo chiede data e ora nella stessa finestra); nome del mittente con i segnaposti, allegato,
immagini incorporate e "All'orario migliore" non ci sono ancora.

Prove: `prove/prova-campagna-riepilogo.js` con `prove/campagne-cli.php` (99 controlli: sezioni, destinatari, segnaposti,
anteprima e prova, programmazione, lotti, versione web, testo semplice, codice HTML, piede, UTM, Rispondi a, campo A,
Duplica, rinvio, nuovi contatti, Archivia, Annulla, Elimina, questionario, centro preferenze, 390 px);
`prove/prova-campagne-vecchie.js` (campagne fatte con la 2.10.0 uguali al byte dopo l'aggiornamento); attacchi: gruppo 33 di
`tools/sicurezza-prova.js`.

---

### 15.11 Editor delle email a blocchi, modelli, sezioni salvate (dalla 2.12.0)

Fase 5 del piano di `docs-sviluppo/brevo-confronto.md` (capitoli 1.12, 1.13, 3.6). Motore in `includes/newsletter-design.php`
(caricato in fondo a `includes/newsletter.php`, `:1036`, e con `require_once` dalle tre pagine che lo usano); le funzioni
nuove chiamate da file di prima passano da `function_exists` (opcache): `newsletter_email()` (`newsletter.php:861`),
`newsletter_richiesta()` (`:526`), `newsletter_campagne_schema()`, `newsletter_campagna_sezioni()` e
`newsletter_campagna_salva_sezione()` (`newsletter-campagne.php:64`, `:267`, `:306`). Solo amministratore (`require_admin()`
sulle pagine e sulle azioni in JSON). Nel testo per chi usa il pannello non c'è nessun paragone con Brevo.

**Dati** (`nd_schema()`, `newsletter-design.php:97`, impronta `newsletter_design_impronta`; chiamata anche da
`newsletter_campagne_schema()`, così chi apre una pagina qualsiasi delle campagne dopo l'aggiornamento ha già le colonne):
- `newsletter_campagne`: `design` MEDIUMTEXT NULL (il JSON) e `modello_id` INT DEFAULT 0. `editor` prende il valore
  `blocchi` (la colonna è VARCHAR(8) dalla 2.11.0); le campagne di prima restano `semplice` o `html`, con lo stesso HTML al
  byte (`prove/prova-campagne-vecchie.js`).
- `newsletter_modelli` (nuova): `nome`, `editor` (`blocchi` o `html`), `design`, `html`, `oggetto` (serve all'email di
  conferma), `attivo` (0 di partenza), `tag` (`optin` per l'email di conferma), `origine` (lo slug della galleria da cui
  viene), `creato`, `aggiornato`.
- `newsletter_sezioni_salvate` (nuova): `nome`, `json` (una sezione), `sincronizzata`, `creato`, `aggiornato`.
- Impostazione `newsletter_doi_modello` (0 = l'email di conferma predefinita della 2.7.0).
Il JSON si scrive con `nd_json()` (`:137`) senza `JSON_UNESCAPED_UNICODE`: tutto ASCII, i caratteri a 4 byte come `\uXXXX`
(le colonne utf8 a 3 byte non li tagliano, e nei campi di testo non entra mai un'entità scritta due volte, trappola della 1.7.0).

**Il design** (`nd_pulisci()`, `:313`): `{v, stile, sezioni[]}`; ogni sezione `{id, layout, fondo, spazio, spazio_x, tel,
nascosta, salvata, colonne[][]}`; ogni blocco `{id, t, ...}`. Le specifiche sono elenchi chiusi in PHP (`nd_spec_stile()`
`:39`, `nd_spec_sezione()` `:52`, `nd_spec_blocchi()` `:61`: `[tipo, etichetta, predefinito, altro]`) e arrivano uguali al
JavaScript dell'editor, che ne fa i controlli: una sola fonte. `nd_valore()` (`:255`) pulisce ogni proprietà per tipo:
`t` testo su una riga (senza caratteri di controllo, tagliato), `h` testo con la formattazione (`nd_html_pulito()`, `:208`:
DOM, solo `p br strong b em i u s a ul ol li`, nessun attributo tranne l'`href` passato da `nd_link()`; le liste puntate di
Quill, `ol > li[data-list=bullet]`, diventano `ul`; libxml codifica `{{ }}` negli `href`, e i quattro link speciali tornano
come si scrivono), `c` colore solo `#rrggbb` (anche `#rgb`; altrimenti il predefinito: nessun CSS libero, quindi niente
`expression`, `url(` o `behavior`), `s` scelta fra le chiavi, `n` intero nei limiti, `m` intero o vuoto, `b` sì/no, `l` link
(`nd_link()`, `:169`: `https?://`, `mailto:`, `tel:`, i quattro speciali `{{ unsubscribe }}`, `{{ update_profile }}`,
`{{ mirror }}`, `{{ doubleoptin }}`, un percorso del sito che diventa intero, un dominio scritto senza schema che prende
`https://`; tutto il resto, anche `javascript:`, `data:`, `vbscript:` e il base64, diventa ''), `i` immagine (`nd_img()`,
`:189`: solo `https?://` e percorsi del sito, niente `data:`), `r` reti social (fino a 12, chiavi di `ND_RETI`). Tipi di
blocco e proprietà fuori elenco spariscono; le colonne sono quelle del layout (`ND_LAYOUT`, `:26`: 100, 50-50, 33-67, 67-33,
25-75, 75-25, 33-33-33); limiti: JSON al massimo 400 KB e 16 livelli (`ND_MAX_JSON`, `json_decode` con profondità 16),
60 sezioni, 300 blocchi. Il design pulito è quello che si salva e quello che si disegna.

**Da JSON a HTML per la posta** (`nd_html()`, `:457`): `<!DOCTYPE html>`, `<meta viewport>`, un solo `<style>` nella testa
con la media query del telefono (`max-width` = larghezza + 20 px: `.nd-corpo` al 100%, margini laterali di 16 px, e nelle
sezioni `.nd-impila` le colonne `display:block`); il corpo è una tabella `role="presentation"` larga `stile.larghezza` (600
di partenza, 480-700) con `bgcolor` e `max-width`; ogni sezione una riga con il suo fondo e i suoi spazi, dentro una tabella
con una cella per colonna (`width` in px dalla quota, 8 px fra le colonne). Ogni blocco (`nd_blocco_html()`, `:399`) sta in una
tabella sua con lo spazio sotto (`nd_cella()`, `:386`), tutti gli stili in linea: titoli `h1`/`h2`/`h3` 30/24/19 px; testo con gli
stili scritti su `p`, `ul`, `ol`, `li`, `a` (`nd_html_stili()`, `:369`; l'ultimo paragrafo senza margine, `nd_ultimo_p()`);
immagini con `alt`, `width` in px dalla colonna e `height` dalle proporzioni salvate (`w`, `h`: l'editor le legge
dall'immagine scelta), `display:block` e `max-width` (`nd_immagine()`, `:391`); pulsante a prova di Outlook: tabella con una
cella `bgcolor` e il link dentro, `mso-padding-alt`, largo quanto la colonna se `pieno`; divisore come `border-top` di una
cella; spaziatore come cella con `height`; social con le icone PNG di `sito/assets/email/<rete>-<stile>.png` (48 px, tre stili,
fatte da `tools/icone-email.js` dalle icone di casa: molti programmi di posta non mostrano gli SVG), indirizzo intero con
`nd_asset()` (`:358`, l'origine più `cms_base()`: giusto anche in cartella); video come immagine con il link (la miniatura di
YouTube se il link è di YouTube, `nd_youtube()`, `:363`) e una riga "&#9654; testo" sotto; piede con il testo e, se acceso,
l'indirizzo dell'azienda. Blocchi senza contenuto (immagine senza indirizzo, pulsante senza link) non escono; le sezioni
nascoste nemmeno. Testo di anteprima nascosto (`div` con `display:none`, che `posta_testo_da_html()` toglie) e "Vedi questa
email nel browser" come nell'editor semplice. Dove andrebbe il piede obbligatorio resta il segno `<!--nd-piede-->`.

**L'email di una campagna** (`nd_email_campagna()`, `:505`, chiamata da `newsletter_email()`): `nd_html()`, poi
`{{ doubleoptin }}` diventa la pagina della newsletter (in una campagna non ha senso), i link speciali
(`nl_camp_link_speciali()`) e i segnaposti `{{ contact.X }}` (`newsletter_personalizza()`, in modo HTML: i valori con
l'escape; i segnaposti sono intatti fino a qui perché i testi sono escapati senza toccare `{{ }}`), infine
`nd_piede_obbligatorio()` (`:495`): se nell'HTML non c'è `href` con il link di uscita firmato, o la prima riga dell'indirizzo
dell'azienda (quando è scritto), una riga in fondo al corpo li rimette. Poi, come per gli altri editor: UTM, tracciamento,
intestazioni, testo semplice (generato dall'HTML o scritto a mano, sezione Design del riepilogo).

**L'editor** (`admin/newsletter-design.php?campagna=N` o `?modello=N`): una pagina sua come l'editor delle pagine
(`aspetti.css`, `admin.css`, `editor/editor.css`, `scegli-link.css`, `newsletter-design.css`; script `pannello.js` per Scegli dai
Media e le conferme, Quill, SortableJS, `scegli-link.js`, `newsletter-design.js`); i dati (`#nd-dati`, `:77`): design, le
specifiche, layout, reti, caratteri, indirizzo delle icone, i colori del sito che sono `#rrggbb` (`editor_globali()`), le
sezioni salvate, l'indirizzo dell'azienda, `modificabile`. Una campagna già partita si apre solo da guardare (`Salva` spento,
avviso), e il server rifiuta comunque. Azioni in POST, risposte JSON con le sezioni salvate aggiornate (`:41`-`:67`): `salva`
(solo bozza o programmata; `editor = 'blocchi'`), `anteprima` (l'email del server con "Giulia"; per un modello `optin` senza
piede obbligatorio), `modello` (Salva come modello: `nd_modello_crea()`, attivo se spuntato, con l'oggetto della campagna),
`sezione_salva`, `sezione_aggiorna`, `sezione_elimina`. CSRF con `csrf_ok()` (token e origine), il design in base64
(`campiProtetti.codifica()` nel `FormData`, `auth.php` lo rimette in chiaro).

`admin/assets/newsletter-design.js`: lo stato `S` (design, selezione, storia per annulla e ripeti fino a 80 passi, con le
modifiche dello stesso controllo raggruppate per 1,5 secondi, `cambia()` `:35`); la tela (`disegna()`, `:156`) è un disegno
in `div` dell'email con i colori dello stile (non l'HTML della posta: quello lo fa il server e si vede in Anteprima), larga
quanto `stile.larghezza` o 390 px in vista telefono (le colonne `.nd-impila` una sotto l'altra); il testo con la
formattazione passa da `testoPulito()` (`:79`), lo stesso elenco chiuso del server. Trascinamento con SortableJS: tavolozza
dei blocchi e delle sezioni e scheda Salvati in `pull: 'clone'`, colonne nel gruppo `blocchi`, l'elenco delle sezioni nel
gruppo `sezioni` con la maniglia; il modello cambia dopo il giro di SortableJS (`setTimeout`), poi la tela si ridisegna e i
Sortable rinascono. Tastiera: voci della tavolozza `role=button` con Invio e Spazio (il blocco entra dopo quello selezionato e
si apre Modifica); blocchi della tela con `tabindex=0`, Invio apre Modifica, Alt+Freccia sposta (`sposta()`, `:240`: dentro
la colonna, poi nella colonna o sezione vicina), Canc elimina; pulsanti ↑ ↓ ⧉ ★ ✕ con `aria-label`; schede ARIA del
pannello con le frecce, Inizio e Fine; Ctrl+Z, Ctrl+Y, Ctrl+S (non dentro i campi). I controlli (`campo()`, `:304`) per tipo:
testo, Quill per il testo con la formattazione (con `pnLink.quill`, indirizzo intero), colore con tavolozza, casella, "Come lo
stile" e i campioni dei colori del sito, scelte, cursore con numero, interruttore, link con `pnLink.campo(..., {modo:
'assoluto'})` e i pulsanti dei link speciali (senza "di conferma" nelle campagne), immagine con "Scegli dai Media"
(`pnMedia.apri`, caricamento in `newsletter-immagine.php`, senza WebP) che legge e salva le misure, reti social. Salvataggio
automatico 2,5 secondi dopo l'ultima modifica, e alla chiusura della pagina se ci sono modifiche.

**Il riepilogo, sezione Design** (`admin/newsletter-campagna.php:302`): le cinque strade in riquadri (`.nd-strade`, `:306`),
ognuna con il suo pulsante: Editor a blocchi (`editor=blocchi`; con il design vuoto parte da `nd_design_vuoto()`, `:333`, e
si apre l'editor, `:39`), Un tuo modello (tendina dei modelli attivi, azione `usa_modello`, `:45`, `nd_campagna_da_modello()`,
`newsletter-design.php:599`, con la conferma del pannello: il design di adesso si sostituisce), La galleria (link a
`newsletter-modelli.php?scheda=galleria&campagna=N`), Editor semplice, Codice HTML; "In uso" sulla strada scelta. Cambiando
strada non si perde niente: `corpo`, `html` e `design` restano nelle loro colonne. Passando al codice HTML dall'editor a
blocchi si parte dall'HTML dei blocchi (`nl_camp_html_partenza()` con il secondo argomento). La sezione è a posto con
almeno un blocco visibile che non sia piede, spaziatore o divisore (`nd_ha_contenuto()`, `:341`). Duplica copia anche
`design` e `modello_id` (parte Design di `NL_CAMP_PARTI`).

**Modelli** (`admin/newsletter-modelli.php`, voce "Modelli" del menu dopo Campagne email, `admin-header.php:33`): "I tuoi
modelli" con Crea un modello (si apre l'editor), elenco con stato scritto (Attivo / Inattivo, con un segno), tipo, data,
Modifica, Rendi attivo o inattivo, Duplica (la copia nasce inattiva), Elimina (con la conferma; se era il modello
dell'email di conferma, l'impostazione torna 0), Rinomina, "Oggetto e uso" (oggetto e tag `optin`). Galleria (`:110`):
`nd_galleria()` (`:577`) legge `includes/newsletter-modelli/*.json` (sei modelli fatti in casa: benvenuto, newsletter del
mese, offerta, evento, nuovo articolo, conferma dell'iscrizione con il tag `optin`; senza immagini: colori, titoli e pulsanti,
così pesano 4 KB in tutto e non chiamano nessun server), ogni JSON passa da `nd_design()`; l'anteprima è un `iframe` con
`sandbox` e `srcdoc`, ridotto al 45%. Azioni: "Aggiungi ai miei modelli" (`nd_galleria_copia()`, attivo) e, arrivando da una
campagna che si può ancora cambiare, "Usa in questa campagna" (una campagna partita: errore, niente copia). Lo slug si
cerca fra le chiavi lette, mai come percorso.

**Sezioni salvate**: la stella di una sezione apre "Salva la sezione per riusarla" (nome, Sincronizzata spuntata di
partenza; `nd_sezione_crea()`, `:629`); sincronizzata, la sezione della tela prende `salvata = id`. Scheda Salvati: Inserisci
(dopo la sezione selezionata, con `salvata` solo se è sincronizzata), trascinamento, Elimina (le email tengono la loro
copia). Selezionando una sezione collegata, il pannello dice da quale viene e offre "Aggiorna in tutte le email"
(`nd_sezione_aggiorna()`, `:647`: prima il JavaScript salva la campagna, poi il server scrive il contenuto nuovo nella
sezione salvata e in ogni campagna in **bozza** e ogni modello a blocchi che hanno una sezione con quel `salvata`, cercati con
`LIKE '%"salvata":ID,%'` e controllati sul JSON letto; le campagne programmate, in invio, inviate o annullate non si toccano;
rifiutato se la sezione non è sincronizzata o non esiste) e "Scollega da quella salvata".

**L'email di conferma con un modello** (`nd_doi_email()`, `:698`, impostazione letta a `:699`): con `newsletter_doi_modello` su un modello attivo con il
tag `optin` e l'editor a blocchi, `newsletter_richiesta()` usa quello: oggetto del modello (con i segnaposti) o "Conferma
l'iscrizione", `{{ doubleoptin }}` diventa il link di conferma (se il modello non ce l'ha, si aggiunge in fondo un pulsante
"Confermo l'iscrizione"), i segnaposti con il nome di chi si iscrive, nessun piede obbligatorio (non è ancora iscritto). Non
vale quando la richiesta porta le parti di un form (`opz['email']` non vuoto) o un oggetto o un testo riscritti: i form con
la loro email di conferma la tengono, e con l'impostazione a 0 l'email è quella della 2.7.0, uguale al byte
(`prove/prova-email-conferma.js`). Si sceglie in Modelli, riquadro "Email di conferma dell'iscrizione" (`#ndm-conferma`,
`:168`), solo fra i modelli adatti (`nd_modelli_optin()`, `:689`; il server rifà il controllo).

**Prove**: `prove/prova-editor-email.js` (ogni blocco e i suoi controlli, tastiera e trascinamento, colonne, annulla e
ripeti, salvataggio automatico, telefono, stile, anteprima, piede rimesso, invio vero al server di posta finto con un
validatore per la posta scritto nella prova, foto delle email a 600 e 390 px, salva come modello e riusa, galleria, modelli,
sezione sincronizzata cambiata nelle bozze e non nelle inviate, modello di doppio opt-in, 390 px), con
`prove/editor-email-cli.php`; attacchi del gruppo 34 in `tools/sicurezza-prova.js`; `prove/prova-ux-editor.js` (5c) e
`prove/prova-ux-pannello.js` sulle pagine nuove.

### 15.12 Il test A/B delle campagne (dalla 2.13.0)

Fase 6 del piano di `docs-sviluppo/brevo-confronto.md` (capitoli 1.14 e 3.6). Motore in `includes/newsletter-ab.php`
(caricato in fondo a `includes/newsletter.php`, `:1041`, e da `newsletter-campagne.php`, `:23`; le pagine che lo usano lo
richiedono con `require_once`). Le funzioni nuove chiamate da file di prima passano da `function_exists` (opcache):
`newsletter_invia_giro()` (`newsletter.php:973`), `newsletter_campagne_schema()` (`newsletter-campagne.php:66`),
`newsletter_campagna_avvia()` (`:227`), `newsletter_campagna_sezioni()` (`:273`), `newsletter_campagna_salva_sezione()`
(`:300`, `:364`), `newsletter_campagna_crea()` (`:388`), `newsletter_campagna_duplica()` (`:404`),
`newsletter_campagna_figlia()` (`:442`), `newsletter_pagina_web()` (`:924`). Solo amministratore (le pagine sono le stesse
delle campagne: `require_admin()`). Nel testo per chi usa il pannello non c'è nessun paragone con Brevo.

**Dati** (`nl_ab_schema()`, `newsletter-ab.php:28`, impronta `newsletter_ab_impronta`; chiamata anche da
`newsletter_campagne_schema()`):
- `newsletter_ab` (nuova), una riga per campagna di tipo `ab` (`newsletter_campagne.tipo`, colonna della 2.11.0): `cosa`
  (`oggetto` o `design`), `oggetto_b`, `anteprima_b`, la versione B del design (`editor_b`, `corpo_b`, `pulsante_testo_b`,
  `pulsante_link_b`, `html_b`, `design_b`, `testo_b`, `testo_modificato_b`: le colonne di `NL_AB_DESIGN`, `:26`, con `_b`;
  `editor_b` vuoto = la B non è ancora nata), `percentuale` (1-50, per gruppo; 20 di partenza), `criterio` (`aperture` o
  `clic`; aperture di partenza), `durata` e `unita` (`ore` fino a 23, `giorni` fino a 7: `NL_AB_UNITA`, `:23`; 4 ore di
  partenza), `fase` (`''` in bozza, `prova` dalla partenza, `resto` quando la vincente è partita al resto, `finita` quando non
  c'è resto), `gruppo` e `resto` (quanti per gruppo e quanti nel resto, contati alla partenza), `avviato_at`, `vincitore`
  (`a`, `b`, `pari`, `''`), `scelto_at`.
- `newsletter_coda.versione` CHAR(1) DEFAULT `'a'` (nuova): `a` e `b` i due gruppi di prova, `r` il resto. Il resto ha lo
  stato `riserva` finché la vincente non c'è. La chiave unica (`campagna_id`, `iscritto_id`) resta: una persona, una riga,
  una versione.
Le campagne normali non hanno righe in `newsletter_ab` e passano da `nl_ab_versione()` senza cambiare: stesso HTML al byte
(`prove/prova-campagne-vecchie.js`).

**La versione di una campagna** (`nl_ab_versione()`, `:82`): con `b` e il test sull'oggetto prende `oggetto_b` e
`anteprima_b`; con il test sul contenuto prende le colonne del design B (e `modello_id` 0); con `r` la vincente (`b` se ha
vinto la B, altrimenti la A: anche nel pari). La usano il giro della coda (`newsletter.php:996`, una copia per campagna e
versione), l'anteprima e la prova del riepilogo, l'editor a blocchi della B, la versione web (`nl_ab_versione_di()`, `:95`,
legge la versione dalla coda di quel contatto) e le figlie.

**Il riepilogo** (`admin/newsletter-campagna.php`): Crea campagna ha il tipo "Test A/B" (`admin/newsletter.php`; un tipo fuori
elenco non crea niente). La sezione **Test A/B** sta dopo Destinatari (`newsletter_campagna_sezioni()`, `:273`; stato da
`nl_ab_sezione()`, `:162`): da completare se manca l'oggetto B o è uguale a quello della A, se il design B non ha contenuto
(`nl_camp_design_stato()`, la regola della sezione Design, `newsletter-campagne.php:279`), se i destinatari sono meno di 2;
come ogni sezione blocca l'invio. Salvataggio con `nl_ab_salva()` (`:136`): cosa, criterio e unità fra le chiavi, percentuale e
durata con `FILTER_VALIDATE_INT` nei limiti (un valore fuori dai limiti è un errore e niente si salva); scegliendo il
contenuto la prima volta il design della A si copia nella B. Il cursore (`input type=range`, 1-50) aggiorna con lo script a
nonce la frase "Versione A a N contatti, versione B a N, la vincente agli altri M" (stesso calcolo di `nl_ab_gruppo()`,
`:130`: `floor(n * p / 100)`, almeno 1 con due destinatari). Con il test sul contenuto la sezione Design ha le schede
Versione A e Versione B (`?versione=b`): con la B le cinque strade, il testo semplice, il Quill e il codice HTML lavorano sulla
B (`newsletter_campagna_salva_sezione()` la scrive con `nl_ab_scrivi_b()`, `:103`, solo nelle colonne di `NL_AB_DESIGN`); un tuo
modello e la galleria con `nl_ab_da_modello()` (`:114`, anche da `admin/newsletter-modelli.php:58`); l'editor a blocchi con
`newsletter-design.php?campagna=N&versione=b` (`admin/newsletter-design.php:22`). La versione B si scrive solo in un test sul
contenuto di una campagna ancora modificabile. Anteprima e prova hanno le schede delle due versioni; la prova le manda tutte e
due ("[PROVA A]", "[PROVA B]"). Niente lotti nel test A/B (la casella non c'è, il salvataggio e l'avvio li forzano a 1).
Si manda come ogni campagna: Invia subito o Programma.

**La partenza** (`nl_ab_avvia()`, `:177`, da `newsletter_campagna_avvia()`): la coda si prepara come per ogni campagna, poi le
righe si mescolano (Fisher-Yates con `random_int`), le prime `gruppo` prendono `a`, le seconde `b`, tutte le altre `r` in
`riserva`; `fase = prova`, `avviato_at` adesso. Il giro manda solo le righe in `attesa`: partono i due gruppi.
`newsletter_invia_giro()` chiude una campagna (inviata) solo quando non ha più righe in `attesa` **né in `riserva`**
(`newsletter.php:1024`): durante il test resta "in invio" (con il 50% non c'è resto e diventa inviata subito, mentre il test
aspetta ancora la sua fine). Annullare la campagna mette `saltata` anche il resto (`newsletter_campagna_annulla()`, `:502`).

**La scelta** (`newsletter_ab_giro()`, `:234`, chiamato da `newsletter_invia_giro()` subito dopo l'avvio delle programmate,
prima dei limiti orari): per ogni test in `prova` di una campagna in invio o inviata (non sospesa né annullata) con
`avviato_at` + durata passati, `nl_ab_scegli()` (`:219`) conta i risultati (`nl_ab_risultati()`, `:195`: per versione le email
inviate dalla coda e le persone con aperture e con clic in `newsletter_eventi`, unite alla coda per sapere la versione; un
clic vale anche come apertura, come sempre), sceglie (`nl_ab_vincitore()`, `:210`: confronto dei tassi senza arrotondare,
aperture o clic sulle inviate del gruppo; uguali, anche zero, = `pari`), poi `UPDATE ... SET fase, vincitore, scelto_at WHERE
fase = 'prova'`: solo chi cambia la riga (`rowCount() = 1`) libera il resto (`riserva` -> `attesa`), che parte con la vincente
nello stesso giro o nei seguenti. Contro due cron sovrapposti: il blocco `newsletter_invio` del giro, il blocco
`nl_ab_` più l'impronta del nome del database e del prefisso (`GET_LOCK`, come l'importazione a giri; i nomi dei blocchi valgono per tutto il server MySQL, e con il database dentro due siti sullo stesso server non si fermano a vicenda) e l'UPDATE condizionato; il resto parte una volta sola.
Riga di log: "campagna N, test A/B: vince la versione B (30% contro 20%), 20 email al resto".

**Il report** (riquadro "Risultati del test A/B", `admin/newsletter-campagna.php:253`): esito (Non ancora con l'ora della
scelta, Ha vinto la versione X per il criterio con data e ora, Pari con la A partita al resto), tabella con le due versioni
(oggetto o "Design A/B", inviate, aperture e clic con i tassi, il segno "Vincente" con la spunta) e la riga del resto. Il
riquadro di stato sopra dice il test in corso, quante email di prova sono partite e quando si sceglie. Nell'elenco delle
campagne la scritta "Test A/B" accanto al nome.

**Duplica, figlie**: Duplica di un test A/B crea un test A/B con le stesse impostazioni e la stessa versione B
(`nl_ab_copia()`, `:260`), fase e vincitore da capo. "Invia ai nuovi contatti" e "Rimanda ai non aperti" creano una campagna
normale con la versione vincente (oggetto, anteprima e design), solo dopo la scelta; prima, l'errore "Il test A/B non ha ancora
scelto la versione vincente".

**Non fatto**: la scelta a mano del vincitore (non trovata nelle fonti, piano capitolo 5); le sezioni salvate sincronizzate
("Aggiorna in tutte le email") non aggiornano il design B di un test (aggiornano le campagne in bozza, cioè la A).

**Prove**: `prove/prova-ab.js` (con `prove/campagne-cli.php`: `semina_ab`, `ab`, `ab_indietro` per spostare indietro la
partenza del test): Crea campagna > Test A/B, sezione e cursore, valori fuori dai limiti, anteprima e prova delle due
versioni, gruppi da 10 su 40 a 25%, una versione a persona, aperture vere dal pixel delle email, vincitore per aperture con due
cron lanciati insieme (resto partito una volta sola), test sul contenuto con la B nell'editor a blocchi, programmato, 50% senza
resto, versione web della B, vincitore per clic, pari con la A al resto, figlia con la vincente, Duplica, annullata durante il
test, 390 px; attacchi del gruppo 35 in `tools/sicurezza-prova.js`.

### 15.13 Report delle campagne, statistiche, soglie, orario migliore (dalla 2.14.0)

Fase 7 del piano di `docs-sviluppo/brevo-confronto.md` (capitoli 1.6 soglie, 1.15 orario migliore, 1.16), con l'orario migliore
scelto da Danilo fra gli extra (decisione 2). Motore in `includes/newsletter-report.php` (caricato in fondo a
`includes/newsletter.php` e da `newsletter-campagne.php`, `:23`; le pagine lo richiedono con `require_once`). Chi lo chiama da
un file di prima passa da `function_exists` (opcache): `newsletter_campagne_schema()` (`newsletter-campagne.php:68`) e
`newsletter_campagna_avvia()` (`:231`). Solo amministratore (`require_admin()` sulle pagine). Nel testo per chi usa il pannello
non c'è nessun paragone con Brevo.

**Dati** (nessuna tabella nuova, nessuna rinominata):
- `newsletter_eventi`: `volte` INT DEFAULT 1 e `ultimo` DATETIME NULL, e l'indice `iscritto` (`iscritto_id`, `tipo`, `creato`)
  per l'orario migliore e gli elenchi. Le aggiunge `newsletter_flussi_schema()` (`newsletter-flussi.php:146`-`:149`, impronta
  con `ripetuti`), perché è lui che `newsletter_evento()` chiama prima di scrivere: anche il primo pixel dopo l'aggiornamento
  trova le colonne. Le righe di prima valgono `volte = 1`: i conteggi per persona restano quelli di prima.
- `newsletter_campagne`: `orario_migliore` TINYINT DEFAULT 0, `orario_ora` TINYINT DEFAULT 10 e l'indice `invio_at` (per i
  periodi). Le aggiunge `nl_rep_schema()` (`newsletter-report.php:39`, impronta `newsletter_report_impronta`), chiamata da
  `newsletter_campagne_schema()`.
- Tipo di evento nuovo `soft` (rimbalzo temporaneo, `tipo` è VARCHAR(10)): `newsletter_evento_interno()` lo accetta
  (`newsletter-flussi.php:231`); lo scrive il giro della coda al terzo tentativo rifiutato con un errore temporaneo
  (`newsletter.php:1019`) e l'avviso `temporaneo` di un servizio di spedizione con il riferimento firmato (`consegna.php:872`).

**Aperture e clic ripetuti** (`newsletter_evento()`, `newsletter-flussi.php:206`): una riga per persona, tipo e indirizzo,
come prima; la stessa apertura o lo stesso clic di nuovo fa `volte = volte + 1, ultimo = NOW()` e basta (gli inneschi dei
flussi e i flussi partono solo la prima volta, come prima). Un clic vale anche come apertura una volta sola (ottavo argomento
`$ripeti` a false): le aperture totali contano i pixel.

**I numeri** (`nl_rep_numeri_molte()`, `:70`, in quattro query per gruppo di 500 campagne; `nl_rep_numeri()`, `:120`):
unici = `COUNT(DISTINCT iscritto_id)` (gli stessi di prima), totali = `SUM(volte)`; rimbalzi temporanei = persone con `soft`
senza un `rimbalzo`; `rimbalzati_dopo` = persone con `rimbalzo` o `soft` la cui riga della coda è `inviata` (l'avviso è
arrivato dopo la partenza). `nl_rep_tassi()` (`:102`): **consegnate** = inviate - rimbalzati_dopo; **provate** = inviate +
errori; tassi di apertura, clic, disiscrizione e spam sulle consegnate, clic sulle aperture = clic unici / aperture uniche,
rimbalzi (definitivi e temporanei) sulle provate (come prima); un decimale, due per lo spam. Le **soglie** (`NL_SOGLIE`,
`:21`): rimbalzi definitivi oltre il 2%, disiscrizioni oltre l'1%, spam oltre lo 0,2% (prima: le soglie di Gmail, spam 0,3%
e rimbalzi 2%), ognuna con la frase di cosa fare; nella pagina con il segno "!" e la parola, mai solo il colore
(`nl_rep_soglie_html()`, `:504`, `nl_rep_tessera()`, `:512`).

**Il report** (`admin/newsletter-report.php?id=N&scheda=...`): solo campagne in invio, sospese, inviate o annullate
(`NL_REP_STATI`); un id che non è un numero intero (`ctype_digit`, `:20`), inesistente o di una campagna non partita torna
all'elenco o al riepilogo. Schede (`NL_REP_SCHEDE`): Panoramica (dettagli, tessere di tutte le voci di `nl_rep_voci()`,
soglie, "Come si contano", il riquadro del test A/B `nl_rep_ab_html()`, `:483`, lo stesso del riepilogo), Aperture, Clic (link
con unici, totali e quota dei clic, `nl_rep_link()`, `:125`; **mappa dei clic** `nl_rep_mappa()`, `:136`: l'email della
campagna per "Giulia" senza tracciamento, con UTM come all'invio, e dopo ogni link tracciato un'etichetta "N clic, N persone,
N%" con `data-mappa`, in un `iframe` con `sandbox` e `srcdoc`; i link di uscita, preferenze e versione web non si contano),
Disiscrizioni (motivi del questionario, `newsletter_uscite_motivi_conta($id)`), Rimbalzi. **Elenchi** (`NL_REP_ELENCHI`,
`:25`; SQL in `nl_rep_elenco_sql()`, `:152`): aperto, non aperto (riga `inviata` senza apertura né rimbalzo), cliccato (una riga
per persona, `GROUP BY i.id`, filtrabile per uno dei link della campagna: l'`url` che non è fra i suoi si ignora), uscito (con
il motivo), spam, rimbalzo e soft (con l'errore della coda); 50 per pagina (`pag` fra 1 e 100000), con il link alla scheda del
contatto. **CSV** (`?csv=<elenco>` o `?csv=report`, `:37`; `nl_rep_csv()`, `:193`): BOM, punto e virgola, a pezzi da 1000,
ogni riga da `sicurezza_csv_riga()` (le celle che cominciano con `= + - @` prendono l'apostrofo, come l'esportazione della
2.9.0); un elenco fuori elenco torna alla pagina con l'errore. **Salva come lista** (`nl_rep_in_lista()`, `:235`: una lista
nuova con `INSERT IGNORE ... SELECT` sulla stessa SQL dell'elenco; le automazioni della lista non partono) e **Salva come
segmento** solo per aperto e cliccato (`nl_rep_segmento()`, `:249`: condizione `email aperta/cliccata` o `link cliccato` sulla
campagna, motore della 2.10.0); POST con `verify_csrf()`, il nome senza tag.

**Statistiche** (`admin/newsletter-statistiche.php`, riscritta): `?campagna=N` (gli indirizzi di prima) fa un 302 con
`no-store` al report (`:19`). Periodo (`nl_rep_periodo()`, `:265`): 7, 30 (predefinito), 90, 365 giorni, da sempre, o dal giorno al
giorno con date valide (`DateTime::createFromFormat('!Y-m-d')` e confronto sul testo, dal 2000 al 2100, la prima non dopo
l'ultima); un valore storto torna a 30 giorni con la frase. Il passo dell'andamento (`nl_rep_passo()`, `:285`): giorno fino a 45
giorni, settimana ISO fino a 200, poi mese. Scheda **Campagne**: le campagne con `invio_at` nel periodo
(`nl_rep_campagne_periodo()`, `:306`, al massimo 300), i totali come somma (`nl_rep_somma()`, `:320`: chi apre due campagne
conta due volte) con i tassi sulla somma, l'andamento (`nl_rep_andamento()`, `:327`) in un grafico a linee **SVG fatto dal
server** (`nl_rep_svg()`, `:385`: un asse solo in percentuale, tacche tonde, griglia, etichette diradate, cerchi per la prima
serie e quadrati con la linea tratteggiata per la seconda, `<title>` su ogni punto, legenda e tabella "I numeri del grafico"
in un `details`; colori `--coral` e `--sage` dell'aspetto, in `admin/assets/css/report.css`), la tabella delle campagne (righe
`data-campagna`, celle `.st-rimbalzi`, `.st-uscite`, `.st-spam` con `.kw-bad` e il segno "!" oltre la soglia), il **confronto**
fra due e quattro campagne spuntate (`confronta[]`, solo id interi fra quelle del periodo), i flussi e i motivi delle uscite di
tutte le campagne (`#st-motivi`). Scheda **Liste e contatti** (`nl_rep_lista()`, `:341`, `lista` 0 = tutti i contatti): per
ogni passo i nuovi (entrati nella lista, o creati), gli usciti (in lista nera, o usciti solo da quella lista nelle preferenze),
gli iscritti alla fine del passo (una sola query con un `SUM(condizione)` per passo); lo stato è quello di oggi (chi è uscito e
rientrato conta come iscritto, detto nella pagina); sotto, i numeri delle campagne mandate alla lista nel periodo
(`FIND_IN_SET` su `liste`).

**Orario migliore**: `nl_orario_ore()` (`:428`): per ogni contatto le prime aperture di ogni email (campagne e flussi) degli
ultimi `NL_ORARIO_GIORNI` (365) giorni raggruppate per `HOUR(creato)` (le date sono nel fuso del sito: `db()` imposta
`time_zone`), almeno `NL_ORARIO_MIN` (2) aperture, l'ora con più aperture, a parità quella dell'apertura più recente; a pezzi
da 1000 id, sull'indice `iscritto`. Nel riepilogo, sezione Programma (`admin/newsletter-campagna.php:520`-`:535`), la terza
scelta "All'orario migliore" con il giorno e l'ora per chi non ha dati (0-23, 10 di partenza), il riassunto
`nl_orario_riassunto()` (`:464`, "N dei M destinatari hanno un orario loro" con le ore) quando è scelto, e "Come si calcola"
in un `details`. Non c'è nel test A/B (rifiutato anche dal server, `:102`). Salvataggio (`:103`): giorno con
`createFromFormat` e confronto, non nel passato; ora con `FILTER_VALIDATE_INT` 0-23; `invio_at` = mezzanotte del giorno (o
adesso, se è oggi), `orario_migliore = 1`, `orario_ora`; le altre due scelte rimettono `orario_migliore = 0`. All'avvio
(`newsletter_campagna_avvia()`, `newsletter-campagne.php:231`) `nl_orario_applica()` (`:448`) scrive in ogni riga della coda
`dopo` = giorno + ora del contatto (o l'ora scelta), NULL se l'ora è già passata (parte subito); niente lotti. Il giro della
coda prende solo le righe con `dopo` passato (dalla 2.11.0) e tiene la campagna "in invio" finché ce n'è una in attesa; i limiti
orari restano sopra (con molti contatti alla stessa ora alcuni partono ai giri dopo).

**Pagine toccate**: riepilogo (`#nlr-report` con le tessere sulle consegnate, le soglie e "Apri il report completo",
`:260`-`:272`), elenco (Report verso `newsletter-report.php`, anche per le annullate, `admin/newsletter.php:266`), menu (la
voce Campagne email resta accesa nel report, `admin-header.php:32`). La dashboard conta ancora aperture e clic unici, come prima.

**Cosa cambia per le campagne di prima**: i numeri per persona (inviate, errori, aperture, clic, uscite, spam, rimbalzi) sono
gli stessi, provato dopo l'aggiornamento 2.13.0 -> 2.14.0; i totali di prima valgono quanto gli unici (le ripetizioni non
erano salvate) e i rimbalzi temporanei di prima sono 0 (non erano eventi). I tassi cambiano solo quando la base "consegnate"
è più bassa delle inviate, cioè quando un servizio ha mandato un rimbalzo o un temporaneo dopo la partenza.

**Non fatto**: Condividi il report e il report mandato per email (qui si scarica dal pannello), le aperture "Apple MPP"
(non si riconoscono senza i dati del servizio), orario migliore per le email delle automazioni (arrivato con la 2.15.0, capitolo 15.14, come le statistiche per passo).

**Prove**: `prove/prova-report.js` con `prove/report-cli.php` (72 controlli: numeri e tassi contati a mano, clic ripetuti, mappa,
elenchi, CSV di ogni elenco e del report, lista e segmento, riepilogo, A/B, periodo scelto e predefinito, andamento, confronto,
parametri storti, lista con i passi, orario migliore calcolato e coda con le ore giuste e il tempo spostato, oggi, 20000
destinatari sotto un secondo, 390 px); attacchi del gruppo 36 in `tools/sicurezza-prova.js`; `prova-consegna-email.js`
legge la tabella nuova (`tr[data-campagna]`, soglia 0,2%).

### 15.14 Automazioni (dalla 2.15.0)

Fase 8 del piano di `docs-sviluppo/brevo-confronto.md` (capitolo 1.17, "Fase 8, 2.15.0"). La pagina e il menu si chiamano
**Automazioni** (`admin/includes/admin-header.php:37`); l'indirizzo resta `admin/newsletter-flussi.php` e le tabelle restano
quelle dei flussi (nessuna rinominata). Solo amministratore (`require_admin()`, `admin/newsletter-flussi.php:13`). Nel testo per
chi usa il pannello nessun paragone con Brevo. Il motore è diviso in due file: `includes/newsletter-flussi.php` (grafo,
salvataggio, inneschi, giro; chiamato a ogni pixel e a ogni clic) e `includes/newsletter-automazioni.php` (nuovo: azioni,
webhook, anniversari e condizioni di uscita, statistiche, attività, design di un passo, modelli), caricato in fondo a
`includes/newsletter.php:1051`. Il primo chiama il secondo sempre con `function_exists` e `include_once` (opcache).

**Dati.** Nessuna tabella rinominata, due nuove; tutto in `newsletter_flussi_schema()` (`newsletter-flussi.php:49`, impronta
con `automazioni giro`), che la pagina, il giro e ogni evento chiamano prima di scrivere:
- `newsletter_flussi`, colonne nuove: `stato` VARCHAR(10) (`attiva`, `in_pausa`, `inattiva`; migrazione `attivo = 1` ->
  `attiva`, `attivo = 0` -> `inattiva`), `inneschi` TEXT (JSON; vuoto = il solo `innesco`/`innesco_id` di prima,
  `nl_flusso_riga()`, `:308`), `tag` VARCHAR(255), `rientro` TINYINT, `rientro_attesa` INT (ore), `uscita` TEXT (JSON
  `disiscritto`, `segmento`). `attivo` resta e si scrive con lo stato (`attivo = stato = 'attiva'`), e `innesco`/`innesco_id`
  restano il primo innesco: il motore di prima, se gira per qualche secondo dopo l'aggiornamento, legge valori giusti.
  Scelta di dettaglio: il piano diceva `attivo = 0` -> `in_pausa` "che è il comportamento di oggi", ma la 2.14.0 nel giro
  fermava anche chi era dentro un flusso spento (`!$f['attivo']` -> `fermato`): il comportamento di prima è quello di
  Inattiva, e la migrazione usa Inattiva.
- `newsletter_corsi`: `giro` INT DEFAULT 1 e la chiave unica `flusso_giro` (`flusso_id`, `iscritto_id`, `giro`) al posto di
  `flusso_iscritto` (prima si aggiunge la nuova, poi si toglie la vecchia). I cammini di prima sono il giro 1.
  Stati del cammino: `attivo`, `finito`, `fermato` (come prima), `tolto` (nuovo: Inattiva, condizione di uscita, a mano).
  `ascolto` prende tre valori in più: `attesa` (dalla 2.15.0 il cammino resta sul nodo Attesa, così la tela dice quanti
  sono lì; quelli di prima, fermi sul nodo dopo con `prossimo` nel futuro, vanno avanti come prima), `evento`, `orario`.
- `newsletter_flussi_registro` (nuova): `flusso_id`, `corso_id`, `iscritto_id`, `nodo`, `evento` (`entrato`, `email`,
  `attesa`, `bivio`, `evento`, `azione`, `finito`, `tolto`, `uscito`, `fermato`), `dettaglio` (255, con le entità),
  `creato`. Si pulisce oltre 90 giorni (`nl_aut_pulizia()`, `newsletter-automazioni.php:261`, dalla pulizia di ogni giorno,
  `newsletter.php:771`).
- `newsletter_flussi_numeri` (nuova): (`flusso_id`, `nodo`, `chiave`) -> `n`, con `INSERT ... ON DUPLICATE KEY UPDATE`
  (`nl_conta()`, `newsletter-flussi.php:625`): `passati` di ogni nodo, `inviate` delle email, l'uscita presa (`poi`, `si`,
  `no`, `r0`..., `altro`, `p0`...). Restano anche quando il registro si pulisce. I flussi di prima partono da zero (i
  passaggi di prima non erano salvati).

**Il grafo.** Elenco chiuso dei tipi in `NL_USCITE` (`:31`) con le uscite fisse; le divisioni hanno le uscite dei loro rami
(`nl_uscite()`, `:378`). `newsletter_flusso_grafo_valido()` (`:403`), alla lettura e al salvataggio: al massimo
`NL_AUT_MAX_NODI` (100) blocchi letti, tipi dall'elenco, dati di ogni tipo con i loro campi e i loro limiti (email: oggetto
255, anteprima 255, corpo 300000, ascolto fino a un anno e, con `nl_email_extra()`, `:456`, editor `semplice`, `blocchi`, `html` (2.17.1, dal modello) o `modello` (dalla 2.18.0 il passo richiama il modello, sotto),
`design`, `modello`, `mittente_id`, `orario` `subito`/`ora`/`migliore`, `ora` 0-23; attesa almeno 1 minuto; evento
`apertura`/`clic`, email, massimo; dividi fino a `NL_AUT_MAX_RAMI` (8) rami con nome e regole grezze, `nl_regole_grezze()`,
`:393`; percento da 2 a 5 parti fra 1 e 99; liste; attributo con nome `[A-Z0-9_]`; webhook 500 caratteri), coordinate fra 0 e
6000, archi solo da uscite esistenti verso nodi esistenti, una per uscita. Un'email di prima non ha i campi nuovi: valgono i
predefiniti e `nl_email_campagna()` (`:794`) costruisce la stessa campagna di prima (le chiavi `editor` e `design` solo per
l'editor a blocchi): l'email esce identica al byte (provato con le impronte, sotto). `newsletter_flusso_grafo_dal_form()`
(`:483`): JSON fino a 2 MB e profondità 64; più di 100 nodi rifiutati con il messaggio.

**Salvataggio** (`newsletter_flusso_salva()`, `:518`): nome obbligatorio, almeno un blocco, innesco collegato, **nessun giro
chiuso** (`nl_grafo_giro()`, `:493`, visita in profondità: il messaggio dice a quale blocco torna il collegamento; un
cammino ha in più il limite `NL_AUT_MAX_SALTI`, 250 passi, e `NL_AUT_PASSI_GIRO`, 50 passi per giro del cron), email con
oggetto, percentuali che fanno 100, rami con almeno una condizione controllata da `newsletter_regole_valida()` con il
contesto `flusso` (stessi messaggi dei segmenti), liste scelte, attributo modificabile (non calcolato né globale; di
sistema solo NOME e COGNOME) con un valore del suo tipo, webhook controllato (sotto). Il design a blocchi di un'email si
prende dal database (quello del browser non vale: lo scrive solo l'editor delle email) o dal modello scelto adesso
(`modello` torna 0). Inneschi (`nl_inneschi_validi()`, `:320`): tipi da `NL_INNESCHI` (`:19`), al massimo 10, senza doppi,
`id` solo dove serve (lista, tolto da lista, form, campagna, giorni, giorni prima dell'anniversario 0-365), `attr` solo per
l'anniversario; si accettano anche i due campi di prima (`innesco`, `innesco_id`, `attivo`: `prove/newsletter-cli.php`).
Stato dall'elenco (altrimenti Inattiva), rientro almeno 1 ora e al massimo 8760, segmento di uscita che esiste, tag senza
tag HTML (al massimo 5). Inattiva toglie subito chi è dentro (`nl_aut_togli_tutti()`, `:630`). `newsletter_flusso_elimina()`
(`:613`) toglie anche registro e contatori.

**Inneschi** (`newsletter_flussi_innesco()`, `:684`): fa partire le automazioni **Attive** (`nl_flussi_attivi()`, `:674`,
una lettura per richiesta) che hanno quell'innesco fra i loro (id 0 = qualunque). Per chi non è attivo parte solo
`disiscritto`; `form` per chi aspetta la conferma resta in attesa come prima. Dove partono gli inneschi nuovi: `lista_tolto`
da `newsletter_togli_da_liste()` (`newsletter-contatti.php:574`, anche con l'azione "togli da una lista") e dal centro
preferenze (`newsletter-campagne.php:915`); `disiscritto` da `newsletter_esci()` (`newsletter.php:692`); `anniversario` dal
giro (`nl_aut_anniversari()`, `newsletter-automazioni.php:137`: giorno e mese della data fra N giorni, attributi data
personali o di sistema, una volta l'anno, 500 per giro); `mano` dalla scheda Attività. `inattivo` come prima
(`newsletter_flussi_inattivi()`, `:703`; con il rientro guarda chi ha finito da abbastanza). **Ingresso**
(`newsletter_flusso_parti()`, `:651`): chi è dentro non rientra; chi ha finito rientra solo con il rientro acceso e l'attesa
passata, con il giro dopo (l'anniversario rientra se l'ultimo ingresso ha più di 300 giorni); `INSERT IGNORE` sulla chiave
unica contro due ingressi insieme; riga `entrato` nel registro con l'innesco.

**Il giro** (`newsletter_flussi_giro()`, `:815`), dentro il giro della newsletter e il suo `GET_LOCK` con il nome del
database (`newsletter_invia_giro()`), con i posti rimasti nel limite orario: inattivi, poi `nl_aut_giro_prima()`
(`newsletter-automazioni.php:127`: anniversari, poi le **condizioni di uscita** `nl_aut_uscite()`, `:160`, che guardano tutti
i cammini in corso, anche in attesa: con "si disiscrive" chi non è più attivo esce, con il segmento chi ci è entrato,
`newsletter_segmento_where()`; e le automazioni Inattive con qualcuno dentro lo tolgono), poi i cammini arrivati alla loro
ora (`posti * 3 + 50`). Per ogni cammino: automazione Inattiva -> tolto; contatto non attivo -> esce (condizione di uscita),
oppure prosegue senza email se l'automazione ha l'innesco "si disiscrive", oppure si ferma come prima. **In pausa** non fa
entrare nessuno (gli inneschi guardano solo le Attive) ma i cammini proseguono. `nl_corso_scorri()` (`:847`) fa scorrere il
cammino: un ascolto scaduto esce dall'uscita giusta (email: "se non fa niente" o "subito dopo"; evento: "no"; attesa: "poi";
orario: manda adesso); poi nodo per nodo: **Attesa** resta sul nodo con `prossimo`; **Attendi un evento** esce subito dal Sì
se l'evento c'è già da quando il contatto è entrato (`newsletter_eventi` dell'automazione, dell'email scelta o di tutte),
sennò aspetta fino al massimo; **Sì o No** come prima; **Divisione condizionale** (`nl_dividi_ramo()`, `:932`): il primo ramo
le cui regole valgono (`newsletter_regole_vale()`, motore dei segmenti), sennò `altro`; **Divisione in percentuale**
(`nl_percento_ramo()`, `:941`): il ramo più indietro rispetto alla sua parte, dai contatori, così le proporzioni sono esatte;
**azioni** (`nl_aut_azione()`, `newsletter-automazioni.php:24`: `newsletter_in_lista()`, che fa partire le automazioni della
lista; `newsletter_togli_da_liste()`; `newsletter_valori_salva()` con la storia; webhook al massimo 20 per giro); **Email**:
con l'orario `ora` o `migliore` il cammino aspetta (`nl_email_quando()`, `:801`: il prossimo "alle HH", oggi o domani;
all'orario migliore l'ora di `nl_orario_ore()` del report 2.14.0, o quella scelta per chi non ha dati), poi parte con
`newsletter_email()` e il mittente del passo (o dell'automazione); dopo: contatori `inviate` e `passati`, registro `email` con
l'oggetto, la storia del contatto come prima, l'ascolto come prima. Ogni spostamento passa da una closure `$vai` che scrive
il cammino e i contatori. Un'apertura o un clic (`newsletter_flusso_evento()`, `:767`) sposta chi è in ascolto sull'email
(come prima) e chi aspetta quell'evento (il clic vale anche come apertura).

**Webhook** (`newsletter-automazioni.php`): `nl_aut_webhook_controlla()` (`:62`), al salvataggio e alla chiamata: https,
porta 443, niente utente e password nell'indirizzo, nome con lettere, cifre, punti e trattini, niente `localhost`,
`.localhost`, `.local`, `.internal`, niente nomi fatti di soli numeri (`2130706433`, `0x7f000001`, `127.1`: l'ultima parte deve
cominciare con una lettera); ogni IP risolto controllato con `nl_aut_ip_interno()` (`:49`: le reti
private, riservate, 100.64/10, 0/8, 192.0.0/24, 198.18/15, più IPv4 dentro IPv6 e
`::/96`); un nome che adesso non si trova si salva (si risolve di nuovo alla chiamata). `nl_aut_webhook()` (`:87`): POST
JSON (`evento`, `automazione` id e nome, `passo`, `contatto` id, email, nome, cognome, `quando`) con `X-Plume-Firma:
sha256=` (HMAC del corpo con il segreto del sito); `CURLOPT_RESOLVE` sull'IP controllato, `CURLOPT_FOLLOWLOCATION` spento (un
3xx dice "il servizio rimanda a un altro indirizzo: per sicurezza non lo seguo"), solo http e https, niente proxy,
certificato verificato, 3 secondi per collegarsi e 5 in tutto (`NL_AUT_WEBHOOK_TEMPO`); della risposta si leggono al
massimo 2 KB e si tengono 120 caratteri senza tag nel registro. Il cammino prosegue comunque. **Solo per le prove**:
`CMS_WEBHOOK_LOCALE` nel `config.local.php` (`nl_aut_webhook_locali()`, `:44`), "host:porta" esatti per cui valgono http e
gli IP interni; non definita di partenza.

**La pagina** (`admin/newsletter-flussi.php`): elenco (inneschi in parole, entrati, in corso, finiti, usciti, email inviate,
stato con la parola, tag) e "Crea un'automazione" con Da zero e i **modelli** (`nl_aut_modelli()`,
`newsletter-automazioni.php:284`: Benvenuto con il Sì o No sull'apertura, Anniversario sul primo attributo data, Attività di
marketing per chi non apre da 90 giorni con Attendi un evento e Togli dalla lista principale), che aprono la tela senza
salvare niente. La tela: barra con nome (di partenza "Automazione n. X", il prossimo id), tag, stato (Attiva, In pausa,
Inattiva, con la frase d'aiuto), Riordina, Elenco, Salva; i numeri (entrati, in corso, finiti, usciti o tolti); pannello a
schede ARIA (Inneschi con l'elenco degli inneschi e Impostazioni: rientro, uscita, mittente; Elimina con la conferma del
pannello; Azioni; Regole); i pezzi sono `role="button"` e si aggiungono anche con Invio. Con un blocco scelto, il pezzo nuovo
entra sotto e si collega alla sua prima uscita libera (il "+" sotto un passo, `aggiungi()` in `admin/assets/flussi.js`). Sulla
tela ogni blocco mostra i suoi numeri (qui adesso, passati; per le email inviate, aperture e clic con i tassi del report,
`nl_aut_numeri()`, `:188`, con `nl_rep_tassi()`), e ogni uscita quanti l'hanno presa. La **Divisione condizionale** monta nel
pannello il costruttore dei segmenti (`window.nlsMonta` e `box.nlsCambio` in `admin/assets/segmenti.js:43` e `:180`; la
condizione "Email di questa automazione" con le email della tela in `DEF.flussoEmail`, `:92`). Il passo Email ha mittente,
oggetto, anteprima, contenuto (Testo semplice con Quill, o Editor a blocchi: "Salva e apri l'editor a blocchi" salva e apre
`newsletter-design.php?flusso=F&nodo=N` (`admin/newsletter-design.php:20`-`:25`), che scrive il design nel grafo con
`nl_aut_design_salva()`, `:267`, e torna alla tela; dalla 2.17.1 "Da un modello" come terza scelta del Contenuto, con tutti i modelli tranne quelli `optin`, anche in codice HTML e non attivi: `$modelliEmail` in `admin/newsletter-flussi.php:216`; nella 2.17.1 il modello si copiava al salvataggio, dalla 2.18.0 il passo lo richiama, vedi "Il passo Email che richiama un modello" qui sotto), quando parte
(subito, a un'ora, all'orario migliore), l'ascolto come prima. Il grafo e gli inneschi viaggiano in JSON nei campi nascosti
(base64 di `campi-protetti.js` contro ModSecurity); la copia nel browser e il battito della sessione come prima. Scheda
**Attività e registro** (`?id=N&scheda=attivita`): chi è dentro (50 per pagina, filtro per passo, "Togli" con la conferma,
`nl_aut_togli()`, `:241`, che controlla che il cammino sia di quell'automazione), "Aggiungi a mano" (`nl_aut_aggiungi()`,
`:249`, solo con l'innesco e l'automazione Attiva), il registro (200 righe, filtro per email, `nl_aut_registro()`, `:231`). Un
segmento usato da una divisione o da una condizione di uscita non si elimina (`newsletter_segmento_usato()`,
`newsletter-segmenti.php:694`). Altre pagine: Statistiche (la tabella delle automazioni con lo stato), Importa, Consegna,
Cron (la parola "automazioni").

**Prove**: `prove/prova-automazioni.js` con `prove/automazioni-cli.php` (la tela disegnata come un utente con tutti i tipi,
gli errori del Salva, le impostazioni; ogni innesco e più inneschi; attendi un evento con il massimo e già successo;
divisione a più rami su 30 contatti e in percentuale 50/30/20 esatta su 200; azioni; rientro con l'attesa; uscita in mezzo a
un'attesa per segmento e disiscrizione; In pausa e Inattiva dal pannello; webhook verso un server finto con
`CMS_WEBHOOK_LOCALE` scritta e poi tolta; statistiche per passo contate a mano; attività, registro, togli, aggiungi a mano;
orario, orario migliore, editor a blocchi e modello; i tre modelli; 390 px). `prove/prova-automazioni-vecchie.js`: un sito
2.14.0 con tre flussi e sei contatti a metà (`automazioni-cli.php vecchi_semina`, impronte delle 42 email), aggiornato alla
2.15.0: impronte uguali, i cammini arrivano in fondo con le stesse email e lo stesso esito dei bivi, il flusso spento diventa
Inattiva e nessuno riceve la sua email. Attacchi del gruppo 37 in `tools/sicurezza-prova.js`.

**Il passo Email che richiama un modello (dalla 2.18.0).** Richiesta di Danilo: le email di una sequenza (per esempio 11 email
di un ebook) si scrivono e si correggono una volta sola in Modelli, e l'automazione manda sempre l'ultima versione salvata.
Nella 2.17.1 "Da un modello" copiava il modello nel passo; dalla 2.18.0 lo **richiama**.

- *Formato del nodo* (colonna `passi` di `<prefisso>newsletter_flussi`, JSON con i caratteri fuori ASCII come `\uXXXX`):
  `{"id":7,"tipo":"email","x":340,"y":980,"dati":{"oggetto":"","anteprima":"","corpo":"","pulsante_testo":"","pulsante_link":"","ascolto":0,"editor":"modello","design":"","html":"","modello":12,"mittente_id":0,"orario":"ora","ora":8}}`.
  `editor` = `modello` e `modello` = id della riga di `<prefisso>newsletter_modelli`; `design` e `html` vuoti (il passo non ha
  contenuto suo); `oggetto` e `anteprima` vuoti valgono quelli del modello al momento dell'invio, scritti vincono.
  `nl_email_extra()` (`includes/newsletter-flussi.php:475`) tiene `modello` (0-1000000) quando il grafo si rilegge;
  `newsletter_flusso_salva()` (`:543`) non lo rimette più a 0 per `editor` `modello` (sì per gli altri editor), svuota `design`
  e `html`, rifiuta un id che non c'è o di un modello `optin` ("il modello scelto non c'è più") e l'oggetto vuoto se anche il
  modello non ne ha ("scrivi l'oggetto, oppure dallo al modello «X»"). Gli altri nodi e le righe non cambiano formato.
- *All'invio*: `nl_passo_modello()` (`:845`) legge il modello adesso (`nd_modello()`, con `include_once` di
  `newsletter-design.php` se il giro del cron non l'ha caricato) e dice perché non si può mandare: non c'è, è `optin`, senza
  oggetto (né nel passo né nel modello), senza contenuto (codice HTML vuoto o design non leggibile). Un modello **inattivo**
  si manda lo stesso (scelta di dettaglio: "inattivo" vale per le scelte nuove, e la conferma di "Rendi inattivo" lo dice).
  `nl_email_campagna()` (`:895`) compone la campagna dal modello: `editor` `blocchi` con il `design` o `html` con il codice
  (carica `newsletter-campagne.php` per `nl_camp_html()`), oggetto e anteprima del passo o del modello; poi
  `newsletter_email()` come per ogni email (segnaposti, tracciamento, piede e link per uscire).
- *Modello che non si può mandare*: `nl_corso_scorri()` (`:969`, il blocco dopo il controllo "non è iscritto" e prima
  dell'orario, `:1027`-`:1039`) non manda niente: il cammino resta sul passo con `ascolto` = `modello` e `prossimo` fra un'ora (`:1036`;
  prima dell'orario, così un passo "alle 8" che aspetta il modello resta segnato fermo), una riga
  `email` nel registro con "modello #N non trovato: email non mandata" una volta sola per cammino (lo stesso dettaglio non si
  riscrive), una riga nel log del giro. Quando il passo richiama un altro modello (o il modello torna), al giro dopo l'email
  parte (con l'orario `ora`, alla prossima ora scelta). Attività dice "fermo: il modello dell'email non si può mandare".
- *Dove è usato*: `nl_modelli_usati()` (`:860`, id del modello -> automazioni e passi) per Modelli e le conferme;
  `nl_passi_senza_modello()` (`:877`) per l'avviso della dashboard (`admin/index.php:47`, solo amministratore, automazioni
  non inattive, con quante persone sono ferme e il link all'automazione). Chi chiama queste funzioni da un altro file lo fa
  con `function_exists` (opcache dopo l'aggiornamento).
- *Pannello del passo* (`admin/assets/flussi.js`): la tela dice "Modello: Ebook 05" (o "Modello #N non trovato") e l'oggetto
  del modello se il passo non ne ha uno; nel pannello oggetto e anteprima vuoti hanno il segnaposto "dal modello: ..."
  (`segnaposto()`, `:20`; `nomeEmail()`, `:33`, anche per i menu e i riassunti dei bivi), "Apri il modello" (`newsletter-design.php?modello=N&flusso=F`, o `newsletter-modelli.php?codice=N&flusso=F`
  per il codice HTML: "Torna" riporta all'automazione, `admin/newsletter-design.php:30`) e **Stacca dal modello**: campo
  nascosto `stacca` con l'id del passo e azione `stacca` (`admin/newsletter-flussi.php:44`); `newsletter_flusso_salva()`
  copia allora il contenuto di adesso (`design` o `html`, editor `blocchi` o `html`, oggetto e anteprima se vuoti) e mette
  `modello` a 0, come la 2.17.1. `$modelliEmail` (`admin/newsletter-flussi.php:216`) porta anche `a` (anteprima) e `u` (dove
  aprirlo); il grafo mandato al browser ha `design` e `html` come "si" o "" (il contenuto resta sul server).
- *Modelli* (`admin/newsletter-modelli.php`): colonna **Usato in** con i link; "Rendi inattivo" e "Elimina" di un modello
  usato con la conferma che elenca le automazioni (`$doveUsato`, `:27`); un modello usato non diventa `optin` (`:67`);
  **Oggetto e uso** con il testo di anteprima; "Crea un modello" con **Come lo scrivi** (blocchi o codice HTML: parte da
  `nl_camp_html_partenza()`, `:42`) e la pagina del codice `?codice=N` (`:111`, azione `codice`, `:49`: oggetto, anteprima,
  codice fino a 1,5 MB, come il Codice HTML delle campagne). Colonna nuova `newsletter_modelli.anteprima` VARCHAR(255)
  (`nd_schema()`, `includes/newsletter-design.php:109` e `:136`, con l'impronta cambiata), `nd_anteprima()` (`:554`),
  `nd_modello_cambia()` accetta `anteprima` e `html` (`:559`). Le campagne non cambiano: lì il modello resta un punto di
  partenza copiato (`nd_campagna_da_modello()`), e l'anteprima del modello non si usa.
- *Compatibilità*: un passo salvato prima (semplice, a blocchi, o copiato da un modello con la 2.17.1, `editor` `blocchi` o
  `html`) non cambia e manda la stessa email al byte (`automazioni-cli.php impronte "AU Copia%"` prima e dopo
  l'aggiornamento). Un passo scritto a mano in un pacchetto con `editor` `blocchi` e `modello` > 0 non copia più il modello
  (il `modello` torna 0): per richiamarlo si scrive `editor` `modello`.
- *Prove*: `prove/prova-passo-modello.js` (sopra), `prove/prova-automazioni.js` (passo Email), gruppo di attacchi 40 di
  `tools/sicurezza-prova.js`.

**Non fatto**: inneschi del sito e dell'e-commerce, SMS (fuori dal piano); la prova di un'automazione (Brevo non l'ha); il
design a blocchi delle email delle automazioni non segue le sezioni salvate sincronizzate (si aggiornano solo nelle campagne
in bozza e nei modelli); il giro delle automazioni parte solo quando il limite orario della newsletter lascia posti (come
prima).

### 15.15 Form a cinque passi, pagine pubbliche, gruppi di consenso, tracciamento (dalla 2.16.0)

Fase 9 del piano di `docs-sviluppo/brevo-confronto.md` (capitoli 1.7, 1.8, 1.9, 3.5, 3.8, "Fase 9, 2.16.0"). Nel testo per
chi usa il pannello e per i visitatori nessun paragone con Brevo. Due file nuovi: `includes/form-brevo.php` (tipi, passi,
attributi, messaggi, conferme, pagina ospitata, codici, QR, domanda di controllo; caricato in cima a `includes/form.php:14`) e
`includes/newsletter-consenso.php` (gruppi di consenso, tracciamento, registro del consenso, pagine di disiscrizione;
caricato da `includes/newsletter-campagne.php:25` e da `includes/form-brevo.php`). Chi chiama una funzione nuova da un file
cambiato carica il file nuovo da sé, o la chiama con `function_exists` (opcache).

**Dati.** Nessuna tabella né colonna rinominata; le migrazioni stanno nelle funzioni di schema.
- `moduli`, colonne nuove (`fb_schema()`, `includes/form-brevo.php:53`, chiamata in fondo a `form_schema()`,
  `includes/form.php:97`): `tipo` (`iscrizione`, `popup`, `profilo`, `disiscrizione`, `generico`), `conferma` (`doppia`,
  `semplice`, `nessuna`), `liste` (le liste in più, id separati da virgola), `doi_modello_id`, `doi_url`, `aggiorna_esistenti`,
  `gdpr`, `gdpr_testo`, `messaggi` (JSON: `riuscita`, `errore`, `obbligatorio`, `email`, `benvenuto_oggetto`,
  `benvenuto_testo`; solo le voci diverse dalla partenza, `fb_messaggi_da_salvare()`, `:142`), `dopo_invio` (`pagina`,
  `messaggio`, `url`), `dopo_url`, `popup_id`. Migrazione una volta (impostazione `form_brevo_migrati`): `lista_id > 0` ->
  `iscrizione`, altrimenti `generico`; `lista_conferma = 1` -> `doppia`, `0` -> `nessuna`. Il resto ha i valori di partenza,
  che danno lo stesso form: `form_html()` senza opzioni esce al byte come prima. `lista_conferma` resta e si scrive con la
  conferma (`form_salva()`, `includes/form.php:366`: `doppia` = 1); chi salva con la spunta di prima (il kit di partenza)
  non tocca le colonne nuove.
- Nei campi (JSON) la chiave `attributo` (`form_campi_validi()`, `:163`): il nome di un attributo dei contatti
  (`fb_attributi_campi()`, `includes/form-brevo.php:106`: normali e di categoria, fra quelli di sistema solo EMAIL, NOME,
  COGNOME), solo se va con il tipo del campo (`FORM_ATTR_CAMPI`, `:96`, e `fb_attributo_compatibile()`, `:117`: la casella da
  spuntare solo con un attributo Sì/No; EMAIL solo dal campo Email). I campi di prima "è il nome / il cognome / l'email"
  puntano da soli a NOME, COGNOME, EMAIL. Per categoria e scelta multipla le opzioni sono quelle dell'attributo. Tre tipi
  di campo nuovi (`FORM_TIPI_CAMPO`, `includes/form.php:16`): `liste` (Iscrizione a più liste, chiave `liste` con gli id
  offerti), `gruppi` (Gruppi di consenso, chiave `gruppi`; vuota = tutti), `tracciamento` (mai obbligatorio).
- `newsletter_gruppi_consenso` (`nome` UNIQUE, `descrizione`, `ingresso` `automatic`|`manual`) e `newsletter_contatti_gruppi`
  (`contatto_id`, `gruppo_id`, `iscritto`), `newsletter_pagine_uscita` (`nome` UNIQUE, `testi` JSON con le chiavi di
  `newsletter_pagine_testi()`), tutte in `nl_consenso_schema()` (`includes/newsletter-consenso.php:28`), che aggiunge anche
  `newsletter_campagne.pagina_uscita_id` e `.gruppo_id`, `newsletter_conferme.altre_liste` e l'attributo di sistema
  TRACCIAMENTO (colonna `tracciamento` di `newsletter_iscritti`, che c'era dalla 2.8.0 con 1 di partenza). Impostazioni:
  `newsletter_gruppi_attivi` (spenta di partenza, decisione di Danilo), `newsletter_preferenze_form` (0).

**Il costruttore** (`admin/form.php`, solo amministratore). Elenco con le viste per tipo (`?vedi=`), "+ Nuovo form" con la
scelta fra cinque tipi (`FORM_TIPI`, `FORM_TIPI_SPIEGA`, `includes/form-brevo.php:22`), poi i passi `?id=N&passo=1..5`
(`FORM_PASSI`, `:30`; i passi fatti si riaprono dalla fila in cima, `nav.fo-passi`). Ogni passo posta solo i suoi campi:
`fm_dati()` (`admin/form.php:24`) rimette il form salvato nella forma di `form_salva()` e il passo (`switch` da `:82`)
cambia solo le sue voci; "Salva" resta sul passo, "Avanti" va al seguente. Il passo 1 di un form nuovo è "Crea il form", con
i campi di partenza del tipo (`fm_campi_partenza()`, `:34`). Passo 2 (`:213`): titolo, introduzione, pulsante, i campi
(`admin/assets/form-campi.js`, con la tendina Attributo filtrata per tipo, le liste e i gruppi da offrire), la dichiarazione
GDPR e il testo della casella. Passo 3 (`:248`): dopo l'invio (pagina di ringraziamento, messaggio al posto del form, un altro
indirizzo), messaggi di riuscita e d'errore (`{campo}`), l'avviso (come dalla 1.9.0). Passo 4 (`:283`): conferma doppia,
semplice, nessuna; lista principale e liste in più; "Aggiorna i contatti esistenti"; il modello di doppio opt-in (i modelli
con l'etichetta optin, `nd_modelli_optin()`) e la pagina dopo il clic; l'email della conferma semplice; il file in regalo; il
riquadro Email di conferma della 2.7.0 (con titolo e nome del form in campi nascosti per l'anteprima). Passo 5 (`:348`):
link della pagina ospitata, codice iframe, HTML, HTML semplice, QR (`?qr=1` lo scarica in SVG, `:45`), per il tipo Pop-up
le regole del popup (`fb_popup_salva()`, `includes/form-brevo.php:446`, che usa il motore dei Popup con `modulo = form:N`),
per il tipo Aggiornamento del profilo "Usa questo form come centro preferenze". `form_salva()` rifiuta: un form di iscrizione
senza lista principale, un tipo che iscrive senza campo Email, una casella nuova senza attributo Sì/No in un form che non è
"Altri form" (quelle di prima restano), un blocco liste vuoto, un indirizzo dopo l'invio o dopo il clic che non comincia con
`/` (non `//` né `/\`) o con `https://host`, o che contiene spazi, virgolette, `<` `>`.

**All'invio** (`form_ricevi()`, `includes/form.php:580`), oltre ai passi di 15.1: i blocchi liste e gruppi accettano solo gli
id offerti (un id in più, scritto a mano, ferma l'invio con l'errore del campo); i valori dei campi con un attributo vanno in
`$x['attributi']`; il form di profilo vale solo con il link firmato del contatto (`pi`, `pt` =
`newsletter_token_preferenze()`, `fb_contatto_da_link()`, `includes/form-brevo.php:330`), altrimenti l'errore `link`, e
l'email del campo è sempre quella del contatto; profilo e disiscrizione non chiedono il consenso. Se il captcha manca, una
risposta alla domanda di controllo (`fb_domanda_valida()`, `:183`: firmata con `moduli_segreto()`, 20 minuti, una volta sola
in `captcha_usati`) vale al suo posto. Poi: profilo -> `fb_salva_profilo()` (`:406`: attributi senza EMAIL, liste offerte con
`newsletter_liste_uscite`, gruppi, tracciamento); disiscrizione -> `fb_disiscrizione()` (`:433`: a chi è iscritto parte
l'email con il link firmato per uscire; il messaggio sul sito è lo stesso per ogni indirizzo); iscrizione -> `form_iscrivi()`
(`:752`, con le liste in più: entrano con la principale, o alla conferma, `newsletter_conferme.altre_liste`) e
`fb_dopo_iscrizione()` (`includes/form-brevo.php:375`: attributi per un contatto nuovo, o per uno che c'era solo con
"Aggiorna i contatti esistenti"; gruppi; tracciamento; una riga nel registro del consenso con la dichiarazione e il testo della
casella, data e IP; con il GDPR acceso `opt_in = 1`), e con la conferma semplice l'email di benvenuto (`fb_benvenuto()`,
`:395`). La conferma doppia con un modello passa `modello` a `newsletter_richiesta()` (`includes/newsletter.php:527`), che lo
dà a `nd_doi_email()` (`includes/newsletter-design.php:698`, parametro nuovo, facoltativo). La pagina dopo il clic:
`sito/index.php:167` (302 senza memoria, solo se il form non dà un file).

**Dopo l'invio** (`api/modulo.php:51`): dalla pagina ospitata o da un codice incorporato (`ospitata=1`) si torna alla pagina
ospitata (`?fatto=1`, o con l'errore); il form di profilo si porta dietro `i` e `t`; "un altro indirizzo" vince; "un
messaggio al posto del form" torna alla pagina con `?modulo=ok&f=N` e `vista_modulo()` (`sito/viste.php:346`) disegna il
messaggio; i messaggi d'errore scritti nel form passano da `fb_errore()` (`includes/form-brevo.php:155`). Senza JavaScript
(HTML semplice, o la pagina ospitata con la domanda dentro `<noscript>`): con `captcha` o `domanda` sbagliati `api/modulo.php:58`
risponde con la pagina "ultimo passo" (`fb_pagina_verifica()`, `:299`): lo stesso form già compilato, con la domanda.

**Pagina ospitata e codici.** `/modulo/<slug>/` (`sito/index.php:217`, `fb_form_da_slug()`, solo form attivi;
`fb_pagina_ospitata()`, `includes/form-brevo.php:278`; `fb_documento()`, `:261`: solo il form, con `sito.css`, `tema.css`,
tema e Stile del sito, senza testata né piede). È l'unica pagina con `frame-ancestors *` e senza `X-Frame-Options`
(`fb_intestazioni_ospitata()`, `:251`); ogni altra pagina resta `frame-ancestors 'self'` (gruppo 38 degli attacchi). Codici
(`:230-243`): iframe; HTML (`form_html()` con `esterno`: indirizzi interi, `data-base` sul form per la sfida del captcha,
`sito/assets/moduli.js:77`, e `api/captcha.php` con `Access-Control-Allow-Origin: *`, la sfida non è un segreto; foglio
`sito/assets/modulo-esterno.css`, colori fissi chiari dentro `.modulo`); HTML semplice (niente script né captcha,
`semplice=1`). QR: `sicurezza_qr_svg()` di `includes/sicurezza-qr.php` (il QR del secondo fattore, fatto in casa dalla 1.x:
modalità byte, livello M, versioni 1-10, fino a 213 byte), riusato. `form_html()` (`includes/form.php:492`) ha il quinto
argomento `$opz` (`ospitata`, `esterno`, `semplice`, `verifica`, `valori`, `contatto`, `riuscito`); senza, esce come prima.

**Disiscrizione e preferenze.** `newsletter_pagina_esci()` (`includes/newsletter-campagne.php:774`): con la campagna firmata
nel link usa la pagina scelta nella campagna (`nl_pagina_uscita_di_campagna()`, `nl_pagina_uscita_testi()`); con
`solo_lista` (acceso di partenza, anche nella predefinita) offre "Esci solo da «lista»" per ogni lista della campagna in cui
il contatto riceve (`nl_liste_campagna_contatto()`, `includes/newsletter-consenso.php:295`, `nl_esci_da_lista()`, `:309`:
riga in `newsletter_liste_uscite`, storia, innesco `lista_tolto`); una lista che non è della campagna, o una firma falsa, non
cambia niente. Centro preferenze (`newsletter_pagina_preferenze()`, `:840`): i gruppi di consenso (se accesi) con "Esci da
tutti i gruppi" (resta iscritto) e la casella del tracciamento; con un form di profilo scelto come centro preferenze,
`sito/index.php:188` rimanda (302 senza memoria) a `/modulo/<slug>/?i=&t=`, già compilato (`fb_valori_contatto()`,
`includes/form-brevo.php:336`).

**Gruppi di consenso nelle campagne.** Accesi, la sezione Destinatari ha la tendina del gruppo
(`admin/newsletter-campagna.php:320`) e senza gruppo la campagna non parte (`newsletter_campagna_sezioni()`,
`includes/newsletter-campagne.php:292`); `newsletter_campagna_where()` (`:164`) aggiunge `nl_camp_sql_gruppo()` (`:211`,
`nl_sql_gruppo()`: automatico = dentro se non c'è una riga a 0, manuale = solo con una riga a 1). Una campagna già
programmata senza gruppo parte come prima. La pagina di disiscrizione si sceglie in Impostazioni aggiuntive
(`admin/newsletter-campagna.php:461`). Duplica copia gruppo e pagina (`NL_CAMP_PARTI`).

**Consenso al tracciamento.** `newsletter_traccia()` (`includes/newsletter-flussi.php:202`) non mette pixel né link tracciati
per chi non acconsente (`newsletter_traccia_consentita()`, `:189`, con una piccola memoria per giro); `newsletter_evento()`
(`:223`) non segna aperture e clic dai link vecchi di chi ha tolto il consenso (il rimando funziona). I segmenti su aperture,
clic, link, attività e bivi delle automazioni contano solo chi acconsente (`nl_seg_tracciati()`,
`includes/newsletter-segmenti.php:50`); "non coinvolti" e "rimanda ai non aperti" non toccano chi non acconsente
(`newsletter_campagna_where()`). L'attributo TRACCIAMENTO si scrive anche dalla scheda del contatto e dall'importazione
(`newsletter_contatto_colonna()`, `includes/newsletter-contatti.php:438`). Il consenso cambia da: centro preferenze, form con
il blocco "Consenso al tracciamento", scheda del contatto. Ogni cambio di gruppo o di tracciamento va nel registro del
consenso (`nl_consenso_registra()`, `includes/newsletter-consenso.php:201`) e nella storia.

**Pagine e consenso** (`admin/newsletter-consenso.php`, voce del menu sotto Attributi dei contatti, solo amministratore):
scheda Gruppi (acceso o spento, crea, modifica, elimina con la conferma; i contatti dentro contati) e scheda Pagine di
disiscrizione (la predefinita resta in Newsletter > Impostazioni, con la casella nuova "Offri Esci solo da questa lista").

**Prove**: `prove/prova-form-brevo.js` con `prove/form-brevo-cli.php` (i cinque passi come un utente, la casella solo con un
attributo Sì/No, GDPR, pagina ospitata e intestazioni, liste offerte e una lista non del form, le tre conferme con il modello e
la pagina dopo il clic, aggiorna esistenti, iframe e codice HTML su un'altra origine con un secondo server PHP, HTML semplice
e pagina ospitata senza JavaScript, QR letto da `prove/qr-decodifica.py`, disiscrizione da una lista e pagina della
campagna, gruppi, tracciamento, form di profilo come centro preferenze, disiscrizione, pop-up, dopo l'invio a un indirizzo);
`prove/prova-form-vecchi.js` con `prove/form-vecchi-cli.php` (cinque form di prima seminati su una 2.15.0, aggiornata:
HTML al byte, email, pagine, iscrizioni e dati uguali; ogni passo salvato dal costruttore senza cambiare niente lo lascia
uguale); `prove/qr-decodifica.py` (un lettore QR scritto a parte: formato BCH, sindromi di Reed-Solomon, dati). Attacchi:
gruppo 38 di `tools/sicurezza-prova.js`. Rifatte per i passi: `prova-form-avviso.js` (passo 3), `prova-omaggio.js` e
`prova-email-conferma.js` (passo 4), gruppo 25 degli attacchi.

**Non fatto**: l'anteprima dal vivo del form nel costruttore (c'è "Apri la pagina del form"); il trascinamento dei blocchi
GDPR (stanno sempre in fondo, sopra il pulsante); le regole di comparsa del pop-up sono quelle dei Popup (secondi,
scorrimento, uscita), non quelle di Brevo, che le fonti non dicono; reCAPTCHA e Turnstile (differenza voluta: il captcha
resta quello in casa, e senza JavaScript una domanda); la conferma semplice usa un'email a testo (oggetto e testo nel passo
4), non un modello; nessun gruppo di consenso si sceglie nelle automazioni.

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
| `collegamenti_sito` | filtro | voci, ricerca, amministratore sì o no: i contenuti di un modulo per Scegli dal sito (2.2, capitolo 38) | `includes/collegamenti.php:97` |
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
  due pacchetti, prima il codice e poi il resto. `tools/consegna.py` lo controlla da solo (19.8).

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
`plumecms-ultima-aggiornamento`. Quale sito lo dice il codice (`versione_sito()`): `VERSIONE_SITO_UFFICIALE`
(`includes/versione-nuova.php:30`, `https://plumecms.danilofailli.it`; fino alla 1.6.0 `https://duhtron.it/test`), e l'indirizzo
arriva quindi con gli aggiornamenti stessi. Dalla 1.3.1 alla 1.6.1 c'era un campo in Aggiornamenti ("Indirizzo del sito da cui
arrivano gli aggiornamenti", impostazione `versione_sito`): dalla 1.6.2 non c'è più, decisione di Danilo, e l'impostazione di un
sito che l'aveva salvata si ignora. Solo per le prove, `CMS_AGGIORNAMENTI_URL` nel `config.local.php` lo sostituisce. La
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
- **Sito ufficiale e indirizzo del sito** (2.4.0): `VERSIONE_SITO_UFFICIALE` (`includes/versione-nuova.php`) e `FIRMA_URL`
  (`includes/firma.php`) sono `https://plumecms.it`; `firma_pulisci()` porta al nuovo una frase salvata con
  `plumecms.danilofailli.it`. In `admin/impostazioni.php` il riquadro **Indirizzo del sito** (`save_indirizzo`,
  `indirizzo_sito_salva()`): solo l'origine (schema, host, porta), la cartella resta `cms_base()`; prima di salvare `sito_url`
  scrive in `uploads/` un file `prova-indirizzo-<codice>.txt`, lo chiede al nuovo indirizzo con `server_http()` e lo toglie;
  senza risposta giusta non salva (salvo "Salva lo stesso"). Registro di sicurezza. Prova: `prove/prova-indirizzo-sito.js`
  (10 controlli, radice e cartella). I siti fino alla 2.3.2 chiedono gli aggiornamenti al vecchio dominio: la 2.4.0 si carica a mano.
- **Menu del pannello** (2.3.2): `admin/includes/admin-header.php` si include dentro ogni pagina, quindi ogni sua variabile è
  una variabile della pagina. Il menu ora usa solo nomi `$_menu...` e `$_m...` e li toglie con `unset` alla fine: prima `$sa` del
  sottomenu cancellava `$sa` (il service account) di `admin/seo.php`, e `$v`, `$nome`, `$x` potevano fare lo stesso altrove. Prova:
  `prove/prova-seo-service-account.js` (8 controlli; con il menu vecchio 4 falliscono).
- **Serie dei download** (2.3.1): `/scarica/<serie>/` (il nome con "ultima" al posto della versione) in `sito/index.php` risponde
  302 con `Cache-Control: no-store` verso `/scarica/<file della versione più alta>/`, che poi manda il file con `public,
  max-age=3600`. Prima mandava il file direttamente con la memoria di un'ora e il browser ridava la versione di prima. Prova:
  `prove/prova-download.js` (36 controlli, anche in cartella).
- **Dashboard e versione in vista** (2.3.0): `admin/includes/admin-header.php` scrive `cms_versione()` accanto al logo
  (`#admin-versione`, `.admin-versione`) e, sotto 900 px, accanto al pulsante del menu (`.admin-versione-tel`). Nel banner
  `#db-ufficiale` di `admin/index.php`, dopo il link al sito ufficiale, `#db-nuova` ("Nuova versione disponibile v. x.x.x", acceso
  da `versione_nuova()` o dal controllo del giorno in JS) e il form `#db-installa` (solo amministratore) con l'azione
  `versione_installa` di `admin/aggiornamenti.php`: `versione_scarica_e_prepara()` e poi lo stesso ramo di `applica` (snapshot,
  ripristino se fallisce). `versione_installabile_qui()` (`includes/versione-nuova.php`) è falso se esistono moduli del sito
  (`moduli/*/modulo.php`): allora il pulsante è "Scarica e prepara" (`versione_prepara`) e l'azione `versione_installa` rifiuta.
  Il vecchio riquadro `#db-versione` non c'è più. Prova: `prove/prova-installa-ora.js` (11 controlli, con `prove/finto-ufficiale.php`).
- **Certificato** (2.2.0): `versione_http()` e `server_http()` (`includes/controllo-server.php`) raccolgono tutti gli avvisi di PHP
  con `set_error_handler` (l'ultimo dice solo "failed to open stream"): un certificato scaduto, per un altro nome o di
  un'autorità sconosciuta si dice con il suo nome invece di "non risponde". Prova con HTTPS vero: `prove/prova-versione-https.py
  <cartella CMS> <pacchetto.zip>` (un'autorità di prova con openssl, `openssl.cafile`; 5 controlli).
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

- **Prepara la copia** (`copia_crea()`, `includes/copia-sito.php:195`): uno zip in `aggiornamenti/copie/` con
  `manifest.json` (formato `plumecms-copia`, versione del CMS, prefisso, sito, quante tabelle, righe e file e, dalla 2.1,
  `elenco_tabelle`: le tabelle della copia senza il prefisso), `db.sql.gz` (le tabelle di questa installazione, stesso
  formato degli snapshot: dalla 2.1 `copia_dump_db()`, `:163`, sulle tabelle di `copia_tabelle_sito()`, `:152`; prima
  `agg_dump_db($file, $prefisso)`) e le cartelle `uploads/`, `temi/`, `moduli/`. Fuori: `includes/config.local.php` e ogni
  file o cartella nascosti. Il manifest ha la firma HMAC-SHA256 dell'impronta dello zip (`sicurezza_pacchetto_impronta()`)
  con la chiave dei pacchetti di questo sito. Lo zip ha permessi 0600; si scarica solo dal pannello (**Scarica**), la
  cartella è vietata al web. Se ne tengono 6 (`copia_pulisci()`, `:102`; dalla 2.1 mai quella che si sta ripristinando:
  prima, con sei copie più nuove, la copia scelta spariva a metà del ripristino).
- **Due installazioni nello stesso database** (2.1): con i prefissi `cms_` e `cms_b_` le tabelle `cms_b_*` cominciano anche
  con `cms_`. `copia_tabelle_del_prefisso()` (`:129`) toglie le tabelle di un prefisso più lungo che ha sia `settings` sia
  `users` (`copia_altri_prefissi()`, `:136`): sono di un'altra installazione. Il ripristino (`copia_ripristina_db()`, `:298`)
  tocca solo le tabelle di `elenco_tabelle` (per una copia vecchia, la stessa regola applicata ai `CREATE TABLE` del suo
  database) e mai una tabella di un'altra installazione presente in questo database, qualunque cosa dica la copia; le righe
  saltate si contano ("Tabelle di un'altra installazione nello stesso database, lasciate stare"). Gli snapshot degli
  Aggiornamenti restano su tutto il database (`agg_dump_db()` senza prefisso).
- **Colonne generate** (2.1.0): `agg_colonne_generate($pdo, $tabella)` e `agg_riga_sql($riga, $generate)`
  (`includes/aggiornamenti.php`) scrivono `DEFAULT` al posto delle colonne `GENERATED ALWAYS AS` (`email_attiva` di
  `evi_iscrizioni`, `slot_attivo` di `pren_prenotazioni`), sia in `copia_dump_db()` sia in `agg_dump_db()`: prima l'INSERT
  falliva con l'errore 3105 e il ripristino finiva con errori. La forma `INSERT INTO ... VALUES (` resta quella che il
  ripristino accetta. Prova: `prove/prova-colonne-generate.php <cartella CMS>` (copia e snapshot, 5 controlli). Le copie e
  gli snapshot fatti prima della 2.1.0 con righe in quelle tabelle non si ripristinano interi.
- **Ripristina** (`copia_ripristina($file, $tieni)`, `includes/copia-sito.php:358`): da una copia caricata (che entra fra quelle sul
  server) o già sul server; serve la spunta di conferma. Prima fa una copia del sito com'è (`prima-del-ripristino`), poi:
  - nomi dello zip controllati (`copia_nome_voce_ok()`): solo `manifest.json`, `db.sql.gz` e file dentro `uploads/`,
    `temi/`, `moduli/`, niente `..`, percorsi assoluti, barre rovesce, parti nascoste; 300 MB per file, 2 GB in tutto,
    rapporto di compressione sotto controllo;
  - database (`copia_ripristina_db()`, `includes/copia-sito.php:298`): passano solo `DROP TABLE IF EXISTS`, `CREATE TABLE
    (...)` e `INSERT INTO ... VALUES` su tabelle con il prefisso della copia, una istruzione per riga, riscritte con il
    prefisso di questo sito; tutto il resto si salta e si conta. Le viste le crea lo schema del sito. L'impostazione
    `sito_url` resta quella di questa installazione;
  - file: si scrivono sopra quelli che ci sono. Un file eseguibile (`.php`, `.phtml`, `.phar`,
    `.php.jpg` e simili) entra solo da una copia firmata da questo sito e mai in `uploads/`; da una copia di un altro sito
    si salta, e il pannello lo dice;
  - (2.1) **ripristino pulito**: dopo i file, `uploads/`, `temi/` e `moduli/` diventano come nella copia. I file che ci sono
    nel sito ma non nella copia (un file saltato perché eseguibile conta come "nella copia" e resta) si spostano, non si
    cancellano, in `aggiornamenti/scarto/ripristino-AAAAMMGG-HHMMSS/` con il loro percorso e un `LEGGIMI.txt`
    (`copia_metti_da_parte()`, `:460`); le cartelle rimaste vuote si tolgono. Restano dove sono: i file e le cartelle
    nascosti (`.htaccess`), i collegamenti simbolici e ciò a cui portano (`copia_file_di()` non li elenca e non entra nelle
    cartelle collegate), i file dei temi del prodotto (`tema.json` con `"autore": "plumeCMS"`, `copia_tema_del_prodotto()`,
    `:448`: arrivano con gli aggiornamenti). Su un altro disco (`uploads_dir`) copia e cancella invece di `rename`.
    L'esito elenca i file spostati (riquadro `#cs-scartati`, fino a 200, e il registro, fino a 40).
  - (2.1) la casella **"Tieni i file in più"** (`tieni=1`, in tutti e due i moduli di ripristino) fa come prima della 2.1;
  - (2.1) lo scarto: cartella vietata al web come le copie (è dentro `aggiornamenti/`). Le sue cartelle restano
    `COPIA_SCARTO_GIORNI` (30) giorni: le toglie il ripristino dopo (`copia_scarto_pulisci()`, `:527`). La pagina ha la
    sezione "File messi da parte dai ripristini" (`#cs-scarto-elenco`, `copia_scarto_elenco()`, `:504`) con **Elimina**
    (azione `elimina_scarto`, POST con CSRF, solo il nome esatto `ripristino-\d{8}-\d{6}`: `copia_scarto_elimina()`, `:518`,
    rifiuta anche un nome che `basename()` cambierebbe), registrata in `sicurezza_log`;
  - alla fine `temi/.htaccess` si riscrive (`temi_proteggi()`). Se l'utente collegato non c'è più fra quelli della copia,
    si torna al login.
- **Limite di caricamento**: la pagina mostra il limite del server; il browser avvisa prima di mandare un file troppo
  grande, e il server spiega anche quando il file supera `post_max_size` (la richiesta arriva vuota, senza token). Una
  copia grande si mette con il gestore dei file in `aggiornamenti/copie/` (nome `copia-AAAAMMGG-HHMMSS-nome.zip`) e si
  ripristina dall'elenco.
- La pagina dice in un riquadro che la copia contiene dati sensibili e che la password del database non c'è.

---

### 19.8 Il pacchetto unico della consegna (dalla 2.1.0)

Regola di Danilo del 31 ottobre 2026: un file solo per aggiornamento. `tools/consegna.py` unisce in **un pacchetto** il codice
del CMS (le voci di `plumecms-<V>-aggiornamento.zip`) e il sito di plumeCMS (le voci di `sito-plumecms-<V>.zip`: pagine,
stile, header e footer, menu, nome del sito, immagini, e in Download gli zip del CMS e i manuali). Scrive
`releases/consegna/plumecms-<V>-pacchetto-unico.zip` e lo zip da dare a Danilo, `plumecms-<V>-consegna.zip`.

**Come si uniscono.**
- I file dei due pacchetti finiscono sotto `cms/` e `sito/` dentro lo zip (`prefissa()`); le destinazioni (`a`) restano
  quelle di prima: `new/...` per il codice, `uploads/...` per il sito.
- Il manifest unito (`unisci()`) ha `file` (prima il CMS, poi il sito), `elimina` (i due elenchi), `redirect`, e dal sito
  `impostazioni`, `editor`, `editor_ambiti`, `pagine`, `azioni` (`sito-pagine-e-stile`). Due file con la stessa destinazione
  fermano la costruzione.
- Lo scrive `tools/pacchetto.py`, con tutti i suoi controlli (destinazioni, sintassi PHP di ogni file con `short_open_tag`
  acceso, JSON delle pagine, azioni); con `PACCHETTO_CHIAVE` nell'ambiente esce firmato.

**Perché funziona con il motore installato.** Il pacchetto lo applica il motore che c'è già sul sito (`agg_applica()`,
`includes/aggiornamenti.php:595`):
- il manifest usa solo voci che `agg_leggi_manifest()` (`:136`) conosce dalla 1.2.3 e solo azioni di `AGG_AZIONI` (`:26`):
  `consegna.py` lo controlla (`VOCI_NOTE`, `AZIONI_NOTE`) e si ferma se ne trova altre;
- l'ordine di `agg_applica()` fa il resto: prima tutti i file (`:633`), il codice nuovo e i file del Download; poi elimina;
  poi impostazioni, stile, menu e pagine (`:694`); infine i redirect (`:700`);
- impostazioni, stile e pagine li importa il codice **di prima**: `sito.php` è caricato in cima al motore (`:20`) e
  `editor-opzioni.php` quando il manifest si legge (`:226`), cioè prima che i file nuovi si scrivano. Le pagine del sito di
  plumeCMS devono quindi essere leggibili anche dall'editor della versione installata, come per le parti di prima.

**Quando servono due pacchetti.** Se `includes/aggiornamenti.php` è diverso da quello dell'ultima versione in
`releases/cms` (`motore_cambiato()`; `--prima X.Y.Z` per sceglierla), o con `--due`, escono `plumecms-<V>-1-cms.zip` (l'aggiornamento
così com'è) e `plumecms-<V>-2-sito.zip` (il sito), e il LEGGIMI dice l'ordine. Senza una versione di prima in `releases/cms`
il confronto non si può fare: due pacchetti anche allora.

**`--senza-pagine`**: il pacchetto porta il CMS e i file del Download, ma non `impostazioni`, `editor`, `pagine` (pagine,
stile e menu del sito restano come sono).

**Cosa contiene la consegna**: il pacchetto unico (o i due), `cms/plumecms-<V>.zip` (lo zip completo per un sito nuovo),
`manuali/manuale-uso.pdf` e `manuali/manuale-temi.pdf`, `LEGGIMI.txt`. L'aggiornamento semplice per gli altri siti è dentro il
pacchetto unico, nel Download del sito ufficiale (`/scarica/plumecms-ultima-aggiornamento/`). Il pacchetto unico pesa
circa 13 MB: serve un server che accetti caricamenti di quella misura (quello di Danilo dal 31 ottobre 2026).

**Prove** (31 ottobre 2026, costruito in una copia del repository con la versione finta 2.0.9): `prove/prova-consegna-unica.js`
13 su 13, con dentro `prove/prova-sito-plumecms.js` 85 su 85 (87 su 87 con il blocco Scegli dai Media del sito, provato sul 2.0.3 nuovo), su un 2.0.3 nuovo e su un 2.0.3 con le cinque parti del sito della
2.0.0 già applicate; dopo, `prove/prova-scegli-media.js` 112 su 112 sul sito aggiornato.

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
4. svuota la coda IndexNow;
5. dall'UX di novembre 2026, `cron_bozze_vuote()` (`includes/cron.php`): mette nel cestino gli articoli e le pagine in bozza
   nati da più di 24 ore con il titolo e l'indirizzo dati dal pannello ("Nuovo articolo", `nuovo-articolo-2`), senza testo
   né struttura pubblicata, con `updated_at` entro cinque minuti da `created_at` e senza versioni in `editor_versioni` oltre
   a "creato dall'editor". Una bozza con dentro qualcosa non si tocca mai. Prova: `prove/prova-bozze-vuote.js`.
Scrive in `logs/cron-daily.log`, che si legge da Impostazioni > Log cron.

**`cron-newsletter.php`**, ogni 5 minuti: svuota la coda IndexNow (log in `logs/seo.log`) e fa un giro della
newsletter (log in `logs/newsletter.log`, capitolo 15.5); dalla 2.0.0, una volta al giorno, prima del giro, la pulizia
(`newsletter_pulizia()`: registro della posta oltre 60 giorni, iscritti mai confermati da oltre 30 giorni).

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

`node tools/manuali-pdf.js` scrive `releases/manuali/manuale-uso.pdf` (dalla 1.4.2) e `manuale-temi.pdf` (il manuale tecnico non si stampa più dalla 1.6.4): pandoc converte il
Markdown, Chromium (Playwright) stampa il PDF A4 con copertina, indice cliccabile e numeri di pagina. Carattere Carlito e
niente tag di accessibilità nel PDF: con i tag e il carattere Inter (variabile) il manuale tecnico pesava 2,1 MB, così 0,9. Si rifà a ogni
versione, dopo aver aggiornato i manuali.

Il manuale d'uso ha le schermate: la pagina si apre da un file scritto accanto al Markdown (`docs/.stampa-<nome>.html`,
tolto subito dopo), così `immagini/manuale-uso/...` si trova; ogni immagine sola in un paragrafo diventa una figura con
la didascalia (il testo alternativo); le checklist (`- [ ]`) diventano caselle.

Peso. Fino alla 2.0.3 il PDF doveva restare sotto 1900 KB (il limite delle parti del sito di plumeCMS) e dalla 2.0.0 il
manuale d'uso usciva in due file (`manuale-uso.pdf` e `manuale-uso-addon.pdf`, la Parte seconda). Dalla 2.1.0 è di nuovo
**un PDF solo**, Parte prima e Parte seconda insieme, con un tetto di 8 MB (`MAX_KB` in `tools/manuali-pdf.js`: sopra, la
costruzione finisce con un errore). Oggi 6,9 MB, 133 pagine; il manuale dei temi 557 KB. Lo script toglie dalla cartella di
uscita un vecchio `manuale-uso-addon.pdf`, così non si consegna per sbaglio.

**`tools/manuale-uso-schermate.js` (1.4.2).** Le schermate del manuale d'uso. Esegue gli esempi del manuale come una
persona, su un plumeCMS appena installato con il kit Attività (`prove/prova-kit.js ... attivita ...`), e fotografa ogni
passo con i pallini rossi numerati: scrive l'articolo "Chiusi per ferie" nell'editor e lo pubblica dalla scheda, ne
programma un altro, costruisce la pagina "Lavora con noi" (sezione a due colonne, immagine, elenco, pulsante, sezione
pronta, form), la mette nel menu, compone il riquadro in fondo agli articoli (1.6.0), crea un form, un popup, una campagna, un utente, un download. Se un passo non si può
fare, si ferma: il manuale e il pannello restano d'accordo. `node tools/manuale-uso-schermate.js <base> <utente>
<password> [capitolo]`. Le foto nascono PNG in `/tmp/manuale-uso-png` e diventano JPEG una volta sola in
`docs/immagini/manuale-uso/` (`stringi()`, costanti `LARGO` e `QUALITA`): dalla 2.1.0 a 1200 px e qualità 65 (circa 180 punti
per pollice sui 17 cm della pagina, le scritte piccole si leggono), prima 760 px e qualità 25; `--stringi` rifà solo la
conversione. Le foto d'esempio (disegnate, niente foto di
terzi) stanno in `esempi/manuale-uso/`.
Le immagini non entrano nello zip del CMS (solo il Markdown, in `docs/`).

### 23.5 Il sito di plumeCMS: `sito-plumecms/costruisci.py`

Il sito da cui si scarica plumeCMS, fatto con plumeCMS. Il costruttore scrive le pagine (home, funzionalità, temi,
documentazione, installazione, scarica, domande frequenti) come alberi dell'editor, lo stile, header, footer e menu, e
mette insieme i file da scaricare: i due zip del CMS, i due PDF, il tema Lavagna. Dalla 2.1.0 il risultato è **un
pacchetto solo** per Impostazioni > Aggiornamenti, `releases/sito/sito-plumecms-<versione>.zip` (circa 11 MB), che di solito
non si carica da solo: `tools/consegna.py` lo unisce all'aggiornamento del CMS (19.8). Il pacchetto toglie dal Download il
vecchio `manuale-uso-addon.pdf` e mette il redirect `/scarica/manuale-uso-addon/` -> `/scarica/manuale-uso/`.

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

Fino alla 2.0.3 il pacchetto si divideva in parti sotto 1900 KB, perché molti hosting non accettano caricamenti più grandi di
2 MB (`upload_max_filesize` e `post_max_size`, mostrati nei Controlli della pagina Aggiornamenti): un pacchetto da 5 MB è stato
rifiutato dal primo server vero, il 27 ottobre 2026. Il 31 ottobre 2026 Danilo ha alzato il limite del suo hosting: dalla 2.1.0
`MAX_KB` vale 200000 e tutto sta in un pacchetto (`dividi_e_costruisci()` mette tutto nella prima parte quando ci sta).
`--max-kb 1900` rifà le parti piccole, `sito-plumecms-<versione>-parte-<k>-di-<n>.zip`, per un server che accetta file piccoli.

### 23.3 `tools/sicurezza-prova.js`

Le prove di attacco, con Playwright:

```
CMS_DIR=<cartella del CMS installato> node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]
```

Il prefisso del pannello predefinito è `/new`; per un CMS nella radice si passa `""`. Prova XSS, CSRF, ruoli,
pagine aperte senza login, CSV, open redirect, forza bruta, blocchi per nome utente e IP di casa, upload di PHP e
SVG, file protetti, SQL injection, intestazioni, session fixation, cron dal web, firma della newsletter, CSP del
pannello, pacchetti non firmati, copia del sito, aspetto del pannello e impostazioni delle foto (1.4.0); il gruppo 21 la pagina Social (ruolo, token, Origin, contenuti inventati, titolo con HTML, immagine con `javascript:`) (1.5.0); il gruppo 22 il riquadro di fine articolo (pagina senza login, ruolo, token, Origin, editor, editor-api e versioni del riquadro per il redattore, bozza in anteprima per il redattore e per chi non è entrato) (1.6.0). Oggi: 201 prove respinte, due saltate (il test del burnout, del modulo danilofailli, e la newsletter firmata senza `SEGRETO`).

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
| `prova-sito-plumecms.js` | il pacchetto del sito di plumeCMS (dalla 2.1.0 anche il pacchetto unico) applicato dal pannello, poi ogni pagina e ogni file da scaricare |
| `prova-hero.js` | le ventisei disposizioni dell'Apertura (undici fino alla 2.16) al computer e al telefono: un solo h1, niente scorrimento orizzontale, foto caricate, contrasto di titolo e testo, pulsanti visibili (pagine create da `pagina-hero.php`) |
| `prova-aperture-nuove.js` | 2.17.0: i campi delle quindici disposizioni nuove sul sito (terminale, parola, video al clic, etichette, lente, forma, maniglia con la tastiera e il mouse, meno movimento) e nell'editor (campi solo con la loro disposizione, Scegli in modo video) |
| `prova-foto-effetti.js` | sezione Foto di Apertura e Presentazione e interruttore dell'onda, dall'editor al sito |
| `prova-aggiornamento.js` | aggiornare dal pannello con il pacchetto del prodotto |
| `prova-kit.js` | (1.3.0) installazione con un kit di partenza, poi ogni pagina del menu al computer e al telefono, in radice e in cartella |
| `prova-sezioni.js` | (1.3.0) la linguetta Sezioni dell'editor: clic, trascinamento, modifica, pubblicazione; `/sezioni-pronte/` (`pagina-sezioni.php`) con il contrasto |
| `prova-copia.js` | (1.3.0) copia del sito: prepara, scarica, ripristina, e gli zip da respingere (`zip-copie-prova.py`) |
| `prova-versione.js` | (1.3.0) "c'è una versione nuova" con due installazioni e un sito ufficiale finto che si comporta male (`finto-ufficiale.php`) |
| `prova-aspetti.js` | (1.4.0) i quattro aspetti del pannello: scelta in Il mio accesso, ogni pagina del menu e l'editor in ognuno, contrasto di ogni testo, colori rimasti scritti a mano |
| `prova-foto-leggere.js` | (1.4.0) foto più leggere: foto grande girata con il GPS, PNG piccola, WebP sul sito e originale per chi non la accetta, lato massimo, spento, newsletter, redattore, cancellazione (`foto-prova.php`, `pagina-foto.php`) |
| `prova-tipo-sito.js` | (1.4.0) il tipo di sito in prova dalla dashboard: cambio con la conferma, copia, pagine tenute e tolte, sito da visitatore, attacchi, Definitivo (`tipo-sito-cli.php`) |
| `prova-primi-passi.js` | (1.4.0) i Primi passi: email di prova con un server di posta finto (`smtp-finto.py`), logo e colori, cron vero e pulsante, nascondi |
| `prova-cron.js` | (1.4.0) la pagina Log cron, Esegui adesso, il cron vero, gli attacchi, e nessuna pagina del pannello che parla di WordPress |
| `prova-controlla-server.js` | (1.4.0) Controlla il server: i sette gruppi, protezioni spente (`logs/controllo-aperto` per il router), email, attacchi |
| `prova-favicon.js` | (1.4.0) l'icona di partenza e quella dello Stile, in radice e in cartella |
| `prova-fine-articolo.js` | (1.6.0) Aggiungi sempre alla fine: linguetta, crea, editor, pubblica, spegni e accendi, il riquadro sotto gli articoli del sito (28) |
| `prova-riquadri.js` | (1.4.0) Riquadri e Pulsante fisso: posizione e allineamento dall'editor al sito (`pagina-riquadri.php`) |
| `prova-scegli-media.js` | (2.1.0) Scegli dai Media su ogni campo immagine: cerca, sceglie, carica, salva, X, tastiera, 130 immagini a pagine, telefono (34) |
| `prova-consegna-unica.js` | (2.1.0) la consegna con il pacchetto unico: contenuto, manifest, applicato dal pannello con `prova-sito-plumecms.js`, pannello aggiornato (19.8) |
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
| `modelli_fine()`, `modelli_fine_acceso()`, `modelli_fine_articolo()`, `vista_fine_articolo($corpo)` | il riquadro in fondo agli articoli (28) |
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

## 28. Aggiungi sempre alla fine: il riquadro in fondo agli articoli (dalla 1.6.0)

Una richiesta di Danilo: in Articoli una linguetta che apre una pagina da cui si compone un riquadro ombreggiato che esce
sempre sotto gli articoli. Non è un meccanismo nuovo: il riquadro è un **modello dell'editor** (capitolo 11) di un tipo
in più, `fine`, accanto a `header` e `footer`. Ne eredita tutto: albero dell'editor, bozza e pubblicazione, versioni
(ambito `modello`), anteprima, esporta e importa. Le differenze sono tre: è uno solo, non ha condizioni né predefinito,
e non esce in cima o in fondo alla pagina ma dentro l'articolo.

### 28.1 Il modello `fine`

`MODELLI_TIPI` (`includes/modelli.php:28`) vale `header`, `footer`, `fine` ("Fine articolo"). La tabella
`modelli` non cambia: il tipo è una colonna di testo.

| Funzione | Cosa fa |
|---|---|
| `modelli_fine()` (`includes/modelli.php:483`) | il riquadro: il primo modello `fine` fuori dal cestino, o `null` |
| `modelli_fine_crea()` (`includes/modelli.php:489`) | lo crea se manca (in bozza, nome "Fine articolo") e ritorna l'id: due clic su Crea non ne fanno due |
| `modelli_fine_acceso($m)` (`includes/modelli.php:495`) | esce sul sito: `status = 'published'`, una struttura pubblicata, fuori dal cestino |
| `modelli_fine_dove_vederlo()` (`includes/modelli.php:501`) | `/slug/` dell'ultimo articolo pubblicato e già uscito (data e ora non nel futuro), o `''` |
| `modelli_cornice_fine($dentro, $id)` (`includes/modelli.php:511`) | la cornice: `<aside class="fine-articolo ed-modello" id="fine-articolo" aria-label="In fondo all'articolo">` |
| `modelli_fine_articolo()` (`includes/modelli.php:520`) | `[html, css]` del riquadro per l'articolo che si mostra, o `null` |
| `modelli_segnaposto_fine()` (`includes/modelli.php:557`) | la tela dell'editor: un articolo finto (righe grigie) con la cornice e, dentro, `#ed-pagina` |

**Struttura di partenza** (`modelli_crea()`, `includes/modelli.php:178`): una sezione larga quanto
l'articolo (`larghezza` `articolo`), senza spazio sopra e sotto (lo spazio lo dà la cornice), con un Titolo h3
"Prima di andare" e un Testo da riscrivere (chiave `contenuto`, quella del widget Testo). Gli spazi dei widget restano
quelli di partenza: il margine sopra il primo titolo e quello sotto l'ultimo widget li toglie il CSS del riquadro (28.2),
così un pulsante aggiunto sotto il Testo ha il suo spazio e il riquadro non ha un vuoto in fondo.

**Acceso e spento.** Non c'è una colonna nuova: acceso vuol dire pubblicato. Pubblica nell'editor chiama
`modelli_stato($id, true)` (`admin/editor-api.php`, come per header e footer); Spegni chiama `modelli_stato($id, false)`,
che rimette `status` a `draft` e lascia la struttura pubblicata dov'è; Accendi la rimette a `published`, solo se una
struttura pubblicata c'è. Ogni cambio di stato scrive una versione ("pubblicato", "messo in bozza").

**Quando esce** (`modelli_fine_articolo()`), in quest'ordine:
1. l'anteprima di una versione (Editor > Versioni, `$GLOBALS['modelli_versione']` di tipo `fine`): disegna quella;
2. `?preview=1&fine=ID` aperto da un amministratore (`modelli_anteprima_admin()`, `includes/modelli.php:361`:
   `SITO_ANTEPRIMA` e il ruolo `admin` letto dal database): disegna la **bozza** di quel modello, anche spento. È
   quello che usano l'anteprima della pagina e la tela dell'editor;
3. altrimenti il riquadro, solo se `modelli_fine_acceso()`.

Un riquadro che disegnato non ha né testo né immagini, video, form, pulsanti o link non esce. Un errore finisce nel log
(`modelli fine: ...`) e l'articolo esce senza riquadro.

### 28.2 Sul sito

`sito/index.php` (`sito/index.php:225`), solo per gli articoli (genere `contenuto`, `$c['tipo'] === 'post'`), dopo
`vista_articolo_editor()` o `vista_articolo()`: `[$corpo, $cssFine] = vista_fine_articolo($corpo)`, e il CSS va
nell'head. Pagine, home, archivi, glossario non lo ricevono.

`vista_fine_articolo($corpo)` (`sito/viste.php:245`) cerca l'`<article class="corpo">` (la colonna di lettura) e mette
il riquadro **prima del `</article>` che lo chiude**, dopo il testo e le domande frequenti: `vista_chiusura_article()`
conta gli `<article>` annidati, così il riquadro non finisce dentro l'ultima scheda di una Griglia di articoli. Senza
colonna di lettura:
- un articolo dell'editor a tutta pagina (sezioni non "Articolo") lo riceve dopo il corpo, dentro
  `<div class="wrap ed-stretta fine-articolo-fuori"><div class="corpo">`, largo quanto la lettura (748 px) e con i
  caratteri della lettura, come nella tela dell'editor;
- la vista di un tema che non scrive `class="corpo"` né `ed-pagina` lo riceve prima del suo ultimo `</article>` (o in
  fondo, se non ne ha).
Il CSS dell'editor esce in `<style id="ed-css-fine">`.

L'aspetto (`sito/assets/sito.css`, `sito/assets/sito.css:194`): margine sopra 3em, padding 28px 30px
(22px 18px sotto i 640 px), `border-radius: var(--r)`, sfondo `var(--bg-2)`, bordo `var(--bordo)`, due ombre. Sono le
variabili dello Stile del sito: i temi che le ridefiniscono cambiano anche il riquadro senza saperlo. Dentro il
riquadro il primo titolo non ha margine sopra e l'ultimo widget non ha margine sotto. I link: la regola dei link
dell'articolo è diventata `.corpo a:not(:where(.fine-articolo a)),:where(.fine-articolo) .corpo a`, con lo stesso peso
di prima (fuori dal riquadro non cambia niente); dentro il riquadro i widget tengono i loro colori come in una pagina
(un pulsante Pieno viola ha il testo bianco, non il ciano dei link), i link del testo restano quelli dell'articolo.
Valgono invece le altre regole di `.corpo` (per esempio `.corpo img{background:#fff}`: un PNG trasparente esce su
bianco, come negli articoli dell'editor).

### 28.3 Nel pannello

- **La linguetta** in Articoli (`admin/contenuti.php`, `admin/contenuti.php:82`, `#ct-fine-articolo`), solo
  se `is_admin()`.
- **La pagina** `admin/fine-articolo.php`, `require_admin()` (il redattore non la apre: non è in `AUTH_REDATTORE_PAGINE`),
  ogni POST con `verify_csrf()`. Ripete le linguette di Articoli con i conteggi, poi lo stato (Non ancora creato, Spento,
  Acceso) e le azioni: `crea` (`modelli_fine_crea()` e redirect all'editor), `accendi` (solo con un riquadro già
  pubblicato e se `editor_puo_pubblicare()`, l'opzione "Chi pubblica" del 9.10), `spegni`. Pulsanti: `#fa-crea`,
  `#fa-modifica`, `#fa-accendi`, `#fa-spegni`, Vedi sotto l'ultimo articolo, Versioni (`versioni.php?ambito=modello`).
  Sotto, l'iframe `#fa-anteprima` sull'ultimo articolo con `?preview=1&fine=ID#fine-articolo`.
- **L'editor** (`admin/editor.php`, `admin/editor.php:51`): per un modello `fine` la freccia e
  l'elenco tornano a `fine-articolo.php`, e "Vedi sul sito" apre l'ultimo articolo con `?preview=1&fine=ID`
  (la home se non ci sono articoli). La tela (`admin/editor-anteprima.php`) usa `modelli_segnaposto_fine()`.
- **Header e footer** (`admin/modelli.php`) conta solo header e footer: il riquadro non compare lì e non dà avvisi.

### 28.4 Esporta e importa

`editor_esporta()` mette il riquadro fra i `modelli` del file, con tipo `fine`. All'import
`editor_importa_chiave_modello()` (`includes/editor-opzioni.php:164`) gli dà la chiave
`fine|`, qualunque sia il nome: c'è un solo riquadro, e il file aggiorna quello che c'è (o lo crea). Un tipo sconosciuto
resta un header, come prima della 1.6.0.

### 28.5 Prove

`prove/prova-fine-articolo.js`: dal pannello al sito, nel browser. Il capitolo 16 del manuale d'uso lo esegue anche
`tools/manuale-uso-schermate.js` (chiave `15f`).

## 29. Il sito su misura (dalla 1.7.0)

Una richiesta di Danilo: chi installa scrive che sito vuole (che attività, quante pagine e di che tipo) e il sito si
compone da solo, con immagini finte da sostituire. Strada scelta: un compositore a regole, senza intelligenza
artificiale e senza chiamate fuori dal server. È un **kit di partenza** (capitolo dei kit, `includes/kit-partenza.php`)
come gli altri cinque, con una differenza: i suoi dati non stanno in un file di `includes/kit/`, li compone
`su_misura_kit()` dalle risposte. Tutto il resto (registro `kit_prova`, impronte, cambio di tipo con copia del sito,
Definitivo) è quello dei kit.

### 29.1 Le risposte

`includes/sito-su-misura.php`. Le costanti: `SU_MISURA_PAGINE` (`:34`, otto pagine facoltative, ognuna `[nome, slug,
spiegazione, scelta all'inizio]`; la home c'è sempre), `SU_MISURA_MAX_SERVIZI` (6), `SU_MISURA_CARTELLA`
(`segnaposto`), `SU_MISURA_IMPOSTAZIONE` (`su_misura_risposte`, l'impostazione dove stanno).

| Funzione | Cosa fa |
|---|---|
| `su_misura_attivita()` (`:49`) | le dieci attività (negozio, ristorante, studio, artigiano, coach, benessere, creativo, alloggio, associazione, altro): nome, icona, invito, nome del listino e della galleria, titolo e sottotitolo dell'apertura, sei servizi tipici, sei soggetti delle foto, testi di Chi siamo, tre valori, quattro numeri, quattro domande frequenti, tre recensioni |
| `su_misura_risposte_vuote()` (`:175`) | attività vuota, pagine scelte all'inizio (Chi siamo, Servizi, Galleria, Contatti) |
| `su_misura_pulisci($p)` (`:182`) | mai un errore: attività fuori elenco => `altro`; città fino a 80 caratteri, servizi fino a 6 da 60, senza HTML, spazi doppi, doppioni ed emoji; pagine solo fra quelle di `SU_MISURA_PAGINE`, nel loro ordine |
| `su_misura_senza_4byte()` (`:201`) | toglie i caratteri a 4 byte: un servizio diventa titolo e slug di una pagina (come entità uscivano scritti, `&#127837;`, nei titoli delle schede) |
| `su_misura_risposte()` / `su_misura_salva_risposte()` (`:206`, `:211`) | leggono e scrivono l'impostazione, sempre passando da `su_misura_pulisci()` |
| `su_misura_quante_pagine($r)` (`:216`) | il conto per il messaggio del pannello (home compresa, blog escluso, una per servizio se richiesto) |
| `su_misura_campi_html($r, $h)` (`:492`) | le quattro domande come campi HTML (classi `sm-*`), le stesse per l'installatore (`$h` = `ih`) e il pannello (`$h` = `h`); nessuna attività scelta all'inizio |

### 29.2 Le immagini finte

`su_misura_foto($attivita, $n, $formato)` (`:252`) scrive una volta sola
`<uploads>/segnaposto/<attività>-<n>-<formato>.svg` e ritorna `['url', 'file']`. Formati (`SU_MISURA_FORMATI`, `:227`):
`largo` 1600x900, `quadro` 1000x1000, `ritratto` 900x1100. Il disegno: sfumatura dei colori primario e secondario dello
Stile del sito scuriti (`su_misura_colori()`, `su_misura_scurisci()`: la scritta bianca si legge sempre), puntini, una
luce, l'icona dell'attività (`icona_svg()`), il soggetto ("La sala") e "Sostituisci questa foto". Nel formato largo, lo
sfondo delle aperture, icona e scritte stanno in alto a destra, lontano dal titolo. I file entrano nel registro del kit
come `['t' => 'immagine', 'file' => ...]` (`kit_costruisci()`, `includes/kit-partenza.php:218`, chiave `segnaposto` del
kit): `kit_impronta()` li riconosce con la stessa espressione di `su_misura_file_valido()` (`:543`), e al cambio di tipo
quelli che nessuna pagina mostra più si cancellano (`kit_immagine_usata()`, `includes/kit-partenza.php:322`).

### 29.3 Il kit

`su_misura_kit(?array $r)` (`:305`) ritorna un kit con `nome`, `form`, `home`, `pagine`, `menu`, `segnaposto`, scritto
nella forma corta delle sezioni pronte (`['imp' => ..., 'widget' => [[tipo, [impostazioni]]]]` o `'colonne'`), che
`kit_sezioni()` passa da `editor_valida()`. **Attenzione**: `editor_valida()` toglie in silenzio chiavi e widget che non
conosce (il widget `modulo` senza il form nel database, una chiave sbagliata come `id_css` al posto di `ancora`):
quando si cambia il kit si contano i widget scritti e quelli validati, pagina per pagina.

- **Home**: apertura Copertina (foto finta larga, occhiello con nome del sito e città, invito e "I servizi"), schede dei
  servizi, presentazione con la foto ritratto, numeri, galleria e recensioni se scelte, invito finale a sfondo sfumato.
- **Servizi**: una pagina con una sezione per servizio (ancora = slug del servizio, `/servizi/#slug`), oppure, con
  `ogni_servizio`, una pagina per servizio (`/slug/`) e le voci figlie sotto Servizi nel menu. Uno slug che coincide con
  un'altra pagina prende quello di riserva `servizio-N` (`su_misura_slug()`, `:520`).
- **Contatti**: form `contatti`, recapiti, mappa (`fornitore` google, `indirizzo` = città) solo se la città c'è.
- **Menu**: home, le pagine scelte nell'ordine di `SU_MISURA_PAGINE`, Blog solo se scelto (la pagina del blog c'è
  sempre). Il costruttore del menu dei kit accetta ora voci figlie: `[etichetta, url, [[etichetta, url], ...]]`.

### 29.4 Dove si risponde

- **Installatore**, passo 4 (`install/index.php:246`): scelta la carta `su-misura` e premuto Installa, se il POST non ha
  `sm=1` la pagina mostra le domande (ancora passo 4, niente scritto); con `sm=1` le risposte pulite vanno in
  `$_SESSION['inst']['su_misura']` e l'installazione parte. Prima di `kit_costruisci()` le risposte si salvano
  nell'impostazione (`install/index.php:318`), perché `kit_leggi('su-misura')` le legge da lì.
- **Pannello**, `admin/sito-su-misura.php` (solo amministratore, solo con il tipo di sito in prova): GET mostra le
  domande compilate con `su_misura_risposte()`; POST (`verify_csrf()`) salva le risposte e chiama
  `kit_cambia('su-misura', ...)` (`admin/sito-su-misura.php:25`), che dalla 1.7.0 accetta il kit uguale a quello in prova
  solo per `su-misura` (`includes/kit-partenza.php:351`). Se il cambio fallisce tornano le risposte di prima. La carta
  della dashboard (`admin/index.php:174`) è un link a questa pagina, non un form.

### 29.5 Sostituisci

`su_misura_sostituisci($url, $file)` (`:559`), chiamata da `admin/media.php:25` (azione `sostituisci`, solo
amministratore: tocca anche header e footer). Controlla che `$url` sia `cms_uploads_url()/segnaposto/...` con il nome
nel formato di `su_misura_file_valido()` e che il file esista; carica la foto con `media_carica()` (tipo letto dal
file, SVG ripulito, foto alleggerita) in `uploads/pagine/AAAA/MM/`; poi in `pages`, `articles` e `modelli`, in ogni colonna
di testo, `REPLACE` del vecchio indirizzo con il nuovo, scritto con le barre normali e con `\/` (il JSON); infine
cancella il file finto. Ritorna `['url', 'righe']` o `['errore']`. In Media la cartella si chiama **Da sostituire**
(`media_cartelle()`, `includes/media.php:31`) e un avviso dice quante ne restano. Una pagina in cui si è sostituita una
foto ha un'impronta nuova: a un cambio di tipo resta della persona.

**Primi passi** (`includes/primi-passi.php:53`): la voce `foto` ("Le tue foto al posto di quelle finte") compare solo se
il sito in prova è su misura o se qualche foto finta è ancora usata (`su_misura_segnaposto_usati()`, `:548`).

**Prove**: `prove/prova-su-misura.js` (installazione con le domande, sito, Media, Sostituisci con i rifiuti, sito
ricomposto dalla dashboard, Definitivo), gruppo 23 di `tools/sicurezza-prova.js`.

## 30. La frase in fondo al sito e il banner del sito ufficiale (dalla 1.8.0)

Richiesta di Danilo: in fondo a ogni pagina "Un nuovo sito plumeCMS" con il link a `https://plumecms.danilofailli.it`,
modificabile da chi installa; nella dashboard, sempre, "Visita il sito ufficiale plumeCMS".

**Motore**: `includes/firma.php`. `FIRMA_URL` (`:10`) è lo stesso indirizzo di `VERSIONE_SITO_UFFICIALE`
(`includes/versione-nuova.php`), scritto due volte per non caricare il motore degli aggiornamenti a ogni visita: se cambia
uno, cambia l'altro. L'impostazione `firma_sito` (JSON `{mostra, testo, url}`); senza impostazione vale
`firma_predefinita()` (`:14`): accesa, `FIRMA_TESTO`, `FIRMA_URL`.

| Funzione | Cosa fa |
|---|---|
| `firma_valori()` (`:19`) | la firma salvata, ripulita, o quella di partenza |
| `firma_pulisci($p)` (`:25`) | testo senza HTML, senza caratteri a 4 byte, fino a 120 caratteri; indirizzo vuoto (niente link) o `http(s)://` valido, altrimenti `FIRMA_URL` |
| `firma_salva($p)` (`:33`) | pulisce e salva |
| `firma_html()` (`:40`) | `<p class="firma-sito" id="firma-sito">`, con il link in una scheda nuova (`rel="noopener"`); `''` se spenta o senza testo |

**Sito**: `vista_pagina()` (`sito/viste.php:62`) aggiunge `firma_html()` in coda al piede, quindi esce anche con i temi
che ridisegnano la pagina (variabile `piede` di `viste/documento.php`) e con qualunque footer del Theme Builder. Stile
`.firma-sito` in `sito/assets/sito.css` (13 px, `--testo-2`, centrata).

**Pannello**: Impostazioni, riquadro "La frase in fondo al sito" (`admin/impostazioni.php:101`, azione `save_firma`,
`:33`, solo amministratore come tutta la pagina). Dashboard: `#db-ufficiale` (`admin/index.php:150`), per tutti i ruoli,
senza chiusura.

**Prove**: `prove/prova-firma.js` (14 controlli: banner, frase sul sito sotto il piede, cambio, senza link, HTML e
`javascript:`, spenta; alla fine rimette la frase com'era), gruppo 24 di `tools/sicurezza-prova.js`.

## 31. I widget per le attività (dalla 1.10.0)

Scelti da Danilo fra le quindici proposte dell'analisi dei widget del 30 ottobre 2026 (1, 2, 5, 6, 8, 11, 12). Stanno in
`includes/editor-widget-extra-d.php`, caricato in coda a `includes/editor-widget-extra.php`, con lo stesso contratto delle parti
A, B e C (`ewx_registra()`, classi `ew-*`, aspetto in `sito/assets/widget.css`, comportamento in `sito/assets/widget.js`).
I widget sono ora 87. Nessun servizio esterno: niente chiavi, niente chiamate dal server.

| Tipo | Nome | Gruppo | Note |
|---|---|---|---|
| `orari` | Orari di apertura | Elementi | sette campi di testo (`lun`...`dom`), ripetitore `chiusure` (dal, al, motivo) |
| `barra_contatti` | Barra contatti (telefono) | Elementi | `tel:`, `https://wa.me/`, indicazioni Google Maps (`/maps/dir/?api=1&destination=`), `mailto:` |
| `recensioni` | Recensioni | Marketing | voto, totale, fonte, ripetitore delle recensioni scritte a mano, pulsante verso la pagina delle recensioni |
| `prima_dopo` | Prima e dopo | Gallerie | due immagini, `--ew-pos` spostata da un `input type=range` invisibile sopra le foto |
| `squadra` | Squadra | Elementi | ripetitore delle persone; i link social solo `http(s)://`, l'email solo se valida, senza foto le iniziali |
| `slider` | Slider | Gallerie | ripetitore delle slide sul motore del carosello (`ewx_carosello_html()`), una per vista; sotto 640 px niente frecce |
| `eventi` | Eventi | Dal sito | ripetitore con data `AAAA-MM-GG`; ordinati per data e ora, i passati tolti sul sito (nell'anteprima si vedono, segnati) |

**Orari.** `ewx_orari_intervalli()` (`:17`) legge "9:00-13:00, 15:00-19:00", "9-13", "9.30 - 12.30", "24 ore"; "chiuso",
vuoto o un testo che non si capisce valgono giorno chiuso; un orario oltre la mezzanotte (18:00-02:00) vale anche per la
notte dopo. `ewx_orari_stato()` (`:50`) dà [aperto, frase]: le chiusure del giorno vincono, poi la notte di ieri, poi le
fasce di oggi; da chiuso cerca la prossima apertura nei sette giorni dopo, saltando le chiusure. Lo stesso calcolo è in
`widget.js` (funzione `orari`), con i dati in `data-ew-orari` e l'ora nel fuso del sito (`Intl.DateTimeFormat` con
`timeZone`): la frase si rifà ogni minuto anche su una pagina aperta a lungo. Dati per Google: `LocalBusiness` con
`openingHoursSpecification`, in un `<script type="application/ld+json">` dentro il widget (non è codice: la CSP non lo
blocca; `JSON_HEX_TAG` contro `</script>` nei testi). Nell'anteprima dell'editor i dati per Google non escono.

**Eventi.** Dati per Google `Event` (nome, `startDate`, luogo, descrizione, immagine) per gli eventi non passati, in un
`@graph`. **Recensioni**: nessun dato per Google, apposta (Google non mostra le stelle delle recensioni che un'attività
pubblica di sé). Le stelle del voto medio sono due file sovrapposte, quella piena larga quanto il voto (`width` in linea:
la CSP del sito ammette gli stili in linea).

**Barra contatti.** `position:fixed` in fondo; con "Solo sul telefono" (predefinito) sparisce sopra 767 px; il corpo della
pagina prende lo spazio in fondo (`body:has(.ew-barra-contatti)`), così il piede non resta coperto. Nell'editor è un
riquadro tratteggiato con una riga che spiega dove sta.

**Prove.** `prove/prova-widget-nuovi.js` (40 controlli: i calcoli degli orari a giorni e ore fissi con
`prove/orari-cli.php`, il sito al computer e al telefono, i dati per Google, l'elenco dell'editor), con la pagina di
`prove/pagina-widget-nuovi.php`; attacchi: gruppo 26 di `tools/sicurezza-prova.js` con `prove/widget-cattivi.php`
(testo cattivo in ogni campo, passato da `editor_valida()`).

## 32. Gli addon (dalla 2.0.0)

Un addon è un modulo (capitolo sui moduli, `docs-sviluppo/cms-moduli.md`) che arriva con il prodotto in `addon/<nome>/` ed è
spento finché l'amministratore non lo accende da **Impostazioni > Addon**.

**Motore.**
- `cms_moduli()` (`includes/cms.php:195`) carica prima `moduli/*`, poi gli addon accesi, con `'addon' => true` nel manifesto.
- L'elenco degli accesi sta in `moduli/addon-attivi.json` (`cms_addon_attivi()`, `includes/cms.php:211`):
  - non entra negli zip (`ESCLUDI_PREFISSI` di `tools/cms-pacchetto.py`), quindi un aggiornamento non accende e non spegne niente;
  - sta in `moduli/`, quindi la Copia del sito lo porta con sé.
- `cms_modulo_dir()` e `cms_modulo_url()` (`:222`) trovano gli addon in `addon/`.
- `includes/addon.php`:
  - `addon_disponibili()` (`:13`): tutti quelli sul disco, anche spenti, con `acceso`;
  - `addon_attiva($nome, $acceso)` (`:29`): accende anche quelli in `richiede`, rifiuta di spegnere un addon che un altro acceso usa, chiama `installa`;
  - `addon_scrivi()` (`:61`): scrittura atomica, file temporaneo e `rename`.
- Spegnere non cancella tabelle né dati.

**Pagine del pannello.**
- Tutte da `admin/addon-pagina.php?a=<addon>&p=<pagina>`: solo amministratore, 404 se l'addon è spento o la pagina non è nel manifesto (chiave `pannello`).
- In `admin-header.php`, `$qui` diventa `addon-pagina.php?a=..&p=..`, così le voci di menu degli addon si accendono.
- `admin/addon.php`: le carte con Accendi e Spegni; ogni azione in POST con CSRF, registrata in `sicurezza_log` (tipo `addon`).

**Chiavi nuove del manifesto.**
- `descrizione`, `richiede`.
- `cron_spesso`: passi del giro frequente, chiamati da `cron_newsletter()` (`includes/cron.php:111`).

**Ganci nuovi del nucleo.**
| Gancio | Dove | Chi lo usa |
|---|---|---|
| evento `sito_contenuto($c, $meta)` | `sito/index.php:236`, prima di disegnare un articolo o una pagina | Area riservata: pagina "Solo per i membri" |
| filtro `contenuto_riservato(null, $c)` | `includes/sito-dati.php:164`, in testa a `sito_html_contenuto()` | Area riservata: il testo riservato non esce in llms-full.txt né nei widget |
| evento `sito_pagina_servita($codice, $percorso)` | `sito/index.php:39`, risposte HTML | Statistiche |
| filtro `sitemap_voci($voci)` | `includes/seo.php:879` | Vetrina: negozio e prodotti nella sitemap (e quindi nella coda IndexNow) |
| (2.1) filtro `contenuti_elenco($tutti, $dove)` | `includes/sito-dati.php:155` (`sito_contenuti_elenco()`) | Area riservata: i riservati fuori da ricerca, feed, sitemap, llms (capitolo 36) |
| (2.1) filtro `scheda_riquadri` ed evento `scheda_salvata` | `admin/contenuto.php:285` e `:62` | Area riservata: "Solo per i membri" dalla scheda (capitolo 36) |
| (2.1) chiave `oauth_ritorno` del manifesto | `admin/oauth-ritorno.php` (file nuovo) | Copia altrove: il ritorno da Google e da Dropbox (capitolo 35) |

- La pagina Widget dice "addon <nome>" come origine (`editor_registra_widget()`, `includes/editor-controlli.php`).
- `editor_carica_widget_esterni()` carica ogni file dei widget dentro una funzione anonima statica: le variabili in cima a
  un file di widget (di un modulo o di un addon) non toccano più `$r`, `$f` e `$file` del caricatore. Prima un `$r` nel
  widget delle Prenotazioni sovrascriveva il registro e tutte le pagine del sito uscivano vuote.
- Le pagine degli addon hanno anche `assets/css/contenuti.css` (i riquadri `.sc-scatola`), da `admin-header.php`; le tabelle
  in un riquadro stretto stanno in `<div class="ad-tabella">` e scorrono lì dentro.
- Dal web, `addon/*/includes|sql|docs`, ogni `.php` di `addon/` e `moduli/addon-attivi.json` danno 403: `.htaccess`, gli esempi in `install/esempi/` e il router delle prove.

**Gli otto addon.** Il dettaglio di ognuno (tabelle, funzioni con file:riga, sicurezza, limiti) sta in `docs-sviluppo/addon/<nome>.md`.
| Addon | Cartella | Tabelle | Widget |
|---|---|---|---|
| Prenotazioni e appuntamenti | `prenotazioni` | `pren_servizi`, `pren_risorse`, `pren_prenotazioni` | Prenota |
| Eventi con iscrizione | `eventi-iscrizione` | `evi_eventi`, `evi_iscrizioni` | Iscrizione all'evento, elenco eventi |
| Raccolta recensioni | `recensioni` | `rec_inviti`, `rec_recensioni`, `rec_esclusi` | Recensioni dal sito |
| Vetrina prodotti con ordini | `vetrina` | `vetrina_prodotti`, `vetrina_varianti`, `vetrina_categorie`, `vetrina_prodotti_categorie`, ordini | Prodotti, Carrello |
| Statistiche delle visite | `statistiche` | `stat_visite`, `stat_impronte` | (riquadro della dashboard) |
| Area riservata | `membri` | `membri`, `membri_sessioni`, `membri_token` | Accesso membri |
| Corsi online (richiede `membri`) | `corsi` | `corsi`, `corsi_moduli`, `corsi_lezioni`, avanzamento | Elenco corsi |
| Copia del sito altrove | `copia-altrove` | `copia_altrove_registro` | (nessuno) |

Scelte comuni:
- doppie prenotazioni e posti degli eventi: transazione con `SELECT ... FOR UPDATE` più un indice UNIQUE;
- link personali firmati con `moduli_segreto()`;
- prezzi della Vetrina in centesimi, sempre ricalcolati dal database;
- Statistiche senza cookie e senza IP: impronta giornaliera con un sale che cambia ogni giorno;
- password della Copia altrove cifrata con una chiave derivata da `moduli_segreto()`.

**Prove.**
| Prova | Risultato |
|---|---|
| `prove/prova-addon-prenotazioni.js` | 60 |
| `prove/prova-addon-eventi-iscrizione.js` | 45 |
| `prove/prova-addon-recensioni.js` | 39 |
| `prove/prova-addon-vetrina.js` | 60 |
| `prove/prova-addon-statistiche.js` | 32 |
| `prove/prova-addon-membri.js` | 75 |
| `prove/prova-addon-corsi.js` | 58 |
| `prove/prova-addon-copia-altrove.js` | 39 |
| attacchi `prove/attacchi-addon-a.js` / `-b.js` / `-c.js` | 108 / 80 / 94 |
| `prove/prova-addon-tutti.js`: tutti accesi insieme, pannello e sito anche al telefono, poi spenti e sito uguale a prima | 66 |

Il modo di lanciarle è in `prove/LEGGIMI.md`.

### 32.1 Le novità degli addon nella 2.1

**Prenotazioni** (1.1.0, `docs-sviluppo/addon/prenotazioni.md`):
- Prenotazioni > Nuova prenotazione (`addon/prenotazioni/includes/admin-nuova.php`): l'attività scrive una prenotazione
  presa al telefono o di persona fra gli orari liberi del sito (senza gli anticipi), oppure forzando "Un altro orario"
  con l'avviso e una persona scelta; mai sopra un altro appuntamento della stessa persona (`pren_prenota_pannello()`,
  `addon/prenotazioni/includes/prenotazioni.php:412`, nella stessa transazione con blocco delle righe, più l'indice UNIQUE);
  email facoltativa, conferma solo con l'email e la spunta;
- il file .ics allegato alle email di conferma, spostamento (REQUEST) e annullamento (CANCEL), con lo stesso UID e la
  SEQUENCE che cresce (`pren_ics()`, `:610`; colonna `sequenza`), con `posta_invia(..., $allegati)` della 2.0.3;
- colonne `origine` e `sequenza` aggiunte da `pren_colonne_nuove()` (`:108`) ai siti della 2.0.0.

**Eventi con iscrizione**: eventi per gli altri addon `evento_svolto` (dal cron, una volta per evento, colonna
`svolto_at`), `iscrizione_presenza`, `iscrizione_cancellata`, `evento_cambiato`, `evento_eliminato`
(`docs-sviluppo/addon/eventi-iscrizione.md`). Aggiornando, gli eventi già finiti si segnano svolti senza annunciarli.

**Raccolta recensioni** (1.1.0): l'invito "Com'è andata?" anche dopo gli eventi, ai presenti o a tutti gli iscritti
(tranne gli assenti), `giorni_dopo` giorni dopo la fine; le presenze segnate dopo l'evento contano
(`docs-sviluppo/addon/recensioni.md`, "Dopo gli eventi"). Nessuna dipendenza: con Eventi spento non arriva niente.

**Corsi online** (1.1.0): il testo delle lezioni con l'editor visuale, come contenitore dell'editor (9.12); le lezioni
di prima entrano nell'editor in bozza, in un widget Testo o, se il widget perderebbe qualcosa, in un widget HTML; la
colonna `testo` resta. Il programma si riordina trascinando (SortableJS del pannello, `addon/corsi/assets/programma.js`),
`corsi_riordina()` tutto o niente (`addon/corsi/includes/corsi.php:374`); le frecce restano per la tastiera.

File del nucleo toccati nella 2.1 per questi addon: `includes/editor.php`, `includes/versioni.php`, `admin/editor.php`,
`admin/editor-api.php`, `admin/editor-anteprima.php`, `admin/versioni.php`, `admin/assets/editor/editor.js` (solo i
contenitori, 9.12).

| Prova | 2.0.0 | 2.1 |
|---|---|---|
| `prove/prova-addon-prenotazioni.js` | 60 | 85 |
| `prove/prova-addon-eventi-iscrizione.js` | 45 | 47 |
| `prove/prova-addon-recensioni.js` | 39 | 51 |
| `prove/prova-addon-corsi.js` | 58 | 80 |
| attacchi `prove/attacchi-addon-a.js` / `-b.js` | 108 / 80 | 127 / 89 |
| `prove/prova-addon-tutti.js` | 66 | 70 |

## 33. File riservati e il file dato dai form (dalla 2.0.0)

**File riservati.**
- Un file riservato di Download sta in `uploads/riservati-<16 esadecimali>/`:
  - nome della cartella nell'impostazione `scaricabili_riservati`;
  - dentro, un `.htaccess` che nega tutto e un `index.html` vuoto (`scaricabili_dir_riservati()`, `includes/scaricabili.php:64`);
  - il router delle prove emula il `.htaccess`.
- Non ha un indirizzo pubblico: non entra nelle serie e il widget Download non lo mostra.
- `scaricabili_rendi_riservato()` (`:316`) lo sposta fra le due cartelle.

**Link personale.**
- Forma: `/scarica/<nome>/?k=<iscritto>.<scadenza>.<firma>`.
- Firma: `scaricabili_firma()` (`:331`), HMAC-SHA256 di `scarica|nome|id|scadenza` con `moduli_segreto()`, 32 caratteri.
- Validità: `scaricabili_giorni()` (`:326`, impostazione `scaricabili_giorni`, 30 giorni di partenza).
- Lo crea `scaricabili_link_personale()` (`:340`), che segna anche la consegna in `scaricabili_consegne` (PK file + iscritto).
- `scaricabili_verifica_chiave()` (`:352`) restituisce l'id dell'iscritto, 0 se il link è scaduto, null se è falso.

**Come si serve** (`sito/index.php`, dopo la rotta pubblica di `/scarica/`, e `sito_scaricabile_riservato()`, `includes/sito.php:264`):
- con una chiave valida, il file con `Cache-Control: private, no-store` e `Referrer-Policy: no-referrer` (`sito_manda_scaricabile()`, `:277`), e il download si conta per iscritto, senza i robot (`scaricabili_conta_consegna()`, `:359`);
- con una chiave scaduta, 410 e una pagina che spiega cosa fare;
- altrimenti 404.

**Il file dato dai form.**
- Colonna `moduli.omaggio` (`form_schema()`). La valida `form_salva()`: deve essere un file riservato, e serve una lista.
- In `form_ricevi()`:
  - con la conferma da fare, il file arriva alla conferma;
  - chi è già attivo lo riceve subito per email: sulla pagina no, così nessuno lo prende scrivendo l'email di un altro;
  - senza conferma, per email e sulla pagina di ringraziamento.
- Lo stato passa ad `api/modulo.php` con `form_ultimo_omaggio()` (`includes/form.php:379`) e va nell'indirizzo di `/grazie/` (`o`, `k`). `form_grazie_html()` (`:363`) mostra il pulsante solo se la chiave è valida.
- La conferma di un form (`newsletter_ultima_conferma()`, capitolo 15.5) ha la sua pagina, `form_conferma_html()` (`:402`); con il file chiama `form_omaggio_consegna()` (`:389`).

**Prove:** `prove/prova-omaggio.js` (27, con `prove/omaggio-cli.php`), gruppo 27 di `tools/sicurezza-prova.js` (23 attacchi).

## 34. Scegli dai Media, immagine social e pannello al telefono (dalla 2.0.0; Scegli dai Media dappertutto dalla 2.1.0)

**Scegli dai Media** (2.0.0 sulla sola immagine social; dalla 2.1.0 su ogni campo immagine del pannello fuori dall'editor
visuale, che ha la sua finestra). Una finestra sola per tutto il pannello, in `admin/assets/pannello.js` (dalla riga 133),
stili `.pn-media` e `.pn-immagine` in `admin/assets/css/admin.css:363`.

*Il campo.* Un `<input type="text" data-immagine>` diventa, al caricamento della pagina (`preparaCampo()`, `pannello.js:353`):
miniatura (`.pn-immagine-mini`, 44 px; vuota con l'icona, "!" se l'indirizzo non si apre), la casella, il pulsante
**Scegli dai Media** (`data-scegli-media="#id"`) e la X (`.pn-immagine-x`, nascosta col campo vuoto). Il campo riceve un id se
non ce l'ha. Scrivere a mano aggiorna la miniatura dopo 300 ms; scegliere o togliere manda `input` e `change`
(`scriviCampo()`, `:340`), così le pagine che ascoltano il campo se ne accorgono. Pulsanti e X vanno a capo insieme
(`.pn-immagine-azioni`). Attributi facoltativi del campo: `data-immagine-carica="pagina.php"` (dove si caricano i file nuovi)
e `data-immagine-assoluto` (l'indirizzo esce completo, con `https://` e il dominio). `window.pnMedia.prepara(elemento)`
prepara i campi aggiunti dopo.

*Dove.* SEO e AEO > Globale SEO, immagine social (`admin/seo.php:508`); Schema, logo e immagine dell'organizzazione (`:568`,
`:569`) e foto della persona (`:582`); Social > Calendario, le due immagini (`admin/social.php:155`, `:156`); Popup
(`admin/popup.php:62`); Corsi, immagine del corso (`addon/corsi/includes/admin-corso.php:84`); Eventi, foto
(`addon/eventi-iscrizione/includes/admin-eventi.php:71`). Usano la stessa finestra con il loro aspetto: la scheda dei contenuti
(immagine in evidenza e "+ Immagine" nel testo, `window.scMedia()` in `admin/assets/contenuto.js:140`, al posto del dialogo
`#sc-media` di prima), Stile del sito (logo, favicon, sfondo: `admin/assets/design.js:191`, al posto di `#dg-media`), la
Vetrina (sotto). Le immagini delle campagne della newsletter: il pulsante immagine dell'editor (Quill) apre la finestra
(`admin/newsletter-campagna.php:164`) con `assoluto` (le email vogliono indirizzi completi), `carica:
'newsletter-immagine.php'` (cartella `newsletter/`, senza WebP) e solo PNG, JPEG, GIF, WebP; prima apriva solo la scelta del
file dal computer. Nessun campo immagine in Impostazioni (la frase in fondo al sito ha testo e indirizzo, non un'immagine),
Membri, Recensioni, Prenotazioni, Statistiche, Copia altrove.

*La Vetrina* ha un elenco di foto, non un campo: il pulsante `data-scegli-media-lista="#vt-foto"`
`data-scegli-media-modello="#vt-foto-modello"` (`addon/vetrina/includes/admin-prodotto.php:88`) aggiunge a `#vt-foto` una copia
del `<template>`, con l'indirizzo negli elementi `[data-url-src]` (src) e `[data-url-valore]` (value): foto, `foto[]`,
Principale, Togli, come le foto già salvate. Il server tiene solo gli indirizzi di `uploads/` (`vt_foto_ammessa()`).

*La finestra* (`mediaApri()`, `pannello.js:321`; da codice `window.pnMedia.apri({scegli, attuale, carica, assoluto, accetta,
titolo})`):
- un solo `<dialog>` per pagina, creato al primo uso; ogni apertura riparte senza ricerca, con tutte le cartelle;
- le immagini da `editor-api.php` azione `media`, **60 per volta**, cercate (`q`, nel percorso) e filtrate per cartella dal
  server (`mediaPagina()`, `:247`); la ricerca parte 250 ms dopo l'ultimo tasto e annulla la richiesta di prima
  (`AbortController`); le pagine dopo arrivano scorrendo (`IntersectionObserver` sul fondo, `:219`) o con "Mostra altre
  immagini"; il conto dice "60 di 171 immagini". Dalla 2.1.0 l'azione `media` dà anche il peso (`admin/editor-api.php:202`);
- ogni immagine è un pulsante con nome, peso e cartella, `loading="lazy"`; quella già nel campo è segnata
  (`.pn-media-scelta`, `aria-current`);
- **Carica un'immagine** (`mediaCarica()`, `:292`): `XMLHttpRequest` con la barra di avanzamento, a `editor-api.php` azione
  `carica` (`media_carica()`, cartella `pagine/AAAA/MM/`) o all'indirizzo dato; il file trascinato sulla finestra si carica
  uguale; l'immagine caricata si sceglie subito. Gli errori del server (formato, peso, limite del server, 413) si leggono
  nella riga di stato (`role="status"`);
- "Oppure usa un indirizzo": una casella per un indirizzo scritto a mano;
- tastiera: si apre con il fuoco sulla ricerca; freccia giù porta alla griglia, una sola fermata di Tab (tabindex mobile,
  `fuoco()`, `:225`), frecce, Home, Fine, Pagina su e giù per muoversi, Invio sceglie; Esc o il clic sullo sfondo chiudono e
  il fuoco torna al pulsante che ha aperto;
- sotto 640 px la finestra prende tutto lo schermo (`100dvh`), la griglia ha colonne da 100 px e scorre da sola.

Il token CSRF per le chiamate sta in `<meta name="pn-csrf">` (`admin/includes/admin-header.php:109`), così la finestra
funziona anche nelle pagine senza un form. L'API resta quella dell'editor: `require_login()`, quindi anche il redattore
(che gestisce i media) sceglie e carica.

**Prove**: `prove/prova-scegli-media.js` (112: ogni campo, carica, salva e ricarica, X, scheda, Stile del sito, newsletter,
Vetrina, tastiera, 130 immagini a pagine, cartelle, indirizzo a mano, telefono), `prove/prova-pannello-telefono.js`.

**og:image.**
- `vista_pagina()` (`sito/viste.php:66`) rende intero un indirizzo che comincia con `/`, con lo schema, l'host e la porta di `cms_url_sito()`. Mai l'host della richiesta: un'intestazione Host falsa non entra nella pagina.
- Per le immagini dei Media, `vista_og_immagine_dati()` (`:44`) aggiunge `og:image:width`, `height`, `type` (con `getimagesize`) e `alt`.
- Dalla 2.0.3, in `vista_pagina()` (`sito/viste.php`):
  - le barre doppie nel percorso di `og:image` diventano una;
  - con `https` esce anche `og:image:secure_url`, e con il titolo `twitter:image`;
  - il tag `<html>` ha `prefix="og: https://ogp.me/ns#"`, come i siti WordPress con Yoast che LinkedIn legge.
  - Non provato contro LinkedIn: la sessione non lo raggiunge.

**Pannello al telefono** (`admin/assets/css/admin.css`, in fondo):
- `fieldset{min-width:0}`;
- i codici nelle tabelle vanno a capo;
- sotto 640 px la casella di ricerca delle barre in alto non impone 200 px e le tabelle scorrono dentro il loro riquadro.
- Dalla 2.0.1 nessuna pagina scorre di lato.
  - `.admin-layout` ha la colonna `minmax(0, 1fr)` e `.admin-main` ha `min-width: 0`. Prima la colonna `1fr` si allargava per il suo elemento più largo, e con lei testi e riquadri.
  - Sotto 640 px vanno a capo i form, `.ct-filtri`, `.fm-riga`, i `nav` e le file di schede (`*-schede`); `select` e campi non superano il contenitore.
  - Prova: `prove/prova-pannello-telefono.js`. Controlla ogni pagina del menu più quelle passate come argomento; `LARGHEZZA=` per provare altre misure.

## 35. Consegna delle email: dominio, servizi, mittenti, rimbalzi, riscaldamento (dalla 2.1.0)

Tappa 2 dell'email marketing (`docs-sviluppo/email-marketing-checklist.md`): far arrivare le email nella posta in arrivo.
Motore in `includes/consegna.php` (caricato da `includes/posta.php`), pagina Impostazioni > Consegna delle email
(`admin/consegna.php`, voce del menu in `admin/includes/admin-header.php:68`, solo amministratore), due indirizzi pubblici:
`api/posta-avvisi.php` (gli avvisi dei servizi) e `api/mittente.php` (la conferma dei mittenti).

### 35.1 Le tabelle e le impostazioni

`consegna_schema()` (`includes/consegna.php:38`), con l'impronta in `consegna_schema_impronta`:
- `posta_mittenti`: email (unica), nome, `rispondi_a`, `predefinito`, `verificato_at` (NULL finché non è confermato), `token`
  (sha256 del link di conferma) e `token_scade` (2 giorni);
- `posta_avvisi`: un avviso ricevuto per riga (quando, servizio, evento, email, campagna, esito in parole). `chiave` è unica:
  sha256 di servizio, identificativo dell'evento, tipo ed email. Lo stesso avviso mandato due volte conta una volta.

Colonne nuove: `newsletter_campagne.mittente_id` (`includes/newsletter.php`, in `newsletter_schema()`) e
`newsletter_flussi.mittente_id` (`includes/newsletter-flussi.php`, in `newsletter_flussi_schema()`), 0 = il predefinito, aggiunte
con `ALTER` anche alle tabelle nate prima (l'impronta dello schema cambia perché cambia il `CREATE`). Stato nuovo degli iscritti:
`spam`.

Impostazioni: `posta_servizio` (JSON: servizio scelto e dati di Brevo, SES, Mailgun, con le chiavi cifrate),
`posta_riscaldamento` (JSON), `posta_avvisi_sale` (cambia gli indirizzi dei webhook), `consegna_ultimo_controllo` (l'ultimo
controllo del dominio, per la scheda Da dove cominciare), `posta_sns_cert_<md5>` (i certificati di Amazon SNS già scaricati).

### 35.2 Chiavi cifrate

`consegna_cifra()` / `consegna_decifra()` (`:85`, `:97`): AES-256-GCM con `openssl` (`v1:` + base64 di iv, tag, testo), oppure
`sodium_crypto_secretbox` se manca openssl (`v2:`). La chiave non si salva: è HMAC-SHA256 di una frase fissa con
`moduli_segreto()` (`consegna_chiave()`, `:80`). Se il segreto del sito cambia, le chiavi non si aprono più (`''`): il pannello
chiede di scriverle di nuovo. Le chiavi tornano in chiaro solo dentro `consegna_segreto()` (`:132`), per spedire o per
controllare una firma; il pannello mostra solo "salvata il ...", i campi sono `password` vuoti e un campo vuoto tiene la chiave.

### 35.3 Il controllo del dominio

`consegna_controlla_dominio($dominio, $selettore, $servizio)` (`:607`) legge con `consegna_dns()` (`:550`, `dns_get_record()`;
con `CMS_DNS_PROVA` un file JSON di record finti) e ritorna una voce per SPF, DKIM, DMARC, MX con stato (`ok`, `attenzione`,
`manca`), frase, record trovati, record da scrivere (tipo, nome, valore) e dove si fa, più il messaggio da mandare all'hosting.
- **Il DNS non risponde**: `consegna_dns_risponde()` (`:573`) cerca gli NS del dominio e, se mancano, quelli di `iana.org`;
  senza risposta la pagina lo dice e non inventa niente.
- **SPF**: uno solo (più record si uniscono con `consegna_spf_unisci()`, `:767`, ogni pezzo una volta, `-all` se c'era), niente
  `+all`, l'`include` del servizio scelto (`consegna_spf_servizio()`, `:593`: `spf.brevo.com`, `amazonses.com`, `mailgun.org`;
  per SMTP `+a +mx` e l'IP pubblico del server SMTP se si risolve), avviso oltre 9 meccanismi che chiedono il DNS.
- **DKIM**: i selettori di `CONSEGNA_SELETTORI` (`:30`: default, mail, cpanel, k1, k2, google, selector1/2, brevo1/2, mailo,
  smtp, krs, s1, s2, dkim, mx) più quello scritto, che va per primo; `p=` vuoto (firma ritirata) e chiavi RSA corte (meno di 300
  caratteri di base64, 1024 bit o meno) sono da sistemare. Dove si attiva dipende dal servizio (cPanel, Brevo, SES, Mailgun).
- **DMARC**: `_dmarc.<dominio>`, uno solo, con `p=`; proposto `v=DMARC1; p=none; adkim=r; aspf=r` con `rua` solo se l'indirizzo
  degli avvisi è dello stesso dominio.
- **MX**: senza, le risposte non arrivano.
- **Posta gratuita** (`CONSEGNA_GRATUITI`, `:32`): avviso, perché il loro DMARC fa rifiutare le newsletter spedite da altri server.

### 35.4 Spedire con le API

`posta_invia()` (`includes/posta.php:215`) chiede `consegna_servizio_attivo()` (`:138`: il servizio scelto **e** con i dati
completi, altrimenti `''` e si spedisce come prima) e chiama `consegna_invia_api()` (`:247`). Il mittente lo sceglie
`consegna_mittente_per()` (`:410`). Gli indirizzi veri delle API passano da `consegna_url_api()` (`:163`): con
`CMS_POSTA_API_PROVA` nel config (solo prove) schema e host diventano quelli del server finto. HTTP con curl, o con gli stream
di PHP se curl manca (`consegna_http()`, `:171`), certificati controllati, niente rimandi seguiti.
- **Brevo**: `POST https://api.brevo.com/v3/smtp/email`, intestazione `api-key`, JSON con `sender`, `to`, `subject`,
  `htmlContent` e `textContent` (da `posta_testo_da_html()`), `replyTo`, `headers` (List-Unsubscribe, X-Plume-Rif e
  `X-Mailin-custom` con il riferimento), `attachment` (nome e base64).
- **Amazon SES**: `POST https://email.<regione>.amazonaws.com/v2/email/outbound-emails` (API v2 `SendEmail`) con il messaggio
  MIME intero in `Content.Raw.Data` (`consegna_mime_intero()`, `:319`, sopra `posta_mime()`), il tag `plume` con il riferimento
  (`EmailTags`) e il set di configurazione se scritto. Firma **AWS Signature Version 4** scritta a mano (`consegna_sigv4()`,
  `:197`): verificata con i vettori di AWS `get-vanilla` e `get-vanilla-query-order-key-case` (`prove/consegna-cli.php sigv4`).
- **Mailgun**: `POST https://api.eu.mailgun.net` (o `api.mailgun.net` per gli account US) `/v3/<dominio>/messages.mime`,
  Basic `api:<chiave>`, multipart con `to`, `message` (il MIME intero) e `v:plume` (il riferimento).
- Errori in parole (`consegna_errore_api()`, `:310`): 401 e 403 "non accetta la chiave", 429 troppe email (riprova al giro dopo),
  5xx problema del servizio, altro con il messaggio del servizio. L'esito è `errore` (le API non dicono subito se l'indirizzo
  esiste: lo dicono dopo, con gli avvisi).
- Lo SMTP di Brevo non ha bisogno di niente: si scrive in Configurazione email (`smtp-relay.brevo.com`, 587, STARTTLS). Il
  pannello lo spiega sia lì sia in Come partono; `consegna_servizio_scelto()` (`:152`) lo riconosce dall'host per i testi.

**Il riferimento.** Ogni email di campagna o di flusso porta `X-Plume-Rif: c<campagna>-f<flusso>-p<passo>-i<iscritto>-<firma>`
(`consegna_rif()`, `:337`, HMAC con `moduli_segreto()`; scritto da `newsletter_email()`, `includes/newsletter.php:695`). Torna
con gli avvisi (`X-Mailin-custom` di Brevo, tag `plume` di SES, `user-variables.plume` di Mailgun) e `consegna_rif_leggi()`
(`:343`) lo accetta solo con la firma giusta.

### 35.5 Mittenti

`consegna_mittente_aggiungi()` (`:433`) valida l'indirizzo, toglie dal nome `< > "`, a capo e caratteri a 4 byte, e manda il link
di conferma (`consegna_mittente_manda_conferma()`, `:448`) all'indirizzo stesso. Il link apre `api/mittente.php`: il GET mostra
solo il pulsante (i filtri antispam aprono i link), il POST conferma (`consegna_mittente_conferma()`, `:465`; il primo confermato
diventa il predefinito); link non valido, scaduto o usato: 404 con la spiegazione. Pagina con la sua CSP (`default-src 'none'`).

Chi può comparire nel From: l'indirizzo di Configurazione email e i mittenti confermati (`consegna_mittente_permesso()`, `:393`).
`posta_mittente()` (`includes/posta.php:120`) dà prima il predefinito confermato. Campagne (`admin/newsletter-campagna.php:35`)
e flussi (`admin/newsletter-flussi.php:110`, salvato da `newsletter_flusso_salva()`) scelgono fra i confermati
(`consegna_mittenti_scelta()`, `:378`); un id non confermato si salva come 0. `newsletter_email()` aggiunge From e Reply-To
del mittente scelto (`consegna_intestazioni_mittente()`, `:424`). Togliere un mittente rimette 0 dove era usato.

### 35.6 Avvisi dei servizi (webhook)

`api/posta-avvisi.php?s=brevo|ses|mailgun&k=<segreto>`: solo POST con JSON; senza il segreto giusto 403 prima di leggere il
corpo. Il segreto è `HMAC(moduli_segreto, "avvisi|<servizio>|<sale>")` (`consegna_webhook_segreto()`, `:786`), diverso per
servizio; "Cambia gli indirizzi" cambia il sale. In più:
- **Brevo** (`consegna_avvisi_brevo()`, `:874`), che non firma: `hard_bounce`, `invalid_email`, `blocked` = rimbalzo;
  `soft_bounce` = temporaneo; `spam`, `complaint` = spam; `unsubscribed` = uscita. Fino a 50 eventi per richiesta.
- **Mailgun** (`consegna_avvisi_mailgun()`, `:888`): firma HMAC-SHA256 di `timestamp + token` con la "HTTP webhook signing key"
  (salvata cifrata dalla scheda Rimbalzi e spam), timestamp entro 15 minuti; `failed` permanente o temporaneo, `complained`,
  `unsubscribed`.
- **Amazon SES con SNS** (`consegna_avvisi_ses()`, `:943`): firma di SNS (versione 1 SHA1, versione 2 SHA256) controllata con il
  certificato scaricato solo da `https://sns.<regione>.amazonaws.com/...pem` (`consegna_sns_firma_valida()`, `:920`; con
  `CMS_SNS_CERT_PROVA` il certificato delle prove). Il primo messaggio (`SubscriptionConfirmation`) si conferma da solo aprendo
  `SubscribeURL` e l'argomento (`TopicArn`) si ricorda: dopo, gli altri argomenti si rifiutano. `Bounce` permanente, transitorio,
  `Complaint`; vale anche il formato degli eventi dei set di configurazione (`eventType`).

Tutti finiscono in `consegna_segnala()` (`:823`): trova l'iscritto dal riferimento (se l'email coincide) o dall'email, poi
rimbalzo = `newsletter_rimbalzato()`, temporaneo = un errore di fila, spam = `newsletter_segnala_spam()`
(`includes/newsletter.php:448`: stato `spam`, `uscito_at`, coda svuotata, impronta nella lista di soppressione), uscita =
`newsletter_esci()`. Con un riferimento valido scrive anche l'evento `rimbalzo`, `spam` o `uscita` per la campagna o il passo
del flusso (`newsletter_evento_interno()`).

### 35.7 Riscaldamento

`consegna_riscaldamento_stato()` (`:512`): il limite di oggi è `partenza x fattore^(giorni dall'inizio)`, fino ad `arrivo`
(vuoto = il limite al giorno della newsletter, o 2000). Fattori: 1,3, 1,5 (normale), 2. Acceso dalla scheda Riscaldamento
(l'inizio è il giorno in cui si accende, o "Ricomincia da oggi"). `newsletter_invia_giro()` usa il più basso fra il limite al
giorno e quello di oggi (`consegna_limite_riscaldamento()`, `:533`; `includes/newsletter.php:756`) e scrive nel log "limite del
riscaldamento raggiunto". Conferme e avvisi dei form non aspettano (non passano dalla coda) ma contano nel registro.

### 35.8 La pagina

`admin/consegna.php`: schede `inizio` (cinque passi con lo stato), `dominio` (`:236`), `servizio` (`:280`, i campi del servizio
scelto si mostrano con uno script a nonce; senza JavaScript si vedono tutti), `mittenti` (`:375`), `avvisi` (`:419`),
`riscaldamento` (`:478`); azioni POST con CSRF (`:27`-`:122`), poi rimando alla scheda. Le caselle dei record si copiano con un
tocco (`data-copia-se-stesso` di `pannello.js`). Regole CSS con il prefisso `ce-` dentro la pagina; a 390 px niente scorrimento
di lato. Variabile della pagina `$ceAttivo`: `$attivo` lo usa il menu del pannello (`admin-header.php`) e veniva sovrascritto.

### 35.9 Prove

- `prove/prova-consegna-email.js` (73 controlli): dominio con il DNS finto, mittenti e conferma, Brevo, SES e Mailgun contro
  `prove/api-posta-finta.py` (che controlla chiave, firma SigV4 ricalcolata in Python e Basic), campagne e flusso con il
  mittente scelto, avvisi dei tre servizi, uscita con la campagna, statistiche, riscaldamento, telefono a 390 px.
- `prove/attacchi-consegna.js` (40 controlli): webhook senza segreto o con firme false, vecchie, cambiate, certificati non di
  Amazon, argomenti diversi, riferimenti inventati; CSRF; XSS; chiavi mai nell'HTML né in chiaro nel database; SSRF da regione e
  dominio; conferma dei mittenti; redattore.
- Aiuti: `prove/consegna-comune.js`, `prove/consegna-cli.php`. Il server PHP delle prove tiene `config.local.php` in opcache fino
  a 2 secondi: dopo aver cambiato le costanti si aspetta.

Non provato: le API vere di Brevo, Amazon SES e Mailgun, i webhook veri e il DNS vero (la sessione non risolve nomi). Formati
e firme seguono la documentazione dei servizi; la firma SigV4 è verificata sui vettori ufficiali di AWS.

## 36. Copia altrove su Google Drive e Dropbox, riservati fuori dagli elenchi, ripristino pulito (dalla 2.1.0)

Lavoro sul ramo, non ancora in una versione (`cms/VERSIONE` e `CHANGELOG.md` non toccati). Bozza del manuale d'uso:
`docs-sviluppo/novita-2.1/cloud-membri-copia-uso.md`.

**Copia del sito altrove, Google Drive e Dropbox** (addon `copia-altrove` 1.1.0, dettaglio in `docs-sviluppo/addon/copia-altrove.md`).
- `addon/copia-altrove/includes/cloud.php`: OAuth 2 con state firmato e legato alla sessione del pannello, PKCE S256, refresh
  token cifrato con la chiave di `copia_altrove_chiave()` (`moduli_segreto()` più `CMS_SEGRETO`), accesso rinnovato da solo,
  upload a pezzi (Drive resumable, Dropbox upload session) con la ripresa, ultime N copie, Prova la connessione, Scollega.
- Nucleo, file nuovo `admin/oauth-ritorno.php`: il redirect URI fisso di ogni addon con OAuth (chiave `oauth_ritorno` del
  manifesto, capitolo 32). Solo amministratore, nessun indirizzo seguito dalla richiesta, 400 se lo state non è di un addon acceso.
- Solo per le prove, nel `config.local.php`: `CMS_COPIA_ALTROVE_CLOUD_PROVA` (indirizzi dei server finti) e
  `CMS_COPIA_ALTROVE_PEZZO` (misura dei pezzi). Server finti: `prove/server-cloud-finti.py`. **Mai provato contro Google
  e Dropbox veri**: dalla sessione non c'è rete verso quei servizi.

**Area riservata** (addon `membri` 1.1.0, dettaglio in `docs-sviluppo/addon/membri.md`).
- Nucleo, `includes/sito-dati.php:155`: `sito_contenuti_elenco($dove)` con il filtro `contenuti_elenco`. La usano la ricerca
  (`sito/viste.php:484`, `cerca`), i feed (`extra_articoli()`, `sito/extra.php:16`, `feed`), la sitemap (`extra_sitemap()`
  `:60` e `seo_sitemap_voci()`, `includes/seo.php:862`, quindi anche IndexNow, `sitemap`), llms.txt e llms-full.txt
  (`extra_llms()` `:75`, `seo_llms_full()` `includes/seo.php:831`, `llms`). L'addon toglie i contenuti "solo per i membri",
  e nella ricerca li lascia a un membro collegato (con `Cache-Control: private, no-store`). Dalla 2.1.0 anche `elenco`:
  blog, categorie, tag e archivi per data (`sito_mappa()`, `includes/sito-mappa.php`, con `$inElenco`; le voci dei contenuti
  restano tutte, la pagina dice lei che è riservata), i conteggi di `sito_categorie()` e `sito_tag()`, `sito_ultimi()` (widget
  Ultimi articoli, articoli collegati) e la Griglia di contenuti (`includes/editor-widget-extra-c.php`). Un membro collegato li
  vede, con `private, no-store`.
- Nucleo, `admin/contenuto.php`: filtro `scheda_riquadri` (`:285`, riquadri in più nella colonna di destra) ed evento
  `scheda_salvata` (`:62`, dopo il salvataggio, con il POST). L'addon ci mette "Area riservata, Solo per i membri", solo per
  l'amministratore.
- Con l'addon spento nessuno ascolta: ricerca, feed, sitemap, llms e scheda come prima.

**Copia del sito: ripristino pulito e due prefissi** (capitolo 19.7, `includes/copia-sito.php`, `admin/copia-sito.php`).
- I file in più di `uploads/`, `temi/`, `moduli/` vanno in `aggiornamenti/scarto/ripristino-<data>/` per 30 giorni, il
  pannello li elenca, "Tieni i file in più" fa come prima; sezione "File messi da parte dai ripristini" con Elimina.
- Le tabelle di un'altra installazione con un prefisso più lungo nello stesso database non entrano nella copia e il
  ripristino non le tocca mai. La copia che si ripristina non si toglie più nella pulizia delle copie vecchie.

**Prove (2.1)**
| Prova | Cosa |
|---|---|
| `prove/prova-addon-copia-altrove.js` | FTP, FTPS, WebDAV come prima, più Google Drive e Dropbox contro i server finti (collega, Annulla, Consenti, file grande a pezzi, un pezzo fallito, rinnovo dell'accesso, ultime N con l'elenco a pagine, Scollega, telefono) |
| `prove/prova-addon-membri.js` | più la scheda e gli elenchi pubblici prima, da fuori e da membro |
| `prove/prova-copia-pulita.js` (nuova) | due siti nello stesso database (`PREFISSO=cms_b_` per `prova-kit.js`): copia senza le tabelle dell'altro, file in più nello scarto, "tieni", Elimina, copia vecchia con le tabelle dell'altro, telefono |
| `prove/attacchi-copia.js` (nuova) | scarto dal web, Elimina senza login, token, da redattore, con nomi cattivi; collegamenti verso `includes/` e fuori; nomi con HTML; copie inventate per arrivare alle tabelle dell'altra installazione |
| `prove/attacchi-addon-a.js` | gruppi 11 (OAuth) e 12 (riservato dalla scheda, redattore), più la ricerca per titolo nel gruppo 1 |

Risultati del 31 ottobre 2026 (PHP 8.3 con MySQL 8.0, server `php -S` con il router delle prove, siti nuovi con il kit
Attività): `prova-addon-copia-altrove.js` 91 su 91 in radice e in `/test`; `prova-addon-membri.js` 92 su 92 in radice e in
`/test`; `attacchi-addon-a.js` 154 su 154 in radice e in `/test`; `prova-copia-pulita.js` 24 su 24; `attacchi-copia.js`
36 su 36; `prova-copia.js` 30 su 30; `prova-addon-tutti.js` 68 su 68. Non provati: Google Drive e Dropbox veri, Apache.

## 37. I widget della 2.1: menu, richiesta di prenotazione, galleria con filtri, scorrevole, contenuto esterno (dalla 2.1.0)

Scelti da Danilo ("tutti") il 31 ottobre 2026. I cinque widget nuovi stanno in `includes/editor-widget-extra-e.php`, caricato
in coda a `includes/editor-widget-extra.php`, con il contratto delle parti A-D (`ewx_registra()`, classi `ew-*`,
`sito/assets/widget.css` e `widget.js`). Niente variabili in cima al file (capitolo 32). I widget sono ora 92.

| Tipo | Nome | Gruppo | Icona |
|---|---|---|---|
| `menu_ristorante` | Menu del ristorante | Elementi | `libro-aperto` |
| `richiesta_prenotazione` | Richiesta di prenotazione | Dal sito | `calendario-spunta` (dalla 2.2.0 `email_a` è `'solo_admin'`: `editor_blinda_solo_admin()` in `includes/editor.php`, chiamata da `api_albero()` di `admin/editor-api.php` per chi non è amministratore, rimette il valore salvato; nell'editor il campo è spento, `D.admin`; prova `prove/prova-solo-admin.js`, 6 controlli) |
| `galleria_filtri` | Galleria con filtri | Gallerie | `filtro` |
| `scorrevole` | Loghi e testo scorrevoli | Elementi | `infinito` |
| `contenuto_esterno` | Contenuto esterno (con consenso) | Interazione | `scudo-spunta` |

**Menu del ristorante** (`:106`).
- Ripetitore `piatti` (sezione, nome, descrizione, prezzo, immagine, allergeni, cinque interruttori: vegano, vegetariano,
  senza_glutine, piccante, novita). Le sezioni nascono dai piatti, nell'ordine in cui compaiono; il ripetitore `sezioni`
  (nome, nota, giorni) aggiunge la nota e i giorni. Niente ripetitori annidati: l'editor non li ha.
- Allergeni: `EWE_ALLERGENI` (`:12`), i 14 dell'allegato II del Reg. UE 1169/2011 con il loro numero. `ewe_allergeni()`
  (`:29`) legge numeri o parole ("1, 7", "glutine, latte"). Sul sito pallini con il numero (`aria-hidden`, con il nome nel
  `title`) più il testo per chi non vede ("Allergeni: cereali con glutine, latte e lattosio."); legenda con quelli usati o
  con tutti e 14, e una nota modificabile.
- Giorni: `ewe_giorni()` (`:44`): "lun-ven", "dom", "sab, dom". Una sezione con giorni che non comprendono oggi non esce sul
  sito (nell'editor sì, con "Solo ..."); se oggi c'è, ha l'etichetta "Oggi". Il giorno è quello del server nel fuso del sito;
  la pagina si disegna a ogni richiesta.
- Prezzo: `ewe_prezzo()` (`:58`): "14" diventa "14 €", "9,5" "9,50 €"; un testo libero ("a peso") resta com'è.
- Dati per Google: `Menu` > `MenuSection` > `MenuItem` con `offers` (prezzo, EUR) e `suitableForDiet` (VeganDiet,
  VegetarianDiet, GlutenFreeDiet), `JSON_HEX_TAG | JSON_HEX_AMP`; solo le sezioni che si vedono; mai nell'anteprima.

**Richiesta di prenotazione** (`:206`, ricezione in `includes/richiesta-prenotazione.php`). Non è l'addon Prenotazioni: niente
posti né calendario, una richiesta per email che l'attività conferma lei. Con l'addon acceso, il pannello del widget mostra
un'intestazione che lo suggerisce (`ewx_controlli_richiesta()`, `:182`, con `cms_addon_attivi()`).
- Perché un ingresso suo e non un form del pannello: il destinatario lo sceglie chi fa la pagina, nel widget, e non può
  arrivare dal browser (il sito diventerebbe un ripetitore di posta). Il widget mette nel modulo la sua configurazione
  (modalità, fino a 3 indirizzi validati, massimo di persone, telefono obbligatorio, titolo) in base64 con la firma
  HMAC-SHA256 di `moduli_segreto()` (`rp_firma()`, `:30`); `api/modulo.php:39` (`modulo=prenota`) chiama `rp_ricevi()`
  (`:73`), che accetta solo una configurazione con la firma giusta (`rp_verifica()`, `:36`, `hash_equals`). Il resto sono
  le difese dei moduli: campo esca, date fra oggi e due anni, partenza dopo l'arrivo, ora `HH:MM`, persone fra 1 e il
  massimo, email con `FILTER_VALIDATE_EMAIL`, telefono, spunta della privacy, captcha, `moduli_troppi()`.
- Le richieste finiscono in `moduli_invii` sotto il form "Richieste di prenotazione" (slug `richieste-prenotazione`,
  `rp_form_id()`, `:52`), creato spento alla prima richiesta: si leggono in Invii dei form; nell'elenco dei form ha
  l'etichetta "Dal widget" (`admin/form.php`) e non compare fra i form del widget Modulo (`editor_moduli_disponibili()`).
- Email a ogni indirizzo della configurazione (vuoti: `posta_notifica_email()`), con `Reply-To` di chi chiede; nel registro
  della posta con il tipo `avviso_form`.
- Ritorno: alla pagina (`pagina`, solo `^/[a-z0-9/-]*$`) con `?prenota=ok|<codice>&w=<id del widget>#prenota-<id>`; il
  widget con quell'id mostra il grazie (`role=status`) o l'errore (`role=alert`). In `widget.js` (`prenota()`) la partenza
  propone il giorno dopo l'arrivo.

**Galleria con filtri** (`:260`). Ripetitore con una casella `categorie` (separate da virgole) per ogni foto; i pulsanti
nascono dalle categorie, nell'ordine in cui compaiono (`ewe_slug()` per `data-cat`). Pulsanti veri (`button`,
`aria-pressed`, `role=group`), alti almeno 44 px, un `aria-live` che dice quante foto si vedono. `widget.js`
(`galleriaFiltri()`) dissolve e rimette le foto (`hidden` più `.ew-gf-via`, con la regola `[hidden]{display:none}`); con meno
movimento, subito. Il lightbox di sempre scorre solo le foto visibili (`widget.js:133`: un filtro sulla lista, che non cambia
niente per le gallerie senza filtri). Stile delle foto: `ewx_controlli_galleria_stile()`, come la Galleria.

**Loghi e testo scorrevoli** (`:310`). Una frase (predefinita) o una fila di loghi; due gruppi uguali in una traccia larga
quanto il contenuto, animata in CSS da 0 a -50% (`@keyframes ew-scorri`); "Durata di un giro" in secondi (`--ew-durata`, nello
stile in linea, che la CSP del sito ammette), direzione con `animation-direction`. Pochi elementi si ripetono (`ew-scr-copia`)
perché un gruppo superi la striscia. Il secondo gruppo e le copie sono `aria-hidden` e i loro link hanno `tabindex=-1`. Pausa
al passaggio del mouse e al fuoco; il pulsante Ferma/Riparti c'è sempre (WCAG 2.2.2). Con `prefers-reduced-motion: reduce`
niente animazione, niente copie, niente pulsante: i loghi vanno a capo. Loghi in grigio (`grayscale`) che si colorano al
passaggio, facoltativo. I loghi non hanno `loading=lazy` (fuori dalla striscia resterebbero da caricare).

**Contenuto esterno con consenso** (`:405`). Niente HTML libero: `ewe_esterno()` (`:374`) legge solo gli indirizzi dei servizi
di `EWE_FORNITORI` (`:362`) e costruisce lui l'indirizzo da incorporare:
- Calendly `https://calendly.com/<utente>[/<evento>]` (con `?hide_gdpr_banner=1&embed_type=Inline`);
- Spotify `open.spotify.com/(track|album|playlist|episode|show|artist)/<id>` (anche `intl-xx`), che diventa `/embed/...`;
- Google Calendar `calendar.google.com/calendar/embed?src=...` o l'indirizzo del calendario (`...@group.calendar.google.com`),
  ricostruito con solo `src`, `ctz`, `mode`;
- Typeform `<qualcosa>.typeform.com/to/<id>`, che diventa `form.typeform.com/to/<id>`;
- Booking.com `/hotel/<paese>/<nome>.html`: Booking non si lascia incorporare, quindi un riquadro con il link (nessun contatto,
  nessuna CSP da aprire).
Un indirizzo con utente, password o porta, un altro host o un altro percorso non dà niente (nell'editor un messaggio). Sul
sito: un riquadro con il nome del servizio, la frase sulla privacy, il link all'informativa, il pulsante "Mostra il contenuto
di <servizio>" e il link "Apri su <servizio>"; `widget.js` (`esterno()`) crea l'iframe solo al clic, e solo se l'indirizzo
comincia con una delle quattro origini. Nell'editor il pulsante è finto. Altezza: quella del servizio (`--ew-hp`), oppure
quella scelta nel widget (`--ew-h`).

**CSP solo nella pagina che li usa.** `sicurezza_csp_pagina()` (`includes/sicurezza.php:752`): il widget, mentre la pagina si
disegna, aggiunge l'origine del servizio (solo `frame-src`, solo `https://host` senza percorso); `sicurezza_csp()` la aggiunge
al `frame-src` del sito (`:797`). La CSP si scrive in `sito_rispondi()`, dopo il disegno. Le altre pagine restano come prima (la
mappa e il video hanno da sempre i loro domini nel `frame-src` di tutto il sito).

**Le opzioni nuove dei widget di prima.** Senza le opzioni nuove l'HTML è quello di prima (foto prima e dopo uguali al pixel).
- Mappa (`includes/editor-widget-extra-c.php:188`): `come_arrivare` (nessuno, sotto, sopra), `come_arrivare_app` (Google Maps
  `/maps/dir/?api=1&destination=`, Apple Mappe `maps.apple.com/?daddr=`, tutte e due), testo, allineamento. Con OpenStreetMap
  la destinazione sono le coordinate. Link in una scheda nuova, detto anche a chi non vede.
- Video (`includes/editor-widget.php:649`, `editor_video_opzioni()` `:1311`): `da_solo` carica subito il lettore (niente
  facciata: il servizio riceve la visita, e l'aiuto lo dice) con `autoplay=1&mute=1&playsinline=1` (Vimeo `muted=1`);
  `ripeti` aggiunge `loop=1` (YouTube anche `playlist=<id>`, senza il quale non ripete). Nell'anteprima dell'editor resta la
  facciata.
- Tabella (`editor_cella()`, `includes/editor-widget.php:1291`): nelle celle `**grassetto**`, `*corsivo*`, `[testo](link)`.
  Prima `h()` di tutto, poi le tre forme; il link passa da `editor_link_sicuro()` (javascript:, data: restano testo), uno
  esterno si apre in una scheda nuova. Un asterisco da solo ("5 * 3") o una parentesi quadra senza link non cambiano niente.
- Ultimi articoli e Griglia di articoli: categoria (e tag, nella Griglia) da una tendina (`editor_opzioni_tassonomia()`,
  `includes/editor-widget.php:94`) con `'accetta' => EDITOR_SLUG_RE` (`:93`): un valore scritto a mano prima della 2.1, o una
  categoria che arriva dopo la pagina, resta (la tendina lo mostra con "non trovato").

**Prove.**
- `prove/prova-widget-2-1.js` (68 controlli, con `prove/pagina-widget-2-1.php ... nuovi` e `prove/widget-2-1-cli.php`): il
  sito al computer e al telefono, meno movimento, CSP, dati per Google, contrasto, richiesta di prenotazione vera con il server
  di posta finto, form con ora e file fino allo scarico e a Cancella dati.
- `prove/attacchi-widget-2-1.js` (40 attacchi, con `pagina-widget-2-1.php ... cattivi`).
- `prove/foto-widget-2-1.js` più `prove/quanto-foto.py`: la pagina `/widget-vecchi/` (`pagina-widget-2-1.php ... vecchi`, i
  widget di prima con i valori di una pagina vera), Tutti i widget, Contatti e home, prima e dopo: uguali al pixel tranne il
  conto alla rovescia.
- `tools/sicurezza-prova.js` non è stato toccato: gli attacchi della 2.1 stanno nel loro file.

## 38. Scegli dal sito: il link a una pagina senza scriverlo a mano (dalla 2.2)

Richiesta di Danilo: "se inserisco un pulsante che deve aprire una pagina o un articolo del sito, oggi devo inserire per forza
la voce a mano". Accanto a ogni casella del pannello che porta un link a una pagina c'è il pulsante **Scegli dal sito**: apre
una finestra che cerca per titolo fra pagine, articoli, categorie, tag e i contenuti degli addon con una pagina pubblica, e
scrive il link nella casella. La casella resta scrivibile a mano come prima: il pulsante aggiunge, non toglie.

**Il valore che si salva.** Il percorso dalla radice del sito, senza la cartella: `/contatti/`, `/offerta-di-primavera/`,
`/category/notizie/`, `/evento/serata/`. È lo stesso formato dei link scritti a mano che `editor_link_sicuro()`
(`includes/editor-widget.php:546`) accetta così com'è, e resta giusto se il sito cambia cartella o dominio: la cartella la
aggiunge `cms_con_cartella()` (`includes/cms.php:96`) quando la pagina esce (`sito/index.php:58`). La home è `/`. Dove serve
l'indirizzo intero (le email della newsletter) si salva `cms_url_sito()` più il percorso (`assoluto`).

**Il motore**, `includes/collegamenti.php`:
- `collegamenti_cerca($q, $tipo, $admin)` (`:58`): pagine e articoli (`cestino IS NULL`, bozze comprese, stato da
  `contenuti_stato()`: `pubblicato`, `bozza` o `programmato`), categorie (`/category/<slug>/`) e tag (`/tag/<slug>/`), poi il
  filtro `collegamenti_sito` (`:97`). Ricerca nel titolo e nello slug con `LIKE ... ESCAPE` (`collegamenti_like()`, `:43`: `%` e
  `_` scritti valgono come caratteri). Ordine: titolo uguale alla ricerca, poi che comincia così, poi gli altri; a parità
  nell'ordine dei tipi. Al massimo 60 voci (`COLLEGAMENTI_MAX`, `:20`), `altre: true` se ce n'erano di più. Ogni voce: `titolo`,
  `tipo`, `tipo_nome`, `stato`, `percorso` (da salvare), `indirizzo` (con la cartella, da mostrare), `assoluto` (per le email).
  La risposta porta anche `tipi` (chiave e nome, per la tendina della finestra).
- `collegamenti_ricerca_pulita()` (`:27`): via i caratteri di controllo e quelli a 4 byte (nessun titolo li contiene: le
  colonne sono utf8 a 3 byte, e in un `LIKE` facevano fallire la query delle tabelle degli addon), spazi ridotti, al massimo 100
  caratteri.
- Il filtro **`collegamenti_sito`** `($voci, $q, $admin)`: un addon (o un modulo) aggiunge le sue voci con `tipo` (chiave
  breve), `tipo_nome`, `titolo`, `percorso`, `stato`. Il nucleo controlla il percorso (`collegamenti_percorso_ok()`, `:38`: una
  barra sola davanti, solo caratteri di un indirizzo), rifà il filtro per la ricerca, **toglie le bozze a chi non è
  amministratore**, scarta i tipi del nucleo usati da un addon e le voci senza titolo. Un addon che lancia un'eccezione toglie le
  voci degli addon, non la finestra (il `try` intorno a `cms_filtro`, nel log `collegamenti_sito:`).
- Gli addon che lo usano: Eventi (`addon/eventi-iscrizione/modulo.php:36`: ogni evento `/evento/<slug>/`, gli annullati con
  "(annullato)", le bozze solo all'amministratore), Vetrina (`addon/vetrina/modulo.php:46`, `vt_collegamenti()`
  `addon/vetrina/includes/vetrina.php:493`: il negozio `vt_base()`, le sue categorie `?categoria=`, i prodotti, i nascosti come
  bozza), Corsi (`addon/corsi/modulo.php:35`: `/corsi/` e ogni corso), Area riservata (`addon/membri/modulo.php:47`: `/accedi/`,
  `/iscriviti/` se l'iscrizione non è solo su invito, `/area-riservata/`). Prenotazioni e Recensioni non hanno pagine da
  linkare (le loro sono widget e link personali con il codice); Statistiche e Copia altrove nessuna pagina pubblica.

**L'endpoint**, `admin/link-api.php`: `require_login()`, solo POST con il token (`csrf_ok()`: token e Origin/Referer), risposta
JSON con `JSON_HEX_TAG | JSON_HEX_AMP` (un titolo con `<script>` arriva come `<script`), `Cache-Control: no-store`,
`nosniff`. Parametri `q` e `cosa` (non `tipo`: `require_login()` legge `?tipo=` per i modelli, e `tipo=modelli` dà 403 al
redattore); un valore che non è testo vale vuoto. È in `AUTH_REDATTORE_PAGINE` (`admin/includes/auth.php:81`): il redattore
riceve `$admin = false` (`is_admin()`), quindi vede pagine, articoli, categorie e tag come nel pannello, bozze comprese, e dei
contenuti degli addon solo quelli già pubblici. Senza login: 401 in JSON (`auth_rifiuta()`).

**La finestra**, `admin/assets/scegli-link.js` con il suo foglio `admin/assets/css/scegli-link.css` (un file suo perché la usa
anche l'editor visuale, che non carica `admin.css`: solo le variabili degli aspetti). Caricati da
`admin/includes/admin-header.php:115` (con `defer`, dopo `pannello.js`) e da `admin/editor.php:135` e `:250` (con il
`<meta name="pn-csrf">` che l'editor prima non aveva). Nessun gestore in linea.
- `window.pnLink.apri({ scegli(url, voce), attuale, assoluto, titolo, manuale })` (`:181`): un solo `<dialog class="pn-link">`
  per pagina, creato al primo uso (`crea()`, `:30`); ogni apertura riparte senza ricerca e rifà la tendina dei tipi con quelli
  che il server manda (gli addon accesi). Ricerca 200 ms dopo l'ultimo tasto, la richiesta di prima annullata
  (`AbortController`, `carica()` `:132`). Ogni voce è un pulsante con titolo, tipo, il segno **bozza** o **programmato**
  (`.pn-link-bozza`, con la spiegazione nel `title`) e l'indirizzo come si vede nel browser; quella già nella casella è segnata
  (`.pn-link-scelta`, `aria-current`). Tutto con `textContent`: un titolo con HTML resta testo. "Oppure scrivi un indirizzo"
  (`manuale: false` lo nasconde) passa `voce = null`.
- Tastiera: si apre con il fuoco sulla ricerca; freccia giù porta alla prima voce, le frecce (e Home, Fine, Pag su/giù) si
  muovono fra le voci con una sola fermata di Tab (tabindex mobile), freccia su dalla prima torna alla ricerca; Invio sceglie
  (nella ricerca, se c'è una voce sola); Esc chiude e il fuoco torna a chi l'ha aperta. Sotto 640 px la finestra prende tutto
  lo schermo. Nell'editor, Canc e Ctrl+Z dentro una finestra aperta non toccano la tela (`editor.js:1105`).
- I campi: `<input data-link-sito>` (o `<textarea>`) diventa `.pn-link-campo` con la casella e il pulsante (`campo()`, `:204`).
  `data-link-sito="assoluto"` scrive l'indirizzo intero; `data-link-sito="riga"` (textarea) aggiunge il percorso su una riga
  nuova; `data-link-sito-titolo="#id"` scrive il titolo in quel campo se è vuoto. Scrivere manda `input` e `change` (`scrivi()`,
  `:199`). Casella `readonly` o `disabled`: pulsante spento. I campi aggiunti dopo si preparano da soli (`MutationObserver`);
  `window.pnLink.campo(input, { classe, scegli, modo })` per quelli creati da uno script (l'attributo `data-link-pronto` lo
  scrive `campo()`: chi prepara un campo a mano non deve usarlo).
- `window.pnLink.quill(quill, { assoluto })` (`:238`): il pulsante link della barra di Quill apre la finestra. Con del testo
  selezionato lo fa diventare link; senza, scrive il titolo della pagina scelta come link; sopra un link già fatto lo toglie
  (come prima). Il fumetto di Quill per vedere, cambiare o togliere un link esistente resta. Quill scrive ogni link con
  `target="_blank"` (anche prima della 2.2, anche quelli scritti a mano): i link dei testi si aprono in una scheda nuova.

**Dove** (inventario dei campi link del pannello, nucleo e addon):

| Dove | Campo | Come |
|---|---|---|
| Editor visuale, controllo `link` (Immagine, Invito all'azione, Riquadri, Flip box, ogni widget con `ewx_link()`) | casella dell'indirizzo | `conSceltaLink()`, `admin/assets/editor/editor.js:918`, usato a `:980` |
| Editor visuale, caselle di testo che portano un link | nome che finisce in `link` (`link`, `pulsante_link`, `extra_link`), `link_privacy`, o `link_sito: true` nel controllo | `eLinkSito()`, `editor.js:926`: Pulsanti, Apertura, Schede con link, Slider, Elenco con icone, Listino, Barra contatti e gli altri, anche dentro i ripetitori |
| Editor visuale, Testo (Quill, controllo `ricco`) | pulsante link della barra | `editor.js:989` |
| Editor visuale, Galleria | pulsante **Link** su ogni foto (il pulsante Testo lo chiede ancora a mano) | `editor.js:900` |
| Header, footer, Fine articolo | sono modelli dell'editor visuale: come sopra | |
| Popup | Link del pulsante; Indirizzi elencati (una riga per volta) | `admin/popup.php:63`, `:77` |
| Form | Link del pulsante "Continua" | `admin/form.php:101` |
| Newsletter, campagna | Link del pulsante e link nel testo (Quill), con l'indirizzo intero | `admin/newsletter-campagna.php:125`, `:173` |
| Newsletter, flussi | link nel testo dei blocchi email (Quill, indirizzo intero) | `admin/assets/flussi.js:241` |
| Social, Impostazioni | Un link in più nei testi di WhatsApp | `admin/social.php:224` |
| SEO e AEO | Motori: "Un indirizzo da inviare" (IndexNow), "Notifica un indirizzo a Google"; Copertura: "Un indirizzo" da ispezionare; Schema: "Indirizzo del sito"; Redirect: "Vecchio indirizzo" e "Nuovo indirizzo" | `admin/seo.php:350`, `:387`, `:415`, `:611`, `:663`, `:664` |
| Menu di navigazione | Link personalizzato (il testo della voce dal titolo); Indirizzo di ogni voce | `admin/menu.php:184-210` |
| Eventi e Prenotazioni, Impostazioni | Pagina dell'informativa privacy | `addon/eventi-iscrizione/includes/admin-impostazioni.php:23`, `addon/prenotazioni/includes/admin-impostazioni.php:53` |

Senza il pulsante, perché non sono pagine del sito: video (YouTube, Vimeo), profili dei social e LinkedIn della Squadra,
`url` di Condividi (vuoto vale la pagina stessa), Mappa e indirizzi postali, link di Google per le recensioni, WebDAV di Copia
altrove, la frase in fondo al sito (porta al sito di plumeCMS), i campi immagine
(hanno Scegli dai Media), gli slug delle schede. Le condizioni dei modelli ("Contenuto preciso", `admin/modelli.php`) hanno già
la loro scelta per titolo.

**Il menu** (`admin/menu.php:184`): una pagina, un articolo o una categoria scelti dal sito diventano una voce di quel tipo con
lo slug in `rif` (`rifDa()`): `menu_url_voce()` (`includes/menu-nav.php:102`) calcola l'indirizzo dallo slug, quindi la voce segue
la pagina anche se cambia indirizzo. Un indirizzo scritto a mano su una voce che ricordava uno slug la fa diventare "Link
personalizzato" (prima l'indirizzo scritto si ignorava in silenzio, perché `rif` vince). Gli elenchi Pagine, Articoli e
Categorie con le caselle, a sinistra, restano.

**Link sicuri anche fuori dall'editor** (2.2): il pulsante del popup passa da `editor_link_sicuro()` (`includes/popup.php:141`),
il "Continua" dei form (`includes/form.php:574`), il pulsante delle email (`includes/newsletter.php:672`) e il link della privacy
di Eventi e Prenotazioni (`addon/eventi-iscrizione/includes/vista.php:75`, `addon/prenotazioni/includes/vista.php:92`) da
`sicurezza_url_pulito()`. Prima un `javascript:` scritto lì dall'amministratore usciva così com'era; gli indirizzi buoni non
cambiano (il popup trasforma anche `contatti` in `/contatti`, come i widget).

**Se la pagina scelta cambia indirizzo.** Il link salvato resta quello di prima (è un testo), ma il CMS scrive da solo un
redirect 301 dal vecchio indirizzo al nuovo quando cambia lo slug di una pagina o di un articolo **pubblicati**
(`contenuti_redirect()`, `includes/contenuti.php:257`, chiamato da `contenuti_salva()` `:354`, dal salvataggio dall'editor `:462`
e dal ripristino di una versione `includes/versioni.php:522`), e di una categoria o un tag (`admin/categories.php:45`): il
pulsante continua a portare alla pagina, con un salto in più. Non lo fa per una pagina in **bozza** (un link scelto su una
bozza si rompe se le si cambia indirizzo prima di pubblicarla) e per eventi, prodotti e corsi (gli addon non scrivono redirect
quando cambia il loro slug). Le voci del menu scelte dal sito non ne hanno bisogno (seguono lo slug).

**Prove.**
- `prove/prova-scegli-link.js` (80 controlli in radice, 81 in cartella, con `prove/scegli-link-cli.php` che prepara e pulisce):
  il motore (ogni tipo, addon compresi, bozze, home, cartella), l'editor (Apertura con la tastiera, Immagine, Testo con e senza
  selezione, Esc, Canc dentro la finestra), popup, form, newsletter (indirizzi interi), social, SEO, menu, privacy degli addon,
  e per ognuno il clic vero sul sito con l'indirizzo finale e il 200; bozza segnata e sceglibile, titolo con `<script>`,
  "scrivi un indirizzo", telefono a 390 px (sette pagine e la finestra), il redattore, il cambio d'indirizzo con il redirect.
- `prove/attacchi-scegli-link.js` (32 attacchi): senza login, GET, senza token, token sbagliato, Origin di un altro sito;
  ricerche cattive (SQL, UNION, jolly di LIKE, barra rovesciata, HTML, NUL, U+202E, emoji, UTF-8 rotto, 100.000 caratteri,
  liste); titolo cattivo nel JSON e nella finestra; `javascript:` e varianti nel popup e nei widget; il redattore e le bozze
  degli addon.

## 39. Audit di novembre 2026: emoji, link scritti a mano, messaggi

Il resoconto completo, con le prove prima e dopo, è in `docs-sviluppo/audit-2026-11.md`; la prova è
`prove/prova-audit-2026-11.js` (un controllo per problema, `CMS_DIR` per P15). Quello che è cambiato nel codice:

| Dove | Cosa fa adesso |
|---|---|
| `form_salva()`, `form_leggi()`, `form_tutti()` (`includes/form.php:105-111`) | i testi del form (`FORM_TESTI`) con le emoji come entità, rimesse emoji da `form_4byte_fuori()`; i campi in JSON con `form_json_3byte()` (`\uXXXX`); i dati di chi compila tengono le emoji e vanno nel database con `form_json_3byte()` |
| `popup_salva()`, `popup_riga()` (`includes/popup.php:46`) | `POPUP_TESTI` come entità al salvataggio, emoji alla lettura |
| `admin/newsletter.php:102` | nome o descrizione della lista con un'emoji: un messaggio; "Esiste già una lista" solo con l'errore 1062 |
| `seo_cfg()` (`includes/seo.php:172`), `sito_contenuti()` (`includes/sito-dati.php`), `admin/contenuto.php` | `sito_4byte_veri()` su tutto il pannello SEO, su riassunto e In breve |
| `scaricabili_elenco()` (`includes/scaricabili.php:264`) | titolo e descrizione dei Download con `scaricabili_4byte_veri()` |
| Dashboard (`admin/index.php:271`), `admin/newsletter-statistiche.php:41` | l'oggetto della campagna con `newsletter_4byte_fuori()` |
| Impostazioni di Prenotazioni ed Eventi (`addon/*/includes/admin-impostazioni.php`) | la pagina della privacy passa da `editor_link_sicuro()` (`privacy-policy` diventa `/privacy-policy`, `www.sito.it/x` `https://www.sito.it/x`); un valore che non è un indirizzo si rifiuta con un messaggio |
| Vetrina > Impostazioni (`addon/vetrina/includes/admin-impostazioni.php:18`) | l'indirizzo del negozio non può essere quello di una pagina o di un articolo (nel cestino escluso) né di un altro addon |
| `admin/versioni.php:84-125` | l'anteprima di una versione passa da `cms_con_cartella()` |
| `contenuti_desinenza()` (`includes/contenuti.php:40`) | "Pagina aggiornata": `'genere' => 'f'` nel tipo `pagine` (anche i tipi dei moduli possono dirlo) |
| `admin/seo.php:121-127` | Meta Pixel senza spazi; Pixel o Clarity sbagliati si rifiutano con un messaggio (prima si toglievano in silenzio) |
| `admin/seo.php:180` | un redirect con un'emoji: un messaggio invece di `SQLSTATE` |
| `admin/utenti.php:39` | il nome mostrato tagliato a 200 caratteri (la colonna), `maxlength` nel campo |

---

## 40. UX di novembre 2026: editor, installatore, addon, sito; Download e logo (dalla 2.6.0)

Il pannello (riquadri, campi, conferme, tabelle al telefono, Impostazioni a schede, bozze vuote) è al capitolo 6 e al 20; le
regole valgono per tutto il CMS e sono in `docs-sviluppo/regole-interfaccia.md`. I documenti del controllo:
`docs-sviluppo/ux-ui-2026-11-pannello.md` e `docs-sviluppo/ux-ui-2026-11-editor-addon-sito.md`. Prove: `prove/prova-ux-pannello.js`,
`prove/prova-ux-editor.js`, `prove/prova-bozze-vuote.js`, `prove/prova-download-limite.js`.

**Installatore** (`install/index.php`): `inst_errore_db()` (riga 82) traduce i codici 1045, 1044, 2002, 2003, 2006, 2013 in una
frase con il campo da controllare, il testo del server resta in fondo fra parentesi. `inst_campo()` (riga 118) collega l'aiuto al campo
(`aria-describedby`, id `<campo>-aiuto`) e legge `$errori['<campo>']`: il campo sbagliato ha `.campo.sbagliato`, `aria-invalid` e
`.errore-campo` sotto; in cima resta il riassunto con `role="alert"`. Il pulsante del passo 4 ha due testi, `.testo-installa` e
`.testo-domande`, scelti dal CSS con `:has()` secondo la carta (Su misura dice "Avanti: le quattro domande").

**Editor** (`admin/editor.php`, `admin/assets/editor/editor.js`): `nominaCampi()` (riga 1311) dà un nome ai campi senza etichetta
con un `MutationObserver` su `#ed-controlli`; `cursoriVuoti()` (riga 1327) mette "come il sito" nella casella vuota e il cursore
grigio; `etichettaMisura()` (riga 611) scrive "solo tablet" o "solo telefono" accanto al pallino dello schermo. Widget della tavolozza
con `role="button"` e `tabindex` (un `<button>` trascinabile in Firefox non parte); schede del pannello `role=tablist/tab/tabpanel`
con frecce, Inizio e Fine; `#ed-avviso-h1` scritto come frase e spento per i modelli; `.ed-solo-computer` sotto 760 px;
`#ed-widget-nessuno` per la ricerca vuota; `.ed-vuoto` nell'anteprima dei widget `eventi_iscrizione` e `corsi_elenco` senza dati
(mai sul sito).

**Addon**: Vetrina (`addon/vetrina/assets/vetrina.css`) con `.vt-conta`, `.vt-filtri a[aria-current]` e `.vt-bollino` a 5,5 di
contrasto; Agenda delle Prenotazioni con "Settimana prima/dopo"; Copia altrove con un solo pulsante di salvataggio
(`.ca-salvataggio`) e i pulsanti spenti spiegati in `#ca-spenti-perche`.

**Sito** (cambi voluti, foto spiegate nel documento del controllo): `sito.css` `.card-meta` con `--testo-2`; `vista_cerca_niente()`
(`sito/viste.php`, riga 507) per la ricerca senza risultati; `moduli.js` chiama `checkValidity()` prima del captcha (il server non
cambia: il captcha resta obbligatorio).

**Invii troppo grandi** (`admin/includes/auth.php`): `pannello_ini_byte()` (riga 275), `pannello_limite_caricamento()` (riga 282,
il minore fra `upload_max_filesize` e `post_max_size`), `pannello_peso()` (riga 288). Un POST oltre `post_max_size` arriva a PHP
senza campi né file: `verify_csrf()` (riga 311) chiama prima `pannello_invio_troppo_grande()` (riga 299), che con `$_POST` e `$_FILES`
vuoti e `CONTENT_LENGTH` oltre il limite scrive un flash in parole e torna alla pagina con un 303. Nessuna azione parte perché nessun
campo è arrivato; prima la risposta era "Token CSRF non valido". Nel browser, `data-peso-massimo="<byte>"` su un campo file
(`pannello.js`, `controllaPeso()`) dice il problema sotto il campo (`.errore-campo`, `aria-invalid`) e blocca l'invio con
`setCustomValidity()`: c'è in Download, Aggiornamenti e Media.

**allow_url_fopen**: `server_controlli()` (`includes/controllo-server.php`) ha la riga "allow_url_fopen" nel gruppo PHP e database
(ko se spento); `versione_http()` (`includes/versione-nuova.php`) con il valore spento lancia un errore che dice la causa invece di
"non risponde" (fopen di un https non parte). `consegna.php` e le chiamate di Google usano cURL quando c'è, quindi non ne dipendono.

**Limite di caricamento di almeno 8 MB** (2.16.1, scelta di Danilo: dalla 2.12.0 lo zip di aggiornamento supera i 2 MB predefiniti
di PHP). Il limite è il più piccolo fra `upload_max_filesize` e `post_max_size`, in byte, 0 se non si sa: `inst_limite_caricamento()`
(`install/index.php`), `agg_limite_caricamento()` (`includes/aggiornamenti.php`) e una copia dentro `server_controlli()` (per opcache:
nessuna funzione nuova chiamata da un altro file). Installatore: riga "Limite di caricamento di almeno 8 MB" in `inst_requisiti()`, solo
avviso (l'installazione va, e Installa ora scarica da sé). Aggiornamenti: la riga del limite in `agg_controlli()` è NO sotto 8 MB, con
dove si alza. Controlla il server: riga "Limite di caricamento" nel gruppo PHP e database, avviso sotto 8 MB.

**Download** (`admin/scaricabili.php`): i giorni dei link personali stanno nel riquadro `#dl-riservati` ("Link dei file
riservati", riga 152), fuori dal form di caricamento; prima un secondo "Salva" sotto "Carica" salvava i giorni e perdeva il file
scelto (segnalazione di Danilo, 1 novembre 2026).

**Logo** (`admin/design.php`, riga 302): nella scheda Identità del sito si cercano i widget `logo_sito` dei modelli header e footer
(fuori dal cestino) con `modo` diverso da `testo` e `immagine` scritta: se ce ne sono, l'avviso `#dg-logo-widget` dice in quali
modelli il logo scelto non compare, con il link all'editor. Il widget usa il logo dello Stile solo con l'immagine vuota
(`includes/editor-widget-tema.php`). Il sito di plumeCMS (`sito-plumecms/costruisci.py`) dalla 2.6.0 mette il logo solo nello Stile.

## 41. Le quindici infografiche disegnate (novembre 2026)

Quindici widget del gruppo Infografiche disegnati in SVG dal server, accanto alle quattordici `ig_*` fatte di riquadri HTML
(capitolo 9.7). Stessa cornice (testata, occhiello, titolo, intro, chiusura, firma, tema delle infografiche: `igx_cornice()`,
`igx_controlli_testa()`, `igx_controlli_coda()` in `includes/editor-widget.php`), stessa palette (`igx_palette()` e le classi
`igx-c-*` di `sito.css`, che seguono le variabili `--ig-*` del tema del sito). Il disegno approvato da Danilo è la pagina
"Quindici infografiche nuove" (anteprime SVG 360 x 240); i valori di partenza sono i suoi esempi.

| Tipo | Nome | Funzione | Voci |
|---|---|---|---|
| `ig_imbuto` | Imbuto | `igs_imbuto()` (`includes/editor-infografiche.php:376`) | `gradini` 3-6 (nome, valore); resa = valore / valore precedente |
| `ig_piramide` | Piramide a livelli | `igs_piramide()` (`:413`) | `livelli` 3-6 (nome, testo), dalla punta |
| `ig_matrice` | Matrice a quattro quadranti | `igs_matrice()` (`:441`) | quattro assi; `quadranti` 4 (titolo, esempi uno per riga) |
| `ig_ciclo` | Ciclo | `igs_ciclo()` (`:492`) | `tappe` 3-6 (2 il minimo per disegnare); frecce con `igs_punta()` (`:487`) |
| `ig_ciambella` | Ciambella | `igs_ciambella()` (`:529`) | `voci` 2-6 (nome, percentuale 0-100); totale diverso da 100: nota per l'editor |
| `ig_venn` | Cerchi che si toccano | `igs_venn()` (`:574`) | `cerchi` 2-3 (riga piccola, nome), incrocio |
| `ig_radar` | Ragnatela | `igs_radar()` (`:612`) | `aspetti` 4-8 (3 il minimo), prima e dopo 0-10 |
| `ig_lancetta` | Lancetta | `igs_lancetta()` (`:654`) | valore, minimo, massimo, due soglie, verso, decimali, suffisso |
| `ig_iceberg` | Iceberg | `igs_iceberg()` (`:698`) | frase sopra, `voci` 2-6 sotto |
| `ig_omini` | Omini | `igs_omini()` (`:753`), simboli `igs_simbolo()` (`:731`) | quanti 0-20 su totale 1-20; persona, casa, cuore, stella |
| `ig_viaggio` | Il viaggio | `igs_viaggio()` (`:797`) | `tappe` 3-7 (2 il minimo), bandiera sull'ultima |
| `ig_bilancia` | Bilancia | `igs_bilancia()` (`:851`) | `pro` e `contro` fino a 8; inclinazione `auto` (5 + 2 gradi per voce di differenza, al massimo 10) o scelta |
| `ig_calore` | Mappa di calore | `igs_calore()` (`:889`) | colonne separate dalla virgola (fino a 12), `righe` fino a 12 con valori 0-4 |
| `ig_cerchi` | Cerchi concentrici | `igs_cerchi()` (`:934`) | `cerchi` 2-4 (nome, testo), dall'esterno |
| `ig_barre100` | Barre al cento per cento | `igs_barre100()` (`:983`) | `voci` 2-6 con il colore, `barre` 1-4 con le percentuali; somma diversa da 100: nota e parti in proporzione |

**Come entrano nell'editor.** `includes/editor-infografiche.php` ha solo funzioni e costanti (niente variabili in cima al
file) e lo carica `includes/editor-widget.php:76`. Le definizioni sono `igs_definizioni()` (`:57`), aggiunte a quelle delle
altre infografiche in `editor_definizioni()` (`editor-widget.php:482`); il disegno passa da `igx_disegna()`, che per i tipi
di `IGS_TIPI` (`:17`) chiama `igs_disegna()` (`:344`, `editor-widget.php:1078`). Il prefisso `ig_` resta riservato al nucleo:
non passano da `editor_registra_widget()`.

**Il disegno.** `igs_disegna()` chiama la funzione del tipo due volte, con `IGS_LARGO` (560) e `IGS_STRETTO` (320): ognuna
restituisce `svg` (il contenuto), `h` (l'altezza), `aria` (tutti i dati in parole), `note` (frasi per l'editor) o `vuoto`
(niente da disegnare). Escono due `<svg role="img" aria-label="..." class="igs-svg igs-largo|igs-stretto">` dentro
`<figure class="igs">`. La versione stretta non è la larga rimpicciolita: cambia composizione dove serve (legenda della
ciambella sotto, viaggio in verticale dal basso verso l'alto, spiegazioni dei cerchi concentrici sotto il disegno, numero
degli omini accanto alla frase, "SOTTO" dell'iceberg a destra).

**I testi.** Tutti passano da `igs_pulito()` (`:201`: entità decodificate, niente caratteri di controllo, al massimo 300
caratteri prima di andare a capo) e da `h()` in `igs_testo()` (`:273`). Vanno a capo con `igs_a_capo()` (`:231`), che usa la
larghezza stimata di `igs_largh()` (`:209`): misurata nel browser con Inter, la stima sta fra 0,88 e 1,1 della larghezza
vera, quindi si moltiplica per 1,1. Quello che non ci sta finisce con i puntini (`igs_tronca()`, `:224`). I numeri all'italiana
con `igs_num()` (`:264`: `1.200`, `8,6`); i valori scritti nei campi di testo (mappa di calore, barre) li legge
`igs_valori()` (`:287`), con un massimo e i limiti.

**I limiti.** `editor_valida()` taglia i ripetitori a 40 voci e i numeri ai `min`/`max` dichiarati; il disegno usa al massimo
le voci della tabella (`igs_voci()`, `:324`, salta le voci vuote) e scrive per l'editor quante non si vedono
(`igs_nota_troppe()`, `:334`). Le note sono `<p class="ed-vuoto igs-nota">`: nell'anteprima dell'editor si vedono, sul sito
`.ed-vuoto{display:none}`. La lancetta corregge da sola massimo minore del minimo e soglie fuori scala, e lo dice.

**I colori.** `igs_c()` (`:301`) dà a un `<g>` la classe `igx-c-<nome>` (o `igs-c-grigio`, il grigio in più delle quindici) oppure
lo stile `--c:#...;--c2:#...` di un colore libero, sempre passato da `igx_esa()`: nell'attributo non entra altro. Le forme
usano `fill:var(--c)` e le trasparenze `fill-opacity`, mai `color-mix()`. Il testo sopra un colore pieno (classe `igs-su`) è
bianco o nero, quello con il contrasto più alto: `sito/assets/infografiche-svg.css:27` calcola nel foglio la luminanza di WCAG
del colore (`rgb(from var(--c) ...)` con `pow(..., 2.2)` e la soglia 0,179), quindi vale anche con i temi e con i colori
liberi; i browser senza i colori relativi restano al bianco. Lo stesso calcolo, sul primo colore della testata (`--t1`), dà il
colore di titolo e occhiello (`igs_classi_cornice()`, `:361`: `igs-testa-auto` se il colore del testo della testata è vuoto,
`igs-occhiello-auto` con l'occhiello "In armonia" o "Bianco"). Le testate di un colore solo sono piene, come nel disegno
approvato (`igs-testa-piatta`, `infografiche-svg.css:15`): su una sfumatura nessun colore del testo passa 4,5 da un capo
all'altro. La firma "sopra una riga" usa il colore del testo (`--tx`), non `--muto` (3,7 sul crema). Il numero della lancetta
ha un alone del colore della card (`igs-alone`): la lancetta gli passa sotto.

**Il foglio.** `sito/assets/infografiche-svg.css` si carica solo nelle pagine che hanno una delle quindici: ogni tipo ha
l'asset in `editor_carica_widget_esterni()` (`includes/editor-controlli.php:86`, come le disposizioni dell'Apertura), e la tela
dell'editor lo chiede in `admin/editor-anteprima.php:48`. Le regole sono tutte sotto `.igx`; la versione da mostrare la sceglie
`@container` (`infografiche-svg.css:9`) sulla larghezza del riquadro, non della finestra (una colonna stretta al computer
mostra la versione stretta). Le classi del disegno: `igs-t` (titoli, `--tit`), `igs-tm` (testo, `--tx`), `igs-inv`,
`igs-p`/`igs-p2` (`--c`/`--c2`), `igs-tinta`, `igs-filo`, `igs-spento`, `igs-prima`/`igs-dopo` (ragnatela).

**Prove.** `prove/prova-infografiche-15.js` con `prove/pagina-infografiche-15.php` (`normali`, `estremi`, `notte`, `cattivi`,
`vuota`/`togli`): le quindici a 1280, 768 e 390 px (numeri calcolati, aria-label, nessuna scritta sovrapposta o fuori dal
disegno, niente scorrimento di lato, scritte di almeno 10 px al telefono), i valori estremi, il contrasto di ogni scritta
misurato sui pixel (senza tema, Aria, Cosmo, Autunno, corpo scuro, telefono), e dall'editor: le quindici dalla tavolozza, voci
cambiate, la nota della ciambella, Pubblica, il sito. Attacchi: gruppo 30 di `tools/sicurezza-prova.js` (con `CMS_DIR`).
La foto del manuale d'uso è `08-infografiche` (`tools/manuale-uso-schermate.js`, `SOLO_CAPITOLI=infografiche`).

## Appendice A. Dove i documenti vecchi non corrispondono al codice

I documenti di `docs-sviluppo/` (fino alla 1.6.4 in `docs/`) sono nati quando il CMS era il pannello di un solo sito.
Il 31 ottobre 2026 sono stati allineati al codice in tutti i punti che questa appendice elencava: versioni dell'editor nella
tabella `editor_versioni` (`editor-pagine.md`, `contenuti.md`, `header-footer-menu.md`), widget HTML del redattore, colori,
link e widget registrati con `editor_registra_widget()` (`editor-pagine.md`), caricamenti in `uploads/pagine/AAAA/MM/` con
AVIF e SVG, voci del gruppo Editor (`stile-sito.md`, `header-footer-menu.md`), CSS personalizzato ripulito
(`stile-sito.md`), aperture e clic contati (`newsletter.md`), tabelle create dal codice (`seo.md`), niente `sincronizza` né
Rank Math (`aggiornamenti.md`), sito servito anche in una cartella e `CMS_BASE` scritto dall'installatore (`cms.md`), e i
rimandi ai documenti spostati in `docs-sviluppo/`. Le citazioni di `includes/editor-articoli.php`, di `share/` e delle voci
Aforismi, Test e Social copy erano già sparite.

Se un documento vecchio dice ancora una cosa diversa da questo manuale, vale il manuale: va aggiunta qui una riga.

| Documento | Cosa dice | Cosa fa il codice |
|---|---|---|

Nel codice, sistemati lo stesso giorno: il commento di `admin/includes/admin-header.php` sul Log cron del redattore,
quello della chiave `rotte` in `includes/cms.php`, la descrizione di `tools/cms-pacchetto.py` (per i caratteri a 4 byte un
avviso, non un rifiuto). Restano:
- il commento di `includes/aggiornamenti.php:144` parla del rapporto di compressione, ma il codice controlla solo le
  dimensioni (200 MB per file, 600 MB in tutto). Non si tocca da solo: cambiare il motore dei pacchetti, anche in un commento,
  costringe `tools/consegna.py` a dare due pacchetti (19.8); va sistemato insieme alla prossima modifica vera del motore;
- l'evento `upload_rifiutato` del registro della sicurezza (`includes/sicurezza.php:213`) esiste ma nessuno lo scrive.


## Aggiunte della 2.18.0 fuori dal passo Email

- **Orario delle email** (`nl_email_quando()`, `includes/newsletter-flussi.php`): se il turno arriva fra l'ora scelta e l'ora dopo,
  ritorna `time()` e l'email parte al giro dopo (prima andava al giorno seguente: "attesa 7 giorni, alle 8" = 8 giorni).
- **Menu del pannello** (`admin/assets/css/admin.css`, blocco 2.18.0 in fondo): solo variabili di `aspetti.css` (`--sidebar-testo`,
  `--logo-accento`, `--sidebar-attivo`, `--sidebar-su-attivo`, `--sidebar-bordo`, `--sidebar-hover`); voci principali peso 600,
  icone in `--logo-accento`, `.nav-gruppo[open] > summary` con riga d'accento, `.nav-sotto` con la linea a sinistra e le
  sotto-voci in `--sidebar-testo`, la sotto-voce attiva piena.
- **Hero tenuta nei pacchetti del sito** (`sito_importa_pagina()`, `includes/sito.php`): con `tieni_apertura` nel JSON della pagina,
  se la pagina esiste, ha una versione con autore `pacchetto` (`sito_pagina_da_pacchetto()`) e la sua prima sezione contiene un
  widget `hero` (`sito_sezione_con_apertura()`), la prima sezione resta quella del sito. Il resoconto aggiunge "l'apertura resta
  quella del sito". `sito-plumecms/costruisci.py` mette `tieni_apertura` sulla home; `--senza-home` la lascia fuori dal pacchetto
  (per un sito ancora sotto la 2.18.0, dove il pacchetto lo applica il motore vecchio: `sito.php` è già caricato da
  `aggiornamenti.php` prima che i file nuovi siano scritti). Prova: `$S/hh.sh` con `prove/home-hero-cli.php`.
