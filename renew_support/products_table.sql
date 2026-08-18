-- Product catalog for Renew / Support.
-- Each product maps to an existing rooms.room_title (products ARE rooms).
-- id_room is optional cache; grants should resolve room by room_title if null.

CREATE TABLE IF NOT EXISTS `renew_support_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_key` VARCHAR(64) NOT NULL COMMENT 'Stable key used in code',
  `display_name` VARCHAR(255) NOT NULL COMMENT 'Name from pricing PDF',
  `room_title` VARCHAR(255) NOT NULL COMMENT 'Must match rooms.room_title',
  `id_room` INT DEFAULT NULL COMMENT 'Optional FK-like cache of rooms.id_room',
  `product_role` ENUM(
    'primary',
    'addon',
    'subscription_flag',
    'convenience_second'
  ) NOT NULL DEFAULT 'primary',
  `keyless_expected` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Expected rooms.keyless_access',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_renew_support_products_key` (`product_key`),
  UNIQUE KEY `uq_renew_support_products_room_title` (`room_title`),
  KEY `idx_renew_support_products_role` (`product_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `renew_support_products`
  (`product_key`, `display_name`, `room_title`, `id_room`, `product_role`, `keyless_expected`, `sort_order`, `notes`)
VALUES
  ('winner_xp',            'Winner XP',              'Winner XP',              4,   'primary',             1, 10, 'Professional steno'),
  ('winner_vr',            'Winner VR',              'Winner VR',              11,  'primary',             1, 20, 'Professional voice'),
  ('student',              'Student',                'Student',                14,  'primary',             1, 30, 'Student license; 3 renewals max'),
  ('student_xp',           'Student XP',             'Student XP',             NULL,'primary',             1, 31, 'Create room if missing; else map to Student'),
  ('student_vr',           'Student VR',             'Student VR',             NULL,'primary',             1, 32, 'Create room if missing; else map to Student'),
  ('edit_only',            'Edit-only',              'Winner Edit-Only',       18,  'primary',             1, 40, 'Edit-only perpetual'),
  ('captivision',          'Captivision',            'CaptiVision',            6,   'addon',               0, 50, 'Column 4 add-on (+$200)'),
  ('subscription_account', 'Subscription Account',   'Subscription Accounts',  112, 'subscription_flag',   0, 60, 'Never sold alone; combined with another license'),
  ('winner_xp_2',          'Winner XP 2',            '2nd Winner XP',          113, 'convenience_second',  0, 70, 'Column 3; requires active Winner XP'),
  ('winner_vr_2',          'Winner VR 2',            '2nd Winner VR',          114, 'convenience_second',  0, 80, 'Column 3; requires active Winner VR'),
  ('xpression',            'Xpression',              'Xpression',              NULL,'primary',             1, 90, 'Standard / Premier / Platinum'),
  ('impression',           'Impression',             'Impression',             NULL,'primary',             1, 100, 'Standard / Premier / Platinum'),
  ('stylus',               'Stylus',                 'Stylus',                 NULL,'primary',             1, 110, 'Standard only')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `room_title` = VALUES(`room_title`),
  `id_room` = VALUES(`id_room`),
  `product_role` = VALUES(`product_role`),
  `keyless_expected` = VALUES(`keyless_expected`),
  `sort_order` = VALUES(`sort_order`),
  `notes` = VALUES(`notes`);
