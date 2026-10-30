#!/usr/bin/env python3
"""
Il sito di plumeCMS (27 ottobre 2026), fatto con plumeCMS: pagine dell'editor, stile, header, footer e menu
in un pacchetto per il pannello Aggiornamenti (motore 1.2.3 o successivo).

Prima di lanciarlo:
  python3 tools/cms-pacchetto.py --aggiornamento     (gli zip del CMS da offrire)
  node tools/manuali-pdf.js                          (i due manuali in PDF)

uso: python3 sito-plumecms/costruisci.py [--schermate <cartella di png>] [--max-kb <KB>]
  --schermate rifà le immagini di sito-plumecms/immagini/ dalle schermate (vedi schermate.js).
  --max-kb    il peso massimo di ogni parte (predefinito 1900: sotto i 2 MB, il limite predefinito di PHP).
Scrive releases/sito/sito-plumecms-<versione>-parte-<k>-di-<n>.zip: si caricano in ordine, dopo il CMS.

Il pacchetto, applicato a un plumeCMS: nome e descrizioni del sito, Stile del sito, header e footer (predefiniti),
menu Principale e Piede, le pagine home, funzionalita, temi, documentazione, installazione, scarica,
domande-frequenti; i file da scaricare in uploads/scaricabili/ (si aprono da /scarica/<nome>/) e le immagini
in uploads/plumecms/. Le altre pagine del sito restano dove sono, fuori dal menu.
"""
import hashlib
import json
import os
import shutil
import subprocess
import re
import sys
import zipfile

QUI = os.path.dirname(os.path.abspath(__file__))
RADICE = os.path.dirname(QUI)
V = open(os.path.join(RADICE, 'cms', 'VERSIONE')).read().strip()
REL = os.path.join(RADICE, 'releases')
IMG = os.path.join(QUI, 'immagini')
U = '/uploads/plumecms/'          # dove finiscono le immagini sul sito
ZIP = f'plumecms-{V}'
ZIP_AGG = f'plumecms-{V}-aggiornamento'
# Dalla 1.2.5 le pagine non scrivono la versione: la prende il sito dall'ultimo zip caricato in Download (serie
# plumecms-ultima). Per pubblicare una versione nuova basta caricare plumecms-X.Y.Z.zip dalla pagina Download.
VU = '{versione:plumecms-ultima}'
SERIE, SERIE_AGG = 'plumecms-ultima', 'plumecms-ultima-aggiornamento'


def errore(msg):
    print('ERRORE: ' + msg)
    sys.exit(1)


# ------------------------------------------------------------------ mattoni dell'editor
def nid(*pezzi):
    """Id stabili (4-12 caratteri a-z0-9): la stessa pagina ricostruita ha gli stessi id, e le versioni si confrontano bene."""
    return 'p' + hashlib.sha1('/'.join(map(str, pezzi)).encode()).hexdigest()[:9]


class Pagina:
    def __init__(self, slug):
        self.slug = slug
        self.sezioni = []

    def sezione(self, colonne, **imp):
        """colonne: elenco di (larghezza in %, [widget]) oppure di [widget] (larghezze uguali)."""
        n = len(self.sezioni)
        figli = []
        for i, c in enumerate(colonne):
            larg, widget = c if isinstance(c, tuple) else (round(100 / len(colonne), 2), c)
            figli.append({'id': nid(self.slug, n, i), 'tipo': 'colonna',
                          'impostazioni': {'larghezza': {'d': larg, 't': 100 if len(colonne) > 2 and larg < 50 else larg, 'm': 100}},
                          'figli': [{'id': nid(self.slug, n, i, k), 'tipo': w[0], 'impostazioni': w[1]} for k, w in enumerate(widget)]})
        self.sezioni.append({'id': nid(self.slug, n), 'tipo': 'sezione', 'impostazioni': imp, 'figli': figli})
        return self

    def albero(self):
        return {'versione': 1, 'elementi': self.sezioni}


def w(tipo, **imp):
    return (tipo, imp)


def spazi(sopra, sotto, m_sopra=None, m_sotto=None):
    return {'spazio_sopra': {'d': sopra, 'm': m_sopra if m_sopra is not None else min(sopra, 48)},
            'spazio_sotto': {'d': sotto, 'm': m_sotto if m_sotto is not None else min(sotto, 48)}}


def titolo(testo, occhiello='', sotto='', livello='h2', centro=False, **imp):
    d = dict(testo=testo, occhiello=occhiello, sottotitolo=sotto, livello=livello, **imp)
    if centro:
        d['allineamento'] = {'d': 'center'}
    return w('titolo', **d)


def ricco(*paragrafi):
    """Paragrafi in HTML semplice (p, ul, strong, a, code): l'editor li ripulisce comunque."""
    return w('testo', contenuto=''.join(p if p.startswith('<') else f'<p>{p}</p>' for p in paragrafi))


def pulsanti(*voci, centro=False):
    d = {'voci': [{'testo': t, 'link': l, 'stile': s, 'nuova': False} for t, l, s in voci]}
    if centro:
        d['allineamento'] = {'d': 'center'}
    return w('pulsanti', **d)


def immagine(nome, alt, didascalia=''):
    return w('immagine', immagine=U + nome, alt=alt, didascalia=didascalia)


def tinta():
    """Una fascia con lo sfondo secondario, per alternare le sezioni."""
    return {'sfondo': 'colore', 'colore_sfondo': 'globale:sfondo-2'}


# ------------------------------------------------------------------ le pagine
def home():
    p = Pagina('home')
    p.sezione([[w('hero', disposizione='copertina',
                  occhiello=f'plumeCMS {VU} · PHP e MySQL',
                  titolo='Il tuo sito, *leggero come una piuma.*',
                  testo='Un CMS con l\'editor visuale a sezioni e colonne, i temi, la SEO e la newsletter già dentro. '
                        'Lo carichi sul server, apri il browser, rispondi a cinque domande. Il resto è il tuo sito.',
                  pulsanti=[{'testo': 'Scarica plumeCMS', 'link': '/scarica/', 'stile': 'viola'},
                            {'testo': 'Guarda cosa sa fare', 'link': '/funzionalita/', 'stile': 'filo'}],
                  # La piuma disegnata da copertina.js, a colori (naturale: il velo colorato la farebbe grigia),
                  # con lo zoom lento che la fa respirare (fermo per chi chiede meno movimento, sito.css).
                  immagine=U + 'copertina.webp', onda_mostra=False, etichetta_onda='', barre=[],
                  foto_trattamento='naturale', foto_effetto='zoom')]], **spazi(64, 72))
    # Il mondo di prima: il problema in cui ci si riconosce.
    p.sezione([(45, [titolo('Volevi un sito. *Ti sei ritrovato a fare il meccanico.*', 'Ti suona familiare?',
                            'Si parte con un\'idea chiara e si finisce la domenica pomeriggio a capire perché un aggiornamento '
                            'ha rotto il modulo dei contatti. Non è colpa tua: è il peso che ti porti dietro.')]),
               (55, [w('elenco_icone', voci=[
                   {'testo': 'Dieci plugin per fare tre cose', 'icona': 'croce-cerchio', 'link': ''},
                   {'testo': 'Un aggiornamento che ne rompe un altro', 'icona': 'croce-cerchio', 'link': ''},
                   {'testo': 'Il tema che costa più del sito', 'icona': 'croce-cerchio', 'link': ''},
                   {'testo': 'La pagina che si apre in sei secondi', 'icona': 'croce-cerchio', 'link': ''},
                   {'testo': 'Un pannello che sembra la cabina di un aereo', 'icona': 'croce-cerchio', 'link': ''}],
                   divisore=True)])],
              allineamento_verticale='center', **tinta(), **spazi(72, 72))
    # La svolta.
    p.sezione([[w('frase', testo='Un sito non dovrebbe pesare più di quello che hai da dire. *plumeCMS nasce da qui.*',
                  autore='Danilo Failli')]], larghezza='stretta', **spazi(72, 40))
    # La strada.
    p.sezione([[titolo('Dallo zip al sito online, *in cinque passi.*', 'La strada', centro=True),
                w('passi', voci=[
                    {'titolo': 'Scarichi lo zip', 'testo': 'Un file solo, poco più di un megabyte. Dentro c\'è tutto: niente da installare a parte.'},
                    {'titolo': 'Lo carichi sul server', 'testo': 'Nella radice del sito o in una cartella, con il gestore dei file del tuo hosting. Basta un hosting con PHP 8.2 e MySQL.'},
                    {'titolo': 'Apri il browser', 'testo': 'L\'installatore ti fa cinque domande: database, nome del sito, il tuo utente. Due minuti, caffè compreso.'},
                    {'titolo': 'Scegli il vestito', 'testo': 'Tre temi pronti: Cosmo, Aria, Bottega. Un clic e il sito cambia aspetto. I tuoi testi restano dove sono.'},
                    {'titolo': 'Scrivi e pubblica', 'testo': 'Trascini sezioni, colonne e widget, guardi l\'anteprima, premi Pubblica. Se sbagli, torni indietro: ogni modifica resta nelle versioni.'}]),
                pulsanti(('Guarda l\'installatore, passo per passo', '/installazione/#passi', 'filo'), centro=True)]],
              **spazi(48, 72))
    p.sezione([[w('numeri', colonne={'d': 4, 't': 2, 'm': 2}, voci=[{'valore': '5', 'etichetta': 'passi per installarlo'}, {'valore': '87', 'etichetta': 'widget nell\'editor'},
                                  {'valore': '3', 'etichetta': 'temi pronti da indossare'}, {'valore': '0', 'etichetta': 'plugin da tenere aggiornati'}])]],
              **tinta(), **spazi(56, 56))
    # Le armi del viaggio: cosa c'è dentro.
    funzioni = [
        ('pennello', 'Editor visuale', 'Sezioni, colonne e 87 widget, con le schede Contenuto, Stile e Avanzate per ogni elemento. Vedi quello che fai mentre lo fai.', '/funzionalita/#editor'),
        ('tavolozza', 'Temi', 'Tre temi pronti e un manuale per disegnarti il tuo. Il tema cambia l\'aspetto, mai i contenuti.', '/temi/'),
        ('storia', 'Versioni', 'Ogni salvataggio lascia una versione: la confronti, la guardi in anteprima, ci torni con un clic.', '/funzionalita/#versioni'),
        ('grafico-su', 'SEO e AEO', 'Sitemap, robots, dati strutturati, llms.txt, IndexNow e Search Console. Per Google e per le intelligenze artificiali.', '/funzionalita/#seo'),
        ('email', 'Newsletter, form e social', 'Iscrizioni con doppia conferma, campagne, flussi automatici, form e popup. E ogni mattina i post pronti per i social.', '/funzionalita/#social'),
        ('scudo-spunta', 'Sicurezza', 'Due fattori, blocchi contro i tentativi, CSP, pacchetti firmati. Messo alla prova con più di 500 attacchi.', '/funzionalita/#sicurezza'),
    ]
    riq = lambda f: w('riquadro_icona', icona=f[0], titolo=f[1], testo=f[2], link={'url': f[3], 'nuova': False}, livello='h3', vista='impilata', allineamento={'d': 'left'})
    p.sezione([[titolo('Tutto quello che serve, *già dentro.*', 'Nello zaino', 'Niente da installare a parte, niente abbonamenti da ricordare.', centro=True)]], **spazi(80, 8))
    p.sezione([[riq(funzioni[0])], [riq(funzioni[1])], [riq(funzioni[2])]], **spazi(24, 0))
    p.sezione([[riq(funzioni[3])], [riq(funzioni[4])], [riq(funzioni[5])]], **spazi(24, 80))
    # Il ritorno.
    p.sezione([[w('invito_libero', icona='razzo', titolo='Il tuo sito può essere online stasera.', livello='h2',
                  testo='Scarica plumeCMS, caricalo sul server e rispondi a cinque domande. Se ti blocchi c\'è il manuale d\'uso, con le schermate. '
                        'Se il manuale non basta, ce ne sono altri due.',
                  pulsante_testo='Scarica plumeCMS', link={'url': '/scarica/', 'nuova': False})]],
              larghezza='stretta', **tinta(), **spazi(72, 72))
    return dict(slug='home', titolo='plumeCMS', struttura=p.albero(),
                seo_titolo='plumeCMS: il CMS leggero con l\'editor visuale',
                seo_descrizione='plumeCMS è un CMS in PHP e MySQL con editor visuale, temi, SEO e newsletter già dentro. Si installa dal browser in cinque passi.')


def funzionalita():
    p = Pagina('funzionalita')
    p.sezione([[titolo('Leggero non vuol dire *vuoto.*', 'Funzionalità',
                       'plumeCMS fa le cose che ti servono davvero, e le fa nello stesso posto. Ecco cosa trovi dentro, con le schermate vere del pannello.',
                       livello='h1', centro=True)]], larghezza='stretta', **spazi(72, 32))
    blocchi = [
        ('editor', 'editor.webp', 'L\'editor visuale: sezioni, colonne, widget',
         ['Costruisci le pagine trascinando sezioni, colonne e widget. Ogni elemento ha le sue tre schede, come negli editor che conosci: <strong>Contenuto</strong>, <strong>Stile</strong> e <strong>Avanzate</strong>.',
          '<ul><li>87 widget: titoli, testi, immagini, pulsanti, gallerie, caroselli, prezzi, domande frequenti, contatori, mappe, orari di apertura, recensioni, eventi e 14 infografiche.</li>'
          '<li>Anteprima per computer, tablet e telefono, con valori diversi per ogni misura.</li>'
          '<li>Colori e caratteri globali: cambi il colore principale e cambia ovunque.</li>'
          '<li>Foto con velo, bianco e nero ed effetti animati, dalla 1.2.2.</li>'
          '<li>Foto leggere: dalla 1.4.0 la foto del telefono si raddrizza, si rimpicciolisce se è enorme, perde i dati nascosti (anche la posizione GPS) e accanto nasce la sua versione WebP, più piccola, per i browser che la leggono.</li></ul>']),
        ('wordpress', 'importa-wp.webp', 'Vieni da WordPress? Porti tutto con te',
         ['In Impostazioni, Importa da WordPress, scrivi l\'indirizzo del vecchio sito, il tuo utente e una password per le applicazioni. plumeCMS guarda tutto il sito e ti fa il report: quanti articoli, pagine, immagini, categorie, tag, menu, cosa diventano qui e cosa resta fuori, con il perché.',
          'Spunti quello che vuoi portare e premi Importa: la barra ti dice a che punto è. I vecchi indirizzi diventano redirect, così i link sparsi per il web e su Google continuano a funzionare.',
          'Tutto entra <strong>in prova</strong>: i visitatori non vedono niente, tu giri il sito nuovo nell\'anteprima. Ti piace? Conferma. Non ti convince? Annulla, e il sito torna com\'era.']),
        ('kit', 'installa-4.webp', 'Si parte con un sito vero, non con una pagina bianca',
         ['All\'ultimo passo l\'installatore ti chiede che sito vuoi fare: personale, attività, blog, portfolio, vuoto, oppure su misura.',
          'Scegli, e plumeCMS parte con la home, le pagine principali e il menu già costruiti, con testi d\'esempio che ti dicono cosa scriverci. Tu cambi le parole e metti le tue foto: il sito c\'è già.']),
        ('su-misura', 'su-misura.webp', 'Il sito su misura: dici che attività è, il sito si compone',
         ['All\'installazione scegli <strong>Su misura</strong> e rispondi a quattro domande: che attività è (un negozio, un ristorante, uno studio, un artigiano, un B&amp;B e altre), dove si trova, cosa offri, quali pagine vuoi.',
          'plumeCMS compone home, pagine, menu e testi pensati per quell\'attività: un ristorante ha il menù e "Prenota un tavolo", un idraulico "Chiedi un preventivo". Con il form dei contatti e la mappa della tua città.',
          'Dove andrà una foto c\'è un disegno che dice cosa fotografare: la vetrina, la sala, le persone. Quando hai le foto vere, in Media premi <strong>Sostituisci</strong> e la foto prende il suo posto in tutte le pagine.']),
        ('tipo-sito', 'tipo-sito.webp', 'Non sei sicuro del tipo di sito? Provali',
         ['Dopo l\'installazione il sito resta in prova: dalla dashboard passi da personale ad attività, a blog, a portfolio con un clic, e guardi come viene. Prima di cambiare, il pannello fa una copia del sito. Le pagine che hai già scritto restano.',
          'Quando hai trovato quello giusto premi <strong>Definitivo</strong>: il riquadro sparisce e da lì in poi cambi tutto con l\'editor, pagina per pagina.']),
        ('primi-passi', 'primi-passi.webp', 'Primi passi: cosa manca perché il sito sia pronto',
         ['Nella dashboard sei caselle: nome e logo, i tuoi colori, le email che partono, il cron, la prima pagina scritta, il tipo di sito deciso (in un sito su misura una in più: le tue foto al posto di quelle finte). Ognuna con il link alla pagina giusta.',
          'Si spuntano da sole quando le hai fatte. Tutte verdi? Le nascondi e la dashboard torna pulita.']),
        ('sezioni', 'sezioni.webp', 'Sezioni pronte: trenta modi di riempire una pagina',
         ['Nell\'editor c\'è la linguetta Sezioni: prezzi, testimonianze, chi siamo, servizi, numeri, domande frequenti, contatti, inviti. Trenta sezioni già impaginate, in nove gruppi, ognuna con la sua anteprima.',
          'Un clic e la sezione entra nella pagina, oppure la trascini dove vuoi. Da lì è una sezione come le altre: cambi testi, colori e widget. E segue i colori del tuo sito e del tema.']),
        ('aperture', 'aperture.webp', 'Undici aperture fra cui scegliere',
         ['La prima cosa che si vede di una pagina è la sua apertura. In plumeCMS la scegli con una tendina, fra undici disposizioni: testo e foto affiancati, tutto al centro, foto a tutto sfondo, divisa a metà, da rivista, in una scheda con il ritratto rotondo, solo parole, a mosaico, con la foto inclinata, a fascia bassa per le pagine interne, con il colore tagliato in diagonale.',
          'Cambi disposizione e i testi restano dove sono: provi, guardi, scegli. Tutte seguono i colori del tuo sito e dei temi, e si leggono bene anche al telefono.']),
        ('stile', 'stile.webp', 'Stile del sito: colori e caratteri in un posto solo',
         ['Colori, caratteri, titoli, pulsanti, campi dei moduli, larghezza della pagina: tutto in una pagina sola, con l\'anteprima accanto.',
          'Le pagine usano i colori globali, quindi una modifica qui arriva su tutto il sito. Senza cercare il blu sbagliato pagina per pagina.']),
        ('aspetti', 'aspetti.webp', 'Il pannello con l\'aspetto che preferisci',
         ['Il pannello parte chiaro, con l\'aspetto Aria. Se ti trovi meglio con altro, in Il mio accesso scegli fra quattro: Aria (chiaro, bianco e blu), Bottega (caldo, carta crema e terracotta), Cosmo (scuro, notte blu e viola), Lavagna (scuro, verde ardesia e gesso).',
          'Ogni utente sceglie il suo, e i testi restano leggibili in tutti e quattro: i contrasti sono misurati, non scelti a occhio.']),
        ('header', 'modelli.webp', 'Header, footer e menu fatti con l\'editor',
         ['Disegni testata e piede con lo stesso editor delle pagine. Con le condizioni decidi dove vale ciascuno: tutto il sito, una categoria, una pagina sola.',
          'I menu si costruiscono trascinando le voci, fino a tre livelli, con posizioni per la testata, il piede e il telefono.']),
        ('versioni', 'versioni.webp', 'Versioni di ogni modifica',
         ['Ogni salvataggio di pagine, header, footer, menu e stile lascia una versione. La confronti con quella di prima, in parole leggibili, la guardi in anteprima e, se serve, ci torni con un clic.',
          'Lavori tranquillo: il passo indietro c\'è sempre.']),
        ('seo', 'seo.webp', 'SEO e AEO: per Google e per le intelligenze artificiali',
         ['Sitemap, robots.txt, dati strutturati (articoli, domande frequenti, briciole di pane), titoli e descrizioni per ogni pagina, redirect e pagine 404 sotto controllo.',
          'In più quello che serve alle risposte delle intelligenze artificiali: <code>llms.txt</code>, riassunti in testa, risposte brevi. E gli avvisi ai motori con IndexNow e Search Console.']),
        ('newsletter', 'flussi.webp', 'Newsletter, form e popup',
         ['Iscrizioni con doppia conferma, liste, campagne programmate, flussi automatici (benvenuto, promemoria, bivi), aperture e clic contati. I form li costruisci nel pannello, e per ognuno scegli chi riceve l\'email a ogni compilazione; i popup decidi tu quando e dove.',
          'Le email partono dal tuo server, a giri, senza servizi esterni da pagare.']),
        ('social', 'social.webp', 'I social, pronti ogni mattina',
         ['Il giorno in cui esce un articolo ti arriva un\'email: <strong>"Oggi sul sito"</strong>. Dentro c\'è tutto per pubblicarlo: le due immagini della misura giusta, 1200x630 per LinkedIn e Facebook e 1080x1350 per Instagram, e il testo pronto per ogni canale, con il link e i tuoi hashtag. Copi, incolli, pubblichi.',
          'Il testo lo scrivi una volta, nella scheda dell\'articolo; se vuoi, uno diverso per LinkedIn, per Facebook e Instagram, per WhatsApp. Le immagini le ritaglia plumeCMS dalla foto in evidenza.',
          'Per un articolo importante c\'è il <strong>calendario</strong>: prepari i post dei giorni dopo, e ogni mattina arriva quello del giorno, con il link al punto giusto della pagina.']),
        ('widget-attivita', 'widget-attivita.webp', 'I widget per chi ha un\'attività',
         ['<strong>Orari di apertura</strong> che dicono da soli "Aperto ora, chiude alle 19:00" o "Chiuso, apre domani alle 9:00", con le ferie e i giorni di chiusura. Google li legge.',
          'Sul telefono, la <strong>barra dei contatti</strong> fissa in fondo allo schermo: Chiama, WhatsApp, Indicazioni, Scrivici. Le <strong>recensioni</strong> con il voto medio e il pulsante per lasciarne una.',
          'E poi <strong>Prima e dopo</strong>, due foto con una linea da trascinare; la <strong>Squadra</strong>; lo <strong>Slider</strong>; gli <strong>Eventi</strong>, che spariscono da soli quando sono passati.']),
        ('addon', 'addon.webp', 'Gli addon: accendi solo quello che ti serve',
         ['In Impostazioni, <strong>Addon</strong>: otto funzioni in più, ognuna con il suo pulsante Accendi. Acceso, l\'addon mette le sue voci nel menu e i suoi widget nell\'editor. Spento, sparisce dal sito, e i suoi dati restano lì per quando lo riaccendi.',
          '<ul><li><strong>Prenotazioni e appuntamenti</strong>: il calendario con i posti davvero liberi, la conferma per email e il promemoria il giorno prima.</li>'
          '<li><strong>Eventi con iscrizione</strong>: posti limitati e lista d\'attesa che scorre da sola.</li>'
          '<li><strong>Raccolta recensioni</strong>: "Com\'è andata?" dopo il servizio, e le recensioni che approvi vanno sul sito.</li>'
          '<li><strong>Vetrina prodotti</strong>: catalogo, varianti, carrello e ordini, con bonifico, pagamento alla consegna o il tuo link PayPal o Stripe.</li>'
          '<li><strong>Statistiche senza cookie</strong>: visite e fonti, senza banner e senza Google Analytics.</li>'
          '<li><strong>Area riservata</strong> e <strong>Corsi online</strong>: pagine e file per chi ha un account, lezioni, avanzamento e certificato.</li>'
          '<li><strong>Copia del sito altrove</strong>: ogni notte la copia del sito su FTP o WebDAV.</li></ul>',
          'Sono in <strong>beta</strong> dalla 2.0.0: provati a fondo, aspettano i siti veri. Se trovi qualcosa che non va, dimmelo.']),
        ('fine-articolo', 'fine-articolo.webp', 'Un riquadro in fondo a ogni articolo',
         ['Chi arriva alla fine di un articolo è il lettore più interessato che hai. In Articoli, la linguetta <strong>Aggiungi sempre alla fine</strong>: prepari una volta sola un riquadro che esce sotto ogni articolo, anche sotto quelli già pubblicati. Un invito alla newsletter, un contatto, una prenotazione, un libro da consigliare.',
          'Lo componi con l\'editor visuale, come una pagina: titoli, testi, foto, pulsanti. Esce quando premi Pubblica, con l\'ombra, il bordo e i colori del tuo sito e del tema.',
          'Lo spegni e lo riaccendi con un clic, senza perdere il lavoro. Ogni modifica lascia una versione, e nella sua pagina vedi l\'anteprima sotto l\'ultimo articolo prima di pubblicare.']),
        ('firma', 'firma.webp', 'La tua firma in fondo al sito',
         ['Sotto il piede di ogni pagina c\'è una riga piccola: <strong>Un nuovo sito plumeCMS</strong>, con il link al sito ufficiale. È tua: in Impostazioni > Sito pubblico cambi il testo e l\'indirizzo, oppure la spegni con una spunta.',
          'Il testo resta testo: niente codice, niente sorprese. E nella dashboard trovi sempre il link al sito ufficiale, per le versioni nuove e i manuali.']),
        ('download', 'download.webp', 'Download manager: i tuoi file, a portata di clic',
         ['Manuali, ebook, listini, programmi: li carichi dalla pagina Download del pannello, con titolo, descrizione e versione. Ogni file ha il suo indirizzo da condividere.',
          'Con la versione nel nome (guida-1.2.pdf) il file entra in una serie: un indirizzo porta sempre alla versione più recente, e nelle pagine il numero si scrive da solo. Carichi la versione nuova e tutto il sito la segue. E vedi quante volte ogni file è stato scaricato: le persone sì, i robot no.',
          'Nelle pagine li mostri con il widget Download, a elenco o a schede, con icona, peso e pulsante. Come nella <a href="/scarica/">pagina Scarica</a> di questo sito.',
          'Dalla 2.0.0 un file può essere <strong>riservato</strong>: non ha un indirizzo pubblico e arriva per email, con un link personale, a chi compila un form e conferma la sua email. L\'ebook in cambio dell\'iscrizione, senza servizi esterni.']),
        ('sicurezza', 'sicurezza.webp', 'Sicurezza, senza doverci pensare ogni giorno',
         ['Password robuste, accesso a due fattori, blocchi automatici contro chi prova a indovinare, sessioni che scadono, Content Security Policy, registro degli eventi.',
          'Prima di uscire, plumeCMS è stato messo alla prova con più di 500 attacchi, addon compresi: tutti respinti. La pagina Sicurezza ti dice in verde e in rosso come sta il tuo sito.']),
        ('aggiornamenti', 'aggiornamenti.webp', 'Aggiornamenti dal pannello, con la rete sotto',
         ['Un aggiornamento è uno zip: lo carichi da Impostazioni, Aggiornamenti, e premi Applica. Prima di toccare qualunque cosa il pannello fa una copia di sicurezza del database e dei file.',
          'Se qualcosa va storto, rimette tutto com\'era da solo. Puoi anche programmare l\'aggiornamento per la notte.',
          'Dalla 1.3.0 non devi nemmeno cercarlo: quando esce una versione nuova la dashboard te lo dice, e con Scarica e prepara lo zip arriva da solo, controllato, pronto da applicare.']),
        ('server', 'controlla-server.webp', 'Controlla il server: il tuo hosting va bene?',
         ['In Impostazioni, Controlla il server: il pannello prova tutto quello che serve a plumeCMS. Versione di PHP, estensioni, HTTPS, cron, email, cartelle, protezioni dei file. Non cambia niente: guarda e ti dice.',
          'Ogni voce ha il suo segno: a posto, da sapere, da sistemare, con la spiegazione in parole semplici. Con il pulsante accanto mandi un\'email di prova e vedi se arriva.']),
        ('copia', 'copia.webp', 'La copia del tuo sito, con un clic',
         ['In Impostazioni, Copia del sito: Prepara la copia mette in uno zip il database, le immagini, i temi e i moduli. La scarichi e la tieni al sicuro.',
          'Se un giorno serve tornare indietro, o spostare il sito su un altro hosting, Ripristina rimette tutto com\'era. Prima, per sicurezza, fa da sola una copia del sito di adesso.']),
    ]
    for i, (ancora, img, tit, testo) in enumerate(blocchi):
        foto = [immagine(img, 'plumeCMS: ' + tit)]
        parole = [titolo(tit, livello='h2'), ricco(*testo)]
        colonne = [(55, foto), (45, parole)] if i % 2 == 0 else [(45, parole), (55, foto)]
        p.sezione(colonne, allineamento_verticale='center', ancora=ancora, **(tinta() if i % 2 else {}), **spazi(64, 64))
    p.sezione([[w('invito_libero', icona='download', titolo='Provalo sul tuo server.', livello='h2',
                  testo='Il modo migliore per capire un CMS è usarlo. Ci vogliono cinque minuti.',
                  pulsante_testo='Scarica plumeCMS', link={'url': '/scarica/', 'nuova': False})]], larghezza='stretta', **spazi(72, 80))
    return dict(slug='funzionalita', titolo='Funzionalità', struttura=p.albero(),
                seo_titolo='Funzionalità di plumeCMS: editor, temi, versioni, SEO, newsletter',
                seo_descrizione='Cosa c\'è dentro plumeCMS: editor visuale con 87 widget, gli addon (prenotazioni, eventi, negozio, statistiche, area riservata, corsi), import da WordPress, stile globale, header e footer, versioni, SEO e AEO, newsletter, sicurezza, aggiornamenti dal pannello.')


def temi():
    p = Pagina('temi')
    p.sezione([[titolo('Cambia vestito al sito, *con un clic.*', 'Temi',
                       'Un tema cambia colori, caratteri, pulsanti, header e footer. Pagine, articoli, menu e immagini restano dove sono. '
                       'Prima lo guardi in anteprima, solo tu; poi decidi.', livello='h1', centro=True)]], larghezza='stretta', **spazi(72, 32))
    t = [('tema-cosmo.webp', 'Cosmo', 'Notte profonda, viola e ciano, titoli che brillano. L\'aspetto di nascita di plumeCMS.'),
         ('tema-aria.webp', 'Aria', 'Chiaro, pulito, un solo blu. Per chi vuole che parlino i contenuti.'),
         ('tema-bottega.webp', 'Bottega', 'Carta crema, titoli con le grazie, terracotta e salvia. Caldo come un laboratorio artigiano.')]
    p.sezione([[immagine(img, 'Il tema ' + n + ' di plumeCMS'), titolo(n, livello='h3'), ricco(d)] for img, n, d in t], **spazi(24, 72))
    p.sezione([(50, [titolo('Fatti il tuo tema.', 'Per chi disegna',
                             'Un tema è una cartella con qualche file: i colori in un JSON, header e footer, un po\' di CSS. Il PHP è facoltativo.'),
                     ricco('Il manuale dei temi ti accompagna dal foglio bianco al tema attivo, file per file. Alla fine c\'è un tema completo, '
                           '<strong>Lavagna</strong>: verde ardesia, testo color gesso. Lo scarichi, lo smonti, lo rifai tuo.',
                           'Lavagna contiene un piccolo file PHP, quindi il pannello chiede un permesso: in <strong>Impostazioni &gt; Sicurezza</strong>, scheda Impostazioni, '
                           'riquadro in fondo, scrivi la tua password e premi <strong>Apri la finestra per un\'ora</strong>. Poi in <strong>Editor &gt; Temi</strong> carica lo zip.'),
                     pulsanti(('Manuale dei temi (PDF)', '/scarica/manuale-temi/', 'viola'), ('Tema Lavagna (zip)', '/scarica/tema-lavagna/', 'filo'))]),
               (50, [immagine('temi.webp', 'La pagina Temi del pannello di plumeCMS')])],
              allineamento_verticale='center', **tinta(), **spazi(72, 72))
    return dict(slug='temi', titolo='Temi', struttura=p.albero(),
                seo_titolo='Temi di plumeCMS: Cosmo, Aria, Bottega e il tuo',
                seo_descrizione='I temi di plumeCMS cambiano l\'aspetto del sito senza toccare i contenuti. Tre temi pronti e un manuale per costruire il tuo.')


def documentazione(pagine_pdf):
    p = Pagina('documentazione')
    p.sezione([[titolo('I manuali, *da leggere anche offline.*', 'Documentazione',
                       'Due manuali in PDF. Uno per usare plumeCMS, con le schermate vere (in due file: il secondo è per gli addon); uno per costruirti il tuo tema.',
                       livello='h1', centro=True)]], larghezza='stretta', **spazi(72, 24))
    man = [('lista-spunte', 'Manuale d\'uso', pagine_pdf['manuale-uso'], 'manuale-uso',
            'Come si fa ogni cosa: articoli, pagine con l\'editor, menu, form, newsletter, sicurezza, aggiornamenti. Ogni capitolo ha un esempio vero, le schermate con i passi numerati e una checklist.'),
           ('puzzle', 'Manuale d\'uso: gli addon', pagine_pdf['manuale-uso-addon'], 'manuale-uso-addon',
            'La seconda parte del manuale d\'uso (dalla 2.0.0): prenotazioni, eventi, recensioni, negozio, statistiche, area riservata, corsi, copia del sito altrove. Un capitolo per addon, con l\'esempio e i limiti della beta.'),
           ('tavolozza', 'Manuale dei temi', pagine_pdf['manuale-temi'], 'manuale-temi',
            'Come si costruisce un tema da zero: cartella, colori, header e footer, CSS, infografiche, PHP, prove, distribuzione. Con il tema d\'esempio Lavagna.')]
    p.sezione([[w('invito_libero', icona=ic, titolo=n, livello='h2', testo=f'{d} {pag} pagine.', pulsante_testo='Scarica il PDF',
                  link={'url': f'/scarica/{f}/', 'nuova': False})] for ic, n, pag, f, d in man], **spazi(24, 56))
    p.sezione([(50, [titolo('Cosa c\'è nel manuale d\'uso', livello='h3'),
                     w('elenco_icone', voci=[{'testo': t, 'icona': 'spunta', 'link': ''} for t in
                                             ['Il pannello in cinque minuti', 'Il primo articolo, e come programmarlo', 'Una pagina con l\'editor, dall\'inizio alla fine',
                                              'Menu, header, footer, stile e temi', 'Form, popup, newsletter, download', 'SEO, utenti, email, cron, sicurezza',
                                              'Aggiornare, copiare il sito, portare qui WordPress', 'Gli otto addon, uno per capitolo', 'La checklist prima di andare online']])]),
               (50, [titolo('Cosa c\'è nel manuale dei temi', livello='h3'),
                     w('elenco_icone', voci=[{'testo': t, 'icona': 'spunta', 'link': ''} for t in
                                             ['Il percorso, dal foglio bianco al tema attivo', 'tema.json, stile.json, modelli.json',
                                              'Le variabili CSS e le classi del sito', 'I colori delle infografiche',
                                              'Il PHP del tema: filtri, eventi, pagine ridisegnate', 'Come provarlo, firmarlo e distribuirlo',
                                              'Lavagna, il tema d\'esempio completo', 'Errori comuni e come uscirne']])])],
              **tinta(), **spazi(64, 64))
    p.sezione([[ricco('I due manuali sono anche dentro lo zip di plumeCMS, nella cartella <code>docs/</code>.')]],
              larghezza='stretta', **spazi(48, 80))
    return dict(slug='documentazione', titolo='Documentazione', struttura=p.albero(),
                seo_titolo='Documentazione di plumeCMS: manuale d\'uso e manuale dei temi in PDF',
                seo_descrizione='Scarica in PDF il manuale d\'uso di plumeCMS (con le schermate) e il manuale per costruire i temi.')


REQUISITI = [{'celle': 'Cosa | Serve'}, {'celle': 'PHP | 8.2 o più nuovo, con PDO MySQL, mbstring, openssl, json'},
             {'celle': 'Database | MySQL 8 oppure MariaDB 10.6 o più nuovo'}, {'celle': 'Server web | Apache con mod_rewrite (il .htaccess è già pronto) oppure Nginx'},
             {'celle': 'Consigliati | GD o Imagick per le immagini, ZipArchive per aggiornamenti e temi'},
             {'celle': 'Spazio | pochi megabyte per il CMS, più le tue immagini'}]


# I cinque passi dell'installatore, con la schermata vera di ognuno (installatore.js): la didascalia del carosello e
# il testo lungo dei passi numerati dicono le stesse cose, il primo in breve.
PASSI_INSTALLA = [
    ('Requisiti', 'Controlla il server: PHP, database, cartelle. Tutto OK? Avanti.',
     'Apri l\'indirizzo del sito nel browser: compare l\'installatore, con una lista di voci verdi. Se una è rossa, la pagina ti dice cosa manca e cosa chiedere al tuo hosting.'),
    ('Database', 'Server, nome, utente e password del database che hai creato.',
     'Scrivi host (di solito localhost), nome, utente e password del database. Il prefisso delle tabelle lascialo com\'è, a meno che nello stesso database ci sia già un altro sito.'),
    ('Sito e amministratore', 'Il nome del sito, l\'indirizzo (lo trova da solo) e il tuo utente.',
     'Nome del sito, una descrizione in una riga, l\'indirizzo e il tuo utente con una password di almeno 12 caratteri. Annotala: è la chiave di casa.'),
    ('Installazione', 'Scegli che sito vuoi fare, poi Installa adesso.',
     'Scegli il punto di partenza: personale, attività, blog, portfolio, vuoto, oppure su misura (quattro domande sulla tua attività e il sito si compone da solo). L\'installatore ti dice cosa farà (tabelle, amministratore, pagine, menu, header e footer): premi Installa adesso e aspetti qualche secondo.'),
    ('Fine', 'Il sito è pronto. Entri nel pannello e cominci.',
     'Trovi il link al pannello e quello al sito, e le due righe per i compiti pianificati. Entri, scegli un tema e il sito è tuo.'),
]


def installazione():
    """La pagina "Installalo in 5 minuti": invece di promettere, mostra l'installatore vero. In alto cosa serve prima
    di cominciare e il carosello delle cinque schermate, che scorre da solo (fermo per chi chiede meno movimento:
    widget.js non avvia lo scorrimento con prefers-reduced-motion); sotto i passi spiegati e i dettagli."""
    p = Pagina('installazione')
    p.sezione([[titolo('Installalo in *5 minuti.*', 'Installazione',
                       'Niente da configurare a mano: carichi lo zip, apri il browser e l\'installatore ti accompagna in cinque passi. '
                       'Eccoli, con le schermate vere.', livello='h1', centro=True)]],
              larghezza='stretta', **spazi(72, 24))
    prima = [('nuvola', 'Un hosting con PHP 8.2 e MySQL', 'Va bene un normale hosting condiviso con cPanel. MySQL 8 oppure MariaDB 10.6.'),
             ('database', 'I dati del database', 'Nome, utente e password. Li crei dal pannello del tuo hosting, in cPanel alla voce Database MySQL.'),
             ('upload', 'Lo zip di plumeCMS, caricato', 'Estrai lo zip e carica il contenuto nella cartella del sito, di solito public_html. Compreso il file .htaccess.')]
    p.sezione([(40, [titolo('Prima di cominciare', 'Ti servono tre cose', livello='h2'),
                     *[w('riquadro_icona', icona=ic, titolo=t, testo=d, livello='h3', vista='impilata', allineamento={'d': 'left'}) for ic, t, d in prima],
                     pulsanti(('Scarica plumeCMS', '/scarica/', 'viola'))]),
               (60, [w('carosello', immagini=[{'immagine': f'{U}installa-{k}.webp', 'alt': f'Installatore di plumeCMS, passo {k}: {n}',
                                               'didascalia': f'{k}. {n}. {breve}', 'link': ''} for k, (n, breve, _) in enumerate(PASSI_INSTALLA, start=1)],
                       per_vista={'d': 1, 't': 1, 'm': 1}, autoplay=True, intervallo=4500, pausa_hover=True, ciclo=True,
                       frecce=True, pallini=True, proporzioni='4/3', didascalia='sotto', clic='lightbox', effetto_hover='',
                       frecce_colore='globale:primario', frecce_sfondo='#ffffff', pallini_attivo='globale:primario')])],
              allineamento_verticale='center', ancora='passi', **spazi(32, 64))
    p.sezione([[titolo('I cinque passi, uno per uno', livello='h2', centro=True),
                w('passi', voci=[{'titolo': n, 'testo': lungo} for n, _, lungo in PASSI_INSTALLA])]],
              **tinta(), **spazi(64, 64))
    p.sezione([[titolo('I requisiti nel dettaglio', livello='h2'), w('tabella', intestazione=True, righe=REQUISITI),
                ricco('Il primo passo dell\'installatore controlla tutto questo da solo: se manca qualcosa, te lo dice prima di cominciare.')]],
              larghezza='stretta', **spazi(64, 32))
    p.sezione([(50, [titolo('Nella radice o in una cartella', livello='h3'),
                     ricco('plumeCMS funziona nella radice del dominio (<code>https://tuosito.it</code>) e in una cartella (<code>https://tuosito.it/nuovo</code>). '
                           'Lo capisce da solo: all\'installazione scrivi l\'indirizzo completo, con la cartella se c\'è.')]),
               (50, [titolo('I due compiti pianificati', livello='h3'),
                     ricco('Per newsletter, aggiornamenti programmati e avvisi ai motori servono due righe nei <strong>Cron Job</strong> del tuo hosting:',
                           '<ul><li><code>cron-daily.php</code> una volta al giorno, per esempio alle 6.</li><li><code>cron-newsletter.php</code> ogni 5 minuti.</li></ul>',
                           'Il percorso esatto te lo mostra l\'installatore all\'ultimo passo.')])], **spazi(64, 32))
    p.sezione([[titolo('Dopo l\'installazione', livello='h3'),
                w('elenco_icone', voci=[{'testo': t, 'icona': 'freccia-destra', 'link': ''} for t in [
                    'Nella dashboard trovi i Primi passi: sei caselle che si spuntano da sole quando il sito è pronto.',
                    'Il tipo di sito resta in prova: dalla dashboard lo cambi con un clic, finché non premi Definitivo.',
                    'In Impostazioni, Controlla il server guarda se all\'hosting manca qualcosa.',
                    'In Impostazioni, Sicurezza togli la cartella install/ con il pulsante apposito.',
                    'In Impostazioni, Configurazione email imposta il server di posta (SMTP), se il tuo hosting lo chiede.',
                    'In Editor, Temi guarda i tre temi in anteprima e scegli il vestito.',
                    'In Editor, Stile del sito metti i tuoi colori, il logo e l\'icona del sito.']]),
                pulsanti(('Manuale d\'uso (PDF)', '/scarica/manuale-uso/', 'filo'), ('Scarica plumeCMS', '/scarica/', 'viola'))]],
              larghezza='stretta', **spazi(32, 80))
    return dict(slug='installazione', titolo='Installazione', struttura=p.albero(),
                seo_titolo='Installa plumeCMS in 5 minuti: i cinque passi dell\'installatore',
                seo_descrizione='Cosa serve, le schermate vere dei cinque passi dell\'installatore, l\'installazione in una cartella e i compiti pianificati di plumeCMS.')


def scarica(note):
    p = Pagina('scarica')
    p.sezione([[titolo(f'Scarica plumeCMS *{VU}*', 'Download',
                       'Scegli lo zip giusto: quello completo per un sito nuovo, quello di aggiornamento se plumeCMS ce l\'hai già.',
                       livello='h1', centro=True)]], larghezza='stretta', **spazi(72, 24))
    p.sezione([[w('invito_libero', icona='download', titolo='Sito nuovo', livello='h2', nastro='Per cominciare',
                  testo='Lo zip completo: lo estrai, lo carichi sul server e apri il browser. L\'installatore fa il resto in cinque passi.',
                  pulsante_testo=f'Scarica plumeCMS {VU}', link={'url': f'/scarica/{SERIE}/', 'nuova': False})],
               [w('invito_libero', icona='freccia-ricarica', titolo='Hai già plumeCMS', livello='h2',
                  testo='Il pacchetto di aggiornamento: lo carichi da Impostazioni, Aggiornamenti e premi Applica. Prima di cambiare qualcosa il pannello fa una copia di sicurezza.',
                  pulsante_testo='Scarica l\'aggiornamento', link={'url': f'/scarica/{SERIE_AGG}/', 'nuova': False})]], **spazi(24, 64))
    # Tutti i file, con il widget Download (1.2.4): titoli e descrizioni scritti qui, peso e tipo dal download manager.
    p.sezione([[titolo('Tutti i file', 'Da scaricare', 'Il CMS, i manuali e il tema d\'esempio. Ogni file ha il suo indirizzo: puoi condividerlo.', centro=True),
                w('download', origine='scelti', disposizione='elenco', mostra_versione=False, voci=[
                    {'file': SERIE, 'titolo': 'plumeCMS {versione}', 'descrizione': 'Lo zip completo per un sito nuovo: estrai, carica sul server, apri il browser.'},
                    {'file': SERIE_AGG, 'titolo': 'plumeCMS {versione}, aggiornamento', 'descrizione': 'Per chi ha già plumeCMS: si carica da Impostazioni, Aggiornamenti.'},
                    {'file': 'manuale-uso', 'titolo': 'Manuale d\'uso', 'descrizione': 'Come si fa ogni cosa, con esempi, schermate e checklist.'},
                    {'file': 'manuale-uso-addon', 'titolo': 'Manuale d\'uso: gli addon', 'descrizione': 'La seconda parte del manuale: un capitolo per ognuno degli otto addon.'},
                    {'file': 'manuale-temi', 'titolo': 'Manuale dei temi', 'descrizione': 'Come si costruisce un tema da zero.'},
                    {'file': 'tema-lavagna', 'titolo': 'Tema Lavagna', 'descrizione': 'Il tema d\'esempio del manuale: verde ardesia e testo color gesso.'}])]],
              larghezza='stretta', **spazi(16, 64))
    p.sezione([(50, [titolo('Cosa serve', livello='h2'), w('tabella', intestazione=True, righe=REQUISITI),
                     pulsanti(('Guida all\'installazione', '/installazione/', 'filo'))]),
               (50, [titolo(f'Novità', livello='h2'), ricco(note)])], **tinta(), **spazi(64, 64))
    p.sezione([[titolo('Software libero', livello='h3'),
                ricco('plumeCMS si scarica e si usa senza pagare, con la licenza <a href="https://www.gnu.org/licenses/gpl-3.0.html">GNU GPL versione 3</a>: '
                      'per tutti i siti che vuoi, da modificare come ti serve. Chi lo ridistribuisce lo fa con la stessa licenza. '
                      'Il testo completo è nel file LICENSE, dentro lo zip.')]],
              larghezza='stretta', **spazi(56, 0))
    p.sezione([[titolo('Un consiglio, prima di aggiornare', livello='h3'),
                ricco('Il pacchetto di aggiornamento è fatto per i siti nati con lo zip di plumeCMS. Se il tuo sito ha moduli suoi, '
                      'cioè pezzi di codice scritti apposta per lui, l\'aggiornamento va preparato insieme a quei moduli.')]],
              larghezza='stretta', **spazi(56, 80))
    return dict(slug='scarica', titolo='Scarica', struttura=p.albero(),
                seo_titolo=f'Scarica plumeCMS {VU}',
                seo_descrizione=f'Scarica plumeCMS {VU}: lo zip completo per un sito nuovo o il pacchetto di aggiornamento per chi lo usa già.')


def faq():
    p = Pagina('domande-frequenti')
    p.sezione([[titolo('Domande frequenti', 'Prima di partire', 'Le domande che arrivano più spesso, con risposte brevi.', livello='h1', centro=True)]],
              larghezza='stretta', **spazi(72, 24))
    voci = [
        ('Quanto costa? A quali condizioni lo uso?', 'Niente: plumeCMS è software libero, con licenza GNU GPL versione 3. Lo usi per tutti i siti che vuoi, tuoi o dei tuoi clienti, lo modifichi e lo ridistribuisci. Una sola regola: chi lo ridistribuisce, anche modificato, lo fa con la stessa licenza. I tuoi siti, i contenuti e i temi che disegni restano tuoi.'),
        ('Vengo da WordPress. Devo rifare tutto?', 'No. In Impostazioni, Importa da WordPress, scrivi l\'indirizzo del sito, il tuo utente e una password per le applicazioni (la crei in WordPress, nel tuo profilo). plumeCMS porta articoli, pagine, immagini, categorie, tag, menu e titoli SEO, e mette i redirect dai vecchi indirizzi. Prima guardi tutto in prova, poi confermi. La grafica di WordPress e i plugin non si portano: la rifai con l\'editor e i temi.'),
        ('Devo saper programmare?', 'No. Installi dal browser, costruisci le pagine trascinando i widget, cambi colori e caratteri da una pagina sola. Il codice serve solo se vuoi costruire un tema o un modulo tuo.'),
        ('Devo partire da una pagina bianca?', 'No. All\'installazione scegli che sito vuoi fare (personale, attività, blog, portfolio) e plumeCMS parte con la home, le pagine e il menu già fatti. Poi, nell\'editor, la linguetta Sezioni ti dà trenta sezioni pronte da aggiungere con un clic.'),
        ('Che hosting serve?', 'Un hosting con PHP 8.2 o più nuovo e MySQL 8 (o MariaDB 10.6). Va bene un normale hosting condiviso con cPanel: non servono server dedicati.'),
        ('Posso installarlo in una cartella del mio dominio?', 'Sì. All\'installazione scrivi l\'indirizzo con la cartella, per esempio https://tuosito.it/nuovo, e plumeCMS sistema da solo link e percorsi.'),
        ('Se cambio tema, perdo le pagine?', 'No. Il tema cambia l\'aspetto: colori, caratteri, header e footer. Pagine, articoli, menu e immagini restano come sono. E lo stile di prima resta nelle Versioni.'),
        ('Se un aggiornamento va storto?', 'Prima di applicarlo il pannello fa una copia del database e dei file che cambia. Se un passo fallisce, rimette tutto com\'era da solo.'),
        ('Ho sbagliato una modifica nell\'editor. Si torna indietro?', 'Sì. Ogni salvataggio lascia una versione: la confronti, la guardi in anteprima e la ripristini con un clic.'),
        ('Funziona con le intelligenze artificiali?', 'plumeCMS prepara il sito anche per le risposte delle intelligenze artificiali: llms.txt, dati strutturati, riassunti e risposte brevi in testa agli articoli.'),
        ('Chi può entrare nel pannello?', 'Gli utenti che crei tu, con due ruoli: amministratore (tutto) e redattore (contenuti, immagini, categorie). Per chi vuole c\'è l\'accesso a due fattori.'),
        ('Mi sono chiuso fuori. E adesso?', 'Se hai dimenticato la password, c\'è il link per reimpostarla. Se ti blocca l\'accesso a due fattori o la lista degli indirizzi permessi, il manuale d\'uso (capitolo 27) dice la riga da aggiungere al file di configurazione per rientrare.'),
        ('Carico un tema e il pannello dice che contiene codice PHP. Che faccio?', 'È una protezione: un tema con PHP esegue codice sul tuo server, quindi serve un permesso. In Impostazioni > Sicurezza, scheda Impostazioni, riquadro in fondo, scrivi la tua password e premi "Apri la finestra per un\'ora". Poi torna in Editor > Temi e carica di nuovo lo zip. Fallo solo con temi di cui ti fidi.'),
        ('Come pubblico la versione nuova di un file da scaricare?', 'Dalla pagina Download del pannello carichi il file nuovo. Se il nome contiene la versione (guida-1.3.pdf), l\'indirizzo "ultima" e le pagine che la citano passano da soli alla versione nuova.'),
        ('Mi aiuta anche con i social?', 'Sì. Scrivi il testo nel riquadro Social dell\'articolo e il giorno in cui esce ti arriva un\'email con il testo pronto per ogni canale, il link, i tuoi hashtag e le due immagini della misura giusta. Per i giorni dopo c\'è il calendario dei post. Pubblicare resta a te: plumeCMS non entra nei tuoi account.'),
        ('Posso mettere lo stesso invito sotto tutti gli articoli?', 'Sì. In Articoli premi la linguetta Aggiungi sempre alla fine e componi il riquadro con l\'editor: un invito alla newsletter, un pulsante per prenotare, un contatto. Quando premi Pubblica esce in fondo a ogni articolo, anche a quelli già online. Se lo spegni non perdi niente: con Accendi torna com\'era.'),
        ('Non so da dove partire. plumeCMS mi aiuta a fare il sito?', 'Sì. Quando lo installi scegli Su misura e rispondi a quattro domande: che attività è, dove si trova, cosa offri, quali pagine vuoi. Il sito si compone da solo, con i testi pensati per la tua attività e delle foto finte che dicono cosa fotografare. Le cambi con le tue in Media, con il pulsante Sostituisci. Le domande si riaprono dalla dashboard finché il sito è in prova.'),
        ('In fondo al mio sito c\'è scritto "Un nuovo sito plumeCMS". Si può cambiare?', 'Sì. In Impostazioni > Sito pubblico, riquadro La frase in fondo al sito, cambi il testo e l\'indirizzo del link, oppure togli la spunta e la frase non compare più.'),
        ('Posso far arrivare le richieste di un form a un\'altra persona?', 'Sì. Apri il form nel pannello e, nel riquadro Dopo l\'invio, scrivi gli indirizzi in Manda l\'avviso a: fino a cinque, separati da una virgola. Ogni form ha i suoi: i preventivi all\'ufficio commerciale, le candidature a chi si occupa del personale.'),
        ('Posso mettere gli orari di apertura e dire se adesso sono aperto?', 'Sì. Nell\'editor c\'è il widget Orari di apertura: scrivi gli orari di ogni giorno e le ferie, e il sito dice da solo "Aperto ora" o "Chiuso, apre domani alle 9:00". Gli orari entrano anche nei dati che Google sa leggere.'),
        ('Cosa sono gli addon?', 'Funzioni in più che arrivano con plumeCMS e accendi solo se ti servono, in Impostazioni > Addon: prenotazioni, eventi con iscrizione, raccolta recensioni, vetrina prodotti, statistiche senza cookie, area riservata, corsi online, copia del sito altrove. Spento, un addon sparisce dal sito ma i suoi dati restano. Dalla 2.0.0 sono in beta.'),
        ('Posso far prenotare un appuntamento dal sito?', 'Sì, con l\'addon Prenotazioni e appuntamenti. Scrivi i servizi, chi li fa e i suoi orari: chi visita sceglie fra i posti davvero liberi, riceve la conferma per email e il promemoria il giorno prima. Tu vedi tutto nell\'agenda del pannello.'),
        ('Posso vendere i miei prodotti?', 'Sì, con l\'addon Vetrina prodotti: catalogo, varianti, carrello e ordini che arrivano per email e nel pannello. Il pagamento è con bonifico, alla consegna o con il tuo link PayPal.me o Stripe: per ora non c\'è il pagamento con carta direttamente sul sito.'),
        ('Posso dare un ebook a chi si iscrive?', 'Sì. In Download carichi il file con la spunta Riservato; nel form, in Dopo l\'invio, scegli "Dopo la conferma, dai questo file". Chi compila riceve l\'email di conferma e, appena conferma, il link personale per scaricarlo. Il file non ha un indirizzo pubblico.'),
        ('C\'è una guida per imparare a usarlo?', 'Sì: il manuale d\'uso, in PDF, in due file (il secondo è per gli addon). Quaranta capitoli, dal primo accesso alla checklist prima di andare online, ognuno con un esempio vero, le schermate con i passi numerati e una checklist per sapere quando hai finito.'),
        ('Posso costruirmi un tema mio?', 'Sì: c\'è un manuale apposta, con un tema d\'esempio completo da smontare e rifare tuo.'),
    ]
    p.sezione([[w('faq', voci=[{'domanda': d, 'risposta': r} for d, r in voci], prima_aperta=True)]], larghezza='stretta', **spazi(24, 56))
    p.sezione([[w('invito_libero', icona='domanda', titolo='Non trovi la risposta?', livello='h2',
                  testo='Nel manuale d\'uso ogni capitolo finisce con "Se qualcosa non va", e in fondo ci sono i problemi più frequenti.',
                  pulsante_testo='Scarica il manuale d\'uso', link={'url': '/scarica/manuale-uso/', 'nuova': False})]],
              larghezza='stretta', **tinta(), **spazi(64, 80))
    return dict(slug='domande-frequenti', titolo='Domande frequenti', struttura=p.albero(),
                seo_titolo='Domande frequenti su plumeCMS',
                seo_descrizione='Hosting, installazione, temi, aggiornamenti, versioni, sicurezza: le risposte alle domande più frequenti su plumeCMS.')


# ------------------------------------------------------------------ stile, header, footer, menu
def editor_json():
    stile = {
        'colori': {'sistema': {'primario': '#5b45e0', 'secondario': '#0b7d72', 'testo': '#1b1f33', 'accento': '#e07a2f', 'sfondo': '#fbfaf8'},
                   'personalizzati': [{'id': 'sfondo-2', 'nome': 'Sfondo secondario', 'valore': '#f3f1fb'},
                                      {'id': 'testo-2', 'nome': 'Testo attenuato', 'valore': '#4a4f66'},
                                      {'id': 'testo-3', 'nome': 'Testo discreto', 'valore': '#646a80'},
                                      {'id': 'bordo', 'nome': 'Bordi', 'valore': 'rgba(27,31,51,.10)'}]},
        'font': {'sistema': {'primario': {'famiglia': 'Space Grotesk'}, 'secondario': {'famiglia': 'Space Grotesk'},
                             'testo': {'famiglia': 'Inter'}, 'accento': {'famiglia': 'Inter'}}, 'personalizzati': []},
        'pulsanti': {'raggio': '10px'},
        'sfondo': {'sfumature_sito': False},
        'sito': {'nome': 'plumeCMS', 'sottotitolo': 'il CMS leggero come una piuma',
                 'descrizione': 'Un CMS in PHP e MySQL con editor visuale, temi, SEO e newsletter già dentro.',
                 'logo': '', 'favicon': U + 'favicon.png'},
        # Ritocchi per lo sfondo chiaro (come il tema Aria): testata chiara, ombre leggere.
        'css': {'sito': ':root{--r:14px}\n'
                        '.top,.ed-testata{background:rgba(var(--bg-rgb),.88)}\n'
                        '.btn-viola,.btn-ciano{box-shadow:0 1px 2px rgba(27,31,51,.08),0 8px 22px rgba(91,69,224,.18)}\n'
                        '.btn-filo{box-shadow:none}\n'
                        'h1,h2,.sez-titolo{letter-spacing:-.02em}\n'
                        '.ed-w-immagine img{border-radius:14px;box-shadow:0 1px 2px rgba(27,31,51,.06),0 18px 40px rgba(27,31,51,.12)}\n'
                        # La Copertina della home con la piuma (copertina.js): il disegno e gia scuro e calmo in basso a
                        # sinistra, dove stanno le parole, quindi il velo scurisce solo li e lascia respirare la piuma.
                        # Al telefono la foto si ritaglia in verticale: si guarda a destra, dove sta la piuma, e il velo
                        # resta pieno sotto le parole (che li passano sopra la piuma).
                        # I tre riquadri di "Prima di cominciare" (pagina Installazione): icona a sinistra, testo allineato a
                        # sinistra. Nel CMS 1.2.7 il riquadro con icona non lo permette: la sua "Posizione dell'icona" ha lo
                        # stesso nome della Posizione della scheda Avanzate e si perde, e .ew-ribox centra sempre il testo.
                        '#passi .ew-ribox{flex-direction:row;align-items:flex-start;text-align:left}\n'
                        '@media (min-width:861px){.ed-w-hero .ed-hero-copertina .ed-hcop-velo{background:linear-gradient(90deg,rgba(0,0,0,.6) 0%,rgba(0,0,0,.45) 50%,rgba(0,0,0,.18) 70%,rgba(0,0,0,0) 85%),linear-gradient(0deg,rgba(0,0,0,.55) 0%,rgba(0,0,0,0) 55%)}}\n'
                        '@media (max-width:860px){.ed-w-hero .ed-hero-copertina.ed-hero-v .finestra.ed-hcop-foto img{object-position:88% 30%}'
                        '.ed-w-hero .ed-hero-copertina .ed-hcop-velo{background:linear-gradient(0deg,rgba(0,0,0,.88) 0%,rgba(0,0,0,.8) 50%,rgba(0,0,0,.62) 66%,rgba(0,0,0,.25) 80%,rgba(0,0,0,.08) 100%)}}\n'},
    }
    sez = lambda i, figli, **imp: {'id': i, 'tipo': 'sezione', 'impostazioni': imp, 'figli': figli}
    col = lambda i, figli, larg=100: {'id': i, 'tipo': 'colonna', 'impostazioni': {'larghezza': {'d': larg, 'm': 100}}, 'figli': figli}
    wid = lambda i, tipo, **imp: {'id': i, 'tipo': tipo, 'impostazioni': imp}
    header = {'versione': 1, 'elementi': [sez('plh01', [col('plh02', [
        wid('plh03', 'logo_sito', modo='entrambi', immagine=U + 'marchio.png', larghezza_immagine={'d': {'v': 32, 'u': 'px'}},
            senza_sottotitolo=True, maiuscolo=False, margine_sotto={'d': 0}),
        wid('plh04', 'menu_nav', menu='posizione:principale', disposizione='orizzontale', spingi=True, collasso=960, hamburger_testo='Menu',
            indicatore='linea', indicatore_colore='globale:primario', extra_testo='Scarica', extra_link='/scarica/', margine_sotto={'d': 0}),
        # Sul computer il pulsante; sul telefono la stessa voce sta in fondo al menu (extra_testo).
        wid('plh05', 'pulsanti', voci=[{'testo': 'Scarica', 'link': '/scarica/', 'stile': 'viola', 'nuova': False}], margine_sotto={'d': 0},
            nascondi_t=True, nascondi_m=True)])],
        larghezza='contenuta', spazio_sopra={'d': 0, 'm': 0}, spazio_sotto={'d': 0, 'm': 0}, spazio_colonne={'d': 28})]}
    footer = {'versione': 1, 'elementi': [
        sez('plf01', [col('plf02', [wid('plf03', 'logo_sito', modo='entrambi', immagine=U + 'marchio.png', larghezza_immagine={'d': {'v': 28, 'u': 'px'}},
                                        maiuscolo=False, margine_sotto={'d': 8}), wid('plf04', 'descrizione_sito', margine_sotto={'d': 0})], 40),
                      col('plf05', [wid('plf06', 'menu_nav', menu='posizione:piede-1', titolo='Il sito', disposizione='verticale', margine_sotto={'d': 0})], 30),
                      col('plf07', [wid('plf08', 'menu_nav', menu='posizione:piede-2', titolo='Manuali e download', disposizione='verticale', margine_sotto={'d': 0})], 30)],
            larghezza='contenuta', colore_testo='globale:testo-2', sfondo='colore', colore_sfondo='globale:sfondo-2',
            spazio_sopra={'d': 56, 'm': 40}, spazio_sotto={'d': 24, 'm': 24}),
        sez('plf10', [col('plf11', [wid('plf12', 'copyright', testo='© {anno} {sito}. Fatto con plumeCMS, naturalmente.', margine_sotto={'d': 0})])],
            larghezza='contenuta', colore_testo='globale:testo-3', sfondo='colore', colore_sfondo='globale:sfondo-2',
            spazio_sopra={'d': 0, 'm': 0}, spazio_sotto={'d': 32, 'm': 32})]}
    voce = lambda i, e, slug: {'id': i, 'etichetta': e, 'tipo': 'pagina', 'rif': slug, 'url': '', 'nuova': False, 'classi': '', 'figli': []}
    principale = [voce('plm01', 'Home', 'home'), voce('plm02', 'Funzionalità', 'funzionalita'), voce('plm03', 'Temi', 'temi'),
                  voce('plm04', 'Documentazione', 'documentazione'), voce('plm05', 'Installazione', 'installazione')]
    piede = [voce('plp01', 'Funzionalità', 'funzionalita'), voce('plp02', 'Temi', 'temi'), voce('plp03', 'Installazione', 'installazione'),
             voce('plp04', 'Domande frequenti', 'domande-frequenti'), voce('plp05', 'Scarica', 'scarica')]
    link = lambda i, e, u: {'id': i, 'etichetta': e, 'tipo': 'link', 'rif': '', 'url': u, 'nuova': False, 'classi': '', 'figli': []}
    manuali = [link('plq00', 'Manuale d\'uso (PDF)', '/scarica/manuale-uso/'), link('plq02', 'Manuale dei temi (PDF)', '/scarica/manuale-temi/'),
               link('plq03', f'plumeCMS {VU} (zip)', f'/scarica/{SERIE}/'), link('plq04', 'Tema Lavagna (zip)', '/scarica/tema-lavagna/')]
    return {'formato': 'editor-impostazioni', 'versione': 1, 'cms': V, 'creato': '',
            'stile': stile,
            'modelli': [{'tipo': 'header', 'nome': 'Header plumeCMS', 'status': 'published', 'predefinito': 1, 'condizioni': [], 'priorita': 0, 'struttura': header, 'struttura_bozza': None},
                        {'tipo': 'footer', 'nome': 'Footer plumeCMS', 'status': 'published', 'predefinito': 1, 'condizioni': [], 'priorita': 0, 'struttura': footer, 'struttura_bozza': None}],
            'menu': [{'nome': 'Principale', 'posizione': 'principale', 'voci': principale}, {'nome': 'Piede', 'posizione': 'piede-1', 'voci': piede},
                     {'nome': 'Manuali e download', 'posizione': 'piede-2', 'voci': manuali}]}


# ------------------------------------------------------------------ file
USATE = ['copertina', 'addon', 'su-misura', 'firma', 'widget-attivita', 'social', 'fine-articolo', 'importa-wp', 'tipo-sito', 'primi-passi', 'aspetti', 'controlla-server', 'sezioni', 'copia', 'installa-1', 'installa-2', 'installa-3', 'installa-4', 'installa-5', 'editor-ritratto', 'editor', 'aperture', 'stile', 'modelli', 'versioni', 'seo', 'flussi', 'download', 'sicurezza', 'aggiornamenti', 'temi',
         'tema-cosmo', 'tema-aria', 'tema-bottega']


def immagini_da_schermate(cartella):
    from PIL import Image
    os.makedirs(IMG, exist_ok=True)
    for f in sorted(os.listdir(cartella)):
        if not f.endswith('.png') or f[:-4] not in USATE:
            continue
        im = Image.open(os.path.join(cartella, f)).convert('RGB')
        larg = 880 if f.startswith('editor-ritratto') else 1440
        if im.width > larg:
            im = im.resize((larg, round(im.height * larg / im.width)), Image.LANCZOS)
        im.save(os.path.join(IMG, f[:-4] + '.webp'), 'WEBP', quality=82, method=6)
        print('immagine', f[:-4] + '.webp')


def favicon(dest, lato=64):
    """L'icona del sito (e il marchio della testata): un quadrato indaco con una piuma bianca, disegnata qui."""
    from PIL import Image, ImageDraw
    s = 4
    im = Image.new('RGBA', (64 * s, 64 * s), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.rounded_rectangle([0, 0, 64 * s - 1, 64 * s - 1], radius=14 * s, fill=(91, 69, 224, 255))
    b = (255, 255, 255, 255)
    d.line([(48 * s, 12 * s), (22 * s, 34 * s), (15 * s, 52 * s)], fill=b, width=4 * s, joint='curve')
    for (x1, y1), (x2, y2) in [((44 * s, 18 * s), (28 * s, 24 * s)), ((44 * s, 22 * s), (30 * s, 32 * s)), ((40 * s, 30 * s), (26 * s, 38 * s))]:
        d.line([(x1, y1), (x2, y2)], fill=b, width=3 * s)
    im.resize((lato, lato), Image.LANCZOS).save(dest)


def zip_lavagna(dest):
    sorgente = os.path.join(RADICE, 'esempi', 'tema-lavagna')
    with zipfile.ZipFile(dest, 'w', zipfile.ZIP_DEFLATED) as z:
        for base, _, files in os.walk(sorgente):
            for f in sorted(files):
                p = os.path.join(base, f)
                info = zipfile.ZipInfo('lavagna/' + os.path.relpath(p, sorgente), date_time=(2026, 10, 27, 12, 0, 0))
                info.external_attr = 0o644 << 16
                info.compress_type = zipfile.ZIP_DEFLATED
                z.writestr(info, open(p, 'rb').read())


# Le novità per chi scarica: brevi, scritte qui (il CHANGELOG è per chi sviluppa). Da aggiornare a ogni versione.
NOVITA = [
    ('2.0.0', 'Gli addon (beta)', ['In Impostazioni > Addon otto funzioni da accendere solo se servono: Prenotazioni e appuntamenti, Eventi con iscrizione, Raccolta recensioni, Vetrina prodotti con ordini, Statistiche senza cookie, Area riservata, Corsi online, Copia del sito altrove.',
                                   'File riservati in Download e il form che li dà dopo la conferma dell\'email; l\'immagine per i social si sceglie dai Media ed esce sempre con l\'indirizzo intero.',
                                   'Newsletter più solida: un limite che conta tutte le email, il mittente giusto anche senza SMTP, gli indirizzi che rimbalzano messi da parte.']),
    ('1.10.0', 'Sette widget per le attività', ['Orari di apertura con "Aperto ora" che si aggiorna da solo, ferie comprese; Barra contatti fissa sul telefono con Chiama, WhatsApp, Indicazioni.',
                                               'Recensioni con il voto medio e le stelle, Prima e dopo con la linea da trascinare, Squadra, Slider ed Eventi che spariscono da soli quando sono passati.',
                                               'I widget ora sono 87.']),
    ('1.9.0', 'A chi arriva l\'avviso di un form', ['Nella scheda di ogni form, Dopo l\'invio, la casella Manda l\'avviso a: fino a cinque indirizzi che ricevono un\'email a ogni compilazione.',
                                                    'Vuota, l\'avviso va all\'indirizzo del sito, come prima.']),
    ('1.8.0', 'La frase in fondo al sito', ['Sotto il piede di ogni pagina una riga piccola, "Un nuovo sito plumeCMS", con il link al sito ufficiale. Si cambia o si spegne in Impostazioni > Sito pubblico.',
                                           'Nella dashboard, in cima, il link al sito ufficiale di plumeCMS.']),
    ('1.7.0', 'Il sito su misura', ['All\'installazione la carta Su misura: quattro domande (che attività è, dove, cosa offri, quali pagine) e il sito si compone da solo, con testi pensati per quell\'attività.',
                                   'Foto finte che dicono cosa fotografare; in Media, cartella Da sostituire, il pulsante Sostituisci mette la foto vera in tutte le pagine.',
                                   'Finché il sito è in prova, le domande si riaprono dalla dashboard e il sito si ricompone.']),
    ('1.6.4', 'Due manuali', ['Nel prodotto e nel sito restano il manuale d\'uso e il manuale dei temi: il manuale tecnico e i documenti per chi sviluppa non ci sono più.']),
    ('1.6.0', 'Un riquadro in fondo a ogni articolo', ['In Articoli, la linguetta Aggiungi sempre alla fine: un riquadro ombreggiato che esce sotto ogni articolo, anche sotto quelli già pubblicati.',
                                                     'Si compone con l\'editor visuale, come una pagina, ed esce quando lo pubblichi. Si spegne e si riaccende con un clic.',
                                                     'Il manuale d\'uso ha un capitolo nuovo, con l\'esempio: ora i capitoli sono 31.']),
    ('1.5.0', 'I social, ogni mattina', ['L\'email "Oggi sul sito": i contenuti che escono oggi, con le due immagini per i social e il testo pronto per LinkedIn, Facebook, Instagram, WhatsApp e Telegram.',
                                         'Il calendario dei post: un testo per ogni giorno, che arriva la mattina con il link e le immagini.',
                                         'Nella scheda di ogni articolo il riquadro Social; le immagini le ritaglia plumeCMS dalla foto in evidenza.']),
    ('1.4.2', 'Il manuale d\'uso', ['Un manuale nuovo, per chi usa plumeCMS: 29 capitoli, ognuno con un esempio vero, le schermate con i passi numerati e una checklist.',
                                    'Un articolo scritto nell\'editor e pubblicato dalla scheda esce con il suo testo.',
                                    'Form, menu e utenti con i campi giusti; accenti rimessi in tutto il pannello.']),
    ('1.4.1', 'Il pannello nuovo si vede subito', ['Dopo un aggiornamento il browser prende da solo i colori e gli script nuovi del pannello: gli aspetti cambiano al primo clic.']),
    ('1.4.0', 'Il sito si prova, il pannello si sceglie, WordPress si porta qui', [
        'Importa da WordPress: report di quello che c\'è, scegli cosa portare, guardi tutto in prova, poi confermi o annulli.',
        'Il tipo di sito resta in prova finché non premi Definitivo: personale, attività, blog o portfolio, con un clic dalla dashboard.',
        'Il pannello parte chiaro, e ogni utente sceglie il suo aspetto: Aria, Bottega, Cosmo, Lavagna.',
        'Primi passi nella dashboard, la pagina Controlla il server, foto più leggere in WebP, l\'icona del sito già pronta.',
        'Il Log cron dice cosa ha fatto ogni giro, in parole chiare.']),
    ('1.6.2', 'L\'indirizzo degli aggiornamenti arriva da solo', ['Ogni installazione chiede le versioni nuove a plumecms.danilofailli.it: non c\'è più niente da scrivere in Aggiornamenti, l\'indirizzo viaggia con gli aggiornamenti.']),
    ('1.3.1', 'Il sito degli aggiornamenti si sceglie', ['In Impostazioni, Aggiornamenti scrivi da quale sito arrivano le versioni nuove: non è più fisso nel codice.']),
    ('1.3.0', 'Si parte con un sito vero', ['All\'installazione scegli che sito vuoi fare: personale, attività, blog, portfolio. Parte già fatto.',
                                           'Nell\'editor, trenta sezioni pronte da mettere nella pagina con un clic.',
                                           'Il pannello ti dice quando esce una versione nuova, e la prepara da solo.',
                                           'La copia del sito con un clic, e il ripristino quando serve.']),
    ('1.2.8', 'Software libero, di tutti', ['plumeCMS ha la sua licenza: GNU GPL versione 3. Lo usi, lo modifichi, lo condividi.',
                                            'Testi d\'esempio neutri e niente più pezzi del sito da cui è nato.',
                                            'Corretti il controllo "Sono una persona" nei siti installati nella radice e la pubblicazione di notte.']),
    ('1.2.7', 'Aperture con il loro respiro', ['Copertina, Centrata, Rivista e Scheda ritrovano il margine interno: il testo non tocca più il bordo.']),
    ('1.2.6', 'Undici aperture', ['L\'apertura delle pagine si sceglie fra undici disposizioni: centrata, a tutto sfondo, divisa, rivista, scheda, minimale, mosaico, inclinata, banner, diagonale e la classica.',
                                'Cambi disposizione con una tendina e i testi restano dove sono.']),
    ('1.2.5', 'Sempre l\'ultima versione', ['Un file con la versione nel nome ha un indirizzo che porta sempre alla versione più recente.',
                                           'Nelle pagine il numero di versione si scrive da solo: carichi il file nuovo e pulsanti e titoli si aggiornano.',
                                           'Temi con codice PHP: il pannello spiega passo per passo come caricarli.']),
    ('1.2.4', 'Download manager', ['Una pagina del pannello per i file da scaricare: carichi, aggiorni, vedi quante volte sono stati scaricati.',
                                  'Il widget Download: i tuoi file in una pagina, con icona, peso, versione e pulsante.']),
    ('1.2.3', 'Il sito e i manuali', ['I manuali in PDF, da scaricare.', 'Il pannello Revisioni dell\'editor ora si chiude davvero e lascia tutta la tela.',
                                     'Un pacchetto di aggiornamento può portare pagine, stile e menu: questo sito è arrivato così.']),
    ('1.2.2', 'Velo ed effetti sulle foto', ['Foto naturali, in bianco e nero o con il velo dei colori che scegli tu.',
                                            'Cinque effetti animati: zoom, bagliore, cornice luminosa, alone, scintille.', 'Le onde sopra la foto si tengono o si tolgono.']),
    ('1.2.1', 'I due manuali', ['Il manuale tecnico e il manuale dei temi, con un tema d\'esempio completo.']),
    ('1.2.0', 'I temi', ['Cosmo, Aria e Bottega: un clic e il sito cambia vestito.', 'Temi caricabili da zip, anche disegnati da te.']),
]


def note_versioni():
    return ''.join(f'<p><strong>{v}: {t}</strong></p><ul>' + ''.join(f'<li>{x}</li>' for x in voci) + '</ul>' for v, t, voci in NOVITA)


def pagine_pdf(f):
    import pypdf
    return len(pypdf.PdfReader(f).pages)


def main():
    global MAX_KB
    if '--max-kb' in sys.argv:
        MAX_KB = int(sys.argv[sys.argv.index('--max-kb') + 1])
    if '--schermate' in sys.argv:
        immagini_da_schermate(sys.argv[sys.argv.index('--schermate') + 1])
    zips = {n: os.path.join(REL, 'cms', n + '.zip') for n in (ZIP, ZIP_AGG)}
    pdf = {n: os.path.join(REL, 'manuali', n + '.pdf') for n in ('manuale-uso', 'manuale-uso-addon', 'manuale-temi')}
    for n, f in list(zips.items()) + list(pdf.items()):
        if not os.path.exists(f):
            errore(f'manca {f}: prima python3 tools/cms-pacchetto.py --aggiornamento e node tools/manuali-pdf.js')
    out = os.path.join(REL, 'sito', 'pacchetto')
    if os.path.isdir(out):
        shutil.rmtree(out)
    os.makedirs(os.path.join(out, 'pagine'))
    os.makedirs(os.path.join(out, 'file'))
    file = []

    def metti(sorgente, nome, dest):
        shutil.copy(sorgente, os.path.join(out, 'file', nome))
        os.chmod(os.path.join(out, 'file', nome), 0o644)
        file.append({'da': 'file/' + nome, 'a': dest})

    for n, f in list(zips.items()) + list(pdf.items()):
        metti(f, os.path.basename(f), 'uploads/scaricabili/' + os.path.basename(f))
    zip_lavagna(os.path.join(out, 'file', 'tema-lavagna.zip'))
    file.append({'da': 'file/tema-lavagna.zip', 'a': 'uploads/scaricabili/tema-lavagna.zip'})
    for nome in USATE:
        f = nome + '.webp'
        if not os.path.exists(os.path.join(IMG, f)):
            errore(f'manca sito-plumecms/immagini/{f}: rifalle con --schermate')
        metti(os.path.join(IMG, f), f, 'uploads/plumecms/' + f)
    for nome, lato in (('favicon.png', 64), ('marchio.png', 128)):
        favicon(os.path.join(out, 'file', nome), lato)
        file.append({'da': 'file/' + nome, 'a': 'uploads/plumecms/' + nome})

    pagine = [home(), funzionalita(), temi(), documentazione({k: pagine_pdf(v) for k, v in pdf.items()}), installazione(), scarica(note_versioni()), faq()]
    nomi = []
    for pg in pagine:
        nome = f'pagine/{pg["slug"]}.json'
        json.dump(pg, open(os.path.join(out, nome), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        nomi.append(nome)
    json.dump(editor_json(), open(os.path.join(out, 'editor.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    manifest = {
        'nome': f'Sito di plumeCMS ({V})',
        'descrizione': 'Il sito per scaricare plumeCMS, fatto con plumeCMS: pagine, stile, header, footer, menu, file da scaricare.',
        'creato': __import__('datetime').date.today().isoformat(),
        'file': file,
        'impostazioni': {'sito_nome': 'plumeCMS', 'sito_titolo': 'plumeCMS', 'sito_sottotitolo': 'il CMS leggero come una piuma',
                         'sito_descrizione': 'Un CMS in PHP e MySQL con editor visuale, temi, SEO e newsletter già dentro.',
                         'home_descrizione': 'plumeCMS è un CMS in PHP e MySQL con editor visuale, temi, SEO e newsletter già dentro. Si installa dal browser in cinque passi.'},
        'editor': 'editor.json',
        'editor_ambiti': ['stile', 'modelli', 'menu'],
        'pagine': nomi,
        'azioni': ['sito-pagine-e-stile'],
        'note': f'Richiede plumeCMS 1.2.3 o successivo: prima il pacchetto {ZIP_AGG}.zip, poi questo. '
                'Cambia Stile del sito, header e footer predefiniti, menu Principale e la home; le altre pagine restano, fuori dal menu.',
    }
    dividi_e_costruisci(out, manifest, MAX_KB)


# Il limite di caricamento dei server: su Hostcare passa un pacchetto da 1,2 MB e non uno da 5 (27 ottobre 2026); il
# predefinito di PHP e 2 MB (upload_max_filesize = 2M), quindi ogni parte resta sotto 1900 KB. Con --max-kb si cambia
# (per esempio --max-kb 60000 per un pacchetto solo, su un server che accetta file grandi).
MAX_KB = 1900


def dividi_e_costruisci(out, manifest, max_kb):
    """La prima parte porta pagine, stile, menu, nome del sito e quanti file ci stanno; i file che restano (gli zip e i
    PDF da scaricare) vanno in parti di soli file, ognuna sotto max_kb. Si caricano in ordine: parte 1, 2, 3..."""
    import glob
    for vecchio in glob.glob(os.path.join(REL, 'sito', f'sito-plumecms-{V}*.zip')):
        os.remove(vecchio)
    peso = lambda voce: os.path.getsize(os.path.join(out, voce['da'])) // 1024 + 2
    base = sum(os.path.getsize(os.path.join(out, p)) for p in manifest['pagine'] + [manifest['editor']]) // 4096 + 8  # JSON: si comprime circa 4 a 1
    piccoli = sorted(manifest['file'], key=peso)
    prima, resto, occupato = [], [], base
    for v in piccoli:
        if occupato + peso(v) <= max_kb and not v['da'].endswith(('.pdf',)) and not v['a'].startswith('uploads/scaricabili/plumecms-'):
            prima.append(v); occupato += peso(v)
        else:
            resto.append(v)
    parti = []
    for v in sorted(resto, key=peso, reverse=True):
        if peso(v) > max_kb:
            errore(f"{v['da']} pesa {peso(v)} KB, piu del limite di {max_kb} KB: alza --max-kb o alleggerisci il file")
        for p in parti:
            if sum(map(peso, p)) + peso(v) <= max_kb:
                p.append(v); break
        else:
            parti.append([v])
    totale = 1 + len(parti)
    elenco = []
    for k, voci in enumerate([prima] + parti, start=1):
        cartella = out + f'-{k}'
        if os.path.isdir(cartella):
            shutil.rmtree(cartella)
        os.makedirs(cartella)
        m = {'nome': f'Sito di plumeCMS ({V}), parte {k} di {totale}', 'creato': manifest['creato'], 'file': voci}
        if k == 1:
            m.update({x: manifest[x] for x in ('descrizione', 'impostazioni', 'editor', 'editor_ambiti', 'pagine', 'azioni')})
            m['note'] = manifest['note'] + f' Dopo questa parte vanno caricate le altre ({totale - 1}), in ordine: portano i file da scaricare.'
            # 1.6.4: il manuale tecnico non si offre più (decisione di Danilo): via dal Download del sito che lo aveva.
            m['elimina'] = ['uploads/scaricabili/manuale-tecnico.pdf']
            for x in manifest['pagine'] + [manifest['editor']]:
                os.makedirs(os.path.dirname(os.path.join(cartella, x)) or cartella, exist_ok=True)
                shutil.copy(os.path.join(out, x), os.path.join(cartella, x))
        else:
            m['descrizione'] = 'File da scaricare del sito di plumeCMS: ' + ', '.join(os.path.basename(v['a']) for v in voci) + '.'
        for v in voci:
            os.makedirs(os.path.join(cartella, 'file'), exist_ok=True)
            shutil.copy(os.path.join(out, v['da']), os.path.join(cartella, v['da']))
        json.dump(m, open(os.path.join(cartella, 'manifest.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        # SITO_REV (30 ottobre 2026): le pagine del sito cambiano senza una versione nuova del CMS. Le parti nuove prendono un nome
        # diverso (sito-plumecms-1.10.0-r3-parte-...), così non si confondono con quelle già date.
        rev = re.sub(r'[^a-z0-9]', '', os.environ.get('SITO_REV', '').lower())
        dest = os.path.join(REL, 'sito', f'sito-plumecms-{V}{"-" + rev if rev else ""}-parte-{k}-di-{totale}.zip')
        subprocess.run([sys.executable, os.path.join(RADICE, 'tools', 'pacchetto.py'), cartella, dest], check=True)
        if os.path.getsize(dest) // 1024 > max_kb:
            errore(f'{dest} pesa {os.path.getsize(dest) // 1024} KB, oltre il limite di {max_kb} KB')
        elenco.append(dest)
    print('\nDa caricare in questo ordine, dopo il CMS ' + V + ':')
    for d in elenco:
        print(f'  {os.path.basename(d)}  ({os.path.getsize(d) // 1024} KB)')


if __name__ == '__main__':
    main()
