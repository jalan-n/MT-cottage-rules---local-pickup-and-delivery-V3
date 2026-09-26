<?php
declare(strict_types=1);

/**
 * ============================================================================
 * BOUTIQUE GOURMET JAM STATIC SITE GENERATOR (SSG)
 * ============================================================================
 * Standalone CLI/Web compilation script.
 * Connects to MySQL/PDO, queries database schema, and compiles complete,
 * pre-rendered, SEO-optimized flat HTML files in /public/.
 * Adheres strictly to SEO best practices:
 *  - Dynamic <title> capped at 60 characters
 *  - <meta name="description"> capped at 160 characters
 *  - Self-referencing <link rel="canonical" href="...">
 *  - Strict header hierarchy (single <h1>, sequential <h2>, <h3>)
 *  - JSON-LD Structured Data (Product, Offer, LocalBusiness, BreadcrumbList)
 *  - OpenGraph and Twitter card meta tags
 *  - All <img> tags have populated alt attributes
 *  - Valid sitemap.xml and robots.txt
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

class StaticSiteGenerator {
    private PDO $db;
    private array $settings;
    private array $products;
    private array $bundles;
    private string $publicDir;
    private array $generatedFiles = [];
    private array $sitemapUrls = [];

    public function __construct() {
        $this->db = get_db();
        $this->settings = get_site_settings();
        $this->products = get_all_products(true);
        $this->bundles = get_bundle_configs(true);
        $this->publicDir = __DIR__ . '/public';

        if (!is_dir($this->publicDir)) {
            mkdir($this->publicDir, 0755, true);
        }
    }

    /**
     * Helper to safely cap strings for SEO title (60 chars) and meta description (160 chars)
     */
    private function capString(string $text, int $max): string {
        $trimmed = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        $len = function_exists('mb_strlen') ? mb_strlen($trimmed, 'UTF-8') : strlen($trimmed);
        if ($len <= $max) {
            return $trimmed;
        }
        return function_exists('mb_substr') 
            ? mb_substr($trimmed, 0, $max - 3, 'UTF-8') . '...'
            : substr($trimmed, 0, $max - 3) . '...';
    }

    private function safeSlug(string $value): string {
        return canonical_product_slug($value);
    }

    /**
     * Render page content using header, body template, and footer
     */
    private function renderPage(string $templateName, array $params = [], string $filename = ''): string {
        // Enforce 60 char title & 160 char description cap
        $cleanTitle = $this->capString($params['title'] ?? "Shelly's Jellys LLC", 60);
        $cleanDesc  = $this->capString($params['description'] ?? "Handcrafted artisanal gourmet jams.", 160);

        $params['title'] = $cleanTitle;
        $params['description'] = $cleanDesc;

        $data = array_merge([
            'settings' => $this->settings,
            'products' => $this->products,
            'bundles'  => $this->bundles,
            'extraScripts' => []
        ], $params);

        extract($data);

        ob_start();
        require __DIR__ . '/includes/templates/header.php';
        require __DIR__ . "/includes/templates/{$templateName}.php";
        require __DIR__ . '/includes/templates/footer.php';
        return ob_get_clean();
    }

    /**
     * Write rendered content to target destination in /public
     */
    private function writeHtml(string $filename, string $html, float $priority = 0.8, string $freq = 'weekly'): void {
        $target = $this->publicDir . '/' . ltrim($filename, '/');
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($target, $html);
        $this->generatedFiles[] = [
            'file' => $filename,
            'bytes' => strlen($html),
            'generated_at' => date('Y-m-d H:i:s')
        ];
        $pagePath = './' . ltrim($filename, './');
        $this->sitemapUrls[] = [
            'loc' => $pagePath,
            'priority' => $priority,
            'changefreq' => $freq,
            'lastmod' => date('Y-m-d')
        ];
    }

    /**
     * Generate dynamic sitemap.xml reflecting clean URLs
     */
    private function generateSitemap(): void {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($this->sitemapUrls as $entry) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($entry['loc']) . "</loc>\n";
            $xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            $xml .= "    <priority>" . number_format($entry['priority'], 1) . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";
        file_put_contents($this->publicDir . '/sitemap.xml', $xml);
        $this->generatedFiles[] = [
            'file' => 'sitemap.xml',
            'bytes' => strlen($xml),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate robots.txt
     */
    private function generateRobots(): void {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /api/\n";
        $content .= "Sitemap: /sitemap.xml\n";

        file_put_contents($this->publicDir . '/robots.txt', $content);
        $this->generatedFiles[] = [
            'file' => 'robots.txt',
            'bytes' => strlen($content),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Execute full SSG build process
     */
    public function build(): array {
        $brandName = $this->settings['brand_name'] ?? "Shelly's Jellys LLC";

        // 1. Home Page (index.html)
        $homeHtml = $this->renderPage('home', [
            'title' => "{$brandName} | Handcrafted Montana Jams",
            'description' => 'Small-batch gourmet artisanal jams made from wild mountain berries and orchard fruit. Free local pickup & delivery across the Flathead Valley.',
            'canonical' => './index.html'
        ]);
        $this->writeHtml('index.html', $homeHtml, 1.0, 'daily');

        // 2. Shop Catalog (shop.html)
        $shopHtml = $this->renderPage('shop', [
            'title' => "Shop 6 Handcrafted Jam Flavors | {$brandName}",
            'description' => 'Browse our 6 homemade small-batch gourmet fruit preserves in 4 oz, 8 oz, and 12 oz jars.',
            'canonical' => './shop.html'
        ]);
        $this->writeHtml('shop.html', $shopHtml, 0.9, 'weekly');

        // 3. Individual Product Pages (product-{slug}.html)
        foreach ($this->products as $product) {
            $slug = $this->safeSlug((string)($product['slug'] ?? ''));
            $prodTitle = "{$product['name']} Jam | {$brandName}";
            $prodHtml = $this->renderPage('product', [
                'title' => $prodTitle,
                'description' => $product['description'],
                'canonical' => "./product-{$slug}.html",
                'product' => $product
            ]);
            $this->writeHtml("product-{$slug}.html", $prodHtml, 0.8, 'weekly');
        }

        // 4. Build-a-Pack (packs.html)
        $packsHtml = $this->renderPage('packs', [
            'title' => "Build a Pack (3, 6, 12 Jars) | {$brandName}",
            'description' => 'Select 3, 6, or 12 jars. Mix and match flavors in 4 oz, 8 oz, or 12 oz with automatic volume discounts.',
            'canonical' => './packs.html',
            'extraScripts' => ['/js/pack-builder.js']
        ]);
        $this->writeHtml('packs.html', $packsHtml, 0.8, 'weekly');

        // 5. Build a Gift Box (gift-box.html)
        $giftHtml = $this->renderPage('gift-box', [
            'title' => "Build a Gift Box (4 Jars) | {$brandName}",
            'description' => 'Choose up to four 4 oz jars to send as a gift! Mix handcrafted flavors in our custom gift box.',
            'canonical' => './gift-box.html',
            'extraScripts' => ['/js/gift-box-builder.js']
        ]);
        $this->writeHtml('gift-box.html', $giftHtml, 0.8, 'weekly');

        // 6. Custom Orders & Special Events (custom-orders.html)
        $customHtml = $this->renderPage('custom-orders', [
            'title' => "Custom Orders & Special Events | {$brandName}",
            'description' => 'Tiered volume pricing for wedding favors, client appreciation, and special events. Inquire today.',
            'canonical' => './custom-orders.html'
        ]);
        $this->writeHtml('custom-orders.html', $customHtml, 0.7, 'monthly');

        // 7. Contact Us (contact.html)
        $contactHtml = $this->renderPage('contact', [
            'title' => "Contact Us & Farmstand Pickup | {$brandName}",
            'description' => 'Kalispell farmstand pickup address, kitchen operating hours, and Flathead Valley doorstep delivery inquiry.',
            'canonical' => './contact.html'
        ]);
        $this->writeHtml('contact.html', $contactHtml, 0.7, 'monthly');

        // 8. Shopping Basket / Cart Page (cart.html)
        $cartHtml = $this->renderPage('cart-page', [
            'title' => "Your Shopping Basket | {$brandName}",
            'description' => 'Review your handcrafted jam selections, select local pickup or delivery, and generate shareable basket links.',
            'canonical' => './cart.html'
        ]);
        $this->writeHtml('cart.html', $cartHtml, 0.5, 'monthly');

        // 9. Checkout Page (checkout.html)
        $checkoutHtml = $this->renderPage('checkout-page', [
            'title' => "Secure Checkout & Local Pickup | {$brandName}",
            'description' => 'Fast, secure checkout for local delivery or farmstand pickup with Square Payments.',
            'canonical' => './checkout.html',
            'extraScripts' => ['/js/checkout.js']
        ]);
        $this->writeHtml('checkout.html', $checkoutHtml, 0.5, 'monthly');

        // 10. Generate sitemap.xml and robots.txt
        $this->generateSitemap();
        $this->generateRobots();

        // 11. Write Build Manifest
        $manifest = [
            'generated_at' => date('c'),
            'total_pages' => count($this->generatedFiles),
            'files' => $this->generatedFiles
        ];
        file_put_contents($this->publicDir . '/build-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        return $manifest;
    }
}

// CLI & Web Execution Invocation
$generator = new StaticSiteGenerator();
$result = $generator->build();

if (php_sapi_name() === 'cli') {
    echo "\n=== SSG Compilation Complete ===\n";
    echo "Total Generated Files: " . $result['total_pages'] . "\n";
    foreach ($result['files'] as $f) {
        printf(" - /public/%-35s (%d bytes)\n", $f['file'], $f['bytes']);
    }
    echo "Manifest written to /public/build-manifest.json\n\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'manifest' => $result]);
}
