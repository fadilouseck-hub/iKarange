-- ============================================================================
-- I'KARANGE - Complete Test Users (v2)
-- Password: Pass2026! (except admin = admin123)
-- Run in phpMyAdmin - paste ALL at once and click Go
-- ============================================================================

-- Clear any rate limit lockout
DELETE FROM login_attempts;

-- ── 1. ADMIN/STAFF (5 users) ──
UPDATE users SET password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE login IN ('daouda','cheikh','fatou','amsa-admin','test-assurance-sa-admin');
UPDATE users SET is_super = 1 WHERE login = 'admin';

-- ── 2. PRESTATAIRE PORTAL (10 users) ──
UPDATE utilisateurs_prestataires SET password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC';

INSERT IGNORE INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active) VALUES
(1, 3, 'poly_admin', '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC', 'Dr. Ould Salem Ahmed', 1),
(1, 5, 'labo_admin', '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC', 'Dr. Mint Cheikh Khadijetou', 1),
(1, 6, 'imagerie_admin', '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC', 'Dr. Mohamed Lemine Ould Horma', 1),
(1, 7, 'dentiste_admin', '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC', 'Dr. Ould Cheikh Sidi', 1),
(1, 8, 'sabah_admin', '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC', 'Dr. Ndiaye Fatimata', 1),
(1, 9, 'ndb_admin', '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC', 'Dr. Ba Amadou', 1),
(1, 10, 'hamd_admin', '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC', 'Ould Taleb Mohameden', 1);

-- ── 3. ADHERENT PORTAL (36 users) ──
-- First reset all existing adherent passwords
UPDATE adherents SET password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE login IS NOT NULL AND login != '';

-- SNIM (6)
UPDATE adherents SET login = 'ould.dah', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SNIM-001';
UPDATE adherents SET login = 'mint.sidi', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SNIM-002';
UPDATE adherents SET login = 'ould.cheikh', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SNIM-003';
UPDATE adherents SET login = 'ould.abdallahi', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SNIM-004';
UPDATE adherents SET login = 'mint.bowba', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SNIM-005';
UPDATE adherents SET login = 'ould.sidi', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SNIM-006';

-- Mauritel SA (6)
UPDATE adherents SET login = 'ba.mamadou', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'MAU-001';
UPDATE adherents SET login = 'diallo.aissata', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'MAU-002';
UPDATE adherents SET login = 'sy.ousmane', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'MAU-003';
UPDATE adherents SET login = 'ndiaye.aminata', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'MAU-004';
UPDATE adherents SET login = 'ould.mohamed', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'MAU-005';
UPDATE adherents SET login = 'mint.cheikhna', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'MAU-006';

-- Banque Centrale (5)
UPDATE adherents SET login = 'ghazouani.ibrahim', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'BCM-001';
UPDATE adherents SET login = 'mint.bilal', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'BCM-002';
UPDATE adherents SET login = 'ould.maouloud', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'BCM-003';
UPDATE adherents SET login = 'ould.brahim', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'BCM-004';
UPDATE adherents SET login = 'mint.cheikh', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'BCM-005';

-- SOMELEC (4)
UPDATE adherents SET login = 'boubacar.moussa', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SOM-001';
UPDATE adherents SET login = 'kane.oumar', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SOM-002';
UPDATE adherents SET login = 'ould.horma', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SOM-003';
UPDATE adherents SET login = 'diop.ibrahima', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SOM-004';

-- Kinross Tasiast (4)
UPDATE adherents SET login = 'camara.djiby', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'KIN-001';
UPDATE adherents SET login = 'ould.vall', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'KIN-002';
UPDATE adherents SET login = 'ould.bah', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'KIN-003';
UPDATE adherents SET login = 'mint.abdel', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'KIN-004';

-- Chinguitel SA (3)
UPDATE adherents SET login = 'ould.jiddou', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'CHI-001';
UPDATE adherents SET login = 'mint.mohamed', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'CHI-002';
UPDATE adherents SET login = 'ould.sid', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'CHI-003';

-- GBM (2)
UPDATE adherents SET login = 'ould.sidiya', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'GBM-001';
UPDATE adherents SET login = 'mint.soueid', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'GBM-002';

-- PANPA (3)
UPDATE adherents SET login = 'ould.dedew', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'PAN-001';
UPDATE adherents SET login = 'tall.ousmane', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'PAN-002';
UPDATE adherents SET login = 'mint.elhacen', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'PAN-003';

-- SMH (3)
UPDATE adherents SET login = 'ould.boye', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SMH-001';
UPDATE adherents SET login = 'mint.moulaye', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SMH-002';
UPDATE adherents SET login = 'ould.samba', password_hash = '$2y$10$dbPPnQzBTiAEdwuiPKruCOwEQsti8BQQKXBx0EtEYAIi6U3Bxc7rC' WHERE matricule = 'SMH-003';
