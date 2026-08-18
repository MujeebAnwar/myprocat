-- Existing installs: combined Subscription + 2nd seat SKUs (Plan E/F + Plan B $125).
-- Users who own Winner XP/VR + Subscription + XP2/VR2 renew as one package.

ALTER TABLE `renew_support_skus`
  MODIFY `pricing_column` ENUM('1', '2', '3', '1+4', '2+4', '2+3') NOT NULL;

INSERT INTO `renew_support_skus`
  (`sku_key`, `display_name`, `pricing_column`, `base_plan_code`, `addon_plan_code`,
   `requires_active_product_key`, `max_renewals`, `sort_order`, `notes`)
VALUES
  ('winner_xp_subscription_xp2', 'Winner XP + Subscription + Winner XP 2', '2+3', 'E', 'B', 'winner_xp', NULL, 85,
   'Col 2 + Col 3 → Plan E + Plan B $125'),
  ('winner_vr_subscription_vr2', 'Winner VR + Subscription + Winner VR 2', '2+3', 'F', 'B', 'winner_vr', NULL, 95,
   'Col 2 + Col 3 → Plan F + Plan B $125')
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
  SELECT 'winner_xp_subscription_xp2' AS sku_key, 'winner_xp' AS product_key, 10 AS sort_order
  UNION ALL SELECT 'winner_xp_subscription_xp2', 'subscription_account', 20
  UNION ALL SELECT 'winner_xp_subscription_xp2', 'winner_xp_2', 30
  UNION ALL SELECT 'winner_vr_subscription_vr2', 'winner_vr', 10
  UNION ALL SELECT 'winner_vr_subscription_vr2', 'subscription_account', 20
  UNION ALL SELECT 'winner_vr_subscription_vr2', 'winner_vr_2', 30
) AS x
INNER JOIN `renew_support_skus` s ON s.sku_key = x.sku_key
INNER JOIN `renew_support_products` p ON p.product_key = x.product_key
ON DUPLICATE KEY UPDATE
  `grant_can_read` = VALUES(`grant_can_read`),
  `grant_can_upload` = VALUES(`grant_can_upload`),
  `grant_can_remove` = VALUES(`grant_can_remove`),
  `sort_order` = VALUES(`sort_order`);
