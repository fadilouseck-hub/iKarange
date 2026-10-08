-- 008 — Mobile API (Assur Plus iOS / Android apps)
-- Adds new tables only; existing tables are not altered. Safe to run more than once.

CREATE TABLE IF NOT EXISTS `mobile_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `access_hash` char(64) NOT NULL,
  `refresh_hash` char(64) NOT NULL,
  `access_expires_at` datetime NOT NULL,
  `refresh_expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mobile_tokens_access` (`access_hash`),
  UNIQUE KEY `uq_mobile_tokens_refresh` (`refresh_hash`),
  KEY `idx_mobile_tokens_adherent` (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_qr_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mobile_qr_tokens_hash` (`token_hash`),
  KEY `idx_mobile_qr_tokens_adherent` (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_uploads` (
  `id` char(32) NOT NULL,
  `adherent_id` int NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size` int NOT NULL,
  `received` int NOT NULL DEFAULT 0,
  `purpose` varchar(50) NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_uploads_adherent` (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_claim_drafts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `type_acte` varchar(50) NOT NULL,
  `fields` json DEFAULT NULL,
  `lines` json DEFAULT NULL,
  `upload_ids` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_claim_drafts_adherent` (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_vault_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `category` enum('prescription','lab_result','imaging','vaccine') NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size` int NOT NULL,
  `path` varchar(500) NOT NULL COMMENT 'AES-256-GCM encrypted file, relative to storage/uploads',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_vault_adherent` (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `platform` varchar(20) NOT NULL,
  `locale` varchar(20) DEFAULT NULL,
  `app_version` varchar(20) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mobile_devices_token` (`token`),
  KEY `idx_mobile_devices_adherent` (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_preferences` (
  `adherent_id` int NOT NULL,
  `language` varchar(5) DEFAULT NULL,
  `notifications` json DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_deletion_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adherent_id` int NOT NULL,
  `reason` text,
  `status` enum('requested','processed','rejected') NOT NULL DEFAULT 'requested',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_deletion_adherent` (`adherent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
