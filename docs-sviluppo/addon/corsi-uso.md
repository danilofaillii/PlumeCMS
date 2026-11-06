# I corsi online

*Bozza del capitolo del manuale d'uso (addon Corsi online, plumeCMS 2.0.0).*

Con i corsi online metti sul sito dei percorsi fatti di lezioni: testo, video e materiali da scaricare. Li seguono i
**membri** dell'area riservata (capitolo precedente): ognuno vede quante lezioni gli mancano, riprende da dove era e, alla
fine, riceve un **certificato** con un codice che chiunque può verificare.

## 1. Accendere i corsi

1. **Impostazioni** > **Addon** > carta **Corsi online** > **Accendi**.
2. Compare «Corsi online» è acceso, e «Acceso anche «Area riservata», che serve».
3. Nel menu a sinistra c'è la voce nuova **Corsi**.

Sul sito l'elenco dei corsi è a `www.tuosito.it/corsi/`.

## 2. Creare un corso

1. Apri **Corsi**. Nel riquadro **Nuovo corso** scrivi il titolo (per esempio «Fotografia per cominciare») e premi **Crea il corso**.
2. Si apre la pagina del corso. Nel riquadro **Il corso**:
   - **Descrizione**: due o tre frasi su cosa si impara;
   - **Immagine** (facoltativa): l'indirizzo di un'immagine di Media (comincia con `/uploads/`);
   - **Chi può seguirlo**: **Tutti i membri**, oppure **Solo i membri a cui lo assegno**;
   - **Stato**: lascia **Bozza** finché il corso non è pronto.
3. Premi **Salva il corso**.

## 3. Moduli e lezioni

Un corso è fatto di **moduli** (i capitoli) e ogni modulo di **lezioni**. Il corso nasce con «Modulo 1».

1. Nel riquadro **Programma** cambia il nome del modulo (per esempio «Le basi») e premi **Salva** accanto.
2. Sotto il modulo scrivi il titolo della prima lezione e premi **Aggiungi lezione**. Si apre la scheda della lezione.
3. Per un altro modulo: in fondo al Programma scrivi il nome e premi **Aggiungi modulo**.
4. Le frecce **↑** e **↓** spostano lezioni e moduli: i membri seguono le lezioni in quest'ordine.

## 4. Scrivere una lezione

Nella scheda della lezione:

1. **Testo della lezione**: si scrive in HTML semplice. Un paragrafo è `<p>...</p>`, un titolo `<h2>...</h2>`, il
   grassetto `<b>...</b>`. Puoi anche incollare il testo di un articolo. Script e codice pericoloso si tolgono da soli.
2. **Video**, una delle due:
   - **Indirizzo di YouTube o Vimeo**: copia l'indirizzo dalla barra del browser mentre guardi il video
     (per esempio `https://www.youtube.com/watch?v=...` o `https://vimeo.com/123456`);
   - **Carica un video** (.mp4 o .webm): lo vede solo chi segue il corso. C'è un limite di peso del server, scritto sotto il campo.
3. **Materiali da scaricare**: spunta i file. Sono i file **riservati** della pagina Download (lì si caricano, con la spunta Riservato).
4. **Durata** (facoltativa), per esempio «12 minuti».
5. Premi **Salva la lezione**. Compare «Lezione salvata». Con **Vedi sul sito** la guardi come la vedrà un membro.

## 5. Pubblicare e decidere chi lo segue

1. Torna alla pagina del corso (link in alto nella scheda della lezione).
2. In **Stato** scegli **Pubblicato** e premi **Salva il corso**.

Se il corso è **Solo i membri a cui lo assegno**: nel riquadro **Chi segue il corso** scegli il membro nella tendina
**Assegna il corso a**, lascia la spunta **mandagli un'email** e premi **Assegna**. Il membro riceve «Hai un corso nuovo».
Con **Togli** il membro non lo vede più (il suo avanzamento resta, se glielo riassegni).

## 6. Cosa vede il membro

- In `/corsi/` e nella sua area riservata, sotto **I miei corsi**, le schede dei corsi con la barra dell'avanzamento.
- Nella pagina del corso il programma, con il segno &#10003; sulle lezioni fatte, e il pulsante **Inizia** oppure
  **Riprendi da dove eri: <lezione>**.
- In ogni lezione video, testo, materiali e il pulsante **Segna come completata e vai avanti**.
- Finita l'ultima lezione: «Hai completato il corso» e il pulsante **Il tuo certificato**.

Chi non è entrato vede il programma (solo i titoli) e il pulsante **Accedi**.

## 7. Il certificato

Il certificato è una pagina con il nome del membro, il titolo del corso, la data e un **codice di verifica** (per esempio
`K7M2-9QXA-4HTZ`). Il membro lo stampa o lo salva in PDF con **Stampa o salva in PDF**.

Chi vuole controllarlo (un datore di lavoro, un cliente) apre `www.tuosito.it/certificato/`, scrive il codice e vede
«Certificato valido» con nome, corso e data. Nel pannello, in **Chi segue il corso**, vedi il codice di ognuno.

## 8. Seguire chi studia

Nella pagina del corso, riquadro **Chi segue il corso**: per ogni membro le lezioni fatte (per esempio «2 su 3 (66%)»),
l'ultima visita e il codice del certificato, se c'è.

## 9. Mettere i corsi in una pagina

Nell'editor, gruppo **Dal sito**, trascina il widget **Elenco corsi**. Scegli se mostrare **Tutti i corsi pubblicati** o
**Solo quelli che chi guarda può seguire**, quanti al massimo e se con la descrizione. Pubblica.

## Esempio: un corso di fotografia in tre lezioni

1. Corsi > Nuovo corso «Fotografia per cominciare» > **Crea il corso**; descrizione; **Tutti i membri**; **Salva il corso**.
2. Modulo 1 diventa «Le basi»; lezioni «La luce» e «L'inquadratura». Modulo nuovo «Pratica» con la lezione «Il primo scatto».
3. «La luce»: testo e indirizzo di un video di YouTube. «L'inquadratura»: un video caricato e la scheda PDF (caricata prima
   in Download come riservata). «Il primo scatto»: un video di Vimeo.
4. Con le frecce porta «L'inquadratura» prima de «La luce».
5. Stato **Pubblicato** > **Salva il corso**.
6. Da una finestra in incognito si iscrive come membro, apre `/corsi/`, segue le tre lezioni e scarica il certificato;
   poi controlla il codice da `/certificato/`.

## Checklist

- [ ] L'Area riservata funziona (iscrizione e accesso provati).
- [ ] Il corso ha descrizione, almeno un modulo e le lezioni nell'ordine giusto.
- [ ] Ogni lezione ha testo o video; i video di YouTube e Vimeo si vedono con **Vedi sul sito**.
- [ ] I materiali sono riservati in Download e spuntati nelle lezioni.
- [ ] Il corso è **Pubblicato** e chi deve seguirlo può (tutti i membri, oppure assegnato).
- [ ] Con un membro di prova: Inizia, Segna come completata, Riprendi, certificato, verifica del codice.
- [ ] Il widget **Elenco corsi** (o un link a `/corsi/`) è in una pagina o nel menu.
