# Newsletter FuoriDalCoso, senza Brevo

Deciso da Danilo il 2026-10-23: Brevo si dismette, anche per la newsletter. Motore in
`includes/newsletter.php`, pannello voce **Newsletter** (`admin/newsletter.php`, editor in
`admin/newsletter-campagna.php`), invio in `cron-newsletter.php`.

## Iscritti

Tabella `newdf_newsletter_iscritti`, stati `in_attesa`, `attivo`, `uscito`. Tre strade per entrare:

| Fonte | Come | Stato |
|---|---|---|
| `modulo` | modulo su `/fuoridalcoso-news-letter/` (al posto del modulo Brevo), email "Conferma l'iscrizione", clic su `/newsletter-conferma/?t=...` (7 giorni) | attivo dopo la conferma |
| `ebook` | spunta facoltativa nel modulo dell'ebook; l'email è confermata dal link dell'ebook | attivo alla conferma dell'ebook |
| `brevo` | CSV esportato da Brevo, importato dal pannello con la spunta sul consenso | attivo; chi è già nell'elenco resta com'è, chi è uscito resta fuori |

Ogni modulo Brevo rimasto nelle pagine migrate lascia il posto al nostro: con il campo SMS diventa
Laser Coaching, senza diventa newsletter (`vista_moduli_in_pagina()`).

Uscita: in fondo a ogni email, `/newsletter-esci/?i=ID&t=TOKEN` (token HMAC, niente nel database).
La pagina chiede conferma con un pulsante: i filtri antispam aprono i link delle email e farebbero
uscire chi non lo ha chiesto. I programmi di posta usano invece l'intestazione `List-Unsubscribe` con
`List-Unsubscribe-Post: List-Unsubscribe=One-Click`, che esce con un POST diretto. Chi esce perde
anche le email ancora in coda. Dal pannello: "Togli" (esce) e "Cancella dati" (diritto alla cancellazione).

## Liste (aggiunte il 2026-10-23, sera)

Richiesta di Danilo: un iscritto può stare in più liste, una campagna va a liste specifiche.
Tabelle `newdf_newsletter_liste` e `newdf_newsletter_iscritti_liste`; nelle campagne la colonna `liste`
(id separati da virgola).

- Due liste di sistema, create da sole e con l'id nelle impostazioni (`newsletter_lista_principale`,
  `newsletter_lista_ebook`): **Newsletter FuoriDalCoso** (iscrizioni dal sito; tutti gli iscritti di prima
  delle liste finiscono qui) e **Lettori ebook** (consenso dato nel modulo dell'ebook, che entra anche nella
  principale). Si possono rinominare, non eliminare.
- Le altre liste si creano, rinominano ed eliminano dalla scheda Liste. Eliminare una lista toglie solo
  l'appartenenza: gli iscritti restano.
- Importazione da Brevo: lista di arrivo a scelta, oppure una lista nuova. Chi è già presente entra
  nella lista e mantiene il suo stato.
- Scheda Iscritti: filtro per lista (anche "in nessuna lista"), liste del singolo iscritto modificabili,
  "Aggiungi/Togli tutti i risultati" della ricerca a una lista.
- Campagna: si spuntano le liste; il numero di destinatari (attivi, contati una volta) si aggiorna al
  salvataggio. Senza liste non parte. Se al momento dell'invio le sue liste non esistono più, torna bozza.
- L'uscita resta unica: chi esce non riceve più niente da nessuna lista.

Collaudo: aggiornamento da un database con la newsletter senza liste (35 iscritti nella principale),
lista creata e doppione rifiutato, 11 iscritti aggiunti con la ricerca, importazione in una lista nuova,
ebook con consenso in due liste, campagna su due liste partita a 12 persone (chi stava in entrambe una
volta, chi era uscito escluso), lista di sistema non eliminabile, campagna con lista eliminata tornata bozza.

## Campagne

Editor Quill 2.0.3, servito dal pannello (`admin/assets/quill/`, licenza BSD-3): titoli, grassetto,
link, elenchi, citazioni, allineamento, immagini caricate in `wp-content/uploads/newsletter/AAAA/MM/`.
Più oggetto, testo di anteprima, pulsante finale facoltativo. `{nome}` si sostituisce con il nome
dell'iscritto in oggetto, anteprima e testo. Le emoji si salvano come entità HTML (colonne utf8 a 3 byte)
e tornano caratteri nell'oggetto.

All'invio `newsletter_corpo_email()` porta l'HTML dell'editor in forma da email: liste vere (Quill 2
scrive tutto come `<ol>`), stili scritti sugli elementi, via script e classi.

Stati: `bozza` → `programmata` (giorno e ora, oppure "Invia adesso") → `in_invio` → `inviata`.
Durante l'invio si può sospendere e riprendere. "Duplica" per ripartire da una campagna inviata.
"Salva e manda la prova" spedisce a un indirizzo con `[PROVA]` nell'oggetto.

## Invio

`cron-newsletter.php`, da cPanel ogni 5 minuti. Ogni giro: avvia le campagne arrivate alla loro ora
(coda in `newdf_newsletter_coda` con gli iscritti attivi in quel momento), poi manda fino a 40 email
senza superare il limite orario (pannello, Newsletter, Impostazioni; predefinito 150). Lock MySQL per
non sovrapporre due giri. Tre tentativi per indirizzo, poi errore. Registro in `logs/newsletter.log`.

## Collaudo del 2026-10-23 (locale, MySQL 8.0.46, browser vero)

Iscrizione, conferma, ebook con consenso, importazione CSV (riga senza email scartata, emoji nel nome
tolta), editor con titolo, elenco, grassetto, immagine, pulsante, prova, invio a 5 iscritti con nome
nell'oggetto e nel testo, `List-Unsubscribe` presente, uscita con pulsante e in un clic, token falso
respinto, reimportazione che lascia fuori chi è uscito, limite orario di 10 rispettato.
Contratto degli indirizzi: 0 differenze.

## Aperto

- Il limite orario delle email del piano Hostcare: da chiedere, poi da scrivere nelle Impostazioni.
- Aperture e clic: non si contano (scelta di privacy, e niente servizi esterni).
- Informativa privacy da aggiornare con la newsletter interna.
- SPF, DKIM e DMARC del dominio su Hostcare: decidono se le email arrivano in posta o nello spam.
