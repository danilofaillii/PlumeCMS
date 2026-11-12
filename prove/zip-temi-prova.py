# Costruisce gli zip di tema per prove/prova-carica-temi.js: uno buono, uno con PHP, e quelli che il pannello deve
# rifiutare (percorsi che escono, file nascosti, tipi vietati, zip bomba...). Mai nel prodotto.
# uso: python3 -I zip-temi-prova.py <cartella di uscita>
import json, os, sys, time, zipfile

OUT = sys.argv[1]
os.makedirs(OUT, exist_ok=True)


def zip_(nome, voci):
    """voci: {nome nello zip: contenuto (str o bytes)}. I file escono 0644."""
    with zipfile.ZipFile(os.path.join(OUT, nome), 'w', zipfile.ZIP_DEFLATED) as z:
        for n, d in voci.items():
            i = zipfile.ZipInfo(n, date_time=time.localtime()[:6])
            i.external_attr = 0o644 << 16
            i.compress_type = zipfile.ZIP_DEFLATED
            z.writestr(i, d.encode('utf-8') if isinstance(d, str) else d)


def tema(slug, nome, extra=None):
    v = {f'{slug}/tema.json': json.dumps({'nome': nome, 'versione': '1.0', 'autore': 'Collaudo', 'descrizione': f'Tema di collaudo {nome}.',
                                           'css': 'tema.css', 'stile': 'stile.json'}),
         f'{slug}/stile.json': json.dumps({'colori': {'sistema': {'primario': '#0f766e', 'sfondo': '#ffffff', 'testo': '#111111'}}}),
         f'{slug}/tema.css': ':root{--r:4px}\n'}
    v.update(extra or {})
    return v


zip_('buono.zip', tema('nuvola', 'Nuvola'))
zip_('radice.zip', {k.split('/', 1)[1]: d for k, d in tema('x', 'Senza cartella', {}).items()} | {'tema.json': json.dumps({'nome': 'Senza cartella', 'slug': 'senza-cartella'})})
zip_('con-php.zip', tema('tuono', 'Tuono', {'tuono/funzioni.php': "<?php\nreturn ['filtri' => []];\n"}))
zip_('esce.zip', tema('fuga', 'Fuga', {'fuga/../../includes/fuga.php': '<?php echo 1;'}))
zip_('nascosto.zip', tema('ombra', 'Ombra', {'ombra/.htaccess': 'SetHandler application/x-httpd-php'}))
zip_('user-ini.zip', tema('ombra', 'Ombra', {'ombra/.user.ini': 'auto_prepend_file=/etc/passwd'}))
zip_('svg.zip', tema('vettore', 'Vettore', {'vettore/logo.svg': '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'}))
zip_('phtml.zip', tema('vecchio', 'Vecchio', {'vecchio/shell.phtml': '<?php system($_GET["c"]);'}))
zip_('senza-tema-json.zip', {'qualcosa/stile.json': '{}', 'qualcosa/tema.css': ''})
zip_('due-cartelle.zip', tema('uno', 'Uno') | tema('due', 'Due'))
zip_('bomba.zip', tema('bomba', 'Bomba', {'bomba/pieno.css': b' ' * (9 * 1048576)}))
zip_('json-rotto.zip', {'rotto-json/tema.json': '{nome: senza virgolette'})
print('zip scritti in', OUT)
