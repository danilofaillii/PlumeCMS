# Newsletter

La newsletter sta dentro il CMS, senza servizi esterni (deciso il 2026-10-23). Motore in
`includes/newsletter.php`, pannello voce **Newsletter** (`admin/newsletter.php`, editor in
`admin/newsletter-campagna.php`), invio in `cron-newsletter.php`.

## Iscritti

Tabella `<prefisso>newsletter_iscritti`, stati `in_attesa`, `attivo`, `uscito`. Due strade per entrare:

| Fonte | Come | Stato |
|---|---|---|
| `modulo` | il widget Modulo della newsletter, in una pagina o in un popup (`moduli.md`), email "Conferma l'iscrizione", clic su `/newsletter-conferma/?t=...` (7 giorni) | attivo dopo la conferma |
| `brevo` | CSV esportato da Brevo (o da un servizio con le stesse colonne), importato dal pannello con la spunta sul consenso | attivo; chi è già nell'elenco resta com'è, chi è uscito resta fuori |

Un modulo del sito può aggiungere altre strade (per esempio una spunta in un modulo suo) con i filtri di `moduli.md`.

Uscita: in fondo a ogni email, `/newsletter-esci/?i=ID&t=TOKEN` (token HMAC, niente nel database).
La pagina chiede conferma con un pulsante: i filtri antispam aprono i link delle email e farebbero
uscire chi non lo ha chiesto. I programmi di posta usano invece l'intestazione `List-Unsubscribe` con
`List-Unsubscribe-Post: List-Unsubscribe=One-Click`, che esce con un POST diretto. Chi esce perde
anche le email ancora in coda. Dal pannello: "Togli" (esce) e "Cancella dati" (diritto alla cancellazione).

## Liste (aggiunte il 2026-10-23, sera)

Un iscritto può stare in più liste, una campagna va a liste specifiche.
Tabelle `<prefisso>newsletter_liste` e `<prefisso>newsletter_iscritti_liste`; nelle campagne la colonna `liste`
(id separati da virgola).

- Una lista di sistema, creata da sola e con l'id nell'impostazione `newsletter_lista_principale`: si chiama
  come la newsletter ("Newsletter", più il nome dato in `newsletter_nome`) e raccoglie le iscrizioni dal sito
  e gli iscritti di prima delle liste. Si può rinominare, non eliminare.
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
campagna su due liste partita a 12 persone (chi stava in entrambe una
volta, chi era uscito escluso), lista di sistema non eliminabile, campagna con lista eliminata tornata bozza.

## Campagne

Editor Quill 2.0.3, servito dal pannello (`admin/assets/quill/`, licenza BSD-3): titoli, grassetto,
link, elenchi, citazioni, allineamento, immagini caricate in `newsletter/AAAA/MM/` dentro la cartella delle immagini (`cms_uploads_dir()`).
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
(coda in `<prefisso>newsletter_coda` con gli iscritti attivi in quel momento), poi manda fino a 40 email
senza superare il limite orario (pannello, Newsletter, Impostazioni; predefinito 150). Lock MySQL per
non sovrapporre due giri. Tre tentativi per indirizzo, poi errore. Registro in `logs/newsletter.log`.

## Collaudo del 2026-10-23 (locale, MySQL 8.0.46, browser vero)

Iscrizione, conferma, importazione CSV (riga senza email scartata, emoji nel nome
tolta), editor con titolo, elenco, grassetto, immagine, pulsante, prova, invio a 5 iscritti con nome
nell'oggetto e nel testo, `List-Unsubscribe` presente, uscita con pulsante e in un clic, token falso
respinto, reimportazione che lascia fuori chi è uscito, limite orario di 10 rispettato.
Contratto degli indirizzi: 0 differenze.

## Aperto

- Il limite orario delle email del proprio hosting: da chiedere al fornitore, poi da scrivere nelle Impostazioni.
- Aperture e clic: dal 2.0 si contano, senza servizi esterni: aperture con un pixel, clic con i link che passano da
  `api/nl.php` firmati; si vedono in Newsletter > Statistiche (capitolo 15.5 del manuale tecnico). Aggiornato al 31 ottobre 2026.
- Informativa privacy da aggiornare con la newsletter interna.
- SPF, DKIM e DMARC del dominio: decidono se le email arrivano in posta o nello spam.
