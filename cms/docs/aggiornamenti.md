# Pannello Aggiornamenti

Dall'11 settembre 2026 ogni consegna al sito è un pacchetto zip. Danilo lo
carica dalla voce **Gestione Contenuti, Aggiornamenti** di WordPress, il
pannello fa uno snapshot, applica tutto e, se qualcosa va storto, rimette il
sito com'era. Niente più FTP, Importa SQL, risincronizzazione con il token,
caricamento di immagini in Media.

## Come funziona, per Danilo

1. **Carica.** Aggiornamenti, "Carica un pacchetto", scegli lo zip, Carica.
   Compare nell'elenco con lo stato "in attesa".
2. **Aspetta o applica.** Se il pacchetto ha una data, il cron delle 06:00
   lo applica quel giorno da solo. Se vuoi prima, "Applica ora". Prima di
   ogni applicazione il pannello crea uno snapshot: tutto il database più
   una copia dei file che sta per sovrascrivere.
3. **Guarda la barra.** Appena premi, compare una finestra scura con la
   barra che avanza e, sotto, la riga che dice cosa sta facendo il server in
   quel momento: lo snapshot, i file copiati uno a uno, il SQL istruzione
   per istruzione, i redirect, gli articoli sincronizzati. Il tempo passato
   si vede in fondo. La stessa finestra compare su Ripristina, Carica e
   Snapshot. La pagina non va chiusa finché la barra non arriva in fondo.
4. **Leggi il log.** Sotto il nome del pacchetto, "Log": una riga per ogni
   cosa fatta. Se un passo fallisce, il log lo dice e il sito è già stato
   riportato indietro.
5. **Se serve tornare indietro a mano.** Sezione Snapshot, "Ripristina"
   sull'ultimo snapshot. Rimette database e file. Tutto quello che è cambiato
   dopo quello snapshot, compresi articoli pubblicati nel frattempo, torna
   indietro: è l'unica cosa da tenere a mente.

Si tengono gli ultimi cinque snapshot. Le immagini dei pillar vivono in
`wp-content/uploads/contenuti/<tema>/`, cartella fissa, indirizzo
prevedibile.

## Come funziona, per chi prepara i pacchetti

Un pacchetto è una cartella con `manifest.json` e i file citati, zippata
con `tools/pacchetto.py <cartella> <destinazione.zip>`. Il programma
controlla il manifest, i percorsi, i commenti `--` e i caratteri a 4 byte
nei file SQL.

```json
{
  "nome": "Pillar 1, burnout lavorativo",
  "descrizione": "una riga per Danilo",
  "creato": "2026-09-11",
  "applica_dal": "2026-10-06",
  "sql": ["sql/01-pillar.sql", "sql/02-bozze.sql"],
  "file": [{"da": "file/x.png", "a": "uploads/contenuti/burnout/x.png"}],
  "redirect": [{"da": "/vecchio-slug/", "a": "/nuovo-slug/"}],
  "sincronizza": [{"slug": "nuovo-slug", "aggiorna_data": true}],
  "note": "cosa controllare dopo"
}
```

Ordine di applicazione: snapshot, file, SQL nell'ordine dato, redirect,
sincronizzazione WordPress. Ogni errore ferma tutto e ripristina.

- **file**: `a` ammette tre radici. `uploads/` è `wp-content/uploads/`,
  `new/` è il publisher, `root/` è la radice del sito. Vietati `..`, la
  cartella `new/aggiornamenti/` e `new/includes/config.local.php`.
- **sql**: si eseguono con la connessione del publisher. Lo spezzatore
  tiene conto delle stringhe e toglie le righe `--`. Restano le regole:
  upsert, niente caratteri a 4 byte, `UPDATE` solo su righe che esistono.
- **redirect**: scrive nella tabella di Rank Math, `df_rank_math_redirections`,
  come 301 attivo. Se lo stesso indirizzo di partenza esiste già, aggiorna la
  destinazione. Verificare a mano il primo redirect di ogni pacchetto:
  Rank Math tiene una cache che si popola alla prima visita.
- **sincronizza**: per ogni slug, se l'articolo ha già un post WordPress lo
  aggiorna (titolo, testo, meta, categoria, tag; con `aggiorna_data` anche
  la data del post); se non lo ha ed è pubblicabile lo crea con immagine in
  evidenza; se è futuro non fa nulla e lo scrive nel log.
- **applica_dal**: se manca, il pacchetto si applica al primo clic. Se c'è,
  il cron delle 06:00 lo applica quel giorno; il pulsante "Applica ora"
  resta disponibile per anticipare.

## Dove sta il codice

| File | Cosa fa |
|---|---|
| `php-site/includes/aggiornamenti.php` | il motore: manifest, snapshot, applica, ripristina, redirect, avanzamento |
| `php-site/includes/wp-sync.php` | tutto il dialogo con WordPress, usato anche dal cron |
| `php-site/admin/aggiornamenti.php` | la pagina del pannello |
| `php-site/cron-daily.php` | alle 06:00 applica i pacchetti in scadenza, poi pubblica come sempre |
| `php-site/wordpress/danilo-admin-panel.php` | il plugin che mostra il pannello dentro WordPress, voce Aggiornamenti |
| `tools/pacchetto.py` | costruisce e controlla lo zip |
| `releases/pacchetti/` | i pacchetti consegnati |
| `/new/aggiornamenti/` sul server | pacchetti caricati, stati, snapshot; protetta da `.htaccess` |

## Come funziona la barra

Mentre applica, il motore scrive un file `aggiornamenti/progresso-<id>.json`
a ogni passo: quanti passi su quanti, e una riga in italiano che dice cosa
sta succedendo. La pagina del pannello lo chiede una volta al secondo su
`aggiornamenti.php?progresso=<id>` e aggiorna barra e riga.

Due dettagli che sembrano inutili e non lo sono. Il primo: prima di
applicare, la pagina chiama `session_write_close()`, altrimenti la
richiesta dell'avanzamento resterebbe in coda dietro a quella che sta
lavorando e la barra non si muoverebbe mai. Dopo, riapre la sessione per
scrivere il messaggio finale. Il secondo: se le richieste dell'avanzamento
non arrivano, per esempio perche il browser le mette in pausa durante
l'invio del modulo, la barra resta quella che scorre avanti e indietro e il
contatore dei secondi continua. Si vede comunque che il lavoro e in corso.

I file di avanzamento piu vecchi di un giorno vengono cancellati da soli.

## Avvisi di PHP durante il lavoro

Il 21 settembre, applicando un pacchetto con otto articoli da
sincronizzare, la pagina si e riempita di righe `Deprecated: Function
curl_close() is deprecated since 8.5`. Il server e passato a PHP 8.5, dove
`curl_close()` stampa un avviso a ogni chiamata: una per ogni richiesta a
WordPress, quindi decine per pacchetto.

Le chiamate sono state tolte da `includes/wp-sync.php` e da
`cron-publish-wp.php`. Dal PHP 8.0 non servono: la risorsa curl e un
oggetto e viene liberata da sola.

Quegli avvisi facevano anche un secondo danno. Arrivando al browser durante
il lavoro mandavano le intestazioni, e la `session_start()` che riapre la
sessione dopo l'applicazione falliva con un warning. Adesso il lavoro gira
dentro `ob_start()`: quello che PHP scrive finisce in fondo al log come
"Avvisi di PHP durante il lavoro, non bloccanti", e la pagina resta pulita.
La sessione si riapre solo se e davvero chiusa.

Restano con `curl_close()` alcuni script di servizio che non fanno parte
della catena giornaliera (`update-aforismi-batch*.php`,
`import-glossario-wp.php`, `upload-og-images-wp.php` e simili): se un
giorno li si rilancia, stamperanno gli stessi avvisi senza altre
conseguenze.

## Limiti conosciuti

- Lo snapshot del database gira dentro una richiesta web: con il database
  di oggi ci mette secondi. Se un giorno superasse i cento secondi di
  Cloudflare, il cron delle 06:00 non ha quel limite: basta dare una data
  al pacchetto.
- Il limite di caricamento dello zip è quello del server, mostrato nei
  Controlli in cima alla pagina. Un pillar con sei immagini pesa poco più di
  un megabyte.
- Il ripristino riporta indietro tutto il database, anche le tabelle di
  WordPress. Va usato subito dopo un aggiornamento andato male, non giorni
  dopo.


## Articoli in JSON e sito nuovo (dal 22 ottobre 2026, rivisto il 23)

Due voci nuove nel manifest. Dettagli in `sito-nuovo/ARCHITETTURA.md`.

- **articoli**: elenco di file JSON, uno per articolo, con `pubblicazione` in formato `AAAA-MM-GGTHH:MM`. Il pannello li inserisce o li aggiorna per slug (`sito_importa_articolo()` in `includes/sito.php`). Lo stato `published` con una data futura vuol dire "esce da solo a quell'ora". Le emoji diventano entità HTML.
- **contenuti**: un file JSON con articoli, pagine, glossario, categorie, tag e redirect. Serve una volta sola, per la migrazione da WordPress (`sito-nuovo/strumenti/pacchetto_migrazione.py`).

Il motore si sceglie in Impostazioni, tab **Sito pubblico**, oppure lo accende il pacchetto con l'azione `attiva-sito-nuovo`:

| | motore `wordpress` (oggi) | motore `php` (dopo il passaggio) |
|---|---|---|
| redirect | tabella `newdf_redirect` e Rank Math | solo `newdf_redirect` |
| sincronizza | spinge su WordPress | saltato, lo scrive nel log |
| cron delle 06:00 | `cron-publish-wp.php` | niente: il sito legge dal database e un articolo esce da solo alla sua ora |

Il sito nuovo legge dal database a ogni visita: un pacchetto applicato o un articolo salvato si vedono subito, senza ricostruzioni.

## Test e azioni (dal 23 ottobre 2026)

- **test**: elenco di definizioni JSON (`test-definizioni/<slug>-v<n>.json`), importate con `test_importa_definizione()`. Una versione con risposte non si può riscrivere nelle domande: serve la versione dopo.
- **azioni**: azioni con nome, solo quelle in `AGG_AZIONI` (oggi `importa-vecchio-test-burnout` e `attiva-sito-nuovo`). Un pacchetto non esegue codice a piacere. `attiva-sito-nuovo` si rifiuta se nella radice c'è `wp-config.php`: un errore ferma il pacchetto e rimette a posto i file appena copiati.
- **root/.htaccess**: l'unico file nascosto che un pacchetto può scrivere. Serve al pacchetto che accende il sito nuovo.
- **new/.htaccess e uploads/.htaccess** (dal pacchetto 16, CMS 1.1.3): con `root/.htaccess` sono gli unici file nascosti che un pacchetto può scrivere (`AGG_HTACCESS_AMMESSI` in `includes/aggiornamenti.php`, `HTACCESS_AMMESSI` in `tools/pacchetto.py`). Il motore di prima li rifiuta e scarta tutto il pacchetto ("Destinazione non ammessa"): per questo il 16 è diviso in due, `2026-10-26-cms-sicurezza.zip` (codice, applicato dal motore vecchio) e `2026-10-26-cms-sicurezza-b.zip` (i due `.htaccess`, applicato dal motore nuovo). Si costruiscono con `python3 tools/cms-pacchetto.py --sito <commit di partenza> <nome>`.
- **Lo spezzatore SQL del motore** tiene conto delle stringhe fra apici anche dentro i commenti `/* */`: un apostrofo in un commento apre una stringa che non si chiude, e il file SQL fallisce (successo alla prima prova del 16). Nei commenti dei file SQL niente apostrofi e niente punti e virgola.
- Un articolo con `"tipo": "aforisma"` finisce nella tabella degli aforismi.

Ordine di applicazione aggiornato: snapshot, schema, file, SQL, contenuti, test, azioni, articoli, redirect, sincronizzazione WordPress.

**Attenzione:** un pacchetto lo applica il motore già installato. Se un pacchetto porta un motore nuovo e insieme usa voci che solo quello nuovo conosce, quelle voci vengono saltate in silenzio. Si fanno due pacchetti: prima i file, poi le voci nuove.


## Sicurezza dei pacchetti (dalla 1.1.0 e 1.1.1)

- Firma HMAC facoltativa: `python3 tools/pacchetto.py <cartella> [nome.zip] --firma` (chiave nella variabile
  `PACCHETTO_CHIAVE`, la stessa di `CMS_FIRMA_PACCHETTI` nel `config.local.php`), oppure `--rifirma <pacchetto.zip>`
  per uno zip gia fatto. Se c'e, il pannello la verifica; se la pagina Sicurezza la esige, senza firma il pacchetto non entra.
- Un file eseguibile (`.php` e simili) nella radice del sito (`root/`) entra solo con un pacchetto firmato, oppure durante
  la finestra di un'ora che l'amministratore apre con la sua password da Impostazioni, Sicurezza. Senza firma, in `root/`
  passano i file statici e il `.htaccess`. Vale per esempio per `2026-10-23-accendi-sito-nuovo.zip` (scrive `root/index.php`).
- Nomi con `..` o percorsi assoluti, file oltre 200 MB o pacchetti oltre 600 MB estratti: rifiutati. Guida in `docs/sicurezza.md`.
