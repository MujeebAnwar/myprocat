-- Renew / Support orders: one purchase/renewal attempt.
-- After payment, application should upsert room_permissions for each SKU room.

CREATE TABLE IF NOT EXISTS `renew_support_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` VARCHAR(64) NOT NULL,
  `sku_id` INT UNSIGNED NOT NULL,
  `sku_key` VARCHAR(64) NOT NULL COMMENT 'Denormalized for audit',
  `base_plan_code` CHAR(1) NOT NULL,
  `addon_plan_code` CHAR(1) DEFAULT NULL,
  `tier` ENUM('standard', 'extended', 'premier') NOT NULL DEFAULT 'standard',
  `base_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `addon_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `status` ENUM(
    'pending',
    'paid',
    'applied',
    'failed',
    'cancelled',
    'refunded'
  ) NOT NULL DEFAULT 'pending',
  `expires_at` DATETIME DEFAULT NULL COMMENT 'License expiry written to room_permissions',
  `rooms_granted_json` TEXT DEFAULT NULL COMMENT 'JSON snapshot of rooms granted',
  `invoice_number` VARCHAR(64) DEFAULT NULL,
  `transaction_id` VARCHAR(128) DEFAULT NULL,
  `payment_method` VARCHAR(64) DEFAULT NULL,
  `payment_response` MEDIUMTEXT DEFAULT NULL,
  `applied_at` DATETIME DEFAULT NULL COMMENT 'When room_permissions were written',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_renew_support_orders_user` (`id_user`),
  KEY `idx_renew_support_orders_status` (`status`),
  KEY `idx_renew_support_orders_sku` (`sku_id`),
  KEY `idx_renew_support_orders_created` (`created_at`),
  CONSTRAINT `fk_renew_support_orders_sku`
    FOREIGN KEY (`sku_id`) REFERENCES `renew_support_skus` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: track Student renewal count (Note 2 — max 3 consecutive years).
CREATE TABLE IF NOT EXISTS `renew_support_student_renewals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` VARCHAR(64) NOT NULL,
  `product_key` VARCHAR(64) NOT NULL DEFAULT 'student',
  `renewal_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_order_id` INT UNSIGNED DEFAULT NULL,
  `last_renewed_at` DATETIME DEFAULT NULL,
  `terminated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 after third renewal',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_renew_support_student_user_product` (`id_user`, `product_key`),
  KEY `idx_renew_support_student_order` (`last_order_id`),
  CONSTRAINT `fk_renew_support_student_order`
    FOREIGN KEY (`last_order_id`) REFERENCES `renew_support_orders` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
