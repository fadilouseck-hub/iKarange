<?php

/**
 * GET /api/compagnies
 * Returns list of compagnies d'assurance for the current organization.
 * Supports ?search= parameter (searches nom, code).
 */

$user = requireAuth();
$oid  = orgId();

$search = trim($_GET['search'] ?? '');

$where  = 'WHERE org_id = ?';
$params = [$oid];

if ($search !== '') {
    $where  .= ' AND (nom LIKE ? OR code LIKE ?)';
    $like    = "%$search%";
    $params[] = $like;
    $params[] = $like;
}

// Total count
$stmt = db()->prepare("SELECT COUNT(*) FROM compagnies_assurance $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Data
$stmt = db()->prepare("
    SELECT * FROM compagnies_assurance
    $where
    ORDER BY nom ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse([
    'data'  => $rows,
    'total' => $total,
]);
