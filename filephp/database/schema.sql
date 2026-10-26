-- ============================================================
-- SecureShop E-Commerce Platform - Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 (full Unicode including emojis)
-- Collation: utf8mb4_unicode_ci
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO,STRICT_ALL_TABLES,NO_ZERO_DATE";
SET time_zone = "+05:30";
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Drop and recreate database
DROP DATABASE IF EXISTS `ecommerce_db`;
CREATE DATABASE `ecommerce_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `ecommerce_db`;

-- ============================================================
-- USERS & AUTHENTICATION
-- ============================================================

CREATE TABLE `users` (
    `id`                       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `username`                 VARCHAR(50)      NOT NULL,
    `email`                    VARCHAR(255)     NOT NULL,
    `password_hash`            VARCHAR(255)     NOT NULL,
    `role`                     ENUM('admin','shopkeeper','user') NOT NULL DEFAULT 'user',
    `first_name`               VARCHAR(100)     NOT NULL DEFAULT '',
    `last_name`                VARCHAR(100)     NOT NULL DEFAULT '',
    `phone`                    VARCHAR(20)               DEFAULT NULL,
    `avatar`                   VARCHAR(500)              DEFAULT NULL,
    `is_active`                TINYINT(1)       NOT NULL DEFAULT 1,
    `email_verified`           TINYINT(1)       NOT NULL DEFAULT 0,
    `phone_verified`           TINYINT(1)       NOT NULL DEFAULT 0,
    `email_verify_token`       VARCHAR(128)              DEFAULT NULL,
    `must_change_password`     TINYINT(1)       NOT NULL DEFAULT 0,
    `failed_login_attempts`    SMALLINT         NOT NULL DEFAULT 0,
    `locked_until`             DATETIME                  DEFAULT NULL,
    `last_login_at`            DATETIME                  DEFAULT NULL,
    `last_login_ip`            VARCHAR(45)               DEFAULT NULL,
    `password_changed_at`      DATETIME                  DEFAULT NULL,
    `created_at`               DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email`    (`email`),
    UNIQUE KEY `uq_username` (`username`),
    INDEX `idx_role`         (`role`),
    INDEX `idx_is_active`    (`is_active`),
    INDEX `idx_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `sessions` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED  NOT NULL,
    `session_token` VARCHAR(128)  NOT NULL,
    `ip_address`    VARCHAR(45)   NOT NULL,
    `user_agent`    VARCHAR(500)  NOT NULL DEFAULT '',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`    DATETIME      NOT NULL,
    `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_session_token` (`session_token`),
    INDEX `idx_user_id`   (`user_id`),
    INDEX `idx_expires_at`(`expires_at`),
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `remember_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at`    DATETIME              DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token_hash` (`token_hash`),
    INDEX `idx_user_id`   (`user_id`),
    INDEX `idx_expires_at`(`expires_at`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `password_reset_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45)           DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token_hash` (`token_hash`),
    INDEX `idx_user_id`   (`user_id`),
    INDEX `idx_expires_at`(`expires_at`),
    CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `otps` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier`   VARCHAR(255) NOT NULL,  -- phone number or email
    `type`         ENUM('register','login','reset') NOT NULL DEFAULT 'register',
    `otp_code`     VARCHAR(10)  NOT NULL,
    `temp_data`    JSON                  DEFAULT NULL,  -- pending user payload for register
    `expires_at`   DATETIME     NOT NULL,
    `is_used`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_identifier` (`identifier`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_is_used`    (`is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- RATE LIMITING & SECURITY
-- ============================================================

CREATE TABLE `rate_limits` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `action`       VARCHAR(50)  NOT NULL,
    `identifier`   VARCHAR(100) NOT NULL,  -- IP or user_id
    `attempts`     SMALLINT     NOT NULL DEFAULT 1,
    `window_start` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_attempt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_action_identifier` (`action`, `identifier`),
    INDEX `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `security_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type`  VARCHAR(100) NOT NULL,
    `user_id`     INT UNSIGNED          DEFAULT NULL,
    `ip_address`  VARCHAR(45)  NOT NULL,
    `user_agent`  VARCHAR(500)          DEFAULT NULL,
    `details`     JSON                  DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `audit_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED          DEFAULT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(100) NOT NULL,
    `entity_id`   INT UNSIGNED          DEFAULT NULL,
    `old_values`  JSON                  DEFAULT NULL,
    `new_values`  JSON                  DEFAULT NULL,
    `ip_address`  VARCHAR(45)           DEFAULT NULL,
    `user_agent`  VARCHAR(500)          DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_entity`     (`entity_type`, `entity_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `failed_logins` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier`   VARCHAR(255) NOT NULL,  -- email or username
    `ip_address`   VARCHAR(45)  NOT NULL,
    `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_agent`   VARCHAR(500)          DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_identifier`   (`identifier`),
    INDEX `idx_ip_address`   (`ip_address`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- CATEGORIES & PRODUCTS
-- ============================================================

CREATE TABLE `categories` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `parent_id`   INT UNSIGNED           DEFAULT NULL,
    `name`        VARCHAR(100)  NOT NULL,
    `slug`        VARCHAR(120)  NOT NULL,
    `description` TEXT                   DEFAULT NULL,
    `image`       VARCHAR(500)           DEFAULT NULL,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    INDEX `idx_parent_id` (`parent_id`),
    INDEX `idx_is_active` (`is_active`),
    CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `products` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `shopkeeper_id`  INT UNSIGNED    NOT NULL,
    `category_id`    INT UNSIGNED             DEFAULT NULL,
    `name`           VARCHAR(255)    NOT NULL,
    `slug`           VARCHAR(280)    NOT NULL,
    `description`    TEXT                     DEFAULT NULL,
    `short_desc`     VARCHAR(500)             DEFAULT NULL,
    `sku`            VARCHAR(100)             DEFAULT NULL,
    `price`          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `sale_price`     DECIMAL(10,2)            DEFAULT NULL,
    `stock_quantity` INT             NOT NULL DEFAULT 0,
    `low_stock_at`   INT             NOT NULL DEFAULT 5,
    `weight`         DECIMAL(8,3)             DEFAULT NULL,
    `image_path`     VARCHAR(500)             DEFAULT NULL,
    `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
    `is_featured`    TINYINT(1)      NOT NULL DEFAULT 0,
    `avg_rating`     DECIMAL(3,2)             DEFAULT NULL,
    `review_count`   INT             NOT NULL DEFAULT 0,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    INDEX `idx_shopkeeper_id` (`shopkeeper_id`),
    INDEX `idx_category_id`   (`category_id`),
    INDEX `idx_is_active`     (`is_active`),
    INDEX `idx_is_featured`   (`is_featured`),
    INDEX `idx_price`         (`price`),
    FULLTEXT KEY `ft_name_desc` (`name`, `description`),
    CONSTRAINT `fk_products_shopkeeper` FOREIGN KEY (`shopkeeper_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_products_category`   FOREIGN KEY (`category_id`)   REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_images` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(500) NOT NULL,
    `alt_text`   VARCHAR(255)          DEFAULT NULL,
    `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order` SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_product_id` (`product_id`),
    CONSTRAINT `fk_pimg_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- CART
-- ============================================================

CREATE TABLE `cart_items` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED          DEFAULT NULL,
    `session_key` VARCHAR(128)          DEFAULT NULL,
    `product_id`  INT UNSIGNED NOT NULL,
    `quantity`    SMALLINT     NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id`     (`user_id`),
    INDEX `idx_session_key` (`session_key`),
    INDEX `idx_product_id`  (`product_id`),
    CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SHIPPING ADDRESSES
-- ============================================================

CREATE TABLE `shipping_addresses` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `label`        VARCHAR(50)           DEFAULT 'Home',
    `full_name`    VARCHAR(200) NOT NULL,
    `phone`        VARCHAR(20)           DEFAULT NULL,
    `address_line1`VARCHAR(300) NOT NULL,
    `address_line2`VARCHAR(300)          DEFAULT NULL,
    `city`         VARCHAR(100) NOT NULL,
    `state`        VARCHAR(100) NOT NULL,
    `postal_code`  VARCHAR(20)  NOT NULL,
    `country`      VARCHAR(100) NOT NULL DEFAULT 'India',
    `is_default`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- DISCOUNT CODES
-- ============================================================

CREATE TABLE `discount_codes` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `code`             VARCHAR(50)     NOT NULL,
    `description`      VARCHAR(500)             DEFAULT NULL,
    `discount_type`    ENUM('percent','fixed')  NOT NULL DEFAULT 'percent',
    `discount_value`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `min_order_amount` DECIMAL(10,2)            DEFAULT 0.00,
    `max_discount`     DECIMAL(10,2)            DEFAULT NULL,
    `max_uses`         INT                       DEFAULT NULL,
    `used_count`       INT             NOT NULL DEFAULT 0,
    `expires_at`       DATETIME                 DEFAULT NULL,
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`       INT UNSIGNED             DEFAULT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_expires_at`(`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ORDERS
-- ============================================================

CREATE TABLE `orders` (
    `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`             INT UNSIGNED    NOT NULL,
    `order_number`        VARCHAR(20)     NOT NULL,
    `status`              ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `subtotal`            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `discount_amount`     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `tax_amount`          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `shipping_amount`     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `total_amount`        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `discount_code_id`    INT UNSIGNED             DEFAULT NULL,
    `discount_code_used`  VARCHAR(50)              DEFAULT NULL,
    `shipping_address_id` INT UNSIGNED             DEFAULT NULL,
    `shipping_snapshot`   JSON                     DEFAULT NULL,
    `payment_method`      VARCHAR(50)     NOT NULL DEFAULT 'cod',
    `payment_status`      ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `payment_reference`   VARCHAR(200)             DEFAULT NULL,
    `notes`               TEXT                     DEFAULT NULL,
    `tax_rate`            DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `ip_address`          VARCHAR(45)              DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_number` (`order_number`),
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_status`     (`status`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_orders_user`     FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`),
    CONSTRAINT `fk_orders_discount` FOREIGN KEY (`discount_code_id`) REFERENCES `discount_codes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_items` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`       INT UNSIGNED  NOT NULL,
    `product_id`     INT UNSIGNED           DEFAULT NULL,
    `shopkeeper_id`  INT UNSIGNED           DEFAULT NULL,
    `product_name`   VARCHAR(255)  NOT NULL,
    `product_sku`    VARCHAR(100)           DEFAULT NULL,
    `quantity`       SMALLINT      NOT NULL DEFAULT 1,
    `unit_price`     DECIMAL(10,2) NOT NULL,
    `total_price`    DECIMAL(10,2) NOT NULL,
    `product_snapshot` JSON                 DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_order_id`      (`order_id`),
    INDEX `idx_product_id`    (`product_id`),
    INDEX `idx_shopkeeper_id` (`shopkeeper_id`),
    CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_status_history` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`   INT UNSIGNED NOT NULL,
    `old_status` VARCHAR(50)           DEFAULT NULL,
    `new_status` VARCHAR(50)  NOT NULL,
    `notes`      TEXT                  DEFAULT NULL,
    `changed_by` INT UNSIGNED          DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order_id` (`order_id`),
    CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- REVIEWS
-- ============================================================

CREATE TABLE `reviews` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`  INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `order_id`    INT UNSIGNED          DEFAULT NULL,
    `rating`      TINYINT      NOT NULL COMMENT '1-5',
    `title`       VARCHAR(200)          DEFAULT NULL,
    `body`        TEXT                  DEFAULT NULL,
    `is_approved` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_user` (`product_id`, `user_id`),
    INDEX `idx_product_id`  (`product_id`),
    INDEX `idx_is_approved` (`is_approved`),
    CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TAX RATES
-- ============================================================

CREATE TABLE `tax_rates` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)  NOT NULL,
    `country`    VARCHAR(100)  NOT NULL DEFAULT 'India',
    `state`      VARCHAR(100)           DEFAULT NULL,
    `tax_rate`   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    INDEX `idx_country_state` (`country`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user (password: Admin@1234)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`, `email_verified`)
VALUES ('admin', 'admin@secureshop.local', '$2y$12$LYQ/V0JZP.j9Jt1LJoHdoekdVVjBpNhkjR7U.4RGFLbfUPBbJeP.', 'admin', 'Admin', 'User', 1, 1);

-- Shopkeeper user (password: Shop@1234)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`, `email_verified`)
VALUES ('shopkeeper1', 'shop@secureshop.local', '$2y$12$K0v8r2Vu1LpWFdmThfDdAeyJZ4wEe7cjxTxcU9e04CMBN4.W1Y9tG', 'shopkeeper', 'Shop', 'Keeper', 1, 1);

-- Regular user (password: User@1234)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`, `email_verified`)
VALUES ('customer1', 'user@secureshop.local', '$2y$12$xdSJw9v1hS8oKCK5gUXu/.y0BN0c14oOfrmgK4D.4Cl1oC4k1FZm.', 'user', 'Test', 'Customer', 1, 1);

-- Categories (Black Stone Clothing Collections)
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `is_active`, `sort_order`) VALUES
(1, 'T-Shirts', 't-shirts', 'Heavyweight 240-280GSM luxury streetwear tees with custom neck ribbing and signature graphics.', 1, 1),
(2, 'Hoodies', 'hoodies', 'Heavyweight 450GSM French terry hoodies with drop shoulders and custom metallic hardware.', 1, 2),
(3, 'Trousers', 'trousers', 'Relaxed utilitarian cargo pants, wide-leg trousers, and tailored streetwear essentials.', 1, 3),
(4, 'Shorts', 'shorts', 'Heavyweight fleece shorts and luxury double-layer breathable court mesh shorts.', 1, 4);

-- Sample Streetwear Products
INSERT INTO `products` (`id`, `shopkeeper_id`, `category_id`, `name`, `slug`, `description`, `short_desc`, `sku`, `price`, `sale_price`, `stock_quantity`, `is_active`, `is_featured`) VALUES
(1, 2, 1, 'Signature Gold Crest Tee', 'signature-gold-crest-tee', 'Cut from heavyweight 260GSM combed cotton, this signature piece features our iconic Black Stone gold crest embroidery on the left chest and a boxy modern drop-shoulder silhouette.', 'Heavyweight 260GSM cotton with gold crest embroidery', 'BST-001', 1499.00, 1199.00, 75, 1, 1),
(2, 2, 1, 'Cyber Void Oversized Graphic Tee', 'cyber-void-oversized-tee', 'Vintage mineral washed oversized tee featuring high-density puff print typography on the back and subtle chest micro-branding. Pre-shrunk with tight 1.25" neck collar.', 'Oversized drop-shoulder tee with high-density puff print', 'BST-002', 1699.00, 1399.00, 60, 1, 1),
(3, 2, 1, 'Minimalist Raw Edge Tee', 'minimalist-raw-edge-tee', 'Ultra-clean everyday essential crafted in premium 240GSM single-jersey cotton with subtle distressed hems and tonal branding.', 'Clean 240GSM cotton everyday luxury tee', 'BST-003', 1299.00, 999.00, 120, 1, 0),
(4, 2, 2, 'Obsidian Heavyweight 450GSM Hoodie', 'obsidian-heavyweight-hoodie', 'Constructed from impenetrable 450GSM French terry cotton. Features double-lined hood without drawstrings for an ultra-clean streetwear profile, deep kangaroo pocket, and ribbed side gussets.', 'Luxury 450GSM French terry with double-lined hood', 'BSH-001', 3999.00, 3299.00, 45, 1, 1),
(5, 2, 2, 'Gold Monogram Drop-Shoulder Hoodie', 'gold-monogram-hoodie', 'Statement hoodie featuring metallic gold crest embroidery on the hood and sleeve cuff. Heavyweight brushed fleece interior for winter warmth.', 'Brushed fleece hoodie with gold metallic embroidery', 'BSH-002', 4299.00, 3599.00, 35, 1, 1),
(6, 2, 3, 'Tactical Multi-Pocket Cargo Trousers', 'tactical-cargo-trousers', 'Crafted from high-tensile cotton ripstop with six ergonomic cargo pockets, reinforced knee darting, and adjustable cinch cords at the ankle cuffs for customizable styling.', 'High-tensile ripstop cargo pants with 6 pockets', 'BSP-001', 2899.00, 2399.00, 50, 1, 1),
(7, 2, 3, 'Relaxed Pleated Wide Trousers', 'relaxed-pleated-trousers', 'Effortless wide-leg trousers featuring double front pleats, hidden elasticated waistband with internal drawstrings, and a generous modern puddle break over sneakers.', 'Wide-leg pleated trousers with tailored streetwear drape', 'BSP-002', 2699.00, 2199.00, 40, 1, 0),
(8, 2, 4, 'Heavy Fleece Lounge Shorts', 'heavy-fleece-shorts', '380GSM French terry shorts designed for unmatched comfort. Deep zippered side pockets ensure your phone never slips out, finished with custom gold eyelets and thick drawstrings.', '380GSM French terry shorts with zippered pockets', 'BSS-001', 1799.00, 1399.00, 80, 1, 1),
(9, 2, 4, 'Double Layer Basketball Mesh Shorts', 'double-layer-mesh-shorts', 'Heavyweight athletic mesh outer layer with buttery-soft micro-mesh inner lining. Subtle tonal Black Stone crest patch on left hem and rear zipper pocket.', 'Breathable double-layer heavyweight court shorts', 'BSS-002', 1899.00, 1499.00, 65, 1, 0);

-- Tax rates
INSERT INTO `tax_rates` (`name`, `country`, `state`, `tax_rate`, `is_active`) VALUES
('GST Standard', 'India', NULL, 18.00, 1),
('GST Essential', 'India', 'Maharashtra', 5.00, 1);

-- Sample discount codes
INSERT INTO `discount_codes` (`code`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount`, `max_uses`, `expires_at`, `is_active`, `created_by`) VALUES
('WELCOME10', 'Welcome discount - 10% off on first order', 'percent', 10.00, 500.00, 500.00, 1000, DATE_ADD(NOW(), INTERVAL 30 DAY), 1, 1),
('FLAT200', 'Flat ₹200 off on orders above ₹2000', 'fixed', 200.00, 2000.00, 200.00, 500, DATE_ADD(NOW(), INTERVAL 30 DAY), 1, 1),
('SALE50', 'Grand Sale - 50% off (max ₹1000)', 'percent', 50.00, 1000.00, 1000.00, 100, DATE_ADD(NOW(), INTERVAL 7 DAY), 1, 1);


-- ============================================================
-- MYSTERY BOXES & CODES
-- ============================================================

CREATE TABLE IF NOT EXISTS `mystery_codes` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`         VARCHAR(50)  NOT NULL,
    `order_id`     INT UNSIGNED          DEFAULT NULL,
    `user_id`      INT UNSIGNED          DEFAULT NULL,
    `box_type`     VARCHAR(50)  NOT NULL DEFAULT 'gold',
    `prize_title`  VARCHAR(255) NOT NULL DEFAULT 'Mystery Discount',
    `prize_value`  VARCHAR(255) NOT NULL DEFAULT '20% OFF Coupon',
    `is_redeemed`  TINYINT(1)   NOT NULL DEFAULT 0,
    `redeemed_at`  DATETIME              DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_redeemed` (`is_redeemed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `mystery_boxes` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `title`        VARCHAR(200) NOT NULL,
    `rarity`       ENUM('common','rare','epic','legendary') NOT NULL DEFAULT 'rare',
    `prize_name`   VARCHAR(255) NOT NULL,
    `prize_desc`   TEXT                  DEFAULT NULL,
    `is_opened`    TINYINT(1)   NOT NULL DEFAULT 0,
    `opened_at`    DATETIME              DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_opened` (`is_opened`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Mystery Codes
INSERT INTO `mystery_codes` (`code`, `user_id`, `box_type`, `prize_title`, `prize_value`, `is_redeemed`) VALUES
('BLACKSTONE-DEMO1', 3, 'gold', 'Legendary Mystery Box', '₹500 Gift Voucher', 0),
('BLACKSTONE-DEMO2', 3, 'epic', 'Epic Mystery Box', '30% OFF Coupon', 0),
('BLACKSTONE-CODE99', 3, 'rare', 'Rare Mystery Box', 'Free Premium Sticker Pack', 0);

