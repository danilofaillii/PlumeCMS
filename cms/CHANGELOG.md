# Novità

Il prodotto si chiama **plumeCMS** (nome scelto il 26 ottobre 2026; prima, per poche ore, "CarbonaraCMS", e ancora prima il provvisorio "Officina": gli zip vecchi si chiamavano `officina-*` e `carbonaracms-*`).

## 1.9.1 (30 ottobre 2026) - Form dentro Newsletter e form

- **Richiesta di Danilo**: nel menu del pannello la voce **Form** non sta più da sola: è in **Newsletter e form**, fra
  Iscritti e liste e Invii dei form. La pagina e i form non cambiano. Solo l'amministratore la vede, come la pagina.

## 1.9.0 (30 ottobre 2026) - A chi arriva l'avviso di un form

- **Segnalazione di Danilo**: creando un form non si poteva scegliere a chi arriva l'email di avviso a ogni compilazione;
  andava sempre all'indirizzo degli avvisi del sito. Ora la scheda del form, riquadro **Dopo l'invio**, ha **Manda
  l'avviso a**: uno o più indirizzi (al massimo cinque), separati da una virgola o uno per riga. Vuoto: l'indirizzo degli
  avvisi del sito, come prima, scritto sotto la casella. Ogni indirizzo riceve la sua email.
- Un indirizzo sbagliato ferma il salvataggio e il pannello dice quale. L'elenco dei form dice a chi va l'avviso.
- Nel widget Modulo dell'editor, il testo d'aiuto dice dove si sceglie. I siti che si aggiornano hanno la colonna nuova
  da soli e, finché non la compilano, continuano come prima.

## 1.8.0 (29 ottobre 2026) - La frase in fondo al sito e il sito ufficiale nella dashboard

- **Richiesta di Danilo**: sotto il piede di ogni pagina una riga piccola, **Un nuovo sito plumeCMS**, con il link a
  `https://plumecms.danilofailli.it`. Chi installa la cambia o la spegne in **Impostazioni > Sito pubblico**, riquadro
  "La frase in fondo al sito": testo, indirizzo del link (vuoto: niente link), acceso o spento. Il testo è solo testo
  (HTML ed emoji tolti); un indirizzo che non è http o https torna quello ufficiale. Esce anche con i temi.
- **Dashboard**: in cima, sempre e per tutti, la riga "Visita il sito ufficiale **plumeCMS**", con il link al sito
  ufficiale in una scheda nuova.

## 1.7.0 (29 ottobre 2026) - Il sito su misura

- **Richiesta di Danilo**: chi installa plumeCMS scrive che sito vuole e il sito si compone da solo, con foto finte da
  sostituire. Nell'installatore, al passo 4, c'è una carta nuova, **Su misura**: quattro domande (che attività è, fra dieci,
  dove si trova, cosa offre, fino a sei servizi, e quali pagine vuole fra Chi siamo, Servizi, Listino, Galleria,
  Recensioni, Domande frequenti, Blog e Contatti, con "una pagina per ogni servizio" se serve). Ne escono home, pagine,
  menu e testi pensati per quell'attività, il form dei contatti e la mappa della città.
- **Le foto finte**: disegni SVG con i colori del sito, l'icona dell'attività e cosa ritrarre ("La sala", "Un piatto",
  "Lo chef"). Stanno in Media, nella cartella nuova **Da sostituire**: il pulsante **Sostituisci** sotto ognuna carica la
  foto vera e la mette al suo posto in tutte le pagine, articoli, header e footer, poi cancella quella finta. Solo
  l'amministratore. I Primi passi hanno una voce in più per i siti su misura: "Le tue foto al posto di quelle finte".
- **Dalla dashboard**: finché il tipo di sito è in prova, la carta Su misura apre le stesse domande (già compilate con le
  risposte di prima, se il sito è già su misura). Con le risposte nuove il sito si ricompone come negli altri cambi di tipo:
  copia del sito prima, e quello che hai scritto o cambiato resta tuo (anche una pagina in cui hai già messo una foto vera).
- Le emoji nei nomi dei servizi si tolgono (diventano anche il titolo e l'indirizzo di una pagina).
- Sicurezza, per tutti i siti: un'immagine che il server non riesce a riscrivere (per esempio una GIF finta) e che contiene
  codice non entra più in Media, dall'editor o da Sostituisci; prima restava nella cartella delle immagini (dove PHP è spento,
  quindi non si eseguiva). Trovato dalle prove di attacco della 1.7.0 (242 respinti).

## 1.6.4 (29 ottobre 2026) - Due manuali

- **Decisione di Danilo**: con plumeCMS restano solo il manuale d'uso e il manuale dei temi. Il manuale tecnico e i
  documenti per chi sviluppa (`cms.md`, `cms-moduli.md`, `editor-pagine.md`, `sicurezza.md` e gli altri) non stanno più
  nello zip, nel sito di plumeCMS e nella pagina Download. La cartella `docs/` ha i due manuali e le schermate.
- Le pagine del pannello che citavano quei documenti (Informazioni, Temi) ora rimandano ai due manuali.

## 1.6.3 (28 ottobre 2026) - Due inviti affiancati, alti uguali

- Corretto: due widget "Invito all'azione" in due colonne affiancate uscivano di altezza diversa (quello con più testo era più
  alto: la pagina Scarica del sito di plumeCMS, segnalata da Danilo). Ora un Invito che sta da solo nella sua colonna la
  riempie, e le colonne di una riga sono già alte uguali. Nient'altro cambia.

## 1.6.2 (28 ottobre 2026) - L'indirizzo degli aggiornamenti arriva con gli aggiornamenti

- **Decisione di Danilo**: tutte le installazioni chiedono le versioni nuove a `https://plumecms.danilofailli.it`, e
  nessuno scrive più quell'indirizzo a mano. In Impostazioni > Aggiornamenti il campo "Indirizzo del sito da cui arrivano gli
  aggiornamenti" (nato nella 1.3.1) non c'è più; se un sito ci aveva scritto un indirizzo suo, da questa versione lo ignora.
  L'indirizzo sta nel codice e cambia solo con un aggiornamento.
- Nient'altro cambia nel pannello e nel sito. Prove: `prova-versione-sito.js` riscritta (5 controlli), `prova-versione.js` e
  il sito ufficiale finto valgono per qualunque versione (28 su 28 sulla 1.6.2).

## 1.6.1 (28 ottobre 2026) - Il sito ufficiale cambia indirizzo

- Il sito da cui plumeCMS si scarica, e a cui il pannello chiede "c'è una versione nuova?", diventa
  `https://plumecms.danilofailli.it` (prima `https://duhtron.it/test`, l'installazione di prova). Vale per chi installa da
  zero e per chi in Aggiornamenti ha lasciato il campo vuoto; chi ha scritto un indirizzo suo non cambia niente.
- Nient'altro: nessuna modifica al pannello né al sito.

## 1.6.0 (28 ottobre 2026) - Un riquadro in fondo a ogni articolo

- **Articoli, linguetta "Aggiungi sempre alla fine"** (accanto a Tutti, Pubblicati, Programmati, Bozze, Cestino; solo
  per l'amministratore). Apre una pagina nuova da cui si crea un riquadro che esce **sotto ogni articolo**, anche sotto
  quelli già pubblicati: un invito alla newsletter, un contatto, un libro da consigliare.
- **Il riquadro si compone con l'editor visuale**, come una pagina: qualunque widget, colori, pulsanti. Parte con un
  titolo ("Prima di andare") e un testo da riscrivere. Ha bozza, versioni e anteprima come header e footer: esce sul sito
  quando lo si pubblica con **Pubblica** nell'editor.
- **Acceso e spento**: la pagina del riquadro dice com'è (Non ancora creato, Spento, Acceso), e ha Modifica nell'editor,
  Accendi o Spegni, Vedi sotto l'ultimo articolo, Versioni. Spegni lo toglie dagli articoli senza perdere il lavoro;
  Accendi lo rimette (se è già stato pubblicato una volta). Più sotto, l'anteprima dell'ultimo articolo con il
  riquadro in fondo, anche con le modifiche non ancora pubblicate.
- **Sul sito** il riquadro sta dentro la colonna di lettura, dopo il testo, con ombra, bordo e angoli tondi che seguono
  i colori dello Stile del sito e dei temi. Non esce nelle pagine né nella home.
- **Esporta e importa** delle impostazioni dell'editor portano il riquadro insieme a header e footer (uno solo: chi
  importa aggiorna quello che c'è).
- Header e footer non dà più avvisi con il tipo di modello nuovo.
- Sicurezza: l'anteprima di un header, di un footer o del riquadro in bozza (indirizzo con `?preview=1&header=`,
  `&footer=`, `&fine=`) la vede solo l'amministratore. Prima bastava essere entrati nel pannello, anche da redattore.
- Corretto: con il kit vuoto le pagine Chi sono e Contatti mostravano "Scrivi qui il testo." al posto del testo
  d'esempio (c'era dalla 1.3.0).
- Tolte tre emoji scritte nei file (Newsletter > Flussi, anteprima dell'editor, menu di navigazione): ora sono entità
  HTML, a schermo uguali.
- Manuale d'uso: capitolo 16 nuovo, "Un riquadro in fondo a ogni articolo" (ora i capitoli sono 31). Manuale tecnico:
  capitolo 28. Prove: `prove/prova-fine-articolo.js` (41 controlli), 24 prove di attacco nuove (201 in tutto, tutte
  passate).

## 1.5.0 (28 ottobre 2026) - I social, ogni mattina

Prima stavano nel modulo di danilofailli.it, scritti per quel sito. Ora sono del CMS, per qualunque sito.

- **Social**, voce nuova del pannello (amministratore), con quattro linguette: Email del mattino, Calendario, Testi dei
  contenuti, Impostazioni.
- **L'email del mattino "Oggi sul sito <dominio>"**: al giro del mattino del cron, i contenuti che escono quel giorno,
  ognuno con le due immagini per i social (da scaricare), il testo pronto per LinkedIn, Facebook, Instagram, WhatsApp e
  Telegram (e Substack, se acceso) e i pulsanti per condividere. Una volta al giorno. Si vede in anteprima, di
  qualunque giorno, e si manda a mano con "Mandala adesso".
- **Il calendario dei testi social**: un testo per un giorno, con il contenuto a cui porta, l'ancora, i testi per canale
  e le immagini. Ogni mattina arriva "Social di oggi" per i testi di quel giorno e per quelli rimasti indietro; dopo
  l'invio il testo è segnato e non riparte.
- **Il riquadro Social nella scheda di ogni contenuto**: un testo per tutti i canali (le emoji vanno bene), e se serve
  uno diverso per LinkedIn, per Facebook e Instagram, per WhatsApp e Telegram. Il link e gli hashtag li aggiunge il CMS.
- **Le due immagini per i social**, fatte da sole dall'immagine in evidenza: 1200x630 per LinkedIn e Facebook,
  1080x1350 per Instagram.
- **Impostazioni**: canali accesi, hashtag fissi per canale, hashtag per categoria, un link in più nei testi di WhatsApp.
- I colori dei canali nelle email sono leggibili: pulsanti e titoli passano 4,5 a 1 (il blu di Facebook e il verde di
  WhatsApp dei marchi non ci arrivano, e sono un poco più scuri).
- **I dati di prima entrano da soli**: i trafiletti social degli articoli e i testi del pillar del pannello di
  danilofailli.it diventano i testi per i social e il calendario, con lo stato di invio.
- Manuale d'uso: capitolo 20 nuovo, "I social e l'email del mattino" (ora i capitoli sono 30). Manuale tecnico:
  capitolo 27. Prove: `prove/prova-social.js` (28 controlli), 14 prove di attacco nuove per la pagina Social (177 in tutto, tutte passate).

## 1.4.2 (28 ottobre 2026) - Il manuale d'uso

- **Manuale d'uso** (`docs/manuale-uso.md`, e in PDF): come si fa ogni cosa con plumeCMS, in 29 capitoli. Ogni capitolo
  ha un esempio vero fatto sul sito di prova "Studio Prova", le schermate con i passi numerati, una checklist e i
  problemi più frequenti. Le schermate le fa `tools/manuale-uso-schermate.js`, che esegue gli esempi come una persona:
  se un passo del manuale smette di funzionare, lo script si ferma.
- **Pubblica dalla scheda pubblica anche il testo scritto nell'editor.** Un articolo nuovo scritto nell'editor (Salva
  bozza) e pubblicato dalla scheda usciva senza testo: la bozza dell'editor restava indietro. Ora "Pubblica" su un
  contenuto in bozza pubblica anche l'editor; "Aggiorna" su un contenuto già online lascia stare una bozza in lavorazione.
- Nell'editor, il titolo nuovo di un articolo (Impostazioni) si vede subito nella tela.
- "1 minuto di lettura", non più "1 minuti".
- Menu di navigazione: i campi con lo stile del pannello (uscivano con quello del browser) e la Home una volta sola
  nell'elenco delle pagine.
- Form: la casella dell'etichetta di ogni campo era larga pochi pixel; ora si legge e si scrive.
- Utenti e le altre schede: le caselle della password, dell'email e dei numeri con lo stile del pannello.
- Configurazione email: niente più "testi social" e "report del test" (erano del sito da cui è nato plumeCMS).
- Dashboard: "Il cron del mattino non è mai partito" al posto di "non gira da mai".
- Accenti rimessi in tutto il pannello: può, più, già, perché, così, c'è, è stato (227 righe in 54 file, solo testi).
- Scheda di un articolo, quando non ci sono ancora categorie: dice "Ancora nessuna categoria" invece di un riquadro vuoto.

## 1.4.1 (28 ottobre 2026) - Il pannello nuovo si vede subito

- **Dopo un aggiornamento il pannello si vede con i fogli nuovi.** Il browser tiene CSS e JavaScript del pannello per un
  mese: dopo la 1.4.0 chi aggiornava vedeva ancora i colori della 1.3, e i quattro aspetti in Il mio accesso sembravano
  non cambiare niente (finché non ricaricava con Cmd+Maiusc+R). Ora ogni foglio e script del pannello porta nell'indirizzo
  la data del file (`?v=`): un file nuovo ha un indirizzo nuovo e il browser lo scarica. Il foglio degli aspetti si
  collega da ogni pagina invece di essere importato da `admin.css` ed `editor.css`.
- Prova nuova `prove/prova-file-pannello.js`: un browser con la memoria della 1.3 sceglie i quattro aspetti e li vede cambiare.

## 1.4.0 (28 ottobre 2026) - Il sito si prova, il pannello si sceglie, WordPress si porta qui

- **Impostazioni, Importa da WordPress**: scrivi indirizzo, utente e password per le applicazioni del sito WordPress (la
  pagina spiega in tre passi dove si crea, in Utenti > Profilo). Se qualcosa non va lo dice in parole semplici: password
  normale al posto di quella per le applicazioni, utente sbagliato, sito che non esiste o non risponde, certificato non
  valido, REST chiusa da un plugin, sito in http.
- **Il report**: plumeCMS legge tutto il sito (anche bozze e programmati) e mostra quanti articoli, pagine, categorie, tag,
  media (con il peso), menu, autori, titoli SEO di Yoast o Rank Math, redirect, e per ognuno cosa diventa. Dice anche cosa
  non si può portare e perché: commenti, page builder come Elementor e Divi (entra il testo), shortcode di plugin, campi
  personalizzati, prodotti di WooCommerce e altri tipi personalizzati, contenuti con un indirizzo già usato.
- **Scegli e importa**: tutto o una parte (anche solo le pagine, o gli articoli senza le bozze). Prima si fa da sola una
  copia del sito; poi l'import va a giri brevi con la barra dell'avanzamento, regge i limiti degli hosting condivisi e, se
  chiudi la pagina, riprende da dove era. Il testo arriva pulito (i video di YouTube e Vimeo restano video), le immagini si
  scaricano nella libreria e i link puntano ai nuovi indirizzi.
- **In prova, poi Conferma o Annulla**: quello che entra è invisibile ai visitatori finché non confermi; lo guardi
  nell'anteprima del sito, con il riepilogo di cosa è entrato e cosa no. **Conferma** pubblica tutto come era su WordPress
  (pubblicati, bozze, programmati), accende i redirect 301 dai vecchi indirizzi (con la data, `?p=123`, pagine e categorie
  annidate) e, se vuoi, mette il menu di WordPress nell'header. **Annulla** toglie tutto quello che è entrato.
- Sicurezza: solo l'amministratore; la password per le applicazioni resta cifrata solo per la durata dell'import e non
  compare mai; il CMS chiama solo il sito dato (niente indirizzi interni, niente rimandi verso altri siti, certificato
  controllato); i file si controllano dal contenuto (un PHP chiamato foto.jpg non entra); i testi passano dal sanificatore.
  30 prove di attacco nuove.
- **Il pannello ha quattro aspetti, e ognuno sceglie il suo**: Aria (chiaro, bianco e blu, con molto spazio), Bottega
  (carta crema, terracotta e verde salvia), Cosmo (scuro, notte e viola) e Lavagna (scuro, verde ardesia e testo color
  gesso). Si sceglie in **Il mio accesso**, riquadro "Aspetto del pannello": clic su una delle quattro miniature, poi
  "Usa questo aspetto". Vale solo per te: gli altri utenti tengono il loro, il sito non cambia. Dopo l'aggiornamento
  tutti partono da Aria. Seguono l'aspetto tutte le pagine del pannello e l'interfaccia dell'editor (la tela no: mostra
  il sito com'è). Login, password dimenticata e installatore sono sempre Aria.
- I testi del pannello si leggono meglio: in tutti e quattro gli aspetti il contrasto di ogni testo, anche quello piccolo
  e grigio, è almeno 4,5 a 1, misurato su ogni pagina. Il menu ha icone disegnate al posto delle emoji, e il pannello
  usa il carattere Inter servito dal sito stesso.
- **Foto più leggere da sole**: quando carichi una foto (pagina Media, editor, newsletter) il CMS la raddrizza se il
  telefono l'ha scattata di lato, la rimpicciolisce se il lato lungo supera 2560 pixel, toglie i dati nascosti (anche la
  posizione GPS) e prepara accanto una versione WebP, molto più leggera, che il sito manda ai browser che la accettano.
  Gli indirizzi delle foto non cambiano. Le foto già caricate restano come sono. Si spegne, e si cambia il lato
  massimo, nella pagina Media, riquadro "Foto più leggere" (solo l'amministratore).
- Corretto: nella scheda di un articolo, con la scheda Visuale aperta, sotto il testo compariva anche la casella del
  codice (l'attributo `hidden` non nascondeva niente nel pannello): ora si vede solo con la scheda Testo.
- Corretto: nella scheda di un articolo il foglio dell'anteprima delle infografiche cambiava i colori del pannello
  (pulsanti rossi anche con altri colori).
- **Dashboard, "Stai provando il sito ..."** (solo nelle installazioni nuove): le cinque carte dell'installatore e il tipo
  di sito scelto segnato. Un clic su un'altra carta, una conferma ("Il sito diventa Portfolio. Le pagine che hai scritto o
  cambiato restano."), e il sito cambia: prima il pannello fa da solo una copia del sito, poi toglie e rifà solo quello
  che il kit aveva preparato e che nessuno ha toccato (pagine, home, menu, form dei contatti, articoli d'esempio, disegni).
  Il riquadro dice in parole semplici cosa è rimasto com'era. **Definitivo**: il riquadro sparisce e il tipo di sito non
  si cambia più, nemmeno con una richiesta fatta a mano. Un sito che si aggiorna non vede niente.
- **Dashboard, Primi passi** (solo nelle installazioni nuove): sei cose da fare che si spuntano da sole guardando lo stato
  vero del sito: logo, colori, email che partono (con il pulsante "Manda un'email di prova" all'indirizzo
  dell'amministratore), cron attivo, la prima pagina o il primo articolo, il tipo di sito definitivo. Ogni voce porta dove
  si fa la cosa; quando sono tutte fatte, "Tutto fatto: nascondi".
- **Icona del sito di partenza**: una piuma bianca su un quadrato grafite, nella scheda del browser e a `/favicon.ico`
  (prima un'installazione nuova rispondeva 404). L'icona scelta in Stile del sito, Identità, continua a vincere. Vale anche
  per i siti che si aggiornano e non hanno un'icona loro, e con il CMS in una cartella.
- **Riquadro con icona, Riquadro con immagine, Pulsante fisso**: "Posizione dell'icona", "Posizione dell'immagine" e
  "Angolo" si perdevano al salvataggio (avevano lo stesso nome di "Posizione" della scheda Avanzate); ora restano. E
  l'allineamento scelto vale anche per il testo dei riquadri (prima restava sempre centrato). Le pagine fatte con i valori
  di partenza non cambiano di un pixel.
- **Impostazioni, Log cron, riscritta**: cosa fa il cron di plumeCMS (il giro del mattino e quello della newsletter), le due
  righe da mettere nel cron dell'hosting con il percorso vero dell'installazione, quando è andato l'ultimo giro e cosa ha
  fatto, e **Esegui adesso** per chi il cron non l'ha ancora impostato. Via le istruzioni rimaste dal sito di prima
  (prove di connessione e di pubblicazione che in plumeCMS non esistono), e gli altri resti di quel sito nel pannello.
- **Impostazioni, Controlla il server** (anche da Informazioni): con un clic il pannello prova davvero, dal server dove
  gira, PHP ed estensioni, il database, HTTPS dell'indirizzo del sito, il cron, l'invio delle email, il sito degli
  aggiornamenti, le cartelle in cui scrive e le protezioni del `.htaccess` (chiede al sito stesso i file vietati). Verde,
  giallo o rosso, con cosa fare scritto per chi non è tecnico.
- Corretto il testo dell'email di prova di Impostazioni, Configurazione email (mostrava un pezzo di codice).

## 1.3.1 (28 ottobre 2026) - Il sito degli aggiornamenti si sceglie

- **Aggiornamenti, "Indirizzo del sito da cui arrivano gli aggiornamenti"**: il sito a cui il pannello chiede se c'è una
  versione nuova non è più scritto nel codice. Si scrive in Impostazioni, Aggiornamenti (solo indirizzi https); vuoto torna
  a quello di partenza. Chi lo fissa nel `config.local.php` (`CMS_AGGIORNAMENTI_URL`) vince, e la pagina lo dice.

## 1.3.0 (28 ottobre 2026) - Si parte con un sito vero

- **Installazione, "Che sito vuoi fare?"**: all'ultimo passo si sceglie Vuoto (il sito di sempre), Personale, Attività,
  Blog o Portfolio. Il CMS parte con la home, le pagine principali e il menu dell'header già fatti con l'editor, e testi
  d'esempio brevi che dicono cosa scriverci. Dove servono immagini ci sono disegni astratti, da sostituire con le tue foto
  dalla libreria. Funziona anche con il CMS in una cartella. Se non si sceglie niente, resta Vuoto.
- **Editor, linguetta Sezioni**: 30 sezioni già impaginate in nove gruppi (apertura, servizi, chi siamo, numeri,
  testimonianze, prezzi, domande frequenti, contatti, invito all'azione), con un'anteprima piccola. Un clic la mette sotto
  la sezione selezionata, oppure la trascini nel punto che vuoi. Una volta dentro è una sezione normale: cambi testi,
  colori e widget come sempre. Seguono i colori del sito e dei temi, chiari e scuri.
- **Aggiornamenti, "C'è una versione nuova"**: una volta al giorno il pannello chiede al sito di plumeCMS qual è l'ultima
  versione. Se è più nuova, lo dice nella dashboard e in Aggiornamenti; con **Scarica e prepara** lo zip arriva da solo,
  controllato (HTTPS, dimensione e impronta sha256), nella lista dei pacchetti, dove premi Applica ora come sempre. Si spegne
  dalla stessa pagina. Senza rete il pannello funziona come prima.
- **Impostazioni, Copia del sito**: "Prepara la copia" mette in uno zip database, immagini e file caricati, temi e moduli
  (la password del database no); poi la scarichi. "Ripristina" rimette tutto da una copia, caricata o già sul server, dopo
  averne fatta una del sito di adesso. Per i siti con un limite di caricamento basso, la copia si mette nella cartella
  `aggiornamenti/copie/` con il gestore dei file e si ripristina dall'elenco. Da una copia di un altro sito i file con
  codice PHP non entrano.
- Gli ultimi testi d'esempio pensati per il primo sito, nei widget Testimonianza, Carosello di testimonianze, Citazione,
  Titolo animato, Invito libero e Tabella prezzi, ora sono neutri.
- **Per chi fa siti con plumeCMS**: `api/versione.php?serie=<serie>` dice versione, nome, sha256 e dimensione dell'ultimo
  file di una serie del download manager.

## 1.2.8 (27 ottobre 2026) - Software libero, di tutti

- **Licenza**: plumeCMS è software libero con la GNU GPL, versione 3 o successiva. Il testo è nel file `LICENSE` e la
  pagina Informazioni del pannello lo dice.
- **Niente più cose di un sito solo**: plumeCMS nasce dal pannello di danilofailli.it, e se ne portava dietro dei pezzi.
  Ora sono fuori dal prodotto:
  - i moduli ebook e Laser Coaching (motore, pagina Contatti del pannello, pagine di conferma e di download);
  - l'invito finale con le offerte di quel sito nella scheda articolo, e il box del test sul burnout negli articoli;
  - i testi d'esempio dei widget (Apertura, Frase, Schede, Numeri, Invito, Riquadro in evidenza, Listino, Pulsanti):
    ora sono neutri, e i siti che usavano quelli di partenza li vedono cambiare;
  - il nome del mittente delle email di partenza, ora il nome del sito; "Glossario PNL", ora "Glossario"
    (impostazione `glossario_titolo`); il tipo "Coaching" nei dati strutturati dei servizi;
  - gli aforismi come tipo fisso: SEO e widget Griglia elencano da soli i tipi di contenuto dei moduli.
- Per chi scrive moduli: filtri `moduli_disponibili`, `modulo_html`, `pagina_moduli` ed evento `sito_percorso`, per
  portare nel proprio modulo i moduli da compilare e le pagine che il nucleo non ha più (manuale tecnico, 15.4).
- **Correzioni**: in un sito installato nella radice il captcha di newsletter e form chiedeva la sfida a un indirizzo
  sbagliato; nel pannello la scheda articolo non è più ingrandita dal vecchio foglio delle infografiche.
- **Installazione di notte**: l'installatore pubblicava le pagine di partenza "oggi alle 6:00"; chi installava fra mezzanotte
  e le sei trovava il sito vuoto (pagina non trovata) fino al mattino. Ora escono subito.
- **Pubblica vuol dire adesso**: un articolo o una pagina nuova parte con l'ora di adesso, non più con le 6:00 di oggi
  (prima, di notte, "Pubblica" lo programmava per il mattino). Per programmarlo si cambiano data e ora, come sempre.
- Nella pagina Newsletter il percorso del cron è quello vero della tua installazione.

## 1.2.7 (27 ottobre 2026) - Il margine delle aperture

- **Apertura**: Centrata, Copertina, Rivista e Scheda ritrovano il loro margine interno. Nella 1.2.6 una regola del
  foglio del sito lo azzerava: nella Copertina testo e pulsanti toccavano il bordo del riquadro.
- Le altre sette disposizioni non cambiano.

## 1.2.6 (27 ottobre 2026) - Undici aperture

- **Apertura (hero), tendina Disposizione**: oltre alla classica, dieci disposizioni nuove. Centrata, Copertina (foto a
  tutto sfondo), Divisa a metà, Rivista, Scheda (ritratto rotondo), Minimale (solo parole), Mosaico, Inclinata, Banner
  (fascia bassa per le pagine interne), Diagonale. Stessi campi per tutte: cambiando disposizione i testi restano.
- Tutte seguono i colori e i caratteri del sito e dei temi, chiari e scuri, e si leggono al telefono.
- La classica è identica al pixel alla 1.2.5: i siti che ci sono non cambiano.
- Per chi scrive widget: `editor_registra_asset()` accetta più file CSS o JS in un elenco.

## 1.2.5 (27 ottobre 2026) - Sempre l'ultima versione

- **Download, serie**: un file con la versione nel nome (`guida-1.2.pdf`) entra in una serie. `/scarica/guida-ultima/`
  porta sempre alla versione più alta caricata, e nelle pagine `{versione:guida-ultima}` diventa il suo numero (anche nel
  titolo della scheda). Per pubblicare una versione nuova basta caricare il file nuovo: link, pulsanti e titoli la seguono.
- Il widget Download offre le serie ("Sempre l'ultima") e `{versione}` nei titoli; la pagina Download mostra l'indirizzo
  "sempre l'ultima versione" da copiare.
- **Temi con PHP**: il messaggio di Editor > Temi dice i passi esatti; il riquadro di Sicurezza si chiama "Finestra di un'ora
  per i file PHP (pacchetti e temi)" e spiega che serve anche per i temi.
- Il sito di plumeCMS usa le serie: le sue pagine non vanno più ricostruite a ogni versione del CMS.

## 1.2.4 (27 ottobre 2026) - Download manager

- **Pannello, voce Download** (sotto Media, solo amministratore): i file che il sito offre da scaricare. Carichi un file
  con titolo, descrizione e versione; con **Sostituisci** metti la versione nuova allo stesso indirizzo; vedi quante
  volte è stato scaricato e quando l'ultima; copi l'indirizzo con un clic; elimini.
- **Widget Download** (gruppo "Dal sito"): i file scelti, o gli ultimi caricati, con icona del tipo, titolo,
  descrizione, tipo e peso, versione, data, quante volte è stato scaricato e il pulsante. A elenco o a schede.
- Tipi ammessi: pdf, zip, epub, docx, xlsx, pptx, odt, ods, mp3, txt, csv. Il contenuto si controlla: un file con il nome
  sbagliato non entra.
- Il conteggio è delle persone: i robot dei motori di ricerca e i programmi automatici non contano.
- Ogni file si scarica da `/scarica/<nome>/`, come nella 1.2.3.

## 1.2.3 (27 ottobre 2026) - Il sito di plumeCMS e i manuali in PDF

- **Manuali in PDF**: `tools/manuali-pdf.js` stampa il manuale tecnico e quello dei temi, con copertina, indice
  cliccabile e numeri di pagina.
- **File da scaricare**: gli zip e i PDF in `uploads/scaricabili/` si aprono da `/scarica/<nome>/` (il `.htaccess`
  vieta gli zip dal web, quindi passano da PHP).
- **Pacchetti che portano un sito**: voci nuove del manifest `pagine` (pagine dell'editor), `editor` (stile, header,
  footer, menu nel formato di Esporta) e `impostazioni` (nome e descrizioni del sito). Con l'azione
  `sito-pagine-e-stile`, che fa rifiutare il pacchetto ai motori più vecchi.
- **Il sito di plumeCMS**, fatto con plumeCMS: `sito-plumecms/costruisci.py` scrive il pacchetto.
- Widget Menu di navigazione: si può scegliere "quello in posizione: ...", qualunque sia l'id del menu.
- Correzioni:
  - nell'editor il pannello **Revisioni** non si chiudeva mai e toglieva 380 pixel alla tela;
  - lo sfondo **Colore** di una sezione non funzionava con i colori globali;
  - il pannello si presentava come "DF Pannello": ora "plumeCMS".

## 1.2.2 (27 ottobre 2026) - Velo ed effetti sulle foto

- **Apertura (hero)** e **Presentazione**: nella scheda Stile c'è la sezione nuova **Foto**.
  - **Trattamento della foto**: velo colorato sul bianco e nero (com'era), solo bianco e nero, oppure foto naturale.
  - **Colori del velo** (tre nell'Apertura, due nella Presentazione), anche presi dai colori globali, e
    **intensità del velo** da 0 a 100%.
  - **Effetto animato**, cinque a scelta: zoom lento, bagliore che attraversa, cornice luminosa che gira, alone che
    pulsa, scintille che salgono. Ognuno con due colori e la velocità (lenta, normale, veloce). Chi ha chiesto al
    proprio sistema meno movimento vede la foto ferma.
- **Apertura**: interruttore **Mostra l'onda sopra la foto** (scheda Contenuto) per tenere o togliere il riquadro
  con le barre che ondeggiano.
- Le pagine già fatte non cambiano: con i valori di partenza le foto sono identiche al pixel a quelle di prima.
- Un tema può dare i suoi colori al velo e agli effetti con le variabili `--velo-*` e `--fx-*` (`docs/manuale-temi.md`).

## 1.2.1 (27 ottobre 2026) - I due manuali

Solo documentazione: il codice è quello della 1.2.0.

- **Manuale tecnico** (`docs/manuale-tecnico.md`): tutto il CMS pezzo per pezzo, scritto leggendo il codice.
  Installazione, configurazione e costanti, percorso di una richiesta, cartelle e base URL, database e tabelle,
  pannello e ruoli, contenuti, editor (albero, limiti, controlli, schede, i 79 widget, come se ne scrive uno nuovo),
  Stile del sito, header, footer e menu, temi, versioni, media, form, popup, newsletter, posta, SEO e AEO, moduli con
  l'elenco completo di filtri ed eventi, sicurezza, aggiornamenti, cron, `.htaccess` e Nginx, log, strumenti e prove,
  risoluzione dei problemi, riferimento delle funzioni. In appendice, i punti in cui i documenti più vecchi non
  corrispondono più al codice.
- **Manuale dei temi** (`docs/manuale-temi.md`): come si costruisce un tema da zero, file per file, con un tema
  d'esempio completo (Lavagna) provato su un plumeCMS vero.
- Nello zip entrano anche `docs/cms-moduli.md` e `docs/versioni.md`, che prima restavano fuori.

## 1.2.0 (27 ottobre 2026) - I temi

- **Editor > Temi**, pagina nuova (solo amministratore): un tema cambia l'aspetto intero del sito in un colpo solo,
  cioè colori, caratteri, pulsanti, sfondo, header, footer e colori delle infografiche. Pagine, articoli, menu e immagini
  non cambiano. **Anteprima** apre il sito con il tema solo per te; **Attiva** chiede conferma e spiega cosa cambia; lo
  stile di prima resta nelle Versioni e gli header e footer di prima restano in Header e footer.
- Tre temi di partenza: **Cosmo** (l'aspetto di sempre: attivarlo lascia il sito identico), **Aria** (chiaro, un solo
  blu) e **Bottega** (carta crema, titoli con le grazie, terracotta e salvia).
- Un tema può contenere PHP, come in WordPress (filtri, eventi, pagine ridisegnate). Il pannello non lo esegue mai; se
  il PHP del tema sbaglia, il tema si segna come guasto e il sito torna da solo all'aspetto di base.
- **Carica un tema** da zip, **Scarica** un tema, **Scarica l'aspetto di adesso come tema**, **Elimina**. Un tema con PHP
  si carica solo firmato con la chiave del sito o con la finestra di un'ora aperta in Sicurezza. Nella cartella `temi/`
  il CMS scrive da solo un `.htaccess` che dal web non esegue niente.
- Le infografiche prendono i colori del tema attivo; i colori scelti a mano su un'infografica restano. "Temi delle
  infografiche" si chiama ora **Colori delle infografiche**, e Carta "segue il tema del sito".
- Lo Stile del sito arriva dappertutto: i gradienti dei pulsanti, le sfumature, i vetri e il riquadro CTA seguono i
  colori globali (prima restavano viola e ciano). Con i colori di partenza il sito è identico al pixel.
- Correzione: con uno sfondo chiaro nello Stile del sito il testo degli articoli era quasi invisibile; ora segue il
  colore del testo.

## 1.1.9 (27 ottobre 2026) - "Vedi sito" e la cartella che tornava alla radice

- Fino alla 1.1.6 la cartella del CMS (`/test/`) veniva rimandata alla radice del dominio con un 301, cioè un rimando
  permanente: il browser lo ricorda e continua a farlo anche dopo l'aggiornamento, senza più chiedere al server. Il
  rimando che resta (cartella del solo pannello, con il sito nella radice) ora è un 302 con `no-store`, che il
  browser non conserva. Chi ha visto la 1.1.6 deve svuotare una volta la cache del browser.
- "Vedi sito" nel menu del pannello apre l'indirizzo del sito scritto in configurazione, invece della cartella sopra il
  pannello.

## 1.1.8 (27 ottobre 2026) - I file dello zip leggibili dal server web

- Lo zip del prodotto salvava ogni file con permessi 0600. Estratto sul server, le pagine PHP funzionavano (girano con
  l'utente del sito) ma il server web non poteva leggere fogli di stile, script e immagini e rispondeva 403: installatore e
  pannello senza grafica e con i pulsanti fermi. Ora i file escono 0644 e le cartelle 0755.

## 1.1.7 (26 ottobre 2026) - Installare in una cartella

- L'installatore accettava come indirizzo del sito solo il dominio (`https://esempio.it`): installando in una cartella
  (`https://esempio.it/test`) l'indirizzo con la cartella veniva rifiutato. Ora la propone già, la accetta e scrive
  la cartella nel `config.local.php` (`CMS_BASE`), così il CMS non deve indovinarla.
- Gli `.htaccess` dichiarano il tipo di fogli di stile, script, SVG e caratteri: alcuni server li mandano senza tipo
  e il browser, con `nosniff`, li scarta, lasciando il pannello senza grafica.

## 1.1.6 (26 ottobre 2026) - Pagina Aggiornamenti pulita

- Il riquadro "Diagnosi articoli" nella pagina Aggiornamenti compare solo dove c'è il modulo che la contiene: su
  un'installazione nuova non c'era la pagina e il link portava a un errore.

## 1.1.5 (26 ottobre 2026) - Il pannello senza grafica con un indirizzo "sporco"

- Con un pezzo in più dopo il nome del file (per esempio `/admin/index.php/`) il pannello si apriva ma cercava fogli
  di stile e script nel posto sbagliato e appariva senza grafica. Ora ogni pagina del pannello torna da sola
  all'indirizzo pulito, con la stessa query.

## 1.1.4 (26 ottobre 2026) - La pagina SEO e AEO su PHP con short_open_tag

- La pagina SEO e AEO dava errore 500 sui server con `short_open_tag` acceso, che è il valore predefinito di PHP
  (Hostcare compreso). L'esempio del file BingSiteAuth.xml nel segnaposto cominciava con `<?xml` e PHP lo leggeva
  come inizio di codice. Ora l'esempio è scritto con le entità.
- `tools/pacchetto.py` e `tools/cms-pacchetto.py` controllano la sintassi di ogni file PHP con `short_open_tag` acceso
  e rifiutano il pacchetto se c'è un errore.
- Provato su PHP 8.4.27: tutte le pagine del pannello e le pagine pubbliche principali, senza errori.

## 1.1.3 (26 ottobre 2026) - Sicurezza, quarta passata

Le tre basse del terzo attacco:
- Login: utente inesistente e password sbagliata rispondono nello stesso tempo. L'hash finto ha lo stesso algoritmo e lo
  stesso costo degli account veri (argon2id 64 MB, 4 passate, o bcrypt 12 dove argon2id manca), calcolato una volta e
  conservato nell'impostazione `sicurezza_hash_finto`.
- Blocco per nome utente: 20 tentativi sbagliati in 15 minuti, da qualunque IP, bloccano il nome per 15 minuti (poi
  raddoppia), con un'email all'amministratore. Durante il blocco entra lo stesso, con la password giusta, chi arriva da
  un IP che ha gia fatto accessi riusciti con quell'utente e non e mai stato bloccato; resta la riga "Accesso durante un
  blocco (IP di casa)" nel registro. Da un IP estraneo password giusta e sbagliata rispondono uguale. Il blocco per IP
  resta com'era (5 tentativi, poi raddoppia).
- Installatore: la password dell'amministratore passa da `sicurezza_password_errore()` come nel pannello (12 caratteri,
  non comune, diversa da nome ed email, niente sequenze). Le funzioni delle password stanno in
  `includes/sicurezza-password.php`, senza database.
- `docs/sicurezza.md`: le regole dei blocchi, e perche i campi `~b64~` e il controllo dell'origine restano come sono.
- `tools/sicurezza-prova.js`: 11 prove nuove (blocchi per nome utente con IP simulati, tempi del login, installatore).

## 1.1.2 (26 ottobre 2026) - Sicurezza, terza passata

- Ruoli applicati lato server con un elenco chiuso: il redattore gestisce solo articoli, pagine (e i tipi di contenuto dei
  moduli), media, categorie e tag; newsletter, form, popup, menu, header e footer, Stile, temi, widget, impostazioni
  dell'editor e le versioni di stile, modelli e menu sono dell'amministratore. Il menu e la dashboard seguono le stesse regole.
  In `modelli.php` pubblica e predefinito rispettano anche "Chi pubblica".
- Nessun file PHP dei moduli si chiama direttamente dal web (`.htaccess`, Nginx); gli script di manutenzione dei moduli
  chiedono l'amministratore, e quelli che scrivono partono solo con POST e CSRF.
- CSV senza formule: le celle che cominciano con = + - @, tab o a capo prendono un apice (`sicurezza_csv_cella`), in tutti
  gli export del nucleo e del modulo.
- Login e password dimenticata non mostrano piu dettagli del database a video (vanno nel log).
- CSP del pannello solo a nonce, senza hash ricavati dall'output; le installazioni nuove partono con la CSP applicata su
  sito e pannello.
- Test del burnout (modulo): il report parte solo per un test fatto nella stessa sessione, una volta, con limiti per IP e per email.
- `tools/sicurezza-prova.js`: giro di tutti i file PHP del pannello, delle API e dei moduli da anonimo e da redattore.

## 1.1.1 (26 ottobre 2026) - Sicurezza, seconda passata

Correzioni trovate dal collaudo di attacco sul prodotto pulito:
- Newsletter: la firma dei link copre anche l'indirizzo di destinazione (niente rimando aperto da `api/nl.php`); i link
  gia spediti portano solo dentro il sito.
- Cron: `cron-daily.php` (come `cron-newsletter.php`) gira solo da riga di comando, o via web con `CMS_CRON_TOKEN`;
  negato anche dal `.htaccess` e dalla regola Nginx.
- Immagini caricate sempre nella cartella del CMS (`cms_uploads_dir()`/`cms_uploads_url()`): sul prodotto pulito finivano
  fuori dalla radice web e la protezione era sulla cartella sbagliata. Ricodifica anche per le immagini della newsletter.
- Ritorni `torna` del pannello solo interni.
- Pannello senza gestori in linea (attributi `data-*` e `admin/assets/pannello.js`): la CSP non ripiega piu su
  `'unsafe-inline'`, salvo tre pagine vecchie del modulo dichiarate con il filtro `csp_pannello_inline`.
- Chiamate in uscita senza curl rifiutate (niente DNS rebinding).
- Pacchetti: file eseguibili nella radice (`root/`) solo con firma o nella finestra di un'ora aperta con la password;
  `tools/pacchetto.py --rifirma`.
- Password dimenticata: limite per email e per IP a parte dal login (nessuno chiude fuori il proprietario).

## 1.1.0 (26 ottobre 2026) - Sicurezza

- **Autenticazione**: hash argon2id (o bcrypt costo 12) con migrazione trasparente al primo login; requisiti della
  password (12 caratteri, diversa dal nome/email, non tra le più comuni); limite ai tentativi di login per IP e per
  utente con blocco crescente e avviso email; registro degli accessi (riusciti, falliti, blocchi) con IP e browser.
- **Secondo fattore TOTP** fatto in casa (QR in SVG generato dal server, otto codici di recupero, obbligatorio
  opzionale per gli amministratori). Nasce spento, si attiva da "Il mio accesso".
- **Sessione**: id rigenerato al login (niente session fixation), cookie HttpOnly/Secure/SameSite, scadenza assoluta
  e per inattività, "esci da tutti i dispositivi", il cambio password chiude le altre sessioni.
- **Password dimenticata** con token firmato monouso via email (30 minuti, senza rivelare se l'utente esiste).
- **Lista IP consentiti** per il pannello (opzionale, nasce spenta).
- **Intestazioni di sicurezza** su ogni risposta (nosniff, Referrer-Policy, X-Frame-Options, Permissions-Policy,
  niente X-Powered-By) e **Content-Security-Policy a nonce** sul sito e sul pannello, con modalità "solo rapporto"
  (endpoint api/csp.php) prima di "applica"; le sorgenti dei tag di analisi entrano solo se attivi nella voce SEO;
  HSTS su https e COOP.
- **Contenuti dell'editor**: sanificatore del testo ricco su parser DOM (niente script, on*, javascript:, data: nei
  link); widget HTML e codice head/body solo amministratore (per i ruoli inferiori diventano testo pulito); CSS
  personalizzato confinato a .ed-ID, senza import/expression/behavior/url() esterni; Attributi su lista stretta.
- **Caricamenti**: controllo del tipo reale e nuova codifica delle immagini; SVG sanificato e servito in sandbox;
  nomi con suffisso casuale.
- **Pacchetti di aggiornamento**: controllo zip slip, dimensione massima, firma HMAC opzionale (tools/pacchetto.py
  --firma); applicazione registrata nel log di sicurezza.
- **Chiamate in uscita** con allowlist di host e blocco degli indirizzi privati (niente SSRF); redirect solo interni;
  LIKE con escape dei jolly nella ricerca; cron-log svuotato solo in POST.
- **Pagina Sicurezza** nel pannello (gruppo Impostazioni): checklist dello stato, impostazioni, registro con filtri,
  sessioni per utente, rapporti CSP. Prove di attacco in tools/sicurezza-prova.js. Documento: docs/sicurezza.md.
- **.htaccess del CMS rifatto**: nega includes, logs, aggiornamenti, sql, docs, tools, test, VERSIONE, *.md; PHP
  spento in uploads/ con SVG in sandbox; esempi Apache e Nginx aggiornati.

## 1.0.0 (26 ottobre 2026)

- Prima versione del CMS come prodotto: nucleo generico (articoli, pagine, editor a sezioni e widget, header e footer come modelli, menu, Stile del sito, SEO e AEO, newsletter con flussi, form, popup, utenti, pacchetti di aggiornamento) separato dal modulo del sito di partenza.
- Installatore da browser in install/: requisiti, database con prefisso a scelta, dati del sito e amministratore, contenuti di partenza.
- Moduli in moduli/<nome>/modulo.php: tipi di contenuto, voci di menu, pagine del pannello, tabelle, passi del cron, widget, filtri ed eventi.
- Il CMS gira nella radice del dominio o in una sottocartella (cms_base()), con le immagini in uploads/.
- Pagina Informazioni nel pannello: versione, moduli, stato del server, tabelle, widget.
- Pacchetto del prodotto e pacchetto di aggiornamento da tools/cms-pacchetto.py.
