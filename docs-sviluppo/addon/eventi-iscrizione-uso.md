# Eventi con iscrizione

*Bozza del capitolo del manuale d'uso (addon della 2.0.0).*

Serate, corsi, laboratori, visite guidate con i posti contati. Ogni evento ha una pagina sua sul sito, con data, luogo,
descrizione, foto e il modulo per iscriversi. Chi si iscrive può prendere più posti (per sé e per un amico). Quando i
posti finiscono si entra in **lista d'attesa**: se qualcuno si cancella, il primo che aspetta entra da solo e riceve
l'email. Il giorno prima arriva il promemoria. Nel pannello vedi gli iscritti, segni le presenze, scarichi l'elenco e
scrivi a tutti.

## Esempio: l'associazione "Colli Bolognesi"

L'associazione organizza una serata di degustazione il venerdì alle 21, nella sede di via Rizzoli 1 a Bologna: 30 posti,
al massimo 2 a persona, 15 € a testa. Vuole chiudere le iscrizioni il giovedì sera.

## 1. Accendi l'addon

1. Nel pannello apri **Impostazioni > Addon**.
2. Sulla carta **Eventi con iscrizione** premi **Accendi**.
3. Nel menu a sinistra compare **Eventi**, con Eventi, Iscritti e Impostazioni.

## 2. Le impostazioni

Apri **Eventi > Impostazioni**:

- **Avvisi delle iscrizioni a**: l'email dell'associazione (vuoto: quella degli avvisi del sito).
- **Mandami un'email a ogni iscrizione e cancellazione**: spunta se vuoi saperlo subito.
- **Manda agli iscritti il promemoria il giorno prima**: lasciala spuntata.
- **Una frase in più nelle email di conferma**: per esempio "Porta con te questa email: all'ingresso diciamo il tuo nome".

Premi **Salva**.

## 3. Scrivi l'evento

1. Apri **Eventi > Eventi** e premi **Aggiungi un evento**.
2. **Titolo**: `Serata di degustazione`.
3. **Inizio**: il giorno e l'ora (21:00). **Fine**: facoltativa.
4. **Luogo**: `Sede dell'associazione`. **Indirizzo**: `Via Rizzoli 1, Bologna`. **Prezzo**: `15 € a persona`.
5. **Foto**: in **Media** apri la foto, copia il suo indirizzo e incollalo qui.
6. **Descrizione**: testo semplice; una riga vuota fa un paragrafo nuovo.
7. **Posti in tutto**: `30` (0 = senza limite). **Posti al massimo per persona**: `2`.
8. **Iscrizioni chiuse dal**: il giovedì alle 20:00 (vuoto: si chiudono all'inizio dell'evento).
9. Lascia spuntata **Lista d'attesa quando è pieno**.
10. **Stato**: `Pubblicato` (una **Bozza** sul sito non si vede).
11. Premi **Salva**. Compare: "la sua pagina è https://.../evento/serata-di-degustazione/". Premi **Vedi la pagina**.

L'indirizzo della pagina si fa da solo dal titolo; puoi cambiarlo in **Indirizzo della pagina**.

## 4. Sul sito

La pagina dell'evento mostra data, luogo, prezzo, quanti posti restano ("12 posti liberi", "Ultimo posto libero",
"Al completo: puoi entrare in lista d'attesa"), la descrizione e il modulo: nome, email, telefono (facoltativo),
quanti posti, la spunta della privacy e "Sono una persona". Google legge da solo i dati dell'evento (data, luogo,
organizzatore).

Per mostrare gli eventi in altre pagine, nell'editor, gruppo **Dal sito**:

- **Eventi con iscrizione**: l'elenco dei prossimi eventi, con i posti rimasti e il link "Iscriviti" alla loro pagina
  (stesso aspetto del widget Eventi).
- **Iscrizione all'evento**: il riquadro di un evento solo con il modulo. In **Evento** scegli quale (vuoto: il prossimo).

## 5. Cosa riceve chi si iscrive

- **Iscrizione confermata**, con il riepilogo, il link **Aggiungi al calendario** (file .ics) e il pulsante **Vedi o
  cancella l'iscrizione**.
- Se l'evento è pieno: **Sei in lista d'attesa**, con il suo numero in fila. Se si libera un posto: **Si è liberato un
  posto: sei iscritto**.
- Il giorno prima: **Promemoria**.

Dal pulsante dell'email la persona apre la sua pagina e può cancellarsi (il sito chiede "Vuoi davvero cancellare
l'iscrizione?"). Il suo posto va al primo in lista d'attesa.

Se qualcuno chiede più posti di quelli rimasti, il sito gli dice quanti ne restano ("Restano solo 2 posti"). La stessa
email non si iscrive due volte allo stesso evento.

## 6. Gli iscritti nel pannello

Apri **Eventi > Iscritti** e scegli l'evento dalla tendina:

- in alto: quanti posti sono presi ("18 su 30"), quanti aspettano;
- per ogni iscritto: posti, stato (Iscritto, In lista d'attesa con il numero, Cancellato);
- **Presente** / **Assente**: la sera dell'evento, per segnare chi è venuto;
- **Cancella**: toglie un'iscrizione (alla persona arriva l'email, e chi aspetta entra);
- **Scarica CSV**: l'elenco da aprire con Excel;
- **Scrivi a tutti gli iscritti**: oggetto e messaggio (testo semplice); ognuno lo riceve con il suo nome e il
  riepilogo dell'evento. Spunta **Anche a chi è in lista d'attesa** se serve.

Se aumenti i **Posti in tutto** dell'evento, i primi in attesa entrano da soli e ricevono l'email: il messaggio verde
dice quanti.

Un evento annullato: mettilo **Annullato** (la pagina lo dice, le iscrizioni si chiudono) e scrivi agli iscritti da
**Scrivi a tutti**. Un evento con iscritti non si elimina.

## 7. Spegnere

**Impostazioni > Addon > Spegni**. Le pagine degli eventi e i widget spariscono dal sito; eventi e iscritti restano nel
database e tornano se lo riaccendi.

## Checklist

- [ ] Addon acceso.
- [ ] Impostazioni: email degli avvisi, promemoria.
- [ ] Configurazione email a posto (Impostazioni > Configurazione email).
- [ ] L'evento: data e ora giuste, luogo e indirizzo, posti in tutto e per persona, chiusura, lista d'attesa, **Pubblicato**.
- [ ] La pagina dell'evento si apre (Vedi la pagina) e dice i posti giusti.
- [ ] Il widget Eventi con iscrizione in una pagina del sito (per esempio "Eventi"), messa nel menu.
- [ ] Un'iscrizione di prova con la tua email: arriva la conferma? Cancellati dal link e controlla che il posto torni.
- [ ] La sera dell'evento: Iscritti, Presente / Assente.
