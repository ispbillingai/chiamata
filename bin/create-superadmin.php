<?php
/**
 * Creates (or resets the password of) a superadmin.
 * Usage: php bin/create-superadmin.php <username> <password> ["Full name"]
 */
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/app.php';

[, $username, $password] = $argv + [null, null, null];
$name = $argv[3] ?? 'Super admin';
if (!$username || strlen((string) $password) < 8) {
    fwrite(STDERR, "Usage: php bin/create-superadmin.php <username> <password (8+ chars)> [\"Full name\"]\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$st = db()->prepare('SELECT id, role FROM users WHERE username = ?');
$st->execute([$username]);
$row = $st->fetch();
if ($row && $row['role'] !== 'superadmin') {
    fwrite(STDERR, "User $username exists and is not a superadmin.\n");
    exit(1);
}
if ($row) {
    db()->prepare('UPDATE users SET password_hash = ?, active = 1 WHERE id = ?')->execute([$hash, $row['id']]);
    echo "Password of superadmin $username updated.\n";
} else {
    db()->prepare("INSERT INTO users (venue_id, role, name, username, password_hash) VALUES (NULL, 'superadmin', ?, ?, ?)")
        ->execute([$name, $username, $hash]);
    echo "Superadmin $username created.\n";
}
