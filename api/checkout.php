<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!check_rate_limit('checkout_order', 15, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many checkout attempts. Please wait a minute and try again.']);
    exit;
}

require_csrf_token();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request payload']);
    exit;
}

// 1. Sanitize & Validate Customer Fields
$customer = $data['customer'] ?? [];
$fullName = trim((string)($customer['full_name'] ?? ''));
$email    = trim((string)($customer['email'] ?? ''));
$phone    = trim((string)($customer['phone'] ?? ''));
$address  = trim((string)($customer['street_address'] ?? ''));
$unit     = trim((string)($customer['unit'] ?? ''));
$city     = trim((string)($customer['city'] ?? ''));
$state    = strtoupper(trim((string)($customer['state'] ?? '')));
$zipCode  = trim((string)($customer['zip_code'] ?? ''));

if (empty($fullName) || empty($email) || empty($phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide your full name, email, and phone number.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
    exit;
}

// 2. Validate Fulfillment Fields
$fulfillment = is_array($data['fulfillment'] ?? null) ? $data['fulfillment'] : [];
$fulfillmentType = in_array($data['fulfillment_type'] ?? ($fulfillment['type'] ?? ''), ['pickup', 'delivery']) 
    ? ($data['fulfillment_type'] ?? $fulfillment['type']) 
    : 'pickup';
$fulfillmentDate = trim((string)($data['fulfillment_date'] ?? ($fulfillment['date'] ?? '')));
$fulfillmentTime = trim((string)($data['fulfillment_time_slot'] ?? ($data['fulfillment_time'] ?? ($fulfillment['time_slot'] ?? ($fulfillment['time'] ?? '')))));
$instructions    = trim((string)($data['special_instructions'] ?? ($fulfillment['instructions'] ?? '')));

if (empty($fulfillmentDate) || empty($fulfillmentTime)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select a preferred pickup or delivery date and time slot.']);
    exit;
}

if ($fulfillmentType === 'delivery') {
    if (empty($address) || empty($city) || empty($state) || empty($zipCode)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Full street address, city, state, and zip code are required for delivery.']);
        exit;
    }

    if (!is_delivery_city_supported($city)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => "City {$city} is outside our local delivery zone."]);
        exit;
    }
}

// 3. Strict Server-Side Cart Calculation & Price Re-computation
$rawItems = is_array($data['cart_items'] ?? null) 
    ? $data['cart_items'] 
    : (is_array($data['items'] ?? null) ? $data['items'] : []);
if (empty($rawItems)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Your cart is empty.']);
    exit;
}

$validationResult = calculate_and_validate_cart($rawItems, $fulfillmentType, $zipCode, $city);

if (!$validationResult['valid']) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Cart validation failed.',
        'details' => $validationResult['errors']
    ]);
    exit;
}

$calc = $validationResult['calculations'];
$validatedItems = $validationResult['items'];
$subtotal = $calc['subtotal'];
$deliveryFee = $calc['delivery_fee'];
$totalAmount = $calc['total'];

// 4. Square Payment Tokenization & Processing
$sourceId = trim((string)($data['payment']['source_id'] ?? ($data['square_nonce'] ?? ($data['source_id'] ?? ''))));
if (empty($sourceId)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing payment authorization token.']);
    exit;
}

// Generate unique order number
$orderNumber = 'WO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

// Process Square Payment via Payments API
$settings = get_site_settings();
$squareOrderId = '';
$squarePaymentId = '';
$paymentStatus = 'paid';

$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
$squareEnv = getenv('SQUARE_ENVIRONMENT') ?: ($settings['square_environment'] ?? 'sandbox');
$squareToken = getenv('SQUARE_ACCESS_TOKEN') ?: ($settings['square_access_token'] ?? '');
$squareLocationId = getenv('SQUARE_LOCATION_ID') ?: ($settings['square_location_id'] ?? '');

$allowDemoNonce = $appEnv !== 'production' && $sourceId === 'cnon:card-nonce-ok';
$isLiveSquare = !empty($squareToken) 
    && !str_starts_with($squareToken, 'EAAA_YOUR_') 
    && !str_starts_with($squareToken, 'sandbox-sq0')
    && !$allowDemoNonce;

if ($isLiveSquare) {
    $squareHost = $squareEnv === 'production' 
        ? 'https://connect.squareup.com' 
        : 'https://connect.squareupsandbox.com';

    $payload = [
        'source_id' => $sourceId,
        'idempotency_key' => bin2hex(random_bytes(16)),
        'amount_money' => [
            'amount' => (int)round($totalAmount * 100),
            'currency' => 'USD'
        ],
        'location_id' => $squareLocationId,
        'buyer_email_address' => $email,
        'note' => "Order {$orderNumber} - {$fullName}",
        'reference_id' => $orderNumber
    ];

    $body = json_encode($payload);
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: Bearer {$squareToken}\r\n" .
                         "Content-Type: application/json\r\n" .
                         "Square-Version: 2024-01-18\r\n" .
                         "Content-Length: " . strlen($body) . "\r\n",
            'content' => $body,
            'timeout' => 25,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents("{$squareHost}/v2/payments", false, $context);
    
    $httpCode = 500;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
    $responseData = json_decode((string)$response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['payment'])) {
        $paymentStatusValue = strtoupper((string)($responseData['payment']['status'] ?? ''));
        $expectedCents = (int)round(($subtotal + $deliveryFee) * 100);
        $actualCents = (int)($responseData['payment']['amount_money']['amount'] ?? 0);

        if ($paymentStatusValue !== 'COMPLETED') {
            http_response_code(402);
            echo json_encode(['success' => false, 'error' => 'Square payment was not completed.']);
            exit;
        }

        if ($actualCents !== $expectedCents) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Payment total mismatch.']);
            exit;
        }

        $squarePaymentId = $responseData['payment']['id'] ?? '';
        $squareOrderId = $responseData['payment']['order_id'] ?? '';
        $paymentStatus = 'paid';
    } else {
        $errorMsg = 'Payment authorization failed.';
        if (isset($responseData['errors'][0]['detail'])) {
            $errorMsg .= ' ' . $responseData['errors'][0]['detail'];
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }
} else {
    if (!$allowDemoNonce) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid payment source or payment token.']);
        exit;
    }

    $squarePaymentId = 'sq_sim_' . bin2hex(random_bytes(10));
    $squareOrderId   = 'sq_ord_' . bin2hex(random_bytes(8));
    $paymentStatus   = 'paid';
}

$idempotencyKey = hash('sha256', $orderNumber . '|' . $email . '|' . $totalAmount . '|' . $sourceId . '|' . time());

$db = get_db();
$db->beginTransaction();

try {
    // Customer record (lookup by email or insert)
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $customerId = (int)$existing['id'];
        $updateStmt = $db->prepare("
            UPDATE customers 
            SET full_name = ?, phone = ?, street_address = ?, unit = ?, city = ?, state = ?, zip_code = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$fullName, $phone, $address, $unit, $city, $state, $zipCode, $customerId]);
    } else {
        $insertCust = $db->prepare("
            INSERT INTO customers (full_name, email, phone, street_address, unit, city, state, zip_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertCust->execute([$fullName, $email, $phone, $address, $unit, $city, $state, $zipCode]);
        $customerId = (int)$db->lastInsertId();
    }

    // Insert Order
    $insertOrder = $db->prepare("
        INSERT INTO orders (
            order_number, customer_id, fulfillment_type, delivery_fee, subtotal, 
            total_amount, payment_method, payment_status, square_order_id, 
            square_payment_id, fulfillment_date, fulfillment_time_slot, 
            special_instructions, order_status
        ) VALUES (?, ?, ?, ?, ?, ?, 'square_card', ?, ?, ?, ?, ?, ?, 'processing')
    ");
    $insertOrder->execute([
        $orderNumber,
        $customerId,
        $fulfillmentType,
        $deliveryFee,
        $subtotal,
        $totalAmount,
        $paymentStatus,
        $squareOrderId,
        $squarePaymentId,
        $fulfillmentDate,
        $fulfillmentTime,
        $instructions
    ]);
    $orderId = (int)$db->lastInsertId();

    // Insert Order Items and Update Inventory
    $insertItem = $db->prepare("
        INSERT INTO order_items (
            order_id, item_type, product_id, variant_id, item_title, 
            variant_details, quantity, unit_price, line_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $updateStock = $db->prepare("
        UPDATE product_variants 
        SET stock_qty = MAX(0, stock_qty - ?),
            stock_status = CASE WHEN stock_qty - ? <= 0 THEN 'out_of_stock' ELSE 'in_stock' END
        WHERE id = ?
    ");

    foreach ($validatedItems as $line) {
        $insertItem->execute([
            $orderId,
            $line['type'],
            $line['product_id'],
            $line['variant_id'],
            $line['item_title'],
            $line['variant_details'],
            $line['quantity'],
            $line['unit_price'],
            $line['line_total']
        ]);

        if ($line['type'] === 'single' && !empty($line['variant_id'])) {
            $updateStock->execute([$line['quantity'], $line['quantity'], $line['variant_id']]);
        }
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error recording order: ' . $e->getMessage()]);
    exit;
}

$settings = get_site_settings();
$brandName = $settings['brand_name'] ?? "Shelly's Jellys LLC";
$pickupAddress = $settings['pickup_address'] ?? '458 Orchard Vista Way, Kalispell, MT 59901';
$primaryAdmin = normalize_email_address((string)($settings['admin_email_primary'] ?? ''));
$secondaryAdmin = normalize_email_address((string)($settings['admin_email_secondary'] ?? ''));

$fulfillmentSummary = $fulfillmentType === 'pickup'
    ? "Local Pickup\n{$pickupAddress}\nDate: {$fulfillmentDate} ({$fulfillmentTime})"
    : "Local Delivery\n{$address} {$unit}\n{$city}, {$state} {$zipCode}\nDate: {$fulfillmentDate} ({$fulfillmentTime})";

$orderRecord = [
    'order_number' => $orderNumber,
    'subtotal' => $subtotal,
    'delivery_fee' => $deliveryFee,
    'total_amount' => $totalAmount,
    'fulfillment_type' => $fulfillmentType,
    'special_instructions' => $instructions,
    'created_at' => date('Y-m-d H:i:s'),
    'fulfillment_summary' => $fulfillmentSummary,
];

$customerOrderPayload = [
    'email' => $email,
    'phone' => $phone,
    'full_name' => $fullName,
];

$customerItems = array_map(function ($line) {
    return [
        'item_title' => $line['item_title'],
        'quantity' => $line['quantity'],
        'unit_price' => $line['unit_price'],
        'line_total' => $line['line_total'],
    ];
}, $validatedItems);

$sent = send_order_notification_email($orderRecord, $customerOrderPayload, $customerItems);
if (!$sent) {
    error_log('Order email notification failed for order ' . $orderNumber);
}

echo json_encode([
    'success' => true,
    'order_number' => $orderNumber,
    'order_id' => $orderId,
    'fulfillment_type' => $fulfillmentType,
    'total_amount' => $totalAmount,
    'customer_email' => $email,
    'payment_id' => $squarePaymentId,
    'order' => [
        'order_number' => $orderNumber,
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'total_amount' => $totalAmount,
        'fulfillment_type' => $fulfillmentType,
        'fulfillment_date' => $fulfillmentDate,
        'fulfillment_time_slot' => $fulfillmentTime,
        'delivery_summary' => $fulfillmentSummary,
    ]
]);
