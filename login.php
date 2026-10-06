<?php
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/layout.php';

$u = current_user();
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
// Only paths inside this app, never another site.
$safeNext = (str_starts_with($next, app_path()) && !str_starts_with($next, '//')) ? $next : '';

if ($u) {
    header('Location: ' . ($safeNext ?: app_path(home_for($u))));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string) ($_POST['username'] ?? ''));
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();
    if ($row && password_verify((string) ($_POST['password'] ?? ''), $row['password_hash'])) {
        $user = load_user((int) $row['id']);
        if ($user) {
            login_user($user, !empty($_POST['remember']));
            header('Location: ' . ($safeNext ?: app_path(home_for($user))));
            exit;
        }
        $error = 'Account disattivato. Contatta il responsabile.';
    } else {
        usleep(400000);
        $error = 'Utente o password errati.';
    }
}

page_head('Accesso', 'center');
?>
<form class="card login" method="post">
  <img src="<?= h(asset('assets/brand/upgrade-logo.png')) ?>" alt="Upgrade" class="login-logo" width="220" height="72">
  <h1>Chiamata</h1>
  <p class="muted">Accesso personale</p>
  <?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= h($safeNext) ?>">
  <label>Utente<input name="username" autocomplete="username" autocapitalize="none" required value="<?= h($_POST['username'] ?? '') ?>"></label>
  <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
  <label class="check"><input type="checkbox" name="remember" value="1" checked> Resta collegato su questo dispositivo</label>
  <button class="btn primary block">Entra</button>
  <?php if ($video = demo_video_url()): ?>
    <a class="video-link" href="<?= h($video) ?>" download="Chiamata-video-dimostrativo.mp4">▶ Scarica il video dimostrativo (3 min)</a>
  <?php endif; ?>
</form>
<?php
page_foot(false);
