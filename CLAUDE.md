# plumeCMS

Memoria di progetto. Questo file viene caricato all'avvio di ogni sessione.
Leggilo tutto prima di toccare qualsiasi cosa.

## Cos'è

plumeCMS è un CMS in PHP e MySQL con un editor visuale "come Elementor" al
centro. Si carica nella radice di un sito (o in una sottocartella), si apre
il browser, un installatore in cinque passi chiede database, nome del sito e
amministratore, e il CMS è pronto.

Proprietario: Danilo Failli. Il nome l'ha scelto lui il 26 ottobre 2026,
dopo "Officina" (provvisorio) e "CarbonaraCMS". Lingua del prodotto, dei
documenti e dei messaggi: italiano.

Nasce dal pannello del sito danilofailli.it (il "publisher" in `/new/`), che
il 26 ottobre 2026 è stato diviso in un nucleo generico e un modulo con le
cose di quel sito. **Questo progetto contiene solo il nucleo.** Il modulo
`moduli/danilofailli/` e tutto ciò che riguarda quel sito (articoli, pillar,
social, test del burnout) restano nel repository `danilofailli.it`, che è la
prima installazione di plumeCMS.

## Struttura

```
cms/            il CMS: è esattamente quello che si carica sul server
  index.php     front controller della radice
  includes/     motore: cms.php (nome, prefisso, base URL), config.php, db.php,
                contenuti, media, editor*, design, modelli, menu-nav, seo,
                sicurezza*, versioni, form, popup, newsletter*, posta, aggiornamenti
  admin/        il pannello
  sito/         il sito pubblico (viste, feed, sitemap, assets)
  api/          endpoint pubblici (nl.php firma HMAC, csp.php rapporti)
  install/      l'installatore e gli esempi .htaccess / Nginx
  moduli/       i moduli del sito che installa (vuota nel prodotto)
  temi/         i temi del sito (motore in includes/temi.php, docs/temi.md)
  docs/         la documentazione del CMS
  VERSIONE, CHANGELOG.md, README.md, INSTALLA.md
tools/          cms-pacchetto.py (costruisce gli zip), pacchetto.py (pacchetti per il
                pannello Aggiornamenti), sicurezza-prova.js (79 prove di attacco)
prove/          prova-install.js (installazione da zero con Playwright), prova-social.js (social ed email del mattino), prova-fine-articolo.js (il riquadro in fondo agli articoli), prova-su-misura.js (il sito su misura), prova-temi.js, prova-pannello-temi.js,
                prova-temi-partenza.js, prova-carica-temi.js (temi), prova-foto-effetti.js (velo ed effetti delle foto), prova-hero.js (le undici aperture),
                foto-sito.js e quanto-foto.py (schermate prima e dopo, al pixel)
esempi/         tema-lavagna/: il tema d'esempio del manuale dei temi (provato, non entra nello zip)
sito-plumecms/  il sito da cui si scarica plumeCMS, fatto con plumeCMS: costruisci.py scrive il pacchetto
                releases/sito/sito-plumecms-<versione>-parte-<k>-di-<n>.zip, ognuna sotto 1900 KB, da caricare in ordine
tools/manuali-pdf.js  i tre manuali in PDF (releases/manuali/); tools/manuale-uso-schermate.js le schermate del manuale d'uso;
tools/consegna.py     lo zip unico da dare a Danilo (releases/consegna/)
```

Stato, decisioni, contesto e diario non stanno qui: stanno nel second brain
(`danilofaillii/second-brain`, cartella `plumecms/`), unica copia.

## Documenti

**Regola di Danilo (29 ottobre 2026): con plumeCMS restano solo due documenti**, il manuale d'uso e il manuale dei temi.
Sono gli unici in `cms/docs/`, nello zip, nel sito di plumeCMS e nella pagina Download.

0. `cms/docs/manuale-uso.md`: il manuale d'uso per chi usa plumeCMS (31 capitoli, esempio, schermate con i passi
   numerati, checklist). Le schermate le fa `tools/manuale-uso-schermate.js` eseguendo gli esempi su un sito di prova
   (kit Attività, in `/home/studioprova/public_html`): **quando cambia il pannello si rifanno le schermate e si
   ricontrolla il capitolo**. Il PDF deve restare sotto 1900 KB (oggi 1,8 MB).
   `cms/docs/manuale-temi.md`: il manuale per chi costruisce un tema, con il tema d'esempio Lavagna.
1. `docs-sviluppo/` (solo nel repository, mai nello zip): il manuale tecnico (`manuale-tecnico.md`, tutto il CMS letto dal
   codice, con `file:riga`: **quando si cambia il codice si aggiorna anche lui**) e i documenti vecchi (`cms.md`,
   `cms-moduli.md`, `editor-pagine.md`, `sicurezza.md`, `temi.md`, `seo.md`...), superati in alcuni punti: se dicono una
   cosa diversa dal manuale tecnico, vale il manuale (appendice A).

## Come si lavora

- **Costruire il prodotto**: `python3 tools/cms-pacchetto.py` scrive
  `releases/cms/plumecms-<VERSIONE>.zip`. Rifiuta: errori di sintassi PHP
  (controllati con `short_open_tag` acceso), il prefisso `newdf_` scritto nel
  codice fuori da `cms.php` e `db.php`, il dominio danilofailli.it nel codice.
- **Regola di Danilo (27 ottobre 2026): ogni funzionalità nuova aggiorna tre cose, sempre, senza che lo chieda.**
  1. *Il progetto*: codice, prove, `cms/CHANGELOG.md`, `cms/VERSIONE`, il manuale tecnico (e quello dei temi, se tocca i temi),
     il manuale d'uso se cambia quello che vede chi usa il pannello (capitolo, passi e schermate con `tools/manuale-uso-schermate.js`).
  2. *I PDF*: `node tools/manuali-pdf.js`, dopo aver aggiornato i manuali.
  3. *Il sito di plumeCMS* (`sito-plumecms/costruisci.py`): `NOVITA`; un blocco nella pagina Funzionalità con la schermata
     (pagina aggiunta a `sito-plumecms/schermate.js`, immagine in `USATE`); home, Domande frequenti o Installazione se la
     funzionalità le cambia; i numeri scritti nei testi (widget, attacchi). Poi le schermate rifatte sul sito di prova e
     `--schermate`, le parti ricostruite, `prove/prova-sito-plumecms.js` con un controllo per il blocco nuovo.
  Dalla 1.2.5 il numero di versione nelle pagine è automatico (`{versione:plumecms-ultima}`): per una versione del CMS
  senza pagine nuove Danilo carica solo i due zip da Download; le parti del sito servono quando cambiano le pagine.
  Ordine dei comandi: `python3 tools/cms-pacchetto.py --aggiornamento`, `node tools/manuali-pdf.js`,
  `python3 sito-plumecms/costruisci.py`, `python3 tools/consegna.py`. **Regola di Danilo (28 ottobre 2026): a lui si consegna
  un solo zip**, `releases/consegna/plumecms-<versione>-consegna.zip`, con dentro l'aggiornamento e lo zip completo del CMS,
  le parti del sito, i tre PDF e un LEGGIMI con l'ordine di caricamento. Non più i file uno per uno.
- **Pacchetto di aggiornamento** per un'installazione esistente:
  `python3 tools/cms-pacchetto.py --aggiornamento`. Si carica dal pannello,
  Impostazioni, Aggiornamenti.
- **Versione**: `cms/VERSIONE` (oggi 1.10.0) e una voce in `cms/CHANGELOG.md` a ogni
  zip con contenuto diverso. Mai cambiare il contenuto di una versione già data.
- **Prove** prima di dire finito:
  - `php -n -d short_open_tag=1 -l` su ogni file PHP toccato, `node --check` sui js;
  - installazione da zero: `prove/prova-install.js` (vedi `prove/LEGGIMI.md`);
  - attacco: `CMS_DIR=<cartella del CMS installato> node tools/sicurezza-prova.js <base> <utente> <password> [prefisso-pannello]`,
    poi svuotare `<prefisso>sicurezza_tentativi`;
  - le pagine si provano nel browser come un utente (Playwright), non con un `curl` che risponde 200.
- **PHP come sui server veri**: Hostcare (il primo cliente) ha PHP 8.4.27, MySQL 8.0.46
  in modalità stretta, Apache 2.4 con cPanel e ModSecurity. In locale si prova con
  PHP 8.4.27 in Docker: `dockerd`, immagine `php:8.4-cli` con `pdo_mysql`.
- Niente librerie esterne nel CMS (SortableJS e Quill sono serviti in casa).
- Niente credenziali nel repository né in chat.
- Italiano ovunque, niente trattini lunghi, niente caratteri a 4 byte nei file
  (si scrivono come entità HTML).

## Decisioni prese

Le decisioni complete, con data e perché, stanno nel second brain, `plumecms/decisioni.md`. In breve:

- **L'editor è la copia di Elementor** (25 ottobre 2026): controlli di gruppo,
  schede Stile e Avanzate per ogni elemento, colori e caratteri globali, Theme
  Builder per header e footer, trenta widget di Elementor free e Pro, 252 icone
  disegnate in casa. Ricreato dall'analisi del codice di Elementor Pro 4.3.2.
- **CMS installabile, una sola base di codice** (26 ottobre 2026): nucleo generico
  più moduli, installatore dal browser, prefisso delle tabelle e base URL configurabili.
- **Versioni di ogni modifica dell'editor** in una tabella unica, con confronto
  leggibile, anteprima e ripristino.
- **Un solo menu "Editor"** nel pannello per tutte le impostazioni dell'editor, con esporta e importa in JSON.
- **A prova di attacco prima di pubblicare** (26 ottobre 2026): audit, blindatura,
  tre giri di attacco con agenti nuovi fino a zero brecce sopra il livello basso.
  Il redattore gestisce solo contenuti, media, categorie e tag.
- **Nome: plumeCMS** (26 ottobre 2026).

## Stato al 28 ottobre 2026

Sito di plumeCMS 1.10.0-r2 (30 ottobre, solo le pagine, il CMS non cambia): in Funzionalità, sotto "Vieni da WordPress?", la sezione
"Chi passa a Plume vive più *leggero.*" con il widget Prima e dopo e due illustrazioni disegnate in SVG da `sito-plumecms/prima-dopo.js`
(persona disperata davanti a un pannello WordPress pieno di avvisi, poi felice davanti al sito plumeCMS; "WordPress" solo come testo, niente
logo). `SITO_REV=r2` dà alle parti un nome diverso da quelle già date. Prova del sito 83/83. Da migliorare nel widget Prima e dopo: con
etichette lunghe, sul telefono si sovrappongono (qui accorciate a "Con WordPress" e "Con plumeCMS").
Fatto: plumeCMS 1.10.0 (30 ottobre): sette widget per le attività scelti da Danilo (Orari di apertura con "Aperto ora" calcolato
dal server e ricalcolato nel browser nel fuso del sito, Barra contatti fissa sul telefono, Recensioni scritte a mano senza dati per Google,
Prima e dopo, Squadra, Slider sul motore del carosello, Eventi che spariscono passati) in `includes/editor-widget-extra-d.php`; 87 widget.
Dati per Google (LocalBusiness, Event) in `<script type="application/ld+json">` dentro il widget. Manuale d'uso capitolo 8 (foto
08-attivita fatta dopo tutti i capitoli: la pagina di prova prende un numero di pagina), tecnico 31; JPEG del manuale a qualità 25
(PDF 1826 KB). Prove: `prova-widget-nuovi.js` 40/40 (sito nuovo e sito aggiornato), attacchi 238/238 (gruppo 26 con
`prove/widget-cattivi.php`), install 37/37 radice e cartella, aggiornamento 1.9.2 -> 1.10.0 13/13 (sito uguale tranne il conto alla
rovescia), sito di plumeCMS 82/82.
1.9.2 (30 ottobre): sezioni e gruppi del pannello dell'editor più riconoscibili (segnalazione di Danilo):
`details.ed-sez` con intestazione piena e, aperta, bordo e striscia d'accento; `.ed-gruppo` a riquadro con il nome in maiuscoletto
(`.ed-controllo:has(>.ed-gruppo)`), solo `admin/assets/editor/editor.css`. Schermate del manuale rifatte (PDF 1884 KB, al limite: la
prossima volta alleggerire). Prove: install 37/37, aggiornamento 1.9.1 -> 1.9.2 13/13, file del pannello 6/6, pannello guardato in Aria e
Lavagna. Analisi dei widget (agente, 80 widget, 15 proposte) consegnata a Danilo il 30 ottobre: nessuna scelta ancora.
1.9.1 (30 ottobre): la voce Form del menu del pannello spostata dentro "Newsletter e form" (richiesta di Danilo),
fra Iscritti e liste e Invii dei form, solo amministratore (`admin/includes/admin-header.php`). Manuale d'uso capitolo 18 e schermata
01-dashboard. Prove: install 37/37, aggiornamento 1.9.0 -> 1.9.1 13/13, menu controllato nel browser. Pagine del sito di plumeCMS
invariate: niente parti nuove.
1.9.0 (30 ottobre): a chi arriva l'avviso di un form (segnalazione di Danilo: non si poteva scegliere). Colonna
`moduli.avviso_a` (aggiunta anche alle tabelle vecchie da `form_schema()`), casella "Manda l'avviso a" in Form > Dopo l'invio, fino a
5 indirizzi validati (`form_avviso_indirizzi()`), un'email per destinatario; vuota = `posta_notifica_email()` come prima. Manuale d'uso
capitolo 18 (schermata 17-avviso), tecnico 15.1. Prove: `prova-form-avviso.js` 13/13 (sito nuovo e sito aggiornato), attacchi 233/233
(gruppo 25), install 37/37 radice e cartella, aggiornamento 1.8.0 -> 1.9.0 13/13 (sito uguale tranne il conto alla rovescia), sito di
plumeCMS 81/81. PDF del manuale d'uso 1877 KB: vicino al limite di 1900.
1.8.0 (29 ottobre): la frase in fondo al sito "Un nuovo sito plumeCMS" con il link a `https://plumecms.danilofailli.it`
(richiesta di Danilo), modificabile in Impostazioni > Sito pubblico (`includes/firma.php`, impostazione `firma_sito`: testo, indirizzo,
acceso); banner "Visita il sito ufficiale plumeCMS" in cima alla dashboard, sempre. `FIRMA_URL` ripete `VERSIONE_SITO_UFFICIALE`: se cambia
uno, cambia l'altro. Manuale d'uso capitoli 1 e 15, tecnico 30. Prove: `prova-firma.js` 14/14 (radice e cartella), attacchi 249/249
(gruppo 24), install 37/37 radice e cartella (la prova ammette ora `plumecms.danilofailli.it` nella home), aggiornamento 1.7.0 -> 1.8.0
13/13 (pagine uguali tranne la riga nuova), sito di plumeCMS 80/80.
1.7.0 (29 ottobre): il sito su misura (richiesta di Danilo, strada A senza intelligenza artificiale):
carta "Su misura" al passo 4 dell'installatore con quattro domande (attività fra dieci, città, fino a sei servizi, pagine), motore
`includes/sito-su-misura.php` che compone il kit dalle risposte, foto finte SVG in `uploads/segnaposto/`, Media > Da sostituire con
Sostituisci (solo admin, sostituisce l'indirizzo in pagine, articoli e modelli), voce dei Primi passi, `admin/sito-su-misura.php` per
ricomporre dalla dashboard finché il tipo di sito è in prova. Manuale d'uso capitolo 2 (e 3, 7), tecnico capitolo 29; JPEG del manuale a
qualità 28 (PDF 1838 KB). `media_carica()` rifiuta un'immagine non riscrivibile che contiene codice (`media_ha_codice()`).
Prove: `prova-su-misura.js` 34/34, attacchi 242/242 (gruppi 23 e 23b, su un sito su misura in prova), install radice e cartella 37/37,
kit 34/34 e 44/44, tipo di sito 39/39, primi passi 19/19, aggiornamento 1.6.4 -> 1.7.0 13/13 (sito uguale tranne il conto alla
rovescia, poi ricomposto su misura dal pannello), sito di plumeCMS in quattro parti 78/78. Attacchi e regressioni fatti da un agente
e rifatti a mano (su misura, attacchi, install, aggiornamento, sito).
1.6.4 (29 ottobre): solo due documenti nel prodotto (decisione di Danilo): manuale tecnico e documenti vecchi
spostati in `docs-sviluppo/`, fuori dallo zip; sito di plumeCMS con due manuali (Documentazione, Download, piede); pannello
(Informazioni, Temi), README e INSTALLA rimandano ai due manuali. L'aggiornamento toglie dai siti i 15 documenti vecchi
(`TOLTI` in `cms-pacchetto.py`), la parte 1 del sito toglie `manuale-tecnico.pdf` dal Download. Il sito ora è in quattro parti.
Prove: install 37/37, aggiornamento 1.6.3 -> 1.6.4 13/13 (in `docs/` restano i due manuali), sito di plumeCMS 77/77 (il manuale
tecnico dà 404).
1.6.3 (28 ottobre, notte): due "Invito all'azione" affiancati alti uguali (segnalazione di Danilo sulla pagina
Scarica, già su plumecms.danilofailli.it): `widget.css`, un Invito da solo nella colonna la riempie. Aggiornamento 1.6.2 -> 1.6.3
13/13, foto del sito uguali tranne il conto alla rovescia, schede 321/321 px.
1.6.2 (28 ottobre, sera): l'indirizzo del sito ufficiale non si scrive più nel pannello (decisione di Danilo:
"non inseriranno più manualmente il dominio, gli verrà passato dagli aggiornamenti"): via il campo della 1.3.1 e `versione_sito_salva()`,
l'impostazione `versione_sito` vecchia si ignora, `CMS_AGGIORNAMENTI_URL` resta solo per le prove. `prova-versione-sito.js` riscritta
(5 controlli, con `prove/settings-cli.php`); `prova-versione.js` e `finto-ufficiale.php` non hanno più le versioni scritte dentro
(28/28 sulla 1.6.2). Schermata 26 del manuale d'uso rifatta. Prove: install 37/37, aggiornamento 1.6.1 -> 1.6.2 13/13 (anche con un
indirizzo salvato a mano, ignorato) e 1.6.0 -> 1.6.2 13/13, sito di plumeCMS 81/81.
1.6.1 (28 ottobre, sera): il sito ufficiale diventa `https://plumecms.danilofailli.it` (decisione di Danilo:
trasferire il sito da duhtron.it/test a un sottodominio suo). `VERSIONE_SITO_UFFICIALE` cambiata; il costruttore rifiuta ancora
`danilofailli.it` nel nucleo ma ammette il sottodominio. Il trasloco si fa con Copia del sito: provato qui (sito in `/test` con
le sei parti, copia ripristinata su un'installazione nuova in radice, 13 pagine uguali, download ok). Prove 1.6.1: install
37/37, versione-sito 7/7, aggiornamento 1.6.0 -> 1.6.1 13/13.
1.6.0 (28 ottobre): Articoli > linguetta "Aggiungi sempre alla fine" (richiesta di Danilo): un riquadro
ombreggiato composto con l'editor visuale che esce in fondo a ogni articolo. Modello di tipo `fine` (`includes/modelli.php`,
uno solo, acceso quando è pubblicato), pagina `admin/fine-articolo.php`, `vista_fine_articolo()` e `vista_chiusura_article()`
in `sito/viste.php` (dentro `<article class="corpo">`, fuori per gli articoli a tutta pagina), `.fine-articolo` in `sito.css`
(i link dell'articolo non colorano più i widget del riquadro). Anteprime dei modelli in bozza solo all'amministratore
(`modelli_anteprima_admin()`, anche header e footer). Corretti il testo d'esempio del kit vuoto (chiave `contenuto`) e tre
emoji scritte nei file. Manuale d'uso capitolo 16 (31 capitoli), tecnico capitolo 28. Prove: install radice e cartella 37/37,
`prova-fine-articolo.js` 41/41 (38 senza l'articolo largo), attacchi 201/201 (gruppo 22), social 28/28, aggiornamento
1.5.0 -> 1.6.0 13/13 (sito uguale tranne il conto alla rovescia), aspetti 56/56, sito di plumeCMS in sei parti 81/81, file del
pannello 6/6. Lavoro fatto con due agenti (prove, manuali) e controllato rifacendo le prove principali.
1.5.0 (28 ottobre): email del mattino "Oggi sul sito" e testi social del giorno portati dal modulo di
danilofailli.it nel CMS, per qualunque sito (`includes/social.php`, `admin/social.php`, riquadro Social della scheda, passo
del cron; tabelle `social_contenuti` e `social_programmati`; immagini 1200x630 e 1080x1350 con GD; i dati vecchi del
modulo, `articles.social_summary` e `social_pillar`, entrano da soli una volta). Capitolo 20 del manuale d'uso, 27 del
tecnico. Colori dei canali scuriti per il contrasto (4,5 a 1). Prove: install radice e cartella 37/37, `prova-social.js`
28/28, attacchi 177/177 (gruppo 21, Social), aggiornamento 1.4.2 -> 1.5.0 13/13 su tre siti (sito uguale al pixel tranne
il conto alla rovescia), aspetti 56/56, sito di plumeCMS in cinque parti 76/76, file del pannello 6/6.
**Da fare quando si porta danilofailli.it alla 1.5.0**: togliere dal suo modulo i passi del cron "email del mattino" e
"social del pillar" (altrimenti le email arrivano due volte) e rimettere hashtag e link del laser in Social > Impostazioni.
1.4.2 (28 ottobre): il manuale d'uso (richiesta di Danilo: "deve spiegare come fare qualsiasi cosa con il
CMS, con esempi pratici, immagini e checklist precise"), e quello che lo script delle schermate ha trovato eseguendo gli
esempi: articolo scritto nell'editor e pubblicato dalla scheda usciva senza testo, titolo dell'articolo non aggiornato nella
tela, campi senza stile in Menu, Form (etichetta larga pochi pixel) e Utenti, "testi social" nella pagina Email, accenti
mancanti in 54 file. Prove: install radice e cartella 37/37, attacchi 163/163, aggiornamento 1.4.1 -> 1.4.2 13/13 (sito
uguale tranne "1 minuto di lettura" e il conto alla rovescia), sito di plumeCMS in cinque parti 74/74, file del pannello 6/6.
1.4.1: fogli e script del pannello con la data nell'indirizzo (gli aspetti non cambiavano sul primo sito aggiornato:
memoria del browser), `prova-file-pannello.js`. 1.4.0 (28 ottobre): import da WordPress (`includes/importa-wp*.php`, `admin/importa-wordpress.php`: report, scelta,
import a giri, prova, conferma o annulla; password per le applicazioni, protezione SSRF), tipo di sito in prova fino a Definitivo
(`admin/tipo-sito.php`, solo installazioni nuove), quattro aspetti del pannello per utente (Aria predefinito, `users.pannello_aspetto`),
Primi passi nella dashboard, Controlla il server, foto leggere (WebP, `includes/foto.php`), favicon di partenza, Log cron in parole
chiare (`includes/cron.php`), riquadri con la posizione salvata (`'prima'=>'posizione'`). 163 attacchi respinti (2 saltati: burnout,
newsletter firmata senza `SEGRETO`); install radice e cartella 37/37; import contro un WordPress 6.6.2 vero 76/76; aggiornamento
1.3.1 -> 1.4.0 12/12; sito di plumeCMS in quattro parti 69/69. 1.3.1: l'indirizzo del sito ufficiale è un parametro (Aggiornamenti, impostazione `versione_sito`; `CMS_AGGIORNAMENTI_URL`
nel config vince); `prova-versione-sito.js` 7 su 7. 1.3.0 (notte del 27 ottobre, un agente con il ciclo PDCA): kit di partenza nell'installatore (`includes/kit-partenza.php`,
`includes/kit/`), 30 sezioni pronte nell'editor (`includes/sezioni-pronte*.php`), "c'è una versione nuova" (`includes/versione-nuova.php`,
`api/versione.php`), copia del sito (`includes/copia-sito.php`, Impostazioni > Copia del sito); prove `prova-kit.js`, `prova-sezioni.js`,
`prova-copia.js`, `prova-versione.js`; 97 attacchi respinti. 1.2.8: licenza GNU GPL 3 o successiva (`LICENSE`); tolto dal prodotto tutto ciò che era di danilofailli.it
(moduli ebook e Laser, CTA, box del burnout, aforismi come tipo fisso, testi d'esempio, glossario PNL, mittente); agganci
`moduli_disponibili`, `modulo_html`, `pagina_moduli`, `sito_percorso`; corretti captcha nella radice, Contatti con il modulo laser,
pubblicazione di notte. Il sito di plumeCMS ha la piuma disegnata in copertina e "Installalo in 5 minuti" con le schermate vere. 1.2.7: margine di Centrata, Copertina, Rivista e Scheda; la home del sito di plumeCMS apre con la Copertina (scelta di Danilo). 1.2.6: tendina Disposizione dell'Apertura, dieci disposizioni nuove più la classica (identica al pixel
alla 1.2.5), `prove/prova-hero.js` 154 su 154 con base, Cosmo, Aria e Bottega, anche senza foto. 1.2.5: serie nel download manager (`/scarica/<nome>-ultima/`, `{versione:<serie>}` nei testi), il sito di
plumeCMS segue da solo l'ultima versione caricata; messaggi chiari per i temi con PHP. 1.2.4: download manager (pannello Download, widget Download, conteggio senza robot; `prove/prova-download.js`
27 su 27). 1.2.3: manuali in PDF, `/scarica/<nome>/`, pacchetti con pagine, stile e impostazioni, il sito di plumeCMS
(`sito-plumecms/`, provato applicandolo dal pannello: `prove/prova-sito-plumecms.js` 36 su 36, quattro parti, server limitato a 2 MB), pannello Revisioni che si
chiude, sfondo colore globale delle sezioni. 1.2.2: sezione Foto di Apertura e Presentazione (trattamento, colori e intensità del velo, cinque effetti
animati) e interruttore dell'onda; provata con `prove/prova-foto-effetti.js` (20 su 20) e foto identiche al pixel con i
valori di partenza. 1.2.1: manuale tecnico e manuale dei temi.
plumeCMS 1.2.0 con i temi (Editor > Temi, `docs/temi.md`): tre temi di partenza (Cosmo, Aria,
Bottega), temi con PHP protetti, caricamento e download da zip. Installazione provata da zero in radice e in
sottocartella; aggiornamento 1.1.9 -> 1.2.0 dal pannello provato con sito identico al pixel prima e dopo;
78 prove di attacco respinte (la 79esima è del modulo danilofailli). Le prove del CMS girano su PHP 8.3 con
MySQL 8.0: su PHP 8.4.27 sono provate la sintassi di ogni file e i `.htaccess` su Apache, non il CMS intero
(senza `pdo_mysql` nell'immagine, e i pacchetti Debian bloccati dalla rete della sessione).
Prima installazione vera su `https://duhtron.it/test` (cartella), funzionante con la 1.1.9: da aggiornare alla 1.3.1.
Dal 27 ottobre plumeCMS vive nel suo progetto e repository (`SPOSTARE.md`).

Aperto:
- i documenti vecchi di `docs/` superati in alcuni punti (elenco nell'appendice A del manuale tecnico), da allineare;
- `CMS_PREFISSO` ha come valore predefinito `newdf_` perché danilofailli.it non lo
  scrive nel suo config. L'installatore lo scrive sempre, quindi le installazioni
  nuove non ne risentono. Se un giorno si cambia il predefinito, prima va aggiunto
  `define('CMS_PREFISSO', 'newdf_');` al `config.local.php` di danilofailli.it;
- prova vera delle chiamate a Google Search Console e IndexNow da un server con rete aperta;
- **indirizzo del sito ufficiale** per "c'è una versione nuova": dalla 1.6.2 è solo `VERSIONE_SITO_UFFICIALE`
  (`includes/versione-nuova.php`, `https://plumecms.danilofailli.it`), nessun campo nel pannello (decisione di Danilo); finché
  il trasloco non è fatto, le installazioni non trovano il sito ufficiale (dicono "non raggiungibile", nient'altro);
- copia del sito: il ripristino non toglie i file che stanno nel sito ma non nella copia; con due installazioni nello stesso database
  e prefissi che cominciano uguale (`cms_` e `cms_b_`) la copia prende anche le tabelle dell'altra;
- "c'è una versione nuova" provato solo in http locale (`CMS_AGGIORNAMENTI_HTTP_PROVA`): HTTPS vero da provare su un server;
- stessa famiglia del Riquadro con icona (corretto nella 1.4.0): "Animazione delle parole" del Titolo animato e `padding` di Social,
  Condividi, Avviso e Flip box hanno lo stesso nome di un controllo delle Avanzate;
- import da WordPress provato contro un WordPress locale, non su un hosting vero; Apache, HTTPS vero, SMTP vero e Imagick non provati per la 1.4.0;
- `tools/` e `prove/` hanno ancora riferimenti a danilofailli.it (strumenti di sviluppo, non vanno nello zip): `--sito`, la prova del burnout;
- i temi a danilofailli.it arrivano solo con un pacchetto `--sito` dal suo repository (mai lo zip del prodotto).

## Rapporto con danilofailli.it

**Regola di Danilo (28 ottobre 2026): non chiedergli più nulla di danilofailli.it.** Se servono informazioni, le dà lui.
Il 28 ottobre ha detto di aver già aggiornato tutto su danilofailli.it.

- danilofailli.it usa plumeCMS con il CMS in `/new/` e il modulo `moduli/danilofailli/`.
- **28 ottobre 2026, sera**: Danilo ha applicato lo zip di aggiornamento del prodotto 1.6.0 a danilofailli.it e il pannello
  lo ha preso. Visto da fuori: le pagine pubbliche (home, blog, laser, glossario, newsletter) le serve ancora WordPress, e
  `/new/` è un sito statico (`/new/styles/global.css`): plumeCMS lì fa solo da pannello e cron. Rischio: email del mattino
  doppie (CMS e modulo); pagine del modulo nel pannello da controllare. Consigliato a Danilo: spegnere le due email in
  Social > Impostazioni finché il modulo non è portato alla 1.6.0; se il pannello si rompe, Aggiornamenti > Snapshot > Ripristina.
- Le versioni nuove di plumeCMS arrivano a danilofailli.it come pacchetti del suo
  pannello Aggiornamenti, costruiti nel suo repository con
  `tools/cms-pacchetto.py --sito <commit> <nome>` (contengono anche il modulo).
- **Lo zip di aggiornamento del prodotto non va mai applicato a danilofailli.it**: non
  contiene il modulo e romperebbe le sue pagine.
- **Nemmeno il pacchetto del sito di plumeCMS** (`sito-plumecms-*.zip`): sostituisce home, stile, header, footer e menu.
  Va solo sul sito da cui si scarica plumeCMS.
- Una correzione fatta qui va riportata anche lì, e viceversa, finché i due repository
  non condividono il nucleo in un modo automatico.

## Trappole già incontrate

Errori veri, già pagati. Non ripeterli.

| Trappola | Cosa succede |
|---|---|
| `<?xml` (o qualunque `<?`) scritto come testo in un file PHP | con `short_open_tag` acceso, il predefinito di PHP e di Hostcare, diventa codice: errore di sintassi e pagina in 500. Ubuntu lo spegne, quindi in locale non si vede. Si scrive `&lt;?`. Il costruttore ora controlla la sintassi con `short_open_tag` acceso |
| Caratteri a 4 byte (emoji) nei dati | colonne utf8 a 3 byte: in modalità stretta MySQL rifiuta (1366), fuori da quella taglia il testo in silenzio. Nei nomi e nei JSON si salvano come entità o `\uXXXX` |
| Database in modalità stretta | `db()` imposta una modalità SQL esplicita; ogni tabella nuova si crea con `*_installa()` e si prova anche con `sql_mode` stretto |
| Apostrofo dentro un commento `/* */` di un file SQL di pacchetto | lo spezzatore del pannello lo legge come inizio di stringa e l'SQL fallisce (il pannello ripristina). Niente apostrofi nei commenti SQL |
| Pacchetto che porta un motore nuovo e usa insieme le sue voci nuove | lo applica il motore vecchio, che salta o rifiuta ciò che non conosce. Due pacchetti: prima il codice, poi il resto |
| `.htaccess` in un pacchetto | i motori fino alla 1.1.0 accettano solo `root/.htaccess`; `new/.htaccess` e `uploads/.htaccess` solo con il motore nuovo |
| `.php` nella radice dentro un pacchetto | dalla 1.1.1 il pannello lo rifiuta senza firma HMAC o senza la "finestra di un'ora" aperta da Sicurezza |
| `agg_registra_pacchetto()` sul file originale | **sposta** lo zip: si passa sempre una copia |
| Prove lanciate subito dopo `sicurezza-prova.js` | la forza bruta blocca 127.0.0.1 e tutto il resto fallisce al login: prima si svuota `<prefisso>sicurezza_tentativi` |
| Le prove nel browser lasciano dati | `editor.php?nuova=1` crea una bozza vera; una prova interrotta lascia i suoi modelli. Pulizia prima e dopo ogni batteria |
| Una pagina del pannello che risponde 200 | non prova che funzioni: un errore fatale può stare dentro la pagina. Solo le prove nel browser contano |
| `display_errors` acceso sul server | una deprecazione stampata prima di `header()` rompe i redirect. Fuori dal pannello gli errori vanno nel log, nel pannello l'output è bufferizzato |
| ModSecurity sui POST con HTML | li blocca: i campi con HTML viaggiano in base64 (`campi-protetti.js`) e `auth.php` li decodifica |
| Un editor che si inizializza copiando sé stesso nella casella di testo | la scheda Visuale vuota ha sovrascritto due testi. La sincronizzazione parte solo da una scheda aperta e visibile |
| `boundingBox()` di Playwright dentro un iframe | le coordinate sono già della pagina principale: non sommare la posizione dell'iframe |
| Un widget generico al posto dei tipi | 92 infografiche di 14 forme diverse schiacciate in un solo widget: tabelle diventate elenchi. Prima si catalogano le forme, poi un widget per forma, poi si converte, e il controllo si fa a occhio, originale e risultato affiancati |
| `pkill -f` con un pezzo del proprio comando | uccide anche la shell. Si cerca il pid con `ps -eo pid,comm,args` |
| Un server PHP avviato dentro una pipeline (`| tail`) | eredita la pipe e il comando non finisce mai. Si avvia con `setsid nohup ... >/dev/null 2>&1 </dev/null &` |
| Un indirizzo del pannello con un pezzo in più dopo il file (`/admin/index.php/`) | Apache apre la pagina lo stesso, ma gli assets relativi si cercano dentro `index.php/` e il pannello appare senza grafica. Dalla 1.1.5 `auth.php` riporta all'indirizzo pulito |
| Il CMS installato in una cartella (`https://esempio.it/test`) | fino alla 1.1.6 l'installatore rifiutava l'indirizzo con la cartella, `index.php` rimandava ogni pagina alla radice e i link del sito partivano da `/`. Dalla 1.1.7 `CMS_BASE` scritto dall'installatore, sito servito quando `cms_sito_base() === cms_base()`, `cms_con_cartella()` sui link. Le prove d'installazione si fanno in radice **e** in cartella, su Apache vero |
| Uno zip costruito con `zipfile.writestr()` | ogni file esce 0600: sul server PHP funziona (gira con l'utente del sito) ma il server web non legge CSS, JS e immagini e risponde 403. Pannello e installatore senza grafica sul primo server vero (27 ottobre 2026). Ora `scrivi_voce` mette 0644 e cartelle 0755. Le prove si fanno estraendo i file con un utente diverso da quello del server web |
| Un 301 che dipende dalla configurazione | il browser lo ricorda e lo ripete senza chiedere più al server: fino alla 1.1.6 la cartella `/test/` andava alla radice con un 301, e dopo la correzione il browser ci andava ancora (la pagina si apriva solo con `?=0`). I rimandi che dipendono dalle impostazioni sono 302 con `no-store`; a chi ha già il 301 in memoria serve svuotare la cache |
| Una chiave numerica scritta come testo in un array PHP (`'404' => ...`) | PHP la trasforma nel numero 404: `array_keys()` restituisce un intero e `in_array('404', ..., true)` dice di no. La vista 404 dei temi non partiva (27 ottobre 2026). Si confronta come testo: `(string)$n` |
| `color-mix()` al posto di `rgba()` per una trasparenza | il colore esce in un altro spazio e i gradienti cambiano di 1-5 livelli: non si vede, ma il sito non è più identico. Per le trasparenze dei temi si usa `rgba(var(--x-rgb),a)` |
| Un elemento con l'attributo `hidden` e un `display` nel suo CSS | il `display` vince e `hidden` non nasconde niente: il pannello Revisioni dell'editor non si chiudeva mai e toglieva 380 pixel alla tela (fino alla 1.2.2). A ogni `display` su un elemento che si nasconde, la regola `[hidden]{display:none}` |
| Un valore di un controllo "scelta" che non sta fra le sue opzioni | `editor_valida` lo svuota in silenzio: un header importato con il menu `posizione:principale` usciva senza menu (prima della 1.2.3), e il widget Download importato dalla parte 1 del sito perdeva i file che arrivano con le parti dopo (1.2.4). Una tendina i cui valori possono non esistere ancora dichiara `'accetta' => regex` |
| Un pacchetto più grande del limite di caricamento del server | "Il file supera il limite di caricamento del server": il pacchetto unico del sito di plumeCMS (5 MB) è stato rifiutato dal primo hosting vero (27 ottobre 2026), che accetta 1,2 MB. Ora il costruttore lo divide in parti sotto 1900 KB (il predefinito di PHP è 2M). Anche i PDF: con i tag di accessibilità e Inter variabile pesavano il doppio |
| `editor_widget_registrati()` per sapere quali fogli caricare | dà solo i widget esterni, non quelli del nucleo: l'anteprima dell'editor non caricava i fogli delle disposizioni dell'Apertura e le mostrava senza stile (1.2.6, trovato dalla prova dell'editor, non da quella del sito). E le viste mettono ogni `<link>` davanti al precedente: l'ordine dei fogli di un widget si capovolge se non lo si dà al contrario |
| Una regola di un foglio nuovo con un solo nome di classe (`.ed-hero-copertina{padding:...}`) | perde contro `.ed-w-hero .hero{padding:0}` di `sito.css`: nella 1.2.6 testo e pulsanti della Copertina toccavano il bordo, e nessuna prova lo vedeva (passavano tutte). Le regole sul contenitore di una disposizione si scrivono `.ed-hero.ed-hero-<id>`; le foto si guardano anche sul sito vero, non solo sulla pagina di prova |
| Cercare i resti di un sito con le sue parole (danilofailli, laser, ebook) | sfuggono le frasi: i widget extra avevano ancora "Non lavoro per renderti più forte", "Fai chiarezza in 15 minuti", un listino a incontri. Si guarda la pagina con tutti i widget, a occhio |
| Una consegna in un solo zip da 15 MB | arrivata rotta a Danilo due volte (1.5.0 e 1.6.0) pur essendo integra qui; anche i pezzi da 4,8 MB lo hanno confuso (li ha caricati nel pannello: "supera il limite"). Funziona mandare **i file uno per uno** (aggiornamento, parti del sito, PDF), ognuno sotto 1,9 MB, nell'ordine di caricamento (28 ottobre 2026) |
| `open(f, 'w').write(f(open(f).read()))` in Python | `open(f, 'w')` svuota il file prima che la lettura avvenga: il `config.local.php` di un sito di prova è finito vuoto (28 ottobre 2026) e la prova dopo falliva in modo incomprensibile. Prima si legge in una variabile, poi si scrive |
| Un'emoji salvata come entità (`&#127837;`) in un campo di testo semplice di un widget | il widget fa l'escape e sul sito esce scritto `&amp;#127837;` (sito su misura, 1.7.0). Le entità valgono solo nei campi che le decodificano (Testo); nell'albero dell'editor ci pensa `editor_json()`; nei nomi che diventano titoli e slug le emoji si tolgono |
| Un gestore in linea (`onchange="..."`) nel pannello | la CSP a nonce lo blocca in silenzio: Sostituisci non partiva. Si usano gli attributi di `pannello.js` (`data-invia-al-cambio`, `data-conferma`...) |
| Più di sei colonne in una sezione | `editor_valida()` tiene le prime sei e toglie le altre in silenzio: la settima colonna (con il suo widget) sparisce. Nelle pagine di prova un widget per sezione |
| Una pagina di prova creata a metà del giro delle schermate | prende il numero di pagina che un capitolo dopo si aspetta (`editor.php?id=6`, "Lavora con noi") e lo script si ferma. Le pagine di prova per le foto si creano dopo tutti i capitoli |
| Fidarsi del resoconto di un agente | è successo che un agente dichiarasse lavoro mai scritto su disco. Si controllano i file veri e si rifanno le prove |

## Tono con l'utente

Danilo è il proprietario e non è uno sviluppatore. Le istruzioni operative vanno
scritte passo per passo, dicendo dove cliccare e cosa deve comparire. I risultati
si riportano per quello che sono: se una cosa non è verificata, si dice.

## Second brain
Second brain: progetto plumecms

- Memoria nel repository `danilofaillii/second-brain`, cartella `plumecms/`.
- Istruzioni: skill `second-brain`. Se la skill non è installata (sessioni cloud), leggi `_sistema/skill/second-brain/SKILL.md` nel repository `second-brain` e seguila.
- A inizio sessione esegui `riprendi`. Durante il lavoro `segna`. A fine sessione `salva`.
- Non usare informazioni di altri progetti senza che Danilo lo chieda.
