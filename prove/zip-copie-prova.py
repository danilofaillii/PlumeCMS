#!/usr/bin/env python3
"""
Le copie del sito che il ripristino deve respingere o ripulire (1.3.0, prova-copia.js).
uso: python3 -I zip-copie-prova.py <cartella di uscita> <prefisso delle tabelle del sito di prova>

  non-copia.zip        uno zip qualunque, senza manifest: va respinto
  slip.zip             una copia con ../fuori.txt: va respinta (zip slip)
  slip-assoluto.zip    una copia con /tmp/assoluto.txt: va respinta
  nascosto.zip         una copia con uploads/.htaccess: va respinta
  estranea.zip         una copia di un "altro sito" (firma sbagliata) con temi/prova-x/funzioni.php, uploads/shell.php,
                       uploads/foto.php.jpg e SQL non ammesso (DROP DATABASE, GRANT, una tabella senza prefisso):
                       si ripristina, ma il PHP e l'SQL estraneo si saltano; entra uploads/copia-estranea.txt
  grande.zip           3 MB di rumore: oltre il limite di caricamento del server di prova (2 MB)
"""
import gzip, io, json, os, sys, zipfile

OUT, PREF = sys.argv[1], sys.argv[2]
os.makedirs(OUT, exist_ok=True)


def voce(z, nome, dati):
    i = zipfile.ZipInfo(nome, (2026, 10, 27, 12, 0, 0))
    i.external_attr = 0o644 << 16
    i.compress_type = zipfile.ZIP_DEFLATED
    z.writestr(i, dati)


def manifest(firma='0' * 64):
    return json.dumps({'formato': 'plumecms-copia', 'versione': 1, 'cms_versione': '1.3.0', 'creato': '2026-10-27 12:00:00', 'sito': 'https://altro-sito.invalid',
                       'prefisso': PREF, 'etichetta': 'prova', 'tabelle': 1, 'righe': 1, 'file': 1, 'peso_file': 1, 'firma': firma})


def db(righe):
    return gzip.compress(('\n'.join(righe) + '\n').encode())


DB_PULITO = db(["SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';", 'SET FOREIGN_KEY_CHECKS=0;',
                f'DROP TABLE IF EXISTS `{PREF}prova_copia`;', f'CREATE TABLE `{PREF}prova_copia` (`id` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
                f"INSERT INTO `{PREF}prova_copia` VALUES ('7');", 'SET FOREIGN_KEY_CHECKS=1;'])

with zipfile.ZipFile(os.path.join(OUT, 'non-copia.zip'), 'w') as z:
    voce(z, 'leggimi.txt', 'non sono una copia')

for nome, cattivo in [('slip.zip', '../fuori.txt'), ('slip-assoluto.zip', '/tmp/assoluto.txt'), ('nascosto.zip', 'uploads/.htaccess')]:
    with zipfile.ZipFile(os.path.join(OUT, nome), 'w') as z:
        voce(z, 'manifest.json', manifest()); voce(z, 'db.sql.gz', DB_PULITO); voce(z, cattivo, 'non devo uscire')

with zipfile.ZipFile(os.path.join(OUT, 'estranea.zip'), 'w') as z:
    voce(z, 'manifest.json', manifest('ab' * 32))
    voce(z, 'db.sql.gz', db(["SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';",
                             f'DROP TABLE IF EXISTS `{PREF}prova_copia`;', f'CREATE TABLE `{PREF}prova_copia` (`id` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
                             f"INSERT INTO `{PREF}prova_copia` VALUES ('9');",
                             'DROP DATABASE `mysql`;', "GRANT ALL ON *.* TO 'x'@'%';", 'CREATE TABLE `senza_prefisso` (`id` int);',
                             f'DROP TABLE IF EXISTS `{PREF}x`; DROP TABLE `{PREF}users`;']))
    voce(z, 'temi/prova-x/funzioni.php', '<?php echo "eseguito";')
    voce(z, 'temi/prova-x/tema.json', '{"nome":"Prova X"}')
    voce(z, 'uploads/shell.php', '<?php echo "eseguito";')
    voce(z, 'uploads/foto.php.jpg', '<?php echo "eseguito";')
    voce(z, 'uploads/copia-estranea.txt', 'entro')

with zipfile.ZipFile(os.path.join(OUT, 'grande.zip'), 'w', zipfile.ZIP_STORED) as z:
    voce(z, 'manifest.json', manifest())
    i = zipfile.ZipInfo('uploads/rumore.bin'); i.compress_type = zipfile.ZIP_STORED
    z.writestr(i, os.urandom(3 * 1024 * 1024))
print('zip scritti in', OUT)
