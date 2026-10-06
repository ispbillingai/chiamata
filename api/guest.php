<?php
/**
 * Guest API (table QR page). POST JSON {a, k, ...}:
 *   verify  {code}            -> checks the table code, sets the guest cookie
 *   call    {type, payment?}  -> call the waiter / ask for the bill
 *   cancel  {id}              -> withdraw an open request
 *   status                    -> this guest's open requests
 * The guest cookie is SameSite=Lax, so other sites cannot call on a guest's behalf.
 */
require __DIR__ . '/../includes/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method'], 405);

$in = input();
$table = table_by_token((string) ($in['k'] ?? ''));
$venue = $table ? venue((int) $table['venue_id']) : null;
if (!$table || !$table['active'] || !$venue || !$venue['active']) json_out(['error' => 'not_found'], 404);

$tableId = (int) $table['id'];
$current = (int) $table['current_session_id'];
$action = (string) ($in['a'] ?? '');

if ($action === 'verify') {
    $ip = client_ip();
    if (code_attempts_blocked($tableId, $ip)) json_out(['error' => 'too_many'], 429);
    $code = preg_replace('/\D/', '', (string) ($in['code'] ?? ''));
    if ($code === '' || !$table['code'] || !hash_equals((string) $table['code'], $code)) {
        code_attempt_failed($tableId, $ip);
        json_out(['error' => 'wrong_code'], 400);
    }
    guest_cookie_set($tableId, $current);
    json_out(['ok' => true, 'calls' => session_calls($current)]);
}

// Every other action needs the code of the current customers.
$sid = guest_cookie_session($tableId);
if ($sid === null) json_out(['error' => 'code'], 403);
if ($sid !== $current) json_out(['error' => 'expired'], 403);

if ($action === 'call') {
    $type = (string) ($in['type'] ?? '');
    if (!in_array($type, CALL_TYPES, true) || ($type === 'bill' && !$venue['bill_enabled'])) json_out(['error' => 'type'], 400);
    $payment = $type === 'bill' && in_array($in['payment'] ?? null, ['cash', 'card'], true) ? $in['payment'] : null;
    $result = call_create($table, $current, $type, $payment);
    $data = ['ok' => true, 'result' => $result, 'calls' => session_calls($current)];
    if ($result === 'wait') json_out($data);
    json_out_then($data, fn() => notify_staff($table));
}

if ($action === 'cancel') {
    db()->prepare("UPDATE calls SET status = 'cancelled', done_at = NOW() WHERE id = ? AND session_id = ? AND status = 'open'")
        ->execute([(int) ($in['id'] ?? 0), $current]);
    json_out(['ok' => true, 'calls' => session_calls($current)]);
}

if ($action === 'status') {
    json_out(['ok' => true, 'calls' => session_calls($current)]);
}

json_out(['error' => 'action'], 400);

function session_calls(int $sessionId): array
{
    $st = db()->prepare("SELECT id, type, payment, status, TIMESTAMPDIFF(SECOND, last_call_at, NOW()) AS ago
                           FROM calls WHERE session_id = ? AND status IN ('open','taken') ORDER BY id");
    $st->execute([$sessionId]);
    return array_map(fn($c) => [
        'id' => (int) $c['id'], 'type' => $c['type'], 'payment' => $c['payment'],
        'status' => $c['status'], 'ago' => (int) $c['ago'],
    ], $st->fetchAll());
}
