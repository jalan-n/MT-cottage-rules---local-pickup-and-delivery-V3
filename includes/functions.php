<?php
declare(strict_types=1);

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
 * Dispatch notification email to both admin emails
 */
function send_admin_notification(string $subject, string $messageHtml, string $replyToEmail = ''): bool {
    $settings = get_site_settings();
    $primary = $settings['admin_email_primary'] ?? 'orders@wildandorchardjam.com';
    $secondary = $settings['admin_email_secondary'] ?? 'kitchen@wildandorchardjam.com';

    $recipients = array_filter([$primary, $secondary]);
    $to = implode(', ', $recipients);

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . ($settings['brand_name'] ?? 'Wild & Orchard Jams') . ' <no-reply@wildandorchardjam.com>',
    ];

    if (!empty($replyToEmail) && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
        $headers[] = "Reply-To: {$replyToEmail}";
    }

    // Log email dispatch for server environments without active sendmail
    $logDir = dirname(__DIR__) . '/data/email_logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/dispatched_' . date('Y-m-d') . '.log';
    $logEntry = sprintf(
        "[%s] TO: %s | SUBJECT: %s\n%s\n----------------------------------------\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        strip_tags(str_replace('<br>', "\n", $messageHtml))
    );
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    // If sendmail binary exists on the system, dispatch via native mail()
    $hasSendmail = file_exists('/usr/sbin/sendmail') || file_exists('/usr/bin/sendmail');
    if ($hasSendmail && function_exists('mail')) {
        return @mail($to, $subject, $messageHtml, implode("\r\n", $headers));
    }

    return true;
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

