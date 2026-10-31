# Email marketing in plumeCMS: la checklist per arrivare al livello di Brevo

Aggiornata al 31 ottobre 2026: tappa 2 (consegna) fatta e provata (2.1.0, `docs-sviluppo/manuale-tecnico.md` capitolo 35). Segno: [x] fatto, [ ] da fare. Fra parentesi la tappa proposta.
Riferimento: le sezioni del menu di Brevo. Cosa c'è oggi viene dal codice (analisi del 30 ottobre).

## Contatti
- [x] Liste, iscritti con stato, fonte e data del consenso
- [x] Doppia conferma dell'email
- [x] Importazione CSV ed esportazione CSV di tutti
- [x] Importazione senza consenso inventato e senza far partire i flussi (2.0.0)
- [x] Lista di soppressione: chi chiede la cancellazione non rientra con un'importazione (2.0.0)
- [x] Stato "rimbalzato" per gli indirizzi rifiutati dal server (2.0.0)
- [ ] Campi personalizzati (città, interesse, data di nascita...) da usare nei testi e nei segmenti (tappa 3)
- [ ] Tag (tappa 3)
- [ ] Segmenti che si aggiornano da soli, con regole su liste, campi, aperture e clic (tappa 3)
- [ ] Scheda del contatto con lo storico: email ricevute, aperture, clic, form, download (tappa 3)
- [ ] Importazione con la scelta delle colonne (tappa 3)
- [ ] Esportazione del singolo contatto (diritto di accesso GDPR) (tappa 4)

## Moduli e landing page
- [x] Form costruiti nel pannello, con lista e doppia conferma
- [x] Popup (secondi, scorrimento, uscita), landing page con l'editor
- [x] Il form con conferma iscrive solo alla sua lista, non anche alla newsletter (2.0.0)
- [x] File omaggio dopo la conferma, con link personale a scadenza (2.0.0)
- [ ] Email di conferma con il testo scritto per ogni form (tappa 1 bis)
- [ ] Campi del form legati ai campi personalizzati del contatto (tappa 3)

## Campagne
- [x] Editor di testo, prova a un indirizzo, nome della persona nel testo
- [x] Programmazione, sospendi e riprendi, duplica, invio a più liste
- [ ] Editor a blocchi per le email (colonne, immagini, pulsanti, social), compatibile con Outlook (tappa 4)
- [ ] Modelli salvati e modelli pronti (tappa 4)
- [ ] Anteprima telefono e computer (tappa 4)
- [ ] Escludere liste e segmenti (tappa 4)
- [ ] Rimandare a chi non ha aperto, con un altro oggetto (tappa 4)
- [ ] Test A/B sull'oggetto (tappa 4, utile oltre qualche migliaio di iscritti)
- [ ] Blocchi che compaiono solo per certi contatti (tappa 4)

## Automazioni
- [x] Tela con inneschi (iscrizione, lista, form, apertura, clic, inattivo), attese, bivi con 9 condizioni
- [x] Il flusso "compila un form" parte anche per chi conferma più tardi (2.0.0)
- [ ] Azioni sul contatto: metti in lista, togli, aggiorna un campo, avvisami (tappa 3)
- [ ] Inneschi nuovi: clic su un link preciso, ha scaricato il file, data (compleanno), visita una pagina (tappa 3)
- [ ] Flussi ripetibili (oggi ogni flusso vale una volta sola per persona) (tappa 3)
- [ ] Modelli pronti: benvenuto in tre email, omaggio più seguito, riattivazione (tappa 3)
- [ ] Prova del flusso con un contatto finto (tappa 3)
- [ ] Numeri per ogni passo del flusso (tappa 3)

## Statistiche
- [x] Aperture e clic per campagna, link più cliccati, iscritti per mese
- [x] Chi ha ricevuto e scaricato un file riservato (2.0.0)
- [x] Rimbalzi, disiscrizioni e segnalazioni di spam per campagna, con le soglie di Gmail e Yahoo in rosso (tappa 2, 2.1.0)
- [ ] Andamento per lista e nel tempo (tappa 4)
- [ ] Avviso: le aperture sono gonfiate dalla protezione della privacy di Apple Mail (tappa 4)

## Mittenti, domini e consegna
- [x] Server di posta configurabile, link di uscita anche in un clic (List-Unsubscribe-Post)
- [x] Mittente giusto anche senza server di posta, parte di testo semplice (2.0.0)
- [x] Un solo conteggio di tutte le email, all'ora e al giorno (2.0.0)
- [x] Pagina che controlla SPF, DKIM, DMARC e MX del dominio e dice cosa chiedere all'hosting, con il testo esatto (tappa 2, 2.1.0; provata con un DNS finto, non con il DNS vero)
- [x] Più mittenti verificati con un link di conferma, uno predefinito, scelti da campagne e flussi (tappa 2, 2.1.0)
- [x] Spedizione tramite un servizio esterno: Brevo SMTP (Configurazione email), API di Brevo, Amazon SES (firma SigV4) e Mailgun, chiavi cifrate, prova con allegato (tappa 2, 2.1.0; provata contro server finti, non contro i servizi veri)
- [x] Rimbalzi, segnalazioni di spam e uscite dai servizi via webhook (Brevo con segreto, Mailgun firmato, SES con SNS firmato) (tappa 2, 2.1.0)
- [x] Riscaldamento graduale: limite che sale da solo per un indirizzo nuovo, con lo stato nel pannello (tappa 2, 2.1.0)
- [ ] Lettura dei rimbalzi che arrivano come email in una casella (IMAP), per chi spedisce con il server dell'hosting (tappa 4)
- [ ] Password SMTP cifrata come le chiavi dei servizi (tappa 4)

## GDPR
- [x] Data e versione del consenso, indirizzo IP
- [x] "Cancella dati" che cancella tutto, anche eventi, flussi e invii dei form (2.0.0)
- [x] Pulizia degli iscritti mai confermati (2.0.0)
- [ ] Registro del consenso con il testo esatto, il form e la pagina (tappa 4)
- [ ] Tempi di conservazione impostabili (tappa 4)

## Fuori dall'email marketing (Brevo li ha, qui no per ora)
- SMS e WhatsApp, chat sul sito, CRM con le trattative, programma fedeltà, testi scritti dall'intelligenza artificiale.
