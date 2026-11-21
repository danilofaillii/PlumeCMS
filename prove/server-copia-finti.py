#!/usr/bin/env python3
"""Server finti per prove/prova-addon-copia-altrove.js (2.0.0): una destinazione FTP, FTPS o WebDAV in locale, con
nome utente e password, che scrive i file in <cartella>. Mai su un server vero.
Servono pyftpdlib (FTP, FTPS con pyopenssl) e wsgidav con cheroot (WebDAV): pip install --user pyftpdlib pyopenssl wsgidav cheroot
uso: python3 prove/server-copia-finti.py ftp|ftps|webdav <porta> <cartella> <utente> <password> [porte passive, es. 8208-8208]
(si ferma con un segnale)
"""
import os
import subprocess
import sys
import tempfile

TIPO, PORTA, CARTELLA, UTENTE, PASSWORD = sys.argv[1], int(sys.argv[2]), sys.argv[3], sys.argv[4], sys.argv[5]
os.makedirs(CARTELLA, exist_ok=True)

if TIPO in ('ftp', 'ftps'):
    from pyftpdlib.authorizers import DummyAuthorizer
    from pyftpdlib.servers import FTPServer
    aut = DummyAuthorizer()
    aut.add_user(UTENTE, PASSWORD, CARTELLA, perm='elradfmwMT')
    if TIPO == 'ftps':
        from pyftpdlib.handlers import TLS_FTPHandler as Gestore
        d = tempfile.mkdtemp()
        cert = os.path.join(d, 'cert.pem')
        subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', cert, '-out', cert, '-days', '2', '-subj', '/CN=127.0.0.1'], check=True, capture_output=True)
        Gestore.certfile = cert
        Gestore.tls_control_required = True
        Gestore.tls_data_required = True
    else:
        from pyftpdlib.handlers import FTPHandler as Gestore
    Gestore.authorizer = aut
    if len(sys.argv) > 6:
        da, a = (int(x) for x in sys.argv[6].split('-'))
        Gestore.passive_ports = range(da, a + 1)
    s = FTPServer(('127.0.0.1', PORTA), Gestore)
    s.serve_forever()
elif TIPO == 'webdav':
    from cheroot import wsgi
    from wsgidav.wsgidav_app import WsgiDAVApp
    cfg = {
        'host': '127.0.0.1', 'port': PORTA,
        'provider_mapping': {'/dav': CARTELLA},
        'simple_dc': {'user_mapping': {'*': {UTENTE: {'password': PASSWORD}}}},
        'http_authenticator': {'accept_basic': True, 'accept_digest': False, 'default_to_digest': False, 'trusted_auth_header': None},
        'verbose': 1, 'logging': {'enable_loggers': []}, 'property_manager': True, 'lock_storage': True,
    }
    app = WsgiDAVApp(cfg)
    server = wsgi.Server(('127.0.0.1', PORTA), app)
    server.start()
else:
    sys.exit('tipo sconosciuto: ' + TIPO)
