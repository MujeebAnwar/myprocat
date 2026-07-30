-- MyProCat platform-time purchases (perpetual or subscription license).
-- Replaces casepad_subscribed_plan for /store/ checkout flow.

CREATE TABLE IF NOT EXISTS `myprocat_purchases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` VARCHAR(255) NOT NULL,
  `license_id` INT UNSIGNED NOT NULL,
  `license_type` ENUM('perpetual', 'subscription') NOT NULL,
  `license_title` VARCHAR(255) NOT NULL,
  `rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `hours` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_four_digits` VARCHAR(4) DEFAULT NULL,
  `card_expiry_date` DATE DEFAULT NULL,
  `vault_id` TEXT DEFAULT NULL,
  `guid` TEXT DEFAULT NULL,
  `address_details` TEXT DEFAULT NULL,
  `prepaid` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `next_payment_date` DATETIME DEFAULT NULL,
  `auto_purchase_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `balance_threshold` DECIMAL(10,2) DEFAULT NULL COMMENT 'Hours; trigger auto-purchase when balance falls below',
  `min_account_balance` DECIMAL(10,2) DEFAULT NULL COMMENT 'Hours; target minimum balance after auto-purchase',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_myprocat_purchases_owner` (`id_owner`),
  KEY `idx_myprocat_purchases_active` (`id_owner`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Run once on existing databases (skip if columns already exist):
ALTER TABLE `myprocat_purchases`
  ADD COLUMN `auto_purchase_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `next_payment_date`,
  ADD COLUMN `balance_threshold` DECIMAL(10,2) DEFAULT NULL COMMENT 'Hours; trigger auto-purchase when balance falls below' AFTER `auto_purchase_enabled`,
  ADD COLUMN `min_account_balance` DECIMAL(10,2) DEFAULT NULL COMMENT 'Hours; target minimum balance after auto-purchase' AFTER `balance_threshold`;

-- Run once on existing databases (skip if column already exists):
ALTER TABLE `casepad_payment_invoices`
  ADD COLUMN `myprocat_purchase_id` INT UNSIGNED NULL DEFAULT NULL AFTER `subscribed_plan_id`,
  ADD KEY `idx_casepad_payment_invoices_myprocat_purchase` (`myprocat_purchase_id`);
