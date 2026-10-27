#!/usr/bin/env python3
"""
Costruisce i pacchetti del CMS (il prodotto, senza il modulo di danilofailli.it e senza i contenuti).

    python3 tools/cms-pacchetto.py                 scrive releases/cms/<nome>-<versione>.zip (il CMS da estrarre in una cartella vuota)
    python3 tools/cms-pacchetto.py --aggiornamento scrive anche releases/cms/<nome>-<versione>-aggiornamento.zip
                                                   (manifest per il pannello Aggiornamenti: tutti i file del nucleo sotto new/)
    python3 tools/cms-pacchetto.py --sito <commit> <nome>
                                                   il pacchetto per danilofailli.it (nucleo E modulo danilofailli) con i file
                                                   di cms/ cambiati o nuovi dopo <commit>: releases/pacchetti/<nome>.zip
                                                   (codice, SQL di cms/sql/<nome>*.sql, file spostati da togliere) e
                                                   <nome>-b.zip (i .htaccess, che solo il motore nuovo accetta: vedi sotto)

Dentro il prodotto: includes/, admin/, sito/, api/, share/, install/, assets/css, i cron, index.php, VERSIONE, CHANGELOG.md,
README.md, INSTALLA.md, i documenti del CMS in docs/, un .htaccess per la radice del dominio, le cartelle vuote uploads/,
logs/, aggiornamenti/, moduli/. Fuori: moduli/danilofailli/, config.local.php, sql/, data/, test/, test-definizioni/,
privato/, le immagini degli articoli, i file ponte (shim) che rimandano al modulo, i vecchi file di radice del publisher.
Il programma rifiuta il pacchetto se dentro c'è un carattere a 4 byte, la parola "newdf_" fuori da cms.php e db.php,
o un riferimento a danilofailli nel codice del nucleo.
"""
import io, json, os, re, shutil, subprocess, sys, zipfile

QUI = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(QUI)
SITE = os.path.join(REPO, 'cms')
NOME_PRODOTTO = 'plumecms'
AVVISI = []   # provvisorio: CMS_NOME in includes/cms.php

CARTELLE = ['includes', 'admin', 'sito', 'api', 'share', 'install', 'assets/css', 'moduli', 'temi']
FILE_RADICE = ['index.php', 'cron-daily.php', 'cron-newsletter.php', 'VERSIONE', 'CHANGELOG.md', 'README.md', 'INSTALLA.md']
DOCS = ['manuale-tecnico.md', 'manuale-temi.md', 'cms.md', 'cms-moduli.md', 'moduli.md', 'sicurezza.md', 'editor-pagine.md', 'stile-sito.md', 'header-footer-menu.md', 'versioni.md', 'seo.md', 'pannello.md', 'contenuti.md', 'newsletter.md', 'aggiornamenti.md', 'temi.md']
VUOTE = ['uploads', 'logs', 'aggiornamenti', 'moduli', 'temi']
ESCLUDI_PREFISSI = ('moduli/danilofailli/', 'includes/config.local.php', 'install/.installato', 'admin/infographics/')
# I file ponte verso il modulo di danilofailli.it: nel prodotto non c'è il modulo, quindi non ci sono nemmeno loro.
SHIM = {
    'admin': ['test', 'test-statistiche', 'burnout-dashboard', 'social-copy', 'diagnosi-articoli', 'importa-sql', 'import-articles', 'export-articles-sql',
              'generate-images', 'generate-images-static', 'generate-missing-images', 'seed-infographics', 'guida-infografiche'],
    'api': ['test', 'burnout-test', 'burnout-share', 'random-aforisma'],
    'share': ['burnout'],
    'includes': ['test', 'social-pillar', 'notifications', 'editor-articoli'],
}
ESCLUDI_FILE = {f'{c}/{n}.php' for c, ll in SHIM.items() for n in ll} | {'admin/setup.php'}


def errore(msg):
    print('ERRORE:', msg)
    sys.exit(1)


def leggi_versione():
    with open(os.path.join(SITE, 'VERSIONE'), encoding='utf-8') as f:
        return f.read().strip()


def elenco_file():
    out = []
    for c in CARTELLE:
        base = os.path.join(SITE, c)
        if not os.path.isdir(base):
            continue
        for radice, cartelle, file in os.walk(base):
            cartelle[:] = sorted(d for d in cartelle if d not in ('.git', '__pycache__'))
            for f in sorted(file):
                rel = os.path.relpath(os.path.join(radice, f), SITE).replace(os.sep, '/')
                if rel.startswith(ESCLUDI_PREFISSI) or rel in ESCLUDI_FILE or f.endswith(('.log', '.pyc')) or f == '.DS_Store':
                    continue
                out.append(rel)
    for f in FILE_RADICE:
        if os.path.isfile(os.path.join(SITE, f)):
            out.append(f)
        else:
            print(f'avviso: manca {f}')
    for d in DOCS:
        p = f'docs/{d}'
        if os.path.isfile(os.path.join(SITE, p)) and p not in out:
            out.append(p)
    return out


def controlla(rel, dati):
    if not rel.endswith(('.php', '.js', '.css', '.md', '.txt', '.json', '.html')):
        return
    testo = dati.decode('utf-8', errors='replace')
    if re.search(r'[\U00010000-\U0010FFFF]', testo):
        # Le colonne a 3 byte del database tagliano i testi: nei file del codice un'emoji è solo un avviso (icone del menu).
        AVVISI.append(f'carattere a 4 byte in {rel}')
    if rel.endswith('.php') and shutil.which('php'):
        # Sintassi con short_open_tag acceso, come nel PHP standard: un "<?xml" fuori dal codice rompe la pagina (26 ottobre 2026).
        import tempfile
        with tempfile.NamedTemporaryFile(suffix='.php', delete=False) as tmp:
            tmp.write(dati)
        try:
            r = subprocess.run(['php', '-n', '-d', 'short_open_tag=1', '-l', tmp.name], capture_output=True)
        finally:
            os.unlink(tmp.name)
        if r.returncode != 0:
            errore(f'errore di sintassi PHP in {rel} (con short_open_tag acceso): '
                   + (r.stdout + r.stderr).decode('utf-8', 'replace').strip().splitlines()[0])
    if rel.endswith(('.php', '.js')):
        # Si guarda il codice, non i commenti: via le righe che cominciano con //, * o # e i commenti in coda.
        codice = re.sub(r'^\s*(//|\*|/\*|#)[^\n]*', '', testo, flags=re.M)
        codice = re.sub(r'\s//[^\n]*', '', codice)
        if rel.endswith('.php') and 'newdf_' in codice and rel not in ('includes/cms.php', 'includes/db.php'):
            errore(f'prefisso newdf_ scritto nel nucleo: {rel}')
        if 'danilofailli.it' in codice:
            errore(f'riferimento fisso a danilofailli.it nel codice del nucleo: {rel}')


def htaccess_radice():
    with open(os.path.join(SITE, 'install/esempi/htaccess-radice.txt'), encoding='utf-8') as f:
        return f.read()


def scrivi_voce(z, nome, dati):
    """Una voce dello zip con permessi da sito web: file 0644, cartelle 0755. Con writestr() Python mette 0600:
    estratto sul server, il server web (un utente diverso da quello di PHP) non legge CSS, JS e immagini e risponde 403,
    mentre le pagine PHP funzionano. Successo il 26 ottobre 2026 sulla prima installazione vera (pannello senza grafica)."""
    parti = nome.split('/')[:-1]
    for i in range(1, len(parti) + 1):
        cartella = '/'.join(parti[:i]) + '/'
        if cartella not in z.NameToInfo:
            info = zipfile.ZipInfo(cartella, date_time=__import__('time').localtime()[:6])
            info.external_attr = (0o040755 << 16) | 0x10
            z.writestr(info, b'')
    info = zipfile.ZipInfo(nome, date_time=__import__('time').localtime()[:6])
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = 0o100644 << 16
    z.writestr(info, dati)


def scrivi_prodotto(versione, dest):
    file = elenco_file()
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    with zipfile.ZipFile(dest, 'w', zipfile.ZIP_DEFLATED) as z:
        for rel in file:
            with open(os.path.join(SITE, rel), 'rb') as f:
                dati = f.read()
            controlla(rel, dati)
            scrivi_voce(z, rel, dati)
        scrivi_voce(z, '.htaccess', htaccess_radice())
        gia = set(z.namelist())
        for d in VUOTE:
            if f'{d}/.gitkeep' not in gia:
                scrivi_voce(z, f'{d}/.gitkeep', '')
        # sicurezza: PHP spento nella cartella delle immagini (lo stesso file lo scrive la pagina Sicurezza del pannello)
        with open(os.path.join(SITE, 'install/esempi/htaccess-uploads.txt'), encoding='utf-8') as f:
            scrivi_voce(z, 'uploads/.htaccess', f.read())
    return file


def scrivi_aggiornamento(versione, file, dest):
    """Lo stesso codice come pacchetto per il pannello Aggiornamenti: ogni file va in new/<percorso> (la cartella del CMS)."""
    manifest = {
        'nome': f'{NOME_PRODOTTO} {versione}', 'descrizione': f'Aggiornamento del CMS alla versione {versione}: tutti i file del nucleo.',
        'creato': __import__('datetime').date.today().isoformat(),
        'file': [{'da': f'file/{rel}', 'a': f'new/{rel}'} for rel in file if rel != 'README.md' and rel != 'INSTALLA.md'],
        'note': f'Versione {versione}. Il pacchetto sostituisce i file del CMS e lascia moduli, immagini, configurazione e database.',
    }
    with zipfile.ZipFile(dest, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('manifest.json', json.dumps(manifest, ensure_ascii=False, indent=2))
        for rel in file:
            z.write(os.path.join(SITE, rel), f'file/{rel}')


def main():
    versione = leggi_versione()
    if not re.match(r'^\d+\.\d+\.\d+$', versione):
        errore(f'VERSIONE non valida: {versione}')
    cartella = os.path.join(REPO, 'releases', 'cms')
    dest = os.path.join(cartella, f'{NOME_PRODOTTO}-{versione}.zip')
    file = scrivi_prodotto(versione, dest)
    print(f'{os.path.relpath(dest, REPO)}: {len(file)} file, {os.path.getsize(dest) // 1024} KB')
    for a in AVVISI: print('avviso:', a)
    if '--aggiornamento' in sys.argv:
        dest2 = os.path.join(cartella, f'{NOME_PRODOTTO}-{versione}-aggiornamento.zip')
        scrivi_aggiornamento(versione, file, dest2)
        print(f'{os.path.relpath(dest2, REPO)}: pacchetto per il pannello Aggiornamenti, {os.path.getsize(dest2) // 1024} KB')


# ---------------------------------------------------------------------------------------------------------------
# --sito: il pacchetto di aggiornamento per danilofailli.it (il CMS sta in /new/, le immagini in wp-content/uploads).
# Due zip, perche un pacchetto lo applica il motore GIA installato (trappola in CLAUDE.md): quello di prima della 1.1.3
# accetta come file nascosto solo root/.htaccess e rifiuterebbe tutto il pacchetto per un new/.htaccess.
#   <nome>.zip    i file di cms/ cambiati o nuovi dopo <commit>, sotto new/; i file SQL cms/sql/<giorno>-*.sql
#                 citati in SQL_SITO; in "elimina" i vecchi percorsi dei file spostati (rinominati) che nessuno usa piu.
#   <nome>-b.zip  new/.htaccess (cms/.htaccess) e uploads/.htaccess (install/esempi/htaccess-uploads.txt).
# Fuori: install/ (l'installatore non serve a un sito gia installato e lasciarlo raggiungibile e un rischio), docs/,
# sql/ (tranne SQL_SITO), tools/, logs/, aggiornamenti/, data/, README.md, INSTALLA.md, .gitignore, config.local.php.
# ---------------------------------------------------------------------------------------------------------------
SITO_ESCLUDI_PREFISSI = ('install/', 'docs/', 'sql/', 'tools/', 'logs/', 'aggiornamenti/', 'data/', 'test/', 'test-definizioni/')
SITO_ESCLUDI_FILE = {'README.md', 'INSTALLA.md', '.gitignore', 'includes/config.local.php', '.htaccess'}
SITO_HTACCESS = {'new/.htaccess': '.htaccess', 'uploads/.htaccess': 'install/esempi/htaccess-uploads.txt'}


def git(*arg):
    import subprocess
    return subprocess.run(['git', '-C', REPO, *arg], check=True, capture_output=True, text=True).stdout


def sito(da, nome):
    import shutil, subprocess, tempfile
    # Confronto fra <commit> e i file sul disco (anche quelli non ancora committati, e i nuovi non tracciati).
    righe = [r.split('\t') for r in git('diff', '--name-status', '-M', da, '--', 'cms').splitlines() if r]
    righe += [['A', r] for r in git('ls-files', '--others', '--exclude-standard', '--', 'cms').splitlines() if r]
    if git('status', '--porcelain', '--', 'cms', 'tools').strip():
        print('avviso: ci sono modifiche non committate in cms/ o tools/: il pacchetto prende i file dal disco')
    file, elimina = [], []
    for r in righe:
        stato, percorsi = r[0], r[1:]
        nuovo = percorsi[-1][len('cms/'):]
        if stato.startswith('R'):
            vecchio = percorsi[0][len('cms/'):]
            if not vecchio.startswith(SITO_ESCLUDI_PREFISSI):
                elimina.append('new/' + vecchio)
        if stato.startswith('D'):
            print(f'avviso: {nuovo} tolto dal repository: valuta a mano se va in "elimina"')
            continue
        if nuovo.startswith(SITO_ESCLUDI_PREFISSI) or nuovo in SITO_ESCLUDI_FILE or nuovo.endswith(('.log', '.pyc')):
            continue
        if os.path.basename(nuovo) == '.htaccess':
            errore(f'{nuovo}: un .htaccess fuori da quelli previsti in SITO_HTACCESS')
        file.append(nuovo)
    # VERSIONE e CHANGELOG.md servono alla pagina Informazioni anche se non sono cambiati
    for f in ('VERSIONE', 'CHANGELOG.md'):
        if f not in file: file.append(f)
    # un file ponte non va mai tolto: elimina solo i vecchi percorsi che non esistono piu nel repository
    elimina = [e for e in elimina if not os.path.exists(os.path.join(SITE, e[len('new/'):]))]
    sql = sorted(f for f in os.listdir(os.path.join(SITE, 'sql')) if f.startswith(nome[:10]) and f.endswith('.sql'))
    versione = leggi_versione()
    cartella = os.path.join(REPO, 'releases', 'pacchetti')
    lavoro = tempfile.mkdtemp(prefix='pacchetto-sito-')
    try:
        for parte, elenco in (('a', file), ('b', None)):
            base = os.path.join(lavoro, parte)
            os.makedirs(base)
            if parte == 'a':
                for rel in elenco:
                    with open(os.path.join(SITE, rel), 'rb') as f:
                        dati = f.read()
                    if re.search(rb'[\xf0-\xf4][\x80-\xbf]{3}', dati) and rel.endswith(('.php', '.sql', '.json')):
                        AVVISI.append(f'carattere a 4 byte in {rel}')
                    os.makedirs(os.path.dirname(os.path.join(base, 'file', rel)), exist_ok=True)
                    shutil.copy2(os.path.join(SITE, rel), os.path.join(base, 'file', rel))
                os.makedirs(os.path.join(base, 'sql'))
                for q in sql:
                    shutil.copy2(os.path.join(SITE, 'sql', q), os.path.join(base, 'sql', q))
                manifest = {
                    'nome': f'CMS {versione}: nucleo, modulo danilofailli, versioni, menu Editor, sicurezza',
                    'descrizione': f'Porta il sito alla versione {versione} del CMS: il pannello diventa un nucleo piu il modulo di danilofailli.it, '
                                   'arrivano Versioni, il menu Editor, la pagina Sicurezza (2FA, blocco dei tentativi, CSP) e Il mio accesso. '
                                   'Va applicato dopo il pacchetto 15. Subito dopo, il pacchetto ' + nome + '-b.',
                    'creato': __import__('datetime').date.today().isoformat(),
                    'file': [{'da': f'file/{rel}', 'a': f'new/{rel}'} for rel in elenco],
                    'sql': [f'sql/{q}' for q in sql],
                    'elimina': elimina,
                    'note': 'Solo codice e una impostazione: la CSP parte in "solo rapporto". Tabelle e colonne nuove si creano da sole al primo uso. '
                            'Il sito pubblico resta identico. Dopo: carica e applica ' + nome + '-b.zip (i .htaccess).',
                }
            else:
                for dest, src in SITO_HTACCESS.items():
                    os.makedirs(os.path.join(base, 'file'), exist_ok=True)
                    shutil.copy2(os.path.join(SITE, src), os.path.join(base, 'file', dest.replace('/', '-')))
                manifest = {
                    'nome': f'CMS {versione}, parte b: i divieti del web server (.htaccess)',
                    'descrizione': 'Scrive new/.htaccess (dal web non si leggono includes, logs, moduli, cron, config, documenti) e '
                                   'wp-content/uploads/.htaccess (nella cartella delle immagini PHP non si esegue). Solo dopo ' + nome + '.zip.',
                    'creato': __import__('datetime').date.today().isoformat(),
                    'file': [{'da': 'file/' + d.replace('/', '-'), 'a': d} for d in SITO_HTACCESS],
                    'note': 'Lo accetta solo il pannello aggiornato dal pacchetto ' + nome + '.zip: se dice "Destinazione non ammessa", applica prima quello.',
                }
            with open(os.path.join(base, 'manifest.json'), 'w', encoding='utf-8') as f:
                json.dump(manifest, f, ensure_ascii=False, indent=1)
            dest = os.path.join(cartella, nome + ('' if parte == 'a' else '-b') + '.zip')
            subprocess.run([sys.executable, os.path.join(QUI, 'pacchetto.py'), base, dest], check=True)
    finally:
        shutil.rmtree(lavoro, ignore_errors=True)
    print(f'{len(file)} file, {len(sql)} SQL ({", ".join(sql) or "nessuno"}), {len(elimina)} da togliere')
    for a in AVVISI: print('avviso:', a)


if __name__ == '__main__':
    if len(sys.argv) >= 4 and sys.argv[1] == '--sito':
        sito(sys.argv[2], sys.argv[3])
    else:
        main()
