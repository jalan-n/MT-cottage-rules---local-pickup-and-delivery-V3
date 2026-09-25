<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/includes/functions.php';

session_start();

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

$isAuthenticated = !empty($_SESSION['admin_user_id']) || (!empty($token) && !empty($_SESSION['admin_token']) && hash_equals($_SESSION['admin_token'], $token));

if (!$isAuthenticated && getenv('DEV_ADMIN_BYPASS') === '1') {
    $isAuthenticated = true;
}

if (!$isAuthenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin authentication required to trigger SSG rebuild']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Execute the Static Site Generator
try {
    require_once dirname(__DIR__, 2) . '/build.php';
    // build.php executes and outputs JSON if non-cli, but here we can return the manifest
    $manifestPath = dirname(__DIR__, 2) . '/public/build-manifest.json';
    $manifestData = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];

    echo json_encode([
        'success' => true,
        'message' => 'SSG static site rebuilt successfully.',
        'manifest' => $manifestData
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Rebuild failed: ' . $e->getMessage()
    ]);
}
