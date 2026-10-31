#!/usr/bin/env python3
"""Server finti di Google Drive e di Dropbox per prove/prova-addon-copia-altrove.js e prove/attacchi-addon-a.js (2.1).

Fanno quello che fanno i servizi veri per quello che usa l'addon Copia del sito altrove, con le stesse regole:
  - OAuth 2: pagina del permesso (Consenti / Annulla), redirect URI confrontato carattere per carattere, codice valido una
    volta e per 60 secondi, PKCE S256 controllato, client id e segreto controllati, refresh token, access token che scade
    (vita scelta dalla prova), invalid_grant per un refresh tolto, revoca;
  - Google Drive v3: about, files (crea cartella, cerca con q, elenca a pagine con nextPageToken, legge, cancella), upload
    "resumable" (sessione con Location, pezzi multipli di 256 KB con Content-Range, 308 con Range, stato con bytes */N,
    un pezzo che si può far fallire una volta con 503 per provare la ripresa);
  - Dropbox v2: users/get_current_account, files/list_folder (+continue, a pagine), files/delete_v2, files/upload,
    upload_session/start, append_v2 (incorrect_offset), finish, auth/token/revoke, errori 401 expired_access_token e 409.
Il CMS li raggiunge con CMS_COPIA_ALTROVE_CLOUD_PROVA: https://<host>/<percorso> diventa http://127.0.0.1:<porta>/<host>/<percorso>.
Mai su un servizio vero.

Per la prova: GET /_stato (file, refresh fatti, pezzi ricevuti, testate viste) e POST /_imposta {"vita": s, "guasto": n,
"pagina": n} (vita dell'accesso in secondi, numero del pezzo che fallisce una volta, quanti file per pagina negli elenchi).

uso: python3 -I prove/server-cloud-finti.py gdrive|dropbox <porta> <cartella> <redirect URI ammessi, separati da virgola>
"""
import base64
import hashlib
import html
import json
import os
import secrets
import sys
import threading
import time
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

SERVIZIO, PORTA, CARTELLA = sys.argv[1], int(sys.argv[2]), sys.argv[3]
REDIRECT = [x for x in sys.argv[4].split(',') if x]
os.makedirs(CARTELLA, exist_ok=True)
CLIENT = {'gdrive': ('prova-plumecms.apps.googleusercontent.com', 'GOCSPX-prova-segreto-2026'),
          'dropbox': ('provadropbox2026', 'segretodropbox2026')}[SERVIZIO]
BLOCCO = threading.Lock()
S = {
    'vita': 3600, 'guasto': 0, 'pagina': 3,
    'codici': {},      # codice -> {redirect, challenge, scade, scope}
    'refresh': {},     # refresh -> {revocato}
    'accessi': {},     # access -> {scade, refresh}
    'refresh_fatti': 0, 'pezzi': 0, 'guasti_fatti': 0, 'sessioni': {}, 'file': {}, 'prossimo': 1,
    'testate_auth': 0, 'token_in_query': 0, 'scambi': 0, 'permessi_negati': 0,
}
ACCOUNT = {'gdrive': {'displayName': 'Mario Prova', 'emailAddress': 'mario.prova@gmail.com'},
           'dropbox': {'name': 'Mario Prova', 'email': 'mario.prova@esempio.it'}}[SERVIZIO]


def b64url(b):
    return base64.urlsafe_b64encode(b).decode().rstrip('=')


class Gestore(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, *a):
        pass

    # ---------------------------------------------------------------- risposte
    def manda(self, codice, corpo=b'', tipo='application/json', testate=None):
        if isinstance(corpo, (dict, list)) or corpo is None:
            corpo = json.dumps(corpo).encode()
        elif isinstance(corpo, str):
            corpo = corpo.encode()
        self.send_response(codice)
        for k, v in (testate or {}).items():
            self.send_header(k, v)
        if corpo or codice not in (204, 304):
            self.send_header('Content-Type', tipo)
        self.send_header('Content-Length', str(len(corpo)))
        self.end_headers()
        self.wfile.write(corpo)

    def corpo(self):
        n = int(self.headers.get('Content-Length') or 0)
        return self.rfile.read(n) if n else b''

    def parti(self):
        u = urllib.parse.urlsplit(self.path)
        return u.path, dict(urllib.parse.parse_qsl(u.query, keep_blank_values=True))

    def token(self):
        """L'accesso dalla testata Authorization: None se manca, False se scaduto o non valido."""
        a = self.headers.get('Authorization', '')
        if not a.startswith('Bearer '):
            return None
        S['testate_auth'] += 1
        t = S['accessi'].get(a[7:])
        if not t or t['scade'] < time.time() or S['refresh'].get(t['refresh'], {}).get('revocato'):
            return False
        return t

    # ---------------------------------------------------------------- OAuth comune
    def pagina_permesso(self, q, nome):
        cid, rid = q.get('client_id', ''), q.get('redirect_uri', '')
        if cid != CLIENT[0]:
            return self.manda(400, '<h1>Errore 401: invalid_client</h1><p>The OAuth client was not found.</p>', 'text/html; charset=utf-8')
        if rid not in REDIRECT:
            return self.manda(400, '<h1>Errore 400: redirect_uri_mismatch</h1><p>' + html.escape(rid) + '</p>', 'text/html; charset=utf-8')
        if q.get('response_type') != 'code' or q.get('code_challenge_method') != 'S256' or not q.get('code_challenge') or not q.get('state'):
            return self.manda(400, '<h1>invalid_request</h1>', 'text/html; charset=utf-8')
        if SERVIZIO == 'gdrive' and ('drive.file' not in q.get('scope', '') or q.get('access_type') != 'offline'):
            return self.manda(400, '<h1>invalid_scope</h1>', 'text/html; charset=utf-8')
        if SERVIZIO == 'dropbox' and q.get('token_access_type') != 'offline':
            return self.manda(400, '<h1>token_access_type</h1>', 'text/html; charset=utf-8')
        dati = html.escape(urllib.parse.urlencode(q))
        pag = ('<!doctype html><meta charset="utf-8"><title>' + nome + ' (finto)</title><h1>' + nome + ' finto</h1>'
               '<p>Accesso come <b>' + html.escape(ACCOUNT.get('emailAddress', ACCOUNT.get('email'))) + '</b>. L\'app vuole salvare le copie del sito.</p>'
               '<form method="get" action="consenso"><input type="hidden" name="q" value="' + dati + '">'
               '<button id="consenti" name="scelta" value="si">Consenti</button> <button id="annulla" name="scelta" value="no">Annulla</button></form>')
        self.manda(200, pag, 'text/html; charset=utf-8')

    def consenso(self, q):
        orig = dict(urllib.parse.parse_qsl(q.get('q', '')))
        rid = orig.get('redirect_uri', '')
        if rid not in REDIRECT:
            return self.manda(400, 'redirect_uri_mismatch', 'text/plain')
        if q.get('scelta') != 'si':
            S['permessi_negati'] += 1
            dove = rid + '?' + urllib.parse.urlencode({'error': 'access_denied', 'state': orig.get('state', '')})
        else:
            codice = '4/' + secrets.token_urlsafe(24)
            S['codici'][codice] = {'redirect': rid, 'challenge': orig.get('code_challenge'), 'scade': time.time() + 60, 'scope': orig.get('scope', '')}
            dove = rid + '?' + urllib.parse.urlencode({'code': codice, 'state': orig.get('state', ''), **({'scope': orig.get('scope', '')} if SERVIZIO == 'gdrive' else {})})
        self.manda(302, b'', 'text/plain', {'Location': dove})

    def token_endpoint(self):
        if 'application/x-www-form-urlencoded' not in self.headers.get('Content-Type', ''):
            return self.manda(400, {'error': 'invalid_request', 'error_description': 'content type'})
        f = dict(urllib.parse.parse_qsl(self.corpo().decode()))
        cid, seg = f.get('client_id', ''), f.get('client_secret', '')
        a = self.headers.get('Authorization', '')
        if a.startswith('Basic '):
            cid, seg = base64.b64decode(a[6:]).decode().split(':', 1)
        if (cid, seg) != CLIENT:
            return self.manda(401, {'error': 'invalid_client', 'error_description': 'The OAuth client was not found.'})
        g = f.get('grant_type')
        if g == 'authorization_code':
            c = S['codici'].pop(f.get('code', ''), None)
            if not c or c['scade'] < time.time():
                return self.manda(400, {'error': 'invalid_grant', 'error_description': 'Bad code'})
            if f.get('redirect_uri') != c['redirect']:
                return self.manda(400, {'error': 'redirect_uri_mismatch'})
            if b64url(hashlib.sha256(f.get('code_verifier', '').encode()).digest()) != c['challenge']:
                return self.manda(400, {'error': 'invalid_grant', 'error_description': 'code_verifier'})
            S['scambi'] += 1
            refresh = ('1//' if SERVIZIO == 'gdrive' else '') + secrets.token_urlsafe(40)
            S['refresh'][refresh] = {'revocato': False}
            acc = self.nuovo_accesso(refresh)
            r = {'access_token': acc, 'expires_in': S['vita'], 'refresh_token': refresh, 'token_type': 'Bearer' if SERVIZIO == 'gdrive' else 'bearer'}
            if SERVIZIO == 'gdrive':
                r['scope'] = c['scope']
            else:
                r.update({'account_id': 'dbid:PROVA', 'uid': '42', 'scope': 'account_info.read files.content.read files.content.write files.metadata.read'})
            return self.manda(200, r)
        if g == 'refresh_token':
            rt = f.get('refresh_token', '')
            if rt not in S['refresh'] or S['refresh'][rt]['revocato']:
                return self.manda(400, {'error': 'invalid_grant', 'error_description': 'Token has been expired or revoked.'})
            S['refresh_fatti'] += 1
            return self.manda(200, {'access_token': self.nuovo_accesso(rt), 'expires_in': S['vita'], 'token_type': 'Bearer'})
        return self.manda(400, {'error': 'unsupported_grant_type'})

    def nuovo_accesso(self, refresh):
        acc = ('ya29.' if SERVIZIO == 'gdrive' else 'sl.') + secrets.token_urlsafe(48)
        S['accessi'][acc] = {'scade': time.time() + S['vita'], 'refresh': refresh}
        return acc

    def revoca(self, refresh):
        if refresh in S['refresh']:
            S['refresh'][refresh]['revocato'] = True

    # ---------------------------------------------------------------- file sul disco
    def scrivi_file(self, nome, padre, dati, extra=None):
        fid = ('id' if SERVIZIO == 'gdrive' else 'id:') + str(S['prossimo'])
        S['prossimo'] += 1
        sicuro = fid.replace(':', '_')
        with open(os.path.join(CARTELLA, sicuro), 'wb') as h:
            h.write(dati)
        S['file'][fid] = {'id': fid, 'name': nome, 'parent': padre, 'size': len(dati), 'mime': (extra or {}).get('mime', 'application/octet-stream'),
                          'sha256': hashlib.sha256(dati).hexdigest(), 'trashed': False}
        return S['file'][fid]

    def togli(self, fid):
        f = S['file'].pop(fid, None)
        if f and f['mime'] != 'application/vnd.google-apps.folder':
            try:
                os.unlink(os.path.join(CARTELLA, fid.replace(':', '_')))
            except OSError:
                pass
        return f

    # ---------------------------------------------------------------- metodi HTTP
    def do_GET(self):
        self.gira('GET')

    def do_POST(self):
        self.gira('POST')

    def do_PUT(self):
        self.gira('PUT')

    def do_DELETE(self):
        self.gira('DELETE')

    def gira(self, metodo):
        with BLOCCO:
            try:
                p, q = self.parti()
                if any(k in q for k in ('access_token', 'refresh_token')):
                    S['token_in_query'] += 1
                if p == '/_stato':
                    return self.manda(200, {k: v for k, v in S.items() if k not in ('codici', 'accessi', 'refresh', 'sessioni')}
                                      | {'refresh_attivi': sum(1 for x in S['refresh'].values() if not x['revocato']), 'refresh_revocati': sum(1 for x in S['refresh'].values() if x['revocato'])})
                if p == '/_imposta' and metodo == 'POST':
                    S.update({k: v for k, v in json.loads(self.corpo() or b'{}').items() if k in ('vita', 'guasto', 'pagina')})
                    if 'scadi' in q:  # tutti gli accessi scaduti adesso
                        for t in S['accessi'].values():
                            t['scade'] = 0
                    return self.manda(200, {'ok': True})
                if p == '/_metti' and metodo == 'POST':  # un file messo da "qualcun altro" nella cartella
                    m = json.loads(self.corpo() or b'{}')
                    f = self.scrivi_file(m['name'], m['parent'], b'non toccare')
                    return self.manda(200, {'id': f['id']})
                if SERVIZIO == 'gdrive':
                    return self.google(metodo, p, q)
                return self.dropbox(metodo, p, q)
            except Exception as e:  # una prova non deve mai bloccarsi su un errore del server finto
                return self.manda(500, {'error': {'code': 500, 'message': 'finto: ' + repr(e)}})

    # ---------------------------------------------------------------- Google
    def google(self, metodo, p, q):
        if p == '/accounts.google.com/o/oauth2/v2/auth' and metodo == 'GET':
            return self.pagina_permesso(q, 'Google')
        if p == '/accounts.google.com/o/oauth2/v2/consenso':
            return self.consenso(q)
        if p == '/oauth2.googleapis.com/token' and metodo == 'POST':
            return self.token_endpoint()
        if p == '/oauth2.googleapis.com/revoke' and metodo == 'POST':
            t = dict(urllib.parse.parse_qsl(self.corpo().decode())).get('token', '')
            if t in S['refresh']:
                self.revoca(t)
                return self.manda(200, {})
            return self.manda(400, {'error': 'invalid_token'})
        if not p.startswith('/www.googleapis.com/'):
            return self.manda(404, {'error': {'code': 404, 'message': 'Not Found'}})
        t = self.token()
        sessione = q.get('upload_id')
        if not t and not (sessione and metodo == 'PUT'):
            return self.manda(401, {'error': {'code': 401, 'message': 'Request had invalid authentication credentials.', 'status': 'UNAUTHENTICATED'}})
        r = p[len('/www.googleapis.com'):]
        if r == '/drive/v3/about' and metodo == 'GET':
            return self.manda(200, {'user': ACCOUNT})
        if r == '/drive/v3/files' and metodo == 'POST':
            m = json.loads(self.corpo() or b'{}')
            if m.get('mimeType') != 'application/vnd.google-apps.folder':
                return self.manda(400, {'error': {'code': 400, 'message': 'solo cartelle qui'}})
            f = self.scrivi_file(m.get('name', ''), (m.get('parents') or ['root'])[0], b'', {'mime': m['mimeType']})
            return self.manda(200, {'id': f['id'], 'name': f['name'], 'mimeType': f['mime']})
        if r == '/drive/v3/files' and metodo == 'GET':
            return self.google_elenco(q)
        if r.startswith('/drive/v3/files/'):
            fid = urllib.parse.unquote(r[len('/drive/v3/files/'):])
            f = S['file'].get(fid)
            if not f:
                return self.manda(404, {'error': {'code': 404, 'message': 'File not found: ' + fid}})
            if metodo == 'GET':
                return self.manda(200, {'id': f['id'], 'name': f['name'], 'mimeType': f['mime'], 'trashed': f['trashed']})
            if metodo == 'DELETE':
                self.togli(fid)
                return self.manda(204, b'')
        if r == '/upload/drive/v3/files' and q.get('uploadType') == 'resumable':
            if metodo == 'POST':
                n = self.headers.get('X-Upload-Content-Length')
                m = json.loads(self.corpo() or b'{}')
                if n is None or not m.get('name') or not m.get('parents'):
                    return self.manda(400, {'error': {'code': 400, 'message': 'manca X-Upload-Content-Length, name o parents'}})
                if m['parents'][0] not in S['file']:
                    return self.manda(404, {'error': {'code': 404, 'message': 'File not found: parent'}})
                sid = secrets.token_urlsafe(16)
                S['sessioni'][sid] = {'nome': m['name'], 'padre': m['parents'][0], 'totale': int(n), 'dati': bytearray(), 'pezzi': 0}
                dove = 'http://127.0.0.1:%d/www.googleapis.com/upload/drive/v3/files?uploadType=resumable&upload_id=%s' % (PORTA, sid)
                return self.manda(200, b'', 'text/plain', {'Location': dove, 'X-GUploader-UploadID': sid})
            if metodo == 'PUT':
                return self.google_pezzo(sessione)
        return self.manda(404, {'error': {'code': 404, 'message': 'Not Found ' + r}})

    def google_elenco(self, q):
        cond = q.get('q', '')
        padre = cond.split("' in parents")[0].split("'")[-1] if "' in parents" in cond else None
        nome = cond.split("name = '")[1].split("'")[0] if "name = '" in cond else None
        cart = "mimeType = 'application/vnd.google-apps.folder'" in cond
        tutti = sorted((f for f in S['file'].values() if (padre is None or f['parent'] == padre) and not f['trashed']
                        and (nome is None or f['name'] == nome) and (not cart or f['mime'] == 'application/vnd.google-apps.folder')), key=lambda f: f['id'])
        per = min(int(q.get('pageSize', 100)), int(S['pagina']))
        da = int(q.get('pageToken') or 0)
        r = {'files': [{'id': f['id'], 'name': f['name']} for f in tutti[da:da + per]]}
        if da + per < len(tutti):
            r['nextPageToken'] = str(da + per)
        return self.manda(200, r)

    def google_pezzo(self, sid):
        s = S['sessioni'].get(sid)
        if not s:
            return self.manda(404, {'error': {'code': 404, 'message': 'upload session not found'}})
        cr = self.headers.get('Content-Range', '')
        dati = self.corpo()
        if cr.startswith('bytes */'):
            return self.google_a_che_punto(s)
        try:
            a, b = cr[len('bytes '):].split('/')[0].split('-')
            a, b = int(a), int(b)
        except ValueError:
            return self.manda(400, {'error': {'code': 400, 'message': 'Content-Range'}})
        if a != len(s['dati']) or b - a + 1 != len(dati):
            return self.manda(400, {'error': {'code': 400, 'message': 'pezzo fuori posto'}})
        ultimo = b + 1 == s['totale']
        if not ultimo and len(dati) % 262144:
            return self.manda(400, {'error': {'code': 400, 'message': 'chunk size must be a multiple of 256 KiB'}})
        S['pezzi'] += 1
        s['pezzi'] += 1
        if S['guasto'] and S['pezzi'] == S['guasto']:
            S['guasto'] = 0
            S['guasti_fatti'] += 1
            return self.manda(503, {'error': {'code': 503, 'message': 'Service Unavailable (finto)'}})
        s['dati'] += dati
        if ultimo:
            f = self.scrivi_file(s['nome'], s['padre'], bytes(s['dati']), {'mime': 'application/zip'})
            del S['sessioni'][sid]
            return self.manda(200, {'id': f['id'], 'name': f['name'], 'size': str(f['size'])})
        return self.google_a_che_punto(s)

    def google_a_che_punto(self, s):
        t = {'Range': 'bytes=0-%d' % (len(s['dati']) - 1)} if s['dati'] else {}
        return self.manda(308, b'', 'text/plain', t)

    # ---------------------------------------------------------------- Dropbox
    def errore_db(self, codice, sommario, errore):
        return self.manda(codice, {'error_summary': sommario, 'error': errore})

    def dropbox(self, metodo, p, q):
        if p == '/www.dropbox.com/oauth2/authorize' and metodo == 'GET':
            return self.pagina_permesso(q, 'Dropbox')
        if p == '/www.dropbox.com/oauth2/consenso':
            return self.consenso(q)
        if p == '/api.dropboxapi.com/oauth2/token' and metodo == 'POST':
            return self.token_endpoint()
        if metodo != 'POST':
            return self.manda(405, b'')
        t = self.token()
        if t is None:
            return self.manda(400, 'Error in call: missing Authorization header', 'text/plain')
        if t is False:
            return self.errore_db(401, 'expired_access_token/', {'.tag': 'expired_access_token'})
        if p.startswith('/api.dropboxapi.com/2/'):
            if 'application/json' not in self.headers.get('Content-Type', '') and self.headers.get('Content-Length', '0') != '0':
                return self.manda(400, 'Error in call: bad content type', 'text/plain')
            raw = self.corpo()
            arg = json.loads(raw) if raw and raw != b'null' else None
            return self.dropbox_rpc(p[len('/api.dropboxapi.com/2/'):], arg, t)
        if p.startswith('/content.dropboxapi.com/2/'):
            if self.headers.get('Content-Type') != 'application/octet-stream':
                return self.manda(400, 'Error in call: bad content type', 'text/plain')
            try:
                arg = json.loads(self.headers.get('Dropbox-API-Arg', ''))
            except ValueError:
                return self.manda(400, 'Error in call: Dropbox-API-Arg', 'text/plain')
            return self.dropbox_contenuto(p[len('/content.dropboxapi.com/2/'):], arg, self.corpo())
        return self.manda(404, b'')

    def meta(self, f):
        return {'.tag': 'file', 'name': f['name'], 'path_lower': f['parent'].lower() + '/' + f['name'].lower(), 'path_display': f['parent'] + '/' + f['name'],
                'id': f['id'], 'size': f['size'], 'content_hash': f['sha256']}

    def per_percorso(self, percorso):
        padre, _, nome = percorso.rpartition('/')
        for f in S['file'].values():
            if f['parent'].lower() == padre.lower() and f['name'].lower() == nome.lower():
                return f
        return None

    def dropbox_rpc(self, m, arg, t):
        if m == 'users/get_current_account':
            return self.manda(200, {'account_id': 'dbid:PROVA', 'name': {'display_name': ACCOUNT['name']}, 'email': ACCOUNT['email']})
        if m == 'auth/token/revoke':
            self.revoca(t['refresh'])
            return self.manda(200, None)
        if m == 'files/list_folder':
            percorso = (arg or {}).get('path', '')
            if percorso and not any(f['parent'].lower() == percorso.lower() for f in S['file'].values()):
                return self.errore_db(409, 'path/not_found/..', {'.tag': 'path', 'path': {'.tag': 'not_found'}})
            voci = sorted((f for f in S['file'].values() if f['parent'].lower() == percorso.lower()), key=lambda f: f['id'])
            return self.dropbox_pagina(voci, 0, percorso)
        if m == 'files/list_folder/continue':
            c = json.loads(base64.b64decode((arg or {}).get('cursor', '')).decode())
            voci = sorted((f for f in S['file'].values() if f['parent'].lower() == c['p'].lower()), key=lambda f: f['id'])
            return self.dropbox_pagina(voci, c['da'], c['p'])
        if m == 'files/delete_v2':
            f = self.per_percorso((arg or {}).get('path', ''))
            if not f:
                return self.errore_db(409, 'path_lookup/not_found/', {'.tag': 'path_lookup', 'path_lookup': {'.tag': 'not_found'}})
            self.togli(f['id'])
            return self.manda(200, {'metadata': self.meta(f)})
        return self.manda(400, 'Unknown API function: ' + m, 'text/plain')

    def dropbox_pagina(self, voci, da, percorso):
        per = int(S['pagina'])
        parte = voci[da:da + per]
        piu = da + per < len(voci)
        return self.manda(200, {'entries': [self.meta(f) for f in parte], 'has_more': piu,
                                'cursor': base64.b64encode(json.dumps({'p': percorso, 'da': da + per}).encode()).decode()})

    def salva_db(self, commit, dati):
        percorso = commit.get('path', '')
        if not percorso.startswith('/') or commit.get('mode') not in ('add', 'overwrite'):
            return self.errore_db(400, 'path/malformed_path/', {'.tag': 'path', 'reason': {'.tag': 'malformed_path'}})
        vecchio = self.per_percorso(percorso)
        if vecchio:
            if commit['mode'] == 'add' and not commit.get('autorename'):
                return self.errore_db(409, 'path/conflict/file/', {'.tag': 'path', 'reason': {'.tag': 'conflict'}})
            self.togli(vecchio['id'])
        padre, _, nome = percorso.rpartition('/')
        f = self.scrivi_file(nome, padre, dati)
        return self.manda(200, self.meta(f))

    def dropbox_contenuto(self, m, arg, dati):
        if m == 'files/upload':
            if len(dati) > 150 * 1024 * 1024:
                return self.errore_db(400, 'too_large', {'.tag': 'other'})
            return self.salva_db(arg, dati)
        if m == 'files/upload_session/start':
            sid = 'AAA' + secrets.token_urlsafe(16)
            S['sessioni'][sid] = {'dati': bytearray(dati), 'chiusa': bool(arg.get('close'))}
            S['pezzi'] += 1
            return self.manda(200, {'session_id': sid})
        cur = arg.get('cursor', {})
        s = S['sessioni'].get(cur.get('session_id', ''))
        if not s:
            return self.errore_db(409, 'not_found/', {'.tag': 'not_found'})
        if int(cur.get('offset', -1)) != len(s['dati']):
            return self.errore_db(409, 'incorrect_offset/', {'.tag': 'incorrect_offset', 'correct_offset': len(s['dati'])})
        if m == 'files/upload_session/append_v2':
            S['pezzi'] += 1
            if S['guasto'] and S['pezzi'] == S['guasto']:
                # il pezzo arriva ma la risposta si perde: il CMS lo rimanda, e Dropbox dice incorrect_offset
                S['guasto'] = 0
                S['guasti_fatti'] += 1
                s['dati'] += dati
                return self.manda(503, 'Service Unavailable (finto)', 'text/plain')
            s['dati'] += dati
            return self.manda(200, None)
        if m == 'files/upload_session/finish':
            s['dati'] += dati
            del S['sessioni'][cur['session_id']]
            return self.salva_db(arg.get('commit', {}), bytes(s['dati']))
        return self.manda(400, 'Unknown API function: ' + m, 'text/plain')


if __name__ == '__main__':
    ThreadingHTTPServer(('127.0.0.1', PORTA), Gestore).serve_forever()
