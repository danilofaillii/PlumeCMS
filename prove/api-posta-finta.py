#!/usr/bin/env python3
"""Le API finte di Brevo, Amazon SES e Mailgun per le prove della consegna (2.1.0). Mai su un server vero.
Il CMS le usa con define('CMS_POSTA_API_PROVA', 'http://127.0.0.1:<porta>') nel config.local.php.

uso: python3 -I prove/api-posta-finta.py <porta> <cartella> <chiave brevo> <id chiave ses> <segreto ses> <chiave mailgun>

Ogni messaggio accettato finisce in <cartella>/<n>-<servizio>.json (quello che è arrivato, già letto) e, per SES e
Mailgun, anche in <cartella>/<n>-<servizio>.eml (il messaggio MIME). Le chiavi si controllano davvero:
  Brevo    intestazione api-key uguale alla chiave, altrimenti 401;
  SES      firma AWS Signature Version 4 ricalcolata qui (servizio ses, regione dall'host o dall'ambito), altrimenti 403;
  Mailgun  Basic api:<chiave>, altrimenti 401.
Un destinatario che contiene "rifiuta" riceve un 400, come un indirizzo non valido."""
import base64
import email
import email.policy
import hashlib
import hmac
import json
import os
import sys
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PORTA, CARTELLA, BREVO, SES_ID, SES_SEG, MAILGUN = int(sys.argv[1]), sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5], sys.argv[6]
os.makedirs(CARTELLA, exist_ok=True)


def salva(servizio, dati, eml=None):
    n = len([f for f in os.listdir(CARTELLA) if f.endswith('.json')]) + 1
    with open(os.path.join(CARTELLA, f'{n}-{servizio}.json'), 'w', encoding='utf-8') as f:
        json.dump(dati, f, ensure_ascii=False, indent=1)
    if eml is not None:
        with open(os.path.join(CARTELLA, f'{n}-{servizio}.eml'), 'wb') as f:
            f.write(eml)
    return n


def allegati_mime(raw):
    """I nomi e i contenuti degli allegati di un messaggio MIME, e il From."""
    m = email.message_from_bytes(raw, policy=email.policy.default)
    fuori = []
    for p in m.walk():
        if p.get_content_disposition() == 'attachment':
            fuori.append({'nome': p.get_filename(), 'tipo': p.get_content_type(), 'dati': p.get_payload(decode=True).decode('utf-8', 'replace')})
    return {'from': str(m.get('From', '')), 'to': str(m.get('To', '')), 'subject': str(m.get('Subject', '')),
            'rif': str(m.get('X-Plume-Rif', '')), 'list_unsubscribe': str(m.get('List-Unsubscribe', '')), 'allegati': fuori,
            'multipart': m.is_multipart()}


def firma_sigv4(metodo, percorso, query, testa, corpo, ambito_giorno, regione, servizio, firmate, data):
    canon_q = '&'.join(sorted(
        urllib.parse.quote(urllib.parse.unquote(k), safe='-_.~') + '=' + urllib.parse.quote(urllib.parse.unquote(v), safe='-_.~')
        for k, v in (c.split('=', 1) if '=' in c else (c, '') for c in query.split('&') if c)))
    canon_h = ''.join(f'{h}:{" ".join(testa.get(h, "").split())}\n' for h in firmate)
    canon_p = '/'.join(urllib.parse.quote(urllib.parse.unquote(s), safe='-_.~') for s in percorso.split('/'))
    richiesta = '\n'.join([metodo, canon_p, canon_q, canon_h, ';'.join(firmate), hashlib.sha256(corpo).hexdigest()])
    ambito = f'{ambito_giorno}/{regione}/{servizio}/aws4_request'
    da_firmare = '\n'.join(['AWS4-HMAC-SHA256', data, ambito, hashlib.sha256(richiesta.encode()).hexdigest()])
    k = hmac.new(('AWS4' + SES_SEG).encode(), ambito_giorno.encode(), hashlib.sha256).digest()
    for x in (regione, servizio, 'aws4_request'):
        k = hmac.new(k, x.encode(), hashlib.sha256).digest()
    return hmac.new(k, da_firmare.encode(), hashlib.sha256).hexdigest()


class Api(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def rispondi(self, codice, dati):
        b = json.dumps(dati).encode()
        self.send_response(codice)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(b)))
        self.end_headers()
        self.wfile.write(b)

    def do_POST(self):
        corpo = self.rfile.read(int(self.headers.get('Content-Length') or 0))
        u = urllib.parse.urlsplit(self.path)
        if u.path == '/v3/smtp/email':
            if self.headers.get('api-key') != BREVO:
                return self.rispondi(401, {'code': 'unauthorized', 'message': 'Key not found'})
            j = json.loads(corpo)
            if any('rifiuta' in t.get('email', '') for t in j.get('to', [])):
                return self.rispondi(400, {'code': 'invalid_parameter', 'message': 'email is not valid in to'})
            j['_allegati'] = [{'nome': a['name'], 'dati': base64.b64decode(a['content']).decode('utf-8', 'replace')} for a in j.get('attachment', [])]
            n = salva('brevo', j)
            return self.rispondi(201, {'messageId': f'<finto-{n}@smtp-relay.mailin.fr>'})
        if u.path == '/v2/email/outbound-emails':
            aut = self.headers.get('Authorization', '')
            try:
                cred = aut.split('Credential=')[1].split(',')[0]
                firmate = aut.split('SignedHeaders=')[1].split(',')[0].split(';')
                firma = aut.split('Signature=')[1].strip()
                chi, giorno, regione, servizio, _ = cred.split('/')
            except (IndexError, ValueError):
                return self.rispondi(403, {'message': 'Missing Authentication Token'})
            testa = {k.lower(): v for k, v in self.headers.items()}
            attesa = firma_sigv4('POST', u.path, u.query, testa, corpo, giorno, regione, servizio, firmate, self.headers.get('X-Amz-Date', ''))
            if chi != SES_ID or servizio != 'ses' or not hmac.compare_digest(attesa, firma) or not self.headers.get('X-Amz-Date', '').startswith(giorno):
                return self.rispondi(403, {'message': 'The request signature we calculated does not match the signature you provided.'})
            j = json.loads(corpo)
            raw = base64.b64decode(j['Content']['Raw']['Data'])
            if any('rifiuta' in t for t in j['Destination']['ToAddresses']):
                return self.rispondi(400, {'message': 'Illegal address'})
            dati = {'regione': regione, 'FromEmailAddress': j.get('FromEmailAddress'), 'to': j['Destination']['ToAddresses'],
                    'EmailTags': j.get('EmailTags', []), 'ConfigurationSetName': j.get('ConfigurationSetName', ''), 'firmate': firmate}
            dati.update(allegati_mime(raw))
            n = salva('ses', dati, raw)
            return self.rispondi(200, {'MessageId': f'finto-{n}-ses'})
        if u.path.startswith('/v3/') and u.path.endswith('/messages.mime'):
            atteso = 'Basic ' + base64.b64encode(f'api:{MAILGUN}'.encode()).decode()
            if self.headers.get('Authorization') != atteso:
                return self.rispondi(401, {'message': 'Invalid private key'})
            ct = self.headers.get('Content-Type', '')
            m = email.message_from_bytes(b'Content-Type: ' + ct.encode() + b'\r\n\r\n' + corpo, policy=email.policy.default)
            campi, raw = {}, b''
            for p in m.iter_parts():
                nome = p.get_param('name', header='content-disposition')
                if nome == 'message':
                    raw = p.get_payload(decode=True) or p.get_content().as_bytes()
                else:
                    campi[nome] = p.get_content().strip()
            if 'rifiuta' in campi.get('to', ''):
                return self.rispondi(400, {'message': "to parameter is not a valid address"})
            dati = {'dominio': u.path.split('/')[2], 'campi': campi}
            dati.update(allegati_mime(raw))
            n = salva('mailgun', dati, raw)
            return self.rispondi(200, {'id': f'<finto-{n}@mg>', 'message': 'Queued. Thank you.'})
        return self.rispondi(404, {'message': 'not found'})


ThreadingHTTPServer(('127.0.0.1', PORTA), Api).serve_forever()
