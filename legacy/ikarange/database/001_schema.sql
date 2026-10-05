-- ============================================================================
-- I'KARANGE - IPM & Mutuelle Management System
-- Database Schema v1.0
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. organizations - Multi-tenant organization table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organizations` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2. users - Admin / staff users
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT                                                    NOT NULL AUTO_INCREMENT,
    `org_id`        INT                                                    NOT NULL,
    `login`         VARCHAR(100)                                           NOT NULL,
    `password_hash` VARCHAR(255)                                           NOT NULL,
    `full_name`     VARCHAR(255)                                           NOT NULL,
    `email`         VARCHAR(255)                                           NULL DEFAULT NULL,
    `phone`         VARCHAR(50)                                            NULL DEFAULT NULL,
    `role`          ENUM('administrateur','gestionnaire','prestataire')     NOT NULL DEFAULT 'gestionnaire',
    `profile_id`    INT                                                    NULL DEFAULT NULL,
    `is_active`     TINYINT(1)                                             NOT NULL DEFAULT 1,
    `notes`         TEXT                                                   NULL DEFAULT NULL,
    `created_at`    TIMESTAMP                                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP                                              NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_login` (`login`),
    INDEX `idx_users_org_id` (`org_id`),
    INDEX `idx_users_profile_id` (`profile_id`),
    CONSTRAINT `fk_users_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3. access_profiles - Permission profiles
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `access_profiles` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `org_id`      INT          NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `permissions` JSON         NULL DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_access_profiles_org_id` (`org_id`),
    CONSTRAINT `fk_access_profiles_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4. entreprises - Companies subscribing to IPM
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `entreprises` (
    `id`                    INT                                    NOT NULL AUTO_INCREMENT,
    `org_id`                INT                                    NOT NULL,
    `raison_sociale`        VARCHAR(255)                           NOT NULL,
    `ninea`                 VARCHAR(50)                            NULL DEFAULT NULL,
    `telephone`             VARCHAR(50)                            NULL DEFAULT NULL,
    `email`                 VARCHAR(255)                           NULL DEFAULT NULL,
    `secteur_activite`      VARCHAR(255)                           NULL DEFAULT NULL,
    `adresse`               TEXT                                   NULL DEFAULT NULL,
    `nombre_employes`       INT                                    NOT NULL DEFAULT 0,
    `taux_cotisation`       DECIMAL(5,2)                           NOT NULL DEFAULT 0.00,
    `taux_prise_en_charge`  DECIMAL(5,2)                           NOT NULL DEFAULT 80.00,
    `date_adhesion`         DATE                                   NULL DEFAULT NULL,
    `statut`                ENUM('actif','inactif','suspendu')      NOT NULL DEFAULT 'actif',
    `created_at`            TIMESTAMP                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP                              NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_entreprises_org_id` (`org_id`),
    CONSTRAINT `fk_entreprises_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 5. adherents - Members / employees
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `adherents` (
    `id`              INT                                          NOT NULL AUTO_INCREMENT,
    `org_id`          INT                                          NOT NULL,
    `entreprise_id`   INT                                          NOT NULL,
    `nom`             VARCHAR(255)                                 NOT NULL,
    `prenom`          VARCHAR(255)                                 NOT NULL,
    `matricule`       VARCHAR(50)                                  NOT NULL,
    `sexe`            ENUM('masculin','feminin')                   NOT NULL DEFAULT 'masculin',
    `date_naissance`  DATE                                         NULL DEFAULT NULL,
    `telephone`       VARCHAR(50)                                  NULL DEFAULT NULL,
    `photo`           VARCHAR(255)                                 NULL DEFAULT NULL,
    `categorie`       ENUM('titulaire','conjoint','enfant')        NOT NULL DEFAULT 'titulaire',
    `plafond_annuel`  DECIMAL(12,0)                                NOT NULL DEFAULT 0,
    `date_adhesion`   DATE                                         NULL DEFAULT NULL,
    `statut`          ENUM('actif','inactif','suspendu')            NOT NULL DEFAULT 'actif',
    `created_at`      TIMESTAMP                                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP                                    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_adherents_matricule` (`matricule`),
    INDEX `idx_adherents_org_id` (`org_id`),
    INDEX `idx_adherents_entreprise_id` (`entreprise_id`),
    CONSTRAINT `fk_adherents_org`        FOREIGN KEY (`org_id`)        REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_adherents_entreprise` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`)   ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 6. prestataires - Healthcare providers
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prestataires` (
    `id`           INT                                                                                           NOT NULL AUTO_INCREMENT,
    `org_id`       INT                                                                                           NOT NULL,
    `nom`          VARCHAR(255)                                                                                  NOT NULL,
    `type`         ENUM('clinique','hopital','pharmacie','laboratoire','centre_imagerie','dentiste')              NOT NULL DEFAULT 'clinique',
    `ville`        VARCHAR(255)                                                                                  NULL DEFAULT NULL,
    `adresse`      TEXT                                                                                          NULL DEFAULT NULL,
    `telephone`    VARCHAR(50)                                                                                   NULL DEFAULT NULL,
    `email`        VARCHAR(255)                                                                                  NULL DEFAULT NULL,
    `specialites`  TEXT                                                                                          NULL DEFAULT NULL,
    `statut`       ENUM('agree','suspendu','resilie')                                                            NOT NULL DEFAULT 'agree',
    `created_at`   TIMESTAMP                                                                                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP                                                                                    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_prestataires_org_id` (`org_id`),
    CONSTRAINT `fk_prestataires_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 7. utilisateurs_prestataires - Provider portal login accounts
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utilisateurs_prestataires` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `org_id`         INT          NOT NULL,
    `prestataire_id` INT          NOT NULL,
    `login`          VARCHAR(100) NOT NULL,
    `password_hash`  VARCHAR(255) NOT NULL,
    `nom_complet`    VARCHAR(255) NOT NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_utilisateurs_prestataires_login` (`login`),
    INDEX `idx_utilisateurs_prestataires_org_id` (`org_id`),
    INDEX `idx_utilisateurs_prestataires_prestataire_id` (`prestataire_id`),
    CONSTRAINT `fk_utilisateurs_prestataires_org`         FOREIGN KEY (`org_id`)         REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_utilisateurs_prestataires_prestataire` FOREIGN KEY (`prestataire_id`) REFERENCES `prestataires` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 8. prises_en_charge - PEC / Care authorizations
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prises_en_charge` (
    `id`               INT                                                                                      NOT NULL AUTO_INCREMENT,
    `org_id`           INT                                                                                      NOT NULL,
    `numero`           VARCHAR(50)                                                                              NOT NULL,
    `adherent_id`      INT                                                                                      NOT NULL,
    `prestataire_id`   INT                                                                                      NOT NULL,
    `type_acte`        ENUM('consultation','analyse','pharmacie','hospitalisation','imagerie','dentaire','chirurgie','optique','maternite','autre')        NOT NULL DEFAULT 'consultation',
    `date_soins`       DATE                                                                                     NULL DEFAULT NULL,
    `montant_total`    DECIMAL(12,0)                                                                            NOT NULL DEFAULT 0,
    `taux_couverture`  DECIMAL(5,2)                                                                             NOT NULL DEFAULT 80.00,
    `part_ipm`         DECIMAL(12,0)                                                                            NOT NULL DEFAULT 0,
    `part_adherent`    DECIMAL(12,0)                                                                            NOT NULL DEFAULT 0,
    `motif`            TEXT                                                                                     NULL DEFAULT NULL,
    `statut`           ENUM('en_attente','approuvee','reglee','rejetee','facturee')                              NOT NULL DEFAULT 'en_attente',
    `observations`     TEXT                                                                                     NULL DEFAULT NULL,
    `created_at`       TIMESTAMP                                                                                NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP                                                                                NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_prises_en_charge_numero` (`numero`),
    INDEX `idx_prises_en_charge_org_id` (`org_id`),
    INDEX `idx_prises_en_charge_adherent_id` (`adherent_id`),
    INDEX `idx_prises_en_charge_prestataire_id` (`prestataire_id`),
    CONSTRAINT `fk_prises_en_charge_org`         FOREIGN KEY (`org_id`)         REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_prises_en_charge_adherent`    FOREIGN KEY (`adherent_id`)    REFERENCES `adherents` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_prises_en_charge_prestataire` FOREIGN KEY (`prestataire_id`) REFERENCES `prestataires` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 9. factures_prestataires - Provider invoices
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `factures_prestataires` (
    `id`              INT                                                    NOT NULL AUTO_INCREMENT,
    `org_id`          INT                                                    NOT NULL,
    `numero`          VARCHAR(50)                                            NOT NULL,
    `prestataire_id`  INT                                                    NOT NULL,
    `montant_total`   DECIMAL(12,0)                                          NOT NULL DEFAULT 0,
    `montant_paye`    DECIMAL(12,0)                                          NOT NULL DEFAULT 0,
    `date_facture`    DATE                                                   NULL DEFAULT NULL,
    `date_echeance`   DATE                                                   NULL DEFAULT NULL,
    `statut`          ENUM('en_attente','validee','partiel','payee')          NOT NULL DEFAULT 'en_attente',
    `observations`    TEXT                                                   NULL DEFAULT NULL,
    `created_at`      TIMESTAMP                                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP                                              NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_factures_prestataires_numero` (`numero`),
    INDEX `idx_factures_prestataires_org_id` (`org_id`),
    INDEX `idx_factures_prestataires_prestataire_id` (`prestataire_id`),
    CONSTRAINT `fk_factures_prestataires_org`         FOREIGN KEY (`org_id`)         REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_factures_prestataires_prestataire` FOREIGN KEY (`prestataire_id`) REFERENCES `prestataires` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 10. factures_entreprises - Company invoices
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `factures_entreprises` (
    `id`              INT                                                    NOT NULL AUTO_INCREMENT,
    `org_id`          INT                                                    NOT NULL,
    `numero`          VARCHAR(50)                                            NOT NULL,
    `entreprise_id`   INT                                                    NOT NULL,
    `montant_total`   DECIMAL(12,0)                                          NOT NULL DEFAULT 0,
    `montant_paye`    DECIMAL(12,0)                                          NOT NULL DEFAULT 0,
    `date_facture`    DATE                                                   NULL DEFAULT NULL,
    `date_echeance`   DATE                                                   NULL DEFAULT NULL,
    `statut`          ENUM('en_attente','validee','partiel','payee')          NOT NULL DEFAULT 'en_attente',
    `observations`    TEXT                                                   NULL DEFAULT NULL,
    `created_at`      TIMESTAMP                                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP                                              NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_factures_entreprises_numero` (`numero`),
    INDEX `idx_factures_entreprises_org_id` (`org_id`),
    INDEX `idx_factures_entreprises_entreprise_id` (`entreprise_id`),
    CONSTRAINT `fk_factures_entreprises_org`        FOREIGN KEY (`org_id`)        REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_factures_entreprises_entreprise` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`)   ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 11. primes - Primes / Cotisations (monthly premiums)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `primes` (
    `id`                INT                                            NOT NULL AUTO_INCREMENT,
    `org_id`            INT                                            NOT NULL,
    `entreprise_id`     INT                                            NOT NULL,
    `mois`              VARCHAR(7)                                     NOT NULL COMMENT 'Format YYYY-MM',
    `montant`           DECIMAL(12,0)                                  NOT NULL DEFAULT 0,
    `nombre_adherents`  INT                                            NOT NULL DEFAULT 0,
    `statut`            ENUM('a_facturer','facturee','payee')           NOT NULL DEFAULT 'a_facturer',
    `date_paiement`     DATE                                           NULL DEFAULT NULL,
    `created_at`        TIMESTAMP                                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP                                      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_primes_org_entreprise_mois` (`org_id`, `entreprise_id`, `mois`),
    INDEX `idx_primes_org_id` (`org_id`),
    INDEX `idx_primes_entreprise_id` (`entreprise_id`),
    CONSTRAINT `fk_primes_org`        FOREIGN KEY (`org_id`)        REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_primes_entreprise` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`)   ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 12. compagnies_assurance - Insurance companies
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compagnies_assurance` (
    `id`               INT          NOT NULL AUTO_INCREMENT,
    `org_id`           INT          NOT NULL,
    `nom`              VARCHAR(255) NOT NULL,
    `code`             VARCHAR(50)  NULL DEFAULT NULL,
    `email`            VARCHAR(255) NULL DEFAULT NULL,
    `adresse`          TEXT         NULL DEFAULT NULL,
    `telephone`        VARCHAR(50)  NULL DEFAULT NULL,
    `contact_personne` VARCHAR(255) NULL DEFAULT NULL,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_compagnies_assurance_org_id` (`org_id`),
    CONSTRAINT `fk_compagnies_assurance_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 13. login_attempts - Rate limiting
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT         NOT NULL AUTO_INCREMENT,
    `ip_address`   VARCHAR(45) NOT NULL,
    `attempted_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_login_attempts_ip_address` (`ip_address`),
    INDEX `idx_login_attempts_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
