-- Resolved price helper view for Renew / Support SKUs.
-- total = base plan tier price + optional addon plan price (Captivision H).

CREATE OR REPLACE VIEW `renew_support_sku_prices` AS
SELECT
  s.id AS sku_id,
  s.sku_key,
  s.display_name,
  s.pricing_column,
  s.base_plan_code,
  s.addon_plan_code,
  bp.standard_price AS base_standard,
  bp.extended_price AS base_extended,
  bp.premier_price AS base_premier,
  COALESCE(ap.standard_price, 0.00) AS addon_standard,
  (bp.standard_price + COALESCE(ap.standard_price, 0.00)) AS total_standard,
  CASE
    WHEN bp.extended_price IS NULL THEN NULL
    ELSE bp.extended_price + COALESCE(ap.standard_price, 0.00)
  END AS total_extended,
  CASE
    WHEN bp.premier_price IS NULL THEN NULL
    ELSE bp.premier_price + COALESCE(ap.standard_price, 0.00)
  END AS total_premier,
  s.requires_active_product_key,
  s.max_renewals,
  s.is_active
FROM `renew_support_skus` s
INNER JOIN `renew_support_plans` bp ON bp.plan_code = s.base_plan_code
LEFT JOIN `renew_support_plans` ap ON ap.plan_code = s.addon_plan_code;
