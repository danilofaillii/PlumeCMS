# Il CMS: com'è fatto

Scritto il 26 ottobre 2026 (agente *pacchetto*, brief 4 rivisto). Danilo: "Voglio che mettendolo su una root di un sito
io possa installarlo come fosse un CMS con editor pazzesco."

## In una frase

`php-site/` è il CMS. Il **nucleo** (`includes/`, `admin/`, `sito/`, `api/`, `share/`, `cron-*.php`, `install/`) è generico:
non sa niente di danilofailli.it. Tutto ciò che è di quel sito sta nel **modulo** `moduli/danilofailli/`. Il nome del
prodotto è **plumeCMS** (scelto da Danilo il 26 ottobre 2026, dopo "CarbonaraCMS"): `CMS_NOME` in `includes/cms.php`, compare solo dove serve un nome.

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
`CMS_FUSO`; a scelta `CMS_BASE` (se il rilevamento della sottocartella sbaglia), `CMS_UPLOADS` e `CMS_UPLOADS_URL` (immagini
altrove), `CMS_RADICE_SITO` (la radice del dominio per i pacchetti, quando il CMS sta in una sottocartella). Su danilofailli.it
il file resta quello di oggi: prefisso `newdf_`, immagini in `wp-content/uploads` dichiarate dal modulo.

## Radice o sottocartella

`cms_base()` confronta `DOCUMENT_ROOT` con `cms_radice()`: `''` nella radice, `/new` su danilofailli.it. Il sito la scrive
in `<meta name="cms-base">` per i JS. `index.php` del CMS serve il sito se sta nella radice, altrimenti rimanda a `/` (il sito
vive nella radice con `install/esempi/index-radice.php`). Nessun percorso assoluto nel codice: tutto parte da `cms_radice()`.

## Prefisso delle tabelle

`CMS_PREFISSO` (default `newdf_` per compatibilità con il sito di oggi; l'installatore scrive `cms_`). `db.php` lo copia in
`DB_PREFIX` e `tbl('nome')` lo aggiunge. Nel nucleo la parola `newdf_` compare solo in `cms.php` e `db.php`: lo controlla
`tools/cms-pacchetto.py`, che rifiuta il pacchetto se la trova altrove nel codice.

## Impostazioni con fallback

`cms_impostazione('chiave', $default)`: prima la tabella delle impostazioni, poi il valore dichiarato da un modulo
(`impostazioni` nel manifesto), poi `$default`. Così il sito di Danilo resta identico senza righe nuove nel database, e
un'installazione nuova prende i valori scritti dall'installatore. L'elenco delle chiavi: `docs/cms-moduli.md`.

## Cosa resta da fare (26 ottobre 2026)

- Alcuni testi predefiniti dei widget dell'editor (per esempio il titolo di esempio dell'Apertura, le voci di esempio delle
  Schede e dei Prezzi) restano scritti nel nucleo: cambiano solo l'esempio che si vede aggiungendo un widget nuovo.
- `includes/functions.php`, `header.php`, `footer.php`, `corpo-articolo.php` sono del publisher di prima (le CTA degli
  articoli classici): hanno ancora i testi del sito di partenza e vanno spostati nel modulo o ripuliti.
- I 61 pacchetti del pannello Aggiornamenti usano le radici `new/`, `uploads/`, `root/`: nel CMS valgono ancora
  (`new/` = cartella del CMS, `uploads/` = `cms_uploads_dir()`, `root/` = radice del dominio).
