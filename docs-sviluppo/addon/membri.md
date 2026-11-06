# Addon Area riservata (`membri`)

Versione 1.1.0 (plumeCMS 2.1, in lavorazione; la 1.0.0 è della 2.0.0). Cartella `cms/addon/membri/`. Si accende da Impostazioni > Addon.

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
| `membri_elenco_filtra($tutti, $dove)` | 458 | (1.1.0) filtro `contenuti_elenco`: toglie i riservati da sitemap, feed, llms.txt e ricerca (sotto) |
| `membri_fonte_tipo()`, `membri_e_home()` | 468, 473 | (1.1.0) `articoli` e `pagine` della scheda verso `articles` e `pages`; la home resta di tutti |
| `membri_scheda_riquadro($tipo, $id, $r)`, `membri_scheda_salva($tipo, $id, $d)` | 482, 498 | (1.1.0) il riquadro "Area riservata" della scheda e il suo salvataggio (sotto) |
| `membri_file()` | 510 | i file **riservati** di Download (`includes/scaricabili.php`) scelti per i membri |
| `membri_pulizia()` | 526 | passo del cron: sessioni e link scaduti, iscrizioni mai confermate dopo 7 giorni |

## Dalla scheda del contenuto, e fuori dagli elenchi pubblici (1.1.0)

**Dalla scheda.** Nella scheda di un articolo o di una pagina (`admin/contenuto.php`, colonna di destra dopo Header e footer)
c'è il riquadro **Area riservata** con la spunta "Solo per i membri" (`#membri-riservato`), la stessa cosa della pagina
Membri > Contenuti e file riservati: le due si vedono a vicenda (una sola tabella, `{p}membri_riservati`).
- Lo vede e lo salva solo l'amministratore (`is_admin()`), come le pagine Membri. Il redattore non lo vede e, se manda a mano
  i campi del riquadro, non cambia niente (`membri_scheda_salva()` controlla il ruolo da sé).
- Il campo nascosto `membri_scheda=1` dice che il riquadro c'era: senza, la spunta assente non si legge come "pubblico"
  (un salvataggio che non viene dalla scheda non tocca la riservatezza).
- La home: spunta spenta, "La home resta di tutti", e il salvataggio la lascia pubblica comunque.
- Solo articoli e pagine (i tipi dei moduli no). La scheda c'è solo per i contenuti che esistono già (un contenuto nuovo nasce
  nell'editor visuale).

**Fuori dagli elenchi pubblici** (`membri_elenco_filtra()`, filtro `contenuti_elenco` del nucleo). Un contenuto "solo per i
membri", titolo e riassunto compresi, non esce in:
- la sitemap (`/sitemap.xml` e le sitemap divise) e quindi nemmeno nella coda di IndexNow (`seo_sitemap_voci()`);
- i feed RSS (`/feed/`, delle categorie e dei tag; il feed del singolo articolo risponde 404);
- `llms.txt` e `llms-full.txt`;
- la ricerca del sito (`/?s=`), tranne per un **membro collegato**: allora la pagina dei risultati esce con
  `Cache-Control: private, no-store`. L'amministratore in anteprima non è un membro: la ricerca non li mostra nemmeno a lui.

Dalla 2.1.0 vale anche per gli archivi del blog, delle categorie, dei tag e per data, per i conteggi delle categorie e per i
widget che elencano articoli (Ultimi articoli, Griglia di contenuti): da fuori il riservato non c'è, un membro collegato lo vede
(`$dove` `elenco`, pagina `private, no-store`).

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
| `cms/sito/index.php` | 220 | **evento nuovo** `sito_contenuto($c, $meta)` nel caso `contenuto` della mappa, prima di disegnare articoli e pagine: chi ascolta risponde e chiude (`exit`) oppure lascia andare. L'addon lo usa per la pagina "Solo per i membri" (`membri_controlla_contenuto()`, `pagine.php:416`) |
| `cms/includes/sito-dati.php` | 164 (era 154 nella 2.0.0) | **filtro nuovo** `contenuto_riservato(null, $c)` in testa a `sito_html_contenuto()`: una stringa al posto del testo vero. Copre llms-full.txt, i widget che mostrano il testo di un contenuto, i conti dei minuti di lettura. L'addon dà l'avviso a chi non è entrato |

Con l'addon spento nessuno ascolta e il sito è identico (provato: `prova-addon-membri.js`, punto 17).

Ganci del nucleo nuovi (2.1, addon 1.1.0):

| File | Riga | Cosa |
|---|---|---|
| `cms/includes/sito-dati.php` | 155 | **funzione e filtro nuovi** `sito_contenuti_elenco($dove)` = `cms_filtro('contenuti_elenco', sito_contenuti(), $dove)`, con `$dove` fra `cerca`, `feed`, `sitemap`, `llms`. Senza addon è `sito_contenuti()` |
| `cms/sito/viste.php` | 484 | `vista_cerca()` legge `sito_contenuti_elenco('cerca')` |
| `cms/sito/extra.php` | 16, 60, 75 | `extra_articoli($dove)` (feed), `extra_sitemap()`, `extra_llms()` |
| `cms/includes/seo.php` | 831, 862 | `seo_llms_full()` e `seo_sitemap_voci()` (sitemap e IndexNow) |
| `cms/admin/contenuto.php` | 285 | **filtro nuovo** `scheda_riquadri([], $tipo, $id, $r)`: un elenco di HTML (ognuno un `<div class="sc-scatola">`) nella colonna di destra della scheda, per un contenuto che esiste già |
| `cms/admin/contenuto.php` | 62 | **evento nuovo** `scheda_salvata($tipo, $id, $d)` dopo il salvataggio della scheda (`$d` = il POST): chi ascolta controlla da sé il ruolo e che il suo campo sia arrivato |

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
- Attacchi provati: `prove/attacchi-addon-a.js` (108 su 108 in radice e in `/test`, insieme a Corsi e Copia altrove).

## Limiti noti

- Spegnendo l'addon i contenuti riservati tornano pubblici (il nucleo non sa più che erano riservati). Il pannello lo dice.
- (Corretti nella 1.1.0: la ricerca, la sitemap, i feed e llms.txt non mostrano più titolo e riassunto dei contenuti
  riservati; la spunta si sceglie anche dalla scheda del contenuto.)
- (Corretto nella 2.1.0: archivi del blog, categorie, tag, per data e i widget degli articoli.) Il titolo resta visibile
  nella pagina chiusa, per chi ne conosce l'indirizzo.
- Il widget Accesso membri e i contenuti riservati presuppongono che le pagine non stiano in una cache condivisa (le pagine
  riservate mandano `private, no-store`; le altre pagine del sito non sono in cache nel CMS).
- La password del membro segue le regole del pannello (almeno 12 caratteri), più severe di quelle di molti siti.

## Prove

- `prove/prova-addon-membri.js` (sito Attività nuovo, posta finta): 75 su 75 in radice e 75 su 75 con il CMS in `/test`
  (2.0.0). Dalla 1.1.0 anche la scheda (Chi siamo e Servizi, la home spenta, la spunta tolta e rimessa) e gli elenchi
  (ricerca per titolo, sitemap, feed, llms.txt e llms-full.txt prima, da fuori e da membro): 92 su 92 in radice e in `/test`
  (31 ottobre 2026).
- `prove/attacchi-addon-a.js`: gruppo 1 con la ricerca per titolo e un cookie inventato; gruppo 12 (1.1.0) con un articolo
  riservato dalla scheda (feed, llms, sitemap, ricerca, feed del singolo articolo) e il redattore che manda a mano i campi
  del riquadro.
- `prove/pagina-addon-a.php`: crea una pagina con il widget, per la prova.
