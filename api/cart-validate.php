<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$items = is_array($data['items'] ?? null) 
    ? $data['items'] 
    : (is_array($data['cart_items'] ?? null) ? $data['cart_items'] : []);
$fulfillmentType = in_array($data['fulfillment_type'] ?? '', ['pickup', 'delivery']) 
    ? $data['fulfillment_type'] 
    : 'pickup';
$zipCode = isset($data['zip_code']) ? trim((string)$data['zip_code']) : null;

$result = calculate_and_validate_cart($items, $fulfillmentType, $zipCode);

echo json_encode([
    'success' => true,
    'valid' => $result['valid'],
    'errors' => $result['errors'],
    'delivery_supported' => $result['delivery_supported'],
    'calculations' => $result['calculations'],
    'items_validated' => $result['items']
]);
