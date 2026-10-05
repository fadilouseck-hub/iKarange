-- ----------------------------------------------------------------------------
-- Expand organizations table for assureur management
-- ----------------------------------------------------------------------------
ALTER TABLE `organizations`
    ADD COLUMN `slug`          VARCHAR(100) NULL AFTER `name`,
    ADD COLUMN `contact_name`  VARCHAR(255) NULL AFTER `slug`,
    ADD COLUMN `contact_email` VARCHAR(255) NULL AFTER `contact_name`,
    ADD COLUMN `contact_phone` VARCHAR(50)  NULL AFTER `contact_email`,
    ADD COLUMN `address`       TEXT         NULL AFTER `contact_phone`,
    ADD COLUMN `is_active`     TINYINT(1)   NOT NULL DEFAULT 1 AFTER `address`,
    ADD COLUMN `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- ----------------------------------------------------------------------------
-- Add super-admin flag to users
-- ----------------------------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN `is_super` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;
