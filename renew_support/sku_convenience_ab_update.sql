-- Existing installs: Winner XP/VR + 2nd seat = Plan A tiers + Plan B ($125).
UPDATE `renew_support_skus`
SET
  `base_plan_code` = 'A',
  `addon_plan_code` = 'B',
  `notes` = 'Col 3 → Plan A tier + Plan B $125 (Winner XP 2); Note 1'
WHERE `sku_key` = 'winner_xp_plus_xp2';

UPDATE `renew_support_skus`
SET
  `base_plan_code` = 'A',
  `addon_plan_code` = 'B',
  `notes` = 'Col 3 → Plan A tier + Plan B $125 (Winner VR 2); Note 1'
WHERE `sku_key` = 'winner_vr_plus_vr2';
