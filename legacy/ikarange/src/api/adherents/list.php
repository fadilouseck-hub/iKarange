<?php

/**
 * GET /api/adherents
 * Returns list of adherents with optional search and filter.
 */

$user = requireAuth();
$oid  = orgId();

$search       = trim($_GET['search'] ?? '');
$entrepriseId = (int)($_GET['entreprise_id'] ?? 0);

$where  = ['a.org_id = ?'];
$params = [$oid];

if ($search !== '') {
    $where[]  = '(a.nom LIKE ? OR a.prenom LIKE ? OR a.matricule LIKE ? OR a.telephone LIKE ?)';
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($entrepriseId > 0) {
    $where[]  = 'a.entreprise_id = ?';
    $params[] = $entrepriseId;
}

$whereSQL = implode(' AND ', $where);

// Total count
$stmt = db()->prepare("SELECT COUNT(*) FROM adherents a WHERE $whereSQL");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Fetch rows with entreprise name
$stmt = db()->prepare("
    SELECT a.*, e.raison_sociale AS entreprise_nom
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE $whereSQL
    ORDER BY a.nom ASC, a.prenom ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse([
    'data'  => $rows,
    'total' => $total,
]);
