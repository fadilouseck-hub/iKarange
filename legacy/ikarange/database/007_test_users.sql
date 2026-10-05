-- ============================================================================
-- I'KARANGE - Test Users for ALL portals
-- Default password: Pass2026! (bcrypt hash below)
-- Admin 'admin' keeps password: admin123
-- Run this in phpMyAdmin SQL tab on production
-- ============================================================================

SET @hash = '$2y$10$vd3of3BWUK3vkAP3CT2UuelBYDZRFGU6DTD3ZxEZwuvNh1bnnSkbm';
SET @hash_admin = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- ============================================================================
-- 1. ADMIN/STAFF USERS (login at /login)
-- ============================================================================

-- Update existing user passwords to Pass2026!
UPDATE users SET password_hash = @hash WHERE login = 'daouda';
UPDATE users SET password_hash = @hash WHERE login = 'cheikh';
UPDATE users SET password_hash = @hash WHERE login = 'fatou';
UPDATE users SET password_hash = @hash WHERE login = 'amsa-admin';

-- Make sure admin is super-admin
UPDATE users SET is_super = 1 WHERE login = 'admin';

-- ============================================================================
-- 2. PRESTATAIRE PORTAL USERS (login at /login → redirects to /tiers-payant)
-- ============================================================================

-- Update existing prestataire user passwords
UPDATE utilisateurs_prestataires SET password_hash = @hash WHERE login = 'chn_admin';
UPDATE utilisateurs_prestataires SET password_hash = @hash WHERE login = 'kissi_admin';
UPDATE utilisateurs_prestataires SET password_hash = @hash WHERE login = 'pharma_admin';

-- Create new prestataire portal users for remaining prestataires
INSERT INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active)
SELECT 1, 3, 'poly_admin', @hash, 'Dr. Ould Salem Ahmed', 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM utilisateurs_prestataires WHERE login = 'poly_admin');

INSERT INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active)
SELECT 1, 5, 'labo_admin', @hash, 'Dr. Mint Cheikh Khadijetou', 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM utilisateurs_prestataires WHERE login = 'labo_admin');

INSERT INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active)
SELECT 1, 6, 'imagerie_admin', @hash, 'Dr. Mohamed Lemine', 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM utilisateurs_prestataires WHERE login = 'imagerie_admin');

INSERT INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active)
SELECT 1, 7, 'dentiste_admin', @hash, 'Dr. Ould Cheikh Sidi', 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM utilisateurs_prestataires WHERE login = 'dentiste_admin');

INSERT INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active)
SELECT 1, 8, 'sabah_admin', @hash, 'Dr. Ndiaye Fatimata', 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM utilisateurs_prestataires WHERE login = 'sabah_admin');

INSERT INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active)
SELECT 1, 9, 'ndb_admin', @hash, 'Dr. Ba Amadou', 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM utilisateurs_prestataires WHERE login = 'ndb_admin');

INSERT INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active)
SELECT 1, 10, 'hamd_admin', @hash, 'Ould Taleb Mohameden', 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM utilisateurs_prestataires WHERE login = 'hamd_admin');

-- ============================================================================
-- 3. ADHERENT PORTAL USERS (login at /login → redirects to /portail-adherent)
--    Matched by matricule to ensure correct adherent_id
-- ============================================================================

-- Update existing adherent passwords + set login where already set
UPDATE adherents SET password_hash = @hash WHERE login IS NOT NULL AND login != '';

-- Set login + password for ALL adherents that don't have one yet
UPDATE adherents SET login = 'ould.dah',         password_hash = @hash WHERE matricule = 'SNIM-001' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'mint.sidi',        password_hash = @hash WHERE matricule = 'SNIM-002' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'ould.cheikh',      password_hash = @hash WHERE matricule = 'SNIM-003' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'ould.abdallahi',   password_hash = @hash WHERE matricule = 'SNIM-004' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'mint.bowba',       password_hash = @hash WHERE matricule = 'SNIM-005' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'ould.sidi',        password_hash = @hash WHERE matricule = 'SNIM-006' AND (login IS NULL OR login = '');

UPDATE adherents SET login = 'ba.mamadou',       password_hash = @hash WHERE matricule = 'MAU-001' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'diallo.aissata',   password_hash = @hash WHERE matricule = 'MAU-002' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'sy.ousmane',       password_hash = @hash WHERE matricule = 'MAU-003' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'ndiaye.aminata',   password_hash = @hash WHERE matricule = 'MAU-004' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'ould.mohamed',     password_hash = @hash WHERE matricule = 'MAU-005' AND (login IS NULL OR login = '');

UPDATE adherents SET login = 'ghazouani.ibrahim', password_hash = @hash WHERE matricule = 'BCM-001' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'mint.bilal',       password_hash = @hash WHERE matricule = 'BCM-002' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'ould.maouloud',    password_hash = @hash WHERE matricule = 'BCM-003' AND (login IS NULL OR login = '');

UPDATE adherents SET login = 'boubacar.moussa',  password_hash = @hash WHERE matricule = 'SOM-001' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'kane.oumar',       password_hash = @hash WHERE matricule = 'SOM-002' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'ould.horma',       password_hash = @hash WHERE matricule = 'SOM-003' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'diop.ibrahima',    password_hash = @hash WHERE matricule = 'SOM-004' AND (login IS NULL OR login = '');

UPDATE adherents SET login = 'camara.djiby',     password_hash = @hash WHERE matricule = 'KIN-001' AND (login IS NULL OR login = '');
UPDATE adherents SET login = 'ould.bah',         password_hash = @hash WHERE matricule = 'KIN-002' AND (login IS NULL OR login = '');
