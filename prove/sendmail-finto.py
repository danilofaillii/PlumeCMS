#!/usr/bin/env python3
"""Un sendmail finto per le prove di mail() di PHP (2.0.0): scrive il messaggio ricevuto su stdin in <cartella>/<n>.eml,
con in testa l'intestazione X-Argomenti (gli argomenti della riga di comando, per vedere il -f del Return-Path).
uso, nel php.ini o con -d: sendmail_path="python3 -I prove/sendmail-finto.py <cartella>"   Mai su un server vero."""
import os
import sys

CARTELLA = sys.argv[1]
os.makedirs(CARTELLA, exist_ok=True)
dati = sys.stdin.buffer.read()
n = len([f for f in os.listdir(CARTELLA) if f.endswith('.eml')]) + 1
with open(os.path.join(CARTELLA, f'{n}.eml'), 'wb') as f:
    f.write(('X-Argomenti: ' + ' '.join(sys.argv[2:]) + '\r\n').encode() + dati)
