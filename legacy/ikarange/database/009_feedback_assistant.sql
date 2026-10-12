-- 009 — In-app feedback and assistant (Assur Plus iOS / Android apps)
-- Adds new tables only; existing tables are not altered. Safe to run more than once.

CREATE TABLE IF NOT EXISTS `mobile_feedback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `org_id` int DEFAULT NULL,
  `adherent_id` int NOT NULL,
  `category` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `app_version` varchar(40) DEFAULT NULL,
  `os_version` varchar(40) DEFAULT NULL,
  `device_model` varchar(60) DEFAULT NULL,
  `locale` varchar(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_feedback_adherent` (`adherent_id`, `created_at`),
  KEY `idx_mobile_feedback_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per assistant question: rate limiting and usage follow-up only. The conversation is not stored.
CREATE TABLE IF NOT EXISTS `mobile_assistant_usage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `input_tokens` int NOT NULL DEFAULT 0,
  `output_tokens` int NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_assistant_usage_adherent` (`adherent_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
