<?php
/** Printable QR cards (A4, 6 per page), one per table. ?table=ID for one, ?png=ID to download the image. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$venueId = (int) current_venue_id();
$venue = venue($venueId);

function qr_out(string $text, string $type): ?string
{
    if (!is_executable('/usr/bin/qrencode')) return null;
    $out = shell_exec('/usr/bin/qrencode -t ' . $type . ' -s 12 -m 2 -l M -o - ' . escapeshellarg($text));
    return is_string($out) && $out !== '' ? $out : null;
}

if (isset($_GET['png'])) {
    $st = db()->prepare('SELECT * FROM venue_tables WHERE id = ? AND venue_id = ?');
    $st->execute([(int) $_GET['png'], $venueId]);
    $t = $st->fetch();
    $png = $t ? qr_out(table_url($t['qr_token']), 'PNG') : null;
    if (!$png) { http_response_code(404); exit('QR non disponibile.'); }
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="qr-' . preg_replace('/[^A-Za-z0-9]+/', '-', $t['label']) . '.png"');
    echo $png;
    exit;
}

$sql = 'SELECT * FROM venue_tables WHERE venue_id = ? AND active = 1';
$args = [$venueId];
if (isset($_GET['table'])) { $sql .= ' AND id = ?'; $args[] = (int) $_GET['table']; }
if (($_GET['zone'] ?? '') !== '') { $sql .= ' AND zone = ?'; $args[] = $_GET['zone']; }
$st = db()->prepare($sql);
$st->execute($args);
$tables = $st->fetchAll();
sort_tables($tables);

$zst = db()->prepare("SELECT DISTINCT zone FROM venue_tables WHERE venue_id = ? AND active = 1 AND zone IS NOT NULL AND zone <> '' ORDER BY zone");
$zst->execute([$venueId]);
$zones = $zst->fetchAll(PDO::FETCH_COLUMN);

page_head('QR da stampare', 'qr-print');
admin_nav('qr');
?>
<main class="wrap">
  <div class="card no-print qr-tools">
    <form method="get" class="inline">
      <label>Zona
        <select name="zone" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <?php foreach ($zones as $z): ?><option<?= ($_GET['zone'] ?? '') === $z ? ' selected' : '' ?>><?= h($z) ?></option><?php endforeach; ?>
        </select>
      </label>
    </form>
    <button class="btn primary" onclick="window.print()">Stampa</button>
    <span class="small muted">Il QR di ogni tavolo non cambia mai: cambia solo il codice che dà il cameriere.</span>
  </div>
  <?php if (!$tables): ?><p class="card">Nessun tavolo attivo. <a href="<?= h(app_path('admin/tables.php')) ?>">Crea i tavoli</a>.</p><?php endif; ?>
  <div class="qr-sheet">
    <?php foreach ($tables as $t): $url = table_url($t['qr_token']); $svg = qr_out($url, 'SVG'); ?>
      <div class="qr-card" style="--brand:<?= h($venue['color']) ?>">
        <div class="qr-venue"><?= h($venue['name']) ?></div>
        <div class="qr-label"><?= h($t['label']) ?></div>
        <div class="qr-img"><?= $svg ? preg_replace('/^.*?(<svg)/s', '$1', $svg) : '<p>qrencode non installato</p>' ?></div>
        <div class="qr-text">Inquadra per chiamare il cameriere, chiedere il conto e vedere il menu</div>
        <div class="qr-text en">Scan to call the waiter, ask for the bill and see the menu</div>
        <a class="no-print small" href="?png=<?= $t['id'] ?>">Scarica PNG</a>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<?php
page_foot();
