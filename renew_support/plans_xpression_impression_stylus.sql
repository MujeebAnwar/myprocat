-- Add Xpression / Impression / Stylus plans, products, and SKUs.
-- Tier mapping: Standard → standard, Premier → extended, Platinum → premier.

INSERT INTO `renew_support_plans`
  (`plan_code`, `name`, `standard_price`, `extended_price`, `premier_price`, `features_json`, `is_addon`, `notes`)
VALUES
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

INSERT INTO `renew_support_products`
  (`product_key`, `display_name`, `room_title`, `id_room`, `product_role`, `keyless_expected`, `sort_order`, `notes`)
VALUES
  ('xpression',  'Xpression',  'Xpression',  NULL, 'primary', 1, 90,  'Standard / Premier / Platinum'),
  ('impression', 'Impression', 'Impression', NULL, 'primary', 1, 100, 'Standard / Premier / Platinum'),
  ('stylus',     'Stylus',     'Stylus',     NULL, 'primary', 1, 110, 'Standard only')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `room_title` = VALUES(`room_title`),
  `id_room` = VALUES(`id_room`),
  `product_role` = VALUES(`product_role`),
  `keyless_expected` = VALUES(`keyless_expected`),
  `sort_order` = VALUES(`sort_order`),
  `notes` = VALUES(`notes`);

INSERT INTO `renew_support_skus`
  (`sku_key`, `display_name`, `pricing_column`, `base_plan_code`, `addon_plan_code`,
   `requires_active_product_key`, `max_renewals`, `sort_order`, `notes`)
VALUES
  ('xpression',  'Xpression',  '1', 'I', NULL, NULL, NULL, 140, 'Plan I — Standard / Premier / Platinum'),
  ('impression', 'Impression', '1', 'J', NULL, NULL, NULL, 150, 'Plan J — Standard / Premier / Platinum'),
  ('stylus',     'Stylus',     '1', 'K', NULL, NULL, NULL, 160, 'Plan K — Standard only')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `pricing_column` = VALUES(`pricing_column`),
  `base_plan_code` = VALUES(`base_plan_code`),
  `addon_plan_code` = VALUES(`addon_plan_code`),
  `requires_active_product_key` = VALUES(`requires_active_product_key`),
  `max_renewals` = VALUES(`max_renewals`),
  `sort_order` = VALUES(`sort_order`),
  `notes` = VALUES(`notes`);

INSERT INTO `renew_support_sku_rooms`
  (`sku_id`, `product_id`, `grant_can_read`, `grant_can_upload`, `grant_can_remove`, `sort_order`)
SELECT s.id, p.id, 1, 0, 0, x.sort_order
FROM (
  SELECT 'xpression' AS sku_key, 'xpression' AS product_key, 10 AS sort_order
  UNION ALL SELECT 'impression', 'impression', 10
  UNION ALL SELECT 'stylus', 'stylus', 10
) AS x
INNER JOIN `renew_support_skus` s ON s.sku_key = x.sku_key
INNER JOIN `renew_support_products` p ON p.product_key = x.product_key
ON DUPLICATE KEY UPDATE
  `grant_can_read` = VALUES(`grant_can_read`),
  `grant_can_upload` = VALUES(`grant_can_upload`),
  `grant_can_remove` = VALUES(`grant_can_remove`),
  `sort_order` = VALUES(`sort_order`);
