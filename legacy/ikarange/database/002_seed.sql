-- ============================================================================
-- I'KARANGE - Seed Data (Mauritanie)
-- Realistic dummy data using real Mauritanian companies, cities, names
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- ENTREPRISES (12 companies - real Mauritanian businesses)
-- ============================================================================
INSERT INTO `entreprises` (`id`, `org_id`, `raison_sociale`, `ninea`, `telephone`, `email`, `secteur_activite`, `adresse`, `nombre_employes`, `taux_cotisation`, `taux_prise_en_charge`, `date_adhesion`, `statut`) VALUES
(1,  1, 'SNIM - Société Nationale Industrielle et Minière',  'MR-2024-00145', '+222 45 25 00 00', 'contact@snim.mr',          'Mines & Industrie',       'Zone Industrielle, Nouadhibou',         4200, 5.50, 80, '2024-06-01', 'actif'),
(2,  1, 'Mauritel SA',                                                       'MR-2024-00278', '+222 45 29 29 29', 'rh@mauritel.mr',            'Télécommunications', 'Avenue Gamal Abdel Nasser, Nouakchott', 850,  4.00, 80, '2024-07-15', 'actif'),
(3,  1, 'Banque Centrale de Mauritanie',                                     'MR-2024-00312', '+222 45 25 22 06', 'drh@bcm.mr',                'Banque & Finance',        'Avenue de l\'Indépendance, Nouakchott', 620, 6.00, 85, '2024-05-10', 'actif'),
(4,  1, 'SOMELEC - Société Mauritanienne d\'Électricité','MR-2024-00089', '+222 45 25 18 09', 'contact@somelec.mr',        'Énergie',            'Rue Ahmed Ould Mohamed, Nouakchott',    1500, 5.00, 80, '2024-08-01', 'actif'),
(5,  1, 'Chinguitel SA',                                                     'MR-2024-00456', '+222 22 00 00 00', 'info@chinguitel.mr',        'Télécommunications', 'Ilot K, Nouakchott',                   720,  4.50, 80, '2024-09-01', 'actif'),
(6,  1, 'Kinross Tasiast',                                                   'MR-2024-00567', '+222 45 25 88 00', 'hr@kinross-tasiast.mr',     'Mines & Or',              'Camp Tasiast, Inchiri',                 2800, 7.00, 90, '2024-04-15', 'actif'),
(7,  1, 'Générale de Banque de Mauritanie (GBM)',                  'MR-2024-00623', '+222 45 25 86 02', 'drh@gbm.mr',                'Banque & Finance',        'Avenue du Roi Faysal, Nouakchott',      380,  5.00, 80, '2024-10-01', 'actif'),
(8,  1, 'Port Autonome de Nouakchott (PANPA)',                               'MR-2024-00701', '+222 45 25 09 72', 'rh@panpa.mr',               'Transport & Logistique',  'Zone Portuaire, Nouakchott',            950,  4.50, 80, '2024-06-15', 'actif'),
(9,  1, 'Société Mauritanienne des Hydrocarbures (SMH)',          'MR-2024-00834', '+222 45 29 12 00', 'contact@smh.mr',            'Pétrole & Gaz',     'Tevragh Zeina, Nouakchott',             540,  5.50, 85, '2024-07-01', 'actif'),
(10, 1, 'ATTM - Agence pour la Transformation Technologique',               'MR-2024-00912', '+222 45 25 34 56', 'info@attm.mr',              'Technologie',             'Ksar, Nouakchott',                      180,  4.00, 75, '2024-11-01', 'actif'),
(11, 1, 'IMROP - Institut Mauritanien de Recherche Océanographique',   'MR-2024-00998', '+222 45 74 51 24', 'direction@imrop.mr',        'Recherche & Pêche', 'Port de Pêche, Nouadhibou',        290,  4.00, 80, '2025-01-10', 'actif'),
(12, 1, 'Ciments de Mauritanie (CIMAF)',                                     'MR-2024-01045', '+222 45 25 67 89', 'rh@cimaf.mr',               'Matériaux & BTP',   'Zone Industrielle, Nouakchott',          420,  5.00, 80, '2025-02-01', 'actif');


-- ============================================================================
-- ADHERENTS (60 members with Mauritanian names)
-- ============================================================================
INSERT INTO `adherents` (`id`, `org_id`, `entreprise_id`, `nom`, `prenom`, `matricule`, `sexe`, `date_naissance`, `telephone`, `categorie`, `plafond_annuel`, `date_adhesion`, `statut`) VALUES
-- SNIM employees
(1,  1, 1, 'Ould Dah',        'Mohamed',      'SNIM-001', 'masculin', '1985-03-12', '+222 36 12 34 56', 'titulaire', 2000000, '2024-06-01', 'actif'),
(2,  1, 1, 'Mint Sidi',       'Fatimetou',     'SNIM-002', 'feminin',  '1990-07-22', '+222 46 23 45 67', 'titulaire', 2000000, '2024-06-01', 'actif'),
(3,  1, 1, 'Ould Cheikh',     'Ahmed',         'SNIM-003', 'masculin', '1982-11-05', '+222 36 34 56 78', 'titulaire', 2000000, '2024-06-01', 'actif'),
(4,  1, 1, 'Ould Abdallahi',  'Sidi Mohamed',  'SNIM-004', 'masculin', '1988-01-30', '+222 46 45 67 89', 'titulaire', 2000000, '2024-06-15', 'actif'),
(5,  1, 1, 'Mint Bowba',      'Mariem',        'SNIM-005', 'feminin',  '1993-09-18', '+222 36 56 78 90', 'titulaire', 1500000, '2024-06-15', 'actif'),
(6,  1, 1, 'Ould Sidi',       'Abdoulaye',     'SNIM-006', 'masculin', '1979-04-25', '+222 46 67 89 01', 'titulaire', 2000000, '2024-07-01', 'actif'),

-- Mauritel employees
(7,  1, 2, 'Ba',              'Mamadou',       'MAU-001',  'masculin', '1987-05-14', '+222 22 12 34 56', 'titulaire', 1800000, '2024-07-15', 'actif'),
(8,  1, 2, 'Diallo',          'Aissata',       'MAU-002',  'feminin',  '1991-12-03', '+222 22 23 45 67', 'titulaire', 1800000, '2024-07-15', 'actif'),
(9,  1, 2, 'Sy',              'Ousmane',       'MAU-003',  'masculin', '1986-08-19', '+222 22 34 56 78', 'titulaire', 1800000, '2024-08-01', 'actif'),
(10, 1, 2, 'Ndiaye',          'Aminata',       'MAU-004',  'feminin',  '1994-02-28', '+222 22 45 67 89', 'titulaire', 1500000, '2024-08-01', 'actif'),
(11, 1, 2, 'Ould Mohamed',    'Moctar',        'MAU-005',  'masculin', '1983-10-07', '+222 22 56 78 90', 'titulaire', 1800000, '2024-08-15', 'actif'),

-- BCM employees
(12, 1, 3, 'Ould Ghazouani',  'Ibrahim',       'BCM-001',  'masculin', '1980-06-15', '+222 45 11 22 33', 'titulaire', 2500000, '2024-05-10', 'actif'),
(13, 1, 3, 'Mint Bilal',      'Khadijetou',    'BCM-002',  'feminin',  '1989-03-21', '+222 45 22 33 44', 'titulaire', 2500000, '2024-05-10', 'actif'),
(14, 1, 3, 'Ould Maouloud',   'Cheikh',        'BCM-003',  'masculin', '1984-12-10', '+222 45 33 44 55', 'titulaire', 2500000, '2024-05-15', 'actif'),
(15, 1, 3, 'Ould Brahim',     'Yahya',         'BCM-004',  'masculin', '1978-09-02', '+222 45 44 55 66', 'titulaire', 2500000, '2024-06-01', 'actif'),
(16, 1, 3, 'Mint Cheikh',     'Malouma',       'BCM-005',  'feminin',  '1992-07-30', '+222 45 55 66 77', 'titulaire', 2000000, '2024-06-01', 'actif'),

-- SOMELEC employees
(17, 1, 4, 'Ould Boubacar',   'Moussa',        'SOM-001',  'masculin', '1986-01-17', '+222 36 11 22 33', 'titulaire', 1800000, '2024-08-01', 'actif'),
(18, 1, 4, 'Kane',            'Oumar',         'SOM-002',  'masculin', '1990-04-05', '+222 36 22 33 44', 'titulaire', 1800000, '2024-08-01', 'actif'),
(19, 1, 4, 'Mint Naha',       'Vatma',         'SOM-003',  'feminin',  '1988-11-23', '+222 36 33 44 55', 'titulaire', 1800000, '2024-08-15', 'actif'),
(20, 1, 4, 'Ould Horma',      'Mohamed Lemine','SOM-004',  'masculin', '1981-06-08', '+222 36 44 55 66', 'titulaire', 2000000, '2024-08-15', 'actif'),
(21, 1, 4, 'Diop',            'Ibrahima',      'SOM-005',  'masculin', '1995-02-14', '+222 36 55 66 77', 'titulaire', 1500000, '2024-09-01', 'actif'),

-- Chinguitel employees
(22, 1, 5, 'Ould Jiddou',     'Isselmou',      'CHI-001',  'masculin', '1987-09-12', '+222 33 11 22 33', 'titulaire', 1800000, '2024-09-01', 'actif'),
(23, 1, 5, 'Mint Mohamed',    'Lalla',         'CHI-002',  'feminin',  '1993-05-28', '+222 33 22 33 44', 'titulaire', 1500000, '2024-09-01', 'actif'),
(24, 1, 5, 'Ould Sid Ahmed',  'Boubacar',      'CHI-003',  'masculin', '1985-03-03', '+222 33 33 44 55', 'titulaire', 1800000, '2024-09-15', 'actif'),
(25, 1, 5, 'Sow',             'Hawa',          'CHI-004',  'feminin',  '1991-08-17', '+222 33 44 55 66', 'titulaire', 1500000, '2024-10-01', 'actif'),

-- Kinross Tasiast employees
(26, 1, 6, 'Ould Vall',       'Mohamed Mahmoud','KIN-001', 'masculin', '1983-12-20', '+222 46 11 22 33', 'titulaire', 3000000, '2024-04-15', 'actif'),
(27, 1, 6, 'Ould Moulaye',    'Hamady',        'KIN-002',  'masculin', '1980-07-09', '+222 46 22 33 44', 'titulaire', 3000000, '2024-04-15', 'actif'),
(28, 1, 6, 'Mint Abdel Aziz', 'Tekber',        'KIN-003',  'feminin',  '1989-01-14', '+222 46 33 44 55', 'titulaire', 2500000, '2024-05-01', 'actif'),
(29, 1, 6, 'Ould Bah',        'Mohamedou',     'KIN-004',  'masculin', '1977-05-30', '+222 46 44 55 66', 'titulaire', 3000000, '2024-05-01', 'actif'),
(30, 1, 6, 'Camara',          'Djiby',         'KIN-005',  'masculin', '1992-10-11', '+222 46 55 66 77', 'titulaire', 2500000, '2024-05-15', 'actif'),
(31, 1, 6, 'Ould Zein',       'Sidi',          'KIN-006',  'masculin', '1985-06-22', '+222 46 66 77 88', 'titulaire', 3000000, '2024-06-01', 'actif'),

-- GBM employees
(32, 1, 7, 'Ould Sidiya',     'Taki',          'GBM-001',  'masculin', '1984-04-18', '+222 45 71 22 33', 'titulaire', 2200000, '2024-10-01', 'actif'),
(33, 1, 7, 'Mint Soueid Ahmed','Salka',        'GBM-002',  'feminin',  '1990-11-07', '+222 45 72 33 44', 'titulaire', 2000000, '2024-10-01', 'actif'),
(34, 1, 7, 'Ould Mohamed Vall','Ahmedou',      'GBM-003',  'masculin', '1986-08-25', '+222 45 73 44 55', 'titulaire', 2200000, '2024-10-15', 'actif'),

-- PANPA employees
(35, 1, 8, 'Ould Dedew',      'Mohamed El Moctar','PAN-001','masculin','1981-02-12', '+222 45 09 11 22', 'titulaire', 1800000, '2024-06-15', 'actif'),
(36, 1, 8, 'Tall',            'Ousmane',       'PAN-002',  'masculin', '1988-07-06', '+222 45 09 22 33', 'titulaire', 1800000, '2024-06-15', 'actif'),
(37, 1, 8, 'Mint El Hacen',   'Nana',          'PAN-003',  'feminin',  '1994-01-28', '+222 45 09 33 44', 'titulaire', 1500000, '2024-07-01', 'actif'),
(38, 1, 8, 'Ould Boudida',    'Isselmou',      'PAN-004',  'masculin', '1979-09-15', '+222 45 09 44 55', 'titulaire', 2000000, '2024-07-01', 'actif'),
(39, 1, 8, 'Dieng',           'Abdoul',        'PAN-005',  'masculin', '1991-05-20', '+222 45 09 55 66', 'titulaire', 1500000, '2024-07-15', 'actif'),

-- SMH employees
(40, 1, 9, 'Ould Boye',       'Cheikh Sid Ahmed','SMH-001','masculin','1983-10-30', '+222 45 29 11 22', 'titulaire', 2200000, '2024-07-01', 'actif'),
(41, 1, 9, 'Mint Moulaye',    'Aicha',         'SMH-002',  'feminin',  '1990-06-14', '+222 45 29 22 33', 'titulaire', 2000000, '2024-07-01', 'actif'),
(42, 1, 9, 'Ould Samba',      'Abdallahi',     'SMH-003',  'masculin', '1987-03-08', '+222 45 29 33 44', 'titulaire', 2200000, '2024-07-15', 'actif'),
(43, 1, 9, 'Ba',              'Thierno',       'SMH-004',  'masculin', '1982-12-25', '+222 45 29 44 55', 'titulaire', 2200000, '2024-08-01', 'actif'),

-- ATTM employees
(44, 1, 10, 'Ould Hamoud',    'Mohamed Salem', 'ATT-001',  'masculin', '1993-04-02', '+222 45 25 11 22', 'titulaire', 1500000, '2024-11-01', 'actif'),
(45, 1, 10, 'Mint Ely',       'Meymouna',      'ATT-002',  'feminin',  '1996-08-19', '+222 45 25 22 33', 'titulaire', 1500000, '2024-11-01', 'actif'),
(46, 1, 10, 'Ould Ahmed',     'Abderrahmane',  'ATT-003',  'masculin', '1991-11-11', '+222 45 25 33 44', 'titulaire', 1500000, '2024-11-15', 'actif'),

-- IMROP employees
(47, 1, 11, 'Ould Taleb',     'Mohameden',     'IMR-001',  'masculin', '1984-05-16', '+222 45 74 11 22', 'titulaire', 1600000, '2025-01-10', 'actif'),
(48, 1, 11, 'Mint Ahmed Ould Dah','Mounina',   'IMR-002',  'feminin',  '1992-09-03', '+222 45 74 22 33', 'titulaire', 1600000, '2025-01-10', 'actif'),
(49, 1, 11, 'Ould Mohamed Lemine','Zein',      'IMR-003',  'masculin', '1988-02-27', '+222 45 74 33 44', 'titulaire', 1600000, '2025-01-15', 'actif'),
(50, 1, 11, 'Camara',          'Mamoudou',     'IMR-004',  'masculin', '1986-07-12', '+222 45 74 44 55', 'titulaire', 1600000, '2025-02-01', 'actif'),

-- CIMAF employees
(51, 1, 12, 'Ould Baba',      'Mohamed Yahya', 'CIM-001',  'masculin', '1985-10-09', '+222 45 25 61 22', 'titulaire', 1700000, '2025-02-01', 'actif'),
(52, 1, 12, 'Mint Sidi Mohamed','Diye',         'CIM-002',  'feminin',  '1990-04-15', '+222 45 25 62 33', 'titulaire', 1500000, '2025-02-01', 'actif'),
(53, 1, 12, 'Ould Bechir',    'Sidi Ould',     'CIM-003',  'masculin', '1981-01-20', '+222 45 25 63 44', 'titulaire', 1700000, '2025-02-15', 'actif'),
(54, 1, 12, 'Sall',           'Mamadou',       'CIM-004',  'masculin', '1989-06-30', '+222 45 25 64 55', 'titulaire', 1500000, '2025-02-15', 'actif'),

-- Additional adherents for variety
(55, 1, 1,  'Ould Mkhaitir',  'Bilal',         'SNIM-007', 'masculin', '1991-03-05', '+222 36 77 88 99', 'titulaire', 2000000, '2024-07-01', 'actif'),
(56, 1, 2,  'Mint Cheikhna',  'Salimata',      'MAU-006',  'feminin',  '1995-12-18', '+222 22 67 78 89', 'titulaire', 1500000, '2024-09-01', 'actif'),
(57, 1, 4,  'Ould Cheikh Ahmed','Mohamedhen',  'SOM-006',  'masculin', '1984-08-14', '+222 36 66 77 88', 'titulaire', 1800000, '2024-09-15', 'actif'),
(58, 1, 6,  'Ould Heydallah', 'Mohamed Khouna','KIN-007',  'masculin', '1978-11-22', '+222 46 77 88 99', 'titulaire', 3000000, '2024-06-01', 'actif'),
(59, 1, 8,  'Mint Daddah',    'Kadiata',       'PAN-006',  'feminin',  '1993-07-09', '+222 45 09 66 77', 'titulaire', 1500000, '2024-08-01', 'actif'),
(60, 1, 9,  'Ould Mohamed Abdallahi','Ely',    'SMH-005',  'masculin', '1986-05-03', '+222 45 29 55 66', 'titulaire', 2200000, '2024-08-15', 'actif');


-- ============================================================================
-- PRESTATAIRES (10 healthcare providers in Mauritania)
-- ============================================================================
INSERT INTO `prestataires` (`id`, `org_id`, `nom`, `type`, `ville`, `adresse`, `telephone`, `email`, `specialites`, `statut`) VALUES
(1,  1, 'Centre Hospitalier National',              'hopital',         'Nouakchott', 'Avenue Gamal Abdel Nasser, Tevragh Zeina',   '+222 45 25 21 35', 'contact@chn.mr',               'Médecine générale, Chirurgie, Urgences, Cardiologie',  'agree'),
(2,  1, 'Clinique Kissi',                           'clinique',        'Nouakchott', 'Ilot K, Tevragh Zeina',                      '+222 45 25 39 00', 'contact@cliniquekissi.mr',      'Médecine générale, Pédiatrie, Gynécologie', 'agree'),
(3,  1, 'Polyclinique Teyarett',                    'clinique',        'Nouakchott', 'Teyarett, Nouakchott',                       '+222 45 25 17 50', 'contact@polyclinique-tey.mr',   'Médecine interne, Dermatologie, ORL',                             'agree'),
(4,  1, 'Pharmacie Centrale de Nouakchott',         'pharmacie',       'Nouakchott', 'Marché Capitale, Ksar',                 '+222 45 25 14 20', 'pharma-centrale@mr.mr',         'Pharmacie générale',                                        'agree'),
(5,  1, 'Laboratoire National de Santé Publique', 'laboratoire',  'Nouakchott', 'Ksar, Nouakchott',                           '+222 45 25 32 10', 'lnsp@sante.gov.mr',             'Analyses biochimiques, Hématologie, Microbiologie',              'agree'),
(6,  1, 'Centre d\'Imagerie Médicale Chiva',  'centre_imagerie', 'Nouakchott', 'Tevragh Zeina, Nouakchott',                  '+222 45 25 61 00', 'contact@imagerie-chiva.mr',     'Radiologie, Échographie, Scanner, IRM',                          'agree'),
(7,  1, 'Cabinet Dentaire Dr. Ould Cheikh',         'dentiste',        'Nouakchott', 'Avenue Kennedy, Ksar',                       '+222 36 88 12 34', 'dr.ouldcheikh@dental.mr',       'Soins dentaires, Orthodontie, Implantologie',                          'agree'),
(8,  1, 'Clinique Sabah',                           'clinique',        'Nouakchott', 'El Mina, Nouakchott',                        '+222 45 25 70 00', 'info@clinique-sabah.mr',        'Médecine générale, Chirurgie, Maternité',        'agree'),
(9,  1, 'Hôpital Régional de Nouadhibou', 'hopital',        'Nouadhibou', 'Centre-ville, Nouadhibou',                   '+222 45 74 00 50', 'contact@hr-nouadhibou.mr',      'Urgences, Médecine générale, Chirurgie',              'agree'),
(10, 1, 'Pharmacie El Hamd',                        'pharmacie',       'Nouakchott', 'Arafat, Nouakchott',                         '+222 45 25 98 76', 'pharmacie.elhamd@mr.mr',        'Pharmacie générale, Parapharmacie',                         'agree');


-- ============================================================================
-- UTILISATEURS PRESTATAIRES (3 provider portal users)
-- ============================================================================
INSERT INTO `utilisateurs_prestataires` (`id`, `org_id`, `prestataire_id`, `login`, `password_hash`, `nom_complet`, `is_active`) VALUES
(1, 1, 1, 'chn_admin',   '$2y$10$xz5P1Q2v3r4s5t6u7v8w9.abcdefghijklmnopqrstuvwxyz1234567', 'Dr. Ould Abdallahi Ibrahim', 1),
(2, 1, 2, 'kissi_admin',  '$2y$10$xz5P1Q2v3r4s5t6u7v8w9.abcdefghijklmnopqrstuvwxyz1234567', 'Dr. Mint Bilal Mariem',      1),
(3, 1, 4, 'pharma_admin', '$2y$10$xz5P1Q2v3r4s5t6u7v8w9.abcdefghijklmnopqrstuvwxyz1234567', 'Ould Brahim Pharmacien',     1);


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
(41, 1, 'PEC-2025-041', 58, 9, 'hospitalisation',   '2025-02-25', 680000, 90, 612000, 68000, 'Chirurgie orthopédique',                              'approuvee',  'Transfert Nouadhibou'),
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
(1, 1, 'FENT-20250115-A', 1, 462400, 462400, '2025-01-31', '2025-02-28', 'payee',     'Facturation SNIM - Janvier 2025'),
(2, 1, 'FENT-20250115-B', 2, 69600,  69600,  '2025-01-31', '2025-02-28', 'payee',     'Facturation Mauritel - Janvier 2025'),
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
-- COMPAGNIES D'ASSURANCE (5 insurance companies in Mauritania)
-- ============================================================================
INSERT INTO `compagnies_assurance` (`id`, `org_id`, `nom`, `code`, `email`, `adresse`, `telephone`, `contact_personne`, `is_active`) VALUES
(1, 1, 'NASR Assurances Mauritanie',           'NASR-MR',    'contact@nasr.mr',     'Avenue Gamal Abdel Nasser, Nouakchott',  '+222 45 25 40 50', 'Ould Brahim Mohamed',    1),
(2, 1, 'SAAR Assurances Mauritanie',           'SAAR-MR',    'info@saar.mr',        'Tevragh Zeina, Nouakchott',              '+222 45 25 33 00', 'Mint Cheikh Aminetou',   1),
(3, 1, 'GAM - Générale des Assurances de Mauritanie', 'GAM-MR', 'contact@gam.mr', 'Ksar, Nouakchott',             '+222 45 25 19 80', 'Ould Dah Sidi',          1),
(4, 1, 'Assurances et Réassurances SALAMA','SALAMA-MR', 'info@salama.mr',      'Ilot K, Tevragh Zeina, Nouakchott',      '+222 45 29 15 00', 'Ba Ibrahima',            1),
(5, 1, 'AMSA Assurances Mauritanie',           'AMSA-MR',    'contact@amsa.mr',     'Avenue Kennedy, Nouakchott',             '+222 45 25 55 10', 'Ould Abdallahi Cheikh',  1);


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
(2, 1, 'daouda',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Daouda DIAGNE',      'daouda@ikarange.mr',   '+222 36 00 11 22', 'administrateur', 1, 1, 'Administrateur principal'),
(3, 1, 'fatou',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fatou BA',            'fatou.ba@ikarange.mr', '+222 46 00 33 44', 'gestionnaire',   2, 1, 'Gestionnaire IPM'),
(4, 1, 'cheikh',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cheikh Ould Mohamed', 'cheikh@ikarange.mr',   '+222 36 00 55 66', 'gestionnaire',   2, 1, 'Gestionnaire adjoint');


SET FOREIGN_KEY_CHECKS = 1;
