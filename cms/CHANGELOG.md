# Novità

Il prodotto si chiama **plumeCMS** (nome scelto il 26 ottobre 2026; prima, per poche ore, "CarbonaraCMS", e ancora prima il provvisorio "Officina": gli zip vecchi si chiamavano `officina-*` e `carbonaracms-*`).

## 2.37.1 (9 novembre 2026) - Le pagine per zona rimaste fuori dal registro

- **Tornano nel registro le pagine per zona perse con la 2.35.0.** Fino alla 2.35.0 il registro delle pagine per zona si fermava a
  200 voci, e aprire SEO e AEO > Zone lo riscriveva a 200: le pagine oltre restavano nel sito, anche pubblicate, ma senza i dati per
  Google (il servizio e la zona servita), senza briciole e fuori dal widget Altre zone. Il caso vero: un laboratorio con 7 servizi su
  circa 40 zone, 262 pagine pubblicate, 62 senza dati. La 2.36.0 ha tolto il tetto, ma quelle pagine non erano più nel registro
  e non sono tornate da sole.
- **Alla prima apertura di Zone** dopo l'aggiornamento il pannello le cerca: le riconosce dal widget Altre zone e dall'apertura con
  cui sono nate (servizio e zona si leggono da lì, la sede dal widget Dati dell'attività o Barra contatti) e mostra un riquadro:
  "62 pagine per zona sono fuori dal registro", le prime 20 per nome, **Rimettile nel registro** oppure **Lasciale fuori**. Le
  pagine non cambiano: tornano nell'elenco, nei dati per Google, nelle briciole e nel widget Altre zone. Se non ce ne sono, non si
  vede niente.
- **I doppioni restano fuori**: una pagina con lo stesso servizio e la stessa zona di una già nel registro (la 2.35.0, non trovando
  più la prima, poteva crearne un'altra) si dice per nome, da cestinare a mano.
- **Cerca le pagine per zona fuori dal registro**, in fondo all'elenco di Zone, rifà la ricerca quando vuoi (per esempio dopo un
  Togli dal registro di cui ti sei pentito).
- Prove: `prove/prova-zone.js` 90 su 90 (9 controlli nuovi, con 62 voci tolte dal registro come faceva la 2.35.0 su un registro di
  450); ricerca in 46 ms con 5.000 pagine per zona nel registro, 4.801 pagine fuori ritrovate e rimesse in 4 secondi.

## 2.37.0 (9 novembre 2026) - Cambia database

- **Impostazioni > Cambia database** (richiesta di Danilo: "migrare tutto il db su un altro DB e a fine copia utilizzare il
  nuovo DB"): sposta tutto il database del sito su un altro database, dello stesso hosting o di un altro server MySQL o
  MariaDB, e a copia controllata fa usare al sito quello nuovo. Solo per l'amministratore, con la sua password per partire.
- **Prova la connessione** prima di tutto: tempo massimo breve, versione del server, permessi provati con una tabella e una
  vista create e tolte, tabelle già presenti con il prefisso scelto (allora serve la spunta "Sostituisci le tabelle con
  questo prefisso nel database nuovo", e si sostituiscono solo quelle). Lo stesso database di adesso con lo stesso prefisso si
  rifiuta, anche scritto con un altro indirizzo del server (lo dice il server stesso). Errori in parole semplici, senza il
  messaggio del server; al più dieci prove ogni dieci minuti per sessione.
- **La copia a giri** con la barra di avanzamento, con le difese del ripristino sicuro della 2.25.1: tabelle di appoggio nel
  database nuovo e una sola RENAME alla fine, collazioni e set di caratteri adattati al server di arrivo (MySQL 8 verso
  MariaDB 10.6 e MySQL 5.7 provati), colonne generate scritte DEFAULT, date di creazione conservate, una istruzione alla volta,
  INSERT sotto il limite del server. Durante la copia il sito dice "Torno subito" (503 con Retry-After) e il pannello mostra
  solo la pagina del cambio. Se la pagina si chiude, riaprendola la copia riprende; dopo dieci minuti il sito torna normale da
  solo sul database di prima. Alla fine le righe si contano tabella per tabella; una tabella cambiata durante la copia si
  ricopia, e se i conti non tornano ci si ferma senza cambiare niente.
- **Il passaggio**: una copia del config di prima non leggibile dal web (`includes/config.local.prima-<data>.php`, comincia con
  un'uscita immediata), il config nuovo con lo stesso formato dell'installatore (cambiano solo le righe del database e del
  prefisso), scritto con un file temporaneo e una rinomina, poi una prova sul database nuovo; se la prova non va torna il
  config di prima. Se il config non si può scrivere, la pagina mostra all'amministratore le cinque righe da incollare con il
  gestore dei file e "Ho cambiato il file, controlla".
- **Il database di prima non si cancella mai**: la pagina lo dice, e **Torna al database di prima** rimette il config salvato
  (con l'avviso che le modifiche fatte dopo il passaggio stanno solo nel database nuovo).
- Prove: `prove/prova-cambia-db.js` (con `prove/cambia-db-cli.php`), gruppo 50 degli attacchi.

## 2.36.0 (9 novembre 2026) - Pagine per zona senza limite

- **Niente più tetto di 200 pagine per zona.** Richiesta di Danilo: "ci sono siti che lo superano di tanto". Il registro delle
  pagine per zona passa dalla configurazione SEO (un JSON letto a ogni pagina) a una tabella sua, `<prefisso>zone`, che nasce al
  primo uso. Il sito legge solo la zona che gli serve; un sito senza pagine per zona non fa nessuna query in più e non crea la
  tabella (provato: stesse query della 2.35.0 su home, Servizi, Contatti e sitemap).
- **Il registro di prima passa da solo nella tabella**, una volta, alla prima pagina aperta dopo l'aggiornamento: pagine per zona,
  dati per Google, widget Altre zone, sitemap e llms.txt restano identici al byte. Se la copia non riesce, niente si perde: il
  registro di prima resta nella configurazione, il pannello Zone lo dice e la copia si riprova.
- **Fino a 100 zone per giro** (erano 30). Su un hosting lento il giro si fa a pezzi di 15 secondi e la pagina continua da sola
  ("Sto creando le bozze a pezzi").
- **L'elenco di Zone a pagine** da 50, con la **ricerca** per zona, servizio o titolo oltre alla tendina dei servizi, e i numeri in
  cima (in tutto, pubblicate, bozze con riquadri da scrivere) contati dal database. "Togli dal registro" torna alla stessa pagina.
- **Widget Altre zone: Quante mostrarne** (0 = tutte, come prima: le pagine già fatte non cambiano) e **Quali mostrare** (le prime in
  ordine alfabetico, oppure le più vicine a questa zona nell'elenco, metà prima e metà dopo, così ogni pagina ne mostra di diverse).
  Quando un servizio ha più di 30 pagine pubblicate e il widget le mostra tutte, il pannello Zone consiglia di metterne fra 12 e 20.
- **llms.txt con migliaia di zone**: oltre 50 pagine per zona, una riga per servizio con dieci esempi e il rimando alla sitemap
  invece di una riga per pagina (con 5.000 zone: 1,4 KB invece di 610 KB). Sotto le 50 il file non cambia.
- Corretto: il widget Altre zone leggeva la home dal database una volta per link (con 5.000 zone, 5.099 query per pagina; ora 101).
- Prova di carico con 5.000 pagine per zona: una pagina normale 103-123 ms, una pagina per zona con 15 zone vicine 125 ms, sitemap
  86 ms, pannello Zone 200-250 ms (manuale tecnico 45.8).

## 2.35.0 (8 novembre 2026) - Più chiaro per Google e per le AI (audit SEO e AEO di plumecms.it)

- **llms.txt calcolato con le pagine.** Il file per le intelligenze artificiali elencava solo gli articoli, scriveva le
  intestazioni anche vuote ("Guide complete (pillar)" senza voci) e un link al Glossario con "0 termini" verso una pagina che non
  c'era. Ora ha la sezione **Pagine** con le pagine pubblicate e indicizzabili (la home per prima, con il riquadro In breve o la
  descrizione per Google accanto; mai le bozze né quelle nascoste a Google), non scrive le sezioni vuote, nomina il Glossario
  solo se ha termini e il feed solo se ci sono articoli. In testa la descrizione generale del sito di SEO e AEO, se c'è,
  altrimenti quella di prima. Il llms.txt scritto a mano nel pannello vince sempre.
- **Dati per Google "Programma"** (SoftwareApplication) nel riquadro Dati per Google in più della scheda di pagine e articoli:
  nome, versione (anche `{versione:<serie>}` del Download, che il sito sostituisce con l'ultima), categoria, dove funziona,
  prezzo (0 se gratuito) e valuta, licenza, indirizzo per scaricarlo, cosa serve. Autore ed editore sono l'Organizzazione di SEO
  e AEO. Niente valutazioni a stelle: Google le vuole solo vere. Senza il tipo scelto la pagina esce identica.
- **La home porta i dati per Google in più della sua scheda.** Dalla 2.29.0 Prodotto, How-to e Video scelti nella scheda della
  pagina che fa da home non uscivano nel JSON-LD (la home ha una strada sua nel sito): ora escono, anche il Programma.
- **La parola chiave anche per le pagine** (prima solo gli articoli): campo nella scheda, colonna `keyword_target` di pages
  aggiunta da sola, letta dal controllo SEO e dalla Tabella SEO.
- **I pacchetti portano i campi SEO delle pagine**: In breve (`tldr`), domande frequenti della scheda (`faq`), parola chiave
  (`keyword`) e dati per Google in più (`seo_schema`, controllati come quelli della scheda: un valore sbagliato ferma il
  pacchetto e il sito torna com'era). Senza queste chiavi una pagina si importa come prima.
- **La voce "seo" del manifest**: un pacchetto scrive solo le impostazioni ammesse di SEO e AEO (nome del sito nei titoli,
  descrizione generale, Organizzazione, Persona con nome e ruolo, interruttori dei dati strutturati), controllate. Le
  verifiche, le chiavi e i codici di statistica un pacchetto non li tocca. La conosce solo il motore dalla 2.35.0: il
  pacchetto del sito si applica dopo l'aggiornamento del CMS (`tools/consegna.py` fa due pacchetti).
- **Il sito di plumeCMS**: titoli e descrizioni per Google nuovi su sette pagine, In breve e parola chiave su tutte, "In breve"
  sotto l'apertura della home, cinque domande nuove (gratis, differenza con WordPress, ChatGPT e Claude, Plume CMS, chi c'è
  dietro) e la risposta sull'hosting con i requisiti veri, la pagina nuova **plumeCMS e WordPress a confronto**
  (`/plumecms-wordpress/`), "Leggi anche" in fondo a Temi, Documentazione, Scarica e Domande frequenti, Domande frequenti nel
  menu principale, i dati Programma nella home e How-to con i cinque passi nell'Installazione; SEO e AEO dal pacchetto (nome
  "plumeCMS" nei titoli, descrizione, Organizzazione con il marchio, Persona Danilo Failli).

## 2.34.0 (8 novembre 2026) - AI collegate: lo stile del sito e gli articoli nuovi

- **Lo stile del sito** (richiesta di Danilo: "la creazione degli articoli nuovi dovrà seguire lo stile di quelli già scritti").
  Lo strumento nuovo `analyze_style` misura gli articoli pubblicati (gli ultimi 50 al massimo): lunghezza media e mediana,
  frase e paragrafo medi, sottotitoli, elenchi, domande a chi legge, se dai del tu, del voi, parli con il noi o in modo
  impersonale, le parole e le espressioni che ripeti, come apri e come chiudi, grassetti, emoji, link interni, immagini,
  domande frequenti, categorie e tag, titoli e descrizioni per Google, la struttura tipica dei widget, e da tre a cinque
  estratti rappresentativi. Tutto contato dal sito con regole dichiarate: il sito non usa un'intelligenza artificiale sua.
- **Il profilo di stile**: l'AI lo scrive in parole (tono, persona, lessico da usare ed evitare, struttura, regole, esempi) con
  `save_style_profile` e lo rilegge con `get_style_profile` prima di scrivere. Uno per sito, con le ultime dieci versioni.
  Nella pagina nuova **AI collegate > Stile del sito** lo leggi, lo correggi a mano, vedi le misure scritte in parole e
  rimetti in uso una versione di prima con **Ripristina**.
- **Le bozze confrontate con lo stile**: `create_draft` e `update_draft` rispondono all'AI dove la bozza si allontana dal
  profilo (troppo lunga o corta, frasi lunghe, un'altra persona, niente sottotitoli, niente link interni, niente domande
  frequenti). Sono avvisi: la bozza si salva comunque.
- **Idee per articoli nuovi.** `suggest_topics` trova dagli argomenti del sito quello che manca: temi citati in più articoli
  senza un articolo loro, tag con un solo articolo, categorie povere, domande dei lettori che meritano un articolo, e (con
  l'addon Statistiche) gli articoli molto letti che nessun altro collega; ogni spunto con il perché, gli articoli da cui nasce
  e i link da mettere. Con `propose_article_ideas` l'AI propone fino a venti idee (titolo, parola chiave, a chi serve,
  scaletta, articoli da collegare, motivo), tutte o nessuna.
- **La pagina Idee per articoli**: per ogni idea **Approva l'idea** (diventa "da scrivere"), **Scarta** o **Modifica** (anche una
  nota per l'AI e la data prevista). Il **piano editoriale** mette le idee approvate in fila per mese, con **Sposta prima** e
  **Sposta dopo**. La data è solo un promemoria: niente si pubblica da solo, niente email nuove.
- **Dall'idea alla bozza**: `list_article_ideas` dà all'AI le idee approvate nell'ordine del piano; `create_draft` con
  `idea_id` scrive la bozza e lega l'idea, che diventa "scritta (in bozza)" con il link. Solo le idee approvate, una bozza per
  idea. L'AI non pubblica mai.
- Permessi: misure, spunti, profilo e idee si leggono con "leggi"; il profilo si salva con "bozze"; le idee si propongono con
  "proposte". Nessun permesso nuovo: i collegamenti che ci sono già funzionano.
- Sicurezza: profilo e idee solo testo (via anche il contenuto di `<script>` e `<style>`), lunghezze tagliate, collegati solo
  verso contenuti pubblicati di questo sito, `idea_id` solo numeri e solo in stato "da scrivere", azioni del pannello solo
  dall'amministratore con il token. Gruppo 48 delle prove di attacco (15).
- Prove: `prove/prova-ai-stile.js` (53 controlli, anche con 300 articoli).

## 2.33.0 (8 novembre 2026) - AI collegate: navigazione e schede degli articoli

- **La navigazione rivista dall'AI** (richiesta di Danilo: "analizzi tutte le pagine del sito, richiamate dai vari menu, e
  standardizzi la navigazione"). Lo strumento nuovo `analyze_navigation` legge tutti i menu, l'header e il footer, e dice dove
  porta ogni voce: voci verso pagine in bozza, nel cestino o che non esistono, voci doppie, etichette troppo lunghe o con le
  maiuscole miste, la stessa pagina con nomi diversi nei menu, voci al terzo livello, menu con più di sette voci, pagine
  pubblicate che nessun menu raggiunge (orfane) o che stanno solo nel piede, la voce Contatti, la voce del blog, le briciole
  di pane, le pagine dei menu senza un invito all'azione in fondo o senza link verso le altre. Con un punteggio e i consigli.
- **Il menu proposto, accanto a quello di adesso.** Con `propose_menu` l'AI manda il menu nuovo intero: in **AI collegate >
  Proposte** vedi i due menu affiancati (al telefono uno sotto l'altro), con le voci **nuove**, **tolte**, **spostate** e con
  il **nome nuovo** scritte accanto, e il motivo. **Approva il menu** lo salva (il menu di prima resta nelle versioni); se nel
  frattempo qualcuno ha cambiato il menu, l'approvazione si ferma e non sovrascrive niente. Una proposta per menu alla volta.
  Le voci portano solo a pagine pubblicate o a indirizzi sicuri; al massimo 200 voci e tre livelli.
- **Leggi anche e invito in fondo alle pagine**: con `propose_navigation_fix` l'AI propone di aggiungere in fondo a una pagina
  un blocco "Leggi anche" e un invito verso Contatti. Su una pagina pubblicata è una proposta da approvare; su una bozza va
  subito nella bozza. Le briciole di pane non hanno un'impostazione da accendere: l'AI lo dice e non propone niente.
- **Le schede degli articoli.** `list_articles_to_review` mette in fila gli articoli che hanno più bisogno di una revisione;
  `review_article` li legge uno per uno: titolo e descrizione per Google, se le prime frasi rispondono alla domanda, i
  sottotitoli in ordine, i paragrafi lunghi, la parola chiave, le domande frequenti, i link interni da aggiungere, le
  immagini senza alt, le frasi che invecchiano ("quest'anno", anni passati, "recentemente") con il punto preciso.
  `propose_article_changes` lascia fino a venti proposte insieme: in **Proposte** stanno sotto "Scheda dell'articolo ..." con
  **Approva tutte** e **Scarta tutte**, oppure si decidono una per una. O si salvano tutte, o nessuna.
- Regola che resta: l'AI non pubblica mai; menu, header, footer e contenuti pubblicati non li cambia mai da sola.
- Sicurezza: etichette solo testo, indirizzi controllati (mai `javascript:`, `data:`, `//` verso altri siti), contenuti
  verificati, approvazioni solo dall'amministratore con il token del pannello. Gruppo 47 delle prove di attacco (15).
- Prove: `prove/prova-ai-navigazione.js` (52 controlli, anche con 300 pagine e cinque menu).
- **Correzioni** (segnalate da Danilo). Il **robots.txt** predefinito chiudeva con due righe `Sitemap:`: identiche con il CMS
  nella radice, e la seconda sbagliata con il CMS in una cartella (la cartella due volte). Ora una riga sola, all'indirizzo del
  sito (`sito/extra.php`). I **vecchi indirizzi `/articoli/<slug>/`** tornano all'articolo con un 301: il passo era sparito
  dalla 1.2.8, e con una regola dei redirect che comincia con `/articoli/` (per esempio importata, verso il blog) ogni vecchio
  indirizzo finiva sul blog invece che sull'articolo. Il controllo viene prima dei redirect e vale solo per gli articoli
  pubblicati; per il resto la regola vale come prima (`sito/index.php`). Prova `prove/prova-robots-articoli.js` (14 controlli,
  radice e cartella, con e senza la regola).

## 2.32.0 (8 novembre 2026) - AI collegate

- **Addon AI collegate** (richiesta di Danilo: un connettore fra il CMS e qualsiasi AI). Si accende da **Impostazioni >
  Addon** e collega la tua AI (Claude, ChatGPT, le altre che parlano MCP) al sito con un indirizzo da incollare
  (`https://tuosito.it/api/mcp`, anche con il sito in una cartella). La prima volta la tua AI apre il pannello: entri (anche
  con il secondo fattore), vedi chi chiede e dove torna, scegli i permessi a caselle e premi **Consenti**. Solo
  l'amministratore può dare il permesso; si revoca quando vuoi da **AI collegate > Collegamenti**.
- **L'AI non pubblica mai.** Scrive bozze complete (testo, widget veri dell'editor, campi SEO, foto) che pubblichi tu dalla
  scheda o dall'editor; non esiste un'opzione per farla pubblicare. Quello che tocca un contenuto già online (titolo, titolo e
  descrizione per Google, testo di un widget, domande frequenti, immagine in evidenza, alt della libreria) diventa una
  **proposta**: in **AI collegate > Proposte** vedi prima e dopo e il motivo, e scegli **Approva**, **Modifica e approva**,
  **Scarta** o **Approva le selezionate**. Il numero delle proposte da decidere esce nel menu.
- **Cosa sa fare**: leggere contenuti, menu e impostazioni SEO; il catalogo dei widget con i campi; l'**audit SEO** di tutto il
  sito o di un contenuto con il motore di plumeCMS (titoli e descrizioni, doppioni, immagini senza alt, link interni rotti o
  verso bozze, contenuti scarni, pagine fuori dai menu, immagini pesanti, senza immagine per i social), ordinato per gravità
  con un suggerimento; cercare e importare **foto con licenza libera anche per uso commerciale** (un archivio aperto senza
  chiave; altri due con la loro chiave gratuita, da scrivere in **AI collegate > Impostazioni**), con autore e licenza
  salvati; le statistiche delle visite, se l'addon Statistiche è acceso.
- **Permessi** a scelta: leggere, bozze, proposte, foto, statistiche. Mai utenti, password, aggiornamenti, copie, codice, temi,
  sicurezza. **Chiavi personali** (mostrate una volta, revocabili) per le AI che non fanno il collegamento con il permesso, e
  lo schema **OpenAPI** con gli stessi strumenti come chiamate REST. **Registro** di ogni azione, con il filtro.
- Sicurezza: autorizzazione OAuth 2.1 con PKCE obbligatorio, token di un'ora e rinnovo di trenta giorni che cambia a ogni uso
  (salvati solo come impronta legata al sito), revoca, limiti di velocità, testi dell'AI passati dagli stessi filtri del
  redattore, foto scaricate solo dagli archivi in elenco e controllate come un caricamento. Gruppo 46 delle prove di attacco.
- Nel nucleo, tre ritocchi che con l'addon spento non cambiano niente: il login torna al consenso dopo l'accesso
  (`admin/login.php`), una voce di menu di un addon può mostrare un numero (`includes/cms.php`), `media_carica()` accetta un
  file scaricato dal server (`includes/media.php`). Il `.htaccess` d'esempio passa l'intestazione Authorization a PHP e quello
  di Nginx manda `/api/mcp` a `index.php`.

## 2.31.0 (6 novembre 2026) - Avvisi senza il pannello in vista

- **Il pulsante "Apri nel pannello" non arriva più al cliente** (segnalazione di Danilo dal sito di Cucina Creativa: rispondendo a
  una richiesta di prenotazione, il programma di posta citava l'avviso intero e il cliente riceveva il pulsante e, sotto,
  l'indirizzo del pannello in chiaro). Gli avvisi che hanno come risposta il visitatore (form, Richiesta di prenotazione, addon
  Prenotazioni, Vetrina, Iscrizioni agli eventi) escono senza pulsante e senza indirizzo del pannello, né nell'HTML né nel testo
  semplice. Vale anche per i siti aggiornati: chi vuole il pulsante lo riaccende in **Impostazioni > Configurazione email**,
  riquadro **Gli avvisi che ricevi**, con l'anteprima di un avviso d'esempio e **Manda un avviso di prova**. Gli avvisi solo per
  te (una recensione da approvare, una copia del sito non partita) tengono il pulsante. `moduli_avviso_pannello()` e
  `moduli_email_avviso()` in `includes/moduli.php`, impostazione `avvisi_pulsante_pannello`.

## 2.30.0 (6 novembre 2026) - Infografiche grandi quanto vuoi

- **Larghezza dell'infografica** (richiesta di Danilo: "le infografiche sono minuscole, devo poterle ingrandire quanto voglio,
  altrimenti si perdono nella pagina; vale per tutte"). Tutte e trenta le infografiche hanno come primo campo della scheda Stile la
  larghezza, in percentuale, pixel o larghezza dello schermo, diversa per computer, tablet e telefono: 100% riempie la colonna anche
  quando la colonna centra i widget e li stringeva. Con una larghezza scritta cadono i tetti di prima (700 px la scheda, 600 px il
  disegno) e il disegno cresce con la scheda; mai oltre la colonna. Senza larghezza le pagine restano identiche.
  `igx_controlli_testa()` e `igx_larghezza_libera()` in `includes/editor-widget.php`, blocco 2.30.0 di `sito.css`.

## 2.29.1 (6 novembre 2026) - Installazione senza iconv

- **L'installazione si fermava su alcuni hosting** con "Call to undefined function iconv()" subito dopo aver creato
  l'amministratore (segnalazione di Danilo, installazione su Hostcare: l'estensione PHP iconv non c'è). Quattro punti la usavano
  senza controllare che esistesse: gli indirizzi delle pagine (`contenuti_slug()`), il nome dei campi e l'indirizzo dei form, un
  widget. Ora passano tutti da `cms_ascii()` (`includes/cms.php`): iconv se c'è, altrimenti intl, altrimenti una tabella delle
  lettere accentate. Con iconv presente il risultato è lo stesso di prima.

## 2.29.0 (5 novembre 2026) - Gli strumenti SEO

- **La Tabella SEO** (SEO e AEO > Tabella SEO, anche per il redattore): tutto il sito in una tabella, una riga per ogni
  contenuto (articoli, pagine e i tipi dei moduli, bozze comprese) con il titolo, il titolo per Google, la descrizione, le
  lunghezze, robots, un semaforo per il titolo (verde da 30 a 60 caratteri; giallo fuori; rosso se manca, oltre 70 o uguale a
  quello di un altro contenuto) e uno per la descrizione (verde da 70 a 160; giallo fuori; rosso se manca o doppia), le
  immagini senza alt. Titolo per Google e descrizione si correggono nella riga e si salvano senza ricaricare (passano dalla
  stessa strada della scheda: revisione, redirect, IndexNow; l'albero dell'editor non si tocca); un doppione risolto aggiorna
  anche l'altra riga. Filtri: solo i contenuti con problemi, per tipo, ricerca; ordine per semaforo, titolo o tipo. Al telefono la
  tabella diventa schede. `includes/seo-tabella.php`, `admin/seo-tabella.php`, `admin/assets/seo-tabella.js`, azione
  `tabella_salva` di `seo-api.php`.
- **I link interni suggeriti**: nel controllo SEO (Impostazioni dell'editor e scheda classica) il riquadro **Collega**: dalla
  parola chiave del contenuto e dalle parole del titolo cerca fra i contenuti pubblicati (titolo, parola chiave, riassunto,
  indirizzo; fuori i riservati "Solo per i membri", le bozze, la home e il blog) e propone fino a 5 pagine o articoli con
  **Copia il link** (gli appunti) e, nell'editor, **Inserisci nel Testo selezionato** (un paragrafo con il link in fondo al
  widget Testo selezionato). La riga dell'analisi dice "Collegamenti interni: N nel testo; 3 pagine parlano di X, collegale" e
  diventa verde quando almeno una delle suggerite è collegata; le già collegate non si ripropongono. `includes/seo-link.php`
  (`seo_link_suggeriti()`, indice leggero per richiesta, niente tabelle).
- **Dati per Google in più: Prodotto, How-to, Video.** Nel riquadro "SEO e Google" della scheda di un articolo o di una pagina la
  tendina **Dati per Google in più**: Nessuno, Prodotto (nome, prezzo, valuta, disponibilità, codice, marca, foto), How-to (nome,
  tempo totale, fino a venti passi con nome e testo), Video (nome, descrizione, miniatura, data di caricamento, indirizzo del file
  o dell'incorporo solo https, durata). I campi stanno nella colonna `seo_schema` (JSON, aggiunta anche alle tabelle vecchie),
  controllati (numeri, date, indirizzi, nessun HTML), e il sito li scrive come nodi del JSON-LD (Product con Offer, HowTo con
  HowToStep, VideoObject) con la WebPage che li indica in `mainEntity`. Un contenuto senza niente scelto esce identico a prima,
  al byte. La scheda di un prodotto della Vetrina aveva già il suo Product con Offer. `includes/seo-schema.php`,
  `admin/assets/seo-schema.js`.
- **Le immagini senza alt** (Media > Senza alt, anche per il redattore): due elenchi, **Nelle pagine** (le immagini dei contenuti
  pubblicati con l'alt vuoto nel widget Immagine, Galleria, Carosello, Slider, Scorri di lato, loghi..., nel testo della scheda
  e l'immagine in evidenza, con il link all'editor) e **Nella libreria** (le immagini dei Media senza un alt loro), con una casella
  per riga salvata senza ricaricare. L'alt scritto nella libreria entra in ogni pagina che usa quella foto con l'alt vuoto
  (tabella `media_alt`, nata al primo uso; senza alt salvati il sito esce identico a prima). La Tabella SEO conta le immagini senza
  alt con la stessa funzione. `includes/media-alt.php`, `admin/includes/media-alt-pagina.php`, `admin/assets/media-alt.js`,
  azione `media_alt_salva` di `seo-api.php`.
- Manuale d'uso capitoli 7 e 23, tecnico capitolo 46, `prove/prova-seo-strumenti.js` (71), gruppo 44 degli attacchi (13).

## 2.28.0 (5 novembre 2026) - Le pagine per zona

- **Le pagine per zona** (SEO e AEO > Zone, solo amministratore; richiesta di Danilo: "è dove la SEO locale si vince"). Si scrive il
  servizio ("Idraulico"), le zone una per riga (già scritte dall'unione delle zone servite delle sedi, al massimo 30 per giro e 200
  pagine in tutto), la sede di riferimento e, se si vuole, la pagina del servizio; **Crea le bozze** fa nascere una pagina vera
  dell'editor per ogni zona, "Idraulico a Casalecchio di Reno" (`/idraulico-a-casalecchio-di-reno/`, titolo e descrizione per
  Google da due modelli con `%servizio%` e `%zona%`), **sempre in bozza**, mai pubblicata dal generatore. Una coppia servizio +
  zona che c'è già non si rifà. Il registro sta nel gruppo `zone` della configurazione SEO: niente tabelle nuove
  (`includes/zone.php`, `admin/zone.php`).
- **Le bozze guidate**: ogni pagina nasce con l'apertura (occhiello = la zona, titolo "Idraulico a *Casalecchio di Reno*", testo
  dalla descrizione, pulsante Contattaci se la pagina Contatti c'è), tre riquadri Testo con un segnaposto `[DA SCRIVERE]` ciascuno
  e una riga di aiuto (cosa fai in quella zona, un caso vero, come si arriva), il widget Altre zone, Dati dell'attività e la Barra
  contatti letti dalla sede. Il blocco guidato esiste per impedire pagine vuote tutte uguali, che Google ignora.
- **La pubblicazione bloccata finché i segnaposto ci sono**: una pagina per zona con un `[DA SCRIVERE]` nel testo non si pubblica da
  nessuna strada (Pubblica dell'editor, scheda del contenuto, azione in blocco, modifica rapida, Impostazioni dell'editor,
  ripristino di una versione), nemmeno dall'amministratore: "Questa pagina per zona ha ancora N riquadri da scrivere: compilali,
  poi pubblica", e resta bozza. Salvare la bozza va sempre bene. Il redattore non crea le bozze (solo l'amministratore) ma le
  scrive e le pubblica con la stessa regola.
- **Widget Altre zone** (112 widget in tutto, gruppo Dal sito): in una pagina per zona elenca le altre pagine per zona dello stesso
  servizio, solo quelle pubblicate, in riga o in elenco, con un titolo a scelta ("Lavoriamo anche a..."); fuori da una pagina per
  zona o senza altre zone pubblicate non mostra niente. Niente JavaScript; `sito/assets/zone.css` solo dove serve.
- **Dati per Google della pagina per zona**: un nodo Service con `name` "Idraulico a Casalecchio di Reno", `serviceType`,
  `areaServed` la zona (City o AdministrativeArea, la stessa regola delle sedi), `provider` la LocalBusiness della sede di
  riferimento (o l'Organizzazione), `mainEntity` della WebPage e le briciole Home > pagina del servizio > zona. Senza pagine per zona
  il sito esce identico a prima, al byte.
- In anteprima dal pannello (`?preview=1`, solo amministratore) si vede anche una bozza mai pubblicata, com'è nell'editor: le pagine
  per zona nascono così. Manuale d'uso capitolo 23, tecnico capitolo 45, `prove/prova-zone.js`, gruppo 43 degli attacchi.

## 2.27.0 (5 novembre 2026) - Le sedi e la SEO geografica

- **Le sedi dell'attività** (SEO e AEO > Sedi, solo amministratore; richiesta di Danilo: più sedi fin dall'inizio, anche per chi ne ha
  una sola). Ogni sede ha nome, tipo di attività da un elenco di 48 voci (ognuna con la categoria che Google riconosce) oppure
  "Altro, lo scrivo io" (il testo esce con la prima lettera maiuscola; verso Google diventa un'attività generica), via, CAP,
  città, provincia, paese, coordinate, telefono, email, fascia di prezzo, zona servita (fino a 30 righe), orari nello stesso
  formato del widget Orari di apertura, ferie, link alla scheda Google e "lascia una recensione" (solo https di google.com,
  g.page, maps.app.goo.gl), foto, descrizione, principale (una sola), pagina. I dati stanno nel gruppo `sedi` della
  configurazione SEO: niente tabelle nuove (`includes/sedi.php`, `includes/sedi-tipi.php`, `admin/sedi.php`).
- **Le coordinate dall'indirizzo**: "Cerca sulla mappa" le chiede dal server al servizio di OpenStreetMap (Nominatim, User-Agent
  `plumeCMS/<versione> (<sito>)`), con i rifiuti degli indirizzi interni come per i webhook; una mini mappa (tessere di
  OpenStreetMap, senza librerie) mostra il segnaposto, un clic lo sposta e ricalcola latitudine e longitudine, + e - lo zoom.
  Le tessere si chiedono solo quando le coordinate ci sono. Se la ricerca non riesce, le coordinate si scrivono a mano.
- **Dati per Google**: con almeno una sede, in ogni pagina l'Organizzazione ha `location` con una LocalBusiness (o il sottotipo)
  per sede: `@id`, nome, indirizzo a campi, coordinate, telefono, email, orari, fascia di prezzo, zona servita (City e
  AdministrativeArea), link alla mappa, foto, `sameAs`, `url` della pagina della sede, `parentOrganization`. Nella pagina di una
  sede la sua LocalBusiness è la `mainEntity` della WebPage; i servizi prendono `areaServed` dall'unione delle zone delle sedi.
  Nei titoli c'è `%citta%` (la città della sede principale; senza sedi sparisce con il suo separatore). Senza sedi il sito esce
  identico a prima, al byte.
- **La pagina della sede**: una pagina vera dell'editor, creata dal pannello (casella "Crea la pagina della sede" o pulsante
  "Crea la pagina"), con apertura, Dati dell'attività, Mappa, Orari, descrizione e Barra contatti letti dalla sede; sta nella
  sitemap come ogni pagina; eliminare la sede non la cancella (lo dice un avviso).
- **Widget nuovi** (111 in tutto): **Sedi** (le sedi in schede, tutte o scelte, griglia o elenco, con nome, tipo, indirizzo,
  telefono, email, "Aperto ora", Come arrivare, Lascia una recensione, La sede, e a scelta una mappa in ogni scheda) e **Dati
  dell'attività** (una sede, per il footer e la pagina Contatti, con gli interruttori su cosa mostrare). **Mappa, Orari di apertura
  e Barra contatti** hanno la tendina "Dati dalla sede": con una sede scelta prendono indirizzo, coordinate, orari, telefono ed
  email da lì (e il widget Orari non ripete i dati per Google); con "Nessuna" tutto come prima.
- **Sito su misura**: la città diventa la sede principale ("Sede di <città>", tipo dall'attività) e la Mappa dei Contatti legge
  "dalla sede principale". Manuale d'uso capitolo 23, tecnico capitolo 44, `prove/prova-sedi.js`, gruppo 42 degli attacchi.

## 2.26.0 (5 novembre 2026) - Barre a confronto

- **Barre a confronto**, la sedicesima infografica disegnata (gruppo Infografiche dell'editor, `ig_confronto`: `ig_barre` è già Barre di avanzamento): da 2 a 8 voci con
  un nome e un valore (euro, anni, percentuali...), barre orizzontali in proporzione al valore più alto, il numero alla fine
  (si può spegnere), l'unità dopo il numero. Serve per un capitale che cresce negli anni, gli anni per raddoppiare, i costi a
  confronto. Al computer il nome sta a sinistra della barra, al telefono sopra; chi non vede sente tutti i valori. Nata nel
  modulo del sito TascaPiena come `tp_barre`, dove cinque articoli la usano, entra nel nucleo com'era: in quei siti i widget
  salvati si rinominano in `ig_confronto` con le stesse impostazioni. 109 widget. Manuale d'uso capitolo 8, tecnico 41.


- **Il ripristino di una copia non cancella più niente prima di sapere che la copia si ricrea** (il 4 novembre 2026 su un
  sito vero il ripristino eseguiva DROP TABLE e subito dopo la CREATE della copia; la CREATE, scritta da un MySQL 8, portava
  una collazione che il database dell'hosting non conosceva, la tabella restava cancellata e il sito finiva senza utenti,
  impostazioni e pagine, con il pannello morto). Ora la copia si ricrea prima in **tabelle di appoggio** (`rip<6 esadecimali>_`
  davanti al nome vero); al primo errore si cancellano quelle e il sito resta com'era, con il motivo in parole semplici
  ("Il ripristino non si è fatto: il sito è come prima"). Se tutto va bene, **una sola RENAME TABLE** scambia le tabelle vive
  con quelle di appoggio (tutta o niente); le vecchie vanno in parcheggio (`vec<esadecimali>_`) e poi si cancellano. Le tabelle
  del sito che non stanno nella copia restano e il registro le elenca; le viste degli articoli si rifanno; le impronte degli
  schemi si azzerano, così al primo accesso le colonne nuove si aggiungono da sole; l'indirizzo del sito si scrive nelle
  impostazioni di appoggio prima dello scambio.
- **Copie fatte su un altro server**: le collazioni e i set di caratteri che il database di arrivo non conosce diventano i più
  vicini conosciuti (`utf8mb4_0900_ai_ci` -> `utf8mb4_unicode_ci`, `utf8mb3` -> `utf8`...), solo fuori dagli apici, e il
  registro lo dice ("Copia fatta su MySQL 8.0.46, qui MariaDB 10.6.28: in 17 tabelle la collazione utf8mb4_0900_ai_ci -> utf8mb4_unicode_ci"). Nel
  manifest della copia c'è `server_versione`. Provato da MySQL 8 a MariaDB 10.6 e a MySQL 5.7, e da MariaDB a MySQL 8. Una
  collazione `utf8_*` di una copia fatta su MySQL 5.7 diventa la `utf8mb3_*` con lo stesso suffisso sui server che elencano
  solo quelle (`utf8_bin` -> `utf8mb3_bin`, mai una `general_ci` al posto di una `bin`), e viceversa.
- Un ripristino alla volta (blocco del database, un secondo clic non ne fa partire due); un marcatore su file dice quali tabelle
  di lavoro ha in mano un ripristino, e un ripristino interrotto a metà (processo ucciso dall'hosting) le lascia: il ripristino
  dopo le toglie. Le righe INSERT della copia accettano solo valori letterali (una sottoquery o una funzione la fanno rifiutare,
  prima di toccare il database); una riga più grande di `max_allowed_packet` del server ferma il ripristino con il messaggio
  giusto, e le copie nuove scrivono blocchi sotto 1 MB. La struttura di una tabella (CREATE) non può portare vincoli o rimandi
  verso altre tabelle, altri server o file (REFERENCES, UNION=, CONNECTION=, DATA DIRECTORY, INDEX DIRECTORY, TABLESPACE, fuori
  dagli apici): plumeCMS non li scrive mai, e con due installazioni nello stesso database un vincolo legherebbe una tabella di
  questo sito a una dell'altro; la copia è rifiutata prima dello scambio, con la parola e la tabella nel motivo. Lo stesso
  blocco lo prende un aggiornamento (Impostazioni > Aggiornamenti) per tutto il suo lavoro: un ripristino e un aggiornamento
  non si incrociano mai. Le tabelle di lavoro lasciate da un ripristino interrotto si tolgono anche aprendo Copia del sito, e
  una che non si cancella resta scritta nel marcatore anche dopo il ripristino seguente.
- **Il registro del ripristino resta su file** (`aggiornamenti/copie/ripristino-<data e ora>-<copia>.log`, un file per
  tentativo, se ne tengono dieci) e in sicurezza_log: Copia del sito mostra sempre l'ultimo; se la copia ha altri utenti, dopo il nuovo accesso si torna lì con il registro. Se i file
  falliscono dopo il database, il registro dice di ripristinare la copia prima-del-ripristino; se fallisce solo quello che
  viene dopo i file (i file in più da mettere da parte, le protezioni dei temi), il titolo dice "Ripristino fatto, con errori
  nelle operazioni finali": database e file ci sono tutti. Nel registro di una riga fallita mai i valori (ci sono le password
  cifrate degli utenti): solo la tabella e il peso. I file di `uploads/` di una copia non firmata passano dal controllo dei
  caricamenti (un file con codice dentro si salta e si elenca).
- **La pagina di soccorso del pannello**: se il database non si raggiunge, o mancano le tabelle `users` o `settings` del
  prefisso, ogni pagina del pannello (anche il login) risponde con una pagina pulita (503) che dice in parole semplici cosa
  succede e i passi per rimettere il sito da una copia con phpMyAdmin. La pagina la vede chiunque (sta al posto del login): a
  chi non è entrato non dice il nome del database, il prefisso delle tabelle né la risposta tecnica del server; questi dati
  (servono in phpMyAdmin), l'elenco delle copie sul server (senza scaricarle né ripristinarle) e le prime 15 righe dell'ultimo
  registro di ripristino escono solo con il file `aggiornamenti/soccorso.txt`, che il proprietario crea con il gestore dei file
  e che vale 24 ore (poi la pagina lo ignora e dice di ricrearlo; la pagina ricorda di cancellarlo a lavoro finito). Mai
  percorsi del server, mai la password, mai l'utente del database (nella risposta del server utente e host sono coperti).
  La pagina avverte che una copia fatta su un altro hosting può essere rifiutata da phpMyAdmin (le collazioni) e si ripristina
  dal pannello. Il sito pubblico risponde "Torno subito" (503) come prima.
- **Niente più errori a video con i percorsi del server**: nel pannello `display_errors` è spento e un'eccezione non gestita
  diventa una pagina sobria con classe, codice, messaggio e file:riga relativi alla cartella del CMS (JSON per le pagine
  `-api.php`), tutto nel log; il dettaglio solo a chi è entrato nel pannello, agli altri l'ora della riga da cercare nel log;
  nelle tracce del log mai gli argomenti delle funzioni (una password passata a una funzione non ci finisce); nel sito il 503
  pulito. Con `define('CMS_DEBUG', true)` nel config tutto come prima.
- Gli snapshot degli Aggiornamenti (e il ripristino automatico di un pacchetto fallito) usano lo stesso motore: con un errore
  il database resta com'era, invece di restare a metà, e nemmeno i file dello snapshot si rimettono (file vecchi con un
  database nuovo sarebbero un sito a metà). Lo snapshot contiene solo le tabelle del sito (fino alla 2.25.0 tutto il database,
  anche le tabelle di un altro sito nello stesso database con i suoi utenti) e il ripristino rimette solo quelle: fino alla
  2.25.0 rieseguiva tutto, anche le tabelle di un'altra installazione o di altri programmi nello stesso database.
- Corretto: le copie e gli snapshot fatti su MySQL 8 perdevano le date `created_at` e `updated_at` di utenti, pagine e articoli
  (MySQL 8 segna quelle colonne "DEFAULT_GENERATED" e il dump le scriveva come DEFAULT, come le colonne generate di
  Prenotazioni ed Eventi): al ripristino prendevano l'ora del ripristino. Ora si scrivono i valori veri.
- **Sicurezza, secondo giro di correzioni (5 novembre 2026)**: una riga della copia come ``CREATE TABLE `x` (`c'` int); DROP TABLE
  `y` `` passava i controlli (lo spezzatore delle istruzioni conosceva solo gli apici semplici: l'apice dentro il nome fra apici
  inversi nascondeva il punto e virgola) e la connessione eseguiva anche il secondo comando. Ora: lo spezzatore (`agg_split_sql()`,
  anche per i file SQL dei pacchetti) e i pezzi fuori dalle virgolette conoscono apici semplici, doppi e inversi; una CREATE o
  una INSERT con un punto e virgola fuori dalle virgolette ferma il ripristino ("contiene un secondo comando"), e in ogni CREATE
  i nomi fra apici inversi devono essere solo lettere, cifre e trattino basso (fino a 64), come tutti quelli di plumeCMS; la
  connessione del CMS (`db()`, e quella dell'installatore) non esegue più istruzioni impilate (`MULTI_STATEMENTS` spento alla
  creazione). Inoltre: una CREATE con `TABLESPACE`, `DATA DIRECTORY` o `INDEX DIRECTORY` (anche dentro `/*!50100 ... */`) è
  rifiutata con il motivo vero ("contiene una clausola che il ripristino non accetta: TABLESPACE"), non "è stata modificata";
  il marcatore del ripristino scrive il prefisso del sito e ogni nome come coppia [marchio di lavoro, tabella], e non fa
  cancellare una tabella di un'altra installazione nello stesso database; un errore qualunque fra il marcatore e lo scambio
  (anche uno SHOW COLLATION fallito) toglie subito le tabelle di appoggio già create; alla fine di un ripristino (copia o
  snapshot) la modalità SQL di sessione torna quella di prima.
- **Terzo giro (5 novembre 2026)**: lo snapshot degli Aggiornamenti contiene solo le tabelle del sito (prima tutto il database,
  anche un altro sito nello stesso database con i suoi utenti); i blocchi INSERT delle copie si chiudono a 1 MB meno 1 KB (la
  riga che arriva al server, con il marchio di lavoro, sta in un `max_allowed_packet` di 1 MB esatto); nei dati di una INSERT il
  punto e virgola fuori dalle virgolette non è mai ammesso; due registri dello stesso secondo si ordinano bene (`-2` è l'ultimo);
  la pagina di soccorso copre il nome dell'utente del database solo come parola intera.
- Prove: `prove/prova-copia-ripristino-sicuro.js` (su quattro siti, più MariaDB e MySQL 5.7); manuale d'uso capitolo 29 con
  "Se il ripristino non si può fare" e "Se il pannello dice che il database è vuoto"; manuale tecnico 6.1, 19.4, 19.7, 19.8,
  19.9, 22, 23.4, 24 e 25.

## 2.25.0 (4 novembre 2026) - Scorri di lato

- **Il widget Scorri di lato** (richiesta di Danilo: "come sul sito Apple"): quando chi visita il sito arriva sul widget, la
  sezione si ferma e le sue pagine scorrono da destra a sinistra fino all'ultima; poi il sito riprende a scorrere in verticale.
  Cinque **disposizioni**, scelte tutte e cinque da Danilo fra le proposte: **Pellicola** (ogni pagina a tutto schermo, testo da
  una parte e immagine dall'altra, lineette di avanzamento in basso), **Carte** (una fila di carte con il titolo della sezione
  fermo in alto; la carta al centro si alza un poco), **Titolo fermo** (a sinistra il testo che cambia con una dissolvenza e il
  contatore "2 di 4", a destra scorrono le immagini), **Linea del tempo** (una linea che si colora, punti che si accendono, una
  tappa con la data per pagina), **Profondità** (lo sfondo si muove più piano del testo, che entra da destra e sfuma).
- Da 2 a 12 **pagine**, ognuna con occhiello, titolo (con la parte fra asterischi sfumata), testo, immagine dai Media con il
  testo alternativo, colore di sfondo, fino a due pulsanti con il link scelto dal sito, e la data per la Linea del tempo.
  Scelte del widget: velocità dello scorrimento, lineette sì o no, a tutta larghezza dello schermo.
- Al telefono funziona con il dito. Chi ha chiesto al telefono o al computer **meno movimento**, e chi ha JavaScript spento,
  vede le pagine una sotto l'altra. Con la tastiera, il pulsante che prende il fuoco porta la sua pagina in vista. Nella tela
  dell'editor le pagine stanno in fila, tutte visibili; l'effetto si vede sul sito e nell'anteprima. Più widget nella stessa
  pagina funzionano insieme. I colori seguono il tema.
- Negli elenchi dell'editor (pagine, pulsanti, voci...) le frecce **Sposta su** e **Sposta giù** accanto a ogni voce, per
  riordinare anche senza trascinare. I widget sono 108.

## 2.24.0 (3 novembre 2026) - Test a pagine, passi a carte con l'anteprima

- **Test e questionari, come si mostrano le domande** (richiesta di Danilo): **Una per pagina** (come prima, e com'è per i
  test già fatti), **A pagine, le scegli tu** (quante pagine e quali domande in ognuna) oppure **Tutte in una pagina**. Ogni
  pagina può avere un titolo ("Come inizi la giornata") che chi fa il test legge sopra le domande. Sul sito si va pagina per
  pagina ("Pagina 2 di 5"), con Indietro e Avanti; le domande condizionali compaiono anche dentro la pagina, una pagina senza
  domande da mostrare si salta, le domande sulla persona sono l'ultima pagina. Senza JavaScript tutto in una pagina, con i
  titoli delle pagine.
- **Il pannello del test, rifatto** (la proposta scelta da Danilo fra cinque): nel passo 2 una lavagna con una colonna per
  pagina e una carta per domanda, da trascinare fra le colonne o nel riquadro "+ Pagina nuova" (oppure con la tendina
  "Pagina" nella scheda), e a destra un telefono con l'anteprima dal vivo. Tutti e cinque i passi, per tutti e tre i tipi,
  hanno la stessa forma: carte compatte che si aprono in una finestra con i campi raggruppati, e il telefono che mostra quello
  che il passo cambia (la presentazione, la pagina delle domande, il risultato di una fascia, l'email del risultato, la fine
  del test). Al telefono il pulsante "Anteprima" apre l'anteprima a schermo intero. Niente si perde: ogni campo di prima resta.

## 2.23.0 (3 novembre 2026) - Tag delle persone, test più completi

- **I tag dei contatti** (richiesta di Danilo: "i tag permettono la selezione di gruppi di persone e i flussi"): si danno dalla
  scheda del contatto (con i suggerimenti), dall'elenco con l'azione di gruppo, dai form (passo 1; con la doppia conferma alla
  conferma), dall'importazione (colonna Tag, aggiungi o sostituisci) e dalle automazioni (azioni Aggiungi tag e Togli tag).
  Pagina **Tag** con i conteggi, rinomina ed elimina; filtro "Con il tag"; nei segmenti "ha / non ha il tag" (qualsiasi o
  tutti); innesco "Riceve un tag"; colonna TAG nell'esportazione; la cronologia registra "Tag aggiunto" e "Tag tolto".
- **Test e questionari, domande sulla persona**: da un catalogo di 25 (età, occupazione, settore con circa 70 voci, stato,
  figli, reddito e altre), alla fine del test, obbligatorie o facoltative a scelta per ogni test, con "Preferisco non
  rispondere"; si salvano anonime con l'esito.
- **Risposta breve e risposta completa** per ogni risultato: la breve sullo schermo, la completa per email. Con "Il risultato
  completo solo per email" (acceso nei test nuovi) il test finisce con "Lasciami la tua email e ti mando il risultato
  completo"; l'email ha la risposta completa, quella di ogni area e il grafico. I test di prima restano come sono sullo schermo.
- **Tag dai test**: chi si iscrive alla newsletter (lista scelta nel pannello) riceve alla conferma le sue risposte sulla
  persona ("Età: 35-44") e l'esito ("Test Stress: Alto"). Chi lascia l'email senza la newsletter riceve solo il risultato.
- **Statistiche per gruppi** (anche nel CSV): risultati e punteggi per ogni risposta sulla persona; i gruppi con meno di 5
  persone non si mostrano, nemmeno per differenza dal totale.

## 2.22.0 (3 novembre 2026) - Test e questionari

- Addon nuovo **Test e questionari** (Impostazioni > Addon): test a punteggio, ad aree con il grafico a barre e
  "che tipo sei" (profili). Domande a scala, scelta singola, sì/no e cursore da 0 a 10, con peso, invertite,
  obbligatorie e condizionali; fasce in percentuale per il totale e per ogni area, regole E/O che vincono sulle fasce,
  avvertenza. Procedura in cinque passi con il simulatore e il controllo delle fasce; tre modelli pronti. Richiesta di
  Danilo: un motore per i test come quello del burnout, tutto dal pannello.
- La pagina del test (`/fai-il-test/<nome>/`: l'indirizzo `/test/` è chiuso dal `.htaccess` di ogni sito): presentazione con
  l'editor visuale e il pulsante Inizia il test, una domanda per schermata con barra di avanzamento, Indietro, tastiera e
  ripresa dopo il ricaricamento; senza JavaScript tutte le domande in una pagina. Calcolo sempre sul server. Il risultato
  ha un link da condividere con solo l'esito, firmato.
- Risultato per email con un consenso separato, iscrizione a una lista con doppia conferma; nelle automazioni l'innesco
  "Completa un test" con il filtro sul risultato (fascia, area, profilo, regola).
- Risposte anonime (scelta di Danilo): si conservano esito e punteggi con il solo giorno, mai l'IP. Statistiche: iniziati,
  completati, abbandoni per domanda, risultati, media per area, CSV.
- Widget Riquadro in evidenza: con l'addon acceso, il pulsante può portare a un test scelto da un elenco; i test compaiono
  anche in "Scegli dal sito".

## 2.21.0 (3 novembre 2026) - Allinea ogni parte di ogni widget

- **Editor, scheda Stile**: ogni parte di testo che ha la sua Tipografia (occhiello, titolo, testo sotto il titolo, nome,
  ruolo, prezzo, didascalie...) ha subito sotto il suo **Allineamento**: come il widget, sinistra, centro, destra o
  giustificato, diverso per computer, tablet e telefono. In tutti i widget, anche quelli degli addon. Richiesta di Danilo:
  nel Titolo il testo sotto restava a sinistra con occhiello e titolo al centro.
- **Allineamento di tutto il widget**: c'è in ogni widget che ha del testo (37 non l'avevano), vale per tutte le parti,
  anche per quelle che hanno uno stile loro, e si sceglie con le icone. Il testo sotto il titolo ora segue il titolo.
- Le pagine già fatte restano come sono: senza una scelta nuova cambia solo dove l'allineamento di tutto il widget era già
  scelto e una parte non lo seguiva (per esempio il testo sotto il titolo, che ora va al centro con il titolo).

## 2.20.0 (3 novembre 2026) - Un pannello più snello

- Tolta da Impostazioni una funzione che sui siti veri non dava i risultati attesi (decisione di Danilo). L'aggiornamento
  cancella i suoi file e, alla prima apertura della dashboard, i dati che aveva lasciato nel database.
- **Sito di plumeCMS**: il pacchetto porta di nuovo la home, con l'apertura tenuta com'è sul sito. In Funzionalità la
  caratteristica **Social ogni mattina** (l'email con i post pronti, il calendario, le immagini nelle misure giuste).

## 2.18.0 (2 novembre 2026) - Le email delle automazioni richiamano i modelli

- **Automazioni, passo Email "Da un modello"**: l'email richiama il modello e lo legge ogni volta che parte. Correggi il modello
  in Modelli e da quel momento le email escono corrette (richiesta di Danilo: una sequenza di email scritta e corretta una volta
  sola). Oggetto e testo di anteprima vuoti nel passo valgono quelli del modello; scritti nel passo, vincono. Sulla tela il
  blocco dice "Modello: ...", nel pannello ci sono **Apri il modello** e **Stacca dal modello** (copia il contenuto di adesso
  e rende l'email indipendente, come nella 2.17.1).
- **Modello che non c'è più**: l'email non parte, la persona si ferma su quel passo, il registro lo dice e la dashboard lo
  segnala con il link all'automazione. Un modello inattivo si manda lo stesso.
- **Modelli**: colonna "Usato in" con le automazioni; conferme che le elencano prima di eliminare o rendere inattivo un
  modello; testo di anteprima; modelli in codice HTML creati e modificati dal pannello.
- **Email "a un'ora precisa"**: se il turno arriva dentro l'ora scelta parte subito. Prima slittava al giorno dopo, e "attesa 7
  giorni, alle 8" mandava un'email ogni 8 giorni.
- **Menu del pannello** più leggibile in tutti e quattro gli aspetti: voci piene, icone nel colore d'accento, gruppo aperto
  segnato, sotto-voci nel colore del testo e pagina attiva piena (richiesta di Danilo: "fiacchi e poco identificabili").
- **Pacchetti del sito**: con `tieni_apertura` una pagina già scritta da un pacchetto tiene la sua prima sezione se è un'Apertura
  (regola di Danilo: a ogni aggiornamento la hero della home resta com'è).
- Le email delle automazioni già salvate (anche quelle copiate da un modello con la 2.17.1) restano identiche.

## 2.17.1 (2 novembre 2026) - Le email delle automazioni dai modelli

- **Automazioni, passo Email**: il Contenuto ha una terza scelta, **Da un modello**, con tutti i modelli di Newsletter e form >
  Modelli (a blocchi e in codice HTML, anche quelli non attivi, segnati). Al salvataggio il modello si copia nel passo e, con
  l'oggetto vuoto, ne porta l'oggetto. Prima si poteva solo "partire da un modello" dentro Editor a blocchi, e solo con i
  modelli attivi a blocchi (segnalazione di Danilo: "devo poter scegliere l'email da un modello").
- Le email dei passi possono essere in codice HTML (dal modello), come le campagne.

## 2.17.0 (2 novembre 2026) - Quindici aperture nuove

- **L'Apertura (hero) ha ventisei disposizioni**: alle undici di prima si aggiungono Prospettiva, Computer e telefono, Pila di
  finestre, Orbita, Terminale, Nastro di schermate, Titolo traforato, Faro, Orizzonte, Video, Note sulla foto, Lente,
  Costellazione, Prima e dopo, Trittico. Stesso aspetto delle undici (finestra con il velo, riquadri di vetro, titolo con
  l'evidenza), colori del tema, tutte in colonna sotto gli 860 pixel, animazioni ferme per chi chiede meno movimento.
- **Campi in più** che compaiono solo con la loro disposizione: righe del terminale, altre foto del nastro, parola grande,
  video, etichette sulla foto, punto da ingrandire, forma della costellazione, foto "prima", numero grande del trittico.
- **Video nei Media**: un riquadro Video per caricare MP4 e WebM (fino a 64 MB, il tipo si legge dal file) e sceglierli
  nell'editor. Il video parte al clic sul play; senza video l'apertura mostra la foto.
- La classica e le undici di prima restano identiche al pixel; un'Apertura con una disposizione che il sito non conosce resta
  salvata e si vede come la classica.

## 2.16.1 (2 novembre 2026) - Il limite di caricamento fra i requisiti

- **Requisito nuovo: limite di caricamento di almeno 8 MB** (`upload_max_filesize` e `post_max_size`), perché i pacchetti di
  aggiornamento pesano più dei 2 MB che molti hosting hanno di partenza. L'installatore lo controlla al primo passo (sotto
  8 MB un avviso, l'installazione va avanti), Impostazioni > Aggiornamenti e Controlla il server lo dicono con dove alzarlo.
- Requisito scritto anche in INSTALLA.md, README.md, nel manuale d'uso e nella tabella "Cosa serve" del sito di plumeCMS.

## 2.16.0 (2 novembre 2026) - Form a cinque passi, pagine pubbliche e consenso

- **Costruttore dei form a cinque passi** con Avanti: Impostazione, Aspetto e campi, Messaggi, Conferma, Condividi. Tipi
  Iscrizione, Pop-up, Aggiornamento del profilo, Disiscrizione e Altri form, con una linguetta per tipo nell'elenco.
- **Campi legati agli attributi** del contatto (la casella da spuntare solo con un attributo Sì/No); blocchi nuovi Iscrizione a
  più liste, Gruppi di consenso, Consenso al tracciamento. **Dichiarazione GDPR** sopra la casella vuota e **registro del
  consenso** (testo, data, IP).
- **Conferma** doppia, semplice o nessuna; lista principale e liste in più; aggiorna i contatti esistenti; modello del doppio
  opt-in scelto fra i modelli delle email e pagina dopo il clic.
- **Condividi**: pagina del form pronta (`/modulo/<nome>/`, l'unica che si può mettere in un iframe), codice iframe, codice HTML,
  HTML semplice che funziona senza JavaScript, QR.
- **Pagine di disiscrizione** in più, scelte nella campagna; uscita da una sola lista; centro preferenze con i gruppi e il
  tracciamento; un form di profilo può fare da centro preferenze. Voce nuova **Pagine e consenso**.
- **Gruppi di consenso** (spenti di partenza) e **consenso al tracciamento**: senza consenso le email partono senza pixel e
  senza link tracciati, e aperture e clic non contano nei segmenti e nelle automazioni.
- I form di prima restano uguali: stesso HTML, stesse email, stessi file consegnati e stesse iscrizioni.

## 2.15.0 (2 novembre 2026) - Le automazioni

- **I flussi diventano Automazioni**: barra con nome, tag e stato (Attiva, In pausa, Inattiva), numeri di entrati, in corso,
  finiti e usciti. Pannello a schede **Inneschi**, **Azioni**, **Regole** (anche da tastiera); "+" sotto un passo per
  aggiungere e collegare.
- **Inneschi**: fino a 10 per automazione. Nuovi: tolto da una lista, disiscritto, anniversario di una data (anche N giorni
  prima, una volta l'anno), aggiunto a mano.
- **Azioni**: email, aggiungi o togli da una lista, aggiorna un attributo, webhook firmato (solo https, mai verso indirizzi
  interni o della rete locale).
- **Regole**: attesa da 1 minuto, attendi un evento (apre o clicca) con un massimo, divisione condizionale fino a 8 rami con
  "altrimenti" (con i filtri dei segmenti), divisione in percentuale, Fine.
- **Passo Email**: mittente, oggetto, anteprima, testo semplice, editor a blocchi o modello; parte subito, a un'ora precisa o
  all'orario migliore.
- **Impostazioni**: rientro con le ore di attesa, condizioni di uscita (si disiscrive, entra in un segmento) controllate anche
  durante un'attesa. **Modelli**: Benvenuto, Anniversario, Attività di marketing.
- **Statistiche per passo** sulla tela (quanti ci sono, quanti sono passati, aperture e clic delle email) e **Attività e
  registro**: chi è dentro, Togli, Aggiungi a mano, registro di 90 giorni.
- I flussi di prima continuano dove erano, con le stesse email; un flusso spento diventa Inattiva.

## 2.14.0 (2 novembre 2026) - Report, statistiche e orario migliore

- **Report della campagna** a schede (Panoramica, Aperture, Clic, Disiscrizioni, Rimbalzi): destinatari, inviate, consegnate,
  aperture e clic unici e totali, clic sulle aperture, disiscrizioni con i motivi, rimbalzi definitivi e temporanei, spam.
  **Mappa dei clic** sull'anteprima dell'email; **elenchi** di chi ha aperto, non ha aperto, cliccato (anche un solo link),
  è uscito o è rimbalzato, da esportare in CSV o salvare come lista (aperture e clic anche come segmento).
- **I tassi sono calcolati sulle consegnate** (le inviate meno quelle rimbalzate): per le campagne di prima i numeri per
  persona restano uguali, il tasso cambia solo se dopo l'invio era arrivato un rimbalzo. Da ora si contano anche i clic
  ripetuti, e i rimbalzi temporanei diventano un evento.
- **Statistiche** di un periodo (totali, andamento, confronto fra campagne, flussi) e delle liste (nuovi, usciti, iscritti
  nel tempo). **Soglie**: rimbalzi oltre il 2%, disiscrizioni oltre l'1%, spam oltre lo 0,2%, dette in parole.
- **All'orario migliore** (nella parte Programma della campagna): ognuno riceve l'email, nel giorno scelto, all'ora in cui di
  solito apre la posta (dalle aperture degli ultimi 12 mesi); chi non ha dati, all'ora scelta.

## 2.13.0 (2 novembre 2026) - Il test A/B delle campagne

- **Crea una campagna > Test A/B**: si prova l'**oggetto** (con il testo di anteprima) oppure il **contenuto** (due design,
  con tutte e cinque le strade, anche l'editor a blocchi; la versione B nasce come copia della A).
- Nel riepilogo la parte **Test A/B**: grandezza dei due gruppi di prova (fino al 50% dei destinatari, scelti a caso),
  criterio (aperture o clic), durata in ore (fino a 23) o giorni (fino a 7). Si manda subito o si programma.
- Alla fine della durata il cron sceglie la versione vincente e la manda a tutti gli altri, una volta sola; a pari merito
  parte la A. Nessuno riceve due versioni.
- **Risultati** nel report della campagna (le due versioni con invii, aperture, clic e tassi, la vincente, il resto) e la
  scritta "Test A/B" nell'elenco. La prova manda tutte e due le versioni; la versione web mostra quella ricevuta.
- Corretto: il blocco dell'invio delle newsletter valeva per tutto il server MySQL, e due siti sullo stesso server con lo
  stesso prefisso delle tabelle si fermavano l'invio a vicenda. Ora ha il nome del database.

## 2.12.0 (2 novembre 2026) - L'editor delle email a blocchi

- **Editor delle email a blocchi** (nella campagna, Design > Editor a blocchi): linguette Contenuti, Modifica, Stile e
  Salvati; otto blocchi (titolo, testo, immagine, pulsante, divisore, spaziatore, social, video) più il piede; sette
  disposizioni di colonne; trascinamento e tastiera; vista computer e telefono; annulla e ripeti; salvataggio da solo.
- L'email esce in HTML fatto per la posta: tabelle, stili in linea, pulsanti che si vedono anche in Outlook, immagini con
  testo alternativo, colonne una sotto l'altra al telefono. Il link per uscire e l'indirizzo dell'azienda si rimettono da
  soli se mancano.
- **Modelli** (Newsletter e form > Modelli): i tuoi modelli, attivi o no, da rinominare, duplicare, eliminare; "Salva come
  modello" dall'editor; una **galleria** di sei modelli pronti (benvenuto, newsletter del mese, offerta, evento, nuovo
  articolo, conferma dell'iscrizione). L'email di conferma può usare un modello.
- **Sezioni salvate**: una sezione che usi sempre si salva e si riusa; quelle sincronizzate, cambiate, si aggiornano in
  tutte le email in bozza e nei modelli.
- Il Design di una campagna si sceglie fra cinque strade: editor a blocchi, un tuo modello, la galleria, l'editor semplice,
  il codice HTML. Le campagne di prima restano identiche.

## 2.11.0 (2 novembre 2026) - Campagne a riepilogo e centro preferenze

- **Crea una campagna** (nome interno) e una **pagina di riepilogo** con cinque parti, ognuna con il suo stato (A posto o Da
  completare): **Mittente**, **Destinatari** (liste e segmenti, esclusioni che vincono, senza i contatti non coinvolti,
  doppioni tolti), **Oggetto**, **Design** (editor semplice o codice HTML, più il testo semplice), **Impostazioni aggiuntive**
  (Rispondi a, campo "A", versione web, link alle preferenze, UTM sui link del sito, tag, invio a lotti). Finché manca
  qualcosa la campagna non parte, e il riquadro in cima dice cosa.
- **Anteprima** con i dati di un contatto, al computer e al telefono; **prova** alla lista dei test; **Invia subito** o
  **Programma** con giorno e ora; Annulla, Sospendi, Riprendi.
- **Elenco delle campagne** con le viste (bozze, programmate, inviate, archiviate...) e le azioni: Report, Duplica scegliendo
  cosa copiare, Invia ai nuovi contatti, Rimanda ai non aperti (una volta sola), Archivia, Elimina solo in bozza.
- **Versione web** di ogni email e **indirizzo dell'azienda** nel piede (avviso nell'elenco se manca).
- **Pagina per uscire** con il questionario (perché esci) e **centro preferenze**: chi riceve le email sceglie le liste da
  tenere, cambia i dati consentiti o esce da tutto; chi è uscito può iscriversi di nuovo. "Perché sono usciti" nelle
  statistiche e nel report di ogni campagna.
- Le campagne di prima si aprono nel riepilogo e partono identiche al byte.
- Corretto: con il sito in una cartella il pixel di apertura e i link delle email avevano la cartella due volte, e aperture
  e clic non si contavano.

## 2.10.0 (2 novembre 2026) - Segmenti e quindici infografiche

- **Segmenti** (Newsletter e form > Contatti > Segmenti): gruppi di contatti che si aggiornano da soli.
  - Condizioni su attributi (anche date relative: negli ultimi 30 giorni, questo mese), iscrizione, lista nera e motivo,
    liste e "in nessuna lista", altri segmenti, email ricevute, aperte e cliccate (una campagna, l'ultima, qualsiasi),
    link preciso cliccato, form inviato. Fino a 100 condizioni, in gruppi uniti da E e da O, con le negazioni.
  - Crea un segmento da zero o da uno dei dieci modelli (per esempio chi non apre da 90 giorni, i nuovi iscritti di
    questo mese, chi ha cliccato l'ultima campagna); il numero dei contatti si aggiorna mentre scrivi. Categorie, ricerca,
    esporta, crea una lista dal segmento. Un segmento usato da un altro o da un flusso non si elimina.
- **Pagina Contatti**: "Carica un segmento", **Aggiungi filtro** con le stesse condizioni, **Salva come segmento**, il filtro
  scritto in parole e le **viste salvate** (filtri e colonne con un nome, per ogni utente).
- **Flussi automatici**: i bivi usano le stesse condizioni (quelli di prima danno lo stesso esito), più "fa parte / non fa
  parte di un segmento".
- **Quindici infografiche disegnate** nel gruppo Infografiche dell'editor: imbuto, piramide a livelli, matrice a quattro
  quadranti, ciclo, ciambella, cerchi che si toccano, ragnatela, lancetta, iceberg, omini, il viaggio, bilancia, mappa di
  calore, cerchi concentrici, barre al cento per cento. Disegno fatto dal server con testo vero, colori del tema, una
  composizione per il telefono, contrasto del testo almeno 4,5. I widget sono 107.
- Le anteprime dei temi Aria, Bottega e Cosmo sono WebP: lo zip di aggiornamento resta sotto i 2 MB.

## 2.9.0 (2 novembre 2026) - Importa ed esporta i contatti

- **Importa contatti** in quattro passi (Newsletter e form > Contatti > Importa contatti):
  1. **Carica**: un file CSV, TXT o Excel (XLSX), anche con gli accenti dei file di Windows, oppure Copia e incolla;
  2. **Abbina i dati**: le prime righe di ogni colonna e dove va; le colonne con il nome di un attributo si abbinano da sole,
     le altre diventano un attributo nuovo o restano fuori; senza la colonna delle email non si va avanti;
  3. **Scegli la lista**: una o più, oppure una lista nuova in una cartella;
  4. **Concludi**: aggiornare chi c'è già, celle vuote che cancellano, lista nera dal file, il consenso da confermare.
  Il lavoro va avanti a giri (anche 20000 righe), dalla pagina e dal cron; alla fine il resoconto con nuovi, aggiornati,
  invariati, doppioni ed errori, e il file delle righe scartate con il motivo. Chi è in lista nera non torna iscritto.
- **Esporta**: una finestra per scegliere quali contatti (quelli scelti, una lista, chi ha aperto o cliccato una campagna),
  quali attributi e il separatore. I file grandi si preparano a giri e restano 7 giorni. Le celle con le formule escono
  innocue.
- **Lista dei test** in Newsletter > Impostazioni: fino a 20 contatti a cui va la prova di una campagna, ognuno con i suoi
  dati, senza contare nelle statistiche.
- I file dei contatti stanno in una cartella privata, chiusa al web (anche nell'esempio per Nginx, che ora chiude anche
  quella dei file riservati).
- Pannello e manuale non fanno più paragoni con altri servizi.

## 2.8.0 (1 novembre 2026) - I contatti con la loro scheda

Prima fase del rinnovo di "Newsletter e form" (piano in `docs-sviluppo/brevo-confronto.md`).

- **Menu nuovo**: Contatti, Campagne email, Form, Invii dei form, Flussi automatici, Statistiche, Impostazioni, Attributi dei
  contatti. I vecchi indirizzi Iscritti e Liste portano alle pagine nuove.
- **Contatti**: elenco con le colonne che scegli tu (Personalizza le colonne, anche con la tastiera), ricerca, filtro per lista e
  per stato, 50, 100 o 500 per pagina; selezione delle righe o di tutti quelli del filtro; azioni di gruppo: aggiungi a una lista,
  togli, sposta, metti in lista nera, modifica un attributo, esporta in CSV, elimina definitivamente (resta l'impronta anonima).
- **Crea un contatto** a mano; se l'email c'è già, si apre il contatto che esiste.
- **Scheda del contatto**: panoramica con gli attributi modificabili, statistiche delle email degli ultimi 90 giorni, form
  compilati, attività; cronologia con i filtri; liste; preferenze con la lista nera.
- **Attributi dei contatti**: sei tipi (testo, numero, data, sì/no, categoria, scelta multipla), fino a 200; **attributi calcolati**
  (letture, clic, invii, form in un periodo) e **valori globali**. Nelle email si scrivono come `{{ contact.NOME }}`, con
  `upper`, `lower`, `capfirst` e `default`; `{nome}` funziona ancora.
- **Liste nelle cartelle**, con crea, modifica, sposta ed elimina.
- **Lista nera con il motivo** (si è disiscritto, bloccato dall'amministratore, rimbalzo definitivo, cinque rimbalzi temporanei di
  fila, segnalato come spam, importato); si toglie un contatto alla volta, confermando di avere il consenso.
- **Aggiornamento senza perdite**: gli iscritti di prima diventano contatti con il loro stato e la loro data, le liste vanno nella
  cartella "Le tue liste".

## 2.7.0 (1 novembre 2026) - L'email di conferma la scrivi tu

- **L'email di conferma dell'iscrizione si modifica in ogni parte** (richiesta di Danilo): oggetto, intestazione in cima,
  titolo, testo, testo e colore del pulsante, frase sotto il pulsante, firma e nota in fondo. Segnaposti `{nome}`, `{lista}`,
  `{giorni}`, `{sito}`; il link del pulsante resta sempre quello vero.
  - per ogni form: Newsletter e form > Form, nella scheda del form, riquadro "Email di conferma";
  - per il modulo della newsletter e come base per tutti i form: Newsletter > Impostazioni, "Email di conferma dell'iscrizione";
  - "Aggiorna l'anteprima" mostra l'email com'è, "Manda una prova a me" la spedisce con un link finto che non conferma niente;
  - solo l'amministratore la cambia; chi non tocca niente riceve l'email di prima, identica.

## 2.6.0 (1 novembre 2026) - Un pannello più chiaro

Controllo di UX e UI di tutto il CMS chiesto da Danilo ("i titoli delle sezioni devono avere uno sfondo diverso"), fatto da due
esperti con le regole scritte in `docs-sviluppo/regole-interfaccia.md` e i dettagli in `docs-sviluppo/ux-ui-2026-11-*.md`.

- **Pannello più leggibile, in tutti e quattro gli aspetti**: i titoli dei riquadri stanno in una fascia con un fondo loro; le
  etichette dei campi non sembrano più titoli; campi di testo, aree e tendine hanno un bordo che si vede (almeno 3 a 1, prima 1,3)
  e un contorno chiaro quando ci si scrive; "Sfoglia" è un pulsante vero dentro un riquadro tratteggiato; il contorno del fuoco
  da tastiera si vede ovunque, anche nelle pagine degli addon.
- **Le conferme** ("Eliminare...?") sono una finestra del pannello, con il pulsante rosso quando l'azione cancella qualcosa.
- **Al telefono** ogni tabella del pannello diventa una scheda per riga; Utenti e Categorie su una colonna.
- **Impostazioni** ha due schede, Configurazione email e **Sito** (indirizzo del sito, sito pubblico, frase in fondo), con la
  voce Sito nel menu. I vecchi indirizzi portano alla scheda giusta.
- **Temi**: il pulsante si chiama "Usa questo tema" (prima "Attiva", accanto al distintivo "ATTIVO").
- **Articoli**: il filtro "Pillar e cluster" compare solo se usi i pillar; la colonna si chiama "Parole e parola chiave".
  Le bozze nate aprendo "Aggiungi articolo" e mai scritte vanno da sole nel cestino dopo un giorno (giro del mattino del cron,
  con la riga nel Log cron). Una bozza con qualcosa dentro non si tocca mai.
- **Dashboard**: "Stai provando il sito" è un riquadro che si chiude e resta chiuso dopo la prima visita.
- **Download**: i giorni dei link dei file riservati stanno in un riquadro loro (prima, sotto Carica, un secondo "Salva" salvava
  i giorni e perdeva il file scelto); un file più grande del limite del server si dice sotto il campo appena lo scegli, e un invio
  troppo grande dà un messaggio in parole invece di "Token CSRF non valido" (anche in Aggiornamenti e Media). L'aiuto lungo si apre a richiesta.
- **Stile del sito > Identità**: se il widget Logo del sito di un header o footer ha un'immagine sua, il pannello lo dice, con il
  link al modello (prima il logo cambiato qui non compariva e non si capiva perché).
- **Controlla il server** dice se `allow_url_fopen` è spento (visto su plumecms.it): senza, il sito non chiede al sito ufficiale se
  c'è una versione nuova e Installa ora non parte. Prima si leggeva "il sito non risponde"; ora il pannello dice la causa e dove accenderlo.
- **SEO**: i passi del service account di Google stanno in un riquadro che si apre. **Utenti**: ultimo accesso in parole.
  **Accesso**: "Nome utente o password non corretti. Controlla le maiuscole e riprova."
- **Editor**: i widget si aggiungono anche con Tab e Invio; le schede del pannello si usano con le frecce; un cursore vuoto dice
  "come il sito" invece di sembrare a zero; accanto al pallino dello schermo compare "solo tablet" o "solo telefono"; i widget
  degli addon senza dati mostrano nella tela dove aggiungerli (solo nell'editor); al telefono l'editor dice che si usa da
  computer o tablet.
- **Installatore**: errori del database in parole semplici e sotto il campo sbagliato; con la carta Su misura il pulsante dice
  "Avanti: le quattro domande".
- **Addon**: Agenda con "Settimana prima" e "Settimana dopo"; Vetrina con carrello e filtri leggibili; Copia altrove con un solo
  pulsante di salvataggio e "Manda una copia adesso" spento finché la destinazione non è collegata.
- **Sul sito (cambi voluti)**: la data sotto i titoli delle schede del blog e dei risultati della ricerca è più leggibile in tutti
  gli undici temi (in Bottega da 3,6 a 7,1 di contrasto); la ricerca senza risultati propone una parola più corta e le pagine
  principali; nei form i campi obbligatori si controllano prima di "Sono una persona". Il resto del sito è identico al pixel.

## 2.5.0 (1 novembre 2026) - Otto temi nuovi e un audit

- **Otto temi nuovi** in Editor > Temi (richiesta di Danilo): le quattro stagioni **Estate**, **Inverno**, **Autunno**,
  **Primavera** e le tinte piene **Giallo** (255, 255, 0), **Rosso** (255, 0, 0), **Verde** (0, 255, 0), **Blu** (0, 0, 255).
  Ogni scritta di ogni tema, provata su sette pagine al computer e al telefono, ha contrasto almeno 4,5 a 1. I temi nuovi
  correggono dentro di sé alcuni colori fissi dei widget (pulsanti negli articoli, social, aperture senza foto).
- **Audit del CMS**, 16 problemi trovati e risolti (dettaglio in `docs-sviluppo/audit-2026-11.md`):
  - le emoji si salvano in form, popup, Download, liste, redirect e campagne, invece di dare un errore del database;
  - un'emoji nel pannello SEO, nel riassunto o in "In breve" non esce più scritta `&#x1F377;` sul sito, nei social e in Google;
  - Vetrina: un indirizzo del negozio uguale a quello di una pagina si rifiuta (prima la pagina spariva dal sito);
  - Prenotazioni ed Eventi: la pagina della privacy scritta a mano non torna più in silenzio quella di partenza;
  - Meta Pixel e Clarity: un ID scritto male si dice, non si toglie in silenzio;
  - anteprima delle versioni corretta per i siti in una cartella; un nome mostrato lungo in Utenti; 31 frasi del pannello con
    gli accenti giusti; "Pagina aggiornata".
- **Aggiornamenti più sicuri**: dopo aver scritto i file PHP il pannello dice a opcache di dimenticare le copie vecchie. Prima, nei
  secondi subito dopo un aggiornamento, una visita poteva trovare un file nuovo insieme a uno vecchio e la pagina andava in errore.

## 2.4.0 (1 novembre 2026) - plumecms.it

- **Il sito ufficiale è https://plumecms.it** (decisione di Danilo, dominio nuovo): da qui ogni installazione chiede se c'è una
  versione nuova e la scarica con Installa ora, e qui porta la frase "Un nuovo sito plumeCMS" in fondo ai siti. Chi aveva salvato
  la frase con il vecchio indirizzo (plumecms.danilofailli.it) passa da solo al nuovo.
- **I siti con la 2.3.2 o prima** cercano gli aggiornamenti sul vecchio indirizzo, che chiude: questa versione si carica una volta
  a mano (Impostazioni > Aggiornamenti > Carica un pacchetto). Dalla 2.4.0 Installa ora torna a funzionare.
- **Impostazioni > Sito pubblico, Indirizzo del sito** (nuovo): per spostare un sito su un altro dominio. Si scrive il dominio
  nuovo; prima di salvarlo il pannello prova che apra proprio questo sito (un file con un codice a caso, letto dal nuovo indirizzo).
  Da quel momento link, sitemap, canonical, email e Search Console usano il nuovo indirizzo. "Salva lo stesso" per gli hosting che
  non lasciano il sito chiamare sé stesso.

## 2.3.2 (1 novembre 2026) - Il service account di Google si vede dopo il salvataggio

- **Corretto** (segnalazione di Danilo): in SEO e AEO, scheda Motori di ricerca, la chiave JSON del service account di Google si
  salvava (messaggio verde) ma il riquadro restava vuoto: niente email, niente "Prova il collegamento", e le schede Copertura e
  Prestazioni dicevano ancora "Serve il service account". Il menu laterale del pannello usava per sé nomi di variabile comuni e
  cancellava quella della pagina. Ora il menu usa nomi suoi: vale per tutte le pagine del pannello.
- Nel riquadro di Search Console il passo 1 porta dritto a "Crea progetto" di Google Cloud e ricorda di controllare l'account.

## 2.3.1 (1 novembre 2026) - Il pulsante "Scarica l'aggiornamento" dà sempre l'ultima

- **Corretto** (segnalazione di Danilo): dopo aver caricato la 2.3.0, il pulsante "Scarica l'aggiornamento" del sito di plumeCMS
  dava ancora la 2.2.0. L'indirizzo che porta sempre all'ultima versione (`/scarica/plumecms-ultima-aggiornamento/`) mandava il
  file dicendo al browser di tenerlo per un'ora: chi l'aveva scaricato da poco riceveva la copia vecchia. Ora quell'indirizzo
  rimanda, senza farsi ricordare, all'indirizzo della versione precisa (`/scarica/plumecms-2.3.1-aggiornamento/`). Vale per ogni
  file con le versioni nel nome, in Download.

## 2.3.0 (31 ottobre 2026) - La versione sempre in vista, e Installa ora

- **La versione installata sempre in alto a sinistra** del pannello, accanto al nome (per esempio "v. 2.3.0"), in ogni pagina.
  Al telefono sta accanto al pulsante del menu.
- **Nuova versione disponibile, Installa ora** (richiesta di Danilo): nel banner della dashboard, subito dopo "Visita il sito
  ufficiale plumeCMS", compare "Nuova versione disponibile v. x.x.x" con il pulsante **Installa ora**. Un clic, una conferma: il
  pannello scarica lo zip dal sito ufficiale, controlla dimensione e impronta sha256, fa lo snapshot e applica; se un passo non
  va, rimette tutto com'era. Solo l'amministratore lo vede. Un sito con moduli suoi passa ancora da "Scarica e prepara" e dal
  riepilogo prima di applicare.
- Il riquadro azzurro "plumeCMS x.x.x è disponibile" della dashboard non c'è più: lo sostituisce la riga del banner.

## 2.2.0 (31 ottobre 2026) - Scegli dal sito

- **Scegli dal sito** (richiesta di Danilo): accanto a ogni campo del pannello che vuole un link a una pagina del sito c'è il
  pulsante **Scegli dal sito**. Si scrivono due lettere del titolo, si sceglie, e il link entra da solo: niente più indirizzi
  scritti a mano. Trova pagine, articoli (anche in bozza o programmati, con il segno), categorie, tag e, con gli addon accesi,
  eventi, prodotti e negozio, corsi, area riservata. Si usa con la tastiera e al telefono.
  - Dove: i link dell'editor visuale (pulsanti, Apertura, immagini, inviti, ripetitori, header, footer, fine articolo), i link nei
    testi (widget Testo, campagne e flussi della newsletter), Popup (pulsante e pagine dove compare), Form (pulsante "Continua"),
    Newsletter, Social (link di WhatsApp), SEO (redirect, IndexNow, Google, dati per Google), Menu, la pagina della privacy di
    Eventi e Prenotazioni.
  - Il link salvato è l'indirizzo della pagina (`/contatti/`): con il sito in una cartella porta comunque dentro la cartella, e
    resta giusto se il sito si sposta. Nelle email l'indirizzo è intero, con `https://`.
  - Menu: una pagina, un articolo o una categoria scelti così diventano una voce di quel tipo, che segue la pagina se cambia
    indirizzo. Scrivere a mano l'indirizzo di una voce ora la fa diventare "Link personalizzato" (prima quello che si scriveva
    non si salvava).
  - Il redattore lo usa anche lui: vede tutte le pagine e gli articoli; degli addon solo quello già pubblicato.
- **Link più sicuri**: il link del pulsante dei popup, il "Continua" dei form, il pulsante delle email e la pagina della privacy di
  Eventi e Prenotazioni ora passano dal controllo dei link (prima un `javascript:` scritto lì usciva sul sito).
- **Editor**: Canc e Ctrl+Z dentro una finestra aperta (Scegli dal sito, Scegli dai Media) non toccano più la tela (prima Canc
  cancellava il widget selezionato).
- **Controlla il server prova Google e IndexNow** dal server vero: con il service account salvato, se Search Console vede la
  tua proprietà; con la chiave di IndexNow, se il file della chiave si legge da fuori e se `api.indexnow.org` risponde. Senza i
  dati, se il server raggiunge i due servizi.
- **Un certificato HTTPS che non va si dice per nome** ("Il certificato HTTPS del sito ... non è valido"), nel controllo della
  versione nuova e in Controlla il server: prima diceva "non risponde". Provato con un certificato vero, uno di un'autorità
  sconosciuta, uno per un altro nome e uno scaduto.
- **Richiesta di prenotazione**: "A chi arriva la richiesta" lo cambia solo l'amministratore. Un redattore vede il campo spento,
  e il server tiene l'indirizzo di prima anche se gli arriva un altro valore.
- **Editor, nomi doppi**: "Animazione delle parole" del Titolo animato e lo spazio interno di Avviso, Flip box, Condividi e Icone
  social avevano lo stesso nome di un controllo delle Avanzate: uno cancellava l'altro. Ora sono separati. Le pagine fatte prima
  restano uguali al pixel; il Titolo animato con le parole che si alternano ora ha la sua animazione di partenza (la scrittura a
  macchina), che prima non partiva.
- **Manuale d'uso più leggero**: il PDF passa da 8,1 a 7,1 MB, con le scritte delle schermate ancora nitide.

## 2.1.0 (31 ottobre 2026) - Le email arrivano, gli addon completi, un file solo

- **Le email arrivano nella posta, non nello spam.** Pannello nuovo Impostazioni > Consegna delle email, con sei linguette (Da dove
  cominciare, Dominio, Come partono, Mittenti, Rimbalzi e spam, Riscaldamento): il mittente (anche più di uno, con il controllo dell'indirizzo), il dominio (SPF, DKIM e DMARC da copiare nel
  pannello dell'hosting, con il controllo di quello che c'è), come partono le email (il server del sito, SMTP, oppure le API di
  Brevo, Amazon SES e Mailgun, con le chiavi salvate cifrate), rimbalzi e segnalazioni di spam (gli indirizzi che rimbalzano
  escono dagli invii da soli), il riscaldamento di un dominio nuovo (pochi invii al giorno che crescono). Campagne e flussi scelgono il loro mittente.
- **Prenotazioni**: una prenotazione presa al telefono si scrive dal pannello (Prenotazioni > Nuova prenotazione), anche fuori dagli orari
  di lavoro se lo si vuole; il cliente riceve la conferma con il file del calendario (.ics) che si aggiorna da solo quando
  l'appuntamento cambia o si annulla.
- **Raccolta recensioni**: "Com'è andata?" arriva anche a chi ha partecipato a un evento (ai presenti o a tutti gli
  iscritti), dopo i giorni scelti nelle Impostazioni.
- **Corsi online**: il testo delle lezioni si scrive con l'editor visuale (le lezioni scritte prima restano come sono) e
  l'ordine di moduli e lezioni si cambia trascinando.
- **Area riservata**: "Solo per i membri" si sceglie anche dalla scheda dell'articolo o della pagina. Chi non è entrato non
  trova più i contenuti riservati, nemmeno il titolo, nella ricerca, nel blog, nelle categorie, nei tag, negli archivi, nei
  feed, nella sitemap, in llms.txt e nei widget degli articoli; un membro collegato sì.
- **Copia del sito altrove**: anche su Google Drive e su Dropbox, collegati con un clic.
- **Copia del sito**: il ripristino è pulito, i file che stanno nel sito ma non nella copia vanno in
  `aggiornamenti/scarto/ripristino-<data>/` (si possono riprendere); con due siti nello stesso database e prefissi che
  cominciano uguale la copia prende solo le tabelle sue.
- **Scegli dai Media dappertutto**: in ogni campo del pannello che chiede un'immagine (articoli, SEO, popup, social,
  newsletter, eventi, vetrina, corsi, stile del sito) c'è il pulsante per prenderla dai Media o caricarla.
- **Cinque widget nuovi** (92 in tutto): il menu del ristorante (sezioni, prezzi, allergeni con la legenda, menu del giorno e
  sezioni che escono solo in certi giorni), la richiesta di prenotazione di un tavolo o di un alloggio (l'email arriva
  all'attività, le richieste si leggono in Invii dei form), la galleria con filtri, loghi e testo scorrevoli, il contenuto
  esterno con il consenso (Calendly, Spotify, Google Calendar, Typeform; Booking.com con il pulsante, perché non si lascia
  mettere dentro un altro sito).
- **Widget migliorati**: la mappa con il pulsante "Come arrivare", il video che parte da solo senza audio e si ripete, la tabella
  con grassetto, corsivo e link nelle celle, la categoria di Ultimi articoli e della Griglia scelta da una tendina.
- **Form**: i campi Ora e File. Il file si controlla sul contenuto vero, si salva fuori dal sito pubblico, arriva allegato
  all'avviso e lo scarica solo l'amministratore; si cancella con l'invio e con Cancella dati.
- Corretto: con il sito in una cartella, dopo l'invio di un form si finiva alla radice del dominio.
- Corretto: con dati negli addon Eventi con iscrizione o Prenotazioni, il ripristino di una Copia del sito finiva "con errori"
  e lo snapshot che il pannello fa prima di un aggiornamento non si sarebbe ripristinato (errore MySQL 3105 sulle colonne
  calcolate dal database). Le copie e gli snapshot fatti dalla 2.1.0 in poi si ripristinano interi; quelli fatti prima con
  quei dati restano da rifare.
- **Un manuale d'uso solo**, in un PDF, con le novità di questa versione.
- **Un file solo da caricare**: l'aggiornamento arriva in un pacchetto unico, con codice e pagine insieme.
- Corretti: la griglia della pagina dei Corsi al telefono, l'indirizzo dell'immagine della newsletter in un sito in una
  cartella (la cartella usciva due volte).

## 2.0.3 (31 ottobre 2026) - L'immagine per i social, come la vogliono i social

- **Segnalazione di Danilo**: su LinkedIn l'anteprima del sito di plumeCMS usciva senza immagine, mentre altri siti sullo stesso
  hosting funzionano. La testa delle pagine ora dice dell'immagine quello che dicono quei siti:
  - le barre doppie in un indirizzo incollato a pezzi (`https://sito.it//uploads/...`) diventano una;
  - `og:image:secure_url` (con https) e `twitter:image`, oltre a misure, tipo e descrizione dell'immagine (dalla 2.0.0);
  - il prefisso di Open Graph sul tag `<html>` (`prefix="og: https://ogp.me/ns#"`).
- Le pagine non cambiano a vista.

## 2.0.2 (31 ottobre 2026) - I link scritti a mano

- **Pulsanti che non portavano da nessuna parte** (segnalazione di Danilo sui pulsanti dell'Apertura). Un link scritto come lo
  scrive una persona, `laser-coaching`, `chi-sono/`, `www.sito.it` o `sito.it/pagina`, veniva scartato in silenzio e il pulsante
  puntava a `#`: il clic restava sulla pagina. C'era da sempre, in tutti i widget con un link (pulsanti, inviti, riquadri,
  immagini, menu dei widget). Ora `www.sito.it` e `sito.it/pagina` diventano `https://...`, un nome di pagina diventa la pagina del
  sito (`/laser-coaching`). Restano rifiutati, come prima, i link pericolosi (`javascript:`, `data:` e simili).
- Controllati i pulsanti dell'Apertura in tutte le undici disposizioni, con i sei effetti della foto, al computer e al telefono,
  senza tema e con Cosmo, Aria e Bottega: ogni pulsante è libero (niente velo, foto o onda sopra) e il clic porta alla pagina giusta.

## 2.0.1 (30 ottobre 2026) - Il pannello al telefono

- **Al telefono il pannello non scorre più di lato.** Prima 29 pagine su 70 erano più larghe dello schermo (Articoli,
  Pagine, Popup, Categorie, Media, Download, Utenti, Newsletter, Form, Flussi, Statistiche, Social, Header e footer, Menu,
  Versioni, SEO, Il mio accesso...): una tabella o una fila di schede allargava tutta la pagina, testi compresi. Ora la
  colonna del contenuto resta larga quanto lo schermo, le tabelle larghe scorrono dentro il loro riquadro, e filtri,
  schede e campi vanno a capo.
- Provato su tutte le pagine del menu e su venti pagine interne (schede di articoli, form, prodotti, campagne, addon), a
  390, 768 e 1280 pixel: nessuna pagina scorre di lato. Al computer le pagine sono identiche a prima, al pixel.
- Cambia solo il foglio di stile del pannello (`admin/assets/css/admin.css`): sito, dati e addon non cambiano.

## 2.0.0 beta (30 ottobre 2026) - Gli addon

La prima versione con gli **addon**: funzioni in più che arrivano con plumeCMS e si accendono solo sul sito che le vuole.
È una **beta**: tutto è provato (prove nel browser, attacchi, installazione e aggiornamento), ma gli addon sono nuovi e
aspettano di essere usati su siti veri. La 1.11.0 non è uscita: le sue correzioni sono qui sotto.

**Impostazioni > Addon** (nuova pagina, solo amministratore): una carta per addon con Accendi e Spegni. Un addon acceso
aggiunge le sue voci al menu e i suoi widget all'editor; spento sparisce dal sito, ma i suoi dati restano e tornano quando lo
si riaccende. Gli aggiornamenti non accendono e non spengono niente.

Gli otto addon:

- **Prenotazioni e appuntamenti**: servizi con durata e prezzo, persone con i loro orari, ferie e giorni chiusi. Chi visita
  sceglie servizio, persona, giorno e ora fra i posti davvero liberi (mai due prenotazioni sullo stesso posto, anche nello
  stesso istante), riceve la conferma per email con il link per il calendario e quello per annullare o spostare, e un
  promemoria il giorno prima. Nel pannello: agenda del giorno e della settimana, prenotazioni da confermare, annullare o
  segnare "non presentato". Widget **Prenota**.
- **Eventi con iscrizione**: eventi con posti limitati, iscrizione con più posti per persona, lista d'attesa che scorre da
  sola quando qualcuno si cancella, email di conferma e promemoria, presenze, esporta CSV, email a tutti gli iscritti.
  Widget **Iscrizione all'evento** ed elenco degli eventi con i posti rimasti.
- **Raccolta recensioni**: dopo una prenotazione svolta (o da un elenco di email scritto a mano) parte "Com'è andata?" con
  un link personale a stelle e testo; le recensioni si approvano nel pannello ed escono nel widget **Recensioni dal sito**.
  A chi dà 4 o 5 stelle si può proporre anche la recensione su Google.
- **Vetrina prodotti con ordini**: catalogo con foto, varianti (taglia, colore), prezzi scontati, scorte e categorie;
  carrello e richiesta d'ordine con spedizione o ritiro. Si paga con bonifico, alla consegna o con il proprio link PayPal.me
  o Stripe: nessuna chiave segreta nel sito. I prezzi li calcola sempre il server. Ordini con stati (nuovo, pagato,
  spedito, consegnato, annullato) e un'email al cliente a ogni cambio. Widget **Prodotti** e **Carrello**.
- **Statistiche delle visite senza cookie**: visite, visitatori, pagine più viste, da dove arrivano, campagne, telefono o
  computer. Niente cookie, nessun indirizzo IP salvato, robot e amministratori esclusi: non serve il banner dei cookie.
  Riquadro nella dashboard e testo pronto per l'informativa privacy.
- **Area riservata (membri)**: account del sito (separati da quelli del pannello) con iscrizione, conferma email, accesso,
  password dimenticata, profilo e cancellazione. Pagine, articoli e file di Download si possono riservare ai membri.
  Widget **Accesso membri**.
- **Corsi online** (accende anche l'Area riservata): corsi con moduli e lezioni in ordine, testo, video (YouTube, Vimeo o
  caricato) e materiali; corsi aperti a tutti i membri o assegnati; avanzamento, "riprendi da dove eri" e certificato con
  codice di verifica. Widget **Elenco corsi**.
- **Copia del sito altrove**: ogni notte la copia del sito va su uno spazio esterno (FTP, FTPS, WebDAV, quindi anche
  Nextcloud e molti cloud; SFTP dove il server ha l'estensione ssh2), tenendo le ultime copie. Password salvata cifrata,
  "Prova la connessione", "Manda una copia adesso", registro degli invii. Google Drive e Dropbox non ci sono ancora.

Download e form:

- **File riservati**: in Download, la spunta **Riservato** al caricamento (o il pulsante "Rendi riservato"). Un file
  riservato non ha un indirizzo pubblico: si scarica solo con un **link personale** firmato, che vale 30 giorni (si cambia
  in Download). Sotto ogni file riservato, "Chi l'ha ricevuto", con i download di ognuno.
- **Form > Dopo l'invio > Dopo la conferma, dai questo file**: chi compila il form riceve il file riservato per email dopo
  aver confermato l'indirizzo, e lo trova anche nella pagina di conferma ("Ecco il tuo file", pulsante Scarica). Chi è già
  nella lista lo riceve subito per email; senza la conferma, il pulsante esce anche nella pagina di ringraziamento.
- La conferma di un form ha la sua pagina, non più quella della newsletter.

Immagine per i social:

- In SEO e AEO > Globale SEO, accanto all'immagine social, il pulsante **Scegli dai Media**.
- L'immagine social esce sempre con l'indirizzo intero (prima, una scelta dai Media usciva senza dominio e Facebook,
  WhatsApp e LinkedIn la scartavano), con misure, tipo e descrizione: le anteprime escono già alla prima lettura.

Altre correzioni:

- Il titolo dell'email di conferma della newsletter mostrava `&#039;` al posto dell'apostrofo.
- La pagina Password dimenticata andava in errore quando doveva mostrare un messaggio.

Correzioni della newsletter (Newsletter e form):

- **Un form con la doppia conferma non iscrive più alla newsletter.** Chi compila un form che mette in una lista con la
  conferma riceve un'email che dice per cosa conferma (il titolo del form, per esempio «Scrivici»), non "la newsletter", ed
  entra solo nella lista del form, e solo quando conferma. Il modulo della newsletter funziona come prima.
- **I flussi "Compila un form" non si perdono più** quando il form chiede la conferma: partono quando la persona conferma
  (prima, se il cron passava prima della conferma, il flusso non partiva mai più).
- **Il limite di email ogni ora conta tutte le email del sito**: conferme, avvisi dei form, flussi e campagne, non solo le
  campagne. Nuova casella in Newsletter, Impostazioni: **Email al massimo al giorno** (0 = nessun limite). Sotto le due
  caselle si legge quante email sono partite nell'ultima ora e nelle ultime 24 ore.
- **Email con il mittente giusto anche senza server SMTP**: con `mail()` di PHP le email partono dall'indirizzo del
  mittente scritto in Impostazioni, Posta in uscita (prima partivano senza mittente e finivano spesso nello spam). Ogni email
  ha anche la versione in testo semplice, per i programmi di posta che non mostrano l'HTML.
- **"Cancella dati" cancella tutto**: oltre all'iscrizione, anche aperture e clic, i passaggi nei flussi e gli invii dei
  form con quell'email. L'indirizzo resta in una lista di esclusione (come impronta, non leggibile): un'importazione non lo
  rimette dentro. Se la persona si iscrive di nuovo da sola e conferma, torna dentro.
- **Importazione**: i contatti importati hanno la fonte "import" e la data del consenso del file (colonna CONSENSO), o
  nessuna data; chi è uscito, è rimbalzato o ha chiesto di cancellare i dati si salta. I flussi automatici non partono per
  gli importati, a meno della spunta **Fai partire i flussi per gli importati**.
- **Indirizzi che non esistono**: se il server di posta rifiuta un indirizzo per sempre, l'iscritto diventa **rimbalzato**
  e non riceve più niente (né campagne né flussi). Un indirizzo che dà tre errori temporanei di fila resta attivo ma si
  segnala in cima a Iscritti e liste ("Vedi chi", filtro "Con 3 errori di fila"). Nuovo riquadro "rimbalzati" e filtro
  Rimbalzati.
- Un form con la conferma che riceve troppe richieste dallo stesso indirizzo in un'ora ora lo dice ("Hai già inviato diverse
  richieste"), invece di ringraziare senza mandare l'email.
- Una volta al giorno il cron toglie chi non ha confermato l'iscrizione da più di 30 giorni dalla scadenza del link, e il
  registro delle email più vecchio di 60 giorni.
- Per chi fa moduli: alla conferma riuscita parte l'evento `newsletter_confermata` con l'iscritto, la lista e il form
  d'origine (`newsletter_conferma_esito()`, manuale tecnico 15.5).

## 1.10.0 (30 ottobre 2026) - Sette widget per le attività

- **Scelti da Danilo** fra le proposte dell'analisi dei widget. Nell'editor, linguetta Widget:
  - **Orari di apertura**: i giorni con gli orari e, in alto, "Aperto ora, chiude alle 19:00" o "Chiuso ora, apre domani
    alle 9:00", che si aggiorna da solo; ferie e chiusure con le date; gli orari anche nei dati per Google.
  - **Barra contatti (telefono)**: una fascia fissa in fondo allo schermo, di solito solo sul telefono, con Chiama,
    WhatsApp, Indicazioni e Scrivici.
  - **Recensioni**: il voto medio con le stelle, le recensioni scritte a mano e il pulsante "Lasciaci una recensione".
  - **Prima e dopo**: due foto sovrapposte con una linea da trascinare (anche con il dito e con la tastiera).
  - **Squadra**: le persone con foto, ruolo, due righe e i contatti; senza foto, le iniziali.
  - **Slider**: più aperture che scorrono, ognuna con foto, titolo, testo e pulsante.
  - **Eventi**: in ordine di data, quelli passati spariscono da soli; anche nei dati per Google.
- I widget ora sono 87. Nessun servizio esterno: le recensioni si copiano a mano (Google non mostra comunque le stelle che
  un'attività scrive di sé), gli orari e gli eventi stanno nel widget.

## 1.9.2 (30 ottobre 2026) - Sezioni dell'editor più riconoscibili

- **Segnalazione di Danilo**: nel pannello di sinistra dell'editor le sezioni (Titolo, Occhiello, Testo sotto il titolo...)
  e i gruppi dentro di loro (Tipografia, Ombra, Sfondo, Bordo) si distinguevano poco. Ora l'intestazione di ogni sezione ha
  un fondo suo; la sezione aperta ha il bordo e una striscia a sinistra del colore d'accento del pannello, e il nome in
  quel colore. Ogni gruppo è un riquadro con il suo nome in testa, in maiuscoletto. Vale per tutti e quattro gli aspetti.
  Cambia solo il foglio di stile dell'editor (`admin/assets/editor/editor.css`): il sito e i dati non cambiano.

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

## 1.4.0 (28 ottobre 2026) - Il sito si prova, il pannello si sceglie

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
