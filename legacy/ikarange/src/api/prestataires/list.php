<?php

/**
 * GET /api/prestataires
 * Liste des prestataires avec recherche.
 */

$user = requireAuth();
$oid  = orgId();

$q = trim($_GET['q'] ?? '');

$where  = ['p.org_id = ?'];
$params = [$oid];

if ($q !== '') {
    $where[]  = '(p.nom LIKE ? OR p.ville LIKE ? OR p.specialites LIKE ?)';
    $like     = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);

// Total count
$stmt = db()->prepare("SELECT COUNT(*) FROM prestataires p WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Fetch rows
$stmt = db()->prepare("
    SELECT p.*
    FROM prestataires p
    WHERE $whereSql
    ORDER BY p.nom ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse([
    'data'  => $rows,
    'total' => $total,
]);
