# Sicurezza del CMS

Questo documento spiega cosa protegge il CMS, come si configura dalla pagina **Sicurezza** del pannello
(gruppo Impostazioni, solo amministratore) e cosa fare se ci si chiude fuori. È scritto per chi non è
sviluppatore: ogni voce dice dove cliccare e cosa deve succedere.

Motore: `includes/sicurezza.php` (sessione, accessi, limite tentativi, 2FA, token, IP, intestazioni, registro,
firma pacchetti), `includes/sicurezza-html.php` (sanificatori di HTML, CSS, SVG, URL, chiamate in uscita),
`includes/sicurezza-qr.php` (il codice QR del 2FA, disegnato in casa in SVG). Pagina: `admin/sicurezza.php`.
Endpoint dei rapporti CSP: `api/csp.php`. Accesso dell'utente: `admin/account.php`. Password dimenticata:
`admin/password-dimenticata.php` e `admin/password-reimposta.php`.

## Cosa c'è, in breve

- **Password**: argon2id dove c'è, altrimenti bcrypt costo 12. Al primo accesso un hash vecchio si rifà da solo
  (migrazione trasparente: i login di prima continuano a funzionare). Requisiti: almeno 12 caratteri, diversa dal
  nome utente e dall'email, non tra le più comuni, niente sequenze di tastiera. Dalla 1.1.3 le stesse regole valgono
  anche per la password dell'amministratore scelta nell'installatore (prima bastavano 10 caratteri qualunque): il
  controllo sta in `includes/sicurezza-password.php`, che non tocca il database e lo usano sia il pannello sia
  l'installatore.
- **Limite ai tentativi di login** (regole della 1.1.3, decise da Danilo dopo il terzo attacco). Due conteggi separati:
  - **per indirizzo IP**: dopo 5 tentativi sbagliati (valore di "Tentativi sbagliati prima del blocco" nella pagina Sicurezza)
    quell'indirizzo è bloccato per i minuti scelti, e il blocco raddoppia a ogni tentativo in più. Il blocco per IP
    ferma tutto, anche la password giusta. Un'email avvisa l'amministratore.
  - **per nome utente**: 20 tentativi sbagliati in 15 minuti, da qualunque indirizzo, bloccano il nome utente per 15
    minuti (poi raddoppia a ogni tentativo in più, al massimo un giorno). Parte un'email all'amministratore
    ("Nome utente del pannello bloccato"). Il conteggio riparte da zero dopo 15 minuti senza tentativi.
  - **il proprietario entra lo stesso**: durante il blocco del nome utente, chi scrive la password giusta da un
    indirizzo "di casa" entra. Di casa vuol dire: da quell'IP c'è già almeno un accesso riuscito con quell'utente nel
    registro, e quell'IP non è mai stato bloccato. L'accesso lascia nel registro la riga "Accesso durante un blocco
    (IP di casa)" e non azzera il blocco: chi attacca da fuori resta fermo. Da un indirizzo estraneo, durante il
    blocco, password giusta e sbagliata ricevono la stessa risposta ("Troppi tentativi"): la password giusta non si
    distingue. Così un anonimo che conosce il tuo nome utente non ti chiude fuori dal pannello.
  - Un accesso riuscito azzera il conteggio dell'IP (e quello del nome utente, se non c'è un blocco in corso).
  - Utente inesistente e password sbagliata rispondono nello stesso tempo: per l'utente inesistente il CMS verifica
    la password contro un hash finto con lo stesso algoritmo e lo stesso costo degli account veri (argon2id, 64 MB,
    4 passate), calcolato una volta e conservato nelle impostazioni (`sicurezza_hash_finto`).
- **Secondo fattore (2FA TOTP)**: fatto in casa, nessun servizio esterno. QR in SVG disegnato dal server, otto codici
  di recupero, si può rendere obbligatorio per gli amministratori. **Nasce spento**: lo accende ogni utente da
  "Il mio accesso".
- **Sessione**: id rigenerato al login (niente session fixation), cookie `HttpOnly`, `SameSite=Lax`, `Secure` su
  https; scadenza assoluta e per inattività configurabili; "esci da tutti i dispositivi"; il cambio password chiude
  tutte le altre sessioni.
- **Password dimenticata**: link firmato monouso via email, scade in 30 minuti, non rivela se l'utente esiste.
- **Registro degli accessi**: riusciti, falliti, blocchi, 2FA, cambi password, pacchetti, con IP e browser. Tiene
  sei mesi. Filtri nella scheda Registro.
- **IP consentiti** (opzionale): il pannello risponde solo da certi indirizzi o reti. **Nasce spento.**
- **Intestazioni di sicurezza**: su ogni risposta via `X-Content-Type-Options: nosniff`, `Referrer-Policy`,
  `X-Frame-Options: SAMEORIGIN`, `Permissions-Policy`, niente `X-Powered-By`; sulle pagine HTML la
  **Content-Security-Policy a nonce**, `frame-ancestors 'self'`, `Cross-Origin-Opener-Policy`, e `Strict-Transport-Security`
  su https (facoltativo). Le sorgenti dei tag di analisi (GA4, Tag Manager, Pixel, Clarity) entrano nella CSP solo
  se quei tag sono accesi nella voce SEO.
- **CSP applicata** (dalla 1.1.2 le installazioni nuove partono con "applica" su sito e pannello: verificata a zero
  violazioni su tutte le pagine). Si puo tornare a "solo rapporto" (il browser segnala, non blocca) dalla pagina Sicurezza.
  I rapporti arrivano a `api/csp.php` e si leggono nella scheda Rapporti CSP. Nel pannello vale solo il nonce: nessun
  gestore in linea viene autorizzato con un hash.
- **Ruoli** (dalla 1.1.2): il redattore apre solo le pagine dei contenuti, dei media, delle categorie e del suo accesso;
  tutto il resto e dell'amministratore, controllato su ogni pagina e ogni azione. Tabella completa in `docs/pannello.md`.
- **Export CSV**: le celle che un foglio di calcolo leggerebbe come formula prendono un apice davanti.
- **Contenuti costruiti dall'editor**: il testo ricco e i contenuti passano da un sanificatore su parser DOM (niente
  `<script>`, `on*`, `javascript:`, `data:` nei link); il widget HTML e il codice head/body sono solo
  dell'amministratore (un redattore che li tocca li vede diventare testo pulito); il CSS personalizzato resta
  confinato all'elemento (`.ed-ID`), senza `@import`, `expression`, `behavior`, `url()` esterni; gli Attributi sono
  su una lista stretta (niente `onclick`).
- **Caricamenti**: solo immagini vere (controllo del tipo reale, non del nome), ricodificate (via metadati e
  code nascosto dopo i pixel); SVG sanificato (niente script, eventi, `foreignObject`, entità XML) e servito in
  sandbox; nomi con un pezzo casuale.
- **Pacchetti di aggiornamento**: solo amministratore, con controllo del percorso (niente `../`, zip slip),
  dimensione massima, e **firma HMAC opzionale**.
- **Chiamate in uscita**: solo verso host in elenco, mai verso indirizzi privati del server, senza seguire redirect.
- **Redirect**: solo interni al sito (anche i ritorni del pannello, `torna`). 404 che non stampa mai il percorso grezzo.
- **Link della newsletter** (dalla 1.1.1): la firma copre anche l'indirizzo di destinazione (parametro `k`). Un link
  spedito prima della 1.1.1, senza `k`, conta ancora il clic ma porta solo dentro il sito.
- **Cron** (dalla 1.1.1): `cron-daily.php` e `cron-newsletter.php` girano solo da riga di comando; dal web rispondono 403
  e il `.htaccess` li nega. Un hosting che sa chiamare il cron solo via HTTP: nel `config.local.php`
  `define('CMS_CRON_TOKEN', '<almeno 32 caratteri a caso>');`, si chiama `cron-daily.php?token=<lo stesso>` e si toglie
  la riga `cron-` dal `.htaccess`. Su danilofailli.it il cron di cPanel usa la riga di comando: niente da cambiare.
- **Password dimenticata** (dalla 1.1.1): al massimo 3 richieste l'ora per email e 10 per IP, con un conteggio a parte:
  chi conosce la tua email non ti blocca il login.
- **Immagini caricate** (dalla 1.1.1): finiscono sempre nella cartella del CMS (`cms_uploads_dir()`, di norma `uploads/`;
  su danilofailli.it `wp-content/uploads`), la stessa che proteggono il `.htaccess` e la pagina Sicurezza.
- **Chiamate in uscita senza curl**: rifiutate (non si puo fissare l'IP controllato); curl c'e su ogni hosting cPanel.
- **Pannello senza gestori in linea**: i pulsanti usano attributi `data-*` agganciati da `admin/assets/pannello.js`, cosi la
  CSP del pannello resta senza `'unsafe-inline'`. Fanno eccezione tre pagine vecchie del modulo di danilofailli.it
  (Social copy, Importa SQL, Dashboard del burnout), dichiarate dal modulo con il filtro `csp_pannello_inline`.

## Configurare passo per passo

Pannello → gruppo **Impostazioni** → **Sicurezza**. Quattro schede più lo Stato.

### Il primo giorno (consigliato)
1. **Attiva il tuo secondo fattore.** Vai su **Il mio accesso** (in fondo al menu laterale), pulsante *Attiva il
   secondo fattore*. Apri sul telefono un'app di autenticazione gratuita (Google Authenticator, Microsoft
   Authenticator, Aegis, 1Password...), scegli "aggiungi account" e inquadra il codice QR. Scrivi le sei cifre che
   l'app mostra e conferma. **Salva subito gli otto codici di recupero**: stampali o mettili nel gestore di password.
   Non si rivedono più.
2. **La CSP parte gia applicata.** Se aggiungi un widget HTML o un codice che carica script da un altro sito e qualcosa
   non funziona, guarda la scheda *Rapporti CSP*: aggiungi la sorgente in *Sorgenti in più* (scheda Impostazioni),
   oppure, per una prova, metti la CSP su "solo rapporto". Righe con `chrome-extension` o `moz-extension` sono
   estensioni dei browser dei visitatori, non problemi.
3. Un sito aggiornato da una versione prima della 1.1.2 tiene la scelta che aveva: portala su **Applica** quando i
   rapporti sono puliti.
4. Controlla lo **Stato**: tutte le voci dovrebbero essere verdi o grigie. Il giallo dice cosa stringere.

### Rendere il 2FA obbligatorio per gli amministratori
Scheda Impostazioni, spunta *Secondo fattore obbligatorio per tutti gli amministratori*. Si può spuntare solo dopo
aver attivato il 2FA sul proprio accesso. Da quel momento un amministratore senza 2FA, al prossimo accesso, deve
configurarlo prima di fare altro.

### IP consentiti (solo con un indirizzo fisso)
Serve solo se ti connetti sempre dallo stesso indirizzo (ufficio con IP fisso, VPN aziendale). Scheda Impostazioni,
scrivi gli indirizzi o le reti (uno per riga, anche `203.0.113.0/24`), poi spunta *Lista IP attiva*. Il salvataggio
si rifiuta se il tuo indirizzo di adesso non è in elenco, così non ti chiudi fuori. **Attenzione**: se la tua
connessione di casa cambia IP (succede spesso), ti ritrovi chiuso fuori. In quel caso vedi sotto.

### File .php nella radice del sito (pacchetti del trasloco)
Un pacchetto che scrive un file eseguibile (`.php` e simili) nella radice del sito (destinazione `root/`) il pannello lo
accetta solo se e **firmato**. Senza firma, nella radice passano solo file statici (robots.txt, immagini, css, js, html)
e il `.htaccess`. Per un pacchetto non firmato che ne ha bisogno, come `2026-10-23-accendi-sito-nuovo.zip` di
danilofailli.it (scrive `root/index.php`), ci sono due strade:
1. **La finestra di un'ora** (la piu semplice): Impostazioni, Sicurezza, scheda Impostazioni, riquadro *Finestra per i
   pacchetti del trasloco*: scrivi la tua password e clicca *Apri la finestra per un'ora*. Poi carichi e applichi il
   pacchetto dal pannello Aggiornamenti come sempre. Dopo un'ora la finestra si chiude da sola (o con *Chiudi adesso*).
2. **La firma**: `PACCHETTO_CHIAVE=<chiave> python3 tools/pacchetto.py --rifirma <pacchetto.zip>` scrive
   `<pacchetto>-firmato.zip`; la stessa chiave sta nel `config.local.php` del server come `CMS_FIRMA_PACCHETTI`.

### Temi con PHP (dal 27 ottobre 2026)
Un tema caricato da Editor, Temi che contiene codice PHP passa dalle stesse due strade: **la finestra di un'ora** qui
sopra, oppure lo zip firmato con `PACCHETTO_CHIAVE=... python3 tools/pacchetto.py --rifirma tema.zip`. Con *I pacchetti
devono essere firmati* spuntato, anche i temi senza PHP devono essere firmati. Nella cartella `temi/` il CMS scrive da
solo un `.htaccess` che dal web non esegue niente (vedi `temi.md`); la scheda di stato lo controlla.

### Firma dei pacchetti di aggiornamento
Se vuoi che il pannello accetti solo pacchetti firmati, scheda Impostazioni, spunta *I pacchetti devono essere
firmati*. Chi costruisce il pacchetto lo firma con `python3 tools/pacchetto.py <cartella> [nome.zip] --firma`, dopo
aver messo la chiave nella variabile d'ambiente `PACCHETTO_CHIAVE` (lo stesso valore di `CMS_FIRMA_PACCHETTI` nel
`config.local.php` del server; se non la metti, si usa `CMS_SEGRETO`). Un pacchetto senza firma, o firmato con una
chiave diversa, viene rifiutato.

## Se ti chiudi fuori

Apri `includes/config.local.php` sul server (file manager di cPanel, cartella del CMS) e aggiungi una riga:

```php
define('CMS_SICUREZZA_SPEGNI', true);
```

Da quel momento il pannello **non chiede più il secondo fattore e ignora la lista IP**: entri con nome utente e
password da qualunque indirizzo. Rientrato, sistema le cose (riattiva il 2FA, correggi la lista IP) e **togli quella
riga**. Lasciata lì, tiene spente 2FA e lista IP per sempre: la pagina Stato te lo ricorda in rosso.

Casi tipici:
- **Ho perso il telefono con l'app** ma ho i codici di recupero: al login, dove chiede le sei cifre, scrivi un codice
  di recupero. Poi da *Il mio accesso* genera codici nuovi e riconfigura l'app.
- **Ho perso telefono e codici**, ma c'è un altro amministratore: lui apre *Utenti*, trova il tuo nome e clicca
  *azzera* nella colonna 2FA. Al tuo prossimo accesso basta la password.
- **Sono l'unico amministratore** e ho perso tutto: usa la riga `CMS_SICUREZZA_SPEGNI` qui sopra.
- **La lista IP mi ha chiuso fuori**: stessa riga. Poi correggi o spegni la lista.
- **"Troppi tentativi" sul mio nome utente** (qualcuno sta provando password): dal computer da cui entri di solito
  la password giusta funziona lo stesso. Da un computer nuovo aspetta 15 minuti, oppure entra da quello di sempre.
  Nel registro trovi i tentativi e gli indirizzi.

## Dove si configura il server (Apache e Nginx)

Le intestazioni di sicurezza e la CSP le manda il CMS da solo. Il web server deve solo **negare dal web** le cartelle
e i file riservati e **spegnere PHP nella cartella delle immagini**. Gli esempi pronti sono in
`install/esempi/htaccess-radice.txt`, `htaccess-sottocartella.txt`, `htaccess-uploads.txt` e `nginx.txt`; i passi
stanno in `INSTALLA.md`. La scheda Stato della pagina Sicurezza prova da sola a leggere `config.local.php`, la cartella
`logs` e `VERSIONE` dal web: se rispondono, te lo dice in rosso.

## Scelte consapevoli (restano così)

Due punti del terzo attacco (1.1.3) erano informativi. Restano come sono, per questi motivi.

- **I campi `~b64~` passano sotto il firewall del server.** Il pannello manda i testi lunghi, l'HTML, il JSON e l'SQL
  in base64 con il prefisso `~b64~` (`admin/assets/campi-protetti.js`), e `auth.php` li decodifica prima di tutto il
  resto. ModSecurity non vede quindi il contenuto vero. È voluto: senza, ModSecurity su cPanel risponde 403 a ogni
  salvataggio che contiene HTML, e il pannello smette di funzionare (è successo il 24 ottobre). La difesa vera del
  pannello non è il firewall: sono il login, il token CSRF su ogni POST, i ruoli, e i sanificatori del CMS
  (`includes/sicurezza-html.php`) che puliscono l'HTML, il CSS e gli SVG dopo la decodifica. Il firewall resta utile
  per il sito pubblico; per il pannello non va contato come barriera.
- **`sicurezza_origine_ok()` accetta una richiesta senza Origin e senza Referer.** Il controllo dell'origine è una
  difesa in più, sopra il token CSRF, che ogni pagina del pannello controlla a ogni salvataggio (`verify_csrf()`,
  `csrf_ok()`): una richiesta senza il token giusto è rifiutata comunque, con o senza intestazioni. Rifiutare le richieste senza le due intestazioni chiuderebbe fuori chi ha un browser, un
  antivirus o un'estensione per la privacy che le toglie, e non aggiunge protezione: chi non ha il token non passa.
  Quando le intestazioni ci sono e indicano un altro sito, la richiesta è rifiutata.

## Prove di attacco

`tools/sicurezza-prova.js` lancia 79 attacchi (XSS, CSRF, forza bruta, blocco del nome utente, tempi del login,
upload travestiti, redirect aperti, SQLi, IDOR, ruoli, session fixation, accesso diretto ai file, intestazioni, CSP)
e passa solo se ognuno **fallisce**. Si lancia sul sito
di prova locale, mai sul vivo:

```
node tools/sicurezza-prova.js http://127.0.0.1:8098 <utente> <password> /new
```

Crea un redattore di prova e lo cancella alla fine. Da rilanciare a ogni versione. Con `CMS_DIR=<cartella del CMS sul
disco>` nell'ambiente fa anche le prove che leggono i file (ogni `.php` da anonimo, l'installatore) e quelle dei blocchi
del login, che girano con `php` da riga di comando e indirizzi IP simulati (10.77.x.x), poi cancellano i tentativi e le
righe del registro che hanno creato. La prova di forza bruta blocca invece l'IP da cui lanci la batteria: alla fine
svuota la tabella `sicurezza_tentativi` del sito di prova prima di altre prove.
