# Il CMS: com'è fatto

Scritto il 26 ottobre 2026. L'obiettivo: caricarlo nella radice di un sito (o in una sottocartella) e installarlo
dal browser, come un CMS qualunque, con l'editor visuale al centro.

## In una frase

`cms/` è il CMS. Il **nucleo** (`includes/`, `admin/`, `sito/`, `api/`, `cron-*.php`, `install/`) è generico:
non sa niente del sito su cui gira. Ciò che è proprio di un sito sta in un **modulo** (`moduli/<nome>/`, `cms-moduli.md`).
Il nome del prodotto è **plumeCMS**: `CMS_NOME` in `includes/cms.php`, compare solo dove serve un nome.

## I pezzi

| Pezzo | Dove | Cosa fa |
|---|---|---|
| Nucleo del CMS | `includes/cms.php` | nome, versione (`VERSIONE`), prefisso (`CMS_PREFISSO`), cartella (`cms_radice()`), base URL (`cms_base()`), URL del sito (`cms_url_sito()`), impostazioni con fallback (`cms_impostazione()`), uploads, moduli, filtri ed eventi |
| Schema di base | `includes/schema-base.php` | le tabelle di partenza e `cms_installa_tutto()` |
| Installatore | `install/index.php`, `install/install.css`, `install/esempi/` | cinque passi dal browser; `config.php` ci manda finché manca `config.local.php` |
| Sito pubblico | `sito/index.php` (front controller), `viste.php`, `extra.php`, `includes/sito*.php` | legge dal database a ogni visita |
| Pannello | `admin/` | menu da un solo elenco in `admin/includes/admin-header.php`, integrato con le voci dei moduli |
| Editor | `includes/editor*.php`, `admin/editor.php` | sezioni, colonne, widget, modelli di header e footer |
| Pagina Informazioni | `admin/informazioni.php` | versione, moduli, server, tabelle, widget, novità |
| Pacchetti | `tools/cms-pacchetto.py` | `releases/cms/<nome>-<versione>.zip` (prodotto) e `-aggiornamento.zip` (per il pannello Aggiornamenti) |

## Configurazione: `includes/config.local.php`

Lo scrive l'installatore. Dentro: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`, `CMS_PREFISSO`, `CMS_SEGRETO`,
`CMS_FUSO`; `CMS_BASE` (l'installatore lo scrive sempre quando l'indirizzo del sito ha una cartella, dalla 1.1.7), `CMS_UPLOADS` e `CMS_UPLOADS_URL` (immagini
altrove), `CMS_RADICE_SITO` (la radice del dominio per i pacchetti, quando il CMS sta in una sottocartella). Un modulo
può dichiarare dove stanno le immagini (`uploads_url` fra le sue `impostazioni`).

## Radice o sottocartella

`cms_base()` confronta `DOCUMENT_ROOT` con `cms_radice()`: `''` nella radice, `/cms` se il CMS sta in `/cms/`. Il sito la scrive
in `<meta name="cms-base">` per i JS. `index.php` del CMS serve il sito quando sito e CMS stanno nella stessa cartella (`cms_sito_base() === cms_base()`: nella
radice o tutti e due in `/test`), altrimenti rimanda al sito con un 302 senza cache (il sito nella radice con
`install/esempi/index-radice.php`). I tre casi nel capitolo 5 del manuale tecnico (aggiornato al 31 ottobre 2026). Nessun percorso assoluto nel codice: tutto parte da `cms_radice()`.

## Prefisso delle tabelle

`CMS_PREFISSO` (predefinito `newdf_`, per le installazioni di prima che non lo scrivono nel config; l'installatore propone `cms_`
e lo scrive sempre). `db.php` lo copia in
`DB_PREFIX` e `tbl('nome')` lo aggiunge. Nel nucleo la parola `newdf_` compare solo in `cms.php` e `db.php`: lo controlla
`tools/cms-pacchetto.py`, che rifiuta il pacchetto se la trova altrove nel codice.

## Impostazioni con fallback

`cms_impostazione('chiave', $default)`: prima la tabella delle impostazioni, poi il valore dichiarato da un modulo
(`impostazioni` nel manifesto), poi `$default`. Così un sito con un modulo prende i valori del modulo senza righe nuove nel
database, e un'installazione nuova prende i valori scritti dall'installatore. L'elenco delle chiavi: `docs-sviluppo/cms-moduli.md`.

## Cosa resta da fare (26 ottobre 2026)

- Alcuni testi predefiniti dei widget dell'editor (per esempio il titolo di esempio dell'Apertura, le voci di esempio delle
  Schede e dei Prezzi) restano scritti nel nucleo: cambiano solo l'esempio che si vede aggiungendo un widget nuovo.
- I pacchetti del pannello Aggiornamenti usano le radici `new/`, `uploads/`, `root/`: il nome `new/` è storico e vuol dire
  la cartella del CMS (`uploads/` = `cms_uploads_dir()`, `root/` = radice del dominio).
