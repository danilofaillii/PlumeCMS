# La consegna a Danilo (1.4.2; dalla 2.1.0 con un pacchetto unico). Regola di Danilo del 31 ottobre 2026: "Dai prossimi
# aggiornamenti non serve più che spezzetti i file. Me ne dai uno solo che contiene tutte le modifiche."
#
# Si lancia per ultimo, dopo:
#   python3 tools/cms-pacchetto.py --aggiornamento     releases/cms/plumecms-<V>-aggiornamento.zip e plumecms-<V>.zip
#   node tools/manuali-pdf.js                          releases/manuali/manuale-uso.pdf e manuale-temi.pdf
#   python3 sito-plumecms/costruisci.py                releases/sito/sito-plumecms-<V>.zip (un pacchetto solo)
#
# Scrive:
#   releases/consegna/plumecms-<V>-pacchetto-unico.zip   UN pacchetto per Impostazioni > Aggiornamenti del sito ufficiale:
#       il codice del CMS (le voci di plumecms-<V>-aggiornamento.zip, sotto cms/) e il sito di plumeCMS (pagine, stile, menu,
#       nome del sito, immagini, e in Download gli zip del CMS e i manuali; le voci di sito-plumecms-<V>.zip, sotto sito/).
#   releases/consegna/plumecms-<V>-consegna.zip           quello che si dà a Danilo: il pacchetto unico, lo zip completo del CMS
#       (per un sito nuovo), i due manuali in PDF e un LEGGIMI.
#
# La trappola del motore nuovo (CLAUDE.md): un pacchetto lo applica il motore GIÀ installato sul sito. Se questa versione cambia
# il motore dei pacchetti (includes/aggiornamenti.php) rispetto alla versione di prima, codice e pagine non stanno insieme:
# allora escono due pacchetti, -1-cms.zip e -2-sito.zip, e il LEGGIMI dice l'ordine. Il manifest unito usa solo le voci che
# il motore conosce dalla 1.2.3 (file, elimina, impostazioni, editor, pagine, azioni, redirect): lo si controlla qui.
#
# uso: python3 tools/consegna.py [--senza-pagine] [--due] [--prima <versione>]
#   --senza-pagine  il pacchetto unico porta il CMS e i file in Download (zip e manuali) ma non tocca pagine, stile e menu
#   --due           due pacchetti anche se il motore non è cambiato
#   --uno           un pacchetto anche se il motore è cambiato: solo quando il cambio non porta voci nuove del manifest (lo
#                   controlla la verifica delle voci qui sotto, che resta). Esempio: 2.1.0, il motore scrive DEFAULT per le
#                   colonne generate nei dump; il motore installato applica il pacchetto come sempre.
#   --prima X.Y.Z   la versione con cui confrontare il motore (predefinita: la più alta in releases/cms sotto <V>)
# Con PACCHETTO_CHIAVE nell'ambiente il pacchetto unico esce firmato (tools/pacchetto.py --firma).
import glob
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile

RADICE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REL = os.path.join(RADICE, 'releases')
V = open(os.path.join(RADICE, 'cms', 'VERSIONE'), encoding='utf-8').read().strip()
MOTORE = 'includes/aggiornamenti.php'
# Le voci del manifest che il motore installato conosce (agg_leggi_manifest, dalla 1.2.3) e le azioni che accetta.
VOCI_NOTE = {'nome', 'descrizione', 'creato', 'applica_dal', 'sql', 'file', 'redirect', 'elimina', 'articoli', 'test', 'azioni',
             'pagine', 'editor', 'editor_ambiti', 'impostazioni', 'note', 'firma'}
AZIONI_NOTE = {'attiva-sito-nuovo', 'sito-pagine-e-stile'}


def errore(msg):
    sys.exit('ERRORE: ' + msg)


def versione(t):
    return tuple(int(x) for x in t.split('.'))


def motore_cambiato(prima):
    """Il motore dei pacchetti di questa versione è diverso da quello della versione <prima> (lo zip completo in releases/cms)?"""
    vecchio = os.path.join(REL, 'cms', f'plumecms-{prima}.zip')
    with zipfile.ZipFile(vecchio) as z:
        a = z.read(MOTORE)
    with open(os.path.join(RADICE, 'cms', MOTORE), 'rb') as f:
        b = f.read()
    return a != b


def versione_prima():
    if '--prima' in sys.argv:
        return sys.argv[sys.argv.index('--prima') + 1]
    tutte = [m.group(1) for f in glob.glob(os.path.join(REL, 'cms', 'plumecms-*.zip'))
             for m in [re.search(r'plumecms-(\d+\.\d+\.\d+)\.zip$', f)] if m]
    sotto = sorted((v for v in tutte if versione(v) < versione(V)), key=versione)
    return sotto[-1] if sotto else None


def estrai(zip_path, dove):
    with zipfile.ZipFile(zip_path) as z:
        for n in z.namelist():
            if n.endswith('/') or n.startswith('/') or '..' in n.split('/'):
                continue
            dest = os.path.join(dove, n)
            os.makedirs(os.path.dirname(dest), exist_ok=True)
            with open(dest, 'wb') as f:
                f.write(z.read(n))
        return json.loads(z.read('manifest.json'))


def prefissa(m, pre):
    """Le voci di un manifest con i file spostati sotto <pre>/ (dentro il pacchetto unito)."""
    m = dict(m)
    m['file'] = [{'da': f'{pre}/{f["da"]}', 'a': f['a']} for f in m.get('file', [])]
    for k in ('pagine', 'sql', 'articoli', 'test'):
        if m.get(k):
            m[k] = [f'{pre}/{x}' for x in m[k]]
    if m.get('editor'):
        m['editor'] = f'{pre}/{m["editor"]}'
    return m


def unisci(cms, sito, senza_pagine):
    """Un manifest solo: prima il codice del CMS, poi le pagine del sito. Nello stesso pacchetto il motore scrive tutti i file,
    toglie quelli da togliere, poi fa nome del sito, stile, header e footer, menu, pagine e redirect."""
    m = {
        'nome': f'plumeCMS {V} e il sito di plumeCMS' if not senza_pagine else f'plumeCMS {V} e i file del Download',
        'descrizione': (f'Tutto in un pacchetto: il CMS passa alla versione {V} e il sito di plumeCMS si aggiorna (pagine, stile, '
                        'header, footer, menu, immagini; in Download gli zip del CMS e i manuali).') if not senza_pagine else
                       (f'Il CMS passa alla versione {V} e in Download arrivano gli zip del CMS e i manuali. Pagine, stile e menu restano come sono.'),
        'creato': cms.get('creato') or sito.get('creato'),
        'file': cms['file'] + sito['file'],
        'elimina': cms.get('elimina', []) + sito.get('elimina', []),
        'redirect': sito.get('redirect', []),
        'note': f'Solo per il sito ufficiale di plumeCMS. Prima di cambiare qualcosa il pannello fa lo snapshot: se un passo non va, '
                f'rimette tutto com\'era. Dentro: {len(cms["file"])} file del CMS, {len(sito["file"])} file del sito'
                + ('' if senza_pagine else f', {len(sito.get("pagine", []))} pagine') + '.',
    }
    if not senza_pagine:
        for k in ('impostazioni', 'editor', 'editor_ambiti', 'pagine', 'azioni'):
            if sito.get(k):
                m[k] = sito[k]
    # due voci con la stessa destinazione: vince l'ultima, ma è quasi sempre un errore di costruzione
    viste = {}
    for f in m['file']:
        if f['a'] in viste:
            errore(f'due file per la stessa destinazione {f["a"]}: {viste[f["a"]]} e {f["da"]}')
        viste[f['a']] = f['da']
    ignote = set(m) - VOCI_NOTE
    if ignote:
        errore('voci che il motore installato non conosce: ' + ', '.join(sorted(ignote)) + ' (servono due pacchetti, --due)')
    if set(m.get('azioni', [])) - AZIONI_NOTE:
        errore('azioni che il motore installato non conosce: ' + ', '.join(sorted(set(m['azioni']) - AZIONI_NOTE)))
    return m


def pacchetto(cartella, m, dest):
    with open(os.path.join(cartella, 'manifest.json'), 'w', encoding='utf-8') as f:
        json.dump(m, f, ensure_ascii=False, indent=1)
    arg = [sys.executable, os.path.join(RADICE, 'tools', 'pacchetto.py'), cartella, dest]
    if os.environ.get('PACCHETTO_CHIAVE'):
        arg.append('--firma')
    subprocess.run(arg, check=True)


def main():
    agg = os.path.join(REL, 'cms', f'plumecms-{V}-aggiornamento.zip')
    completo = os.path.join(REL, 'cms', f'plumecms-{V}.zip')
    sito = os.path.join(REL, 'sito', f'sito-plumecms-{V}.zip')
    manuali = [os.path.join(REL, 'manuali', n + '.pdf') for n in ('manuale-uso', 'manuale-temi')]
    for f in [agg, completo] + manuali:
        if not os.path.exists(f):
            errore(f'manca {f}: prima cms-pacchetto.py --aggiornamento e manuali-pdf.js')
    if not os.path.exists(sito):
        parti = glob.glob(os.path.join(REL, 'sito', f'sito-plumecms-{V}-parte-*.zip'))
        errore(f'manca {sito}' + (': ci sono solo le parti piccole, rifallo senza --max-kb' if parti else ': prima python3 sito-plumecms/costruisci.py'))
    senza_pagine = '--senza-pagine' in sys.argv
    prima = versione_prima()
    cambiato = False
    if prima is None:
        print(f'avviso: nessuna versione prima della {V} in releases/cms: il motore dei pacchetti non si può confrontare, escono due pacchetti')
        due = True
    else:
        cambiato = motore_cambiato(prima)
        due = (cambiato and '--uno' not in sys.argv) or '--due' in sys.argv
        if cambiato and not due:
            print(f'avviso: {MOTORE} è cambiato dalla {prima}, ma con --uno esce un pacchetto solo (voci del manifest controllate)')
        elif cambiato:
            print(f'avviso: {MOTORE} è cambiato dalla {prima}: il motore nuovo arriva con il primo pacchetto, le pagine con il secondo')
    uscita = os.path.join(REL, 'consegna')
    os.makedirs(uscita, exist_ok=True)
    for vecchio in glob.glob(os.path.join(uscita, f'plumecms-{V}-*.zip')):
        os.remove(vecchio)
    lavoro = tempfile.mkdtemp(prefix='consegna-')
    try:
        m_cms = estrai(agg, os.path.join(lavoro, 'cms'))
        m_sito = estrai(sito, os.path.join(lavoro, 'sito'))
        if due:
            # Primo pacchetto: il codice (è l'aggiornamento così com'è). Secondo: il sito, applicato dal motore nuovo.
            p1 = os.path.join(uscita, f'plumecms-{V}-1-cms.zip')
            p2 = os.path.join(uscita, f'plumecms-{V}-2-sito.zip')
            shutil.copy(agg, p1)
            s = os.path.join(lavoro, 'sito')
            m2 = dict(m_sito)
            if senza_pagine:
                for k in ('impostazioni', 'editor', 'editor_ambiti', 'pagine', 'azioni'):
                    m2.pop(k, None)
            pacchetto(s, m2, p2)
            pacchetti = [p1, p2]
        else:
            unito = os.path.join(lavoro, 'unito')
            os.makedirs(unito)
            for pre in ('cms', 'sito'):
                os.rename(os.path.join(lavoro, pre), os.path.join(unito, pre))
                os.remove(os.path.join(unito, pre, 'manifest.json'))
            m = unisci(prefissa(m_cms, 'cms'), prefissa(m_sito, 'sito'), senza_pagine)
            p = os.path.join(uscita, f'plumecms-{V}-pacchetto-unico.zip')
            pacchetto(unito, m, p)
            pacchetti = [p]
    finally:
        shutil.rmtree(lavoro, ignore_errors=True)

    righe = [f'plumeCMS {V}: cosa c\'è qui dentro e cosa si fa', '',
             'Apri questo zip sul computer (doppio clic). Dentro ci sono altri zip: NON aprirli, si caricano così come sono.', '']
    if len(pacchetti) == 1:
        righe += [
            '1. Sul sito ufficiale di plumeCMS (plumecms.danilofailli.it), un pacchetto solo:',
            '   Pannello > Impostazioni > Aggiornamenti > Carica un pacchetto:',
            f'   {os.path.basename(pacchetti[0])}, poi Applica ora.',
            f'   Porta tutto insieme: il CMS {V}, ' + ('le pagine del sito, ' if not senza_pagine else '') + 'gli zip e i manuali nella pagina Download.',
            f'   Alla fine in Informazioni deve comparire la versione {V}.',
        ]
    else:
        righe += [
            '1. Sul sito ufficiale di plumeCMS (plumecms.danilofailli.it), due pacchetti, in quest\'ordine'
            + (' (questa volta cambia il\n   motore degli aggiornamenti: le pagine le deve applicare quello nuovo).' if cambiato else '.'),
            '   Pannello > Impostazioni > Aggiornamenti > Carica un pacchetto > Applica ora, per ognuno:',
            f'   prima {os.path.basename(pacchetti[0])}, poi {os.path.basename(pacchetti[1])}.',
            f'   Dopo il primo in Informazioni deve comparire la versione {V}.',
        ]
    righe += [
        '',
        ('   Porta i file della pagina Download del sito ufficiale: va solo lì, su nessun altro sito.' if senza_pagine else
         '   ' + ('Il pacchetto cambia' if len(pacchetti) == 1 else 'I pacchetti cambiano') + ' le pagine del sito ufficiale (home, menu, stile): solo lì, su nessun altro sito.'),
        '   Per un altro sito con plumeCMS si usa l\'aggiornamento semplice: si scarica dalla pagina Download del sito',
        f'   ufficiale (Scarica l\'aggiornamento, plumecms-{V}-aggiornamento.zip) e si carica nello stesso modo.',
        '',
        f'2. cms/plumecms-{V}.zip è lo zip completo, per installare plumeCMS su un sito nuovo (lo trovi anche in Download).',
        '',
        '3. Da leggere o conservare: i manuali in PDF (cartella manuali).',
    ]
    voci = [(p, os.path.basename(p)) for p in pacchetti] + [(completo, f'cms/{os.path.basename(completo)}')]
    voci += [(x, f'manuali/{os.path.basename(x)}') for x in manuali]
    scrivi(os.path.join(uscita, f'plumecms-{V}-consegna.zip'), voci, righe)


def scrivi(out, voci, righe):
    """Uno zip di consegna: le voci (sorgente, nome dentro lo zip), le cartelle, il LEGGIMI; poi si ricontrolla."""
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_STORED) as z:  # dentro ci sono zip e PDF già compressi
        # Le voci delle cartelle, come negli zip fatti a mano: qualche programma per aprire gli zip le vuole (1.5.0).
        for d in sorted({a.split('/')[0] + '/' for _, a in voci if '/' in a}):
            info = zipfile.ZipInfo(d, date_time=(2026, 1, 1, 0, 0, 0))
            info.external_attr = (0o40755 << 16) | 0x10
            z.writestr(info, b'')
        for da, a in voci:
            info = zipfile.ZipInfo.from_file(da, a)
            info.external_attr = 0o644 << 16
            with open(da, 'rb') as f:
                z.writestr(info, f.read())
        info = zipfile.ZipInfo('LEGGIMI.txt', date_time=(2026, 1, 1, 0, 0, 0))
        info.external_attr = 0o644 << 16
        z.writestr(info, '\r\n'.join(righe) + '\r\n')
    with zipfile.ZipFile(out) as z:
        rotto = z.testzip()
    if rotto:
        sys.exit(f'{out}: il file {rotto} dentro lo zip non torna')
    impronta = hashlib.sha256(open(out, 'rb').read()).hexdigest()
    print(f'{out}: {os.path.getsize(out)} byte ({round(os.path.getsize(out) / 1024)} KB), {len(voci) + 1} file')
    print(f'SHA-256 {impronta}')


if __name__ == '__main__':
    main()
