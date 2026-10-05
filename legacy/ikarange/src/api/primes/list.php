<?php

/**
 * GET /api/primes
 * List primes/cotisations with optional search.
 * Returns data + summary totals.
 */

$user = requireAuth();
$oid = orgId();

$search = trim($_GET['search'] ?? '');

$where = ['pr.org_id = ?'];
$params = [$oid];

if ($search) {
    $where[] = '(e.raison_sociale LIKE ?)';
    $s = "%$search%";
    $params[] = $s;
}

$whereClause = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT pr.*,
           e.raison_sociale AS entreprise_nom
    FROM primes pr
    LEFT JOIN entreprises e ON e.id = pr.entreprise_id
    WHERE $whereClause
    ORDER BY pr.mois DESC, e.raison_sociale ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Calculate summary totals
$total_cotisations = 0;
$cotisations_payees = 0;

foreach ($rows as $row) {
    $total_cotisations += (int)$row['montant'];
    if ($row['statut'] === 'payee') {
        $cotisations_payees += (int)$row['montant'];
    }
}

$reste_encaisser = $total_cotisations - $cotisations_payees;

jsonResponse([
    'data'    => $rows,
    'total'   => count($rows),
    'summary' => [
        'total_cotisations'  => $total_cotisations,
        'cotisations_payees' => $cotisations_payees,
        'reste_encaisser'    => $reste_encaisser,
    ],
]);
