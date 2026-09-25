<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/includes/functions.php';

session_start();

// Simple auth check
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

$isAuthenticated = !empty($_SESSION['admin_user_id']) || (!empty($token) && !empty($_SESSION['admin_token']) && hash_equals($_SESSION['admin_token'], $token));

// Allow dev bypass for local VS Code testing if DEV_MODE=1
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
    // List all products and variants (including inactive)
    $products = get_all_products(false);
    echo json_encode(['success' => true, 'products' => $products]);
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

    $action = $data['action'] ?? '';

    if ($action === 'update_variant') {
        $variantId   = (int)($data['variant_id'] ?? 0);
        $stockStatus = in_array($data['stock_status'] ?? '', ['in_stock', 'out_of_stock']) 
            ? $data['stock_status'] 
            : 'in_stock';
        $stockQty    = max(0, (int)($data['stock_qty'] ?? 0));
        $price       = round((float)($data['price'] ?? 0.0), 2);

        $stmt = $db->prepare("
            UPDATE product_variants 
            SET stock_status = ?, stock_qty = ?, price = ?
            WHERE id = ?
        ");
        $stmt->execute([$stockStatus, $stockQty, $price, $variantId]);

        echo json_encode(['success' => true, 'message' => 'Variant updated successfully']);
        exit;
    }

    if ($action === 'toggle_product') {
        $productId = (int)($data['product_id'] ?? 0);
        $isActive  = !empty($data['is_active']) ? 1 : 0;

        $stmt = $db->prepare("UPDATE products SET is_active = ? WHERE id = ?");
        $stmt->execute([$isActive, $productId]);

        echo json_encode(['success' => true, 'message' => 'Product status updated']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}
