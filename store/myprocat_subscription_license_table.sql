CREATE TABLE IF NOT EXISTS `myprocat_subscription_license_table` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `per_hour_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `minimum_hours` INT UNSIGNED NOT NULL DEFAULT 0,
  `type` ENUM('perpetual', 'subscription') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `myprocat_subscription_license_table` (`per_hour_amount`, `minimum_hours`, `type`) VALUES
(1.85, 10, 'perpetual'),
(2.00, 10, 'subscription')
ON DUPLICATE KEY UPDATE
  `per_hour_amount` = VALUES(`per_hour_amount`),
  `minimum_hours` = VALUES(`minimum_hours`);
