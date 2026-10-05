<?php

/**
 * GET /api/statistiques
 * Returns statistics data for charts and KPIs.
 */

$user = requireAuth();
$oid  = orgId();

// ── KPI: Total depenses IPM ──
$stmt = db()->prepare('SELECT COALESCE(SUM(part_ipm), 0) FROM prises_en_charge WHERE org_id = ?');
$stmt->execute([$oid]);
$depensesIpm = (int)$stmt->fetchColumn();

// ── KPI: Moyenne par PEC ──
$stmt = db()->prepare('SELECT COUNT(*) FROM prises_en_charge WHERE org_id = ?');
$stmt->execute([$oid]);
$totalPec = (int)$stmt->fetchColumn();
$moyennePec = $totalPec > 0 ? round($depensesIpm / $totalPec) : 0;

// ── KPI: Total factures ──
$stmt = db()->prepare('SELECT COUNT(*) FROM prises_en_charge WHERE org_id = ? AND statut = "facturee"');
$stmt->execute([$oid]);
$totalFactures = (int)$stmt->fetchColumn();

// ── KPI: Entreprises clientes ──
$stmt = db()->prepare('SELECT COUNT(*) FROM entreprises WHERE org_id = ? AND statut = "actif"');
$stmt->execute([$oid]);
$entreprisesClientes = (int)$stmt->fetchColumn();

// ── Chart: Depenses par entreprise ──
$stmt = db()->prepare('
    SELECT e.raison_sociale AS name, COALESCE(SUM(pec.part_ipm), 0) AS total
    FROM prises_en_charge pec
    JOIN adherents a ON a.id = pec.adherent_id
    JOIN entreprises e ON e.id = a.entreprise_id
    WHERE pec.org_id = ?
    GROUP BY e.id, e.raison_sociale
    ORDER BY total DESC
    LIMIT 10
');
$stmt->execute([$oid]);
$depensesParEntreprise = $stmt->fetchAll();

// ── Chart: PEC par statut ──
$stmt = db()->prepare('
    SELECT statut, COUNT(*) AS count
    FROM prises_en_charge WHERE org_id = ?
    GROUP BY statut
');
$stmt->execute([$oid]);
$pecParStatut = $stmt->fetchAll();

// ── Chart: Evolution des depenses (12 derniers mois) ──
$stmt = db()->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COALESCE(SUM(part_ipm), 0) AS total
    FROM prises_en_charge
    WHERE org_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$oid]);
$evolutionDepenses = $stmt->fetchAll();

// ── Chart: Top prestataires ──
$stmt = db()->prepare('
    SELECT p.nom AS name, COALESCE(SUM(pec.part_ipm), 0) AS total
    FROM prises_en_charge pec
    JOIN prestataires p ON p.id = pec.prestataire_id
    WHERE pec.org_id = ?
    GROUP BY p.id, p.nom
    ORDER BY total DESC
    LIMIT 6
');
$stmt->execute([$oid]);
$topPrestataires = $stmt->fetchAll();

jsonResponse([
    'depenses_ipm'           => $depensesIpm,
    'moyenne_pec'            => $moyennePec,
    'total_factures'         => $totalFactures,
    'entreprises_clientes'   => $entreprisesClientes,
    'depenses_par_entreprise' => $depensesParEntreprise,
    'pec_par_statut'         => $pecParStatut,
    'evolution_depenses'     => $evolutionDepenses,
    'top_prestataires'       => $topPrestataires,
]);
