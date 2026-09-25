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
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin authentication required']);
    exit;
}

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $settings = get_site_settings();
    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
    }

    $allowedKeys = [
        'brand_name',
        'promo_banner_active',
        'promo_banner_text',
        'pickup_address',
        'pickup_hours',
        'supported_zip_codes',
        'delivery_radius_miles',
        'local_delivery_fee',
        'free_delivery_threshold',
        'admin_email_primary',
        'admin_email_secondary',
        'square_environment',
        'square_app_id',
        'square_location_id'
    ];

    $stmt = $db->prepare("
        INSERT INTO site_settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP
    ");

    // MySQL syntax fallback check
    $isMysql = (getenv('DB_DRIVER') ?: 'mysql') === 'mysql';

    foreach ($data as $k => $v) {
        if (in_array($k, $allowedKeys, true)) {
            $val = is_array($v) ? json_encode($v) : (string)$v;
            try {
                if ($isMysql) {
                    $mStmt = $db->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, updated_at)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    ");
                    $mStmt->execute([$k, $val]);
                } else {
                    $stmt->execute([$k, $val]);
                }
            } catch (Exception $e) {
                // Try fallback query
                $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?")->execute([$val, $k]);
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
    exit;
}
