# SEO e AEO: il pannello

Voce di menu **SEO e AEO** (solo amministratore). Motore in `includes/seo.php`, pagina `admin/seo.php`,
chiamate `admin/seo-api.php`, tabelle in `sql/seo.sql` (le crea da sola `seo_installa()` al primo uso).
Impostazioni in `newdf_settings`: `seo_config` (JSON di tutto), `seo_indexnow_chiave`, `seo_google_sa`
(la chiave del service account, mai mostrata), `seo_google_token` (cache del token).

Regola di fondo: **con i valori di partenza il sito esce identico a prima.** Titoli, robots, canonical,
robots.txt, sitemap, llms.txt e JSON-LD non cambiano finche non si tocca qualcosa. Il contratto dei 1.005
indirizzi (`verifica_contratto.py`) resta a zero differenze.

## Le schede

| Scheda | Cosa c'e |
|---|---|
| Panoramica | tutti i contenuti pubblicati con il punteggio SEO, ordinabili; chi manca di In breve, FAQ, descrizione, immagine |
| Motori di ricerca | verifica di Google, Bing, Yandex, Pinterest; IndexNow; service account Google; sitemap in Search Console |
| Copertura | ispezione degli indirizzi in Search Console: indicizzato, scansionato non indicizzato, escluso |
| Prestazioni | clic, impressioni, CTR e posizione degli ultimi 28 giorni, per giorno, per pagina, per ricerca |
| Analisi e tag | Google Analytics 4, Tag Manager, Meta Pixel, Clarity, codice libero, banner del consenso |
| Globale SEO | formato dei titoli, descrizione e immagine predefinite, meta robots, indicizzazione per tipo, robots.txt, crawler AI, sitemap |
| Schema e AEO | organizzazione e persona del JSON-LD, interruttori dei nodi, soglie AEO, anteprima JSON-LD, llms.txt |
| Redirect e 404 | monitor dei 404 con "Crea redirect", elenco dei redirect (la stessa tabella dei pacchetti) |
| Registro | ogni chiamata a IndexNow e Google con esito e codice |

## Google Search Console: verifica della proprieta

1. Vai su search.google.com/search-console, **Aggiungi proprieta**, scegli **Prefisso URL** e scrivi
   `https://www.danilofailli.it/`.
2. Fra i metodi di verifica scegli **Tag HTML**. Google mostra una riga tipo
   `<meta name="google-site-verification" content="abc123...">`: copiala tutta.
3. Nel pannello, SEO e AEO, scheda **Motori di ricerca**, riquadro Verifica della proprieta: incolla nel
   campo Google (va bene il tag intero o solo il codice). **Salva i codici**.
4. Torna su Google e premi **Verifica**. Il tag e gia nel `<head>` di ogni pagina.

Se preferisci il metodo **File HTML**: Google ti fa scaricare `google1234abcd.html`. Non serve caricarlo:
scrivi il nome del file nel campo Google e il sito risponde da solo a quell'indirizzo con il contenuto giusto.

Se il sito e gia verificato in Search Console (lo era con WordPress e Rank Math), il tag di prima non vale piu
con il sito nuovo: rifai la verifica con il tag di questo pannello, cosi la proprieta non si perde.

## Bing Webmaster Tools

La via corta: su bing.com/webmasters scegli **Importa da Google Search Console**. Bing copia proprieta e
sitemap, senza codici. Altrimenti **Aggiungi sito manualmente**, metodo **Meta tag HTML**: copia il codice di
`msvalidate.01` nel campo Bing del pannello. Il metodo del file XML funziona anche lui: incolla il contenuto
del file BingSiteAuth.xml nel campo apposito e il sito lo serve in radice.

Bing non va "pingato": riceve ogni indirizzo via IndexNow (sotto).

## IndexNow

IndexNow e un protocollo aperto: quando una pagina esce o cambia, il sito avvisa i motori con una chiamata, e
loro la leggono subito invece di aspettare la prossima scansione. Lo usano **Bing, Yandex, Seznam, Naver, Yep**
e si passano le notifiche a vicenda. **Google non lo usa.**

Come si accende: scheda Motori, riquadro IndexNow, **Genera la chiave**. Il pannello crea una chiave casuale e
il sito la pubblica in `https://www.danilofailli.it/<chiave>.txt` (il protocollo chiede solo questo, nessuna
registrazione). Da quel momento:

- ogni articolo, pagina o aforisma **pubblicato o modificato** dal pannello va nella coda `newdf_seo_coda` e,
  con "Invio immediato" acceso, parte subito;
- la coda si svuota anche dal cron: `cron-newsletter.php` ogni 5 minuti e `cron-daily.php` alle 06:00;
- **Invia tutta la sitemap adesso** mette in coda tutti gli indirizzi della sitemap e li manda in una chiamata
  (il protocollo accetta fino a 10.000 indirizzi per volta): da fare una volta dopo aver generato la chiave;
- il **Registro** mostra ogni esito. Codice 200 o 202 = ricevuto. 403 = la chiave non corrisponde al file;
  422 = indirizzi non del dominio; 429 = troppe richieste.

Rigenerare la chiave rende invalida quella vecchia: Bing la rilegge dal file alla prossima notifica.

## Google: il service account (Copertura, Prestazioni, sitemap)

Per leggere i dati di Search Console dal pannello serve un **service account**, un utente tecnico di Google
Cloud con una chiave JSON. La chiave si salva nel database del pannello e non si vede piu (il pannello mostra
solo l'email). I passi, uno per uno:

1. Apri console.cloud.google.com con l'account Google che ha Search Console. **Crea progetto**, nome a piacere
   (per esempio `danilofailli-seo`).
2. Menu a sinistra, **API e servizi**, **Libreria**: cerca **Google Search Console API** e premi **Abilita**.
3. Menu, **IAM e amministrazione**, **Service account**, **Crea service account**. Nome a piacere. Alla
   richiesta dei ruoli non serve niente: **Fine**.
4. Clicca sul service account appena creato, scheda **Chiavi**, **Aggiungi chiave**, **Crea nuova chiave**,
   tipo **JSON**, **Crea**. Il browser scarica un file `.json`.
5. Apri il file con un editor di testo (Blocco note, TextEdit), seleziona tutto e copia. Nel pannello, scheda
   Motori, riquadro Google: incolla nel campo **Chiave JSON** e **Salva la chiave**. Il pannello mostra l'email
   del service account, tipo `nome@progetto.iam.gserviceaccount.com`.
6. Vai in Search Console, **Impostazioni**, **Utenti e autorizzazioni**, **Aggiungi utente**: incolla quella
   email, autorizzazione **Completa** (serve per l'ispezione degli URL). Aggiungi.
7. Nel pannello controlla che **Proprieta di Search Console** sia scritta come in Search Console:
   `https://www.danilofailli.it/` (prefisso URL, con la barra finale) oppure `sc-domain:danilofailli.it`
   (proprieta Dominio). Premi **Prova il collegamento**: deve dire quali proprieta vede.

Se dice 403: il service account non e stato aggiunto in Search Console oppure l'API non e abilitata.
Se dice 404: la proprieta e scritta diversa da come sta in Search Console.
Se dice che il server non raggiunge Internet: la rete dell'hosting e chiusa verso Google, da chiedere a Hostcare.

Il token si firma in casa con OpenSSL (JWT RS256) e si scambia con `oauth2.googleapis.com`: nessuna libreria.

### Copertura

Il problema storico del sito sono le pagine "Scansionata, al momento non indicizzata". La scheda Copertura
chiede a Search Console il verdetto di ogni indirizzo (`urlInspection/index:inspect`) e lo salva in
`newdf_seo_url`: verdetto, stato di copertura in italiano, ultima scansione di Google, canonical scelto da Google,
stato in robots.txt. Un indirizzo alla volta o a lotti (10, 25, 50, 100): i lotti partono dagli indirizzi mai
controllati o controllati da piu tempo. La quota di Google e di **2000 ispezioni al giorno**: il contatore e in
alto. I filtri in alto dividono indicizzati, scansionati non indicizzati, esclusi.

### Prestazioni

Clic, impressioni, CTR e posizione degli ultimi 28 giorni (Google consegna i dati con 2-3 giorni di ritardo),
con un grafico per giorno e due tabelle, per pagina e per ricerca. I dati restano in cache un'ora; il pulsante
**Rileggi adesso** li chiede di nuovo.

### Indexing API (spenta per scelta)

Google ha una API per chiedere l'indicizzazione di un indirizzo, ma la ammette **solo** per pagine di tipo
JobPosting (offerte di lavoro) e BroadcastEvent (dirette). Su un blog funziona a volte, ma fuori dalle regole.
L'interruttore c'e, spento, con la nota. Per accenderla servono l'API **Web Search Indexing API** abilitata nel
progetto e il service account come **proprietario** in Search Console. Quota: 200 notifiche al giorno.

### Ping della sitemap

Il vecchio indirizzo `google.com/ping?sitemap=` e stato chiuso da Google nel 2023: non va piu chiamato. La
sitemap si invia **una volta** in Search Console (Sitemap, Aggiungi nuova sitemap, `sitemap.xml`) e Google la
rilegge da solo. Il riquadro "Sitemap e Google" mostra quando l'ha letta e quanti indirizzi ha trovato.

## Analisi e tag

Incolla gli ID: **GA4** (`G-XXXXXXXXXX`, da Analytics, Amministrazione, Flussi di dati), **Tag Manager**
(`GTM-XXXXXXX`), **Meta Pixel** (numero), **Clarity** (ID progetto). Il sito carica gli script nel `<head>`.
Tre campi di codice libero: nel `<head>`, dopo `<body>`, prima di `</body>`. Viaggiano protetti fino al pannello
(`campi-protetti.js`), cosi ModSecurity non li ferma.

**Consenso.** Il sito non aveva un banner dei cookie. Con la scelta "Carica i tag solo dopo il consenso" il sito
mostra un banner leggero fatto in casa (Accetta / Rifiuta, scelta ricordata nel browser con `localStorage`) e
gli script partono **solo dopo il si**. Il banner compare solo se c'e almeno un tag. Se un giorno il consenso lo
raccoglie un altro strumento (per esempio un popup del pannello), basta che chiami `seoTagAvvia()` dopo il si e
scegliere "Carica subito, senza banner".

## Globale SEO

- **Titoli**: formato per tipo con `%titolo% %sep% %sito%`. Partenza: `Titolo - Danilo Failli - D.O.C.`, quello di
  oggi. Il titolo per Google scritto nel singolo contenuto vince sempre.
- **Descrizione e immagine predefinite** per le pagine che non ne hanno; account X per `twitter:site`.
- **Meta robots avanzato**: `max-snippet:-1, max-image-preview:large, max-video-preview:-1` (oggi acceso).
- **Indicizzazione per tipo**: Automatica = la regola di oggi. Forzare index o noindex vale per tutto il tipo.
- **robots.txt**: il predefinito e `extra_robots()` di prima. Scrivendo nel campo, il sito serve quel testo;
  "Ripristina" torna al predefinito. Sotto, un interruttore per ogni **crawler AI** (GPTBot, OAI-SearchBot,
  ChatGPT-User, ClaudeBot, Google-Extended, PerplexityBot, CCBot, Bytespider, Applebot-Extended,
  meta-externalagent) con la spiegazione di cosa fa: ogni interruttore aggiunge `User-agent / Disallow: /`.
  Per essere citati dalle IA conviene lasciare passare i bot di ricerca (OAI-SearchBot, PerplexityBot).
- **Sitemap**: pagine, categorie, immagine in evidenza (sitemap immagini), sitemap divise per tipo con indice
  (`/sitemap.xml` diventa indice di `sitemap-articoli.xml`, `sitemap-pagine.xml`, `sitemap-categorie.xml`).

## Schema e AEO

I nodi fissi del JSON-LD (WebSite, organizzazione, persona) si scrivono qui: erano fissi in `sito_jsonld_fissi()`,
ora `seo_jsonld_fissi()` li legge dal pannello con gli stessi valori di partenza. Interruttori per WebSite,
SearchAction, organizzazione, persona, Article, FAQPage, BreadcrumbList, Speakable. HowTo non c'e perche nessun
contenuto ha passi strutturati come dati.

**Risposte dirette.** Nella scheda di ogni contenuto ci sono gia **In breve** (il riquadro in cima, che diventa
`abstract` nell'Article) e le **Domande frequenti** (FAQPage). Qui si impostano le soglie: parole massime del
primo paragrafo (60), parole di In breve (40-60). La Panoramica segnala chi non le rispetta.

**Anteprima JSON-LD**: scrivi un indirizzo del sito e il pannello mostra il grafo che quella pagina emette,
controllato (ogni nodo ha `@type`, i riferimenti `@id` esistono), con i link al Test dei risultati avanzati di
Google e allo Schema Markup Validator (lavorano sul sito pubblico).

**llms.txt e llms-full.txt**: `/llms.txt` e calcolato dal database (formato llmstxt.org) e si puo sostituire con
un testo scritto a mano; `/llms-full.txt` aggiunge il testo intero dei pillar e degli articoli indicizzati.

## Controllo SEO per contenuto

`seo_analisi(array $contenuto): array` da punteggio 0-100 e voci: keyword nel titolo, nella descrizione,
nell'indirizzo, nel primo 10% del testo, nei sottotitoli; densita della keyword (obiettivo 1,8%) e delle correlate
(1,5%); titolo e descrizione in caratteri e pixel stimati; immagine in evidenza con alt e alt delle immagini nel
testo; link interni (almeno 2) ed esterni (le fonti); lunghezza (pillar 3500, altri 1000); leggibilita (frasi e
paragrafi lunghi); trattini lunghi; primo paragrafo, In breve, domande frequenti. Ogni voce ha un peso; una voce
in attenzione vale meta.

Dove si vede: nella scheda classica del contenuto (riquadro **Controllo SEO** sotto "SEO e Google", con il
pulsante **Ricontrolla** che rilegge i campi senza salvare) e nella Panoramica. Per l'editor visuale c'e
`admin/seo-api.php?azione=analisi` (POST con i campi e il token CSRF): risponde con punteggio, voci e l'HTML del
riquadro.

## Redirect e 404

Il front controller registra ogni 404 vero (niente sonde, niente file di sistema) in `newdf_seo_404` con
conteggio, provenienza, prima e ultima volta. Il pulsante **Crea redirect** compila il modulo; il redirect va
nella stessa tabella `newdf_redirect` dei pacchetti e dei cambi di slug, e l'indirizzo viene notificato a
IndexNow. Dall'elenco si spegne, riaccende o elimina ogni redirect.

## Tabelle

`newdf_seo_coda` (coda IndexNow), `newdf_seo_log` (registro, si pulisce dopo 90 giorni), `newdf_seo_url`
(copertura), `newdf_seo_404` (monitor, tiene le ultime 3000 righe). SQL leggibile in `sql/seo.sql`.

## Dove il sito legge il pannello

- `includes/sito-seo.php`: `sito_meta()` passa da `seo_titolo()`, `seo_robots_tipo()`, descrizione e immagine
  predefinite; `sito_robots_meta()` da `seo_robots_meta()`; `sito_jsonld_fissi()` da `seo_jsonld_fissi()`;
  `sito_jsonld_script()` filtra i nodi con `seo_jsonld_filtra()`.
- `sito/index.php`: `sito_html()` passa il documento finito da `seo_inietta_html()` (verifica, tag, codice
  libero, banner); `robots.txt`, `sitemap.xml`, `llms.txt` da `seo_robots_txt()`, `seo_sitemap()`, `seo_llms()`;
  `seo_file_speciale()` serve i file di verifica, `/<chiave>.txt`, `llms-full.txt`, le sitemap divise;
  `sito_non_trovato()` registra il 404.
- `includes/contenuti.php`: `contenuti_salva()` chiama `contenuti_avvisa_motori()` dopo ogni salvataggio.
- `cron-daily.php` (passo 3) e `cron-newsletter.php` svuotano la coda di IndexNow.

Ogni chiamata esterna passa da `seo_http()` (curl o stream, timeout, mai un'eccezione) e torna con un messaggio
in italiano: con la rete chiusa il pannello lo dice e il sito non se ne accorge.
