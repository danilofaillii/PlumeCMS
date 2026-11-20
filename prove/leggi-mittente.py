#!/usr/bin/env python3
"""Rilegge i messaggi delle prove del mittente (2.40.1) con la libreria standard di Python, come farebbe un programma di posta.
Mai nel prodotto.

uso: python3 -I prove/leggi-mittente.py <attesi.json> [cartella]
attesi.json: [{"file": "1.eml", "from": [nome, indirizzo], "to": [...], "reply_to": [...], "subject": "...",
               "senza": ["Bcc", ...]}, ...]   (le voci mancanti non si controllano; "file" relativo a cartella o a attesi.json)
Per ogni messaggio controlla, carattere per carattere, che il nome riletto di From, To e Reply-To sia quello atteso e
l'indirizzo giusto; che non ci siano MISSING_MAILBOX né difetti di lettura; che ogni parola codificata (=?...?=) sia lunga
al massimo 75 caratteri; che nessuna riga delle intestazioni superi 998 caratteri; che non ci siano Bcc, Cc o un secondo
To o Content-Type (iniezione). Stampa una riga OK o KO per controllo e, in fondo, "N su M"."""
import email
import email.policy
import json
import os
import re
import sys

attesi_file = sys.argv[1]
cartella = sys.argv[2] if len(sys.argv) > 2 else os.path.dirname(os.path.abspath(attesi_file))
with open(attesi_file, encoding='utf-8') as f:
    attesi = json.load(f)

n = ko = 0


def ok(cosa, cond, extra=''):
    global n, ko
    n += 1
    if not cond:
        ko += 1
    print(('OK  ' if cond else 'KO  ') + cosa + (' ' + json.dumps(extra, ensure_ascii=False)[:300] if extra != '' else ''))


for a in attesi:
    percorso = a['file'] if os.path.isabs(a['file']) else os.path.join(cartella, a['file'])
    with open(percorso, 'rb') as f:
        raw = f.read()
    testa = raw.split(b'\r\n\r\n', 1)[0].split(b'\n\n', 1)[0]
    m = email.message_from_bytes(raw, policy=email.policy.default)
    nome_file = os.path.basename(percorso)
    ok(f'{nome_file}: nessun MISSING_MAILBOX', b'MISSING' not in testa.upper() and 'MISSING' not in str(m.get('From', '')).upper())
    righe = testa.replace(b'\r\n', b'\n').split(b'\n')
    ok(f'{nome_file}: righe delle intestazioni sotto 998 caratteri', max(len(r) for r in righe) <= 998)
    parole = re.findall(rb'=\?[^?\s]+\?[BbQq]\?[^?\s]*\?=', testa)
    ok(f'{nome_file}: parole codificate di 75 caratteri al massimo', all(len(p) <= 75 for p in parole), [len(p) for p in parole if len(p) > 75])
    nomi = [r.split(b':', 1)[0].strip().lower() for r in righe if r and r[:1] not in (b' ', b'\t') and b':' in r]
    for vietata in a.get('senza', ['bcc', 'cc']):
        ok(f'{nome_file}: nessuna intestazione {vietata}', vietata.lower().encode() not in nomi)
    ok(f'{nome_file}: un solo To e un solo Content-Type in testa', nomi.count(b'to') <= 1 and nomi.count(b'content-type') <= 1, [x.decode() for x in nomi])
    for campo, chiave in (('From', 'from'), ('To', 'to'), ('Reply-To', 'reply_to')):
        if chiave not in a:
            continue
        nome, ind = a[chiave]
        h = m[campo]
        if h is None:
            ok(f'{nome_file}: {campo} c\'è', False)
            continue
        indirizzi = getattr(h, 'addresses', ())
        difetti = [type(d).__name__ for d in getattr(h, 'defects', [])]
        letto = (indirizzi[0].display_name, indirizzi[0].addr_spec) if len(indirizzi) == 1 else None
        ok(f'{nome_file}: {campo} riletto con il nome identico {json.dumps(nome, ensure_ascii=False)}',
           letto is not None and letto[0] == nome and letto[1] == ind and not difetti, {'letto': letto, 'difetti': difetti, 'grezzo': str(m.get_all(campo))[:200]})
    if 'subject' in a:
        ok(f'{nome_file}: Subject identico', str(m['Subject']) == a['subject'], str(m['Subject']))

print(f'{n - ko} su {n}')
sys.exit(1 if ko else 0)
