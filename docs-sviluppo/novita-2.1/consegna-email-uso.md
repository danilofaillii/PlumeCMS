# Bozza per il manuale d'uso: consegna delle email (2.1.0)

Bozza dei capitoli da mettere nel manuale d'uso (`cms/docs/manuale-uso.md`) quando si decide il numero dei capitoli.
Le schermate sono da fare con `tools/manuale-uso-schermate.js` (pagine: `consegna.php` con le sei schede,
`newsletter-statistiche.php`, `newsletter-campagna.php` con la tendina Mittente).

---

## Le email arrivano nella posta, non nello spam

Le email del sito (newsletter, conferme di iscrizione, avvisi dei form) arrivano nella posta in arrivo solo se il tuo
dominio dice chiaramente chi può spedire per lui. Gmail, Outlook e Yahoo controllano tre righe del DNS del dominio:
**SPF** (chi può spedire), **DKIM** (la firma delle email) e **DMARC** (cosa fare con le email false). Da febbraio 2024
Gmail e Yahoo le chiedono a chiunque mandi una newsletter.

Tutto si fa da **Impostazioni > Consegna delle email**. La prima scheda, **Da dove cominciare**, mostra cinque passi con
il loro stato: Fatto (verde), Da sistemare (giallo), Da fare (rosso).

**Esempio.** Lo Studio Rossi ha il sito su `studiorossi.it` e manda la newsletter da `notizie@studiorossi.it`. Le prime
email sono finite nello spam di Gmail. Segui i passi qui sotto nell'ordine.

### 1. Il mittente

Il mittente è il nome e l'indirizzo che le persone vedono quando ricevono un'email.

1. Apri **Impostazioni > Consegna delle email**, scheda **Mittenti**.
2. In "Aggiungi un mittente" scrivi il nome (per esempio "Studio Rossi Notizie") e l'indirizzo (`notizie@studiorossi.it`).
   Se vuoi che le risposte arrivino altrove, scrivi l'indirizzo in "Le risposte arrivano a".
3. Premi **Aggiungi e manda la conferma**. Nella riga del mittente compare "Aspetta la conferma".
4. Apri la posta di `notizie@studiorossi.it`: c'è un'email "Conferma il mittente". Premi il pulsante.
5. Nella pagina che si apre premi **Confermo il mittente**. Compare "Mittente confermato".
6. Torna nel pannello e ricarica la scheda: la riga dice "Confermato" e, se è il primo, "Predefinito".

Puoi avere più mittenti (per esempio "Anna dello Studio" per i flussi di benvenuto). Il **predefinito** è quello usato
quando una campagna o un flusso non ne sceglie un altro: per cambiarlo premi **Rendi predefinito** nella sua riga.

Usa sempre un indirizzo del tuo dominio. Un indirizzo `@gmail.com`, `@libero.it` o `@outlook.com` non va bene: il pannello
lo segnala in rosso, perché Gmail, Yahoo e Outlook rifiutano o mettono nello spam le newsletter spedite a loro nome da altri
server.

### 2. Il dominio

1. Scheda **Dominio**. Nella casella c'è già il dominio del mittente predefinito: premi **Controlla**.
2. Per ognuna delle quattro voci (SPF, DKIM, DMARC, MX) leggi lo stato:
   - **A posto**: non devi fare niente;
   - **Manca** o **Da sistemare**: sotto c'è la frase che spiega perché e, quasi sempre, il record da aggiungere con
     Tipo, Nome e Valore.
3. Se il DNS lo gestisci tu (per esempio da cPanel, **Zone Editor**): tocca la casella Valore, il testo si copia da solo;
   aggiungi un record del tipo e con il nome indicati e incolla il valore.
4. Se il DNS lo gestisce l'hosting: in fondo c'è **Il messaggio per l'hosting**. Tocca la casella per copiarlo e mandalo
   alla loro assistenza così com'è.
5. Dopo la modifica aspetta (possono servire fino a 24 ore) e premi di nuovo **Controlla**.

Per la firma **DKIM** il servizio di spedizione a volte dà un "selettore" (la parte prima di `._domainkey`, per esempio
`brevo1`). Se il pannello dice "Non trovata con i selettori più comuni", scrivilo in **Selettore DKIM** e rifai il controllo.

Se compare "Il DNS non risponde", il server non riesce a leggere il dominio: riprova più tardi; se resta così, chiedilo
all'hosting.

### 3. Come partono le email

Per poche centinaia di email al giorno basta il server di posta dell'hosting (Impostazioni > Configurazione email). Per di
più, o se le email finiscono spesso nello spam, conviene un servizio di spedizione: ha una reputazione già costruita e dice al
sito chi rimbalza e chi segnala spam.

**Brevo via SMTP** (il modo più semplice): in **Impostazioni > Configurazione email** scrivi server `smtp-relay.brevo.com`,
porta `587`, sicurezza STARTTLS, utente il Login della pagina **SMTP e API** di Brevo, password una chiave SMTP di Brevo.

**Con le API** (Brevo, Amazon SES, Mailgun):

1. Scheda **Come partono**. Nella tendina scegli il servizio: compaiono i suoi campi e i passi per trovare le chiavi.
2. Incolla le chiavi e premi **Salva**. Deve comparire "Salvato: le email partono da ...".
3. In **Prova l'invio** scrivi il tuo indirizzo e premi **Manda la prova**. Arriva un'email "Prova di consegna dal pannello"
   con un piccolo file allegato. Guarda anche nello Spam.

Le chiavi si salvano cifrate e non si vedono più: il campo resta vuoto e sotto c'è "salvata il ...". Per cambiarle incolla
quella nuova; per toglierle usa **Togli le chiavi** in fondo alla scheda.

Errori frequenti, come li dice il pannello:
- "Questa è la chiave SMTP di Brevo": hai incollato la chiave che comincia con `xsmtpsib-`; nelle API serve quella che
  comincia con `xkeysib-`.
- "... non accetta la chiave (codice 401)": la chiave è sbagliata, incompleta o disattivata nel servizio.
- "Salvato, ma mancano dei dati": finché non li scrivi le email partono come prima.

### 4. Rimbalzi e spam

Quando un indirizzo non esiste (rimbalzo) o qualcuno preme "è spam", il servizio lo dice al sito con un avviso automatico.
Il sito toglie subito quella persona: continuare a scriverle rovina la reputazione del dominio.

1. Scheda **Rimbalzi e spam**. Per il tuo servizio c'è un **Indirizzo**: toccalo per copiarlo.
2. Segui i tre passi scritti sotto, nel pannello del servizio (Brevo: Transazionale, Impostazioni, Webhook; Amazon: SNS e SES;
   Mailgun: Send, Webhooks).
3. Con Mailgun incolla anche la **HTTP webhook signing key** e premi **Salva la chiave di firma**.
4. Quando arriva il primo avviso, accanto al servizio compare "Ultimo avviso ..." e in fondo la tabella **Ultimi avvisi
   ricevuti** dice cosa ha fatto il sito.

Chi segnala spam diventa "ha segnalato spam" in **Newsletter e form > Iscritti e liste** (filtro "Hanno segnalato spam"):
non riceve più niente e un'importazione non lo rimette dentro. Solo una sua nuova iscrizione con la conferma lo fa rientrare.

Se usi il server SMTP dell'hosting non serve: i rifiuti immediati il pannello li vede da solo.

### 5. Riscaldamento

Un indirizzo nuovo (o fermo da mesi) che manda subito migliaia di email sembra uno spammer. Il riscaldamento parte con poche
email al giorno e sale da solo.

1. Scheda **Riscaldamento**. Spunta **Riscaldamento acceso**.
2. "Email il primo giorno": 50 va bene per quasi tutti. "Fino a quante al giorno": il numero da raggiungere (vuoto = il
   limite di Newsletter, Impostazioni). "Quanto sale ogni giorno": Normale (+50%).
3. Premi **Salva**. In alto compare "A che punto è": il giorno, quante email partono oggi al massimo, quante domani e fra
   quanti giorni arriva al limite. Sotto, la tabella dei giorni.

Le email che non entrano nel limite di oggi partono il giorno dopo. Conferme di iscrizione e avvisi dei form partono
sempre. Anche la pagina **Newsletter e form > Campagne** dice "Riscaldamento acceso, giorno ...".

### 6. Scegliere il mittente di una campagna o di un flusso

- Campagna: **Newsletter e form > Campagne**, apri la campagna, tendina **Mittente**, poi **Salva**.
- Flusso: **Newsletter e form > Flussi automatici**, apri il flusso, a sinistra **Mittente**, poi **Salva**.

Nelle tendine ci sono solo i mittenti confermati. "Il predefinito" segue il mittente predefinito anche se lo cambi dopo.

### 7. Le statistiche

**Newsletter e form > Statistiche**: per ogni campagna, accanto ad aperture e clic, ci sono **Rimbalzi**, **Uscite** e
**Spam**, ognuno con il numero di persone e la percentuale. Il numero diventa rosso quando supera le soglie che Gmail e Yahoo
guardano: spam oltre lo 0,3%, rimbalzi oltre il 2%. In quel caso: pulisci la lista (togli i contatti vecchi o comprati),
controlla il dominio e, se l'indirizzo è nuovo, accendi il riscaldamento.

---

## Checklist

- [ ] Il mittente è un indirizzo del mio dominio, confermato e predefinito.
- [ ] In Dominio SPF, DKIM, DMARC e MX sono "A posto".
- [ ] In Come partono la prova arriva nella posta in arrivo, con l'allegato.
- [ ] Se uso Brevo, Amazon SES o Mailgun: l'indirizzo di Rimbalzi e spam è incollato nel servizio e c'è un "Ultimo avviso".
- [ ] Indirizzo nuovo o tanti iscritti tutti insieme: il riscaldamento è acceso.
- [ ] Dopo ogni campagna guardo in Statistiche che spam e rimbalzi non siano rossi.

Portata nel manuale d'uso il 2026-10-31, capitoli 25 (sezione "Le email arrivano nella posta, non nello spam") e 20 (tendina Mittente della campagna).
