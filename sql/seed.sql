-- ============================================================================
-- BOUTIQUE GOURMET JAM E-COMMERCE SEED DATA
-- Default catalog, variants, bundle configurations, and site settings
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Insert Products (6 Homemade Gourmet Jams)
-- ----------------------------------------------------------------------------
INSERT INTO `products` (`id`, `name`, `slug`, `description`, `category`, `image_url`, `is_active`, `display_order`, `created_at`) VALUES
(1, 'Wild Mountain Huckleberry Jam', 'huckleberry', 'Hand-foraged wild mountain huckleberries simmered in small copper kettles with pure cane sugar and a hint of fresh Meyer lemon juice. Rich, deeply aromatic, and uniquely Northwest.', 'regular', '/assets/images/jam-huckleberry.jpg', 1, 1, NOW()),
(2, 'Rhubarb Huckleberry Jam', 'rhubarb-huckleberry', 'A balanced blend of wild alpine huckleberries and tart spring rhubarb from local valley orchards. Complex, tart-sweet balance that pairs well with aged cheeses and warm sourdough.', 'regular', '/assets/images/jam-rhubarb-huckleberry.jpg', 1, 2, NOW()),
(3, 'Flathead Cherry Jam', 'flathead-cherry', 'Sun-ripened Montana Flathead sweet cherries slow-reduced with subtle warm spices and real vanilla bean. Thick, chunky preserve loaded with whole cherry halves.', 'regular', '/assets/images/jam-flathead-cherry.jpg', 1, 3, NOW()),
(4, 'Pacific Wild Raspberry Jam', 'raspberry', 'Intensely fragrant summer red raspberries picked at peak maturity. Seed-softened, bright crimson preserves with a tart berry punch and velvet texture.', 'regular', '/assets/images/jam-raspberry.jpg', 1, 4, NOW()),
(5, 'Strawberry Vanilla Bean Jam', 'strawberry-vanilla-bean', 'Plump field strawberries infused with whole split Madagascar Bourbon vanilla bean pods. Gentle sweetness complemented by aromatic, creamy bourbon vanilla notes.', 'regular', '/assets/images/jam-strawberry-vanilla.jpg', 1, 5, NOW()),
(6, 'Pineapple Toasted Coconut Jam', 'pineapple-toasted-coconut', 'Golden Maui pineapple simmered with slow-toasted unsweetened coconut flakes and a pinch of hand-harvested sea salt. Bright tropical preserve with rich texture.', 'regular', '/assets/images/jam-pineapple-coconut.jpg', 1, 6, NOW());

-- ----------------------------------------------------------------------------
-- 2. Insert Product Variants (3 Sizes: 4 oz @ $9.00, 8 oz @ $14.00, 12 oz @ $17.00)
-- ----------------------------------------------------------------------------
INSERT INTO `product_variants` (`product_id`, `size`, `price`, `sku`, `stock_status`, `stock_qty`) VALUES
-- 1. Huckleberry
(1, '4 oz', 9.00, 'JAM-HUCK-04', 'in_stock', 45),
(1, '8 oz', 14.00, 'JAM-HUCK-08', 'in_stock', 60),
(1, '12 oz', 17.00, 'JAM-HUCK-12', 'in_stock', 30),

-- 2. Rhubarb Huckleberry
(2, '4 oz', 9.00, 'JAM-RHUB-HUCK-04', 'in_stock', 40),
(2, '8 oz', 14.00, 'JAM-RHUB-HUCK-08', 'in_stock', 55),
(2, '12 oz', 17.00, 'JAM-RHUB-HUCK-12', 'in_stock', 25),

-- 3. Flathead Cherry
(3, '4 oz', 9.00, 'JAM-CHERRY-04', 'in_stock', 50),
(3, '8 oz', 14.00, 'JAM-CHERRY-08', 'in_stock', 70),
(3, '12 oz', 17.00, 'JAM-CHERRY-12', 'in_stock', 35),

-- 4. Raspberry
(4, '4 oz', 9.00, 'JAM-RASP-04', 'in_stock', 48),
(4, '8 oz', 14.00, 'JAM-RASP-08', 'in_stock', 65),
(4, '12 oz', 17.00, 'JAM-RASP-12', 'in_stock', 28),

-- 5. Strawberry Vanilla Bean
(5, '4 oz', 9.00, 'JAM-STRAW-VAN-04', 'in_stock', 52),
(5, '8 oz', 14.00, 'JAM-STRAW-VAN-08', 'in_stock', 75),
(5, '12 oz', 17.00, 'JAM-STRAW-VAN-12', 'in_stock', 32),

-- 6. Pineapple Toasted Coconut
(6, '4 oz', 9.00, 'JAM-PINE-COCO-04', 'in_stock', 38),
(6, '8 oz', 14.00, 'JAM-PINE-COCO-08', 'in_stock', 50),
(6, '12 oz', 17.00, 'JAM-PINE-COCO-12', 'in_stock', 20);

-- ----------------------------------------------------------------------------
-- 3. Insert Bundle Configurations
-- ----------------------------------------------------------------------------
INSERT INTO `bundle_configs` (`id`, `type`, `name`, `base_size`, `fixed_price`, `description`, `is_active`) VALUES
(1, 'pack_3', '3-Pack Gourmet Sampler', '4 oz', 25.00, 'Choose any 3 gourmet jam flavors in 4 oz sampler jars or upgrade to 8 oz pantry jars. Available as Quick Pack (single flavor), Regular Pack, or Custom Mix & Match.', 1),
(2, 'pack_6', '6-Pack Pantry Collection', '8 oz', 78.00, 'Stock your pantry or table with six 8 oz jars. Select your favorite flavor for all jars, stick to regular favorites, or craft a custom curated 6-jar assortment.', 1),
(3, 'pack_12', '12-Pack Case Bundle', '8 oz', 150.00, 'Full case of twelve jars at bulk savings. Ideal for seasonal bakers, breakfast lovers, and hosting. Choose Quick Pack, Regular Pack, or fully Custom selections.', 1),
(4, 'gift_box_4', 'Artisanal 4-Jar Gift Box', '4 oz', 38.00, 'Exactly four 4 oz handcrafted jars nestled inside our signature foil-stamped, eco-friendly craft gift box tied with natural linen ribbon. Configure custom flavor assortments.', 1);

-- ----------------------------------------------------------------------------
-- 4. Insert Site Settings
-- ----------------------------------------------------------------------------
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('brand_name', 'Wild & Orchard Gourmet Artisan Jams', NOW()),
('header_logo_path', '/assets/images/logo.svg', NOW()),
('promo_banner_active', '1', NOW()),
('promo_banner_text', 'Fresh small-batch seasonal harvest ready! Free local delivery on orders $45+ in the Flathead Valley.', NOW()),
('pickup_address', 'The Jam Kitchen & Farmstand, 458 Orchard Vista Way, Suite B, Kalispell, MT 59901', NOW()),
('pickup_hours', 'Tuesday – Saturday: 10:00 AM – 6:00 PM (Closed Sunday & Monday)', NOW()),
('supported_zip_codes', '["59901", "59902", "59903", "59904", "59911", "59912", "59937"]', NOW()),
('delivery_radius_miles', '18', NOW()),
('local_delivery_fee', '6.50', NOW()),
('free_delivery_threshold', '45.00', NOW()),
('admin_email_primary', 'orders@wildandorchardjam.com', NOW()),
('admin_email_secondary', 'kitchen@wildandorchardjam.com', NOW()),
('square_environment', 'sandbox', NOW()),
('square_app_id', 'sandbox-sq0idb-YOUR_SQUARE_APP_ID', NOW()),
('square_location_id', 'YOUR_SQUARE_LOCATION_ID', NOW()),
('sales_tax_rate', '0.00', NOW());

-- ----------------------------------------------------------------------------
-- 5. Insert Admin Users (Default admin user, password: JamAdmin2026!)
-- ----------------------------------------------------------------------------
INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `email`, `last_login`) VALUES
(1, 'admin', '$2y$10$ubVjzhesnrv/Wsp8VRujh.yOWE3ujFfYwnCx1EJe2SGydWcRgQPKy', 'admin@wildandorchardjam.com', NOW());
