# Come aprire plumeCMS come progetto a sé

Questo zip contiene tutto il progetto: il CMS (`cms/`), gli strumenti per costruire gli zip (`tools/`),
le prove (`prove/`), la memoria (`CLAUDE.md` e `memoria/`). Non serve niente del repository di danilofailli.it.

## 1. Il repository su GitHub

1. Su github.com, in alto a destra **+**, poi **New repository**.
2. Nome: `plumecms`. Visibilità: **Private**. Lascia vuote le caselle README, .gitignore e licenza.
3. Premi **Create repository**.
4. Nella pagina che si apre clicca **uploading an existing file**.
5. Estrai lo zip sul computer, apri la cartella `plumecms` e trascina nella pagina **tutto quello che c'è dentro**
   (le cartelle `cms`, `tools`, `prove`, `memoria` e i file `CLAUDE.md`, `README.md`, `SPOSTARE.md`, `.gitignore`).
   Se `.gitignore` non si vede, sul Mac premi Cmd+Maiusc+punto nella cartella.
6. In fondo scrivi "plumeCMS 1.1.9, primo caricamento" e premi **Commit changes**.

## 2. La sessione Claude Code

1. Su claude.ai/code apri una sessione nuova e scegli il repository `plumecms`.
2. Al primo messaggio Claude legge da solo `CLAUDE.md`: dentro ci sono stato, regole, trappole e prossimi passi.
3. Se l'ambiente chiede l'accesso a GitHub per il repository nuovo, dai il permesso.

## 3. Se usi un Progetto su claude.ai (chat)

Nella scheda **Conoscenza del progetto** carica `CLAUDE.md`, `memoria/INDEX.md`, `memoria/decisioni.md`,
`memoria/contesto.md` e `cms/CHANGELOG.md`. Il codice va su GitHub: per lavorarci serve Claude Code.

## 4. Cosa resta a danilofailli.it

danilofailli.it è la prima installazione di plumeCMS e ha in più il suo modulo `moduli/danilofailli/`.
Quel modulo resta nel repository di danilofailli.it. Le versioni nuove del CMS arrivano al sito di Danilo
solo come pacchetti fatti con `--sito` da quel repository, mai con lo zip `-aggiornamento` del prodotto.
