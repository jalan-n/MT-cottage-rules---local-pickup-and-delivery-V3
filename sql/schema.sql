-- ============================================================================
-- BOUTIQUE GOURMET JAM E-COMMERCE DATABASE SCHEMA
-- Target Database: MySQL 8.x
-- Storage Engine: InnoDB
-- Character Set: utf8mb4 (utf8mb4_unicode_ci)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `bundle_configs`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `admin_users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. Table: products
-- Catalog of artisanal gourmet jam offerings
-- ----------------------------------------------------------------------------
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` TEXT NOT NULL,
  `category` ENUM('regular', 'pack', 'gift_box') NOT NULL DEFAULT 'regular',
  `image_url` VARCHAR(255) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_slug` (`slug`),
  KEY `idx_products_category_active` (`category`, `is_active`),
  KEY `idx_products_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: product_variants
-- Jar sizes and standardized pricing (4 oz: $9, 8 oz: $14, 12 oz: $17)
-- ----------------------------------------------------------------------------
CREATE TABLE `product_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `size` ENUM('4 oz', '8 oz', '12 oz') NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `sku` VARCHAR(100) NOT NULL,
  `stock_status` ENUM('in_stock', 'out_of_stock') NOT NULL DEFAULT 'in_stock',
  `stock_qty` INT NOT NULL DEFAULT 50,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_variants_sku` (`sku`),
  UNIQUE KEY `uk_product_size` (`product_id`, `size`),
  KEY `idx_variants_stock` (`stock_status`, `stock_qty`),
  CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: bundle_configs
-- Configurations for 3-Pack, 6-Pack, 12-Pack, and 4-Jar Gift Box
-- ----------------------------------------------------------------------------
CREATE TABLE `bundle_configs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('pack_3', 'pack_6', 'pack_12', 'gift_box_4') NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `base_size` VARCHAR(50) NOT NULL DEFAULT '4 oz',
  `fixed_price` DECIMAL(10,2) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_bundles_type_active` (`type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: customers
-- Customer records indexed by email address
-- ----------------------------------------------------------------------------
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `street_address` VARCHAR(255) NOT NULL,
  `unit` VARCHAR(50) NULL DEFAULT '',
  `city` VARCHAR(100) NOT NULL,
  `state` VARCHAR(2) NOT NULL,
  `zip_code` VARCHAR(10) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_email` (`email`),
  KEY `idx_customers_zip` (`zip_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table: orders
-- Orders for local pickup or local delivery with Square transaction references
-- ----------------------------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(64) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `fulfillment_type` ENUM('pickup', 'delivery') NOT NULL,
  `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'square_card',
  `payment_status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
  `square_order_id` VARCHAR(191) NULL DEFAULT '',
  `square_payment_id` VARCHAR(191) NULL DEFAULT '',
  `fulfillment_date` DATE NOT NULL,
  `fulfillment_time_slot` VARCHAR(100) NOT NULL,
  `special_instructions` TEXT NULL,
  `order_status` ENUM('new', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'new',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_order_number` (`order_number`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_status_date` (`order_status`, `fulfillment_date`),
  KEY `idx_orders_created` (`created_at`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) 
    REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Table: order_items
-- Purchased lines for single jars, packs, and custom gift boxes
-- ----------------------------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `item_type` ENUM('single', 'pack', 'gift_box') NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `variant_id` INT UNSIGNED NULL,
  `item_title` VARCHAR(191) NOT NULL,
  `variant_details` TEXT NULL COMMENT 'JSON array of selected flavors and jar sizes',
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  KEY `idx_order_items_variant` (`variant_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) 
    REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`) 
    REFERENCES `product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Table: site_settings
-- System configuration, delivery zones, thresholds, and administrative emails
-- ----------------------------------------------------------------------------
CREATE TABLE `site_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Table: admin_users
-- Administrative access for content management, stock, and rebuild triggers
-- ----------------------------------------------------------------------------
CREATE TABLE `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `last_login` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_username` (`username`),
  KEY `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
