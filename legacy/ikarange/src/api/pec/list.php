<?php

/**
 * GET /api/pec
 * List prises en charge with filters.
 * Supports ?search= and ?statut= query parameters.
 */

$user = requireAuth();
$oid = orgId();

$search = trim($_GET['search'] ?? '');
$statut = trim($_GET['statut'] ?? '');

$where = ['pec.org_id = ?'];
$params = [$oid];

if ($search) {
    $where[] = '(a.nom LIKE ? OR a.prenom LIKE ? OR p.nom LIKE ? OR pec.numero LIKE ? OR e.raison_sociale LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

if ($statut) {
    $where[] = 'pec.statut = ?';
    $params[] = $statut;
}

$whereClause = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT pec.*,
           a.nom AS adherent_nom, a.prenom AS adherent_prenom,
           e.raison_sociale AS entreprise_nom,
           p.nom AS prestataire_nom
    FROM prises_en_charge pec
    LEFT JOIN adherents a ON a.id = pec.adherent_id
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    LEFT JOIN prestataires p ON p.id = pec.prestataire_id
    WHERE $whereClause
    ORDER BY pec.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse([
    'data'  => $rows,
    'total' => count($rows),
]);
