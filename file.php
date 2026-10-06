<?php
/** Serves a venue's logo or menu PDF from storage/ (not reachable directly). */
require __DIR__ . '/includes/app.php';

$venue = venue((int) ($_GET['v'] ?? 0));
$kind = (string) ($_GET['f'] ?? '');
$name = $venue ? ($kind === 'logo' ? $venue['logo_file'] : ($kind === 'menu' ? $venue['menu_file'] : null)) : null;
$path = $name ? __DIR__ . '/storage/v' . $venue['id'] . '/' . basename($name) : '';

if (!$venue || !$venue['active'] || !$name || !is_file($path)) {
    http_response_code(404);
    exit('Non trovato.');
}

$types = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'];
header('Content-Type: ' . ($types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
if ($kind === 'menu') header('Content-Disposition: inline; filename="menu.pdf"');
// URLs carry a hash of the file name (h=...), so a new upload gets a new URL.
header('Cache-Control: public, max-age=' . (isset($_GET['h']) ? 604800 : 300));
readfile($path);
