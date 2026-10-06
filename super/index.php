<?php
/** Superadmin: every venue; create a venue (with its manager and tables) in one form. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

$me = require_role(['superadmin'], false, false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'enter' && venue($id)) {
        $_SESSION['venue_ctx'] = $id;
        redirect('admin/');
    } elseif ($action === 'toggle' && ($v = venue($id))) {
        db()->prepare('UPDATE venues SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash($v['active'] ? "{$v['name']} disattivato: QR e accessi bloccati." : "{$v['name']} riattivato.");
    } elseif ($action === 'create') {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $mName = mb_substr(trim((string) ($_POST['manager_name'] ?? '')), 0, 100);
        $mUser = mb_strtolower(trim((string) ($_POST['manager_username'] ?? '')));
        $mPass = (string) ($_POST['manager_password'] ?? '');
        $nTables = max(0, min(200, (int) ($_POST['tables'] ?? 0)));
        $exists = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $exists->execute([$mUser]);
        if ($name === '' || $mName === '') {
            flash('Nome del locale e del responsabile sono obbligatori.', 'err');
        } elseif (!preg_match('/^[a-z0-9._-]{3,60}$/', $mUser) || $exists->fetchColumn()) {
            flash('Utente del responsabile non valido o già esistente.', 'err');
        } elseif (strlen($mPass) < 6) {
            flash('La password del responsabile deve avere almeno 6 caratteri.', 'err');
        } else {
            $pdo = db();
            $pdo->prepare('INSERT INTO venues (name) VALUES (?)')->execute([$name]);
            $venueId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO users (venue_id, role, name, username, password_hash) VALUES (?, 'manager', ?, ?, ?)")
                ->execute([$venueId, $mName, $mUser, password_hash($mPass, PASSWORD_DEFAULT)]);
            for ($n = 1; $n <= $nTables; $n++) create_table($venueId, 'Tavolo ' . $n, null);
            flash("Locale \"$name\" creato con $nTables tavoli. Il responsabile entra con l'utente \"$mUser\".");
        }
    }
    redirect('super/');
}

$venues = db()->query("SELECT v.*,
        (SELECT COUNT(*) FROM venue_tables t WHERE t.venue_id = v.id AND t.active = 1) AS tables,
        (SELECT COUNT(*) FROM users u WHERE u.venue_id = v.id AND u.active = 1) AS staff,
        (SELECT COUNT(*) FROM calls c WHERE c.venue_id = v.id AND c.created_at >= CURDATE()) AS today
    FROM venues v ORDER BY v.active DESC, v.name")->fetchAll();

page_head('Locali');
?>
<header class="topbar">
  <div class="topbar-in">
    <span class="brand"><img src="<?= h(asset('assets/brand/upgrade-logo.png')) ?>" alt="Upgrade" class="brand-logo" width="98" height="32">Chiamata · Super admin</span>
    <div class="who"><span><?= h($me['name']) ?></span> <a href="<?= h(app_path('logout.php')) ?>">Esci</a></div>
  </div>
</header>
<?php flash_box(); ?>
<main class="wrap">
  <form class="card form" method="post" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h2>Nuovo locale</h2>
    <div class="row">
      <label>Nome del locale<input name="name" required maxlength="120" placeholder="Ristorante Da Mario"></label>
      <label>Tavoli da creare<input type="number" name="tables" value="10" min="0" max="200"></label>
    </div>
    <div class="row">
      <label>Responsabile<input name="manager_name" required maxlength="100" placeholder="Mario Rossi"></label>
      <label>Utente<input name="manager_username" required maxlength="60" autocapitalize="none" placeholder="damario"></label>
      <label>Password<input name="manager_password" required minlength="6" autocomplete="new-password"></label>
    </div>
    <button class="btn primary">Crea locale</button>
  </form>

  <div class="card">
    <h2>Locali (<?= count($venues) ?>)</h2>
    <?php if (!$venues): ?><p class="muted">Nessun locale.</p><?php else: ?>
    <div class="table-wrap"><table class="list">
      <thead><tr><th>Locale</th><th>Tavoli</th><th>Personale</th><th>Richieste oggi</th><th>Creato</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($venues as $v): ?>
        <tr class="<?= $v['active'] ? '' : 'off' ?>">
          <td><span class="dot" style="background:<?= h($v['color']) ?>"></span> <?= h($v['name']) ?><?= $v['active'] ? '' : ' <span class="tag">disattivato</span>' ?></td>
          <td><?= (int) $v['tables'] ?></td>
          <td><?= (int) $v['staff'] ?></td>
          <td><?= (int) $v['today'] ?></td>
          <td><?= h(date('d/m/Y', strtotime($v['created_at']))) ?></td>
          <td class="actions">
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="enter"><input type="hidden" name="id" value="<?= $v['id'] ?>">
              <button class="btn small primary">Gestisci</button></form>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $v['id'] ?>">
              <button class="btn small ghost"><?= $v['active'] ? 'Disattiva' : 'Riattiva' ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</main>
<?php
page_foot();
