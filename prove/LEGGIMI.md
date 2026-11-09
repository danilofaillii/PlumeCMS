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

## prova-segmenti.js

I segmenti della 2.10.0 (fase 3 del piano Brevo): motore delle condizioni, pagina Segmenti, Aggiungi filtro, viste, lista da
un segmento, esportazione, bivi dei flussi.

1. Un CMS installato (anche nuovo, senza kit); il sito si riempie da solo con 5000 contatti finti `@seg-prova.it`
   (`php prove/segmenti-cli.php <cartella CMS> semina`), eventi, form e un flusso: va su un sito da buttare.
2. `node prove/prova-segmenti.js <base> <utente> <password> <cartella del CMS> [cartella schermate]` (`SEMINA=0` per non
   rifare i contatti). Ogni condizione si confronta con una query scritta a mano (`segmenti-cli.php confronta`), i bivi dei
   flussi con il codice della 2.9.0 copiato (`segmenti-cli.php flussi`); per un sito aggiornato, `segmenti-cli.php bivi`
   prima e dopo l'aggiornamento dà due JSON da confrontare.
3. Atteso (2.10.0): tutto OK, ogni conteggio sotto un secondo.

## prova-campagna-riepilogo.js

Le campagne a riepilogo della 2.11.0 (fase 4 del piano Brevo), con la pagina di disiscrizione e il centro preferenze.

1. Un CMS installato (anche nuovo, senza kit) e il server di posta finto: `python3 -I prove/smtp-finto.py <porta> <cartella> &`.
   I dati della prova (`@cr-prova.it`, liste e campagne "CR ") li mette e li toglie `prove/campagne-cli.php` (`semina`,
   `pulisci`): va su un sito da buttare.
2. `node prove/prova-campagna-riepilogo.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS> [cartella foto]`.
   Il tempo che passa (ora programmata, secondo lotto) si simula spostando le date nel database.
3. Atteso (2.11.0): 99 su 99, in radice e in cartella.

## prova-campagne-vecchie.js

Le campagne fatte con la 2.10.0, dopo l'aggiornamento alla 2.11.0: stesso HTML al byte e stesso oggetto, aperte e mandate.

1. Sul sito 2.10.0: `php prove/campagne-cli.php <cartella> semina_vecchie`, poi `php prove/campagne-cli.php <cartella> impronte > prima.json`.
2. Aggiornamento alla 2.11.0 dal pannello (`prove/prova-aggiornamento.js`), server di posta finto acceso.
3. `node prove/prova-campagne-vecchie.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS> prima.json`.
4. Atteso: tutto OK (nove email uguali al byte a quelle che dava la 2.10.0).

## prova-editor-email.js

L'editor delle email a blocchi della 2.12.0 (fase 5 del piano Brevo), con i modelli, la galleria e le sezioni salvate.

1. Un CMS installato e il server di posta finto: `python3 -I prove/smtp-finto.py <porta> <cartella> &`. I dati della prova
   ("EE ...", `@ee-prova.it`, e quelli "CR ..." di `campagne-cli.php semina`) li mette e li toglie `prove/editor-email-cli.php`
   (`pulisci`): va su un sito da buttare.
2. `node prove/prova-editor-email.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS> [cartella foto]`.
   Con la cartella foto: le schermate dell'editor e le foto dell'email arrivata a 600 e 390 px (`email-600.png`,
   `email-390.png`), da guardare a occhio. La miniatura di YouTube del blocco video senza rete esterna non si carica (si vede
   il testo alternativo).
3. Atteso (2.12.0): tutto OK, in radice e in cartella, su un sito nuovo e su uno aggiornato dalla 2.11.0.

## prova-ab.js

Il test A/B delle campagne della 2.13.0 (fase 6 del piano Brevo), con il tempo spostato.

1. Un CMS installato e il server di posta finto: `python3 -I prove/smtp-finto.py <porta> <cartella> &`. I dati della prova
   (42 contatti `ab..@cr-prova.it` nella lista "CR AB", campagne "CR AB ...") li mette e li toglie `prove/campagne-cli.php`
   (`semina_ab`, `pulisci`); la fine del test si fa arrivare con `campagne-cli.php ab_indietro <campagna> <secondi>`, i giri del
   cron con `campagne-cli.php giro` (due lanciati insieme per il resto partito una volta sola). Va su un sito da buttare.
2. `node prove/prova-ab.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS> [cartella foto]`.
3. Atteso (2.13.0): tutto OK, in radice e in cartella, su un sito nuovo e su uno aggiornato dalla 2.12.0.

## prova-report.js

Il report delle campagne, le statistiche e l'orario migliore della 2.14.0 (fase 7 del piano Brevo), con eventi contati a mano
(scritti in cima al file) e il tempo spostato.

1. Un CMS installato e il server di posta finto **con i rimbalzi**: `python3 -I prove/smtp-finto.py <porta> <cartella> rimbalzi &`.
   I dati della prova (contatti `@rp-prova.it`, liste, campagne e segmenti "RP ...") li mette e li toglie `prove/report-cli.php`
   (`semina`, `semina_orario`, `semina_ab`, `pulisci`); `semina_grande 20000` mette i 20000 destinatari per i tempi e
   `pulisci_grande` li toglie alla fine (le prove dopo, come Contatti a pagine e il limite orario, non li devono vedere).
   Va su un sito da buttare.
2. `node prove/prova-report.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS> [cartella foto]`.
3. Atteso (2.14.0): tutto OK, in radice e in cartella, su un sito nuovo e su uno aggiornato dalla 2.13.0.
4. Le campagne di prima: sul sito 2.13.0 `report-cli.php semina`, tre `giro`, `eventi_vecchi`, poi
   `report-cli.php conta_vecchie > prima.json`; dopo l'aggiornamento lo stesso comando deve dare lo stesso file.

## tools/sicurezza-prova.js



Le prove di attacco: ogni OK è un attacco respinto (445 nella 2.28.0, con le 12 nuove del gruppo 43 sulle pagine per zona; 433 nella 2.27.0, con le 13 nuove del gruppo 42 sulle sedi; 420 nella 2.25.0 su un sito appena installato, radice e cartella `/test`, con le 9
nuove del gruppo 26b su Scorri di lato; dalla 2.25.0 la prova dell'immagine caricata (gruppo 14) chiede l'indirizzo dalla radice del
server, perché nei siti in cartella l'indirizzo dato dai Media ha già la cartella; 238 nella 1.10.0 su un sito Attività, con le 5 nuove del gruppo 26 sui
widget nuovi; 233 nella 1.9.0 su un sito Attività, con le 6 nuove del gruppo 25 sull'avviso
dei form; 249 nella 1.8.0 sullo stesso sito su misura in prova: le 7 nuove,
gruppo 24, sono sulla frase in fondo al sito; 227 su un sito Attività; 242 nella 1.7.0, su un sito su misura ancora in prova: le 41
nuove, gruppi 23 e 23b, sono sul sito su misura; 226 sullo stesso tipo di sito già definitivo; 201 nella 1.6.0,
le 24 nuove, gruppo 22, sono su Aggiungi sempre alla
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

## prova-temi-nuovi.js (gli otto temi della 2.5.0)

Estate, Inverno, Autunno, Primavera, Giallo, Rosso, Verde e Blu, prima in anteprima da amministratore
(`?preview=1&tema=<nome>`) e poi attivati dal pannello (Editor > Temi, pulsante Attiva) e guardati da un visitatore:
home, `/servizi/`, `/benvenuto/`, blog, `/tutti-i-widget/` e `/tutte-le-hero/` (se ci sono) e la 404, a 1280 e a 390 px.
Controlla errori JavaScript, scorrimento di lato, lo sfondo esatto delle tinte piene e il **contrasto WCAG di ogni
testo** (corpo, titoli, link, pulsanti, campi, anche nell'header e nel footer), misurato contro quello che c'è davvero
dietro (`document.elementsFromPoint`, sfumature comprese; un testo sopra una foto non si misura). Alla fine rimette
Cosmo e l'aspetto di base dal pannello.
1. Sito con il kit Attività (`prova-kit.js`), `php prove/pagina-widget.php <cartella>` e
   `php prove/pagina-hero.php <cartella> ""` (le undici aperture senza foto).
2. `node prove/prova-temi-nuovi.js <base> <utente> <password> [temi] [cartella schermate]`. Con la cartella salva
   `<tema>.png` (home a 1280 px, tema attivato) e `<tema>-intera.png`. Per lavorare su un tema: `PAGINE=home,widget`,
   `MISURE=1280`, `SOLO_ANTEPRIMA=1`, `TUTTI=1` (ogni testo sotto 4,5).

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
`node prove/prova-sito-plumecms.js <base> <cartella CMS> <utente> <password> <pacchetto.zip[,parte-2.zip,...]> <versione>`: i pacchetti si
caricano e si applicano in ordine (dalla 2.1.0 di solito uno: `sito-plumecms-<V>.zip`, o il pacchetto unico di `tools/consegna.py`,
che porta anche il CMS: allora controlla anche la versione). Il nome atteso nel pannello lo legge dal manifest. Atteso: 55 su 55 con quattro parti (notte del 27 ottobre 2026: anche la piuma della
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

Dalla 2.17.0 le disposizioni sono ventisei: senza elenco `prova-hero.js` le prova tutte (atteso 364, più 11 con
l'editor). `pagina-hero.php` riempie anche i campi nuovi (righe del terminale, parola, etichette, punto della lente,
forma; il video con `VIDEO=<url di un mp4 del sito>`); con `SUFFISSO=-nf` e la foto vuota fa `/hero-<id>-nf/`, che
`prova-hero.js` apre con `SUFFISSO=-nf`. Con `TEMA=cosmo` (o un altro tema) e `UTENTE`, `PASSWORD` nell'ambiente le
pagine si aprono nell'anteprima del tema, da amministratore, e le foto si chiamano `<tema>-<id>-pc.png`.

## prova-aperture-nuove.js (2.17.0)

I campi delle quindici disposizioni nuove, come li usa una persona. Prima `pagina-hero.php` con `VIDEO=` (con e senza foto,
`SUFFISSO=-nf`). `node prove/prova-aperture-nuove.js <base> <utente> <password> <id di /hero-terminale/>`: righe del
terminale scritte una alla volta e ferme con meno movimento, parola traforata con il titolo intero nell'h1, video che parte
al clic (e la pagina senza video né prima e dopo non carica `hero-c.js`), etichette dentro la foto, lente sul punto,
costellazione, maniglia del prima e dopo con frecce, Inizio e mouse, niente scorrimento di lato a 390 px; nell'editor i
campi nuovi solo con la loro disposizione e la finestra Scegli in modo video. Atteso: 46 su 46. Non salva niente.
Attacchi: gruppo 39 di `tools/sicurezza-prova.js` (con `CMS_DIR`), che usa `prove/pagina-hero-cattive.php`.

## prova-ai-collegate.js (2.32.0)

L'addon AI collegate come lo userebbero una persona e un'AI: un client MCP scritto nella prova (Node, senza librerie) fa il
collegamento OAuth 2.1 intero (metadati della risorsa e del server, registrazione dinamica, PKCE S256, consenso nel pannello con
il browser, codice, token, rinnovo ruotato e riusato, revoca da endpoint e da pannello, Rifiuta, redattore, amministratore a due
fattori), poi tutti gli strumenti (lettura, audit, bozza mai pubblicata anche chiedendo "pubblicato", modifica di una bozza,
rifiuto su un pubblicato, proposte approvate, corrette, in gruppo e scartate, foto dai tre archivi finti con autore e licenza,
`get_stats` solo con l'addon Statistiche), la chiave personale creata nel pannello con REST e OpenAPI, e le quattro pagine
dell'addon a 390 px.

1. Un CMS installato (anche senza kit), radice o cartella. L'addon lo accende la prova (`prove/ai-cli.php <cartella> accendi`).
2. Il server finto degli archivi di foto: `setsid nohup php -S 127.0.0.1:<porta> prove/foto-finte.php >/dev/null 2>&1 </dev/null &`.
   La prova scrive `CMS_AI_FOTO_PROVA` nel `config.local.php` del sito.
3. `node prove/prova-ai-collegate.js <base> <cartella del CMS> <utente> <password> <porta delle foto finte> [cartella foto]`
   (in cartella la base è `http://127.0.0.1:<porta>/test` e la cartella del CMS quella con `/test`).
4. Atteso (2.32.0): 90 su 90 in radice, in cartella e su un sito aggiornato dalla 2.31.0. Pulisce le sue tabelle alla fine
   (`ai-cli.php pulisci`), lascia la pagina `ai-prova-pubblicata` e le foto importate nei Media.

Gli attacchi all'addon sono il gruppo 46 di `tools/sicurezza-prova.js` (26, con `CMS_DIR`; la batteria intera 488 sulla 2.32.0, 503 sulla 2.33.0 con il gruppo 47, 516 sulla 2.34.0 con il gruppo 48): accende l'addon e il server finto
delle foto sulla porta `SIC_FOTO` (9298), e alla fine li rimette come erano.

## prova-ai-stile.js (2.34.0)

La terza fase dell'addon AI collegate: lo stile del sito e gli articoli nuovi dagli argomenti, come li usano un'AI (con una chiave
personale, via MCP) e l'amministratore (nel browser). Dieci articoli seminati con uno stile riconoscibile (`ai-cli.php
stile-semina`: frasi brevi in seconda persona, una domanda nell'attacco, due H2, un elenco, un link interno; metà con l'editor;
il buco "concime naturale" fra le parole correlate e nel testo di quattro articoli; la categoria "Balcone prova" con un articolo e
il tag "Lumache prova" su uno solo); poi `analyze_style` (misure coerenti con quelle volute), `save_style_profile` e
`get_style_profile` (permessi, testo corto, script), la pagina Stile del sito (misure in parole, Ripristina, Salva a mano),
`suggest_topics` (il buco primo, categoria povera, tag con un articolo, le formule dello stile scartate), `propose_article_ideas`
buone e cattive (tutte o nessuna), Approva, Scarta, Modifica con la data prevista e Sposta prima nella pagina Idee per articoli,
`list_article_ideas` con l'ordine del piano, `create_draft` con `idea_id` (stati sbagliati rifiutati senza bozze, il legame, la
coerenza con uno scostamento voluto), `update_draft` che rientra nello stile, 390 px, e i tempi con 300 articoli (`stile-molti`;
`SENZA_MOLTE=1` li salta).

1. Un CMS installato con il kit Attività (`prove/prova-kit.js ... attivita ...`), radice o cartella. L'addon lo accende la prova.
2. `node prove/prova-ai-stile.js <base> <cartella del CMS> <utente> <password> [cartella foto]`.
3. Atteso (2.34.0): 53 su 53 in radice e in cartella. Alla fine toglie articoli, profilo, idee e la bozza scritta (`stile-pulisci`).

Gli attacchi sono il gruppo 48 di `tools/sicurezza-prova.js` (15, con `CMS_DIR`).

## prova-ai-navigazione.js (2.33.0)

La seconda fase dell'addon AI collegate: navigazione e schede degli articoli, come le usano un'AI (con una chiave personale, via
MCP) e l'amministratore (nel browser). Il menu Principale del kit rovinato apposta (`ai-cli.php nav-prepara`: voce verso una bozza,
voce doppia, voce verso una pagina che non c'è, etichetta lunga a maiuscole miste, otto voci) e una pagina orfana; poi
`analyze_navigation`, `propose_menu` cattive e buone, i due alberi nel pannello, Approva il menu e il sito pubblico, il conflitto
quando il menu cambia dopo la proposta (`menu-cambia`), `review_article` e `list_articles_to_review` su un articolo pieno di
problemi (`articolo-prova`), `propose_article_changes` raggruppate con Approva tutte e Scarta tutte, `propose_navigation_fix` su un
pubblicato e su una bozza, la pagina Proposte a 390 px, e i tempi con 300 pagine e cinque menu (`nav-molte`; `SENZA_MOLTE=1` li salta).

1. Un CMS installato con il kit Attività (`prove/prova-kit.js ... attivita ...`), radice o cartella. L'addon lo accende la prova.
2. `node prove/prova-ai-navigazione.js <base> <cartella del CMS> <utente> <password> [cartella foto]`.
3. Atteso (2.33.0): 52 su 52 in radice e in cartella. Alla fine rimette il menu (`nav-ripristina`) e toglie pagine e articoli di prova;
   le 300 pagine di `nav-molte` le toglie anche lei. Le proposte approvate cambiano davvero la pagina "Lavora con noi" (di prova) e
   l'articolo di prova, che poi vengono cancellati.

Gli attacchi sono il gruppo 47 di `tools/sicurezza-prova.js` (15, con `CMS_DIR` e il kit Attività).

## prova-avvisi-pannello.js (2.31.0)

Il pulsante "Apri nel pannello" negli avvisi con il Reply-To del visitatore (Impostazioni > Configurazione email, "Gli avvisi che
ricevi"). Su un CMS installato (anche nuovo, senza kit), con una porta libera per il server di posta finto, che la prova accende
e spegne da sola:
`node prove/prova-avvisi-pannello.js <base> <utente> <password> <cartella del CMS> <porta smtp> <cartella posta> [cartella foto]`.
Manda i cinque avvisi veri (form, richiesta di prenotazione, Prenotazioni, Vetrina, Iscrizioni agli eventi) con
`prove/avvisi-cli.php` (che scrive anche il server SMTP finto e l'indirizzo degli avvisi `negozio@esempio.it`), prima con la
casella mai salvata, poi accesa, poi spenta dal pannello; controlla riquadro, anteprima (iframe sandbox), prova dal pannello,
390 px. Lascia un form "Prova avvisi" e i suoi invii: va su un sito da buttare. Atteso (2.31.0): 36 su 36 in radice, in
cartella e su un sito aggiornato dalla 2.30.0.

## prova-kit.js (1.3.0)

I kit di partenza dell'installatore. Su una cartella appena estratta e un database vuoto (come per `prova-install.js`):
`DBPASS=... node prove/prova-kit.js <base> <kit> <db> <cartella schermate>`, con `<kit>` fra `vuoto`, `personale`,
`attivita`, `blog`, `portfolio`; `DBHOST=127.0.0.1:3309` per un database su un altro server (MariaDB, MySQL 5.7; di
partenza `127.0.0.1:3308`), `PREFISSO=cms_b_` per una seconda installazione nello stesso database. Installa scegliendo il kit al passo 4, poi visita ogni pagina del menu dell'header al
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

## prova-copia-ripristino-sicuro.js (2.25.1)

Il ripristino sicuro della Copia del sito (tabelle di appoggio, scambio in una RENAME, collazioni di un altro server, blocco,
marcatore a coppie [marchio, tabella] con il prefisso, che non tocca le tabelle di un'altra installazione, e pulizia aprendo
Copia del sito, INSERT solo letterali, CREATE senza vincoli o rimandi verso altre tabelle (REFERENCES, UNION=) e con TABLESPACE
rifiutato con il motivo vero, istruzioni impilate (secondo giro, 5 novembre 2026: una CREATE con un apice dentro un nome fra
apici inversi e un secondo comando, un nome con l'apice, una INSERT con un punto e virgola fuori dalle virgolette: rifiutate;
una INSERT con punto e virgola e apici inversi dentro un valore: accettata; `db()` che non esegue due istruzioni in una),
registro su file uno per tentativo, pagina di soccorso con il nome del database e il prefisso (mai l'utente) solo con
`aggiornamenti/soccorso.txt`, che vale 24 ore, registro a 15 righe, snapshot degli Aggiornamenti sullo stesso motore con la
modalità SQL rimessa, un aggiornamento che non parte durante un ripristino). Servono quattro siti
installati con lo stesso utente: A (kit Attività, prefisso `cms_`), C (kit Personale, prefisso `plume_c_`, database suo), B2
(prefisso `cms_b_` nello STESSO database di A, come per `prova-copia-pulita.js`), D (kit Vuoto, per la pagina di soccorso).
Per le prove fra server, un sito su MariaDB 10.6 (`SITO_MARIADB="<base>|<cartella>"`) e uno su MySQL 5.7 (`SITO_MYSQL57`),
installati con l'host del loro server (`DBHOST=127.0.0.1:3309 node prove/prova-kit.js ...`; in Docker: `mariadb:10.6` sulla
3309, `mysql:5.7` sulla 3310). Il server php di C deve essere un
`php -S 127.0.0.1:<porta> -t <cartella C> prove/router-php-s.php` senza cartella: la prova lo uccide a metà ripristino
(`CMS_RIPRISTINO_PAUSA` scritto nel config di C) e lo riaccende. `FOTO_MANUALE=1` scrive anche la schermata del manuale
(`27-soccorso.jpg`, con i segni dei passi) nella cartella di uscita.
`FOTO_MANUALE=1 SITO_MARIADB=... SITO_MYSQL57=... DBPASS=... node prove/prova-copia-ripristino-sicuro.js <base A> <cartella A>
<base C> <cartella C> <base B2> <cartella B2> <base D> <cartella D> <utente> <password> <cartella uscita>`.
Atteso: 114 su 114 (5 novembre 2026, secondo giro; senza i due siti degli altri server 105 su 105). Lascia A come la sua prima copia (con uno
snapshot manuale in più), C con i contenuti del sito MariaDB (o di A), D rimesso; le copie di prova (collazione, rotta,
sottoquery, funzione, impilata, nome, insert-impilata, insert-strana, tablespace, vincolo, union, commento) restano nelle
cartelle `aggiornamenti/copie/` dei siti. Prima di rilanciarla sugli stessi siti: l'amministratore di C torna `prova`.

## prova-infografiche-15.js (2.10.0; sedici dalla 2.26.0)

Le infografiche disegnate in SVG (`includes/editor-infografiche.php`), quindici dalla 2.10.0 e sedici dalla 2.26.0 (Barre a
confronto). `prove/pagina-infografiche-15.php <cartella CMS> normali|estremi|notte|cattivi|vuota|togli` scrive le pagine di prova
(la prova le rifà da sola). `node prove/prova-infografiche-15.js <base> <cartella CMS> <utente> <password> [cartella delle foto]`:
a 1280, 768 e 390 px i numeri calcolati, gli aria-label, nessuna scritta sovrapposta o fuori; i valori estremi; il contrasto
senza tema, con Aria, Cosmo, Autunno e con il corpo scuro; dall'editor tutte dalla tavolozza, voci cambiate, pubblica, il
sito. Atteso: 47 su 47 (2.26.0; 44 fino alla 2.25.1). I valori cattivi stanno nel gruppo 32 di `tools/sicurezza-prova.js`.

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
Atteso: 57 su 57. Dura circa 8 minuti. Non crea contenuti.

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
del sito come se fosse WordPress (restano i paragoni). Atteso: 13 su 13.

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

## prova-email-conferma.js (2.7.0)

L'email di conferma dell'iscrizione modificabile (Form > riquadro Email di conferma; Newsletter > Impostazioni). Su un CMS
installato con il kit Attività e il server di posta finto acceso:
`node prove/prova-email-conferma.js <base> <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato>`.
Usa `prove/email-conferma-cli.php` (il codice della 2.6.0 copiato, per il confronto al byte; `richiesta` per il modulo della
newsletter) e `prove/tema-cli.php` (il redattore). Si rilancia sullo stesso sito: toglie prima gli iscritti `@persona.it`.
Atteso (1 novembre 2026): 44 su 44.

## prova-contatti-brevo.js (2.8.0)

La base dei contatti come Brevo (fase 1 di `docs-sviluppo/brevo-confronto.md`): pagina Contatti (colonne fisse, ricerca,
Carica una lista, filtri, Personalizza le colonne, le azioni di gruppo, "tutti i contatti del filtro"), Crea un contatto,
scheda del contatto (Panoramica, Cronologia, Liste, Preferenze), attributi dei sei tipi più calcolati e globali, cartelle e
liste, lista nera (a mano, togliere uno alla volta, indirizzi scritti a mano, 5 soft bounce, cambio di email), Elimina
definitivamente con l'impronta, segnaposti `{{ contact.X }}` in una campagna, CSV con le formule neutralizzate, redattore,
390 e 768 px. Usa `prove/contatti-cli.php` e `prove/tema-cli.php` (il redattore).
1. Un CMS installato con il kit Attività; poi `php prove/contatti-cli.php <cartella CMS> semina` (contatti nei cinque stati di
   prima, tre liste "PC", una campagna inviata con aperture e clic, una programmata, un flusso con un cammino in corso).
2. `node prove/prova-contatti-brevo.js <base> <utente> <password> <cartella CMS> [cartella schermate]`.
3. Migrazione: su un sito **2.7.0** fai il passo 1 (con il codice della 2.7.0), `contatti-cli.php conta > prima.json`, aggiorna
   (`prova-aggiornamento.js`), `contatti-cli.php conta > dopo.json` (devono essere uguali), poi la prova con `MIGRATO=1`.
Atteso (1 novembre 2026): 94 su 94 su un sito nuovo, 102 su 102 con `MIGRATO=1` su un sito aggiornato dalla 2.7.0. Lascia dati
di prova: per rilanciarla, `contatti-cli.php pulisci` e di nuovo `semina`.

## prova-widget-nuovi.js (1.10.0)

I sette widget per le attività (Orari di apertura, Barra contatti, Recensioni, Prima e dopo, Squadra, Slider, Eventi).
Prima `php prove/pagina-widget-nuovi.php <cartella CMS>` (la pagina `/widget-nuovi/`, con le foto del kit di partenza),
poi `node prove/prova-widget-nuovi.js <base> <cartella CMS> <utente> <password>`. Controlla anche i calcoli degli orari a
giorni e ore fissi (`prove/orari-cli.php`). Atteso: 40 su 40. Le prove di attacco sui widget nuovi (gruppo 26) usano
`prove/widget-cattivi.php` e partono con `CMS_DIR`.

## prova-sedi.js (2.27.0)

Le sedi e la SEO geografica, come le userebbe una persona. Nel pannello (SEO e AEO, Sedi) crea due sedi: una dal tipo in
elenco, con le coordinate chieste al servizio finto e il segnaposto spostato sulla mini mappa (le tessere di OpenStreetMap
le risponde la prova stessa e ne controlla gli indirizzi z/x/y), salvata con "Crea la pagina della sede"; una con "Altro, lo
scrivo io" ("centro di meditazione" -> "Centro di meditazione"), prima con gli errori sotto i campi. Poi il sito: la pagina
della sede, il JSON-LD di home, pagina e pagina della sede (due LocalBusiness con i campi, `mainEntity`, `areaServed` dei
servizi), il titolo con `%citta%`, la sitemap, i widget Sedi e Dati dell'attività sul sito e nell'editor (la pagina
`/prova-sedi/` di `prove/pagina-sedi.php`), Mappa, Orari e Barra "dalla sede", l'eliminazione di una sede con la pagina che
resta, il redattore fuori, 1280 e 390 px senza scorrimento di lato.
1. Un sito installato (va bene il kit Attività: la prova usa `/chi-siamo/` e `/servizi/`), e il servizio delle coordinate finto:
   `php -S 127.0.0.1:<porta> prove/nominatim-finto.php &`; nel `config.local.php` del sito:
   `define('CMS_NOMINATIM_URL', 'http://127.0.0.1:<porta>/search'); define('CMS_NOMINATIM_PROVA', true);`
   (senza `CMS_NOMINATIM_PROVA` un servizio su 127.0.0.1 è rifiutato, come per i webhook).
2. `node prove/prova-sedi.js <base> <cartella CMS> <utente> <password> [cartella foto]`. Usa `prove/sedi-cli.php` (elenco, azzera,
   salva, geocodifica, jsonld, sql, redattore). Alla fine toglie le sedi e le pagine che ha creato.
3. Atteso (2.27.0): 58 su 58, in radice e in cartella.
Gli attacchi sono il gruppo 42 di `tools/sicurezza-prova.js` (con `CMS_DIR`): codice in ogni campo, link `javascript:` e host non di
Google, coordinate storte, 60 sedi e 200 righe di zona, redattore, senza token, servizio finto che risponde HTML cattivo,
`CMS_NOMINATIM_URL` verso indirizzi interni.

## prova-zone.js (2.28.0)

Le pagine per zona, come le userebbe una persona. Su un sito col kit Attività e una sede "Sede di Bologna" (la crea la prova con
`prove/sedi-cli.php`): dal pannello (SEO e AEO, Zone) crea tre bozze per "Idraulico" (Bologna, Casalecchio di Reno, Provincia di
Bologna) con la pagina Servizi collegata; controlla titoli, indirizzi, stato bozza, i tre segnaposto `[DA SCRIVERE]`, il
registro; gli errori sotto i campi (servizio di una lettera, modello senza `%zona%`, 31 zone); la stessa coppia che non si ricrea
(anche con maiuscole diverse) e una pagina cancellata che sparisce dal registro. Poi prova a pubblicare una bozza con i segnaposto
da ogni strada (Pubblica dell'editor, scheda del contenuto, azione in blocco, Impostazioni dell'editor, salvataggio diretto con
`pubblica=1`): rifiutata con il messaggio, resta bozza. Compila i riquadri con `prove/zone-cli.php scrivi` (scrive l'albero come
farebbe una persona nell'editor), prima due su tre (rifiuto con "1 riquadro"), poi tutti, e pubblica dall'editor: la pagina esce
200 con il JSON-LD (Service con `name`, `serviceType`, `areaServed` City o AdministrativeArea, `provider` = la sede; `mainEntity`;
briciole Home > Servizi > zona), il widget Altre zone con le sole zone pubblicate, la sitemap, il titolo; home e Servizi senza nodi
in più; il filtro per servizio e Togli dal registro; il redattore fuori dal pannello Zone ma che scrive e pubblica la bozza con la
stessa regola; 1280 e 390 px senza scorrimento di lato.
1. Un sito installato col kit Attività (la prova usa `/servizi/` e `/contatti/`).
2. `node prove/prova-zone.js <base> <cartella CMS> <utente> <password> [cartella foto]`. Usa `prove/zone-cli.php` (elenco, azzera,
   crea, segnaposto, togli, scrivi, pubblica, pubblicabile, jsonld, sql, redattore) e `prove/sedi-cli.php`. Alla fine toglie le
   pagine per zona e le sedi che ha creato.
3. Atteso (2.28.0): 58 su 58, in radice e in cartella, su un sito nuovo e su uno aggiornato dalla 2.27.0. Atteso (2.37.1): 90 su 90
   (con la parte 2.36.0 e la parte 11 della 2.37.1: 62 voci tolte dal registro come faceva la 2.35.0, il riquadro "fuori dal
   registro", Rimettile nel registro, i doppioni, Lasciale fuori), provato in radice su MariaDB 10.11.
Gli attacchi sono il gruppo 43 di `tools/sicurezza-prova.js` (con `CMS_DIR`): codice in servizio, zone, modelli e descrizione,
`../` nel modello del titolo (lo slug resta pulito), modello senza `%zona%`, 500 zone in un colpo, pagina del servizio inventata o
con SQL, 210 zone contro il limite di 200, pubblicazione con i segnaposto da ogni strada anche dall'amministratore e ripubblicazione
con il segnaposto rimesso, il segnaposto mai nel JSON-LD, redattore, senza token e da un altro sito.

## prova-seo-strumenti.js (2.29.0)

Gli strumenti SEO, come li userebbe una persona. Crea quattro pagine e un articolo di prova (fatti con l'editor, con la keyword
"vaso in terracotta") e due foto PNG minime nella libreria, poi: la Tabella SEO (SEO e AEO > Tabella SEO) con i semafori giusti
(Blog senza descrizione, "Chi siamo" con il titolo doppio, l'articolo a posto, la pagina dei vasi con un'immagine senza alt), i
filtri (solo problemi, tipo, ricerca, nessun risultato), la modifica nella riga di titolo per Google e descrizione salvata senza
ricaricare e letta sul sito (`<title>` e meta description), la revisione lasciata; nell'editor il riquadro **Collega** del
controllo SEO con le pagine suggerite, Copia il link (gli appunti del browser: il contesto ha i permessi `clipboard-read` e
`clipboard-write`), Inserisci nel Testo selezionato (prima senza un Testo selezionato, poi con), la riga dei link interni verde,
il link sul sito dopo Pubblica; la scheda classica con Copia il link; i dati per Google in più dalla scheda (Prodotto, How-to con
tre passi aggiunti dal pulsante, Video prima con un indirizzo `javascript:` rifiutato e poi giusto, "Nessuno" che toglie il nodo)
con i nodi nel JSON-LD del sito e Servizi identica al byte; la Vetrina accesa da Impostazioni > Addon e il Product della scheda
di un prodotto creato dalla riga di comando; Media > Senza alt (Nelle pagine e Nella libreria, due alt salvati senza ricaricare,
l'alt che esce sul sito, la colonna della tabella a 0); il redattore (menu con la sola Tabella SEO, seo.php e sedi.php chiuse,
salva dalla riga, apre Senza alt); 1280 e 390 px senza scorrimento di lato; la pulizia (pagine, articolo, prodotto, foto, alt,
redattore, Chi siamo e Blog come prima, la Vetrina com'era).
1. Un sito installato con il kit Attività (`prova-kit.js`; la prova usa `/servizi/`, `/chi-siamo/` e `/blog/`).
2. `node prove/prova-seo-strumenti.js <base> <cartella CMS> <utente> <password> [cartella foto]`. Usa `prove/seo-strumenti-cli.php`
   (sql, analisi, suggeriti, jsonld, tabella, alt_pagine, alt_libreria, alt_azzera, pagina, articolo, addon, prodotto, foto,
   foto_togli, redattore).
3. Atteso (2.29.0): 71 su 71, in radice e in cartella.
Gli attacchi sono il gruppo 44 di `tools/sicurezza-prova.js` (con `CMS_DIR`): codice nei campi della tabella e degli schema,
foto e video `javascript:`, `data:` e `http:`, 500 passi di how-to, senza token e da un altro sito, tipo `modelli` e id
inesistente, un alt per un file fuori dalla libreria, redattore (configurazione SEO, coordinate), keyword con codice nei
suggerimenti, alt con codice sul sito.

## prova-scorri.js (2.25.0)

Il widget **Scorri di lato** e le sue cinque disposizioni (pellicola, carte, fermo, tempo, profondita).
1. `php prove/pagina-scorri.php <cartella CMS> <foto> <foto>` (per esempio due WebP di `sito-plumecms/immagini/`): copia le foto
   in `<immagini>/prova-scorri/` e crea `/scorri-<disposizione>/` (testo, il widget con quattro pagine, due con la foto, testo),
   `/scorri-due/`, `/scorri-tutte/` e `/scorri-editor/` (solo un testo). Va rilanciato prima di ogni giro: la prova
   pubblica `/scorri-editor/` con il widget.
2. `node prove/prova-scorri.js <base> <utente> <password> [cartella foto]`. Per ogni disposizione, a 1280 e 390 px, senza tema e
   con Cosmo, Aria e Bottega in anteprima: la radice è larga quanto la finestra, la scena si ferma sotto la testata, scorre di
   lato fino all'ultima pagina, poi la pagina riparte in verticale; mai scorrimento di lato; contrasto di titolo e testo; con Tab
   dal link prima del widget si arriva ai cinque pulsanti, ognuno in vista, sopra a tutto e non coperto. Poi due widget nella
   stessa pagina, meno movimento e senza JavaScript (pagine una sotto l'altra), la tela dell'editor e il widget creato dal
   pannello (pagine aggiunte, Sposta su, trascinamento, Elimina, Linea del tempo con la data, il massimo di 12, Pubblica).
   `TEMI=,cosmo` e `DISP=pellicola,fermo` per provarne solo alcuni; `SCHERMATE=<cartella>` scrive anche le JPEG del manuale
   d'uso (`08-scorri-*.jpg`).
3. Atteso (2.25.0): 601 su 601, in radice e in cartella (`<base>` = `http://127.0.0.1:<porta>/test`).
Gli attacchi sono il gruppo 26b di `tools/sicurezza-prova.js` (con `CMS_DIR`), che usa `prove/scorri-cattivi.php` e salva la
pagina `/scorri-cattivi/`.

## Addon Prenotazioni, Eventi con iscrizione, Raccolta recensioni (2.0.0)

Ognuna su un sito nuovo installato con il kit Attività (`prova-kit.js ... attivita`, utente `prova`), con il server di
posta finto acceso: `python3 -I prove/smtp-finto.py <porta> <cartella posta> &`. La prova accende il suo addon da
Impostazioni > Addon e scrive il server finto in Impostazioni > Configurazione email da sola.

- `node prove/prova-addon-prenotazioni.js <base> <cartella CMS> <porta smtp> <cartella posta>`. Fa girare anche
  `prove/prenotazioni-cli.php` (posti a ore fisse: ora legale, fine mese, anticipi, ferie, pausa, doppia prenotazione,
  promemoria) prima di ogni prenotazione vera. Atteso: 60 su 60 con la 2.0.0, nella radice e con `CMS_CARTELLA=/test`;
  85 su 85 con la 2.1 (allegati .ics letti dalle email del server finto, prenotazione scritta dal pannello, colonne nuove
  su un sito della 2.0.0; provata nella radice).
- `node prove/prova-addon-eventi-iscrizione.js <base> <cartella CMS> <porta smtp> <cartella posta>`. Atteso: 45 su 45
  (2.0.0), 47 su 47 con la 2.1 (colonna `svolto_at` su un sito della 2.0.0, «1 evento concluso» una volta sola).
- `node prove/prova-addon-recensioni.js <base> <cartella CMS> <porta smtp> <cartella posta>` (accende per un momento
  anche Prenotazioni e, dalla 2.1, Eventi con iscrizione). Atteso: 39 su 39 (2.0.0), 51 su 51 con la 2.1 (inviti dopo gli
  eventi).
- `node prove/attacchi-addon-b.js <base> <cartella CMS> <porta smtp> <cartella posta>`: 80 attacchi (89 con la 2.1: la
  prenotazione scritta dal pannello, A18 e D2), ogni OK è un attacco respinto. Accende i tre addon e crea un redattore `red_addon`.

Usano `prove/addon-b-comune.js` (dalla 2.1 anche `allegati(email)` e `ics(testo, campo)` per gli allegati delle email) e `prove/addon-b-cli.php` (pagine con un widget, SQL di prova con `{p}` per il prefisso,
il cron frequente con l'orologio spostato in avanti). Due prove insieme vogliono due server di posta finti diversi: le
email si riconoscono dal destinatario, e le prove usano gli stessi indirizzi.

## prova-pannello-telefono.js (2.0.1)

Il pannello al telefono: apre ogni pagina del menu (sottovoci comprese) a 390x844 come amministratore e controlla che non
scorra di lato; per una pagina larga dice gli elementi più interni che escono dallo schermo. Pagine in più come quarto
argomento, separate da virgola; `LARGHEZZA=768` (o altro) per un'altra misura.
`node prove/prova-pannello-telefono.js <base> <utente> <password> [pagine in più]`. Atteso: tutto OK a 390, 768 e 1280 px,
anche con gli otto addon accesi (30 ottobre 2026: 81 su 81 con venti pagine interne).

## prova-ux-pannello.js (UX 2026-11)

Il pannello si legge nei quattro aspetti. Sceglie ogni aspetto da Il mio accesso, apre le pagine del menu e alcune schede
(SEO, Newsletter, Sicurezza, Consegna) e misura nel browser: testi almeno 4,5:1 (3:1 i grandi), bordi dei campi almeno 3:1
sul fondo intorno, segnaposto almeno 4,5:1, titolo di ogni riquadro in una fascia con fondo diverso dal corpo e testo almeno
4,5:1, pulsante della scelta file con l'aspetto del pannello, niente scorrimento di lato a 390 px. Alla fine rimette Aria.
`node prove/prova-ux-pannello.js <base> <utente> <password> [aspetti, es. aria,cosmo] [filtro delle pagine, regex]`;
`TUTTO=1` stampa anche gli OK. Atteso (1 novembre 2026, kit Attività): 1207 su 1207 senza addon, 1667 su 1667 con gli otto
addon accesi. Sul codice di prima (2.5.0) dava 773 su 1175. Non misura i fondi a gradiente né gli elementi trasparenti. Dal secondo giro UX (1 novembre 2026) prova anche la finestra di conferma del pannello in Editor > Temi (fuoco dentro, Esc,
pulsante rosso, nessun dialogo del browser): 1694 su 1694 con gli otto addon accesi.

## conferme-pannello.js (UX 2026-11)

Le conferme del pannello (`data-conferma`) non sono più `confirm()` del browser ma una finestra del pannello
(`pannello.js`). Una prova che accettava le conferme con `p.on('dialog', d => d.accept())` scrive
`pnConferme.on(p, d => d.accept())` (o `pnConferme.once`), con `const pnConferme = require('./conferme-pannello')`: il gestore
riceve lo stesso oggetto (`message()`, `type()`, `accept()`, `dismiss()`) per i dialoghi veri e per la finestra del pannello,
e `accept()`/`dismiss()` premono i suoi pulsanti. `pnConferme.ferma(p, true)` lascia la finestra aperta (per fotografarla:
`tools/manuale-uso-schermate.js`, foto 01-conferma), `pnConferme.ferma(p, false)` riprende. Le prove che ascoltano i dialoghi per scoprire un `alert()` iniettato
restano con `p.on('dialog')`.

## prova-bozze-vuote.js (UX 2026-11)

Le bozze vuote nel cestino dopo un giorno (giro del mattino, `cron_bozze_vuote()`). Crea dal pannello cinque bozze (articolo e
pagina vuoti, articolo con un testo salvato dall'editor, articolo con il titolo cambiato nella scheda, articolo vuoto appena
nato), le invecchia con `prove/bozze-cli.php`, preme Esegui adesso in Log cron e controlla che vadano nel cestino solo le due
vuote vecchie. `node prove/prova-bozze-vuote.js <base> <cartella CMS installato> <utente> <password>`. Atteso: 11 su 11.
Lascia le sue bozze nel cestino.

## prova-hero-pulsanti.js (2.0.2)

I pulsanti dell'Apertura si cliccano davvero. Prima `php prove/pagina-hero-pulsanti.php <cartella CMS> <url foto> > elenco.txt`
(una pagina per disposizione ed effetto della foto, 66, più `/hp-link-a-mano/` con i link scritti senza la barra). Poi
`node prove/prova-hero-pulsanti.js <base> elenco.txt`: per ogni pulsante l'elemento sotto il suo centro e sotto i due lati è il
pulsante, il link non è `#`, il clic porta alla pagina giusta; a 1280 e a 390 px. Con `TEMA=cosmo UTENTE=... PASSWORD=...` nelle
anteprime del tema. Atteso: 670 su 670, anche con Cosmo, Aria e Bottega (31 ottobre 2026); sul codice di prima della 2.0.2 la
pagina dei link scritti a mano dà KO (il pulsante punta a `#`).

## prova-scegli-media.js (2.1.0)

Scegli dai Media dappertutto, come una persona. Prepara da solo 130 immagini finte (`uploads/2026/10/scmp-*.png`), gli otto
addon accesi, una campagna, un corso. Per ogni campo immagine del pannello (SEO: immagine social, logo e immagine
dell'organizzazione, foto della persona; Social: le due immagini; Popup; Corsi; Eventi) apre la finestra, cerca, sceglie,
carica un'immagine nuova dalla finestra, salva dove serve, toglie con la X. Poi la scheda dell'articolo, Stile del sito (logo e
favicon), l'immagine della newsletter (indirizzo completo, cartella della newsletter), la Vetrina (foto aggiunte all'elenco),
la tastiera (Invio, frecce, Esc, fuoco che torna al pulsante, una sola fermata di Tab per la griglia), le 130 immagini a
pagine di 60 scorrendo, la tendina delle cartelle, l'indirizzo scritto a mano e il telefono (finestra a tutto schermo).
`CMS_DIR=<cartella del CMS installato> node prove/prova-scegli-media.js <base> <utente> <password>` su un sito con il kit
Attività. Atteso: 112 su 112 (31 ottobre 2026, su un sito nuovo e su un 2.0.3 aggiornato con il pacchetto unico).

## prova-scegli-link.js e attacchi-scegli-link.js (2.2)

Scegli dal sito, come una persona. `prove/scegli-link-cli.php` prepara da solo pagine e articoli di prova (`sl-*`, una pagina in
bozza, un titolo con `<script>`), una categoria, un tag, un redattore, gli otto addon accesi, un evento (e uno in bozza), un
prodotto, un corso; alla fine li toglie e rimette form, social e privacy degli addon com'erano. Per ogni campo link (editor:
Apertura, Immagine, Testo; Popup, Form, Newsletter, Social, SEO, Menu, privacy di Eventi e Prenotazioni) apre la finestra,
cerca, sceglie, salva e fa clic sul sito (indirizzo finale e 200); poi tastiera, bozza, telefono a 390 px, redattore, cambio
d'indirizzo con il redirect 301. Gli attacchi: senza login, senza token, da un altro sito, ricerche cattive, `javascript:`.
`CMS_DIR=<cartella del CMS installato> node prove/prova-scegli-link.js <base> <utente> <password>`, idem
`attacchi-scegli-link.js`, su un sito con il kit Attività, in radice e in cartella. Atteso: 80 su 80 in radice, 81 su 81 in
`/test`; attacchi 32 su 32 (31 ottobre 2026).

## prova-consegna-unica.js (2.1.0)

La consegna con il pacchetto unico di `tools/consegna.py`: controlla lo zip di consegna (pacchetto unico, zip completo, due
manuali, LEGGIMI), il manifest (solo voci che il motore 2.0.3 conosce, nessuna destinazione doppia, codice del CMS, Download
senza il manuale degli addon), poi lancia `prova-sito-plumecms.js` con il pacchetto unico e guarda il pannello aggiornato.
`node prove/prova-consegna-unica.js <base> <cartella CMS> <utente> <password> <plumecms-V-consegna.zip> <versione>`.
Provata su un 2.0.3 nuovo e su un 2.0.3 con le cinque parti del sito della 2.0.0 già applicate (il manuale degli addon sparisce
e `/scarica/manuale-uso-addon/` porta a `/scarica/manuale-uso/`), con il server a 64 MB (`php -d upload_max_filesize=64M
-d post_max_size=64M -S ...`): il pacchetto unico pesa circa 13 MB. Per provarla senza toccare `cms/VERSIONE` si costruisce
tutto in una copia del repository con una versione finta (2.0.9). Atteso: 13 su 13 (e 87 su 87 dentro, con il blocco Scegli dai Media del sito).

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
## Le prove della 2.1 (in lavorazione): Google Drive e Dropbox, riservati fuori dagli elenchi, ripristino pulito

- **Server finti di Google Drive e Dropbox**: `python3 -I prove/server-cloud-finti.py gdrive <porta> <cartella> <redirect URI>`
  e lo stesso con `dropbox` (redirect URI separati da virgola, per esempio `http://127.0.0.1:8320/admin/oauth-ritorno.php`).
  Client di prova: Google `prova-plumecms.apps.googleusercontent.com` / `GOCSPX-prova-segreto-2026`, Dropbox `provadropbox2026` /
  `segretodropbox2026`. `GET /_stato` e `POST /_imposta` (vita dell'accesso, pezzo che fallisce, file per pagina) per le prove.
  **Non sono i servizi veri**: contro Google e Dropbox non si è provato (nessuna rete dalla sessione).
- `prova-addon-copia-altrove.js`: come prima più Google Drive e Dropbox. Porte: `PORTE_COPIA="ftp,ftps,webdav"` (di partenza
  8205-8207), `CLOUD_GOOGLE` e `CLOUD_DROPBOX` (8321, 8322). La prova scrive da sé nel config `CMS_COPIA_ALTROVE_LOCALE`,
  `CMS_COPIA_ALTROVE_CLOUD_PROVA` e `CMS_COPIA_ALTROVE_PEZZO` (pezzi da 256 KB) e li toglie alla fine. `SOLO=cloud` salta FTP e
  WebDAV. Atteso: 91 su 91 (31 ottobre 2026).
- `prova-addon-membri.js`: in più la scheda (riquadro "Area riservata") e gli elenchi pubblici (ricerca per titolo, sitemap,
  feed, llms) prima, da fuori e da membro. Su un sito nuovo.
- `attacchi-addon-a.js`: in più i gruppi 11 (OAuth: servono i server finti con il redirect URI del sito) e 12.
- `prova-copia-pulita.js` e `attacchi-copia.js`: due siti nello stesso database, A con `cms_` e B con `cms_b_`
  (`PREFISSO=cms_b_ node prove/prova-kit.js ...` per B). `node prove/prova-copia-pulita.js <base A> <cartella A> <base B>
  <cartella B> <utente> <password>`, idem `attacchi-copia.js`. Lasciano A come la sua prima copia; tolgono i collegamenti
  simbolici che creano.

## prova-ux-editor.js (controllo di UX, novembre 2026)

Le correzioni del controllo di UX e UI di editor, installatore e addon (`docs-sviluppo/ux-ui-2026-11-editor-addon-sito.md`),
provate nel browser: errori del database dell'installatore in italiano (su una copia non installata), fuoco da tastiera
visibile e non tagliato, widget della tavolozza aggiunti con Tab e Invio, ricerca vuota, nome di ogni campo delle schede di
un widget, finestra Immagini, contrasto dell'editor in Cosmo e Aria anche con l'opacità, anello dei colori globali, header
senza avviso H1 e senza indirizzo tecnico, avviso dell'editor al telefono, Agenda e Vetrina.
`node prove/prova-ux-editor.js <base con kit Attività e otto addon accesi> <utente> <password> [base di una copia non installata]`.
Atteso (con le scelte di Danilo del 1 novembre): 71 su 71, con la copia non installata, il database vuoto (quinto argomento, DBPASS
nell'ambiente) e la pagina /addon-tutti/ di `pagina-addon-a.php` (vedi in cima al file). Non installa e non salva niente; alla fine rimette Aria.

## prova-download-limite.js (2.6.0)

Download con un server dal limite basso: i giorni dei link riservati in un riquadro loro, il file troppo grande detto sotto
il campo prima dell'invio, il riservato caricato e in elenco, l'invio oltre `post_max_size` con un messaggio in parole (prima
"Token CSRF non valido"). Sito col kit, server avviato con `php -d upload_max_filesize=1M -d post_max_size=2M -S ...`.
Uso: `node prove/prova-download-limite.js <base> [utente] [password]`. 8 controlli.

## prova-test.js e attacchi-test.js

L'addon Test e questionari della 2.22.0 (`cms/addon/test/`, `docs-sviluppo/addon/test.md`).

1. Un CMS appena installato (`prova-install.js`, anche in cartella): va su un sito da buttare. Il server di posta finto:
   `python3 -I prove/smtp-finto.py <porta> <cartella posta> &`.
2. `node prove/prova-test.js <base> <cartella del CMS> <porta smtp> <cartella posta> [cartella foto]`: accende l'addon dal
   pannello, crea un test per tipo con la procedura in cinque passi, li fa come un visitatore (con e senza JavaScript, al
   telefono), controlla i risultati calcolati a mano, link, simulatore, statistiche, CSV, email, lista, automazioni,
   Riquadro in evidenza, Scegli dal sito, Duplica, Elimina, spento e riacceso. Usa `prove/test-cli.php`.
3. Subito dopo, sullo stesso sito: `node prove/attacchi-test.js <base> <cartella del CMS> <cartella posta>`.
   Lascia un redattore `red_test` e un test "XSS ...". Alla fine svuota `<prefisso>test_limiti` (lo fa da sola).
4. Atteso (2.22.0): tutto OK, in radice e in cartella.

2.23.0: `prova-test.js` ha la sezione D (domande sulla persona, risposta breve e completa, il risultato completo per email con
e senza newsletter, i tag alla conferma, l'innesco "Riceve un tag", le statistiche per gruppi con la soglia di 5, il CSV, un
test scritto con la 2.22.0 confrontato al byte con il codice di c3b99e9, estratto con `git archive` nella cartella
`addon/test-vecchio` del sito e poi tolto); `attacchi-test.js` il gruppo G. Atteso: 141 su 141 e 71 su 71, in radice e in cartella.

2.24.0: il pannello del test è fatto di carte che si aprono in finestre (`<dialog>`), con il telefono dell'anteprima: gli aiuti
di `prova-test.js` aprono le carte (`apriCarta`, `fatto`, `apriDomanda`) e trascinano con il mouse vero (`trascina`, SortableJS
con `forceFallback`). Sezione E: i tre modi (una per pagina, a pagine, tutte), la lavagna (trascinamento fra colonne e verso
"+ Pagina nuova", la tendina Pagina, Invio ed Esc con il fuoco che torna alla carta, i titoli), l'anteprima che cambia mentre
scrivi, i cinque passi di tutti e tre i tipi, "Anteprima" a 390 px, il sito pagina per pagina con e senza JavaScript, "Tutte
in una pagina", un test senza modo che resta una domanda per schermata. `attacchi-test.js` gruppo H: pagine maligne (chiavi
inventate, doppioni, titoli con HTML e script, 4 byte, 5000 pagine, modo inventato), CSRF e redattore. Atteso: 196 su 196 e
82 su 82, in radice e in cartella. `prova-ux-pannello.js` ha le pagine dei cinque passi (con una finestra aperta per passo,
`&apri=<finestra>`): con il filtro `a=test`, dopo `prova-test.js` (il test 1 deve esserci).

## prova-tag.js e attacchi-tag.js (2.23.0)

I tag dei contatti: scheda del contatto, azioni di gruppo e filtro dei Contatti, pagina Tag, segmenti (contati a mano), automazioni
(innesco "Riceve un tag", azioni "Aggiungi tag" e "Togli tag", con le email del giro nel server finto), form (passo 1, con e senza
doppia conferma), importazione (aggiungi e sostituisci), esportazione, telefono a 390 px.

1. Un CMS appena installato (`prova-install.js`, anche in cartella) e il server di posta finto:
   `python3 -I prove/smtp-finto.py <porta> <cartella posta> &`. Va su un sito da buttare (contatti `@tag-prova.it`).
   Su un sito dove ha già girato `prova-test.js` la pagina Tag non è vuota e la prova si ferma subito: va lanciata prima, o su un sito suo.
2. `node prove/prova-tag.js <base> <cartella del CMS> <porta smtp> <cartella posta> [cartella foto]`. Usa `prove/tag-cli.php`.
3. Subito dopo, sullo stesso sito: `node prove/attacchi-tag.js <base> <cartella del CMS>` (lascia il redattore `red_tag`).
4. Atteso (2.23.0): 57 su 57 e 25 su 25, in radice e in cartella.

## prova-robots-articoli.js (2.33.0)

robots.txt con una sola riga `Sitemap:` (all'indirizzo del sito, anche in cartella) e i vecchi indirizzi `/articoli/<slug>/`
con un 301 all'articolo, anche con una regola `start` su `/articoli/` nella tabella dei redirect (che resta valida per gli slug
che non sono articoli, e per le bozze). 14 controlli. Su un sito appena installato (serve l'articolo di partenza `/benvenuto/`),
radice e cartella: `node prove/prova-robots-articoli.js <base> <cartella del CMS>`. Mette e toglie i suoi redirect e la sua
bozza con `prove/redirect-cli.php`. Con la 2.32.0 dà 9 su 14 (i due difetti).

## prova-seo-plume.js (2.35.0)

Le correzioni SEO e AEO dell'audit di plumecms.it: llms.txt calcolato con le pagine (niente sezioni vuote, niente Glossario senza
termini, mai bozze né pagine nascoste a Google, quello scritto a mano che vince), i dati per Google "Programma" dalla scheda nel
browser (anche i valori cattivi e 390 px), la parola chiave delle pagine, le pagine dei pacchetti con `tldr`, `faq`, `keyword` e
`seo_schema`, la voce `seo` del manifest.
1. Un CMS installato con il kit Attività (`prove/prova-kit.js ... attivita ...`), radice o cartella.
2. `node prove/prova-seo-plume.js <base> <cartella del CMS> <utente> <password> [cartella foto]` (con `prove/seo-plume-cli.php`
   e `prove/seo-strumenti-cli.php`). Per il sito identico dopo l'aggiornamento: sul sito 2.34.0 col kit
   `SALVA_HTML=<file> node prove/prova-seo-plume.js ...` (salva sette indirizzi e esce), poi l'aggiornamento, poi
   `CONFRONTA_HTML=<file> node prove/prova-seo-plume.js ...` (confronta al byte e fa il resto).
3. Atteso (2.35.0): 42 su 42 in radice e in cartella; 49 su 49 con `CONFRONTA_HTML` dopo l'aggiornamento 2.34.0 -> 2.35.0.
   Alla fine toglie le pagine di prova e rimette llms e descrizione generale.

Gli attacchi sono il gruppo 49 di `tools/sicurezza-prova.js` (7, con `CMS_DIR`).
