<?php
/**
 * Aiuti da riga di comando per prova-importa.js e per gli attacchi del gruppo 30 (2.9.0), su un CMS installato. Mai nel prodotto.
 * uso: php prove/importa-cli.php <cartella CMS> <comando> [argomenti]
 *   xlsx <file> excel|libreoffice     un XLSX come lo scrivono Excel (testi condivisi, data come numero seriale con il
 *                                      formato 14) o LibreOffice (testi in linea, formato di data scritto, sì/no, rel a un
 *                                      foglio con un altro nome, celle saltate)
 *   xlsx <file> xxe|dtd|bomba|percorsi file cattivi: entità esterna, DTD, zip bomba (100 MB di zeri), nomi con ../ e /
 *   righe <file> <n> <sep>            un CSV con n contatti (@grande.prova-importa.it)
 *   lavori [secondi]                  un giro dei lavori in sottofondo (come il cron); stampa le righe
 *   imp <id>                          un'importazione (JSON)
 *   sql "<query>"                     righe in JSON ({p} diventa il prefisso)
 *   valori <email>                    i valori degli attributi di un contatto
 */
[$dir, $cmd] = array_pad(array_slice($argv, 1, 2), 2, '');
$arg = array_slice($argv, 3);
if ($dir === '' || $cmd === '') { fwrite(STDERR, "uso: importa-cli.php <cartella CMS> <comando>\n"); exit(2); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
require $dir . '/includes/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/newsletter.php';
require_once $dir . '/includes/newsletter-importa.php';
newsletter_importa_schema();
$out = null;

/** Uno zip con le parti date: [nome => testo]; con $livello 0 (store) o 9. */
function zip_scrivi(string $file, array $parti): void {
    @unlink($file);
    $z = new ZipArchive();
    $z->open($file, ZipArchive::CREATE);
    foreach ($parti as $n => $t) $z->addFromString($n, $t);
    $z->close();
}
function xlsx_base(string $foglio, string $condivisi = '', string $stili = '', string $target = 'worksheets/sheet1.xml'): array {
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $p = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr/><sheets><sheet name="Contatti" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="' . $target . '"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/' . $target => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '"><sheetData>' . $foglio . '</sheetData></worksheet>',
    ];
    if ($condivisi !== '') $p['xl/sharedStrings.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="' . $ns . '">' . $condivisi . '</sst>';
    if ($stili !== '') $p['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="' . $ns . '">' . $stili . '</styleSheet>';
    return $p;
}

switch ($cmd) {
    case 'xlsx':
        [$file, $tipo] = $arg;
        $x = fn($s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if ($tipo === 'excel') {
            // Excel: tutti i testi nei testi condivisi; la data come numero seriale (46096 = 15/03/2026) con lo stile 1 (numFmtId 14).
            $testi = ['EMAIL', 'NOME', 'DATA_ARRIVO', 'PUNTI', 'excel1@xlsx.prova-importa.it', "Màrio \u{1F600}", 'excel2@xlsx.prova-importa.it', 'Giulia', 'Città'];
            $ss = implode('', array_map(fn($t) => '<si><t>' . $x($t) . '</t></si>', $testi));
            $ss .= '<si><r><t>Ro</t></r><r><rPr><b/></rPr><t>sa</t></r></si>'; // 9: testo ricco
            $foglio = '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
                . '<row r="2"><c r="A2" t="s"><v>4</v></c><c r="B2" t="s"><v>5</v></c><c r="C2" s="1"><v>46096</v></c><c r="D2"><v>12.5</v></c></row>'
                . '<row r="3"><c r="A3" t="s"><v>6</v></c><c r="B3" t="s"><v>9</v></c><c r="C3" s="1"><v>45658</v></c><c r="D3"><f>1+1</f><v>2</v></c></row>';
            $stili = '<numFmts count="0"/><cellXfs count="2"><xf numFmtId="0" fontId="0"/><xf numFmtId="14" fontId="0" applyNumberFormat="1"/></cellXfs>';
            zip_scrivi($file, xlsx_base($foglio, $ss, $stili));
        } elseif ($tipo === 'libreoffice') {
            // LibreOffice: testi in linea, formato di data scritto (164 = DD/MM/YYYY), sì/no, un foglio con un altro nome, celle saltate.
            $foglio = '<row r="1"><c r="A1" t="inlineStr"><is><t>EMAIL</t></is></c><c r="B1" t="inlineStr"><is><t>NOME</t></is></c><c r="C1" t="inlineStr"><is><t>DATA_ARRIVO</t></is></c><c r="D1" t="inlineStr"><is><t>PUNTI</t></is></c><c r="E1" t="inlineStr"><is><t>CLIENTE</t></is></c></row>'
                . '<row r="2"><c r="A2" t="inlineStr"><is><t>libre1@xlsx.prova-importa.it</t></is></c><c r="B2" t="inlineStr"><is><t>Niccolò</t></is></c><c r="C2" s="2"><v>46022</v></c><c r="D2" s="0"><v>-3.25</v></c><c r="E2" t="b"><v>1</v></c></row>'
                . '<row r="4"><c r="A4" t="inlineStr"><is><t>libre2@xlsx.prova-importa.it</t></is></c><c r="E4" t="b"><v>0</v></c></row>';
            $stili = '<numFmts count="1"><numFmt numFmtId="164" formatCode="DD/MM/YYYY"/></numFmts><cellStyleXfs count="1"><xf numFmtId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0"/><xf numFmtId="2"/><xf numFmtId="164" applyNumberFormat="true"/></cellXfs>';
            zip_scrivi($file, xlsx_base($foglio, '', $stili, 'worksheets/foglio_contatti.xml'));
        } elseif ($tipo === 'xxe' || $tipo === 'dtd') {
            $ent = $tipo === 'xxe' ? '<!DOCTYPE sst [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>' : '<!DOCTYPE sst [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;"><!ENTITY c "&b;&b;&b;&b;&b;&b;&b;&b;&b;&b;">]>';
            $p = xlsx_base('<row r="1"><c r="A1" t="s"><v>0</v></c></row><row r="2"><c r="A2" t="s"><v>1</v></c></row>', '<si><t>EMAIL</t></si><si><t>' . ($tipo === 'xxe' ? '&xxe;' : '&c;') . '@x.it</t></si>');
            $p['xl/sharedStrings.xml'] = str_replace('<sst ', $ent . '<sst ', $p['xl/sharedStrings.xml']);
            zip_scrivi($file, $p);
        } elseif ($tipo === 'bomba') {
            // 100 MB di spazi dentro il foglio: compressi diventano poche centinaia di KB.
            $p = xlsx_base('<row r="1"><c r="A1" t="inlineStr"><is><t>EMAIL</t></is></c></row>');
            $p['xl/worksheets/sheet1.xml'] = str_replace('<sheetData>', '<sheetData>' . str_repeat(' ', 100 * 1048576), $p['xl/worksheets/sheet1.xml']);
            zip_scrivi($file, $p);
        } elseif ($tipo === 'percorsi') {
            $p = xlsx_base('<row r="1"><c r="A1" t="inlineStr"><is><t>EMAIL</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>percorso@x.it</t></is></c></row>');
            $p['../../percorso-cattivo.php'] = '<?php echo "dentro"; ?>';
            $p['/tmp/assoluto.txt'] = 'x';
            zip_scrivi($file, $p);
        }
        $out = is_file($file) ? filesize($file) : 0;
        break;
    case 'righe':
        [$file, $n, $sep] = $arg + [2 => ';'];
        $f = fopen($file, 'w');
        fwrite($f, implode($sep, ['EMAIL', 'NOME', 'PUNTI']) . "\n");
        for ($i = 1; $i <= (int)$n; $i++) fwrite($f, implode($sep, ["persona$i@grande.prova-importa.it", "Persona $i", (string)($i % 100)]) . "\n");
        fclose($f);
        $out = (int)$n;
        break;
    case 'lavori':
        $out = newsletter_lavori_giro((int)($arg[0] ?? 15));
        break;
    case 'imp':
        $out = newsletter_importazione((int)$arg[0]);
        break;
    case 'sql':
        $st = db()->query(str_replace('{p}', DB_PREFIX, (string)($arg[0] ?? '')));
        $out = $st->columnCount() ? $st->fetchAll() : $st->rowCount();
        break;
    case 'valori':
        $id = newsletter_id_da_email((string)$arg[0]);
        $out = $id ? newsletter_valori_di([$id])[$id] : null;
        break;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
