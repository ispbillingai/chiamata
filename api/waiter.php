<?php
/**
 * Waiter app API.
 *   GET  ?a=feed           open requests + tables with their current code
 *   GET  ?a=push_summary   what to show in a push notification (service worker)
 *   POST take {id} | done {id, close} | close_table {table_id} | set_zones {zones}
 *        push_subscribe {endpoint, keys} | push_unsubscribe {endpoint} | push_test
 * POSTs need the X-CSRF header.
 */
require __DIR__ . '/../includes/app.php';

$user = require_role(['waiter', 'manager', 'superadmin'], true);
$venueId = (int) current_venue_id();
$uid = (int) $user['id'];
$action = (string) ($_GET['a'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'feed') json_out(feed($venueId, $user));
    if ($action === 'push_summary') json_out(push_summary($venueId, $user));
    json_out(['error' => 'action'], 400);
}

csrf_check(true);
$in = input();
$action = (string) ($in['a'] ?? $action);

switch ($action) {
    case 'take':
        db()->prepare("UPDATE calls SET status = 'taken', taken_by = ?, taken_at = NOW()
                        WHERE id = ? AND venue_id = ? AND status = 'open'")
            ->execute([$uid, (int) ($in['id'] ?? 0), $venueId]);
        json_out(feed($venueId, $user));

    case 'done':
        $st = db()->prepare("SELECT table_id FROM calls WHERE id = ? AND venue_id = ? AND status IN ('open','taken')");
        $st->execute([(int) ($in['id'] ?? 0), $venueId]);
        $tableId = $st->fetchColumn();
        if ($tableId !== false) {
            if (!empty($in['close'])) {
                rotate_table_code((int) $tableId, $uid);   // also closes this call
            } else {
                db()->prepare("UPDATE calls SET status = 'done', done_by = ?, done_at = NOW(),
                                      taken_by = COALESCE(taken_by, ?), taken_at = COALESCE(taken_at, NOW())
                                WHERE id = ?")
                    ->execute([$uid, $uid, (int) $in['id']]);
            }
        }
        json_out(feed($venueId, $user));

    case 'close_table':
        $st = db()->prepare('SELECT id FROM venue_tables WHERE id = ? AND venue_id = ?');
        $st->execute([(int) ($in['table_id'] ?? 0), $venueId]);
        if ($st->fetchColumn() === false) json_out(['error' => 'table'], 404);
        rotate_table_code((int) $in['table_id'], $uid);
        json_out(feed($venueId, $user));

    case 'set_zones':
        $zones = array_values(array_filter(array_map('strval', (array) ($in['zones'] ?? [])), fn($z) => $z !== ''));
        db()->prepare('UPDATE users SET zones = ? WHERE id = ?')
            ->execute([$zones ? json_encode($zones, JSON_UNESCAPED_UNICODE) : null, $uid]);
        $user['zones'] = $zones ? json_encode($zones) : null;
        json_out(feed($venueId, $user));

    case 'push_subscribe':
        $endpoint = (string) ($in['endpoint'] ?? '');
        $keys = (array) ($in['keys'] ?? []);
        if (!preg_match('#^https://#', $endpoint) || empty($keys['p256dh']) || empty($keys['auth'])) json_out(['error' => 'subscription'], 400);
        db()->prepare('INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
                       VALUES (?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth),
                                               user_agent = VALUES(user_agent)')
            ->execute([$uid, $endpoint, hash('sha256', $endpoint), (string) $keys['p256dh'], (string) $keys['auth'],
                       substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
        json_out(['ok' => true]);

    case 'push_unsubscribe':
        db()->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?')
            ->execute([hash('sha256', (string) ($in['endpoint'] ?? '')), $uid]);
        json_out(['ok' => true]);

    case 'push_test':
        require_once __DIR__ . '/../includes/push.php';
        $st = db()->prepare('SELECT * FROM push_subscriptions WHERE user_id = ?');
        $st->execute([$uid]);
        $_SESSION['push_test'] = time();
        json_out(['ok' => true] + push_send($st->fetchAll()));
}

json_out(['error' => 'action'], 400);

function feed(int $venueId, array $user): array
{
    $st = db()->prepare("SELECT c.id, c.table_id, c.type, c.payment, c.status, c.repeat_count,
                                TIMESTAMPDIFF(SECOND, c.created_at, NOW()) AS age,
                                TIMESTAMPDIFF(SECOND, c.last_call_at, NOW()) AS last_age,
                                t.label, t.zone, u.name AS taken_name, c.taken_by
                           FROM calls c JOIN venue_tables t ON t.id = c.table_id
                           LEFT JOIN users u ON u.id = c.taken_by
                          WHERE c.venue_id = ? AND c.status IN ('open','taken')
                          ORDER BY c.created_at");
    $st->execute([$venueId]);
    $calls = [];
    foreach ($st->fetchAll() as $c) {
        if (!follows_zone($user, $c['zone'])) continue;
        $calls[] = [
            'id' => (int) $c['id'], 'table_id' => (int) $c['table_id'], 'label' => $c['label'], 'zone' => $c['zone'],
            'type' => $c['type'], 'payment' => $c['payment'], 'status' => $c['status'],
            'repeat' => (int) $c['repeat_count'], 'age' => (int) $c['age'], 'last_age' => (int) $c['last_age'],
            'taken_name' => $c['taken_name'], 'mine' => (int) $c['taken_by'] === (int) $user['id'],
        ];
    }

    $st = db()->prepare('SELECT t.id, t.label, t.zone, s.code, TIMESTAMPDIFF(MINUTE, s.opened_at, NOW()) AS code_age
                           FROM venue_tables t LEFT JOIN table_sessions s ON s.id = t.current_session_id
                          WHERE t.venue_id = ? AND t.active = 1');
    $st->execute([$venueId]);
    $rows = $st->fetchAll();
    sort_tables($rows);
    $zones = [];
    $tables = [];
    foreach ($rows as $t) {
        if ((string) $t['zone'] !== '') $zones[$t['zone']] = true;
        if (!follows_zone($user, $t['zone'])) continue;
        $tables[] = ['id' => (int) $t['id'], 'label' => $t['label'], 'zone' => $t['zone'],
                     'code' => $t['code'], 'code_age' => (int) $t['code_age']];
    }
    return ['calls' => $calls, 'tables' => $tables, 'zones' => array_keys($zones), 'my_zones' => user_zones($user)];
}

/** Text of the notification: the newest open request, or how many are waiting. */
function push_summary(int $venueId, array $user): array
{
    $open = array_values(array_filter(feed($venueId, $user)['calls'], fn($c) => $c['status'] === 'open'));
    if (!$open) {
        start_session();
        if (time() - (int) ($_SESSION['push_test'] ?? 0) < 120) {
            return ['title' => 'Notifiche attive ✓', 'body' => 'Riceverai qui le chiamate dei tavoli.', 'tag' => 'test'];
        }
        return ['title' => '', 'body' => '', 'tag' => ''];
    }
    usort($open, fn($a, $b) => $a['last_age'] <=> $b['last_age']);
    $what = fn($c) => $c['type'] === 'bill'
        ? 'Conto' . ($c['payment'] ? ' (' . ($c['payment'] === 'card' ? 'carta' : 'contanti') . ')' : '')
        : 'Chiama il cameriere';
    $c = $open[0];
    $title = table_name($c['label']) . ' · ' . $what($c) . ($c['repeat'] ? ' (sollecito)' : '');
    $body = count($open) > 1
        ? count($open) . ' richieste in attesa: ' . implode(', ', array_unique(array_map(fn($x) => table_name($x['label']), $open)))
        : ($c['zone'] ? $c['zone'] : 'Tocca per aprire');
    return ['title' => $title, 'body' => $body, 'tag' => 'calls'];
}
