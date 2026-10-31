# Il foglio delle undici aperture (1.2.6) per la pagina Funzionalità: le foto al computer di prove/prova-hero.js,
# in una griglia con il nome di ognuna. uso: python3 sito-plumecms/foglio-aperture.py <cartella di prova-hero> <cartella schermate>
# poi: python3 sito-plumecms/costruisci.py --schermate <cartella schermate> (fa aperture.webp).
import os
import sys
from PIL import Image, ImageDraw, ImageFont

NOMI = [('classica', 'Classica'), ('centrata', 'Centrata'), ('copertina', 'Copertina'), ('divisa', 'Divisa a metà'),
        ('rivista', 'Rivista'), ('scheda', 'Scheda'), ('minimale', 'Minimale'), ('mosaico', 'Mosaico'),
        ('inclinata', 'Inclinata'), ('banner', 'Banner'), ('diagonale', 'Diagonale')]
COLONNE, LARGO, ALTO, SPAZIO, TESTA = 4, 440, 282, 24, 44
FONDO, TESTO, NUMERO = (11, 13, 26), (236, 238, 248), (139, 120, 255)


def carattere(peso, misura):
    for f in (f'/usr/share/fonts/opentype/inter/Inter-{peso}.otf', '/usr/share/fonts/truetype/crosextra/Carlito-Bold.ttf'):
        if os.path.exists(f):
            return ImageFont.truetype(f, misura)
    return ImageFont.load_default(size=misura)


def main(sorgente, dest):
    righe = (len(NOMI) + COLONNE - 1) // COLONNE
    foglio = Image.new('RGB', (COLONNE * LARGO + (COLONNE + 1) * SPAZIO, righe * (ALTO + TESTA) + (righe + 1) * SPAZIO), FONDO)
    d = ImageDraw.Draw(foglio)
    f_num, f_nome = carattere('Bold', 22), carattere('SemiBold', 22)
    for k, (id_, nome) in enumerate(NOMI):
        im = Image.open(os.path.join(sorgente, f'{id_}-pc.png')).convert('RGB')
        alto = round(im.width * ALTO / LARGO)
        if im.height < alto:  # una disposizione bassa (Banner): si completa con il fondo della pagina, senza stirarla
            sotto = Image.new('RGB', (im.width, alto), im.getpixel((4, im.height - 1)))
            sotto.paste(im, (0, 0))
            im = sotto
        im = im.crop((0, 0, im.width, alto)).resize((LARGO, ALTO), Image.LANCZOS)
        x = SPAZIO + (k % COLONNE) * (LARGO + SPAZIO)
        y = SPAZIO + (k // COLONNE) * (ALTO + TESTA + SPAZIO)
        d.text((x, y + 8), f'{k + 1:02d}', fill=NUMERO, font=f_num)
        d.text((x + 38, y + 8), nome, fill=TESTO, font=f_nome)
        maschera = Image.new('L', im.size, 0)
        ImageDraw.Draw(maschera).rounded_rectangle([0, 0, LARGO - 1, ALTO - 1], radius=12, fill=255)
        foglio.paste(im, (x, y + TESTA), maschera)
    # L'ultima casella, vuota nella griglia da dodici, dice cosa fare.
    x, y = SPAZIO + (len(NOMI) % COLONNE) * (LARGO + SPAZIO), SPAZIO + (righe - 1) * (ALTO + TESTA + SPAZIO) + TESTA
    d.rounded_rectangle([x, y, x + LARGO - 1, y + ALTO - 1], radius=12, outline=(60, 64, 96), width=2)
    for i, riga in enumerate(['Una tendina,', 'undici aperture.', 'I testi restano.']):
        d.text((x + 36, y + 70 + i * 48), riga, fill=TESTO if i < 2 else NUMERO, font=carattere('Bold', 34))
    os.makedirs(dest, exist_ok=True)
    foglio.save(os.path.join(dest, 'aperture.png'))
    print('aperture.png', foglio.size)


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
