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
    // Silently discard bot submission
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for reaching out. We have received your inquiry and will be in touch shortly.'
    ]);
    exit;
}

$name        = trim((string)($data['name'] ?? ''));
$email       = trim((string)($data['email'] ?? ''));
$phone       = trim((string)($data['phone'] ?? ''));
$inquiryType = trim((string)($data['inquiry_type'] ?? 'general'));
$eventDate   = trim((string)($data['event_date'] ?? ''));
$estJars     = trim((string)($data['estimated_jars'] ?? ''));
$message     = trim((string)($data['message'] ?? ''));

if (empty($name) || empty($email) || empty($message)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide your name, email, and message.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
    exit;
}

$subject = "[Inquiry: " . ucfirst(str_replace('_', ' ', $inquiryType)) . "] Message from {$name}";

$body = "
<h2>New Inbound Inquiry</h2>
<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
<p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
<p><strong>Inquiry Type:</strong> " . htmlspecialchars(ucfirst(str_replace('_', ' ', $inquiryType))) . "</p>
" . (!empty($eventDate) ? "<p><strong>Target Event Date:</strong> " . htmlspecialchars($eventDate) . "</p>" : "") . "
" . (!empty($estJars) ? "<p><strong>Estimated Jars:</strong> " . htmlspecialchars($estJars) . "</p>" : "") . "
<hr>
<h3>Message:</h3>
<p>" . nl2br(htmlspecialchars($message)) . "</p>
";

send_admin_notification($subject, $body, $email);

echo json_encode([
    'success' => true,
    'message' => 'Thank you for reaching out. We have received your inquiry and will be in touch shortly.'
]);
