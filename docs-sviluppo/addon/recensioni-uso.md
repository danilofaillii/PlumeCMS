# Raccolta recensioni

*Bozza del capitolo del manuale d'uso (addon della 2.0.0).*

Qualche giorno dopo il servizio, i clienti ricevono un'email "Com'è andata?" con cinque stelle da cliccare. Si apre una
pagina del tuo sito dove lasciano le stelle e due righe. Le recensioni arrivano nel pannello: tu le **approvi** (o le
rifiuti), puoi **rispondere**, e quelle approvate escono sul sito con il widget **Recensioni dal sito**. A chi è contento
(4 o 5 stelle) il sito propone anche di lasciarla su Google, se gli dai il link.

Ogni link è personale, vale per una recensione sola e scade (30 giorni, puoi cambiarlo). Chi non vuole più queste email
le ferma con un clic su "Non scrivermi più".

## Esempio: il centro estetico "Rosa" a Bologna

Il centro usa già l'addon Prenotazioni. Vuole che due giorni dopo ogni trattamento parta l'email, e che chi dà 4 o 5
stelle trovi anche il pulsante per Google. Ha anche un elenco di clienti affezionati, dal registro di cassa, a cui
vuole chiedere una recensione subito.

## 1. Accendi l'addon

1. **Impostazioni > Addon**, carta **Raccolta recensioni**, premi **Accendi**.
2. Nel menu a sinistra compare **Recensioni**, con Da approvare e approvate, Inviti, Impostazioni.

## 2. Le impostazioni

Apri **Recensioni > Impostazioni**:

- **Chiedi da solo dopo ogni appuntamento svolto**: spuntata. Serve l'addon Prenotazioni acceso: ogni appuntamento
  svolto prepara un invito (non per chi non si è presentato o ha annullato).
- **Dopo quanti giorni**: `2`.
- **Il link vale (giorni)**: `30`.
- **Oggetto** e **Testo** dell'email: c'è già un testo; cambialo con le tue parole.
- **Il link per lasciare una recensione su Google**: lo trovi nel Profilo dell'attività su Google, "Chiedi recensioni".
  Comincia con `https://`.
- **Proponilo a chi dà almeno**: `4 stelle`.
- **Mandami un'email a ogni recensione nuova** e **A questo indirizzo**.

Premi **Salva**.

## 3. Chiedere a mano a dei clienti

1. Apri **Recensioni > Inviti**.
2. In **Indirizzi email, uno per riga** incolla l'elenco. Puoi scrivere anche il nome:
   `Giulia Rossi <giulia.rossi@esempio.it>` (l'email comincerà con "Ciao Giulia").
3. **Servizio** (facoltativo): per esempio `Trattamento viso`.
4. **Quando parte**: Subito, oppure Fra 2 giorni.
5. Spunta **Sono miei clienti e mi hanno dato il loro indirizzo**: si può scrivere solo a loro.
6. Premi **Metti in coda**. Il messaggio dice quanti inviti sono in coda e quali indirizzi sono stati saltati (sbagliati,
   già invitati, o persone che hanno chiesto di non ricevere queste email).
7. Gli inviti partono al prossimo giro del cron (ogni 5 minuti). Per mandarli subito premi **Manda adesso quelli
   arrivati alla loro ora**.

L'elenco qui sotto dice per ognuno: In coda (e quando parte), Mandato (e fino a quando vale il link), Recensione
lasciata, Annullato. Un invito in coda si può **Annullare**; uno non usato si può **Eliminare**.

## 4. Cosa vede il cliente

L'email "Com'è andata?" ha cinque stelle: un clic su una stella apre la pagina del sito con quel voto già scelto. Il
cliente scrive due righe, controlla il nome da mostrare (il sito propone "Giulia R.") e spunta il consenso alla
pubblicazione. Premuto **Manda la recensione**, vede "Grazie!" e, se ha dato almeno 4 stelle, "Ti va di lasciarla anche
su Google?" con il suo testo da copiare e il pulsante **Lasciala su Google**.

Lo stesso link una seconda volta dice "Grazie, l'hai già lasciata". Un link scaduto dice "Questo link è scaduto".

In fondo all'email c'è **Non scrivermi più**: chi lo preme non riceve più inviti a lasciare recensioni.

## 5. Approvare, rispondere, rifiutare

Apri **Recensioni > Da approvare e approvate** (la scheda dice quante aspettano):

- **Approva**: la recensione esce sul sito.
- **Rifiuta**: non esce.
- **La tua risposta**: scrivila e premi **Salva la risposta**; sul sito esce sotto la recensione ("Risposta di Rosa").
- **Elimina**: per esempio se chi l'ha scritta chiede di toglierla.

Sopra trovi quante recensioni sono approvate e la media delle stelle.

## 6. Sul sito: il widget Recensioni dal sito

Nell'editor, gruppo **Marketing**, trascina **Recensioni dal sito** in una pagina (per esempio "Dicono di noi"). Ha lo
stesso aspetto del widget Recensioni: il voto medio con le stelle, le schede con stelle, testo, nome e mese, e sotto la
tua risposta. Puoi scegliere quante mostrarne, solo quelle da 4 stelle in su, le colonne, e accendere il pulsante per
Google.

Le stelle non vanno nei dati per Google: Google non le mostra per le recensioni raccolte sul proprio sito.

## 7. Spegnere

**Impostazioni > Addon > Spegni**. Il widget e i link delle email smettono di funzionare; recensioni e inviti restano
nel database e tornano se lo riaccendi.

## Checklist

- [ ] Addon acceso.
- [ ] Impostazioni: giorni, validità, testo dell'email, link di Google (https), avvisi.
- [ ] Configurazione email a posto (Impostazioni > Configurazione email).
- [ ] Con l'addon Prenotazioni acceso: "Chiedi da solo dopo ogni appuntamento svolto" spuntata.
- [ ] Un invito di prova alla tua email, Manda adesso: arriva? Le stelle aprono la pagina?
- [ ] La recensione di prova arriva nel pannello; approvala e rispondi.
- [ ] Il widget Recensioni dal sito in una pagina pubblicata.
- [ ] Il cron gira (Impostazioni > Log cron).
