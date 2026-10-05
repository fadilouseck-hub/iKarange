<?php

/**
 * GET /api/rapports/remboursements
 * Etat des remboursements (factures prestataires).
 * Params: ?prestataire_id=, ?statut=, ?date_debut=, ?date_fin=, ?format=csv
 */

$user = requireAuth();
$oid  = orgId();

$where = ['f.org_id = ?'];
$params = [$oid];

if (!empty($_GET['prestataire_id'])) {
    $where[] = 'f.prestataire_id = ?';
    $params[] = (int)$_GET['prestataire_id'];
}
if (!empty($_GET['statut'])) {
    $where[] = 'f.statut = ?';
    $params[] = $_GET['statut'];
}
if (!empty($_GET['date_debut'])) {
    $where[] = 'f.date_facture >= ?';
    $params[] = $_GET['date_debut'];
}
if (!empty($_GET['date_fin'])) {
    $where[] = 'f.date_facture <= ?';
    $params[] = $_GET['date_fin'];
}

$whereStr = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT f.numero, f.date_facture, f.date_echeance, f.montant_total, f.montant_paye,
           (f.montant_total - f.montant_paye) as reste_a_payer,
           f.statut, f.observations,
           p.nom as prestataire_nom, p.type as prestataire_type
    FROM factures_prestataires f
    LEFT JOIN prestataires p ON p.id = f.prestataire_id
    WHERE $whereStr
    ORDER BY f.date_facture DESC
    LIMIT 5000
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Summary
$totalFacture = array_sum(array_column($rows, 'montant_total'));
$totalPaye = array_sum(array_column($rows, 'montant_paye'));
$totalReste = array_sum(array_column($rows, 'reste_a_payer'));
$enAttente = count(array_filter($rows, fn($r) => in_array($r['statut'], ['en_attente', 'validee'])));
$payees = count(array_filter($rows, fn($r) => $r['statut'] === 'payee'));

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="etat-remboursements-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Numero', 'Prestataire', 'Type', 'Date facture', 'Date echeance', 'Montant total', 'Montant paye', 'Reste a payer', 'Statut', 'Observations'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['numero'], $r['prestataire_nom'], $r['prestataire_type'],
            $r['date_facture'], $r['date_echeance'], $r['montant_total'],
            $r['montant_paye'], $r['reste_a_payer'], $r['statut'], $r['observations']
        ], ';');
    }
    fclose($out);
    exit;
}

jsonResponse([
    'summary' => [
        'total_factures' => count($rows),
        'montant_total' => (int)$totalFacture,
        'montant_paye' => (int)$totalPaye,
        'reste_a_payer' => (int)$totalReste,
        'en_attente' => $enAttente,
        'payees' => $payees,
    ],
    'data' => $rows,
]);
