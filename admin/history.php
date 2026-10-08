<?php
/** Requests of a day with response times. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$venueId = (int) current_venue_id();
$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['day'] ?? '')) ? $_GET['day'] : date('Y-m-d');

// Calendar dots: number of requests per day of a month (?days=YYYY-MM), as JSON.
if (preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['days'] ?? ''))) {
    $from = $_GET['days'] . '-01';
    $st = db()->prepare('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM calls
                          WHERE venue_id = ? AND created_at >= ? AND created_at < ? + INTERVAL 1 MONTH GROUP BY d');
    $st->execute([$venueId, $from, $from]);
    json_out(array_map('intval', array_column($st->fetchAll(), 'n', 'd')));
}

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
    <?php
    $ts = strtotime($day);
    $dayLabel = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'][(int) date('w', $ts)] . ' ' . date('j', $ts) . ' '
        . ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'][(int) date('n', $ts) - 1]
        . ' ' . date('Y', $ts);
    $prev = date('Y-m-d', strtotime($day . ' -1 day'));
    $next = date('Y-m-d', strtotime($day . ' +1 day'));
    ?>
    <div class="day-picker">
      <a class="btn small" href="?day=<?= h($prev) ?>" aria-label="Giorno precedente">‹</a>
      <button class="btn day-btn" id="dayBtn" type="button" aria-haspopup="dialog"><?= h(mb_strtoupper(mb_substr($dayLabel, 0, 1)) . mb_substr($dayLabel, 1)) ?> ▾</button>
      <a class="btn small" href="?day=<?= h($next) ?>" aria-label="Giorno successivo">›</a>
      <?php if ($day !== date('Y-m-d')): ?><a class="btn small ghost" href="?day=<?= date('Y-m-d') ?>">Oggi</a><?php endif; ?>
      <div class="cal" id="cal" hidden>
        <div class="cal-head">
          <button type="button" class="cal-nav" data-step="-1" aria-label="Mese precedente">‹</button>
          <strong id="calTitle"></strong>
          <button type="button" class="cal-nav" data-step="1" aria-label="Mese successivo">›</button>
        </div>
        <div class="cal-grid" id="calGrid"></div>
        <p class="cal-legend"><span class="cal-dot"></span> giorni con richieste</p>
      </div>
    </div>
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
<script>
// Calendar of the history: a dot on the days that have requests.
(function () {
  var selected = <?= json_encode($day) ?>, today = <?= json_encode(date('Y-m-d')) ?>;
  var months = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
  var cal = document.getElementById('cal'), grid = document.getElementById('calGrid');
  var shown = new Date(selected.slice(0, 4), +selected.slice(5, 7) - 1, 1), cache = {};
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function key(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1); }
  function draw(counts) {
    document.getElementById('calTitle').textContent = months[shown.getMonth()] + ' ' + shown.getFullYear();
    grid.innerHTML = '';
    ['lun','mar','mer','gio','ven','sab','dom'].forEach(function (w, i) {
      var h = document.createElement('span'); h.className = 'cal-w' + (i > 4 ? ' we' : ''); h.textContent = w; grid.appendChild(h);
    });
    var first = (shown.getDay() + 6) % 7, days = new Date(shown.getFullYear(), shown.getMonth() + 1, 0).getDate();
    for (var i = 0; i < first; i++) grid.appendChild(document.createElement('span'));
    for (var d = 1; d <= days; d++) {
      var iso = key(shown) + '-' + pad(d), n = counts[iso] || 0, a = document.createElement('a');
      a.href = '?day=' + iso;
      a.className = 'cal-d' + (iso === selected ? ' sel' : '') + (iso === today ? ' today' : '') + (n ? ' has' : '') + ((first + d - 1) % 7 > 4 ? ' we' : '');
      a.innerHTML = '<span>' + d + '</span>' + (n ? '<i></i>' : '');
      if (n) a.title = n + (n === 1 ? ' richiesta' : ' richieste');
      grid.appendChild(a);
    }
  }
  function load() {
    var k = key(shown);
    draw(cache[k] || {});
    if (cache[k]) return;
    fetch('?days=' + k, { credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (c) { cache[k] = Array.isArray(c) ? {} : c; if (key(shown) === k) draw(cache[k]); }).catch(function () {});
  }
  document.getElementById('dayBtn').onclick = function (e) {
    e.stopPropagation();
    cal.hidden = !cal.hidden;
    if (!cal.hidden) load();
  };
  cal.querySelectorAll('.cal-nav').forEach(function (b) {
    b.onclick = function () { shown = new Date(shown.getFullYear(), shown.getMonth() + +b.dataset.step, 1); load(); };
  });
  cal.addEventListener('click', function (e) { e.stopPropagation(); });
  document.addEventListener('click', function () { cal.hidden = true; });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cal.hidden = true; });
})();
</script>
<?php
page_foot();
