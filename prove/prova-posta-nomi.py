#!/usr/bin/env python3
"""2.40.1: i nomi nel From, nel To e nel Reply-To scritti come vuole la RFC 5322 (segnalazione di Danilo dal sito di Cucina
Creativa, 2.39.0: Apple Mail mostrava "MISSING_MAILBOX@MISSING_DOMAIN per conto di [ Cucina Creativa ]": il nome con le
parentesi quadre passava senza virgolette e l'intestazione From non era valida).

Accende il server di posta finto (prove/smtp-finto.py), il sendmail finto (prove/sendmail-finto.py, per mail() di PHP) e le API
finte di Brevo, Amazon SES e Mailgun (prove/api-posta-finta.py); con prove/posta-nomi-cli.php manda le email dalle funzioni vere
del CMS, per ogni nome e da ogni strada; poi legge ogni messaggio arrivato con il parser rigoroso di Python
(email.policy.default, email.headerregistry) e controlla: un indirizzo solo, quello giusto, il nome uguale a quello scritto,
nessun difetto dell'intestazione, nessuna intestazione in più (Bcc), righe sotto i 998 caratteri, parole codificate sotto i 75.
Per Brevo controlla i campi name del JSON (in chiaro, senza virgolette aggiunte né a capo).

uso: python3 -I prove/prova-posta-nomi.py <cartella del CMS installato> <porta smtp libera> <porta api libera>
Lascia dati di prova (mittenti "@nomi-prova.it", un form "Prova nomi"): va su un sito da buttare. Mai su un server vero.
Atteso: tutto OK sulla 2.40.1; la 2.40.0 sbaglia From, To e Reply-To dei nomi con gli speciali (la riga "funzioni" non c'è)."""
import email
import email.policy
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import time

DIR, PORTA_SMTP, PORTA_API = sys.argv[1], sys.argv[2], sys.argv[3]
QUI = os.path.dirname(os.path.abspath(__file__))
CLI = os.path.join(QUI, 'posta-nomi-cli.php')

# I quattro della segnalazione, poi quelli cattivi: spazi dentro le parentesi (il nome vero di Cucina Creativa), \ e " insieme,
# un nome lungo con accenti (più parole codificate), un nome che sembra un indirizzo, uno che finge un altro indirizzo, una parola
# codificata finta (deve restare scritta così), un a capo con un Bcc dietro (intestazione aggiunta), un carattere a 4 byte.
NOMI = [
    '[Cucina Creativa]',
    'Rossi, Mario',
    'Caffè "Da Mario"',
    'Cucina Creativa',
    '[ Cucina Creativa ]',
    'Studio "Il Punto" \\ Roma',
    "O'Brien & Figli S.r.l.",
    'Trattoria da Nicolò e Ludovica, cucina tipica marchigiana dal 1962 (Ascoli Piceno)',
    'prenotazioni@altro.it',
    'Mario <falso@cattivo.it>',
    '=?UTF-8?B?ZmFsc28=?=',
    'Riga\r\nBcc: spia@cattivo.it',
    '\U0001F35D Pasta fresca',
    'Année: 2026; ok.',
]
QUATTRO = 4  # i primi quattro sono quelli della segnalazione

n = ko = 0


def ok(testo, cond, extra=''):
    global n, ko
    n += 1
    if not cond:
        ko += 1
    print(('OK  ' if cond else 'KO  ') + testo + ((' ' + json.dumps(extra, ensure_ascii=False)[:300]) if extra != '' else ''))


def stretto(s):
    return re.sub(r'\s+', ' ', s).strip()


def atteso(nome):
    """Il nome come lo deve mostrare il programma di posta: a capo e caratteri di controllo diventano uno spazio."""
    return stretto(re.sub(r'[\x00-\x1f\x7f]+', ' ', nome))[:150]


def atteso_to(nome):
    """Il nome nel To arriva dal campo "A" (posta_interne() toglie anche " < >, come dalla 2.11.0)."""
    return stretto(re.sub(r'[\x00-\x1f\x7f"<>]+', ' ', nome))[:150]


def cli(*a, ambiente=None, php=('php',)):
    r = subprocess.run([*php, CLI, DIR, *a], capture_output=True, text=True, timeout=180)
    if r.returncode != 0:
        print(r.stdout[-800:], r.stderr[-800:])
    try:
        return json.loads(r.stdout.strip().splitlines()[-1])
    except Exception:
        return r.stdout + r.stderr


def messaggi(cartella, estensione='.eml'):
    nomi = sorted((f for f in os.listdir(cartella) if f.endswith(estensione)), key=lambda f: int(re.match(r'\d+', f).group()))
    return [open(os.path.join(cartella, f), 'rb').read() for f in nomi]


def indice(m):
    s = str(m['Subject'] or '')
    g = re.search(r'nome (\d+)', s)
    return int(g.group(1)) if g else -1


def controlla_testa(raw, dove):
    """Righe dell'intestazione sotto i 998 caratteri, parole codificate sotto i 75, nessun Bcc."""
    testa = raw.split(b'\r\n\r\n', 1)[0].split(b'\n\n', 1)[0]
    righe = testa.split(b'\n')
    lunghe = [r[:80] for r in righe if len(r.rstrip(b'\r')) > 998]
    parole = [p for p in re.findall(rb'=\?[^?\s]+\?[BbQq]\?[^?\s]*\?=', testa) if len(p) > 75]
    bcc = [r for r in righe if re.match(rb'(?i)bcc:', r)]
    return not lunghe and not parole and not bcc, {'dove': dove, 'lunghe': len(lunghe), 'parole lunghe': len(parole), 'bcc': len(bcc)}


def indirizzo(m, campo):
    """[(nome, indirizzo)], difetti dell'intestazione."""
    try:
        h = m[campo]
        if h is None:
            return [], ['manca']
        return [(a.display_name, a.addr_spec) for a in h.addresses], [type(d).__name__ for d in h.defects]
    except Exception as e:  # un a capo dentro l'indirizzo (la 2.40.0 con SMTP): Python rifiuta di leggerlo
        return [], [type(e).__name__ + ': ' + str(e)[:80]]


def guarda(m, campo, nome_atteso, ind_atteso, dove):
    ind, dif = indirizzo(m, campo)
    buono = ind == [(nome_atteso, ind_atteso)] and not dif
    ok(f'{dove}: {campo} = {nome_atteso!r} <{ind_atteso}>', buono, {'letto': ind, 'difetti': dif} if not buono else '')


def atteso_mittente(nome):
    """Il nome di un mittente di Consegna delle email, come lo salva consegna_mittente_aggiungi()."""
    return atteso(re.sub(r'[\U00010000-\U0010FFFF\r\n<>]', '', nome).strip()[:100])


def strada(nome_strada, cartella, mittente_di=None, con_to=True, con_reply=True):
    ms = messaggi(cartella)
    ok(f'{nome_strada}: arrivate {len(NOMI)} email', len(ms) == len(NOMI), len(ms))
    for raw in ms:
        m = email.message_from_bytes(raw, policy=email.policy.default)
        i = indice(m)
        if i < 0:
            ok(f'{nome_strada}: oggetto con il numero del nome', False, str(m['Subject']))
            continue
        nome = NOMI[i]
        buono, extra = controlla_testa(raw, f'{nome_strada} {i}')
        ok(f'{nome_strada} {i}: intestazioni senza righe lunghe, parole codificate lunghe o Bcc', buono, extra if not buono else '')
        guarda(m, 'From', atteso_mittente(nome) if mittente_di else atteso(nome), mittente_di(i) if mittente_di else 'sito@nomi-prova.it', f'{nome_strada} {i}')
        if con_to:
            guarda(m, 'To', atteso_to(nome), f'dest{i}@nomi-prova.it', f'{nome_strada} {i}')
        if con_reply:
            guarda(m, 'Reply-To', atteso(nome), 'risposte@nomi-prova.it', f'{nome_strada} {i}')


T = tempfile.mkdtemp(prefix='posta-nomi-')
fnomi = os.path.join(T, 'nomi.json')
with open(fnomi, 'w', encoding='utf-8') as f:
    json.dump(NOMI, f, ensure_ascii=False)
procs = []
try:
    # ===== 0. Le funzioni, senza spedire (solo dalla 2.40.1)
    r = cli('funzioni', '-', fnomi)
    if isinstance(r, list) and len(r) == len(NOMI):
        for x in r:
            nome = NOMI[x['i']]
            h = 'From: ' + x['intestazione'] + '\r\n\r\n'
            m = email.message_from_bytes(h.encode('utf-8'), policy=email.policy.default)
            ind, dif = indirizzo(m, 'From')
            ok(f'funzioni {x["i"]}: posta_nome_indirizzo() si legge {atteso(nome)!r}', ind == [(atteso(nome), 'sito@nomi-prova.it')] and not dif,
               {'intestazione': x['intestazione'], 'letto': ind, 'difetti': dif})
            ok(f'funzioni {x["i"]}: posta_nome_da_intestazione() ridà il nome', x['ritorno'] == ['sito@nomi-prova.it', atteso(nome)], x['ritorno'])
        r0 = {x['i']: x['intestazione'] for x in r}
        ok('funzioni: "Cucina Creativa" resta senza virgolette', r0[3] == 'Cucina Creativa <sito@nomi-prova.it>', r0[3])
        ok('funzioni: "[Cucina Creativa]" fra virgolette', r0[0] == '"[Cucina Creativa]" <sito@nomi-prova.it>', r0[0])
        ok('funzioni: "Rossi, Mario" fra virgolette', r0[1] == '"Rossi, Mario" <sito@nomi-prova.it>', r0[1])
        ok('funzioni: Caffè "Da Mario" in parola codificata UTF-8', r0[2].startswith('=?UTF-8?B?') and '"' not in r0[2], r0[2])
        ok('funzioni: \\ e " protetti dentro le virgolette', r0[5] == '"Studio \\"Il Punto\\" \\\\ Roma" <sito@nomi-prova.it>', r0[5])
        ok('funzioni: la parola codificata finta va codificata a sua volta (si legge com\'è scritta)', r0[10].startswith('=?UTF-8?B?') and 'ZmFsc28=?= <' not in r0[10], r0[10])
        ok('funzioni: il nome lungo con accenti in più parole codificate', r0[7].count('=?UTF-8?B?') >= 2, r0[7])
    else:
        ok('funzioni: posta_nome_indirizzo() c\'è', False, str(r)[:300])

    # ===== 1. SMTP: From da Configurazione email, To dal campo "A", Reply-To con il nome
    posta = os.path.join(T, 'smtp')
    procs.append(subprocess.Popen([sys.executable, '-I', os.path.join(QUI, 'smtp-finto.py'), PORTA_SMTP, posta]))
    time.sleep(1)
    r = cli('smtp', PORTA_SMTP, fnomi)
    ok('SMTP: tutte spedite', r == ['ok'] * len(NOMI), r)
    strada('SMTP', posta)

    # ===== 2. SMTP con il mittente scelto di Consegna delle email (From nelle intestazioni, riletto da consegna_mittente_per())
    shutil.rmtree(posta); os.makedirs(posta)
    r = cli('mittente', PORTA_SMTP, fnomi)
    ok('mittente scelto: tutte spedite', r == ['ok'] * len(NOMI), r)
    strada('mittente scelto', posta, mittente_di=lambda i: f'mitt{i}@nomi-prova.it', con_to=False, con_reply=False)

    # ===== 3. mail() di PHP con il sendmail finto
    sm = os.path.join(T, 'sendmail')
    r = cli('mail', '-', fnomi, php=('php', '-d', f'sendmail_path=python3 -I {os.path.join(QUI, "sendmail-finto.py")} {sm}'))
    ok('mail(): tutte spedite', r == ['ok'] * len(NOMI), r)
    strada('mail()', sm)

    # ===== 4. Brevo, Amazon SES, Mailgun con le API finte
    api = os.path.join(T, 'api')
    procs.append(subprocess.Popen([sys.executable, '-I', os.path.join(QUI, 'api-posta-finta.py'), PORTA_API, api,
                                   'xkeysib-' + 'a1b2c3d4' * 8 + '-ProvaBrevo123456', 'AKIAPROVAPROVA123456', 'Prova/Segreta+Amazon' + 'X' * 20, 'key-' + 'f0e1d2c3' * 4]))
    time.sleep(1)
    for servizio in ('brevo', 'ses', 'mailgun'):
        r = cli(servizio, PORTA_API, fnomi)
        ok(f'{servizio}: tutte spedite', r == ['ok'] * len(NOMI), r)
    js = sorted((f for f in os.listdir(api) if f.endswith('-brevo.json')), key=lambda f: int(f.split('-')[0]))
    ok(f'Brevo: arrivate {len(NOMI)} richieste', len(js) == len(NOMI), len(js))
    for f in js:
        d = json.load(open(os.path.join(api, f), encoding='utf-8'))
        i = int(re.search(r'nome (\d+)', d.get('subject', '')).group(1))
        nome = NOMI[i]
        s, t, rt = d.get('sender', {}), (d.get('to') or [{}])[0], d.get('replyTo', {})
        buono = s.get('name') == atteso(nome) and s.get('email') == 'sito@nomi-prova.it' and t.get('name') == atteso_to(nome) \
            and rt.get('email') == 'risposte@nomi-prova.it' and rt.get('name') == atteso(nome)
        ok(f'Brevo {i}: name di sender, to e replyTo in chiaro', buono, {'sender': s, 'to': t, 'replyTo': rt} if not buono else '')
    for servizio in ('ses', 'mailgun'):
        cart = os.path.join(T, servizio)
        os.makedirs(cart)
        for f in os.listdir(api):
            if f.endswith(f'-{servizio}.eml'):
                shutil.copy(os.path.join(api, f), os.path.join(cart, f.split('-')[0] + '.eml'))
        strada('SES' if servizio == 'ses' else 'Mailgun', cart)

    # ===== 5. Gli avvisi: form e richiesta di prenotazione, Reply-To con il nome di chi scrive
    shutil.rmtree(posta); os.makedirs(posta)
    r = cli('form', PORTA_SMTP, fnomi)
    ok('form e richiesta: tutti ricevuti', isinstance(r, list) and all(x.endswith(': ok') for x in r), r)
    for raw in messaggi(posta):
        m = email.message_from_bytes(raw, policy=email.policy.default)
        ind, dif = indirizzo(m, 'Reply-To')
        g = re.match(r'(form|richiesta)(\d+)@nomi-prova\.it', ind[0][1]) if len(ind) == 1 else None
        if not g:
            ok('avvisi: Reply-To con un indirizzo solo, quello di chi ha scritto', False, {'letto': ind, 'difetti': dif})
            continue
        i, chi = int(g.group(2)), g.group(1)
        buono, extra = controlla_testa(raw, f'{chi} {i}')
        nome_letto = ind[0][0]
        # I campi del form tolgono da sé qualcosa (a capo, caratteri a 4 byte): per i nomi della segnalazione il nome è identico.
        giusto = nome_letto == atteso(NOMI[i]) if i < QUATTRO else ('\n' not in nome_letto and 'spia@' not in ind[0][1])
        ok(f'avviso {chi} {i}: Reply-To {nome_letto!r} <{ind[0][1]}>, senza difetti', buono and giusto and not dif, {'letto': ind, 'difetti': dif, 'testa': extra} if not (buono and giusto and not dif) else '')
finally:
    cli('pulisci')
    for p in procs:
        p.terminate()
    shutil.rmtree(T, ignore_errors=True)

print(f'\n{n - ko} su {n} OK')
sys.exit(1 if ko else 0)
