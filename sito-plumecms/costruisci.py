#!/usr/bin/env python3
"""
Il sito di plumeCMS (27 ottobre 2026), fatto con plumeCMS: pagine dell'editor, stile, header, footer e menu
in un pacchetto per il pannello Aggiornamenti (motore 1.2.3 o successivo).

Prima di lanciarlo:
  python3 tools/cms-pacchetto.py --aggiornamento     (gli zip del CMS da offrire)
  node tools/manuali-pdf.js                          (i due manuali in PDF)

uso: python3 sito-plumecms/costruisci.py [--schermate <cartella di png>] [--max-kb <KB>]
  --schermate rifà le immagini di sito-plumecms/immagini/ dalle schermate (vedi schermate.js).
  --max-kb    il peso massimo di ogni parte (dal 2.1.0 predefinito 200000 KB: un pacchetto solo). Con un limite basso,
              per un server che accetta file piccoli, il pacchetto si divide in parti come prima della 2.1.0.
Scrive releases/sito/sito-plumecms-<versione>.zip (con --max-kb basso: ...-parte-<k>-di-<n>.zip, da caricare in ordine).
Di solito non si carica da solo: tools/consegna.py lo unisce all'aggiornamento del CMS in un pacchetto unico.

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
                    {'titolo': 'Scegli il vestito', 'testo': 'Undici temi pronti: Cosmo, Aria, Bottega, le quattro stagioni e quattro tinte piene. Un clic e il sito cambia aspetto. I tuoi testi restano dove sono.'},
                    {'titolo': 'Scrivi e pubblica', 'testo': 'Trascini sezioni, colonne e widget, guardi l\'anteprima, premi Pubblica. Se sbagli, torni indietro: ogni modifica resta nelle versioni.'}]),
                pulsanti(('Guarda l\'installatore, passo per passo', '/installazione/#passi', 'filo'), centro=True)]],
              **spazi(48, 72))
    p.sezione([[w('numeri', colonne={'d': 4, 't': 2, 'm': 2}, voci=[{'valore': '5', 'etichetta': 'passi per installarlo'}, {'valore': '109', 'etichetta': 'widget nell\'editor'},
                                  {'valore': '11', 'etichetta': 'temi pronti da indossare'}, {'valore': '0', 'etichetta': 'plugin da tenere aggiornati'}])]],
              **tinta(), **spazi(56, 56))
    # Le armi del viaggio: cosa c'è dentro.
    funzioni = [
        ('pennello', 'Editor visuale', 'Sezioni, colonne e 109 widget, con le schede Contenuto, Stile e Avanzate per ogni elemento. Vedi quello che fai mentre lo fai.', '/funzionalita/#editor'),
        ('tavolozza', 'Temi', 'Undici temi pronti e un manuale per disegnarti il tuo. Il tema cambia l\'aspetto, mai i contenuti.', '/temi/'),
        ('storia', 'Versioni', 'Ogni salvataggio lascia una versione: la confronti, la guardi in anteprima, ci torni con un clic.', '/funzionalita/#editor'),
        ('grafico-su', 'SEO e AEO', 'Sitemap, robots, dati strutturati, llms.txt, IndexNow e Search Console. Per Google e per le intelligenze artificiali.', '/funzionalita/#seo'),
        ('email', 'Newsletter, form e social', 'Iscrizioni con doppia conferma, campagne, automazioni, form e popup. E ogni mattina i post pronti per i social.', '/funzionalita/#newsletter'),
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
    # 2.18.0, regola di Danilo: "ogni volta che aggiorniamo la versione lascia la hero dell'home page così com'è". Con
    # tieni_apertura il pannello (dalla 2.18.0) tiene la prima sezione della home del sito, se ha un'Apertura e la home viene da un pacchetto.
    return dict(slug='home', titolo='plumeCMS', tieni_apertura=True, struttura=p.albero(),
                seo_titolo='plumeCMS: il CMS leggero con l\'editor visuale',
                seo_descrizione='plumeCMS è un CMS in PHP e MySQL con editor visuale, temi, SEO e newsletter già dentro. Si installa dal browser in cinque passi.')


def funzionalita():
    p = Pagina('funzionalita')
    p.sezione([[titolo('Leggero non vuol dire *vuoto.*', 'Funzionalità',
                       'plumeCMS fa le cose che ti servono davvero, e le fa nello stesso posto. Ecco cosa trovi dentro, con le schermate vere del pannello.',
                       livello='h1', centro=True)]], larghezza='stretta', **spazi(72, 32))
    # Regola di Danilo (2 novembre 2026): la pagina non è un elenco di quello che esce a ogni versione, ma le 10 caratteristiche
    # principali di plumeCMS. Una funzione nuova entra dentro la caratteristica a cui appartiene, non diventa un blocco.
    blocchi = [
        ('editor', 'editor.webp', 'L\'editor visuale: vedi quello che fai mentre lo fai',
         ['Costruisci le pagine trascinando sezioni, colonne e widget. Ogni elemento ha le sue tre schede: <strong>Contenuto</strong>, <strong>Stile</strong> e <strong>Avanzate</strong>. Al computer, al tablet e al telefono, con misure diverse per ognuno.',
          '<ul><li>109 widget: titoli, testi, foto, gallerie, prezzi, domande frequenti, orari di apertura, recensioni, eventi, menu del ristorante, 30 infografiche (anche le barre a confronto, per i numeri uno accanto all\'altro) e Scorri di lato, le pagine che scorrono di lato come nei siti dei grandi marchi.</li>'
          '<li>26 aperture fra cui scegliere con una tendina, e 30 sezioni pronte per riempire una pagina in un minuto.</li>'
          '<li>Ogni parte si allinea per conto suo: occhiello al centro, testo a sinistra, e un altro allineamento sul telefono.</li>'
          '<li>Le foto le prendi dai Media e i link dal sito, senza scrivere indirizzi. Le foto del telefono si raddrizzano e si alleggeriscono da sole.</li>'
          '<li>Ogni salvataggio lascia una versione: la confronti, la guardi in anteprima, ci torni con un clic.</li></ul>']),
        ('partenza', 'installa-4.webp', 'In cinque minuti hai un sito vero, non una pagina bianca',
         ['Carichi plumeCMS sul tuo spazio, apri il browser e rispondi a cinque domande. All\'ultima scegli che sito vuoi: personale, attività, blog, portfolio, oppure <strong>su misura</strong>: dici che attività fai, in che città e quali servizi offri, e il sito si compone da solo.',
          '<ul><li>Home, pagine principali e menu sono già costruiti, con testi d\'esempio che ti dicono cosa scriverci.</li>'
          '<li>Non sei sicuro del tipo di sito? Lo provi, lo cambi, e quando ti convince lo rendi definitivo.</li>'
          '<li>I Primi passi nella dashboard ti dicono cosa manca perché il sito sia pronto, e Controlla il server ti dice se il tuo spazio va bene.</li></ul>']),
        ('aspetto', 'stile.webp', 'Temi e stile: il vestito cambia, i contenuti restano',
         ['Undici temi pronti, da Cosmo alle quattro stagioni: un clic e il sito cambia aspetto, i tuoi testi restano dove sono.',
          '<ul><li>Stile del sito: colori, caratteri, logo e pulsanti in un posto solo. Cambi il colore principale e cambia ovunque.</li>'
          '<li>Header, footer e menu li costruisci con lo stesso editor delle pagine.</li>'
          '<li>Vuoi un tema tutto tuo? C\'è il manuale dei temi, con un tema d\'esempio da cui partire.</li></ul>']),
        ('newsletter', 'campagne.webp', 'Newsletter: contatti, campagne e report, a casa tua',
         ['I contatti stanno nel tuo database, non in quello di qualcun altro. Ognuno ha la sua scheda, con la storia di quello che ha ricevuto, aperto e cliccato.',
          '<ul><li>Importi i contatti da un file in quattro passi, li dividi in liste e in segmenti con i filtri che vuoi.</li>'
          '<li>Ogni persona ha i suoi tag: li danno i form, i test, l\'importazione e le automazioni, e da un tag può partire un flusso.</li>'
          '<li>Componi le email trascinando blocchi, oppure parti da un modello. Provi due versioni con il test A/B e la migliore parte da sola.</li>'
          '<li>Il report di ogni campagna ti dice chi ha aperto, chi ha cliccato e dove, e l\'orario migliore per scrivere a ognuno.</li>'
          '<li>Le email arrivano nella posta, non nello spam: plumeCMS ti guida nella configurazione della consegna.</li></ul>']),
        ('automazioni', 'automazioni.webp', 'Automazioni: lavora anche quando dormi',
         ['Disegni il percorso sulla tela, con i blocchi uno sotto l\'altro, e plumeCMS lo fa fare a ogni persona che entra. Un benvenuto in tre email, gli auguri il giorno del compleanno, un richiamo a chi non apre da tre mesi.',
          '<ul><li>Si entra quando ci si iscrive, si viene tolti da una lista, arriva una data o lo decidi tu.</li>'
          '<li>Attese, bivi su aperture e clic, rami con i filtri dei segmenti, divisioni in percentuale.</li>'
          '<li>Su ogni blocco vedi quanti ci sono adesso e quanti ci sono passati; chi vuoi lo togli con un clic.</li></ul>']),
        ('form', 'form-passi.webp', 'Form e consenso: in regola senza fatica',
         ['I form si costruiscono in cinque passi: cosa chiedere, come appare, i messaggi, la conferma, dove metterlo. Ogni campo finisce nella scheda del contatto, e a ogni compilazione decidi tu chi riceve l\'email.',
          '<ul><li>Il form ha la sua pagina da condividere, il codice da mettere in un altro sito e il QR da stampare. E i popup decidi tu quando e dove.</li>'
          '<li>Doppia conferma, dichiarazione per la privacy con la casella vuota, consenso registrato con data e ora.</li>'
          '<li>Chi vuole uscire esce da una lista sola o sceglie cosa ricevere, e ti dice perché.</li></ul>']),
        ('seo', 'seo.webp', 'SEO e AEO: ti trovano Google e le intelligenze artificiali',
         ['Sitemap, robots.txt, dati strutturati, titoli e descrizioni per ogni pagina, redirect e pagine 404 sotto controllo. In più quello che serve alle risposte delle intelligenze artificiali: <code>llms.txt</code>, riassunti in testa, risposte brevi.',
          '<ul><li>Gli avvisi ai motori partono da soli, con IndexNow e Search Console.</li></ul>']),
        ('social', 'social.webp', 'Social ogni mattina: i post sono già pronti',
         ['Scrivi il testo nel riquadro Social dell\'articolo. Il giorno in cui esce ti arriva un\'email con il testo pronto per ogni canale, il link, i tuoi hashtag e le immagini nelle misure giuste, una orizzontale e una verticale.',
          '<ul><li>Il calendario dei post per i giorni dopo, e i testi da copiare con un clic.</li>'
          '<li>Ogni pagina condivisa mostra la sua immagine, con titolo e descrizione.</li>'
          '<li>Pubblicare resta a te: plumeCMS non entra nei tuoi account.</li></ul>']),
        ('addon', 'addon.webp', 'Gli addon: accendi solo quello che ti serve',
         ['In Impostazioni, <strong>Addon</strong>: nove funzioni in più, ognuna con il suo pulsante Accendi. Spento, l\'addon sparisce dal sito, e i suoi dati restano lì per quando lo riaccendi.',
          '<ul><li><strong>Prenotazioni e appuntamenti</strong>, con i posti davvero liberi e il promemoria il giorno prima.</li>'
          '<li><strong>Eventi con iscrizione</strong> e lista d\'attesa, <strong>Raccolta recensioni</strong> dopo il servizio.</li>'
          '<li><strong>Vetrina prodotti</strong> con carrello e ordini, <strong>Statistiche senza cookie</strong>.</li>'
          '<li><strong>Area riservata</strong>, <strong>Corsi online</strong> e <strong>Copia del sito altrove</strong>, ogni notte.</li>'
          '<li><strong>Test e questionari</strong>: a punteggio, ad aree o "che tipo sei", la risposta breve sullo schermo e quella completa per email. Le domande si dividono in pagine trascinandole, e un telefono accanto mostra il test come lo vedrà chi lo fa.</li></ul>',
          'E il download manager: manuali, ebook e listini con il loro indirizzo, anche riservati in cambio dell\'iscrizione.']),
        ('sicurezza', 'sicurezza.webp', 'Al sicuro, senza doverci pensare ogni giorno',
         ['Accesso a due fattori, blocchi contro chi prova a indovinare le password, protezioni del browser, registro degli eventi. Prima di uscire plumeCMS è stato messo alla prova con più di 500 attacchi, addon compresi: tutti respinti.',
          '<ul><li>Gli aggiornamenti si installano dal pannello, con <strong>Installa ora</strong>: prima il pannello fa una copia di sicurezza, e se qualcosa va storto rimette tutto com\'era.</li>'
          '<li>La copia del sito, con un clic: la scarichi, la tieni da parte, e se serve la rimetti al suo posto o su un altro spazio, anche con un database diverso. Se il ripristino non può riuscire, niente si cancella: il sito resta com\'era e il pannello dice perché.</li></ul>']),
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
                seo_titolo='Funzionalità di plumeCMS: le dieci cose che sa fare',
                seo_descrizione='Cosa c\'è dentro plumeCMS: editor visuale con 109 widget, gli addon (prenotazioni, eventi, negozio, statistiche, area riservata, corsi), social ogni mattina, stile globale, header e footer, versioni, SEO e AEO, newsletter, sicurezza, aggiornamenti dal pannello.')


def temi():
    p = Pagina('temi')
    p.sezione([[titolo('Cambia vestito al sito, *con un clic.*', 'Temi',
                       'Un tema cambia colori, caratteri, pulsanti, header e footer. Pagine, articoli, menu e immagini restano dove sono. '
                       'Prima lo guardi in anteprima, solo tu; poi decidi.', livello='h1', centro=True)]], larghezza='stretta', **spazi(72, 32))
    t = [('tema-cosmo.webp', 'Cosmo', 'Notte profonda, viola e ciano, titoli che brillano. L\'aspetto di nascita di plumeCMS.'),
         ('tema-aria.webp', 'Aria', 'Chiaro, pulito, un solo blu. Per chi vuole che parlino i contenuti.'),
         ('tema-bottega.webp', 'Bottega', 'Carta crema, titoli con le grazie, terracotta e salvia. Caldo come un laboratorio artigiano.')]
    p.sezione([[immagine(img, 'Il tema ' + n + ' di plumeCMS'), titolo(n, livello='h3'), ricco(d)] for img, n, d in t], **spazi(24, 48))
    stagioni = [('tema-estate.webp', 'Estate', 'Sabbia chiara, corallo e blu del largo, pulsanti a pillola e un\'onda sopra il piede.'),
                ('tema-inverno.webp', 'Inverno', 'Bianco ghiaccio con un velo di brina, blu profondo, titoli sottili.'),
                ('tema-autunno.webp', 'Autunno', 'Castagna scura, zucca e ocra, titoli con le grazie. Caldo come un camino acceso.'),
                ('tema-primavera.webp', 'Primavera', 'Bianco verdino, rosa pesco e verde foglia, titoli in corsivo, forme tonde.')]
    p.sezione([[titolo('Le quattro stagioni.', 'Novità della 2.5.0', 'Quattro temi, uno per stagione: il sito cambia aria quando cambia il tempo.', centro=True)]],
              larghezza='stretta', **spazi(48, 16))
    p.sezione([[immagine(img, 'Il tema ' + n + ' di plumeCMS'), titolo(n, livello='h3'), ricco(d)] for img, n, d in stagioni], **spazi(16, 48))
    tinte = [('tema-giallo.webp', 'Giallo', 'Sfondo 255, 255, 0. Testo nero, righe nette, pulsanti neri a lettere gialle.'),
             ('tema-rosso.webp', 'Rosso', 'Sfondo 255, 0, 0. Testo nero, titoli in maiuscolo, pulsanti bianchi e neri.'),
             ('tema-verde.webp', 'Verde', 'Sfondo 0, 255, 0. Testo nero, titoli a spaziatura fissa, pulsanti neri a lettere verdi.'),
             ('tema-blu.webp', 'Blu', 'Sfondo 0, 0, 255. Testo bianco, titoli con le grazie, pulsanti gialli.')]
    p.sezione([[titolo('Le tinte piene.', 'Per chi non passa inosservato',
                       'Un colore solo, pieno, dal bordo al bordo. Il testo resta leggibile ovunque: ogni scritta è misurata, almeno 4,5 a 1.', centro=True)]],
              larghezza='stretta', **spazi(48, 16))
    p.sezione([[immagine(img, 'Il tema ' + n + ' di plumeCMS'), titolo(n, livello='h3'), ricco(d)] for img, n, d in tinte], **spazi(16, 72))
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
                seo_titolo='Temi di plumeCMS: undici pronti e il tuo',
                seo_descrizione='I temi di plumeCMS cambiano l\'aspetto del sito senza toccare i contenuti. Undici temi pronti (Cosmo, Aria, Bottega, le quattro stagioni, giallo, rosso, verde e blu) e un manuale per costruire il tuo.')


def documentazione(pagine_pdf):
    p = Pagina('documentazione')
    p.sezione([[titolo('I manuali, *da leggere anche offline.*', 'Documentazione',
                       'Due manuali in PDF. Uno per usare plumeCMS, con le schermate vere e un capitolo per ogni addon; uno per costruirti il tuo tema.',
                       livello='h1', centro=True)]], larghezza='stretta', **spazi(72, 24))
    man = [('lista-spunte', 'Manuale d\'uso', pagine_pdf['manuale-uso'], 'manuale-uso',
            'Come si fa ogni cosa: articoli, pagine con l\'editor, menu, form, newsletter, sicurezza, aggiornamenti. Nella seconda parte i nove addon: prenotazioni, eventi, recensioni, negozio, statistiche, area riservata, corsi, copia del sito altrove, test e questionari. Ogni capitolo ha un esempio vero, le schermate con i passi numerati e una checklist.'),
           ('tavolozza', 'Manuale dei temi', pagine_pdf['manuale-temi'], 'manuale-temi',
            'Come si costruisce un tema da zero: cartella, colori, header e footer, CSS, infografiche, PHP, prove, distribuzione. Con il tema d\'esempio Lavagna.')]
    p.sezione([[w('invito_libero', icona=ic, titolo=n, livello='h2', testo=f'{d} {pag} pagine.', pulsante_testo='Scarica il PDF',
                  link={'url': f'/scarica/{f}/', 'nuova': False})] for ic, n, pag, f, d in man], **spazi(24, 56))
    p.sezione([(50, [titolo('Cosa c\'è nel manuale d\'uso', livello='h3'),
                     w('elenco_icone', voci=[{'testo': t, 'icona': 'spunta', 'link': ''} for t in
                                             ['Il pannello in cinque minuti', 'Il primo articolo, e come programmarlo', 'Una pagina con l\'editor, dall\'inizio alla fine',
                                              'Menu, header, footer, stile e temi', 'Form, popup, newsletter, download', 'SEO, utenti, email, cron, sicurezza',
                                              'Aggiornare e copiare il sito', 'I nove addon, uno per capitolo', 'La checklist prima di andare online']])]),
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
             {'celle': 'Caricamento | limite di almeno 8 MB (upload_max_filesize e post_max_size), per gli aggiornamenti'},
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
                    {'file': 'manuale-uso', 'titolo': 'Manuale d\'uso', 'descrizione': 'Come si fa ogni cosa, con esempi, schermate e checklist. Con un capitolo per ognuno degli otto addon.'},
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
        ('C\'è una guida per imparare a usarlo?', 'Sì: il manuale d\'uso, in PDF, in un file solo con le schermate nitide. Quaranta capitoli, dal primo accesso alla checklist prima di andare online, ognuno con un esempio vero, le schermate con i passi numerati e una checklist per sapere quando hai finito.'),
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
                 'logo': U + 'marchio.png', 'favicon': U + 'favicon.png'},
        # 2.6.0: il logo sta solo qui; i widget Logo del sito di header e footer lo leggono (prima avevano l'immagine scritta dentro
        # e il logo cambiato in Stile del sito non compariva).
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
        wid('plh03', 'logo_sito', modo='entrambi', larghezza_immagine={'d': {'v': 32, 'u': 'px'}},
            senza_sottotitolo=True, maiuscolo=False, margine_sotto={'d': 0}),
        wid('plh04', 'menu_nav', menu='posizione:principale', disposizione='orizzontale', spingi=True, collasso=960, hamburger_testo='Menu',
            indicatore='linea', indicatore_colore='globale:primario', extra_testo='Scarica', extra_link='/scarica/', margine_sotto={'d': 0}),
        # Sul computer il pulsante; sul telefono la stessa voce sta in fondo al menu (extra_testo).
        wid('plh05', 'pulsanti', voci=[{'testo': 'Scarica', 'link': '/scarica/', 'stile': 'viola', 'nuova': False}], margine_sotto={'d': 0},
            nascondi_t=True, nascondi_m=True)])],
        larghezza='contenuta', spazio_sopra={'d': 0, 'm': 0}, spazio_sotto={'d': 0, 'm': 0}, spazio_colonne={'d': 28})]}
    footer = {'versione': 1, 'elementi': [
        sez('plf01', [col('plf02', [wid('plf03', 'logo_sito', modo='entrambi', larghezza_immagine={'d': {'v': 28, 'u': 'px'}},
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
USATE = ['form-passi', 'automazioni', 'report', 'test-ab', 'editor-email', 'campagne', 'infografiche', 'segmenti', 'importa-contatti', 'contatti-brevo', 'email-conferma', 'pannello-chiaro', 'copertina', 'installa-ora', 'scegli-sito', 'consegna', 'widget-2-1', 'scegli-media', 'addon', 'su-misura', 'firma', 'widget-attivita', 'social', 'fine-articolo', 'tipo-sito', 'primi-passi', 'aspetti', 'controlla-server', 'sezioni', 'copia', 'installa-1', 'installa-2', 'installa-3', 'installa-4', 'installa-5', 'editor-ritratto', 'editor', 'aperture', 'stile', 'modelli', 'versioni', 'seo', 'flussi', 'download', 'sicurezza', 'aggiornamenti', 'temi',
         'tema-cosmo', 'tema-aria', 'tema-bottega',
         'tema-estate', 'tema-inverno', 'tema-autunno', 'tema-primavera', 'tema-giallo', 'tema-rosso', 'tema-verde', 'tema-blu']


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


# Regola di Danilo (1 novembre 2026): solo i punti salienti, una riga per versione, mai nomi di aziende o servizi di terzi.
# Le novità per chi scarica: brevi, scritte qui (il CHANGELOG è per chi sviluppa). Da aggiornare a ogni versione.
NOVITA = [
    ('2.26.0', 'Barre a confronto', ['Una sedicesima infografica disegnata: da 2 a 8 voci con un valore (euro, anni, percentuali), barre in proporzione al più alto e il numero alla fine. 109 widget.']),
    ('2.25.1', 'Ripristino sicuro della copia', ['La copia di un sito si rimette anche su un altro spazio o un altro database: se qualcosa non va, niente si cancella e il sito resta com\'era, con il motivo scritto in parole semplici. E se il pannello non parte, una pagina di soccorso dice cosa fare.']),
    ('2.25.0', 'Scorri di lato', ['Un widget con più pagine: arrivati lì la pagina scorre di lato fino all\'ultima, poi riprende in verticale. Cinque disposizioni.']),
    ('2.24.0', 'Test a pagine, con l\'anteprima', ['Una domanda per pagina, pagine scelte da te o tutte insieme; nel pannello le domande si spostano fra le pagine e un telefono mostra il test mentre lo scrivi.']),
    ('2.23.0', 'Tag delle persone e test più completi', ['Ogni contatto ha i suoi tag, anche dai test; nei test le domande sulla persona e il risultato completo per email.']),
    ('2.22.0', 'Test e questionari', ['Un addon per costruire test a punteggio, ad aree o "che tipo sei": una domanda per schermata, risultato con il grafico.']),
    ('2.21.0', 'Allinea ogni parte di ogni widget', ['Occhiello, titolo, testo, nome, prezzo: ognuno con il suo allineamento, anche diverso sul telefono.']),
    ('2.20.0', 'Un pannello più snello', ['Via una funzione che non dava i risultati attesi: restano le cose che si usano davvero.']),
    ('2.18.0', 'Le automazioni richiamano i modelli', ['Correggi un modello e le email delle automazioni escono già corrette; menu del pannello più leggibile.']),
    ('2.17.1', 'Le email delle automazioni dai modelli', ['Nel passo Email di un\'automazione si sceglie un modello, a blocchi o in codice HTML.']),
    ('2.17.0', 'Quindici aperture nuove', ['Ventisei disposizioni per l\'apertura delle pagine, con video, lente, prima e dopo e terminale; i video si caricano nei Media.']),
    ('2.16.1', 'Il limite di caricamento fra i requisiti', ['Almeno 8 MB per caricare gli aggiornamenti: l\'installatore e il pannello lo controllano.']),
    ('2.16.0', 'Form a cinque passi e consenso', ['Form costruiti in cinque passi, con la loro pagina, il codice da incollare e il QR; consenso e preferenze per ogni contatto.']),
    ('2.15.0', 'Le automazioni', ['Percorsi disegnati sulla tela con inneschi, attese, bivi e rami: ognuno riceve l\'email giusta al momento giusto.']),
    ('2.14.0', 'Report, statistiche e orario migliore', ['Il report di ogni campagna con la mappa dei clic, le statistiche di un periodo e l\'invio all\'ora in cui ognuno apre.']),
    ('2.13.0', 'Test A/B delle campagne', ['Due versioni provate su due piccoli gruppi: la migliore parte da sola a tutti gli altri.']),
    ('2.12.0', 'Editor delle email a blocchi', ['Email composte trascinando blocchi e colonne, sei modelli pronti, i tuoi modelli e le sezioni salvate.']),
    ('2.11.0', 'Campagne a riepilogo e centro preferenze', ['Ogni campagna in una pagina con le sue parti, invio programmato e a lotti; chi esce dice perché o sceglie le liste da tenere.']),
    ('2.10.0', 'Segmenti e quindici infografiche', ['Gruppi di contatti che si aggiornano da soli e quindici infografiche disegnate nell\'editor: 107 widget.']),
    ('2.9.0', 'Importa ed esporta i contatti in pochi passi', ['Da file CSV, TXT o Excel, oppure incollati: abbini le colonne, scegli la lista, confermi; e la lista dei test per le prove.']),
    ('2.8.0', 'Contatti con scheda, liste e campi tuoi', ['Una scheda per ogni persona con la sua storia, colonne che scegli tu, liste in cartelle, campi tuoi anche calcolati e lista nera con il motivo.']),
    ('2.7.0', 'L\'email di conferma la scrivi tu', ['Oggetto, testo, pulsante e firma dell\'email a chi si iscrive: tutto si cambia, con anteprima e prova.']),
    ('2.6.0', 'Un pannello più chiaro', ['Titoli su una fascia, campi che si vedono, conferme chiare e tabelle comode al telefono.']),
    ('2.5.0', 'Otto temi nuovi', ['Le quattro stagioni e quattro tinte piene, tutte leggibili su ogni pagina.']),
    ('2.4.0', 'Il sito ufficiale è plumecms.it', ['Le versioni nuove arrivano da plumecms.it, e un sito si sposta su un altro dominio dal pannello.']),
    ('2.3.2', 'Il collegamento con i motori di ricerca', ['La chiave salvata in SEO e AEO ora si vede, con il pulsante per provarla.']),
    ('2.3.1', 'Scarica sempre l\'ultima', ['Il link dell\'ultima versione porta subito a quella appena caricata.']),
    ('2.3.0', 'Installa ora', ['Quando esce una versione nuova, un clic dalla dashboard la scarica e la installa, con la copia di sicurezza prima.']),
    ('2.2.0', 'Scegli dal sito', ['In ogni campo link scrivi due lettere e scegli la pagina o l\'articolo: il link entra da solo.']),
    ('2.1.0', 'Le email arrivano nella posta', ['Mittente, dominio e rimbalzi sotto controllo, cinque widget nuovi (92 in tutto), Scegli dai Media in ogni campo immagine.']),
    ('2.0.3', 'L\'immagine per i social', ['Ogni pagina condivisa mostra la sua immagine, con le misure giuste.']),
    ('2.0.2', 'I link scritti a mano', ['Un link scritto senza https:// o senza la barra porta comunque alla pagina giusta.']),
    ('2.0.1', 'Il pannello al telefono', ['Nessuna pagina del pannello scorre più di lato sul telefono.']),
    ('2.0.0', 'Gli addon', ['Otto funzioni da accendere solo se servono: prenotazioni, eventi, recensioni, vetrina, statistiche senza cookie, area riservata, corsi, copia del sito altrove.']),
    ('1.10.0', 'Sette widget per le attività', ['Orari con "Aperto ora", barra contatti sul telefono, recensioni, prima e dopo, squadra, slider ed eventi.']),
    ('1.9.0', 'A chi arriva l\'avviso di un form', ['Fino a cinque indirizzi che ricevono un\'email a ogni compilazione.']),
    ('1.8.0', 'La frase in fondo al sito', ['"Un nuovo sito plumeCMS" sotto ogni pagina, da cambiare o spegnere.']),
    ('1.7.0', 'Il sito su misura', ['Quattro domande all\'installazione e il sito si compone da solo, con testi per la tua attività.']),
    ('1.6.4', 'Due manuali', ['Il manuale d\'uso e il manuale dei temi.']),
    ('1.6.2', 'Gli aggiornamenti si trovano da soli', ['Non c\'è più un indirizzo da scrivere per ricevere le versioni nuove.']),
    ('1.6.0', 'Un riquadro in fondo a ogni articolo', ['Si compone con l\'editor visuale ed esce sotto tutti gli articoli.']),
    ('1.5.0', 'I social, ogni mattina', ['Un\'email con i contenuti del giorno, le immagini pronte e il testo da pubblicare.']),
    ('1.4.2', 'Il manuale d\'uso', ['Ogni capitolo con un esempio vero, le schermate e una checklist.']),
    ('1.4.1', 'Il pannello nuovo si vede subito', ['Dopo un aggiornamento colori e funzioni nuove arrivano al primo clic.']),
    ('1.4.0', 'Il pannello come piace a te', ['Quattro aspetti del pannello, foto più leggere, i primi passi nella dashboard e il controllo del server.']),
    ('1.3.1', 'Il sito degli aggiornamenti si sceglie', ['Da quale sito arrivano le versioni nuove non è più fisso.']),
    ('1.3.0', 'Si parte con un sito vero', ['Scegli il tipo di sito e parte già fatto; trenta sezioni pronte nell\'editor; copia del sito con un clic.']),
    ('1.2.8', 'Software libero', ['plumeCMS ha una licenza libera: lo usi, lo modifichi, lo condividi.']),
    ('1.2.7', 'Aperture con il loro respiro', ['Il testo delle aperture non tocca più il bordo.']),
    ('1.2.6', 'Undici aperture', ['L\'apertura delle pagine si sceglie fra undici disposizioni, con una tendina.']),
    ('1.2.5', 'Sempre l\'ultima versione', ['Un link che porta sempre alla versione più recente, e il numero di versione che si scrive da solo.']),
    ('1.2.4', 'Download manager', ['I file da scaricare in una pagina del pannello, con i conteggi, e il widget Download.']),
    ('1.2.3', 'Il sito e i manuali', ['I manuali in PDF e gli aggiornamenti che portano anche pagine e stile.']),
    ('1.2.2', 'Velo ed effetti sulle foto', ['Bianco e nero, velo colorato e cinque effetti animati.']),
    ('1.2.1', 'I due manuali', ['Il manuale tecnico e il manuale dei temi, con un tema d\'esempio.']),
    ('1.2.0', 'I temi', ['Un clic e il sito cambia vestito; i temi si caricano anche da zip.']),
]


# Regola di Danilo (1 novembre 2026): nella pagina Scarica non un changelog, ma un elenco breve delle ultime versioni,
# che non scende sotto il riquadro "Cosa serve" accanto (lo controlla prova-sito-plumecms.js).
NOVITA_MOSTRATE = 6


def note_versioni():
    return '<ul>' + ''.join(f'<li><strong>{v}</strong>: {t}</li>' for v, t, voci in NOVITA[:NOVITA_MOSTRATE]) + '</ul>'


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
    pdf = {n: os.path.join(REL, 'manuali', n + '.pdf') for n in ('manuale-uso', 'manuale-temi')}  # 2.1.0: niente più manuale-uso-addon
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

    # --senza-home: la home non va nel pacchetto. Serve finché il sito che lo riceve è sotto la 2.18.0: lì il pacchetto lo applica il
    # motore vecchio, che non conosce tieni_apertura e rimpiazzerebbe anche la hero.
    pagine = ([] if '--senza-home' in sys.argv else [home()]) + [funzionalita(), temi(), documentazione({k: pagine_pdf(v) for k, v in pdf.items()}), installazione(), scarica(note_versioni()), faq()]
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


# Il limite di caricamento dei server: su Hostcare passava un pacchetto da 1,2 MB e non uno da 5 (27 ottobre 2026), e fino
# alla 2.0.3 ogni parte restava sotto 1900 KB. Il 31 ottobre 2026 Danilo ha alzato il limite del suo hosting e ha chiesto un
# file solo per aggiornamento: dalla 2.1.0 il predefinito è un pacchetto solo. --max-kb 1900 rifà le parti piccole.
MAX_KB = 200000


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
    # 2.1.0: se tutto sta sotto max_kb (il predefinito), un pacchetto solo con tutto dentro.
    tutto = base + sum(map(peso, piccoli)) <= max_kb
    for v in piccoli:
        if tutto or (occupato + peso(v) <= max_kb and not v['da'].endswith(('.pdf',)) and not v['a'].startswith('uploads/scaricabili/plumecms-')):
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
        m = {'nome': f'Sito di plumeCMS ({V})' + (f', parte {k} di {totale}' if totale > 1 else ''), 'creato': manifest['creato'], 'file': voci}
        if k == 1:
            m.update({x: manifest[x] for x in ('descrizione', 'impostazioni', 'editor', 'editor_ambiti', 'pagine', 'azioni')})
            m['note'] = manifest['note'] + (f' Dopo questa parte vanno caricate le altre ({totale - 1}), in ordine: portano i file da scaricare.' if totale > 1 else '')
            # 1.6.4: il manuale tecnico non si offre più (decisione di Danilo): via dal Download del sito che lo aveva.
            m['elimina'] = ['uploads/scaricabili/manuale-tecnico.pdf']
            # 2.1.0: il manuale d'uso è un PDF solo; il vecchio "manuale-uso-addon" sparisce e il suo indirizzo porta al manuale.
            m['elimina'].append('uploads/scaricabili/manuale-uso-addon.pdf')
            m['redirect'] = [{'da': '/scarica/manuale-uso-addon/', 'a': '/scarica/manuale-uso/'}]
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
        dest = os.path.join(REL, 'sito', f'sito-plumecms-{V}{"-" + rev if rev else ""}' + (f'-parte-{k}-di-{totale}' if totale > 1 else '') + '.zip')
        subprocess.run([sys.executable, os.path.join(RADICE, 'tools', 'pacchetto.py'), cartella, dest], check=True)
        if os.path.getsize(dest) // 1024 > max_kb:
            errore(f'{dest} pesa {os.path.getsize(dest) // 1024} KB, oltre il limite di {max_kb} KB')
        elenco.append(dest)
    print('\n' + ('Il pacchetto del sito' if len(elenco) == 1 else 'Da caricare in questo ordine') + ', dopo il CMS ' + V + ' (o dentro il pacchetto unico di tools/consegna.py):')
    for d in elenco:
        print(f'  {os.path.basename(d)}  ({os.path.getsize(d) // 1024} KB)')


if __name__ == '__main__':
    main()
