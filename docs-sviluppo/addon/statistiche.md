# Addon Statistiche delle visite (2.0.0): documento tecnico

Conta le visite delle pagine pubbliche sul server, senza cookie, senza servizi esterni e senza salvare l'indirizzo IP
(modello Plausible). Si accende da Impostazioni > Addon (`cms/addon/statistiche/`).

## File

| File | Cosa fa |
|---|---|
| `cms/addon/statistiche/modulo.php` | manifesto: menu Statistiche (dopo Dashboard, solo amministratore), pagine, evento `sito_pagina_servita`, filtro `dashboard_numeri`, passo del cron |
| `includes/statistiche.php` | motore: tabelle, chi si conta, impronta del giorno, scrittura, pulizia, letture per il pannello, testo per la privacy |
| `includes/admin-visite.php` | Statistiche > Visite: periodo, numeri con il confronto, grafico SVG, pagine, provenienze, campagne, dispositivi |
| `includes/admin-esporta.php` | il CSV del periodo |
| `includes/admin-impostazioni.php` | Statistiche > Impostazioni e privacy: conservazione, Do Not Track, testo per l'informativa |

## Il gancio nel nucleo

**Evento nuovo `sito_pagina_servita(int $codice, string $percorso)`** in `sito_rispondi()` (`cms/sito/index.php:34-40`):
parte per ogni risposta `text/html` del sito pubblico, dopo le intestazioni e prima del corpo. `$percorso` è quello dalla
radice del sito, già senza la cartella (`$GLOBALS['sito_percorso_404']`). Chi ascolta non deve mandare intestazioni né scrivere;
un suo errore finisce nel log e la pagina esce lo stesso. Le pagine dei moduli e degli addon servite con `sito_html()`
(per esempio quelle della Vetrina) passano di qui; robots.txt, sitemap, feed e file scaricati no (non sono HTML).

Con l'addon spento l'evento non ha ascoltatori: `cms_evento()` gira sui manifesti caricati e non fa niente.

## Tabelle (prefisso del sito davanti, utf8)

Create da `st_installa()` (`includes/statistiche.php:50`) all'accensione, e da `st_registra()` se mancano (errore 42S02).

- `stat_visite`: giorno, chiave (BINARY 16, md5 di pagina + provenienza + tre utm + dispositivo), pagina (191), fonte (100),
  utm_source, utm_medium, utm_campaign (80), dispositivo (`computer`, `telefono`, `tablet`), visite, visitatori.
  Chiave primaria (giorno, chiave): una riga aggregata, aggiornata con `INSERT ... ON DUPLICATE KEY UPDATE`.
- `stat_impronte`: giorno, impronta (BINARY 16), viste. Solo il giorno corrente: le righe dei giorni prima si cancellano
  al primo passaggio del giorno nuovo e dal cron.

Impostazioni (`settings`): `statistiche_impostazioni` (JSON: `dnt` 0/1, `giorni` di conservazione, predefinito 395) e
`statistiche_sale` (JSON: `giorno`, `sale` di 32 byte casuali). Lette insieme con una query sola (`st_letture()` `:60`).

## Chi si conta (`st_conta()`, `:160`)

1. risposta 200, metodo GET, non l'anteprima del pannello (`SITO_ANTEPRIMA`, `?preview`);
2. non un robot (`st_e_robot()` `:105`): user agent vuoto, lungo più di 600, che non comincia con `Mozilla/`, o che contiene
   una delle parole di `ST_ROBOT` (`:24`: bot, crawl, spider, headless, curl, python, whatsapp, facebookexternalhit, gptbot,
   claude, playwright...); intestazione `Accept` senza `text/html`; `Accept-Language` assente o `*`; `Sec-Purpose`/`Purpose`
   prefetch o prerender; `Sec-Fetch-Dest` diverso da `document`;
3. con «Rispetta Do Not Track» acceso: non chi manda `DNT: 1` o `Sec-GPC: 1`;
4. non chi è entrato nel pannello (`st_e_del_pannello()` `:126`): la sessione si legge **solo se il browser ha già il cookie
   della sessione**, in sola lettura (`read_and_close`, `cache_limiter` vuoto): nessun cookie nuovo, nessun Cache-Control,
   nessun file di sessione creato per i visitatori.

Poi si preparano i dati (pagina, provenienza con `st_fonte()` `:139`, utm con `st_utm()` `:149`, dispositivo con
`st_dispositivo()` `:119`) e la scrittura si registra con `register_shutdown_function` (prima `fastcgi_finish_request()` dove c'è:
con PHP-FPM la pagina è già partita quando si scrive).

## Il visitatore unico del giorno, senza IP

`impronta = primi 16 byte di sha256(sale del giorno | IP | user agent | host)` (`st_registra()` `:187`). Il sale si crea
quando cambia il giorno (`st_sale()` `:84`) e sovrascrive quello di ieri, che quindi non esiste più: le impronte di ieri non
si possono ricalcolare né collegare a quelle di oggi. L'IP e lo user agent restano in una variabile della richiesta e non si
scrivono da nessuna parte (verificato da `prova-addon-statistiche.js` sul contenuto delle tabelle).

`INSERT INTO stat_impronte ... ON DUPLICATE KEY UPDATE viste = LAST_INSERT_ID(viste + 1)`: righe toccate 1 = visitatore
nuovo del giorno. Oltre `ST_VISTE_MAX` (1000) pagine in un giorno, lo stesso visitatore non si conta più (un programma).

Le visite e i visitatori vanno nella riga del giorno + pagina + provenienza + campagna + dispositivo: `visitatori` cresce solo
alla prima pagina del giorno di quel visitatore. Quindi:

- visitatori del giorno = `SUM(visitatori)` delle righe del giorno;
- visitatori di un periodo = somma dei visitatori unici di ogni giorno (chi torna domani conta di nuovo; con il sale giornaliero
  non si può fare altrimenti, ed è scritto nel pannello);
- nelle pagine, la colonna «Entrati da qui» è il numero di persone per cui quella è stata la prima pagina del giorno;
- provenienza e campagna sono quelle della prima pagina del giorno.

Scritture per visita contata: 3 query (impostazioni e sale insieme, impronta, riga aggregata), 4 al primo passaggio del giorno.

## Provenienza e campagne

- `st_fonte()`: solo il dominio del referrer, minuscolo, senza `www.`, validato come nome di dominio (max 100); il sito stesso,
  un dominio non valido, `javascript:` e simili diventano `''` («Diretto o sconosciuto»).
- `st_utm()`: `utm_source`, `utm_medium`, `utm_campaign` dalla query, solo lettere, cifre, spazio e `._+/:@-`, max 80 caratteri.
  Niente HTML salvato; nel pannello tutto passa comunque da `h()`.

## Pannello

- `admin-visite.php`: periodo `7`, `30`, `90` o `personalizzato` con `dal`/`al` (date controllate, al massimo 730 giorni,
  mai oltre oggi; date sbagliate: 30 giorni) con `st_periodo()` (`:224`), periodo prima della stessa lunghezza (`st_periodo_prima()`).
  Grafico `st_grafico()` (`admin-visite.php:23`): SVG disegnato sul server, barre per le visite e linea per i visitatori sulla
  stessa scala (stessa unità), griglia a passi tondi, un `<title>` per giorno come suggerimento al passaggio del mouse, e la
  tabella «Vedi i numeri giorno per giorno». I giorni si contano con le date, non con 86400 secondi (cambio dell'ora).
- `admin-esporta.php`: CSV con punto e virgola, UTF-8 con BOM; un valore che comincia con `= + - @` prende un apostrofo
  (niente formule eseguite dal foglio di calcolo).
- `admin-impostazioni.php`: conservazione (3 mesi, 6 mesi, 13 mesi, 2 anni, 5 anni), Do Not Track, testo per la privacy
  (`st_testo_privacy()` `:301`, cambia con le impostazioni).
- Dashboard: filtro `dashboard_numeri` con «visite oggi» e «visite, 7 giorni».

## Cron

`st_pulizia()` (`:209`), passo `statistiche` del cron giornaliero: cancella le righe più vecchie del periodo scelto e le
impronte dei giorni passati.

## Tempo di risposta

Misurato con PHP 8.3 `php -S` (4 worker) e MySQL 8 in Docker, sito del kit Attività, 400 richieste per modo a giri alterni
(addon acceso e spento cambiando `moduli/addon-attivi.json`), visitatori diversi: spento media 73,7 ms (mediana 69,0), acceso
media 75,7 ms (mediana 70,6): circa +2 ms a pagina (+2,7%). La prova `prova-addon-statistiche.js` ripete una misura più corta
(3 pagine x 40) e vuole meno di 15 ms in più: nei giri fatti la differenza è stata fra 2,8 e 12 ms, rumorosa.
Con PHP-FPM la scrittura avviene dopo `fastcgi_finish_request()`, quindi il visitatore non la aspetta (non misurato qui).

## Limiti noti

- Niente paese né città (nessun database esterno, per scelta).
- Il visitatore unico è per giorno; dietro lo stesso IP (un ufficio) due persone con lo stesso browser e la stessa versione
  contano come una.
- Con `CMS_PROXY_FIDATO` l'IP viene da `X-Forwarded-For` come nel resto del CMS (`sicurezza_ip()`).
- Un robot che si finge un browser in tutto (user agent, Accept, Accept-Language) si conta; il limite di 1000 pagine al giorno
  per impronta ferma solo quelli che insistono.
- Le pagine servite da una cache davanti a PHP (CDN, cache del server) non arrivano a PHP e non si contano.

## Prove

- `prove/prova-addon-statistiche.js` (32 controlli; in radice e con il CMS in `/test`): uso `node prove/prova-addon-statistiche.js
  <base> <utente> <password> <cartella del CMS installato>`; legge le tabelle con `prove/statistiche-cli.php` (dump, azzera).
- `prove/attacchi-addon-c.js`, parte 12: referrer e utm con HTML o lunghissimi, 404 con HTML nel percorso, SQL nel periodo.
