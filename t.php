<?php
/**
 * Guest page, opened from the table QR (/t/<token>). The QR never changes:
 * the guest types the code the waiter gave them, valid until the bill is closed.
 */
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/guest_i18n.php';

$token = (string) ($_GET['k'] ?? '');
$table = table_by_token($token);
$venue = $table ? venue((int) $table['venue_id']) : null;
$lang = guest_lang();

header('Cache-Control: no-store');

if (!$table || !$table['active'] || !$venue || !$venue['active']) {
    http_response_code(404);
    ?><!doctype html><html lang="<?= h($lang) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chiamata</title><link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>"></head>
<body class="guest center"><div class="card"><p><?= h(gt('not_found')) ?></p></div></body></html><?php
    exit;
}

$sid = guest_cookie_session((int) $table['id']);
$verified = $sid !== null && $sid === (int) $table['current_session_id'];
$expired = $sid !== null && !$verified;

$menuHref = null;
if ($venue['menu_file']) $menuHref = app_path('menu.php?k=' . rawurlencode($token));
elseif ($venue['menu_url']) $menuHref = $venue['menu_url'];
$menuExternal = !$venue['menu_file'] && $venue['menu_url'];

$config = [
    'api'        => app_path('api/guest.php'),
    'token'      => $token,
    'verified'   => $verified,
    'codeLength' => (int) $venue['code_length'],
    'askPayment' => (bool) $venue['bill_ask_payment'],
    't'          => guest_texts(),
];
?><!doctype html>
<html lang="<?= h($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= h($venue['color']) ?>">
<title><?= h($venue['name']) ?> · <?= h(gt('table')) ?> <?= h($table['label']) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>">
<style>:root{--brand:<?= h($venue['color']) ?>}</style>
</head>
<body class="guest">
<main class="guest-main">
  <header class="guest-head">
    <?php if ($venue['logo_file']): ?>
      <img class="guest-logo" src="<?= h(app_path('file.php?v=' . $venue['id'] . '&f=logo&h=' . substr(md5($venue['logo_file']), 0, 8))) ?>" alt="<?= h($venue['name']) ?>">
    <?php else: ?>
      <h1 class="guest-name"><?= h($venue['name']) ?></h1>
    <?php endif; ?>
    <div class="guest-table"><?= h(gt('table')) ?> <strong><?= h($table['label']) ?></strong></div>
    <?php if ($venue['welcome_text']): ?><p class="guest-welcome"><?= nl2br(h($venue['welcome_text'])) ?></p><?php endif; ?>
  </header>

  <section id="codeBox" class="card guest-card"<?= $verified ? ' hidden' : '' ?>>
    <?php if ($expired): ?><p class="notice" id="expiredMsg"><?= h(gt('expired')) ?></p><?php endif; ?>
    <form id="codeForm" autocomplete="off">
      <label for="code" class="code-label"><?= h(gt('enter_code')) ?></label>
      <input id="code" class="code-input" inputmode="numeric" pattern="[0-9]*" maxlength="<?= (int) $venue['code_length'] ?>"
             autocomplete="one-time-code" placeholder="<?= str_repeat('•', (int) $venue['code_length']) ?>" required>
      <p class="muted small"><?= h(gt('code_hint')) ?></p>
      <p class="err-text" id="codeErr" hidden></p>
      <button class="btn primary big block" id="codeBtn"><?= h(gt('confirm')) ?></button>
    </form>
  </section>

  <section id="actions" class="guest-actions"<?= $verified ? '' : ' hidden' ?>>
    <button class="btn action" data-type="waiter"><span class="ico">🙋</span><?= h(gt('call_waiter')) ?></button>
    <?php if ($venue['bill_enabled']): ?>
      <button class="btn action" data-type="bill"><span class="ico">🧾</span><?= h(gt('ask_bill')) ?></button>
    <?php endif; ?>
  </section>

  <div id="status" class="guest-status" aria-live="polite"></div>
  <p class="toast" id="toast" hidden></p>

  <?php if ($menuHref): ?>
    <a class="btn ghost big block menu-btn" href="<?= h($menuHref) ?>"<?= $menuExternal ? ' target="_blank" rel="noopener"' : '' ?>><span class="ico">📖</span><?= h(gt('menu')) ?></a>
  <?php endif; ?>

  <footer class="langs">
    <?php foreach (GUEST_LANGS as $code => $name): ?>
      <a href="?k=<?= h(rawurlencode($token)) ?>&amp;lang=<?= $code ?>"<?= $code === $lang ? ' class="on"' : '' ?>><?= h($name) ?></a>
    <?php endforeach; ?>
  </footer>
</main>

<dialog id="payDialog" class="sheet">
  <h2><?= h(gt('pay_how')) ?></h2>
  <div class="pay-choices">
    <button class="btn action" data-pay="cash"><span class="ico">💶</span><?= h(gt('cash')) ?></button>
    <button class="btn action" data-pay="card"><span class="ico">💳</span><?= h(gt('card')) ?></button>
  </div>
  <button class="btn ghost block" data-pay=""><?= h(gt('cancel')) ?></button>
</dialog>

<script>window.GUEST = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= h(asset('assets/guest.js')) ?>"></script>
</body>
</html>
