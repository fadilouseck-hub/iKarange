<?php

/**
 * GET /api/factures-prestataires
 * List factures prestataires with optional search.
 */

$user = requireAuth();
$oid = orgId();

$search = trim($_GET['search'] ?? '');

$where = ['fp.org_id = ?'];
$params = [$oid];

if ($search) {
    $where[] = '(fp.numero LIKE ? OR p.nom LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s]);
}

$whereClause = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT fp.*,
           p.nom AS prestataire_nom,
           e.raison_sociale AS entreprise_nom,
           (SELECT COUNT(*) FROM facture_prestataire_documents fpd WHERE fpd.facture_id = fp.id) as nb_docs
    FROM factures_prestataires fp
    LEFT JOIN prestataires p ON p.id = fp.prestataire_id
    LEFT JOIN entreprises e ON e.id = fp.entreprise_id
    WHERE $whereClause
    ORDER BY fp.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Attach documents info for each facture
foreach ($rows as &$row) {
    if ((int)$row['nb_docs'] > 0) {
        $stmt = db()->prepare('SELECT id, nom_fichier FROM facture_prestataire_documents WHERE facture_id = ? ORDER BY id');
        $stmt->execute([$row['id']]);
        $row['documents'] = $stmt->fetchAll();
    } else {
        $row['documents'] = [];
    }
}

jsonResponse([
    'data'  => $rows,
    'total' => count($rows),
]);
