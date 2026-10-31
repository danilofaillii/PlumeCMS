# Addon Copia del sito altrove (`copia-altrove`)

Versione 1.1.0 (plumeCMS 2.1, in lavorazione; la 1.0.0 è della 2.0.0). Cartella `cms/addon/copia-altrove/`. Si accende da Impostazioni > Addon.

Ogni notte (passo del cron giornaliero, chiave `cron` del manifesto) la copia del sito di `includes/copia-sito.php`
(`copia_crea()`: database, uploads, temi, moduli, firmata) parte verso una destinazione: **Google Drive** e **Dropbox**
(dalla 1.1.0, collegati con OAuth 2), **FTP**, **FTPS**, **SFTP** (solo se il server ha l'estensione `ssh2`) o **WebDAV**
(Nextcloud, ownCloud e gli spazi cloud che parlano WebDAV). Sulla destinazione restano le ultime N copie.

## File

| File | Cosa |
|---|---|
| `modulo.php` | manifesto: voce "Copia altrove" nel gruppo Impostazioni (dopo Copia del sito), pagina del pannello, passo del cron, `oauth_ritorno` (1.1.0, riga 20) |
| `includes/copia-altrove.php` | il motore: impostazioni, cifratura, controllo SSRF, le tre classi FTP, SFTP, WebDAV, prova, invio, registro, notte; in fondo carica `cloud.php` (riga 498) |
| `includes/cloud.php` | (1.1.0) Google Drive e Dropbox: collegamento OAuth 2, rinnovo dell'accesso, le due classi con il caricamento a pezzi, Scollega |
| `includes/admin-destinazione.php` | pannello: destinazione, credenziali, i passi per creare l'app di Google o di Dropbox, il redirect URI da copiare, "Salva e collega", "Scollega", "Prova la connessione", "Manda una copia adesso", registro |
| `assets/copia-altrove.js` | nella pagina si vedono solo i campi del tipo scelto (senza JavaScript si vedono tutti) |

## Dati

- Impostazione `copia_altrove_cfg` (JSON): `tipo` (di partenza `gdrive`), `host`, `porta`, `utente`, `password` (**cifrata**,
  `v1:` + base64 di nonce e secretbox), `cartella`, `url` (WebDAV), `tenere` (1-30, di partenza 7), `attivo` (ogni notte),
  `tieni_locale`, `sftp_impronta`, e dalla 1.1.0 `oauth`: per `gdrive` e `dropbox` un oggetto con `client_id`, `segreto`
  (cifrato), `refresh` (cifrato), `accesso` (cifrato), `scade` (ora Unix dell'accesso), `account` (nome ed email, testo),
  `collegato` (data), `cartella_id` e `cartella_per` (Drive: l'id della cartella trovata o creata, e per quale `cartella`).
- Tabella `{p}copia_altrove_registro`: `id`, `quando`, `origine` (`notte`, `manuale`, `prova`), `esito` (`ok`, `errore`),
  `tipo`, `destinazione` (descrizione senza password), `file`, `peso`, `secondi`, `messaggio`. Tiene gli ultimi 500.
- La copia si crea con l'etichetta `altrove` (`copia-AAAAMMGG-HHMMSS-altrove.zip`) in `aggiornamenti/copie/`; dopo un invio
  riuscito si toglie dal server (salvo `tieni_locale`); se l'invio non riesce resta lì, in Impostazioni > Copia del sito.
- Nella sessione del pannello, solo mentre si collega: `copia_altrove_oauth` (servizio, nonce, verifier PKCE, scadenza,
  id dell'amministratore, indirizzo della pagina del servizio). Si toglie al ritorno, riuscito o no.

## Funzioni (`includes/copia-altrove.php`)

| Funzione | Riga | Cosa |
|---|---|---|
| `copia_altrove_installa()`, `copia_altrove_schema()` | 34, 52 | il registro |
| `copia_altrove_cfg()`, `copia_altrove_cfg_scrivi()`, `copia_altrove_salva($post)` | 67, 94, 123 | impostazioni; la password vuota lascia quella di prima, "Togli la password salvata" la cancella; controlli su host, porta, cartella (niente `..`), indirizzo WebDAV (solo http/https, niente credenziali, query o frammento); per Drive e Dropbox `copia_altrove_cloud_salva()` |
| `copia_altrove_configurata($c)` | 89 | (1.1.0) pronta per un invio: il server, l'indirizzo WebDAV o il servizio collegato |
| `copia_altrove_chiave()`, `copia_altrove_cifra()`, `copia_altrove_decifra()` | 99, 104, 111 | chiave = HMAC-SHA256 di `CMS_SEGRETO` (config.local.php) con `moduli_segreto()` (database). `CMS_SEGRETO` non entra nella copia del sito: chi ha solo la copia non decifra password, segreti e token |
| `copia_altrove_descrivi()`, `copia_altrove_disponibili()` | 171, 183 | la destinazione in parole (per Drive e Dropbox con l'account); le estensioni PHP presenti |
| `copia_altrove_locali()`, `copia_altrove_ip_interno()`, `copia_altrove_ip()` | 192, 198, 216 | SSRF (sotto) |
| `copia_altrove_apri($cfg)` | 237 | la connessione: per Drive e Dropbox la classe di `cloud.php` (se collegato); per gli altri estensione presente, password che si decifra, https per WebDAV, IP controllato |
| `CopiaAltroveFtp` | 267 | FTP e FTPS (`ftp_ssl_connect`): login, `FTP_USEPASVADDRESS` spento (i dati vanno all'IP controllato, mai a quello che dice il server), passivo, cartelle create, upload in `.parte` poi rinominato, controllo del peso |
| `CopiaAltroveSftp` | 307 | SFTP con `ssh2`: impronta del server ricordata al primo collegamento e controllata dopo, `copy()` sullo stream `ssh2.sftp://`, rinomina. **Non provato** (l'estensione non c'è nell'ambiente di prova) |
| `CopiaAltroveWebdav` | 346 | curl: `PROPFIND` (controllo e elenco), `MKCOL` (cartelle), `PUT`, `DELETE`; `CURLOPT_RESOLVE` sull'IP controllato, niente rimandi, solo http/https, Basic subito (su https), certificati verificati |
| `copia_altrove_registra()`, `copia_altrove_registro()` | 410, 418 | il registro |
| `copia_altrove_prova()` | 424 | collega, apre la cartella, scrive e cancella `plumecms-prova-<hex>.txt` |
| `copia_altrove_invia($origine)` | 447 | un invio alla volta (`flock`); prima la connessione, poi `copia_crea('altrove')`, upload, pulizia: tiene le ultime `tenere` copie che corrispondono a `copia-\d{8}-\d{6}-altrove\.zip`, **mai altri file** |
| `copia_altrove_notte()` | 483 | il passo del cron: se `attivo`; se non riesce (anche un servizio scollegato o con il permesso tolto), email all'indirizzo degli avvisi (`posta_notifica_email()`) e l'errore nel log del cron |

## Google Drive e Dropbox (1.1.0, `includes/cloud.php`)

**Il collegamento, per chi non è tecnico.** Nel pannello, scelto "Google Drive" o "Dropbox":
1. i passi per creare la propria app (Google Cloud Console: progetto, Google Drive API abilitata, Google Auth Platform con
   pubblico Esterno e app **pubblicata**, client "Applicazione web"; Dropbox App Console: Scoped access, **App folder**,
   i permessi `files.metadata.read`, `files.content.write`, `files.content.read`), aperti finché non è collegato;
2. l'indirizzo da incollare come redirect URI, in un campo che si copia con un clic: `copia_altrove_redirect_uri()` (riga 47),
   cioè `cms_url_pannello() . 'oauth-ritorno.php'`, fisso e senza query (i servizi lo confrontano carattere per carattere);
3. ID e segreto dell'app (il segreto non si rivede, come la password), poi "Salva e collega con ...";
4. il POST salva e prepara il collegamento (`copia_altrove_oauth_inizia()`, riga 259), poi rimanda alla stessa pagina con
   `&autorizza=1`: la pagina dice "Ti porto alla pagina di ..." e ci va con uno script (con il nonce) dopo 0,7 secondi, con il
   pulsante se lo script non parte. Non un redirect del POST: la CSP del pannello ha `form-action 'self'` e il browser
   fermerebbe un redirect verso un altro sito dopo l'invio di un modulo;
5. sulla pagina del servizio si sceglie l'account e si preme Consenti; il servizio torna a `admin/oauth-ritorno.php`
   (nucleo, sotto) con `code` e `state`, e da lì a `copia_altrove_oauth_ritorno()` (riga 295);
6. collegato: lo stato dice "Collegato come Mario Rossi (mario@gmail.com) dal ..."; poi "Prova la connessione".

**state** (`copia_altrove_oauth_inizia()`): `copia-altrove.<servizio>.<nonce>.<scadenza>.<firma>`; nonce di 18 byte a caso,
scadenza 15 minuti (`COPIA_ALTROVE_OAUTH_MINUTI`), firma HMAC-SHA256 con `moduli_segreto()` di servizio, nonce, scadenza e
id dell'amministratore (`copia_altrove_state_firma()`, riga 251). Il ritorno lo accetta solo se: forma giusta, la sessione di
questo browser ha una richiesta aperta con lo stesso nonce, servizio e scadenza, lo stesso amministratore, firma giusta
(`hash_equals`), non scaduto. Poi la richiesta si toglie dalla sessione **prima** dello scambio del codice: una volta sola.
Uno state rubato e usato da un altro browser (anche dello stesso amministratore) non ha la richiesta nella sua sessione e si
respinge senza scambiare il codice. Ogni rifiuto va in `sicurezza_log`.

**PKCE** S256: verifier di 48 byte nella sessione, challenge nell'indirizzo. **Permessi**: Google
`https://www.googleapis.com/auth/drive.file` con `access_type=offline` e `prompt=consent` (sempre un refresh token; il
permesso vede solo i file creati dall'app ed è "non sensibile", quindi niente verifica dell'app da parte di Google; il
ritorno controlla che lo scope sia stato davvero concesso); Dropbox `token_access_type=offline`.

**Token** (`copia_altrove_token_chiama()`, riga 205; `copia_altrove_accesso()`, riga 227): refresh token e access token
cifrati come la password. L'accesso si rinnova da solo un minuto prima della scadenza, e un 401 delle API lo rinnova e
riprova una volta (`copia_altrove_cloud_api()`, riga 363). `invalid_grant` al rinnovo (permesso tolto dall'utente, app di
Google lasciata "in test" che scade dopo 7 giorni): il collegamento si dimentica e il messaggio dice di collegare di nuovo;
la notte arriva l'email. Il token va solo nella testata `Authorization`, mai in un indirizzo, in una pagina o in un messaggio
(`copia_altrove_cloud_motivo()`, riga 191, toglie dai messaggi dei servizi ogni sequenza che somiglia a un token).

**HTTP** (`copia_altrove_cloud_http()`, riga 140): solo gli host di `COPIA_ALTROVE_CLOUD_HOST` (riga 35) su https, certificati
verificati, niente rimandi seguiti, `Expect:` vuoto, testate della risposta lette. Niente controllo SSRF da fare: gli host
sono fissi nel codice e dal pannello non si cambiano.

**Google Drive** (`CopiaAltroveGdrive`, riga 395):
- cartella: quella ricordata (`cartella_id`, se esiste e non è nel cestino), altrimenti cercata o creata pezzo per pezzo
  (`copie-del-sito/notti` = due cartelle) con `files.list` e `q` (apici e barre rovesciate con la barra davanti);
- `carica()` (riga 442): upload **resumable**. POST `upload/drive/v3/files?uploadType=resumable` con il JSON (nome, cartella),
  `X-Upload-Content-Length`; la `Location` della sessione deve stare sullo stesso servizio, se no ci si ferma (il token non
  va mai a un altro indirizzo); poi PUT a pezzi di `copia_altrove_pezzo()` (8 MB, multipli di 256 KB) con `Content-Range`;
  308 con `Range: bytes=0-N` = si riparte da N+1; un errore di rete, 5xx o 429 = si chiede a Google a che punto è
  (`Content-Range: bytes */<peso>`) e si riparte da lì, fino a 4 volte; alla fine si controlla il peso arrivato;
- `elenco()` a pagine (`nextPageToken`), `elimina()` per id (DELETE: con `drive.file` si cancellano solo i file dell'app).

**Dropbox** (`CopiaAltroveDropbox`, riga 522):
- la cartella sta dentro la cartella dell'app ("Applicazioni/<nome dell'app>/copie-del-sito"); Dropbox crea da solo le cartelle;
- `carica()` (riga 556): fino a un pezzo `files/upload`; più grande upload session (`start` con il primo pezzo, `append_v2`
  con l'offset, `finish` con il commit `overwrite`). `incorrect_offset` (per esempio un pezzo arrivato ma la risposta persa)
  = si riparte dal `correct_offset` che dice Dropbox; 429 e 5xx = fino a 4 tentativi con l'attesa di `Retry-After`
  (`riprova()`, riga 587); alla fine il peso;
- `elenco()` con `list_folder` e `list_folder/continue` (cartella che non c'è = vuota), `elimina()` con `delete_v2`;
- gli argomenti delle chiamate "content" stanno in `Dropbox-API-Arg` (JSON con i caratteri non ASCII come `\uXXXX`).

**Scollega** (`copia_altrove_scollega()`, riga 370): chiede al servizio di togliere il permesso (Google `revoke` del refresh
token, Dropbox `auth/token/revoke`), poi dimentica token e account (ID e segreto dell'app restano: per ricollegare basta
"Salva e collega") e spegne l'invio di ogni notte se era quella la destinazione. Le copie già mandate restano.

**Solo per le prove**, nel `config.local.php` (mai dal pannello):

```php
define('CMS_COPIA_ALTROVE_CLOUD_PROVA', 'gdrive=http://127.0.0.1:8321,dropbox=http://127.0.0.1:8322');
define('CMS_COPIA_ALTROVE_PEZZO', 262144);   // pezzi da 256 KB, per provare il caricamento a pezzi con una copia piccola
```

`https://<host>/<percorso>` diventa `<base>/<host>/<percorso>` (`copia_altrove_cloud_url()`, riga 63) e http è ammesso solo
verso quelle basi. I server finti sono in `prove/server-cloud-finti.py`.

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

- (1.1.0) `cms/admin/oauth-ritorno.php`, file nuovo del nucleo: il redirect URI fisso di ogni addon che si collega con OAuth 2.
  Solo amministratore (`require_admin()`), `Cache-Control: no-store`, `Referrer-Policy: no-referrer`. Lo `state` deve
  cominciare con il nome di un addon acceso seguito da un punto, e quell'addon deve avere nel manifesto la chiave
  `oauth_ritorno` (una funzione che riceve `$_GET` e chiude con il suo redirect); altrimenti 400. La pagina non segue nessun
  indirizzo della richiesta. Chiave nuova del manifesto: `oauth_ritorno` (`docs-sviluppo/cms-moduli.md`).
- Usa `copia_crea()`, `copia_percorso()`, `copia_dir()` (`includes/copia-sito.php`), `moduli_segreto()`, `moduli_email_html()`
  (`includes/moduli.php`), `posta_invia()`, `sicurezza_log()`, `cms_url_pannello()`.

## Cosa non c'è

- **Google Drive e Dropbox non sono provati contro i servizi veri**: dalla sessione di sviluppo non c'è rete verso Google e
  Dropbox. Sono provati contro server finti fedeli alle API documentate (`prove/server-cloud-finti.py`). Da provare su un
  server vero: la pagina di consenso vera (e l'eventuale avviso "app non verificata"), i nomi delle voci delle due console
  (cambiano spesso), un caricamento grande (centinaia di MB), la scadenza del permesso di un'app Google lasciata "in test".
- SFTP scritto ma non provato (manca `ssh2` qui); il pannello dice chiaramente quando l'estensione manca.
- FTPS: l'estensione ftp di PHP non verifica il certificato del server (cifra, ma non controlla con chi parla). Per una
  destinazione con certificato verificato meglio WebDAV su https, Google Drive o Dropbox.
- Le copie viaggiano come sono: la copia del sito contiene il database, quindi dati personali. Va su uno spazio tuo e protetto.
- Una sola destinazione alla volta (cambiando tipo, il collegamento con Google o Dropbox resta salvato e si ritrova).
- Un caricamento interrotto a metà non riprende alla notte dopo: si ricomincia con una copia nuova.

## Prove

- `prove/prova-addon-copia-altrove.js` con `prove/server-copia-finti.py` (FTP, FTPS, WebDAV; porte in `PORTE_COPIA`, di
  partenza 8205-8207) e `prove/server-cloud-finti.py` (Google `CLOUD_GOOGLE`, Dropbox `CLOUD_DROPBOX`, di partenza 8321 e
  8322, con il redirect URI del sito di prova): 91 su 91 in radice e in `/test` (31 ottobre 2026; con `SOLO=cloud` solo Drive, Dropbox e la fine).
- `prove/attacchi-addon-a.js`, gruppo 10: SSRF verso undici indirizzi interni, schemi `file:`, `gopher:`, `dict:`,
  credenziali nell'indirizzo; password mai mostrata. Gruppo 11 (1.1.0): state falso, rubato da un'altra sessione, ripetuto,
  con servizio o scadenza cambiati, state senza addon (400), ritorno senza login o con il cookie di un membro, rimandi fuori
  dal pannello, Host falso, token e segreto mai nella pagina, nei log e in chiaro nel database, Scollega senza token.
