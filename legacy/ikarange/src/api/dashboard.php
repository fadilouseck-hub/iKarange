<?php

/**
 * GET /api/dashboard
 * Returns dashboard statistics.
 */

$user = requireAuth();
$oid = orgId();

// Count entreprises
$stmt = db()->prepare('SELECT COUNT(*) FROM entreprises WHERE org_id = ? AND statut = "actif"');
$stmt->execute([$oid]);
$entreprises = (int)$stmt->fetchColumn();

// Count adherents
$stmt = db()->prepare('SELECT COUNT(*) FROM adherents WHERE org_id = ?');
$stmt->execute([$oid]);
$adherents = (int)$stmt->fetchColumn();

// Count PEC + pending
$stmt = db()->prepare('SELECT COUNT(*) FROM prises_en_charge WHERE org_id = ?');
$stmt->execute([$oid]);
$pecTotal = (int)$stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM prises_en_charge WHERE org_id = ? AND statut = "en_attente"');
$stmt->execute([$oid]);
$pecEnAttente = (int)$stmt->fetchColumn();

// Total depenses IPM
$stmt = db()->prepare('SELECT COALESCE(SUM(part_ipm), 0) FROM prises_en_charge WHERE org_id = ?');
$stmt->execute([$oid]);
$depensesIpm = (int)$stmt->fetchColumn();

// Depenses par type d'acte
$stmt = db()->prepare('
    SELECT type_acte, COALESCE(SUM(part_ipm), 0) as total
    FROM prises_en_charge WHERE org_id = ?
    GROUP BY type_acte ORDER BY total DESC
');
$stmt->execute([$oid]);
$depensesParType = $stmt->fetchAll();

// Repartition PEC par statut
$stmt = db()->prepare('
    SELECT statut, COUNT(*) as count
    FROM prises_en_charge WHERE org_id = ?
    GROUP BY statut
');
$stmt->execute([$oid]);
$pecParStatut = $stmt->fetchAll();

// Factures en attente
$stmt = db()->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(montant_total - montant_paye), 0) as montant FROM factures_prestataires WHERE org_id = ? AND statut IN ('en_attente','validee')");
$stmt->execute([$oid]);
$facturesAttente = $stmt->fetch();

// Primes collectees (current year)
$currentYear = date('Y');
$stmt = db()->prepare("SELECT COALESCE(SUM(montant), 0) FROM primes WHERE org_id = ? AND mois LIKE ?");
$stmt->execute([$oid, "$currentYear%"]);
$primesCollectees = (int)$stmt->fetchColumn();

// Primes total (all time, for sinistralite)
$stmt = db()->prepare("SELECT COALESCE(SUM(montant), 0) FROM primes WHERE org_id = ?");
$stmt->execute([$oid]);
$primesTotal = (int)$stmt->fetchColumn();

// Taux de sinistralite
$tauxSinistralite = $primesTotal > 0 ? round($depensesIpm / $primesTotal * 100, 1) : 0;

// Prestataires agrees
$stmt = db()->prepare("SELECT COUNT(*) FROM prestataires WHERE org_id = ? AND statut = 'agree'");
$stmt->execute([$oid]);
$prestatairesAgrees = (int)$stmt->fetchColumn();

// Volume mensuel (12 derniers mois)
$typeActeFilter = $_GET['type_acte'] ?? '';
$volumeWhere = 'WHERE p.org_id = ? AND p.date_soins >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)';
$volumeParams = [$oid];
if ($typeActeFilter && $typeActeFilter !== 'tous') {
    $volumeWhere .= ' AND p.type_acte = ?';
    $volumeParams[] = $typeActeFilter;
}
$stmt = db()->prepare("
    SELECT DATE_FORMAT(p.date_soins, '%Y-%m') as mois, COUNT(*) as nb, COALESCE(SUM(p.part_ipm), 0) as montant
    FROM prises_en_charge p
    $volumeWhere
    GROUP BY mois ORDER BY mois ASC
");
$stmt->execute($volumeParams);
$volumeMensuel = $stmt->fetchAll();

// Dernieres prises en charge
$stmt = db()->prepare('
    SELECT pec.*, a.nom as adherent_nom, a.prenom as adherent_prenom,
           p.nom as prestataire_nom, e.raison_sociale as entreprise_nom
    FROM prises_en_charge pec
    LEFT JOIN adherents a ON a.id = pec.adherent_id
    LEFT JOIN prestataires p ON p.id = pec.prestataire_id
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE pec.org_id = ?
    ORDER BY pec.created_at DESC
    LIMIT 10
');
$stmt->execute([$oid]);
$recentPec = $stmt->fetchAll();

jsonResponse([
    'entreprises'             => $entreprises,
    'adherents'               => $adherents,
    'pec_total'               => $pecTotal,
    'pec_en_attente'          => $pecEnAttente,
    'depenses_ipm'            => $depensesIpm,
    'depenses_par_type'       => $depensesParType,
    'pec_par_statut'          => $pecParStatut,
    'recent_pec'              => $recentPec,
    'factures_attente_count'  => (int)$facturesAttente['cnt'],
    'factures_attente_montant'=> (int)$facturesAttente['montant'],
    'primes_collectees'       => $primesCollectees,
    'taux_sinistralite'       => $tauxSinistralite,
    'prestataires_agrees'     => $prestatairesAgrees,
    'volume_mensuel'          => $volumeMensuel,
]);
