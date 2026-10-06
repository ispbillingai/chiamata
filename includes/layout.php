<?php
/** Header and footer of the staff pages (admin, super). */
declare(strict_types=1);

function page_head(string $title, string $bodyClass = ''): void
{
    ?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?> · Chiamata</title>
<link rel="icon" href="<?= h(app_path('icon.php?s=192')) ?>">
<link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>">
</head>
<body class="<?= h($bodyClass) ?>">
<?php
}

function page_foot(): void
{
    echo "</body>\n</html>\n";
}

/** Top bar of the venue admin. $active: key of the current page. */
function admin_nav(string $active): void
{
    $u = current_user();
    $v = venue((int) current_venue_id());
    $items = [
        'settings' => ['admin/', 'Impostazioni'],
        'tables'   => ['admin/tables.php', 'Tavoli'],
        'qr'       => ['admin/qr.php', 'QR da stampare'],
        'staff'    => ['admin/staff.php', 'Personale'],
        'history'  => ['admin/history.php', 'Storico'],
    ];
    ?>
<header class="topbar">
  <div class="topbar-in">
    <a class="brand" href="<?= h(app_path('admin/')) ?>"><span class="dot" style="background:<?= h($v['color']) ?>"></span><?= h($v['name']) ?></a>
    <nav class="nav">
      <?php foreach ($items as $k => [$href, $label]): ?>
        <a href="<?= h(app_path($href)) ?>"<?= $k === $active ? ' class="on"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= h(app_path('cameriere/')) ?>">App cameriere</a>
    </nav>
    <div class="who">
      <?php if ($u['role'] === 'superadmin'): ?><a href="<?= h(app_path('super/')) ?>">← Tutti i locali</a><?php endif; ?>
      <span><?= h($u['name']) ?></span> <a href="<?= h(app_path('logout.php')) ?>">Esci</a>
    </div>
  </div>
</header>
<?php
    flash_box();
}

function flash_box(): void
{
    $f = flash();
    if ($f) echo '<div class="wrap"><div class="flash ' . h($f[1]) . '">' . h($f[0]) . '</div></div>';
}
