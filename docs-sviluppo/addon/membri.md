# Addon Area riservata (`membri`)

Versione 1.0.0, con plumeCMS 2.0.0. Cartella `cms/addon/membri/`. Si accende da Impostazioni > Addon.

Account per i **visitatori del sito**, separati dagli utenti del pannello: tabella propria, cookie proprio, nessun uso di
`$_SESSION`. Un membro non entra nel pannello; un utente del pannello non è un membro.

## File

| File | Cosa |
|---|---|
| `modulo.php` | il manifesto: menu, pagine del pannello, eventi `sito_percorso` e `sito_contenuto`, filtri `contenuto_riservato` e `dashboard_numeri`, widget, passo del cron |
| `includes/membri.php` | il motore: tabelle, impostazioni, membri, link monouso, cookie e sessione, CSRF, tentativi, email, contenuti e file riservati, pulizia |
| `includes/pagine.php` | le pagine pubbliche (`/accedi/`, `/iscriviti/`, `/area-riservata/`...) e il controllo dei contenuti riservati |
| `includes/admin-elenco.php` | pannello: elenco dei membri, Attiva, Sospendi, Elimina, Invita, Esporta CSV |
| `includes/admin-riservati.php` | pannello: pagine e articoli "solo per i membri", file riservati dati ai membri |
| `includes/admin-impostazioni.php` | pannello: chi può iscriversi, testi, avvisi |
| `includes/widget.php` | il widget "Accesso membri" |
| `assets/membri.css`, `assets/membri.js` | stile delle pagine pubbliche; captcha del modulo Iscriviti (senza sessionStorage: il modulo ha le password) |

## Tabelle

| Tabella | Colonne | Note |
|---|---|---|
| `{p}membri` | `id`, `email` (unica, minuscola), `nome`, `password_hash`, `stato`, `creato`, `confermato`, `ultimo_accesso`, `epoca`, `ip` | stati: `da_confermare`, `in_attesa`, `invitato`, `attivo`, `sospeso` (`MEMBRI_STATI`, `membri.php:25`) |
| `{p}membri_sessioni` | `token` (sha256 del token del cookie), `membro_id`, `creata`, `ultimo`, `scade`, `ip`, `user_agent` | il token vero sta solo nel cookie |
| `{p}membri_token` | `token` (sha256), `scopo` (`conferma`, `password`, `invito`, `email`), `membro_id`, `dato`, `scade`, `creato` | `dato`: la nuova email (scopo `email`) o l'hash della password di una seconda iscrizione (scopo `conferma`) |
| `{p}membri_riservati` | `fonte` (`articles`, `pages`), `contenuto_id`, `creato` | i contenuti "solo per i membri" |

Si creano accendendo l'addon (`membri_installa()`, `membri.php:40`) e, se mancano (dopo un ripristino), alla prima richiesta
(`membri_schema()`, `membri.php:87`). Impostazioni: `membri_cfg` (JSON: `modo`, `avviso`, `avvisa_admin`, `file`, `benvenuto`),
`membri_schema`. I blocchi dei tentativi stanno nella tabella del nucleo `{p}sicurezza_tentativi`, con chiavi `membri-...`.

## Funzioni principali (`includes/membri.php`)

| Funzione | Riga | Cosa |
|---|---|---|
| `membri_cfg()`, `membri_cfg_salva()` | 98, 110 | impostazioni (modo dell'iscrizione: `aperta`, `approvazione`, `invito`) |
| `membri_testo($s, $max)` | 122 | testo scritto da una persona: senza `\0`, `\r` e caratteri a 4 byte |
| `membri_per_email()`, `membri_leggi()`, `membri_conta()`, `membri_elenco()` | 132-155 | letture |
| `membri_crea()`, `membri_imposta_stato()`, `membri_elimina()` | 170, 178, 186 | un membro sospeso esce subito (sessioni chiuse); l'eliminazione chiama l'evento `membro_eliminato` |
| `membri_password_imposta()`, `membri_password_errore()` | 193, 198 | `sicurezza_password_hash()` (argon2id o bcrypt) e le regole del pannello (`sicurezza_password_errore()`); `epoca` + 1 |
| `membri_token_crea()`, `membri_token_leggi()` | 207, 218 | link monouso: 32 byte a caso, nel database solo lo sha256, scadenza, uno per scopo e membro |
| `membri_impronta()`, `membri_cookie_nome()`, `membri_cookie()` | 235-243 | cookie `membri_<8 hex>` e `membri_csrf_<8 hex>` (impronta di prefisso e cartella: due siti sullo stesso dominio non si mescolano), `path` = cartella del sito, HttpOnly, SameSite=Lax, Secure su https |
| `membri_csrf()`, `membri_csrf_campo()`, `membri_csrf_ok()` | 249-265 | campo `_mt` = HMAC-SHA256 (segreto `moduli_segreto()`) del cookie CSRF, più `sicurezza_origine_ok()` |
| `membri_corrente()` | 273 | il membro di questo browser: sessione non scaduta e membro `attivo`, una query per richiesta |
| `membri_entra()`, `membri_esci()`, `membri_sessioni_chiudi()` | 293, 307, 317 | apre (12 ore, o 30 giorni con "Resta collegato") e chiude; all'accesso il cookie CSRF si rinnova |
| `membri_bloccato()`, `membri_tentativo_fallito()`, `membri_tentativo_riuscito()` | 336-361 | 10 accessi sbagliati per IP o per email (in 15 minuti) e parte un blocco di 15 minuti che raddoppia, fino a un giorno |
| `membri_verifica_accesso()` | 370 | lo stesso messaggio e lo stesso tempo (`sicurezza_hash_finto()`) per email inesistente e password sbagliata |
| `membri_manda()` | 394 | `posta_invia()` con `moduli_email_html()` del nucleo, tipo `membri` nel registro della posta |
| `membri_riservati()`, `membri_contenuto_riservato()`, `membri_contenuto_chiuso()`, `membri_riservato_imposta()` | 421-446 | i contenuti per i membri; l'anteprima del pannello (`SITO_ANTEPRIMA`) li vede |
| `membri_file()` | 454 | i file **riservati** di Download (`includes/scaricabili.php`) scelti per i membri |
| `membri_pulizia()` | 470 | passo del cron: sessioni e link scaduti, iscrizioni mai confermate dopo 7 giorni |

## Pagine pubbliche (`includes/pagine.php`)

Tutte passano dall'evento `sito_percorso` (manifesto) e da `membri_pagina()` (`pagine.php:85`). Ogni POST controlla `_mt`
(`membri_post_ok()`, `pagine.php:73`: 403 se manca o è di un altro browser). Ogni pagina esce con `noindex`,
`Cache-Control: private, no-store` e senza popup (`membri_html()`, `pagine.php:33`).

| Indirizzo | Funzione | Note |
|---|---|---|
| `/accedi/` | `membri_pagina_accedi()` (134) | `?torna=` solo dentro il sito (`sicurezza_percorso_interno()`), mai `/accedi/` o `/esci/` |
| `/iscriviti/` | `membri_pagina_iscriviti()` (165) | captcha del nucleo (`moduli_captcha_valido()`), campo esca, privacy, 5 iscrizioni l'ora per IP (`sicurezza_limite()`); stessa risposta se l'email c'è già (al proprietario arriva "Hai già un account") |
| `/conferma-iscrizione/?t=` | `membri_pagina_conferma()` (205) | un pulsante (POST), non la conferma diretta: i filtri antispam aprono i link |
| `/password-dimenticata/` | `membri_pagina_dimenticata()` (235) | stessa risposta sempre; 10 richieste l'ora per IP, 3 per email; il link vale 60 minuti |
| `/nuova-password/?t=` | `membri_pagina_nuova_password()` (254) | anche per gli inviti (scopo `invito`, 7 giorni); chiude tutte le sessioni del membro |
| `/conferma-email/?t=` | `membri_pagina_conferma_email()` (399) | il cambio di email, confermato dal nuovo indirizzo |
| `/esci/` | in `membri_pagina()` | solo POST con `_mt` |
| `/area-riservata/` | `membri_pagina_area()` (285) | contenuti per i membri, file, sezioni degli altri addon (filtro `membri_area`) |
| `/area-riservata/profilo/` | `membri_pagina_profilo()` (329) | nome; email e password con la password attuale; cancella il mio account (password e spunta) |
| `/area-riservata/file/<nome>/` | `membri_manda_file()` (311) | solo membri entrati e solo i file scelti; `private, no-store`; conta il download |

Un indirizzo che comincia con questi percorsi vince su una pagina del sito con lo stesso slug (l'evento viene prima della mappa).

## Ganci del nucleo toccati (2.0.0)

| File | Riga | Cosa |
|---|---|---|
| `cms/sito/index.php` | 220 | **evento nuovo** `sito_contenuto($c, $meta)` nel caso `contenuto` della mappa, prima di disegnare articoli e pagine: chi ascolta risponde e chiude (`exit`) oppure lascia andare. L'addon lo usa per la pagina "Solo per i membri" (`membri_controlla_contenuto()`, `pagine.php:414`) |
| `cms/includes/sito-dati.php` | 154 | **filtro nuovo** `contenuto_riservato(null, $c)` in testa a `sito_html_contenuto()`: una stringa al posto del testo vero. Copre llms-full.txt, i widget che mostrano il testo di un contenuto, i conti dei minuti di lettura. L'addon dà l'avviso a chi non è entrato |

Con l'addon spento nessuno ascolta e il sito è identico (provato: `prova-addon-membri.js`, punto 17).

Ganci che l'addon offre agli altri addon: evento `membro_eliminato($id)` (`membri.php:190`), filtro `membri_area($sezioni, $membro)`
(`pagine.php:302`, sezioni `[titolo, html]`), evento `membri_area_percorso($percorso, $q, $membro)` (`pagine.php:106`, gli indirizzi
sotto `/area-riservata/` che l'addon non conosce).

## Sicurezza

- Password: `password_hash` attraverso `sicurezza_password_hash()`; nel database solo l'hash; regole del pannello (12 caratteri).
- Sessione: token di 256 bit nel cookie, sha256 nel database; scadenza lato server; sospensione, cambio di password e
  cancellazione chiudono le sessioni. Nessuna sessione PHP.
- CSRF: `_mt` legato a un cookie del browser + Origin/Referer. Il pannello usa il suo `verify_csrf()`.
- Enumerazione: stesse risposte (pagina intera identica per Password dimenticata, stesso messaggio per l'accesso, stessa
  pagina per l'iscrizione, stessa risposta per il cambio di email del profilo).
- Forza bruta: blocchi in `sicurezza_tentativi`, visibili e sbloccabili dalla pagina Sicurezza; non toccano l'accesso al pannello.
- XSS: ogni testo esce con `h()`; nomi senza caratteri a 4 byte. CSV: `sicurezza_csv_riga()` (formule neutralizzate).
- Attacchi provati: `prove/attacchi-addon-a.js` (108 su 108, insieme a Corsi e Copia altrove).

## Limiti noti

- Spegnendo l'addon i contenuti riservati tornano pubblici (il nucleo non sa più che erano riservati). Il pannello lo dice.
- La ricerca del sito (`/?s=`) cerca nel titolo, nel TL;DR e nella descrizione: quelli di un contenuto riservato restano
  visibili (come il titolo nella pagina chiusa). La sitemap elenca anche gli indirizzi riservati (la pagina chiusa è noindex).
- Il riquadro "solo per i membri" si sceglie dalla pagina Contenuti e file riservati, non dalla scheda del contenuto.
- Il widget Accesso membri e i contenuti riservati presuppongono che le pagine non stiano in una cache condivisa (le pagine
  riservate mandano `private, no-store`; le altre pagine del sito non sono in cache nel CMS).
- La password del membro segue le regole del pannello (almeno 12 caratteri), più severe di quelle di molti siti.

## Prove

- `prove/prova-addon-membri.js` (sito Attività nuovo, posta finta): 74 su 74 in radice; con il CMS in `/test` vedi il resoconto.
- `prove/pagina-addon-a.php`: crea una pagina con il widget, per la prova.
