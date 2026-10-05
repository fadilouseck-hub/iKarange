-- ============================================================================
-- I'KARANGE - Barème des Prestations (Benefits Schedule)
-- Migration 003
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- baremes - Per-enterprise benefits schedule (one row per act type)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `baremes` (
    `id`                INT           NOT NULL AUTO_INCREMENT,
    `org_id`            INT           NOT NULL,
    `entreprise_id`     INT           NOT NULL,
    `type_acte`         VARCHAR(50)   NOT NULL COMMENT 'Maps to prises_en_charge.type_acte or sub-type like maternite_simple',
    `libelle`           VARCHAR(255)  NOT NULL COMMENT 'Display label, e.g. Maternite simple',
    `taux_couverture`   DECIMAL(5,2)  NOT NULL DEFAULT 80.00,
    `plafond_acte`      DECIMAL(12,0) NULL DEFAULT NULL COMMENT 'NULL = FRAIS REELS (no ceiling)',
    `periode_plafond`   ENUM('par_evenement','par_an','par_2_ans') NULL DEFAULT NULL COMMENT 'NULL when plafond_acte is NULL',
    `plafond_par`       ENUM('beneficiaire','titulaire') NOT NULL DEFAULT 'beneficiaire' COMMENT 'Who the ceiling applies to',
    `is_active`         TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_baremes_org_ent_type` (`org_id`, `entreprise_id`, `type_acte`),
    INDEX `idx_baremes_entreprise_id` (`entreprise_id`),
    CONSTRAINT `fk_baremes_org`        FOREIGN KEY (`org_id`)        REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_baremes_entreprise` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`)   ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Add sous_type_acte column to prises_en_charge for maternity sub-types
-- ----------------------------------------------------------------------------
ALTER TABLE `prises_en_charge`
    ADD COLUMN `sous_type_acte` VARCHAR(50) NULL DEFAULT NULL AFTER `type_acte`;
