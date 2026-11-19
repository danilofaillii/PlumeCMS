# Controllo SEO profondo di plumeCMS (2.39.2), 19 novembre 2026

Sito di prova: kit Attività installato dallo zip, tutti e dieci gli addon accesi (`$S/pag/addon.php`), seminati: 30 articoli
(`prove/paginazione-cli.php semina 30`), la pagina "Blog di prova" fatta con l'editor e scelta come pagina del blog, una sede con
la sua pagina, una pagina per zona pubblicata, una pagina classica, bozze (articolo e pagina), un articolo nel cestino, uno
programmato nel futuro, uno "solo per i membri", una categoria vuota, un prodotto della Vetrina, un test, un corso, un evento.
Radice: `http://127.0.0.1:9801` (sito `pagO`, 2.39.1 con i file corretti copiati sopra). Cartella: `http://127.0.0.1:9805/test`
(sito `pagKC`, zip 2.39.2). HTML letto con `$S/pag/controllo.py` (richieste senza seguire i rimandi: stato, Location, title,
description, robots, X-Robots-Tag, canonical, og:url, og:title, hreflang, JSON-LD con @id e briciole) e la sitemap con ogni
indirizzo riletto. Gravità: alta (Google indicizza male o vede doppioni), media, bassa, nessuna (va bene così).

## Corretto in questa versione

| # | Cosa | Come l'ho visto | File:riga | Gravità |
|---|---|---|---|---|
| 1 | Pagine 2, 3... del blog con canonical alla prima, index, titolo e descrizione uguali alla prima; JSON-LD della pagina con l'indirizzo della prima | `/blog-di-prova/page/2/` sulla 2.39.1: canonical `/blog-di-prova/`, title "Blog di prova - Studio Prova" uguale alla pagina 1 | `includes/sito-seo.php:67`, `:95` (`sito_meta_paginata`), `sito/index.php:371` | alta |
| 2 | Categorie e tag: stesso titolo su tutte le pagine (canonical già giusto per le categorie) | `/category/pagine-prova/page/2/` title "Pagine di prova - Studio Prova" | `includes/sito-seo.php:78` | media |
| 3 | Pagina oltre l'ultima con 301 alla prima (soft 404); `page/0/` idem | `/blog-di-prova/page/9/` e `/category/pagine-prova/page/7/`: 301 alla prima | `sito/index.php:387` | media |
| 4 | Barre doppie: `//servizi/` letto come dominio da `parse_url()`, 200 con la home (canonical home); `/servizi//` 404 | curl sui due indirizzi | `sito/index.php:128` | media |
| 5 | Sitemap ignorava il robots per tipo di SEO e AEO: con "Articoli: noindex" 31 indirizzi noindex nella sitemap | `controllo.py sitemap` con `robots.articoli = noindex`: 31 voci noindex | `includes/seo.php:898` | media |
| 6 | Titolo della home "Studio Prova - Studio Prova" (nessun titolo scritto per la home, formato di partenza) | home del kit | `includes/seo.php:790`, `includes/sito-seo.php:47`, `includes/seo-tabella.php:36` | bassa |
| 7 | og:url sempre l'indirizzo della pagina anche con un canonical scelto | lettura del codice (nel nucleo oggi il canonical dei contenuti è sempre vuoto: nessun effetto visibile) | `includes/sito-seo.php:59` | bassa |
| 8 | Tag indicizzato da SEO e AEO senza canonical | `/tag/x/` con `robots.tag = index` | `includes/sito-seo.php:78` | bassa |

HTML che cambia sulle pagine già fatte: pagine 2+ degli elenchi; titolo (e og:title) della home solo nel caso 6; tag indicizzati
(canonical in più). La pagina 1 degli elenchi e le altre pagine restano uguali (prova di aggiornamento con le foto).

## Controllato e a posto (nessuna correzione)

| Cosa | Indirizzo provato | Esito |
|---|---|---|
| Home | `/` | 200, index, canonical e og:url `/`, WebPage `/#pagina` |
| Pagina dell'editor | `/servizi/` | 200, index, canonical e og:url sé stessa, WebPage con il suo @id |
| Pagina classica | `/pagina-classica/` | idem, descrizione dalla scheda |
| Articolo | `/articolo-paginato-01/` | 200, index, canonical e og:url sé stesso, WebPage, BlogPosting, briciole Home > Blog > categoria > articolo |
| Blog pagina 1 | `/blog-di-prova/` | 200, index, canonical sé stesso, WebPage; niente Open Graph (vedi proposte) |
| Categoria | `/category/pagine-prova/` | 200, index, canonical sé stessa, niente descrizione (vedi proposte) |
| Tag | `/tag/pagine-prova-tag/` | 200, noindex, senza canonical (regola di sempre per i tag noindex) |
| Archivi per data | `/2026/`, `/2026/11/`, `/2026/11/19/` | 200, noindex, canonical sé stessi; `/2019/` senza articoli 404 |
| Ricerca | `/?s=prova`, `/?s=zzzz`, `/?s=zzzz&paged=2` | 200, noindex, senza canonical (la ricerca non pagina) |
| Glossario | `/glossario/` | 404 senza termini (corretto dalla 2.35.0); le pagine 2-15 esistono solo con `glossario_pagine`, sempre noindex |
| Pagina per zona | `/idraulico-a-imola/` | 200, index, canonical, WebPage e Service con il suo indirizzo |
| Pagina della sede | `/sede-di-bologna/` | 200, index, canonical, WebPage |
| Feed | `/feed/`, `/category/pagine-prova/feed/` | 200 RSS, fuori dalla sitemap, chiusi in robots.txt |
| 404 | `/non-esiste/`, `/tag/inesistente/`, `/category/inesistente/` | 404 vera, noindex, pagina 404 del sito |
| Senza barra finale | `/servizi`, `/articolo-paginato-01`, `/category/pagine-prova` | 301 con la barra |
| Parametri | `?utm_source=`, `?pagina=2`, `?preview=1` senza accesso, `?fbclid=` | 200 con il canonical senza parametri (va bene: Google unisce) |
| Indirizzi vecchi | `/articoli/<slug>/`, `/?p=ID` | 301 all'articolo |
| Bozze, cestino, programmati | `/articolo-in-bozza/`, `/pagina-in-bozza/`, `?preview=1` senza accesso, `/articolo-nel-cestino/`, `/articolo-futuro/` | 404 |
| Solo per i membri | `/articolo-riservato/` | 200 con `noindex` e `X-Robots-Tag: noindex`, fuori da sitemap, blog, ricerca |
| Ringraziamento dei form, `/modulo/<slug>/` | `/grazie/?f=1`, `/modulo/contatti/` | noindex (la pagina ospitata noindex, nofollow) |
| Newsletter | `/newsletter-grazie/`, `/newsletter-conferma/`, `/newsletter-esci/`, `/newsletter-preferenze/` noindex; `/newsletter-web/` senza firma 404 |
| Addon | `/negozio/`, `/negozio/vaso-di-prova/` (Product), `/negozio/condizioni/`, `/evento/evento-di-prova/` (Event), `/fai-il-test/test-di-prova/` index con canonical; `/negozio/carrello/`, `/corsi/`, `/corsi/<corso>/`, `/accedi/`, `/iscriviti/`, `/password-dimenticata/`, `/certificato/` noindex; `/area-riservata/` 303 all'accesso; `/recensione/`, `/prenotazione/` senza codice 404 |
| Sitemap | `/sitemap.xml` (43 indirizzi) | ognuno 200, index, canonical uguale al loc, nessun `page/N/` |
| llms.txt | `/llms.txt` | solo pagine e articoli pubblicati e indicizzabili, nessun `page/N/` |
| hreflang | tutte | nessuno (sito in una lingua: giusto) |
| Doppioni del CMS | Tabella SEO e `site_audit` | lavorano per contenuto: nessuna pagina d'elenco, nessun doppione inventato; un doppione vero (due descrizioni uguali) si vede |
| Open redirect | `//evil.example/` | 301 a `/evil.example/` sullo stesso sito, poi 404 |

## Proposte (non toccate)

| # | Cosa | Come l'ho visto | Dove | Gravità |
|---|---|---|---|---|
| P1 | La pagina scelta come pagina del blog, se è fatta con l'editor, non mostra il suo disegno: esce la vista standard del blog ("Gli articoli", griglia, paginazione). Chi costruisce il blog con l'editor non lo vede sul sito | `/blog-di-prova/` costruita con titolo e Ultimi articoli: sul sito "Gli articoli" | `sito/index.php` caso `blog`, `sito/viste.php` `vista_blog()` | media (non SEO, ma è la causa probabile della confusione "widget elenco articoli") |
| P2 | La descrizione della categoria (scritta in Categorie) non diventa la meta description della categoria | `/category/pagine-prova/`: niente description, la categoria ne ha una | `includes/sito-seo.php:78` | media |
| P3 | Blog, categorie e tag senza Open Graph (og:title, og:url, og:description): un link condiviso esce senza anteprima curata | `/blog-di-prova/`, `/category/pagine-prova/` | `includes/sito-seo.php:67`, `:78` | bassa |
| P4 | Eventi, test e condizioni della Vetrina sono indicizzabili ma fuori dalla sitemap (i prodotti e il negozio ci sono) | `/evento/evento-di-prova/`, `/fai-il-test/test-di-prova/`, `/negozio/condizioni/` index, assenti dalla sitemap | filtro `sitemap_voci` degli addon Eventi e Test | media (eventi) |
| P5 | Categoria vuota: 200 noindex con il canonical, non 404. Scelta voluta del codice ("una categoria vuota risponde lo stesso": i menu possono puntarci prima del primo articolo); con noindex Google non la indicizza | `/category/vuota/` | `includes/sito-mappa.php` | bassa |
| P6 | Con un link di conferma della newsletter non valido il titolo della scheda dice "Iscrizione confermata" (la pagina è noindex) | `/newsletter-conferma/?t=x` | `sito/index.php` passo newsletter | bassa |
| P7 | llms.txt e llms-full.txt contano solo il robots della scheda, non il robots per tipo di SEO e AEO (come la sitemap prima della correzione 5) | lettura del codice | `sito/extra.php` `extra_llms()`, `includes/seo.php` `seo_llms_full()` | bassa |
| P8 | Il glossario con `glossario_pagine` ripete lo stesso elenco su `/glossario/page/2/` ... `/page/15/` (noindex, senza canonical): pagine inutili da togliere quando nessun sito le usa più | lettura del codice | `includes/sito-mappa.php:105` | bassa |

Maiuscole (`/SERVIZI/`) e `/index.php`, `/index.php/servizi/` rispondono 404: va bene (nessun doppione), nessuna proposta.

## In cartella (`http://127.0.0.1:9805/test`, zip 2.39.2, kit, addon accesi, stessi dati)

Home, pagina dell'editor, articolo, blog 1 e 2, categoria e tag pagina 2 e 3, archivio, ricerca, Vetrina (negozio e prodotto), test,
evento, corsi, 404, barre doppie, senza barra, parametri, `/articoli/<slug>/`: canonical, og:url, JSON-LD e Location tutti con
`/test` una volta sola; `page/1/` in 301 a `/test/blog-di-prova/`, `page/9/` 404; `//servizi/` e `/servizi//` in 301 a
`/test/servizi/`. Sitemap: 40 indirizzi, tutti 200, index, canonical uguale, nessuno con `/test/test`; robots.txt con una riga
`Sitemap: .../test/sitemap.xml`. Nessuna differenza dalla radice.
