<?php
/** App icon (Upgrade mark) at the asked size: favicon, PWA, notifications (72 = transparent badge). */
$s = (int) ($_GET['s'] ?? 192);
$s = in_array($s, [72, 180, 192, 512], true) ? $s : 192;

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
readfile(__DIR__ . '/assets/brand/icon-' . $s . '.png');
