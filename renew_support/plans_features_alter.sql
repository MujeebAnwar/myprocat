-- Add features_json to existing renew_support_plans installs.
-- Safe to skip if the column already exists.

ALTER TABLE `renew_support_plans`
  ADD COLUMN `features_json` TEXT DEFAULT NULL
    COMMENT 'JSON feature lists per tier (standard/extended/premier)'
    AFTER `premier_price`;

UPDATE `renew_support_plans` SET `features_json` = CASE `plan_code`
  WHEN 'A' THEN '{"standard":["Standard support coverage","Renews your current license package"],"extended":["Extended support coverage","Renews your current license package"],"premier":["Premier support coverage","Renews your current license package"]}'
  WHEN 'B' THEN '{"standard":["Second-seat convenience license","Requires active primary Winner license"]}'
  WHEN 'C' THEN '{"standard":["Student license renewal","Maximum of three consecutive renewals"]}'
  WHEN 'D' THEN '{"standard":["Standard support coverage","Renews your Edit-only license"],"extended":["Extended support coverage","Renews your Edit-only license"],"premier":["Premier support coverage","Renews your Edit-only license"]}'
  WHEN 'E' THEN '{"standard":["Standard support coverage","Includes Subscription Accounts"],"extended":["Extended support coverage","Includes Subscription Accounts"]}'
  WHEN 'F' THEN '{"standard":["Standard support coverage","Includes Subscription Accounts"],"extended":["Extended support coverage","Includes Subscription Accounts"]}'
  WHEN 'G' THEN '{"standard":["Standard support coverage","Includes Subscription Accounts"],"extended":["Extended support coverage","Includes Subscription Accounts"]}'
  WHEN 'H' THEN '{"standard":["Captivision add-on","Added to base plan price"]}'
  WHEN 'I' THEN '{"standard":["Standard support coverage","Renews your Xpression license"],"extended":["Premier support coverage","Renews your Xpression license"],"premier":["Platinum support coverage","Renews your Xpression license"]}'
  WHEN 'J' THEN '{"standard":["Standard support coverage","Renews your Impression license"],"extended":["Premier support coverage","Renews your Impression license"],"premier":["Platinum support coverage","Renews your Impression license"]}'
  WHEN 'K' THEN '{"standard":["Standard support coverage","Renews your Stylus license"]}'
  ELSE `features_json`
END
WHERE `features_json` IS NULL OR `features_json` = '';
