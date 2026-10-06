<?php
/** App icon (bell on teal), drawn with GD at the asked size: favicon, PWA, notifications. */
$s = (int) ($_GET['s'] ?? 192);
$s = in_array($s, [72, 180, 192, 512], true) ? $s : 192;

$im = imagecreatetruecolor($s, $s);
imagesavealpha($im, true);
$bg = imagecolorallocate($im, 0x0f, 0x76, 0x6e);
$fg = imagecolorallocate($im, 0xff, 0xff, 0xff);
imagefill($im, 0, 0, $bg);

$c = $s / 2;
$p = fn(float $f) => (int) round($f * $s);
imagefilledellipse($im, (int) $c, $p(0.25), $p(0.09), $p(0.09), $fg);              // knob
imagefilledellipse($im, (int) $c, $p(0.46), $p(0.42), $p(0.42), $fg);              // dome
imagefilledpolygon($im, [                                                           // body
    $p(0.29), $p(0.46), $p(0.71), $p(0.46),
    $p(0.78), $p(0.68), $p(0.22), $p(0.68),
], $fg);
imagefilledrectangle($im, $p(0.18), $p(0.66), $p(0.82), $p(0.72), $fg);            // rim
imagefilledellipse($im, (int) $c, $p(0.77), $p(0.13), $p(0.13), $fg);              // clapper

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
imagepng($im);
