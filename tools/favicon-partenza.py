#!/usr/bin/env python3
"""L'icona di partenza di plumeCMS (1.4.0): un quadrato grafite con gli angoli tondi e una piuma bianca, disegnata qui.
Scrive cms/sito/assets/favicon.svg (a mano, la stessa piuma in vettoriale) e cms/sito/assets/favicon.ico (16, 32 e 48
pixel, disegnata a 4 volte la misura e rimpicciolita). Il sito la usa finche lo Stile del sito non ha un'icona sua.
uso: python3 tools/favicon-partenza.py"""
import os
from PIL import Image, ImageDraw

RADICE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEST = os.path.join(RADICE, 'cms', 'sito', 'assets')
FONDO = (47, 50, 61, 255)       # grafite, neutro: sta bene con ogni colore del sito
PIUMA = (255, 255, 255, 255)

# La piuma su una griglia di 64: il vessillo (una foglia piena), lo stelo che lo attraversa e ne esce in basso,
# due tagli fra le barbe.
VESSILLO = [(51, 9), (39, 13), (28, 22), (21, 34), (21, 44), (31, 41), (42, 32), (49, 21)]
STELO = [(48, 13), (17, 53)]
TAGLI = [((44, 14), (37, 22)), ((25, 40), (31, 33))]


def disegna(lato):
    s = 4 * lato / 64
    im = Image.new('RGBA', (4 * lato, 4 * lato), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.rounded_rectangle([0, 0, 4 * lato - 1, 4 * lato - 1], radius=round(14 * s), fill=FONDO)
    d.polygon([(x * s, y * s) for x, y in VESSILLO], fill=PIUMA)
    d.line([(x * s, y * s) for x, y in STELO], fill=PIUMA, width=round(3.5 * s))
    d.line([(x * s, y * s) for x, y in [STELO[0], (24, 44)]], fill=FONDO, width=round(2.2 * s))
    for (x1, y1), (x2, y2) in TAGLI:
        d.line([(x1 * s, y1 * s), (x2 * s, y2 * s)], fill=FONDO, width=round(2 * s))
    return im.resize((lato, lato), Image.LANCZOS)


def main():
    os.makedirs(DEST, exist_ok=True)
    grande = disegna(48)
    grande.save(os.path.join(DEST, 'favicon.ico'), format='ICO', sizes=[(16, 16), (32, 32), (48, 48)],
                append_images=[disegna(16), disegna(32)])
    punti = ' '.join(f'{x},{y}' for x, y in VESSILLO)
    (a, b), (c, e) = STELO
    tagli = ''.join(f'<line x1="{p[0]}" y1="{p[1]}" x2="{q[0]}" y2="{q[1]}" stroke-width="2"/>' for p, q in TAGLI)
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
           '<rect width="64" height="64" rx="14" fill="#2f323d"/>'
           f'<polygon points="{punti}" fill="#fff"/>'
           f'<line x1="{a}" y1="{b}" x2="{c}" y2="{e}" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/>'
           f'<g stroke="#2f323d" stroke-linecap="round"><line x1="{a}" y1="{b}" x2="24" y2="44" stroke-width="2.2"/>{tagli}</g></svg>\n')
    with open(os.path.join(DEST, 'favicon.svg'), 'w') as f:
        f.write(svg)
    for n in ('favicon.ico', 'favicon.svg'):
        p = os.path.join(DEST, n)
        os.chmod(p, 0o644)
        print(n, os.path.getsize(p), 'byte')


if __name__ == '__main__':
    main()
