#!/usr/bin/env python3
"""
Costruisce un pacchetto di aggiornamento per il pannello Aggiornamenti.

Uso:
    python3 tools/pacchetto.py <cartella> [destinazione.zip]

La cartella deve contenere un manifest.json e i file che il manifest cita
(percorsi relativi alla cartella). Il programma controlla il manifest,
rifiuta destinazioni fuori dalle radici ammesse e scrive lo zip.

Formato del manifest:
{
  "nome": "Pillar 1, burnout",
  "descrizione": "una riga per Danilo",
  "creato": "2026-09-11",
  "applica_dal": "2026-10-06",            facoltativo: se manca, si applica al primo clic
  "sql": ["sql/01-pillar.sql"],           in ordine di esecuzione
  "file": [{"da": "file/x.png", "a": "uploads/contenuti/burnout/x.png"}],
  "redirect": [{"da": "/vecchio-slug/", "a": "/nuovo-slug/"}],
  "sincronizza": ["slug", {"slug": "slug2", "aggiorna_data": true}],
  "articoli": ["articoli/slug.json"],     un file per articolo, con data e ora di uscita
  "test": ["test/burnout-v1.json"],       definizioni dei test (php-site/test-definizioni/)
  "azioni": ["importa-vecchio-test-burnout"],  azioni con nome, solo quelle ammesse dal pannello
  "note": "testo mostrato nel pannello"
}

Formato di un articolo (articoli/slug.json), letto da sito_importa_articolo() in php-site/includes/sito.php:
{
  "slug": "cambiare-lavoro-o-restare",
  "titolo": "Cambiare lavoro o restare: come si decide",
  "pubblicazione": "2026-12-01T06:00",
  "stato": "published",                   "draft" per caricarlo senza farlo uscire
  "tipo": "pillar",
  "categoria": "crescita-e-cambiamento",
  "tag": ["cambiare lavoro"],
  "corpo_markdown": "...",                oppure "corpo_html"
  "estratto": "...", "seo_titolo": "...", "seo_descrizione": "...",
  "keyword": "...", "correlate": ["..."],
  "tldr": "40-60 parole", "faq": [{"domanda": "...", "risposta": "..."}],
  "immagine": "https://www.danilofailli.it/wp-content/uploads/contenuti/<tema>/<file>.png",
  "cta": "laser coaching 15 min"
}

Radici ammesse per "a": uploads/ (wp-content/uploads), new/ (il publisher), root/ (radice del sito).
File nascosti ammessi: root/.htaccess, new/.htaccess, uploads/.htaccess (gli ultimi due solo dal motore 1.1.3 in poi).

Firma (facoltativa): python3 tools/pacchetto.py <cartella> [nome.zip] --firma, con la chiave nella variabile
d'ambiente PACCHETTO_CHIAVE (uguale a CMS_FIRMA_PACCHETTI in config.local.php sul server). Il manifest riceve "firma".
Uno zip gia fatto si firma con: python3 tools/pacchetto.py --rifirma <pacchetto.zip>  (scrive <pacchetto>-firmato.zip).
Vale anche per lo zip di un tema (Editor > Temi): senza manifest.json, se ne aggiunge uno con la sola firma.
Un pacchetto che mette file eseguibili (.php) nella radice del sito (root/) il pannello lo accetta solo firmato.
"""
import json, os, re, sys, time, zipfile

RADICI = ('uploads/', 'new/', 'root/')
HTACCESS_AMMESSI = ('root/.htaccess', 'new/.htaccess', 'uploads/.htaccess')


def errore(msg):
    print('ERRORE:', msg)
    sys.exit(1)


def controlla_sintassi_php(percorso, dest):
    """Un file PHP con un errore di sintassi manda in 500 la sua pagina sul server.
    Si controlla con short_open_tag acceso, come nel PHP standard e su Hostcare: il 26 ottobre 2026
    un "<?xml" scritto come esempio in admin/seo.php passava qui (Ubuntu lo spegne) e rompeva la pagina SEO.
    Senza il comando php sulla macchina il controllo si salta con un avviso."""
    if not percorso.endswith('.php'):
        return
    import shutil, subprocess
    if not shutil.which('php'):
        print('avviso: comando php assente, sintassi di ' + dest + ' non controllata', file=sys.stderr)
        return
    r = subprocess.run(['php', '-n', '-d', 'short_open_tag=1', '-l', percorso], capture_output=True, text=True)
    if r.returncode != 0:
        errore(f'{dest}: errore di sintassi PHP (con short_open_tag acceso, come sul server): '
               + (r.stdout + r.stderr).strip().splitlines()[0])


def controlla_dest(a):
    if not a.startswith(RADICI):
        errore(f'destinazione fuori dalle radici ammesse: {a}')
    # Unici file nascosti ammessi: i .htaccess della radice, della cartella del CMS e delle immagini (motore 1.1.3 o dopo:
    # il motore di prima accetta solo root/.htaccess, quindi new/.htaccess e uploads/.htaccess vanno in un pacchetto a parte).
    if a not in HTACCESS_AMMESSI and (not re.match(r'^(uploads|new|root)/[A-Za-z0-9_][A-Za-z0-9_./ -]*$', a) or '..' in a.split('/')):
        errore(f'destinazione non valida: {a}')
    if a.startswith('new/aggiornamenti/') or a == 'new/includes/config.local.php':
        errore(f'destinazione protetta: {a}')


def firma_pacchetto(cartella, dentro, chiave):
    """Lo stesso calcolo di sicurezza_pacchetto_impronta() in PHP: per ogni file (tranne manifest.json), in ordine di nome,
    una riga "nome sha256"; la firma e l'HMAC-SHA256 del testo con la chiave."""
    import hashlib, hmac
    righe = []
    for d in sorted(set(dentro)):
        with open(os.path.join(cartella, d), 'rb') as f:
            righe.append(d + ' ' + hashlib.sha256(f.read()).hexdigest())
    testo = '\n'.join(righe)
    return hmac.new(chiave.encode('utf-8'), testo.encode('utf-8'), hashlib.sha256).hexdigest()


def rifirma(percorso_zip):
    """Firma uno zip gia costruito (per esempio un pacchetto di trasloco con root/index.php): ricalcola l'impronta
    dei file dentro, scrive "firma" nel manifest e salva <nome>-firmato.zip accanto all'originale."""
    import hashlib, hmac
    chiave = os.environ.get('PACCHETTO_CHIAVE', '')
    if not chiave:
        errore('--rifirma: manca la variabile d\'ambiente PACCHETTO_CHIAVE (il valore di CMS_FIRMA_PACCHETTI del server)')
    with zipfile.ZipFile(percorso_zip) as z:
        # Un tema (cartella con tema.json, pagina Editor > Temi) non ha manifest: se ne crea uno solo per la firma.
        m = json.loads(z.read('manifest.json')) if 'manifest.json' in z.namelist() else {'tipo': 'tema'}
        voci = {n: z.read(n) for n in z.namelist() if n != 'manifest.json' and not n.endswith('/')}
    righe = sorted(n + ' ' + hashlib.sha256(d).hexdigest() for n, d in voci.items())
    m['firma'] = hmac.new(chiave.encode('utf-8'), '\n'.join(righe).encode('utf-8'), hashlib.sha256).hexdigest()
    dest = re.sub(r'\.zip$', '', percorso_zip) + '-firmato.zip'
    with zipfile.ZipFile(dest, 'w', zipfile.ZIP_DEFLATED) as z:
        # 0644: con writestr() e un nome semplice i file uscirebbero 0600 (trappola del 27 ottobre 2026, vedi CLAUDE.md).
        def voce(n):
            i = zipfile.ZipInfo(n, date_time=time.localtime()[:6])
            i.external_attr = 0o644 << 16
            i.compress_type = zipfile.ZIP_DEFLATED
            return i
        z.writestr(voce('manifest.json'), json.dumps(m, ensure_ascii=False, indent=2))
        for n, d in voci.items():
            z.writestr(voce(n), d)
    print(f'Pacchetto firmato: {dest}')


def main():
    if len(sys.argv) >= 3 and sys.argv[1] == '--rifirma':
        rifirma(sys.argv[2])
        return
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)
    cartella = sys.argv[1].rstrip('/')
    mpath = os.path.join(cartella, 'manifest.json')
    if not os.path.exists(mpath):
        errore('manca manifest.json in ' + cartella)
    m = json.load(open(mpath, encoding='utf-8'))
    if not m.get('nome'):
        errore('il manifest non ha "nome"')
    if m.get('applica_dal') and not re.match(r'^\d{4}-\d{2}-\d{2}$', m['applica_dal']):
        errore('applica_dal deve essere AAAA-MM-GG')
    dentro = []
    for s in m.get('sql', []):
        p = os.path.join(cartella, s)
        if not os.path.exists(p):
            errore('file SQL mancante: ' + s)
        testo = open(p, encoding='utf-8').read()
        if any(l.startswith('--') for l in testo.splitlines()):
            errore(f'{s}: contiene righe che iniziano con -- (il motore le salta come Importa SQL)')
        if any(ord(c) > 0xFFFF for c in testo):
            # Non li rifiuta: con la modalita' non stretta li TAGLIA, e manda
            # un avviso che nessuno legge. Il 21 settembre nove articoli sono
            # stati trovati tagliati a meta' per una emoji dentro
            # un'infografica. Qui si ferma prima.
            errore(f'{s}: contiene caratteri a 4 byte (emoji). La colonna content e utf8 a 3 byte: '
                   f'MySQL taglia il testo a quel punto invece di rifiutarlo, e il guasto si vede settimane dopo')
        dentro.append(s)
    for f in m.get('file', []):
        if not isinstance(f, dict) or 'da' not in f or 'a' not in f:
            errore('ogni voce di "file" deve avere "da" e "a"')
        if not os.path.exists(os.path.join(cartella, f['da'])):
            errore('file mancante: ' + f['da'])
        controlla_dest(f['a'])
        controlla_sintassi_php(os.path.join(cartella, f['da']), f['a'])
        dentro.append(f['da'])
    for a in m.get('articoli', []):
        p = os.path.join(cartella, a)
        if not os.path.exists(p):
            errore('articolo mancante: ' + a)
        try:
            j = json.load(open(p, encoding='utf-8'))
        except ValueError as e:
            errore(f'{a}: JSON non valido ({e})')
        if not re.match(r'^[a-z0-9-]+$', j.get('slug', '')):
            errore(f'{a}: slug non valido')
        if not j.get('titolo'):
            errore(f'{a}: manca il titolo')
        if not re.match(r'^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$', j.get('pubblicazione', '')):
            errore(f'{a}: pubblicazione deve essere AAAA-MM-GGTHH:MM')
        if not (j.get('corpo_markdown') or j.get('corpo_html')):
            errore(f'{a}: manca il corpo')
        if '\u2014' in json.dumps(j, ensure_ascii=False):
            errore(f'{a}: contiene trattini lunghi (regola 1)')
        dentro.append(a)
    for c in m.get('contenuti', []):
        p = os.path.join(cartella, c)
        if not os.path.exists(p):
            errore('file dei contenuti mancante: ' + c)
        testo = open(p, encoding='utf-8').read()
        json.loads(testo)
        if any(ord(ch) > 0xFFFF for ch in testo):
            errore(f'{c}: contiene caratteri a 4 byte (emoji): vanno convertiti in entità HTML')
        dentro.append(c)
    for t in m.get('test', []):
        p = os.path.join(cartella, t)
        if not os.path.exists(p):
            errore('definizione di test mancante: ' + t)
        j = json.load(open(p, encoding='utf-8'))
        for k in ('slug', 'versione', 'titolo', 'scala', 'dimensioni', 'livelli', 'domande', 'consenso'):
            if not j.get(k):
                errore(f'{t}: manca "{k}"')
        dentro.append(t)
    # Pagine fatte con l'editor e stile, header, footer, menu (motore 1.2.3): chi le usa mette anche l'azione
    # sito-pagine-e-stile, cosi un motore piu vecchio rifiuta il pacchetto invece di saltarle in silenzio.
    for pg in m.get('pagine', []):
        p = os.path.join(cartella, pg)
        if not os.path.exists(p):
            errore('pagina mancante: ' + pg)
        testo = open(p, encoding='utf-8').read()
        try:
            j = json.loads(testo)
        except ValueError as e:
            errore(f'{pg}: JSON non valido ({e})')
        if not re.match(r'^[a-z0-9-]{1,100}$', j.get('slug', '')):
            errore(f'{pg}: slug non valido')
        if not j.get('titolo'):
            errore(f'{pg}: manca il titolo')
        if not isinstance(j.get('struttura'), dict) or not j['struttura'].get('elementi'):
            errore(f'{pg}: manca la struttura dell\'editor')
        if any(ord(ch) > 0xFFFF for ch in testo) or '\u2014' in json.dumps(j, ensure_ascii=False):
            errore(f'{pg}: caratteri a 4 byte o trattini lunghi')
        dentro.append(pg)
    if m.get('editor'):
        p = os.path.join(cartella, m['editor'])
        if not os.path.exists(p):
            errore('file dell\'editor mancante: ' + m['editor'])
        if json.load(open(p, encoding='utf-8')).get('formato') != 'editor-impostazioni':
            errore(m['editor'] + ': non e un\'esportazione di Editor > Impostazioni (formato editor-impostazioni)')
        dentro.append(m['editor'])
    for k, v in (m.get('impostazioni') or {}).items():
        if k not in ('sito_nome', 'sito_titolo', 'sito_sottotitolo', 'sito_descrizione', 'home_descrizione') or not isinstance(v, str):
            errore(f'impostazione non ammessa in un pacchetto: {k}')
    if (m.get('pagine') or m.get('editor') or m.get('impostazioni')) and 'sito-pagine-e-stile' not in m.get('azioni', []):
        errore('con "pagine" o "editor" serve anche l\'azione "sito-pagine-e-stile" (motore 1.2.3 o successivo)')
    for a in m.get('azioni', []):
        if a not in ('importa-vecchio-test-burnout', 'attiva-sito-nuovo', 'converti-articoli-editor', 'sito-pagine-e-stile'):
            errore(f'azione sconosciuta: {a}')
    for r in m.get('redirect', []):
        if not isinstance(r, dict) or 'da' not in r or 'a' not in r:
            errore('ogni redirect deve avere "da" e "a"')
    for e in m.get('elimina', []):
        controlla_dest(e)
    argomenti = [a for a in sys.argv[2:] if not a.startswith('--')]
    nome_zip = argomenti[0] if argomenti else re.sub(r'[^a-z0-9]+', '-', m['nome'].lower()).strip('-') + '.zip'
    # Firma HMAC-SHA256 (sicurezza): --firma legge la chiave dalla variabile d'ambiente PACCHETTO_CHIAVE (la stessa di
    # CMS_FIRMA_PACCHETTI nel config.local.php del server). Il pannello la verifica se c'e, la esige se la pagina Sicurezza lo chiede.
    firma = None
    if '--firma' in sys.argv:
        chiave = os.environ.get('PACCHETTO_CHIAVE', '')
        if not chiave:
            errore('--firma: manca la variabile d\'ambiente PACCHETTO_CHIAVE (il valore di CMS_FIRMA_PACCHETTI del server)')
        firma = firma_pacchetto(cartella, dentro, chiave)
        m['firma'] = firma
    with zipfile.ZipFile(nome_zip, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        if firma:
            z.writestr('manifest.json', json.dumps(m, ensure_ascii=False, indent=2))
        else:
            z.write(mpath, 'manifest.json')
        for d in dentro:
            z.write(os.path.join(cartella, d), d)
    dim = os.path.getsize(nome_zip)
    print(f'Pacchetto scritto: {nome_zip} ({dim // 1024} KB)' + (' firmato' if firma else ''))
    print(f'  {len(m.get("pagine", []))} pagine, {len(m.get("articoli", []))} articoli, {len(m.get("file", []))} file, {len(m.get("sql", []))} SQL, {len(m.get("redirect", []))} redirect, {len(m.get("elimina", []))} da togliere' + (f', applica dal {m["applica_dal"]}' if m.get('applica_dal') else ''))


if __name__ == '__main__':
    main()
