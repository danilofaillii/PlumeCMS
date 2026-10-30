# Addon Vetrina (2.0.0): documento tecnico

Un piccolo negozio dentro plumeCMS: catalogo con foto, varianti, scorte e categorie, carrello senza account, richiesta
d'ordine, ordini nel pannello con gli stati e le email. Si accende da Impostazioni > Addon (`cms/addon/vetrina/`).

**Pagamenti, decisione presa:** nessuna API di pagamento e nessuna chiave segreta. I metodi sono bonifico (IBAN e causale
con il numero d'ordine), pagamento alla consegna o al ritiro, link di pagamento dell'attività (PayPal.me con l'importo
aggiunto all'indirizzo, oppure un Payment Link di Stripe). Il pagamento lo conferma l'attività a mano, con lo stato «Pagato».

## File

| File | Cosa fa |
|---|---|
| `cms/addon/vetrina/modulo.php` | manifesto: menu (gruppo Vetrina dopo Media, solo amministratore), pagine del pannello, widget, evento `sito_percorso`, filtri `sitemap_voci` e `dashboard_numeri`, passo del cron |
| `includes/vetrina.php` | il motore: tabelle, testi e soldi, impostazioni, categorie, prodotti, varianti, carrello firmato, calcolo dei totali, ordini, scorte, email |
| `includes/sito.php` | le pagine pubbliche (elenco, scheda, carrello, ordine, ordine ricevuto, condizioni) |
| `includes/widget.php` | i widget dell'editor `vetrina_prodotti` e `vetrina_carrello` (gruppo «Negozio») |
| `includes/admin-comune.php` | pezzi comuni del pannello (indirizzi, stile, badge degli stati) |
| `includes/admin-ordini.php` | Vetrina > Ordini: elenco con filtri e scheda dell'ordine con il cambio di stato |
| `includes/admin-prodotti.php`, `admin-prodotto.php` | elenco dei prodotti e scheda di un prodotto (foto, varianti, categorie) |
| `includes/admin-categorie.php` | categorie |
| `includes/admin-impostazioni.php` | impostazioni del negozio |
| `assets/vetrina.css`, `assets/vetrina.js` | aspetto (variabili del sito, quindi del tema) e piccoli aiuti nel browser; senza script funziona tutto |

## Tabelle (prefisso del sito davanti, utf8 come il nucleo)

Create da `vt_installa()` (`includes/vetrina.php:107`) all'accensione; `vt_schema()` (`:114`) le ricrea se l'impronta
salvata in `vetrina_schema` non torna.

- `vetrina_categorie`: id, nome, slug (unico), descrizione, ordine.
- `vetrina_prodotti`: id, nome, slug (unico), breve, descrizione (testo semplice), `prezzo_cent`, `scontato_cent` (NULL = niente
  sconto), aliquota (IVA compresa, %), foto (JSON di indirizzi della cartella delle immagini), opzione1 e opzione2 (nomi delle
  caratteristiche delle varianti), scorta (NULL = non contata), esaurito, evidenza, pubblicato, codice, ordine, creato, aggiornato.
- `vetrina_varianti`: id, prodotto_id, valore1, valore2, `prezzo_cent` (NULL = quello del prodotto), scorta (NULL = non contata),
  attiva (una variante tolta resta, spenta, per i vecchi ordini), ordine.
- `vetrina_prodotti_categorie`: prodotto_id, categoria_id.
- `vetrina_ordini`: id, numero (unico, `AAAA-NNNN`), stato, nome, email, telefono, indirizzo, cap, citta, consegna, metodo, note,
  righe (JSON: copia di nome, variante, prezzo, quantità e totale al momento dell'ordine), subtotale, spedizione, totale e IVA in
  centesimi, consenso (data), storia (JSON degli stati con la data), ip (solo per il limite all'ora; il cron lo toglie dopo 7 giorni),
  creato, aggiornato.

Impostazioni (tabella `settings`): `vetrina_impostazioni` (JSON, predefiniti in `vt_impostazioni_predefinite()` `:155`),
`vetrina_segreto` (chiave HMAC di carrello e link degli ordini, `vt_segreto()` `:498`), `vetrina_schema`.

## Regole di sicurezza (non si cambiano)

- **Il prezzo lo decide il server.** Il browser manda solo prodotto, variante e quantità. `vt_carrello_calcola()` (`:580`)
  rilegge tutto dal database a ogni pagina; `vt_ordine_crea()` (`:669`) ricalcola di nuovo prima di scrivere l'ordine.
  Un campo `prezzo` o `totale` nel modulo non si legge mai.
- **Carrello**: cookie `vetrina_carrello` = base64url(JSON delle righe `[prodotto, variante, quantità]`) + `.` + HMAC-SHA256
  (`vt_carrello_leggi()` `:505`, `vt_carrello_scrivi()` `:522`). Firma sbagliata = carrello vuoto. HttpOnly, SameSite=Lax,
  Secure su https, 30 giorni, al massimo 30 righe e 99 pezzi a riga. Nasce solo quando si aggiunge un prodotto: chi guarda
  e basta non riceve cookie.
- **Varianti**: `vt_carrello_aggiungi()` (`:541`) accetta solo una variante attiva di quel prodotto; un prodotto con varianti
  ne vuole una; esaurito o non pubblicato: rifiutato.
- **Scorte**: scendono in una transazione con `UPDATE ... SET scorta = scorta - q WHERE ... AND scorta >= q`; se una riga non
  si aggiorna, niente ordine («è appena finito»). Annullare rimette i pezzi (`vt_ordine_stato()` `:761`), togliere l'annullamento
  li riprende se ci sono.
- **Ordine**: captcha dei moduli (`moduli_captcha_valido()`, una sfida vale una volta), campo esca `sito_web`, consenso
  obbligatorio, al massimo 5 richieste per IP in un'ora (`vt_troppi()` `:622`), campi controllati in `vt_ordine_leggi()` (`:633`).
- **Testi**: `vt_testo()` (`:126`) toglie caratteri di controllo (gli a capo solo nei campi a più righe) e i caratteri a 4 byte;
  tutto si scrive con `h()`. Email: `vt_rispondi_a()` (`:848`) mette nel Reply-To solo un indirizzo valido senza a capo;
  l'oggetto passa da `posta_invia()`, che lo codifica.
- **Link di pagamento**: `vt_link_valido()` (`:224`) vuole `https://`, un dominio vero, niente credenziali, niente spazi o
  virgolette. `javascript:`, `data:`, `http:` si rifiutano già nel pannello.
- **POST pubblici**: `sicurezza_origine_ok()` del nucleo (Origin o Referer dello stesso sito), altrimenti 403.
- **Pagina «ordine ricevuto»**: solo con `k` = HMAC di numero ed email (`vt_ordine_firma()` `:730`), altrimenti 404.
- **Pannello**: tutte le pagine passano da `admin/addon-pagina.php` (solo amministratore); ogni azione in POST con `verify_csrf()`.

## Indirizzi pubblici

Base configurabile (`percorso`, predefinito `negozio`): `vt_base()` (`:185`). L'evento `sito_percorso` del nucleo chiama
`vt_sito_rispondi()` (`includes/sito.php:22`), che risponde con `sito_html()` del nucleo (header, footer, tema, CSP a nonce,
cartella del sito con `cms_con_cartella()`).

| Indirizzo | Funzione |
|---|---|
| `/negozio/` (`?categoria=<slug>`, `?ordina=prezzo\|prezzo-giu\|nuovi`) | `vt_pagina_elenco()` `sito.php:96`; categoria che non c'è: 404 |
| `/negozio/<slug>/` | `vt_pagina_prodotto()` `:123`, con JSON-LD Product e Offer (o AggregateOffer se le varianti hanno prezzi diversi), `JSON_HEX_TAG` |
| `/negozio/carrello/` | `vt_pagina_carrello()` `:177`; POST `azione=aggiungi\|aggiorna\|togli` |
| `/negozio/ordine/` | `vt_pagina_ordine()` `:235`; POST: ordine, poi 303 alla pagina dell'ordine |
| `/negozio/ordine/fatto/?n=&k=` | `vt_pagina_fatto()` `:296` |
| `/negozio/condizioni/` | `vt_pagina_condizioni()` `:311` |

Il foglio e lo script si caricano con un `<link>` in testa e uno `<script src defer>` (stessa origine: la CSP li ammette).
I moduli usano le classi del sito (`.modulo`, `.modulo-campi`, `.spunta`, `.captcha`) e lo script `sito/assets/moduli.js` per il captcha.

## Email

`vt_email_ordine()` (`:854`): al cliente (riepilogo, come pagare, dove arriva) e al negozio (`vt_email_negozio()` `:840`:
`email_avvisi`, poi l'email dell'attività, poi `posta_notifica_email()`). `vt_email_stato()` (`:880`) a ogni cambio di stato,
se la casella «Manda un'email al cliente» è spuntata, con un messaggio facoltativo (per esempio il numero di spedizione).
Usano `posta_invia()` e `moduli_email_html()` del nucleo, tipo `vetrina` nel registro della posta. Nessun file di posta modificato.

## Widget

- `vetrina_prodotti`: quali (in evidenza, di una categoria, ultimi arrivati, in offerta), categoria (tendina con `accetta`
  `/^\d{0,10}$/`, così una categoria creata dopo non si perde), quanti, colonne, testo del link al negozio.
- `vetrina_carrello`: icona `carrello`, testo, numero di pezzi (dal cookie, senza query), allineamento.
- Il foglio: `editor_registra_asset($tipo, ['css' => '../../addon/vetrina/assets/vetrina.css'])`: il percorso parte da
  `sito/assets/` come quello dei widget del nucleo, il browser lo risolve in `/addon/vetrina/assets/vetrina.css`.
- Nota: la pagina Widget del pannello segna l'origine solo per i file in `moduli/<nome>/` (`editor-controlli.php:46`); i widget
  degli addon risultano «nucleo». Non toccato.

## Ganci del nucleo toccati

1. **Filtro nuovo `sitemap_voci`** in `seo_sitemap_voci()` (`cms/includes/seo.php:878`): i moduli e gli addon aggiungono
   voci alla sitemap nello stesso formato (`loc`, `lastmod`, `tipo`, `immagine`). La Vetrina aggiunge `/negozio/` e le schede
   (`vt_sitemap_voci()` `:480`). Con la sitemap divisa finiscono in `sitemap-pagine.xml`; vanno anche nella coda IndexNow
   come le altre voci della sitemap.
2. **Evento nuovo `sito_pagina_servita($codice, $percorso)`** in `sito_rispondi()` (`cms/sito/index.php:34-40`), usato
   dall'addon Statistiche (vedi `statistiche.md`).

Nessun altro file del nucleo è cambiato. Usati senza modifiche: `moduli.php` (captcha, `moduli_email_html`, `moduli_intestazioni`),
`posta.php` (`posta_invia`, `posta_notifica_email`), `media.php` (`media_carica` nella cartella `vetrina/`, con foto leggere e WebP),
`sicurezza.php` (`sicurezza_origine_ok`, `sicurezza_ip`, `sicurezza_https`).

## Cosa non c'è (in questa versione)

- Pagamenti con API (Stripe Checkout, PayPal con conferma automatica): niente chiavi segrete, decisione presa.
- Spedizioni fuori dall'Italia (il CAP è di 5 cifre), costi per peso o per zona, codici sconto, fatture.
- Account dei clienti e storico ordini per il cliente (il cliente ha il link firmato dell'ordine nell'email).
- I prodotti non entrano nella ricerca del sito (`/?s=`) né in `sito_contenuti()`: non sono un tipo di contenuto del nucleo.
- Una variante si descrive con al massimo due caratteristiche.

## Prove

- `prove/prova-addon-vetrina.js` (60 controlli; in radice e con il CMS in `/test`): uso: `node prove/prova-addon-vetrina.js <base>
  <utente> <password> <porta smtp> <cartella posta> <cartella del CMS installato>`, con `prove/smtp-finto.py` acceso.
  Crea la pagina di prova dei widget con `prove/pagina-vetrina.php` e la toglie prima del confronto finale.
- `prove/attacchi-addon-c.js` (94 controlli, anche per le Statistiche): da lanciare su un sito senza ordini nell'ultima ora.
