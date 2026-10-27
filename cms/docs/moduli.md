# Moduli da compilare: newsletter e form

> I **moduli del CMS** (cartella `moduli/`, manifesto `modulo.php`) sono un'altra cosa: `docs/cms-moduli.md`.

Il nucleo ha due moduli da compilare: l'iscrizione alla **newsletter** (`newsletter.md`) e i **form** costruiti
dal pannello, voce Form (`includes/form.php`). Tutti e due arrivano a `api/modulo.php` e si mettono nelle pagine
con il widget Modulo. I pezzi comuni stanno in `includes/moduli.php`. Dati nel database del sito, email spedite
dalla posta SMTP della scheda Posta (`posta_invia()`).

## I pezzi comuni (`includes/moduli.php`)

- **Captcha fatto in casa**: spuntando "Sono una persona" il browser chiede una sfida a `api/captcha.php` e cerca
  il numero n con sha256(sale + n) = sfida (in media meno di mezzo secondo). Sfida firmata con HMAC, valida
  20 minuti, usabile una volta (`<prefisso>captcha_usati`). Nessun servizio esterno, nessun cookie.
- **Campo esca** `sito_web`, invisibile alle persone: chi lo riempie è un programma e viene respinto.
- **Limite di invii**: massimo 5 l'ora per indirizzo IP e per modulo (`MODULI_LIMITE_ORA`).
- **Campi**: `moduli_leggi()` controlla nome, cognome, email, consenso privacy e captcha, e toglie i caratteri a 4 byte.
- **Email**: `moduli_email_html()` le impagina tutte allo stesso modo, con la riga in testa (`email_intestazione`,
  altrimenti il nome del sito) e la firma in fondo (`email_firma`).
- Il ritorno dopo l'invio accetta solo percorsi interni.

## Un modulo da compilare portato da un modulo del sito

Un modulo con un percorso suo (un download dopo la conferma, una richiesta da richiamare) lo porta il modulo del
sito, con i suoi filtri ed eventi (`cms-moduli.md`):

- `moduli_disponibili` aggiunge la sua voce alla tendina del widget Modulo; `modulo_html` lo disegna. Se nessuno
  conosce più quel tipo, sul sito non esce niente e nell'editor compare un avviso.
- `pagina_moduli` lo aggiunge al testo delle pagine scritte nella scheda classica.
- l'evento `sito_percorso` (con `$percorso` e la query) gli fa rispondere ai suoi indirizzi: conferme, download,
  pagine di cortesia.
