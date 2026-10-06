<?php
require __DIR__ . '/../includes/app.php';

header('Content-Type: application/manifest+json');
echo json_encode([
    'name' => 'Chiamata – app cameriere',
    'short_name' => 'Chiamate',
    'start_url' => app_path('cameriere/'),
    'scope' => app_path('cameriere/'),
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#f4f6f5',
    'theme_color' => '#0f766e',
    'icons' => [
        ['src' => app_path('icon.php?s=192'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => app_path('icon.php?s=512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
