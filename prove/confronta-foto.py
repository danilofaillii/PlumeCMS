# Confronta due cartelle di schermate: pixel diversi per file, e un'immagine delle differenze. uso: python3 -I confronta.py <a> <b> <diff>
import sys, os
from PIL import Image, ImageChops
a, b, d = sys.argv[1:4]
os.makedirs(d, exist_ok=True)
tot = 0
for f in sorted(os.listdir(a)):
    ia, ib = Image.open(os.path.join(a, f)).convert('RGB'), Image.open(os.path.join(b, f)).convert('RGB')
    if ia.size != ib.size:
        print(f'{f}: misure diverse {ia.size} -> {ib.size}'); tot += 1; continue
    diff = ImageChops.difference(ia, ib)
    box = diff.getbbox()
    n = sum(1 for px in diff.get_flattened_data() if px != (0, 0, 0)) if box else 0
    if n:
        tot += 1; diff.point(lambda x: 255 if x else 0).save(os.path.join(d, f))
    print(f'{f}: {n} pixel diversi' + (f' nel riquadro {box}' if box else ''))
print('file con differenze:', tot)
