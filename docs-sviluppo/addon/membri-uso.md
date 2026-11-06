# L'area riservata: account per i visitatori del sito

*Bozza del capitolo del manuale d'uso (addon Area riservata, plumeCMS 2.0.0).*

Con l'area riservata i visitatori del tuo sito possono crearsi un account: si iscrivono con la loro email, entrano con
una password e trovano pagine, articoli e file che gli altri non vedono. Sono **membri del sito**, non utenti del
pannello: non possono mai entrare nel pannello né cambiare il sito.

Cosa ti serve prima di cominciare:

- la posta del sito funzionante (Impostazioni > Configurazione email): ai membri arrivano le email di conferma;
- un indirizzo per gli avvisi nella stessa pagina, se vuoi sapere chi si iscrive.

## 1. Accendere l'area riservata

1. Nel pannello apri **Impostazioni**, poi **Addon**.
2. Nella carta **Area riservata** premi **Accendi**.
3. In alto compare il messaggio verde «Area riservata» è acceso. Nel menu a sinistra, sotto Utenti, c'è la voce nuova **Membri**.

Da adesso il sito ha questi indirizzi nuovi (al posto di `www.tuosito.it` metti il tuo):

| Indirizzo | A cosa serve |
|---|---|
| `www.tuosito.it/accedi/` | entrare con email e password |
| `www.tuosito.it/iscriviti/` | crearsi un account |
| `www.tuosito.it/area-riservata/` | la pagina del membro: contenuti, file, profilo |
| `www.tuosito.it/password-dimenticata/` | scegliere una password nuova |

## 2. Scegliere chi può iscriversi

1. Apri **Membri**, poi **Impostazioni**.
2. In **Chi può iscriversi** scegli una delle tre:
   - **Aperta a tutti**: chi si iscrive conferma l'email ed è subito dentro;
   - **Aperta, ma approvo io**: dopo la conferma dell'email l'account aspetta che tu lo attivi;
   - **Solo su invito**: la pagina Iscriviti dice che si entra solo su invito; gli account li crei tu.
3. Se vuoi, cambia **Il testo per chi non è entrato**: è la frase che legge chi apre una pagina riservata senza essere entrato.
4. Lascia la spunta su **Mandami un'email per ogni nuova iscrizione** se vuoi saperlo subito.
5. Premi **Salva**. Compare «Impostazioni dell'area riservata salvate».

## 3. Rendere una pagina o un articolo "solo per i membri"

1. Apri **Membri**, poi **Contenuti e file riservati**.
2. Nel riquadro **Pagine e articoli** spunta quelli da riservare. La home non si può riservare: resta di tutti.
3. Premi **Salva i contenuti riservati**. Compare per esempio «Fatto: 2 contenuti sono solo per i membri».

Cosa vede chi non è entrato: il titolo, la frase per chi non è entrato e i pulsanti **Accedi** e **Iscriviti**. Il testo no.
Chi preme Accedi, dopo aver messo email e password, torna proprio a quella pagina, aperta.

> Attenzione: se spegni l'addon, le pagine riservate tornano visibili a tutti.

## 4. Dare un file ai membri

1. Apri **Download** e carica il file con la spunta **Riservato** (vedi il capitolo dei Download).
2. Apri **Membri**, poi **Contenuti e file riservati**.
3. Nel riquadro **File per i membri** spunta il file e premi **Salva i file per i membri**.

I membri lo trovano nella loro area riservata, sotto **File da scaricare**. Chi non è entrato non può scaricarlo.

## 5. Mettere "Accedi" sul sito

Il modo più semplice è il widget **Accesso membri**:

1. Apri la pagina (o l'header, in Editor > Header e footer) nell'editor.
2. Nel gruppo **Dal sito** trascina **Accesso membri** dove vuoi.
3. Nella scheda Contenuto scegli i testi e lo stile del pulsante.
4. Pubblica.

Chi è fuori vede **Accedi** (e **Iscriviti**, se le iscrizioni sono aperte); chi è dentro vede «Ciao, Giulia» e **Area riservata**.
In alternativa metti nel menu (Editor > Menu di navigazione) un link a `/accedi/`.

## 6. Come si iscrive un visitatore

1. Apre `/iscriviti/`, scrive nome, email e due volte la password (almeno 12 caratteri).
2. Spunta l'informativa privacy e **Sono una persona**, aspetta «Verificato» e preme **Crea il mio account**.
3. Compare «Controlla la tua email». Gli arriva l'email **Conferma la tua iscrizione**: preme il pulsante, poi **Confermo l'iscrizione**.
4. È dentro: vede «Ciao Giulia» nella sua area riservata.

Con **Aperta, ma approvo io**, dopo la conferma legge «Adesso la tua iscrizione aspetta la nostra approvazione»: a te arriva
l'email **Nuova iscrizione da approvare**.

## 7. Gestire i membri

Apri **Membri**, poi **Elenco dei membri**. In alto filtri per stato (Attivi, In attesa, Sospesi...) e la ricerca per nome o email.

| Pulsante | Cosa fa |
|---|---|
| **Attiva** | attiva un account in attesa o sospeso; al membro arriva l'email «Il tuo account è attivo» |
| **Sospendi** | il membro non entra più, e se era dentro esce subito; i suoi dati restano |
| **Elimina** | cancella il membro e tutti i suoi dati (anche l'avanzamento nei corsi). Non si torna indietro |
| **Rimanda l'invito** | per chi è stato invitato e non ha ancora scelto la password |

**Invitare una persona**: nel riquadro **Invita una persona** scrivi nome ed email e premi **Invita**. Le arriva un'email con
il pulsante **Scegli la password** (vale 7 giorni): scelta la password, è dentro.

**Esportare l'elenco**: nel riquadro **Esporta** premi **Scarica l'elenco (CSV)**. Il file si apre con Excel o LibreOffice.
Sono dati personali: tienilo al sicuro.

## 8. Quello che il membro fa da solo

Nella sua area riservata, con **Il mio profilo**:

- cambia il nome;
- cambia l'email: scrive il nuovo indirizzo e la password attuale, poi conferma dal link che arriva al nuovo indirizzo;
- cambia la password (serve quella attuale): sugli altri dispositivi deve entrare di nuovo;
- **Cancella il mio account**: password, spunta «Sì, voglio cancellare il mio account», pulsante. Sparisce tutto.

Se dimentica la password: **Password dimenticata?** nella pagina Accedi, scrive l'email e riceve un link valido 60 minuti.

## Esempio: una guida per gli iscritti

Lo Studio Rossi vuole dare il listino 2026 e una pagina "Consigli per i clienti" solo a chi si iscrive.

1. Impostazioni > Addon > Area riservata > **Accendi**.
2. Membri > Impostazioni: **Aperta a tutti**, testo «Questa guida è per i nostri clienti: iscriviti, è gratis.» > **Salva**.
3. Pagine > **Nuova pagina** "Consigli per i clienti", la scrive e la pubblica.
4. Membri > Contenuti e file riservati: spunta "Consigli per i clienti" > **Salva i contenuti riservati**.
5. Download: carica `listino-2026.pdf` con la spunta **Riservato**.
6. Membri > Contenuti e file riservati: spunta il listino nel riquadro File per i membri > **Salva i file per i membri**.
7. Editor > Header e footer: nell'header aggiunge il widget **Accesso membri** e pubblica.
8. Prova da un'altra finestra del browser (in incognito): la pagina dei consigli mostra solo il titolo e **Accedi**; si
   iscrive con la sua email personale, conferma, e trova pagina e listino nella sua area riservata.

## Checklist

- [ ] La posta del sito funziona (Impostazioni > Configurazione email > prova).
- [ ] L'addon Area riservata è acceso e nel menu c'è **Membri**.
- [ ] Ho scelto chi può iscriversi e salvato.
- [ ] Le pagine e gli articoli riservati sono spuntati e salvati; da una finestra in incognito si vede solo il titolo.
- [ ] I file per i membri sono riservati in Download e spuntati in Contenuti e file riservati.
- [ ] Sul sito c'è un modo per arrivare ad Accedi (widget Accesso membri o voce di menu).
- [ ] Mi sono iscritto con un'email di prova: l'email di conferma arriva, e dopo la conferma sono dentro.
- [ ] L'informativa privacy parla anche degli account del sito (`/privacy-policy/`).
