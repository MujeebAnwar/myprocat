-- Flexible key/value profile attributes per user.
-- Address/city/state/zip remain on accounts.
-- Examples: info=`company_name` / `phone`, and future keys.

CREATE TABLE IF NOT EXISTS `user_info` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` VARCHAR(64) NOT NULL,
  `info` VARCHAR(64) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_info_user_key` (`id_user`, `info`),
  KEY `idx_user_info_user` (`id_user`),
  KEY `idx_user_info_key` (`info`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
