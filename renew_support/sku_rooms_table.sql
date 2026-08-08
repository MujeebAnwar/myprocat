-- Rooms granted when a Renew / Support SKU is purchased / applied.
-- Application writes one room_permissions row per product (by room_title).

CREATE TABLE IF NOT EXISTS `renew_support_sku_rooms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `grant_can_read` TINYINT(1) NOT NULL DEFAULT 1,
  `grant_can_upload` TINYINT(1) NOT NULL DEFAULT 0,
  `grant_can_remove` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_renew_support_sku_product` (`sku_id`, `product_id`),
  KEY `idx_renew_support_sku_rooms_product` (`product_id`),
  CONSTRAINT `fk_renew_support_sku_rooms_sku`
    FOREIGN KEY (`sku_id`) REFERENCES `renew_support_skus` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_renew_support_sku_rooms_product`
    FOREIGN KEY (`product_id`) REFERENCES `renew_support_products` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resolve product ids by product_key, then attach to skus by sku_key.
INSERT INTO `renew_support_sku_rooms`
  (`sku_id`, `product_id`, `grant_can_read`, `grant_can_upload`, `grant_can_remove`, `sort_order`)
SELECT s.id, p.id, 1, 0, 0, x.sort_order
FROM (
  SELECT 'winner_xp_only' AS sku_key, 'winner_xp' AS product_key, 10 AS sort_order
  UNION ALL SELECT 'winner_vr_only', 'winner_vr', 10
  UNION ALL SELECT 'edit_only', 'edit_only', 10
  UNION ALL SELECT 'winner_xp_subscription', 'winner_xp', 10
  UNION ALL SELECT 'winner_xp_subscription', 'subscription_account', 20
  UNION ALL SELECT 'winner_vr_subscription', 'winner_vr', 10
  UNION ALL SELECT 'winner_vr_subscription', 'subscription_account', 20
  UNION ALL SELECT 'student', 'student', 10
  UNION ALL SELECT 'student_xp', 'student_xp', 10
  UNION ALL SELECT 'student_vr', 'student_vr', 10
  UNION ALL SELECT 'edit_only_subscription', 'edit_only', 10
  UNION ALL SELECT 'edit_only_subscription', 'subscription_account', 20
  UNION ALL SELECT 'winner_xp_plus_xp2', 'winner_xp', 10
  UNION ALL SELECT 'winner_xp_plus_xp2', 'winner_xp_2', 20
  UNION ALL SELECT 'winner_vr_plus_vr2', 'winner_vr', 10
  UNION ALL SELECT 'winner_vr_plus_vr2', 'winner_vr_2', 20
  UNION ALL SELECT 'winner_xp_captivision', 'winner_xp', 10
  UNION ALL SELECT 'winner_xp_captivision', 'captivision', 20
  UNION ALL SELECT 'winner_vr_captivision', 'winner_vr', 10
  UNION ALL SELECT 'winner_vr_captivision', 'captivision', 20
  UNION ALL SELECT 'winner_xp_subscription_captivision', 'winner_xp', 10
  UNION ALL SELECT 'winner_xp_subscription_captivision', 'subscription_account', 20
  UNION ALL SELECT 'winner_xp_subscription_captivision', 'captivision', 30
  UNION ALL SELECT 'winner_vr_subscription_captivision', 'winner_vr', 10
  UNION ALL SELECT 'winner_vr_subscription_captivision', 'subscription_account', 20
  UNION ALL SELECT 'winner_vr_subscription_captivision', 'captivision', 30
) AS x
INNER JOIN `renew_support_skus` s ON s.sku_key = x.sku_key
INNER JOIN `renew_support_products` p ON p.product_key = x.product_key
ON DUPLICATE KEY UPDATE
  `grant_can_read` = VALUES(`grant_can_read`),
  `grant_can_upload` = VALUES(`grant_can_upload`),
  `grant_can_remove` = VALUES(`grant_can_remove`),
  `sort_order` = VALUES(`sort_order`);
