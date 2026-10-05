-- ----------------------------------------------------------------------------
-- Add login credentials to adherents for portal access
-- ----------------------------------------------------------------------------
ALTER TABLE `adherents`
    ADD COLUMN `login`         VARCHAR(100) NULL AFTER `statut`,
    ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `login`;

CREATE UNIQUE INDEX `uk_adherents_login` ON `adherents` (`login`);
