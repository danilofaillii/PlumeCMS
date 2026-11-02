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
  moduli/       i moduli del sito che installa (vuota nel prodotto); addon-attivi.json = gli addon accesi
  addon/        gli otto addon (2.0.0), moduli del prodotto spenti finché non si accendono da Impostazioni > Addon
  temi/         i temi del sito (motore in includes/temi.php, docs/temi.md)
  docs/         la documentazione del CMS
  VERSIONE, CHANGELOG.md, README.md, INSTALLA.md
tools/          cms-pacchetto.py (costruisce gli zip), pacchetto.py (pacchetti per il
                pannello Aggiornamenti), sicurezza-prova.js (261 prove di attacco; quelle degli addon e dei widget stanno in prove/attacchi-*.js)
prove/          prova-install.js (installazione da zero con Playwright), prova-social.js (social ed email del mattino), prova-fine-articolo.js (il riquadro in fondo agli articoli), prova-su-misura.js (il sito su misura), prova-temi.js, prova-pannello-temi.js,
                prova-temi-partenza.js, prova-carica-temi.js (temi), prova-foto-effetti.js (velo ed effetti delle foto), prova-hero.js (le undici aperture),
                foto-sito.js e quanto-foto.py (schermate prima e dopo, al pixel)
esempi/         tema-lavagna/: il tema d'esempio del manuale dei temi (provato, non entra nello zip)
sito-plumecms/  il sito da cui si scarica plumeCMS, fatto con plumeCMS: costruisci.py scrive il pacchetto
                releases/sito/sito-plumecms-<versione>.zip (dalla 2.1.0 uno solo; --max-kb lo divide di nuovo in parti)
tools/manuali-pdf.js  i due manuali in PDF (releases/manuali/: manuale-uso.pdf, manuale-temi.pdf); tools/manuale-uso-schermate.js le schermate del manuale d'uso;
tools/consegna.py     lo zip unico da dare a Danilo (releases/consegna/)
```

Stato, decisioni, contesto e diario non stanno qui: stanno nel second brain
(`danilofaillii/second-brain`, cartella `plumecms/`), unica copia.

## Documenti

**Regola di Danilo (29 ottobre 2026): con plumeCMS restano solo due documenti**, il manuale d'uso e il manuale dei temi.
Sono gli unici in `cms/docs/`, nello zip, nel sito di plumeCMS e nella pagina Download.

0. `cms/docs/manuale-uso.md`: il manuale d'uso per chi usa plumeCMS (40 capitoli in due parti, la seconda per gli addon; esempi, schermate con i passi
   numerati, checklist). Le schermate le fa `tools/manuale-uso-schermate.js` eseguendo gli esempi su un sito di prova
   (kit Attività, in `/home/studioprova/public_html`): **quando cambia il pannello si rifanno le schermate e si
   ricontrolla il capitolo**. Il PDF è uno solo (dalla 2.1.0) e deve restare sotto 12 MB (`MAX_KB` in `tools/manuali-pdf.js`, alzato da 8 a 12 MB il 1 novembre 2026, decisione di Danilo; oggi
   7,3 MB con le schermate JPEG a qualità 55). Nel PDF vanno JPEG: Chromium rimette i PNG senza tavolozza e pesano di più.
   `cms/docs/manuale-temi.md`: il manuale per chi costruisce un tema, con il tema d'esempio Lavagna.
   **Copia nel second brain** (richiesta di Danilo, 1 novembre 2026): i tre manuali (uso, temi, tecnico) stanno anche in
   `second-brain/_comune/manuali-plumecms/` come `plumecms-manuale-*.md`, per tutti i progetti. A ogni versione si rimpiazzano
   (il manuale d'uso senza le immagini: le righe `![...](immagini/...)` diventano "Schermata: ..., nel PDF") e si aggiorna la
   versione nel `LEGGIMI.md` di quella cartella, poi commit e push su `main` del second brain.
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
  3. *Il sito di plumeCMS* (`sito-plumecms/costruisci.py`): `NOVITA` (regola di Danilo, 1 novembre 2026: nella pagina Scarica **non un changelog** ma un elenco
     breve delle ultime versioni, un titolo per riga (`NOVITA_MOSTRATE`), che non scende sotto il riquadro "Cosa serve" accanto, e
     **mai nomi di aziende o servizi di terzi** nelle novità; lo controlla `prova-sito-plumecms.js`. Fuori dalle novità i nomi restano:
     Importa da WordPress, Google e gli orari, WhatsApp, LinkedIn e Facebook); un blocco nella pagina Funzionalità con la schermata
     (pagina aggiunta a `sito-plumecms/schermate.js`, immagine in `USATE`); home, Domande frequenti o Installazione se la
     funzionalità le cambia; i numeri scritti nei testi (widget, attacchi). Poi le schermate rifatte sul sito di prova e
     `--schermate`, le parti ricostruite, `prove/prova-sito-plumecms.js` con un controllo per il blocco nuovo.
  Dalla 1.2.5 il numero di versione nelle pagine è automatico (`{versione:plumecms-ultima}`): per una versione del CMS
  senza pagine nuove Danilo carica solo i due zip da Download; le parti del sito servono quando cambiano le pagine.
  Ordine dei comandi: `python3 tools/cms-pacchetto.py --aggiornamento`, `node tools/manuali-pdf.js`,
  `python3 sito-plumecms/costruisci.py`, `python3 tools/consegna.py` (con `--uno` se il motore dei pacchetti è cambiato senza voci
  nuove del manifest). A Danilo si consegna il pacchetto unico `releases/consegna/plumecms-<versione>-pacchetto-unico.zip` (regola
  del 31 ottobre, sotto), più lo zip completo del CMS e il PDF del manuale da conservare.
- **Regola di Danilo (31 ottobre 2026): niente più file spezzettati. "Dai prossimi aggiornamenti non serve più che spezzetti i file.
  Me ne dai uno solo che contiene tutte le modifiche."** Ha alzato il limite di caricamento del suo hosting (php.ini). Quindi:
  le modifiche di una versione (codice del CMS ed eventuali pagine del sito di plumeCMS) arrivano in **un solo pacchetto** da caricare
  in Impostazioni > Aggiornamenti; le parti del sito non si dividono più sotto 1900 KB (`costruisci.py --max-kb` alto). Supera le regole
  vecchie "file uno per uno" e "parti sotto 1900 KB" per quello che si consegna a lui. Attenzione alla trappola del motore nuovo: se
  una versione cambia il motore dei pacchetti (`includes/aggiornamenti.php`) e il pacchetto usa voci nuove del manifest, codice e
  pagine non stanno nello stesso pacchetto (`consegna.py` controlla le voci; se il motore cambia solo dentro, `--uno`).
- **Pacchetto di aggiornamento** per un'installazione esistente:
  `python3 tools/cms-pacchetto.py --aggiornamento`. Si carica dal pannello,
  Impostazioni, Aggiornamenti.
- **Versione**: `cms/VERSIONE` (oggi 2.17.0; sempre tre numeri: "beta" va nel CHANGELOG, mai nel nome dello zip, se no la serie "ultima" del Download non lo riconosce) e una voce in `cms/CHANGELOG.md` a ogni
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
- **Regole di interfaccia** (regola globale di Danilo, 1 novembre 2026): prima di toccare pannello, editor, installatore, addon o sito
  si legge `docs-sviluppo/regole-interfaccia.md` (copia in `second-brain/_comune/regole-interfaccia.md`, valida per tutti i progetti;
  se cambia una, si aggiorna l'altra) e si chiude con la sua checklist. Le prove sono `prove/prova-ux-pannello.js` e `prove/prova-ux-editor.js`.
- **Regola di Danilo (2 novembre 2026): le aperture (hero) nuove hanno l'aspetto e lo stile di quelle che ci sono già.** Proporre
  disegni in un altro stile (una fioreria chiara con caratteri da rivista) è stato respinto: "non c'entrano nulla con le hero che sono già
  nel sito". Ogni proposta o disposizione nuova si disegna nella lingua delle undici di `includes/editor-hero*.php` (foglio
  `sito-plumecms/immagini/aperture.webp`), tema Cosmo: fondo scuro blu notte (`--bg #060a17`) con aloni viola e ciano; occhiello piccolo
  maiuscolo spaziato in ciano (`.occhiello`); titolo in Space Grotesk 700 maiuscolo, con la parte in evidenza sfumata ciano-viola
  (`.hero h1 em`, `--grad`); testo Inter grigio (`--testo-2`); pulsante viola pieno e pulsante a filo ciano (`.btn-viola`, `.btn-filo`);
  la foto è una schermata in una finestra di vetro, in bianco e nero con il velo ciano-viola (`.finestra`); riquadri di vetro con onda e
  barre "Leggero", "Meno peso", "Più tempo" (`.chip`). Testo d'esempio quello del sito: "Il tuo sito, leggero come una piuma."
  Cambia solo la disposizione; con gli altri temi i colori seguono il tema. Proposte del 2 novembre: 15 tavole
  (`https://claude.ai/artifact/D72dbZ333x6ctQLqUk9Uzt`): Danilo ha scelto tutte e 15, fatte nella 2.17.0.
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

## Stato al 31 ottobre 2026

Fatto: plumeCMS 2.17.0 (2 novembre): quindici aperture nuove, ventisei in tutto (richiesta di Danilo, tutte e 15 dalle tavole approvate;
agente, ramo `aperture-15`): `includes/editor-hero-c.php|d.php`, `sito/assets/hero-c.css|d.css`, `hero-c.js` (solo video e prima e dopo,
caricato con il `'segno'` di `editor_asset_pagina()`), campi in più con `'condizione'`, tendina con `'accetta'`, video nei Media
(`media_carica_video()`, `uploads/video/AAAA/MM/`, campo `video` dell'editor, `editor_video_locale()`). Ritocchi chiesti da Danilo: Orizzonte
più visibile, Trittico "Installazione 5 min" (campi Numero grande); Faro più luminoso (io). Corretto da me: il Titolo traforato scriveva la
foto in `url()` senza la cartella (`editor_hero_con_cartella()`). Gruppo di attacchi 39. Prove: install 38/38 radice e cartella, aperture
364/364 con e senza foto e in cartella, campi nuovi 46/46 (il video va in `uploads/video/.../prova.mp4`, come dal pannello, e `VIDEO=` a
`pagina-hero.php`), pulsanti 1570/1570 senza tema, Cosmo e Aria (su un sito col kit: le pagine puntano a `/servizi/`), aggiornamento 2.16.1 ->
2.17.0 13/13 con 20 foto del sito e 22 delle undici aperture identiche, attacchi 422/422, addon 70/70, pacchetto unico 13/13. Foglio delle 26
in `sito-plumecms/foglio-aperture.py` (tre colonne). Zip 2218 KB, PDF 10910 KB. Aperto: Aggiornamenti a 768 px scorre di lato con un
pacchetto applicato (da vedere se c'era già).

Fatto: plumeCMS 2.16.1 (2 novembre): limite di caricamento di almeno 8 MB fra i requisiti (scelta A di Danilo): avviso nell'installatore
(`inst_limite_caricamento()`, non blocca), riga NO in Aggiornamenti (`agg_limite_caricamento()` in `agg_controlli()`), riga in Controlla il
server, INSTALLA, README, manuale d'uso e tecnico, "Cosa serve" del sito, `prova-install.js` con il controllo (38). Il motore dei pacchetti
è cambiato solo dentro: `consegna.py --uno`. Prove: install 38/38 radice e cartella con il limite a 2 MB, controlla il server 21/21,
aggiornamento 2.16.0 -> 2.16.1 13/13 (1 foto su 20 a 1 livello), conteggi uguali, attacchi 412/412, pacchetto unico 13/13. Il primo giro
è caduto per MySQL delle prove spento (Docker): si riaccende con dockerd e `docker start cmsdb`, e al riavvio MySQL ci mette minuti.

Fatto: plumeCMS 2.16.0 (2 novembre): fase 9 del piano Brevo, l'ultima: form a cinque passi e pagine pubbliche (agente, ramo
`fase9-form-brevo`: `admin/form.php` riscritto, `includes/form-brevo.php`, `includes/newsletter-consenso.php`, `admin/newsletter-consenso.php`
(voce Pagine e consenso), `admin/assets/form-campi.js`, `sito/assets/modulo-esterno.css`; pagina ospitata `/modulo/<slug>/`, l'unica con
`frame-ancestors *`; QR con `sicurezza_qr_svg()` letto da `prove/qr-decodifica.py`; gruppi di consenso spenti di partenza, attributo di sistema
TRACCIAMENTO; tabelle `newsletter_gruppi_consenso|contatti_gruppi|pagine_uscita`, colonne nuove in `moduli`, `newsletter_campagne`,
`newsletter_conferme`). Gruppo di attacchi 38. Corretto da me: Statistiche senza tabelle vuote (al telefono scorreva di lato su un sito senza
automazioni). Prove: install 37/37 radice e cartella, form 90/90 radice, cartella e aggiornato, form di prima 25/25 (HTML ed email al byte),
aggiornamento 2.15.0 -> 2.16.0 13/13 con 20 foto uguali, campagne vecchie al byte 15/15, automazioni 89/89 x3, campagne 100/100 (la prova
ora conta solo le campagne senza nome nuove: `contatti-cli.php semina` ne mette due vecchie), contatti, editor, A/B, report, segmenti, form-avviso
13/13, omaggio 27/27, email di conferma 44/44, newsletter 37/37, addon 70/70, attacchi 412/412, telefono 54/54, pacchetto unico 13/13.
Zip 2193 KB, PDF 10837 KB. Con la 2.16.0 il piano in nove fasi è finito.

Fatto: plumeCMS 2.15.0 (2 novembre): fase 8 del piano Brevo, le automazioni (agente, ramo `fase8-automazioni-brevo`:
`includes/newsletter-automazioni.php` nuovo, `newsletter-flussi.php` e la tela riscritti; inneschi, azioni con webhook firmato solo https
e mai verso indirizzi interni, regole, statistiche per passo, Attività e registro; tabelle `newsletter_flussi_registro|numeri`, colonne
`newsletter_flussi.stato|inneschi|tag|rientro|rientro_attesa|uscita` e `newsletter_corsi.giro`; un flusso spento diventa Inattiva). Gruppo
di attacchi 37. Prove: install 37/37 radice e cartella, automazioni 89/89 radice, cartella e aggiornato (4872 contatti: l'innesco degli
inattivi ne fa entrare 200 a giro, la prova gira fino a 60 volte), flussi di prima in corso 18/18 (su un sito loro: con le campagne vecchie
sullo stesso sito le email dei flussi finiscono nella posta dell'altra prova), campagne vecchie al byte 15/15, aggiornamento 2.14.0 -> 2.15.0
13/13 con 20 foto uguali, contatti 94/94, campagne 100/100, editor email 107/107, A/B 65/65, report 72/72, segmenti 179/179, newsletter 37/37,
addon 70/70, attacchi 389/389, pacchetto unico su un sito 2.14.0 13/13. Lo zip del CMS si costruisce prima di `consegna.py`
(`cms-pacchetto.py --aggiornamento` in testa a `$S/v2150.sh`). La prova degli attacchi delle formule ora usa indirizzi nuovi a ogni giro
(quelli cancellati restano nell'impronta e l'importazione li scarta). Zip 2151 KB, PDF 10463 KB.

Fatto: plumeCMS 2.14.0 (2 novembre): fase 7 del piano Brevo, report e statistiche (agente, ramo `fase7-report-brevo`:
`includes/newsletter-report.php`, `admin/newsletter-report.php`, `assets/css/report.css`, statistiche riscritte, colonne `newsletter_eventi.volte`
e `ultimo`, evento `soft`, orario migliore con `newsletter_campagne.orario_migliore|orario_ora`, gruppo di attacchi 36). Tassi sulle consegnate.
Prove: install 37/37 radice e cartella, report 72/72 nuovo e aggiornato (`prova-report.js` vuole il server finto con `rimbalzi`), contatti
94/94, campagne 100/100, editor 107/107, A/B 65/65, aggiornamento 2.13.0 -> 2.14.0 13/13 con 20 foto uguali, campagne vecchie al byte 15/15,
segmenti 179/179, newsletter 37/37, addon 70/70, attacchi 374/374, pacchetto unico su un sito 2.13.0 13/13. Zip 2123 KB, PDF 10350 KB.

Fatto: plumeCMS 2.13.0 (2 novembre): fase 6 del piano Brevo, test A/B (agente, ramo `fase6-ab-brevo`: `includes/newsletter-ab.php`,
tabella `newsletter_ab`, colonna `newsletter_coda.versione`, gruppo di attacchi 35). Corretto da me il blocco `GET_LOCK` dell'invio
(`newsletter_invia_giro()`): ora con `md5(DB_NAME|DB_PREFIX)`, prima due siti sullo stesso MySQL si fermavano a vicenda. Prove: install 37/37
radice e cartella, test A/B 65/65 nuovo e aggiornato, campagne 100/100 radice e cartella, editor email 107/107, aggiornamento 2.12.0 -> 2.13.0
13/13 con 20 foto uguali, campagne vecchie al byte 15/15, contatti 94/94 su un sito pulito (`prova-contatti-brevo.js` va lanciata prima di
`prova-ab.js`: con più di 50 contatti la riga cercata va alla seconda pagina), segmenti 179/179, newsletter 37/37, addon 70/70, attacchi
362/362, pacchetto unico su un sito 2.12.0 13/13. Zip di aggiornamento 2099 KB, PDF 10077 KB.

Fatto: plumeCMS 2.12.0 (2 novembre): fase 5 del piano Brevo (agente, ramo `fase5-editor-email`): editor delle email a blocchi
(`admin/newsletter-design.php`, `assets/newsletter-design.js|css`, `includes/newsletter-design.php` da JSON a HTML per la posta), modelli
(`admin/newsletter-modelli.php`, galleria in `includes/newsletter-modelli/*.json`), sezioni salvate e sincronizzate, icone dei social PNG in
`sito/assets/email/` (da `tools/icone-email.js`). Prove: install 37/37 radice e cartella, editor email 107/107, campagne 100/100 radice e
cartella, aggiornamento 2.11.0 -> 2.12.0 13/13 con 20 foto uguali, campagne vecchie al byte 15/15 (il seme `semina_vecchie` ora mette
nome = oggetto, come il pannello dalla 2.11.0), attacchi 353/353 (gruppo 34), pacchetto unico su un sito 2.11.0 13/13.
**Zip di aggiornamento 2088 KB, sopra i 2 MB predefiniti di PHP**: decisione chiesta a Danilo (requisito 8 MB o due file). Le prove di
aggiornamento vogliono il server con `-d upload_max_filesize=64M` (`PHPOPT` in `$S/addon-nuovo.sh`). PDF 9890 KB.

Fatto: plumeCMS 2.11.0 (2 novembre): fase 4 del piano Brevo (agente, ramo `fase4-campagne-brevo`): campagne a riepilogo
(`includes/newsletter-campagne.php`, `admin/newsletter-campagna.php` riscritta), elenco con viste e azioni, versione web
(`/newsletter-web/`), pagina per uscire con il questionario (`/newsletter-esci/`), centro preferenze (`/newsletter-preferenze/`),
indirizzo dell'azienda nel piede. Corretto da me `nl_url_evento()`: nei siti in cartella pixel e link avevano la cartella due volte.
Prove: install 37/37 radice e cartella, campagne 100/100 radice e cartella (il pixel in cartella si apre), aggiornamento 2.10.0 -> 2.11.0
13/13 con 20 foto uguali e conteggi uguali, campagne vecchie identiche al byte 15/15, contatti 94/94, segmenti 179/179, newsletter 37/37,
addon 70/70, attacchi 335/335 (gruppo 33), pacchetto unico su un sito 2.10.0 13/13. Zip di aggiornamento 2017 KB, PDF 9365 KB.

Fatto: plumeCMS 2.10.0 (2 novembre): fase 3 del piano Brevo, i segmenti (agente, ramo `fase3-segmenti-brevo`: `includes/newsletter-segmenti.php`,
`admin/newsletter-segmenti.php`, `assets/segmenti.js`, Aggiungi filtro, viste, bivi dei flussi sul motore; campagne con segmenti nella fase 4),
e le 15 infografiche disegnate (agente, ramo `infografiche-15`: `includes/editor-infografiche.php`, `sito/assets/infografiche-svg.css`, 107 widget;
su testate chiare il titolo esce nero per il contrasto). Anteprime di Aria, Bottega, Cosmo in WebP (`TOLTI` toglie i PNG): zip di aggiornamento
1986 KB. Gruppi di attacchi 31 (segmenti) e 32 (infografiche). Prove: install 37/37 radice e cartella, segmenti 179/179 nuovo e aggiornato,
infografiche 44/44, importa 85/85, contatti 94/94, aggiornamento 2.9.0 -> 2.10.0 13/13 con 20 foto uguali e conteggi uguali, newsletter 37/37,
addon 70/70, attacchi 316/316, pacchetto unico su un sito 2.9.0 13/13. `prova-sito-plumecms.js` scorre Funzionalità prima di guardare le
immagini (loading=lazy). `prova-pannello-temi.js` vuole prima il tema finto "prova". PDF 8865 KB.
Attenzione: dalla 2.10.0 le versioni hanno due cifre in mezzo; confronti di versione sempre numerici, mai come testo.

Fatto: plumeCMS 2.9.0 (2 novembre): fase 2 del piano Brevo (agente, ramo `fase2-importa-brevo`): Importa contatti in quattro passi
(`admin/newsletter-importa.php`, motore `includes/newsletter-importa.php`, XLSX senza librerie con difese DTD e zip bomba, lavoro a giri dalla
pagina e dal cron), Esporta con la finestra, lista dei test, cartella privata `uploads/contatti-<casuale>/`. Pannello e manuale senza "come
in Brevo" (decisione di Danilo; Brevo resta solo come servizio di invio). Novità del sito in elenco breve. Prove: install 37/37 radice e
cartella, importa 85/85, contatti 94/94 (nuovo e aggiornato), aggiornamento 2.8.0 -> 2.9.0 13/13 (1 foto su 20 a 1 livello), conteggi uguali,
newsletter 37/37 (su un sito aggiornato con esportazioni la pagina Contatti ha due `table.table`: la prova usa `.first()`), addon 70/70,
attacchi 283/283, pacchetto unico su un sito 2.8.0 13/13 con il sito di plumeCMS. Zip di aggiornamento 2019 KB, PDF 8528 KB.

Fatto: plumeCMS 2.8.0 (1 novembre, notte): fase 1 del piano Brevo (`docs-sviluppo/brevo-confronto.md`, richiesta di Danilo: "newsletter
e form esattamente come Brevo"; agente in worktree, ramo `fase1-contatti-brevo`). Contatti (`admin/newsletter-contatti.php`), scheda del
contatto, Attributi (sei tipi, calcolati, globali, `{{ contact.X }}`), liste in cartelle, lista nera con i motivi di Brevo, 5 soft bounce
di fila (messaggi, non tentativi), Elimina definitivamente con l'impronta. Motore `includes/newsletter-contatti.php`, sette tabelle nuove,
nessuna rinominata. Prove in `prove/prova-contatti-brevo.js` (con `MIGRATO=1` su un sito 2.7.0 seminato da `contatti-cli.php semina`).
Fasi dopo: 2.9.0 importazione, 2.10.0 segmenti, 2.11.0 campagne con disiscrizione e preferenze, 2.12.0 editor a blocchi, 2.13.0 A/B,
2.14.0 statistiche, 2.15.0 automazioni, 2.16.0 form e gruppi di consenso. Zip di aggiornamento 1988 KB: oltre la 2.8.0 supera i 2 MB.
Attenzione: `/tmp/manuale-uso-png` ha 173 PNG vecchi che `tools/manuale-uso-schermate.js` con `SOLO_CAPITOLI` ricopia (usare `TMPDIR`).

Fatto: plumeCMS 2.7.0 (1 novembre, sera): l'email di conferma dell'iscrizione modificabile in ogni parte (richiesta di Danilo; agente
in worktree): `includes/newsletter-email.php` (`nl_email_conferma()`), riquadro "Email di conferma" nella scheda del form e in Newsletter >
Impostazioni (impostazione `newsletter_conferma_email`, colonna `moduli.conferma_email`), anteprima e prova, solo admin; senza modifiche
l'email è identica al byte alla 2.6.0 (`prove/email-conferma-cli.php`). Prove: email conferma 44/44 su un sito aggiornato, install 37/37
radice e cartella, aggiornamento 2.6.0 -> 2.7.0 13/13 con le foto uguali (una a 1 livello), addon 70/70, attacchi 260/260 (gruppo 28 nuovo),
pacchetto unico su un sito 2.6.0 13/13 senza errori fatali. PDF del manuale d'uso 7989 KB: vicino al tetto di 8 MB.
Lo stesso giorno: su danilofailli.it la regola `RewriteRule ^new/?(.*)$` del suo `.htaccess` mangiava "new" di /newsletter-conferma/
(pagina 404 dopo il clic nell'email): data a Danilo la regola `^new(?:/(.*))?$`.

Fatto: plumeCMS 2.6.0 (1 novembre): controllo di UX e UI con due agenti (pannello; editor, installatore, addon, sito), scelte di
Danilo su 18 punti (tutti tranne l'editor ridotto al telefono, rimandato), regole globali in `docs-sviluppo/regole-interfaccia.md`
(copia in `second-brain/_comune/`). Titoli dei riquadri in fascia, campi a 3:1, conferme in una finestra del pannello, tabelle a schede
al telefono, Impostazioni a schede (Sito), bozze vuote nel cestino dal cron, Download con i giorni in un riquadro loro e il peso del file
controllato (`data-peso-massimo`, `pannello_invio_troppo_grande()`), avviso del logo coperto dal widget in Stile del sito,
`allow_url_fopen` in Controlla il server (su plumecms.it era spento). Sito: data delle schede e ricerca vuota cambiate apposta.
Prove: install 37/37 radice e cartella, aggiornamento 2.5.0 -> 2.6.0 13/13 (4 foto su 20 diverse, tutte volute: blog e ricerca),
pacchetto unico su un sito 2.5.0 13/13 senza errori fatali, addon 70/70, ux pannello 1234/1234, ux editor 59/59 (senza copia non
installata e pagina /addon-tutti/), telefono 50/50 a 390 e 768, bozze vuote 11/11, download limite 8/8, attacchi 250/250 (senza
FINTO_WP e SEGRETO), sito di plumeCMS 95/95. Manuale d'uso con 173 schermate rifatte, PDF 7840 KB. Le prove che caricano pacchetti
grandi vogliono il server con `-d upload_max_filesize=64M -d post_max_size=80M`: dalla 2.6.0 il browser blocca prima dell'invio un file
oltre il limite. `prova-addon-tutti.js` spegne gli addon alla fine: `prova-ux-editor.js` li vuole accesi.

Fatto: plumeCMS 2.5.0 (1 novembre): otto temi nuovi (richiesta di Danilo: Estate, Inverno, Autunno, Primavera, Giallo 255,255,0,
Rosso, Verde, Blu; agente temi in worktree) con contrasto almeno 4,5 misurato su sette pagine (`prove/prova-temi-nuovi.js` 611/611),
e un audit di tutto il CMS (agente auditor): 16 problemi risolti, quasi tutti emoji nelle tabelle a 3 byte, documento
`docs-sviluppo/audit-2026-11.md` con Gantt e sette punti aperti, `prove/prova-audit-2026-11.js` 34/34. Cosmo, Aria e Bottega hanno
ancora testi sotto 4,5 (Bottega 3,97): non toccati. Le anteprime dei temi nuovi sono WebP: con i PNG lo zip di aggiornamento
pesava 2182 KB, oltre i 2 MB predefiniti di PHP (ora 1908 KB). Prove: install 37/37 radice e cartella, pacchetto unico su un sito 2.4.0 13/13 due volte (dopo la correzione di opcache), aggiornamento 2.4.0 -> 2.5.0
13/13 con 24 foto uguali, addon 70/70 (nuovo e aggiornato), attacchi 261/261, temi nuovi 451/451 (nuovo e aggiornato), audit 34/34
radice e cartella, pannello temi 32/32, motore temi 39/40 (il KO cerca il nome "Bottega di prova" dell'installatore, sito fatto col kit),
service account 8/8, telefono 49/49 a 390 e 768, firma 14/14, indirizzo sito 10/10, sito di plumeCMS 92/92.

Fatto: plumeCMS 2.4.0 (1 novembre): il sito ufficiale passa a **https://plumecms.it** (Danilo ha comprato il dominio; stesso hosting;
il vecchio plumecms.danilofailli.it si chiudeva subito; dal 1 novembre, decisione di Danilo, resta e rimanda a plumecms.it con un 301 in `.htaccess`, cartella vecchia vuota). `VERSIONE_SITO_UFFICIALE` e `FIRMA_URL` cambiati, la frase salvata col
vecchio indirizzo migra da sola, e il riquadro nuovo Impostazioni > Indirizzo del sito (`sito_url` dal pannello, con la prova del file).
I siti fino alla 2.3.2 vanno aggiornati a mano una volta. Foglio dei passi del trasloco in `docs-sviluppo/trasloco-plumecms-it.md`.
Prove: indirizzo sito 10/10 radice e cartella, firma 14/14 (la prova vecchia contava "Installa ora" come pulsante di chiusura: corretta),
versione sito 5/5, install 37/37 radice e cartella, aggiornamento 2.3.2 -> 2.4.0 13/13 con 24 foto uguali, addon 70/70, attacchi 261/261,
service account 8/8, telefono 49/49 a 390 e 768, sito 89/89, pacchetto unico 13/13.

Fatto: plumeCMS 2.3.2 (1 novembre): segnalazione di Danilo, la chiave del service account di Google si salvava ma il riquadro SEO restava
vuoto: `admin-header.php` sovrascriveva `$sa` della pagina con il suo sottomenu. Variabili del menu con prefisso e `unset`. Link
`projectcreate` nel passo 1. Prove: service account 8/8 (4 KO col menu vecchio), install 37/37 radice e cartella, aggiornamento 2.3.1 -> 2.3.2
13/13 con 24 foto uguali, addon 70/70, attacchi 261/261, telefono 49/49 a 390 e 768, sito 89/89, pacchetto unico 13/13.
Danilo ha incollato la chiave privata del service account in chat e ha scelto di tenerla (avvisato del rischio, proposta l'autorizzazione Limitata).

Fatto: plumeCMS 2.3.1 (1 novembre): segnalazione di Danilo, "Scarica l'aggiornamento" dava la 2.2.0 dopo aver caricato la 2.3.0.
Causa: `/scarica/<serie>/` mandava il file con `public, max-age=3600` e il browser ridava la copia vecchia. Ora 302 con `no-store` verso
`/scarica/<versione>/` (`sito/index.php`). `prova-download.js` aggiornata (mancava il file manuale-tecnico dalla 1.6.4, link senza la cartella):
36/36 radice e cartella. Install 37/37, aggiornamento 2.3.0 -> 2.3.1 13/13 con 24 foto uguali, addon 70/70, attacchi 261/261, sito 89/89,
pacchetto unico 13/13. Danilo ha caricato la 2.3.1 ("tutto ok") e ha provato **Installa ora** su un hosting vero: funziona.

Fatto: plumeCMS 2.3.0 (31 ottobre, notte): la versione sempre in alto a sinistra del pannello (`#admin-versione`, al telefono
`.admin-versione-tel`) e, nel banner della dashboard dopo "Visita il sito ufficiale plumeCMS", "Nuova versione disponibile v. x.x.x" con
**Installa ora** (azione `versione_installa`: scarica, controlla, applica con snapshot; solo admin; con moduli del sito resta "Scarica e
prepara", `versione_installabile_qui()`). Prove: installa ora 11/11 (sito 2.2.0 e ufficiale finto 2.3.0), install 37/37 radice e
cartella, aggiornamento 2.2.0 -> 2.3.0 13/13 con 24 foto uguali, addon 70/70, attacchi 261/261, nomi doppi 9/9, telefono 49/49 a 390 e
768, sito di plumeCMS 89/89, pacchetto unico su un sito 2.2.0 13/13. `prova-versione.js` adattata al banner nuovo ma non rilanciata.

Fatto: plumeCMS 2.2.0 (31 ottobre, sera): Scegli dal sito (richiesta di Danilo, agente in worktree rifatto da me): `includes/collegamenti.php`,
`admin/link-api.php`, `admin/assets/scegli-link.js` (`window.pnLink`, `data-link-sito`), filtro `collegamenti_sito` per gli addon, in tutti i
campi link (editor, Quill, popup, form, newsletter, social, SEO, menu, privacy di eventi e prenotazioni); il valore è `/percorso/` (la
cartella la mette `cms_con_cartella()`), nelle email l'indirizzo intero. Il redirect 301 da solo c'è per pagine e articoli pubblicati, non per
le bozze né per i contenuti degli addon. Poi: Controlla il server con il gruppo "Google e IndexNow" (`server_servizi_esterni()`), certificato
HTTPS non valido detto per nome, `email_a` della Richiesta di prenotazione `solo_admin` (`editor_blinda_solo_admin()`), nomi doppi
dell'editor risolti con `'prima'` (anche per dimensioni e misure), PDF del manuale a 7,3 MB (JPEG a qualità 55). Prove: scegli link 80/80 radice
e 81/81 cartella, attacchi link 32/32, solo admin 6/6, nomi doppi 9/9, versione HTTPS 5/5, controlla server 21/21, install 37/37 radice e
cartella, aggiornamento 2.1.0 -> 2.2.0 13/13 (foto uguali tranne il cursore del Titolo animato, che prima non partiva), addon 70/70,
attacchi 261/261, Scegli dai Media 112/112, telefono 72/72 a 390 e 768, hero 670/670.

Fatto: plumeCMS 2.1.0 (31 ottobre): i punti scelti da Danilo dall'elenco del sospeso, con quattro agenti e uno per i widget, tutto
rifatto e provato da me sul codice unito. Consegna delle email (Impostazioni > Consegna delle email, Brevo, SES, Mailgun, rimbalzi,
riscaldamento), prenotazioni dal pannello con .ics, recensioni dopo gli eventi, corsi con l'editor e trascinamento, Google Drive e
Dropbox, "Solo per i membri" dalla scheda e riservati fuori da ricerca, blog, archivi, widget, feed, sitemap (`sito_contenuti_elenco()`),
ripristino pulito, Scegli dai Media dappertutto, cinque widget nuovi (92) e form con Ora e File, manuale d'uso in un PDF (8089 KB, tetto
8 MB), copia e snapshot con le colonne generate (`agg_colonne_generate()`: prima errore 3105). Il backend nuovo l'ha fermato Danilo.
Prove: install 37/37 radice e cartella, aggiornamento 2.0.3 -> 2.1.0 13/13 con 20 foto uguali, addon tutti 70/70, attacchi 261/261,
widget 68/68 (radice e cartella) e 40/40, membri 95/95, telefono 49/49 a 390 e 768, Scegli dai Media 112/112, hero 670/670,
colonne generate 5/5, sito di plumeCMS 87/87. Consegna con `consegna.py --uno`: un pacchetto unico da 14,3 MB (il motore è cambiato
solo nei dump, nessuna voce nuova del manifest).

Fatto: plumeCMS 2.0.3 (31 ottobre): l'immagine per i social (Danilo: su LinkedIn niente immagine, mentre i suoi siti WordPress sullo
stesso hosting vanno, quindi non è l'hosting). Il sito di plumeCMS era ancora senza la 2.0.x e aveva `og:image` con le barre doppie
(`https://plumecms.danilofailli.it//uploads/...`); pagina e immagine arrivano al robot di LinkedIn con 200. Ora barre doppie corrette,
`og:image:secure_url`, `twitter:image`, `prefix` di Open Graph su `<html>`. Non provato contro LinkedIn (la sessione non lo raggiunge).
Aggiornamento 2.0.2 -> 2.0.3 13/13 con 20 foto uguali, install 37/37 radice e cartella, pulsanti 670/670.
Fatto: plumeCMS 2.0.2 (31 ottobre): i link scritti a mano (segnalazione di Danilo: "i pulsanti della hero non funzionano").
`editor_link_sicuro()` scartava in silenzio `laser-coaching`, `www.sito.it`, `sito.it/pagina` e il pulsante puntava a `#`, in tutti i
widget; ora diventano `/laser-coaching` e `https://...`, gli schemi pericolosi restano fuori. `prova-hero-pulsanti.js` 670/670
(11 disposizioni per 6 effetti più i link a mano, 1280 e 390 px, senza tema e con Cosmo, Aria, Bottega in anteprima `?preview=1&tema=`):
nessun pulsante coperto da velo, foto o onda. Attacchi 261/261, install 37/37 radice e cartella, aggiornamento 2.0.1 -> 2.0.2 13/13 con
20 foto uguali. danilofailli.it non si apre dalla sessione (proxy 403): la sua pagina non è stata guardata.
Fatto: plumeCMS 2.0.1 (30 ottobre, notte): il pannello al telefono (richiesta di Danilo). `.admin-layout` a `minmax(0, 1fr)` e
`.admin-main{min-width:0}` (la colonna `1fr` si allargava per l'elemento più largo: 29 pagine su 70 scorrevano di lato), sotto 640 px
form, etichette, filtri, `nav` e schede vanno a capo. `prove/prova-pannello-telefono.js` 81/81 a 390, 768 e 1280 px (menu più venti
pagine interne, otto addon accesi), al computer 12 pagine identiche al pixel; aggiornamento 2.0.0 -> 2.0.1 13/13 con 20 foto del sito
uguali; install 37/37 radice e cartella. Solo `admin.css`: niente parti nuove del sito.
Fatto: plumeCMS 2.0.0 beta (30 ottobre, sera; la 1.11.0 non è uscita, decisione di Danilo: dentro la 2.0.0). Base degli addon
(`includes/addon.php`, `admin/addon.php`, `admin/addon-pagina.php`, `cron_spesso`, manuale tecnico 32, `docs-sviluppo/cms-moduli.md`) e
otto addon scelti da Danilo, fatti da tre agenti in worktree e rifatti da me: Prenotazioni 60, Eventi con iscrizione 45, Recensioni 39
(agente B, attacchi 80), Vetrina 60, Statistiche 32 (agente C, attacchi 94), Area riservata 75, Corsi 58, Copia altrove 39 (agente A,
attacchi 108); `prova-addon-tutti.js` 68/68 su sito nuovo e su sito aggiornato dalla 1.10.0 (salva una persona nelle Prenotazioni:
il difetto delle pagine vuote, trovato dall'agente del manuale). Manuale d'uso con la Parte seconda (capitoli 32-40) in due PDF:
`manuale-uso.pdf` 1889 KB (11 KB di margine: la prossima aggiunta non ci sta) e `manuale-uso-addon.pdf` 1330 KB; sito di plumeCMS in
cinque parti 87/87 (server a 2 MB). Dettaglio in `docs-sviluppo/addon/<nome>.md`.
Correzioni della newsletter (agente, `prova-newsletter-difetti.js` 37/37), file riservati e "Dopo la conferma, dai questo file"
(`prova-omaggio.js` 27/27, attacchi gruppo 27), Scegli dai Media e og:image intero con misure (manuale tecnico 33-34). Attacchi
261/261, install 37/37 radice e cartella dallo zip, aggiornamento 1.10.0 -> 2.0.0 12/13 (il KO vuole un sito di prima della 1.4.0:
riquadri uguali prima e dopo) con 20 foto identiche al pixel. Limiti della beta: niente pagamento con carta, Google Drive e Dropbox,
prenotazione inserita dal pannello, inviti alle recensioni dopo gli eventi; alcune pagine del nucleo (Articoli, SEO, Form, Newsletter,
Media, Download, Utenti) scorrono ancora di lato al telefono (c'era già prima).
1.10.0 (30 ottobre): sette widget per le attività scelti da Danilo (Orari di apertura con "Aperto ora" calcolato
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
- `CMS_PREFISSO` ha come valore predefinito `newdf_` perché danilofailli.it non lo
  scrive nel suo config. L'installatore lo scrive sempre, quindi le installazioni
  nuove non ne risentono. Se un giorno si cambia il predefinito, prima va aggiunto
  `define('CMS_PREFISSO', 'newdf_');` al `config.local.php` di danilofailli.it;
- Google Search Console e IndexNow su un server vero: dalla 2.2.0 li prova Impostazioni > Controlla il server (gruppo "Google e
  IndexNow": il service account legge le proprietà, il file della chiave si legge da fuori, api.indexnow.org risponde). Da fare
  sul sito di Danilo con i suoi dati;
- **indirizzo del sito ufficiale** per "c'è una versione nuova": è solo `VERSIONE_SITO_UFFICIALE` (`includes/versione-nuova.php`),
  dalla 2.4.0 `https://plumecms.it` (prima `https://plumecms.danilofailli.it`), nessun campo nel pannello (decisione di Danilo);
- "c'è una versione nuova" in HTTPS: dalla 2.2.0 provato con un certificato vero firmato da un'autorità di prova
  (`prove/prova-versione-https.py`: valido, autorità sconosciuta, altro nome, scaduto); manca la prova contro plumecms.danilofailli.it;
- import da WordPress provato contro un WordPress locale (6.6.2 vero), non su un hosting vero: da fare con uno dei siti WordPress di
  Danilo; Apache, SMTP vero e Imagick non provati per la 1.4.0;
- `tools/` e `prove/` hanno ancora riferimenti a danilofailli.it (strumenti di sviluppo, non vanno nello zip): `--sito`, la prova del burnout;
- i temi a danilofailli.it arrivano solo con un pacchetto `--sito` dal suo repository (mai lo zip del prodotto).

## Rapporto con danilofailli.it

**Regola di Danilo (28 ottobre 2026): non chiedergli più nulla di danilofailli.it.** Se servono informazioni, le dà lui.
**Regola di Danilo (31 ottobre 2026), ripetuta più volte: "devi lasciar stare danilofailli.it. Se mi serve, te lo chiedo io."**
Non proporre controlli, pacchetti, aggiornamenti o cose da fare su danilofailli.it, non metterlo negli elenchi di cose in sospeso,
non nominarlo nelle risposte. Se ne parla solo quando è Danilo a chiederlo.
Il 28 ottobre ha detto di aver già aggiornato tutto su danilofailli.it.

- danilofailli.it usa plumeCMS con il modulo `moduli/danilofailli/`. **Dal 28 ottobre 2026 gira dalla radice** (detto da Danilo il
  31 ottobre: pacchetti 18 e 18-b del suo repository): pannello in `danilofailli.it/admin/`, `/new/` e `/new/admin/` rimandano agli
  indirizzi senza `/new`, la cartella `new/` resta sul server solo per i rimandi. Il `new/` nei manifesti dei pacchetti è la cartella
  del CMS dovunque sia (`includes/aggiornamenti.php`), non la cartella `/new/` del sito.
- **28 ottobre 2026, sera**: Danilo ha applicato lo zip di aggiornamento del prodotto 1.6.0 a danilofailli.it e il pannello
  lo ha preso. (Quello che segue era vero prima del trasloco nella radice, ora superato: pagine servite da WordPress e `/new/`
  statico.) Rischio: email del mattino
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
| Un agente con `isolation: worktree` | la sua worktree nasce dal primo commit del repository, senza il CMS: tutti e tre gli agenti degli addon hanno dovuto fare `git reset --hard` sul ramo di lavoro. Nel compito si scrive da quale commit partire |
| Un agente con `isolation: worktree` lanciato mentre la cartella di lavoro è un altro repository | la sua worktree nasce in quel repository (il 31 ottobre 2026 dentro il second brain): prima di lanciarlo, `cd /home/user/PlumeCMS`; nel compito, "se il commit di partenza non esiste, fermati" |
| `quanto-foto.py` con due file | vuole due **cartelle** di schermate, non due file; senza differenze non stampa niente |
| `prova-install.js` in cartella | la base è `http://127.0.0.1:<porta>/test` e il quinto argomento resta vuoto (il quinto è per il CMS in una sottocartella con il sito nella radice) |
| Un `fieldset` o un `<code>` con `white-space:nowrap` in una pagina del pannello | il fieldset non si stringe sotto il suo contenuto e la pagina scorre di lato al telefono: dalla 2.0.0 `admin.css` mette `fieldset{min-width:0}` e manda a capo i codici nelle tabelle |
| Una variabile in cima a un file di widget (`$r`, `$f`, `$file`) | il file si caricava nello spazio di `editor_carica_widget_esterni()` e ne sovrascriveva il registro: con una persona nelle Prenotazioni tutte le pagine del sito uscivano vuote (2.0.0, trovato dall'agente del manuale, non dalle prove degli addon). Ora ogni file si carica in una funzione sua; le prove degli addon mettono dati veri prima di guardare le pagine |
| Centinaia di server di prova accesi (agenti e giri di prove) | le porte si esauriscono: "Address already in use" e la prova fallisce in modo strano. Alla fine di un lavoro si spengono i `php -S` e i server finti che non servono (cercando il pid con `ps -eo pid=,comm=,args=` e `comm` uguale a php, mai `pkill -f`) |
| `tema_attivo` scritto da riga di comando per provare un tema | non basta: il tema si applica dal pannello (copia stile e modelli). Per le prove si usa l'anteprima da amministratore, `?preview=1&tema=<slug>` |
| Un link scritto senza `https://` o senza `/` in un widget | fino alla 2.0.1 `editor_link_sicuro()` lo scartava in silenzio e il pulsante puntava a `#` ("i pulsanti non funzionano") |
| Una tabella con colonne generate (`GENERATED ALWAYS AS`) in un dump | l'INSERT con il valore della colonna fallisce (MySQL 3105): ripristino della copia "con errori" e snapshot non ripristinabile con dati in Eventi o Prenotazioni (fino alla 2.0.3). Nei dump si scrive `DEFAULT` (`agg_riga_sql()`) |
| `tbl()` in uno script di prova | restituisce il nome fra apici inversi: per `SHOW COLUMNS` o funzioni che li aggiungono si tolgono con `trim($t, '`')` |
| Una variabile con un nome comune in un file incluso dalle pagine (`admin-header.php`: `$sa`, `$v`, `$nome`) | sovrascrive quella della pagina che lo include: il service account di Google salvato non compariva in SEO (fino alla 2.3.1). Nei file inclusi, nomi con prefisso (`$_menu...`) e `unset` alla fine. E una prova che cerca un testo deve guardare nel riquadro giusto: il messaggio verde conteneva la stessa email e la prova passava lo stesso |
| Una funzione nuova in un file usata da un altro file nella stessa versione | subito dopo l'aggiornamento opcache (revalidate_freq 2 s, su alcuni hosting mai) può dare il file vecchio insieme al nuovo: "Call to undefined function", home in 500 (2.5.0, trovato dalla prova del pacchetto unico una volta su due). Dalla 2.5.0 il motore chiama `opcache_invalidate` sui file scritti; ma l'aggiornamento lo applica il motore vecchio, quindi chi chiama una funzione nuova di un altro file mette una copia di riserva con `function_exists`. E `php -S` usa opcache (`opcache.enable`, non `enable_cli`) |
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
