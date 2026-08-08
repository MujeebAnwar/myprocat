-- Sellable Renew / Support SKUs (pricing sheet database entries).
-- Each SKU points at a base plan (and optional addon plan H).
-- Rooms granted for a SKU are listed in renew_support_sku_rooms.

CREATE TABLE IF NOT EXISTS `renew_support_skus` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku_key` VARCHAR(64) NOT NULL COMMENT 'Stable key used in checkout / admin',
  `display_name` VARCHAR(255) NOT NULL COMMENT 'Database entry label from pricing sheet',
  `pricing_column` ENUM('1', '2', '3', '1+4', '2+4') NOT NULL,
  `base_plan_code` CHAR(1) NOT NULL COMMENT 'Primary plan letter A–G',
  `addon_plan_code` CHAR(1) DEFAULT NULL COMMENT 'H=Captivision (+$200) or B=2nd seat (+$125)',
  `requires_active_product_key` VARCHAR(64) DEFAULT NULL COMMENT 'e.g. winner_xp for XP2 renewals',
  `max_renewals` INT UNSIGNED DEFAULT NULL COMMENT 'e.g. 3 for Student',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_renew_support_skus_key` (`sku_key`),
  KEY `idx_renew_support_skus_base_plan` (`base_plan_code`),
  KEY `idx_renew_support_skus_column` (`pricing_column`),
  CONSTRAINT `fk_renew_support_skus_base_plan`
    FOREIGN KEY (`base_plan_code`) REFERENCES `renew_support_plans` (`plan_code`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_renew_support_skus_addon_plan`
    FOREIGN KEY (`addon_plan_code`) REFERENCES `renew_support_plans` (`plan_code`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `renew_support_skus`
  (`sku_key`, `display_name`, `pricing_column`, `base_plan_code`, `addon_plan_code`,
   `requires_active_product_key`, `max_renewals`, `sort_order`, `notes`)
VALUES
  ('winner_xp_only',                     'Winner XP only',                              '1',   'A', NULL, NULL,        NULL, 10, 'Col 1 → Plan A'),
  ('winner_vr_only',                     'Winner VR only',                              '1',   'A', NULL, NULL,        NULL, 20, 'Col 1 → Plan A'),
  ('edit_only',                          'Edit-only',                                   '1',   'D', NULL, NULL,        NULL, 30, 'Col 1 → Plan D'),
  ('winner_xp_subscription',             'Winner XP + Subscription',                    '2',   'E', NULL, NULL,        NULL, 40, 'Col 2 → Plan E'),
  ('winner_vr_subscription',             'Winner VR + Subscription',                    '2',   'F', NULL, NULL,        NULL, 50, 'Col 2 → Plan F'),
  ('student',                            'Student',                                     '2',   'C', NULL, NULL,        3,    60, 'Col 2 → Plan C; terminates after 3rd renewal'),
  ('student_xp',                         'Student XP',                                  '2',   'C', NULL, NULL,        3,    61, 'Col 2 → Plan C'),
  ('student_vr',                         'Student VR',                                  '2',   'C', NULL, NULL,        3,    62, 'Col 2 → Plan C'),
  ('edit_only_subscription',             'Edit-only + Subscription',                    '2',   'G', NULL, NULL,        NULL, 70, 'Col 2 → Plan G'),
  ('winner_xp_plus_xp2',                 'Winner XP + Winner XP 2',                     '3',   'A', 'B',  'winner_xp', NULL, 80, 'Col 3 → Plan A tier + Plan B $125 (Winner XP 2); Note 1'),
  ('winner_vr_plus_vr2',                 'Winner VR + Winner VR 2',                     '3',   'A', 'B',  'winner_vr', NULL, 90, 'Col 3 → Plan A tier + Plan B $125 (Winner VR 2); Note 1'),
  ('winner_xp_captivision',              'Winner XP + Captivision',                     '1+4', 'A', 'H',  NULL,        NULL, 100, 'Col 1 + Col 4 → A + H'),
  ('winner_vr_captivision',              'Winner VR + Captivision',                     '1+4', 'A', 'H',  NULL,        NULL, 110, 'Col 1 + Col 4 → A + H'),
  ('winner_xp_subscription_captivision', 'Winner XP + Subscription + Captivision',      '2+4', 'E', 'H',  NULL,        NULL, 120, 'Col 2 + Col 4 → E + H'),
  ('winner_vr_subscription_captivision', 'Winner VR + Subscription + Captivision',      '2+4', 'F', 'H',  NULL,        NULL, 130, 'Col 2 + Col 4 → F + H')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `pricing_column` = VALUES(`pricing_column`),
  `base_plan_code` = VALUES(`base_plan_code`),
  `addon_plan_code` = VALUES(`addon_plan_code`),
  `requires_active_product_key` = VALUES(`requires_active_product_key`),
  `max_renewals` = VALUES(`max_renewals`),
  `sort_order` = VALUES(`sort_order`),
  `notes` = VALUES(`notes`);
