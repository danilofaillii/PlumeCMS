#!/usr/bin/env python3
"""Un server di posta finto per le prove (1.4.0): accetta SMTP in chiaro, senza autenticazione, e scrive ogni messaggio
ricevuto in <cartella>/<n>.eml. Mai su un server vero.
uso: python3 -I prove/smtp-finto.py <porta> <cartella>   (si ferma con un segnale)"""
import os
import socketserver
import sys

PORTA, CARTELLA = int(sys.argv[1]), sys.argv[2]
os.makedirs(CARTELLA, exist_ok=True)


class Sessione(socketserver.StreamRequestHandler):
    def scrivi(self, riga):
        self.wfile.write((riga + '\r\n').encode())

    def handle(self):
        self.scrivi('220 finto ESMTP')
        a, dati = [], None
        while True:
            riga = self.rfile.readline()
            if not riga:
                return
            if dati is not None:
                if riga in (b'.\r\n', b'.\n'):
                    n = len(os.listdir(CARTELLA)) + 1
                    with open(os.path.join(CARTELLA, f'{n}.eml'), 'wb') as f:
                        f.write(('X-Destinatari: ' + ','.join(a) + '\r\n').encode() + b''.join(dati))
                    dati = None
                    self.scrivi('250 ricevuto')
                else:
                    dati.append(riga)
                continue
            c = riga.decode(errors='replace').strip()
            v = c.upper()
            if v.startswith('EHLO') or v.startswith('HELO'):
                self.scrivi('250 finto')
            elif v.startswith('MAIL FROM'):
                self.scrivi('250 ok')
            elif v.startswith('RCPT TO'):
                a.append(c.split(':', 1)[1].strip(' <>'))
                self.scrivi('250 ok')
            elif v == 'DATA':
                dati = []
                self.scrivi('354 avanti')
            elif v == 'QUIT':
                self.scrivi('221 ciao')
                return
            else:
                self.scrivi('250 ok')


socketserver.ThreadingTCPServer.allow_reuse_address = True
with socketserver.ThreadingTCPServer(('127.0.0.1', PORTA), Sessione) as s:
    s.serve_forever()
