#!/usr/bin/env python3
"""
Un lettore di codici QR scritto a parte (2.16.0), per controllare il QR fatto in casa (includes/sicurezza-qr.php) senza
librerie: nella sessione non c'è un decodificatore installato. Legge la matrice (righe di 0 e 1 in JSON, da
prove/form-brevo-cli.php qr) e rifà la strada al contrario, come la norma ISO/IEC 18004:
  1. le informazioni di formato (15 bit, BCH 15,5) nelle due copie, e devono essere uguali e valide;
  2. livello di correzione e maschera; i moduli dei dati letti a zig-zag, saltando le parti fisse;
  3. i blocchi di Reed-Solomon separati e controllati: le sindromi di ogni blocco devono essere zero;
  4. i dati: modalità byte, lunghezza, i byte; poi il riempimento 0xEC 0x11.
Stampa il testo letto; esce con 1 se qualcosa non torna.
uso: python3 -I prove/qr-decodifica.py <file json della matrice>
"""
import json
import sys

# I blocchi del livello M, versioni 1-10: (codeword di correzione per blocco, [(blocchi, codeword di dati)]). Dalla norma.
BLOCCHI_M = {
    1: (10, [(1, 16)]), 2: (16, [(1, 28)]), 3: (26, [(1, 44)]), 4: (18, [(2, 32)]), 5: (24, [(2, 43)]),
    6: (16, [(4, 27)]), 7: (18, [(4, 31)]), 8: (22, [(2, 38), (2, 39)]), 9: (22, [(3, 36), (2, 37)]), 10: (26, [(4, 43), (1, 44)]),
}
ALLINEAMENTO = {1: [], 2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30], 6: [6, 34], 7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46], 10: [6, 28, 50]}
LIVELLI = {0b01: 'L', 0b00: 'M', 0b11: 'Q', 0b10: 'H'}

# GF(256) con il polinomio 0x11D
ESP = [0] * 512
LOG = [0] * 256
x = 1
for i in range(255):
    ESP[i] = x
    LOG[x] = i
    x <<= 1
    if x & 0x100:
        x ^= 0x11D
for i in range(255, 512):
    ESP[i] = ESP[i - 255]


def mul(a, b):
    return 0 if a == 0 or b == 0 else ESP[LOG[a] + LOG[b]]


def bch_formato(dati5):
    v = dati5 << 10
    for i in range(14, 9, -1):
        if v & (1 << i):
            v ^= 0b10100110111 << (i - 10)
    return ((dati5 << 10) | v) ^ 0b101010000010010


def fail(msg):
    print('ERRORE: ' + msg)
    sys.exit(1)


m = [[int(c) for c in r] for r in json.load(open(sys.argv[1]))]
n = len(m)
if any(len(r) != n for r in m) or (n - 17) % 4:
    fail('matrice non quadrata o di misura sbagliata: %d' % n)
ver = (n - 17) // 4
if ver not in BLOCCHI_M:
    fail('versione %d fuori dalle versioni 1-10' % ver)

# 1. formato: prima copia intorno al cercatore in alto a sinistra, seconda divisa fra gli altri due
pos1 = [(8, 0), (8, 1), (8, 2), (8, 3), (8, 4), (8, 5), (8, 7), (8, 8), (7, 8), (5, 8), (4, 8), (3, 8), (2, 8), (1, 8), (0, 8)]
pos2 = [(n - 1, 8), (n - 2, 8), (n - 3, 8), (n - 4, 8), (n - 5, 8), (n - 6, 8), (n - 7, 8), (8, n - 8), (8, n - 7), (8, n - 6), (8, n - 5), (8, n - 4), (8, n - 3), (8, n - 2), (8, n - 1)]
f1 = sum(m[r][c] << (14 - i) for i, (r, c) in enumerate(pos1))
f2 = sum(m[r][c] << (14 - i) for i, (r, c) in enumerate(pos2))
if f1 != f2:
    fail('le due copie del formato sono diverse')
validi = {bch_formato(d): d for d in range(32)}
if f1 not in validi:
    fail('informazioni di formato non valide: %s' % bin(f1))
d5 = validi[f1]
livello = LIVELLI[d5 >> 3]
maschera = d5 & 7
if livello != 'M':
    fail('livello %s (atteso M)' % livello)
if m[n - 8][8] != 1:
    fail('manca il modulo scuro fisso')

# 2. le parti fisse
fisso = [[False] * n for _ in range(n)]
for (r0, c0) in [(0, 0), (0, n - 7), (n - 7, 0)]:
    for r in range(r0 - 1, r0 + 8):
        for c in range(c0 - 1, c0 + 8):
            if 0 <= r < n and 0 <= c < n:
                fisso[r][c] = True
for i in range(n):
    fisso[6][i] = fisso[i][6] = True
centri = ALLINEAMENTO[ver]
for r in centri:
    for c in centri:
        if (r <= 8 and c <= 8) or (r <= 8 and c >= n - 9) or (r >= n - 9 and c <= 8):
            continue
        for dr in range(-2, 3):
            for dc in range(-2, 3):
                fisso[r + dr][c + dc] = True
for (r, c) in pos1 + pos2:
    fisso[r][c] = True
fisso[n - 8][8] = True
if ver >= 7:
    for i in range(6):
        for j in range(3):
            fisso[i][n - 11 + j] = fisso[n - 11 + j][i] = True

MASCHERE = [lambda r, c: (r + c) % 2 == 0, lambda r, c: r % 2 == 0, lambda r, c: c % 3 == 0, lambda r, c: (r + c) % 3 == 0,
            lambda r, c: (r // 2 + c // 3) % 2 == 0, lambda r, c: (r * c) % 2 + (r * c) % 3 == 0,
            lambda r, c: ((r * c) % 2 + (r * c) % 3) % 2 == 0, lambda r, c: ((r + c) % 2 + (r * c) % 3) % 2 == 0]
bit = []
c = n - 1
su = True
while c > 0:
    if c == 6:
        c -= 1
    righe = range(n - 1, -1, -1) if su else range(n)
    for r in righe:
        for cc in (c, c - 1):
            if not fisso[r][cc]:
                bit.append(m[r][cc] ^ (1 if MASCHERE[maschera](r, cc) else 0))
    su = not su
    c -= 2
cw = [int(''.join(map(str, bit[i:i + 8])), 2) for i in range(0, len(bit) - len(bit) % 8, 8)]

# 3. blocchi e Reed-Solomon
ecc, gruppi = BLOCCHI_M[ver]
lunghezze = [k for (b, k) in gruppi for _ in range(b)]
totale = sum(lunghezze) + ecc * len(lunghezze)
if len(cw) < totale:
    fail('codeword lette %d, ne servono %d' % (len(cw), totale))
blocchi = [[] for _ in lunghezze]
i = 0
for j in range(max(lunghezze)):
    for b, k in enumerate(lunghezze):
        if j < k:
            blocchi[b].append(cw[i])
            i += 1
for j in range(ecc):
    for b in range(len(lunghezze)):
        blocchi[b].append(cw[i])
        i += 1
for b, blk in enumerate(blocchi):
    for s in range(ecc):
        v = 0
        for byte in blk:
            v = mul(v, ESP[s]) ^ byte
        if v:
            fail('Reed-Solomon: il blocco %d ha la sindrome %d diversa da zero' % (b + 1, s))
dati = [byte for b, blk in enumerate(blocchi) for byte in blk[:lunghezze[b]]]

# 4. i dati
flusso = ''.join(format(x, '08b') for x in dati)
if flusso[:4] != '0100':
    fail('modalità %s (attesa byte, 0100)' % flusso[:4])
lb = 8 if ver <= 9 else 16
lung = int(flusso[4:4 + lb], 2)
p = 4 + lb
testo = bytes(int(flusso[p + 8 * k:p + 8 * k + 8], 2) for k in range(lung))
p += 8 * lung
if flusso[p:p + 4] not in ('0000', flusso[p:p + 4][:4]) or len(flusso) - p < 0:
    fail('terminatore mancante')
resto = flusso[p + 4:]
resto = resto[(8 - (p + 4) % 8) % 8:]
pad = [int(resto[k:k + 8], 2) for k in range(0, len(resto) - len(resto) % 8, 8)]
if any(x != (0xEC if k % 2 == 0 else 0x11) for k, x in enumerate(pad)):
    fail('riempimento sbagliato')
print('versione %d, livello %s, maschera %d' % (ver, livello, maschera))
print(testo.decode('utf-8'))
