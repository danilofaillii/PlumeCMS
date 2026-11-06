#!/usr/bin/env python3
# 2.2.0: "c'è una versione nuova" su HTTPS vero, con la verifica del certificato accesa come sul server (versione_http(),
# includes/versione-nuova.php). Un sito ufficiale finto in HTTPS con un certificato firmato da un'autorità creata qui:
#   1. con l'autorità fra quelle fidate (openssl.cafile): la versione arriva, lo zip si scarica con sha256 e misura giusti;
#   2. senza l'autorità (come un certificato fatto in casa): rifiutato, niente versione;
#   3. un certificato giusto ma per un altro nome (chiamato come 127.0.0.1, il certificato dice localhost): rifiutato;
#   4. un certificato scaduto: rifiutato.
# uso: python3 -I prove/prova-versione-https.py <cartella CMS installato> <pacchetto.zip> [porta]
import hashlib, json, os, ssl, subprocess, sys, tempfile, threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

CMS, ZIP = sys.argv[1], sys.argv[2]
PORTA = int(sys.argv[3]) if len(sys.argv) > 3 else 8643
d = tempfile.mkdtemp(prefix='https-prova-')
o = lambda *a: subprocess.run(['openssl', *a], cwd=d, check=True, capture_output=True)
o('req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', 'ca.key', '-out', 'ca.pem', '-days', '2', '-subj', '/CN=Autorita di prova plumeCMS')
def certificato(nome, scaduto=False):
    o('req', '-newkey', 'rsa:2048', '-nodes', '-keyout', nome + '.key', '-out', nome + '.csr', '-subj', '/CN=localhost')
    open(os.path.join(d, nome + '.ext'), 'w').write('subjectAltName=DNS:localhost\nbasicConstraints=CA:FALSE\n')
    if not scaduto:
        o('x509', '-req', '-in', nome + '.csr', '-CA', 'ca.pem', '-CAkey', 'ca.key', '-CAcreateserial', '-out', nome + '.pem', '-days', '2', '-extfile', nome + '.ext')
        return
    # scaduto: "openssl ca" con le date scelte (valido nel 2020)
    os.makedirs(os.path.join(d, 'ca'), exist_ok=True); open(os.path.join(d, 'ca', 'indice'), 'w').close()
    open(os.path.join(d, 'ca.cnf'), 'w').write('[ca]\ndefault_ca=c\n[c]\ndatabase=ca/indice\nnew_certs_dir=ca\nserial=ca/serie\ndefault_md=sha256\npolicy=p\ncopy_extensions=none\n[p]\ncommonName=supplied\n')
    open(os.path.join(d, 'ca', 'serie'), 'w').write('1000\n')
    o('ca', '-batch', '-config', 'ca.cnf', '-cert', 'ca.pem', '-keyfile', 'ca.key', '-in', nome + '.csr', '-out', nome + '.pem',
      '-startdate', '20200101000000Z', '-enddate', '20200102000000Z', '-extfile', nome + '.ext', '-notext')
certificato('buono')
certificato('scaduto', True)

dati_zip = open(ZIP, 'rb').read()
nome_zip = os.path.basename(ZIP)
versione = nome_zip.split('-')[1]

def server(cert, porta):
    class H(BaseHTTPRequestHandler):
        def log_message(self, *a): pass
        def do_GET(self):
            if self.path.startswith('/api/versione.php'):
                corpo = json.dumps({'serie': 'plumecms-ultima-aggiornamento', 'versione': versione, 'nome': nome_zip,
                                    'sha256': hashlib.sha256(dati_zip).hexdigest(), 'dimensione': len(dati_zip),
                                    'url': f'https://localhost:{porta}/scarica/{nome_zip[:-4]}/'}).encode()
                tipo = 'application/json'
            elif self.path.startswith('/scarica/'):
                corpo, tipo = dati_zip, 'application/zip'
            else:
                self.send_response(404); self.end_headers(); return
            self.send_response(200); self.send_header('Content-Type', tipo); self.send_header('Content-Length', str(len(corpo))); self.end_headers()
            self.wfile.write(corpo)
    s = ThreadingHTTPServer(('127.0.0.1', porta), H)
    c = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER); c.load_cert_chain(os.path.join(d, cert + '.pem'), os.path.join(d, cert + '.key'))
    s.socket = c.wrap_socket(s.socket, server_side=True)
    threading.Thread(target=s.serve_forever, daemon=True).start()
    return s

def chiedi(url, cafile):
    codice = f"""define('CMS_AGGIORNAMENTI_URL', {json.dumps(url)}); $_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/';
require {json.dumps(CMS + '/includes/config.php')}; require_once {json.dumps(CMS + '/includes/aggiornamenti.php')}; require_once {json.dumps(CMS + '/includes/versione-nuova.php')};
try {{ $v = versione_chiedi(); $t = tempnam(sys_get_temp_dir(), 'z'); versione_http($v['url'], $v['dimensione'], 30, $t);
  echo json_encode(['ok' => true, 'versione' => $v['versione'], 'sha' => hash_equals($v['sha256'], hash_file('sha256', $t)), 'misura' => filesize($t) === $v['dimensione']]); @unlink($t);
}} catch (Throwable $e) {{ echo json_encode(['ok' => false, 'errore' => $e->getMessage()]); }}"""
    arg = ['php', '-d', 'openssl.cafile=' + (os.path.join(d, 'ca.pem') if cafile else os.path.join(d, 'nessuna.pem')), '-r', codice]
    r = subprocess.run(arg, capture_output=True, text=True, timeout=60)
    try: return json.loads(r.stdout.strip().splitlines()[-1])
    except Exception: return {'ok': False, 'errore': (r.stdout + r.stderr)[-300:]}

n = ko = 0
def ok(t, c, x=''):
    global n, ko
    n += 1; ko += 0 if c else 1
    print(('OK  ' if c else 'KO  ') + t + (' ' + json.dumps(x, ensure_ascii=False)[:300] if x != '' else ''))

s1 = server('buono', PORTA)
r = chiedi(f'https://localhost:{PORTA}', True)
ok('HTTPS con un certificato valido: la versione arriva e lo zip si scarica intero (sha256 e misura)', r.get('ok') and r.get('versione') == versione and r.get('sha') and r.get('misura'), r)
r = chiedi(f'https://localhost:{PORTA}', False)
ok('certificato di un\'autorità non fidata: rifiutato, e il messaggio parla del certificato', not r.get('ok') and 'certificato' in r.get('errore', ''), r)
r = chiedi(f'https://127.0.0.1:{PORTA}', True)
ok('certificato valido ma per un altro nome: rifiutato, e il messaggio parla del certificato', not r.get('ok') and 'certificato' in r.get('errore', ''), r)
s1.shutdown()
s2 = server('scaduto', PORTA + 1)
r = chiedi(f'https://localhost:{PORTA + 1}', True)
ok('certificato scaduto: rifiutato, e il messaggio parla del certificato', not r.get('ok') and 'certificato' in r.get('errore', ''), r)
s2.shutdown()
r = chiedi('https://localhost:' + str(PORTA + 7), True)
ok('sito spento: "non risponde"', not r.get('ok') and 'non risponde' in r.get('errore', ''), r)
print(f'\n{n - ko} su {n} OK')
sys.exit(1 if ko else 0)
