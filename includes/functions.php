<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

/**
 * Global Helper Functions & Business Logic Engine
 */

function get_db(): PDO {
    return Database::getConnection();
}

function canonical_product_slug(string $value): string {
    $slug = strtolower(trim((string)$value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'product';
}

function normalize_product_image_url(?string $imageUrl, ?string $slug = null): string {
    $base = trim((string)(getenv('APP_BASE_PATH') ?: getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food'));
    $base = rtrim($base, '/');
    $projectRoot = dirname(__DIR__);

    $resolvedSlug = canonical_product_slug((string)($slug ?? ''));
    $fallback = $base . '/public/assets/images/' . ($resolvedSlug !== '' ? $resolvedSlug . '.svg' : 'jam-jar-hero.svg');

    $raw = trim((string)($imageUrl ?? ''));
    if ($raw === '') {
        return $fallback;
    }

    if (preg_match('/^(https?:)?\/\//i', $raw) === 1 || str_starts_with($raw, 'data:')) {
        return $raw;
    }

    $path = $raw;
    if (str_starts_with($path, '/')) {
        $path = preg_replace('#^' . preg_quote($base, '#') . '#', '', $path) ?: $path;
        $path = '/' . ltrim($path, '/');
    } elseif (str_starts_with($path, 'public/')) {
        $path = '/' . $path;
    }

    $absPath = $projectRoot . $path;
    if (file_exists($absPath)) {
        return $raw;
    }

    if (str_contains($path, '/public/images/') || str_contains($path, '/images/')) {
        $rawPath = $projectRoot . '/public' . preg_replace('#^/public#', '', $path, 1);
        if (file_exists($rawPath)) {
            return $raw;
        }
    }

    if (str_contains(strtolower($raw), '/assets/images/')) {
        return $raw;
    }

    return $fallback;
}

/**
 * Retrieve all site settings as a key-value associative array
 */
function get_site_settings(): array {
    $db = get_db();
    $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Retrieve all active products with their associated variants
 */
function get_all_products(bool $activeOnly = true): array {
    $db = get_db();
    $sql = "SELECT * FROM products";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY display_order ASC, id ASC";

    $stmt = $db->query($sql);
    $products = $stmt->fetchAll();

    foreach ($products as &$product) {
        $vStmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY price ASC");
        $vStmt->execute([$product['id']]);
        $product['variants'] = $vStmt->fetchAll();
    }
    unset($product);

    return $products;
}

/**
 * Retrieve single product by slug with variants
 */
function get_product_by_slug(string $slug): ?array {
    $db = get_db();
    $normalizedSlug = canonical_product_slug($slug);
    $stmt = $db->query("SELECT * FROM products WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        if (canonical_product_slug((string)($product['slug'] ?? '')) === $normalizedSlug) {
            $vStmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY price ASC");
            $vStmt->execute([$product['id']]);
            $product['variants'] = $vStmt->fetchAll();
            return $product;
        }
    }

    return null;
}

/**
 * Retrieve all bundle configurations
 */
function get_bundle_configs(bool $activeOnly = true): array {
    $db = get_db();
    $sql = "SELECT * FROM bundle_configs";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY id ASC";
    return $db->query($sql)->fetchAll();
}

/**
 * Normalize supported delivery city names from JSON or comma-separated input.
 */
function normalize_supported_city_names($rawValue): array {
    $items = [];

    if (is_array($rawValue)) {
        $items = $rawValue;
    } elseif (is_string($rawValue) && trim($rawValue) !== '') {
        $value = trim($rawValue);
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/\s*,\s*/', $value) ?: [$value];
        }
    }

    $normalized = [];
    foreach ($items as $item) {
        $city = trim((string)$item);
        if ($city === '') {
            continue;
        }

        $city = preg_replace('/\s+/', ' ', $city);
        $city = ucwords(strtolower($city));
        if (!in_array($city, $normalized, true)) {
            $normalized[] = $city;
        }
    }

    return $normalized;
}

/**
 * Load all supported delivery city names from the saved site settings.
 */
function get_supported_city_names(): array {
    $settings = get_site_settings();

    foreach (['supported_cities', 'supported_zip_codes'] as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }

        $cities = normalize_supported_city_names($settings[$key]);
        if ($cities !== []) {
            return $cities;
        }
    }

    return [];
}

/**
 * Check whether a customer-entered city is within the current delivery area.
 */
function is_delivery_city_supported(string $city): bool {
    $lookup = trim($city);
    if ($lookup === '') {
        return false;
    }

    $supported = array_map('strtolower', array_map('trim', get_supported_city_names()));
    return in_array(strtolower($lookup), $supported, true);
}

/**
 * Backward-compatible ZIP validation for older data sets.
 */
function is_delivery_zip_supported(string $zipCode): bool {
    $settings = get_site_settings();
    $rawZips = $settings['supported_zip_codes'] ?? $settings['supported_cities'] ?? '[]';
    $supported = normalize_supported_city_names($rawZips);

    $cleanZip = trim($zipCode);
    foreach ($supported as $z) {
        if (trim((string)$z) === $cleanZip) {
            return true;
        }
    }
    return false;
}

/**
 * Strict Server-Side Cart Pricing & Stock Calculator
 * Never trusts client-supplied prices!
 *
 * $items structure:
 * [
 *   [
 *     'type' => 'single',
 *     'variant_id' => 1,
 *     'quantity' => 2
 *   ],
 *   [
 *     'type' => 'pack',
 *     'bundle_type' => 'pack_3' | 'pack_6' | 'pack_12',
 *     'pack_mode' => 'quick' | 'regular' | 'custom',
 *     'jar_size' => '4 oz' | '8 oz' | '12 oz',
 *     'flavors' => ['huckleberry', 'raspberry', ...],
 *     'quantity' => 1
 *   ],
 *   [
 *     'type' => 'gift_box',
 *     'bundle_type' => 'gift_box_4',
 *     'flavors' => ['huckleberry', 'rhubarb-huckleberry', 'flathead-cherry', 'raspberry'],
 *     'quantity' => 1
 *   ]
 * ]
 */
function calculate_and_validate_cart(array $items, string $fulfillmentType, ?string $zipCode = null, ?string $city = null): array {
    $db = get_db();
    $settings = get_site_settings();

    $validatedItems = [];
    $subtotal = 0.00;
    $errors = [];

    // Cache products & bundles for efficient lookup
    $bundles = [];
    foreach (get_bundle_configs() as $b) {
        $bundles[$b['type']] = $b;
    }

    // Size base prices for packs
    $sizePricing = [
        '4 oz' => 9.00,
        '8 oz' => 14.00,
        '12 oz' => 17.00
    ];

    foreach ($items as $idx => $item) {
        $type = $item['type'] ?? 'single';
        $qty = max(1, (int)($item['quantity'] ?? 1));

        if ($type === 'single') {
            $variantId = (int)($item['variant_id'] ?? 0);
            $stmt = $db->prepare("
                SELECT v.*, p.name AS product_name, p.slug, p.is_active AS product_active
                FROM product_variants v
                JOIN products p ON v.product_id = p.id
                WHERE v.id = ?
            ");
            $stmt->execute([$variantId]);
            $variant = $stmt->fetch();

            if (!$variant || (int)$variant['product_active'] !== 1) {
                $errors[] = "Item at position " . ($idx + 1) . " is no longer available.";
                continue;
            }

            if ($variant['stock_status'] === 'out_of_stock' || (int)$variant['stock_qty'] < $qty) {
                $errors[] = "Insufficient stock for {$variant['product_name']} ({$variant['size']}). Available: {$variant['stock_qty']}.";
                continue;
            }

            $unitPrice = (float)$variant['price'];
            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $validatedItems[] = [
                'type' => 'single',
                'product_id' => (int)$variant['product_id'],
                'variant_id' => (int)$variant['id'],
                'item_title' => "{$variant['product_name']} - {$variant['size']}",
                'variant_details' => json_encode(['size' => $variant['size'], 'sku' => $variant['sku']]),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal
            ];

        } elseif ($type === 'pack') {
            $bType = $item['bundle_type'] ?? 'pack_3';
            if (!isset($bundles[$bType])) {
                $errors[] = "Invalid bundle pack selection.";
                continue;
            }

            $bundle = $bundles[$bType];
            $packCounts = [
                'pack_3' => 3,
                'pack_6' => 6,
                'pack_12' => 12
            ];
            $requiredJars = $packCounts[$bType] ?? 3;
            $jarSize = in_array($item['jar_size'] ?? '', ['4 oz', '8 oz', '12 oz']) ? $item['jar_size'] : '8 oz';
            $flavors = is_array($item['flavors'] ?? null) ? $item['flavors'] : [];

            if (count($flavors) !== $requiredJars) {
                $errors[] = "Pack {$bundle['name']} requires exactly {$requiredJars} selected flavors.";
                continue;
            }

            // Calculate pack unit price (standard volume-discounted rates or fixed bundle prices)
            // 3-pack: 4 oz ($25) or 8 oz ($38) or 12 oz ($47)
            // 6-pack: 4 oz ($48) or 8 oz ($78) or 12 oz ($95)
            // 12-pack: 4 oz ($92) or 8 oz ($150) or 12 oz ($184)
            $pricingTable = [
                'pack_3'  => ['4 oz' => 25.00, '8 oz' => 38.00, '12 oz' => 47.00],
                'pack_6'  => ['4 oz' => 48.00, '8 oz' => 78.00, '12 oz' => 95.00],
                'pack_12' => ['4 oz' => 92.00, '8 oz' => 150.00, '12 oz' => 184.00],
            ];

            $unitPrice = $pricingTable[$bType][$jarSize] ?? (float)$bundle['fixed_price'];
            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $validatedItems[] = [
                'type' => 'pack',
                'product_id' => null,
                'variant_id' => null,
                'item_title' => "{$bundle['name']} ({$jarSize})",
                'variant_details' => json_encode([
                    'bundle_type' => $bType,
                    'jar_size' => $jarSize,
                    'pack_mode' => $item['pack_mode'] ?? 'custom',
                    'flavors' => $flavors
                ]),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal
            ];

        } elseif ($type === 'gift_box') {
            $bundle = $bundles['gift_box_4'] ?? null;
            if (!$bundle) {
                $errors[] = "Gift box configuration is currently unavailable.";
                continue;
            }

            $flavors = is_array($item['flavors'] ?? null) ? $item['flavors'] : [];
            if (count($flavors) !== 4) {
                $errors[] = "A Gift Box must contain exactly 4 jars of 4 oz jam.";
                continue;
            }

            $unitPrice = (float)$bundle['fixed_price']; // $38.00
            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $validatedItems[] = [
                'type' => 'gift_box',
                'product_id' => null,
                'variant_id' => null,
                'item_title' => "Artisanal 4-Jar Gift Box (4 oz)",
                'variant_details' => json_encode([
                    'bundle_type' => 'gift_box_4',
                    'box_number' => $item['box_number'] ?? 1,
                    'flavors' => $flavors
                ]),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal
            ];
        }
    }

    // Fulfillment calculations
    $deliveryFee = 0.00;
    $freeDeliveryApplied = false;
    $deliverySupported = true;

    $standardDeliveryFee = (float)($settings['local_delivery_fee'] ?? 6.50);
    $freeThreshold = (float)($settings['free_delivery_threshold'] ?? 45.00);

    if ($fulfillmentType === 'delivery') {
        $deliveryCity = trim((string)($city ?? ''));
        if (empty($deliveryCity) || !is_delivery_city_supported($deliveryCity)) {
            $deliverySupported = false;
            $errors[] = "Local delivery is not currently available for city {$deliveryCity}.";
        } else {
            if ($subtotal >= $freeThreshold) {
                $deliveryFee = 0.00;
                $freeDeliveryApplied = true;
            } else {
                $deliveryFee = $standardDeliveryFee;
            }
        }
    }

    $taxRate = (float)($settings['sales_tax_rate'] ?? 0.00);
    $tax = round($subtotal * $taxRate, 2);
    $grandTotal = round($subtotal + $deliveryFee + $tax, 2);

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'delivery_supported' => $deliverySupported,
        'calculations' => [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'free_delivery_threshold' => $freeThreshold,
            'free_delivery_applied' => $freeDeliveryApplied,
            'tax' => $tax,
            'total' => $grandTotal
        ],
        'items' => $validatedItems
    ];
}

/**
 * Normalize and validate email addresses before sending.
 */
function normalize_email_address(?string $value): ?string {
    $email = trim((string)($value ?? ''));
    if ($email === '') {
        return null;
    }

    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/**
 * Return the configured SMTP mail settings from environment variables.
 */
function get_smtp_config(): array {
    $host = trim((string)(getenv('SMTP_HOST') ?: getenv('MAIL_HOST') ?: ''));
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $username = trim((string)(getenv('SMTP_USERNAME') ?: getenv('MAIL_USERNAME') ?: ''));
    $password = trim((string)(getenv('SMTP_PASSWORD') ?: getenv('MAIL_PASSWORD') ?: ''));
    $encryption = strtolower(trim((string)(getenv('SMTP_ENCRYPTION') ?: getenv('MAIL_ENCRYPTION') ?: 'tls')));
    $fromName = trim((string)(getenv('SMTP_FROM_NAME') ?: getenv('MAIL_FROM_NAME') ?: "Shelly's Jellys"));
    $fromEmail = normalize_email_address((string)(getenv('SMTP_FROM_EMAIL') ?: getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost'));

    if ($encryption === 'none' || $encryption === 'false') {
        $encryption = '';
    }

    return [
        'host' => $host,
        'port' => $port,
        'username' => $username,
        'password' => $password,
        'encryption' => $encryption,
        'from_name' => $fromName !== '' ? $fromName : "Shelly's Jellys",
        'from_email' => $fromEmail ?? 'noreply@localhost',
    ];
}

/**
 * Log email delivery failures without exposing secrets or stack traces to customers.
 */
function log_email_error(string $context, string $message, ?Throwable $exception = null): void {
    $logDir = dirname(__DIR__) . '/data/email_logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $detail = $message;
    if ($exception instanceof Throwable) {
        $detail .= ' | ' . $exception->getMessage();
    }

    $entry = sprintf("[%s] %s | %s\n", date('Y-m-d H:i:s'), $context, $detail);
    @file_put_contents($logDir . '/email-errors.log', $entry, FILE_APPEND | LOCK_EX);
    error_log($context . ': ' . $detail);
}

/**
 * Build a PHPMailer instance from env-backed SMTP configuration.
 */
function create_smtp_mailer(): ?PHPMailer {
    $config = get_smtp_config();
    if ($config['host'] === '') {
        log_email_error('SMTP configuration missing', 'SMTP_HOST is not set in the environment.');
        return null;
    }

    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $config['host'];
    $mailer->Port = $config['port'];
    $mailer->SMTPAuth = $config['username'] !== '' && $config['password'] !== '';
    $mailer->Username = $config['username'];
    $mailer->Password = $config['password'];

    if ($config['encryption'] === 'tls') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($config['encryption'] === 'ssl') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($config['encryption'] !== '') {
        $mailer->SMTPSecure = $config['encryption'];
    }

    $mailer->SMTPAutoTLS = true;
    $mailer->Timeout = 20;
    $mailer->CharSet = 'UTF-8';
    $mailer->Encoding = 'base64';
    $mailer->setFrom($config['from_email'], $config['from_name']);
    return $mailer;
}

/**
 * Send a single transactional email through PHPMailer.
 */
function send_mail_message(
    array $recipients,
    string $subject,
    string $htmlBody,
    string $plainText = '',
    ?string $replyToEmail = null,
    ?string $replyToName = null
): bool {
    $cleanRecipients = [];
    foreach ($recipients as $recipient) {
        $email = normalize_email_address((string)$recipient);
        if ($email !== null) {
            $cleanRecipients[] = $email;
        }
    }

    if ($cleanRecipients === []) {
        log_email_error('Mailer recipients', 'No valid recipient addresses were provided for email delivery.');
        return false;
    }

    $mailer = create_smtp_mailer();
    if ($mailer === null) {
        return false;
    }

    try {
        foreach (array_values(array_unique($cleanRecipients)) as $recipient) {
            $mailer->addAddress($recipient);
        }

        if ($replyToEmail !== null) {
            $replyEmail = normalize_email_address($replyToEmail);
            if ($replyEmail !== null) {
                $mailer->addReplyTo($replyEmail, $replyToName ?: $replyEmail);
            }
        }

        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $htmlBody;
        $mailer->AltBody = $plainText !== '' ? $plainText : strip_tags($htmlBody);

        $mailer->send();
        return true;
    } catch (Exception $e) {
        log_email_error('PHPMailer send failed', $subject, $e);
        return false;
    }
}

/**
 * Get a deduplicated list of admin and kitchen recipients from the DB settings.
 */
function get_contact_recipient_emails(): array {
    $settings = get_site_settings();
    $primary = normalize_email_address((string)($settings['admin_email_primary'] ?? ''));
    $secondary = normalize_email_address((string)($settings['admin_email_secondary'] ?? ''));
    $valid = [];

    foreach ([$primary, $secondary] as $email) {
        if ($email !== null) {
            $valid[] = $email;
        }
    }

    if ($valid === []) {
        log_email_error('Recipient validation', 'Primary Administrator Email / Secondary Kitchen Notification Email are missing or invalid.');
    }

    return array_values(array_unique($valid));
}

/**
 * Quick duplicate-submission guard for public forms.
 */
function is_duplicate_submission(string $key, int $ttlSeconds = 1800): bool {
    $dir = dirname(__DIR__) . '/data/email_logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $file = $dir . '/form_dedupe.json';
    $entries = [];
    if (file_exists($file)) {
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (is_array($decoded)) {
            $entries = $decoded;
        }
    }

    $now = time();
    foreach ($entries as $fingerprint => $timestamp) {
        if (is_numeric($timestamp) && ($now - (int)$timestamp) > $ttlSeconds) {
            unset($entries[$fingerprint]);
        }
    }

    if (isset($entries[$key])) {
        return true;
    }

    $entries[$key] = $now;
    @file_put_contents($file, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return false;
}

/**
 * Legacy admin notification helper used by existing modules.
 */
function send_admin_notification(string $subject, string $messageHtml, string $replyToEmail = ''): bool {
    $settings = get_site_settings();
    $primary = normalize_email_address((string)($settings['admin_email_primary'] ?? ''));
    $secondary = normalize_email_address((string)($settings['admin_email_secondary'] ?? ''));
    $recipients = array_values(array_filter([$primary, $secondary], fn($value) => $value !== null));

    if ($recipients === []) {
        log_email_error('Admin recipient validation', 'No valid admin notification recipients configured.');
        return false;
    }

    return send_mail_message($recipients, $subject, $messageHtml, strip_tags($messageHtml), $replyToEmail);
}

/**
 * Send a customer confirmation email plus admin notification emails for a form inquiry.
 */
function send_form_submission_email(array $payload, string $inquiryType = 'general'): bool {
    $customerEmail = normalize_email_address((string)($payload['customer_email'] ?? $payload['email'] ?? ''));
    $customerName = trim((string)($payload['customer_name'] ?? $payload['name'] ?? 'Customer'));
    $settings = get_site_settings();
    $primary = normalize_email_address((string)($settings['admin_email_primary'] ?? ''));
    $secondary = normalize_email_address((string)($settings['admin_email_secondary'] ?? ''));
    $adminRecipients = array_values(array_filter([$primary, $secondary], fn($value) => $value !== null));

    if ($customerEmail === null) {
        log_email_error('Form submission validation', 'Customer email is missing or invalid.');
        return false;
    }

    $customerSubject = $inquiryType === 'custom_event'
        ? 'Thank you for your custom order request'
        : 'Thank you for contacting Shelly\'s Jellys';

    $customerHtml = '<h2>Thank you for reaching out</h2>'
        . '<p>Hi ' . htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
        . '<p>We have received your message and will be in touch soon.</p>'
        . '<p><strong>Submitted details:</strong></p>'
        . '<ul>'
        . '<li><strong>Name:</strong> ' . htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
        . '<li><strong>Email:</strong> ' . htmlspecialchars($customerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
        . (!empty($payload['phone']) ? '<li><strong>Phone:</strong> ' . htmlspecialchars((string)$payload['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>' : '')
        . (!empty($payload['estimated_jars']) ? '<li><strong>Estimated quantity:</strong> ' . htmlspecialchars((string)$payload['estimated_jars'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>' : '')
        . (!empty($payload['event_date']) ? '<li><strong>Event date:</strong> ' . htmlspecialchars((string)$payload['event_date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>' : '')
        . '</ul>'
        . '<p><strong>Message:</strong></p>'
        . '<p>' . nl2br(htmlspecialchars((string)($payload['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>'
        . '<p>Warmly,<br>' . htmlspecialchars((string)($settings['brand_name'] ?? "Shelly's Jellys LLC"), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';

    $adminSubject = $inquiryType === 'custom_event'
        ? 'New custom order request from ' . $customerName
        : 'New contact form message from ' . $customerName;

    $adminHtml = '<h2>New inquiry</h2>'
        . '<p><strong>Customer name:</strong> ' . htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p><strong>Email:</strong> ' . htmlspecialchars($customerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . (!empty($payload['phone']) ? '<p><strong>Phone:</strong> ' . htmlspecialchars((string)$payload['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' : '')
        . (!empty($payload['address']) ? '<p><strong>Address:</strong> ' . htmlspecialchars((string)$payload['address'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' : '')
        . (!empty($payload['estimated_jars']) ? '<p><strong>Estimated quantity:</strong> ' . htmlspecialchars((string)$payload['estimated_jars'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' : '')
        . (!empty($payload['event_date']) ? '<p><strong>Event date:</strong> ' . htmlspecialchars((string)$payload['event_date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' : '')
        . '<p><strong>Message:</strong></p>'
        . '<p>' . nl2br(htmlspecialchars((string)($payload['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';

    $customerSent = send_mail_message([$customerEmail], $customerSubject, $customerHtml, strip_tags($customerHtml), $primary ?? $customerEmail, $settings['brand_name'] ?? "Shelly's Jellys LLC");
    $adminSent = send_mail_message($adminRecipients, $adminSubject, $adminHtml, strip_tags($adminHtml), $customerEmail, $customerName);
    return $customerSent && $adminSent;
}

/**
 * Generate order email content for successful sales.
 */
function send_order_notification_email(array $order, array $customer, array $items): bool {
    $settings = get_site_settings();
    $primary = normalize_email_address((string)($settings['admin_email_primary'] ?? ''));
    $secondary = normalize_email_address((string)($settings['admin_email_secondary'] ?? ''));
    $customerEmail = normalize_email_address((string)($customer['email'] ?? ''));

    if ($customerEmail === null) {
        log_email_error('Order email validation', 'Customer email is missing or invalid for order notification.');
        return false;
    }

    $brandName = (string)($settings['brand_name'] ?? "Shelly's Jellys LLC");
    $phone = trim((string)($settings['pickup_phone'] ?? '(406) 555-0192'));
    $address = trim((string)($settings['pickup_address'] ?? '458 Orchard Vista Way, Kalispell, MT 59901'));
    $emailAddress = trim((string)($settings['pickup_email'] ?? 'mtshellysjellys@gmail.com'));

    $orderNumber = (string)($order['order_number'] ?? 'UNKNOWN');
    $customerName = trim((string)($customer['full_name'] ?? 'Customer'));
    $subtotal = number_format((float)($order['subtotal'] ?? 0.0), 2);
    $deliveryFee = number_format((float)($order['delivery_fee'] ?? 0.0), 2);
    $total = number_format((float)($order['total_amount'] ?? 0.0), 2);
    $lineRows = '';
    foreach ($items as $item) {
        $lineRows .= '<tr>'
            . '<td style="padding:8px; border-bottom:1px solid #e5e7eb;">' . htmlspecialchars((string)($item['item_title'] ?? 'Item'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>'
            . '<td style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:center;">' . (int)($item['quantity'] ?? 1) . '</td>'
            . '<td style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:right;">$' . number_format((float)($item['unit_price'] ?? 0.0), 2) . '</td>'
            . '<td style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:right;">$' . number_format((float)($item['line_total'] ?? 0.0), 2) . '</td>'
            . '</tr>';
    }

    $customerText = "Hi {$customerName},\n\nThank you for purchasing our homemade jam! Please take time to look over the details of your order. Your jam will be ready in the next 2 to 3 days. One of us will contact you with information regarding your order if necessary. Don’t hesitate to get in touch with us if you have any questions.\n\nOrder Summary:\n";
    foreach ($items as $item) {
        $customerText .= '- ' . (string)($item['item_title'] ?? 'Item') . ' x' . (int)($item['quantity'] ?? 1) . ' @ $' . number_format((float)($item['unit_price'] ?? 0.0), 2) . "\n";
    }
    $customerText .= "\nSubtotal: ${$subtotal}\nDelivery: ${$deliveryFee}\nTotal: {$total}\n\nContact Us\n{$brandName}\nPhone: {$phone}\nAddress: {$address}\nEmail: {$emailAddress}\n";

    $customerHtml = '<div style="font-family:Arial,sans-serif; line-height:1.6; color:#1f2937;">'
        . '<p>Hi ' . htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
        . '<p>Thank you for purchasing our homemade jam! Please take time to look over the details of your order. Your jam will be ready in the next 2 to 3 days. One of us will contact you with information regarding your order if necessary. Don’t hesitate to get in touch with us if you have any questions.</p>'
        . '<h3>Order Summary</h3>'
        . '<table style="width:100%; border-collapse:collapse;">'
        . '<thead><tr style="background:#f3f4f6;"><th style="padding:8px; text-align:left;">Item</th><th style="padding:8px; text-align:center;">Qty</th><th style="padding:8px; text-align:right;">Price</th><th style="padding:8px; text-align:right;">Total</th></tr></thead>'
        . '<tbody>' . $lineRows . '</tbody>'
        . '<tfoot><tr><td colspan="3" style="padding:8px; text-align:right;"><strong>Subtotal:</strong></td><td style="padding:8px; text-align:right;">$' . $subtotal . '</td></tr>'
        . '<tr><td colspan="3" style="padding:8px; text-align:right;"><strong>Delivery:</strong></td><td style="padding:8px; text-align:right;">$' . $deliveryFee . '</td></tr>'
        . '<tr><td colspan="3" style="padding:8px; text-align:right;"><strong>Total:</strong></td><td style="padding:8px; text-align:right;"><strong>$' . $total . '</strong></td></tr></tfoot>'
        . '</table>'
        . '<p><strong>Contact info:</strong><br>' . htmlspecialchars($brandName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>' . htmlspecialchars($phone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>' . htmlspecialchars($address, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>' . htmlspecialchars($emailAddress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '</div>';

    $adminHtml = '<div style="font-family:Arial,sans-serif; line-height:1.6; color:#1f2937;">'
        . '<h2>You have a new order! Order ' . htmlspecialchars($orderNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h2>'
        . '<p><strong>Customer:</strong> ' . htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p><strong>Email:</strong> ' . htmlspecialchars($customerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p><strong>Phone:</strong> ' . htmlspecialchars((string)($customer['phone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p><strong>Delivery / Pickup:</strong> ' . htmlspecialchars((string)($order['fulfillment_type'] ?? 'pickup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p><strong>Order date:</strong> ' . htmlspecialchars((string)($order['created_at'] ?? date('Y-m-d H:i:s')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p><strong>Special instructions:</strong> ' . nl2br(htmlspecialchars((string)($order['special_instructions'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>'
        . '<table style="width:100%; border-collapse:collapse;">'
        . '<thead><tr style="background:#f3f4f6;"><th style="padding:8px; text-align:left;">Item</th><th style="padding:8px; text-align:center;">Qty</th><th style="padding:8px; text-align:right;">Price</th><th style="padding:8px; text-align:right;">Total</th></tr></thead>'
        . '<tbody>' . $lineRows . '</tbody>'
        . '<tfoot><tr><td colspan="3" style="padding:8px; text-align:right;"><strong>Subtotal:</strong></td><td style="padding:8px; text-align:right;">$' . $subtotal . '</td></tr>'
        . '<tr><td colspan="3" style="padding:8px; text-align:right;"><strong>Delivery:</strong></td><td style="padding:8px; text-align:right;">$' . $deliveryFee . '</td></tr>'
        . '<tr><td colspan="3" style="padding:8px; text-align:right;"><strong>Total:</strong></td><td style="padding:8px; text-align:right;"><strong>$' . $total . '</strong></td></tr></tfoot>'
        . '</table>'
        . '<p><strong>Fulfillment details:</strong></p>'
        . '<p>' . nl2br(htmlspecialchars((string)($order['fulfillment_summary'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>'
        . '</div>';

    $adminRecipients = array_values(array_filter([$primary, $secondary], fn($value) => $value !== null));
    $customerSent = send_mail_message([$customerEmail], 'Purchase details from Shelly’s Jellys for order ' . $orderNumber, $customerHtml, $customerText, $primary ?? $customerEmail, $brandName);
    $adminSent = send_mail_message($adminRecipients, 'You have a new order! Order ' . $orderNumber, $adminHtml, strip_tags($adminHtml), $customerEmail, $customerName);

    return $customerSent && $adminSent;
}

/**
 * Get verified customer reviews
 */
function get_customer_reviews(): array {
    return [
        [
            'name' => 'Patty',
            'stars' => 4,
            'rating_text' => '4 of 5 stars',
            'quote' => 'You need to try the pepper jellies... oh wait!',
            'verified' => true,
            'source' => 'Verified Farmstand Buyer'
        ],
        [
            'name' => 'Ian Alan',
            'stars' => 5,
            'rating_text' => '5 of 5 stars',
            'quote' => 'Huckleberry on scrambled eggs and toast too.',
            'verified' => true,
            'source' => 'Verified Local Customer'
        ],
        [
            'name' => 'Amanda E.',
            'stars' => 5,
            'rating_text' => '5 of 5 stars',
            'quote' => 'Five stars from me all day long!',
            'verified' => true,
            'source' => 'Verified Flathead Delivery'
        ],
        [
            'name' => 'Marcus B.',
            'stars' => 5,
            'rating_text' => '5 of 5 stars',
            'quote' => 'Best artisan preserves we have ever ordered for breakfast or gifting.',
            'verified' => true,
            'source' => 'Verified Buyer'
        ],
        [
            'name' => 'Sarah T.',
            'stars' => 5,
            'rating_text' => '5 of 5 stars',
            'quote' => 'The Flathead Cherry and Huckleberry combo is utterly addictive.',
            'verified' => true,
            'source' => 'Verified Gift Box Recipient'
        ]
    ];
}

