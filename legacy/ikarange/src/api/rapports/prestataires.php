<?php

/**
 * GET /api/rapports/prestataires
 * Etat du reseau de soins des prestataires agrees.
 * Params: ?type=, ?statut=, ?ville=, ?format=csv
 */

$user = requireAuth();
$oid  = orgId();

$where = ['p.org_id = ?'];
$params = [$oid];

if (!empty($_GET['type'])) {
    $where[] = 'p.type = ?';
    $params[] = $_GET['type'];
}
if (!empty($_GET['statut'])) {
    $where[] = 'p.statut = ?';
    $params[] = $_GET['statut'];
}
if (!empty($_GET['ville'])) {
    $where[] = 'p.ville LIKE ?';
    $params[] = '%' . $_GET['ville'] . '%';
}

$whereStr = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT p.nom, p.type, p.ville, p.adresse, p.telephone, p.email,
           p.specialites, p.statut,
           (SELECT COUNT(*) FROM prises_en_charge pec WHERE pec.prestataire_id = p.id) as nb_pec,
           (SELECT COALESCE(SUM(pec2.part_ipm), 0) FROM prises_en_charge pec2 WHERE pec2.prestataire_id = p.id) as total_ipm
    FROM prestataires p
    WHERE $whereStr
    ORDER BY p.nom
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Summary by type
$parType = [];
foreach ($rows as $r) {
    $t = $r['type'];
    if (!isset($parType[$t])) $parType[$t] = 0;
    $parType[$t]++;
}
$agrees = count(array_filter($rows, fn($r) => $r['statut'] === 'agree'));

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="etat-prestataires-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Nom', 'Type', 'Ville', 'Adresse', 'Telephone', 'Email', 'Specialites', 'Statut', 'Nb PEC', 'Total IPM'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['nom'], $r['type'], $r['ville'], $r['adresse'],
            $r['telephone'], $r['email'], $r['specialites'],
            $r['statut'], $r['nb_pec'], $r['total_ipm']
        ], ';');
    }
    fclose($out);
    exit;
}

jsonResponse([
    'summary' => [
        'total' => count($rows),
        'agrees' => $agrees,
        'par_type' => $parType,
    ],
    'data' => $rows,
]);
