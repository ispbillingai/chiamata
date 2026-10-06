<?php
/** Tables: create (one or many), rename, zone, enable/disable, new code, new QR link. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

$user = require_role(['manager', 'superadmin']);
$venueId = (int) current_venue_id();

function own_table(int $id, int $venueId): ?array
{
    $st = db()->prepare('SELECT * FROM venue_tables WHERE id = ? AND venue_id = ?');
    $st->execute([$id, $venueId]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $zone = trim((string) ($_POST['zone'] ?? ''));
    $zone = $zone === '' ? null : mb_substr($zone, 0, 60);

    if ($action === 'bulk') {
        $prefix = trim((string) ($_POST['prefix'] ?? ''));
        $from = max(1, (int) ($_POST['from'] ?? 1));
        $to = min($from + 199, max($from, (int) ($_POST['to'] ?? $from)));
        $existing = db()->prepare('SELECT label FROM venue_tables WHERE venue_id = ?');
        $existing->execute([$venueId]);
        $have = array_flip(array_map('mb_strtolower', $existing->fetchAll(PDO::FETCH_COLUMN)));
        $made = 0;
        for ($n = $from; $n <= $to; $n++) {
            $label = mb_substr(trim($prefix . ' ' . $n), 0, 40);
            if (isset($have[mb_strtolower($label)])) continue;
            create_table($venueId, $label, $zone);
            $made++;
        }
        flash($made ? "Creati $made tavoli." : 'Nessun tavolo nuovo: esistono già tutti.', $made ? 'ok' : 'err');
    } elseif ($action === 'add') {
        $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 40);
        if ($label !== '') { create_table($venueId, $label, $zone); flash("Tavolo $label creato."); }
    } elseif ($t = own_table((int) ($_POST['id'] ?? 0), $venueId)) {
        if ($action === 'save') {
            $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 40);
            if ($label !== '') {
                db()->prepare('UPDATE venue_tables SET label = ?, zone = ? WHERE id = ?')->execute([$label, $zone, $t['id']]);
                flash('Tavolo salvato.');
            }
        } elseif ($action === 'toggle') {
            db()->prepare('UPDATE venue_tables SET active = 1 - active WHERE id = ?')->execute([$t['id']]);
            flash($t['active'] ? "Tavolo {$t['label']} disattivato: il suo QR non funziona più." : "Tavolo {$t['label']} riattivato.");
        } elseif ($action === 'code') {
            $code = rotate_table_code((int) $t['id'], (int) $user['id']);
            flash("Tavolo {$t['label']}: nuovo codice $code.");
        } elseif ($action === 'token') {
            db()->prepare('UPDATE venue_tables SET qr_token = ? WHERE id = ?')->execute([random_token(), $t['id']]);
            flash("Tavolo {$t['label']}: nuovo link QR. Ristampa il suo QR, il vecchio non funziona più.");
        }
    }
    redirect('admin/tables.php');
}

$st = db()->prepare('SELECT t.*, s.code FROM venue_tables t LEFT JOIN table_sessions s ON s.id = t.current_session_id WHERE t.venue_id = ?');
$st->execute([$venueId]);
$tables = $st->fetchAll();
sort_tables($tables);
$zones = array_values(array_unique(array_filter(array_column($tables, 'zone'))));

page_head('Tavoli');
admin_nav('tables');
?>
<main class="wrap">
  <datalist id="zones"><?php foreach ($zones as $z): ?><option value="<?= h($z) ?>"><?php endforeach; ?></datalist>
  <div class="grid2">
    <form class="card form" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="bulk">
      <h2>Crea più tavoli</h2>
      <div class="row">
        <label>Nome<input name="prefix" value="Tavolo" maxlength="30"></label>
        <label>da<input type="number" name="from" value="1" min="1"></label>
        <label>a<input type="number" name="to" value="10" min="1"></label>
      </div>
      <label>Zona (facoltativa: Sala, Dehors, Piano 1…)<input name="zone" list="zones" maxlength="60"></label>
      <button class="btn primary">Crea</button>
    </form>
    <form class="card form" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <h2>Aggiungi un tavolo</h2>
      <label>Nome<input name="label" required maxlength="40" placeholder="es. 12, Bancone, Ombrellone 4"></label>
      <label>Zona<input name="zone" list="zones" maxlength="60"></label>
      <button class="btn">Aggiungi</button>
    </form>
  </div>

  <div class="card">
    <h2>Tavoli (<?= count($tables) ?>)</h2>
    <?php if (!$tables): ?><p class="muted">Ancora nessun tavolo.</p><?php else: ?>
    <div class="table-wrap"><table class="list">
      <thead><tr><th>Nome</th><th>Zona</th><th>Codice attuale</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tables as $t): ?>
        <tr class="<?= $t['active'] ? '' : 'off' ?>">
          <td colspan="2">
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $t['id'] ?>">
              <input name="label" value="<?= h($t['label']) ?>" maxlength="40" required aria-label="Nome">
              <input name="zone" value="<?= h($t['zone']) ?>" list="zones" maxlength="60" placeholder="Zona" aria-label="Zona">
              <button class="btn small">Salva</button>
            </form>
          </td>
          <td><span class="code"><?= h($t['code']) ?></span></td>
          <td class="actions">
            <a class="btn small" href="<?= h(table_url($t['qr_token'])) ?>" target="_blank">Apri</a>
            <a class="btn small" href="<?= h(app_path('admin/qr.php?table=' . $t['id'])) ?>">QR</a>
            <?php foreach (['code' => 'Nuovo codice', 'toggle' => $t['active'] ? 'Disattiva' : 'Attiva', 'token' => 'Nuovo link QR'] as $a => $label): ?>
              <form method="post" class="inline"<?= $a === 'token' ? ' onsubmit="return confirm(\'Il QR stampato di questo tavolo smetterà di funzionare. Continuare?\')"' : '' ?>>
                <?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="btn small<?= $a === 'token' ? ' ghost' : '' ?>"><?= h($label) ?></button>
              </form>
            <?php endforeach; ?>
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
