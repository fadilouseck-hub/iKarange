<?php

/**
 * GET /api/remboursements
 * List all reimbursement claims (admin).
 */

$user = requireAuth();
$oid = orgId();

$where = ['r.org_id = ?'];
$params = [$oid];

if (!empty($_GET['statut'])) {
    $where[] = 'r.statut = ?';
    $params[] = $_GET['statut'];
}
if (!empty($_GET['adherent_id'])) {
    $where[] = 'r.adherent_id = ?';
    $params[] = (int)$_GET['adherent_id'];
}

$whereStr = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT r.*, a.nom as adherent_nom, a.prenom as adherent_prenom, a.matricule,
           e.raison_sociale as entreprise_nom,
           (SELECT COUNT(*) FROM remboursement_documents rd WHERE rd.remboursement_id = r.id) as nb_docs,
           (SELECT GROUP_CONCAT(rd2.id, ':', rd2.nom_fichier SEPARATOR '||') FROM remboursement_documents rd2 WHERE rd2.remboursement_id = r.id) as docs_info
    FROM remboursements r
    LEFT JOIN adherents a ON a.id = r.adherent_id
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE $whereStr
    ORDER BY r.created_at DESC
    LIMIT 500
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Parse docs_info into array
foreach ($rows as &$row) {
    $docs = [];
    if ($row['docs_info']) {
        foreach (explode('||', $row['docs_info']) as $d) {
            [$id, $name] = explode(':', $d, 2);
            $docs[] = ['id' => (int)$id, 'nom' => $name];
        }
    }
    $row['documents'] = $docs;
    unset($row['docs_info']);
}

jsonResponse(['data' => $rows]);
