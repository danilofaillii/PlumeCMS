# La consegna a Danilo in un solo zip (1.4.2): l'aggiornamento e lo zip completo del CMS, le parti del sito di plumeCMS,
# i tre manuali in PDF e un LEGGIMI con l'ordine in cui caricarli. Si lancia per ultimo, dopo cms-pacchetto.py,
# manuali-pdf.js e sito-plumecms/costruisci.py. Scrive releases/consegna/plumecms-<versione>-consegna.zip.
# Lo zip si apre sul computer: sul server si caricano i file che contiene, uno per uno, come dice il LEGGIMI.
# uso: python3 tools/consegna.py
import glob
import hashlib
import os
import sys
import zipfile

RADICE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REL = os.path.join(RADICE, 'releases')
V = open(os.path.join(RADICE, 'cms', 'VERSIONE'), encoding='utf-8').read().strip()


def main():
    agg = os.path.join(REL, 'cms', f'plumecms-{V}-aggiornamento.zip')
    completo = os.path.join(REL, 'cms', f'plumecms-{V}.zip')
    parti = sorted(glob.glob(os.path.join(REL, 'sito', f'sito-plumecms-{V}-parte-*.zip')), key=lambda f: int(f.rsplit('-parte-', 1)[1].split('-')[0]))
    manuali = [os.path.join(REL, 'manuali', n + '.pdf') for n in ('manuale-uso', 'manuale-tecnico', 'manuale-temi')]
    for f in [agg, completo] + manuali:
        if not os.path.exists(f):
            sys.exit(f'manca {f}: prima cms-pacchetto.py --aggiornamento, manuali-pdf.js e costruisci.py')
    if not parti:
        sys.exit(f'mancano le parti del sito {V}: prima python3 sito-plumecms/costruisci.py')
    n = len(parti)
    righe = [
        f'plumeCMS {V}: cosa c\'è qui dentro e in che ordine si carica',
        '',
        'Apri questo zip sul computer (doppio clic). Dentro ci sono altri zip: NON aprirli, si caricano così come sono.',
        '',
        '1. Il CMS, su ogni sito con plumeCMS (il sito ufficiale e duhtron.it/test).',
        '   Pannello > Impostazioni > Aggiornamenti > Carica un pacchetto:',
        f'   cms/plumecms-{V}-aggiornamento.zip, poi Applica ora. In alto deve comparire {V}.',
        '',
        f'2. Le pagine del sito di plumeCMS, SOLO sul sito ufficiale, dopo il passo 1, una alla volta in quest\'ordine',
        '   (Aggiornamenti > Carica un pacchetto > Applica ora per ognuna):',
    ] + [f'   sito/{os.path.basename(p)}' for p in parti] + [
        '   Le parti portano anche gli zip del CMS e i manuali nella pagina Download: non serve caricarli a mano.',
        '',
        '3. Da leggere o conservare: i manuali in PDF (cartella manuali).',
        '   cms/plumecms-' + V + '.zip è lo zip completo, per installare plumeCMS su un sito nuovo.',
        '',
        'Mai su danilofailli.it: né l\'aggiornamento del CMS né le parti del sito.',
    ]
    os.makedirs(os.path.join(REL, 'consegna'), exist_ok=True)
    out = os.path.join(REL, 'consegna', f'plumecms-{V}-consegna.zip')
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_STORED) as z:  # dentro ci sono zip e PDF già compressi
        # Le voci delle cartelle, come negli zip fatti a mano: qualche programma per aprire gli zip le vuole (1.5.0).
        for d in ('cms/', 'sito/', 'manuali/'):
            info = zipfile.ZipInfo(d, date_time=(2026, 1, 1, 0, 0, 0))
            info.external_attr = (0o40755 << 16) | 0x10
            z.writestr(info, b'')
        def metti(da, a):
            info = zipfile.ZipInfo.from_file(da, a)
            info.external_attr = 0o644 << 16
            with open(da, 'rb') as f:
                z.writestr(info, f.read())
        metti(agg, f'cms/{os.path.basename(agg)}')
        metti(completo, f'cms/{os.path.basename(completo)}')
        for p in parti:
            metti(p, f'sito/{os.path.basename(p)}')
        for m in manuali:
            metti(m, f'manuali/{os.path.basename(m)}')
        info = zipfile.ZipInfo('LEGGIMI.txt', date_time=(2026, 1, 1, 0, 0, 0))
        info.external_attr = 0o644 << 16
        z.writestr(info, '\r\n'.join(righe) + '\r\n')
    with zipfile.ZipFile(out) as z:
        rotto = z.testzip()
    if rotto:
        sys.exit(f'{out}: il file {rotto} dentro lo zip non torna')
    impronta = hashlib.sha256(open(out, 'rb').read()).hexdigest()
    print(f'{out}: {os.path.getsize(out)} byte ({round(os.path.getsize(out) / 1024)} KB), {2 + n + len(manuali) + 1} file')
    print(f'SHA-256 {impronta}')


if __name__ == '__main__':
    main()
