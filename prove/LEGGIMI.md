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

## prova-newsletter-difetti.js

I difetti della newsletter corretti nella 2.0.0, con un controllo (o più) per punto: doppia conferma dei form (solo la lista
del form, email che dice per cosa, aggancio `newsletter_conferma_esito()`), flusso "Compila un form" che parte alla
conferma, registro di tutte le email con il limite orario e giornaliero, From, Return-Path e testo semplice anche con
`mail()`, Cancella dati completo con la lista di soppressione, importazione (fonte, consenso, usciti, flussi solo con la
spunta), rimbalzi (550 e tre 451 di fila), errore "troppe richieste" del form, pulizia del cron.

1. Un CMS installato con il kit Attività (`prove/prova-kit.js ... attivita ...`): la pagina Contatti ha il form.
2. Il server di posta finto con i rimbalzi: `python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> rimbalzi`
   (rifiuta con 550 gli indirizzi con "rimbalza" e con 451 quelli con "rinvia").
3. `node prove/prova-newsletter-difetti.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS>`.
   Usa `prove/newsletter-cli.php` (query e giri da riga di comando) e, per `mail()`, `prove/sendmail-finto.py` come
   `sendmail_path`. Lascia dati di prova: va su un sito da buttare.
4. Atteso (2.0.0): 37 su 37. Sullo stesso sito con il codice della 1.10.0: 7 su 37 (i 30 KO sono i difetti).

## tools/sicurezza-prova.js



Le prove di attacco: ogni OK è un attacco respinto (238 nella 1.10.0 su un sito Attività, con le 5 nuove del gruppo 26 sui
widget nuovi; 233 nella 1.9.0 su un sito Attività, con le 6 nuove del gruppo 25 sull'avviso
dei form; 249 nella 1.8.0 sullo stesso sito su misura in prova, con FINTO_WP: le 7 nuove,
gruppo 24, sono sulla frase in fondo al sito; 227 su un sito Attività; 242 nella 1.7.0, con FINTO_WP, su un sito su misura ancora in prova: le 41
nuove, gruppi 23 e 23b, sono sul sito su misura; 226 sullo stesso tipo di sito già definitivo e senza FINTO_WP; 201 nella 1.6.0,
con FINTO_WP: le 24 nuove, gruppo 22, sono su Aggiungi sempre alla
fine; 177 nella 1.5.0; 118 nella 1.4.0, con `CMS_DIR` e `SEGRETO`; le 21 nuove sono sul
tipo di sito in prova, i primi passi, Esegui adesso del cron e Controlla il server). Si lanciano contro un'installazione locale, mai contro un sito vero:
`CMS_DIR=<cartella del CMS installato> node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]`.
Le prove del modulo danilofailli (burnout) si saltano da sole dove il modulo non c'è.
Il gruppo 23 (1.7.0, sito su misura) gira prima del gruppo 20, che preme Definitivo. Le risposte cattive mandate davvero
(il sito si ricompone, poi torna alle risposte di prima: restano due copie del sito in più) partono solo se il sito è in prova
su misura; altrimenti si saltano con un OK motivato e l'escape si prova da riga di comando (`php`, con `CMS_DIR`). Gli attacchi a
Sostituisci vogliono almeno una foto finta in Media; senza, si saltano. Il 23b (dopo il Definitivo il sito su misura non si
ricompone) vuole `CMS_DIR`. Un sito su misura in prova si fa con i passi 1-3 di `prova-su-misura.js`, senza il resto.
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
che conti solo la persona; elimina il file. Serve il file manuale-temi in Download (il sito di plumeCMS lo ha).
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
La prova scrive il pacchetto finto (la versione del cliente più uno, cambia solo `VERSIONE`) nel download manager del sito
ufficiale e cambia il
`config.local.php` del cliente (`CMS_AGGIORNAMENTI_URL`, `CMS_AGGIORNAMENTI_HTTP_PROVA`), aspettando 2 secondi a ogni
cambio perché l'opcache del server lo rilegga; alla fine toglie le sue righe e rimette `VERSIONE` a 1.3.0.
`CMS_AGGIORNAMENTI_HTTP_PROVA` serve solo qui: su un sito vero il controllo vuole HTTPS. Atteso: 28 su 28. Fino alla 1.6.1 le
versioni 1.3.0 e 1.3.1 erano scritte nella prova e nel sito finto, che quindi passavano solo allora: dalla 1.6.2 il finto legge
la versione dal nome del file in `FINTO_ZIP` (`plumecms-<versione del cliente più uno>-aggiornamento.zip`).

## prova-versione-sito.js (1.3.1, riscritta nella 1.6.2)

L'indirizzo del sito ufficiale non si scrive più nel pannello: il campo della 1.3.1 non c'è, la pagina dice che chiede a
`https://plumecms.danilofailli.it`, il vecchio POST `versione_sito` non fa niente e un'impostazione `versione_sito` salvata
in passato viene ignorata (scritta e tolta con `prove/settings-cli.php`, che legge e scrive un'impostazione di un CMS
installato). Serve un sito senza `CMS_AGGIORNAMENTI_URL` nel config.
`node prove/prova-versione-sito.js <base> <utente> <password> [cartella CMS installato]`. Atteso: 5 su 5.

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

## prova-social.js (1.5.0)

Social e email del mattino. Su un plumeCMS appena installato con un kit (l'articolo d'esempio ha id 1), con il server
di posta finto acceso (`python3 -I prove/smtp-finto.py <porta> <cartella posta> &`):
`node prove/prova-social.js <base> <utente> <password> <porta smtp> <cartella posta>`. Imposta la posta verso il server
finto, gli hashtag e il link di WhatsApp (e un link con `javascript:` rifiutato); scrive nel riquadro Social
dell'articolo un testo con un'emoji e uno script, un testo solo per LinkedIn, carica la foto in evidenza e misura le
due immagini (1200x630 e 1080x1350); controlla i testi canale per canale in Testi dei contenuti; manda l'email del
mattino e la legge dal server finto (oggetto, testi, immagini, script scappato); mette un testo nel calendario per oggi
(e uno con un'immagine `javascript:`, rifiutato), lancia "Esegui adesso" e controlla le due email, l'ancora, lo stato
"inviato", e che il secondo giro non mandi niente. Atteso: 28 su 28. Una volta sola per installazione (il giorno resta segnato).

I dati del pannello di prima (danilofailli.it): `prove/social-dati-vecchi.sql` crea `social_pillar` e la colonna
`social_summary` su un'installazione con prefisso `cms_` e toglie le tabelle dei social; poi
`php -r 'require "includes/config.php"; require "includes/social.php"; social_installa();'` nella cartella del CMS, e le
righe giuste devono essere nel calendario e nei testi (quella con uno slug che non esiste si salta).

## tools/manuale-uso-schermate.js (1.4.2)

Le schermate del manuale d'uso, ed è anche una prova: esegue gli esempi del manuale come una persona (articolo scritto
nell'editor e pubblicato dalla scheda, articolo programmato, pagina "Lavora con noi" con colonne, immagine, elenco,
pulsante, sezione pronta e form compilato dal sito, menu, popup, campagna, utente, download) e si ferma al primo passo
che non riesce. Serve un plumeCMS appena installato con il kit Attività (`prova-kit.js <base> attivita <db> ...`), in una
cartella con un nome che si può mostrare (le schermate di Log cron e Aggiornamenti mostrano il percorso: si usa
`/home/studioprova/public_html`) e con l'indirizzo del sito `https://www.studioprova.it` (impostazione `sito_url`, scritta a mano
nella tabella delle impostazioni dopo l'installazione: così i testi social e le email del manuale non mostrano 127.0.0.1).
`node tools/manuale-uso-schermate.js <base> prova Collaudo-Officina-2026`. Dura circa
5 minuti. Atteso: nessun "FERMO", 70 immagini, sotto 1450 KB in tutto; poi `node tools/manuali-pdf.js` e il manuale
d'uso sotto 1900 KB.

## prova-file-pannello.js (1.4.1)

Fogli e script del pannello con la data nell'indirizzo. `node prove/prova-file-pannello.js <base> <utente> <password>`.
Fa il browser con la memoria vecchia: ogni foglio o script del pannello chiesto senza `?v=` riceve i colori della 1.3.
Sceglie Cosmo, Lavagna, Bottega e Aria in Il mio accesso e controlla che la barra laterale cambi ogni volta, che le
quattro miniature siano diverse, che nessuna pagina (dashboard, stile, articoli, flussi, editor, accesso) chieda un file
senza data. Sulla 1.4.0 dà 1 su 6 (il caso di Danilo); atteso: 6 su 6. Lascia l'utente su Aria.

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

### Aggiornamento 1.3.1 -> 1.4.0

Installazione 1.3.1 (`releases/cms/plumecms-1.3.1.zip`, kit Personale), `pagina-widget.php`, foto; `prova-aggiornamento.js`
con `plumecms-1.4.0-aggiornamento.zip` (ora controlla anche che la dashboard non abbia i riquadri delle installazioni
nuove); foto; rientro da zero dopo l'aggiornamento (1.4.1). 28 ottobre 2026: 12 su 12, sito identico al pixel tranne i minuti del Conto alla rovescia.

## prova-fine-articolo.js (1.6.0)

Articoli > Aggiungi sempre alla fine, come un utente, su un plumeCMS appena installato (`prova-install.js`, due articoli
pubblicati). Prima `php prove/articolo-largo.php <cartella CMS>`: crea `/articolo-largo/`, un articolo fatto con sezioni
dell'editor a tutta larghezza e una Griglia di articoli in fondo (le sue schede sono `<article>`).
`node prove/prova-fine-articolo.js <base> <utente> <password> /benvenuto/ /secondo-articolo-di-prova/ [cartella schermate] [/articolo-largo/]`.
Linguetta nella lista degli articoli (non in quella delle pagine) e menu Articoli acceso; Crea apre l'editor con un articolo
finto e il riquadro in fondo, con il testo di partenza; in bozza il sito non lo mostra e l'anteprima della pagina sì;
Accendi prima di pubblicare rifiutato; Pubblica: in fondo a ogni articolo, dentro la colonna di lettura, ombreggiato, senza
spazio in più sopra il titolo, non nella home; nell'articolo largo dopo le sezioni, largo quanto la lettura e al centro,
mai dentro una scheda della griglia; un pulsante aggiunto (dall'API dell'editor) ha il colore che ha in una pagina, non
quello dei link dell'articolo, staccato dal testo e senza spazio in più in fondo; telefono a 390 px senza scorrimento di
lato; Spegni e Accendi; versioni con l'anteprima; Header e footer senza il riquadro. Atteso: 41 su 41, in radice e in
cartella (base `http://127.0.0.1:<porta>/test`). Una volta sola per installazione. Lascia `fine-*.png`.
Le prove di attacco del riquadro sono il gruppo 22 di `tools/sicurezza-prova.js`; `prova-aspetti.js` apre anche
`fine-articolo.php` nei quattro aspetti.

## prova-su-misura.js (1.7.0)

Il sito su misura, come un utente, da zero. Serve una cartella del CMS appena copiata, senza `includes/config.local.php`,
servita su `<base>` (`php -S ... prove/router-php-s.php`), e un database vuoto che l'utente `danilo` può usare (host
`127.0.0.1:3308`, come `prova-install.js`).
`DBPASS=<password del database> node prove/prova-su-misura.js <base> <db> [cartella schermate]`.
Installatore con la carta Su misura, le quattro domande (Indietro torna alle carte), risposte con HTML e un'emoji ripulite;
il sito che ne esce (menu, pagine scelte e non scelte, foto finte SVG, Bologna nei testi e nella mappa); dashboard, Primi
passi e Media > Da sostituire; Sostituisci con un PNG vero (la foto finta sparisce, la home mostra quella vera) e i rifiuti
(percorso fuori dalle foto finte, file che non è un'immagine); il sito ricomposto dalla dashboard con risposte nuove (la home
cambiata resta); Definitivo, e la pagina non ricompone più. Atteso: 34 su 34. Alla fine il sito è definitivo: per rifarla
servono una cartella e un database nuovi. Con la cartella delle schermate lascia `su-misura-*.png`.
Dalla 1.7.0 `prova-kit.js` conta sei carte al passo 4 e `prova-tipo-sito.js` sei carte nella dashboard (con Su misura).

## prova-firma.js (1.8.0)

La frase in fondo al sito ("Un nuovo sito plumeCMS") e il banner "Visita il sito ufficiale plumeCMS" della dashboard.
`node prove/prova-firma.js <base del CMS> <utente> <password>`, su un CMS installato (qualunque kit). Cambia la frase in
Impostazioni e alla fine la rimette com'era. Atteso: 14 su 14, in radice e in cartella.

## prova-form-avviso.js (1.9.0)

A chi arriva l'avviso di un form (Dopo l'invio, "Manda l'avviso a"). Su un CMS installato con un kit (la pagina Contatti
ha il form) e con il server di posta finto acceso (`python3 -I prove/smtp-finto.py <porta> <cartella posta> &`):
`node prove/prova-form-avviso.js <base> <utente> <password> <porta smtp> <cartella posta>`. Scrive il server finto in
Impostazioni e compila il form tre volte (il limite è 5 all'ora per IP: prima di rilanciarla, svuotare
`<prefisso>moduli_invii`). Atteso: 13 su 13, anche su un sito aggiornato dalla 1.8.0.

## prova-widget-nuovi.js (1.10.0)

I sette widget per le attività (Orari di apertura, Barra contatti, Recensioni, Prima e dopo, Squadra, Slider, Eventi).
Prima `php prove/pagina-widget-nuovi.php <cartella CMS>` (la pagina `/widget-nuovi/`, con le foto del kit di partenza),
poi `node prove/prova-widget-nuovi.js <base> <cartella CMS> <utente> <password>`. Controlla anche i calcoli degli orari a
giorni e ore fissi (`prove/orari-cli.php`). Atteso: 40 su 40. Le prove di attacco sui widget nuovi (gruppo 26) usano
`prove/widget-cattivi.php` e partono con `CMS_DIR`.

## Addon Prenotazioni, Eventi con iscrizione, Raccolta recensioni (2.0.0)

Ognuna su un sito nuovo installato con il kit Attività (`prova-kit.js ... attivita`, utente `prova`), con il server di
posta finto acceso: `python3 -I prove/smtp-finto.py <porta> <cartella posta> &`. La prova accende il suo addon da
Impostazioni > Addon e scrive il server finto in Impostazioni > Configurazione email da sola.

- `node prove/prova-addon-prenotazioni.js <base> <cartella CMS> <porta smtp> <cartella posta>`. Fa girare anche
  `prove/prenotazioni-cli.php` (posti a ore fisse: ora legale, fine mese, anticipi, ferie, pausa, doppia prenotazione,
  promemoria) prima di ogni prenotazione vera. Atteso: 60 su 60, nella radice e con `CMS_CARTELLA=/test`.
- `node prove/prova-addon-eventi-iscrizione.js <base> <cartella CMS> <porta smtp> <cartella posta>`. Atteso: 45 su 45.
- `node prove/prova-addon-recensioni.js <base> <cartella CMS> <porta smtp> <cartella posta>` (accende per un momento
  anche Prenotazioni). Atteso: 39 su 39.
- `node prove/attacchi-addon-b.js <base> <cartella CMS> <porta smtp> <cartella posta>`: 80 attacchi, ogni OK è un
  attacco respinto. Accende i tre addon e crea un redattore `red_addon`.

Usano `prove/addon-b-comune.js` e `prove/addon-b-cli.php` (pagine con un widget, SQL di prova con `{p}` per il prefisso,
il cron frequente con l'orologio spostato in avanti). Due prove insieme vogliono due server di posta finti diversi: le
email si riconoscono dal destinatario, e le prove usano gli stessi indirizzi.

## prova-pannello-telefono.js (2.0.1)

Il pannello al telefono: apre ogni pagina del menu (sottovoci comprese) a 390x844 come amministratore e controlla che non
scorra di lato; per una pagina larga dice gli elementi più interni che escono dallo schermo. Pagine in più come quarto
argomento, separate da virgola; `LARGHEZZA=768` (o altro) per un'altra misura.
`node prove/prova-pannello-telefono.js <base> <utente> <password> [pagine in più]`. Atteso: tutto OK a 390, 768 e 1280 px,
anche con gli otto addon accesi (30 ottobre 2026: 81 su 81 con venti pagine interne).

## prova-hero-pulsanti.js (2.0.2)

I pulsanti dell'Apertura si cliccano davvero. Prima `php prove/pagina-hero-pulsanti.php <cartella CMS> <url foto> > elenco.txt`
(una pagina per disposizione ed effetto della foto, 66, più `/hp-link-a-mano/` con i link scritti senza la barra). Poi
`node prove/prova-hero-pulsanti.js <base> elenco.txt`: per ogni pulsante l'elemento sotto il suo centro e sotto i due lati è il
pulsante, il link non è `#`, il clic porta alla pagina giusta; a 1280 e a 390 px. Con `TEMA=cosmo UTENTE=... PASSWORD=...` nelle
anteprime del tema. Atteso: 670 su 670, anche con Cosmo, Aria e Bottega (31 ottobre 2026); sul codice di prima della 2.0.2 la
pagina dei link scritti a mano dà KO (il pulsante punta a `#`).

## prova-consegna-email.js e attacchi-consegna.js (2.1.0)

La consegna delle email (`includes/consegna.php`, Impostazioni > Consegna delle email). Su un CMS installato con il kit
Attività:
1. `python3 -I prove/smtp-finto.py <porta smtp> <cartella posta> &`;
2. `node prove/prova-consegna-email.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS> <porta api finta>`;
3. `node prove/attacchi-consegna.js` con gli stessi argomenti.
Le prove scrivono nel `config.local.php` il blocco "prove della consegna" (`CMS_POSTA_API_PROVA`, `CMS_DNS_PROVA`,
`CMS_SNS_CERT_PROVA`), creano il DNS finto e il certificato SNS delle prove in `/tmp/plume-consegna-<porta api>` e accendono da
sole `prove/api-posta-finta.py` (Brevo, SES con la firma SigV4 ricalcolata, Mailgun) sulla porta api. Aiuti:
`prove/consegna-comune.js`, `prove/consegna-cli.php`. Atteso: 73 su 73 e 40 su 40 (31 ottobre 2026). Lasciano dati di prova.
