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

if (!check_rate_limit('contact_form', 10, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment before sending another message.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

// Honeypot spam trap detection
$honeypot = trim((string)($data['website_url_hp'] ?? ($data['website'] ?? ($data['hp_field'] ?? ''))));
if (!empty($honeypot)) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for reaching out. We have received your inquiry and will be in touch shortly.'
    ]);
    exit;
}

$name = trim((string)($data['name'] ?? ($data['full_name'] ?? '')));
$email = trim((string)($data['email'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$inquiryType = trim((string)($data['inquiry_type'] ?? 'general'));
$eventDate = trim((string)($data['event_date'] ?? ''));
$estJars = trim((string)($data['estimated_jars'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$address = trim((string)($data['address'] ?? ''));

if (empty($name) || empty($email) || empty($message)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide your name, email, and message.']);
    exit;
}

$email = normalize_email_address($email);
if ($email === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
    exit;
}

$submissionKey = md5($email . '|' . $name . '|' . $message . '|' . $inquiryType . '|' . date('YmdHis'));
if (is_duplicate_submission('contact_' . $submissionKey, 1800)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'This message was already submitted. Please wait a moment and try again.']);
    exit;
}

$formPayload = [
    'customer_email' => $email,
    'customer_name' => $name,
    'phone' => $phone,
    'address' => $address,
    'estimated_jars' => $estJars,
    'event_date' => $eventDate,
    'message' => $message,
];

$sent = send_form_submission_email($formPayload, $inquiryType);
if (!$sent) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Your message could not be delivered at this time. Please try again later.']);
    exit;
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Thank you for reaching out. We have received your inquiry and will be in touch shortly.'
]);
