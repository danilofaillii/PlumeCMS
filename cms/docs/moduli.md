# Moduli del sito: ebook gratuito e Laser Coaching

> I **moduli del CMS** (cartella `moduli/`, manifesto `modulo.php`) sono un'altra cosa: `docs/cms-moduli.md`.

Deciso il 2026-10-23: al posto dei moduli Brevo, un sistema nel pannello. Dati nel database del sito,
email spedite dalla posta SMTP della scheda Posta. Codice in `includes/moduli.php`.

## Ebook (doppia conferma dell'email)

1. Modulo su `/ebook/`: nome, cognome, email, cellulare, consenso privacy (necessario),
   newsletter (facoltativo), captcha. I pulsanti della pagina che portavano a `/fuori-dagli-schemi/`
   (indirizzo Brevo) ora portano al modulo; `/fuori-dagli-schemi/` fa 301 su `/ebook/`.
2. Invio: `new/api/modulo.php` salva la richiesta in `newdf_iscrizioni_ebook` e manda l'email
   "Conferma il tuo indirizzo". Si arriva su `/fuori-dagli-schemi-download/`, la pagina "Ci sei quasi".
3. Clic sul link (valido 7 giorni): `/conferma-ebook/?t=...` segna la conferma e manda la seconda email
   con il link per scaricare (valido 30 giorni).
4. `/scarica-ebook/?t=...` scarica il PDF, che sta in `new/privato/` (cartella protetta) e si carica dal
   pannello, voce Contatti. Ogni download si conta.

Stessa email richiesta di nuovo: si aggiorna la riga e parte un link nuovo; il vecchio smette di valere.

## Laser Coaching

Modulo su `/fuoridalcoso-laser-coaching/` (al posto del modulo Brevo, tra i segnaposti
`Begin/End Brevo Form`) e in fondo a `/laser-coaching/`. Invio: riga in `newdf_richieste_laser`,
email immediata a Danilo (indirizzo di notifica della scheda Posta) con nome, cellulare cliccabile,
email e la scadenza delle 24 ore; pagina di cortesia `/laser-coaching-grazie/`.
Nel pannello, Contatti: le richieste da chiamare in cima, stato e note, CSV.

## Difese

- Captcha fatto in casa: il browser chiede una sfida a `new/api/captcha.php` e cerca il numero n con
  sha256(sale + n) = sfida (in media meno di mezzo secondo). Sfida firmata con HMAC, valida 20 minuti,
  usabile una volta (`newdf_captcha_usati`). Nessun servizio esterno, nessun cookie.
- Campo esca `sito_web`, invisibile alle persone.
- Massimo 5 invii l'ora per indirizzo IP e per modulo.
- Token di conferma e di download salvati solo come hash sha256.
- Il ritorno dopo l'invio accetta solo percorsi interni.

## Aperto

- Informativa privacy da aggiornare: dati raccolti dai due moduli, consenso alla newsletter, IP salvato
  come prova del consenso.
- La newsletter non usa più Brevo: vedi `newsletter.md`. Chi dà il consenso nel modulo dell'ebook
  diventa iscritto attivo quando conferma l'email.
