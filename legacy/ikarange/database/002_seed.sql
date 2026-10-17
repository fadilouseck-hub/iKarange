-- SYNTHETIC TEST FIXTURES ONLY: fictional people and records; non-deliverable email/phone contacts.
-- ============================================================================
-- I'KARANGE - Seed Data (Sénégal)
-- Fictional Senegalese people and companies; geographic place names only are real.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- ENTREPRISES (12 fictional Senegalese businesses)
-- ============================================================================
INSERT INTO `entreprises` (`id`, `org_id`, `raison_sociale`, `ninea`, `telephone`, `email`, `secteur_activite`, `adresse`, `nombre_employes`, `taux_cotisation`, `taux_prise_en_charge`, `date_adhesion`, `statut`) VALUES
(1,  1, 'Teranga Industries',  'MR-2024-00145', '+221 00 000 0001', 'contact@snim-mr.example.invalid',          'Mines & Industrie',       'Plateau, Dakar',         4200, 5.50, 80, '2024-06-01', 'actif'),
(2,  1, 'Baobab Télécom',                                                       'MR-2024-00278', '+221 00 000 0002', 'rh@mauritel-mr.example.invalid',            'Télécommunications', 'Plateau, Dakar', 850,  4.00, 80, '2024-07-15', 'actif'),
(3,  1, 'Banque du Cayor',                                     'MR-2024-00312', '+221 00 000 0003', 'drh@bcm-mr.example.invalid',                'Banque & Finance',        'Sor, Saint-Louis', 620, 6.00, 85, '2024-05-10', 'actif'),
(4,  1, 'Jamm Énergie','MR-2024-00089', '+221 00 000 0004', 'contact@somelec-mr.example.invalid',        'Énergie',            'Médina, Dakar',    1500, 5.00, 80, '2024-08-01', 'actif'),
(5,  1, 'Ndam Télécom',                                                     'MR-2024-00456', '+221 00 000 0005', 'info@chinguitel-mr.example.invalid',        'Télécommunications', 'Centre-ville, Thiès',                   720,  4.50, 80, '2024-09-01', 'actif'),
(6,  1, 'Kédougou Mines',                                                   'MR-2024-00567', '+221 00 000 0006', 'hr@kinross-tasiast-mr.example.invalid',     'Mines & Or',              'Zone industrielle, Kédougou',                 2800, 7.00, 90, '2024-04-15', 'actif'),
(7,  1, 'Banque du Saloum',                  'MR-2024-00623', '+221 00 000 0007', 'drh@gbm-mr.example.invalid',                'Banque & Finance',        'Centre-ville, Kaolack',      380,  5.00, 80, '2024-10-01', 'actif'),
(8,  1, 'Teranga Logistique',                               'MR-2024-00701', '+221 00 000 0008', 'rh@panpa-mr.example.invalid',               'Transport & Logistique',  'Zone portuaire, Dakar',            950,  4.50, 80, '2024-06-15', 'actif'),
(9,  1, 'Sunu Énergie',          'MR-2024-00834', '+221 00 000 0009', 'contact@smh-mr.example.invalid',            'Pétrole & Gaz',     'Point E, Dakar',             540,  5.50, 85, '2024-07-01', 'actif'),
(10, 1, 'Jokko Technologies',               'MR-2024-00912', '+221 00 000 0010', 'info@attm-mr.example.invalid',              'Technologie',             'Sor, Saint-Louis',                      180,  4.00, 75, '2024-11-01', 'actif'),
(11, 1, 'Institut du Fleuve',   'MR-2024-00998', '+221 00 000 0011', 'direction@imrop-mr.example.invalid',        'Recherche & Pêche', 'Quai de pêche, Saint-Louis',        290,  4.00, 80, '2025-01-10', 'actif'),
(12, 1, 'Ciments du Ndiambour',                                     'MR-2024-01045', '+221 00 000 0012', 'rh@cimaf-mr.example.invalid',               'Matériaux & BTP',   'Zone industrielle, Rufisque',          420,  5.00, 80, '2025-02-01', 'actif');


-- ============================================================================
-- ADHERENTS (60 members with Senegalese names)
-- ============================================================================
INSERT INTO `adherents` (`id`, `org_id`, `entreprise_id`, `nom`, `prenom`, `matricule`, `sexe`, `date_naissance`, `telephone`, `categorie`, `plafond_annuel`, `date_adhesion`, `statut`) VALUES
-- SNIM employees
(1,  1, 1, 'Mbaye',        'Modou',      'SNIM-001', 'masculin', '1985-03-12', '+221 00 000 0013', 'titulaire', 2000000, '2024-06-01', 'actif'),
(2,  1, 1, 'Sow',       'Fatou',     'SNIM-002', 'feminin',  '1990-07-22', '+221 00 000 0014', 'titulaire', 2000000, '2024-06-01', 'actif'),
(3,  1, 1, 'Fall',     'Cheikh',         'SNIM-003', 'masculin', '1982-11-05', '+221 00 000 0015', 'titulaire', 2000000, '2024-06-01', 'actif'),
(4,  1, 1, 'Sarr',  'Modou',  'SNIM-004', 'masculin', '1988-01-30', '+221 00 000 0016', 'titulaire', 2000000, '2024-06-15', 'actif'),
(5,  1, 1, 'Seck',      'Khady',        'SNIM-005', 'feminin',  '1993-09-18', '+221 00 000 0017', 'titulaire', 1500000, '2024-06-15', 'actif'),
(6,  1, 1, 'Mbaye',       'Modou',     'SNIM-006', 'masculin', '1979-04-25', '+221 00 000 0018', 'titulaire', 2000000, '2024-07-01', 'actif'),

-- Mauritel employees
(7,  1, 2, 'Mbaye',              'Modou',       'MAU-001',  'masculin', '1987-05-14', '+221 00 000 0019', 'titulaire', 1800000, '2024-07-15', 'actif'),
(8,  1, 2, 'Gueye',          'Ndeye',       'MAU-002',  'feminin',  '1991-12-03', '+221 00 000 0020', 'titulaire', 1800000, '2024-07-15', 'actif'),
(9,  1, 2, 'Diallo',              'Modou',       'MAU-003',  'masculin', '1986-08-19', '+221 00 000 0021', 'titulaire', 1800000, '2024-08-01', 'actif'),
(10, 1, 2, 'Sow',          'Astou',       'MAU-004',  'feminin',  '1994-02-28', '+221 00 000 0022', 'titulaire', 1500000, '2024-08-01', 'actif'),
(11, 1, 2, 'Cisse',    'Mamadou',        'MAU-005',  'masculin', '1983-10-07', '+221 00 000 0023', 'titulaire', 1800000, '2024-08-15', 'actif'),

-- BCM employees
(12, 1, 3, 'Diallo',  'Ibrahima',       'BCM-001',  'masculin', '1980-06-15', '+221 00 000 0024', 'titulaire', 2500000, '2024-05-10', 'actif'),
(13, 1, 3, 'Mbaye',      'Mariama',    'BCM-002',  'feminin',  '1989-03-21', '+221 00 000 0025', 'titulaire', 2500000, '2024-05-10', 'actif'),
(14, 1, 3, 'Diouf',   'Ousmane',        'BCM-003',  'masculin', '1984-12-10', '+221 00 000 0026', 'titulaire', 2500000, '2024-05-15', 'actif'),
(15, 1, 3, 'Thiam',     'Abdoulaye',         'BCM-004',  'masculin', '1978-09-02', '+221 00 000 0027', 'titulaire', 2500000, '2024-06-01', 'actif'),
(16, 1, 3, 'Mbaye',     'Adama',       'BCM-005',  'feminin',  '1992-07-30', '+221 00 000 0028', 'titulaire', 2000000, '2024-06-01', 'actif'),

-- SOMELEC employees
(17, 1, 4, 'Sow',   'Modou',        'SOM-001',  'masculin', '1986-01-17', '+221 00 000 0029', 'titulaire', 1800000, '2024-08-01', 'actif'),
(18, 1, 4, 'Fall',            'Babacar',         'SOM-002',  'masculin', '1990-04-05', '+221 00 000 0030', 'titulaire', 1800000, '2024-08-01', 'actif'),
(19, 1, 4, 'Sarr',       'Coumba',         'SOM-003',  'feminin',  '1988-11-23', '+221 00 000 0031', 'titulaire', 1800000, '2024-08-15', 'actif'),
(20, 1, 4, 'Seck',      'Amadou','SOM-004',  'masculin', '1981-06-08', '+221 00 000 0032', 'titulaire', 2000000, '2024-08-15', 'actif'),
(21, 1, 4, 'Mbaye',            'Modou',      'SOM-005',  'masculin', '1995-02-14', '+221 00 000 0033', 'titulaire', 1500000, '2024-09-01', 'actif'),

-- Chinguitel employees
(22, 1, 5, 'Faye',     'Babacar',      'CHI-001',  'masculin', '1987-09-12', '+221 00 000 0034', 'titulaire', 1800000, '2024-09-01', 'actif'),
(23, 1, 5, 'Gueye',    'Mariama',         'CHI-002',  'feminin',  '1993-05-28', '+221 00 000 0035', 'titulaire', 1500000, '2024-09-01', 'actif'),
(24, 1, 5, 'Gueye',  'Modou',      'CHI-003',  'masculin', '1985-03-03', '+221 00 000 0036', 'titulaire', 1800000, '2024-09-15', 'actif'),
(25, 1, 5, 'Sow',             'Khady',          'CHI-004',  'feminin',  '1991-08-17', '+221 00 000 0037', 'titulaire', 1500000, '2024-10-01', 'actif'),

-- Kinross Tasiast employees
(26, 1, 6, 'Cisse',       'Modou','KIN-001', 'masculin', '1983-12-20', '+221 00 000 0038', 'titulaire', 3000000, '2024-04-15', 'actif'),
(27, 1, 6, 'Diallo',    'Modou',        'KIN-002',  'masculin', '1980-07-09', '+221 00 000 0039', 'titulaire', 3000000, '2024-04-15', 'actif'),
(28, 1, 6, 'Mbaye', 'Ndeye',        'KIN-003',  'feminin',  '1989-01-14', '+221 00 000 0040', 'titulaire', 2500000, '2024-05-01', 'actif'),
(29, 1, 6, 'Diouf',        'Pape',     'KIN-004',  'masculin', '1977-05-30', '+221 00 000 0041', 'titulaire', 3000000, '2024-05-01', 'actif'),
(30, 1, 6, 'Seck',          'Amadou',         'KIN-005',  'masculin', '1992-10-11', '+221 00 000 0042', 'titulaire', 2500000, '2024-05-15', 'actif'),
(31, 1, 6, 'Mbaye',       'Mamadou',          'KIN-006',  'masculin', '1985-06-22', '+221 00 000 0043', 'titulaire', 3000000, '2024-06-01', 'actif'),

-- GBM employees
(32, 1, 7, 'Sow',     'Ibrahima',          'GBM-001',  'masculin', '1984-04-18', '+221 00 000 0044', 'titulaire', 2200000, '2024-10-01', 'actif'),
(33, 1, 7, 'Fall','Mariama',        'GBM-002',  'feminin',  '1990-11-07', '+221 00 000 0045', 'titulaire', 2000000, '2024-10-01', 'actif'),
(34, 1, 7, 'Sarr','Ousmane',      'GBM-003',  'masculin', '1986-08-25', '+221 00 000 0046', 'titulaire', 2200000, '2024-10-15', 'actif'),

-- PANPA employees
(35, 1, 8, 'Seck',      'Modou','PAN-001','masculin','1981-02-12', '+221 00 000 0047', 'titulaire', 1800000, '2024-06-15', 'actif'),
(36, 1, 8, 'Mbaye',            'Modou',       'PAN-002',  'masculin', '1988-07-06', '+221 00 000 0048', 'titulaire', 1800000, '2024-06-15', 'actif'),
(37, 1, 8, 'Faye',   'Sokhna',          'PAN-003',  'feminin',  '1994-01-28', '+221 00 000 0049', 'titulaire', 1500000, '2024-07-01', 'actif'),
(38, 1, 8, 'Gueye',    'Babacar',      'PAN-004',  'masculin', '1979-09-15', '+221 00 000 0050', 'titulaire', 2000000, '2024-07-01', 'actif'),
(39, 1, 8, 'Diallo',           'Pape',        'PAN-005',  'masculin', '1991-05-20', '+221 00 000 0051', 'titulaire', 1500000, '2024-07-15', 'actif'),

-- SMH employees
(40, 1, 9, 'Sow',       'Amadou','SMH-001','masculin','1983-10-30', '+221 00 000 0052', 'titulaire', 2200000, '2024-07-01', 'actif'),
(41, 1, 9, 'Cisse',    'Awa',         'SMH-002',  'feminin',  '1990-06-14', '+221 00 000 0053', 'titulaire', 2000000, '2024-07-01', 'actif'),
(42, 1, 9, 'Diallo',      'Modou',     'SMH-003',  'masculin', '1987-03-08', '+221 00 000 0054', 'titulaire', 2200000, '2024-07-15', 'actif'),
(43, 1, 9, 'Mbaye',              'Ousmane',       'SMH-004',  'masculin', '1982-12-25', '+221 00 000 0055', 'titulaire', 2200000, '2024-08-01', 'actif'),

-- ATTM employees
(44, 1, 10, 'Diouf',    'Modou', 'ATT-001',  'masculin', '1993-04-02', '+221 00 000 0056', 'titulaire', 1500000, '2024-11-01', 'actif'),
(45, 1, 10, 'Thiam',       'Khady',      'ATT-002',  'feminin',  '1996-08-19', '+221 00 000 0057', 'titulaire', 1500000, '2024-11-01', 'actif'),
(46, 1, 10, 'Mbaye',     'Modou',  'ATT-003',  'masculin', '1991-11-11', '+221 00 000 0058', 'titulaire', 1500000, '2024-11-15', 'actif'),

-- IMROP employees
(47, 1, 11, 'Sow',     'Modou',     'IMR-001',  'masculin', '1984-05-16', '+221 00 000 0059', 'titulaire', 1600000, '2025-01-10', 'actif'),
(48, 1, 11, 'Fall','Ndeye',   'IMR-002',  'feminin',  '1992-09-03', '+221 00 000 0060', 'titulaire', 1600000, '2025-01-10', 'actif'),
(49, 1, 11, 'Sarr','Pape',      'IMR-003',  'masculin', '1988-02-27', '+221 00 000 0061', 'titulaire', 1600000, '2025-01-15', 'actif'),
(50, 1, 11, 'Seck',          'Amadou',     'IMR-004',  'masculin', '1986-07-12', '+221 00 000 0062', 'titulaire', 1600000, '2025-02-01', 'actif'),

-- CIMAF employees
(51, 1, 12, 'Mbaye',      'Modou', 'CIM-001',  'masculin', '1985-10-09', '+221 00 000 0063', 'titulaire', 1700000, '2025-02-01', 'actif'),
(52, 1, 12, 'Faye','Fatou',         'CIM-002',  'feminin',  '1990-04-15', '+221 00 000 0064', 'titulaire', 1500000, '2025-02-01', 'actif'),
(53, 1, 12, 'Gueye',    'Ousmane',     'CIM-003',  'masculin', '1981-01-20', '+221 00 000 0065', 'titulaire', 1700000, '2025-02-15', 'actif'),
(54, 1, 12, 'Diallo',           'Modou',       'CIM-004',  'masculin', '1989-06-30', '+221 00 000 0066', 'titulaire', 1500000, '2025-02-15', 'actif'),

-- Additional adherents for variety
(55, 1, 1,  'Sow',  'Abdoulaye',         'SNIM-007', 'masculin', '1991-03-05', '+221 00 000 0067', 'titulaire', 2000000, '2024-07-01', 'actif'),
(56, 1, 2,  'Cisse',  'Adama',      'MAU-006',  'feminin',  '1995-12-18', '+221 00 000 0068', 'titulaire', 1500000, '2024-09-01', 'actif'),
(57, 1, 4,  'Diallo','Modou',  'SOM-006',  'masculin', '1984-08-14', '+221 00 000 0069', 'titulaire', 1800000, '2024-09-15', 'actif'),
(58, 1, 6,  'Mbaye', 'Babacar','KIN-007',  'masculin', '1978-11-22', '+221 00 000 0070', 'titulaire', 3000000, '2024-06-01', 'actif'),
(59, 1, 8,  'Diouf',    'Coumba',       'PAN-006',  'feminin',  '1993-07-09', '+221 00 000 0071', 'titulaire', 1500000, '2024-08-01', 'actif'),
(60, 1, 9,  'Thiam','Amadou',    'SMH-005',  'masculin', '1986-05-03', '+221 00 000 0072', 'titulaire', 2200000, '2024-08-15', 'actif');


-- ============================================================================
-- PRESTATAIRES (10 healthcare providers in Senegal)
-- ============================================================================
INSERT INTO `prestataires` (`id`, `org_id`, `nom`, `type`, `ville`, `adresse`, `telephone`, `email`, `specialites`, `statut`) VALUES
(1,  1, 'Hôpital Diop',              'hopital',         'Tambacounda', 'Centre-ville, Dakar',   '+221 00 000 0073', 'contact@chn-mr.example.invalid',               'Médecine générale, Chirurgie, Urgences, Cardiologie',  'agree'),
(2,  1, 'Clinique Ndiaye',                           'clinique',        'Tambacounda', 'Centre-ville, Thiès',                      '+221 00 000 0074', 'contact@cliniquekissi-mr.example.invalid',      'Médecine générale, Pédiatrie, Gynécologie', 'agree'),
(3,  1, 'Clinique Fall',                    'clinique',        'Tambacounda', 'Centre-ville, Saint-Louis',                       '+221 00 000 0075', 'contact@polyclinique-tey-mr.example.invalid',   'Médecine interne, Dermatologie, ORL',                             'agree'),
(4,  1, 'Pharmacie Sarr',         'pharmacie',       'Tambacounda', 'Centre-ville, Kaolack',                 '+221 00 000 0076', 'pharma-centrale@mr-mr.example.invalid',         'Pharmacie générale',                                        'agree'),
(5,  1, 'Laboratoire Seck', 'laboratoire',  'Tambacounda', 'Sor, Saint-Louis',                           '+221 00 000 0077', 'lnsp@sante-gov-mr.example.invalid',             'Analyses biochimiques, Hématologie, Microbiologie',              'agree'),
(6,  1, 'Centre de santé Ba',  'centre_imagerie', 'Tambacounda', 'Point E, Dakar',                  '+221 00 000 0078', 'contact@imagerie-chiva-mr.example.invalid',     'Radiologie, Échographie, Scanner, IRM',                          'agree'),
(7,  1, 'Centre de santé Faye',         'dentiste',        'Tambacounda', 'Centre-ville, Diourbel',                       '+221 00 000 0079', 'dr.ouldcheikh@dental-mr.example.invalid',       'Soins dentaires, Orthodontie, Implantologie',                          'agree'),
(8,  1, 'Clinique Gueye',                           'clinique',        'Tambacounda', 'Centre-ville, Louga',                        '+221 00 000 0080', 'info@clinique-sabah-mr.example.invalid',        'Médecine générale, Chirurgie, Maternité',        'agree'),
(9,  1, 'Hôpital Diallo', 'hopital',        'Mbour', 'Centre-ville, Mbour',                   '+221 00 000 0081', 'contact@hr-nouadhibou-mr.example.invalid',      'Urgences, Médecine générale, Chirurgie',              'agree'),
(10, 1, 'Pharmacie Sow',                        'pharmacie',       'Tambacounda', 'Centre-ville, Tambacounda',                         '+221 00 000 0082', 'pharmacie.elhamd@mr-mr.example.invalid',        'Pharmacie générale, Parapharmacie',                         'agree');


-- ============================================================================
-- UTILISATEURS PRESTATAIRES (3 provider portal users)
-- ============================================================================
INSERT INTO `utilisateurs_prestataires` (`id`, `org_id`, `prestataire_id`, `login`, `password_hash`, `nom_complet`, `is_active`) VALUES
(1, 1, 1, 'chn_admin',   '$2y$10$xz5P1Q2v3r4s5t6u7v8w9.abcdefghijklmnopqrstuvwxyz1234567', 'Mamadou Diop', 1),
(2, 1, 2, 'kissi_admin',  '$2y$10$xz5P1Q2v3r4s5t6u7v8w9.abcdefghijklmnopqrstuvwxyz1234567', 'Ibrahima Ndiaye',      1),
(3, 1, 4, 'pharma_admin', '$2y$10$xz5P1Q2v3r4s5t6u7v8w9.abcdefghijklmnopqrstuvwxyz1234567', 'Cheikh Fall',     1);


-- ============================================================================
-- PRISES EN CHARGE (45 PEC - varied statuses and types)
-- ============================================================================
INSERT INTO `prises_en_charge` (`id`, `org_id`, `numero`, `adherent_id`, `prestataire_id`, `type_acte`, `date_soins`, `montant_total`, `taux_couverture`, `part_ipm`, `part_adherent`, `motif`, `statut`, `observations`) VALUES
-- Consultations
(1,  1, 'PEC-2025-001', 1,  1, 'consultation',      '2025-01-15', 25000,  80, 20000,  5000,  'Consultation médecine générale',           'approuvee',  NULL),
(2,  1, 'PEC-2025-002', 7,  2, 'consultation',      '2025-01-18', 30000,  80, 24000,  6000,  'Consultation pédiatrie',                              'approuvee',  NULL),
(3,  1, 'PEC-2025-003', 12, 1, 'consultation',      '2025-01-22', 35000,  85, 29750,  5250,  'Consultation cardiologie',                                  'reglee',     'Règlement effectué'),
(4,  1, 'PEC-2025-004', 17, 3, 'consultation',      '2025-02-03', 25000,  80, 20000,  5000,  'Consultation dermatologie',                                 'approuvee',  NULL),
(5,  1, 'PEC-2025-005', 26, 2, 'consultation',      '2025-02-10', 30000,  90, 27000,  3000,  'Consultation médecine générale',           'reglee',     NULL),
(6,  1, 'PEC-2025-006', 32, 1, 'consultation',      '2025-02-14', 25000,  80, 20000,  5000,  'Consultation ORL',                                          'facturee',   NULL),
(7,  1, 'PEC-2025-007', 40, 3, 'consultation',      '2025-02-20', 30000,  85, 25500,  4500,  'Visite médicale annuelle',                             'approuvee',  NULL),
(8,  1, 'PEC-2025-008', 44, 2, 'consultation',      '2025-03-01', 25000,  75, 18750,  6250,  'Consultation médecine générale',           'en_attente', NULL),

-- Analyses
(9,  1, 'PEC-2025-009', 2,  5, 'analyse',           '2025-01-20', 45000,  80, 36000,  9000,  'Bilan sanguin complet',                                     'approuvee',  NULL),
(10, 1, 'PEC-2025-010', 8,  5, 'analyse',           '2025-01-25', 35000,  80, 28000,  7000,  'Analyse d\'urine + NFS',                                    'reglee',     NULL),
(11, 1, 'PEC-2025-011', 13, 5, 'analyse',           '2025-02-05', 60000,  85, 51000,  9000,  'Bilan hépatique complet',                              'approuvee',  NULL),
(12, 1, 'PEC-2025-012', 22, 5, 'analyse',           '2025-02-12', 40000,  80, 32000,  8000,  'Glycémie + Bilan lipidique',                          'facturee',   NULL),
(13, 1, 'PEC-2025-013', 35, 5, 'analyse',           '2025-02-28', 55000,  80, 44000,  11000, 'Bilan thyroïdien',                                    'approuvee',  NULL),
(14, 1, 'PEC-2025-014', 47, 5, 'analyse',           '2025-03-05', 38000,  80, 30400,  7600,  'Hémogramme complet',                                  'en_attente', NULL),

-- Pharmacie
(15, 1, 'PEC-2025-015', 3,  4, 'pharmacie',         '2025-01-16', 18000,  80, 14400,  3600,  'Antibiotiques + anti-inflammatoires',                       'reglee',     NULL),
(16, 1, 'PEC-2025-016', 9,  4, 'pharmacie',         '2025-01-28', 22000,  80, 17600,  4400,  'Traitement hypertension',                                   'reglee',     NULL),
(17, 1, 'PEC-2025-017', 14, 10,'pharmacie',         '2025-02-06', 15000,  85, 12750,  2250,  'Médicaments anti-diabétiques',                   'approuvee',  NULL),
(18, 1, 'PEC-2025-018', 27, 4, 'pharmacie',         '2025-02-15', 28000,  90, 25200,  2800,  'Traitement post-opératoire',                          'reglee',     NULL),
(19, 1, 'PEC-2025-019', 36, 10,'pharmacie',         '2025-03-01', 12000,  80, 9600,   2400,  'Antalgiques + vitamines',                                   'en_attente', NULL),
(20, 1, 'PEC-2025-020', 51, 4, 'pharmacie',         '2025-03-03', 35000,  80, 28000,  7000,  'Traitement allergie chronique',                             'en_attente', NULL),

-- Hospitalisation
(21, 1, 'PEC-2025-021', 4,  1, 'hospitalisation',   '2025-01-10', 350000, 80, 280000, 70000, 'Appendicectomie',                                           'reglee',     'Séjour 3 jours'),
(22, 1, 'PEC-2025-022', 18, 8, 'hospitalisation',   '2025-02-01', 450000, 80, 360000, 90000, 'Intervention chirurgicale genou',                            'approuvee',  'Séjour 5 jours'),
(23, 1, 'PEC-2025-023', 29, 1, 'hospitalisation',   '2025-02-18', 280000, 90, 252000, 28000, 'Observation cardiaque 48h',                                  'facturee',   NULL),
(24, 1, 'PEC-2025-024', 55, 8, 'hospitalisation',   '2025-03-02', 520000, 80, 416000, 104000,'Chirurgie hernie discale',                                   'en_attente', 'En attente accord médical'),

-- Imagerie
(25, 1, 'PEC-2025-025', 5,  6, 'imagerie',          '2025-01-22', 75000,  80, 60000,  15000, 'Radiographie thoracique',                                   'reglee',     NULL),
(26, 1, 'PEC-2025-026', 20, 6, 'imagerie',          '2025-02-08', 120000, 80, 96000,  24000, 'Échographie abdominale',                               'approuvee',  NULL),
(27, 1, 'PEC-2025-027', 28, 6, 'imagerie',          '2025-02-22', 250000, 90, 225000, 25000, 'IRM cérébrale',                                  'facturee',   NULL),
(28, 1, 'PEC-2025-028', 41, 6, 'imagerie',          '2025-03-04', 85000,  85, 72250,  12750, 'Scanner abdominal',                                         'en_attente', NULL),

-- Dentaire
(29, 1, 'PEC-2025-029', 6,  7, 'dentaire',          '2025-01-25', 65000,  80, 52000,  13000, 'Soins dentaires + détartrage',                        'reglee',     NULL),
(30, 1, 'PEC-2025-030', 10, 7, 'dentaire',          '2025-02-04', 150000, 80, 120000, 30000, 'Extraction + prothèse dentaire',                      'approuvee',  NULL),
(31, 1, 'PEC-2025-031', 23, 7, 'dentaire',          '2025-02-20', 45000,  80, 36000,  9000,  'Traitement carie + plombage',                               'reglee',     NULL),
(32, 1, 'PEC-2025-032', 33, 7, 'dentaire',          '2025-03-06', 80000,  80, 64000,  16000, 'Couronne céramique',                                  'en_attente', NULL),

-- More varied PECs for recent activity
(33, 1, 'PEC-2025-033', 15, 1, 'consultation',      '2025-03-01', 25000,  85, 21250,  3750,  'Contrôle tension artérielle',                    'approuvee',  NULL),
(34, 1, 'PEC-2025-034', 21, 4, 'pharmacie',         '2025-03-02', 42000,  80, 33600,  8400,  'Traitement bronchite',                                      'en_attente', NULL),
(35, 1, 'PEC-2025-035', 30, 5, 'analyse',           '2025-03-03', 48000,  90, 43200,  4800,  'Marqueurs tumoraux',                                        'en_attente', NULL),
(36, 1, 'PEC-2025-036', 37, 2, 'consultation',      '2025-03-04', 25000,  80, 20000,  5000,  'Consultation gynécologie',                            'approuvee',  NULL),
(37, 1, 'PEC-2025-037', 42, 6, 'imagerie',          '2025-03-05', 95000,  85, 80750,  14250, 'Radiographie colonne vertébrale',                     'en_attente', NULL),
(38, 1, 'PEC-2025-038', 48, 3, 'consultation',      '2025-03-05', 30000,  80, 24000,  6000,  'Consultation médecine interne',                       'en_attente', NULL),
(39, 1, 'PEC-2025-039', 52, 8, 'consultation',      '2025-03-06', 25000,  80, 20000,  5000,  'Consultation maternité',                              'en_attente', NULL),
(40, 1, 'PEC-2025-040', 56, 1, 'consultation',      '2025-03-06', 35000,  80, 28000,  7000,  'Consultation pneumologie',                                  'en_attente', NULL),
(41, 1, 'PEC-2025-041', 58, 9, 'hospitalisation',   '2025-02-25', 680000, 90, 612000, 68000, 'Chirurgie orthopédique',                              'approuvee',  'Transfert Saint-Louis'),
(42, 1, 'PEC-2025-042', 11, 5, 'analyse',           '2025-03-07', 52000,  80, 41600,  10400, 'Bilan rénal complet',                                 'en_attente', NULL),
(43, 1, 'PEC-2025-043', 16, 7, 'dentaire',          '2025-03-07', 55000,  85, 46750,  8250,  'Soin canalaire + couronne',                                 'en_attente', NULL),
(44, 1, 'PEC-2025-044', 24, 4, 'pharmacie',         '2025-03-07', 19000,  80, 15200,  3800,  'Anti-douleurs + pansements',                                'en_attente', NULL),
(45, 1, 'PEC-2025-045', 31, 2, 'consultation',      '2025-03-08', 30000,  90, 27000,  3000,  'Visite de contrôle post-op',                          'en_attente', NULL);


-- ============================================================================
-- FACTURES PRESTATAIRES (8 invoices)
-- ============================================================================
INSERT INTO `factures_prestataires` (`id`, `org_id`, `numero`, `prestataire_id`, `montant_total`, `montant_paye`, `date_facture`, `date_echeance`, `statut`, `observations`) VALUES
(1, 1, 'FPRS-20250115-A', 1, 349750, 349750, '2025-01-31', '2025-02-28', 'payee',     'Facture janvier - CHN'),
(2, 1, 'FPRS-20250115-B', 2, 24000,  24000,  '2025-01-31', '2025-02-28', 'payee',     'Facture janvier - Clinique Kissi'),
(3, 1, 'FPRS-20250115-C', 4, 32000,  32000,  '2025-01-31', '2025-02-28', 'payee',     'Facture janvier - Pharmacie Centrale'),
(4, 1, 'FPRS-20250115-D', 5, 64000,  0,      '2025-01-31', '2025-02-28', 'en_attente','Facture janvier - LNSP'),
(5, 1, 'FPRS-20250201-A', 1, 45500,  45500,  '2025-02-28', '2025-03-31', 'payee',     'Facture février - CHN'),
(6, 1, 'FPRS-20250201-B', 6, 381000, 200000, '2025-02-28', '2025-03-31', 'partiel',   'Facture février - Centre Imagerie Chiva'),
(7, 1, 'FPRS-20250201-C', 7, 208000, 0,      '2025-02-28', '2025-03-31', 'en_attente','Facture février - Cabinet Dentaire'),
(8, 1, 'FPRS-20250201-D', 8, 360000, 0,      '2025-02-28', '2025-03-31', 'validee',   'Facture février - Clinique Sabah');


-- ============================================================================
-- FACTURES ENTREPRISES (6 invoices)
-- ============================================================================
INSERT INTO `factures_entreprises` (`id`, `org_id`, `numero`, `entreprise_id`, `montant_total`, `montant_paye`, `date_facture`, `date_echeance`, `statut`, `observations`) VALUES
(1, 1, 'FENT-20250115-A', 1, 462400, 462400, '2025-01-31', '2025-02-28', 'payee',     'Facturation Teranga Industries - Janvier 2025'),
(2, 1, 'FENT-20250115-B', 2, 69600,  69600,  '2025-01-31', '2025-02-28', 'payee',     'Facturation Baobab Télécom - Janvier 2025'),
(3, 1, 'FENT-20250115-C', 3, 93750,  0,      '2025-01-31', '2025-02-28', 'en_attente','Facturation BCM - Janvier 2025'),
(4, 1, 'FENT-20250201-A', 6, 1162200,1162200,'2025-02-28', '2025-03-31', 'payee',     'Facturation Kinross - Février 2025'),
(5, 1, 'FENT-20250201-B', 4, 20000,  0,      '2025-02-28', '2025-03-31', 'validee',   'Facturation SOMELEC - Février 2025'),
(6, 1, 'FENT-20250201-C', 8, 29600,  0,      '2025-02-28', '2025-03-31', 'en_attente','Facturation PANPA - Février 2025');


-- ============================================================================
-- PRIMES / COTISATIONS (monthly premiums)
-- ============================================================================
INSERT INTO `primes` (`id`, `org_id`, `entreprise_id`, `mois`, `montant`, `nombre_adherents`, `statut`, `date_paiement`) VALUES
-- Janvier 2025
(1,  1, 1,  '2025-01', 1386000, 7,  'payee',      '2025-02-05'),
(2,  1, 2,  '2025-01', 396000,  6,  'payee',      '2025-02-10'),
(3,  1, 3,  '2025-01', 465000,  5,  'payee',      '2025-02-08'),
(4,  1, 4,  '2025-01', 540000,  6,  'facturee',   NULL),
(5,  1, 6,  '2025-01', 1176000, 7,  'payee',      '2025-02-03'),
(6,  1, 7,  '2025-01', 228000,  3,  'payee',      '2025-02-12'),
(7,  1, 8,  '2025-01', 427500,  5,  'facturee',   NULL),
(8,  1, 9,  '2025-01', 475200,  5,  'payee',      '2025-02-07'),

-- Fevrier 2025
(9,  1, 1,  '2025-02', 1386000, 7,  'payee',      '2025-03-04'),
(10, 1, 2,  '2025-02', 396000,  6,  'a_facturer', NULL),
(11, 1, 3,  '2025-02', 465000,  5,  'payee',      '2025-03-06'),
(12, 1, 4,  '2025-02', 540000,  6,  'a_facturer', NULL),
(13, 1, 5,  '2025-02', 324000,  4,  'a_facturer', NULL),
(14, 1, 6,  '2025-02', 1176000, 7,  'payee',      '2025-03-02'),
(15, 1, 8,  '2025-02', 427500,  5,  'a_facturer', NULL),
(16, 1, 9,  '2025-02', 475200,  5,  'facturee',   NULL),

-- Mars 2025
(17, 1, 1,  '2025-03', 1386000, 7,  'a_facturer', NULL),
(18, 1, 2,  '2025-03', 396000,  6,  'a_facturer', NULL),
(19, 1, 3,  '2025-03', 465000,  5,  'a_facturer', NULL),
(20, 1, 6,  '2025-03', 1176000, 7,  'a_facturer', NULL),
(21, 1, 10, '2025-03', 180000,  3,  'a_facturer', NULL),
(22, 1, 11, '2025-03', 256000,  4,  'a_facturer', NULL),
(23, 1, 12, '2025-03', 340000,  4,  'a_facturer', NULL);


-- ============================================================================
-- COMPAGNIES D'ASSURANCE (5 insurance companies in Senegal)
-- ============================================================================
INSERT INTO `compagnies_assurance` (`id`, `org_id`, `nom`, `code`, `email`, `adresse`, `telephone`, `contact_personne`, `is_active`) VALUES
(1, 1, 'Assur Plus',           'NASR-MR',    'contact@nasr-mr.example.invalid',     'Plateau, Dakar',  '+221 00 000 0083', 'Mamadou Diop',    1),
(2, 1, 'Teranga Assurances',           'SAAR-MR',    'info@saar-mr.example.invalid',        'Point E, Dakar',              '+221 00 000 0084', 'Ibrahima Ndiaye',   1),
(3, 1, 'Sunu Protection', 'GAM-MR', 'contact@gam-mr.example.invalid', 'Sor, Saint-Louis',             '+221 00 000 0085', 'Cheikh Fall',          1),
(4, 1, 'Baobab Assurance','SALAMA-MR', 'info@salama-mr.example.invalid',      'Médina, Dakar',      '+221 00 000 0086', 'Ousmane Sarr',            1),
(5, 1, 'Jamm Assurance',           'AMSA-MR',    'contact@amsa-mr.example.invalid',     'Centre-ville, Thiès',             '+221 00 000 0087', 'Abdoulaye Seck',  1);


-- ============================================================================
-- ACCESS PROFILES (3 profiles)
-- ============================================================================
INSERT INTO `access_profiles` (`id`, `org_id`, `name`, `permissions`) VALUES
(1, 1, 'Administrateur Complet', '["dashboard","entreprises","adherents","prestataires","utilisateurs-prestataires","pec","factures-prestataires","factures-entreprises","primes","statistiques","compagnies","acces"]'),
(2, 1, 'Gestionnaire IPM',       '["dashboard","entreprises","adherents","prestataires","pec","factures-prestataires","factures-entreprises","primes","statistiques"]'),
(3, 1, 'Consultation Seule',     '["dashboard","statistiques"]');


-- ============================================================================
-- ADDITIONAL USERS (2 more staff users)
-- ============================================================================
INSERT INTO `users` (`id`, `org_id`, `login`, `password_hash`, `full_name`, `email`, `phone`, `role`, `profile_id`, `is_active`, `notes`) VALUES
(2, 1, 'daouda',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ibrahima Ndiaye',      'daouda@ikarange-mr.example.invalid',   '+221 00 000 0088', 'administrateur', 1, 1, 'Administrateur principal'),
(3, 1, 'fatou',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cheikh Fall',            'fatou.ba@ikarange-mr.example.invalid', '+221 00 000 0089', 'gestionnaire',   2, 1, 'Gestionnaire IPM'),
(4, 1, 'cheikh',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ousmane Sarr', 'cheikh@ikarange-mr.example.invalid',   '+221 00 000 0090', 'gestionnaire',   2, 1, 'Gestionnaire adjoint');


SET FOREIGN_KEY_CHECKS = 1;
