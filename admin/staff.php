<?php
/** Staff of the venue: waiters and managers. Never deleted, only disabled. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

$me = require_role(['manager', 'superadmin']);
$venueId = (int) current_venue_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $role = ($_POST['role'] ?? '') === 'manager' ? 'manager' : 'waiter';
    $password = (string) ($_POST['password'] ?? '');

    if ($action === 'add') {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 100);
        $username = mb_strtolower(trim((string) ($_POST['username'] ?? '')));
        $exists = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $exists->execute([$username]);
        if ($name === '' || !preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
            flash('Nome obbligatorio; utente di almeno 3 caratteri tra lettere, numeri, punto, trattino.', 'err');
        } elseif ($exists->fetchColumn()) {
            flash("L'utente \"$username\" esiste già: scegline un altro.", 'err');
        } elseif (strlen($password) < 6) {
            flash('La password deve avere almeno 6 caratteri.', 'err');
        } else {
            db()->prepare('INSERT INTO users (venue_id, role, name, username, password_hash) VALUES (?, ?, ?, ?, ?)')
                ->execute([$venueId, $role, $name, $username, password_hash($password, PASSWORD_DEFAULT)]);
            flash("$name aggiunto. Accesso: utente \"$username\".");
        }
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE id = ? AND venue_id = ?');
        $st->execute([(int) ($_POST['id'] ?? 0), $venueId]);
        $u = $st->fetch();
        if ($u && $action === 'toggle' && (int) $u['id'] !== (int) $me['id']) {
            db()->prepare('UPDATE users SET active = 1 - active WHERE id = ?')->execute([$u['id']]);
            if ($u['active']) {
                // Log out every device and stop its notifications.
                db()->prepare('DELETE FROM auth_tokens WHERE user_id = ?')->execute([$u['id']]);
                db()->prepare('DELETE FROM push_subscriptions WHERE user_id = ?')->execute([$u['id']]);
            }
            flash($u['active'] ? "{$u['name']} disattivato." : "{$u['name']} riattivato.");
        } elseif ($u && $action === 'password') {
            if (strlen($password) < 6) {
                flash('La password deve avere almeno 6 caratteri.', 'err');
            } else {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
                flash("Password di {$u['name']} cambiata.");
            }
        } elseif ($u && $action === 'role' && (int) $u['id'] !== (int) $me['id']) {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $u['id']]);
            flash("Ruolo di {$u['name']} aggiornato.");
        }
    }
    redirect('admin/staff.php');
}

$st = db()->prepare('SELECT u.*, (SELECT COUNT(*) FROM push_subscriptions p WHERE p.user_id = u.id) AS devices
                       FROM users u WHERE u.venue_id = ? ORDER BY u.active DESC, u.role, u.name');
$st->execute([$venueId]);
$staff = $st->fetchAll();

page_head('Personale');
admin_nav('staff');
?>
<main class="wrap">
  <form class="card form" method="post" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2>Aggiungi persona</h2>
    <div class="row">
      <label>Nome<input name="name" required maxlength="100" placeholder="Mario"></label>
      <label>Utente<input name="username" required maxlength="60" autocapitalize="none" placeholder="mario"></label>
      <label>Password<input name="password" required minlength="6" autocomplete="new-password"></label>
      <label>Ruolo<select name="role"><option value="waiter">Cameriere</option><option value="manager">Responsabile</option></select></label>
    </div>
    <button class="btn primary">Aggiungi</button>
    <p class="small muted">Il cameriere entra da <strong><?= h(abs_url('cameriere/')) ?></strong>. Il responsabile vede anche questa gestione.</p>
  </form>

  <div class="card">
    <h2>Personale</h2>
    <div class="table-wrap"><table class="list">
      <thead><tr><th>Nome</th><th>Utente</th><th>Ruolo</th><th>Notifiche</th><th>Ultimo accesso</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($staff as $u): $self = (int) $u['id'] === (int) $me['id']; ?>
        <tr class="<?= $u['active'] ? '' : 'off' ?>">
          <td><?= h($u['name']) ?><?= $u['active'] ? '' : ' <span class="tag">disattivato</span>' ?></td>
          <td><?= h($u['username']) ?></td>
          <td>
            <?php if ($self): ?><?= $u['role'] === 'manager' ? 'Responsabile' : 'Cameriere' ?><?php else: ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <select name="role" onchange="this.form.submit()">
                <option value="waiter"<?= $u['role'] === 'waiter' ? ' selected' : '' ?>>Cameriere</option>
                <option value="manager"<?= $u['role'] === 'manager' ? ' selected' : '' ?>>Responsabile</option>
              </select></form>
            <?php endif; ?>
          </td>
          <td><?= $u['devices'] ? (int) $u['devices'] . ' dispositivo/i' : '<span class="muted">—</span>' ?></td>
          <td><?= $u['last_login_at'] ? h(date('d/m H:i', strtotime($u['last_login_at']))) : '<span class="muted">mai</span>' ?></td>
          <td class="actions">
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input name="password" minlength="6" placeholder="Nuova password" autocomplete="new-password" required><button class="btn small">Cambia</button></form>
            <?php if (!$self): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn small<?= $u['active'] ? ' ghost' : '' ?>"><?= $u['active'] ? 'Disattiva' : 'Riattiva' ?></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</main>
<?php
page_foot();
