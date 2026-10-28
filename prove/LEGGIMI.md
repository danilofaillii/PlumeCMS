# Prove

## prova-install.js

Installa plumeCMS da zero con il browser (Playwright) e controlla sito e pannello.

1. Costruisci lo zip: `python3 tools/cms-pacchetto.py`.
2. Estrailo in una cartella vuota e avvia un server PHP lì, per esempio
   `php -S 127.0.0.1:8099` (il server integrato non legge gli `.htaccess`: per provare anche i divieti
   serve un router che li emuli, oppure Apache vero).
3. Crea un database vuoto e un utente che può usarlo.
4. Lancia:
   `DBPASS=<password del database> node prove/prova-install.js http://127.0.0.1:8099 <cartella-schermate> <prefisso-schermate> <nome-database> [sottocartella]`
   La prova usa l'utente del database `danilo` e l'host `127.0.0.1:3308`, quelli dell'ambiente in cui è nata:
   cambiali in testa al file per il tuo ambiente. La password dell'amministratore che crea è `Collaudo-Officina-2026`.
5. Atteso (1.4.0): 37 su 37, in radice e in cartella (base `http://127.0.0.1:<porta>/test` con `CMS_CARTELLA=/test`), anche
   l'icona di partenza a `/favicon.ico` e nel `<link rel="icon">`. Fino alla 1.3.1 l'unico KO era il 404 della favicon.

## tools/sicurezza-prova.js

Le prove di attacco (125 dalla 1.4.0): ogni OK è un attacco respinto. Si lanciano contro un'installazione locale, mai contro un sito vero:

Le prove di attacco: ogni OK è un attacco respinto (118 nella 1.4.0, con `CMS_DIR` e `SEGRETO`; le 21 nuove sono sul
tipo di sito in prova, i primi passi, Esegui adesso del cron e Controlla il server). Si lanciano contro un'installazione locale, mai contro un sito vero:
`CMS_DIR=<cartella del CMS installato> node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]`.
Le prove del modulo danilofailli (burnout) si saltano da sole dove il modulo non c'è.
Dopo, svuota la tabella `<prefisso>sicurezza_tentativi`: la prova della forza bruta blocca 127.0.0.1 per qualche minuto.

## Le altre prove

Le prove in browser di editor, controlli, stile, modelli, menu, versioni e widget sono nate sul sito di prova di
danilofailli.it e usano i suoi contenuti: restano in quel progetto. Da riscrivere qui sui contenuti di partenza
dell'installatore.

## prova-vedi-sito.js

Entra nel pannello, clicca "Vedi sito" e controlla che si apra il sito, anche con il CMS in una cartella.
`node prove/prova-vedi-sito.js <base del CMS> <utente> <password>`. Nata il 27 ottobre 2026 per il caso di
`duhtron.it/test` (vedi la trappola del 301 in `CLAUDE.md`).

## Foto del sito prima e dopo (temi, 27 ottobre 2026)

Per cambiare CSS senza cambiare il sito. Nate con la fase 1 dei temi (colori di `sito.css` in variabili).

1. Installa il CMS (vedi sopra) e avvialo con `php -S 127.0.0.1:8099 -t <cartella> prove/router-php-s.php`
   (il router fa quello che fa il `.htaccess`: file veri serviti, il resto a `index.php`).
2. `php prove/pagina-widget.php <cartella>` crea la pagina `/tutti-i-widget/` con un widget per tipo (80 dalla 1.2.4), valori di partenza.
3. `node prove/foto-sito.js http://127.0.0.1:8099 <foto-prima>`; poi cambia il CSS nella cartella installata e
   `node prove/foto-sito.js http://127.0.0.1:8099 <foto-dopo>`.
4. `python3 -I prove/quanto-foto.py <foto-prima> <foto-dopo>`: per ogni pagina lo scarto massimo e quanti pixel
   superano 2, 8 e 24 livelli. Nessuna riga = identiche. `prove/confronta-foto.py <a> <b> <cartella-diff>` salva le
   immagini delle differenze.
5. `php prove/colori-prova.php <cartella> chiaro` mette colori chiari nello Stile del sito (per vedere se il CSS li
   segue); `ripristina` torna ai predefiniti.

Rumore noto: il widget Conto alla rovescia cambia i minuti fra due foto (circa 2.000 pixel su `widget-pc`, due zone
di 30 per 30), e `blog-tel` ha a volte 1 livello di scarto su 17 pixel. Si vedono anche fra due foto dello stesso sito.

## prova-temi.js (temi, fase 2)

Il motore dei temi provato come un utente: anteprima solo per l'amministratore, attivazione, header e footer del tema,
CSS, filtro PHP, vista 404 del tema, riattivazione senza doppioni, pannello che non esegue il PHP del tema, e i tre
modi in cui un tema può rompersi (eccezione in una vista, errore di sintassi, errore fatale) con il ritorno
all'aspetto di base.

1. Installa il CMS (vedi sopra) e crea i temi di collaudo: `php prove/temi-collaudo.php <cartella CMS installato>`
   (prova, rotto, fatale, mortale; mai nel prodotto).
2. `node prove/prova-temi.js <base> <cartella CMS installato> <utente> <password>`. Usa `prove/tema-cli.php` per
   attivare e disattivare i temi da riga di comando.
3. Atteso: 40 su 40 (anche i colori delle infografiche: con il tema, senza tema, colori liberi). `temi-collaudo.php` crea anche la pagina `/infografiche-tema/`. Il controllo "errore fatale: la prima richiesta fallisce" è voluto: quella richiesta va persa, la
   successiva risponde con l'aspetto di base.
4. Alla fine la prova lascia il tema disattivato e lo stile ai predefiniti, ma restano i modelli "Header Prova" e
   "Footer Prova" come predefiniti: per le foto prima e dopo rimetti predefiniti quelli di partenza.

Il `.htaccess` dei temi (PHP vietato, CSS servito) va provato su Apache vero: il server integrato di PHP non lo legge.

## prova-pannello-temi.js (temi, fase 3)

La pagina Editor > Temi come un utente: voce di menu, schede, Anteprima in una scheda nuova, Attiva con la conferma
prima rifiutata e poi accettata, messaggio, Versioni, avviso di tema guasto, ritorno all'aspetto di base, POST senza
token CSRF e con un nome `../`, redattore che non vede la voce e viene rimandato indietro.
`node prove/prova-pannello-temi.js <base> <cartella CMS installato> <utente> <password> <cartella schermate>`.
Atteso: 31 su 31. Crea l'utente `redattore-prova` (con `tema-cli.php redattore`). Lascia quattro schermate.

## prova-temi-partenza.js (temi, fase 5)

Attiva uno per uno Cosmo, Aria e Bottega e controlla home, articolo, blog e pagina dei widget: risposta 200, menu
Principale nell'header, nessun errore JavaScript e **contrasto del testo** almeno 4,5 a 1 (norme WCAG), misurato fra il
colore del testo e lo sfondo dietro. Nata dopo che, su sfondo chiaro, il testo degli articoli era quasi invisibile.
`node prove/prova-temi-partenza.js <base> <cartella CMS installato>` (servono i temi in `temi/` e la pagina
`/tutti-i-widget/` di `pagina-widget.php`). Atteso: 36 su 36. Alla fine disattiva il tema e ripristina lo stile.

## prova-carica-temi.js (temi, fase 6)

Caricare, sostituire, scaricare ed eliminare i temi dalla pagina Temi, e gli zip che devono essere respinti.
1. `python3 -I prove/zip-temi-prova.py <cartella zip>` costruisce gli zip: uno buono, uno con tema.json nella radice, uno
   con PHP, e quelli malevoli (`../`, `.htaccess`, `.user.ini`, SVG con script, `.phtml`, senza tema.json, due temi,
   zip bomba, tema.json rotto).
2. `node prove/prova-carica-temi.js <base> <cartella CMS installato> <utente> <password> <cartella zip>`. Usa
   `tema-cli.php` per aprire e chiudere la finestra di Sicurezza e per leggere la chiave di firma del sito di prova,
   che passa a `tools/pacchetto.py --rifirma` senza stamparla. Servono i temi di partenza in `temi/`.
3. Atteso: 45 su 45. Alla fine toglie i temi caricati e chiude la finestra; restano le copie in
   `aggiornamenti/temi-precedenti/`.

Il `.htaccess` della cartella dei temi si prova su Apache vero: con il `.htaccess` della radice della 1.1.9 (senza la
regola dei temi) e `temi/.htaccess` scritto dal CMS, il PHP dei temi deve rispondere 403 e non essere mai eseguito.

## prova-aggiornamento.js (versione 1.2.0)

Aggiorna un sito dal pannello come farebbe Danilo: Aggiornamenti, carica lo zip, Applica ora, conferma; poi controlla
versione, pagina Temi (tre temi, nessuno attivato dall'aggiornamento), `temi/.htaccess`, menu.
`node prove/prova-aggiornamento.js <base> <cartella CMS installato> <utente> <password> <pacchetto.zip> <versione>`.
La 1.1.9 vera si ricostruisce dal suo commit (`git worktree add <cartella> 077d826`, poi `tools/cms-pacchetto.py` lì
dentro). Prima e dopo si fanno le foto (`foto-sito.js`, `quanto-foto.py`): il 27 ottobre 2026, 1.1.9 -> 1.2.0 identico
al pixel (11 su 11, pacchetto applicato con snapshot, 179 file).

## router-php-s.php

Il router per `php -S` fa quello che fa `cms/.htaccess` (cartelle e file vietati 403, file veri serviti, il resto a
`index.php`), più `temi/.htaccess`. Con `CMS_CARTELLA=/test` nell'ambiente il CMS sta in `<radice>/test`, come
duhtron.it/test. Le 78 prove di attacco ci passano tutte (`tools/sicurezza-prova.js <base> <utente> <password> ""`,
con `SEGRETO` dalla impostazione `moduli_segreto` per la prova della newsletter firmata).

## prova-foto-effetti.js (1.2.2)

La sezione Foto di Apertura e Presentazione e l'interruttore dell'onda, dall'editor al sito, come un utente: spegne
l'onda, sceglie foto naturale e cornice sull'Apertura, velo al 40% e alone sulla Presentazione, pubblica e controlla
il sito. Serve una pagina con un'Apertura (foto ed etichetta dell'onda) e una Presentazione (foto), valori di partenza.
`node prove/prova-foto-effetti.js <base> <utente> <password> <id della pagina> <percorso pubblico>`. Atteso: 20 su 20.

## prova-sito-plumecms.js (1.2.3)

Il sito di plumeCMS: carica e applica dal pannello il pacchetto di `sito-plumecms/costruisci.py`, poi visita le sette
pagine da visitatore (titoli, menu, immagini, favicon) e scarica davvero gli zip e i PDF da `/scarica/<nome>/`.
Serve un plumeCMS 1.2.3 o successivo.
`node prove/prova-sito-plumecms.js <base> <cartella CMS> <utente> <password> <parte-1.zip,parte-2.zip,...> <versione>`: le parti si
caricano e si applicano in ordine. Atteso: 55 su 55 con quattro parti (notte del 27 ottobre 2026: anche la piuma della
Copertina, il suo peso, il ritaglio al telefono, il contrasto delle parole bianche misurato sul fondo e la piuma ferma
per chi chiede meno movimento; la pagina Installazione con cosa serve, il carosello delle cinque schermate
dell'installatore, che va avanti da solo e resta fermo con meno movimento, e il link dalla home). Il controllo del
carosello aspetta sei secondi due volte: la prova dura un po' di più. Provata con il server limitato a 2 MB
(`php -d upload_max_filesize=2M -S ...`), il predefinito di PHP; nella 1.2.3 anche a 1,2 MB, con cinque parti.
Si può rilanciare sullo stesso sito: la seconda volta le pagine si aggiornano invece di nascere.

## prova-download.js (1.2.4)

Il download manager come un utente: carica un PDF con titolo, descrizione e versione dalla pagina Download; prova i
caricamenti che vanno rifiutati (stesso nome senza Sostituisci, un testo chiamato .pdf, un .php, uno .zip con lo stesso
nome del PDF); sostituisce il file e controlla che titolo e versione restino; cambia il titolo; mette il widget in una
pagina (`prove/pagina-download.php`); guarda la pagina da visitatore; scarica come persona e come Googlebot e controlla
che conti solo la persona; elimina il file. Serve il file manuale-tecnico in Download (il sito di plumeCMS lo ha).
`node prove/prova-download.js <base> <cartella CMS> <utente> <password>`. Atteso: 27 su 27.

## prova-hero.js (1.2.6)

Le undici disposizioni dell'Apertura. `php prove/pagina-hero.php <cartella CMS> <url della foto> [id,id,...]` crea
`/hero-<id>/` per ognuna (stessi testi e foto) e `/tutte-le-hero/`; con la foto vuota (`''`) le prova senza foto.
`node prove/prova-hero.js <base> <cartella foto> [id,id,...] [utente password id-di-/hero-classica/]`: per ogni
disposizione, al computer e al telefono, un solo h1, niente scorrimento orizzontale, foto caricate, contrasto di titolo
e testo, pulsanti visibili, nessun errore; lascia `<id>-pc.png` e `<id>-tel.png` da guardare. Con utente e password
prova anche la tendina nell'editor. Atteso: 154 su 154, più 7 con l'editor. Da rifare con ogni tema
(`tema-cli.php attiva cosmo|aria|bottega`). `python3 -I sito-plumecms/foglio-aperture.py <cartella foto> <cartella
schermate>` fa il foglio delle undici per la pagina Funzionalità del sito di plumeCMS.

## prova-kit.js (1.3.0)

I kit di partenza dell'installatore. Su una cartella appena estratta e un database vuoto (come per `prova-install.js`):
`DBPASS=... node prove/prova-kit.js <base> <kit> <db> <cartella schermate>`, con `<kit>` fra `vuoto`, `personale`,
`attivita`, `blog`, `portfolio`. Installa scegliendo il kit al passo 4, poi visita ogni pagina del menu dell'header al
computer e al telefono: 200, un solo h1, niente scorrimento orizzontale, immagini caricate, nessun segnaposto `{...}`
rimasto, link interni nella cartella del sito; apre la home nell'editor. Con il CMS in una cartella la base è quella con la
cartella (`http://127.0.0.1:8082/test`, server con `CMS_CARTELLA=/test`). Ogni kit vuole un'installazione nuova.
Atteso (28 ottobre 2026): attività 34 su 34 in radice e 44 su 44 in cartella, personale 29, blog 29, portfolio 34 (44 in
cartella), vuoto 28. Lascia `<kit>-installatore.png` e `<kit>-<pagina>-pc.png` / `-tel.png`.

## prova-sezioni.js (1.3.0)

Le sezioni pronte dell'editor. Prima `php prove/pagina-sezioni.php <cartella CMS>` (crea `/sezioni-pronte/` con tutte le
varianti e `/sezioni-<categoria>/`), poi `node prove/prova-sezioni.js <base> <utente> <password> <cartella schermate>`.
Nell'editor: linguetta, categorie e varianti, ricerca, clic sotto la sezione selezionata, trascinamento (con eventi di
trascinamento costruiti nel browser: Playwright non trascina dal pannello dentro l'iframe), modifica, Pubblica; sul sito
`/sezioni-pronte/` al computer e al telefono con il contrasto del testo. Atteso: 15 su 15, anche con
`tema-cli.php attiva cosmo|aria|bottega`. Lascia una pagina "Nuova pagina" pubblicata: da cancellare dopo.

## prova-copia.js (1.3.0)

La copia del sito. `python3 -I prove/zip-copie-prova.py <cartella zip> <prefisso>` scrive gli zip da respingere (non una
copia, zip slip, percorso assoluto, file nascosto, copia di un altro sito con PHP e SQL estraneo, 3 MB oltre il limite),
poi `node prove/prova-copia.js <base> <cartella CMS> <utente> <password> <cartella zip> <cartella schermate>`. Il server
di prova deve avere i limiti predefiniti di PHP (`upload_max_filesize` 2M, `post_max_size` 8M). Atteso: 30 su 30.
Ripristina il sito più volte: alla fine il sito è quello della copia fatta all'inizio, più `uploads/copia-estranea.txt`.

## prova-versione.js (1.3.0)

"C'è una versione nuova" con due installazioni: il sito (`<base cliente>`) e un plumeCMS che fa da sito ufficiale
(`<base ufficiale>`, installazione qualunque della 1.3.0). Più `prove/finto-ufficiale.php`, un sito ufficiale che si
comporta male: `FINTO_ZIP=<cartella schermate>/plumecms-1.3.1-aggiornamento.zip FINTO_CONTA=<file> php -S 127.0.0.1:8084 prove/finto-ufficiale.php`.
`node prove/prova-versione.js <base cliente> <cartella CMS cliente> <base ufficiale> <cartella CMS ufficiale> <base finto> <file FINTO_CONTA> <utente> <password> <cartella schermate>`.
La prova scrive il pacchetto finto 1.3.1 (cambia solo `VERSIONE`) nel download manager del sito ufficiale e cambia il
`config.local.php` del cliente (`CMS_AGGIORNAMENTI_URL`, `CMS_AGGIORNAMENTI_HTTP_PROVA`), aspettando 2 secondi a ogni
cambio perché l'opcache del server lo rilegga; alla fine toglie le sue righe e rimette `VERSIONE` a 1.3.0.
`CMS_AGGIORNAMENTI_HTTP_PROVA` serve solo qui: su un sito vero il controllo vuole HTTPS. Atteso: 28 su 28.

## prova-versione-sito.js (1.3.1)

L'indirizzo del sito ufficiale come parametro di Aggiornamenti: rifiuta http e gli indirizzi con `?`, salva quello giusto
senza la barra finale, vuoto torna al predefinito. Serve un sito senza `CMS_AGGIORNAMENTI_URL` nel config.
`node prove/prova-versione-sito.js <base> <utente> <password>`. Atteso: 7 su 7.

## prova-aspetti.js (1.4.0)

I quattro aspetti del pannello. `node prove/prova-aspetti.js <base> <utente> <password> <cartella schermate> [cartella cms]`.
Login e password dimenticata in Aria (e il loro contrasto); poi per Aria, Bottega, Cosmo e Lavagna: la scelta in Il mio
accesso con un clic sulla miniatura, l'attributo `data-pannello` scritto dal server nell'HTML (nessun JavaScript), le 16
variabili di sempre definite, tutte le voci del menu con le sottovoci e qualche pagina interna (scheda di un articolo,
campagna, popup, utenti, password, account), ognuna senza errori PHP o JavaScript, con il contrasto di ogni testo
visibile (4,5 a 1, 3 a 1 per il testo grande; si saltano i testi su immagini o gradienti, i controlli spenti e le zone che
mostrano il sito), e una schermata `<aspetto>-<pagina>.png`; l'editor della home, anche con un widget aperto. Alla fine
rimette Aria e cerca i colori scritti a mano rimasti in `cms/admin` (le eccezioni volute hanno un commento `fisso`).
Atteso: 56 su 56. Dura circa 8 minuti. Non crea contenuti.

## prova-foto-leggere.js (1.4.0)

Foto più leggere. Prima `php prove/foto-prova.php <cartella foto>` (una foto 4000x3000 salvata di lato con orientamento
EXIF 6 e un dato GPS, una 3600x2400, una PNG 600x400 trasparente), poi
`node prove/prova-foto-leggere.js <base> <cartella CMS installato> <utente> <password> <cartella foto>`.
Carica dalla pagina Media e controlla sul disco del sito misure, orientamento (il quadrante rosso deve finire in alto a
destra), metadati e WebP; crea la pagina `/prova-foto/` (`prove/pagina-foto.php`) e la guarda con Chromium (WebP) e con
una richiesta senza `image/webp` in Accept (JPEG); lato massimo 1200, valore 50 rifiutato, spento, newsletter senza WebP,
redattore respinto (lo crea `tema-cli.php redattore`), cancellazione con la WebP. Atteso: 31 su 31. Lascia accese le foto
più leggere con 2560, la pagina `/prova-foto/` e le foto caricate.

Le prove di attacco della 1.4.0 (`tools/sicurezza-prova.js`, gruppo 18) controllano anche la scelta dell'aspetto (un
utente non cambia quello di un altro, valori non ammessi, token e Origin) e le impostazioni delle foto (redattore, token).

## prova-importa-wp.js (1.4.0)

L'import da WordPress come un utente, contro un WordPress vero (6.6.2) sul server integrato di PHP.

1. Una copia di WordPress in una cartella tua (mai l'originale scaricato) e un database vuoto, poi
   `DBPASS=... php prove/wp-installa.php <cartella WP> http://127.0.0.1:8071 <database>` (wp-config con
   `WP_ENVIRONMENT_TYPE` local, che permette le password per le applicazioni senza HTTPS; cron spento; permalink con la data;
   il plugin di prova `wp-plugin-prova.php`, che imita Yoast nella REST e con `WP_REST_CHIUSA=1` chiude la REST) e
   `php prove/wp-riempi.php <cartella WP> <cartella segreti>` (33 articoli: 27 pubblicati, 4 bozze, 1 in revisione, 1
   programmato; 6 pagine con una figlia; categorie annidate, 8 tag, 15 immagini GD e un PDF, un menu con sottovoci nella
   posizione Primary, 5 commenti, un'emoji, uno shortcode, una pagina "Elementor", SEO nei meta di Yoast). Nella cartella dei
   segreti (fuori dal repository): `app.txt` (la password per le applicazioni, nuova a ogni giro), `login.txt`,
   `atteso.json`. Si rilanciano tutti e due quando si vuole un WordPress da capo.
2. Due server per WordPress, con piu processi (WordPress chiama se stesso):
   `DBPASS=... PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8071 -t <cartella WP> prove/router-wp.php` e, per la REST chiusa,
   lo stesso con `WP_REST_CHIUSA=1` sulla 8072.
3. Un plumeCMS installato da zero (`prova-install.js`), anche in una cartella (`CMS_CARTELLA=/test`).
4. `node prove/prova-importa-wp.js <base CMS> <cartella CMS> <utente> <password> http://127.0.0.1:8071 <cartella segreti> <cartella schermate> http://127.0.0.1:8072`.
   La prova scrive nel `config.local.php` del CMS `CMS_IMPORTA_WP_LOCALE` (gli host:porta di prova) e `CMS_IMPORTA_WP_GIRO`
   0.3 (giri corti, per interrompere e riprendere). Usa `prove/importa-wp-cli.php` per leggere database e file.
   Atteso: 76 su 76, in radice e in cartella. Lascia `importa-*.png` (collegamento, errore, report, avanzamento, fermo,
   riepilogo, anteprima, confermato). Alla fine il sito ha l'import confermato: per rifarla serve un CMS nuovo.

Per le prove di attacco dell'import (punto 18 di `tools/sicurezza-prova.js`): `php -S 127.0.0.1:8073 prove/finto-wp.php`
(un WordPress finto e cattivo), `127.0.0.1:8073` in `CMS_IMPORTA_WP_LOCALE`, e `FINTO_WP=http://127.0.0.1:8073` e `CMS_DIR`
nell'ambiente. Senza `FINTO_WP` le prove con il sito cattivo si saltano; quelle di SSRF, CSRF e ruoli girano lo stesso.

## Le prove della 1.4.0 (28 ottobre 2026)

Il server di prova serve più richieste insieme: `PHP_CLI_SERVER_WORKERS=4 php -S ...` (Controlla il server chiede al sito
stesso i file vietati; con un solo processo aspetterebbe se stesso). `tipo-sito-cli.php <cartella CMS> stato | pagina
<slug> | articolo <slug> | copie | disegni | togli-prova` legge lo stato vero per le prove.

### prova-tipo-sito.js

Il tipo di sito in prova dalla dashboard. Serve un'installazione nuova con il kit Portfolio (`prova-kit.js ... portfolio
...`), in radice o in cartella. `node prove/prova-tipo-sito.js <base> <cartella CMS> <utente> <password> <cartella
schermate>`. La persona cambia Chi sono e scrive un articolo dalla scheda classica; il sito diventa Blog (prima la
conferma rifiutata), poi Vuoto: copie "prima del cambio", pagine tenute e tolte, menu, blog e contatti da visitatore,
disegni tolti, attacchi (senza CSRF, kit inventato, redattore), Definitivo e cambio rifiutato dopo. Atteso: 39 su 39, in
radice e in cartella. Lascia `tipo-sito-*.png`.

### prova-primi-passi.js

Dopo `prova-tipo-sito.js`, sullo stesso sito, con il server di posta finto acceso:
`python3 -I prove/smtp-finto.py <porta> <cartella posta> &` (scrive ogni messaggio in `<n>.eml`, mai su un server vero).
`node prove/prova-primi-passi.js <base> <cartella CMS> <utente> <password> <porta smtp> <cartella posta> <cartella
schermate>`. Email di prova senza server (rossa) e con il server scritto in Impostazioni (arrivata davvero), logo e colore
dallo Stile, "Esegui adesso" che non conta e i due cron lanciati da riga di comando che contano, nascondi. Scrive nel sito
il server SMTP finto: toglierlo dopo da Impostazioni. Atteso: 19 su 19.

### prova-cron.js

`node prove/prova-cron.js <base> <cartella CMS> <utente> <password> <cartella schermate>`: la pagina Log cron, le due
righe con il percorso vero, Esegui adesso, il cron vero, gli attacchi, e 31 pagine del pannello senza frasi che parlino
del sito come se fosse WordPress (restano i paragoni e l'import). Atteso: 13 su 13.

### prova-controlla-server.js

`node prove/prova-controlla-server.js <base> <cartella CMS> <utente> <password> <cartella schermate>`: i sette gruppi
(sul sito di prova in http l'HTTPS è rosso, come deve), le protezioni spente con `logs/controllo-aperto` (il router fa
come un server che non legge il `.htaccess`), l'email di prova, gli attacchi. Atteso: 19 su 19.

### prova-favicon.js

`node prove/prova-favicon.js <base> <utente> <password> <indirizzo di un'immagine del sito, es. /test/uploads/x.png>
<cartella schermate>`: icona di partenza (link e `/favicon.ico`), icona dello Stile (link e 302), di nuovo quella di
partenza. Atteso: 7 su 7, in radice e in cartella.

### prova-riquadri.js

`php prove/pagina-riquadri.php <cartella CMS>` (crea `/riquadri/`, stampa l'id; il quarto riquadro è scritto come lo
scriveva la 1.3.1), poi `node prove/prova-riquadri.js <base> <utente> <password> <id> <cartella schermate>`: valori di
partenza com'erano, posizione e allineamento scelti nell'editor che restano dopo Pubblica e arrivano al sito. Atteso: 11 su 11.
Dalla 1.4.1 `pagina-riquadri.php` salva l'albero con `"versione": 1`, come la 1.3.1: il nome di prima vale solo lì.

### prova-nomi-doppi.js (1.4.1)

`php prove/pagina-nomi-doppi.php <cartella CMS>` (crea `/nomi-doppi/`, stampa l'id; gli alberi sono scritti come li
salvava la 1.4.0: Titolo animato con `animazione` vuota e in Zoom, Condividi, Avviso e Flip box con `padding` a 30 px,
Icone social con il `padding` delle Avanzate, più un Titolo animato, un Condividi e delle Icone social nuovi), poi
`node prove/prova-nomi-doppi.js <base> <utente> <password> <id> <cartella schermate>`: la pagina della 1.4.0 com'era,
poi nell'editor parole "Scrittura a macchina" e ingresso "Dal basso", ingresso tolto al titolo in Zoom, 20 px nei
pulsanti di Condividi, 16 px intorno alle icone social; Pubblica, editor riaperto, sito misurato nel browser.
Atteso: 22 su 22 (con il codice della 1.4.0: 12 su 22). Le foto prima e dopo della pagina:
`node prove/foto-sito.js <base> <cartella> /nomi-doppi/` (dalla 1.4.1 `foto-sito.js` accetta altri percorsi in fondo).

### Aggiornamento 1.3.1 -> 1.4.0

Installazione 1.3.1 (`releases/cms/plumecms-1.3.1.zip`, kit Personale), `pagina-widget.php`, foto; `prova-aggiornamento.js`
con `plumecms-1.4.0-aggiornamento.zip` (ora controlla anche che la dashboard non abbia i riquadri delle installazioni
nuove); foto. 28 ottobre 2026: 12 su 12, sito identico al pixel tranne i minuti del Conto alla rovescia.
