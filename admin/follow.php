<?php
/** Which zones and single tables a staff member follows (gets the calls of). */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$venueId = (int) current_venue_id();

$st = db()->prepare("SELECT * FROM users WHERE id = ? AND venue_id = ? AND role IN ('waiter','manager')");
$st->execute([(int) ($_GET['id'] ?? $_POST['id'] ?? 0), $venueId]);
$person = $st->fetch();
if (!$person) redirect('admin/staff.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $all = !empty($_POST['all']);
    save_following((int) $person['id'], $venueId, $all ? [] : (array) ($_POST['zones'] ?? []), $all ? [] : (array) ($_POST['tables'] ?? []));
    flash('Tavoli di ' . $person['name'] . ' salvati.');
    redirect('admin/staff.php');
}

$st = db()->prepare('SELECT id, label, zone FROM venue_tables WHERE venue_id = ? AND active = 1');
$st->execute([$venueId]);
$tables = $st->fetchAll();
sort_tables($tables);
$groups = [];
foreach ($tables as $t) $groups[(string) $t['zone']][] = $t;
$myZones = user_zones($person);
$myTables = user_table_ids($person);

page_head('Tavoli di ' . $person['name']);
admin_nav('staff');
?>
<main class="wrap">
  <form class="card form follow-form" method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
    <h2>Quali tavoli segue <?= h($person['name']) ?></h2>
    <p class="small muted">Riceve solo le chiamate dei tavoli scelti: zone intere o singoli tavoli, anche di zone diverse.
      Niente spuntato = tutti i tavoli. Un tavolo che non segue nessuno arriva comunque a tutti, così nessuna chiamata va persa.</p>
    <?php if (!$tables): ?><p class="muted">Ancora nessun tavolo.</p><?php endif; ?>
    <?php foreach ($groups as $zone => $list): ?>
      <fieldset class="follow-zone" data-zone>
        <legend>
          <?php if ($zone !== ''): ?>
            <label class="check chip zone"><input type="checkbox" name="zones[]" value="<?= h($zone) ?>" data-whole<?= in_array($zone, $myZones, true) ? ' checked' : '' ?>> Tutta la zona <?= h($zone) ?></label>
          <?php else: ?>
            <strong><?= count($groups) > 1 ? 'Senza zona' : 'Tavoli' ?></strong>
          <?php endif; ?>
        </legend>
        <div class="chips">
          <?php foreach ($list as $t): ?>
            <label class="chip"><input type="checkbox" name="tables[]" value="<?= (int) $t['id'] ?>"<?= in_array((int) $t['id'], $myTables, true) ? ' checked' : '' ?>> <?= h(table_name($t['label'])) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
    <div class="row-btns">
      <button class="btn primary">Salva</button>
      <button class="btn" name="all" value="1">Segue tutti i tavoli</button>
      <a class="btn ghost" href="<?= h(app_path('admin/staff.php')) ?>">Annulla</a>
    </div>
  </form>
</main>
<script>
// A whole zone ticked covers its single tables.
document.querySelectorAll('[data-zone]').forEach(function (fs) {
  var whole = fs.querySelector('[data-whole]');
  if (!whole) return;
  function sync() {
    fs.querySelectorAll('input[name="tables[]"]').forEach(function (cb) {
      cb.disabled = whole.checked;
      cb.closest('.chip').classList.toggle('covered', whole.checked);
    });
  }
  whole.addEventListener('change', sync);
  sync();
});
</script>
<?php
page_foot();
