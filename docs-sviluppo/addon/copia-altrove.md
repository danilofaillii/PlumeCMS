# Addon Copia del sito altrove (`copia-altrove`)

Versione 1.0.0, con plumeCMS 2.0.0. Cartella `cms/addon/copia-altrove/`. Si accende da Impostazioni > Addon.

Ogni notte (passo del cron giornaliero, chiave `cron` del manifesto) la copia del sito di `includes/copia-sito.php`
(`copia_crea()`: database, uploads, temi, moduli, firmata) parte verso una destinazione: **FTP**, **FTPS**, **SFTP** (solo
se il server ha l'estensione `ssh2`) o **WebDAV** (Nextcloud, ownCloud e gli spazi cloud che parlano WebDAV). Sulla
destinazione restano le ultime N copie.

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: voce "Copia altrove" nel gruppo Impostazioni (dopo Copia del sito), pagina del pannello, passo del cron |
| `includes/copia-altrove.php` | il motore: impostazioni, cifratura, controllo SSRF, le tre classi di destinazione, prova, invio, registro, notte |
| `includes/admin-destinazione.php` | pannello: destinazione, credenziali, Prova la connessione, Manda una copia adesso, registro |
| `assets/copia-altrove.js` | nella pagina si vedono solo i campi del tipo scelto (senza JavaScript si vedono tutti) |

## Dati

- Impostazione `copia_altrove_cfg` (JSON): `tipo`, `host`, `porta`, `utente`, `password` (**cifrata**, `v1:` + base64 di nonce e
  secretbox), `cartella`, `url` (WebDAV), `tenere` (1-30, di partenza 7), `attivo` (ogni notte), `tieni_locale`, `sftp_impronta`.
- Tabella `{p}copia_altrove_registro`: `id`, `quando`, `origine` (`notte`, `manuale`, `prova`), `esito` (`ok`, `errore`),
  `tipo`, `destinazione` (descrizione senza password), `file`, `peso`, `secondi`, `messaggio`. Tiene gli ultimi 500.
- La copia si crea con l'etichetta `altrove` (`copia-AAAAMMGG-HHMMSS-altrove.zip`) in `aggiornamenti/copie/`; dopo un invio
  riuscito si toglie dal server (salvo `tieni_locale`); se l'invio non riesce resta lì, in Impostazioni > Copia del sito.

## Funzioni (`includes/copia-altrove.php`)

| Funzione | Riga | Cosa |
|---|---|---|
| `copia_altrove_installa()`, `copia_altrove_schema()` | 33, 51 | il registro |
| `copia_altrove_cfg()`, `copia_altrove_cfg_scrivi()`, `copia_altrove_salva($post)` | 66, 85, 114 | impostazioni; la password vuota lascia quella di prima, "Togli la password salvata" la cancella; controlli su host, porta, cartella (niente `..`), indirizzo WebDAV (solo http/https, niente credenziali, query o frammento) |
| `copia_altrove_chiave()`, `copia_altrove_cifra()`, `copia_altrove_decifra()` | 90, 95, 102 | chiave = HMAC-SHA256 di `CMS_SEGRETO` (config.local.php) con `moduli_segreto()` (database). `CMS_SEGRETO` non entra nella copia del sito: chi ha solo la copia non decifra la password |
| `copia_altrove_descrivi()`, `copia_altrove_disponibili()` | 157, 164 | la destinazione in parole; le estensioni PHP presenti |
| `copia_altrove_locali()`, `copia_altrove_ip_interno()`, `copia_altrove_ip()` | 172, 178, 196 | SSRF (sotto) |
| `copia_altrove_apri($cfg)` | 217 | la connessione: estensione presente, password che si decifra, https per WebDAV, IP controllato |
| `CopiaAltroveFtp` | 241 | FTP e FTPS (`ftp_ssl_connect`): login, `FTP_USEPASVADDRESS` spento (i dati vanno all'IP controllato, mai a quello che dice il server), passivo, cartelle create, upload in `.parte` poi rinominato, controllo del peso |
| `CopiaAltroveSftp` | 281 | SFTP con `ssh2`: impronta del server ricordata al primo collegamento e controllata dopo, `copy()` sullo stream `ssh2.sftp://`, rinomina. **Non provato** (l'estensione non c'è nell'ambiente di prova) |
| `CopiaAltroveWebdav` | 320 | curl: `PROPFIND` (controllo e elenco), `MKCOL` (cartelle), `PUT`, `DELETE`; `CURLOPT_RESOLVE` sull'IP controllato, niente rimandi, solo http/https, Basic subito (su https), certificati verificati |
| `copia_altrove_registra()`, `copia_altrove_registro()` | 384, 392 | il registro |
| `copia_altrove_prova()` | 398 | collega, apre la cartella, scrive e cancella `plumecms-prova-<hex>.txt` |
| `copia_altrove_invia($origine)` | 421 | un invio alla volta (`flock`); prima la connessione, poi `copia_crea('altrove')`, upload, pulizia: tiene le ultime `tenere` copie che corrispondono a `copia-\d{8}-\d{6}-altrove\.zip`, **mai altri file** |
| `copia_altrove_notte()` | 457 | il passo del cron: se `attivo`; se non riesce, email all'indirizzo degli avvisi (`posta_notifica_email()`) e l'errore nel log del cron |

## SSRF

`copia_altrove_ip()` risolve l'host (o prende l'IP scritto) e rifiuta ogni indirizzo interno: privati, riservati,
loopback, link-local (169.254.0.0/16, i metadati dei cloud), 100.64.0.0/10, 0.0.0.0/8, multicast, IPv6 `::1`, `fc00::/7`,
`fe80::/10`, `::ffff:` con un IPv4 interno. Ci si collega a quell'IP (FTP con l'IP, curl con `CURLOPT_RESOLVE`): niente
seconda risoluzione DNS. L'unica eccezione, per le prove in locale, sta nel `config.local.php`:

```php
define('CMS_COPIA_ALTROVE_LOCALE', '127.0.0.1:8205,127.0.0.1:8206,127.0.0.1:8207');
```

Solo quelle coppie host:porta, e dal pannello non si può aprire nessuna eccezione. Con l'eccezione, WebDAV ammette anche http.

## Ganci del nucleo

Nessun file del nucleo toccato. Usa `copia_crea()`, `copia_percorso()`, `copia_dir()` (`includes/copia-sito.php`),
`moduli_segreto()`, `moduli_email_html()` (`includes/moduli.php`), `posta_invia()`, `sicurezza_log()`.

## Cosa non c'è

- **Google Drive e Dropbox: non fatti.** Richiedono OAuth (un token con scadenza e rinnovo, un'app registrata presso Google o
  Dropbox) e le loro API; in questa sessione non c'era rete verso quei servizi, quindi non si potevano provare davvero.
  Dropbox e Google Drive non parlano WebDAV. Chi usa Nextcloud, ownCloud, Koofr, pCloud (WebDAV) o un hosting con FTP è coperto.
- SFTP scritto ma non provato (manca `ssh2` qui); il pannello dice chiaramente quando l'estensione manca.
- FTPS: l'estensione ftp di PHP non verifica il certificato del server (cifra, ma non controlla con chi parla). Per una
  destinazione con certificato verificato meglio WebDAV su https.
- Le copie viaggiano come sono: la copia del sito contiene il database, quindi dati personali. Va su uno spazio tuo e protetto.

## Prove

- `prove/prova-addon-copia-altrove.js` con `prove/server-copia-finti.py` (FTP 8205, FTPS 8206 con certificato a sé, WebDAV 8207):
  39 su 39 in radice e in `/test`.
- `prove/attacchi-addon-a.js`, gruppo 10: SSRF verso undici indirizzi interni, schemi `file:`, `gopher:`, `dict:`,
  credenziali nell'indirizzo; password mai nella pagina, nel registro o in chiaro nel database.
