# Newsletter e form come Brevo: confronto, modello dei dati e piano

Documento di analisi del 1 novembre 2026, plumeCMS 2.7.0. Nessun codice è stato cambiato.

Richiesta di Danilo: il modulo "Newsletter e form" deve funzionare "esattamente come Brevo, nello stesso identico modo":
stesse voci, stessi passi nello stesso ordine, stesse opzioni e regole. Non il marchio né la grafica: l'aspetto resta
quello di plumeCMS (regole in `docs-sviluppo/regole-interfaccia.md`).

Ambito: contatti, attributi, liste e cartelle, segmenti, importazione ed esportazione, lista nera, form di iscrizione e
doppio opt-in, pagina di disiscrizione e preferenze, campagne email, modelli, A/B, prova, programmazione, statistiche,
automazioni, impostazioni di invio. Fuori: SMS, WhatsApp, chat, CRM (responsabili, aziende, trattative, compiti).

## Fonti e come leggerle

- **[CF x.y]**: `brevo/contatti-form.md` (ricerca del 1 novembre 2026), capitolo x.y. Contatti, attributi, liste,
  segmenti, importazione, lista nera, form, disiscrizione, consenso.
- **[CA x][n]**: `brevo/campagne-automazioni.md`, capitolo x, fonte numero n del suo elenco. Campagne, editor, modelli,
  A/B, statistiche, automazioni, invio.
- Le due ricerche non hanno potuto aprire help.brevo.com e developers.brevo.com (blocco di rete della sessione): hanno
  letto gli estratti del motore di ricerca e il riferimento delle API dai repository pubblici di Brevo su GitHub. Dove
  manca un dettaglio necessario per essere identici, il capitolo 5 lo elenca.
- **plumeCMS oggi**: verificato sul codice della 2.7.0 (`cms/includes/newsletter.php`, `newsletter-flussi.php`,
  `newsletter-email.php`, `form.php`, `consegna.php`; `cms/admin/newsletter*.php`, `form.php`, `form-submissions.php`,
  `consegna.php`; `admin/includes/admin-header.php` righe 27-34 per il menu).

Segni della colonna "Peso": **S** fino a un giorno di lavoro, **M** da due a cinque giorni, **L** oltre una settimana
(compresi prove, manuali e schermate). "uguale" = plumeCMS fa già come Brevo.

---

## 1. Confronto voce per voce

### 1.1 Contatti: pagina, ricerca, azioni

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Base dei contatti | Una base unica; liste come appartenenze (più liste o nessuna); stato di iscrizione per canale, non per lista [CF 1.1] | Tabella `newsletter_iscritti`, liste in `newsletter_iscritti_liste`; stato unico (`in_attesa`, `attivo`, `uscito`, `rimbalzato`, `spam`) | Base unica già c'è. Lo stato diventa "canale email: Iscritto / In lista nera" con motivo (vedi 1.6); un contatto può stare in nessuna lista | M |
| Nome della voce | **Contatti**, con dentro Liste e Segmenti [CF 1.2] | "Iscritti e liste" (`newsletter.php?scheda=iscritti`) | Voce "Contatti" con le schede Contatti, Liste, Segmenti (menu nel capitolo 2) | S |
| Colonne predefinite | CONTATTO, ISCRITTO, IN LISTA NERA, DATA DI CREAZIONE, ULTIMA MODIFICA, non cancellabili [CF 1.3] | Email, nome, stato, liste, data | Stesse cinque colonne fisse (`creato`, `aggiornato` esistono già) | S |
| Personalizza le colonne | Pulsante "Personalizza le colonne": aggiunge attributi come colonne, si trascinano, si tolgono [CF 1.3] | Assente | Finestra con le caselle degli attributi e trascinamento (SortableJS, già in casa); scelta salvata per utente | M |
| Viste | Schede salvate (filtri + colonne + ordine); "Tutti i contatti" e "I miei contatti" non modificabili; **+** > Crea vista [CF 1.3] | Assente | Tabella `newsletter_viste`; scheda "Tutti i contatti" fissa. "I miei contatti" è CRM (responsabile): fuori ambito, da non mettere | M |
| Ricerca | Per nome, email (anche un pezzo), in tutta la base o dentro "Carica una lista o un segmento" [CF 1.3] | Ricerca per email e nome, filtro per lista e stato | Aggiungere "Carica una lista o un segmento" come scelta sopra la tabella | S |
| Filtri | "Aggiungi filtro" con le stesse condizioni dei segmenti; il risultato si salva come segmento [CF 1.3] | Filtri fissi (lista, stato, errori) | Usa il motore dei segmenti (fase 3) e il pulsante "Salva come segmento" | M (dopo i segmenti) |
| Paginazione e ordine | 50 per pagina predefiniti, ordine per data di creazione decrescente [CF 1.3, API] | Nessuna paginazione (inventario) | Paginazione da 50 con scelta 50/100/500; ordine per creazione decrescente | S |
| Selezione | Casella in alto: tutti i contatti del filtro, non solo la pagina visibile [CF 1.3] | Azioni "su tutti i risultati" del filtro (`nl_filtro`) | Casella per riga più "seleziona tutti i N del filtro" | S |
| Azioni di gruppo | Aggiungi a liste; Metti in lista nera; Modifica (attributo); Esporta; Elimina definitivamente; Togli dalle liste (in Altre azioni) [CF 1.3, 3.6] | Liste in massa, Esci, Cancella dati | Le sei azioni con gli stessi nomi; "Assegna un responsabile" e "Copia SMS in WhatsApp" fuori ambito | M |
| Crea un contatto | Pulsante "Crea un contatto": un campo per attributo; almeno un identificatore; liste facoltative (anche lista nuova); "Crea"; riquadro con il link al contatto; se esiste già porta all'esistente [CF 1.4] | Impossibile aggiungere un contatto a mano | Pagina `newsletter-contatto.php?nuovo=1` con tutti gli attributi, menu Liste con "Crea una lista", stessa regola dei doppioni | M |
| Valore del tipo sbagliato | Si ignora senza errore [CF 1.4, API] | Non applicabile (niente attributi) | Stessa regola in `contatto_salva_attributi()` | S |
| Scheda del contatto | Quattro schede: Panoramica, Cronologia, Liste, Preferenze [CF 1.5] | Assente | Pagina `newsletter-contatto.php?id=` con le quattro schede (dettaglio nella riga dopo) | L |
| Panoramica | Attributi, cronologia recente, statistiche email (inviate, consegnate, aperte, cliccate con link, hard e soft bounce, spam, disiscrizioni) degli ultimi 90 giorni [CF 1.5] | I dati ci sono in `newsletter_coda` e `newsletter_eventi`, non mostrati | Riquadro attributi modificabile più numeri dei 90 giorni da coda ed eventi | M |
| Cronologia | Registro completo con ricerca e filtro per tipo e periodo [CF 1.5] | Assente | Unione di `newsletter_coda` (inviate), `newsletter_eventi` (aperture, clic, uscite) e tabella nuova `newsletter_storia` (liste, form, attributi, lista nera, importazioni) | M |
| Scheda Liste | Liste a cui appartiene, con gestione [CF 1.5] | Caselle delle liste per iscritto (`liste_iscritto`) | Spostare nella scheda | S |
| Scheda Preferenze | Canale email Iscritto / In lista nera; gruppi di consenso se attivi [CF 1.5] | Assente | Interruttore con l'avviso legale di Brevo (riscrivere chi è uscito è illegale) [CF 6.5] | S |
| Modifica un contatto | Dalla scheda [CF 1.6] | Impossibile | Scheda Panoramica modificabile | S |
| Modifica in massa | Altre azioni > Modifica: solo attributi data, sì/no, categoria [CF 1.6] | Assente | Stessa regola dei tre tipi | S |
| Cambio email di un contatto in lista nera | La lista nera si toglie e il contatto torna iscritto [CF 1.6, API] | Non applicabile | Stessa regola, con una riga in `newsletter_storia` | S |
| Elimina definitivamente | Più contatti o uno; irreversibile; si perdono attributi, cronologia, statistiche e lista nera; Brevo sconsiglia di cancellare chi è in lista nera [CF 1.7] | "Cancella dati" (GDPR): toglie tutto e tiene l'impronta sha256 in `newsletter_soppressi` | Nome e conferma di Brevo; tenere la soppressione come protezione in più (decisione per Danilo, vedi capitolo 6) | S |
| Identificatori | EMAIL, SMS, WHATSAPP, numero fisso, EXT_ID; nessun doppione [CF 1.4] | Email unica (`UNIQUE KEY email`) | Email resta obbligatoria (gli altri canali sono fuori ambito); aggiungere EXT_ID facoltativo e unico | S |

### 1.2 Attributi dei contatti

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Dove | Impostazioni > Contatti > Attributi dei contatti, scheda "Attributi normali" [CF 2.2] | Assente | Pagina `newsletter-attributi.php` (menu: Newsletter e form > Impostazioni > Attributi dei contatti) | M |
| Tipi | Testo, Numero, Data, Sì/No, Categoria (una scelta, opzioni con valore numerico ed etichetta), Scelta multipla, Utente [CF 2.4] | Solo nome e cognome fissi | Tabelle `newsletter_attributi`, `newsletter_attributi_opzioni`, `newsletter_valori`. Il tipo Utente è CRM: fuori ambito | L |
| Categorie | normale, transazionale, categoria, calcolato, globale [CF 2.3] | Assente | Normale e categoria subito; calcolati e globali (COUNT, SUM) in una fase tarda o mai: decisione per Danilo | S / M |
| Attributi di sistema | CONTACT_ID, EXT_ID, EMAIL, FIRSTNAME, LASTNAME, OPT_IN, DOUBLE_OPT-IN, SUBSCRIBED, BLOCKLISTED, CREATION_DATE, LAST_CHANGED; non si cancellano [CF 2.5] | `id`, `email`, `nome`, `cognome`, `creato`, `aggiornato`, `consenso_at` | Attributi di sistema legati alle colonne esistenti (`colonna` in `newsletter_attributi`); nomi in maiuscolo come Brevo, etichetta italiana accanto (NOME = FIRSTNAME) | S |
| Creare | Pulsante in fondo; tipo; nome; opzioni se categoria o scelta multipla; conferma [CF 2.6] | Assente | Stessi passi in una finestra del pannello | S |
| Regole | Massimo 200 attributi; nome e tipo non si cambiano; cancellare toglie i dati a tutti; opzioni massimo 200 caratteri; si crea anche nell'importazione [CF 2.4, 2.6] | Assente | Stesse regole nel motore (`newsletter_attributo_crea()`) | S |
| Uso | Personalizzazione, segmenti, campi dei form, mappatura dell'importazione [CF 2.8] | Solo `{nome}` nelle email | `{{ contact.NOME }}` ovunque (vedi 1.12), condizioni dei segmenti, campi dei form legati ad attributi | dipende dalle fasi |

### 1.3 Liste, cartelle, lista dei test

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Liste statiche | Ci entra solo chi viene aggiunto (a mano, importazione, form, API) [CF 3.1] | Uguale | uguale | - |
| Cartelle | Ogni lista sta in una cartella; Crea una cartella > nome > conferma [CF 3.3] | Nessuna cartella | Tabella `newsletter_cartelle`, colonna `cartella_id` in `newsletter_liste`; cartella di partenza per le liste esistenti | M |
| Crea una lista | Nome e cartella; nasce vuota [CF 3.4] | Nome e descrizione | Aggiungere la scelta della cartella; la descrizione si tiene (in più, innocua) | S |
| Limiti | 300 cartelle, 300 liste [CF 3.5] | Nessun limite | Stessi limiti | S |
| Cancellare | Lista: i contatti restano. Cartella: cancella le sue liste, non i contatti, senza ritorno [CF 3.5] | Lista: uguale | uguale per la lista; regola nuova per la cartella con conferma | S |
| Pagina della lista | Nome, cartella, data, iscritti, statistiche delle campagne inviate a lei in un intervallo di date [CF 3.5] | Conteggi attivi e totali | Pagina della lista con le statistiche per lista (fase statistiche) | M |
| Lista nuova da un segmento | Esiste [CF 3.5] | Assente | Azione nel segmento (fase 3) | S |
| Aggiungi, sposta, togli | Aggiungi a liste; Sposta = aggiungi più togli da una lista d'origine; Togli dalla lista corrente o da tutte [CF 3.6] | Aggiungi e togli in massa | Aggiungere "Sposta" con la lista d'origine | S |
| Disiscrizione da una sola lista | Esiste nei dati (`listUnsubscribed`) e nei form a più liste [CF 6.2] | Assente: l'uscita vale per tutte | Tabella `newsletter_liste_uscite`; la usa il centro preferenze (fase 9) | M |
| Lista dei test | Impostazioni > Lista dei test: un indirizzo per riga, massimo 20, devono essere contatti [CF 3.7] | La prova va a un indirizzo scritto a mano | Pagina "Lista dei test" e scelta dei destinatari nella prova | S |

### 1.4 Segmenti

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Esistenza | Gruppo dinamico che si aggiorna da solo [CF 4.1] | Assente | Tabella `newsletter_segmenti`, motore che traduce le condizioni in SQL | L |
| Passi | Crea un segmento > da zero o da modello > prima condizione, poi **+ E** / **O** > conteggio aggiornato > nome > categoria > Crea [CF 4.3] | Assente | Pagina `newsletter-segmenti.php` con gli stessi passi | L |
| Logica | Fino a 100 condizioni, positive e negative, E/O, date dinamiche "X giorni/settimane/mesi fa" [CF 4.4] | Le condizioni dei bivi dei flussi (9 fisse) | Motore unico delle condizioni, usato da segmenti, filtri della pagina Contatti, bivi delle automazioni ed esclusioni delle campagne | L |
| Condizioni | Attributi; liste e segmenti (membro, non membro, in nessuna lista); email: non aperta, link cliccato, aperta negli ultimi N, link preciso dell'ultima campagna; form inviato [CF 4.5] | Nei flussi: in lista, aperta, cliccata, inattivo, attivo, iscritto da | Tutte queste, con i dati che già ci sono (`newsletter_eventi`, `newsletter_coda`, `moduli_invii`). Sito ed e-commerce fuori | M |
| Modelli di segmento | Per esempio "Contatti email non coinvolti" (né aperto né cliccato negli ultimi 30 giorni) [CF 4.6] | Assente | I modelli trovati; l'elenco completo manca (capitolo 5) | S |
| Organizzazione | Categorie (cartelle) dei segmenti, ricerca [CF 4.3, 4.7] | Assente | Tabella `newsletter_segmenti_cartelle` | S |
| Uso | Carica nella pagina Contatti, destinatari ed esclusioni delle campagne, esportazione, lista nuova [CF 4.7] | Assente | In ogni fase che lo tocca | dipende |
| Tracciamento anonimo | Con aperture e clic anonimi non si segmenta per aperture e clic [CF 4.5] | Il tracciamento è sempre acceso | Regola valida quando arriva il consenso al tracciamento per contatto (fase 9) | S |

### 1.5 Importazione ed esportazione

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| File | CSV, XLSX, TXT, UTF-8 o Windows-1252, fino a 250 MB [CF 5.2] | Solo CSV, separatore indovinato | CSV e TXT subito; XLSX senza librerie esterne (lettura dello zip con `ZipArchive` e XML: fattibile, M); Windows-1252 convertito con `mb_convert_encoding`. Il limite vero è il caricamento dell'hosting: va detto nella pagina (`data-peso-massimo`, già nel pannello) | M |
| Tre modi | Carica un file; Copia e incolla; inserimento manuale [CF 5.3] | Solo file | Aggiungere "Copia e incolla" (area di testo) | S |
| Passo 1 | Carica [CF 5.4] | Un passo unico | Pagina a passi `newsletter-importa.php` | M |
| Passo 2, Abbina i dati | Per colonna: attributo esistente (abbinato da solo se il nome è uguale), "Crea un nuovo attributo", "Non importare"; obbligatoria EMAIL [CF 5.4] | Colonne riconosciute da sole, nessuna mappatura | Tabella di abbinamento con anteprima delle prime righe | M |
| Passo 3, Scegli la lista | Una o più liste esistenti, o una nuova (nome e cartella) [CF 5.4] | Una lista | Più liste e lista nuova | S |
| Passo 4, Concludi | Opzioni: Aggiorna gli attributi degli esistenti; Le celle vuote cancellano; Lista nera email; gruppi di consenso; avviso; promemoria del consenso [CF 5.5, 5.6] | Gli esistenti non si toccano; data del consenso dal file | Le stesse opzioni con l'avviso "irreversibile" | M |
| Conferma e lavoro in sottofondo | "Conferma l'importazione", il lavoro va in sottofondo [CF 5.4] | Tutto nella stessa richiesta | Importazione a giri dal cron e dalla pagina stessa, tabella `newsletter_importazioni` | M |
| Resoconto | Nuovi, aggiornati, invariati, errori, liste; CSV degli scarti [CF 5.7] | Nuovi, già presenti, scartati, saltati | Stesse quattro voci più il file degli scarti | S |
| Doppioni | Nel file si tolgono da soli; gli esistenti non diventano doppioni [CF 5.5] | Uguale | uguale | - |
| Chi è uscito | Brevo li importa? Con l'aggiornamento acceso la lista nera resta: non è scritto che si riscrivano [CF 5.5] | Usciti, rimbalzati, spam e soppressi si saltano | Tenere: coerente con "riscrivere chi è uscito è illegale" [CF 6.5]. Nel resoconto sotto "invariati" | S |
| Esportazione | Carica lista o segmento > seleziona > Altre azioni > Esporta > attributi, separatore (punto e virgola o virgola), "manda per email" > Avvia; CSV pronto in pochi minuti [CF 5.8] | "Esporta CSV" di tutti | Finestra con attributi e separatore; filtri per lista o segmento con tutti / iscritti / disiscritti / disiscritti solo da questa lista; per campagna aperto, non aperto, cliccato, non cliccato, disiscritti, rimbalzi [CF 5.8] | M |

### 1.6 Lista nera, disiscrizioni, rimbalzi

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Principio | Lista nera per canale; il contatto resta con la sua storia [CF 6.1] | Stati `uscito`, `rimbalzato`, `spam` | Stato email "In lista nera" con **motivo** e data; i tre stati di oggi diventano tre motivi | M |
| Disiscrizione = lista nera | Chi clicca "disiscriviti" va in lista nera email [CF 6.2] | Uguale (stato `uscito`, coda svuotata) | uguale nel comportamento; cambia il nome mostrato | S |
| Motivi | Spam, hard bounce, disiscrizione, a mano, da importazione; codici `unsubscribedViaEmail`, `unsubscribedViaMA`, `unsubscribedViaApi`, `adminBlocked`, `hardBounce`, `contactFlaggedAsSpam` [CF 6.3] | Tre stati senza motivo a mano o da importazione | Colonna `blocco_motivo` con questi codici | S |
| Mettere in lista nera | Più contatti dalla pagina, uno dalla scheda, da file con l'importazione [CF 6.4] | Solo "Esci" in massa | Le tre strade | S |
| Togliere dalla lista nera | Solo dalla scheda, uno alla volta, con l'avviso legale; mai in massa [CF 6.5] | Solo con una nuova iscrizione confermata | Interruttore nella scheda Preferenze; la nuova iscrizione confermata resta (è il contatto stesso che chiede) | S |
| Dove si vedono | Colonna IN LISTA NERA, filtro, segmento [CF 6.6] | Filtro per stato | Colonna e condizione di segmento | S |
| Hard bounce | Lista nera subito [CF 6.7] | Uguale (`newsletter_rimbalzato`) | uguale | - |
| Soft bounce | Si riprova per 36 ore; **5 soft bounce di fila sulle campagne = lista nera** [CF 6.7] | Conta `errori_di_fila`; da 3 in su lo segnala ma resta attivo; nessun nuovo tentativo | Riprova nella coda per 36 ore (colonna `riprova_at` in `newsletter_coda`); al quinto di fila lista nera con motivo soft bounce | M |
| Soglie | Hard bounce oltre 2%, disiscrizioni oltre 1%, spam oltre 0,2% [CF 6.7] | Rosso sopra 2% rimbalzi e 0,3% spam (soglie di Gmail) | Usare le soglie di Brevo (0,2% spam, 1% disiscrizioni) | S |

### 1.7 Consenso e GDPR

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| OPT_IN | Attributo sì/no creato dalla casella GDPR del form [CF 9.2] | `consenso_versione`, `consenso_at`, `ip` | Attributo di sistema OPT_IN, valorizzato dai form | S |
| DOUBLE_OPT-IN | Attributo "Yes" quando il contatto clicca il link [CF 9.2] | Stato da `in_attesa` ad `attivo` | Attributo di sistema DOUBLE_OPT-IN letto dalla data di conferma | S |
| Registro del consenso | Data, ora, IP e testo: non trovato in Brevo [CF 9.2] | plumeCMS lo ha in parte (versione, data, IP) | Tenere e allargare: tabella `newsletter_consensi` (testo, form, IP, data). È in più rispetto a Brevo, non contro | S |
| Gruppi di consenso | Impostazioni > Contatti > Gruppi di consenso; nome, descrizione, ingresso automatico o manuale; obbligatori nelle campagne se attivi [CF 9.4] | Assente | Tabelle `newsletter_gruppi_consenso`, `newsletter_contatti_gruppi`; spenti di partenza come in Brevo | M |
| Consenso al tracciamento | Per contatto: aperture e clic anonimi per chi non acconsente [CF 9.5] | Tracciamento per tutti | Colonna `tracciamento` nel contatto; senza consenso niente pixel e link senza `i=` | S |

### 1.8 Form di iscrizione e doppio opt-in

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Tipi | Iscrizione, Pop-up, Aggiornamento del profilo, Disiscrizione [CF 7.2] | Un tipo di form generico (contatti, iscrizione, file) più i Popup in un'altra voce del menu | Colonna `tipo` in `moduli`; i form generici restano (servono al sito: contatti, lavora con noi), quelli con una lista diventano "Iscrizione" | M |
| Costruttore a passi | 1 Impostazione; 2 Aspetto e campi; 3 Messaggi; 4 Conferma dell'iscrizione; 5 Condividi; ogni passo si salva con "Avanti" [CF 7.3] | Una pagina sola (`admin/form.php`) | `admin/form.php` a cinque passi con "Avanti" | L |
| Passo 1 | Nome interno; "Attiva i campi GDPR"; Crea form [CF 7.3] | Nome | Casella "Attiva i campi GDPR" che aggiunge i due blocchi | S |
| Passo 2, campi | Ogni campo è un attributo; blocchi trascinabili; casella da spuntare solo con un attributo sì/no; GDPR, Dichiarazione GDPR, Iscrizione a più liste, Captcha, pulsante [CF 7.3] | 11 tipi di campo liberi, salvati in `moduli_invii.dati` | Ogni campo sceglie un attributo (o lo crea); i form generici possono tenere campi senza attributo. Blocco "Iscrizione a più liste" nuovo | L |
| Captcha | reCAPTCHA o Turnstile [CF 7.3] | Captcha fatto in casa (`moduli.php`) | Tenere quello in casa: niente servizi esterni nel CMS (regola di progetto). Differenza voluta | - |
| Passo 3, messaggi | Messaggio di riuscita, di errore, messaggi dei campi; oppure pagina di conferma a un URL [CF 7.3] | Pagina `/grazie/?f=` con titolo, testo, link | Scelta "Messaggio" o "Pagina dopo l'invio (URL)"; messaggi dei campi modificabili | M |
| Passo 4, conferma | Doppia (consigliata), Semplice (entra subito e riceve un'email), Nessuna; modello dell'email di doppia conferma; pagina dopo il clic (predefinita o URL); liste più lista principale [CF 7.3] | `lista_conferma` 1 = doppia, 0 = entra subito senza email; email di conferma modificabile (2.7.0) | Tre scelte; la "Semplice" è nuova (email di benvenuto); scelta del modello DOI (fase 4) e dell'URL dopo il clic | M |
| Aggiorna i contatti esistenti | Opzione documentata per le landing page [CF 7.3] | Gli esistenti non si toccano | Casella, spenta di partenza (dettaglio non confermato, capitolo 5) | S |
| Passo 5, condividi | Link diretto alla pagina ospitata, codice QR, codice da incorporare: iframe, HTML, HTML semplice [CF 7.3] | Widget dell'editor; nessun codice per altri siti | Pagina ospitata `/modulo/<slug>/`, codici iframe e HTML; QR fatto in casa (senza librerie) | M |
| Modello DOI | Nei Modelli, pulsante con link di tipo "Link di doppio opt-in", tag `optin`, niente personalizzazione [CF 7.5] | Email di conferma a parti (2.7.0) | I modelli con il tag optin compaiono nel passo 4; le parti di oggi diventano il "Modello predefinito di doppio opt-in" | M |
| Form pop-up | Stesso costruttore, passo "Configura il pop-up" [CF 7.4] | Modulo Popup a parte, che può contenere un form | Tipo "Pop-up" che usa il motore dei Popup per comparire; regole di comparsa non trovate | M |

### 1.9 Pagina di disiscrizione e centro preferenze

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Pagina di disiscrizione | Impostazioni > Pagine di disiscrizione > Crea; nome; aspetto; impostazioni; Concludi [CF 8.2] | `/newsletter-esci/` fissa (POST e un clic) | Tabella `newsletter_pagine_uscita`, pagina predefinita uguale a quella di oggi | M |
| Questionario dei motivi | Acceso di serie, cinque motivi; risposte nel resoconto della campagna [CF 8.2] | Assente | Tabella `newsletter_uscite_motivi`; testi dei motivi non trovati (capitolo 5) | S |
| Collegarla a una campagna | Impostazioni aggiuntive > Iscrizione > "Usa una pagina di disiscrizione personalizzata" [CF 8.2] | Assente | Colonna `pagina_uscita_id` nella campagna | S |
| Link obbligatorio | Se si cancella, Brevo lo rimette all'invio [CF 8.2, CA 3][15] | Il piede con "Esci dalla newsletter" è fisso nel guscio | uguale nel risultato; nell'editor a blocchi il piede diventa modificabile e il link si rimette se manca | S |
| One-click (List-Unsubscribe) | Non citato nelle fonti | Presente | Tenere | - |
| Form di disiscrizione | Da incorporare o ospitato [CF 8.3] | Assente | Tipo di form "Disiscrizione" | S |
| Centro preferenze | Form "Aggiornamento del profilo": attributi, iscrizione a più liste, gruppi di consenso, "esci da tutti i gruppi" che non è la disiscrizione globale; link unico per contatto; link "Aggiorna le tue preferenze o disiscriviti" [CF 8.4] | Assente | Tipo di form "Aggiornamento del profilo", link firmato come quello di uscita (`newsletter_token_uscita`), segnaposto nuovo nelle email | M |
| Versione web | Link "vedi nel browser" nell'intestazione predefinita [CA 3][15] | Assente | Pagina `/newsletter-web/?c=&i=&t=` firmata | S |

### 1.10 Campagne: elenco, stati, azioni

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Percorso | Marketing > Campagne > Email [CA 1][1] | Newsletter e form > Campagne | Nome "Campagne email" | S |
| Stati | Bozza, Programmata, In coda/in invio, Inviata, Sospesa, Annullata, Archiviata [CA 1][5][6] | bozza, programmata, in_invio, inviata, sospesa, errore | Aggiungere Annullata e Archiviata; "errore" resta come avviso (non c'è in Brevo) | S |
| Viste dell'elenco | Tendina con Inviate, Sospese, Annullate (Bozze e Programmate non confermate) [CA 1][5] | Un elenco unico | Tendina delle viste | S |
| Report | Link "Report" accanto alla campagna [CA 1][25] | Le statistiche sono in un'altra pagina | Link "Report" per riga | S |
| Duplica | Da qualsiasi stato; si sceglie cosa copiare [CA 1][5] | Duplica tutto | Finestra con le parti da copiare | S |
| Invia ai nuovi contatti | Stesse impostazioni, solo a chi non l'ha ricevuta [CA 1][7] | Assente | Campagna figlia (`padre_id`) che salta chi è in `newsletter_coda` della madre | S |
| Rimanda ai non aperti | Solo Inviate con non aperti, una volta sola, data e ora, non per i lotti [CA 1][7][30] | Assente | Pagina "Rimanda la campagna" | M |
| Riprendi, Sospendi | Ci sono [CA 1][5] | Ci sono | uguale | - |
| Archivia | Solo Inviate, Sospese, Annullate [CA 1][5] | Assente | Colonna `archiviata` | S |
| Elimina | Solo in bozza (fonte non ufficiale) [CA 1][28][31] | Elimina | Stessa regola | S |

### 1.11 Creare una campagna: la pagina di riepilogo

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Passo 1 | Crea campagna > Normale o Test A/B > nome della campagna (interno) > Crea campagna [CA 2][1] | "Nuova" apre subito l'editor | Finestra con tipo e nome; colonna `nome` | S |
| Pagina di riepilogo | Sezioni in ordine: Mittente, Destinatari, Oggetto, Design, poi Impostazioni aggiuntive; la pagina dice cosa manca [CA 2][1][2] | Una pagina con tutti i campi | `newsletter-campagna.php` diventa a sezioni chiudibili, ognuna con "Salva" e il segno di completata | M |
| Mittente | Precompilato col mittente predefinito; scelta fra quelli verificati; nome personalizzabile con attributi [CA 2][1][10] | Tendina dei mittenti confermati (2.1.0) | uguale nella scelta; nome con attributi in più | S |
| Destinatari | "Invia a" liste o segmenti; "Non mandare ai contatti non coinvolti"; escludi liste o segmenti (vince l'esclusione); doppioni tolti [CA 2][1][2] | Solo liste, doppioni tolti | Segmenti, esclusioni, interruttore dei non coinvolti (criterio nelle impostazioni dei contatti) | M |
| Oggetto | Oggetto, testo di anteprima facoltativo, con attributi [CA 2][1] | Oggetto, anteprima, `{nome}` | Attributi `{{ contact.X }}` | S |
| Design | Modelli pronti, i tuoi modelli o campagne precedenti, da zero con editor a blocchi, editor semplice, codice HTML [CA 2][1][11][12] | Solo Quill | Finestra "Scegli il design" con queste cinque strade | L |
| Impostazioni aggiuntive | Rispondi a, Personalizza il campo "A", allegato, immagini incorporate, link della versione web, UTM di Google Analytics, tag, pagina di disiscrizione, invio a lotti (massimo 10), lingue [CA 2][4][8][9][13] | Nessuna (il "Rispondi a" sta nel mittente) | Rispondi a, campo "A", UTM, tag, pagina di disiscrizione, lotti subito; allegato e immagini incorporate con il limite dei 4 MB di posta; lingue fuori (sito monolingua) | M |
| Link di disiscrizione obbligatorio | Rimesso all'invio se manca [CA 2][15][16] | Sempre nel piede fisso | Controllo all'invio sul design | S |

### 1.12 Editor delle email

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Schermata | Pannello a sinistra con Contenuti e Stile (e Salvati); tela al centro; barra in alto con computer/telefono, Anteprima e prova, Altre azioni [CA 3][19][20] | Quill in una casella | Editor nuovo `newsletter-design.php` che riusa l'impianto dell'editor delle pagine (pannello, controlli, SortableJS) ma scrive HTML a tabelle per la posta | L |
| Sezioni e blocchi | Sezioni con una o più colonne; blocchi dentro le colonne; sposta, nascondi, duplica, salva, elimina; sezioni già disegnate [CA 3][22][23] | Assente | Albero JSON come quello delle pagine (`design` nella campagna) | L |
| Blocchi | Titolo, Testo, Immagine (alt, larghezza 400-650, allineamento, link), Pulsante (larghezza, colore, angoli, bordi), Divisore, Social, HTML, Video [CA 3][19][21][22][47] | Testo più un pulsante | Questi otto blocchi; Prodotto fuori (e-commerce); Video come immagine con link | L |
| Stile globale | Larghezza del corpo (600 px), colore del corpo, stili dei testi, link, pulsanti, sfondo [CA 3][48] | Guscio a colori fissi (`newsletter_email()`) | Scheda Stile con questi gruppi; i colori globali del sito come scorciatoia | M |
| Sezioni salvate | "Salva per riusare", Contenuti > Salvati, modifica in tutte le email o solo qui [CA 3][50][51] | Assente | Tabella `newsletter_sezioni_salvate` | M |
| Editor semplice | Rich Text Editor per email di testo formattato [CA 3][58] | Quill (è già questo) | Il Quill di oggi diventa "Editor semplice" | uguale |
| Codice HTML | Si incolla o si carica [CA 3][11] | Assente | Casella HTML, ripulita da `sicurezza-html.php`, inviata in base64 (ModSecurity) | S |
| Testo semplice | Generato da solo, modificabile dal menu della sezione Design [CA 3][59] | Generata da sola dall'HTML (`posta.php`, multipart/alternative), non modificabile | Renderla visibile e modificabile (`testo`, `testo_modificato`) | S |
| Personalizzazione | `{{ contact.FIRSTNAME }}`, filtri (`upper`, valore predefinito), `if` [CA 3][10][52] | `{nome}` | Segnaposto `{{ contact.X }}` e i filtri `upper`, `lower`, `default`; `{nome}` resta valido per le campagne vecchie. `if`: dopo, se serve (sintassi non trovata) | M |
| Anteprima telefono | Interruttore in alto [CA 3][14] | Assente | Tela a 390 px | S |

### 1.13 Modelli

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Dove | Marketing > Modelli > Crea modello > Modello email [CA 4][12] | Assente | Pagina `newsletter-modelli.php`, tabella `newsletter_modelli` | M |
| Stato | Nasce inattivo finché non lo si attiva; si rinomina dall'elenco [CA 4][12] | Assente | Colonna `attivo` | S |
| Uso | Nella campagna porta solo il design [CA 4][1][12] | Assente | Copia del design nella campagna | S |
| Galleria | Modelli pronti e per intento [CA 4][12][60] | Assente | Sei-dieci modelli fatti in casa nello zip (`includes/newsletter-modelli/*.json`), nessuno copiato da Brevo | M |
| Salva come modello | Dall'editor, Altre azioni [CA 4][11] | Assente | Voce nel menu dell'editor | S |

### 1.14 Test A/B

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Cosa | Oggetto oppure contenuto [CA 5][63] | Assente | Tabella `newsletter_ab` | M |
| Gruppi | Cursore, ogni gruppo al massimo 50%, una versione a persona [CA 5][63] | Assente | Cursore e scelta casuale nella coda (colonna `versione`) | S |
| Vincitore | Tasso di apertura o di clic; durata in ore (max 23) o giorni (max 7); poi parte da sola al resto [CA 5][63][64] | Assente | Passo del cron che sceglie e accoda il resto | M |
| Invio | Solo Invia subito o Programma [CA 2][40] | - | Stessa regola | S |
| Risultati | Aperture e clic per versione; vincitore, pari, non ancora [CA 5][64][65] | - | Riquadro nel report | S |

### 1.15 Prova, invio, programmazione

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Anteprima e prova | Tre schede: Anteprima, Vedi nella casella (servizio esterno), Manda una prova a destinatari scelti fra i contatti, personalizzata con i loro dati [CA 2][14] | Prova a un indirizzo, non personalizzata (`id` 0) | Anteprima computer e telefono; prova ai contatti della lista dei test con i loro attributi. "Vedi nella casella" richiede un servizio esterno: fuori | M |
| Invia subito | Pulsante Programma > Invia subito [CA 2][40] | "Invia adesso" | Stesso percorso | S |
| Programma | Data e ora nel fuso dell'account [CA 2][13][40][42] | Data e ora (`invio_at`), cron ogni 5 minuti | uguale; dire nella pagina che parte entro 5 minuti | uguale |
| All'orario migliore | Calcolato per contatto (Aura, piani a pagamento) [CA 2][13][41] | Assente | Possibile con le aperture passate di ogni contatto; facoltativo, decisione per Danilo | M |
| Lotti | Fino a 10 lotti a intervalli [CA 2][13] | Coda con 40 email per giro e limiti ora e giorno | Colonne `lotti`, `lotti_intervallo`; il ritmo della coda resta sopra | S |

### 1.16 Statistiche

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Report della campagna | Dettagli in alto, Condividi, Esporta il report; schede Panoramica, Aperture, Clic [CA 6][65] | Una riga per campagna con i link cliccati | Pagina `newsletter-report.php?id=` a schede | M |
| Numeri | Inviate, destinatari, consegnate, aperture uniche (con e senza Apple MPP), tasso di apertura = aperture / consegnate, clic unici e totali, tasso di clic, clic su aperture, disiscrizioni, hard e soft bounce, spam [CA 6][25][67][69] | Inviate, aperte, clic, rimbalzi, uscite, spam; tassi su inviate | Tassi su consegnate (inviate meno rimbalzi); clic totali (oggi solo unici: va cambiato `newsletter_evento`, che non salva i ripetuti); clic su aperture. Apple MPP: riconoscerle è incerto senza dati del servizio, da segnalare | M |
| Mappa dei clic | Per ogni link: totali, unici, percentuale; anteprima con i link colorati [CA 6][67] | Elenco dei link con i clic | Anteprima colorata | M |
| Chi ha fatto cosa | Esportazione per tipo: aperti, cliccati, non aperti, rimbalzi, disiscritti, spam [CA 6][72] | I dati ci sono, non si vedono | Elenchi nelle schede ed esportazione CSV | S |
| Esporta il report | CSV mandato per email [CA 6][65] | Assente | CSV scaricato dal pannello (l'email resta facoltativa) | S |
| Statistiche di un periodo | Marketing > Statistiche, numeri di un periodo [CA 6][25] | Medie di tutte le campagne, iscritti per mese | Scelta del periodo | S |
| Statistiche per contatto e per lista | Nella scheda del contatto e nella pagina della lista [CF 1.5, 3.5] | Assente | Fasi 1 e 7 | M |

### 1.17 Automazioni

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Nome e percorso | Automazioni > Crea un'automazione (da zero o da modello) [CA 7][74][81] | "Flussi automatici" | Nome "Automazioni" | S |
| Tela | Tela al centro; pannello con Inneschi, Azioni, Regole; **+** sotto ogni passo; barra con nome (predefinito "Automazione n. X"), tag, elimina, stato [CA 7][74][77] | Tela a grafo con innesco, Email, Attesa, Bivio, Fine | uguale nella forma; cambiano le schede del pannello e la barra | M |
| Più inneschi | Entra se ne soddisfa almeno uno [CA 7][77] | Un innesco | Colonna `inneschi` (JSON), con lettura del vecchio `innesco` | M |
| Inneschi | Aggiunto a una lista (non quando passa da una lista a un'altra), tolto da una lista, disiscritto, anniversario di una data, email aperta, link cliccato, aggiunta a mano [CA 7][77][78][83][85] | Iscrizione, lista, form, apertura, clic, inattivo | Aggiungere tolto da lista, disiscritto, anniversario (attributo data), aggiunta a mano. Form e inattivo restano (Brevo ha l'invio di un form tra le condizioni) | M |
| Azioni | Manda un'email (mittente, oggetto e anteprima, design, orario, tag), aggiungi a lista, togli da lista, aggiorna un attributo, chiama un webhook [CA 7][9][83][86][87] | Solo Email | Le altre quattro azioni; il webhook con la stessa protezione SSRF dell'import da WordPress | M |
| Regole | Attesa (minimo 1 minuto); Attendi un evento con un massimo (sennò ramo No); Divisione condizionale a più rami con ramo predefinito; Divisione in percentuale [CA 7][78][86][88] | Attesa; Bivio sì/no con 9 condizioni; l'email ha le uscite "se apre, se clicca, se non fa niente entro" | Attendi un evento come regola a sé; bivio a più rami con le condizioni dei segmenti; divisione in percentuale | M |
| Impostazioni | Rientro dopo l'uscita, attesa prima di rientrare; condizioni di uscita [CA 7][79] | Nessun rientro (`UNIQUE flusso_iscritto` in `newsletter_corsi`) | Colonne `rientro`, `rientro_attesa`, `uscita`; la chiave unica va tolta | M |
| Stati | Attiva; In pausa (non entrano, chi è dentro prosegue); Inattiva (chi è dentro viene tolto) [CA 7][74][92] | Attivo / spento; spento = non entrano, chi è dentro prosegue (`newsletter_flussi_giro` non guarda `attivo`) | Lo spento di oggi è "In pausa"; aggiungere "Inattiva" che toglie i cammini | S |
| Modelli | Benvenuto (con il doppio opt-in conta dopo la conferma), anniversario, attività di marketing [CA 7][81][84][90] | Assente | Tre modelli in casa | S |
| Statistiche | Entrati, finiti, in corso, usciti, sospesi; per ogni email aperture, clic, uscite [CA 7][93] | Inviate, in corso, finiti | Tutti i numeri, per passo | S |
| Attività e registro | Chi è dentro, filtrabile per passo; togliere a mano [CA 7][76] | Assente | Scheda "Attività" e tabella `newsletter_flussi_registro` | M |
| Prova | Non trovato nelle fonti | Assente | Non serve per essere identici | - |

### 1.18 Impostazioni di invio

| Voce | Brevo fa così | plumeCMS oggi | Cosa cambiare | Peso |
|---|---|---|---|---|
| Mittenti | Nome e indirizzo; con dominio non autenticato un **codice di 6 cifre** da incollare, "Verifica il mittente"; con dominio autenticato niente verifica; niente Gmail o Yahoo per le campagne [CA 8][96][98][99] | `posta_mittenti` con conferma per link (`token`) | Codice di 6 cifre al posto del link (o insieme); niente verifica se il dominio è autenticato | S |
| Mittente predefinito | Precompila la campagna [CA 8][1] | `predefinito` | uguale | - |
| Domini | Record Brevo code, DKIM, DMARC con `rua`; "Autentica questo dominio" [CA 8][97][100] | Controllo DNS di SPF, DKIM, DMARC, MX | uguale nella sostanza (il servizio è il proprio: niente "Brevo code"). Elenco dei domini con lo stato "Autenticato" | S |
| Rispondi a | Predefinito il mittente, cambiabile per campagna [CA 8][4][35] | Per mittente | Anche per campagna | S |
| Piede con indirizzo | Indirizzo dell'azienda nel piede predefinito [CA 8][53] | Il piede non ha indirizzo | Campo "Indirizzo dell'azienda" nelle impostazioni, nel piede di serie | S |
| IP dedicato e riscaldamento | Riscaldamento dell'IP dedicato [CA 8][108][109] | Riscaldamento del limite giornaliero (2.1.0) | uguale nello spirito; resta in Consegna delle email | - |
| Impostazioni nascoste | - | `newsletter_nome`, `newsletter_intestazione`, `newsletter_firma`, `newsletter_pagina`, `newsletter_descrizione`, `email_firma` senza campo nel pannello | Campi in Impostazioni della newsletter, o assorbiti dallo Stile dell'editor | S |

---

## 2. Il menu di arrivo

Oggi (`admin/includes/admin-header.php`, righe 27-34): Campagne, Iscritti e liste, Form (solo amministratore), Invii dei
form, Flussi automatici, Statistiche. Consegna delle email e Configurazione email stanno in Impostazioni; i Popup sono
una voce a sé del menu principale.

Di arrivo, nello stesso ordine in cui Brevo divide queste aree (Contatti; Marketing: Campagne, Modelli, Statistiche;
Automazioni; Form; Impostazioni) [CF 1.2, 2.2, 3.7, 7.2, 8.2, 9.4; CA 1, 4, 6, 7, 8]:

```
Newsletter e form
  Contatti                         newsletter-contatti.php        (Brevo: CRM > Contacts)
    Contatti                       scheda: tabella, viste, filtri, Importa contatti, Crea un contatto
    Liste                          scheda: cartelle e liste       (Brevo: Contacts > Lists)
    Segmenti                       scheda                         (Brevo: Contacts > Segments)
  Campagne email                   newsletter.php                 (Brevo: Marketing > Campaigns > Email)
  Modelli                          newsletter-modelli.php         (Brevo: Marketing > Templates)
  Statistiche                      newsletter-statistiche.php     (Brevo: Marketing > Statistics)
  Automazioni                      newsletter-flussi.php          (Brevo: Automations; scheda Registro)
  Form                             form.php                       (Brevo: Forms)
    Iscrizione                     schede per tipo: Sign-up, Pop-up, Profile update, Unsubscribe
    Pop-up
    Aggiornamento del profilo
    Disiscrizione
    Altri form                     i form generici del sito (contatti, lavora con noi): non c'è in Brevo, resta
  Invii dei form                   form-submissions.php           (non c'è in Brevo: resta per i form generici)
  Impostazioni                     newsletter-impostazioni.php    (Brevo: Settings)
    Attributi dei contatti         (Settings > Contacts > Contact attributes)
    Gruppi di consenso             (Settings > Contacts > Consent groups)
    Lista dei test                 (Settings > Campaigns > Test list)
    Pagine di disiscrizione        (Settings > Campaigns > Unsubscribe pages)
    Mittenti e domini              rimando a Impostazioni > Consegna delle email (Settings > Senders, Domains)
    Generali                       nome della newsletter, intestazione, firma, indirizzo dell'azienda, contatti non coinvolti
```

Note:
- "Invii dei form" e "Altri form" sono le sole voci senza un corrispondente in Brevo: servono ai form del sito che non
  iscrivono a nessuna lista. Toglierle romperebbe i siti esistenti.
- I Popup del sito (voce del menu principale) restano dove sono; il form "Pop-up" li usa per comparire.
- Mittenti e domini restano in Consegna delle email perché servono a tutta la posta del CMS (avvisi, password): in
  Brevo stanno anch'essi nelle Impostazioni generali dell'account, non sotto Campagne.
- Ruoli: oggi Statistiche e Form sono solo amministratore. Restano tali; il redattore non vede la newsletter (regola di
  progetto: gestisce solo contenuti, media, categorie e tag).

---

## 3. Il modello dei dati di arrivo

Principio: **nessuna tabella si rinomina e nessuna colonna si toglie.** Si aggiungono tabelle e colonne, con le
funzioni `*_schema()` e l'impronta, come oggi (`newsletter_schema()`, `newsletter_flussi_schema()`, `form_schema()`).
Motivi: il pacchetto di aggiornamento lo applica il motore vecchio; altri file (`api/modulo.php`,
`includes/scaricabili.php`, `includes/consegna.php`, i moduli dei siti) leggono già `newsletter_iscritti.stato`; opcache
può servire un file vecchio accanto a uno nuovo per qualche secondo.

Tutte le tabelle: `ENGINE=InnoDB DEFAULT CHARSET=utf8` (3 byte, come le altre), colonne `NOT NULL DEFAULT` esplicite per
la modalità stretta, niente colonne generate (trappola dei dump, errore 3105).

### 3.1 Contatti (tabella esistente `newsletter_iscritti`)

Colonne nuove:

| Colonna | Tipo | A cosa serve |
|---|---|---|
| `ext_id` | VARCHAR(190) NULL, UNIQUE | EXT_ID di Brevo |
| `blocco_motivo` | VARCHAR(24) NOT NULL DEFAULT '' | `unsubscribedViaEmail`, `unsubscribedViaMA`, `unsubscribedViaApi`, `adminBlocked`, `hardBounce`, `softBounce`, `contactFlaggedAsSpam`, `import` |
| `blocco_at` | DATETIME NULL | data della lista nera |
| `opt_in` | TINYINT(1) NOT NULL DEFAULT 0 | attributo OPT_IN |
| `doi_at` | DATETIME NULL | DOUBLE_OPT-IN (sì se valorizzata) |
| `tracciamento` | TINYINT(1) NOT NULL DEFAULT 1 | consenso al tracciamento di aperture e clic |
| `pref_token` | CHAR(64) NULL | facoltativa: si può ricavare con HMAC come il link di uscita, senza colonna |

Lo stato: `stato` resta e prende un valore in più, `bloccato` (a mano, da importazione, da automazione). "In lista nera
email" = `stato IN ('uscito','rimbalzato','spam','bloccato')`. "Iscritto" = `attivo`. `in_attesa` resta (doppio opt-in
non confermato). Una sola funzione cambia lo stato (`contatto_blocca($id, $motivo)`, `contatto_sblocca($id)`), così
stato, motivo, data, coda e storia restano coerenti.

Migrazione (una volta, nella funzione di schema): `uscito` -> motivo `unsubscribedViaEmail`, `blocco_at = uscito_at`;
`rimbalzato` -> `hardBounce`, `blocco_at = rimbalzato_at`; `spam` -> `contactFlaggedAsSpam`, `blocco_at = uscito_at`;
`attivo` con `consenso_versione` di conferma -> `doi_at = consenso_at`. Tre `UPDATE`, nessun dato spostato.

### 3.2 Attributi

`newsletter_attributi`
- `id`, `nome` VARCHAR(50) UNIQUE (maiuscolo, `[A-Z0-9_]`), `etichetta` VARCHAR(100), `categoria` VARCHAR(12)
  (`normal`, `category`, poi eventualmente `calculated`, `global`), `tipo` VARCHAR(16) (`text`, `float`, `date`,
  `boolean`, `category`, `multiple-choice`), `sistema` TINYINT(1), `colonna` VARCHAR(30) (per quelli di sistema:
  `email`, `nome`, `cognome`, `ext_id`, `creato`, `aggiornato`, `opt_in`, `doi_at`), `ordine` INT, `in_scheda`
  TINYINT(1), `creato` DATETIME.
- Righe di partenza: EMAIL, NOME (FIRSTNAME), COGNOME (LASTNAME), EXT_ID, OPT_IN, DOUBLE_OPT-IN, CREATION_DATE,
  LAST_CHANGED, tutti `sistema = 1`.

`newsletter_attributi_opzioni`: `id`, `attributo_id`, `valore` INT (il numero della categoria di Brevo), `etichetta`
VARCHAR(200), `ordine`.

`newsletter_valori` (valori degli attributi personalizzati, una riga per valore):
- `contatto_id`, `attributo_id`, `opzione` INT NOT NULL DEFAULT 0 (per la scelta multipla una riga per opzione),
  `v_testo` VARCHAR(500) NOT NULL DEFAULT '', `v_num` DECIMAL(20,6) NULL, `v_data` DATE NULL.
- Chiave primaria (`contatto_id`, `attributo_id`, `opzione`); indici (`attributo_id`, `v_testo`(100)),
  (`attributo_id`, `v_num`), (`attributo_id`, `v_data`), (`attributo_id`, `opzione`).
- Perché non una colonna JSON: i segmenti devono filtrare per valore con indici, e gli indici su JSON vogliono colonne
  generate (trappola 3105 nei dump). Perché non una colonna per attributo: 200 `ALTER TABLE` su hosting condiviso.

### 3.3 Liste, cartelle, uscite per lista, lista dei test

- `newsletter_cartelle`: `id`, `nome` VARCHAR(100) UNIQUE, `creato`. Migrazione: una cartella "Le tue liste" e tutte le
  liste esistenti dentro.
- `newsletter_liste`: colonna nuova `cartella_id` INT NOT NULL DEFAULT 0. La lista principale e le liste di sistema
  (`newsletter_lista_di_sistema()`) restano com'erano.
- `newsletter_liste_uscite`: `contatto_id`, `lista_id`, `quando`; chiave (`contatto_id`, `lista_id`). Chi è qui non
  riceve le campagne di quella lista anche se c'è ancora l'appartenenza.
- Lista dei test: impostazione `newsletter_lista_test` (JSON di fino a 20 id di contatti). Niente tabella.

### 3.4 Segmenti e viste

- `newsletter_segmenti`: `id`, `nome` VARCHAR(100), `cartella_id` INT, `condizioni` MEDIUMTEXT (JSON: gruppi con `e`/`o`,
  condizioni `{campo, operatore, valore, periodo}`), `conteggio` INT, `contato_at` DATETIME, `creato`, `aggiornato`.
- `newsletter_segmenti_cartelle`: `id`, `nome`.
- `newsletter_viste`: `id`, `utente_id`, `nome`, `filtro` TEXT (JSON, o `segmento_id`/`lista_id`), `colonne` TEXT,
  `ordine` VARCHAR(40), `creato`.
- Il segmento non salva i membri: il motore lo traduce in SQL ogni volta (come Brevo, "si aggiorna da solo"). Il
  conteggio si salva per l'elenco.

### 3.5 Importazioni, esportazioni, storia, consensi

- `newsletter_importazioni`: `id`, `utente_id`, `file` (in una cartella non pubblica), `origine` (`file`, `incolla`),
  `mappatura` TEXT, `opzioni` TEXT, `liste` VARCHAR(255), `stato` (`pronta`, `in_corso`, `finita`, `errore`),
  `righe`, `fatte`, `nuovi`, `aggiornati`, `invariati`, `errori`, `scarti_file`, `creato`, `finito`.
- `newsletter_esportazioni`: `id`, `utente_id`, `filtro` TEXT, `attributi` TEXT, `separatore` CHAR(1), `stato`, `file`,
  `righe`, `creato`, `scade`. I file stanno fuori dalla radice pubblica (come i file riservati) e si scaricano solo dal
  pannello.
- `newsletter_storia`: `id`, `contatto_id`, `tipo` VARCHAR(20) (`creato`, `lista_aggiunto`, `lista_tolto`, `form`,
  `attributo`, `bloccato`, `sbloccato`, `importato`, `preferenze`), `rif_id` INT, `dettaglio` VARCHAR(255),
  `utente_id`, `creato`. Indice (`contatto_id`, `creato`). Invii, aperture e clic non si duplicano qui: la Cronologia li
  legge da `newsletter_coda` e `newsletter_eventi`.
- `newsletter_consensi`: `id`, `contatto_id`, `tipo` (`opt_in`, `doi`, `import`, `preferenze`, `gruppo`), `testo`
  TEXT, `form_id`, `ip`, `creato`. Migrazione: una riga per ogni contatto con `consenso_at`, dal `consenso_versione` e
  dall'`ip` di oggi.
- `newsletter_gruppi_consenso`: `id`, `nome` VARCHAR(255) UNIQUE, `descrizione` VARCHAR(500), `ingresso`
  (`automatic`, `manual`), `creato`. `newsletter_contatti_gruppi`: `contatto_id`, `gruppo_id`, `iscritto` TINYINT,
  `aggiornato`. Impostazione `newsletter_gruppi_attivi` (spenta).

### 3.6 Campagne, coda, A/B, modelli

`newsletter_campagne`, colonne nuove:

| Colonna | Tipo | Migrazione delle campagne di oggi |
|---|---|---|
| `nome` | VARCHAR(150) | = `oggetto` |
| `tipo` | VARCHAR(8) DEFAULT 'normale' (`normale`, `ab`) | `normale` |
| `editor` | VARCHAR(8) DEFAULT 'semplice' (`semplice`, `blocchi`, `html`) | `semplice`: corpo Quill e pulsante continuano a uscire come oggi |
| `design` | MEDIUMTEXT NULL (JSON dei blocchi) | NULL |
| `html` | MEDIUMTEXT NULL (HTML del design o incollato) | NULL |
| `testo` | MEDIUMTEXT NULL, `testo_modificato` TINYINT | NULL, 0 |
| `segmenti`, `escludi_liste`, `escludi_segmenti` | VARCHAR(255) DEFAULT '' | vuoti |
| `non_coinvolti` | TINYINT(1) DEFAULT 0 | 0 |
| `rispondi_a`, `campo_a`, `tag` | VARCHAR(190) DEFAULT '' | vuoti |
| `utm`, `utm_campagna` | TINYINT, VARCHAR(100) | 0, '' |
| `pagina_uscita_id`, `profilo_form_id` | INT DEFAULT 0 | 0 (predefinite) |
| `lotti`, `lotti_intervallo` | TINYINT DEFAULT 1, INT DEFAULT 0 (minuti) | 1, 0 |
| `archiviata` | TINYINT(1) DEFAULT 0 | 0 |
| `padre_id`, `rinvio_di` | INT DEFAULT 0 | 0 |
| `rinvio_fatto` | TINYINT(1) DEFAULT 0 | 0 |
| `modello_id` | INT DEFAULT 0 | 0 |

Stati nuovi: `annullata`. `liste`, `mittente_id`, `invio_at`, `pulsante_*` restano.

`newsletter_coda`, colonne nuove: `versione` CHAR(1) DEFAULT 'a', `lotto` TINYINT DEFAULT 1, `riprova_at` DATETIME NULL
(soft bounce: nuovo tentativo entro 36 ore). La chiave unica (`campagna_id`, `iscritto_id`) resta: una sola copia a
persona, anche nell'A/B.

`newsletter_ab`: `campagna_id` PK, `cosa` (`oggetto`, `design`), `oggetto_b`, `anteprima_b`, `design_b` MEDIUMTEXT,
`html_b` MEDIUMTEXT, `percentuale` TINYINT (1-50, per gruppo), `criterio` (`aperture`, `clic`), `durata` INT, `unita`
(`ore`, `giorni`), `fase` (`prova`, `attesa`, `resto`, `finita`), `vincitore` (`a`, `b`, `pari`, ''), `scelto_at`.

`newsletter_modelli`: `id`, `nome`, `editor`, `design`, `html`, `testo`, `mittente_id`, `campo_a`, `attivo` TINYINT
DEFAULT 0, `tag` VARCHAR(50) (`optin` per i modelli di doppio opt-in), `creato`, `aggiornato`. Migrazione: l'email di
conferma di oggi (impostazione delle parti, `includes/newsletter-email.php`) resta il modello predefinito di doppio
opt-in, letto da lì; i form con `conferma_email` propria tengono la loro.

`newsletter_sezioni_salvate`: `id`, `nome`, `json` MEDIUMTEXT, `sincronizzata` TINYINT, `aggiornato`.

### 3.7 Automazioni

`newsletter_flussi`, colonne nuove: `stato` VARCHAR(10) (`attiva`, `in_pausa`, `inattiva`; migrazione: `attivo = 1` ->
`attiva`, `attivo = 0` -> `in_pausa`, che è il comportamento di oggi), `inneschi` TEXT (JSON; migrazione: il solo
`innesco` di oggi), `rientro` TINYINT DEFAULT 0, `rientro_attesa` INT DEFAULT 0 (ore), `uscita` TEXT (condizioni), `tag`.
La colonna `attivo` resta e si scrive insieme a `stato` (`attivo = stato = 'attiva'`), così il motore vecchio, se gira
per qualche secondo dopo l'aggiornamento, legge il valore giusto.

`newsletter_corsi`: per il rientro serve togliere la chiave unica `flusso_iscritto` e aggiungere `giro` INT DEFAULT 1 con
chiave unica (`flusso_id`, `iscritto_id`, `giro`). Stati nuovi del cammino: `tolto` (Inattiva, uscita, a mano).

`newsletter_flussi_registro`: `id`, `flusso_id`, `corso_id`, `iscritto_id`, `nodo`, `evento` (`entrato`, `email`,
`attesa`, `bivio`, `azione`, `uscito`, `finito`, `tolto`), `dettaglio`, `creato`. Pulizia oltre 90 giorni nel cron
giornaliero.

### 3.8 Form e pagine pubbliche

`moduli`, colonne nuove:

| Colonna | Tipo | Migrazione |
|---|---|---|
| `tipo` | VARCHAR(12) DEFAULT 'generico' (`iscrizione`, `popup`, `profilo`, `disiscrizione`, `generico`) | `lista_id > 0` -> `iscrizione`, altrimenti `generico` |
| `conferma` | VARCHAR(8) DEFAULT 'doppia' (`doppia`, `semplice`, `nessuna`) | `lista_conferma = 1` -> `doppia`, `0` -> `nessuna` (oggi entra senza email) |
| `liste` | VARCHAR(255) DEFAULT '' | vuoto; `lista_id` di oggi diventa la lista principale del form |
| `doi_modello_id`, `doi_url` | INT, VARCHAR(500) | 0, '' (pagina di conferma di oggi) |
| `aggiorna_esistenti` | TINYINT DEFAULT 0 | 0 (come oggi) |
| `gdpr` | TINYINT DEFAULT 0 | 1 se `consenso` non è vuoto |
| `messaggi` | TEXT (JSON: riuscita, errore, obbligatorio, formato) | dai testi di grazie di oggi |
| `dopo_invio` | VARCHAR(8) DEFAULT 'pagina' (`messaggio`, `pagina`), `dopo_url` VARCHAR(500) | `pagina` con `/grazie/?f=` |

Nei campi (JSON `campi`) ogni campo prende la chiave `attributo` (nome dell'attributo, vuota nei form generici).
Migrazione: i campi di tipo email, e quelli che oggi riempiono nome e cognome, puntano a EMAIL, NOME, COGNOME.

`newsletter_pagine_uscita`: `id`, `nome` UNIQUE, `titolo`, `testo`, `messaggio_conferma`, `questionario` TINYINT DEFAULT
1, `motivi` TEXT (JSON), `predefinita` TINYINT, `creato`. La predefinita nasce con i testi della pagina di oggi.

`newsletter_uscite_motivi`: `id`, `contatto_id`, `campagna_id`, `flusso_id`, `motivo` VARCHAR(100), `altro`
VARCHAR(500), `creato`.

Nessun dato va perso: iscritti, liste, appartenenze, coda, eventi, campagne, flussi, cammini, form e invii restano nelle
loro tabelle; le colonne nuove hanno valori predefiniti che riproducono il comportamento di oggi.

---

## 4. Piano in fasi

Ogni fase è una versione che si consegna da sola e serve subito. A ogni fase valgono le regole di progetto: CHANGELOG,
VERSIONE, manuale tecnico, manuale d'uso con capitoli e schermate rifatti, PDF, sito di plumeCMS (Novità, blocco in
Funzionalità, schermata, `prova-sito-plumecms.js`), regole di interfaccia con la loro checklist, prova d'installazione in
radice e in cartella, aggiornamento dalla versione prima con le foto del sito uguali, attacchi, `prova-ux-pannello.js`,
pannello al telefono. Qui sotto solo quello che è proprio di ogni fase.

### Fase 1, 2.8.0: la base dei contatti (L)

Entra: modello dei dati 3.1, 3.2, 3.3 (cartelle, uscite per lista), `newsletter_storia`, `newsletter_consensi`; pagina
Contatti con le cinque colonne fisse, Personalizza le colonne, paginazione da 50, ricerca, "Carica una lista", selezione
e le sei azioni di gruppo; Crea un contatto; scheda del contatto a quattro schede (Panoramica con i numeri dei 90 giorni,
Cronologia, Liste, Preferenze); attributi (Impostazioni > Attributi dei contatti, sei tipi, regole di Brevo); liste
dentro le cartelle, Sposta; lista nera con motivo, mettere e togliere come Brevo; regola dei 5 soft bounce di fila; menu
nuovo (le voci delle fasi future non compaiono finché non ci sono).

Pagine: `admin/newsletter-contatti.php` (nuova, con le schede Contatti e Liste), `admin/newsletter-contatto.php`
(nuova), `admin/newsletter-attributi.php` (nuova), `admin/newsletter.php` (perde le schede Iscritti e Liste, che
rimandano con 302 alle nuove), `admin/includes/admin-header.php`; motore `includes/newsletter-contatti.php` (nuovo),
`includes/newsletter.php` (schema e migrazione), `includes/consegna.php` (soft bounce).

Prove: `prove/prova-contatti.js` (crea, doppione che porta all'esistente, attributi dei sei tipi, valore del tipo
sbagliato ignorato, modifica in massa solo su data, sì/no, categoria, cartella che cancella le liste e non i contatti,
lista nera a mano e dalla scheda, riscrittura solo uno alla volta, cambio di email che toglie la lista nera); prova di
migrazione da un sito 2.7.0 con iscritti in tutti e cinque gli stati, liste, campagne inviate e flussi in corso (conteggi
uguali prima e dopo, campagne e flussi che continuano); attacchi sulle pagine nuove (CSRF, ruoli, XSS negli attributi e
nelle etichette, emoji nei valori).

Dipendenze: nessuna. Utile subito: si vede e si gestisce ogni contatto.

### Fase 2, 2.9.0: importazione, esportazione, lista dei test (M)

Entra: importazione in quattro passi con abbinamento delle colonne, "Crea un nuovo attributo", "Non importare", liste
nuove, opzioni (aggiorna esistenti, celle vuote, lista nera), promemoria del consenso, lavoro a giri, resoconto con gli
scarti; Copia e incolla; CSV, TXT, XLSX, Windows-1252; esportazione con attributi e separatore; lista dei test.

Pagine: `admin/newsletter-importa.php` (nuova), finestra Esporta nella pagina Contatti, Impostazioni > Lista dei test;
motore `includes/newsletter-importa.php` (nuovo, sostituisce `newsletter_importa_csv()`, che resta come involucro),
`includes/cron.php` (passo delle importazioni ed esportazioni).

Prove: `prove/prova-importa.js` con file veri (CSV con punto e virgola e virgola, Windows-1252, XLSX fatto con
LibreOffice e con Excel, 20000 righe a giri, doppioni, usciti saltati, celle vuote con e senza l'opzione, colonna nuova
come attributo data); esportazione confrontata con l'importazione (andata e ritorno uguali); attacchi: formule nel CSV
(`=CMD` deve uscire con l'apostrofo davanti), file XLSX con XML esterno, zip bomba, percorso del file.

Dipendenze: fase 1.

### Fase 3, 2.10.0: segmenti (L)

Entra: motore delle condizioni (attributi, liste, segmenti, nessuna lista, email aperta/non aperta/cliccata in un
periodo, link preciso, form inviato, lista nera e motivo), gruppi E/O, negazioni, date relative, fino a 100 condizioni;
pagina Segmenti con i passi di Brevo, categorie, modelli trovati; "Aggiungi filtro" e "Salva come segmento" nella pagina
Contatti; viste salvate; lista nuova da un segmento; esportazione per segmento; i bivi delle automazioni usano lo stesso
motore (le nove condizioni di oggi diventano condizioni del motore).

Pagine: `admin/newsletter-contatti.php` (scheda Segmenti, filtri, viste), `includes/newsletter-segmenti.php` (nuovo),
`includes/newsletter-flussi.php` (`newsletter_flusso_condizione()` chiama il motore).

Prove: `prove/prova-segmenti.js` con un sito di 5000 contatti finti ed eventi: ogni condizione confrontata con una query
scritta a mano; tempi sotto un secondo con gli indici; bivi dei flussi vecchi con lo stesso esito di prima. Attacchi:
condizioni manipolate nel JSON (nomi di colonna, operatori, valori) che non devono arrivare all'SQL.

Dipendenze: fase 1. Perché prima delle campagne: le campagne di Brevo scelgono segmenti ed esclusioni; e il motore
serve anche alle automazioni.

### Fase 4, 2.11.0: campagne a riepilogo (M)

Entra: Crea campagna (Normale, nome interno); pagina di riepilogo a sezioni Mittente, Destinatari (liste, segmenti,
esclusioni, non coinvolti), Oggetto, Design (per ora Editor semplice, cioè il Quill di oggi, e Codice HTML), Impostazioni
aggiuntive (Rispondi a, campo "A", UTM, tag, lotti); segnaposto `{{ contact.X }}` con i filtri; Anteprima e prova con la
lista dei test e i dati dei contatti; Programma > Invia subito / Programma; elenco con viste, Report, Duplica con scelta,
Invia ai nuovi contatti, Rimanda ai non aperti, Archivia, Annullata, Elimina solo in bozza; versione web; testo
semplice; indirizzo dell'azienda nel piede.

Pagine: `admin/newsletter.php` (elenco), `admin/newsletter-campagna.php` (riepilogo), `includes/newsletter.php`
(`newsletter_email()`, destinatari, lotti), `sito/index.php` (versione web).

Prove: `prove/prova-campagna-riepilogo.js` (sezioni incomplete che bloccano l'invio, esclusione che vince, doppioni,
segnaposto con attributi e valore predefinito, lotti con orari giusti, rinvio una volta sola, nuovi contatti); campagne
2.7.0 aperte e inviate dopo l'aggiornamento uguali al byte (stesso HTML a parità di dati).

Dipendenze: fasi 1 e 3 (segmenti), 2 per la lista dei test. Peso M perché l'editor a blocchi è nella fase dopo: chi
aggiorna ha subito la campagna come Brevo con l'editor semplice, che Brevo ha anch'esso.

### Fase 5, 2.12.0: editor a blocchi, modelli, sezioni salvate (L)

Entra: editor delle email con pannello Contenuti/Stile/Salvati, tela con computer e telefono, sezioni a colonne, otto
blocchi, Stile globale, sezioni salvate con "modifica in tutte le email", HTML a tabelle per la posta; Modelli (elenco,
attivo/inattivo, rinomina, salva come modello, galleria in casa, modello predefinito di doppio opt-in); scelta del design
con le cinque strade; piede con il link di disiscrizione rimesso se manca.

Pagine: `admin/newsletter-design.php` (nuova), `admin/assets/newsletter-design.js` e `.css` (nuovi, SortableJS già in
casa), `admin/newsletter-modelli.php` (nuova), `includes/newsletter-design.php` (da JSON a HTML per la posta),
`includes/newsletter-modelli/` (modelli pronti in JSON).

Prove: `prove/prova-editor-email.js` (ogni blocco e ogni controllo, trascinamento, anteprima telefono, salva come
modello e riusa, sezione sincronizzata cambiata ovunque); controllo dell'HTML uscito con un validatore per la posta (solo
tabelle e stili in linea, larghezza 600, immagini con alt); foto delle email in Chromium a 600 e 390 px; `prova-ux-editor.js`
allargata; attacchi: HTML e `javascript:` nei blocchi, CSS con `expression`, link in base64.

Dipendenze: fase 4.

### Fase 6, 2.13.0: test A/B (M)

Entra: Crea campagna > Test A/B; oggetto o contenuto; cursore dei gruppi fino a 50%; criterio aperture o clic; durata in
ore (max 23) o giorni (max 7); solo Invia subito o Programma; scelta del vincitore dal cron e invio del resto; risultati.

Pagine: `admin/newsletter-campagna.php` (sezione A/B), `includes/newsletter-ab.php` (nuovo), `includes/cron.php`.

Prove: `prove/prova-ab.js` con il tempo spostato (`settings-cli.php`): gruppi delle dimensioni giuste, nessuno riceve due
versioni, vincitore per aperture e per clic, pari, resto partito una volta sola anche con due cron sovrapposti.

Dipendenze: fasi 4 e 5 (per il contenuto B). Potrebbe stare dopo le statistiche: è piccola e chiude le campagne.

### Fase 7, 2.14.0: statistiche e report (M)

Entra: report della campagna a schede (Panoramica, Aperture, Clic, Disiscrizioni con i motivi, Rimbalzi), numeri e tassi
di Brevo (su consegnate), clic totali e unici (oggi si salvano solo gli unici: da qui si salvano anche i ripetuti),
mappa dei clic sull'anteprima, elenchi di chi ha aperto, cliccato, non aperto, rimbalzato, è uscito, esportazione CSV
del report e di ogni scheda; Statistiche di un periodo; statistiche della lista; soglie di Brevo.

Pagine: `admin/newsletter-report.php` (nuova), `admin/newsletter-statistiche.php`, `includes/newsletter-flussi.php`
(`newsletter_evento()` per i clic ripetuti, con una colonna `ripetuti` o righe in più).

Prove: `prove/prova-report.js` con eventi finti contati a mano; numeri delle campagne 2.7.0 uguali dopo l'aggiornamento
(i tassi cambiano solo per la base "consegnate", da dire nel CHANGELOG).

Dipendenze: fase 4. Le statistiche vengono prima delle automazioni perché le automazioni di Brevo mostrano per ogni
passo email le statistiche del messaggio: stesso motore del report.

### Fase 8, 2.15.0: automazioni (L)

Entra: nome Automazioni; barra con nome "Automazione n. X", tag, stato Attiva / In pausa / Inattiva; pannello Inneschi,
Azioni, Regole; più inneschi; inneschi nuovi (tolto da una lista, disiscritto, anniversario, aggiunta a mano); azioni
(aggiungi e togli da lista, aggiorna attributo, webhook); regole (attesa da 1 minuto, attendi un evento con massimo,
divisione condizionale a più rami con predefinito, divisione in percentuale); passo Email con mittente, oggetto,
anteprima, design dall'editor a blocchi o da un modello, orario; impostazioni (rientro, attesa, condizioni di uscita);
modelli (benvenuto, anniversario, attività di marketing); statistiche per passo; Attività e registro con "togli".

Pagine: `admin/newsletter-flussi.php` e il suo script della tela, `includes/newsletter-flussi.php`.

Prove: `prove/prova-automazioni.js` (ogni innesco, azione e regola, rientro con attesa, uscita in mezzo a un'attesa,
Inattiva che toglie, In pausa che lascia proseguire, flussi 2.7.0 in corso che arrivano in fondo dopo l'aggiornamento);
attacchi: webhook verso indirizzi interni (127.0.0.1, 169.254.169.254, rete locale), grafi con giri infiniti.

Dipendenze: fasi 1, 3, 5, 7.

### Fase 9, 2.16.0: form e pagine pubbliche (L)

Entra: costruttore dei form a cinque passi con "Avanti" (Impostazione, Aspetto e campi, Messaggi, Conferma, Condividi);
tipi Iscrizione, Pop-up, Aggiornamento del profilo, Disiscrizione, più Altri form; campi legati agli attributi; GDPR e
Dichiarazione GDPR con la casella vuota; Iscrizione a più liste con lista principale; conferma doppia, semplice,
nessuna; modello di doppio opt-in e pagina dopo il clic; pagina ospitata, codici iframe, HTML, HTML semplice, QR;
pagine di disiscrizione con il questionario e la scelta nella campagna; centro preferenze con link unico; gruppi di
consenso (spenti di partenza); consenso al tracciamento.

Pagine: `admin/form.php` (a passi), `admin/newsletter-impostazioni.php` (Pagine di disiscrizione, Gruppi di consenso),
`includes/form.php`, `api/modulo.php`, `sito/index.php` e `sito/viste.php` (pagina ospitata, disiscrizione,
preferenze), `includes/popup.php`.

Prove: `prove/prova-form-brevo.js` (ogni passo, ogni conferma, campo casella solo con attributo sì/no, più liste,
aggiorna esistenti, pagina ospitata, iframe su un'altra origine, HTML semplice senza JavaScript); form 2.7.0 che
continuano a funzionare uguali (iscrizione, file omaggio, avviso, email di conferma della 2.7.0); `prova-form-avviso.js`,
`prova-omaggio.js`, `prova-newsletter-difetti.js` rifatte; attacchi: iframe e clickjacking (l'iframe va permesso solo
sulla pagina ospitata, `frame-ancestors`), link di preferenze di un altro contatto, questionario con testo lungo e
emoji.

Dipendenze: fasi 1, 5 (modello DOI), 4 (scelta della pagina nella campagna).

**Perché un ordine diverso potrebbe convenire**: la pagina di disiscrizione con il questionario e il centro preferenze
sono piccoli (S e M) e toccano ogni email spedita. Se Danilo vuole il link "Aggiorna le tue preferenze o disiscriviti"
prima del costruttore dei form, si possono anticipare nella fase 4 (servono solo i dati della fase 1). Il resto
dell'ordine resta: ogni fase usa quelle prima (contatti e attributi -> segmenti -> campagne -> editor -> statistiche ->
automazioni -> form, che legano i campi agli attributi e scelgono i modelli).

Totale indicativo: quattro fasi L, cinque M.

---

## 5. Cose non trovate che servono per essere identici

Da cercare quando la sessione potrà aprire help.brevo.com e developers.brevo.com (e www.brevo.com per le note di
rilascio). Raggruppate per area.

**Contatti e attributi**
- Quali attributi si vedono nella scheda e come si scelgono ("Manage the attributes displayed on a contact details page").
- Se NOME e COGNOME (FIRSTNAME, LASTNAME) si possono cancellare.
- La schermata esatta di creazione di un attributo di categoria e di scelta multipla.
- Che cosa succede a un contatto con il doppio opt-in non ancora confermato: esiste già nella base o no.

**Segmenti**
- Elenco completo delle condizioni e forma dei gruppi E/O annidati.
- Elenco completo dei modelli di segmento; limite al numero di segmenti.
- Criterio dei "contatti non coinvolti" e dove si cambia nelle impostazioni dei Contatti.

**Importazione ed esportazione**
- Formato delle date accettato nel file; testo esatto della casella del consenso nell'importazione da file.
- Che cosa fa l'importazione con un contatto in lista nera quando l'aggiornamento è acceso.

**Lista nera**
- Codice del motivo per la lista nera dopo cinque soft bounce; pagina sui "contatti non raggiungibili".

**Form e consenso**
- Controlli dell'Aspetto del form (stili, opzioni di ogni campo) ed elenco dei messaggi del passo 3.
- Email di conferma finale dopo il doppio opt-in nei form fatti in Brevo; opzione "aggiorna i contatti esistenti" nei
  form normali.
- Regole di comparsa dei form pop-up.
- Registro del consenso (data, IP, testo) oltre a OPT_IN e DOUBLE_OPT-IN.

**Disiscrizione e preferenze**
- Passo 3 (impostazioni) della pagina e del form di disiscrizione; testo dei cinque motivi del questionario.
- Dove si modifica il piede predefinito; segnaposto dei link di disiscrizione, versione web e preferenze.

**Campagne ed editor**
- Colonne esatte dell'elenco delle campagne; viste Bozze e Programmate nella tendina.
- Come si rinomina una campagna; come si toglie la programmazione dall'interfaccia.
- Nomi esatti dei pulsanti di ogni sezione del riepilogo; testi delle finestre di conferma e degli errori.
- Elenco completo dei blocchi e impostazioni di Divisore, Social, Video, Spazio; spaziatura del pulsante.
- Sintassi esatta del filtro del valore predefinito e delle condizioni `if` del Brevo Template Language.
- Cartelle o etichette dei modelli.

**A/B e statistiche**
- Scelta manuale del vincitore.
- Contenuto delle schede del report su disiscrizioni e rimbalzi; elenchi di contatti dentro le schede; Condividi.

**Automazioni**
- Elenco completo dei modelli e delle azioni; togliere un contatto da un'attesa; cosa succede alle attese durante la
  pausa; modifica di un'automazione attiva.

**Invio**
- Nome esatto della voce di menu dei mittenti e domini; se l'SPF serve secondo la pagina ufficiale.

---

## 6. Rischi e vincoli di plumeCMS da rispettare

| Vincolo | Dove morde in questo lavoro | Cosa fare |
|---|---|---|
| Tabelle utf8 a 3 byte, emoji | Valori degli attributi, nomi di liste, segmenti, modelli, oggetti, testi dei blocchi, motivi del questionario, CSV importati | Una sola funzione per tutto il testo che entra (`newsletter_4byte_dentro()` già c'è, entità `&#x...;`), `newsletter_4byte_fuori()` all'uscita nelle email e negli oggetti. Nei nomi che diventano slug le emoji si tolgono. Nei campi semplici dei blocchi l'entità non va scritta due volte (trappola della 1.7.0) |
| MySQL in modalità stretta | Tabelle nuove, valori numerici e date degli attributi, `DECIMAL`, colonne `NOT NULL` | Ogni tabella con `*_schema()` e impronta; `DEFAULT` espliciti; valori del tipo sbagliato scartati prima della query (come Brevo); prove con `sql_mode` stretto |
| Colonne generate | Indici su JSON | Niente colonne generate: `newsletter_valori` a righe |
| ModSecurity e HTML nei POST | Editor a blocchi, Codice HTML, modelli, testi dei form | I campi con HTML viaggiano in base64 (`campi-protetti.js`) e `auth.php` li decodifica; il JSON del design anch'esso in base64 |
| CSP a nonce, niente gestori in linea | Editor delle email, costruttore dei form, finestre di conferma, cursore A/B | Solo attributi di `pannello.js` (`data-conferma`, `data-invia-al-cambio`) e script con nonce. La pagina ospitata del form e il suo iframe hanno la loro CSP con `frame-ancestors *` solo lì |
| Nessuna libreria esterna salvo SortableJS e Quill | XLSX, QR, grafici delle statistiche, editor a blocchi | XLSX con `ZipArchive` e SimpleXML; QR scritto in casa (algoritmo pubblico, una classe PHP); grafici in SVG fatti dal server; editor con SortableJS |
| Cron ogni 5 minuti | Programmazione, lotti, A/B, attese da 1 minuto, importazioni ed esportazioni a giri, nuovi tentativi dei soft bounce | Dire nelle pagine "parte entro 5 minuti"; l'attesa minima di 1 minuto diventa "almeno 1 minuto" (scatta al giro dopo); lavori lunghi a pezzi con un blocco (`GET_LOCK`) contro due cron sovrapposti |
| Hosting condiviso PHP 8.4 | Importazioni grandi, esportazioni, segmenti su molti contatti, limiti di posta per ora | Lavori a giri sotto 20 secondi; limite di caricamento detto prima dell'invio (`data-peso-massimo`); indici sui valori; nessuna query senza limite nella pagina Contatti; i limiti di invio della coda restano sopra lotti e A/B |
| Aggiornamento dal pannello con il motore vecchio | Ogni fase | Migrazioni dentro le funzioni di schema, non nell'SQL del pacchetto; niente voci nuove del manifest insieme a un motore nuovo; niente apostrofi nei commenti SQL; niente `.htaccess` nuovi |
| Opcache | Funzioni nuove chiamate da un altro file nella stessa versione (`contatto_blocca()` da `consegna.php`, motore dei segmenti dai flussi) | Copia di riserva con `function_exists` in chi chiama, come dalla 2.5.0 |
| Variabili nei file inclusi | Pagine nuove che includono `admin-header.php`; file dei blocchi dell'editor | Nomi con prefisso e `unset`; ogni file di blocchi caricato in una funzione sua (trappola della 2.0.0) |
| Valori di una scelta fuori elenco | Tendine di liste, segmenti, modelli, attributi che possono non esistere ancora (importazioni di pagine e modelli) | `'accetta' => regex` come per il widget Download |
| Rimandi | Le schede Iscritti e Liste che cambiano indirizzo | 302 con `no-store`, mai 301 |
| Prove che lasciano dati | Contatti, segmenti, campagne, automazioni di prova | Pulizia prima e dopo; prove degli attacchi seguite dallo svuotamento di `sicurezza_tentativi` |
| Legge e dati personali | Lista nera, riscrittura, esportazioni con dati personali, cancellazione | Riscrivere solo uno alla volta con l'avviso; esportazioni fuori dalla radice pubblica e cancellate dopo 7 giorni; "Cancella dati" che tiene l'impronta (scelta di Danilo: Brevo non la tiene, plumeCMS sì) |
| Peso del PDF del manuale | Nove fasi con molte schermate | Il PDF è a 7989 KB su un tetto di 8 MB (2.7.0): prima della fase 1 decidere come alleggerirlo o dividerlo, sennò la prima fase non ci sta |

Decisioni da chiedere a Danilo prima di cominciare:
1. Attributi calcolati e globali (COUNT, SUM): sì o no.
2. "All'orario migliore": sì o no.
3. Gruppi di consenso: nella fase 9 o mai (in Brevo sono dei piani a pagamento alti).
4. Soppressione con impronta dopo "Cancella dati": tenerla (consigliato) anche se Brevo non la ha.
5. Pagina di disiscrizione e centro preferenze anticipati nella fase 4 o nella fase 9.
6. Come stare sotto gli 8 MB del PDF del manuale.


## Decisioni di Danilo (1 novembre 2026)

1. Attributi calcolati e globali: sì.
2. "All'orario migliore": sì.
3. Gruppi di consenso: sì (nella fase dei form).
4. "Cancella dati" tiene l'impronta sha256 (differenza voluta da Brevo, più prudente).
5. Pagina di disiscrizione con il questionario e centro preferenze anticipati alla fase 4 (2.11.0, con le campagne a riepilogo).
6. PDF del manuale d'uso: tetto alzato da 8 a 12 MB (`MAX_KB` in `tools/manuali-pdf.js`), un solo PDF.
7. help.brevo.com non si legge dalla sessione (controllo anti-robot): per i dettagli esatti delle schermate fanno fede le schermate del suo account Brevo, se le manda.
