# Per ogni coppia di schermate: scarto massimo per canale e quanti pixel superano 2, 8 e 24 livelli. uso: python3 -I quanto.py <a> <b>
import sys, os
from PIL import Image, ImageChops
a, b = sys.argv[1:3]
for f in sorted(os.listdir(a)):
    ia, ib = Image.open(os.path.join(a, f)).convert('RGB'), Image.open(os.path.join(b, f)).convert('RGB')
    if ia.size != ib.size: print(f, 'misure diverse', ia.size, ib.size); continue
    d = ImageChops.difference(ia, ib).convert('L') if False else ImageChops.difference(ia, ib)
    m = [max(px) for px in d.get_flattened_data()]
    if not any(m): continue
    print(f'{f}: max {max(m)}, >2: {sum(x>2 for x in m)}, >8: {sum(x>8 for x in m)}, >24: {sum(x>24 for x in m)}')
