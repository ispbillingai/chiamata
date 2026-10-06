<?php
/**
 * Bootstrap for every page: DB, settings, staff login (with "remember me"),
 * CSRF, the guest's table cookie and the call logic shared by the APIs.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Europe/Rome');

const CALL_TYPES = ['waiter', 'bill'];
const BRAND_COLOR = '#0786c4';          // Upgrade blue, default colour of a venue's guest pages
const CALL_REPEAT_SECONDS = 20;      // a guest can call again (reminder) after this
const CODE_MAX_FAILS_TABLE = 8;      // wrong codes per IP and table, in 15 minutes
const CODE_MAX_FAILS_IP = 25;        // wrong codes per IP on any table, in 15 minutes

function db(): PDO
{
    return getDBConnection();
}

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// URLs
// ---------------------------------------------------------------------------

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Path of $rel inside the app ("/cameriere/" on the server, "/chiamata/cameriere/" in a local subfolder). */
function app_path(string $rel = ''): string
{
    static $base = null;
    if ($base === null) {
        $doc  = str_replace('\\', '/', (string) (realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''));
        $root = str_replace('\\', '/', (string) (realpath(__DIR__ . '/..') ?: ''));
        $base = ($doc !== '' && str_starts_with($root, $doc)) ? rtrim(substr($root, strlen(rtrim($doc, '/'))), '/') : '';
    }
    return $base . '/' . ltrim($rel, '/');
}

function abs_url(string $rel = ''): string
{
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . app_path($rel);
}

/** The fixed link printed in a table's QR. */
function table_url(string $token): string
{
    return abs_url('t/' . $token);
}

/** Static asset URL with a version so phones pick up new deploys. */
function asset(string $rel): string
{
    $file = __DIR__ . '/../' . $rel;
    return app_path($rel) . '?v=' . (is_file($file) ? filemtime($file) : 0);
}

const DEMO_VIDEO = 'media/Chiamata-demo.mp4';

/** Download link of the demo video, or null when the file is not on this server. */
function demo_video_url(): ?string
{
    return is_file(__DIR__ . '/../' . DEMO_VIDEO) ? app_path(DEMO_VIDEO) . '?v=' . filemtime(__DIR__ . '/../' . DEMO_VIDEO) : null;
}

/** Small "powered by Upgrade" footer, on staff and guest pages. */
function powered_by(): string
{
    return '<footer class="powered"><span>powered by</span><img src="' . h(asset('assets/brand/upgrade-logo.png'))
        . '" alt="Upgrade" width="96" height="31"></footer>';
}

function redirect(string $rel): never
{
    header('Location: ' . app_path($rel));
    exit;
}

// ---------------------------------------------------------------------------
// JSON
// ---------------------------------------------------------------------------

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Answers the browser now, then runs $after (e.g. push notifications) without making it wait. */
function json_out_then(array $data, callable $after): never
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $body = json_encode($data, JSON_UNESCAPED_UNICODE);
    ignore_user_abort(true);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Connection: close');
    header('Content-Length: ' . strlen($body));
    while (ob_get_level() > 0) ob_end_flush();
    echo $body;
    flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    try {
        $after();
    } catch (Throwable $e) {
        error_log('chiamata: after-response task failed: ' . $e->getMessage());
    }
    exit;
}

/** POST body: JSON or form fields. */
function input(): array
{
    static $in = null;
    if ($in === null) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        $in = is_array($json) ? $json + $_POST : $_POST;
    }
    return $in;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// ---------------------------------------------------------------------------
// App settings (key/value in the DB: secrets, VAPID keys)
// ---------------------------------------------------------------------------

function setting(string $k): ?string
{
    $st = db()->prepare('SELECT v FROM app_settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function setting_set(string $k, ?string $v): void
{
    db()->prepare('INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$k, $v]);
}

/** Value created once and then shared by every request (first writer wins). */
function setting_once(string $k, callable $make): string
{
    $v = setting($k);
    if ($v === null) {
        db()->prepare('INSERT IGNORE INTO app_settings (k, v) VALUES (?, ?)')->execute([$k, $make()]);
        $v = (string) setting($k);
    }
    return $v;
}

function app_secret(): string
{
    static $s = null;
    return $s ??= setting_once('app_secret', fn() => bin2hex(random_bytes(32)));
}

// ---------------------------------------------------------------------------
// Staff login
// ---------------------------------------------------------------------------

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('chiamata_s');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => app_path(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https(),
    ]);
    session_start();
}

const REMEMBER_COOKIE = 'chiamata_r';
const REMEMBER_DAYS = 180;

function set_remember_cookie(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires' => $expires, 'path' => app_path(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https(),
    ]);
}

function load_user(int $id): ?array
{
    $st = db()->prepare('SELECT u.*, v.active AS venue_active, v.name AS venue_name
                           FROM users u LEFT JOIN venues v ON v.id = u.venue_id WHERE u.id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u || !$u['active'] || ($u['venue_id'] !== null && !$u['venue_active'])) return null;
    return $u;
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    start_session();
    $user = null;
    $id = (int) ($_SESSION['uid'] ?? 0);
    if (!$id) $id = (int) restore_remember();
    if ($id) {
        $user = load_user($id);
        if (!$user) logout_user();
    }
    return $user;
}

function login_user(array $user, bool $remember): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    unset($_SESSION['venue_ctx']);
    if ($remember) {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + REMEMBER_DAYS * 86400;
        db()->prepare('INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires)]);
        set_remember_cookie($selector . ':' . $validator, $expires);
    }
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
}

function restore_remember(): ?int
{
    $cookie = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m)) return null;
    $st = db()->prepare('SELECT user_id, token_hash FROM auth_tokens WHERE selector = ? AND expires_at > NOW()');
    $st->execute([$m[1]]);
    $row = $st->fetch();
    if (!$row || !hash_equals($row['token_hash'], hash('sha256', $m[2]))) {
        set_remember_cookie('', time() - 3600);
        return null;
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $row['user_id'];
    return (int) $row['user_id'];
}

function logout_user(): void
{
    start_session();
    $cookie = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if (preg_match('/^([a-f0-9]{24}):/', $cookie, $m)) {
        db()->prepare('DELETE FROM auth_tokens WHERE selector = ?')->execute([$m[1]]);
    }
    set_remember_cookie('', time() - 3600);
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Venue the user works on; for the superadmin, the venue chosen in Super › Gestisci. */
function current_venue_id(): ?int
{
    $u = current_user();
    if (!$u) return null;
    if ($u['role'] === 'superadmin') return isset($_SESSION['venue_ctx']) ? (int) $_SESSION['venue_ctx'] : null;
    return (int) $u['venue_id'];
}

function home_for(array $u): string
{
    if ($u['role'] === 'superadmin') return 'super/';
    return $u['role'] === 'manager' ? 'admin/' : 'cameriere/';
}

/**
 * Page guard. $roles: who may enter; $json: API (401/403 JSON instead of redirects).
 * Venue pages (waiter, admin) also need a venue: a superadmin without one goes to Super.
 */
function require_role(array $roles, bool $json = false, bool $needVenue = true): array
{
    $u = current_user();
    if (!$u) {
        if ($json) json_out(['error' => 'login'], 401);
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    if (!in_array($u['role'], $roles, true)) {
        if ($json) json_out(['error' => 'forbidden'], 403);
        redirect(home_for($u));
    }
    if ($needVenue && !current_venue_id()) {
        if ($json) json_out(['error' => 'venue'], 403);
        redirect('super/');
    }
    return $u;
}

function csrf_token(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(bool $json = false): void
{
    $t = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(csrf_token(), $t)) {
        if ($json) json_out(['error' => 'csrf'], 400);
        http_response_code(400);
        exit('Sessione scaduta: ricarica la pagina e riprova.');
    }
}

/** One-shot message shown on the next page. */
function flash(?string $msg = null, string $kind = 'ok'): ?array
{
    start_session();
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $kind];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function user_zones(array $u): array
{
    $z = json_decode((string) ($u['zones'] ?? ''), true);
    return is_array($z) ? array_values(array_filter($z, 'is_string')) : [];
}

function user_table_ids(array $u): array
{
    $t = json_decode((string) ($u['table_ids'] ?? ''), true);
    return is_array($t) ? array_values(array_unique(array_map('intval', $t))) : [];
}

/**
 * Tables a waiter follows: nothing chosen = every table; otherwise the tables of
 * the chosen zones plus the single tables chosen. A table without a zone also
 * reaches whoever chose only zones.
 */
function follows_table(array $u, array $table): bool
{
    $zones = user_zones($u);
    $ids = user_table_ids($u);
    if (!$zones && !$ids) return true;
    if (in_array((int) $table['id'], $ids, true)) return true;
    $zone = (string) ($table['zone'] ?? '');
    if ($zone !== '' && in_array($zone, $zones, true)) return true;
    return $zone === '' && !$ids;
}

/** Active staff of the venue that receives calls (waiters and managers), with their choices. */
function venue_followers(int $venueId): array
{
    static $cache = [];
    if (!isset($cache[$venueId])) {
        $st = db()->prepare("SELECT id, zones, table_ids FROM users
                              WHERE venue_id = ? AND active = 1 AND role IN ('waiter','manager')");
        $st->execute([$venueId]);
        $cache[$venueId] = $st->fetchAll();
    }
    return $cache[$venueId];
}

/** $u gets this table's calls: they follow it, or nobody does (so no call is ever lost). */
function receives_table(array $u, array $table): bool
{
    if (follows_table($u, $table)) return true;
    foreach (venue_followers((int) $table['venue_id']) as $f) {
        if (follows_table($f, $table)) return false;
    }
    return true;
}

/**
 * Saves which zones and single tables a user follows (only ones of their venue).
 * Empty lists = every table.
 */
function save_following(int $userId, int $venueId, array $zones, array $tableIds): void
{
    $st = db()->prepare('SELECT id, zone FROM venue_tables WHERE venue_id = ? AND active = 1');
    $st->execute([$venueId]);
    $validIds = [];
    $validZones = [];
    foreach ($st->fetchAll() as $t) {
        $validIds[(int) $t['id']] = true;
        if ((string) $t['zone'] !== '') $validZones[$t['zone']] = true;
    }
    $zones = array_values(array_unique(array_filter(array_map('strval', $zones), fn($z) => isset($validZones[$z]))));
    $ids = array_values(array_unique(array_filter(array_map('intval', $tableIds), fn($i) => isset($validIds[$i]))));
    db()->prepare('UPDATE users SET zones = ?, table_ids = ? WHERE id = ?')->execute([
        $zones ? json_encode($zones, JSON_UNESCAPED_UNICODE) : null,
        $ids ? json_encode($ids) : null,
        $userId,
    ]);
}

/** "Tutti i tavoli" or e.g. "Sala · Tavolo 12, Tavolo 14", for the staff list and the waiter app. */
function following_summary(array $u, array $tablesById): string
{
    $parts = user_zones($u);
    foreach (user_table_ids($u) as $id) {
        if (isset($tablesById[$id])) $parts[] = table_name($tablesById[$id]['label']);
    }
    return $parts ? implode(', ', $parts) : 'Tutti i tavoli';
}

// ---------------------------------------------------------------------------
// Venues and tables
// ---------------------------------------------------------------------------

function venue(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM venues WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** CSS that gives the guest pages the venue's own colour; empty when it uses the Upgrade palette. */
function venue_css(array $venue): string
{
    $c = strtolower((string) $venue['color']);
    if ($c === BRAND_COLOR || !preg_match('/^#[0-9a-f]{6}$/', $c)) return '';
    return ":root{--brand:$c;--brand-dark:color-mix(in srgb,$c 80%,#000);--brand-soft:color-mix(in srgb,$c 10%,#fff);"
        . "--grad:linear-gradient(135deg,color-mix(in srgb,$c 70%,#fff) 0%,$c 100%)}";
}

function table_by_token(string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9]{6,16}$/', $token)) return null;
    $st = db()->prepare('SELECT t.*, s.code FROM venue_tables t
                           LEFT JOIN table_sessions s ON s.id = t.current_session_id WHERE t.qr_token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function random_token(int $len = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
    return $out;
}

function random_code(int $len, ?string $avoid = null): string
{
    $len = max(3, min(6, $len));
    do {
        $code = str_pad((string) random_int(0, 10 ** $len - 1), $len, '0', STR_PAD_LEFT);
    } while ($code === $avoid);
    return $code;
}

/** Natural order: "Tavolo 2" before "Tavolo 10", grouped by zone. */
function sort_tables(array &$tables): void
{
    usort($tables, fn($a, $b) => strnatcasecmp((string) $a['zone'], (string) $b['zone'])
        ?: strnatcasecmp($a['label'], $b['label']));
}

/** "12" -> "Tavolo 12"; names like "Tavolo 3" or "Bancone" stay as they are. $word: "Tavolo" in the reader's language. */
function table_name(string $label, string $word = 'Tavolo'): string
{
    return preg_match('/^\d+[A-Za-z]?$/', $label) ? $word . ' ' . $label : $label;
}

function create_table(int $venueId, string $label, ?string $zone): int
{
    $pdo = db();
    $v = venue($venueId);
    $pdo->prepare('INSERT INTO venue_tables (venue_id, label, zone, qr_token) VALUES (?, ?, ?, ?)')
        ->execute([$venueId, $label, $zone ?: null, random_token()]);
    $tableId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO table_sessions (venue_id, table_id, code) VALUES (?, ?, ?)')
        ->execute([$venueId, $tableId, random_code((int) $v['code_length'])]);
    $pdo->prepare('UPDATE venue_tables SET current_session_id = ? WHERE id = ?')
        ->execute([(int) $pdo->lastInsertId(), $tableId]);
    return $tableId;
}

/**
 * Bill closed / table freed: the guests' code stops working, open calls are
 * closed and the table gets a new code for the next customers.
 */
function rotate_table_code(int $tableId, ?int $userId): string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT t.id, t.venue_id, t.current_session_id, s.code, v.code_length
                               FROM venue_tables t JOIN venues v ON v.id = t.venue_id
                               LEFT JOIN table_sessions s ON s.id = t.current_session_id
                              WHERE t.id = ? FOR UPDATE');
        $st->execute([$tableId]);
        $t = $st->fetch();
        if (!$t) throw new RuntimeException('Tavolo non trovato');
        if ($t['current_session_id']) {
            $pdo->prepare('UPDATE table_sessions SET closed_at = NOW(), closed_by = ? WHERE id = ?')
                ->execute([$userId, $t['current_session_id']]);
            $pdo->prepare("UPDATE calls SET status = 'done', done_by = ?, done_at = NOW()
                            WHERE session_id = ? AND status IN ('open','taken')")
                ->execute([$userId, $t['current_session_id']]);
        }
        $code = random_code((int) $t['code_length'], $t['code']);
        $pdo->prepare('INSERT INTO table_sessions (venue_id, table_id, code) VALUES (?, ?, ?)')
            ->execute([$t['venue_id'], $tableId, $code]);
        $pdo->prepare('UPDATE venue_tables SET current_session_id = ? WHERE id = ?')
            ->execute([(int) $pdo->lastInsertId(), $tableId]);
        $pdo->commit();
        return $code;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ---------------------------------------------------------------------------
// Guest: the code typed at the table is remembered in a signed cookie
// ---------------------------------------------------------------------------

function guest_sig(int $tableId, int $sessionId): string
{
    return substr(hash_hmac('sha256', "guest|$tableId|$sessionId", app_secret()), 0, 32);
}

function guest_cookie_set(int $tableId, int $sessionId): void
{
    setcookie('cg' . $tableId, $sessionId . '.' . guest_sig($tableId, $sessionId), [
        'expires' => time() + 16 * 3600, 'path' => app_path(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https(),
    ]);
}

/** Session id from the guest's cookie for this table (may be an old, closed one), or null. */
function guest_cookie_session(int $tableId): ?int
{
    $c = (string) ($_COOKIE['cg' . $tableId] ?? '');
    if (!preg_match('/^(\d+)\.([a-f0-9]{32})$/', $c, $m)) return null;
    return hash_equals(guest_sig($tableId, (int) $m[1]), $m[2]) ? (int) $m[1] : null;
}

function code_attempts_blocked(int $tableId, string $ip): bool
{
    $st = db()->prepare('SELECT COUNT(*) AS total, SUM(table_id = ?) AS here FROM code_attempts
                          WHERE ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE');
    $st->execute([$tableId, $ip]);
    $r = $st->fetch();
    return (int) $r['here'] >= CODE_MAX_FAILS_TABLE || (int) $r['total'] >= CODE_MAX_FAILS_IP;
}

function code_attempt_failed(int $tableId, string $ip): void
{
    db()->prepare('INSERT INTO code_attempts (table_id, ip) VALUES (?, ?)')->execute([$tableId, $ip]);
    if (random_int(1, 50) === 1) db()->exec('DELETE FROM code_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
}

// ---------------------------------------------------------------------------
// Calls
// ---------------------------------------------------------------------------

/**
 * A guest calls the waiter or asks for the bill. A second tap on the same
 * request is a reminder (repeat_count), allowed every CALL_REPEAT_SECONDS.
 * Returns 'created', 'repeated' or 'wait'.
 */
function call_create(array $table, int $sessionId, string $type, ?string $payment): string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT id, TIMESTAMPDIFF(SECOND, last_call_at, NOW()) AS ago FROM calls
                              WHERE session_id = ? AND type = ? AND status IN ('open','taken')
                              ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $st->execute([$sessionId, $type]);
        $open = $st->fetch();
        if ($open && (int) $open['ago'] < CALL_REPEAT_SECONDS) {
            $pdo->commit();
            return 'wait';
        }
        if ($open) {
            $pdo->prepare('UPDATE calls SET repeat_count = repeat_count + 1, last_call_at = NOW(),
                                  payment = COALESCE(?, payment) WHERE id = ?')
                ->execute([$payment, $open['id']]);
            $result = 'repeated';
        } else {
            $pdo->prepare('INSERT INTO calls (venue_id, table_id, session_id, type, payment) VALUES (?, ?, ?, ?, ?)')
                ->execute([$table['venue_id'], $table['id'], $sessionId, $type, $payment]);
            $result = 'created';
        }
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Push to the venue's waiters and managers who follow this table (see receives_table),
 * or to all of them ($everyone: nobody answered in time, see bin/escalate.php).
 */
function notify_staff(array $table, bool $everyone = false): void
{
    require_once __DIR__ . '/push.php';
    $st = db()->prepare("SELECT p.*, u.zones, u.table_ids FROM push_subscriptions p JOIN users u ON u.id = p.user_id
                          WHERE u.venue_id = ? AND u.active = 1 AND u.role IN ('waiter','manager')");
    $st->execute([$table['venue_id']]);
    $subs = array_filter($st->fetchAll(), fn($s) => $everyone || receives_table($s, $table));
    push_send(array_values($subs));
}
