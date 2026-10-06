<?php
/**
 * Menu PDF viewer (pdf.js), so the menu opens inside the page on every phone:
 * Android Chrome would otherwise download the PDF instead of showing it.
 */
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/guest_i18n.php';

$token = (string) ($_GET['k'] ?? '');
$table = table_by_token($token);
$venue = $table ? venue((int) $table['venue_id']) : null;
if (!$venue || !$venue['active'] || !$venue['menu_file']) {
    http_response_code(404);
    exit('Menu non disponibile.');
}
$pdf = app_path('file.php?v=' . $venue['id'] . '&f=menu&h=' . substr(md5($venue['menu_file']), 0, 8));
?><!doctype html>
<html lang="<?= h(guest_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= h($venue['color']) ?>">
<title><?= h(gt('menu')) ?> · <?= h($venue['name']) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>">
<?php if ($css = venue_css($venue)): ?><style><?= $css ?></style><?php endif; ?>
</head>
<body class="guest menu-page">
<header class="menu-bar">
  <a class="btn ghost small" href="<?= h(app_path('t/' . $token)) ?>">← <?= h(gt('back')) ?></a>
  <strong><?= h($venue['name']) ?></strong>
  <a class="btn ghost small" href="<?= h($pdf) ?>" download><?= h(gt('download')) ?></a>
</header>
<div id="pages" class="menu-pages"><p class="muted center-text" id="loading"><?= h(gt('loading')) ?></p></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
  var url = <?= json_encode($pdf) ?>, box = document.getElementById('pages');
  if (!window.pdfjsLib) { location.replace(url); return; }
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  pdfjsLib.getDocument(url).promise.then(function (pdf) {
    document.getElementById('loading').remove();
    var width = Math.min(box.clientWidth, 900), ratio = Math.min(window.devicePixelRatio || 1, 2.5);
    var chain = Promise.resolve();
    for (var n = 1; n <= pdf.numPages; n++) (function (n) {
      chain = chain.then(function () { return pdf.getPage(n); }).then(function (page) {
        var base = page.getViewport({ scale: 1 });
        var vp = page.getViewport({ scale: width / base.width * ratio });
        var canvas = document.createElement('canvas');
        canvas.width = vp.width; canvas.height = vp.height;
        canvas.style.width = width + 'px';
        box.appendChild(canvas);
        return page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
      });
    })(n);
  }).catch(function () { location.replace(url); });
})();
</script>
</body>
</html>
