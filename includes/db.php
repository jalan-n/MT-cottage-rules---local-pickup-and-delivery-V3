<?php
declare(strict_types=1);

/**
 * Database Connection & Initialization Layer
 * Uses PDO with MySQL 8.x (InnoDB, utf8mb4) by default,
 * with turnkey auto-fallback for local development/testing.
 */

class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // Load .env variables if present
        self::loadEnv();

        $driver = getenv('DB_DRIVER') ?: 'mysql';
        $host   = getenv('DB_HOST') ?: '127.0.0.1';
        $port   = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'jam_ecommerce';
        $user   = getenv('DB_USER') ?: 'root';
        $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

        // Attempt MySQL connection first
        if ($driver === 'mysql') {
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 2,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];

                self::$pdo = new PDO($dsn, $user, $pass, $options);
                self::ensureDefaultAdminUser();
                return self::$pdo;
            } catch (PDOException $e) {
                // If MySQL is not running or credentials not active yet, fallback to local SQLite for seamless testing
                $sqlitePath = dirname(__DIR__) . '/data/jam_ecommerce.sqlite';
                if (!is_dir(dirname($sqlitePath))) {
                    mkdir(dirname($sqlitePath), 0755, true);
                }

                $dsn = "sqlite:{$sqlitePath}";
                self::$pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);

                // Auto-initialize tables in SQLite fallback
                self::initSqliteFallback(self::$pdo);
                self::ensureDefaultAdminUser();
                return self::$pdo;
            }
        }

        throw new RuntimeException("Unsupported database driver: {$driver}");
    }

    private static function loadEnv(): void {
        $envPath = dirname(__DIR__) . '/.env';
        if (!file_exists($envPath)) {
            $envPath = dirname(__DIR__) . '/.env.example';
        }

        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val, " \t\n\r\0\x0B\"'");
                    if (getenv($key) === false) {
                        putenv("{$key}={$val}");
                        $_ENV[$key] = $val;
                    }
                }
            }
        }
    }

    private static function ensureDefaultAdminUser(): void {
        try {
            $tableCheck = self::$pdo->query("SELECT 1 FROM admin_users LIMIT 1");
            $tableCheck->fetch();
        } catch (Throwable $e) {
            return;
        }

        $count = (int)self::$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $hash = password_hash('JamAdmin2026!', PASSWORD_BCRYPT);
        self::$pdo->prepare(
            "INSERT INTO admin_users (username, password_hash, email, last_login) VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
        )->execute(['admin', $hash, 'admin@wildandorchardjam.com']);
    }

    private static function initSqliteFallback(PDO $pdo): void {
        // Check if tables already exist
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'")->fetch();
        if ($check) {
            return;
        }

        // Create tables matching schema
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT 'regular',
                image_url TEXT NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                display_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS product_variants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                size TEXT NOT NULL,
                price REAL NOT NULL,
                sku TEXT NOT NULL UNIQUE,
                stock_status TEXT NOT NULL DEFAULT 'in_stock',
                stock_qty INTEGER NOT NULL DEFAULT 50,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS bundle_configs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                name TEXT NOT NULL,
                base_size TEXT NOT NULL DEFAULT '4 oz',
                fixed_price REAL NOT NULL,
                description TEXT,
                is_active INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL,
                street_address TEXT NOT NULL,
                unit TEXT DEFAULT '',
                city TEXT NOT NULL,
                state TEXT NOT NULL,
                zip_code TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_number TEXT NOT NULL UNIQUE,
                customer_id INTEGER NOT NULL,
                fulfillment_type TEXT NOT NULL,
                delivery_fee REAL NOT NULL DEFAULT 0.00,
                subtotal REAL NOT NULL,
                total_amount REAL NOT NULL,
                payment_method TEXT NOT NULL DEFAULT 'square_card',
                payment_status TEXT NOT NULL DEFAULT 'pending',
                square_order_id TEXT DEFAULT '',
                square_payment_id TEXT DEFAULT '',
                fulfillment_date TEXT NOT NULL,
                fulfillment_time_slot TEXT NOT NULL,
                special_instructions TEXT,
                order_status TEXT NOT NULL DEFAULT 'new',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id)
            );

            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                item_type TEXT NOT NULL,
                product_id INTEGER,
                variant_id INTEGER,
                item_title TEXT NOT NULL,
                variant_details TEXT,
                quantity INTEGER NOT NULL DEFAULT 1,
                unit_price REAL NOT NULL,
                line_total REAL NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS site_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                email TEXT NOT NULL,
                last_login TEXT
            );
        ");

        // Seed initial data if products is empty
        $prodCount = (int)$pdo->query("SELECT count(*) FROM products")->fetchColumn();
        if ($prodCount === 0) {
            $seedPath = dirname(__DIR__) . '/sql/seed.sql';
            if (file_exists($seedPath)) {
                $sql = file_get_contents($seedPath);
                $lines = explode("\n", $sql);
                $clean = '';
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (!str_starts_with($trimmed, '--')) {
                        $clean .= $line . "\n";
                    }
                }
                $clean = str_replace(['`', 'NOW()'], ['', 'datetime("now")'], $clean);
                $statements = array_filter(array_map('trim', explode(';', $clean)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        try {
                            $pdo->exec($stmt);
                        } catch (Exception $e) {
                            // Ignore duplicates
                        }
                    }
                }
            }
        }
    }
}
