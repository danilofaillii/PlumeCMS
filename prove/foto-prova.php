<?php
// Costruisce le foto per prove/prova-foto-leggere.js (1.4.0), con GD, in <cartella>:
//   grande-girata.jpg  4000x3000 salvata "di lato" con orientamento EXIF 6 (va girata di un quarto in senso orario),
//                      un dato GPS finto e la scritta PROVA-GPS nei metadati; il quadrante rosso e in alto a sinistra nei pixel.
//   grande.jpg         3600x2400, senza EXIF (per il lato massimo impostato a mano e per l'interruttore spento)
//   piccola.png        600x400 con trasparenza (sotto il limite: le misure non cambiano)
// Uso: php foto-prova.php <cartella>
$d = rtrim($argv[1], '/'); @mkdir($d, 0755, true);
$foto = function (int $l, int $a) {
    $i = imagecreatetruecolor($l, $a);
    imagefill($i, 0, 0, imagecolorallocate($i, 40, 90, 200));
    imagefilledrectangle($i, 0, 0, intdiv($l, 2), intdiv($a, 2), imagecolorallocate($i, 220, 20, 20)); // rosso in alto a sinistra
    for ($k = 0; $k < 400; $k++) imagefilledellipse($i, mt_rand(0, $l), mt_rand(0, $a), mt_rand(10, 160), mt_rand(10, 160), imagecolorallocate($i, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
    return $i;
};
mt_srand(7);
// APP1 Exif minimo, little endian: IFD0 con Orientation=6 e il puntatore al GPS IFD; GPS IFD con GPSLatitudeRef e una stringa.
$ifd0 = pack('v', 2) . pack('vvVV', 0x0112, 3, 1, 6) . pack('vvVV', 0x8825, 4, 1, 38) . pack('V', 0);
$gps = pack('v', 2) . pack('vvVa4', 0x0001, 2, 2, "N\0\0\0") . pack('vvVV', 0x001B, 7, 10, 38 + 2 + 24 + 4) . pack('V', 0) . 'PROVA-GPS!';
$tiff = 'II' . pack('v', 42) . pack('V', 8) . $ifd0 . $gps;
$app1 = "\xFF\xE1" . pack('n', strlen($tiff) + 8) . "Exif\0\0" . $tiff;
$i = $foto(4000, 3000); ob_start(); imagejpeg($i, null, 92); $j = ob_get_clean();
file_put_contents("$d/grande-girata.jpg", substr($j, 0, 2) . $app1 . substr($j, 2));
$i = $foto(3600, 2400); imagejpeg($i, "$d/grande.jpg", 92);
$p = imagecreatetruecolor(600, 400); imagealphablending($p, false); imagesavealpha($p, true);
imagefill($p, 0, 0, imagecolorallocatealpha($p, 0, 0, 0, 127)); imagefilledellipse($p, 300, 200, 380, 300, imagecolorallocatealpha($p, 30, 160, 90, 0));
imagepng($p, "$d/piccola.png");
foreach (['grande-girata.jpg', 'grande.jpg', 'piccola.png'] as $f) echo $f, ' ', filesize("$d/$f"), "\n";
