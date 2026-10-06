<?php
/** Requests of a day with response times. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$venueId = (int) current_venue_id();
$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['day'] ?? '')) ? $_GET['day'] : date('Y-m-d');

$st = db()->prepare("SELECT c.*, t.label, t.zone, ut.name AS taken_name, ud.name AS done_name,
                            TIMESTAMPDIFF(SECOND, c.created_at, COALESCE(c.taken_at, c.done_at)) AS response
                       FROM calls c JOIN venue_tables t ON t.id = c.table_id
                       LEFT JOIN users ut ON ut.id = c.taken_by LEFT JOIN users ud ON ud.id = c.done_by
                      WHERE c.venue_id = ? AND c.created_at >= ? AND c.created_at < ? + INTERVAL 1 DAY
                      ORDER BY c.created_at DESC");
$st->execute([$venueId, $day, $day]);
$calls = $st->fetchAll();

$answered = array_filter($calls, fn($c) => $c['status'] !== 'cancelled' && $c['response'] !== null);
$avg = $answered ? array_sum(array_column($answered, 'response')) / count($answered) : null;
$fmt = fn($s) => $s === null ? '—' : ($s < 60 ? $s . ' s' : floor($s / 60) . ' min ' . ($s % 60) . ' s');
$status = ['open' => 'In attesa', 'taken' => 'Preso', 'done' => 'Fatto', 'cancelled' => 'Annullato dal cliente'];

page_head('Storico');
admin_nav('history');
?>
<main class="wrap">
  <div class="card">
    <form method="get" class="inline">
      <label>Giorno <input type="date" name="day" value="<?= h($day) ?>" onchange="this.form.submit()"></label>
    </form>
    <div class="stats">
      <div><strong><?= count(array_filter($calls, fn($c) => $c['type'] === 'waiter')) ?></strong><span>chiamate</span></div>
      <div><strong><?= count(array_filter($calls, fn($c) => $c['type'] === 'bill')) ?></strong><span>richieste conto</span></div>
      <div><strong><?= h($fmt($avg === null ? null : (int) round($avg))) ?></strong><span>risposta media</span></div>
    </div>
  </div>
  <div class="card">
    <?php if (!$calls): ?><p class="muted">Nessuna richiesta in questo giorno.</p><?php else: ?>
    <div class="table-wrap"><table class="list">
      <thead><tr><th>Ora</th><th>Tavolo</th><th>Richiesta</th><th>Stato</th><th>Risposta</th><th>Cameriere</th></tr></thead>
      <tbody>
      <?php foreach ($calls as $c): ?>
        <tr>
          <td><?= h(date('H:i', strtotime($c['created_at']))) ?></td>
          <td><?= h($c['label']) ?><?= $c['zone'] ? ' <span class="muted small">' . h($c['zone']) . '</span>' : '' ?></td>
          <td><?= $c['type'] === 'bill' ? 'Conto' . ($c['payment'] ? ' (' . ($c['payment'] === 'card' ? 'carta' : 'contanti') . ')' : '') : 'Cameriere' ?>
              <?= $c['repeat_count'] ? ' <span class="tag">sollecitato ' . (int) $c['repeat_count'] . '×</span>' : '' ?>
              <?= $c['escalated_at'] ? ' <span class="tag warn">nessuna risposta: avvisati tutti</span>' : ($c['reminded_at'] ? ' <span class="tag">promemoria inviato</span>' : '') ?></td>
          <td><?= h($status[$c['status']]) ?></td>
          <td><?= $c['status'] === 'cancelled' ? '—' : h($fmt($c['response'] === null ? null : (int) $c['response'])) ?></td>
          <td><?= h($c['taken_name'] ?: $c['done_name'] ?: '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</main>
<?php
page_foot();
