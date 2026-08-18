-- Renew / Support pricing plans (A–H).
-- Dollar amounts come from Online MyProCAT Pricing Table — Company Confidential.
-- Entitlements still live in rooms + room_permissions; these tables only price SKUs.
--
-- features_json: JSON object keyed by tier, each value a list of feature strings.
-- Example:
-- {
--   "standard": ["Standard support coverage", "Renews your current license package"],
--   "extended": ["Extended support coverage", "Renews your current license package"],
--   "premier":  ["Premier support coverage", "Renews your current license package"]
-- }
-- A flat JSON array is also accepted and applied to every tier.

CREATE TABLE IF NOT EXISTS `renew_support_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_code` CHAR(1) NOT NULL COMMENT 'A–H from pricing sheet',
  `name` VARCHAR(100) NOT NULL,
  `standard_price` DECIMAL(10,2) DEFAULT NULL,
  `extended_price` DECIMAL(10,2) DEFAULT NULL,
  `premier_price` DECIMAL(10,2) DEFAULT NULL,
  `features_json` TEXT DEFAULT NULL COMMENT 'JSON feature lists per tier (standard/extended/premier)',
  `is_addon` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = add-on only (Plan H Captivision)',
  `notes` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_renew_support_plans_code` (`plan_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `renew_support_plans`
  (`plan_code`, `name`, `standard_price`, `extended_price`, `premier_price`, `features_json`, `is_addon`, `notes`)
VALUES
  ('A', 'Perpetual primary (Winner XP / Winner VR)', 695.00, 895.00, 995.00,
   '{"standard":["Standard support coverage","Renews your current license package"],"extended":["Extended support coverage","Renews your current license package"],"premier":["Premier support coverage","Renews your current license package"]}',
   0, 'Column 1 perpetual licenses'),
  ('B', 'Convenience second seat (Winner XP 2 / VR 2)', 125.00, NULL, NULL,
   '{"standard":["Second-seat convenience license","Requires active primary Winner license"]}',
   0, 'Column 3; renew only if perpetual primary is active'),
  ('C', 'Student', 99.00, NULL, NULL,
   '{"standard":["Student license renewal","Maximum of three consecutive renewals"]}',
   0, 'Student / Student XP / Student VR; 3 consecutive renewals max'),
  ('D', 'Edit-only perpetual', 475.00, 675.00, 875.00,
   '{"standard":["Standard support coverage","Renews your Edit-only license"],"extended":["Extended support coverage","Renews your Edit-only license"],"premier":["Premier support coverage","Renews your Edit-only license"]}',
   0, 'Column 1 Edit-only'),
  ('E', 'Winner XP + Subscription', 758.00, 1095.00, NULL,
   '{"standard":["Standard support coverage","Includes Subscription Accounts"],"extended":["Extended support coverage","Includes Subscription Accounts"]}',
   0, 'Column 2'),
  ('F', 'Winner VR + Subscription', 895.00, 1195.00, NULL,
   '{"standard":["Standard support coverage","Includes Subscription Accounts"],"extended":["Extended support coverage","Includes Subscription Accounts"]}',
   0, 'Column 2'),
  ('G', 'Edit-only + Subscription', 575.00, 875.00, NULL,
   '{"standard":["Standard support coverage","Includes Subscription Accounts"],"extended":["Extended support coverage","Includes Subscription Accounts"]}',
   0, 'Column 2'),
  ('H', 'Captivision add-on', 200.00, NULL, NULL,
   '{"standard":["Captivision add-on","Added to base plan price"]}',
   1, 'Column 4; always +$200 on top of base plan'),
  ('I', 'Xpression', 395.00, 495.00, 895.00,
   '{"standard":["Standard support coverage","Renews your Xpression license"],"extended":["Premier support coverage","Renews your Xpression license"],"premier":["Platinum support coverage","Renews your Xpression license"]}',
   0, 'Standard / Premier / Platinum'),
  ('J', 'Impression', 495.00, 595.00, 895.00,
   '{"standard":["Standard support coverage","Renews your Impression license"],"extended":["Premier support coverage","Renews your Impression license"],"premier":["Platinum support coverage","Renews your Impression license"]}',
   0, 'Standard / Premier / Platinum'),
  ('K', 'Stylus', 595.00, NULL, NULL,
   '{"standard":["Standard support coverage","Renews your Stylus license"]}',
   0, 'Standard only')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `standard_price` = VALUES(`standard_price`),
  `extended_price` = VALUES(`extended_price`),
  `premier_price` = VALUES(`premier_price`),
  `features_json` = VALUES(`features_json`),
  `is_addon` = VALUES(`is_addon`),
  `notes` = VALUES(`notes`);
