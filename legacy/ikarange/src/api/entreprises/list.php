<?php

/**
 * GET /api/entreprises
 * Returns paginated list of entreprises for the current organization.
 * Supports ?search= parameter (searches raison_sociale, ninea).
 */

$user = requireAuth();
$oid  = orgId();

$search = trim($_GET['search'] ?? '');

$where  = 'WHERE org_id = ?';
$params = [$oid];

if ($search !== '') {
    $where  .= ' AND (raison_sociale LIKE ? OR ninea LIKE ?)';
    $like    = "%$search%";
    $params[] = $like;
    $params[] = $like;
}

// Total count
$stmt = db()->prepare("SELECT COUNT(*) FROM entreprises $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Data
$stmt = db()->prepare("
    SELECT * FROM entreprises
    $where
    ORDER BY raison_sociale ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse([
    'data'  => $rows,
    'total' => $total,
]);
