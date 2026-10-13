-- 010 — Online subscription in TEST MODE (Assur Plus iOS / Android apps)
-- Adds new tables only; existing tables are not altered. Safe to run more than once.

-- Members who subscribe online instead of belonging to a company plan.
CREATE TABLE IF NOT EXISTS `mobile_online_subscribers` (
  `adherent_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_quotes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `snapshot` json NOT NULL,
  `total` int NOT NULL,
  `eligible` tinyint(1) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_quotes_adherent` (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `quote_id` int NOT NULL,
  `number` varchar(40) NOT NULL,
  `product_id` varchar(40) NOT NULL,
  `coverage_rate` int NOT NULL,
  `total_premium` int NOT NULL,
  `snapshot` json NOT NULL,
  `status` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_policies_adherent` (`adherent_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `policy_id` int NOT NULL,
  `reference` varchar(40) NOT NULL,
  `amount` int NOT NULL,
  `method` varchar(20) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `return_url` varchar(255) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `status` varchar(20) NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_payments_adherent` (`adherent_id`),
  KEY `idx_mobile_payments_policy` (`policy_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
