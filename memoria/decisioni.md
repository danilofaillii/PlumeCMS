# Decisioni

Solo in aggiunta: una decisione superata non si cancella, se ne aggiunge una nuova con il riferimento.

## 2026-10-23 — Un editor delle pagine come Elementor, con la nostra tecnologia
- **Decisione (Danilo):** le pagine si costruiscono con un editor a sezioni, colonne e widget, tre misure, bozza e pubblicazione, revisioni.

## 2026-10-25 — L'editor è la copia di Elementor, con stile globale, header e footer, SEO nel pannello
- **Decisione (Danilo):** "L'editor deve essere la copia esatta di Elementor"; un menu per le variabili globali (colori, caratteri, H1..H6); più header e più footer con i predefiniti e la scelta per ogni contenuto; una pagina per Search Console, Bing e tutto ciò che riguarda SEO e AEO.
- **Come:** controlli di gruppo; schede Stile e Avanzate; Stile del sito con variabili CSS; modelli header e footer con condizioni a priorità; trenta widget; 252 icone; voce SEO e AEO.

## 2026-10-25 — Le infografiche restano del loro tipo
- **Decisione (Danilo):** ogni infografica torna nell'editor con il suo tipo e il suo aspetto, ogni oggetto modificabile, 10 temi preimpostati e temi salvabili con nome.

## 2026-10-26 — Versioni di ogni modifica e un solo menu Editor
- **Decisione (Danilo):** "versioning per tutte le modifiche all'editor"; "tutte le impostazioni dell'editor in un unico menu".

## 2026-10-26 — Il publisher diventa un CMS installabile
- **Decisione (Danilo):** "mettendolo su una root di un sito io possa installarlo come fosse un CMS con editor pazzesco".
- **Come:** una sola base di codice, nucleo generico più moduli; installatore in cinque passi; prefisso e base URL configurabili.

## 2026-10-26 — A prova di attacco prima di pubblicare
- **Decisione (Danilo):** "voglio che il CMS sia a prova di hackeraggio".
- **Come:** audit (21 problemi), blindatura, tre giri di attacco con agenti nuovi fino a zero brecce sopra il livello basso; 2FA, blocchi dei tentativi, argon2id, CSP a nonce, pagina Sicurezza, prove di attacco automatiche.
- **Scelte fatte senza conferma esplicita di Danilo:** il redattore gestisce solo contenuti, media, categorie e tag; blocco per nome utente a 20 tentativi con passaggio per il proprietario dagli IP già noti; CSP che parte in "solo rapporto" sui siti che si aggiornano e in "applica" sulle installazioni nuove.

## 2026-10-26 — Il nome è plumeCMS
- **Decisione (Danilo):** il prodotto si chiama plumeCMS. Sostituisce "CarbonaraCMS", deciso poche ore prima, che sostituiva il provvisorio "Officina".

## 2026-10-26 — plumeCMS diventa un progetto a sé
- **Decisione (Danilo):** "spostiamo l'editor in un nuovo progetto". Il CMS (solo nucleo) esce dal repository di danilofailli.it in un progetto suo, con memoria e documenti propri. danilofailli.it resta la prima installazione, con il suo modulo nel suo repository.

## 2026-10-27 - plumeCMS in un progetto Claude a sé
Danilo: "Ora possiamo spostare il progetto del CMS in un suo progetto Claude". Da qui plumeCMS si sviluppa nel suo progetto e nel suo repository; danilofailli.it resta la prima installazione e riceve le versioni nuove come pacchetti con il suo modulo.
