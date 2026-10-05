<?php

/**
 * GET /api/factures-entreprises
 * List factures entreprises with optional search.
 */

$user = requireAuth();
$oid = orgId();

$search = trim($_GET['search'] ?? '');

$where = ['fe.org_id = ?'];
$params = [$oid];

if ($search) {
    $where[] = '(fe.numero LIKE ? OR e.raison_sociale LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s]);
}

$whereClause = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT fe.*,
           e.raison_sociale AS entreprise_nom
    FROM factures_entreprises fe
    LEFT JOIN entreprises e ON e.id = fe.entreprise_id
    WHERE $whereClause
    ORDER BY fe.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse([
    'data'  => $rows,
    'total' => count($rows),
]);
